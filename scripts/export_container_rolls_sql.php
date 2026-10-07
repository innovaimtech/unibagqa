<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Db.php';

Env::load(__DIR__ . '/../.env');

$containerId = isset($argv[1]) ? (int)$argv[1] : 0;
if ($containerId <= 0) {
    fwrite(STDERR, "Uso: php scripts/export_container_rolls_sql.php <container_id>\n");
    exit(1);
}

$pdo = Db::trzPdo();
$stmt = $pdo->prepare(
    "SELECT r.roll_code,
            s.code AS sku_code,
            s.description AS sku_description,
            w.code AS warehouse_code,
            w.name AS warehouse_name,
            r.weight_kg, r.received_qty, r.reception_mode,
            r.microns, r.width_mm, r.color, r.meters, r.status,
            r.purchase_order_id, r.purchase_order_line_id, r.import_container_id, r.import_container_item_id,
            r.supplier_id, r.current_work_order_id, r.parent_roll_id, r.source_work_order_id, r.process_stage, r.created_at
     FROM rolls r
     INNER JOIN skus s ON s.id = r.sku_id
     INNER JOIN warehouses w ON w.id = r.warehouse_id
     WHERE r.import_container_id = :container_id
     ORDER BY r.id ASC"
);
$stmt->execute([':container_id' => $containerId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($rows === []) {
    fwrite(STDERR, "No se encontraron bobinas para el contenedor {$containerId}\n");
    exit(2);
}

echo "-- Export de recepcion local para contenedor {$containerId}\n";
echo "-- Generado: " . date('Y-m-d H:i:s') . "\n";
echo "START TRANSACTION;\n";

foreach ($rows as $row) {
    $skuCode = $pdo->quote((string)$row['sku_code']);
    $skuDescription = $pdo->quote((string)$row['sku_description']);
    $warehouseCode = (int)$row['warehouse_code'];
    $warehouseName = $pdo->quote((string)$row['warehouse_name']);
    $rollCode = $pdo->quote((string)$row['roll_code']);
    $weightKg = $pdo->quote((string)$row['weight_kg']);
    $receivedQty = $pdo->quote((string)$row['received_qty']);
    $receptionMode = $pdo->quote((string)$row['reception_mode']);
    $microns = $row['microns'] === null ? 'NULL' : (int)$row['microns'];
    $widthMm = $row['width_mm'] === null ? 'NULL' : (int)$row['width_mm'];
    $color = $row['color'] === null ? 'NULL' : $pdo->quote((string)$row['color']);
    $meters = $row['meters'] === null ? 'NULL' : $pdo->quote((string)$row['meters']);
    $status = $pdo->quote((string)$row['status']);
    $purchaseOrderId = 'NULL';
    $purchaseOrderLineId = 'NULL';
    $importContainerId = $row['import_container_id'] === null ? 'NULL' : (int)$row['import_container_id'];
    $importContainerItemId = $row['import_container_item_id'] === null ? 'NULL' : (int)$row['import_container_item_id'];
    $supplierId = 'NULL';
    $currentWorkOrderId = 'NULL';
    $parentRollId = 'NULL';
    $sourceWorkOrderId = 'NULL';
    $processStage = $row['process_stage'] === null ? 'NULL' : $pdo->quote((string)$row['process_stage']);
    $createdAt = $pdo->quote((string)$row['created_at']);

    echo "INSERT INTO `skus` (`code`, `description`, `is_active`)\n";
    echo "VALUES ({$skuCode}, {$skuDescription}, 1)\n";
    echo "ON DUPLICATE KEY UPDATE `description` = VALUES(`description`), `is_active` = 1;\n";

    echo "INSERT INTO `warehouses` (`code`, `name`)\n";
    echo "VALUES ({$warehouseCode}, {$warehouseName})\n";
    echo "ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);\n";

    echo "INSERT INTO `rolls` (\n";
    echo "  `roll_code`, `sku_id`, `warehouse_id`, `weight_kg`, `received_qty`, `reception_mode`,\n";
    echo "  `microns`, `width_mm`, `color`, `meters`, `status`,\n";
    echo "  `purchase_order_id`, `purchase_order_line_id`, `import_container_id`, `import_container_item_id`,\n";
    echo "  `supplier_id`, `current_work_order_id`, `parent_roll_id`, `source_work_order_id`, `process_stage`, `created_at`\n";
    echo ") VALUES (\n";
    echo "  {$rollCode},\n";
    echo "  (SELECT `id` FROM `skus` WHERE `code` = {$skuCode} LIMIT 1),\n";
    echo "  (SELECT `id` FROM `warehouses` WHERE `code` = {$warehouseCode} LIMIT 1),\n";
    echo "  {$weightKg}, {$receivedQty}, {$receptionMode},\n";
    echo "  {$microns}, {$widthMm}, {$color}, {$meters}, {$status},\n";
    echo "  {$purchaseOrderId}, {$purchaseOrderLineId}, {$importContainerId}, {$importContainerItemId},\n";
    echo "  {$supplierId}, {$currentWorkOrderId}, {$parentRollId}, {$sourceWorkOrderId}, {$processStage}, {$createdAt}\n";
    echo ")\n";
    echo "ON DUPLICATE KEY UPDATE\n";
    echo "  `sku_id` = VALUES(`sku_id`),\n";
    echo "  `warehouse_id` = VALUES(`warehouse_id`),\n";
    echo "  `weight_kg` = VALUES(`weight_kg`),\n";
    echo "  `received_qty` = VALUES(`received_qty`),\n";
    echo "  `reception_mode` = VALUES(`reception_mode`),\n";
    echo "  `microns` = VALUES(`microns`),\n";
    echo "  `width_mm` = VALUES(`width_mm`),\n";
    echo "  `color` = VALUES(`color`),\n";
    echo "  `meters` = VALUES(`meters`),\n";
    echo "  `status` = VALUES(`status`),\n";
    echo "  `purchase_order_id` = VALUES(`purchase_order_id`),\n";
    echo "  `purchase_order_line_id` = VALUES(`purchase_order_line_id`),\n";
    echo "  `import_container_id` = VALUES(`import_container_id`),\n";
    echo "  `import_container_item_id` = VALUES(`import_container_item_id`),\n";
    echo "  `supplier_id` = VALUES(`supplier_id`),\n";
    echo "  `current_work_order_id` = VALUES(`current_work_order_id`),\n";
    echo "  `parent_roll_id` = VALUES(`parent_roll_id`),\n";
    echo "  `source_work_order_id` = VALUES(`source_work_order_id`),\n";
    echo "  `process_stage` = VALUES(`process_stage`);\n";
}

echo "COMMIT;\n";
