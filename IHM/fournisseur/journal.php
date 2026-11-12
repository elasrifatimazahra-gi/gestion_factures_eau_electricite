<?php
$titreFournisseur = 'Journal des actions';
$sousTitreFournisseur = 'Consultez les opérations récentes et filtrez leur historique.';
include ROOT . 'IHM/layouts/fournisseur_header.php';
?>
<section class="m1-page">
    <?php if ($erreur): ?><div class="m1-alert m1-alert--error"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>

    <form class="m1-filters" method="get" action="<?= BASE_URL ?>Journal/index">
        <label>
            <span>Action</span>
            <select name="action_type">
                <option value="">Toutes les actions</option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?= htmlspecialchars($action, ENT_QUOTES) ?>" <?= $filtres['action_type'] === $action ? 'selected' : '' ?>>
                        <?= htmlspecialchars($action) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Du</span>
            <input type="date" name="date_debut" value="<?= htmlspecialchars($filtres['date_debut'], ENT_QUOTES) ?>">
        </label>
        <label>
            <span>Au</span>
            <input type="date" name="date_fin" value="<?= htmlspecialchars($filtres['date_fin'], ENT_QUOTES) ?>">
        </label>
        <button class="m1-button" type="submit">Filtrer</button>
        <a class="m1-button m1-button--secondary" href="<?= BASE_URL ?>Journal/index">Réinitialiser</a>
    </form>

    <div class="m1-table-wrap">
        <table class="m1-table">
            <thead>
                <tr>
                    <th>Date</th><th>Utilisateur</th><th>Action</th><th>Élément</th><th>Description</th><th>Adresse IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$journal): ?>
                    <tr><td class="m1-empty" colspan="6">Aucune action ne correspond à ces filtres.</td></tr>
                <?php else: ?>
                    <?php foreach ($journal as $ligne): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ligne['date_action']))) ?></td>
                            <td><?= htmlspecialchars($ligne['identifiant'] ?? 'Utilisateur supprimé') ?></td>
                            <td><span class="m1-tag"><?= htmlspecialchars($ligne['action_type']) ?></span></td>
                            <td>
                                <?= htmlspecialchars($ligne['table_concernee'] ?? '—') ?>
                                <?= $ligne['id_entite'] === null ? '' : '#' . (int) $ligne['id_entite'] ?>
                            </td>
                            <td><?= htmlspecialchars($ligne['description']) ?></td>
                            <td><?= htmlspecialchars($ligne['adresse_ip'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="m1-hint">Les 200 actions les plus récentes au maximum sont affichées.</p>
</section>
<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>
