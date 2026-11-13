<?php
include_once "Connexion.php";

function saisieExiste($idClient, $mois, $annee)
{
    $conn = Connexion();
    $sql  = mysqli_prepare($conn, "SELECT COUNT(*) FROM consommations WHERE id_client = ? AND mois = ? AND annee = ?");
    mysqli_stmt_bind_param($sql, "iii", $idClient, $mois, $annee);
    mysqli_stmt_execute($sql);
    mysqli_stmt_bind_result($sql, $count);
    mysqli_stmt_fetch($sql);
    mysqli_stmt_close($sql);
    return $count > 0;
}

function derniereSaisie($idClient)
{
    $conn = Connexion();
    $sql  = mysqli_prepare($conn, "SELECT * FROM consommations WHERE id_client = ? ORDER BY annee DESC, mois DESC LIMIT 1");
    mysqli_stmt_bind_param($sql, "i", $idClient);
    mysqli_stmt_execute($sql);
    $ligne = mysqli_fetch_assoc(mysqli_stmt_get_result($sql));
    mysqli_stmt_close($sql);
    return $ligne ? $ligne : null;
}

// Index d'installation du client (utilisé pour la première saisie)
function indexInitial($idClient)
{
    $conn = Connexion();
    $sql  = mysqli_prepare($conn, "SELECT index_initial_eau, index_initial_elec FROM clients WHERE id_client = ?");
    mysqli_stmt_bind_param($sql, "i", $idClient);
    mysqli_stmt_execute($sql);
    $ligne = mysqli_fetch_assoc(mysqli_stmt_get_result($sql));
    mysqli_stmt_close($sql);
    return $ligne;
}

function bd_consommation_contrats_client($idClient)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "SELECT ct.id_contrat, ct.numero_contrat, ct.numero_compteur_eau, ct.numero_compteur_elec,
                ct.index_initial_eau, ct.index_initial_elec, ct.id_quartier
         FROM contrats ct
         WHERE ct.id_client = ? AND ct.statut = 'ACTIF'
         ORDER BY ct.numero_contrat"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la liste des contrats : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, 'i', $idClient);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de récupérer les contrats : " . $erreur);
    }
    $resultat = mysqli_stmt_get_result($stmt);
    $contrats = mysqli_fetch_all($resultat, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $contrats;
}

function bd_consommation_contrat_client($idContrat, $idClient)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "SELECT id_contrat, numero_contrat, id_client
         FROM contrats
         WHERE id_contrat = ? AND id_client = ? AND statut = 'ACTIF'
         LIMIT 1"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la vérification du contrat : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, 'ii', $idContrat, $idClient);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de vérifier le contrat : " . $erreur);
    }
    $resultat = mysqli_stmt_get_result($stmt);
    $contrat = mysqli_fetch_assoc($resultat);
    mysqli_stmt_close($stmt);
    return $contrat ?: null;
}

function bd_consommation_derniere_par_contrat($idContrat)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "SELECT * FROM consommations
         WHERE id_contrat = ?
         ORDER BY annee DESC, mois DESC, id_conso DESC
         LIMIT 1"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la recherche du dernier relevé : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, 'i', $idContrat);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de récupérer le dernier relevé : " . $erreur);
    }
    $resultat = mysqli_stmt_get_result($stmt);
    $ligne = mysqli_fetch_assoc($resultat);
    mysqli_stmt_close($stmt);
    return $ligne ?: null;
}

function bd_consommation_client_saisies_manquantes($idClient, $mois, $annee)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "SELECT COUNT(*) AS restantes
         FROM contrats ct
         WHERE ct.id_client = ? AND ct.statut = 'ACTIF'
           AND NOT EXISTS (
               SELECT 1 FROM consommations co
               WHERE co.id_contrat = ct.id_contrat AND co.mois = ? AND co.annee = ?
           )"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la vérification des déclarations : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, 'iii', $idClient, $mois, $annee);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de vérifier les déclarations du client : " . $erreur);
    }
    $resultat = mysqli_stmt_get_result($stmt);
    $ligne = mysqli_fetch_assoc($resultat);
    mysqli_stmt_close($stmt);
    return (int) ($ligne['restantes'] ?? 0);
}

