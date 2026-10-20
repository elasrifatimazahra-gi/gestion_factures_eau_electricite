<?php
require_once __DIR__ . "/Connexion.php";

function bd_facture_conso($idConso)
{
    $link = Connexion();
    $idConso = (int) $idConso;
    $res = mysqli_query($link, "SELECT * FROM consommations WHERE id_conso = $idConso");
    return $res ? mysqli_fetch_assoc($res) : null;
}

function bd_facture_par_conso($idConso)
{
    $link = Connexion();
    $idConso = (int) $idConso;
    $res = mysqli_query($link, "SELECT id_facture FROM factures
                                WHERE id_conso = $idConso AND type_facture = 'MENSUELLE' LIMIT 1");
    return $res ? mysqli_fetch_assoc($res) : null;
}

function bd_facture_numero_existe($numero)
{
    $link = Connexion();
    $numero = mysqli_real_escape_string($link, $numero);
    $res = mysqli_query($link, "SELECT 1 FROM factures WHERE numero_facture = '$numero' LIMIT 1");
    return $res && mysqli_num_rows($res) > 0;
}

/** Ex. : FAC-2026-09-000101 (année, mois, fin du numéro client) */
function bd_facture_numero($idClient, $annee, $mois)
{
    $link = Connexion();
    $idClient = (int) $idClient;
    $res = mysqli_query($link, "SELECT numero_client FROM clients WHERE id_client = $idClient");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $suffixe = $row ? substr(strrchr('-' . $row['numero_client'], '-'), 1) : sprintf('%06d', $idClient);

    $base = sprintf('FAC-%04d-%02d-%s', $annee, $mois, $suffixe);
    $numero = $base;
    $i = 2;
    while (bd_facture_numero_existe($numero)) {
        $numero = $base . '-' . $i;
        $i++;
    }
    return $numero;
}

/** Insère une facture. $d = ['colonne' => valeur, ...]. Retourne l'id ou false. */
function bd_facture_creer($d)
{
    $link = Connexion();
    $cols = [];
    $vals = [];
    foreach ($d as $col => $v) {
        $cols[] = "`$col`";
        $vals[] = ($v === null) ? 'NULL' : "'" . mysqli_real_escape_string($link, (string) $v) . "'";
    }
    $sql = "INSERT INTO factures (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
    try {
        if (!mysqli_query($link, $sql)) {
            return false;
        }
    } catch (mysqli_sql_exception $e) {
        return false;
    }
    return mysqli_insert_id($link);
}

function bd_facture_du_client($idClient)
{
    $link = Connexion();
    $idClient = (int) $idClient;
    $res = mysqli_query($link, "SELECT * FROM factures WHERE id_client = $idClient
                                ORDER BY periode_annee DESC, periode_mois DESC, id_facture DESC");
    return $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
}

/** Une facture, seulement si elle appartient bien à ce client. */
function bd_facture_detail($idFacture, $idClient)
{
    $link = Connexion();
    $idFacture = (int) $idFacture;
    $idClient = (int) $idClient;
    $res = mysqli_query($link, "SELECT * FROM factures
                                WHERE id_facture = $idFacture AND id_client = $idClient");
    return $res ? mysqli_fetch_assoc($res) : null;
}

/** Nombre de factures non payées d'une période antérieure à (annee, mois). */
function bd_facture_nb_impayees_anterieures($idClient, $annee, $mois)
{
    $link = Connexion();
    $idClient = (int) $idClient;
    $annee = (int) $annee;
    $mois = (int) $mois;
    $res = mysqli_query($link, "SELECT COUNT(*) AS nb FROM factures
                                WHERE id_client = $idClient
                                  AND statut IN ('IMPAYEE', 'EN_RETARD')
                                  AND (periode_annee < $annee OR (periode_annee = $annee AND periode_mois < $mois))");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row ? (int) $row['nb'] : 0;
}

/** Factures dont l'échéance est dépassée et sans pénalité déjà appliquée. */
function bd_facture_a_penaliser()
{
    $link = Connexion();
    $res = mysqli_query($link, "SELECT id_facture, montant_ttc FROM factures
                                WHERE statut = 'IMPAYEE'
                                  AND penalite_appliquee = 0
                                  AND date_echeance < CURDATE()");
    return $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
}

function bd_facture_appliquer_penalite($idFacture, $penalite)
{
    $link = Connexion();
    $idFacture = (int) $idFacture;
    $penalite = (float) $penalite;
    return mysqli_query($link, "UPDATE factures
                                SET penalite_retard = $penalite,
                                    penalite_appliquee = 1,
                                    montant_total_a_payer = montant_total_a_payer + $penalite,
                                    reste_a_payer = reste_a_payer + $penalite,
                                    statut = 'EN_RETARD'
                                WHERE id_facture = $idFacture");
}

/** Identité et compteurs du client (barre latérale et en-tête de facture). */
function bd_facture_infos_client($idClient)
{
    $link = Connexion();
    $idClient = (int) $idClient;
    $res = mysqli_query($link, "SELECT nom, prenom, numero_client, adresse, numero_compteur_eau, numero_compteur_elec
                                FROM clients WHERE id_client = $idClient");
    return $res ? mysqli_fetch_assoc($res) : null;
}