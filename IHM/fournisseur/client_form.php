<center>
    <form method="post" action="<?= BASE_URL . $action_formulaire ?>">
        <?php if (isset($erreur)){ ?>
    <div style="color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
        <?= $erreur; ?>
    </div>
        <?php } ?>
        <table>
            <tr>
                <td>Nom</td>
                <td><input type="text" name="nom" value="<?= htmlspecialchars($client_actuel['nom'] ?? '') ?>" required></td>
            </tr>
            <tr>
                <td>Prenom</td>
                <td><input type="text" name="prenom" value="<?= htmlspecialchars($client_actuel['prenom'] ?? '') ?>" required></td>
            </tr>
            <tr>
                <td>CIN</td> 
                <td><input type="text" name="cin" value="<?= htmlspecialchars($client_actuel['cin'] ?? '') ?>" minlength="5" maxlength="8" pattern="[a-zA-Z]{1,2}[0-9]{4,6}" required></td>
            </tr>
            <tr>
                <td>Adresse</td>
                <td><input type="text" name="adresse" value="<?= htmlspecialchars($client_actuel['adresse'] ?? '') ?>" required></td>
            </tr>
            <tr>
                <td>Telephone</td>
                <td><input type="tel" name="telephone" value="<?= htmlspecialchars($client_actuel['telephone'] ?? '') ?>" pattern="0[567][0-9]{8}" required></td>
            </tr>
            <tr>
                <td>Numero compteur eau</td>
                <td><input type="text" name="numero_compteur_eau" value="<?= htmlspecialchars($client_actuel['numero_compteur_eau'] ?? '') ?>" maxlength="20" placeholder="A1945678" required></td>
            </tr>
            <tr>
                <td>Numero compteur electricite</td>
                <td><input type="text" name="numero_compteur_elec" value="<?= htmlspecialchars($client_actuel['numero_compteur_elec'] ?? '') ?>" maxlength="20" placeholder="0102938475" required></td>
            </tr> 
            
            <tr>
                <td>Index initial eau</td>
                <td><input type="number" name="index_initial_eau" value="<?= htmlspecialchars($client_actuel['index_initial_eau'] ?? '') ?>" min="0" required></td>
            </tr>    
            <tr>
                <td>Index initial electricite</td>
                <td><input type="number" name="index_initial_elec" value="<?= htmlspecialchars($client_actuel['index_initial_elec'] ?? '') ?>" min="0" required></td>
            </tr>
            <tr>
                <td><input type="submit" value="<?= htmlspecialchars($bouton_formulaire) ?>"></td>
                <td><input type="reset" value="Annuler"></td>
            </tr>
        </table>
    </form>
</center>