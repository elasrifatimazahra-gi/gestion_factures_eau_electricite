CREATE DATABASE IF NOT EXISTS `gestion_factures_tp2`
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `gestion_factures_tp2`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `import_lignes_rejetees`;
DROP TABLE IF EXISTS `journal_actions`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `coupures_services`;
DROP TABLE IF EXISTS `sanctions`;
DROP TABLE IF EXISTS `controle_annuel`;
DROP TABLE IF EXISTS `reclamations`;
DROP TABLE IF EXISTS `paiements`;
DROP TABLE IF EXISTS `factures`;
DROP TABLE IF EXISTS `anomalies`;
DROP TABLE IF EXISTS `consommations`;
DROP TABLE IF EXISTS `agents`;
DROP TABLE IF EXISTS `clients`;
DROP TABLE IF EXISTS `utilisateurs`;
DROP TABLE IF EXISTS `tarifs_tranches`;
DROP TABLE IF EXISTS `parametres`;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- 1. TABLE : parametres (RG50)
-- =============================================================================
CREATE TABLE `parametres` (
    `id_parametre` INT AUTO_INCREMENT PRIMARY KEY,
    `cle` VARCHAR(50) NOT NULL UNIQUE,
    `libelle` VARCHAR(150) NOT NULL,
    `valeur` VARCHAR(100) NOT NULL,
    `type_donnee` ENUM('DECIMAL', 'INTEGER', 'STRING', 'BOOLEAN') NOT NULL DEFAULT 'STRING',
    `description` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2. TABLE : tarifs_tranches (RG26, RG27, RG50)
-- =============================================================================
CREATE TABLE `tarifs_tranches` (
    `id_tarif` INT AUTO_INCREMENT PRIMARY KEY,
    `type_fluide` ENUM('EAU', 'ELECTRICITE') NOT NULL,
    `tranche_num` INT NOT NULL,
    `borne_min` INT NOT NULL,
    `borne_max` INT NULL,
    `prix_unitaire_dh` DECIMAL(10,4) NOT NULL,
    `description` VARCHAR(100) NOT NULL,
    `actif` BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE KEY `unique_tranche_fluide` (`type_fluide`, `tranche_num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 3. TABLE : utilisateurs (RG1 à RG8)
-- =============================================================================
CREATE TABLE `utilisateurs` (
    `id_utilisateur` INT AUTO_INCREMENT PRIMARY KEY,
    `identifiant` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NULL UNIQUE,
    `mot_de_passe` VARCHAR(255) NOT NULL,
    `role` ENUM('CLIENT', 'FOURNISSEUR') NOT NULL DEFAULT 'CLIENT',
    `statut_compte` ENUM('ACTIF', 'BLOQUE_TENTATIVES', 'BLOQUE_GESTIONNAIRE', 'COUPE') NOT NULL DEFAULT 'ACTIF',
    `tentatives_echouees` INT NOT NULL DEFAULT 0,
    `bloque_jusqu_a` DATETIME NULL,
    `derniere_connexion` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 4. TABLE : clients (RG1, RG2, RG9, RG10, RG19, RG34, RG44)
-- =============================================================================
CREATE TABLE `clients` (
    `id_client` INT AUTO_INCREMENT PRIMARY KEY,
    `id_utilisateur` INT NOT NULL UNIQUE,
    `numero_client` VARCHAR(30) NOT NULL UNIQUE,
    `cin` VARCHAR(20) NOT NULL UNIQUE,
    `nom` VARCHAR(100) NOT NULL,
    `prenom` VARCHAR(100) NOT NULL,
    `adresse` TEXT NOT NULL,
    `telephone` VARCHAR(25) NOT NULL,
    `numero_compteur_eau` VARCHAR(50) NOT NULL UNIQUE,
    `numero_compteur_elec` VARCHAR(50) NOT NULL UNIQUE,
    `index_initial_eau` INT NOT NULL DEFAULT 0,
    `index_initial_elec` INT NOT NULL DEFAULT 0,
    `solde_credit` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `statut_service` ENUM('ACTIF', 'AVERTISSEMENT', 'COUPE') NOT NULL DEFAULT 'ACTIF',
    `nb_mois_impayes_consecutifs` INT NOT NULL DEFAULT 0,
    `nb_mois_non_declares_consecutifs` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs`(`id_utilisateur`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 5. TABLE : agents (RG39, RG40)
-- =============================================================================
CREATE TABLE `agents` (
    `id_agent` INT AUTO_INCREMENT PRIMARY KEY,
    `matricule` VARCHAR(30) NOT NULL UNIQUE,
    `nom` VARCHAR(100) NOT NULL,
    `prenom` VARCHAR(100) NOT NULL,
    `telephone` VARCHAR(25) NULL,
    `email` VARCHAR(100) NULL,
    `zone_affectation` VARCHAR(100) NULL,
    `actif` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 6. TABLE : consommations (RG11 à RG15, RG17, RG18, RG21, RG23)
-- =============================================================================
CREATE TABLE `consommations` (
    `id_conso` INT AUTO_INCREMENT PRIMARY KEY,
    `id_client` INT NOT NULL,
    `annee` INT NOT NULL,
    `mois` TINYINT NOT NULL,
    `index_eau_precedent` INT NOT NULL DEFAULT 0,
    `index_eau_nouveau` INT NOT NULL DEFAULT 0,
    `conso_eau` INT NOT NULL DEFAULT 0,
    `photo_compteur_eau` VARCHAR(255) NULL,
    `index_elec_precedent` INT NOT NULL DEFAULT 0,
    `index_elec_nouveau` INT NOT NULL DEFAULT 0,
    `conso_elec` INT NOT NULL DEFAULT 0,
    `photo_compteur_elec` VARCHAR(255) NULL,
    `date_saisie` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `type_saisie` ENUM('REELLE', 'ESTIMEE', 'CORRIGEE') NOT NULL DEFAULT 'REELLE',
    `statut` ENUM('VALIDE', 'ANOMALIE', 'EN_ATTENTE_CORRECTION', 'REGULARISEE') NOT NULL DEFAULT 'VALIDE',
    `commentaire` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_client_mois_annee` (`id_client`, `annee`, `mois`),
    CONSTRAINT `chk_mois_conso` CHECK (`mois` BETWEEN 1 AND 12),
    FOREIGN KEY (`id_client`) REFERENCES `clients`(`id_client`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 7. TABLE : anomalies (RG21, RG22, RG23)
-- =============================================================================
CREATE TABLE `anomalies` (
    `id_anomalie` INT AUTO_INCREMENT PRIMARY KEY,
    `id_conso` INT NOT NULL,
    `id_client` INT NOT NULL,
    `type_anomalie` ENUM('INDEX_INFERIEUR_EAU', 'INDEX_INFERIEUR_ELEC', 'SEUIL_DEPASSE_EAU', 'SEUIL_DEPASSE_ELEC', 'PHOTO_ILLISIBLE', 'AUTRE') NOT NULL,
    `description` TEXT NOT NULL,
    `valeur_saisie` INT NULL,
    `valeur_attendue_ou_seuil` INT NULL,
    `statut` ENUM('EN_ATTENTE', 'CORRIGEE', 'VALIDEE_FORCEE', 'REJETEE') NOT NULL DEFAULT 'EN_ATTENTE',
    `index_eau_rectifie` INT NULL,
    `conso_eau_rectifiee` INT NULL,
    `index_elec_rectifie` INT NULL,
    `conso_elec_rectifiee` INT NULL,
    `date_detection` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_traitement` DATETIME NULL,
    `traite_par` INT NULL,
    `commentaire_resolution` TEXT NULL,
    FOREIGN KEY (`id_conso`) REFERENCES `consommations`(`id_conso`) ON DELETE CASCADE,
    FOREIGN KEY (`id_client`) REFERENCES `clients`(`id_client`) ON DELETE CASCADE,
    FOREIGN KEY (`traite_par`) REFERENCES `utilisateurs`(`id_utilisateur`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 8. TABLE : factures (RG24 à RG29, RG32, RG33)
-- =============================================================================
CREATE TABLE `factures` (
    `id_facture` INT AUTO_INCREMENT PRIMARY KEY,
    `numero_facture` VARCHAR(50) NOT NULL UNIQUE,
    `id_client` INT NOT NULL,
    `id_conso` INT NULL,
    `periode_mois` TINYINT NOT NULL,
    `periode_annee` INT NOT NULL,
    `date_emission` DATE NOT NULL,
    `date_echeance` DATE NOT NULL,
    `type_facture` ENUM('MENSUELLE', 'ESTIMEE', 'REGULARISATION', 'EXCEPTIONNELLE_FRAUDE') NOT NULL DEFAULT 'MENSUELLE',
    `index_eau_debut` INT NOT NULL DEFAULT 0,
    `index_eau_fin` INT NOT NULL DEFAULT 0,
    `conso_eau` INT NOT NULL DEFAULT 0,
    `montant_ht_eau` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `index_elec_debut` INT NOT NULL DEFAULT 0,
    `index_elec_fin` INT NOT NULL DEFAULT 0,
    `conso_elec` INT NOT NULL DEFAULT 0,
    `montant_ht_elec` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `montant_ht` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `taux_tva` DECIMAL(5,2) NOT NULL DEFAULT 18.00,
    `montant_tva` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `montant_ttc` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `credit_deduit` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `penalite_retard` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `penalite_appliquee` BOOLEAN NOT NULL DEFAULT FALSE,
    `montant_total_a_payer` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `montant_paye` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `reste_a_payer` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `statut` ENUM('IMPAYEE', 'EN_RETARD', 'PAYEE', 'ANNULEE') NOT NULL DEFAULT 'IMPAYEE',
    `date_paiement` DATETIME NULL,
    `chemin_pdf` VARCHAR(255) NULL,
    `photo_eau_facture` VARCHAR(255) NULL,
    `photo_elec_facture` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_facture_client` (`id_client`),
    KEY `idx_facture_statut` (`statut`),
    KEY `idx_facture_echeance` (`date_echeance`),
    CONSTRAINT `chk_periode_mois` CHECK (`periode_mois` BETWEEN 1 AND 12),
    FOREIGN KEY (`id_client`) REFERENCES `clients`(`id_client`) ON DELETE CASCADE,
    FOREIGN KEY (`id_conso`) REFERENCES `consommations`(`id_conso`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 9. TABLE : paiements (RG30, RG31, RG35)
-- =============================================================================
CREATE TABLE `paiements` (
    `id_paiement` INT AUTO_INCREMENT PRIMARY KEY,
    `reference_paiement` VARCHAR(50) NOT NULL UNIQUE,
    `id_facture` INT NOT NULL,
    `id_client` INT NOT NULL,
    `montant` DECIMAL(10,2) NOT NULL,
    `date_paiement` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `mode_paiement` ENUM('ESPECES', 'CARTE_BANCAIRE', 'VIREMENT', 'CHEQUE') NOT NULL DEFAULT 'ESPECES',
    `enregistre_par` INT NULL,
    `remarques` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_montant_paiement` CHECK (`montant` > 0),
    FOREIGN KEY (`id_facture`) REFERENCES `factures`(`id_facture`) ON DELETE CASCADE,
    FOREIGN KEY (`id_client`) REFERENCES `clients`(`id_client`) ON DELETE CASCADE,
    FOREIGN KEY (`enregistre_par`) REFERENCES `utilisateurs`(`id_utilisateur`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 10. TABLE : reclamations (RG36, RG37, RG38)
-- =============================================================================
CREATE TABLE `reclamations` (
    `id_reclamation` INT AUTO_INCREMENT PRIMARY KEY,
    `reference_reclamation` VARCHAR(50) NOT NULL UNIQUE,
    `id_client` INT NOT NULL,
    `id_facture` INT NULL,
    `type_reclamation` ENUM('FUITE_EXTERNE', 'FUITE_INTERNE', 'FACTURE', 'PROBLEME_COMPTEUR', 'COUPURE_INEXPLIQUEE', 'AUTRE') NOT NULL,
    `autre_motif` VARCHAR(255) NULL,
    `description` TEXT NOT NULL,
    `piece_jointe` VARCHAR(255) NULL,
    `statut` ENUM('OUVERTE', 'EN_COURS', 'TRAITEE', 'REJETEE') NOT NULL DEFAULT 'OUVERTE',
    `suspension_coupure` BOOLEAN NOT NULL DEFAULT TRUE,
    `reponse_fournisseur` TEXT NULL,
    `date_reponse` DATETIME NULL,
    `traite_par` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_client`) REFERENCES `clients`(`id_client`) ON DELETE CASCADE,
    FOREIGN KEY (`id_facture`) REFERENCES `factures`(`id_facture`) ON DELETE SET NULL,
    FOREIGN KEY (`traite_par`) REFERENCES `utilisateurs`(`id_utilisateur`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 11. TABLE : controle_annuel (RG39 à RG44)
-- =============================================================================
CREATE TABLE `controle_annuel` (
    `id_controle` INT AUTO_INCREMENT PRIMARY KEY,
    `annee` INT NOT NULL,
    `id_client` INT NOT NULL,
    `id_agent` INT NOT NULL,
    `date_releve_agent` DATE NOT NULL,
    `conso_eau_declaree` INT NOT NULL DEFAULT 0,
    `conso_elec_declaree` INT NOT NULL DEFAULT 0,
    `conso_eau_reelle_agent` INT NOT NULL DEFAULT 0,
    `conso_elec_reelle_agent` INT NOT NULL DEFAULT 0,
    `ecart_eau` INT NOT NULL DEFAULT 0,
    `ecart_elec` INT NOT NULL DEFAULT 0,
    `ecart_eau_pct` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `ecart_elec_pct` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `resultat` ENUM('CONFORME', 'SOUS_DECLARATION', 'SUR_DECLARATION') NOT NULL,
    `statut_validation` ENUM('EN_ATTENTE_VALIDATION', 'VALIDE_PAR_FOURNISSEUR', 'REJETE', 'CONTESTE') NOT NULL DEFAULT 'EN_ATTENTE_VALIDATION',
    `valide_par` INT NULL,
    `date_validation` DATETIME NULL,
    `id_facture_regularisation` INT NULL,
    `observations` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_client_annee_controle` (`id_client`, `annee`),
    FOREIGN KEY (`id_client`) REFERENCES `clients`(`id_client`) ON DELETE CASCADE,
    FOREIGN KEY (`id_agent`) REFERENCES `agents`(`id_agent`) ON DELETE RESTRICT,
    FOREIGN KEY (`valide_par`) REFERENCES `utilisateurs`(`id_utilisateur`) ON DELETE SET NULL,
    FOREIGN KEY (`id_facture_regularisation`) REFERENCES `factures`(`id_facture`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 12. TABLE : sanctions (RG43, RG45, RG46, RG47, RG48)
-- =============================================================================
CREATE TABLE `sanctions` (
    `id_sanction` INT AUTO_INCREMENT PRIMARY KEY,
    `reference_sanction` VARCHAR(50) NOT NULL UNIQUE,
    `id_client` INT NOT NULL,
    `id_controle` INT NULL,
    `id_facture_exceptionnelle` INT NULL,
    `motif` ENUM('FRAUDE_SOUS_DECLARATION', 'RETARD_REPETE_3_MOIS', 'NON_DECLARATION_PROLONGEE', 'AUTRE') NOT NULL,
    `montant_energie_regularisee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `penalite_fraude` DECIMAL(10,2) NOT NULL DEFAULT 2000.00,
    `frais_retablissement` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `montant_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `date_notification` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_limite_paiement` DATE NOT NULL,
    `date_coupure_programmee` DATE NOT NULL,
    `statut` ENUM('NOTIFIEE', 'CONTESTEE', 'CONFIRMEE', 'REGULARISEE', 'COUPURE_EFFECTUEE', 'ANNULEE') NOT NULL DEFAULT 'NOTIFIEE',
    `date_coupure_reelle` DATETIME NULL,
    `date_retablissement` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_client`) REFERENCES `clients`(`id_client`) ON DELETE CASCADE,
    FOREIGN KEY (`id_controle`) REFERENCES `controle_annuel`(`id_controle`) ON DELETE SET NULL,
    FOREIGN KEY (`id_facture_exceptionnelle`) REFERENCES `factures`(`id_facture`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 13. TABLE : coupures_services (RG34, RG35, RG48)
-- =============================================================================
CREATE TABLE `coupures_services` (
    `id_coupure` INT AUTO_INCREMENT PRIMARY KEY,
    `id_client` INT NOT NULL,
    `id_sanction` INT NULL,
    `motif_coupure` ENUM('TROIS_MOIS_IMPAYES', 'SANCTION_FRAUDE_NON_PAYEE', 'DEMANDE_FOURNISSEUR') NOT NULL,
    `service_coupe` ENUM('EAU', 'ELECTRICITE', 'TOUS_LES_DEUX') NOT NULL DEFAULT 'TOUS_LES_DEUX',
    `date_alerte` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_programmee` DATE NOT NULL,
    `date_execution` DATETIME NULL,
    `statut` ENUM('PROGRAMMEE', 'EFFECTUEE', 'ANNULEE', 'RETABLIE') NOT NULL DEFAULT 'PROGRAMMEE',
    `frais_retablissement` DECIMAL(10,2) NOT NULL DEFAULT 1000.00,
    `date_retablissement` DATETIME NULL,
    `agent_responsable` INT NULL,
    `commentaire` TEXT NULL,
    FOREIGN KEY (`id_client`) REFERENCES `clients`(`id_client`) ON DELETE CASCADE,
    FOREIGN KEY (`id_sanction`) REFERENCES `sanctions`(`id_sanction`) ON DELETE SET NULL,
    FOREIGN KEY (`agent_responsable`) REFERENCES `utilisateurs`(`id_utilisateur`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 14. TABLE : notifications (RG8, RG16, RG20, RG22, RG30, RG32, RG37, RG45)
-- =============================================================================
CREATE TABLE `notifications` (
    `id_notification` INT AUTO_INCREMENT PRIMARY KEY,
    `id_utilisateur` INT NOT NULL,
    `titre` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('RAPPEL_SAISIE', 'FACTURE_DISPONIBLE', 'RETARD_PAIEMENT', 'ANOMALIE_DETECTEE', 'ALERTE_COUPURE', 'RECLAMATION_REPONSE', 'SANCTION', 'INFO') NOT NULL,
    `lu` BOOLEAN NOT NULL DEFAULT FALSE,
    `date_envoi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_lecture` DATETIME NULL,
    KEY `idx_notif_utilisateur_lu` (`id_utilisateur`, `lu`),
    FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs`(`id_utilisateur`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 15. TABLE : journal_actions (RG51, RG52)
-- =============================================================================
CREATE TABLE `journal_actions` (
    `id_log` INT AUTO_INCREMENT PRIMARY KEY,
    `id_utilisateur` INT NULL,
    `action_type` VARCHAR(50) NOT NULL,
    `table_concernee` VARCHAR(50) NULL,
    `id_entite` INT NULL,
    `description` TEXT NOT NULL,
    `ancienne_valeur` TEXT NULL,
    `nouvelle_valeur` TEXT NULL,
    `adresse_ip` VARCHAR(45) NULL,
    `date_action` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs`(`id_utilisateur`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 16. TABLE : import_lignes_rejetees (RG39, RG40)
-- =============================================================================
CREATE TABLE `import_lignes_rejetees` (
    `id_rejet` INT AUTO_INCREMENT PRIMARY KEY,
    `nom_fichier` VARCHAR(100) NOT NULL DEFAULT 'Consommation_Annuelle.txt',
    `annee` INT NOT NULL,
    `numero_ligne` INT NOT NULL,
    `contenu_brut` TEXT NOT NULL,
    `motif_rejet` VARCHAR(255) NOT NULL,
    `date_import` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- INSERTION DES PARAMÈTRES ET TARIFS OFFICIELS DU TP (RG26, RG27, RG50)
-- =============================================================================

INSERT INTO `parametres` (`cle`, `libelle`, `valeur`, `type_donnee`, `description`) VALUES
('TVA_POURCENT', 'Taux de TVA', '18.00', 'DECIMAL', 'RG28: Taux de TVA applique sur le total HT (18%)'),
('JOUR_OUVERTURE_SAISIE', 'Jour d ouverture de la saisie', '18', 'INTEGER', 'RG11: La saisie est ouverte du 18 au dernier jour du mois'),
('JOUR_ECHEANCE_FACTURE', 'Jour d echeance de paiement', '15', 'INTEGER', 'RG29: L echeance est fixee au 15 du mois suivant'),
('SEUIL_MAX_ELEC_MENSUEL', 'Seuil max mensuel electricite (kWh)', '2000', 'INTEGER', 'RG21: Anomalie si consommation mensuelle > 2000 kWh'),
('SEUIL_MAX_EAU_MENSUEL', 'Seuil max mensuel eau (m3)', '100', 'INTEGER', 'RG21: Anomalie si consommation mensuelle > 100 m3'),
('COEF_SEUIL_MOYENNE', 'Coefficient d anomalie sur moyenne historique', '2.5', 'DECIMAL', 'RG21/4.6.2: anomalie si conso > coef * moyenne des 3 derniers mois'),
('PENALITE_RETARD_POURCENT', 'Taux penalite de retard (%)', '5.00', 'DECIMAL', 'RG33: 5% du montant TTC de la facture en retard'),
('PENALITE_RETARD_MINIMUM_DH', 'Penalite de retard minimum (DH)', '15.00', 'DECIMAL', 'RG33: Minimum de 15 DH pour la penalite de retard'),
('NB_MOIS_IMPAYES_COUPURE', 'Mois impayes avant coupure', '3', 'INTEGER', 'RG34: Coupure immediate du service apres 3 mois impayes consecutifs'),
('NB_MOIS_NON_DECLARES_ALERTE', 'Mois non declares avant alerte', '3', 'INTEGER', 'RG20: Notification fournisseur apres 3 mois non declares consecutifs'),
('FRAIS_RETABLISSEMENT_DH', 'Frais de retablissement (DH)', '1000.00', 'DECIMAL', 'RG35: Frais fixes de 1000 DH pour le retablissement apres coupure'),
('PENALITE_FRAUDE_DH', 'Penalite forfaitaire pour fraude (DH)', '2000.00', 'DECIMAL', 'RG43: Penalite de fraude de 2000 DH lors du controle annuel'),
('TOLERANCE_CONTROLE_ANNUEL_PCT', 'Tolerance du controle annuel (%)', '5.00', 'DECIMAL', 'RG42: Ecart acceptable entre releve annuel et declarations mensuelles'),
('ESTIMATION_DEFAUT_EAU_M3', 'Estimation par defaut eau (m3)', '10', 'INTEGER', 'RG18: Utilisee en l absence d historique pour la facture estimee eau'),
('ESTIMATION_DEFAUT_ELEC_KWH', 'Estimation par defaut electricite (kWh)', '150', 'INTEGER', 'RG18: Utilisee en l absence d historique pour la facture estimee elec'),
('MAX_TENTATIVES_ECHECS_CONNEXION', 'Nb max d echecs de connexion', '5', 'INTEGER', 'RG6: Blocage temporaire apres 5 echecs consecutifs'),
('DELAI_SESSION_MINUTES', 'Duree de validite de session (min)', '15', 'INTEGER', 'RG7: Expiration de la session apres 15 minutes d inactivite');

-- Tarifs electricite (RG26) :
INSERT INTO `tarifs_tranches` (`type_fluide`, `tranche_num`, `borne_min`, `borne_max`, `prix_unitaire_dh`, `description`) VALUES
('ELECTRICITE', 1, 0, 100, 0.8200, 'Tranche 1 (0 a 100 kWh)'),
('ELECTRICITE', 2, 101, 150, 0.9200, 'Tranche 2 (101 a 150 kWh)'),
('ELECTRICITE', 3, 151, NULL, 1.1000, 'Tranche 3 (Plus de 150 kWh)');

-- Tarifs eau (RG27) :
INSERT INTO `tarifs_tranches` (`type_fluide`, `tranche_num`, `borne_min`, `borne_max`, `prix_unitaire_dh`, `description`) VALUES
('EAU', 1, 0, 6, 2.3700, 'Tranche 1 (0 a 6 m3)'),
('EAU', 2, 7, 20, 7.3900, 'Tranche 2 (7 a 20 m3)'),
('EAU', 3, 21, 35, 10.9800, 'Tranche 3 (21 a 35 m3)'),
('EAU', 4, 36, NULL, 11.0300, 'Tranche 4 (Plus de 35 m3)');

-- Compte Fournisseur (login / email: admin@fournisseur.ma | mot de passe: Admin1234@)
INSERT INTO `utilisateurs` (`id_utilisateur`, `identifiant`, `email`, `mot_de_passe`, `role`, `statut_compte`) VALUES
(1, 'admin@fournisseur.ma', 'admin@fournisseur.ma', '$2y$10$wT8B14V.c91K56jE038U1e028nN1.C4a3EaAekJ1E152P70hM1a1y', 'FOURNISSEUR', 'ACTIF');

-- Agent de tournee (RG39)
INSERT INTO `agents` (`id_agent`, `matricule`, `nom`, `prenom`, `telephone`, `email`, `zone_affectation`, `actif`) VALUES
(1, 'AGT-001', 'Alami', 'Karim', '0661122334', 'k.alami@fournisseur.ma', 'Tetouan Centre', TRUE);

-- Clients tests (CLT-2026-000101 et CLT-2026-000102 | mot de passe: Client1234@)
INSERT INTO `utilisateurs` (`id_utilisateur`, `identifiant`, `email`, `mot_de_passe`, `role`, `statut_compte`) VALUES
(2, 'CLT-2026-000101', 'omar.benali@email.ma', '$2y$10$wT8B14V.c91K56jE038U1e028nN1.C4a3EaAekJ1E152P70hM1a1y', 'CLIENT', 'ACTIF'),
(3, 'CLT-2026-000102', 'fatima.amrani@email.ma', '$2y$10$wT8B14V.c91K56jE038U1e028nN1.C4a3EaAekJ1E152P70hM1a1y', 'CLIENT', 'ACTIF');

INSERT INTO `clients` (`id_client`, `id_utilisateur`, `numero_client`, `cin`, `nom`, `prenom`, `adresse`, `telephone`, `numero_compteur_eau`, `numero_compteur_elec`, `index_initial_eau`, `index_initial_elec`, `solde_credit`, `statut_service`) VALUES
(1, 2, 'CLT-2026-000101', 'K123456', 'Benali', 'Omar', '12 Avenue Hassan II, Tetouan', '0662001122', 'CPT-EAU-1001', 'CPT-ELEC-2001', 120, 1500, 0.00, 'ACTIF'),
(2, 3, 'CLT-2026-000102', 'L987654', 'El Amrani', 'Fatima', '45 Boulevard Mohamed V, Martil', '0663445566', 'CPT-EAU-1002', 'CPT-ELEC-2002', 85, 920, 0.00, 'ACTIF');

-- Donnees du cas chiffre officiel (Section 4.6.4 p.16-17) :
-- 134 kWh elec (123.28 DH) + 9 m3 eau (66.51 DH) -> HT: 189.79 DH, TVA 18%: 34.16 DH, TTC: 223.95 DH
INSERT INTO `consommations` (`id_conso`, `id_client`, `annee`, `mois`, `index_eau_precedent`, `index_eau_nouveau`, `conso_eau`, `photo_compteur_eau`, `index_elec_precedent`, `index_elec_nouveau`, `conso_elec`, `photo_compteur_elec`, `date_saisie`, `type_saisie`, `statut`) VALUES
(1, 1, 2026, 9, 120, 129, 9, 'uploads/compteurs/eau_clt101_202609.jpg', 1500, 1634, 134, 'uploads/compteurs/elec_clt101_202609.jpg', '2026-09-22 10:15:00', 'REELLE', 'VALIDE');

INSERT INTO `factures` (`id_facture`, `numero_facture`, `id_client`, `id_conso`, `periode_mois`, `periode_annee`, `date_emission`, `date_echeance`, `type_facture`, `index_eau_debut`, `index_eau_fin`, `conso_eau`, `montant_ht_eau`, `index_elec_debut`, `index_elec_fin`, `conso_elec`, `montant_ht_elec`, `montant_ht`, `taux_tva`, `montant_tva`, `montant_ttc`, `credit_deduit`, `penalite_retard`, `penalite_appliquee`, `montant_total_a_payer`, `montant_paye`, `reste_a_payer`, `statut`, `photo_eau_facture`, `photo_elec_facture`) VALUES
(1, 'FAC-2026-09-000101', 1, 1, 9, 2026, '2026-10-01', '2026-10-15', 'MENSUELLE', 120, 129, 9, 66.51, 1500, 1634, 134, 123.28, 189.79, 18.00, 34.16, 223.95, 0.00, 0.00, FALSE, 223.95, 0.00, 223.95, 'IMPAYEE', 'uploads/compteurs/eau_clt101_202609.jpg', 'uploads/compteurs/elec_clt101_202609.jpg');

INSERT INTO `journal_actions` (`id_utilisateur`, `action_type`, `table_concernee`, `id_entite`, `description`, `adresse_ip`) VALUES
(1, 'INITIALISATION_SYSTEME', 'parametres', 1, 'Initialisation du schema de la base de donnees', '127.0.0.1');