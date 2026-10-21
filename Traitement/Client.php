<?php require_once 'BD/Client.php';
require_once 'BD/Utilisateur.php';
require_once ROOT . 'Traitement/Auth.php';
    function Client_index()
    {
        exiger_role('FOURNISSEUR');

        if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
            $terme = trim($_GET['search']);
            $res_clients = rechercher($terme);
        } else {
           $res_clients = lister();
        }
        require_once 'IHM/fournisseur/clients.php';
    }
   
function Client_ajouter()
{
    exiger_role('FOURNISSEUR');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
       
        $nom = $_POST['nom'];
        $prenom = $_POST['prenom'];
        $cin = $_POST['cin'];
        $adresse = $_POST['adresse'];
        $telephone = $_POST['telephone'];
        $numero_compteur_eau = $_POST['numero_compteur_eau'];
        $numero_compteur_elec = $_POST['numero_compteur_elec'];
        $index_initial_eau = $_POST['index_initial_eau'];
        $index_initial_elec = $_POST['index_initial_elec'];
        
        // 1. On verifie D'ABORD si le CIN existe deja
        if (cinExiste($cin)) {
            $erreur = "Ce CIN existe déjà .";
        } else {  
            // 2. S'il n'existe pas, on genere le numero client
            $numero_client = genererNumClient();
            
            // 3. On cree le compte utilisateur
            $id_utilisateur = bd_utilisateur_creer($numero_client, $cin, 'CLIENT');
            
            if ($id_utilisateur) {
                // 4. On cree le compte client
                $resultat = ajouter($id_utilisateur, $numero_client, $cin, $nom, $prenom, $adresse, $telephone, $numero_compteur_eau, $numero_compteur_elec, $index_initial_eau, $index_initial_elec);
        
                if ($resultat) {
                    $_SESSION['msg_succes'] = "Client ajouté avec succès<br>
                    Identifiant: <strong>$numero_client</strong><br>
                    Mot de passe (PAR DÉFAUT): <strong>$cin</strong>";
                    
                    // Redirection vers index 
                    header('Location: ' . BASE_URL . 'Client/index');
                    exit; // Toujours mettre un exit apres une redirection
                } else {
                    $erreur = "Erreur lors de l'enregistrement de la fiche client.";
                }
            } else {
                $erreur = "Erreur lors de la création des identifiants de connexion.";
            }
        }
    }
    
    // Cas d'un GET ou s'il y a une erreur : afficher le formulaire d'ajout
    $action_formulaire = 'Client/ajouter';
    $bouton_formulaire = 'Ajouter le client';
    $client_actuel = $_POST ?? [];
    require_once ROOT . 'IHM/fournisseur/client_form.php';
}
    
function Client_modifier($id_client = null) {
    exiger_role('FOURNISSEUR');

    if (!$id_client) {
        header('Location: ' . BASE_URL . 'Client/index');
        exit;
    }
        //Sauvegarde des modifications du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
        if (modifier(
            $id_client, 
            $_POST['cin'], 
            $_POST['nom'], 
            $_POST['prenom'], 
            $_POST['adresse'], 
            $_POST['telephone'], 
            $_POST['numero_compteur_eau'], 
            $_POST['numero_compteur_elec'], 
            $_POST['index_initial_eau'], 
            $_POST['index_initial_elec']
        )) {
            $_SESSION['msg_succes'] = "Les informations du client ont été mises à jour.";
            header('Location: ' . BASE_URL . 'Client/index');
            exit;
        } else {
            $erreur = "Erreur lors de la modification.";
        }
    }
    //Cas bouton modifier existant dans la liste des clients
    // Recuperer les infos actuelles pour pre-remplir le formulaire
    $res = trouverParId($id_client);
    if ($client_actuel = mysqli_fetch_assoc($res)) {
        $action_formulaire = 'Client/modifier/' . $id_client;
        $bouton_formulaire = 'Modifier le client';
        require_once ROOT . 'IHM/fournisseur/client_form.php';
    } else {
        header('Location: ' . BASE_URL . 'Client/index');
        exit;
    }
}

function Client_debloquer($cin = NULL)
{
    exiger_role('FOURNISSEUR');

    if ($cin) {
        $statut = statutCompteClient($cin);

        if (!$statut) {
            $_SESSION['msg_erreur'] = "Client introuvable.";
        } elseif ($statut['statut_compte'] === 'ACTIF' && !$statut['est_bloque']) {
            $_SESSION['msg_succes'] = "Le compte du client (CIN: $cin) est déjà actif.";
        } elseif (debloquerClient($cin)) {
            $_SESSION['msg_succes'] = "Le compte du client (CIN: $cin) a été débloqué avec succès.";
        } else {
            $_SESSION['msg_erreur'] = "Impossible de débloquer le client.";
        }
    }

    header('Location: ' . BASE_URL . 'Client/index');
    exit;
}

function Client_changerMdp($id_client = null) {
    if ($id_client !== null || (isset($_SESSION['role']) && $_SESSION['role'] === 'FOURNISSEUR')) {
        exiger_role('FOURNISSEUR');
    } else {
        exiger_role('CLIENT');
    }

    // 1. Recuperation des infos de la personne actuellement connectee
    $id_connecte = $_SESSION['id_utilisateur'];
    $role_connecte = $_SESSION['role'] ; 

    // Pour qui on va changer le mdp
    $target_id_utilisateur = null;
    $titre_page = "Changer le mot de passe";
    $redirection_apres_succes = BASE_URL . 'Client/index'; 

   
    
    if ($id_client !== null && $role_connecte === 'FOURNISSEUR') {
        // CAS 3 : Le fournisseur change le mot de passe d'un client oublie
        $res = trouverParId($id_client);
        if ($client = mysqli_fetch_assoc($res)) {
            $target_id_utilisateur = $client['id_utilisateur'];
            $titre_page = "Réinitialiser le mot de passe du client : " . $client['nom'];
            $redirection_apres_succes = BASE_URL . 'Client/index';
        } else {
            $_SESSION['msg_erreur'] = "Client introuvable.";
            header('Location: ' . BASE_URL . 'Client/index');
            exit;
        }
    } else {
        // CAS 1 & 2 : Le fournisseur OU le client change son PROPRE mot de passe
        // L'URL est appelee sans id
        $target_id_utilisateur = $id_connecte;
        $titre_page = "Modifier mon mot de passe";
        // Si c'est son propre compte, on le renvoie vers l'accueil apres modification
        $redirection_apres_succes = BASE_URL . 'Accueil'; 
    }

    // TRAITEMENT DU FORMULAIRE client_mdp
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nouveau_mdp = $_POST['nouveau_mdp'];
        
        if (!empty($nouveau_mdp)) {
            // Hachage du mot de passe
            $mdp_hash = password_hash($nouveau_mdp, PASSWORD_DEFAULT);
            
            // Mise a jour en BD
            if (changerMotDePasse($target_id_utilisateur, $mdp_hash)) {
                $_SESSION['msg_succes'] = "Le mot de passe a été modifié avec succès.";
                header('Location: ' . $redirection_apres_succes);
                exit;
            } else {
                $erreur = "Erreur lors de la mise à jour dans la base de données.";
            }
        } else {
            $erreur = "Veuillez saisir un nouveau mot de passe.";
        }
    }

    // Affichage de la vue
    require_once ROOT . 'IHM/fournisseur/client_mdp_form.php';
}