<?php

declare(strict_types=1);

require __DIR__ . '/../src/Env.php';
require __DIR__ . '/../src/Db.php';
require __DIR__ . '/erp_production_support.php';

Env::load(__DIR__ . '/../.env');

$monthKey = trim((string)($argv[1] ?? ''));
if ($monthKey === '') {
    $defaultMonthKey = date('Y-m');
    $today = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
    if ((int)$today->format('j') >= 26) {
        $defaultMonthKey = $today->modify('+1 month')->format('Y-m');
    }
    $monthKey = $defaultMonthKey;
}
if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
    fwrite(STDERR, "Mes inválido. Usa YYYY-MM\n");
    exit(1);
}

$erp = Db::erpPdo();
ensureErpProductionSchema($erp);
$operator = ensureErpOperatorContext($erp);

$period = resolveBonusPeriodByMonthFinal($monthKey);
$startTs = $period['start_ts'];
$suffix = date('ymdHis');
$reqBase = (int)date('ymdHis');

$amounts = [
    9000,
    12500,
    15000,
    22000,
    38000,
    52000,
    76000,
    98000,
    125000,
    175000,
];
$descs = [
    'FUNERARIA HOGAR DE CRISTO BOU 25X30X8',
    'PANIFICADORA LA PLAZA PRO 45X40X12',
    'SOCOVFAR MAICAO BOU 38X38X18',
    'ERAL CHILE BOU 37X40X12',
    'DIMERC REUTILIZA PRO 45X40X12',
    'WALMART GATOS COMIENDO PRO 45X40X12',
    'JKR 58 MARKET PRO 40X40X12',
    'EASY XXL BOU 44X39X23',
    'UNIBAG FRANJAS COLORES PRO 40X40X12',
    'UNIBAG INDIVIDUAL IND 45X33',
];

$erp->beginTransaction();
try {
    $created = [];
    foreach ($amounts as $i => $amount) {
        $date = $startTs + (($i + 1) * 86400) + 3600;
        $plan = [
            'number' => 'FLEXO-DEMO-' . ($i + 1) . '-' . $suffix,
            'desc' => $descs[$i] ?? ('FLEXO DEMO PRO 40X40X12 #' . ($i + 1)),
            'req_id' => (string)($reqBase + $i + 1),
            'planta_id' => 1,
            'amount' => $amount,
            'machine_id' => 200 + $i,
            'machine_type_id' => 1,
            'date' => $date,
            'active' => 0,
            'finished' => true,
            'ag_order' => $i + 1,
        ];

        $headerId = createFlexoProdHeader($erp, $plan);
        $agendaId = createFlexoProdAgenda($erp, $headerId, $plan);
        $workerInitId = createFlexoProdWorkerInit($erp, $operator, $plan);
        $workerOtId = createFlexoProdWorkerOt($erp, $agendaId, $workerInitId, $plan);
        createFlexoProdWorkerEvent($erp, $workerOtId, $plan);

        $created[] = [
            'prod_header_id' => $headerId,
            'prod_agenda_id' => $agendaId,
            'prod_worker_ot_id' => $workerOtId,
            'prd_number' => $plan['number'],
            'amount' => $amount,
        ];
    }

    $erp->commit();
    echo json_encode([
        'ok' => true,
        'month' => $monthKey,
        'period' => $period,
        'rows' => $created,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    $erp->rollBack();
    throw $e;
}

function resolveBonusPeriodByMonthFinal(string $monthKey): array
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $monthKey . '-01', new DateTimeZone(date_default_timezone_get()));
    if (!$dt) {
        $now = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
        $dt = $now->setDate((int)$now->format('Y'), (int)$now->format('n'), 1);
    }
    $start = $dt->modify('-1 month')->setDate((int)$dt->modify('-1 month')->format('Y'), (int)$dt->modify('-1 month')->format('n'), 26)->setTime(0, 0, 0);
    $end = $dt->setDate((int)$dt->format('Y'), (int)$dt->format('n'), 25)->setTime(23, 59, 59);
    return [
        'start_date' => $start->format('Y-m-d'),
        'end_date' => $end->format('Y-m-d'),
        'start_ts' => $start->getTimestamp(),
        'end_ts' => $end->getTimestamp(),
    ];
}

