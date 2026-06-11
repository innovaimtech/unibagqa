<?php

declare(strict_types=1);

require __DIR__ . '/../src/Env.php';
require __DIR__ . '/../src/Db.php';
require __DIR__ . '/erp_production_support.php';

Env::load(__DIR__ . '/../.env');

$erp = Db::erpPdo();

try {
    $tables = ensureErpProductionSchema($erp);
    $operator = ensureErpOperatorContext($erp);

    echo json_encode([
        'ok' => true,
        'tables' => $tables,
        'operator' => $operator,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    throw $e;
}
