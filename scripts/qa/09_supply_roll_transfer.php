<?php
declare(strict_types=1);
// Solicitudes MP: despacho de bobinas de la Bodega 100 a la 3000 (ERP 16) con kardex y códigos de trazabilidad.
// ERP y TRZ en SQLite (memoria). Revisa: fila de stock general (no la de CC), kardex sthdown/sthup sin CC,
// documento como los nativos, bobina y código en la 3000, movimiento, reintento sin duplicar y recepción.
require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/lib_erp_sqlite.php';

$fail = 0;
$check = function (string $name, bool $ok, string $extra = '') use (&$fail): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . ($extra !== '' ? ' (' . $extra . ')' : '') . "\n";
    if (!$ok) {
        $fail++;
    }
};
$err = function (callable $f): string { try { $f(); } catch (Throwable $e) { return $e->getMessage(); } return ''; };

$erp = qaErpSqlite();
$erp->exec("ALTER TABLE storehousechanges ADD COLUMN strc_updusr INT; ALTER TABLE storehousechanges ADD COLUMN strc_upddat INT;
CREATE TABLE company_shops (id INTEGER PRIMARY KEY, shop_company_id INT);
INSERT INTO company_shops VALUES (30010, 20010);
INSERT INTO company_shops_storehouses (id,st_name) VALUES (5,'100 (MP PLA)');
CREATE TABLE prod_worker_ot (id INTEGER PRIMARY KEY);
INSERT INTO item (id,item_title,item_number_prod) VALUES (64,'PLA/VERDE/G41/100X85X1400','TEL0064');
-- Bodega 100: fila general con 10 bobinas y una fila suelta de una CC.
INSERT INTO item_shops_storehouses VALUES (64,30010,5,0,0,10,0,0),(64,30010,5,0,0,0,0,7823);
-- Bodega 3000: fila general con 3 y fila de CC con 0 (caso real de TIN0082 / TEL0007).
INSERT INTO item_shops_storehouses VALUES (64,30010,16,0,0,3,0,0),(64,30010,16,0,0,0,0,7823);");

$trz = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$trz->exec("CREATE TABLE warehouses (id INTEGER PRIMARY KEY, code TEXT, name TEXT, erp_storehouse_id INT);
INSERT INTO warehouses VALUES (1,'100','100 (MP PLA)',5),(18,'3000','3000 INSUMOS EN PRODUCCION',16);
CREATE TABLE skus (id INTEGER PRIMARY KEY, code TEXT);
INSERT INTO skus VALUES (1,'TEL0064');
CREATE TABLE rolls (id INTEGER PRIMARY KEY, roll_code TEXT, sku_id INT, warehouse_id INT, status TEXT, current_work_order_id INT, received_qty REAL, weight_kg REAL, meters REAL);
INSERT INTO rolls VALUES (1,'RB-1',1,1,'RECEIVED',NULL,1,119,1400),(2,'RB-2',1,1,'RECEIVED',NULL,1,119,1400);
CREATE TABLE traceability_warehouse_entries (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INT, warehouse_id INT, warehouse_name TEXT);
INSERT INTO traceability_warehouse_entries (entity_type,entity_id,warehouse_id,warehouse_name) VALUES ('ROLL',1,5,'100 (MP PLA)'),('ROLL',2,5,'100 (MP PLA)');
CREATE TABLE movements (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INT, movement_type TEXT, from_warehouse_id INT, to_warehouse_id INT, payload TEXT);
CREATE TABLE events (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, payload TEXT);");

$svc = new MaterialSupplyService($erp, $trz);
$svc->ensureSchema();

$reqKey = str_repeat('a', 32);
$ctx = ['req_shop_id' => 30010, 'req_company_id' => 20010, 'order_id' => 7823, 'req_number' => '26-00900-1',
    'win_equipoid' => 30, 'machine_name' => 'FLEXO III', 'agenda_id' => 0];
$trz->prepare("INSERT INTO production_supply_requests (request_key,erp_target,worker_ot_id,agenda_id,context,item_id,sku,description,
    source_storehouse_id,requested_qty,unit,requested_by,notes,created_at,status)
    VALUES (?,?,1,0,?,64,'TEL0064','PLA verde',5,2,'UN',?,'',?, 'ACCEPTED')")
    ->execute([$reqKey, 'sqlite-test', json_encode($ctx), json_encode(['user_id' => 9, 'name' => 'Operador']), time()]);

$actor = ['user_id' => 4074, 'name' => 'Bodega'];
$svc->scan($reqKey, "RB'1", $actor); // pistola con teclado inglés: ' en vez de -
$svc->scan($reqKey, 'RB-2', $actor);
$check('escaneo normaliza la comilla a guion', (int)$trz->query("SELECT COUNT(*) FROM production_supply_scans WHERE code IN ('RB-1','RB-2')")->fetchColumn() === 2);

$dKey = str_repeat('b', 32);
$svc->dispatch($dKey, $reqKey, 0, '', $actor);

$stock = fn(int $st, int $order) => (float)$erp->query("SELECT iss_inventory FROM item_shops_storehouses WHERE item_id=64 AND st_id=$st AND iss_order_id=$order")->fetchColumn();
$check('bodega 100: la fila general baja de 10 a 8', $stock(5, 0) === 8.0, (string)$stock(5, 0));
$check('bodega 3000: la fila general sube de 3 a 5', $stock(16, 0) === 5.0, (string)$stock(16, 0));
$check('las filas por CC no se tocan (antes se pisaban con el mismo saldo)', $stock(5, 7823) === 0.0 && $stock(16, 7823) === 0.0);

$doc = $erp->query('SELECT * FROM storehousechanges')->fetchAll();
$check('un documento de traspaso, cerrado (estado 2)', count($doc) === 1 && (int)$doc[0]['strc_status'] === 2);
$check('documento sin CC, como los traspasos nativos', (int)$doc[0]['strc_order_id'] === 0 && (int)$doc[0]['strc_order_id_dest'] === 0);
$check('la CC queda en la descripción', str_contains((string)$doc[0]['strc_desc'], '26-00900-1'));
$line = $erp->query('SELECT * FROM storehousechanges_items')->fetchAll();
$check('línea 100 → 3000 por 2', count($line) === 1 && (int)$line[0]['item_st_id'] === 5 && (int)$line[0]['item_st_dest_id'] === 16 && (float)$line[0]['item_amount'] === 2.0);
$k = $erp->query('SELECT tran_type, tran_st_id, tran_currstock, tran_amount, tran_order_id FROM tran_data ORDER BY tran_pos')->fetchAll();
$check('kardex: sthdown en 100 con saldo previo 10', ($k[0]['tran_type'] ?? '') === 'sthdown' && (int)$k[0]['tran_st_id'] === 5 && (float)$k[0]['tran_currstock'] === 10.0 && (float)$k[0]['tran_amount'] === 2.0);
$check('kardex: sthup en 3000 con saldo previo 3', ($k[1]['tran_type'] ?? '') === 'sthup' && (int)$k[1]['tran_st_id'] === 16 && (float)$k[1]['tran_currstock'] === 3.0);
$check('kardex sin CC (iss_order_id 0 = tran_order_id 0)', (int)$k[0]['tran_order_id'] === 0 && (int)$k[1]['tran_order_id'] === 0);

$rolls = $trz->query('SELECT warehouse_id FROM rolls ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$check('las bobinas quedan en la 3000 (id local 18)', array_map('intval', $rolls) === [18, 18]);
$entries = $trz->query("SELECT warehouse_id, warehouse_name FROM traceability_warehouse_entries ORDER BY entity_id")->fetchAll();
$check('los códigos quedan con la bodega ERP 16 y su nombre', (int)$entries[0]['warehouse_id'] === 16 && $entries[0]['warehouse_name'] === '3000 INSUMOS EN PRODUCCION' && (int)$entries[1]['warehouse_id'] === 16);
$mov = $trz->query("SELECT from_warehouse_id, to_warehouse_id FROM movements WHERE movement_type='TRANSFER'")->fetchAll();
$check('un movimiento 100 → 3000 por bobina', count($mov) === 2 && (int)$mov[0]['from_warehouse_id'] === 1 && (int)$mov[0]['to_warehouse_id'] === 18);
$check('solicitud y escaneos quedan despachados',
    $trz->query("SELECT status FROM production_supply_requests")->fetchColumn() === 'DISPATCHED'
    && (int)$trz->query("SELECT COUNT(*) FROM production_supply_scans WHERE state='DISPATCHED'")->fetchColumn() === 2);

// Reintentar el mismo despacho no vuelve a mover nada.
$svc->dispatch($dKey, $reqKey, 0, '', $actor);
$svc->resume($dKey, $actor);
$check('reintentar no duplica stock, kardex ni movimientos',
    $stock(5, 0) === 8.0 && $stock(16, 0) === 5.0 && (int)$erp->query('SELECT COUNT(*) FROM tran_data')->fetchColumn() === 2
    && (int)$trz->query('SELECT COUNT(*) FROM movements')->fetchColumn() === 2);

// Corte a mitad: el documento se creó en el ERP pero falló antes del kardex → al reintentar se completa una sola vez.
$trz->exec("UPDATE rolls SET warehouse_id=1 WHERE id IN (1,2)");
$trz->exec("INSERT INTO rolls VALUES (3,'RB-3',1,1,'RECEIVED',NULL,1,119,1400)");
$trz->exec("INSERT INTO traceability_warehouse_entries (entity_type,entity_id,warehouse_id,warehouse_name) VALUES ('ROLL',3,5,'100 (MP PLA)')");
$req2 = str_repeat('c', 32);
$trz->prepare("INSERT INTO production_supply_requests (request_key,erp_target,worker_ot_id,agenda_id,context,item_id,sku,description,
    source_storehouse_id,requested_qty,unit,requested_by,notes,created_at,status)
    VALUES (?,?,1,0,?,64,'TEL0064','PLA verde',5,1,'UN',?,'',?, 'ACCEPTED')")
    ->execute([$req2, 'sqlite-test', json_encode($ctx), json_encode(['user_id' => 9, 'name' => 'Operador']), time()]);
$svc->scan($req2, 'RB-3', $actor);
$check('sin stock general suficiente no despacha', $err(function () use ($svc, $req2, $actor, $erp) {
    $erp->exec('UPDATE item_shops_storehouses SET iss_inventory=0 WHERE item_id=64 AND st_id=5 AND iss_order_id=0');
    $svc->dispatch(str_repeat('d', 32), $req2, 0, '', $actor);
}) !== '' && $stock(16, 0) === 5.0);
$erp->exec('UPDATE item_shops_storehouses SET iss_inventory=8 WHERE item_id=64 AND st_id=5 AND iss_order_id=0');
$svc->resume(str_repeat('d', 32), $actor);
$check('tras reponer stock, Reintentar completa el despacho', $stock(5, 0) === 7.0 && $stock(16, 0) === 6.0
    && (int)$trz->query("SELECT warehouse_id FROM rolls WHERE id=3")->fetchColumn() === 18);

exit($fail > 0 ? 1 : 0);
