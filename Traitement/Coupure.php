<?php
// Contrôleur des coupures de service par contrat (RG34, RG35) - Membre 4
// URLs : Coupure/index, Coupure/programmer, Coupure/executer, Coupure/retablir, Coupure/annuler (POST)
//
// Fonctions réutilisables par les autres modules :
//   coupure_programmer_contrat(...)  -> Traitement/Rappel.php (3 mois impayés) et sanctions du Membre 5
//   coupure_reevaluer_contrat($id)   -> appelée par Traitement/Paiement.php après un paiement

require_once ROOT . 'BD/Coupure.php';
require_once ROOT . 'BD/Parametre.php';
require_once ROOT . 'BD/Journal.php';
require_once ROOT . 'BD/Notification.php';
require_once ROOT . 'Traitement/Auth.php';

function coupure_seuil_impayes()
{
    return max(1, (int) lireParametre('NB_MOIS_IMPAYES_COUPURE', 3));
}

function coupure_frais_retablissement()
{
    return round((float) lireParametre('FRAIS_RETABLISSEMENT_DH', 1000), 2);
}

function coupure_utilisateur_session()
{
    return isset($_SESSION['id_utilisateur']) ? (int) $_SESSION['id_utilisateur'] : null;
}

/** Notification au client ; un échec ne doit jamais annuler la coupure. */
function coupure_notifier($info, $titre, $message)
{
    if (empty($info['id_utilisateur_client'])) {
        return;
    }
    try {
        bd_notification_creer((int) $info['id_utilisateur_client'], $titre, $message, 'ALERTE_COUPURE');
    } catch (Throwable $e) {
    }
}

/** Exécute $travail dans une transaction ; annule tout si une exception est levée. */
function coupure_transaction(callable $travail)
{
    $link = Connexion();
    if (!mysqli_begin_transaction($link)) {
        throw new RuntimeException('Impossible de démarrer la transaction : ' . mysqli_error($link));
    }
    try {
        $resultat = $travail();
        if (!mysqli_commit($link)) {
            throw new RuntimeException('Impossible de valider la transaction : ' . mysqli_error($link));
        }
        return $resultat;
    } catch (Throwable $e) {
        mysqli_rollback($link);
        throw $e;
    }
}

/**
 * Programme la coupure d'UN contrat. Le contrat passe à l'état AVERTISSEMENT et le client est alerté.
 * Lève DomainException (message affichable) si la coupure est impossible.
 * Ne pas appeler à l'intérieur d'une autre transaction.
 */
function coupure_programmer_contrat($idContrat, $motif, $datePrevue, $idUtilisateur = null, $commentaire = '', $idSanction = null)
{
    if (!array_key_exists($motif, bd_coupure_motifs())) {
        throw new DomainException('Motif de coupure invalide.');
    }
    return coupure_transaction(function () use ($idContrat, $motif, $datePrevue, $idUtilisateur, $commentaire, $idSanction) {
        $info = bd_coupure_contrat_infos($idContrat, true);
        if (!$info) {
            throw new DomainException('Contrat introuvable.');
        }
        if ($info['statut'] !== 'ACTIF') {
            throw new DomainException('Ce contrat n\'est pas actif.');
        }
        if ($info['statut_service'] === 'COUPE') {
            throw new DomainException('Le service de ce contrat est déjà coupé.');
        }
        if (bd_coupure_ouverte_pour_contrat($idContrat)) {
            throw new DomainException('Une coupure est déjà programmée ou en cours pour ce contrat.');
        }
        $frais = coupure_frais_retablissement();
        $idCoupure = bd_coupure_programmer($info['id_client'], $idContrat, $motif, $datePrevue, $frais, $idUtilisateur, $commentaire, $idSanction);
        bd_coupure_definir_statut_service($idContrat, 'AVERTISSEMENT');

        bd_journal_enregistrer(
            $idUtilisateur, 'PROGRAMMATION_COUPURE', 'coupures_services', $idCoupure,
            'Coupure du contrat ' . $info['numero_contrat'] . ' programmée pour le ' . $datePrevue
                . ' (' . bd_coupure_motifs()[$motif] . ')',
            'service : ' . $info['statut_service'], 'service : AVERTISSEMENT'
        );
        coupure_notifier(
            $info,
            'Alerte de coupure',
            'Le service du contrat ' . $info['numero_contrat'] . ' sera coupé à partir du ' . date('d/m/Y', strtotime($datePrevue))
                . ' (' . bd_coupure_motifs()[$motif] . '). Rétablissement après coupure : '
                . number_format($frais, 2, ',', ' ') . ' DH.'
        );
        return $idCoupure;
    });
}

