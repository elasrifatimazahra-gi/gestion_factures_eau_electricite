
<?php
$titreFournisseur = 'Anomalies';
$sousTitreFournisseur = 'Consultez et traitez les anomalies de consommation.';
include ROOT . 'IHM/layouts/fournisseur_header.php';

$mois = [
    1 => 'Janvier',
    2 => 'Février',
    3 => 'Mars',
    4 => 'Avril',
    5 => 'Mai',
    6 => 'Juin',
    7 => 'Juillet',
    8 => 'Août',
    9 => 'Septembre',
    10 => 'Octobre',
    11 => 'Novembre',
    12 => 'Décembre'
];
?>

<?php if (isset($_SESSION['erreur'])): ?>
    <div class="clients-message clients-message--error"><?= htmlspecialchars($_SESSION['erreur']) ?></div>
    <?php unset($_SESSION['erreur']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['message'])): ?>
    <div class="clients-message clients-message--success"><?= htmlspecialchars($_SESSION['message']) ?></div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<section class="dash-carte fournisseur-table-card">
<div class="clients-table-scroll">
<table class="dash-tableau">
    <thead>
        <tr>
            <th>Client</th>
            <th>Mois</th>
            <th>Type</th>
            <th>Statut</th>
            <th>Actions</th>
        </tr>
    </thead>

    <tbody>
        <?php
        $vide = true;

        while ($res && ($a = mysqli_fetch_assoc($res))) {
            $vide = false;

            $numMois = (int) $a['mois'];
            $nomMois = $mois[$numMois] ?? $a['mois'];

            $statuts = [
                'EN_ATTENTE' => 'En attente',
                'CORRIGEE' => 'Corrigée',
                'VALIDEE_FORCEE' => 'Validée de force',
                'REJETEE' => 'Rejetée'
            ];

            $statut = $statuts[$a['statut']] ?? $a['statut'];
        ?>

            <tr>
                <td>
                    <?= htmlspecialchars(
                        $a['prenom'] . ' ' . $a['nom'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </td>

                <td>
                    <?= htmlspecialchars(
                        $nomMois . ' ' . $a['annee'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </td>

                <td>
                    <?= htmlspecialchars(
                        $a['type'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </td>

                <td>
                    <?= htmlspecialchars(
                        $statut,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </td>

                <td>
                    <a class="anomalie-detail-link" href="<?= BASE_URL ?>Anomalie/detail/<?= (int) $a['id_anomalie'] ?>">
                        Voir le détail
                    </a>
                </td>
            </tr>

        <?php } ?>

        <?php if ($vide): ?>
            <tr>
                <td class="vide" colspan="5">Aucune anomalie trouvée.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
</div>
</section>
<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>
