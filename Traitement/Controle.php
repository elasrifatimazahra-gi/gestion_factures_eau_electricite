<?php

require_once ROOT . 'BD/Agent.php';
require_once ROOT . 'BD/Controle.php';
require_once ROOT . 'BD/Journal.php';
require_once ROOT . 'BD/Parametre.php';
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
