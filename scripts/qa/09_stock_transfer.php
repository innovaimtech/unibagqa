<?php

declare(strict_types=1);

// QA · Bodega → Traspasos: códigos de varias bodegas a un destino, con stock y kardex del ERP; destino 3000 por aceptar.
// Solo SQLite en memoria.

require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/lib_erp_sqlite.php';

$fail = 0;
$total = 0;
$check = function (string $name, bool $ok, string $extra = '') use (&$fail, &$total): void {
    $total++;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . ($ok || $extra === '' ? '' : ' → ' . $extra) . "\n";
};
$err = static function (callable $f): string {
    try {
        $f();
        return '';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
};

$erp = qaErpSqlite();
$erp->exec("ALTER TABLE item ADD COLUMN item_number TEXT DEFAULT '';
INSERT INTO company_shops_storehouses (id, st_name) VALUES (5, '100 MP PLA'), (11, '200 MP PP');
INSERT INTO item (id, item_title, item_number_prod) VALUES (500, 'BOLSA TOTTUS', 'BOL0352'), (600, 'BOLSA ALGODON', 'BOL0600'), (700, 'TELA PLA', 'TEL0180'), (800, 'BOLSA BLANCA', 'BOL0800');
INSERT INTO orders (id, req_number, req_company_id, req_shop_id) VALUES (70, '26-00070-1', 20010, 30010);
INSERT INTO item_shops_storehouses VALUES (500,30010,14,0,0,150,0,70),(500,30010,1,0,0,30,0,0),(600,30010,8,0,0,200,0,0),(700,30010,5,0,0,3,0,0),(800,30010,1,0,0,100,0,0);
CREATE TABLE user (id INTEGER PRIMARY KEY, user_login TEXT, user_pass TEXT, user_status INT, user_type INT, user_firstname TEXT, user_lastname TEXT);
CREATE TABLE user_group (user_id INT, group_id INT);
INSERT INTO user VALUES (4077, 'HECTOR', '" . md5('clave1') . "', 1, 0, 'Héctor', 'Supervisor'), (4500, 'OPERADOR', '" . md5('clave2') . "', 1, 0, 'Juan', 'Operador');
INSERT INTO user_group VALUES (4077, 31);");

$trz = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$trz->exec("
CREATE TABLE warehouses (id INTEGER PRIMARY KEY, code TEXT, name TEXT, erp_storehouse_id INT);
INSERT INTO warehouses VALUES (1,'100','Bodega 100',5),(2,'3000','Bodega 3000',16),(3,'1000','Bodega 1000',14),(4,'400','Bodega 400',1),(5,'500','Bodega 500',8),(6,'700','Bodega 700',10);
CREATE TABLE boxes (id INTEGER PRIMARY KEY AUTOINCREMENT, box_code TEXT UNIQUE, work_order_id INT, source_roll_id INT, pallet_id INT, final_sku TEXT NOT NULL, units_qty REAL NOT NULL, destination_mode TEXT NOT NULL DEFAULT 'BODEGA', customer_order_ref TEXT, warehouse_id INT, operator_name TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'CREATED', created_at TEXT);
CREATE TABLE traceability_warehouse_entries (id INTEGER PRIMARY KEY AUTOINCREMENT, traceability_code TEXT UNIQUE, batch_id TEXT, entity_type TEXT, entity_id INT, warehouse_id INT NOT NULL, warehouse_name TEXT NOT NULL, item_id INT, item_code TEXT NOT NULL, item_description TEXT NOT NULL, quantity REAL, quantity_initial REAL, unit_name TEXT, box_count INT, order_ref TEXT, operator_name TEXT NOT NULL, comments TEXT, status TEXT);
CREATE TABLE movements (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INT, movement_type TEXT, from_warehouse_id INT, to_warehouse_id INT, payload TEXT);
CREATE TABLE skus (id INTEGER PRIMARY KEY, code TEXT);
CREATE TABLE rolls (id INTEGER PRIMARY KEY, roll_code TEXT, sku_id INT, warehouse_id INT, status TEXT, current_work_order_id INT, received_qty REAL);
INSERT INTO skus VALUES (1, 'TEL0180');
INSERT INTO rolls VALUES (1, 'TEL-0001', 1, 1, 'RECEIVED', NULL, 1);
");
$svc = new StockTransferService($trz, $erp);
$svc->ensureSchema();

$box = function (string $code, int $item, float $units, int $st, int $order = 0, string $cc = '', string $status = 'IN_WAREHOUSE') use ($trz): void {
    $trz->prepare("INSERT INTO boxes (box_code, final_sku, units_qty, units_initial, customer_order_ref, operator_name, status, erp_item_id, erp_st_id, erp_order_id, source_kind)
        VALUES (?, 'SKU', ?, ?, ?, 'QA', ?, ?, ?, ?, 'ETIQUETA')")->execute([$code, $units, $units, $cc ?: null, $status, $item, $st, $order ?: null]);
    $id = (int)$trz->lastInsertId();
    $trz->prepare("INSERT INTO traceability_warehouse_entries (traceability_code, entity_type, entity_id, warehouse_id, warehouse_name, item_id, item_code,
        item_description, quantity, quantity_initial, unit_name, order_ref, operator_name, status) VALUES (?, 'BOX', ?, ?, ?, ?, 'SKU', 'Bolsa', ?, ?, 'Unid.', ?, 'QA', 'IN_WAREHOUSE')")
        ->execute([$code, $id, $st, 'Bodega ' . $st, $item, $units, $units, $cc ?: null]);
};
$box('BX-A1', 500, 100, 14, 70, '26-00070-1');
$box('BX-A2', 500, 50, 14, 70, '26-00070-1');
$box('BX-B1', 500, 30, 1);
$box('BX-MAQ', 500, 10, 1, 0, '', 'IN_MACHINE');
$trz->exec("INSERT INTO traceability_warehouse_entries (traceability_code, entity_type, entity_id, warehouse_id, warehouse_name, item_id, item_code, item_description,
    quantity, quantity_initial, unit_name, operator_name, status) VALUES ('PL-0001', 'PALLET', NULL, 8, '500 BODEGA', 600, 'BOL0600', 'Bolsa algodón', 200, 200, 'Unid.', 'QA', 'IN_WAREHOUSE'),
    ('TEL-0001', 'ROLL', 1, 5, 'Bodega 100', 700, 'TEL0180', 'Tela', 1, 1, 'BOB', 'QA', 'IN_WAREHOUSE')");

$stock = fn(int $item, int $st, int $order = 0): float => round((float)$erp->query("SELECT COALESCE(SUM(iss_inventory),0) FROM item_shops_storehouses WHERE item_id=$item AND st_id=$st AND iss_order_id=$order")->fetchColumn(), 3);
$kardex = fn(string $type): int => (int)$erp->query("SELECT COUNT(*) FROM tran_data WHERE tran_type = '$type'")->fetchColumn();
$docs = fn(): int => (int)$erp->query('SELECT COUNT(*) FROM storehousechanges WHERE strc_status = 2')->fetchColumn();

// Revisión
$rows = $svc->inspect(['bx-a1', "BX'A2", 'BX-B1', 'PL-0001', 'TEL-0001', 'BX-MAQ', 'NOEXISTE']);
$byCode = array_column($rows, null, 'code');
$check('lee cajas, pallet y bobina de distintas bodegas (y la pistola con «\'»)', $byCode['BX-A1']['problem'] === '' && $byCode['BX-A2']['problem'] === ''
    && $byCode['PL-0001']['from_st_id'] === 8 && $byCode['TEL-0001']['kind'] === 'ROLL' && $byCode['TEL-0001']['from_st_id'] === 5 && $byCode['TEL-0001']['qty'] === 1.0,
    json_encode(array_map(fn($r) => [$r['code'], $r['kind'], $r['from_st_id'], $r['problem']], $rows), JSON_UNESCAPED_UNICODE));
$check('la caja con CC usa la fila de stock de su CC', $byCode['BX-A1']['order_id'] === 70);
$check('no acepta cajas en máquina ni códigos inexistentes', str_contains($byCode['BX-MAQ']['problem'], 'máquina') && $byCode['NOEXISTE']['problem'] === 'No existe.');
$check('no deja traspasar a la misma bodega', str_contains($svc->inspect(['BX-B1'], 1)[0]['problem'], 'Ya está'));
$check('pide bodega de destino', str_contains($err(fn() => $svc->create(['BX-B1'], 999, ['name' => 'Juan'])), 'destino'));
$check('si un código no sirve no se registra nada', str_contains($err(fn() => $svc->create(['BX-B1', 'BX-MAQ'], 10, ['name' => 'Juan'])), 'BX-MAQ')
    && (int)$trz->query('SELECT COUNT(*) FROM stock_transfers')->fetchColumn() === 0);

// 1. Cinco códigos de cuatro bodegas a la 700: se ejecuta al tiro.
$r = $svc->create(['BX-A1', 'BX-A2', 'BX-B1', 'PL-0001', 'TEL-0001'], 10, ['user_id' => 4500, 'name' => 'Juan Operador'], 'Pedido de bodega 700');
$t = $svc->transfer($r['id']);
$check('a otra bodega queda realizado y guarda quién lo hizo', $r['status'] === 'DONE' && $t['sent_by_name'] === 'Juan Operador' && (int)$t['sent_by_user_id'] === 4500);
$check('stock del ERP: cada código sale de su bodega y llega a la 700', $stock(500, 14, 70) === 0.0 && $stock(500, 10, 70) === 150.0 && $stock(500, 1) === 0.0
    && $stock(500, 10) === 30.0 && $stock(600, 8) === 0.0 && $stock(600, 10) === 200.0 && $stock(700, 5) === 2.0 && $stock(700, 10) === 1.0,
    json_encode([$stock(500, 10, 70), $stock(500, 10), $stock(600, 10), $stock(700, 10)]));
$check('un documento de traspaso por artículo, bodega y CC (2 cajas del mismo CC = 1 TR)', $docs() === 4, (string)$docs());
$check('kardex sthdown/sthup por cada documento', $kardex('sthdown') === 4 && $kardex('sthup') === 4);
$items = $svc->items($r['id']);
$check('cada código guarda su documento del ERP', count(array_filter($items, fn($i) => str_starts_with((string)$i['erp_doc'], 'TR'))) === 5
    && $items[0]['erp_doc'] === $items[1]['erp_doc']);
$boxA = $trz->query("SELECT * FROM boxes WHERE box_code = 'BX-A1'")->fetch();
$check('trazabilidad: cajas, etiquetas y bobina quedan en la 700', (int)$boxA['erp_st_id'] === 10 && (int)$boxA['warehouse_id'] === 6
    && (int)$trz->query("SELECT COUNT(*) FROM traceability_warehouse_entries WHERE warehouse_id = 10")->fetchColumn() === 5
    && (int)$trz->query('SELECT warehouse_id FROM rolls WHERE id = 1')->fetchColumn() === 6);
$check('movimientos TRANSFER con el número de traspaso', (int)$trz->query("SELECT COUNT(*) FROM movements WHERE movement_type = 'TRANSFER' AND payload LIKE '%\"transfer_id\":" . $r['id'] . "%'")->fetchColumn() === 4);

// 2. A la 3000: queda por aceptar y no mueve stock.
$box('BX-C1', 800, 40, 1);
$box('BX-C2', 800, 60, 1);
$p = $svc->create(['BX-C1', 'BX-C2'], 16, ['user_id' => 4500, 'name' => 'Juan Operador']);
$check('a la 3000 queda por aceptar sin mover stock', $p['status'] === 'PENDING' && $stock(800, 1) === 100.0 && $stock(800, 16) === 0.0 && $svc->pendingCount() === 1);
$check('un código en tránsito no se puede volver a traspasar', str_contains($svc->inspect(['BX-C1'], 10)[0]['problem'], 'por aceptar'));
$check('un usuario que no es supervisor no acepta', str_contains($err(fn() => $svc->accept($p['id'], 'OPERADOR', 'clave2')), 'no válidos'));
$check('clave equivocada no acepta', str_contains($err(fn() => $svc->accept($p['id'], 'HECTOR', 'mala')), 'no válidos') && $stock(800, 16) === 0.0);
$a = $svc->accept($p['id'], 'HECTOR', 'clave1');
$check('el supervisor acepta: se mueve el stock y queda quién aceptó', $a['status'] === 'DONE' && $a['accepted_by_name'] === 'Héctor Supervisor'
    && (int)$a['accepted_by_user_id'] === 4077 && $stock(800, 1) === 0.0 && $stock(800, 16) === 100.0);
$check('no se acepta dos veces', str_contains($err(fn() => $svc->accept($p['id'], 'HECTOR', 'clave1')), 'no está por aceptar'));

// 3. Rechazo: libera los códigos.
$box('BX-D1', 500, 30, 10);
$q = $svc->create(['BX-D1'], 16, ['name' => 'Juan Operador']);
$check('rechazar pide motivo', str_contains($err(fn() => $svc->reject($q['id'], ' ', 'HECTOR', 'clave1')), 'motivo'));
$rj = $svc->reject($q['id'], 'No corresponde', 'HECTOR', 'clave1');
$check('rechazado: queda quién y por qué, y el código se libera sin mover stock', $rj['status'] === 'REJECTED' && $rj['reject_reason'] === 'No corresponde'
    && $rj['accepted_by_name'] === 'Héctor Supervisor' && $svc->inspect(['BX-D1'], 16)[0]['problem'] === '' && $stock(500, 10) === 30.0);

// 4. Error del ERP y reintento sin duplicar.
$box('BX-E1', 600, 50, 10);
$e = $svc->create(['BX-E1'], 16, ['name' => 'Juan Operador']);
$erp->exec('UPDATE item_shops_storehouses SET iss_inventory = 20 WHERE item_id = 600 AND st_id = 10');
$msg = $err(fn() => $svc->accept($e['id'], 'HECTOR', 'clave1'));
$check('sin stock suficiente en el ERP queda con error y no mueve nada', str_contains($msg, 'El ERP tiene 20') && $svc->transfer($e['id'])['status'] === 'ERROR'
    && $stock(600, 16) === 0.0, $msg);
$erp->exec('UPDATE item_shops_storehouses SET iss_inventory = 250 WHERE item_id = 600 AND st_id = 10');
$before = $docs();
$svc->retry($e['id']);
$check('reintentar lo completa', $svc->transfer($e['id'])['status'] === 'DONE' && $stock(600, 16) === 50.0 && $docs() === $before + 1);
$check('reintentar algo ya realizado no hace nada', str_contains($err(fn() => $svc->retry($e['id'])), 'no tiene un error') && $docs() === $before + 1);

// 5. Historial.
$h = $svc->history();
$check('historial con estado, quién envió y quién aceptó', count($h) === 4 && $h[0]['status'] === 'DONE' && (int)$h[3]['codes'] === 5 && (float)$h[3]['units'] === 381.0);

// 6. Pantallas (HTML) sin servidor.
$send = stockTransferLayout('traspasar', '', '', stockTransferSendPage($svc->inspect(['BX-D1', 'NOEXISTE']), $svc->storehouses(), 0), 0);
$check('traspasar: casillas de escaneo, lista con inválidos en rojo y bodega de destino', str_contains($send, 'class="ca-card ca-slots"') && str_contains($send, 'unibag-scan-slots.js')
    && substr_count($send, 'class="bad"') === 1 && str_contains($send, 'name="to_st"') && str_contains($send, 'Traspasar (1)') && str_contains($send, 'requiere aceptación'));
$box('BX-F1', 500, 5, 10);
$svc->create(['BX-F1'], 16, ['name' => 'Juan Operador']);
$acc = stockTransferLayout('aceptar', '', '', stockTransferAcceptPage($svc->pending()), $svc->pendingCount());
$check('por aceptar: cada traspaso con sus códigos, usuario y clave para aceptar o rechazar', str_contains($acc, 'BX-F1') && str_contains($acc, 'name="password"')
    && str_contains($acc, '/stock/traspasos/rechazar') && str_contains($acc, 'st-badge'));
$det = stockTransferDetailPage($svc->transfer($r['id']), $svc->items($r['id']));
$check('detalle con documentos del ERP', str_contains($det, 'TR') && str_contains($det, 'Juan Operador'));
$noHelp = static fn(string $h): bool => !str_contains($h, 'placeholder=') && !str_contains($h, 'ca-help') && !str_contains($h, 'ca-sub');
$check('sin subtítulos, textos de ayuda ni placeholders', $noHelp($send) && $noHelp($acc) && $noHelp($det));

echo "\n" . ($fail === 0 ? "TRASPASOS OK ($total)" : "TRASPASOS: $fail de $total con error") . "\n";
exit($fail === 0 ? 0 : 1);
