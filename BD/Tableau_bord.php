<?php
// Requêtes des tableaux de bord (Membre 1) : espace fournisseur + espace client
require_once __DIR__ . '/Connexion.php';

/** Compte les lignes renvoyées par un "SELECT COUNT(*) ..." */
function bd_tableau_compter($sql)
{
    $link = Connexion();
    $res = mysqli_query($link, $sql);
    $ligne = $res ? mysqli_fetch_row($res) : null;
    return $ligne ? (int) $ligne[0] : 0;
}

/* ===================== ESPACE FOURNISSEUR ===================== */

/** Compteurs conservés pour les indicateurs du tableau de bord. */
function bd_tableau_compteurs()
{
    return [
        'anomalies' => bd_tableau_compter("SELECT COUNT(*) FROM anomalies WHERE statut = 'EN_ATTENTE'"),
        'bloques'   => bd_tableau_compter("SELECT COUNT(*) FROM utilisateurs
                                            WHERE role = 'CLIENT'
                                              AND (statut_compte IN ('BLOQUE_TENTATIVES', 'BLOQUE_GESTIONNAIRE')
                                                   OR bloque_jusqu_a > NOW())"),
        'impayees'  => bd_tableau_compter("SELECT COUNT(*) FROM factures WHERE statut = 'IMPAYEE'"),
        'en_retard' => bd_tableau_compter("SELECT COUNT(*) FROM factures WHERE statut = 'EN_RETARD'"),
        'clients'   => bd_tableau_compter("SELECT COUNT(*) FROM clients"),
    ];
}

/** Statistiques des clients affichées sur le tableau de bord fournisseur. */
function bd_tableau_statistiques_clients()
{
    $link = Connexion();
    $sql = "SELECT
                (SELECT COUNT(*) FROM clients) AS total,
                (SELECT COUNT(*)
                 FROM clients c
                 INNER JOIN utilisateurs u ON c.id_utilisateur = u.id_utilisateur
                 WHERE u.statut_compte = 'ACTIF'
                   AND (u.bloque_jusqu_a IS NULL OR u.bloque_jusqu_a <= NOW())) AS actifs,
                (SELECT COUNT(*)
                 FROM clients
                 WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS nouveaux_mois,
                (SELECT COUNT(*) FROM factures WHERE statut = 'EN_RETARD') AS retards,
                (SELECT COALESCE(SUM(reste_a_payer), 0)
                 FROM factures
                 WHERE statut = 'EN_RETARD') AS montant_retard";
    $res = mysqli_query($link, $sql);

    if (!$res) {
        throw new RuntimeException("Impossible de récupérer les statistiques des clients : " . mysqli_error($link));
    }

    return mysqli_fetch_assoc($res);
}

/** Indicateurs des contrats regroupés par quartier, y compris les contrats sans quartier. */
function bd_tableau_statistiques_quartiers()
{
    $link = Connexion();
    $sql = "SELECT q.id_quartier, COALESCE(q.nom, 'Non affecté') AS quartier,
                   COUNT(DISTINCT ct.id_contrat) AS contrats,
                   COUNT(DISTINCT ct.id_client) AS clients,
                   COUNT(DISTINCT CASE WHEN f.statut IN ('IMPAYEE', 'EN_RETARD') THEN f.id_facture END) AS factures_impayees,
                   COALESCE(SUM(CASE WHEN f.statut IN ('IMPAYEE', 'EN_RETARD') THEN f.reste_a_payer ELSE 0 END), 0) AS montant_impaye
            FROM contrats ct
            LEFT JOIN quartiers q ON q.id_quartier = ct.id_quartier
            LEFT JOIN factures f ON f.id_contrat = ct.id_contrat
            GROUP BY q.id_quartier, q.nom
            ORDER BY q.nom IS NULL, q.nom";
    $res = mysqli_query($link, $sql);
    if (!$res) {
        throw new RuntimeException("Impossible de récupérer les statistiques par quartier : " . mysqli_error($link));
    }
    return mysqli_fetch_all($res, MYSQLI_ASSOC);
}

/** Les dernières factures émises, avec le nom du client. */
function bd_tableau_dernieres_factures($limite = 5)
{
    $link = Connexion();
    $limite = (int) $limite;
    $res = mysqli_query($link, "SELECT f.numero_facture, f.montant_ttc, f.date_echeance, f.statut,
                                       c.nom, c.prenom
                                FROM factures f
                                JOIN clients c ON c.id_client = f.id_client
                                ORDER BY f.date_emission DESC, f.id_facture DESC
                                LIMIT $limite");
    return $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
}

/** Email du fournisseur connecté (affiché en haut à droite). */
function bd_tableau_infos_utilisateur($idUtilisateur)
{
    $link = Connexion();
    $idUtilisateur = (int) $idUtilisateur;
    $res = mysqli_query($link, "SELECT identifiant, email FROM utilisateurs WHERE id_utilisateur = $idUtilisateur");
    return $res ? mysqli_fetch_assoc($res) : null;
}

/* ===================== ESPACE CLIENT ===================== */

/**
 * Les $nb derniers totaux mensuels valides d'un client, cumulés sur ses contrats.
 */
function bd_tableau_consommations_client($idClient, $nb = 6)
{
    $link = Connexion();
    $idClient = (int) $idClient;
    $nb = (int) $nb;
    $res = mysqli_query($link, "SELECT annee, mois,
                                       SUM(conso_eau) AS conso_eau,
                                       SUM(conso_elec) AS conso_elec
                                FROM consommations
                                WHERE id_client = $idClient
                                  AND statut IN ('VALIDE', 'REGULARISEE')
                                  AND conso_eau >= 0 AND conso_elec >= 0
                                GROUP BY annee, mois
                                ORDER BY annee DESC, mois DESC
                                LIMIT $nb");
    $lignes = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
    return array_reverse($lignes);
}

/** Dernière consommation connue et solde impayé de chacun des contrats d'un client. */
function bd_tableau_contrats_client($idClient)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "SELECT ct.id_contrat, ct.numero_contrat, ct.statut,
                COALESCE(q.nom, 'Non affecté') AS quartier,
                (SELECT co.conso_eau
                 FROM consommations co
                 WHERE co.id_contrat = ct.id_contrat
                   AND co.statut IN ('VALIDE', 'REGULARISEE')
                 ORDER BY co.annee DESC, co.mois DESC, co.id_conso DESC
                 LIMIT 1) AS derniere_eau,
                (SELECT co.conso_elec
                 FROM consommations co
                 WHERE co.id_contrat = ct.id_contrat
                   AND co.statut IN ('VALIDE', 'REGULARISEE')
                 ORDER BY co.annee DESC, co.mois DESC, co.id_conso DESC
                 LIMIT 1) AS derniere_electricite,
                (SELECT COUNT(*)
                 FROM factures f
                 WHERE f.id_contrat = ct.id_contrat
                   AND f.statut IN ('IMPAYEE', 'EN_RETARD')) AS factures_impayees,
                (SELECT COALESCE(SUM(f.reste_a_payer), 0)
                 FROM factures f
                 WHERE f.id_contrat = ct.id_contrat
                   AND f.statut IN ('IMPAYEE', 'EN_RETARD')) AS solde_impaye
         FROM contrats ct
         LEFT JOIN quartiers q ON q.id_quartier = ct.id_quartier
         WHERE ct.id_client = ?
         ORDER BY ct.statut = 'ACTIF' DESC, ct.numero_contrat"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer les indicateurs des contrats : " . mysqli_error($link));
    }
    $idClient = (int) $idClient;
    mysqli_stmt_bind_param($stmt, 'i', $idClient);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de récupérer les indicateurs des contrats : " . $erreur);
    }
    $contrats = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $contrats;
}
