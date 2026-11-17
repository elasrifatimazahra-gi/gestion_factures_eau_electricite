<?php
// Accès base de données : tâches planifiées (pénalités, alertes, mois non déclarés) - Membre 4

require_once __DIR__ . '/Coupure.php';   // fournit bd_coupure_selectionner()

/** Contrats actifs avec le client et son compte utilisateur (pour les notifier). */
function bd_rappel_contrats_actifs()
{
    return bd_coupure_selectionner(
        "SELECT ct.id_contrat, ct.numero_contrat, ct.id_client, ct.statut_service,
                cl.id_utilisateur AS id_utilisateur_client, cl.nom, cl.prenom
         FROM contrats ct
         JOIN clients cl ON cl.id_client = ct.id_client
         WHERE ct.statut = 'ACTIF'
         ORDER BY ct.id_contrat"
    );
}

/** Dernières saisies d'un contrat (les plus récentes d'abord) : annee, mois, type_saisie. */
function bd_rappel_saisies_recentes($idContrat)
{
    return bd_coupure_selectionner(
        "SELECT annee, mois, type_saisie FROM consommations
         WHERE id_contrat = ? ORDER BY annee DESC, mois DESC LIMIT 24",
        'i',
        [(int) $idContrat]
    );
}

/** Vrai si cette notification (même titre) a déjà été envoyée depuis $depuis (Y-m-d). */
function bd_rappel_notification_deja_envoyee($idUtilisateur, $titre, $depuis)
{
    $l = bd_coupure_selectionner(
        "SELECT COUNT(*) AS n FROM notifications
         WHERE id_utilisateur = ? AND titre = ? AND date_envoi >= ?",
        'iss',
        [(int) $idUtilisateur, $titre, $depuis]
    );
    return (int) ($l[0]['n'] ?? 0) > 0;
}

/** Vrai si la table sanctions a une colonne id_contrat (selon la version de la base). */
function bd_rappel_sanctions_ont_contrat()
{
    $r = mysqli_query(Connexion(), "SHOW COLUMNS FROM `sanctions` LIKE 'id_contrat'");
    return $r && mysqli_num_rows($r) > 0;
}

/**
 * Sanctions CONFIRMEES, non payées, dont la date limite de paiement est dépassée et qui n'ont pas
 * encore de coupure (hors coupure annulée). « Non payée » = la facture exceptionnelle n'est pas PAYEE.
 */
function bd_rappel_sanctions_a_couper()
{
    $colonneContrat = bd_rappel_sanctions_ont_contrat() ? 's.id_contrat' : 'NULL';
    return bd_coupure_selectionner(
        "SELECT s.id_sanction, s.reference_sanction, s.id_client, s.date_coupure_programmee, s.frais_retablissement,
                $colonneContrat AS id_contrat_sanction, ce.id_contrat AS id_contrat_controle,
                CONCAT(cl.nom, ' ', cl.prenom) AS client
         FROM sanctions s
         JOIN clients cl ON cl.id_client = s.id_client
         LEFT JOIN controle_annuel ce ON ce.id_controle = s.id_controle
         LEFT JOIN factures fe ON fe.id_facture = s.id_facture_exceptionnelle
         WHERE s.statut = 'CONFIRMEE'
           AND s.date_limite_paiement < CURDATE()
           AND (fe.id_facture IS NULL OR fe.statut <> 'PAYEE')
           AND NOT EXISTS (SELECT 1 FROM coupures_services c WHERE c.id_sanction = s.id_sanction AND c.statut <> 'ANNULEE')
         ORDER BY s.id_sanction"
    );
}

/** Identifiants des contrats actifs d'un client. */
function bd_rappel_contrats_actifs_du_client($idClient)
{
    return array_column(bd_coupure_selectionner(
        "SELECT id_contrat FROM contrats WHERE id_client = ? AND statut = 'ACTIF' ORDER BY id_contrat",
        'i',
        [(int) $idClient]
    ), 'id_contrat');
}

/** Coupures PROGRAMMEES d'une sanction devenue payée (facture PAYEE), régularisée ou annulée. */
function bd_rappel_coupures_sanction_a_annuler()
{
    return bd_coupure_selectionner(
        "SELECT c.id_coupure, s.reference_sanction
         FROM coupures_services c
         JOIN sanctions s ON s.id_sanction = c.id_sanction
         LEFT JOIN factures fe ON fe.id_facture = s.id_facture_exceptionnelle
         WHERE c.statut = 'PROGRAMMEE'
           AND (s.statut IN ('REGULARISEE', 'ANNULEE') OR fe.statut = 'PAYEE')"
    );
}