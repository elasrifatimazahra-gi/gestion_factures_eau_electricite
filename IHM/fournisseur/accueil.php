<?php
// Variables reçues de Auth_accueil() :
// $statistiquesClients, $dernieresFactures, $infosFournisseur

$email = $infosFournisseur['email'] ?? ($infosFournisseur['identifiant'] ?? '');
$initiales = strtoupper(substr($email !== '' ? $email : 'AD', 0, 2));

$icones = [
    'tableau'  => '<rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>',
    'clients'  => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'anomalie' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    'message'  => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'carte'    => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
    'eclair'   => '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
    'bouclier' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
    'calendrier' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
    'import'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>',
    'reglages' => '<circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>',
    'journal'  => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
    'cloche'   => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    'sortie'   => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/>',
    'chevron'  => '<path d="m9 18 6-6-6-6"/>',
];
$ico = function ($nom, $classe = '') use ($icones) {
    return '<svg class="' . $classe . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icones[$nom] . '</svg>';
};

// [libellé, lien, icône, actif, disponible]
$menu = [
    ['Tableau de bord', BASE_URL . 'Auth/accueil',  'tableau',    true,  true],
    ['Clients',         BASE_URL . 'Client/index',   'clients',    false, true],
    ['Anomalies',       BASE_URL . 'Anomalie/index', 'anomalie',   false, true],
    ['Réclamations',    '#',                         'message',    false, false],
    ['Paiements',       '#',                         'carte',      false, false],
    ['Coupures',        '#',                         'eclair',     false, false],
    ['Sanctions',       '#',                         'bouclier',   false, false],
    ['Contrôle annuel', '#',                         'calendrier', false, false],
    ['Paramètres',      '#',                         'reglages',   false, false],
    ['Journal',         '#',                         'journal',    false, false],
];

$statuts = [
    'IMPAYEE'   => ['Impayée',   'impayee'],
    'EN_RETARD' => ['En retard', 'retard'],
    'PAYEE'     => ['Payée',     'payee'],
    'ANNULEE'   => ['Annulée',   'annulee'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tableau de bord</title>
    <!-- Fournisseur.css = gabarit commun fournisseur (menu, barre du haut) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/Fournisseur.css?v=<?= filemtime(ROOT . 'assets/css/Fournisseur.css') ?>">
    <!-- accueil_fournisseur.css (Membre 1) = éléments propres à cette page -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/accueil_fournisseur.css?v=<?= filemtime(ROOT . 'assets/css/accueil_fournisseur.css') ?>">
</head>
<body>
<div class="app">

    <aside class="sidebar">
        <div class="brand">
            <div class="brand__name brand__name--amanor">Amanor</div>
        </div>

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
                <h1 class="topbar__title">Tableau de bord</h1>
                <p class="topbar__subtitle">Vue d'ensemble de l'activité de votre service.</p>
            </div>
            <div class="topbar__actions">
                <div class="lang">
                    <button type="button" class="lang__btn is-active">FR</button>
                    <button type="button" class="lang__btn">AR</button>
                </div>
                <button type="button" class="icon-btn" aria-label="Notifications">
                    <?= $ico('cloche') ?><span class="icon-btn__dot"></span>
                </button>
                <div class="user">
                    <div class="user__name">Administrateur</div>
                    <div class="user__email"><?= htmlspecialchars($email) ?></div>
                </div>
                <a class="logout" href="<?= BASE_URL ?>Auth/logout"><?= $ico('sortie') ?> Se déconnecter</a>
            </div>
        </header>

        <main class="content">

            <section class="clients-resume">
                <div class="clients-resume__entete">
                    <h2>Clients</h2>
                    <p>Recherchez, ajoutez et gérez les comptes clients.</p>
                </div>
                <div class="clients-resume__cartes">
                    <a class="clients-resume__carte" href="<?= BASE_URL ?>Client/index">
                        <span class="clients-resume__libelle">Total clients</span>
                        <span class="clients-resume__badge clients-resume__badge--bleu">+<?= (int) $statistiquesClients['nouveaux_mois'] ?> ce mois</span>
                        <strong><?= number_format((int) $statistiquesClients['total'], 0, ',', ' ') ?></strong>
                    </a>
                    <a class="clients-resume__carte" href="<?= BASE_URL ?>Client/index">
                        <span class="clients-resume__libelle">Comptes actifs</span>
                        <span class="clients-resume__badge clients-resume__badge--vert"><?= (int) $statistiquesClients['total'] > 0 ? number_format((int) $statistiquesClients['actifs'] * 100 / (int) $statistiquesClients['total'], 1, ',', ' ') : '0,0' ?> % du parc</span>
                        <strong><?= number_format((int) $statistiquesClients['actifs'], 0, ',', ' ') ?></strong>
                    </a>
                    <div class="clients-resume__carte">
                        <span class="clients-resume__libelle">En retard</span>
                        <span class="clients-resume__badge clients-resume__badge--ambre"><?= number_format((float) $statistiquesClients['montant_retard'], 2, ',', ' ') ?> DH</span>
                        <strong><?= number_format((int) $statistiquesClients['retards'], 0, ',', ' ') ?></strong>
                    </div>
                </div>
            </section>

            <!-- Dernières factures -->
            <section class="dash-carte">
                <div class="dash-entete">
                    <h2 class="dash-titre">Dernières factures</h2>
                </div>
                <div class="dash-tableau-conteneur">
                    <table class="dash-tableau">
                        <thead>
                            <tr>
                                <th>Facture</th>
                                <th>Client</th>
                                <th>Montant TTC</th>
                                <th>Échéance</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($dernieresFactures)): ?>
                            <tr><td class="vide" colspan="5">Aucune facture pour le moment.</td></tr>
                        <?php else: ?>
                            <?php foreach ($dernieresFactures as $f):
                                [$texteStatut, $classeStatut] = $statuts[$f['statut']] ?? [$f['statut'], 'annulee']; ?>
                                <tr>
                                    <td class="fort"><?= htmlspecialchars($f['numero_facture']) ?></td>
                                    <td><?= htmlspecialchars(trim($f['prenom'] . ' ' . $f['nom'])) ?></td>
                                    <td><?= number_format((float) $f['montant_ttc'], 2, ',', ' ') ?> DH</td>
                                    <td><?= date('d/m/Y', strtotime($f['date_echeance'])) ?></td>
                                    <td><span class="dash-badge dash-badge--<?= $classeStatut ?>"><?= htmlspecialchars($texteStatut) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
    </div>
</div>
</body>
</html>