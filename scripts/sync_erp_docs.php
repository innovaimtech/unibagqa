<?php

declare(strict_types=1);

/**
 * Trae a la VPS las imágenes de las CC en producción (diseño y adjuntos de producción) desde el servidor del ERP,
 * para que la pantalla de flexo, serigrafía y demás las muestre sin esperar ni depender del ERP en ese momento.
 *
 * Lee el ERP SOLO en modo lectura y únicamente escribe archivos en src/Http/docs/{order,prod} y src/Http/docs_cache.
 * Los archivos que ya están no se vuelven a bajar.
 *
 *   php scripts/sync_erp_docs.php                 # CC con OT creadas en los últimos 120 días
 *   php scripts/sync_erp_docs.php --days=30
 *   php scripts/sync_erp_docs.php --cc=26-00988-1 # una CC
 *   php scripts/sync_erp_docs.php --dry-run       # solo informa qué falta
 *
 * En la VPS, programarlo cada 10 minutos (crontab del usuario del sitio):
 *   *\/10 * * * * php /RUTA/DEL/SITIO/scripts/sync_erp_docs.php --days=60 >> /RUTA/DEL/SITIO/storage/logs/sync-erp-docs.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    exit("Solo por línea de comandos.\n");
}

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/ErpDocumentFetcher.php';

Env::load(dirname(__DIR__) . '/.env');
date_default_timezone_set(trim((string)(Env::get('APP_TIMEZONE', 'America/Santiago') ?? '')) ?: 'America/Santiago');

$days = 120;
$cc = '';
$dry = false;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--days=')) {
        $days = max(1, (int)substr($arg, 7));
    } elseif (str_starts_with($arg, '--cc=')) {
        $cc = trim(substr($arg, 5));
    } elseif ($arg === '--dry-run') {
        $dry = true;
    }
}

// Evita dos corridas a la vez (cron cada pocos minutos con descargas lentas).
$lockFile = dirname(__DIR__) . '/storage/cache/sync-erp-docs.lock';
@mkdir(dirname($lockFile), 0775, true);
$lock = fopen($lockFile, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo date('Y-m-d H:i:s') . " otra sincronización sigue en curso; se omite.\n";
    exit(0);
}

$erp = Db::erpPdo();
if ($erp === null) {
    echo date('Y-m-d H:i:s') . " sin conexión al ERP.\n";
    exit(1);
}
$erp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$erp->exec('SET SESSION TRANSACTION READ ONLY');

$adj = 'o.req_prod_adjfile_0, o.req_prod_adjfile_1, o.req_prod_adjfile_2, o.req_prod_adjfile_3, o.req_prod_adjfile_4';
if ($cc !== '') {
    $st = $erp->prepare("SELECT o.id, o.req_number, $adj FROM orders o WHERE o.req_number = ?");
    $st->execute([$cc]);
} else {
    // CC con OT activa (prd_status > 0) creadas en el período.
    $st = $erp->prepare("SELECT o.id, o.req_number, $adj FROM orders o
        WHERE o.req_crtdat >= ? AND o.id IN (SELECT ph.prd_reqid FROM prod_header ph WHERE ph.prd_status > 0)");
    $st->execute([time() - $days * 86400]);
}
$orders = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$wanted = []; // hash => [carpeta, CC]
foreach ($orders as $o) {
    for ($i = 0; $i <= 4; $i++) {
        $h = trim((string)($o["req_prod_adjfile_$i"] ?? ''));
        if (ErpDocumentFetcher::validHash($h)) {
            $wanted[$h] = ['docs.prod', (string)$o['req_number']];
        }
    }
}
$ids = array_map(static fn($o) => (int)$o['id'], $orders);
foreach (array_chunk($ids, 500) as $chunk) {
    $in = implode(',', array_fill(0, count($chunk), '?'));
    $it = $erp->prepare("SELECT oi.req_id, oi.fab_design_imagehash FROM orders_items oi WHERE oi.req_id IN ($in) AND oi.fab_design_imagehash <> ''");
    $it->execute($chunk);
    $numbers = array_column($orders, 'req_number', 'id');
    foreach ($it->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $h = trim((string)$r['fab_design_imagehash']);
        if (ErpDocumentFetcher::validHash($h) && !isset($wanted[$h])) {
            $wanted[$h] = ['docs.order', (string)($numbers[(int)$r['req_id']] ?? '')];
        }
    }
}

$count = ['ok' => 0, 'downloaded' => 0, 'missing' => 0];
$missing = [];
foreach ($wanted as $hash => [$folder, $number]) {
    if (ErpDocumentFetcher::existing($hash) !== null) {
        $count['ok']++;
        continue;
    }
    if ($dry) {
        $count['missing']++;
        $missing[] = "$number $folder/$hash";
        continue;
    }
    $res = ErpDocumentFetcher::ensure($hash, $folder);
    $count[$res]++;
    if ($res === 'missing') {
        $missing[] = "$number $folder/$hash";
    } else {
        echo "  bajada $number $folder/$hash\n";
    }
}

printf("%s CC revisadas: %d · archivos: %d · ya estaban: %d · bajados: %d · no encontrados%s: %d · servidores: %s\n",
    date('Y-m-d H:i:s'), count($orders), count($wanted), $count['ok'], $count['downloaded'], $dry ? ' (simulación)' : '', $count['missing'],
    implode(' → ', ErpDocumentFetcher::baseUrls()));
foreach (array_slice($missing, 0, 30) as $m) {
    echo "  falta $m\n";
}
flock($lock, LOCK_UN);
exit(0);
