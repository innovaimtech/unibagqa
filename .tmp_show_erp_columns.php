<?php
require __DIR__ . '/src/Env.php';
require __DIR__ . '/src/Db.php';
Env::load(__DIR__ . '/.env');
$pdo = Db::erpPdo();
$tables = ['supplier','country','item','supplier_order','supplier_order_items','supplier_contenedor','supplier_contenedor_items'];
foreach ($tables as $table) {
  echo "TABLE: $table\n";
  $stmt = $pdo->query("SHOW COLUMNS FROM {$table}");
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['Field'] . ' | ' . $row['Type'] . "\n";
  }
  echo "\n";
}
