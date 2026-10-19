<?php
require_once __DIR__ . "/Connexion.php";
$link = Connexion();
function lireParametre($cle, $defaut = null)
{
    global $link;
    $cle = mysqli_real_escape_string($link, $cle);
    $sql = "SELECT valeur FROM parametres WHERE cle = '$cle'";
    $res = mysqli_query($link, $sql);
    $row = mysqli_fetch_assoc($res);
    if ($row && $row['valeur'] !== null) {
        return $row['valeur'];
    }
    return $defaut;
}