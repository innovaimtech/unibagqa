<?php

declare(strict_types=1);

require __DIR__ . '/../src/Env.php';
require __DIR__ . '/../src/Db.php';
require __DIR__ . '/erp_production_support.php';

Env::load(__DIR__ . '/../.env');

$erp = Db::erpPdo();
ensureErpProductionSchema($erp);

$rows = $erp->query(
    "SELECT
        ph.id AS header_id,
        ph.prd_number,
        pa.id AS agenda_id,
        pa.ag_amount AS requested_units,
        e.evt_amount AS produced_units
     FROM prod_header ph
     INNER JOIN prod_agenda pa ON pa.ag_prdid = ph.id
     INNER JOIN prod_worker_ot pwo ON pwo.wok_ag_id = pa.id
     INNER JOIN prod_worker_ot_events e ON e.evt_prod_worker_otid = pwo.id AND e.evt_type = 'PRODUCTION'
     WHERE ph.prd_number LIKE 'FLEXO-DEMO-%'
     ORDER BY ph.id DESC
     LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);

if ($rows === false || $rows === []) {
    echo json_encode(['ok' => false, 'message' => 'No se encontraron registros FLEXO-DEMO para actualizar.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}

$percents = [0.00, 0.02, 0.05, 0.03, -0.01, 0.04, 0.06, -0.02, 0.01, 0.07];
$erp->beginTransaction();
try {
    $updates = [];
    $stmt = $erp->prepare('UPDATE prod_agenda SET ag_amount = :requested WHERE id = :id');
    foreach ($rows as $idx => $row) {
        $agendaId = (int)($row['agenda_id'] ?? 0);
        $produced = (float)($row['produced_units'] ?? 0);
        if ($agendaId <= 0 || $produced <= 0) {
            continue;
        }
        $p = (float)($percents[$idx] ?? 0.0);
        $requested = round($produced * (1.0 + $p), 3);
        if ($requested <= 0) {
            $requested = $produced;
        }
        $stmt->execute([
            ':requested' => $requested,
            ':id' => $agendaId,
        ]);
        $updates[] = [
            'prd_number' => (string)($row['prd_number'] ?? ''),
            'agenda_id' => $agendaId,
            'produced_units' => $produced,
            'requested_units_old' => (float)($row['requested_units'] ?? 0),
            'requested_units_new' => $requested,
            'merma_percent_simulated' => $p * 100.0,
        ];
    }
    $erp->commit();
    echo json_encode(['ok' => true, 'updated' => $updates], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    $erp->rollBack();
    throw $e;
}

