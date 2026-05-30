<?php
require __DIR__ . '/src/Env.php';
require __DIR__ . '/src/Db.php';
Env::load(__DIR__ . '/.env');
$pdo = Db::trzPdo();
$stmt = $pdo->query("SELECT id, ot_code, status FROM work_orders WHERE ot_code LIKE 'DEMO-OT-%' ORDER BY id DESC LIMIT 10");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
