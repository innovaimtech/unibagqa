<?php

declare(strict_types=1);

require __DIR__ . '/../src/Env.php';
require __DIR__ . '/../src/Db.php';

Env::load(__DIR__ . '/../.env');

$trz = Db::trzPdo();
$erp = Db::erpPdo();

$summary = [
    'trz' => cleanupTrzDemoData($trz),
    'erp' => cleanupErpDemoData($erp),
];

echo json_encode([
    'ok' => true,
    'message' => 'Limpieza de datos demo completada.',
    'summary' => $summary,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

/**
 * @return array<string,int>
 */
function cleanupTrzDemoData(PDO $pdo): array
{
    $summary = [
        'work_orders' => 0,
        'rolls' => 0,
        'boxes' => 0,
        'pallets' => 0,
        'chemical_weighings' => 0,
        'material_requests' => 0,
        'production_wastes' => 0,
        'shift_sessions' => 0,
        'sync_rows' => 0,
        'movements' => 0,
        'events' => 0,
    ];

    $workOrderIds = findIds(
        $pdo,
        "SELECT id FROM work_orders WHERE ot_code LIKE 'DEMO-OT-%' OR ot_code LIKE 'PRD-PLAN-%' OR ot_code LIKE 'PRD-ACT-%' OR ot_code LIKE 'PRD-END-%'"
    );
    $rollIds = findDemoRollIds($pdo, $workOrderIds);

    if ($rollIds !== []) {
        $summary['boxes'] += deleteByIds($pdo, 'boxes', 'source_roll_id', $rollIds);
        $summary['pallets'] += deleteByIds($pdo, 'pallets', 'source_roll_id', $rollIds);
        $summary['production_wastes'] += deleteByIds($pdo, 'production_wastes', 'roll_id', $rollIds);
        $summary['material_requests'] += deleteByIds($pdo, 'work_order_material_requests', 'delivered_roll_id', $rollIds);
        $summary['material_requests'] += deleteByIds($pdo, 'work_order_material_requests', 'requested_roll_id', $rollIds);
        $summary['movements'] += deleteByIdsWithExtra($pdo, 'movements', 'entity_id', $rollIds, "entity_type = 'ROLL'");
    }

    if ($workOrderIds !== []) {
        $summary['chemical_weighings'] += deleteByIds($pdo, 'chemical_weighings', 'work_order_id', $workOrderIds);
        $summary['material_requests'] += deleteByIds($pdo, 'work_order_material_requests', 'work_order_id', $workOrderIds);
        $summary['production_wastes'] += deleteByIds($pdo, 'production_wastes', 'work_order_id', $workOrderIds);
        $summary['shift_sessions'] += deleteShiftSessionsByWorkOrders($pdo, $workOrderIds);
        $summary['sync_rows'] += deleteByIds($pdo, 'erp_work_order_sync', 'work_order_id', $workOrderIds);
        $summary['events'] += deleteEventsByWorkOrders($pdo, $workOrderIds);
    }

    $summary['shift_sessions'] += deleteShiftSessionsByOperator($pdo, 'Operador Demo');

    if ($rollIds !== []) {
        $summary['events'] += deleteEventsByRolls($pdo, $rollIds);
        $summary['rolls'] += deleteByIds($pdo, 'rolls', 'id', $rollIds);
    }

    if ($workOrderIds !== []) {
        $summary['work_orders'] += deleteByIds($pdo, 'work_orders', 'id', $workOrderIds);
    }

    return $summary;
}

/**
 * @return array<string,int>
 */
function cleanupErpDemoData(PDO $pdo): array
{
    $summary = [
        'prod_headers' => 0,
        'prod_agenda' => 0,
        'prod_worker_ot' => 0,
        'prod_worker_init' => 0,
        'prod_worker_ot_events' => 0,
        'supplier_orders' => 0,
        'supplier_order_items' => 0,
        'containers' => 0,
        'container_items' => 0,
        'suppliers' => 0,
    ];

    $headerIds = findIds(
        $pdo,
        "SELECT id FROM prod_header WHERE prd_number LIKE 'PRD-PLAN-%' OR prd_number LIKE 'PRD-ACT-%' OR prd_number LIKE 'PRD-END-%'"
    );
    $agendaIds = $headerIds === [] ? [] : findIdsPrepared(
        $pdo,
        'SELECT id FROM prod_agenda WHERE ag_prdid IN (%s)',
        $headerIds
    );
    $workerOtIds = $agendaIds === [] ? [] : findIdsPrepared(
        $pdo,
        'SELECT id FROM prod_worker_ot WHERE wok_ag_id IN (%s)',
        $agendaIds
    );
    $workerInitIds = $workerOtIds === [] ? [] : findIdsPrepared(
        $pdo,
        'SELECT DISTINCT wok_init_id FROM prod_worker_ot WHERE id IN (%s) AND wok_init_id IS NOT NULL',
        $workerOtIds
    );

    if ($workerOtIds !== []) {
        $summary['prod_worker_ot_events'] += deleteByIds($pdo, 'prod_worker_ot_events', 'evt_prod_worker_otid', $workerOtIds);
        $summary['prod_worker_ot'] += deleteByIds($pdo, 'prod_worker_ot', 'id', $workerOtIds);
    }
    if ($workerInitIds !== []) {
        $summary['prod_worker_init'] += deleteByIds($pdo, 'prod_worker_init', 'id', $workerInitIds);
    }
    if ($agendaIds !== []) {
        $summary['prod_agenda'] += deleteByIds($pdo, 'prod_agenda', 'id', $agendaIds);
    }
    if ($headerIds !== []) {
        $summary['prod_headers'] += deleteByIds($pdo, 'prod_header', 'id', $headerIds);
    }

    $supplierOrderIds = findIds($pdo, "SELECT id FROM supplier_order WHERE sord_number LIKE 'OC-DEM-%'");
    if ($supplierOrderIds !== []) {
        $summary['supplier_order_items'] += deleteByIds($pdo, 'supplier_order_items', 'sord_id', $supplierOrderIds);
        $summary['supplier_orders'] += deleteByIds($pdo, 'supplier_order', 'id', $supplierOrderIds);
    }

    $containerIds = findIds($pdo, "SELECT id FROM supplier_contenedor WHERE sord_contenedor LIKE 'CONT-DEM-%' OR sord_title LIKE 'CONT-DEM-%'");
    if ($containerIds !== []) {
        $summary['container_items'] += deleteByIds($pdo, 'supplier_contenedor_items', 'sord_id', $containerIds);
        $summary['containers'] += deleteByIds($pdo, 'supplier_contenedor', 'id', $containerIds);
    }

    $supplierIds = findIds($pdo, "SELECT id FROM supplier WHERE supp_company LIKE 'Proveedor Demo %'");
    if ($supplierIds !== []) {
        $summary['suppliers'] += deleteByIds($pdo, 'supplier', 'id', $supplierIds);
    }

    return $summary;
}

/**
 * @param int[] $workOrderIds
 * @return int[]
 */
function findDemoRollIds(PDO $pdo, array $workOrderIds): array
{
    $ids = [];
    $queue = [];

    foreach (findIds($pdo, "SELECT id FROM rolls WHERE roll_code LIKE 'DEMO-RAW-%'") as $rollId) {
        $ids[$rollId] = true;
        $queue[] = $rollId;
    }

    if ($workOrderIds !== []) {
        foreach (findIdsPrepared($pdo, 'SELECT id FROM rolls WHERE current_work_order_id IN (%s) OR source_work_order_id IN (%s)', $workOrderIds, $workOrderIds) as $rollId) {
            if (!isset($ids[$rollId])) {
                $ids[$rollId] = true;
                $queue[] = $rollId;
            }
        }
    }

    while ($queue !== []) {
        $parentId = array_pop($queue);
        foreach (findIdsPrepared($pdo, 'SELECT id FROM rolls WHERE parent_roll_id IN (%s)', [$parentId]) as $childId) {
            if (!isset($ids[$childId])) {
                $ids[$childId] = true;
                $queue[] = $childId;
            }
        }
    }

    $result = array_map('intval', array_keys($ids));
    sort($result);
    return $result;
}

/**
 * @return int[]
 */
function findIds(PDO $pdo, string $sql): array
{
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    return array_map('intval', array_values(array_filter($rows, static fn ($value): bool => $value !== false && $value !== null && $value !== '')));
}

/**
 * @param int[] ...$groups
 * @return int[]
 */
function findIdsPrepared(PDO $pdo, string $sqlTemplate, array ...$groups): array
{
    $placeholderSets = [];
    $params = [];
    foreach ($groups as $group) {
        if ($group === []) {
            return [];
        }
        $placeholderSets[] = implode(', ', array_fill(0, count($group), '?'));
        foreach ($group as $value) {
            $params[] = (int)$value;
        }
    }

    $sql = vsprintf($sqlTemplate, $placeholderSets);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return array_map('intval', array_values(array_filter($rows, static fn ($value): bool => $value !== false && $value !== null && $value !== '')));
}

/**
 * @param int[] $ids
 */
function deleteByIds(PDO $pdo, string $table, string $column, array $ids): int
{
    return deleteByIdsWithExtra($pdo, $table, $column, $ids, '1=1');
}

/**
 * @param int[] $ids
 */
function deleteByIdsWithExtra(PDO $pdo, string $table, string $column, array $ids, string $extraWhere): int
{
    if ($ids === []) {
        return 0;
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $sql = sprintf('DELETE FROM %s WHERE %s IN (%s) AND %s', $table, $column, $placeholders, $extraWhere);
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_map('intval', $ids));
    return $stmt->rowCount();
}

/**
 * @param int[] $workOrderIds
 */
function deleteEventsByWorkOrders(PDO $pdo, array $workOrderIds): int
{
    if ($workOrderIds === []) {
        return 0;
    }

    $placeholders = implode(', ', array_fill(0, count($workOrderIds), '?'));
    $sql = 'DELETE FROM events
            WHERE CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")), "null") AS UNSIGNED) IN (' . $placeholders . ')
               OR CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.source_work_order_id")), "null") AS UNSIGNED) IN (' . $placeholders . ')';
    $params = array_merge(array_map('intval', $workOrderIds), array_map('intval', $workOrderIds));
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * @param int[] $rollIds
 */
function deleteEventsByRolls(PDO $pdo, array $rollIds): int
{
    if ($rollIds === []) {
        return 0;
    }

    $placeholders = implode(', ', array_fill(0, count($rollIds), '?'));
    $sql = 'DELETE FROM events
            WHERE CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.roll_id")), "null") AS UNSIGNED) IN (' . $placeholders . ')
               OR CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.output_roll_id")), "null") AS UNSIGNED) IN (' . $placeholders . ')';
    $params = array_merge(array_map('intval', $rollIds), array_map('intval', $rollIds));
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * @param int[] $workOrderIds
 */
function deleteShiftSessionsByWorkOrders(PDO $pdo, array $workOrderIds): int
{
    if ($workOrderIds === []) {
        return 0;
    }

    $placeholders = implode(', ', array_fill(0, count($workOrderIds), '?'));
    $stmt = $pdo->prepare('DELETE FROM production_shift_sessions WHERE work_order_id IN (' . $placeholders . ')');
    $stmt->execute(array_map('intval', $workOrderIds));
    return $stmt->rowCount();
}

function deleteShiftSessionsByOperator(PDO $pdo, string $operatorName): int
{
    $operatorName = trim($operatorName);
    if ($operatorName === '') {
        return 0;
    }

    $stmt = $pdo->prepare('DELETE FROM production_shift_sessions WHERE operator_name = :operator_name');
    $stmt->execute([':operator_name' => $operatorName]);
    return $stmt->rowCount();
}
