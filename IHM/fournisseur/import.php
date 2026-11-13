<?php
$titreFournisseur = 'Import annuel';
$sousTitreFournisseur = 'Consultez les lignes rejetées lors des imports annuels.';
$lignesRejetees = $lignesRejetees ?? [];
include ROOT . 'IHM/layouts/fournisseur_header.php';
?>
<section class="m1-page">
    <section class="card">
        <h2 class="card__title">Lignes rejetées</h2>
        <form class="m1-filters" method="get" action="<?= BASE_URL ?>Import/index">
            <label>
                <span>Année</span>
                <input type="number" name="annee" min="2000" max="2100" value="<?= isset($annee) ? (int) $annee : '' ?>">
            </label>
            <button class="m1-button" type="submit">Filtrer</button>
            <a class="m1-button m1-button--secondary" href="<?= BASE_URL ?>Import/index">Toutes les années</a>
        </form>

        <?php if (!$lignesRejetees): ?>
            <p class="m1-empty">Aucune ligne rejetée n’est disponible.</p>
        <?php else: ?>
            <div class="m1-table-wrap">
                <table class="m1-table">
                    <thead>
                        <tr><th>Année</th><th>Fichier</th><th>Ligne</th><th>Contenu</th><th>Motif</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lignesRejetees as $ligne): ?>
                            <tr>
                                <td><?= $ligne['annee'] === null ? '—' : (int) $ligne['annee'] ?></td>
                                <td><?= htmlspecialchars($ligne['nom_fichier']) ?></td>
                                <td><?= (int) $ligne['numero_ligne'] ?></td>
                                <td><?= htmlspecialchars($ligne['contenu_brut']) ?></td>
                                <td><?= htmlspecialchars($ligne['motif_rejet']) ?></td>
                                <td><?= htmlspecialchars($ligne['date_import']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>
