
<?php
// Détection et traitement des anomalies de consommation.

require_once ROOT . 'BD/Anomalie.php';
require_once ROOT . 'BD/Parametre.php';
require_once ROOT . 'Traitement/Auth.php';
require_once ROOT . 'Traitement/Facturation.php';

/*
 * Ne pas redéclarer TYPES_ANOMALIE :
 * cette constante existe déjà dans BD/Anomalie.php.
 */
const TYPES_ANOMALIE_LIBELLES = [
    'INDEX_INFERIEUR_EAU'  => "Index d'eau inférieur au précédent",
    'INDEX_INFERIEUR_ELEC' => "Index d'électricité inférieur au précédent",
    'SEUIL_DEPASSE_EAU'    => "Consommation d'eau anormalement élevée",
    'SEUIL_DEPASSE_ELEC'   => "Consommation d'électricité anormalement élevée",
    'PHOTO_ILLISIBLE'      => "Photo du compteur illisible",
    'AUTRE'                => "Autre anomalie",
];

const STATUTS_ANOMALIE_LIBELLES = [
    'EN_ATTENTE'     => 'En attente',
    'CORRIGEE'       => 'Corrigée',
    'VALIDEE_FORCEE' => 'Validée telle quelle',
    'REJETEE'        => 'Rejetée',
];

/* ============================================================
   Fonctions auxiliaires du traitement
   Elles remplacent les appels bd_anomalie_* inexistants.
   ============================================================ */

/**
 * Récupérer une consommation.
 */
function traitement_anomalie_conso($idConso)
{
    $link = Connexion();

    $stmt = mysqli_prepare(
        $link,
        "SELECT *
         FROM consommations
         WHERE id_conso = ?"
    );

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "i", $idConso);
    mysqli_stmt_execute($stmt);

    $resultat = mysqli_stmt_get_result($stmt);
    $conso = mysqli_fetch_assoc($resultat);

    mysqli_stmt_close($stmt);

    return $conso ?: null;
}

/**
 * Calculer la moyenne des consommations antérieures valides.
 */
function traitement_anomalie_moyenne(
    $idClient,
    $annee,
    $mois,
    $fluide
) {
    if (!in_array($fluide, ['eau', 'elec'], true)) {
        return 0.0;
    }

    $colonne = $fluide === 'eau' ? 'conso_eau' : 'conso_elec';
    $link = Connexion();

    $stmt = mysqli_prepare(
        $link,
        "SELECT AVG($colonne) AS moyenne
         FROM (
             SELECT $colonne
             FROM consommations
             WHERE id_client = ?
               AND statut IN ('VALIDE', 'REGULARISEE')
               AND (
                    annee < ?
                    OR (annee = ? AND mois < ?)
               )
             ORDER BY annee DESC, mois DESC
             LIMIT 3
         ) AS historique"
    );

    if (!$stmt) {
        return 0.0;
    }

    mysqli_stmt_bind_param(
        $stmt,
        "iiii",
        $idClient,
        $annee,
        $annee,
        $mois
    );

    mysqli_stmt_execute($stmt);
    $resultat = mysqli_stmt_get_result($stmt);
    $ligne = mysqli_fetch_assoc($resultat);

    mysqli_stmt_close($stmt);

    return (float) ($ligne['moyenne'] ?? 0);
}

/**
 * Vérifier si une anomalie du même type est déjà en attente.
 */
function traitement_anomalie_existe($idConso, $type)
{
    $link = Connexion();

    $stmt = mysqli_prepare(
        $link,
        "SELECT id_anomalie
         FROM anomalies
         WHERE id_conso = ?
           AND type_anomalie = ?
           AND statut = 'EN_ATTENTE'
         LIMIT 1"
    );

    if (!$stmt) {
        return true;
    }

    mysqli_stmt_bind_param($stmt, "is", $idConso, $type);
    mysqli_stmt_execute($stmt);

    $resultat = mysqli_stmt_get_result($stmt);
    $existe = mysqli_num_rows($resultat) > 0;

    mysqli_stmt_close($stmt);

    return $existe;
}

