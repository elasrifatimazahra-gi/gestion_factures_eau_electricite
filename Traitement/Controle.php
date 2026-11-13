<?php

require_once ROOT . 'BD/Agent.php';
require_once ROOT . 'BD/Controle.php';
require_once ROOT . 'BD/Journal.php';
require_once ROOT . 'BD/Parametre.php';
require_once ROOT . 'BD/Facture.php';
require_once ROOT . 'BD/Tarif.php';
require_once ROOT . 'Traitement/Facturation.php';   // calculerMontantFluide
require_once ROOT . 'Traitement/Auth.php';

function controle_date_normaliser($valeur)
{
    if (!is_string($valeur)) {
        return null;
    }

    foreach (['!Y-m-d' => 'Y-m-d', '!d/m/Y' => 'd/m/Y'] as $format => $affichage) {
        $date = DateTimeImmutable::createFromFormat($format, trim($valeur));
        $erreurs = DateTimeImmutable::getLastErrors();
        if ($date && (!$erreurs || ($erreurs['warning_count'] === 0 && $erreurs['error_count'] === 0))
            && $date->format($affichage) === trim($valeur)) {
            return $date->format('Y-m-d');
        }
    }

    return null;
}

function controle_ecart_calculer($declare, $reel, $tolerancePct)
{
    $ecart = $reel - $declare;
    $ecartPct = $declare > 0
        ? round($ecart * 100 / $declare, 2)
        : ($reel > 0 ? 100.0 : 0.0);

    return [
        'ecart' => $ecart,
        'ecart_pct' => $ecartPct,
        'sous_declaration' => $reel > $declare * (1 + $tolerancePct / 100),
        'sur_declaration' => $declare > 0 && $reel < $declare * (1 - $tolerancePct / 100),
    ];
}

function controle_annuel_donnees_preparer($contrat, $idAgent, $annee, $dateReleve, $eauReelle, $elecReelle)
{
    $declarees = bd_controle_annuel_declarees((int) $contrat['id_contrat'], (int) $annee);
    $tolerance = (float) lireParametre('TOLERANCE_CONTROLE_ANNUEL_PCT', 5);
    $eau = controle_ecart_calculer($declarees['eau'], (int) $eauReelle, $tolerance);
    $elec = controle_ecart_calculer($declarees['elec'], (int) $elecReelle, $tolerance);

    if ($eau['sous_declaration'] || $elec['sous_declaration']) {
        $resultat = 'SOUS_DECLARATION';
    } elseif ($eau['sur_declaration'] || $elec['sur_declaration']) {
        $resultat = 'SUR_DECLARATION';
    } else {
        $resultat = 'CONFORME';
    }

    return [
        'annee' => (int) $annee,
        'id_client' => (int) $contrat['id_client'],
        'id_contrat' => (int) $contrat['id_contrat'],
        'id_agent' => (int) $idAgent,
        'date_releve_agent' => $dateReleve,
        'conso_eau_declaree' => $declarees['eau'],
        'conso_elec_declaree' => $declarees['elec'],
        'conso_eau_reelle_agent' => (int) $eauReelle,
        'conso_elec_reelle_agent' => (int) $elecReelle,
        'ecart_eau' => $eau['ecart'],
        'ecart_elec' => $elec['ecart'],
        'ecart_eau_pct' => $eau['ecart_pct'],
        'ecart_elec_pct' => $elec['ecart_pct'],
        'resultat' => $resultat,
    ];
}

function Controle_saisie_controle($param = null)
{
    exiger_role('AGENT');
    $idAgent = bd_utilisateur_id_agent((int) $_SESSION['id_utilisateur']);
    if ($idAgent === null) {
        http_response_code(403);
        include ROOT . 'IHM/auth/acces_refuse.php';
        return;
    }

    $contrats = bd_agent_tournee_lister($idAgent);
    $historique = bd_controle_annuel_historique_agent($idAgent);
    $erreur = $_SESSION['erreur_controle'] ?? '';
    $message = $_SESSION['message_controle'] ?? '';
    unset($_SESSION['erreur_controle'], $_SESSION['message_controle']);
    include ROOT . 'IHM/agent/saisie_controle.php';
}

