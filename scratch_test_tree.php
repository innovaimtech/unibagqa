<?php
require_once __DIR__ . '/src/Env.php';
Env::load(__DIR__ . '/.env');
require_once __DIR__ . '/src/Db.php';
require_once __DIR__ . '/src/ProductionService.php';

$prodService = new ProductionService(Db::erpPdo(), Db::trzPdo());

// Test with any existing roll in rolls
$trzPdo = Db::trzPdo();
$sampleRoll = $trzPdo->query("SELECT roll_code FROM rolls ORDER BY id DESC LIMIT 1")->fetchColumn();
echo "Sample Roll: " . ($sampleRoll ?: 'none') . "\n";
if ($sampleRoll) {
    $tree = $prodService->getTraceabilityTree($sampleRoll);
    echo "Roll Tree Keys: " . json_encode(array_keys(array_filter($tree)), JSON_PRETTY_PRINT) . "\n";
    if (!empty($tree['roll'])) {
        echo "Roll Code: {$tree['roll']['roll_code']} ({$tree['roll']['process_stage']})\n";
    }
}

// Test with any existing box in boxes
$sampleBox = $trzPdo->query("SELECT box_code FROM boxes ORDER BY id DESC LIMIT 1")->fetchColumn();
echo "\nSample Box: " . ($sampleBox ?: 'none') . "\n";
if ($sampleBox) {
    $treeBox = $prodService->getTraceabilityTree($sampleBox);
    echo "Box Tree Keys: " . json_encode(array_keys(array_filter($treeBox)), JSON_PRETTY_PRINT) . "\n";
}
