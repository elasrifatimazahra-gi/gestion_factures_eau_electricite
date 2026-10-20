<?php
// Variables reçues de Auth_accueil() :
// $client, $solde, $echeance, $nbImpayees, $etatSaisie, $dernierJour, $mois, $annee

$prenom = $client['prenom'] ?? '';
$nom = $client['nom'] ?? '';
$initiales = mb_strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));

$jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$moisFr = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
           'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$dateDuJour = $jours[(int) date('w')] . ' ' . date('j') . ' ' . $moisFr[(int) date('n')] . ' ' . date('Y');

$dateEcheance = '';
if ($echeance) {
    $t = strtotime($echeance);
    $dateEcheance = date('j', $t) . ' ' . $moisFr[(int) date('n', $t)] . ' ' . date('Y', $t);
}

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
    'horloge'  => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    'fleche'   => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Espace client</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <!-- factures.css (Membre 5) = gabarit commun : barre latérale, barre du haut, cartes -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/factures.css">
    <!-- accueil.css (Membre 1) = éléments propres à cette page -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/accueil.css">
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
            <p class="date-jour"><?= htmlspecialchars($dateDuJour) ?></p>
            <h1>Bonjour, <?= htmlspecialchars($prenom) ?></h1>
            <p class="sous-titre">Voici l'essentiel de votre espace aujourd'hui.</p>

            <!-- Carte : solde à payer -->
            <section class="grille-accueil">
                <div class="carte carte-solde">
                    <div class="solde-entete">
                        <span class="solde-titre">Solde à payer</span>
                        <span class="solde-icone"><?= $ico('carte') ?></span>
                    </div>
                    <div class="solde-montant">
                        <?= number_format($solde, 2, ',', ' ') ?> <small>DH</small>
                    </div>

                    <?php if ($nbImpayees > 0): ?>
                        <div class="solde-pied">
                            <span class="solde-icone solde-icone-orange"><?= $ico('horloge') ?></span>
                            <div>
                                <div class="solde-pied-titre">À régler avant le</div>
                                <div class="solde-pied-valeur"><?= htmlspecialchars($dateEcheance) ?></div>
                            </div>
                            <span class="badge badge-impayee"><?= $nbImpayees ?> impayée<?= $nbImpayees > 1 ? 's' : '' ?></span>
                        </div>
                    <?php else: ?>
                        <div class="solde-pied">
                            <span class="badge badge-payee">Tout est réglé</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Carte : accès rapide aux factures -->
                <div class="carte carte-raccourci">
                    <span class="solde-titre">Mes factures</span>
                    <p>Consultez, téléchargez et réglez vos factures.</p>
                    <a class="bouton-vert" href="<?= BASE_URL ?>Facture/liste">Voir mes factures <?= $ico('fleche') ?></a>
                </div>
            </section>

            <!-- Bandeau : état de la saisie du mois -->
            <section class="bandeau bandeau-<?= strtolower(str_replace('_', '-', $etatSaisie)) ?>">
                <span class="bandeau-icone"><?= $ico('calendrier') ?></span>
                <div class="bandeau-texte">
                    <?php if ($etatSaisie === 'OUVERTE'): ?>
                        <strong>Saisie ouverte · 18-<?= $dernierJour ?> <?= $moisFr[$mois] ?></strong>
                        <span>Vous n'avez pas encore déclaré vos index ce mois-ci.</span>
                    <?php elseif ($etatSaisie === 'DEJA_SAISI'): ?>
                        <strong>Déclaration envoyée</strong>
                        <span>Vous avez déjà saisi vos index ce mois-ci. Merci !</span>
                    <?php else: ?>
                        <strong>Saisie fermée</strong>
                        <span>La saisie ouvre le 18 <?= $moisFr[$mois] ?>.</span>
                    <?php endif; ?>
                </div>
                <?php if ($etatSaisie === 'OUVERTE'): ?>
                    <a class="bouton-vert" href="<?= BASE_URL ?>Consommation/declaration">Saisir mes index <?= $ico('fleche') ?></a>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>
</body>
</html>