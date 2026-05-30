<?php
require __DIR__ . '/src/Env.php';
require __DIR__ . '/src/Db.php';
Env::load(__DIR__ . '/.env');
$pdo = Db::trzPdo();
$stmt = $pdo->query("SELECT id, ot_code, status FROM work_orders WHERE id IN (7,8) ORDER BY id");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
