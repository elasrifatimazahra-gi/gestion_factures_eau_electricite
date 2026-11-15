<?php
// Variables fournies par Coupure_index : $coupures, $contrats, $motifs, $statuts, $filtre, $frais, $message, $erreur
$titreFournisseur = 'Coupures';
$sousTitreFournisseur = 'Chaque coupure vise un seul contrat : les autres contrats du même client ne sont pas touchés.';
include ROOT . 'IHM/layouts/fournisseur_header.php';

$dh = function ($v) { return number_format((float) $v, 2, ',', ' ') . ' DH'; };
$onglets = ['PROGRAMMEE' => 'Programmées', 'EFFECTUEE' => 'Effectuées', 'RETABLIE' => 'Rétablies', 'ANNULEE' => 'Annulées', 'TOUTES' => 'Toutes'];
$classeStatut = ['PROGRAMMEE' => 'm4-tag--danger', 'EFFECTUEE' => 'm4-tag--danger', 'RETABLIE' => 'm4-tag--ok', 'ANNULEE' => 'm4-tag--neutral'];
$aujourdhui = date('Y-m-d');
?>
<section class="m1-page">
    <?php if ($message): ?>
        <div class="m4-notice m4-notice--success" role="status"><span><?= htmlspecialchars($message) ?></span><button class="m4-notice__close" type="button" aria-label="Fermer">×</button></div>
    <?php endif; ?>
    <?php if ($erreur): ?>
        <div class="m4-notice m4-notice--error" role="alert"><span><?= htmlspecialchars($erreur) ?></span><button class="m4-notice__close" type="button" aria-label="Fermer">×</button></div>
    <?php endif; ?>

    <div style="margin-bottom:16px">
        <details class="m4-details">
            <summary class="m1-button">Programmer une coupure</summary>
            <form class="m1-edit-form" method="post" action="<?= BASE_URL ?>Coupure/programmer"
                  data-confirm="Programmer la coupure de ce contrat ? Le client sera alerté."
                  data-confirm-title="Programmer une coupure"
                  data-confirm-detail="Date de coupure : {date_programmee}"
                  data-confirm-label="Programmer la coupure">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
                <label>Contrat
                    <select name="id_contrat" required>
                        <option value="">— Choisir un contrat —</option>
                        <?php foreach ($contrats as $ct): ?>
                            <option value="<?= (int) $ct['id_contrat'] ?>">
                                <?= htmlspecialchars($ct['numero_contrat'] . ' — ' . $ct['nom'] . ' ' . $ct['prenom']
                                    . ($ct['quartier'] ? ' (' . $ct['quartier'] . ')' : '')
                                    . ($ct['statut_service'] === 'AVERTISSEMENT' ? ' [avertissement]' : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Date de coupure
                    <input type="date" name="date_programmee" min="<?= $aujourdhui ?>" value="<?= $aujourdhui ?>" required>
                </label>
                <label>Commentaire
                    <input type="text" name="commentaire" maxlength="500">
                </label>
                <button class="m1-button m1-button--small" type="submit">Programmer</button>
            </form>
        </details>
    </div>

    <p>
        <?php foreach ($onglets as $code => $libelle): ?>
            <a class="m1-button<?= $code === $filtre ? '' : ' m1-button--secondary' ?> m1-button--small"
               href="<?= BASE_URL ?>Coupure/index?statut=<?= $code ?>"><?= htmlspecialchars($libelle) ?></a>
        <?php endforeach; ?>
    </p>

    <div class="m1-table-wrap">
        <table class="m1-table">
            <thead>
                <tr>
                    <th>Client</th><th>Contrat / quartier</th><th>Motif</th><th>Date prévue</th>
                    <th>Statut</th><th>Frais de rétablissement</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$coupures): ?>
                    <tr><td class="m1-empty" colspan="7">Aucune coupure dans cette catégorie.</td></tr>
                <?php else: ?>
                    <?php foreach ($coupures as $c): ?>
                        <?php
                        $ouverte = in_array($c['statut'], ['PROGRAMMEE', 'EFFECTUEE'], true);
                        $dateDepassee = $c['statut'] === 'PROGRAMMEE' && $c['date_programmee'] <= $aujourdhui;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($c['nom'] . ' ' . $c['prenom']) ?><br><?= htmlspecialchars($c['numero_client']) ?></td>
                            <td>
                                <?= htmlspecialchars($c['numero_contrat'] ?? '—') ?>
                                <?php if (!empty($c['quartier'])): ?><br><?= htmlspecialchars($c['quartier']) ?><?php endif; ?>
                                <?php if ($ouverte && $c['statut_service'] === 'COUPE'): ?><br><span class="m4-tag m4-tag--danger">Service coupé</span>
                                <?php elseif ($ouverte && $c['statut_service'] === 'AVERTISSEMENT'): ?><br><span class="m4-tag m4-tag--danger">Alerte envoyée</span><?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($motifs[$c['motif_coupure']] ?? $c['motif_coupure']) ?></td>
                            <td>
                                <span class="<?= $dateDepassee ? 'm4-text-danger' : '' ?>"><?= htmlspecialchars(date('d/m/Y', strtotime($c['date_programmee']))) ?></span>
                                <?php if ($c['date_execution']): ?><br>Coupée le <?= htmlspecialchars(date('d/m/Y H:i', strtotime($c['date_execution']))) ?><?php endif; ?>
                                <?php if ($c['date_retablissement']): ?><br>Rétablie le <?= htmlspecialchars(date('d/m/Y H:i', strtotime($c['date_retablissement']))) ?><?php endif; ?>
                            </td>
                            <td><span class="m4-tag <?= $classeStatut[$c['statut']] ?? 'm4-tag--neutral' ?>"><?= htmlspecialchars($statuts[$c['statut']] ?? $c['statut']) ?></span></td>
                            <td><?= $dh($c['frais_retablissement']) ?></td>
                            <td>
                                <?php if ($c['statut'] === 'PROGRAMMEE'): ?>
                                    <div class="m4-stack">
                                        <?php if ($c['reclamations_bloquantes'] > 0): ?>
                                            <span class="m4-tag m4-tag--warning">Suspendue : réclamation en cours</span>
                                        <?php else: ?>
                                            <form method="post" action="<?= BASE_URL ?>Coupure/executer"
                                                  data-confirm="Le service de ce contrat va être coupé. Les autres contrats du client ne sont pas concernés."
                                                  data-confirm-title="Exécuter la coupure"
                                                  data-confirm-detail="Contrat <?= htmlspecialchars($c['numero_contrat'] ?? '', ENT_QUOTES) ?>"
                                                  data-confirm-label="Couper le service" data-confirm-danger="1">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
                                                <input type="hidden" name="id_coupure" value="<?= (int) $c['id_coupure'] ?>">
                                                <button class="m1-button m1-button--small m4-btn-danger" type="submit">Exécuter la coupure</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="<?= BASE_URL ?>Coupure/annuler"
                                              data-confirm="Annuler cette coupure programmée ? Le client sera prévenu."
                                              data-confirm-title="Annuler la coupure" data-confirm-label="Annuler la coupure">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
                                            <input type="hidden" name="id_coupure" value="<?= (int) $c['id_coupure'] ?>">
                                            <button class="m1-button m1-button--secondary m1-button--small" type="submit">Annuler</button>
                                        </form>
                                    </div>
                                <?php elseif ($c['statut'] === 'EFFECTUEE'): ?>
                                    <details class="m4-details">
                                        <summary class="m1-button m1-button--small">Rétablir le service</summary>
                                        <form class="m1-edit-form" method="post" action="<?= BASE_URL ?>Coupure/retablir"
                                              data-confirm="Rétablir le service de ce contrat ?"
                                              data-confirm-title="Rétablir le service"
                                              data-confirm-detail="Frais encaissés : {frais} DH"
                                              data-confirm-label="Rétablir le service">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
                                            <input type="hidden" name="id_coupure" value="<?= (int) $c['id_coupure'] ?>">
                                            <label>Frais encaissés (DH)
                                                <input type="text" name="frais" inputmode="decimal"
                                                       value="<?= htmlspecialchars(number_format((float) $c['frais_retablissement'], 2, '.', ''), ENT_QUOTES) ?>" required>
                                            </label>
                                            <button class="m1-button m1-button--small" type="submit">Rétablir</button>
                                        </form>
                                    </details>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>