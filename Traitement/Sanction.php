<?php

require_once ROOT . 'BD/Sanction.php';
require_once ROOT . 'BD/Contrat.php';
require_once ROOT . 'BD/Journal.php';
require_once ROOT . 'BD/Notification.php';
require_once ROOT . 'Traitement/Auth.php';

function Sanction_index($param = null)
{
    exiger_role('FOURNISSEUR');
    $sanctions = bd_sanction_lister();
    $clients = bd_sanction_clients_lister();
    $contrats = bd_contrat_lister();
    $erreur = $_SESSION['erreur_sanction'] ?? '';
    $message = $_SESSION['message_sanction'] ?? '';
    unset($_SESSION['erreur_sanction'], $_SESSION['message_sanction']);
    include ROOT . 'IHM/fournisseur/sanctions.php';
}

function Sanction_creer($param = null)
{
    exiger_role('FOURNISSEUR');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        $_SESSION['erreur_sanction'] = 'Le formulaire a expiré. Veuillez réessayer.';
        rediriger('Sanction/index');
    }

    $idClient = filter_var($_POST['id_client'] ?? null, FILTER_VALIDATE_INT);
    $idContrat = filter_var($_POST['id_contrat'] ?? null, FILTER_VALIDATE_INT) ?: null;
    $motif = is_string($_POST['motif'] ?? null) ? $_POST['motif'] : '';
    $energie = filter_var($_POST['montant_energie_regularisee'] ?? null, FILTER_VALIDATE_FLOAT);
    $penalite = filter_var($_POST['penalite_fraude'] ?? null, FILTER_VALIDATE_FLOAT);
    $frais = filter_var($_POST['frais_retablissement'] ?? null, FILTER_VALIDATE_FLOAT);
    $dateLimite = sanction_date_valide($_POST['date_limite_paiement'] ?? null);
    $dateCoupure = sanction_date_valide($_POST['date_coupure_programmee'] ?? null);
    $clients = array_map('intval', array_column(bd_sanction_clients_lister(), 'id_client'));
    $contratsDuClient = $idClient ? array_map('intval', array_column(bd_contrat_lister((int) $idClient), 'id_contrat')) : [];
    if (!$idClient || !in_array((int) $idClient, $clients, true)
        || !in_array($motif, MOTIFS_SANCTION, true)
        || $energie === false || $energie < 0 || $penalite === false || $penalite < 0
        || $frais === false || $frais < 0 || !$dateLimite || !$dateCoupure
        || $dateLimite < date('Y-m-d') || $dateCoupure <= $dateLimite
        || ($idContrat !== null && !in_array($idContrat, $contratsDuClient, true))) {
        $_SESSION['erreur_sanction'] = 'Vérifiez le client, le contrat, le motif, les montants et les dates.';
        rediriger('Sanction/index');
    }

    $link = Connexion();
    if (!mysqli_begin_transaction($link)) {
        throw new RuntimeException("Impossible de démarrer la création de la sanction : " . mysqli_error($link));
    }
    try {
        $sanctionCree = bd_sanction_creer([
            'id_client' => (int) $idClient,
            'id_contrat' => $idContrat,
            'motif' => $motif,
            'montant_energie_regularisee' => (float) $energie,
            'penalite_fraude' => (float) $penalite,
            'frais_retablissement' => (float) $frais,
            'date_limite_paiement' => $dateLimite,
            'date_coupure_programmee' => $dateCoupure,
        ]);
        $idSanction = $sanctionCree['id'];
        $stmt = mysqli_prepare($link, "SELECT id_utilisateur FROM clients WHERE id_client = ?");
        if (!$stmt) {
            throw new RuntimeException("Impossible de rechercher le destinataire : " . mysqli_error($link));
        }
        mysqli_stmt_bind_param($stmt, 'i', $idClient);
        if (!mysqli_stmt_execute($stmt)) {
            $erreurSql = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new RuntimeException("Impossible de rechercher le destinataire : " . $erreurSql);
        }
        $client = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$client) {
            throw new RuntimeException('Client introuvable pour la notification de sanction.');
        }
        bd_notification_creer(
            (int) $client['id_utilisateur'],
            'Nouvelle sanction',
            'Une sanction a été émise. Référence : ' . $sanctionCree['reference'] . '. Consultez votre espace client pour les détails.',
            'SANCTION'
        );
        bd_journal_enregistrer(
            (int) $_SESSION['id_utilisateur'],
            'CREATION_SANCTION',
            'sanctions',
            $idSanction,
            'Création d’une sanction pour le client ' . (int) $idClient
        );
        if (!mysqli_commit($link)) {
            throw new RuntimeException("Impossible de valider la création de la sanction : " . mysqli_error($link));
        }
    } catch (RuntimeException $exception) {
        mysqli_rollback($link);
        error_log($exception->getMessage());
        $_SESSION['erreur_sanction'] = 'La sanction n’a pas pu être enregistrée.';
        rediriger('Sanction/index');
    }
    $_SESSION['message_sanction'] = 'La sanction a été enregistrée et notifiée au client.';
    rediriger('Sanction/index');
}

