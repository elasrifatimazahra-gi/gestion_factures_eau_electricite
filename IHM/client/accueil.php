<?php
// Variables reçues de Auth_accueil() :
// $client, $solde, $echeance, $nbImpayees, $etatSaisie, $dernierJour, $mois,
// $historique, $dernierEau, $dernierElec, $variationEau, $variationElec,
// $estimationTtc, $dernieresFacturesClient

$prenom = $client['prenom'] ?? '';
$nom = $client['nom'] ?? '';
$initiales = mb_strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));

$jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$moisFr = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
           'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$moisCourt = [1 => 'Janv.', 'Févr.', 'Mars', 'Avr.', 'Mai', 'Juin',
              'Juil.', 'Août', 'Sept.', 'Oct.', 'Nov.', 'Déc.'];
$dateDuJour = $jours[(int) date('w')] . ' ' . date('j') . ' ' . $moisFr[(int) date('n')];

$dh = function ($v) { return number_format((float) $v, 2, ',', ' '); };
$dateCourte = function ($d) use ($moisCourt) {
    $t = strtotime($d);
    return date('d', $t) . ' ' . mb_strtolower($moisCourt[(int) date('n', $t)]) . ' ' . date('Y', $t);
};

$statuts = [
    'IMPAYEE'   => ['Impayée',   'badge-impayee'],
    'EN_RETARD' => ['En retard', 'badge-retard'],
    'PAYEE'     => ['Payée',     'badge-payee'],
    'ANNULEE'   => ['Annulée',   'badge-annulee'],
];

/* ---------- Icônes ---------- */
$icones = [
    'goutte'   => '<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>',
    'grille'   => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
    'jauge'    => '<path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/>',
    'facture'  => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
    'carte'    => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
    'message'  => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'cloche'   => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    'sortie'   => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/>',
    'chevron'  => '<path d="m9 18 6-6-6-6"/>',
    'calendrier' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
    'eclair'   => '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
    'oeil'     => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
    'tendance' => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
];
$ico = function ($nom, $classe = '') use ($icones) {
    return '<svg class="ico ' . $classe . '" viewBox="0 0 24 24" aria-hidden="true">' . $icones[$nom] . '</svg>';
};

$menu = [
    ["Vue d'ensemble",    BASE_URL . 'Auth/accueil',              'grille',  true],
    ['Ma déclaration',    BASE_URL . 'Consommation/declaration', 'jauge',   false],
    ['Mes factures',      BASE_URL . 'Facture/liste',             'facture', false],
    ['Paiement en ligne', '#',                                    'carte',   false],
    ['Mes réclamations',  '#',                                    'message', false],
    ['Notifications',     '#',                                    'cloche',  false],
];

/* ---------- Variation « par rapport au mois dernier » ---------- */
$badgeVariation = function ($v) {
    if ($v === null) {
        return '';
    }
    $baisse = $v <= 0;
    return '<span class="tendance ' . ($baisse ? 'tendance-baisse' : 'tendance-hausse') . '">'
         . ($baisse ? '↓' : '↑') . ' ' . abs((int) $v) . ' %</span>';
};

