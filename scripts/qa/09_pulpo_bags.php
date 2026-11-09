<?php
declare(strict_types=1);
// Pulpo Serigráfico: cajas con código al cerrar embalaje, traspaso de cajas a 3000 con su CC, bolsas usadas por caja
// con kardex, anulación, control al cerrar la OT, tinta de serigrafía y flexo por kardex.
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "pdo_sqlite no disponible: prueba omitida\n";
    exit(0);
}
$root = dirname(__DIR__, 2);
require_once $root . '/src/bootstrap.php';
require_once __DIR__ . '/lib_erp_sqlite.php';

$fail = 0;
$check = function (string $name, bool $ok, string $extra = '') use (&$fail): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . ($extra !== '' && !$ok ? " [$extra]" : '') . "\n";
    if (!$ok) {
        $fail++;
    }
};
$err = function (callable $f): string { try { $f(); } catch (Throwable $e) { return $e->getMessage(); } return ''; };

$erp = qaErpSqlite();
$erp->exec("CREATE TABLE prod_worker_init (id INTEGER PRIMARY KEY, win_wrkid INT, win_status INT, win_plantaid INT, win_equipoid INT);
CREATE TABLE prod_worker_ot (id INTEGER PRIMARY KEY, wok_ag_id INT, wok_init_id INT, wok_status INT, wok_enddat INT);
CREATE TABLE prod_agenda (id INTEGER PRIMARY KEY, ag_reqid INT, ag_equipotype_id INT);
CREATE TABLE equipo (id INTEGER PRIMARY KEY, equipo_name TEXT, equipo_type_id INT, equipo_prod_isprinter_seri INT DEFAULT 0);
CREATE TABLE prod_worker_ot_events (id INTEGER PRIMARY KEY AUTOINCREMENT, evt_prod_worker_otid INT, evt_amount REAL, evt_crtdat INT, evt_enddat INT, evt_status INT, evt_type TEXT, evt_comments TEXT, prod_bobina_kg REAL DEFAULT 0, evt_amount_pulpo_frente REAL DEFAULT 0, evt_amount_pulpo_dorso REAL DEFAULT 0, evt_amount_metros_lineales INT DEFAULT 0);
CREATE TABLE prod_worker_ot_defectunits (id INTEGER PRIMARY KEY AUTOINCREMENT, evt_refid INT, evt_status INT, evt_type TEXT, evt_amount REAL);
CREATE TABLE prod_worker_embalajes (id INTEGER PRIMARY KEY, evt_prod_worker_otid INT, evt_reqid INT, req_v2refid INT, req_codigo_cierre TEXT, req_id_bodega INT, req_embalaje_cajas_completas_amt INT, req_embalaje_bolsas_por_caja_amt INT, req_embalaje_caja_final INT, req_embalaje_bolsas_sobrantes_amt INT, req_embalaje_showroom INT);
CREATE TABLE embalaje_mixto (id INTEGER PRIMARY KEY, id_embalaje INT);
CREATE TABLE detalle_mixto (id INTEGER PRIMARY KEY, id_mixto INT, req_number TEXT, id_item INT, total_cajas INT, bolsas_por_caja INT, bolsas_caja_final INT);
INSERT INTO item (id,item_title,item_number_prod) VALUES (20,'BOLSA BLANCA 30X40','BOL0900'),(21,'BOLSA IMPRESA','BOL0901'),(30,'TINTA SERI BLANCA','TIN0100');
INSERT INTO orders (id,req_number,req_company_id,req_shop_id) VALUES (500,'26-00500-1',20010,30010),(600,'26-00600-1',20010,30010);
INSERT INTO orders_items (req_id,item_id) VALUES (500,20),(600,21);
INSERT INTO item_shops_storehouses VALUES (20,30010,14,0,0,600,0,500),(30,30010,16,0,0,20,0,0);
INSERT INTO equipo VALUES (36,'PULPO SERIGRAFICO',11,1),(40,'SERI II',11,1);
INSERT INTO prod_worker_init VALUES (1,77,1,1,36);
INSERT INTO prod_agenda VALUES (70,600,11);
INSERT INTO prod_worker_ot (id,wok_ag_id,wok_init_id,wok_status) VALUES (7001,70,1,1);
INSERT INTO prod_worker_embalajes VALUES (1,100,500,900,'10',14,2,200,200,0,0);");

$trz = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$trz->exec("CREATE TABLE boxes (id INTEGER PRIMARY KEY AUTOINCREMENT, box_code TEXT UNIQUE, work_order_id INT, source_roll_id INT, pallet_id INT, final_sku TEXT NOT NULL, units_qty REAL NOT NULL, destination_mode TEXT NOT NULL DEFAULT 'BODEGA', customer_order_ref TEXT, warehouse_id INT, operator_name TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'CREATED', created_at TEXT);
CREATE TABLE traceability_warehouse_entries (id INTEGER PRIMARY KEY AUTOINCREMENT, traceability_code TEXT UNIQUE, batch_id TEXT, entity_type TEXT, entity_id INT, warehouse_id INT NOT NULL, warehouse_name TEXT NOT NULL, item_id INT, item_code TEXT NOT NULL, item_description TEXT NOT NULL, quantity REAL, unit_name TEXT, box_count INT, order_ref TEXT, operator_name TEXT NOT NULL, comments TEXT, status TEXT);
CREATE TABLE movements (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INT, movement_type TEXT, from_warehouse_id INT, to_warehouse_id INT, payload TEXT);
CREATE TABLE events (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, payload TEXT);
CREATE TABLE warehouses (id INTEGER PRIMARY KEY, code INT, name TEXT, erp_storehouse_id INT);
CREATE TABLE work_order_machine_ink_loads (id INTEGER PRIMARY KEY AUTOINCREMENT, worker_ot_id INT, ag_id INT, warehouse_code INT DEFAULT 16, item_id INT, item_code TEXT, item_name TEXT, unit_name TEXT, initial_weight_kg REAL DEFAULT 0, loaded_qty REAL DEFAULT 0, leftover_qty REAL DEFAULT 0, consumed_qty REAL DEFAULT 0, returned_qty REAL DEFAULT 0, color_label TEXT, machine_process TEXT, operator_name TEXT, comments TEXT, status TEXT, created_at INT, updated_at INT, closed_at INT);
INSERT INTO warehouses VALUES (1,1000,'Bodega 1000',14),(2,3000,'Bodega 3000',16);");

$stock = fn(int $item, int $st, int $order = 0): float => (float)$erp->query("SELECT COALESCE(SUM(iss_inventory),0) FROM item_shops_storehouses WHERE item_id=$item AND st_id=$st AND iss_order_id=$order")->fetchColumn();
$kardex = fn(int $item, int $st, int $order = 0): float => (float)$erp->query("SELECT COALESCE(SUM(CASE WHEN tran_type IN ('stockchangeup','sthup') THEN tran_amount ELSE -tran_amount END),0) FROM tran_data WHERE item_id=$item AND tran_st_id=$st AND tran_order_id=$order")->fetchColumn();

// 1. Cajas con código al cerrar el embalaje
$bags = new BagBoxService($trz, $erp);
$sync = $bags->syncPackagingBoxes(900, 14, 'Operador');
$boxes = $bags->packagingBoxes(900);
$check('el embalaje crea una caja por caja completa y otra por la caja final', count($sync['created']) === 3 && count($boxes) === 3);
$check('cada caja guarda CC, SKU, bodega y unidades', $boxes[0]['erp_order_id'] == 500 && $boxes[0]['final_sku'] === 'BOL0900' && (int)$boxes[0]['erp_st_id'] === 14
    && (float)$boxes[0]['units_qty'] === 200.0 && (float)$boxes[0]['units_initial'] === 200.0 && $boxes[0]['customer_order_ref'] === '26-00500-1');
$check('códigos únicos con formato BX', count(array_unique(array_column($boxes, 'box_code'))) === 3 && preg_match('/^BX[0-9A-F]{8}$/', $boxes[0]['box_code']) === 1);
$check('la etiqueta trazable de la caja queda creada', (int)$trz->query("SELECT COUNT(*) FROM traceability_warehouse_entries WHERE entity_type='BOX'")->fetchColumn() === 3);
$check('repetir el cierre no duplica cajas', $bags->syncPackagingBoxes(900, 14, 'Operador')['created'] === [] && count($bags->packagingBoxes(900)) === 3);
$erp->exec('UPDATE prod_worker_embalajes SET req_embalaje_caja_final=0 WHERE req_v2refid=900');
$check('corregir el embalaje sin usar cajas las rehace', count($bags->syncPackagingBoxes(900, 14, 'Operador')['created']) === 2 && count($bags->packagingBoxes(900)) === 2);
$erp->exec('UPDATE prod_worker_embalajes SET req_embalaje_caja_final=200 WHERE req_v2refid=900');
$bags->syncPackagingBoxes(900, 14, 'Operador');
$boxes = $bags->packagingBoxes(900);
$check('la búsqueda por código encuentra la caja', (int)($bags->find(strtolower($boxes[0]['box_code']))['id'] ?? 0) === (int)$boxes[0]['id']);

// 2. Traspaso de una caja a la Bodega 3000 (Solicitudes MP)
$supply = new MaterialSupplyService($erp, $trz);
$transfer = new ReflectionMethod($supply, 'transferBoxes');
$transfer->setAccessible(true);
$box = $boxes[0];
$payload = ['boxes' => [['id' => (int)$box['id'], 'code' => $box['box_code'], 'qty' => 200.0, 'order_id' => 500, 'from_st' => 14]], 'context' => ['machine_name' => 'PULPO']];
$r = ['item_id' => 20, 'request_key' => str_repeat('a', 32)];
$d = ['dispatch_key' => str_repeat('b', 32)];
$transfer->invoke($supply, $r, $d, $payload, ['user_id' => 1, 'name' => 'Bodega']);
$check('el traspaso mueve el saldo de la caja con su CC', $stock(20, 14, 500) === 400.0 && $stock(20, 16, 500) === 200.0);
$check('el traspaso deja kardex sthdown/sthup', $kardex(20, 14, 500) === -200.0 && $kardex(20, 16, 500) === 200.0);
$check('la caja queda en la Bodega 3000', (int)$bags->byId((int)$box['id'])['erp_st_id'] === 16);
$transfer->invoke($supply, $r, $d, $payload, ['user_id' => 1, 'name' => 'Bodega']);
$check('reintentar el despacho no traspasa dos veces', $stock(20, 16, 500) === 200.0);

// 3. Pulpo: poner caja, usar bolsas, anular, cerrar
$pulpo = new PulpoBagService($erp, $trz);
$check('una caja que sigue en su bodega se rechaza', str_contains($err(fn() => $pulpo->mount(7001, $boxes[1]['box_code'], 'Op')), 'Solicitudes MP'));
$mounted = $pulpo->mount(7001, $box['box_code'], 'Op');
$check('la caja en 3000 queda en la máquina', $mounted['status'] === 'IN_MACHINE' && count($pulpo->mountedBoxes(7001)) === 1);
$key = str_repeat('c', 32);
$res = $pulpo->consume(7001, (int)$box['id'], 50, 'Op', $key);
$check('usar 50 bolsas deja 150 en la caja', $res['units_after'] === 150.0 && (float)$bags->byId((int)$box['id'])['units_qty'] === 150.0);
$check('el inventario del SKU baja 50 en 3000 con su CC', $stock(20, 16, 500) === 150.0 && $kardex(20, 16, 500) === 150.0);
$check('la etiqueta trazable baja su saldo', (float)$trz->query("SELECT quantity FROM traceability_warehouse_entries WHERE entity_id=" . (int)$box['id'])->fetchColumn() === 150.0);
$pulpo->consume(7001, (int)$box['id'], 50, 'Op', $key);
$check('repetir el envío no descuenta dos veces', $stock(20, 16, 500) === 150.0 && (float)$bags->byId((int)$box['id'])['units_qty'] === 150.0);
$check('no se pueden usar más bolsas que las de la caja', str_contains($err(fn() => $pulpo->consume(7001, (int)$box['id'], 500, 'Op', str_repeat('d', 32))), 'tiene 150'));
$check('cantidad no entera se rechaza', str_contains($err(fn() => $pulpo->consume(7001, (int)$box['id'], 2.5, 'Op', str_repeat('e', 32))), 'entera'));
$moveId = (int)$trz->query("SELECT id FROM box_unit_moves WHERE kind='CONSUME' ORDER BY id LIMIT 1")->fetchColumn();
$pulpo->reverse(7001, $moveId, 'Op', 'me equivoqué de caja');
$check('anular devuelve las bolsas a la caja y al inventario', (float)$bags->byId((int)$box['id'])['units_qty'] === 200.0 && $stock(20, 16, 500) === 200.0 && $kardex(20, 16, 500) === 200.0);
$check('el consumo anulado no cuenta', $pulpo->consumedForAgenda(70) === 0.0);
$pulpo->consume(7001, (int)$box['id'], 45, 'Op', str_repeat('f', 32));
$erp->exec("INSERT INTO prod_worker_ot_events (id,evt_prod_worker_otid,evt_amount,evt_status,evt_type,evt_amount_pulpo_frente) VALUES (8001,7001,0,2,'prod',40)");
$erp->exec("INSERT INTO prod_worker_ot_defectunits (evt_refid,evt_status,evt_type,evt_amount) VALUES (8001,1,'merma',3)");
$need = $pulpo->neededForAgenda(70);
$check('bolsas necesarias = impresas + merma', $need['needed'] === 43.0);
$check('cerrar con 2 bolsas de más pide confirmar', str_contains($err(fn() => $pulpo->assertReadyToClose(7001, false, '', 'sup')), 'de más'));
$check('confirmar sin motivo no basta', str_contains($err(fn() => $pulpo->assertReadyToClose(7001, true, 'corto', 'sup')), 'motivo'));
$pulpo->assertReadyToClose(7001, true, 'dos bolsas rotas al cargar la camilla', 'sup');
$check('con motivo cierra y baja la caja con su saldo', (string)$bags->byId((int)$box['id'])['status'] === 'IN_WAREHOUSE' && $pulpo->mountedBoxes(7001) === []);
$check('la diferencia queda en la bitácora de la OT', (int)$erp->query("SELECT COUNT(*) FROM prod_worker_ot_events WHERE evt_comments LIKE 'Diferencia de bolsas confirmada%'")->fetchColumn() === 1);
$check('la bitácora registra las bolsas usadas', (int)$erp->query("SELECT COUNT(*) FROM prod_worker_ot_events WHERE evt_comments LIKE 'Bolsas usadas:%'")->fetchColumn() === 2);
$check('una OT que no es Pulpo no usa cajas', str_contains($err(function () use ($erp, $pulpo) {
    $erp->exec("INSERT INTO prod_worker_init VALUES (2,77,1,1,40); INSERT INTO prod_worker_ot (id,wok_ag_id,wok_init_id,wok_status) VALUES (7002,70,2,1);");
    $pulpo->context(7002);
}), 'Pulpo'));

// 4. Embalaje con cajas usadas no se puede borrar
$check('no se puede borrar un embalaje cuyas cajas ya se usaron', str_contains($err(fn() => $bags->voidPackagingBoxes(900)), 'ya se usó'));
$check('corregir el embalaje con cajas usadas avisa y no cambia códigos', str_contains($bags->syncPackagingBoxes(900, 8, 'Op')['warning'], 'ya se usaron'));

// 5. Detección de la máquina
$check('equipo 36 es Pulpo', MachineKind::isPulpo(36));
$check('serigrafía con nombre PULPO es Pulpo', MachineKind::isPulpo(99, 'Pulpo nuevo', 11));
$check('serigrafía plana no es Pulpo', !MachineKind::isPulpo(40, 'SERI II', 11, true));

// 5b. Bolsas terminadas según las caras que imprime la OT
$check('frente y dorso: terminadas = las que tienen ambas caras', PulpoBagService::finishedBags(100, 60, true, true) === 60.0);
$check('solo frente: terminadas = pasadas de frente', PulpoBagService::finishedBags(100, 0, true, false) === 100.0);
$check('solo dorso: terminadas = pasadas de dorso (antes daba 0)', PulpoBagService::finishedBags(0, 80, false, true) === 80.0);
$sidesDb = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$colDefs = [];
for ($i = 1; $i <= 10; $i++) {
    $colDefs[] = "fab_print_colors_front_$i INT DEFAULT 0, fab_print_colors_back_$i INT DEFAULT 0, fab_print_colordesc_$i TEXT DEFAULT ''";
}
$sidesDb->exec('CREATE TABLE prod_agenda (id INTEGER PRIMARY KEY, ag_reqid INT); CREATE TABLE orders_items (req_id INT, ' . implode(', ', $colDefs) . ');');
$sidesDb->exec("INSERT INTO prod_agenda VALUES (1,10),(2,20),(3,30);
INSERT INTO orders_items (req_id, fab_print_colors_back_7, fab_print_colordesc_7) VALUES (10, 1, 'AZUL');
INSERT INTO orders_items (req_id, fab_print_colors_front_1, fab_print_colors_back_2) VALUES (20, 1, 1);
INSERT INTO orders_items (req_id, fab_print_colordesc_1) VALUES (30, 'NEGRO');");
$check('OT de solo dorso (color 7) se reconoce', PulpoBagService::printSides($sidesDb, 1) === ['front' => false, 'back' => true]);
$check('OT de frente y dorso se reconoce', PulpoBagService::printSides($sidesDb, 2) === ['front' => true, 'back' => true]);
$check('color sin marcar cuenta como frente', PulpoBagService::printSides($sidesDb, 3) === ['front' => true, 'back' => false]);

// 6. Tinta por kardex
$prod = new ProductionService($erp, $trz);
$ink = $prod->recordSerigrafiaInkConsumption(7001, 70, 30, 5.0, 0, 2.0, 0, 'Color 1: BLANCO', 'Op');
$check('la tinta de serigrafía se descuenta con kardex', $ink['ok'] === true && $stock(30, 16) === 17.0 && $kardex(30, 16) === -3.0, json_encode($ink));
$check('la ficha del pulpo lee la tinta por color', ($prod->pulpoInkByColor(70)[1] ?? 0) === 3.0);
$del = $prod->deleteMachineInkLoad((int)$ink['load_id'], 7001, 'Op');
$check('anular la tinta la devuelve con kardex', $del['ok'] === true && $stock(30, 16) === 20.0 && $kardex(30, 16) === 0.0);
$trz->exec("INSERT INTO work_order_machine_ink_loads (worker_ot_id,ag_id,item_id,loaded_qty,consumed_qty,machine_process,status,created_at,updated_at) VALUES (7001,70,30,4,4,'FLEXO','CLOSED',0,0)");
$flexoId = (int)$trz->lastInsertId();
$prod->deleteMachineInkLoad($flexoId, 7001, 'Op');
$check('anular una carga de flexo que nunca descontó no suma stock', $stock(30, 16) === 20.0);
$trz->exec("INSERT INTO work_order_machine_ink_loads (worker_ot_id,ag_id,item_id,loaded_qty,consumed_qty,returned_qty,machine_process,status,created_at,updated_at) VALUES (7001,70,30,4,0,0,'FLEXO','ACTIVE',0,0)");
$flexoId = (int)$trz->lastInsertId();
$c = $prod->consumeMachineInkLoad($flexoId, 7001, 1.5, 'Op');
$check('la tinta usada en flexo se descuenta con kardex', $c['ok'] === true && $stock(30, 16) === 18.5 && $kardex(30, 16) === -1.5, json_encode($c));
$prod->deleteMachineInkLoad($flexoId, 7001, 'Op');
$check('anular la carga de flexo devuelve lo usado', $stock(30, 16) === 20.0 && $kardex(30, 16) === 0.0);

// 6b. Insumos de embalaje (pallet, caja, cinta) por kardex, y su reversa al corregir
$erp->exec("INSERT INTO item (id,item_title,item_number_prod) VALUES (118,'PALLET','INS0029'),(93,'CAJA 500X400X400','INS0012'),(575,'CINTA PAPEL','INS0073');
INSERT INTO item_shops_storehouses VALUES (118,30010,16,0,0,50,0,0),(93,30010,16,0,0,500,0,0),(575,30010,16,0,0,100,0,0);");
$supplyDetail = ['req_codigo_cierre' => '10', 'req_embalaje_cajas_completas_amt' => 10, 'req_embalaje_caja_final' => 0,
    'req_embalaje_cajas_por_pallet_amt' => 20, 'req_embalaje_medidas_caja' => '50X40X40', 'req_embalaje_tipo_cinta' => 'INS0073'];
$sup = $prod->consumePackagingSupplies(7001, 5555, $supplyDetail);
$check('los insumos de embalaje se descuentan con kardex', ($sup['ok'] ?? false) === true && $stock(118, 16) === 49.0 && $kardex(118, 16) === -1.0
    && $stock(93, 16) === 490.0 && $kardex(93, 16) === -10.0 && $kardex(575, 16) < 0, json_encode($sup));
$prod->consumePackagingSupplies(7001, 5555, $supplyDetail);
$check('recalcular el mismo embalaje no descuenta dos veces', $stock(118, 16) === 49.0 && $stock(93, 16) === 490.0 && $kardex(93, 16) === -10.0);

// 7. Revisión de solo lectura
$audit = (new StockLedgerAudit($erp, $trz))->run(time() - 3600);
$check('la revisión no marca nada en un flujo correcto', $audit['docs_a_medias'] === [] && $audit['movimientos_pendientes'] === [] && $audit['cajas_sin_stock'] === [] && $audit['stock_fuera_de_kardex'] === [], json_encode($audit));
$erp->exec('UPDATE item_shops_storehouses SET iss_inventory = iss_inventory - 7 WHERE item_id=20 AND st_id=16 AND iss_order_id=500');
$audit = (new StockLedgerAudit($erp, $trz))->run(time() - 3600);
$check('la revisión detecta stock cambiado sin kardex', count($audit['stock_fuera_de_kardex']) === 1);
$erp->exec('UPDATE item_shops_storehouses SET iss_inventory = 0 WHERE item_id=20 AND st_id=14 AND iss_order_id=500');
$audit = (new StockLedgerAudit($erp, $trz))->run(time() - 3600);
$check('la revisión detecta cajas con más bolsas que el stock', count($audit['cajas_sin_stock']) >= 1);

echo $fail === 0 ? "TODO OK\n" : "FALLOS: $fail\n";
exit($fail === 0 ? 0 : 1);
