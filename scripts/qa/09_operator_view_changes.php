<?php

declare(strict_types=1);

// QA · Vista de operadores: «Materiales», OT en curso ajena, OT sin actividad descartada, cabecera de Embalaje y
// cierre de Embalaje sin «Quién recibe». Solo SQLite en memoria y lectura de archivos.

require_once __DIR__ . '/../../src/bootstrap.php';

$fail = 0;
$total = 0;
$check = function (string $name, bool $ok, string $extra = '') use (&$fail, &$total): void {
    $total++;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK   ' : 'FAIL ') . $name . ($ok || $extra === '' ? '' : ' → ' . $extra) . "\n";
};
$root = dirname(__DIR__, 2);
$read = static fn(string $rel): string => (string)file_get_contents($root . '/' . $rel);

// ── Módulo 1: textos ─────────────────────────────────────────────────────────
$active = $read('src/Views/production/work_orders/active_screen.php');
$new = $read('src/Views/production/work_orders/new_screen.php');
$table = $read('src/Views/production/work_orders/machine_table.php');
$check('En Curso y Nueva OT ya no dicen «Bobinas» en los botones', !preg_match('/fa-dolly"><\/i> (Solicitar )?Bobinas?/', $active . $new)
    && !str_contains($table, '">Bobinas</a>') && !str_contains($active . $new, 'cargar bobinas'));
$check('los botones dicen «Materiales» y siguen yendo a la misma ruta', substr_count($active, 'fa-dolly"></i> Materiales') === 4
    && substr_count($active, 'fa-dolly"></i> Solicitar Materiales') === 4 && substr_count($new, 'Solicitar Materiales') === 6
    && str_contains($table, '">Materiales</a>') && substr_count($active, '/production/materials/request') === 8);

