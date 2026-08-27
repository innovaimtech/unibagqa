<?php

declare(strict_types=1);

require __DIR__ . '/../src/Env.php';
require __DIR__ . '/../src/Db.php';

Env::load(__DIR__ . '/../.env');

$pdo = Db::erpPdo();
$startTs = (int)($argv[1] ?? 1787695200);
$endTs = (int)($argv[2] ?? 1790373599);
$operator = (string)($argv[3] ?? '');

$sql = <<<SQL
SELECT
    pa.ag_equipo_id AS printer_no,
    ph.prd_reqid AS cost_center,
    ph.prd_number AS work_order_number,
    DATE(FROM_UNIXTIME(e.evt_crtdat)) AS event_date,
    ph.prd_desc AS erp_desc,
    TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))) AS operator_name,
    MAX(pa.ag_amount) AS requested_units,
    SUM(e.evt_amount) AS produced_units,
    SUM(e.evt_amount_metros_lineales) AS produced_linear_meters,
    SUM(e.evt_amount_metros_maquina) AS produced_machine_meters
FROM prod_worker_ot_events e
INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
INNER JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
INNER JOIN workers w ON w.id = pwi.win_wrkid
WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
  AND pa.ag_equipotype_id = 1
  AND e.evt_type = 'PRODUCTION'
  AND (:operator_name = '' OR TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))) = :operator_name_exact)
GROUP BY pa.ag_equipo_id, ph.prd_reqid, ph.prd_number, DATE(FROM_UNIXTIME(e.evt_crtdat)), operator_name, ph.prd_desc
ORDER BY event_date ASC, printer_no ASC, cost_center ASC, work_order_number ASC
SQL;

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':start_ts' => $startTs,
        ':end_ts' => $endTs,
        ':operator_name' => $operator,
        ':operator_name_exact' => $operator,
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "rows=" . count($rows) . PHP_EOL;
    if ($rows !== []) {
        echo json_encode($rows[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
} catch (PDOException $e) {
    echo "SQLSTATE=" . (string)($e->errorInfo[0] ?? $e->getCode() ?? '') . PHP_EOL;
    echo "DRIVER_CODE=" . (string)($e->errorInfo[1] ?? '') . PHP_EOL;
    echo "DRIVER_MSG=" . (string)($e->errorInfo[2] ?? $e->getMessage() ?? '') . PHP_EOL;
    exit(1);
}
