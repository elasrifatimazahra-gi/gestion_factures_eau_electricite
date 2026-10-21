<?php

require_once __DIR__ . "/Connexion.php";

function lireParametre($cle, $defaut = null)
{
    $link = Connexion();

    $sql = mysqli_prepare(
        $link,
        "SELECT valeur FROM parametres WHERE cle = ?"
    );

    if (!$sql) {
        return $defaut;
    }

    mysqli_stmt_bind_param($sql, "s", $cle);
    mysqli_stmt_execute($sql);

    $res = mysqli_stmt_get_result($sql);
    $row = mysqli_fetch_assoc($res);

    mysqli_stmt_close($sql);

    if ($row && $row['valeur'] !== null) {
        return $row['valeur'];
    }

    return $defaut;
}