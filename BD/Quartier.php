<?php

require_once __DIR__ . '/Connexion.php';

function bd_quartier_types_activite()
{
    $link = Connexion();
    $resultat = mysqli_query(
        $link,
        "SELECT id_type_activite, code, libelle
         FROM types_activite
         ORDER BY id_type_activite"
    );
    if (!$resultat) {
        throw new RuntimeException("Impossible de récupérer les types d’activité : " . mysqli_error($link));
    }
    return mysqli_fetch_all($resultat, MYSQLI_ASSOC);
}

function bd_quartier_lister()
{
    $link = Connexion();
    $resultat = mysqli_query(
        $link,
        "SELECT q.id_quartier, q.nom, q.adresse, q.id_type_activite, t.code AS type_activite,
                t.libelle AS libelle_activite, COUNT(DISTINCT c.id_contrat) AS nombre_contrats,
                COUNT(DISTINCT a.id_agent) AS nombre_agents
         FROM quartiers q
         JOIN types_activite t ON t.id_type_activite = q.id_type_activite
         LEFT JOIN contrats c ON c.id_quartier = q.id_quartier
         LEFT JOIN agents a ON a.id_quartier = q.id_quartier
         GROUP BY q.id_quartier, q.nom, q.adresse, q.id_type_activite, t.code, t.libelle
         ORDER BY q.nom"
    );
    if (!$resultat) {
        throw new RuntimeException("Impossible de récupérer les quartiers : " . mysqli_error($link));
    }
    return mysqli_fetch_all($resultat, MYSQLI_ASSOC);
}

function bd_quartier_enregistrer($idQuartier, $nom, $adresse, $idTypeActivite)
{
    $link = Connexion();
    if ($idQuartier) {
        $stmt = mysqli_prepare(
            $link,
            "UPDATE quartiers SET nom = ?, adresse = ?, id_type_activite = ? WHERE id_quartier = ?"
        );
        if (!$stmt) {
            throw new RuntimeException("Impossible de préparer la modification du quartier : " . mysqli_error($link));
        }
        mysqli_stmt_bind_param($stmt, 'ssii', $nom, $adresse, $idTypeActivite, $idQuartier);
    } else {
        $stmt = mysqli_prepare(
            $link,
            "INSERT INTO quartiers (nom, adresse, id_type_activite) VALUES (?, ?, ?)"
        );
        if (!$stmt) {
            throw new RuntimeException("Impossible de préparer la création du quartier : " . mysqli_error($link));
        }
        mysqli_stmt_bind_param($stmt, 'ssi', $nom, $adresse, $idTypeActivite);
    }
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible d’enregistrer le quartier : " . $erreur);
    }
    $idEnregistre = $idQuartier ?: mysqli_insert_id($link);
    mysqli_stmt_close($stmt);
    return (int) $idEnregistre;
}
