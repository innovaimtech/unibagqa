<?php
declare(strict_types=1);
// Solicitudes MP desde la bodega 400: traspaso escaneando las etiquetas del Ingreso con Trazabilidad (pallet, bulto o
// caja), caja que se queda con el saldo, parte con código temporal cargada en el Pulpo, y el candado del ERP cuando el
// usuario no tiene permiso LOCK TABLES (error 1044). ERP y TRZ en SQLite (memoria): no toca ninguna base real.
require_once __DIR__ . '/../../src/bootstrap.php';
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

$erp = qaErpSqlite();
$erp->exec("ALTER TABLE storehousechanges ADD COLUMN strc_updusr INT; ALTER TABLE storehousechanges ADD COLUMN strc_upddat INT;
CREATE TABLE company_shops (id INTEGER PRIMARY KEY, shop_company_id INT);
INSERT INTO company_shops VALUES (30010, 20010);
CREATE TABLE prod_worker_init (id INTEGER PRIMARY KEY, win_wrkid INT, win_status INT, win_plantaid INT, win_equipoid INT);
CREATE TABLE prod_worker_ot (id INTEGER PRIMARY KEY, wok_ag_id INT, wok_init_id INT, wok_status INT, wok_enddat INT);
CREATE TABLE prod_agenda (id INTEGER PRIMARY KEY, ag_reqid INT, ag_equipotype_id INT);
CREATE TABLE equipo (id INTEGER PRIMARY KEY, equipo_name TEXT, equipo_type_id INT, equipo_prod_isprinter_seri INT DEFAULT 0);
CREATE TABLE prod_worker_ot_events (id INTEGER PRIMARY KEY AUTOINCREMENT, evt_prod_worker_otid INT, evt_amount REAL, evt_crtdat INT, evt_enddat INT, evt_status INT, evt_type TEXT, evt_comments TEXT);
INSERT INTO item (id,item_title,item_number_prod) VALUES (20,'BOLSA ALGODON 30X40','BOL0900'),(21,'BOLSA SIN ETIQUETAS','BOL0950'),(22,'OTRA BOLSA','BOL0960');
INSERT INTO orders (id,req_number,req_company_id,req_shop_id) VALUES (600,'26-00600-1',20010,30010);
INSERT INTO item_shops_storehouses VALUES (20,30010,1,0,0,600,0,0),(21,30010,1,0,0,50,0,0),(22,30010,1,0,0,200,0,0);
INSERT INTO equipo VALUES (36,'PULPO SERIGRAFICO',11,1);
INSERT INTO prod_worker_init VALUES (1,77,1,1,36);
INSERT INTO prod_agenda VALUES (70,600,11);
INSERT INTO prod_worker_ot (id,wok_ag_id,wok_init_id,wok_status) VALUES (7001,70,1,1);");

$trz = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$trz->exec("CREATE TABLE warehouses (id INTEGER PRIMARY KEY, code TEXT, name TEXT, erp_storehouse_id INT);
INSERT INTO warehouses VALUES (4,'400','400 BODEGA',1),(18,'3000','3000 INSUMOS EN PRODUCCION',16);
CREATE TABLE boxes (id INTEGER PRIMARY KEY AUTOINCREMENT, box_code TEXT UNIQUE, work_order_id INT, source_roll_id INT, pallet_id INT, final_sku TEXT NOT NULL, units_qty REAL NOT NULL, destination_mode TEXT NOT NULL DEFAULT 'BODEGA', customer_order_ref TEXT, warehouse_id INT, operator_name TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'CREATED', created_at TEXT);
CREATE TABLE traceability_warehouse_entries (id INTEGER PRIMARY KEY AUTOINCREMENT, traceability_code TEXT UNIQUE, batch_id TEXT, entity_type TEXT, entity_id INT, warehouse_id INT NOT NULL, warehouse_name TEXT NOT NULL, item_id INT, item_code TEXT NOT NULL, item_description TEXT NOT NULL, quantity REAL, quantity_initial REAL, unit_name TEXT, box_count INT, order_ref TEXT, operator_name TEXT NOT NULL, comments TEXT, status TEXT);
CREATE TABLE movements (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INT, movement_type TEXT, from_warehouse_id INT, to_warehouse_id INT, payload TEXT);
CREATE TABLE events (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, payload TEXT);
-- Etiquetas del Ingreso con Trazabilidad en la bodega 400 (ERP 1): un pallet, un bulto y una caja sin registro de caja.
INSERT INTO traceability_warehouse_entries (traceability_code,entity_type,entity_id,warehouse_id,warehouse_name,item_id,item_code,item_description,quantity,quantity_initial,unit_name,operator_name,status) VALUES
 ('TRZ0001','PALLET',1,1,'400 BODEGA',20,'BOL0900','BOLSA ALGODON',200,200,'Unid.','Bodega','RECEIVED'),
 ('TRZ0002','PRODUCT',NULL,1,'400 BODEGA',20,'BOL0900','BOLSA ALGODON',200,200,'Unid.','Bodega',NULL),
 ('TRZ0003','PALLET',2,1,'400 BODEGA',20,'BOL0900','BOLSA ALGODON',200,200,'Unid.','Bodega','RECEIVED'),
 ('TRZ0099','PALLET',3,1,'400 BODEGA',22,'BOL0960','OTRA BOLSA',200,200,'Unid.','Bodega','RECEIVED'),
 ('TRZ0050','PALLET',4,16,'3000',20,'BOL0900','BOLSA ALGODON',200,200,'Unid.','Bodega','RECEIVED');");

$svc = new MaterialSupplyService($erp, $trz);
$svc->ensureSchema();
(new BagBoxService($trz, $erp))->ensureSchema(); // en MySQL el ALTER no se deshace con la transacción; en SQLite sí
$actor = ['user_id' => 4074, 'name' => 'Bodeguero'];
$ctx = ['req_shop_id' => 30010, 'req_company_id' => 20010, 'order_id' => 600, 'req_number' => '26-00600-1', 'win_equipoid' => 36, 'machine_name' => 'PULPO SERIGRAFICO', 'agenda_id' => 70];
$newRequest = function (int $itemId, string $sku, float $qty, int $otId = 7001) use ($trz, $ctx): string {
    $k = bin2hex(random_bytes(16));
    $trz->prepare("INSERT INTO production_supply_requests (request_key,erp_target,worker_ot_id,agenda_id,context,item_id,sku,description,
        source_storehouse_id,requested_qty,unit,requested_by,notes,created_at,status) VALUES (?,?,?,70,?,?,?,'Bolsa',1,?,'Unid.',?,'',?,'ACCEPTED')")
        ->execute([$k, 'sqlite-test', $otId, json_encode($ctx), $itemId, $sku, $qty, json_encode(['user_id' => 9, 'name' => 'Operador Pulpo']), time()]);
    return $k;
};
$row = fn(string $k): array => $trz->query("SELECT * FROM production_supply_requests WHERE request_key='$k'")->fetch();
$stock = fn(int $item, int $st, int $order = 0): float => round((float)$erp->query("SELECT COALESCE(SUM(iss_inventory),0) FROM item_shops_storehouses WHERE item_id=$item AND st_id=$st AND iss_order_id=$order")->fetchColumn(), 3);
$kardex = fn(int $item, int $st): float => round((float)$erp->query("SELECT COALESCE(SUM(CASE WHEN tran_type IN ('stockchangeup','sthup') THEN tran_amount ELSE -tran_amount END),0) FROM tran_data WHERE item_id=$item AND tran_st_id=$st")->fetchColumn(), 3);
$box = fn(string $code): ?array => (new BagBoxService($trz, $erp))->find($code);
$entry = fn(string $code): array => $trz->query("SELECT * FROM traceability_warehouse_entries WHERE traceability_code='$code'")->fetch();

echo "== Escaneo de etiquetas de la 400 ==\n";
$k1 = $newRequest(20, 'BOL0900', 300);
$check('producto de la 400 con etiquetas: se traspasa escaneando', $svc->usesLabelScan($row($k1)));
$check('producto de la 400 sin etiquetas: sigue por cantidad', !$svc->usesLabelScan($row($newRequest(21, 'BOL0950', 10))));
$check('código que no existe se rechaza', str_contains($err(fn() => $svc->scan($k1, 'TRZ9999', $actor)), 'no es una etiqueta'));
$check('etiqueta de otro producto se rechaza', str_contains($err(fn() => $svc->scan($k1, 'TRZ0099', $actor)), 'no del producto pedido'));
$check('etiqueta que está en otra bodega se rechaza', str_contains($err(fn() => $svc->scan($k1, 'TRZ0050', $actor)), 'no está en la bodega de origen'));
$svc->scan($k1, 'TRZ0001', $actor);
$svc->scan($k1, 'trz0002', $actor);
$check('pallet y bulto del ingreso se escanean (también en minúsculas)', (int)$trz->query("SELECT COUNT(*) FROM production_supply_scans WHERE request_key='$k1' AND state='SCANNED'")->fetchColumn() === 2);
$check('cada etiqueta escaneada pasa a manejarse como caja con su saldo', ($box('TRZ0001')['units_qty'] ?? 0) == 200 && ($box('TRZ0001')['source_kind'] ?? '') === 'INGRESO' && (int)($box('TRZ0002')['erp_st_id'] ?? 0) === 1);
$check('escanear dos veces la misma etiqueta se rechaza', str_contains($err(fn() => $svc->scan($k1, 'TRZ0001', $actor)), 'ya fue escaneada'));

echo "== Caja que se queda con el saldo ==\n";
$check('elegir una caja no escaneada se rechaza', str_contains($err(fn() => $svc->dispatch(bin2hex(random_bytes(16)), $k1, 0, '', $actor, '', 'TRZ0003')), 'no está entre las escaneadas'));
$dKey = bin2hex(random_bytes(16));
$svc->dispatch($dKey, $k1, 0, '', $actor, '', 'TRZ0002');
$check('ERP: salen exactamente las 300 pedidas de la 400 y llegan a la 3000', $stock(20, 1) === 300.0 && $stock(20, 16) === 300.0, $stock(20, 1) . '/' . $stock(20, 16));
$check('kardex: -300 en la 400 y +300 en la 3000', $kardex(20, 1) === -300.0 && $kardex(20, 16) === 300.0);
$b1 = $box('TRZ0001');
$check('la caja completa (TRZ0001) queda en la 3000 con sus 200', (int)$b1['erp_st_id'] === 16 && (float)$b1['units_qty'] === 200.0 && (int)$entry('TRZ0001')['warehouse_id'] === 16);
$b2 = $box('TRZ0002');
$check('la caja elegida (TRZ0002) se queda en la 400 con 100 y su etiqueta dice 100', (int)$b2['erp_st_id'] === 1 && (float)$b2['units_qty'] === 100.0
    && $b2['status'] === 'IN_WAREHOUSE' && (float)$entry('TRZ0002')['quantity'] === 100.0 && (int)$entry('TRZ0002')['warehouse_id'] === 1);
$part = $trz->query("SELECT * FROM boxes WHERE source_kind='SALDO'")->fetchAll();
$check('lo sacado (100) llega a la 3000 con un código temporal', count($part) === 1 && (float)$part[0]['units_qty'] === 100.0 && (int)$part[0]['erp_st_id'] === 16);
$check('el código temporal queda cargado en el Pulpo de la OT, sin escanear', ($part[0]['status'] ?? '') === 'IN_MACHINE' && (int)$part[0]['current_worker_ot_id'] === 7001);
$check('la solicitud queda traspasada', $row($k1)['status'] === 'DISPATCHED');
$check('los escaneos quedan traspasados y liberados para volver a escanear', (int)$trz->query("SELECT COUNT(*) FROM production_supply_scans WHERE request_key='$k1' AND state='DISPATCHED' AND active_code IS NULL")->fetchColumn() === 2);
$audit = $trz->query("SELECT detail FROM production_supply_audit WHERE request_key='$k1'")->fetchAll(PDO::FETCH_COLUMN);
$check('la bitácora dice cuánto salió de la caja y con cuánto quedó', count(array_filter($audit, static fn($n) => str_contains((string)$n, 'queda con 100'))) === 1, json_encode($audit));

// Reintento: el mismo traspaso no mueve stock ni crea otra parte.
$d = $trz->query("SELECT * FROM production_supply_dispatches WHERE dispatch_key='$dKey'")->fetch();
$payload = json_decode($d['payload'], true);
$m = new ReflectionMethod($svc, 'transferBoxes');
$m->setAccessible(true);
$args = [$row($k1), $d, &$payload, $actor];
$m->invokeArgs($svc, $args);
$check('reintentar el traspaso no duplica stock ni la parte temporal', $stock(20, 1) === 300.0 && $stock(20, 16) === 300.0
    && (int)$trz->query("SELECT COUNT(*) FROM boxes WHERE source_kind='SALDO'")->fetchColumn() === 1 && (float)$box('TRZ0002')['units_qty'] === 100.0);

echo "== Usar las bolsas en el Pulpo ==\n";
$pulpo = new PulpoBagService($erp, $trz);
$pulpo->consume(7001, (int)$part[0]['id'], 100, 'Operador', bin2hex(random_bytes(16)));
$check('el Pulpo consume las 100 del código temporal y baja la 3000', $stock(20, 16) === 200.0 && (string)$box((string)$part[0]['box_code'])['status'] === 'CONSUMED');
$pulpo->mount(7001, 'TRZ0001', 'Operador');
$pulpo->consume(7001, (int)$b1['id'], 50, 'Operador', bin2hex(random_bytes(16)));
$check('la caja completa se escanea en el Pulpo con su código de siempre', (float)$box('TRZ0001')['units_qty'] === 150.0 && $stock(20, 16) === 150.0);

echo "== Otras situaciones ==\n";
$k2 = $newRequest(20, 'BOL0900', 50);
$svc->scan($k2, 'TRZ0002', $actor);
$check('la caja que quedó con saldo se puede escanear en otra solicitud (con sus 100)', (float)$trz->query("SELECT quantity FROM production_supply_scans WHERE request_key='$k2' AND code='TRZ0002'")->fetchColumn() === 100.0);
$check('si sobra la caja entera, avisa que hay que anular su escaneo', str_contains($err(function () use ($svc, $k2, $actor) {
    $svc->scan($k2, 'TRZ0003', $actor);
    $svc->dispatch(bin2hex(random_bytes(16)), $k2, 0, '', $actor, '', 'TRZ0003');
}), 'Anula su escaneo'));
$sk = (string)$trz->query("SELECT scan_key FROM production_supply_scans WHERE request_key='$k2' AND code='TRZ0003'")->fetchColumn();
$svc->voidScan($sk, 'sobraba esta caja', $actor);
$svc->dispatch(bin2hex(random_bytes(16)), $k2, 0, '', $actor);
$check('sin elegir caja: se traspasa la caja completa (100) aunque se pidieron 50', (float)$box('TRZ0002')['units_qty'] === 100.0 && (int)$box('TRZ0002')['erp_st_id'] === 16 && $stock(20, 1) === 200.0);

// Producto sin etiquetas: por cantidad (traspaso ERP clásico).
$k3 = $newRequest(21, 'BOL0950', 10);
$svc->dispatch(bin2hex(random_bytes(16)), $k3, 10, '', $actor);
$check('producto sin etiquetas se traspasa por cantidad', $stock(21, 1) === 40.0 && $stock(21, 16) === 10.0 && $row($k3)['status'] === 'DISPATCHED');

// OT cerrada: la parte temporal queda esperando en la 3000 y el Pulpo la carga con un botón.
$erp->exec('UPDATE prod_worker_ot SET wok_status=2 WHERE id=7001');
$k4 = $newRequest(20, 'BOL0900', 150, 7001);
$svc->scan($k4, 'TRZ0003', $actor);
$svc->dispatch(bin2hex(random_bytes(16)), $k4, 0, '', $actor, '', 'TRZ0003');
$waiting = $pulpo->waitingPartials();
$check('con la OT cerrada, la parte temporal espera en la 3000 para cargarla sin escanear', count($waiting) === 1 && (float)$waiting[0]['units_qty'] === 150.0 && $waiting[0]['status'] === 'IN_WAREHOUSE');
$erp->exec("INSERT INTO prod_worker_ot (id,wok_ag_id,wok_init_id,wok_status) VALUES (7002,70,1,1)");
$pulpo->mount(7002, (string)$waiting[0]['box_code'], 'Operador');
$check('el botón «Cargar en la máquina» la deja en el Pulpo', $pulpo->waitingPartials() === [] && count($pulpo->mountedBoxes(7002)) === 1);
$check('cuadre: stock ERP de la 3000 = saldo de las cajas en la 3000', $stock(20, 16) === round((float)$trz->query("SELECT COALESCE(SUM(units_qty),0) FROM boxes WHERE erp_st_id=16 AND erp_item_id=20 AND status IN ('IN_WAREHOUSE','IN_MACHINE')")->fetchColumn(), 3));

echo "== Permiso LOCK TABLES (error 1044) ==\n";
class QaFakeMysql extends PDO
{
    public array $log = [];
    public bool $deny = true;
    public function getAttribute(int $a): mixed { return $a === PDO::ATTR_DRIVER_NAME ? 'mysql' : parent::getAttribute($a); }
    public function exec(string $s): int|false
    {
        $this->log[] = trim($s);
        if (str_starts_with(trim($s), 'LOCK TABLES') && $this->deny) {
            $e = new PDOException("SQLSTATE[42000]: Syntax error or access violation: 1044 Access denied for user 'unibag_qa_client'@'%' to database 'unibag_qa'");
            $e->errorInfo = ['42000', 1044, 'Access denied'];
            throw $e;
        }
        return str_starts_with(trim($s), 'LOCK TABLES') || trim($s) === 'UNLOCK TABLES' ? 0 : parent::exec($s);
    }
    public function query(string $s, ?int $m = null, mixed ...$a): PDOStatement|false
    {
        $this->log[] = $s;
        return parent::query(str_contains($s, 'GET_LOCK') || str_contains($s, 'RELEASE_LOCK') ? 'SELECT 1' : $s);
    }
}
$fake = new QaFakeMysql('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$svc2 = (new ReflectionClass(MaterialSupplyService::class))->newInstanceWithoutConstructor();
foreach (['erp' => $fake, 'trz' => $trz] as $p => $v) {
    $rp = new ReflectionProperty(MaterialSupplyService::class, $p);
    $rp->setAccessible(true);
    $rp->setValue($svc2, $v);
}
$lock = new ReflectionMethod($svc2, 'lockErp');
$lock->setAccessible(true);
$unlock = new ReflectionMethod($svc2, 'unlockErp');
$unlock->setAccessible(true);
$mode = $lock->invoke($svc2, 'storehousechanges READ');
$unlock->invoke($svc2, $mode);
$check('sin permiso LOCK TABLES (1044) usa el candado con nombre y lo libera', $mode === 'named' && in_array("SELECT RELEASE_LOCK('unibag_stock_ledger')", $fake->log, true));
$fake->deny = false;
$fake->log = [];
$mode = $lock->invoke($svc2, 'storehousechanges READ');
$unlock->invoke($svc2, $mode);
$check('con permiso sigue usando LOCK TABLES', $mode === 'tables' && in_array('UNLOCK TABLES', $fake->log, true));

echo "\n$total pruebas, $fail fallas\n";
exit($fail === 0 ? 0 : 1);
