<?php
// Détection et traitement des anomalies de consommation (RG21, RG22, RG23).

require_once ROOT . 'BD/Anomalie.php';
require_once ROOT . 'BD/Parametre.php';
require_once ROOT . 'Traitement/Auth.php';
require_once ROOT . 'Traitement/Facturation.php';

/*
 * Ne pas redéclarer TYPES_ANOMALIE / STATUTS_ANOMALIE :
 * ces constantes existent déjà dans BD/Anomalie.php.
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
   ============================================================ */

/**
 * Récupérer une consommation.
 */
function traitement_anomalie_conso($idConso)
{
    $link = Connexion();

    $stmt = mysqli_prepare($link, "SELECT * FROM consommations WHERE id_conso = ?");
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "i", $idConso);
    mysqli_stmt_execute($stmt);
    $conso = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $conso ?: null;
}

/**
 * Moyenne des 3 dernières consommations valides antérieures au mois donné.
 */
function traitement_anomalie_moyenne($idClient, $annee, $mois, $fluide)
{
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
               AND (annee < ? OR (annee = ? AND mois < ?))
             ORDER BY annee DESC, mois DESC
             LIMIT 3
         ) AS historique"
    );
    if (!$stmt) {
        return 0.0;
    }

    mysqli_stmt_bind_param($stmt, "iiii", $idClient, $annee, $annee, $mois);
    mysqli_stmt_execute($stmt);
    $ligne = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (float) ($ligne['moyenne'] ?? 0);
}

/**
 * Seuil effectif d'un fluide : le plus bas entre le seuil fixe (paramètre)
 * et coef × moyenne des 3 derniers mois (si un historique existe).
 */
function traitement_anomalie_seuil($idClient, $annee, $mois, $fluide)
{
    $param  = $fluide === 'eau' ? 'SEUIL_MAX_EAU_MENSUEL' : 'SEUIL_MAX_ELEC_MENSUEL';
    $defaut = $fluide === 'eau' ? 100 : 2000;

    $seuil = (float) lireParametre($param, $defaut);
    $coef  = (float) lireParametre('COEF_SEUIL_MOYENNE', 2.5);
    $moyenne = traitement_anomalie_moyenne($idClient, $annee, $mois, $fluide);

    if ($moyenne > 0) {
        $seuil = min($seuil, round($coef * $moyenne));
    }

    return $seuil;
}

/**
 * Une anomalie du même type est-elle déjà en attente pour cette consommation ?
 */
function traitement_anomalie_existe($idConso, $type)
{
    $link = Connexion();

    $stmt = mysqli_prepare(
        $link,
        "SELECT id_anomalie FROM anomalies
         WHERE id_conso = ? AND type_anomalie = ? AND statut = 'EN_ATTENTE'
         LIMIT 1"
    );
    if (!$stmt) {
        return true;
    }

    mysqli_stmt_bind_param($stmt, "is", $idConso, $type);
    mysqli_stmt_execute($stmt);
    $existe = mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
    mysqli_stmt_close($stmt);

    return $existe;
}

/**
 * Mettre à jour le statut de la consommation.
 */
function traitement_anomalie_statut_conso($idConso, $statut)
{
    $link = Connexion();

    $stmt = mysqli_prepare($link, "UPDATE consommations SET statut = ? WHERE id_conso = ?");
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "si", $statut, $idConso);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

/**
 * Anomalies encore en attente pour une consommation (type + description).
 */
function traitement_anomalie_ouvertes($idConso)
{
    $link = Connexion();

    $stmt = mysqli_prepare(
        $link,
        "SELECT type_anomalie, description FROM anomalies
         WHERE id_conso = ? AND statut = 'EN_ATTENTE'
         ORDER BY id_anomalie"
    );
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, "i", $idConso);
    mysqli_stmt_execute($stmt);
    $resultat = mysqli_stmt_get_result($stmt);

    $lignes = [];
    while ($ligne = mysqli_fetch_assoc($resultat)) {
        $lignes[] = $ligne;
    }
    mysqli_stmt_close($stmt);

    return $lignes;
}

/**
 * Types d'anomalies en attente pour une consommation.
 */
