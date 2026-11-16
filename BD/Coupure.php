<?php
// Accès base de données : coupures de service par contrat (RG34, RG35) - Membre 4
// Prérequis : database/migration_coupures.sql (coupures_services.id_contrat, contrats.statut_service)

require_once __DIR__ . '/Connexion.php';

function bd_coupure_motifs()
{
    return [
        'TROIS_MOIS_IMPAYES'        => 'Trois mois impayés',
        'SANCTION_FRAUDE_NON_PAYEE' => 'Sanction (fraude non payée)',
        'DEMANDE_FOURNISSEUR'       => 'Demande du fournisseur',
    ];
}

function bd_coupure_statuts()
{
    return [
        'PROGRAMMEE' => 'Programmée',
        'EFFECTUEE'  => 'Effectuée',
        'RETABLIE'   => 'Rétablie',
        'ANNULEE'    => 'Annulée',
    ];
}

/** SELECT préparé : retourne toutes les lignes. */
function bd_coupure_selectionner($sql, $types = '', array $valeurs = [])
{
    $link = Connexion();
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) {
        throw new RuntimeException('Requête coupure invalide : ' . mysqli_error($link));
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$valeurs);
    }
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Lecture des coupures impossible : ' . $erreur);
    }
    $lignes = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $lignes;
}

/** INSERT / UPDATE préparé : retourne ['affected' => lignes modifiées, 'id' => dernier id inséré]. */
function bd_coupure_ecrire($sql, $types, array $valeurs)
{
    $link = Connexion();
    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) {
        throw new RuntimeException('Requête coupure invalide : ' . mysqli_error($link));
    }
    mysqli_stmt_bind_param($stmt, $types, ...$valeurs);
    $ok = mysqli_stmt_execute($stmt);
    $erreur = mysqli_stmt_error($stmt);
    $resultat = ['affected' => mysqli_stmt_affected_rows($stmt), 'id' => mysqli_insert_id($link)];
    mysqli_stmt_close($stmt);
    if (!$ok) {
        throw new RuntimeException("Écriture de la coupure impossible : " . $erreur);
    }
    return $resultat;
}

/** Liste des coupures (toutes, ou d'un seul statut), avec client, contrat et quartier. */
function bd_coupure_lister($statut = null)
{
    $sql = "SELECT c.id_coupure, c.id_client, c.id_contrat, c.motif_coupure, c.service_coupe, c.date_alerte,
                   c.date_programmee, c.date_execution, c.statut, c.frais_retablissement,
                   c.date_retablissement, c.commentaire,
                   cl.numero_client, cl.nom, cl.prenom,
                   ct.numero_contrat, ct.statut_service, q.nom AS quartier
            FROM coupures_services c
            JOIN clients cl ON cl.id_client = c.id_client
            LEFT JOIN contrats ct ON ct.id_contrat = c.id_contrat
            LEFT JOIN quartiers q ON q.id_quartier = ct.id_quartier";
    $types = '';
    $valeurs = [];
    if ($statut !== null) {
        $sql .= " WHERE c.statut = ?";
        $types = 's';
        $valeurs = [$statut];
    }
    $sql .= " ORDER BY c.date_programmee DESC, c.id_coupure DESC LIMIT 300";
    return bd_coupure_selectionner($sql, $types, $valeurs);
}

/** Lit une coupure et la verrouille jusqu'à la fin de la transaction. */
function bd_coupure_verrouiller($idCoupure)
{
    $l = bd_coupure_selectionner("SELECT * FROM coupures_services WHERE id_coupure = ? FOR UPDATE", 'i', [(int) $idCoupure]);
    return $l[0] ?? null;
}

/** Contrat + client (pour notifier). $verrou = true : verrouille la ligne (dans une transaction). */
function bd_coupure_contrat_infos($idContrat, $verrou = false)
{
    $l = bd_coupure_selectionner(
        "SELECT ct.id_contrat, ct.numero_contrat, ct.statut, ct.statut_service, ct.id_client,
                cl.id_utilisateur AS id_utilisateur_client, cl.nom, cl.prenom
         FROM contrats ct
         JOIN clients cl ON cl.id_client = ct.id_client
         WHERE ct.id_contrat = ?" . ($verrou ? ' FOR UPDATE' : ''),
        'i',
        [(int) $idContrat]
    );
    return $l[0] ?? null;
}

/** Coupure PROGRAMMEE ou EFFECTUEE (donc pas encore terminée) pour ce contrat, sinon null. */
function bd_coupure_ouverte_pour_contrat($idContrat)
{
    $l = bd_coupure_selectionner(
        "SELECT * FROM coupures_services WHERE id_contrat = ? AND statut IN ('PROGRAMMEE', 'EFFECTUEE') LIMIT 1",
        'i',
        [(int) $idContrat]
    );
    return $l[0] ?? null;
}

/** Coupure PROGRAMMEE de ce contrat pour un motif donné, sinon null. */
function bd_coupure_programmee_pour_contrat($idContrat, $motif)
{
    $l = bd_coupure_selectionner(
        "SELECT * FROM coupures_services WHERE id_contrat = ? AND statut = 'PROGRAMMEE' AND motif_coupure = ? LIMIT 1",
        'is',
        [(int) $idContrat, $motif]
    );
    return $l[0] ?? null;
}

