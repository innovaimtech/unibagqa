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

$period = resolveBonusPeriodByMonthFinal($monthKey);
$startTs = (int)$period['start_ts'];
$suffix = date('ymdHis');
$reqBase = (int)date('ymdHis');

$operators = [
    ensureErpNamedOperatorContext($erp, 'jg.demo', 'JG', 'Operador', 'JG-DEMO'),
    ensureErpNamedOperatorContext($erp, 'le.demo', 'LE', 'Operador', 'LE-DEMO'),
    ensureErpNamedOperatorContext($erp, 'dr.demo', 'DR', 'Operador', 'DR-DEMO'),
    ensureErpNamedOperatorContext($erp, 'sh.demo', 'SH', 'Operador', 'SH-DEMO'),
];

$amounts = [
    3100,
    2049,
    15301,
    1039,
    1066,
    2042,
    13204,
    5104,
    10241,
    7349,
];
$descs = [
    'FUNERARIA HOGAR DE CRISTO BOU 25X30X8',
    'PANIFICADORA LA PLAZA PRO 45X40X12',
    'SOCOFAR MAICAO BOU 38X38X18',
    'ERAL CHILE BOU 37X40X12',
    'ERAL CHILE BOU 25X30X8',
    'DIMERC REUTILIZA PRO 45X40X12',
    'WALMART GATOS COMIENDO PRO 45X40X12',
    'JKR 58 MARKET PRO 40X40X12',
    'EASY XXL BOU 44X39X23',
    'COMERCIAL CAUPOLICAN BOU 48X40X20',
];
$mermaPercents = [0.00, 0.03, -0.02, 0.05, 0.01, 0.04, -0.01, 0.06, 0.02, 0.07];

