<?php
// Accès base de données : paiements (RG30, RG31, RG35) - Membre 4

require_once __DIR__ . '/Connexion.php';

/** Modes de paiement acceptés (valeurs de l'ENUM paiements.mode_paiement). */
function bd_paiement_modes()
{
    return [
        'ESPECES'        => 'Espèces',
        'CARTE_BANCAIRE' => 'Carte bancaire',
        'VIREMENT'       => 'Virement',
        'CHEQUE'         => 'Chèque',
    ];
}

/** Exécute un SELECT préparé et retourne toutes les lignes. */
function bd_paiement_selectionner($sql, $types = '', array $valeurs = [])
{
    $link = Connexion();
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) {
        throw new RuntimeException('Requête paiement invalide : ' . mysqli_error($link));
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$valeurs);
    }
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Lecture des paiements impossible : ' . $erreur);
    }
    $resultat = mysqli_stmt_get_result($stmt);
    $lignes = mysqli_fetch_all($resultat, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $lignes;
}

/**
 * Factures qui peuvent encore recevoir un paiement (IMPAYEE ou EN_RETARD, reste > 0).
 * nb_anterieures = nombre de factures plus anciennes encore impayées sur le même contrat
 * (le paiement se fait dans l'ordre chronologique).
 */
function bd_paiement_factures_a_payer($recherche = '')
{
    $sql = "SELECT f.id_facture, f.numero_facture, f.id_client, f.id_contrat, f.periode_mois, f.periode_annee,
                   f.date_echeance, f.montant_total_a_payer, f.montant_paye, f.reste_a_payer, f.statut,
                   cl.numero_client, cl.nom, cl.prenom,
                   ct.numero_contrat, q.nom AS quartier,
                   (SELECT COUNT(*) FROM factures f2
                     WHERE f2.id_client = f.id_client
                       AND f2.id_contrat <=> f.id_contrat
                       AND f2.statut IN ('IMPAYEE', 'EN_RETARD')
                       AND (f2.periode_annee < f.periode_annee
                            OR (f2.periode_annee = f.periode_annee AND f2.periode_mois < f.periode_mois))
                   ) AS nb_anterieures
            FROM factures f
            JOIN clients cl ON cl.id_client = f.id_client
            LEFT JOIN contrats ct ON ct.id_contrat = f.id_contrat
            LEFT JOIN quartiers q ON q.id_quartier = ct.id_quartier
            WHERE f.statut IN ('IMPAYEE', 'EN_RETARD') AND f.reste_a_payer > 0";
    $types = '';
    $valeurs = [];
    if ($recherche !== '') {
        $sql .= " AND (cl.numero_client LIKE ? OR cl.nom LIKE ? OR cl.prenom LIKE ?
                       OR f.numero_facture LIKE ? OR ct.numero_contrat LIKE ?)";
        $motif = '%' . $recherche . '%';
        $types = 'sssss';
        $valeurs = [$motif, $motif, $motif, $motif, $motif];
    }
    $sql .= " ORDER BY cl.nom, cl.prenom, f.id_contrat, f.periode_annee, f.periode_mois LIMIT 300";
    return bd_paiement_selectionner($sql, $types, $valeurs);
}

/**
 * Lit une facture ET la verrouille jusqu'à la fin de la transaction (SELECT ... FOR UPDATE).
 * À appeler dans une transaction, pour que deux paiements simultanés ne dépassent pas le reste à payer.
 */
function bd_paiement_facture_verrouiller($idFacture)
{
    $lignes = bd_paiement_selectionner(
        "SELECT f.*, cl.id_utilisateur AS id_utilisateur_client, cl.nom, cl.prenom
         FROM factures f
         JOIN clients cl ON cl.id_client = f.id_client
         WHERE f.id_facture = ? FOR UPDATE",
        'i',
        [(int) $idFacture]
    );
    return $lignes[0] ?? null;
}

/** Référence unique d'un paiement, ex. PAY-20261114-153012-A1B2. */
function bd_paiement_reference()
{
    return 'PAY-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

/** Insère le paiement. Retourne l'id du paiement. */
function bd_paiement_inserer($reference, $idFacture, $idClient, $montant, $mode, $idUtilisateur, $remarques)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "INSERT INTO paiements (reference_paiement, id_facture, id_client, montant, mode_paiement, enregistre_par, remarques)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer l'enregistrement du paiement : " . mysqli_error($link));
    }
    $idFacture = (int) $idFacture;
    $idClient = (int) $idClient;
    $montant = (float) $montant;
    $idUtilisateur = $idUtilisateur === null ? null : (int) $idUtilisateur;
    $remarques = $remarques === '' ? null : $remarques;
    mysqli_stmt_bind_param($stmt, 'siidsis', $reference, $idFacture, $idClient, $montant, $mode, $idUtilisateur, $remarques);
    $ok = mysqli_stmt_execute($stmt);
    $erreur = mysqli_stmt_error($stmt);
    $id = mysqli_insert_id($link);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        throw new RuntimeException("Impossible d'enregistrer le paiement : " . $erreur);
    }
    return $id;
}

/** Derniers paiements enregistrés, avec la facture, le client et l'utilisateur qui a encaissé. */
function bd_paiement_lister($limite = 30)
{
    return bd_paiement_selectionner(
        "SELECT p.id_paiement, p.reference_paiement, p.montant, p.date_paiement, p.mode_paiement, p.remarques,
                f.numero_facture, cl.numero_client, cl.nom, cl.prenom, u.identifiant AS encaisse_par
         FROM paiements p
         JOIN factures f ON f.id_facture = p.id_facture
         JOIN clients cl ON cl.id_client = p.id_client
         LEFT JOIN utilisateurs u ON u.id_utilisateur = p.enregistre_par
         ORDER BY p.date_paiement DESC, p.id_paiement DESC
         LIMIT ?",
        'i',
        [max(1, min(200, (int) $limite))]
    );
}