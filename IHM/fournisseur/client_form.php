<?php
$titreFournisseur = $bouton_formulaire;
$sousTitreFournisseur = 'Renseignez les informations du client.';
include ROOT . 'IHM/layouts/fournisseur_header.php';
?>

<div class="formulaire-client-page">
    <a href="<?= BASE_URL ?>Client/index" class="retour-clients">&larr; Retour à la liste des clients</a>
    <div class="formulaire-client-carte">
        <h2><?= htmlspecialchars($bouton_formulaire) ?></h2>

        <?php if (isset($erreur)){ ?>
            <div class="erreur-client"><?= htmlspecialchars($erreur) ?></div>
        <?php } ?>

        <form method="post" action="<?= BASE_URL . $action_formulaire ?>" class="formulaire-client fournisseur-form">
            <div class="champ-client">
                <label for="nom">Nom</label>
                <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($client_actuel['nom'] ?? '') ?>" required>
            </div>
            <div class="champ-client">
                <label for="prenom">Prénom</label>
                <input type="text" id="prenom" name="prenom" value="<?= htmlspecialchars($client_actuel['prenom'] ?? '') ?>" required>
            </div>
            <div class="champ-client">
                <label for="cin">CIN</label>
                <input type="text" id="cin" name="cin" value="<?= htmlspecialchars($client_actuel['cin'] ?? '') ?>" minlength="5" maxlength="8" pattern="[a-zA-Z]{1,2}[0-9]{4,6}" required>
            </div>
            <div class="champ-client">
                <label for="adresse">Adresse</label>
                <input type="text" id="adresse" name="adresse" value="<?= htmlspecialchars($client_actuel['adresse'] ?? '') ?>" required>
            </div>
            <div class="champ-client">
                <label for="telephone">Téléphone</label>
                <input type="tel" id="telephone" name="telephone" value="<?= htmlspecialchars($client_actuel['telephone'] ?? '') ?>" pattern="0[567][0-9]{8}" required>
            </div>
            <div class="champ-client">
                <label for="numero_compteur_eau">Numéro compteur eau</label>
                <input type="text" id="numero_compteur_eau" name="numero_compteur_eau" value="<?= htmlspecialchars($client_actuel['numero_compteur_eau'] ?? '') ?>" maxlength="20" placeholder="A1945678" required>
            </div>
            <div class="champ-client">
                <label for="numero_compteur_elec">Numéro compteur électricité</label>
                <input type="text" id="numero_compteur_elec" name="numero_compteur_elec" value="<?= htmlspecialchars($client_actuel['numero_compteur_elec'] ?? '') ?>" maxlength="20" placeholder="0102938475" required>
            </div>
            <div class="champ-client">
                <label for="index_initial_eau">Index initial eau</label>
                <input type="number" id="index_initial_eau" name="index_initial_eau" value="<?= htmlspecialchars($client_actuel['index_initial_eau'] ?? '') ?>" min="0" required>
            </div>
            <div class="champ-client">
                <label for="index_initial_elec">Index initial électricité</label>
                <input type="number" id="index_initial_elec" name="index_initial_elec" value="<?= htmlspecialchars($client_actuel['index_initial_elec'] ?? '') ?>" min="0" required>
            </div>

            <div class="actions-formulaire-client">
                <button type="submit" class="bouton-client"><?= htmlspecialchars($bouton_formulaire) ?></button>
                <button type="reset" class="bouton-annuler-client">Annuler</button>
            </div>
        </form>
    </div>
</div>

<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>