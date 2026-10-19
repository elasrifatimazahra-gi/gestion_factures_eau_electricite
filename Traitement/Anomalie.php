<?php
require_once __DIR__ . "/../BD/Anomalie.php";
require_once __DIR__ . "/../BD/Parametre.php";

const TYPE_INDEX_INFERIEUR = 'Index inférieur au précédent';
const TYPE_SEUIL_DEPASSE   = 'Consommation au-dessus du seuil';

function detecter($idConso, $idClient, $indexEau, $indexElec, $precEau, $precElec)
{
    $seuilElec = (float) lireParametre('seuil_electricite', 2000);
    $seuilEau  = (float) lireParametre('seuil_eau', 100);

    $indexEau  = (float) $indexEau;
    $indexElec = (float) $indexElec;
    $precEau   = (float) $precEau;
    $precElec  = (float) $precElec;

    $type = null;
    if ($indexEau < $precEau || $indexElec < $precElec) {
        $type = TYPE_INDEX_INFERIEUR;
    } elseif (($indexEau - $precEau) > $seuilEau || ($indexElec - $precElec) > $seuilElec) {
        $type = TYPE_SEUIL_DEPASSE;
    }

    if ($type === null) {
        return false;
    }
    creerAnomalie($idConso, $type);
    return true;
}