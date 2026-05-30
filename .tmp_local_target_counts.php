<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=unibagqa;charset=utf8mb4', 'root', 'AY2309', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['country','supplier','item','company_shops','company_shops_storehouses','supplier_order','supplier_order_items','supplier_contenedor','supplier_contenedor_items'] as $table) {
  echo $table . '=' . $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() . PHP_EOL;
}
