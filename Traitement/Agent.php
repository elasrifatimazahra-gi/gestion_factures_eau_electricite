<?php

require_once ROOT . 'BD/Agent.php';
require_once ROOT . 'BD/Quartier.php';
require_once ROOT . 'BD/Journal.php';
require_once ROOT . 'BD/Consommation.php';
require_once ROOT . 'BD/Controle.php';
require_once ROOT . 'BD/Utilisateur.php';
require_once ROOT . 'Traitement/Auth.php';

function agent_id_connecte()
{
    exiger_role('AGENT');
    $idAgent = bd_utilisateur_id_agent((int) $_SESSION['id_utilisateur']);
    if ($idAgent === null) {
        http_response_code(403);
        include ROOT . 'IHM/auth/acces_refuse.php';
        exit;
    }
    $_SESSION['id_agent'] = $idAgent;
    return $idAgent;
}

function Agent_accueil($param = null)
{
    $idAgent = agent_id_connecte();
    $tournees = bd_agent_tournee_lister($idAgent);
    $message = $_SESSION['message_agent_espace'] ?? '';
    $erreur = $_SESSION['erreur_agent_espace'] ?? '';
    unset($_SESSION['message_agent_espace'], $_SESSION['erreur_agent_espace']);
    include ROOT . 'IHM/agent/accueil.php';
}

function Agent_releve($param = null)
{
    agent_id_connecte();
    $suffixe = ctype_digit((string) $param) ? '/' . (int) $param : '';
    rediriger('Consommation/saisie_mensuelle' . $suffixe);
}

function Agent_historique($param = null)
{
    $idAgent = agent_id_connecte();
    $historique = bd_consommation_historique_agent($idAgent);
    $historiqueControles = bd_controle_annuel_historique_agent($idAgent);
    include ROOT . 'IHM/agent/historique.php';
}

function Agent_depotFichier($param = null)
{
    agent_id_connecte();
    $erreur = $_SESSION['erreur_agent_espace'] ?? '';
    $message = $_SESSION['message_agent_espace'] ?? '';
    unset($_SESSION['erreur_agent_espace'], $_SESSION['message_agent_espace']);
    include ROOT . 'IHM/agent/depot_fichier.php';
}

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
    $motDePasse = $_POST['mot_de_passe'] ?? '';
    $idQuartier = ($_POST['id_quartier'] ?? '') === ''
        ? null
        : filter_var($_POST['id_quartier'], FILTER_VALIDATE_INT);
    $idsQuartiers = array_map('intval', array_column(bd_quartier_lister(), 'id_quartier'));

    if ($champs['matricule'] === '' || mb_strlen($champs['matricule']) > 30
        || $champs['nom'] === '' || mb_strlen($champs['nom']) > 100
        || $champs['prenom'] === '' || mb_strlen($champs['prenom']) > 100
        || ($champs['email'] !== '' && !filter_var($champs['email'], FILTER_VALIDATE_EMAIL))
        || !is_string($motDePasse) || strlen($motDePasse) < 12
        || !$idQuartier || !in_array((int) $idQuartier, $idsQuartiers, true)) {
        $_SESSION['erreur_agent'] = 'Vérifiez les informations, le quartier et un mot de passe de 12 caractères minimum.';
        rediriger('Agent/index');
    }

    $link = Connexion();
    if (!mysqli_begin_transaction($link)) {
        throw new RuntimeException("Impossible de démarrer la création de l’agent : " . mysqli_error($link));
    }
    try {
        $idUtilisateur = bd_utilisateur_creer($champs['matricule'], $motDePasse, 'AGENT');
        if (!$idUtilisateur) {
            throw new RuntimeException("Impossible de créer le compte de l’agent.");
        }
        $idAgent = bd_agent_enregistrer(
            $champs['matricule'],
            $champs['nom'],
            $champs['prenom'],
            $champs['telephone'] !== '' ? $champs['telephone'] : null,
            $champs['email'] !== '' ? $champs['email'] : null,
            $champs['zone_affectation'] !== '' ? $champs['zone_affectation'] : null,
            $idQuartier ? (int) $idQuartier : null,
            (int) $idUtilisateur
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
        $_SESSION['erreur_agent'] = 'Impossible de créer l’agent ou son compte. Vérifiez que le matricule est unique.';
        rediriger('Agent/index');
    }

    $_SESSION['message_agent'] = 'L’agent a été enregistré.';
    rediriger('Agent/index');
}
