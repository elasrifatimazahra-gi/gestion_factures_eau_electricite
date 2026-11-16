<?php
require_once ROOT . 'BD/Contrat.php';
require_once ROOT . 'BD/Client.php';
require_once ROOT . 'BD/Quartier.php';
require_once ROOT . 'Traitement/Auth.php';

function Contrat_index($param = null)
{
    exiger_role('FOURNISSEUR');

    $message = $_SESSION['msg_succes'] ?? null;
    unset($_SESSION['msg_succes']);
    $erreur = $_SESSION['msg_erreur'] ?? null;
    unset($_SESSION['msg_erreur']);

    $contrats = bd_contrat_lister();
    include ROOT . 'IHM/fournisseur/contrats.php';
}

function Contrat_ajouter($idClient = null)
{
    exiger_role('FOURNISSEUR');

    $clients = mysqli_fetch_all(bd_client_lister(), MYSQLI_ASSOC);
    $quartiers = bd_quartier_lister();
    $idClient = $idClient !== null ? (int) $idClient : null;
    $contratActuel = [
        'id_client' => $idClient,
        'id_quartier' => null,
        'numero_contrat' => '',
        'numero_compteur_eau' => '',
        'numero_compteur_elec' => '',
        'index_initial_eau' => 0,
        'index_initial_elec' => 0,
        'statut' => 'ACTIF',
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $idClient = filter_var($_POST['id_client'] ?? null, FILTER_VALIDATE_INT);
        $idQuartier = filter_var($_POST['id_quartier'] ?? null, FILTER_VALIDATE_INT);
        $numeroContrat = trim((string) ($_POST['numero_contrat'] ?? ''));
        $numeroCompteurEau = trim((string) ($_POST['numero_compteur_eau'] ?? ''));
        $numeroCompteurElec = trim((string) ($_POST['numero_compteur_elec'] ?? ''));
        $indexInitialEau = filter_var($_POST['index_initial_eau'] ?? 0, FILTER_VALIDATE_INT);
        $indexInitialElec = filter_var($_POST['index_initial_elec'] ?? 0, FILTER_VALIDATE_INT);
        $statut = in_array($_POST['statut'] ?? 'ACTIF', ['ACTIF', 'SUSPENDU', 'RESILIE'], true) ? $_POST['statut'] : 'ACTIF';

        if ($idClient === false || $idClient < 1 || $numeroContrat === '' || $numeroCompteurEau === '' || $numeroCompteurElec === '') {
            $erreur = 'Veuillez renseigner un client, un numéro de contrat et les numéros de compteurs.';
        } elseif (bd_contrat_numero_existe($numeroContrat)) {
            $erreur = 'Ce numéro de contrat existe déjà.';
        } else {
            try {
                bd_contrat_ajouter(
                    (int) $idClient,
                    $idQuartier !== false && $idQuartier !== null && $idQuartier > 0 ? (int) $idQuartier : null,
                    $numeroContrat,
                    $numeroCompteurEau,
                    $numeroCompteurElec,
                    $indexInitialEau !== false ? (int) $indexInitialEau : 0,
                    $indexInitialElec !== false ? (int) $indexInitialElec : 0,
                    $statut
                );

                $_SESSION['msg_succes'] = 'Le contrat a été ajouté avec succès.';
                header('Location: ' . BASE_URL . 'Contrat/index');
                exit;
            } catch (RuntimeException $exception) {
                $erreur = $exception->getMessage();
            }
        }

        $contratActuel = [
            'id_client' => $idClient,
            'id_quartier' => $idQuartier !== false && $idQuartier !== null && $idQuartier > 0 ? (int) $idQuartier : null,
            'numero_contrat' => $numeroContrat,
            'numero_compteur_eau' => $numeroCompteurEau,
            'numero_compteur_elec' => $numeroCompteurElec,
            'index_initial_eau' => $indexInitialEau !== false ? (int) $indexInitialEau : 0,
            'index_initial_elec' => $indexInitialElec !== false ? (int) $indexInitialElec : 0,
            'statut' => $statut,
        ];
    }

    $action_formulaire = 'Contrat/ajouter' . ($idClient !== null ? '/' . $idClient : '');
    $bouton_formulaire = 'Ajouter le contrat';
    include ROOT . 'IHM/fournisseur/contrat_form.php';
}

