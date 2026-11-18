<?php
// Variables fournies par Coupure_index : $coupures, $contrats, $motifs, $statuts, $filtre, $frais, $message, $erreur, $rapport
$titreFournisseur = 'Coupures';
$sousTitreFournisseur = 'Chaque coupure vise un seul contrat : les autres contrats du même client ne sont pas touchés.';
include ROOT . 'IHM/layouts/fournisseur_header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/Coupure.css?v=<?= filemtime(ROOT . 'assets/css/Coupure.css') ?>">
<style>
.cp-nom { font-weight: 600; }
.cp-sub { display: inline-block; font-size: .82rem; color: #6b7a72; white-space: nowrap; }
.cp-num { font-weight: 600; white-space: nowrap; }
.m1-table th, .m1-table td { padding-left: 12px; padding-right: 12px; }
.m1-table .m4-tag--warning { white-space: normal; line-height: 1.35; border-radius: 12px; max-width: 190px; }
</style>
<?php

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

<?php if ($rapport):
    // ===== Rapport des tâches planifiées : mise en page =====
    // couleur : info (bleu) | warn (ambre) | danger (rouge) | action (orange) | ok (vert)
    $rt_sections = [
        'estimations'         => ['Estimations générées',        'Factures estimées pour le mois écoulé',            'info'],
        'penalites'           => ['Pénalités de retard',         'Pénalité appliquée aux factures en retard',        'warn'],
        'coupures'            => ['Coupures programmées',        'Trois mois impayés consécutifs',                   'danger'],
        'non_declares'        => ['Sans déclaration',            'Contrats qui ne déclarent plus leur consommation', 'warn'],
        'sanctions'           => ['Coupures pour sanction',      'Sanction non payée',                               'danger'],
        'sanctions_annulees'  => ['Coupures annulées',           'Sanction payée : la coupure est annulée',          'ok'],
        'sanctions_a_traiter' => ['Sanctions sans contrat',      'À traiter à la main : contrat à préciser',         'action'],
    ];
    // « REF (Nom) : détail »  ->  [REF, Nom, détail]
    $rt_decouper = function ($texte) {
        if (preg_match('/^([^\s:(]+)\s*(?:\((.+?)\))?\s*:\s*(.+)$/u', (string) $texte, $m)) {
            return [$m[1], $m[2] ?? '', $m[3]];
        }
        return [(string) $texte, '', ''];
    };
    $rt_parts = explode(' ', (string) ($rapport['date'] ?? ''), 2);
    $rt_sous_titre = $rt_parts[0] !== '' ? 'Exécuté le ' . $rt_parts[0] . (isset($rt_parts[1]) ? ' à ' . $rt_parts[1] : '') : '';

    $rt_tuiles = [];
    $rt_zeros = [];
    $rt_blocs = [];
    $rt_a_traiter = 0;
    foreach ($rt_sections as $rt_cle => $rt_def) {
        if (!array_key_exists($rt_cle, $rapport)) {
            continue;
        }
        $rt_val = $rapport[$rt_cle];
        $rt_nb = is_array($rt_val) ? count($rt_val) : (int) $rt_val;
        if (!in_array($rt_def[2], ['info', 'ok'], true) && $rt_nb > 0) {
            $rt_a_traiter += $rt_nb;
        }
        // « Coupures annulées » à 0 : on ne l'affiche pas dans la synthèse
        if ($rt_cle === 'sanctions_annulees' && $rt_nb === 0) {
            continue;
        }
                if ($rt_nb === 0) {
            $rt_zeros[] = $rt_def[0];
            continue;
        }
        $rt_tuiles[] = ['label' => $rt_def[0], 'nb' => $rt_nb, 'tone' => $rt_def[2]];
        if (is_array($rt_val) && $rt_nb > 0) {
            $rt_blocs[] = ['label' => $rt_def[0], 'desc' => $rt_def[1], 'tone' => $rt_def[2], 'items' => $rt_val, 'nb' => $rt_nb];
        }
    }
    $rt_erreurs = (isset($rapport['erreurs']) && is_array($rapport['erreurs'])) ? $rapport['erreurs'] : [];
?>
        <style>
.rt {
  --rt-ink: #14231c;
  --rt-muted: #6b7a72;
  --rt-line: #e3e8e5;
  --rt-bg: #ffffff;
  --rt-soft: #f6f8f7;

  --rt-info: #2457a6;     --rt-info-bg: #e8f0fb;
  --rt-warn: #a85a00;     --rt-warn-bg: #fff3e0;
  --rt-danger: #b42318;   --rt-danger-bg: #fdecea;
  --rt-action: #c2410c;   --rt-action-bg: #ffedd5;
  --rt-ok: #1b6b43;       --rt-ok-bg: #e3f3ea;

  background: var(--rt-bg);
  border: 1px solid var(--rt-line);
  border-top: 4px solid #1d5a42;
  border-radius: 14px;
  padding: 20px 24px 22px;
  margin: 0 0 22px;
  color: var(--rt-ink);
  box-shadow: 0 1px 2px rgba(15, 52, 38, .05);
}

/* ---- En-tête ---- */
.rt__head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 18px; }
.rt__title { margin: 0; font-size: 1.15rem; font-weight: 700; letter-spacing: -.01em; }
.rt__sub { margin: 4px 0 0; font-size: .85rem; color: var(--rt-muted); }
.rt__head-right { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.rt__close { background: none; border: 0; cursor: pointer; font-size: 1.4rem; line-height: 1; color: var(--rt-muted); padding: 2px 6px; border-radius: 6px; }
.rt__close:hover { background: var(--rt-soft); color: var(--rt-ink); }

.rt-badge { display: inline-block; padding: 5px 12px; border-radius: 999px; font-size: .8rem; font-weight: 700; white-space: nowrap; }
.rt-badge--danger { background: var(--rt-danger-bg); color: var(--rt-danger); }
.rt-badge--ok { background: var(--rt-ok-bg); color: var(--rt-ok); }

/* ---- Synthèse : une tuile par type de tâche ---- */
.rt__tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; margin-bottom: 18px; }
.rt-tile { display: flex; flex-direction: column; gap: 2px; padding: 12px 14px; border-radius: 10px; border-left: 4px solid transparent; background: var(--rt-soft); }
.rt-tile__nb { font-size: 1.7rem; font-weight: 800; line-height: 1.1; }
.rt-tile__label { font-size: .8rem; font-weight: 600; color: var(--rt-muted); }
.rt-tile--info   { background: var(--rt-info-bg);   border-left-color: var(--rt-info); }   .rt-tile--info .rt-tile__nb   { color: var(--rt-info); }
.rt-tile--warn   { background: var(--rt-warn-bg);   border-left-color: var(--rt-warn); }   .rt-tile--warn .rt-tile__nb   { color: var(--rt-warn); }
.rt-tile--danger { background: var(--rt-danger-bg); border-left-color: var(--rt-danger); } .rt-tile--danger .rt-tile__nb { color: var(--rt-danger); }
.rt-tile--action { background: var(--rt-action-bg); border-left-color: var(--rt-action); } .rt-tile--action .rt-tile__nb { color: var(--rt-action); }
.rt-tile--zero   { background: var(--rt-soft);      border-left-color: #c9d3ce; }          .rt-tile--zero .rt-tile__nb   { color: #9aa8a0; }

/* ---- Détail : une carte par type de tâche (liste courte, jamais de longue ligne) ---- */
.rt__blocs { display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 14px; align-items: start; }
.rt-bloc { border: 1px solid var(--rt-line); border-radius: 12px; background: #fff; overflow: hidden; }
.rt-bloc__head { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 11px 14px; cursor: pointer; list-style: none; font-weight: 700; font-size: .95rem; }
.rt-bloc__head::-webkit-details-marker { display: none; }
.rt-bloc__head::after { content: "▾"; margin-left: auto; font-size: .8rem; color: var(--rt-muted); transition: transform .15s; }
.rt-bloc:not([open]) .rt-bloc__head::after { transform: rotate(-90deg); }
.rt-bloc__nb { order: 2; min-width: 26px; text-align: center; padding: 2px 9px; border-radius: 999px; font-size: .8rem; font-weight: 800; }
.rt-bloc__head::after { order: 3; }
.rt-bloc__desc { margin: 0; padding: 0 14px 10px; font-size: .82rem; color: var(--rt-muted); }

.rt-bloc--info   .rt-bloc__head { background: var(--rt-info-bg); }   .rt-bloc--info   .rt-bloc__nb { background: var(--rt-info);   color: #fff; }
.rt-bloc--warn   .rt-bloc__head { background: var(--rt-warn-bg); }   .rt-bloc--warn   .rt-bloc__nb { background: var(--rt-warn);   color: #fff; }
.rt-bloc--danger .rt-bloc__head { background: var(--rt-danger-bg); } .rt-bloc--danger .rt-bloc__nb { background: var(--rt-danger); color: #fff; }
.rt-bloc--action .rt-bloc__head { background: var(--rt-action-bg); } .rt-bloc--action .rt-bloc__nb { background: var(--rt-action); color: #fff; }
.rt-bloc__desc { padding-top: 10px; }

.rt-list { list-style: none; margin: 0; padding: 0 14px 6px; }
.rt-item { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; padding: 9px 0; border-top: 1px solid var(--rt-line); font-size: .88rem; }
.rt-item:first-child { border-top: 0; }
.rt-item__ref { font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace; font-size: .8rem; font-weight: 700; color: var(--rt-ink); overflow-wrap: anywhere; }
.rt-item__nom { color: var(--rt-muted); }
.rt-item__detail { margin-left: auto; padding: 2px 10px; border-radius: 999px; font-size: .78rem; font-weight: 700; white-space: nowrap; background: var(--rt-soft); color: var(--rt-ink); }
.rt-bloc--info   .rt-item__detail { background: var(--rt-info-bg);   color: var(--rt-info); }
.rt-bloc--warn   .rt-item__detail { background: var(--rt-warn-bg);   color: var(--rt-warn); }
.rt-bloc--danger .rt-item__detail { background: var(--rt-danger-bg); color: var(--rt-danger); }
.rt-bloc--action .rt-item__detail { background: var(--rt-action-bg); color: var(--rt-action); }

/* ---- Erreurs ---- */
.rt__erreurs { margin-top: 16px; padding: 12px 16px; border-radius: 10px; background: var(--rt-danger-bg); border: 1px solid #f3c4c1; color: var(--rt-danger); font-size: .88rem; }
.rt__erreurs ul { margin: 6px 0 0; padding-left: 18px; }

@media (max-width: 640px) {
  .rt { padding: 16px; }
  .rt__head { flex-direction: column; }
  .rt__blocs { grid-template-columns: 1fr; }
}

.rt-tile--ok   { background: var(--rt-ok-bg);   border-left-color: var(--rt-ok); }   .rt-tile--ok .rt-tile__nb   { color: var(--rt-ok); }
.rt-bloc--ok   .rt-bloc__head { background: var(--rt-ok-bg); }   .rt-bloc--ok   .rt-bloc__nb { background: var(--rt-ok);   color: #fff; }
.rt-bloc--ok   .rt-item__detail { background: var(--rt-ok-bg);   color: var(--rt-ok); }
.rt__zeros { margin: 0 0 18px; padding: 9px 14px; border-radius: 10px; background: var(--rt-ok-bg); color: var(--rt-ok); font-size: .85rem; font-weight: 600; }

        </style>

        <section class="rt" aria-label="Rapport des tâches planifiées">
            <header class="rt__head">
                <div>
                    <h3 class="rt__title">Rapport des tâches planifiées</h3>
                    <?php if ($rt_sous_titre !== ''): ?>
                        <p class="rt__sub"><?= htmlspecialchars($rt_sous_titre) ?><?php if (!empty($rapport['mois_estime'])): ?> · Mois estimé : <?= htmlspecialchars($rapport['mois_estime']) ?><?php endif; ?></p>
                    <?php endif; ?>
                </div>
                <div class="rt__head-right">
                    <?php if ($rt_a_traiter > 0): ?>
                        <span class="rt-badge rt-badge--danger"><?= (int) $rt_a_traiter ?> élément<?= $rt_a_traiter > 1 ? 's' : '' ?> à surveiller</span>
                    <?php else: ?>
                        <span class="rt-badge rt-badge--ok">Rien à signaler</span>
                    <?php endif; ?>
                    <button class="rt__close" type="button" aria-label="Fermer le rapport" onclick="this.closest('.rt').remove()">×</button>
                </div>
            </header>

                       <?php if ($rt_tuiles): ?>
                <div class="rt__tiles">
                    <?php foreach ($rt_tuiles as $rt_t): ?>
                        <div class="rt-tile rt-tile--<?= htmlspecialchars($rt_t['tone']) ?>">
                            <span class="rt-tile__nb"><?= (int) $rt_t['nb'] ?></span>
                            <span class="rt-tile__label"><?= htmlspecialchars($rt_t['label']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($rt_zeros): ?>
                <p class="rt__zeros">Rien à signaler pour : <?= htmlspecialchars(implode(' · ', $rt_zeros)) ?></p>
            <?php endif; ?>

            <?php if ($rt_blocs): ?>
                <div class="rt__blocs">
                    <?php foreach ($rt_blocs as $rt_b): ?>
                        <details class="rt-bloc rt-bloc--<?= htmlspecialchars($rt_b['tone']) ?>" open>
                            <summary class="rt-bloc__head">
                                <span class="rt-bloc__titre"><?= htmlspecialchars($rt_b['label']) ?></span>
                                <span class="rt-bloc__nb"><?= (int) $rt_b['nb'] ?></span>
                            </summary>
                            <p class="rt-bloc__desc"><?= htmlspecialchars($rt_b['desc']) ?></p>
                            <ul class="rt-list">
                                <?php foreach ($rt_b['items'] as $rt_item): ?>
                                    <?php [$rt_ref, $rt_nom, $rt_detail] = $rt_decouper($rt_item); ?>
                                    <li class="rt-item">
                                        <span class="rt-item__ref"><?= htmlspecialchars($rt_ref) ?></span>
                                        <?php if ($rt_nom !== ''): ?><span class="rt-item__nom"><?= htmlspecialchars($rt_nom) ?></span><?php endif; ?>
                                        <?php if ($rt_detail !== ''): ?><span class="rt-item__detail"><?= htmlspecialchars($rt_detail) ?></span><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($rt_erreurs): ?>
                <div class="rt__erreurs" role="alert">
                    <strong>Erreurs rencontrées</strong>
                    <ul>
                        <?php foreach ($rt_erreurs as $rt_e): ?>
                            <li><?= htmlspecialchars($rt_e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <div class="m4-barre">
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

        <form method="post" action="<?= BASE_URL ?>Rappel/executer"
              data-confirm="Estimer le mois écoulé, appliquer les pénalités de retard, programmer les coupures à trois mois impayés et envoyer les alertes ?"
              data-confirm-title="Exécuter les tâches planifiées"
              data-confirm-label="Exécuter">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
            <button class="m1-button m1-button--secondary" type="submit">Exécuter les tâches planifiées</button>
        </form>
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
        <td>
            <span class="cp-nom"><?= htmlspecialchars($c['nom'] . ' ' . $c['prenom']) ?></span><br>
            <span class="cp-sub"><?= htmlspecialchars($c['numero_client']) ?></span>
        </td>
        <td>
            <span class="cp-num"><?= htmlspecialchars($c['numero_contrat'] ?? '—') ?></span>
            <?php if (!empty($c['quartier'])): ?><br><span class="cp-sub"><?= htmlspecialchars($c['quartier']) ?></span><?php endif; ?>
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