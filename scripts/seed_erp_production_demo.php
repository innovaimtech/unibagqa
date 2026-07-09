<?php

declare(strict_types=1);

require __DIR__ . '/../src/Env.php';
require __DIR__ . '/../src/Db.php';
require __DIR__ . '/erp_production_support.php';

Env::load(__DIR__ . '/../.env');

$erp = Db::erpPdo();
$tables = ensureErpProductionSchema($erp);
$suffix = date('ymdHis');
$now = time();
$reqBase = (int)date('ymdHis');
$operator = ensureErpOperatorContext($erp);

$plans = [
    [
        'number' => 'PRD-PLAN-A-' . $suffix,
        'desc' => 'BOLSA 45X60 IMPRESA - PLANIFICADA',
        'req_id' => $reqBase + 1,
        'planta_id' => 1,
        'amount' => 12000,
        'machine_id' => 101,
        'machine_type_id' => 1,
        'date' => $now + 3600,
        'active' => 0,
        'with_execution' => false,
        'finished' => false,
    ],
    [
        'number' => 'PRD-PLAN-B-' . $suffix,
        'desc' => 'BOLSA 35X45 NATURAL - PLANIFICADA',
        'req_id' => $reqBase + 2,
        'planta_id' => 1,
        'amount' => 9500,
        'machine_id' => 104,
        'machine_type_id' => 2,
        'date' => $now + 5400,
        'active' => 0,
        'with_execution' => false,
        'finished' => false,
    ],
    [
        'number' => 'PRD-ACT-A-' . $suffix,
        'desc' => 'BOLSA 50X70 IMPRESA - EN PRODUCCION',
        'req_id' => $reqBase + 3,
        'planta_id' => 1,
        'amount' => 18000,
        'machine_id' => 102,
        'machine_type_id' => 1,
        'date' => $now,
        'active' => 1,
        'with_execution' => true,
        'finished' => false,
    ],
    [
        'number' => 'PRD-ACT-B-' . $suffix,
        'desc' => 'BOLSA 60X90 RETAIL - EN PRODUCCION',
        'req_id' => $reqBase + 4,
        'planta_id' => 1,
        'amount' => 15500,
        'machine_id' => 103,
        'machine_type_id' => 2,
        'date' => $now - 1800,
        'active' => 1,
        'with_execution' => true,
        'finished' => false,
    ],
    [
        'number' => 'PRD-ACT-C-' . $suffix,
        'desc' => 'BOLSA 70X100 SACO - EN AJUSTE',
        'req_id' => $reqBase + 5,
        'planta_id' => 1,
        'amount' => 13200,
        'machine_id' => 105,
        'machine_type_id' => 3,
        'date' => $now - 2700,
        'active' => 1,
        'with_execution' => true,
        'finished' => false,
    ],
    [
        'number' => 'PRD-END-A-' . $suffix,
        'desc' => 'BOLSA 60X90 IMPRESA - EJECUTADA',
        'req_id' => $reqBase + 6,
        'planta_id' => 1,
        'amount' => 9000,
        'machine_id' => 106,
        'machine_type_id' => 2,
        'date' => $now - 7200,
        'active' => 0,
        'with_execution' => true,
        'finished' => true,
    ],
    [
        'number' => 'PRD-END-B-' . $suffix,
        'desc' => 'BOLSA 40X50 IMPRESA - EJECUTADA',
        'req_id' => $reqBase + 7,
        'planta_id' => 1,
        'amount' => 11200,
        'machine_id' => 107,
        'machine_type_id' => 1,
        'date' => $now - 9600,
        'active' => 0,
        'with_execution' => true,
        'finished' => true,
    ],
    [
        'number' => 'PRD-END-C-' . $suffix,
        'desc' => 'BOLSA 25X35 TROQUELADA - EJECUTADA',
        'req_id' => $reqBase + 8,
        'planta_id' => 1,
        'amount' => 8700,
        'machine_id' => 108,
        'machine_type_id' => 3,
        'date' => $now - 12600,
        'active' => 0,
        'with_execution' => true,
        'finished' => true,
    ],
];

