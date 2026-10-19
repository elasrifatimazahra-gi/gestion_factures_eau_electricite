<?php
include_once "Connexion.php";

function bd_utilisateur_trouver($login,$role)
{
       $link=Connexion();
       $colonne = ($role === 'CLIENT') ? 'identifiant' : 'email';
       $sql= "SELECT *,
               (bloque_jusqu_a IS NOT NULL AND bloque_jusqu_a > NOW()) AS est_bloque
        FROM utilisateurs
        WHERE $colonne = ? AND role = ?";

    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $login, $role);
    mysqli_stmt_execute($stmt);

    $resultat = mysqli_stmt_get_result($stmt);
    $ligne    = mysqli_fetch_assoc($resultat);
    mysqli_stmt_close($stmt); 
    return $ligne ?: null;

}

function bd_utilisateur_echec($id)
{
    $link = Connexion();
    $id = (int) $id;      // "5 OR 1=1" devient 5, et "abc" devient 0
    $sql = "UPDATE utilisateurs SET tentatives_echouees = tentatives_echouees + 1
        WHERE id_utilisateur = $id";
    mysqli_query($link, $sql);
}

function bd_utilisateur_reset($id)
{
    $link = Connexion();
    $id = (int) $id;
    $sql = "UPDATE utilisateurs
            SET tentatives_echouees = 0, bloque_jusqu_a = NULL, derniere_connexion = NOW()
            WHERE id_utilisateur = $id";
    mysqli_query($link, $sql);//Envoie la requête à MySQL.
}

function  bd_utilisateur_bloquer($id, $minutes)
{
    $link = Connexion();
    $id = (int) $id;
    $minutes = (int) $minutes;

    $sql = "UPDATE utilisateurs 
            SET bloque_jusqu_a = DATE_ADD(NOW(), INTERVAL $minutes MINUTE), tentatives_echouees = 0 
            WHERE id_utilisateur = $id";
    mysqli_query($link, $sql);
}

// Retourne l'id_client lié à un compte utilisateur, ou null s'il n'a pas de fiche client
function bd_utilisateur_id_client($id_utilisateur)
{
    $link = Connexion();
    $id_utilisateur = (int) $id_utilisateur;
    $sql = "SELECT id_client FROM clients WHERE id_utilisateur = $id_utilisateur";
    $res = mysqli_query($link, $sql);
    $ligne = mysqli_fetch_assoc($res);
    return $ligne ? (int) $ligne['id_client'] : null;
}