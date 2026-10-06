<?php
require_once __DIR__ . '/src/Env.php';
Env::load(__DIR__ . '/.env');
require_once __DIR__ . '/src/Db.php';
require_once __DIR__ . '/src/ReceptionService.php';
require_once __DIR__ . '/src/PrintService.php';

$svc = new ReceptionService(Db::trzPdo(), Db::erpPdo());
$printer = new PrintService($_ENV);

$stmt = Db::trzPdo()->query("SELECT id FROM rolls WHERE status = 'RECEIVED' ORDER BY id DESC LIMIT 1");
$row = $stmt->fetch();
if (!$row) {
    echo "No received roll found\n";
    exit;
}
$roll = $svc->getRoll((int)$row['id']);
echo "ROLL ID: " . $roll['id'] . "\n";
echo "ROLL CODE: " . ($roll['roll_code'] ?? '') . "\n";
echo "PRINT LANGUAGE: " . $printer->getConfiguredPrintLanguage() . "\n";
echo "COMMAND:\n" . $printer->getRollPrintCommand($roll) . "\n";
