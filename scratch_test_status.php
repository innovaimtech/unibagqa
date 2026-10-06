<?php
require_once __DIR__ . '/src/Env.php';
Env::load(__DIR__ . '/.env');
require_once __DIR__ . '/src/Db.php';
require_once __DIR__ . '/src/ProductionService.php';

$svc = new ProductionService(Db::erpPdo());
$alis = $svc->getWorkOrderAlistamientoStatus(1000191);
$auto = $svc->getWorkOrderAutocontrolStatus(1000191, 7);

echo "ALISTAMIENTO:\n";
print_r($alis);
echo "\nAUTOCONTROL:\n";
print_r($auto);
