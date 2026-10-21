<?php include ROOT . 'IHM/layouts/header.php'; ?>

<div class="recherche-client">
    <h2>Gestion des clients</h2>
    
    <!-- Formulaire de recherche -->
    <form method="GET" action="<?= BASE_URL ?>Client/index" class="barre-recherche">
        <input type="text" name="search" placeholder="Rechercher un client par nom, prénom ou CIN..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
        <button type="submit" class="btn-submit">Rechercher</button>
    </form>
</div>

<!-- Bouton Ajouter -->
<div style="margin-bottom: 20px;">
    <a href="<?= BASE_URL ?>Client/ajouter" class="btn-submit">+ Ajouter client</a>
</div>

<?php if(isset($_SESSION['msg_succes'])){ ?>
     <div style="color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
        <?= $_SESSION['msg_succes'] ?>
    </div>
        <?php unset($_SESSION['msg_succes']); //faire disparaitre le message de succes 
        } ?>


<!-- Tableau des clients -->
<div class="conteneur-table">
    <table class="table-clients">
        <thead>
            <tr>
                <th>Numéro client</th>
                <th>CIN</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Adresse</th>
                <th>Téléphone</th>
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
                    <td><?= htmlspecialchars($client['numero_compteur_eau']) ?></td>
                    <td><?= htmlspecialchars($client['numero_compteur_elec']) ?></td>
                    <td><?= htmlspecialchars($client['index_initial_eau']) ?></td>
                    <td><?= htmlspecialchars($client['index_initial_elec']) ?></td>
                    <td class="actions">
                        <a href="<?= BASE_URL ?>Client/modifier/<?= htmlspecialchars($client['id_client']) ?>" class="btn-edit">Modifier</a>
                        <a href="<?= BASE_URL ?>Client/changerMdp/<?= htmlspecialchars($client['id_client']) ?>" class="btn-mdp">Changer mot de passe</a>
                        <a href="<?= BASE_URL ?>Client/debloquer/<?= htmlspecialchars($client['cin']) ?>" class="btn-debloquer">Débloquer</a>
                    </td>
                </tr>
            <?php 
                }
            } else {
                echo "<tr><td colspan='11' class='message-vide'>Aucun client trouvé.</td></tr>";
            } 
            ?>
        </tbody>
    </table>
</div>

<?php include ROOT . 'IHM/layouts/footer.php'; ?>