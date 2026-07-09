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

foreach ([
    ['username' => 'operador.demo', 'display_name' => 'Operador Demo'],
    ['username' => 'operador.flexo2', 'display_name' => 'Operador Flexo II'],
    ['username' => 'ayudante.flexo', 'display_name' => 'Ayudante Flexo'],
    ['username' => 'preparador.flexo', 'display_name' => 'Preparador Flexo'],
] as $authUser) {
    upsertAuthUser($trz, (string)$authUser['username'], (string)$authUser['display_name']);
}

$warehouses = [
    100 => '100 (MP PLA)',
    110 => '110 (MP PLA DESCALIBRADO)',
    120 => '120 (MP PLA EMPALMADO)',
    150 => '150 (BODEGA RESERVA MATERIALES)',
    200 => '200 (MP PP)',
    300 => '300 (PROD. TERMINADO FABRICACION INTERNA)',
    400 => '400 (PROD TERMINADOS REVENTA)',
    500 => '500 (PRODUCCION - BODEGA)',
    510 => '510 (RESIDUOS)',
    600 => '600 (REPUESTOS)',
    700 => '700 (BODEGA CANAL TRADICIONAL)',
    800 => '800 (EPP Y ROPAS)',
    900 => '900 (TINTAS FLEXOGRAFIA)',
    910 => '910 (TINTAS SERIGRAFIA)',
    920 => '920 (TINTAS PULPO SERIGRAFIA)',
    1000 => '1000 (BODEGA RETAIL A y B)',
    2000 => '2000 TALLERES EXTERNOS',
    3000 => '3000 INSUMOS EN PRODUCCION',
    3100 => '3100 INSUMOS-LIMPIEZA',
    3200 => '3200 INSUMOS DISPONIBLES (MP)',
    4000 => '4000 (BOBINAS USADAS)',
    5000 => '5000 (PRODUCTOS INMOVILIZADOS)',
    6000 => '6000 Facturacion de servicios No productivos',
];
foreach ($warehouses as $code => $name) {
    upsertWarehouse($trz, $code, $name);
}

$warehouseIds = resolveWarehouseIds($trz, array_keys($warehouses));
$warehouse100Id = $warehouseIds[100] ?? null;
$warehouse200Id = $warehouseIds[200] ?? null;
$warehouse700Id = $warehouseIds[700] ?? null;
$warehouse1000Id = $warehouseIds[1000] ?? null;
if ($warehouse100Id === null || $warehouse200Id === null || $warehouse700Id === null || $warehouse1000Id === null) {
    throw new RuntimeException('No se pudieron preparar las bodegas demo requeridas.');
}

$chemicals = [
    ['code' => 'B900', 'name' => 'Tinta Flexografica Azul', 'warehouse_code' => 900],
    ['code' => 'B910', 'name' => 'Tinta Serigrafia Negra', 'warehouse_code' => 910],
    ['code' => 'B920', 'name' => 'Pulpo Serigrafia Blanco', 'warehouse_code' => 920],
];
foreach ($chemicals as $chemical) {
    upsertChemical($trz, $chemical['code'], $chemical['name'], (int)$chemical['warehouse_code']);
}

$chemicalIds = [];
foreach (['B900', 'B910', 'B920'] as $chemicalCode) {
    $chemicalId = getChemicalIdByCode($trz, $chemicalCode);
    if ($chemicalId === null) {
        throw new RuntimeException('No se pudo preparar el insumo quimico demo ' . $chemicalCode . '.');
    }
    $chemicalIds[$chemicalCode] = $chemicalId;
}

$rawMaterialSkus = [
    [
        'code' => 'TEL0006',
        'description' => 'PP/NEGRO/C90/100X80X1100',
    ],
    [
        'code' => 'TEL0044',
        'description' => 'PP/BLANCO/W80/100X80X1100',
    ],
    [
        'code' => 'PLA0012',
        'description' => 'PLA/NATURAL/C40/090X70X0900',
    ],
];
$finalProductSkus = [
    'BOLSA-45X60-IMPRESA' => 'Bolsa demo 45x60 impresa',
    'BOLSA-50X70-NEGRA' => 'Bolsa demo 50x70 negra',
    'BOLSA-60X90-RETAIL' => 'Bolsa demo 60x90 retail',
    'BOLSA-35X45-NATURAL' => 'Bolsa demo 35x45 natural',
];