/**
 * Mettre à jour le statut de la consommation.
 */
function traitement_anomalie_statut_conso($idConso, $statut)
{
    $link = Connexion();

    $stmt = mysqli_prepare(
        $link,
        "UPDATE consommations
         SET statut = ?
         WHERE id_conso = ?"
    );

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "si", $statut, $idConso);
    $ok = mysqli_stmt_execute($stmt);

    mysqli_stmt_close($stmt);

    return $ok;
}

/**
 * Obtenir les types d'anomalies en attente pour une consommation.
 */
function traitement_anomalie_types_ouverts($idConso)
{
    $link = Connexion();

    $stmt = mysqli_prepare(
        $link,
        "SELECT type_anomalie
         FROM anomalies
         WHERE id_conso = ?
           AND statut = 'EN_ATTENTE'"
    );

    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $idConso);
    mysqli_stmt_execute($stmt);

    $resultat = mysqli_stmt_get_result($stmt);
    $types = [];

    while ($ligne = mysqli_fetch_assoc($resultat)) {
        $types[] = $ligne['type_anomalie'];
    }

    mysqli_stmt_close($stmt);

    return $types;
}

/**
 * Récupérer une anomalie sous forme de tableau associatif.
 */
function traitement_anomalie_trouver($id)
{
    $resultat = trouverAnomalieParId($id);

    if (!$resultat) {
        return null;
    }

    $ligne = mysqli_fetch_assoc($resultat);

    mysqli_free_result($resultat);

    return $ligne ?: null;
}

/* ============================================================
   Détection des anomalies
   ============================================================ */

function detecterAnomalies($c)
{
    $fluides = [
        'eau' => [
            'unite' => 'm³',
            'param' => 'SEUIL_MAX_EAU_MENSUEL',
            'defaut' => 100,
            'nom' => 'eau',
            'inf' => 'INDEX_INFERIEUR_EAU',
            'sup' => 'SEUIL_DEPASSE_EAU',
        ],
        'elec' => [
            'unite' => 'kWh',
            'param' => 'SEUIL_MAX_ELEC_MENSUEL',
            'defaut' => 2000,
            'nom' => 'électricité',
            'inf' => 'INDEX_INFERIEUR_ELEC',
            'sup' => 'SEUIL_DEPASSE_ELEC',
        ],
    ];

    $coef = (float) lireParametre('COEF_SEUIL_MOYENNE', 2.5);
    $anomalies = [];

    foreach ($fluides as $cle => $f) {
        $prec = (float) ($c["index_{$cle}_precedent"] ?? 0);
        $nouveau = (float) ($c["index_{$cle}_nouveau"] ?? 0);
        $conso = $nouveau - $prec;

        if ($conso < 0) {
            $anomalies[] = [
                'type' => $f['inf'],
                'description' =>
                    "Index {$f['nom']} saisi ($nouveau) inférieur à l'index précédent ($prec).",
                'valeur_saisie' => $nouveau,
                'valeur_attendue' => $prec,
            ];

            continue;
        }

        $seuil = (float) lireParametre($f['param'], $f['defaut']);

        $moyenne = traitement_anomalie_moyenne(
            (int) $c['id_client'],
            (int) $c['annee'],
            (int) $c['mois'],
            $cle
        );

        if ($moyenne > 0) {
            $seuil = min($seuil, round($coef * $moyenne));
        }

        if ($conso > $seuil) {
            $anomalies[] = [
                'type' => $f['sup'],
                'description' =>
                    "Consommation {$f['nom']} de $conso {$f['unite']} supérieure au seuil de $seuil {$f['unite']}.",
                'valeur_saisie' => $conso,
                'valeur_attendue' => $seuil,
            ];
        }
    }

    return $anomalies;
}

/**
 * Appelé par Traitement/Consommation.php.
 */
