<?php
function Connexion($serv = "localhost", $user = "root", $pass = "", $bd = "gestion_factures_tp2")
{
    static $link = null;              
    if ($link === null) {
        $link = mysqli_connect($serv, $user, $pass, $bd);
        if (!$link) {
            die("Erreur de connexion à la base : " . mysqli_connect_error());
        }
        mysqli_set_charset($link, "utf8mb4");
    }
    return $link;
}

