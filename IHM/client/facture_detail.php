<?php
// Variables reçues du contrôleur : $facture, $client, $anterieuresImpayees, $penalitePct, $penaliteMin

// Interrupteur : mettre à false pour masquer les boutons "Télécharger PDF" et "Imprimer"
$afficherOutils = true;

[$classeBadge, $libelleBadge] = facture_badge($facture);
$statutsAr = ['payee' => 'مدفوعة', 'impayee' => 'غير مدفوعة', 'retard' => 'متأخرة',
              'annulee' => 'ملغاة', 'estimee' => 'تقديرية'];

$dh = function ($m) {
    return number_format((float) $m, 2, ',', ' ') . ' DH';
};
$pct = function ($x) {
    return rtrim(rtrim(number_format((float) $x, 2, ',', ''), '0'), ',');
};
$periode = facture_periode_dates($facture['periode_mois'], $facture['periode_annee']);
$joursFactures = (int) substr($periode['fin'], 0, 2);          // dernier jour du mois
$nomClient = trim(($client['prenom'] ?? '') . ' ' . ($client['nom'] ?? ''));
$referencePaiement = trim(str_replace(['FAC-', '-'], ['', ' '], $facture['numero_facture']));

$icones = [
    'goutte'  => '<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>',
    'maison'  => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
    'jauge'   => '<path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/>',
    'facture' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
    'sortie'  => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/>',
    'telecharger' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
    'imprimer' => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
    'vagues'  => '<path d="M2 6c.6.5 1.2 1 2.5 1C7 7 7 5 9.5 5c2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M2 12c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M2 18c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/>',
    'eclair'  => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
];
$ico = function ($nom) use ($icones) {
    return '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">' . $icones[$nom] . '</svg>';
};

// Lignes du tableau : [FR, AR, icône, classe icône, index début, index fin, conso, unité, montant]
$services = [
    ['Eau', 'الماء', 'vagues', 'pic-eau', $facture['index_eau_debut'], $facture['index_eau_fin'], $facture['conso_eau'], 'm³', $facture['montant_ht_eau']],
    ['Électricité', 'الكهرباء', 'eclair', 'pic-elec', $facture['index_elec_debut'], $facture['index_elec_fin'], $facture['conso_elec'], 'kWh', $facture['montant_ht_elec']],
];

// Totaux : [FR, AR, valeur, classes]
$totaux = [
    ['Total HT', 'المجموع دون الضريبة', $dh($facture['montant_ht']), ''],
    ['TVA (' . $pct($facture['taux_tva']) . ' %)', 'الضريبة على القيمة المضافة', $dh($facture['montant_tva']), ''],
    ['Total TTC', 'المجموع مع الضريبة', $dh($facture['montant_ttc']), 'fort'],
];
if ((float) $facture['credit_deduit'] > 0) {
    $totaux[] = ['Crédit déduit', 'الرصيد المخصوم', '- ' . $dh($facture['credit_deduit']), ''];
}
if ($facture['penalite_appliquee']) {
    $totaux[] = ['Pénalité de retard', 'غرامة التأخير', $dh($facture['penalite_retard']), 'penalite'];
}
$totaux[] = ['Total à payer', 'المبلغ الواجب أداؤه', $dh($facture['montant_total_a_payer']), 'fort'];
$totaux[] = ['Déjà payé', 'المبلغ المؤدى', $dh($facture['montant_paye']), ''];

