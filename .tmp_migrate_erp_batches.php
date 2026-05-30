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
  ]);
}
function qi(string $name): string { return '`' . str_replace('`', '``', $name) . '`'; }
function normalizeCreateTable(string $sql): string { return preg_replace('/AUTO_INCREMENT=\d+\s*/i', '', $sql) ?? $sql; }
function sqlValue(PDO $pdo, mixed $value): string {
  if ($value === null) return 'NULL';
  if (is_int($value) || is_float($value)) return (string)$value;
  if (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) return $pdo->quote($value);
  return $pdo->quote((string)$value);
}
function copyTableBatched(string $table): void {
  $src = connect('mysql:host=127.0.0.1;port=3306;dbname=unibagqa;charset=utf8mb4', 'root', 'AY2309');
  $dst = connect('mysql:host=ballast.proxy.rlwy.net;port=28980;dbname=unibagqa;charset=utf8mb4', 'root', 'xDMhawbiomnmPcUfjRxiTWSJsNvznezj');
  $dst->exec('SET FOREIGN_KEY_CHECKS=0');
  out("Tabla unibagqa.{$table}: preparando");
  $createRow = $src->query('SHOW CREATE TABLE ' . qi($table))->fetch();
  $createSql = normalizeCreateTable((string)($createRow['Create Table'] ?? ''));
  $dst->exec('DROP TABLE IF EXISTS ' . qi($table));
  $dst->exec($createSql);
  $rows = $src->query('SELECT * FROM ' . qi($table))->fetchAll();
  $count = count($rows);
  out("Tabla unibagqa.{$table}: {$count} filas");
  if ($count === 0) return;
  $columns = array_keys($rows[0]);
  $colSql = implode(', ', array_map('qi', $columns));
  $batchSize = 250;
  for ($i = 0; $i < $count; $i += $batchSize) {
    $chunk = array_slice($rows, $i, $batchSize);
    $valuesSql = [];
    foreach ($chunk as $row) {
      $vals = [];
      foreach ($columns as $col) { $vals[] = sqlValue($dst, $row[$col]); }
      $valuesSql[] = '(' . implode(', ', $vals) . ')';
    }
    $sql = 'INSERT INTO ' . qi($table) . ' (' . $colSql . ') VALUES ' . implode(', ', $valuesSql);
    $dst->exec($sql);
    out("Tabla unibagqa.{$table}: " . min($i + $batchSize, $count) . "/{$count}");
  }
  out("Tabla unibagqa.{$table}: completa");
}
try {
  foreach (['supplier_order_items','supplier_contenedor','supplier_contenedor_items'] as $table) {
    copyTableBatched($table);
  }
  out('Migracion ERP final por lotes completada');
} catch (Throwable $e) {
  out('FATAL: ' . $e->getMessage());
  exit(1);
}
