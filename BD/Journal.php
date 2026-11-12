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

    $sql = "SELECT j.id_log, j.action_type, j.table_concernee, j.id_entite, j.description,
                   j.ancienne_valeur, j.nouvelle_valeur, j.adresse_ip, j.date_action,
                   u.identifiant, u.role
            FROM journal_actions j
            LEFT JOIN utilisateurs u ON u.id_utilisateur = j.id_utilisateur";
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
