<?php
function summarize(PDO $pdo, string $db): void {
  $tables = $pdo->query("SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.tables WHERE table_schema = " . $pdo->quote($db) . " AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
  $count = count($tables);
  $rows = 0;
  foreach ($tables as $t) { $rows += (int)($t['TABLE_ROWS'] ?? 0); }
  echo $db . ' tables=' . $count . ' rows~=' . $rows . PHP_EOL;
}
$src = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', 'AY2309', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
summarize($src, 'unibagqa');
summarize($src, 'unibag_trazabilidad');
