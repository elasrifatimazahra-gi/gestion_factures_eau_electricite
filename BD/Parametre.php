
<?php

require_once __DIR__ . "/Connexion.php";

function lireParametre($cle, $defaut = null)
{
    $link = Connexion();

    $sql = mysqli_prepare(
        $link,
        "SELECT valeur FROM parametres WHERE cle = ?"
    );

    if (!$sql) {
        throw new RuntimeException("Impossible de préparer la lecture du paramètre : " . mysqli_error($link));
    }

    mysqli_stmt_bind_param($sql, "s", $cle);
    if (!mysqli_stmt_execute($sql)) {
        $erreur = mysqli_stmt_error($sql);
        mysqli_stmt_close($sql);
        throw new RuntimeException("Impossible de lire le paramètre : " . $erreur);
    }

    $res = mysqli_stmt_get_result($sql);
    $row = mysqli_fetch_assoc($res);

    mysqli_stmt_close($sql);

    if ($row && $row['valeur'] !== null) {
        return $row['valeur'];
    }

    return $defaut;
}

function bd_parametre_lister()
{
    $link = Connexion();
    $resultat = mysqli_query($link, "SELECT id_parametre, cle, libelle, valeur, type_donnee, description, updated_at
                                     FROM parametres
                                     ORDER BY id_parametre");
    if (!$resultat) {
        throw new RuntimeException("Impossible de récupérer les paramètres : " . mysqli_error($link));
    }
    return mysqli_fetch_all($resultat, MYSQLI_ASSOC);
}

function bd_parametre_modifier($cle, $valeur)
{
    $link = Connexion();
    $stmt = mysqli_prepare($link, "UPDATE parametres SET valeur = ? WHERE cle = ?");
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la modification du paramètre : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, "ss", $valeur, $cle);
    $execute = mysqli_stmt_execute($stmt);
    $erreur = mysqli_stmt_error($stmt);
    $modifie = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if (!$execute) {
        throw new RuntimeException("Impossible de modifier le paramètre : " . $erreur);
    }
    return $modifie > 0;
}

function bd_parametre_tarifs_lister()
{
    $link = Connexion();
    $resultat = mysqli_query(
        $link,
        "SELECT t.id_type_activite, t.code, t.libelle,
                r.type_fluide, r.tranche_num, r.borne_min, r.borne_max,
                p.prix_unitaire_dh, r.description
         FROM types_activite t
         CROSS JOIN (
             SELECT type_fluide, tranche_num, borne_min, borne_max, description
             FROM tarifs_tranches
             WHERE id_type_activite = (
                 SELECT id_type_activite FROM types_activite WHERE code = 'RESIDENTIEL'
             )
         ) r
         LEFT JOIN tarifs_tranches p
           ON p.id_type_activite = t.id_type_activite
          AND p.type_fluide = r.type_fluide
          AND p.tranche_num = r.tranche_num
         ORDER BY t.id_type_activite, r.type_fluide, r.tranche_num"
    );
    if (!$resultat) {
        throw new RuntimeException("Impossible de récupérer les grilles tarifaires : " . mysqli_error($link));
    }
    return mysqli_fetch_all($resultat, MYSQLI_ASSOC);
}

function bd_parametre_tarif_enregistrer($idTypeActivite, $typeFluide, $tranche, $borneMin, $borneMax, $prix, $description)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "INSERT INTO tarifs_tranches
            (id_type_activite, type_fluide, tranche_num, borne_min, borne_max, prix_unitaire_dh, description)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE prix_unitaire_dh = VALUES(prix_unitaire_dh)"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la mise à jour du tarif : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param(
        $stmt,
        'isiiids',
        $idTypeActivite,
        $typeFluide,
        $tranche,
        $borneMin,
        $borneMax,
        $prix,
        $description
    );
    $execute = mysqli_stmt_execute($stmt);
    $erreur = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    if (!$execute) {
        throw new RuntimeException("Impossible d’enregistrer le tarif : " . $erreur);
    }
}