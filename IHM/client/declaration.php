<?php
session_start();
require_once __DIR__ . '/../../BD/Consommation.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'CLIENT') {
    header('Location: ../../index.php');
    exit;
}

$idClient = (int) $_SESSION['id_client'];

$derniere = dernierSaisie($idClient);
if ($derniere) {
    $eauPrecedent = (int) $derniere['index_eau_nouveau'];
    $elecPrecedent = (int) $derniere['index_elec_nouveau'];
} else {
    $initial = indexInitial($idClient);
    $eauPrecedent = (int) $initial['index_initial_eau'];
    $elecPrecedent = (int) $initial['index_initial_elec'];
}

$erreur = $_SESSION['erreur'] ?? '';
$succes = $_SESSION['succes'] ?? '';
unset($_SESSION['erreur'], $_SESSION['succes']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ma déclaration</title>
    <script src="../../assets/js/declaration.js" defer></script>
</head>
<body>

<?php include __DIR__ . '/../layouts/sidebar_client.php'; ?>

<?php if ($erreur): ?>
    <p style="color:red"><?= htmlspecialchars($erreur) ?></p>
<?php endif; ?>

<?php if ($succes): ?>
    <p style="color:green"><?= htmlspecialchars($succes) ?></p>
<?php endif; ?>

<form method="POST" action="../../Traitement/Consommation.php" enctype="multipart/form-data">

    <h3>Eau</h3>
    <p>Index précédent : <span id="eau-prec" data-valeur="<?= $eauPrecedent ?>"><?= $eauPrecedent ?> m³</span></p>
    <label>Nouvel index :</label>
    <input type="text" name="index_eau" required>
    <p>Consommation : <span id="conso-eau">—</span></p>
    <label>Photo du compteur :</label>
    <input type="file" id="photo_eau" name="photo_eau" accept="image/jpeg,image/png" required>
    <img id="apercu-eau" style="display:none; max-width:200px">

    <h3>Électricité</h3>
    <p>Index précédent : <span id="elec-prec" data-valeur="<?= $elecPrecedent ?>"><?= $elecPrecedent ?> kWh</span></p>
    <label>Nouvel index :</label>
    <input type="text" name="index_elec" required>
    <p>Consommation : <span id="conso-elec">—</span></p>
    <label>Photo du compteur :</label>
    <input type="file" id="photo_elec" name="photo_elec" accept="image/jpeg,image/png" required>
    <img id="apercu-elec" style="display:none; max-width:200px">

    <button type="submit">Envoyer ma déclaration</button>
</form>

</body>
</html>