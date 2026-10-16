<?php
$rootDir = dirname(__DIR__);
require_once $rootDir . '/src/Env.php';
Env::load($rootDir . '/.env');
require_once $rootDir . '/src/Db.php';
require_once $rootDir . '/src/ReceptionService.php';

$svc = new ReceptionService();
$c = $svc->getImportContainer(275);

echo "CONTAINER 275 STATUS: " . $c['status'] . PHP_EOL;
echo "ORDERED ROLLS: " . $c['rolls_count'] . PHP_EOL;
echo "RECEIVED ROLLS: " . $c['received_rolls'] . PHP_EOL;
echo "PENDING ROLLS: " . $c['pending_rolls'] . PHP_EOL;

foreach ($c['items'] as $it) {
    echo "Item {$it['id']} ({$it['sku']}): ordered={$it['rolls_count']}, received={$it['received_rolls']}, pending={$it['pending_rolls']}" . PHP_EOL;
}
