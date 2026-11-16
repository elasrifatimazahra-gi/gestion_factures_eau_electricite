<?php

require_once ROOT . 'BD/Reclamation.php';
require_once ROOT . 'BD/Facture.php';
require_once ROOT . 'BD/Journal.php';
require_once ROOT . 'BD/Notification.php';
require_once ROOT . 'Traitement/Auth.php';

function Reclamation_liste($param = null)
{
    exiger_role('CLIENT');
    $idClient = (int) ($_SESSION['id_client'] ?? 0);
    $contrat = auth_exiger_contrat($idClient);
    $idContrat = $contrat ? (int) $contrat['id_contrat'] : null;
    $client = $idContrat ? bd_facture_infos_contrat($idContrat, $idClient) : bd_facture_infos_client($idClient);
    $client = $client ?: [];
    $reclamations = bd_reclamation_lister_client($idClient, $idContrat);
    foreach ($reclamations as &$reclamation) {
        $reclamation['messages'] = bd_reclamation_messages_lister((int) $reclamation['id_reclamation']);
    }
    unset($reclamation);
    $factures = $idContrat ? bd_facture_du_contrat($idContrat, $idClient) : bd_facture_du_client($idClient);
    $typesReclamation = TYPES_RECLAMATION;
    $erreur = $_SESSION['erreur_reclamation'] ?? '';
    $message = $_SESSION['message_reclamation'] ?? '';
    unset($_SESSION['erreur_reclamation'], $_SESSION['message_reclamation']);
    include ROOT . 'IHM/client/reclamations.php';
}

function Reclamation_creer($param = null)
{
    exiger_role('CLIENT');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rediriger('Reclamation/liste');
    }
    if (!csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        $_SESSION['erreur_reclamation'] = 'Le formulaire a expiré. Veuillez réessayer.';
        rediriger('Reclamation/liste');
    }

    $type = is_string($_POST['type_reclamation'] ?? null) ? $_POST['type_reclamation'] : '';
    $description = is_string($_POST['description'] ?? null) ? trim($_POST['description']) : '';
    $autreMotif = is_string($_POST['autre_motif'] ?? null) ? trim($_POST['autre_motif']) : '';
    $idFactureBrut = $_POST['id_facture'] ?? '';
    $idClient = (int) ($_SESSION['id_client'] ?? 0);
    $contrat = auth_exiger_contrat($idClient);
    $idContrat = $contrat ? (int) $contrat['id_contrat'] : null;
    $idFacture = $idFactureBrut === '' ? null : filter_var($idFactureBrut, FILTER_VALIDATE_INT);

    if (!in_array($type, TYPES_RECLAMATION, true) || $description === '' || mb_strlen($description) > 5000
        || ($type === 'AUTRE' && ($autreMotif === '' || mb_strlen($autreMotif) > 255))
        || ($idFactureBrut !== '' && (!$idFacture || !bd_facture_detail($idFacture, $idClient, $idContrat)))) {
        $_SESSION['erreur_reclamation'] = 'Vérifiez le type, la description et la facture associée.';
        rediriger('Reclamation/liste');
    }

    $link = Connexion();
    if (!mysqli_begin_transaction($link)) {
        throw new RuntimeException("Impossible de démarrer la création de la réclamation : " . mysqli_error($link));
    }
    try {
        $idReclamation = bd_reclamation_creer(
            $idClient,
            $idFacture,
            $type,
            $type === 'AUTRE' ? $autreMotif : null,
            $description,
            $idContrat
        );
        bd_journal_enregistrer(
            (int) $_SESSION['id_utilisateur'],
            'CREATION_RECLAMATION',
            'reclamations',
            $idReclamation,
            'Création d’une réclamation de type ' . $type
        );
        if (!mysqli_commit($link)) {
            throw new RuntimeException("Impossible de valider la réclamation : " . mysqli_error($link));
        }
    } catch (RuntimeException $exception) {
        mysqli_rollback($link);
        error_log($exception->getMessage());
        $_SESSION['erreur_reclamation'] = 'La réclamation n’a pas pu être enregistrée.';
        rediriger('Reclamation/liste');
    }

    $_SESSION['message_reclamation'] = 'Votre réclamation a été transmise au fournisseur.';
    rediriger('Reclamation/liste');
}

function Reclamation_index($param = null)
{
    exiger_role('FOURNISSEUR');
    $reclamations = bd_reclamation_lister_fournisseur();
    $erreur = $_SESSION['erreur_reclamation'] ?? '';
    $message = $_SESSION['message_reclamation'] ?? '';
    unset($_SESSION['erreur_reclamation'], $_SESSION['message_reclamation']);
    include ROOT . 'IHM/fournisseur/reclamations.php';
}