function traitement_anomalie_types_ouverts($idConso)
{
    return array_column(traitement_anomalie_ouvertes($idConso), 'type_anomalie');
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

/**
 * Envoyer une notification au compte utilisateur d'un client.
 */
function traitement_anomalie_notifier($idClient, $titre, $message, $type)
{
    $link = Connexion();

    $stmt = mysqli_prepare(
        $link,
        "INSERT INTO notifications (id_utilisateur, titre, message, type)
         SELECT id_utilisateur, ?, ?, ? FROM clients WHERE id_client = ?"
    );
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, "sssi", $titre, $message, $type, $idClient);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

/* ============================================================
   Détection des anomalies
   ============================================================ */

function detecterAnomalies($c)
{
    $fluides = [
        'eau' => [
            'unite' => 'm³',
            'nom'   => 'eau',
            'inf'   => 'INDEX_INFERIEUR_EAU',
            'sup'   => 'SEUIL_DEPASSE_EAU',
        ],
        'elec' => [
            'unite' => 'kWh',
            'nom'   => 'électricité',
            'inf'   => 'INDEX_INFERIEUR_ELEC',
            'sup'   => 'SEUIL_DEPASSE_ELEC',
        ],
    ];

    $anomalies = [];

    foreach ($fluides as $cle => $f) {
        $prec    = (int) ($c["index_{$cle}_precedent"] ?? 0);
        $nouveau = (int) ($c["index_{$cle}_nouveau"] ?? 0);
        $conso   = $nouveau - $prec;

        if ($conso < 0) {
            $anomalies[] = [
                'type' => $f['inf'],
                'description' =>
                    "Index {$f['nom']} saisi ($nouveau) inférieur à l'index précédent ($prec).",
                'valeur_saisie'   => $nouveau,
                'valeur_attendue' => $prec,
            ];
            continue;
        }

        $seuil = traitement_anomalie_seuil(
            (int) $c['id_client'],
            (int) $c['annee'],
            (int) $c['mois'],
            $cle
        );

        if ($conso > $seuil) {
            $anomalies[] = [
                'type' => $f['sup'],
                'description' =>
                    "Consommation {$f['nom']} de $conso {$f['unite']} supérieure au seuil de $seuil {$f['unite']}.",
                'valeur_saisie'   => $conso,
                'valeur_attendue' => (int) $seuil,
            ];
        }
    }

    return $anomalies;
}

/**
 * Appelé par Traitement/Consommation.php après chaque enregistrement.
 * Retourne true si au moins une anomalie existe (la saisie est alors bloquée).
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

    if (!traitement_anomalie_statut_conso($idConso, 'EN_ATTENTE_CORRECTION')) {
        return false;
    }

    // RG22 : informer le client que sa saisie est en cours de vérification
    traitement_anomalie_notifier(
        (int) $c['id_client'],
        "Anomalie détectée sur votre saisie",
        "Votre relevé {$c['mois']}/{$c['annee']} présente une anomalie. "
        . "Il est en cours de vérification par le fournisseur.",
        'ANOMALIE_DETECTEE'
    );

    return true;
}

/* ============================================================
   Pages fournisseur
   ============================================================ */

/**
 * Échappement HTML utilisé par les vues d'anomalies.
 */
function anomalie_e($valeur)
{
    return htmlspecialchars((string) $valeur, ENT_QUOTES, 'UTF-8');
}

function anomalie_retour($url, $message, $cle = 'erreur')
{
    $_SESSION[$cle] = $message;
    rediriger($url);
}

function anomalie_commentaire()
{
    return mb_substr(trim($_POST['commentaire'] ?? ''), 0, 1000);
}

/**
 * Génère la facture puis notifie le client. Retourne true si une facture a été créée.
 */
function anomalie_facturer($idConso, $idClient)
{
    $idFacture = genererFacture($idConso);

    if ($idFacture) {
        traitement_anomalie_notifier(
            $idClient,
            "Facture disponible",
            "Votre saisie a été vérifiée et votre facture est disponible.",
            'FACTURE_DISPONIBLE'
        );
        return true;
    }

    return false;
}

function Anomalie_index($param = null)
{
    exiger_role('FOURNISSEUR');

    $statut = $_GET['statut'] ?? '';
    if ($statut !== '' && !array_key_exists($statut, STATUTS_ANOMALIE_LIBELLES)) {
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

    // Seuils effectifs réellement appliqués à cette saisie
    $seuilEau  = traitement_anomalie_seuil((int) $a['id_client'], (int) $a['annee'], (int) $a['mois'], 'eau');
    $seuilElec = traitement_anomalie_seuil((int) $a['id_client'], (int) $a['annee'], (int) $a['mois'], 'elec');

    $anomaliesOuvertes = traitement_anomalie_ouvertes((int) $a['id_conso']);
    $typesOuverts = array_column($anomaliesOuvertes, 'type_anomalie');

    // On ne peut valider telle quelle que des dépassements de seuil
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
 * Corriger les index (POST depuis IHM/fournisseur/anomalie_detail.php).
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

    if (!ctype_digit($_POST['index_eau'] ?? '') || !ctype_digit($_POST['index_elec'] ?? '')) {
        anomalie_retour($retour, "Les index doivent être des nombres entiers positifs.");
    }

    $indexEau  = (int) $_POST['index_eau'];
    $indexElec = (int) $_POST['index_elec'];
    $precEau   = (int) $a['index_eau_precedent'];
    $precElec  = (int) $a['index_elec_precedent'];

    if ($indexEau < $precEau || $indexElec < $precElec) {
        anomalie_retour($retour, "Un index corrigé ne peut pas être inférieur à l'index précédent.");
    }

    $consoEau   = $indexEau - $precEau;
    $consoElec  = $indexElec - $precElec;
    $idConso    = (int) $a['id_conso'];
    $idClient   = (int) $a['id_client'];
    $traitePar  = (int) $_SESSION['id_utilisateur'];
    $commentaire = anomalie_commentaire();

    $link = Connexion();
    mysqli_begin_transaction($link);

    try {
        $stmt = mysqli_prepare(
            $link,
            "UPDATE consommations
             SET index_eau_nouveau = ?, conso_eau = ?,
                 index_elec_nouveau = ?, conso_elec = ?,
                 statut = 'VALIDE', type_saisie = 'CORRIGEE'
             WHERE id_conso = ?"
        );
        if (!$stmt) {
            throw new Exception("Préparation de la correction impossible.");
        }

        mysqli_stmt_bind_param($stmt, "iiiii", $indexEau, $consoEau, $indexElec, $consoElec, $idConso);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Échec de la correction des index.");
        }
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare(
            $link,
            "UPDATE anomalies
             SET statut = 'CORRIGEE',
                 date_traitement = NOW(),
                 traite_par = ?,
                 commentaire_resolution = ?,
                 index_eau_rectifie = ?, conso_eau_rectifiee = ?,
                 index_elec_rectifie = ?, conso_elec_rectifiee = ?
             WHERE id_conso = ? AND statut = 'EN_ATTENTE'"
        );
        if (!$stmt) {
            throw new Exception("Préparation de la mise à jour des anomalies impossible.");
        }

        mysqli_stmt_bind_param(
            $stmt, "isiiiii",
            $traitePar, $commentaire,
            $indexEau, $consoEau, $indexElec, $consoElec,
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

    $facture = anomalie_facturer($idConso, $idClient);

    anomalie_retour(
        'Anomalie/index',
        $facture
            ? "Anomalie corrigée, la facture a été générée."
            : "Anomalie corrigée, mais la facture n'a pas pu être générée (déjà existante ou erreur).",
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

    $idConso     = (int) $a['id_conso'];
    $idClient    = (int) $a['id_client'];
    $traitePar   = (int) $_SESSION['id_utilisateur'];
    $commentaire = anomalie_commentaire();

    $link = Connexion();
    mysqli_begin_transaction($link);

    try {
        $stmt = mysqli_prepare($link, "UPDATE consommations SET statut = 'VALIDE' WHERE id_conso = ?");
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
                 date_traitement = NOW(),
                 traite_par = ?,
                 commentaire_resolution = ?
             WHERE id_conso = ? AND statut = 'EN_ATTENTE'"
        );
        if (!$stmt) {
            throw new Exception("Préparation du traitement des anomalies impossible.");
        }

        mysqli_stmt_bind_param($stmt, "isi", $traitePar, $commentaire, $idConso);
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

    $facture = anomalie_facturer($idConso, $idClient);

    anomalie_retour(
        'Anomalie/index',
        $facture
            ? "Saisie validée, la facture a été générée."
            : "Saisie validée, mais la facture n'a pas pu être générée (déjà existante ou erreur).",
        'message'
    );
}
