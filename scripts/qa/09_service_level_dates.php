<?php

declare(strict_types=1);

// Nivel de servicio: días de atraso por día calendario (sin importar la hora). No usa ninguna base de datos.
require_once __DIR__ . '/../../src/bootstrap.php';

$failures = 0;
function checkSl(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures++;
    }
}

date_default_timezone_set('America/Santiago');
$d = static fn(string $a, string $b): int => ReceptionReportingService::calendarDaysBetween((int)strtotime($a), (int)strtotime($b));

checkSl('NV0002524: pactada 03-11 12:00, registro 10-11 00:00 → 7 días', $d('2026-11-03 12:00:00', '2026-11-10 00:00:00') === 7);
checkSl('mismo día, más tarde → 0 días (a tiempo)', $d('2026-11-03 12:00:00', '2026-11-03 18:30:00') === 0);
checkSl('día siguiente antes de la hora pactada → 1 día (antes quedaba a tiempo)', $d('2026-11-03 12:00:00', '2026-11-04 09:00:00') === 1);
checkSl('despacho antes de lo pactado → negativo', $d('2026-11-10 00:00:00', '2026-11-08 15:00:00') === -2);
checkSl('cruce de cambio de hora (sep 2026) → días exactos', $d('2026-09-05 00:00:00', '2026-09-08 00:00:00') === 3);

// Fecha pactada desde el texto del pedido (orders.req_despacho_desc).
$order = (int)strtotime('2026-10-29 10:03:09'); // jueves, como NV0002524
$p = static fn(string $t, int $o = 0): string => ($ts = ReceptionReportingService::parseAgreedDispatchDate($t, $o ?: $order)) === null ? 'null' : date('Y-m-d', $ts);
checkSl('«03-11» (NV0002524) → 2026-11-03', $p('03-11') === '2026-11-03');
checkSl('«19-11-2026» y «02/09/2026»', $p('19-11-2026') === '2026-11-19' && $p('02/09/2026') === '2026-09-02');
checkSl('«DESPACHO 02-12-2026» y «19-10-2026 (ó antes si se peude)»', $p('DESPACHO 02-12-2026') === '2026-12-02' && $p('19-10-2026 (ó antes si se peude)') === '2026-10-19');
checkSl('«01 octubre», «1 de Octubre», «11-Agosto», «04 noviembre»', $p('01 octubre', (int)strtotime('2026-09-20')) === '2026-10-01'
    && $p('1 de Octubre', (int)strtotime('2026-09-20')) === '2026-10-01' && $p('11-Agosto', (int)strtotime('2026-07-20')) === '2026-08-11' && $p('04 noviembre') === '2026-11-04');
checkSl('«10 días hábiles» desde jueves 29-10 → jueves 12-11 (lunes a viernes)', $p('10 dias hábiles') === '2026-11-12' && $p('10 DIAS HÁBILES') === '2026-11-12');
checkSl('«10 ds, habiles», «3 ds. habiles», «10/12 ds, habiles»', $p('10 ds, habiles') === '2026-11-12' && $p('3 ds. habiles') === '2026-11-03' && $p('10/12 ds, habiles') === '2026-11-12');
checkSl('sin año en diciembre → enero del año siguiente', $p('05-01', (int)strtotime('2026-12-20')) === '2027-01-05');
checkSl('«2.500 Unid. 04-Set / 2.500 unid. 11-Set» → primera fecha 04-09', $p('2.500 Unid. 04-Set / 2.500 unid. 11-Set', (int)strtotime('2026-08-20')) === '2026-09-04');
checkSl('no se entiende → null: vacío, «NOVIEMBRE», «AÑO 2026», «90 dias una vez VB°», fecha imposible', $p('') === 'null' && $p('NOVIEMBRE') === 'null'
    && $p('AÑO 2026') === 'null' && $p('90 dias una vez VB°') === 'null' && $p('31-02-2026') === 'null');

