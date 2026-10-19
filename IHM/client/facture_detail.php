<?php
// Variables reçues du contrôleur : $facture, $anterieuresImpayees
$dh = function ($montant) {
    return number_format($montant, 2, ',', ' ') . ' DH';
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture <?= htmlspecialchars($facture['numero_facture']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>

<?php include ROOT . 'IHM/layouts/sidebar_client.php'; ?>

<main class="contenu">
    <p><a href="<?= BASE_URL ?>Facture/liste">&larr; Retour à mes factures</a></p>

    <h1>Facture <?= htmlspecialchars($facture['numero_facture']) ?></h1>
    <p>
        Période : <strong><?= facture_nom_mois($facture['periode_mois']) . ' ' . (int) $facture['periode_annee'] ?></strong><br>
        Émise le <?= date('d/m/Y', strtotime($facture['date_emission'])) ?>,
        à payer avant le <strong><?= date('d/m/Y', strtotime($facture['date_echeance'])) ?></strong><br>
        Statut : <span class="badge badge-<?= strtolower($facture['statut']) ?>"><?= facture_libelle_statut($facture['statut']) ?></span>
    </p>

    <?php if ($anterieuresImpayees > 0): ?>
        <p class="alerte">
            Vous avez <?= $anterieuresImpayees ?> facture(s) plus ancienne(s) non payée(s).
            Elles doivent être réglées avant celle-ci.
        </p>
    <?php endif; ?>

    <table class="table">
        <thead>
            <tr><th>Service</th><th>Index début</th><th>Index fin</th><th>Consommation</th><th>Montant HT</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>Eau</td>
                <td><?= (int) $facture['index_eau_debut'] ?></td>
                <td><?= (int) $facture['index_eau_fin'] ?></td>
                <td><?= (int) $facture['conso_eau'] ?> m³</td>
                <td><?= $dh($facture['montant_ht_eau']) ?></td>
            </tr>
            <tr>
                <td>Électricité</td>
                <td><?= (int) $facture['index_elec_debut'] ?></td>
                <td><?= (int) $facture['index_elec_fin'] ?></td>
                <td><?= (int) $facture['conso_elec'] ?> kWh</td>
                <td><?= $dh($facture['montant_ht_elec']) ?></td>
            </tr>
        </tbody>
    </table>

    <table class="table">
        <tr><td>Total HT</td><td><?= $dh($facture['montant_ht']) ?></td></tr>
        <tr><td>TVA (<?= rtrim(rtrim($facture['taux_tva'], '0'), '.') ?> %)</td><td><?= $dh($facture['montant_tva']) ?></td></tr>
        <tr><td><strong>Total TTC</strong></td><td><strong><?= $dh($facture['montant_ttc']) ?></strong></td></tr>
        <?php if ($facture['credit_deduit'] > 0): ?>
            <tr><td>Crédit déduit</td><td>- <?= $dh($facture['credit_deduit']) ?></td></tr>
        <?php endif; ?>
        <?php if ($facture['penalite_appliquee']): ?>
            <tr><td>Pénalité de retard</td><td><?= $dh($facture['penalite_retard']) ?></td></tr>
        <?php endif; ?>
        <tr><td><strong>Total à payer</strong></td><td><strong><?= $dh($facture['montant_total_a_payer']) ?></strong></td></tr>
        <tr><td>Déjà payé</td><td><?= $dh($facture['montant_paye']) ?></td></tr>
        <tr><td><strong>Reste à payer</strong></td><td><strong><?= $dh($facture['reste_a_payer']) ?></strong></td></tr>
    </table>

    <?php if (!empty($facture['photo_eau_facture']) || !empty($facture['photo_elec_facture'])): ?>
        <h2>Photos des compteurs</h2>
        <?php if (!empty($facture['photo_eau_facture'])): ?>
            <img src="<?= BASE_URL . htmlspecialchars($facture['photo_eau_facture']) ?>" alt="Compteur d'eau" style="max-width:240px">
        <?php endif; ?>
        <?php if (!empty($facture['photo_elec_facture'])): ?>
            <img src="<?= BASE_URL . htmlspecialchars($facture['photo_elec_facture']) ?>" alt="Compteur d'électricité" style="max-width:240px">
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>