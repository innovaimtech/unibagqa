<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/InventoryCountService.php';
require_once __DIR__ . '/../src/RollReceptionService.php';
require_once __DIR__ . '/../src/ReceptionService.php';

Env::load(__DIR__ . '/../.env');

$pdo = Db::trzPdo();
$erpPdo = Db::erpPdo();
$service = new ReceptionService($pdo, $erpPdo);

$demo = [
    'ot_code' => 'DEMO-OT-EMB-PROD-001',
    'sku_final' => 'BOL0120',
    'target_qty' => 15000,
    'req_id' => '25-00766-1',
    'plan_desc' => 'BOUTIQUE 38X38X18 PLA BEIGE SIN LOGO',
    'plan_date' => date('Y-m-d'),
    'operator_username' => 'operador_demo',
    'operator_password' => '1234',
    'operator_name' => 'Operador Demo',
    'supervisor_username' => 'supervisor_demo',
    'supervisor_password' => '1234',
    'supervisor_name' => 'Supervisor Demo',
    'warehouse_code' => 700,
    'warehouse_name' => 'Bodega 700 - Canal Tradicional',
    'storage_warehouse_code' => 1000,
    'storage_warehouse_name' => 'Bodega 1000 - Retail',
    'roll_code' => 'DEMO-ROLL-EMB-001',
];

ensureUser(
    $pdo,
    $demo['operator_username'],
    $demo['operator_password'],
    $demo['operator_name']
);
ensureUser(
    $pdo,
    $demo['supervisor_username'],
    $demo['supervisor_password'],
    $demo['supervisor_name']
);

$warehouseId = ensureWarehouse($pdo, (int)$demo['warehouse_code'], (string)$demo['warehouse_name']);
ensureWarehouse($pdo, (int)$demo['storage_warehouse_code'], (string)$demo['storage_warehouse_name']);
$skuId = ensureSku($pdo, (string)$demo['sku_final'], 'BOL0120 Boutique Grande 38x38x18');
$workOrderId = upsertWorkOrder($pdo, $demo);

