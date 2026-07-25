-- Table des financements accordés aux agents
-- À exécuter sur la base de données externe (api.objetombrepegasus.online)

CREATE TABLE IF NOT EXISTS `financements` (
  `id_financement` int(11) NOT NULL AUTO_INCREMENT,
  `id_agent` int(11) NOT NULL,
  `id_user` int(11) NOT NULL COMMENT 'Propriétaire qui accorde le financement',
  `montant` decimal(12,2) NOT NULL DEFAULT 0.00,
  `date_financement` date NOT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `statut` enum('accordé','en attente','refusé') NOT NULL DEFAULT 'accordé',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_financement`),
  KEY `financements_id_agent_index` (`id_agent`),
  KEY `financements_id_user_index` (`id_user`),
  KEY `financements_date_financement_index` (`date_financement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Exemple de données de test
-- INSERT INTO `financements` (`id_agent`, `id_user`, `montant`, `date_financement`, `motif`, `statut`) VALUES
-- (1, 1, 50000.00, '2026-02-01', 'Avance sur salaire', 'accordé'),
-- (2, 1, 100000.00, '2026-02-05', 'Prêt personnel', 'accordé'),
-- (3, 1, 25000.00, '2026-02-06', 'Frais médicaux', 'en attente');
