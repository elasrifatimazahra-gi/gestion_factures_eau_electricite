<?php

require_once ROOT . 'BD/Facture.php';
require_once ROOT . 'Traitement/Auth.php';
require_once ROOT . 'BD/Utilisateur.php';
require_once ROOT . 'Traitement/Facture.php';
require_once ROOT . 'lib/fpdf/fpdf.php';

/** FPDF attend de l'ISO-8859-1 : on convertit le texte UTF-8 de la base. */
function facture_pdf_txt($texte)
{
    $converti = iconv('UTF-8', 'windows-1252//TRANSLIT', (string) $texte);
    return $converti === false ? '' : $converti;
}

function facture_pdf_dh($montant)
{
    return number_format((float) $montant, 2, ',', ' ') . ' DH';
}

/** Télécharge la facture ID en PDF (URL : FacturePdf/telecharger/ID). */
function FacturePdf_telecharger($param = null)
{
    exiger_role('CLIENT');
    $idClient = facture_client_connecte();
    $idFacture = is_array($param) ? (int) ($param[0] ?? 0) : (int) $param;

    appliquerPenalites();
    $idContrat = facture_contrat_courant($idClient);
    $f = bd_facture_detail($idFacture, $idClient, $idContrat);
    if (!$f) {
        http_response_code(404);
        include ROOT . 'IHM/404.php';
        exit;
    }

    $client = $idContrat ? bd_facture_infos_contrat($idContrat, $idClient) : bd_facture_infos_client($idClient);
    $badge = facture_badge($f);
    $periode = facture_periode_dates($f['periode_mois'], $f['periode_annee']);
    $joursFactures = (int) substr($periode['fin'], 0, 2);
    $nomClient = trim(($client['prenom'] ?? '') . ' ' . ($client['nom'] ?? ''));
    $adresse = str_replace(["\r", "\n"], ' ', (string) ($client['adresse'] ?? ''));
    $reference = trim(str_replace(['FAC-', '-'], ['', ' '], $f['numero_facture']));
    $penalitePct = (float) lireParametre('PENALITE_RETARD_POURCENT', 5);
    $penaliteMin = (float) lireParametre('PENALITE_RETARD_MINIMUM_DH', 15);
    $pct = function ($x) {
        return rtrim(rtrim(number_format((float) $x, 2, ',', ''), '0'), ',');
    };
    $t = 'facture_pdf_txt';
    $vert = [22, 86, 60];
    $gris = [110, 120, 115];
    $noir = [30, 35, 32];

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    // ----- En-tête -----
    $pdf->SetTextColor(...$vert);
    $pdf->SetFont('Times', 'BI', 24);
    $pdf->Cell(90, 12, 'Amanor', 0, 0, 'L');
    $pdf->SetFont('Helvetica', 'B', 20);
    $pdf->Cell(90, 12, 'FACTURE', 0, 1, 'R');

    $meta = [
        ['N° de facture', $f['numero_facture']],
        ["Date d'émission", date('d/m/Y', strtotime($f['date_emission']))],
        ["Date d'échéance", date('d/m/Y', strtotime($f['date_echeance']))],
        ['Statut', $badge[1]],
    ];
    foreach ($meta as $m) {
        $pdf->Cell(90, 5.5, '', 0, 0);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(...$gris);
        $pdf->Cell(45, 5.5, $t($m[0]), 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(...$noir);
        $pdf->Cell(45, 5.5, $t($m[1]), 0, 1, 'R');
    }

    $y = $pdf->GetY() + 3;
    $pdf->SetDrawColor(...$vert);
    $pdf->SetLineWidth(0.8);
    $pdf->Line(15, $y, 195, $y);
    $pdf->SetLineWidth(0.2);
    $pdf->SetDrawColor(205, 212, 208);
    $y += 6;

    // ----- Deux cadres : client et période -----
    $pdf->Rect(15, $y, 88, 56);
    $pdf->Rect(107, $y, 88, 56);

    $pdf->SetXY(19, $y + 4);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(...$gris);
    $pdf->Cell(80, 4, 'CLIENT', 0, 1);
    $pdf->SetXY(19, $y + 9);
    $pdf->SetFont('Helvetica', 'B', 13);
    $pdf->SetTextColor(...$vert);
    $pdf->Cell(80, 6, $t($nomClient), 0, 1);
    $pdf->SetXY(19, $y + 16);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(...$gris);
    $pdf->Cell(80, 5, $t($adresse), 0, 1);

    $infos = [
        ['N° client', $client['numero_client'] ?? ''],
        ['N° contrat', $f['numero_contrat']],
        ['Quartier', $f['quartier'] ?: 'Non renseigné'],
        ["Type d'activité", $f['type_activite'] ?: 'Résidentiel (par défaut)'],
        ['Compteur eau', $f['numero_compteur_eau']],
        ['Compteur électricité', $f['numero_compteur_elec']],
    ];
    $yy = $y + 24;
    foreach ($infos as $i) {
        $pdf->SetXY(19, $yy);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(...$gris);
        $pdf->Cell(36, 5, $t($i[0]), 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(...$noir);
        $pdf->Cell(44, 5, $t($i[1]), 0, 0, 'R');
        $yy += 5;
    }

    $pdf->SetXY(111, $y + 4);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(...$gris);
    $pdf->Cell(80, 4, $t('PÉRIODE DE CONSOMMATION'), 0, 1);
    $pdf->SetXY(111, $y + 9);
    $pdf->SetFont('Helvetica', 'B', 13);
    $pdf->SetTextColor(...$vert);
    $pdf->Cell(80, 6, $t(facture_nom_mois($f['periode_mois']) . ' ' . $f['periode_annee']), 0, 1);
    $pdf->SetXY(111, $y + 16);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(...$gris);
    $pdf->Cell(80, 5, $t('Du ' . $periode['debut'] . ' au ' . $periode['fin']), 0, 1);
    $pdf->SetXY(111, $y + 44);
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->SetTextColor(...$vert);
    $pdf->Cell(80, 8, $t($joursFactures . ' jours facturés'), 0, 0, 'R');

    // ----- Tableau de consommation -----
    $y += 56 + 8;
    $pdf->SetXY(15, $y);
    $pdf->SetFillColor(...$vert);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 9);
    $cols = [['Service', 40, 'L'], ['Index début', 25, 'R'], ['Index fin', 25, 'R'],
             ['Consommation', 30, 'R'], ['Prix unitaire', 30, 'R'], ['Montant HT', 30, 'R']];
    foreach ($cols as $c) {
        $pdf->Cell($c[1], 8, $t($c[0]), 0, 0, $c[2], true);
    }
    $pdf->Ln();

    $lignes = [
        ['Eau', $f['index_eau_debut'], $f['index_eau_fin'], $f['conso_eau'], 'm³', $f['montant_ht_eau']],
        ['Électricité', $f['index_elec_debut'], $f['index_elec_fin'], $f['conso_elec'], 'kWh', $f['montant_ht_elec']],
    ];
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(...$noir);
    foreach ($lignes as $k => $l) {
        if ($k % 2) {
            $pdf->SetFillColor(245, 247, 246);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }
        $valeurs = [$l[0], $l[1], $l[2], $l[3] . ' ' . $l[4],
                    facture_prix_unitaire($l[5], $l[3]), facture_pdf_dh($l[5])];
        foreach ($cols as $i => $c) {
            $pdf->Cell($c[1], 9, $t($valeurs[$i]), 0, 0, $c[2], true);
        }
        $pdf->Ln();
    }

    // ----- Mode de paiement (gauche) et totaux (droite) -----
    $y = $pdf->GetY() + 8;
    $pdf->Rect(15, $y, 85, 30);
    $pdf->SetXY(19, $y + 4);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(...$gris);
    $pdf->Cell(75, 4, 'MODE DE PAIEMENT', 0, 1);
    $pdf->SetXY(19, $y + 9);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(...$vert);
    $pdf->Cell(75, 6, $t("À régler en agence"), 0, 1);
    $pdf->SetXY(19, $y + 17);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(...$gris);
    $pdf->Cell(75, 4, $t('Référence de paiement'), 0, 1);
    $pdf->SetXY(19, $y + 22);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(...$noir);
    $pdf->Cell(75, 6, $t($reference), 0, 1);

    $totaux = [
        ['Total HT', facture_pdf_dh($f['montant_ht']), false],
        ['TVA (' . $pct($f['taux_tva']) . ' %)', facture_pdf_dh($f['montant_tva']), false],
        ['Total TTC', facture_pdf_dh($f['montant_ttc']), true],
    ];
    if ((float) ($f['montant_regularisation'] ?? 0) != 0) {
        $totaux[] = ['Estimations déjà facturées', '- ' . facture_pdf_dh(abs($f['montant_regularisation'])), false];
    }
    if ((float) $f['credit_deduit'] > 0) {
        $totaux[] = ['Crédit déduit', '- ' . facture_pdf_dh($f['credit_deduit']), false];
    }
    if ($f['penalite_appliquee']) {
        $totaux[] = ['Pénalité de retard', facture_pdf_dh($f['penalite_retard']), false];
    }
    $totaux[] = ['Total à payer', facture_pdf_dh($f['montant_total_a_payer']), true];
    $totaux[] = ['Déjà payé', facture_pdf_dh($f['montant_paye']), false];

    $yt = $y;
    foreach ($totaux as $l) {
        $pdf->SetXY(110, $yt);
        $pdf->SetFont('Helvetica', $l[2] ? 'B' : '', 9.5);
        $pdf->SetTextColor(...$noir);
        $pdf->Cell(50, 7, $t($l[0]), 'B', 0, 'L');
        $pdf->Cell(35, 7, $t($l[1]), 'B', 0, 'R');
        $yt += 7;
    }
    $pdf->SetXY(110, $yt + 1);
    $pdf->SetFillColor(...$vert);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->Cell(40, 12, $t('Reste à payer'), 0, 0, 'L', true);
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->Cell(45, 12, $t(facture_pdf_dh($f['reste_a_payer'])), 0, 0, 'R', true);

    // ----- Photos des compteurs -----
    $yp = max($yt + 13, $y + 30) + 6;
    $pdf->SetXY(15, $yp);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(...$vert);
    $pdf->Cell(110, 5, 'PHOTOS DES COMPTEURS', 0, 0, 'L');
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(...$gris);
    $pdf->Cell(70, 5, $t('Relevés du ' . $periode['fin']), 0, 1, 'R');

    $photos = [
        ["Compteur d'eau", $f['photo_eau_facture'] ?? '', $f['index_eau_fin'], 15],
        ["Compteur d'électricité", $f['photo_elec_facture'] ?? '', $f['index_elec_fin'], 107],
    ];
    $yc = $yp + 7;      // haut des cadres photo
    $hc = 30;           // hauteur des cadres photo
    foreach ($photos as $p) {
        $x = $p[3];
        $pdf->SetDrawColor(205, 212, 208);
        $pdf->SetFillColor(236, 240, 238);
        $pdf->Rect($x, $yc, 88, $hc, 'DF');
        $fichier = trim((string) $p[1]) !== '' ? ROOT . ltrim($p[1], '/\\') : '';
        $affichee = false;
        if ($fichier !== '' && is_file($fichier)) {
            $dim = @getimagesize($fichier);
            if ($dim && in_array($dim[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                $ratio = min(88 / $dim[0], $hc / $dim[1]);
                $w = $dim[0] * $ratio;
                $h = $dim[1] * $ratio;
                $pdf->Image($fichier, $x + (88 - $w) / 2, $yc + ($hc - $h) / 2, $w, $h,
                    $dim[2] === IMAGETYPE_PNG ? 'PNG' : 'JPG');
                $affichee = true;
            }
        }
        if (!$affichee) {
            $pdf->SetXY($x, $yc + $hc / 2 - 2);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(...$gris);
            $pdf->Cell(88, 4, $t('Photo non disponible'), 0, 0, 'C');
        }
        $pdf->SetXY($x, $yc + $hc + 1);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(...$noir);
        $pdf->Cell(88, 4, $t($p[0] . ' - index ' . (int) $p[2]), 0, 0, 'L');
    }

    // ----- Pied de page -----
    $pdf->SetXY(15, $yc + $hc + 8);
    $pdf->SetFont('Helvetica', 'B', 8.5);
    $pdf->SetTextColor(...$noir);
    $pdf->MultiCell(180, 4.5, $t('Service Régional Eau & Électricité · Tétouan, Maroc · Tél. 05 39 00 00 00 · contact@fournisseur.ma'));
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetTextColor(...$gris);
    $pdf->MultiCell(180, 4.5, $t("Paiement à effectuer à l'agence avant la date d'échéance. Une pénalité de "
        . $pct($penalitePct) . ' % (minimum ' . $pct($penaliteMin) . " DH) s'applique en cas de retard."));

    $pdf->Output('D', 'facture-' . $f['numero_facture'] . '.pdf');
    exit;
}