$erp->beginTransaction();
try {
    $result = [];
    $plannedCount = 0;
    $activeCount = 0;
    $finishedCount = 0;
    foreach ($plans as $index => $plan) {
        $plan['ag_order'] = $index + 1;
        $headerId = createProdHeader($erp, $plan);
        $agendaId = createProdAgenda($erp, $headerId, $plan);
        $workerInitId = null;
        $workerOtId = null;

        if ($plan['with_execution'] === true) {
            $workerInitId = createProdWorkerInit($erp, $operator, $plan);
            $workerOtId = createProdWorkerOt($erp, $agendaId, $workerInitId, $plan);
            createProdWorkerEvent($erp, $workerOtId, $plan, $plan['finished'] === true ? 'COMPLETE' : 'START');
            if ($plan['finished'] === true) {
                createProdWorkerEvent($erp, $workerOtId, $plan, 'PRODUCTION');
            }
        }

        $result[] = [
            'prod_header_id' => $headerId,
            'prod_agenda_id' => $agendaId,
            'prod_worker_init_id' => $workerInitId,
            'prod_worker_ot_id' => $workerOtId,
            'prd_number' => $plan['number'],
            'prd_desc' => $plan['desc'],
        ];

        if ($plan['finished'] === true) {
            $finishedCount++;
        } elseif ($plan['with_execution'] === true) {
            $activeCount++;
        } else {
            $plannedCount++;
        }
    }

    $erp->commit();
    echo json_encode([
        'ok' => true,
        'tables' => $tables,
        'worker_id' => $operator['worker_id'],
        'user_id' => $operator['user_id'],
        'summary' => [
            'planned' => $plannedCount,
            'active' => $activeCount,
            'finished' => $finishedCount,
            'total' => count($result),
        ],
        'plans' => $result,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    $erp->rollBack();
    throw $e;
}

function createProdHeader(PDO $pdo, array $plan): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO prod_header (
            prd_crtdat, prd_crtusr, prd_status, prd_desc, prd_plantaid, prd_reqid, prd_upddat, prd_updusr, prd_number
         ) VALUES (
            :created_at, 1, :status, :description, :planta_id, :req_id, :updated_at, 1, :prd_number
         )'
    );
    $status = $plan['active'] === 1 ? 1 : 0;
    $stmt->execute([
        ':created_at' => (int)$plan['date'],
        ':status' => $status,
        ':description' => (string)$plan['desc'],
        ':planta_id' => (int)$plan['planta_id'],
        ':req_id' => (string)$plan['req_id'],
        ':updated_at' => (int)$plan['date'],
        ':prd_number' => (string)$plan['number'],
    ]);

    return (int)$pdo->lastInsertId();
}

function createProdAgenda(PDO $pdo, int $headerId, array $plan): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO prod_agenda (
            ag_date, ag_date_stamp, ag_equipo_id, ag_equipotype_id, ag_amount, ag_prdid, ag_reqid, ag_plantaid,
            ag_crtdat, ag_crtusr, ag_status, ag_active, ag_order
         ) VALUES (
            :ag_date, :ag_date_stamp, :machine_id, :machine_type_id, :amount, :header_id, :req_id, :planta_id,
            :created_at, 1, :status, :active, :ag_order
         )'
    );
    $stmt->execute([
        ':ag_date' => (int)$plan['date'],
        ':ag_date_stamp' => (int)$plan['date'],
        ':machine_id' => (int)$plan['machine_id'],
        ':machine_type_id' => (int)$plan['machine_type_id'],
        ':amount' => (float)$plan['amount'],
        ':header_id' => $headerId,
        ':req_id' => (string)$plan['req_id'],
        ':planta_id' => (int)$plan['planta_id'],
        ':created_at' => (int)$plan['date'],
        ':status' => $plan['active'] === 1 ? 1 : 0,
        ':active' => (int)$plan['active'],
        ':ag_order' => (int)($plan['ag_order'] ?? 1),
    ]);

    return (int)$pdo->lastInsertId();
}

