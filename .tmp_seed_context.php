<?php
require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Db.php';
Env::load(__DIR__ . '/.env');
$trz = Db::trzPdo();
$erp = Db::erpPdo();
$tables = ['warehouses','skus','rolls','work_orders','chemicals','chemical_weighings','events','movements','work_order_material_requests','production_wastes','boxes','pallets'];
foreach ($tables as $table) {
  $count = (int)$trz->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
  echo $table . '=' . $count . PHP_EOL;
}
$po = $erp->query("SELECT id, sord_number, sord_supplier_id FROM supplier_order ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo 'erp_pos=' . json_encode($po, JSON_UNESCAPED_UNICODE) . PHP_EOL;