$photos = [
    ['Compteur d\'eau', $facture['photo_eau_facture'], $facture['index_eau_fin']],
    ['Compteur d\'électricité', $facture['photo_elec_facture'], $facture['index_elec_fin']],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Facture <?= htmlspecialchars($facture['numero_facture']) ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/facture_detail.css">
</head>
<body>

<header class="barre">
    <div class="barre-nom"><?= $ico('goutte') ?> Eau &amp; Électricité</div>
    <div class="barre-droite">
        <span class="nom-client"><?= htmlspecialchars($nomClient) ?></span>
        <a class="btn-deco" href="<?= BASE_URL ?>Auth/logout"><?= $ico('sortie') ?> Déconnexion</a>
    </div>
</header>

<div class="corps">
    <nav class="menu">
        <a href="<?= BASE_URL ?>Auth/accueil"><?= $ico('maison') ?> Accueil</a>
        <a href="<?= BASE_URL ?>Consommation/declaration"><?= $ico('jauge') ?> Saisir ma consommation</a>
        <a class="actif" href="<?= BASE_URL ?>Facture/liste"><?= $ico('facture') ?> Mes factures</a>
    </nav>

    <main class="zone">
        <div class="colonne">

            <div class="outils">
                <a class="retour" href="<?= BASE_URL ?>Facture/liste">&larr; Retour à mes factures</a>
                <?php if ($afficherOutils): ?>
                <div class="outils-droite">
                    <button type="button" class="btn btn-plein" onclick="window.print()"><?= $ico('telecharger') ?> Télécharger PDF</button>
                    <button type="button" class="btn btn-contour" onclick="window.print()"><?= $ico('imprimer') ?> Imprimer</button>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($anterieuresImpayees > 0): ?>
                <div class="alerte">
                    Vous avez <?= (int) $anterieuresImpayees ?> facture(s) plus ancienne(s) non payée(s).
                    Elles doivent être réglées avant celle-ci.
                </div>
            <?php endif; ?>

            <article class="feuille">

                <!-- En-tête -->
                <div class="f-entete">
                    <div class="marque">
                        <div class="marque-logo"><?= $ico('goutte') ?></div>
                        <div>
                            <div class="marque-nom">Service Régional</div>
                            <div class="marque-sous">Eau &amp; Électricité</div>
                        </div>
                    </div>
                    <div class="f-droite">
                        <div class="f-titre-ligne">
                            <h1>FACTURE <span class="ar">فاتورة</span></h1>
                            <span class="badge badge-<?= $classeBadge ?>">
                                <?= htmlspecialchars(mb_strtoupper($libelleBadge)) ?> <i class="point"></i> <span class="ar"><?= $statutsAr[$classeBadge] ?></span>
                            </span>
                        </div>
                        <div class="meta"><span>N° de facture</span><strong><?= htmlspecialchars($facture['numero_facture']) ?></strong></div>
                        <div class="meta"><span>Date d'émission</span><strong><?= date('d/m/Y', strtotime($facture['date_emission'])) ?></strong></div>
                        <div class="meta"><span>Date d'échéance</span><strong><?= date('d/m/Y', strtotime($facture['date_echeance'])) ?></strong></div>
                    </div>
                </div>

                <div class="trait"><span></span></div>

                <!-- Client et période -->
                <div class="infos">
                    <div class="carte-info">
                        <div class="etiquette">Client <span class="ar">الزبون</span></div>
                        <div class="gros-nom"><?= htmlspecialchars($nomClient) ?></div>
                        <div class="sous-texte"><?= nl2br(htmlspecialchars($client['adresse'] ?? '')) ?></div>
                        <div class="lignes">
                            <div class="ligne"><span>N° client</span><b><?= htmlspecialchars($client['numero_client'] ?? '') ?></b></div>
                            <div class="ligne"><span>Compteur eau</span><b><?= htmlspecialchars($client['numero_compteur_eau'] ?? '') ?></b></div>
                            <div class="ligne"><span>Compteur électricité</span><b><?= htmlspecialchars($client['numero_compteur_elec'] ?? '') ?></b></div>
                        </div>
                    </div>
                    <div class="carte-info">
                        <div class="etiquette">Période de consommation <span class="ar">فترة الاستهلاك</span></div>
                        <div class="gros-nom"><?= facture_nom_mois($facture['periode_mois']) . ' ' . (int) $facture['periode_annee'] ?></div>
                        <div class="sous-texte">Du <?= $periode['debut'] ?> au <?= $periode['fin'] ?></div>
                        <div class="jours"><b><?= $joursFactures ?></b> jours facturés</div>
                    </div>
                </div>

                <!-- Détail de la consommation -->
                <div class="section-titre">Détail de la consommation <span class="ar">تفاصيل الاستهلاك</span></div>
                <table class="detail">
                    <thead>
                        <tr>
                            <th>Service<span class="ar">الخدمة</span></th>
                            <th>Index début<span class="ar">المؤشر السابق</span></th>
                            <th>Index fin<span class="ar">المؤشر الحالي</span></th>
                            <th>Consommation<span class="ar">الاستهلاك</span></th>
                            <th>Prix unitaire<span class="ar">الثمن الوحدوي</span></th>
                            <th>Montant HT<span class="ar">المبلغ دون الضريبة</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($services as [$fr, $ar, $icone, $classeIcone, $debut, $fin, $conso, $unite, $montant]): ?>
                        <tr>
                            <td>
                                <div class="service">
                                    <span class="pic <?= $classeIcone ?>"><?= $ico($icone) ?></span>
                                    <div><div class="nom"><?= $fr ?></div><span class="ar"><?= $ar ?></span></div>
                                </div>
                            </td>
                            <td><?= (int) $debut ?></td>
                            <td><?= (int) $fin ?></td>
                            <td class="fort"><?= (int) $conso ?> <?= $unite ?></td>
                            <td><?= facture_prix_unitaire($montant, $conso) ?></td>
                            <td class="fort"><?= $dh($montant) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Mode de paiement + totaux -->
                <div class="bas">
                    <div class="mode">
                        <div class="etiquette">Mode de paiement</div>
                        <div class="gros">À régler en agence</div>
                        <div class="ref-lib">Référence de paiement</div>
                        <div class="ref"><?= htmlspecialchars($referencePaiement) ?></div>
                    </div>
                    <div class="totaux">
                        <?php foreach ($totaux as [$fr, $ar, $valeur, $classe]): ?>
                            <div class="t-ligne <?= $classe ?>">
                                <div class="lib"><?= htmlspecialchars($fr) ?><span class="ar"><?= $ar ?></span></div>
                                <div class="val"><?= $valeur ?></div>
                            </div>
                        <?php endforeach; ?>
                        <div class="t-final">
                            <div class="lib">Reste à payer<span class="ar">المتبقي للأداء</span></div>
                            <div class="val"><?= number_format((float) $facture['reste_a_payer'], 2, ',', ' ') ?> <small>DH</small></div>
                        </div>
                    </div>
                </div>

                <!-- Photos des compteurs -->
                <div class="photos-entete">
                    <div class="titre">Photos des compteurs <span class="ar">صور العدادات</span></div>
                    <div class="releves">Relevés du <?= $periode['fin'] ?></div>
                </div>
                <div class="photos">
                    <?php foreach ($photos as [$legende, $chemin, $index]): ?>
                        <figure class="photo-carte">
                            <div class="photo-cadre">
                                <?php if (!empty($chemin)): ?>
                                    <img src="<?= BASE_URL . htmlspecialchars($chemin) ?>" alt="<?= htmlspecialchars($legende) ?>">
                                <?php else: ?>
                                    Photo non disponible
                                <?php endif; ?>
                            </div>
                            <figcaption class="photo-legende"><?= $legende ?> &ndash; index <?= (int) $index ?></figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>

                <!-- Pied -->
                <div class="f-pied">
                    <div class="coord">Service Régional Eau &amp; Électricité &middot; Tétouan, Maroc &middot; Tél. 05 39 00 00 00 &middot; contact@fournisseur.ma</div>
                    <div>Paiement à effectuer à l'agence avant la date d'échéance. Une pénalité de <?= $pct($penalitePct) ?> % (minimum <?= $pct($penaliteMin) ?> DH) s'applique en cas de retard.</div>
                    <div class="ar">يتم الأداء بالوكالة قبل تاريخ الاستحقاق. تُطبَّق غرامة تأخير قدرها <?= $pct($penalitePct) ?>% (بحد أدنى <?= $pct($penaliteMin) ?> درهماً) في حالة التأخر.</div>
                </div>
                <div class="f-fin"></div>

            </article>
        </div>
    </main>
</div>

</body>
</html>