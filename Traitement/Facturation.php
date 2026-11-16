<?php
// Calculs de facturation (RG24 à RG29, RG33)

require_once ROOT . 'BD/Facture.php';
require_once ROOT . 'BD/Tarif.php';
require_once ROOT . 'BD/Parametre.php';
require_once ROOT . 'BD/Controle.php';
require_once ROOT . 'BD/Consommation.php';   // enregistrer(), index précédent
require_once ROOT . 'BD/Journal.php';

/**
 * Montant HT d'un fluide. Le prix de la tranche s'applique à TOUTE la consommation
 * (pas de calcul cumulé). Vérifié sur le cas chiffré du TP :
 *   9 m3 d'eau   -> 9 x 7.39    = 66.51
 *   134 kWh elec -> 134 x 0.92  = 123.28
 */
function calculerMontantFluide($typeFluide, $conso, $idTypeActivite = null)
{
    $conso = (int) $conso;
    $tarif = bd_tarif_pour_conso($typeFluide, $conso, $idTypeActivite);
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

    $mois = (int) $c['mois'];
    $annee = (int) $c['annee'];

    // Le contrat de la consommation donne le quartier, donc le type d'activité, donc la grille
    $idContrat = !empty($c['id_contrat']) ? (int) $c['id_contrat'] : null;
    $idType = $idContrat ? bd_tarif_type_du_contrat($idContrat) : null;

    // Régularisation (RG19) : des mois estimés attendent ce vrai relevé
    $estimees = [];
    if ($idContrat && $c['type_saisie'] !== 'ESTIMEE') {
        $estimees = bd_facture_estimees_a_regulariser($idContrat, $annee, $mois);
    }

    if ($estimees) {
        // La consommation réelle couvre toute la période : on la répartit sur les mois
        // concernés et on applique le barème MENSUEL à chaque part.
        $nbMois = count($estimees) + 1;
        $htEau = 0.0;
        foreach (facturation_repartir($c['conso_eau'], $nbMois) as $part) {
            $htEau += calculerMontantFluide('EAU', $part, $idType);
        }
        $htElec = 0.0;
        foreach (facturation_repartir($c['conso_elec'], $nbMois) as $part) {
            $htElec += calculerMontantFluide('ELECTRICITE', $part, $idType);
        }
        $htEau = round($htEau, 2);
        $htElec = round($htElec, 2);
    } else {
        $htEau = calculerMontantFluide('EAU', $c['conso_eau'], $idType);
        $htElec = calculerMontantFluide('ELECTRICITE', $c['conso_elec'], $idType);
    }
    $ht = round($htEau + $htElec, 2);

    $tauxTva = (float) lireParametre('TVA_POURCENT', 18);
    $tva = round($ht * $tauxTva / 100, 2);
    $ttc = round($ht + $tva, 2);

    // Ce qui a déjà été facturé en estimation est déduit (montant négatif).
    // Si le client a trop payé, l'excédent devient un crédit sur son compte.
    $regularisation = $estimees ? -round(array_sum(array_column($estimees, 'ttc_estime')), 2) : 0.0;
    $base = round($ttc + $regularisation, 2);
    $creditRegul = 0.0;
    if ($base < 0) {
        $creditRegul = -$base;
        $base = 0.0;
    }

    // Crédit du client (sur-déclaration au contrôle annuel, trop-perçu) : déduit de cette facture
    $credit = min(bd_client_credit($c['id_client']), $base);
    $aPayer = round($base - $credit, 2);

    // RG29 : échéance le 15 du mois suivant la période facturée
    $jour = (int) lireParametre('JOUR_ECHEANCE_FACTURE', 15);
    $echeance = date('Y-m-', strtotime(sprintf('%04d-%02d-01 +1 month', $annee, $mois))) . sprintf('%02d', $jour);

    $donnees = [
        'numero_facture'        => bd_facture_numero($c['id_client'], $annee, $mois, $idContrat),
        'id_client'             => $c['id_client'],
        'id_contrat'            => $idContrat,
        'id_conso'              => $c['id_conso'],
        'periode_mois'          => $mois,
        'periode_annee'         => $annee,
        'date_emission'         => date('Y-m-d'),
        'date_echeance'         => $echeance,
        'type_facture'          => ($c['type_saisie'] === 'ESTIMEE') ? 'ESTIMEE' : 'MENSUELLE',
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
        'credit_deduit'         => $credit,
        'montant_total_a_payer' => $aPayer,
        'reste_a_payer'         => $aPayer,
        'statut'                => $aPayer > 0 ? 'IMPAYEE' : 'PAYEE',
        'photo_eau_facture'     => $c['photo_compteur_eau'],
        'photo_elec_facture'    => $c['photo_compteur_elec'],
    ];
    if ($regularisation != 0) {
        $donnees['montant_regularisation'] = $regularisation;   // colonne ajoutée par migration_regularisation.sql
    }
    $idFacture = bd_facture_creer($donnees);

    if ($idFacture) {
        if ($credit > 0) {
            bd_client_modifier_credit($c['id_client'], -$credit);
        }
        if ($creditRegul > 0) {
            bd_client_modifier_credit($c['id_client'], $creditRegul);
        }
        foreach ($estimees as $e) {
            mettreAJourStatut((int) $e['id_conso'], 'REGULARISEE');   // ces mois sont réglés
        }
        if ($estimees) {
            bd_journal_enregistrer(
                null, 'REGULARISATION_ESTIMATION', 'factures', (int) $idFacture,
                'Régularisation de ' . count($estimees) . ' mois estimés du contrat ' . $idContrat . ' : ' . number_format($regularisation, 2, '.', '') . ' DH déduits'
            );
        }
    }
    return $idFacture;
}

