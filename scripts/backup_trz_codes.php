<?php

declare(strict_types=1);

/**
 * Respaldo (solo lectura) de las tablas de códigos de trazabilidad antes de corregirlas:
 * rolls, traceability_warehouse_entries, boxes y warehouses completas, y el id máximo de movements.
 * Guarda un JSON en storage/logs/backup_trz_codes_FECHA.json.
 *
 *   php scripts/backup_trz_codes.php
 */

if (PHP_SAPI !== 'cli') {
    exit("Solo por línea de comandos.\n");
}
require_once dirname(__DIR__) . '/src/bootstrap.php';
Env::load(dirname(__DIR__) . '/.env');
date_default_timezone_set('America/Santiago');

$t = Db::trzPdo();
$t->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$t->exec('SET SESSION TRANSACTION READ ONLY');

$out = ['created_at' => date('c'), 'database' => (string)$t->query('SELECT DATABASE()')->fetchColumn(), 'tables' => []];
foreach (['rolls', 'traceability_warehouse_entries', 'boxes', 'warehouses'] as $table) {
    $out['tables'][$table] = $t->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    echo str_pad($table, 34) . count($out['tables'][$table]) . " filas\n";
}
$out['movements_max_id'] = (int)$t->query('SELECT COALESCE(MAX(id),0) FROM movements')->fetchColumn();
echo 'movements: id máximo ' . $out['movements_max_id'] . " (las correcciones solo agregan filas después de este id)\n";

$dir = dirname(__DIR__) . '/storage/logs';
@mkdir($dir, 0775, true);
$file = $dir . '/backup_trz_codes_' . date('Ymd_His') . '.json';
file_put_contents($file, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
echo "Respaldo: $file (" . round(filesize($file) / 1024) . " KB)\n";