/** PROGRAMMEE -> EFFECTUEE : le contrat (et lui seul) passe à COUPE. */
function coupure_faire_executer($idCoupure, $idUtilisateur)
{
    coupure_transaction(function () use ($idCoupure, $idUtilisateur) {
        $c = bd_coupure_verrouiller($idCoupure);
        if (!$c) {
            throw new DomainException('Coupure introuvable.');
        }
        if ($c['statut'] !== 'PROGRAMMEE') {
            throw new DomainException('Seule une coupure programmée peut être exécutée.');
        }
        if (empty($c['id_contrat'])) {
            throw new DomainException('Cette coupure n\'est liée à aucun contrat.');
        }
        if (bd_coupure_reclamations_bloquantes($c['id_contrat']) > 0) {
            throw new DomainException('Une réclamation en cours suspend la coupure de ce contrat.');
        }
        if ($c['motif_coupure'] === 'TROIS_MOIS_IMPAYES'
            && bd_coupure_mois_impayes_consecutifs($c['id_contrat']) < coupure_seuil_impayes()) {
            throw new DomainException('Le client n\'a plus ' . coupure_seuil_impayes() . ' mois impayés consécutifs : annulez cette coupure.');
        }
        $info = bd_coupure_contrat_infos($c['id_contrat'], true);
        if (!bd_coupure_marquer_effectuee($idCoupure, $idUtilisateur)) {
            throw new DomainException('La coupure a déjà été traitée.');
        }
        bd_coupure_definir_statut_service($c['id_contrat'], 'COUPE');

        bd_journal_enregistrer(
            $idUtilisateur, 'EXECUTION_COUPURE', 'coupures_services', (int) $idCoupure,
            'Service du contrat ' . $info['numero_contrat'] . ' coupé',
            'service : ' . $info['statut_service'], 'service : COUPE'
        );
        coupure_notifier(
            $info, 'Service coupé',
            'Le service du contrat ' . $info['numero_contrat'] . ' a été coupé. Frais de rétablissement : '
                . number_format((float) $c['frais_retablissement'], 2, ',', ' ') . ' DH.'
        );
    });
}

/** EFFECTUEE -> RETABLIE. Les frais encaissés doivent être égaux aux frais de la coupure (1 000 DH par défaut). */
function coupure_faire_retablir($idCoupure, $idUtilisateur, $montantFrais)
{
    coupure_transaction(function () use ($idCoupure, $idUtilisateur, $montantFrais) {
        $c = bd_coupure_verrouiller($idCoupure);
        if (!$c) {
            throw new DomainException('Coupure introuvable.');
        }
        if ($c['statut'] !== 'EFFECTUEE') {
            throw new DomainException('Seule une coupure effectuée peut être rétablie.');
        }
        $frais = round((float) $c['frais_retablissement'], 2);
        if ((int) round($montantFrais * 100) !== (int) round($frais * 100)) {
            throw new DomainException('Les frais de rétablissement sont de ' . number_format($frais, 2, ',', ' ') . ' DH : le montant encaissé doit être exactement celui-ci.');
        }
        $info = bd_coupure_contrat_infos($c['id_contrat'], true);
        $note = "\nRétablissement : frais de " . number_format($frais, 2, '.', '') . ' DH encaissés le ' . date('d/m/Y H:i');
        if (!bd_coupure_marquer_retablie($idCoupure, $idUtilisateur, $note)) {
            throw new DomainException('La coupure a déjà été traitée.');
        }
        bd_coupure_definir_statut_service($c['id_contrat'], 'ACTIF');

        bd_journal_enregistrer(
            $idUtilisateur, 'RETABLISSEMENT', 'coupures_services', (int) $idCoupure,
            'Service du contrat ' . $info['numero_contrat'] . ' rétabli (frais de ' . number_format($frais, 2, '.', '') . ' DH encaissés)',
            'service : COUPE', 'service : ACTIF'
        );
        coupure_notifier(
            $info, 'Service rétabli',
            'Le service du contrat ' . $info['numero_contrat'] . ' a été rétabli.'
        );
    });
}

