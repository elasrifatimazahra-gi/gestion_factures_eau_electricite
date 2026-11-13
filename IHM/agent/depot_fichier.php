<?php
$erreur = $erreur ?? '';
$message = $message ?? '';
$agentPageTitle = 'Import annuel';
$agentPageSubtitle = 'Déposez un fichier pour transmettre plusieurs contrôles annuels en une seule opération.';
$agentPageActive = 'import';

include ROOT . 'IHM/layouts/agent_header.php';
?>

<section class="agent-card">
    <div class="agent-card__heading">
        <div>
            <h2>Importer le fichier annuel</h2>
            <p>Formats acceptés : CSV ou TXT · Taille maximale : 10 Mo.</p>
        </div>
        <span class="agent-card__count">01</span>
    </div>

    <form class="agent-form" method="post" action="<?= BASE_URL ?>Consommation/import_annuel" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1(), ENT_QUOTES, 'UTF-8') ?>">
        <label class="agent-dropzone agent-form__full" data-agent-dropzone>
            <input
                class="agent-dropzone__input"
                type="file"
                id="fichier_annuel"
                name="fichier"
                accept=".txt,.csv,text/plain,text/csv"
                aria-describedby="agent-dropzone-help agent-dropzone-file"
                required
            >
            <span class="agent-dropzone__icon"><?= $agentIcon('upload') ?></span>
            <strong>Déposez votre fichier annuel ici</strong>
            <span class="agent-dropzone__hint" id="agent-dropzone-help">ou cliquez pour parcourir vos fichiers · TXT ou CSV uniquement</span>
            <span class="agent-dropzone__filename" id="agent-dropzone-file" data-file-name aria-live="polite">Aucun fichier sélectionné</span>
        </label>
        
        <div class="agent-actions">
            <button class="agent-button" type="submit"><?= $agentIcon('upload') ?> Déposer et traiter</button>
        </div>
    </form>
</section>

<section class="agent-card agent-section-spacer">
    <div class="agent-card__heading">
        <div>
            <h2>Format attendu</h2>
            <p>Les champs doivent rester dans cet ordre sur chaque ligne.</p>
        </div>
    </div>
    <div class="agent-import-format">
        <div class="agent-import-field"><span>Champ 01</span><strong>ID_Contrat</strong></div>
        <div class="agent-import-field"><span>Champ 02</span><strong>ID_Agent</strong></div>
        <div class="agent-import-field"><span>Champ 03</span><strong>ConsommationEau</strong></div>
        <div class="agent-import-field"><span>Champ 04</span><strong>ConsommationElect</strong></div>
        <div class="agent-import-field"><span>Champ 05</span><strong>Annee</strong></div>
        <div class="agent-import-field"><span>Champ 06</span><strong>Date_Saisie</strong></div>
    </div>
    <code class="agent-code-sample">12;4;120;950;2025;2025-11-12</code>
    <p class="agent-help">Les lignes invalides seront consignées dans le rapport d’import, sans empêcher le traitement des autres lignes.</p>
</section>

<script src="<?= BASE_URL ?>assets/js/agent_import.js?v=<?= filemtime(ROOT . 'assets/js/agent_import.js') ?>" defer></script>
<?php include ROOT . 'IHM/layouts/agent_footer.php'; ?>
