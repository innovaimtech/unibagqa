<?php

declare(strict_types=1);

require __DIR__ . '/../src/Env.php';
require __DIR__ . '/../src/Db.php';
require __DIR__ . '/../src/ReceptionService.php';

Env::load(__DIR__ . '/../.env');

$trz = Db::trzPdo();
$erp = Db::erpPdo();
$service = new ReceptionService($trz, $erp);

$operator = 'Seeder Materiales Demo';
$notePrefix = '[Seeder materiales demo]';
$suffix = date('ymdHis');

$requestedWorkOrderId = isset($argv[1]) ? (int)$argv[1] : 0;
$targetWorkOrder = $requestedWorkOrderId > 0
    ? findWorkOrderById($trz, $requestedWorkOrderId)
    : findTargetWorkOrder($trz);
if ($targetWorkOrder === null) {
    throw new RuntimeException('No se encontró una OT activa para poblar Utilizar materiales. Ejecuta primero scripts/seed_demo_flow.php.');
}

$workOrderId = (int)$targetWorkOrder['id'];
$workOrderCode = (string)$targetWorkOrder['ot_code'];
$currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
if ($currentRoll === null) {
    $currentRoll = findCurrentRollByWorkOrder($trz, $service, $workOrderId);
}
if ($currentRoll === null) {
    throw new RuntimeException('La OT objetivo no tiene una bobina actual para tomar como referencia.');
}

$warehouseId = resolveSeedWarehouseId($trz, [100, 200, 3000], (int)($currentRoll['warehouse_id'] ?? 0));
if ($warehouseId <= 0) {
    throw new RuntimeException('No se encontró una bodega válida para sembrar bobinas de prueba.');
}

cleanupPreviousSeed($trz, $workOrderId, $notePrefix);

$rollSeedBase = [
    'sku_code' => (string)($currentRoll['sku_code'] ?? 'TEL0006'),
    'sku_description' => (string)($currentRoll['sku_description'] ?? 'Tela demo'),
    'warehouse_id' => $warehouseId,
    'weight_kg' => (float)($currentRoll['weight_kg'] ?? 118.2),
    'grams' => (int)($currentRoll['grams'] ?? 35),
    'width_mm' => (int)($currentRoll['width_mm'] ?? 450),
    'color' => trim((string)($currentRoll['color'] ?? 'Natural')) !== '' ? (string)$currentRoll['color'] : 'Natural',
    'meters' => (float)($currentRoll['meters'] ?? 2100),
    'operator_name' => $operator,
];

$createdRollIds = [];
$createdRollCodes = [];
foreach (['A', 'B', 'C', 'D'] as $index => $slot) {
    $rollData = $rollSeedBase;
    $rollData['roll_code'] = 'DEMO-MATUSE-' . $workOrderId . '-' . $slot . '-' . $suffix;
    $rollData['weight_kg'] = $rollSeedBase['weight_kg'] + ($index * 1.25);
    $rollId = createReceiptRoll($trz, $rollData);
    $createdRollIds[] = $rollId;
    $createdRollCodes[] = $rollData['roll_code'];
}

$referenceRoll = $service->getRoll($createdRollIds[0]);
if ($referenceRoll === null) {
    throw new RuntimeException('No se pudo recuperar la bobina de referencia recién creada.');
}

$groupKey = buildMaterialGroupKey($referenceRoll);
$requestIds = [];

$partialRequest = $service->createMaterialRequest(
    $workOrderId,
    'ROLL',
    $groupKey,
    null,
    '',
    3.0,
    'Unid.',
    $notePrefix . ' Solicitud parcial para probar ingresos secuenciales.',
    $operator
);
assertOk($partialRequest, 'No se pudo crear la solicitud parcial.');
$partialRequestId = (int)($partialRequest['request_id'] ?? 0);
assertInt($partialRequestId, 'No se obtuvo el ID de la solicitud parcial.');
$requestIds[] = $partialRequestId;
assertOk($service->acceptMaterialRequest($partialRequestId, $operator), 'No se pudo aceptar la solicitud parcial.');
assertOk($service->deliverMaterialRequest($partialRequestId, $createdRollIds[0], $operator), 'No se pudo registrar la primera entrada de la solicitud parcial.');

$acceptedRequest = $service->createMaterialRequest(
    $workOrderId,
    'ROLL',
    $groupKey,
    null,
    '',
    1.0,
    'Unid.',
    $notePrefix . ' Solicitud aceptada pendiente de ingreso.',
    $operator
);
assertOk($acceptedRequest, 'No se pudo crear la solicitud aceptada.');
$acceptedRequestId = (int)($acceptedRequest['request_id'] ?? 0);
assertInt($acceptedRequestId, 'No se obtuvo el ID de la solicitud aceptada.');
$requestIds[] = $acceptedRequestId;
assertOk($service->acceptMaterialRequest($acceptedRequestId, $operator), 'No se pudo aceptar la solicitud pendiente.');

