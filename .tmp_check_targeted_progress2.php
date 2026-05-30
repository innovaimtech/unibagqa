<?php
$pdo = new PDO('mysql:host=ballast.proxy.rlwy.net;port=28980;dbname=unibagqa;charset=utf8mb4', 'root', 'xDMhawbiomnmPcUfjRxiTWSJsNvznezj', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['country','supplier','item','supplier_order','supplier_order_items','supplier_contenedor','supplier_contenedor_items','company_shops_storehouses'] as $table) {
  try { echo $table . '=' . $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() . PHP_EOL; } catch (Throwable $e) { echo $table . '=ERR' . PHP_EOL; }
}
