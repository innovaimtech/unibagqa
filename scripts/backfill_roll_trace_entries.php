<?php

declare(strict_types=1);

/**
 * Bobinas en bodega (rolls RECEIVED) que no tienen su código en traceability_warehouse_entries.
 * Sin ese código la bobina «no existe» para Bodega, Traspasos y Ajuste de códigos.
 *
 *   php scripts/backfill_roll_trace_entries.php           solo muestra (no escribe nada)
 *   php scripts/backfill_roll_trace_entries.php --apply   crea los códigos que faltan
 *
 * Solo crea códigos de bobinas que salen de las máquinas: impresas (ROLL-SE), manillas y cuerpos cortados
 * (ROLL-MAN, ROLL-BOL), empalmadas (ROLL-EMP) y sobrantes (-SOB-). Las demás se muestran pero no se tocan.
 * No toca el stock del ERP.
 */

if (PHP_SAPI !== 'cli') {
    exit("Solo por línea de comandos.\n");
}

require_once dirname(__DIR__) . '/src/bootstrap.php';

Env::load(dirname(__DIR__) . '/.env');
date_default_timezone_set(trim((string)(Env::get('APP_TIMEZONE', 'America/Santiago') ?? '')) ?: 'America/Santiago');

$apply = in_array('--apply', $argv, true);
$trz = Db::trzPdo();
if (!$apply) {
    $trz->exec('SET SESSION TRANSACTION READ ONLY');
}

/** Tipo de bobina por su código; null = no se crea su código con este script. */
function backfillKind(string $code): ?string
{
    $code = strtoupper($code);
    return match (true) {
        str_starts_with($code, 'ROLL-MAN-MERMA') => null,
        str_starts_with($code, 'ROLL-SE-') => 'Impresa (ROLL-SE)',
        str_starts_with($code, 'ROLL-MAN-') => 'Manilla (ROLL-MAN)',
        str_starts_with($code, 'ROLL-BOL-') => 'Cuerpo bolsa (ROLL-BOL)',
        str_starts_with($code, 'ROLL-EMP-') => 'Empalmada (ROLL-EMP)',
        str_contains($code, '-SOB-') => 'Sobrante (-SOB-)',
        default => null,
    };
}

// Las tablas tienen intercalaciones distintas: se cruza por la bobina (entity_id) y por el código en PHP.
$rolls = $trz->query("SELECT r.id, r.roll_code, r.created_at, r.warehouse_id FROM rolls r WHERE r.status = 'RECEIVED' ORDER BY r.id")->fetchAll(PDO::FETCH_ASSOC);
$byEntity = [];
$byCode = [];
foreach ($trz->query("SELECT entity_id, traceability_code FROM traceability_warehouse_entries WHERE entity_type = 'ROLL'") as $e) {
    $byEntity[(int)$e['entity_id']] = true;
    $byCode[strtoupper((string)$e['traceability_code'])] = true;
}

$missing = [];
$skipped = [];
foreach ($rolls as $r) {
    if (isset($byEntity[(int)$r['id']]) || isset($byCode[strtoupper((string)$r['roll_code'])])) {
        continue;
    }
    $kind = backfillKind((string)$r['roll_code']);
    if ($kind === null) {
        $skipped[] = $r;
    } else {
        $missing[$kind][] = $r;
    }
}

echo $apply ? "Creando códigos que faltan\n" : "Solo lectura (agrega --apply para crear los códigos)\n";
$total = 0;
foreach ($missing as $kind => $list) {
    $total += count($list);
    printf("\n%s: %d\n", $kind, count($list));
    foreach (array_slice($list, 0, 8) as $r) {
        printf("  %s  (%s)\n", $r['roll_code'], $r['created_at']);
    }
    if (count($list) > 8) {
        printf("  … y %d más\n", count($list) - 8);
    }
}
printf("\nTotal a crear: %d\n", $total);
if ($skipped !== []) {
    printf("Otras bobinas sin código (no se tocan): %d, p. ej. %s\n", count($skipped),
        implode(', ', array_map(static fn($r) => $r['roll_code'], array_slice($skipped, 0, 5))));
}

if (!$apply || $total === 0) {
    exit(0);
}
$ok = 0;
foreach ($missing as $list) {
    foreach ($list as $r) {
        if (RollTraceabilityEntry::syncSafe($trz, (int)$r['id'], 'Recuperación de códigos') > 0) {
            $ok++;
        } else {
            echo '  No se pudo: ' . $r['roll_code'] . "\n";
        }
    }
}
printf("Códigos creados: %d de %d\n", $ok, $total);