/**
 * RG33 : pénalité de retard = 5 % du TTC, minimum 15 DH, appliquée une seule fois.

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

/* ------------------------------------------------------------------
 *  Facture estimée (RG17, RG18)
 * ------------------------------------------------------------------ */

/**
 * Consommation estimée d'un contrat : moyenne de son historique réel (eau et électricité
 * séparément), ou valeur par défaut des paramètres s'il n'a aucun historique.
 * Retourne [conso_eau, conso_elec].
 */
function facturation_estimer_conso($idContrat, $annee, $mois)
{
    $moyenne = bd_facture_moyenne_contrat($idContrat, $annee, $mois);
    if ($moyenne) {
        return [$moyenne['eau'], $moyenne['elec']];
    }
    return [
        (int) lireParametre('ESTIMATION_DEFAUT_EAU_M3', 10),
        (int) lireParametre('ESTIMATION_DEFAUT_ELEC_KWH', 150),
    ];
}

/**
 * Crée la saisie estimée d'un contrat qui n'a pas déclaré, puis sa facture.
 * L'INDEX N'AVANCE PAS : seul un vrai relevé fait avancer le compteur. La prochaine saisie
 * réelle mesure donc toute la période depuis le dernier index réel (base de RG19).
 * Retourne l'id de la facture, ou false.
 */
function facturation_generer_estimation($idContrat, $idClient, $annee, $mois)
{
    $prec = bd_consommation_index_precedent_contrat($idContrat, $annee, $mois);
    [$eau, $elec] = facturation_estimer_conso($idContrat, $annee, $mois);

    $idConso = enregistrer(
        $idClient, $annee, $mois,
        $prec['eau'], $prec['eau'], $eau, null,
        $prec['elec'], $prec['elec'], $elec, null,
        'ESTIMEE', 'VALIDE', $idContrat
    );
    if (!$idConso) {
        return false;
    }
    $idFacture = genererFacture($idConso);
    bd_journal_enregistrer(
        null, 'GENERATION_ESTIMATION', 'consommations', (int) $idConso,
        "Estimation du contrat $idContrat pour $mois/$annee : $eau m3, $elec kWh"
    );
    return $idFacture;
}

/** Estime tous les contrats actifs qui n'ont pas saisi ce mois-là. Retourne le nombre de factures créées. */
function facturation_estimer_mois($annee, $mois)
{
    $nb = 0;
    foreach (bd_facture_contrats_a_estimer($annee, $mois) as $ct) {
        if (facturation_generer_estimation((int) $ct['id_contrat'], (int) $ct['id_client'], $annee, $mois)) {
            $nb++;
        }
    }
    return $nb;
}

/** Répartit un total entier en $n parts entières, la somme restant exacte (ex. 28 sur 3 : 10, 9, 9). */
function facturation_repartir($total, $n)
{
    $total = (int) $total;
    $n = max(1, (int) $n);
    $base = intdiv($total, $n);
    $reste = $total - $base * $n;
    $parts = [];
    for ($i = 0; $i < $n; $i++) {
        $parts[] = $base + ($i < $reste ? 1 : 0);
    }
    return $parts;
}
