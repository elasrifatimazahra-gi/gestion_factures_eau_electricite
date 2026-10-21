<?php
$email = $infosFournisseur['email'] ?? ($infosFournisseur['identifiant'] ?? '');
$initiales = strtoupper(substr($email !== '' ? $email : 'AD', 0, 2));
$icones = [
    'goutte' => '<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>',
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
    'cloche' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    'sortie' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/>',
    'chevron' => '<path d="m9 18 6-6-6-6"/>',
];
$ico = function ($nom, $classe = '') use ($icones) {
    return '<svg class="' . $classe . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icones[$nom] . '</svg>';
};
$menu = [
    ['Tableau de bord', BASE_URL . 'Auth/accueil', 'tableau', false, true],
    ['Clients', BASE_URL . 'Client/index', 'clients', true, true],
    ['Anomalies', BASE_URL . 'Anomalie/index', 'anomalie', false, true],
    ['Réclamations', '#', 'message', false, false],
    ['Paiements', '#', 'carte', false, false],
    ['Coupures', '#', 'eclair', false, false],
    ['Sanctions', '#', 'bouclier', false, false],
    ['Contrôle annuel', '#', 'calendrier', false, false],
    ['Paramètres', '#', 'reglages', false, false],
    ['Journal', '#', 'journal', false, false],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestion des clients</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/accueil_fournisseur.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/Fournisseur.css?v=<?= filemtime(ROOT . 'assets/css/Fournisseur.css') ?>">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand__logo"><?= $ico('goutte') ?></div>
            <div>
                <div class="brand__name">Portail</div>
                <div class="brand__tag">Eau &amp; Électricité</div>
            </div>
        </div>
        <div class="sidebar__section">Espace fournisseur</div>
        <ul class="nav">
            <?php foreach ($menu as [$libelle, $lien, $icone, $actif, $dispo]): ?>
                <li>
                    <a class="nav__link<?= $actif ? ' is-active' : '' ?><?= $dispo ? '' : ' is-disabled' ?>"
                       href="<?= htmlspecialchars($lien) ?>"<?= $dispo ? '' : ' aria-disabled="true"' ?>>
                        <?= $ico($icone) ?>
                        <span><?= htmlspecialchars($libelle) ?></span>
                        <?php if ($actif): ?><?= $ico('chevron', 'nav__chevron') ?><?php endif; ?>
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
                <h1 class="topbar__title">Gestion des clients</h1>
                <p class="topbar__subtitle">Consultez et gérez les comptes clients.</p>
            </div>
            <div class="topbar__actions">
                <div class="user">
                    <div class="user__name">Administrateur</div>
                    <div class="user__email"><?= htmlspecialchars($email) ?></div>
                </div>
                <a class="logout" href="<?= BASE_URL ?>Auth/logout"><?= $ico('sortie') ?> Se déconnecter</a>
            </div>
        </header>

        <main class="content clients-content">
            <form method="GET" action="<?= BASE_URL ?>Client/index" class="clients-search">
                <input type="text" name="search" placeholder="Rechercher un client par nom, prénom ou CIN..."
                       value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn btn--primary">Rechercher</button>
            </form>

            <div class="clients-actions">
                <a href="<?= BASE_URL ?>Client/ajouter" class="btn btn--primary">+ Ajouter client</a>
            </div>

<?php if(isset($_SESSION['msg_succes'])){ ?>
     <div class="clients-message clients-message--success">
        <?= $_SESSION['msg_succes'] ?>
    </div>
        <?php unset($_SESSION['msg_succes']); //faire disparaitre le message de succes 
        } ?>

<?php if(isset($_SESSION['msg_erreur'])){ ?>
    <div class="clients-message clients-message--error">
        <?= htmlspecialchars($_SESSION['msg_erreur']) ?>
    </div>
    <?php unset($_SESSION['msg_erreur']); ?>
<?php } ?>

<!-- Tableau des clients -->
<section class="dash-carte clients-table-card">
    <div class="clients-table-scroll">
    <table class="dash-tableau clients-table">
        <thead>
            <tr>
                <th>Numéro client</th>
                <th>CIN</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Adresse</th>
                <th>Téléphone</th>
                <th>Statut du compte</th>
                <th>Numéro compteur eau</th>
                <th>Numéro compteur électricité</th>
                <th>Index initial eau</th>
                <th>Index initial électricité</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if(isset($res_clients) && mysqli_num_rows($res_clients) > 0) {
                while($client = mysqli_fetch_assoc($res_clients)) { 
            ?>
                <tr>
                    <td><?= htmlspecialchars($client['numero_client']) ?></td>
                    <td><?= htmlspecialchars($client['cin']) ?></td>
                    <td><?= htmlspecialchars($client['nom']) ?></td>
                    <td><?= htmlspecialchars($client['prenom']) ?></td>
                    <td><?= htmlspecialchars($client['adresse']) ?></td>
                    <td><?= htmlspecialchars($client['telephone']) ?></td>
                    <td>
                        <?php
                        if (!empty($client['est_bloque'])) {
                            echo 'Bloqué temporairement';
                        } else {
                            $statuts = [
                                'ACTIF' => 'Actif',
                                'BLOQUE_TENTATIVES' => 'Bloqué après tentatives',
                                'BLOQUE_GESTIONNAIRE' => 'Bloqué par gestionnaire',
                                'COUPE' => 'Coupé',
                            ];
                            echo htmlspecialchars($statuts[$client['statut_compte']] ?? $client['statut_compte']);
                        }
                        ?>
                    </td>
                    <td><?= htmlspecialchars($client['numero_compteur_eau']) ?></td>
                    <td><?= htmlspecialchars($client['numero_compteur_elec']) ?></td>
                    <td><?= htmlspecialchars($client['index_initial_eau']) ?></td>
                    <td><?= htmlspecialchars($client['index_initial_elec']) ?></td>
                    <td class="clients-row-actions">
                        <a href="<?= BASE_URL ?>Client/modifier/<?= htmlspecialchars($client['id_client']) ?>" class="clients-action clients-action--edit">Modifier</a>
                        <a href="<?= BASE_URL ?>Client/changerMdp/<?= htmlspecialchars($client['id_client']) ?>" class="clients-action clients-action--password">Changer mot de passe</a>
                        <?php if ($client['statut_compte'] !== 'ACTIF' || !empty($client['est_bloque'])) { ?>
                            <a href="<?= BASE_URL ?>Client/debloquer/<?= htmlspecialchars($client['cin']) ?>" class="clients-action clients-action--unlock">Débloquer</a>
                        <?php } ?>
                    </td>
                </tr>
            <?php 
                }
            } else {
                echo "<tr><td class='clients-empty' colspan='12'>Aucun client trouvé.</td></tr>";
            } 
            ?>
        </tbody>
    </table>
</section>
        </main>
    </div>
</div>
</body>
</html>