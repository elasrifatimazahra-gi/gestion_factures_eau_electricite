USE `gestion_factures_tp2`;

-- Migration consolidée et réexécutable pour le socle contrats/quartiers.
-- Les prix commerciaux (+25 %) et industriels (+50 %) sont des valeurs indicatives,
-- modifiables depuis Paramètres; la grille résidentielle officielle est conservée.

CREATE TABLE IF NOT EXISTS `types_activite` (
    `id_type_activite` TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(30) NOT NULL UNIQUE,
    `libelle` VARCHAR(80) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `types_activite` (`code`, `libelle`) VALUES
('RESIDENTIEL', 'Résidentiel'),
('COMMERCIAL', 'Commercial'),
('INDUSTRIEL', 'Industriel')
ON DUPLICATE KEY UPDATE `libelle` = VALUES(`libelle`);

CREATE TABLE IF NOT EXISTS `quartiers` (
    `id_quartier` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(120) NOT NULL,
    `adresse` TEXT NULL,
    `id_type_activite` TINYINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_quartier_type_activite` (`id_type_activite`),
    CONSTRAINT `fk_quartiers_type_activite`
        FOREIGN KEY (`id_type_activite`) REFERENCES `types_activite` (`id_type_activite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contrats` (
    `id_contrat` INT AUTO_INCREMENT PRIMARY KEY,
    `numero_contrat` VARCHAR(40) NOT NULL UNIQUE,
    `id_client` INT NOT NULL,
    `id_quartier` INT NULL,
    `numero_compteur_eau` VARCHAR(50) NULL,
    `numero_compteur_elec` VARCHAR(50) NULL,
    `index_initial_eau` INT NOT NULL DEFAULT 0,
    `index_initial_elec` INT NOT NULL DEFAULT 0,
    `statut` ENUM('ACTIF', 'SUSPENDU', 'RESILIE') NOT NULL DEFAULT 'ACTIF',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_contrats_client` (`id_client`),
    KEY `idx_contrats_quartier` (`id_quartier`),
    CONSTRAINT `fk_contrats_client`
        FOREIGN KEY (`id_client`) REFERENCES `clients` (`id_client`) ON DELETE CASCADE,
    CONSTRAINT `fk_contrats_quartier`
        FOREIGN KEY (`id_quartier`) REFERENCES `quartiers` (`id_quartier`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'tarifs_tranches' AND `COLUMN_NAME` = 'id_type_activite') = 0,
    'ALTER TABLE `tarifs_tranches` ADD COLUMN `id_type_activite` TINYINT UNSIGNED NULL AFTER `id_tarif`',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

UPDATE `tarifs_tranches`
SET `id_type_activite` = (
    SELECT `id_type_activite` FROM `types_activite` WHERE `code` = 'RESIDENTIEL'
)
WHERE `id_type_activite` IS NULL;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'tarifs_tranches' AND `INDEX_NAME` = 'unique_tranche_fluide') > 0,
    'ALTER TABLE `tarifs_tranches` DROP INDEX `unique_tranche_fluide`',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

ALTER TABLE `tarifs_tranches`
    MODIFY COLUMN `id_type_activite` TINYINT UNSIGNED NOT NULL;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'tarifs_tranches' AND `INDEX_NAME` = 'idx_tarifs_type_activite') = 0,
    'ALTER TABLE `tarifs_tranches` ADD KEY `idx_tarifs_type_activite` (`id_type_activite`)',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'consommations' AND `INDEX_NAME` = 'idx_consommations_client') = 0,
    'ALTER TABLE `consommations` ADD KEY `idx_consommations_client` (`id_client`)',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`TABLE_CONSTRAINTS`
     WHERE `CONSTRAINT_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'tarifs_tranches' AND `CONSTRAINT_NAME` = 'fk_tarifs_type_activite') = 0,
    'ALTER TABLE `tarifs_tranches` ADD CONSTRAINT `fk_tarifs_type_activite` FOREIGN KEY (`id_type_activite`) REFERENCES `types_activite` (`id_type_activite`)',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'tarifs_tranches' AND `INDEX_NAME` = 'unique_tranche_activite_fluide') = 0,
    'ALTER TABLE `tarifs_tranches` ADD UNIQUE KEY `unique_tranche_activite_fluide` (`id_type_activite`, `type_fluide`, `tranche_num`)',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'agents' AND `COLUMN_NAME` = 'id_quartier') = 0,
    'ALTER TABLE `agents` ADD COLUMN `id_quartier` INT NULL AFTER `zone_affectation`',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`TABLE_CONSTRAINTS`
     WHERE `CONSTRAINT_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'agents' AND `CONSTRAINT_NAME` = 'fk_agents_quartier') = 0,
    'ALTER TABLE `agents` ADD CONSTRAINT `fk_agents_quartier` FOREIGN KEY (`id_quartier`) REFERENCES `quartiers` (`id_quartier`) ON DELETE SET NULL',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'consommations' AND `COLUMN_NAME` = 'id_contrat') = 0,
    'ALTER TABLE `consommations` ADD COLUMN `id_contrat` INT NULL AFTER `id_client`',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'consommations' AND `INDEX_NAME` = 'unique_client_mois_annee') > 0,
    'ALTER TABLE `consommations` DROP INDEX `unique_client_mois_annee`',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'consommations' AND `INDEX_NAME` = 'unique_contrat_mois_annee') = 0,
    'ALTER TABLE `consommations` ADD UNIQUE KEY `unique_contrat_mois_annee` (`id_contrat`, `annee`, `mois`)',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`TABLE_CONSTRAINTS`
     WHERE `CONSTRAINT_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'consommations' AND `CONSTRAINT_NAME` = 'fk_consommations_contrat') = 0,
    'ALTER TABLE `consommations` ADD CONSTRAINT `fk_consommations_contrat` FOREIGN KEY (`id_contrat`) REFERENCES `contrats` (`id_contrat`) ON DELETE SET NULL',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'factures' AND `COLUMN_NAME` = 'id_contrat') = 0,
    'ALTER TABLE `factures` ADD COLUMN `id_contrat` INT NULL AFTER `id_client`',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'factures' AND `INDEX_NAME` = 'idx_facture_contrat') = 0,
    'ALTER TABLE `factures` ADD KEY `idx_facture_contrat` (`id_contrat`)',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`TABLE_CONSTRAINTS`
     WHERE `CONSTRAINT_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'factures' AND `CONSTRAINT_NAME` = 'fk_factures_contrat') = 0,
    'ALTER TABLE `factures` ADD CONSTRAINT `fk_factures_contrat` FOREIGN KEY (`id_contrat`) REFERENCES `contrats` (`id_contrat`) ON DELETE SET NULL',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

