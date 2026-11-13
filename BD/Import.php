<?php

require_once __DIR__ . '/Connexion.php';

function bd_import_lister_rejets($annee = null, $limite = 100)
{
    $link = Connexion();
    $limite = max(1, min(500, (int) $limite));

    if ($annee !== null) {
        $stmt = mysqli_prepare(
            $link,
            "SELECT id_rejet, nom_fichier, annee, numero_ligne, contenu_brut, motif_rejet, date_import
             FROM import_lignes_rejetees
             WHERE annee = ?
             ORDER BY date_import DESC, numero_ligne ASC
             LIMIT ?"
        );
        if (!$stmt) {
            throw new RuntimeException('Impossible de préparer la liste des lignes rejetées : ' . mysqli_error($link));
        }
        $annee = (int) $annee;
        mysqli_stmt_bind_param($stmt, 'ii', $annee, $limite);
    } else {
        $stmt = mysqli_prepare(
            $link,
            "SELECT id_rejet, nom_fichier, annee, numero_ligne, contenu_brut, motif_rejet, date_import
             FROM import_lignes_rejetees
             ORDER BY date_import DESC, numero_ligne ASC
             LIMIT ?"
        );
        if (!$stmt) {
            throw new RuntimeException('Impossible de préparer la liste des lignes rejetées : ' . mysqli_error($link));
        }
        mysqli_stmt_bind_param($stmt, 'i', $limite);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Impossible de récupérer les lignes rejetées : ' . $erreur);
    }

    $resultat = mysqli_stmt_get_result($stmt);
    $lignes = mysqli_fetch_all($resultat, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return $lignes;
}

function bd_import_enregistrer_rejet($nomFichier, $annee, $numeroLigne, $contenuBrut, $motif)
{
    $link = Connexion();
    $stmt = mysqli_prepare(
        $link,
        "INSERT INTO import_lignes_rejetees (nom_fichier, annee, numero_ligne, contenu_brut, motif_rejet)
         VALUES (?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new RuntimeException("Impossible de préparer l’enregistrement d’une ligne rejetée : " . mysqli_error($link));
    }
    $annee = $annee === null ? null : (int) $annee;
    $numeroLigne = (int) $numeroLigne;
    mysqli_stmt_bind_param($stmt, 'siiss', $nomFichier, $annee, $numeroLigne, $contenuBrut, $motif);
    if (!mysqli_stmt_execute($stmt)) {
        $erreur = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException("Impossible d’enregistrer une ligne rejetée : " . $erreur);
    }
    mysqli_stmt_close($stmt);
}
