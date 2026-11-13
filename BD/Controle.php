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

/**
 * Liste des contrôles annuels pour le fournisseur.
 * $resultat = null  → tous ; sinon 'CONFORME', 'SOUS_DECLARATION' ou 'SUR_DECLARATION'.
 */
function bd_controle_annuel_lister($resultat = null)
{
    $link = Connexion();
    $sql = "SELECT ca.id_controle, ca.annee, ca.date_releve_agent, ca.resultat, ca.statut_validation,
                   ca.conso_eau_declaree, ca.conso_elec_declaree,
                   ca.conso_eau_reelle_agent, ca.conso_elec_reelle_agent,
                   ca.ecart_eau_pct, ca.ecart_elec_pct,
                   ct.numero_contrat, cl.numero_client, cl.nom, cl.prenom
            FROM controle_annuel ca
            JOIN clients cl ON cl.id_client = ca.id_client
            LEFT JOIN contrats ct ON ct.id_contrat = ca.id_contrat";
    if ($resultat !== null) {
        $sql .= " WHERE ca.resultat = ?";
    }
    $sql .= " ORDER BY ca.annee DESC, ca.date_releve_agent DESC, ca.id_controle DESC";

    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la liste des contrôles : " . mysqli_error($link));
    }
    if ($resultat !== null) {
        mysqli_stmt_bind_param($stmt, 's', $resultat);
    }
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de lister les contrôles : " . $erreur);
    }
    $lignes = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $lignes;
}

/** Liste des contrôles annuels pour le fournisseur (filtre optionnel sur le statut). */
function bd_controle_lister($statut = '')
{
    $link = Connexion();
    $filtre = '';
    if (in_array($statut, ['EN_ATTENTE_VALIDATION', 'VALIDE_PAR_FOURNISSEUR', 'REJETE', 'CONTESTE'], true)) {
        $filtre = "WHERE ca.statut_validation = '" . $statut . "'";
    }
    $res = mysqli_query($link, "SELECT ca.*, ct.numero_contrat, cl.nom, cl.prenom, cl.numero_client,
                                       f.numero_facture AS numero_facture_regul
                                FROM controle_annuel ca
                                LEFT JOIN contrats ct ON ct.id_contrat = ca.id_contrat
                                JOIN clients cl ON cl.id_client = ca.id_client
                                LEFT JOIN factures f ON f.id_facture = ca.id_facture_regularisation
                                $filtre
                                ORDER BY (ca.statut_validation = 'EN_ATTENTE_VALIDATION') DESC, ca.annee DESC, ca.id_controle DESC");
    return $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
}

function bd_controle_trouver($idControle)
{
    $link = Connexion();
    $idControle = (int) $idControle;
    $res = mysqli_query($link, "SELECT * FROM controle_annuel WHERE id_controle = $idControle");
    return $res ? mysqli_fetch_assoc($res) : null;
}

/** Clôture un contrôle (validé ou rejeté). Ne touche que les contrôles encore en attente. */
function bd_controle_marquer($idControle, $statut, $idUtilisateur, $idFacture = null, $observations = null)
{
    $link = Connexion();
    $stmt = mysqli_prepare($link, "UPDATE controle_annuel
                                   SET statut_validation = ?, valide_par = ?, date_validation = NOW(),
                                       id_facture_regularisation = ?, observations = ?
                                   WHERE id_controle = ? AND statut_validation = 'EN_ATTENTE_VALIDATION'");
    if (!$stmt) {
        throw new RuntimeException('Impossible de préparer la validation du contrôle : ' . mysqli_error($link));
    }
    $idControle = (int) $idControle;
    $idUtilisateur = (int) $idUtilisateur;
    mysqli_stmt_bind_param($stmt, 'siisi', $statut, $idUtilisateur, $idFacture, $observations, $idControle);
    $ok = mysqli_stmt_execute($stmt);
    $nb = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $ok && $nb === 1;
}

/** Solde de crédit d'un client (DH). */
function bd_client_credit($idClient)
{
    $link = Connexion();
    $idClient = (int) $idClient;
    $res = mysqli_query($link, "SELECT solde_credit FROM clients WHERE id_client = $idClient");
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return $row ? (float) $row['solde_credit'] : 0.0;
}

/** Ajoute (montant > 0) ou retire (montant < 0) du crédit sur le compte du client. */
function bd_client_modifier_credit($idClient, $montant)
{
    $link = Connexion();
    $idClient = (int) $idClient;
    $montant = round((float) $montant, 2);
    return (bool) mysqli_query($link, "UPDATE clients SET solde_credit = GREATEST(0, solde_credit + ($montant)) WHERE id_client = $idClient");
}
