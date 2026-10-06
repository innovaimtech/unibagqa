<?php
require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Db.php';
require_once __DIR__ . '/src/ProductionService.php';
Env::load(__DIR__ . '/.env');

$prod = new ProductionService(Db::erpPdo(), Db::trzPdo());

// Let's time inside getWorkOrderOperatingContext
$agId = 487;
$workerId = 4075;
$plantaId = 1;

$t0 = microtime(true);
$openInit = $prod->getOpenWorkerInit($workerId, $plantaId);
echo "getOpenWorkerInit: " . round((microtime(true) - $t0) * 1000, 2) . " ms\n";

$t0 = microtime(true);
$workerOt = $prod->getOpenWorkerOtByAgId($workerId, $plantaId, $agId);
echo "getOpenWorkerOtByAgId: " . round((microtime(true) - $t0) * 1000, 2) . " ms\n";

$t0 = microtime(true);
$res = $prod->getWorkOrderOperatingContext($agId, $workerId, $plantaId);
echo "total getWorkOrderOperatingContext: " . round((microtime(true) - $t0) * 1000, 2) . " ms\n";