$deliveredRequest = $service->createMaterialRequest(
    $workOrderId,
    'ROLL',
    $groupKey,
    null,
    '',
    1.0,
    'Unid.',
    $notePrefix . ' Solicitud completada para validar el estado entregado.',
    $operator
);
assertOk($deliveredRequest, 'No se pudo crear la solicitud completada.');
$deliveredRequestId = (int)($deliveredRequest['request_id'] ?? 0);
assertInt($deliveredRequestId, 'No se obtuvo el ID de la solicitud completada.');
$requestIds[] = $deliveredRequestId;
assertOk($service->acceptMaterialRequest($deliveredRequestId, $operator), 'No se pudo aceptar la solicitud completada.');
assertOk($service->deliverMaterialRequest($deliveredRequestId, $createdRollIds[1], $operator), 'No se pudo registrar la entrada completa.');

$pendingRequest = $service->createMaterialRequest(
    $workOrderId,
    'ROLL',
    $groupKey,
    null,
    '',
    1.0,
    'Unid.',
    $notePrefix . ' Solicitud pendiente para mostrar espera de bodega.',
    $operator
);
assertOk($pendingRequest, 'No se pudo crear la solicitud pendiente.');
$pendingRequestId = (int)($pendingRequest['request_id'] ?? 0);
assertInt($pendingRequestId, 'No se obtuvo el ID de la solicitud pendiente.');
$requestIds[] = $pendingRequestId;

$summary = fetchRequestSummary($trz, $requestIds);

