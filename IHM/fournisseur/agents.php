<?php
$titreFournisseur = 'Agents';
$sousTitreFournisseur = 'Gérez les agents et leurs quartiers d’affectation.';
include ROOT . 'IHM/layouts/fournisseur_header.php';
?>
<section class="m1-page">
    <?php if ($message): ?><div class="m1-alert m1-alert--success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($erreur): ?><div class="m1-alert m1-alert--error"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>

    <section class="card m1-form-card">
        <h2 class="card__title">Ajouter un agent</h2>
        <form class="m1-filters" method="post" action="<?= BASE_URL ?>Agent/enregistrer">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
            <label><span>Matricule</span><input name="matricule" maxlength="30" required></label>
            <label><span>Nom</span><input name="nom" maxlength="100" required></label>
            <label><span>Prénom</span><input name="prenom" maxlength="100" required></label>
            <label><span>Téléphone</span><input name="telephone" maxlength="25"></label>
            <label><span>E-mail</span><input type="email" name="email" maxlength="100"></label>
            <label><span>Mot de passe initial (12 caractères minimum)</span><input type="password" name="mot_de_passe" minlength="12" autocomplete="new-password" required></label>
            <label><span>Zone</span><input name="zone_affectation" maxlength="100"></label>
            <label>
                <span>Quartier</span>
                <select name="id_quartier" required>
                    <option value="">Choisir un quartier</option>
                    <?php foreach ($quartiers as $quartier): ?>
                        <option value="<?= (int) $quartier['id_quartier'] ?>"><?= htmlspecialchars($quartier['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="m1-button" type="submit">Ajouter</button>
        </form>
    </section>

    <div class="m1-table-wrap">
        <table class="m1-table">
            <thead>
                <tr><th>Matricule</th><th>Agent</th><th>Contact</th><th>Zone</th><th>Quartier</th><th>Compte</th></tr>
            </thead>
            <tbody>
                <?php if (!$agents): ?>
                    <tr><td class="m1-empty" colspan="6">Aucun agent n’est enregistré.</td></tr>
                <?php else: ?>
                    <?php foreach ($agents as $agent): ?>
                        <tr>
                            <td><?= htmlspecialchars($agent['matricule']) ?></td>
                            <td><?= htmlspecialchars(trim($agent['prenom'] . ' ' . $agent['nom'])) ?></td>
                            <td><?= htmlspecialchars($agent['telephone'] ?: ($agent['email'] ?: '—')) ?></td>
                            <td><?= htmlspecialchars($agent['zone_affectation'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($agent['quartier'] ?? 'Non affecté') ?></td>
                            <td><?= $agent['id_utilisateur'] ? 'Actif' : 'Sans compte' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>
