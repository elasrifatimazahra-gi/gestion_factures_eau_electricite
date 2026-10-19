<?php

require_once __DIR__ . "/Connexion.php";
$link = Connexion();

function creerAnomalie($idConso, $type)
{
    global $link;
    $idConso = (int) $idConso;
    $type    = mysqli_real_escape_string($link, $type);
    $sql = "INSERT INTO anomalies (type, statut, date_detection, id_conso)
            VALUES ('$type', 'ouverte', NOW(), $idConso)";
    mysqli_query($link, $sql);
    return mysqli_insert_id($link);
}

function listerAnomalies($statut = null)
{
    global $link;
    $sql = "SELECT a.id_anomalie, a.type, a.statut, a.date_detection,
                   c.mois, c.annee,
                   cl.numero_client, cl.nom, cl.prenom
            FROM anomalies a
            JOIN consommations c ON c.id_conso = a.id_conso
            JOIN clients cl      ON cl.id_client = c.id_client";
    if ($statut !== null && $statut !== '') {
        $statut = mysqli_real_escape_string($link, $statut);
        $sql .= " WHERE a.statut = '$statut'";
    }
    $sql .= " ORDER BY a.date_detection DESC";
    return mysqli_query($link, $sql);
}

function trouverAnomalieParId($id)
{
    global $link;
    $id = (int) $id;
    $sql = "SELECT a.id_anomalie, a.type, a.statut, a.date_detection, a.date_traitement,
                   c.id_conso, c.mois, c.annee, c.index_eau, c.index_elec,
                   c.photo_eau, c.photo_elec,
                   cl.id_client, cl.id_utilisateur, cl.numero_client, cl.nom, cl.prenom
            FROM anomalies a
            JOIN consommations c ON c.id_conso = a.id_conso
            JOIN clients cl      ON cl.id_client = c.id_client
            WHERE a.id_anomalie = $id";
    return mysqli_query($link, $sql);
}

function marquerAnomalieTraitee($id)
{
    global $link;
    $id = (int) $id;
    $sql = "UPDATE anomalies SET statut = 'traitee', date_traitement = NOW() WHERE id_anomalie = $id";
    return mysqli_query($link, $sql);
}
function trouverIndexPrecedent($idClient, $mois, $annee)
{
    global $link;
    $idClient = (int) $idClient;
    $mois     = (int) $mois;
    $annee    = (int) $annee;

    $sql = "SELECT index_eau, index_elec FROM consommations
            WHERE id_client = $idClient
              AND index_eau IS NOT NULL AND index_elec IS NOT NULL
              AND statut <> 'anomalie'
              AND (annee < $annee OR (annee = $annee AND mois < $mois))
            ORDER BY annee DESC, mois DESC LIMIT 1";
    $row = mysqli_fetch_assoc(mysqli_query($link, $sql));
    if ($row) {
        return ['eau' => (float) $row['index_eau'], 'elec' => (float) $row['index_elec']];
    }

    $sql = "SELECT index_initial_eau, index_initial_elec FROM clients WHERE id_client = $idClient";
    $row = mysqli_fetch_assoc(mysqli_query($link, $sql));
    return [
        'eau'  => $row ? (float) $row['index_initial_eau']  : 0.0,
        'elec' => $row ? (float) $row['index_initial_elec'] : 0.0,
    ];
}
