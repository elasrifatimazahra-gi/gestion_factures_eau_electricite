<?php
require_once "Connexion.php";
$link = Connexion();

function lister()
{
    global $link;
    $sql = "select * from clients";
    return mysqli_query($link,$sql);
}

function rechercher($terme)
{
   global $link;
   $terme =  mysqli_real_escape_string($link,$terme);
   $sql = "select * from clients where nom LIKE '%$terme%' or prenom LIKE '%$terme%' or cin LIKE '%$terme%'";
   return mysqli_query($link,$sql);
}

function trouverParId($id){
    global $link;
    $sql = "select * from clients where id_client = $id";
    return mysqli_query($link,$sql);
}

function genererNumClient(){
    global $link;
    $annee_actuelle = date("Y");
    $prefix = "CL-" . $annee_actuelle . "-";
    $sql = " select numero_client from clients where numero_client LIKE '$prefix%' order by numero_client DESC limit 1";
    $res = mysqli_query($link,$sql);
    if ($row = mysqli_fetch_assoc($res)){
        $numero_client = $row['numero_client'];
        $numero_client++; // php prend les derniers chiffres et les incremente sans toucher les lettres
    } else {
        $numero_client = $prefix . "001";
    }
    return $numero_client;

    }


function ajouter($id_utilisateur, $numero_client, $cin, $nom, $prenom, $adresse, $telephone, $numero_compteur_eau, $numero_compteur_elec, $index_initial_eau, $index_initial_elec) {
    global $link;
    
    // Securite
    $numero_client        = mysqli_real_escape_string($link, $numero_client);
    $cin                  = mysqli_real_escape_string($link, $cin);
    $nom                  = mysqli_real_escape_string($link, $nom);
    $prenom               = mysqli_real_escape_string($link, $prenom);
    $adresse              = mysqli_real_escape_string($link, $adresse);
    $telephone            = mysqli_real_escape_string($link, $telephone);
    $numero_compteur_eau  = mysqli_real_escape_string($link, $numero_compteur_eau);
    $numero_compteur_elec = mysqli_real_escape_string($link, $numero_compteur_elec);
    $index_initial_eau    = mysqli_real_escape_string($link, $index_initial_eau);
    $index_initial_elec   = mysqli_real_escape_string($link, $index_initial_elec);
   
    $sql = "INSERT INTO clients (id_utilisateur, numero_client, cin, nom, prenom, adresse, telephone, numero_compteur_eau, numero_compteur_elec, index_initial_eau, index_initial_elec)
            VALUES ('$id_utilisateur', '$numero_client', '$cin', '$nom', '$prenom', '$adresse', '$telephone', '$numero_compteur_eau', '$numero_compteur_elec', '$index_initial_eau', '$index_initial_elec')";
            
    $res = mysqli_query($link, $sql);
    
    if ($res) {
        return true; 
    } else {
        return false; 
    }
}


function modifier($id_client, $cin, $nom, $prenom, $adresse, $telephone, $numero_compteur_eau, $numero_compteur_elec,$index_initial_eau, $index_initial_elec) {
    global $link;
    //Securite
    $id_client            = mysqli_real_escape_string($link, $id_client);
    $cin                  = mysqli_real_escape_string($link, $cin);
    $nom                  = mysqli_real_escape_string($link, $nom);
    $prenom               = mysqli_real_escape_string($link, $prenom);
    $adresse              = mysqli_real_escape_string($link, $adresse);
    $telephone            = mysqli_real_escape_string($link, $telephone);
    $numero_compteur_eau  = mysqli_real_escape_string($link, $numero_compteur_eau);
    $numero_compteur_elec = mysqli_real_escape_string($link, $numero_compteur_elec);
    $index_initial_eau    = mysqli_real_escape_string($link,$index_initial_eau);
    $index_initial_elec   = mysqli_real_escape_string($link,$index_initial_elec);

    $sql = "UPDATE clients SET cin='$cin', nom='$nom', prenom='$prenom', adresse='$adresse', telephone='$telephone', numero_compteur_eau='$numero_compteur_eau', numero_compteur_elec='$numero_compteur_elec', index_initial_eau='$index_initial_eau', index_initial_elec='$index_initial_elec' WHERE id_client=$id_client";

    return mysqli_query($link, $sql);
}

function changerMotDePasse($id_user, $nouveauPswd) {
    global $link;
    // Securite
    $id_user = mysqli_real_escape_string($link, $id_user);
    $nouveauPswd = mysqli_real_escape_string($link, $nouveauPswd);

    $sql = "UPDATE utilisateurs SET mot_de_passe='$nouveauPswd' WHERE id_utilisateur=$id_user";
    
    return mysqli_query($link, $sql);
}

function debloquerClient($cin){
    global $link;
    $cin = mysqli_real_escape_string($link,$cin);
    $sql = "Update utilisateurs SET statut_compte ='ACTIF' where id_utilisateur = (SELECT id_utilisateur from clients where cin='$cin')";
    return mysqli_query($link,$sql);
}

function cinExiste($cin){
    global $link;
    $cin = mysqli_real_escape_string($link,$cin);
    $sql = "select id_client from clients where cin='$cin'";
    $res = mysqli_query($link,$sql);
    //mysqli_num_rows compte le nombre de lignes trouvees
    if(mysqli_num_rows($res) > 0){
        return true;
    } else {
        return false;
    }
}