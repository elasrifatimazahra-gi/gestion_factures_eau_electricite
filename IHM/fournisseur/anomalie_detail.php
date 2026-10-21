<?php
/** @var array  $a                 Anomalie + consommation + client (Anomalie_detail) */
/** @var string $erreur            Message d'erreur flash */
/** @var float  $seuilEau */
/** @var float  $seuilElec */
/** @var array  $anomaliesOuvertes */
/** @var bool   $peutValider */

// Vue appelée par Anomalie_detail() : utilise $a, $erreur, $seuilEau, $seuilElec,
// $anomaliesOuvertes et $peutValider. Aucun traitement ici : les formulaires
// envoient vers Anomalie/corriger/ID et Anomalie/valider/ID.
$pageCss = ['anomalies.css'];
include ROOT . 'IHM/layouts/header.php';

$enAttente = $a['statut'] === 'EN_ATTENTE';
$statLib   = isset(STATUTS_ANOMALIE_LIBELLES[$a['statut']]) ? STATUTS_ANOMALIE_LIBELLES[$a['statut']] : $a['statut'];
$id        = (int) $a['id_anomalie'];

$precEau  = (int) $a['index_eau_precedent'];
$precElec = (int) $a['index_elec_precedent'];
?>

<h2>
    Anomalie : <?= anomalie_e($a['prenom'] . ' ' . $a['nom']) ?>
    (<?= anomalie_e($a['numero_client']) ?>)
</h2>

<p class="anomalie-periode">
    Période : <strong><?= (int) $a['mois'] ?>/<?= (int) $a['annee'] ?></strong>
    — Statut : <span class="badge badge-<?= anomalie_e($a['statut']) ?>"><?= anomalie_e($statLib) ?></span>
</p>

<?php if ($erreur !== ''): ?>
    <div class="alerte-erreur"><?= anomalie_e($erreur) ?></div>
<?php endif; ?>

<div class="conteneur-table bloc">
    <h3>Anomalie(s) détectée(s)</h3>
    <ul>
        <?php if ($enAttente && $anomaliesOuvertes): ?>
            <?php foreach ($anomaliesOuvertes as $o): ?>
                <li>
                    <strong><?= anomalie_e(isset(TYPES_ANOMALIE_LIBELLES[$o['type_anomalie']]) ? TYPES_ANOMALIE_LIBELLES[$o['type_anomalie']] : $o['type_anomalie']) ?></strong>
                    — <?= anomalie_e($o['description']) ?>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li>
                <strong><?= anomalie_e(isset(TYPES_ANOMALIE_LIBELLES[$a['type']]) ? TYPES_ANOMALIE_LIBELLES[$a['type']] : $a['type']) ?></strong>
                — <?= anomalie_e($a['description']) ?>
            </li>
        <?php endif; ?>
    </ul>
</div>

<div class="conteneur-table bloc">
    <table class="table-clients">
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
                        <img src="<?= BASE_URL . anomalie_e($a['photo_eau']) ?>" alt="Compteur d'eau" class="photo-compteur">
                    <?php else: ?>Aucune photo<?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($a['photo_elec'])): ?>
                        <img src="<?= BASE_URL . anomalie_e($a['photo_elec']) ?>" alt="Compteur d'électricité" class="photo-compteur">
                    <?php else: ?>Aucune photo<?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>Index précédent</td>
                <td><?= $precEau ?></td>
                <td><?= $precElec ?></td>
            </tr>
            <tr>
                <td>Index saisi par le client</td>
                <td><?= anomalie_e($a['index_eau']) ?></td>
                <td><?= anomalie_e($a['index_elec']) ?></td>
            </tr>
            <tr>
                <td>Consommation calculée</td>
                <td><?= anomalie_e($a['conso_eau']) ?></td>
                <td><?= anomalie_e($a['conso_elec']) ?></td>
            </tr>
            <tr>
                <td>Seuil appliqué</td>
                <td><?= anomalie_e($seuilEau) ?></td>
                <td><?= anomalie_e($seuilElec) ?></td>
            </tr>
            <?php if (!$enAttente && $a['index_eau_rectifie'] !== null): ?>
            <tr>
                <td>Index rectifié</td>
                <td><?= anomalie_e($a['index_eau_rectifie']) ?></td>
                <td><?= anomalie_e($a['index_elec_rectifie']) ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($enAttente): ?>

    <div class="conteneur-table bloc">
        <h3>Corriger les index d'après les photos</h3>

        <form method="post" action="<?= BASE_URL ?>Anomalie/corriger/<?= $id ?>">
            <div class="groupe-champ">
                <label>Index eau corrigé
                    <input type="number" step="1" min="<?= $precEau ?>" name="index_eau"
                           value="<?= anomalie_e(max($precEau, (int) $a['index_eau'])) ?>" required>
                </label>
            </div>
            <div class="groupe-champ">
                <label>Index électricité corrigé
                    <input type="number" step="1" min="<?= $precElec ?>" name="index_elec"
                           value="<?= anomalie_e(max($precElec, (int) $a['index_elec'])) ?>" required>
                </label>
            </div>
            <div class="groupe-champ">
                <label>Commentaire (facultatif)
                    <textarea name="commentaire" rows="3" maxlength="1000"></textarea>
                </label>
            </div>
            <button type="submit" class="btn-submit">Corriger et générer la facture</button>
        </form>
    </div>

    <?php if ($peutValider): ?>
        <div class="conteneur-table bloc">
            <h3>Valider telle quelle</h3>
            <p class="aide">
                Seul un dépassement de seuil est en cause : si la consommation est confirmée
                par les photos, vous pouvez valider la saisie sans modifier les index.
            </p>
            <form method="post" action="<?= BASE_URL ?>Anomalie/valider/<?= $id ?>"
                  onsubmit="return confirm('Valider cette saisie telle quelle et générer la facture ?');">
                <div class="groupe-champ">
                    <label>Commentaire (facultatif)
                        <textarea name="commentaire" rows="2" maxlength="1000"></textarea>
                    </label>
                </div>
                <button type="submit" class="btn-submit btn-info">Valider telle quelle</button>
            </form>
        </div>
    <?php else: ?>
        <p class="texte-discret">
            La validation « telle quelle » n'est pas possible : la saisie contient une erreur d'index
            qui doit être corrigée.
        </p>
    <?php endif; ?>

<?php else: ?>

    <p class="texte-traite">
        Cette anomalie a déjà été traitée<?php if (!empty($a['date_traitement'])): ?>
            le <?= anomalie_e(date('d/m/Y H:i', strtotime($a['date_traitement']))) ?><?php endif; ?>.
    </p>
    <?php if (!empty($a['commentaire_resolution'])): ?>
        <p class="texte-traite">Commentaire : <?= anomalie_e($a['commentaire_resolution']) ?></p>
    <?php endif; ?>

<?php endif; ?>

<p><a href="<?= BASE_URL ?>Anomalie/index" class="btn-submit btn-gris">Retour à la liste</a></p>

<?php include ROOT . 'IHM/layouts/footer.php'; ?>
