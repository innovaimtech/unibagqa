<?php

declare(strict_types=1);

require __DIR__ . '/../src/Env.php';
require __DIR__ . '/../src/Db.php';
require __DIR__ . '/../src/ReceptionService.php';

Env::load(__DIR__ . '/../.env');

$trz = Db::trzPdo();
$erp = Db::erpPdo();

applySchema($trz, __DIR__ . '/../database/schema.sql');
ensureWorkOrderStatusSupportsCutting($trz);

$service = new ReceptionService($trz, $erp);

$suffix = date('ymdHis');
$operator = 'Seeder Demo';

$warehouses = [
    100 => '100 (MP PLA)',
    200 => '200 (MP PP)',
    500 => '500 (PRODUCCION - BODEGA)',
    700 => '700 (BODEGA CANAL TRADICIONAL)',
    1000 => '1000 (BODEGA RETAIL A y B)',
];
foreach ($warehouses as $code => $name) {
    upsertWarehouse($trz, $code, $name);
}

$warehouse100Id = getWarehouseIdByCode($trz, 100);
$warehouse700Id = getWarehouseIdByCode($trz, 700);
if ($warehouse100Id === null || $warehouse700Id === null) {
    throw new RuntimeException('No se pudieron preparar las bodegas demo.');
}

$chemicals = [
    ['code' => 'B900', 'name' => 'Tinta Flexografica Azul', 'warehouse_code' => 900],
    ['code' => 'B910', 'name' => 'Tinta Serigrafia', 'warehouse_code' => 910],
];
foreach ($chemicals as $chemical) {
    upsertChemical($trz, $chemical['code'], $chemical['name'], (int)$chemical['warehouse_code']);
}

$chemicalId = getChemicalIdByCode($trz, 'B900');
if ($chemicalId === null) {
    throw new RuntimeException('No se pudo preparar la tinta demo.');
}

$rawMaterialSku = [
    'code' => 'TEL0006',
    'description' => 'PP/NEGRO/C90/100X80X1100',
];
$finalProductSku = 'BOLSA-45X60-IMPRESA';

upsertSku($trz, $rawMaterialSku['code'], $rawMaterialSku['description']);
upsertSku($trz, $finalProductSku, 'Bolsa demo 45x60 impresa');

$pendingRollId = createReceiptRoll($trz, [
    'roll_code' => 'DEMO-RAW-PEND-' . $suffix,
    'sku_code' => $rawMaterialSku['code'],
    'sku_description' => $rawMaterialSku['description'],
    'warehouse_id' => $warehouse100Id,
    'weight_kg' => 125.400,
    'grams' => 35,
    'width_mm' => 450,
    'color' => 'Natural',
    'meters' => 2200,
    'operator_name' => $operator,
]);
$activeRollId = createReceiptRoll($trz, [
    'roll_code' => 'DEMO-RAW-ACT-' . $suffix,
    'sku_code' => $rawMaterialSku['code'],
    'sku_description' => $rawMaterialSku['description'],
    'warehouse_id' => $warehouse100Id,
    'weight_kg' => 118.200,
    'grams' => 35,
    'width_mm' => 450,
    'color' => 'Natural',
    'meters' => 2100,
    'operator_name' => $operator,
]);
$cutRollSeedId = createReceiptRoll($trz, [
    'roll_code' => 'DEMO-RAW-CUT-' . $suffix,
    'sku_code' => $rawMaterialSku['code'],
    'sku_description' => $rawMaterialSku['description'],
    'warehouse_id' => $warehouse100Id,
    'weight_kg' => 121.700,
    'grams' => 35,
    'width_mm' => 450,
    'color' => 'Natural',
    'meters' => 2150,
    'operator_name' => $operator,
]);
$fullRollSeedId = createReceiptRoll($trz, [
    'roll_code' => 'DEMO-RAW-FULL-' . $suffix,
    'sku_code' => $rawMaterialSku['code'],
    'sku_description' => $rawMaterialSku['description'],
    'warehouse_id' => $warehouse100Id,
    'weight_kg' => 130.500,
    'grams' => 35,
    'width_mm' => 450,
    'color' => 'Natural',
    'meters' => 2300,
    'operator_name' => $operator,
]);
$multiRollAId = createReceiptRoll($trz, [
    'roll_code' => 'DEMO-RAW-MULTI-A-' . $suffix,
    'sku_code' => $rawMaterialSku['code'],
    'sku_description' => $rawMaterialSku['description'],
    'warehouse_id' => $warehouse100Id,
    'weight_kg' => 109.800,
    'grams' => 35,
    'width_mm' => 450,
    'color' => 'Natural',
    'meters' => 2050,
    'operator_name' => $operator,
]);
$multiRollBId = createReceiptRoll($trz, [
    'roll_code' => 'DEMO-RAW-MULTI-B-' . $suffix,
    'sku_code' => $rawMaterialSku['code'],
    'sku_description' => $rawMaterialSku['description'],
    'warehouse_id' => $warehouse100Id,
    'weight_kg' => 111.400,
    'grams' => 35,
    'width_mm' => 450,
    'color' => 'Natural',
    'meters' => 2080,
    'operator_name' => $operator,
]);

