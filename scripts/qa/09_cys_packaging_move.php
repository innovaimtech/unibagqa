<?php
declare(strict_types=1);
// Embalaje de Corte y Sellado: debe contar como Embalaje y no como sellado, cargar stock solo al cerrar uno nuevo y nunca en una recuperación.
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "pdo_sqlite no disponible: prueba omitida\n";
    exit(0);
}
$root = dirname(__DIR__, 2);
require_once $root . '/src/ProductionService.php';

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $fail++;
    }
};

$erp = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$trz = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$erp->exec("CREATE TABLE equipo (id INTEGER PRIMARY KEY, equipo_name TEXT, equipo_type_id INT);
CREATE TABLE prod_worker_init (id INTEGER PRIMARY KEY AUTOINCREMENT, win_crtdat INT, win_enddat INT, win_wrkid INT, win_status INT, win_plantaid INT, win_equipoid INT, win_ass_id INT, win_day INT, win_month INT, win_year INT);
CREATE TABLE prod_agenda (id INTEGER PRIMARY KEY AUTOINCREMENT, ag_date TEXT, ag_date_stamp INT, ag_equipo_id INT, ag_equipotype_id INT, ag_amount REAL, ag_prdid INT, ag_reqid INT, ag_plantaid INT, ag_crtdat INT, ag_crtusr INT, ag_status INT, ag_active INT);
CREATE TABLE prod_worker_ot (id INTEGER PRIMARY KEY AUTOINCREMENT, wok_ag_id INT, wok_init_id INT, wok_crtdat INT, wok_enddat INT, wok_status INT);
CREATE TABLE prod_worker_ot_events (id INTEGER PRIMARY KEY AUTOINCREMENT, evt_prod_worker_otid INT, evt_amount REAL, evt_crtdat INT, evt_enddat INT, evt_status INT, evt_type TEXT, evt_comments TEXT, overrideembalaje_act INT DEFAULT 0, overrideembalaje_selladora_evtrefid INT DEFAULT 0, evt_medida_fromid INT, evt_medida_toid INT, evt_pause_id INT, evt_equipo_mantid INT, evt_ubim_id INT);
CREATE TABLE prod_medidas (id INTEGER PRIMARY KEY, med_name TEXT);
CREATE TABLE prod_pause_types (id INTEGER PRIMARY KEY, pause_name TEXT);
CREATE TABLE equipo_manttype (id INTEGER PRIMARY KEY, mant_title TEXT, mant_code TEXT);
CREATE TABLE equipos_ubimants (id INTEGER PRIMARY KEY, ubim_title TEXT);
CREATE TABLE prod_worker_embalajes (id INTEGER PRIMARY KEY AUTOINCREMENT, evt_prod_worker_otid INT, evt_reqid INT, req_v2refid INT, req_codigo_cierre TEXT, req_id_bodega INT, req_embalaje_cajas_completas_amt INT, req_embalaje_bolsas_por_caja_amt INT, req_embalaje_caja_final INT, req_embalaje_bolsas_sobrantes_amt INT, req_embalaje_showroom INT);
CREATE TABLE orders (id INTEGER PRIMARY KEY, req_number TEXT, req_company_id INT, req_shop_id INT);
CREATE TABLE orders_items (id INTEGER PRIMARY KEY AUTOINCREMENT, req_id INT, item_id INT);
CREATE TABLE item (id INTEGER PRIMARY KEY, item_title TEXT);
CREATE TABLE company_data (id INTEGER PRIMARY KEY, company_numformat_stockchange TEXT, company_numcounter_stockchange INT);
CREATE TABLE stockchanges (id INTEGER PRIMARY KEY AUTOINCREMENT, stk_num TEXT, stk_annotation TEXT, stk_issueid INT, stk_companyid INT, stk_shopid INT, stk_bookdate INT, stk_negative INT, stk_status INT, stk_crtdat INT, stk_crtusr INT, stk_upddat INT, stk_updusr INT, stk_fixedsthid INT, sth_fromprodotid INT, sth_fromrebo_agid INT, sth_order_id INT, sth_order_num TEXT, stk_cinumber TEXT, stk_vintnumber TEXT, sth_fromrebo_agid_grphash TEXT);
CREATE TABLE stockchanges_items (id INTEGER PRIMARY KEY AUTOINCREMENT, stk_id INT, item_id INT, item_pos INT, item_amount REAL, item_type TEXT, item_st_id INT);
CREATE TABLE item_shops_storehouses (item_id INT, shop_id INT, st_id INT, iss_order_amount REAL, iss_inventory_min REAL, iss_inventory REAL, iss_inventory_reserved REAL, iss_order_id INT);
CREATE TABLE tran_data (id INTEGER PRIMARY KEY AUTOINCREMENT, tran_id INT, tran_pos INT, tran_type TEXT, tran_number TEXT, tran_currstock REAL, tran_amount REAL, tran_company_id INT, tran_shop_id INT, tran_st_id INT, tran_issueid INT, item_id INT, item_title TEXT, item_type TEXT, tran_day INT, tran_month INT, tran_year INT, tran_crtusr INT, tran_crtdat INT, tran_order_id INT);
INSERT INTO equipo VALUES (25,'EMBALAJE',15),(3,'SELLADORA VI.',8);
INSERT INTO orders VALUES (100,'26-00100-1',20010,30010);
INSERT INTO orders_items (req_id,item_id) VALUES (100,1309);
INSERT INTO item VALUES (1309,'BOLSA');
INSERT INTO company_data VALUES (20010,'IA',500);
INSERT INTO prod_worker_init (id,win_wrkid,win_status,win_plantaid,win_equipoid) VALUES (1,17,1,1,3);
INSERT INTO prod_agenda (id,ag_equipo_id,ag_equipotype_id,ag_amount,ag_prdid,ag_reqid,ag_plantaid) VALUES (1,3,8,5000,40,100,1);
INSERT INTO prod_worker_ot (id,wok_ag_id,wok_init_id,wok_crtdat,wok_status) VALUES (10,1,1,1,1);");

$service = new ProductionService($erp, $trz);
$ready = method_exists($service, 'finalizeCysPackagingEvent') && method_exists($service, 'linkCysPackagingEvent');
$check('existe el cierre que mueve el embalaje a la máquina Embalaje', $ready);

$start = strtotime('2026-11-03 14:05:00');
$end = strtotime('2026-11-03 14:40:00');
$addPacking = static function (int $units, int $warehouse = 8, string $closure = '10') use ($erp, $start, $end): int {
    $erp->prepare("INSERT INTO prod_worker_ot_events (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, overrideembalaje_act) VALUES (10, ?, ?, ?, 2, 'prod', 1)")
        ->execute([$units, $start, $end]);
    $id = (int)$erp->lastInsertId();
    $erp->prepare('INSERT INTO prod_worker_embalajes (evt_prod_worker_otid, evt_reqid, req_v2refid, req_codigo_cierre, req_id_bodega, req_embalaje_cajas_completas_amt, req_embalaje_bolsas_por_caja_amt, req_embalaje_caja_final, req_embalaje_bolsas_sobrantes_amt, req_embalaje_showroom) VALUES (10, 100, ?, ?, ?, ?, 100, ?, 0, 0)')
        ->execute([$id, $closure, $warehouse, intdiv($units, 100), $units % 100]);
    return $id;
};
// Lo mismo que leen los informes: producción por tipo de máquina de la agenda.
$byType = static function (int $type) use ($erp): float {
    $st = $erp->prepare("SELECT COALESCE(SUM(e.evt_amount),0) FROM prod_worker_ot_events e JOIN prod_worker_ot o ON o.id=e.evt_prod_worker_otid JOIN prod_agenda a ON a.id=o.wok_ag_id WHERE e.evt_type='prod' AND e.evt_status>0 AND a.ag_equipotype_id=?");
    $st->execute([$type]);
    return (float)$st->fetchColumn();
};
$stock = static function (int $warehouse = 8) use ($erp): float {
    $st = $erp->prepare('SELECT COALESCE(SUM(iss_inventory),0) FROM item_shops_storehouses WHERE item_id=1309 AND st_id=? AND iss_order_id=100');
    $st->execute([$warehouse]);
    return (float)$st->fetchColumn();
};
$count = static fn(string $table): int => (int)$erp->query("SELECT COUNT(*) FROM $table")->fetchColumn();

if ($ready) {
    // Producción de sellado de la misma OT: no debe cambiar.
    $erp->exec("INSERT INTO prod_worker_ot_events (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, overrideembalaje_act) VALUES (10, 3000, 1793700000, 1793703600, 2, 'prod', 0)");

    // Embalaje nuevo: se cierra con el arreglo.
    $new = $addPacking(1000);
    $check('antes del cierre: el embalaje sale como sellado', $byType(8) === 4000.0 && $byType(15) === 0.0);
    $service->finalizeCysPackagingEvent($new, 10, true);
    $check('cierre: las unidades salen de Corte y Sellado', $byType(8) === 3000.0);
    $check('cierre: las unidades entran a Embalaje', $byType(15) === 1000.0);
    $event = $erp->query("SELECT * FROM prod_worker_ot_events WHERE id=$new")->fetch();
    $check('cierre: conserva las fechas del evento', (int)$event['evt_crtdat'] === $start && (int)$event['evt_enddat'] === $end);
    $check('cierre: guarda el enlace con la OT de la selladora', (int)$event['overrideembalaje_selladora_evtrefid'] === 10);
    $row = $erp->query('SELECT o.wok_status, o.wok_crtdat, o.wok_enddat, a.ag_equipo_id, a.ag_equipotype_id, a.ag_active, a.ag_amount, a.ag_prdid, a.ag_reqid, i.win_wrkid, i.win_equipoid, i.win_status, i.win_day, i.win_month FROM prod_worker_ot o JOIN prod_agenda a ON a.id=o.wok_ag_id JOIN prod_worker_init i ON i.id=o.wok_init_id WHERE o.id=' . (int)$event['evt_prod_worker_otid'])->fetch();
    $check('cierre: la agenda es de Embalaje, oculta y del mismo pedido', (int)$row['ag_equipo_id'] === 25 && (int)$row['ag_equipotype_id'] === 15 && (int)$row['ag_active'] === 0 && (int)$row['ag_prdid'] === 40 && (int)$row['ag_reqid'] === 100 && (float)$row['ag_amount'] === 1000.0);
    $check('cierre: queda a nombre del operador y en el día real', (int)$row['win_wrkid'] === 17 && (int)$row['win_equipoid'] === 25 && (int)$row['win_status'] === 2 && (int)$row['wok_status'] === 2 && (int)$row['win_day'] === (int)date('j', $end) && (int)$row['win_month'] === (int)date('n', $end));
    $check('cierre: el detalle de cajas acompaña al evento', (int)$erp->query("SELECT evt_prod_worker_otid FROM prod_worker_embalajes WHERE req_v2refid=$new")->fetchColumn() === (int)$event['evt_prod_worker_otid']);

    // Consola de la selladora.
    $check('consola: encuentra el evento movido', ($service->getEventById($new, 10)['id'] ?? 0) == $new);
    $check('consola: encuentra el detalle de cajas', ($service->getPackagingEventDetail($new, 10)['req_v2refid'] ?? 0) == $new);
    $check('consola: lista el evento en la OT de la selladora', in_array($new, array_map('intval', array_column($service->getWorkOrderEvents(10), 'id')), true));
    $check('consola: otra OT no puede abrirlo', $service->getEventById($new, 99999) === null && $service->getPackagingEventDetail($new, 99999) === []);

    // Stock del embalaje nuevo.
    $check('stock: un embalaje nuevo entra a la bodega elegida', $stock() === 1000.0);
    $doc = $erp->query('SELECT * FROM stockchanges ORDER BY id DESC LIMIT 1')->fetch();
    $check('stock: documento igual al antiguo', $doc['stk_num'] === 'IA0000501' && (int)$doc['stk_status'] === 2 && (int)$doc['stk_issueid'] === 20 && (int)$doc['stk_negative'] === 0 && (int)$doc['stk_fixedsthid'] === 8 && (int)$doc['sth_order_id'] === 100 && $doc['sth_order_num'] === '26-00100-1');
    $tran = $erp->query('SELECT * FROM tran_data ORDER BY id DESC LIMIT 1')->fetch();
    $check('stock: kardex con el stock anterior', $tran['tran_type'] === 'stockchangeup' && (float)$tran['tran_currstock'] === 0.0 && (float)$tran['tran_amount'] === 1000.0 && (int)$tran['tran_order_id'] === 100 && (int)$tran['tran_st_id'] === 8);

    // Repetir no duplica.
    $before = [$count('prod_agenda'), $count('prod_worker_ot'), $count('prod_worker_init'), $count('stockchanges')];
    $service->finalizeCysPackagingEvent($new, 10, true);
    $check('repetir: no crea más registros ni carga más stock', $before === [$count('prod_agenda'), $count('prod_worker_ot'), $count('prod_worker_init'), $count('stockchanges')] && $stock() === 1000.0);

    // Corregir un embalaje ya cerrado: solo la diferencia.
    $erp->exec("UPDATE prod_worker_ot_events SET evt_amount=1200 WHERE id=$new");
    $erp->exec("UPDATE prod_worker_embalajes SET req_embalaje_cajas_completas_amt=12, evt_prod_worker_otid=10 WHERE req_v2refid=$new");
    $service->finalizeCysPackagingEvent($new, 10, false);
    $check('corrección: Embalaje y agenda toman la nueva cantidad', $byType(15) === 1200.0 && (float)$erp->query('SELECT ag_amount FROM prod_agenda ORDER BY id DESC LIMIT 1')->fetchColumn() === 1200.0);
    $check('corrección: el detalle vuelve a la OT de Embalaje', (int)$erp->query("SELECT evt_prod_worker_otid FROM prod_worker_embalajes WHERE req_v2refid=$new")->fetchColumn() === (int)$event['evt_prod_worker_otid']);
    $check('corrección: el stock suma solo la diferencia', $stock() === 1200.0 && (float)$erp->query('SELECT tran_amount FROM tran_data ORDER BY id DESC LIMIT 1')->fetchColumn() === 200.0);
    $erp->exec("UPDATE prod_worker_ot_events SET evt_amount=1100 WHERE id=$new");
    $erp->exec("UPDATE prod_worker_embalajes SET req_embalaje_cajas_completas_amt=11 WHERE req_v2refid=$new");
    $service->finalizeCysPackagingEvent($new, 10, false);
    $check('corrección a la baja: el stock descuenta la diferencia', $stock() === 1100.0 && $erp->query('SELECT tran_type FROM tran_data ORDER BY id DESC LIMIT 1')->fetchColumn() === 'stockchangedown');
    $erp->exec("UPDATE prod_worker_embalajes SET req_id_bodega=14 WHERE req_v2refid=$new");
    $service->finalizeCysPackagingEvent($new, 10, false);
    $check('cambio de bodega: el stock pasa a la bodega nueva', $stock(8) === 0.0 && $stock(14) === 1100.0);

    // Recuperación de un embalaje ya ajustado por bodega.
    $old = $addPacking(9520);
    $stockDocs = $count('stockchanges');
    $trans = $count('tran_data');
    $inventory = (float)$erp->query('SELECT COALESCE(SUM(iss_inventory),0) FROM item_shops_storehouses')->fetchColumn();
    $result = $service->linkCysPackagingEvent($old);
    $check('recuperación: mueve la producción a Embalaje', ($result['moved'] ?? false) === true && $byType(8) === 3000.0 && $byType(15) === 10620.0);
    $check('recuperación: conserva las fechas', (int)$erp->query("SELECT evt_enddat FROM prod_worker_ot_events WHERE id=$old")->fetchColumn() === $end);
    $check('recuperación: no toca stock ni kardex', $count('stockchanges') === $stockDocs && $count('tran_data') === $trans && (float)$erp->query('SELECT COALESCE(SUM(iss_inventory),0) FROM item_shops_storehouses')->fetchColumn() === $inventory);
    $check('recuperación: repetirla no duplica', ($service->linkCysPackagingEvent($old)['moved'] ?? true) === false && $byType(15) === 10620.0);
    $service->finalizeCysPackagingEvent($old, 10, false);
    $check('corregir un recuperado tampoco carga stock', $count('stockchanges') === $stockDocs);

    // Embalaje cerrado antes del arreglo y corregido antes de recuperarlo: se mueve, sin stock.
    $edited = $addPacking(700);
    $service->finalizeCysPackagingEvent($edited, 10, false);
    $check('corregir uno antiguo sin recuperar: se mueve y no carga stock', $count('stockchanges') === $stockDocs && $byType(15) === 11320.0);

    // Casos que no se mueven.
    $erp->exec("INSERT INTO prod_worker_ot_events (id, evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, overrideembalaje_act) VALUES (9001, 10, 0, 1, 2, 2, 'prod', 1), (9002, 10, 500, 1, 0, 1, 'prod', 1), (9003, 10, 500, 1, 2, 2, 'prod', 0)");
    $check('sin unidades: no se mueve', $service->linkCysPackagingEvent(9001) === null);
    $check('en curso: no se mueve', $service->linkCysPackagingEvent(9002) === null);
    $check('producción de sellado: no se mueve', $service->linkCysPackagingEvent(9003) === null);
    $erp->exec("INSERT INTO prod_worker_ot (id,wok_ag_id,wok_init_id,wok_crtdat,wok_status) VALUES (777,777,1,1,2);
INSERT INTO prod_agenda (id,ag_equipo_id,ag_equipotype_id,ag_amount,ag_prdid,ag_reqid,ag_plantaid) VALUES (777,25,15,800,40,100,1);
INSERT INTO prod_worker_ot_events (id, evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, overrideembalaje_act) VALUES (9004, 777, 800, 1, 2, 2, 'prod', 1);
INSERT INTO prod_worker_embalajes (evt_prod_worker_otid, evt_reqid, req_v2refid, req_codigo_cierre, req_id_bodega, req_embalaje_cajas_completas_amt, req_embalaje_bolsas_por_caja_amt, req_embalaje_caja_final, req_embalaje_bolsas_sobrantes_amt, req_embalaje_showroom) VALUES (777,100,9004,'10',8,8,100,0,0,0);");
    $docs = $count('stockchanges');
    $service->finalizeCysPackagingEvent(9004, 777, true);
    $check('máquina Embalaje: no se mueve ni carga stock aquí', $count('stockchanges') === $docs && (int)$erp->query('SELECT evt_prod_worker_otid FROM prod_worker_ot_events WHERE id=9004')->fetchColumn() === 777);

    // Cierre mixto: en stock entra lo del CC principal.
    $mixed = $addPacking(700, 14, '50');
    $inv = $stock(14);
    $erp->exec("UPDATE prod_worker_ot_events SET evt_amount=1500 WHERE id=$mixed");
    $service->finalizeCysPackagingEvent($mixed, 10, true);
    $check('cierre mixto: Embalaje toma el total y el stock solo el CC principal', $stock(14) === $inv + 700.0 && $byType(15) === 13620.0);

    // Un error no impide terminar el cierre: queda como aviso.
    $erp->exec('DROP TABLE company_data');
    $failing = $addPacking(300);
    $service->finalizeCysPackagingEvent($failing, 10, true);
    $check('stock con error: la producción igual se mueve y avisa', $byType(15) === 13920.0 && str_contains($service->getLastSupplyConsumptionWarning(), 'stock'));
}

$module = (string)file_get_contents($root . '/src/Http/Production/ProductionEventsModule.php');
$check('el cierre de embalaje llama al arreglo y distingue uno que se cierra ahora', str_contains($module, 'finalizeCysPackagingEvent((int)$productionResult[\'event_id\'], $workerOtId, $packagingClosingNow)'));
$check('consola: ya no exige que el evento siga en la OT de la selladora', !str_contains($module, "(int)\$event['evt_prod_worker_otid'] !== \$workerOtId") && !str_contains($module, "(int)\$selectedEvent['evt_prod_worker_otid'] === \$workerOtId"));
$repair = (string)@file_get_contents($root . '/scripts/repair_cys_packaging_events.php');
$check('script de recuperación: solo usa el movimiento, sin stock', str_contains($repair, 'linkCysPackagingEvent(') && !str_contains($repair, 'finalizeCysPackagingEvent(') && !str_contains($repair, 'item_shops_storehouses'));
$check('script de recuperación: sin --apply no escribe', str_contains($repair, "'--apply'"));

echo $fail === 0 ? "\nEMBALAJE CORTE Y SELLADO OK\n" : "\nEMBALAJE CORTE Y SELLADO: $fail fallas\n";
exit($fail === 0 ? 0 : 1);
