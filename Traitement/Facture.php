<?php
// Contrôleur : pages "Mes factures" (URL : Facture/liste et Facture/detail/ID)

require_once ROOT . 'BD/Facture.php';
require_once ROOT . 'BD/Utilisateur.php';
require_once ROOT . 'Traitement/Auth.php';
require_once ROOT . 'Traitement/Facturation.php';

function facture_client_connecte()
{
    $idClient = bd_utilisateur_id_client($_SESSION['id_utilisateur']);
    if ($idClient === null) {
        http_response_code(403);
        include ROOT . 'IHM/auth/acces_refuse.php';
        exit;
    }
    return $idClient;
}

function facture_nom_mois($mois)
{
    $noms = [1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
             'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    return $noms[(int) $mois] ?? '';
}

function facture_libelle_statut($statut)
{
    $libelles = ['IMPAYEE' => 'Impayée', 'EN_RETARD' => 'En retard',
                 'PAYEE' => 'Payée', 'ANNULEE' => 'Annulée'];
    return $libelles[$statut] ?? $statut;
}

/** [classe CSS, libellé] du badge de statut, comme sur la maquette. */
function facture_badge($f)
{
    if ($f['statut'] === 'PAYEE') {
        return ['payee', 'Payée'];
    }
    if ($f['statut'] === 'ANNULEE') {
        return ['annulee', 'Annulée'];
    }
    if ($f['statut'] === 'EN_RETARD') {
        return ['retard', 'En retard'];
    }
    if ($f['type_facture'] === 'ESTIMEE') {
        return ['estimee', 'Estimée'];
    }
    return ['impayee', 'Impayée'];
}

/** Groupes de filtre de la ligne : toutes, impayees, payees, estimees. */
function facture_groupes($f)
{
    $g = ['toutes'];
    if (in_array($f['statut'], ['IMPAYEE', 'EN_RETARD'], true)) {
        $g[] = 'impayees';
    }
    if ($f['statut'] === 'PAYEE') {
        $g[] = 'payees';
    }
    if ($f['type_facture'] === 'ESTIMEE') {
        $g[] = 'estimees';
    }
    return implode(' ', $g);
}

function facture_type_services($f)
{
    $eau = (int) $f['conso_eau'] > 0;
    $elec = (int) $f['conso_elec'] > 0;
    if ($eau && !$elec) {
        return 'Eau';
    }
    if ($elec && !$eau) {
        return 'Électricité';
    }
    return 'Eau + Électricité';
}

/** 2026-11-15 -> "15 nov. 2026" */
function facture_date_courte($date)
{
    $mois = [1 => 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
             'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $t = strtotime($date);
    return date('j', $t) . ' ' . $mois[(int) date('n', $t)] . ' ' . date('Y', $t);
}

function Facture_index($param = null)
{
    Facture_liste($param);                    // /Facture sans action = la liste
}

function Facture_liste($param = null)
{
    exiger_role('CLIENT');
    $idClient = facture_client_connecte();

    appliquerPenalites();                     // met à jour les retards avant l'affichage
    $factures = bd_facture_du_client($idClient);
    $client = bd_facture_infos_client($idClient);

    include ROOT . 'IHM/client/factures.php';
}

function Facture_detail($param = null)
{
    exiger_role('CLIENT');
    $idClient = facture_client_connecte();
    $idFacture = is_array($param) ? (int) ($param[0] ?? 0) : (int) $param;

    appliquerPenalites();
    $facture = bd_facture_detail($idFacture, $idClient);
    if (!$facture) {
        http_response_code(404);
        include ROOT . 'IHM/404.php';
        exit;
    }

    // Des factures plus anciennes sont-elles encore à payer ?
    $anterieuresImpayees = 0;
    if (in_array($facture['statut'], ['IMPAYEE', 'EN_RETARD'], true)) {
        $anterieuresImpayees = bd_facture_nb_impayees_anterieures(
            $idClient, $facture['periode_annee'], $facture['periode_mois']
        );
    }

    include ROOT . 'IHM/client/facture_detail.php';
}