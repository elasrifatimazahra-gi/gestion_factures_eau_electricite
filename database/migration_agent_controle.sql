USE `gestion_factures_tp2`;

-- À exécuter après migration_contrat.sql pour activer les comptes agent et les contrôles par contrat.
ALTER TABLE `utilisateurs`
    MODIFY COLUMN `role` ENUM('CLIENT', 'FOURNISSEUR', 'AGENT') NOT NULL DEFAULT 'CLIENT';

ALTER TABLE `consommations`
    MODIFY COLUMN `type_saisie` ENUM('REELLE', 'MANUELLE', 'ESTIMEE', 'CORRIGEE') NOT NULL DEFAULT 'REELLE';

ALTER TABLE `import_lignes_rejetees`
    MODIFY COLUMN `annee` INT NULL;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'agents' AND `COLUMN_NAME` = 'id_utilisateur') = 0,
    'ALTER TABLE `agents` ADD COLUMN `id_utilisateur` INT NULL AFTER `id_agent`',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'agents' AND `INDEX_NAME` = 'unique_agent_utilisateur') = 0,
    'ALTER TABLE `agents` ADD UNIQUE KEY `unique_agent_utilisateur` (`id_utilisateur`)',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`TABLE_CONSTRAINTS`
     WHERE `CONSTRAINT_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'agents' AND `CONSTRAINT_NAME` = 'fk_agents_utilisateur') = 0,
    'ALTER TABLE `agents` ADD CONSTRAINT `fk_agents_utilisateur` FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs` (`id_utilisateur`) ON DELETE SET NULL',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'consommations' AND `COLUMN_NAME` = 'id_agent') = 0,
    'ALTER TABLE `consommations` ADD COLUMN `id_agent` INT NULL AFTER `id_contrat`',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`TABLE_CONSTRAINTS`
     WHERE `CONSTRAINT_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'consommations' AND `CONSTRAINT_NAME` = 'fk_consommations_agent') = 0,
    'ALTER TABLE `consommations` ADD CONSTRAINT `fk_consommations_agent` FOREIGN KEY (`id_agent`) REFERENCES `agents` (`id_agent`) ON DELETE SET NULL',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'consommations' AND `INDEX_NAME` = 'idx_consommations_agent') = 0,
    'ALTER TABLE `consommations` ADD KEY `idx_consommations_agent` (`id_agent`)',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'controle_annuel' AND `COLUMN_NAME` = 'id_contrat') = 0,
    'ALTER TABLE `controle_annuel` ADD COLUMN `id_contrat` INT NULL AFTER `id_client`',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

-- Les anciens contrôles restent sans contrat : l’ancien modèle ne permet pas de les rattacher sûrement.
SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
     WHERE `TABLE_SCHEMA` = DATABASE()
       AND `TABLE_NAME` = 'controle_annuel'
       AND `COLUMN_NAME` = 'id_client'
       AND `SEQ_IN_INDEX` = 1
       AND `INDEX_NAME` <> 'unique_client_annee_controle') = 0,
    'ALTER TABLE `controle_annuel` ADD KEY `idx_controle_client` (`id_client`)',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'controle_annuel' AND `INDEX_NAME` = 'unique_client_annee_controle') > 0,
    'ALTER TABLE `controle_annuel` DROP INDEX `unique_client_annee_controle`',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
     WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'controle_annuel' AND `INDEX_NAME` = 'unique_contrat_annee_controle') = 0,
    'ALTER TABLE `controle_annuel` ADD UNIQUE KEY `unique_contrat_annee_controle` (`id_contrat`, `annee`)',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @migration_ddl = IF(
    (SELECT COUNT(*) FROM `information_schema`.`TABLE_CONSTRAINTS`
     WHERE `CONSTRAINT_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'controle_annuel' AND `CONSTRAINT_NAME` = 'fk_controle_contrat') = 0,
    'ALTER TABLE `controle_annuel` ADD CONSTRAINT `fk_controle_contrat` FOREIGN KEY (`id_contrat`) REFERENCES `contrats` (`id_contrat`) ON DELETE SET NULL',
    'SELECT NULL WHERE FALSE'
);
PREPARE migration_stmt FROM @migration_ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;
