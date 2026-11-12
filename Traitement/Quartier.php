<?php

require_once ROOT . 'BD/Quartier.php';
require_once ROOT . 'BD/Journal.php';
require_once ROOT . 'Traitement/Auth.php';

function Quartier_index($param = null)
{
    exiger_role('FOURNISSEUR');
    $quartiers = bd_quartier_lister();
    $typesActivite = bd_quartier_types_activite();
    $message = $_SESSION['message_quartier'] ?? null;
    unset($_SESSION['message_quartier']);
    $erreur = $_SESSION['erreur_quartier'] ?? null;
    unset($_SESSION['erreur_quartier']);
    include ROOT . 'IHM/fournisseur/quartiers.php';
}

function Quartier_enregistrer($param = null)
{
    exiger_role('FOURNISSEUR');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rediriger('Quartier/index');
    }
    if (!csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        $_SESSION['erreur_quartier'] = 'Le formulaire a expiré. Veuillez réessayer.';
        rediriger('Quartier/index');
    }

    $nomBrut = $_POST['nom'] ?? null;
    $adresseBrute = $_POST['adresse'] ?? '';
    $idType = filter_var($_POST['id_type_activite'] ?? null, FILTER_VALIDATE_INT);
    $idQuartier = filter_var($_POST['id_quartier'] ?? null, FILTER_VALIDATE_INT);
    $nom = is_string($nomBrut) ? trim($nomBrut) : '';
    $adresse = is_string($adresseBrute) ? trim($adresseBrute) : '';
    $types = array_map('intval', array_column(bd_quartier_types_activite(), 'id_type_activite'));

    if ($nom === '' || mb_strlen($nom) > 120 || !$idType || !in_array($idType, $types, true)
        || ($idQuartier !== false && $idQuartier !== null && $idQuartier < 1)) {
        $_SESSION['erreur_quartier'] = 'Veuillez fournir un nom et un type d’activité valides.';
        rediriger('Quartier/index');
    }

    $idQuartier = $idQuartier ? (int) $idQuartier : null;
    $link = Connexion();
    if (!mysqli_begin_transaction($link)) {
        throw new RuntimeException("Impossible de démarrer l’enregistrement du quartier : " . mysqli_error($link));
    }
    try {
        $idEnregistre = bd_quartier_enregistrer($idQuartier, $nom, $adresse, (int) $idType);
        bd_journal_enregistrer(
            (int) $_SESSION['id_utilisateur'],
            $idQuartier ? 'MODIFICATION_QUARTIER' : 'CREATION_QUARTIER',
            'quartiers',
            $idEnregistre,
            ($idQuartier ? 'Modification' : 'Création') . ' du quartier ' . $nom
        );
        if (!mysqli_commit($link)) {
            throw new RuntimeException("Impossible de valider l’enregistrement du quartier : " . mysqli_error($link));
        }
    } catch (RuntimeException $exception) {
        mysqli_rollback($link);
        $_SESSION['erreur_quartier'] = 'Impossible d’enregistrer le quartier. Vérifiez les données puis réessayez.';
        rediriger('Quartier/index');
    }

    $_SESSION['message_quartier'] = 'Le quartier a été enregistré.';
    rediriger('Quartier/index');
}
