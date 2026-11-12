<?php

require_once ROOT . 'BD/Parametre.php';
require_once ROOT . 'BD/Journal.php';
require_once ROOT . 'Traitement/Auth.php';

function Parametre_index($param = null)
{
    exiger_role('FOURNISSEUR');
    $parametres = bd_parametre_lister();
    $grilleTarifs = bd_parametre_tarifs_lister();
    $tarifsParActivite = [];
    foreach ($grilleTarifs as $ligne) {
        $idType = (int) $ligne['id_type_activite'];
        $tarifsParActivite[$idType]['libelle'] = $ligne['libelle'];
        $tarifsParActivite[$idType]['code'] = $ligne['code'];
        $tarifsParActivite[$idType]['grille'][$ligne['type_fluide']][(int) $ligne['tranche_num']] = $ligne;
    }
    $message = $_SESSION['message_parametre'] ?? null;
    unset($_SESSION['message_parametre']);
    $erreur = $_SESSION['erreur_parametre'] ?? null;
    unset($_SESSION['erreur_parametre']);
    include ROOT . 'IHM/fournisseur/parametres.php';
}

function Parametre_enregistrer($param = null)
{
    exiger_role('FOURNISSEUR');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rediriger('Parametre/index');
    }

    if (!csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        $_SESSION['erreur_parametre'] = 'Le formulaire a expiré. Veuillez réessayer.';
        rediriger('Parametre/index');
    }

    $parametres = bd_parametre_lister();
    $soumis = $_POST['parametres'] ?? [];
    if (!is_array($soumis)) {
        $_SESSION['erreur_parametre'] = 'Les paramètres reçus sont invalides.';
        rediriger('Parametre/index');
    }

    $modifications = [];
    foreach ($parametres as $parametre) {
        $cle = $parametre['cle'];
        if (!array_key_exists($cle, $soumis) || !is_string($soumis[$cle])) {
            continue;
        }
        $ancienneValeur = (string) $parametre['valeur'];
        $valeur = trim($soumis[$cle]);
        $type = $parametre['type_donnee'];

        if ($type === 'INTEGER' && filter_var($valeur, FILTER_VALIDATE_INT) === false) {
            $_SESSION['erreur_parametre'] = 'La valeur de « ' . $parametre['libelle'] . ' » doit être un entier.';
            rediriger('Parametre/index');
        }
        if ($type === 'DECIMAL' && (!is_numeric($valeur) || !is_finite((float) $valeur))) {
            $_SESSION['erreur_parametre'] = 'La valeur de « ' . $parametre['libelle'] . ' » doit être un nombre.';
            rediriger('Parametre/index');
        }
        if ($type === 'BOOLEAN') {
            if (!in_array(strtolower($valeur), ['0', '1', 'true', 'false'], true)) {
                $_SESSION['erreur_parametre'] = 'La valeur de « ' . $parametre['libelle'] . ' » doit être vraie ou fausse.';
                rediriger('Parametre/index');
            }
            $valeur = in_array(strtolower($valeur), ['1', 'true'], true) ? '1' : '0';
        }

        if ($valeur !== $ancienneValeur) {
            $modifications[] = [$parametre, $ancienneValeur, $valeur];
        }
    }

    $link = Connexion();
    if (!mysqli_begin_transaction($link)) {
        throw new RuntimeException("Impossible de démarrer la mise à jour des paramètres : " . mysqli_error($link));
    }
    try {
        foreach ($modifications as [$parametre, $ancienneValeur, $valeur]) {
            bd_parametre_modifier($parametre['cle'], $valeur);
            bd_journal_enregistrer(
                (int) $_SESSION['id_utilisateur'],
                'MODIFICATION_PARAMETRE',
                'parametres',
                (int) $parametre['id_parametre'],
                'Modification du paramètre ' . $parametre['cle'],
                $ancienneValeur,
                $valeur
            );
        }
        if (!mysqli_commit($link)) {
            throw new RuntimeException("Impossible de valider la mise à jour des paramètres : " . mysqli_error($link));
        }
    } catch (RuntimeException $exception) {
        mysqli_rollback($link);
        $_SESSION['erreur_parametre'] = 'Impossible d’enregistrer les paramètres. Veuillez réessayer.';
        rediriger('Parametre/index');
    }

    $_SESSION['message_parametre'] = 'Les paramètres ont été enregistrés.';
    rediriger('Parametre/index');
}

