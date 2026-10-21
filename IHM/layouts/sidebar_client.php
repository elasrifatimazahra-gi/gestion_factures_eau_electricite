<?php
// Barre latérale client : identique à celle de « Vue d'ensemble » (accueil.php)
$page = $_GET['url'] ?? '';

// Données du client, fournies par Consommation_declaration()
$client = $client ?? [];
$prenom = $client['prenom'] ?? '';
$nom = $client['nom'] ?? '';
$numero = $client['numero_client'] ?? '';
$initiales = mb_strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));

$icones = [
    'goutte'  => '<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>',
    'grille'  => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
    'jauge'   => '<path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/>',
    'facture' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
    'carte'   => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
    'message' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'cloche'  => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    'chevron' => '<path d="m9 18 6-6-6-6"/>',
];

// Même menu, même ordre, mêmes liens que accueil.php
// Les entrées sans lien (« # ») ne sont pas encore développées
$menu = [
    ["Vue d'ensemble",    BASE_URL . 'Auth/accueil',              'grille',  'Auth/accueil'],
    ['Ma déclaration',    BASE_URL . 'Consommation/declaration', 'jauge',   'Consommation/declaration'],
    ['Mes factures',      BASE_URL . 'Facture/liste',             'facture', 'Facture/liste'],
    ['Paiement en ligne', '#',                                    'carte',   ''],
    ['Mes réclamations',  '#',                                    'message', ''],
    ['Notifications',     '#',                                    'cloche',  ''],
];
?>
<aside class="sidebar">
    <div class="marque">
        <div class="marque-nom marque-nom--amanor">Amanor</div>
    </div>

    <div class="nav-titre">MON ESPACE</div>
    <nav>
        <?php foreach ($menu as [$libelle, $lien, $icone, $cle]): $actif = ($cle !== '' && $page === $cle); ?>
            <a class="nav-item<?= $actif ? ' actif' : '' ?>" href="<?= htmlspecialchars($lien) ?>">
                <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><?= $icones[$icone] ?></svg>
                <span><?= htmlspecialchars($libelle) ?></span>
                <?php if ($actif): ?><svg class="ico chevron" viewBox="0 0 24 24" aria-hidden="true"><?= $icones['chevron'] ?></svg><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="carte-user">
        <div class="avatar"><?= htmlspecialchars($initiales) ?></div>
        <div>
            <div class="user-nom"><?= htmlspecialchars(trim($prenom . ' ' . $nom)) ?></div>
            <div class="user-num"><?= htmlspecialchars($numero) ?></div>
        </div>
    </div>
</aside>