<?php
require_once __DIR__ . "/../../BD/Anomalie.php";
require_once __DIR__ . "/../../BD/Parametre.php";
require_once __DIR__ . "/../../BD/Consommation.php"; 

$id  = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$res = trouverAnomalieParId($id);
$a   = $res ? mysqli_fetch_assoc($res) : null;

if (!$a) {
    echo "<p>Anomalie introuvable.</p>";
    return;
}

$prec      = trouverIndexPrecedent($a['id_client'], $a['mois'], $a['annee']);
$seuilEau  = (float) lireParametre('seuil_eau', 100);
$seuilElec = (float) lireParametre('seuil_electricite', 2000);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $a['statut'] === 'ouverte') {
    $ie = isset($_POST['index_eau'])  ? $_POST['index_eau']  : '';
    $il = isset($_POST['index_elec']) ? $_POST['index_elec'] : '';

    if (!is_numeric($ie) || !is_numeric($il)) {
        $message = "Les index doivent être numériques.";
    } elseif ((float) $ie < $prec['eau'] || (float) $il < $prec['elec']) {
        $message = "Un index corrigé ne peut pas être inférieur à l'index précédent.";
    } else {
        $ie = (float) $ie;
        $il = (float) $il;
        corrigerConso($a['id_conso'], $ie, $il, $ie - $prec['eau'], $il - $prec['elec']);
        marquerAnomalieTraitee($id);

        $f = __DIR__ . "/../../Traitement/Facturation.php";
        if (is_file($f)) {
            require_once $f;
            if (function_exists('genererFacture')) {
                genererFacture($a['id_conso']);
            }
        }
        header("Location: anomalies.php");
        exit;
    }
}

$consoEau  = (float) $a['index_eau']  - $prec['eau'];
$consoElec = (float) $a['index_elec'] - $prec['elec'];
?>
<h2>Anomalie : <?php echo htmlspecialchars($a['prenom'] . ' ' . $a['nom']); ?> (<?php echo htmlspecialchars($a['numero_client']); ?>)</h2>
<p>Type : <?php echo htmlspecialchars($a['type']); ?> · Période : <?php echo (int) $a['mois'] . '/' . (int) $a['annee']; ?></p>

<?php if ($message !== '') { ?>
    <p><strong><?php echo htmlspecialchars($message); ?></strong></p>
<?php } ?>

<table>
    <thead>
        <tr><th></th><th>Eau (m³)</th><th>Électricité (kWh)</th></tr>
    </thead>
    <tbody>
        <tr>
            <td>Photo du compteur</td>
            <td><?php if ($a['photo_eau']) { ?><img src="../../uploads/<?php echo htmlspecialchars($a['photo_eau']); ?>" alt="Compteur eau" width="250"><?php } ?></td>
            <td><?php if ($a['photo_elec']) { ?><img src="../../uploads/<?php echo htmlspecialchars($a['photo_elec']); ?>" alt="Compteur électricité" width="250"><?php } ?></td>
        </tr>
        <tr>
            <td>Index saisi</td>
            <td><?php echo htmlspecialchars($a['index_eau']); ?></td>
            <td><?php echo htmlspecialchars($a['index_elec']); ?></td>
        </tr>
        <tr>
            <td>Index précédent</td>
            <td><?php echo $prec['eau']; ?></td>
            <td><?php echo $prec['elec']; ?></td>
        </tr>
        <tr>
            <td>Consommation calculée</td>
            <td><?php echo $consoEau; ?></td>
            <td><?php echo $consoElec; ?></td>
        </tr>
        <tr>
            <td>Seuil</td>
            <td><?php echo $seuilEau; ?></td>
            <td><?php echo $seuilElec; ?></td>
        </tr>
    </tbody>
</table>

<?php if ($a['statut'] === 'ouverte') { ?>
<h3>Correction d'après les photos</h3>
<form method="post">
    <label>Index eau corrigé
        <input type="number" step="0.01" name="index_eau" min="<?php echo $prec['eau']; ?>" required>
    </label>
    <label>Index électricité corrigé
        <input type="number" step="0.01" name="index_elec" min="<?php echo $prec['elec']; ?>" required>
    </label>
    <button type="submit">Corriger et générer la facture</button>
</form>
<?php } else { ?>
<p>Cette anomalie a déjà été traitée.</p>
<?php } ?>

<p><a href="anomalies.php">Retour à la liste</a></p>