<?php
session_start();
define('ROOT', str_replace('index.php', '', $_SERVER['SCRIPT_FILENAME']));
define('BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/');

// Contrôleurs autorisés : chaque membre ajoutera le sien à la liste
$controleurs = ['Auth', 'Client', 'Consommation', 'Anomalie', 'Facture'];

$url        = explode('/', trim($_GET['url'] ?? '', '/'));
$controleur = $url[0] ?? '';
$action     = ($url[1] ?? '') !== '' ? $url[1] : 'index';
$param      = $url[2] ?? null;

// Page d'accueil : le formulaire de connexion client
if ($controleur === '') {
    header('Location: ' . BASE_URL . 'Auth/login_client');
    exit;
}

if (in_array($controleur, $controleurs) && file_exists(ROOT . "Traitement/$controleur.php")) {
    require_once ROOT . "Traitement/$controleur.php";
    $fonction = $controleur . '_' . $action;     // ex. Auth_connecter
    if (function_exists($fonction)) {
        $fonction($param);
        exit;
    }
}

http_response_code(404);
include ROOT . 'IHM/404.php';