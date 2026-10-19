<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion client</title>
</head>
<body>
    <h1>Bienvenue</h1>

    <?php if ($erreur): ?>
        <p style="color:red"><?= htmlspecialchars($erreur) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>Auth/connecter">
        <input type="hidden" name="role" value="CLIENT">

        <label>Numéro client</label><br>
        <input type="text" name="login" required><br><br>

        <label>Mot de passe</label><br>
        <input type="password" name="mot_de_passe" required><br><br>

        <button type="submit">Se connecter</button>
    </form>
</body>
</html>