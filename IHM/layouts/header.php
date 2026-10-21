<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYGEL - Eau & Électricité</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= filemtime(ROOT . 'assets/css/style.css') ?>">
</head>
<body>

<div class="app-wrapper">

    <?php
// On vérifie le rôle de l'utilisateur connecté pour afficher le bon menu
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'FOURNISSEUR') {
        include ROOT . 'IHM/layouts/sidebar_fournisseur.php';
    } elseif ($_SESSION['role'] === 'CLIENT') {
        include ROOT . 'IHM/layouts/sidebar_client.php';
    }
}
?>

    <div class="main-content">
        
        <?php if(isset($_SESSION['message'])): ?>
            <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <?= $_SESSION['message']; ?>
                <?php unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>