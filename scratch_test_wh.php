<?php
require_once __DIR__ . '/src/Env.php';
Env::load(__DIR__ . '/.env');
require_once __DIR__ . '/src/Db.php';

$trzPdo = Db::trzPdo();
$entries = $trzPdo->query("SELECT * FROM traceability_warehouse_entries LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($entries);
