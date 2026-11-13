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

function bd_facture_numero($idClient, $annee, $mois, $idContrat = null)
{
    $link = Connexion();
    $idClient = (int) $idClient;
    $res = mysqli_query($link, "SELECT numero_client FROM clients WHERE id_client = $idClient");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $suffixe = $row ? substr(strrchr('-' . $row['numero_client'], '-'), 1) : sprintf('%06d', $idClient);

    $base = sprintf('FAC-%04d-%02d-%s', $annee, $mois, $suffixe);
    $numero = $base;
    if ($idContrat && bd_facture_numero_existe($numero)) {
        $numero = $base . '-C' . (int) $idContrat;
    }
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

/**
 * Factures d'UN contrat, avec son quartier et son type d'activité.
 * Le contrat doit appartenir au client (sinon : liste vide).
 */
function bd_facture_du_contrat($idContrat, $idClient)
{
    $link = Connexion();
    $idContrat = (int) $idContrat;
    $idClient = (int) $idClient;
    $res = mysqli_query($link, "SELECT f.*, ct.numero_contrat, q.nom AS quartier, ta.libelle AS type_activite
                                FROM factures f
                                JOIN contrats ct ON ct.id_contrat = f.id_contrat
                                LEFT JOIN quartiers q ON q.id_quartier = ct.id_quartier
                                LEFT JOIN types_activite ta ON ta.id_type_activite = q.id_type_activite
                                WHERE f.id_contrat = $idContrat AND ct.id_client = $idClient
                                ORDER BY f.periode_annee DESC, f.periode_mois DESC, f.id_facture DESC");
    return $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
}

/**
 * Une facture, seulement si elle appartient bien à ce client (et, si $idContrat
 * est donné, à ce contrat). Renvoie aussi le contrat, le quartier et le type d'activité.
 */
function bd_facture_detail($idFacture, $idClient, $idContrat = null)
{
    $link = Connexion();
    $idFacture = (int) $idFacture;
    $idClient = (int) $idClient;
    $filtreContrat = $idContrat ? ' AND f.id_contrat = ' . (int) $idContrat : '';
    $res = mysqli_query($link, "SELECT f.*, ct.numero_contrat, ct.numero_compteur_eau, ct.numero_compteur_elec,
                                       q.nom AS quartier, ta.libelle AS type_activite
                                FROM factures f
                                LEFT JOIN contrats ct ON ct.id_contrat = f.id_contrat
                                LEFT JOIN quartiers q ON q.id_quartier = ct.id_quartier
                                LEFT JOIN types_activite ta ON ta.id_type_activite = q.id_type_activite
                                WHERE f.id_facture = $idFacture AND f.id_client = $idClient$filtreContrat");
    return $res ? mysqli_fetch_assoc($res) : null;
}

/** Nombre de factures non payées d'une période antérieure à (annee, mois). */
/**
 * Nombre de factures non payées d'une période antérieure à (annee, mois).
 * Avec $idContrat : on ne regarde que ce contrat (paiement chronologique par contrat).
 */
function bd_facture_nb_impayees_anterieures($idClient, $annee, $mois, $idContrat = null)
{
    $link = Connexion();
    $idClient = (int) $idClient;
    $annee = (int) $annee;
    $mois = (int) $mois;
    $filtreContrat = $idContrat ? ' AND id_contrat = ' . (int) $idContrat : '';
    $res = mysqli_query($link, "SELECT COUNT(*) AS nb FROM factures
                                WHERE id_client = $idClient$filtreContrat
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

/** Identité du client + infos du contrat choisi (compteurs, quartier, type d'activité). */
function bd_facture_infos_contrat($idContrat, $idClient)
{
    $link = Connexion();
    $idContrat = (int) $idContrat;
    $idClient = (int) $idClient;
    $res = mysqli_query($link, "SELECT cl.nom, cl.prenom, cl.numero_client, cl.adresse,
                                       ct.numero_contrat, ct.numero_compteur_eau, ct.numero_compteur_elec,
                                       q.nom AS quartier, ta.libelle AS type_activite
                                FROM contrats ct
                                JOIN clients cl ON cl.id_client = ct.id_client
                                LEFT JOIN quartiers q ON q.id_quartier = ct.id_quartier
                                LEFT JOIN types_activite ta ON ta.id_type_activite = q.id_type_activite
                                WHERE ct.id_contrat = $idContrat AND ct.id_client = $idClient");
    return $res ? mysqli_fetch_assoc($res) : null;
}

// Premier contrat d'un client 
function bd_facture_premier_contrat($idClient)
{
    $link = Connexion();
    $idClient = (int) $idClient;
    $res = mysqli_query($link, "SELECT id_contrat FROM contrats WHERE id_client = $idClient ORDER BY id_contrat LIMIT 1");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row ? (int) $row['id_contrat'] : null;
}