function signalerAnomalie($idConso)
{
    $c = traitement_anomalie_conso($idConso);

    if (!$c) {
        return false;
    }

    $anomalies = detecterAnomalies($c);

    if (!$anomalies) {
        return false;
    }

    foreach ($anomalies as $a) {
        if (!traitement_anomalie_existe($idConso, $a['type'])) {
            $ok = creerAnomalie(
                $idConso,
                $a['type'],
                $a['description'],
                $a['valeur_saisie'],
                $a['valeur_attendue']
            );

            if (!$ok) {
                return false;
            }
        }
    }

    if (!traitement_anomalie_statut_conso(
        $idConso,
        'EN_ATTENTE_CORRECTION'
    )) {
        return false;
    }

    return true;
}

/* ============================================================
   Pages fournisseur
   ============================================================ */

function anomalie_retour($url, $message, $cle = 'erreur')
{
    $_SESSION[$cle] = $message;
    rediriger($url);
}

function Anomalie_index($param = null)
{
    exiger_role('FOURNISSEUR');

    $statut = $_GET['statut'] ?? '';

    if (
        $statut !== ''
        && !array_key_exists($statut, STATUTS_ANOMALIE_LIBELLES)
    ) {
        $statut = '';
    }

    $res = listerAnomalies($statut === '' ? null : $statut);

    include ROOT . 'IHM/fournisseur/anomalies.php';
}

function Anomalie_detail($param = null)
{
    exiger_role('FOURNISSEUR');

    $a = ctype_digit((string) $param)
        ? traitement_anomalie_trouver((int) $param)
        : null;

    if (!$a) {
        http_response_code(404);
        include ROOT . 'IHM/404.php';
        return;
    }

    $erreur = $_SESSION['erreur'] ?? '';
    unset($_SESSION['erreur']);

    $seuilEau = (int) lireParametre('SEUIL_MAX_EAU_MENSUEL', 100);
    $seuilElec = (int) lireParametre('SEUIL_MAX_ELEC_MENSUEL', 2000);

    $typesOuverts = traitement_anomalie_types_ouverts($a['id_conso']);
    $peutValider = $a['statut'] === 'EN_ATTENTE' && count($typesOuverts) > 0;

    foreach ($typesOuverts as $type) {
        if (strpos($type, 'SEUIL_DEPASSE') !== 0) {
            $peutValider = false;
            break;
        }
    }

    include ROOT . 'IHM/fournisseur/anomalie_detail.php';
}

/**
 * Corriger les index.
 *
 * Les champs rectifiés utilisés ici sont ceux sélectionnés
 * dans trouverAnomalieParId() de BD/Anomalie.php.
 */
