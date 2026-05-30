<?php
$pdo = new PDO('mysql:host=ballast.proxy.rlwy.net;port=28980;dbname=unibagqa;charset=utf8mb4', 'root', 'xDMhawbiomnmPcUfjRxiTWSJsNvznezj', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='unibagqa' AND TABLE_TYPE='BASE TABLE'")->fetchColumn();
$rows = (int)$pdo->query("SELECT COALESCE(SUM(TABLE_ROWS),0) FROM information_schema.tables WHERE table_schema='unibagqa' AND TABLE_TYPE='BASE TABLE'")->fetchColumn();
echo 'tables=' . $tables . PHP_EOL;
echo 'rows~=' . $rows . PHP_EOL;
foreach (['supplier','supplier_order','supplier_order_items','item'] as $table) {
 try { echo $table . '=' . $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() . PHP_EOL; } catch (Throwable $e) { echo $table . '=ERR' . PHP_EOL; }
}