echo json_encode([
    'ok' => true,
    'work_order_id' => $workOrderId,
    'work_order_code' => $workOrderCode,
    'screen_path' => '/work-orders/' . $workOrderId . '/materials',
    'seeded_roll_codes' => $createdRollCodes,
    'seeded_request_ids' => $requestIds,
    'request_summary' => $summary,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

function findTargetWorkOrder(PDO $pdo): ?array
{
    $stmt = $pdo->query(
        'SELECT wo.id, wo.ot_code
         FROM work_orders wo
         LEFT JOIN production_shift_sessions pss
           ON pss.work_order_id = wo.id
          AND pss.status = "ACTIVE"
         LEFT JOIN production_machines pm
           ON pm.id = pss.machine_id
         WHERE wo.status IN ("OPEN", "ACTIVE", "CUTTING")
           AND EXISTS (
               SELECT 1
               FROM rolls r
               WHERE r.current_work_order_id = wo.id
           )
         ORDER BY
           CASE WHEN pm.code = "FLEXO-02" THEN 0 ELSE 1 END,
           CASE WHEN pss.id IS NOT NULL THEN 0 ELSE 1 END,
           wo.id DESC
         LIMIT 1'
    );
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function findWorkOrderById(PDO $pdo, int $workOrderId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, ot_code
         FROM work_orders
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $workOrderId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function findCurrentRollByWorkOrder(PDO $pdo, ReceptionService $service, int $workOrderId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id
         FROM rolls
         WHERE current_work_order_id = :work_order_id
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([':work_order_id' => $workOrderId]);
    $rollId = (int)$stmt->fetchColumn();
    return $rollId > 0 ? $service->getRoll($rollId) : null;
}

function resolveSeedWarehouseId(PDO $pdo, array $preferredCodes, int $fallbackId): int
{
    foreach ($preferredCodes as $code) {
        $stmt = $pdo->prepare('SELECT id FROM warehouses WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $warehouseId = (int)$stmt->fetchColumn();
        if ($warehouseId > 0) {
            return $warehouseId;
        }
    }
    return $fallbackId > 0 ? $fallbackId : 0;
}

function cleanupPreviousSeed(PDO $pdo, int $workOrderId, string $notePrefix): void
{
    $requestStmt = $pdo->prepare(
        'SELECT id
         FROM work_order_material_requests
         WHERE work_order_id = :work_order_id
           AND request_notes LIKE :note_prefix'
    );
    $requestStmt->execute([
        ':work_order_id' => $workOrderId,
        ':note_prefix' => $notePrefix . '%',
    ]);
    $requestIds = array_map('intval', $requestStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

    if ($requestIds !== []) {
        $in = implode(',', array_fill(0, count($requestIds), '?'));
        $deleteRequests = $pdo->prepare('DELETE FROM work_order_material_requests WHERE id IN (' . $in . ')');
        $deleteRequests->execute($requestIds);
    }

    $rollStmt = $pdo->prepare('SELECT id FROM rolls WHERE roll_code LIKE :prefix');
    $rollStmt->execute([':prefix' => 'DEMO-MATUSE-' . $workOrderId . '-%']);
    $rollIds = array_map('intval', $rollStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

    if ($rollIds !== []) {
        $in = implode(',', array_fill(0, count($rollIds), '?'));
        $deleteMoves = $pdo->prepare('DELETE FROM movements WHERE entity_type = "ROLL" AND entity_id IN (' . $in . ')');
        $deleteMoves->execute($rollIds);
        $deleteRolls = $pdo->prepare('DELETE FROM rolls WHERE id IN (' . $in . ')');
        $deleteRolls->execute($rollIds);
    }
}

function createReceiptRoll(PDO $pdo, array $data): int
{
    $skuId = upsertSku($pdo, (string)$data['sku_code'], (string)($data['sku_description'] ?? $data['sku_code']));
    $stmt = $pdo->prepare(
        'INSERT INTO rolls (
            roll_code, sku_id, warehouse_id, weight_kg, received_qty, microns, width_mm, color, meters,
            status, current_work_order_id, parent_roll_id, source_work_order_id, process_stage, reception_mode
         ) VALUES (
            :roll_code, :sku_id, :warehouse_id, :weight_kg, 1.000, :microns, :width_mm, :color, :meters,
            "RECEIVED", NULL, NULL, NULL, "RAW", "WEIGHT"
         )'
    );
    $stmt->execute([
        ':roll_code' => (string)$data['roll_code'],
        ':sku_id' => $skuId,
        ':warehouse_id' => (int)$data['warehouse_id'],
        ':weight_kg' => number_format((float)$data['weight_kg'], 3, '.', ''),
        ':microns' => (int)$data['grams'],
        ':width_mm' => (int)$data['width_mm'],
        ':color' => (string)$data['color'],
        ':meters' => number_format((float)$data['meters'], 2, '.', ''),
    ]);
    $rollId = (int)$pdo->lastInsertId();

    $move = $pdo->prepare(
        'INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload)
         VALUES ("ROLL", :entity_id, "RECEIPT", NULL, :warehouse_id, :payload)'
    );
    $move->execute([
        ':entity_id' => $rollId,
        ':warehouse_id' => (int)$data['warehouse_id'],
        ':payload' => json_encode([
            'operator_name' => (string)$data['operator_name'],
            'weight_kg' => round((float)$data['weight_kg'], 3),
            'reception_mode' => 'WEIGHT',
        ], JSON_UNESCAPED_UNICODE),
    ]);

    $event = $pdo->prepare('INSERT INTO events (type, payload) VALUES (:type, :payload)');
    $event->execute([
        ':type' => 'ROLL_RECEIVED',
        ':payload' => json_encode([
            'roll_id' => $rollId,
            'roll_code' => (string)$data['roll_code'],
            'warehouse_id' => (int)$data['warehouse_id'],
            'operator_name' => (string)$data['operator_name'],
        ], JSON_UNESCAPED_UNICODE),
    ]);

    return $rollId;
}

function upsertSku(PDO $pdo, string $code, string $description): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO skus (code, description, is_active) VALUES (:code, :description, 1)
         ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = 1'
    );
    $stmt->execute([
        ':code' => $code,
        ':description' => $description,
    ]);

    $stmt = $pdo->prepare('SELECT id FROM skus WHERE code = :code LIMIT 1');
    $stmt->execute([':code' => $code]);
    return (int)$stmt->fetchColumn();
}

function buildMaterialGroupKey(array $roll): string
{
    return implode('|', [
        (string)($roll['sku_code'] ?? ''),
        (string)($roll['sku_description'] ?? ''),
        (string)($roll['grams'] ?? ''),
        (string)($roll['width_mm'] ?? ''),
        trim((string)($roll['color'] ?? '')),
        (string)($roll['meters'] ?? ''),
        (string)($roll['process_stage'] ?? ''),
    ]);
}

function fetchRequestSummary(PDO $pdo, array $requestIds): array
{
    if ($requestIds === []) {
        return [];
    }
    $in = implode(',', array_fill(0, count($requestIds), '?'));
    $stmt = $pdo->prepare(
        'SELECT id, requested_item, requested_qty, delivered_qty, status
         FROM work_order_material_requests
         WHERE id IN (' . $in . ')
         ORDER BY id ASC'
    );
    $stmt->execute($requestIds);
    return $stmt->fetchAll();
}

function assertOk(array $result, string $message): void
{
    if (($result['ok'] ?? false) !== true) {
        $details = implode(' ', array_map('strval', array_values($result['errors'] ?? [])));
        throw new RuntimeException($message . ($details !== '' ? ' ' . $details : ''));
    }
}

function assertInt(int $value, string $message): void
{
    if ($value <= 0) {
        throw new RuntimeException($message);
    }
}
