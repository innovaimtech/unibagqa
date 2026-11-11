<?php
declare(strict_types=1);
// Informe de producción por máquina · «Máquinas en producción»: estado de cada máquina con turno abierto (ERP sqlite).
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "pdo_sqlite no disponible: prueba omitida\n";
    exit(0);
}
require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

$fail = 0;
$check = function (string $name, bool $ok, string $extra = '') use (&$fail): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . ($extra !== '' && !$ok ? " [$extra]" : '') . "\n";
    if (!$ok) {
        $fail++;
    }
};

$now = 1_800_000_000;
$h = 3600;
$erp = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$erp->exec("CREATE TABLE equipo (id INTEGER PRIMARY KEY, equipo_name TEXT, equipo_type_id INT);
CREATE TABLE workers (id INTEGER PRIMARY KEY, wrk_firstname TEXT, wrk_lastname TEXT);
CREATE TABLE prod_worker_init (id INTEGER PRIMARY KEY, win_equipoid INT, win_wrkid INT, win_status INT, win_crtdat INT);
CREATE TABLE prod_worker_ot (id INTEGER PRIMARY KEY, wok_init_id INT, wok_ag_id INT, wok_status INT, wok_crtdat INT);
CREATE TABLE prod_worker_ot_events (id INTEGER PRIMARY KEY, evt_prod_worker_otid INT, evt_type TEXT, evt_status INT, evt_crtdat INT, evt_enddat INT, evt_pause_id INT, evt_amount REAL, evt_comments TEXT);
CREATE TABLE prod_pause_types (id INTEGER PRIMARY KEY, pause_name TEXT);
CREATE TABLE prod_agenda (id INTEGER PRIMARY KEY, ag_reqid INT, ag_amount REAL, ag_equipotype_id INT);
CREATE TABLE orders (id INTEGER PRIMARY KEY, req_number TEXT);
CREATE TABLE orders_items (id INTEGER PRIMARY KEY, req_id INT, item_id INT, item_pos INT);
CREATE TABLE item (id INTEGER PRIMARY KEY, item_number_prod TEXT, item_title TEXT);
INSERT INTO equipo VALUES (1,'FLEXO III',7),(2,'SELLADORA 2',8),(3,'SERI II',11),(4,'SELLADORA 5',8),(5,'REBOBINADORA',12),(6,'SUPERVISOR TURNO',99),(7,'SELLADORA 6',8),(36,'PULPO SERIGRAFICO',11),(8,'FLEXO I',7);
INSERT INTO workers VALUES (1,'Juan','Pérez'),(2,'Ana','Soto'),(3,'Luis','Rojas'),(4,'Eva','Díaz'),(5,'Raúl','Mora'),(6,'Sup','Er'),(7,'Olga','Vera'),(8,'Pía','Luna');
INSERT INTO prod_pause_types VALUES (1,'Colación'),(5,'Cambio de bobina');
INSERT INTO orders VALUES (100,'26-00100-1'),(200,'26-00200-1');
INSERT INTO item VALUES (10,'BOL0100','BOLSA TNT 30X40'),(11,'BOL0101','BOLSA OTRA');
INSERT INTO orders_items VALUES (1,100,10,0),(2,100,11,1),(3,200,11,0);
INSERT INTO prod_agenda VALUES (50,100,5000,7),(51,200,1000,8),(52,100,800,11),(53,200,300,8),(54,100,700,11);
-- 1 FLEXO III: produciendo (evento prod abierto); ya hay 1.200 producidas + 50 de bobina semielaborada que no cuentan
INSERT INTO prod_worker_init VALUES (1,1,1,1,$now-2*$h);
INSERT INTO prod_worker_ot VALUES (11,1,50,1,$now-2*$h);
INSERT INTO prod_worker_ot_events VALUES (101,11,'prod',2,$now-2*$h,$now-$h,0,1200,''),(102,11,'prod',2,$now-90*60,$now-80*60,0,50,'Bobina Semielaborada X'),(103,11,'prod',1,$now-30*60,0,0,0,'');
-- 2 SELLADORA 2: producción abierta pero con pausa abierta encima -> en pausa (Cambio de bobina)
INSERT INTO prod_worker_init VALUES (2,2,2,1,$now-3*$h);
INSERT INTO prod_worker_ot VALUES (12,2,51,1,$now-3*$h);
INSERT INTO prod_worker_ot_events VALUES (104,12,'prod',1,$now-2*$h,NULL,0,0,''),(105,12,'pause',1,$now-10*60,0,5,0,'');
-- 3 SERI II: apertura abierta -> preparando
INSERT INTO prod_worker_init VALUES (3,3,3,1,$now-$h);
INSERT INTO prod_worker_ot VALUES (13,3,52,1,$now-$h);
INSERT INTO prod_worker_ot_events VALUES (106,13,'apertura',1,$now-40*60,0,0,0,'');
-- 4 SELLADORA 5: OT abierta sin evento abierto -> OT abierta, sin producir
INSERT INTO prod_worker_init VALUES (4,4,4,1,$now-$h);
INSERT INTO prod_worker_ot VALUES (14,4,53,1,$now-50*60);
-- 5 REBOBINADORA: turno sin OT
INSERT INTO prod_worker_init VALUES (5,5,5,1,$now-$h);
-- 6 SUPERVISOR: no es máquina
INSERT INTO prod_worker_init VALUES (6,6,6,1,$now-$h);
-- 7 SELLADORA 6: turno abierto hace 3 días (olvidado) -> no se muestra
INSERT INTO prod_worker_init VALUES (7,7,7,1,$now-72*$h);
-- 8 PULPO: turno en pausa (3) porque la operadora se fue a FLEXO I, donde está produciendo
INSERT INTO prod_worker_init VALUES (8,36,8,3,$now-4*$h),(9,8,8,1,$now-$h);
INSERT INTO prod_worker_ot VALUES (18,8,54,1,$now-4*$h),(19,9,50,1,$now-$h);
INSERT INTO prod_worker_ot_events VALUES (107,18,'prod',1,$now-4*$h,0,0,0,''),(108,19,'prod',1,$now-20*60,0,0,0,'');
-- turno cerrado (2): no cuenta
INSERT INTO prod_worker_init VALUES (10,2,2,2,$now-5*$h);");

$snap = (new MachineLiveStatusService($erp))->snapshot($now);
$by = array_column($snap['machines'], null, 'machine_name');

$check('lista solo máquinas con turno abierto reciente (sin supervisor ni turno olvidado)', count($snap['machines']) === 7 && !isset($by['SUPERVISOR TURNO']) && !isset($by['SELLADORA 6']), implode(',', array_keys($by)));
$f = $by['FLEXO III'] ?? [];
$check('produciendo: CC, producto de la primera línea y operador', ($f['state_key'] ?? '') === 'producing' && ($f['cc'] ?? '') === '26-00100-1' && ($f['sku'] ?? '') === 'BOL0100'
    && ($f['operator'] ?? '') === 'Juan Pérez' && ($f['process'] ?? '') === 'Flexografía' && ($f['minutes'] ?? 0) === 30);
$check('producido de la agenda sin la bobina semielaborada / programado', ($f['produced'] ?? 0) === 1200 && ($f['planned'] ?? 0) === 5000, json_encode($f));
$s = $by['SELLADORA 2'] ?? [];
$check('una pausa abierta manda sobre la producción abierta, con su motivo', ($s['state_key'] ?? '') === 'paused' && ($s['state_detail'] ?? '') === 'Cambio de bobina' && ($s['minutes'] ?? 0) === 10);
$check('apertura abierta = preparando', ($by['SERI II']['state_key'] ?? '') === 'setup');
$check('OT abierta sin evento abierto', ($by['SELLADORA 5']['state_key'] ?? '') === 'idle' && ($by['SELLADORA 5']['cc'] ?? '') === '26-00200-1');
$check('turno sin OT', ($by['REBOBINADORA']['state_key'] ?? '') === 'no_ot' && ($by['REBOBINADORA']['cc'] ?? 'x') === '');
$check('turno en pausa: la operadora está en otra máquina', ($by['PULPO SERIGRAFICO']['state_key'] ?? '') === 'away' && ($by['PULPO SERIGRAFICO']['process'] ?? '') === 'Pulpo Serigráfico'
    && ($by['FLEXO I']['state_key'] ?? '') === 'producing');
$check('el turno cerrado no tapa al abierto de la misma máquina', ($by['SELLADORA 2']['worker_ot_id'] ?? 0) === 12);
$check('conteo: 2 produciendo, 1 en pausa, 4 en otro estado', $snap['counts'] === ['total' => 7, 'producing' => 2, 'paused' => 1, 'other' => 4], json_encode($snap['counts']));
$check('orden: primero las que producen', ($snap['machines'][0]['state_key'] ?? '') === 'producing' && ($snap['machines'][1]['state_key'] ?? '') === 'producing');
$empty = (new MachineLiveStatusService($erp))->snapshot($now + 100 * $h);
$check('sin turnos abiertos recientes: lista vacía', $empty['machines'] === [] && $empty['counts']['total'] === 0);

echo $fail === 0 ? "\nMAQUINAS EN PRODUCCION OK\n" : "\nMAQUINAS EN PRODUCCION: $fail fallas\n";
exit($fail > 0 ? 1 : 0);
