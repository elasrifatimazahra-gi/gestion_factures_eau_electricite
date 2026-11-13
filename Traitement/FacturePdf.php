<?php

require_once ROOT . 'BD/Facture.php';
require_once ROOT . 'Traitement/Auth.php';
require_once ROOT . 'BD/Utilisateur.php';
require_once ROOT . 'Traitement/Facture.php';

/**
 * Ouvre la facture ID en mode impression (URL : FacturePdf/telecharger/ID).
 * La page de détail s'affiche sans boutons, puis la fenêtre
 * « Imprimer » s'ouvre : le client choisit « Enregistrer au format PDF ».
 * Facture_detail vérifie déjà que la facture appartient au client connecté.
 */
function FacturePdf_telecharger($param = null)
{
    $GLOBALS['impressionAuto'] = true;
    Facture_detail($param);
}