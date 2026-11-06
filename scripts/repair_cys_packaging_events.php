<?php

declare(strict_types=1);

/**
 * Recupera la producción de embalaje de Corte y Sellado que quedó en la OT de la selladora desde el 3 nov 2026.
 *
 * Solo pasa la producción a la máquina Embalaje, en su fecha real. No toca bodega: esos embalajes ya los ajustó bodega.
 *
 * Uso:
 *   php scripts/repair_cys_packaging_events.php                     Muestra lo que haría, sin escribir.
 *   php scripts/repair_cys_packaging_events.php --apply             Aplica. Se puede repetir sin duplicar.
 *   php scripts/repair_cys_packaging_events.php --since=2026-11-03  Cambia la fecha de inicio.
 *
 * Al aplicar guarda los valores anteriores en storage/logs/repair_cys_packaging_*.json.
 */

if (PHP_SAPI !== 'cli') {
    exit("Solo por línea de comandos.\n");
}

require_once dirname(__DIR__) . '/src/bootstrap.php';

Env::load(dirname(__DIR__) . '/.env');
date_default_timezone_set(trim((string)(Env::get('APP_TIMEZONE', 'America/Santiago') ?? '')) ?: 'America/Santiago');

$apply = in_array('--apply', $argv, true);
$since = '2026-11-03';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--since=')) {
        $since = substr($arg, 8);
    }
}
$sinceTs = strtotime($since . ' 00:00:00');
if ($sinceTs === false) {
    exit("Fecha no válida: $since\n");
}

$erp = Db::erpPdo();
$candidates = static function () use ($erp, $sinceTs): array {
    $stmt = $erp->prepare("SELECT e.id, e.evt_prod_worker_otid AS ot, e.evt_amount, e.evt_crtdat, e.evt_enddat, a.ag_reqid, o.req_number,
            TRIM(CONCAT(COALESCE(w.wrk_firstname,''),' ',COALESCE(w.wrk_lastname,''))) AS operator_name,
            q.equipo_name, (SELECT COUNT(*) FROM prod_worker_embalajes pe WHERE pe.req_v2refid = e.id) AS details
        FROM prod_worker_ot_events e
        JOIN prod_worker_ot ot ON ot.id = e.evt_prod_worker_otid
        JOIN prod_agenda a ON a.id = ot.wok_ag_id
        LEFT JOIN orders o ON o.id = a.ag_reqid
        LEFT JOIN prod_worker_init i ON i.id = ot.wok_init_id
        LEFT JOIN workers w ON w.id = i.win_wrkid
        LEFT JOIN equipo q ON q.id = i.win_equipoid
        WHERE e.evt_type = 'prod' AND e.overrideembalaje_act = 1 AND e.overrideembalaje_selladora_evtrefid = 0
          AND e.evt_status > 0 AND e.evt_enddat > 0 AND e.evt_amount > 0
          AND a.ag_equipotype_id = 8 AND e.evt_crtdat >= ?
        ORDER BY e.id");
    $stmt->execute([$sinceTs]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
};

$rows = $candidates();
$totals = [];
foreach ($rows as $row) {
    $totals[$row['operator_name']] = ($totals[$row['operator_name']] ?? 0) + (float)$row['evt_amount'];
    printf("Evento %d | %s | %s | CC %s | %s | %s unidades | OT %d%s\n",
        $row['id'], date('d/m/Y H:i', (int)$row['evt_crtdat']), $row['operator_name'], (string)$row['req_number'], $row['equipo_name'],
        number_format((float)$row['evt_amount'], 0, ',', '.'), $row['ot'], (int)$row['details'] === 0 ? ' | SIN DETALLE DE CAJAS' : '');
}
echo "\nTotal: " . count($rows) . ' embalajes, ' . number_format(array_sum($totals), 0, ',', '.') . " unidades\n";
foreach ($totals as $name => $units) {
    echo '  ' . $name . ': ' . number_format($units, 0, ',', '.') . "\n";
}

if (!$apply) {
    echo "\nVista previa: no se escribió nada. Usa --apply para aplicar.\n";
    exit(0);
}
if ($rows === []) {
    echo "\nNada que recuperar.\n";
    exit(0);
}

$logFile = dirname(__DIR__) . '/storage/logs/repair_cys_packaging_' . date('Ymd_His') . '.json';
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0775, true);
}
if (file_put_contents($logFile, json_encode(['applied_at' => date('c'), 'before' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    exit("No se pudo guardar el respaldo en $logFile. No se aplicó nada.\n");
}
echo "\nRespaldo: $logFile\n";

$service = new ProductionService($erp, null);
$moved = 0;
$errors = 0;
$results = [];
foreach ($rows as $row) {
    try {
        $result = $service->linkCysPackagingEvent((int)$row['id']);
        $results[] = $result;
        if (($result['moved'] ?? false) === true) {
            $moved++;
            echo 'Movido evento ' . $row['id'] . ' a la OT de Embalaje ' . $result['packaging_ot'] . "\n";
        } else {
            echo 'Sin cambios en el evento ' . $row['id'] . "\n";
        }
    } catch (Throwable $e) {
        $errors++;
        echo 'ERROR en el evento ' . $row['id'] . ': ' . $e->getMessage() . "\n";
    }
}
file_put_contents($logFile, json_encode(['applied_at' => date('c'), 'before' => $rows, 'results' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$pending = count($candidates());
echo "\nMovidos: $moved. Errores: $errors. Pendientes: $pending.\n";
exit($errors === 0 && $pending === 0 ? 0 : 1);