$pdo->beginTransaction();
try {
    cleanupPackagingDemoData($pdo, $workOrderId);

    upsertErpSync($pdo, $workOrderId, $demo);
    $rollId = insertDemoRoll($pdo, $skuId, $warehouseId, $workOrderId, $demo);
    insertDemoPalletsAndBoxes($pdo, $rollId, $workOrderId, $warehouseId, $demo);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

$setupStart = $service->startWorkOrderPackagingSetupEvent(
    $workOrderId,
    'Alistamiento demo Embalaje',
    (string)$demo['operator_name'],
    'Carga automática para pruebas'
);
assertOk($setupStart, 'No fue posible iniciar el alistamiento demo.');

$setupEvents = $service->listWorkOrderPackagingSetupEvents($workOrderId);
$openSetup = null;
foreach ($setupEvents as $event) {
    if ((string)($event['status'] ?? '') === 'OPEN') {
        $openSetup = $event;
        break;
    }
}
if (!is_array($openSetup)) {
    throw new RuntimeException('No se encontró el alistamiento demo abierto.');
}

$finishSetup = $service->finishWorkOrderPackagingSetupEvent(
    $workOrderId,
    (int)($openSetup['start_event_id'] ?? 0),
    (string)$demo['operator_name']
);
assertOk($finishSetup, 'No fue posible terminar el alistamiento demo.');

$approval = $service->approveWorkOrderPackagingSetup(
    $workOrderId,
    'SUPERVISOR',
    (string)$demo['supervisor_username'],
    (string)$demo['supervisor_name']
);
assertOk($approval, 'No fue posible aprobar el alistamiento demo.');

$startProduction = $service->startWorkOrderPackagingProductionEvent(
    $workOrderId,
    (string)$demo['operator_name'],
    'Producción demo Embalaje iniciada'
);
assertOk($startProduction, 'No fue posible iniciar la producción demo.');

$boxes = $service->listBoxesByWorkOrder($workOrderId);
$pallets = $service->listPalletsByWorkOrder($workOrderId);
$productionEvents = $service->listWorkOrderPackagingProductionEvents($workOrderId);

echo json_encode([
    'ok' => true,
    'message' => 'Datos demo de producción Embalaje cargados.',
    'work_order_id' => $workOrderId,
    'ot_code' => $demo['ot_code'],
    'operator_user' => $demo['operator_username'],
    'operator_password' => $demo['operator_password'],
    'supervisor_user' => $demo['supervisor_username'],
    'supervisor_password' => $demo['supervisor_password'],
    'boxes' => count($boxes),
    'pallets' => count($pallets),
    'production_events' => $productionEvents,
    'routes' => [
        'inicio' => '/work-orders/' . $workOrderId . '/packaging',
        'setup' => '/work-orders/' . $workOrderId . '/packaging/setup',
        'finish_data' => '/work-orders/' . $workOrderId . '/packaging/finish-data',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

function ensureUser(PDO $pdo, string $username, string $password, string $displayName): void
{
    $stmt = $pdo->prepare('SELECT id FROM auth_users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $existingId = $stmt->fetchColumn();

    $payload = [
        ':username' => $username,
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':display_name' => $displayName,
    ];

    if ($existingId !== false) {
        $stmt = $pdo->prepare(
            'UPDATE auth_users
             SET password_hash = :password_hash,
                 display_name = :display_name,
                 is_active = 1,
                 can_erp = 1,
                 can_production = 1,
                 can_operator = 1,
                 can_warehouse = 1,
                 can_marketing = 0
             WHERE username = :username'
        );
        $stmt->execute($payload);
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO auth_users (
            username, password_hash, display_name, is_active,
            can_erp, can_production, can_operator, can_warehouse, can_marketing
         ) VALUES (
            :username, :password_hash, :display_name, 1,
            1, 1, 1, 1, 0
         )'
    );
    $stmt->execute($payload);
}

function ensureWarehouse(PDO $pdo, int $code, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM warehouses WHERE code = :code LIMIT 1');
    $stmt->execute([':code' => $code]);
    $warehouseId = $stmt->fetchColumn();
    if ($warehouseId !== false) {
        $pdo->prepare('UPDATE warehouses SET name = :name WHERE id = :id')->execute([
            ':name' => $name,
            ':id' => (int)$warehouseId,
        ]);
        return (int)$warehouseId;
    }

    $stmt = $pdo->prepare('INSERT INTO warehouses (code, name) VALUES (:code, :name)');
    $stmt->execute([
        ':code' => $code,
        ':name' => $name,
    ]);
    return (int)$pdo->lastInsertId();
}

function ensureSku(PDO $pdo, string $code, string $description): int
{
    $stmt = $pdo->prepare('SELECT id FROM skus WHERE code = :code LIMIT 1');
    $stmt->execute([':code' => $code]);
    $skuId = $stmt->fetchColumn();
    if ($skuId !== false) {
        $pdo->prepare('UPDATE skus SET description = :description WHERE id = :id')->execute([
            ':description' => $description,
            ':id' => (int)$skuId,
        ]);
        return (int)$skuId;
    }

    $stmt = $pdo->prepare('INSERT INTO skus (code, description, is_active) VALUES (:code, :description, 1)');
    $stmt->execute([
        ':code' => $code,
        ':description' => $description,
    ]);
    return (int)$pdo->lastInsertId();
}

function upsertWorkOrder(PDO $pdo, array $demo): int
{
    $stmt = $pdo->prepare('SELECT id FROM work_orders WHERE ot_code = :ot_code LIMIT 1');
    $stmt->execute([':ot_code' => $demo['ot_code']]);
    $workOrderId = $stmt->fetchColumn();
    if ($workOrderId !== false) {
        $pdo->prepare(
            'UPDATE work_orders
             SET sku_final = :sku_final,
                 target_qty = :target_qty,
                 status = :status
             WHERE id = :id'
        )->execute([
            ':sku_final' => $demo['sku_final'],
            ':target_qty' => $demo['target_qty'],
            ':status' => 'CUTTING',
            ':id' => (int)$workOrderId,
        ]);
        return (int)$workOrderId;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO work_orders (ot_code, sku_final, target_qty, status)
         VALUES (:ot_code, :sku_final, :target_qty, :status)'
    );
    $stmt->execute([
        ':ot_code' => $demo['ot_code'],
        ':sku_final' => $demo['sku_final'],
        ':target_qty' => $demo['target_qty'],
        ':status' => 'CUTTING',
    ]);
    return (int)$pdo->lastInsertId();
}

function cleanupPackagingDemoData(PDO $pdo, int $workOrderId): void
{
    $rollIdsStmt = $pdo->prepare('SELECT id FROM rolls WHERE source_work_order_id = :work_order_id');
    $rollIdsStmt->execute([':work_order_id' => $workOrderId]);
    $rollIds = array_map('intval', $rollIdsStmt->fetchAll(PDO::FETCH_COLUMN));

    $pdo->prepare(
        'DELETE FROM events
         WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) = :work_order_id'
    )->execute([':work_order_id' => (string)$workOrderId]);

    $pdo->prepare('DELETE FROM boxes WHERE work_order_id = :work_order_id')->execute([
        ':work_order_id' => $workOrderId,
    ]);
    $pdo->prepare('DELETE FROM pallets WHERE work_order_id = :work_order_id')->execute([
        ':work_order_id' => $workOrderId,
    ]);

    if ($rollIds !== []) {
        $placeholders = implode(',', array_fill(0, count($rollIds), '?'));
        $stmt = $pdo->prepare('DELETE FROM rolls WHERE id IN (' . $placeholders . ')');
        $stmt->execute($rollIds);
    }

    $pdo->prepare('DELETE FROM erp_work_order_sync WHERE work_order_id = :work_order_id')->execute([
        ':work_order_id' => $workOrderId,
    ]);
}

function upsertErpSync(PDO $pdo, int $workOrderId, array $demo): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO erp_work_order_sync (
            work_order_id, erp_prod_header_id, erp_agenda_id, erp_worker_ot_id, erp_worker_init_id,
            erp_worker_id, erp_worker_name, erp_user_id, erp_user_login, erp_prod_number,
            erp_req_id, erp_plan_desc, erp_plan_date, erp_plan_timestamp, erp_machine_id,
            erp_machine_label, erp_machine_type_id, erp_planta_id, erp_target_qty,
            erp_header_status, erp_agenda_status, erp_agenda_active, erp_worker_status
         ) VALUES (
            :work_order_id, :erp_prod_header_id, :erp_agenda_id, :erp_worker_ot_id, :erp_worker_init_id,
            :erp_worker_id, :erp_worker_name, :erp_user_id, :erp_user_login, :erp_prod_number,
            :erp_req_id, :erp_plan_desc, :erp_plan_date, :erp_plan_timestamp, :erp_machine_id,
            :erp_machine_label, :erp_machine_type_id, :erp_planta_id, :erp_target_qty,
            :erp_header_status, :erp_agenda_status, :erp_agenda_active, :erp_worker_status
         )'
    );
    $stmt->execute([
        ':work_order_id' => $workOrderId,
        ':erp_prod_header_id' => 900000 + $workOrderId,
        ':erp_agenda_id' => 910000 + $workOrderId,
        ':erp_worker_ot_id' => 920000 + $workOrderId,
        ':erp_worker_init_id' => 930000 + $workOrderId,
        ':erp_worker_id' => 940000 + $workOrderId,
        ':erp_worker_name' => $demo['operator_name'],
        ':erp_user_id' => 950000 + $workOrderId,
        ':erp_user_login' => $demo['operator_username'],
        ':erp_prod_number' => $demo['ot_code'],
        ':erp_req_id' => $demo['req_id'],
        ':erp_plan_desc' => $demo['plan_desc'],
        ':erp_plan_date' => $demo['plan_date'],
        ':erp_plan_timestamp' => time(),
        ':erp_machine_id' => 201,
        ':erp_machine_label' => 'EMBALAJE',
        ':erp_machine_type_id' => null,
        ':erp_planta_id' => 1,
        ':erp_target_qty' => $demo['target_qty'],
        ':erp_header_status' => 'ACTIVE',
        ':erp_agenda_status' => 'ACTIVE',
        ':erp_agenda_active' => 1,
        ':erp_worker_status' => 'ACTIVE',
    ]);
}

function insertDemoRoll(PDO $pdo, int $skuId, int $warehouseId, int $workOrderId, array $demo): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO rolls (
            roll_code, sku_id, warehouse_id, weight_kg, received_qty, microns, width_mm, color, meters, status,
            current_work_order_id, parent_roll_id, source_work_order_id, process_stage
         ) VALUES (
            :roll_code, :sku_id, :warehouse_id, :weight_kg, :received_qty, :microns, :width_mm, :color, :meters, :status,
            :current_work_order_id, NULL, :source_work_order_id, :process_stage
         )'
    );
    $stmt->execute([
        ':roll_code' => $demo['roll_code'],
        ':sku_id' => $skuId,
        ':warehouse_id' => $warehouseId,
        ':weight_kg' => 185.5,
        ':received_qty' => 1,
        ':microns' => 55,
        ':width_mm' => 380,
        ':color' => 'Beige',
        ':meters' => 1500,
        ':status' => 'IN_PROCESS',
        ':current_work_order_id' => $workOrderId,
        ':source_work_order_id' => $workOrderId,
        ':process_stage' => 'CUT',
    ]);
    return (int)$pdo->lastInsertId();
}

