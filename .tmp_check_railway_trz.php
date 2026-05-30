<?php
$pdo = new PDO('mysql:host=ballast.proxy.rlwy.net;port=28980;dbname=unibag_trazabilidad;charset=utf8mb4', 'root', 'xDMhawbiomnmPcUfjRxiTWSJsNvznezj', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['app_settings','auth_users','chemicals','events','skus','warehouses','work_orders'] as $table) {
  try {
    $count = $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    echo $table . '=' . $count . PHP_EOL;
  } catch (Throwable $e) {
    echo $table . '=ERR ' . $e->getMessage() . PHP_EOL;
  }
}
