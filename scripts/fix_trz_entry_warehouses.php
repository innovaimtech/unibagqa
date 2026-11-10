<?php

declare(strict_types=1);

/**
 * Deja cada código de trazabilidad (traceability_warehouse_entries) con la bodega real de su bobina o caja, usando
 * SIEMPRE el id de bodega del ERP en warehouse_id y el nombre de esa bodega (regla única del sistema).
 *
 *  - ROLL: bodega = rolls.warehouse_id (id local) -> su erp_storehouse_id y nombre.
 *  - BOX : bodega = boxes.erp_st_id, o el erp_storehouse_id de boxes.warehouse_id. Cajas despachadas no se tocan.
 *  - PRODUCT / PALLET sin entidad con bodega: si warehouse_id es un id LOCAL cuyo nombre coincide con el guardado,
 *    se pasa al id ERP de esa bodega.
 * No cambia rolls, boxes ni el ERP; solo warehouse_id y warehouse_name de los códigos.
 *
 *   php scripts/fix_trz_entry_warehouses.php          # simulación
 *   php scripts/fix_trz_entry_warehouses.php --apply
 */

if (PHP_SAPI !== 'cli') {
    exit("Solo por línea de comandos.\n");
}
require_once dirname(__DIR__) . '/src/bootstrap.php';
Env::load(dirname(__DIR__) . '/.env');
date_default_timezone_set('America/Santiago');

$apply = in_array('--apply', $argv, true);
$t = Db::trzPdo();
$t->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (!$apply) {
    $t->exec('SET SESSION TRANSACTION READ ONLY');
}

$wh = [];        // id local => [erp, name]
$byErp = [];     // erp => name (primera bodega local con ese erp)
foreach ($t->query('SELECT id, erp_storehouse_id, name FROM warehouses ORDER BY id') as $w) {
    $wh[(int)$w['id']] = ['erp' => (int)$w['erp_storehouse_id'], 'name' => (string)$w['name']];
    if ((int)$w['erp_storehouse_id'] > 0 && !isset($byErp[(int)$w['erp_storehouse_id']])) {
        $byErp[(int)$w['erp_storehouse_id']] = (string)$w['name'];
    }
}

$changes = []; // [entry_id, new_wh, new_name, tipo, antes]

// ROLL
$rows = $t->query("SELECT e.id, e.warehouse_id, e.warehouse_name, r.warehouse_id AS local_wh
    FROM traceability_warehouse_entries e JOIN rolls r ON r.id = e.entity_id
    WHERE e.entity_type = 'ROLL' AND r.warehouse_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $w = $wh[(int)$r['local_wh']] ?? null;
    if ($w === null || $w['erp'] <= 0) {
        continue;
    }
    if ((int)$r['warehouse_id'] !== $w['erp'] || (string)$r['warehouse_name'] !== $w['name']) {
        $changes[] = [(int)$r['id'], $w['erp'], $w['name'], 'ROLL', $r['warehouse_id'] . ' ' . $r['warehouse_name']];
    }
}

// BOX
$rows = $t->query("SELECT e.id, e.warehouse_id, e.warehouse_name, b.erp_st_id, b.warehouse_id AS local_wh, b.status
    FROM traceability_warehouse_entries e JOIN boxes b ON b.id = e.entity_id
    WHERE e.entity_type = 'BOX' AND b.status <> 'DISPATCHED'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $erp = (int)($r['erp_st_id'] ?? 0);
    if ($erp <= 0 && (int)($r['local_wh'] ?? 0) > 0) {
        $erp = $wh[(int)$r['local_wh']]['erp'] ?? 0;
    }
    if ($erp <= 0) {
        continue;
    }
    // Nombre: el que ya tiene si el id no cambia (puede venir del ERP, más largo); si cambia, el de la bodega.
    $name = (int)$r['warehouse_id'] === $erp ? (string)$r['warehouse_name'] : ($byErp[$erp] ?? (string)$r['warehouse_name']);
    if ((int)$r['warehouse_id'] !== $erp) {
        $changes[] = [(int)$r['id'], $erp, $name, 'BOX', $r['warehouse_id'] . ' ' . $r['warehouse_name']];
    }
}

// PRODUCT / PALLET (y ROLL/BOX sin entidad): id local cuyo nombre coincide -> id ERP.
$rows = $t->query("SELECT e.id, e.entity_type, e.warehouse_id, e.warehouse_name FROM traceability_warehouse_entries e
    WHERE e.entity_type IN ('PRODUCT','PALLET')
       OR (e.entity_type = 'ROLL' AND NOT EXISTS (SELECT 1 FROM rolls r WHERE r.id = e.entity_id))
       OR (e.entity_type = 'BOX' AND NOT EXISTS (SELECT 1 FROM boxes b WHERE b.id = e.entity_id))")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $local = $wh[(int)$r['warehouse_id']] ?? null;
    $isErpAlready = isset($byErp[(int)$r['warehouse_id']]) && $byErp[(int)$r['warehouse_id']] === (string)$r['warehouse_name'];
    if (!$isErpAlready && $local !== null && $local['erp'] > 0 && $local['name'] === (string)$r['warehouse_name'] && $local['erp'] !== (int)$r['warehouse_id']) {
        $changes[] = [(int)$r['id'], $local['erp'], $local['name'], (string)$r['entity_type'], $r['warehouse_id'] . ' ' . $r['warehouse_name']];
    }
}

$summary = [];
foreach ($changes as [$id, $newWh, $newName, $type, $before]) {
    $k = "$type | $before  ->  $newWh $newName";
    $summary[$k] = ($summary[$k] ?? 0) + 1;
}
ksort($summary);
foreach ($summary as $k => $n) {
    printf("  %5d  %s\n", $n, $k);
}
echo '  TOTAL ' . count($changes) . " códigos a corregir\n";
if (!$apply) {
    echo "Simulación: no se escribió nada. Para aplicar: --apply\n";
    exit(0);
}

$upd = $t->prepare('UPDATE traceability_warehouse_entries SET warehouse_id = ?, warehouse_name = ? WHERE id = ?');
$t->beginTransaction();
try {
    foreach ($changes as [$id, $newWh, $newName]) {
        $upd->execute([$newWh, $newName, $id]);
    }
    $t->commit();
    echo 'Aplicado: ' . count($changes) . " códigos corregidos.\n";
} catch (Throwable $e) {
    $t->rollBack();
    echo 'Error, no se cambió nada: ' . $e->getMessage() . "\n";
    exit(1);
}
