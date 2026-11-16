-- Migration pour une base existante : à exécuter une seule fois.
ALTER TABLE contrats
    ADD COLUMN statut_service ENUM('ACTIF', 'AVERTISSEMENT', 'COUPE') NOT NULL DEFAULT 'ACTIF' AFTER statut;

-- Reprend l'état de service historique au niveau des contrats.
UPDATE contrats ct
JOIN clients cl ON cl.id_client = ct.id_client
SET ct.statut_service = cl.statut_service;

ALTER TABLE coupures_services
    ADD COLUMN id_contrat INT NULL AFTER id_client,
    ADD KEY idx_coupure_contrat (id_contrat),
    ADD CONSTRAINT fk_coupures_contrat
        FOREIGN KEY (id_contrat) REFERENCES contrats(id_contrat) ON DELETE SET NULL;

-- Lie les anciennes coupures uniquement quand le client n'a qu'un seul contrat.
UPDATE coupures_services cs
JOIN (
    SELECT id_client, MIN(id_contrat) AS id_contrat
    FROM contrats
    GROUP BY id_client
    HAVING COUNT(*) = 1
) ct ON ct.id_client = cs.id_client
SET cs.id_contrat = ct.id_contrat
WHERE cs.id_contrat IS NULL;
