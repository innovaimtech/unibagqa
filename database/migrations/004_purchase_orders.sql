CREATE TABLE IF NOT EXISTS suppliers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_suppliers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  po_code VARCHAR(40) NOT NULL,
  supplier_id INT UNSIGNED NOT NULL,
  status ENUM('OPEN','PARTIAL','COMPLETE') NOT NULL DEFAULT 'OPEN',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_purchase_orders_po_code (po_code),
  KEY idx_purchase_orders_supplier (supplier_id),
  KEY idx_purchase_orders_status (status),
  CONSTRAINT fk_purchase_orders_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_order_lines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_order_id BIGINT UNSIGNED NOT NULL,
  sku_id INT UNSIGNED NOT NULL,
  ordered_rolls INT UNSIGNED NOT NULL DEFAULT 0,
  reception_mode ENUM('QUANTITY','WEIGHT') NOT NULL DEFAULT 'QUANTITY',
  ordered_weight_kg DECIMAL(12,3) NULL,
  microns INT UNSIGNED NULL,
  width_mm INT UNSIGNED NULL,
  color VARCHAR(60) NULL,
  meters DECIMAL(12,2) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pol_po (purchase_order_id),
  KEY idx_pol_sku (sku_id),
  CONSTRAINT fk_pol_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
  CONSTRAINT fk_pol_sku FOREIGN KEY (sku_id) REFERENCES skus(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE rolls
  ADD COLUMN received_qty DECIMAL(12,3) NOT NULL DEFAULT 1.000 AFTER weight_kg,
  ADD COLUMN purchase_order_id BIGINT UNSIGNED NULL,
  ADD COLUMN purchase_order_line_id BIGINT UNSIGNED NULL,
  ADD COLUMN supplier_id INT UNSIGNED NULL,
  ADD KEY idx_rolls_po_id (purchase_order_id),
  ADD KEY idx_rolls_pol_id (purchase_order_line_id),
  ADD KEY idx_rolls_supplier_id (supplier_id),
  ADD CONSTRAINT fk_rolls_po_id FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
  ADD CONSTRAINT fk_rolls_pol_id FOREIGN KEY (purchase_order_line_id) REFERENCES purchase_order_lines(id),
  ADD CONSTRAINT fk_rolls_supplier_id FOREIGN KEY (supplier_id) REFERENCES suppliers(id);

INSERT IGNORE INTO suppliers (name, is_active) VALUES
  ('Proveedor Ejemplo A', 1),
  ('Proveedor Ejemplo B', 1);

INSERT IGNORE INTO purchase_orders (po_code, supplier_id, status) VALUES
  ('OC-10001', 1, 'OPEN'),
  ('OC-10002', 2, 'OPEN');

INSERT IGNORE INTO purchase_order_lines (purchase_order_id, sku_id, ordered_rolls, reception_mode, ordered_weight_kg, microns, width_mm, color, meters) VALUES
  (1, 1, 3, 'QUANTITY', NULL, 50, 800, 'Transparente', 1000.00),
  (1, 2, 0, 'WEIGHT', 1250.000, 60, 700, 'Blanco', 900.00),
  (2, 1, 1, 'QUANTITY', NULL, 50, 800, 'Transparente', 1000.00);
