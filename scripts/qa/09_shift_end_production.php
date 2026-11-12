<?php
declare(strict_types=1);
// Final del turno · Producción: lo producido en el día por máquina, con detalle (ERP sqlite en memoria, solo lectura).
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
$ts = static fn(string $s): int => (new DateTimeImmutable($s))->getTimestamp();

$erp = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$erp->exec("CREATE TABLE equipo (id INTEGER PRIMARY KEY, equipo_name TEXT, equipo_type_id INT);
CREATE TABLE workers (id INTEGER PRIMARY KEY, wrk_firstname TEXT, wrk_lastname TEXT);
CREATE TABLE prod_worker_init (id INTEGER PRIMARY KEY, win_equipoid INT, win_wrkid INT);
CREATE TABLE prod_header (id INTEGER PRIMARY KEY, prd_number TEXT);
CREATE TABLE orders (id INTEGER PRIMARY KEY, req_number TEXT);
CREATE TABLE item (id INTEGER PRIMARY KEY, item_number_prod TEXT, item_title TEXT);
CREATE TABLE orders_items (id INTEGER PRIMARY KEY, req_id INT, item_id INT, item_pos INT);
CREATE TABLE prod_agenda (id INTEGER PRIMARY KEY, ag_prdid INT, ag_reqid INT, ag_amount REAL, ag_equipo_id INT);
CREATE TABLE prod_worker_ot (id INTEGER PRIMARY KEY, wok_ag_id INT, wok_init_id INT);
CREATE TABLE prod_worker_ot_events (id INTEGER PRIMARY KEY, evt_prod_worker_otid INT, evt_type TEXT, evt_amount REAL, evt_crtdat INT, evt_enddat INT,
  evt_status INT, evt_comments TEXT, evt_amount_metros_lineales REAL, prod_bobina_kg REAL);
CREATE TABLE prod_mermatypes (id INTEGER PRIMARY KEY, merma_title TEXT);
CREATE TABLE prod_worker_ot_defectunits (id INTEGER PRIMARY KEY, evt_refid INT, evt_type TEXT, evt_amount REAL, evt_kgstounits REAL, evt_crtdat INT,
  evt_status INT, evt_comments TEXT, evt_merma_typeid INT);
INSERT INTO equipo VALUES (1,'SELLADORA 3',8),(2,'FLEXO 1',7),(3,'SELLADORA 9',8);
INSERT INTO workers VALUES (10,'Ana','Pérez'),(11,'Luis','Soto');
INSERT INTO prod_worker_init VALUES (100,1,10),(101,2,11),(102,1,11),(103,0,10);
INSERT INTO prod_header VALUES (1,'003837'),(2,'003900');
INSERT INTO orders VALUES (1,'26-00808-1'),(2,'26-00900-1');
INSERT INTO item VALUES (1,'BOL0352','BOLSA KRAFT 30X40'),(2,'BOL0999','BOLSA OTRA');
INSERT INTO orders_items VALUES (1,1,1,1),(2,1,2,2),(3,2,2,1);
INSERT INTO prod_agenda VALUES (50,1,1,20000,1),(51,2,2,8000,2),(52,1,1,5000,3);
INSERT INTO prod_worker_ot VALUES (500,50,100),(501,51,101),(502,50,102),(503,52,103);
INSERT INTO prod_mermatypes VALUES (1,'Mal sellado');");
$day = '2026-11-12';
$ins = $erp->prepare('INSERT INTO prod_worker_ot_events VALUES (?,?,?,?,?,?,?,?,?,?)');
foreach ([
    [1, 500, 'prod', 3000, $ts("$day 08:00"), $ts("$day 10:00"), 2, '', 0, 0],          // Ana, selladora 3
    [2, 502, 'prod', 1500, $ts("$day 15:00"), 0, 1, '', 0, 0],                           // Luis, selladora 3, abierto
    [3, 501, 'prod', 4000, $ts("$day 09:00"), $ts("$day 12:00"), 2, '', 2500, 0],        // flexo
    [4, 501, 'prod', 0, $ts("$day 12:00"), $ts("$day 12:05"), 2, 'Bobina Semielaborada ROLL-SE-00010', 2500, 120], // flexo, no suma
    [5, 500, 'prod', 999, $ts('2026-11-11 22:00'), $ts("$day 01:00"), 2, '', 0, 0],     // día anterior: fuera
    [6, 500, 'prod', 777, $ts("$day 11:00"), $ts("$day 11:30"), 0, '', 0, 0],          // anulado: fuera
    [7, 500, 'pause', 0, $ts("$day 10:00"), $ts("$day 10:30"), 2, '', 0, 0],           // pausa: fuera
    [8, 503, 'prod', 200, $ts("$day 13:00"), $ts("$day 14:00"), 2, '', 0, 0],          // turno sin máquina: usa la de la agenda (9)
] as $r) {
    $ins->execute($r);
}
$erp->exec("INSERT INTO prod_worker_ot_defectunits VALUES (1,1,'merma',50,2.5," . $ts("$day 10:00") . ",1,'arrugas',1),
  (2,3,'merma',0,4," . $ts("$day 12:00") . ",1,'',NULL),
  (3,1,'merma',99,9," . $ts('2026-11-11 10:00') . ",1,'',1);");

$data = (new ShiftEndProductionService($erp))->day($day);
$by = array_column($data['machines'], null, 'machine_name');
$check('máquinas que produjeron en el día, ordenadas por proceso', array_keys($by) === ['FLEXO 1', 'SELLADORA 3', 'SELLADORA 9'], implode(',', array_keys($by)));
$s3 = $by['SELLADORA 3'] ?? [];
$check('unidades del día sin anulados, pausas ni registros de ayer', ($s3['units'] ?? null) === 4500.0, (string)($s3['units'] ?? ''));
$check('operadores de la máquina', ($s3['operators'] ?? []) === ['Ana Pérez', 'Luis Soto']);
$check('registro abierto marcado', ($s3['open_records'] ?? 0) === 1 && ($s3['production'][1]['open'] ?? false) === true);
$check('merma del día por máquina (kg y und, con tipo)', ($s3['waste_kg'] ?? 0) === 2.5 && ($s3['waste_units'] ?? 0) === 50.0 && ($s3['waste'][0]['type'] ?? '') === 'Mal sellado');
$check('detalle por OT con programado, CC y producto', ($s3['ots'][0]['ot'] ?? '') === '003837' && ($s3['ots'][0]['cc'] ?? '') === '26-00808-1'
    && ($s3['ots'][0]['sku'] ?? '') === 'BOL0352' && ($s3['ots'][0]['planned'] ?? 0) === 20000.0);
$fx = $by['FLEXO 1'] ?? [];
$check('bobina semielaborada en el detalle pero sin sumar unidades', ($fx['units'] ?? 0) === 4000.0 && ($fx['semi_records'] ?? 0) === 1 && count($fx['production'] ?? []) === 2
    && ($fx['meters'] ?? 0) === 5000.0 && ($fx['waste'][0]['type'] ?? '') === 'Sin tipo');
$check('turno sin máquina usa la máquina de la agenda', ($by['SELLADORA 9']['units'] ?? 0) === 200.0);
$check('totales y procesos', $data['totals']['units'] === 8700.0 && $data['totals']['machines'] === 3
    && array_column($data['processes'], 'process') === ['Flexografía', 'Corte y Sellado'] && $data['processes'][1]['units'] === 4700.0);
$check('día sin producción: vacío', (new ShiftEndProductionService($erp))->day('2026-11-01')['machines'] === []);
$check('fecha inválida cae en hoy', ShiftEndProductionService::normalizeDate('2026-13-40') === date('Y-m-d') && ShiftEndProductionService::normalizeDate('2026-11-05') === '2026-11-05');

$html = shiftEndProductionPage($data);
$check('pantalla: fila por máquina con botón + y detalle desplegable oculto', substr_count($html, 'class="se-plus"') === 3 && substr_count($html, 'class="se-detail" id=') === 3
    && str_contains($html, ' hidden><td colspan') && str_contains($html, 'Registros de producción') && str_contains($html, 'Mermas registradas'));
$shell = (string)file_get_contents($root . '/src/Views/layouts/erp_shell.php');
$check('menú «Final del turno» con Producción y Bodega', str_contains($shell, "'Final del turno'") && str_contains($shell, 'href="/turno/produccion"') && str_contains($shell, 'href="/turno/bodega"'));
$check('ruta conectada', str_contains((string)file_get_contents($root . '/public/index.php'), 'handleShiftEndRoutes('));

echo $fail === 0 ? "\nFINAL DEL TURNO OK\n" : "\nFINAL DEL TURNO: $fail fallas\n";
exit($fail > 0 ? 1 : 0);
