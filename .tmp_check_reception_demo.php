<?php
require __DIR__ . '/src/Env.php';
require __DIR__ . '/src/Db.php';
require __DIR__ . '/src/ReceptionService.php';
Env::load(__DIR__ . '/.env');
$service = new ReceptionService(Db::trzPdo(), Db::erpPdo());
$pos = $service->listPurchaseOrders(null, 'OC-DEM-NAC', 'active', 'NATIONAL', 10);
$containers = $service->listImportContainers(null, 'CONT-DEM', 'active', 10);
echo json_encode(['purchase_orders' => $pos, 'containers' => $containers], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
