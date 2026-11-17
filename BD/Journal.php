<?php

require_once __DIR__ . '/Connexion.php';

function bd_journal_enregistrer($idUtilisateur, $action, $table, $idEntite, $description, $ancienneValeur = null, $nouvelleValeur = null)
{
    $link = Connexion();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = mysqli_prepare(
        $link,
        "INSERT INTO journal_actions
            (id_utilisateur, action_type, table_concernee, id_entite, description, ancienne_valeur, nouvelle_valeur, adresse_ip)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer l'enregistrement du journal : " . mysqli_error($link));
    }

    $idUtilisateur = $idUtilisateur === null ? null : (int) $idUtilisateur;
    $idEntite = $idEntite === null ? null : (int) $idEntite;
    mysqli_stmt_bind_param(
        $stmt,
        "ississss",
        $idUtilisateur,
        $action,
        $table,
        $idEntite,
        $description,
        $ancienneValeur,
        $nouvelleValeur,
        $ip
    );
    $execute = mysqli_stmt_execute($stmt);
    $erreur = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    if (!$execute) {
        throw new RuntimeException("Impossible d'enregistrer l'action dans le journal : " . $erreur);
    }
}

function bd_journal_types()
{
    $link = Connexion();
    $resultat = mysqli_query($link, "SELECT DISTINCT action_type FROM journal_actions ORDER BY action_type");
    if (!$resultat) {
        throw new RuntimeException("Impossible de récupérer les types d'action : " . mysqli_error($link));
    }
    return array_column(mysqli_fetch_all($resultat, MYSQLI_ASSOC), 'action_type');
}

function bd_journal_lister(array $filtres = [], $limite = 200)
{
    $link = Connexion();
    $conditions = [];
    $types = '';
    $valeurs = [];

    if (!empty($filtres['action_type'])) {
        $conditions[] = 'j.action_type = ?';
        $types .= 's';
        $valeurs[] = $filtres['action_type'];
    }
    if (!empty($filtres['date_debut'])) {
        $conditions[] = 'j.date_action >= ?';
        $types .= 's';
        $valeurs[] = $filtres['date_debut'] . ' 00:00:00';
    }
    if (!empty($filtres['date_fin'])) {
        $conditions[] = 'j.date_action < DATE_ADD(?, INTERVAL 1 DAY)';
        $types .= 's';
        $valeurs[] = $filtres['date_fin'];
    }

// ===== BD/Journal.php : dans bd_journal_lister(), REMPLACER le bloc $sql = "SELECT ... LEFT JOIN utilisateurs ..."; par : =====
// (le reste de la fonction ne change pas ; la colonne adresse_ip reste enregistrée en base mais n'est plus affichée)

 
    $agentsLies = bd_journal_colonne_existe('agents', 'id_utilisateur');
    $nomAgent = $agentsLies ? "CONCAT(ag.nom, ' ', ag.prenom)" : 'NULL';
    $jointureAgents = $agentsLies ? 'LEFT JOIN agents ag ON ag.id_utilisateur = j.id_utilisateur' : '';
 
// ===== BD/Journal.php, dans bd_journal_lister() : REMPLACER le bloc $sql = "SELECT ... LEFT JOIN utilisateurs ..."; par ce bloc =====
// Les LEFT JOIN fj, pj, cj, tj retrouvent le CLIENT CONCERNÉ à partir de table_concernee + id_entite.
// (une seule modification, aucune fonction à ajouter ; $link est déjà défini au début de la fonction)

    $verification = mysqli_query($link, "SHOW COLUMNS FROM `agents` LIKE 'id_utilisateur'");
    $agentsLies = $verification && mysqli_num_rows($verification) > 0;
    $nomAgent = $agentsLies ? "CONCAT(ag.nom, ' ', ag.prenom)" : 'NULL';
    $jointureAgents = $agentsLies ? 'LEFT JOIN agents ag ON ag.id_utilisateur = j.id_utilisateur' : '';

    $sql = "SELECT j.id_log, j.id_utilisateur, j.action_type, j.table_concernee, j.id_entite, j.description,
                   j.ancienne_valeur, j.nouvelle_valeur, j.date_action,
                   u.identifiant, u.email, u.role,
                   COALESCE(CONCAT(cl.nom, ' ', cl.prenom), $nomAgent) AS nom_complet,
                   cc.numero_client AS client_numero, CONCAT(cc.nom, ' ', cc.prenom) AS client_nom
            FROM journal_actions j
            LEFT JOIN utilisateurs u ON u.id_utilisateur = j.id_utilisateur
            LEFT JOIN clients cl ON cl.id_utilisateur = j.id_utilisateur
            $jointureAgents
            LEFT JOIN factures fj ON j.table_concernee = 'factures' AND fj.id_facture = j.id_entite
            LEFT JOIN paiements pj ON j.table_concernee = 'paiements' AND pj.id_paiement = j.id_entite
            LEFT JOIN coupures_services cj ON j.table_concernee = 'coupures_services' AND cj.id_coupure = j.id_entite
            LEFT JOIN contrats tj ON j.table_concernee = 'contrats' AND tj.id_contrat = j.id_entite
            LEFT JOIN clients cc ON cc.id_client = COALESCE(fj.id_client, pj.id_client, cj.id_client, tj.id_client)";
 
         if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY j.date_action DESC, j.id_log DESC LIMIT ?';
    $types .= 'i';
    $valeurs[] = max(1, min(500, (int) $limite));

    $stmt = mysqli_prepare($link, $sql);
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer la lecture du journal : " . mysqli_error($link));
    }
    $arguments = [$stmt, $types];
    foreach ($valeurs as $index => &$valeur) {
        $arguments[] = &$valeur;
    }
    unset($valeur);
    call_user_func_array('mysqli_stmt_bind_param', $arguments);

    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible de lire le journal : " . $erreur);
    }
    $resultat = mysqli_stmt_get_result($stmt);
    $lignes = mysqli_fetch_all($resultat, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $lignes;
}
/** Vrai si la colonne existe dans la base (certaines bases n'ont pas agents.id_utilisateur). */
function bd_journal_colonne_existe($table, $colonne)
{
    if (!preg_match('/^\w+$/', $table) || !preg_match('/^\w+$/', $colonne)) {
        return false;
    }
    $resultat = mysqli_query(Connexion(), "SHOW COLUMNS FROM `$table` LIKE '$colonne'");
    return $resultat && mysqli_num_rows($resultat) > 0;
}