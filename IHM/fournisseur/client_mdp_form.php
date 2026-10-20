<?php include ROOT . 'IHM/layouts/header.php'; ?>

<center>
    <!-- Le titre change automatiquement selon la personne qui modifie le mot de passe -->
    <h2><?= isset($titre_page) ? htmlspecialchars($titre_page) : 'Changer le mot de passe' ?></h2>
    <?php if (isset($erreur)): ?>
        <div style="color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
            <?= $erreur; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <table>
            <tr>
                <td>Nouveau mot de passe :</td>
                <td><input type="password" name="nouveau_mdp" required minlength="6"></td>
            </tr>
            <tr>
                <td><input type="submit" value="Valider le changement"></td>
                <td><input type="reset" value="Annuler"></td>
            </tr>
        </table>
    </form>
</center>

<?php include ROOT . 'IHM/layouts/footer.php'; ?>