<?php
// Contrôleur de la saisie des consommations, appelé par index.php :
//   Consommation/declaration -> Consommation_declaration()
//   Consommation/enregistrer -> Consommation_enregistrer()

require_once ROOT . 'BD/Consommation.php';
require_once ROOT . 'BD/Utilisateur.php';
require_once ROOT . 'BD/Facture.php';
require_once ROOT . 'BD/Parametre.php';
require_once ROOT . 'BD/Anomalie.php';
require_once ROOT . 'BD/Agent.php';
require_once ROOT . 'BD/Journal.php';
require_once ROOT . 'Traitement/Auth.php';
require_once ROOT . 'Traitement/Upload.php';
require_once ROOT . 'Traitement/Facturation.php';
require_once ROOT . 'Traitement/Anomalie.php';
require_once ROOT . 'Traitement/Controle.php';
require_once ROOT . 'Traitement/Import.php';

function consommation_mois_fr()
{
    return [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
}

function consommation_date_fr($date)
{
    $mois = consommation_mois_fr();
    $t = strtotime($date);
    return date('j', $t) . ' ' . $mois[(int) date('n', $t)] . ' ' . date('Y', $t);
}

// Stocke le message en session et renvoie vers le formulaire
function consommation_retour($message, $cle = 'erreur', $idContrat = null)
{
    $_SESSION[$cle] = $message;
    $url = BASE_URL . 'Consommation/declaration';
    if ($idContrat !== null && (int) $idContrat > 0) {
        $url .= '?contrat=' . (int) $idContrat;
    }
    header('Location: ' . $url);
    exit;
}

// id_client du client connecté
function consommation_id_client()
{
    $idClient = bd_utilisateur_id_client($_SESSION['id_utilisateur']);
    if ($idClient === null) {
        http_response_code(403);
        include ROOT . 'IHM/auth/acces_refuse.php';
        exit;
    }
    return $idClient;
}

// Index précédents : dernière saisie, sinon index d'installation
function consommation_index_precedents($idClient)
{
    $derniere = derniereSaisie($idClient);
    if ($derniere) {
        return [
            'eau'  => (int) $derniere['index_eau_nouveau'],
            'elec' => (int) $derniere['index_elec_nouveau'],
        ];
    }
    $initial = indexInitial($idClient);
    return [
        'eau'  => (int) $initial['index_initial_eau'],
        'elec' => (int) $initial['index_initial_elec'],
    ];
}

// Détecte les anomalies de saisie (RG21)
// Retourne la liste des types, vide si la saisie est correcte
function consommation_detecter_anomalies($consoEau, $consoElec)
{
    $seuilEau  = (float) lireParametre('SEUIL_MAX_EAU_MENSUEL', 100);
    $seuilElec = (float) lireParametre('SEUIL_MAX_ELEC_MENSUEL', 2000);

    $anomalies = [];
    if ($consoEau < 0) {
        $anomalies[] = 'INDEX_INFERIEUR_EAU';
    }
    if ($consoElec < 0) {
        $anomalies[] = 'INDEX_INFERIEUR_ELEC';
    }
    if ($consoEau > $seuilEau) {
        $anomalies[] = 'SEUIL_DEPASSE_EAU';
    }
    if ($consoElec > $seuilElec) {
        $anomalies[] = 'SEUIL_DEPASSE_ELEC';
    }
    return $anomalies;
}

// Affiche la page de déclaration
function Consommation_declaration($param = null)
{
    exiger_role('CLIENT');
    $idClient = consommation_id_client();

    $contrats = bd_consommation_contrats_client($idClient);
    $contratCourant = auth_exiger_contrat($idClient);
    $idContrat = $contratCourant ? (int) $contratCourant['id_contrat'] : 0;
    $contratSelectionne = null;
    foreach ($contrats as $contrat) {
        if ((int) $contrat['id_contrat'] === $idContrat) {
            $contratSelectionne = $contrat;
            break;
        }
    }
    $prec = $contratSelectionne
        ? bd_consommation_index_precedent_contrat($idContrat, (int) date('Y'), (int) date('n'))
        : ['eau' => 0, 'elec' => 0];
    $eauPrecedent = $prec['eau'];
    $elecPrecedent = $prec['elec'];
    $dejaSaisi = $contratSelectionne
        ? bd_consommation_contrat_existe($idContrat, (int) date('Y'), (int) date('n'))
        : false;

    // Informations du client pour la carte utilisateur de la sidebar
    $client = bd_facture_infos_client($idClient) ?: ['nom' => '', 'prenom' => '', 'numero_client' => ''];

    // Date limite : dernier jour du mois en cours
    $mois = consommation_mois_fr();
    $dateLimite = date('t') . ' ' . $mois[(int) date('n')];

    // Date du dernier relevé enregistré
    $derniere = $contratSelectionne ? bd_consommation_derniere_par_contrat($idContrat) : null;
    $dernierReleve = $derniere ? consommation_date_fr($derniere['date_saisie']) : 'aucun';

    // Messages stockés par consommation_retour()
    $erreur = $_SESSION['erreur'] ?? '';
    $succes = $_SESSION['succes'] ?? '';
    unset($_SESSION['erreur'], $_SESSION['succes']);

    include ROOT . 'IHM/client/declaration.php';
}

// Traite le formulaire envoyé en POST
function Consommation_enregistrer($param = null)
{
    global $erreurUpload;
    exiger_role('CLIENT');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . BASE_URL . 'Consommation/declaration');
        exit;
    }
    if (!csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        consommation_retour('Le formulaire a expiré. Veuillez réessayer.');
    }

    $idClient = consommation_id_client();
    $idContrat = filter_var($_POST['id_contrat'] ?? null, FILTER_VALIDATE_INT);
    $contratCourant = auth_exiger_contrat($idClient);
    if (!$idContrat || !$contratCourant
        || (int) $idContrat !== (int) $contratCourant['id_contrat']
        || !bd_consommation_contrat_client((int) $idContrat, $idClient)) {
        consommation_retour('Veuillez choisir un contrat actif qui vous appartient.');
    }
    $annee = (int) date('Y');
    $mois = (int) date('n');
    $jour = (int) date('j');

    // Période (RG11) et une seule saisie par mois (RG12)
    if ($jour < 18) {
        consommation_retour("La saisie est ouverte du 18 à la fin du mois.");
    }
    if (bd_consommation_contrat_existe((int) $idContrat, $annee, $mois)) {
        consommation_retour("Vous avez déjà saisi vos index pour ce contrat ce mois-ci.", 'erreur', $idContrat);
    }

    // Index entiers positifs
    if (!ctype_digit($_POST['index_eau'] ?? '') || !ctype_digit($_POST['index_elec'] ?? '')) {
        consommation_retour("Les index doivent être des nombres entiers positifs.", 'erreur', $idContrat);
    }
    $eauNouv = (int) $_POST['index_eau'];
    $elecNouv = (int) $_POST['index_elec'];

    // Calcul côté serveur (RG15)
    $prec = bd_consommation_index_precedent_contrat((int) $idContrat, $annee, $mois);
    $eauPrec = $prec['eau'];
    $elecPrec = $prec['elec'];
    $consoEau = $eauNouv - $eauPrec;
    $consoElec = $elecNouv - $elecPrec;

    // Anomalies (RG21) : index inférieur ou seuil dépassé
    $anomalies = detecterAnomalies([
        'id_client' => $idClient,
        'id_contrat' => (int) $idContrat,
        'annee' => $annee,
        'mois' => $mois,
        'index_eau_precedent' => $eauPrec,
        'index_eau_nouveau' => $eauNouv,
        'index_elec_precedent' => $elecPrec,
        'index_elec_nouveau' => $elecNouv,
    ]);
    $estAnomalie = count($anomalies) > 0;

    // Photos (RG13)
    $photoEau = uploadPhoto($_FILES['photo_eau'] ?? []);
    if ($photoEau === false) {
        consommation_retour($erreurUpload, 'erreur', $idContrat);
    }
    $photoElec = uploadPhoto($_FILES['photo_elec'] ?? []);
    if ($photoElec === false) {
        consommation_retour($erreurUpload, 'erreur', $idContrat);
    }

    // Enregistrement de la consommation
    $statut = $estAnomalie ? 'EN_ATTENTE_CORRECTION' : 'VALIDE';
    $id = enregistrer(
        $idClient, $annee, $mois,
        $eauPrec, $eauNouv, $consoEau, $photoEau,
        $elecPrec, $elecNouv, $consoElec, $photoElec,
        'REELLE', $statut, (int) $idContrat
    );
    if ($id === false) {
        consommation_retour("Une erreur est survenue lors de l'enregistrement.", 'erreur', $idContrat);
    }

    // Enregistrement de chaque anomalie pour le fournisseur
    foreach ($anomalies as $anomalie) {
        if (!creerAnomalie(
            $id,
            $anomalie['type'],
            $anomalie['description'],
            $anomalie['valeur_saisie'],
            $anomalie['valeur_attendue']
        )) {
            consommation_retour("Votre saisie a été enregistrée, mais une anomalie n’a pas pu être créée.", 'erreur', $idContrat);
        }
    }

    // Suite : anomalie ou facturation (modules des autres membres)
    if ($estAnomalie) {
        consommation_retour("Votre saisie présente une anomalie et est en cours de vérification.", 'erreur', $idContrat);
    }
    if (function_exists('genererFacture')) {
        genererFacture($id);
    }
    consommation_retour("Votre consommation a bien été enregistrée.", 'succes', $idContrat);
}

