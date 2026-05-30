<?php
declare(strict_types=1);
set_time_limit(0);
ini_set('memory_limit', '1024M');

function out(string $message): void { echo '[' . date('H:i:s') . '] ' . $message . PHP_EOL; }
function connect(string $dsn, string $user, string $pass): PDO {
  return new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
  ]);
}
function qi(string $name): string { return '`' . str_replace('`', '``', $name) . '`'; }
function normalizeCreateTable(string $sql): string { return preg_replace('/AUTO_INCREMENT=\d+\s*/i', '', $sql) ?? $sql; }
function copyTable(PDO $src, PDO $dst, string $db, string $table): void {
  out("Tabla {$db}.{$table}: estructura");
  $src->exec('USE ' . qi($db));
  $dst->exec('USE ' . qi($db));
  $createRow = $src->query('SHOW CREATE TABLE ' . qi($table))->fetch();
  $createSql = normalizeCreateTable((string)($createRow['Create Table'] ?? ''));
  if ($createSql === '') { throw new RuntimeException('Sin CREATE TABLE para ' . $table); }
  $dst->exec('SET FOREIGN_KEY_CHECKS=0');
  $dst->exec('DROP TABLE IF EXISTS ' . qi($table));
  $dst->exec($createSql);
  $count = (int)$src->query('SELECT COUNT(*) FROM ' . qi($table))->fetchColumn();
  out("Tabla {$db}.{$table}: {$count} filas");
  if ($count === 0) { return; }
  $select = $src->query('SELECT * FROM ' . qi($table));
  $first = $select->fetch(PDO::FETCH_ASSOC);
  if ($first === false) { return; }
  $columns = array_keys($first);
  $insert = $dst->prepare('INSERT INTO ' . qi($table) . ' (' . implode(', ', array_map('qi', $columns)) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')');
  $dst->beginTransaction();
  $inserted = 0;
  $row = $first;
  while ($row !== false) {
    $vals = [];
    foreach ($columns as $col) { $vals[] = $row[$col]; }
    $insert->execute($vals);
    $inserted++;
    if ($inserted % 1000 === 0) {
      $dst->commit();
      out("Tabla {$db}.{$table}: {$inserted}/{$count}");
      $dst->beginTransaction();
    }
    $row = $select->fetch(PDO::FETCH_ASSOC);
  }
  $dst->commit();
}
$src = connect('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', 'AY2309');
$dst = connect('mysql:host=ballast.proxy.rlwy.net;port=28980;charset=utf8mb4', 'root', 'xDMhawbiomnmPcUfjRxiTWSJsNvznezj');
$dst->exec('CREATE DATABASE IF NOT EXISTS `unibagqa` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$tables = [
  'country',
  'supplier',
  'item',
  'company_shops',
  'company_shops_storehouses',
  'supplier_order',
  'supplier_order_items',
  'supplier_contenedor',
  'supplier_contenedor_items'
];
foreach ($tables as $table) { copyTable($src, $dst, 'unibagqa', $table); }
out('Migracion ERP focalizada completada');