function Reclamation_message($param = null)
{
    exiger_role('CLIENT');
    $retour = 'Reclamation/liste';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        $_SESSION['erreur_reclamation'] = 'Le formulaire a expiré. Veuillez réessayer.';
        rediriger($retour);
    }

    $idReclamation = filter_var($_POST['id_reclamation'] ?? null, FILTER_VALIDATE_INT);
    $message = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
    $idClient = (int) ($_SESSION['id_client'] ?? 0);
    $contrat = auth_exiger_contrat($idClient);
    $idContrat = $contrat ? (int) $contrat['id_contrat'] : null;
    $reclamation = $idReclamation ? bd_reclamation_trouver($idReclamation, $idClient, $idContrat) : null;
    if (!$reclamation || !in_array($reclamation['statut'], ['OUVERTE', 'EN_COURS'], true)
        || $message === '' || mb_strlen($message) > 5000) {
        $_SESSION['erreur_reclamation'] = 'Cette réclamation ne peut pas recevoir de message.';
        rediriger($retour);
    }

    $link = Connexion();
    if (!mysqli_begin_transaction($link)) {
        throw new RuntimeException("Impossible de démarrer l’envoi du message : " . mysqli_error($link));
    }
    try {
        $idMessage = bd_reclamation_client_repondre(
            (int) $idReclamation,
            $idClient,
            (int) $_SESSION['id_utilisateur'],
            $message
        );
        if (!$idMessage) {
            throw new RuntimeException('La réclamation est clôturée ou introuvable.');
        }
        bd_journal_enregistrer(
            (int) $_SESSION['id_utilisateur'],
            'MESSAGE_RECLAMATION',
            'reclamation_messages',
            $idMessage,
            'Message client sur la réclamation ' . $reclamation['reference_reclamation']
        );
        if (!mysqli_commit($link)) {
            throw new RuntimeException("Impossible de valider l’envoi du message : " . mysqli_error($link));
        }
    } catch (RuntimeException $exception) {
        mysqli_rollback($link);
        error_log($exception->getMessage());
        $_SESSION['erreur_reclamation'] = 'Le message n’a pas pu être envoyé.';
        rediriger($retour);
    }

    $_SESSION['message_reclamation'] = 'Votre message a été transmis au fournisseur.';
    rediriger($retour);
}

function Reclamation_detail($param = null)
{
    exiger_role('FOURNISSEUR');
    $idReclamation = ctype_digit((string) $param) ? (int) $param : 0;
    $reclamation = $idReclamation > 0 ? bd_reclamation_trouver($idReclamation) : null;
    if (!$reclamation) {
        http_response_code(404);
        include ROOT . 'IHM/404.php';
        return;
    }
    $reclamation['messages'] = bd_reclamation_messages_lister((int) $reclamation['id_reclamation']);
    $erreur = $_SESSION['erreur_reclamation'] ?? '';
    $message = $_SESSION['message_reclamation'] ?? '';
    unset($_SESSION['erreur_reclamation'], $_SESSION['message_reclamation']);
    include ROOT . 'IHM/fournisseur/reclamation_detail.php';
}

function Reclamation_repondre($param = null)
{
    exiger_role('FOURNISSEUR');
    $idReclamation = ctype_digit((string) $param) ? (int) $param : 0;
    $retour = 'Reclamation/detail/' . $idReclamation;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        $_SESSION['erreur_reclamation'] = 'Le formulaire a expiré. Veuillez réessayer.';
        rediriger($idReclamation > 0 ? $retour : 'Reclamation/index');
    }

    $reclamation = $idReclamation > 0 ? bd_reclamation_trouver($idReclamation) : null;
    $reponse = is_string($_POST['reponse_fournisseur'] ?? null) ? trim($_POST['reponse_fournisseur']) : '';
    $decision = $_POST['decision'] ?? '';
    $statut = [
        'continuer' => 'EN_COURS',
        'traiter' => 'TRAITEE',
        'rejeter' => 'REJETEE',
    ][$decision] ?? null;
    if (!$reclamation || !in_array($reclamation['statut'], ['OUVERTE', 'EN_COURS'], true)
        || !$statut || $reponse === '' || mb_strlen($reponse) > 5000) {
        $_SESSION['erreur_reclamation'] = 'La réclamation ou la réponse est invalide.';
        rediriger($idReclamation > 0 ? $retour : 'Reclamation/index');
    }

    $link = Connexion();
    if (!mysqli_begin_transaction($link)) {
        throw new RuntimeException("Impossible de démarrer le traitement de la réclamation : " . mysqli_error($link));
    }
    try {
        $idMessage = bd_reclamation_repondre($idReclamation, (int) $_SESSION['id_utilisateur'], $reponse, $statut);
        if (!$idMessage) {
            throw new RuntimeException('La réclamation a déjà été traitée.');
        }
        bd_notification_creer(
            (int) $reclamation['id_utilisateur'],
            'Réponse à votre réclamation ' . $reclamation['reference_reclamation'],
            $reponse,
            'RECLAMATION_REPONSE'
        );
        bd_journal_enregistrer(
            (int) $_SESSION['id_utilisateur'],
            'REPONSE_RECLAMATION',
            'reclamation_messages',
            $idMessage,
            'Message fournisseur sur la réclamation ' . $reclamation['reference_reclamation'] . ' (statut ' . $statut . ')'
        );
        if (!mysqli_commit($link)) {
            throw new RuntimeException("Impossible de valider la réponse à la réclamation : " . mysqli_error($link));
        }
    } catch (RuntimeException $exception) {
        mysqli_rollback($link);
        error_log($exception->getMessage());
        $_SESSION['erreur_reclamation'] = 'La réponse n’a pas pu être enregistrée.';
        rediriger($retour);
    }

    $_SESSION['message_reclamation'] = 'La réponse a été transmise au client.';
    rediriger($retour);
}
