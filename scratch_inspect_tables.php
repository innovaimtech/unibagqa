<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=unibag_trazabilidad;charset=utf8mb4', 'root', 'AY2309');
echo "ROLLS:\n";
foreach ($pdo->query('DESCRIBE rolls')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "  {$col['Field']} ({$col['Type']})\n";
}
echo "\nBOXES:\n";
foreach ($pdo->query('DESCRIBE boxes')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "  {$col['Field']} ({$col['Type']})\n";
}
echo "\nPALLETS:\n";
foreach ($pdo->query('DESCRIBE pallets')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "  {$col['Field']} ({$col['Type']})\n";
}
