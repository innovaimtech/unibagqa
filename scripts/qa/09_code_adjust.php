<?php

declare(strict_types=1);

// QA · Bodega → Ajuste de códigos de trazabilidad (juntar saldos y borrar). Solo SQLite en memoria.

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

$trz = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$trz->exec("
CREATE TABLE boxes (id INTEGER PRIMARY KEY AUTOINCREMENT, box_code TEXT UNIQUE, work_order_id INT, source_roll_id INT, pallet_id INT, final_sku TEXT NOT NULL, units_qty REAL NOT NULL, destination_mode TEXT NOT NULL DEFAULT 'BODEGA', customer_order_ref TEXT, warehouse_id INT, operator_name TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'CREATED', created_at TEXT);
CREATE TABLE traceability_warehouse_entries (id INTEGER PRIMARY KEY AUTOINCREMENT, traceability_code TEXT UNIQUE, batch_id TEXT, entity_type TEXT, entity_id INT, warehouse_id INT NOT NULL, warehouse_name TEXT NOT NULL, item_id INT, item_code TEXT NOT NULL, item_description TEXT NOT NULL, quantity REAL, quantity_initial REAL, unit_name TEXT, box_count INT, order_ref TEXT, operator_name TEXT NOT NULL, comments TEXT, status TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE movements (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT, entity_id INT, movement_type TEXT, from_warehouse_id INT, to_warehouse_id INT, payload TEXT);
");
$erp = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$svc = new TraceabilityCodeAdjustService($trz, $erp);
$svc->ensureSchema();

// Cajas: dos con saldo del mismo artículo, CC y bodega; una de otro CC; una de otra bodega; una bobina; una en máquina.
$box = function (string $code, float $units, string $cc = '26-00100-1', int $st = 14, int $item = 500, string $status = 'IN_WAREHOUSE', string $source = 'ETIQUETA') use ($trz): void {
    $trz->prepare("INSERT INTO boxes (box_code, final_sku, units_qty, units_initial, customer_order_ref, operator_name, status, erp_item_id, erp_st_id, source_kind)
        VALUES (?, 'BOL0352', ?, ?, ?, 'QA', ?, ?, ?, ?)")->execute([$code, $units, $units, $cc, $status, $item, $st, $source]);
    $id = (int)$trz->lastInsertId();
    $trz->prepare("INSERT INTO traceability_warehouse_entries (traceability_code, entity_type, entity_id, warehouse_id, warehouse_name, item_id, item_code,
        item_description, quantity, quantity_initial, unit_name, order_ref, operator_name, status)
        VALUES (?, 'BOX', ?, ?, ?, ?, 'BOL0352', 'BOLSA TOTTUS', ?, ?, 'Unid.', ?, 'QA', ?)")
        ->execute([$code, $id, $st, $st === 14 ? '1000 BODEGA' : '400 BODEGA', $item, $units, $units, $cc,
            $status === 'IN_MACHINE' ? 'IN_WAREHOUSE' : $status]);
};
$box('BXAAAA0001', 120);
$box('BXAAAA0002', 380);
$box('BXAAAA0003', 50, '26-00999-1');
$box('BXAAAA0004', 70, '26-00100-1', 1);
$box('BXAAAA0005', 90, '26-00100-1', 14, 500, 'IN_MACHINE');
$box('BXAAAA0006', 10, '26-00100-1', 14, 500, 'IN_WAREHOUSE', 'SALDO');
$trz->exec("INSERT INTO traceability_warehouse_entries (traceability_code, entity_type, entity_id, warehouse_id, warehouse_name, item_id, item_code,
    item_description, quantity, unit_name, operator_name, status) VALUES ('TEL-ROLL-1', 'ROLL', 9, 5, '100 BODEGA', 77, 'TEL0180', 'TELA', 1, 'UNID', 'QA', 'IN_WAREHOUSE')");

$a = $svc->find('bxaaaa0001');
$check('busca sin importar mayúsculas y muestra artículo, CC, bodega y saldo', $a !== null && $a['code'] === 'BXAAAA0001' && $a['qty'] === 120.0
    && $a['order_ref'] === '26-00100-1' && $a['warehouse_name'] === '1000 BODEGA' && $a['blocked'] === '');
$check("la pistola en teclado español («'» por «-») también encuentra el código", TraceabilityCodeAdjustService::normalizeCode("bx'1") === 'BX-1');
$check('al escanear dos cajas compatibles no hay aviso', TraceabilityCodeAdjustService::mergeProblem($a, $svc->find('BXAAAA0002')) === '');
$check('al escanear dos cajas de CC distinto avisa antes de confirmar', str_contains(TraceabilityCodeAdjustService::mergeProblem($a, $svc->find('BXAAAA0003')), 'CC distintos'));
$check('escanear dos veces la misma caja avisa', str_contains(TraceabilityCodeAdjustService::mergeProblem($a, $a), 'mismo código'));

$err = static function (callable $f): string {
    try {
        $f();
        return '';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
};
$check('no junta CC distintos', str_contains($err(fn() => $svc->merge('BXAAAA0001', 'BXAAAA0003', null, 'Gabriel')), 'CC distintos'));
$check('no junta bodegas distintas', str_contains($err(fn() => $svc->merge('BXAAAA0001', 'BXAAAA0004', null, 'Gabriel')), 'bodegas distintas'));
$check('no toca una caja cargada en máquina', str_contains($err(fn() => $svc->merge('BXAAAA0005', 'BXAAAA0001', null, 'Gabriel')), 'máquina'));
$check('no toca códigos temporales del Pulpo', str_contains($err(fn() => $svc->merge('BXAAAA0006', 'BXAAAA0001', null, 'Gabriel')), 'temporal'));
$check('no toca bobinas', str_contains($err(fn() => $svc->deleteMany(['TEL-ROLL-1'], 'IMPRESA_DE_MAS', 'Gabriel')), 'bobina'));
$check('no pasa más de lo que tiene', str_contains($err(fn() => $svc->merge('BXAAAA0001', 'BXAAAA0002', 500, 'Gabriel')), 'tiene 120'));
$check('mismo código en origen y destino no se permite', str_contains($err(fn() => $svc->merge('BXAAAA0001', 'bxaaaa0001', null, 'Gabriel')), 'mismo'));
$check('un error no deja nada a medias', $svc->find('BXAAAA0001')['qty'] === 120.0 && $svc->find('BXAAAA0002')['qty'] === 380.0);

// Parcial: 20 unidades.
$r = $svc->merge('BXAAAA0001', 'BXAAAA0002', 20, 'Gabriel', 'prueba parcial');
$check('juntar parcial: origen 100 y destino 400', $r['from']['qty'] === 100.0 && $r['to']['qty'] === 400.0 && $r['from']['status'] === 'IN_WAREHOUSE');
// Todo el resto.
$r = $svc->merge('BXAAAA0001', 'BXAAAA0002', null, 'Gabriel');
$check('juntar todo: origen queda sin saldo (consumido) y destino con 500', $r['from']['qty'] === 0.0 && $r['from']['status'] === 'CONSUMED' && $r['to']['qty'] === 500.0, json_encode([$r['from']['status'], $r['to']['qty']]));
$boxA = $trz->query("SELECT * FROM boxes WHERE box_code = 'BXAAAA0001'")->fetch();
$boxB = $trz->query("SELECT * FROM boxes WHERE box_code = 'BXAAAA0002'")->fetch();
$check('la caja (boxes) queda igual que su etiqueta', (float)$boxA['units_qty'] === 0.0 && $boxA['status'] === 'CONSUMED' && (float)$boxB['units_qty'] === 500.0);
$check('la etiqueta del destino pasa a mostrar el total (para reimprimir)', $r['to']['qty_initial'] === 500.0 && (float)$boxB['units_initial'] === 500.0);
$bag = new BagBoxService($trz, $erp);
$check('las cajas ajustadas cuentan como usadas (un embalaje corregido no las reemplaza)', $bag->isUsed($boxA) && $bag->isUsed($boxB));
$check('el total de unidades no cambia (120 + 380 = 500)', (float)$trz->query("SELECT SUM(units_qty) FROM boxes WHERE box_code IN ('BXAAAA0001','BXAAAA0002')")->fetchColumn() === 500.0);
$check('un código sin saldo ya no se puede usar', str_contains($err(fn() => $svc->merge('BXAAAA0001', 'BXAAAA0002', null, 'Gabriel')), 'no tiene saldo')
    || str_contains($err(fn() => $svc->merge('BXAAAA0001', 'BXAAAA0002', null, 'Gabriel')), 'consumido'));
$check('el comentario de la etiqueta dice a dónde se fue el saldo', str_contains((string)$trz->query("SELECT comments FROM traceability_warehouse_entries WHERE traceability_code = 'BXAAAA0001'")->fetchColumn(), 'Saldo juntado en BXAAAA0002'));

// Borrar
$check('borrar pide motivo', str_contains($err(fn() => $svc->deleteMany(['BXAAAA0003'], '', 'Gabriel')), 'motivo'));
$check('«Otro» pide detalle', str_contains($err(fn() => $svc->deleteMany(['BXAAAA0003'], 'OTRO', 'Gabriel', ' ')), 'Escribe'));
$check('no borra una caja que ya se usó (juntada)', str_contains($err(fn() => $svc->deleteMany(['BXAAAA0002'], 'IMPRESA_DE_MAS', 'Gabriel')), 'BXAAAA0002'));
$box('BXBBBB0001', 30);
$box('BXBBBB0002', 30);
$box('BXBBBB0003', 30);
$box('BXBBBB0004', 30);
$trz->exec("UPDATE traceability_warehouse_entries SET quantity = 12 WHERE traceability_code = 'BXBBBB0004'");
$trz->exec("UPDATE boxes SET units_qty = 12 WHERE box_code = 'BXBBBB0004'");
$check('no borra un código con el saldo cambiado', str_contains($err(fn() => $svc->deleteMany(['BXBBBB0004'], 'IMPRESA_DE_MAS', 'Gabriel')), 'saldo ya cambió'));
$msg = $err(fn() => $svc->deleteMany(['BXBBBB0001', 'BXBBBB0002', 'BXAAAA0005'], 'IMPRESA_DE_MAS', 'Gabriel'));
$check('borrar varios: si uno no se puede, no se borra ninguno y dice cuál', str_contains($msg, 'No se borró ningún código') && str_contains($msg, 'BXAAAA0005')
    && $svc->find('BXBBBB0001') !== null, $msg);
$many = $svc->deleteMany(['bxbbbb0001', 'BXBBBB0002', 'BXBBBB0003', 'BXBBBB0002', 'BXAAAA0003'], 'IMPRESA_DE_MAS', 'Gabriel', '6 etiquetas para 5 cajas');
$check('borrar varios de una vez (los repetidos cuentan una vez)', count($many) === 4);
$check('los códigos borrados ya no existen (etiqueta, caja y movimientos)', $svc->find('BXBBBB0001') === null && $svc->find('BXAAAA0003') === null
    && (int)$trz->query("SELECT COUNT(*) FROM boxes WHERE box_code IN ('BXBBBB0001','BXBBBB0002','BXBBBB0003','BXAAAA0003')")->fetchColumn() === 0);
$check('historial por acción', count($svc->history(40, '', 'MERGE')) === 2 && count($svc->history(40, '', 'DELETE')) === 4);
$h = $svc->history();
$check('el borrado queda en el historial con código, unidades, motivo y usuario', $h[0]['action'] === 'DELETE' && (float)$h[count($h) - 1]['from_before'] === 120.0
    && in_array('BXAAAA0003', array_column($h, 'from_code'), true) && $h[0]['reason'] === 'IMPRESA_DE_MAS' && $h[0]['operator_name'] === 'Gabriel', json_encode(array_column($h, 'action')));
$check('historial filtrado por código', count($svc->history(40, 'BXAAAA0002')) === 2);
$moves = (int)$trz->query("SELECT COUNT(*) FROM box_unit_moves WHERE state = 'DONE' AND worker_ot_id IS NULL")->fetchColumn();
$check('diario de cajas: movimientos hechos y sin OT (el Pulpo no los toma)', $moves === 4, (string)$moves);

// Pantallas (HTML) sin servidor.
$box('BXCCCC0001', 15);
$box('BXCCCC0002', 25);
$merge = codeAdjustLayout('juntar', '', '', codeAdjustMergePage($svc->find('BXCCCC0001'), $svc->find('BXCCCC0002'), 'BXCCCC0001', 'BXCCCC0002', '', $svc->history(30, '', 'MERGE')));
$check('juntar: dos cajas escaneadas, el operador elige cuál queda', substr_count($merge, 'name="keep"') === 2 && str_contains($merge, 'Queda esta caja')
    && str_contains($merge, 'Total: <b>40</b>') && !str_contains($merge, 'data-ca-pick'));
$check('juntar y borrar en pestañas separadas', str_contains($merge, '?tab=borrar') && str_contains($merge, '🗑 Borrar'));
$empty = codeAdjustLayout('borrar', '', '', codeAdjustDeletePage([], $svc->history(30, '', 'DELETE')));
$check('borrar: casillas para escanear como Solicitudes MP (sin consultar al servidor por cada código)', str_contains($empty, 'class="ca-card ca-slots"')
    && !str_contains($empty, 'ca-slot-count') && str_contains($empty, 'ensureEmpty()') && str_contains($empty, 'Cargar (0)') && !str_contains($empty, 'fetch(') && !str_contains($empty, 'id="ca_void"'));
$c1 = $svc->find('BXCCCC0001');
$rowsLoaded = [['code' => 'BXCCCC0001', 'info' => $c1, 'problem' => $svc->deleteProblem($c1)],
    ['code' => 'BXAAAA0002', 'info' => $svc->find('BXAAAA0002'), 'problem' => $svc->deleteProblem($svc->find('BXAAAA0002'))],
    ['code' => 'BXNOEXISTE', 'info' => null, 'problem' => 'No existe.']];
$void = codeAdjustLayout('borrar', '', '', codeAdjustDeletePage($rowsLoaded, $svc->history(30, '', 'DELETE')));
$check('borrar: lista cargada con los que se pueden borrar y los que no (en rojo con el motivo)', substr_count($void, 'class="bad"') === 2
    && str_contains($void, 'No existe.') && str_contains($void, 'Borrar (1)') && str_contains($void, 'name="codes" value="BXCCCC0001"')
    && str_contains($void, 'name="codes[]" value="BXNOEXISTE"') && !str_contains($void, 'Anular'));
$noHelp = static fn(string $h): bool => !str_contains($h, 'placeholder=') && !str_contains($h, 'ca-sub') && !str_contains($h, 'ca-help') && !str_contains($h, 'ca-step');
$check('sin subtítulos, textos de ayuda ni placeholders', $noHelp($merge) && $noHelp($void));
$bad = codeAdjustMergePage($svc->find('BXAAAA0001'), $svc->find('BXCCCC0002'), 'BXAAAA0001', 'BXCCCC0002', '', []);
$check('juntar: si no se pueden juntar no ofrece el botón', !str_contains($bad, 'name="keep"'));

echo "\n" . ($fail === 0 ? "AJUSTE DE CÓDIGOS OK ($total)" : "AJUSTE DE CÓDIGOS: $fail de $total con error") . "\n";
exit($fail === 0 ? 0 : 1);