foreach ($rawMaterialSkus as $rawMaterialSku) {
    upsertSku($trz, $rawMaterialSku['code'], $rawMaterialSku['description']);
}
foreach ($finalProductSkus as $skuCode => $description) {
    upsertSku($trz, $skuCode, $description);
}

$warehouseStockSummary = seedWarehouseInventoryStock($trz, $warehouseIds, $warehouses, $suffix, $operator);

$pendingRollId = createReceiptRoll($trz, [
    'roll_code' => 'DEMO-RAW-PEND-' . $suffix,
    'sku_code' => $rawMaterialSkus[0]['code'],
    'sku_description' => $rawMaterialSkus[0]['description'],
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
    'sku_code' => $rawMaterialSkus[0]['code'],
    'sku_description' => $rawMaterialSkus[0]['description'],
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
    'sku_code' => $rawMaterialSkus[0]['code'],
    'sku_description' => $rawMaterialSkus[0]['description'],
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
    'sku_code' => $rawMaterialSkus[0]['code'],
    'sku_description' => $rawMaterialSkus[0]['description'],
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
    'sku_code' => $rawMaterialSkus[0]['code'],
    'sku_description' => $rawMaterialSkus[0]['description'],
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
    'sku_code' => $rawMaterialSkus[0]['code'],
    'sku_description' => $rawMaterialSkus[0]['description'],
    'warehouse_id' => $warehouse100Id,
    'weight_kg' => 111.400,
    'grams' => 35,
    'width_mm' => 450,
    'color' => 'Natural',
    'meters' => 2080,
    'operator_name' => $operator,
]);
$ppActiveRollId = createReceiptRoll($trz, [
    'roll_code' => 'DEMO-RAW-PP-ACT-' . $suffix,
    'sku_code' => $rawMaterialSkus[1]['code'],
    'sku_description' => $rawMaterialSkus[1]['description'],
    'warehouse_id' => $warehouse200Id,
    'weight_kg' => 116.900,
    'grams' => 40,
    'width_mm' => 500,
    'color' => 'Blanco',
    'meters' => 2140,
    'operator_name' => $operator,
]);
$ppFinishedRollId = createReceiptRoll($trz, [
    'roll_code' => 'DEMO-RAW-PP-FIN-' . $suffix,
    'sku_code' => $rawMaterialSkus[1]['code'],
    'sku_description' => $rawMaterialSkus[1]['description'],
    'warehouse_id' => $warehouse200Id,
    'weight_kg' => 122.300,
    'grams' => 40,
    'width_mm' => 520,
    'color' => 'Negro',
    'meters' => 2185,
    'operator_name' => $operator,
]);
$plaReserveRollId = createReceiptRoll($trz, [
    'roll_code' => 'DEMO-RAW-PLA-RES-' . $suffix,
    'sku_code' => $rawMaterialSkus[2]['code'],
    'sku_description' => $rawMaterialSkus[2]['description'],
    'warehouse_id' => $warehouse100Id,
    'weight_kg' => 107.500,
    'grams' => 28,
    'width_mm' => 380,
    'color' => 'Natural',
    'meters' => 1980,
    'operator_name' => $operator,
]);

$pendingOtCode = 'DEMO-OT-PEND-' . $suffix;
$activeOtCode = 'DEMO-OT-ACT-' . $suffix;
$cutOtCode = 'DEMO-OT-CUT-' . $suffix;
$fullOtCode = 'DEMO-OT-FULL-' . $suffix;
$multiOtCode = 'DEMO-OT-MULTI-' . $suffix;
$ppActiveOtCode = 'DEMO-OT-PP-ACT-' . $suffix;
$retailOtCode = 'DEMO-OT-RET-' . $suffix;
$reserveOtCode = 'DEMO-OT-PLA-PEND-' . $suffix;

assertOk($service->createWorkOrder($pendingOtCode, 'BOLSA-45X60-IMPRESA', 8000), 'No se pudo crear OT pendiente.');
$pendingOtId = getWorkOrderIdByCode($trz, $pendingOtCode);

