
<?php

require_once __DIR__ . "/Connexion.php";

/**
 * Types et statuts autorisés par le schéma SQL.
 */
const TYPES_ANOMALIE = [
    'INDEX_INFERIEUR_EAU',
    'INDEX_INFERIEUR_ELEC',
    'SEUIL_DEPASSE_EAU',
    'SEUIL_DEPASSE_ELEC',
    'PHOTO_ILLISIBLE',
    'AUTRE'
];

const STATUTS_ANOMALIE = [
    'EN_ATTENTE',
    'CORRIGEE',
    'VALIDEE_FORCEE',
    'REJETEE'
];

/**
 * Créer une anomalie à partir d'une consommation existante.
 */
function creerAnomalie(
    $idConso,
    $type,
    $description = 'Anomalie de consommation',
    $valeurSaisie = null,
    $valeurAttendue = null
) {
    if (!in_array($type, TYPES_ANOMALIE, true)) {
        return false;
    }

    $link = Connexion();

    // Récupérer le client associé à la consommation.
    $sql = mysqli_prepare(
        $link,
        "SELECT id_client
         FROM consommations
         WHERE id_conso = ?"
    );

    mysqli_stmt_bind_param($sql, "i", $idConso);
    mysqli_stmt_execute($sql);

    $resultat = mysqli_stmt_get_result($sql);
    $conso = mysqli_fetch_assoc($resultat);

    mysqli_stmt_close($sql);

    if (!$conso) {
        return false;
    }

    $idClient = (int) $conso['id_client'];

    $sql = mysqli_prepare(
        $link,
        "INSERT INTO anomalies (
            id_conso,
            id_client,
            type_anomalie,
            description,
            valeur_saisie,
            valeur_attendue_ou_seuil,
            statut,
            date_detection
        ) VALUES (?, ?, ?, ?, ?, ?, 'EN_ATTENTE', NOW())"
    );

    mysqli_stmt_bind_param(
        $sql,
        "iissii",
        $idConso,
        $idClient,
        $type,
        $description,
        $valeurSaisie,
        $valeurAttendue
    );

    $ok = mysqli_stmt_execute($sql);
    $id = $ok ? mysqli_insert_id($link) : false;

    mysqli_stmt_close($sql);

    return $id;
}

/**
 * Lister les anomalies, avec filtre optionnel sur le statut.
 */
function listerAnomalies($statut = null)
{
    $link = Connexion();

    $sql = "
        SELECT
            a.id_anomalie,
            a.id_conso,
            a.id_client,
            a.type_anomalie AS type,
            a.statut,
            a.description,
            a.date_detection,
            a.date_traitement,
            c.mois,
            c.annee,
            cl.numero_client,
            cl.nom,
            cl.prenom
        FROM anomalies a
        JOIN consommations c
            ON c.id_conso = a.id_conso
        JOIN clients cl
            ON cl.id_client = a.id_client
    ";

    if ($statut !== null && $statut !== '') {
        if (!in_array($statut, STATUTS_ANOMALIE, true)) {
            return false;
        }

        $sql .= "
            WHERE a.statut = ?
            ORDER BY a.date_detection DESC
        ";

        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "s", $statut);
        mysqli_stmt_execute($stmt);

        $resultat = mysqli_stmt_get_result($stmt);
        mysqli_stmt_close($stmt);

        return $resultat;
    }

    $sql .= " ORDER BY a.date_detection DESC";

    return mysqli_query($link, $sql);
}

/**
 * Trouver une anomalie avec sa consommation et son client.
 *
 * Les alias permettent de conserver les anciens noms utilisés
 * par la vue de détail.
 */
function trouverAnomalieParId($id)
{
    $link = Connexion();
    $id = (int) $id;

    $sql = mysqli_prepare(
        $link,
        "SELECT
            a.id_anomalie,
            a.id_conso,
            a.id_client,
            a.type_anomalie AS type,
            a.type_anomalie,
            a.description,
            a.statut,
            a.date_detection,
            a.date_traitement,
            a.index_eau_rectifie,
            a.conso_eau_rectifiee,
            a.index_elec_rectifie,
            a.conso_elec_rectifiee,
            c.mois,
            c.annee,
            c.index_eau_precedent,
            c.index_eau_nouveau AS index_eau,
            c.index_elec_precedent,
            c.index_elec_nouveau AS index_elec,
            c.conso_eau,
            c.conso_elec,
            c.photo_compteur_eau AS photo_eau,
            c.photo_compteur_elec AS photo_elec,
            cl.id_utilisateur,
            cl.numero_client,
            cl.nom,
            cl.prenom
        FROM anomalies a
        JOIN consommations c
            ON c.id_conso = a.id_conso
        JOIN clients cl
            ON cl.id_client = a.id_client
        WHERE a.id_anomalie = ?"
    );

    mysqli_stmt_bind_param($sql, "i", $id);
    mysqli_stmt_execute($sql);

    $resultat = mysqli_stmt_get_result($sql);
    mysqli_stmt_close($sql);

    return $resultat;
}

/**
 * Marquer une anomalie comme corrigée.
 */
function marquerAnomalieTraitee($id)
{
    $link = Connexion();
    $id = (int) $id;

    $sql = mysqli_prepare(
        $link,
        "UPDATE anomalies
         SET statut = 'CORRIGEE',
             date_traitement = NOW()
         WHERE id_anomalie = ?
           AND statut = 'EN_ATTENTE'"
    );

    mysqli_stmt_bind_param($sql, "i", $id);
    $ok = mysqli_stmt_execute($sql);

    mysqli_stmt_close($sql);

    return $ok;
}

/**
 * Trouver les index précédents de la consommation.
 *
 * On utilise les consommations antérieures valides.
 * S'il n'existe aucun historique, on utilise les index initiaux
 * enregistrés dans la table clients.
 */
function trouverIndexPrecedent($idClient, $mois, $annee)
{
    $link = Connexion();

    $idClient = (int) $idClient;
    $mois = (int) $mois;
    $annee = (int) $annee;

    $sql = mysqli_prepare(
        $link,
        "SELECT
            index_eau_nouveau,
            index_elec_nouveau
         FROM consommations
         WHERE id_client = ?
           AND statut IN ('VALIDE', 'REGULARISEE')
           AND (
                annee < ?
                OR (annee = ? AND mois < ?)
           )
         ORDER BY annee DESC, mois DESC
         LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $sql,
        "iiii",
        $idClient,
        $annee,
        $annee,
        $mois
    );

    mysqli_stmt_execute($sql);

    $resultat = mysqli_stmt_get_result($sql);
    $ligne = mysqli_fetch_assoc($resultat);

    mysqli_stmt_close($sql);

    if ($ligne) {
        return [
            'eau' => (float) $ligne['index_eau_nouveau'],
            'elec' => (float) $ligne['index_elec_nouveau']
        ];
    }

    $sql = mysqli_prepare(
        $link,
        "SELECT index_initial_eau, index_initial_elec
         FROM clients
         WHERE id_client = ?"
    );

    mysqli_stmt_bind_param($sql, "i", $idClient);
    mysqli_stmt_execute($sql);

    $resultat = mysqli_stmt_get_result($sql);
    $ligne = mysqli_fetch_assoc($resultat);

    mysqli_stmt_close($sql);

    return [
        'eau' => $ligne ? (float) $ligne['index_initial_eau'] : 0.0,
        'elec' => $ligne ? (float) $ligne['index_initial_elec'] : 0.0
    ];
}