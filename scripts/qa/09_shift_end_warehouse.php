<?php
declare(strict_types=1);
// Final del turno · Bodega: recepciones, despachos, producto terminado e ingresos del día (ERP y TRZ sqlite en memoria, solo lectura).
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "pdo_sqlite no disponible: prueba omitida\n";
    exit(0);
}
$root = dirname(__DIR__, 2);
require_once $root . '/src/bootstrap.php';

$fail = 0;
$check = function (string $name, bool $ok, string $extra = '') use (&$fail): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . ($extra !== '' && !$ok ? " [$extra]" : '') . "\n";
    if (!$ok) {
        $fail++;
    }
};
$day = '2026-11-12';
$ts = static fn(string $s): int => (new DateTimeImmutable($s))->getTimestamp();
$opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
$shop = WarehouseVisibility::shopId();

$erp = new PDO('sqlite::memory:', null, null, $opts);
$erp->exec("CREATE TABLE item (id INTEGER PRIMARY KEY, item_number_prod TEXT, item_title TEXT);
CREATE TABLE company_shops_storehouses (id INTEGER PRIMARY KEY, st_name TEXT, st_shop_id INT);
CREATE TABLE user (id INTEGER PRIMARY KEY, user_firstname TEXT, user_lastname TEXT);
CREATE TABLE customer (id INTEGER PRIMARY KEY, cust_name TEXT);
CREATE TABLE orders (id INTEGER PRIMARY KEY, req_number TEXT, req_cust_id INT);
CREATE TABLE orders_items (id INTEGER PRIMARY KEY, req_id INT, item_id INT, item_pos INT);
CREATE TABLE tran_data (id INTEGER PRIMARY KEY, tran_id INT, tran_pos INT, tran_type TEXT, tran_number TEXT, tran_amount REAL, tran_st_id INT,
  tran_crtdat INT, tran_order_id INT, item_id INT, item_title TEXT, tran_crtusr INT);
CREATE TABLE supplier (id INTEGER PRIMARY KEY, supp_company TEXT);
CREATE TABLE supplier_order (id INTEGER PRIMARY KEY, sord_number TEXT, sord_supplier_id INT);
CREATE TABLE supplier_contenedor (id INTEGER PRIMARY KEY, sord_contenedor TEXT);
CREATE TABLE equipo (id INTEGER PRIMARY KEY, equipo_name TEXT, equipo_type_id INT);
CREATE TABLE workers (id INTEGER PRIMARY KEY, wrk_firstname TEXT, wrk_lastname TEXT);
CREATE TABLE prod_worker_init (id INTEGER PRIMARY KEY, win_equipoid INT, win_wrkid INT);
CREATE TABLE prod_agenda (id INTEGER PRIMARY KEY, ag_prdid INT, ag_equipo_id INT);
CREATE TABLE prod_header (id INTEGER PRIMARY KEY, prd_number TEXT);
CREATE TABLE prod_worker_ot (id INTEGER PRIMARY KEY, wok_init_id INT, wok_ag_id INT);
CREATE TABLE prod_worker_embalajes (id INTEGER PRIMARY KEY, evt_prod_worker_otid INT, evt_reqid INT, evt_crtdat INT, req_id_bodega INT, req_id_supervisor INT,
  req_codigo_cierre TEXT, req_embalaje_cajas_completas_amt INT, req_embalaje_bolsas_por_caja_amt INT, req_embalaje_caja_final INT,
  req_embalaje_bolsas_sobrantes_amt INT, req_embalaje_showroom INT);
INSERT INTO item VALUES (1,'BOL0352','BOLSA KRAFT 30X40'),(2,'INS0073','CINTA EMBALAJE'),(3,'BOL0999','BOLSA OTRA');
INSERT INTO company_shops_storehouses VALUES (1,'400 (PROD TERMINADOS REVENTA)',$shop),(14,'1000 (BODEGA RETAIL A y B)',$shop),(16,'3000 (MP PRODUCCIÓN)',$shop),(40,'1000 VENTAS',30019);
INSERT INTO user VALUES (4077,'Héctor','Ramos'),(4129,'Javier','Perozo');
INSERT INTO customer VALUES (1,'Tottus'),(2,'Falabella');
INSERT INTO orders VALUES (1,'26-00808-1',1),(2,'26-00900-1',2);
INSERT INTO orders_items VALUES (1,1,1,1),(2,2,3,1);
INSERT INTO supplier VALUES (7,'Papelera Sur'),(8,'Import Co');
INSERT INTO supplier_order VALUES (300,'OC-0451',7);
INSERT INTO supplier_contenedor VALUES (90,'MSCU1234567');
INSERT INTO equipo VALUES (3,'SELLADORA 3',8),(5,'EMBALAJE 1',15);
INSERT INTO workers VALUES (10,'Ana','Pérez'),(11,'Luis','Soto');
INSERT INTO prod_worker_init VALUES (100,3,10),(101,5,11);
INSERT INTO prod_header VALUES (1,'003837');
INSERT INTO prod_agenda VALUES (50,1,3),(51,1,5);
INSERT INTO prod_worker_ot VALUES (500,100,50),(501,101,51);");
$t = $erp->prepare('INSERT INTO tran_data (tran_id, tran_pos, tran_type, tran_number, tran_amount, tran_st_id, tran_crtdat, tran_order_id, item_id, item_title, tran_crtusr) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
foreach ([
    [71, 0, 'ordersdelivery', 'GV0001234', 5000, 1, $ts("$day 10:15"), 1, 1, '', 4077],
    [71, 1, 'ordersdelivery', 'GV0001234', 2000, 14, $ts("$day 10:15"), 1, 1, '', 4077],
    [72, 0, 'ordersdelivery', 'GV0001240', 800, 14, $ts("$day 16:00"), 2, 3, '', 4129],
    [73, 0, 'ordersdelivery', 'GV0001199', 999, 1, $ts('2026-11-11 16:00'), 2, 3, '', 4129], // ayer
    [74, 0, 'ordersdelivery', 'GV0001241', 50, 40, $ts("$day 12:00"), 2, 3, '', 4129],     // otra sucursal
    [80, 0, 'invoicesbuy', 'FC0000555', 120, 16, $ts("$day 09:00"), 0, 2, '', 4129],
    [81, 0, 'stockchangeup', 'IA0033800', 9999, 1, $ts("$day 09:30"), 1, 1, '', 4077],    // ajuste: no es compra ni despacho
] as $r) {
    $t->execute($r);
}
$erp->exec('INSERT INTO prod_worker_embalajes VALUES
 (1,500,1,' . $ts("$day 11:00") . ",1,4129,'',10,500,300,20,0),
 (2,501,2," . $ts("$day 17:30") . ",14,4077,'50',4,250,0,0,10),
 (3,500,1," . $ts('2026-11-11 23:00') . ",1,4129,'',99,1,0,0,0),
 (4,500,1," . $ts("$day 18:00") . ",0,0,'',1,100,0,0,0);");

$trz = new PDO('sqlite::memory:', null, null, $opts);
$trz->exec("CREATE TABLE warehouses (id INTEGER PRIMARY KEY, code INT, name TEXT);
CREATE TABLE skus (id INTEGER PRIMARY KEY, code TEXT, description TEXT);
CREATE TABLE rolls (id INTEGER PRIMARY KEY, roll_code TEXT, sku_id INT, weight_kg REAL, received_qty REAL, purchase_order_id INT, import_container_id INT, supplier_id INT);
CREATE TABLE movements (id INTEGER PRIMARY KEY, entity_type TEXT, entity_id INT, movement_type TEXT, from_warehouse_id INT, to_warehouse_id INT, payload TEXT, created_at TEXT);
CREATE TABLE dispatch_pickings (id INTEGER PRIMARY KEY, code TEXT, dispatch_type TEXT, document_type TEXT, guide_number TEXT, guide_snapshot TEXT, created_by TEXT,
  confirmed_by TEXT, confirmed_at TEXT, status TEXT);
CREATE TABLE dispatch_picking_items (id INTEGER PRIMARY KEY, picking_id INT, status TEXT);
CREATE TABLE traceability_warehouse_entries (id INTEGER PRIMARY KEY, batch_id TEXT, entity_type TEXT, warehouse_name TEXT, item_code TEXT, item_description TEXT,
  quantity REAL, unit_name TEXT, order_ref TEXT, operator_name TEXT, created_at TEXT);
INSERT INTO warehouses VALUES (1,100,'100 (MP PLA)'),(2,200,'200 (MP PP)');
INSERT INTO skus VALUES (1,'TEL0088','TELA PLA 80G'),(2,'TEL0158','TELA PP 70G');
INSERT INTO rolls VALUES (1,'TEL0088-A1',1,250,1,300,NULL,7),(2,'TEL0088-A2',1,240,1,300,NULL,7),(3,'TEL0158-C1',2,300,1,NULL,90,8),
  (4,'ROLL-SE-00009',1,100,1,NULL,NULL,NULL),(5,'TEL0088-OLD',1,200,1,300,NULL,7);
INSERT INTO movements VALUES
 (1,'ROLL',1,'RECEIPT',NULL,1,'{\"operator_name\":\"Javier Perozo\"}','$day 08:10:00'),
 (2,'ROLL',2,'RECEIPT',NULL,1,'{\"operator_name\":\"Javier Perozo\"}','$day 08:12:00'),
 (3,'ROLL',3,'RECEIPT',NULL,2,'{\"operator_name\":\"Gabriel Muñoz\"}','$day 13:00:00'),
 (4,'ROLL',4,'RECEIPT',NULL,1,'{\"operator_name\":\"Prod\"}','$day 13:30:00'),
 (5,'ROLL',5,'RECEIPT',NULL,1,'{\"operator_name\":\"Javier Perozo\"}','2026-11-11 08:00:00'),
 (6,'ROLL',1,'TRANSFER',1,2,'{}','$day 15:00:00');
INSERT INTO dispatch_pickings VALUES
 (1,'DSP-0001','SALE','GUIDE','1234','{\"number\":\"1234\",\"docnum\":\"55\",\"customer\":\"Tottus\",\"items\":[{\"qty\":7000}]}','Gabriel Muñoz','Héctor Ramos','$day 10:20:00','DISPATCHED'),
 (2,'DSP-0002','MAQUILA','INVOICE','FV0011701','{\"customer\":\"Falabella\",\"items\":[{\"qty\":300}]}','Gabriel Muñoz','Gabriel Muñoz','$day 17:00:00','DISPATCHED'),
 (3,'DSP-0003','SALE','GUIDE','1300','{}','x','x',NULL,'PICKING');
INSERT INTO dispatch_picking_items VALUES (1,1,'DISPATCHED'),(2,1,'DISPATCHED'),(3,2,'DISPATCHED');
INSERT INTO traceability_warehouse_entries VALUES
 (1,'b1','PALLET','400 (PROD TERMINADOS REVENTA)','BOL0352','BOLSA KRAFT',1000,'Unid.','CC 26-00808-1','Gabriel Muñoz','$day 12:00:00'),
 (2,'b1','PALLET','400 (PROD TERMINADOS REVENTA)','BOL0352','BOLSA KRAFT',1000,'Unid.','CC 26-00808-1','Gabriel Muñoz','$day 12:00:01'),
 (3,NULL,'BOX','1000 (BODEGA RETAIL A y B)','BOL0999','AUTO',500,'Unid.',NULL,'Sistema','$day 12:30:00'),
 (4,'b2','PRODUCT','1000 (BODEGA RETAIL A y B)','BOL0999','BOLSA OTRA',200,'Unid.',NULL,'Javier Perozo','2026-11-11 12:00:00');");

$d = (new ShiftEndWarehouseService($erp, $trz))->day($day);

// 1. Recepciones
$rec = $d['receptions'];
$check('bobinas recibidas del día agrupadas por documento, artículo, bodega y quién recibió', count($rec) === 2
    && $rec[0]['document'] === 'OC OC-0451' && $rec[0]['supplier'] === 'Papelera Sur' && $rec[0]['rolls'] === 2 && $rec[0]['kg'] === 490.0
    && $rec[0]['received_by'] === 'Javier Perozo' && $rec[0]['warehouse'] === '100 (MP PLA)', json_encode($rec[0] ?? null));
$check('contenedor con proveedor de la bobina', ($rec[1]['document'] ?? '') === 'Contenedor MSCU1234567' && ($rec[1]['supplier'] ?? '') === 'Import Co'
    && ($rec[1]['received_by'] ?? '') === 'Gabriel Muñoz');
$check('sin bobinas de producción, de ayer ni traspasos', $d['totals']['rolls'] === 2 + 1 && !in_array('ROLL-SE-00009', array_merge(...array_column($rec, 'codes')), true));
$check('otras compras del ERP (factura de compra), sin ajustes', count($d['purchases']) === 1 && $d['purchases'][0]['document'] === 'FC0000555'
    && $d['purchases'][0]['user'] === 'Javier Perozo' && $d['purchases'][0]['warehouse'] === '3000 (MP PRODUCCIÓN)');

// 2. Despachos
$g = array_column($d['dispatches'], null, 'guide');
$check('guías del día de la sucursal (sin ayer ni otra sucursal)', array_keys($g) === ['GV0001234', 'GV0001240'], implode(',', array_keys($g)));
$check('guía con cliente, CC, bodegas, unidades y líneas', ($g['GV0001234']['units'] ?? 0) === 7000.0 && $g['GV0001234']['customer'] === 'Tottus'
    && $g['GV0001234']['cc'] === ['26-00808-1'] && count($g['GV0001234']['items']) === 2
    && $g['GV0001234']['warehouses'] === ['400 (PROD TERMINADOS REVENTA)', '1000 (BODEGA RETAIL A y B)']);
$check('quién emitió, preparó y confirmó el despacho', $g['GV0001234']['issued_by'] === 'Héctor Ramos' && $g['GV0001234']['picked_by'] === 'Gabriel Muñoz'
    && $g['GV0001234']['confirmed_by'] === 'Héctor Ramos' && $g['GV0001240']['confirmed_by'] === '');
$check('despacho confirmado con factura aparte', count($d['unmatched_dispatches']) === 1 && $d['unmatched_dispatches'][0]['picking'] === 'DSP-0002'
    && $d['unmatched_dispatches'][0]['units'] === 300.0 && $d['unmatched_dispatches'][0]['type'] === 'Maquila');

// 3. Producto terminado
$f = $d['finished'];
$check('cierres de embalaje del día con unidades', count($f) === 3 && $f[0]['units'] === 10 * 500 + 300 + 20 && $f[0]['boxes'] === 11
    && $f[1]['units'] === 4 * 250 + 10, json_encode(array_column($f, 'units')));
$check('proceso, máquina, OT, CC, cliente y producto', $f[0]['process'] === 'Corte y Sellado' && $f[0]['machine'] === 'SELLADORA 3' && $f[0]['ot'] === '003837'
    && $f[0]['cc'] === '26-00808-1' && $f[0]['customer'] === 'Tottus' && $f[0]['sku'] === 'BOL0352' && $f[1]['process'] === 'Embalaje' && $f[1]['mixed'] === true);
$check('bodega de destino, quién embaló y quién validó', $f[0]['warehouse'] === '400 (PROD TERMINADOS REVENTA)' && $f[0]['operator'] === 'Ana Pérez'
    && $f[0]['supervisor'] === 'Javier Perozo' && $f[2]['warehouse'] === 'Sin bodega');

// 4. Ingresos de bodega
$check('ingresos registrados por bodega agrupados por lote (sin cajas automáticas ni ayer)', count($d['entries']) === 1 && $d['entries'][0]['qty'] === 2000.0
    && $d['entries'][0]['labels'] === 2 && $d['entries'][0]['received_by'] === 'Gabriel Muñoz' && $d['entries'][0]['type'] === 'Pallet');

// Resumen por bodega
$w = array_column($d['by_warehouse'], null, 'warehouse');
$check('resumen por bodega', ($w['400 (PROD TERMINADOS REVENTA)']['dispatched'] ?? 0) === 5000.0 && ($w['400 (PROD TERMINADOS REVENTA)']['production_in'] ?? 0) === 5320.0
    && ($w['400 (PROD TERMINADOS REVENTA)']['entries_in'] ?? 0) === 2000.0 && ($w['100 (MP PLA)']['rolls_in'] ?? 0) == 2);
$check('sin trazabilidad: avisa y sigue con el ERP', ($x = (new ShiftEndWarehouseService($erp, null))->day($day))['warnings'] !== [] && count($x['dispatches']) === 2 && count($x['finished']) === 3);

$html = shiftEndWarehousePage($d, 'UNIBAG CHILE');
$check('informe formal con las 4 secciones, firmas e imprimir', str_contains($html, 'Informe de cierre de turno · Bodega') && str_contains($html, '1. Recepciones de proveedores')
    && str_contains($html, '2. Despachos') && str_contains($html, '3. Producto terminado desde Corte y Sellado y Embalaje')
    && str_contains($html, '4. Mercadería recibida y guardada por bodega') && str_contains($html, 'Revisado por') && str_contains($html, 'window.print()'));
$empty = shiftEndWarehousePage((new ShiftEndWarehouseService($erp, $trz))->day('2026-11-01'), 'UNIBAG CHILE');
$check('día sin movimientos: textos de vacío', str_contains($empty, 'No se recibieron bobinas en el día.') && str_contains($empty, 'No hubo despachos por guía en el día.'));

echo $fail === 0 ? "\nCIERRE DE BODEGA OK\n" : "\nCIERRE DE BODEGA: $fail fallas\n";
exit($fail > 0 ? 1 : 0);
