<?php

require_once __DIR__ . '/Connexion.php';

const TYPES_RECLAMATION = [
    'FUITE_EXTERNE',
    'FUITE_INTERNE',
    'FACTURE',
    'PROBLEME_COMPTEUR',
    'COUPURE_INEXPLIQUEE',
    'AUTRE',
];

function bd_reclamation_lister_client($idClient)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "SELECT r.*, f.numero_facture
         FROM reclamations r
         LEFT JOIN factures f ON f.id_facture = r.id_facture
         WHERE r.id_client = ?
         ORDER BY r.created_at DESC, r.id_reclamation DESC"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la liste des réclamations : " . mysqli_error($link));
    }
    $idClient = (int) $idClient;
    mysqli_stmt_bind_param($stmt, 'i', $idClient);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de lire les réclamations : " . $erreur);
    }
    $lignes = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $lignes;
}

function bd_reclamation_messages_lister($idReclamation)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "SELECT m.id_message, m.id_auteur, m.role_auteur, m.message, m.created_at,
                CASE
                    WHEN m.role_auteur = 'CLIENT' THEN COALESCE(NULLIF(CONCAT_WS(' ', c.prenom, c.nom), ''), 'Client')
                    ELSE COALESCE(u.identifiant, 'Fournisseur')
                END AS auteur
         FROM reclamation_messages m
         LEFT JOIN utilisateurs u ON u.id_utilisateur = m.id_auteur
         LEFT JOIN clients c ON c.id_utilisateur = u.id_utilisateur
         WHERE m.id_reclamation = ?
         ORDER BY m.created_at, m.id_message"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la conversation : " . mysqli_error($link));
    }
    $idReclamation = (int) $idReclamation;
    mysqli_stmt_bind_param($stmt, 'i', $idReclamation);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de lire la conversation : " . $erreur);
    }
    $messages = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $messages;
}

function bd_reclamation_message_creer($idReclamation, $idAuteur, $roleAuteur, $message)
{
    if (!in_array($roleAuteur, ['CLIENT', 'FOURNISSEUR'], true)) {
        throw new InvalidArgumentException('Rôle de message invalide.');
    }
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "INSERT INTO reclamation_messages (id_reclamation, id_auteur, role_auteur, message)
         VALUES (?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer l’enregistrement du message : " . mysqli_error($link));
    }
    $idReclamation = (int) $idReclamation;
    $idAuteur = (int) $idAuteur;
    mysqli_stmt_bind_param($stmt, 'iiss', $idReclamation, $idAuteur, $roleAuteur, $message);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible d’enregistrer le message : " . $erreur);
    }
    $idMessage = (int) mysqli_insert_id($link);
    mysqli_stmt_close($stmt);
    return $idMessage;
}

function bd_reclamation_lister_fournisseur()
{
    $link = Connexion();
    $sql = "SELECT r.id_reclamation, r.reference_reclamation, r.type_reclamation, r.statut,
                   r.created_at, r.description, f.numero_facture,
                   c.id_client, c.numero_client, c.nom, c.prenom
            FROM reclamations r
            JOIN clients c ON c.id_client = r.id_client
            LEFT JOIN factures f ON f.id_facture = r.id_facture
            ORDER BY FIELD(r.statut, 'OUVERTE', 'EN_COURS', 'TRAITEE', 'REJETEE'),
                     r.created_at DESC, r.id_reclamation DESC";
    $resultat = mysqli_query($link, $sql);
    if (!$resultat) {
        throw new RuntimeException("Impossible de lire les réclamations : " . mysqli_error($link));
    }
    return mysqli_fetch_all($resultat, MYSQLI_ASSOC);
}

function bd_reclamation_trouver($idReclamation, $idClient = null)
{
    $link = Connexion();
    $sql = "SELECT r.*, f.numero_facture, c.id_client, c.id_utilisateur,
                   c.numero_client, c.nom, c.prenom, c.telephone, c.adresse
            FROM reclamations r
            JOIN clients c ON c.id_client = r.id_client
            LEFT JOIN factures f ON f.id_facture = r.id_facture
            WHERE r.id_reclamation = ?";
    if ($idClient !== null) {
        $sql .= " AND r.id_client = ?";
    }
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la recherche de la réclamation : " . mysqli_error($link));
    }
    $idReclamation = (int) $idReclamation;
    if ($idClient === null) {
        mysqli_stmt_bind_param($stmt, 'i', $idReclamation);
    } else {
        $idClient = (int) $idClient;
        mysqli_stmt_bind_param($stmt, 'ii', $idReclamation, $idClient);
    }
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de rechercher la réclamation : " . $erreur);
    }
    $ligne = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $ligne ?: null;
}