// Informe completo con un ERP de prueba en memoria (sin bases reales).
$erp = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
foreach ([
    'CREATE TABLE despacho (id INTEGER PRIMARY KEY, mes INT, "año" INT, fecha_ingreso INT, hora_ingreso TEXT, hora_salida TEXT, numero_documento TEXT,
        tipo_documento TEXT, estado INT, observacion TEXT, sello TEXT, cantidad_pallet INT, cantidad_cajas INT, id_cliente INT, id_chofer INT, id_patente INT, id_transporte INT)',
    'CREATE TABLE detalle_despacho (id INTEGER PRIMARY KEY, id_despacho INT, id_item INT, salida REAL)',
    'CREATE TABLE customer (id INTEGER PRIMARY KEY, cust_name TEXT, cust_company TEXT, cust_canal TEXT)',
    'CREATE TABLE transports_chofer (id INTEGER PRIMARY KEY, transports_chofer_nombre TEXT, transports_chofer_paterno TEXT, transports_chofer_rut TEXT)',
    'CREATE TABLE transports_vehiculo (id INTEGER PRIMARY KEY, transports_vh_patente TEXT)',
    'CREATE TABLE transports (id INTEGER PRIMARY KEY, trans_name TEXT)',
    'CREATE TABLE parametros (tabla TEXT, codigo TEXT, descripcion TEXT)',
    'CREATE TABLE item (id INTEGER PRIMARY KEY, item_number_prod TEXT)',
    'CREATE TABLE orders_delivery (id INTEGER PRIMARY KEY, dlv_order_id INT, dlv_delivery_date INT, dlv_docnum TEXT)',
    'CREATE TABLE orders (id INTEGER PRIMARY KEY, req_number TEXT, req_crtdat INT, req_despacho_desc TEXT, req_fecha_aprobacion INT)',
    'CREATE TABLE plantas (id INTEGER PRIMARY KEY, nombre TEXT)',
] as $ddl) {
    $erp->exec($ddl);
}
// El SQLite de PHP no trae CONCAT (la consulta del informe es para MySQL): se agrega igual que en MySQL.
$erp->sqliteCreateFunction('CONCAT', static fn(...$parts): string => implode('', array_map('strval', $parts)));
$ts = static fn(string $s): int => (int)strtotime($s);
$erp->exec("INSERT INTO customer VALUES (1, 'Cliente', 'Cliente SA', '')");
$erp->exec("INSERT INTO item VALUES (1, 'BOL0001')");
// NV0002524: pactada «03-11», factura 03-11, camión registrado el 10-11 (y un borrador duplicado, estado 1).
$erp->exec("INSERT INTO orders VALUES (1, 'NV0002524', {$ts('2026-10-29 10:03:09')}, '03-11', {$ts('2026-10-29 10:03:09')})");
$erp->exec("INSERT INTO orders_delivery VALUES (101, 1, {$ts('2026-11-03 12:00:00')}, '')");
$erp->exec("INSERT INTO despacho VALUES (1, 11, 2026, {$ts('2026-11-10 00:00:00')}, '', '', '101', 'FA', 2, '', '', 22, 0, 1, 0, 0, 0)");
$erp->exec("INSERT INTO despacho VALUES (2, 10, 2026, {$ts('2026-11-10 11:01:37')}, '', '', '101', 'FA', 1, '', '', 0, 0, 1, 0, 0, 0)");
$erp->exec("INSERT INTO detalle_despacho VALUES (1, 1, 1, 26400), (2, 1, 1, 5280), (3, 2, 1, 26400)");
// 26-00948-1: creada 20-10, aprobada 29-10, «10 DIAS HÁBILES», factura 09-11 → a tiempo desde la aprobación (vence 12-11).
$erp->exec("INSERT INTO orders VALUES (2, '26-00948-1', {$ts('2026-10-20 15:42:57')}, '10 DIAS HÁBILES', {$ts('2026-10-29 15:42:57')})");
$erp->exec("INSERT INTO orders_delivery VALUES (102, 2, {$ts('2026-11-09 10:00:00')}, '')");
$erp->exec("INSERT INTO despacho VALUES (3, 11, 2026, {$ts('2026-11-09 00:00:00')}, '', '', '102', 'FA', 2, '', '', 5, 0, 1, 0, 0, 0)");
$erp->exec("INSERT INTO detalle_despacho VALUES (4, 3, 1, 1000)");
// 26-00999-1: sin aprobación, «3 ds. habiles» desde creación 02-11 (lunes) → vence 05-11; factura 09-11 → 4 días de atraso.
$erp->exec("INSERT INTO orders VALUES (3, '26-00999-1', {$ts('2026-11-02 09:00:00')}, '3 ds. habiles', 0)");
$erp->exec("INSERT INTO orders_delivery VALUES (103, 3, {$ts('2026-11-09 10:00:00')}, '')");
$erp->exec("INSERT INTO despacho VALUES (4, 11, 2026, {$ts('2026-11-09 00:00:00')}, '', '', '103', 'FA', 2, '', '', 1, 0, 1, 0, 0, 0)");
$erp->exec("INSERT INTO detalle_despacho VALUES (5, 4, 1, 500)");
// NV0009999: sin fecha pactada → no cuenta como atraso, queda marcada.
$erp->exec("INSERT INTO orders VALUES (4, 'NV0009999', {$ts('2026-11-02 09:00:00')}, 'NOVIEMBRE', {$ts('2026-11-02 09:00:00')})");
$erp->exec("INSERT INTO orders_delivery VALUES (104, 4, {$ts('2026-11-20 10:00:00')}, '')");
$erp->exec("INSERT INTO despacho VALUES (5, 11, 2026, {$ts('2026-11-20 00:00:00')}, '', '', '104', 'FA', 2, '', '', 1, 0, 1, 0, 0, 0)");
$erp->exec("INSERT INTO detalle_despacho VALUES (6, 5, 1, 700)");

