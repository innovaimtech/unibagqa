<?php
require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Db.php';
Env::load(__DIR__ . '/.env');
$pdo = Db::erpPdo();
require_once __DIR__ . '/src/ReceptionService.php';
$service = new ReceptionService(Db::trzPdo(), Db::erpPdo());
$lines = $service->listImportContainerLines(279);
foreach ($lines as $l) {
    $sm = $service->summarizeReceptionLine($l);
    echo "Line " . $l['import_container_item_id'] . ":\n";
    print_r($sm);
}
