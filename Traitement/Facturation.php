<?php
// Calculs de facturation (RG24 à RG29, RG33)

require_once ROOT . 'BD/Facture.php';
require_once ROOT . 'BD/Tarif.php';
require_once ROOT . 'BD/Parametre.php';

/**
 * Montant HT d'un fluide. Le prix de la tranche s'applique à TOUTE la consommation
 * (pas de calcul cumulé). Vérifié sur le cas chiffré du TP :
 *   9 m3 d'eau   -> 9 x 7.39    = 66.51
 *   134 kWh elec -> 134 x 0.92  = 123.28
 */
function calculerMontantFluide($typeFluide, $conso)
{
    $conso = (int) $conso;
    $tarif = bd_tarif_pour_conso($typeFluide, $conso);
    if (!$tarif) {
        return 0.0;
    }
    return round($conso * (float) $tarif['prix_unitaire_dh'], 2);
}

/**
 * Génère la facture mensuelle d'une consommation VALIDE.
 * Appelée par Traitement/Consommation.php. Retourne l'id de la facture, ou false.
 */
function genererFacture($idConso)
{
    if (bd_facture_par_conso($idConso)) {
        return false;                       // déjà facturée
    }
    $c = bd_facture_conso($idConso);
    if (!$c || $c['statut'] !== 'VALIDE') {
        return false;
    }

    $htEau = calculerMontantFluide('EAU', $c['conso_eau']);
    $htElec = calculerMontantFluide('ELECTRICITE', $c['conso_elec']);
    $ht = round($htEau + $htElec, 2);

    $tauxTva = (float) lireParametre('TVA_POURCENT', 18);
    $tva = round($ht * $tauxTva / 100, 2);
    $ttc = round($ht + $tva, 2);

    // RG29 : échéance le 15 du mois suivant la période facturée
    $jour = (int) lireParametre('JOUR_ECHEANCE_FACTURE', 15);
    $mois = (int) $c['mois'];
    $annee = (int) $c['annee'];
    $echeance = date('Y-m-', strtotime(sprintf('%04d-%02d-01 +1 month', $annee, $mois))) . sprintf('%02d', $jour);

    return bd_facture_creer([
        'numero_facture'        => bd_facture_numero($c['id_client'], $annee, $mois),
        'id_client'             => $c['id_client'],
        'id_conso'              => $c['id_conso'],
        'periode_mois'          => $mois,
        'periode_annee'         => $annee,
        'date_emission'         => date('Y-m-d'),
        'date_echeance'         => $echeance,
        'type_facture'          => 'MENSUELLE',
        'index_eau_debut'       => $c['index_eau_precedent'],
        'index_eau_fin'         => $c['index_eau_nouveau'],
        'conso_eau'             => $c['conso_eau'],
        'montant_ht_eau'        => $htEau,
        'index_elec_debut'      => $c['index_elec_precedent'],
        'index_elec_fin'        => $c['index_elec_nouveau'],
        'conso_elec'            => $c['conso_elec'],
        'montant_ht_elec'       => $htElec,
        'montant_ht'            => $ht,
        'taux_tva'              => $tauxTva,
        'montant_tva'           => $tva,
        'montant_ttc'           => $ttc,
        'montant_total_a_payer' => $ttc,
        'reste_a_payer'         => $ttc,
        'statut'                => 'IMPAYEE',
        'photo_eau_facture'     => $c['photo_compteur_eau'],
        'photo_elec_facture'    => $c['photo_compteur_elec'],
    ]);
}

/**
 * RG33 : pénalité de retard = 5 % du TTC, minimum 15 DH, appliquée une seule fois.
 * Retourne le nombre de factures pénalisées.
 */
function appliquerPenalites()
{
    $taux = (float) lireParametre('PENALITE_RETARD_POURCENT', 5);
    $minimum = (float) lireParametre('PENALITE_RETARD_MINIMUM_DH', 15);
    $nb = 0;
    foreach (bd_facture_a_penaliser() as $f) {
        $penalite = max(round((float) $f['montant_ttc'] * $taux / 100, 2), $minimum);
        bd_facture_appliquer_penalite($f['id_facture'], $penalite);
        $nb++;
    }
    return $nb;
}