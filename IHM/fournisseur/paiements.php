<?php
// Variables fournies par Paiement_index : $factures, $historique, $modes, $recherche, $message, $erreur
$titreFournisseur = 'Paiements';
$sousTitreFournisseur = 'Enregistrez les paiements des factures, dans l’ordre chronologique de chaque contrat.';
include ROOT . 'IHM/layouts/fournisseur_header.php';

$noms_mois = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
              7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];
$dh = function ($v) { return number_format((float) $v, 2, ',', ' ') . ' DH'; };
$aujourdhui = strtotime('today');
?>
<section class="m1-page">
    <?php if ($message): ?>
        <div class="m4-notice m4-notice--success" role="status"><span><?= htmlspecialchars($message) ?></span><button class="m4-notice__close" type="button" aria-label="Fermer">×</button></div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="m4-notice m4-notice--error" role="alert"><span><?= htmlspecialchars($erreur) ?></span><button class="m4-notice__close" type="button" aria-label="Fermer">×</button></div>
    <?php endif; ?>

    <form class="m1-filters" method="get" action="<?= BASE_URL ?>Paiement/index">
        <label>
            <span>Rechercher (client, n° client, contrat, facture)</span>
            <input type="text" name="q" value="<?= htmlspecialchars($recherche, ENT_QUOTES) ?>">
        </label>
        <button class="m1-button" type="submit">Rechercher</button>
        <a class="m1-button m1-button--secondary" href="<?= BASE_URL ?>Paiement/index">Réinitialiser</a>
    </form>

    <h2 class="card__title">Factures à payer</h2>
    <div class="m1-table-wrap">
        <table class="m1-table">
            <thead>
                <tr>
                    <th>Facture</th><th>Client</th><th>Contrat / quartier</th><th>Échéance</th>
                    <th>Total</th><th>Déjà payé</th><th>Reste</th><th>Statut</th><th>Paiement</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$factures): ?>
                    <tr><td class="m1-empty" colspan="9">Aucune facture à payer.</td></tr>
                <?php else: ?>
                    <?php foreach ($factures as $f): ?>
                        <?php $enRetard = $f['statut'] === 'EN_RETARD' || strtotime($f['date_echeance']) < $aujourdhui; ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($f['numero_facture']) ?></strong><br>
                                <?= htmlspecialchars(($noms_mois[(int) $f['periode_mois']] ?? '') . ' ' . $f['periode_annee']) ?>
                            </td>
                            <td><?= htmlspecialchars($f['nom'] . ' ' . $f['prenom']) ?><br><?= htmlspecialchars($f['numero_client']) ?></td>
                            <td>
                                <?= htmlspecialchars($f['numero_contrat'] ?? '—') ?>
                                <?php if (!empty($f['quartier'])): ?><br><?= htmlspecialchars($f['quartier']) ?><?php endif; ?>
                            </td>
                            <td class="<?= $enRetard ? 'm4-text-danger' : '' ?>"><?= htmlspecialchars(date('d/m/Y', strtotime($f['date_echeance']))) ?></td>
                            <td><?= $dh($f['montant_total_a_payer']) ?></td>
                            <td><?= $dh($f['montant_paye']) ?></td>
                            <td class="<?= $enRetard ? 'm4-text-danger' : '' ?>"><strong><?= $dh($f['reste_a_payer']) ?></strong></td>
                            <td>
                                <?php if ($f['statut'] === 'EN_RETARD'): ?>
                                    <span class="m4-tag m4-tag--danger">En retard</span>
                                <?php else: ?>
                                    <span class="m4-tag <?= $enRetard ? 'm4-tag--danger' : 'm4-tag--warning' ?>"><?= $enRetard ? 'Échéance dépassée' : 'Impayée' ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) $f['nb_anterieures'] > 0): ?>
                                    <span class="m4-hint">Réglez d’abord la facture plus ancienne de ce contrat.</span>
                                <?php else: ?>
                                    <details class="m4-details">
                                        <summary class="m1-button m1-button--small">Enregistrer un paiement</summary>
                                        <form class="m1-edit-form" method="post" action="<?= BASE_URL ?>Paiement/enregistrer"
                                              data-confirm="Enregistrer ce paiement pour la facture <?= htmlspecialchars($f['numero_facture'], ENT_QUOTES) ?> ?"
                                              data-confirm-title="Confirmer le paiement"
                                              data-confirm-detail="Montant encaissé : {montant} DH"
                                              data-confirm-label="Enregistrer le paiement">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
                                            <input type="hidden" name="id_facture" value="<?= (int) $f['id_facture'] ?>">
                                            <label>Montant (DH)
                                                <input type="text" name="montant" inputmode="decimal"
                                                       value="<?= htmlspecialchars(number_format((float) $f['reste_a_payer'], 2, '.', ''), ENT_QUOTES) ?>" required>
                                            </label>
                                            <label>Mode
                                                <select name="mode_paiement" required>
                                                    <?php foreach ($modes as $code => $libelle): ?>
                                                        <option value="<?= htmlspecialchars($code, ENT_QUOTES) ?>"><?= htmlspecialchars($libelle) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label>Remarque
                                                <input type="text" name="remarques" maxlength="500">
                                            </label>
                                            <button class="m1-button m1-button--small" type="submit">Valider</button>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h2 class="card__title">Derniers paiements</h2>
    <div class="m1-table-wrap">
        <table class="m1-table">
            <thead>
                <tr><th>Date</th><th>Référence</th><th>Facture</th><th>Client</th><th>Montant</th><th>Mode</th><th>Encaissé par</th></tr>
            </thead>
            <tbody>
                <?php if (!$historique): ?>
                    <tr><td class="m1-empty" colspan="7">Aucun paiement enregistré.</td></tr>
                <?php else: ?>
                    <?php foreach ($historique as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($p['date_paiement']))) ?></td>
                            <td><?= htmlspecialchars($p['reference_paiement']) ?></td>
                            <td><?= htmlspecialchars($p['numero_facture']) ?></td>
                            <td><?= htmlspecialchars($p['nom'] . ' ' . $p['prenom']) ?></td>
                            <td><?= $dh($p['montant']) ?></td>
                            <td><?= htmlspecialchars($modes[$p['mode_paiement']] ?? $p['mode_paiement']) ?></td>
                            <td><?= htmlspecialchars($p['encaisse_par'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>