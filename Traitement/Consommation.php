<?php
session_start();
require_once __DIR__ . '/../BD/Consommation.php';
require_once __DIR__ . '/Upload.php';

function retour($message, $cle = 'erreur')
{
    $_SESSION[$cle] = $message;
    header('Location: ../IHM/client/declaration.php');
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'CLIENT' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$idClient = (int) $_SESSION['id_client'];
$annee    = (int) date('Y');
$mois     = (int) date('n');
$jour     = (int) date('j');


if ($jour < 18) {
    retour("La saisie est ouverte du 18 à la fin du mois. Revenez à partir du 18.");
}
if (saisieExiste($idClient, $mois, $annee)) {
    retour("Vous avez déjà saisi vos index ce mois-ci.");
}

if (!ctype_digit($_POST['index_eau'] ?? '') || !ctype_digit($_POST['index_elec'] ?? '')) {
    retour("Les index doivent être des nombres entiers positifs.");
}

$eauNouv  = (int) $_POST['index_eau'];
$elecNouv = (int) $_POST['index_elec'];


$derniere = derniereSaisie($idClient);
if ($derniere) {
    $eauPrec  = (int) $derniere['index_eau_nouveau'];
    $elecPrec = (int) $derniere['index_elec_nouveau'];
} else {
    require_once __DIR__ . '/../BD/Client.php';
    $rowClient = mysqli_fetch_assoc(trouverParId($idClient));
    $eauPrec   = (int) ($rowClient['index_initial_eau']  ?? 0);
    $elecPrec  = (int) ($rowClient['index_initial_elec'] ?? 0);
}

$consoEau  = $eauNouv  - $eauPrec;
$consoElec = $elecNouv - $elecPrec;

$anomalie = ($consoEau < 0 || $consoElec < 0);

$photoEau = uploadPhoto($_FILES['photo_eau'] ?? []);
if ($photoEau === false) {
    global $erreurUpload;
    retour($erreurUpload);
}

$photoElec = uploadPhoto($_FILES['photo_elec'] ?? []);
if ($photoElec === false) {
    global $erreurUpload;
    retour($erreurUpload);
}

$statut = $anomalie ? 'EN_ATTENTE_CORRECTION' : 'VALIDE';
$id = enregistrer(
    $idClient, $annee, $mois,
    $eauPrec,  $eauNouv,  $consoEau,  $photoEau,
    $elecPrec, $elecNouv, $consoElec, $photoElec,
    'REELLE', $statut
);

if ($id === false) {
    retour("Une erreur est survenue lors de l'enregistrement. Veuillez réessayer.");
}

if ($anomalie) {
    require_once __DIR__ . '/Anomalie.php';
    signalerAnomalie($id);
    retour("Votre saisie présente une anomalie et est en cours de vérification.", 'succes');
} else {
    require_once __DIR__ . '/Facturation.php';
    genererFacture($id);
    retour("Votre consommation a bien été enregistrée. Votre facture est disponible.", 'succes');
}
?>