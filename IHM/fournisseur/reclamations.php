<?php
$titreFournisseur = 'Réclamations';
$sousTitreFournisseur = 'Consultez et traitez les demandes des clients.';
include ROOT . 'IHM/layouts/fournisseur_header.php';
$statuts = ['OUVERTE' => 'Ouverte', 'EN_COURS' => 'En cours', 'TRAITEE' => 'Traitée', 'REJETEE' => 'Rejetée'];
$types = ['FUITE_EXTERNE' => 'Fuite externe', 'FUITE_INTERNE' => 'Fuite interne', 'FACTURE' => 'Facture',
    'PROBLEME_COMPTEUR' => 'Problème de compteur', 'COUPURE_INEXPLIQUEE' => 'Coupure inexpliquée', 'AUTRE' => 'Autre'];
?>
<?php if ($message !== ''): ?><div class="clients-message clients-message--success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($erreur !== ''): ?><div class="clients-message clients-message--error"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>
<section class="dash-carte fournisseur-table-card">
    <div class="clients-table-scroll">
        <table class="dash-tableau">
            <thead><tr><th>Référence</th><th>Client</th><th>Motif</th><th>Facture</th><th>Date</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php if (!$reclamations): ?>
                <tr><td class="vide" colspan="7">Aucune réclamation.</td></tr>
            <?php else: foreach ($reclamations as $reclamation): ?>
                <tr>
                    <td><?= htmlspecialchars($reclamation['reference_reclamation']) ?></td>
                    <td><?= htmlspecialchars(trim($reclamation['prenom'] . ' ' . $reclamation['nom'])) ?><br><?= htmlspecialchars($reclamation['numero_client']) ?></td>
                    <td><?= htmlspecialchars($types[$reclamation['type_reclamation']] ?? $reclamation['type_reclamation']) ?></td>
                    <td><?= htmlspecialchars($reclamation['numero_facture'] ?? '—') ?></td>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($reclamation['created_at']))) ?></td>
                    <td><?= htmlspecialchars($statuts[$reclamation['statut']] ?? $reclamation['statut']) ?></td>
                    <td><a class="anomalie-detail-link" href="<?= BASE_URL ?>Reclamation/detail/<?= (int) $reclamation['id_reclamation'] ?>">Détail</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>