function createProdWorkerInit(PDO $pdo, array $operator, array $plan): int
{
    $isFinished = $plan['finished'] === true;
    $endDate = $isFinished ? ((int)$plan['date'] + 3600) : 0;
    $stmt = $pdo->prepare(
        'INSERT INTO prod_worker_init (
            win_crtdat, win_enddat, win_wrkid, win_status, win_plantaid, win_equipoid, win_ass_id, win_day, win_month, win_year
         ) VALUES (
            :created_at, :end_at, :worker_id, :status, :planta_id, :machine_id, 0, :day_value, :month_value, :year_value
         )'
    );
    $stmt->execute([
        ':created_at' => (int)$plan['date'],
        ':end_at' => $endDate,
        ':worker_id' => $operator['worker_id'],
        ':status' => $isFinished ? 0 : 1,
        ':planta_id' => (int)$plan['planta_id'],
        ':machine_id' => (int)$plan['machine_id'],
        ':day_value' => (int)date('j', (int)$plan['date']),
        ':month_value' => (int)date('n', (int)$plan['date']),
        ':year_value' => (int)date('Y', (int)$plan['date']),
    ]);

    return (int)$pdo->lastInsertId();
}

function createProdWorkerOt(PDO $pdo, int $agendaId, int $workerInitId, array $plan): int
{
    $isFinished = $plan['finished'] === true;
    $endDate = $isFinished ? ((int)$plan['date'] + 3600) : 0;
    $stmt = $pdo->prepare(
        'INSERT INTO prod_worker_ot (
            wok_ag_id, wok_init_id, wok_crtdat, wok_enddat, wok_status
         ) VALUES (
            :agenda_id, :worker_init_id, :created_at, :end_at, :status
         )'
    );
    $stmt->execute([
        ':agenda_id' => $agendaId,
        ':worker_init_id' => $workerInitId,
        ':created_at' => (int)$plan['date'],
        ':end_at' => $endDate,
        ':status' => $isFinished ? 2 : 1,
    ]);

    return (int)$pdo->lastInsertId();
}

function createProdWorkerEvent(PDO $pdo, int $workerOtId, array $plan, string $eventType): int
{
    $isFinished = $plan['finished'] === true;
    $eventAt = $eventType === 'PRODUCTION' ? ((int)$plan['date'] + 1800) : (int)$plan['date'];
    $endAt = $eventType === 'PRODUCTION' && $isFinished ? ((int)$plan['date'] + 3600) : 0;
    $amount = $eventType === 'START' ? 0 : (float)$plan['amount'];
    $bobbinKg = $eventType === 'START' ? 0 : round(((float)$plan['amount']) / 1000, 3);

    $stmt = $pdo->prepare(
        'INSERT INTO prod_worker_ot_events (
            evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments,
            evt_equipo_mantid, evt_pause_id, prod_bobina_kg, prod_seri_color, prod_seri_converted_amt,
            evt_medida_fromid, evt_medida_toid, evt_ubim_id, evt_amount_metros_maquina, evt_amount_metros_lineales, evt_metrotype
         ) VALUES (
            :worker_ot_id, :amount, :created_at, :end_at, :status, :event_type, :comments,
            :equipo_mant_id, :pause_id, :bobina_kg, :seri_color, :seri_converted_amt,
            :medida_from_id, :medida_to_id, :ubim_id, :metros_maquina, :metros_lineales, :metro_type
         )'
    );
    $stmt->execute([
        ':worker_ot_id' => $workerOtId,
        ':amount' => $amount,
        ':created_at' => $eventAt,
        ':end_at' => $endAt,
        ':status' => $eventType === 'START' ? 1 : 2,
        ':event_type' => $eventType,
        ':comments' => 'Seed demo ' . $eventType,
        ':equipo_mant_id' => 0,
        ':pause_id' => 0,
        ':bobina_kg' => $bobbinKg,
        ':seri_color' => '',
        ':seri_converted_amt' => 0,
        ':medida_from_id' => 0,
        ':medida_to_id' => 0,
        ':ubim_id' => 0,
        ':metros_maquina' => $amount,
        ':metros_lineales' => $amount,
        ':metro_type' => 'ML',
    ]);

    return (int)$pdo->lastInsertId();
}
