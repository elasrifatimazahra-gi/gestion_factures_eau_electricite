<center>
    <form method="post" action="../Client/add">
        <table>
            <tr>
                <td>Nom</td>
                <td><input type="text" name="nom" required></td>
            </tr>
            <tr>
                <td>Prenom</td>
                <td><input type="text" name="prenom" required></td>
            </tr>
            <tr>
                <td>CIN</td> 
                <td><input type="text" name="cin" minlength="5" maxlength="8" pattern="[a-zA-Z]{1,2}[0-9]{4,6}" required></td>
            </tr>
            <tr>
                <td>Adresse</td>
                <td><input type="text" name="adresse" required></td>
            </tr>
            <tr>
                <td>Telephone</td>
                <td><input type="tel" name="telephone" pattern="0[567][0-9]{8}" required></td>
            </tr>
            <tr>
                <td>Numero compteur eau</td>
                <td><input type="text" name="numero_compteur_eau" maxlength="20" placeholder="A1945678" required></td>
            </tr>
            <tr>
                <td>Numero compteur electricite</td>
                <td><input type="text" name="numero_compteur_elec" maxlength="20" placeholder="0102938475" required></td>
            </tr> 
            
            <tr>
                <td>Index initial eau</td>
                <td><input type="number" name="index_initial_eau" min="0" required></td>
            </tr>    
            <tr>
                <td>Index initial electricite</td>
                <td><input type="number" name="index_initial_elec" min="0" required></td>
            </tr>
            <tr>
                <td>Mot de passe</td>
                <td><input type="password" name="mot_de_passe" required></td>
            </tr>
            <tr>
                <td><input type="submit" value="Ajouter le client"></td>
                <td><input type="reset" value="Annuler"></td>
            </tr>
        </table>
    </form>
</center>