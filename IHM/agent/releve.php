<?php
$contrats = $contrats ?? [];
$idContrat = (int) ($idContrat ?? 0);
$moisSelectionne = (int) ($moisSelectionne ?? date('n'));
$anneeSelectionnee = (int) ($anneeSelectionnee ?? date('Y'));
$contratSelectionne = $contratSelectionne ?? null;
$indexPrecedents = $indexPrecedents ?? null;
$erreur = $erreur ?? '';
$message = $message ?? '';
$agentPageTitle = 'Saisie mensuelle';
$agentPageSubtitle = 'Choisissez un contrat de votre tournée et enregistrez les index observés sur place.';
$agentPageActive = 'releve';

include ROOT . 'IHM/layouts/agent_header.php';
?>

<section class="agent-card">
    <div class="agent-card__heading">
        <div>
            <h2>Sélection de la visite</h2>
            <p>La saisie est rattachée au contrat, au mois et à l’année choisis.</p>
        </div>
        <span class="agent-card__count">01</span>
    </div>
    <form class="agent-form agent-form--filters" method="get" action="<?= BASE_URL ?>Consommation/saisie_mensuelle">
        <label>
            <span>Contrat de votre tournée</span>
            <select name="contrat" required>
                <option value="">Choisir un contrat</option>
                <?php foreach ($contrats as $contrat): ?>
                    <option value="<?= (int) $contrat['id_contrat'] ?>" <?= (int) $contrat['id_contrat'] === $idContrat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($contrat['numero_contrat'] . ' — ' . trim(($contrat['prenom'] ?? '') . ' ' . ($contrat['nom'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Mois du relevé</span>
            <select name="mois" required>
                <?php foreach ([1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'] as $numeroMois => $nomMois): ?>
                    <option value="<?= $numeroMois ?>" <?= $numeroMois === $moisSelectionne ? 'selected' : '' ?>><?= htmlspecialchars($nomMois, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Année</span>
            <input type="number" name="annee" min="2000" max="<?= (int) date('Y') ?>" value="<?= $anneeSelectionnee ?>" required>
        </label>
        <div class="agent-actions"><button class="agent-button" type="submit"><?= $agentIcon('search') ?> Afficher</button></div>
    </form>

    <?php if ($contratSelectionne && $indexPrecedents): ?>
        <div class="agent-reading-summary">
            <div class="agent-reading-summary__identity">
                <strong><?= htmlspecialchars($contratSelectionne['numero_contrat'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                <span><?= htmlspecialchars(trim(($contratSelectionne['prenom'] ?? '') . ' ' . ($contratSelectionne['nom'] ?? '')), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($contratSelectionne['adresse'] ?? 'Adresse non renseignée', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="agent-reading-meter"><span>Dernier index eau</span><strong><?= (int) $indexPrecedents['eau'] ?> m³</strong></div>
            <div class="agent-reading-meter"><span>Dernier index électricité</span><strong><?= (int) $indexPrecedents['elec'] ?> kWh</strong></div>
        </div>
    <?php endif; ?>
</section>

<?php if ($contratSelectionne): ?>
    <section class="agent-card agent-section-spacer">
        <div class="agent-card__heading">
            <div>
                <h2>Nouveau relevé</h2>
                <p>Vérifiez les numéros de compteurs avant d’enregistrer les index.</p>
            </div>
            <span class="agent-card__count">02</span>
        </div>
        <form class="agent-form agent-entry-form" method="post" action="<?= BASE_URL ?>Consommation/enregistrer_mensuelle">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id_contrat" value="<?= $idContrat ?>">
            <input type="hidden" name="mois" value="<?= $moisSelectionne ?>">
            <input type="hidden" name="annee" value="<?= $anneeSelectionnee ?>">

            <div class="agent-meter-card">
                <div class="agent-meter-card__title"><span class="agent-meter-card__icon"><?= $agentIcon('water') ?></span> Compteur eau</div>
                <label><span>Index eau relevé (m³)</span><input type="number" name="index_eau" min="0" step="1" required></label>
            </div>
            <div class="agent-meter-card">
                <div class="agent-meter-card__title"><span class="agent-meter-card__icon"><?= $agentIcon('bolt') ?></span> Compteur électricité</div>
                <label><span>Index électricité relevé (kWh)</span><input type="number" name="index_elec" min="0" step="1" required></label>
            </div>
            <label class="agent-form__full"><span>Date du relevé</span><input type="date" name="date_releve" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" max="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" required></label>
            <div class="agent-actions"><button class="agent-button" type="submit"><?= $agentIcon('check') ?> Enregistrer le relevé</button></div>
        </form>
    </section>
<?php elseif (!$contrats): ?>
    <section class="agent-card agent-section-spacer"><p class="agent-empty">Aucun contrat actif n’est affecté à votre quartier.</p></section>
<?php else: ?>
    <section class="agent-card agent-section-spacer"><p class="agent-empty">Choisissez un contrat pour afficher ses derniers index.</p></section>
<?php endif; ?>

<?php include ROOT . 'IHM/layouts/agent_footer.php'; ?>