/* ---------- Courbe SVG : 6 derniers mois (chaque courbe sur sa propre échelle) ---------- */
$courbe = function (array $lignes) use ($moisCourt) {
    $n = count($lignes);
    if ($n < 2) {
        return '';
    }
    $W = 560; $H = 230; $gauche = 24; $droite = 24; $haut = 18; $bas = 40;
    $largeur = $W - $gauche - $droite;
    $hauteur = $H - $haut - $bas;

    $maxElec = max(1, max(array_column($lignes, 'conso_elec')));
    $maxEau  = max(1, max(array_column($lignes, 'conso_eau')));

    $x = function ($i) use ($n, $gauche, $largeur) { return $gauche + $i * $largeur / ($n - 1); };
    $y = function ($v, $max) use ($haut, $hauteur) { return $haut + $hauteur * (1 - $v / ($max * 1.15)); };

    $ptsElec = []; $ptsEau = [];
    foreach ($lignes as $i => $l) {
        $ptsElec[] = [$x($i), $y($l['conso_elec'], $maxElec)];
        $ptsEau[]  = [$x($i), $y($l['conso_eau'], $maxEau)];
    }
    $chemin = function ($pts) {
        $d = '';
        foreach ($pts as $k => $p) { $d .= ($k ? ' L' : 'M') . round($p[0], 1) . ',' . round($p[1], 1); }
        return $d;
    };
    $bas_y = $haut + $hauteur;

    $svg  = '<svg class="courbe" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Évolution de la consommation sur 6 mois">';
    $svg .= '<defs><linearGradient id="degrade-elec" x1="0" y1="0" x2="0" y2="1">'
          . '<stop offset="0" stop-color="#1f7a4a" stop-opacity=".18"/><stop offset="1" stop-color="#1f7a4a" stop-opacity="0"/>'
          . '</linearGradient></defs>';
    foreach ([0, .5, 1] as $g) {
        $gy = round($haut + $hauteur * $g, 1);
        $svg .= '<line x1="' . $gauche . '" x2="' . ($W - $droite) . '" y1="' . $gy . '" y2="' . $gy . '" class="grille-courbe"/>';
    }
    $svg .= '<path d="' . $chemin($ptsElec) . ' L' . round($x($n - 1), 1) . ',' . $bas_y . ' L' . $gauche . ',' . $bas_y . ' Z" fill="url(#degrade-elec)"/>';
    $svg .= '<path d="' . $chemin($ptsEau) . '" class="trait trait-eau"/>';
    $svg .= '<path d="' . $chemin($ptsElec) . '" class="trait trait-elec"/>';
    foreach ($lignes as $i => $l) {
        $svg .= '<circle cx="' . round($ptsEau[$i][0], 1) . '" cy="' . round($ptsEau[$i][1], 1) . '" r="4" class="point point-eau"><title>'
              . htmlspecialchars($moisCourt[(int) $l['mois']]) . ' : ' . (int) $l['conso_eau'] . ' m³</title></circle>';
        $svg .= '<circle cx="' . round($ptsElec[$i][0], 1) . '" cy="' . round($ptsElec[$i][1], 1) . '" r="4" class="point point-elec"><title>'
              . htmlspecialchars($moisCourt[(int) $l['mois']]) . ' : ' . (int) $l['conso_elec'] . ' kWh</title></circle>';
        $svg .= '<text x="' . round($x($i), 1) . '" y="' . ($H - 12) . '" text-anchor="middle" class="axe-courbe">'
              . htmlspecialchars($moisCourt[(int) $l['mois']]) . '</text>';
    }
    return $svg . '</svg>';
};

/* ---------- Conseil du mois (change chaque mois) ---------- */
$conseils = [
    ['eclair',  "Éteignez vos appareils plutôt que de les laisser en veille : ils continuent de consommer de l'électricité."],
    ['goutte',  "Un robinet qui goutte gaspille de l'eau jour et nuit. Un petit joint suffit souvent à régler le problème."],
    ['eclair',  "Pensez à dégivrer votre congélateur régulièrement : il consomme moins quand il n'est pas couvert de glace."],
    ['goutte',  "Une douche courte consomme beaucoup moins d'eau qu'un bain. Fermez le robinet pendant que vous vous savonnez."],
];
[$iconeConseil, $texteConseil] = $conseils[((int) $mois - 1) % count($conseils)];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Espace client</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <!-- factures.css (Membre 5) = gabarit commun : barre latérale, barre du haut, cartes, badges -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/factures.css?v=<?= filemtime(ROOT . 'assets/css/factures.css') ?>">
    <!-- accueil.css (Membre 1) = éléments propres à la vue d'ensemble -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/accueil.css?v=<?= @filemtime(ROOT . 'assets/css/accueil.css') ?>">
