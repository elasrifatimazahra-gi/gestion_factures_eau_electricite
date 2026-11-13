<?php
$historique = $historique ?? [];
$historiqueControles = $historiqueControles ?? [];
$erreur = $erreur ?? '';
$message = $message ?? '';
$agentPageTitle = 'Historique';
$agentPageSubtitle = 'Consultez les relevés mensuels et les contrôles annuels réalisés dans votre tournée.';
$agentPageActive = 'historique';

include ROOT . 'IHM/layouts/agent_header.php';
?>

<section class="agent-card">
    <div class="agent-card__heading">
        <div>
            <h2>Relevés mensuels</h2>
            <p>Dernières saisies enregistrées pour vos contrats.</p>
        </div>
        <span class="agent-card__count"><?= count($historique) ?></span>
    </div>
    <?php if (!$historique): ?>
        <p class="agent-empty">Aucun relevé mensuel n’est disponible pour le moment.</p>
    <?php else: ?>
        <div class="agent-table-wrap">
            <table class="agent-table">
                <thead><tr><th>Date</th><th>Contrat</th><th>Index eau</th><th>Index électricité</th><th>Statut</th></tr></thead>
                <tbody>
                    <?php foreach ($historique as $releve): ?>
                        <?php
                        $statut = (string) ($releve['statut'] ?? '');
                        $statutClass = in_array($statut, ['VALIDE', 'REGULARISEE'], true)
                            ? 'agent-badge--ok'
                            : (in_array($statut, ['REJETE', 'ANOMALIE'], true) ? 'agent-badge--danger' : 'agent-badge--pending');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($releve['date_releve'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="agent-table__primary"><?= htmlspecialchars($releve['numero_contrat'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars((string) ($releve['index_eau'] ?? '—'), ENT_QUOTES, 'UTF-8') ?> <span class="agent-table__secondary">m³</span></td>
                            <td><?= htmlspecialchars((string) ($releve['index_elec'] ?? '—'), ENT_QUOTES, 'UTF-8') ?> <span class="agent-table__secondary">kWh</span></td>
                            <td><span class="agent-badge <?= $statutClass ?>"><?= htmlspecialchars(str_replace('_', ' ', $statut), ENT_QUOTES, 'UTF-8') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="agent-card agent-section-spacer">
    <div class="agent-card__heading">
        <div>
            <h2>Contrôles annuels</h2>
            <p>Résultats et statut de validation des contrôles terrain.</p>
        </div>
        <span class="agent-card__count"><?= count($historiqueControles) ?></span>
    </div>
    <?php if (!$historiqueControles): ?>
        <p class="agent-empty">Aucun contrôle annuel n’est disponible pour le moment.</p>
    <?php else: ?>
        <div class="agent-table-wrap">
            <table class="agent-table">
                <thead><tr><th>Année</th><th>Contrat</th><th>Résultat</th><th>Validation</th><th>Date du relevé</th></tr></thead>
                <tbody>
                    <?php foreach ($historiqueControles as $controle): ?>
                        <?php
                        $resultat = (string) ($controle['resultat'] ?? '');
                        $statutValidation = (string) ($controle['statut_validation'] ?? '');
                        $resultatClass = $resultat === 'CONFORME' ? 'agent-badge--ok' : ($resultat === 'SOUS_DECLARATION' ? 'agent-badge--danger' : 'agent-badge--pending');
                        $validationClass = $statutValidation === 'VALIDE_PAR_FOURNISSEUR' ? 'agent-badge--ok' : ($statutValidation === 'REJETE' ? 'agent-badge--danger' : 'agent-badge--pending');
                        ?>
                        <tr>
                            <td><span class="agent-table__primary"><?= (int) ($controle['annee'] ?? 0) ?></span></td>
                            <td><?= htmlspecialchars($controle['numero_contrat'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="agent-badge <?= $resultatClass ?>"><?= htmlspecialchars(str_replace('_', ' ', $resultat), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><span class="agent-badge <?= $validationClass ?>"><?= htmlspecialchars(str_replace('_', ' ', $statutValidation), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars($controle['date_releve_agent'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php include ROOT . 'IHM/layouts/agent_footer.php'; ?>
