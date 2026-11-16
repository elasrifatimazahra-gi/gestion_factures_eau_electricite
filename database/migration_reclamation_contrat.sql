-- Ajoute le rattachement au contrat pour filtrer les réclamations côté client.
ALTER TABLE reclamations
    ADD COLUMN id_contrat INT NULL AFTER id_facture,
    ADD KEY idx_reclamations_contrat (id_contrat),
    ADD CONSTRAINT fk_reclamations_contrat
        FOREIGN KEY (id_contrat) REFERENCES contrats(id_contrat) ON DELETE SET NULL;

-- Reprend automatiquement le contrat des réclamations déjà liées à une facture.
UPDATE reclamations r
JOIN factures f ON f.id_facture = r.id_facture
SET r.id_contrat = f.id_contrat
WHERE r.id_contrat IS NULL;
