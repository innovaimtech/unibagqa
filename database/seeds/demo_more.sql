START TRANSACTION;

INSERT IGNORE INTO suppliers (name, is_active) VALUES
  ('Proveedor Demo C', 1),
  ('Proveedor Demo D', 1),
  ('Proveedor Demo E', 1),
  ('Proveedor Demo F', 1),
  ('Proveedor Demo G', 1),
  ('Proveedor Demo H', 1);

INSERT IGNORE INTO skus (code, description, is_active) VALUES
  ('TEL0006', 'PP/NEGRO/C90/100X80X1100', 1),
  ('TEL0007', 'PP/MORADO/P62/100X80X1100', 1),
  ('TEL0008', 'PP/ROJO/RO1/100X80X1100', 1),
  ('TEL0009', 'PP/NARANJO/Y23/100X80X1100', 1),
  ('TEL0011', 'PP/VERDE PISTACHO/G41/100X80X1100', 1),
  ('TEL0034', 'PP/BEIGE/Y26/130X80X1100', 1),
  ('TEL0042', 'PP/FUCSIA/R08/100X80X1100', 1),
  ('TEL0044', 'PP/BLANCO/W80/100X80X1100', 1),
  ('TEL0046', 'PP/AZUL REY/B53/100X80X1100', 1),
  ('TEL0070', 'PP/CELESTE/B50/100X80X1100', 1),
  ('TEL0084', 'PP/BURDEO/R06/100X80X1100', 1),
  ('TEL0122', 'PP/GRIS OSCURO/E72/100X80X1100', 1),
  ('TEL0173', 'PP/AMARILLO/Y20/130X80X1100', 1),
  ('TEL0174', 'PP/VERDE HOJA/G42/130X80X1100', 1),
  ('TEL0197', 'PP/NEGRO/C90/100X80X1000', 1);

INSERT IGNORE INTO purchase_orders (po_code, supplier_id, status)
SELECT 'OC-20001', id, 'OPEN' FROM suppliers WHERE name='Proveedor Demo C' LIMIT 1;
INSERT IGNORE INTO purchase_orders (po_code, supplier_id, status)
SELECT 'OC-20002', id, 'OPEN' FROM suppliers WHERE name='Proveedor Demo D' LIMIT 1;
INSERT IGNORE INTO purchase_orders (po_code, supplier_id, status)
SELECT 'OC-20003', id, 'OPEN' FROM suppliers WHERE name='Proveedor Demo E' LIMIT 1;
INSERT IGNORE INTO purchase_orders (po_code, supplier_id, status)
SELECT 'OC-20004', id, 'OPEN' FROM suppliers WHERE name='Proveedor Demo F' LIMIT 1;
INSERT IGNORE INTO purchase_orders (po_code, supplier_id, status)
SELECT 'OC-20005', id, 'OPEN' FROM suppliers WHERE name='Proveedor Demo G' LIMIT 1;
INSERT IGNORE INTO purchase_orders (po_code, supplier_id, status)
SELECT 'OC-20006', id, 'OPEN' FROM suppliers WHERE name='Proveedor Demo H' LIMIT 1;
INSERT IGNORE INTO purchase_orders (po_code, supplier_id, status)
SELECT 'OC-20007', id, 'OPEN' FROM suppliers WHERE name='Proveedor Ejemplo A' LIMIT 1;
INSERT IGNORE INTO purchase_orders (po_code, supplier_id, status)
SELECT 'OC-20008', id, 'OPEN' FROM suppliers WHERE name='Proveedor Ejemplo B' LIMIT 1;

INSERT IGNORE INTO purchase_order_lines (purchase_order_id, sku_id, ordered_rolls, microns, width_mm, color, meters)
SELECT po.id, s.id, 8, 50, 800, 'Transparente', 1000.00
FROM purchase_orders po, skus s
WHERE po.po_code='OC-20001' AND s.code='TEL0006'
LIMIT 1;
INSERT IGNORE INTO purchase_order_lines (purchase_order_id, sku_id, ordered_rolls, microns, width_mm, color, meters)
SELECT po.id, s.id, 6, 60, 700, 'Blanco', 900.00
FROM purchase_orders po, skus s
WHERE po.po_code='OC-20001' AND s.code='TEL0007'
LIMIT 1;

INSERT IGNORE INTO purchase_order_lines (purchase_order_id, sku_id, ordered_rolls, microns, width_mm, color, meters)
SELECT po.id, s.id, 12, 60, 820, 'Transparente', 1100.00
FROM purchase_orders po, skus s
WHERE po.po_code='OC-20002' AND s.code='TEL0008'
LIMIT 1;

INSERT IGNORE INTO purchase_order_lines (purchase_order_id, sku_id, ordered_rolls, microns, width_mm, color, meters)
SELECT po.id, s.id, 4, 70, 900, 'Negro', 850.00
FROM purchase_orders po, skus s
WHERE po.po_code='OC-20003' AND s.code='TEL0009'
LIMIT 1;
INSERT IGNORE INTO purchase_order_lines (purchase_order_id, sku_id, ordered_rolls, microns, width_mm, color, meters)
SELECT po.id, s.id, 3, 50, 750, 'Azul', 950.00
FROM purchase_orders po, skus s
WHERE po.po_code='OC-20003' AND s.code='TEL0011'
LIMIT 1;

INSERT IGNORE INTO purchase_order_lines (purchase_order_id, sku_id, ordered_rolls, microns, width_mm, color, meters)
SELECT po.id, s.id, 10, 40, 600, 'Transparente', 1200.00
FROM purchase_orders po, skus s
WHERE po.po_code='OC-20004' AND s.code='TEL0034'
LIMIT 1;