/** PROGRAMMEE -> ANNULEE ; le contrat repasse à ACTIF s'il était en AVERTISSEMENT. */
function coupure_faire_annuler($idCoupure, $idUtilisateur, $raison = 'Annulée par le fournisseur')
{
    coupure_transaction(function () use ($idCoupure, $idUtilisateur, $raison) {
        $c = bd_coupure_verrouiller($idCoupure);
        if (!$c) {
            throw new DomainException('Coupure introuvable.');
        }
        if ($c['statut'] !== 'PROGRAMMEE') {
            throw new DomainException('Seule une coupure programmée peut être annulée.');
        }
        $info = $c['id_contrat'] ? bd_coupure_contrat_infos($c['id_contrat'], true) : null;
        if (!bd_coupure_marquer_annulee($idCoupure, "\n" . $raison)) {
            throw new DomainException('La coupure a déjà été traitée.');
        }
        if ($info && $info['statut_service'] === 'AVERTISSEMENT') {
            bd_coupure_definir_statut_service($c['id_contrat'], 'ACTIF');
        }
        bd_journal_enregistrer(
            $idUtilisateur, 'ANNULATION_COUPURE', 'coupures_services', (int) $idCoupure,
            'Coupure annulée' . ($info ? ' (contrat ' . $info['numero_contrat'] . ')' : '') . ' : ' . $raison
        );
        if ($info) {
            coupure_notifier($info, 'Coupure annulée', 'La coupure prévue pour le contrat ' . $info['numero_contrat'] . ' a été annulée.');
        }
    });
}

/**
 * Appelée après un paiement : si le contrat n'a plus assez de mois impayés consécutifs,
 * la coupure « trois mois impayés » encore PROGRAMMEE est annulée. Ne lève jamais d'exception
 * (le paiement est déjà enregistré). Retourne true si une coupure a été annulée.
 */
function coupure_reevaluer_contrat($idContrat)
{
    try {
        $programmee = bd_coupure_programmee_pour_contrat($idContrat, 'TROIS_MOIS_IMPAYES');
        if ($programmee && bd_coupure_mois_impayes_consecutifs($idContrat) < coupure_seuil_impayes()) {
            coupure_faire_annuler($programmee['id_coupure'], coupure_utilisateur_session(), 'Annulée automatiquement : le client a réglé ses factures en retard.');
            return true;
        }
    } catch (Throwable $e) {
    }
    return false;
}

// ---------------------------------------------------------------- actions (pages)

function Coupure_index($param = null)
{
    exiger_role('FOURNISSEUR');

    $statuts = bd_coupure_statuts();
    $filtre = is_string($_GET['statut'] ?? null) ? $_GET['statut'] : 'PROGRAMMEE';
    if ($filtre !== 'TOUTES' && !array_key_exists($filtre, $statuts)) {
        $filtre = 'PROGRAMMEE';
    }
    $coupures = bd_coupure_lister($filtre === 'TOUTES' ? null : $filtre);
    foreach ($coupures as &$c) {
        $c['reclamations_bloquantes'] = ($c['statut'] === 'PROGRAMMEE' && $c['id_contrat'])
            ? bd_coupure_reclamations_bloquantes($c['id_contrat']) : 0;
    }
    unset($c);

    $contrats = bd_coupure_contrats_programmables();
    $motifs = bd_coupure_motifs();
    $frais = coupure_frais_retablissement();

    $message = $_SESSION['message_coupure'] ?? null;
    unset($_SESSION['message_coupure']);
    $erreur = $_SESSION['erreur_coupure'] ?? null;
    unset($_SESSION['erreur_coupure']);
    $rapport = $_SESSION['rapport_rappel'] ?? null;
    unset($_SESSION['rapport_rappel']);
    include ROOT . 'IHM/fournisseur/coupures.php';
}