$trz = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$svc = new ReceptionReportingService($trz, $erp);
// SQLite no tiene information_schema: se indica a mano que las tablas del ERP de prueba existen.
Closure::bind(function (): void {
    $this->erpTableExistsCache = ['despacho' => true, 'detalle_despacho' => true];
}, $svc, ReceptionReportingService::class)();
$report = $svc->getServiceLevelReport('2026-11-01 00:00:00', '2026-11-30 23:59:59', null, null, 'all', '');
$byCc = [];
foreach ($report['rows'] as $r) {
    $byCc[$r['cc_number']][] = $r;
}
$nv = $byCc['NV0002524'] ?? [];
checkSl('informe: NV0002524 a tiempo, despacho = factura 03-11, sin el borrador duplicado (2 filas, 31.680 u.)', count($nv) === 2
    && $nv[0]['is_on_time'] && $nv[0]['date'] === '03/11/2026' && $nv[0]['truck_date'] === '10/11/2026'
    && array_sum(array_column($nv, 'dispatched_units')) === 31680.0);
$cc = ($byCc['26-00948-1'] ?? [[]])[0];
checkSl('informe: 26-00948-1 a tiempo, 10 días hábiles desde la aprobación 29-10 (vence 12-11)', ($cc['is_on_time'] ?? false)
    && ($cc['committed_date'] ?? '') === '12/11/2026' && ($cc['term_start_source'] ?? '') === 'aprobación' && ($cc['term_start'] ?? '') === '29/10/2026');
$cc = ($byCc['26-00999-1'] ?? [[]])[0];
checkSl('informe: sin aprobación cuenta desde la creación (02-11 + 3 hábiles = 05-11) → 4 días de atraso', !($cc['is_on_time'] ?? true)
    && ($cc['committed_date'] ?? '') === '05/11/2026' && ($cc['delay_days'] ?? 0) === 4 && ($cc['term_start_source'] ?? '') === 'creación');
$cc = ($byCc['NV0009999'] ?? [[]])[0];
checkSl('informe: «NOVIEMBRE» → sin fecha pactada, no es atraso', ($cc['is_on_time'] ?? false) && !empty($cc['without_agreed_date']) && ($cc['status_label'] ?? '') === 'Sin fecha pactada');
$s = $report['summary'];
checkSl('informe: resumen por CC distintas (4 CC, 1 atrasada, 1 sin fecha pactada)', $s['total_cc'] === 4 && $s['delayed_cc'] === 1 && $s['without_agreed_cc'] === 1, json_encode($s));

exit($failures > 0 ? 1 : 0);
