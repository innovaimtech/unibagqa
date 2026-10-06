<?php
require_once __DIR__ . '/src/Env.php';
Env::load(__DIR__ . '/.env');
require_once __DIR__ . '/src/Db.php';
require_once __DIR__ . '/src/ProductionService.php';

$prodService = new ProductionService(Db::erpPdo(), Db::trzPdo());
$tree = $prodService->getTraceabilityTree('RB-20261005-3HY2SR');
echo "Tree keys: " . implode(', ', array_keys(array_filter($tree))) . "\n";
if (!empty($tree['roll'])) {
    echo "Roll found: " . $tree['roll']['roll_code'] . "\n";
}
