<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ma déclaration</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/consommation.css">
    <script src="<?= BASE_URL ?>assets/js/declaration.js" defer></script>
</head>
<body>

<?php include ROOT . 'IHM/layouts/sidebar_client.php'; ?>

<div class="main">

    <header class="topbar">
        <span class="statut"><span class="point"></span>Portail opérationnel</span>
        <a class="deconnexion" href="<?= BASE_URL ?>Auth/logout">Déconnexion</a>
    </header>

    <div class="contenu">
        <h1>Ma déclaration</h1>

        <?php if ($erreur): ?>
            <p class="message message-erreur"><?= htmlspecialchars($erreur) ?></p>
        <?php endif; ?>

        <?php if ($succes): ?>
            <p class="message message-succes"><?= htmlspecialchars($succes) ?></p>
        <?php endif; ?>

        <div class="bandeau">
            <?php if ((int) date('j') >= 18): ?>
                <strong>Période ouverte.</strong> Une seule déclaration est autorisée par mois.
            <?php else: ?>
                <strong>Période fermée.</strong> La saisie ouvre le 18 du mois.
            <?php endif; ?>
        </div>

        <form method="POST" action="<?= BASE_URL ?>Consommation/enregistrer" enctype="multipart/form-data">
            <div class="grille">

                <section class="carte">
                    <div class="carte-entete">
                        <span class="icone">💧</span>
                        <div>
                            <h2>Compteur eau</h2>
                            <small>Relevé mensuel</small>
                        </div>
                    </div>

                    <div class="precedent">
                        <span>Index précédent</span>
                        <strong id="eau-prec" data-valeur="<?= $eauPrecedent ?>"><?= $eauPrecedent ?> m³</strong>
                    </div>

                    <label for="index_eau">Nouvel index</label>
                    <input type="text" id="index_eau" name="index_eau" required>

                    <p class="conso">Consommation calculée : <span id="conso-eau">—</span></p>

                    <label class="bouton-fichier">
                        Ajouter une photo du compteur
                        <input type="file" id="photo_eau" name="photo_eau" accept="image/jpeg,image/png" class="cache" required>
                    </label>
                    <img id="apercu-eau" class="apercu" alt="Aperçu de la photo eau" style="display:none">
                    <p class="aide">JPG ou PNG · 5 Mo maximum</p>
                </section>

                <section class="carte">
                    <div class="carte-entete">
                        <span class="icone">⚡</span>
                        <div>
                            <h2>Compteur électricité</h2>
                            <small>Relevé mensuel</small>
                        </div>
                    </div>

                    <div class="precedent">
                        <span>Index précédent</span>
                        <strong id="elec-prec" data-valeur="<?= $elecPrecedent ?>"><?= $elecPrecedent ?> kWh</strong>
                    </div>

                    <label for="index_elec">Nouvel index</label>
                    <input type="text" id="index_elec" name="index_elec" required>

                    <p class="conso">Consommation calculée : <span id="conso-elec">—</span></p>

                    <label class="bouton-fichier">
                        Ajouter une photo du compteur
                        <input type="file" id="photo_elec" name="photo_elec" accept="image/jpeg,image/png" class="cache" required>
                    </label>
                    <img id="apercu-elec" class="apercu" alt="Aperçu de la photo électricité" style="display:none">
                    <p class="aide">JPG ou PNG · 5 Mo maximum</p>
                </section>

            </div>

            <div class="pied">
                <button type="submit">Envoyer ma déclaration →</button>
            </div>
        </form>
    </div>

</div>

</body>
</html>