<?php
require_once __DIR__ . '/src/Env.php';
Env::load(__DIR__ . '/.env');
require_once __DIR__ . '/src/Db.php';

$pdo = Db::erpPdo();
$tables = ['prod_worker_ot', 'prod_worker_ot_events', 'prod_worker_ot_autocontrol', 'prod_agenda', 'prod_anilox_ot', 'prod_anilox_ot_worker', 'orders_classify_rebo_values'];
foreach ($tables as $t) {
    $c = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    echo "=== $t ($c rows) ===\n";
    $st = $pdo->query("SHOW INDEX FROM $t");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $idx) {
        echo "{$idx['Key_name']} -> {$idx['Column_name']}\n";
    }
}