INSERT IGNORE INTO purchase_order_lines (purchase_order_id, sku_id, ordered_rolls, microns, width_mm, color, meters)
SELECT po.id, s.id, 5, 50, 650, 'Blanco', 1000.00
FROM purchase_orders po, skus s
WHERE po.po_code='OC-20005' AND s.code='TEL0042'
LIMIT 1;
INSERT IGNORE INTO purchase_order_lines (purchase_order_id, sku_id, ordered_rolls, microns, width_mm, color, meters)
SELECT po.id, s.id, 2, 12, 500, 'Transparente', 2000.00
FROM purchase_orders po, skus s
WHERE po.po_code='OC-20005' AND s.code='TEL0044'
LIMIT 1;

INSERT IGNORE INTO purchase_order_lines (purchase_order_id, sku_id, ordered_rolls, microns, width_mm, color, meters)
SELECT po.id, s.id, 7, 15, 520, 'Transparente', 1800.00
FROM purchase_orders po, skus s
WHERE po.po_code='OC-20006' AND s.code='TEL0046'
LIMIT 1;

INSERT IGNORE INTO purchase_order_lines (purchase_order_id, sku_id, ordered_rolls, microns, width_mm, color, meters)
SELECT po.id, s.id, 9, 60, 720, 'Blanco', 900.00
FROM purchase_orders po, skus s
WHERE po.po_code='OC-20007' AND s.code='TEL0070'
LIMIT 1;

INSERT IGNORE INTO purchase_order_lines (purchase_order_id, sku_id, ordered_rolls, microns, width_mm, color, meters)
SELECT po.id, s.id, 11, 50, 800, 'Transparente', 1000.00
FROM purchase_orders po, skus s
WHERE po.po_code='OC-20008' AND s.code='TEL0084'
LIMIT 1;

INSERT IGNORE INTO rolls (
  roll_code, sku_id, warehouse_id, weight_kg, microns, width_mm, color, meters, status,
  purchase_order_id, purchase_order_line_id, supplier_id
)
SELECT
  'R-OC20003-001', s.id, w.id, 25.300, pol.microns, pol.width_mm, pol.color, pol.meters, 'RECEIVED',
  po.id, pol.id, po.supplier_id
FROM purchase_orders po
JOIN purchase_order_lines pol ON pol.purchase_order_id = po.id
JOIN skus s ON s.id = pol.sku_id
JOIN warehouses w ON w.code = 100
WHERE po.po_code = 'OC-20003' AND s.code = 'TEL0009'
LIMIT 1;

INSERT IGNORE INTO rolls (
  roll_code, sku_id, warehouse_id, weight_kg, microns, width_mm, color, meters, status,
  purchase_order_id, purchase_order_line_id, supplier_id
)
SELECT
  'R-OC20003-002', s.id, w.id, 25.910, pol.microns, pol.width_mm, pol.color, pol.meters, 'RECEIVED',
  po.id, pol.id, po.supplier_id
FROM purchase_orders po
JOIN purchase_order_lines pol ON pol.purchase_order_id = po.id
JOIN skus s ON s.id = pol.sku_id
JOIN warehouses w ON w.code = 100
WHERE po.po_code = 'OC-20003' AND s.code = 'TEL0009'
LIMIT 1;

INSERT IGNORE INTO rolls (
  roll_code, sku_id, warehouse_id, weight_kg, microns, width_mm, color, meters, status,
  purchase_order_id, purchase_order_line_id, supplier_id
)
SELECT
  'R-OC20004-001', s.id, w.id, 18.400, pol.microns, pol.width_mm, pol.color, pol.meters, 'RECEIVED',
  po.id, pol.id, po.supplier_id
FROM purchase_orders po
JOIN purchase_order_lines pol ON pol.purchase_order_id = po.id
JOIN skus s ON s.id = pol.sku_id
JOIN warehouses w ON w.code = 200
WHERE po.po_code = 'OC-20004' AND s.code = 'TEL0034'
LIMIT 1;

INSERT IGNORE INTO rolls (
  roll_code, sku_id, warehouse_id, weight_kg, microns, width_mm, color, meters, status,
  purchase_order_id, purchase_order_line_id, supplier_id
)
SELECT
  'R-OC20004-002', s.id, w.id, 18.250, pol.microns, pol.width_mm, pol.color, pol.meters, 'RECEIVED',
  po.id, pol.id, po.supplier_id
FROM purchase_orders po
JOIN purchase_order_lines pol ON pol.purchase_order_id = po.id
JOIN skus s ON s.id = pol.sku_id
JOIN warehouses w ON w.code = 200
WHERE po.po_code = 'OC-20004' AND s.code = 'TEL0034'
LIMIT 1;

UPDATE purchase_orders po
JOIN (
  SELECT po2.id AS id,
         COALESCE(SUM(pol.ordered_rolls),0) AS ordered_rolls,
         (SELECT COUNT(*) FROM rolls r WHERE r.purchase_order_id = po2.id) AS received_rolls
  FROM purchase_orders po2
  LEFT JOIN purchase_order_lines pol ON pol.purchase_order_id = po2.id
  WHERE po2.po_code IN ('OC-20003','OC-20004')
  GROUP BY po2.id
) x ON x.id = po.id
SET po.status = CASE
  WHEN x.ordered_rolls > 0 AND x.received_rolls >= x.ordered_rolls THEN 'COMPLETE'
  WHEN x.received_rolls > 0 THEN 'PARTIAL'
  ELSE 'OPEN'
END;

COMMIT;