function Anomalie_corriger($param = null)
{
    exiger_role('FOURNISSEUR');

    $id = ctype_digit((string) $param) ? (int) $param : 0;
    $retour = 'Anomalie/detail/' . $id;
    $a = $id > 0 ? traitement_anomalie_trouver($id) : null;

    if (!$a || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        rediriger('Anomalie/index');
    }

    if ($a['statut'] !== 'EN_ATTENTE') {
        anomalie_retour($retour, "Cette anomalie a déjà été traitée.");
    }

    if (
        !ctype_digit($_POST['index_eau'] ?? '')
        || !ctype_digit($_POST['index_elec'] ?? '')
    ) {
        anomalie_retour($retour, "Les index doivent être des nombres entiers positifs.");
    }

    $indexEau = (int) $_POST['index_eau'];
    $indexElec = (int) $_POST['index_elec'];
    $precEau = (int) $a['index_eau_precedent'];
    $precElec = (int) $a['index_elec_precedent'];

    if ($indexEau < $precEau || $indexElec < $precElec) {
        anomalie_retour(
            $retour,
            "Un index corrigé ne peut pas être inférieur à l'index précédent."
        );
    }

    $link = Connexion();
    mysqli_begin_transaction($link);

    try {
        $stmt = mysqli_prepare(
            $link,
            "UPDATE consommations
             SET index_eau_nouveau = ?,
                 conso_eau = ?,
                 index_elec_nouveau = ?,
                 conso_elec = ?,
                 statut = 'VALIDE',
                 type_saisie = 'CORRIGEE'
             WHERE id_conso = ?"
        );

        if (!$stmt) {
            throw new Exception("Préparation de la correction impossible.");
        }

        $consoEau = $indexEau - $precEau;
        $consoElec = $indexElec - $precElec;
        $idConso = (int) $a['id_conso'];

        mysqli_stmt_bind_param(
            $stmt,
            "iiiii",
            $indexEau,
            $consoEau,
            $indexElec,
            $consoElec,
            $idConso
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Échec de la correction des index.");
        }

        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare(
            $link,
            "UPDATE anomalies
             SET statut = 'CORRIGEE',
                 date_traitement = NOW(),
                 index_eau_rectifie = ?,
                 conso_eau_rectifiee = ?,
                 index_elec_rectifie = ?,
                 conso_elec_rectifiee = ?
             WHERE id_conso = ?
               AND statut = 'EN_ATTENTE'"
        );

        if (!$stmt) {
            throw new Exception("Préparation de la mise à jour des anomalies impossible.");
        }

        mysqli_stmt_bind_param(
            $stmt,
            "iiiii",
            $indexEau,
            $consoEau,
            $indexElec,
            $consoElec,
            $idConso
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Échec de la mise à jour des anomalies.");
        }

        mysqli_stmt_close($stmt);
        mysqli_commit($link);
    } catch (Throwable $e) {
        mysqli_rollback($link);
        anomalie_retour($retour, "Erreur lors de l'enregistrement de la correction.");
        return;
    }

    genererFacture($idConso);
    anomalie_retour(
        'Anomalie/index',
        "Anomalie corrigée, la facture a été générée.",
        'message'
    );
}

/**
 * Valider une consommation malgré un dépassement de seuil.
 */
function Anomalie_valider($param = null)
{
    exiger_role('FOURNISSEUR');

    $id = ctype_digit((string) $param) ? (int) $param : 0;
    $retour = 'Anomalie/detail/' . $id;
    $a = $id > 0 ? traitement_anomalie_trouver($id) : null;

    if (!$a || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        rediriger('Anomalie/index');
    }

    if ($a['statut'] !== 'EN_ATTENTE') {
        anomalie_retour($retour, "Cette anomalie a déjà été traitée.");
    }

    $types = traitement_anomalie_types_ouverts($a['id_conso']);

    if (!$types) {
        anomalie_retour($retour, "Aucune anomalie en attente à valider.");
    }

    foreach ($types as $type) {
        if (strpos($type, 'SEUIL_DEPASSE') !== 0) {
            anomalie_retour(
                $retour,
                "Cette saisie contient une erreur d'index : elle doit être corrigée, pas validée."
            );
        }
    }

    $idConso = (int) $a['id_conso'];
    $link = Connexion();

    mysqli_begin_transaction($link);

    try {
        $stmt = mysqli_prepare(
            $link,
            "UPDATE consommations
             SET statut = 'VALIDE'
             WHERE id_conso = ?"
        );

        if (!$stmt) {
            throw new Exception("Préparation de la validation impossible.");
        }

        mysqli_stmt_bind_param($stmt, "i", $idConso);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Échec de la validation de la consommation.");
        }

        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare(
            $link,
            "UPDATE anomalies
             SET statut = 'VALIDEE_FORCEE',
                 date_traitement = NOW()
             WHERE id_conso = ?
               AND statut = 'EN_ATTENTE'"
        );

        if (!$stmt) {
            throw new Exception("Préparation du traitement des anomalies impossible.");
        }

        mysqli_stmt_bind_param($stmt, "i", $idConso);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Échec du traitement des anomalies.");
        }

        mysqli_stmt_close($stmt);
        mysqli_commit($link);
    } catch (Throwable $e) {
        mysqli_rollback($link);
        anomalie_retour($retour, "Erreur lors de la validation.");
        return;
    }

    genererFacture($idConso);

    anomalie_retour(
        'Anomalie/index',
        "Saisie validée, la facture a été générée.",
        'message'
    );
}
