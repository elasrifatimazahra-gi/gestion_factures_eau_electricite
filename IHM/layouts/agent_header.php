<?php
$agentPageTitle = $agentPageTitle ?? 'Espace agent';
$agentPageSubtitle = $agentPageSubtitle ?? '';
$agentPageActive = $agentPageActive ?? 'tournee';

require_once ROOT . 'BD/Agent.php';
$agentProfil = bd_agent_par_utilisateur((int) ($_SESSION['id_utilisateur'] ?? 0)) ?: [];
$agentPrenom = trim((string) ($agentProfil['prenom'] ?? ''));
$agentNom = trim((string) ($agentProfil['nom'] ?? ''));
$agentNomComplet = trim($agentPrenom . ' ' . $agentNom);
$agentNomComplet = $agentNomComplet !== '' ? $agentNomComplet : 'Agent terrain';
$agentMatricule = (string) ($agentProfil['matricule'] ?? '');
$agentInitiales = mb_strtoupper(mb_substr($agentPrenom, 0, 1) . mb_substr($agentNom, 0, 1));
$agentInitiales = $agentInitiales !== '' ? $agentInitiales : 'AG';

$agentIcons = [
    'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
    'route' => '<path d="M4 20 9 4l6 16 5-16"/><circle cx="4" cy="20" r="1.5"/><circle cx="9" cy="4" r="1.5"/><circle cx="15" cy="20" r="1.5"/><circle cx="20" cy="4" r="1.5"/>',
    'gauge' => '<path d="M12 14l4-4"/><path d="M3.3 19a10 10 0 1 1 17.4 0"/><path d="M12 13v.1"/>',
    'check' => '<path d="M9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
    'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
    'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/>',
    'logout' => '<path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>',
    'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
    'water' => '<path d="M12 3c-2.6 4-6.5 7.4-6.5 11.5a6.5 6.5 0 0 0 13 0C18.5 10.4 14.6 7 12 3Z"/><path d="M9 16a3 3 0 0 0 3 2"/>',
    'bolt' => '<path d="m13 2-3 8h7l-6 12 1-9H5l8-11Z"/>',
];
$agentIcon = static function ($name, $class = '') use ($agentIcons) {
    return '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($agentIcons[$name] ?? '')
        . '</svg>';
};
$agentNavigation = [
    ['Ma tournée', 'Agent/accueil', 'tournee', 'grid'],
    ['Saisie mensuelle', 'Consommation/saisie_mensuelle', 'releve', 'gauge'],
    ['Contrôle annuel', 'Controle/saisie_controle', 'controle', 'check'],
    ['Import annuel', 'Consommation/import_annuel', 'import', 'upload'],
    ['Historique', 'Agent/historique', 'historique', 'history'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($agentPageTitle, ENT_QUOTES, 'UTF-8') ?> · Amanor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/Agent.css?v=<?= filemtime(ROOT . 'assets/css/Agent.css') ?>">
</head>
<body>
<div class="agent-shell">
    <aside class="agent-sidebar">
        <a class="agent-brand" href="<?= BASE_URL ?>Agent/accueil">
            <span class="agent-brand__mark">A</span>
            <span>
                <span class="agent-brand__name">AMANOR</span>
                <span class="agent-brand__caption">Gestion des services</span>
            </span>
        </a>
        <div class="agent-sidebar__label">ESPACE TERRAIN</div>
        <nav class="agent-nav" aria-label="Navigation agent">
            <?php foreach ($agentNavigation as [$libelle, $route, $section, $icone]): ?>
                <a class="<?= $agentPageActive === $section ? 'is-active' : '' ?>" href="<?= BASE_URL . htmlspecialchars($route, ENT_QUOTES, 'UTF-8') ?>"<?= $agentPageActive === $section ? ' aria-current="page"' : '' ?>>
                    <?= $agentIcon($icone) ?>
                    <span><?= htmlspecialchars($libelle, ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="agent-sidebar__bottom">
            <div class="agent-profile">
                <span class="agent-avatar"><?= htmlspecialchars($agentInitiales, ENT_QUOTES, 'UTF-8') ?></span>
                <span>
                    <span class="agent-profile__name"><?= htmlspecialchars($agentNomComplet, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="agent-profile__meta"><?= htmlspecialchars($agentMatricule !== '' ? 'Matricule ' . $agentMatricule : 'Agent terrain', ENT_QUOTES, 'UTF-8') ?></span>
                </span>
            </div>
        </div>
    </aside>

    <div class="agent-main">
        <header class="agent-topbar">
            <div class="agent-topbar__context"><span class="agent-live-dot"></span> Portail terrain <span aria-hidden="true">·</span> Connecté</div>
            <div class="agent-topbar__right">
                <span class="agent-date"><?= $agentIcon('calendar') ?> <?= date('d/m/Y') ?></span>
                <a class="agent-logout" href="<?= BASE_URL ?>Auth/logout"><?= $agentIcon('logout') ?> Déconnexion</a>
            </div>
        </header>

        <main class="agent-content">
            <header class="agent-page-heading">
                <div>
                    <p class="agent-eyebrow">AMANOR · ESPACE AGENT</p>
                    <h1><?= htmlspecialchars($agentPageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                    <?php if ($agentPageSubtitle !== ''): ?>
                        <p><?= htmlspecialchars($agentPageSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
                <span class="agent-page-heading__badge"><?= $agentIcon('route') ?> Agent de terrain</span>
            </header>

            <?php if (!empty($erreur)): ?>
                <p class="agent-alert agent-alert--error" role="alert"><?= htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php if (!empty($message)): ?>
                <p class="agent-alert agent-alert--success" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
