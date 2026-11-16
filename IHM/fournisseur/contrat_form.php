<?php
$titreFournisseur = $bouton_formulaire ?? 'Contrat';
$sousTitreFournisseur = 'Renseignez les informations du contrat.';
include ROOT . 'IHM/layouts/fournisseur_header.php';
?>
<div class="formulaire-client-page">
    <a href="<?= BASE_URL ?>Contrat/index" class="retour-clients">&larr; Retour à la liste des contrats</a>
    <div class="formulaire-client-carte">
        <h2><?= htmlspecialchars($bouton_formulaire ?? 'Contrat') ?></h2>

        <?php if (isset($erreur)) { ?>
            <div class="erreur-client"><?= htmlspecialchars($erreur) ?></div>
        <?php } ?>

        <form method="post" action="<?= BASE_URL . ($action_formulaire ?? 'Contrat/index') ?>" class="formulaire-client fournisseur-form">
            <div class="champ-client">
                <label for="id_client">Client</label>
                <select id="id_client" name="id_client" required>
                    <option value="">Choisir un client</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client['id_client'] ?>" <?= ((int) ($contratActuel['id_client'] ?? 0) === (int) $client['id_client']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($client['nom'] . ' ' . $client['prenom'] . ' - ' . $client['numero_client']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="champ-client">
                <label for="id_quartier">Quartier</label>
                <select id="id_quartier" name="id_quartier">
                    <option value="">Aucun quartier</option>
                    <?php foreach ($quartiers as $quartier): ?>
                        <option value="<?= (int) $quartier['id_quartier'] ?>" <?= ((int) ($contratActuel['id_quartier'] ?? 0) === (int) $quartier['id_quartier']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($quartier['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="champ-client">
                <label for="numero_contrat">Numéro de contrat</label>
                <input type="text" id="numero_contrat" name="numero_contrat" value="<?= htmlspecialchars($contratActuel['numero_contrat'] ?? '') ?>" maxlength="40" required>
            </div>

            <div class="champ-client">
                <label for="numero_compteur_eau">N° compteur eau</label>
                <input type="text" id="numero_compteur_eau" name="numero_compteur_eau" value="<?= htmlspecialchars($contratActuel['numero_compteur_eau'] ?? '') ?>" maxlength="50" required>
            </div>

            <div class="champ-client">
                <label for="numero_compteur_elec">N° compteur électricité</label>
                <input type="text" id="numero_compteur_elec" name="numero_compteur_elec" value="<?= htmlspecialchars($contratActuel['numero_compteur_elec'] ?? '') ?>" maxlength="50" required>
            </div>

            <div class="champ-client">
                <label for="index_initial_eau">Index initial eau</label>
                <input type="number" id="index_initial_eau" name="index_initial_eau" min="0" value="<?= (int) ($contratActuel['index_initial_eau'] ?? 0) ?>" required>
            </div>

            <div class="champ-client">
                <label for="index_initial_elec">Index initial électricité</label>
                <input type="number" id="index_initial_elec" name="index_initial_elec" min="0" value="<?= (int) ($contratActuel['index_initial_elec'] ?? 0) ?>" required>
            </div>

            <div class="champ-client">
                <label for="statut">Statut</label>
                <select id="statut" name="statut">
                    <option value="ACTIF" <?= (($contratActuel['statut'] ?? 'ACTIF') === 'ACTIF') ? 'selected' : '' ?>>Actif</option>
                    <option value="SUSPENDU" <?= (($contratActuel['statut'] ?? 'ACTIF') === 'SUSPENDU') ? 'selected' : '' ?>>Suspendu</option>
                    <option value="RESILIE" <?= (($contratActuel['statut'] ?? 'ACTIF') === 'RESILIE') ? 'selected' : '' ?>>Résilié</option>
                </select>
            </div>

            <div class="actions-formulaire-client">
                <button type="submit" class="bouton-client"><?= htmlspecialchars($bouton_formulaire ?? 'Valider') ?></button>
                <button type="reset" class="bouton-annuler-client">Annuler</button>
            </div>
        </form>
    </div>
</div>
<?php include ROOT . 'IHM/layouts/fournisseur_footer.php'; ?>