</head>
<body>
<div class="appli">

    <aside class="sidebar">
        <div class="marque">
            <div class="marque-nom marque-nom--amanor">Amanor</div>
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
            <div class="statut-portail"><span class="point-vert"></span> Amanor opérationnel</div>
            <div class="topbar-droite">
                <div class="langues"><span class="actif">FR</span><span>AR</span></div>
                <span class="cloche"><?= $ico('cloche') ?></span>
                <span class="separateur"></span>
                <a class="deconnexion" href="<?= BASE_URL ?>Auth/logout"><?= $ico('sortie') ?> Déconnexion</a>
            </div>
        </header>

        <main class="contenu">

            <!-- En-tête : bonjour + date -->
            <div class="accueil-entete">
                <div>
                    <p class="eyebrow">ESPACE CLIENT</p>
                    <h1>Bonjour, <?= htmlspecialchars($prenom) ?></h1>
                    <p class="sous-titre">Voici un aperçu de votre compte et de votre consommation.</p>
                </div>
                <div class="puce-date"><?= $ico('calendrier') ?> <?= htmlspecialchars($dateDuJour) ?></div>
            </div>

            <!-- Bandeau : état de la déclaration du mois -->
            <section class="bandeau bandeau-<?= strtolower(str_replace('_', '-', $etatSaisie)) ?>">
                <span class="bandeau-icone"><?= $ico('calendrier') ?></span>
                <div class="bandeau-texte">
                    <?php if ($etatSaisie === 'OUVERTE'): ?>
                        <span class="bandeau-etiquette"><span class="point-vert"></span> DÉCLARATION OUVERTE</span>
                        <strong>Du 18 au <?= $dernierJour ?> <?= $moisFr[$mois] ?></strong>
                    <?php elseif ($etatSaisie === 'DEJA_SAISI'): ?>
                        <span class="bandeau-etiquette"><span class="point-vert"></span> DÉCLARATION ENVOYÉE</span>
                        <strong>Vous avez déjà saisi vos index ce mois-ci. Merci !</strong>
                    <?php else: ?>
                        <span class="bandeau-etiquette">DÉCLARATION FERMÉE</span>
                        <strong>Elle ouvre le 18 <?= $moisFr[$mois] ?></strong>
                    <?php endif; ?>
                </div>
                <?php if ($etatSaisie === 'OUVERTE'): ?>
                    <a class="bouton-vert" href="<?= BASE_URL ?>Consommation/declaration">Faire ma déclaration <?= $ico('chevron') ?></a>
                <?php endif; ?>
            </section>

            <!-- Trois cartes : électricité, eau, total estimé -->
            <section class="grille-3">
                <div class="carte carte-stat">
                    <div class="stat-haut">
                        <span class="stat-icone stat-icone-elec"><?= $ico('eclair') ?></span>
                        <?= $badgeVariation($variationElec) ?>
                    </div>
                    <div class="stat-libelle">Électricité</div>
                    <div class="stat-valeur">
                        <?= $dernierElec === null ? '—' : (int) $dernierElec ?> <small>kWh</small>
                    </div>
                    <div class="stat-note"><?= $variationElec !== null ? 'Par rapport au mois dernier' : 'Dernier relevé' ?></div>
                </div>

                <div class="carte carte-stat">
                    <div class="stat-haut">
                        <span class="stat-icone stat-icone-eau"><?= $ico('goutte') ?></span>
                        <?= $badgeVariation($variationEau) ?>
                    </div>
                    <div class="stat-libelle">Eau</div>
                    <div class="stat-valeur">
                        <?= $dernierEau === null ? '—' : (int) $dernierEau ?> <small>m³</small>
                    </div>
                    <div class="stat-note"><?= $variationEau !== null ? 'Par rapport au mois dernier' : 'Dernier relevé' ?></div>
                </div>

                <div class="carte carte-stat">
                    <div class="stat-haut">
                        <span class="stat-icone stat-icone-total"><?= $ico('facture') ?></span>
                        <span class="stat-note-haut">Prochaine facture</span>
                    </div>
                    <div class="stat-libelle">Total estimé</div>
                    <div class="stat-valeur">
                        <?= $estimationTtc === null ? '—' : $dh($estimationTtc) ?> <small>DH TTC</small>
                    </div>
                    <?php if ($nbImpayees > 0): ?>
                        <div class="stat-note">Solde à payer : <strong><?= $dh($solde) ?> DH</strong>
                            (avant le <?= htmlspecialchars($dateCourte($echeance)) ?>)</div>
                    <?php else: ?>
                        <div class="stat-note">Aucune facture à payer.</div>
                    <?php endif; ?>
                    <a class="lien-vert" href="<?= BASE_URL ?>Facture/liste">Voir le détail <?= $ico('chevron') ?></a>
                </div>
            </section>

            <!-- Courbe + conseil -->
            <section class="grille-courbe-conseil">
                <div class="carte carte-courbe">
                    <div class="carte-entete">
                        <div>
                            <h2>Votre consommation</h2>
                            <p class="carte-sous">Évolution sur les 6 derniers mois</p>
                        </div>
                        <div class="legende">
                            <span><i class="pastille pastille-elec"></i> Électricité</span>
                            <span><i class="pastille pastille-eau"></i> Eau</span>
                        </div>
                    </div>
                    <?php $svg = $courbe($historique); ?>
                    <?php if ($svg !== ''): ?>
                        <?= $svg ?>
                        <p class="carte-note">Chaque courbe est tracée à sa propre échelle. Passez la souris sur un point pour voir la valeur.</p>
                    <?php else: ?>
                        <p class="vide-courbe">Pas encore assez de relevés pour tracer la courbe.<br>Elle apparaîtra après votre deuxième déclaration.</p>
                    <?php endif; ?>
                </div>

                <aside class="carte-conseil">
                    <div class="conseil-titre"><?= $ico('tendance') ?> CONSEIL DU MOIS</div>
                    <div class="conseil-rond"><?= $ico($iconeConseil) ?></div>
                    <p><?= htmlspecialchars($texteConseil) ?></p>
                </aside>
            </section>

            <!-- Dernières factures -->
            <section class="carte carte-factures">
                <div class="carte-entete carte-entete-pad">
                    <div>
                        <h2>Dernières factures</h2>
                        <p class="carte-sous">Vos factures récentes et documents disponibles</p>
                    </div>
                    <a class="lien-vert" href="<?= BASE_URL ?>Facture/liste">Voir toutes les factures <?= $ico('chevron') ?></a>
                </div>
                <table>
                    <thead>
                        <tr><th>N° de facture</th><th>Période</th><th>Montant TTC</th><th>Échéance</th><th>Statut</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($dernieresFacturesClient)): ?>
                        <tr><td class="vide" colspan="6">Aucune facture pour le moment.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dernieresFacturesClient as $f):
                            [$texteStatut, $classeStatut] = $statuts[$f['statut']] ?? [$f['statut'], 'badge-annulee']; ?>
                            <tr>
                                <td class="fort"><?= htmlspecialchars($f['numero_facture']) ?></td>
                                <td><?= htmlspecialchars(ucfirst($moisFr[(int) $f['periode_mois']]) . ' ' . (int) $f['periode_annee']) ?></td>
                                <td class="fort"><?= $dh($f['montant_ttc']) ?> DH</td>
                                <td><?= htmlspecialchars($dateCourte($f['date_echeance'])) ?></td>
                                <td><span class="badge <?= $classeStatut ?>"><?= htmlspecialchars($texteStatut) ?></span></td>
                                <td><a class="oeil" href="<?= BASE_URL ?>Facture/detail/<?= (int) $f['id_facture'] ?>" aria-label="Voir la facture"><?= $ico('oeil') ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </section>

        </main>
    </div>
</div>
</body>
</html>