-- Crée un contrat initial par client existant, sans inventer son quartier.
INSERT INTO `contrats`
    (`numero_contrat`, `id_client`, `numero_compteur_eau`, `numero_compteur_elec`,
     `index_initial_eau`, `index_initial_elec`)
SELECT CONCAT('LEGACY-', LPAD(c.`id_client`, 10, '0')),
       c.`id_client`,
       c.`numero_compteur_eau`,
       c.`numero_compteur_elec`,
       c.`index_initial_eau`,
       c.`index_initial_elec`
FROM `clients` c
WHERE NOT EXISTS (
    SELECT 1 FROM `contrats` ct WHERE ct.`id_client` = c.`id_client`
);

UPDATE `consommations` co
JOIN `contrats` ct ON ct.`id_client` = co.`id_client`
SET co.`id_contrat` = ct.`id_contrat`
WHERE co.`id_contrat` IS NULL;

UPDATE `factures` f
JOIN `contrats` ct ON ct.`id_client` = f.`id_client`
SET f.`id_contrat` = ct.`id_contrat`
WHERE f.`id_contrat` IS NULL;

-- Grille commerciale indicative : +25 % par rapport aux tarifs résidentiels.
INSERT INTO `tarifs_tranches`
    (`id_type_activite`, `type_fluide`, `tranche_num`, `borne_min`, `borne_max`, `prix_unitaire_dh`, `description`)
SELECT t.`id_type_activite`, r.`type_fluide`, r.`tranche_num`, r.`borne_min`, r.`borne_max`,
       r.`prix_unitaire_dh`, CONCAT(r.`description`, ' - tarif commercial indicatif (+25%)')
