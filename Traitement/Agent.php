<?php

require_once ROOT . 'BD/Agent.php';
require_once ROOT . 'BD/Quartier.php';
require_once ROOT . 'BD/Journal.php';
require_once ROOT . 'Traitement/Auth.php';

function Agent_index($param = null)
{
    exiger_role('FOURNISSEUR');
    $agents = bd_agent_lister();
    $quartiers = bd_quartier_lister();
    $message = $_SESSION['message_agent'] ?? null;
    unset($_SESSION['message_agent']);
    $erreur = $_SESSION['erreur_agent'] ?? null;
    unset($_SESSION['erreur_agent']);
    include ROOT . 'IHM/fournisseur/agents.php';
}

function Agent_enregistrer($param = null)
{
    exiger_role('FOURNISSEUR');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rediriger('Agent/index');
    }
    if (!csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        $_SESSION['erreur_agent'] = 'Le formulaire a expiré. Veuillez réessayer.';
        rediriger('Agent/index');
    }

    $champs = [];
    foreach (['matricule', 'nom', 'prenom', 'telephone', 'email', 'zone_affectation'] as $champ) {
        $valeur = $_POST[$champ] ?? '';
        $champs[$champ] = is_string($valeur) ? trim($valeur) : '';
    }
    $idQuartier = ($_POST['id_quartier'] ?? '') === ''
        ? null
        : filter_var($_POST['id_quartier'], FILTER_VALIDATE_INT);
    $idsQuartiers = array_map('intval', array_column(bd_quartier_lister(), 'id_quartier'));

    if ($champs['matricule'] === '' || mb_strlen($champs['matricule']) > 30
        || $champs['nom'] === '' || mb_strlen($champs['nom']) > 100
        || $champs['prenom'] === '' || mb_strlen($champs['prenom']) > 100
        || ($champs['email'] !== '' && !filter_var($champs['email'], FILTER_VALIDATE_EMAIL))
        || ($idQuartier !== null && (!$idQuartier || !in_array((int) $idQuartier, $idsQuartiers, true)))) {
        $_SESSION['erreur_agent'] = 'Veuillez vérifier les informations de l’agent et son quartier.';
        rediriger('Agent/index');
    }

    $link = Connexion();
    if (!mysqli_begin_transaction($link)) {
        throw new RuntimeException("Impossible de démarrer la création de l’agent : " . mysqli_error($link));
    }
    try {
        $idAgent = bd_agent_enregistrer(
            $champs['matricule'],
            $champs['nom'],
            $champs['prenom'],
            $champs['telephone'] !== '' ? $champs['telephone'] : null,
            $champs['email'] !== '' ? $champs['email'] : null,
            $champs['zone_affectation'] !== '' ? $champs['zone_affectation'] : null,
            $idQuartier ? (int) $idQuartier : null
        );
        bd_journal_enregistrer(
            (int) $_SESSION['id_utilisateur'],
            'CREATION_AGENT',
            'agents',
            $idAgent,
            'Création de l’agent ' . $champs['matricule']
        );
        if (!mysqli_commit($link)) {
            throw new RuntimeException("Impossible de valider la création de l’agent : " . mysqli_error($link));
        }
    } catch (RuntimeException $exception) {
        mysqli_rollback($link);
        $_SESSION['erreur_agent'] = 'Impossible d’enregistrer l’agent. Vérifiez le matricule puis réessayez.';
        rediriger('Agent/index');
    }

    $_SESSION['message_agent'] = 'L’agent a été enregistré.';
    rediriger('Agent/index');
}
