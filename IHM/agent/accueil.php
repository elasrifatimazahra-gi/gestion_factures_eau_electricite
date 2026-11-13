<?php
$tournees = $tournees ?? [];
$erreur = $erreur ?? '';
$message = $message ?? '';
$agentPageTitle = 'Ma tournée';
$agentPageSubtitle = 'Organisez vos visites et accédez rapidement aux relevés de vos contrats.';
$agentPageActive = 'tournee';

$clientsTournee = [];
foreach ($tournees as $contrat) {
    $clientsTournee[(int) ($contrat['id_client'] ?? 0)] = true;
}

include ROOT . 'IHM/layouts/agent_header.php';
?>

<section class="agent-welcome">
    <div class="agent-welcome__copy">
        <p>Votre espace de travail</p>
        <h2>Bonjour <?= htmlspecialchars($agentPrenom !== '' ? $agentPrenom : 'Agent', ENT_QUOTES, 'UTF-8') ?>, bienvenue.</h2>
        <span>Retrouvez votre tournée, saisissez les index sur le terrain et consultez vos derniers contrôles.</span>
    </div>
    <div class="agent-welcome__mark" aria-hidden="true"><?= $agentIcon('route') ?></div>
</section>

<section class="agent-stats" aria-label="Résumé de la tournée">
    <article class="agent-stat">
        <span class="agent-stat__icon"><?= $agentIcon('grid') ?></span>
        <span>
            <span class="agent-stat__label">Contrats affectés</span>
            <strong><?= count($tournees) ?></strong>
            <small>Dans votre quartier</small>
        </span>
    </article>
    <article class="agent-stat">
        <span class="agent-stat__icon"><?= $agentIcon('route') ?></span>
        <span>
            <span class="agent-stat__label">Clients à visiter</span>
            <strong><?= count($clientsTournee) ?></strong>
            <small>Clients uniques de la tournée</small>
        </span>
    </article>
    <article class="agent-stat">
        <span class="agent-stat__icon"><?= $agentIcon('calendar') ?></span>
        <span>
            <span class="agent-stat__label">Date du jour</span>
            <strong><?= date('d/m/Y') ?></strong>
            <small>Préparez vos relevés du jour</small>
        </span>
    </article>
</section>

<section class="agent-quick-grid" aria-label="Actions rapides">
    <a class="agent-quick-link" href="<?= BASE_URL ?>Consommation/saisie_mensuelle">
        <span class="agent-quick-link__icon"><?= $agentIcon('gauge') ?></span>
        <span><strong>Saisie mensuelle</strong><span>Enregistrer les index d’un contrat</span></span>
        <span class="agent-quick-link__arrow" aria-hidden="true">›</span>
    </a>
    <a class="agent-quick-link" href="<?= BASE_URL ?>Controle/saisie_controle">
        <span class="agent-quick-link__icon"><?= $agentIcon('check') ?></span>
        <span><strong>Contrôle annuel</strong><span>Comparer les consommations terrain</span></span>
        <span class="agent-quick-link__arrow" aria-hidden="true">›</span>
    </a>
    <a class="agent-quick-link" href="<?= BASE_URL ?>Consommation/import_annuel">
        <span class="agent-quick-link__icon"><?= $agentIcon('upload') ?></span>
        <span><strong>Importer un fichier</strong><span>Déposer plusieurs contrôles annuels</span></span>
        <span class="agent-quick-link__arrow" aria-hidden="true">›</span>
    </a>
</section>

<section class="agent-card">
    <div class="agent-card__heading">
        <div>
            <h2>Contrats à visiter</h2>
            <p>Les contrats actifs rattachés à votre tournée.</p>
        </div>
        <span class="agent-card__count"><?= count($tournees) ?></span>
    </div>

    <?php if (!$tournees): ?>
        <p class="agent-empty">Aucun contrat actif n’est affecté à votre quartier. Contactez votre fournisseur pour vérifier votre tournée.</p>
    <?php else: ?>
        <div class="agent-table-wrap">
            <table class="agent-table">
                <thead>
                    <tr><th>Contrat</th><th>Client</th><th>Adresse</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($tournees as $contrat): ?>
                        <tr>
                            <td><span class="agent-table__primary"><?= htmlspecialchars($contrat['numero_contrat'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td>
                                <span class="agent-table__primary"><?= htmlspecialchars(trim(($contrat['prenom'] ?? '') . ' ' . ($contrat['nom'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="agent-table__secondary"><?= htmlspecialchars($contrat['numero_client'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td><?= htmlspecialchars($contrat['adresse'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><a class="agent-table__action" href="<?= BASE_URL ?>Consommation/saisie_mensuelle/<?= (int) ($contrat['id_contrat'] ?? 0) ?>">Saisir un relevé <span aria-hidden="true">→</span></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php include ROOT . 'IHM/layouts/agent_footer.php'; ?>
