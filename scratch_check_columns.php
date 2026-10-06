<?php
require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Db.php';
Env::load(__DIR__ . '/.env');
$erp = Db::erpPdo();

$trz = Db::trzPdo();
$stmt = $trz->query("SELECT status, count(*) FROM rolls GROUP BY status");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);












