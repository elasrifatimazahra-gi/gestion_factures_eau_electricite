<?php

require_once __DIR__ . '/Connexion.php';

function bd_notification_creer($idUtilisateur, $titre, $message, $type = 'INFO')
{
    $typesAutorises = [
        'RAPPEL_SAISIE', 'FACTURE_DISPONIBLE', 'RETARD_PAIEMENT', 'ANOMALIE_DETECTEE',
        'ALERTE_COUPURE', 'RECLAMATION_REPONSE', 'SANCTION', 'INFO',
    ];
    if (!in_array($type, $typesAutorises, true)) {
        throw new InvalidArgumentException('Type de notification invalide.');
    }

    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "INSERT INTO notifications (id_utilisateur, titre, message, type) VALUES (?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la création de la notification : " . mysqli_error($link));
    }
    $idUtilisateur = (int) $idUtilisateur;
    mysqli_stmt_bind_param($stmt, 'isss', $idUtilisateur, $titre, $message, $type);
    $execute = mysqli_stmt_execute($stmt);
    $erreur = mysqli_stmt_error($stmt);
    $idNotification = mysqli_insert_id($link);
    mysqli_stmt_close($stmt);
    if (!$execute) {
        throw new RuntimeException("Impossible de créer la notification : " . $erreur);
    }
    return $idNotification;
}

function bd_notification_lister($idUtilisateur, $limite = 100)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "SELECT id_notification, titre, message, type, lu, date_envoi, date_lecture
         FROM notifications
         WHERE id_utilisateur = ?
         ORDER BY date_envoi DESC, id_notification DESC
         LIMIT ?"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la lecture des notifications : " . mysqli_error($link));
    }
    $idUtilisateur = (int) $idUtilisateur;
    $limite = max(1, min(500, (int) $limite));
    mysqli_stmt_bind_param($stmt, 'ii', $idUtilisateur, $limite);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de lire les notifications : " . $erreur);
    }
    $resultat = mysqli_stmt_get_result($stmt);
    $lignes = mysqli_fetch_all($resultat, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $lignes;
}

function bd_notification_compter_non_lues($idUtilisateur)
{
    $link = Connexion();
    $stmt = mysqli_prepare($link, "SELECT COUNT(*) FROM notifications WHERE id_utilisateur = ? AND lu = 0");
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer le compteur de notifications : " . mysqli_error($link));
    }
    $idUtilisateur = (int) $idUtilisateur;
    mysqli_stmt_bind_param($stmt, 'i', $idUtilisateur);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de compter les notifications : " . $erreur);
    }
    mysqli_stmt_bind_result($stmt, $nombre);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return (int) $nombre;
}

function bd_notification_marquer_lue($idNotification, $idUtilisateur)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "SELECT lu FROM notifications WHERE id_notification = ? AND id_utilisateur = ?"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de vérifier la notification : " . mysqli_error($link));
    }
    $idNotification = (int) $idNotification;
    $idUtilisateur = (int) $idUtilisateur;
    mysqli_stmt_bind_param($stmt, 'ii', $idNotification, $idUtilisateur);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de vérifier la notification : " . $erreur);
    }
    mysqli_stmt_bind_result($stmt, $lu);
    $existe = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if (!$existe) {
        return false;
    }
    if ((int) $lu === 1) {
        return true;
    }

    $stmt = mysqli_prepare(
        $link,
        "UPDATE notifications SET lu = 1, date_lecture = COALESCE(date_lecture, NOW())
         WHERE id_notification = ? AND id_utilisateur = ?"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la mise à jour de la notification : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, 'ii', $idNotification, $idUtilisateur);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de marquer la notification comme lue : " . $erreur);
    }
    mysqli_stmt_close($stmt);
    return true;
}
