CREATE TABLE IF NOT EXISTS chemicals (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(120) NOT NULL,
  warehouse_code INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_chemicals_code (code),
  KEY idx_chemicals_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chemical_weighings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  work_order_id BIGINT UNSIGNED NOT NULL,
  chemical_id INT UNSIGNED NOT NULL,
  initial_weight_kg DECIMAL(10,3) NOT NULL,
  return_weight_kg DECIMAL(10,3) NOT NULL,
  net_consumption_kg DECIMAL(10,3) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_chemical_weighings_wo (work_order_id),
  KEY idx_chemical_weighings_chem (chemical_id),
  CONSTRAINT fk_chemical_weighings_wo FOREIGN KEY (work_order_id) REFERENCES work_orders(id),
  CONSTRAINT fk_chemical_weighings_chem FOREIGN KEY (chemical_id) REFERENCES chemicals(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO chemicals (code, name, warehouse_code, is_active) VALUES
  ('B900', 'Tinta (B900)', 900, 1),
  ('B910', 'Solvente (B910)', 910, 1),
  ('B920', 'Solvente (B920)', 920, 1);

