<?php 

include_once(ROOT . 'BD/Utilisateur.php');

const NB_ECHECS         = 5;
const DUREE_BLOCAGE      = 15;
const MINUTES_INACTIVITE = 15;

function rediriger($chemin)
{
    header('Location: ' . BASE_URL . $chemin);
    exit;
}

function Auth_login_client($param = null)
{
    $erreur = $_SESSION['erreur_login'] ?? null;
    unset($_SESSION['erreur_login']);
    include ROOT . 'IHM/auth/login_client.php';
}

function Auth_login_fournisseur($param = null)
{
    $erreur = $_SESSION['erreur_login'] ?? null;
    unset($_SESSION['erreur_login']);
    include ROOT . 'IHM/auth/login_fournisseur.php';
}

function Auth_connecter($param = null)
{
    // 1. La demande doit venir du formulaire (POST)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rediriger('Auth/login_client');
    }

    // 2. Lire ce que l'utilisateur a envoyé
    $role  = (($_POST['role'] ?? '') === 'FOURNISSEUR') ? 'FOURNISSEUR' : 'CLIENT';
    $page  = ($role === 'CLIENT') ? 'Auth/login_client' : 'Auth/login_fournisseur';
    $login = trim($_POST['login'] ?? '');
    $mdp   = $_POST['mot_de_passe'] ?? '';

    // 3. Champs vides ?
    if ($login === '' || $mdp === '') {
        $_SESSION['erreur_login'] = "Veuillez remplir tous les champs.";
        rediriger($page);
    }

    // 4. L'utilisateur existe-t-il ?
    $user = bd_utilisateur_trouver($login, $role);
    if ($user === null) {
        $_SESSION['erreur_login'] = "Identifiants incorrects.";
        rediriger($page);
    }

    // 5. Compte bloqué temporairement ? (avant de tester le mot de passe)
    if ($user['est_bloque']) {
        $_SESSION['erreur_login'] = "Compte bloqué temporairement. Réessayez plus tard.";
        rediriger($page);
    }

    // 6. Mot de passe correct ?
    if (!password_verify($mdp, $user['mot_de_passe'])) {
        bd_utilisateur_echec($user['id_utilisateur']);

        // C'était le dernier essai autorisé : on bloque
        if ($user['tentatives_echouees'] + 1 >= NB_ECHECS) {
            bd_utilisateur_bloquer($user['id_utilisateur'], DUREE_BLOCAGE);
            $_SESSION['erreur_login'] = "Trop d'échecs : compte bloqué pendant " . DUREE_BLOCAGE . " minutes.";
        } else {
            $_SESSION['erreur_login'] = "Identifiants incorrects.";
        }
        rediriger($page);
    }

    // 7. Compte désactivé ? (après le mot de passe)
    if ($user['statut_compte'] !== 'ACTIF') {
        $_SESSION['erreur_login'] = "Votre compte est bloqué. Rendez-vous à l'agence.";
        rediriger($page);
    }

    // 8. Tout est bon : on connecte l'utilisateur
    session_regenerate_id(true);
    bd_utilisateur_reset($user['id_utilisateur']);
    $_SESSION['id_utilisateur']    = $user['id_utilisateur'];
    $_SESSION['role']              = $user['role'];
    $_SESSION['derniere_activite'] = time();

    if ($user['role'] === 'CLIENT') {
        $_SESSION['id_client'] = bd_utilisateur_id_client($user['id_utilisateur']);
    }
    rediriger('Auth/accueil');
}

function Auth_accueil($param = null)
{
    echo "Connecté : utilisateur n°" . ($_SESSION['id_utilisateur'] ?? '?')
       . ", rôle " . ($_SESSION['role'] ?? '?')
       . ' - <a href="' . BASE_URL . 'Auth/logout">Se déconnecter</a>';
}

function Auth_logout($param = null)
{
    $role = $_SESSION['role'] ?? 'CLIENT';
    $_SESSION = [];
    session_destroy();
    rediriger($role === 'FOURNISSEUR' ? 'Auth/login_fournisseur' : 'Auth/login_client');
}

function exiger_role($role)
{
    $page = ($role === 'FOURNISSEUR') ? 'Auth/login_fournisseur' : 'Auth/login_client';

    // 1. Connecté ?
    if (!isset($_SESSION['id_utilisateur'])) {
        rediriger($page);
    }

    // 2. Session expirée ?
    if (time() - ($_SESSION['derniere_activite'] ?? 0) > MINUTES_INACTIVITE * 60) {
        unset($_SESSION['id_utilisateur'], $_SESSION['role'], $_SESSION['derniere_activite'], $_SESSION['id_client']);
        $_SESSION['erreur_login'] = "Session expirée, veuillez vous reconnecter.";
        rediriger($page);
    }
    $_SESSION['derniere_activite'] = time();

    // 3. Bon rôle ?
    if ($_SESSION['role'] !== $role) {
        http_response_code(403);
        include ROOT . 'IHM/auth/acces_refuse.php';
        exit;
    }
}

