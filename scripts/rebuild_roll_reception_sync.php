<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/ReceptionService.php';

Env::load(__DIR__ . '/../.env');

$reset = in_array('--reset', $argv, true);
$trz = Db::trzPdo();

if ($reset) {
    $stmt = $trz->prepare(
        "DELETE FROM app_settings
         WHERE setting_key IN (
           'roll_warehouse_traceability_sync_version',
           'roll_erp_stock_sync_version'
         )"
    );
    $stmt->execute();
    echo "Versiones de resincronizacion eliminadas.\n";
}

new ReceptionService($trz, Db::erpPdo());
echo "Resincronizacion de trazabilidad y stock ejecutada correctamente.\n";
