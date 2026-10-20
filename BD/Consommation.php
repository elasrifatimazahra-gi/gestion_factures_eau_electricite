<?php
include_once "Connexion.php";

function saisieExiste($idClient, $mois, $annee)
{
    $conn = Connexion();
    $sql  = mysqli_prepare($conn, "SELECT COUNT(*) FROM consommations WHERE id_client = ? AND mois = ? AND annee = ?");
    mysqli_stmt_bind_param($sql, "iii", $idClient, $mois, $annee);
    mysqli_stmt_execute($sql);
    mysqli_stmt_bind_result($sql, $count);
    mysqli_stmt_fetch($sql);
    mysqli_stmt_close($sql);
    return $count > 0;
}

function derniereSaisie($idClient)
{
    $conn = Connexion();
    $sql  = mysqli_prepare($conn, "SELECT * FROM consommations WHERE id_client = ? ORDER BY annee DESC, mois DESC LIMIT 1");
    mysqli_stmt_bind_param($sql, "i", $idClient);
    mysqli_stmt_execute($sql);
    $ligne = mysqli_fetch_assoc(mysqli_stmt_get_result($sql));
    mysqli_stmt_close($sql);
    return $ligne ? $ligne : null;
}

// Index d'installation du client (utilisé pour la première saisie)
function indexInitial($idClient)
{
    $conn = Connexion();
    $sql  = mysqli_prepare($conn, "SELECT index_initial_eau, index_initial_elec FROM clients WHERE id_client = ?");
    mysqli_stmt_bind_param($sql, "i", $idClient);
    mysqli_stmt_execute($sql);
    $ligne = mysqli_fetch_assoc(mysqli_stmt_get_result($sql));
    mysqli_stmt_close($sql);
    return $ligne;
}

function enregistrer($idClient, $annee, $mois, $indexEauPrec, $indexEauNouv, $consoEau, $photoEau, $indexElecPrec, $indexElecNouv, $consoElec, $photoElec, $typeSaisie = 'REELLE', $statut = 'VALIDE')
{
    $conn = Connexion();
    $sql  = mysqli_prepare($conn, "INSERT INTO consommations (id_client, annee, mois, index_eau_precedent, index_eau_nouveau, conso_eau, photo_compteur_eau, index_elec_precedent, index_elec_nouveau, conso_elec, photo_compteur_elec, type_saisie, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($sql, "iiiiiisiiisss", $idClient, $annee, $mois, $indexEauPrec, $indexEauNouv, $consoEau, $photoEau, $indexElecPrec, $indexElecNouv, $consoElec, $photoElec, $typeSaisie, $statut);
    $ok = mysqli_stmt_execute($sql);
    $id = mysqli_insert_id($conn);
    mysqli_stmt_close($sql);
    return $ok ? $id : false;
}

function trouverParId($idConso)
{
    $conn = Connexion();
    $sql  = mysqli_prepare($conn, "SELECT * FROM consommations WHERE id_conso = ?");
    mysqli_stmt_bind_param($sql, "i", $idConso);
    mysqli_stmt_execute($sql);
    $ligne = mysqli_fetch_assoc(mysqli_stmt_get_result($sql));
    mysqli_stmt_close($sql);
    return $ligne ? $ligne : null;
}

function mettreAJourStatut($id, $statut)
{
    $conn = Connexion();
    $sql  = mysqli_prepare($conn, "UPDATE consommations SET statut = ? WHERE id_conso = ?");
    mysqli_stmt_bind_param($sql, "si", $statut, $id);
    $ok = mysqli_stmt_execute($sql);
    mysqli_stmt_close($sql);
    return $ok;
}