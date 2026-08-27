<?php

declare(strict_types=1);

require __DIR__ . '/../src/Env.php';
require __DIR__ . '/../src/Db.php';

Env::load(__DIR__ . '/../.env');

$pdo = Db::erpPdo();

$tests = [
    'prod_worker_ot_events' => "SELECT COUNT(*) AS c FROM prod_worker_ot_events",
    'prod_worker_ot' => "SELECT COUNT(*) AS c FROM prod_worker_ot",
    'prod_agenda' => "SELECT COUNT(*) AS c FROM prod_agenda",
    'prod_header' => "SELECT COUNT(*) AS c FROM prod_header",
    'workers' => "SELECT COUNT(*) AS c FROM workers",
];

foreach ($tests as $label => $sql) {
    try {
        $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        echo $label . '=' . (string)($row['c'] ?? '?') . PHP_EOL;
    } catch (Throwable $e) {
        echo $label . '=ERROR ' . $e->getMessage() . PHP_EOL;
    }
}
