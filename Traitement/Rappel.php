<?php
// Tâches planifiées du fournisseur (RG20, RG33, RG34) - Membre 4
// URL : Rappel/executer (POST, bouton « Exécuter les tâches planifiées » de la page Coupures)
//
// Ordre d'exécution :
//   1. estimation du mois écoulé pour les contrats qui n'ont rien déclaré
//   2. pénalités de retard
//   3. alerte + coupure programmée pour les contrats à 3 mois impayés consécutifs
//   4. alerte « mois non déclarés » (client notifié une fois par mois ; trace dans le journal)

require_once ROOT . 'BD/Rappel.php';
require_once ROOT . 'Traitement/Facturation.php';
require_once ROOT . 'Traitement/Coupure.php';

function rappel_seuil_non_declares()
{
    return max(1, (int) lireParametre('NB_MOIS_NON_DECLARES_ALERTE', 3));
}

/** Nombre de mois consécutifs (jusqu'au dernier mois saisi) où la consommation est seulement estimée. */
function rappel_mois_non_declares_consecutifs($idContrat)
{
    $nombre = 0;
    $attendu = null;
    foreach (bd_rappel_saisies_recentes($idContrat) as $l) {
        if ($l['type_saisie'] !== 'ESTIMEE') {
            break;
        }
        $rang = (int) $l['annee'] * 12 + ((int) $l['mois'] - 1);
        if ($attendu !== null && $rang !== $attendu) {
            break;
        }
        $nombre++;
        $attendu = $rang - 1;
    }
    return $nombre;
}

/**
 * Exécute toutes les tâches et retourne un rapport :
 *   date, mois_estime, estimations (int), penalites (liste), coupures (liste), non_declares (liste), erreurs (liste)
 * Chaque étape est isolée : si l'une échoue, les autres s'exécutent quand même.
 */
function rappel_executer_taches($idUtilisateur = null)
{
    $rapport = [
        'date'         => date('d/m/Y H:i'),
        'mois_estime'  => '',
        'estimations'  => 0,
        'penalites'    => [],
        'coupures'     => [],
        'non_declares' => [],
        'erreurs'      => [],
    ];

    // 1. Estimation du mois écoulé (jamais le mois en cours : la saisie est ouverte jusqu'à sa fin)
    try {
        $moisPrecedent = new DateTime('first day of last month');
        $rapport['mois_estime'] = $moisPrecedent->format('m/Y');
        $rapport['estimations'] = (int) facturation_estimer_mois((int) $moisPrecedent->format('Y'), (int) $moisPrecedent->format('n'));
    } catch (Throwable $e) {
        $rapport['erreurs'][] = 'Estimation du mois écoulé impossible.';
    }

    // 2. Pénalités de retard (journal + notification faits par appliquerPenalitesDetail)
    try {
        foreach (appliquerPenalitesDetail() as $p) {
            $rapport['penalites'][] = $p['numero_facture'] . ' : ' . number_format($p['penalite'], 2, ',', ' ') . ' DH';
        }
    } catch (Throwable $e) {
        $rapport['erreurs'][] = 'Application des pénalités impossible.';
    }

    // 3 et 4. Contrôle de chaque contrat actif
    try {
        $seuilImpayes = coupure_seuil_impayes();
        $seuilNonDeclares = rappel_seuil_non_declares();
        $debutMois = date('Y-m-01');

        foreach (bd_rappel_contrats_actifs() as $ct) {
            $idContrat = (int) $ct['id_contrat'];
            $client = trim($ct['nom'] . ' ' . $ct['prenom']);

            // 3. Trois mois impayés consécutifs -> alerte + coupure programmée
            $nbImpayes = bd_coupure_mois_impayes_consecutifs($idContrat);
            if ($nbImpayes >= $seuilImpayes && !bd_coupure_ouverte_pour_contrat($idContrat)) {
                try {
                    coupure_programmer_contrat(
                        $idContrat, 'TROIS_MOIS_IMPAYES', date('Y-m-d'), $idUtilisateur,
                        'Programmée automatiquement : ' . $nbImpayes . ' mois impayés consécutifs'
                    );
                    $rapport['coupures'][] = $ct['numero_contrat'] . ' (' . $client . ') : ' . $nbImpayes . ' mois impayés';
                } catch (DomainException $e) {
                    $rapport['erreurs'][] = 'Contrat ' . $ct['numero_contrat'] . ' : ' . $e->getMessage();
                }
            }

            // 4. Mois non déclarés consécutifs
            $nbNonDeclares = rappel_mois_non_declares_consecutifs($idContrat);
            if ($nbNonDeclares >= $seuilNonDeclares) {
                $rapport['non_declares'][] = $ct['numero_contrat'] . ' (' . $client . ') : ' . $nbNonDeclares . ' mois sans déclaration';
                $titre = 'Consommation non déclarée - contrat ' . $ct['numero_contrat'];
                if (!empty($ct['id_utilisateur_client']) && !bd_rappel_notification_deja_envoyee($ct['id_utilisateur_client'], $titre, $debutMois)) {
                    try {
                        bd_notification_creer(
                            (int) $ct['id_utilisateur_client'],
                            $titre,
                            'Vous n\'avez pas déclaré la consommation du contrat ' . $ct['numero_contrat'] . ' depuis ' . $nbNonDeclares
                                . ' mois consécutifs : vos factures sont estimées. Saisissez votre consommation entre le 18 et la fin du mois.',
                            'RAPPEL_SAISIE'
                        );
                        bd_journal_enregistrer(
                            $idUtilisateur, 'ALERTE_NON_DECLARATION', 'contrats', $idContrat,
                            'Contrat ' . $ct['numero_contrat'] . ' : ' . $nbNonDeclares . ' mois consécutifs sans déclaration'
                        );
                    } catch (Throwable $e) {
                    }
                }
            }
        }
    } catch (Throwable $e) {
        $rapport['erreurs'][] = 'Contrôle des contrats interrompu.';
    }

    try {
        bd_journal_enregistrer(
            $idUtilisateur, 'EXECUTION_TACHES_PLANIFIEES', null, null,
            'Tâches planifiées : ' . $rapport['estimations'] . ' estimation(s), ' . count($rapport['penalites'])
                . ' pénalité(s), ' . count($rapport['coupures']) . ' coupure(s) programmée(s), '
                . count($rapport['non_declares']) . ' contrat(s) sans déclaration'
        );
    } catch (Throwable $e) {
    }
    return $rapport;
}

function Rappel_executer($param = null)
{
    exiger_role('FOURNISSEUR');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rediriger('Coupure/index');
    }
    if (!csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        $_SESSION['erreur_coupure'] = 'Le formulaire a expiré. Veuillez réessayer.';
        rediriger('Coupure/index');
    }
    $_SESSION['rapport_rappel'] = rappel_executer_taches(coupure_utilisateur_session());
    rediriger('Coupure/index?statut=PROGRAMMEE');
}