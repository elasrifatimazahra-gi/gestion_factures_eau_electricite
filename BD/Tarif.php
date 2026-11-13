<?php
require_once __DIR__ . "/Connexion.php";

/**
 * Id du type d'activité « Résidentiel » : c'est le type par défaut
 * (contrat sans quartier, ou ancien appel sans type).
 */
function bd_tarif_type_residentiel()
{
    $link = Connexion();
    $res = mysqli_query($link, "SELECT id_type_activite FROM types_activite WHERE code = 'RESIDENTIEL' LIMIT 1");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row ? (int) $row['id_type_activite'] : 1;
}

/**
 * Type d'activité d'un contrat : contrat -> quartier -> type d'activité.
 * Contrat introuvable ou sans quartier : Résidentiel.
 */
function bd_tarif_type_du_contrat($idContrat)
{
    $link = Connexion();
    $idContrat = (int) $idContrat;
    $res = mysqli_query($link, "SELECT q.id_type_activite
                                FROM contrats c
                                JOIN quartiers q ON q.id_quartier = c.id_quartier
                                WHERE c.id_contrat = $idContrat");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row ? (int) $row['id_type_activite'] : bd_tarif_type_residentiel();
}

/**
 * Retourne la tranche tarifaire qui correspond à la consommation totale,
 * dans la grille du type d'activité demandé (Résidentiel si non précisé).
 * Ex. : EAU, 9 m3, Résidentiel -> tranche 2 (7 à 20 m3), prix 7.39 DH/m3
 */
function bd_tarif_pour_conso($typeFluide, $conso, $idTypeActivite = null)
{
    if (!in_array($typeFluide, ['EAU', 'ELECTRICITE'], true)) {
        return null;
    }
    $link = Connexion();
    $conso = max(0, (int) $conso);
    $idType = ($idTypeActivite === null) ? bd_tarif_type_residentiel() : (int) $idTypeActivite;
    $sql = "SELECT tranche_num, prix_unitaire_dh, description
            FROM tarifs_tranches
            WHERE type_fluide = '$typeFluide'
              AND id_type_activite = $idType
              AND actif = 1
              AND borne_min <= $conso
              AND (borne_max IS NULL OR borne_max >= $conso)
            ORDER BY tranche_num
            LIMIT 1";
    $res = mysqli_query($link, $sql);
    return $res ? mysqli_fetch_assoc($res) : null;
}