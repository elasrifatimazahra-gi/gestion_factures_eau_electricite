<?php // Variables reçues du contrôleur : $erreur ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion fournisseur</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/auth.css">
</head>
<body class="auth-fournisseur">
<main class="auth-page">

    <section class="auth-visuel">
        <div>
            <div class="auth-marque-nom">Portail</div>
            <div class="auth-marque-sous">Eau &amp; Électricité</div>
        </div>

        <div class="auth-accroche">
            <span class="auth-surtitre">Votre espace en ligne</span>
            <h1>Votre<br>consommation,<br>en toute simplicité.</h1>
            <p>Déclarez vos index, consultez vos factures et suivez vos demandes depuis un espace unique et sécurisé.</p>
        </div>

        <div class="auth-service">Service disponible 24h/24 et 7j/7</div>
    </section>

    <section class="auth-formulaire">
        <div class="langues"><span class="actif">FR</span><span>AR</span></div>

        <div class="auth-bloc">
            <span class="auth-etiquette">Accès professionnel</span>
            <h2>Connexion fournisseur</h2>
            <p class="auth-sous-titre">Accédez à votre espace de gestion sécurisé.</p>

            <?php if ($erreur): ?>
                <div class="alerte" role="alert"><?= htmlspecialchars($erreur) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= BASE_URL ?>Auth/connecter">
                <input type="hidden" name="role" value="FOURNISSEUR">

                <div class="champ">
                    <label for="login">E-mail</label>
                    <input type="email" id="login" name="login" placeholder="nom@fournisseur.ma"
                           autocomplete="username" required autofocus>
                </div>

                <div class="champ">
                    <label for="mot_de_passe">Mot de passe</label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe" placeholder="Votre mot de passe"
                           autocomplete="current-password" required>
                </div>

                <button type="submit" class="bouton">Se connecter</button>
            </form>

            <div class="auth-pied">
                <a class="auth-lien" href="<?= BASE_URL ?>Auth/login_client" style="margin-top:0">Retour à l’espace client</a>
            </div>
        </div>
    </section>

</main>
<button type="button" class="aide" aria-label="Aide">?</button>
</body>
</html>
