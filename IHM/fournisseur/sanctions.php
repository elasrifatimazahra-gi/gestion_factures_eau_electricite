<?php
$titreFournisseur = 'Sanctions';
$sousTitreFournisseur = 'Émettez et suivez les sanctions adressées aux clients.';
include ROOT . 'IHM/layouts/fournisseur_header.php';
$motifs = [
    'FRAUDE_SOUS_DECLARATION' => 'Fraude ou sous-déclaration',
    'RETARD_REPETE_3_MOIS' => 'Retards répétés (3 mois)',
    'NON_DECLARATION_PROLONGEE' => 'Non-déclaration prolongée',
    'AUTRE' => 'Autre',
];
$statuts = [
    'NOTIFIEE' => 'Notifiée', 'CONTESTEE' => 'Contestée', 'CONFIRMEE' => 'Confirmée',
    'REGULARISEE' => 'Régularisée', 'COUPURE_EFFECTUEE' => 'Coupure effectuée', 'ANNULEE' => 'Annulée',
];
?>
<?php if ($message !== ''): ?><div class="clients-message clients-message--success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($erreur !== ''): ?><div class="clients-message clients-message--error"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>
<section class="dash-carte anomalie-action-card">
    <h2>Émettre une sanction</h2>
    <form method="post" action="<?= BASE_URL ?>Sanction/creer" class="sanction-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
        <label class="field" for="id_client">
            <span class="field__label">Client</span>
            <select class="field__input" id="id_client" name="id_client" required>
                <option value="">Choisir un client</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= (int) $client['id_client'] ?>"><?= htmlspecialchars($client['numero_client'] . ' — ' . trim($client['prenom'] . ' ' . $client['nom'])) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field" for="motif">
            <span class="field__label">Motif</span>
            <select class="field__input" id="motif" name="motif" required>
                <?php foreach ($motifs as $code => $libelle): ?><option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($libelle) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="field" for="montant_energie_regularisee">
            <span class="field__label">Énergie régularisée (DH)</span>
            <input class="field__input" id="montant_energie_regularisee" name="montant_energie_regularisee" type="number" min="0" step="0.01" value="0" required>
        </label>
        <label class="field" for="penalite_fraude">
            <span class="field__label">Pénalité (DH)</span>
            <input class="field__input" id="penalite_fraude" name="penalite_fraude" type="number" min="0" step="0.01" value="2000" required>
        </label>
        <label class="field" for="frais_retablissement">
            <span class="field__label">Frais de rétablissement (DH)</span>
            <input class="field__input" id="frais_retablissement" name="frais_retablissement" type="number" min="0" step="0.01" value="0" required>
        </label>
        <label class="field" for="date_limite_paiement">
            <span class="field__label">Date limite de paiement</span>
            <input class="field__input" id="date_limite_paiement" name="date_limite_paiement" type="date" min="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
        </label>
        <label class="field" for="date_coupure_programmee">
            <span class="field__label">Date de coupure programmée</span>
            <input class="field__input" id="date_coupure_programmee" name="date_coupure_programmee" type="date" min="<?= htmlspecialchars(date('Y-m-d', strtotime('+1 day'))) ?>" required>
        </label>
        <div class="sanction-form__actions">
            <button type="submit" class="btn btn--primary">Émettre et notifier</button>
        </div>
    </form>
</section>
<section class="dash-carte fournisseur-table-card">
    <div class="clients-table-scroll">
        <table class="dash-tableau">
            <thead><tr><th>Référence</th><th>Client</th><th>Motif</th><th>Montant</th><th>Limite</th><th>Coupure prévue</th><th>Statut</th><th>Décision</th></tr></thead>
            <tbody>
            <?php if (!$sanctions): ?>
                <tr><td class="vide" colspan="8">Aucune sanction enregistrée.</td></tr>
            <?php else: foreach ($sanctions as $sanction): ?>
                <tr>
                    <td><?= htmlspecialchars($sanction['reference_sanction']) ?></td>
                    <td><?= htmlspecialchars(trim($sanction['prenom'] . ' ' . $sanction['nom'])) ?><br><?= htmlspecialchars($sanction['numero_client']) ?></td>
                    <td><?= htmlspecialchars($motifs[$sanction['motif']] ?? $sanction['motif']) ?></td>
                    <td><?= number_format((float) $sanction['montant_total'], 2, ',', ' ') ?> DH</td>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($sanction['date_limite_paiement']))) ?></td>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($sanction['date_coupure_programmee']))) ?></td>
                    <td><?= htmlspecialchars($statuts[$sanction['statut']] ?? $sanction['statut']) ?></td>
                    <td>
                        <?php if (in_array($sanction['statut'], ['NOTIFIEE', 'CONTESTEE'], true)): ?>
                            <form method="post" action="<?= BASE_URL ?>Sanction/decider/<?= (int) $sanction['id_sanction'] ?>" style="display:flex;gap:6px;flex-wrap:wrap">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
                                <button type="submit" name="decision" value="confirmer" class="btn btn--primary">Confirmer</button>
                                <button type="submit" name="decision" value="annuler" class="btn">Annuler</button>
                            </form>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>
