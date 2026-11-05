<?php
declare(strict_types=1);
// Bobinas y operadores: el traspaso de carga entre OT no debe cambiar quién cargó, ni quitarle bobinas a otro operador activo.
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "pdo_sqlite no disponible: prueba omitida\n";
    exit(0);
}
require_once dirname(__DIR__, 2) . '/src/MachineRollContinuityService.php';

$fail = 0;
$check = function (string $name, bool $ok) use (&$fail): void {
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $fail++;
    }
};

$erp = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$trz = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$erp->exec("CREATE TABLE prod_worker_init (id INTEGER PRIMARY KEY, win_equipoid INT, win_status INT, win_wrkid INT);
CREATE TABLE prod_worker_ot (id INTEGER PRIMARY KEY, wok_init_id INT, wok_ag_id INT, wok_status INT, wok_crtdat INT);");
$trz->exec("CREATE TABLE rolls (id INTEGER PRIMARY KEY, roll_code TEXT, status TEXT, weight_kg REAL, current_work_order_id INT);
CREATE TABLE events (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, payload TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);");

$service = new MachineRollContinuityService($erp, $trz);
$resolve = static fn(int $ot, bool $create): int => $ot; // La OT de trazabilidad usa el mismo número en esta prueba.
$mount = static function (int $rollId, int $ot, int $machine, string $who) use ($trz): void {
    $trz->prepare("INSERT INTO rolls (id, roll_code, status, weight_kg, current_work_order_id) VALUES (?,?, 'IN_PROCESS', 135, ?)")
        ->execute([$rollId, 'BOB-' . $rollId, $ot]);
    $trz->prepare("INSERT INTO events (type, payload) VALUES ('ROLL_MOUNTED_ON_MACHINE', ?)")
        ->execute([json_encode(['roll_id' => $rollId, 'roll_code' => 'BOB-' . $rollId, 'worker_ot_id' => $ot, 'machine_id' => $machine, 'operator' => $who, 'entry_weight_kg' => 135])]);
};

// Caso real del 5 nov en FLEXO III: Juan Alberto (10) sigue trabajando y Damian (20) abre otra OT en esa máquina.
$erp->exec("INSERT INTO prod_worker_init VALUES (1,5,1,10),(2,5,1,20);
INSERT INTO prod_worker_ot VALUES (100,1,1,1,1),(200,2,2,1,2);");
$mount(1, 100, 5, 'Juan Alberto');
$continued = $service->continueForWorkOrder(200, $resolve);
$check('otro operador activo: su bobina no pasa a la OT nueva', $continued === [] && $service->state(1)['worker_ot_id'] === 100);
$check('otro operador activo: la bobina sigue en su OT', (int)$trz->query('SELECT current_work_order_id FROM rolls WHERE id=1')->fetchColumn() === 100);
$check('otro operador activo: figura quien la cargó', $service->loaderOf(1) === 'Juan Alberto');

// Máquina pausada por su operador: tampoco se la lleva otro.
$erp->exec("UPDATE prod_worker_init SET win_status=3 WHERE id=1");
$check('operador con la máquina en pausa: tampoco se traspasa', $service->continueForWorkOrder(200, $resolve) === []);

// Cambio de turno normal: el primero cerró y el siguiente hereda la carga, pero el nombre no cambia.
$erp->exec("UPDATE prod_worker_init SET win_status=2 WHERE id=1; UPDATE prod_worker_ot SET wok_status=2 WHERE id=100;");
$check('cambio de turno: la carga sigue en la máquina', $service->continueForWorkOrder(200, $resolve) === [1] && $service->state(1)['worker_ot_id'] === 200);
$check('cambio de turno: sigue figurando quien la cargó', $service->loaderOf(1) === 'Juan Alberto');
$check('el evento de traspaso lleva el nombre original', (json_decode((string)$trz->query('SELECT payload FROM events ORDER BY id DESC LIMIT 1')->fetchColumn(), true)['operator'] ?? '') === 'Juan Alberto');

// Mismo operador cambia de CC: la carga lo sigue, como hasta ahora.
$erp->exec("INSERT INTO prod_worker_init VALUES (3,6,1,30);
INSERT INTO prod_worker_ot VALUES (300,3,3,1,3),(301,3,4,1,4);");
$mount(2, 300, 6, 'Viloria');
$check('mismo operador, otro CC: la carga lo sigue', $service->continueForWorkOrder(301, $resolve) === [2] && $service->loaderOf(2) === 'Viloria');

// Datos ya guardados antes del arreglo: el evento de traspaso no trae el nombre, se recupera del montaje original.
$trz->exec("INSERT INTO rolls (id, roll_code, status, weight_kg, current_work_order_id) VALUES (3,'BOB-3','IN_PROCESS',135,200)");
$trz->prepare("INSERT INTO events (id, type, payload) VALUES (900,'ROLL_MOUNTED_ON_MACHINE',?)")->execute([json_encode(['roll_id' => 3, 'worker_ot_id' => 100, 'machine_id' => 5, 'operator' => 'Juan Alberto'])]);
$trz->prepare("INSERT INTO events (id, type, payload) VALUES (901,'ROLL_MOUNTED_ON_MACHINE',?)")->execute([json_encode(['roll_id' => 3, 'worker_ot_id' => 200, 'machine_id' => 5, 'continuity' => true, 'original_mount_event_id' => 900, 'operator_worker_id' => 20])]);
$check('registros anteriores: se recupera quien la cargó', $service->loaderOf(3) === 'Juan Alberto');

// Historial de cargas para el árbol: solo cargas reales, no los traspasos.
$history = $service->loadHistory(3);
$check('historial: una carga real con su operador y OT', count($history) === 1 && $history[0]['operator'] === 'Juan Alberto' && $history[0]['worker_ot_id'] === 100);
$check('bobina sin cargas: sin nombre ni historial', $service->loaderOf(999) === '' && $service->loadHistory(999) === []);

// Árbol de trazabilidad: la búsqueda de la bobina no debe depender de la bodega, y lee con pistola en teclado español.
$source = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/Production/ProductionMaterialsService.php');
$start = strpos($source, 'public function getTraceabilityTree');
$tree = substr($source, $start, (int)strpos($source, "\n    }\n", (int)strpos($source, 'return null;', (int)strpos($source, 'supplier_order po', $start))) - $start);
$check('árbol: no exige que la bobina tenga bodega ni SKU', !str_contains($tree, 'INNER JOIN warehouses') && !str_contains($tree, 'INNER JOIN skus'));
$check('árbol: corrige el guion que la pistola escribe como apóstrofo', str_contains($tree, 'normalizeTraceabilityCode($queryCode)'));
$check('árbol: no usa el número guardado en la bobina como si fuera la OT del ERP', str_contains($tree, 'resolveRollWorkerOt($roll)') && !str_contains($tree, "\$woId = (int)(\$roll['current_work_order_id']"));

echo $fail === 0 ? "\nBOBINAS Y OPERADORES OK\n" : "\nBOBINAS Y OPERADORES: $fail fallas\n";
exit($fail === 0 ? 0 : 1);
