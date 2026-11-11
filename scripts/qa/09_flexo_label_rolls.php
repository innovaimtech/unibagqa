<?php
declare(strict_types=1);
// Flexo · etiquetas de bobinas generadas al desmontar (impresa + sobrante) visibles para reimprimir (sqlite en memoria).
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
$opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
$erp = new PDO('sqlite::memory:', null, null, $opts);
$erp->exec('CREATE TABLE prod_worker_ot (id INTEGER PRIMARY KEY, wok_ag_id INT);
INSERT INTO prod_worker_ot VALUES (500, 77), (501, 77), (600, 88);');
$trz = new PDO('sqlite::memory:', null, null, $opts);
$trz->exec("CREATE TABLE rolls (id INTEGER PRIMARY KEY, roll_code TEXT, weight_kg REAL, meters REAL, status TEXT, reception_mode TEXT, process_stage TEXT,
    parent_roll_id INT, source_work_order_id INT, created_at TEXT);
INSERT INTO rolls VALUES
 (1,'TEL0088-ORIG',300,0,'CONSUMED','ERP_STOCK','PRODUCTION',NULL,NULL,'2026-11-10 08:00:00'),
 (2,'ROLL-SE-00002',250,3000,'RECEIVED',NULL,'PRINTED',1,500,'2026-11-10 12:00:00'),
 (3,'TEL0088-SOB-261110-AB12',40,500,'RECEIVED','SOBRANTE','PRODUCTION',1,500,'2026-11-10 12:00:01'),
 (4,'ROLL-SE-00004',120,1500,'RECEIVED',NULL,'PRINTED',1,501,'2026-11-11 09:00:00'),
 (5,'TEL0099-ENTRADA',200,0,'IN_PROCESS','BODEGA_3000','PRODUCTION',NULL,500,'2026-11-10 07:00:00'),
 (6,'ROLL-SE-00006',90,900,'RECEIVED',NULL,'PRINTED',1,600,'2026-11-11 10:00:00');");

$svc = new ProductionService($erp, $trz);
$rows = $svc->getWorkOrderLabelRolls(501, 77);
$codes = array_column($rows, 'roll_code');
$check('lista impresa y sobrante de todos los turnos de la agenda, la más nueva primero', $codes === ['ROLL-SE-00004', 'TEL0088-SOB-261110-AB12', 'ROLL-SE-00002'], implode(',', $codes));
$check('no incluye bobinas de entrada ni de otra agenda', !in_array('TEL0099-ENTRADA', $codes, true) && !in_array('ROLL-SE-00006', $codes, true));
$by = array_column($rows, null, 'roll_code');
$check('tipo, peso y bobina de origen', $by['TEL0088-SOB-261110-AB12']['kind'] === 'leftover' && $by['ROLL-SE-00002']['kind'] === 'printed'
    && $by['ROLL-SE-00002']['weight_kg'] === 250.0 && $by['ROLL-SE-00002']['parent_code'] === 'TEL0088-ORIG');
$check('sin agenda: solo la OT del turno', array_column($svc->getWorkOrderLabelRolls(500), 'roll_code') === ['TEL0088-SOB-261110-AB12', 'ROLL-SE-00002']);
$check('sin OT ni agenda: vacío', $svc->getWorkOrderLabelRolls(0, 0) === []);

$view = (string)file_get_contents($root . '/src/Views/production/materials/materials_subform.php');
$check('pantalla de materiales muestra la sección con Imprimir y Ver', str_contains($view, 'getWorkOrderLabelRolls(') && str_contains($view, 'Etiquetas de bobinas de esta OT')
    && str_contains($view, "/label?server_print=1"));

echo $fail === 0 ? "\nETIQUETAS DE BOBINAS OK\n" : "\nETIQUETAS DE BOBINAS: $fail fallas\n";
exit($fail > 0 ? 1 : 0);
