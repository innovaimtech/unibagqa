<?php

declare(strict_types=1);

require __DIR__ . '/../src/Env.php';
require __DIR__ . '/../src/Db.php';
require __DIR__ . '/../src/ReceptionService.php';

Env::load(__DIR__ . '/../.env');

$trz = Db::trzPdo();
$erp = Db::erpPdo();
$service = new ReceptionService($trz, $erp);
$result = $service->syncErpProductionPlan(true);

$stmt = $trz->query(
    'SELECT wo.id, wo.ot_code, wo.sku_final, wo.target_qty, wo.status,
            sync.erp_plan_date, sync.erp_machine_label, sync.erp_worker_name, sync.erp_req_id
     FROM work_orders wo
     INNER JOIN erp_work_order_sync sync ON sync.work_order_id = wo.id
     ORDER BY COALESCE(sync.erp_plan_timestamp, UNIX_TIMESTAMP(wo.created_at)) DESC, wo.id DESC
     LIMIT 20'
);

echo json_encode([
    'sync' => $result,
    'work_orders' => $stmt->fetchAll(PDO::FETCH_ASSOC),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
