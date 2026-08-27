<?php

declare(strict_types=1);

require __DIR__ . '/../src/Env.php';
require __DIR__ . '/../src/Db.php';

Env::load(__DIR__ . '/../.env');

$pdo = Db::erpPdo();

$like = (string)($argv[1] ?? 'prod\\_%');
if (!preg_match('/^[A-Za-z0-9%_\\\\-]+$/', $like)) {
    $like = 'prod\\_%';
}
$sql = "SHOW TABLES LIKE " . $pdo->quote($like);
$tables = $pdo->query($sql)->fetchAll(PDO::FETCH_NUM);

echo "tables=" . count($tables) . PHP_EOL;
foreach ($tables as $t) {
    $table = (string)($t[0] ?? '');
    if ($table === '') {
        continue;
    }
    echo PHP_EOL . $table . PHP_EOL;
    $cols = $pdo->query("SHOW COLUMNS FROM `" . str_replace('`', '``', $table) . "`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "  " . (string)($c['Field'] ?? '') . " " . (string)($c['Type'] ?? '') . PHP_EOL;
    }
}