function Controle_enregistrer($param = null)
{
    exiger_role('AGENT');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rediriger('Controle/saisie_controle');
    }
    if (!csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        $_SESSION['erreur_controle'] = 'Le formulaire a expiré. Veuillez réessayer.';
        rediriger('Controle/saisie_controle');
    }

    $idAgent = bd_utilisateur_id_agent((int) $_SESSION['id_utilisateur']);
    $idContrat = filter_var($_POST['id_contrat'] ?? null, FILTER_VALIDATE_INT);
    $annee = filter_var($_POST['annee'] ?? null, FILTER_VALIDATE_INT);
    $eauReelle = filter_var($_POST['conso_eau_reelle'] ?? null, FILTER_VALIDATE_INT);
    $elecReelle = filter_var($_POST['conso_elec_reelle'] ?? null, FILTER_VALIDATE_INT);
    $dateReleve = controle_date_normaliser($_POST['date_releve'] ?? null);

    if ($idAgent === null || !$idContrat || !$annee || $annee < 2000 || $annee > (int) date('Y')
        || $eauReelle === false || $eauReelle < 0
        || $elecReelle === false || $elecReelle < 0
        || $dateReleve === null || $dateReleve > date('Y-m-d')) {
        $_SESSION['erreur_controle'] = 'Vérifiez le contrat, l’année, la date et les consommations saisies.';
        rediriger('Controle/saisie_controle');
    }

    $contrats = bd_agent_tournee_lister($idAgent, (int) $idContrat);
    if (!$contrats) {
        $_SESSION['erreur_controle'] = 'Ce contrat ne fait pas partie de votre tournée.';
        rediriger('Controle/saisie_controle');
    }
    if (bd_controle_annuel_existe((int) $idContrat, (int) $annee)) {
        $_SESSION['erreur_controle'] = 'Un contrôle existe déjà pour ce contrat et cette année.';
        rediriger('Controle/saisie_controle');
    }

    $link = Connexion();
    if (!mysqli_begin_transaction($link)) {
        throw new RuntimeException("Impossible de démarrer l’enregistrement du contrôle annuel : " . mysqli_error($link));
    }
    try {
        $donnees = controle_annuel_donnees_preparer(
            $contrats[0],
            $idAgent,
            $annee,
            $dateReleve,
            $eauReelle,
            $elecReelle
        );
        $idControle = bd_controle_annuel_creer($donnees);
        bd_journal_enregistrer(
            (int) $_SESSION['id_utilisateur'],
            'CONTROLE_ANNUEL_AGENT',
            'controle_annuel',
            $idControle,
            'Contrôle annuel du contrat ' . $contrats[0]['numero_contrat'] . ' pour ' . $annee
        );
        if (!mysqli_commit($link)) {
            throw new RuntimeException("Impossible de valider le contrôle annuel : " . mysqli_error($link));
        }
    } catch (RuntimeException $exception) {
        mysqli_rollback($link);
        error_log($exception->getMessage());
        $_SESSION['erreur_controle'] = 'Le contrôle annuel n’a pas pu être enregistré.';
        rediriger('Controle/saisie_controle');
    }

    $_SESSION['message_controle'] = 'Contrôle enregistré et transmis au fournisseur pour validation.';
    rediriger('Controle/saisie_controle');
}

/* ------------------------------------------------------------------
 *  Côté FOURNISSEUR : validation des contrôles annuels (RG39 à RG44)
 * ------------------------------------------------------------------ */

function Controle_index($param = null)
{
    exiger_role('FOURNISSEUR');
    $statut = $_GET['statut'] ?? '';
    $controles = bd_controle_lister($statut);
    $erreur = $_SESSION['erreur_controle'] ?? '';
    $message = $_SESSION['message_controle'] ?? '';
    unset($_SESSION['erreur_controle'], $_SESSION['message_controle']);
    include ROOT . 'IHM/fournisseur/controle.php';
}

/** Montants HT/TVA/TTC d'un écart de consommation (eau + électricité) au tarif du contrat. */
function controle_montant_ecart($idContrat, $ecartEau, $ecartElec)
{
    $idType = $idContrat ? bd_tarif_type_du_contrat((int) $idContrat) : null;
    $htEau = calculerMontantFluide('EAU', abs((int) $ecartEau), $idType);
    $htElec = calculerMontantFluide('ELECTRICITE', abs((int) $ecartElec), $idType);
    $ht = round($htEau + $htElec, 2);
    $taux = (float) lireParametre('TVA_POURCENT', 18);
    $tva = round($ht * $taux / 100, 2);
    return ['ht_eau' => $htEau, 'ht_elec' => $htElec, 'ht' => $ht, 'taux' => $taux, 'tva' => $tva, 'ttc' => round($ht + $tva, 2)];
}

