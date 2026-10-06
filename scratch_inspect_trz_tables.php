<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=unibag_trazabilidad;charset=utf8mb4', 'root', 'AY2309');
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "=== TABLAS EN unibag_trazabilidad ===\n";
print_r($tables);

foreach ($tables as $t) {
    if (in_array($t, ['rolls', 'boxes', 'pallets', 'work_orders', 'work_order_roll_outputs', 'machine_outputs', 'production_records', 'production_events', 'stock_moves', 'cliches'], true)) {
        echo "\n=== ESTRUCTURA: $t ===\n";
        $cols = $pdo->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            echo "  {$c['Field']} ({$c['Type']})\n";
        }
    }
}
