CREATE TABLE IF NOT EXISTS warehouses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_warehouses_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS skus (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(60) NOT NULL,
  description VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_skus_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rolls (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  roll_code VARCHAR(40) NOT NULL,
  sku_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  weight_kg DECIMAL(10,3) NOT NULL,
  received_qty DECIMAL(12,3) NOT NULL DEFAULT 1.000,
  microns INT UNSIGNED NULL,
  width_mm INT UNSIGNED NULL,
  color VARCHAR(60) NULL,
  meters DECIMAL(12,2) NULL,
  status ENUM('RECEIVED','IN_PROCESS','BLOCKED','CONSUMED') NOT NULL DEFAULT 'RECEIVED',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rolls_roll_code (roll_code),
  KEY idx_rolls_sku_id (sku_id),
  KEY idx_rolls_warehouse_id (warehouse_id),
  CONSTRAINT fk_rolls_sku_id FOREIGN KEY (sku_id) REFERENCES skus(id),
  CONSTRAINT fk_rolls_warehouse_id FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_type VARCHAR(30) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  movement_type ENUM('RECEIPT','TRANSFER','ADJUSTMENT') NOT NULL,
  from_warehouse_id INT UNSIGNED NULL,
  to_warehouse_id INT UNSIGNED NULL,
  payload JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_movements_entity (entity_type, entity_id),
  KEY idx_movements_from_wh (from_warehouse_id),
  KEY idx_movements_to_wh (to_warehouse_id),
  CONSTRAINT fk_movements_from_wh FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(id),
  CONSTRAINT fk_movements_to_wh FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  type VARCHAR(50) NOT NULL,
  payload JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_events_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(60) NOT NULL,
  setting_value VARCHAR(255) NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_app_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(60) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  can_erp TINYINT(1) NOT NULL DEFAULT 1,
  can_production TINYINT(1) NOT NULL DEFAULT 1,
  can_operator TINYINT(1) NOT NULL DEFAULT 1,
  can_warehouse TINYINT(1) NOT NULL DEFAULT 1,
  can_marketing TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_auth_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ot_code VARCHAR(40) NOT NULL,
  sku_final VARCHAR(80) NOT NULL,
  target_qty INT UNSIGNED NULL,
  status ENUM('OPEN','ACTIVE','CLOSED') NOT NULL DEFAULT 'OPEN',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_work_orders_ot_code (ot_code),
  KEY idx_work_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

INSERT IGNORE INTO warehouses (code, name) VALUES
  (100, 'Bodega 100 - Recepción MP'),
  (200, 'Bodega 200 - Recepción MP'),
  (700, 'Bodega 700 - Canal Tradicional'),
  (1000, 'Bodega 1000 - Retail');

INSERT IGNORE INTO skus (code, description) VALUES
  ('TEL0006', 'PP/NEGRO/C90/100X80X1100'),
  ('TEL0007', 'PP/MORADO/P62/100X80X1100'),
  ('TEL0008', 'PP/ROJO/RO1/100X80X1100'),
  ('TEL0009', 'PP/NARANJO/Y23/100X80X1100'),
  ('TEL0011', 'PP/VERDE PISTACHO/G41/100X80X1100'),
  ('TEL0034', 'PP/BEIGE/Y26/130X80X1100'),
  ('TEL0042', 'PP/FUCSIA/R08/100X80X1100'),
  ('TEL0044', 'PP/BLANCO/W80/100X80X1100'),
  ('TEL0046', 'PP/AZUL REY/B53/100X80X1100'),
  ('TEL0070', 'PP/CELESTE/B50/100X80X1100'),
  ('TEL0084', 'PP/BURDEO/R06/100X80X1100'),
  ('TEL0122', 'PP/GRIS OSCURO/E72/100X80X1100'),
  ('TEL0173', 'PP/AMARILLO/Y20/130X80X1100'),
  ('TEL0174', 'PP/VERDE HOJA/G42/130X80X1100'),
  ('TEL0197', 'PP/NEGRO/C90/100X80X1000');

INSERT IGNORE INTO work_orders (ot_code, sku_final, target_qty, status) VALUES
  ('OT-0001', 'SKU-FINAL-EJ-001', 10000, 'OPEN'),
  ('OT-0002', 'SKU-FINAL-EJ-002', 5000, 'OPEN');

INSERT IGNORE INTO chemicals (code, name, warehouse_code, is_active) VALUES
  ('B900', 'Tinta (B900)', 900, 1),
  ('B910', 'Tinta (B910)', 910, 1),
  ('B920', 'Tinta (B920)', 920, 1);

INSERT IGNORE INTO auth_users (
  username, password_hash, display_name, is_active,
  can_erp, can_production, can_operator, can_warehouse, can_marketing
) VALUES (
  'demo',
  '$2y$10$NEnibNryVcuH8MX2zxUaW.Inrqb0go6.jf3VMFXHERLyLuY0jOAny',
  'Operador Demo',
  1,
  1, 1, 1, 1, 1
);
