<?php // Variables reçues du contrôleur : $erreur ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion agent</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/auth.css?v=<?= filemtime(ROOT . 'assets/css/auth.css') ?>">
</head>
<body class="auth-fournisseur">
<main class="auth-page">
    <section class="auth-visuel">
        <div class="auth-marque-nom">Amanor</div>
        <div class="auth-accroche">
            <span class="auth-surtitre">Espace terrain</span>
            <h1>Vos relevés,<br>en toute sécurité.</h1>
            <p>Consultez votre tournée et enregistrez les relevés des contrats de votre quartier.</p>
        </div>
        <div class="auth-service">Service disponible 24h/24 et 7j/7</div>
    </section>
    <section class="auth-formulaire">
        <div class="auth-bloc">
            <span class="auth-etiquette">Accès professionnel</span>
            <h2>Connexion agent</h2>
            <p class="auth-sous-titre">Connectez-vous avec votre matricule et votre mot de passe.</p>
            <?php if ($erreur): ?>
                <div class="alerte" role="alert"><?= htmlspecialchars($erreur) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= BASE_URL ?>Auth/connecter">
                <input type="hidden" name="role" value="AGENT">
                <div class="champ">
                    <label for="login">Matricule</label>
                    <input type="text" id="login" name="login" autocomplete="username" required autofocus>
                </div>
                <div class="champ">
                    <label for="mot_de_passe">Mot de passe</label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe" autocomplete="current-password" required>
                </div>
                <button type="submit" class="bouton">Se connecter</button>
            </form>
            <div class="auth-pied">
                <a class="auth-lien" href="<?= BASE_URL ?>Auth/login_client">Retour à l’espace client</a>
            </div>
        </div>
    </section>
</main>
</body>
</html>
