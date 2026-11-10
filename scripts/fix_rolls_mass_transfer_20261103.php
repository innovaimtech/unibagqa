<?php

declare(strict_types=1);

/**
 * Devuelve a la bodega 100 las bobinas TEL que el 3-nov-2026 (y antes, 14 y 29 de octubre) se pasaron en masa a la
 * 3000 con «solo trazabilidad» y que nunca se usaron: siguen RECEIVED, sin OT, sin escaneo de traspaso a producción
 * y su último movimiento es ese traspaso. El ERP las tiene en la 100 (st 5), así que el ERP NO se toca.
 *
 * Por defecto solo muestra lo que haría. Para escribir: --apply
 *   php scripts/fix_rolls_mass_transfer_20261103.php
 *   php scripts/fix_rolls_mass_transfer_20261103.php --apply
 *
 * Por bobina, en una transacción: rolls.warehouse_id 18 -> 1, su registro en traceability_warehouse_entries pasa a
 * la bodega 100 (warehouse_id ERP 5, nombre de la 100) y se deja un movimiento TRANSFER 18 -> 1 con el motivo.
 * Solo SKU TEL: las bobinas BOL (ROLL-SE) no tienen stock en el ERP y se dejan como están hasta decidir su bodega.
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

$w100 = $t->query("SELECT id, name, erp_storehouse_id FROM warehouses WHERE code = '100' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$w3000 = $t->query("SELECT id FROM warehouses WHERE code = '3000' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$w100 || !$w3000 || (int)$w100['erp_storehouse_id'] !== 5) {
    exit("No se encontraron las bodegas 100 (ERP 5) y 3000 como se esperaba; no se hace nada.\n");
}
$to = (int)$w100['id'];
$from = (int)$w3000['id'];

$rows = $t->prepare("SELECT r.id, r.roll_code, s.code sku FROM rolls r JOIN skus s ON s.id = r.sku_id
    WHERE r.warehouse_id = ? AND r.status = 'RECEIVED' AND r.current_work_order_id IS NULL AND s.code LIKE 'TEL%'
      AND NOT EXISTS (SELECT 1 FROM production_supply_scans p WHERE p.roll_id = r.id AND p.voided_at IS NULL)
      AND (SELECT m.from_warehouse_id FROM movements m WHERE m.entity_type = 'ROLL' AND m.entity_id = r.id ORDER BY m.id DESC LIMIT 1) = 5
      AND (SELECT JSON_EXTRACT(m.payload, '$.traceability_only') FROM movements m WHERE m.entity_type = 'ROLL' AND m.entity_id = r.id ORDER BY m.id DESC LIMIT 1) = true
    ORDER BY s.code, r.id");
$rows->execute([$from]);
$rolls = $rows->fetchAll(PDO::FETCH_ASSOC);

$bySku = [];
foreach ($rolls as $r) {
    $bySku[$r['sku']] = ($bySku[$r['sku']] ?? 0) + 1;
}
foreach ($bySku as $sku => $n) {
    printf("  %-10s %4d bobinas 3000 -> 100\n", $sku, $n);
}
echo '  TOTAL ' . count($rolls) . " bobinas\n";
if (!$apply) {
    echo "Simulación: no se escribió nada. Para aplicar: --apply\n";
    exit(0);
}

$payload = json_encode([
    'operator_name' => 'Corrección sistema',
    'traceability_only' => true,
    'inventory_impact' => false,
    'reason' => 'Corrección: traspaso masivo a 3000 del 3-nov-2026 sin uso; el ERP las tiene en la 100.',
], JSON_UNESCAPED_UNICODE);
$updRoll = $t->prepare('UPDATE rolls SET warehouse_id = ? WHERE id = ? AND warehouse_id = ?');
$updEntry = $t->prepare("UPDATE traceability_warehouse_entries SET warehouse_id = ?, warehouse_name = ? WHERE entity_type = 'ROLL' AND entity_id = ?");
$insMov = $t->prepare("INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload, created_at)
    VALUES ('ROLL', ?, 'TRANSFER', ?, ?, ?, NOW())");
$t->beginTransaction();
try {
    $done = 0;
    foreach ($rolls as $r) {
        $updRoll->execute([$to, (int)$r['id'], $from]);
        if ($updRoll->rowCount() !== 1) {
            continue;
        }
        $updEntry->execute([5, (string)$w100['name'], (int)$r['id']]);
        $insMov->execute([(int)$r['id'], $from, $to, $payload]);
        $done++;
    }
    $t->commit();
    echo "Aplicado: $done bobinas devueltas a la 100.\n";
} catch (Throwable $e) {
    $t->rollBack();
    echo 'Error, no se cambió nada: ' . $e->getMessage() . "\n";
    exit(1);
}