function Sanction_decider($param = null)
{
    exiger_role('FOURNISSEUR');
    $idSanction = ctype_digit((string) $param) ? (int) $param : 0;
    $statutRetour = 'Sanction/index';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        $_SESSION['erreur_sanction'] = 'Le formulaire a expiré. Veuillez réessayer.';
        rediriger($statutRetour);
    }
    $decision = $_POST['decision'] ?? '';
    $statut = ['confirmer' => 'CONFIRMEE', 'annuler' => 'ANNULEE'][$decision] ?? null;
    $sanction = $idSanction > 0 ? bd_sanction_trouver($idSanction) : null;
    if (!$sanction || !$statut || !in_array($sanction['statut'], ['NOTIFIEE', 'CONTESTEE'], true)) {
        $_SESSION['erreur_sanction'] = 'Cette sanction n’existe pas ou a déjà été traitée.';
        rediriger($statutRetour);
    }

    $link = Connexion();
    if (!mysqli_begin_transaction($link)) {
        throw new RuntimeException("Impossible de démarrer la décision de sanction : " . mysqli_error($link));
    }
    try {
        if (!bd_sanction_decider($idSanction, $statut)) {
            throw new RuntimeException('La sanction a déjà été traitée.');
        }
        $decisionLibelle = $statut === 'CONFIRMEE' ? 'confirmée' : 'annulée';
        bd_notification_creer(
            (int) $sanction['id_utilisateur'],
            'Mise à jour de votre sanction',
            'La sanction ' . $sanction['reference_sanction'] . ' a été ' . $decisionLibelle . '.',
            'SANCTION'
        );
        bd_journal_enregistrer(
            (int) $_SESSION['id_utilisateur'],
            'DECISION_SANCTION',
            'sanctions',
            $idSanction,
            'Sanction ' . $sanction['reference_sanction'] . ' ' . $decisionLibelle
        );
        if (!mysqli_commit($link)) {
            throw new RuntimeException("Impossible de valider la décision de sanction : " . mysqli_error($link));
        }
    } catch (RuntimeException $exception) {
        mysqli_rollback($link);
        error_log($exception->getMessage());
        $_SESSION['erreur_sanction'] = 'La décision n’a pas pu être enregistrée.';
        rediriger($statutRetour);
    }
    $_SESSION['message_sanction'] = 'La sanction a été mise à jour et le client a été notifié.';
    rediriger($statutRetour);
}

function sanction_date_valide($valeur)
{
    if (!is_string($valeur)) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);
    $erreurs = DateTimeImmutable::getLastErrors();
    if (!$date || ($erreurs && ($erreurs['warning_count'] || $erreurs['error_count']))
        || $date->format('Y-m-d') !== $valeur) {
        return null;
    }
    return $date->format('Y-m-d');
}
