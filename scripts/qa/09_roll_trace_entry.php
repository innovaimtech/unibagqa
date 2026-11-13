<?php

declare(strict_types=1);

// QA · Toda bobina que sale de una máquina queda con su código en bodega (traceability_warehouse_entries). Solo SQLite.

require_once __DIR__ . '/../../src/bootstrap.php';

$fail = 0;
$total = 0;
$check = function (string $name, bool $ok, string $extra = '') use (&$fail, &$total): void {
    $total++;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . ($ok || $extra === '' ? '' : ' → ' . $extra) . "\n";
};

$trz = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$trz->exec("
CREATE TABLE warehouses (id INTEGER PRIMARY KEY, code TEXT, name TEXT, erp_storehouse_id INT);
CREATE TABLE skus (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT, description TEXT);
CREATE TABLE rolls (id INTEGER PRIMARY KEY AUTOINCREMENT, roll_code TEXT UNIQUE, sku_id INT, warehouse_id INT, weight_kg REAL, received_qty REAL, reception_mode TEXT,
    microns INT, width_mm INT, meters REAL, status TEXT, current_work_order_id INT, parent_roll_id INT);
CREATE TABLE traceability_warehouse_entries (id INTEGER PRIMARY KEY AUTOINCREMENT, traceability_code TEXT UNIQUE, entity_type TEXT, entity_id INT, warehouse_id INT NOT NULL,
    warehouse_name TEXT NOT NULL, item_id INT, item_code TEXT NOT NULL, item_description TEXT NOT NULL, quantity REAL, unit_name TEXT, package_number INT, total_packages INT,
    weight_kg REAL, width_mm INT, microns INT, meters REAL, order_ref TEXT, operator_name TEXT NOT NULL, comments TEXT, status TEXT, created_at TEXT);
CREATE TABLE movements (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INT, movement_type TEXT, payload TEXT);
INSERT INTO warehouses VALUES (5, '500', '500 (PRODUCCIÓN - BODEGA)', 8), (18, '3000', '3000 INSUMOS EN PRODUCCION', 16), (30, '999', 'SIN ERP', NULL);
INSERT INTO skus (code, description) VALUES ('BOL0864', 'BOLSA IMPRESA'), ('TEL0045', 'TELA 45');
INSERT INTO rolls (roll_code, sku_id, warehouse_id, weight_kg, received_qty, width_mm, meters, status) VALUES ('ROLL-SE-04474', 1, 5, 182.5, 1, 800, 2400, 'RECEIVED');
INSERT INTO rolls (roll_code, sku_id, warehouse_id, weight_kg, received_qty, reception_mode, status) VALUES ('TEL0045-SOB-261112-BB31', 2, 18, 12.3, 0.4, 'SOBRANTE', 'RECEIVED');
INSERT INTO rolls (roll_code, sku_id, warehouse_id, weight_kg, received_qty, status) VALUES ('BOB-TEL0045-261112-3D57', 2, 18, 20, 1, 'IN_PROCESS');
INSERT INTO rolls (roll_code, sku_id, warehouse_id, weight_kg, received_qty, status) VALUES ('ROLL-X-1', 2, 30, 20, 1, 'RECEIVED');
");

$id = RollTraceabilityEntry::sync($trz, 1, 'Operador Flexo', ['order_ref' => 'OT #8123', 'comments' => 'Impresa desde RB-2-001']);
$e = $trz->query("SELECT * FROM traceability_warehouse_entries WHERE entity_type = 'ROLL' AND entity_id = 1")->fetch();
$check('bobina impresa: queda con su código en la bodega del ERP (500 → 8)', $id > 0 && $e !== false && $e['traceability_code'] === 'ROLL-SE-04474'
    && (int)$e['warehouse_id'] === 8 && $e['warehouse_name'] === '500 (PRODUCCIÓN - BODEGA)' && $e['status'] === 'IN_WAREHOUSE', json_encode($e));
$check('bobina impresa: SKU, kilos, ancho, metros, OT, comentario y operador', $e['item_code'] === 'BOL0864' && (float)$e['weight_kg'] === 182.5
    && (int)$e['width_mm'] === 800 && (float)$e['meters'] === 2400.0 && $e['order_ref'] === 'OT #8123' && $e['comments'] === 'Impresa desde RB-2-001'
    && $e['operator_name'] === 'Operador Flexo' && $e['created_at'] !== null);
$again = RollTraceabilityEntry::sync($trz, 1, 'Otro');
$check('si se vuelve a llamar no duplica el código y conserva OT y comentario', $again === $id
    && (int)$trz->query("SELECT COUNT(*) FROM traceability_warehouse_entries WHERE traceability_code = 'ROLL-SE-04474'")->fetchColumn() === 1
    && $trz->query("SELECT order_ref FROM traceability_warehouse_entries WHERE id = $id")->fetchColumn() === 'OT #8123');

RollTraceabilityEntry::sync($trz, 2, 'Operador Seri', ['comments' => 'Sobrante de TEL0045-261110']);
$s = $trz->query("SELECT * FROM traceability_warehouse_entries WHERE entity_id = 2")->fetch();
$check('sobrante: queda con su código en la 3000 (ERP 16) con su cantidad', $s !== false && (int)$s['warehouse_id'] === 16 && (float)$s['quantity'] === 0.4 && $s['status'] === 'IN_WAREHOUSE');

RollTraceabilityEntry::sync($trz, 3);
$p = $trz->query("SELECT * FROM traceability_warehouse_entries WHERE entity_id = 3")->fetch();
$check('bobina en proceso: el código queda «en proceso», no en bodega', $p !== false && $p['status'] === 'IN_PROCESS' && $p['operator_name'] === 'Sistema');

RollTraceabilityEntry::sync($trz, 4);
$x = $trz->query("SELECT warehouse_id FROM traceability_warehouse_entries WHERE entity_id = 4")->fetchColumn();
$check('bodega sin id del ERP: usa el id local', (int)$x === 30);

$check('bobina que no existe: no hace nada', RollTraceabilityEntry::sync($trz, 999) === 0);
$check('si falla, syncSafe no corta el proceso (devuelve 0)', RollTraceabilityEntry::syncSafe(new PDO('sqlite::memory:'), 1) === 0);

// Los procesos que crean bobinas llaman al helper justo después de crearlas.
$pm = (string)file_get_contents(__DIR__ . '/../../src/Services/Production/ProductionMaterialsService.php');
$rw = (string)file_get_contents(__DIR__ . '/../../src/Services/Production/RewindAndPackagingService.php');
$check('Flexo/Serigrafía: la bobina impresa (ROLL-SE) crea su código', (bool)preg_match("/childId = \\(int\\)\\\$this->trzPdo->lastInsertId\\(\\);\\s*\\/\\/[^\\n]*\\n\\s*RollTraceabilityEntry::syncSafe\\(\\\$this->trzPdo, \\\$childId/", $pm));
$check('Bajar bobina: el sobrante (-SOB-) crea su código', (bool)preg_match("/newRollId = \\(int\\)\\\$this->trzPdo->lastInsertId\\(\\);\\s*\\/\\/[^\\n]*\\n\\s*RollTraceabilityEntry::syncSafe\\(\\\$this->trzPdo, \\\$newRollId/", $pm));
$check('Rebobinadora: manillas, cuerpos y empalmes usan el helper (sin INSERT que calle errores)', substr_count($rw, 'RollTraceabilityEntry::syncSafe($this->trzPdo, $childRollId') === 2
    && !str_contains($rw, 'INSERT INTO traceability_warehouse_entries'));

echo "\n" . ($fail === 0 ? "CÓDIGOS DE BOBINAS OK ($total)" : "CÓDIGOS DE BOBINAS: $fail de $total con error") . "\n";
exit($fail === 0 ? 0 : 1);
