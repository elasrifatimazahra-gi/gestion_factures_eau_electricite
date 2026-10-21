<?php // Variables reçues du contrôleur : $erreur ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion client</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/auth.css?v=<?= filemtime(ROOT . 'assets/css/auth.css') ?>">
</head>
<body class="auth-client">
<main class="auth-page">

    <section class="auth-visuel">
        <div aria-hidden="true"></div>

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
            <span class="auth-etiquette">Espace client</span>
            <h2>Bienvenue chez<br><span class="auth-bienvenue-marque">Amanor</span></h2>
            <p class="auth-sous-titre">Connectez-vous pour accéder à votre espace personnel.</p>

            <?php if ($erreur): ?>
                <div class="alerte" role="alert"><?= htmlspecialchars($erreur) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= BASE_URL ?>Auth/connecter">
                <input type="hidden" name="role" value="CLIENT">

                <div class="champ">
                    <label for="login">Numéro client</label>
                    <input type="text" id="login" name="login" placeholder="ex. CLT-2026-000101"
                           autocomplete="username" required autofocus>
                </div>

                <div class="champ">
                    <label for="mot_de_passe">Mot de passe</label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe" placeholder="Votre mot de passe"
                           autocomplete="current-password" required>
                </div>

                <button type="submit" class="bouton">Se connecter</button>
            </form>

            <p class="auth-pied">Pas encore client ? <strong>Rendez-vous dans votre agence.</strong></p>
            <div style="text-align:center">
                <a class="auth-lien" href="<?= BASE_URL ?>Auth/login_fournisseur">Connexion fournisseur</a>
            </div>
            <div class="auth-copyright">© <?= date('Y') ?> Amanor · Aide et confidentialité</div>
        </div>
    </section>

</main>
<button type="button" class="aide" aria-label="Aide">?</button>
</body>
</html>
