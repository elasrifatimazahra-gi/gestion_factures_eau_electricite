<?php
require_once __DIR__ . "/Connexion.php";

/**
 * Retourne la tranche tarifaire qui correspond à la consommation totale.
 * Ex. : EAU, 9 m3  -> tranche 2 (7 à 20 m3), prix 7.39 DH/m3
 */
function bd_tarif_pour_conso($typeFluide, $conso)
{
    if (!in_array($typeFluide, ['EAU', 'ELECTRICITE'], true)) {
        return null;
    }
    $link = Connexion();
    $conso = max(0, (int) $conso);
    $sql = "SELECT tranche_num, prix_unitaire_dh, description
            FROM tarifs_tranches
            WHERE type_fluide = '$typeFluide'
              AND actif = 1
              AND borne_min <= $conso
              AND (borne_max IS NULL OR borne_max >= $conso)
            ORDER BY tranche_num
            LIMIT 1";
    $res = mysqli_query($link, $sql);
    return $res ? mysqli_fetch_assoc($res) : null;
}