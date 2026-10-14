<?php
require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Db.php';
Env::load(__DIR__ . '/.env');
$trz = Db::trzPdo();
if ($trz) {
    $stmt = $trz->query("SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'unibag_trazabilidad' AND (COLUMN_NAME LIKE '%armad%' OR COLUMN_NAME LIKE '%etiquet%' OR COLUMN_NAME LIKE '%reimpr%' OR COLUMN_NAME LIKE '%corte%')");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
}
