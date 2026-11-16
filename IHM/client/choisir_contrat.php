<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choisir un contrat</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/factures.css?v=<?= filemtime(ROOT . 'assets/css/factures.css') ?>">
    <style>
        .choix-contrat-page { max-width: 760px; margin: 8vh auto; padding: 24px; }
        .choix-contrat-page h1 { margin-bottom: 8px; }
        .choix-contrat-liste { display: grid; gap: 14px; margin: 24px 0; }
        .choix-contrat-item { display: flex; align-items: flex-start; gap: 14px; padding: 18px; border: 1px solid #dfe8e2; border-radius: 12px; cursor: pointer; }
        .choix-contrat-item:has(input:checked) { border-color: #198754; background: #f3fbf6; }
        .choix-contrat-item span { display: block; margin-top: 5px; color: #64736a; }
        .choix-contrat-page button { border: 0; border-radius: 8px; padding: 12px 20px; color: #fff; background: #198754; font: inherit; cursor: pointer; }
    </style>
</head>
<body>
    <main class="choix-contrat-page">
        <p class="eyebrow">ESPACE CLIENT</p>
        <h1>Choisissez un contrat</h1>
        <p class="sous-titre">Sélectionnez le contrat à consulter. Vous pourrez en changer à tout moment avec « Mes contrats ».</p>

        <?php if ($erreur !== ''): ?>
            <p class="m1-alert m1-alert--error"><?= htmlspecialchars($erreur) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= BASE_URL ?>Contrat/selectionner">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
            <input type="hidden" name="retour" value="Auth/accueil">
            <div class="choix-contrat-liste">
                <?php foreach ($contrats as $index => $contrat): ?>
                    <label class="choix-contrat-item">
                        <input type="radio" name="id_contrat" value="<?= (int) $contrat['id_contrat'] ?>" <?= $index === 0 ? 'checked' : '' ?> required>
                        <div>
                            <strong>Contrat <?= htmlspecialchars($contrat['numero_contrat']) ?></strong>
                            <span>Quartier : <?= htmlspecialchars($contrat['quartier_nom'] ?? 'Non affecté') ?></span>
                            <span>Statut : <?= htmlspecialchars($contrat['statut']) ?></span>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="submit">Continuer</button>
        </form>
    </main>
</body>
</html>
