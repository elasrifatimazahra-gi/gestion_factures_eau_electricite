<?php
// Requêtes du tableau de bord fournisseur (Membre 1)
require_once __DIR__ . '/Connexion.php';

/** Compte les lignes renvoyées par un "SELECT COUNT(*) ..." */
function bd_tableau_compter($sql)
{
    $link = Connexion();
    $res = mysqli_query($link, $sql);
    $ligne = $res ? mysqli_fetch_row($res) : null;
    return $ligne ? (int) $ligne[0] : 0;
}

/** Les chiffres affichés dans « Points d'attention ». */
function bd_tableau_compteurs()
{
    return [
        'anomalies' => bd_tableau_compter("SELECT COUNT(*) FROM anomalies WHERE statut = 'EN_ATTENTE'"),
        'bloques'   => bd_tableau_compter("SELECT COUNT(*) FROM utilisateurs
                                            WHERE role = 'CLIENT'
                                              AND (statut_compte IN ('BLOQUE_TENTATIVES', 'BLOQUE_GESTIONNAIRE')
                                                   OR bloque_jusqu_a > NOW())"),
        'impayees'  => bd_tableau_compter("SELECT COUNT(*) FROM factures WHERE statut = 'IMPAYEE'"),
        'en_retard' => bd_tableau_compter("SELECT COUNT(*) FROM factures WHERE statut = 'EN_RETARD'"),
        'clients'   => bd_tableau_compter("SELECT COUNT(*) FROM clients"),
    ];
}

/** Les dernières factures émises, avec le nom du client. */
function bd_tableau_dernieres_factures($limite = 5)
{
    $link = Connexion();
    $limite = (int) $limite;
    $res = mysqli_query($link, "SELECT f.numero_facture, f.montant_ttc, f.date_echeance, f.statut,
                                       c.nom, c.prenom
                                FROM factures f
                                JOIN clients c ON c.id_client = f.id_client
                                ORDER BY f.date_emission DESC, f.id_facture DESC
                                LIMIT $limite");
    return $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
}

/** Email du fournisseur connecté (affiché en haut à droite). */
function bd_tableau_infos_utilisateur($idUtilisateur)
{
    $link = Connexion();
    $idUtilisateur = (int) $idUtilisateur;
    $res = mysqli_query($link, "SELECT identifiant, email FROM utilisateurs WHERE id_utilisateur = $idUtilisateur");
    return $res ? mysqli_fetch_assoc($res) : null;
}