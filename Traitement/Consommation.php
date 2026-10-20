<?php
// Contrôleur de la saisie des consommations, appelé par index.php :
//   Consommation/declaration -> Consommation_declaration()
//   Consommation/enregistrer -> Consommation_enregistrer()

require_once ROOT . 'BD/Consommation.php';
require_once ROOT . 'BD/Utilisateur.php';
require_once ROOT . 'BD/Facture.php';
require_once ROOT . 'Traitement/Auth.php';
require_once ROOT . 'Traitement/Upload.php';
require_once ROOT . 'Traitement/Anomalie.php';
require_once ROOT . 'Traitement/Facturation.php';

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
function consommation_retour($message, $cle = 'erreur')
{
    $_SESSION[$cle] = $message;
    header('Location: ' . BASE_URL . 'Consommation/declaration');
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

// Affiche la page de déclaration
function Consommation_declaration($param = null)
{
    exiger_role('CLIENT');
    $idClient = consommation_id_client();

    $prec = consommation_index_precedents($idClient);
    $eauPrecedent = $prec['eau'];
    $elecPrecedent = $prec['elec'];

    // Informations du client pour la carte utilisateur de la sidebar
    $client = bd_facture_infos_client($idClient) ?: ['nom' => '', 'prenom' => '', 'numero_client' => ''];

    // Date limite : dernier jour du mois en cours
    $mois = consommation_mois_fr();
    $dateLimite = date('t') . ' ' . $mois[(int) date('n')];

    // Date du dernier relevé enregistré
    $derniere = derniereSaisie($idClient);
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

    $idClient = consommation_id_client();
    $annee = (int) date('Y');
    $mois = (int) date('n');
    $jour = (int) date('j');

    // Période (RG11) et une seule saisie par mois (RG12)
    if ($jour < 18) {
        consommation_retour("La saisie est ouverte du 18 à la fin du mois.");
    }
    if (saisieExiste($idClient, $mois, $annee)) {
        consommation_retour("Vous avez déjà saisi vos index ce mois-ci.");
    }

    // Index entiers positifs
    if (!ctype_digit($_POST['index_eau'] ?? '') || !ctype_digit($_POST['index_elec'] ?? '')) {
        consommation_retour("Les index doivent être des nombres entiers positifs.");
    }
    $eauNouv = (int) $_POST['index_eau'];
    $elecNouv = (int) $_POST['index_elec'];

    // Calcul côté serveur (RG15) et anomalie de base (RG21)
    $prec = consommation_index_precedents($idClient);
    $eauPrec = $prec['eau'];
    $elecPrec = $prec['elec'];
    $consoEau = $eauNouv - $eauPrec;
    $consoElec = $elecNouv - $elecPrec;
    $anomalie = ($consoEau < 0 || $consoElec < 0);

    // Photos (RG13)
    $photoEau = uploadPhoto($_FILES['photo_eau'] ?? []);
    if ($photoEau === false) {
        consommation_retour($erreurUpload);
    }
    $photoElec = uploadPhoto($_FILES['photo_elec'] ?? []);
    if ($photoElec === false) {
        consommation_retour($erreurUpload);
    }

    // Enregistrement
    $statut = $anomalie ? 'EN_ATTENTE_CORRECTION' : 'VALIDE';
    $id = enregistrer(
        $idClient, $annee, $mois,
        $eauPrec, $eauNouv, $consoEau, $photoEau,
        $elecPrec, $elecNouv, $consoElec, $photoElec,
        'REELLE', $statut
    );
    if ($id === false) {
        consommation_retour("Une erreur est survenue lors de l'enregistrement.");
    }

    // Suite : anomalie ou facturation (modules des autres membres)
    if ($anomalie) {
        if (function_exists('signalerAnomalie')) {
            signalerAnomalie($id);
        }
        consommation_retour("Votre saisie présente une anomalie et est en cours de vérification.", 'succes');
    }
    if (function_exists('genererFacture')) {
        genererFacture($id);
    }
    consommation_retour("Votre consommation a bien été enregistrée.", 'succes');
}