assertOk($service->createWorkOrder($activeOtCode, 'BOLSA-45X60-IMPRESA', 12000), 'No se pudo crear OT activa.');
$activeOtId = getWorkOrderIdByCode($trz, $activeOtCode);
assertInt($activeOtId, 'No se encontro la OT activa creada.');
assertOk($service->attachRollToWorkOrder($activeOtId, $activeRollId, 118.200, 0.600, $operator), 'No se pudo adjuntar bobina a OT activa.');
assertOk($service->createChemicalInput($activeOtId, $chemicalIds['B900'], 8.500, $operator), 'No se pudo registrar tinta de OT activa.');
assertOk($service->startWorkOrder($activeOtId, $operator), 'No se pudo iniciar OT activa.');
assertOk($service->createProductionWaste($activeOtId, $activeRollId, 'PRODUCTION', 'Ajuste de color inicial', 1.250, $operator), 'No se pudo registrar merma de OT activa.');
assertOk($service->createChemicalWeighing([
    'work_order_id' => $activeOtId,
    'chemical_id' => $chemicalIds['B900'],
    'initial_weight_kg' => 8.500,
    'return_weight_kg' => 2.100,
]), 'No se pudo registrar pesaje de tinta de OT activa.');

assertOk($service->createWorkOrder($cutOtCode, 'BOLSA-45X60-IMPRESA', 15000), 'No se pudo crear OT en corte.');
$cutOtId = getWorkOrderIdByCode($trz, $cutOtCode);
assertInt($cutOtId, 'No se encontro la OT en corte creada.');
assertOk($service->attachRollToWorkOrder($cutOtId, $cutRollSeedId, 121.700, 0.400, $operator), 'No se pudo adjuntar bobina a OT en corte.');
assertOk($service->createChemicalInput($cutOtId, $chemicalIds['B900'], 9.200, $operator), 'No se pudo registrar tinta de OT en corte.');
assertOk($service->startWorkOrder($cutOtId, $operator), 'No se pudo iniciar OT en corte.');
assertOk($service->createProductionWaste($cutOtId, $cutRollSeedId, 'PRODUCTION', 'Merma de calibracion', 1.800, $operator), 'No se pudo registrar merma de OT en corte.');
$cutFinish = $service->finishWorkOrder($cutOtId, 14.300, 2.000, 2.200, 18, 101.500, $operator);
assertOk($cutFinish, 'No se pudo cerrar impresion de OT en corte.');

