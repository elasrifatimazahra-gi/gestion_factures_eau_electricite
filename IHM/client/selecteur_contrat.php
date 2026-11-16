<?php
$idClientSelecteur = (int) ($_SESSION['id_client'] ?? 0);
$contratsSelecteur = $idClientSelecteur > 0 ? bd_contrat_lister($idClientSelecteur) : [];
$idContratSelecteur = (int) ($_SESSION['id_contrat'] ?? 0);
$routeRetourContrat = $routeRetourContrat ?? ($_GET['url'] ?? 'Auth/accueil');
if (!is_string($routeRetourContrat) || !preg_match(
    '#^(Auth/accueil|Consommation/declaration|Facture/liste|Facture/detail/[0-9]+|Reclamation/liste|Notification/liste)$#',
    $routeRetourContrat
)) {
    $routeRetourContrat = 'Auth/accueil';
}
?>
<?php if (count($contratsSelecteur) > 1): ?>
    <style>
        .selecteur-contrat-client { display: flex; align-items: center; gap: 8px; margin: 0 10px; }
        .selecteur-contrat-client label { color: #52635a; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .selecteur-contrat-client select { max-width: 190px; min-height: 36px; padding: 6px 9px; border: 1px solid #d6e1d9; border-radius: 7px; color: #26352c; background: #fff; }
        .selecteur-contrat-client button { min-height: 36px; padding: 6px 10px; border: 0; border-radius: 7px; color: #fff; background: #198754; cursor: pointer; }
        @media (max-width: 760px) { .selecteur-contrat-client { margin: 8px 0; } }
    </style>
    <form class="selecteur-contrat-client" method="post" action="<?= BASE_URL ?>Contrat/selectionner">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token_m1()) ?>">
        <input type="hidden" name="retour" value="<?= htmlspecialchars($routeRetourContrat, ENT_QUOTES, 'UTF-8') ?>">
        <label for="mes-contrats">Mes contrats</label>
        <select id="mes-contrats" name="id_contrat" required onchange="this.form.submit()">
            <?php foreach ($contratsSelecteur as $contratSelecteur): ?>
                <option value="<?= (int) $contratSelecteur['id_contrat'] ?>" <?= (int) $contratSelecteur['id_contrat'] === $idContratSelecteur ? 'selected' : '' ?>>
                    <?= htmlspecialchars($contratSelecteur['numero_contrat']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Changer</button>
    </form>
<?php endif; ?>
