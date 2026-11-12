<?php

require_once ROOT . 'BD/Journal.php';
require_once ROOT . 'Traitement/Auth.php';

function Journal_index($param = null)
{
    exiger_role('FOURNISSEUR');
    $filtres = [];
    foreach (['action_type', 'date_debut', 'date_fin'] as $champ) {
        $valeur = $_GET[$champ] ?? '';
        $filtres[$champ] = is_string($valeur) ? trim($valeur) : '';
    }
    $erreur = null;
    $actions = bd_journal_types();
    foreach (['date_debut', 'date_fin'] as $champ) {
        if ($filtres[$champ] !== '') {
            $date = DateTime::createFromFormat('!Y-m-d', $filtres[$champ]);
            if (!$date || $date->format('Y-m-d') !== $filtres[$champ]) {
                $erreur = 'Veuillez saisir des dates valides.';
                $filtres[$champ] = '';
            }
        }
    }
    if ($filtres['date_debut'] !== '' && $filtres['date_fin'] !== ''
        && $filtres['date_debut'] > $filtres['date_fin']) {
        $erreur = 'La date de début doit précéder la date de fin.';
        $filtres['date_debut'] = '';
        $filtres['date_fin'] = '';
    }
    if ($filtres['action_type'] !== '' && !in_array($filtres['action_type'], $actions, true)) {
        $erreur = 'Le type d’action sélectionné est invalide.';
        $filtres['action_type'] = '';
    }

    $journal = bd_journal_lister($filtres);
    include ROOT . 'IHM/fournisseur/journal.php';
}
