<?php
$titreFournisseur = 'Quartiers';
$sousTitreFournisseur = 'Gérez les quartiers et leur type d’activité tarifaire.';
include ROOT . 'IHM/layouts/fournisseur_header.php';
?>
<section class="m1-page">
    <?php if ($message): ?><div class="m1-alert m1-alert--success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($erreur): ?><div class="m1-alert m1-alert--error"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>

    <section class="card m1-form-card">
        <h2 class="card__title">Ajouter un quartier</h2>
        <form class="m1-filters" method="post" action="<?= BASE_URL ?>Quartier/enregistrer">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
            <label>
                <span>Nom du quartier</span>
                <input type="text" name="nom" maxlength="120" required>
            </label>
            <label>
                <span>Adresse / zone</span>
                <input type="text" name="adresse" maxlength="500">
            </label>
            <label>
                <span>Type d’activité et grille tarifaire</span>
                <select name="id_type_activite" required>
                    <?php foreach ($typesActivite as $type): ?>
                        <option value="<?= (int) $type['id_type_activite'] ?>"><?= htmlspecialchars($type['libelle']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="m1-button" type="submit">Ajouter</button>
        </form>
    </section>

    <div class="m1-table-wrap">
        <table class="m1-table">
            <thead>
                <tr><th>Quartier</th><th>Type d’activité</th><th>Contrats</th><th>Agents</th><th>Modifier</th></tr>
            </thead>
            <tbody>
                <?php if (!$quartiers): ?>
                    <tr><td class="m1-empty" colspan="5">Aucun quartier n’est enregistré.</td></tr>
                <?php else: ?>
                    <?php foreach ($quartiers as $quartier): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($quartier['nom']) ?></strong>
                                <?php if ($quartier['adresse']): ?><br><?= htmlspecialchars($quartier['adresse']) ?><?php endif; ?>
                            </td>
                            <td><span class="m1-tag"><?= htmlspecialchars($quartier['libelle_activite']) ?></span></td>
                            <td><?= (int) $quartier['nombre_contrats'] ?></td>
                            <td><?= (int) $quartier['nombre_agents'] ?></td>
                            <td>
                                <details>
                                    <summary class="m1-link-button">Modifier</summary>
                                    <form class="m1-edit-form" method="post" action="<?= BASE_URL ?>Quartier/enregistrer">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
                                        <input type="hidden" name="id_quartier" value="<?= (int) $quartier['id_quartier'] ?>">
                                        <label>Nom <input type="text" name="nom" maxlength="120" value="<?= htmlspecialchars($quartier['nom'], ENT_QUOTES) ?>" required></label>
                                        <label>Adresse <input type="text" name="adresse" maxlength="500" value="<?= htmlspecialchars($quartier['adresse'] ?? '', ENT_QUOTES) ?>"></label>
                                        <label>Type
                                            <select name="id_type_activite" required>
                                                <?php foreach ($typesActivite as $type): ?>
                                                    <option value="<?= (int) $type['id_type_activite'] ?>" <?= (int) $type['id_type_activite'] === (int) $quartier['id_type_activite'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($type['libelle']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <button class="m1-button m1-button--small" type="submit">Enregistrer</button>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>