function enregistrer($idClient, $annee, $mois, $indexEauPrec, $indexEauNouv, $consoEau, $photoEau, $indexElecPrec, $indexElecNouv, $consoElec, $photoElec, $typeSaisie = 'REELLE', $statut = 'VALIDE', $idContrat = null)
{
    $conn = Connexion();
    $sql = mysqli_prepare(
        $conn,
        "INSERT INTO consommations (
            id_client, id_contrat, annee, mois,
            index_eau_precedent, index_eau_nouveau, conso_eau, photo_compteur_eau,
            index_elec_precedent, index_elec_nouveau, conso_elec, photo_compteur_elec,
            type_saisie, statut
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$sql) {
        throw new RuntimeException("Impossible de préparer l’enregistrement de la consommation : " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param(
        $sql,
        "iiiiiiisiiisss",
        $idClient,
        $idContrat,
        $annee,
        $mois,
        $indexEauPrec,
        $indexEauNouv,
        $consoEau,
        $photoEau,
        $indexElecPrec,
        $indexElecNouv,
        $consoElec,
        $photoElec,
        $typeSaisie,
        $statut
    );
    $ok = mysqli_stmt_execute($sql);
    $id = mysqli_insert_id($conn);
    mysqli_stmt_close($sql);
    return $ok ? $id : false;
}

function trouverParId($idConso)
{
    $conn = Connexion();
    $sql  = mysqli_prepare($conn, "SELECT * FROM consommations WHERE id_conso = ?");
    mysqli_stmt_bind_param($sql, "i", $idConso);
    mysqli_stmt_execute($sql);
    $ligne = mysqli_fetch_assoc(mysqli_stmt_get_result($sql));
    mysqli_stmt_close($sql);
    return $ligne ? $ligne : null;
}

function mettreAJourStatut($id, $statut)
{
    $conn = Connexion();
    $sql  = mysqli_prepare($conn, "UPDATE consommations SET statut = ? WHERE id_conso = ?");
    mysqli_stmt_bind_param($sql, "si", $statut, $id);
    $ok = mysqli_stmt_execute($sql);
    mysqli_stmt_close($sql);
    return $ok;
}

function bd_consommation_contrat_existe($idContrat, $annee, $mois)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "SELECT id_conso FROM consommations WHERE id_contrat = ? AND annee = ? AND mois = ? LIMIT 1"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la vérification du relevé : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, 'iii', $idContrat, $annee, $mois);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de vérifier le relevé : " . $erreur);
    }
    $resultat = mysqli_stmt_get_result($stmt);
    $existe = mysqli_num_rows($resultat) > 0;
    mysqli_stmt_close($stmt);
    return $existe;
}

function bd_consommation_index_precedent_contrat($idContrat, $annee, $mois)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "SELECT index_eau_nouveau, index_elec_nouveau
         FROM consommations
         WHERE id_contrat = ?
           AND statut IN ('VALIDE', 'REGULARISEE')
           AND (annee < ? OR (annee = ? AND mois < ?))
         ORDER BY annee DESC, mois DESC, id_conso DESC
         LIMIT 1"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la recherche des index précédents : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, 'iiii', $idContrat, $annee, $annee, $mois);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de récupérer les index précédents : " . $erreur);
    }
    $resultat = mysqli_stmt_get_result($stmt);
    $ligne = mysqli_fetch_assoc($resultat);
    mysqli_stmt_close($stmt);
    if ($ligne) {
        return [
            'eau' => (int) $ligne['index_eau_nouveau'],
            'elec' => (int) $ligne['index_elec_nouveau'],
        ];
    }

    $stmt = mysqli_prepare(
        $link,
        "SELECT index_initial_eau, index_initial_elec FROM contrats WHERE id_contrat = ?"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la recherche des index initiaux : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, 'i', $idContrat);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de récupérer les index initiaux : " . $erreur);
    }
    $resultat = mysqli_stmt_get_result($stmt);
    $ligne = mysqli_fetch_assoc($resultat);
    mysqli_stmt_close($stmt);

    if (!$ligne) {
        throw new RuntimeException('Le contrat sélectionné n’existe plus.');
    }
    return [
        'eau' => (int) $ligne['index_initial_eau'],
        'elec' => (int) $ligne['index_initial_elec'],
    ];
}

function bd_consommation_creer_releve_agent($donnees)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "INSERT INTO consommations (
            id_client, id_contrat, id_agent, annee, mois,
            index_eau_precedent, index_eau_nouveau, conso_eau,
            index_elec_precedent, index_elec_nouveau, conso_elec,
            date_saisie, type_saisie, statut
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'MANUELLE', ?)"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer l’enregistrement du relevé : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param(
        $stmt,
        'iiiiiiiiiiiss',
        $donnees['id_client'],
        $donnees['id_contrat'],
        $donnees['id_agent'],
        $donnees['annee'],
        $donnees['mois'],
        $donnees['index_eau_precedent'],
        $donnees['index_eau_nouveau'],
        $donnees['conso_eau'],
        $donnees['index_elec_precedent'],
        $donnees['index_elec_nouveau'],
        $donnees['conso_elec'],
        $donnees['date_saisie'],
        $donnees['statut']
    );
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible d’enregistrer le relevé : " . $erreur);
    }
    $idConso = (int) mysqli_insert_id($link);
    mysqli_stmt_close($stmt);
    return $idConso;
}

function bd_consommation_historique_agent($idAgent, $limite = 100)
{
    $link = Connexion();
    $limite = max(1, min(500, (int) $limite));
    $stmt = mysqli_prepare(
        $link,
        "SELECT co.id_conso, co.annee, co.mois, co.index_eau_nouveau AS index_eau,
                co.index_elec_nouveau AS index_elec, co.date_saisie AS date_releve,
                co.statut, ct.numero_contrat, cl.nom, cl.prenom
         FROM consommations co
         JOIN contrats ct ON ct.id_contrat = co.id_contrat
         JOIN clients cl ON cl.id_client = co.id_client
         WHERE co.id_agent = ?
         ORDER BY co.date_saisie DESC, co.id_conso DESC
         LIMIT ?"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer l’historique des relevés : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, 'ii', $idAgent, $limite);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de récupérer l’historique des relevés : " . $erreur);
    }
    $resultat = mysqli_stmt_get_result($stmt);
    $historique = mysqli_fetch_all($resultat, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $historique;
}