/** Sous-déclaration : facture de régularisation = énergie non facturée (ce que le client aurait dû payer). */
function controle_creer_facture_regularisation($c)
{
    $m = controle_montant_ecart($c['id_contrat'], max(0, (int) $c['ecart_eau']), max(0, (int) $c['ecart_elec']));
    $idFacture = bd_facture_creer([
        'numero_facture'        => bd_facture_numero($c['id_client'], (int) date('Y'), (int) date('n'), $c['id_contrat']),
        'id_client'             => $c['id_client'],
        'id_contrat'            => $c['id_contrat'],
        'id_conso'              => null,
        'periode_mois'          => (int) date('n'),
        'periode_annee'         => (int) $c['annee'],
        'date_emission'         => date('Y-m-d'),
        'date_echeance'         => date('Y-m-d', strtotime('+15 days')),
        'type_facture'          => 'REGULARISATION',
        'index_eau_debut'       => 0,
        'index_eau_fin'         => max(0, (int) $c['ecart_eau']),
        'conso_eau'             => max(0, (int) $c['ecart_eau']),
        'montant_ht_eau'        => $m['ht_eau'],
        'index_elec_debut'      => 0,
        'index_elec_fin'        => max(0, (int) $c['ecart_elec']),
        'conso_elec'            => max(0, (int) $c['ecart_elec']),
        'montant_ht_elec'       => $m['ht_elec'],
        'montant_ht'            => $m['ht'],
        'taux_tva'              => $m['taux'],
        'montant_tva'           => $m['tva'],
        'montant_ttc'           => $m['ttc'],
        'montant_total_a_payer' => $m['ttc'],
        'reste_a_payer'         => $m['ttc'],
        'statut'                => 'IMPAYEE',
    ]);
    if (!$idFacture) {
        throw new RuntimeException('Impossible de créer la facture de régularisation.');
    }
    return [$idFacture, $m['ttc']];
}

function Controle_valider($param = null)
{
    exiger_role('FOURNISSEUR');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        $_SESSION['erreur_controle'] = 'Le formulaire a expiré. Veuillez réessayer.';
        rediriger('Controle/index');
    }

    $c = bd_controle_trouver(filter_var($_POST['id_controle'] ?? 0, FILTER_VALIDATE_INT));
    if (!$c || $c['statut_validation'] !== 'EN_ATTENTE_VALIDATION') {
        $_SESSION['erreur_controle'] = 'Ce contrôle n’existe pas ou a déjà été traité.';
        rediriger('Controle/index');
    }

    $rejet = ($_POST['decision'] ?? '') === 'rejeter';
    $obs = trim((string) ($_POST['observations'] ?? '')) ?: null;
    $idUtilisateur = (int) $_SESSION['id_utilisateur'];

    $link = Connexion();
    mysqli_begin_transaction($link);
    try {
        $idFacture = null;
        $texte = 'Contrôle rejeté.';
        if (!$rejet) {
            if ($c['resultat'] === 'SOUS_DECLARATION') {
                [$idFacture, $ttc] = controle_creer_facture_regularisation($c);
                $texte = 'Fraude confirmée : facture de régularisation de ' . number_format($ttc, 2, ',', ' ') . ' DH créée.';
            } elseif ($c['resultat'] === 'SUR_DECLARATION') {
                $m = controle_montant_ecart($c['id_contrat'], $c['ecart_eau'], $c['ecart_elec']);
                bd_client_modifier_credit((int) $c['id_client'], $m['ttc']);
                $texte = 'Crédit de ' . number_format($m['ttc'], 2, ',', ' ') . ' DH ajouté au compte du client.';
            } else {
                $texte = 'Contrôle conforme : aucune action.';
            }
        }
        if (!bd_controle_marquer($c['id_controle'], $rejet ? 'REJETE' : 'VALIDE_PAR_FOURNISSEUR', $idUtilisateur, $idFacture, $obs)) {
            throw new RuntimeException('Le contrôle a déjà été traité.');
        }
        bd_journal_enregistrer($idUtilisateur, $rejet ? 'CONTROLE_REJETE' : 'CONTROLE_VALIDE', 'controle_annuel', (int) $c['id_controle'], $texte);
        mysqli_commit($link);
        $_SESSION['message_controle'] = $texte;
    } catch (Throwable $e) {
        mysqli_rollback($link);
        error_log($e->getMessage());
        $_SESSION['erreur_controle'] = 'La validation a échoué : ' . $e->getMessage();
    }
    rediriger('Controle/index');
}