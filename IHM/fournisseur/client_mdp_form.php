<?php include ROOT . 'IHM/layouts/header.php'; ?>

<div class="formulaire-client-page">
    <a href="<?= BASE_URL . (isset($id_client) ? 'Client/index' : 'Auth/accueil') ?>" class="retour-clients">&larr; Retour</a>
    <div class="formulaire-client-carte">
        <h2><?= isset($titre_page) ? htmlspecialchars($titre_page) : 'Changer le mot de passe' ?></h2>

        <?php if (isset($erreur)): ?>
            <div class="erreur-client"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <form method="post" action="" class="formulaire-mdp">
            <div class="champ-client">
                <label for="nouveau_mdp">Nouveau mot de passe</label>
                <input type="password" id="nouveau_mdp" name="nouveau_mdp" required minlength="6" autocomplete="new-password">
            </div>
            <div class="actions-formulaire-client">
                <button type="submit" class="bouton-client">Valider le changement</button>
                <button type="reset" class="bouton-annuler-client">Annuler</button>
            </div>
        </form>
    </div>
</div>

<?php include ROOT . 'IHM/layouts/footer.php'; ?>