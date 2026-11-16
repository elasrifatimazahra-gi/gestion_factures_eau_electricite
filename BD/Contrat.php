<?php
require_once __DIR__ . '/Connexion.php';

function bd_contrat_lister($idClient = null)
{
    $link = Connexion();
    $sql = "SELECT c.id_contrat, c.numero_contrat, c.id_client, c.id_quartier,
                   c.numero_compteur_eau, c.numero_compteur_elec,
                   c.index_initial_eau, c.index_initial_elec, c.statut,
                   cl.numero_client, CONCAT(cl.nom, ' ', cl.prenom) AS client_nom,
                   q.nom AS quartier_nom
            FROM contrats c
            LEFT JOIN clients cl ON cl.id_client = c.id_client
            LEFT JOIN quartiers q ON q.id_quartier = c.id_quartier";

    if ($idClient !== null) {
        $idClient = (int) $idClient;
        $sql .= " WHERE c.id_client = $idClient";
    }

    $sql .= " ORDER BY c.numero_contrat ASC";
    $resultat = mysqli_query($link, $sql);
    if (!$resultat) {
        throw new RuntimeException("Impossible de récupérer les contrats : " . mysqli_error($link));
    }

    return mysqli_fetch_all($resultat, MYSQLI_ASSOC);
}

function bd_contrat_trouver_par_id($idContrat)
{
    $link = Connexion();
    $idContrat = (int) $idContrat;
    $sql = "SELECT c.*, cl.numero_client, CONCAT(cl.nom, ' ', cl.prenom) AS client_nom,
                   q.nom AS quartier_nom
            FROM contrats c
            LEFT JOIN clients cl ON cl.id_client = c.id_client
            LEFT JOIN quartiers q ON q.id_quartier = c.id_quartier
            WHERE c.id_contrat = $idContrat LIMIT 1";
    $resultat = mysqli_query($link, $sql);
    if (!$resultat) {
        throw new RuntimeException("Impossible de trouver le contrat : " . mysqli_error($link));
    }
    return mysqli_fetch_assoc($resultat);
}

function bd_contrat_numero_existe($numeroContrat)
{
    $link = Connexion();
    $numeroContrat = trim((string) $numeroContrat);
    if ($numeroContrat === '') {
        return false;
    }

    $numeroContrat = mysqli_real_escape_string($link, $numeroContrat);
    $sql = "SELECT id_contrat FROM contrats WHERE numero_contrat = '$numeroContrat' LIMIT 1";
    $resultat = mysqli_query($link, $sql);
    if (!$resultat) {
        throw new RuntimeException("Impossible de vérifier le contrat : " . mysqli_error($link));
    }

    return mysqli_num_rows($resultat) > 0;
}

function bd_contrat_ajouter($idClient, $idQuartier, $numeroContrat, $numeroCompteurEau, $numeroCompteurElec, $indexInitialEau, $indexInitialElec, $statut = 'ACTIF')
{
    $link = Connexion();
    $idClient = (int) $idClient;
    $idQuartier = $idQuartier !== null ? (int) $idQuartier : null;
    $numeroContrat = mysqli_real_escape_string($link, trim((string) $numeroContrat));
    $numeroCompteurEau = mysqli_real_escape_string($link, trim((string) $numeroCompteurEau));
    $numeroCompteurElec = mysqli_real_escape_string($link, trim((string) $numeroCompteurElec));
    $indexInitialEau = (int) $indexInitialEau;
    $indexInitialElec = (int) $indexInitialElec;
    $statut = in_array($statut, ['ACTIF', 'SUSPENDU', 'RESILIE'], true) ? $statut : 'ACTIF';

    $sql = "INSERT INTO contrats (numero_contrat, id_client, id_quartier, numero_compteur_eau, numero_compteur_elec, index_initial_eau, index_initial_elec, statut)
            VALUES ('$numeroContrat', $idClient, " . ($idQuartier === null ? 'NULL' : $idQuartier) . ", '$numeroCompteurEau', '$numeroCompteurElec', $indexInitialEau, $indexInitialElec, '$statut')";

    $resultat = mysqli_query($link, $sql);
    if (!$resultat) {
        throw new RuntimeException("Impossible d’ajouter le contrat : " . mysqli_error($link));
    }

    return (int) mysqli_insert_id($link);
}

function bd_contrat_modifier($idContrat, $idClient, $idQuartier, $numeroContrat, $numeroCompteurEau, $numeroCompteurElec, $indexInitialEau, $indexInitialElec, $statut = 'ACTIF')
{
    $link = Connexion();
    $idContrat = (int) $idContrat;
    $idClient = (int) $idClient;
    $idQuartier = $idQuartier !== null ? (int) $idQuartier : null;
    $numeroContrat = mysqli_real_escape_string($link, trim((string) $numeroContrat));
    $numeroCompteurEau = mysqli_real_escape_string($link, trim((string) $numeroCompteurEau));
    $numeroCompteurElec = mysqli_real_escape_string($link, trim((string) $numeroCompteurElec));
    $indexInitialEau = (int) $indexInitialEau;
    $indexInitialElec = (int) $indexInitialElec;
    $statut = in_array($statut, ['ACTIF', 'SUSPENDU', 'RESILIE'], true) ? $statut : 'ACTIF';

    $sql = "UPDATE contrats
            SET numero_contrat = '$numeroContrat',
                id_client = $idClient,
                id_quartier = " . ($idQuartier === null ? 'NULL' : $idQuartier) . ",
                numero_compteur_eau = '$numeroCompteurEau',
                numero_compteur_elec = '$numeroCompteurElec',
                index_initial_eau = $indexInitialEau,
                index_initial_elec = $indexInitialElec,
                statut = '$statut'
            WHERE id_contrat = $idContrat";

    $resultat = mysqli_query($link, $sql);
    if (!$resultat) {
        throw new RuntimeException("Impossible de modifier le contrat : " . mysqli_error($link));
    }

    return $resultat;
}
