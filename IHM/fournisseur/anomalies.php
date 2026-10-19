<?php
require_once __DIR__ . "/../../BD/Anomalie.php";

$res = listerAnomalies();

$mois = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
         7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];
?>
<table>
    <thead>
        <tr>
            <th>Client</th>
            <th>Mois</th>
            <th>Type</th>
            <th>Statut</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php while ($res && $a = mysqli_fetch_assoc($res)) {
        $nomMois = isset($mois[(int) $a['mois']]) ? $mois[(int) $a['mois']] : $a['mois'];
    ?>
        <tr>
            <td><?php echo htmlspecialchars($a['prenom'] . ' ' . $a['nom']); ?></td>
            <td><?php echo htmlspecialchars($nomMois . ' ' . $a['annee']); ?></td>
            <td><?php echo htmlspecialchars($a['type']); ?></td>
            <td><?php echo $a['statut'] === 'ouverte' ? 'Ouverte' : 'Traitée'; ?></td>
            <td><a href="anomalie_detail.php?id=<?php echo (int) $a['id_anomalie']; ?>">Voir le détail</a></td>
        </tr>
    <?php } ?>
    </tbody>
</table>