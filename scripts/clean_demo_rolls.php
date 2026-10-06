<?php
require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Db.php';
Env::load(__DIR__ . '/../.env');

$trz = Db::trzPdo();
$demoCount = $trz->query("SELECT COUNT(*) FROM rolls WHERE roll_code LIKE 'DEMO-%'")->fetchColumn();
echo "Found {$demoCount} DEMO rolls in TRZ.\n";

if ($demoCount > 0) {
    // Delete any events referencing demo rolls or set null
    $trz->exec("DELETE FROM rolls WHERE roll_code LIKE 'DEMO-%'");
    echo "Deleted {$demoCount} DEMO rolls successfully.\n";
}

$remaining = $trz->query("SELECT COUNT(*) FROM rolls")->fetchColumn();
echo "Remaining rolls in TRZ: {$remaining}\n";
