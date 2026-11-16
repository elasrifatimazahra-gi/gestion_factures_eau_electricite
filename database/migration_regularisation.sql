USE `gestion_factures_tp2`;

-- Membre 3 : régularisation des factures estimées (RG19).
-- Montant (négatif) déjà facturé en estimation, déduit de la facture du vrai relevé.
ALTER TABLE `factures`
    ADD COLUMN IF NOT EXISTS `montant_regularisation` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `credit_deduit`;
