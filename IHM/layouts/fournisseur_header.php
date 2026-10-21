<?php
require_once ROOT . 'BD/Tableau_bord.php';

$infosFournisseur = bd_tableau_infos_utilisateur($_SESSION['id_utilisateur']) ?: [];
$email = $infosFournisseur['email'] ?? ($infosFournisseur['identifiant'] ?? '');
$initiales = strtoupper(substr($email !== '' ? $email : 'AD', 0, 2));
$pageFournisseur = $_GET['url'] ?? '';
$sectionFournisseur = strpos($pageFournisseur, 'Client/') === 0 ? 'Client' : 'Anomalie';
$titreFournisseur = $titreFournisseur ?? 'Tableau de bord';
$sousTitreFournisseur = $sousTitreFournisseur ?? '';

$iconesFournisseur = [
    'tableau' => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
    'clients' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'anomalie' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    'message' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'carte' => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
    'eclair' => '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
    'bouclier' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
    'calendrier' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    'reglages' => '<circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>',
    'journal' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4M10 9H8M16 13H8M16 17H8"/>',
    'sortie' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/>',
    'chevron' => '<path d="m9 18 6-6-6-6"/>',
];
$icoFournisseur = function ($nom, $classe = '') use ($iconesFournisseur) {
    return '<svg class="' . $classe . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $iconesFournisseur[$nom] . '</svg>';
};

$menuFournisseur = [
    ['Tableau de bord', BASE_URL . 'Auth/accueil', 'tableau', ''],
    ['Clients', BASE_URL . 'Client/index', 'clients', 'Client'],
    ['Anomalies', BASE_URL . 'Anomalie/index', 'anomalie', 'Anomalie'],
    ['Réclamations', '#', 'message', ''],
    ['Paiements', '#', 'carte', ''],
    ['Coupures', '#', 'eclair', ''],
    ['Sanctions', '#', 'bouclier', ''],
    ['Contrôle annuel', '#', 'calendrier', ''],
    ['Paramètres', '#', 'reglages', ''],
    ['Journal', '#', 'journal', ''],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titreFournisseur) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/accueil_fournisseur.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/Fournisseur.css?v=<?= filemtime(ROOT . 'assets/css/Fournisseur.css') ?>">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand__name brand__name--amanor">Amanor</div>
        </div>
        <ul class="nav">
            <?php foreach ($menuFournisseur as [$libelle, $lien, $icone, $section]): ?>
                <?php $actif = $section !== '' && $section === $sectionFournisseur; ?>
                <li>
                    <a class="nav__link<?= $actif ? ' is-active' : '' ?><?= $lien === '#' ? ' is-disabled' : '' ?>"
                       href="<?= htmlspecialchars($lien) ?>"<?= $lien === '#' ? ' aria-disabled="true"' : '' ?>>
                        <?= $icoFournisseur($icone) ?>
                        <span><?= htmlspecialchars($libelle) ?></span>
                        <?php if ($actif): ?><?= $icoFournisseur('chevron', 'nav__chevron') ?><?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="sidebar__user">
            <div class="avatar"><?= htmlspecialchars($initiales) ?></div>
            <div>
                <div class="sidebar__user-name"><?= htmlspecialchars($email) ?></div>
                <div class="sidebar__user-role">Administrateur</div>
            </div>
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <div>
                <h1 class="topbar__title"><?= htmlspecialchars($titreFournisseur) ?></h1>
                <?php if ($sousTitreFournisseur !== ''): ?>
                    <p class="topbar__subtitle"><?= htmlspecialchars($sousTitreFournisseur) ?></p>
                <?php endif; ?>
            </div>
            <div class="topbar__actions">
                <div class="user">
                    <div class="user__name">Administrateur</div>
                    <div class="user__email"><?= htmlspecialchars($email) ?></div>
                </div>
                <a class="logout" href="<?= BASE_URL ?>Auth/logout"><?= $icoFournisseur('sortie') ?> Se déconnecter</a>
            </div>
        </header>
        <main class="content">
