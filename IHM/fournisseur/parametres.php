<?php
$titreFournisseur = 'Paramètres';
$sousTitreFournisseur = 'Consultez et mettez à jour les règles de gestion.';
include ROOT . 'IHM/layouts/fournisseur_header.php';
?>
<section class="m1-page">
    <?php if ($message): ?><div class="m1-alert m1-alert--success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($erreur): ?><div class="m1-alert m1-alert--error"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>

    <?php if (!$parametres): ?>
        <div class="m1-empty">Aucun paramètre n’est configuré.</div>
    <?php else: ?>
        <form method="post" action="<?= BASE_URL ?>Parametre/enregistrer">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
            <div class="settings-grid">
                <?php foreach ($parametres as $parametre): ?>
                    <section class="card">
                        <h2 class="card__title"><?= htmlspecialchars($parametre['libelle']) ?></h2>
                        <label class="field">
                            <span class="field__label"><?= htmlspecialchars($parametre['cle']) ?></span>
                            <?php if ($parametre['type_donnee'] === 'BOOLEAN'): ?>
                                <select class="field__input" name="parametres[<?= htmlspecialchars($parametre['cle'], ENT_QUOTES) ?>]">
                                    <option value="1" <?= $parametre['valeur'] === '1' ? 'selected' : '' ?>>Activé</option>
                                    <option value="0" <?= $parametre['valeur'] === '0' ? 'selected' : '' ?>>Désactivé</option>
                                </select>
                            <?php else: ?>
                                <input
                                    class="field__input"
                                    type="<?= in_array($parametre['type_donnee'], ['INTEGER', 'DECIMAL'], true) ? 'number' : 'text' ?>"
                                    <?= $parametre['type_donnee'] === 'INTEGER' ? 'step="1"' : '' ?>
                                    <?= $parametre['type_donnee'] === 'DECIMAL' ? 'step="any"' : '' ?>
                                    name="parametres[<?= htmlspecialchars($parametre['cle'], ENT_QUOTES) ?>]"
                                    value="<?= htmlspecialchars($parametre['valeur'], ENT_QUOTES) ?>"
                                    maxlength="100"
                                    required
                                >
                            <?php endif; ?>
                        </label>
                        <?php if ($parametre['description']): ?>
                            <p class="m1-description"><?= htmlspecialchars($parametre['description']) ?></p>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>
            <div class="m1-actions">
                <button class="m1-button" type="submit">Enregistrer les paramètres</button>
            </div>
        </form>
    <?php endif; ?>
</section>
<section class="m1-page m1-tariff-page">
    <h2 class="m1-section-title">Tarifs par type d’activité</h2>
    <p class="m1-hint">Les tarifs commerciaux et industriels doivent être renseignés avant de facturer ces quartiers. Les cellules vides ne remplacent pas les tarifs déjà enregistrés.</p>
    <form method="post" action="<?= BASE_URL ?>Parametre/enregistrerTarifs">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
        <?php foreach ($tarifsParActivite as $idType => $activite): ?>
            <section class="card m1-tariff-card">
                <h3 class="card__title"><?= htmlspecialchars($activite['libelle']) ?></h3>
                <?php foreach (['EAU' => 'Eau', 'ELECTRICITE' => 'Électricité'] as $fluide => $libelleFluide): ?>
                    <h4 class="m1-tariff-fluid"><?= htmlspecialchars($libelleFluide) ?></h4>
                    <div class="m1-tariff-grid">
                        <?php foreach ($activite['grille'][$fluide] ?? [] as $numeroTranche => $tranche): ?>
                            <label class="field">
                                <span class="field__label">
                                    Tranche <?= (int) $numeroTranche ?>
                                    (<?= (int) $tranche['borne_min'] ?>–<?= $tranche['borne_max'] === null ? '∞' : (int) $tranche['borne_max'] ?>)
                                </span>
                                <span class="m1-tariff-input">
                                    <input
                                        class="field__input"
                                        type="number"
                                        min="0"
                                        step="0.0001"
                                        name="tarifs[<?= (int) $idType ?>][<?= htmlspecialchars($fluide) ?>][<?= (int) $numeroTranche ?>]"
                                        value="<?= $tranche['prix_unitaire_dh'] === null ? '' : htmlspecialchars((string) $tranche['prix_unitaire_dh'], ENT_QUOTES) ?>"
                                        placeholder="DH / unité"
                                    >
                                    <span>DH</span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
        <div class="m1-actions">
            <button class="m1-button" type="submit">Enregistrer les tarifs</button>
        </div>
    </form>
</section>
<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>
