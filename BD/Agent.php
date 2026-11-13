<?php

require_once __DIR__ . '/Connexion.php';

function bd_agent_lister()
{
    $link = Connexion();
    $resultat = mysqli_query(
        $link,
        "SELECT a.id_agent, a.id_utilisateur, a.matricule, a.nom, a.prenom, a.telephone, a.email,
                a.zone_affectation, a.actif, a.id_quartier, q.nom AS quartier
         FROM agents a
         LEFT JOIN quartiers q ON q.id_quartier = a.id_quartier
         ORDER BY a.nom, a.prenom"
    );
    if (!$resultat) {
        throw new RuntimeException("Impossible de récupérer les agents : " . mysqli_error($link));
    }
    return mysqli_fetch_all($resultat, MYSQLI_ASSOC);
}

function bd_agent_enregistrer($matricule, $nom, $prenom, $telephone, $email, $zone, $idQuartier, $idUtilisateur)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "INSERT INTO agents (id_utilisateur, matricule, nom, prenom, telephone, email, zone_affectation, id_quartier)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la création de l’agent : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, 'issssssi', $idUtilisateur, $matricule, $nom, $prenom, $telephone, $email, $zone, $idQuartier);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de créer l’agent : " . $erreur);
    }
    $idAgent = mysqli_insert_id($link);
    mysqli_stmt_close($stmt);
    return (int) $idAgent;
}

function bd_agent_par_utilisateur($idUtilisateur)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "SELECT id_agent, matricule, nom, prenom, id_quartier
         FROM agents
         WHERE id_utilisateur = ? AND actif = 1
         LIMIT 1"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la recherche de l’agent : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, 'i', $idUtilisateur);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de récupérer l’agent : " . $erreur);
    }
    $resultat = mysqli_stmt_get_result($stmt);
    $agent = mysqli_fetch_assoc($resultat);
    mysqli_stmt_close($stmt);

    return $agent ?: null;
}

function bd_agent_tournee_lister($idAgent, $idContrat = null)
{
    $link = Connexion();
    $sql = "SELECT c.id_contrat, c.numero_contrat, c.index_initial_eau, c.index_initial_elec,
                   cl.id_client, cl.numero_client, cl.nom, cl.prenom, cl.adresse
            FROM agents a
            JOIN contrats c ON c.id_quartier = a.id_quartier
            JOIN clients cl ON cl.id_client = c.id_client
            WHERE a.id_agent = ? AND a.actif = 1 AND c.statut = 'ACTIF'";
    if ($idContrat !== null) {
        $sql .= " AND c.id_contrat = ?";
    }
    $sql .= " ORDER BY cl.nom, cl.prenom, c.numero_contrat";

    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la tournée de l’agent : " . mysqli_error($link));
    }
    if ($idContrat !== null) {
        mysqli_stmt_bind_param($stmt, 'ii', $idAgent, $idContrat);
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $idAgent);
    }
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de récupérer la tournée de l’agent : " . $erreur);
    }
    $resultat = mysqli_stmt_get_result($stmt);
    $contrats = mysqli_fetch_all($resultat, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return $contrats;
}
