<?php
$titreFournisseur = 'Contrôle annuel';
$sousTitreFournisseur = 'Validez les relevés des agents : conforme, fraude (régularisation) ou crédit.';
include ROOT . 'IHM/layouts/fournisseur_header.php';

$h = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
$libResultat = ['CONFORME' => 'Conforme', 'SOUS_DECLARATION' => 'Fraude (sous-déclaration)', 'SUR_DECLARATION' => 'Crédit (sur-déclaration)'];
$libStatut = ['EN_ATTENTE_VALIDATION' => 'En attente', 'VALIDE_PAR_FOURNISSEUR' => 'Validé', 'REJETE' => 'Rejeté', 'CONTESTE' => 'Contesté'];
$filtre = $_GET['statut'] ?? '';
?>

<?php if (!empty($erreur)): ?><div class="clients-message clients-message--error"><?= $h($erreur) ?></div><?php endif; ?>
<?php if (!empty($message)): ?><div class="clients-message clients-message--success"><?= $h($message) ?></div><?php endif; ?>

<form method="get" action="<?= BASE_URL ?>Controle/index" style="margin-bottom:12px">
    <label>Statut :
            <select name="statut" class="filtre-statut" onchange="this.form.submit()">
            <option value="">Tous</option>
            <?php foreach ($libStatut as $code => $lib): ?>
                <option value="<?= $code ?>"<?= $filtre === $code ? ' selected' : '' ?>><?= $lib ?></option>
            <?php endforeach; ?>
        </select>
    </label>
</form>

<section class="dash-carte fournisseur-table-card">
<div class="clients-table-scroll">
<table class="dash-tableau">
    <thead>
        <tr>
            <th>Client</th><th>Contrat</th><th>Année</th>
            <th>Eau (déclaré / réel)</th><th>Élec. (déclaré / réel)</th>
            <th>Écart</th><th>Résultat</th><th>Statut</th><th>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($controles as $c): ?>
        <tr>
            <td><?= $h(trim($c['prenom'] . ' ' . $c['nom'])) ?><br><small><?= $h($c['numero_client']) ?></small></td>
            <td><?= $h($c['numero_contrat'] ?? '—') ?></td>
            <td><?= (int) $c['annee'] ?></td>
            <td><?= (int) $c['conso_eau_declaree'] ?> / <?= (int) $c['conso_eau_reelle_agent'] ?> m³</td>
            <td><?= (int) $c['conso_elec_declaree'] ?> / <?= (int) $c['conso_elec_reelle_agent'] ?> kWh</td>
            <td>eau <?= (float) $c['ecart_eau_pct'] ?> %<br>élec. <?= (float) $c['ecart_elec_pct'] ?> %</td>
            <td><?= $h($libResultat[$c['resultat']] ?? $c['resultat']) ?></td>
            <td>
                <?= $h($libStatut[$c['statut_validation']] ?? $c['statut_validation']) ?>
                <?php if (!empty($c['numero_facture_regul'])): ?><br><small>Facture <?= $h($c['numero_facture_regul']) ?></small><?php endif; ?>
            </td>
            <td>
            <?php if ($c['statut_validation'] === 'EN_ATTENTE_VALIDATION'): ?>
                <form method="post" action="<?= BASE_URL ?>Controle/valider" style="display:flex;gap:6px;flex-wrap:wrap">
                    <input type="hidden" name="csrf_token" value="<?= $h(csrf_token_m1()) ?>">
                    <input type="hidden" name="id_controle" value="<?= (int) $c['id_controle'] ?>">
                     <button type="submit" name="decision" value="valider" class="btn btn--primary">Valider</button>
                    <button type="submit" name="decision" value="rejeter" class="btn btn--rejet">Rejeter</button>
                </form>
            <?php else: ?>—<?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$controles): ?>
        <tr><td class="vide" colspan="9">Aucun contrôle annuel trouvé.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
</section>
<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>