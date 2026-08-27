<?php

declare(strict_types=1);

require __DIR__ . '/../src/Env.php';
require __DIR__ . '/../src/Db.php';
require __DIR__ . '/../src/InventoryCountService.php';
require __DIR__ . '/../src/RollReceptionService.php';
require __DIR__ . '/../src/ReceptionService.php';

Env::load(__DIR__ . '/../.env');

$service = new ReceptionService(Db::pdo(), Db::erpPdo());
$monthKey = isset($argv[1]) ? trim((string)$argv[1]) : date('Y-m');
$result = $service->listErpFlexoProductionForBonusPeriod($monthKey);

echo json_encode([
    'ok' => (bool)($result['ok'] ?? false),
    'month' => $monthKey,
    'period' => $result['period'] ?? null,
    'rows' => isset($result['rows']) && is_array($result['rows']) ? count($result['rows']) : 0,
    'errors' => $result['errors'] ?? [],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
