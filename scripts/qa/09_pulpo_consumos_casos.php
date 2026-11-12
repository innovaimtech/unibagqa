<?php
declare(strict_types=1);
// Pulpo Serigráfico: casos de borde del consumo de BOLSAS (cajas con código) y de TINTAS, con bases SQLite en memoria
// (no toca ninguna base real). Complementa 09_pulpo_bags.php.
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "pdo_sqlite no disponible: prueba omitida\n";
    exit(0);
}
$root = dirname(__DIR__, 2);
require_once $root . '/src/bootstrap.php';
require_once __DIR__ . '/lib_erp_sqlite.php';

$fail = 0;
$total = 0;
$check = function (string $name, bool $ok, string $extra = '') use (&$fail, &$total): void {
    $total++;
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . ($extra !== '' && !$ok ? " [$extra]" : '') . "\n";
    if (!$ok) {
        $fail++;
    }
};
$err = function (callable $f): string { try { $f(); } catch (Throwable $e) { return $e->getMessage(); } return ''; };
$key = static fn(): string => bin2hex(random_bytes(16));

$erp = qaErpSqlite();
$erp->exec("CREATE TABLE prod_worker_init (id INTEGER PRIMARY KEY, win_wrkid INT, win_status INT, win_plantaid INT, win_equipoid INT);
CREATE TABLE prod_worker_ot (id INTEGER PRIMARY KEY, wok_ag_id INT, wok_init_id INT, wok_status INT, wok_enddat INT);
CREATE TABLE prod_agenda (id INTEGER PRIMARY KEY, ag_reqid INT, ag_equipotype_id INT);
CREATE TABLE equipo (id INTEGER PRIMARY KEY, equipo_name TEXT, equipo_type_id INT, equipo_prod_isprinter_seri INT DEFAULT 0);
CREATE TABLE prod_worker_ot_events (id INTEGER PRIMARY KEY AUTOINCREMENT, evt_prod_worker_otid INT, evt_amount REAL, evt_crtdat INT, evt_enddat INT, evt_status INT, evt_type TEXT, evt_comments TEXT, prod_bobina_kg REAL DEFAULT 0, evt_amount_pulpo_frente REAL DEFAULT 0, evt_amount_pulpo_dorso REAL DEFAULT 0, evt_amount_metros_lineales INT DEFAULT 0);
CREATE TABLE prod_worker_ot_defectunits (id INTEGER PRIMARY KEY AUTOINCREMENT, evt_refid INT, evt_status INT, evt_type TEXT, evt_amount REAL, evt_kgstounits REAL DEFAULT 0);
ALTER TABLE item ADD COLUMN item_weight REAL DEFAULT 0;
ALTER TABLE orders_items ADD COLUMN fab_med_width REAL DEFAULT 0; ALTER TABLE orders_items ADD COLUMN fab_med_height REAL DEFAULT 0; ALTER TABLE orders_items ADD COLUMN fab_med_fuelle REAL DEFAULT 0;
ALTER TABLE orders_items ADD COLUMN fab_mat_gramms REAL DEFAULT 0; ALTER TABLE orders_items ADD COLUMN fab_manilla_length REAL DEFAULT 0;
CREATE TABLE prod_worker_embalajes (id INTEGER PRIMARY KEY, evt_prod_worker_otid INT, evt_reqid INT, req_v2refid INT, req_codigo_cierre TEXT, req_id_bodega INT, req_embalaje_cajas_completas_amt INT, req_embalaje_bolsas_por_caja_amt INT, req_embalaje_caja_final INT, req_embalaje_bolsas_sobrantes_amt INT, req_embalaje_showroom INT);
CREATE TABLE embalaje_mixto (id INTEGER PRIMARY KEY, id_embalaje INT);
CREATE TABLE detalle_mixto (id INTEGER PRIMARY KEY, id_mixto INT, req_number TEXT, id_item INT, total_cajas INT, bolsas_por_caja INT, bolsas_caja_final INT);
INSERT INTO item (id,item_title,item_number_prod) VALUES (20,'BOLSA ALGODON 30X40','BOL0900'),(21,'BOLSA IMPRESA','BOL0901'),
  (30,'TINTA SERI BLANCA','TIN0100'),(31,'TINTA SERI NEGRA','TIN0101'),(32,'TINTA SERI ROJA','TIN0102');
INSERT INTO orders (id,req_number,req_company_id,req_shop_id) VALUES (500,'26-00500-1',20010,30010),(600,'26-00600-1',20010,30010),(601,'26-00601-1',20010,30010);
INSERT INTO orders_items (req_id,item_id) VALUES (500,20),(600,21),(601,21);
INSERT INTO item_shops_storehouses VALUES (20,30010,14,0,0,1000,0,500),(30,30010,16,0,0,20,0,0),(31,30010,16,0,0,2,0,0),(32,30010,16,0,0,10,0,0);
INSERT INTO equipo VALUES (36,'PULPO SERIGRAFICO',11,1);
INSERT INTO prod_worker_init VALUES (1,77,1,1,36);
INSERT INTO prod_agenda VALUES (70,600,11),(71,601,11);
INSERT INTO prod_worker_ot (id,wok_ag_id,wok_init_id,wok_status) VALUES (7001,70,1,1),(7101,71,1,1);
INSERT INTO prod_worker_embalajes VALUES (1,100,500,900,'10',14,4,200,200,0,0);");

$trz = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$trz->exec("CREATE TABLE boxes (id INTEGER PRIMARY KEY AUTOINCREMENT, box_code TEXT UNIQUE, work_order_id INT, source_roll_id INT, pallet_id INT, final_sku TEXT NOT NULL, units_qty REAL NOT NULL, destination_mode TEXT NOT NULL DEFAULT 'BODEGA', customer_order_ref TEXT, warehouse_id INT, operator_name TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'CREATED', created_at TEXT);
CREATE TABLE traceability_warehouse_entries (id INTEGER PRIMARY KEY AUTOINCREMENT, traceability_code TEXT UNIQUE, batch_id TEXT, entity_type TEXT, entity_id INT, warehouse_id INT NOT NULL, warehouse_name TEXT NOT NULL, item_id INT, item_code TEXT NOT NULL, item_description TEXT NOT NULL, quantity REAL, unit_name TEXT, box_count INT, order_ref TEXT, operator_name TEXT NOT NULL, comments TEXT, status TEXT);
CREATE TABLE movements (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INT, movement_type TEXT, from_warehouse_id INT, to_warehouse_id INT, payload TEXT);
CREATE TABLE events (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, payload TEXT);
CREATE TABLE warehouses (id INTEGER PRIMARY KEY, code INT, name TEXT, erp_storehouse_id INT);
CREATE TABLE work_order_machine_ink_loads (id INTEGER PRIMARY KEY AUTOINCREMENT, worker_ot_id INT, ag_id INT, warehouse_code INT DEFAULT 16, item_id INT, item_code TEXT, item_name TEXT, unit_name TEXT, initial_weight_kg REAL DEFAULT 0, loaded_qty REAL DEFAULT 0, leftover_qty REAL DEFAULT 0, consumed_qty REAL DEFAULT 0, returned_qty REAL DEFAULT 0, color_label TEXT, machine_process TEXT, operator_name TEXT, comments TEXT, status TEXT, created_at INT, updated_at INT, closed_at INT);
INSERT INTO warehouses VALUES (1,1000,'Bodega 1000',14),(2,3000,'Bodega 3000',16);");

$stock = fn(int $item, int $st, int $order = 0): float => round((float)$erp->query("SELECT COALESCE(SUM(iss_inventory),0) FROM item_shops_storehouses WHERE item_id=$item AND st_id=$st AND iss_order_id=$order")->fetchColumn(), 3);
$kardex = fn(int $item, int $st, int $order = 0): float => round((float)$erp->query("SELECT COALESCE(SUM(CASE WHEN tran_type IN ('stockchangeup','sthup') THEN tran_amount ELSE -tran_amount END),0) FROM tran_data WHERE item_id=$item AND tran_st_id=$st AND tran_order_id=$order")->fetchColumn(), 3);

// Cajas: 4 completas de 200 + caja final de 200 = 5 cajas de 200 (1.000 bolsas en la Bodega 1000, CC 26-00500-1).
$bags = new BagBoxService($trz, $erp);
$bags->syncPackagingBoxes(900, 14, 'Operador');
$boxes = $bags->packagingBoxes(900);
$supply = new MaterialSupplyService($erp, $trz);
$transfer = new ReflectionMethod($supply, 'transferBoxes');
$transfer->setAccessible(true);
$send = function (array $box) use ($transfer, $supply): void {
    $payload = ['boxes' => [['id' => (int)$box['id'], 'code' => $box['box_code'], 'qty' => (float)$box['units_qty'], 'order_id' => 500, 'from_st' => 14]], 'context' => ['machine_name' => 'PULPO']];
    $transfer->invoke($supply, ['item_id' => 20, 'request_key' => bin2hex(random_bytes(16))], ['dispatch_key' => bin2hex(random_bytes(16))], $payload, ['user_id' => 1, 'name' => 'Bodega']);
};
$pulpo = new PulpoBagService($erp, $trz);
$units = fn(array $box): float => (float)$bags->byId((int)$box['id'])['units_qty'];
$status = fn(array $box): string => (string)$bags->byId((int)$box['id'])['status'];
[$A, $B, $C, $D, $E] = $boxes;

echo "== BOLSAS ==\n";
// B1. Caso real: se piden 100 bolsas y la caja trae 200. Se traspasa la caja completa; la OT usa 100; el saldo sigue en 3000.
$send($A);
$check('B1 traspaso: la caja de 200 pasa completa a 3000 con su CC', $stock(20, 16, 500) === 200.0 && $stock(20, 14, 500) === 800.0 && (int)$bags->byId((int)$A['id'])['erp_st_id'] === 16);
$pulpo->mount(7001, $A['box_code'], 'Op1');
$pulpo->consume(7001, (int)$A['id'], 100, 'Op1', $key());
$check('B1 la OT 1 usa 100: la caja queda con 100 y 3000 con 100', $units($A) === 100.0 && $stock(20, 16, 500) === 100.0 && $kardex(20, 16, 500) === 100.0);
$pulpo->unmount(7001, (int)$A['id'], 'Op1');
$check('B1 al bajarla queda disponible en 3000 con su saldo y la misma etiqueta', $status($A) === 'IN_WAREHOUSE'
    && (float)$trz->query('SELECT quantity FROM traceability_warehouse_entries WHERE entity_id=' . (int)$A['id'])->fetchColumn() === 100.0);
$pulpo->mount(7101, $A['box_code'], 'Op2');
$pulpo->consume(7101, (int)$A['id'], 100, 'Op2', $key());
$check('B1 la OT 2 usa las 100 restantes: caja consumida y 3000 en 0', $units($A) === 0.0 && $status($A) === 'CONSUMED' && $stock(20, 16, 500) === 0.0 && $kardex(20, 16, 500) === 0.0);
$check('B1 cada OT cuenta solo sus bolsas', $pulpo->consumedForAgenda(70) === 100.0 && $pulpo->consumedForAgenda(71) === 100.0);
$check('B2 una caja consumida no se puede volver a montar', str_contains($err(fn() => $pulpo->mount(7001, $A['box_code'], 'Op1')), 'no tiene bolsas'));

// B3. Anular el último consumo de una caja consumida: vuelve a estar disponible.
$lastMove = (int)$trz->query("SELECT id FROM box_unit_moves WHERE box_id=" . (int)$A['id'] . " AND kind='CONSUME' ORDER BY id DESC LIMIT 1")->fetchColumn();
$check('B3 motivo de anulación muy corto se rechaza', str_contains($err(fn() => $pulpo->reverse(7101, $lastMove, 'Op2', 'x')), 'motivo'));
$check('B3 no se puede anular el consumo de otra OT', str_contains($err(fn() => $pulpo->reverse(7001, $lastMove, 'Op1', 'error de conteo')), 'no pertenece'));
$pulpo->reverse(7101, $lastMove, 'Op2', 'error de conteo');
$check('B3 anular devuelve 100 a la caja, la reactiva y repone 3000 con kardex', $units($A) === 100.0 && $status($A) === 'IN_WAREHOUSE' && $stock(20, 16, 500) === 100.0 && $kardex(20, 16, 500) === 100.0);
$pulpo->reverse(7101, $lastMove, 'Op2', 'error de conteo');
$check('B4 anular dos veces no devuelve dos veces', $units($A) === 100.0 && $stock(20, 16, 500) === 100.0 && $pulpo->consumedForAgenda(71) === 0.0);

// B5. Validaciones de cantidad y de caja.
$send($B);
$pulpo->mount(7001, $B['box_code'], 'Op1');
foreach (['cero' => 0.0, 'negativa' => -5.0, 'decimal' => 1.5, 'infinita' => INF, 'no numérica' => NAN] as $label => $q) {
    $check("B5 cantidad $label se rechaza", str_contains($err(fn() => $pulpo->consume(7001, (int)$B['id'], $q, 'Op1', $key())), 'entera'));
}
$check('B5 clave de confirmación inválida se rechaza', str_contains($err(fn() => $pulpo->consume(7001, (int)$B['id'], 1, 'Op1', 'abc')), 'Confirmación'));
$check('B5 más bolsas que el saldo se rechaza', str_contains($err(fn() => $pulpo->consume(7001, (int)$B['id'], 201, 'Op1', $key())), 'tiene 200'));
$check('B6 la caja montada en la OT 1 no se puede usar desde la OT 2', str_contains($err(fn() => $pulpo->consume(7101, (int)$B['id'], 1, 'Op2', $key())), 'no está en esta máquina'));
$check('B6 tampoco se puede montar en la OT 2 mientras está en la 1', str_contains($err(fn() => $pulpo->mount(7101, $B['box_code'], 'Op2')), 'en uso en otra OT'));
$check('B6 una caja que sigue en la Bodega 1000 no se monta', str_contains($err(fn() => $pulpo->mount(7001, $C['box_code'], 'Op1')), 'Solicitudes MP'));
$check('B6 código inexistente', str_contains($err(fn() => $pulpo->mount(7001, 'BXNOEXISTE', 'Op1')), 'no es una caja'));

// B7. Mismo envío dos veces (doble clic) y luego otro distinto.
$k = $key();
$pulpo->consume(7001, (int)$B['id'], 30, 'Op1', $k);
$pulpo->consume(7001, (int)$B['id'], 30, 'Op1', $k);
$check('B7 doble clic: se descuenta una sola vez', $units($B) === 170.0 && $stock(20, 16, 500) === 270.0);
$pulpo->consume(7001, (int)$B['id'], 70, 'Op1', $k);
$check('B7 reusar la clave con otra cantidad no descuenta de nuevo', $units($B) === 170.0);

// B8. Falla del ERP a mitad: queda pendiente, bloquea, y al reintentar termina sin descontar dos veces.
$erp->exec('ALTER TABLE tran_data RENAME TO tran_data_off');
$msg = $err(fn() => $pulpo->consume(7001, (int)$B['id'], 20, 'Op1', $key()));
$erp->exec('ALTER TABLE tran_data_off RENAME TO tran_data');
$check('B8 con el ERP caído el consumo queda pendiente y avisa', str_contains($msg, 'pendiente'), $msg);
$check('B8 la caja no cambia su saldo mientras está pendiente', $units($B) === 170.0);
$check('B8 no deja registrar otro consumo de esa caja', str_contains($err(fn() => $pulpo->consume(7001, (int)$B['id'], 5, 'Op1', $key())), 'pendiente'));
$check('B8 no deja cerrar la OT con pendientes', str_contains($err(fn() => $pulpo->assertReadyToClose(7001, true, 'motivo de prueba largo', 'sup')), 'pendientes'));
$check('B8 «Reintentar pendientes» lo termina', $pulpo->resumePending(7001) === 1 && $units($B) === 150.0 && $stock(20, 16, 500) === 250.0 && $kardex(20, 16, 500) === 250.0);
$check('B8 reintentar otra vez no hace nada', $pulpo->resumePending(7001) === 0 && $stock(20, 16, 500) === 250.0);

// B9. Dos cajas a la vez y cierre de OT: baja ambas con su saldo.
$send($C);
$pulpo->mount(7001, $C['box_code'], 'Op1');
$pulpo->consume(7001, (int)$C['id'], 200, 'Op1', $key());
$check('B9 usar la caja completa la marca consumida y la saca de la máquina', $status($C) === 'CONSUMED' && count($pulpo->mountedBoxes(7001)) === 1);
$check('B9 total de la OT 1 = 100 + 50 + 200', $pulpo->consumedForAgenda(70) === 350.0);

// B10. Stock del ERP menor que el saldo de la caja (alguien lo movió a mano): se descuenta igual y avisa.
$erp->exec('UPDATE item_shops_storehouses SET iss_inventory = 10 WHERE item_id=20 AND st_id=16 AND iss_order_id=500');
$r = $pulpo->consume(7001, (int)$B['id'], 50, 'Op1', $key());
$check('B10 stock insuficiente en 3000: descuenta y avisa a bodega', str_contains($r['warning'], 'negativo') && $stock(20, 16, 500) === -40.0, json_encode($r));
$erp->exec('UPDATE item_shops_storehouses SET iss_inventory = 150 WHERE item_id=20 AND st_id=16 AND iss_order_id=500');

// B11. Anular un consumo cuando la caja ya está montada en otra OT: vuelve el saldo y la caja sigue en esa máquina.
$m1 = (int)$trz->query("SELECT id FROM box_unit_moves WHERE box_id=" . (int)$B['id'] . " AND kind='CONSUME' AND state='DONE' ORDER BY id DESC LIMIT 1")->fetchColumn();
$pulpo->unmount(7001, (int)$B['id'], 'Op1');
$pulpo->mount(7101, $B['box_code'], 'Op2');
$pulpo->reverse(7001, $m1, 'Op1', 'conteo corregido');
$bb = $bags->byId((int)$B['id']);
$check('B11 la anulación devuelve el saldo sin sacar la caja de la otra OT', (float)$bb['units_qty'] === 150.0 && $bb['status'] === 'IN_MACHINE' && (int)$bb['current_worker_ot_id'] === 7101);

// B12. Cierre de la OT 2 con bolsas sin registrar: pide confirmar; con motivo baja la caja con su saldo.
$erp->exec("INSERT INTO prod_worker_ot_events (id,evt_prod_worker_otid,evt_amount,evt_status,evt_type,evt_amount_pulpo_frente) VALUES (9101,7101,0,2,'prod',100)");
$check('B12 cerrar sin registrar las bolsas usadas pide corregir o confirmar', str_contains($err(fn() => $pulpo->assertReadyToClose(7101, false, '', 'sup')), 'Faltan registrar'));
$pulpo->consume(7101, (int)$B['id'], 100, 'Op2', $key());
$pulpo->assertReadyToClose(7101, false, '', 'sup');
$check('B12 con las bolsas cuadradas cierra sin pedir motivo y baja la caja con 50', $status($B) === 'IN_WAREHOUSE' && $units($B) === 50.0 && $pulpo->mountedBoxes(7101) === []);

// B13. Todo cuadra: suma de saldos de cajas en 3000 = stock del ERP, y el kardex explica el stock.
$erp->exec('UPDATE item_shops_storehouses SET iss_inventory = (SELECT COALESCE(SUM(CASE WHEN tran_type IN (\'stockchangeup\',\'sthup\') THEN tran_amount ELSE -tran_amount END),0) FROM tran_data WHERE item_id=20 AND tran_st_id=16 AND tran_order_id=500) WHERE item_id=20 AND st_id=16 AND iss_order_id=500');
$boxSum = (float)$trz->query("SELECT COALESCE(SUM(units_qty),0) FROM boxes WHERE erp_st_id=16 AND status IN ('IN_WAREHOUSE','IN_MACHINE')")->fetchColumn();
$check('B13 saldo de las cajas en 3000 = stock del ERP = kardex', $boxSum === $stock(20, 16, 500) && $stock(20, 16, 500) === $kardex(20, 16, 500), "$boxSum / " . $stock(20, 16, 500) . ' / ' . $kardex(20, 16, 500));
$check('B13 la Bodega 1000 bajó exactamente lo traspasado (3 cajas)', $stock(20, 14, 500) === 400.0 && $kardex(20, 14, 500) === -600.0);

echo "== TINTAS ==\n";
$prod = new ProductionService($erp, $trz);
// T1. Registro normal: tarro de 5 kg, sobró 2 kg → 3 kg.
$t1 = $prod->recordSerigrafiaInkConsumption(7001, 70, 30, 5.0, 0, 2.0, 0, 'Color 1: BLANCO', 'Op1');
$check('T1 tarro 5 kg, sobró 2: descuenta 3 kg con kardex', $t1['ok'] && $t1['consumed_kg'] === 3.0 && $stock(30, 16) === 17.0 && $kardex(30, 16) === -3.0, json_encode($t1));
// T2. Sobró más de lo que pesaba el tarro: debe rechazarse (la pantalla lo frena, pero el servidor también).
$t2 = $prod->recordSerigrafiaInkConsumption(7001, 70, 30, 2.0, 0, 3.0, 0, 'Color 1: BLANCO', 'Op1');
$check('T2 sobrante mayor que el tarro se rechaza y no deja registro', !$t2['ok'] && $stock(30, 16) === 17.0
    && (int)$trz->query('SELECT COUNT(*) FROM work_order_machine_ink_loads')->fetchColumn() === 1, json_encode($t2));
// T3. Sin pesos: todo en cero o negativo.
$check('T3 todo en cero se rechaza', !$prod->recordSerigrafiaInkConsumption(7001, 70, 30, 0, 0, 0, 0, 'Color 1: BLANCO', 'Op1')['ok']);
$check('T3 consumo negativo se rechaza', !$prod->recordSerigrafiaInkConsumption(7001, 70, 30, 0, 0, 0, -2, 'Color 1: BLANCO', 'Op1')['ok'] && $stock(30, 16) === 17.0);
// T4. Un artículo que no es tinta (bolsas) no se puede descontar como tinta.
$t4 = $prod->recordSerigrafiaInkConsumption(7001, 70, 20, 5.0, 0, 1.0, 0, 'Color 1: BLANCO', 'Op1');
$check('T4 un artículo que no es tinta (BOL) se rechaza', !$t4['ok'] && $stock(20, 16, 0) === 0.0, json_encode($t4));
$check('T4 artículo inexistente se rechaza', !$prod->recordSerigrafiaInkConsumption(7001, 70, 999, 5.0, 0, 1.0, 0, 'Color 1', 'Op1')['ok']);
// T5. Doble envío del mismo formulario (doble clic o recargar): no debe descontar dos veces.
$opKey = $key();
$a = $prod->recordSerigrafiaInkConsumption(7001, 70, 32, 4.0, 0, 1.0, 0, 'Color 2: ROJO', 'Op1', '', 16, $opKey);
$b = $prod->recordSerigrafiaInkConsumption(7001, 70, 32, 4.0, 0, 1.0, 0, 'Color 2: ROJO', 'Op1', '', 16, $opKey);
$check('T5 doble envío con la misma clave descuenta una sola vez', $a['ok'] && $b['ok'] && $stock(32, 16) === 7.0 && $kardex(32, 16) === -3.0
    && (int)$trz->query('SELECT COUNT(*) FROM work_order_machine_ink_loads WHERE item_id=32')->fetchColumn() === 1, json_encode([$a, $b]));
// T6. Stock insuficiente: descuenta y avisa.
$t6 = $prod->recordSerigrafiaInkConsumption(7001, 70, 31, 3.5, 0, 0.5, 0, 'Color 3: NEGRO', 'Op1');
$check('T6 sin stock suficiente descuenta y avisa a bodega', $t6['ok'] && str_contains($t6['warning'], 'negativo') && $stock(31, 16) === -1.0, json_encode($t6));
// T7. Decimales: 1,1 - 0,9 = 0,2 exacto.
$t7 = $prod->recordSerigrafiaInkConsumption(7001, 70, 30, 1.1, 0, 0.9, 0, 'Color 1: BLANCO', 'Op1');
$check('T7 decimales exactos (1,1 − 0,9 = 0,2)', $t7['ok'] && $t7['consumed_kg'] === 0.2 && $stock(30, 16) === 16.8, json_encode($t7));
// T8. La ficha del pulpo suma por color y descuenta las anulaciones.
$byColor = $prod->pulpoInkByColor(70);
$check('T8 ficha del pulpo: color 1 = 3,2 kg, color 2 = 3 kg, color 3 = 3 kg', round($byColor[1] ?? 0, 3) === 3.2 && ($byColor[2] ?? 0) === 3.0 && ($byColor[3] ?? 0) === 3.0, json_encode($byColor));
$del = $prod->deleteMachineInkLoad((int)$t7['load_id'], 7001, 'Op1');
$check('T9 anular devuelve 0,2 kg con kardex y la ficha lo resta', $del['ok'] && $stock(30, 16) === 17.0 && $kardex(30, 16) === -3.0 && round($prod->pulpoInkByColor(70)[1] ?? 0, 3) === 3.0);
$del2 = $prod->deleteMachineInkLoad((int)$t7['load_id'], 7001, 'Op1');
$check('T9 anular dos veces no devuelve dos veces', !$del2['ok'] && $stock(30, 16) === 17.0);
$check('T9 no se puede anular la tinta desde otra OT', !$prod->deleteMachineInkLoad((int)$t1['load_id'], 7101, 'Op2')['ok'] && $stock(30, 16) === 17.0);

// T10. Tinta cargada a máquina (flujo de carga / uso / devolución).
$check('T10 cargar a máquina algo que no es TIN se rechaza', !$prod->loadInkToMachine(7001, 70, 20, 3, 'Op1')['ok']);
$ld = $prod->loadInkToMachine(7001, 70, 30, 4, 'Op1');
$check('T10 cargar 4 kg no mueve stock (sigue siendo de la bodega hasta usarla)', $ld['ok'] && $stock(30, 16) === 17.0);
$u1 = $prod->consumeMachineInkLoad((int)$ld['load_id'], 7001, 1.5, 'Op1');
$u2 = $prod->consumeMachineInkLoad((int)$ld['load_id'], 7001, 1.0, 'Op1');
$check('T10 usar 1,5 + 1 kg descuenta 2,5 kg con kardex', $u1['ok'] && $u2['ok'] && $stock(30, 16) === 14.5 && $kardex(30, 16) === -5.5);
$check('T10 usar más de lo que queda en máquina se rechaza', !$prod->consumeMachineInkLoad((int)$ld['load_id'], 7001, 2.0, 'Op1')['ok'] && $stock(30, 16) === 14.5);
$rt = $prod->returnMachineInkLoad((int)$ld['load_id'], 7001, 1.5, 'Op1');
$check('T10 devolver lo que sobró (1,5) cierra la carga y no mueve stock', $rt['ok'] && $rt['remaining_qty'] === 0.0 && $stock(30, 16) === 14.5
    && (string)$trz->query('SELECT status FROM work_order_machine_ink_loads WHERE id=' . (int)$ld['load_id'])->fetchColumn() === 'CLOSED');
$check('T10 una carga cerrada no admite más uso', !$prod->consumeMachineInkLoad((int)$ld['load_id'], 7001, 0.1, 'Op1')['ok']);
$dl = $prod->deleteMachineInkLoad((int)$ld['load_id'], 7001, 'Op1');
$check('T10 anular la carga devuelve los 2,5 kg usados', $dl['ok'] && $stock(30, 16) === 17.0 && $kardex(30, 16) === -3.0);

// T11. Cuadre final: el stock de cada tinta en 3000 = inicial + kardex.
$check('T11 cuadre final tintas: stock = inicial + kardex', $stock(30, 16) === 20.0 + $kardex(30, 16) && $stock(31, 16) === 2.0 + $kardex(31, 16) && $stock(32, 16) === 10.0 + $kardex(32, 16));
$audit = (new StockLedgerAudit($erp, $trz))->run(time() - 3600);
$check('T11 la revisión del kardex no encuentra documentos a medias ni pendientes', $audit['docs_a_medias'] === [] && $audit['movimientos_pendientes'] === [], json_encode($audit));

echo "\n$total pruebas, $fail fallas\n";
exit($fail === 0 ? 0 : 1);