assertOk($service->createWorkOrder($fullOtCode, 'BOLSA-45X60-IMPRESA', 24000), 'No se pudo crear OT completa.');
$fullOtId = getWorkOrderIdByCode($trz, $fullOtCode);
assertInt($fullOtId, 'No se encontro la OT completa creada.');
assertOk($service->attachRollToWorkOrder($fullOtId, $fullRollSeedId, 130.500, 0.700, $operator), 'No se pudo adjuntar bobina a OT completa.');
assertOk($service->createChemicalInput($fullOtId, $chemicalIds['B900'], 10.400, $operator), 'No se pudo registrar tinta de OT completa.');
assertOk($service->startWorkOrder($fullOtId, $operator), 'No se pudo iniciar OT completa.');
assertOk($service->createProductionWaste($fullOtId, $fullRollSeedId, 'PRODUCTION', 'Ajuste de maquina', 2.300, $operator), 'No se pudo registrar merma de OT completa.');
assertOk($service->createChemicalWeighing([
    'work_order_id' => $fullOtId,
    'chemical_id' => $chemicalIds['B900'],
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

assertOk($service->createWorkOrder($multiOtCode, 'BOLSA-45X60-IMPRESA', 18000), 'No se pudo crear OT multi salida.');
$multiOtId = getWorkOrderIdByCode($trz, $multiOtCode);
assertInt($multiOtId, 'No se encontro la OT multi salida creada.');
assertOk($service->attachRollToWorkOrder($multiOtId, $multiRollAId, 109.800, 0.500, $operator), 'No se pudo adjuntar bobina A a OT multi.');
assertOk($service->createChemicalInput($multiOtId, $chemicalIds['B900'], 7.900, $operator), 'No se pudo registrar tinta de OT multi.');
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
    'chemical_id' => $chemicalIds['B900'],
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

assertOk($service->createWorkOrder($ppActiveOtCode, 'BOLSA-50X70-NEGRA', 14000), 'No se pudo crear OT PP activa.');
$ppActiveOtId = getWorkOrderIdByCode($trz, $ppActiveOtCode);
assertInt($ppActiveOtId, 'No se encontro la OT PP activa creada.');
assertOk($service->attachRollToWorkOrder($ppActiveOtId, $ppActiveRollId, 116.900, 0.550, $operator), 'No se pudo adjuntar bobina a OT PP activa.');
assertOk($service->createChemicalInput($ppActiveOtId, $chemicalIds['B910'], 6.700, $operator), 'No se pudo registrar tinta de OT PP activa.');
assertOk($service->startWorkOrder($ppActiveOtId, $operator), 'No se pudo iniciar OT PP activa.');
assertOk($service->createProductionWaste($ppActiveOtId, $ppActiveRollId, 'PRODUCTION', 'Ajuste de presion', 1.450, $operator), 'No se pudo registrar merma de OT PP activa.');

assertOk($service->createWorkOrder($retailOtCode, 'BOLSA-60X90-RETAIL', 16000), 'No se pudo crear OT retail.');
$retailOtId = getWorkOrderIdByCode($trz, $retailOtCode);
assertInt($retailOtId, 'No se encontro la OT retail creada.');
assertOk($service->attachRollToWorkOrder($retailOtId, $ppFinishedRollId, 122.300, 0.650, $operator), 'No se pudo adjuntar bobina a OT retail.');
assertOk($service->createChemicalInput($retailOtId, $chemicalIds['B920'], 7.300, $operator), 'No se pudo registrar pulpo de OT retail.');
assertOk($service->startWorkOrder($retailOtId, $operator), 'No se pudo iniciar OT retail.');
assertOk($service->createChemicalWeighing([
    'work_order_id' => $retailOtId,
    'chemical_id' => $chemicalIds['B920'],
    'initial_weight_kg' => 7.300,
    'return_weight_kg' => 1.200,
]), 'No se pudo registrar pesaje de OT retail.');
$retailFinish = $service->finishWorkOrder($retailOtId, 9.800, 1.200, 2.100, 16, 96.700, $operator);
assertOk($retailFinish, 'No se pudo cerrar OT retail.');
$retailOutputRollId = (int)($retailFinish['output_roll_id'] ?? 0);
if ($retailOutputRollId <= 0) {
    throw new RuntimeException('No se obtuvo bobina de salida para la OT retail.');
}
assertOk($service->processCutRoll($retailOutputRollId, 16000, 16, 8, 'STOCK', null, $warehouse1000Id, $operator), 'No se pudo completar el corte de la OT retail.');

assertOk($service->createWorkOrder($reserveOtCode, 'BOLSA-35X45-NATURAL', 6000), 'No se pudo crear OT de reserva.');
$reserveOtId = getWorkOrderIdByCode($trz, $reserveOtCode);
assertInt($reserveOtId, 'No se encontro la OT de reserva creada.');
assertOk($service->attachRollToWorkOrder($reserveOtId, $plaReserveRollId, 107.500, 0.350, $operator), 'No se pudo adjuntar bobina a OT de reserva.');
assertOk($service->createChemicalInput($reserveOtId, $chemicalIds['B900'], 5.400, $operator), 'No se pudo registrar tinta de OT de reserva.');

if ($activeOtId !== null) {
    $service->setActiveWorkOrder($activeOtId, $operator);
}

$flexoIiMachineId = getProductionMachineIdByCode($trz, 'FLEXO-02');
assertInt($flexoIiMachineId, 'No se encontró la máquina FLEXO II.');
$shiftStart = $service->startShiftSession(
    $flexoIiMachineId,
    'Operador Flexo II',
    'Ayudante Flexo',
    'Turno mañana',
    'PRINTING',
    'Preparación inicial Flexo II con carga de anilox y revisión de tintas.'
);
assertOk($shiftStart, 'No se pudo iniciar el turno demo de FLEXO II.');
$service->assignActiveShiftSessionToWorkOrder($activeOtId, 'Operador Flexo II');

$result = [
    'warehouse_stock_rows' => $warehouseStockSummary,
    'warehouse_count' => count($warehouses),
    'warehouse_minimum_stock_rows' => min(array_values($warehouseStockSummary)),
    'pending_ot' => $pendingOtCode,
    'active_ot' => $activeOtCode,
    'cutting_ot' => $cutOtCode,
    'full_ot' => $fullOtCode,
    'multi_ot' => $multiOtCode,
    'pp_active_ot' => $ppActiveOtCode,
    'retail_ot' => $retailOtCode,
    'reserve_ot' => $reserveOtCode,
    'pending_roll' => getRollCodeById($trz, $pendingRollId),
    'active_roll' => getRollCodeById($trz, $activeRollId),
    'cut_output_roll' => getRollCodeById($trz, (int)($cutFinish['output_roll_id'] ?? 0)),
    'full_output_roll' => getRollCodeById($trz, $outputRollId),
    'multi_output_roll_a' => getRollCodeById($trz, $multiOutputRollAId),
    'multi_output_roll_b' => getRollCodeById($trz, $multiOutputRollBId),
    'retail_output_roll' => getRollCodeById($trz, $retailOutputRollId),
    'flexo_ii_shift' => [
        'machine_code' => 'FLEXO-02',
        'machine_name' => 'FLEXO II.',
        'operator' => 'Operador Flexo II',
        'helper' => 'Ayudante Flexo',
        'linked_work_order' => $activeOtCode,
    ],
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

function upsertAuthUser(PDO $pdo, string $username, string $displayName): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO auth_users (
            username, password_hash, display_name, is_active,
            can_erp, can_production, can_operator, can_warehouse, can_marketing
         ) VALUES (
            :username, :password_hash, :display_name, 1, 1, 1, 1, 1, 0
         )
         ON DUPLICATE KEY UPDATE
            display_name = VALUES(display_name),
            is_active = 1,
            can_erp = VALUES(can_erp),
            can_production = VALUES(can_production),
            can_operator = VALUES(can_operator),
            can_warehouse = VALUES(can_warehouse)'
    );
    $stmt->execute([
        ':username' => trim($username),
        ':password_hash' => '$2y$10$NEnibNryVcuH8MX2zxUaW.Inrqb0go6.jf3VMFXHERLyLuY0jOAny',
        ':display_name' => trim($displayName),
    ]);
}

/**
 * @param int[] $warehouseCodes
 * @return array<int,int>
 */
function resolveWarehouseIds(PDO $pdo, array $warehouseCodes): array
{
    $warehouseIds = [];
    foreach ($warehouseCodes as $warehouseCode) {
        $warehouseId = getWarehouseIdByCode($pdo, (int)$warehouseCode);
        if ($warehouseId === null) {
            throw new RuntimeException('No se pudo resolver la bodega ' . $warehouseCode . '.');
        }
        $warehouseIds[(int)$warehouseCode] = $warehouseId;
    }

    return $warehouseIds;
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

function getProductionMachineIdByCode(PDO $pdo, string $code): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM production_machines WHERE code = :code LIMIT 1');
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

/**
 * @param array<int,int> $warehouseIds
 * @param array<int,string> $warehouses
 * @return array<string,int>
 */
function seedWarehouseInventoryStock(PDO $pdo, array $warehouseIds, array $warehouses, string $suffix, string $operator): array
{
    $summary = [];
    foreach ($warehouses as $warehouseCode => $warehouseName) {
        $warehouseId = $warehouseIds[$warehouseCode] ?? null;
        if ($warehouseId === null) {
            throw new RuntimeException('No se encontro la bodega para poblar stock demo: ' . $warehouseCode);
        }

        $catalog = buildWarehouseProductCatalog($warehouseCode);
        foreach ($catalog as $index => $product) {
            createReceiptRoll($pdo, [
                'roll_code' => sprintf('DEMO-RAW-W%04d-%02d-%s', $warehouseCode, $index + 1, $suffix),
                'sku_code' => (string)$product['sku_code'],
                'sku_description' => (string)$product['sku_description'],
                'warehouse_id' => $warehouseId,
                'weight_kg' => (float)$product['weight_kg'],
                'grams' => (int)$product['grams'],
                'width_mm' => (int)$product['width_mm'],
                'color' => (string)$product['color'],
                'meters' => (float)$product['meters'],
                'operator_name' => $operator,
            ]);
        }

        $summary[(string)$warehouseCode] = count($catalog);
    }

    return $summary;
}

/**
 * @return array<int,array{sku_code:string,sku_description:string,weight_kg:float,grams:int,width_mm:int,color:string,meters:float}>
 */
function buildWarehouseProductCatalog(int $warehouseCode): array
{
    switch (true) {
        case in_array($warehouseCode, [100, 110, 120, 150], true):
            return buildCatalogEntries(
                $warehouseCode,
                'PLA',
                [
                    'PLA NATURAL C35 80X70X900',
                    'PLA NATURAL C40 90X70X950',
                    'PLA BLANCO C45 100X80X1000',
                    'PLA NEGRO C50 105X80X1000',
                    'PLA AZUL C45 95X75X980',
                    'PLA VERDE C40 90X72X930',
                    'PLA ROJO C55 110X85X1080',
                    'PLA TRANSPARENTE C35 85X68X920',
                    'PLA GRIS C48 98X78X990',
                    'PLA RECUPERADO C42 92X74X940',
                ],
                380,
                28,
                1650,
                72.0,
                ['Natural', 'Blanco', 'Negro', 'Azul', 'Verde']
            );
        case in_array($warehouseCode, [200, 3200], true):
            return buildCatalogEntries(
                $warehouseCode,
                'PP',
                [
                    'PP NEGRO C90 100X80X1100',
                    'PP BLANCO W80 100X80X1100',
                    'PP NATURAL C70 95X75X1020',
                    'PP NEGRO C95 110X85X1180',
                    'PP CRISTAL C60 90X70X980',
                    'PP VERDE C75 96X76X1010',
                    'PP ROJO C88 104X82X1120',
                    'PP AZUL C82 102X80X1090',
                    'PP GRIS C68 92X72X970',
                    'PP CAFE C72 94X74X995',
                ],
                450,
                35,
                1900,
                88.0,
                ['Negro', 'Blanco', 'Natural', 'Verde', 'Azul']
            );
        case in_array($warehouseCode, [300, 400, 700, 1000], true):
            return buildCatalogEntries(
                $warehouseCode,
                'FIN',
                [
                    'BOLSA 20X30 CAMISETA',
                    'BOLSA 25X35 TROQUELADA',
                    'BOLSA 30X40 BASURA',
                    'BOLSA 35X45 NATURAL',
                    'BOLSA 40X50 IMPRESA',
                    'BOLSA 45X60 REFORZADA',
                    'BOLSA 50X70 NEGRA',
                    'BOLSA 55X80 INDUSTRIAL',
                    'BOLSA 60X90 RETAIL',
                    'BOLSA 70X100 SACO',
                ],
                320,
                22,
                900,
                34.0,
                ['Negro', 'Blanco', 'Natural', 'Azul', 'Rojo']
            );
        case $warehouseCode === 500:
            return buildCatalogEntries(
                $warehouseCode,
                'PRD',
                [
                    'BOBINA EN PROCESO FLEXO A',
                    'BOBINA EN PROCESO FLEXO B',
                    'BOBINA EN PROCESO FLEXO C',
                    'BOBINA EN PROCESO SERI A',
                    'BOBINA EN PROCESO SERI B',
                    'BOBINA EN PROCESO CORTE A',
                    'BOBINA EN PROCESO CORTE B',
                    'BOBINA EN PROCESO LAM A',
                    'BOBINA EN PROCESO LAM B',
                    'BOBINA EN PROCESO MIXTA',
                ],
                430,
                32,
                1700,
                76.0,
                ['Natural', 'Negro', 'Azul', 'Rojo']
            );
        case $warehouseCode === 510:
            return buildCatalogEntries(
                $warehouseCode,
                'RSD',
                [
                    'MERMA PP NEGRA',
                    'MERMA PLA NATURAL',
                    'RECORTE IMPRESION AZUL',
                    'RECORTE IMPRESION ROJO',
                    'SOBRANTE SELLADO 35X45',
                    'SOBRANTE SELLADO 50X70',
                    'BOBINA REPROCESO 01',
                    'BOBINA REPROCESO 02',
                    'MATERIAL CONTAMINADO 01',
                    'MATERIAL CONTAMINADO 02',
                ],
                300,
                20,
                600,
                18.0,
                ['Negro', 'Natural', 'Azul', 'Rojo', 'Gris']
            );
        case $warehouseCode === 600:
            return buildCatalogEntries(
                $warehouseCode,
                'REP',
                [
                    'RODILLO FLEXO 12P',
                    'ENGRANAJE IMPRESORA 01',
                    'CUCHILLA CORTE 220',
                    'BANDA TRANSPORTE 02',
                    'SENSOR FOTOELECTRICO',
                    'RODAMIENTO 6204',
                    'TERMOPAR SELLADO',
                    'PISTON NEUMATICO 32',
                    'VALVULA AIRE 1-4',
                    'MOTOR REDUCTOR 0.5HP',
                ],
                180,
                12,
                320,
                9.0,
                ['Metal', 'Negro', 'Azul']
            );
        case $warehouseCode === 800:
            return buildCatalogEntries(
                $warehouseCode,
                'EPP',
                [
                    'GUANTE NITRILO L',
                    'GUANTE NITRILO XL',
                    'LENTE SEGURIDAD CLARO',
                    'MASCARILLA P100',
                    'CASCO BLANCO',
                    'CASCO AZUL',
                    'PECHERA PVC',
                    'CHAQUETA REFLECTANTE',
                    'BOTIN SEGURIDAD 41',
                    'PROTECTOR AUDITIVO',
                ],
                160,
                10,
                250,
                6.5,
                ['Azul', 'Blanco', 'Negro', 'Amarillo']
            );
        case in_array($warehouseCode, [900, 910, 920], true):
            return buildCatalogEntries(
                $warehouseCode,
                'TIN',
                [
                    'TINTA AZUL FLEXO',
                    'TINTA NEGRA FLEXO',
                    'TINTA ROJA FLEXO',
                    'TINTA VERDE FLEXO',
                    'TINTA AMARILLA FLEXO',
                    'TINTA BLANCA FLEXO',
                    'TINTA BARNIZ FLEXO',
                    'TINTA PLATA FLEXO',
                    'TINTA MORADA FLEXO',
                    'TINTA CAFE FLEXO',
                ],
                140,
                8,
                180,
                4.8,
                ['Azul', 'Negro', 'Rojo', 'Verde', 'Amarillo']
            );
        case $warehouseCode === 2000:
            return buildCatalogEntries(
                $warehouseCode,
                'EXT',
                [
                    'SERVICIO IMPRESION EXTERNA 01',
                    'SERVICIO IMPRESION EXTERNA 02',
                    'SERVICIO CORTE EXTERNO 01',
                    'SERVICIO CORTE EXTERNO 02',
                    'SERVICIO LAMINADO 01',
                    'SERVICIO LAMINADO 02',
                    'SERVICIO SELLADO 01',
                    'SERVICIO SELLADO 02',
                    'SERVICIO TROQUEL 01',
                    'SERVICIO TROQUEL 02',
                ],
                260,
                14,
                450,
                12.0,
                ['Negro', 'Natural', 'Azul']
            );
        case $warehouseCode === 3000:
            return buildCatalogEntries(
                $warehouseCode,
                'IPR',
                [
                    'INSUMO ACTIVO FLEXO A',
                    'INSUMO ACTIVO FLEXO B',
                    'INSUMO ACTIVO FLEXO C',
                    'INSUMO ACTIVO SERI A',
                    'INSUMO ACTIVO SERI B',
                    'INSUMO ACTIVO CORTE A',
                    'INSUMO ACTIVO CORTE B',
                    'INSUMO ACTIVO SELLADO A',
                    'INSUMO ACTIVO RETAIL A',
                    'INSUMO ACTIVO RETAIL B',
                ],
                420,
                30,
                1600,
                70.0,
                ['Natural', 'Negro', 'Blanco', 'Azul']
            );
        case $warehouseCode === 3100:
            return buildCatalogEntries(
                $warehouseCode,
                'LMP',
                [
                    'DETERGENTE INDUSTRIAL',
                    'ALCOHOL ISOPROPILICO',
                    'DESENGRASANTE MAQUINA',
                    'TRAPO INDUSTRIAL',
                    'ESCOBILLA PISO',
                    'BOLSA BASURA 100L',
                    'JABON LIQUIDO',
                    'PAPEL SECANTE',
                    'PAO MICROFIBRA',
                    'ATOMIZADOR LIMPIEZA',
                ],
                120,
                8,
                150,
                3.8,
                ['Transparente', 'Azul', 'Blanco']
            );
        case $warehouseCode === 4000:
            return buildCatalogEntries(
                $warehouseCode,
                'USA',
                [
                    'BOBINA USADA PLA 01',
                    'BOBINA USADA PLA 02',
                    'BOBINA USADA PP 01',
                    'BOBINA USADA PP 02',
                    'BOBINA USADA NEGRA 01',
                    'BOBINA USADA BLANCA 01',
                    'BOBINA USADA RETAIL 01',
                    'BOBINA USADA FLEXO 01',
                    'BOBINA USADA SERI 01',
                    'BOBINA USADA CORTE 01',
                ],
                260,
                16,
                420,
                11.0,
                ['Negro', 'Blanco', 'Natural', 'Azul']
            );
        case $warehouseCode === 5000:
            return buildCatalogEntries(
                $warehouseCode,
                'INM',
                [
                    'STOCK OBSOLETO PLA 01',
                    'STOCK OBSOLETO PP 01',
                    'STOCK OBSOLETO NEGRO 01',
                    'STOCK OBSOLETO BLANCO 01',
                    'STOCK OBSOLETO RETAIL 01',
                    'STOCK OBSOLETO TROQUEL 01',
                    'STOCK OBSOLETO FLEXO 01',
                    'STOCK OBSOLETO SERI 01',
                    'STOCK OBSOLETO SACO 01',
                    'STOCK OBSOLETO BASURA 01',
                ],
                240,
                15,
                380,
                10.0,
                ['Gris', 'Negro', 'Natural']
            );
        case $warehouseCode === 6000:
            return buildCatalogEntries(
                $warehouseCode,
                'SRV',
                [
                    'SERVICIO MANTENCION PRENSA',
                    'SERVICIO CALIBRACION BALANZA',
                    'SERVICIO FLETE INTERNO',
                    'SERVICIO INSTALACION REPUESTO',
                    'SERVICIO ASEO INDUSTRIAL',
                    'SERVICIO MONTAJE CILINDRO',
                    'SERVICIO AJUSTE SELLO',
                    'SERVICIO SOPORTE TI',
                    'SERVICIO CAPACITACION',
                    'SERVICIO INSPECCION',
                ],
                110,
                6,
                120,
                2.6,
                ['Servicio', 'Interno']
            );
        default:
            return buildCatalogEntries(
                $warehouseCode,
                'GEN',
                [
                    'ITEM GENERICO 01',
                    'ITEM GENERICO 02',
                    'ITEM GENERICO 03',
                    'ITEM GENERICO 04',
                    'ITEM GENERICO 05',
                    'ITEM GENERICO 06',
                    'ITEM GENERICO 07',
                    'ITEM GENERICO 08',
                    'ITEM GENERICO 09',
                    'ITEM GENERICO 10',
                ],
                200,
                10,
                300,
                7.0,
                ['Natural', 'Negro']
            );
    }
}

/**
 * @param string[] $descriptions
 * @param string[] $colors
 * @return array<int,array{sku_code:string,sku_description:string,weight_kg:float,grams:int,width_mm:int,color:string,meters:float}>
 */
function buildCatalogEntries(
    int $warehouseCode,
    string $prefix,
    array $descriptions,
    int $baseWidth,
    int $baseGrams,
    float $baseMeters,
    float $baseWeight,
    array $colors
): array {
    $entries = [];
    foreach ($descriptions as $index => $description) {
        $color = $colors[$index % count($colors)];
        $entries[] = [
            'sku_code' => sprintf('%s-%04d-%02d', $prefix, $warehouseCode, $index + 1),
            'sku_description' => $description,
            'weight_kg' => round($baseWeight + ($index * 3.4), 3),
            'grams' => $baseGrams + (($index % 4) * 2),
            'width_mm' => $baseWidth + ($index * 12),
            'color' => $color,
            'meters' => round($baseMeters + ($index * 55.0), 3),
        ];
    }

    return $entries;
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
