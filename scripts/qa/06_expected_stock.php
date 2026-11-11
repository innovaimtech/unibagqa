<?php
declare(strict_types=1);
// Prueba funcional (sqlite en memoria) del servicio de Stock esperado: cálculo, estados, maestro e informe.
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "pdo_sqlite no disponible: prueba omitida\n";
    exit(0);
}
require_once dirname(__DIR__, 2) . '/src/WarehouseVisibility.php';
require_once dirname(__DIR__, 2) . '/src/ExpectedStockService.php';

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $fail++;
    }
};
$throws = function (callable $fn, string $needle = '') {
    try {
        $fn();
    } catch (Throwable $e) {
        return $needle === '' || stripos($e->getMessage(), $needle) !== false;
    }
    return false;
};

// ---- cálculo puro
$cfg = ['warehouse_code' => 1000, 'units_per_box' => 100, 'boxes_per_pallet' => 10, 'target_units' => 5000, 'min_units' => 1000];
$c = ExpectedStockService::compute($cfg, 3250, true);
$check('3250 un = 3 pallets + 2 cajas + 50 un', $c['pallets'] === 3 && $c['boxes'] === 2 && $c['loose'] === 50 && $c['saldo'] === '3 pallets + 2 cajas + 50 un');
$check('diferencia contra objetivo', $c['diff'] === -1750 && $c['diff_pallets'] === -1.8 && $c['status'] === 'Bajo');
$check('stock 0 no divide por cero', ExpectedStockService::compute($cfg, 0, true)['pallets'] === 0 && ExpectedStockService::compute($cfg, 0, true)['status'] === 'Crítico');
$check('menos de una caja', ExpectedStockService::compute($cfg, 99, true)['boxes'] === 0 && ExpectedStockService::compute($cfg, 99, true)['loose'] === 99);
$check('exacto en pallets', ExpectedStockService::compute($cfg, 2000, true)['saldo'] === '2 pallets + 0 cajas + 0 un');
$check('bajo el mínimo es Crítico', ExpectedStockService::compute($cfg, 999, true)['status'] === 'Crítico');
$check('sobre el objetivo es OK', ExpectedStockService::compute($cfg, 5000, true)['status'] === 'OK');
$check('sin objetivo ni mínimo', ExpectedStockService::compute(['warehouse_code' => 400, 'units_per_box' => 50, 'boxes_per_pallet' => 4], 10, true)['status'] === 'Sin objetivo');
$check('sin embalaje: falta configurar y sin pallets', ExpectedStockService::compute(['warehouse_code' => 400], 10, true)['status'] === 'Falta configurar'
    && ExpectedStockService::compute(['warehouse_code' => 400], 10, true)['pallets'] === null);
$check('sin bodega: falta configurar', ExpectedStockService::compute(['units_per_box' => 1, 'boxes_per_pallet' => 1], 10, true)['status'] === 'Falta configurar');
$check('código inexistente en ERP', ExpectedStockService::compute($cfg, null, false)['status'] === 'No existe en ERP');