FROM `types_activite` t
CROSS JOIN (
    SELECT 'ELECTRICITE' AS `type_fluide`, 1 AS `tranche_num`, 0 AS `borne_min`, 100 AS `borne_max`, 1.0250 AS `prix_unitaire_dh`, 'Tranche 1 (0 a 100 kWh)' AS `description`
    UNION ALL SELECT 'ELECTRICITE', 2, 101, 150, 1.1500, 'Tranche 2 (101 a 150 kWh)'
    UNION ALL SELECT 'ELECTRICITE', 3, 151, NULL, 1.3750, 'Tranche 3 (Plus de 150 kWh)'
    UNION ALL SELECT 'EAU', 1, 0, 6, 2.9625, 'Tranche 1 (0 a 6 m3)'
    UNION ALL SELECT 'EAU', 2, 7, 20, 9.2375, 'Tranche 2 (7 a 20 m3)'
    UNION ALL SELECT 'EAU', 3, 21, 35, 13.7250, 'Tranche 3 (21 a 35 m3)'
    UNION ALL SELECT 'EAU', 4, 36, NULL, 13.7875, 'Tranche 4 (Plus de 35 m3)'
) r
WHERE t.`code` = 'COMMERCIAL'
ON DUPLICATE KEY UPDATE
    `id_tarif` = LAST_INSERT_ID(`id_tarif`);

-- Grille industrielle indicative : +50 % par rapport aux tarifs résidentiels.
INSERT INTO `tarifs_tranches`
    (`id_type_activite`, `type_fluide`, `tranche_num`, `borne_min`, `borne_max`, `prix_unitaire_dh`, `description`)
SELECT t.`id_type_activite`, r.`type_fluide`, r.`tranche_num`, r.`borne_min`, r.`borne_max`,
       r.`prix_unitaire_dh`, CONCAT(r.`description`, ' - tarif industriel indicatif (+50%)')
FROM `types_activite` t
CROSS JOIN (
    SELECT 'ELECTRICITE' AS `type_fluide`, 1 AS `tranche_num`, 0 AS `borne_min`, 100 AS `borne_max`, 1.2300 AS `prix_unitaire_dh`, 'Tranche 1 (0 a 100 kWh)' AS `description`
    UNION ALL SELECT 'ELECTRICITE', 2, 101, 150, 1.3800, 'Tranche 2 (101 a 150 kWh)'
    UNION ALL SELECT 'ELECTRICITE', 3, 151, NULL, 1.6500, 'Tranche 3 (Plus de 150 kWh)'
    UNION ALL SELECT 'EAU', 1, 0, 6, 3.5550, 'Tranche 1 (0 a 6 m3)'
    UNION ALL SELECT 'EAU', 2, 7, 20, 11.0850, 'Tranche 2 (7 a 20 m3)'
    UNION ALL SELECT 'EAU', 3, 21, 35, 16.4700, 'Tranche 3 (21 a 35 m3)'
    UNION ALL SELECT 'EAU', 4, 36, NULL, 16.5450, 'Tranche 4 (Plus de 35 m3)'
) r
WHERE t.`code` = 'INDUSTRIEL'
ON DUPLICATE KEY UPDATE
    `id_tarif` = LAST_INSERT_ID(`id_tarif`);

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'journal_actions' AND `INDEX_NAME` = 'idx_journal_date_action') = 0,
    'ALTER TABLE `journal_actions` ADD KEY `idx_journal_date_action` (`date_action`, `action_type`)',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

-- Seed client idempotent.
INSERT INTO `notifications` (`id_utilisateur`, `titre`, `message`, `type`)
SELECT u.`id_utilisateur`,
       'Bienvenue dans votre espace client',
       'Votre espace client Amanor est prêt. Vous recevrez ici les informations concernant vos factures et votre compte.',
       'INFO'
FROM `utilisateurs` u
WHERE u.`identifiant` = 'CLT-2026-000101'
  AND NOT EXISTS (
      SELECT 1
      FROM `notifications` n
      WHERE n.`id_utilisateur` = u.`id_utilisateur`
        AND n.`titre` = 'Bienvenue dans votre espace client'
        AND n.`type` = 'INFO'
  );