/** Vérifications communes des formulaires POST (rôle, méthode, jeton CSRF). */
function coupure_verifier_post()
{
    exiger_role('FOURNISSEUR');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rediriger('Coupure/index');
    }
    if (!csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        $_SESSION['erreur_coupure'] = 'Le formulaire a expiré. Veuillez réessayer.';
        rediriger('Coupure/index');
    }
}

/** Exécute une action métier et convertit les erreurs en message pour la page. */
function coupure_repondre(callable $action, $messageSucces, $filtreRetour = 'TOUTES')
{
    try {
        $action();
        $_SESSION['message_coupure'] = $messageSucces;
    } catch (DomainException $e) {
        $_SESSION['erreur_coupure'] = $e->getMessage();
    } catch (RuntimeException $e) {
        $_SESSION['erreur_coupure'] = 'Opération impossible. Vérifiez que database/migration_coupures.sql a été exécuté, puis réessayez.';
    }
    rediriger('Coupure/index' . ($filtreRetour ? '?statut=' . $filtreRetour : ''));
}

function Coupure_programmer($param = null)
{
    coupure_verifier_post();
    $idContrat = filter_var($_POST['id_contrat'] ?? null, FILTER_VALIDATE_INT);
    $date = is_string($_POST['date_programmee'] ?? null) ? $_POST['date_programmee'] : '';
    $commentaire = is_string($_POST['commentaire'] ?? null) ? trim($_POST['commentaire']) : '';
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$idContrat || !$d || $d->format('Y-m-d') !== $date || $date < date('Y-m-d') || mb_strlen($commentaire) > 500) {
        $_SESSION['erreur_coupure'] = 'Choisissez un contrat et une date de coupure (aujourd\'hui ou plus tard).';
        rediriger('Coupure/index');
    }
    coupure_repondre(function () use ($idContrat, $date, $commentaire) {
        coupure_programmer_contrat($idContrat, 'DEMANDE_FOURNISSEUR', $date, coupure_utilisateur_session(), $commentaire);
    }, 'Coupure programmée : le contrat est en avertissement et le client a été alerté.', 'PROGRAMMEE');
}

function Coupure_executer($param = null)
{
    coupure_verifier_post();
    $id = filter_var($_POST['id_coupure'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        rediriger('Coupure/index');
    }
    coupure_repondre(function () use ($id) {
        coupure_faire_executer($id, coupure_utilisateur_session());
    }, 'Coupure effectuée : seul ce contrat est coupé.', 'EFFECTUEE');
}

function Coupure_retablir($param = null)
{
    coupure_verifier_post();
    $id = filter_var($_POST['id_coupure'] ?? null, FILTER_VALIDATE_INT);
    $brut = is_string($_POST['frais'] ?? null) ? str_replace([' ', ','], ['', '.'], trim($_POST['frais'])) : '';
    $frais = filter_var($brut, FILTER_VALIDATE_FLOAT);
    if (!$id || $frais === false || $frais <= 0) {
        $_SESSION['erreur_coupure'] = 'Saisissez le montant des frais de rétablissement encaissés.';
        rediriger('Coupure/index?statut=EFFECTUEE');
    }
    coupure_repondre(function () use ($id, $frais) {
        coupure_faire_retablir($id, coupure_utilisateur_session(), round($frais, 2));
    }, 'Service rétabli : les frais de rétablissement sont enregistrés.', 'RETABLIE');
}

function Coupure_annuler($param = null)
{
    coupure_verifier_post();
    $id = filter_var($_POST['id_coupure'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        rediriger('Coupure/index');
    }
    coupure_repondre(function () use ($id) {
        coupure_faire_annuler($id, coupure_utilisateur_session());
    }, 'Coupure annulée.', 'ANNULEE');
}