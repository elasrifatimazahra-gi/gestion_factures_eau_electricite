<?php

require_once __DIR__ . '/Connexion.php';

function bd_controle_annuel_declarees($idContrat, $annee)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "SELECT COALESCE(SUM(conso_eau), 0) AS conso_eau,
                COALESCE(SUM(conso_elec), 0) AS conso_elec
         FROM consommations
         WHERE id_contrat = ? AND annee = ? AND statut IN ('VALIDE', 'REGULARISEE')"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer le total annuel déclaré : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, 'ii', $idContrat, $annee);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de calculer le total annuel déclaré : " . $erreur);
    }
    $resultat = mysqli_stmt_get_result($stmt);
    $ligne = mysqli_fetch_assoc($resultat);
    mysqli_stmt_close($stmt);

    return [
        'eau' => (int) ($ligne['conso_eau'] ?? 0),
        'elec' => (int) ($ligne['conso_elec'] ?? 0),
    ];
}

function bd_controle_annuel_existe($idContrat, $annee)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "SELECT id_controle FROM controle_annuel WHERE id_contrat = ? AND annee = ? LIMIT 1"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la vérification du contrôle annuel : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, 'ii', $idContrat, $annee);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de vérifier le contrôle annuel : " . $erreur);
    }
    $resultat = mysqli_stmt_get_result($stmt);
    $existe = mysqli_num_rows($resultat) > 0;
    mysqli_stmt_close($stmt);
    return $existe;
}

function bd_controle_annuel_creer($donnees)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "INSERT INTO controle_annuel (
            annee, id_client, id_contrat, id_agent, date_releve_agent,
            conso_eau_declaree, conso_elec_declaree,
            conso_eau_reelle_agent, conso_elec_reelle_agent,
            ecart_eau, ecart_elec, ecart_eau_pct, ecart_elec_pct, resultat
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la création du contrôle annuel : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param(
        $stmt,
        'iiiisiiiiiidds',
        $donnees['annee'],
        $donnees['id_client'],
        $donnees['id_contrat'],
        $donnees['id_agent'],
        $donnees['date_releve_agent'],
        $donnees['conso_eau_declaree'],
        $donnees['conso_elec_declaree'],
        $donnees['conso_eau_reelle_agent'],
        $donnees['conso_elec_reelle_agent'],
        $donnees['ecart_eau'],
        $donnees['ecart_elec'],
        $donnees['ecart_eau_pct'],
        $donnees['ecart_elec_pct'],
        $donnees['resultat']
    );
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible d’enregistrer le contrôle annuel : " . $erreur);
    }
    $idControle = (int) mysqli_insert_id($link);
    mysqli_stmt_close($stmt);
    return $idControle;
}

function bd_controle_annuel_historique_agent($idAgent, $limite = 100)
{
    $link = Connexion();
    $limite = max(1, min(500, (int) $limite));
    $stmt = mysqli_prepare(
        $link,
        "SELECT ca.id_controle, ca.annee, ca.date_releve_agent, ca.resultat,
                ca.statut_validation, ca.conso_eau_reelle_agent, ca.conso_elec_reelle_agent,
                ct.numero_contrat, cl.nom, cl.prenom
         FROM controle_annuel ca
         JOIN contrats ct ON ct.id_contrat = ca.id_contrat
         JOIN clients cl ON cl.id_client = ca.id_client
         WHERE ca.id_agent = ?
         ORDER BY ca.annee DESC, ca.date_releve_agent DESC
         LIMIT ?"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer l’historique des contrôles : " . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, 'ii', $idAgent, $limite);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de récupérer l’historique des contrôles : " . $erreur);
    }
    $resultat = mysqli_stmt_get_result($stmt);
    $historique = mysqli_fetch_all($resultat, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $historique;
}
