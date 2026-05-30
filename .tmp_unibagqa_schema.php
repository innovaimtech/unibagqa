<?php
$tables = [
  'supplier','supplier_order','supplier_order_items','supplier_order_items_specs',
  'item','item_barcodes','item_shops_storehouses','company_shops_storehouses',
  'workers','user','plantas','company_data','prod_worker_ot','prod_worker_ot_events','balanca_kgs'
];
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=unibagqa;charset=utf8mb4', 'root', 'AY2309', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach ($tables as $table) {
    echo "===== TABLE: {$table} =====\n";
    $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo ($row['Create Table'] ?? 'NO CREATE'), "\n\n";
}