$erp->beginTransaction();
try {
    $created = [];
    for ($i = 0; $i < 10; $i++) {
        $operator = $operators[$i % count($operators)];
        $produced = (float)$amounts[$i];
        $p = (float)$mermaPercents[$i];
        $requested = round($produced * (1.0 + $p), 3);
        if ($requested <= 0) {
            $requested = $produced;
        }

        $dayOffset = ($i + 1) * 86400;
        $date = $startTs + $dayOffset + 5400;

        $plan = [
            'number' => 'FLEXO-OPS-' . ($i + 1) . '-' . $suffix,
            'desc' => $descs[$i] ?? ('FLEXO OPS DEMO #' . ($i + 1)),
            'req_id' => (string)($reqBase + 100 + $i + 1),
            'planta_id' => 1,
            'requested_amount' => $requested,
            'produced_amount' => $produced,
            'machine_id' => 200 + ($i % 10),
            'machine_type_id' => 1,
            'date' => $date,
            'ag_order' => 50 + $i,
        ];

        $headerId = createFlexoProdHeader($erp, $plan);
        $agendaId = createFlexoProdAgenda($erp, $headerId, $plan);
        $workerInitId = createFlexoProdWorkerInit($erp, $operator, $plan);
        $workerOtId = createFlexoProdWorkerOt($erp, $agendaId, $workerInitId, $plan);
        createFlexoProdWorkerEvent($erp, $workerOtId, $plan);

        $created[] = [
            'prd_number' => $plan['number'],
            'operator' => trim($operator['first_name'] . ' ' . $operator['last_name']),
            'requested_units' => $requested,
            'produced_units' => $produced,
            'merma_percent_simulated' => $p * 100.0,
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
    $previousMonth = $dt->modify('-1 month');
    $start = $previousMonth->setDate((int)$previousMonth->format('Y'), (int)$previousMonth->format('n'), 26)->setTime(0, 0, 0);
    $end = $dt->setDate((int)$dt->format('Y'), (int)$dt->format('n'), 25)->setTime(23, 59, 59);
    return [
        'start_date' => $start->format('Y-m-d'),
        'end_date' => $end->format('Y-m-d'),
        'start_ts' => $start->getTimestamp(),
        'end_ts' => $end->getTimestamp(),
    ];
}

function ensureErpNamedOperatorContext(PDO $pdo, string $login, string $firstName, string $lastName, string $userCode): array
{
    $login = trim($login);
    $firstName = trim($firstName);
    $lastName = trim($lastName);
    $userCode = trim($userCode);
    if ($login === '') {
        $login = 'operador.demo';
    }
    if ($firstName === '') {
        $firstName = 'Operador';
    }
    if ($userCode === '') {
        $userCode = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', $login));
    }

    $userStmt = $pdo->prepare('SELECT id, user_login FROM `user` WHERE user_login = :login LIMIT 1');
    $userStmt->execute([':login' => $login]);
    $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);

    $timestamp = time();
    $userId = null;

    if ($userRow === false) {
        $insertUser = $pdo->prepare(
            'INSERT INTO `user` (
                user_firstname, user_lastname, user_login, user_pass, user_type, user_status,
                user_crtusr, user_crtdat, user_updusr, user_upddat, user_visible, user_mailforward,
                user_mail, user_street, user_telephone, user_cellphone, user_internet, user_annotations,
                user_mail_signature_html, user_doc_signature, user_pic, user_countryid, user_regionid,
                user_provinciaid, user_comunaid, user_sidepanel_active, user_sidepanel_login,
                user_printer_name, user_printer_port, user_rut, user_code, conf_mailserver,
                conf_mail_accountname, conf_mail_password
             ) VALUES (
                :first_name, :last_name, :login, :password, 2, 1,
                1, :created_at, 1, :updated_at, 1, 0,
                :mail, "", "", "", "", "",
                "", "", "", 0, 0,
                0, 0, 1, 1,
                "", "", "", :user_code, "",
                "", ""
             )'
        );
        $insertUser->execute([
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':login' => $login,
            ':password' => '1234',
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
            ':mail' => $login . '@local.test',
            ':user_code' => $userCode,
        ]);
        $userId = (int)$pdo->lastInsertId();
    } else {
        $userId = (int)$userRow['id'];
        $pdo->prepare(
            'UPDATE `user`
             SET user_status = 1,
                 user_firstname = :first_name,
                 user_lastname = :last_name,
                 user_mail = COALESCE(NULLIF(user_mail, \'\'), :mail),
                 user_code = COALESCE(NULLIF(user_code, \'\'), :user_code),
                 user_upddat = :updated_at,
                 user_updusr = 1
             WHERE id = :id'
        )->execute([
            ':id' => $userId,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':mail' => $login . '@local.test',
            ':user_code' => $userCode,
            ':updated_at' => $timestamp,
        ]);
    }

    $workerStmt = $pdo->prepare('SELECT id FROM workers WHERE wrk_uid = :user_id LIMIT 1');
    $workerStmt->execute([':user_id' => $userId]);
    $workerRow = $workerStmt->fetch(PDO::FETCH_ASSOC);

    if ($workerRow === false) {
        $insertWorker = $pdo->prepare(
            'INSERT INTO workers (wrk_uid, wrk_firstname, wrk_lastname, wrk_status, wrk_turno_state)
             VALUES (:user_id, :first_name, :last_name, 1, 0)'
        );
        $fallbackWorkerData = [
            ':user_id' => $userId,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
        ];
        try {
            $insertWorker->execute($fallbackWorkerData);
        } catch (Throwable $e) {
            $insertWorker = $pdo->prepare(
                'INSERT INTO workers (
                    wrk_uid, wrk_firstname, wrk_lastname, wrk_status, wrk_turno_state,
                    wrk_crtdat, wrk_crtusr, wrk_rut, wrk_folio, wrk_email, wrk_telefono1,
                    wrk_telefono2, wrk_telefono3, wrk_turno_turnoid, wrk_cargoid, wrk_costo_hh,
                    wrk_titulo, wrk_birthday, wrk_street, wrk_foto, wrk_turno_startdate, wrk_axx_pass
                 ) VALUES (
                    :user_id, :first_name, :last_name, 1, 0,
                    :created_at, 1, :rut, :folio, :mail, "", "",
                    "", 0, 0, 0.00,
                    "", "", "", "", 0, ""
                 )'
            );
            $rutBase = preg_replace('/[^0-9]+/', '', (string)$userId);
            $rutBase = $rutBase !== '' ? $rutBase : '11111111';
            $rut = $rutBase . '-' . (($userId % 9) + 1);
            $insertWorker->execute([
                ':user_id' => $userId,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':created_at' => $timestamp,
                ':rut' => $rut,
                ':folio' => $userCode !== '' ? $userCode : strtoupper($login),
                ':mail' => $login . '@local.test',
            ]);
        }
        $workerId = (int)$pdo->lastInsertId();
    } else {
        $workerId = (int)$workerRow['id'];
        $pdo->prepare(
            'UPDATE workers
             SET wrk_status = 1,
                 wrk_firstname = COALESCE(NULLIF(wrk_firstname, \'\'), :first_name),
                 wrk_lastname = COALESCE(NULLIF(wrk_lastname, \'\'), :last_name)
             WHERE id = :id'
        )->execute([
            ':id' => $workerId,
            ':first_name' => $firstName,
            ':last_name' => $lastName,
        ]);
    }

    return [
        'worker_id' => $workerId,
        'user_id' => $userId,
        'user_login' => $login,
        'first_name' => $firstName,
        'last_name' => $lastName,
    ];
}

function createFlexoProdHeader(PDO $pdo, array $plan): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO prod_header (
            prd_crtdat, prd_crtusr, prd_status, prd_desc, prd_plantaid, prd_reqid, prd_upddat, prd_updusr, prd_number
         ) VALUES (
            :created_at, 1, 0, :description, :planta_id, :req_id, :updated_at, 1, :prd_number
         )'
    );
    $stmt->execute([
        ':created_at' => (int)$plan['date'],
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
        ':amount' => (float)$plan['requested_amount'],
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
    $amount = (float)$plan['produced_amount'];
    $bobbinKg = round($amount / 1000, 3);

    $stmt = $pdo->prepare(
        'INSERT INTO prod_worker_ot_events (
            evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments,
            evt_equipo_mantid, evt_pause_id, prod_bobina_kg, prod_seri_color, prod_seri_converted_amt,
            evt_medida_fromid, evt_medida_toid, evt_ubim_id, evt_amount_metros_maquina, evt_amount_metros_lineales, evt_metrotype
         ) VALUES (
            :worker_ot_id, :amount, :created_at, :end_at, 2, "PRODUCTION", :comments,
            0, 0, :bobbin_kg, "", 0,
            0, 0, 0, :metros_maquina, :metros_lineales, "ML"
         )'
    );
    $stmt->execute([
        ':worker_ot_id' => $workerOtId,
        ':amount' => $amount,
        ':created_at' => $eventAt,
        ':end_at' => $endAt,
        ':comments' => 'Seed flexo multi ops',
        ':bobbin_kg' => $bobbinKg,
        ':metros_maquina' => $amount,
        ':metros_lineales' => $amount,
    ]);

    return (int)$pdo->lastInsertId();
}
