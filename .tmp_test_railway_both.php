<?php
foreach ([
  'trz' => 'mysql:host=ballast.proxy.rlwy.net;port=28980;dbname=unibag_trazabilidad;charset=utf8mb4',
  'erp' => 'mysql:host=ballast.proxy.rlwy.net;port=28980;dbname=unibagqa;charset=utf8mb4',
] as $name => $dsn) {
  try {
    $pdo = new PDO($dsn, 'root', 'xDMhawbiomnmPcUfjRxiTWSJsNvznezj', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo $name . '=ok ' . $pdo->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;
  } catch (Throwable $e) {
    echo $name . '=err ' . $e->getMessage() . PHP_EOL;
  }
}
