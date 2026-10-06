<?php
require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Db.php';
Env::load(__DIR__ . '/.env');

$trz = Db::trzPdo();
$stmt = $trz->query("SELECT r.*, s.code as sku_code, s.description as sku_desc, w.name as warehouse_name FROM rolls r LEFT JOIN skus s ON r.sku_id = s.id LEFT JOIN warehouses w ON r.warehouse_id = w.id LIMIT 25");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Total rolls in TRZ: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "ID: {$r['id']} | RollCode: {$r['roll_code']} | SKU: {$r['sku_code']} ({$r['sku_desc']}) | Bodega: {$r['warehouse_name']} | Status: {$r['status']} | WO_ID: {$r['current_work_order_id']}\n";
}