function createFlexoProdHeader(PDO $pdo, array $plan): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO prod_header (
            prd_crtdat, prd_crtusr, prd_status, prd_desc, prd_plantaid, prd_reqid, prd_upddat, prd_updusr, prd_number
         ) VALUES (
            :created_at, 1, :status, :description, :planta_id, :req_id, :updated_at, 1, :prd_number
         )'
    );
    $stmt->execute([
        ':created_at' => (int)$plan['date'],
        ':status' => 0,
        ':description' => (string)$plan['desc'],
        ':planta_id' => (int)$plan['planta_id'],
        ':req_id' => (string)$plan['req_id'],
        ':updated_at' => (int)$plan['date'],
        ':prd_number' => (string)$plan['number'],
    ]);
    return (int)$pdo->lastInsertId();
}

function createFlexoProdAgenda(PDO $pdo, int $headerId, array $plan): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO prod_agenda (
            ag_date, ag_date_stamp, ag_equipo_id, ag_equipotype_id, ag_amount, ag_prdid, ag_reqid, ag_plantaid,
            ag_crtdat, ag_crtusr, ag_status, ag_active, ag_order
         ) VALUES (
            :ag_date, :ag_date_stamp, :machine_id, :machine_type_id, :amount, :header_id, :req_id, :planta_id,
            :created_at, 1, 0, 0, :ag_order
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
        ':ag_order' => (int)($plan['ag_order'] ?? 1),
    ]);
    return (int)$pdo->lastInsertId();
}

function createFlexoProdWorkerInit(PDO $pdo, array $operator, array $plan): int
{
    $endDate = ((int)$plan['date'] + 3600);
    $stmt = $pdo->prepare(
        'INSERT INTO prod_worker_init (
            win_crtdat, win_enddat, win_wrkid, win_status, win_plantaid, win_equipoid, win_ass_id, win_day, win_month, win_year
         ) VALUES (
            :created_at, :end_at, :worker_id, 0, :planta_id, :machine_id, 0, :day_value, :month_value, :year_value
         )'
    );
    $stmt->execute([
        ':created_at' => (int)$plan['date'],
        ':end_at' => $endDate,
        ':worker_id' => (int)$operator['worker_id'],
        ':planta_id' => (int)$plan['planta_id'],
        ':machine_id' => (int)$plan['machine_id'],
        ':day_value' => (int)date('j', (int)$plan['date']),
        ':month_value' => (int)date('n', (int)$plan['date']),
        ':year_value' => (int)date('Y', (int)$plan['date']),
    ]);
    return (int)$pdo->lastInsertId();
}

function createFlexoProdWorkerOt(PDO $pdo, int $agendaId, int $workerInitId, array $plan): int
{
    $endDate = ((int)$plan['date'] + 3600);
    $stmt = $pdo->prepare(
        'INSERT INTO prod_worker_ot (
            wok_ag_id, wok_init_id, wok_crtdat, wok_enddat, wok_status
         ) VALUES (
            :agenda_id, :worker_init_id, :created_at, :end_at, 2
         )'
    );
    $stmt->execute([
        ':agenda_id' => $agendaId,
        ':worker_init_id' => $workerInitId,
        ':created_at' => (int)$plan['date'],
        ':end_at' => $endDate,
    ]);
    return (int)$pdo->lastInsertId();
}

function createFlexoProdWorkerEvent(PDO $pdo, int $workerOtId, array $plan): int
{
    $eventAt = ((int)$plan['date'] + 1800);
    $endAt = ((int)$plan['date'] + 3600);
    $amount = (float)$plan['amount'];
    $bobbinKg = round($amount / 1000, 3);

    $stmt = $pdo->prepare(
        'INSERT INTO prod_worker_ot_events (
            evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments,
            evt_equipo_mantid, evt_pause_id, prod_bobina_kg, prod_seri_color, prod_seri_converted_amt,
            evt_medida_fromid, evt_medida_toid, evt_ubim_id, evt_amount_metros_maquina, evt_amount_metros_lineales, evt_metrotype
         ) VALUES (
            :worker_ot_id, :amount, :created_at, :end_at, 2, :event_type, :comments,
            0, 0, :bobbin_kg, "", 0,
            0, 0, 0, :metros_maquina, :metros_lineales, "ML"
         )'
    );
    $stmt->execute([
        ':worker_ot_id' => $workerOtId,
        ':amount' => $amount,
        ':created_at' => $eventAt,
        ':end_at' => $endAt,
        ':event_type' => 'PRODUCTION',
        ':comments' => 'Seed flexo bonus',
        ':bobbin_kg' => $bobbinKg,
        ':metros_maquina' => $amount,
        ':metros_lineales' => $amount,
    ]);

    return (int)$pdo->lastInsertId();
}