/** Contrats actifs dont le service n'est pas coupé (liste déroulante du formulaire). */
function bd_coupure_contrats_programmables()
{
    return bd_coupure_selectionner(
        "SELECT ct.id_contrat, ct.numero_contrat, ct.statut_service, cl.nom, cl.prenom, q.nom AS quartier
         FROM contrats ct
         JOIN clients cl ON cl.id_client = ct.id_client
         LEFT JOIN quartiers q ON q.id_quartier = ct.id_quartier
         WHERE ct.statut = 'ACTIF' AND ct.statut_service <> 'COUPE'
         ORDER BY cl.nom, cl.prenom, ct.numero_contrat"
    );
}

/**
 * Nombre de mois impayés CONSÉCUTIFS d'un contrat, en partant du mois impayé le plus récent
 * (RG34). Ex. : impayés mars, avril, mai -> 3 ; impayés janvier et mai -> 1.
 */
function bd_coupure_mois_impayes_consecutifs($idContrat)
{
    $lignes = bd_coupure_selectionner(
        "SELECT DISTINCT periode_annee, periode_mois FROM factures
         WHERE id_contrat = ? AND statut IN ('IMPAYEE', 'EN_RETARD') AND reste_a_payer > 0
         ORDER BY periode_annee DESC, periode_mois DESC",
        'i',
        [(int) $idContrat]
    );
    $nombre = 0;
    $attendu = null;
    foreach ($lignes as $l) {
        $rang = (int) $l['periode_annee'] * 12 + ((int) $l['periode_mois'] - 1);
        if ($attendu !== null && $rang !== $attendu) {
            break;
        }
        $nombre++;
        $attendu = $rang - 1;
    }
    return $nombre;
}

/**
 * Réclamations ouvertes qui suspendent la coupure (reclamations.suspension_coupure = 1)
 * et qui portent sur une facture impayée de ce contrat. Retourne leur nombre.
 */
function bd_coupure_reclamations_bloquantes($idContrat)
{
    $l = bd_coupure_selectionner(
        "SELECT COUNT(*) AS n FROM reclamations r
         JOIN factures f ON f.id_facture = r.id_facture
         WHERE f.id_contrat = ? AND f.statut IN ('IMPAYEE', 'EN_RETARD')
           AND r.statut IN ('OUVERTE', 'EN_COURS') AND r.suspension_coupure = 1",
        'i',
        [(int) $idContrat]
    );
    return (int) ($l[0]['n'] ?? 0);
}

/** Insère une coupure PROGRAMMEE. Retourne son id. */
function bd_coupure_programmer($idClient, $idContrat, $motif, $datePrevue, $frais, $idUtilisateur, $commentaire, $idSanction = null)
{
    $r = bd_coupure_ecrire(
        "INSERT INTO coupures_services
            (id_client, id_contrat, id_sanction, motif_coupure, date_programmee, frais_retablissement, agent_responsable, commentaire)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        'iiissdis',
        [
            (int) $idClient,
            (int) $idContrat,
            $idSanction === null ? null : (int) $idSanction,
            $motif,
            $datePrevue,
            (float) $frais,
            $idUtilisateur === null ? null : (int) $idUtilisateur,
            $commentaire === '' ? null : $commentaire,
        ]
    );
    return $r['id'];
}

/** PROGRAMMEE -> EFFECTUEE. Retourne true si la ligne a bien changé. */
function bd_coupure_marquer_effectuee($idCoupure, $idUtilisateur)
{
    $r = bd_coupure_ecrire(
        "UPDATE coupures_services SET statut = 'EFFECTUEE', date_execution = NOW(), agent_responsable = ?
         WHERE id_coupure = ? AND statut = 'PROGRAMMEE'",
        'ii',
        [$idUtilisateur === null ? null : (int) $idUtilisateur, (int) $idCoupure]
    );
    return $r['affected'] > 0;
}

/** EFFECTUEE -> RETABLIE ; $note est ajoutée au commentaire. */
function bd_coupure_marquer_retablie($idCoupure, $idUtilisateur, $note)
{
    $r = bd_coupure_ecrire(
        "UPDATE coupures_services
         SET statut = 'RETABLIE', date_retablissement = NOW(), agent_responsable = ?,
             commentaire = CONCAT(COALESCE(commentaire, ''), ?)
         WHERE id_coupure = ? AND statut = 'EFFECTUEE'",
        'isi',
        [$idUtilisateur === null ? null : (int) $idUtilisateur, $note, (int) $idCoupure]
    );
    return $r['affected'] > 0;
}

/** PROGRAMMEE -> ANNULEE ; $note est ajoutée au commentaire. */
function bd_coupure_marquer_annulee($idCoupure, $note)
{
    $r = bd_coupure_ecrire(
        "UPDATE coupures_services SET statut = 'ANNULEE', commentaire = CONCAT(COALESCE(commentaire, ''), ?)
         WHERE id_coupure = ? AND statut = 'PROGRAMMEE'",
        'si',
        [$note, (int) $idCoupure]
    );
    return $r['affected'] > 0;
}

/** Définit l'état du service d'UN contrat : ACTIF, AVERTISSEMENT ou COUPE. */
function bd_coupure_definir_statut_service($idContrat, $statutService)
{
    if (!in_array($statutService, ['ACTIF', 'AVERTISSEMENT', 'COUPE'], true)) {
        throw new InvalidArgumentException('Statut de service invalide.');
    }
    bd_coupure_ecrire("UPDATE contrats SET statut_service = ? WHERE id_contrat = ?", 'si', [$statutService, (int) $idContrat]);
}