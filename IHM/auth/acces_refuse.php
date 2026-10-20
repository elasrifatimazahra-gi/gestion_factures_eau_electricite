<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accès refusé</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/auth.css">
</head>
<body>
<main class="page-erreur">
    <div class="auth-bloc">
        <span class="auth-etiquette">Erreur 403</span>
        <h2>Accès refusé</h2>
        <p class="auth-sous-titre">Vous n'avez pas le droit d'ouvrir cette page.</p>
        <a class="bouton bouton-lien" href="<?= BASE_URL ?>Auth/logout">Se déconnecter</a>
    </div>
</main>
</body>
</html>