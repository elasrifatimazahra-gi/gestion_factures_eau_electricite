<?php
$titreFournisseur = 'Contrats';
$sousTitreFournisseur = 'Gérez les contrats liés à chaque client et quartier.';
include ROOT . 'IHM/layouts/fournisseur_header.php';
?>
<div class="clients-content">
    <div class="clients-actions">
        <a href="<?= BASE_URL ?>Contrat/ajouter" class="btn btn--primary">+ Ajouter contrat</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="clients-message clients-message--success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (!empty($erreur)): ?>
        <div class="clients-message clients-message--error"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <section class="dash-carte clients-table-card">
        <div class="clients-table-scroll">
            <table class="dash-tableau clients-table">
                <thead>
                    <tr>
                        <th>Contrat</th>
                        <th>Client</th>
                        <th>Quartier</th>
                        <th>Compteur eau</th>
                        <th>Compteur élec</th>
                        <th>Index initial</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$contrats): ?>
                        <tr><td class="clients-empty" colspan="8">Aucun contrat enregistré.</td></tr>
                    <?php else: ?>
                        <?php foreach ($contrats as $contrat): ?>
                            <tr>
                                <td><?= htmlspecialchars($contrat['numero_contrat']) ?></td>
                                <td><?= htmlspecialchars(trim(($contrat['client_nom'] ?? '') ?: 'Client inconnu')) ?></td>
                                <td><?= htmlspecialchars($contrat['quartier_nom'] ?? 'Aucun') ?></td>
                                <td><?= htmlspecialchars($contrat['numero_compteur_eau'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($contrat['numero_compteur_elec'] ?? '—') ?></td>
                                <td><?= (int) ($contrat['index_initial_eau'] ?? 0) ?> / <?= (int) ($contrat['index_initial_elec'] ?? 0) ?></td>
                                <td>
                                    <?php
                                    $libelleStatut = [
                                        'ACTIF' => 'Actif',
                                        'SUSPENDU' => 'Suspendu',
                                        'RESILIE' => 'Résilié',
                                    ];
                                    echo htmlspecialchars($libelleStatut[$contrat['statut']] ?? $contrat['statut']);
                                    ?>
                                </td>
                                <td class="clients-row-actions">
                                    <a href="<?= BASE_URL ?>Contrat/modifier/<?= (int) $contrat['id_contrat'] ?>" class="clients-action clients-action--edit">Modifier</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>