function consommation_agent_retour($url, $message, $cle = 'erreur_agent_espace')
{
    $_SESSION[$cle] = $message;
    rediriger($url);
}

function consommation_agent_connecte()
{
    exiger_role('AGENT');
    $idAgent = bd_utilisateur_id_agent((int) $_SESSION['id_utilisateur']);
    if ($idAgent === null) {
        http_response_code(403);
        include ROOT . 'IHM/auth/acces_refuse.php';
        exit;
    }
    $_SESSION['id_agent'] = $idAgent;
    return $idAgent;
}

function Consommation_saisie_mensuelle($param = null)
{
    $idAgent = consommation_agent_connecte();
    $contrats = bd_agent_tournee_lister($idAgent);
    $idContratFiltre = filter_var($_GET['contrat'] ?? $param, FILTER_VALIDATE_INT);
    $idContrat = $idContratFiltre !== false ? (int) $idContratFiltre : 0;
    if ($idContrat > 0 && !in_array($idContrat, array_map('intval', array_column($contrats, 'id_contrat')), true)) {
        http_response_code(404);
        include ROOT . 'IHM/404.php';
        return;
    }
    $moisDemande = filter_var($_GET['mois'] ?? null, FILTER_VALIDATE_INT);
    $anneeDemande = filter_var($_GET['annee'] ?? null, FILTER_VALIDATE_INT);
    $moisSelectionne = $moisDemande !== false && $moisDemande >= 1 && $moisDemande <= 12
        ? $moisDemande : (int) date('n');
    $anneeSelectionnee = $anneeDemande !== false && $anneeDemande >= 2000 && $anneeDemande <= (int) date('Y')
        ? $anneeDemande : (int) date('Y');
    $contratSelectionne = null;
    foreach ($contrats as $contrat) {
        if ((int) $contrat['id_contrat'] === $idContrat) {
            $contratSelectionne = $contrat;
            break;
        }
    }
    $indexPrecedents = $idContrat > 0
        ? bd_consommation_index_precedent_contrat($idContrat, $anneeSelectionnee, $moisSelectionne)
        : null;
    $erreur = $_SESSION['erreur_agent_espace'] ?? '';
    $message = $_SESSION['message_agent_espace'] ?? '';
    unset($_SESSION['erreur_agent_espace'], $_SESSION['message_agent_espace']);
    include ROOT . 'IHM/agent/releve.php';
}

