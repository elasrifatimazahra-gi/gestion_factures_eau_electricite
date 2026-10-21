<?php
$titreFournisseur = 'Détail de l’anomalie';
$sousTitreFournisseur = 'Vérifiez les relevés et traitez cette anomalie.';
include ROOT . 'IHM/layouts/fournisseur_header.php';

$indexEauPrec = (float) $a['index_eau_precedent'];
$indexElecPrec = (float) $a['index_elec_precedent'];
?>

<a class="retour-clients" href="<?= BASE_URL ?>Anomalie/index">&larr; Retour aux anomalies</a>

<?php if (!empty($erreur)): ?>
    <div class="clients-message clients-message--error"><?= htmlspecialchars($erreur) ?></div>
<?php endif; ?>

<section class="dash-carte anomalie-detail-card">
    <h2>Anomalie : <?= htmlspecialchars(trim($a['prenom'] . ' ' . $a['nom'])) ?></h2>
    <p><strong>Client :</strong> <?= htmlspecialchars($a['numero_client']) ?></p>
    <p><strong>Type :</strong> <?= htmlspecialchars($a['type']) ?></p>
    <p><strong>Période :</strong> <?= (int) $a['mois'] ?>/<?= (int) $a['annee'] ?></p>
    <p><strong>Statut :</strong> <?= htmlspecialchars(STATUTS_ANOMALIE_LIBELLES[$a['statut']] ?? $a['statut']) ?></p>
    <p><strong>Description :</strong> <?= htmlspecialchars($a['description']) ?></p>

    <div class="anomalie-photos">
        <div>
            <h3>Compteur d’eau</h3>
            <?php if (!empty($a['photo_eau'])): ?>
                <img src="<?= htmlspecialchars(BASE_URL . ltrim($a['photo_eau'], '/')) ?>" alt="Photo du compteur d’eau">
            <?php else: ?>
                <p>Aucune photo disponible.</p>
            <?php endif; ?>
        </div>
        <div>
            <h3>Compteur d’électricité</h3>
            <?php if (!empty($a['photo_elec'])): ?>
                <img src="<?= htmlspecialchars(BASE_URL . ltrim($a['photo_elec'], '/')) ?>" alt="Photo du compteur d’électricité">
            <?php else: ?>
                <p>Aucune photo disponible.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="clients-table-scroll anomalie-table-scroll">
        <table class="dash-tableau anomalie-table">
            <thead>
                <tr>
                    <th>Mesure</th>
                    <th>Eau (m³)</th>
                    <th>Électricité (kWh)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Index précédent</td>
                    <td><?= htmlspecialchars((string) $indexEauPrec) ?></td>
                    <td><?= htmlspecialchars((string) $indexElecPrec) ?></td>
                </tr>
                <tr>
                    <td>Index saisi</td>
                    <td><?= htmlspecialchars((string) $a['index_eau']) ?></td>
                    <td><?= htmlspecialchars((string) $a['index_elec']) ?></td>
                </tr>
                <tr>
                    <td>Consommation calculée</td>
                    <td><?= htmlspecialchars((string) $a['conso_eau']) ?></td>
                    <td><?= htmlspecialchars((string) $a['conso_elec']) ?></td>
                </tr>
                <tr>
                    <td>Seuil</td>
                    <td><?= htmlspecialchars((string) $seuilEau) ?></td>
                    <td><?= htmlspecialchars((string) $seuilElec) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<?php if ($peutValider): ?>
    <section class="dash-carte anomalie-action-card">
        <h2>Valider cette consommation</h2>
        <p>Les index sont cohérents, mais la consommation dépasse le seuil autorisé.</p>
        <form method="post" action="<?= BASE_URL ?>Anomalie/valider/<?= (int) $a['id_anomalie'] ?>">
            <button type="submit" class="btn btn--primary">Valider la consommation</button>
        </form>
    </section>
<?php elseif ($a['statut'] === 'EN_ATTENTE'): ?>
    <section class="dash-carte anomalie-action-card">
        <h2>Corriger les index</h2>
        <form method="post" action="<?= BASE_URL ?>Anomalie/corriger/<?= (int) $a['id_anomalie'] ?>" class="anomalie-form">
            <label for="index_eau">Index eau corrigé</label>
            <input type="number" id="index_eau" name="index_eau" min="<?= htmlspecialchars((string) $indexEauPrec) ?>" value="<?= htmlspecialchars((string) $a['index_eau']) ?>" required>

            <label for="index_elec">Index électricité corrigé</label>
            <input type="number" id="index_elec" name="index_elec" min="<?= htmlspecialchars((string) $indexElecPrec) ?>" value="<?= htmlspecialchars((string) $a['index_elec']) ?>" required>

            <button type="submit" class="btn btn--primary">Enregistrer la correction</button>
        </form>
    </section>
<?php else: ?>
    <section class="dash-carte anomalie-action-card">
        <p>Cette anomalie a déjà été traitée.</p>
    </section>
<?php endif; ?>

<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>