// ---- base
$erp = new PDO('sqlite::memory:');
$trz = new PDO('sqlite::memory:');
foreach ([$erp, $trz] as $p) {
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
}
$erp->exec("CREATE TABLE item (id INTEGER PRIMARY KEY, item_number_prod TEXT, item_title TEXT);
CREATE TABLE item_shops_storehouses (id INTEGER PRIMARY KEY, item_id INTEGER, st_id INTEGER, iss_order_id INTEGER DEFAULT 0, iss_inventory REAL, iss_inventory_reserved REAL);
CREATE TABLE company_shops_storehouses (id INTEGER PRIMARY KEY, st_name TEXT, st_status INTEGER);
INSERT INTO company_shops_storehouses VALUES (10,'1000 (PROD TERMINADOS)',1),(11,'400 (PROD TERMINADOS REVENTA)',1),(12,'3000 INSUMOS',1),(13,'1000 VIEJA',0);
INSERT INTO item VALUES (1,'BOL0250','BOLSA CRUZ VERDE ERP'),(2,'BOL0251','BOLSA EASY ERP'),(3,'ZZZ9999','PRODUCTO NUEVO ERP');
INSERT INTO item_shops_storehouses (item_id,st_id,iss_inventory,iss_inventory_reserved) VALUES
 (1,10,2500,100),(1,10,500,0),(1,11,50,0),(1,12,9999,0),(1,13,777,0),(2,11,300,0);");

$svc = new ExpectedStockService($erp, $trz);
$actor = ['name' => 'Tester'];

// ---- carga inicial
$m = $svc->master(false);
$check('42 códigos iniciales', count($m) === 42);
$check('sin códigos duplicados', count(array_unique(array_column($m, 'item_code'))) === 42);
$check('códigos y descripciones de la lista', (function () use ($m) {
    $by = array_column($m, 'description', 'item_code');
    return ($by['BOL0250'] ?? '') === 'CRUZ VERDE' && ($by['ONL0172'] ?? '') === 'PP BOU 30X40X11 NEGRO' && ($by['BOL0388'] ?? '') === 'SALCOBRAND ALGODÓN 38X42';
})());
$svc2 = new ExpectedStockService($erp, $trz);
$svc2->master(true);
$check('la carga inicial es idempotente', count($svc->master(false)) === 42);
$check('todo parte sin configurar', count(array_filter($m, fn($r) => $r['warehouse_code'] !== null || $r['units_per_box'] !== null)) === 0);

// ---- guardar maestro
$id250 = (int)array_column($m, 'id', 'item_code')['BOL0250'];
$id251 = (int)array_column($m, 'id', 'item_code')['BOL0251'];
$n = $svc->saveRows([
    $id250 => ['warehouse_code' => '1000', 'units_per_box' => '100', 'boxes_per_pallet' => '10', 'target_units' => '5000', 'min_units' => '1000'],
    $id251 => ['warehouse_code' => '1000', 'units_per_box' => '50', 'boxes_per_pallet' => '20'],
], $actor);
$check('guardar cuenta los campos cambiados', $n === 8);
$check('el historial registra quién y qué', count($svc->history()) === 8 && $svc->history()[0]['actor'] === 'Tester');
$check('guardar sin cambios no registra nada', $svc->saveRows([$id250 => ['warehouse_code' => '1000', 'units_per_box' => '100']], $actor) === 0 && count($svc->history()) === 8);
$check('bodega inválida se rechaza', $throws(fn() => $svc->saveRows([$id250 => ['warehouse_code' => '500']], $actor), 'bodega'));
$check('texto en número se rechaza', $throws(fn() => $svc->saveRows([$id250 => ['target_units' => 'abc']], $actor), 'número'));
$check('limpiar un campo lo deja vacío', $svc->saveRows([$id250 => ['min_units' => '']], $actor) === 1
    && $trz->query("SELECT min_units FROM expected_stock_master WHERE id=$id250")->fetchColumn() === null);
$svc->saveRows([$id250 => ['min_units' => '1000']], $actor);

// ---- informe con stock ERP
$rep = $svc->report();
$by = array_column($rep['rows'], null, 'code');
$check('informe trae los 42 activos', count($rep['rows']) === 42 && $rep['erp_ok']);
$check('stock = suma de las filas de la bodega 1000 (activa) y excluye 3000 y bodegas inactivas', $by['BOL0250']['stock'] === 3000);
$check('sin tablas de NV el stock se muestra igual y avisa', $rep['flow_ok'] === false && $rep['flow_error'] !== '' && $by['BOL0250']['reserved'] === null && $by['BOL0250']['stock'] === 3000);
$check('pallets y saldo con el stock real', $by['BOL0250']['pallets'] === 3 && $by['BOL0250']['boxes'] === 0 && $by['BOL0250']['loose'] === 0);
$check('otra bodega muestra lo de la 400', $by['BOL0250']['other_stock'] === 50);
$check('estado contra objetivo', $by['BOL0250']['status'] === 'Bajo' && $by['BOL0250']['diff'] === -2000);
$check('stock de la bodega asignada, no de otra', $by['BOL0251']['stock'] === 0 && $by['BOL0251']['other_stock'] === 300);
$check('código del maestro que no existe en ERP', $by['BOL0247']['status'] === 'No existe en ERP' || $by['BOL0247']['status'] === 'Falta configurar');
$check('nombre ERP disponible', $by['BOL0250']['erp_title'] === 'BOLSA CRUZ VERDE ERP');

// ---- agregar códigos
$r = $svc->addItem('zzz9999', '', $actor);
$check('agregar toma la descripción del ERP', $r['code'] === 'ZZZ9999' && $r['exists_in_erp'] === true
    && $trz->query("SELECT description FROM expected_stock_master WHERE item_code='ZZZ9999'")->fetchColumn() === 'PRODUCTO NUEVO ERP');
$check('código repetido se rechaza', $throws(fn() => $svc->addItem('ZZZ9999', 'x', $actor), 'ya está'));
$check('código fuera del ERP sin descripción se rechaza', $throws(fn() => $svc->addItem('NOP0001', '', $actor), 'descripción'));
$check('código fuera del ERP con descripción se avisa', $svc->addItem('NOP0002', 'Otro producto', $actor)['exists_in_erp'] === false);

// ---- pegar lista
$imp = $svc->importList("PRODUCTO A\tBOL5001\nBOL5002\tPRODUCTO B\n\nBOL5001\tDUPLICADO\nsin codigo aqui\n", $actor);
$check('pegar lista agrega ambos formatos', $imp['added'] === 2 && $trz->query("SELECT description FROM expected_stock_master WHERE item_code='BOL5002'")->fetchColumn() === 'PRODUCTO B');
$check('pegar lista informa lo no agregado', count($imp['skipped']) === 2);

// ---- desactivar
$svc->saveRows([$id251 => ['active' => '0']], $actor);
$check('inactivos salen del informe', !isset(array_column($svc->report()['rows'], null, 'code')['BOL0251']));

// ---- NV sin despachar (reservado) y en producción
$erp->exec("CREATE TABLE customer (id INTEGER PRIMARY KEY, cust_name TEXT);
CREATE TABLE user (id INTEGER PRIMARY KEY, user_firstname TEXT, user_lastname TEXT);
CREATE TABLE orders (id INTEGER PRIMARY KEY, req_number TEXT, req_status INT, req_order_shipped INT DEFAULT 0, req_crtdat INT, req_cust_id INT,
  req_fec_despacho INT DEFAULT 0, req_oc_number TEXT DEFAULT '', req_userid_seller INT DEFAULT 0, req_isreserva INT DEFAULT 0, req_isfabricate INT DEFAULT 0);
CREATE TABLE orders_items (id INTEGER PRIMARY KEY, req_id INT, item_id INT, item_type TEXT DEFAULT 'item', item_amount REAL, item_amount_shipped REAL DEFAULT 0, item_amount_shipped_stop INT DEFAULT 0, fab_printtype VARCHAR(10) DEFAULT 'FLEX');
CREATE TABLE orders_delivery (id INTEGER PRIMARY KEY, dlv_order_id INT, dlv_status INT, dlv_delivery_date INT);
CREATE TABLE orders_delivery_items (id INTEGER PRIMARY KEY, dlv_id INT, item_id INT, item_amount_shipped REAL);
CREATE TABLE prod_header (id INTEGER PRIMARY KEY, prd_reqid INT, prd_number TEXT, prd_status INT);
CREATE TABLE prod_amtplan (id INTEGER PRIMARY KEY, prodplan_prdid INT, prodplan_amt REAL, prodplan_date INT);
CREATE TABLE equipo (id INTEGER PRIMARY KEY, equipo_name TEXT, equipo_type_id INT);
CREATE TABLE prod_agenda (id INTEGER PRIMARY KEY, ag_reqid INT, ag_prdid INT, ag_equipo_id INT, ag_equipotype_id INT, ag_date TEXT, ag_amount REAL, ag_status INT, ag_date_stamp INT DEFAULT 0);
CREATE TABLE prod_worker_init (id INTEGER PRIMARY KEY, win_wrkid INT, win_status INT DEFAULT 1);
CREATE TABLE workers (id INTEGER PRIMARY KEY, wrk_firstname TEXT, wrk_lastname TEXT);
CREATE TABLE prod_worker_ot (id INTEGER PRIMARY KEY, wok_ag_id INT, wok_status INT, wok_init_id INT, wok_crtdat INT DEFAULT 0);
CREATE TABLE prod_worker_ot_events (id INTEGER PRIMARY KEY, evt_prod_worker_otid INT, evt_type TEXT, evt_status INT, evt_amount REAL, evt_crtdat INT, evt_comments TEXT);
CREATE TABLE prod_worker_embalajes (id INTEGER PRIMARY KEY, evt_reqid INT, evt_crtdat INT, req_embalaje_cajas_completas_amt INT, req_embalaje_bolsas_por_caja_amt INT,
  req_embalaje_caja_final INT, req_embalaje_bolsas_sobrantes_amt INT DEFAULT 0, req_embalaje_showroom INT DEFAULT 0);");
$day = 86400;
$now = time();
$past = $now - 5 * $day;
$future = $now + 10 * $day;
$old = $now - 200 * $day;
$erp->exec("INSERT INTO customer VALUES (1,'CRUZ VERDE SA'),(2,'EASY SA');
INSERT INTO user VALUES (7,'Ana','Ventas');
INSERT INTO workers VALUES (9,'Juan','Operador');
INSERT INTO equipo VALUES (25,'EMBALAJE',15),(30,'SELLADORA 2',8),(40,'FLEXO III',7);
-- NV 1: aprobada, 1000 pedido, 300 despachado -> pendiente 700, vencida; con OT y embalado 400 -> por fabricar 600
INSERT INTO orders VALUES (101,'26-00101-1',3,0,$past,1,$past,'OC-55',7,0,0);
INSERT INTO orders_items (req_id,item_id,item_amount,item_amount_shipped) VALUES (101,1,1000,300);
INSERT INTO orders_delivery VALUES (1,101,2,$past),(2,101,5,$now);
INSERT INTO orders_delivery_items (dlv_id,item_id,item_amount_shipped) VALUES (1,1,300),(2,1,999);
INSERT INTO prod_header VALUES (501,101,'001234',2);
INSERT INTO prod_amtplan VALUES (1,501,500,$future);
-- Agenda: flexo de hace 5 días (ya no vigente), selladora y embalaje de hoy (vigentes)
INSERT INTO prod_agenda VALUES (601,101,501,40,7,'01.11.2026',5000,1,$past),(602,101,501,30,8,'03.11.2026',1000,1,$now),(603,101,501,25,15,'05.11.2026',1000,1,$now);
-- CC 8 agendada hace 5 días y no reagendada -> no está planificada
INSERT INTO prod_agenda VALUES (604,108,503,30,8,'01.11.2026',400,1,$past);
INSERT INTO prod_worker_init (id,win_wrkid,win_status) VALUES (1,9,1),(2,9,2);
-- 704: OT que quedó abierta con el turno ya cerrado -> NO está en curso
INSERT INTO prod_worker_ot (id,wok_ag_id,wok_status,wok_init_id,wok_crtdat) VALUES (701,601,2,1,$past),(702,602,1,1,$now),(703,603,2,1,$now),(704,601,1,2,$past);
INSERT INTO prod_worker_ot_events (evt_prod_worker_otid,evt_type,evt_status,evt_amount,evt_crtdat,evt_comments) VALUES
 (701,'prod',2,4800,$past,''),(702,'prod',2,450,$now,''),(702,'prod',0,999,$now,''),(703,'prod',2,400,$now,''),(701,'prod',2,77,$now,'Bobina Semielaborada X');
INSERT INTO prod_worker_embalajes (evt_reqid,evt_crtdat,req_embalaje_cajas_completas_amt,req_embalaje_bolsas_por_caja_amt,req_embalaje_caja_final,req_embalaje_bolsas_sobrantes_amt,req_embalaje_showroom) VALUES
 (101,$now,3,100,50,30,20);
INSERT INTO item_shops_storehouses (item_id,st_id,iss_order_id,iss_inventory,iss_inventory_reserved) VALUES (1,10,101,120,0);
-- CC 2 de fabricación, sin OT: todo el pedido por fabricar (nueva), NO es venta (no reservado)
INSERT INTO orders VALUES (102,'26-00102-1',2,0,$now,2,$future,'',0,1,1);
INSERT INTO orders_items (req_id,item_id,item_amount) VALUES (102,1,200);
-- NV 3: despachada (estado 4) -> no cuenta
INSERT INTO orders VALUES (103,'26-00103-1',4,1,$past,1,0,'',0,0,0);
INSERT INTO orders_items (req_id,item_id,item_amount) VALUES (103,1,9999);
-- NV 4: borrador (estado 1) -> no cuenta
INSERT INTO orders VALUES (104,'26-00104-1',1,0,$past,1,0,'',0,0,1);
INSERT INTO orders_items (req_id,item_id,item_amount) VALUES (104,1,8888);
-- NV 5: línea cerrada sin despachar -> no cuenta
INSERT INTO orders VALUES (105,'26-00105-1',2,0,$past,1,0,'',0,0,0);
INSERT INTO orders_items (req_id,item_id,item_amount,item_amount_shipped_stop) VALUES (105,1,7777,1);
-- NV 6: de stock (sin OT ni marcada): reservado sí, producción no
INSERT INTO orders VALUES (106,'26-00106-1',2,0,$now,2,0,'',0,0,0);
INSERT INTO orders_items (req_id,item_id,item_amount) VALUES (106,1,50);
-- NV 7: con OT pero embalada de más -> producción 0 (no negativa)
INSERT INTO orders VALUES (107,'26-00107-1',3,0,$past,1,0,'',0,0,0);
INSERT INTO orders_items (req_id,item_id,item_amount,item_amount_shipped) VALUES (107,1,100,100);
INSERT INTO prod_header VALUES (502,107,'001235',2);
INSERT INTO prod_worker_embalajes (evt_reqid,evt_crtdat,req_embalaje_cajas_completas_amt,req_embalaje_bolsas_por_caja_amt,req_embalaje_caja_final) VALUES (107,$now,2,100,0);
-- NV 8: dos productos (BOL0250 y BOL0251) en la misma CC, embalado repartido por lo pedido
INSERT INTO orders VALUES (108,'26-00108-1',2,0,$now,2,0,'',0,0,0);
INSERT INTO orders_items (req_id,item_id,item_amount) VALUES (108,1,300),(108,2,100);
INSERT INTO prod_header VALUES (503,108,'001236',2);
INSERT INTO prod_worker_embalajes (evt_reqid,evt_crtdat,req_embalaje_cajas_completas_amt,req_embalaje_bolsas_por_caja_amt,req_embalaje_caja_final) VALUES (108,$now,2,100,0);
-- NV 9: venta sin marcar despachado en la NV, pero con guía de 200 -> pendiente 300
INSERT INTO orders VALUES (109,'NV0000109',2,0,$now,2,0,'',0,0,0);
INSERT INTO orders_items (req_id,item_id,item_amount) VALUES (109,1,500);
INSERT INTO orders_delivery VALUES (3,109,2,$now);
INSERT INTO orders_delivery_items (dlv_id,item_id,item_amount_shipped) VALUES (3,1,200);
-- NV 10: venta de hace 200 días abierta en el ERP -> no se cuenta, se informa aparte
INSERT INTO orders VALUES (110,'NV0000110',2,0,$old,1,0,'',0,0,0);
INSERT INTO orders_items (req_id,item_id,item_amount) VALUES (110,1,999);
-- CC 11 de fabricación con OT, saldo 60 y sin movimiento hace 100 días -> no se cuenta, se informa aparte
INSERT INTO orders VALUES (111,'25-00111-1',2,0,$old,1,0,'',0,0,1);
INSERT INTO orders_items (req_id,item_id,item_amount) VALUES (111,1,100);
INSERT INTO prod_header VALUES (504,111,'000999',2);
INSERT INTO prod_worker_embalajes (evt_reqid,evt_crtdat,req_embalaje_cajas_completas_amt,req_embalaje_bolsas_por_caja_amt,req_embalaje_caja_final) VALUES (111,$now-100*$day,0,0,40);
-- CC 12 de fabricación con 96 % embalado hoy -> terminada (saldo de merma), se informa aparte
INSERT INTO orders VALUES (112,'26-00112-1',2,0,$now,1,0,'',0,0,1);
INSERT INTO orders_items (req_id,item_id,item_amount) VALUES (112,1,1000);
INSERT INTO prod_worker_embalajes (evt_reqid,evt_crtdat,req_embalaje_cajas_completas_amt,req_embalaje_bolsas_por_caja_amt,req_embalaje_caja_final) VALUES (112,$now,0,0,960);");
$svc->saveRows([$id251 => ['active' => '1']], $actor);
$rep = $svc->report();
$by = array_column($rep['rows'], null, 'code');
$b = $by['BOL0250'];
// vendido sin despachar: 700 (NV1) + 50 (NV6) + 300 (NV8) + 300 (NV9) = 1350; NO la CC 2 de fabricación ni la NV 10 antigua
$check('vendido sin despachar = solo NV de venta recientes (excluye CC de fabricación, despachadas, borrador, líneas cerradas y antiguas)', $rep['flow_ok'] === true && $b['reserved'] === 1350 && $b['reserved_orders'] === 4);
$check('libre = stock − reservado', $b['free'] === $b['stock'] - 1350);
// NV1: impreso 4.800 (≥ pedido) → por imprimir 0; sellado máx(450, embalado 400) → por cortar y sellar 1000−450 = 550
// CC2: sin máquinas, tipo FLEX → por imprimir 200 · NV8: ya embalado sin impresión → por cortar y sellar 300−150 = 150
$check('por imprimir y por cortar y sellar de CC vivas (excluye la CC sin movimiento y la de merma)', $b['to_print'] === 200 && $b['to_seal'] === 700
    && $b['in_production'] === 900 && $b['production_orders'] === 3);
$check('la CC con dos productos reparte lo embalado', $by['BOL0251']['in_production'] === 50);
// Planificado: solo la selladora de hoy de la CC 1 (1.000 agendadas, tope lo que falta sellar 550); el embalaje no suma y la CC 8 no se reagendó
$check('planificado en máquinas = agenda vigente con tope de lo que falta', $b['planned'] === 550 && $b['planned_orders'] === 1);
$check('proyectado (para el estado) = stock + planificado − reservado', $b['projected'] === $b['stock'] + 550 - 1350);
$check('estado proyectado calculado', $b['projected_status'] !== '');
$check('la fecha de entrega más próxima se informa', $b['oldest_due'] === date('Y-m-d', $past));

$rd = $svc->reservedDetail('bol0250');
$rbyNv = array_column($rd['rows'], null, 'nv');
$check('detalle de vendido: 4 NV con totales', count($rd['rows']) === 4 && $rd['totals']['pending'] === 1350 && $rd['totals']['orders'] === 4);
$check('lo despachado por guía descuenta aunque la NV no lo marque', ($rbyNv['NV0000109']['shipped'] ?? 0) === 200 && ($rbyNv['NV0000109']['pending'] ?? 0) === 300 && ($rbyNv['NV0000109']['guides'] ?? 0) === 1);
$check('las NV antiguas abiertas se informan aparte sin contarse', count($rd['excluded']) === 1 && ($rd['excluded'][0]['nv'] ?? '') === 'NV0000110' && $rd['excluded_totals']['pending'] === 999);
$nv1 = $rbyNv['26-00101-1'] ?? [];
$check('detalle NV: cliente, vendedor, OC, vencida y despacho parcial', ($nv1['customer'] ?? '') === 'CRUZ VERDE SA' && ($nv1['seller'] ?? '') === 'Ana Ventas'
    && ($nv1['oc'] ?? '') === 'OC-55' && ($nv1['overdue'] ?? false) === true && ($nv1['status'] ?? '') === 'Despacho parcial');
$check('detalle NV: guías sin anuladas, stock asignado y OT', ($nv1['guides'] ?? 0) === 1 && ($nv1['stock'] ?? 0) === 120 && ($nv1['ots'] ?? '') === '001234'
    && ($nv1['pending'] ?? 0) === 700 && ($nv1['shipped'] ?? 0) === 300);
$check('detalle NV: la más atrasada primero', ($rd['rows'][0]['nv'] ?? '') === '26-00101-1');
$check('detalle trae el resumen del SKU', ($rd['summary']['reserved'] ?? null) === 1350);

$pd = $svc->productionDetail('BOL0250');
$pbyCc = array_column($pd['rows'], null, 'cc');
$check('detalle de producción: 3 CC con pendiente y totales', count($pd['rows']) === 3 && $pd['totals']['pending'] === 900 && $pd['totals']['to_print'] === 200 && $pd['totals']['to_seal'] === 700 && !isset($pbyCc['26-00107-1']));
$cc1 = $pbyCc['26-00101-1'] ?? [];
$check('detalle CC: OT, embalado, avance y entrega planificada', ($cc1['ot'] ?? '') === '001234' && ($cc1['packed'] ?? 0) === 400 && ($cc1['printed'] ?? 0) === 4800 && ($cc1['sealed'] ?? 0) === 450 && ($cc1['to_seal'] ?? 0) === 550 && ($cc1['progress'] ?? 0) === 45
    && ($cc1['next_plan'] ?? '') === date('Y-m-d', $future) && count($cc1['plans'] ?? []) === 1);
$check('detalle CC: etapa actual = máquina con OT abierta y su operador', str_contains($cc1['stage'] ?? '', 'Corte y Sellado') && str_contains($cc1['stage'] ?? '', 'Juan Operador'));
$ags = array_column($cc1['agendas'] ?? [], null, 'machine');
$check('detalle CC: agendas por máquina con producido (sin anulados ni semielaborada) y unidad', ($ags['SELLADORA 2']['produced'] ?? 0) === 450 && ($ags['FLEXO III']['produced'] ?? 0) === 4800
    && ($ags['FLEXO III']['unit'] ?? '') === 'u' && ($ags['FLEXO III']['state'] ?? '') === 'Con producción' && ($ags['SELLADORA 2']['state'] ?? '') === 'En curso' && ($ags['EMBALAJE']['process'] ?? '') === 'Embalaje');
$cc2 = $pbyCc['26-00102-1'] ?? [];
$check('detalle CC sin OT: «Sin programar» con todo el pedido', ($cc2['stage'] ?? '') === 'Sin programar' && ($cc2['to_print'] ?? 0) === 200 && ($cc2['to_seal'] ?? 1) === 0 && ($cc2['ot'] ?? 'x') === '');
$check('detalle CC con varios productos lo marca', ($pbyCc['26-00108-1']['multi_product'] ?? false) === true && ($pbyCc['26-00108-1']['pending'] ?? 0) === 150);
$check('las CC con saldo sin movimiento se informan aparte sin contarse', count($pd['excluded']) === 2 && $pd['excluded_totals']['pending'] === 100
    && str_contains((string)(array_column($pd['excluded'], 'reason', 'cc')['25-00111-1'] ?? ''), 'Sin producción')
    && str_contains((string)(array_column($pd['excluded'], 'reason', 'cc')['26-00112-1'] ?? ''), 'merma'));
$check('detalle CC: planificado por CC y agenda vigente marcada', ($cc1['planned'] ?? 0) === 550 && ($ags['SELLADORA 2']['in_plan'] ?? false) === true && ($ags['FLEXO III']['in_plan'] ?? true) === false
    && ($pbyCc['26-00108-1']['planned'] ?? 1) === 0 && $pd['totals']['planned'] === 550);

// Una CC sin movimiento hace 100 días pero agendada hoy sí cuenta (está en máquina).
$erp->exec("INSERT INTO prod_agenda VALUES (605,111,504,30,8,'10.11.2026',30,1,$now)");
$b2 = array_column((new ExpectedStockService($erp, $trz))->report()['rows'], null, 'code')['BOL0250'];
$check('CC sin movimiento pero agendada hoy cuenta', $b2['planned'] === 580 && $b2['production_orders'] === 4);
// Si hoy aún no se planifica (lunes temprano), cuenta la agenda del último día planificado.
$erp->exec('UPDATE prod_agenda SET ag_date_stamp = ag_date_stamp - ' . (3 * $day));
$b3 = array_column((new ExpectedStockService($erp, $trz))->report()['rows'], null, 'code')['BOL0250'];
$check('sin agenda hoy: usa el último día planificado', $b3['planned'] === 580);

$check('detalle de código inexistente avisa', $throws(fn() => $svc->productionDetail('NOP9999'), 'no existe'));

// ---- ERP caído
$bad = new PDO('sqlite::memory:');
$bad->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$bad->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$down = (new ExpectedStockService($bad, $trz))->report();
$check('sin ERP el informe no se cae y lo avisa', $down['erp_ok'] === false && $down['error'] !== '' && count($down['rows']) > 0 && $down['rows'][0]['status'] === 'Sin conexión ERP');

echo $fail === 0 ? "\nSTOCK ESPERADO OK\n" : "\nSTOCK ESPERADO: $fail fallas\n";
exit($fail === 0 ? 0 : 1);