function Parametre_enregistrerTarifs($param = null)
{
    exiger_role('FOURNISSEUR');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rediriger('Parametre/index');
    }
    if (!csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        $_SESSION['erreur_parametre'] = 'Le formulaire a expiré. Veuillez réessayer.';
        rediriger('Parametre/index');
    }

    $lignesExistantes = bd_parametre_tarifs_lister();
    $grille = $_POST['tarifs'] ?? [];
    if (!is_array($grille)) {
        $_SESSION['erreur_parametre'] = 'La grille tarifaire reçue est invalide.';
        rediriger('Parametre/index');
    }

    $activites = [];
    $tranches = [];
    foreach ($lignesExistantes as $ligne) {
        $idType = (int) $ligne['id_type_activite'];
        $activites[$idType] = true;
        $tranches[$ligne['type_fluide']][(int) $ligne['tranche_num']] = $ligne;
    }

    $modifications = [];
    foreach ($activites as $idType => $_) {
        if (!isset($grille[$idType]) || !is_array($grille[$idType])) {
            continue;
        }
        foreach (['EAU', 'ELECTRICITE'] as $fluide) {
            if (!isset($grille[$idType][$fluide]) || !is_array($grille[$idType][$fluide])) {
                continue;
            }
            foreach ($grille[$idType][$fluide] as $numeroTranche => $prixBrut) {
                if (!is_string($prixBrut) || trim($prixBrut) === '') {
                    continue;
                }
                $numeroTranche = filter_var($numeroTranche, FILTER_VALIDATE_INT);
                $prix = trim($prixBrut);
                if (!$numeroTranche || !isset($tranches[$fluide][$numeroTranche])
                    || !is_numeric($prix) || !is_finite((float) $prix) || (float) $prix < 0) {
                    $_SESSION['erreur_parametre'] = 'Veuillez saisir des tarifs positifs et valides pour chaque tranche.';
                    rediriger('Parametre/index');
                }
                $tranche = $tranches[$fluide][$numeroTranche];
                $ancienneValeur = $tranche['prix_unitaire_dh'];
                if ($ancienneValeur === null || (float) $ancienneValeur !== (float) $prix) {
                    $modifications[] = [$idType, $fluide, $numeroTranche, $tranche, (string) $prix, $ancienneValeur];
                }
            }
        }
    }

    $link = Connexion();
    if (!mysqli_begin_transaction($link)) {
        throw new RuntimeException("Impossible de démarrer la mise à jour des tarifs : " . mysqli_error($link));
    }
    try {
        foreach ($modifications as [$idType, $fluide, $numeroTranche, $tranche, $prix, $ancienneValeur]) {
            bd_parametre_tarif_enregistrer(
                (int) $idType,
                $fluide,
                (int) $numeroTranche,
                (int) $tranche['borne_min'],
                $tranche['borne_max'] === null ? null : (int) $tranche['borne_max'],
                (float) $prix,
                $tranche['description']
            );
            bd_journal_enregistrer(
                (int) $_SESSION['id_utilisateur'],
                'MODIFICATION_TARIF',
                'tarifs_tranches',
                null,
                'Modification du tarif ' . $fluide . ', activité ' . $tranche['code'] . ', tranche ' . $numeroTranche,
                $ancienneValeur === null ? null : (string) $ancienneValeur,
                $prix
            );
        }
        if (!mysqli_commit($link)) {
            throw new RuntimeException("Impossible de valider la mise à jour des tarifs : " . mysqli_error($link));
        }
    } catch (RuntimeException $exception) {
        mysqli_rollback($link);
        $_SESSION['erreur_parametre'] = 'Impossible d’enregistrer les tarifs. Veuillez réessayer.';
        rediriger('Parametre/index');
    }

    $_SESSION['message_parametre'] = 'Les tarifs ont été enregistrés.';
    rediriger('Parametre/index');
}
