<?php

require_once ROOT . 'BD/Facture.php';
require_once ROOT . 'Traitement/Auth.php';

function facture_pdf_client_connecte()
{
    exiger_role('CLIENT');

    $idClient = bd_utilisateur_id_client((int) $_SESSION['id_utilisateur']);
    if ($idClient === null) {
        http_response_code(403);
        include ROOT . 'IHM/auth/acces_refuse.php';
        exit;
    }

    return (int) $idClient;
}

function facture_pdf_trouver($idFacture)
{
    $idClient = facture_pdf_client_connecte();
    $idFacture = filter_var($idFacture, FILTER_VALIDATE_INT);
    if (!$idFacture || $idFacture < 1) {
        return null;
    }

    return bd_facture_detail($idFacture, $idClient);
}
