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
    if (!isset($_SESSION['id_utilisateur'])) {
        rediriger('Auth/login_client');
    }
    exiger_role($_SESSION['role']);   // vérifie la connexion et l'expiration de session
 
    // ----- Fournisseur : page simple -----
    if ($_SESSION['role'] === 'FOURNISSEUR') {
        include ROOT . 'IHM/fournisseur/accueil.php';
        return;
    }
 
    // ----- Client : on prépare les données du tableau de bord -----
    require_once ROOT . 'BD/Facture.php';
    require_once ROOT . 'BD/Consommation.php';
 
    $idClient = (int) ($_SESSION['id_client'] ?? 0);
    $client   = bd_facture_infos_client($idClient) ?: ['nom' => '', 'prenom' => '', 'numero_client' => ''];
 
    // Solde à payer = somme des factures impayées ou en retard ; échéance = la plus proche
    $solde     = 0.0;
    $echeance  = null;
    $nbImpayees = 0;
    foreach (bd_facture_du_client($idClient) as $f) {
        if (in_array($f['statut'], ['IMPAYEE', 'EN_RETARD'], true)) {
            $solde += (float) $f['reste_a_payer'];
            $nbImpayees++;
            if ($echeance === null || $f['date_echeance'] < $echeance) {
                $echeance = $f['date_echeance'];
            }
        }
    }
 
    // État de la saisie du mois (règles RG11 et RG12 : du 18 au dernier jour, une seule fois)
    $jour  = (int) date('j');
    $mois  = (int) date('n');
    $annee = (int) date('Y');
    $dernierJour = (int) date('t');
 
    if (saisieExiste($idClient, $mois, $annee)) {
        $etatSaisie = 'DEJA_SAISI';
    } elseif ($jour < 18) {
        $etatSaisie = 'HORS_PERIODE';
    } else {
        $etatSaisie = 'OUVERTE';
    }
 
    include ROOT . 'IHM/client/accueil.php';
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

