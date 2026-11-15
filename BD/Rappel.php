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