function Contrat_modifier($idContrat = null)
{
    exiger_role('FOURNISSEUR');

    if (!$idContrat) {
        $_SESSION['msg_erreur'] = 'Contrat introuvable.';
        rediriger('Contrat/index');
    }

    $contratActuel = bd_contrat_trouver_par_id((int) $idContrat);
    if (!$contratActuel) {
        $_SESSION['msg_erreur'] = 'Contrat introuvable.';
        rediriger('Contrat/index');
    }

    $clients = mysqli_fetch_all(bd_client_lister(), MYSQLI_ASSOC);
    $quartiers = bd_quartier_lister();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $idClient = filter_var($_POST['id_client'] ?? null, FILTER_VALIDATE_INT);
        $idQuartier = filter_var($_POST['id_quartier'] ?? null, FILTER_VALIDATE_INT);
        $numeroContrat = trim((string) ($_POST['numero_contrat'] ?? ''));
        $numeroCompteurEau = trim((string) ($_POST['numero_compteur_eau'] ?? ''));
        $numeroCompteurElec = trim((string) ($_POST['numero_compteur_elec'] ?? ''));
        $indexInitialEau = filter_var($_POST['index_initial_eau'] ?? 0, FILTER_VALIDATE_INT);
        $indexInitialElec = filter_var($_POST['index_initial_elec'] ?? 0, FILTER_VALIDATE_INT);
        $statut = in_array($_POST['statut'] ?? 'ACTIF', ['ACTIF', 'SUSPENDU', 'RESILIE'], true) ? $_POST['statut'] : 'ACTIF';

        if ($idClient === false || $idClient < 1 || $numeroContrat === '' || $numeroCompteurEau === '' || $numeroCompteurElec === '') {
            $erreur = 'Veuillez renseigner un client, un numéro et les compteurs.';
        } else {
            try {
                bd_contrat_modifier(
                    (int) $idContrat,
                    (int) $idClient,
                    $idQuartier !== false && $idQuartier !== null && $idQuartier > 0 ? (int) $idQuartier : null,
                    $numeroContrat,
                    $numeroCompteurEau,
                    $numeroCompteurElec,
                    $indexInitialEau !== false ? (int) $indexInitialEau : 0,
                    $indexInitialElec !== false ? (int) $indexInitialElec : 0,
                    $statut
                );

                $_SESSION['msg_succes'] = 'Le contrat a été modifié avec succès.';
                header('Location: ' . BASE_URL . 'Contrat/index');
                exit;
            } catch (RuntimeException $exception) {
                $erreur = $exception->getMessage();
            }
        }
    }

    $action_formulaire = 'Contrat/modifier/' . (int) $idContrat;
    $bouton_formulaire = 'Modifier le contrat';
    include ROOT . 'IHM/fournisseur/contrat_form.php';
}

function Contrat_choisir($param = null)
{
    exiger_role('CLIENT');
    $idClient = (int) ($_SESSION['id_client'] ?? 0);
    $contrats = bd_contrat_lister($idClient);

    if (!$contrats) {
        unset($_SESSION['id_contrat']);
        rediriger('Auth/accueil');
    }
    if (count($contrats) === 1) {
        $_SESSION['id_contrat'] = (int) $contrats[0]['id_contrat'];
        rediriger('Auth/accueil');
    }
    if (auth_contrat_courant($idClient, $contrats)) {
        rediriger('Auth/accueil');
    }

    $erreur = $_SESSION['erreur_contrat'] ?? '';
    unset($_SESSION['erreur_contrat']);
    include ROOT . 'IHM/client/choisir_contrat.php';
}

function Contrat_selectionner($param = null)
{
    exiger_role('CLIENT');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        $_SESSION['erreur_contrat'] = 'Le formulaire a expiré. Veuillez choisir votre contrat.';
        rediriger('Contrat/choisir');
    }

    $idClient = (int) ($_SESSION['id_client'] ?? 0);
    $idContrat = filter_var($_POST['id_contrat'] ?? null, FILTER_VALIDATE_INT);
    $contrat = $idContrat && $idContrat > 0 ? bd_contrat_trouver_par_id((int) $idContrat) : null;
    if (!$contrat || (int) $contrat['id_client'] !== $idClient) {
        $_SESSION['erreur_contrat'] = 'Ce contrat ne vous appartient pas.';
        rediriger('Contrat/choisir');
    }

    $_SESSION['id_contrat'] = (int) $idContrat;
    $retour = $_POST['retour'] ?? 'Auth/accueil';
    if (!is_string($retour) || !preg_match(
        '#^(Auth/accueil|Consommation/declaration|Facture/liste|Facture/detail/[0-9]+|Reclamation/liste|Notification/liste)$#',
        $retour
    )) {
        $retour = 'Auth/accueil';
    }
    rediriger($retour);
}