function Consommation_enregistrer_mensuelle($param = null)
{
    $idAgent = consommation_agent_connecte();
    $urlRetour = 'Consommation/saisie_mensuelle';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rediriger($urlRetour);
    }
    if (!csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        consommation_agent_retour($urlRetour, 'Le formulaire a expiré. Veuillez réessayer.');
    }

    $idContrat = filter_var($_POST['id_contrat'] ?? null, FILTER_VALIDATE_INT);
    $annee = filter_var($_POST['annee'] ?? null, FILTER_VALIDATE_INT);
    $mois = filter_var($_POST['mois'] ?? null, FILTER_VALIDATE_INT);
    $indexEau = filter_var($_POST['index_eau'] ?? null, FILTER_VALIDATE_INT);
    $indexElec = filter_var($_POST['index_elec'] ?? null, FILTER_VALIDATE_INT);
    $dateReleve = controle_date_normaliser($_POST['date_releve'] ?? null);

    if (!$idContrat || !$annee || $annee < 2000 || $annee > (int) date('Y')
        || !$mois || $mois < 1 || $mois > 12
        || ($annee === (int) date('Y') && $mois > (int) date('n'))
        || $indexEau === false || $indexEau < 0
        || $indexElec === false || $indexElec < 0
        || $dateReleve === null || $dateReleve > date('Y-m-d')) {
        consommation_agent_retour($urlRetour, 'Vérifiez le contrat, la période, la date et les index saisis.');
    }

    $contrats = bd_agent_tournee_lister($idAgent, (int) $idContrat);
    if (!$contrats) {
        consommation_agent_retour($urlRetour, 'Ce contrat ne fait pas partie de votre tournée.');
    }
    if (bd_consommation_contrat_existe((int) $idContrat, (int) $annee, (int) $mois)) {
        consommation_agent_retour($urlRetour, 'Un relevé existe déjà pour ce contrat et cette période.');
    }

    $contrat = $contrats[0];
    $precedents = bd_consommation_index_precedent_contrat((int) $idContrat, (int) $annee, (int) $mois);
    $consoEau = (int) $indexEau - $precedents['eau'];
    $consoElec = (int) $indexElec - $precedents['elec'];
    $anomalies = detecterAnomalies([
        'id_client' => (int) $contrat['id_client'],
        'id_contrat' => (int) $idContrat,
        'annee' => (int) $annee,
        'mois' => (int) $mois,
        'index_eau_precedent' => $precedents['eau'],
        'index_eau_nouveau' => (int) $indexEau,
        'index_elec_precedent' => $precedents['elec'],
        'index_elec_nouveau' => (int) $indexElec,
    ]);
    $statut = $anomalies ? 'EN_ATTENTE_CORRECTION' : 'VALIDE';

    $link = Connexion();
    if (!mysqli_begin_transaction($link)) {
        throw new RuntimeException("Impossible de démarrer l’enregistrement du relevé : " . mysqli_error($link));
    }
    try {
        $idConso = bd_consommation_creer_releve_agent([
            'id_client' => (int) $contrat['id_client'],
            'id_contrat' => (int) $idContrat,
            'id_agent' => (int) $idAgent,
            'annee' => (int) $annee,
            'mois' => (int) $mois,
            'index_eau_precedent' => $precedents['eau'],
            'index_eau_nouveau' => (int) $indexEau,
            'conso_eau' => $consoEau,
            'index_elec_precedent' => $precedents['elec'],
            'index_elec_nouveau' => (int) $indexElec,
            'conso_elec' => $consoElec,
            'date_saisie' => $dateReleve . ' 12:00:00',
            'statut' => $statut,
        ]);
        foreach ($anomalies as $anomalie) {
            if (!creerAnomalie(
                $idConso,
                $anomalie['type'],
                $anomalie['description'],
                $anomalie['valeur_saisie'],
                $anomalie['valeur_attendue']
            )) {
                throw new RuntimeException('Impossible d’enregistrer une anomalie du relevé.');
            }
        }
        if (!$anomalies && !genererFacture($idConso)) {
            throw new RuntimeException('La consommation a été relevée, mais la facture n’a pas pu être créée.');
        }
        bd_journal_enregistrer(
            (int) $_SESSION['id_utilisateur'],
            'SAISIE_AGENT',
            'consommations',
            $idConso,
            'Saisie manuelle du contrat ' . $contrat['numero_contrat'] . ' pour ' . sprintf('%02d/%04d', $mois, $annee)
        );
        if (!mysqli_commit($link)) {
            throw new RuntimeException("Impossible de valider le relevé : " . mysqli_error($link));
        }
    } catch (RuntimeException $exception) {
        mysqli_rollback($link);
        error_log($exception->getMessage());
        consommation_agent_retour(
            $urlRetour . '/' . (int) $idContrat,
            'Le relevé n’a pas pu être enregistré. Vérifiez la période et les tarifs du contrat.'
        );
    }

    $message = $anomalies
        ? 'Le relevé a été enregistré et transmis au fournisseur pour vérification.'
        : 'Le relevé a été enregistré et la facture générée.';
    consommation_agent_retour($urlRetour, $message, 'message_agent_espace');
}

function Consommation_import_annuel($param = null)
{
    consommation_agent_connecte();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        import_annuel_traiter((int) $_SESSION['id_agent']);
        return;
    }

    $erreur = $_SESSION['erreur_agent_espace'] ?? '';
    $message = $_SESSION['message_agent_espace'] ?? '';
    unset($_SESSION['erreur_agent_espace'], $_SESSION['message_agent_espace']);
    include ROOT . 'IHM/agent/depot_fichier.php';
}