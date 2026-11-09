<?php

declare(strict_types=1);

/**
 * Revisa los embalajes de Corte y Sellado sin escribir nada. Termina con código 1 si encuentra algo.
 *
 *   php scripts/audit_cys_packaging.php
 *   php scripts/audit_cys_packaging.php --since="2026-11-06 11:28"
 *
 * --since: cierres desde esa fecha deben tener ingreso automático. Los anteriores los ajustó bodega.
 */

if (PHP_SAPI !== 'cli') {
    exit("Solo por línea de comandos.\n");
}

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/CysPackagingAudit.php';

Env::load(dirname(__DIR__) . '/.env');
date_default_timezone_set(trim((string)(Env::get('APP_TIMEZONE', 'America/Santiago') ?? '')) ?: 'America/Santiago');

$since = '2026-11-06 11:28'; // Hora en que se recuperaron los 12 embalajes.
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--since=')) {
        $since = substr($arg, 8);
    }
}
$sinceTs = strtotime($since);
if ($sinceTs === false) {
    exit("Fecha no válida: $since\n");
}

$erp = Db::erpPdo();
$erp->exec('SET SESSION TRANSACTION READ ONLY');
$result = (new CysPackagingAudit($erp))->run($sinceTs, time());

$titles = [
    'sin_mover' => 'Embalajes cerrados que siguen contando como sellado',
    'stock' => 'Embalajes con ingreso automático faltante o distinto',
    'traspasos' => 'Líneas mixtas con traspaso distinto (sin traspaso es válido solo si ya estaba en destino)',
    'abiertos' => 'Embalajes abiertos hace más de un día',
];
$total = 0;
echo 'Revisión desde ' . date('d/m/Y H:i', $sinceTs) . "\n";
foreach ($titles as $key => $title) {
    $rows = $result[$key];
    $total += count($rows);
    echo "\n$title: " . count($rows) . "\n";
    foreach ($rows as $row) {
        foreach (['cierre', 'inicio'] as $dateField) {
            if (isset($row[$dateField])) {
                $row[$dateField] = date('d/m/Y H:i', (int)$row[$dateField]);
            }
        }
        echo '  ' . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
}
echo "\nTotal: $total\n";
exit($total === 0 ? 0 : 1);
