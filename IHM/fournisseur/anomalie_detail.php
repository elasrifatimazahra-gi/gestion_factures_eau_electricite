```php
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../BD/Anomalie.php";
require_once __DIR__ . "/../../BD/Parametre.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$res = $id > 0 ? trouverAnomalieParId($id) : false;
$a = $res ? mysqli_fetch_assoc($res) : null;

if (!$a) {
    echo "<p>Anomalie introuvable.</p>";
    exit;
}

$prec = trouverIndexPrecedent(
    $a['id_client'],
    $a['mois'],
    $a['annee']
);

$seuilEau = (float) lireParametre('seuil_eau', 100);
$seuilElec = (float) lireParametre('seuil_electricite', 2000);

$message = '';
$erreur = false;

$indexEauPrec = (float) $prec['eau'];
$indexElecPrec = (float) $prec['elec'];

$indexEauActuel = (float) $a['index_eau'];
$indexElecActuel = (float) $a['index_elec'];

$consoEau = (float) $a['conso_eau'];
$consoElec = (float) $a['conso_elec'];

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $a['statut'] === 'EN_ATTENTE'
) {
    $ie = $_POST['index_eau'] ?? '';
    $il = $_POST['index_elec'] ?? '';
    $commentaire = trim($_POST['commentaire'] ?? '');

    $idUtilisateur = $_SESSION['id_utilisateur'] ?? null;

    if (
        !$idUtilisateur
        || !is_numeric($idUtilisateur)
        || (int) $idUtilisateur <= 0
    ) {
        $message = "Utilisateur connecté introuvable. Reconnectez-vous.";
        $erreur = true;

    } elseif (
        !is_numeric($ie)
        || !is_numeric($il)
        || !is_finite((float) $ie)
        || !is_finite((float) $il)
    ) {
        $message = "Les index doivent être numériques et valides.";
        $erreur = true;

    } elseif (
        (float) $ie < $indexEauPrec
        || (float) $il < $indexElecPrec
    ) {
        $message = "Les index corrigés ne peuvent pas être inférieurs aux index précédents.";
        $erreur = true;

    } else {
        $ie = (float) $ie;
        $il = (float) $il;

        $nouvelleConsoEau = $ie - $indexEauPrec;
        $nouvelleConsoElec = $il - $indexElecPrec;

        $conn = Connexion();

        mysqli_begin_transaction($conn);

        try {
            // Mettre à jour uniquement si l'anomalie est encore en attente.
            $sql = mysqli_prepare(
                $conn,
                "UPDATE consommations
                 SET index_eau_precedent = ?,
                     index_eau_nouveau = ?,
                     conso_eau = ?,
                     index_elec_precedent = ?,
                     index_elec_nouveau = ?,
                     conso_elec = ?,
                     statut = 'VALIDE',
                     type_saisie = 'CORRIGEE'
                 WHERE id_conso = ?
                   AND EXISTS (
                       SELECT 1
                       FROM anomalies
                       WHERE anomalies.id_conso = consommations.id_conso
                         AND anomalies.id_anomalie = ?
                         AND anomalies.statut = 'EN_ATTENTE'
                   )"
            );

            if (!$sql) {
                throw new Exception("Préparation de la mise à jour impossible.");
            }

            mysqli_stmt_bind_param(
                $sql,
                "ddddddii",
                $indexEauPrec,
                $ie,
                $nouvelleConsoEau,
                $indexElecPrec,
                $il,
                $nouvelleConsoElec,
                $a['id_conso'],
                $id
            );

            if (!mysqli_stmt_execute($sql)) {
                throw new Exception("Erreur lors de la mise à jour de la consommation.");
            }

            $nbModifiees = mysqli_stmt_affected_rows($sql);
            mysqli_stmt_close($sql);

            if ($nbModifiees !== 1) {
                throw new Exception(
                    "La consommation n'a pas été modifiée. Vérifiez le statut de l'anomalie."
                );
            }

            $sql = mysqli_prepare(
                $conn,
                "UPDATE anomalies
                 SET statut = 'CORRIGEE',
                     index_eau_rectifie = ?,
                     conso_eau_rectifiee = ?,
                     index_elec_rectifie = ?,
                     conso_elec_rectifiee = ?,
                     date_traitement = NOW(),
                     commentaire_traitement = ?,
                     id_utilisateur_traitement = ?
                 WHERE id_anomalie = ?
                   AND statut = 'EN_ATTENTE'"
            );

            if (!$sql) {
                throw new Exception("Préparation de la correction de l'anomalie impossible.");
            }

            mysqli_stmt_bind_param(
                $sql,
                "ddddsii",
                $ie,
                $nouvelleConsoEau,
                $il,
                $nouvelleConsoElec,
                $commentaire,
                $idUtilisateur,
                $id
            );

            if (!mysqli_stmt_execute($sql)) {
                throw new Exception("Erreur lors de la mise à jour de l'anomalie.");
            }

            $nbAnomalies = mysqli_stmt_affected_rows($sql);
            mysqli_stmt_close($sql);

            if ($nbAnomalies !== 1) {
                throw new Exception("L'anomalie n'a pas été mise à jour.");
            }

            mysqli_commit($conn);

            header("Location: anomalies.php");
            exit;

        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $message = $e->getMessage();
            $erreur = true;
        }
    }
}
?>

<h2>
    Anomalie :
    <?= htmlspecialchars($a['prenom'] . ' ' . $a['nom'], ENT_QUOTES, 'UTF-8') ?>
    (<?= htmlspecialchars($a['numero_client'], ENT_QUOTES, 'UTF-8') ?>)
</h2>

<p>
    Type :
    <?= htmlspecialchars($a['type'], ENT_QUOTES, 'UTF-8') ?>
    — Période :
    <?= (int) $a['mois'] ?>/<?= (int) $a['annee'] ?>
</p>

<p>
    Statut :
    <?= htmlspecialchars($a['statut'], ENT_QUOTES, 'UTF-8') ?>
</p>

<?php if ($message !== ''): ?>
    <p>
        <strong>
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </strong>
    </p>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th></th>
            <th>Eau (m³)</th>
            <th>Électricité (kWh)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Photo du compteur</td>
            <td>
                <?php if (!empty($a['photo_eau'])): ?>
                    <img
                        src="../../uploads/<?= htmlspecialchars(basename($a['photo_eau']), ENT_QUOTES, 'UTF-8') ?>"
                        alt="Compteur d'eau"
                        width="250"
                    >
                <?php else: ?>
                    Aucune photo
                <?php endif; ?>
            </td>
            <td>
                <?php if (!empty($a['photo_elec'])): ?>
                    <img
                        src="../../uploads/<?= htmlspecialchars(basename($a['photo_elec']), ENT_QUOTES, 'UTF-8') ?>"
                        alt="Compteur d'électricité"
                        width="250"
                    >
                <?php else: ?>
                    Aucune photo
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td>Index saisi</td>
            <td><?= htmlspecialchars((string) $indexEauActuel, ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $indexElecActuel, ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td>Index précédent</td>
            <td><?= htmlspecialchars((string) $indexEauPrec, ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $indexElecPrec, ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td>Consommation calculée</td>
            <td><?= htmlspecialchars((string) $consoEau, ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $consoElec, ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <tr>
            <td>Seuil</td>
            <td><?= htmlspecialchars((string) $seuilEau, ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $seuilElec, ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
    </tbody>
</table>

<?php if ($a['statut'] === 'EN_ATTENTE'): ?>

    <h3>Correction d'après les photos</h3>

    <form method="post">
        <label>
            Index eau corrigé
            <input
                type="number"
                step="0.01"
                min="<?= htmlspecialchars((string) $indexEauPrec, ENT_QUOTES, 'UTF-8') ?>"
                name="index_eau"
                value="<?= htmlspecialchars((string) $indexEauActuel, ENT_QUOTES, 'UTF-8') ?>"
                required
            >
        </label>

        <br>

        <label>
            Index électricité corrigé
            <input
                type="number"
                step="0.01"
                min="<?= htmlspecialchars((string) $indexElecPrec, ENT_QUOTES, 'UTF-8') ?>"
                name="index_elec"
                value="<?= htmlspecialchars((string) $indexElecActuel, ENT_QUOTES, 'UTF-8') ?>"
                required
            >
        </label>

        <br>

        <label>
            Commentaire
            <textarea name="commentaire" rows="3"></textarea>
        </label>

        <br>

        <button type="submit">Corriger</button>
    </form>

<?php else: ?>

    <p>Cette anomalie n'est plus en attente de correction.</p>

<?php endif; ?>

<p>
    <a href="anomalies.php">Retour à la liste</a>
</p>