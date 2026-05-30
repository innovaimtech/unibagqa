<?php
require __DIR__ . '/src/Env.php';
require __DIR__ . '/src/Db.php';
Env::load(__DIR__ . '/.env');
$pdo = Db::erpPdo();
foreach (['supplier','supplier_order','item'] as $table) {
  echo "TABLE: $table\n";
  $stmt = $pdo->query("SHOW CREATE TABLE {$table}");
  $row = $stmt->fetch(PDO::FETCH_NUM);
  echo ($row[1] ?? '') . "\n\n";
}
