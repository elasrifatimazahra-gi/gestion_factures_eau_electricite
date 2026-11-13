<?php

require_once ROOT . 'BD/Import.php';
require_once ROOT . 'BD/Agent.php';
require_once ROOT . 'BD/Controle.php';
require_once ROOT . 'BD/Journal.php';
require_once ROOT . 'Traitement/Controle.php';
require_once ROOT . 'Traitement/Auth.php';

const IMPORT_ANNUEL_TAILLE_MAX = 10 * 1024 * 1024;

function Import_index($param = null)
{
    exiger_role('FOURNISSEUR');

    $annee = filter_input(INPUT_GET, 'annee', FILTER_VALIDATE_INT);
    $annee = $annee !== false && $annee !== null ? $annee : null;
    $lignesRejetees = bd_import_lister_rejets($annee);

    include ROOT . 'IHM/fournisseur/import.php';
}

function import_annuel_analyser_ligne($ligne)
{
    $ligne = ltrim($ligne, "\xEF\xBB\xBF");
    foreach (["\t", ';', ','] as $separateur) {
        $champs = str_getcsv($ligne, $separateur, '"', '\\');
        if (count($champs) === 6) {
            return array_map('trim', $champs);
        }
    }
    return null;
}

function import_annuel_entier($valeur, $minimum = 0)
{
    if (!is_string($valeur) || $valeur === '' || !ctype_digit($valeur)) {
        return null;
    }
    $nombre = filter_var($valeur, FILTER_VALIDATE_INT);
    return $nombre !== false && $nombre >= $minimum ? $nombre : null;
}

function import_annuel_est_entete($champs)
{
    return $champs
        && strcasecmp(ltrim($champs[0], "\xEF\xBB\xBF"), 'ID_Contrat') === 0;
}

