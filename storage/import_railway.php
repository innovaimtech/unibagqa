<?php
$pdo = new PDO('mysql:host=yamabiko.proxy.rlwy.net;port=52311;dbname=railway;charset=utf8mb4', 'root', 'reXWlpnPnuQvXeWbJakDQjpcDhWGByfN', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
]);
$base = 'C:/Users/Axiliarmu/Desktop/unibag proyecto';
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ([
    'chemical_weighings','chemicals','movements','events','rolls','purchase_order_lines','purchase_orders','suppliers','app_settings','work_orders','skus','warehouses'
] as $table) {
    $pdo->exec("DROP TABLE IF EXISTS {$table}");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$files = [
    $base . '/database/schema.sql',
    $base . '/database/migrations/001_app_settings_fix.sql',
    $base . '/database/migrations/002_roll_specs_nullable.sql',
    $base . '/database/migrations/003_chemicals_and_weighings.sql',
    $base . '/database/migrations/004_purchase_orders.sql',
    $base . '/database/migrations/005_inventory_traceability.sql',
    $base . '/database/seeds/demo_more.sql',
];
foreach ($files as $file) {
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('No se pudo leer ' . $file);
    }
    $pdo->exec($sql);
    echo "Importado: {$file}\n";
}
echo "Importacion completada\n";
