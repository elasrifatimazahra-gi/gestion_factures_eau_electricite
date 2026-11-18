<?php

require_once __DIR__ . '/Connexion.php';

const MOTIFS_SANCTION = [
    'FRAUDE_SOUS_DECLARATION',
    'RETARD_REPETE_3_MOIS',
    'NON_DECLARATION_PROLONGEE',
    'AUTRE',
];

function bd_sanction_lister()
{
    $link = Connexion();
    $sql = "SELECT s.*, c.numero_client, c.nom, c.prenom, c.id_utilisateur,
                   ct.annee AS annee_controle, ct.resultat AS resultat_controle,
                   f.numero_facture
            FROM sanctions s
            JOIN clients c ON c.id_client = s.id_client
            LEFT JOIN controle_annuel ct ON ct.id_controle = s.id_controle
            LEFT JOIN factures f ON f.id_facture = s.id_facture_exceptionnelle
            ORDER BY s.created_at DESC, s.id_sanction DESC";
    $resultat = mysqli_query($link, $sql);
    if (!$resultat) {
        throw new RuntimeException("Impossible de lire les sanctions : " . mysqli_error($link));
    }
    return mysqli_fetch_all($resultat, MYSQLI_ASSOC);
}

function bd_sanction_clients_lister()
{
    $link = Connexion();
    $resultat = mysqli_query($link, "SELECT id_client, nom, prenom, numero_client FROM clients ORDER BY nom, prenom");
    if (!$resultat) {
        throw new RuntimeException("Impossible de lire les clients : " . mysqli_error($link));
    }
    return mysqli_fetch_all($resultat, MYSQLI_ASSOC);
}

function bd_sanction_trouver($idSanction)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "SELECT s.*, c.id_utilisateur
         FROM sanctions s
         JOIN clients c ON c.id_client = s.id_client
         WHERE s.id_sanction = ?"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la recherche de la sanction : " . mysqli_error($link));
    }
    $idSanction = (int) $idSanction;
    mysqli_stmt_bind_param($stmt, 'i', $idSanction);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de rechercher la sanction : " . $erreur);
    }
    $sanction = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $sanction ?: null;
}

function bd_sanction_creer(array $donnees)
{
    $link = Connexion();
    $reference = 'SAN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    $montantTotal = round(
        (float) $donnees['montant_energie_regularisee']
        + (float) $donnees['penalite_fraude']
        + (float) $donnees['frais_retablissement'],
        2
    );
     $stmt = mysqli_prepare(
        $link,
        "INSERT INTO sanctions (
            reference_sanction, id_client, id_contrat, motif, montant_energie_regularisee,
            penalite_fraude, frais_retablissement, montant_total,
            date_limite_paiement, date_coupure_programmee
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la création de la sanction : " . mysqli_error($link));
    }
    $idClient = (int) $donnees['id_client'];
    $idContrat = $donnees['id_contrat'] !== null ? (int) $donnees['id_contrat'] : null;
    $energie = (float) $donnees['montant_energie_regularisee'];
    $penalite = (float) $donnees['penalite_fraude'];
    $frais = (float) $donnees['frais_retablissement'];
    $dateLimite = $donnees['date_limite_paiement'];
    $dateCoupure = $donnees['date_coupure_programmee'];
    mysqli_stmt_bind_param(
        $stmt,
        'siisddddss',
        $reference,
        $idClient,
        $idContrat,
        $donnees['motif'],
        $energie,
        $penalite,
        $frais,
        $montantTotal,
        $dateLimite,
        $dateCoupure
    );
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de créer la sanction : " . $erreur);
    }
    $id = (int) mysqli_insert_id($link);
    mysqli_stmt_close($stmt);
    return ['id' => $id, 'reference' => $reference];
}

function bd_sanction_decider($idSanction, $statut)
{
    if (!in_array($statut, ['CONFIRMEE', 'ANNULEE'], true)) {
        throw new InvalidArgumentException('Décision de sanction invalide.');
    }
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "UPDATE sanctions SET statut = ?
         WHERE id_sanction = ? AND statut IN ('NOTIFIEE', 'CONTESTEE')"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la décision de sanction : " . mysqli_error($link));
    }
    $idSanction = (int) $idSanction;
    mysqli_stmt_bind_param($stmt, 'si', $statut, $idSanction);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible d’enregistrer la décision de sanction : " . $erreur);
    }
    $modifie = mysqli_stmt_affected_rows($stmt) === 1;
    mysqli_stmt_close($stmt);
    return $modifie;
}
