<?php
declare(strict_types=1);
// Embalaje: una clave errónea no cierra nada, eliminar devuelve el stock, una OT con solo embalaje exige supervisor y la revisión detecta fallas.
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "pdo_sqlite no disponible: prueba omitida\n";
    exit(0);
}
$root = dirname(__DIR__, 2);
require_once $root . '/src/bootstrap.php';
require_once $root . '/src/CysPackagingAudit.php';

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $fail++;
    }
};

$erp = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$erp->exec("CREATE TABLE user (id INTEGER PRIMARY KEY, user_login TEXT, user_pass TEXT, user_status INT, user_type INT);
CREATE TABLE user_group (user_id INT, group_id INT);
CREATE TABLE company_shops_storehouses (id INTEGER PRIMARY KEY, st_name TEXT, st_status INT);
CREATE TABLE prod_worker_init (id INTEGER PRIMARY KEY AUTOINCREMENT, win_crtdat INT, win_enddat INT, win_wrkid INT, win_status INT, win_plantaid INT, win_equipoid INT, win_ass_id INT, win_day INT, win_month INT, win_year INT);
CREATE TABLE prod_agenda (id INTEGER PRIMARY KEY AUTOINCREMENT, ag_date TEXT, ag_date_stamp INT, ag_equipo_id INT, ag_equipotype_id INT, ag_amount REAL, ag_prdid INT, ag_reqid INT, ag_plantaid INT, ag_crtdat INT, ag_crtusr INT, ag_status INT, ag_active INT);
CREATE TABLE prod_worker_ot (id INTEGER PRIMARY KEY AUTOINCREMENT, wok_ag_id INT, wok_init_id INT, wok_crtdat INT, wok_enddat INT, wok_status INT);
CREATE TABLE equipo (id INTEGER PRIMARY KEY, equipo_name TEXT, equipo_type_id INT);
CREATE TABLE prod_worker_ot_events (id INTEGER PRIMARY KEY, evt_prod_worker_otid INT, evt_amount REAL, evt_crtdat INT, evt_enddat INT, evt_status INT, evt_type TEXT, evt_comments TEXT, overrideembalaje_act INT DEFAULT 0, overrideembalaje_selladora_evtrefid INT DEFAULT 0, prod_bobina_kg REAL DEFAULT 0, evt_amount_metros_lineales INT DEFAULT 0, evt_amount_metros_maquina INT DEFAULT 0);
CREATE TABLE prod_worker_ot_defectunits (evt_refid INT, evt_status INT, evt_amount REAL, evt_kgstounits REAL, evt_mtstounits REAL);
CREATE TABLE prod_worker_embalajes (id INTEGER PRIMARY KEY, evt_prod_worker_otid INT, evt_reqid INT, req_v2refid INT, req_codigo_cierre TEXT, req_id_bodega INT, req_embalaje_cajas_completas_amt INT, req_embalaje_bolsas_por_caja_amt INT, req_embalaje_caja_final INT, req_embalaje_bolsas_sobrantes_amt INT, req_embalaje_showroom INT);
CREATE TABLE embalaje_mixto (id INTEGER PRIMARY KEY, id_embalaje INT);
CREATE TABLE detalle_mixto (id INTEGER PRIMARY KEY, id_mixto INT, req_number TEXT, total_bolsas INT);
CREATE TABLE orders (id INTEGER PRIMARY KEY, req_number TEXT, req_company_id INT, req_shop_id INT);
CREATE TABLE orders_items (id INTEGER PRIMARY KEY, req_id INT, item_id INT);
CREATE TABLE item (id INTEGER PRIMARY KEY, item_title TEXT);
CREATE TABLE company_data (id INTEGER PRIMARY KEY, company_numformat_stockchange TEXT, company_numcounter_stockchange INT, company_numformat_storehouse TEXT, company_numcounter_storehouse INT);
CREATE TABLE stockchanges (id INTEGER PRIMARY KEY AUTOINCREMENT, stk_num TEXT, stk_annotation TEXT, stk_issueid INT, stk_companyid INT, stk_shopid INT, stk_bookdate INT, stk_negative INT, stk_status INT, stk_crtdat INT, stk_crtusr INT, stk_upddat INT, stk_updusr INT, stk_fixedsthid INT, sth_fromprodotid INT, sth_fromrebo_agid INT, sth_order_id INT, sth_order_num TEXT, stk_cinumber TEXT, stk_vintnumber TEXT, sth_fromrebo_agid_grphash TEXT);
CREATE TABLE stockchanges_items (stk_id INT, item_id INT, item_pos INT, item_amount REAL, item_type TEXT, item_st_id INT);
CREATE TABLE item_shops_storehouses (item_id INT, shop_id INT, st_id INT, iss_order_amount REAL, iss_inventory_min REAL, iss_inventory REAL, iss_inventory_reserved REAL, iss_order_id INT);
CREATE TABLE storehousechanges (id INTEGER PRIMARY KEY AUTOINCREMENT, strc_company_id INT, strc_shop_id INT, strc_company_dest_id INT, strc_shop_dest_id INT, strc_status INT, strc_date INT, strc_desc TEXT, strc_number TEXT, strc_crtusr INT, strc_crtdat INT, strc_order_id INT, strc_order_id_dest INT, strc_order_num TEXT, strc_order_num_dest TEXT, strc_shopsent INT, strc_shopreceived INT, strc_custid INT, strc_rel_reqnum TEXT);
CREATE TABLE storehousechanges_items (strc_id INT, item_id INT, item_pos INT, item_amount REAL, item_type TEXT, item_st_id INT, item_st_dest_id INT);
CREATE TABLE tran_data (tran_id INT, tran_pos INT, tran_type TEXT, tran_number TEXT, tran_currstock REAL, tran_amount REAL, tran_company_id INT, tran_shop_id INT, tran_st_id INT, tran_issueid INT, item_id INT, item_title TEXT, item_type TEXT, tran_day INT, tran_month INT, tran_year INT, tran_crtusr INT, tran_crtdat INT, tran_order_id INT);
INSERT INTO user VALUES (1,'sup','" . md5('bien') . "',1,1);
INSERT INTO company_shops_storehouses VALUES (8,'500 BODEGA',1),(14,'1000 RETAIL',1),(99,'9000 OTRA',1);
INSERT INTO orders VALUES (1,'A',20010,30010),(2,'B',20010,30010);
INSERT INTO orders_items VALUES (1,1,1),(2,2,2);
INSERT INTO item VALUES (1,'BOLSA A'),(2,'BOLSA B');
INSERT INTO company_data VALUES (20010,'IA',100,'IT',500);
INSERT INTO item_shops_storehouses VALUES (2,30010,8,0,0,5000,0,2);
INSERT INTO equipo VALUES (25,'EMBALAJE',15);
INSERT INTO prod_agenda (id,ag_reqid,ag_equipotype_id,ag_amount) VALUES (10,1,8,1000),(20,1,15,1000);
INSERT INTO prod_worker_init (id,win_wrkid) VALUES (1,77);
INSERT INTO prod_worker_ot (id,wok_ag_id,wok_init_id,wok_status) VALUES (100,10,1,1),(200,20,1,2);");

$service = new ProductionService($erp, null);
$finalize = fn(int $ev, bool $now) => $service->finalizeCysPackagingEvent($ev, 100, $now);
$stock = fn(int $order, int $st): float => (float)$erp->query("SELECT COALESCE(SUM(iss_inventory),0) x FROM item_shops_storehouses WHERE iss_order_id=$order AND st_id=$st")->fetch()['x'];
$count = fn(string $table): int => (int)$erp->query("SELECT COUNT(*) x FROM $table")->fetch()['x'];
$now = time();

// Validación previa
$rejected = static function (callable $call): string {
    try {
        $call();
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }
    return '';
};
$check('una clave errónea se rechaza', str_contains($rejected(fn() => $service->assertPackagingCloseAllowed(['req_id_bodega' => 14], 'sup', 'mal')), 'credenciales'));
$check('una bodega no permitida se rechaza', str_contains($rejected(fn() => $service->assertPackagingCloseAllowed(['req_id_bodega' => 99], 'sup', 'bien')), 'Bodega'));
$check('supervisor y bodega correctos pasan', $rejected(fn() => $service->assertPackagingCloseAllowed(['req_id_bodega' => 14], 'sup', 'bien')) === '');
$module = (string)file_get_contents($root . '/src/Http/Production/ProductionEventsModule.php');
$check('la validación ocurre antes de cerrar el evento', strpos($module, 'assertPackagingCloseAllowed') < strpos($module, '$productionResult = $prodService->recordEventProduction('));
$check('reintentar un cierre sin detalle y sin mover cuenta como cierre nuevo', str_contains($module, "overrideembalaje_selladora_evtrefid'] ?? 0) === 0 && \$prodService->getPackagingEventDetail(\$eventId, \$workerOtId) === []"));

// Reintento de un embalaje cerrado sin detalle: carga una sola vez
$erp->exec("INSERT INTO prod_worker_ot_events (id,evt_prod_worker_otid,evt_amount,evt_crtdat,evt_enddat,evt_status,evt_type,overrideembalaje_act) VALUES (1,100,1000,$now,$now,2,'prod',1)");
$erp->exec("INSERT INTO prod_worker_embalajes VALUES (51,100,1,1,'50',14,10,100,0,0,0)");
$finalize(1, true);
$check('el reintento carga el stock', $stock(1, 14) === 1000.0);
$finalize(1, true);
$check('repetir no vuelve a cargar', $stock(1, 14) === 1000.0 && $count('stockchanges') === 1);

// Traspaso del CC secundario
$transfer = new ReflectionMethod($service, 'transferMixedStock');
$transfer->setAccessible(true);
$transfer->invoke($service, 51, 2, 8, 14, 3000, 'B');
$erp->exec("INSERT INTO embalaje_mixto VALUES (1,51)");
$erp->exec("INSERT INTO detalle_mixto VALUES (1,1,'A',1000),(2,1,'B',3000)");

// Revisión: todo correcto
$since = $now - 60;
$audit = new CysPackagingAudit($erp);
$r = $audit->run($since, $now);
$check('la revisión no marca un embalaje correcto', $r['sin_mover'] === [] && $r['stock'] === [] && $r['traspasos'] === []);

// OT con solo embalaje
$check('una OT con solo un embalaje movido exige supervisor', $service->hasWorkOrderProductionStarted(100));

// Eliminar
$kardexBefore = $count('tran_data');
$reversed = $service->deleteEvent(1, 100, 'Prueba');
$check('eliminar el embalaje termina bien', ($reversed['ok'] ?? false) === true);
$check('eliminar devuelve el ingreso', $stock(1, 14) === 0.0);
$check('eliminar devuelve el traspaso', $stock(2, 8) === 5000.0 && $stock(2, 14) === 0.0);
$check('la reversa deja documento y kardex', $count('stockchanges') === 2 && $count('tran_data') === $kardexBefore + 3);
$service->reversePackagingEventStock(1);
$check('repetir la reversa no mueve nada', $stock(1, 14) === 0.0 && $stock(2, 8) === 5000.0 && $count('stockchanges') === 2);

// Lo cargado sin marca no se toca
$erp->exec("INSERT INTO prod_worker_ot_events (id,evt_prod_worker_otid,evt_amount,evt_crtdat,evt_enddat,evt_status,evt_type,overrideembalaje_act,overrideembalaje_selladora_evtrefid) VALUES (2,200,500,$now,$now,2,'prod',1,100)");
$erp->exec("INSERT INTO prod_worker_embalajes VALUES (52,200,1,2,'10',14,5,100,0,0,0)");
$erp->exec("INSERT INTO item_shops_storehouses VALUES (1,30010,14,0,0,500,0,99)");
$service->reversePackagingEventStock(2);
$check('un embalaje recuperado sin ingreso propio no cambia stock', $stock(99, 14) === 500.0 && $count('stockchanges') === 2);

// Si la reversa falla, no se elimina
$erp->exec('ALTER TABLE stockchanges RENAME TO stockchanges_oculta');
$failed = $service->deleteEvent(2, 200, 'Prueba');
$erp->exec('ALTER TABLE stockchanges_oculta RENAME TO stockchanges');
$check('si la reversa falla, el embalaje no se elimina', ($failed['ok'] ?? true) === false && (int)$erp->query('SELECT COUNT(*) x FROM prod_worker_ot_events WHERE id=2')->fetch()['x'] === 1);

// Revisión: detecta cada falla
$erp->exec("INSERT INTO prod_worker_ot_events (id,evt_prod_worker_otid,evt_amount,evt_crtdat,evt_enddat,evt_status,evt_type,overrideembalaje_act) VALUES (3,100,700,$now,$now,2,'prod',1)");
$erp->exec("INSERT INTO prod_worker_ot_events (id,evt_prod_worker_otid,evt_amount,evt_crtdat,evt_enddat,evt_status,evt_type,overrideembalaje_act) VALUES (4,100,0," . ($now - 90000) . ",0,1,'prod',1)");
$erp->exec("INSERT INTO prod_worker_ot_events (id,evt_prod_worker_otid,evt_amount,evt_crtdat,evt_enddat,evt_status,evt_type,overrideembalaje_act,overrideembalaje_selladora_evtrefid) VALUES (5,200,900,$now,$now,2,'prod',1,100)");
$erp->exec("INSERT INTO prod_worker_embalajes VALUES (53,200,1,5,'50',14,9,100,0,0,0)");
$erp->exec("INSERT INTO embalaje_mixto VALUES (2,53)");
$erp->exec("INSERT INTO detalle_mixto VALUES (3,2,'B',400)");
$before = [$count('stockchanges'), $count('tran_data'), $count('storehousechanges')];
$r = $audit->run($since, $now);
$check('detecta un embalaje sin mover', in_array(3, array_column($r['sin_mover'], 'evento')));
$check('detecta un embalaje sin ingreso', in_array(5, array_column($r['stock'], 'evento')));
$check('detecta una línea mixta sin traspaso', in_array(5, array_column($r['traspasos'], 'evento')));
$check('detecta un embalaje abierto hace más de un día', in_array(4, array_column($r['abiertos'], 'evento')));
$check('la revisión no escribe', $before === [$count('stockchanges'), $count('tran_data'), $count('storehousechanges')]);

echo $fail === 0 ? "SEGURIDAD EMBALAJE OK\n" : "SEGURIDAD EMBALAJE CON FALLOS: $fail\n";
exit($fail === 0 ? 0 : 1);
