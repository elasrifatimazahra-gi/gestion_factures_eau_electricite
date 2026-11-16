<?php
// Contrôleur des paiements (RG30, RG31, RG35) - Membre 4
// URLs : Paiement/index (liste + formulaires), Paiement/enregistrer (POST)

require_once ROOT . 'BD/Paiement.php';
require_once ROOT . 'BD/Facture.php';
require_once ROOT . 'BD/Journal.php';
require_once ROOT . 'BD/Notification.php';
require_once ROOT . 'Traitement/Auth.php';

function Paiement_index($param = null)
{
    exiger_role('FOURNISSEUR');

    $recherche = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $factures = bd_paiement_factures_a_payer($recherche);
    $historique = bd_paiement_lister(30);
    $modes = bd_paiement_modes();

    $message = $_SESSION['message_paiement'] ?? null;
    unset($_SESSION['message_paiement']);
    $erreur = $_SESSION['erreur_paiement'] ?? null;
    unset($_SESSION['erreur_paiement']);

    include ROOT . 'IHM/fournisseur/paiements.php';
}

function Paiement_enregistrer($param = null)
{
    exiger_role('FOURNISSEUR');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rediriger('Paiement/index');
    }
    if (!csrf_valider_m1($_POST['csrf_token'] ?? null)) {
        $_SESSION['erreur_paiement'] = 'Le formulaire a expiré. Veuillez réessayer.';
        rediriger('Paiement/index');
    }

    // --- 1. Validation des champs saisis ---
    $idFacture = filter_var($_POST['id_facture'] ?? null, FILTER_VALIDATE_INT);
    $montantBrut = is_string($_POST['montant'] ?? null) ? str_replace([' ', ','], ['', '.'], trim($_POST['montant'])) : '';
    $montant = filter_var($montantBrut, FILTER_VALIDATE_FLOAT);
    $mode = is_string($_POST['mode_paiement'] ?? null) ? $_POST['mode_paiement'] : '';
    $remarques = is_string($_POST['remarques'] ?? null) ? trim($_POST['remarques']) : '';

    if (!$idFacture || $idFacture < 1 || $montant === false || $montant <= 0
        || !array_key_exists($mode, bd_paiement_modes()) || mb_strlen($remarques) > 500) {
        $_SESSION['erreur_paiement'] = 'Veuillez saisir une facture, un montant positif et un mode de paiement valides.';
        rediriger('Paiement/index');
    }
    $montant = round($montant, 2);
    $idUtilisateur = (int) $_SESSION['id_utilisateur'];

    // --- 2. Enregistrement dans une transaction (facture verrouillée pendant le calcul) ---
    $link = Connexion();
    if (!mysqli_begin_transaction($link)) {
        throw new RuntimeException("Impossible de démarrer l'enregistrement du paiement : " . mysqli_error($link));
    }
    try {
        $f = bd_paiement_facture_verrouiller($idFacture);
        if (!$f) {
            throw new DomainException('Facture introuvable.');
        }
        if (!in_array($f['statut'], ['IMPAYEE', 'EN_RETARD'], true) || (float) $f['reste_a_payer'] <= 0) {
            throw new DomainException('Cette facture est déjà réglée ou annulée.');
        }

        // Paiement chronologique : une facture plus ancienne du même contrat doit être soldée d'abord
        $nbAnterieures = bd_facture_nb_impayees_anterieures(
            $f['id_client'], $f['periode_annee'], $f['periode_mois'], $f['id_contrat'] ?: null
        );
        if ($nbAnterieures > 0) {
            throw new DomainException('Une facture plus ancienne de ce contrat est encore impayée : réglez-la d\'abord.');
        }

        // Comparaison en centimes pour éviter les erreurs d'arrondi des décimaux
        $resteAvant = (float) $f['reste_a_payer'];
        if ((int) round($montant * 100) > (int) round($resteAvant * 100)) {
            throw new DomainException('Le montant dépasse le reste à payer (' . number_format($resteAvant, 2, ',', ' ') . ' DH).');
        }

        $nouveauPaye = round((float) $f['montant_paye'] + $montant, 2);
        $nouveauReste = max(0.0, round($resteAvant - $montant, 2));
        $soldee = $nouveauReste <= 0;

        $reference = bd_paiement_reference();
        $idPaiement = bd_paiement_inserer($reference, $f['id_facture'], $f['id_client'], $montant, $mode, $idUtilisateur, $remarques);
        bd_facture_enregistrer_paiement($f['id_facture'], $nouveauPaye, $nouveauReste, $soldee);

        bd_journal_enregistrer(
            $idUtilisateur,
            'ENREGISTREMENT_PAIEMENT',
            'paiements',
            $idPaiement,
            'Paiement ' . $reference . ' de ' . number_format($montant, 2, '.', '') . ' DH sur la facture '
                . $f['numero_facture'] . ($soldee ? ' (facture soldée)' : ''),
            'reste à payer : ' . number_format($resteAvant, 2, '.', ''),
            'reste à payer : ' . number_format($nouveauReste, 2, '.', '')
        );

        if (!empty($f['id_utilisateur_client'])) {
            bd_notification_creer(
                (int) $f['id_utilisateur_client'],
                'Paiement enregistré',
                'Votre paiement de ' . number_format($montant, 2, ',', ' ') . ' DH pour la facture '
                    . $f['numero_facture'] . ' a bien été enregistré.'
                    . ($soldee ? ' La facture est soldée.' : ' Reste à payer : ' . number_format($nouveauReste, 2, ',', ' ') . ' DH.'),
                'INFO'
            );
        }

        if (!mysqli_commit($link)) {
            throw new RuntimeException("Impossible de valider le paiement : " . mysqli_error($link));
        }
    } catch (DomainException $e) {
        mysqli_rollback($link);
        $_SESSION['erreur_paiement'] = $e->getMessage();
        rediriger('Paiement/index');
    } catch (RuntimeException $e) {
        mysqli_rollback($link);
        $_SESSION['erreur_paiement'] = "Impossible d'enregistrer le paiement. Veuillez réessayer.";
        rediriger('Paiement/index');
    }

    // Étape 3 (Coupure) : après un paiement, on réévalue le contrat (annule une coupure programmée
    // si le client n'a plus 3 mois impayés). Ignoré tant que Traitement/Coupure.php n'existe pas.
    if (!empty($f['id_contrat']) && file_exists(ROOT . 'Traitement/Coupure.php')) {
        require_once ROOT . 'Traitement/Coupure.php';
        if (function_exists('coupure_reevaluer_contrat')) {
            coupure_reevaluer_contrat((int) $f['id_contrat']);
        }
    }

    $_SESSION['message_paiement'] = 'Paiement ' . $reference . ' enregistré'
        . ($soldee ? ' : la facture ' . $f['numero_facture'] . ' est soldée.' : '. Reste à payer : ' . number_format($nouveauReste, 2, ',', ' ') . ' DH.');
    rediriger('Paiement/index');
}