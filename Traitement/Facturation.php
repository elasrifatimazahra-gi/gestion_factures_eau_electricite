<?php
// Calculs de facturation (RG24 à RG29, RG33)

require_once ROOT . 'BD/Facture.php';
require_once ROOT . 'BD/Tarif.php';
require_once ROOT . 'BD/Parametre.php';
require_once ROOT . 'BD/Controle.php';
require_once ROOT . 'BD/Consommation.php';   // enregistrer(), index précédent
require_once ROOT . 'BD/Journal.php';
require_once ROOT . 'BD/Notification.php';

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

        // Le contrat de la consommation donne le quartier, donc le type d'activité, donc la grille
    $idContrat = !empty($c['id_contrat']) ? (int) $c['id_contrat'] : null;
    $idType = $idContrat ? bd_tarif_type_du_contrat($idContrat) : null;

    $htEau = calculerMontantFluide('EAU', $c['conso_eau'], $idType);
    $htElec = calculerMontantFluide('ELECTRICITE', $c['conso_elec'], $idType);
    $ht = round($htEau + $htElec, 2);

    $tauxTva = (float) lireParametre('TVA_POURCENT', 18);
    $tva = round($ht * $tauxTva / 100, 2);
    $ttc = round($ht + $tva, 2);
        // Crédit du client (sur-déclaration au contrôle annuel) : déduit de cette facture
    $credit = min(bd_client_credit($c['id_client']), $ttc);
    $aPayer = round($ttc - $credit, 2);

    // RG29 : échéance le 15 du mois suivant la période facturée
    $jour = (int) lireParametre('JOUR_ECHEANCE_FACTURE', 15);
    $mois = (int) $c['mois'];
    $annee = (int) $c['annee'];
    $echeance = date('Y-m-', strtotime(sprintf('%04d-%02d-01 +1 month', $annee, $mois))) . sprintf('%02d', $jour);

    $idFacture = bd_facture_creer([
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
    ]);

    if ($idFacture && $credit > 0) {
        bd_client_modifier_credit($c['id_client'], -$credit);
    }
    return $idFacture;
}

/**
 * RG33 : pénalité de retard = 5 % du TTC, minimum 15 DH, appliquée une seule fois.

/**
 * RG33 : pénalité de retard = 5 % du TTC, minimum 15 DH, appliquée une seule fois.
 * Retourne le nombre de factures pénalisées.
 */
// ===== ÉTAPE 2 - Traitement/Facturation.php =====
// (a) En haut du fichier, avec les autres require_once, ajouter cette ligne :
//        require_once ROOT . 'BD/Notification.php';
//
// (b) REMPLACER toute l'ancienne fonction appliquerPenalites() par les deux fonctions ci-dessous.
//     Les appels existants dans Traitement/Facture.php (appliquerPenalites();) continuent de marcher.

/**
 * Applique les pénalités de retard (RG33) et retourne le NOMBRE de factures pénalisées.
 * (Même retour qu'avant : Facture_liste et Facture_detail n'ont rien à changer.)
 */
function appliquerPenalites()
{
    return count(appliquerPenalitesDetail());
}

/**
 * Applique les pénalités de retard et retourne la LISTE des factures pénalisées par cet appel.
 * Chaque élément : id_facture, numero_facture, id_client, id_contrat, numero_contrat, montant_ttc,
 * date_echeance, id_utilisateur_client + penalite (montant appliqué).
 * Pour chaque facture : pénalité appliquée une seule fois, ligne dans le journal (utilisateur NULL =
 * action du système), notification RETARD_PAIEMENT au client.
 * Traitement/Rappel.php (étape 4) réutilise cette liste.
 */
function appliquerPenalitesDetail()
{
    $taux = (float) lireParametre('PENALITE_RETARD_POURCENT', 5);
    $minimum = (float) lireParametre('PENALITE_RETARD_MINIMUM_DH', 15);
    $penalisees = [];

    foreach (bd_facture_a_penaliser_detail() as $f) {
        $penalite = max(round((float) $f['montant_ttc'] * $taux / 100, 2), $minimum);
        if (!bd_facture_appliquer_penalite_unique($f['id_facture'], $penalite)) {
            continue;   // déjà pénalisée par un autre appel
        }
        $f['penalite'] = $penalite;
        $penalisees[] = $f;

        // Journal et notification : un échec ne doit jamais bloquer l'affichage de la page client
        try {
            bd_journal_enregistrer(
                null,
                'PENALITE_RETARD',
                'factures',
                (int) $f['id_facture'],
                'Pénalité de retard de ' . number_format($penalite, 2, '.', '') . ' DH appliquée à la facture '
                    . $f['numero_facture'] . ' (échéance ' . $f['date_echeance'] . ')'
            );
            if (!empty($f['id_utilisateur_client'])) {
                bd_notification_creer(
                    (int) $f['id_utilisateur_client'],
                    'Pénalité de retard',
                    'La facture ' . $f['numero_facture']
                        . (!empty($f['numero_contrat']) ? ' (contrat ' . $f['numero_contrat'] . ')' : '')
                        . ' n\'a pas été réglée avant le ' . date('d/m/Y', strtotime($f['date_echeance']))
                        . '. Une pénalité de retard de ' . number_format($penalite, 2, ',', ' ')
                        . ' DH a été ajoutée au montant à payer.',
                    'RETARD_PAIEMENT'
                );
            }
        } catch (Throwable $e) {
            // on garde la pénalité appliquée ; seule la trace (journal/notification) est perdue
        }
    }
    return $penalisees;
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

