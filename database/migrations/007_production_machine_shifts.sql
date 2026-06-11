CREATE TABLE IF NOT EXISTS production_machine_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(120) NOT NULL,
  production_area VARCHAR(30) NOT NULL DEFAULT 'PRODUCTION',
  erp_machine_type_id BIGINT NULL,
  display_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_production_machine_types_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_machines (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  machine_type_id INT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL,
  name VARCHAR(120) NOT NULL,
  production_area VARCHAR(30) NOT NULL DEFAULT 'PRODUCTION',
  erp_machine_id BIGINT NULL,
  plant_label VARCHAR(120) NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_production_machines_code (code),
  KEY idx_production_machines_type (machine_type_id),
  KEY idx_production_machines_erp (erp_machine_id),
  CONSTRAINT fk_production_machines_type FOREIGN KEY (machine_type_id) REFERENCES production_machine_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_shift_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  machine_id INT UNSIGNED NOT NULL,
  work_order_id BIGINT UNSIGNED NULL,
  operator_name VARCHAR(120) NOT NULL,
  helper_name VARCHAR(120) NULL,
  shift_label VARCHAR(60) NULL,
  process_stage VARCHAR(30) NOT NULL DEFAULT 'PRODUCTION',
  comments TEXT NULL,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at TIMESTAMP NULL DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_shift_sessions_machine_status (machine_id, status),
  KEY idx_shift_sessions_operator_status (operator_name, status),
  KEY idx_shift_sessions_work_order (work_order_id),
  CONSTRAINT fk_shift_sessions_machine FOREIGN KEY (machine_id) REFERENCES production_machines(id),
  CONSTRAINT fk_shift_sessions_work_order FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO production_machine_types (code, name, production_area, erp_machine_type_id, display_order, is_active) VALUES
  ('EMBALAJE', 'EMBALAJE', 'PACKAGING', NULL, 10, 1),
  ('FLEXOGRAFIA', 'IMPRESORA FLEXOGRAFIA', 'PRODUCTION', 1, 20, 1),
  ('SERIGRAFIA', 'IMPRESORA SERIGRAFIA', 'PRODUCTION', 2, 30, 1),
  ('REBOBINADO', 'REBOBINADORA', 'REWINDING', 3, 40, 1),
  ('SELLADO', 'SELLADORAS', 'SEALING', 4, 50, 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  production_area = VALUES(production_area),
  erp_machine_type_id = VALUES(erp_machine_type_id),
  display_order = VALUES(display_order),
  is_active = VALUES(is_active);

INSERT INTO production_machines (machine_type_id, code, name, production_area, erp_machine_id, plant_label, sort_order, is_active)
SELECT pmt.id, catalog.code, catalog.name, catalog.production_area, catalog.erp_machine_id, 'SANTIAGO CM', catalog.sort_order, 1
FROM (
  SELECT 'EMBALAJE' AS type_code, 'EMB-01' AS code, 'EMBALAJE' AS name, 'PACKAGING' AS production_area, 201 AS erp_machine_id, 10 AS sort_order
  UNION ALL SELECT 'FLEXOGRAFIA', 'FLEXO-01', 'FLEXO I.', 'PRODUCTION', 101, 20
  UNION ALL SELECT 'FLEXOGRAFIA', 'FLEXO-02', 'FLEXO II.', 'PRODUCTION', 102, 21
  UNION ALL SELECT 'SERIGRAFIA', 'SERI-PULPO', 'PULPO SERIGRAFICO', 'PRODUCTION', 111, 30
  UNION ALL SELECT 'SERIGRAFIA', 'SERI-01', 'SERI I.', 'PRODUCTION', 112, 31
  UNION ALL SELECT 'SERIGRAFIA', 'SERI-02', 'SERI II.', 'PRODUCTION', 113, 32
  UNION ALL SELECT 'SERIGRAFIA', 'SERI-03', 'SERI III.', 'PRODUCTION', 114, 33
  UNION ALL SELECT 'REBOBINADO', 'REBO-02', 'REBO II.', 'REWINDING', 121, 40
  UNION ALL SELECT 'SELLADO', 'SELLA-01', 'SELLADORA I.', 'SEALING', 131, 50
  UNION ALL SELECT 'SELLADO', 'SELLA-02', 'SELLADORA II.', 'SEALING', 132, 51
  UNION ALL SELECT 'SELLADO', 'SELLA-04', 'SELLADORA IV.', 'SEALING', 134, 52
  UNION ALL SELECT 'SELLADO', 'SELLA-05', 'SELLADORA V.', 'SEALING', 135, 53
  UNION ALL SELECT 'SELLADO', 'SELLA-06', 'SELLADORA VI.', 'SEALING', 136, 54
) catalog
INNER JOIN production_machine_types pmt ON pmt.code = catalog.type_code
ON DUPLICATE KEY UPDATE
  machine_type_id = VALUES(machine_type_id),
  name = VALUES(name),
  production_area = VALUES(production_area),
  erp_machine_id = VALUES(erp_machine_id),
  plant_label = VALUES(plant_label),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active);
