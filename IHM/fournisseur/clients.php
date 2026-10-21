<?php
$titreFournisseur = 'Gestion des clients';
$sousTitreFournisseur = 'Consultez et gérez les comptes clients.';
include ROOT . 'IHM/layouts/fournisseur_header.php';
?>
<div class="clients-content">
            <form method="GET" action="<?= BASE_URL ?>Client/index" class="clients-search">
                <input type="text" name="search" placeholder="Rechercher un client par nom, prénom ou CIN..."
                       value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn--primary">Rechercher</button>
            </form>

            <div class="clients-actions">
                <a href="<?= BASE_URL ?>Client/ajouter" class="btn btn--primary">+ Ajouter client</a>
            </div>

<?php if(isset($_SESSION['msg_succes'])){ ?>
     <div class="clients-message clients-message--success">
        <?= $_SESSION['msg_succes'] ?>
    </div>
        <?php unset($_SESSION['msg_succes']); //faire disparaitre le message de succes 
        } ?>

<?php if(isset($_SESSION['msg_erreur'])){ ?>
    <div class="clients-message clients-message--error">
        <?= htmlspecialchars($_SESSION['msg_erreur']) ?>
    </div>
    <?php unset($_SESSION['msg_erreur']); ?>
<?php } ?>

<!-- Tableau des clients -->
<section class="dash-carte clients-table-card">
    <div class="clients-table-scroll">
    <table class="dash-tableau clients-table">
        <thead>
            <tr>
                <th>Numéro client</th>
                <th>CIN</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Adresse</th>
                <th>Téléphone</th>
                <th>Statut du compte</th>
                <th>Numéro compteur eau</th>
                <th>Numéro compteur électricité</th>
                <th>Index initial eau</th>
                <th>Index initial électricité</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if(isset($res_clients) && mysqli_num_rows($res_clients) > 0) {
                while($client = mysqli_fetch_assoc($res_clients)) { 
            ?>
                <tr>
                    <td><?= htmlspecialchars($client['numero_client']) ?></td>
                    <td><?= htmlspecialchars($client['cin']) ?></td>
                    <td><?= htmlspecialchars($client['nom']) ?></td>
                    <td><?= htmlspecialchars($client['prenom']) ?></td>
                    <td><?= htmlspecialchars($client['adresse']) ?></td>
                    <td><?= htmlspecialchars($client['telephone']) ?></td>
                    <td>
                        <?php
                        if (!empty($client['est_bloque'])) {
                            echo 'Bloqué temporairement';
                        } else {
                            $statuts = [
                                'ACTIF' => 'Actif',
                                'BLOQUE_TENTATIVES' => 'Bloqué après tentatives',
                                'BLOQUE_GESTIONNAIRE' => 'Bloqué par gestionnaire',
                                'COUPE' => 'Coupé',
                            ];
                            echo htmlspecialchars($statuts[$client['statut_compte']] ?? $client['statut_compte']);
                        }
                        ?>
                    </td>
                    <td><?= htmlspecialchars($client['numero_compteur_eau']) ?></td>
                    <td><?= htmlspecialchars($client['numero_compteur_elec']) ?></td>
                    <td><?= htmlspecialchars($client['index_initial_eau']) ?></td>
                    <td><?= htmlspecialchars($client['index_initial_elec']) ?></td>
                    <td class="clients-row-actions">
                        <a href="<?= BASE_URL ?>Client/modifier/<?= htmlspecialchars($client['id_client']) ?>" class="clients-action clients-action--edit">Modifier</a>
                        <a href="<?= BASE_URL ?>Client/changerMdp/<?= htmlspecialchars($client['id_client']) ?>" class="clients-action clients-action--password">Changer mot de passe</a>
                        <?php if ($client['statut_compte'] !== 'ACTIF' || !empty($client['est_bloque'])) { ?>
                            <a href="<?= BASE_URL ?>Client/debloquer/<?= htmlspecialchars($client['cin']) ?>" class="clients-action clients-action--unlock">Débloquer</a>
                        <?php } ?>
                    </td>
                </tr>
            <?php 
                }
            } else {
                echo "<tr><td class='clients-empty' colspan='12'>Aucun client trouvé.</td></tr>";
            } 
            ?>
        </tbody>
    </table>
</section>
<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>