$pendingOtCode = 'DEMO-OT-PEND-' . $suffix;
$activeOtCode = 'DEMO-OT-ACT-' . $suffix;
$cutOtCode = 'DEMO-OT-CUT-' . $suffix;
$fullOtCode = 'DEMO-OT-FULL-' . $suffix;
$multiOtCode = 'DEMO-OT-MULTI-' . $suffix;

assertOk($service->createWorkOrder($pendingOtCode, $finalProductSku, 8000), 'No se pudo crear OT pendiente.');
$pendingOtId = getWorkOrderIdByCode($trz, $pendingOtCode);

assertOk($service->createWorkOrder($activeOtCode, $finalProductSku, 12000), 'No se pudo crear OT activa.');
$activeOtId = getWorkOrderIdByCode($trz, $activeOtCode);
assertInt($activeOtId, 'No se encontro la OT activa creada.');
assertOk($service->attachRollToWorkOrder($activeOtId, $activeRollId, 118.200, 0.600, $operator), 'No se pudo adjuntar bobina a OT activa.');
assertOk($service->createChemicalInput($activeOtId, $chemicalId, 8.500, $operator), 'No se pudo registrar tinta de OT activa.');
assertOk($service->startWorkOrder($activeOtId, $operator), 'No se pudo iniciar OT activa.');
assertOk($service->createProductionWaste($activeOtId, $activeRollId, 'PRODUCTION', 'Ajuste de color inicial', 1.250, $operator), 'No se pudo registrar merma de OT activa.');
assertOk($service->createChemicalWeighing([
    'work_order_id' => $activeOtId,
    'chemical_id' => $chemicalId,
    'initial_weight_kg' => 8.500,
    'return_weight_kg' => 2.100,
]), 'No se pudo registrar pesaje de tinta de OT activa.');

assertOk($service->createWorkOrder($cutOtCode, $finalProductSku, 15000), 'No se pudo crear OT en corte.');
$cutOtId = getWorkOrderIdByCode($trz, $cutOtCode);
assertInt($cutOtId, 'No se encontro la OT en corte creada.');
assertOk($service->attachRollToWorkOrder($cutOtId, $cutRollSeedId, 121.700, 0.400, $operator), 'No se pudo adjuntar bobina a OT en corte.');
assertOk($service->createChemicalInput($cutOtId, $chemicalId, 9.200, $operator), 'No se pudo registrar tinta de OT en corte.');
assertOk($service->startWorkOrder($cutOtId, $operator), 'No se pudo iniciar OT en corte.');
assertOk($service->createProductionWaste($cutOtId, $cutRollSeedId, 'PRODUCTION', 'Merma de calibracion', 1.800, $operator), 'No se pudo registrar merma de OT en corte.');
$cutFinish = $service->finishWorkOrder($cutOtId, 14.300, 2.000, 2.200, 18, 101.500, $operator);
assertOk($cutFinish, 'No se pudo cerrar impresion de OT en corte.');