// ── Módulo 2: OT en curso de otro operador ──────────────────────────────────
$erp = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$erp->exec("
CREATE TABLE prod_worker_init (id INTEGER PRIMARY KEY, win_wrkid INT, win_equipoid INT, win_status INT);
CREATE TABLE prod_worker_ot (id INTEGER PRIMARY KEY AUTOINCREMENT, wok_ag_id INT, wok_init_id INT, wok_crtdat INT, wok_enddat INT DEFAULT 0, wok_status INT, wok_idusuario INT DEFAULT 0);
CREATE TABLE workers (id INTEGER PRIMARY KEY, wrk_firstname TEXT, wrk_lastname TEXT, wrk_status INT DEFAULT 1);
CREATE TABLE equipo (id INTEGER PRIMARY KEY, equipo_name TEXT);
CREATE TABLE prod_worker_ot_events (id INTEGER PRIMARY KEY AUTOINCREMENT, evt_prod_worker_otid INT, evt_type TEXT, evt_status INT, evt_crtdat INT DEFAULT 0, evt_enddat INT DEFAULT 0,
    evt_amount REAL DEFAULT 0, prod_bobina_kg REAL DEFAULT 0, evt_amount_metros_lineales REAL DEFAULT 0, evt_amount_metros_maquina REAL DEFAULT 0,
    overrideembalaje_act INT DEFAULT 0, overrideembalaje_selladora_evtrefid INT DEFAULT 0);
CREATE TABLE prod_worker_ot_defectunits (id INTEGER PRIMARY KEY AUTOINCREMENT, evt_refid INT, evt_status INT, evt_amount REAL DEFAULT 0, evt_kgstounits REAL DEFAULT 0, evt_mtstounits REAL DEFAULT 0);
CREATE TABLE prod_worker_ot_autocontrol (id INTEGER PRIMARY KEY AUTOINCREMENT, ctr_init_id INT);
CREATE TABLE prod_worker_embalajes (id INTEGER PRIMARY KEY AUTOINCREMENT, evt_prod_worker_otid INT);
CREATE TABLE orders_classify_subproducts_inputweights (id INTEGER PRIMARY KEY AUTOINCREMENT, prod_worker_ot_id INT);
INSERT INTO workers VALUES (1, 'Juan', 'Pérez', 1), (2, 'Ana', 'Soto', 1), (3, 'Luis', 'Rojas', 1);
INSERT INTO equipo VALUES (5, 'SELLADORA 2');
INSERT INTO prod_worker_init VALUES (10, 1, 5, 1), (20, 2, 5, 1), (30, 3, 5, 2), (40, 1, 5, 3);
");
$agenda = new WorkOrderAgendaService($erp);
$erp->exec("INSERT INTO prod_worker_ot (wok_ag_id, wok_init_id, wok_crtdat, wok_status) VALUES (100, 20, " . mktime(8, 15) . ", 1)");
$owner = $agenda->findForeignOpenOwner(100, 1);
$check('OT abierta por otra operadora con turno vigente: se informa quién la tiene', $owner !== null && $owner['name'] === 'Ana Soto' && $owner['equipo'] === 'SELLADORA 2', json_encode($owner));
$check('la dueña de la OT sí puede entrar', $agenda->findForeignOpenOwner(100, 2) === null);
$erp->exec("INSERT INTO prod_worker_ot (wok_ag_id, wok_init_id, wok_crtdat, wok_status) VALUES (200, 30, 1, 1)");
$check('si el turno del otro ya se cerró (cambio de turno), se puede tomar', $agenda->findForeignOpenOwner(200, 1) === null);
$erp->exec("INSERT INTO prod_worker_ot (wok_ag_id, wok_init_id, wok_crtdat, wok_status) VALUES (300, 40, 1, 1)");
$check('un turno pausado (operador en otra máquina) sigue siendo dueño', $agenda->findForeignOpenOwner(300, 2)['name'] === 'Juan Pérez');
$erp->exec("INSERT INTO prod_worker_ot (wok_ag_id, wok_init_id, wok_crtdat, wok_status) VALUES (400, 20, 1, 2)");
$check('una OT ya cerrada no bloquea', $agenda->findForeignOpenOwner(400, 1) === null);

$_SESSION = ['user_type' => '2', 'user_groups' => [5]];
$rows = productionMarkForeignActiveRows([
    ['ag_id' => 100, 'win_wrkid' => 2, 'win_status' => 1, 'wrk_firstname' => 'Ana', 'wrk_lastname' => 'Soto'],
    ['ag_id' => 101, 'win_wrkid' => 1, 'win_status' => 1, 'wrk_firstname' => 'Juan', 'wrk_lastname' => 'Pérez'],
    ['ag_id' => 102, 'win_wrkid' => 3, 'win_status' => 2, 'wrk_firstname' => 'Luis', 'wrk_lastname' => 'Rojas'],
], 1);
$check('En Curso: la OT ajena queda bloqueada; la propia y la de turno cerrado no', $rows[0]['locked_by'] === 'Ana Soto' && $rows[1]['locked_by'] === '' && $rows[2]['locked_by'] === '');
$check('el bloqueo muestra quién la tiene y no lleva enlace para entrar', str_contains(productionLockedOperateBadge('Ana Soto'), '🔒 En curso por Ana Soto')
    && !str_contains(productionLockedOperateBadge('Ana Soto'), 'href'));
$_SESSION = ['user_type' => '2', 'user_groups' => ['31']];
$check('supervisor (grupo 31) puede entrar a todas', sessionIsProductionSupervisor() && productionMarkForeignActiveRows($rows, 1)[0]['locked_by'] === '');
$_SESSION = ['user_type' => '1'];
$check('administrador puede entrar a todas', sessionIsProductionSupervisor());
$_SESSION = [];
$check('mensaje de bloqueo claro', str_contains(productionForeignOwnerMessage($owner), 'Ana Soto') && str_contains(productionForeignOwnerMessage($owner), 'supervisor'));
$events = $read('src/Http/Production/ProductionEventsModule.php');
$check('la ruta para operar bloquea antes de crear el registro en la OT', strpos($events, 'productionForeignOwnerBlocking($prodService, $agId, $workerId);') < strpos($events, '$startRes = $prodService->startWorkerOt($agId, $initId);'));
$check('los otros puntos que crean el registro también bloquean (inicio, apertura y materiales)',
    substr_count($events . $read('src/Http/Production/ProductionMaterialsModule.php') . $read('src/Http/Production/WorkOrderLifecycleModule.php'), 'productionForeignOwnerBlocking(') === 6);

// ── Módulo 3: OT sin actividad → descartada ─────────────────────────────────
$closure = new class($erp) extends ProductionClosureService {
    public function getPrintedRollsSummary(int $workerOtId): array
    {
        return [];
    }
};
$newOt = function (array $events = [], string $extraTable = '') use ($erp): int {
    $erp->exec("INSERT INTO prod_worker_ot (wok_ag_id, wok_init_id, wok_crtdat, wok_status) VALUES (900, 10, " . (time() - 60) . ", 1)");
    $id = (int)$erp->lastInsertId();
    $erp->exec("INSERT INTO prod_worker_ot_events (evt_prod_worker_otid, evt_type, evt_status, evt_crtdat) VALUES ($id, 'apertura', 1, " . (time() - 60) . ")");
    foreach ($events as [$type, $status, $amount]) {
        $erp->exec("INSERT INTO prod_worker_ot_events (evt_prod_worker_otid, evt_type, evt_status, evt_amount) VALUES ($id, '$type', $status, $amount)");
    }
    if ($extraTable !== '') {
        $col = ['prod_worker_ot_autocontrol' => 'ctr_init_id', 'prod_worker_embalajes' => 'evt_prod_worker_otid', 'orders_classify_subproducts_inputweights' => 'prod_worker_ot_id'][$extraTable];
        $erp->exec("INSERT INTO $extraTable ($col) VALUES ($id)");
    }
    return $id;
};
$empty = $newOt();
$check('OT con solo la apertura: sin actividad', $closure->isWorkOrderEmpty($empty));
$check('con una pausa: tiene actividad', !$closure->isWorkOrderEmpty($newOt([['pause', 2, 0]])));
$check('con colación o mantención: tiene actividad', !$closure->isWorkOrderEmpty($newOt([['mantencion', 2, 0]])));
$check('con producción: tiene actividad', !$closure->isWorkOrderEmpty($newOt([['prod', 2, 120]])));
$check('con autocontrol: tiene actividad', !$closure->isWorkOrderEmpty($newOt([], 'prod_worker_ot_autocontrol')));
$check('con embalaje registrado: tiene actividad', !$closure->isWorkOrderEmpty($newOt([], 'prod_worker_embalajes')));
$check('un evento anulado no cuenta como actividad', $closure->isWorkOrderEmpty($newOt([['pause', 0, 0]])));
$res = $closure->closeWorkOrder($empty);
$ot = $erp->query("SELECT * FROM prod_worker_ot WHERE id = $empty")->fetch();
$ap = $erp->query("SELECT * FROM prod_worker_ot_events WHERE evt_prod_worker_otid = $empty AND evt_type = 'apertura'")->fetch();
$check('al cerrar sin actividad: queda descartada (estado -1, con fecha de término)', $res['ok'] && !empty($res['discarded']) && (int)$ot['wok_status'] === -1 && (int)$ot['wok_enddat'] > 0, json_encode($res));
$check('su apertura queda anulada (no aparece en informes por eventos)', (int)$ap['evt_status'] === 0 && (int)$ap['evt_enddat'] > 0);
$check('los informes que filtran estado > 0 ya no la cuentan', (int)$erp->query("SELECT COUNT(*) FROM prod_worker_ot WHERE id = $empty AND wok_status > 0")->fetchColumn() === 0);
$withPause = $newOt([['pause', 2, 0]]);
$res2 = $closure->closeWorkOrder($withPause);
$check('una OT con paradas pero sin producción se cierra como hoy (estado 2, sin supervisor)', $res2['ok'] && empty($res2['discarded'])
    && (int)$erp->query("SELECT wok_status FROM prod_worker_ot WHERE id = $withPause")->fetchColumn() === 2, json_encode($res2));
$sub = $read('src/Views/production/closure/closure_subform.php');
$check('el operador ve «será descartada» al cerrar y «OT descartada» después', str_contains($sub, 'Esta OT no registra producción y será descartada')
    && str_contains($active, 'OT descartada: no registró producción') && str_contains($read('src/Http/Production/ProductionClosureModule.php'), 'discarded=1'));
$check('listados que no filtraban estado excluyen las descartadas', str_contains($read('src/TraceabilityService.php'), 'pwo.wok_status <> -1')
    && str_contains($read('src/FabricationDossierService.php'), "evt.evt_type = 'apertura' AND pwo.wok_status <> -1"));

// ── Módulo 4: cabecera propia de Embalaje ────────────────────────────────────
$console = $read('src/Views/production/operator_console.php');
$pack = $read('src/Views/production/packaging/info_section.php');
$check('Embalaje usa su propia cabecera (antes caía en la de Corte y Sellado)', (bool)preg_match('/elseif \(!empty\(\$isEmbalaje\) && !\$isSelladora && !\$isRebobinadora\): \?>\s*<\?php renderPackagingConsoleInfoSection/', $console)
    && strpos($console, 'renderPackagingConsoleInfoSection') < strpos($console, 'renderSealingConsoleInfoSection'));
$check('la cabecera de Embalaje tiene cliente, datos de embalaje y entregas', str_contains($pack, 'Información Cliente') && str_contains($pack, 'renderMachineFabricationExtras($agenda, true)')
    && str_contains($pack, 'Fechas de entrega'));
$check('y no trae configuración ni insumos de Corte y Sellado', !str_contains($pack, 'Corte de Bolsa') && !str_contains($pack, 'Doblez superior') && !str_contains($pack, 'Dado de Manillas')
    && !str_contains($pack, 'Características de tela') && !str_contains($pack, 'Configuración'));

// ── Módulo 5: cierre de Embalaje sin «Quién recibe» ─────────────────────────
$check('«Recibe producción» solo en Corte y Sellado, no en Embalaje', (bool)preg_match('/if \(\$isSelladora && \$overrideEmbalaje !== 1\): \?>\s*<\?php renderProductionReceiverField\(/', $console)
    && !preg_match('/if \(\$isSelladora \|\| \$overrideEmbalaje === 1\): \?>\s*<\?php renderProductionReceiverField\(/', $console));
$check('en Embalaje recibe el supervisor que autoriza el cierre', str_contains($events, "\$phaseActor['receiver_supervisor_login'] = trim((string)(\$_POST['pack_supervisor_user'] ?? ''));"));
$erp->exec("CREATE TABLE user (id INTEGER PRIMARY KEY, user_login TEXT, user_firstname TEXT, user_lastname TEXT)");
$erp->exec("INSERT INTO user VALUES (7, 'hector', 'Héctor', 'Muñoz')");
$ev = new ProductionEventsService($erp, new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]));
$m = new ReflectionMethod($ev, 'validateProductionPhaseActor');
$m->setAccessible(true);
try {
    $actor = $m->invoke($ev, ['worker_id' => 1, 'name' => 'Juan Pérez', 'receiver_worker_id' => 0, 'receiver_supervisor_login' => 'hector'], 1, null);
} catch (Throwable $e) {
    $actor = ['error' => $e->getMessage()];
}
$check('el receptor queda con el nombre del supervisor', ($actor['receiver_name'] ?? '') === 'Héctor Muñoz' && (int)($actor['receiver_worker_id'] ?? -1) === 0
    && !isset($actor['receiver_supervisor_login']), json_encode($actor, JSON_UNESCAPED_UNICODE));

echo "\n" . ($fail === 0 ? "VISTA DE OPERADORES OK ($total)" : "VISTA DE OPERADORES: $fail de $total con error") . "\n";
exit($fail === 0 ? 0 : 1);
