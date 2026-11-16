<?php

require_once ROOT . 'BD/Notification.php';
require_once ROOT . 'BD/Journal.php';
require_once ROOT . 'BD/Utilisateur.php';
require_once ROOT . 'BD/Facture.php';
require_once ROOT . 'Traitement/Auth.php';

function Notification_liste($param = null)
{
    exiger_role('CLIENT');
    auth_exiger_contrat((int) ($_SESSION['id_client'] ?? 0));
    $idUtilisateur = (int) $_SESSION['id_utilisateur'];
    $notifications = bd_notification_lister($idUtilisateur);
    $client = bd_facture_infos_client((int) ($_SESSION['id_client'] ?? 0));
    $notificationsNonLues = bd_notification_compter_non_lues($idUtilisateur);
    $message = $_SESSION['message_notification'] ?? null;
    unset($_SESSION['message_notification']);
    $erreur = $_SESSION['erreur_notification'] ?? null;
    unset($_SESSION['erreur_notification']);
    include ROOT . 'IHM/client/notifications.php';
}

function Notification_lire($param = null)
{
    exiger_role('CLIENT');
    auth_exiger_contrat((int) ($_SESSION['id_client'] ?? 0));
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rediriger('Notification/liste');
    }
    if (!csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        $_SESSION['erreur_notification'] = 'Le formulaire a expiré. Veuillez réessayer.';
        rediriger('Notification/liste');
    }

    $idNotification = filter_var($_POST['id_notification'] ?? null, FILTER_VALIDATE_INT);
    if (!$idNotification) {
        $_SESSION['erreur_notification'] = 'Cette notification est introuvable.';
        rediriger('Notification/liste');
    }

    $link = Connexion();
    if (!mysqli_begin_transaction($link)) {
        throw new RuntimeException("Impossible de démarrer la mise à jour de la notification : " . mysqli_error($link));
    }
    try {
        if (!bd_notification_marquer_lue($idNotification, (int) $_SESSION['id_utilisateur'])) {
            mysqli_rollback($link);
            $_SESSION['erreur_notification'] = 'Cette notification est introuvable.';
            rediriger('Notification/liste');
        }
        bd_journal_enregistrer(
            (int) $_SESSION['id_utilisateur'],
            'LECTURE_NOTIFICATION',
            'notifications',
            $idNotification,
            'Lecture d’une notification'
        );
        if (!mysqli_commit($link)) {
            throw new RuntimeException("Impossible de valider la lecture de la notification : " . mysqli_error($link));
        }
    } catch (RuntimeException $exception) {
        mysqli_rollback($link);
        $_SESSION['erreur_notification'] = 'Impossible de mettre à jour la notification. Veuillez réessayer.';
        rediriger('Notification/liste');
    }
    $_SESSION['message_notification'] = 'La notification a été marquée comme lue.';
    rediriger('Notification/liste');
}

function Notification_creer($param = null)
{
    exiger_role('FOURNISSEUR');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rediriger('Journal/index');
    }
    if (!csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Le formulaire a expiré. Veuillez réessayer.');
    }

    $idUtilisateur = filter_var($_POST['id_utilisateur'] ?? null, FILTER_VALIDATE_INT);
    $titreBrut = $_POST['titre'] ?? null;
    $messageBrut = $_POST['message'] ?? null;
    $titre = is_string($titreBrut) ? trim($titreBrut) : '';
    $message = is_string($messageBrut) ? trim($messageBrut) : '';
    $type = is_string($_POST['type'] ?? null) ? $_POST['type'] : 'INFO';
    $typesAutorises = [
        'RAPPEL_SAISIE', 'FACTURE_DISPONIBLE', 'RETARD_PAIEMENT', 'ANOMALIE_DETECTEE',
        'ALERTE_COUPURE', 'RECLAMATION_REPONSE', 'SANCTION', 'INFO',
    ];
    if (!$idUtilisateur || $titre === '' || mb_strlen($titre) > 150
        || $message === '' || !in_array($type, $typesAutorises, true)) {
        http_response_code(400);
        exit('Les informations de notification sont invalides.');
    }

    $link = Connexion();
    $stmt = mysqli_prepare($link, "SELECT id_utilisateur FROM utilisateurs WHERE id_utilisateur = ? AND role = 'CLIENT'");
    if (!$stmt) {
        throw new RuntimeException("Impossible de vérifier le destinataire : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, 'i', $idUtilisateur);
    if (!mysqli_stmt_execute($stmt)) {
        $erreurSql = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de vérifier le destinataire : " . $erreurSql);
    }
    $resultat = mysqli_stmt_get_result($stmt);
    $destinataireExiste = mysqli_fetch_assoc($resultat);
    mysqli_stmt_close($stmt);

    if (!$destinataireExiste) {
        http_response_code(400);
        exit('Le destinataire client est introuvable.');
    }

    if (!mysqli_begin_transaction($link)) {
        throw new RuntimeException("Impossible de démarrer la création de la notification : " . mysqli_error($link));
    }
    try {
        $idNotification = bd_notification_creer($idUtilisateur, $titre, $message, $type);
        bd_journal_enregistrer(
            (int) $_SESSION['id_utilisateur'],
            'CREATION_NOTIFICATION',
            'notifications',
            $idNotification,
            'Création d’une notification destinée à l’utilisateur ' . $idUtilisateur,
            null,
            $titre
        );
        if (!mysqli_commit($link)) {
            throw new RuntimeException("Impossible de valider la création de la notification : " . mysqli_error($link));
        }
    } catch (RuntimeException $exception) {
        mysqli_rollback($link);
        throw $exception;
    }
    rediriger('Journal/index');
}