assertOk($service->createWorkOrder($fullOtCode, $finalProductSku, 24000), 'No se pudo crear OT completa.');
$fullOtId = getWorkOrderIdByCode($trz, $fullOtCode);
assertInt($fullOtId, 'No se encontro la OT completa creada.');
assertOk($service->attachRollToWorkOrder($fullOtId, $fullRollSeedId, 130.500, 0.700, $operator), 'No se pudo adjuntar bobina a OT completa.');
assertOk($service->createChemicalInput($fullOtId, $chemicalId, 10.400, $operator), 'No se pudo registrar tinta de OT completa.');
assertOk($service->startWorkOrder($fullOtId, $operator), 'No se pudo iniciar OT completa.');
assertOk($service->createProductionWaste($fullOtId, $fullRollSeedId, 'PRODUCTION', 'Ajuste de maquina', 2.300, $operator), 'No se pudo registrar merma de OT completa.');
assertOk($service->createChemicalWeighing([
    'work_order_id' => $fullOtId,
    'chemical_id' => $chemicalId,
    'initial_weight_kg' => 10.400,
    'return_weight_kg' => 1.900,
]), 'No se pudo registrar pesaje de tinta de OT completa.');
$fullFinish = $service->finishWorkOrder($fullOtId, 12.600, 1.900, 3.200, 24, 104.700, $operator);
assertOk($fullFinish, 'No se pudo cerrar impresion de OT completa.');
$outputRollId = (int)($fullFinish['output_roll_id'] ?? 0);
if ($outputRollId <= 0) {
    throw new RuntimeException('No se obtuvo bobina de salida para la OT completa.');
}
assertOk($service->processCutRoll($outputRollId, 24000, 24, 12, 'STOCK', null, $warehouse700Id, $operator), 'No se pudo completar el corte de la OT completa.');

assertOk($service->createWorkOrder($multiOtCode, $finalProductSku, 18000), 'No se pudo crear OT multi salida.');
$multiOtId = getWorkOrderIdByCode($trz, $multiOtCode);
assertInt($multiOtId, 'No se encontro la OT multi salida creada.');
assertOk($service->attachRollToWorkOrder($multiOtId, $multiRollAId, 109.800, 0.500, $operator), 'No se pudo adjuntar bobina A a OT multi.');
assertOk($service->createChemicalInput($multiOtId, $chemicalId, 7.900, $operator), 'No se pudo registrar tinta de OT multi.');
assertOk($service->startWorkOrder($multiOtId, $operator), 'No se pudo iniciar OT multi.');
assertOk($service->createProductionWaste($multiOtId, $multiRollAId, 'PRODUCTION', 'Ajuste previo cambio de bobina', 1.100, $operator), 'No se pudo registrar merma de OT multi.');
$multiChange = $service->changeRollInWorkOrder($multiOtId, $multiRollBId, 8.600, 1.300, 84.700, 111.400, 0.400, $operator);
assertOk($multiChange, 'No se pudo ejecutar cambio de bobina en OT multi.');
$multiOutputRollAId = (int)($multiChange['output_roll_id'] ?? 0);
if ($multiOutputRollAId <= 0) {
    throw new RuntimeException('No se obtuvo la primera bobina de salida para la OT multi.');
}
assertOk($service->createChemicalWeighing([
    'work_order_id' => $multiOtId,
    'chemical_id' => $chemicalId,
    'initial_weight_kg' => 7.900,
    'return_weight_kg' => 1.600,
]), 'No se pudo registrar pesaje de tinta de OT multi.');
$multiFinish = $service->finishWorkOrder($multiOtId, 11.200, 1.600, 2.400, 20, 86.300, $operator);
assertOk($multiFinish, 'No se pudo cerrar impresion de OT multi.');
$multiOutputRollBId = (int)($multiFinish['output_roll_id'] ?? 0);
if ($multiOutputRollBId <= 0) {
    throw new RuntimeException('No se obtuvo la segunda bobina de salida para la OT multi.');
}
assertOk($service->processCutRoll($multiOutputRollAId, 8400, 8, 4, 'STOCK', null, $warehouse700Id, $operator), 'No se pudo cortar la primera bobina de salida de OT multi.');
assertOk($service->processCutRoll($multiOutputRollBId, 9600, 12, 6, 'STOCK', null, $warehouse700Id, $operator), 'No se pudo cortar la segunda bobina de salida de OT multi.');

if ($activeOtId !== null) {
    $service->setActiveWorkOrder($activeOtId, $operator);
}

