<?php
$erreur = $erreur ?? '';
$message = $message ?? '';
$contrats = $contrats ?? [];
$historique = $historique ?? [];
$agentPageTitle = 'Contrôle annuel';
$agentPageSubtitle = 'Comparez les consommations déclarées avec les relevés réalisés sur le terrain.';
$agentPageActive = 'controle';

include ROOT . 'IHM/layouts/agent_header.php';
?>

<section class="agent-card">
    <div class="agent-card__heading">
        <div>
            <h2>Enregistrer un contrôle</h2>
            <p>Un contrôle est enregistré par contrat et par année.</p>
        </div>
        <span class="agent-card__count">01</span>
    </div>

    <?php if (!$contrats): ?>
        <p class="agent-empty">Aucun contrat actif n’est affecté à votre quartier pour le moment.</p>
    <?php else: ?>
        <form class="agent-form" method="post" action="<?= BASE_URL ?>Controle/enregistrer">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1(), ENT_QUOTES, 'UTF-8') ?>">
            <label>
                <span>Contrat contrôlé</span>
                <select name="id_contrat" required>
                    <option value="">Choisir un contrat</option>
                    <?php foreach ($contrats as $contrat): ?>
                        <option value="<?= (int) $contrat['id_contrat'] ?>">
                            <?= htmlspecialchars($contrat['numero_contrat'] . ' — ' . trim(($contrat['prenom'] ?? '') . ' ' . ($contrat['nom'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><span>Année contrôlée</span><input type="number" name="annee" min="2000" max="<?= (int) date('Y') ?>" value="<?= (int) date('Y') ?>" required></label>
            <label><span>Consommation réelle d’eau <small>en m³</small></span><input type="number" name="conso_eau_reelle" min="0" step="1" required></label>
            <label><span>Consommation réelle d’électricité <small>en kWh</small></span><input type="number" name="conso_elec_reelle" min="0" step="1" required></label>
            <label><span>Date du relevé</span><input type="date" name="date_releve" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" max="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" required></label>
            <div class="agent-actions"><button class="agent-button" type="submit"><?= $agentIcon('check') ?> Enregistrer le contrôle</button></div>
        </form>
    <?php endif; ?>
</section>

<section class="agent-card agent-section-spacer">
    <div class="agent-card__heading">
        <div>
            <h2>Mes contrôles récents</h2>
            <p>Suivi des vérifications annuelles effectuées par vos soins.</p>
        </div>
        <span class="agent-card__count"><?= count($historique) ?></span>
    </div>
    <?php if (!$historique): ?>
        <p class="agent-empty">Aucun contrôle annuel enregistré pour le moment.</p>
    <?php else: ?>
        <div class="agent-table-wrap">
            <table class="agent-table">
                <thead><tr><th>Année</th><th>Contrat</th><th>Résultat</th><th>Validation</th><th>Date du relevé</th></tr></thead>
                <tbody>
                    <?php foreach ($historique as $controle): ?>
                        <?php
                        $resultat = (string) ($controle['resultat'] ?? '');
                        $statutValidation = (string) ($controle['statut_validation'] ?? '');
                        $resultatClass = $resultat === 'CONFORME' ? 'agent-badge--ok' : ($resultat === 'SOUS_DECLARATION' ? 'agent-badge--danger' : 'agent-badge--pending');
                        $validationClass = $statutValidation === 'VALIDE_PAR_FOURNISSEUR' ? 'agent-badge--ok' : ($statutValidation === 'REJETE' ? 'agent-badge--danger' : 'agent-badge--pending');
                        ?>
                        <tr>
                            <td><span class="agent-table__primary"><?= (int) ($controle['annee'] ?? 0) ?></span></td>
                            <td><?= htmlspecialchars($controle['numero_contrat'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="agent-badge <?= $resultatClass ?>"><?= htmlspecialchars(str_replace('_', ' ', $resultat), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><span class="agent-badge <?= $validationClass ?>"><?= htmlspecialchars(str_replace('_', ' ', $statutValidation), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars($controle['date_releve_agent'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php include ROOT . 'IHM/layouts/agent_footer.php'; ?>
