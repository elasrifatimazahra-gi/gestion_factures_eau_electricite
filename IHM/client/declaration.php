<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ma déclaration</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/factures.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/consommation.css">
    <script src="<?= BASE_URL ?>assets/js/declaration.js" defer></script>
</head>
<body>
<div class="appli">

    <?php include ROOT . 'IHM/layouts/sidebar_client.php'; ?>

    <div class="principal">
        <header class="topbar">
            <div class="statut-portail"><span class="point-vert"></span> Portail opérationnel</div>
            <div class="topbar-droite">
                <a class="deconnexion" href="<?= BASE_URL ?>Auth/logout">Déconnexion</a>
            </div>
        </header>

        <main class="contenu">
            <h1>Ma déclaration</h1>
            <p class="sous-titre">Déclarez les index visibles sur vos compteurs avant le <?= htmlspecialchars($dateLimite) ?>.</p>

            <?php if ($erreur): ?>
                <p class="saisie-alerte saisie-alerte-erreur"><?= htmlspecialchars($erreur) ?></p>
            <?php endif; ?>

            <?php if ($succes): ?>
                <p class="saisie-alerte saisie-alerte-succes"><?= htmlspecialchars($succes) ?></p>
            <?php endif; ?>

            <?php $ouvert = (int) date('j') >= 18; ?>
            <section class="saisie-bandeau<?= $ouvert ? '' : ' ferme' ?>">
                <?php if ($ouvert): ?>
                    <strong>Période ouverte.</strong> Une seule déclaration est autorisée par mois.
                <?php else: ?>
                    <strong>Période fermée.</strong> La saisie ouvre le 18 du mois.
                <?php endif; ?>
            </section>

            <form method="POST" action="<?= BASE_URL ?>Consommation/enregistrer" enctype="multipart/form-data">
                <div class="saisie-grille">

                    <!-- Carte eau -->
                    <section class="saisie-carte">
                        <div class="saisie-entete">
                            <span class="saisie-icone">
                                <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/></svg>
                            </span>
                            <div>
                                <h2>Compteur eau</h2>
                                <small>Dernier relevé : <?= htmlspecialchars($dernierReleve) ?></small>
                            </div>
                        </div>

                        <div class="saisie-precedent">
                            <span>Index précédent</span>
                            <strong id="eau-prec" data-valeur="<?= $eauPrecedent ?>"><?= $eauPrecedent ?> m³</strong>
                        </div>

                        <label for="index_eau">Nouvel index</label>
                        <input type="text" id="index_eau" name="index_eau" inputmode="numeric" required>

                        <p class="saisie-conso">Consommation calculée : <span id="conso-eau">—</span></p>

                        <label class="saisie-fichier">
                            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/></svg>
                            Ajouter une photo du compteur
                            <input type="file" id="photo_eau" name="photo_eau" accept="image/jpeg,image/png" class="saisie-cache" required>
                        </label>
                        <img id="apercu-eau" class="saisie-apercu" alt="Aperçu de la photo eau" style="display:none">
                        <p class="saisie-aide">JPG ou PNG · 5 Mo maximum</p>
                    </section>

                    <!-- Carte électricité -->
                    <section class="saisie-carte">
                        <div class="saisie-entete">
                            <span class="saisie-icone">
                                <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                            </span>
                            <div>
                                <h2>Compteur électricité</h2>
                                <small>Dernier relevé : <?= htmlspecialchars($dernierReleve) ?></small>
                            </div>
                        </div>

                        <div class="saisie-precedent">
                            <span>Index précédent</span>
                            <strong id="elec-prec" data-valeur="<?= $elecPrecedent ?>"><?= $elecPrecedent ?> kWh</strong>
                        </div>

                        <label for="index_elec">Nouvel index</label>
                        <input type="text" id="index_elec" name="index_elec" inputmode="numeric" required>

                        <p class="saisie-conso">Consommation calculée : <span id="conso-elec">—</span></p>

                        <label class="saisie-fichier">
                            <svg class="ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                            Ajouter une photo du compteur
                            <input type="file" id="photo_elec" name="photo_elec" accept="image/jpeg,image/png" class="saisie-cache" required>
                        </label>
                        <img id="apercu-elec" class="saisie-apercu" alt="Aperçu de la photo électricité" style="display:none">
                        <p class="saisie-aide">JPG ou PNG · 5 Mo maximum</p>
                    </section>

                </div>

                <div class="saisie-pied">
                    <button type="submit">Envoyer ma déclaration →</button>
                </div>
            </form>
        </main>
    </div>

</div>
</body>
</html>