$result = [
    'pending_ot' => $pendingOtCode,
    'active_ot' => $activeOtCode,
    'cutting_ot' => $cutOtCode,
    'full_ot' => $fullOtCode,
    'pending_roll' => getRollCodeById($trz, $pendingRollId),
    'active_roll' => getRollCodeById($trz, $activeRollId),
    'cut_output_roll' => getRollCodeById($trz, (int)($cutFinish['output_roll_id'] ?? 0)),
    'full_output_roll' => getRollCodeById($trz, $outputRollId),
    'multi_ot' => $multiOtCode,
    'multi_output_roll_a' => getRollCodeById($trz, $multiOutputRollAId),
    'multi_output_roll_b' => getRollCodeById($trz, $multiOutputRollBId),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

function applySchema(PDO $pdo, string $schemaPath): void
{
    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        throw new RuntimeException('No se pudo leer schema.sql');
    }

    foreach (preg_split('/;\s*\R/', $sql) ?: [] as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }
}

function ensureWorkOrderStatusSupportsCutting(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_TYPE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = "work_orders"
           AND COLUMN_NAME = "status"
         LIMIT 1'
    );
    $stmt->execute();
    $columnType = strtolower((string)$stmt->fetchColumn());
    if ($columnType === '') {
        return;
    }

    if (str_contains($columnType, 'enum(') && !str_contains($columnType, "'cutting'")) {
        $pdo->exec("ALTER TABLE work_orders MODIFY status ENUM('OPEN','ACTIVE','CUTTING','CLOSED') NOT NULL DEFAULT 'OPEN'");
    }
}

function upsertWarehouse(PDO $pdo, int $code, string $name): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO warehouses (code, name) VALUES (:code, :name)
         ON DUPLICATE KEY UPDATE name = VALUES(name)'
    );
    $stmt->execute([
        ':code' => $code,
        ':name' => $name,
    ]);
}

function getWarehouseIdByCode(PDO $pdo, int $code): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM warehouses WHERE code = :code LIMIT 1');
    $stmt->execute([':code' => $code]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (int)$value;
}

function upsertChemical(PDO $pdo, string $code, string $name, int $warehouseCode): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO chemicals (code, name, warehouse_code, is_active) VALUES (:code, :name, :warehouse_code, 1)
         ON DUPLICATE KEY UPDATE name = VALUES(name), warehouse_code = VALUES(warehouse_code), is_active = 1'
    );
    $stmt->execute([
        ':code' => $code,
        ':name' => $name,
        ':warehouse_code' => $warehouseCode,
    ]);
}

function getChemicalIdByCode(PDO $pdo, string $code): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM chemicals WHERE code = :code LIMIT 1');
    $stmt->execute([':code' => $code]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (int)$value;
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

function createReceiptRoll(PDO $pdo, array $data): int
{
    $skuId = upsertSku($pdo, (string)$data['sku_code'], (string)($data['sku_description'] ?? $data['sku_code']));
    $stmt = $pdo->prepare(
        'INSERT INTO rolls (roll_code, sku_id, warehouse_id, weight_kg, received_qty, microns, width_mm, color, meters, status, current_work_order_id, parent_roll_id, source_work_order_id, process_stage, reception_mode)
         VALUES (:roll_code, :sku_id, :warehouse_id, :weight_kg, 1.000, :microns, :width_mm, :color, :meters, "RECEIVED", NULL, NULL, NULL, "RAW", "WEIGHT")'
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

function getWorkOrderIdByCode(PDO $pdo, string $code): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM work_orders WHERE ot_code = :code LIMIT 1');
    $stmt->execute([':code' => $code]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (int)$value;
}

function getRollCodeById(PDO $pdo, int $id): ?string
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT roll_code FROM rolls WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (string)$value;
}

function assertOk(array $result, string $message): void
{
    if (($result['ok'] ?? false) === true) {
        return;
    }

    $errors = isset($result['errors']) && is_array($result['errors'])
        ? implode(' | ', array_map(static fn($value): string => (string)$value, array_values($result['errors'])))
        : 'sin detalle';

    throw new RuntimeException($message . ' ' . $errors);
}

function assertInt(?int $value, string $message): void
{
    if ($value !== null && $value > 0) {
        return;
    }

    throw new RuntimeException($message);
}
