-- =============================================================================
-- Migración 009: Registro y Control de Colaciones de Operarios
-- =============================================================================

CREATE TABLE IF NOT EXISTS production_operator_lunch_breaks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  shift_date DATE NOT NULL,
  init_id BIGINT NULL,
  worker_id BIGINT NULL,
  operator_name VARCHAR(150) NOT NULL,
  machine_id INT UNSIGNED NULL,
  machine_name VARCHAR(120) NULL,
  start_time DATETIME NOT NULL,
  end_time DATETIME NULL,
  duration_minutes INT UNSIGNED NULL,
  erp_event_id BIGINT NULL,
  comments TEXT NULL,
  created_by VARCHAR(100) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_lunch_date (shift_date),
  KEY idx_lunch_worker (worker_id),
  KEY idx_lunch_init (init_id),
  KEY idx_lunch_machine (machine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
