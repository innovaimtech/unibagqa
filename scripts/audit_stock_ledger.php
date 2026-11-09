<?php

declare(strict_types=1);

/**
 * Revisa el kardex del sistema y las cajas de bolsas sin escribir nada. Termina con código 1 si encuentra algo.
 *
 *   php scripts/audit_stock_ledger.php
 *   php scripts/audit_stock_ledger.php --since="2026-11-08 00:00"
 */

if (PHP_SAPI !== 'cli') {
    exit("Solo por línea de comandos.\n");
}

require_once dirname(__DIR__) . '/src/bootstrap.php';

Env::load(dirname(__DIR__) . '/.env');
date_default_timezone_set(trim((string)(Env::get('APP_TIMEZONE', 'America/Santiago') ?? '')) ?: 'America/Santiago');

$since = date('Y-m-d 00:00', strtotime('-30 days'));
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
$trz = null;
try {
    $trz = Db::trzPdo();
    $trz?->exec('SET SESSION TRANSACTION READ ONLY');
} catch (Throwable) {
    $trz = null;
}
$result = (new StockLedgerAudit($erp, $trz))->run($sinceTs);

$titles = [
    'docs_a_medias' => 'Documentos que quedaron a medias (reintentar desde la pantalla que los creó)',
    'cajas_sin_stock' => 'Cajas de bolsas con más bolsas que el stock del ERP',
    'movimientos_pendientes' => 'Bolsas usadas o devueltas pendientes en el Pulpo',
    'stock_fuera_de_kardex' => 'Stock que cambió sin movimiento en el kardex',
];
$total = 0;
echo 'Revisión desde ' . date('d/m/Y H:i', $sinceTs) . "\n";
foreach ($titles as $key => $title) {
    $rows = $result[$key];
    $total += count($rows);
    echo "\n$title: " . count($rows) . "\n";
    foreach ($rows as $row) {
        if (isset($row['fecha'])) {
            $row['fecha'] = date('d/m/Y H:i', (int)$row['fecha']);
        }
        echo '  ' . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
}
echo "\nTotal: $total\n";
exit($total === 0 ? 0 : 1);
