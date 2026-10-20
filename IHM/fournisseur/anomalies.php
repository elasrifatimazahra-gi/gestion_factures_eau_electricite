```php
<?php
require_once __DIR__ . "/../../BD/Anomalie.php";

$res = listerAnomalies();

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

<table>
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
                    <a href="anomalie_detail.php?id=<?= (int) $a['id_anomalie'] ?>">
                        Voir le détail
                    </a>
                </td>
            </tr>

        <?php } ?>

        <?php if ($vide): ?>
            <tr>
                <td colspan="5">Aucune anomalie trouvée.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
