
<?php
// Page « compte bloqué ».

$role = $role ?? 'CLIENT';
$statut = $statut ?? 'BLOQUE_TENTATIVES';
$duree = $duree ?? (defined('DUREE_BLOCAGE') ? DUREE_BLOCAGE : 15);

$lienRetour = BASE_URL . (
    $role === 'FOURNISSEUR'
        ? 'Auth/login_fournisseur'
        : 'Auth/login_client'
);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte bloqué</title>
</head>
<body>
    <?php if ($statut === 'BLOQUE_TENTATIVES'): ?>
        <h1>Votre compte est bloqué temporairement</h1>
        <p>
            Vous avez dépassé le nombre de tentatives de connexion autorisées.
            Veuillez réessayer dans <?= (int) $duree ?> minutes.
        </p>
    <?php else: ?>
        <h1>Votre compte est bloqué</h1>
        <p>
            L'accès à votre compte a été suspendu.
            Veuillez contacter le gestionnaire de votre compte.
        </p>
    <?php endif; ?>

    <a href="<?= htmlspecialchars($lienRetour, ENT_QUOTES, 'UTF-8') ?>">
        Retour à la connexion
    </a>
</body>
</html>