function insertDemoPalletsAndBoxes(PDO $pdo, int $rollId, int $workOrderId, int $warehouseId, array $demo): void
{
    $palletIds = [];
    $insertPallet = $pdo->prepare(
        'INSERT INTO pallets (
            pallet_code, work_order_id, source_roll_id, final_sku, destination_mode,
            customer_order_ref, warehouse_id, box_count, operator_name, status
         ) VALUES (
            :pallet_code, :work_order_id, :source_roll_id, :final_sku, :destination_mode,
            :customer_order_ref, :warehouse_id, :box_count, :operator_name, :status
         )'
    );

    for ($i = 1; $i <= 12; $i++) {
        $insertPallet->execute([
            ':pallet_code' => sprintf('DEMO-PAL-EMB-%03d', $i),
            ':work_order_id' => $workOrderId,
            ':source_roll_id' => $rollId,
            ':final_sku' => $demo['sku_final'],
            ':destination_mode' => 'PACKAGING',
            ':customer_order_ref' => $demo['req_id'],
            ':warehouse_id' => $warehouseId,
            ':box_count' => 12,
            ':operator_name' => $demo['operator_name'],
            ':status' => 'CREATED',
        ]);
        $palletIds[] = (int)$pdo->lastInsertId();
    }

    $insertBox = $pdo->prepare(
        'INSERT INTO boxes (
            box_code, work_order_id, source_roll_id, pallet_id, final_sku, units_qty,
            destination_mode, customer_order_ref, warehouse_id, operator_name, status
         ) VALUES (
            :box_code, :work_order_id, :source_roll_id, :pallet_id, :final_sku, :units_qty,
            :destination_mode, :customer_order_ref, :warehouse_id, :operator_name, :status
         )'
    );

    for ($i = 1; $i <= 150; $i++) {
        $palletId = $i <= 144 ? $palletIds[(int)floor(($i - 1) / 12)] : null;
        $insertBox->execute([
            ':box_code' => sprintf('DEMO-BOX-EMB-%03d', $i),
            ':work_order_id' => $workOrderId,
            ':source_roll_id' => $rollId,
            ':pallet_id' => $palletId,
            ':final_sku' => $demo['sku_final'],
            ':units_qty' => 100,
            ':destination_mode' => 'PACKAGING',
            ':customer_order_ref' => $demo['req_id'],
            ':warehouse_id' => $warehouseId,
            ':operator_name' => $demo['operator_name'],
            ':status' => 'CREATED',
        ]);
    }
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
