CREATE TABLE IF NOT EXISTS `reclamation_messages` (
    `id_message` INT AUTO_INCREMENT PRIMARY KEY,
    `id_reclamation` INT NOT NULL,
    `id_auteur` INT NULL,
    `role_auteur` ENUM('CLIENT', 'FOURNISSEUR') NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_reclamation_messages_conversation` (`id_reclamation`, `created_at`),
    FOREIGN KEY (`id_reclamation`) REFERENCES `reclamations`(`id_reclamation`) ON DELETE CASCADE,
    FOREIGN KEY (`id_auteur`) REFERENCES `utilisateurs`(`id_utilisateur`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
