<?php
$titreFournisseur = 'Journal des actions';
$sousTitreFournisseur = 'Consultez les opérations récentes et filtrez leur historique.';
include ROOT . 'IHM/layouts/fournisseur_header.php';

/** Actions urgentes (retard, coupure, alerte, fraude, sanction) en rouge ; règlements en vert. */
$classeAction = function ($action) {
    if (preg_match('/RETARD|COUPURE|ALERTE|FRAUDE|SANCTION|ANOMALIE/', $action)) {
        return 'm4-tag--danger';
    }
    if (preg_match('/PAIEMENT|RETABLISSEMENT/', $action)) {
        return 'm4-tag--ok';
    }
    return 'm4-tag--neutral';
};
$rolesLibelles = ['CLIENT' => 'Client', 'AGENT' => 'Agent', 'FOURNISSEUR' => 'Fournisseur'];
?>
<section class="m1-page">
    <?php if ($erreur): ?>
        <div class="m4-notice m4-notice--error" role="alert"><span><?= htmlspecialchars($erreur) ?></span><button class="m4-notice__close" type="button" aria-label="Fermer">×</button></div>
    <?php endif; ?>

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
                    <th>Date</th><th>Utilisateur</th><th>Client concerné</th><th>Action</th><th>Élément</th><th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$journal): ?>
                    <tr><td class="m1-empty" colspan="6">Aucune action ne correspond à ces filtres.</td></tr>
                <?php else: ?>
                    <?php foreach ($journal as $ligne): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ligne['date_action']))) ?></td>
                            <td>
                                <?php if ($ligne['id_utilisateur'] === null): ?>
                                    <strong>Système</strong><br><small>Action automatique</small>
                                <?php elseif (!empty($ligne['nom_complet'])): ?>
                                    <strong><?= htmlspecialchars($ligne['nom_complet']) ?></strong><br>
                                    <small><?= htmlspecialchars($rolesLibelles[$ligne['role']] ?? '') ?></small>
                                <?php elseif (($ligne['role'] ?? '') === 'FOURNISSEUR'): ?>
                                    <strong>Administrateur</strong><br><small><?= htmlspecialchars($ligne['email'] ?? $ligne['identifiant'] ?? '') ?></small>
                                <?php elseif (!empty($ligne['identifiant'])): ?>
                                    <strong><?= htmlspecialchars($ligne['identifiant']) ?></strong>
                                <?php else: ?>
                                    Utilisateur supprimé
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($ligne['client_nom'])): ?>
                                    <strong><?= htmlspecialchars($ligne['client_nom']) ?></strong><br>
                                    <small><?= htmlspecialchars($ligne['client_numero']) ?></small>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><span class="m4-tag <?= $classeAction($ligne['action_type']) ?>"><?= htmlspecialchars($ligne['action_type']) ?></span></td>
                            <td>
                                <?= htmlspecialchars($ligne['table_concernee'] ?? '—') ?>
                                <?= $ligne['id_entite'] === null ? '' : '#' . (int) $ligne['id_entite'] ?>
                            </td>
                            <td><?= htmlspecialchars($ligne['description']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="m1-hint">Les 200 actions les plus récentes au maximum sont affichées.</p>
</section>
<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>