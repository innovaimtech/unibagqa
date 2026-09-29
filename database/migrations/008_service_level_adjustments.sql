CREATE TABLE IF NOT EXISTS service_level_adjustments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  despacho_id BIGINT UNSIGNED NOT NULL,
  orders_delivery_id BIGINT UNSIGNED NULL,
  doc_number VARCHAR(60) NULL,
  cc_number VARCHAR(60) NULL,
  original_committed_date DATE NULL,
  adjusted_committed_date DATE NULL,
  is_justified TINYINT(1) NOT NULL DEFAULT 1,
  reason_category VARCHAR(120) NOT NULL,
  reason_details TEXT NULL,
  updated_by VARCHAR(120) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sl_adjust_despacho (despacho_id),
  KEY idx_sl_adjust_dates (adjusted_committed_date, is_justified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