function import_annuel_traiter($idAgent)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rediriger('Consommation/import_annuel');
    }
    if (!csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        $_SESSION['erreur_agent_espace'] = 'Le formulaire a expiré. Veuillez réessayer.';
        rediriger('Consommation/import_annuel');
    }

    $fichier = $_FILES['fichier'] ?? null;
    $nomOriginal = is_array($fichier) ? ($fichier['name'] ?? null) : null;
    $cheminTemporaire = is_array($fichier) ? ($fichier['tmp_name'] ?? null) : null;
    $extension = is_string($nomOriginal) ? strtolower(pathinfo($nomOriginal, PATHINFO_EXTENSION)) : '';
    if (!is_array($fichier) || !is_string($nomOriginal) || !is_string($cheminTemporaire)
        || ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !in_array($extension, ['txt', 'csv'], true)
        || (int) ($fichier['size'] ?? 0) <= 0
        || (int) $fichier['size'] > IMPORT_ANNUEL_TAILLE_MAX
        || !is_uploaded_file($cheminTemporaire)) {
        $_SESSION['erreur_agent_espace'] = 'Sélectionnez un fichier TXT ou CSV valide de 10 Mo maximum.';
        rediriger('Consommation/import_annuel');
    }

    $handle = fopen($cheminTemporaire, 'rb');
    if ($handle === false) {
        $_SESSION['erreur_agent_espace'] = 'Impossible de lire le fichier transmis.';
        rediriger('Consommation/import_annuel');
    }

    $nomFichier = substr(basename($nomOriginal), 0, 100);
    $contrats = bd_agent_tournee_lister((int) $idAgent);
    $contratsParId = [];
    foreach ($contrats as $contrat) {
        $contratsParId[(int) $contrat['id_contrat']] = $contrat;
    }

    $link = Connexion();
    if (!mysqli_begin_transaction($link)) {
        fclose($handle);
        throw new RuntimeException("Impossible de démarrer l’import annuel : " . mysqli_error($link));
    }

    $ligneNumero = 0;
    $lignesDonnees = 0;
    $lignesValides = 0;
    $lignesRejetees = 0;
    try {
        while (($ligneBrute = fgets($handle)) !== false) {
            $ligneNumero++;
            if (trim($ligneBrute) === '') {
                continue;
            }

            $champs = import_annuel_analyser_ligne($ligneBrute);
            if (import_annuel_est_entete($champs)) {
                continue;
            }
            $lignesDonnees++;

            $annee = $champs ? import_annuel_entier($champs[4], 2000) : null;
            $anneeRejet = $annee !== null && $annee <= (int) date('Y') ? $annee : null;
            $motif = null;
            if (!$champs) {
                $motif = 'La ligne doit contenir six colonnes séparées par ;, une tabulation ou ,.';
            } else {
                [$idContrat, $idAgentLigne, $eauReelle, $elecReelle, $anneeLigne, $dateSaisie] = $champs;
                $idContrat = import_annuel_entier($idContrat, 1);
                $idAgentLigne = import_annuel_entier($idAgentLigne, 1);
                $eauReelle = import_annuel_entier($eauReelle);
                $elecReelle = import_annuel_entier($elecReelle);
                $annee = import_annuel_entier($anneeLigne, 2000);
                $dateSaisie = controle_date_normaliser($dateSaisie);

                if ($idContrat === null || $idAgentLigne === null || $eauReelle === null || $elecReelle === null
                    || $annee === null || $annee > (int) date('Y')
                    || $dateSaisie === null || $dateSaisie > date('Y-m-d')) {
                    $motif = 'Une ou plusieurs valeurs sont absentes ou invalides.';
                } elseif ($idAgentLigne !== (int) $idAgent) {
                    $motif = 'L’identifiant agent de la ligne ne correspond pas à votre compte.';
                } elseif (!isset($contratsParId[$idContrat])) {
                    $motif = 'Le contrat n’est pas actif ou n’appartient pas à votre tournée.';
                } elseif (bd_controle_annuel_existe($idContrat, $annee)) {
                    $motif = 'Un contrôle annuel existe déjà pour ce contrat et cette année.';
                }
            }

            if ($motif !== null) {
                bd_import_enregistrer_rejet($nomFichier, $anneeRejet, $ligneNumero, rtrim($ligneBrute, "\r\n"), $motif);
                $lignesRejetees++;
                continue;
            }

            $donnees = controle_annuel_donnees_preparer(
                $contratsParId[$idContrat],
                $idAgent,
                $annee,
                $dateSaisie,
                $eauReelle,
                $elecReelle
            );
            bd_controle_annuel_creer($donnees);
            $lignesValides++;
        }

        if (!feof($handle)) {
            throw new RuntimeException('Une erreur est survenue pendant la lecture du fichier.');
        }
        if ($lignesDonnees === 0) {
            throw new RuntimeException('Le fichier ne contient aucune ligne de contrôle.');
        }
        if ($lignesValides > 0) {
            bd_journal_enregistrer(
                (int) $_SESSION['id_utilisateur'],
                'IMPORT_CONTROLE_ANNUEL',
                'controle_annuel',
                null,
                sprintf('Import annuel %s : %d ligne(s) valide(s), %d rejetée(s).', $nomFichier, $lignesValides, $lignesRejetees)
            );
        }
        if (!mysqli_commit($link)) {
            throw new RuntimeException("Impossible de valider l’import annuel : " . mysqli_error($link));
        }
    } catch (RuntimeException $exception) {
        mysqli_rollback($link);
        fclose($handle);
        error_log($exception->getMessage());
        $_SESSION['erreur_agent_espace'] = 'L’import a échoué. Aucune ligne de ce fichier n’a été enregistrée.';
        rediriger('Consommation/import_annuel');
    }
    fclose($handle);

    $_SESSION['message_agent_espace'] = sprintf(
        'Import terminé : %d contrôle(s) enregistré(s), %d ligne(s) rejetée(s).',
        $lignesValides,
        $lignesRejetees
    );
    rediriger('Consommation/import_annuel');
}