function bd_reclamation_creer($idClient, $idFacture, $type, $autreMotif, $description)
{
    $link = Connexion();
    $reference = 'REC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    $stmt = mysqli_prepare(
        $link,
        "INSERT INTO reclamations
            (reference_reclamation, id_client, id_facture, type_reclamation, autre_motif, description)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la création de la réclamation : " . mysqli_error($link));
    }
    $idClient = (int) $idClient;
    $idFacture = $idFacture === null ? null : (int) $idFacture;
    mysqli_stmt_bind_param($stmt, 'siisss', $reference, $idClient, $idFacture, $type, $autreMotif, $description);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de créer la réclamation : " . $erreur);
    }
    $id = (int) mysqli_insert_id($link);
    mysqli_stmt_close($stmt);
    return $id;
}

function bd_reclamation_repondre($idReclamation, $idUtilisateur, $reponse, $statut)
{
    if (!in_array($statut, ['EN_COURS', 'TRAITEE', 'REJETEE'], true)) {
        throw new InvalidArgumentException('Décision de réclamation invalide.');
    }
    $link = Connexion();
    $verifier = mysqli_prepare(
        $link,
        "SELECT id_reclamation FROM reclamations
         WHERE id_reclamation = ? AND statut IN ('OUVERTE', 'EN_COURS')
         FOR UPDATE"
    );
    if (!$verifier) {
        throw new RuntimeException("Impossible de vérifier l’état de la réclamation : " . mysqli_error($link));
    }
    $idReclamation = (int) $idReclamation;
    mysqli_stmt_bind_param($verifier, 'i', $idReclamation);
    if (!mysqli_stmt_execute($verifier)) {
        $erreur = mysqli_stmt_error($verifier);
        mysqli_stmt_close($verifier);
        throw new RuntimeException("Impossible de vérifier l’état de la réclamation : " . $erreur);
    }
    $active = mysqli_fetch_assoc(mysqli_stmt_get_result($verifier));
    mysqli_stmt_close($verifier);
    if (!$active) {
        return false;
    }

    $stmt = mysqli_prepare(
        $link,
        "UPDATE reclamations
         SET statut = ?, reponse_fournisseur = ?, date_reponse = NOW(), traite_par = ?
         WHERE id_reclamation = ? AND statut IN ('OUVERTE', 'EN_COURS')"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la réponse à la réclamation : " . mysqli_error($link));
    }
    $idUtilisateur = (int) $idUtilisateur;
    mysqli_stmt_bind_param($stmt, 'ssii', $statut, $reponse, $idUtilisateur, $idReclamation);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible d’enregistrer la réponse : " . $erreur);
    }
    mysqli_stmt_close($stmt);
    return bd_reclamation_message_creer($idReclamation, $idUtilisateur, 'FOURNISSEUR', $reponse);
}

function bd_reclamation_client_repondre($idReclamation, $idClient, $idUtilisateur, $message)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "SELECT id_reclamation FROM reclamations
         WHERE id_reclamation = ? AND id_client = ? AND statut IN ('OUVERTE', 'EN_COURS')
         FOR UPDATE"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de vérifier la réclamation : " . mysqli_error($link));
    }
    $idReclamation = (int) $idReclamation;
    $idClient = (int) $idClient;
    mysqli_stmt_bind_param($stmt, 'ii', $idReclamation, $idClient);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de vérifier la réclamation : " . $erreur);
    }
    $active = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$active) {
        return false;
    }

    $stmt = mysqli_prepare($link, "UPDATE reclamations SET statut = 'EN_COURS' WHERE id_reclamation = ?");
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la mise à jour de la réclamation : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, 'i', $idReclamation);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de mettre à jour la réclamation : " . $erreur);
    }
    mysqli_stmt_close($stmt);

    return bd_reclamation_message_creer($idReclamation, $idUtilisateur, 'CLIENT', $message);
}
