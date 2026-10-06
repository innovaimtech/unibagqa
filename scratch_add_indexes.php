<?php
require_once __DIR__ . '/src/Env.php';
Env::load(__DIR__ . '/.env');
require_once __DIR__ . '/src/Db.php';

$pdo = Db::erpPdo();

$indexes = [
    "ALTER TABLE prod_worker_ot ADD INDEX idx_wok_status (wok_status)",
    "ALTER TABLE prod_worker_ot_events ADD INDEX idx_events_ot_type_status (evt_prod_worker_otid, evt_type, evt_status)",
    "ALTER TABLE prod_worker_ot_autocontrol ADD INDEX idx_autoctrl_init_type (ctr_init_id, ctr_type)",
    "ALTER TABLE prod_anilox_ot ADD INDEX idx_pao_ag_req (prod_anilox_agid, prod_anilox_reqid)",
    "ALTER TABLE prod_anilox_ot_worker ADD INDEX idx_paow_worker_id (paow_ot_worker_id)",
];

foreach ($indexes as $sql) {
    try {
        $t = microtime(true);
        $pdo->exec($sql);
        echo "Executed: $sql (" . round(microtime(true) - $t, 3) . "s)\n";
    } catch (Throwable $e) {
        echo "Error or already exists: " . $e->getMessage() . "\n";
    }
}
