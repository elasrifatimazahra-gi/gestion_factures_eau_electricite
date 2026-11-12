<?php

require_once __DIR__ . '/Connexion.php';

function bd_agent_lister()
{
    $link = Connexion();
    $resultat = mysqli_query(
        $link,
        "SELECT a.id_agent, a.matricule, a.nom, a.prenom, a.telephone, a.email,
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

function bd_agent_enregistrer($matricule, $nom, $prenom, $telephone, $email, $zone, $idQuartier)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "INSERT INTO agents (matricule, nom, prenom, telephone, email, zone_affectation, id_quartier)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la création de l’agent : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, 'ssssssi', $matricule, $nom, $prenom, $telephone, $email, $zone, $idQuartier);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de créer l’agent : " . $erreur);
    }
    $idAgent = mysqli_insert_id($link);
    mysqli_stmt_close($stmt);
    return (int) $idAgent;
}
