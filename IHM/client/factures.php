<?php
// Variables reçues du contrôleur : $factures, $client
// NB : la barre latérale et la barre du haut sont dessinées ici pour que la page
// ressemble exactement à la maquette. Quand les layouts communs seront prêts,
// on les remplacera par des include.
$prenom = $client['prenom'] ?? '';
$nom = $client['nom'] ?? '';
$initiales = mb_strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));

$icones = [
    'goutte'   => '<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>',
    'grille'   => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
    'jauge'    => '<path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/>',
    'facture'  => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
    'carte'    => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
    'message'  => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'cloche'   => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    'sortie'   => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/>',
    'oeil'     => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
    'chevron'  => '<path d="m9 18 6-6-6-6"/>',
];
$ico = function ($nom, $classe = '') use ($icones) {
    return '<svg class="ico ' . $classe . '" viewBox="0 0 24 24" aria-hidden="true">' . $icones[$nom] . '</svg>';
};

$menu = [
    ["Vue d'ensemble",   BASE_URL . 'Auth/accueil',                'grille',  false],
    ['Ma déclaration',   BASE_URL . 'Consommation/declaration',   'jauge',   false],
    ['Mes factures',     BASE_URL . 'Facture/liste',              'facture', true],
    ['Paiement en ligne', '#',                                     'carte',   false],
    ['Mes réclamations', '#',                                      'message', false],
    ['Notifications',    '#',                                      'cloche',  false],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mes factures</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/factures.css">
</head>
<body>
<div class="appli">

    <aside class="sidebar">
        <div class="marque">
            <div class="marque-logo"><?= $ico('goutte') ?></div>
            <div>
                <div class="marque-nom">Portail</div>
                <div class="marque-sous">EAU &amp; ÉLECTRICITÉ</div>
            </div>
        </div>

        <div class="nav-titre">MON ESPACE</div>
        <nav>
            <?php foreach ($menu as [$libelle, $lien, $icone, $actif]): ?>
                <a class="nav-item<?= $actif ? ' actif' : '' ?>" href="<?= htmlspecialchars($lien) ?>">
                    <?= $ico($icone) ?>
                    <span><?= htmlspecialchars($libelle) ?></span>
                    <?php if ($actif): ?><?= $ico('chevron', 'chevron') ?><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="carte-user">
            <div class="avatar"><?= htmlspecialchars($initiales) ?></div>
            <div>
                <div class="user-nom"><?= htmlspecialchars(trim($prenom . ' ' . $nom)) ?></div>
                <div class="user-num"><?= htmlspecialchars($client['numero_client'] ?? '') ?></div>
            </div>
        </div>
    </aside>

    <div class="principal">
        <header class="topbar">
            <div class="statut-portail"><span class="point-vert"></span> Portail opérationnel</div>
            <div class="topbar-droite">
                <div class="langues"><span class="actif">FR</span><span>AR</span></div>
                <span class="cloche"><?= $ico('cloche') ?></span>
                <span class="separateur"></span>
                <a class="deconnexion" href="<?= BASE_URL ?>Auth/logout"><?= $ico('sortie') ?> Déconnexion</a>
            </div>
        </header>

        <main class="contenu">
            <h1>Mes factures</h1>
            <p class="sous-titre">Consultez, téléchargez et réglez vos factures.</p>

            <div class="onglets">
                <button type="button" class="onglet actif" data-filtre="toutes">Toutes</button>
                <button type="button" class="onglet" data-filtre="impayees">Impayées</button>
                <button type="button" class="onglet" data-filtre="payees">Payées</button>
                <button type="button" class="onglet" data-filtre="estimees">Estimées</button>
            </div>

            <div class="carte">
                <table>
                    <thead>
                        <tr>
                            <th>N° de facture</th>
                            <th>Période</th>
                            <th>Type</th>
                            <th>Montant TTC</th>
                            <th>Échéance</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($factures as $f): ?>
                        <?php [$classeBadge, $libelleBadge] = facture_badge($f); ?>
                        <tr data-groupes="<?= facture_groupes($f) ?>">
                            <td class="fort"><?= htmlspecialchars($f['numero_facture']) ?></td>
                            <td><?= facture_nom_mois($f['periode_mois']) . ' ' . (int) $f['periode_annee'] ?></td>
                            <td><?= facture_type_services($f) ?></td>
                            <td class="fort"><?= number_format($f['montant_ttc'], 2, ',', ' ') ?> DH</td>
                            <td><?= facture_date_courte($f['date_echeance']) ?></td>
                            <td><span class="badge badge-<?= $classeBadge ?>"><?= $libelleBadge ?></span></td>
                            <td><a class="oeil" href="<?= BASE_URL ?>Facture/detail/<?= (int) $f['id_facture'] ?>" title="Voir le détail"><?= $ico('oeil') ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                        <tr id="ligne-vide" style="display:<?= empty($factures) ? '' : 'none' ?>">
                            <td class="vide" colspan="7">Aucune facture pour le moment.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<button class="aide" type="button" title="Aide">?</button>

<script>
(function () {
    var onglets = document.querySelectorAll('.onglet');
    var lignes = document.querySelectorAll('tr[data-groupes]');
    var vide = document.getElementById('ligne-vide');
    onglets.forEach(function (onglet) {
        onglet.addEventListener('click', function () {
            onglets.forEach(function (o) { o.classList.remove('actif'); });
            onglet.classList.add('actif');
            var filtre = onglet.getAttribute('data-filtre');
            var visibles = 0;
            lignes.forEach(function (ligne) {
                var ok = ligne.getAttribute('data-groupes').split(' ').indexOf(filtre) !== -1;
                ligne.style.display = ok ? '' : 'none';
                if (ok) { visibles++; }
            });
            vide.style.display = visibles ? 'none' : '';
        });
    });
})();
</script>
</body>
</html>