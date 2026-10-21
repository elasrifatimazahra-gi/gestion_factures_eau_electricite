<?php
/** @var mysqli_result|false $res    Résultat de listerAnomalies() */
/** @var string              $statut Filtre de statut actif ('' = tous) */

// Vue appelée par Anomalie_index() : utilise $res (résultat SQL) et $statut (filtre).
$pageCss = ['anomalies.css'];
include ROOT . 'IHM/layouts/header.php';

$moisFr = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
];
?>

<h2>Anomalies de consommation</h2>

<div class="filtres">
    <a href="<?= BASE_URL ?>Anomalie/index" class="<?= $statut === '' ? 'actif' : '' ?>">Toutes</a>
    <?php foreach (STATUTS_ANOMALIE_LIBELLES as $code => $libelle): ?>
        <a href="<?= BASE_URL ?>Anomalie/index?statut=<?= anomalie_e($code) ?>"
           class="<?= $statut === $code ? 'actif' : '' ?>">
            <?= anomalie_e($libelle) ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="conteneur-table">
    <table class="table-clients">
        <thead>
            <tr>
                <th>Client</th>
                <th>N° client</th>
                <th>Période</th>
                <th>Type</th>
                <th>Détectée le</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $vide = true;
        while ($res && ($a = mysqli_fetch_assoc($res))):
            $vide = false;
            $nomMois = isset($moisFr[(int) $a['mois']]) ? $moisFr[(int) $a['mois']] : $a['mois'];
            $typeLib = isset(TYPES_ANOMALIE_LIBELLES[$a['type']]) ? TYPES_ANOMALIE_LIBELLES[$a['type']] : $a['type'];
            $statLib = isset(STATUTS_ANOMALIE_LIBELLES[$a['statut']]) ? STATUTS_ANOMALIE_LIBELLES[$a['statut']] : $a['statut'];
        ?>
            <tr>
                <td><?= anomalie_e($a['prenom'] . ' ' . $a['nom']) ?></td>
                <td><?= anomalie_e($a['numero_client']) ?></td>
                <td><?= anomalie_e($nomMois . ' ' . $a['annee']) ?></td>
                <td><?= anomalie_e($typeLib) ?></td>
                <td><?= anomalie_e(date('d/m/Y H:i', strtotime($a['date_detection']))) ?></td>
                <td><span class="badge badge-<?= anomalie_e($a['statut']) ?>"><?= anomalie_e($statLib) ?></span></td>
                <td class="actions">
                    <a href="<?= BASE_URL ?>Anomalie/detail/<?= (int) $a['id_anomalie'] ?>" class="btn-edit">
                        <?= $a['statut'] === 'EN_ATTENTE' ? 'Traiter' : 'Voir le détail' ?>
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>

        <?php if ($vide): ?>
            <tr><td colspan="7" class="message-vide">Aucune anomalie trouvée.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include ROOT . 'IHM/layouts/footer.php'; ?>
