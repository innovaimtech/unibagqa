<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/ReceptionService.php';
require_once __DIR__ . '/../src/ScaleService.php';
require_once __DIR__ . '/../src/PrintService.php';
require_once __DIR__ . '/../src/Http/AuthModule.php';
require_once __DIR__ . '/../src/Http/ApiModule.php';
require_once __DIR__ . '/../src/Http/InventoryModule.php';
require_once __DIR__ . '/../src/Http/ErpReportsModule.php';

Env::load(__DIR__ . '/../.env');
$appTimezone = trim((string)(Env::get('APP_TIMEZONE', 'America/Santiago') ?? 'America/Santiago'));
if ($appTimezone === '') {
    $appTimezone = 'America/Santiago';
}
date_default_timezone_set($appTimezone);

$sessionPath = trim((string)(Env::get('SESSION_SAVE_PATH', '') ?? ''));
if ($sessionPath === '') {
    $defaultStorageRoot = __DIR__ . '/../storage';
    if ((is_dir($defaultStorageRoot) || @mkdir($defaultStorageRoot, 0777, true)) && is_writable($defaultStorageRoot)) {
        $sessionPath = $defaultStorageRoot . '/sessions';
    } else {
        $sessionPath = rtrim(sys_get_temp_dir(), "\\/") . DIRECTORY_SEPARATOR . 'unibag-sessions';
    }
}
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0777, true);
}
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    ini_set('session.save_path', $sessionPath);
}
session_set_cookie_params([
    'httponly' => true,
    'secure' => isHttpsRequest(),
    'samesite' => 'Lax',
]);
session_start();
csrfToken();

$trzPdo = null;
$erpPdo = null;
$service = null;
$scale = null;
$printer = null;
$currentOperatorName = trim((string)($_SESSION['operator_name'] ?? $_SESSION['auth_display_name'] ?? 'Operador Demo'));
if ($currentOperatorName === '') {
    $currentOperatorName = 'Operador Demo';
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? rtrim($path, '/') : '/';
if ($path === '') {
    $path = '/';
}

function redirectResponse(string $location, int $statusCode = 303): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Location: ' . $location, true, $statusCode);
    exit;
}

if (handleAuthRoutes($path, $method)) {
    exit;
}

unibagEnforceAuthenticatedAreaAccess($path);

try {
    $trzPdo = Db::trzPdo();
    $erpPdo = Db::erpPdo();
} catch (Throwable $e) {
    renderDatabaseConnectionError($e);
}

$service = new ReceptionService($trzPdo, $erpPdo);
$scale = new ScaleService($_ENV);
$printer = new PrintService($_ENV);

if (handleApiRoutes($path, $method, $service, $scale, $printer, $currentOperatorName)) {
    exit;
}

if (handleInventoryRoutes($path, $method, $service, $currentOperatorName)) {
    exit;
}

if (handleErpReportRoutes($path, $method, $service)) {
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @param array<string, mixed> $roll
 * @return array{
 *   warehouse: array{allowed: bool, reason: string},
 *   work_order: array{allowed: bool, reason: string},
 *   can_transfer: bool
 * }
 */
function rollTransferAvailability(array $roll): array
{
    $status = strtoupper(trim((string)($roll['status'] ?? '')));
    $processStage = strtoupper(trim((string)($roll['process_stage'] ?? 'RAW')));
    $currentWorkOrderId = (int)($roll['current_work_order_id'] ?? 0);
    $currentWorkOrderCode = trim((string)($roll['work_order_code'] ?? ''));

    $warehouse = ['allowed' => false, 'reason' => ''];
    $workOrder = ['allowed' => false, 'reason' => ''];

    if ($currentWorkOrderId > 0) {
        $reason = 'La bobina ya está asignada a la OT ' . ($currentWorkOrderCode !== '' ? $currentWorkOrderCode : ('#' . $currentWorkOrderId)) . '.';
        $warehouse['reason'] = $reason;
        $workOrder['reason'] = $reason;
    } elseif ($status !== 'RECEIVED') {
        $reason = match ($status) {
            'IN_PROCESS' => 'La bobina está en proceso y no se puede mover.',
            'CONSUMED' => 'La bobina ya fue consumida y no se puede mover.',
            'BLOCKED' => 'La bobina está bloqueada y no se puede mover.',
            default => 'La bobina no está disponible para traslado.',
        };
        $warehouse['reason'] = $reason;
        $workOrder['reason'] = $reason;
    } else {
        $warehouse['allowed'] = true;
        if (in_array($processStage, ['RAW', 'PRINTED'], true)) {
            $workOrder['allowed'] = true;
        } else {
            $workOrder['reason'] = 'La etapa actual de la bobina no permite ingresarla a una OT.';
        }
    }

    return [
        'warehouse' => $warehouse,
        'work_order' => $workOrder,
        'can_transfer' => $warehouse['allowed'] || $workOrder['allowed'],
    ];
}

function renderDatabaseConnectionError(Throwable $e): void
{
    $body = '<div class="card">
        <div style="font-size:18px;font-weight:800;margin-bottom:6px">No hay conexión a la base de datos</div>
        <div class="muted" style="margin-bottom:10px">Este módulo requiere MySQL configurado mediante variables de entorno o en el archivo <b>.env</b> para ERP y trazabilidad.</div>
        <div class="err" style="margin-bottom:10px"><div style="font-weight:700;margin-bottom:6px">Detalle</div><div>' . h($e->getMessage()) . '</div></div>
        <div style="font-weight:700;margin-bottom:6px">Checklist</div>
        <ul style="margin:0;padding-left:18px">
          <li>Configurar <b>ERP_DB_*</b> para <b>unibagqa</b></li>
          <li>Configurar <b>TRZ_DB_*</b> para <b>unibag_trazabilidad</b></li>
          <li>Ejecutar el esquema de trazabilidad en la base <b>TRZ_DB_NAME</b></li>
        </ul>
      </div>';
    render('Sin BD', $body);
    exit;
}

function receptionModeLabel(string $mode): string
{
    return strtoupper(trim($mode)) === 'WEIGHT' ? 'Por peso' : 'Por unidades';
}

function rollProcessStageLabel(?string $stage): string
{
    return match (strtoupper(trim((string)$stage))) {
        'RAW' => 'Materia prima',
        'PRINTED' => 'Impresa',
        'CUT' => 'Cortada',
        default => trim((string)$stage) !== '' ? (string)$stage : '-',
    };
}

function rollStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'RECEIVED' => 'Disponible',
        'IN_PROCESS' => 'En proceso',
        'BLOCKED' => 'Bloqueada',
        'CONSUMED' => 'Consumida',
        default => trim((string)$status) !== '' ? (string)$status : '-',
    };
}

function workOrderStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'PENDING' => 'Pendiente',
        'OPEN' => 'Pendiente',
        'ACTIVE' => 'En producción',
        'CUTTING' => 'Corte pendiente',
        'CLOSED' => 'Cerrada',
        default => trim((string)$status) !== '' ? (string)$status : '-',
    };
}

function machineProcessStageLabel(?string $stage): string
{
    return match (strtoupper(trim((string)$stage))) {
        'PRODUCTION' => 'Producción',
        'REWINDING' => 'Rebobinado',
        'PACKAGING' => 'Embalaje',
        'SEALING' => 'Sellado',
        'CUTTING' => 'Corte',
        default => trim((string)$stage) !== '' ? trim((string)$stage) : '-',
    };
}

function shiftSessionStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'ACTIVE' => 'En turno',
        'CLOSED' => 'Terminado',
        default => trim((string)$status) !== '' ? trim((string)$status) : '-',
    };
}

function receptionDocumentStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'OPEN' => 'Abierta',
        'PARTIAL' => 'Parcial',
        'COMPLETE' => 'Completa',
        default => trim((string)$status) !== '' ? (string)$status : '-',
    };
}

function materialRequestStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'PENDING' => 'Pendiente',
        'ACCEPTED' => 'Aceptada',
        'PARTIAL' => 'Parcial',
        'DELIVERED' => 'Entregada',
        default => trim((string)$status) !== '' ? (string)$status : '-',
    };
}

function workOrderCanReceiveMaterials(?string $status): bool
{
    return in_array(strtoupper(trim((string)$status)), ['OPEN', 'ACTIVE', 'CUTTING'], true);
}

function materialRequestTypeLabel(?string $type): string
{
    return match (strtoupper(trim((string)$type))) {
        'ROLL' => 'Bobina',
        'CHEMICAL' => 'Tinta',
        'OTHER' => 'Otro insumo',
        default => trim((string)$type) !== '' ? (string)$type : '-',
    };
}

function movementTypeLabel(?string $type): string
{
    return match (strtoupper(trim((string)$type))) {
        'RECEIPT' => 'Recepción',
        'TRANSFER' => 'Traspaso',
        default => trim((string)$type) !== '' ? (string)$type : '-',
    };
}

function maquilaStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'OPEN' => 'En maquila',
        'PARTIAL' => 'Retorno parcial',
        'RETURNED' => 'Retornada',
        'CANCELLED' => 'Cancelada',
        default => trim((string)$status) !== '' ? (string)$status : '-',
    };
}

function palletStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'CREATED' => 'Pendiente',
        'STORED' => 'Almacenado',
        'IN_MAQUILA' => 'En maquila',
        'MAQUILA_RETURNED' => 'Retornado de maquila',
        default => trim((string)$status) !== '' ? (string)$status : '-',
    };
}

function boxStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'CREATED' => 'Pendiente',
        'STORED' => 'Almacenada',
        'IN_MAQUILA' => 'En maquila',
        'MAQUILA_RETURNED' => 'Retornada de maquila',
        default => trim((string)$status) !== '' ? (string)$status : '-',
    };
}

function clicheStatusLabel(?string $status): string
{
    return match (strtoupper(trim((string)$status))) {
        'AVAILABLE' => 'Disponible',
        'IN_USE' => 'En uso',
        'MAINTENANCE' => 'Mantención',
        'INACTIVE' => 'Inactivo',
        default => trim((string)$status) !== '' ? (string)$status : '-',
    };
}

function eventTypeLabel(?string $type): string
{
    return match (strtoupper(trim((string)$type))) {
        'WORK_ORDER_CREATED' => 'OT creada',
        'WORK_ORDER_ACTIVATED' => 'OT activada',
        'WORK_ORDER_STARTED' => 'OT iniciada',
        'WORK_ORDER_FINISHED' => 'OT finalizada',
        'WORK_ORDER_ROLL_ATTACHED' => 'Bobina ingresada a OT',
        'WORK_ORDER_ROLL_RELEASED' => 'Bobina salida de OT',
        'MATERIAL_REQUESTED' => 'Material solicitado',
        'MATERIAL_REQUEST_ACCEPTED' => 'Solicitud aceptada',
        'MATERIAL_DELIVERED' => 'Material entregado',
        'CHEMICAL_WEIGHING_CREATED' => 'Pesaje de tinta registrado',
        'CHEMICAL_INPUT_RECORDED' => 'Tinta de entrada registrada',
        'PRODUCTION_WASTE_RECORDED' => 'Merma registrada',
        'OUTPUT_ROLL_CREATED' => 'Bobina de salida creada',
        'CUT_COMPLETED' => 'Corte completado',
        'ROLL_TRANSFERRED' => 'Bobina transferida',
        'ROLL_RECEIVED' => 'Bobina recepcionada',
        'CLICHE_CREATED' => 'Clisé creado',
        'CLICHE_ASSIGNED' => 'Clisé asignado a OT',
        'CLICHE_RETURNED' => 'Clisé devuelto',
        'PALLET_TRANSFERRED' => 'Pallet trasladado',
        'SKU_CREATED' => 'SKU creado',
        'SKU_TOGGLED' => 'SKU actualizado',
        'SHIFT_SESSION_STARTED' => 'Turno iniciado',
        'SHIFT_SESSION_ENDED' => 'Turno terminado',
        'SHIFT_SESSION_WORK_ORDER_ASSIGNED' => 'OT asignada a máquina',
        'SHIFT_SESSION_WORK_ORDER_RELEASED' => 'OT liberada de máquina',
        'MAQUILA_SENT' => 'Salida a maquila',
        'MAQUILA_RETURN_RECORDED' => 'Retorno de maquila',
        default => trim((string)$type) !== '' ? trim((string)$type) : '-',
    };
}

function erpAreaShortLabel(string $area): string
{
    return match (normalizeErpArea($area)) {
        'RECEPTION' => 'RECEPCIÓN',
        'PRODUCTION' => 'PRODUCCIÓN',
        default => 'ERP',
    };
}

function receptionLineSummary(array $line): array
{
    $mode = strtoupper(trim((string)($line['reception_mode'] ?? 'QUANTITY'))) === 'WEIGHT' ? 'WEIGHT' : 'QUANTITY';
    if ($mode === 'WEIGHT') {
        $ordered = round((float)($line['ordered_weight_kg'] ?? 0), 3);
        $received = round((float)($line['received_weight_kg'] ?? 0), 3);
        $unit = 'Kg';
    } else {
        $ordered = round((float)($line['ordered_rolls'] ?? 0), 3);
        $received = round((float)($line['received_qty'] ?? $line['received_rolls'] ?? 0), 3);
        $unit = 'Unid.';
    }

    return [
        'mode' => $mode,
        'ordered' => $ordered,
        'received' => $received,
        'pending' => max(0, round($ordered - $received, 3)),
        'unit' => $unit,
    ];
}

function formatReceptionValue(float $value, string $unit): string
{
    if ($unit === 'Unid.' && abs($value - round($value)) < 0.0001) {
        return (string)(int)round($value);
    }
    return number_format($value, 3, ',', '.');
}

function formatLabelDate(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }

    return date('d-m-Y', $ts);
}

function formatElapsedLabel(?string $startedAt, ?string $endedAt = null): string
{
    $startedTs = $startedAt !== null && trim($startedAt) !== '' ? strtotime($startedAt) : false;
    if ($startedTs === false) {
        return '0h 0m';
    }
    $endedTs = $endedAt !== null && trim($endedAt) !== '' ? strtotime($endedAt) : time();
    if ($endedTs === false || $endedTs < $startedTs) {
        $endedTs = $startedTs;
    }
    $diffSeconds = max(0, $endedTs - $startedTs);
    $hours = intdiv($diffSeconds, 3600);
    $minutes = intdiv($diffSeconds % 3600, 60);
    return $hours . 'h ' . $minutes . 'm';
}

function buildReceptionSpec(array $line): string
{
    $spec = [];
    $grams = $line['grams'] ?? $line['microns'] ?? null;
    if ($grams !== null && $grams !== '') { $spec[] = 'Gramos ' . (int)$grams; }
    if (($line['width_mm'] ?? null) !== null) { $spec[] = 'Ancho ' . (int)$line['width_mm']; }
    if (($line['color'] ?? '') !== '') { $spec[] = 'Color ' . h((string)$line['color']); }
    if (($line['meters'] ?? null) !== null) { $spec[] = 'ML ' . h((string)$line['meters']); }
    return $spec === [] ? '-' : implode(' · ', $spec);
}

function materialRequestGroupLabel(array $item): string
{
    $parts = [];
    $product = trim((string)($item['sku_description'] ?? $item['requested_item'] ?? 'Bobina'));
    if ($product !== '') {
        $parts[] = $product;
    }

    $spec = [];
    $grams = $item['grams'] ?? null;
    $width = $item['width_mm'] ?? null;
    $color = $item['color'] ?? null;
    $meters = $item['meters'] ?? null;
    $availableQty = $item['available_qty'] ?? null;

    if ($grams !== null && $grams !== '') { $spec[] = 'Gramos ' . (string)$grams; }
    if ($width !== null && $width !== '') { $spec[] = 'Ancho ' . (string)$width . ' mm'; }
    if ($color !== null && trim((string)$color) !== '') { $spec[] = 'Color ' . trim((string)$color); }
    if ($meters !== null && $meters !== '') { $spec[] = 'ML ' . (string)$meters; }
    if ($availableQty !== null && $availableQty !== '') { $spec[] = 'Disponibles ' . (string)$availableQty; }

    if ($spec !== []) {
        $parts[] = implode(' · ', $spec);
    }

    return implode(' | ', $parts);
}

function parseMaterialRequestGroupKey(?string $groupKey): array
{
    $parts = explode('|', trim((string)$groupKey));
    return [
        'sku_code' => trim((string)($parts[0] ?? '')),
        'sku_description' => trim((string)($parts[1] ?? '')),
        'grams' => trim((string)($parts[2] ?? '')),
        'width_mm' => trim((string)($parts[3] ?? '')),
        'color' => trim((string)($parts[4] ?? '')),
        'meters' => trim((string)($parts[5] ?? '')),
        'process_stage' => trim((string)($parts[6] ?? '')),
    ];
}

function buildMaterialRequestGroupKeyFromRollData(array $roll): string
{
    return implode('|', [
        (string)($roll['sku_code'] ?? ''),
        (string)($roll['sku_description'] ?? ''),
        (string)($roll['grams'] ?? ''),
        (string)($roll['width_mm'] ?? ''),
        trim((string)($roll['color'] ?? '')),
        (string)($roll['meters'] ?? ''),
        (string)($roll['process_stage'] ?? ''),
    ]);
}

function formatLabelDateTime(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return $value;
    }

    return date('d.m.Y H:i:s', $ts);
}

function code39Svg(string $data, int $height = 70, int $narrow = 2, int $wide = 5): string
{
    $data = strtoupper(trim($data));
    if ($data === '') {
        return '';
    }

    $allowed = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-. $/+%';
    for ($i = 0; $i < strlen($data); $i++) {
        if (strpos($allowed, $data[$i]) === false) {
            return '';
        }
    }

    $map = [
        '0' => 'nnnwwnwnn',
        '1' => 'wnnwnnnnw',
        '2' => 'nnwwnnnnw',
        '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw',
        '5' => 'wnnwwnnnn',
        '6' => 'nnwwwnnnn',
        '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn',
        '9' => 'nnwwnnwnn',
        'A' => 'wnnnnwnnw',
        'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn',
        'D' => 'nnnnwwnnw',
        'E' => 'wnnnwwnnn',
        'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw',
        'H' => 'wnnnnwwnn',
        'I' => 'nnwnnwwnn',
        'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww',
        'L' => 'nnwnnnnww',
        'M' => 'wnwnnnnwn',
        'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn',
        'P' => 'nnwnwnnwn',
        'Q' => 'nnnnnnwww',
        'R' => 'wnnnnnwwn',
        'S' => 'nnwnnnwwn',
        'T' => 'nnnnwnwwn',
        'U' => 'wwnnnnnnw',
        'V' => 'nwwnnnnnw',
        'W' => 'wwwnnnnnn',
        'X' => 'nwnnwnnnw',
        'Y' => 'wwnnwnnnn',
        'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw',
        '.' => 'wwnnnnwnn',
        ' ' => 'nwwnnnwnn',
        '$' => 'nwnwnwnnn',
        '/' => 'nwnwnnnwn',
        '+' => 'nwnnnwnwn',
        '%' => 'nnnwnwnwn',
        '*' => 'nwnnwnwnn',
    ];

    $encoded = '*' . $data . '*';
    $x = 10;
    $bars = [];
    for ($i = 0; $i < strlen($encoded); $i++) {
        $ch = $encoded[$i];
        $pat = $map[$ch] ?? null;
        if ($pat === null) {
            return '';
        }

        for ($j = 0; $j < 9; $j++) {
            $isBar = ($j % 2) === 0;
            $w = ($pat[$j] === 'w') ? $wide : $narrow;
            if ($isBar) {
                $bars[] = '<rect x="' . $x . '" y="0" width="' . $w . '" height="' . $height . '" fill="#000"/>';
            }
            $x += $w;
        }
        $x += $narrow;
    }
    $totalW = $x + 10;

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $totalW . '" height="' . $height . '" viewBox="0 0 ' . $totalW . ' ' . $height . '" role="img" aria-label="código de barras">' . implode('', $bars) . '</svg>';
}

function csrfToken(): string
{
    $token = trim((string)($_COOKIE[csrfCookieName()] ?? ''));
    if ($token === '') {
        $token = trim((string)($_SESSION['csrf'] ?? ''));
    }
    if ($token === '') {
        $token = bin2hex(random_bytes(32));
    }

    $_SESSION['csrf'] = $token;
    setCsrfCookie($token);

    return $token;
}

function requireCsrf(): void
{
    $token = (string)($_POST['_csrf'] ?? '');
    $cookieToken = trim((string)($_COOKIE[csrfCookieName()] ?? ''));
    $sessionToken = trim((string)($_SESSION['csrf'] ?? ''));

    $validCookieToken = $token !== '' && $cookieToken !== '' && hash_equals($cookieToken, $token);
    $validSessionToken = $token !== '' && $sessionToken !== '' && hash_equals($sessionToken, $token);

    if (!$validCookieToken && !$validSessionToken) {
        http_response_code(400);
        render('CSRF', '<div class="card"><div class="err">Solicitud inválida (CSRF).</div></div>');
        exit;
    }

    if ($cookieToken === '' || !$validCookieToken) {
        setCsrfCookie($token);
    }
    $_SESSION['csrf'] = $token;
}

function csrfCookieName(): string
{
    return 'unibag_csrf';
}

function setCsrfCookie(string $token): void
{
    if ($token === '') {
        return;
    }

    if ((string)($_COOKIE[csrfCookieName()] ?? '') === $token) {
        return;
    }

    if (headers_sent()) {
        $_COOKIE[csrfCookieName()] = $token;
        return;
    }

    $options = [
        'expires' => time() + 86400 * 30,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    setcookie(csrfCookieName(), $token, $options);
    $_COOKIE[csrfCookieName()] = $token;
}

function expireCsrfCookie(): void
{
    if (headers_sent()) {
        unset($_COOKIE[csrfCookieName()]);
        return;
    }

    $options = [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    setcookie(csrfCookieName(), '', $options);
    unset($_COOKIE[csrfCookieName()]);
}

function isHttpsRequest(): bool
{
    $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off') {
        return true;
    }

    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $forwardedProto === 'https';
}

function authModeDefinitions(): array
{
    return [
        0 => ['id' => 0, 'label' => 'ERP', 'icon' => 'Documento', 'active_bg' => '#00A9A6', 'inactive_bg' => '#F9F9F9', 'show_plant' => false],
    ];
}

function authCompanyDefinitions(): array
{
    return [
        1 => ['id' => 1, 'label' => 'UNIBAG CHILE'],
        2 => ['id' => 2, 'label' => 'UNIBAG PERÚ'],
        3 => ['id' => 3, 'label' => 'UNIBAG MÉXICO'],
    ];
}

function authPlantDefinitions(): array
{
    return [
        1 => ['id' => 1, 'label' => 'Planta Extrusión'],
        2 => ['id' => 2, 'label' => 'Planta Conversión'],
        3 => ['id' => 3, 'label' => 'Bodega Central'],
    ];
}

function authPermissionColumn(int $appMode): string
{
    return match ($appMode) {
        0 => 'can_erp',
        1 => 'can_production',
        2 => 'can_operator',
        3 => 'can_warehouse',
        4 => 'can_marketing',
        default => '',
    };
}

function erpAreaDefinitions(): array
{
    return [
        'ERP' => [
            'id' => 'ERP',
            'label' => 'ERP',
            'home' => '/',
        ],
        'RECEPTION' => [
            'id' => 'RECEPTION',
            'label' => 'Recepción',
            'home' => '/purchase-orders?status=active&supplier_type=NATIONAL',
        ],
        'PRODUCTION' => [
            'id' => 'PRODUCTION',
            'label' => 'Producción',
            'home' => '/production/shifts',
        ],
    ];
}

function normalizeErpArea(string $value): string
{
    $value = strtoupper(trim($value));
    return array_key_exists($value, erpAreaDefinitions()) ? $value : 'ERP';
}

function currentSessionArea(): string
{
    return normalizeErpArea((string)($_SESSION['erp_area'] ?? 'ERP'));
}

function inventoryNavigationContext(): bool
{
    return currentSessionArea() === 'RECEPTION'
        && strtolower(trim((string)($_GET['nav'] ?? ''))) === 'inventory';
}

/**
 * @return array<string, string>
 */
function inventoryNavigationParams(?int $warehouseCode = null): array
{
    if (currentSessionArea() !== 'RECEPTION') {
        return [];
    }
    $params = ['nav' => 'inventory'];
    $resolvedWarehouseCode = $warehouseCode ?? (isset($_GET['bodega']) ? (int)$_GET['bodega'] : 0);
    if ($resolvedWarehouseCode > 0) {
        $params['bodega'] = (string)$resolvedWarehouseCode;
    }
    return $params;
}

/**
 * @param array<string, scalar|null> $params
 */
function withQuery(string $path, array $params = []): string
{
    $params = array_filter($params, static fn($value): bool => $value !== null && $value !== '');
    if ($params === []) {
        return $path;
    }
    return $path . (str_contains($path, '?') ? '&' : '?') . http_build_query($params);
}

function inventoryReturnUrl(): string
{
    $warehouseCode = isset($_GET['bodega']) ? (int)$_GET['bodega'] : 0;
    return withQuery('/stock', $warehouseCode > 0 ? ['bodega' => (string)$warehouseCode] : []);
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function outputInventoryExcel(string $filename, array $rows): void
{
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    echo '<tr><th>Codigo SKU</th><th>Cantidad disponible</th></tr>';
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>' . h((string)($row['sku_code'] ?? '')) . '</td>';
        echo '<td>' . h(number_format((float)($row['available_qty'] ?? 0), 3, '.', '')) . '</td>';
        echo '</tr>';
    }
    if ($rows === []) {
        echo '<tr><td colspan="2">Sin stock disponible</td></tr>';
    }
    echo '</table>';
    echo '</body></html>';
    exit;
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function outputInventoryCountDetailExcel(string $filename, array $rows, array $inventoryCount): void
{
    $warehouseLabel = trim((string)($inventoryCount['warehouse_code'] ?? '')) . ' (' . trim((string)($inventoryCount['warehouse_name'] ?? '')) . ')';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    echo '<html><head><meta charset="UTF-8"></head><body>';
    echo '<table border="1">';
    echo '<tr><th>Numero</th><th>Articulo</th><th>Familia</th><th>Cod. color</th><th>Alto</th><th>Gramos</th><th>Metros</th><th>Unidad</th><th>Bodega</th><th>Sistema</th><th>Fisico</th><th>Dif</th></tr>';
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>' . h((string)($row['sku_code'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['article_code'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['family_color'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['color_code'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['height_mm'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['grams'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['meters'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['unit_code'] ?? '')) . '</td>';
        echo '<td>' . h(trim($warehouseLabel) !== '()' ? $warehouseLabel : '') . '</td>';
        echo '<td>' . h(number_format((float)($row['system_qty'] ?? 0), 3, '.', '')) . '</td>';
        echo '<td>' . h(number_format((float)($row['physical_qty'] ?? 0), 3, '.', '')) . '</td>';
        echo '<td>' . h(number_format((float)($row['diff_qty'] ?? 0), 3, '.', '')) . '</td>';
        echo '</tr>';
    }
    if ($rows === []) {
        echo '<tr><td colspan="12">Sin registros</td></tr>';
    }
    echo '</table>';
    echo '</body></html>';
    exit;
}

function userAreaPermissions(array $user): array
{
    $canErp = (int)($user['can_erp'] ?? 0) === 1;
    $canReception = $canErp || (int)($user['can_warehouse'] ?? 0) === 1;
    $canProduction = $canErp || (int)($user['can_production'] ?? 0) === 1 || (int)($user['can_operator'] ?? 0) === 1;

    return [
        'ERP' => $canErp,
        'RECEPTION' => $canReception,
        'PRODUCTION' => $canProduction,
    ];
}

function sessionAreaPermissions(): array
{
    return [
        'ERP' => (int)($_SESSION['perm_area_erp'] ?? 0) === 1,
        'RECEPTION' => (int)($_SESSION['perm_area_reception'] ?? 0) === 1,
        'PRODUCTION' => (int)($_SESSION['perm_area_production'] ?? 0) === 1,
    ];
}

function userCanAccessArea(string $area, array $permissions): bool
{
    $area = normalizeErpArea($area);
    return (bool)($permissions[$area] ?? false);
}

function firstAllowedAreaHome(array $permissions): string
{
    foreach (['ERP', 'RECEPTION', 'PRODUCTION'] as $area) {
        if (userCanAccessArea($area, $permissions)) {
            return erpAreaDefinitions()[$area]['home'] ?? '/';
        }
    }

    return '/logout';
}

function detectRequestedArea(string $path): string
{
    if ($path === '/') {
        return 'ERP';
    }
    if (
        inventoryNavigationContext()
        && (
            str_starts_with($path, '/work-orders')
            || str_starts_with($path, '/rolls')
            || str_starts_with($path, '/boxes')
            || str_starts_with($path, '/pallets')
        )
    ) {
        return 'RECEPTION';
    }
    if (
        str_starts_with($path, '/purchase-orders')
        || str_starts_with($path, '/import-containers')
        || str_starts_with($path, '/stock')
        || str_starts_with($path, '/maquila')
        || (str_starts_with($path, '/pallets') && currentSessionArea() === 'RECEPTION')
    ) {
        return 'RECEPTION';
    }
    if (
        str_starts_with($path, '/production/shifts')
        || str_starts_with($path, '/production/machines')
        || str_starts_with($path, '/work-orders')
        || str_starts_with($path, '/chemicals')
        || str_starts_with($path, '/cut')
        || str_starts_with($path, '/boxes')
        || str_starts_with($path, '/pallets')
    ) {
        return 'PRODUCTION';
    }

    return 'ERP';
}

function isProductionPath(string $path): bool
{
    return $path === '/' || detectRequestedArea($path) === 'PRODUCTION';
}

function isErpProductionReadOnlyMode(?string $path = null): bool
{
    $path = $path ?? (string)($GLOBALS['path'] ?? '/');
    return currentSessionArea() === 'ERP' && isProductionPath($path);
}

function canAccessWorkOrderTraceability(): bool
{
    return userCanAccessArea('ERP', sessionAreaPermissions());
}

/**
 * @param array<int, array<string, mixed>> $workOrders
 * @param array<string, mixed>|null $activeShiftSession
 * @return array<int, array<string, mixed>>
 */
function filterPendingWorkOrdersByShiftMachine(array $workOrders, ?array $activeShiftSession): array
{
    if ($activeShiftSession === null) {
        return $workOrders;
    }

    $shiftErpMachineId = (int)($activeShiftSession['erp_machine_id'] ?? 0);
    $shiftErpMachineTypeId = (int)($activeShiftSession['erp_machine_type_id'] ?? 0);

    if ($shiftErpMachineId <= 0 && $shiftErpMachineTypeId <= 0) {
        return $workOrders;
    }

    $filtered = array_values(array_filter(
        $workOrders,
        static function (array $workOrder) use ($shiftErpMachineId, $shiftErpMachineTypeId): bool {
            $workOrderMachineId = (int)($workOrder['erp_machine_id'] ?? 0);
            $workOrderMachineTypeId = (int)($workOrder['erp_machine_type_id'] ?? 0);

            if ($shiftErpMachineId > 0) {
                return $workOrderMachineId === $shiftErpMachineId;
            }

            return $shiftErpMachineTypeId > 0 && $workOrderMachineTypeId === $shiftErpMachineTypeId;
        }
    ));

    return $filtered;
}

function denyErpProductionWriteAccess(): void
{
    if (!isErpProductionReadOnlyMode()) {
        return;
    }

    http_response_code(403);
    render('Solo lectura', '<div class="card"><div style="font-size:18px;font-weight:800;margin-bottom:8px">Acceso de solo lectura</div><div class="muted">Desde ERP solo puedes revisar el avance, estado y movimientos de la OT. Las acciones operativas se realizan desde el área de Producción.</div><div style="margin-top:12px"><a class="btn secondary" href="/work-orders">Volver a trazabilidad</a></div></div>');
    exit;
}

function isWarehousePalletAssignmentArea(): bool
{
    return currentSessionArea() === 'RECEPTION' && ((bool)(sessionAreaPermissions()['RECEPTION'] ?? false));
}

function productionSectionLabel(string $path): string
{
    if ($path === '/') {
        return 'Panel de producción';
    }
    if (str_starts_with($path, '/production/shifts')) {
        return 'Asignación máquina';
    }
    if (str_starts_with($path, '/work-orders')) {
        return 'Órdenes de trabajo';
    }
    if (str_starts_with($path, '/chemicals')) {
        return 'Pesajes de tintas';
    }
    if (str_starts_with($path, '/cut') || str_starts_with($path, '/boxes')) {
        return 'Proceso de corte';
    }
    if (str_starts_with($path, '/pallets')) {
        return 'Pallets terminados';
    }

    return 'Producción';
}

function renderProductionShell(string $body, string $currentPath, string $displayName, string $companyName): void
{
    $view = strtolower(trim((string)($_GET['view'] ?? 'pending')));
    $subLink = static function (?string $href, string $label, bool $isActive = false): string {
        if ($href === null || $href === '') {
            return '<span class="prod-nav-static">' . h($label) . '</span>';
        }
        return '<a class="prod-nav-sublink' . ($isActive ? ' active' : '') . '" href="' . h($href) . '">' . h($label) . '</a>';
    };
    $group = static function (string $label, string $iconClass, bool $isActive, array $items): string {
        return '<div class="prod-nav-folder' . ($isActive ? ' active' : '') . '">'
            . '<div class="prod-nav-folder-head"><span class="prod-nav-folder-label"><span class="prod-nav-folder-icon ' . h($iconClass) . '" aria-hidden="true"></span>' . h($label) . '</span><span class="prod-nav-folder-caret">v</span></div>'
            . '<div class="prod-nav-folder-body">' . implode('', $items) . '</div>'
            . '</div>';
    };

    echo '<div class="prod-shell" id="prodShell">';
    echo '<button class="prod-sidebar-backdrop" id="prodSidebarBackdrop" type="button" aria-label="Cerrar menú"></button>';
    echo '<aside class="prod-sidebar" id="prodSidebar">';
    echo '<div class="prod-brand">';
    echo '<div class="prod-brand-box"><span class="prod-brand-mark">U</span><span class="prod-brand-word">unibag</span></div>';
    echo '</div>';
    echo '<div class="prod-nav-title">Navegación</div>';
    echo $group('Asignación máquina', 'machine', str_starts_with($currentPath, '/production/shifts'), [
        $subLink('/production/shifts', 'Iniciar / Terminar turno', str_starts_with($currentPath, '/production/shifts')),
    ]);
    echo $group('Ordenes de trabajo', 'orders', str_starts_with($currentPath, '/work-orders'), [
        $subLink('/work-orders?view=pending', 'Iniciar nueva OT', str_starts_with($currentPath, '/work-orders') && ($view === 'pending' || $view === '')),
        $subLink('/work-orders?view=active', 'En cursos', str_starts_with($currentPath, '/work-orders') && $view === 'active'),
        $subLink('/work-orders?view=closed', 'Historico', str_starts_with($currentPath, '/work-orders') && $view === 'closed'),
    ]);
    echo $group('Mensajes', 'messages', false, [
        $subLink(null, 'Nuevo mensaje'),
        $subLink(null, 'Buzon'),
    ]);
    echo '</aside>';
    echo '<div class="prod-main-wrap">';
    echo '<header class="prod-topbar">';
    echo '<div class="prod-topbar-title">';
    echo '<div class="prod-topbar-brand">Unibag</div>';
    echo '<button class="prod-toggle-btn" id="prodSidebarToggle" type="button" aria-expanded="false" aria-controls="prodSidebar" aria-label="Abrir menú">&#9776;</button>';
    echo '<span>' . h(productionSectionLabel($currentPath)) . '</span>';
    echo '</div>';
    echo '<div class="prod-topbar-right">';
    echo '<div class="prod-user-pill">Usuario: ' . h($displayName) . '</div>';
    echo '<div class="prod-user-pill">Empresa: ' . h($companyName) . '</div>';
    echo '<a class="prod-user-pill logout" href="/logout">Salir</a>';
    echo '</div>';
    echo '</header>';
    echo '<main class="prod-main">' . $body . '</main>';
    echo '<script>
      (function () {
        var isMobileLayout = function () {
          return window.matchMedia("(max-width: 980px)").matches;
        };
        var shell = document.getElementById("prodShell");
        var sidebar = document.getElementById("prodSidebar");
        var toggle = document.getElementById("prodSidebarToggle");
        var backdrop = document.getElementById("prodSidebarBackdrop");
        if (!shell || !sidebar || !toggle || !backdrop) return;
        var sync = function (open) {
          var shouldOpen = isMobileLayout() ? open : false;
          shell.classList.toggle("menu-open", shouldOpen);
          toggle.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
          toggle.setAttribute("aria-label", shouldOpen ? "Cerrar menú" : "Abrir menú");
        };
        toggle.addEventListener("click", function () {
          if (!isMobileLayout()) return;
          sync(!shell.classList.contains("menu-open"));
        });
        backdrop.addEventListener("click", function () {
          sync(false);
        });
        document.addEventListener("keydown", function (event) {
          if (event.key === "Escape") {
            sync(false);
          }
        });
        window.addEventListener("resize", function () {
          sync(false);
        });
        sync(false);
      })();
    </script>';
    echo '</div>';
    echo '</div>';
}

function ensureAuthSchema(PDO $pdo): void
{
    static $schemaReady = false;
    if ($schemaReady) {
        return;
    }

    $schemaVersion = 'auth_v1';
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'auth_schema_version' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row !== false && (string)($row['setting_value'] ?? '') === $schemaVersion) {
            $schemaReady = true;
            return;
        }
    } catch (Throwable) {
        // Continue with the schema bootstrap if app_settings is not ready yet.
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS auth_users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(60) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(120) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            can_erp TINYINT(1) NOT NULL DEFAULT 1,
            can_production TINYINT(1) NOT NULL DEFAULT 1,
            can_operator TINYINT(1) NOT NULL DEFAULT 1,
            can_warehouse TINYINT(1) NOT NULL DEFAULT 1,
            can_marketing TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_auth_users_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $stmt = $pdo->prepare('SELECT id FROM auth_users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => 'demo']);
    if ($stmt->fetch() === false) {
        $insert = $pdo->prepare(
            'INSERT INTO auth_users (
                username, password_hash, display_name, is_active,
                can_erp, can_production, can_operator, can_warehouse, can_marketing
            ) VALUES (
                :username, :password_hash, :display_name, 1, 1, 1, 1, 1, 1
            )'
        );
        $insert->execute([
            'username' => 'demo',
            'password_hash' => '$2y$10$NEnibNryVcuH8MX2zxUaW.Inrqb0go6.jf3VMFXHERLyLuY0jOAny',
            'display_name' => 'Operador Demo',
        ]);
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO app_settings (setting_key, setting_value) VALUES ('auth_schema_version', :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $stmt->execute([':v' => $schemaVersion]);
    } catch (Throwable) {
        // The auth table is already available; a missing setting should not block the request.
    }

    $schemaReady = true;
}

function renderLoginPage(?string $error = null, array $state = []): void
{
    http_response_code(200);
    $companies = authCompanyDefinitions();
    $plants = authPlantDefinitions();
    $areas = erpAreaDefinitions();
    $companyId = isset($state['user_company_id']) ? (int)$state['user_company_id'] : 1;
    $userLogin = (string)($state['user_login'] ?? '');
    $erpArea = normalizeErpArea((string)($state['erp_area'] ?? 'ERP'));

    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Ingreso ERP</title>';
    echo '<style>
        *{box-sizing:border-box}
        body{margin:0;background:#f2f2f2;font-family:Arial,sans-serif;color:#333}
        .login-shell{min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:28px 14px}
        .login-box{width:min(560px,100%);border:1px solid #a6a6a6;background:#efefef;box-shadow:0 1px 0 #fff inset}
        .login-header{background:#d7d7d7;border-bottom:1px solid #b8b8b8;padding:4px 10px;font-size:13px;font-weight:700}
        .login-body{position:relative;padding:30px 60px 34px}
        .login-logo{display:flex;justify-content:center;align-items:center;gap:12px;margin:6px 0 24px}
        .logo-mark{font-size:26px;font-weight:700;color:#00A9A6}
        .logo-text{font-size:22px;font-weight:700;color:#666}
        .logo-sub{font-size:13px;color:#00A9A6;text-align:center;margin-top:2px}
        .logo-badge{width:50px;height:50px;border:3px solid #10b981;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#10b981;font-size:11px;font-weight:700}
        .mode-row{display:flex;gap:12px;flex-wrap:wrap;margin:0 0 18px}
        .mode-card{width:79px;height:60px;border:1px solid #d4d4d4;background:#F9F9F9;box-shadow:0 0 8px 5px rgba(0,0,0,.12);cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:12px;color:#666;line-height:1.1}
        .mode-card .mode-icon{font-size:13px;font-weight:700;margin-bottom:6px}
        .mode-card.active{background:#00A9A6;color:#fff;border-color:#00A9A6}
        .area-card{width:118px}
        .form-grid{display:grid;grid-template-columns:105px 1fr;gap:10px 14px;align-items:center}
        .form-grid label{font-size:12px}
        .text{width:100%;height:24px;border:1px solid #b9b9b9;background:#fff;padding:2px 7px;font:12px Arial,sans-serif}
        .text:focus{outline:2px solid #2f2f2f;border-color:#2f2f2f;background:#eef6fb}
        .plant-row{display:none}
        .plant-row.visible{display:contents}
        .status{margin-bottom:12px;padding:8px 10px;border:1px solid #d6b0b0;background:#fff4f4;color:#8b1f1f;font-size:12px}
        .hint{margin-top:14px;font-size:12px;color:#666}
        .submit-row{margin-top:18px}
        .submit-btn{width:100%;height:30px;border:1px solid #8fb48f;background:#b9ddb9;color:#111;font:700 13px Arial,sans-serif;cursor:pointer}
        .submit-btn:hover{filter:brightness(.98)}
        .demo-note{margin-top:10px;font-size:11px;color:#666}
        @media (max-width: 640px){
          .login-box{width:100%}
          .login-body{padding:24px 18px 28px}
          .form-grid{grid-template-columns:1fr}
        }
      </style></head><body>';
    echo '<div class="login-shell"><div class="login-box">';
    echo '<div class="login-header">Unibag ERP</div>';
    echo '<div class="login-body">';
    echo '<div class="login-logo"><div><div class="logo-mark">Unibag</div><div class="logo-sub">Bolsas con vida</div></div><div class="logo-badge">PRUEBA</div></div>';
    if ($error !== null && $error !== '') {
        echo '<div class="status">' . h($error) . '</div>';
    }
    echo '<form method="post" action="/login" id="login-form">';
    echo '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
    echo '<input type="hidden" name="appmode" id="menu_appmode" value="0">';
    echo '<input type="hidden" name="erp_area" id="erp_area" value="' . h($erpArea) . '">';
    echo '<div class="mode-row">';
    foreach ($areas as $area) {
        $isActiveArea = (string)$area['id'] === $erpArea;
        echo '<button type="button" class="mode-card area-card' . ($isActiveArea ? ' active' : '') . '" data-erp-area="' . h((string)$area['id']) . '"' . ($isActiveArea ? ' aria-current="true"' : '') . '>';
        echo '<span class="mode-icon">' . h(erpAreaShortLabel((string)$area['id'])) . '</span>';
        echo '<span>' . h((string)$area['label']) . '</span>';
        echo '</button>';
    }
    echo '</div>';
    echo '<div class="form-grid">';
    echo '<label for="user_login">Usuario</label>';
    echo '<input class="text" id="user_login" name="user_login" type="text" value="' . h($userLogin) . '" autocomplete="username">';
    echo '<label for="user_pass">Contraseña</label>';
    echo '<input class="text" id="user_pass" name="user_pass" type="password" autocomplete="current-password">';
    echo '<label for="user_company_id">Empresa</label>';
    echo '<select class="text" id="user_company_id" name="user_company_id">';
    foreach ($companies as $company) {
        $selected = $company['id'] === $companyId ? ' selected' : '';
        echo '<option value="' . (int)$company['id'] . '"' . $selected . '>' . h((string)$company['label']) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '<div class="submit-row"><button class="submit-btn" type="submit">Acceder</button></div>';
    echo '<div class="demo-note">Acceso demo: usuario <b>demo</b> y clave <b>demo123</b>.</div>';
    echo '</form>';
    echo '</div></div></div>';
    echo '<script>
      document.getElementById("user_login")?.focus();
      (function () {
        var areaInput = document.getElementById("erp_area");
        var areaButtons = document.querySelectorAll("[data-erp-area]");
        if (!areaInput || !areaButtons.length) return;
        areaButtons.forEach(function (button) {
          button.addEventListener("click", function () {
            var area = button.getAttribute("data-erp-area") || "RECEPTION";
            areaInput.value = area;
            areaButtons.forEach(function (item) {
              item.classList.remove("active");
              item.removeAttribute("aria-current");
            });
            button.classList.add("active");
            button.setAttribute("aria-current", "true");
          });
        });
      })();
    </script>';
    echo '</body></html>';
    exit;
}

function render(string $title, string $body): void
{
    http_response_code(200);
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($title) . '</title>';
    echo '<style>
        *{box-sizing:border-box}
        html{-webkit-text-size-adjust:100%}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:0;background:#f6f7f9;color:#111;overflow-x:hidden}
        main{padding:16px; max-width:1100px; margin:0 auto}
        .card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px}
        .grid{display:grid;grid-template-columns: 320px 1fr;gap:14px}
        .trace-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:14px}
        .trace-stack{display:grid;grid-template-columns:1fr;gap:14px}
        .kpi-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}
        .kpi-card{background:#fff;border:1px solid #d0d5dd;border-radius:10px;padding:12px}
        .kpi-label{font-size:12px;color:#667085;margin-bottom:6px}
        .kpi-value{font-size:24px;font-weight:800;line-height:1}
        .kpi-sub{font-size:12px;color:#475467;margin-top:6px}
        .dashboard-grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);gap:14px}
        .dashboard-alert{border:1px solid #d0d5dd;border-radius:10px;padding:10px 12px;background:#fff}
        .dashboard-alert.warning{border-color:#f7b955;background:#fff9eb}
        .dashboard-alert.info{border-color:#84c5ff;background:#f5faff}
        .dashboard-alert.success{border-color:#86d7a0;background:#f5fff8}
        .bar-track{height:8px;background:#edf2f7;border-radius:999px;overflow:hidden}
        .bar-fill{height:100%;background:#00A9A6;border-radius:999px}
        .trace-roll-card{border:1px solid #dbe4ea;border-radius:10px;padding:10px 12px;background:#fbfdff}
        .trace-roll-header{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:8px}
        .trace-roll-title{font-size:15px;font-weight:800;line-height:1.2}
        .trace-roll-subtitle{margin-top:2px}
        .trace-roll-link{font-size:12px;font-weight:700;color:#0f766e;text-decoration:none;white-space:nowrap}
        .trace-roll-link:hover{text-decoration:underline}
        .trace-roll-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px}
        .trace-roll-stat{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:8px}
        .trace-roll-stat .value{font-size:13px;font-weight:700;line-height:1.3}
        .trace-roll-events{margin-top:8px;border-top:1px solid #e5e7eb;padding-top:8px}
        .trace-roll-events .item{padding:7px 0;border-bottom:1px solid #eef2f7}
        .trace-roll-events .item:last-child{border-bottom:none}
        .trace-roll-event-top{display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}
        .trace-roll-event-line{margin-top:2px;font-size:13px;color:#374151;line-height:1.35}
        .row{display:flex;gap:10px;flex-wrap:wrap}
        .row.nowrap{flex-wrap:nowrap}
        label{display:block;font-size:12px;color:#374151;margin-bottom:4px}
        input,select,textarea{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;background:#fff;font:inherit}
        input:disabled,select:disabled{opacity:1;background:#f3f4f6;color:#111}
        .btn{display:inline-flex;align-items:center;justify-content:center;background:#0aa;color:#fff;border:none;border-radius:8px;padding:10px 12px;font-weight:600;text-decoration:none;cursor:pointer;text-align:center}
        .btn.secondary{background:#374151}
        .panel{background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:12px;overflow:hidden}
        .ot-stage-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
        .ot-stage-card{background:#fff;border:1px solid #d0d5dd;border-radius:10px;padding:10px 12px}
        .ot-stage-card.current{border-color:#259492;background:#eef8f8}
        .ot-stage-card.done{border-color:#b7e4d7;background:#f6fffb}
        .ot-meta-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px}
        .ot-meta{background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:10px}
        .ot-meta .value{font-size:14px;font-weight:800;line-height:1.3}
        .ot-tasks{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px}
        .ot-task{background:#fcfcfd;border:1px solid #e4e7ec;border-radius:8px;padding:10px}
        .ot-request-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(320px,0.8fr);gap:12px}
        .fold{border:1px solid #e5e7eb;border-radius:10px;background:#fff;margin-top:10px;overflow:hidden}
        .fold summary{cursor:pointer;list-style:none;padding:11px 12px;font-weight:800;background:#fbfcfd}
        .fold summary::-webkit-details-marker{display:none}
        .fold .fold-body{padding:12px;border-top:1px solid #eef2f7}
        .table-compact th,.table-compact td{padding:7px 8px;font-size:12px}
        @media (max-width: 900px){
          main{padding:12px}
          .grid{grid-template-columns:1fr}
          .row.nowrap{flex-wrap:wrap}
          .trace-grid{grid-template-columns:1fr}
          .dashboard-grid{grid-template-columns:1fr}
          .kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
          .ot-stage-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
          .ot-request-grid{grid-template-columns:1fr}
          .prod-shell{flex-direction:column}
        }
        @media (max-width: 640px){
          main{padding:10px}
          .card,.panel,.legacy-sheet-card,.legacy-form-card,.kpi-card{padding:10px}
          .row{gap:8px}
          .row > *{flex:1 1 100% !important;min-width:0 !important}
          .btn,.legacy-primary-btn,.legacy-placeholder-btn{width:100%;max-width:100%}
          .trace-roll-meta,.ot-meta-grid,.ot-tasks{grid-template-columns:1fr}
          .kpi-grid{grid-template-columns:1fr}
          .ot-stage-grid{grid-template-columns:1fr}
          .topbar .inner,.subbar .inner{padding:8px 10px}
          .menu,.submenu,.top-right{width:100%;flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;padding-bottom:4px}
          .menu a,.subitem,.top-right .pill{white-space:nowrap;flex:none}
          .prod-sidebar{width:min(300px,86vw)}
          .prod-topbar{align-items:flex-start}
          .prod-topbar-title{width:100%}
          .prod-topbar-right{width:100%;justify-content:flex-start}
          .prod-topbar-brand{font-size:22px}
          .prod-main{padding:12px}
          .legacy-actionbar{align-items:stretch}
          .legacy-sheet-card,.legacy-form-card,.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
          .card > table,.panel > table,.legacy-sheet-card > table,.legacy-form-card > table{display:block;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
          .legacy-sheet-table{min-width:520px}
          .legacy-label-cell,.legacy-value-cell{width:auto}
          .trace-table{min-width:480px}
          .trace-roll-link{white-space:normal}
          th,td{padding:6px;vertical-align:top}
          iframe{max-width:100%}
          #label_preview{height:320px}
        }
        table{width:100%;border-collapse:collapse}
        th,td{border-bottom:1px solid #e5e7eb;padding:8px;text-align:left;font-size:13px}
        .table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
        .trace-table{min-width:640px}
        .err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px;border-radius:10px}
        .ok{background:#ecfeff;border:1px solid #a5f3fc;color:#155e75;padding:10px;border-radius:10px}
        .muted{color:#6b7280;font-size:12px}

        .topbar{background:#009f9f;color:#fff}
        .topbar .inner{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:10px 14px}
        .menu{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
        .menu a{color:#fff;text-decoration:none;font-weight:600;font-size:14px;padding:8px 10px;border-radius:6px}
        .menu a:hover{background:rgba(255,255,255,.12)}
        .menu a.active{background:#f3f4f6;color:#111}
        .top-right{display:flex;gap:6px;flex-wrap:wrap;align-items:center;padding:4px 8px}
        .top-right select{width:auto;background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.25);padding:6px 10px;border-radius:6px;font-size:12px}
        .top-right .pill{display:flex;align-items:center;gap:8px;color:#fff;text-decoration:none;font-weight:600;font-size:13px;padding:6px 10px;border-radius:6px;background:rgba(0,0,0,.12)}
        .top-right .pill:hover{background:rgba(0,0,0,.18)}
        .subbar{background:#f1f5f9;border-bottom:1px solid #e5e7eb}
        .subbar .inner{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:8px 14px}
        .submenu{display:flex;gap:14px;flex-wrap:wrap;align-items:center}
        .subitem{display:inline-flex;gap:8px;align-items:center;color:#111;text-decoration:none;font-weight:700;font-size:13px}
        .subitem:hover{text-decoration:underline}
        .prod-shell{display:flex;min-height:100vh;background:#dfe5ea;position:relative}
        .prod-sidebar-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.35);border:none;padding:0;margin:0;opacity:0;pointer-events:none;transition:opacity .2s ease;z-index:39}
        .prod-sidebar{width:228px;background:#157f7d;color:#fff;display:flex;flex-direction:column;box-shadow:2px 0 6px rgba(15,23,42,.12);position:fixed;top:0;left:0;bottom:0;z-index:40;transform:none;transition:transform .22s ease}
        .prod-brand{padding:10px 10px 8px;background:#0b9694}
        .prod-brand-box{background:#f4f7f7;border-radius:4px;padding:7px 10px;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:inset 0 0 0 1px rgba(0,0,0,.04)}
        .prod-brand-mark{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:2px;background:#18a7a4;color:#fff;font-size:18px;font-weight:800;line-height:1}
        .prod-brand-word{font-size:21px;font-weight:800;line-height:1;color:#8a9196;letter-spacing:.01em}
        .prod-nav-title{padding:8px 14px;font-size:12px;font-weight:700;color:#d7f4f3;background:rgba(0,0,0,.12)}
        .prod-nav-folder{margin-bottom:1px;background:#166764}
        .prod-nav-folder-head{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#147c79;font-size:14px;font-weight:700;color:#fff}
        .prod-nav-folder.active .prod-nav-folder-head{background:#0f8f8d}
        .prod-nav-folder-label{display:flex;align-items:center;gap:8px}
        .prod-nav-folder-icon{display:inline-block;position:relative;flex:none}
        .prod-nav-folder-icon.machine{width:16px;height:12px;border:2px solid #fff;border-radius:2px}
        .prod-nav-folder-icon.machine::after{content:"";position:absolute;left:5px;right:5px;bottom:-4px;height:2px;background:#fff}
        .prod-nav-folder-icon.orders{width:14px;height:14px;border:2px solid #fff;border-radius:2px}
        .prod-nav-folder-icon.orders::before{content:"";position:absolute;left:2px;right:2px;top:3px;height:2px;background:#fff;box-shadow:0 4px 0 #fff}
        .prod-nav-folder-icon.messages{width:16px;height:11px;border:2px solid #fff;border-radius:2px}
        .prod-nav-folder-icon.messages::before{content:"";position:absolute;left:1px;right:1px;top:1px;height:2px;background:#fff;transform:skewY(-24deg)}
        .prod-nav-folder-caret{font-size:15px;line-height:1;color:#fff}
        .prod-nav-folder-body{padding:5px 0 8px;background:#166764}
        .prod-nav-sublink,.prod-nav-static{display:flex;align-items:center;gap:8px;padding:9px 14px 9px 16px;color:#b9c7c7;text-decoration:none;font-size:12px;font-weight:600}
        .prod-nav-sublink::before,.prod-nav-static::before{content:"";width:15px;height:15px;border-radius:999px;background:#b9b1b1;flex:none}
        .prod-nav-sublink:hover{background:rgba(255,255,255,.04);color:#fff}
        .prod-nav-sublink.active{background:rgba(255,255,255,.03);color:#fff;font-weight:500}
        .prod-nav-sublink.active::before{background:#d8d1d1}
        .prod-nav-static{opacity:.92;cursor:default}
        .prod-main-wrap{flex:1;display:flex;flex-direction:column;min-width:0;margin-left:228px;width:calc(100% - 228px)}
        .prod-topbar{background:#08a8a6;color:#fff;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;box-shadow:0 2px 6px rgba(15,23,42,.08)}
        .prod-topbar-title{display:flex;align-items:center;gap:10px;font-size:18px;font-weight:800}
        .prod-topbar-brand{background:#fff;color:#0aa;padding:7px 10px;border-radius:4px;font-size:22px;font-weight:800;line-height:1}
        .prod-toggle-btn{display:none;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid rgba(255,255,255,.3);background:rgba(0,0,0,.12);color:#fff;border-radius:8px;cursor:pointer;font-size:16px;font-weight:800;line-height:1}
        .prod-toggle-btn:hover{background:rgba(0,0,0,.2)}
        .prod-topbar-right{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .prod-user-pill{background:rgba(0,0,0,.12);padding:8px 10px;border-radius:6px;font-size:13px;font-weight:700}
        .prod-user-pill.logout{color:#fff;text-decoration:none}
        .prod-main{padding:18px;max-width:none}
        .legacy-screen-title{font-size:18px;font-weight:800;color:#4b5563;margin-bottom:10px}
        .legacy-breadcrumb{background:#fff;border:1px solid #d9dde3;border-radius:6px;padding:10px 12px;color:#6b7280;font-size:13px;margin-bottom:8px}
        .legacy-actionbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;background:#f3f4f6;border:1px solid #d8dadd;border-radius:6px;padding:8px 10px;margin-bottom:10px}
        .legacy-primary-btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;background:#10b156;color:#fff;text-decoration:none;font-weight:800;border-radius:2px;min-width:200px}
        .legacy-primary-btn:hover{filter:brightness(.96)}
        .legacy-sheet-card{background:#efefef;border:1px solid #d4d4d4;border-radius:6px;padding:10px}
        .legacy-sheet-table{width:100%;border-collapse:collapse;background:#fff}
        .legacy-sheet-table th{background:#145e5b;color:#fff;font-size:14px;font-weight:800;text-align:center;padding:6px;border:1px solid #145e5b}
        .legacy-sheet-table td{border:1px solid #d6d6d6;padding:6px 8px;font-size:13px}
        .legacy-label-cell{background:#efefef;color:#5f6368;font-weight:700;width:15%}
        .legacy-value-cell{background:#fff;color:#374151;width:35%}
        .legacy-placeholder-btn{display:inline-flex;align-items:center;justify-content:center;min-width:180px;padding:2px 10px;background:#a3a3a3;color:#fff;border-radius:1px;font-size:12px;font-weight:700}
        .legacy-form-card{background:#efefef;border:1px solid #d4d4d4;border-radius:6px;padding:10px}
        .legacy-form-card .fold{border:1px solid #d6d6d6;background:#fff}
        .legacy-form-card .fold summary{background:#e5e7eb;border-bottom:1px solid #d6d6d6;padding:8px 10px;font-size:13px}
        .legacy-form-card label{display:block;font-size:12px;font-weight:700;color:#5f6368;margin-bottom:4px}
        .legacy-form-card input,.legacy-form-card select,.legacy-form-card textarea{border:1px solid #cfd4dc;border-radius:2px;background:#fff;min-height:34px}
        .legacy-form-card .btn,.legacy-form-card .btn.secondary{border-radius:2px}
        .legacy-note{background:#f7f7f7;border:1px solid #d6d6d6;padding:8px 10px;color:#6b7280;font-size:12px}
        .legacy-anilox-card .legacy-sheet-table th{background:#e9ecef;color:#145e5b;border-color:#d5dadd}
        .legacy-anilox-unit-cell{width:9%;text-align:center;font-weight:800}
        .legacy-anilox-input,.legacy-anilox-select{width:100%;min-height:34px;border:1px solid #cfd4dc;border-radius:3px;padding:6px 8px;background:#fff}
        .legacy-anilox-actions{white-space:nowrap;text-align:center;width:100px}
        .anilox-move-btn{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;margin:0 2px;border:1px solid #cfd4dc;border-radius:4px;background:#f5f7fa;color:#0f766e;font-weight:800;cursor:pointer}
        .anilox-move-btn:hover:not(:disabled){background:#e8f3f2}
        .anilox-move-btn:disabled{opacity:.45;cursor:not-allowed}
        .legacy-anilox-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
        .legacy-anilox-help{font-size:12px;color:#5f6368}
        .legacy-setup-actions{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:0;border-radius:4px;overflow:hidden;margin:0 0 12px}
        .legacy-setup-action{display:flex;align-items:center;justify-content:center;min-height:42px;padding:10px 12px;color:#fff;font-size:13px;font-weight:800;text-decoration:none;border-right:1px solid rgba(255,255,255,.22)}
        .legacy-setup-action:last-child{border-right:0}
        .legacy-setup-action.request{background:#0b7d5c}
        .legacy-setup-action.materials{background:#4256a5}
        .legacy-setup-action.production{background:#12805c}
        .legacy-setup-action.maintenance{background:#d94841}
        .legacy-setup-action.pause{background:#a9a9a9}
        .legacy-setup-action.consumption{background:#d8a103}
        .legacy-setup-action.active{box-shadow:inset 0 -3px 0 rgba(255,255,255,.35)}
        .legacy-setup-event-table th{text-align:center}
        .legacy-setup-event-table td{vertical-align:middle}
        .legacy-setup-finish-btn{background:#d8a103;border-color:#c89402;color:#fff}
        .legacy-setup-finish-btn:hover{background:#c89402}
        .autocontrol-panel{margin-bottom:12px;border:1px solid #d6d6d6;border-radius:4px;overflow:hidden;background:#fff}
        .autocontrol-panel-header{background:#ebe600;color:#111;font-size:17px;font-weight:900;letter-spacing:.02em;text-align:center;padding:8px 12px}
        .autocontrol-table{width:100%;border-collapse:collapse;table-layout:fixed}
        .autocontrol-table td{width:25%;padding:8px 12px;border-top:1px solid #d9dde3;border-right:1px solid #e5e7eb;background:#fff;vertical-align:top;box-sizing:border-box}
        .autocontrol-table td:last-child{border-right:0}
        .autocontrol-checkline{display:grid;grid-template-columns:16px minmax(0,1fr);align-items:start;column-gap:8px;margin:0;font-size:12.5px;font-weight:600;color:#334155;line-height:1.3}
        .autocontrol-checkline input{margin:2px 0 0 0;width:14px;height:14px}
        .autocontrol-checkline span{display:block;word-break:break-word}
        .approval-actions{display:grid;grid-template-columns:1fr;gap:8px;margin:12px 0}
        .approval-action-btn{display:flex;align-items:center;justify-content:center;min-height:26px;padding:6px 12px;color:#fff;text-decoration:none;font-size:13px;font-weight:800;border-radius:2px}
        .approval-action-btn.area{background:#f15b40}
        .approval-action-btn.supervisor{background:#5ea7cf}
        .approval-action-btn.hidden{display:none}
        .approval-panel-header{position:relative;display:flex;align-items:center;min-height:28px;background:#8dcc4b;color:#111;padding:4px 12px;border-radius:4px;font-size:14px;font-weight:900;letter-spacing:.01em;overflow:hidden}
        .approval-panel-header .title{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);text-align:center;line-height:1;pointer-events:none;max-width:calc(100% - 190px);white-space:nowrap}
        .approval-header-actions{position:relative;z-index:1;display:flex;align-items:center;flex:0 0 auto}
        .approval-mark-all{display:inline-flex;align-items:center;gap:6px;padding:0;border:0;background:transparent;font-size:12px;font-weight:800;color:#111;cursor:pointer;white-space:nowrap}
        .approval-mark-all input{margin:0;width:13px;height:13px;accent-color:#fff}
        .approval-mark-all span{display:block;line-height:1}
        .approval-validation{display:none;grid-template-columns:minmax(180px,220px) minmax(180px,220px) minmax(220px,1fr);gap:8px;align-items:stretch;margin-top:12px}
        .approval-validation.visible{display:grid}
        .approval-validation input{width:100%;min-height:36px;border:1px solid #cbd5e1;border-radius:4px;padding:8px 10px;background:#eef3f9;font-size:14px;font-weight:700;color:#0f172a}
        .approval-validation input::placeholder{color:#475569;opacity:1}
        .approval-validation .btn{min-height:36px;width:100%;display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:800}
        .approval-validation-status{margin-top:12px}
        .approval-role-note{background:#fff;border:1px solid #d9dde3;border-radius:6px;padding:10px 12px;color:#4b5563;font-size:13px;margin-bottom:12px}
        .finish-production-card{background:#f3f3f3;padding:8px}
        .finish-production-card form{margin:0}
        .finish-form-row{display:grid;align-items:center;border-bottom:1px solid #dddddd;min-height:32px}
        .finish-form-row:last-child{border-bottom:0}
        .finish-form-row-inline{grid-template-columns:102px minmax(0,1fr) auto minmax(0,1.2fr);column-gap:6px}
        .finish-form-row-production{grid-template-columns:102px 140px minmax(90px,1fr);column-gap:6px}
        .finish-form-row-comments{grid-template-columns:102px minmax(0,1fr);column-gap:6px;padding:4px 0}
        .finish-label,.finish-merma-title{font-size:11px;font-weight:700;color:#5f6368;padding:4px 6px;line-height:1.2;min-width:0;overflow-wrap:anywhere}
        .finish-static-value{font-size:12px;color:#57748b;padding:4px 6px;line-height:1.2;min-width:0}
        .finish-inline-input input,.finish-short-input input,.finish-wide-input input,.finish-comments-wrap textarea,.finish-merma-weight input,.finish-merma-comment input,.finish-merma-unit select{width:100%;border:1px solid #cfd4dc;border-radius:3px;background:#fff;min-height:26px;padding:3px 7px;font-size:12px}
        .finish-comments-wrap textarea{min-height:38px;resize:vertical}
        .finish-inline-input{max-width:124px}
        .finish-short-input{max-width:68px}
        .finish-wide-input{max-width:300px}
        .finish-inline-suffix{font-size:11px;color:#666;line-height:1;min-width:0;white-space:nowrap}
        .finish-form-row-production .finish-inline-input{max-width:none;min-width:0}
        .finish-form-row-production .finish-inline-input input{min-width:0}
        .finish-form-row-production .finish-label{white-space:nowrap}
        .finish-button-inline{max-width:118px}
        .finish-grey-btn{background:#b5b5b5;border-color:#aaaaaa;color:#fff}
        .finish-grey-btn:hover{background:#a7a7a7}
        .finish-red-btn{background:#d94141;border-color:#cf3a3a;color:#fff}
        .finish-red-btn:hover{background:#c83737}
        .finish-yellow-btn{background:#d8a103;border-color:#c89402;color:#fff}
        .finish-yellow-btn:hover{background:#c89402}
        .finish-merma-block{margin-top:6px}
        .finish-merma-row{grid-template-columns:82px 190px 64px 74px 86px minmax(0,1fr);column-gap:6px}
        .finish-merma-detail,.finish-merma-comment-label{font-size:11px;color:#5f6368;padding:4px 6px;line-height:1.2}
        .finish-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:10px}
        .finish-actions-left,.finish-actions-right{display:flex;gap:4px;align-items:center}
        .finish-actions .btn{min-width:170px;min-height:30px;border-radius:0;padding:6px 10px;font-size:12px}
        @media (max-width: 980px){
            .prod-sidebar-backdrop{display:block}
            .prod-sidebar{transform:translateX(-100%)}
            .prod-shell.menu-open .prod-sidebar{transform:translateX(0)}
            .prod-shell.menu-open .prod-sidebar-backdrop{opacity:1;pointer-events:auto}
            .prod-main-wrap{margin-left:0;width:100%}
            .prod-toggle-btn{display:inline-flex}
            .legacy-setup-actions{grid-template-columns:1fr 1fr}
            .autocontrol-table td{padding:8px 10px}
            .approval-panel-header{align-items:center;min-height:28px;padding:4px 10px}
            .approval-panel-header .title{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);max-width:calc(100% - 170px);text-align:center;pointer-events:none;white-space:nowrap;font-size:13px}
            .approval-mark-all{font-size:11px;gap:5px}
            .approval-mark-all input{width:12px;height:12px}
            .approval-validation{grid-template-columns:minmax(140px,1fr) minmax(140px,1fr) minmax(180px,1fr)}
            .finish-production-card{padding:6px}
            .finish-form-row-inline{grid-template-columns:88px minmax(90px,120px) 46px minmax(150px,1fr);column-gap:4px;row-gap:0}
            .finish-form-row-production{grid-template-columns:88px 120px minmax(88px,1fr);column-gap:4px}
            .finish-form-row-comments{grid-template-columns:92px minmax(0,1fr)}
            .finish-button-inline,.finish-inline-input,.finish-short-input,.finish-wide-input{max-width:none}
            .finish-merma-row{grid-template-columns:58px 120px 58px 64px 76px minmax(150px,1fr);column-gap:4px}
            .finish-actions{flex-direction:column;align-items:stretch}
            .finish-actions-left,.finish-actions-right{width:100%}
            .finish-actions-left .btn,.finish-actions-right .btn{flex:1;min-width:0}
        }
        @media (max-width: 640px){
            .finish-form-row-production{grid-template-columns:92px minmax(0,1fr)}
            .finish-form-row-production .finish-inline-suffix{white-space:normal}
            .finish-form-row-inline{grid-template-columns:92px minmax(0,1fr);row-gap:4px}
            .finish-merma-row{grid-template-columns:92px minmax(0,1fr)}
        }
    </style></head><body>';
    $currentPath = (string)($GLOBALS['path'] ?? '/');
    $currentArea = normalizeErpArea((string)($_SESSION['erp_area'] ?? 'ERP'));
    $requestedArea = detectRequestedArea($currentPath);
    $areaPermissions = sessionAreaPermissions();
    $displayArea = ($currentArea === 'ERP' && userCanAccessArea('ERP', $areaPermissions))
        ? 'ERP'
        : (userCanAccessArea($requestedArea, $areaPermissions) ? $requestedArea : $currentArea);

    $link = function (string $href, string $label, bool $isActive) : string {
        return '<a href="' . h($href) . '"' . ($isActive ? ' class="active"' : '') . '>' . h($label) . '</a>';
    };

    $areaDefinitions = erpAreaDefinitions();
    $appModeLabel = (string)($areaDefinitions[$displayArea]['label'] ?? $_SESSION['erp_area_label'] ?? $_SESSION['app_mode_label'] ?? 'ERP');
    $companyName = (string)($_SESSION['company_name'] ?? 'UNIBAG CHILE');
    $displayName = (string)($_SESSION['auth_display_name'] ?? $_SESSION['operator_name'] ?? 'Operador Demo');
    if ($currentArea === 'PRODUCTION' && isProductionPath($currentPath)) {
        renderProductionShell($body, $currentPath, $displayName, $companyName);
        echo '</body></html>';
        exit;
    }

    echo '<div class="topbar"><div class="inner">';
    echo '<nav class="menu">';
    if ($displayArea === 'ERP' && userCanAccessArea('ERP', $areaPermissions)) {
        echo $link('/', 'Informes', $currentPath === '/' || str_starts_with($currentPath, '/reports/inventory'));
        echo $link('/work-orders?view=pending', 'Trazabilidad', str_starts_with($currentPath, '/work-orders') || str_starts_with($currentPath, '/chemicals') || str_starts_with($currentPath, '/cut') || str_starts_with($currentPath, '/boxes') || str_starts_with($currentPath, '/pallets'));
    } elseif ($displayArea === 'RECEPTION') {
        echo $link('/purchase-orders?status=active&supplier_type=NATIONAL', 'Recepción', str_starts_with($currentPath, '/purchase-orders') || str_starts_with($currentPath, '/import-containers'));
        echo $link('/stock', 'Inventario', str_starts_with($currentPath, '/stock') || str_starts_with($currentPath, '/pallets') || str_starts_with($currentPath, '/maquila') || str_starts_with($currentPath, '/cliches') || (inventoryNavigationContext() && (str_starts_with($currentPath, '/work-orders') || str_starts_with($currentPath, '/rolls') || str_starts_with($currentPath, '/boxes'))));
    } else {
        echo $link('/production/shifts', 'Producción', str_starts_with($currentPath, '/production/shifts') || str_starts_with($currentPath, '/work-orders') || str_starts_with($currentPath, '/chemicals') || str_starts_with($currentPath, '/cut') || str_starts_with($currentPath, '/boxes') || str_starts_with($currentPath, '/pallets'));
    }
    echo '</nav>';
    echo '<div class="top-right">';
    echo '<select aria-label="Sistema" disabled><option>' . h($appModeLabel) . '</option></select>';
    echo '<a class="pill" href="#"><span>' . h($companyName) . '</span></a>';
    echo '<a class="pill" href="#"><span>' . h($displayName) . '</span></a>';
    echo '<a class="pill" href="/logout">Salir</a>';
    echo '</div>';
    echo '</div></div>';

    echo '<div class="subbar"><div class="inner">';
    $activeModule = 'home';
    if ($displayArea === 'ERP') {
        if ($currentPath === '/' || str_starts_with($currentPath, '/reports/inventory')) {
            $activeModule = 'reports';
        } elseif (str_starts_with($currentPath, '/work-orders') || str_starts_with($currentPath, '/chemicals') || str_starts_with($currentPath, '/cut') || str_starts_with($currentPath, '/boxes') || str_starts_with($currentPath, '/pallets')) {
            $activeModule = 'traceability';
        }
    } else {
        if (str_starts_with($currentPath, '/purchase-orders') || str_starts_with($currentPath, '/import-containers')) { $activeModule = 'reception'; }
        elseif (str_starts_with($currentPath, '/stock') || str_starts_with($currentPath, '/maquila') || str_starts_with($currentPath, '/cliches') || (str_starts_with($currentPath, '/pallets') && currentSessionArea() === 'RECEPTION') || (inventoryNavigationContext() && (str_starts_with($currentPath, '/work-orders') || str_starts_with($currentPath, '/rolls') || str_starts_with($currentPath, '/boxes')))) { $activeModule = 'inventory'; }
        elseif (str_starts_with($currentPath, '/production/shifts') || str_starts_with($currentPath, '/work-orders') || str_starts_with($currentPath, '/chemicals') || str_starts_with($currentPath, '/cut') || str_starts_with($currentPath, '/boxes') || str_starts_with($currentPath, '/pallets')) { $activeModule = 'production'; }
    }

    echo '<div class="submenu">';
    if ($activeModule === 'reports') {
        echo '<a class="subitem" href="/"><span>Panel ERP</span></a>';
        echo '<a class="subitem" href="/reports/inventory"><span>Informe inventario</span></a>';
    } elseif ($activeModule === 'traceability') {
        echo '<a class="subitem" href="/work-orders?view=pending"><span>OT pendientes</span></a>';
        echo '<a class="subitem" href="/work-orders?view=active"><span>OT en curso</span></a>';
        echo '<a class="subitem" href="/work-orders?view=closed"><span>Histórico OT</span></a>';
        echo '<a class="subitem" href="/cut"><span>Corte</span></a>';
        echo '<a class="subitem" href="/chemicals/weighings"><span>Tintas</span></a>';
        echo '<a class="subitem" href="/pallets"><span>Pallets</span></a>';
    } elseif ($activeModule === 'reception') {
        echo '<a class="subitem" href="/purchase-orders?status=active&supplier_type=NATIONAL"><span>Recepción nacional</span></a>';
        echo '<a class="subitem" href="/import-containers?status=active"><span>Importación</span></a>';
        echo '<a class="subitem" href="/purchase-orders?status=complete"><span>Recepciones finalizadas</span></a>';
    } elseif ($activeModule === 'inventory') {
        echo '<a class="subitem" href="/stock"><span>Inventario</span></a>';
        echo '<a class="subitem" href="/stock/inventory-counts"><span>Toma inventario</span></a>';
        echo '<a class="subitem" href="/stock/material-requests"><span>Solicitudes</span></a>';
        echo '<a class="subitem" href="/stock/transfers"><span>Traspaso</span></a>';
        echo '<a class="subitem" href="/pallets"><span>Asignación de pallets</span></a>';
        echo '<a class="subitem" href="/maquila"><span>Maquila</span></a>';
        echo '<a class="subitem" href="/cliches"><span>Clisés</span></a>';
    } elseif ($activeModule === 'production') {
        echo '<a class="subitem" href="/production/shifts"><span>Asignación máquina</span></a>';
        echo '<a class="subitem" href="/work-orders?view=pending"><span>Iniciar nueva OT</span></a>';
        echo '<a class="subitem" href="/work-orders?view=active"><span>En cursos</span></a>';
        echo '<a class="subitem" href="/work-orders?view=closed"><span>Historico</span></a>';
        echo '<a class="subitem" href="/cut"><span>Proceso de corte</span></a>';
        echo '<a class="subitem" href="/chemicals/weighings"><span>Pesajes de tintas</span></a>';
    }
    echo '</div>';
    echo '</div></div>';
    echo '<main>' . $body . '</main></body></html>';
    exit;
}

/**
 * @param array<int,string> $colorSlots
 * @param array<int,array<string,mixed>> $chemicalInputs
 * @param array<int,array<string,mixed>> $savedAssignments
 * @param array<int,array<string,mixed>>|null $formSlots
 * @return array<int,array{unit_no:int,color_name:string,anilox_id:int}>
 */
function buildWorkOrderAniloxSlots(array $colorSlots, array $chemicalInputs, array $savedAssignments = [], ?array $formSlots = null): array
{
    $normalize = static function (array $slots): array {
        $normalized = [];
        foreach ($slots as $index => $slot) {
            $unitNo = isset($slot['unit_no']) ? (int)$slot['unit_no'] : ($index + 1);
            if ($unitNo < 1 || $unitNo > 6) {
                continue;
            }
            $normalized[$unitNo] = [
                'unit_no' => $unitNo,
                'color_name' => trim((string)($slot['color_name'] ?? '')),
                'anilox_id' => isset($slot['anilox_id']) ? max(0, (int)$slot['anilox_id']) : 0,
            ];
        }

        $result = [];
        for ($unit = 1; $unit <= 6; $unit++) {
            $result[] = $normalized[$unit] ?? [
                'unit_no' => $unit,
                'color_name' => '',
                'anilox_id' => 0,
            ];
        }

        return $result;
    };

    if (is_array($formSlots) && $formSlots !== []) {
        return $normalize($formSlots);
    }

    if ($savedAssignments !== []) {
        return $normalize($savedAssignments);
    }

    $detected = [];
    foreach ($colorSlots as $colorLabel) {
        $colorLabel = trim((string)$colorLabel);
        if ($colorLabel !== '' && $colorLabel !== '-' && !in_array($colorLabel, $detected, true)) {
            $detected[] = $colorLabel;
        }
    }
    foreach ($chemicalInputs as $input) {
        foreach ([
            trim((string)($input['chemical_name'] ?? '')),
            trim((string)($input['chemical_code'] ?? '')),
        ] as $candidate) {
            if ($candidate !== '' && !in_array($candidate, $detected, true)) {
                $detected[] = $candidate;
            }
        }
    }

    $detected = array_pad(array_slice($detected, 0, 6), 6, '');
    $result = [];
    for ($unit = 1; $unit <= 6; $unit++) {
        $result[] = [
            'unit_no' => $unit,
            'color_name' => (string)$detected[$unit - 1],
            'anilox_id' => 0,
        ];
    }

    return $result;
}

/**
 * @param array<int,string> $colorSlots
 * @param array<int,array<string,mixed>> $chemicalInputs
 * @param array<int,array<string,mixed>> $aniloxSlots
 * @return array<int,string>
 */
function extractSuggestedAniloxColors(array $colorSlots, array $chemicalInputs, array $aniloxSlots): array
{
    $values = [];
    foreach ($colorSlots as $colorLabel) {
        $colorLabel = trim((string)$colorLabel);
        if ($colorLabel !== '' && $colorLabel !== '-' && !in_array($colorLabel, $values, true)) {
            $values[] = $colorLabel;
        }
    }
    foreach ($chemicalInputs as $input) {
        foreach ([
            trim((string)($input['chemical_name'] ?? '')),
            trim((string)($input['chemical_code'] ?? '')),
        ] as $candidate) {
            if ($candidate !== '' && !in_array($candidate, $values, true)) {
                $values[] = $candidate;
            }
        }
    }
    foreach ($aniloxSlots as $slot) {
        $candidate = trim((string)($slot['color_name'] ?? ''));
        if ($candidate !== '' && !in_array($candidate, $values, true)) {
            $values[] = $candidate;
        }
    }

    return $values;
}

/**
 * @param array<int,array{unit_no:int,color_name:string,anilox_id:int}> $slots
 * @param array<int,array<string,mixed>> $aniloxCatalog
 * @param array<int,string> $suggestedColors
 */
function renderWorkOrderAniloxConfigCard(int $workOrderId, array $slots, array $aniloxCatalog, array $suggestedColors): string
{
    $datalistId = 'anilox-color-options-' . $workOrderId;
    $html = '<div class="legacy-sheet-card legacy-anilox-card" style="margin-bottom:12px">'
        . '<form method="post" action="/work-orders/' . $workOrderId . '/anilox-config">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<table class="legacy-sheet-table">'
        . '<thead><tr><th colspan="4">Configuración de Anilox</th></tr></thead>'
        . '<tbody data-anilox-table>';

    foreach ($slots as $slot) {
        $unitNo = (int)($slot['unit_no'] ?? 0);
        $selectedAniloxId = (int)($slot['anilox_id'] ?? 0);
        $html .= '<tr data-anilox-row>'
            . '<td class="legacy-label-cell legacy-anilox-unit-cell">'
            . '<span data-anilox-unit>' . $unitNo . '</span>'
            . '<input type="hidden" name="anilox_unit_no[]" value="' . $unitNo . '" data-anilox-unit-input>'
            . '</td>'
            . '<td class="legacy-value-cell">'
            . '<input class="legacy-anilox-input" type="text" name="anilox_color_name[]" value="' . h((string)($slot['color_name'] ?? '')) . '" list="' . h($datalistId) . '" placeholder="Color o tinta">'
            . '</td>'
            . '<td class="legacy-value-cell">'
            . '<select class="legacy-anilox-select" name="anilox_id[]">'
            . '<option value="0">&lt; Por favor seleccione &gt;</option>';
        foreach ($aniloxCatalog as $anilox) {
            $optionId = (int)($anilox['id'] ?? 0);
            $selected = $optionId === $selectedAniloxId ? ' selected' : '';
            $label = trim((string)($anilox['display_label'] ?? ''));
            if ($label === '') {
                $label = trim((string)($anilox['code'] ?? '')) . ' - ' . trim((string)($anilox['name'] ?? ''));
            }
            $html .= '<option value="' . $optionId . '"' . $selected . '>' . h($label) . '</option>';
        }
        $html .= '</select>'
            . '</td>'
            . '<td class="legacy-value-cell legacy-anilox-actions">'
            . '<button class="anilox-move-btn" type="button" data-direction="up" aria-label="Subir unidad">&#8593;</button>'
            . '<button class="anilox-move-btn" type="button" data-direction="down" aria-label="Bajar unidad">&#8595;</button>'
            . '</td>'
            . '</tr>';
    }

    $html .= '<tr><td class="legacy-value-cell" colspan="4">'
        . '<div class="legacy-anilox-footer">'
        . '<div class="legacy-anilox-help">Usa las flechas para reordenar las unidades y luego guarda la configuración.</div>'
        . '<button class="btn" type="submit">Guardar configuración anilox</button>'
        . '</div>'
        . '</td></tr>'
        . '</tbody></table>';

    if ($suggestedColors !== []) {
        $html .= '<datalist id="' . h($datalistId) . '">';
        foreach ($suggestedColors as $colorOption) {
            $html .= '<option value="' . h($colorOption) . '"></option>';
        }
        $html .= '</datalist>';
    }

    $html .= '</form></div>';

    return $html;
}


function renderWorkOrderMachineHeaderCard(
    int $workOrderId,
    ?array $sessionForDisplay,
    string $machineTypeLabel,
    string $machineLabel,
    string $helperValue,
    string $commentValue,
    array $helperOptions
): string {
    $hasActiveSession = (int)($sessionForDisplay['id'] ?? 0) > 0;
    $resolvedType = trim($machineTypeLabel) !== '' ? $machineTypeLabel : '-';
    $resolvedMachine = trim($machineLabel) !== '' ? $machineLabel : '-';
    $resolvedHelper = trim($helperValue);
    $resolvedComment = trim($commentValue);
    $options = array_values(array_unique(array_filter(array_map(
        static fn($value): string => trim((string)$value),
        $helperOptions
    ), static fn(string $value): bool => $value !== '')));
    natcasesort($options);
    $options = array_values($options);

    $html = '<div class="legacy-sheet-card" style="margin-bottom:12px">'
        . '<form method="post" action="/work-orders/' . $workOrderId . '/machine-header">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<table class="legacy-sheet-table">'
        . '<tbody>';

    $html .= '<tr>'
        . '<td class="legacy-label-cell">Tipo</td>'
        . '<td class="legacy-value-cell" colspan="3">' . h($resolvedType) . '</td>'
        . '</tr>';
    $html .= '<tr>'
        . '<td class="legacy-label-cell">Máquina</td>'
        . '<td class="legacy-value-cell" colspan="3">' . h($resolvedMachine) . '</td>'
        . '</tr>';
    $html .= '<tr>'
        . '<td class="legacy-label-cell">Ayudante</td>'
        . '<td class="legacy-value-cell" colspan="3">'
        . '<select name="shift_helper_name" style="max-width:260px;min-height:30px;padding:4px 8px;font-size:12px;border-radius:4px">'
        . '<option value="">&lt; Por favor seleccione &gt;</option>';
    foreach ($options as $helperOption) {
        $selected = $helperOption === $resolvedHelper ? ' selected' : '';
        $html .= '<option value="' . h($helperOption) . '"' . $selected . '>' . h($helperOption) . '</option>';
    }
    if ($resolvedHelper !== '' && !in_array($resolvedHelper, $options, true)) {
        $html .= '<option value="' . h($resolvedHelper) . '" selected>' . h($resolvedHelper) . '</option>';
    }
    $html .= '</select></td></tr>';
    $html .= '<tr>'
        . '<td class="legacy-label-cell">Comentarios</td>'
        . '<td class="legacy-value-cell" colspan="3">'
        . '<textarea name="shift_comments" rows="2" style="width:100%;min-height:38px;resize:vertical;border:2px solid #5f6368;border-radius:4px;padding:8px 10px;background:#fff">' . h($resolvedComment) . '</textarea>'
        . '</td></tr>';

    $buttonStyle = 'width:100%;min-width:0;border:0;border-radius:0;padding:8px 14px';
    if (!$hasActiveSession) {
        $buttonStyle .= ';opacity:.6;cursor:not-allowed';
    }
    $html .= '<tr><td class="legacy-value-cell" colspan="4" style="padding:10px">'
        . '<button class="legacy-primary-btn" type="submit" style="' . h($buttonStyle) . '"'
        . ($hasActiveSession ? '' : ' disabled title="Inicia o asigna un turno para continuar"')
        . '>&#9654; Iniciar alistamiento</button>'
        . '</td></tr>';

    $html .= '</tbody></table></form></div>';

    return $html;
}

function renderWorkOrderSetupScreen(
    array $ot,
    ?array $activeShiftSession,
    string $currentOperatorName,
    array $chemicalInputs = [],
    array $boxes = [],
    array $pallets = [],
    ?array $currentRoll = null,
    ?array $outputRoll = null,
    array $assignedCliches = [],
    array $processEvents = [],
    ?string $flashMessage = null,
    bool $flashIsError = false,
    bool $showFinishData = false
): void {
    $service = $GLOBALS['service'] ?? null;
    $sessionForDisplay = null;
    if ((int)($ot['shift_session_id'] ?? 0) > 0) {
        $sessionForDisplay = [
            'id' => (int)($ot['shift_session_id'] ?? 0),
            'machine_type_name' => (string)($ot['shift_machine_type_name'] ?? ''),
            'machine_name' => (string)($ot['shift_machine_name'] ?? ''),
            'operator_name' => (string)($ot['shift_operator_name'] ?? ''),
            'helper_name' => (string)($ot['shift_helper_name'] ?? ''),
            'comments' => (string)($ot['shift_comments'] ?? ''),
        ];
    } elseif ($activeShiftSession !== null) {
        $sessionForDisplay = $activeShiftSession;
    }

    $machineTypeLabel = trim((string)($sessionForDisplay['machine_type_name'] ?? ''));
    if ($machineTypeLabel === '') {
        $machineTypeLabel = 'Pendiente de asignación';
    }
    $machineLabel = trim((string)($sessionForDisplay['machine_name'] ?? ''));
    if ($machineLabel === '') {
        $machineLabel = trim((string)($ot['erp_machine_label'] ?? ''));
    }
    if ($machineLabel === '') {
        $machineLabel = 'Sin máquina asignada';
    }
    $operatorLabel = trim((string)($sessionForDisplay['operator_name'] ?? ''));
    if ($operatorLabel === '') {
        $operatorLabel = trim((string)($ot['erp_worker_name'] ?? ''));
    }
    if ($operatorLabel === '') {
        $operatorLabel = $currentOperatorName;
    }
    $helperLabel = trim((string)($sessionForDisplay['helper_name'] ?? ''));
    $commentsLabel = trim((string)($sessionForDisplay['comments'] ?? ''));
    $sheetText = trim((string)($ot['erp_plan_desc'] ?? '') . ' ' . (string)($ot['sku_final'] ?? ''));
    $stationLabel = $machineLabel;
    $targetQtyLabel = trim((string)($ot['erp_target_qty'] ?? '')) !== ''
        ? (string)$ot['erp_target_qty']
        : (trim((string)($ot['target_qty'] ?? '')) !== '' ? (string)$ot['target_qty'] : '-');
    $widthLabel = '-';
    $heightLabel = '-';
    $gussetLabel = '-';
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*[xX]\s*(\d+(?:[.,]\d+)?)(?:\s*[xX]\s*(\d+(?:[.,]\d+)?))?/', $sheetText, $dimensionMatch) === 1) {
        $widthLabel = $dimensionMatch[1];
        $heightLabel = $dimensionMatch[2];
        $gussetLabel = trim((string)($dimensionMatch[3] ?? '')) !== '' ? (string)$dimensionMatch[3] : '-';
    }
    $materialLabel = '-';
    foreach (['PLA', 'BOPP', 'PP', 'PEBD', 'PEAD', 'PE'] as $materialKeyword) {
        if (stripos($sheetText, $materialKeyword) !== false) {
            $materialLabel = $materialKeyword;
            break;
        }
    }
    $detectedColors = [];
    foreach ([trim((string)($currentRoll['color'] ?? '')), trim((string)($outputRoll['color'] ?? ''))] as $colorValue) {
        if ($colorValue !== '' && !in_array($colorValue, $detectedColors, true)) {
            $detectedColors[] = $colorValue;
        }
    }
    foreach ($chemicalInputs as $chemicalInput) {
        foreach ([
            trim((string)($chemicalInput['chemical_name'] ?? '')),
            trim((string)($chemicalInput['chemical_code'] ?? '')),
        ] as $colorValue) {
            if ($colorValue !== '' && !in_array($colorValue, $detectedColors, true)) {
                $detectedColors[] = $colorValue;
            }
        }
    }
    foreach (['Natural', 'Blanco', 'Beige', 'Azul', 'Rojo', 'Verde', 'Negro', 'Transparente'] as $colorKeyword) {
        if (stripos($sheetText, $colorKeyword) !== false && !in_array($colorKeyword, $detectedColors, true)) {
            $detectedColors[] = $colorKeyword;
        }
    }
    $colorSlots = array_pad(array_slice($detectedColors, 0, 6), 6, '-');
    $aniloxCatalog = $service instanceof ReceptionService ? $service->listAniloxCatalog() : [];
    $savedAniloxAssignments = $service instanceof ReceptionService ? $service->getWorkOrderAniloxAssignments((int)$ot['id']) : [];
    $aniloxSlots = buildWorkOrderAniloxSlots($colorSlots, $chemicalInputs, $savedAniloxAssignments, null);
    $suggestedAniloxColors = extractSuggestedAniloxColors($colorSlots, $chemicalInputs, $aniloxSlots);
    $totalUnitsQty = 0;
    foreach ($boxes as $box) {
        $totalUnitsQty += (int)($box['units_qty'] ?? 0);
    }
    $targetQtyNumber = is_numeric((string)$targetQtyLabel) ? (float)$targetQtyLabel : 0.0;
    $producedPercent = $targetQtyNumber > 0 ? ($totalUnitsQty * 100) / $targetQtyNumber : 0.0;
    $producedProgressLabel = number_format((float)$totalUnitsQty, 0, '.', '.')
        . ' de '
        . ($targetQtyNumber > 0 ? number_format($targetQtyNumber, 0, '.', '.') : '0')
        . ' (' . number_format($producedPercent, 0, '.', '.') . ' %)';
    $saldoTotalUnits = max(0.0, $targetQtyNumber - (float)$totalUnitsQty);
    $saldoTotalLabel = $targetQtyNumber > 0
        ? number_format($saldoTotalUnits, 0, '.', '.') . ' unidades'
        : '-';
    $fabricColorLabel = trim((string)($currentRoll['color'] ?? '')) !== ''
        ? (string)$currentRoll['color']
        : ((string)$colorSlots[0] !== '-' ? (string)$colorSlots[0] : '-');
    $fabricWidthLabel = isset($currentRoll['width_mm']) && (string)$currentRoll['width_mm'] !== ''
        ? rtrim(rtrim(number_format((float)$currentRoll['width_mm'], 2, '.', ''), '0'), '.')
        : $widthLabel;
    $fabricGramajeLabel = isset($currentRoll['grams']) && (string)$currentRoll['grams'] !== ''
        ? rtrim(rtrim(number_format((float)$currentRoll['grams'], 2, '.', ''), '0'), '.') . ' gr'
        : '-';
    $fabricMetersLabel = isset($currentRoll['meters']) && (string)$currentRoll['meters'] !== ''
        ? number_format((float)$currentRoll['meters'], 0, '.', '.')
        : '-';
    $fabricKgLabel = isset($currentRoll['weight_kg']) && (string)$currentRoll['weight_kg'] !== ''
        ? rtrim(rtrim(number_format((float)$currentRoll['weight_kg'], 2, '.', ''), '0'), '.') . ' kgs'
        : '-';
    $primaryCliche = $assignedCliches[0] ?? null;
    $primaryClicheLocationLabel = trim((string)($primaryCliche['location_detail'] ?? $primaryCliche['location_code'] ?? ''));
    if ($primaryClicheLocationLabel === '') {
        $primaryClicheLocationLabel = trim((string)($ot['erp_machine_label'] ?? ''));
    }
    $primaryClicheCodeLabel = trim((string)($primaryCliche['code'] ?? ''));
    if ($primaryClicheCodeLabel === '') {
        $primaryClicheCodeLabel = trim((string)($ot['erp_prod_number'] ?? ''));
    }
    $primarySelectedAniloxLabel = '-';
    foreach ($aniloxSlots as $aniloxSlot) {
        if ((int)($aniloxSlot['anilox_id'] ?? 0) <= 0) {
            continue;
        }
        foreach ($aniloxCatalog as $aniloxRow) {
            if ((int)($aniloxRow['id'] ?? 0) === (int)$aniloxSlot['anilox_id']) {
                $primarySelectedAniloxLabel = trim((string)($aniloxRow['display_label'] ?? ''));
                if ($primarySelectedAniloxLabel === '') {
                    $primarySelectedAniloxLabel = trim((string)($aniloxRow['code'] ?? '')) . ' - ' . trim((string)($aniloxRow['name'] ?? ''));
                }
                break 2;
            }
        }
    }
    $configuredColorCount = count(array_values(array_filter(
        array_map(static fn(array $slot): string => trim((string)($slot['color_name'] ?? '')), $aniloxSlots),
        static fn(string $value): bool => $value !== ''
    )));
    $impressionsPerDevelopmentLabel = $configuredColorCount > 0 ? ($configuredColorCount . ' impresiones') : 'Sin información';
    $counterPrinterLabel = $totalUnitsQty > 0 ? number_format((float)$totalUnitsQty, 0, '.', '.') : '-';
    $topUnitRows = [];
    for ($unitIndex = 0; $unitIndex < 6; $unitIndex++) {
        $topUnitRows[] = [
            'label' => 'Unidad N° ' . ($unitIndex + 1),
            'value' => trim((string)($aniloxSlots[$unitIndex]['color_name'] ?? '')) !== ''
                ? h((string)$aniloxSlots[$unitIndex]['color_name'])
                : '-',
        ];
    }
    $bottomUnitRows = [];
    for ($unitIndex = 0; $unitIndex < 6; $unitIndex++) {
        $bottomUnitRows[] = [
            'label' => 'Unidad N° ' . ($unitIndex + 1),
            'value' => '-',
        ];
    }
    $imagePlaceholder = '<span class="legacy-placeholder-btn">Mostrar imagen</span>';
    $legacyClientRows = [
        ['N° OT', h((string)$ot['ot_code']), 'Color N° 1', h((string)$colorSlots[0])],
        ['Cliente', '-', 'Color N° 2', h((string)$colorSlots[1])],
        ['N° CC', trim((string)($ot['erp_req_id'] ?? '')) !== '' ? h((string)$ot['erp_req_id']) : '-', 'Color N° 3', h((string)$colorSlots[2])],
        ['Diseño', trim((string)($ot['erp_plan_desc'] ?? '')) !== '' ? h((string)$ot['erp_plan_desc']) : '-', 'Color N° 4', h((string)$colorSlots[3])],
        ['Producto', h((string)$ot['sku_final']), 'Color N° 5', h((string)$colorSlots[4])],
        ['Materialidad', h($materialLabel), 'Color N° 6', h((string)$colorSlots[5])],
        ['Ancho', h($widthLabel), 'Alto', h($heightLabel)],
        ['Fuelle', h($gussetLabel), 'Cantidad de Bolsas', h($targetQtyLabel)],
        ['Fecha de Entrega', trim((string)($ot['erp_plan_date'] ?? '')) !== '' ? h(formatLabelDate((string)$ot['erp_plan_date'])) : '-', 'Maquina', h($stationLabel)],
        ['Fecha de solicitud de cliché', '-', 'Fecha de recepción de cliché', '-'],
        ['Imagen Diseño', $imagePlaceholder, 'Imagen #5', $imagePlaceholder],
    ];
    $legacyFabricationRows = [
        ['Rodillo a utilizar', h($primarySelectedAniloxLabel), $topUnitRows[0]['label'], $topUnitRows[0]['value']],
        ['Corte de bolsa', h($heightLabel !== '-' ? ($heightLabel . ' mtrs') : '-'), $topUnitRows[1]['label'], $topUnitRows[1]['value']],
        ['Ubicación Cliché', h($primaryClicheLocationLabel !== '' ? $primaryClicheLocationLabel : '-'), $topUnitRows[2]['label'], $topUnitRows[2]['value']],
        ['Código Cliché', h($primaryClicheCodeLabel !== '' ? $primaryClicheCodeLabel : '-'), $topUnitRows[3]['label'], $topUnitRows[3]['value']],
        ['Pie de Imprenta', 'Cliente', $topUnitRows[4]['label'], $topUnitRows[4]['value']],
        ['N° Código de Barra', trim((string)($currentRoll['roll_code'] ?? '')) !== '' ? h((string)$currentRoll['roll_code']) : '-', $topUnitRows[5]['label'], $topUnitRows[5]['value']],
        ['Impresiones al eje', 'Sin información', $bottomUnitRows[0]['label'], $bottomUnitRows[0]['value']],
        ['Impresiones por desarrollo', h($impressionsPerDevelopmentLabel), $bottomUnitRows[1]['label'], $bottomUnitRows[1]['value']],
        ['% de merma', '-', $bottomUnitRows[2]['label'], $bottomUnitRows[2]['value']],
        ['Bolsas producidas / en curso', h($producedProgressLabel), $bottomUnitRows[3]['label'], $bottomUnitRows[3]['value']],
        ['Bolsas producidas / total', h($producedProgressLabel), $bottomUnitRows[4]['label'], $bottomUnitRows[4]['value']],
        ['Saldo total', h($saldoTotalLabel), $bottomUnitRows[5]['label'], $bottomUnitRows[5]['value']],
        ['', '', '', ''],
        ['Materialidad', h($materialLabel), 'Metros a imprimir', h($fabricMetersLabel)],
        ['Color Tela', h($fabricColorLabel), 'Contador impresora', h($counterPrinterLabel)],
        ['Ancho Tela', h($fabricWidthLabel), 'Kg a imprimir', h($fabricKgLabel)],
        ['Gramaje', h($fabricGramajeLabel), '', ''],
    ];
    $setupStartedAt = trim((string)($sessionForDisplay['started_at'] ?? ''));
    $setupApproval = $service instanceof ReceptionService ? $service->getLastWorkOrderSetupApproval((int)$ot['id']) : null;
    $setupEndedAt = trim((string)($setupApproval['created_at'] ?? ''));
    $lastProductionStart = $service instanceof ReceptionService ? $service->getLastWorkOrderStart((int)$ot['id']) : null;
    $productionStartedAt = trim((string)($lastProductionStart['created_at'] ?? ''));
    $setupDurationLabel = formatElapsedLabel($setupStartedAt !== '' ? $setupStartedAt : null, $setupEndedAt !== '' ? $setupEndedAt : null);
    $setupApprovalRoleLabel = strtoupper(trim((string)($setupApproval['role'] ?? ''))) === 'LEADER' ? 'Líder de Área' : 'Supervisor';
    $setupApprovalUserLabel = trim((string)($setupApproval['approved_display_name'] ?? '')) !== ''
        ? trim((string)$setupApproval['approved_display_name'])
        : trim((string)($setupApproval['approved_username'] ?? ''));
    $setupCommentParts = [];
    if ($commentsLabel !== '') {
        $setupCommentParts[] = $commentsLabel;
    }
    if ($setupEndedAt !== '') {
        $setupCommentParts[] = 'Máquina configurada para producción';
        $setupCommentParts[] = 'Validado por ' . $setupApprovalRoleLabel . ': ' . ($setupApprovalUserLabel !== '' ? $setupApprovalUserLabel : '-');
    }
    $setupCommentLabel = $setupCommentParts !== [] ? h(implode(' | ', $setupCommentParts)) : '-';
    $setupReadyForProduction = $setupEndedAt !== ''
        || (isset($_GET['setup_approved']) && $_GET['setup_approved'] === '1')
        || $productionStartedAt !== '';
    $productionActionButton = $productionStartedAt !== ''
        ? '<span class="legacy-setup-action production" style="opacity:.75;cursor:default">Producción iniciada</span>'
        : ($setupReadyForProduction
            ? '<button class="legacy-setup-action production" type="button" data-progress-modal-open="production" style="border:0">Producción</button>'
            : '<span class="legacy-setup-action production" style="opacity:.5;cursor:not-allowed" title="Finaliza y valida el alistamiento para iniciar la producción.">Producción</span>');
    $setupActionButtons = '<div class="legacy-setup-actions" style="grid-template-columns:repeat(6,minmax(0,1fr))">'
        . '<a class="legacy-setup-action request" href="/work-orders/' . (int)$ot['id'] . '/request-materials" target="_blank" rel="noopener">Solicitar materiales</a>'
        . '<a class="legacy-setup-action materials active" href="/work-orders/' . (int)$ot['id'] . '/materials">Utilizar materiales</a>'
        . $productionActionButton
        . '<button class="legacy-setup-action maintenance" type="button" data-progress-modal-open="maintenance" style="border:0">Registrar mantención</button>'
        . '<button class="legacy-setup-action pause" type="button" data-progress-modal-open="pause" style="border:0">Pausa</button>'
        . '<a class="legacy-setup-action consumption" href="#">Consumo</a>'
        . '</div>';

    $body = '<div class="legacy-screen-title">Alistamiento</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Alistamiento</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/start">Volver a ficha</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Informacion Cliente</th></tr></thead><tbody>';
    foreach ($legacyClientRows as $legacyRow) {
        $body .= '<tr>'
            . '<td class="legacy-label-cell">' . h((string)$legacyRow[0]) . '</td>'
            . '<td class="legacy-value-cell">' . $legacyRow[1] . '</td>'
            . '<td class="legacy-label-cell">' . h((string)$legacyRow[2]) . '</td>'
            . '<td class="legacy-value-cell">' . $legacyRow[3] . '</td>'
            . '</tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Información de Fabricación</th></tr></thead><tbody>';
    foreach ($legacyFabricationRows as $legacyRow) {
        if (
            trim((string)$legacyRow[0]) === ''
            && trim((string)$legacyRow[1]) === ''
            && trim((string)$legacyRow[2]) === ''
            && trim((string)$legacyRow[3]) === ''
        ) {
            $body .= '<tr><td class="legacy-value-cell" colspan="4" style="background:#f3f4f6;padding:8px"></td></tr>';
            continue;
        }
        $body .= '<tr>'
            . '<td class="legacy-label-cell">' . h((string)$legacyRow[0]) . '</td>'
            . '<td class="legacy-value-cell">' . $legacyRow[1] . '</td>'
            . '<td class="legacy-label-cell">' . h((string)$legacyRow[2]) . '</td>'
            . '<td class="legacy-value-cell">' . $legacyRow[3] . '</td>'
            . '</tr>';
    }
    $body .= '</tbody></table></div>';
    if ($showFinishData) {
        $finishRollWeightState = (string)($formState['finish_final_roll_weight_kg'] ?? '');
        $finishChemicalWeightState = (string)($formState['finish_final_chemical_weight_kg'] ?? '');
        $finishBoxQtyState = (string)($formState['finish_box_qty'] ?? '');
        $finishWasteState = (string)($formState['finish_waste_kg'] ?? '0');
        $finishOutputRollWeightState = (string)($formState['finish_output_roll_weight_kg'] ?? '');
        if ($finishOutputRollWeightState === '') {
            $finishOutputRollWeightState = $finishRollWeightState;
        }
        $finishProductionMetersState = (string)($formState['finish_production_meters'] ?? '');
        $finishCommentsState = (string)($formState['finish_comments'] ?? '');
        $chemicalReturnState = isset($formState['finish_chemical_return_kg']) && is_array($formState['finish_chemical_return_kg'])
            ? $formState['finish_chemical_return_kg']
            : [];
        $wasteWeightState = isset($formState['finish_waste_detail_weight']) && is_array($formState['finish_waste_detail_weight'])
            ? $formState['finish_waste_detail_weight']
            : [];
        $wasteCommentState = isset($formState['finish_waste_detail_comment']) && is_array($formState['finish_waste_detail_comment'])
            ? $formState['finish_waste_detail_comment']
            : [];
        $finishSupplyRows = [];
        foreach ($chemicalInputs as $input) {
            $chemicalId = (string)($input['chemical_id'] ?? 0);
            $finishSupplyRows[] = [
                'key' => $chemicalId,
                'code' => (string)($input['chemical_code'] ?? ('INS' . $chemicalId)),
                'detail' => trim((string)($input['chemical_name'] ?? '')) !== ''
                    ? (string)$input['chemical_name']
                    : (string)($input['chemical_code'] ?? 'Insumo'),
                'entry' => (float)($input['weight_kg'] ?? 0),
                'return' => (string)($chemicalReturnState[$chemicalId] ?? ''),
            ];
        }
        $finishUsedRolls = [];
        $currentFinishRollId = (int)($currentRoll['id'] ?? 0);
        $finishRollHistory = $service instanceof ReceptionService ? $service->listWorkOrderRollHistory((int)$ot['id']) : [];
        foreach ($finishRollHistory as $historyRow) {
            $rollId = (int)($historyRow['roll_id'] ?? 0);
            if ($rollId <= 0) {
                continue;
            }
            $eventType = strtoupper(trim((string)($historyRow['type'] ?? '')));
            $payload = isset($historyRow['payload_data']) && is_array($historyRow['payload_data'])
                ? $historyRow['payload_data']
                : [];
            if (!isset($finishUsedRolls[$rollId])) {
                $finishUsedRolls[$rollId] = [
                    'roll_id' => $rollId,
                    'roll_code' => trim((string)($historyRow['roll_code'] ?? '')) !== '' ? (string)$historyRow['roll_code'] : ('#' . $rollId),
                    'sku_code' => (string)($historyRow['sku_code'] ?? '-'),
                    'sku_description' => (string)($historyRow['sku_description'] ?? '-'),
                    'warehouse_code' => (string)($historyRow['warehouse_code'] ?? '-'),
                    'meters' => (float)($historyRow['meters'] ?? 0),
                    'attached_at' => '',
                    'attached_weight_kg' => '',
                    'released_at' => '',
                    'final_weight_kg' => '',
                    'waste_kg' => '',
                    'status_label' => 'En maquina',
                ];
            }
            if ($eventType === 'WORK_ORDER_ROLL_ATTACHED') {
                $finishUsedRolls[$rollId]['attached_at'] = (string)($historyRow['created_at'] ?? '');
                $finishUsedRolls[$rollId]['attached_weight_kg'] = (string)($payload['process_weight_kg'] ?? '');
            } elseif ($eventType === 'WORK_ORDER_ROLL_RELEASED') {
                $finishUsedRolls[$rollId]['released_at'] = (string)($historyRow['created_at'] ?? '');
                $finishUsedRolls[$rollId]['final_weight_kg'] = (string)($payload['final_weight_kg'] ?? '');
                $finishUsedRolls[$rollId]['waste_kg'] = (string)($payload['waste_kg'] ?? '');
                $finishUsedRolls[$rollId]['status_label'] = 'Liberada';
            }
        }
        $finishRollMetrics = [];
        foreach ($finishUsedRolls as $usedRoll) {
            $finishRollMetrics[] = [
                'roll_id' => (int)($usedRoll['roll_id'] ?? 0),
                'meters' => (float)($usedRoll['meters'] ?? 0),
                'entry_weight_kg' => (float)($usedRoll['attached_weight_kg'] !== '' ? $usedRoll['attached_weight_kg'] : 0),
                'final_weight_kg' => (float)($usedRoll['final_weight_kg'] !== '' ? $usedRoll['final_weight_kg'] : 0),
                'is_current' => (int)($usedRoll['roll_id'] ?? 0) === $currentFinishRollId,
            ];
        }
        $finishRollMetricsJson = json_encode($finishRollMetrics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($finishRollMetricsJson) || $finishRollMetricsJson === '') {
            $finishRollMetricsJson = '[]';
        }
        $formatFinishKg = static function ($value): string {
            if ($value === null || $value === '') {
                return '-';
            }
            $number = (float)$value;
            $label = rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
            return ($label !== '' ? $label : '0') . ' Kg';
        };
        $wasteRows = [
            ['key' => 'approval', 'label' => 'Ajuste / Aprobación'],
            ['key' => 'printing', 'label' => 'Impresión'],
            ['key' => 'process', 'label' => 'Merma Proceso'],
            ['key' => 'other', 'label' => 'Otros'],
        ];

        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="5">Cargar Insumos</th></tr></thead><tbody>';
        $body .= '<tr><td class="legacy-label-cell">Código</td><td class="legacy-label-cell">Detalle</td><td class="legacy-label-cell">Entrada</td><td class="legacy-label-cell">Salida</td><td class="legacy-label-cell">Consumo</td></tr>';
        foreach ($finishSupplyRows as $supplyRow) {
            $entryLabel = rtrim(rtrim(number_format((float)$supplyRow['entry'], 3, '.', ''), '0'), '.');
            if ($entryLabel === '') {
                $entryLabel = '0';
            }
            $body .= '<tr>'
                . '<td class="legacy-value-cell">' . h((string)$supplyRow['code']) . '</td>'
                . '<td class="legacy-value-cell">' . h((string)$supplyRow['detail']) . '</td>'
                . '<td class="legacy-value-cell"><input type="text" value="' . h($entryLabel) . ' Kg" readonly style="width:100%;background:#f3f4f6"></td>'
                . '<td class="legacy-value-cell"><input type="number" step="0.001" min="0" name="finish_chemical_return_kg[' . h((string)$supplyRow['key']) . ']" form="finish-production-form" value="' . h((string)$supplyRow['return']) . '" data-finish-entry="' . h(number_format((float)$supplyRow['entry'], 3, '.', '')) . '" data-finish-return-input style="width:100%"></td>'
                . '<td class="legacy-value-cell"><input type="text" value="0 Kg" data-finish-consumption readonly style="width:100%;background:#f3f4f6"></td>'
                . '</tr>';
        }
        if ($finishSupplyRows === []) {
            $body .= '<tr><td class="legacy-value-cell" colspan="5">Sin insumos registrados para esta OT.</td></tr>';
        }
        $body .= '</tbody></table></div>';
        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="7">Bobinas usadas</th></tr></thead><tbody>';
        $body .= '<tr><td class="legacy-label-cell">Codigo</td><td class="legacy-label-cell">Detalle</td><td class="legacy-label-cell">Bodega</td><td class="legacy-label-cell">Ingreso</td><td class="legacy-label-cell">Peso ingreso</td><td class="legacy-label-cell">Peso final</td><td class="legacy-label-cell">Estado</td></tr>';
        foreach ($finishUsedRolls as $usedRoll) {
            $detailLabel = trim((string)$usedRoll['sku_code']) !== '' && trim((string)$usedRoll['sku_code']) !== '-'
                ? (string)$usedRoll['sku_code'] . ' - ' . (string)$usedRoll['sku_description']
                : (string)$usedRoll['sku_description'];
            $body .= '<tr>'
                . '<td class="legacy-value-cell">' . h((string)$usedRoll['roll_code']) . '</td>'
                . '<td class="legacy-value-cell">' . h($detailLabel !== '' ? $detailLabel : '-') . '</td>'
                . '<td class="legacy-value-cell">' . h((string)$usedRoll['warehouse_code']) . '</td>'
                . '<td class="legacy-value-cell">' . h(trim((string)$usedRoll['attached_at']) !== '' ? formatLabelDateTime((string)$usedRoll['attached_at']) : '-') . '</td>'
                . '<td class="legacy-value-cell">' . h($formatFinishKg($usedRoll['attached_weight_kg'])) . '</td>'
                . '<td class="legacy-value-cell">' . h($formatFinishKg($usedRoll['final_weight_kg'])) . '</td>'
                . '<td class="legacy-value-cell">' . h((string)$usedRoll['status_label']) . '</td>'
                . '</tr>';
        }
        if ($finishUsedRolls === []) {
            $body .= '<tr><td class="legacy-value-cell" colspan="7">Sin bobinas registradas en esta OT.</td></tr>';
        }
        $body .= '</tbody></table></div>';

        $body .= '<div class="legacy-sheet-card finish-production-card" style="margin-top:12px"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/finish" id="finish-production-form">'
            . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
            . '<input type="hidden" name="show_finish_data" value="1">'
            . '<input type="hidden" name="finish_final_chemical_weight_kg" id="finish_final_chemical_weight_kg" value="' . h($finishChemicalWeightState) . '">'
            . '<input type="hidden" name="finish_waste_kg" id="finish_waste_kg" value="' . h($finishWasteState) . '">'
            . '<input type="hidden" name="finish_output_roll_weight_kg" id="finish_output_roll_weight_kg" value="' . h($finishOutputRollWeightState) . '">'
            . '<div class="finish-form-row finish-form-row-inline">'
            . '<div class="finish-label">Tipo</div>'
            . '<div class="finish-static-value">' . h($machineTypeLabel) . '</div>'
            . '</div>'
            . '<div class="finish-form-row finish-form-row-inline">'
            . '<div class="finish-label">Máquina</div>'
            . '<div class="finish-static-value">' . h($stationLabel) . '</div>'
            . '</div>'
            . '<div class="finish-form-row finish-form-row-production">'
            . '<div class="finish-label">Producción</div>'
            . '<div class="finish-inline-input"><input name="finish_production_meters" type="number" step="0.001" min="0" value="' . h($finishProductionMetersState) . '"></div>'
            . '<div class="finish-inline-suffix">mtrs/lineales</div>'
            . '</div>'
            . '<div class="finish-form-row finish-form-row-inline">'
            . '<div class="finish-label">Peso Bobina Sobrante</div>'
            . '<div class="finish-short-input"><input id="finish_final_roll_weight_kg" name="finish_final_roll_weight_kg" type="number" step="0.001" min="0" value="' . h($finishRollWeightState) . '"></div>'
            . '<div class="finish-inline-suffix">Kg</div>'
            . '<div class="finish-button-inline"><button class="btn secondary finish-grey-btn" type="button" id="read_scale_finish_roll">Leer balanza</button></div>'
            . '</div>'
            . '<div class="finish-form-row finish-form-row-comments">'
            . '<div class="finish-label">Comentarios</div>'
            . '<div class="finish-comments-wrap"><textarea name="finish_comments" rows="3">' . h($finishCommentsState) . '</textarea></div>'
            . '</div>'
            . '<div class="finish-actions">'
            . '<div class="finish-actions-left"><a class="btn secondary finish-grey-btn" href="/work-orders/' . (int)$ot['id'] . '/setup">Volver</a><button class="btn secondary finish-red-btn" type="reset">Eliminar</button></div>'
            . '<div class="finish-actions-right"><button class="btn secondary finish-grey-btn" type="button" id="finish-production-refresh">Actualizar</button><button class="btn finish-yellow-btn" type="submit">Terminar producción</button></div>'
            . '</div>'
            . '<div class="finish-merma-block" style="margin-top:12px">';
        foreach ($wasteRows as $wasteRow) {
            $body .= '<div class="finish-form-row finish-merma-row">'
                . '<div class="finish-merma-title">Merma</div>'
                . '<div class="finish-merma-detail">' . h((string)$wasteRow['label']) . '</div>'
                . '<div class="finish-merma-unit"><select disabled><option>Kilos</option></select></div>'
                . '<div class="finish-merma-weight"><input type="number" step="0.001" min="0" name="finish_waste_detail_weight[' . h((string)$wasteRow['key']) . ']" value="' . h((string)($wasteWeightState[$wasteRow['key']] ?? '')) . '" data-finish-waste-input></div>'
                . '<div class="finish-merma-comment-label">Comentarios</div>'
                . '<div class="finish-merma-comment"><input type="text" name="finish_waste_detail_comment[' . h((string)$wasteRow['key']) . ']" value="' . h((string)($wasteCommentState[$wasteRow['key']] ?? '')) . '"></div>'
                . '</div>';
        }
        $body .= '</div>'
            . '</form></div>'
            . '<script>
                (function () {
                    var form = document.getElementById("finish-production-form");
                    if (!form) return;
                    var finishRollMetrics = ' . $finishRollMetricsJson . ';
                    function syncFinishOutputWeight() {
                        var finalRollInput = document.getElementById("finish_final_roll_weight_kg");
                        var outputRollInput = document.getElementById("finish_output_roll_weight_kg");
                        if (!finalRollInput || !outputRollInput) return;
                        outputRollInput.value = finalRollInput.value;
                    }
                    function syncFinishProductionMeters() {
                        var finalRollInput = document.getElementById("finish_final_roll_weight_kg");
                        var productionMetersInput = form.querySelector("input[name=\"finish_production_meters\"]");
                        if (!productionMetersInput || !Array.isArray(finishRollMetrics)) return;
                        var currentFinalWeight = finalRollInput ? parseFloat(finalRollInput.value || "0") : 0;
                        if (!isFinite(currentFinalWeight) || currentFinalWeight < 0) currentFinalWeight = 0;
                        var totalMeters = 0;
                        finishRollMetrics.forEach(function (rollMetric) {
                            var entryWeight = parseFloat(rollMetric.entry_weight_kg || "0");
                            var sourceMeters = parseFloat(rollMetric.meters || "0");
                            if (!isFinite(entryWeight) || entryWeight <= 0 || !isFinite(sourceMeters) || sourceMeters <= 0) {
                                return;
                            }
                            var finalWeight = rollMetric.is_current
                                ? currentFinalWeight
                                : parseFloat(rollMetric.final_weight_kg || "0");
                            if (!isFinite(finalWeight) || finalWeight < 0) finalWeight = 0;
                            if (finalWeight > entryWeight) finalWeight = entryWeight;
                            var consumedWeight = entryWeight - finalWeight;
                            var consumedMeters = sourceMeters * (consumedWeight / entryWeight);
                            if (isFinite(consumedMeters) && consumedMeters > 0) {
                                totalMeters += consumedMeters;
                            }
                        });
                        productionMetersInput.value = totalMeters > 0 ? totalMeters.toFixed(3) : "";
                    }
                    function formatKg(value) {
                        var rounded = (Math.round(value * 1000) / 1000).toString();
                        return rounded + " Kg";
                    }
                    function recalcFinish() {
                        var chemicalTotal = 0;
                        document.querySelectorAll("[data-finish-return-input]").forEach(function (input) {
                            var entry = parseFloat(input.getAttribute("data-finish-entry") || "0");
                            var output = parseFloat(input.value || "0");
                            if (!isFinite(output) || output < 0) output = 0;
                            chemicalTotal += output;
                            var consumption = Math.max(0, entry - output);
                            var target = input.parentElement && input.parentElement.nextElementSibling
                                ? input.parentElement.nextElementSibling.querySelector("[data-finish-consumption]")
                                : null;
                            if (target) target.value = formatKg(consumption);
                        });
                        var wasteTotal = 0;
                        document.querySelectorAll("[data-finish-waste-input]").forEach(function (input) {
                            var value = parseFloat(input.value || "0");
                            if (isFinite(value) && value > 0) wasteTotal += value;
                        });
                        var chemicalField = document.getElementById("finish_final_chemical_weight_kg");
                        if (chemicalField) chemicalField.value = chemicalTotal.toFixed(3);
                        var wasteField = document.getElementById("finish_waste_kg");
                        if (wasteField) wasteField.value = wasteTotal.toFixed(3);
                        syncFinishProductionMeters();
                    }
                    document.querySelectorAll("[data-finish-return-input],[data-finish-waste-input]").forEach(function (input) {
                        input.addEventListener("input", recalcFinish);
                    });
                    var refreshBtn = document.getElementById("finish-production-refresh");
                    if (refreshBtn) refreshBtn.addEventListener("click", recalcFinish);
                    var finalRollInput = document.getElementById("finish_final_roll_weight_kg");
                    if (finalRollInput) {
                        finalRollInput.addEventListener("input", syncFinishOutputWeight);
                        finalRollInput.addEventListener("change", syncFinishOutputWeight);
                        finalRollInput.addEventListener("input", syncFinishProductionMeters);
                        finalRollInput.addEventListener("change", syncFinishProductionMeters);
                    }
                    form.addEventListener("submit", syncFinishOutputWeight);
                    syncFinishOutputWeight();
                    recalcFinish();
                })();
            </script>';
        render('Terminar producción', $body);
        return;
    }

    $body .= renderWorkOrderAniloxConfigCard((int)$ot['id'], $aniloxSlots, $aniloxCatalog, $suggestedAniloxColors);
    $body .= $setupActionButtons;
    $body .= '<div class="legacy-sheet-card"><table class="legacy-sheet-table legacy-setup-event-table"><thead><tr><th>Evento</th><th>Inicio</th><th>Termino</th><th>Tiempo</th><th>Cantidad</th><th>Comentarios</th><th>Opciones</th></tr></thead><tbody>';
    $setupOptionsHtml = ($setupEndedAt !== '' || $setupReadyForProduction || $productionStartedAt !== '')
        ? '<span class="btn secondary legacy-setup-finish-btn" style="opacity:.75;cursor:default">Configurado</span>'
        : '<a class="btn secondary legacy-setup-finish-btn" href="/work-orders/' . (int)$ot['id'] . '/in-progress">Terminar</a>';
    $body .= '<tr>'
        . '<td>Alistamiento</td>'
        . '<td>' . h($setupStartedAt !== '' ? $setupStartedAt : '-') . '</td>'
        . '<td>' . h($setupEndedAt !== '' ? formatLabelDateTime($setupEndedAt) : '-') . '</td>'
        . '<td>' . h($setupDurationLabel) . '</td>'
        . '<td>-</td>'
        . '<td>' . $setupCommentLabel . '</td>'
        . '<td>' . $setupOptionsHtml . '</td>'
        . '</tr>';
    if ($productionStartedAt !== '') {
        $productionEndedAt = trim((string)($lastFinish['created_at'] ?? ''));
        $productionComments = trim((string)($lastProductionStart['comments'] ?? ''));
        $productionOptionsHtml = $productionEndedAt !== ''
            ? '<span class="btn secondary legacy-setup-finish-btn" style="opacity:.75;cursor:default">Terminado</span>'
            : '<a class="btn secondary legacy-setup-finish-btn" href="/work-orders/' . (int)$ot['id'] . '/finish-data">Terminar producción</a>';
        $body .= '<tr>'
            . '<td>Producción</td>'
            . '<td>' . h(formatLabelDateTime($productionStartedAt)) . '</td>'
            . '<td>' . h($productionEndedAt !== '' ? formatLabelDateTime($productionEndedAt) : '-') . '</td>'
            . '<td>' . h(formatElapsedLabel($productionStartedAt, $productionEndedAt !== '' ? $productionEndedAt : null)) . '</td>'
            . '<td>-</td>'
            . '<td>' . h($productionComments !== '' ? $productionComments : ($productionEndedAt !== '' ? 'Producción cerrada.' : 'Producción en curso.')) . '</td>'
            . '<td>' . $productionOptionsHtml . '</td>'
            . '</tr>';
    }
    foreach ($processEvents as $processEvent) {
        $startedAt = (string)($processEvent['started_at'] ?? '');
        $endedAt = (string)($processEvent['ended_at'] ?? '');
        $isOpen = strtoupper((string)($processEvent['status'] ?? '')) === 'OPEN';
        $optionsHtml = '<span class="muted">-</span>';
        if ($isOpen) {
            $optionsHtml = '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/production-event/finish" style="margin:0">'
                . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                . '<input type="hidden" name="start_event_id" value="' . (int)($processEvent['start_event_id'] ?? 0) . '">'
                . '<input type="hidden" name="return_screen" value="SETUP">'
                . '<button class="btn secondary legacy-setup-finish-btn" type="submit">Terminar</button>'
                . '</form>';
        }
        $body .= '<tr>'
            . '<td>' . h((string)($processEvent['event_label'] ?? '-')) . '</td>'
            . '<td>' . h(formatLabelDateTime($startedAt)) . '</td>'
            . '<td>' . h($endedAt !== '' ? formatLabelDateTime($endedAt) : '-') . '</td>'
            . '<td>' . h(formatElapsedLabel($startedAt, $endedAt !== '' ? $endedAt : null)) . '</td>'
            . '<td>-</td>'
            . '<td>' . h((string)($processEvent['comments'] ?? '-')) . '</td>'
            . '<td>' . $optionsHtml . '</td>'
            . '</tr>';
    }
    $body .= '</tbody></table></div>';

    $modalBaseStyle = 'display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto';
    $modalCardStyle = 'max-width:720px;margin:20px auto;background:#f4f5f7;border:1px solid #d6d6d6;border-radius:8px;padding:16px 18px;box-shadow:0 20px 45px rgba(15,23,42,.22)';
    if ($setupReadyForProduction && $productionStartedAt === '') {
        $body .= '<div class="legacy-inline-modal" id="progress-modal-production" style="' . $modalBaseStyle . '">'
            . '<div style="' . $modalCardStyle . '">'
            . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Iniciar producción</div><button type="button" class="btn secondary" data-progress-modal-close="production">Cerrar</button></div>'
            . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/production/start">'
            . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
            . '<div class="legacy-sheet-card"><table class="legacy-sheet-table" style="margin:0"><tbody>'
            . '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell">Producción</td><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>'
            . '<tr><td class="legacy-label-cell">Comentario</td><td class="legacy-value-cell" colspan="3"><textarea name="comments" rows="4" required placeholder="Ingresa el comentario para iniciar la producción." style="width:100%;border:1px solid #cfd4dc;border-radius:3px;padding:8px"></textarea></td></tr>'
            . '</tbody></table></div>'
            . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-progress-modal-close="production">Cancelar</button><button class="btn" type="submit">Guardar inicio</button></div>'
            . '</form></div></div>';
    }
    $body .= '<div class="legacy-inline-modal" id="progress-modal-maintenance" style="' . $modalBaseStyle . '">'
        . '<div style="' . $modalCardStyle . '">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Registrar mantención</div><button type="button" class="btn secondary" data-progress-modal-close="maintenance">Cerrar</button></div>'
        . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/production-event/start">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="event_key" value="MAINTENANCE">'
        . '<input type="hidden" name="return_screen" value="SETUP">'
        . '<div class="legacy-sheet-card"><table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell">Mantención</td><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Comentario</td><td class="legacy-value-cell" colspan="3"><textarea name="comments" rows="4" required placeholder="Describe la mantención realizada o el motivo del registro." style="width:100%;border:1px solid #cfd4dc;border-radius:3px;padding:8px"></textarea></td></tr>'
        . '</tbody></table></div>'
        . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-progress-modal-close="maintenance">Cancelar</button><button class="btn" type="submit">Guardar inicio</button></div>'
        . '</form></div></div>';
    $body .= '<div class="legacy-inline-modal" id="progress-modal-pause" style="' . $modalBaseStyle . '">'
        . '<div style="' . $modalCardStyle . '">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Registrar pausa</div><button type="button" class="btn secondary" data-progress-modal-close="pause">Cerrar</button></div>'
        . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/production-event/start">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="event_key" value="PAUSE">'
        . '<input type="hidden" name="return_screen" value="SETUP">'
        . '<div class="legacy-sheet-card"><table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell">Pausa</td><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Comentario</td><td class="legacy-value-cell" colspan="3"><textarea name="comments" rows="4" required placeholder="Indica el motivo de la pausa." style="width:100%;border:1px solid #cfd4dc;border-radius:3px;padding:8px"></textarea></td></tr>'
        . '</tbody></table></div>'
        . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-progress-modal-close="pause">Cancelar</button><button class="btn" type="submit">Guardar inicio</button></div>'
        . '</form></div></div>';
    $body .= '<script>
        (function () {
            function toggleProgressModal(key, open) {
                var modal = document.getElementById("progress-modal-" + key);
                if (!modal) {
                    return;
                }
                modal.style.display = open ? "block" : "none";
            }
            document.querySelectorAll("[data-progress-modal-open]").forEach(function (button) {
                button.addEventListener("click", function () {
                    toggleProgressModal(button.getAttribute("data-progress-modal-open"), true);
                });
            });
            document.querySelectorAll("[data-progress-modal-close]").forEach(function (button) {
                button.addEventListener("click", function () {
                    toggleProgressModal(button.getAttribute("data-progress-modal-close"), false);
                });
            });
            document.querySelectorAll(".legacy-inline-modal").forEach(function (modal) {
                modal.addEventListener("click", function (event) {
                    if (event.target === modal && modal.id.indexOf("progress-modal-") === 0) {
                        modal.style.display = "none";
                    }
                });
            });
        })();
    </script>';

    render('Alistamiento', $body);
}

function renderWorkOrderRequestMaterialsScreen(
    array $ot,
    array $chemicals,
    ?array $currentRoll,
    array $chemicalInputs,
    array $materialRequests,
    array $availableMaterialRolls,
    array $boxes,
    ?array $activeShiftSession = null,
    array $assignedCliches = [],
    ?array $outputRoll = null,
    ?string $flashMessage = null,
    bool $flashIsError = false,
    array $formState = []
): void {
    $service = $GLOBALS['service'] ?? null;
    $sessionForDisplay = null;
    if ((int)($ot['shift_session_id'] ?? 0) > 0) {
        $sessionForDisplay = [
            'id' => (int)($ot['shift_session_id'] ?? 0),
            'machine_type_name' => (string)($ot['shift_machine_type_name'] ?? ''),
            'machine_name' => (string)($ot['shift_machine_name'] ?? ''),
            'operator_name' => (string)($ot['shift_operator_name'] ?? ''),
            'helper_name' => (string)($ot['shift_helper_name'] ?? ''),
            'shift_label' => (string)($ot['shift_shift_label'] ?? ''),
            'started_at' => (string)($ot['shift_started_at'] ?? ''),
            'comments' => (string)($ot['shift_comments'] ?? ''),
            'process_stage' => (string)($ot['shift_process_stage'] ?? ''),
        ];
    } elseif (is_array($activeShiftSession) && $activeShiftSession !== []) {
        $sessionForDisplay = $activeShiftSession;
    }
    $sheetText = trim((string)($ot['erp_plan_desc'] ?? '') . ' ' . (string)($ot['sku_final'] ?? ''));
    $targetQtyLabel = trim((string)($ot['erp_target_qty'] ?? '')) !== ''
        ? (string)$ot['erp_target_qty']
        : (trim((string)($ot['target_qty'] ?? '')) !== '' ? (string)$ot['target_qty'] : '-');
    $stationLabel = trim((string)($sessionForDisplay['machine_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['machine_name']
        : (trim((string)($ot['erp_machine_label'] ?? '')) !== '' ? (string)$ot['erp_machine_label'] : 'Sin máquina asignada');
    $widthLabel = '-';
    $heightLabel = '-';
    $gussetLabel = '-';
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*[xX]\s*(\d+(?:[.,]\d+)?)(?:\s*[xX]\s*(\d+(?:[.,]\d+)?))?/', $sheetText, $dimensionMatch) === 1) {
        $widthLabel = $dimensionMatch[1];
        $heightLabel = $dimensionMatch[2];
        $gussetLabel = trim((string)($dimensionMatch[3] ?? '')) !== '' ? (string)$dimensionMatch[3] : '-';
    }
    $materialLabel = '-';
    foreach (['PLA', 'BOPP', 'PP', 'PEBD', 'PEAD', 'PE'] as $materialKeyword) {
        if (stripos($sheetText, $materialKeyword) !== false) {
            $materialLabel = $materialKeyword;
            break;
        }
    }
    $detectedColors = [];
    foreach ([trim((string)($currentRoll['color'] ?? '')), trim((string)($outputRoll['color'] ?? ''))] as $colorValue) {
        if ($colorValue !== '' && !in_array($colorValue, $detectedColors, true)) {
            $detectedColors[] = $colorValue;
        }
    }
    foreach ($chemicalInputs as $chemicalInput) {
        foreach ([trim((string)($chemicalInput['chemical_name'] ?? '')), trim((string)($chemicalInput['chemical_code'] ?? ''))] as $colorValue) {
            if ($colorValue !== '' && !in_array($colorValue, $detectedColors, true)) {
                $detectedColors[] = $colorValue;
            }
        }
    }
    foreach (['Natural', 'Blanco', 'Beige', 'Azul', 'Rojo', 'Verde', 'Negro', 'Transparente'] as $colorKeyword) {
        if (stripos($sheetText, $colorKeyword) !== false && !in_array($colorKeyword, $detectedColors, true)) {
            $detectedColors[] = $colorKeyword;
        }
    }
    $colorSlots = array_pad(array_slice($detectedColors, 0, 6), 6, '-');
    $aniloxCatalog = $service instanceof ReceptionService ? $service->listAniloxCatalog() : [];
    $savedAniloxAssignments = $service instanceof ReceptionService ? $service->getWorkOrderAniloxAssignments((int)$ot['id']) : [];
    $aniloxSlots = buildWorkOrderAniloxSlots($colorSlots, $chemicalInputs, $savedAniloxAssignments, null);
    $totalUnitsQty = 0;
    foreach ($boxes as $box) {
        $totalUnitsQty += (int)($box['units_qty'] ?? 0);
    }
    $targetQtyNumber = is_numeric((string)$targetQtyLabel) ? (float)$targetQtyLabel : 0.0;
    $producedPercent = $targetQtyNumber > 0 ? ($totalUnitsQty * 100) / $targetQtyNumber : 0.0;
    $producedProgressLabel = number_format((float)$totalUnitsQty, 0, '.', '.')
        . ' de '
        . ($targetQtyNumber > 0 ? number_format($targetQtyNumber, 0, '.', '.') : '0')
        . ' (' . number_format($producedPercent, 0, '.', '.') . ' %)';
    $saldoTotalUnits = max(0.0, $targetQtyNumber - (float)$totalUnitsQty);
    $saldoTotalLabel = $targetQtyNumber > 0 ? number_format($saldoTotalUnits, 0, '.', '.') . ' unidades' : '-';
    $fabricColorLabel = trim((string)($currentRoll['color'] ?? '')) !== ''
        ? (string)$currentRoll['color']
        : ((string)$colorSlots[0] !== '-' ? (string)$colorSlots[0] : '-');
    $fabricWidthLabel = isset($currentRoll['width_mm']) && (string)$currentRoll['width_mm'] !== ''
        ? rtrim(rtrim(number_format((float)$currentRoll['width_mm'], 2, '.', ''), '0'), '.')
        : $widthLabel;
    $fabricGramajeLabel = isset($currentRoll['grams']) && (string)$currentRoll['grams'] !== ''
        ? rtrim(rtrim(number_format((float)$currentRoll['grams'], 2, '.', ''), '0'), '.') . ' gr'
        : '-';
    $fabricMetersLabel = isset($currentRoll['meters']) && (string)$currentRoll['meters'] !== ''
        ? number_format((float)$currentRoll['meters'], 0, '.', '.')
        : '-';
    $fabricKgLabel = isset($currentRoll['weight_kg']) && (string)$currentRoll['weight_kg'] !== ''
        ? rtrim(rtrim(number_format((float)$currentRoll['weight_kg'], 2, '.', ''), '0'), '.') . ' kgs'
        : '-';
    $primaryCliche = $assignedCliches[0] ?? null;
    $primaryClicheLocationLabel = trim((string)($primaryCliche['location_detail'] ?? $primaryCliche['location_code'] ?? ''));
    if ($primaryClicheLocationLabel === '') {
        $primaryClicheLocationLabel = trim((string)($ot['erp_machine_label'] ?? ''));
    }
    $primaryClicheCodeLabel = trim((string)($primaryCliche['code'] ?? ''));
    if ($primaryClicheCodeLabel === '') {
        $primaryClicheCodeLabel = trim((string)($ot['erp_prod_number'] ?? ''));
    }
    $primarySelectedAniloxLabel = '-';
    foreach ($aniloxSlots as $aniloxSlot) {
        if ((int)($aniloxSlot['anilox_id'] ?? 0) <= 0) {
            continue;
        }
        foreach ($aniloxCatalog as $aniloxRow) {
            if ((int)($aniloxRow['id'] ?? 0) === (int)$aniloxSlot['anilox_id']) {
                $primarySelectedAniloxLabel = trim((string)($aniloxRow['display_label'] ?? ''));
                if ($primarySelectedAniloxLabel === '') {
                    $primarySelectedAniloxLabel = trim((string)($aniloxRow['code'] ?? '')) . ' - ' . trim((string)($aniloxRow['name'] ?? ''));
                }
                break 2;
            }
        }
    }
    $configuredColorCount = count(array_values(array_filter(
        array_map(static fn(array $slot): string => trim((string)($slot['color_name'] ?? '')), $aniloxSlots),
        static fn(string $value): bool => $value !== ''
    )));
    $impressionsPerDevelopmentLabel = $configuredColorCount > 0 ? ($configuredColorCount . ' impresiones') : 'Sin información';
    $counterPrinterLabel = $totalUnitsQty > 0 ? number_format((float)$totalUnitsQty, 0, '.', '.') : '-';
    $topUnitRows = [];
    for ($unitIndex = 0; $unitIndex < 6; $unitIndex++) {
        $topUnitRows[] = [
            'label' => 'Unidad N° ' . ($unitIndex + 1),
            'value' => trim((string)($aniloxSlots[$unitIndex]['color_name'] ?? '')) !== ''
                ? h((string)$aniloxSlots[$unitIndex]['color_name'])
                : '-',
        ];
    }
    $bottomUnitRows = [];
    for ($unitIndex = 0; $unitIndex < 6; $unitIndex++) {
        $bottomUnitRows[] = [
            'label' => 'Unidad N° ' . ($unitIndex + 1),
            'value' => '-',
        ];
    }
    $imagePlaceholder = '<span class="legacy-placeholder-btn">Mostrar imagen</span>';
    $legacyClientRows = [
        ['N° OT', h((string)$ot['ot_code']), 'Color N° 1', h((string)$colorSlots[0])],
        ['Cliente', '-', 'Color N° 2', h((string)$colorSlots[1])],
        ['N° CC', trim((string)($ot['erp_req_id'] ?? '')) !== '' ? h((string)$ot['erp_req_id']) : '-', 'Color N° 3', h((string)$colorSlots[2])],
        ['Diseño', trim((string)($ot['erp_plan_desc'] ?? '')) !== '' ? h((string)$ot['erp_plan_desc']) : '-', 'Color N° 4', h((string)$colorSlots[3])],
        ['Producto', h((string)$ot['sku_final']), 'Color N° 5', h((string)$colorSlots[4])],
        ['Materialidad', h($materialLabel), 'Color N° 6', h((string)$colorSlots[5])],
        ['Ancho', h($widthLabel), 'Alto', h($heightLabel)],
        ['Fuelle', h($gussetLabel), 'Cantidad de Bolsas', h($targetQtyLabel)],
        ['Fecha de Entrega', trim((string)($ot['erp_plan_date'] ?? '')) !== '' ? h(formatLabelDate((string)$ot['erp_plan_date'])) : '-', 'Maquina', h($stationLabel)],
        ['Fecha de solicitud de cliché', '-', 'Fecha de recepción de cliché', '-'],
        ['Imagen Diseño', $imagePlaceholder, 'Imagen #5', $imagePlaceholder],
    ];
    $legacyFabricationRows = [
        ['Rodillo a utilizar', h($primarySelectedAniloxLabel), $topUnitRows[0]['label'], $topUnitRows[0]['value']],
        ['Corte de bolsa', h($heightLabel !== '-' ? ($heightLabel . ' mtrs') : '-'), $topUnitRows[1]['label'], $topUnitRows[1]['value']],
        ['Ubicación Cliché', h($primaryClicheLocationLabel !== '' ? $primaryClicheLocationLabel : '-'), $topUnitRows[2]['label'], $topUnitRows[2]['value']],
        ['Código Cliché', h($primaryClicheCodeLabel !== '' ? $primaryClicheCodeLabel : '-'), $topUnitRows[3]['label'], $topUnitRows[3]['value']],
        ['Pie de Imprenta', 'Cliente', $topUnitRows[4]['label'], $topUnitRows[4]['value']],
        ['N° Código de Barra', trim((string)($currentRoll['roll_code'] ?? '')) !== '' ? h((string)$currentRoll['roll_code']) : '-', $topUnitRows[5]['label'], $topUnitRows[5]['value']],
        ['Impresiones al eje', 'Sin información', $bottomUnitRows[0]['label'], $bottomUnitRows[0]['value']],
        ['Impresiones por desarrollo', h($impressionsPerDevelopmentLabel), $bottomUnitRows[1]['label'], $bottomUnitRows[1]['value']],
        ['% de merma', '-', $bottomUnitRows[2]['label'], $bottomUnitRows[2]['value']],
        ['Bolsas producidas / en curso', h($producedProgressLabel), $bottomUnitRows[3]['label'], $bottomUnitRows[3]['value']],
        ['Bolsas producidas / total', h($producedProgressLabel), $bottomUnitRows[4]['label'], $bottomUnitRows[4]['value']],
        ['Saldo total', h($saldoTotalLabel), $bottomUnitRows[5]['label'], $bottomUnitRows[5]['value']],
        ['', '', '', ''],
        ['Materialidad', h($materialLabel), 'Metros a imprimir', h($fabricMetersLabel)],
        ['Color Tela', h($fabricColorLabel), 'Contador impresora', h($counterPrinterLabel)],
        ['Ancho Tela', h($fabricWidthLabel), 'Kg a imprimir', h($fabricKgLabel)],
        ['Gramaje', h($fabricGramajeLabel), '', ''],
    ];

    $body = '<div class="legacy-screen-title">Solicitar materiales</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Solicitar materiales</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/setup">Volver a alistamiento</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $requestFilter = strtoupper(trim((string)($formState['request_filter'] ?? ($_GET['request_filter'] ?? 'ROLL'))));
    if (!in_array($requestFilter, ['ROLL', 'CHEMICAL', 'OTHER'], true)) {
        $requestFilter = 'ROLL';
    }
    $requestFilterLabels = [
        'ROLL' => 'Bobinas',
        'CHEMICAL' => 'Tintas',
        'OTHER' => 'Otros',
    ];
    $filteredMaterialRequests = array_values(array_filter(
        $materialRequests,
        static fn(array $request): bool => strtoupper(trim((string)($request['request_type'] ?? 'ROLL'))) === $requestFilter
    ));

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Informacion Cliente</th></tr></thead><tbody>';
    foreach ($legacyClientRows as $legacyRow) {
        $body .= '<tr><td class="legacy-label-cell">' . h((string)$legacyRow[0]) . '</td><td class="legacy-value-cell">' . $legacyRow[1] . '</td><td class="legacy-label-cell">' . h((string)$legacyRow[2]) . '</td><td class="legacy-value-cell">' . $legacyRow[3] . '</td></tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Información de Fabricación</th></tr></thead><tbody>';
    foreach ($legacyFabricationRows as $legacyRow) {
        if (trim((string)$legacyRow[0]) === '' && trim((string)$legacyRow[1]) === '' && trim((string)$legacyRow[2]) === '' && trim((string)$legacyRow[3]) === '') {
            $body .= '<tr><td class="legacy-value-cell" colspan="4" style="background:#f3f4f6;padding:8px"></td></tr>';
            continue;
        }
        $body .= '<tr><td class="legacy-label-cell">' . h((string)$legacyRow[0]) . '</td><td class="legacy-value-cell">' . $legacyRow[1] . '</td><td class="legacy-label-cell">' . h((string)$legacyRow[2]) . '</td><td class="legacy-value-cell">' . $legacyRow[3] . '</td></tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= '<div class="row" style="gap:8px;flex-wrap:wrap;margin-bottom:12px">';
    foreach ($requestFilterLabels as $filterKey => $filterLabel) {
        $buttonClass = $requestFilter === $filterKey ? 'btn' : 'btn secondary';
        $body .= '<button type="button" class="' . $buttonClass . '" data-request-filter-tab="' . h($filterKey) . '" data-active-class="btn" data-inactive-class="btn secondary" style="min-width:120px;text-align:center">' . h($filterLabel) . '</button>';
    }
    $body .= '</div>';

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Solicitudes a bodega</th></tr></thead><tbody>';
    $body .= '<tr data-request-filter-panel="ROLL"' . ($requestFilter === 'ROLL' ? '' : ' style="display:none"') . '><td class="legacy-label-cell">Bobinas</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/material-request"><input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><input type="hidden" name="request_type" value="ROLL"><input type="hidden" name="request_filter" value="ROLL"><input type="hidden" name="return_context" value="REQUEST_WINDOW"><table class="legacy-sheet-table" style="margin:0"><tbody><tr><td class="legacy-label-cell">Tipo de bobina</td><td class="legacy-value-cell" colspan="3"><select name="requested_group_key" required><option value="">Seleccionar material</option>';
    foreach ($availableMaterialRolls as $availableRoll) {
        $selected = ((string)($formState['requested_group_key'] ?? '') === (string)$availableRoll['group_key']) ? ' selected' : '';
        $body .= '<option value="' . h((string)$availableRoll['group_key']) . '"' . $selected . '>' . h(materialRequestGroupLabel($availableRoll)) . '</option>';
    }
    $body .= '</select></td></tr><tr><td class="legacy-label-cell">Cantidad de bobinas</td><td class="legacy-value-cell"><input name="requested_qty" type="number" step="1" min="1" value="' . h((string)($formState['requested_qty'] ?? '1')) . '" required></td><td class="legacy-label-cell">Nota</td><td class="legacy-value-cell"><input name="request_notes" type="text" value="' . h((string)($formState['request_notes'] ?? '')) . '" placeholder="Observaciones para bodega"></td></tr><tr><td class="legacy-label-cell">Acción</td><td class="legacy-value-cell" colspan="3"><button class="btn" type="submit">Solicitar bobinas</button></td></tr></tbody></table></form></td></tr>';
    $body .= '<tr data-request-filter-panel="CHEMICAL"' . ($requestFilter === 'CHEMICAL' ? '' : ' style="display:none"') . '><td class="legacy-label-cell">Tintas</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/material-request"><input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><input type="hidden" name="request_type" value="CHEMICAL"><input type="hidden" name="request_filter" value="CHEMICAL"><input type="hidden" name="return_context" value="REQUEST_WINDOW"><table class="legacy-sheet-table" style="margin:0"><tbody><tr><td class="legacy-label-cell">Tinta</td><td class="legacy-value-cell" colspan="3"><select name="chemical_id" required><option value="">Seleccionar tinta</option>';
    foreach ($chemicals as $chemical) {
        $selected = ((string)($formState['chemical_request_id'] ?? '') === (string)$chemical['id']) ? ' selected' : '';
        $body .= '<option value="' . (int)$chemical['id'] . '"' . $selected . '>' . h((string)$chemical['code']) . ' - ' . h((string)$chemical['name']) . '</option>';
    }
    $body .= '</select></td></tr><tr><td class="legacy-label-cell">Cantidad</td><td class="legacy-value-cell"><input name="requested_qty" type="number" step="0.001" min="0.001" value="' . h((string)($formState['chemical_requested_qty'] ?? '1')) . '" required></td><td class="legacy-label-cell">Unidad</td><td class="legacy-value-cell"><select name="requested_unit"><option value="Kg">Kg</option><option value="Lt">Lt</option><option value="Unid.">Unid.</option></select></td></tr><tr><td class="legacy-label-cell">Nota</td><td class="legacy-value-cell" colspan="3"><input name="request_notes" type="text" value="' . h((string)($formState['chemical_request_notes'] ?? '')) . '" placeholder="Ej: para mezcla inicial o reposición"></td></tr><tr><td class="legacy-label-cell">Acción</td><td class="legacy-value-cell" colspan="3"><button class="btn" type="submit">Solicitar tinta</button></td></tr></tbody></table></form></td></tr>';
    $body .= '<tr data-request-filter-panel="OTHER"' . ($requestFilter === 'OTHER' ? '' : ' style="display:none"') . '><td class="legacy-label-cell">Otros insumos</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/material-request"><input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><input type="hidden" name="request_type" value="OTHER"><input type="hidden" name="request_filter" value="OTHER"><input type="hidden" name="return_context" value="REQUEST_WINDOW"><table class="legacy-sheet-table" style="margin:0"><tbody><tr><td class="legacy-label-cell">Material o insumo</td><td class="legacy-value-cell" colspan="3"><input name="requested_item" type="text" value="' . h((string)($formState['requested_item'] ?? '')) . '" placeholder="Ej: tinta azul, cajas, bolsas, aditivo" required></td></tr><tr><td class="legacy-label-cell">Cantidad</td><td class="legacy-value-cell"><input name="requested_qty" type="number" step="0.001" min="0.001" value="' . h((string)($formState['other_requested_qty'] ?? '1')) . '" required></td><td class="legacy-label-cell">Unidad</td><td class="legacy-value-cell"><input name="requested_unit" type="text" value="' . h((string)($formState['other_requested_unit'] ?? 'Unid.')) . '" placeholder="Ej: Unid., Kg, Lt"></td></tr><tr><td class="legacy-label-cell">Nota</td><td class="legacy-value-cell" colspan="3"><input name="request_notes" type="text" value="' . h((string)($formState['other_request_notes'] ?? '')) . '" placeholder="Observaciones para bodega"></td></tr><tr><td class="legacy-label-cell">Acción</td><td class="legacy-value-cell" colspan="3"><button class="btn" type="submit">Solicitar insumo</button></td></tr></tbody></table></form></td></tr>';
    $body .= '</tbody></table></div>';

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Solicitudes de la OT</th></tr></thead><tbody>';
    $body .= '<tr><td class="legacy-label-cell">Resumen</td><td class="legacy-value-cell" colspan="3">Solicitudes registradas para <span data-request-filter-summary-label>' . h(strtolower($requestFilterLabels[$requestFilter])) . '</span>.</td></tr>';
    $body .= '<tr><td class="legacy-label-cell">Solicitudes OT</td><td class="legacy-value-cell" colspan="3"><div class="table-wrap"><table class="table-compact"><thead><tr><th>Tipo</th><th>Material solicitado</th><th>Cant.</th><th>Entregadas</th><th>Estado</th><th>Última entrega</th></tr></thead><tbody>';
    foreach ($materialRequests as $request) {
        $deliveredRoll = trim((string)($request['delivered_roll_code'] ?? ''));
        $requestTypeValue = strtoupper(trim((string)($request['request_type'] ?? 'ROLL')));
        $body .= '<tr data-request-filter-row="' . h($requestTypeValue) . '"' . ($requestTypeValue === $requestFilter ? '' : ' style="display:none"') . '><td>' . h(materialRequestTypeLabel((string)($request['request_type'] ?? 'ROLL'))) . '</td><td>' . h((string)$request['requested_item']) . '</td><td>' . h(formatReceptionValue((float)($request['requested_qty'] ?? 0), (string)($request['requested_unit'] ?? 'Unid.'))) . '</td><td>' . h(formatReceptionValue((float)($request['delivered_qty'] ?? 0), (string)($request['requested_unit'] ?? 'Unid.'))) . '</td><td>' . h(materialRequestStatusLabel((string)$request['status'])) . '</td><td>' . h($deliveredRoll !== '' ? $deliveredRoll : (string)($request['delivered_by'] ?? '-')) . '</td></tr>';
    }
    $body .= '<tr data-request-filter-empty="' . h($requestFilter) . '"' . ($filteredMaterialRequests === [] ? '' : ' style="display:none"') . '><td colspan="6" class="muted">Sin solicitudes de <span data-request-filter-empty-label>' . h(strtolower($requestFilterLabels[$requestFilter])) . '</span> todavía.</td></tr>';
    $body .= '</tbody></table></div></td></tr></tbody></table></div>';
    $requestFilterJson = json_encode($requestFilterLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $body .= '<script>
        (function () {
            var labels = ' . ($requestFilterJson !== false ? $requestFilterJson : '{"ROLL":"Bobinas","CHEMICAL":"Tintas","OTHER":"Otros"}') . ';
            var buttons = document.querySelectorAll("[data-request-filter-tab]");
            var panels = document.querySelectorAll("[data-request-filter-panel]");
            var rows = document.querySelectorAll("[data-request-filter-row]");
            var summary = document.querySelector("[data-request-filter-summary-label]");
            var emptyRow = document.querySelector("[data-request-filter-empty]");
            var emptyLabel = document.querySelector("[data-request-filter-empty-label]");

            function setFilter(filterKey) {
                var visibleRows = 0;
                buttons.forEach(function (button) {
                    var activeClass = button.getAttribute("data-active-class") || "btn";
                    var inactiveClass = button.getAttribute("data-inactive-class") || "btn secondary";
                    button.className = button.getAttribute("data-request-filter-tab") === filterKey ? activeClass : inactiveClass;
                });
                panels.forEach(function (panel) {
                    panel.style.display = panel.getAttribute("data-request-filter-panel") === filterKey ? "" : "none";
                });
                rows.forEach(function (row) {
                    var matches = row.getAttribute("data-request-filter-row") === filterKey;
                    row.style.display = matches ? "" : "none";
                    if (matches) {
                        visibleRows++;
                    }
                });
                if (summary) {
                    summary.textContent = (labels[filterKey] || "").toLowerCase();
                }
                if (emptyLabel) {
                    emptyLabel.textContent = (labels[filterKey] || "").toLowerCase();
                }
                if (emptyRow) {
                    emptyRow.style.display = visibleRows === 0 ? "" : "none";
                    emptyRow.setAttribute("data-request-filter-empty", filterKey);
                }
            }

            buttons.forEach(function (button) {
                button.addEventListener("click", function () {
                    setFilter(button.getAttribute("data-request-filter-tab"));
                });
            });
        })();
    </script>';

    render('Solicitar materiales', $body);
}

function renderWorkOrderInProgressScreen(
    array $ot,
    ?array $activeShiftSession,
    array $chemicalInputs = [],
    array $boxes = [],
    ?array $currentRoll = null,
    ?array $outputRoll = null,
    array $assignedCliches = [],
    array $processEvents = [],
    ?string $flashMessage = null,
    bool $flashIsError = false
): void {
    $service = $GLOBALS['service'] ?? null;
    $sessionForDisplay = null;
    if ((int)($ot['shift_session_id'] ?? 0) > 0) {
        $sessionForDisplay = [
            'id' => (int)($ot['shift_session_id'] ?? 0),
            'machine_type_name' => (string)($ot['shift_machine_type_name'] ?? ''),
            'machine_name' => (string)($ot['shift_machine_name'] ?? ''),
            'operator_name' => (string)($ot['shift_operator_name'] ?? ''),
            'helper_name' => (string)($ot['shift_helper_name'] ?? ''),
            'shift_label' => (string)($ot['shift_shift_label'] ?? ''),
            'started_at' => (string)($ot['shift_started_at'] ?? ''),
            'comments' => (string)($ot['shift_comments'] ?? ''),
            'process_stage' => (string)($ot['shift_process_stage'] ?? ''),
        ];
    } elseif (is_array($activeShiftSession) && $activeShiftSession !== []) {
        $sessionForDisplay = $activeShiftSession;
    }
    $sheetText = trim((string)($ot['erp_plan_desc'] ?? '') . ' ' . (string)($ot['sku_final'] ?? ''));
    $targetQtyLabel = trim((string)($ot['erp_target_qty'] ?? '')) !== ''
        ? (string)$ot['erp_target_qty']
        : (trim((string)($ot['target_qty'] ?? '')) !== '' ? (string)$ot['target_qty'] : '-');
    $stationLabel = trim((string)($sessionForDisplay['machine_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['machine_name']
        : (trim((string)($ot['erp_machine_label'] ?? '')) !== '' ? (string)$ot['erp_machine_label'] : 'Sin máquina asignada');
    $widthLabel = '-';
    $heightLabel = '-';
    $gussetLabel = '-';
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*[xX]\s*(\d+(?:[.,]\d+)?)(?:\s*[xX]\s*(\d+(?:[.,]\d+)?))?/', $sheetText, $dimensionMatch) === 1) {
        $widthLabel = $dimensionMatch[1];
        $heightLabel = $dimensionMatch[2];
        $gussetLabel = trim((string)($dimensionMatch[3] ?? '')) !== '' ? (string)$dimensionMatch[3] : '-';
    }
    $detectedColors = [];
    foreach ([trim((string)($currentRoll['color'] ?? '')), trim((string)($outputRoll['color'] ?? ''))] as $colorValue) {
        if ($colorValue !== '' && !in_array($colorValue, $detectedColors, true)) {
            $detectedColors[] = $colorValue;
        }
    }
    foreach ($chemicalInputs as $chemicalInput) {
        foreach ([trim((string)($chemicalInput['chemical_name'] ?? '')), trim((string)($chemicalInput['chemical_code'] ?? ''))] as $colorValue) {
            if ($colorValue !== '' && !in_array($colorValue, $detectedColors, true)) {
                $detectedColors[] = $colorValue;
            }
        }
    }
    foreach (['Natural', 'Blanco', 'Beige', 'Azul', 'Rojo', 'Verde', 'Negro', 'Transparente'] as $colorKeyword) {
        if (stripos($sheetText, $colorKeyword) !== false && !in_array($colorKeyword, $detectedColors, true)) {
            $detectedColors[] = $colorKeyword;
        }
    }
    $colorSlots = array_values(array_filter(array_pad(array_slice($detectedColors, 0, 6), 6, ''), static fn(string $value): bool => trim($value) !== ''));
    $productLabel = trim((string)($ot['erp_plan_desc'] ?? '')) !== '' ? (string)$ot['erp_plan_desc'] : (string)$ot['sku_final'];
    $clientLabel = trim((string)($ot['erp_customer_name'] ?? '')) !== '' ? (string)$ot['erp_customer_name'] : '-';
    $reqLabel = trim((string)($ot['erp_req_id'] ?? '')) !== '' ? (string)$ot['erp_req_id'] : '-';
    $measuresLabel = ($widthLabel !== '-' || $heightLabel !== '-') ? trim($widthLabel . ' x ' . $heightLabel) : '-';
    $fabricLabel = trim((string)($currentRoll['color'] ?? '')) !== '' ? (string)$currentRoll['color'] : '-';
    $handlesLabel = trim((string)($currentRoll['color'] ?? '')) !== '' ? (string)$currentRoll['color'] : '-';
    $colorsLabel = $colorSlots !== [] ? implode(', ', $colorSlots) : '-';
    $autocontrolItems = [
        'Paso de Taca',
        'Color de Tela',
        'Revisión Detalles Impresión vs CC',
        'Caras Impresión',
        'Fecha de Impresión',
        'Ancho Bobina',
        'Calce de Colores',
        'Posicion Taca',
        'Colores de Impresión',
        'Pie de Máquina',
        'Tipo de Tela',
        'Código Barras',
    ];

    $body = '<div class="legacy-screen-title">En curso</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; En curso</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/setup">Volver</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><tbody>'
        . '<tr><td class="legacy-label-cell">N° OT</td><td class="legacy-value-cell">' . h((string)$ot['ot_code']) . '</td><td class="legacy-label-cell">N° CC</td><td class="legacy-value-cell">' . h($reqLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cliente</td><td class="legacy-value-cell">' . h($clientLabel) . '</td><td class="legacy-label-cell">Producto</td><td class="legacy-value-cell">' . h($productLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Medidas</td><td class="legacy-value-cell">' . h($measuresLabel) . '</td><td class="legacy-label-cell">Fuelle</td><td class="legacy-value-cell">' . h($gussetLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Area</td><td class="legacy-value-cell">' . h($measuresLabel) . '</td><td class="legacy-label-cell">Tela</td><td class="legacy-value-cell">' . h($fabricLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Manillas</td><td class="legacy-value-cell">' . h($handlesLabel) . '</td><td class="legacy-label-cell">Colores</td><td class="legacy-value-cell">' . h($colorsLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad</td><td class="legacy-value-cell">' . h($targetQtyLabel) . '</td><td class="legacy-label-cell">Máquina</td><td class="legacy-value-cell">' . h($stationLabel) . '</td></tr>'
        . '</tbody></table></div>';

    $body .= '<div class="autocontrol-panel" id="autocontrol-current"><div class="autocontrol-panel-header">AUTOCONTROL</div><table class="autocontrol-table"><tbody>';
    for ($rowIndex = 0; $rowIndex < count($autocontrolItems); $rowIndex += 4) {
        $body .= '<tr>';
        foreach (array_slice($autocontrolItems, $rowIndex, 4) as $autocontrolItem) {
            $checked = $autocontrolItem === 'Caras Impresión' ? ' checked' : '';
            $body .= '<td><label class="autocontrol-checkline">'
                . '<input type="checkbox" data-autocontrol-current="1"' . $checked . '>'
                . '<span>' . h($autocontrolItem) . '</span>'
                . '</label></td>';
        }
        $body .= '</tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= '<div class="approval-actions" id="autocontrol-notify-actions">'
        . '<a class="approval-action-btn area hidden" id="notify-area-btn" href="/work-orders/' . (int)$ot['id'] . '/setup-approval?role=LEADER">Informar Líder de Área</a>'
        . '<a class="approval-action-btn supervisor hidden" id="notify-supervisor-btn" href="/work-orders/' . (int)$ot['id'] . '/setup-approval?role=SUPERVISOR">Informar supervisor</a>'
        . '</div>';

    $body .= '<div class="legacy-setup-actions" style="grid-template-columns:repeat(3,minmax(0,1fr))">'
        . '<a class="legacy-setup-action materials active" href="/work-orders/' . (int)$ot['id'] . '/materials">Utilizar materiales</a>'
        . '<button class="legacy-setup-action maintenance" type="button" data-progress-modal-open="maintenance" style="border:0">Registrar mantención</button>'
        . '<button class="legacy-setup-action pause" type="button" data-progress-modal-open="pause" style="border:0">Pausa</button>'
        . '</div>';

    $modalBaseStyle = 'display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto';
    $modalCardStyle = 'max-width:720px;margin:20px auto;background:#f4f5f7;border:1px solid #d6d6d6;border-radius:8px;padding:16px 18px;box-shadow:0 20px 45px rgba(15,23,42,.22)';
    $body .= '<div class="legacy-inline-modal" id="progress-modal-maintenance" style="' . $modalBaseStyle . '">'
        . '<div style="' . $modalCardStyle . '">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Registrar mantención</div><button type="button" class="btn secondary" data-progress-modal-close="maintenance">Cerrar</button></div>'
        . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/production-event/start">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="event_key" value="MAINTENANCE">'
        . '<input type="hidden" name="return_screen" value="IN_PROGRESS">'
        . '<div class="legacy-sheet-card"><table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell">Mantención</td><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Comentario</td><td class="legacy-value-cell" colspan="3"><textarea name="comments" rows="4" required placeholder="Describe la mantención realizada o el motivo del registro." style="width:100%;border:1px solid #cfd4dc;border-radius:3px;padding:8px"></textarea></td></tr>'
        . '</tbody></table></div>'
        . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-progress-modal-close="maintenance">Cancelar</button><button class="btn" type="submit">Guardar inicio</button></div>'
        . '</form></div></div>';

    $body .= '<div class="legacy-inline-modal" id="progress-modal-pause" style="' . $modalBaseStyle . '">'
        . '<div style="' . $modalCardStyle . '">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Registrar pausa</div><button type="button" class="btn secondary" data-progress-modal-close="pause">Cerrar</button></div>'
        . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/production-event/start">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="event_key" value="PAUSE">'
        . '<input type="hidden" name="return_screen" value="IN_PROGRESS">'
        . '<div class="legacy-sheet-card"><table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell">Pausa</td><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Comentario</td><td class="legacy-value-cell" colspan="3"><textarea name="comments" rows="4" required placeholder="Indica el motivo de la pausa." style="width:100%;border:1px solid #cfd4dc;border-radius:3px;padding:8px"></textarea></td></tr>'
        . '</tbody></table></div>'
        . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-progress-modal-close="pause">Cancelar</button><button class="btn" type="submit">Guardar inicio</button></div>'
        . '</form></div></div>';

    $body .= '<script>
        (function () {
            function toggleProgressModal(key, open) {
                var modal = document.getElementById("progress-modal-" + key);
                if (!modal) {
                    return;
                }
                modal.style.display = open ? "block" : "none";
            }
            function syncAutocontrolActions() {
                var checks = Array.prototype.slice.call(document.querySelectorAll("[data-autocontrol-current]"));
                if (!checks.length) {
                    return;
                }
                var allChecked = checks.every(function (check) { return check.checked; });
                ["notify-area-btn", "notify-supervisor-btn"].forEach(function (id) {
                    var button = document.getElementById(id);
                    if (!button) {
                        return;
                    }
                    button.classList.toggle("hidden", !allChecked);
                });
            }
            document.querySelectorAll("[data-progress-modal-open]").forEach(function (button) {
                button.addEventListener("click", function () {
                    toggleProgressModal(button.getAttribute("data-progress-modal-open"), true);
                });
            });
            document.querySelectorAll("[data-progress-modal-close]").forEach(function (button) {
                button.addEventListener("click", function () {
                    toggleProgressModal(button.getAttribute("data-progress-modal-close"), false);
                });
            });
            document.querySelectorAll(".legacy-inline-modal").forEach(function (modal) {
                modal.addEventListener("click", function (event) {
                    if (event.target === modal && modal.id.indexOf("progress-modal-") === 0) {
                        modal.style.display = "none";
                    }
                });
            });
            document.querySelectorAll("[data-autocontrol-current]").forEach(function (check) {
                check.addEventListener("change", syncAutocontrolActions);
            });
            syncAutocontrolActions();
        })();
    </script>';

    render('En curso', $body);
}

function renderWorkOrderSetupApprovalScreen(
    array $ot,
    ?array $activeShiftSession,
    array $chemicalInputs = [],
    ?array $currentRoll = null,
    ?array $outputRoll = null,
    string $role = 'SUPERVISOR',
    ?string $flashMessage = null,
    bool $flashIsError = false,
    bool $showValidationForm = false,
    string $approvalUsername = '',
    ?string $validatedByLabel = null
): void {
    $sessionForDisplay = null;
    if ((int)($ot['shift_session_id'] ?? 0) > 0) {
        $sessionForDisplay = [
            'machine_name' => (string)($ot['shift_machine_name'] ?? ''),
        ];
    } elseif (is_array($activeShiftSession) && $activeShiftSession !== []) {
        $sessionForDisplay = $activeShiftSession;
    }

    $sheetText = trim((string)($ot['erp_plan_desc'] ?? '') . ' ' . (string)($ot['sku_final'] ?? ''));
    $targetQtyLabel = trim((string)($ot['erp_target_qty'] ?? '')) !== ''
        ? (string)$ot['erp_target_qty']
        : (trim((string)($ot['target_qty'] ?? '')) !== '' ? (string)$ot['target_qty'] : '-');
    $stationLabel = trim((string)($sessionForDisplay['machine_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['machine_name']
        : (trim((string)($ot['erp_machine_label'] ?? '')) !== '' ? (string)$ot['erp_machine_label'] : 'Sin máquina asignada');
    $widthLabel = '-';
    $heightLabel = '-';
    $gussetLabel = '-';
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*[xX]\s*(\d+(?:[.,]\d+)?)(?:\s*[xX]\s*(\d+(?:[.,]\d+)?))?/', $sheetText, $dimensionMatch) === 1) {
        $widthLabel = $dimensionMatch[1];
        $heightLabel = $dimensionMatch[2];
        $gussetLabel = trim((string)($dimensionMatch[3] ?? '')) !== '' ? (string)$dimensionMatch[3] : '-';
    }
    $detectedColors = [];
    foreach ([trim((string)($currentRoll['color'] ?? '')), trim((string)($outputRoll['color'] ?? ''))] as $colorValue) {
        if ($colorValue !== '' && !in_array($colorValue, $detectedColors, true)) {
            $detectedColors[] = $colorValue;
        }
    }
    foreach ($chemicalInputs as $chemicalInput) {
        foreach ([trim((string)($chemicalInput['chemical_name'] ?? '')), trim((string)($chemicalInput['chemical_code'] ?? ''))] as $colorValue) {
            if ($colorValue !== '' && !in_array($colorValue, $detectedColors, true)) {
                $detectedColors[] = $colorValue;
            }
        }
    }
    foreach (['Natural', 'Blanco', 'Beige', 'Azul', 'Rojo', 'Verde', 'Negro', 'Transparente'] as $colorKeyword) {
        if (stripos($sheetText, $colorKeyword) !== false && !in_array($colorKeyword, $detectedColors, true)) {
            $detectedColors[] = $colorKeyword;
        }
    }
    $colorSlots = array_values(array_filter(array_pad(array_slice($detectedColors, 0, 6), 6, ''), static fn(string $value): bool => trim($value) !== ''));
    $productLabel = trim((string)($ot['erp_plan_desc'] ?? '')) !== '' ? (string)$ot['erp_plan_desc'] : (string)$ot['sku_final'];
    $clientLabel = trim((string)($ot['erp_customer_name'] ?? '')) !== '' ? (string)$ot['erp_customer_name'] : '-';
    $reqLabel = trim((string)($ot['erp_req_id'] ?? '')) !== '' ? (string)$ot['erp_req_id'] : '-';
    $measuresLabel = ($widthLabel !== '-' || $heightLabel !== '-') ? trim($widthLabel . ' x ' . $heightLabel) : '-';
    $fabricLabel = trim((string)($currentRoll['color'] ?? '')) !== '' ? (string)$currentRoll['color'] : '-';
    $handlesLabel = trim((string)($currentRoll['color'] ?? '')) !== '' ? (string)$currentRoll['color'] : '-';
    $colorsLabel = $colorSlots !== [] ? implode(', ', $colorSlots) : '-';
    $autocontrolItems = [
        'Paso de Taca',
        'Color de Tela',
        'Revisión Detalles Impresión vs CC',
        'Caras Impresión',
        'Fecha de Impresión',
        'Ancho Bobina',
        'Calce de Colores',
        'Posicion Taca',
        'Colores de Impresión',
        'Pie de Máquina',
        'Tipo de Tela',
        'Código Barras',
    ];
    $role = strtoupper(trim($role)) === 'LEADER' ? 'LEADER' : 'SUPERVISOR';
    $roleLabel = $role === 'LEADER' ? 'Líder de Área' : 'Supervisor';

    $body = '<div class="legacy-screen-title">Aprobación partida</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Aprobación partida</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/in-progress">Volver</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= '<div class="approval-role-note">Validación pendiente por: <strong>' . h($roleLabel) . '</strong>. Esta aprobación confirma que la máquina quedó configurada con los parámetros de producción y que el alistamiento puede darse por terminado.</div>';
    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><tbody>'
        . '<tr><td class="legacy-label-cell">N° OT</td><td class="legacy-value-cell">' . h((string)$ot['ot_code']) . '</td><td class="legacy-label-cell">N° CC</td><td class="legacy-value-cell">' . h($reqLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cliente</td><td class="legacy-value-cell">' . h($clientLabel) . '</td><td class="legacy-label-cell">Producto</td><td class="legacy-value-cell">' . h($productLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Medidas</td><td class="legacy-value-cell">' . h($measuresLabel) . '</td><td class="legacy-label-cell">Fuelle</td><td class="legacy-value-cell">' . h($gussetLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Area</td><td class="legacy-value-cell">' . h($measuresLabel) . '</td><td class="legacy-label-cell">Tela</td><td class="legacy-value-cell">' . h($fabricLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Manillas</td><td class="legacy-value-cell">' . h($handlesLabel) . '</td><td class="legacy-label-cell">Colores</td><td class="legacy-value-cell">' . h($colorsLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad</td><td class="legacy-value-cell">' . h($targetQtyLabel) . '</td><td class="legacy-label-cell">Máquina</td><td class="legacy-value-cell">' . h($stationLabel) . '</td></tr>'
        . '</tbody></table></div>';

    $body .= '<div class="autocontrol-panel" style="margin-bottom:12px"><div class="autocontrol-panel-header">AUTOCONTROL</div><table class="autocontrol-table"><tbody>';
    for ($rowIndex = 0; $rowIndex < count($autocontrolItems); $rowIndex += 4) {
        $body .= '<tr>';
        foreach (array_slice($autocontrolItems, $rowIndex, 4) as $autocontrolItem) {
            $body .= '<td><label class="autocontrol-checkline">'
                . '<input type="checkbox" checked disabled>'
                . '<span>' . h($autocontrolItem) . '</span>'
                . '</label></td>';
        }
        $body .= '</tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= '<div class="autocontrol-panel"><div class="approval-panel-header"><div class="approval-header-actions"><label class="approval-mark-all"><input type="checkbox" id="approval-mark-all"><span>Marcar todos</span></label></div><div class="title">APROBACIÓN PARTIDA</div></div><table class="autocontrol-table"><tbody>';
    for ($rowIndex = 0; $rowIndex < count($autocontrolItems); $rowIndex += 4) {
        $body .= '<tr>';
        foreach (array_slice($autocontrolItems, $rowIndex, 4) as $autocontrolItem) {
            $body .= '<td><label class="autocontrol-checkline">'
                . '<input type="checkbox" data-approval-check="1">'
                . '<span>' . h($autocontrolItem) . '</span>'
                . '</label></td>';
        }
        $body .= '</tr>';
    }
    $body .= '</tbody></table>';
    $body .= '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/setup-approval/validate" id="approval-validation-form" class="approval-validation' . ($showValidationForm ? ' visible' : '') . '" data-force-visible="' . ($showValidationForm ? '1' : '0') . '">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="role" value="' . h($role) . '">'
        . '<input type="hidden" name="approval_ready" id="approval-ready" value="0">'
        . '<input type="text" name="approval_username" value="' . h($approvalUsername) . '" placeholder="Usuario ' . h(strtolower($roleLabel)) . '" autocomplete="username" required>'
        . '<input type="password" name="approval_password" placeholder="Clave" autocomplete="current-password" required>'
        . '<button class="btn" type="submit">Aprobar partida</button>'
        . '</form>';
    if ($validatedByLabel !== null && trim($validatedByLabel) !== '') {
        $body .= '<div class="approval-validation-status ok">Partida validada por <strong>' . h($roleLabel) . '</strong>: ' . h($validatedByLabel) . '.</div>';
    }
    $body .= '</div>';

    $body .= '<script>
        (function () {
            var master = document.getElementById("approval-mark-all");
            var checks = Array.prototype.slice.call(document.querySelectorAll("[data-approval-check]"));
            var validationForm = document.getElementById("approval-validation-form");
            var readyInput = document.getElementById("approval-ready");
            if (!master || !checks.length) {
                return;
            }
            function syncApprovalState() {
                var allChecked = checks.every(function (check) {
                    return check.checked;
                });
                master.checked = allChecked;
                if (readyInput) {
                    readyInput.value = allChecked ? "1" : "0";
                }
                if (validationForm) {
                    var shouldShow = allChecked || validationForm.getAttribute("data-force-visible") === "1";
                    validationForm.classList.toggle("visible", shouldShow);
                }
            }
            master.addEventListener("change", function () {
                checks.forEach(function (check) {
                    check.checked = master.checked;
                });
                syncApprovalState();
            });
            checks.forEach(function (check) {
                check.addEventListener("change", syncApprovalState);
            });
            syncApprovalState();
        })();
    </script>';

    render('Aprobación partida', $body);
}

function renderWorkOrderFinishApprovalScreen(
    array $ot,
    ?array $activeShiftSession,
    ?array $lastFinish = null,
    array $rollHistory = [],
    array $wastes = [],
    ?array $outputRoll = null,
    string $role = 'SUPERVISOR',
    ?string $flashMessage = null,
    bool $flashIsError = false,
    string $approvalUsername = '',
    string $printed = '',
    string $boxPrinted = '',
    string $labelRollId = '',
    ?string $validatedByLabel = null
): void {
    $sessionForDisplay = null;
    if ((int)($ot['shift_session_id'] ?? 0) > 0) {
        $sessionForDisplay = [
            'machine_name' => (string)($ot['shift_machine_name'] ?? ''),
        ];
    } elseif (is_array($activeShiftSession) && $activeShiftSession !== []) {
        $sessionForDisplay = $activeShiftSession;
    }

    $sheetText = trim((string)($ot['erp_plan_desc'] ?? '') . ' ' . (string)($ot['sku_final'] ?? ''));
    $stationLabel = trim((string)($sessionForDisplay['machine_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['machine_name']
        : (trim((string)($ot['erp_machine_label'] ?? '')) !== '' ? (string)$ot['erp_machine_label'] : 'Sin máquina asignada');
    $clientLabel = trim((string)($ot['erp_customer_name'] ?? '')) !== '' ? (string)$ot['erp_customer_name'] : '-';
    $productLabel = trim((string)($ot['erp_plan_desc'] ?? '')) !== '' ? (string)$ot['erp_plan_desc'] : (string)($ot['sku_final'] ?? '-');
    $productionMeters = (float)($lastFinish['production_meters'] ?? 0);
    $reportedWasteKg = 0.0;
    foreach ($wastes as $wasteRow) {
        $reportedWasteKg += (float)($wasteRow['weight_kg'] ?? 0);
    }
    if ($reportedWasteKg <= 0) {
        $reportedWasteKg = (float)($lastFinish['waste_kg'] ?? 0);
    }
    $usedRollIds = [];
    foreach ($rollHistory as $rollEvent) {
        $rollId = (int)($rollEvent['roll_id'] ?? 0);
        if ($rollId > 0) {
            $usedRollIds[$rollId] = true;
        }
    }
    $usedRollCount = count($usedRollIds);
    $printedRollCount = ((int)($lastFinish['output_roll_id'] ?? 0) > 0 || $outputRoll !== null) ? 1 : 0;
    $remainingRollWeight = (float)($lastFinish['final_roll_weight_kg'] ?? 0);
    $unitsPrinted = 0;
    $role = strtoupper(trim($role)) === 'LEADER' ? 'LEADER' : 'SUPERVISOR';
    $roleLabel = $role === 'LEADER' ? 'Líder de Área' : 'Supervisor';
    $formatKg = static function ($value): string {
        $label = rtrim(rtrim(number_format((float)$value, 3, '.', ''), '0'), '.');
        return $label !== '' ? $label : '0';
    };
    $colorSlots = [];
    foreach (['Natural', 'Blanco', 'Beige', 'Azul', 'Rojo', 'Verde', 'Negro', 'Amarillo', 'Rosado', 'Fucsia', 'Celeste'] as $colorKeyword) {
        if (stripos($sheetText, $colorKeyword) !== false) {
            $colorSlots[] = $colorKeyword;
        }
    }
    $colorSlots = array_values(array_unique($colorSlots));
    $colorStyleMap = [
        'NATURAL' => '#d6c6aa',
        'BLANCO' => '#e5e7eb',
        'BEIGE' => '#c8b28a',
        'AZUL' => '#2f80ed',
        'ROJO' => '#d92d20',
        'VERDE' => '#16a34a',
        'NEGRO' => '#101828',
        'AMARILLO' => '#facc15',
        'ROSADO' => '#ec4899',
        'FUCSIA' => '#d946ef',
        'CELESTE' => '#38bdf8',
    ];

    $body = '<div class="legacy-screen-title">Validación cierre flexo</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Validación cierre flexo</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/start">Volver</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= '<div style="display:grid;grid-template-columns:minmax(320px,1fr) minmax(360px,1.1fr);gap:12px;align-items:stretch">';
    $body .= '<div class="legacy-sheet-card" style="margin:0;padding:0;overflow:hidden">'
        . '<div style="background:#0f766e;color:#fff;padding:10px 14px;font-size:18px;font-weight:800">Diseño</div>'
        . '<div style="padding:18px;min-height:360px;display:flex;flex-direction:column;justify-content:space-between">'
        . '<div style="border:1px dashed #d0d5dd;border-radius:12px;padding:18px;min-height:250px;background:#f8fafc">'
        . '<div style="font-size:12px;color:#667085;text-transform:uppercase;letter-spacing:.04em;margin-bottom:10px">Referencia de impresión</div>'
        . '<div style="font-size:22px;font-weight:800;color:#101828;line-height:1.2">' . h($productLabel) . '</div>'
        . '<div style="margin-top:14px;color:#475467;font-size:14px">OT: <strong>' . h((string)$ot['ot_code']) . '</strong></div>'
        . '<div style="margin-top:6px;color:#475467;font-size:14px">Máquina: <strong>' . h($stationLabel) . '</strong></div>'
        . '<div style="margin-top:6px;color:#475467;font-size:14px">Bobina impresa: <strong>' . h((string)($outputRoll['roll_code'] ?? '-')) . '</strong></div>'
        . '<div style="margin-top:6px;color:#475467;font-size:14px">Comentarios: ' . h(trim((string)($lastFinish['comments'] ?? '')) !== '' ? (string)$lastFinish['comments'] : '-') . '</div>'
        . '</div>'
        . '<div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-top:18px">';
    foreach ($colorSlots as $colorSlot) {
        $colorKey = strtoupper($colorSlot);
        $body .= '<div title="' . h($colorSlot) . '" style="width:26px;height:26px;border-radius:999px;border:2px solid #fff;box-shadow:0 0 0 1px #d0d5dd;background:' . h($colorStyleMap[$colorKey] ?? '#9ca3af') . '"></div>';
    }
    if ($colorSlots === []) {
        $body .= '<div style="color:#667085;font-size:13px">Sin colores detectados en la OT.</div>';
    }
    $body .= '</div></div></div>';

    $body .= '<div class="legacy-sheet-card" style="margin:0;padding:0;overflow:hidden">'
        . '<div style="background:#0f766e;color:#fff;padding:10px 14px;font-size:18px;font-weight:800">Información</div>'
        . '<div style="padding:0">'
        . '<table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Cliente</td><td class="legacy-value-cell">' . h($clientLabel) . '</td><td class="legacy-label-cell" style="width:64px"> </td></tr>'
        . '<tr><td class="legacy-label-cell">Metros impresos</td><td class="legacy-value-cell">' . h($formatKg($productionMeters)) . '</td><td class="legacy-label-cell">M</td></tr>'
        . '<tr><td class="legacy-label-cell">Unidades impresas</td><td class="legacy-value-cell">' . h((string)$unitsPrinted) . '</td><td class="legacy-label-cell">Und</td></tr>'
        . '<tr><td class="legacy-label-cell">Total mermas reportadas</td><td class="legacy-value-cell">' . h($formatKg($reportedWasteKg)) . '</td><td class="legacy-label-cell">Kg</td></tr>'
        . '<tr><td class="legacy-label-cell">Total bobinas consumidas</td><td class="legacy-value-cell">' . h((string)$usedRollCount) . '</td><td class="legacy-label-cell">Und</td></tr>'
        . '<tr><td class="legacy-label-cell">Total bobinas impresas</td><td class="legacy-value-cell">' . h((string)$printedRollCount) . '</td><td class="legacy-label-cell">Und</td></tr>'
        . '<tr><td class="legacy-label-cell">Peso Bobina Sobrante</td><td class="legacy-value-cell">' . h($formatKg($remainingRollWeight)) . '</td><td class="legacy-label-cell">Kg</td></tr>'
        . '</tbody></table>'
        . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/finish-approval/validate" style="padding:14px 14px 18px">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="role" value="' . h($role) . '">'
        . '<input type="hidden" name="printed" value="' . h($printed) . '">'
        . '<input type="hidden" name="box_printed" value="' . h($boxPrinted) . '">'
        . '<input type="hidden" name="label_roll_id" value="' . h($labelRollId) . '">'
        . '<div class="row" style="gap:10px">'
        . '<div style="flex:1;min-width:180px"><input type="text" name="approval_username" value="' . h($approvalUsername) . '" placeholder="Usuario ' . h(strtolower($roleLabel)) . '" autocomplete="username" required></div>'
        . '<div style="flex:1;min-width:180px"><input type="password" name="approval_password" placeholder="Clave" autocomplete="current-password" required></div>'
        . '</div>'
        . '<div class="row" style="margin-top:14px;justify-content:space-between">'
        . '<a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/start" style="min-width:220px;text-align:center">Volver</a>'
        . '<button class="btn" type="submit" style="min-width:220px">Guardar</button>'
        . '</div>'
        . '</form>';
    if ($validatedByLabel !== null && trim($validatedByLabel) !== '') {
        $body .= '<div class="approval-validation-status ok" style="margin:0 14px 14px">Cierre flexo validado por <strong>' . h($roleLabel) . '</strong>: ' . h($validatedByLabel) . '.</div>';
    }
    $body .= '</div></div></div>';

    render('Validación cierre flexo', $body);
}

function buildWorkOrderSealingDisplayData(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = []
): array {
    $sessionForDisplay = null;
    if ((int)($ot['shift_session_id'] ?? 0) > 0) {
        $sessionForDisplay = [
            'id' => (int)($ot['shift_session_id'] ?? 0),
            'machine_type_name' => (string)($ot['shift_machine_type_name'] ?? ''),
            'machine_name' => (string)($ot['shift_machine_name'] ?? ''),
            'operator_name' => (string)($ot['shift_operator_name'] ?? ''),
            'helper_name' => (string)($ot['shift_helper_name'] ?? ''),
            'comments' => (string)($ot['shift_comments'] ?? ''),
        ];
    } elseif (is_array($activeShiftSession) && $activeShiftSession !== []) {
        $sessionForDisplay = $activeShiftSession;
    }

    $sheetText = trim((string)($ot['erp_plan_desc'] ?? '') . ' ' . (string)($ot['sku_final'] ?? ''));
    $targetQtyLabel = trim((string)($ot['erp_target_qty'] ?? '')) !== ''
        ? (string)$ot['erp_target_qty']
        : (trim((string)($ot['target_qty'] ?? '')) !== '' ? (string)$ot['target_qty'] : '-');
    $clientLabel = trim((string)($ot['erp_customer_name'] ?? '')) !== '' ? (string)$ot['erp_customer_name'] : '-';
    $requestLabel = trim((string)($ot['erp_req_id'] ?? '')) !== '' ? (string)$ot['erp_req_id'] : '-';
    $stationLabel = trim((string)($sessionForDisplay['machine_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['machine_name']
        : (trim((string)($ot['erp_machine_label'] ?? '')) !== '' ? (string)$ot['erp_machine_label'] : 'Pendiente');
    $widthLabel = '-';
    $heightLabel = '-';
    $gussetLabel = '-';
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*[xX]\s*(\d+(?:[.,]\d+)?)(?:\s*[xX]\s*(\d+(?:[.,]\d+)?))?/', $sheetText, $dimensionMatch) === 1) {
        $widthLabel = (string)$dimensionMatch[1];
        $heightLabel = (string)$dimensionMatch[2];
        $gussetLabel = trim((string)($dimensionMatch[3] ?? '')) !== '' ? (string)$dimensionMatch[3] : '-';
    }
    $materialLabel = '-';
    foreach (['PLA', 'BOPP', 'PP', 'PEBD', 'PEAD', 'PE'] as $materialKeyword) {
        if (stripos($sheetText, $materialKeyword) !== false) {
            $materialLabel = $materialKeyword;
            break;
        }
    }
    $colorLabel = '-';
    foreach (['Natural', 'Blanco', 'Beige', 'Azul', 'Rojo', 'Verde', 'Negro', 'Transparente', 'Amarillo', 'Rosado'] as $colorKeyword) {
        if (stripos($sheetText, $colorKeyword) !== false) {
            $colorLabel = $colorKeyword;
            break;
        }
    }
    $helperLabel = trim((string)($sessionForDisplay['helper_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['helper_name']
        : '-';
    $measureCompact = '-';
    if ($widthLabel !== '-' && $heightLabel !== '-') {
        $measureCompact = $widthLabel . 'x' . $heightLabel . ($gussetLabel !== '-' ? 'x' . $gussetLabel : '');
    }
    $measureLabel = trim((string)($ot['erp_plan_desc'] ?? '')) !== ''
        ? trim((string)$ot['erp_plan_desc']) . ($measureCompact !== '-' ? ' ' . $measureCompact : '')
        : ((string)$ot['sku_final'] !== '' ? (string)$ot['sku_final'] . ($measureCompact !== '-' ? ' ' . $measureCompact : '') : $measureCompact);
    $helperOptions = array_values(array_unique(array_filter(array_map(
        static fn($value): string => trim((string)$value),
        $helperOptions
    ), static fn(string $value): bool => $value !== '')));
    natcasesort($helperOptions);

    return [
        'session' => $sessionForDisplay,
        'target_qty_label' => $targetQtyLabel,
        'client_label' => $clientLabel,
        'request_label' => $requestLabel,
        'station_label' => $stationLabel,
        'width_label' => $widthLabel,
        'height_label' => $heightLabel,
        'gusset_label' => $gussetLabel,
        'material_label' => $materialLabel,
        'color_label' => $colorLabel,
        'helper_label' => $helperLabel,
        'measure_compact' => $measureCompact,
        'measure_label' => $measureLabel,
        'helper_options' => array_values($helperOptions),
        'product_label' => trim((string)($ot['erp_plan_desc'] ?? '')) !== '' ? (string)$ot['erp_plan_desc'] : (string)$ot['sku_final'],
        'output_roll_count' => count($outputRolls),
    ];
}

function renderWorkOrderSealingOverviewHtml(array $ot, array $display): string
{
    $body = '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Información de Fabricación</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">N° O.T.</td><td class="legacy-value-cell">' . h((string)$ot['ot_code']) . '</td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
        . '<tr><td class="legacy-label-cell">Cliente</td><td class="legacy-value-cell">' . h((string)$display['client_label']) . '</td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
        . '<tr><td class="legacy-label-cell">N° C.C.</td><td class="legacy-value-cell">' . h((string)$display['request_label']) . '</td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
        . '</tbody></table></div>';

    $body .= '<div style="display:grid;grid-template-columns:minmax(320px,1fr) minmax(320px,1fr);gap:12px">';
    $body .= '<div class="legacy-sheet-card" style="margin:0"><table class="legacy-sheet-table"><thead><tr><th colspan="2">Configuración</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">Producto</td><td class="legacy-value-cell">' . h((string)$display['product_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Ancho</td><td class="legacy-value-cell">' . h((string)$display['width_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Alto (Tiro)</td><td class="legacy-value-cell">' . h((string)$display['height_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Alto (Retiro)</td><td class="legacy-value-cell">' . h((string)$display['height_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Doblez superior</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Fuelle</td><td class="legacy-value-cell">' . h((string)$display['gusset_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Dado de Manillas</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Cabezal</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Alarma</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Código</td><td class="legacy-value-cell">' . h((string)$ot['sku_final']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Etiqueta adhesiva</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Máquina</td><td class="legacy-value-cell">' . h((string)$display['station_label']) . '</td></tr>'
        . '</tbody></table></div>';

    $body .= '<div class="legacy-sheet-card" style="margin:0"><table class="legacy-sheet-table"><thead><tr><th colspan="2">Insumos</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell" colspan="2" style="background:#eef6f6;font-weight:800">Características de tela</td></tr>'
        . '<tr><td class="legacy-label-cell">Materialidad</td><td class="legacy-value-cell">' . h((string)$display['material_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Color Tela</td><td class="legacy-value-cell">' . h((string)$display['color_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Ancho Tela</td><td class="legacy-value-cell">' . h((string)$display['width_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Gramaje</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Cant. Bobinas</td><td class="legacy-value-cell">' . h((string)$display['output_roll_count']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell" colspan="2" style="background:#eef6f6;font-weight:800">Características de manillas</td></tr>'
        . '<tr><td class="legacy-label-cell">Color Manilla</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Ancho de Manillas</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Largo de Manillas</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Cant. Manillas</td><td class="legacy-value-cell">-</td></tr>'
        . '</tbody></table></div>';
    $body .= '</div>';

    $body .= '<div class="legacy-sheet-card" style="margin-top:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Fechas de entrega</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">Cantidad de Bolsas</td><td class="legacy-value-cell">' . h((string)$display['target_qty_label']) . '</td><td class="legacy-label-cell">Fechas de Entrega</td><td class="legacy-value-cell">' . h(trim((string)($ot['erp_plan_date'] ?? '')) !== '' ? formatLabelDate((string)$ot['erp_plan_date']) : '-') . '</td></tr>'
        . '</tbody></table></div>';

    $body .= '<div class="legacy-sheet-card" style="margin-top:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Adjuntos</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">Imagen Diseño</td><td class="legacy-value-cell"><span class="btn secondary" style="opacity:.65;cursor:default;display:inline-block;padding:6px 12px">Descargar</span></td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
        . '<tr><td class="legacy-label-cell">Montaje</td><td class="legacy-value-cell"><span class="btn secondary" style="opacity:.65;cursor:default;display:inline-block;padding:6px 12px">Descargar</span></td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
        . '<tr><td class="legacy-label-cell">Bobinas Impresas</td><td class="legacy-value-cell"><button class="btn secondary" type="button" data-sealing-stock-open="1" style="display:inline-block;padding:6px 12px">Ver stock actual</button></td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
        . '</tbody></table></div>';

    return $body;
}

function renderWorkOrderSealingStockModalHtml(array $outputRolls): string
{
    $formatNumber = static function ($value, int $decimals = 0): string {
        return rtrim(rtrim(number_format((float)$value, $decimals, '.', ''), '0'), '.') ?: '0';
    };

    $body = '<div class="legacy-inline-modal" id="sealing-stock-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto">'
        . '<div style="max-width:980px;margin:24px auto;background:#fff;border-radius:8px;box-shadow:0 12px 30px rgba(15,23,42,.22);padding:18px">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Bobinas impresas</div><button type="button" class="btn secondary" data-sealing-stock-close="1">Cerrar</button></div>'
        . '<div class="table-wrap"><table class="legacy-sheet-table" style="margin:0"><thead><tr><th>Código</th><th>Bobina origen</th><th>Descripción</th><th>Peso</th><th>Estado</th><th>Fecha</th></tr></thead><tbody>';
    if ($outputRolls !== []) {
        foreach ($outputRolls as $outputRoll) {
            $body .= '<tr>'
                . '<td class="legacy-value-cell"><a href="/rolls/' . (int)$outputRoll['id'] . '">' . h((string)($outputRoll['roll_code'] ?? '-')) . '</a></td>'
                . '<td class="legacy-value-cell">' . h((string)($outputRoll['parent_roll_code'] ?? '-')) . '</td>'
                . '<td class="legacy-value-cell">' . h((string)($outputRoll['sku_description'] ?? '-')) . '</td>'
                . '<td class="legacy-value-cell">' . h($formatNumber((float)($outputRoll['weight_kg'] ?? 0), 3)) . ' Kg</td>'
                . '<td class="legacy-value-cell">' . h(rollProcessStageLabel((string)($outputRoll['process_stage'] ?? 'PRINTED'))) . '</td>'
                . '<td class="legacy-value-cell">' . h((string)($outputRoll['created_at'] ?? '-')) . '</td>'
                . '</tr>';
        }
    } else {
        $body .= '<tr><td class="legacy-value-cell" colspan="6">Todavía no hay bobinas impresas registradas para esta OT.</td></tr>';
    }
    $body .= '</tbody></table></div></div></div>';
    $body .= '<script>
        (function () {
            var modal = document.getElementById("sealing-stock-modal");
            if (!modal) return;
            document.querySelectorAll("[data-sealing-stock-open]").forEach(function (button) {
                button.addEventListener("click", function () {
                    modal.style.display = "block";
                });
            });
            document.querySelectorAll("[data-sealing-stock-close]").forEach(function (button) {
                button.addEventListener("click", function () {
                    modal.style.display = "none";
                });
            });
            modal.addEventListener("click", function (event) {
                if (event.target === modal) {
                    modal.style.display = "none";
                }
            });
        })();
    </script>';

    return $body;
}

function buildWorkOrderPackagingDisplayData(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    array $boxes = [],
    array $pallets = []
): array {
    $base = buildWorkOrderSealingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions);
    $sheetText = trim((string)($ot['erp_plan_desc'] ?? '') . ' ' . (string)($ot['sku_final'] ?? ''));

    $bagTypeLabel = '-';
    foreach (['BOUTIQUE', 'CAMISETA', 'TROQUELADA', 'PLANA', 'RINONERA', 'BASURA'] as $bagKeyword) {
        if (stripos($sheetText, $bagKeyword) !== false) {
            $bagTypeLabel = $bagKeyword;
            break;
        }
    }

    $logoPrintedLabel = '-';
    if (stripos($sheetText, 'SIN LOGO') !== false) {
        $logoPrintedLabel = 'SIN LOGO';
    } elseif (stripos($sheetText, 'LOGO') !== false) {
        $logoPrintedLabel = 'CON LOGO';
    }

    $reverseLabel = stripos($sheetText, 'REVERSA') !== false ? 'Si' : 'No';
    $mixLabel = (string)$base['request_label'] !== '-' ? (string)$base['request_label'] . '-1' : '-';
    $boxMeasureLabel = (string)$base['measure_compact'] !== '-'
        ? (string)$base['measure_compact'] . ($logoPrintedLabel !== '-' ? ' ' . $logoPrintedLabel : '')
        : '-';

    $boxCount = count($boxes);
    $palletCount = count($pallets);
    $unitsTotal = 0.0;
    foreach ($boxes as $box) {
        $unitsTotal += (float)($box['units_qty'] ?? 0);
    }

    $boxesPerPallet = '-';
    if ($palletCount > 0) {
        $totalPalletBoxes = 0;
        foreach ($pallets as $pallet) {
            $totalPalletBoxes += (int)($pallet['box_count'] ?? 0);
        }
        $boxesPerPallet = (string)max(0, (int)round($totalPalletBoxes / max(1, $palletCount)));
    }

    $unitsPerBoxLabel = $boxCount > 0 ? rtrim(rtrim(number_format($unitsTotal / $boxCount, 3, '.', ''), '0'), '.') : '-';
    $finalBoxUnitsLabel = $boxCount > 0 ? rtrim(rtrim(number_format((float)($boxes[0]['units_qty'] ?? 0), 3, '.', ''), '0'), '.') : '0';

    return $base + [
        'bag_type_label' => $bagTypeLabel,
        'procedence_label' => 'Fabrica',
        'print_type_label' => 'Flexografia',
        'printed_colors_label' => (string)$base['color_label'],
        'handle_color_label' => (string)$base['color_label'],
        'handle_measure_label' => '-',
        'reverse_label' => $reverseLabel,
        'mix_label' => $mixLabel,
        'box_measure_label' => $boxMeasureLabel,
        'printed_logo_label' => $logoPrintedLabel,
        'boxes_per_pallet_label' => $boxesPerPallet,
        'boxes_to_use_label' => $boxCount > 0 ? (string)$boxCount : '-',
        'units_per_box_label' => $unitsPerBoxLabel,
        'final_box_units_label' => $finalBoxUnitsLabel,
        'total_order_label' => (string)$base['target_qty_label'],
        'requested_units_label' => (string)$base['target_qty_label'],
    ];
}

function renderWorkOrderPackagingOverviewHtml(array $ot, array $display): string
{
    $leftHeaderStyle = 'background:#145e5b;color:#fff;border-color:#145e5b';
    $rightHeaderStyle = 'background:#19b7b2;color:#fff;border-color:#19b7b2';

    $body = '<div style="display:grid;grid-template-columns:minmax(0,1.55fr) minmax(320px,1fr);gap:12px;align-items:start">'
        . '<div class="legacy-sheet-card" style="margin:0"><table class="legacy-sheet-table" style="margin:0"><thead><tr><th colspan="2" style="' . $leftHeaderStyle . '">Informacion de Fabricacion</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">N° O.T.</td><td class="legacy-value-cell">' . h((string)$ot['ot_code']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cliente</td><td class="legacy-value-cell">' . h((string)$display['client_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">N° C.C.</td><td class="legacy-value-cell">' . h((string)$display['request_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Tipo de Material</td><td class="legacy-value-cell">' . h((string)$display['material_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Tipo de Bolsa</td><td class="legacy-value-cell">' . h((string)$display['bag_type_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Medidas</td><td class="legacy-value-cell">' . h((string)$display['measure_compact']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Procedencia</td><td class="legacy-value-cell"><span style="display:inline-block;background:#2b78d2;color:#fff;padding:2px 6px;border-radius:3px;font-weight:700">' . h((string)$display['procedence_label']) . '</span></td></tr>'
        . '<tr><td class="legacy-label-cell">Tipo de Impresion</td><td class="legacy-value-cell">' . h((string)$display['print_type_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Colores</td><td class="legacy-value-cell">' . h((string)$display['printed_colors_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Color Manillas</td><td class="legacy-value-cell">' . h((string)$display['handle_color_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Medida Manillas</td><td class="legacy-value-cell">' . h((string)$display['handle_measure_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Reversa</td><td class="legacy-value-cell">' . h((string)$display['reverse_label']) . '</td></tr>'
        . '</tbody></table></div>'
        . '<div class="legacy-sheet-card" style="margin:0"><table class="legacy-sheet-table" style="margin:0"><thead><tr><th colspan="2" style="' . $rightHeaderStyle . '">Datos Embalaje</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">Mezcla</td><td class="legacy-value-cell">' . h((string)$display['mix_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Medida de Caja</td><td class="legacy-value-cell">' . h((string)$display['box_measure_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Logotipo impresa</td><td class="legacy-value-cell">' . h((string)$display['printed_logo_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cant. Cajas por Pallet</td><td class="legacy-value-cell">' . h((string)$display['boxes_per_pallet_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cant. Cajas a Utilizar</td><td class="legacy-value-cell">' . h((string)$display['boxes_to_use_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Unidades por caja</td><td class="legacy-value-cell">' . h((string)$display['units_per_box_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Unidades caja final</td><td class="legacy-value-cell">' . h((string)$display['final_box_units_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Total de Pedido</td><td class="legacy-value-cell" style="font-weight:800">' . h((string)$display['total_order_label']) . '</td></tr>'
        . '</tbody></table></div>'
        . '</div>';

    $body .= '<div class="legacy-sheet-card" style="margin-top:12px"><table class="legacy-sheet-table" style="margin:0"><thead><tr><th colspan="2" style="' . $rightHeaderStyle . '">Adjuntos</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">Imagen Diseño</td><td class="legacy-value-cell" style="width:120px"><span class="btn secondary" style="opacity:.65;cursor:default;display:inline-block;padding:6px 12px;min-width:92px;text-align:center">Descargar</span></td></tr>'
        . '<tr><td class="legacy-label-cell">Montaje</td><td class="legacy-value-cell"><span class="btn secondary" style="opacity:.65;cursor:default;display:inline-block;padding:6px 12px;min-width:92px;text-align:center">Descargar</span></td></tr>'
        . '</tbody></table></div>';

    $body .= '<div class="legacy-sheet-card" style="margin-top:12px"><table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Unidades solicitadas</td><td class="legacy-value-cell" style="font-weight:800">' . h((string)$display['requested_units_label']) . '</td></tr>'
        . '</tbody></table></div>';

    return $body;
}

function renderWorkOrderPackagingStartScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    array $boxes = [],
    array $pallets = [],
    ?string $flashMessage = null,
    bool $flashIsError = false,
    array $formState = []
): void {
    $service = $GLOBALS['service'] ?? null;
    $display = buildWorkOrderPackagingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions, $boxes, $pallets);
    $commentsValue = (string)($formState['comments'] ?? '');

    $body = '<div class="legacy-screen-title">Embalaje</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Embalaje</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/sealing/setup">Volver a Selladora</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= renderWorkOrderPackagingOverviewHtml($ot, $display);
    $body .= '<div class="legacy-sheet-card" style="margin-top:12px"><table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Tipo</td><td class="legacy-value-cell">EMBALAJE</td></tr>'
        . '<tr><td class="legacy-label-cell">Máquina</td><td class="legacy-value-cell">EMBALAJE</td></tr>'
        . '<tr><td class="legacy-label-cell">Ayudante</td><td class="legacy-value-cell"><select style="max-width:340px;min-height:34px;padding:6px 8px;font-size:13px;border-radius:4px"><option value="">&lt; Por favor seleccione &gt;</option>';
    foreach ((array)$display['helper_options'] as $helperOption) {
        $selected = $helperOption === $helperValue ? ' selected' : '';
        $body .= '<option value="' . h((string)$helperOption) . '"' . $selected . '>' . h((string)$helperOption) . '</option>';
    }
    if ($helperValue !== '' && !in_array($helperValue, (array)$display['helper_options'], true)) {
        $body .= '<option value="' . h($helperValue) . '" selected>' . h($helperValue) . '</option>';
    }
    $body .= '</select></td></tr>'
        . '<tr><td class="legacy-label-cell">Comentarios</td><td class="legacy-value-cell"><textarea rows="3" style="width:100%;min-height:68px;resize:vertical;border:1px solid #c5cbd3;border-radius:4px;padding:8px 10px">' . h($commentsValue) . '</textarea></td></tr>'
        . '</tbody></table></div>';

    render('Embalaje', $body);
}

function renderWorkOrderPackagingInfoScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    array $boxes = [],
    array $pallets = [],
    ?string $flashMessage = null,
    bool $flashIsError = false,
    array $formState = []
): void {
    $display = buildWorkOrderPackagingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions, $boxes, $pallets);
    $helperValue = trim((string)($formState['helper_name'] ?? ($display['helper_label'] ?? '')));
    $commentsValue = (string)($formState['comments'] ?? '');

    $body = '<div class="legacy-screen-title">Embalaje</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Embalaje</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/sealing/setup">Volver a Selladora</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= renderWorkOrderPackagingOverviewHtml($ot, $display);
    $body .= '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/packaging/setup/start">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<div class="legacy-sheet-card" style="margin-top:12px"><table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Tipo</td><td class="legacy-value-cell">EMBALAJE</td></tr>'
        . '<tr><td class="legacy-label-cell">Maquina</td><td class="legacy-value-cell">EMBALAJE</td></tr>'
        . '<tr><td class="legacy-label-cell">Comentarios</td><td class="legacy-value-cell"><textarea name="comments" rows="3" style="width:100%;min-height:68px;resize:vertical;border:1px solid #c5cbd3;border-radius:4px;padding:8px 10px">' . h($commentsValue) . '</textarea></td></tr>'
        . '</tbody></table></div>'
        . '<div style="margin-top:12px"><button class="legacy-primary-btn" type="submit" style="width:100%;min-width:0;background:#166534;color:#fff;border:0;padding:12px 16px;border-radius:6px;font-weight:700">Iniciar producción</button></div>'
        . '</form>';

    render('Embalaje', $body);
}

function renderWorkOrderPackagingApprovalSummaryHtml(array $ot, array $display): string
{
    $measureLabel = (string)$display['measure_compact'] !== '-' ? (string)$display['measure_compact'] : '-';
    $colorsLabel = trim((string)$display['printed_colors_label']) !== '' ? (string)$display['printed_colors_label'] : '-';

    return '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><tbody>'
        . '<tr><td class="legacy-label-cell">NÂ° OT</td><td class="legacy-value-cell">' . h((string)$ot['ot_code']) . '</td><td class="legacy-label-cell">NÂ° CC</td><td class="legacy-value-cell">' . h((string)$display['request_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cliente</td><td class="legacy-value-cell">' . h((string)$display['client_label']) . '</td><td class="legacy-label-cell">Producto</td><td class="legacy-value-cell">' . h((string)$display['product_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Medidas</td><td class="legacy-value-cell">' . h($measureLabel) . '</td><td class="legacy-label-cell">Fuelle</td><td class="legacy-value-cell">' . h((string)$display['gusset_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Area</td><td class="legacy-value-cell">' . h($measureLabel) . '</td><td class="legacy-label-cell">Tela</td><td class="legacy-value-cell">' . h((string)$display['material_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Manillas</td><td class="legacy-value-cell">' . h((string)$display['handle_color_label']) . '</td><td class="legacy-label-cell">Colores</td><td class="legacy-value-cell">' . h($colorsLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad</td><td class="legacy-value-cell">' . h((string)$display['requested_units_label']) . '</td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
        . '</tbody></table></div>';
}

function renderWorkOrderPackagingSetupScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    array $boxes = [],
    array $pallets = [],
    ?string $flashMessage = null,
    bool $flashIsError = false
): void {
    $service = $GLOBALS['service'] ?? null;
    $display = buildWorkOrderPackagingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions, $boxes, $pallets);
    $setupEvents = $service instanceof ReceptionService ? $service->listWorkOrderPackagingSetupEvents((int)$ot['id']) : [];
    $productionEvents = $service instanceof ReceptionService ? $service->listWorkOrderPackagingProductionEvents((int)$ot['id']) : [];
    $processEvents = $service instanceof ReceptionService ? $service->listWorkOrderProcessEvents((int)$ot['id']) : [];
    $setupApproval = $service instanceof ReceptionService ? $service->getLastWorkOrderPackagingSetupApproval((int)$ot['id']) : null;
    $lastPackagingFinish = $service instanceof ReceptionService ? $service->getLastWorkOrderPackagingFinish((int)$ot['id']) : null;
    $setupApproved = $setupApproval !== null;
    $packagingFinishAfterSetup = false;
    if ($lastPackagingFinish !== null) {
        if ($setupApproval === null) {
            $packagingFinishAfterSetup = true;
        } else {
            $finishTs = strtotime((string)($lastPackagingFinish['created_at'] ?? ''));
            $setupTs = strtotime((string)($setupApproval['created_at'] ?? ''));
            $packagingFinishAfterSetup = ($finishTs === false || $setupTs === false) ? true : ($finishTs >= $setupTs);
        }
    }
    $hasOpenProduction = false;
    foreach ($productionEvents as $productionEvent) {
        if (strtoupper((string)($productionEvent['status'] ?? '')) === 'OPEN') {
            $hasOpenProduction = true;
            break;
        }
    }
    $productionActionButton = $packagingFinishAfterSetup
        ? '<span class="legacy-setup-action production" style="opacity:.75;cursor:default">Producción terminada</span>'
        : ($hasOpenProduction
            ? '<span class="legacy-setup-action production" style="opacity:.75;cursor:default">Producción iniciada</span>'
            : ($setupApproved
                ? '<button class="legacy-setup-action production" type="button" data-progress-modal-open="production" style="border:0">Producción</button>'
                : '<span class="legacy-setup-action production" style="opacity:.5;cursor:not-allowed" title="Finaliza y valida el alistamiento para iniciar la producción.">Producción</span>'));

    $body = '<div class="legacy-screen-title">Alistamiento Embalaje</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Embalaje &gt; Alistamiento</div>'
        . '<div class="legacy-actionbar"><div class="row" style="display:flex;justify-content:space-between;align-items:center;gap:8px">'
        . '<a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/packaging">Volver</a>'
        . '<a class="btn" href="/work-orders/' . (int)$ot['id'] . '/packaging/close">Cerrar OT</a>'
        . '</div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= renderWorkOrderPackagingApprovalSummaryHtml($ot, $display);
    $body .= '<div class="legacy-setup-actions" style="grid-template-columns:repeat(5,minmax(0,1fr));margin-top:12px">'
        . '<a class="legacy-setup-action materials active" href="/work-orders/' . (int)$ot['id'] . '/materials">Utilizar materiales</a>'
        . $productionActionButton
        . '<button class="legacy-setup-action maintenance" type="button" data-progress-modal-open="maintenance" style="border:0">Registrar mantencion</button>'
        . '<button class="legacy-setup-action pause" type="button" data-progress-modal-open="pause" style="border:0">Pausa</button>'
        . '<a class="legacy-setup-action consumption" href="#">Consumo</a>'
        . '</div>';
    $body .= '<div class="legacy-sheet-card" style="margin-top:12px"><div class="table-wrap"><table class="legacy-sheet-table legacy-setup-event-table" style="margin:0"><thead><tr><th>Evento</th><th>Inicio</th><th>Termino</th><th>Tiempo</th><th>Cantidad</th><th>Comentarios</th><th>Opciones</th></tr></thead><tbody>';
    foreach ($setupEvents as $setupEvent) {
        $startedAt = (string)($setupEvent['started_at'] ?? '');
        $endedAt = (string)($setupEvent['ended_at'] ?? '');
        $isOpen = strtoupper((string)($setupEvent['status'] ?? '')) === 'OPEN';
        $eventComments = trim((string)($setupEvent['detail'] ?? '')) !== ''
            ? (string)$setupEvent['detail']
            : (trim((string)($setupEvent['comments'] ?? '')) !== '' ? (string)$setupEvent['comments'] : '-');
        $optionsHtml = '<span class="muted">-</span>';
        if ($isOpen) {
            $optionsHtml = '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/packaging/setup-event/finish" style="margin:0">'
                . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                . '<input type="hidden" name="start_event_id" value="' . (int)($setupEvent['start_event_id'] ?? 0) . '">'
                . '<button class="btn secondary legacy-setup-finish-btn" type="submit">Terminar</button>'
                . '</form>';
        }
        $body .= '<tr>'
            . '<td>' . h((string)($setupEvent['event_label'] ?? 'Alistamiento')) . '</td>'
            . '<td>' . h(formatLabelDateTime($startedAt)) . '</td>'
            . '<td>' . h($endedAt !== '' ? formatLabelDateTime($endedAt) : '-') . '</td>'
            . '<td>' . h(formatElapsedLabel($startedAt, $endedAt !== '' ? $endedAt : null)) . '</td>'
            . '<td>' . h((string)$display['requested_units_label']) . '</td>'
            . '<td>' . h($eventComments) . '</td>'
            . '<td>' . $optionsHtml . '</td>'
            . '</tr>';
    }
    foreach ($productionEvents as $productionEvent) {
        $startedAt = (string)($productionEvent['started_at'] ?? '');
        $endedAt = (string)($productionEvent['ended_at'] ?? '');
        $isOpen = strtoupper((string)($productionEvent['status'] ?? '')) === 'OPEN';
        $productionComments = trim((string)($productionEvent['detail'] ?? '')) !== ''
            ? (string)$productionEvent['detail']
            : (trim((string)($productionEvent['comments'] ?? '')) !== '' ? (string)$productionEvent['comments'] : '-');
        $optionsHtml = $isOpen
            ? '<a class="btn secondary legacy-setup-finish-btn" href="/work-orders/' . (int)$ot['id'] . '/packaging/finish-data">Terminar producción</a>'
            : '<span class="btn secondary legacy-setup-finish-btn" style="opacity:.75;cursor:default">Terminado</span>';
        $body .= '<tr>'
            . '<td>' . h((string)($productionEvent['event_label'] ?? 'Producción')) . '</td>'
            . '<td>' . h($startedAt !== '' ? formatLabelDateTime($startedAt) : '-') . '</td>'
            . '<td>' . h($endedAt !== '' ? formatLabelDateTime($endedAt) : '-') . '</td>'
            . '<td>' . h(formatElapsedLabel($startedAt, $endedAt !== '' ? $endedAt : null)) . '</td>'
            . '<td>' . h((string)$display['requested_units_label']) . '</td>'
            . '<td>' . h($productionComments) . '</td>'
            . '<td>' . $optionsHtml . '</td>'
            . '</tr>';
    }
    foreach ($processEvents as $processEvent) {
        $startedAt = (string)($processEvent['started_at'] ?? '');
        $endedAt = (string)($processEvent['ended_at'] ?? '');
        $isOpen = strtoupper((string)($processEvent['status'] ?? '')) === 'OPEN';
        $optionsHtml = '<span class="muted">-</span>';
        if ($isOpen) {
            $optionsHtml = '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/production-event/finish" style="margin:0">'
                . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                . '<input type="hidden" name="start_event_id" value="' . (int)($processEvent['start_event_id'] ?? 0) . '">'
                . '<input type="hidden" name="return_screen" value="PACKAGING_SETUP">'
                . '<button class="btn secondary legacy-setup-finish-btn" type="submit">Terminar</button>'
                . '</form>';
        }
        $body .= '<tr>'
            . '<td>' . h((string)($processEvent['event_label'] ?? '-')) . '</td>'
            . '<td>' . h(formatLabelDateTime($startedAt)) . '</td>'
            . '<td>' . h($endedAt !== '' ? formatLabelDateTime($endedAt) : '-') . '</td>'
            . '<td>' . h(formatElapsedLabel($startedAt, $endedAt !== '' ? $endedAt : null)) . '</td>'
            . '<td>-</td>'
            . '<td>' . h((string)($processEvent['comments'] ?? '-')) . '</td>'
            . '<td>' . $optionsHtml . '</td>'
            . '</tr>';
    }
    if ($setupEvents === [] && $productionEvents === [] && $processEvents === []) {
        $body .= '<tr><td colspan="7" class="legacy-value-cell">Todavia no hay eventos registrados para Embalaje.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    if ($setupApproval !== null) {
        $approvedBy = trim((string)($setupApproval['approved_display_name'] ?? ''));
        if ($approvedBy === '') {
            $approvedBy = trim((string)($setupApproval['approved_username'] ?? ''));
        }
        $body .= '<div class="ok" style="margin-top:12px">Alistamiento de Embalaje validado correctamente' . ($approvedBy !== '' ? ' por ' . h($approvedBy) : '') . '.</div>';
    }
    if ($setupApproved && !$hasOpenProduction && !$packagingFinishAfterSetup) {
        $body .= '<div class="legacy-inline-modal" id="progress-modal-production" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto">'
            . '<div style="max-width:720px;margin:20px auto;background:#f4f5f7;border:1px solid #d6d6d6;border-radius:8px;padding:16px 18px;box-shadow:0 20px 45px rgba(15,23,42,.22)">'
            . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Iniciar producción Embalaje</div><button type="button" class="btn secondary" data-progress-modal-close="production">Cerrar</button></div>'
            . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/packaging/production/start">'
            . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
            . '<div class="legacy-sheet-card"><table class="legacy-sheet-table" style="margin:0"><tbody>'
            . '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell">Producción</td><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>'
            . '<tr><td class="legacy-label-cell">Comentario</td><td class="legacy-value-cell" colspan="3"><textarea name="comments" rows="4" required placeholder="Ingresa el comentario para iniciar la producción de Embalaje." style="width:100%;border:1px solid #cfd4dc;border-radius:3px;padding:8px"></textarea></td></tr>'
            . '</tbody></table></div>'
            . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-progress-modal-close="production">Cancelar</button><button class="btn" type="submit">Guardar inicio</button></div>'
            . '</form></div></div>';
    }
    $body .= renderWorkOrderSealingProgressModalsHtml((int)$ot['id'], 'PACKAGING_SETUP');

    render('Alistamiento Embalaje', $body);
}

function renderWorkOrderPackagingSetupApprovalScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    array $boxes = [],
    array $pallets = [],
    string $role = 'SUPERVISOR',
    ?string $flashMessage = null,
    bool $flashIsError = false,
    bool $showValidationForm = false,
    string $approvalUsername = '',
    ?string $validatedByLabel = null
): void {
    $display = buildWorkOrderPackagingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions, $boxes, $pallets);
    $autocontrolItems = [
        'Medidas (Bolsa / Manillas)',
        'Sellado / Costura (Bolsa / Manillas)',
        'Color Tela (Bolsa / Manillas)',
        'Materialidad de Bolsa',
        'Etiquetas / Alarma',
        'Logo Manillas',
    ];
    $role = strtoupper(trim($role)) === 'LEADER' ? 'LEADER' : 'SUPERVISOR';
    $roleLabel = $role === 'LEADER' ? 'Lider de Area' : 'Supervisor';

    $body = '<div class="legacy-screen-title">Aprobacion alistamiento Embalaje</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Embalaje &gt; Aprobacion</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/packaging/setup">Volver</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= renderWorkOrderPackagingApprovalSummaryHtml($ot, $display);
    $body .= '<div class="autocontrol-panel"><div class="autocontrol-panel-header">AUTOCONTROL</div><table class="autocontrol-table"><tbody>';
    for ($rowIndex = 0; $rowIndex < count($autocontrolItems); $rowIndex += 4) {
        $body .= '<tr>';
        foreach (array_slice($autocontrolItems, $rowIndex, 4) as $autocontrolItem) {
            $body .= '<td><label class="autocontrol-checkline">'
                . '<input type="checkbox" data-packaging-autocontrol-check="1">'
                . '<span>' . h($autocontrolItem) . '</span>'
                . '</label></td>';
        }
        $body .= '</tr>';
    }
    $body .= '</tbody></table></div>';

    $approvalPanelVisible = $showValidationForm || ($validatedByLabel !== null && trim($validatedByLabel) !== '');
    $body .= '<div class="approval-actions" id="packaging-autocontrol-actions">'
        . '<button type="button" class="approval-action-btn supervisor hidden" id="packaging-open-approval-btn" style="border:0;cursor:pointer">Informar supervisor</button>'
        . '</div>';

    $body .= '<div class="autocontrol-panel" id="packaging-approval-panel" style="' . ($approvalPanelVisible ? '' : 'display:none;') . '"><div class="approval-panel-header"><div class="approval-header-actions"><label class="approval-mark-all"><input type="checkbox" id="packaging-approval-mark-all"><span>Marcar todos</span></label></div><div class="title">APROBACION PARTIDA</div></div><table class="autocontrol-table"><tbody>';
    for ($rowIndex = 0; $rowIndex < count($autocontrolItems); $rowIndex += 4) {
        $body .= '<tr>';
        foreach (array_slice($autocontrolItems, $rowIndex, 4) as $autocontrolItem) {
            $body .= '<td><label class="autocontrol-checkline">'
                . '<input type="checkbox" data-packaging-approval-check="1">'
                . '<span>' . h($autocontrolItem) . '</span>'
                . '</label></td>';
        }
        $body .= '</tr>';
    }
    $body .= '</tbody></table>';
    $body .= '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/packaging/setup-approval/validate" id="packaging-approval-validation-form" class="approval-validation' . ($showValidationForm ? ' visible' : '') . '" data-force-visible="' . ($showValidationForm ? '1' : '0') . '">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="role" value="' . h($role) . '">'
        . '<input type="hidden" name="approval_ready" id="packaging-approval-ready" value="0">'
        . '<input type="text" name="approval_username" value="' . h($approvalUsername) . '" placeholder="Usuario ' . h(strtolower($roleLabel)) . '" autocomplete="username" required>'
        . '<input type="password" name="approval_password" placeholder="Clave" autocomplete="current-password" required>'
        . '<button class="btn" type="submit">Aprobar partida</button>'
        . '</form>';
    if ($validatedByLabel !== null && trim($validatedByLabel) !== '') {
        $body .= '<div class="approval-validation-status ok">Partida validada por <strong>' . h($roleLabel) . '</strong>: ' . h($validatedByLabel) . '.</div>';
    }
    $body .= '</div>';
    $body .= '<div class="legacy-setup-actions" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-top:12px">'
        . '<a class="legacy-setup-action materials active" href="/work-orders/' . (int)$ot['id'] . '/materials">Utilizar materiales</a>'
        . '<button class="legacy-setup-action maintenance" type="button" data-progress-modal-open="maintenance" style="border:0">Registrar mantencion</button>'
        . '<button class="legacy-setup-action pause" type="button" data-progress-modal-open="pause" style="border:0">Pausa</button>'
        . '</div>';
    $body .= renderWorkOrderSealingProgressModalsHtml((int)$ot['id'], 'PACKAGING_SETUP');
    $body .= '<script>
        (function () {
            var autocontrolChecks = Array.prototype.slice.call(document.querySelectorAll("[data-packaging-autocontrol-check]"));
            var openApprovalButton = document.getElementById("packaging-open-approval-btn");
            var approvalPanel = document.getElementById("packaging-approval-panel");
            var master = document.getElementById("packaging-approval-mark-all");
            var checks = Array.prototype.slice.call(document.querySelectorAll("[data-packaging-approval-check]"));
            var validationForm = document.getElementById("packaging-approval-validation-form");
            var readyInput = document.getElementById("packaging-approval-ready");
            if (!master || !checks.length) {
                return;
            }
            function syncAutocontrolState() {
                if (!autocontrolChecks.length || !openApprovalButton) {
                    return;
                }
                var allAutocontrolChecked = autocontrolChecks.every(function (check) {
                    return check.checked;
                });
                openApprovalButton.classList.toggle("hidden", !allAutocontrolChecked);
            }
            function syncApprovalState() {
                var allChecked = checks.every(function (check) {
                    return check.checked;
                });
                master.checked = allChecked;
                if (readyInput) {
                    readyInput.value = allChecked ? "1" : "0";
                }
                if (validationForm) {
                    var shouldShow = allChecked || validationForm.getAttribute("data-force-visible") === "1";
                    validationForm.classList.toggle("visible", shouldShow);
                }
            }
            if (openApprovalButton) {
                openApprovalButton.addEventListener("click", function () {
                    if (approvalPanel) {
                        approvalPanel.style.display = "";
                    }
                });
            }
            master.addEventListener("change", function () {
                checks.forEach(function (check) {
                    check.checked = master.checked;
                });
                syncApprovalState();
            });
            autocontrolChecks.forEach(function (check) {
                check.addEventListener("change", syncAutocontrolState);
            });
            checks.forEach(function (check) {
                check.addEventListener("change", syncApprovalState);
            });
            syncAutocontrolState();
            syncApprovalState();
        })();
    </script>';

    render('Aprobacion alistamiento Embalaje', $body);
}

function renderWorkOrderPackagingFinishScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    array $boxes = [],
    array $pallets = [],
    ?string $flashMessage = null,
    bool $flashIsError = false,
    array $formState = []
): void {
    $service = $GLOBALS['service'] ?? null;
    $display = buildWorkOrderPackagingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions, $boxes, $pallets);
    $wasteWeightState = isset($formState['packaging_waste_weight']) && is_array($formState['packaging_waste_weight'])
        ? $formState['packaging_waste_weight']
        : [];
    $wasteCommentState = isset($formState['packaging_waste_comment']) && is_array($formState['packaging_waste_comment'])
        ? $formState['packaging_waste_comment']
        : [];
    $packagingState = isset($formState['packaging_data']) && is_array($formState['packaging_data'])
        ? $formState['packaging_data']
        : [];
    $boxesPerPalletBase = (float)(is_numeric((string)($display['boxes_per_pallet_label'] ?? '')) ? $display['boxes_per_pallet_label'] : 0);
    $unitsPerBoxBase = (float)(is_numeric((string)($display['units_per_box_label'] ?? '')) ? $display['units_per_box_label'] : 0);
    $totalBoxesBase = count($boxes);
    $fullPalletsBase = count($pallets);
    $incompleteBoxesBase = max(0, $totalBoxesBase - ((int)round($boxesPerPalletBase) * $fullPalletsBase));
    $finalBoxUnitsBase = (float)(is_numeric((string)($display['final_box_units_label'] ?? '')) ? $display['final_box_units_label'] : 0);
    $measureState = (string)($packagingState['package_measure'] ?? (string)($display['box_measure_label'] ?? ''));
    $unitsPerBoxState = (string)($packagingState['units_per_box'] ?? ($unitsPerBoxBase > 0 ? rtrim(rtrim(number_format($unitsPerBoxBase, 3, '.', ''), '0'), '.') : ''));
    $boxesPerPalletState = (string)($packagingState['boxes_per_pallet'] ?? ($boxesPerPalletBase > 0 ? rtrim(rtrim(number_format($boxesPerPalletBase, 3, '.', ''), '0'), '.') : ''));
    $completePalletsState = (string)($packagingState['complete_pallets'] ?? ($fullPalletsBase > 0 ? (string)$fullPalletsBase : ''));
    $incompletePalletBoxesState = (string)($packagingState['incomplete_pallet_boxes'] ?? ($incompleteBoxesBase > 0 ? (string)$incompleteBoxesBase : ''));
    $totalCompleteBoxesState = (string)($packagingState['total_complete_boxes'] ?? ($totalBoxesBase > 0 ? (string)$totalBoxesBase : ''));
    $finalBoxUnitsState = (string)($packagingState['final_box_units'] ?? ($finalBoxUnitsBase > 0 ? rtrim(rtrim(number_format($finalBoxUnitsBase, 3, '.', ''), '0'), '.') : ''));
    $leftoverBagsState = (string)($packagingState['leftover_bags'] ?? '');
    $showroomBagsState = (string)($packagingState['showroom_bags'] ?? '');
    $productionState = (string)($formState['packaging_produced_units'] ?? '');
    if ($productionState === '') {
        $productionBase = ($totalBoxesBase * $unitsPerBoxBase) + $finalBoxUnitsBase;
        $productionState = $productionBase > 0 ? rtrim(rtrim(number_format($productionBase, 3, '.', ''), '0'), '.') : '';
    }
    $commentsState = (string)($formState['packaging_finish_comments'] ?? '');
    $closePackagingState = isset($formState['close_packaging_data']) && is_array($formState['close_packaging_data'])
        ? $formState['close_packaging_data']
        : [];
    $closeMeasureState = (string)($closePackagingState['package_measure'] ?? $measureState);
    $closeUnitsPerBoxState = (string)($closePackagingState['units_per_box'] ?? $unitsPerBoxState);
    $closeBoxesPerPalletState = (string)($closePackagingState['boxes_per_pallet'] ?? $boxesPerPalletState);
    $closeCompletePalletsState = (string)($closePackagingState['complete_pallets'] ?? $completePalletsState);
    $closeIncompletePalletBoxesState = (string)($closePackagingState['incomplete_pallet_boxes'] ?? $incompletePalletBoxesState);
    $closeTotalCompleteBoxesState = (string)($closePackagingState['total_complete_boxes'] ?? $totalCompleteBoxesState);
    $closeFinalBoxUnitsState = (string)($closePackagingState['final_box_units'] ?? $finalBoxUnitsState);
    $closeLeftoverBagsState = (string)($closePackagingState['leftover_bags'] ?? $leftoverBagsState);
    $closeShowroomBagsState = (string)($closePackagingState['showroom_bags'] ?? $showroomBagsState);
    $closeTotalUnitsState = (string)($formState['close_total_units'] ?? $productionState);
    $closeWarehouseState = (string)($formState['close_warehouse_id'] ?? '');
    $closeSupervisorUsernameState = (string)($formState['close_supervisor_username'] ?? '');
    $closeSupervisorObservationState = (string)($formState['close_supervisor_observation'] ?? '');
    $closeClassificationState = (string)($formState['close_closure_classification'] ?? '');
    $closeModalOpen = (($formState['close_modal_open'] ?? '') === '1');
    $closureOptions = [
        'CONFORME' => 'Conforme',
        'OBSERVADO' => 'Con observación',
        'PARCIAL' => 'Parcial',
    ];
    $closeWarehouses = $service instanceof ReceptionService
        ? array_values(array_filter($service->listWarehouses(), static fn(array $warehouse): bool => in_array((int)$warehouse['code'], [700, 1000], true)))
        : [];
    if ($closeWarehouses === [] && $service instanceof ReceptionService) {
        $closeWarehouses = $service->listWarehouses();
    }
    $wasteRows = [
        ['key' => 'setup', 'title' => 'Merma', 'label' => 'Configuración'],
        ['key' => 'printing', 'title' => 'Merma', 'label' => 'Impresión'],
        ['key' => 'other', 'title' => 'Merma', 'label' => 'Otros'],
        ['key' => 'repair', 'title' => 'Reparación', 'label' => 'Reparación Manillas / Configuración'],
    ];

    $body = '<div class="legacy-screen-title">Terminar producción Embalaje</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Embalaje &gt; Terminar producción</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/packaging/setup">Volver</a><button class="btn" type="button" id="packaging-open-close-modal">Cerrar OT</button></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= renderWorkOrderPackagingOverviewHtml($ot, $display);
    $body .= '<div class="legacy-sheet-card finish-production-card"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/packaging/finish" id="packaging-finish-form">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="close_modal_open" id="close_modal_open" value="' . ($closeModalOpen ? '1' : '0') . '">'
        . '<div class="finish-form-row finish-form-row-inline">'
        . '<div class="finish-label">Tipo</div>'
        . '<div class="finish-static-value">EMBALAJE</div>'
        . '</div>'
        . '<div class="finish-form-row finish-form-row-inline">'
        . '<div class="finish-label">Máquina</div>'
        . '<div class="finish-static-value">EMBALAJE</div>'
        . '</div>'
        . '<div class="finish-form-row finish-form-row-inline">'
        . '<div class="finish-label">Producción</div>'
        . '<div class="finish-inline-input"><input id="packaging_produced_units" name="packaging_produced_units" type="number" step="0.001" min="0" value="' . h($productionState) . '"></div>'
        . '<div class="finish-inline-suffix">unidades</div>'
        . '</div>'
        . '<div class="finish-form-row finish-form-row-comments">'
        . '<div class="finish-label">Comentarios</div>'
        . '<div class="finish-comments-wrap"><textarea name="packaging_finish_comments" rows="3">' . h($commentsState) . '</textarea></div>'
        . '</div>'
        . '<div style="margin-top:12px"><table class="legacy-sheet-table" style="margin:0"><thead><tr><th style="text-align:left">Medidas / Cantidades embalaje</th><th style="text-align:left">Predefinido</th><th style="text-align:left">Ingreso</th><th style="text-align:left"></th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">Medida de caja</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['box_measure_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_measure" type="text" name="packaging_data[package_measure]" value="' . h($measureState) . '" style="width:100%"></td><td class="legacy-value-cell"></td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad de bolsas por caja</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['units_per_box_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_units_per_box" type="number" step="0.001" min="0" name="packaging_data[units_per_box]" value="' . h($unitsPerBoxState) . '" style="width:100%"></td><td class="legacy-value-cell">(calcula unidades producidas)</td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad de cajas por pallets</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['boxes_per_pallet_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_boxes_per_pallet" type="number" step="0.001" min="0" name="packaging_data[boxes_per_pallet]" value="' . h($boxesPerPalletState) . '" style="width:100%"></td><td class="legacy-value-cell"></td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad de pallets completos</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$fullPalletsBase) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_complete_pallets" type="number" step="0.001" min="0" name="packaging_data[complete_pallets]" value="' . h($completePalletsState) . '" style="width:100%"></td><td class="legacy-value-cell"></td></tr>'
        . '<tr><td class="legacy-label-cell">Numero de cajas en pallet incompleto</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$incompleteBoxesBase) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_incomplete_boxes" type="number" step="0.001" min="0" name="packaging_data[incomplete_pallet_boxes]" value="' . h($incompletePalletBoxesState) . '" style="width:100%"></td><td class="legacy-value-cell"></td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad total de cajas completas</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$totalBoxesBase) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_total_boxes" type="number" step="0.001" min="0" name="packaging_data[total_complete_boxes]" value="' . h($totalCompleteBoxesState) . '" style="width:100%"></td><td class="legacy-value-cell">(calcula unidades producidas)</td></tr>'
        . '<tr><td class="legacy-label-cell">Caja final (completa pedido)</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['final_box_units_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_final_box_units" type="number" step="0.001" min="0" name="packaging_data[final_box_units]" value="' . h($finalBoxUnitsState) . '" style="width:100%"></td><td class="legacy-value-cell">(calcula unidades producidas)</td></tr>'
        . '<tr><td class="legacy-label-cell">Bolsas sobrantes</td><td class="legacy-value-cell"><input type="text" value="" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_leftover_bags" type="number" step="0.001" min="0" name="packaging_data[leftover_bags]" value="' . h($leftoverBagsState) . '" style="width:100%"></td><td class="legacy-value-cell">(calcula unidades producidas)</td></tr>'
        . '<tr><td class="legacy-label-cell">Bolsas a Showroom</td><td class="legacy-value-cell"><input type="text" value="" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_showroom_bags" type="number" step="0.001" min="0" name="packaging_data[showroom_bags]" value="' . h($showroomBagsState) . '" style="width:100%"></td><td class="legacy-value-cell">(calcula unidades producidas)</td></tr>'
        . '</tbody></table></div>'
        . '<div class="finish-merma-block" style="margin-top:12px">';
    foreach ($wasteRows as $wasteRow) {
        $body .= '<div class="finish-form-row finish-merma-row">'
            . '<div class="finish-merma-title">' . h((string)$wasteRow['title']) . '</div>'
            . '<div class="finish-merma-detail">' . h((string)$wasteRow['label']) . '</div>'
            . '<div class="finish-merma-unit"><select disabled><option>Cantidad</option></select></div>'
            . '<div class="finish-merma-weight"><input type="number" step="0.001" min="0" name="packaging_waste_weight[' . h((string)$wasteRow['key']) . ']" value="' . h((string)($wasteWeightState[$wasteRow['key']] ?? '')) . '"></div>'
            . '<div class="finish-merma-comment-label">Comentarios</div>'
            . '<div class="finish-merma-comment"><input type="text" name="packaging_waste_comment[' . h((string)$wasteRow['key']) . ']" value="' . h((string)($wasteCommentState[$wasteRow['key']] ?? '')) . '"></div>'
            . '</div>';
    }
    $body .= '</div>'
        . '<div class="legacy-inline-modal" id="packaging-close-modal" style="display:' . ($closeModalOpen ? 'block' : 'none') . ';position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:18px;overflow:auto">'
        . '<div style="max-width:980px;margin:18px auto;background:#f2f2f2;border:1px solid #d6d6d6;border-radius:4px;padding:12px 14px;box-shadow:0 20px 45px rgba(15,23,42,.22)">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px"><div style="font-size:18px;font-weight:800">Terminar OT</div><button type="button" class="btn secondary" id="packaging-close-modal-close">Cerrar</button></div>'
        . '<table class="legacy-sheet-table" style="margin:0"><thead><tr><th style="text-align:left">Medidas / Cantidades embalaje</th><th style="text-align:left">Predefinido</th><th style="text-align:left">Ingreso</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">Medida de caja</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['box_measure_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_package_measure" type="text" name="close_packaging_data[package_measure]" value="' . h($closeMeasureState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad de bolsas por caja</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['units_per_box_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_units_per_box" type="number" step="0.001" min="0" name="close_packaging_data[units_per_box]" value="' . h($closeUnitsPerBoxState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad de cajas por pallets</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['boxes_per_pallet_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_boxes_per_pallet" type="number" step="0.001" min="0" name="close_packaging_data[boxes_per_pallet]" value="' . h($closeBoxesPerPalletState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad de pallets completos</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$fullPalletsBase) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_complete_pallets" type="number" step="0.001" min="0" name="close_packaging_data[complete_pallets]" value="' . h($closeCompletePalletsState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Numero de cajas en pallet incompleto</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$incompleteBoxesBase) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_incomplete_pallet_boxes" type="number" step="0.001" min="0" name="close_packaging_data[incomplete_pallet_boxes]" value="' . h($closeIncompletePalletBoxesState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad total de cajas completas</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$totalBoxesBase) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_total_complete_boxes" type="number" step="0.001" min="0" name="close_packaging_data[total_complete_boxes]" value="' . h($closeTotalCompleteBoxesState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Caja final (completa pedido)</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['final_box_units_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_final_box_units" type="number" step="0.001" min="0" name="close_packaging_data[final_box_units]" value="' . h($closeFinalBoxUnitsState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Bolsas sobrantes</td><td class="legacy-value-cell"><input type="text" value="" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_leftover_bags" type="number" step="0.001" min="0" name="close_packaging_data[leftover_bags]" value="' . h($closeLeftoverBagsState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Bolsas a Showroom</td><td class="legacy-value-cell"><input type="text" value="" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_showroom_bags" type="number" step="0.001" min="0" name="close_packaging_data[showroom_bags]" value="' . h($closeShowroomBagsState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Totales</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['requested_units_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_total_units" type="number" step="0.001" min="0" name="close_total_units" value="' . h($closeTotalUnitsState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Bodega</td><td class="legacy-value-cell" colspan="2"><select name="close_warehouse_id" style="width:100%"><option value="">&lt; Por favor seleccione &gt;</option>';
    foreach ($closeWarehouses as $warehouseOption) {
        $selected = (string)($warehouseOption['id'] ?? '') === $closeWarehouseState ? ' selected' : '';
        $body .= '<option value="' . (int)($warehouseOption['id'] ?? 0) . '"' . $selected . '>' . h((string)($warehouseOption['code'] ?? '')) . ' - ' . h((string)($warehouseOption['name'] ?? '')) . '</option>';
    }
    $body .= '</select></td></tr>'
        . '<tr><td class="legacy-label-cell">Usuario supervisor</td><td class="legacy-value-cell"><input type="text" name="close_supervisor_username" value="' . h($closeSupervisorUsernameState) . '" placeholder="Usuario Supervisor" style="width:100%"></td><td class="legacy-value-cell"><input type="password" name="close_supervisor_password" placeholder="Contrasena Supervisor" autocomplete="current-password" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Observacion Supervisor</td><td class="legacy-value-cell" colspan="2"><textarea name="close_supervisor_observation" rows="3" style="width:100%;min-height:60px;resize:vertical">' . h($closeSupervisorObservationState) . '</textarea></td></tr>'
        . '<tr><td class="legacy-label-cell">Clasificacion de Cierre</td><td class="legacy-value-cell" colspan="2"><select name="close_closure_classification" style="width:100%"><option value="">&lt; Por favor seleccione &gt;</option>';
    foreach ($closureOptions as $closureValue => $closureLabel) {
        $selected = $closureValue === $closeClassificationState ? ' selected' : '';
        $body .= '<option value="' . h($closureValue) . '"' . $selected . '>' . h($closureLabel) . '</option>';
    }
    $body .= '</select></td></tr>'
        . '</tbody></table>'
        . '<div style="display:flex;justify-content:space-between;gap:8px;margin-top:10px"><button class="btn secondary finish-grey-btn" type="button" id="packaging-close-modal-cancel">Volver</button><button class="btn finish-yellow-btn" type="submit" formaction="/work-orders/' . (int)$ot['id'] . '/packaging/finish-order">Terminar OT</button></div>'
        . '</div></div>'
        . '<div class="finish-actions">'
        . '<div class="finish-actions-left"><a class="btn secondary finish-grey-btn" href="/work-orders/' . (int)$ot['id'] . '/packaging/setup">Volver</a><button class="btn secondary finish-red-btn" type="reset">Eliminar</button></div>'
        . '<div class="finish-actions-right"><button class="btn secondary finish-grey-btn" type="button" id="packaging-finish-refresh">Actualizar</button><button class="btn finish-yellow-btn" type="submit">Terminar producción</button></div>'
        . '</div>'
        . '</form></div>'
        . '<script>
            (function () {
                var form = document.getElementById("packaging-finish-form");
                if (!form) return;
                var refreshButton = document.getElementById("packaging-finish-refresh");
                var actionsRight = form.querySelector(".finish-actions-right");
                var openCloseModalButton = document.getElementById("packaging-open-close-modal");
                var closeModal = document.getElementById("packaging-close-modal");
                var closeModalOpenInput = document.getElementById("close_modal_open");
                var closeModalCloseButton = document.getElementById("packaging-close-modal-close");
                var closeModalCancelButton = document.getElementById("packaging-close-modal-cancel");
                var producedInput = document.getElementById("packaging_produced_units");
                var measureInput = document.getElementById("packaging_measure");
                var unitsPerBoxInput = document.getElementById("packaging_units_per_box");
                var totalBoxesInput = document.getElementById("packaging_total_boxes");
                var finalBoxUnitsInput = document.getElementById("packaging_final_box_units");
                var leftoverBagsInput = document.getElementById("packaging_leftover_bags");
                var showroomBagsInput = document.getElementById("packaging_showroom_bags");
                var boxesPerPalletInput = document.getElementById("packaging_boxes_per_pallet");
                var completePalletsInput = document.getElementById("packaging_complete_pallets");
                var incompleteBoxesInput = document.getElementById("packaging_incomplete_boxes");
                var closeMeasureInput = document.getElementById("close_package_measure");
                var closeUnitsPerBoxInput = document.getElementById("close_units_per_box");
                var closeBoxesPerPalletInput = document.getElementById("close_boxes_per_pallet");
                var closeCompletePalletsInput = document.getElementById("close_complete_pallets");
                var closeIncompleteBoxesInput = document.getElementById("close_incomplete_pallet_boxes");
                var closeTotalBoxesInput = document.getElementById("close_total_complete_boxes");
                var closeFinalBoxUnitsInput = document.getElementById("close_final_box_units");
                var closeLeftoverBagsInput = document.getElementById("close_leftover_bags");
                var closeShowroomBagsInput = document.getElementById("close_showroom_bags");
                var closeTotalUnitsInput = document.getElementById("close_total_units");
                if (!openCloseModalButton && actionsRight) {
                    openCloseModalButton = document.createElement("button");
                    openCloseModalButton.type = "button";
                    openCloseModalButton.id = "packaging-open-close-modal";
                    openCloseModalButton.className = "btn secondary finish-grey-btn";
                    openCloseModalButton.textContent = "Cerrar OT";
                    if (refreshButton && refreshButton.nextSibling) {
                        actionsRight.insertBefore(openCloseModalButton, refreshButton.nextSibling);
                    } else {
                        actionsRight.appendChild(openCloseModalButton);
                    }
                }
                function toNumber(input) {
                    if (!input) return 0;
                    var value = parseFloat(input.value || "0");
                    return isFinite(value) && value >= 0 ? value : 0;
                }
                function normalizeNumericInputs() {
                    form.querySelectorAll("input[type=number]").forEach(function (input) {
                        var value = parseFloat(input.value || "0");
                        if (!isFinite(value) || value < 0) {
                            input.value = "";
                        }
                    });
                }
                function syncCloseModalValues() {
                    if (closeMeasureInput && measureInput) closeMeasureInput.value = measureInput.value;
                    if (closeUnitsPerBoxInput && unitsPerBoxInput) closeUnitsPerBoxInput.value = unitsPerBoxInput.value;
                    if (closeBoxesPerPalletInput && boxesPerPalletInput) closeBoxesPerPalletInput.value = boxesPerPalletInput.value;
                    if (closeCompletePalletsInput && completePalletsInput) closeCompletePalletsInput.value = completePalletsInput.value;
                    if (closeIncompleteBoxesInput && incompleteBoxesInput) closeIncompleteBoxesInput.value = incompleteBoxesInput.value;
                    if (closeTotalBoxesInput && totalBoxesInput) closeTotalBoxesInput.value = totalBoxesInput.value;
                    if (closeFinalBoxUnitsInput && finalBoxUnitsInput) closeFinalBoxUnitsInput.value = finalBoxUnitsInput.value;
                    if (closeLeftoverBagsInput && leftoverBagsInput) closeLeftoverBagsInput.value = leftoverBagsInput.value;
                    if (closeShowroomBagsInput && showroomBagsInput) closeShowroomBagsInput.value = showroomBagsInput.value;
                    if (closeTotalUnitsInput && producedInput) closeTotalUnitsInput.value = producedInput.value;
                }
                function setCloseModal(open) {
                    if (!closeModal) return;
                    closeModal.style.display = open ? "block" : "none";
                    if (closeModalOpenInput) {
                        closeModalOpenInput.value = open ? "1" : "0";
                    }
                    if (open) {
                        syncCloseModalValues();
                    }
                }
                function syncTotalBoxes() {
                    if (!totalBoxesInput || !boxesPerPalletInput || !completePalletsInput || !incompleteBoxesInput) return;
                    var totalBoxes = (toNumber(boxesPerPalletInput) * toNumber(completePalletsInput)) + toNumber(incompleteBoxesInput);
                    if (totalBoxes > 0) {
                        totalBoxesInput.value = totalBoxes % 1 === 0 ? String(totalBoxes) : totalBoxes.toFixed(3).replace(/\.000$/, "");
                    }
                }
                function syncProducedUnits() {
                    if (!producedInput) return;
                    var produced = (toNumber(totalBoxesInput) * toNumber(unitsPerBoxInput)) + toNumber(finalBoxUnitsInput) + toNumber(leftoverBagsInput) + toNumber(showroomBagsInput);
                    producedInput.value = produced > 0 ? (produced % 1 === 0 ? String(produced) : produced.toFixed(3).replace(/\.000$/, "")) : "";
                }
                [boxesPerPalletInput, completePalletsInput, incompleteBoxesInput].forEach(function (input) {
                    if (!input) return;
                    input.addEventListener("input", function () {
                        syncTotalBoxes();
                        syncProducedUnits();
                    });
                });
                [unitsPerBoxInput, totalBoxesInput, finalBoxUnitsInput, leftoverBagsInput, showroomBagsInput].forEach(function (input) {
                    if (!input) return;
                    input.addEventListener("input", syncProducedUnits);
                });
                if (refreshButton) {
                    refreshButton.addEventListener("click", function () {
                        normalizeNumericInputs();
                        syncTotalBoxes();
                        syncProducedUnits();
                    });
                }
                if (openCloseModalButton) {
                    openCloseModalButton.addEventListener("click", function () {
                        setCloseModal(true);
                    });
                }
                [closeModalCloseButton, closeModalCancelButton].forEach(function (button) {
                    if (!button) return;
                    button.addEventListener("click", function () {
                        setCloseModal(false);
                    });
                });
                if (closeModal) {
                    closeModal.addEventListener("click", function (event) {
                        if (event.target === closeModal) {
                            setCloseModal(false);
                        }
                    });
                }
                if (closeModalOpenInput && closeModalOpenInput.value === "1") {
                    setCloseModal(true);
                }
            })();
        </script>';

    render('Terminar producción Embalaje', $body);
}

function renderWorkOrderSealingProgressModalsHtml(int $workOrderId, string $returnScreen): string
{
    $modalBaseStyle = 'display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto';
    $modalCardStyle = 'max-width:720px;margin:20px auto;background:#f4f5f7;border:1px solid #d6d6d6;border-radius:8px;padding:16px 18px;box-shadow:0 20px 45px rgba(15,23,42,.22)';

    return '<div class="legacy-inline-modal" id="progress-modal-maintenance" style="' . $modalBaseStyle . '">'
        . '<div style="' . $modalCardStyle . '">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Registrar mantención</div><button type="button" class="btn secondary" data-progress-modal-close="maintenance">Cerrar</button></div>'
        . '<form method="post" action="/work-orders/' . $workOrderId . '/production-event/start">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="event_key" value="MAINTENANCE">'
        . '<input type="hidden" name="return_screen" value="' . h($returnScreen) . '">'
        . '<div class="legacy-sheet-card"><table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell">Mantención</td><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Comentario</td><td class="legacy-value-cell" colspan="3"><textarea name="comments" rows="4" required placeholder="Describe la mantención realizada o el motivo del registro." style="width:100%;border:1px solid #cfd4dc;border-radius:3px;padding:8px"></textarea></td></tr>'
        . '</tbody></table></div>'
        . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-progress-modal-close="maintenance">Cancelar</button><button class="btn" type="submit">Guardar inicio</button></div>'
        . '</form></div></div>'
        . '<div class="legacy-inline-modal" id="progress-modal-pause" style="' . $modalBaseStyle . '">'
        . '<div style="' . $modalCardStyle . '">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Registrar pausa</div><button type="button" class="btn secondary" data-progress-modal-close="pause">Cerrar</button></div>'
        . '<form method="post" action="/work-orders/' . $workOrderId . '/production-event/start">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="event_key" value="PAUSE">'
        . '<input type="hidden" name="return_screen" value="' . h($returnScreen) . '">'
        . '<div class="legacy-sheet-card"><table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell">Pausa</td><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Comentario</td><td class="legacy-value-cell" colspan="3"><textarea name="comments" rows="4" required placeholder="Indica el motivo de la pausa." style="width:100%;border:1px solid #cfd4dc;border-radius:3px;padding:8px"></textarea></td></tr>'
        . '</tbody></table></div>'
        . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-progress-modal-close="pause">Cancelar</button><button class="btn" type="submit">Guardar inicio</button></div>'
        . '</form></div></div>'
        . '<script>
            (function () {
                function toggleProgressModal(key, open) {
                    var modal = document.getElementById("progress-modal-" + key);
                    if (!modal) {
                        return;
                    }
                    modal.style.display = open ? "block" : "none";
                }
                document.querySelectorAll("[data-progress-modal-open]").forEach(function (button) {
                    button.addEventListener("click", function () {
                        toggleProgressModal(button.getAttribute("data-progress-modal-open"), true);
                    });
                });
                document.querySelectorAll("[data-progress-modal-close]").forEach(function (button) {
                    button.addEventListener("click", function () {
                        toggleProgressModal(button.getAttribute("data-progress-modal-close"), false);
                    });
                });
                document.querySelectorAll(".legacy-inline-modal").forEach(function (modal) {
                    modal.addEventListener("click", function (event) {
                        if (event.target === modal && modal.id.indexOf("progress-modal-") === 0) {
                            modal.style.display = "none";
                        }
                    });
                });
            })();
        </script>';
}

function renderWorkOrderSealingInfoScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    ?array $lastFinish = null,
    array $sealingMachines = [],
    array $helperOptions = [],
    ?string $flashMessage = null,
    bool $flashIsError = false,
    array $formState = []
): void {
    $display = buildWorkOrderSealingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions);
    $helperValue = trim((string)($formState['helper_name'] ?? ''));
    if ($helperValue === '' && (string)$display['helper_label'] !== '-') {
        $helperValue = (string)$display['helper_label'];
    }
    $commentsValue = (string)($formState['comments'] ?? '');

    $body = '<div class="legacy-screen-title">Selladora</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Selladora</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/start">Volver</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= renderWorkOrderSealingOverviewHtml($ot, $display);
    $body .= '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/sealing/setup/start">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<div class="legacy-sheet-card" style="margin-top:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="2">Inicio de alistamiento Selladora</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">Tipo</td><td class="legacy-value-cell">SELLADORAS</td></tr>'
        . '<tr><td class="legacy-label-cell">Máquina</td><td class="legacy-value-cell">' . h((string)$display['station_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">De Medida *</td><td class="legacy-value-cell"><select disabled style="max-width:340px;min-height:34px;padding:6px 8px;font-size:13px;border-radius:4px"><option selected>' . h((string)$display['measure_compact'] !== '-' ? (string)$display['measure_compact'] : 'Sin medida definida') . '</option></select></td></tr>'
        . '<tr><td class="legacy-label-cell">A Medida *</td><td class="legacy-value-cell"><select disabled style="max-width:340px;min-height:34px;padding:6px 8px;font-size:13px;border-radius:4px"><option selected>' . h((string)$display['measure_label']) . '</option></select></td></tr>'
        . '<tr><td class="legacy-label-cell">Ayudante *</td><td class="legacy-value-cell"><select name="helper_name" required style="max-width:340px;min-height:34px;padding:6px 8px;font-size:13px;border-radius:4px"><option value="">&lt; Por favor seleccione &gt;</option>';
    foreach ((array)$display['helper_options'] as $helperOption) {
        $selected = $helperOption === $helperValue ? ' selected' : '';
        $body .= '<option value="' . h((string)$helperOption) . '"' . $selected . '>' . h((string)$helperOption) . '</option>';
    }
    if ($helperValue !== '' && !in_array($helperValue, (array)$display['helper_options'], true)) {
        $body .= '<option value="' . h($helperValue) . '" selected>' . h($helperValue) . '</option>';
    }
    $body .= '</select></td></tr>'
        . '<tr><td class="legacy-label-cell">Comentarios</td><td class="legacy-value-cell"><textarea name="comments" rows="3" style="width:100%;min-height:68px;resize:vertical;border:1px solid #c5cbd3;border-radius:4px;padding:8px 10px">' . h($commentsValue) . '</textarea></td></tr>'
        . '<tr><td class="legacy-value-cell" colspan="2" style="padding:10px"><button class="legacy-primary-btn" type="submit" style="width:100%;min-width:0">&#9654; Iniciar alistamiento</button></td></tr>'
        . '</tbody></table></div></form>';

    $body .= renderWorkOrderSealingStockModalHtml($outputRolls);
    render('Selladora', $body);
}

function renderWorkOrderSealingSetupScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    ?string $flashMessage = null,
    bool $flashIsError = false
): void {
    $service = $GLOBALS['service'] ?? null;
    $display = buildWorkOrderSealingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions);
    $setupEvents = $service instanceof ReceptionService ? $service->listWorkOrderSealingSetupEvents((int)$ot['id']) : [];
    $productionEvents = $service instanceof ReceptionService ? $service->listWorkOrderSealingProductionEvents((int)$ot['id']) : [];
    $processEvents = $service instanceof ReceptionService ? $service->listWorkOrderProcessEvents((int)$ot['id']) : [];
    $setupApproval = $service instanceof ReceptionService ? $service->getLastWorkOrderSealingSetupApproval((int)$ot['id']) : null;
    $lastSealingFinish = $service instanceof ReceptionService ? $service->getLastWorkOrderSealingFinish((int)$ot['id']) : null;
    $setupApproved = $setupApproval !== null;
    $sealingFinishAfterSetup = false;
    if ($lastSealingFinish !== null) {
        if ($setupApproval === null) {
            $sealingFinishAfterSetup = true;
        } else {
            $finishTs = strtotime((string)($lastSealingFinish['created_at'] ?? ''));
            $setupTs = strtotime((string)($setupApproval['created_at'] ?? ''));
            $sealingFinishAfterSetup = ($finishTs === false || $setupTs === false) ? true : ($finishTs >= $setupTs);
        }
    }
    $hasOpenProduction = false;
    foreach ($productionEvents as $productionEvent) {
        if (strtoupper((string)($productionEvent['status'] ?? '')) === 'OPEN') {
            $hasOpenProduction = true;
            break;
        }
    }
    $productionActionButton = $sealingFinishAfterSetup
        ? '<span class="legacy-setup-action production" style="opacity:.75;cursor:default">Producción terminada</span>'
        : ($hasOpenProduction
            ? '<span class="legacy-setup-action production" style="opacity:.75;cursor:default">Producción iniciada</span>'
            : ($setupApproved
                ? '<button class="legacy-setup-action production" type="button" data-progress-modal-open="production" style="border:0">Producción</button>'
                : '<span class="legacy-setup-action production" style="opacity:.5;cursor:not-allowed" title="Finaliza y valida el alistamiento para iniciar la producción.">Producción</span>'));

    $body = '<div class="legacy-screen-title">Alistamiento Selladora</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Selladora &gt; Alistamiento</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/sealing">Volver</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= renderWorkOrderSealingOverviewHtml($ot, $display);
    $body .= '<div class="legacy-setup-actions" style="grid-template-columns:repeat(5,minmax(0,1fr));margin-top:12px">'
        . '<a class="legacy-setup-action materials active" href="/work-orders/' . (int)$ot['id'] . '/materials">Utilizar materiales</a>'
        . $productionActionButton
        . '<button class="legacy-setup-action maintenance" type="button" data-progress-modal-open="maintenance" style="border:0">Registrar mantención</button>'
        . '<button class="legacy-setup-action pause" type="button" data-progress-modal-open="pause" style="border:0">Pausa</button>'
        . '<a class="legacy-setup-action consumption" href="#">Consumo</a>'
        . '</div>';
    $body .= '<div class="legacy-sheet-card" style="margin-top:12px"><div class="table-wrap"><table class="legacy-sheet-table legacy-setup-event-table" style="margin:0"><thead><tr><th>Evento</th><th>Inicio</th><th>Termino</th><th>Tiempo</th><th>Cantidad</th><th>Comentarios</th><th>Opciones</th></tr></thead><tbody>';
    foreach ($setupEvents as $setupEvent) {
        $startedAt = (string)($setupEvent['started_at'] ?? '');
        $endedAt = (string)($setupEvent['ended_at'] ?? '');
        $isOpen = strtoupper((string)($setupEvent['status'] ?? '')) === 'OPEN';
        $eventComments = trim((string)($setupEvent['detail'] ?? '')) !== ''
            ? (string)$setupEvent['detail']
            : (trim((string)($setupEvent['comments'] ?? '')) !== '' ? (string)$setupEvent['comments'] : '-');
        $optionsHtml = '<span class="muted">-</span>';
        if ($isOpen) {
            $optionsHtml = '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/sealing/setup-event/finish" style="margin:0">'
                . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                . '<input type="hidden" name="start_event_id" value="' . (int)($setupEvent['start_event_id'] ?? 0) . '">'
                . '<button class="btn secondary legacy-setup-finish-btn" type="submit">Terminar</button>'
                . '</form>';
        }
        $body .= '<tr>'
            . '<td>' . h((string)($setupEvent['event_label'] ?? 'Alistamiento')) . '</td>'
            . '<td>' . h(formatLabelDateTime($startedAt)) . '</td>'
            . '<td>' . h($endedAt !== '' ? formatLabelDateTime($endedAt) : '-') . '</td>'
            . '<td>' . h(formatElapsedLabel($startedAt, $endedAt !== '' ? $endedAt : null)) . '</td>'
            . '<td>' . h((string)$display['target_qty_label']) . '</td>'
            . '<td>' . h($eventComments) . '</td>'
            . '<td>' . $optionsHtml . '</td>'
            . '</tr>';
    }
    foreach ($productionEvents as $productionEvent) {
        $startedAt = (string)($productionEvent['started_at'] ?? '');
        $endedAt = (string)($productionEvent['ended_at'] ?? '');
        $isOpen = strtoupper((string)($productionEvent['status'] ?? '')) === 'OPEN';
        $productionComments = trim((string)($productionEvent['detail'] ?? '')) !== ''
            ? (string)$productionEvent['detail']
            : (trim((string)($productionEvent['comments'] ?? '')) !== '' ? (string)$productionEvent['comments'] : '-');
        $optionsHtml = $isOpen
            ? '<a class="btn secondary legacy-setup-finish-btn" href="/work-orders/' . (int)$ot['id'] . '/sealing/finish-data">Terminar producción</a>'
            : '<span class="btn secondary legacy-setup-finish-btn" style="opacity:.75;cursor:default">Terminado</span>';
        $body .= '<tr>'
            . '<td>' . h((string)($productionEvent['event_label'] ?? 'Producción')) . '</td>'
            . '<td>' . h($startedAt !== '' ? formatLabelDateTime($startedAt) : '-') . '</td>'
            . '<td>' . h($endedAt !== '' ? formatLabelDateTime($endedAt) : '-') . '</td>'
            . '<td>' . h(formatElapsedLabel($startedAt, $endedAt !== '' ? $endedAt : null)) . '</td>'
            . '<td>' . h((string)$display['target_qty_label']) . '</td>'
            . '<td>' . h($productionComments) . '</td>'
            . '<td>' . $optionsHtml . '</td>'
            . '</tr>';
    }
    foreach ($processEvents as $processEvent) {
        $startedAt = (string)($processEvent['started_at'] ?? '');
        $endedAt = (string)($processEvent['ended_at'] ?? '');
        $isOpen = strtoupper((string)($processEvent['status'] ?? '')) === 'OPEN';
        $optionsHtml = '<span class="muted">-</span>';
        if ($isOpen) {
            $optionsHtml = '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/production-event/finish" style="margin:0">'
                . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                . '<input type="hidden" name="start_event_id" value="' . (int)($processEvent['start_event_id'] ?? 0) . '">'
                . '<input type="hidden" name="return_screen" value="SEALING_SETUP">'
                . '<button class="btn secondary legacy-setup-finish-btn" type="submit">Terminar</button>'
                . '</form>';
        }
        $body .= '<tr>'
            . '<td>' . h((string)($processEvent['event_label'] ?? '-')) . '</td>'
            . '<td>' . h(formatLabelDateTime($startedAt)) . '</td>'
            . '<td>' . h($endedAt !== '' ? formatLabelDateTime($endedAt) : '-') . '</td>'
            . '<td>' . h(formatElapsedLabel($startedAt, $endedAt !== '' ? $endedAt : null)) . '</td>'
            . '<td>-</td>'
            . '<td>' . h((string)($processEvent['comments'] ?? '-')) . '</td>'
            . '<td>' . $optionsHtml . '</td>'
            . '</tr>';
    }
    if ($setupEvents === [] && $productionEvents === [] && $processEvents === []) {
        $body .= '<tr><td colspan="7" class="legacy-value-cell">Todavía no hay eventos registrados para Selladora.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    if ($setupApproved && !$hasOpenProduction && !$sealingFinishAfterSetup) {
        $body .= '<div class="legacy-inline-modal" id="progress-modal-production" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto">'
            . '<div style="max-width:720px;margin:20px auto;background:#f4f5f7;border:1px solid #d6d6d6;border-radius:8px;padding:16px 18px;box-shadow:0 20px 45px rgba(15,23,42,.22)">'
            . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Iniciar producción Selladora</div><button type="button" class="btn secondary" data-progress-modal-close="production">Cerrar</button></div>'
            . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/sealing/production/start">'
            . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
            . '<div class="legacy-sheet-card"><table class="legacy-sheet-table" style="margin:0"><tbody>'
            . '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell">Producción</td><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>'
            . '<tr><td class="legacy-label-cell">Comentario</td><td class="legacy-value-cell" colspan="3"><textarea name="comments" rows="4" required placeholder="Ingresa el comentario para iniciar la producción de Selladora." style="width:100%;border:1px solid #cfd4dc;border-radius:3px;padding:8px"></textarea></td></tr>'
            . '</tbody></table></div>'
            . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-progress-modal-close="production">Cancelar</button><button class="btn" type="submit">Guardar inicio</button></div>'
            . '</form></div></div>';
    }
    $body .= renderWorkOrderSealingProgressModalsHtml((int)$ot['id'], 'SEALING_SETUP');
    $body .= renderWorkOrderSealingStockModalHtml($outputRolls);

    render('Alistamiento Selladora', $body);
}

function renderWorkOrderSealingSetupApprovalScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    string $role = 'SUPERVISOR',
    ?string $flashMessage = null,
    bool $flashIsError = false,
    bool $showValidationForm = false,
    string $approvalUsername = '',
    ?string $validatedByLabel = null
): void {
    $display = buildWorkOrderSealingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions);
    $autocontrolItems = [
        'Sellado Bolsa',
        'Medidas Bolsa',
        'Medidas Fuelle',
        'Color Bolsa',
        'Sellado Manillas',
        'Largo Manillas',
        'Etiquetas / Alarma',
        'Dados Manillas',
    ];
    $role = strtoupper(trim($role)) === 'LEADER' ? 'LEADER' : 'SUPERVISOR';
    $roleLabel = $role === 'LEADER' ? 'Líder de Área' : 'Supervisor';
    $measuresLabel = trim((string)$display['width_label'] . ' x ' . (string)$display['height_label']);
    if ($measuresLabel === 'x' || trim($measuresLabel) === '') {
        $measuresLabel = (string)$display['measure_compact'];
    }
    $areaLabel = (string)$display['gusset_label'] !== '-'
        ? trim((string)$display['width_label'] . ' x ' . (string)$display['gusset_label'])
        : $measuresLabel;
    $fabricLabel = (string)$display['color_label'] !== '' ? (string)$display['color_label'] : '-';
    $handlesLabel = $fabricLabel !== '' ? $fabricLabel : '-';
    $colorsLabel = $fabricLabel !== '' ? $fabricLabel : '-';

    $body = '<div class="legacy-screen-title">Aprobación alistamiento Selladora</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Selladora &gt; Aprobación</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/sealing/setup">Volver</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><tbody>'
        . '<tr><td class="legacy-label-cell">N° OT</td><td class="legacy-value-cell">' . h((string)$ot['ot_code']) . '</td><td class="legacy-label-cell">N° CC</td><td class="legacy-value-cell">' . h((string)$display['request_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cliente</td><td class="legacy-value-cell">' . h((string)$display['client_label']) . '</td><td class="legacy-label-cell">Producto</td><td class="legacy-value-cell">' . h((string)$display['product_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Medidas</td><td class="legacy-value-cell">' . h($measuresLabel !== '' ? $measuresLabel : '-') . '</td><td class="legacy-label-cell">Fuelle</td><td class="legacy-value-cell">' . h((string)$display['gusset_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Area</td><td class="legacy-value-cell">' . h($areaLabel !== '' ? $areaLabel : '-') . '</td><td class="legacy-label-cell">Tela</td><td class="legacy-value-cell">' . h($fabricLabel !== '' ? $fabricLabel : '-') . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Manillas</td><td class="legacy-value-cell">' . h($handlesLabel !== '' ? $handlesLabel : '-') . '</td><td class="legacy-label-cell">Colores</td><td class="legacy-value-cell">' . h($colorsLabel !== '' ? $colorsLabel : '-') . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad</td><td class="legacy-value-cell">' . h((string)$display['target_qty_label']) . '</td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
        . '</tbody></table></div>';

    $body .= '<div class="autocontrol-panel"><div class="autocontrol-panel-header">AUTOCONTROL</div><table class="autocontrol-table"><tbody>';
    for ($rowIndex = 0; $rowIndex < count($autocontrolItems); $rowIndex += 4) {
        $body .= '<tr>';
        foreach (array_slice($autocontrolItems, $rowIndex, 4) as $autocontrolItem) {
            $body .= '<td><label class="autocontrol-checkline">'
                . '<input type="checkbox" data-sealing-autocontrol-check="1">'
                . '<span>' . h($autocontrolItem) . '</span>'
                . '</label></td>';
        }
        $body .= '</tr>';
    }
    $body .= '</tbody></table></div>';

    $approvalPanelVisible = $showValidationForm || ($validatedByLabel !== null && trim($validatedByLabel) !== '');
    $body .= '<div class="approval-actions" id="sealing-autocontrol-actions">'
        . '<button type="button" class="approval-action-btn supervisor hidden" id="sealing-open-approval-btn" style="border:0;cursor:pointer">Informar supervisor</button>'
        . '</div>';

    $body .= '<div class="autocontrol-panel" id="sealing-approval-panel" style="' . ($approvalPanelVisible ? '' : 'display:none;') . '"><div class="approval-panel-header"><div class="approval-header-actions"><label class="approval-mark-all"><input type="checkbox" id="sealing-approval-mark-all"><span>Marcar todos</span></label></div><div class="title">APROBACIÓN PARTIDA</div></div><table class="autocontrol-table"><tbody>';
    for ($rowIndex = 0; $rowIndex < count($autocontrolItems); $rowIndex += 4) {
        $body .= '<tr>';
        foreach (array_slice($autocontrolItems, $rowIndex, 4) as $autocontrolItem) {
            $body .= '<td><label class="autocontrol-checkline">'
                . '<input type="checkbox" data-sealing-approval-check="1">'
                . '<span>' . h($autocontrolItem) . '</span>'
                . '</label></td>';
        }
        $body .= '</tr>';
    }
    $body .= '</tbody></table>';
    $body .= '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/sealing/setup-approval/validate" id="sealing-approval-validation-form" class="approval-validation' . ($showValidationForm ? ' visible' : '') . '" data-force-visible="' . ($showValidationForm ? '1' : '0') . '">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="role" value="' . h($role) . '">'
        . '<input type="hidden" name="approval_ready" id="sealing-approval-ready" value="0">'
        . '<input type="text" name="approval_username" value="' . h($approvalUsername) . '" placeholder="Usuario ' . h(strtolower($roleLabel)) . '" autocomplete="username" required>'
        . '<input type="password" name="approval_password" placeholder="Clave" autocomplete="current-password" required>'
        . '<button class="btn" type="submit">Aprobar partida</button>'
        . '</form>';
    if ($validatedByLabel !== null && trim($validatedByLabel) !== '') {
        $body .= '<div class="approval-validation-status ok">Partida validada por <strong>' . h($roleLabel) . '</strong>: ' . h($validatedByLabel) . '.</div>';
    }
    $body .= '</div>';
    $body .= '<div class="legacy-setup-actions" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-top:12px">'
        . '<a class="legacy-setup-action materials active" href="/work-orders/' . (int)$ot['id'] . '/materials">Utilizar materiales</a>'
        . '<button class="legacy-setup-action maintenance" type="button" data-progress-modal-open="maintenance" style="border:0">Registrar mantención</button>'
        . '<button class="legacy-setup-action pause" type="button" data-progress-modal-open="pause" style="border:0">Pausa</button>'
        . '</div>';
    $body .= renderWorkOrderSealingProgressModalsHtml((int)$ot['id'], 'SEALING_SETUP');
    $body .= renderWorkOrderSealingStockModalHtml($outputRolls);
    $body .= '<script>
        (function () {
            var autocontrolChecks = Array.prototype.slice.call(document.querySelectorAll("[data-sealing-autocontrol-check]"));
            var openApprovalButton = document.getElementById("sealing-open-approval-btn");
            var approvalPanel = document.getElementById("sealing-approval-panel");
            var master = document.getElementById("sealing-approval-mark-all");
            var checks = Array.prototype.slice.call(document.querySelectorAll("[data-sealing-approval-check]"));
            var validationForm = document.getElementById("sealing-approval-validation-form");
            var readyInput = document.getElementById("sealing-approval-ready");
            if (!master || !checks.length) {
                return;
            }
            function syncAutocontrolState() {
                if (!autocontrolChecks.length || !openApprovalButton) {
                    return;
                }
                var allAutocontrolChecked = autocontrolChecks.every(function (check) {
                    return check.checked;
                });
                openApprovalButton.classList.toggle("hidden", !allAutocontrolChecked);
            }
            function syncApprovalState() {
                var allChecked = checks.every(function (check) {
                    return check.checked;
                });
                master.checked = allChecked;
                if (readyInput) {
                    readyInput.value = allChecked ? "1" : "0";
                }
                if (validationForm) {
                    var shouldShow = allChecked || validationForm.getAttribute("data-force-visible") === "1";
                    validationForm.classList.toggle("visible", shouldShow);
                }
            }
            if (openApprovalButton) {
                openApprovalButton.addEventListener("click", function () {
                    if (approvalPanel) {
                        approvalPanel.style.display = "";
                    }
                });
            }
            master.addEventListener("change", function () {
                checks.forEach(function (check) {
                    check.checked = master.checked;
                });
                syncApprovalState();
            });
            autocontrolChecks.forEach(function (check) {
                check.addEventListener("change", syncAutocontrolState);
            });
            checks.forEach(function (check) {
                check.addEventListener("change", syncApprovalState);
            });
            syncAutocontrolState();
            syncApprovalState();
        })();
    </script>';

    render('Aprobación alistamiento Selladora', $body);
}

function renderWorkOrderSealingFinishScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    ?string $flashMessage = null,
    bool $flashIsError = false,
    array $formState = []
): void {
    $display = buildWorkOrderSealingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions);
    $principalCounterState = (string)($formState['sealing_principal_counter'] ?? '');
    $receiverCounterState = (string)($formState['sealing_receiver_counter'] ?? '');
    $commentsState = (string)($formState['sealing_finish_comments'] ?? '');
    $wasteWeightState = isset($formState['sealing_waste_weight']) && is_array($formState['sealing_waste_weight'])
        ? $formState['sealing_waste_weight']
        : [];
    $wasteCommentState = isset($formState['sealing_waste_comment']) && is_array($formState['sealing_waste_comment'])
        ? $formState['sealing_waste_comment']
        : [];
    $wasteRows = [
        ['key' => 'setup', 'title' => 'Merma', 'label' => 'Configuración'],
        ['key' => 'printing', 'title' => 'Merma', 'label' => 'Impresión'],
        ['key' => 'other', 'title' => 'Merma', 'label' => 'Otros'],
        ['key' => 'repair', 'title' => 'Reparación', 'label' => 'Reparación Manillas / Configuración'],
    ];

    $body = '<div class="legacy-screen-title">Terminar producción Selladora</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Selladora &gt; Terminar producción</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/sealing/setup">Volver</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= renderWorkOrderSealingOverviewHtml($ot, $display);
    $body .= renderWorkOrderSealingStockModalHtml($outputRolls);
    $body .= '<div class="legacy-sheet-card finish-production-card"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/sealing/finish" id="sealing-finish-form">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<div class="finish-form-row finish-form-row-inline">'
        . '<div class="finish-label">Tipo</div>'
        . '<div class="finish-static-value">SELLADORAS</div>'
        . '</div>'
        . '<div class="finish-form-row finish-form-row-inline">'
        . '<div class="finish-label">Máquina</div>'
        . '<div class="finish-static-value">' . h((string)$display['station_label']) . '</div>'
        . '</div>'
        . '<div class="finish-form-row finish-form-row-inline">'
        . '<div class="finish-label">Contador Tablero Principal</div>'
        . '<div class="finish-inline-input"><input name="sealing_principal_counter" type="number" step="1" min="0" value="' . h($principalCounterState) . '"></div>'
        . '<div class="finish-inline-suffix">unidades</div>'
        . '</div>'
        . '<div class="finish-form-row finish-form-row-inline">'
        . '<div class="finish-label">Contador Módulo Recibidor</div>'
        . '<div class="finish-inline-input"><input name="sealing_receiver_counter" type="number" step="1" min="0" value="' . h($receiverCounterState) . '"></div>'
        . '<div class="finish-inline-suffix">unidades</div>'
        . '</div>'
        . '<div class="finish-form-row finish-form-row-comments">'
        . '<div class="finish-label">Comentarios</div>'
        . '<div class="finish-comments-wrap"><textarea name="sealing_finish_comments" rows="3">' . h($commentsState) . '</textarea></div>'
        . '</div>'
        . '<div class="finish-actions">'
        . '<div class="finish-actions-left"><a class="btn secondary finish-grey-btn" href="/work-orders/' . (int)$ot['id'] . '/sealing/setup">Volver</a><button class="btn secondary finish-red-btn" type="reset">Eliminar</button></div>'
        . '<div class="finish-actions-right"><button class="btn secondary finish-grey-btn" type="button" id="sealing-finish-refresh">Actualizar</button><button class="btn finish-yellow-btn" type="submit">Terminar producción</button></div>'
        . '</div>'
        . '<div class="finish-merma-block" style="margin-top:12px">';
    foreach ($wasteRows as $wasteRow) {
        $body .= '<div class="finish-form-row finish-merma-row">'
            . '<div class="finish-merma-title">' . h((string)$wasteRow['title']) . '</div>'
            . '<div class="finish-merma-detail">' . h((string)$wasteRow['label']) . '</div>'
            . '<div class="finish-merma-unit"><select disabled><option>Kilos</option></select></div>'
            . '<div class="finish-merma-weight"><input type="number" step="0.001" min="0" name="sealing_waste_weight[' . h((string)$wasteRow['key']) . ']" value="' . h((string)($wasteWeightState[$wasteRow['key']] ?? '')) . '"></div>'
            . '<div class="finish-merma-comment-label">Comentarios</div>'
            . '<div class="finish-merma-comment"><input type="text" name="sealing_waste_comment[' . h((string)$wasteRow['key']) . ']" value="' . h((string)($wasteCommentState[$wasteRow['key']] ?? '')) . '"></div>'
            . '</div>';
    }
    $body .= '</div>'
        . '</form></div>'
        . '<script>
            (function () {
                var form = document.getElementById("sealing-finish-form");
                if (!form) return;
                var refreshButton = document.getElementById("sealing-finish-refresh");
                function normalizeNumericInputs() {
                    form.querySelectorAll("input[type=number]").forEach(function (input) {
                        var value = parseFloat(input.value || "0");
                        if (!isFinite(value) || value < 0) {
                            input.value = "";
                        }
                    });
                }
                if (refreshButton) {
                    refreshButton.addEventListener("click", normalizeNumericInputs);
                }
            })();
        </script>';

    $body .= renderWorkOrderSealingStockModalHtml($outputRolls);
    render('Terminar producción Selladora', $body);
}

function renderWorkOrderMaterialUsageScreen(
    array $ot,
    ?array $currentRoll,
    array $chemicalInputs,
    array $materialRequests,
    array $availableMaterialRolls,
    array $boxes,
    ?array $activeShiftSession = null,
    array $assignedCliches = [],
    ?array $outputRoll = null,
    ?string $flashMessage = null,
    bool $flashIsError = false,
    array $formState = []
): void {
    $service = $GLOBALS['service'] ?? null;
    $sessionForDisplay = null;
    if ((int)($ot['shift_session_id'] ?? 0) > 0) {
        $sessionForDisplay = [
            'id' => (int)($ot['shift_session_id'] ?? 0),
            'machine_type_name' => (string)($ot['shift_machine_type_name'] ?? ''),
            'machine_name' => (string)($ot['shift_machine_name'] ?? ''),
            'operator_name' => (string)($ot['shift_operator_name'] ?? ''),
            'helper_name' => (string)($ot['shift_helper_name'] ?? ''),
            'shift_label' => (string)($ot['shift_shift_label'] ?? ''),
            'started_at' => (string)($ot['shift_started_at'] ?? ''),
            'comments' => (string)($ot['shift_comments'] ?? ''),
            'process_stage' => (string)($ot['shift_process_stage'] ?? ''),
        ];
    } elseif (is_array($activeShiftSession) && $activeShiftSession !== []) {
        $sessionForDisplay = $activeShiftSession;
    }
    $releaseFinalWeightState = (string)($formState['final_weight_kg'] ?? (isset($currentRoll['weight_kg'])
        ? rtrim(rtrim(number_format((float)$currentRoll['weight_kg'], 2, '.', ''), '0'), '.')
        : ''));
    $releaseWasteTotalState = (string)($formState['waste_kg'] ?? '0');
    $releaseWasteWeightState = isset($formState['release_waste_detail_weight']) && is_array($formState['release_waste_detail_weight'])
        ? $formState['release_waste_detail_weight']
        : [];
    $releaseWasteCommentState = isset($formState['release_waste_detail_comment']) && is_array($formState['release_waste_detail_comment'])
        ? $formState['release_waste_detail_comment']
        : [];
    $releaseWasteRows = [
        ['key' => 'approval', 'label' => 'Ajuste / Aprobación'],
        ['key' => 'printing', 'label' => 'Impresión'],
        ['key' => 'process', 'label' => 'Merma Proceso'],
        ['key' => 'other', 'label' => 'Otros'],
    ];

    $sheetText = trim((string)($ot['erp_plan_desc'] ?? '') . ' ' . (string)($ot['sku_final'] ?? ''));
    $targetQtyLabel = trim((string)($ot['erp_target_qty'] ?? '')) !== ''
        ? (string)$ot['erp_target_qty']
        : (trim((string)($ot['target_qty'] ?? '')) !== '' ? (string)$ot['target_qty'] : '-');
    $stationLabel = trim((string)($sessionForDisplay['machine_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['machine_name']
        : (trim((string)($ot['erp_machine_label'] ?? '')) !== '' ? (string)$ot['erp_machine_label'] : 'Sin máquina asignada');
    $widthLabel = '-';
    $heightLabel = '-';
    $gussetLabel = '-';
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*[xX]\s*(\d+(?:[.,]\d+)?)(?:\s*[xX]\s*(\d+(?:[.,]\d+)?))?/', $sheetText, $dimensionMatch) === 1) {
        $widthLabel = $dimensionMatch[1];
        $heightLabel = $dimensionMatch[2];
        $gussetLabel = trim((string)($dimensionMatch[3] ?? '')) !== '' ? (string)$dimensionMatch[3] : '-';
    }
    $materialLabel = '-';
    foreach (['PLA', 'BOPP', 'PP', 'PEBD', 'PEAD', 'PE'] as $materialKeyword) {
        if (stripos($sheetText, $materialKeyword) !== false) {
            $materialLabel = $materialKeyword;
            break;
        }
    }
    $detectedColors = [];
    foreach ([trim((string)($currentRoll['color'] ?? '')), trim((string)($outputRoll['color'] ?? ''))] as $colorValue) {
        if ($colorValue !== '' && !in_array($colorValue, $detectedColors, true)) {
            $detectedColors[] = $colorValue;
        }
    }
    foreach ($chemicalInputs as $chemicalInput) {
        foreach ([trim((string)($chemicalInput['chemical_name'] ?? '')), trim((string)($chemicalInput['chemical_code'] ?? ''))] as $colorValue) {
            if ($colorValue !== '' && !in_array($colorValue, $detectedColors, true)) {
                $detectedColors[] = $colorValue;
            }
        }
    }
    foreach (['Natural', 'Blanco', 'Beige', 'Azul', 'Rojo', 'Verde', 'Negro', 'Transparente'] as $colorKeyword) {
        if (stripos($sheetText, $colorKeyword) !== false && !in_array($colorKeyword, $detectedColors, true)) {
            $detectedColors[] = $colorKeyword;
        }
    }
    $colorSlots = array_pad(array_slice($detectedColors, 0, 6), 6, '-');
    $aniloxCatalog = $service instanceof ReceptionService ? $service->listAniloxCatalog() : [];
    $savedAniloxAssignments = $service instanceof ReceptionService ? $service->getWorkOrderAniloxAssignments((int)$ot['id']) : [];
    $aniloxSlots = buildWorkOrderAniloxSlots($colorSlots, $chemicalInputs, $savedAniloxAssignments, null);
    $suggestedAniloxColors = extractSuggestedAniloxColors($colorSlots, $chemicalInputs, $aniloxSlots);
    $totalUnitsQty = 0;
    foreach ($boxes as $box) {
        $totalUnitsQty += (int)($box['units_qty'] ?? 0);
    }
    $targetQtyNumber = is_numeric((string)$targetQtyLabel) ? (float)$targetQtyLabel : 0.0;
    $producedPercent = $targetQtyNumber > 0 ? ($totalUnitsQty * 100) / $targetQtyNumber : 0.0;
    $producedProgressLabel = number_format((float)$totalUnitsQty, 0, '.', '.')
        . ' de '
        . ($targetQtyNumber > 0 ? number_format($targetQtyNumber, 0, '.', '.') : '0')
        . ' (' . number_format($producedPercent, 0, '.', '.') . ' %)';
    $saldoTotalUnits = max(0.0, $targetQtyNumber - (float)$totalUnitsQty);
    $saldoTotalLabel = $targetQtyNumber > 0 ? number_format($saldoTotalUnits, 0, '.', '.') . ' unidades' : '-';
    $fabricColorLabel = trim((string)($currentRoll['color'] ?? '')) !== ''
        ? (string)$currentRoll['color']
        : ((string)$colorSlots[0] !== '-' ? (string)$colorSlots[0] : '-');
    $fabricWidthLabel = isset($currentRoll['width_mm']) && (string)$currentRoll['width_mm'] !== ''
        ? rtrim(rtrim(number_format((float)$currentRoll['width_mm'], 2, '.', ''), '0'), '.')
        : $widthLabel;
    $fabricGramajeLabel = isset($currentRoll['grams']) && (string)$currentRoll['grams'] !== ''
        ? rtrim(rtrim(number_format((float)$currentRoll['grams'], 2, '.', ''), '0'), '.') . ' gr'
        : '-';
    $fabricMetersLabel = isset($currentRoll['meters']) && (string)$currentRoll['meters'] !== ''
        ? number_format((float)$currentRoll['meters'], 0, '.', '.')
        : '-';
    $fabricKgLabel = isset($currentRoll['weight_kg']) && (string)$currentRoll['weight_kg'] !== ''
        ? rtrim(rtrim(number_format((float)$currentRoll['weight_kg'], 2, '.', ''), '0'), '.') . ' kgs'
        : '-';
    $primaryCliche = $assignedCliches[0] ?? null;
    $primaryClicheLocationLabel = trim((string)($primaryCliche['location_detail'] ?? $primaryCliche['location_code'] ?? ''));
    if ($primaryClicheLocationLabel === '') {
        $primaryClicheLocationLabel = trim((string)($ot['erp_machine_label'] ?? ''));
    }
    $primaryClicheCodeLabel = trim((string)($primaryCliche['code'] ?? ''));
    if ($primaryClicheCodeLabel === '') {
        $primaryClicheCodeLabel = trim((string)($ot['erp_prod_number'] ?? ''));
    }
    $primarySelectedAniloxLabel = '-';
    foreach ($aniloxSlots as $aniloxSlot) {
        if ((int)($aniloxSlot['anilox_id'] ?? 0) <= 0) {
            continue;
        }
        foreach ($aniloxCatalog as $aniloxRow) {
            if ((int)($aniloxRow['id'] ?? 0) === (int)$aniloxSlot['anilox_id']) {
                $primarySelectedAniloxLabel = trim((string)($aniloxRow['display_label'] ?? ''));
                if ($primarySelectedAniloxLabel === '') {
                    $primarySelectedAniloxLabel = trim((string)($aniloxRow['code'] ?? '')) . ' - ' . trim((string)($aniloxRow['name'] ?? ''));
                }
                break 2;
            }
        }
    }
    $configuredColorCount = count(array_values(array_filter(
        array_map(static fn(array $slot): string => trim((string)($slot['color_name'] ?? '')), $aniloxSlots),
        static fn(string $value): bool => $value !== ''
    )));
    $impressionsPerDevelopmentLabel = $configuredColorCount > 0 ? ($configuredColorCount . ' impresiones') : 'Sin información';
    $counterPrinterLabel = $totalUnitsQty > 0 ? number_format((float)$totalUnitsQty, 0, '.', '.') : '-';
    $topUnitRows = [];
    for ($unitIndex = 0; $unitIndex < 6; $unitIndex++) {
        $topUnitRows[] = [
            'label' => 'Unidad N° ' . ($unitIndex + 1),
            'value' => trim((string)($aniloxSlots[$unitIndex]['color_name'] ?? '')) !== ''
                ? h((string)$aniloxSlots[$unitIndex]['color_name'])
                : '-',
        ];
    }
    $bottomUnitRows = [];
    for ($unitIndex = 0; $unitIndex < 6; $unitIndex++) {
        $bottomUnitRows[] = [
            'label' => 'Unidad N° ' . ($unitIndex + 1),
            'value' => '-',
        ];
    }
    $imagePlaceholder = '<span class="legacy-placeholder-btn">Mostrar imagen</span>';
    $legacyClientRows = [
        ['N° OT', h((string)$ot['ot_code']), 'Color N° 1', h((string)$colorSlots[0])],
        ['Cliente', '-', 'Color N° 2', h((string)$colorSlots[1])],
        ['N° CC', trim((string)($ot['erp_req_id'] ?? '')) !== '' ? h((string)$ot['erp_req_id']) : '-', 'Color N° 3', h((string)$colorSlots[2])],
        ['Diseño', trim((string)($ot['erp_plan_desc'] ?? '')) !== '' ? h((string)$ot['erp_plan_desc']) : '-', 'Color N° 4', h((string)$colorSlots[3])],
        ['Producto', h((string)$ot['sku_final']), 'Color N° 5', h((string)$colorSlots[4])],
        ['Materialidad', h($materialLabel), 'Color N° 6', h((string)$colorSlots[5])],
        ['Ancho', h($widthLabel), 'Alto', h($heightLabel)],
        ['Fuelle', h($gussetLabel), 'Cantidad de Bolsas', h($targetQtyLabel)],
        ['Fecha de Entrega', trim((string)($ot['erp_plan_date'] ?? '')) !== '' ? h(formatLabelDate((string)$ot['erp_plan_date'])) : '-', 'Maquina', h($stationLabel)],
        ['Fecha de solicitud de cliché', '-', 'Fecha de recepción de cliché', '-'],
        ['Imagen Diseño', $imagePlaceholder, 'Imagen #5', $imagePlaceholder],
    ];
    $legacyFabricationRows = [
        ['Rodillo a utilizar', h($primarySelectedAniloxLabel), $topUnitRows[0]['label'], $topUnitRows[0]['value']],
        ['Corte de bolsa', h($heightLabel !== '-' ? ($heightLabel . ' mtrs') : '-'), $topUnitRows[1]['label'], $topUnitRows[1]['value']],
        ['Ubicación Cliché', h($primaryClicheLocationLabel !== '' ? $primaryClicheLocationLabel : '-'), $topUnitRows[2]['label'], $topUnitRows[2]['value']],
        ['Código Cliché', h($primaryClicheCodeLabel !== '' ? $primaryClicheCodeLabel : '-'), $topUnitRows[3]['label'], $topUnitRows[3]['value']],
        ['Pie de Imprenta', 'Cliente', $topUnitRows[4]['label'], $topUnitRows[4]['value']],
        ['N° Código de Barra', trim((string)($currentRoll['roll_code'] ?? '')) !== '' ? h((string)$currentRoll['roll_code']) : '-', $topUnitRows[5]['label'], $topUnitRows[5]['value']],
        ['Impresiones al eje', 'Sin información', $bottomUnitRows[0]['label'], $bottomUnitRows[0]['value']],
        ['Impresiones por desarrollo', h($impressionsPerDevelopmentLabel), $bottomUnitRows[1]['label'], $bottomUnitRows[1]['value']],
        ['% de merma', '-', $bottomUnitRows[2]['label'], $bottomUnitRows[2]['value']],
        ['Bolsas producidas / en curso', h($producedProgressLabel), $bottomUnitRows[3]['label'], $bottomUnitRows[3]['value']],
        ['Bolsas producidas / total', h($producedProgressLabel), $bottomUnitRows[4]['label'], $bottomUnitRows[4]['value']],
        ['Saldo total', h($saldoTotalLabel), $bottomUnitRows[5]['label'], $bottomUnitRows[5]['value']],
        ['', '', '', ''],
        ['Materialidad', h($materialLabel), 'Metros a imprimir', h($fabricMetersLabel)],
        ['Color Tela', h($fabricColorLabel), 'Contador impresora', h($counterPrinterLabel)],
        ['Ancho Tela', h($fabricWidthLabel), 'Kg a imprimir', h($fabricKgLabel)],
        ['Gramaje', h($fabricGramajeLabel), '', ''],
    ];

    $availableRollsByGroup = [];
    foreach ($availableMaterialRolls as $availableRoll) {
        $groupKey = implode('|', [
            (string)($availableRoll['sku_code'] ?? ''),
            (string)($availableRoll['sku_description'] ?? ''),
            (string)($availableRoll['grams'] ?? ''),
            (string)($availableRoll['width_mm'] ?? ''),
            trim((string)($availableRoll['color'] ?? '')),
            (string)($availableRoll['meters'] ?? ''),
            (string)($availableRoll['process_stage'] ?? ''),
        ]);
        if (!isset($availableRollsByGroup[$groupKey])) {
            $availableRollsByGroup[$groupKey] = [];
        }
        $availableRollsByGroup[$groupKey][] = $availableRoll;
    }

    $rollRequests = array_values(array_filter(
        $materialRequests,
        static fn(array $request): bool => strtoupper(trim((string)($request['request_type'] ?? 'ROLL'))) === 'ROLL'
    ));
    $activeRolls = $service instanceof ReceptionService ? $service->listActiveRollsInWorkOrder((int)$ot['id']) : [];
    $activeRollIds = [];
    $activeRollsById = [];
    foreach ($activeRolls as $activeRoll) {
        $activeRollId = (int)($activeRoll['id'] ?? 0);
        if ($activeRollId <= 0) {
            continue;
        }
        $activeRollIds[$activeRollId] = true;
        $activeRollsById[$activeRollId] = $activeRoll;
    }
    $deliveriesByRequest = [];
    $deliveredRollRequestMap = [];
    if ($service instanceof ReceptionService) {
        foreach ($rollRequests as $rollRequest) {
            $requestId = (int)($rollRequest['id'] ?? 0);
            if ($requestId <= 0) {
                continue;
            }
            $deliveries = $service->listMaterialDeliveriesByRequest($requestId);
            $deliveredRolls = [];
            foreach ($deliveries as $delivery) {
                $deliveredRollId = (int)($delivery['roll_id'] ?? 0);
                if ($deliveredRollId <= 0 || isset($deliveredRolls[$deliveredRollId])) {
                    continue;
                }
                $deliveredRoll = $service->getRoll($deliveredRollId);
                if (!is_array($deliveredRoll)) {
                    continue;
                }
                $deliveredRolls[$deliveredRollId] = $deliveredRoll;
                $deliveredRollRequestMap[$deliveredRollId] = $requestId;
            }
            $deliveriesByRequest[$requestId] = array_values($deliveredRolls);
        }
    }
    $rollHistory = $service instanceof ReceptionService ? $service->listWorkOrderRollHistory((int)$ot['id']) : [];
    $activeAttachEventIds = [];
    $openAttachEventIdByRoll = [];
    foreach (array_reverse($rollHistory) as $historyRow) {
        $eventType = strtoupper(trim((string)($historyRow['type'] ?? '')));
        $historyEventId = (int)($historyRow['id'] ?? 0);
        $historyRollId = (int)($historyRow['roll_id'] ?? 0);
        if ($historyEventId <= 0 || $historyRollId <= 0) {
            continue;
        }
        if ($eventType === 'WORK_ORDER_ROLL_ATTACHED') {
            // Solo la ultima entrada vigente por bobina debe quedar operable en la grilla.
            $openAttachEventIdByRoll[$historyRollId] = $historyEventId;
            continue;
        }
        if ($eventType === 'WORK_ORDER_ROLL_RELEASED') {
            unset($openAttachEventIdByRoll[$historyRollId]);
        }
    }
    foreach ($openAttachEventIdByRoll as $openAttachEventId) {
        $activeAttachEventIds[(int)$openAttachEventId] = true;
    }
    $activeRollRequestMap = [];
    $activeRollRequestByRollId = [];
    foreach ($rollHistory as $historyRow) {
        $eventType = strtoupper(trim((string)($historyRow['type'] ?? '')));
        $historyEventId = (int)($historyRow['id'] ?? 0);
        if ($eventType !== 'WORK_ORDER_ROLL_ATTACHED' || !isset($activeAttachEventIds[$historyEventId])) {
            continue;
        }
        $historyRollId = (int)($historyRow['roll_id'] ?? 0);
        if ($historyRollId <= 0 || !isset($activeRollsById[$historyRollId])) {
            continue;
        }
        $payload = isset($historyRow['payload_data']) && is_array($historyRow['payload_data']) ? $historyRow['payload_data'] : [];
        $historyRequestId = (int)($payload['request_id'] ?? ($deliveredRollRequestMap[$historyRollId] ?? 0));
        if ($historyRequestId <= 0) {
            continue;
        }
        if (!isset($activeRollRequestMap[$historyRequestId])) {
            $activeRollRequestMap[$historyRequestId] = [];
        }
        $activeRollRequestMap[$historyRequestId][$historyRollId] = $activeRollsById[$historyRollId];
        $activeRollRequestByRollId[$historyRollId] = $historyRequestId;
    }
    $movementRows = [];
    foreach ($rollHistory as $historyRow) {
        $eventType = strtoupper(trim((string)($historyRow['type'] ?? '')));
        $payload = isset($historyRow['payload_data']) && is_array($historyRow['payload_data']) ? $historyRow['payload_data'] : [];
        $isAttach = $eventType === 'WORK_ORDER_ROLL_ATTACHED';
        $isRelease = $eventType === 'WORK_ORDER_ROLL_RELEASED';
        if (!$isAttach && !$isRelease) {
            continue;
        }
        $historyRollId = (int)($historyRow['roll_id'] ?? 0);
        $historyEventId = (int)($historyRow['id'] ?? 0);
        $requestIdForRow = (int)($payload['request_id'] ?? ($deliveredRollRequestMap[$historyRollId] ?? 0));
        $quantityValue = $isAttach
            ? (float)($payload['process_weight_kg'] ?? 0)
            : (float)($payload['final_weight_kg'] ?? 0);
        $movementRows[] = [
            'event_id' => $historyEventId,
            'transaction' => ($isAttach ? 'IA' : 'SA') . str_pad((string)((int)($historyRow['id'] ?? 0)), 7, '0', STR_PAD_LEFT),
            'date_time' => formatLabelDateTime((string)($historyRow['created_at'] ?? '')),
            'material' => trim((string)($historyRow['sku_description'] ?? '')) !== '' ? (string)$historyRow['sku_description'] : '-',
            'code' => trim((string)($historyRow['roll_code'] ?? '')) !== '' ? (string)$historyRow['roll_code'] : '-',
            'quantity' => rtrim(rtrim(number_format($quantityValue, 3, '.', ''), '0'), '.') . ' Kilogramos',
            'type' => $isAttach ? 'Entrada' : 'Salida',
            'request_id' => $requestIdForRow,
            'roll_id' => $historyRollId,
            'is_active' => $isAttach && isset($activeAttachEventIds[$historyEventId]),
        ];
    }

    $setupActionButtons = '<div class="legacy-setup-actions">'
        . '<a class="legacy-setup-action request" href="/work-orders/' . (int)$ot['id'] . '/request-materials" target="_blank" rel="noopener">Solicitar materiales</a>'
        . '<a class="legacy-setup-action materials active" href="/work-orders/' . (int)$ot['id'] . '/materials">Utilizar materiales</a>'
        . '<a class="legacy-setup-action maintenance" href="#">Registrar mantención</a>'
        . '<a class="legacy-setup-action pause" href="#">Pausa</a>'
        . '<a class="legacy-setup-action consumption" href="#">Consumo</a>'
        . '</div>';

    $body = '<div class="legacy-screen-title">Utilizar materiales</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Utilizar materiales</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/setup">Volver a alistamiento</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Informacion Cliente</th></tr></thead><tbody>';
    foreach ($legacyClientRows as $legacyRow) {
        $body .= '<tr><td class="legacy-label-cell">' . h((string)$legacyRow[0]) . '</td><td class="legacy-value-cell">' . $legacyRow[1] . '</td><td class="legacy-label-cell">' . h((string)$legacyRow[2]) . '</td><td class="legacy-value-cell">' . $legacyRow[3] . '</td></tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Información de Fabricación</th></tr></thead><tbody>';
    foreach ($legacyFabricationRows as $legacyRow) {
        if (trim((string)$legacyRow[0]) === '' && trim((string)$legacyRow[1]) === '' && trim((string)$legacyRow[2]) === '' && trim((string)$legacyRow[3]) === '') {
            $body .= '<tr><td class="legacy-value-cell" colspan="4" style="background:#f3f4f6;padding:8px"></td></tr>';
            continue;
        }
        $body .= '<tr><td class="legacy-label-cell">' . h((string)$legacyRow[0]) . '</td><td class="legacy-value-cell">' . $legacyRow[1] . '</td><td class="legacy-label-cell">' . h((string)$legacyRow[2]) . '</td><td class="legacy-value-cell">' . $legacyRow[3] . '</td></tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= renderWorkOrderAniloxConfigCard((int)$ot['id'], $aniloxSlots, $aniloxCatalog, $suggestedAniloxColors);
    $body .= $setupActionButtons;
    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="7" style="background:#d94841;border-color:#d94841">Egresos/Ingresos contabilizados</th></tr></thead><tbody>';
    $body .= '<tr><td class="legacy-value-cell" colspan="7" style="padding:0"><div class="table-wrap"><table class="table-compact" style="width:100%"><thead><tr><th>Transacción</th><th>Fecha/Hora</th><th>Material</th><th>Código</th><th>Cantidad</th><th>Tipo</th><th>Opciones</th></tr></thead><tbody>';
    if ($movementRows === []) {
        $body .= '<tr><td colspan="7" class="muted">Todavía no hay movimientos realizados en esta orden de trabajo.</td></tr>';
    } else {
        foreach ($movementRows as $movementRow) {
            $rowEventId = (int)($movementRow['event_id'] ?? 0);
            $rowRequestId = (int)($movementRow['request_id'] ?? 0);
            $rowRollId = (int)($movementRow['roll_id'] ?? 0);
            $isActiveMovement = (bool)($movementRow['is_active'] ?? false);
            $rowReleaseModalId = 'material-row-release-' . $rowEventId;
            $rowOpenReleaseOnclick = ' onclick="var modal=document.getElementById(\'' . $rowReleaseModalId . '\'); if(modal){modal.style.display=\'block\';} return false;"';
            $rowCloseReleaseOnclick = ' onclick="var modal=document.getElementById(\'' . $rowReleaseModalId . '\'); if(modal){modal.style.display=\'none\';} return false;"';
            $rowActiveRoll = $activeRollsById[$rowRollId] ?? null;
            $rowReleaseWeight = is_array($rowActiveRoll) && isset($rowActiveRoll['weight_kg'])
                ? rtrim(rtrim(number_format((float)$rowActiveRoll['weight_kg'], 2, '.', ''), '0'), '.')
                : '';
            $rowOptions = $isActiveMovement
                ? '<div style="display:flex;gap:6px;align-items:center">'
                    . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/materials/remove" onsubmit="return confirm(\'¿Eliminar esta entrada por error?\')" style="margin:0">'
                    . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                    . '<input type="hidden" name="request_id" value="' . $rowRequestId . '">'
                    . '<input type="hidden" name="roll_id" value="' . $rowRollId . '">'
                    . '<button class="btn" type="submit" style="background:#d94841;border-color:#d94841;padding:6px 14px"' . ($rowRequestId > 0 && $rowRollId > 0 ? '' : ' disabled') . '>Eliminar</button>'
                    . '</form>'
                    . '<button class="btn secondary" type="button"' . $rowOpenReleaseOnclick . ($rowRollId > 0 ? '' : ' disabled') . '>Salida</button>'
                    . '<div class="legacy-inline-modal" id="' . $rowReleaseModalId . '" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto">'
                    . '<div style="max-width:520px;margin:40px auto;background:#fff;border-radius:8px;box-shadow:0 12px 30px rgba(15,23,42,.22);padding:18px">'
                    . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Salida de bobina</div><button type="button" class="btn secondary"' . $rowCloseReleaseOnclick . '>Cerrar</button></div>'
                    . '<div class="muted" style="margin-bottom:12px">Registra los kilos que salieron de la máquina y la merma generada.</div>'
                    . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/materials/release" data-release-form>'
                    . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                    . '<input type="hidden" name="request_id" value="' . $rowRequestId . '">'
                    . '<label>Bobina activa</label>'
                    . '<input type="hidden" name="roll_id" value="' . $rowRollId . '">'
                    . '<input type="text" value="' . h((string)($movementRow['code'] ?? '-')) . '" readonly>'
                    . '<label style="margin-top:10px">Kilos que salieron de la máquina</label>'
                    . '<input name="final_weight_kg" type="number" step="0.001" min="0" value="' . h($rowReleaseWeight) . '" required placeholder="Ej: 8.600">'
                    . '<input name="waste_kg" type="hidden" value="' . h($releaseWasteTotalState) . '" data-release-waste-total>'
                    . '<div style="font-weight:700;margin-top:12px;margin-bottom:8px">Mermas de salida</div>'
                    . '<div style="display:grid;grid-template-columns:120px minmax(0,1fr) 86px minmax(0,1fr);gap:8px;align-items:center;margin-bottom:8px"><div style="font-weight:700">Ajuste / Aprobación</div><div><input type="number" step="0.001" min="0" name="release_waste_detail_weight[approval]" value="" data-release-waste-input placeholder="Kg" style="width:100%;box-sizing:border-box"></div><div style="font-size:12px;color:#555">Comentario</div><div><input type="text" name="release_waste_detail_comment[approval]" value="" style="width:100%;box-sizing:border-box"></div></div>'
                    . '<div style="display:grid;grid-template-columns:120px minmax(0,1fr) 86px minmax(0,1fr);gap:8px;align-items:center;margin-bottom:8px"><div style="font-weight:700">Impresión</div><div><input type="number" step="0.001" min="0" name="release_waste_detail_weight[printing]" value="" data-release-waste-input placeholder="Kg" style="width:100%;box-sizing:border-box"></div><div style="font-size:12px;color:#555">Comentario</div><div><input type="text" name="release_waste_detail_comment[printing]" value="" style="width:100%;box-sizing:border-box"></div></div>'
                    . '<div style="display:grid;grid-template-columns:120px minmax(0,1fr) 86px minmax(0,1fr);gap:8px;align-items:center;margin-bottom:8px"><div style="font-weight:700">Merma Proceso</div><div><input type="number" step="0.001" min="0" name="release_waste_detail_weight[process]" value="" data-release-waste-input placeholder="Kg" style="width:100%;box-sizing:border-box"></div><div style="font-size:12px;color:#555">Comentario</div><div><input type="text" name="release_waste_detail_comment[process]" value="" style="width:100%;box-sizing:border-box"></div></div>'
                    . '<div style="display:grid;grid-template-columns:120px minmax(0,1fr) 86px minmax(0,1fr);gap:8px;align-items:center;margin-bottom:8px"><div style="font-weight:700">Otros</div><div><input type="number" step="0.001" min="0" name="release_waste_detail_weight[other]" value="" data-release-waste-input placeholder="Kg" style="width:100%;box-sizing:border-box"></div><div style="font-size:12px;color:#555">Comentario</div><div><input type="text" name="release_waste_detail_comment[other]" value="" style="width:100%;box-sizing:border-box"></div></div>'
                    . '<label style="margin-top:10px">Merma total</label>'
                    . '<input type="text" value="' . h($releaseWasteTotalState) . ' Kg" readonly data-release-waste-display>'
                    . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px"><button class="btn secondary" type="button"' . $rowCloseReleaseOnclick . '>Cancelar</button><button class="btn" type="submit">Guardar salida</button></div>'
                    . '</form></div></div>'
                    . '</div>'
                : '<div class="muted">Realizado</div>';
            $body .= '<tr>'
                . '<td>' . h($movementRow['transaction']) . '</td>'
                . '<td>' . h($movementRow['date_time']) . '</td>'
                . '<td>' . h($movementRow['material']) . '</td>'
                . '<td>' . h($movementRow['code']) . '</td>'
                . '<td>' . h($movementRow['quantity']) . '</td>'
                . '<td>' . h($movementRow['type']) . '</td>'
                . '<td>' . $rowOptions . '</td>'
                . '</tr>';
        }
    }
    $body .= '</tbody></table></div></td></tr></tbody></table></div>';
    if ($currentRoll !== null) {
        $releaseWeight = isset($currentRoll['weight_kg'])
            ? rtrim(rtrim(number_format((float)$currentRoll['weight_kg'], 2, '.', ''), '0'), '.')
            : '';
        $currentRollId = (int)($currentRoll['id'] ?? 0);
        $currentRollRequestId = (int)($activeRollRequestByRollId[$currentRollId] ?? 0);
        $releaseMaterialLabel = trim((string)($currentRoll['sku_description'] ?? '')) !== '' ? (string)$currentRoll['sku_description'] : '-';
        $releaseGsmValue = isset($currentRoll['grams']) && (string)($currentRoll['grams'] ?? '') !== ''
            ? rtrim(rtrim(number_format((float)$currentRoll['grams'], 2, '.', ''), '0'), '.')
            : '-';
        $releaseWidthValue = isset($currentRoll['width_mm']) && (string)($currentRoll['width_mm'] ?? '') !== ''
            ? rtrim(rtrim(number_format(((float)$currentRoll['width_mm']) / 10, 2, '.', ''), '0'), '.')
            : '-';
        $releaseBaseMaterial = '-';
        if ($releaseMaterialLabel !== '-' && $releaseMaterialLabel !== '') {
            $releaseParts = explode('/', $releaseMaterialLabel);
            $releaseBaseMaterial = trim((string)($releaseParts[0] ?? ''));
            if ($releaseBaseMaterial === '') {
                $releaseBaseMaterial = $releaseMaterialLabel;
            }
        }
        $releaseClientLabel = trim((string)($ot['erp_customer_name'] ?? '')) !== '' ? (string)$ot['erp_customer_name'] : '-';
        $releaseRequestLabel = trim((string)($ot['erp_req_id'] ?? '')) !== '' ? (string)$ot['erp_req_id'] : (string)$ot['ot_code'];
        $releaseDesignLabel = trim((string)($ot['erp_plan_desc'] ?? '')) !== '' ? (string)$ot['erp_plan_desc'] : (string)$ot['sku_final'];
        $releaseColorCountLabel = $configuredColorCount > 0 ? (string)$configuredColorCount : '-';
        $body .= '<div class="legacy-inline-modal" id="material-modal-active-release" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto">'
            . '<div style="max-width:780px;margin:10px auto;background:#f6f6f3;border-radius:10px;box-shadow:0 12px 30px rgba(15,23,42,.22);padding:14px">'
            . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Salida de bobina</div><button type="button" class="btn secondary" data-material-modal-close="active-release">Cerrar</button></div>'
            . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/materials/release" data-release-form>'
            . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
            . '<input type="hidden" name="request_id" value="' . $currentRollRequestId . '">'
            . '<input type="hidden" name="roll_id" value="' . $currentRollId . '">'
            . '<div style="background:#fff;border:1px solid #d4d4cf;border-radius:4px;overflow:hidden;margin-bottom:12px">'
            . '<div style="background:#18a8a3;color:#fff;font-weight:800;padding:8px 10px">Información del producto : Tela</div>'
            . '<div style="padding:10px"><table class="legacy-sheet-table" style="margin:0"><tbody>'
            . '<tr><td class="legacy-label-cell">Codigo</td><td class="legacy-value-cell">' . h((string)($currentRoll['sku_code'] ?? '-')) . '</td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
            . '<tr><td class="legacy-label-cell">Descripción</td><td class="legacy-value-cell" colspan="3">' . h($releaseMaterialLabel) . '</td></tr>'
            . '<tr><td class="legacy-label-cell">Gramaje</td><td class="legacy-value-cell">' . h($releaseGsmValue) . '</td><td class="legacy-label-cell">Ancho Rollo</td><td class="legacy-value-cell">' . h($releaseWidthValue) . '</td></tr>'
            . '<tr><td class="legacy-label-cell">Color Tela</td><td class="legacy-value-cell">' . h(trim((string)($currentRoll['color'] ?? '')) !== '' ? (string)$currentRoll['color'] : '-') . '</td><td class="legacy-label-cell">Materialidad</td><td class="legacy-value-cell">' . h($releaseBaseMaterial) . '</td></tr>'
            . '</tbody></table></div></div>'
            . '<div style="background:#fff;border:1px solid #d4d4cf;border-radius:4px;overflow:hidden;margin-bottom:12px">'
            . '<div style="background:#18a8a3;color:#fff;font-weight:800;padding:8px 10px">Información de Confirmacion de Compras</div>'
            . '<div style="padding:10px"><table class="legacy-sheet-table" style="margin:0"><tbody>'
            . '<tr><td class="legacy-label-cell">Numero</td><td class="legacy-value-cell">' . h($releaseRequestLabel) . '</td><td class="legacy-label-cell">Cliente</td><td class="legacy-value-cell">' . h($releaseClientLabel) . '</td></tr>'
            . '<tr><td class="legacy-label-cell">Diseño</td><td class="legacy-value-cell">' . h($releaseDesignLabel) . '</td><td class="legacy-label-cell">Impreso en</td><td class="legacy-value-cell">FLEXOGRAFIA</td></tr>'
            . '<tr><td class="legacy-label-cell">Color Frente</td><td class="legacy-value-cell">' . h($releaseColorCountLabel) . '</td><td class="legacy-label-cell">Color Dorso</td><td class="legacy-value-cell">' . h($releaseColorCountLabel) . '</td></tr>'
            . '</tbody></table></div></div>'
            . '<div style="background:#fff;border:1px solid #d4d4cf;border-radius:4px;overflow:hidden">'
            . '<div style="background:#18a8a3;color:#fff;font-weight:800;padding:8px 10px">Opciones de salida de stock</div>'
            . '<div style="padding:10px">'
            . '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:14px;margin-bottom:12px">'
            . '<span style="font-weight:700">Opción de Ingreso</span>'
            . '<label style="display:flex;align-items:center;gap:4px"><input type="radio" disabled>Por unidad</label>'
            . '<label style="display:flex;align-items:center;gap:4px"><input type="radio" disabled>Por metros</label>'
            . '<label style="display:flex;align-items:center;gap:4px"><input type="radio" checked>Por peso</label>'
            . '</div>'
            . '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px">'
            . '<label style="font-weight:700;min-width:180px;margin:0">Kilogramos</label>'
            . '<input id="active-release-final-weight" name="final_weight_kg" type="number" step="0.001" min="0" value="' . h($releaseFinalWeightState) . '" required style="max-width:180px">'
            . '<span>kg</span>'
            . '</div>'
            . '<input name="waste_kg" type="hidden" value="' . h($releaseWasteTotalState) . '" data-release-waste-total>'
            . '<div style="margin-top:10px">'
            . '<div style="font-weight:700;margin-bottom:8px">Mermas de salida</div>';
        foreach ($releaseWasteRows as $releaseWasteRow) {
            $body .= '<div style="display:grid;grid-template-columns:140px minmax(160px,1fr) 78px 92px 92px minmax(180px,1fr);gap:8px;align-items:center;margin-bottom:8px">'
                . '<div style="font-weight:700">Merma</div>'
                . '<div>' . h((string)$releaseWasteRow['label']) . '</div>'
                . '<div><select disabled style="width:100%"><option>Kilos</option></select></div>'
                . '<div><input type="number" step="0.001" min="0" name="release_waste_detail_weight[' . h((string)$releaseWasteRow['key']) . ']" value="' . h((string)($releaseWasteWeightState[$releaseWasteRow['key']] ?? '')) . '" data-release-waste-input style="width:100%"></div>'
                . '<div style="font-size:12px;color:#555">Comentarios</div>'
                . '<div><input type="text" name="release_waste_detail_comment[' . h((string)$releaseWasteRow['key']) . ']" value="' . h((string)($releaseWasteCommentState[$releaseWasteRow['key']] ?? '')) . '" style="width:100%"></div>'
                . '</div>';
        }
        $body .= '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:6px">'
            . '<label style="font-weight:700;min-width:180px;margin:0">Merma total</label>'
            . '<input type="text" value="' . h($releaseWasteTotalState) . ' Kg" readonly data-release-waste-display style="max-width:180px;background:#f3f4f6">'
            . '<span>calculada automaticamente</span>'
            . '</div>'
            . '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:16px"><button class="btn secondary" type="button" data-material-modal-close="active-release">Volver</button><button class="btn" type="submit">Guardar</button></div>'
            . '</div></div>'
            . '</form></div></div>';
    }
    $body .= '<div class="legacy-sheet-card"><table class="legacy-sheet-table"><thead><tr><th colspan="9">Bobinas solicitadas</th></tr></thead><tbody>';
    $body .= '<tr><td class="legacy-label-cell">Resumen</td><td class="legacy-value-cell" colspan="8">Muestra las bobinas pendientes por ingresar a la máquina.</td></tr>';
    $body .= '<tr><td class="legacy-label-cell">Ingreso</td><td class="legacy-value-cell" colspan="8"><div class="table-wrap"><table class="table-compact"><thead><tr><th>Descripción</th><th>SKU</th><th>Código bobina</th><th>Ancho (cm)</th><th>Longitud (m)</th><th>GSM</th><th>Color</th><th>Kg</th><th>Entrada</th></tr></thead><tbody>';

    foreach ($rollRequests as $request) {
        $groupData = parseMaterialRequestGroupKey((string)($request['requested_group_key'] ?? ''));
        $requestId = (int)($request['id'] ?? 0);
        $matchingRollsById = [];
        foreach (($deliveriesByRequest[$requestId] ?? []) as $deliveredRequestRoll) {
            $deliveredRequestRollId = (int)($deliveredRequestRoll['id'] ?? 0);
            if ($deliveredRequestRollId <= 0 || isset($matchingRollsById[$deliveredRequestRollId])) {
                continue;
            }
            $matchingRollsById[$deliveredRequestRollId] = $deliveredRequestRoll;
        }
        $deliveredRollId = (int)($request['delivered_roll_id'] ?? 0);
        if ($deliveredRollId > 0 && !isset($matchingRollsById[$deliveredRollId]) && $service instanceof ReceptionService) {
            $deliveredRoll = $service->getRoll($deliveredRollId);
            if (is_array($deliveredRoll)) {
                $matchingRollsById[$deliveredRollId] = $deliveredRoll;
            }
        }
        foreach (($activeRollRequestMap[$requestId] ?? []) as $activeRequestRollId => $activeRequestRoll) {
            if ($activeRequestRollId <= 0 || isset($matchingRollsById[$activeRequestRollId])) {
                continue;
            }
            $matchingRollsById[$activeRequestRollId] = $activeRequestRoll;
        }
        foreach (($availableRollsByGroup[(string)($request['requested_group_key'] ?? '')] ?? []) as $availableGroupRoll) {
            $availableGroupRollId = (int)($availableGroupRoll['id'] ?? 0);
            if ($availableGroupRollId <= 0 || isset($matchingRollsById[$availableGroupRollId])) {
                continue;
            }
            $matchingRollsById[$availableGroupRollId] = $availableGroupRoll;
        }
        $matchingRolls = array_values($matchingRollsById);
        $activeRequestRolls = array_values(array_filter(
            $matchingRolls,
            static fn(array $roll): bool => isset($activeRollIds[(int)($roll['id'] ?? 0)])
        ));
        $referenceRoll = $matchingRolls[0] ?? null;
        $requestedQty = (float)($request['requested_qty'] ?? 0);
        $deliveredQty = (float)($request['delivered_qty'] ?? 0);
        $pendingQty = max(0.0, $requestedQty - $deliveredQty);
        $requestGroupKey = (string)($request['requested_group_key'] ?? '');
        $rowHasActiveRoll = $activeRequestRolls !== [];
        $widthCmLabel = ($groupData['width_mm'] !== '' && is_numeric($groupData['width_mm']))
            ? rtrim(rtrim(number_format(((float)$groupData['width_mm']) / 10, 2, '.', ''), '0'), '.')
            : '-';
        $metersLabel = $groupData['meters'] !== '' ? $groupData['meters'] : '-';
        $gramsLabel = $groupData['grams'] !== '' ? $groupData['grams'] : '-';
        $weightLabel = $referenceRoll !== null && (string)($referenceRoll['weight_kg'] ?? '') !== ''
            ? rtrim(rtrim(number_format((float)$referenceRoll['weight_kg'], 2, '.', ''), '0'), '.')
            : '-';
        $materialLabel = trim((string)($groupData['sku_description'] !== '' ? $groupData['sku_description'] : (string)($request['requested_item'] ?? '-')));
        $materialBaseLabel = '-';
        if ($materialLabel !== '' && $materialLabel !== '-') {
            $materialParts = explode('/', $materialLabel);
            $materialBaseLabel = trim((string)($materialParts[0] ?? ''));
            if ($materialBaseLabel === '') {
                $materialBaseLabel = $materialLabel;
            }
        }
        $selectedRollCode = $referenceRoll !== null && trim((string)($referenceRoll['roll_code'] ?? '')) !== ''
            ? (string)$referenceRoll['roll_code']
            : '-';
        $selectedRollWeight = $referenceRoll !== null && isset($referenceRoll['weight_kg'])
            ? rtrim(rtrim(number_format((float)$referenceRoll['weight_kg'], 3, '.', ''), '0'), '.')
            : '';
        $entryHtml = '<div class="muted">Sin disponibilidad en bodega.</div>';
        if (!workOrderCanReceiveMaterials((string)($ot['status'] ?? ''))) {
            $entryHtml = '<div class="muted">La OT ya no admite ingresos.</div>';
        } else {
            $canOpenAttachModal = in_array((string)($request['status'] ?? ''), ['PENDING', 'ACCEPTED', 'PARTIAL', 'DELIVERED'], true)
                && $matchingRolls !== [];
            $canAttach = $canOpenAttachModal;
            $canRelease = $rowHasActiveRoll;
            $attachDisabled = $canOpenAttachModal ? '' : ' disabled';
            $releaseDisabled = $canRelease ? '' : ' disabled';
            $attachButtonClass = $canOpenAttachModal ? 'btn' : 'btn secondary';
            $releaseButtonClass = $canRelease ? 'btn secondary' : 'btn secondary';
            $openAttachOnclick = ' onclick="var modal=document.getElementById(\'material-modal-attach-' . $requestId . '\'); if(modal){modal.style.display=\'block\';} return false;"';
            $openReleaseOnclick = ' onclick="var modal=document.getElementById(\'material-modal-release-' . $requestId . '\'); if(modal){modal.style.display=\'block\';} return false;"';
            $closeAttachOnclick = ' onclick="var modal=document.getElementById(\'material-modal-attach-' . $requestId . '\'); if(modal){modal.style.display=\'none\';} return false;"';
            $closeReleaseOnclick = ' onclick="var modal=document.getElementById(\'material-modal-release-' . $requestId . '\'); if(modal){modal.style.display=\'none\';} return false;"';
            $entryHtml = '<div style="display:flex;gap:8px;flex-wrap:wrap">'
                . '<button class="' . $attachButtonClass . '" type="button" data-material-modal-open="attach-' . $requestId . '"' . $openAttachOnclick . $attachDisabled . '>Entrada</button>'
                . '<button class="' . $releaseButtonClass . '" type="button" data-material-modal-open="release-' . $requestId . '"' . $openReleaseOnclick . $releaseDisabled . '>Salida</button>'
                . '</div>';
            if ((string)($request['status'] ?? '') === 'PENDING') {
                $entryHtml .= '<div class="muted" style="margin-top:6px">Pendiente de bodega.</div>';
            } elseif ((string)($request['status'] ?? '') === 'DELIVERED' && $matchingRolls !== []) {
                $entryHtml .= '<div class="muted" style="margin-top:6px">Entregadas por bodega: ' . h(implode(', ', array_map(static fn(array $roll): string => (string)($roll['roll_code'] ?? '-'), $matchingRolls))) . '</div>';
            } elseif ($rowHasActiveRoll) {
                $entryHtml .= '<div class="muted" style="margin-top:6px">Activas: ' . h(implode(', ', array_map(static fn(array $roll): string => (string)($roll['roll_code'] ?? '-'), $activeRequestRolls))) . '</div>';
            }

            $attachFormDisabled = $canAttach ? '' : ' disabled';
            $entryHtml .= '<div class="legacy-inline-modal" id="material-modal-attach-' . $requestId . '" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto">'
                . '<div style="max-width:780px;margin:10px auto;background:#f6f6f3;border-radius:10px;box-shadow:0 12px 30px rgba(15,23,42,.22);padding:14px">'
                . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Entrada de bobina</div><button type="button" class="btn secondary" data-material-modal-close="attach-' . $requestId . '"' . $closeAttachOnclick . '>Cerrar</button></div>'
                . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/materials/attach">'
                . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                . '<input type="hidden" name="request_id" value="' . $requestId . '">'
                . '<div style="background:#fff;border:1px solid #d4d4cf;border-radius:4px;overflow:hidden;margin-bottom:12px">'
                . '<div style="background:#18a8a3;color:#fff;font-weight:800;padding:8px 10px">Informacion del producto</div>'
                . '<div style="padding:10px">'
                . '<label style="font-weight:700;margin-bottom:6px">Bobina</label>'
                . '<select name="roll_id" required data-attach-roll-select="' . $requestId . '" style="margin-bottom:12px"' . $attachFormDisabled . '><option value="">Seleccionar bobina</option>';
            foreach ($matchingRolls as $matchingRoll) {
                $matchingRollCode = trim((string)($matchingRoll['roll_code'] ?? '')) !== '' ? (string)$matchingRoll['roll_code'] : '-';
                $matchingRollWeight = rtrim(rtrim(number_format((float)($matchingRoll['weight_kg'] ?? 0), 3, '.', ''), '0'), '.');
                $entryHtml .= '<option value="' . (int)$matchingRoll['id'] . '"'
                    . ' data-roll-code="' . h($matchingRollCode) . '"'
                    . ' data-roll-weight="' . h($matchingRollWeight !== '' ? $matchingRollWeight : '0') . '"'
                    . ' data-roll-warehouse="' . h((string)($matchingRoll['warehouse_name'] ?? '-')) . '"'
                    . '>' . h($matchingRollCode) . ' · ' . h((string)($matchingRoll['warehouse_name'] ?? '-')) . ' · ' . h(rtrim(rtrim(number_format((float)($matchingRoll['weight_kg'] ?? 0), 2, '.', ''), '0'), '.')) . ' Kg</option>';
            }
            $entryHtml .= '</select>'
                . '<table class="legacy-sheet-table" style="margin:0"><tbody>'
                . '<tr><td class="legacy-label-cell">Categoria</td><td class="legacy-value-cell">Tela</td></tr>'
                . '<tr><td class="legacy-label-cell">Material</td><td class="legacy-value-cell">' . h($materialLabel !== '' ? $materialLabel : '-') . '</td></tr>'
                . '<tr><td class="legacy-label-cell">SKU</td><td class="legacy-value-cell">' . h($groupData['sku_code'] !== '' ? $groupData['sku_code'] : '-') . '</td></tr>'
                . '<tr><td class="legacy-label-cell">Codigo bobina</td><td class="legacy-value-cell"><span id="attach-roll-code-' . $requestId . '">' . h($selectedRollCode) . '</span></td></tr>'
                . '<tr><td class="legacy-label-cell">Ancho</td><td class="legacy-value-cell">' . h($widthCmLabel) . ' cm</td></tr>'
                . '<tr><td class="legacy-label-cell">Longitud</td><td class="legacy-value-cell">' . h($metersLabel) . ' m</td></tr>'
                . '<tr><td class="legacy-label-cell">GSM</td><td class="legacy-value-cell">' . h($gramsLabel) . ' gr</td></tr>'
                . '<tr><td class="legacy-label-cell">Kilogramos</td><td class="legacy-value-cell"><span id="attach-roll-kg-' . $requestId . '">' . h($selectedRollWeight !== '' ? $selectedRollWeight : '-') . '</span> kg</td></tr>'
                . '<tr><td class="legacy-label-cell">Ancho de rollo</td><td class="legacy-value-cell">' . h($groupData['width_mm'] !== '' ? $groupData['width_mm'] : '-') . '</td></tr>'
                . '<tr><td class="legacy-label-cell">Gramaje tela</td><td class="legacy-value-cell">' . h($gramsLabel) . '</td></tr>'
                . '<tr><td class="legacy-label-cell">Longitud rollo</td><td class="legacy-value-cell">' . h($metersLabel) . '</td></tr>'
                . '<tr><td class="legacy-label-cell">Tela: Color</td><td class="legacy-value-cell">' . h($groupData['color'] !== '' ? $groupData['color'] : '-') . '</td></tr>'
                . '<tr><td class="legacy-label-cell">Tela: Material</td><td class="legacy-value-cell">' . h($materialBaseLabel) . '</td></tr>'
                . '<tr><td class="legacy-label-cell">Stock</td><td class="legacy-value-cell"><span id="attach-roll-stock-' . $requestId . '">' . h($selectedRollWeight !== '' ? $selectedRollWeight : '-') . '</span> kg</td></tr>'
                . '</tbody></table>'
                . '</div></div>'
                . '<div style="background:#fff;border:1px solid #d4d4cf;border-radius:4px;overflow:hidden">'
                . '<div style="background:#18a8a3;color:#fff;font-weight:800;padding:8px 10px">Opciones de retiro de stock</div>'
                . '<div style="padding:10px">'
                . '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:14px;margin-bottom:12px">'
                . '<span style="font-weight:700">Opcion de retiro</span>'
                . '<label style="display:flex;align-items:center;gap:4px"><input type="radio" disabled>Por unidad</label>'
                . '<label style="display:flex;align-items:center;gap:4px"><input type="radio" disabled>Por metros</label>'
                . '<label style="display:flex;align-items:center;gap:4px"><input type="radio" checked>Por peso</label>'
                . '</div>'
                . '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">'
                . '<label style="font-weight:700;min-width:120px;margin:0">Kilogramos</label>'
                . '<input name="process_weight_kg" type="number" step="0.001" min="0.001" required placeholder="Ej: 118.200" style="max-width:180px"' . $attachFormDisabled . '>'
                . '<span>kg</span>'
                . '</div>'
                . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-material-modal-close="attach-' . $requestId . '"' . $closeAttachOnclick . '>Cancelar</button><button class="btn" type="submit"' . $attachFormDisabled . '>Guardar entrada</button></div>'
                . '</div></div>'
                . '</form></div></div>';

            $releaseRoll = $activeRequestRolls[0] ?? null;
            $releaseRollCode = $releaseRoll !== null ? (string)($releaseRoll['roll_code'] ?? '-') : '-';
            $releaseWeight = $releaseRoll !== null && isset($releaseRoll['weight_kg'])
                ? rtrim(rtrim(number_format((float)$releaseRoll['weight_kg'], 2, '.', ''), '0'), '.')
                : '';
            $entryHtml .= '<div class="legacy-inline-modal" id="material-modal-release-' . $requestId . '" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto">'
                . '<div style="max-width:520px;margin:40px auto;background:#fff;border-radius:8px;box-shadow:0 12px 30px rgba(15,23,42,.22);padding:18px">'
                . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Salida de bobina</div><button type="button" class="btn secondary" data-material-modal-close="release-' . $requestId . '"' . $closeReleaseOnclick . '>Cerrar</button></div>'
                . '<div class="muted" style="margin-bottom:12px">Registra los kilos que salieron de la máquina y la merma generada.</div>'
                . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/materials/release" data-release-form>'
                . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                . '<input type="hidden" name="request_id" value="' . $requestId . '">'
                . '<label>Bobina activa</label>';
            if (count($activeRequestRolls) > 1) {
                $entryHtml .= '<select name="roll_id" required>';
                foreach ($activeRequestRolls as $activeRequestRoll) {
                    $entryHtml .= '<option value="' . (int)($activeRequestRoll['id'] ?? 0) . '">' . h((string)($activeRequestRoll['roll_code'] ?? '-')) . ' · ' . h(rtrim(rtrim(number_format((float)($activeRequestRoll['weight_kg'] ?? 0), 2, '.', ''), '0'), '.')) . ' Kg</option>';
                }
                $entryHtml .= '</select>';
            } else {
                $entryHtml .= '<input type="hidden" name="roll_id" value="' . (int)($releaseRoll['id'] ?? 0) . '">'
                    . '<input type="text" value="' . h($releaseRollCode) . '" readonly>';
            }
            $entryHtml .= '<label style="margin-top:10px">Kilos que salieron de la máquina</label>'
                . '<input name="final_weight_kg" type="number" step="0.001" min="0" value="' . h($releaseWeight) . '" required placeholder="Ej: 8.600">'
                . '<input name="waste_kg" type="hidden" value="' . h($releaseWasteTotalState) . '" data-release-waste-total>'
                . '<div style="font-weight:700;margin-top:12px;margin-bottom:8px">Mermas de salida</div>';
            foreach ($releaseWasteRows as $releaseWasteRow) {
                $entryHtml .= '<div style="display:grid;grid-template-columns:120px minmax(0,1fr) 86px minmax(0,1fr);gap:8px;align-items:center;margin-bottom:8px">'
                    . '<div style="font-weight:700">' . h((string)$releaseWasteRow['label']) . '</div>'
                    . '<div><input type="number" step="0.001" min="0" name="release_waste_detail_weight[' . h((string)$releaseWasteRow['key']) . ']" value="' . h((string)($releaseWasteWeightState[$releaseWasteRow['key']] ?? '')) . '" data-release-waste-input placeholder="Kg" style="width:100%;box-sizing:border-box"></div>'
                    . '<div style="font-size:12px;color:#555">Comentario</div>'
                    . '<div><input type="text" name="release_waste_detail_comment[' . h((string)$releaseWasteRow['key']) . ']" value="' . h((string)($releaseWasteCommentState[$releaseWasteRow['key']] ?? '')) . '" style="width:100%;box-sizing:border-box"></div>'
                    . '</div>';
            }
            $entryHtml .= '<label style="margin-top:10px">Merma total</label>'
                . '<input type="text" value="' . h($releaseWasteTotalState) . ' Kg" readonly data-release-waste-display>'
                . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px"><button class="btn secondary" type="button" data-material-modal-close="release-' . $requestId . '"' . $closeReleaseOnclick . '>Cancelar</button><button class="btn" type="submit">Guardar salida</button></div>'
                . '</form></div></div>';
        }

        $body .= '<tr>';
        $body .= '<td>' . h($groupData['sku_description'] !== '' ? $groupData['sku_description'] : (string)($request['requested_item'] ?? '-')) . '</td>';
        $body .= '<td>' . h($groupData['sku_code'] !== '' ? $groupData['sku_code'] : '-') . '</td>';
        $body .= '<td>' . h($referenceRoll !== null && trim((string)($referenceRoll['roll_code'] ?? '')) !== '' ? (string)$referenceRoll['roll_code'] : '-') . '</td>';
        $body .= '<td>' . h($widthCmLabel) . '</td>';
        $body .= '<td>' . h($metersLabel) . '</td>';
        $body .= '<td>' . h($gramsLabel) . '</td>';
        $body .= '<td>' . h($groupData['color'] !== '' ? $groupData['color'] : '-') . '</td>';
        $body .= '<td>' . h($weightLabel) . '</td>';
        $body .= '<td>' . $entryHtml . '</td>';
        $body .= '</tr>';
    }
    if ($rollRequests === []) {
        $body .= '<tr><td colspan="9" class="muted">No hay telas pendientes por ingresar en esta orden de trabajo.</td></tr>';
    }
    $body .= '</tbody></table></div></td></tr></tbody></table></div>';
    $body .= '<script>
        (function () {
            function toggleModal(key, visible) {
                var modal = document.getElementById("material-modal-" + key);
                if (!modal) {
                    return;
                }
                modal.style.display = visible ? "block" : "none";
            }
            document.querySelectorAll("[data-material-modal-open]").forEach(function (button) {
                button.addEventListener("click", function () {
                    toggleModal(button.getAttribute("data-material-modal-open"), true);
                });
            });
            document.querySelectorAll("[data-material-modal-close]").forEach(function (button) {
                button.addEventListener("click", function () {
                    toggleModal(button.getAttribute("data-material-modal-close"), false);
                });
            });
            document.querySelectorAll("form[data-release-form]").forEach(function (form) {
                function syncReleaseWasteTotal() {
                    var total = 0;
                    form.querySelectorAll("[data-release-waste-input]").forEach(function (input) {
                        var value = parseFloat(input.value || "0");
                        if (isFinite(value) && value > 0) {
                            total += value;
                        }
                    });
                    var normalized = total > 0 ? total.toFixed(3) : "0";
                    var hidden = form.querySelector("[data-release-waste-total]");
                    var display = form.querySelector("[data-release-waste-display]");
                    if (hidden) hidden.value = normalized;
                    if (display) display.value = normalized + " Kg";
                }
                form.querySelectorAll("[data-release-waste-input]").forEach(function (input) {
                    input.addEventListener("input", syncReleaseWasteTotal);
                    input.addEventListener("change", syncReleaseWasteTotal);
                });
                syncReleaseWasteTotal();
            });
            document.querySelectorAll("[data-attach-roll-select]").forEach(function (select) {
                function updateAttachSummary() {
                    var requestId = select.getAttribute("data-attach-roll-select");
                    var option = select.options[select.selectedIndex];
                    var rollCode = option && option.getAttribute("data-roll-code") ? option.getAttribute("data-roll-code") : "-";
                    var rollWeight = option && option.getAttribute("data-roll-weight") ? option.getAttribute("data-roll-weight") : "-";
                    var codeNode = document.getElementById("attach-roll-code-" + requestId);
                    var kgNode = document.getElementById("attach-roll-kg-" + requestId);
                    var stockNode = document.getElementById("attach-roll-stock-" + requestId);
                    if (codeNode) {
                        codeNode.textContent = rollCode;
                    }
                    if (kgNode) {
                        kgNode.textContent = rollWeight;
                    }
                    if (stockNode) {
                        stockNode.textContent = rollWeight;
                    }
                }
                select.addEventListener("change", updateAttachSummary);
                updateAttachSummary();
            });
            document.querySelectorAll(".legacy-inline-modal").forEach(function (modal) {
                modal.addEventListener("click", function (event) {
                    if (event.target === modal) {
                        modal.style.display = "none";
                    }
                });
            });
        })();
    </script>';

    render('Utilizar materiales', $body);
}

function renderWorkOrderStartScreen(
    array $ot,
    array $chemicals,
    ?array $currentRoll,
    array $rollHistory,
    array $chemicalInputs,
    ?array $lastStart,
    ?array $lastFinish,
    string $currentOperatorName,
    ?string $flashMessage = null,
    bool $flashIsError = false,
    array $formState = [],
    array $materialRequests = [],
    array $wastes = [],
    array $boxes = [],
    array $pallets = [],
    ?array $outputRoll = null,
    array $availableMaterialRolls = [],
    array $cutWarehouses = [],
    ?array $activeShiftSession = null,
    array $assignedCliches = [],
    array $clicheUsageHistory = []
): void {
    $service = $GLOBALS['service'] ?? null;
    if (isErpProductionReadOnlyMode()) {
        renderErpWorkOrderReadOnlyScreen($ot, $currentRoll, $rollHistory, $chemicalInputs, $lastStart, $lastFinish, $materialRequests, $wastes, $boxes, $pallets, $outputRoll, $assignedCliches, $clicheUsageHistory);
        return;
    }

    $status = (string)$ot['status'];
    $statusLabel = match ($status) {
        'ACTIVE' => 'Producción',
        'CUTTING' => 'Corte pendiente',
        'CLOSED' => 'Fabricada completa',
        default => 'Pendiente',
    };
    $finishApproval = $service instanceof ReceptionService ? $service->getLastWorkOrderFinishApproval((int)$ot['id']) : null;
    $finishApprovedAt = trim((string)($finishApproval['created_at'] ?? ''));
    $isFinishApproved = $finishApprovedAt !== '';
    $sealingSetupApproval = $service instanceof ReceptionService ? $service->getLastWorkOrderSealingSetupApproval((int)$ot['id']) : null;
    $sealingLastFinish = $service instanceof ReceptionService ? $service->getLastWorkOrderSealingFinish((int)$ot['id']) : null;
    $sealingFinished = false;
    if ($sealingLastFinish !== null) {
        if ($sealingSetupApproval === null) {
            $sealingFinished = true;
        } else {
            $finishTs = strtotime((string)($sealingLastFinish['created_at'] ?? ''));
            $setupTs = strtotime((string)($sealingSetupApproval['created_at'] ?? ''));
            $sealingFinished = ($finishTs === false || $setupTs === false) ? true : ($finishTs >= $setupTs);
        }
    }
    $sheetText = trim((string)($ot['erp_plan_desc'] ?? '') . ' ' . (string)($ot['sku_final'] ?? ''));
    $isStarted = $lastStart !== null;
    $isCutting = $status === 'CUTTING';
    $isClosed = $status === 'CLOSED';
    $showFinishData = !$isClosed
        && !$isCutting
        && $isStarted
        && (
            ((string)($_GET['finish_data'] ?? '0') === '1')
            || ((string)($formState['show_finish_data'] ?? '0') === '1')
        );
    $screenTitle = match ($status) {
        'ACTIVE' => $showFinishData ? 'Terminar producción' : 'En curso',
        'CUTTING' => $isFinishApproved ? ($sealingFinished ? 'Embalaje' : 'Selladora') : 'En corte',
        'CLOSED' => 'Historico',
        default => 'Inicio OT',
    };
    $showFinishShortcut = !$showFinishData && !$isClosed && !$isCutting && $isStarted;
    $targetQtyLabel = trim((string)($ot['erp_target_qty'] ?? '')) !== ''
        ? (string)$ot['erp_target_qty']
        : (trim((string)($ot['target_qty'] ?? '')) !== '' ? (string)$ot['target_qty'] : '-');
    $screenActionHtml = '<a class="btn secondary" href="/work-orders">Volver</a>';
    if ($showFinishShortcut) {
        $screenActionHtml .= '<a class="legacy-primary-btn" href="/work-orders/' . (int)$ot['id'] . '/finish-data">Terminar OT</a>';
    } elseif ($isCutting) {
        $screenActionHtml .= $isFinishApproved
            ? ($sealingFinished
                ? '<a class="legacy-primary-btn" href="/work-orders/' . (int)$ot['id'] . '/packaging?sealing_finished=1">Iniciar alistamiento Embalaje</a>'
                : '<a class="legacy-primary-btn" href="/work-orders/' . (int)$ot['id'] . '/sealing">Ir a Selladora</a>')
            : '<a class="legacy-primary-btn" href="/work-orders/' . (int)$ot['id'] . '/finish-approval">Validar cierre flexo</a>';
    }
    $body = '<div class="legacy-screen-title">' . h($screenTitle) . '</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h($screenTitle) . '</div>'
        . '<div class="legacy-actionbar"><div class="row">' . $screenActionHtml . '</div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $expectedProcessStage = null;
    if ($status === 'ACTIVE') {
        $expectedProcessStage = 'PRODUCTION';
    } elseif ($status === 'CUTTING') {
        if (!$isFinishApproved) {
            $expectedProcessStage = 'CUTTING';
        } else {
            $expectedProcessStage = $sealingFinished ? 'PACKAGING' : 'SEALING';
        }
    }

    $sessionForDisplay = null;
    if ((int)($ot['shift_session_id'] ?? 0) > 0) {
        $sessionForDisplay = [
            'id' => (int)($ot['shift_session_id'] ?? 0),
            'machine_type_name' => (string)($ot['shift_machine_type_name'] ?? ''),
            'machine_name' => (string)($ot['shift_machine_name'] ?? ''),
            'machine_code' => (string)($ot['shift_machine_code'] ?? ''),
            'operator_name' => (string)($ot['shift_operator_name'] ?? ''),
            'helper_name' => (string)($ot['shift_helper_name'] ?? ''),
            'shift_label' => (string)($ot['shift_label'] ?? ''),
            'process_stage' => (string)($ot['shift_process_stage'] ?? ''),
            'comments' => (string)($ot['shift_comments'] ?? ''),
            'started_at' => (string)($activeShiftSession['started_at'] ?? ''),
            'work_order_id' => (int)$ot['id'],
        ];
    } elseif ($activeShiftSession !== null) {
        $sessionForDisplay = $activeShiftSession;
    }

    if ($sessionForDisplay !== null && $expectedProcessStage !== null) {
        $sessionProcessStage = strtoupper(trim((string)($sessionForDisplay['process_stage'] ?? '')));
        if ($sessionProcessStage !== '' && $sessionProcessStage !== $expectedProcessStage) {
            $sessionForDisplay = null;
        }
    }

    $operatorShiftMismatch = $activeShiftSession !== null
        && (int)($activeShiftSession['work_order_id'] ?? 0) > 0
        && (int)($activeShiftSession['work_order_id'] ?? 0) !== (int)$ot['id'];
    if ($operatorShiftMismatch) {
        $body .= '<div class="err" style="margin-bottom:12px">Tu turno activo está asociado a otra OT. Revisa la asignación de máquina antes de continuar con esta orden.</div>';
    }

    $stationLabel = trim((string)($sessionForDisplay['machine_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['machine_name']
        : (trim((string)($ot['erp_machine_label'] ?? '')) !== '' ? (string)$ot['erp_machine_label'] : 'Sin máquina asignada');
    $stationTypeLabel = trim((string)($sessionForDisplay['machine_type_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['machine_type_name']
        : 'Pendiente de asignación';
    $stationOperatorLabel = trim((string)($sessionForDisplay['operator_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['operator_name']
        : (trim((string)($ot['erp_worker_name'] ?? '')) !== '' ? (string)$ot['erp_worker_name'] : $currentOperatorName);
    $stationHelperLabel = trim((string)($sessionForDisplay['helper_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['helper_name']
        : '-';
    $stationShiftLabel = trim((string)($sessionForDisplay['shift_label'] ?? '')) !== ''
        ? (string)$sessionForDisplay['shift_label']
        : 'Sin turno iniciado';
    $stationStartedAt = trim((string)($sessionForDisplay['started_at'] ?? '')) !== ''
        ? (string)$sessionForDisplay['started_at']
        : '-';
    $stationCommentLabel = trim((string)($sessionForDisplay['comments'] ?? '')) !== ''
        ? (string)$sessionForDisplay['comments']
        : '-';
    $operationStageLabel = $sessionForDisplay !== null && trim((string)($sessionForDisplay['process_stage'] ?? '')) !== ''
        ? machineProcessStageLabel((string)$sessionForDisplay['process_stage'])
        : ($status === 'CUTTING' ? 'Corte' : ($status === 'CLOSED' ? 'Fabricación completa' : ($lastStart !== null ? 'Producción' : 'Preparación')));
    $nextOperationalArea = match (strtoupper(trim((string)($sessionForDisplay['process_stage'] ?? '')))) {
        'REWINDING' => 'Corte o embalaje',
        'PACKAGING' => 'Bodega final',
        'SEALING' => 'Corte o embalaje',
        'CUTTING' => 'Pallets y cierre final',
        default => ($status === 'CUTTING' ? 'Pallets y cierre final' : ($status === 'CLOSED' ? 'Proceso finalizado' : 'Rebobinado o corte')),
    };
    $pendingRequests = array_values(array_filter($materialRequests, static fn(array $req): bool => (string)($req['status'] ?? '') !== 'DELIVERED'));
    $deliveredRequests = array_values(array_filter($materialRequests, static fn(array $req): bool => in_array((string)($req['status'] ?? ''), ['PARTIAL', 'DELIVERED'], true)));
    $chemicalTotalWeightKg = 0.0;
    foreach ($chemicalInputs as $input) {
        $chemicalTotalWeightKg += (float)($input['weight_kg'] ?? 0);
    }
    $wasteTotalWeightKg = 0.0;
    foreach ($wastes as $waste) {
        $wasteTotalWeightKg += (float)($waste['weight_kg'] ?? 0);
    }
    $totalUnitsQty = 0;
    foreach ($boxes as $box) {
        $totalUnitsQty += (int)($box['units_qty'] ?? 0);
    }
    $totalBoxesOnPallets = 0;
    foreach ($pallets as $pallet) {
        $totalBoxesOnPallets += (int)($pallet['box_count'] ?? 0);
    }
    $outputRollLabel = $outputRoll !== null
        ? (string)$outputRoll['roll_code'] . ' (' . (string)$outputRoll['weight_kg'] . ' Kg)'
        : '-';
    $widthLabel = '-';
    $heightLabel = '-';
    $gussetLabel = '-';
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*[xX]\s*(\d+(?:[.,]\d+)?)(?:\s*[xX]\s*(\d+(?:[.,]\d+)?))?/', $sheetText, $dimensionMatch) === 1) {
        $widthLabel = $dimensionMatch[1];
        $heightLabel = $dimensionMatch[2];
        $gussetLabel = trim((string)($dimensionMatch[3] ?? '')) !== '' ? (string)$dimensionMatch[3] : '-';
    }
    $materialLabel = '-';
    foreach (['PLA', 'BOPP', 'PP', 'PEBD', 'PEAD', 'PE'] as $materialKeyword) {
        if (stripos($sheetText, $materialKeyword) !== false) {
            $materialLabel = $materialKeyword;
            break;
        }
    }
    $detectedColors = [];
    foreach ([
        trim((string)($currentRoll['color'] ?? '')),
        trim((string)($outputRoll['color'] ?? '')),
    ] as $colorValue) {
        if ($colorValue !== '' && !in_array($colorValue, $detectedColors, true)) {
            $detectedColors[] = $colorValue;
        }
    }
    foreach (['Natural', 'Blanco', 'Beige', 'Azul', 'Rojo', 'Verde', 'Negro', 'Transparente'] as $colorKeyword) {
        if (stripos($sheetText, $colorKeyword) !== false && !in_array($colorKeyword, $detectedColors, true)) {
            $detectedColors[] = $colorKeyword;
        }
    }
    $colorSlots = array_pad(array_slice($detectedColors, 0, 6), 6, '-');
    $aniloxCatalog = $service instanceof ReceptionService ? $service->listAniloxCatalog() : [];
    $savedAniloxAssignments = $service instanceof ReceptionService ? $service->getWorkOrderAniloxAssignments((int)$ot['id']) : [];
    $formAniloxSlots = isset($formState['anilox_slots']) && is_array($formState['anilox_slots'])
        ? $formState['anilox_slots']
        : null;
    $aniloxSlots = buildWorkOrderAniloxSlots($colorSlots, $chemicalInputs, $savedAniloxAssignments, $formAniloxSlots);
    $suggestedAniloxColors = extractSuggestedAniloxColors($colorSlots, $chemicalInputs, $aniloxSlots);
    $mermaPercentLabel = '-';
    $finishWasteKg = (float)($lastFinish['waste_kg'] ?? 0);
    $finishRollKg = (float)($lastFinish['final_roll_weight_kg'] ?? 0);
    if ($finishWasteKg > 0 && ($finishWasteKg + $finishRollKg) > 0) {
        $mermaPercentLabel = number_format(($finishWasteKg * 100) / ($finishWasteKg + $finishRollKg), 2, '.', '') . ' %';
    }
    $targetQtyNumber = is_numeric((string)$targetQtyLabel) ? (float)$targetQtyLabel : 0.0;
    $producedUnits = $totalUnitsQty;
    $producedPercent = $targetQtyNumber > 0 ? ($producedUnits * 100) / $targetQtyNumber : 0.0;
    $producedProgressLabel = number_format((float)$producedUnits, 0, '.', '.')
        . ' de '
        . ($targetQtyNumber > 0 ? number_format($targetQtyNumber, 0, '.', '.') : '0')
        . ' (' . number_format($producedPercent, 0, '.', '.') . ' %)';
    $saldoTotalUnits = max(0.0, $targetQtyNumber - (float)$producedUnits);
    $saldoTotalLabel = $targetQtyNumber > 0
        ? number_format($saldoTotalUnits, 0, '.', '.') . ' unidades'
        : '-';
    $fabricColorLabel = trim((string)($currentRoll['color'] ?? '')) !== ''
        ? (string)$currentRoll['color']
        : ((string)$colorSlots[0] !== '-' ? (string)$colorSlots[0] : '-');
    $fabricWidthLabel = isset($currentRoll['width_mm']) && (string)$currentRoll['width_mm'] !== ''
        ? rtrim(rtrim(number_format((float)$currentRoll['width_mm'], 2, '.', ''), '0'), '.')
        : $widthLabel;
    $fabricGramajeLabel = isset($currentRoll['grams']) && (string)$currentRoll['grams'] !== ''
        ? rtrim(rtrim(number_format((float)$currentRoll['grams'], 2, '.', ''), '0'), '.') . ' gr'
        : '-';
    $fabricMetersLabel = isset($currentRoll['meters']) && (string)$currentRoll['meters'] !== ''
        ? number_format((float)$currentRoll['meters'], 0, '.', '.')
        : '-';
    $fabricKgLabel = isset($currentRoll['weight_kg']) && (string)$currentRoll['weight_kg'] !== ''
        ? rtrim(rtrim(number_format((float)$currentRoll['weight_kg'], 2, '.', ''), '0'), '.') . ' kgs'
        : '-';
    $primaryCliche = $assignedCliches[0] ?? null;
    $primaryClicheLocationLabel = trim((string)($primaryCliche['location_detail'] ?? $primaryCliche['location_code'] ?? ''));
    if ($primaryClicheLocationLabel === '') {
        $primaryClicheLocationLabel = trim((string)($ot['erp_machine_label'] ?? ''));
    }
    $primaryClicheCodeLabel = trim((string)($primaryCliche['code'] ?? ''));
    if ($primaryClicheCodeLabel === '') {
        $primaryClicheCodeLabel = trim((string)($ot['erp_prod_number'] ?? ''));
    }
    $primarySelectedAniloxLabel = '-';
    foreach ($aniloxSlots as $aniloxSlot) {
        if ((int)($aniloxSlot['anilox_id'] ?? 0) <= 0) {
            continue;
        }
        foreach ($aniloxCatalog as $aniloxRow) {
            if ((int)($aniloxRow['id'] ?? 0) === (int)$aniloxSlot['anilox_id']) {
                $primarySelectedAniloxLabel = trim((string)($aniloxRow['display_label'] ?? ''));
                if ($primarySelectedAniloxLabel === '') {
                    $primarySelectedAniloxLabel = trim((string)($aniloxRow['code'] ?? '')) . ' - ' . trim((string)($aniloxRow['name'] ?? ''));
                }
                break 2;
            }
        }
    }
    $impressionsPerAxisLabel = 'Sin información';
    $configuredColorCount = count(array_values(array_filter(
        array_map(static fn(array $slot): string => trim((string)($slot['color_name'] ?? '')), $aniloxSlots),
        static fn(string $value): bool => $value !== ''
    )));
    $impressionsPerDevelopmentLabel = $configuredColorCount > 0 ? ($configuredColorCount . ' impresiones') : 'Sin información';
    $wasteValueLabel = $mermaPercentLabel !== '-' ? str_replace(' %', '', $mermaPercentLabel) : '-';
    $counterPrinterLabel = $producedUnits > 0
        ? number_format((float)$producedUnits, 0, '.', '.')
        : '-';
    $helperOptions = $service instanceof ReceptionService ? $service->listProductionPersonnelNames() : [];
    $machineHeaderHelperValue = isset($formState['shift_helper_name'])
        ? trim((string)$formState['shift_helper_name'])
        : (trim((string)($sessionForDisplay['helper_name'] ?? '')) !== '' ? trim((string)$sessionForDisplay['helper_name']) : '');
    $machineHeaderCommentValue = isset($formState['shift_comments'])
        ? trim((string)$formState['shift_comments'])
        : (trim((string)($sessionForDisplay['comments'] ?? '')) !== '' ? trim((string)$sessionForDisplay['comments']) : '');
    $topUnitRows = [];
    for ($unitIndex = 0; $unitIndex < 6; $unitIndex++) {
        $topUnitRows[] = [
            'label' => 'Unidad N° ' . ($unitIndex + 1),
            'value' => trim((string)($aniloxSlots[$unitIndex]['color_name'] ?? '')) !== ''
                ? h((string)$aniloxSlots[$unitIndex]['color_name'])
                : '-',
        ];
    }
    $bottomUnitRows = [];
    for ($unitIndex = 0; $unitIndex < 6; $unitIndex++) {
        $bottomUnitRows[] = [
            'label' => 'Unidad N° ' . ($unitIndex + 1),
            'value' => '-',
        ];
    }
    $imagePlaceholder = '<span class="legacy-placeholder-btn">Mostrar imagen</span>';
    $legacyClientRows = [
        ['N° OT', h((string)$ot['ot_code']), 'Color N° 1', h((string)$colorSlots[0])],
        ['Cliente', '-', 'Color N° 2', h((string)$colorSlots[1])],
        ['N° CC', trim((string)($ot['erp_req_id'] ?? '')) !== '' ? h((string)$ot['erp_req_id']) : '-', 'Color N° 3', h((string)$colorSlots[2])],
        ['Diseño', trim((string)($ot['erp_plan_desc'] ?? '')) !== '' ? h((string)$ot['erp_plan_desc']) : '-', 'Color N° 4', h((string)$colorSlots[3])],
        ['Producto', h((string)$ot['sku_final']), 'Color N° 5', h((string)$colorSlots[4])],
        ['Materialidad', h($materialLabel), 'Color N° 6', h((string)$colorSlots[5])],
        ['Ancho', h($widthLabel), 'Alto', h($heightLabel)],
        ['Fuelle', h($gussetLabel), 'Cantidad de Bolsas', h($targetQtyLabel)],
        ['Fecha de Entrega', trim((string)($ot['erp_plan_date'] ?? '')) !== '' ? h(formatLabelDate((string)$ot['erp_plan_date'])) : '-', 'Maquina', h($stationLabel)],
        ['Fecha de solicitud de cliché', '-', 'Fecha de recepción de cliché', '-'],
        ['Imagen Diseño', $imagePlaceholder, 'Imagen #5', $imagePlaceholder],
    ];
    $legacyFabricationRows = [
        ['Rodillo a utilizar', h($primarySelectedAniloxLabel), $topUnitRows[0]['label'], $topUnitRows[0]['value']],
        ['Corte de bolsa', h($heightLabel !== '-' ? ($heightLabel . ' mtrs') : '-'), $topUnitRows[1]['label'], $topUnitRows[1]['value']],
        ['Ubicación Cliché', h($primaryClicheLocationLabel !== '' ? $primaryClicheLocationLabel : '-'), $topUnitRows[2]['label'], $topUnitRows[2]['value']],
        ['Código Cliché', h($primaryClicheCodeLabel !== '' ? $primaryClicheCodeLabel : '-'), $topUnitRows[3]['label'], $topUnitRows[3]['value']],
        ['Pie de Imprenta', 'Cliente', $topUnitRows[4]['label'], $topUnitRows[4]['value']],
        ['N° Código de Barra', trim((string)($currentRoll['roll_code'] ?? '')) !== '' ? h((string)$currentRoll['roll_code']) : '-', $topUnitRows[5]['label'], $topUnitRows[5]['value']],
        ['Impresiones al eje', h($impressionsPerAxisLabel), $bottomUnitRows[0]['label'], $bottomUnitRows[0]['value']],
        ['Impresiones por desarrollo', h($impressionsPerDevelopmentLabel), $bottomUnitRows[1]['label'], $bottomUnitRows[1]['value']],
        ['% de merma', h($wasteValueLabel), $bottomUnitRows[2]['label'], $bottomUnitRows[2]['value']],
        ['Bolsas producidas / en curso', h($producedProgressLabel), $bottomUnitRows[3]['label'], $bottomUnitRows[3]['value']],
        ['Bolsas producidas / total', h($producedProgressLabel), $bottomUnitRows[4]['label'], $bottomUnitRows[4]['value']],
        ['Saldo total', h($saldoTotalLabel), $bottomUnitRows[5]['label'], $bottomUnitRows[5]['value']],
        ['', '', '', ''],
        ['Materialidad', h($materialLabel), 'Metros a imprimir', h($fabricMetersLabel)],
        ['Color Tela', h($fabricColorLabel), 'Contador impresora', h($counterPrinterLabel)],
        ['Ancho Tela', h($fabricWidthLabel), 'Kg a imprimir', h($fabricKgLabel)],
        ['Gramaje', h($fabricGramajeLabel), '', ''],
    ];
    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Informacion Cliente</th></tr></thead><tbody>';
    foreach ($legacyClientRows as $legacyRow) {
        $body .= '<tr>'
            . '<td class="legacy-label-cell">' . h((string)$legacyRow[0]) . '</td>'
            . '<td class="legacy-value-cell">' . $legacyRow[1] . '</td>'
            . '<td class="legacy-label-cell">' . h((string)$legacyRow[2]) . '</td>'
            . '<td class="legacy-value-cell">' . $legacyRow[3] . '</td>'
            . '</tr>';
    }
    $body .= '</tbody></table></div>';

    $compactClicheLocationLabel = $primaryClicheLocationLabel !== ''
        ? $primaryClicheLocationLabel
        : 'Sin ubicación de cliché';
    $body .= '<div style="margin:-2px 0 12px 0">'
        . '<a class="btn secondary" href="/cliches?work_order_id=' . (int)$ot['id'] . '" style="padding:4px 10px;font-size:12px;line-height:1.2;min-height:auto;border-radius:8px">'
        . 'Ubicación Cliché: ' . h($compactClicheLocationLabel)
        . '</a>'
        . '</div>';

    if ($clicheUsageHistory !== []) {
        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Historial de clisés en OT</th></tr></thead><tbody>';
        foreach ($clicheUsageHistory as $clicheLog) {
            $detailText = trim((string)($clicheLog['notes'] ?? ''));
            $locationText = trim((string)($clicheLog['to_location_code'] ?? ''));
            $body .= '<tr>'
                . '<td class="legacy-label-cell">' . h(eventTypeLabel('CLICHE_' . (string)$clicheLog['action_type'])) . '</td>'
                . '<td class="legacy-value-cell"><a href="/cliches/' . (int)$clicheLog['cliche_id'] . '">' . h((string)$clicheLog['cliche_code']) . '</a></td>'
                . '<td class="legacy-label-cell">' . h((string)$clicheLog['created_at']) . '</td>'
                . '<td class="legacy-value-cell">' . h(($locationText !== '' ? $locationText : '-') . ($detailText !== '' ? ' | ' . $detailText : '')) . '</td>'
                . '</tr>';
        }
        $body .= '</tbody></table></div>';
    }
    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Informacion de Fabricacion</th></tr></thead><tbody>';
    foreach ($legacyFabricationRows as $legacyRow) {
        if (
            trim((string)$legacyRow[0]) === ''
            && trim((string)$legacyRow[1]) === ''
            && trim((string)$legacyRow[2]) === ''
            && trim((string)$legacyRow[3]) === ''
        ) {
            $body .= '<tr><td class="legacy-value-cell" colspan="4" style="background:#f3f4f6;padding:8px"></td></tr>';
            continue;
        }
        $body .= '<tr>'
            . '<td class="legacy-label-cell">' . h((string)$legacyRow[0]) . '</td>'
            . '<td class="legacy-value-cell">' . $legacyRow[1] . '</td>'
            . '<td class="legacy-label-cell">' . h((string)$legacyRow[2]) . '</td>'
            . '<td class="legacy-value-cell">' . $legacyRow[3] . '</td>'
            . '</tr>';
    }
    $body .= '</tbody></table></div>';
    $body .= renderWorkOrderAniloxConfigCard((int)$ot['id'], $aniloxSlots, $aniloxCatalog, $suggestedAniloxColors);
    $body .= renderWorkOrderMachineHeaderCard(
        (int)$ot['id'],
        $sessionForDisplay,
        $stationTypeLabel,
        $stationLabel,
        $machineHeaderHelperValue,
        $machineHeaderCommentValue,
        $helperOptions
    );
    $showOperationalFlowOnLanding = false;
    $pendingRequests = array_values(array_filter($materialRequests, static fn(array $req): bool => (string)($req['status'] ?? '') !== 'DELIVERED'));
    $currentStage = $isClosed ? 5 : ($isCutting ? 4 : ($showFinishData ? 3 : ($isStarted ? 2 : 1)));

    if ($lastFinish !== null) {
        $legacyLastFinishRows = [
            ['Fecha', h((string)$lastFinish['created_at']), 'Peso final bobina', h((string)($lastFinish['final_roll_weight_kg'] ?? '0')) . ' Kg'],
            ['Peso final tintas', h((string)($lastFinish['final_chemical_weight_kg'] ?? '0')) . ' Kg', 'Cajas', h((string)($lastFinish['box_qty'] ?? '0'))],
            ['Merma', h((string)($lastFinish['waste_kg'] ?? '0')) . ' Kg', 'Bobina final', (int)($lastFinish['output_roll_id'] ?? 0) > 0 ? 'Generada' : '-'],
        ];
        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Ultimo cierre OT</th></tr></thead><tbody>';
        foreach ($legacyLastFinishRows as $legacyRow) {
            $body .= '<tr>'
                . '<td class="legacy-label-cell">' . h((string)$legacyRow[0]) . '</td>'
                . '<td class="legacy-value-cell">' . $legacyRow[1] . '</td>'
                . '<td class="legacy-label-cell">' . h((string)$legacyRow[2]) . '</td>'
                . '<td class="legacy-value-cell">' . $legacyRow[3] . '</td>'
                . '</tr>';
        }
        if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
            $body .= '<tr><td class="legacy-label-cell">Etiquetas</td><td class="legacy-value-cell" colspan="3"><div class="row"><a class="btn secondary" href="/rolls/' . (int)$lastFinish['output_roll_id'] . '/label?auto_print=1" target="_blank" rel="noopener">Etiqueta bobina final</a><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/box-label?auto_print=1" target="_blank" rel="noopener">Etiqueta cajas producto</a></div></td></tr>';
        }
        $body .= '</tbody></table></div>';
    }

    if ($showOperationalFlowOnLanding && in_array($currentStage, [1, 2], true)) {
        $sectionTitle = $currentStage === 1 ? '1. Solicitudes a bodega' : 'Solicitudes adicionales durante producción';
        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">' . h($sectionTitle) . '</th></tr></thead><tbody>';
        $body .= '<tr><td class="legacy-label-cell">Bobinas</td><td class="legacy-value-cell" colspan="3"><details class="fold">
              <summary>Bobinas</summary>
              <div class="fold-body">
              <form method="post" action="/work-orders/' . (int)$ot['id'] . '/material-request">
                <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
                <input type="hidden" name="request_type" value="ROLL">
                <div style="margin-bottom:10px">
                  <label>Tipo de bobina</label>
                  <select name="requested_group_key" required>
                    <option value="">Seleccionar material</option>';
    foreach ($availableMaterialRolls as $availableRoll) {
        $selected = ((string)($formState['requested_group_key'] ?? '') === (string)$availableRoll['group_key']) ? ' selected' : '';
        $body .= '<option value="' . h((string)$availableRoll['group_key']) . '"' . $selected . '>' . h(materialRequestGroupLabel($availableRoll)) . '</option>';
    }
    $body .= '</select>
                </div>
                <div class="row">
                  <div style="flex:1;min-width:160px">
                    <label>Cantidad de bobinas</label>
                    <input name="requested_qty" type="number" step="1" min="1" value="' . h((string)($formState['requested_qty'] ?? '1')) . '" required>
                  </div>
                  <div style="flex:1;min-width:220px">
                    <label>Nota</label>
                    <input name="request_notes" type="text" value="' . h((string)($formState['request_notes'] ?? '')) . '" placeholder="Observaciones para bodega">
                  </div>
                </div>
                <div style="margin-top:12px"><button class="btn" type="submit">Solicitar bobinas</button></div>
              </form>
              </div>
            </details></td></tr>';
        $body .= '<tr><td class="legacy-label-cell">Tintas</td><td class="legacy-value-cell" colspan="3"><details class="fold">
              <summary>Tintas</summary>
              <div class="fold-body">
              <form method="post" action="/work-orders/' . (int)$ot['id'] . '/material-request">
                <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
                <input type="hidden" name="request_type" value="CHEMICAL">
                <div class="row">
                  <div style="flex:2;min-width:220px">
                    <label>Tinta</label>
                    <select name="chemical_id" required>
                      <option value="">Seleccionar tinta</option>';
    foreach ($chemicals as $chemical) {
        $selected = ((string)($formState['chemical_request_id'] ?? '') === (string)$chemical['id']) ? ' selected' : '';
        $body .= '<option value="' . (int)$chemical['id'] . '"' . $selected . '>' . h((string)$chemical['code']) . ' - ' . h((string)$chemical['name']) . '</option>';
    }
    $body .= '</select>
                </div>
                  <div style="flex:1;min-width:160px">
                    <label>Cantidad</label>
                    <input name="requested_qty" type="number" step="0.001" min="0.001" value="' . h((string)($formState['chemical_requested_qty'] ?? '1')) . '" required>
                  </div>
                  <div style="flex:1;min-width:160px">
                    <label>Unidad</label>
                    <select name="requested_unit">
                      <option value="Kg">Kg</option>
                      <option value="Lt">Lt</option>
                      <option value="Unid.">Unid.</option>
                    </select>
                  </div>
                </div>
                <div style="margin-top:10px">
                  <label>Nota</label>
                  <input name="request_notes" type="text" value="' . h((string)($formState['chemical_request_notes'] ?? '')) . '" placeholder="Ej: para mezcla inicial o reposición">
                </div>
                <div style="margin-top:12px"><button class="btn" type="submit">Solicitar tinta</button></div>
              </form>
              </div>
            </details></td></tr>';
        $body .= '<tr><td class="legacy-label-cell">Otros insumos</td><td class="legacy-value-cell" colspan="3"><details class="fold">
              <summary>Otros insumos</summary>
              <div class="fold-body">
              <form method="post" action="/work-orders/' . (int)$ot['id'] . '/material-request">
                <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
                <input type="hidden" name="request_type" value="OTHER">
                <div class="row">
                  <div style="flex:2;min-width:220px">
                    <label>Material o insumo</label>
                    <input name="requested_item" type="text" value="' . h((string)($formState['requested_item'] ?? '')) . '" placeholder="Ej: tinta azul, cajas, bolsas, aditivo" required>
                  </div>
                  <div style="flex:1;min-width:160px">
                    <label>Cantidad</label>
                    <input name="requested_qty" type="number" step="0.001" min="0.001" value="' . h((string)($formState['other_requested_qty'] ?? '1')) . '" required>
                  </div>
                  <div style="flex:1;min-width:160px">
                    <label>Unidad</label>
                    <input name="requested_unit" type="text" value="' . h((string)($formState['other_requested_unit'] ?? 'Unid.')) . '" placeholder="Ej: Unid., Kg, Lt">
                  </div>
                </div>
                <div style="margin-top:10px">
                  <label>Nota</label>
                  <input name="request_notes" type="text" value="' . h((string)($formState['other_request_notes'] ?? '')) . '" placeholder="Observaciones para bodega">
                </div>
                <div style="margin-top:12px"><button class="btn" type="submit">Solicitar insumo</button></div>
              </form>
              </div>
            </details></td></tr>';
        $body .= '</tbody></table></div>';

        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Solicitudes de la OT</th></tr></thead><tbody>';
        $body .= '<tr><td class="legacy-label-cell">Solicitudes OT</td><td class="legacy-value-cell" colspan="3"><details class="fold"' . ($materialRequests !== [] ? ' open' : '') . '>
          <summary>Solicitudes OT</summary>
          <div class="fold-body"><div class="table-wrap"><table class="table-compact"><thead><tr><th>Tipo</th><th>Material solicitado</th><th>Cant.</th><th>Entregadas</th><th>Estado</th><th>Última entrega</th></tr></thead><tbody>';
    foreach ($materialRequests as $request) {
        $deliveredRoll = trim((string)($request['delivered_roll_code'] ?? ''));
        $body .= '<tr>';
        $body .= '<td>' . h(materialRequestTypeLabel((string)($request['request_type'] ?? 'ROLL'))) . '</td>';
        $body .= '<td>' . h((string)$request['requested_item']) . '</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($request['requested_qty'] ?? 0), (string)($request['requested_unit'] ?? 'Unid.'))) . '</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($request['delivered_qty'] ?? 0), (string)($request['requested_unit'] ?? 'Unid.'))) . '</td>';
        $body .= '<td>' . h(materialRequestStatusLabel((string)$request['status'])) . '</td>';
        $body .= '<td>' . h($deliveredRoll !== '' ? $deliveredRoll : (string)($request['delivered_by'] ?? '-')) . '</td>';
        $body .= '</tr>';
    }
    if ($materialRequests === []) {
        $body .= '<tr><td colspan="6" class="muted">Sin solicitudes todavía.</td></tr>';
    }
        $body .= '</tbody></table></div>
          </div></details></td></tr></tbody></table></div>';
    }

    if (false && $currentStage === 2) {
        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Registro de merma</th></tr></thead><tbody>';
        $body .= '<tr><td class="legacy-label-cell">Acción</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/waste">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <div class="row" style="align-items:end">
              <div style="flex:2;min-width:220px">
                <label>Motivo</label>
                <input name="waste_reason" type="text" value="' . h((string)($formState['waste_reason'] ?? '')) . '" placeholder="Ej: ajuste máquina, rotura, prueba color" required>
              </div>
              <div style="flex:1;min-width:180px">
                <label>Peso merma (Kg)</label>
                <input id="waste_weight_kg" name="waste_weight_kg" type="number" step="0.001" min="0" value="' . h((string)($formState['waste_weight_kg'] ?? '')) . '" required>
              </div>
              <div style="min-width:160px">
                <button class="btn secondary" type="button" id="read_scale_waste">Leer balanza</button>
              </div>
            </div>
            <div style="margin-top:12px"><button class="btn" type="submit">Guardar merma</button></div>
          </form></td></tr></tbody></table></div>';
        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Mermas registradas</th></tr></thead><tbody><tr><td class="legacy-value-cell" colspan="4"><table><thead><tr><th>Fecha</th><th>Motivo</th><th>Peso</th><th>Operador</th></tr></thead><tbody>';
    foreach ($wastes as $waste) {
        $body .= '<tr>';
        $body .= '<td>' . h((string)$waste['created_at']) . '</td>';
        $body .= '<td>' . h((string)$waste['reason']) . '</td>';
        $body .= '<td>' . h((string)$waste['weight_kg']) . ' Kg</td>';
        $body .= '<td>' . h((string)$waste['operator_name']) . '</td>';
        $body .= '</tr>';
    }
    if ($wastes === []) {
        $body .= '<tr><td colspan="4" class="muted">Sin mermas registradas.</td></tr>';
    }
    $body .= '</tbody></table></td></tr></tbody></table></div>';
    }

    if ($currentStage >= 4 && ($outputRoll !== null || $boxes !== [] || $pallets !== [])) {
        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Salida de producción y corte</th></tr></thead><tbody>';
        if ($outputRoll !== null) {
            $body .= '<tr><td class="legacy-label-cell">Bobina salida</td><td class="legacy-value-cell" colspan="3"><div class="legacy-note" style="margin-bottom:10px">
                <div class="row">
                  <div style="flex:1;min-width:180px"><div class="muted">Nueva bobina</div><div style="font-weight:800">' . h((string)$outputRoll['roll_code']) . '</div></div>
                  <div style="flex:1;min-width:180px"><div class="muted">Peso</div><div style="font-weight:800">' . h((string)$outputRoll['weight_kg']) . ' Kg</div></div>
                  <div style="flex:1;min-width:180px"><div class="muted">Bodega</div><div style="font-weight:800">' . h((string)$outputRoll['warehouse_code']) . '</div></div>
                </div>
                <div class="row" style="margin-top:10px">
                  <a class="btn secondary" href="/rolls/' . (int)$outputRoll['id'] . '">Ver bobina salida</a>
                  ' . ($currentStage === 4 ? '<a class="btn secondary" href="#cut-stage">Registrar corte en esta OT</a>' : '') . '
                  <a class="btn secondary" href="/rolls/' . (int)$outputRoll['id'] . '/label?auto_print=1" target="_blank" rel="noopener">Etiqueta bobina salida</a>
                </div>
              </div></td></tr>';
        }
        if ($boxes !== []) {
            $body .= '<tr><td class="legacy-label-cell">Cajas generadas</td><td class="legacy-value-cell" colspan="3"><div class="table-wrap"><table class="table-compact"><thead><tr><th>Código</th><th>Bobina origen</th><th>Unidades</th><th>Pallet</th><th></th></tr></thead><tbody>';
            foreach ($boxes as $box) {
                $body .= '<tr><td>' . h((string)$box['box_code']) . '</td><td>' . h((string)($box['source_roll_code'] ?? '-')) . '</td><td>' . h((string)$box['units_qty']) . '</td><td>' . h((string)($box['pallet_code'] ?? '-')) . '</td><td><a class="btn secondary" href="/boxes/' . (int)$box['id'] . '">Ver</a></td></tr>';
            }
            $body .= '</tbody></table></div></td></tr>';
        }
        if ($pallets !== []) {
            $body .= '<tr><td class="legacy-label-cell">Pallets generados</td><td class="legacy-value-cell" colspan="3"><div class="table-wrap"><table class="table-compact"><thead><tr><th>Código</th><th>Cajas</th><th>Destino</th><th></th></tr></thead><tbody>';
            foreach ($pallets as $pallet) {
                $body .= '<tr><td>' . h((string)$pallet['pallet_code']) . '</td><td>' . h((string)$pallet['box_count']) . '</td><td>' . h((string)$pallet['destination_mode']) . '</td><td><a class="btn secondary" href="/pallets/' . (int)$pallet['id'] . '">Ver</a></td></tr>';
            }
            $body .= '</tbody></table></div></td></tr>';
        }
        $body .= '</tbody></table></div>';
    }

    $cutDestinationState = strtoupper(trim((string)($formState['destination_mode'] ?? 'STOCK')));
    if (!in_array($cutDestinationState, ['STOCK', 'CUSTOMER_ORDER'], true)) {
        $cutDestinationState = 'STOCK';
    }
    $cutCustomerOrderState = (string)($formState['customer_order_ref'] ?? '');
    $cutWarehouseState = (string)($formState['warehouse_id'] ?? '');
    $cutUnitsState = (string)($formState['units_total'] ?? '');
    $cutBoxQtyState = (string)($formState['box_qty'] ?? ($lastFinish['box_qty'] ?? ''));
    $cutBoxesPerPalletState = (string)($formState['boxes_per_pallet'] ?? '10');

    $scanCode = (string)($formState['scan_code'] ?? '');
    $processWeightState = (string)($formState['process_weight_kg'] ?? '');
    $processWasteState = (string)($formState['process_waste_kg'] ?? '0');
    $chemicalIdState = (string)($formState['chemical_id'] ?? '');
    $chemicalWeightState = (string)($formState['chemical_weight_kg'] ?? '');
    $changeScanCode = (string)($formState['change_scan_code'] ?? '');
    $changeFinalWeightState = (string)($formState['change_final_roll_weight_kg'] ?? '');
    $changeWasteState = (string)($formState['change_waste_kg'] ?? '0');
    $changeOutputRollWeightState = (string)($formState['change_output_roll_weight_kg'] ?? '');
    $changeNextWeightState = (string)($formState['change_next_process_weight_kg'] ?? '');
    $changeNextWasteState = (string)($formState['change_next_waste_kg'] ?? '0');
    $finishRollWeightState = (string)($formState['finish_final_roll_weight_kg'] ?? '');
    $finishChemicalWeightState = (string)($formState['finish_final_chemical_weight_kg'] ?? '');
    $finishBoxQtyState = (string)($formState['finish_box_qty'] ?? '');
    $finishWasteState = (string)($formState['finish_waste_kg'] ?? '0');
    $finishOutputRollWeightState = (string)($formState['finish_output_roll_weight_kg'] ?? '');

    if ($showOperationalFlowOnLanding && !$isStarted) {
        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">1. Registro de inicio</th></tr></thead><tbody>';
        $body .= '<tr><td class="legacy-label-cell">Registro bobina inicial</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/attach-roll">
              <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
              <label>Código pieza / ID</label>
              <input name="scan_code" type="text" value="' . h($scanCode) . '" autofocus placeholder="Escanear bobina">
              <div class="row" style="align-items:end;margin-top:10px">
                <div style="flex:1;min-width:180px">
                  <label>Peso proceso (Kg)</label>
                  <input id="process_weight_kg" name="process_weight_kg" type="number" step="0.001" min="0" required value="' . h($processWeightState) . '">
                </div>
                <div style="min-width:160px">
                  <button class="btn secondary" type="button" id="read_scale_process_weight">Leer balanza</button>
                </div>
              </div>
              <div style="margin-top:10px">
                <label>Merma inicial (Kg)</label>
                <input name="process_waste_kg" type="number" step="0.001" min="0" value="' . h($processWasteState) . '">
              </div>
              <div style="margin-top:12px">
                <button class="btn" type="submit">Guardar bobina inicial</button>
              </div>
            </form></td></tr>';
        $body .= '<tr><td class="legacy-label-cell">Bobina lista para iniciar</td><td class="legacy-value-cell" colspan="3">';
        if ($currentRoll !== null) {
            $body .= '<div class="legacy-note">
                <div class="row">
                  <div style="flex:1;min-width:180px"><div class="muted">Código</div><div style="font-weight:800">' . h((string)$currentRoll['roll_code']) . '</div></div>
                  <div style="flex:1;min-width:180px"><div class="muted">Código SKU</div><div style="font-weight:800">' . h((string)$currentRoll['sku_code']) . '</div></div>
                  <div style="flex:1;min-width:180px"><div class="muted">Bodega</div><div style="font-weight:800">' . h((string)$currentRoll['warehouse_code']) . '</div></div>
                  <div style="flex:1;min-width:180px"><div class="muted">Peso actual</div><div style="font-weight:800">' . h((string)$currentRoll['weight_kg']) . ' Kg</div></div>
                </div>
              </div>';
        } else {
            $body .= '<div class="muted">Aún no hay una bobina registrada para el inicio.</div>';
        }
        $body .= '</td></tr>';
        $body .= '<tr><td class="legacy-label-cell">Registro de tintas de inicio</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/chemical-input">
              <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
              <div style="margin-bottom:10px">
                <label>Tinta</label>
                <select name="chemical_id" required>
                  <option value="">Seleccionar</option>';
        foreach ($chemicals as $chemical) {
            $selected = ((string)$chemical['id'] === $chemicalIdState) ? ' selected' : '';
            $body .= '<option value="' . (int)$chemical['id'] . '"' . $selected . '>' . h((string)$chemical['code']) . ' - ' . h((string)$chemical['name']) . '</option>';
        }
        $body .= '</select>
              </div>
              <div class="row" style="align-items:end">
                <div style="flex:1;min-width:220px">
                  <label>Peso entrada (Kg)</label>
                  <input id="chemical_input_weight_kg" name="chemical_weight_kg" type="number" step="0.001" min="0" required value="' . h($chemicalWeightState) . '">
                </div>
                <div style="min-width:180px">
                  <button class="btn secondary" type="button" id="read_scale_chemical_input">Leer balanza</button>
                </div>
              </div>
              <div style="margin-top:12px">
                <button class="btn" type="submit">Guardar tinta</button>
              </div>
            </form></td></tr>';
        $body .= '<tr><td class="legacy-label-cell">Tintas registradas</td><td class="legacy-value-cell" colspan="3"><table><thead><tr><th>Tinta</th><th>Peso</th><th>Fecha</th></tr></thead><tbody>';
        foreach ($chemicalInputs as $input) {
            $body .= '<tr>';
            $body .= '<td>' . h((string)$input['chemical_code']) . '</td>';
            $body .= '<td>' . h((string)$input['weight_kg']) . ' Kg</td>';
            $body .= '<td>' . h((string)$input['created_at']) . '</td>';
            $body .= '</tr>';
        }
        if ($chemicalInputs === []) {
            $body .= '<td colspan="3" class="muted">Sin tintas de inicio registradas.</td></tr>';
        }
        $body .= '</tbody></table></td></tr>';
        $body .= '<tr><td class="legacy-label-cell">2. Iniciar producción</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/start">
              <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
              <button class="btn" type="submit">Iniciar producción</button>
            </form></td></tr>';
        $body .= '<tr><td class="legacy-label-cell">3. Cambio de bobina</td><td class="legacy-value-cell" colspan="3">-</td></tr>';
        $body .= '<tr><td class="legacy-label-cell">4. Finalizar producción</td><td class="legacy-value-cell" colspan="3">-</td></tr>';
        $body .= '</tbody></table></div>';
    } elseif ($showOperationalFlowOnLanding && !$isClosed && !$isCutting) {
        if (!$showFinishData) {
            $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Producción activa</th></tr></thead><tbody>';
            $body .= '<tr><td class="legacy-label-cell">Bobina en producción</td><td class="legacy-value-cell" colspan="3">';
            if ($currentRoll !== null) {
                $body .= '<div class="legacy-note"><div class="row">
                    <div style="flex:1;min-width:180px"><div class="muted">Código</div><div style="font-weight:800">' . h((string)$currentRoll['roll_code']) . '</div></div>
                    <div style="flex:1;min-width:180px"><div class="muted">Código SKU</div><div style="font-weight:800">' . h((string)$currentRoll['sku_code']) . '</div></div>
                    <div style="flex:1;min-width:180px"><div class="muted">Bodega</div><div style="font-weight:800">' . h((string)$currentRoll['warehouse_code']) . '</div></div>
                    <div style="flex:1;min-width:180px"><div class="muted">Peso actual</div><div style="font-weight:800">' . h((string)$currentRoll['weight_kg']) . ' Kg</div></div>
                  </div></div>';
            } else {
                $body .= '<div class="muted">No hay una bobina activa en producción.</div>';
            }
            $body .= '</td></tr>';
            $body .= '<tr><td class="legacy-label-cell">3. Cambio de bobina (opcional)</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/change-roll">
                  <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
                  <div class="row" style="align-items:end">
                    <div style="flex:1;min-width:180px">
                      <label>Peso remanente bobina actual (Kg)</label>
                      <input id="change_final_roll_weight_kg" name="change_final_roll_weight_kg" type="number" step="0.001" min="0" value="' . h($changeFinalWeightState) . '">
                    </div>
                    <div style="min-width:160px">
                      <button class="btn secondary" type="button" id="read_scale_change_final">Leer balanza</button>
                    </div>
                    <div style="flex:1;min-width:180px">
                      <label>Merma bobina actual (Kg)</label>
                      <input name="change_waste_kg" type="number" step="0.001" min="0" value="' . h($changeWasteState) . '">
                    </div>
                  </div>
                  <div class="row" style="align-items:end;margin-top:10px">
                    <div style="flex:1;min-width:180px">
                      <label>Peso bobina salida impresión (Kg)</label>
                      <input id="change_output_roll_weight_kg" name="change_output_roll_weight_kg" type="number" step="0.001" min="0" value="' . h($changeOutputRollWeightState) . '">
                    </div>
                    <div style="min-width:160px">
                      <button class="btn secondary" type="button" id="read_scale_change_output">Leer balanza</button>
                    </div>
                  </div>
                  <div class="row" style="align-items:end;margin-top:10px">
                    <div style="flex:1;min-width:180px">
                      <label>Nueva bobina código / ID</label>
                      <input name="change_scan_code" type="text" value="' . h($changeScanCode) . '" placeholder="Escanear nueva bobina">
                    </div>
                    <div style="flex:1;min-width:180px">
                      <label>Peso nueva bobina (Kg)</label>
                      <input id="change_next_process_weight_kg" name="change_next_process_weight_kg" type="number" step="0.001" min="0" value="' . h($changeNextWeightState) . '">
                    </div>
                    <div style="min-width:160px">
                      <button class="btn secondary" type="button" id="read_scale_change_next">Leer balanza</button>
                    </div>
                    <div style="flex:1;min-width:180px">
                      <label>Merma nueva bobina (Kg)</label>
                      <input name="change_next_waste_kg" type="number" step="0.001" min="0" value="' . h($changeNextWasteState) . '">
                    </div>
                  </div>
                  <div style="margin-top:12px">
                    <button class="btn" type="submit">Cambiar bobina</button>
                  </div>
                </form></td></tr>';
            $body .= '<tr><td class="legacy-label-cell">4. Finalizar producción</td><td class="legacy-value-cell" colspan="3"><div class="row">
                  <a class="btn" href="/work-orders/' . (int)$ot['id'] . '/finish-data">Registrar cierre de impresión</a>
                </div></td></tr></tbody></table></div>';
        } else {
            $body .= '<div class="legacy-sheet-card" style="margin-top:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">5. Datos finalizar impresión</th></tr></thead><tbody>';
            $body .= '<tr><td class="legacy-label-cell">Cierre de impresión</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/finish">
                  <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
                  <input type="hidden" name="show_finish_data" value="1">
                  <div class="row" style="align-items:end">
                    <div style="flex:1;min-width:180px">
                      <label>Peso final bobina (Kg)</label>
                      <input id="finish_final_roll_weight_kg" name="finish_final_roll_weight_kg" type="number" step="0.001" min="0" value="' . h($finishRollWeightState) . '">
                    </div>
                    <div style="min-width:160px">
                      <button class="btn secondary" type="button" id="read_scale_finish_roll">Leer balanza</button>
                    </div>
                    <div style="flex:1;min-width:180px">
                  <label>Peso final tintas (Kg)</label>
                      <input id="finish_final_chemical_weight_kg" name="finish_final_chemical_weight_kg" type="number" step="0.001" min="0" value="' . h($finishChemicalWeightState) . '">
                    </div>
                    <div style="min-width:160px">
                      <button class="btn secondary" type="button" id="read_scale_finish_chemicals">Leer balanza</button>
                    </div>
                    <div style="flex:1;min-width:180px">
                      <label>Cantidad de cajas</label>
                      <input name="finish_box_qty" type="number" step="1" min="1" value="' . h($finishBoxQtyState) . '">
                    </div>
                    <div style="flex:1;min-width:180px">
                      <label>Peso nueva bobina salida (Kg)</label>
                      <input id="finish_output_roll_weight_kg" name="finish_output_roll_weight_kg" type="number" step="0.001" min="0" value="' . h($finishOutputRollWeightState) . '">
                    </div>
                    <div style="min-width:160px">
                      <button class="btn secondary" type="button" id="read_scale_finish_output_roll">Leer balanza</button>
                    </div>
                    <div style="flex:1;min-width:180px">
                      <label>Merma final bobina (Kg)</label>
                      <input name="finish_waste_kg" type="number" step="0.001" min="0" value="' . h($finishWasteState) . '">
                    </div>
                  </div>
                  <div style="margin-top:12px">
                    <button class="btn" type="submit">Guardar cierre de impresión e imprimir etiqueta</button>
                  </div>
                </form></td></tr></tbody></table></div>';
        }
    }

    if ($isCutting && !$isFinishApproved) {
        $body .= '<div class="legacy-sheet-card" style="margin-top:12px;border-color:#f59e0b;background:#fffbeb"><div style="font-size:16px;font-weight:800;color:#92400e;margin-bottom:6px">Validación de supervisor pendiente</div><div style="color:#78350f">Antes de pasar a la siguiente máquina debes validar el cierre de flexografía con supervisor.</div><div style="margin-top:12px"><a class="btn" href="/work-orders/' . (int)$ot['id'] . '/finish-approval">Validar cierre flexo</a></div></div>';
    } elseif ($isCutting && $isFinishApproved) {
        $body .= '<div class="legacy-sheet-card" style="margin-top:12px;border-color:#0f766e;background:#f0fdfa"><div style="font-size:16px;font-weight:800;color:#115e59;margin-bottom:6px">Flexografía validada</div><div style="color:#134e4a">La OT quedó lista para continuar en Selladora. Desde aquí ya no se trabaja el cierre como corte.</div><div style="margin-top:12px"><a class="btn" href="/work-orders/' . (int)$ot['id'] . '/sealing">Abrir pantalla de Selladora</a></div></div>';
    } elseif ($isCutting && $outputRoll !== null) {
        $body .= '<div class="legacy-sheet-card" id="cut-stage" style="margin-top:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">5. Corte final de la OT</th></tr></thead><tbody>';
        $body .= '<tr><td class="legacy-label-cell">Registro de corte</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/cut/process">
              <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
              <input type="hidden" name="source_roll_id" value="' . (int)$outputRoll['id'] . '">
              <div class="row">
                <div style="flex:1;min-width:220px">
                  <label>Bobina salida producción</label>
                  <input type="text" value="' . h((string)$outputRoll['roll_code']) . '" readonly>
                </div>
                <div style="flex:1;min-width:220px">
                  <label>Destino</label>
                  <select name="destination_mode" id="ot_cut_destination_mode">
                    <option value="STOCK"' . ($cutDestinationState === 'STOCK' ? ' selected' : '') . '>Almacenar en stock</option>
                    <option value="CUSTOMER_ORDER"' . ($cutDestinationState === 'CUSTOMER_ORDER' ? ' selected' : '') . '>Orden de compra cliente</option>
                  </select>
                </div>
                <div style="flex:1;min-width:220px" id="ot_cut_customer_wrap">
                  <label>OC cliente</label>
                  <input name="customer_order_ref" type="text" value="' . h($cutCustomerOrderState) . '" placeholder="OC cliente">
                </div>
              </div>
              <div class="row" style="margin-top:10px">
                <div style="flex:1;min-width:220px">
                  <label>Asignación de bodega</label>
                  <input type="text" value="La realiza Bodega al recibir el pallet terminado" readonly>
                </div>
                <div style="flex:1;min-width:180px">
                  <label>Unidades totales</label>
                  <input name="units_total" type="number" step="1" min="1" required value="' . h($cutUnitsState) . '">
                </div>
                <div style="flex:1;min-width:180px">
                  <label>Cantidad de cajas</label>
                  <input name="box_qty" type="number" step="1" min="1" required value="' . h($cutBoxQtyState) . '">
                </div>
                <div style="flex:1;min-width:180px">
                  <label>Cajas por pallet</label>
                  <input name="boxes_per_pallet" type="number" step="1" min="1" required value="' . h($cutBoxesPerPalletState) . '">
                </div>
              </div>
              <div style="margin-top:12px">
                <button class="btn" type="submit">Completar corte y cerrar OT</button>
              </div>
            </form></td></tr></tbody></table></div>';
    }

    if (false && $rollHistory !== [] && $currentStage >= 2) {
        $body .= '<details class="fold" style="margin-top:12px"><summary>' . ($currentStage >= 4 ? 'Historial final de bobinas OT' : 'Historial de bobinas en proceso') . '</summary><div class="fold-body"><div class="table-wrap"><table class="table-compact"><thead><tr><th>Fecha</th><th>Acción</th><th>Bobina</th><th>Peso</th><th>Merma</th><th>Detalle</th></tr></thead><tbody>';
        foreach ($rollHistory as $roll) {
            $payload = $roll['payload_data'] ?? [];
            $action = (string)$roll['type'] === 'WORK_ORDER_ROLL_ATTACHED' ? 'Ingreso OT' : 'Salida OT';
            $weight = (string)$roll['type'] === 'WORK_ORDER_ROLL_ATTACHED'
                ? (string)($payload['process_weight_kg'] ?? '-')
                : (string)($payload['final_weight_kg'] ?? '-');
            $detail = (string)$roll['type'] === 'WORK_ORDER_ROLL_RELEASED'
                ? ('Motivo: ' . (string)($payload['reason'] ?? '-'))
                : ('Operador: ' . (string)($payload['operator_name'] ?? '-'));
            $body .= '<tr>';
            $body .= '<td>' . h((string)$roll['created_at']) . '</td>';
            $body .= '<td>' . h($action) . '</td>';
            $body .= '<td>' . h((string)$roll['roll_code']) . ' - ' . h((string)$roll['sku_code']) . '</td>';
            $body .= '<td>' . h($weight) . ' Kg</td>';
            $body .= '<td>' . h((string)($payload['waste_kg'] ?? '0')) . ' Kg</td>';
            $body .= '<td>' . h($detail) . '</td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div></div></details>';
    }

    $body .= '<script>
      (function () {
        function bindScale(btnId, inputId) {
          var btn = document.getElementById(btnId);
          var input = document.getElementById(inputId);
          if (!btn || !input) return;
          btn.addEventListener("click", async function () {
            btn.disabled = true;
            btn.textContent = "Leyendo...";
            try {
              var res = await fetch("/api/scale/weight", { cache: "no-store" });
              var data = await res.json();
              if (!data || data.ok !== true) throw new Error((data && data.error) ? data.error : "No se pudo leer la balanza.");
              input.value = data.weight_kg;
              input.dispatchEvent(new Event("input", { bubbles: true }));
              input.dispatchEvent(new Event("change", { bubbles: true }));
            } catch (e) {
              alert(e && e.message ? e.message : "Error leyendo la balanza.");
            } finally {
              btn.disabled = false;
              btn.textContent = "Leer balanza";
            }
          });
        }
        bindScale("read_scale_process_weight", "process_weight_kg");
        bindScale("read_scale_chemical_input", "chemical_input_weight_kg");
        bindScale("read_scale_change_final", "change_final_roll_weight_kg");
        bindScale("read_scale_change_output", "change_output_roll_weight_kg");
        bindScale("read_scale_change_next", "change_next_process_weight_kg");
        bindScale("read_scale_finish_roll", "finish_final_roll_weight_kg");
        bindScale("read_scale_finish_chemicals", "finish_final_chemical_weight_kg");
        bindScale("read_scale_finish_output_roll", "finish_output_roll_weight_kg");
        bindScale("read_scale_waste", "waste_weight_kg");
        var cutMode = document.getElementById("ot_cut_destination_mode");
        var cutCustomerWrap = document.getElementById("ot_cut_customer_wrap");
        if (cutMode) {
          var syncCutMode = function () {
            var isCustomer = cutMode.value === "CUSTOMER_ORDER";
            if (cutCustomerWrap) cutCustomerWrap.style.display = isCustomer ? "" : "none";
          };
          cutMode.addEventListener("change", syncCutMode);
          syncCutMode();
        }
        var syncAniloxTable = function (table) {
          var rows = Array.prototype.slice.call(table.querySelectorAll("tr[data-anilox-row]"));
          rows.forEach(function (row, index) {
            var unit = index + 1;
            var unitLabel = row.querySelector("[data-anilox-unit]");
            var unitInput = row.querySelector("[data-anilox-unit-input]");
            if (unitLabel) unitLabel.textContent = String(unit);
            if (unitInput) unitInput.value = String(unit);
            var upBtn = row.querySelector(".anilox-move-btn[data-direction=\"up\"]");
            var downBtn = row.querySelector(".anilox-move-btn[data-direction=\"down\"]");
            if (upBtn) upBtn.disabled = index === 0;
            if (downBtn) downBtn.disabled = index === rows.length - 1;
          });
        };
        Array.prototype.slice.call(document.querySelectorAll("[data-anilox-table]")).forEach(function (table) {
          syncAniloxTable(table);
          table.addEventListener("click", function (event) {
            var button = event.target.closest(".anilox-move-btn");
            if (!button) return;
            var row = button.closest("tr[data-anilox-row]");
            if (!row || !row.parentNode) return;
            if (button.getAttribute("data-direction") === "up" && row.previousElementSibling) {
              row.parentNode.insertBefore(row, row.previousElementSibling);
              syncAniloxTable(table);
            }
            if (button.getAttribute("data-direction") === "down" && row.nextElementSibling) {
              row.parentNode.insertBefore(row.nextElementSibling, row);
              syncAniloxTable(table);
            }
          });
        });
      })();
    </script>';

    render($screenTitle, $body);
}

function renderErpWorkOrderReadOnlyScreen(
    array $ot,
    ?array $currentRoll,
    array $rollHistory,
    array $chemicalInputs,
    ?array $lastStart,
    ?array $lastFinish,
    array $materialRequests,
    array $wastes,
    array $boxes,
    array $pallets,
    ?array $outputRoll,
    array $assignedCliches = [],
    array $clicheUsageHistory = []
): void {
    $status = (string)($ot['status'] ?? '');
    $statusLabel = workOrderStatusLabel($status);
    $isStarted = $lastStart !== null;
    $isCutting = $status === 'CUTTING';
    $isClosed = $status === 'CLOSED';
    $currentStage = $isClosed ? 5 : ($isCutting ? 4 : ($isStarted ? 2 : 1));
    $stageTitles = [
        1 => 'Preparación OT',
        2 => 'Producción activa',
        3 => 'Cierre de impresión',
        4 => 'Corte de la OT',
        5 => 'Fabricación completa',
    ];
    if ($lastFinish !== null && !$isClosed && !$isCutting) {
        $currentStage = 3;
    }

    $statusTone = match ($status) {
        'CLOSED' => ['#ecfdf3', '#067647', '#abefc6'],
        'CUTTING' => ['#fff7ed', '#c4320a', '#fdba74'],
        'ACTIVE' => ['#eff8ff', '#175cd3', '#b2ddff'],
        default => ['#f8fafc', '#475467', '#d0d5dd'],
    };

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:20px;font-weight:800">Seguimiento OT ' . h((string)$ot['ot_code']) . '</div>
          <div class="muted">Vista ejecutiva de trazabilidad y movimientos de la orden. Sin acciones operativas desde ERP.</div>
        </div>
        <div class="row">
          <a class="btn secondary" href="/work-orders">Volver</a>
          <a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/traceability">Ver trazabilidad completa</a>
        </div>
      </div>';

    $body .= '<div class="card" style="margin-bottom:12px;border-color:' . $statusTone[2] . ';background:' . $statusTone[0] . '">
        <div class="row" style="justify-content:space-between;align-items:flex-start">
          <div style="font-size:18px;font-weight:800">' . h((string)$ot['sku_final']) . '</div>
          <div style="padding:8px 12px;border-radius:999px;background:#fff;color:' . $statusTone[1] . ';font-weight:800;border:1px solid ' . $statusTone[2] . '">' . h($statusLabel) . '</div>
        </div>
        <div class="ot-meta-grid" style="margin-top:12px">
          <div class="ot-meta"><div class="muted">OT</div><div class="value">' . h((string)$ot['ot_code']) . '</div></div>
          <div class="ot-meta"><div class="muted">SKU final</div><div class="value">' . h((string)$ot['sku_final']) . '</div></div>
          <div class="ot-meta"><div class="muted">Cantidad objetivo</div><div class="value">' . h((string)($ot['target_qty'] ?? '-')) . '</div></div>
          <div class="ot-meta"><div class="muted">Fecha creación</div><div class="value">' . h((string)($ot['created_at'] ?? '-')) . '</div></div>
          <div class="ot-meta"><div class="muted">Plan ERP</div><div class="value">' . h((string)($ot['erp_plan_date'] ?? '-')) . '</div></div>
          <div class="ot-meta"><div class="muted">Máquina ERP</div><div class="value">' . h((string)($ot['erp_machine_label'] ?? '-')) . '</div></div>
          <div class="ot-meta"><div class="muted">Producción ERP</div><div class="value">' . h((string)($ot['erp_prod_number'] ?? '-')) . '</div></div>
          <div class="ot-meta"><div class="muted">Req ERP</div><div class="value">' . h((string)($ot['erp_req_id'] ?? '-')) . '</div></div>
          <div class="ot-meta"><div class="muted">Operario ERP</div><div class="value">' . h((string)($ot['erp_worker_name'] ?? '-')) . '</div></div>
          <div class="ot-meta"><div class="muted">Inicio producción</div><div class="value">' . h((string)($lastStart['created_at'] ?? '-')) . '</div></div>
          <div class="ot-meta"><div class="muted">Último cierre</div><div class="value">' . h((string)($lastFinish['created_at'] ?? '-')) . '</div></div>
        </div>
      </div>';

    $body .= '<div class="ot-stage-grid" style="margin-bottom:12px">';
    foreach ([1, 2, 3, 4, 5] as $stageNumber) {
        $isDone = $stageNumber < $currentStage;
        $isCurrent = $stageNumber === $currentStage;
        $stageClass = 'ot-stage-card' . ($isCurrent ? ' current' : '') . ($isDone ? ' done' : '');
        $stageState = $isDone ? 'Completado' : ($isCurrent ? 'En curso' : 'Pendiente');
        $body .= '<div class="' . $stageClass . '"><div class="muted">Etapa ' . $stageNumber . '</div><div style="font-weight:800;margin:4px 0 6px">' . h($stageTitles[$stageNumber]) . '</div><div class="muted">' . h($stageState) . '</div></div>';
    }
    $body .= '</div>';

    $body .= '<div class="dashboard-grid" style="margin-bottom:12px">';
    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Resumen operativo</div><div class="ot-meta-grid">
        <div class="ot-meta"><div class="muted">Bobina en proceso</div><div class="value">' . h((string)($currentRoll['roll_code'] ?? '-')) . '</div></div>
        <div class="ot-meta"><div class="muted">Bobina salida</div><div class="value">' . h((string)($outputRoll['roll_code'] ?? '-')) . '</div></div>
        <div class="ot-meta"><div class="muted">Solicitudes</div><div class="value">' . count($materialRequests) . '</div></div>
        <div class="ot-meta"><div class="muted">Movimientos bobina</div><div class="value">' . count($rollHistory) . '</div></div>
        <div class="ot-meta"><div class="muted">Mermas</div><div class="value">' . count($wastes) . '</div></div>
        <div class="ot-meta"><div class="muted">Tintas</div><div class="value">' . count($chemicalInputs) . '</div></div>
        <div class="ot-meta"><div class="muted">Cajas</div><div class="value">' . count($boxes) . '</div></div>
        <div class="ot-meta"><div class="muted">Pallets</div><div class="value">' . count($pallets) . '</div></div>
        <div class="ot-meta"><div class="muted">Clisés activos</div><div class="value">' . count($assignedCliches) . '</div></div>
      </div></div>';

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Observación ERP</div>
        <div class="muted" style="margin-bottom:10px">Esta vista está orientada a seguimiento gerencial. Desde aquí solo se consulta avance, estado y trazabilidad; la ejecución se realiza en Producción.</div>
        <div class="panel">
          <div style="font-weight:700;margin-bottom:6px">Situación actual</div>
          <div class="muted">Estado: ' . h($statusLabel) . ' · Etapa: ' . h($stageTitles[$currentStage]) . '</div>
          <div class="muted" style="margin-top:6px">Bobina actual: ' . h((string)($currentRoll['roll_code'] ?? '-')) . ' · Bobina salida: ' . h((string)($outputRoll['roll_code'] ?? '-')) . '</div>
        </div></div>';
    $body .= '</div>';

    $body .= '<div class="card" style="margin-bottom:12px"><div style="font-weight:800;margin-bottom:8px">Clisés OT</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Código</th><th>Descripción</th><th>Ubicación</th><th>Estado</th><th></th></tr></thead><tbody>';
    foreach ($assignedCliches as $assignedCliche) {
        $locationLabel = trim((string)($assignedCliche['location_detail'] ?? $assignedCliche['location_code'] ?? ''));
        $body .= '<tr>';
        $body .= '<td>' . h((string)$assignedCliche['code']) . '</td>';
        $body .= '<td>' . h((string)$assignedCliche['description']) . '</td>';
        $body .= '<td>' . h($locationLabel !== '' ? $locationLabel : '-') . '</td>';
        $body .= '<td>' . h(clicheStatusLabel((string)$assignedCliche['status'])) . '</td>';
        $body .= '<td><a class="btn secondary" href="/cliches/' . (int)$assignedCliche['id'] . '?work_order_id=' . (int)$ot['id'] . '">Ver clisé</a></td>';
        $body .= '</tr>';
    }
    if ($assignedCliches === []) {
        $body .= '<tr><td colspan="5" class="muted">No hay clisés activos asociados a esta OT.</td></tr>';
    }
    $body .= '</tbody></table></div><div style="margin-top:10px"><a class="btn secondary" href="/cliches?work_order_id=' . (int)$ot['id'] . '">Gestionar clisés</a></div></div>';

    if ($clicheUsageHistory !== []) {
        $body .= '<div class="card" style="margin-bottom:12px"><div style="font-weight:800;margin-bottom:8px">Historial de clisés</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Fecha</th><th>Acción</th><th>Clisé</th><th>Detalle</th></tr></thead><tbody>';
        foreach ($clicheUsageHistory as $clicheLog) {
            $detailText = trim((string)($clicheLog['notes'] ?? ''));
            $locationText = trim((string)($clicheLog['to_location_code'] ?? ''));
            $body .= '<tr>';
            $body .= '<td>' . h((string)$clicheLog['created_at']) . '</td>';
            $body .= '<td>' . h(eventTypeLabel('CLICHE_' . (string)$clicheLog['action_type'])) . '</td>';
            $body .= '<td><a href="/cliches/' . (int)$clicheLog['cliche_id'] . '?work_order_id=' . (int)$ot['id'] . '">' . h((string)$clicheLog['cliche_code']) . '</a></td>';
            $body .= '<td>' . h(($locationText !== '' ? $locationText : '-') . ($detailText !== '' ? ' | ' . $detailText : '')) . '</td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div></div>';
    }

    $body .= '<div class="card" style="margin-bottom:12px"><div style="font-weight:800;margin-bottom:8px">Movimientos de bobinas</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Fecha</th><th>Acción</th><th>Bobina</th><th>Peso</th><th>Merma</th><th>Detalle</th></tr></thead><tbody>';
    foreach ($rollHistory as $roll) {
        $payload = $roll['payload_data'] ?? [];
        $action = (string)$roll['type'] === 'WORK_ORDER_ROLL_ATTACHED' ? 'Ingreso a OT' : 'Salida de OT';
        $weight = (string)$roll['type'] === 'WORK_ORDER_ROLL_ATTACHED'
            ? (string)($payload['process_weight_kg'] ?? '-')
            : (string)($payload['final_weight_kg'] ?? '-');
        $detail = (string)$roll['type'] === 'WORK_ORDER_ROLL_RELEASED'
            ? ('Motivo: ' . (string)($payload['reason'] ?? '-'))
            : ('Operador: ' . (string)($payload['operator_name'] ?? '-'));
        $body .= '<tr><td>' . h((string)$roll['created_at']) . '</td><td>' . h($action) . '</td><td>' . h((string)$roll['roll_code']) . '</td><td>' . h($weight) . ' Kg</td><td>' . h((string)($payload['waste_kg'] ?? '0')) . ' Kg</td><td>' . h($detail) . '</td></tr>';
    }
    if ($rollHistory === []) {
        $body .= '<tr><td colspan="6" class="muted">Sin movimientos de bobina registrados.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';

    $body .= '<div class="trace-grid" style="margin-bottom:12px">';
    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Solicitudes y abastecimiento</div><div class="table-wrap"><table><thead><tr><th>Tipo</th><th>Material</th><th>Pedida</th><th>Entregada</th><th>Estado</th></tr></thead><tbody>';
    foreach ($materialRequests as $request) {
        $body .= '<tr><td>' . h(materialRequestTypeLabel((string)($request['request_type'] ?? 'ROLL'))) . '</td><td>' . h((string)($request['requested_item'] ?? '-')) . '</td><td>' . h(formatReceptionValue((float)($request['requested_qty'] ?? 0), (string)($request['requested_unit'] ?? 'Unid.'))) . '</td><td>' . h(formatReceptionValue((float)($request['delivered_qty'] ?? 0), (string)($request['requested_unit'] ?? 'Unid.'))) . '</td><td>' . h(materialRequestStatusLabel((string)($request['status'] ?? ''))) . '</td></tr>';
    }
    if ($materialRequests === []) {
        $body .= '<tr><td colspan="5" class="muted">Sin solicitudes registradas.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Tintas y mermas</div><div class="table-wrap"><table><thead><tr><th>Tipo</th><th>Detalle</th><th>Valor</th><th>Fecha</th></tr></thead><tbody>';
    foreach ($chemicalInputs as $input) {
        $body .= '<tr><td>Tinta</td><td>' . h((string)$input['chemical_code']) . '</td><td>' . h((string)$input['weight_kg']) . ' Kg</td><td>' . h((string)$input['created_at']) . '</td></tr>';
    }
    foreach ($wastes as $waste) {
        $body .= '<tr><td>Merma</td><td>' . h((string)$waste['reason']) . '</td><td>' . h((string)$waste['weight_kg']) . ' Kg</td><td>' . h((string)$waste['created_at']) . '</td></tr>';
    }
    if ($chemicalInputs === [] && $wastes === []) {
        $body .= '<tr><td colspan="4" class="muted">Sin tintas ni mermas registradas.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    $body .= '</div>';

    $body .= '<div class="trace-grid">';
    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Cajas generadas</div><div class="table-wrap"><table><thead><tr><th>Código</th><th>Unidades</th><th>Pallet</th><th></th></tr></thead><tbody>';
    foreach ($boxes as $box) {
        $body .= '<tr><td>' . h((string)$box['box_code']) . '</td><td>' . h((string)$box['units_qty']) . '</td><td>' . h((string)($box['pallet_code'] ?? '-')) . '</td><td><a class="btn secondary" href="/boxes/' . (int)$box['id'] . '">Ver</a></td></tr>';
    }
    if ($boxes === []) {
        $body .= '<tr><td colspan="4" class="muted">Sin cajas generadas.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Pallets generados</div><div class="table-wrap"><table><thead><tr><th>Código</th><th>Cajas</th><th>Destino</th><th></th></tr></thead><tbody>';
    foreach ($pallets as $pallet) {
        $body .= '<tr><td>' . h((string)$pallet['pallet_code']) . '</td><td>' . h((string)$pallet['box_count']) . '</td><td>' . h((string)$pallet['destination_mode']) . '</td><td><a class="btn secondary" href="/pallets/' . (int)$pallet['id'] . '">Ver</a></td></tr>';
    }
    if ($pallets === []) {
        $body .= '<tr><td colspan="4" class="muted">Sin pallets generados.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    $body .= '</div>';

    render('Seguimiento OT', $body);
}

function renderProductionShiftSessionsScreen(
    array $machines,
    string $currentOperatorName,
    ?array $selectedMachine = null,
    ?array $currentShiftSession = null,
    ?string $flashMessage = null,
    bool $flashIsError = false
): void {
    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Iniciar / Terminar turno</div>
        </div>
        <div class="row">
          <a class="btn secondary" href="/">Inicio producción</a>
          <a class="btn secondary" href="/work-orders?view=active">OT en proceso</a>
        </div>
      </div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    if ($currentShiftSession !== null) {
        $body .= '<div class="card" style="margin-bottom:12px"><div style="font-weight:800;margin-bottom:8px">Terminar turno activo</div><div class="ot-meta-grid">';
        $body .= '<div class="ot-meta"><div class="muted">Operador</div><div class="value">' . h((string)($currentShiftSession['operator_name'] ?? $currentOperatorName)) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Tipo</div><div class="value">' . h((string)($currentShiftSession['machine_type_name'] ?? '-')) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Máquina</div><div class="value">' . h((string)($currentShiftSession['machine_name'] ?? '-')) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Etapa</div><div class="value">' . h(machineProcessStageLabel((string)($currentShiftSession['process_stage'] ?? ''))) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Turno</div><div class="value">' . h((string)($currentShiftSession['shift_label'] ?? '-')) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Inicio</div><div class="value">' . h((string)($currentShiftSession['started_at'] ?? '-')) . '</div></div>';
        $body .= '</div>';
        if (trim((string)($currentShiftSession['ot_code'] ?? '')) !== '') {
            $body .= '<div class="ok" style="margin-top:12px">Trabajando OT ' . h((string)$currentShiftSession['ot_code']) . ' en ' . h((string)($currentShiftSession['machine_name'] ?? '')) . '.</div>';
        }
        $body .= '<form method="post" action="/production/shifts/' . (int)$currentShiftSession['id'] . '/end" style="margin-top:12px">';
        $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
        $body .= '<button class="btn secondary" type="submit" style="min-width:220px">Terminar turno</button></form></div>';
    }

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Máquinas</div><div class="table-wrap"><table><thead><tr><th>Tipo</th><th>Máquina</th><th>Turno</th><th>Horario</th><th>Etapa</th><th>Opciones</th></tr></thead><tbody>';
    foreach ($machines as $machine) {
        $isActive = trim((string)($machine['session_status'] ?? '')) === 'ACTIVE';
        $turnLabel = $isActive ? shiftSessionStatusLabel((string)$machine['session_status']) : 'Sin turno';
        $timeLabel = $isActive ? (string)($machine['started_at'] ?? '-') : '-';
        $stageLabel = $isActive ? machineProcessStageLabel((string)($machine['process_stage'] ?? '')) : machineProcessStageLabel((string)($machine['production_area'] ?? $machine['machine_type_area'] ?? 'PRODUCTION'));
        $body .= '<tr><td>' . h((string)($machine['machine_type_name'] ?? '-')) . '</td><td>' . h((string)$machine['name']) . '</td><td>' . h($turnLabel) . '</td><td>' . h($timeLabel) . '</td><td>' . h($stageLabel) . '</td><td>';
        if ($isActive) {
            $body .= '<div class="muted" style="margin-bottom:6px">' . h((string)($machine['operator_name'] ?? '-')) . '</div>';
            if (trim((string)($machine['active_work_order_code'] ?? '')) !== '') {
                $body .= '<div class="muted" style="margin-bottom:6px">OT ' . h((string)$machine['active_work_order_code']) . '</div>';
            }
            $body .= '<span class="muted">Turno activo</span>';
        } else {
            $body .= '<form method="post" action="/production/shifts/start" style="margin:0">'
                . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                . '<input type="hidden" name="machine_id" value="' . (int)$machine['id'] . '">'
                . '<button class="btn" type="submit">Iniciar turno</button>'
                . '</form>';
        }
        $body .= '</td></tr>';
    }
    if ($machines === []) {
        $body .= '<tr><td colspan="6" class="muted">No hay máquinas configuradas todavía.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';

    render('Iniciar / Terminar turno', $body);
}


if ($path === '/production/shifts' && $method === 'GET') {
    $machines = $service->listProductionMachinesWithSessions();
    $selectedMachineId = isset($_GET['machine_id']) ? (int)$_GET['machine_id'] : 0;
    $selectedMachine = $selectedMachineId > 0 ? $service->getProductionMachine($selectedMachineId) : null;
    $currentShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['started']) && $_GET['started'] === '1') {
        $flashMessage = 'Turno iniciado correctamente.';
    } elseif (isset($_GET['ended']) && $_GET['ended'] === '1') {
        $flashMessage = 'Turno cerrado correctamente.';
    } elseif (isset($_GET['error']) && trim((string)$_GET['error']) !== '') {
        $flashMessage = trim((string)$_GET['error']);
        $flashIsError = true;
    }
    renderProductionShiftSessionsScreen($machines, $currentOperatorName, $selectedMachine, $currentShiftSession, $flashMessage, $flashIsError);
    exit;
}

if ($path === '/production/shifts/start' && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $result = $service->startShiftSession(
        (int)($_POST['machine_id'] ?? 0),
        $currentOperatorName,
        (string)($_POST['helper_name'] ?? ''),
        null,
        null,
        (string)($_POST['comments'] ?? '')
    );
    if (($result['ok'] ?? false) === true) {
        header('Location: /production/shifts?started=1');
        exit;
    }
    $error = trim((string)reset($result['errors']));
    header('Location: /production/shifts?machine_id=' . (int)($_POST['machine_id'] ?? 0) . '&error=' . rawurlencode($error !== '' ? $error : 'No se pudo iniciar el turno.'));
    exit;
}

if (preg_match('#^/production/shifts/(\d+)/end$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $result = $service->endShiftSession((int)$m[1], '', (string)($_POST['comments'] ?? ''));
    if (($result['ok'] ?? false) === true) {
        header('Location: /production/shifts?ended=1');
        exit;
    }
    $error = trim((string)reset($result['errors']));
    header('Location: /production/shifts?error=' . rawurlencode($error !== '' ? $error : 'No se pudo cerrar el turno.'));
    exit;
}

if ($path === '/purchase-orders' && $method === 'GET') {
    $supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
    $q = isset($_GET['q']) ? (string)$_GET['q'] : '';
    $status = isset($_GET['status']) ? (string)$_GET['status'] : 'active';
    $supplierType = isset($_GET['supplier_type']) ? strtoupper(trim((string)$_GET['supplier_type'])) : '';
    if (!in_array($supplierType, ['', 'ALL', 'NATIONAL', 'IMPORT'], true)) {
        $supplierType = '';
    }
    if ($supplierType === '' && $status === 'active') {
        $supplierType = 'NATIONAL';
    }
    $suppliers = $service->listSuppliersForPurchaseOrders($status, $supplierType);
    if ($supplierType === 'IMPORT') {
        $query = ['status=' . rawurlencode($status)];
        if ($supplierId > 0) {
            $query[] = 'supplier_id=' . $supplierId;
        }
        if ($q !== '') {
            $query[] = 'q=' . rawurlencode($q);
        }
        header('Location: /import-containers?' . implode('&', $query));
        exit;
    }
    $pos = $service->listPurchaseOrders($supplierId > 0 ? $supplierId : null, $q !== '' ? $q : null, $status, $supplierType);

    $pageTitle = 'Recepción por Orden de Compra';
    if ($status === 'complete') {
        $pageTitle = 'Recepciones finalizadas';
    } elseif ($supplierType === 'IMPORT') {
        $pageTitle = 'Importación';
    } elseif ($supplierType === 'NATIONAL') {
        $pageTitle = 'Recepción nacional';
    }

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">' . h($pageTitle) . '</div>
          <div class="muted">Buscar OC y recepcionar parcial o completa. Separación por proveedor nacional o importación según país de origen en ERP.</div>
        </div>
      </div>';

    $body .= '<div class="card" style="margin-bottom:12px">
        <form method="get" action="/purchase-orders">
          <input type="hidden" name="status" value="' . h($status) . '">
          <input type="hidden" name="supplier_type" value="' . h($supplierType) . '">
          <div class="row" style="align-items:end">
            <div style="flex:1;min-width:260px">
              <label>Proveedor</label>
              <select name="supplier_id">
                <option value="">Todos</option>';
    foreach ($suppliers as $s) {
        $selected = ((int)$s['id'] === $supplierId) ? ' selected' : '';
        $typeLabel = ((string)($s['supplier_type'] ?? 'NATIONAL')) === 'IMPORT' ? 'Importación' : 'Nacional';
        $countryLabel = trim((string)($s['country_name'] ?? ''));
        $optionLabel = (string)$s['name'] . ' [' . $typeLabel . ($countryLabel !== '' ? ' · ' . $countryLabel : '') . ']';
        $body .= '<option value="' . (int)$s['id'] . '"' . $selected . '>' . h($optionLabel) . '</option>';
    }
    $body .= '</select>
            </div>
            <div style="flex:1;min-width:260px">
              <label>Buscar OC</label>
              <input name="q" type="text" value="' . h($q) . '" placeholder="OC-10001">
            </div>
            <div style="min-width:160px">
              <button class="btn" type="submit">Filtrar</button>
            </div>
          </div>
        </form>
      </div>';

    $body .= '<div class="card"><table><thead><tr>
        <th>OC</th><th>Proveedor</th><th>Tipo</th><th>País</th><th>Estado</th><th>Avance</th><th>Fecha</th>
      </tr></thead><tbody>';
    foreach ($pos as $po) {
        $completedLines = (int)($po['completed_lines'] ?? 0);
        $totalLines = (int)($po['total_lines'] ?? 0);
        $typeLabel = ((string)($po['supplier_type'] ?? 'NATIONAL')) === 'IMPORT' ? 'Importación' : 'Nacional';
        $body .= '<tr>';
        $body .= '<td><a href="/purchase-orders/' . (int)$po['id'] . '">' . h((string)$po['po_code']) . '</a></td>';
        $body .= '<td>' . h((string)$po['supplier_name']) . '</td>';
        $body .= '<td>' . h($typeLabel) . '</td>';
        $body .= '<td>' . h((string)($po['supplier_country_name'] ?? '-')) . '</td>';
        $body .= '<td>' . h(receptionDocumentStatusLabel((string)$po['status'])) . '</td>';
        $body .= '<td>' . $completedLines . ' / ' . $totalLines . '</td>';
        $body .= '<td>' . h((string)$po['created_at']) . '</td>';
        $body .= '</tr>';
    }
    if ($pos === []) {
        $body .= '<tr><td colspan="7" class="muted">Sin resultados.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    render('Recepción OC', $body);
    exit;
}

if ($path === '/import-containers' && $method === 'GET') {
    $supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
    $q = isset($_GET['q']) ? (string)$_GET['q'] : '';
    $status = isset($_GET['status']) ? (string)$_GET['status'] : 'active';
    $suppliers = $service->listSuppliersForImportContainers($status);
    $containers = $service->listImportContainers($supplierId > 0 ? $supplierId : null, $q !== '' ? $q : null, $status);

    $pageTitle = $status === 'complete' ? 'Contenedores finalizados' : 'Importación';
    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">' . h($pageTitle) . '</div>
          <div class="muted">Selecciona por número de contenedor para recepcionar importaciones aunque un contenedor tenga varias OCs y una OC llegue en varios contenedores.</div>
        </div>
      </div>';

    $body .= '<div class="card" style="margin-bottom:12px">
        <form method="get" action="/import-containers">
          <input type="hidden" name="status" value="' . h($status) . '">
          <div class="row" style="align-items:end">
            <div style="flex:1;min-width:260px">
              <label>Proveedor importación</label>
              <select name="supplier_id">
                <option value="">Todos</option>';
    foreach ($suppliers as $s) {
        $selected = ((int)$s['id'] === $supplierId) ? ' selected' : '';
        $countryLabel = trim((string)($s['country_name'] ?? ''));
        $optionLabel = (string)$s['name'] . ($countryLabel !== '' ? ' [' . $countryLabel . ']' : '');
        $body .= '<option value="' . (int)$s['id'] . '"' . $selected . '>' . h($optionLabel) . '</option>';
    }
    $body .= '</select>
            </div>
            <div style="flex:1;min-width:260px">
              <label>Buscar contenedor</label>
              <input name="q" type="text" value="' . h($q) . '" placeholder="CONT-001 / BL / barco">
            </div>
            <div style="min-width:160px">
              <button class="btn" type="submit">Filtrar</button>
            </div>
          </div>
        </form>
      </div>';

    $body .= '<div class="card"><table><thead><tr>
        <th>Contenedor</th><th>BL</th><th>Embarcador</th><th>OCs</th><th>Estado</th><th>Avance</th><th>ETA planta</th>
      </tr></thead><tbody>';
    foreach ($containers as $container) {
        $body .= '<tr>';
        $body .= '<td><a href="/import-containers/' . (int)$container['id'] . '">' . h((string)($container['container_code'] ?: ('Contenedor #' . (int)$container['id']))) . '</a></td>';
        $body .= '<td>' . h((string)($container['bill_of_lading'] ?: '-')) . '</td>';
        $body .= '<td>' . h((string)($container['forwarder_name'] ?: '-')) . '</td>';
        $body .= '<td>' . h((string)($container['po_codes'] ?: '-')) . '</td>';
        $body .= '<td>' . h(receptionDocumentStatusLabel((string)$container['status'])) . '</td>';
        $body .= '<td>' . (int)($container['completed_lines'] ?? 0) . ' / ' . (int)($container['total_lines'] ?? 0) . '</td>';
        $body .= '<td>' . h((string)($container['eta_plant'] ?: '-')) . '</td>';
        $body .= '</tr>';
    }
    if ($containers === []) {
        $body .= '<tr><td colspan="7" class="muted">Sin contenedores.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    render('Importación', $body);
    exit;
}

if (preg_match('#^/import-containers/(\\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $containerId = (int)$m[1];
    $container = $service->getImportContainer($containerId);
    if ($container === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe el contenedor.</div>');
        exit;
    }
    $lines = $service->listImportContainerLines($containerId);

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Contenedor ' . h((string)($container['container_code'] ?: ('#' . $containerId))) . '</div>
          <div class="muted">OCs: ' . h((string)($container['po_codes'] ?: '-')) . ' · BL: ' . h((string)($container['bill_of_lading'] ?: '-')) . ' · Estado: ' . h(receptionDocumentStatusLabel((string)$container['status'])) . '</div>
        </div>
        <a class="btn secondary" href="/import-containers">Volver</a>
      </div>';

    $body .= '<div class="card" style="margin-bottom:12px">
        <div class="row">
          <div style="flex:1;min-width:220px"><div class="muted">Buque</div><div style="font-weight:800">' . h((string)($container['vessel_name'] ?: '-')) . '</div></div>
          <div style="flex:1;min-width:220px"><div class="muted">Embarcador</div><div style="font-weight:800">' . h((string)($container['forwarder_name'] ?: '-')) . '</div></div>
          <div style="flex:1;min-width:220px"><div class="muted">Incoterm</div><div style="font-weight:800">' . h((string)($container['incoterm'] ?: '-')) . '</div></div>
          <div style="flex:1;min-width:220px"><div class="muted">ETA planta</div><div style="font-weight:800">' . h((string)($container['eta_plant'] ?: '-')) . '</div></div>
        </div>
      </div>';

    $body .= '<div class="card"><table><thead><tr>
        <th>OC</th><th>SKU</th><th>Modo</th><th>Especificación</th><th>Ordenado cont.</th><th>Recibido cont.</th><th>Pendiente</th><th></th>
      </tr></thead><tbody>';
    foreach ($lines as $ln) {
        $lineSummary = receptionLineSummary($ln);
        $ordered = formatReceptionValue($lineSummary['ordered'], $lineSummary['unit']) . ' ' . $lineSummary['unit'];
        $received = formatReceptionValue($lineSummary['received'], $lineSummary['unit']) . ' ' . $lineSummary['unit'];
        $pending = formatReceptionValue($lineSummary['pending'], $lineSummary['unit']) . ' ' . $lineSummary['unit'];
        $specText = buildReceptionSpec($ln);

        $body .= '<tr>';
        $body .= '<td>' . h((string)$ln['po_code']) . '</td>';
        $body .= '<td>' . h((string)$ln['sku_code']) . '</td>';
        $body .= '<td>' . h(receptionModeLabel((string)$ln['reception_mode'])) . '</td>';
        $body .= '<td>' . $specText . '</td>';
        $body .= '<td>' . $ordered . '</td>';
        $body .= '<td>' . $received . '</td>';
        $body .= '<td>' . $pending . '</td>';
        if ((string)$container['status'] === 'COMPLETE') {
            $body .= '<td class="muted">Finalizada</td>';
        } elseif ($lineSummary['pending'] <= 0) {
            $body .= '<td class="muted">Completa</td>';
        } else {
            $body .= '<td><a class="btn secondary" href="/import-containers/' . $containerId . '/receive?line=' . (int)$ln['import_container_item_id'] . '">Recepcionar</a></td>';
        }
        $body .= '</tr>';
    }
    if ($lines === []) {
        $body .= '<tr><td colspan="8" class="muted">Sin líneas.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    render('Contenedor', $body);
    exit;
}

if (preg_match('#^/import-containers/(\\d+)/receive$#', $path, $m) === 1 && $method === 'GET') {
    $containerId = (int)$m[1];
    $container = $service->getImportContainer($containerId);
    if ($container === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe el contenedor.</div>');
        exit;
    }
    if ((string)$container['status'] === 'COMPLETE') {
        render('Recepción importación', '<div class="err">Esta recepción ya está finalizada y no permite agregar más.</div><div style="margin-top:12px"><a class="btn secondary" href="/import-containers/' . $containerId . '">Volver</a></div>');
        exit;
    }
    $containerItemId = isset($_GET['line']) ? (int)$_GET['line'] : 0;
    $line = $service->getImportContainerLine($containerItemId);
    if ($line === null || (int)($line['import_container_id'] ?? 0) !== $containerId) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la línea de contenedor.</div>');
        exit;
    }

    $warehouses = $service->listWarehousesForReception();
    $recent = $service->listRollsByPurchaseOrderLine((int)$line['id'], 10, (int)$line['import_container_item_id']);
    $lineSummary = receptionLineSummary($line);
    $isWeightMode = $lineSummary['mode'] === 'WEIGHT';
    $lineUnit = $lineSummary['unit'];
    $pendingInputMax = $isWeightMode ? number_format((float)$lineSummary['pending'], 3, '.', '') : '';

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Recepcionar producto importado</div>
        <a class="btn secondary" href="/import-containers/' . $containerId . '">Volver</a>
      </div>';

    $body .= '<div class="card" style="margin-bottom:12px">
        <div class="row">
          <div style="flex:1;min-width:220px"><div class="muted">Contenedor</div><div style="font-weight:800">' . h((string)($line['container_code'] ?: ('#' . $containerId))) . '</div></div>
          <div style="flex:1;min-width:220px"><div class="muted">OC</div><div style="font-weight:800">' . h((string)$line['po_code']) . '</div></div>
          <div style="flex:1;min-width:260px"><div class="muted">Proveedor</div><div style="font-weight:800">' . h((string)$line['supplier_name']) . '</div></div>
          <div style="flex:1;min-width:260px"><div class="muted">SKU</div><div style="font-weight:800">' . h((string)$line['sku_code']) . ' - ' . h((string)$line['sku_description']) . '</div></div>
        </div>
        <div class="row" style="margin-top:10px">
          <div style="flex:1;min-width:180px"><div class="muted">Recepción</div><div style="font-weight:800">' . h(receptionModeLabel((string)$line['reception_mode'])) . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Ordenado contenedor</div><div style="font-weight:800">' . h(formatReceptionValue($lineSummary['ordered'], $lineUnit)) . ' ' . h($lineUnit) . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Recibido contenedor</div><div style="font-weight:800">' . h(formatReceptionValue($lineSummary['received'], $lineUnit)) . ' ' . h($lineUnit) . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Pendiente</div><div style="font-weight:800">' . h(formatReceptionValue($lineSummary['pending'], $lineUnit)) . ' ' . h($lineUnit) . '</div></div>
        </div>
      </div>';

    $body .= '<div class="card">
        <form method="post" id="receive_form" action="/import-containers/' . $containerId . '/receive">
          <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
          <input type="hidden" name="import_container_item_id" value="' . (int)$containerItemId . '">
          <input type="hidden" name="reception_mode" id="reception_mode" value="' . h((string)$lineSummary['mode']) . '">
          <input type="hidden" id="received_qty" name="received_qty" value="1">
          <input type="hidden" id="server_print_enabled" value="' . ($printer->isEnabled() ? '1' : '0') . '">
          <div class="row" style="align-items:end">
            <div style="flex:1;min-width:220px">
              <label>Operador</label>
              <div class="panel" style="padding:10px 12px;font-weight:700">' . h($currentOperatorName) . '</div>
            </div>
            <div style="flex:1;min-width:220px">
              <label>Modo recepción</label>
              <select id="reception_mode_selector">
                <option value="WEIGHT"' . ($isWeightMode ? ' selected' : '') . '>Por peso</option>
                <option value="QUANTITY"' . (!$isWeightMode ? ' selected' : '') . '>Por unidades</option>
              </select>
            </div>
            <div style="flex:1;min-width:220px">
              <label>Bodega</label>
              <select name="warehouse_id" required>
                <option value="">Seleccionar</option>';
    foreach ($warehouses as $w) {
        $body .= '<option value="' . (int)$w['id'] . '">' . h((string)$w['code']) . ' - ' . h((string)$w['name']) . '</option>';
    }
    $body .= '</select>
            </div>
            <div id="weight_field_wrapper" style="flex:1;min-width:220px">
              <label id="primary_value_label">Peso real (Kg)</label>
              <input id="weight_kg" name="weight_kg" type="number" step="0.001" min="0" value=""' . ($pendingInputMax !== '' ? ' max="' . h($pendingInputMax) . '"' : '') . '>
            </div>
            <div id="qty_field_wrapper" style="flex:1;min-width:220px">
              <label id="qty_value_label">Cantidad recibida (Unid.)</label>
              <input id="received_qty_input" type="number" step="0.001" min="0.001" value="1">
            </div>
            <div id="action_buttons_wrapper" style="flex:0 0 ' . ($isWeightMode ? '300px' : '190px') . ';display:flex;flex-direction:row;gap:8px;align-items:flex-end;justify-content:flex-end;flex-wrap:nowrap">';
    $body .= '<button class="btn secondary" type="button" id="toggle_scale"' . (!$isWeightMode ? ' style="display:none"' : '') . '>Activar balanza</button>
              <button class="btn" type="button" id="save_print"' . (!$isWeightMode ? ' style="display:none"' : '') . ' disabled>Guardar e imprimir</button>
              <button class="btn" type="submit" id="submit_primary"' . ($isWeightMode ? ' style="display:none"' : '') . '>Guardar e imprimir</button></div>
          </div>
          <div class="row" id="scale_status_row" style="margin-top:6px' . ($isWeightMode ? '' : ';display:none') . '">
            <div style="flex:1;min-width:220px"></div>
            <div style="flex:1;min-width:220px"></div>
            <div style="flex:1;min-width:220px"><div class="muted" id="scale_status">Balanza: inactiva · Máximo pendiente ' . h(formatReceptionValue((float)$lineSummary['pending'], $lineUnit)) . ' ' . h($lineUnit) . '</div></div>
            <div id="scale_status_spacer" style="flex:0 0 ' . ($isWeightMode ? '300px' : '190px') . '"></div>
          </div>

          <div class="panel" style="margin-top:12px">
            <div style="font-weight:800;margin-bottom:6px">Especificación</div>
            <div class="row">
              <div style="flex:1;min-width:160px"><label>Gramos</label><input type="text" value="' . h((string)($line['grams'] ?? '')) . '" disabled></div>
              <div style="flex:1;min-width:160px"><label>Ancho (mm)</label><input type="text" value="' . h((string)($line['width_mm'] ?? '')) . '" disabled></div>
              <div style="flex:1;min-width:220px"><label>Color</label><input type="text" value="' . h((string)($line['color'] ?? '')) . '" disabled></div>
              <div style="flex:1;min-width:220px"><label>Metros lineales</label><input type="text" value="' . h((string)($line['meters'] ?? '')) . '" disabled></div>
            </div>
          </div>

        </form>
      </div>';

    $body .= '<div class="card" style="margin-top:12px">
        <div style="font-weight:800;margin-bottom:8px">Últimas recepciones del contenedor</div>
        <table><thead><tr><th>Código</th><th>Bodega</th><th>Unidades</th><th>Peso (Kg)</th><th>Fecha</th><th></th></tr></thead><tbody id="recent_tbody">';
    foreach ($recent as $r) {
        $recentValue = formatReceptionValue((float)($r['received_qty'] ?? 1), 'Unid.') . ' Unid.';
        $recentWeight = formatReceptionValue((float)($r['weight_kg'] ?? 0), 'Kg');
        $body .= '<tr>';
        $body .= '<td>' . h((string)$r['roll_code']) . '</td>';
        $body .= '<td>' . h((string)$r['warehouse_code']) . '</td>';
        $body .= '<td>' . h($recentValue) . '</td>';
        $body .= '<td>' . h($recentWeight) . '</td>';
        $body .= '<td>' . h((string)$r['created_at']) . '</td>';
        $body .= '<td><a class="btn secondary" href="/rolls/' . (int)$r['id'] . '/label?auto_print=1" target="_blank" rel="noopener">Etiqueta</a></td>';
        $body .= '</tr>';
    }
    if ($recent === []) {
        $body .= '<tr><td colspan="6" class="muted">Sin recepciones aún.</td></tr>';
    }
    $body .= '</tbody></table></div>';
    $body .= '<div class="card" style="margin-top:12px">
        <div style="font-weight:800;margin-bottom:8px">Vista previa última etiqueta</div>
        <iframe id="label_preview" title="Vista previa etiqueta" style="width:100%;height:420px;border:1px solid #e5e7eb;border-radius:8px;background:#fff"></iframe>
      </div>';

    $body .= '<script>
      (function () {
        var form = document.getElementById("receive_form");
        var modeField = document.getElementById("reception_mode");
        var modeSelector = document.getElementById("reception_mode_selector");
        var weight = document.getElementById("weight_kg");
        var qty = document.getElementById("received_qty");
        var qtyInput = document.getElementById("received_qty_input");
        var toggle = document.getElementById("toggle_scale");
        var saveBtn = document.getElementById("save_print");
        var submitPrimary = document.getElementById("submit_primary");
        var actionButtonsWrapper = document.getElementById("action_buttons_wrapper");
        var weightFieldWrapper = document.getElementById("weight_field_wrapper");
        var qtyFieldWrapper = document.getElementById("qty_field_wrapper");
        var primaryValueLabel = document.getElementById("primary_value_label");
        var qtyValueLabel = document.getElementById("qty_value_label");
        var scaleStatusRow = document.getElementById("scale_status_row");
        var scaleStatusSpacer = document.getElementById("scale_status_spacer");
        var status = document.getElementById("scale_status");
        var tbody = document.getElementById("recent_tbody");
        var preview = document.getElementById("label_preview");
        if (!form || !modeField || !weight || !qty || !qtyInput || !toggle || !saveBtn || !status) return;

        var active = false;
        var timer = null;
        var last = null;
        var stable = 0;
        var lastSaved = null;
        var saving = false;
        var armed = true;
        var printWindow = null;
        var printQueue = [];
        var printing = false;
        var printTimer = null;
        var lastPrintedAt = 0;
        var lastPrintedWeight = null;
        var TRIGGER_ON_KG = 0.100;
        var TRIGGER_OFF_KG = 0.050;
        var MIN_INTERVAL_MS = 2000;
        var REARM_DELTA_KG = 0.500;
        var serverPrintEnabled = (function(){
          var el = document.getElementById("server_print_enabled");
          return !!(el && String(el.value) === "1");
        })();

        function setStatus(text) { status.textContent = text; }

        function enqueuePrint(url) {
          if (!active) return;
          if (serverPrintEnabled) return;
          printQueue.push(url);
          if (!printing) processPrintQueue();
        }

        function getWarehouseSelect() {
          return form.querySelector("select[name=warehouse_id]");
        }

        function getMode() { return modeField.value === "WEIGHT" ? "WEIGHT" : "QUANTITY"; }

        function syncQtyValue() {
          qty.value = qtyInput.value && Number(qtyInput.value) > 0 ? qtyInput.value : "1";
        }

        function canManualWeightSave() {
          var wh = getWarehouseSelect();
          var weightValue = Number(weight.value);
          return getMode() === "WEIGHT"
            && !!(wh && wh.value)
            && Number.isFinite(weightValue)
            && weightValue >= TRIGGER_ON_KG;
        }

        function syncWeightSaveButton() {
          if (!saveBtn) return;
          if (active) {
            return;
          }
          saveBtn.disabled = !canManualWeightSave();
        }

        function stopScale() {
          active = false;
          toggle.textContent = "Activar balanza";
          syncWeightSaveButton();
          if (timer) clearInterval(timer);
          timer = null;
          if (printTimer) clearTimeout(printTimer);
          printTimer = null;
          printQueue = [];
          printing = false;
          try { if (printWindow && !printWindow.closed) { printWindow.close(); } } catch (e) {}
        }

        function applyModeUI() {
          if (modeSelector) modeSelector.value = getMode();
          weight.step = "0.001";
          weight.required = true;
          if (weightFieldWrapper) weightFieldWrapper.style.display = "";
          if (getMode() === "WEIGHT") {
            qty.value = "1";
            qtyInput.value = "1";
            if (primaryValueLabel) primaryValueLabel.textContent = "Peso real (Kg)";
            if (qtyFieldWrapper) qtyFieldWrapper.style.display = "none";
            if (toggle) toggle.style.display = "";
            if (saveBtn) saveBtn.style.display = "";
            if (submitPrimary) submitPrimary.style.display = "none";
            if (scaleStatusRow) scaleStatusRow.style.display = "";
            if (actionButtonsWrapper) actionButtonsWrapper.style.flexBasis = "300px";
            if (scaleStatusSpacer) scaleStatusSpacer.style.flexBasis = "300px";
            if (!active) {
              setStatus(canManualWeightSave() ? "Listo para guardar e imprimir 1 unidad" : "Ingresa peso manual o activa balanza");
            }
          } else {
            syncQtyValue();
            if (primaryValueLabel) primaryValueLabel.textContent = "Peso real de la recepción (Kg)";
            if (qtyValueLabel) qtyValueLabel.textContent = "Cantidad recibida (Unid.)";
            if (qtyFieldWrapper) qtyFieldWrapper.style.display = "";
            if (toggle) toggle.style.display = "none";
            if (saveBtn) saveBtn.style.display = "none";
            if (submitPrimary) submitPrimary.style.display = "";
            if (scaleStatusRow) scaleStatusRow.style.display = "none";
            if (actionButtonsWrapper) actionButtonsWrapper.style.flexBasis = "190px";
            if (scaleStatusSpacer) scaleStatusSpacer.style.flexBasis = "190px";
            setStatus("Ingresa unidades y peso real para guardar la recepción");
          }
          syncWeightSaveButton();
        }

        function processPrintQueue() {
          if (!active || serverPrintEnabled) {
            printQueue = [];
            printing = false;
            return;
          }
          if (printQueue.length === 0) {
            printing = false;
            return;
          }
          printing = true;
          var url = printQueue.shift();
          if (printWindow && !printWindow.closed) {
            printWindow.location = url;
            try { printWindow.focus(); } catch (e) {}
          } else {
            printWindow = window.open(url, "label_print");
          }
          if (printTimer) clearTimeout(printTimer);
          printTimer = setTimeout(processPrintQueue, 2500);
        }

        async function poll() {
          if (getMode() !== "WEIGHT") {
            return;
          }
          try {
            var res = await fetch("/api/scale/weight", { cache: "no-store" });
            var data = await res.json();
            if (!data || data.ok !== true) throw new Error((data && data.error) ? data.error : "No se pudo leer la balanza.");
            var w = Number(data.weight_kg);
            if (!Number.isFinite(w)) throw new Error("Lectura inválida.");
            weight.value = w.toFixed(3);

            if (last !== null && Math.abs(w - last) < 0.005) stable++; else stable = 0;
            last = w;

            var wh = getWarehouseSelect();
            var hasWarehouse = !!(wh && wh.value);

            if (w < TRIGGER_OFF_KG) {
              armed = true;
              lastPrintedWeight = null;
              saveBtn.disabled = true;
              setStatus("Balanza: activa (lista)");
              return;
            }

            if (w >= TRIGGER_ON_KG && !hasWarehouse) {
              saveBtn.disabled = true;
              setStatus("Balanza: activa (selecciona bodega)");
              return;
            }

            saveBtn.disabled = true;
            var intervalOk = (Date.now() - lastPrintedAt) >= MIN_INTERVAL_MS;
            var deltaOk = (lastPrintedWeight !== null) && (Math.abs(w - lastPrintedWeight) >= REARM_DELTA_KG);

            if (!armed && deltaOk) {
              armed = true;
            }

            if (!saving && armed && hasWarehouse && w >= TRIGGER_ON_KG) {
              if (intervalOk) {
                armed = false;
                setStatus("Balanza: activa (guardando...)");
                saveAndPrint(w);
                return;
              }
              setStatus("Balanza: activa (imprimiendo...)");
              return;
            }

            if (!armed) {
              setStatus("Balanza: activa (bulto presente)");
              return;
            }
            setStatus("Balanza: activa");
          } catch (e) {
            setStatus("Balanza: error");
            saveBtn.disabled = true;
          }
        }

        async function saveAndPrint(weightOverride) {
          if (saving) return;
          var wh = getWarehouseSelect();
          var containerItem = form.querySelector("input[name=import_container_item_id]");
          var csrf = form.querySelector("input[name=_csrf]");
          if (!wh || !containerItem || !csrf) return;
          if (!wh.value) { alert("Selecciona la bodega."); return; }
          if (getMode() !== "WEIGHT") return;

          var weightToSave = (weightOverride !== undefined && weightOverride !== null) ? Number(weightOverride) : Number(weight.value);
          if (!Number.isFinite(weightToSave) || weightToSave < TRIGGER_ON_KG) {
            setStatus("Balanza: activa (peso inválido)");
            return;
          }
          weight.value = weightToSave.toFixed(3);

          saving = true;
          saveBtn.disabled = true;
          saveBtn.textContent = "Guardando...";
          try {
            var params = new URLSearchParams();
            params.set("_csrf", csrf.value);
            params.set("import_container_item_id", containerItem.value);
            params.set("reception_mode", getMode());
            params.set("warehouse_id", wh.value);
            params.set("weight_kg", weightToSave.toFixed(3));
            params.set("received_qty", qty.value || "1");
            var res = await fetch("/api/receptions/receive", {
              method: "POST",
              headers: { "Content-Type": "application/x-www-form-urlencoded" },
              body: params.toString()
            });
            var data = await res.json();
            if (!data || data.ok !== true) {
              if (data && data.errors && typeof data.errors === "object") {
                var containerErr = data.errors.import_container_item_id ? String(data.errors.import_container_item_id) : "";
                if (containerErr.indexOf("no permite") >= 0 || containerErr.indexOf("completa") >= 0 || containerErr.indexOf("finalizada") >= 0) {
                  setStatus("Recepción completa (pausada)");
                  active = false;
                  toggle.textContent = "Activar balanza";
                  saveBtn.disabled = true;
                  if (timer) clearInterval(timer);
                  timer = null;
                  return;
                }
              }
              var msg = (data && data.errors) ? Object.values(data.errors).join("\\n") : "No se pudo guardar.";
              throw new Error(msg);
            }
            var labelHref = "";
            try { labelHref = new URL(data.label_url, window.location.origin).toString(); } catch (e) { labelHref = data.label_url; }
            lastSaved = weightToSave;
            lastPrintedAt = Date.now();
            lastPrintedWeight = weightToSave;
            setStatus("Balanza: activa (retira la bobina)");

            if (tbody) {
              var tr = document.createElement("tr");
              var now = new Date();
              var whLabel = wh.options[wh.selectedIndex] ? wh.options[wh.selectedIndex].text : "";
              var whCode = whLabel.split(" - ")[0] || whLabel;
              var dt = now.toISOString().slice(0, 19).replace("T", " ");

              var td1 = document.createElement("td");
              td1.textContent = "#" + data.id;
              var td2 = document.createElement("td");
              td2.textContent = whCode;
              var td3 = document.createElement("td");
              td3.textContent = "1 Unid.";
              var td4 = document.createElement("td");
              td4.textContent = weightToSave.toFixed(3);
              var td5 = document.createElement("td");
              td5.textContent = dt;
              var td6 = document.createElement("td");
              var a = document.createElement("a");
              a.className = "btn secondary";
              a.target = "_blank";
              a.rel = "noopener";
              a.href = labelHref;
              a.textContent = "Etiqueta";
              td6.appendChild(a);

              tr.appendChild(td1);
              tr.appendChild(td2);
              tr.appendChild(td3);
              tr.appendChild(td4);
              tr.appendChild(td5);
              tr.appendChild(td5);
              tr.appendChild(td6);
              tbody.prepend(tr);
            }

            if (preview) {
              preview.src = labelHref;
            }

            if (data.printed === true) {
              setStatus("Balanza: activa (impreso)");
            } else {
              if (active) {
                enqueuePrint(labelHref);
              } else if (labelHref) {
                window.open(labelHref, "_blank", "noopener");
                setStatus("Recepción guardada e impresión lista");
              }
            }
          } catch (e) {
            alert(e && e.message ? e.message : "Error guardando.");
          } finally {
            saving = false;
            saveBtn.textContent = "Guardar e imprimir";
            syncWeightSaveButton();
          }
        }

        toggle.addEventListener("click", function () {
          var wh = getWarehouseSelect();
          active = !active;
          if (active) {
            if (!wh || !wh.value) {
              active = false;
              setStatus("Balanza: selecciona la bodega antes de activar");
              alert("Selecciona la bodega antes de activar la balanza.");
              return;
            }
            toggle.textContent = "Desactivar balanza";
            setStatus("Balanza: activa");
            armed = true;
            stable = 0;
            last = null;
            lastPrintedWeight = null;
            try {
              if (!serverPrintEnabled && !printing && (printQueue.length === 0)) {
                printWindow = window.open("about:blank", "label_print");
              }
            } catch (e) {
              printWindow = null;
            }
            poll();
            timer = setInterval(poll, 1000);
          } else {
            stopScale();
            setStatus("Balanza: inactiva");
          }
        });

        var whSelect = getWarehouseSelect();
        if (whSelect) {
          whSelect.addEventListener("change", function () {
            if (!active) {
              if (!whSelect.value) {
                setStatus("Balanza: selecciona bodega");
              } else if (canManualWeightSave()) {
                setStatus("Listo para guardar e imprimir 1 unidad");
              } else {
                setStatus("Ingresa peso manual o activa balanza");
              }
            }
            syncWeightSaveButton();
          });
          if (!whSelect.value) {
            setStatus("Balanza: selecciona bodega");
          }
        }

        weight.addEventListener("input", function () {
          if (getMode() === "WEIGHT" && !active) {
            setStatus(canManualWeightSave() ? "Listo para guardar e imprimir 1 unidad" : "Ingresa peso manual o activa balanza");
          }
          syncWeightSaveButton();
        });
        if (modeSelector) {
          modeSelector.addEventListener("change", function () {
            modeField.value = modeSelector.value === "WEIGHT" ? "WEIGHT" : "QUANTITY";
            stopScale();
            applyModeUI();
          });
        }
        qtyInput.addEventListener("input", function () {
          syncQtyValue();
        });
        form.addEventListener("submit", function () {
          if (getMode() === "WEIGHT") {
            qty.value = "1";
          } else {
            syncQtyValue();
          }
        });
        modeField.value = modeField.value === "WEIGHT" ? "WEIGHT" : "QUANTITY";
        applyModeUI();
        saveBtn.addEventListener("click", function () { saveAndPrint(); });
      })();
    </script>';

    render('Recepción importación', $body);
    exit;
}

if (preg_match('#^/import-containers/(\\d+)/receive$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $containerId = (int)$m[1];
    $containerItemId = isset($_POST['import_container_item_id']) ? (int)$_POST['import_container_item_id'] : 0;
    $warehouseId = isset($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : 0;
    $weight = isset($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : 0.0;
    $receivedQty = isset($_POST['received_qty']) ? (float)$_POST['received_qty'] : 1.0;
    $receptionMode = isset($_POST['reception_mode']) ? (string)$_POST['reception_mode'] : 'QUANTITY';
    $line = $service->getImportContainerLine($containerItemId);
    if ($line === null || (int)($line['import_container_id'] ?? 0) !== $containerId) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la línea de contenedor.</div>');
        exit;
    }

    $result = $service->createRollFromImportContainerLine($containerItemId, $warehouseId, $weight, $currentOperatorName, $receivedQty, $receptionMode);
    if ($result['ok'] === true) {
        header('Location: /import-containers/' . $containerId);
        exit;
    }

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Recepcionar bobina importada</div>
        <a class="btn secondary" href="/import-containers/' . $containerId . '">Volver</a>
      </div>';
    $body .= '<div class="err" style="margin-bottom:12px"><ul style="margin:0;padding-left:16px">';
    foreach ($result['errors'] as $msg) {
        $body .= '<li>' . h((string)$msg) . '</li>';
    }
    $body .= '</ul></div>';
    $body .= '<div class="card"><a class="btn secondary" href="/import-containers/' . $containerId . '">Volver</a></div>';
    render('Recepción importación', $body);
    exit;
}

if (preg_match('#^/purchase-orders/(\\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $poId = (int)$m[1];
    $po = $service->getPurchaseOrder($poId);
    if ($po === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OC.</div>');
        exit;
    }
    $lines = $service->listPurchaseOrderLines($poId);

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">OC ' . h((string)$po['po_code']) . '</div>
          <div class="muted">Proveedor: ' . h((string)$po['supplier_name']) . ' · Tipo: ' . h(((string)($po['supplier_type'] ?? 'NATIONAL')) === 'IMPORT' ? 'Importación' : 'Nacional') . ' · País: ' . h((string)($po['supplier_country_name'] ?? '-')) . ' · Estado: ' . h(receptionDocumentStatusLabel((string)$po['status'])) . '</div>
        </div>
        <a class="btn secondary" href="/purchase-orders">Volver</a>
      </div>';

    $body .= '<div class="card"><table><thead><tr>
        <th>SKU</th><th>Modo</th><th>Especificación</th><th>Ordenado</th><th>Recibido</th><th>Pendiente</th><th></th>
      </tr></thead><tbody>';
    foreach ($lines as $ln) {
        $lineSummary = receptionLineSummary($ln);
        $ordered = formatReceptionValue($lineSummary['ordered'], $lineSummary['unit']) . ' ' . $lineSummary['unit'];
        $received = formatReceptionValue($lineSummary['received'], $lineSummary['unit']) . ' ' . $lineSummary['unit'];
        $pending = formatReceptionValue($lineSummary['pending'], $lineSummary['unit']) . ' ' . $lineSummary['unit'];
        $specText = buildReceptionSpec($ln);

        $body .= '<tr>';
        $body .= '<td>' . h((string)$ln['sku_code']) . '</td>';
        $body .= '<td>' . h(receptionModeLabel((string)$ln['reception_mode'])) . '</td>';
        $body .= '<td>' . $specText . '</td>';
        $body .= '<td>' . $ordered . '</td>';
        $body .= '<td>' . $received . '</td>';
        $body .= '<td>' . $pending . '</td>';
        if ((string)$po['status'] === 'COMPLETE') {
            $body .= '<td class="muted">Finalizada</td>';
        } elseif ($lineSummary['pending'] <= 0) {
            $body .= '<td class="muted">Completa</td>';
        } else {
            $body .= '<td><a class="btn secondary" href="/purchase-orders/' . (int)$poId . '/receive?line=' . (int)$ln['id'] . '">Recepcionar</a></td>';
        }
        $body .= '</tr>';
    }
    if ($lines === []) {
        $body .= '<tr><td colspan="7" class="muted">Sin líneas.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    render('OC', $body);
    exit;
}

if (preg_match('#^/purchase-orders/(\\d+)/receive$#', $path, $m) === 1 && $method === 'GET') {
    $poId = (int)$m[1];
    $po = $service->getPurchaseOrder($poId);
    if ($po === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OC.</div>');
        exit;
    }
    if ((string)$po['status'] === 'COMPLETE') {
        render('Recepción OC', '<div class="err">Esta recepción ya está finalizada y no permite agregar más.</div><div style="margin-top:12px"><a class="btn secondary" href="/purchase-orders/' . (int)$poId . '">Volver</a></div>');
        exit;
    }
    $lineId = isset($_GET['line']) ? (int)$_GET['line'] : 0;
    $line = $service->getPurchaseOrderLine($lineId);
    if ($line === null || (int)$line['purchase_order_id'] !== $poId) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la línea de OC.</div>');
        exit;
    }

    $warehouses = $service->listWarehousesForReception();
    $recent = $service->listRollsByPurchaseOrderLine($lineId, 10);
    $lineSummary = receptionLineSummary($line);
    $isWeightMode = $lineSummary['mode'] === 'WEIGHT';
    $lineUnit = $lineSummary['unit'];
    $pendingInputMax = $isWeightMode ? number_format((float)$lineSummary['pending'], 3, '.', '') : '';

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Recepcionar producto</div>
        <a class="btn secondary" href="/purchase-orders/' . (int)$poId . '">Volver</a>
      </div>';

    $body .= '<div class="card" style="margin-bottom:12px">
        <div class="row">
          <div style="flex:1;min-width:260px"><div class="muted">OC</div><div style="font-weight:800">' . h((string)$line['po_code']) . '</div></div>
          <div style="flex:1;min-width:260px"><div class="muted">Proveedor</div><div style="font-weight:800">' . h((string)$line['supplier_name']) . '</div></div>
          <div style="flex:1;min-width:260px"><div class="muted">SKU</div><div style="font-weight:800">' . h((string)$line['sku_code']) . ' - ' . h((string)$line['sku_description']) . '</div></div>
          <div style="flex:1;min-width:220px"><div class="muted">Recepción</div><div style="font-weight:800">' . h(receptionModeLabel((string)$line['reception_mode'])) . '</div></div>
        </div>
        <div class="row" style="margin-top:10px">
          <div style="flex:1;min-width:180px"><div class="muted">Ordenado</div><div style="font-weight:800">' . h(formatReceptionValue($lineSummary['ordered'], $lineUnit)) . ' ' . h($lineUnit) . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Recibido</div><div style="font-weight:800">' . h(formatReceptionValue($lineSummary['received'], $lineUnit)) . ' ' . h($lineUnit) . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Pendiente</div><div style="font-weight:800">' . h(formatReceptionValue($lineSummary['pending'], $lineUnit)) . ' ' . h($lineUnit) . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Tipo proveedor</div><div style="font-weight:800">' . h(((string)($line['supplier_type'] ?? 'NATIONAL')) === 'IMPORT' ? 'Importación' : 'Nacional') . '</div></div>
        </div>
      </div>';

    $body .= '<div class="card">
        <form method="post" id="receive_form" action="/purchase-orders/' . (int)$poId . '/receive">
          <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
          <input type="hidden" name="purchase_order_line_id" value="' . (int)$lineId . '">
          <input type="hidden" name="reception_mode" id="reception_mode" value="' . h((string)$lineSummary['mode']) . '">
          <input type="hidden" id="received_qty" name="received_qty" value="1">
          <input type="hidden" id="server_print_enabled" value="' . ($printer->isEnabled() ? '1' : '0') . '">
          <div class="row" style="align-items:end">
            <div style="flex:1;min-width:220px">
              <label>Operador</label>
              <div class="panel" style="padding:10px 12px;font-weight:700">' . h($currentOperatorName) . '</div>
            </div>
            <div style="flex:1;min-width:220px">
              <label>Modo recepción</label>
              <select id="reception_mode_selector">
                <option value="WEIGHT"' . ($isWeightMode ? ' selected' : '') . '>Por peso</option>
                <option value="QUANTITY"' . (!$isWeightMode ? ' selected' : '') . '>Por unidades</option>
              </select>
            </div>
            <div style="flex:1;min-width:220px">
              <label>Bodega</label>
              <select name="warehouse_id" required>
                <option value="">Seleccionar</option>';
    foreach ($warehouses as $w) {
        $body .= '<option value="' . (int)$w['id'] . '">' . h((string)$w['code']) . ' - ' . h((string)$w['name']) . '</option>';
    }
    $body .= '</select>
            </div>
            <div id="weight_field_wrapper" style="flex:1;min-width:220px">
              <label id="primary_value_label">Peso real (Kg)</label>
              <input id="weight_kg" name="weight_kg" type="number" step="0.001" min="0" value=""' . ($pendingInputMax !== '' ? ' max="' . h($pendingInputMax) . '"' : '') . '>
            </div>
            <div id="qty_field_wrapper" style="flex:1;min-width:220px">
              <label id="qty_value_label">Cantidad recibida (Unid.)</label>
              <input id="received_qty_input" type="number" step="0.001" min="0.001" value="1">
            </div>
            <div id="action_buttons_wrapper" style="flex:0 0 ' . ($isWeightMode ? '300px' : '190px') . ';display:flex;flex-direction:row;gap:8px;align-items:flex-end;justify-content:flex-end;flex-wrap:nowrap">';
    $body .= '<button class="btn secondary" type="button" id="toggle_scale"' . (!$isWeightMode ? ' style="display:none"' : '') . '>Activar balanza</button>
              <button class="btn" type="button" id="save_print"' . (!$isWeightMode ? ' style="display:none"' : '') . ' disabled>Guardar e imprimir</button>
              <button class="btn" type="submit" id="submit_primary"' . ($isWeightMode ? ' style="display:none"' : '') . '>Guardar e imprimir</button></div>
          </div>
          <div class="row" id="scale_status_row" style="margin-top:6px' . ($isWeightMode ? '' : ';display:none') . '">
            <div style="flex:1;min-width:220px"></div>
            <div style="flex:1;min-width:220px"></div>
            <div style="flex:1;min-width:220px"><div class="muted" id="scale_status">Balanza: inactiva · Máximo pendiente ' . h(formatReceptionValue((float)$lineSummary['pending'], $lineUnit)) . ' ' . h($lineUnit) . '</div></div>
            <div id="scale_status_spacer" style="flex:0 0 ' . ($isWeightMode ? '300px' : '190px') . '"></div>
          </div>

          <div class="panel" style="margin-top:12px">
            <div style="font-weight:800;margin-bottom:6px">Especificación</div>
            <div class="row">
              <div style="flex:1;min-width:160px"><label>Gramos</label><input type="text" value="' . h((string)($line['grams'] ?? '')) . '" disabled></div>
              <div style="flex:1;min-width:160px"><label>Ancho (mm)</label><input type="text" value="' . h((string)($line['width_mm'] ?? '')) . '" disabled></div>
              <div style="flex:1;min-width:220px"><label>Color</label><input type="text" value="' . h((string)($line['color'] ?? '')) . '" disabled></div>
              <div style="flex:1;min-width:220px"><label>Metros lineales</label><input type="text" value="' . h((string)($line['meters'] ?? '')) . '" disabled></div>
            </div>
          </div>

        </form>
      </div>';

    $body .= '<div class="card" style="margin-top:12px">
        <div style="font-weight:800;margin-bottom:8px">Últimas recepciones</div>
        <table><thead><tr><th>Código</th><th>Bodega</th><th>Unidades</th><th>Peso (Kg)</th><th>Fecha</th><th></th></tr></thead><tbody id="recent_tbody">';
    foreach ($recent as $r) {
        $recentValue = formatReceptionValue((float)($r['received_qty'] ?? 1), 'Unid.') . ' Unid.';
        $recentWeight = formatReceptionValue((float)($r['weight_kg'] ?? 0), 'Kg');
        $body .= '<tr>';
        $body .= '<td>' . h((string)$r['roll_code']) . '</td>';
        $body .= '<td>' . h((string)$r['warehouse_code']) . '</td>';
        $body .= '<td>' . h($recentValue) . '</td>';
        $body .= '<td>' . h($recentWeight) . '</td>';
        $body .= '<td>' . h((string)$r['created_at']) . '</td>';
        $body .= '<td><a class="btn secondary" href="/rolls/' . (int)$r['id'] . '/label?auto_print=1" target="_blank" rel="noopener">Etiqueta</a></td>';
        $body .= '</tr>';
    }
    if ($recent === []) {
        $body .= '<tr><td colspan="6" class="muted">Sin recepciones aún.</td></tr>';
    }
    $body .= '</tbody></table></div>';
    $body .= '<div class="card" style="margin-top:12px">
        <div style="font-weight:800;margin-bottom:8px">Vista previa última etiqueta</div>
        <iframe id="label_preview" title="Vista previa etiqueta" style="width:100%;height:420px;border:1px solid #e5e7eb;border-radius:8px;background:#fff"></iframe>
      </div>';

    $body .= '<script>
      (function () {
        var form = document.getElementById("receive_form");
        var modeField = document.getElementById("reception_mode");
        var modeSelector = document.getElementById("reception_mode_selector");
        var weight = document.getElementById("weight_kg");
        var qty = document.getElementById("received_qty");
        var qtyInput = document.getElementById("received_qty_input");
        var toggle = document.getElementById("toggle_scale");
        var saveBtn = document.getElementById("save_print");
        var submitPrimary = document.getElementById("submit_primary");
        var actionButtonsWrapper = document.getElementById("action_buttons_wrapper");
        var weightFieldWrapper = document.getElementById("weight_field_wrapper");
        var qtyFieldWrapper = document.getElementById("qty_field_wrapper");
        var primaryValueLabel = document.getElementById("primary_value_label");
        var qtyValueLabel = document.getElementById("qty_value_label");
        var scaleStatusRow = document.getElementById("scale_status_row");
        var scaleStatusSpacer = document.getElementById("scale_status_spacer");
        var status = document.getElementById("scale_status");
        var tbody = document.getElementById("recent_tbody");
        var preview = document.getElementById("label_preview");
        if (!form || !modeField || !weight || !qty || !qtyInput || !toggle || !saveBtn || !status) return;

        var active = false;
        var timer = null;
        var last = null;
        var stable = 0;
        var lastSaved = null;
        var saving = false;
        var armed = true;
        var printWindow = null;
        var printQueue = [];
        var printing = false;
        var printTimer = null;
        var lastPrintedAt = 0;
        var lastPrintedWeight = null;
        var TRIGGER_ON_KG = 0.100;
        var TRIGGER_OFF_KG = 0.050;
        var MIN_INTERVAL_MS = 2000;
        var REARM_DELTA_KG = 0.500;
        var serverPrintEnabled = (function(){
          var el = document.getElementById("server_print_enabled");
          return !!(el && String(el.value) === "1");
        })();

        function setStatus(text) { status.textContent = text; }

        function enqueuePrint(url) {
          if (!active) return;
          if (serverPrintEnabled) return;
          printQueue.push(url);
          if (!printing) processPrintQueue();
        }

        function getWarehouseSelect() {
          return form.querySelector("select[name=warehouse_id]");
        }

        function getMode() { return modeField.value === "WEIGHT" ? "WEIGHT" : "QUANTITY"; }

        function syncQtyValue() {
          qty.value = qtyInput.value && Number(qtyInput.value) > 0 ? qtyInput.value : "1";
        }

        function canManualWeightSave() {
          var wh = getWarehouseSelect();
          var weightValue = Number(weight.value);
          return getMode() === "WEIGHT"
            && !!(wh && wh.value)
            && Number.isFinite(weightValue)
            && weightValue >= TRIGGER_ON_KG;
        }

        function syncWeightSaveButton() {
          if (!saveBtn) return;
          if (active) {
            return;
          }
          saveBtn.disabled = !canManualWeightSave();
        }

        function stopScale() {
          active = false;
          toggle.textContent = "Activar balanza";
          syncWeightSaveButton();
          if (timer) clearInterval(timer);
          timer = null;
          if (printTimer) clearTimeout(printTimer);
          printTimer = null;
          printQueue = [];
          printing = false;
          try { if (printWindow && !printWindow.closed) { printWindow.close(); } } catch (e) {}
        }

        function applyModeUI() {
          if (modeSelector) modeSelector.value = getMode();
          weight.step = "0.001";
          weight.required = true;
          if (weightFieldWrapper) weightFieldWrapper.style.display = "";
          if (getMode() === "WEIGHT") {
            qty.value = "1";
            qtyInput.value = "1";
            if (primaryValueLabel) primaryValueLabel.textContent = "Peso real (Kg)";
            if (qtyFieldWrapper) qtyFieldWrapper.style.display = "none";
            if (toggle) toggle.style.display = "";
            if (saveBtn) saveBtn.style.display = "";
            if (submitPrimary) submitPrimary.style.display = "none";
            if (scaleStatusRow) scaleStatusRow.style.display = "";
            if (actionButtonsWrapper) actionButtonsWrapper.style.flexBasis = "300px";
            if (scaleStatusSpacer) scaleStatusSpacer.style.flexBasis = "300px";
            if (!active) {
              setStatus(canManualWeightSave() ? "Listo para guardar e imprimir 1 unidad" : "Ingresa peso manual o activa balanza");
            }
          } else {
            syncQtyValue();
            if (primaryValueLabel) primaryValueLabel.textContent = "Peso real de la recepción (Kg)";
            if (qtyValueLabel) qtyValueLabel.textContent = "Cantidad recibida (Unid.)";
            if (qtyFieldWrapper) qtyFieldWrapper.style.display = "";
            if (toggle) toggle.style.display = "none";
            if (saveBtn) saveBtn.style.display = "none";
            if (submitPrimary) submitPrimary.style.display = "";
            if (scaleStatusRow) scaleStatusRow.style.display = "none";
            if (actionButtonsWrapper) actionButtonsWrapper.style.flexBasis = "190px";
            if (scaleStatusSpacer) scaleStatusSpacer.style.flexBasis = "190px";
            setStatus("Ingresa unidades y peso real para guardar la recepción");
          }
          syncWeightSaveButton();
        }

        function processPrintQueue() {
          if (!active || serverPrintEnabled) {
            printQueue = [];
            printing = false;
            return;
          }
          if (printQueue.length === 0) {
            printing = false;
            return;
          }
          printing = true;
          var url = printQueue.shift();
          if (printWindow && !printWindow.closed) {
            printWindow.location = url;
            try { printWindow.focus(); } catch (e) {}
          } else {
            printWindow = window.open(url, "label_print");
          }
          if (printTimer) clearTimeout(printTimer);
          printTimer = setTimeout(processPrintQueue, 2500);
        }

        async function poll() {
          if (getMode() !== "WEIGHT") {
            return;
          }
          try {
            var res = await fetch("/api/scale/weight", { cache: "no-store" });
            var data = await res.json();
            if (!data || data.ok !== true) throw new Error((data && data.error) ? data.error : "No se pudo leer la balanza.");
            var w = Number(data.weight_kg);
            if (!Number.isFinite(w)) throw new Error("Lectura inválida.");
            weight.value = w.toFixed(3);

            if (last !== null && Math.abs(w - last) < 0.005) stable++; else stable = 0;
            last = w;

            var wh = getWarehouseSelect();
            var hasWarehouse = !!(wh && wh.value);

            if (w < TRIGGER_OFF_KG) {
              armed = true;
              lastPrintedWeight = null;
              saveBtn.disabled = true;
              setStatus("Balanza: activa (lista)");
              return;
            }

            if (w >= TRIGGER_ON_KG && !hasWarehouse) {
              saveBtn.disabled = true;
              setStatus("Balanza: activa (selecciona bodega)");
              return;
            }

            saveBtn.disabled = true;
            var now = Date.now();
            var intervalOk = (now - lastPrintedAt) >= MIN_INTERVAL_MS;
            var deltaOk = (lastPrintedWeight !== null) && (Math.abs(w - lastPrintedWeight) >= REARM_DELTA_KG);

            if (!armed && deltaOk) {
              armed = true;
            }

            if (!saving && armed && hasWarehouse && w >= TRIGGER_ON_KG) {
              var now = Date.now();
              if (intervalOk) {
                armed = false;
                setStatus("Balanza: activa (guardando...)");
                saveAndPrint(w);
                return;
              }
              setStatus("Balanza: activa (imprimiendo...)");
              return;
            }

            if (!armed) {
              setStatus("Balanza: activa (bulto presente)");
              return;
            }
            setStatus("Balanza: activa");
          } catch (e) {
            setStatus("Balanza: error");
            saveBtn.disabled = true;
          }
        }

        async function saveAndPrint(weightOverride) {
          if (saving) return;
          var wh = getWarehouseSelect();
          var pol = form.querySelector("input[name=purchase_order_line_id]");
          var containerItem = form.querySelector("input[name=import_container_item_id]");
          var csrf = form.querySelector("input[name=_csrf]");
          if (!wh || (!pol && !containerItem) || !csrf) return;
          if (!wh.value) { alert("Selecciona la bodega."); return; }
          if (getMode() !== "WEIGHT") return;

          var weightToSave = (weightOverride !== undefined && weightOverride !== null) ? Number(weightOverride) : Number(weight.value);
          if (!Number.isFinite(weightToSave) || weightToSave < TRIGGER_ON_KG) {
            setStatus("Balanza: activa (peso inválido)");
            return;
          }
          weight.value = weightToSave.toFixed(3);

          saving = true;
          saveBtn.disabled = true;
          saveBtn.textContent = "Guardando...";
          try {
            var params = new URLSearchParams();
            params.set("_csrf", csrf.value);
            if (pol && pol.value) {
              params.set("purchase_order_line_id", pol.value);
            }
            if (containerItem && containerItem.value) {
              params.set("import_container_item_id", containerItem.value);
            }
            params.set("reception_mode", getMode());
            params.set("warehouse_id", wh.value);
            params.set("weight_kg", weightToSave.toFixed(3));
            params.set("received_qty", qty.value || "1");
            var res = await fetch("/api/receptions/receive", {
              method: "POST",
              headers: { "Content-Type": "application/x-www-form-urlencoded" },
              body: params.toString()
            });
            var data = await res.json();
            if (!data || data.ok !== true) {
              if (data && data.errors && typeof data.errors === "object") {
                var poErr = data.errors.purchase_order_line_id ? String(data.errors.purchase_order_line_id) : "";
                var containerErr = data.errors.import_container_item_id ? String(data.errors.import_container_item_id) : "";
                var lineErr = poErr || containerErr;
                if (lineErr.indexOf("no permite") >= 0 || lineErr.indexOf("completa") >= 0 || lineErr.indexOf("finalizada") >= 0) {
                  setStatus("Recepción completa (pausada)");
                  active = false;
                  toggle.textContent = "Activar balanza";
                  saveBtn.disabled = true;
                  if (timer) clearInterval(timer);
                  timer = null;
                  return;
                }
              }
              var msg = (data && data.errors) ? Object.values(data.errors).join("\\n") : "No se pudo guardar.";
              throw new Error(msg);
            }
            var labelHref = "";
            try { labelHref = new URL(data.label_url, window.location.origin).toString(); } catch (e) { labelHref = data.label_url; }
            lastSaved = weightToSave;
            lastPrintedAt = Date.now();
            lastPrintedWeight = weightToSave;
            setStatus("Balanza: activa (retira la bobina)");

            if (tbody) {
              var tr = document.createElement("tr");
              var now = new Date();
              var whLabel = wh.options[wh.selectedIndex] ? wh.options[wh.selectedIndex].text : "";
              var whCode = whLabel.split(" - ")[0] || whLabel;
              var dt = now.toISOString().slice(0, 19).replace("T", " ");

              var td1 = document.createElement("td");
              td1.textContent = "#" + data.id;
              var td2 = document.createElement("td");
              td2.textContent = whCode;
              var td3 = document.createElement("td");
              td3.textContent = "1 Unid.";
              var td4 = document.createElement("td");
              td4.textContent = weightToSave.toFixed(3);
              var td5 = document.createElement("td");
              td5.textContent = dt;
              var td6 = document.createElement("td");
              var a = document.createElement("a");
              a.className = "btn secondary";
              a.target = "_blank";
              a.rel = "noopener";
              a.href = labelHref;
              a.textContent = "Etiqueta";
              td6.appendChild(a);

              tr.appendChild(td1);
              tr.appendChild(td2);
              tr.appendChild(td3);
              tr.appendChild(td4);
              tr.appendChild(td5);
              tr.appendChild(td5);
              tr.appendChild(td6);
              tbody.prepend(tr);
            }

            if (preview) {
              preview.src = labelHref;
            }

            if (data.printed === true) {
              setStatus("Balanza: activa (impreso)");
            } else {
              if (active) {
                enqueuePrint(labelHref);
              } else if (labelHref) {
                window.open(labelHref, "_blank", "noopener");
                setStatus("Recepción guardada e impresión lista");
              }
            }
          } catch (e) {
            alert(e && e.message ? e.message : "Error guardando.");
          } finally {
            saving = false;
            saveBtn.textContent = "Guardar e imprimir";
            syncWeightSaveButton();
          }
        }

        toggle.addEventListener("click", function () {
          var wh = getWarehouseSelect();
          active = !active;
          if (active) {
            if (!wh || !wh.value) {
              active = false;
              setStatus("Balanza: selecciona la bodega antes de activar");
              alert("Selecciona la bodega antes de activar la balanza.");
              return;
            }
            toggle.textContent = "Desactivar balanza";
            setStatus("Balanza: activa");
            armed = true;
            stable = 0;
            last = null;
            lastPrintedWeight = null;
            try {
              if (!serverPrintEnabled && !printing && (printQueue.length === 0)) {
                printWindow = window.open("about:blank", "label_print");
                if (printWindow && printWindow.document) {
                  var doc = printWindow.document;
                  doc.open();
                  doc.close();
                  var html = doc.documentElement;
                  var head = doc.head || doc.createElement("head");
                  var body = doc.body || doc.createElement("body");
                  if (!doc.head) html.appendChild(head);
                  if (!doc.body) html.appendChild(body);

                  doc.title = "Etiquetas";

                  var metaCharset = doc.createElement("meta");
                  metaCharset.setAttribute("charset", "utf-8");
                  head.appendChild(metaCharset);

                  var metaViewport = doc.createElement("meta");
                  metaViewport.setAttribute("name", "viewport");
                  metaViewport.setAttribute("content", "width=device-width, initial-scale=1");
                  head.appendChild(metaViewport);

                  var style = doc.createElement("style");
                  style.textContent = "body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:0;padding:16px;background:#f6f7f9;color:#111}.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px}.muted{color:#6b7280;font-size:12px}";
                  head.appendChild(style);

                  var card = doc.createElement("div");
                  card.className = "card";
                  var title = doc.createElement("div");
                  title.style.fontWeight = "800";
                  title.style.marginBottom = "6px";
                  title.textContent = "Ventana de impresión";
                  var msg = doc.createElement("div");
                  msg.className = "muted";
                  msg.textContent = "Se abrirá la etiqueta automáticamente cuando se guarde un pesaje.";
                  card.appendChild(title);
                  card.appendChild(msg);
                  body.appendChild(card);
                }
                try { printWindow.focus(); } catch (e) {}
              }
            } catch (e) {
              printWindow = null;
            }
            poll();
            timer = setInterval(poll, 1000);
          } else {
            stopScale();
            setStatus("Balanza: inactiva");
          }
        });

        var whSelect = getWarehouseSelect();
        if (whSelect) {
          whSelect.addEventListener("change", function () {
            if (!active) {
              if (!whSelect.value) {
                setStatus("Balanza: selecciona bodega");
              } else if (canManualWeightSave()) {
                setStatus("Listo para guardar e imprimir 1 unidad");
              } else {
                setStatus("Ingresa peso manual o activa balanza");
              }
            }
            syncWeightSaveButton();
          });
          if (!whSelect.value) {
            setStatus("Balanza: selecciona bodega");
          }
        }

        weight.addEventListener("input", function () {
          if (getMode() === "WEIGHT" && !active) {
            setStatus(canManualWeightSave() ? "Listo para guardar e imprimir 1 unidad" : "Ingresa peso manual o activa balanza");
          }
          syncWeightSaveButton();
        });
        if (modeSelector) {
          modeSelector.addEventListener("change", function () {
            modeField.value = modeSelector.value === "WEIGHT" ? "WEIGHT" : "QUANTITY";
            stopScale();
            applyModeUI();
          });
        }
        qtyInput.addEventListener("input", function () {
          syncQtyValue();
        });
        form.addEventListener("submit", function () {
          if (getMode() === "WEIGHT") {
            qty.value = "1";
          } else {
            syncQtyValue();
          }
        });
        modeField.value = modeField.value === "WEIGHT" ? "WEIGHT" : "QUANTITY";
        applyModeUI();
        saveBtn.addEventListener("click", function () { saveAndPrint(); });
      })();
    </script>';

    render('Recepción OC', $body);
    exit;
}

if (preg_match('#^/purchase-orders/(\\d+)/receive$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $poId = (int)$m[1];
    $lineId = isset($_POST['purchase_order_line_id']) ? (int)$_POST['purchase_order_line_id'] : 0;
    $warehouseId = isset($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : 0;
    $weight = isset($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : 0.0;
    $receivedQty = isset($_POST['received_qty']) ? (float)$_POST['received_qty'] : 1.0;
    $receptionMode = isset($_POST['reception_mode']) ? (string)$_POST['reception_mode'] : 'QUANTITY';
    $line = $service->getPurchaseOrderLine($lineId);
    if ($line === null || (int)$line['purchase_order_id'] !== $poId) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la línea de OC.</div>');
        exit;
    }

    $result = $service->createRollFromPurchaseOrderLine($lineId, $warehouseId, $weight, $currentOperatorName, $receivedQty, $receptionMode);
    if ($result['ok'] === true) {
        header('Location: /purchase-orders/' . $poId);
        exit;
    }

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Recepcionar bobina</div>
        <a class="btn secondary" href="/purchase-orders/' . $poId . '">Volver</a>
      </div>';
    $body .= '<div class="err" style="margin-bottom:12px"><ul style="margin:0;padding-left:16px">';
    foreach ($result['errors'] as $msg) {
        $body .= '<li>' . h((string)$msg) . '</li>';
    }
    $body .= '</ul></div>';
    $body .= '<div class="card"><a class="btn secondary" href="/purchase-orders/' . $poId . '">Volver</a></div>';
    render('Recepción OC', $body);
    exit;
}

if ($path === '/work-orders' && $method === 'GET') {
    $view = isset($_GET['view']) ? strtolower(trim((string)$_GET['view'])) : 'pending';
    if (!in_array($view, ['pending', 'active', 'closed'], true)) {
        $view = 'pending';
    }
    $wos = $service->listWorkOrdersByView($view);
    $erpReadOnly = isErpProductionReadOnlyMode();
    $activeShiftSession = currentSessionArea() === 'PRODUCTION'
        ? $service->getActiveShiftSessionByOperator($currentOperatorName)
        : null;
    if ($view === 'pending' && !$erpReadOnly) {
        $wos = filterPendingWorkOrdersByShiftMachine($wos, $activeShiftSession);
    }

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">' . ($erpReadOnly ? 'Seguimiento de órdenes de trabajo' : 'Traspasos de materiales por OT') . '</div>
          <div class="muted">' . ($erpReadOnly ? 'Consulta formal del estado de la OT, su proceso actual y los movimientos registrados.' : 'Consulta OT pendientes, en producción/corte y fabricadas completas con su trazabilidad.') . '</div>
        </div>
        <div class="row">
          <a class="btn secondary" href="/">Volver</a>
        </div>
      </div>';

    if ($view === 'pending') {
        $body .= '<div class="card"><table><thead><tr><th>ID</th><th>OT</th><th>SKU final</th><th>Cantidad</th><th>Plan ERP</th><th>Máquina</th><th></th></tr></thead><tbody>';
        foreach ($wos as $wo) {
            $planLabel = trim((string)($wo['erp_plan_label'] ?? ''));
            if ($planLabel === '') {
                $planLabel = (string)$wo['created_at'];
            }
            $body .= '<tr>';
            $body .= '<td>' . (int)$wo['id'] . '</td>';
            $body .= '<td>' . h((string)$wo['ot_code']) . '</td>';
            $body .= '<td>' . h((string)$wo['sku_final']) . '</td>';
            $body .= '<td>' . h((string)($wo['target_qty'] ?? '')) . '</td>';
            $body .= '<td>' . h($planLabel) . '</td>';
            $body .= '<td>' . h((string)($wo['erp_machine_label'] !== '' ? $wo['erp_machine_label'] : '-')) . '</td>';
            $body .= '<td>' . ($erpReadOnly
                ? '<a class="btn secondary" href="/work-orders/' . (int)$wo['id'] . '/start">Ver seguimiento</a>'
                : '<form method="post" action="/work-orders/' . (int)$wo['id'] . '/activate" style="margin:0"><input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><button class="btn secondary" type="submit">Activar</button></form>')
                . '</td>';
            $body .= '</tr>';
        }
        if ($wos === []) {
            $body .= '<tr><td colspan="7" class="muted">Sin OTs pendientes.</td></tr>';
        }
        $body .= '</tbody></table></div>';
    } elseif ($view === 'active') {
        $body .= '<div class="card"><table><thead><tr><th>ID</th><th>OT</th><th>SKU final</th><th>Etapa</th><th>Operador</th><th>Máquina</th><th>Referencia</th><th>Detalle</th><th></th><th></th></tr></thead><tbody>';
        foreach ($wos as $wo) {
            $erpReference = trim((string)($wo['erp_reference_label'] ?? ''));
            if ($erpReference === '') {
                $erpReference = '-';
            }
            $body .= '<tr>';
            $body .= '<td>' . (int)$wo['id'] . '</td>';
            $body .= '<td>' . h((string)$wo['ot_code']) . '</td>';
            $body .= '<td>' . h((string)$wo['sku_final']) . '</td>';
            $body .= '<td>' . h((string)($wo['status_label'] ?? '-')) . '</td>';
            $body .= '<td>' . h((string)$wo['operator_label']) . '</td>';
            $body .= '<td>' . h((string)($wo['erp_machine_label'] !== '' ? $wo['erp_machine_label'] : '-')) . '</td>';
            $body .= '<td>' . h($erpReference) . '</td>';
            $body .= '<td>' . h((string)$wo['current_chemical_label']) . '</td>';
            $body .= '<td><a class="btn secondary" href="/work-orders/' . (int)$wo['id'] . '/start">' . ($erpReadOnly ? 'Ver seguimiento' : 'Ver OT') . '</a></td>';
            $body .= '<td>' . (canAccessWorkOrderTraceability()
                ? '<a class="btn secondary" href="/work-orders/' . (int)$wo['id'] . '/traceability">Trazabilidad</a>'
                : '-') . '</td>';
            $body .= '</tr>';
        }
        if ($wos === []) {
            $body .= '<tr><td colspan="10" class="muted">Sin OTs en producción o corte.</td></tr>';
        }
        $body .= '</tbody></table></div>';
    } else {
        $body .= '<div class="card"><table><thead><tr><th>ID</th><th>OT</th><th>SKU final</th><th>Operador final</th><th>Plan ERP</th><th>Fecha fabricación</th><th>Cajas</th><th></th><th></th></tr></thead><tbody>';
        foreach ($wos as $wo) {
            $planLabel = trim((string)($wo['erp_plan_label'] ?? ''));
            if ($planLabel === '') {
                $planLabel = '-';
            }
            $body .= '<tr>';
            $body .= '<td>' . (int)$wo['id'] . '</td>';
            $body .= '<td>' . h((string)$wo['ot_code']) . '</td>';
            $body .= '<td>' . h((string)$wo['sku_final']) . '</td>';
            $body .= '<td>' . h((string)$wo['operator_label']) . '</td>';
            $body .= '<td>' . h($planLabel) . '</td>';
            $body .= '<td>' . h((string)($wo['finished_at'] !== '' ? $wo['finished_at'] : '-')) . '</td>';
            $body .= '<td>' . h((string)($wo['box_qty'] !== '' ? $wo['box_qty'] : '-')) . '</td>';
            $body .= '<td><a class="btn secondary" href="/work-orders/' . (int)$wo['id'] . '/start">' . ($erpReadOnly ? 'Ver seguimiento' : 'Ver OT') . '</a></td>';
            $body .= '<td>' . (canAccessWorkOrderTraceability()
                ? '<a class="btn secondary" href="/work-orders/' . (int)$wo['id'] . '/traceability">Trazabilidad</a>'
                : '-') . '</td>';
            $body .= '</tr>';
        }
        if ($wos === []) {
            $body .= '<tr><td colspan="9" class="muted">Sin OTs finalizadas.</td></tr>';
        }
        $body .= '</tbody></table></div>';
    }

    render('OTs', $body);
    exit;
}

if ($path === '/work-orders/new' && $method === 'GET') {
    render('OT desde ERP', '<div class="card"><div style="font-size:18px;font-weight:800;margin-bottom:8px">OT cargadas desde ERP</div><div class="muted">Las órdenes de trabajo ya no se crean manualmente en este módulo. Deben venir cargadas desde el ERP y aquí solo se consultan o procesan.</div><div style="margin-top:12px"><a class="btn secondary" href="/work-orders">Volver a OT</a></div></div>');
    exit;
}

if ($path === '/work-orders' && $method === 'POST') {
    http_response_code(403);
    render('OT desde ERP', '<div class="card"><div style="font-size:18px;font-weight:800;margin-bottom:8px">Creación manual no disponible</div><div class="muted">Las órdenes de trabajo deben ser cargadas desde el ERP. Este módulo no permite crear OT manualmente.</div><div style="margin-top:12px"><a class="btn secondary" href="/work-orders">Volver a OT</a></div></div>');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/activate$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $service->setActiveWorkOrder($workOrderId, $currentOperatorName);
    header('Location: /work-orders/' . $workOrderId . '/start?activated=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/start$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    if ((string)($ot['status'] ?? '') === 'CUTTING') {
        $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
        if ($finishApproval === null) {
            header('Location: /work-orders/' . $workOrderId . '/finish-approval');
            exit;
        }

        $lastSealingFinish = $service->getLastWorkOrderSealingFinish($workOrderId);
        $packagingSetupApproval = $service->getLastWorkOrderPackagingSetupApproval($workOrderId);
        $packagingSetupEvents = $service->listWorkOrderPackagingSetupEvents($workOrderId);
        $packagingProductionEvents = $service->listWorkOrderPackagingProductionEvents($workOrderId);
        if ($packagingSetupApproval !== null || !empty($packagingSetupEvents) || !empty($packagingProductionEvents)) {
            header('Location: /work-orders/' . $workOrderId . '/packaging/setup?setup_started=1');
            exit;
        }

        if ($lastSealingFinish !== null) {
            header('Location: /work-orders/' . $workOrderId . '/packaging?sealing_finished=1');
            exit;
        }

        $setupApproval = $service->getLastWorkOrderSealingSetupApproval($workOrderId);
        $setupEvents = $service->listWorkOrderSealingSetupEvents($workOrderId);
        $productionEvents = $service->listWorkOrderSealingProductionEvents($workOrderId);
        if ($setupApproval !== null || !empty($setupEvents) || !empty($productionEvents)) {
            header('Location: /work-orders/' . $workOrderId . '/sealing/setup');
            exit;
        }

        header('Location: /work-orders/' . $workOrderId . '/sealing');
        exit;
    }

    if (!in_array((string)($ot['status'] ?? ''), ['CUTTING', 'CLOSED'], true)) {
        $setupApproval = $service->getLastWorkOrderSetupApproval($workOrderId);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        if ($lastStart !== null) {
            header('Location: /work-orders/' . $workOrderId . '/in-progress');
            exit;
        }
        if ($setupApproval !== null) {
            header('Location: /work-orders/' . $workOrderId . '/setup');
            exit;
        }
    }
    $chemicals = $service->listChemicals();
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $lastStart = $service->getLastWorkOrderStart($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
    $availableMaterialRolls = $service->listAvailableRollsForMaterialRequest();
    $wastes = $service->listProductionWastesByWorkOrder($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $assignedCliches = $service->listClichesAssignedToWorkOrder($workOrderId);
    $clicheUsageHistory = $service->listClicheUsageLogsByWorkOrder($workOrderId);
    $cutWarehouses = array_values(array_filter($service->listWarehouses(), static fn(array $warehouse): bool => in_array((int)$warehouse['code'], [700, 1000], true)));
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['activated']) && $_GET['activated'] === '1') {
        $flashMessage = 'OT activada. Continúa con bobina, tintas de entrada e inicio.';
    } elseif (isset($_GET['roll_attached']) && $_GET['roll_attached'] === '1') {
        $flashMessage = 'Bobina asignada y pesada correctamente para la OT.';
    } elseif (isset($_GET['chemical_input']) && $_GET['chemical_input'] === '1') {
        $flashMessage = 'Tinta de entrada registrada correctamente.';
    } elseif (isset($_GET['anilox_saved']) && $_GET['anilox_saved'] === '1') {
        $flashMessage = 'Configuración de anilox guardada correctamente.';
    } elseif (isset($_GET['machine_header_saved']) && $_GET['machine_header_saved'] === '1') {
        $flashMessage = 'Cabecera de máquina actualizada correctamente.';
    } elseif (isset($_GET['material_requested']) && $_GET['material_requested'] === '1') {
        $flashMessage = 'Solicitud enviada correctamente a bodega.';
    } elseif (isset($_GET['material_error']) && trim((string)$_GET['material_error']) !== '') {
        $flashMessage = trim((string)$_GET['material_error']);
        $flashIsError = true;
    } elseif (isset($_GET['roll_changed']) && $_GET['roll_changed'] === '1') {
        $flashMessage = 'Cambio de bobina registrado correctamente.';
        if (isset($_GET['printed']) && $_GET['printed'] === '1') {
            $flashMessage .= ' La etiqueta de la bobina salida se envió a impresión.';
        } elseif (isset($_GET['printed']) && $_GET['printed'] === '0') {
            $flashMessage .= ' Revisa la etiqueta de la bobina salida para imprimirla.';
        }
    } elseif (isset($_GET['finished']) && $_GET['finished'] === '1') {
        $flashMessage = 'La impresión fue finalizada y la OT pasó a corte.';
        if (isset($_GET['finish_approved']) && $_GET['finish_approved'] === '1') {
            $flashMessage .= ' El cierre de flexografía fue validado por supervisor.';
        }
        if (isset($_GET['printed']) && $_GET['printed'] === '1') {
            $flashMessage .= ' La etiqueta se envió a impresión.';
        } elseif (isset($_GET['printed']) && $_GET['printed'] === '0') {
            $flashMessage .= ' Revisa la etiqueta de la bobina salida para imprimirla.';
        }
        if (isset($_GET['box_printed']) && $_GET['box_printed'] === '1') {
            $flashMessage .= ' También se imprimió la etiqueta de cajas.';
        } elseif (isset($_GET['box_printed']) && $_GET['box_printed'] === '0') {
            $flashMessage .= ' Revisa la etiqueta de cajas para imprimirla.';
        }
    } elseif (isset($_GET['cut_completed']) && $_GET['cut_completed'] === '1') {
        $flashMessage = 'Corte completado. La OT quedó fabricada completamente.';
    } elseif (isset($_GET['cut_error']) && trim((string)$_GET['cut_error']) !== '') {
        $flashMessage = trim((string)$_GET['cut_error']);
        $flashIsError = true;
    } elseif (isset($_GET['closed']) && $_GET['closed'] === '1') {
        $flashMessage = 'La OT ya fue cerrada correctamente.';
    }
    renderWorkOrderStartScreen($ot, $chemicals, $currentRoll, $rollHistory, $chemicalInputs, $lastStart, $lastFinish, $currentOperatorName, $flashMessage, $flashIsError, [], $materialRequests, $wastes, $boxes, $pallets, $outputRoll, $availableMaterialRolls, $cutWarehouses, $activeShiftSession, $assignedCliches, $clicheUsageHistory);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/finish-data$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
    $status = (string)($ot['status'] ?? '');
    if (in_array($status, ['CUTTING', 'CLOSED'], true)) {
        if ($lastFinish !== null && $finishApproval === null) {
            header('Location: /work-orders/' . $workOrderId . '/finish-approval');
        } else {
            header('Location: /work-orders/' . $workOrderId . '/start');
        }
        exit;
    }
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $assignedCliches = $service->listClichesAssignedToWorkOrder($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    $processEvents = $service->listWorkOrderProcessEvents($workOrderId);
    renderWorkOrderSetupScreen(
        $ot,
        $activeShiftSession,
        $currentOperatorName,
        $chemicalInputs,
        $boxes,
        $pallets,
        $currentRoll,
        $outputRoll,
        $assignedCliches,
        $processEvents,
        null,
        false,
        true
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/finish-approval$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    if ($lastFinish === null) {
        header('Location: /work-orders/' . $workOrderId . '/finish-data');
        exit;
    }
    $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
    if ($finishApproval !== null) {
        $validatedByLabel = trim((string)($finishApproval['approved_display_name'] ?? '')) !== ''
            ? (string)$finishApproval['approved_display_name'] . ' (' . (string)($finishApproval['approved_username'] ?? '') . ')'
            : (string)($finishApproval['approved_username'] ?? '');
        $qs = '?finish_approved=1&approved_by=' . urlencode($validatedByLabel);
        if (isset($_GET['printed'])) {
            $qs .= '&printed=' . urlencode((string)$_GET['printed']);
        }
        if (isset($_GET['box_printed'])) {
            $qs .= '&box_printed=' . urlencode((string)$_GET['box_printed']);
        }
        if (isset($_GET['label_roll_id'])) {
            $qs .= '&label_roll_id=' . urlencode((string)$_GET['label_roll_id']);
        }
        header('Location: /work-orders/' . $workOrderId . '/sealing' . $qs);
        exit;
    }
    $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
    $wastes = $service->listProductionWastesByWorkOrder($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    renderWorkOrderFinishApprovalScreen(
        $ot,
        $activeShiftSession,
        $lastFinish,
        $rollHistory,
        $wastes,
        $outputRoll,
        (string)($_GET['role'] ?? 'SUPERVISOR'),
        null,
        false,
        '',
        (string)($_GET['printed'] ?? ''),
        (string)($_GET['box_printed'] ?? ''),
        (string)($_GET['label_roll_id'] ?? ''),
        null
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
    if ($finishApproval === null) {
        header('Location: /work-orders/' . $workOrderId . '/finish-approval');
        exit;
    }
    // Check if setup is already approved or there are existing events
    $setupApproval = $service->getLastWorkOrderSealingSetupApproval($workOrderId);
    $setupEvents = $service->listWorkOrderSealingSetupEvents($workOrderId);
    $productionEvents = $service->listWorkOrderSealingProductionEvents($workOrderId);
    $lastSealingFinish = $service->getLastWorkOrderSealingFinish($workOrderId);
    $sealingFinishAfterSetup = false;
    if ($lastSealingFinish !== null) {
        if ($setupApproval === null) {
            $sealingFinishAfterSetup = true;
        } else {
            $finishTs = strtotime((string)($lastSealingFinish['created_at'] ?? ''));
            $setupTs = strtotime((string)($setupApproval['created_at'] ?? ''));
            $sealingFinishAfterSetup = ($finishTs === false || $setupTs === false) ? true : ($finishTs >= $setupTs);
        }
    }
    if ($sealingFinishAfterSetup) {
        header('Location: /work-orders/' . $workOrderId . '/packaging?sealing_finished=1');
        exit;
    }
    if ($setupApproval !== null || !empty($setupEvents) || !empty($productionEvents)) {
        header('Location: /work-orders/' . $workOrderId . '/sealing/setup');
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $sealingMachines = array_values(array_filter(
        $service->listProductionMachinesWithSessions(),
        static fn(array $machine): bool => strtoupper(trim((string)($machine['machine_type_code'] ?? ''))) === 'SELLADO'
            || strtoupper(trim((string)($machine['production_area'] ?? ''))) === 'SEALING'
    ));
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['finish_approved']) && $_GET['finish_approved'] === '1') {
        $approvedBy = trim((string)($_GET['approved_by'] ?? ''));
        $flashMessage = $approvedBy !== ''
            ? 'Flexografía validada por ' . $approvedBy . '. Continúa con la información inicial de Selladora.'
            : 'Flexografía validada. Continúa con la información inicial de Selladora.';
    }
    renderWorkOrderSealingInfoScreen($ot, $outputRolls, $activeShiftSession, $lastFinish, $sealingMachines, $helperOptions, $flashMessage, $flashIsError);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing/setup/start$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
    if ($finishApproval === null) {
        header('Location: /work-orders/' . $workOrderId . '/finish-approval');
        exit;
    }

    $comments = trim((string)($_POST['comments'] ?? ''));
    $helperName = trim((string)($_POST['helper_name'] ?? ''));
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }

    if ($helperName === '') {
        renderWorkOrderSealingInfoScreen(
            $ot,
            $outputRolls,
            $activeShiftSession,
            $service->getLastWorkOrderFinish($workOrderId),
            [],
            $helperOptions,
            'Debes seleccionar un ayudante para iniciar el alistamiento de Selladora.',
            true,
            ['helper_name' => '', 'comments' => $comments]
        );
        exit;
    }

    $sessionId = (int)($ot['shift_session_id'] ?? 0);
    if ($sessionId <= 0) {
        $sessionId = (int)($activeShiftSession['id'] ?? 0);
    }
    if ($sessionId > 0) {
        $service->updateShiftSessionHeader($sessionId, $helperName, $comments);
        $service->assignActiveShiftSessionToWorkOrder($workOrderId, $currentOperatorName);
    }

    $detailParts = [];
    $measureCompact = trim((string)($ot['sku_final'] ?? ''));
    if (trim((string)($ot['erp_plan_desc'] ?? '')) !== '') {
        $detailParts[] = 'De ' . trim((string)$ot['erp_plan_desc']);
    }
    if ($measureCompact !== '') {
        $detailParts[] = 'a ' . $measureCompact;
    }
    $setupResult = $service->startWorkOrderSealingSetupEvent(
        $workOrderId,
        $comments,
        $currentOperatorName,
        trim(implode(' ', $detailParts))
    );
    if (($setupResult['ok'] ?? false) !== true) {
        renderWorkOrderSealingInfoScreen(
            $ot,
            $outputRolls,
            $activeShiftSession,
            $service->getLastWorkOrderFinish($workOrderId),
            [],
            $helperOptions,
            implode(' ', array_map('strval', array_values($setupResult['errors'] ?? []))),
            true,
            ['helper_name' => $helperName, 'comments' => $comments]
        );
        exit;
    }

    header('Location: /work-orders/' . $workOrderId . '/sealing/setup?setup_started=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing/setup$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
    if ($finishApproval === null) {
        header('Location: /work-orders/' . $workOrderId . '/finish-approval');
        exit;
    }
    $setupApproval = $service->getLastWorkOrderSealingSetupApproval($workOrderId);
    $lastSealingFinish = $service->getLastWorkOrderSealingFinish($workOrderId);
    $sealingFinishAfterSetup = false;
    if ($lastSealingFinish !== null) {
        if ($setupApproval === null) {
            $sealingFinishAfterSetup = true;
        } else {
            $finishTs = strtotime((string)($lastSealingFinish['created_at'] ?? ''));
            $setupTs = strtotime((string)($setupApproval['created_at'] ?? ''));
            $sealingFinishAfterSetup = ($finishTs === false || $setupTs === false) ? true : ($finishTs >= $setupTs);
        }
    }
    if ($sealingFinishAfterSetup) {
        header('Location: /work-orders/' . $workOrderId . '/packaging?sealing_finished=1');
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['setup_started']) && $_GET['setup_started'] === '1') {
        $flashMessage = 'Alistamiento de Selladora iniciado correctamente.';
    } elseif (isset($_GET['setup_approved']) && $_GET['setup_approved'] === '1') {
        $approvedBy = trim((string)($_GET['approved_by'] ?? ''));
        $flashMessage = $approvedBy !== ''
            ? 'Alistamiento de Selladora validado por ' . $approvedBy . '.'
            : 'Alistamiento de Selladora validado correctamente.';
    } elseif (isset($_GET['production_started']) && $_GET['production_started'] === '1') {
        $flashMessage = 'Producción de Selladora iniciada correctamente.';
    } elseif (isset($_GET['sealing_finished']) && $_GET['sealing_finished'] === '1') {
        $flashMessage = 'Producción de Selladora terminada correctamente.';
    } elseif (isset($_GET['process_started']) && $_GET['process_started'] === '1') {
        $flashMessage = 'Evento de producción iniciado correctamente.';
    } elseif (isset($_GET['process_finished']) && $_GET['process_finished'] === '1') {
        $flashMessage = 'Evento de producción terminado correctamente.';
    } elseif (isset($_GET['process_error']) && trim((string)$_GET['process_error']) !== '') {
        $flashMessage = trim((string)$_GET['process_error']);
        $flashIsError = true;
    }
    renderWorkOrderSealingSetupScreen($ot, $outputRolls, $activeShiftSession, $helperOptions, $flashMessage, $flashIsError);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing/production/start$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $result = $service->startWorkOrderSealingProductionEvent(
        $workOrderId,
        $currentOperatorName,
        (string)($_POST['comments'] ?? '')
    );
    if (($result['ok'] ?? false) === true) {
        header('Location: /work-orders/' . $workOrderId . '/sealing/setup?production_started=1');
    } else {
        header('Location: /work-orders/' . $workOrderId . '/sealing/setup?process_error=' . urlencode(implode(' ', array_map('strval', array_values($result['errors'] ?? [])))));
    }
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing/finish-data$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    if ($service->getLastWorkOrderFinishApproval($workOrderId) === null) {
        header('Location: /work-orders/' . $workOrderId . '/finish-approval');
        exit;
    }
    $openProductionEvent = $service->getOpenWorkOrderSealingProductionEvent($workOrderId);
    if ($openProductionEvent === null) {
        $setupApproval = $service->getLastWorkOrderSealingSetupApproval($workOrderId);
        $lastFinish = $service->getLastWorkOrderSealingFinish($workOrderId);
        $finishAfterSetup = false;
        if ($lastFinish !== null) {
            if ($setupApproval === null) {
                $finishAfterSetup = true;
            } else {
                $finishTs = strtotime((string)($lastFinish['created_at'] ?? ''));
                $setupTs = strtotime((string)($setupApproval['created_at'] ?? ''));
                $finishAfterSetup = ($finishTs === false || $setupTs === false) ? true : ($finishTs >= $setupTs);
            }
        }
        if ($finishAfterSetup) {
            header('Location: /work-orders/' . $workOrderId . '/packaging?sealing_finished=1');
        } else {
            header('Location: /work-orders/' . $workOrderId . '/sealing/setup?process_error=' . urlencode('Debes iniciar la producción de Selladora antes de terminarla.'));
        }
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    renderWorkOrderSealingFinishScreen($ot, $outputRolls, $activeShiftSession, $helperOptions);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing/finish$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }

    $result = $service->finishWorkOrderSealingProduction(
        $workOrderId,
        (int)($_POST['sealing_principal_counter'] ?? 0),
        (int)($_POST['sealing_receiver_counter'] ?? 0),
        (string)($_POST['sealing_finish_comments'] ?? ''),
        is_array($_POST['sealing_waste_weight'] ?? null) ? $_POST['sealing_waste_weight'] : [],
        is_array($_POST['sealing_waste_comment'] ?? null) ? $_POST['sealing_waste_comment'] : [],
        $currentOperatorName
    );
    if (($result['ok'] ?? false) !== true) {
        $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
        $helperOptions = $service->listProductionPersonnelNames();
        $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
        if ($activeShiftSession === null) {
            $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
        }
        renderWorkOrderSealingFinishScreen(
            $ot,
            $outputRolls,
            $activeShiftSession,
            $helperOptions,
            implode(' ', array_map('strval', array_values($result['errors'] ?? []))),
            true,
            [
                'sealing_principal_counter' => (string)($_POST['sealing_principal_counter'] ?? ''),
                'sealing_receiver_counter' => (string)($_POST['sealing_receiver_counter'] ?? ''),
                'sealing_finish_comments' => (string)($_POST['sealing_finish_comments'] ?? ''),
                'sealing_waste_weight' => is_array($_POST['sealing_waste_weight'] ?? null) ? $_POST['sealing_waste_weight'] : [],
                'sealing_waste_comment' => is_array($_POST['sealing_waste_comment'] ?? null) ? $_POST['sealing_waste_comment'] : [],
            ]
        );
        exit;
    }
    header('Location: /work-orders/' . $workOrderId . '/packaging?sealing_finished=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    if ((string)($ot['status'] ?? '') === 'CLOSED') {
        header('Location: /work-orders/' . $workOrderId . '/start?closed=1');
        exit;
    }
    // Check if setup is already approved or there are existing events
    $setupApproval = $service->getLastWorkOrderPackagingSetupApproval($workOrderId);
    $setupEvents = $service->listWorkOrderPackagingSetupEvents($workOrderId);
    $productionEvents = $service->listWorkOrderPackagingProductionEvents($workOrderId);
    if ($setupApproval !== null || !empty($setupEvents) || !empty($productionEvents)) {
        header('Location: /work-orders/' . $workOrderId . '/packaging/setup?setup_started=1');
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $message = isset($_GET['sealing_finished'])
        ? 'Produccion de Selladora terminada. Continua con Embalaje.'
        : null;
    renderWorkOrderPackagingInfoScreen($ot, $outputRolls, $activeShiftSession, $helperOptions, $boxes, $pallets, $message);
    exit;
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $display = buildWorkOrderSealingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions);
    $message = isset($_GET['sealing_finished'])
        ? '<div class="ok" style="margin-bottom:12px">Producción de Selladora terminada. Continúa con Embalaje.</div>'
        : '';
    $body = '<div class="legacy-screen-title">Embalaje</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Embalaje</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/sealing/setup">Volver a Selladora</a></div></div>'
        . $message
        . renderWorkOrderSealingOverviewHtml($ot, $display)
        . renderWorkOrderSealingStockModalHtml($outputRolls)
        . '<div class="legacy-sheet-card" style="margin-top:12px">'
        . '<div style="font-size:18px;font-weight:800;margin-bottom:6px">Etapa de Embalaje</div>'
        . '<div class="muted">La OT ya terminó Selladora y quedó lista para continuar con Embalaje.</div>'
        . '</div>';
    render('Embalaje', $body);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/setup/start$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }

    $helperName = trim((string)($_POST['helper_name'] ?? ''));
    $comments = trim((string)($_POST['comments'] ?? ''));
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }

    $sessionId = (int)($ot['shift_session_id'] ?? 0);
    if ($sessionId <= 0) {
        $sessionId = (int)($activeShiftSession['id'] ?? 0);
    }
    if ($sessionId > 0) {
        $service->updateShiftSessionHeader($sessionId, '', $comments);
        $service->assignActiveShiftSessionToWorkOrder($workOrderId, $currentOperatorName);
    }

    $setupResult = $service->startWorkOrderPackagingSetupEvent(
        $workOrderId,
        $comments,
        $currentOperatorName,
        'Alistamiento inicial de Embalaje.'
    );
    if (($setupResult['ok'] ?? false) !== true) {
        renderWorkOrderPackagingInfoScreen(
            $ot,
            $outputRolls,
            $activeShiftSession,
            $helperOptions,
            $boxes,
            $pallets,
            implode(' ', array_map('strval', array_values($setupResult['errors'] ?? []))),
            true,
            ['comments' => $comments]
        );
        exit;
    }

    header('Location: /work-orders/' . $workOrderId . '/packaging/setup?setup_started=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/setup$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    if ((string)($ot['status'] ?? '') === 'CLOSED') {
        header('Location: /work-orders/' . $workOrderId . '/start?closed=1');
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['setup_started']) && $_GET['setup_started'] === '1') {
        $flashMessage = 'Alistamiento de Embalaje iniciado correctamente.';
    } elseif (isset($_GET['setup_approved']) && $_GET['setup_approved'] === '1') {
        $approvedBy = trim((string)($_GET['approved_by'] ?? ''));
        $flashMessage = $approvedBy !== ''
            ? 'Alistamiento de Embalaje validado por ' . $approvedBy . '.'
            : 'Alistamiento de Embalaje validado correctamente.';
    } elseif (isset($_GET['production_started']) && $_GET['production_started'] === '1') {
        $flashMessage = 'Producción de Embalaje iniciada correctamente.';
    } elseif (isset($_GET['packaging_finished']) && $_GET['packaging_finished'] === '1') {
        $flashMessage = 'Producción de Embalaje terminada correctamente.';
    } elseif (isset($_GET['process_started']) && $_GET['process_started'] === '1') {
        $flashMessage = 'Evento de producción iniciado correctamente.';
    } elseif (isset($_GET['process_finished']) && $_GET['process_finished'] === '1') {
        $flashMessage = 'Evento de producción terminado correctamente.';
    } elseif (isset($_GET['process_error']) && trim((string)$_GET['process_error']) !== '') {
        $flashMessage = trim((string)$_GET['process_error']);
        $flashIsError = true;
    }
    renderWorkOrderPackagingSetupScreen($ot, $outputRolls, $activeShiftSession, $helperOptions, $boxes, $pallets, $flashMessage, $flashIsError);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/close$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    if ((string)($ot['status'] ?? '') === 'CLOSED') {
        header('Location: /work-orders/' . $workOrderId . '/start?closed=1');
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    renderWorkOrderPackagingFinishScreen(
        $ot,
        $outputRolls,
        $activeShiftSession,
        $helperOptions,
        $boxes,
        $pallets,
        null,
        false,
        ['close_modal_open' => '1']
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/production/start$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $result = $service->startWorkOrderPackagingProductionEvent(
        $workOrderId,
        $currentOperatorName,
        (string)($_POST['comments'] ?? '')
    );
    if (($result['ok'] ?? false) === true) {
        header('Location: /work-orders/' . $workOrderId . '/packaging/setup?production_started=1');
    } else {
        header('Location: /work-orders/' . $workOrderId . '/packaging/setup?process_error=' . urlencode(implode(' ', array_map('strval', array_values($result['errors'] ?? [])))));
    }
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/finish-data$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    if ((string)($ot['status'] ?? '') === 'CLOSED') {
        header('Location: /work-orders/' . $workOrderId . '/start?closed=1');
        exit;
    }
    $openProductionEvent = $service->getOpenWorkOrderPackagingProductionEvent($workOrderId);
    if ($openProductionEvent === null) {
        $setupApproval = $service->getLastWorkOrderPackagingSetupApproval($workOrderId);
        $lastFinish = $service->getLastWorkOrderPackagingFinish($workOrderId);
        $finishAfterSetup = false;
        if ($lastFinish !== null) {
            if ($setupApproval === null) {
                $finishAfterSetup = true;
            } else {
                $finishTs = strtotime((string)($lastFinish['created_at'] ?? ''));
                $setupTs = strtotime((string)($setupApproval['created_at'] ?? ''));
                $finishAfterSetup = ($finishTs === false || $setupTs === false) ? true : ($finishTs >= $setupTs);
            }
        }
        if ($finishAfterSetup) {
            header('Location: /work-orders/' . $workOrderId . '/packaging/setup?packaging_finished=1');
        } else {
            header('Location: /work-orders/' . $workOrderId . '/packaging/setup?process_error=' . urlencode('Debes iniciar la producción de Embalaje antes de terminarla.'));
        }
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    renderWorkOrderPackagingFinishScreen($ot, $outputRolls, $activeShiftSession, $helperOptions, $boxes, $pallets);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/finish$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }

    $result = $service->finishWorkOrderPackagingProduction(
        $workOrderId,
        (float)($_POST['packaging_produced_units'] ?? 0),
        (string)($_POST['packaging_finish_comments'] ?? ''),
        is_array($_POST['packaging_waste_weight'] ?? null) ? $_POST['packaging_waste_weight'] : [],
        is_array($_POST['packaging_waste_comment'] ?? null) ? $_POST['packaging_waste_comment'] : [],
        is_array($_POST['packaging_data'] ?? null) ? $_POST['packaging_data'] : [],
        $currentOperatorName
    );
    if (($result['ok'] ?? false) !== true) {
        $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
        $boxes = $service->listBoxesByWorkOrder($workOrderId);
        $pallets = $service->listPalletsByWorkOrder($workOrderId);
        $helperOptions = $service->listProductionPersonnelNames();
        $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
        if ($activeShiftSession === null) {
            $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
        }
        renderWorkOrderPackagingFinishScreen(
            $ot,
            $outputRolls,
            $activeShiftSession,
            $helperOptions,
            $boxes,
            $pallets,
            implode(' ', array_map('strval', array_values($result['errors'] ?? []))),
            true,
            [
                'packaging_produced_units' => (string)($_POST['packaging_produced_units'] ?? ''),
                'packaging_finish_comments' => (string)($_POST['packaging_finish_comments'] ?? ''),
                'packaging_waste_weight' => is_array($_POST['packaging_waste_weight'] ?? null) ? $_POST['packaging_waste_weight'] : [],
                'packaging_waste_comment' => is_array($_POST['packaging_waste_comment'] ?? null) ? $_POST['packaging_waste_comment'] : [],
                'packaging_data' => is_array($_POST['packaging_data'] ?? null) ? $_POST['packaging_data'] : [],
            ]
        );
        exit;
    }
    header('Location: /work-orders/' . $workOrderId . '/packaging/setup?packaging_finished=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/finish-order$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    if ((string)($ot['status'] ?? '') === 'CLOSED') {
        header('Location: /work-orders/' . $workOrderId . '/start?closed=1');
        exit;
    }

    $approvalUsername = trim((string)($_POST['close_supervisor_username'] ?? ''));
    $approvalPassword = (string)($_POST['close_supervisor_password'] ?? '');
    $approvedUser = unibagFindAuthorizedUser($trzPdo, $approvalUsername, $approvalPassword, 'PRODUCTION', 1);
    if (!is_array($approvedUser)) {
        $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
        $boxes = $service->listBoxesByWorkOrder($workOrderId);
        $pallets = $service->listPalletsByWorkOrder($workOrderId);
        $helperOptions = $service->listProductionPersonnelNames();
        $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
        if ($activeShiftSession === null) {
            $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
        }
        renderWorkOrderPackagingFinishScreen(
            $ot,
            $outputRolls,
            $activeShiftSession,
            $helperOptions,
            $boxes,
            $pallets,
            'Usuario o clave invalidos para cerrar la OT.',
            true,
            [
                'packaging_produced_units' => (string)($_POST['packaging_produced_units'] ?? ''),
                'packaging_finish_comments' => (string)($_POST['packaging_finish_comments'] ?? ''),
                'packaging_waste_weight' => is_array($_POST['packaging_waste_weight'] ?? null) ? $_POST['packaging_waste_weight'] : [],
                'packaging_waste_comment' => is_array($_POST['packaging_waste_comment'] ?? null) ? $_POST['packaging_waste_comment'] : [],
                'packaging_data' => is_array($_POST['packaging_data'] ?? null) ? $_POST['packaging_data'] : [],
                'close_packaging_data' => is_array($_POST['close_packaging_data'] ?? null) ? $_POST['close_packaging_data'] : [],
                'close_total_units' => (string)($_POST['close_total_units'] ?? ''),
                'close_warehouse_id' => (string)($_POST['close_warehouse_id'] ?? ''),
                'close_supervisor_username' => $approvalUsername,
                'close_supervisor_observation' => (string)($_POST['close_supervisor_observation'] ?? ''),
                'close_closure_classification' => (string)($_POST['close_closure_classification'] ?? ''),
                'close_modal_open' => '1',
            ]
        );
        exit;
    }

    $closeResult = $service->closePackagingWorkOrder(
        $workOrderId,
        (int)($_POST['close_warehouse_id'] ?? 0),
        (string)($_POST['close_closure_classification'] ?? ''),
        (string)$approvedUser['username'],
        trim((string)($approvedUser['display_name'] ?? '')) !== '' ? (string)$approvedUser['display_name'] : (string)$approvedUser['username'],
        (string)($_POST['close_supervisor_observation'] ?? ''),
        (float)($_POST['close_total_units'] ?? 0),
        is_array($_POST['close_packaging_data'] ?? null) ? $_POST['close_packaging_data'] : [],
        $currentOperatorName
    );
    if (($closeResult['ok'] ?? false) !== true) {
        $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
        $boxes = $service->listBoxesByWorkOrder($workOrderId);
        $pallets = $service->listPalletsByWorkOrder($workOrderId);
        $helperOptions = $service->listProductionPersonnelNames();
        $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
        if ($activeShiftSession === null) {
            $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
        }
        renderWorkOrderPackagingFinishScreen(
            $ot,
            $outputRolls,
            $activeShiftSession,
            $helperOptions,
            $boxes,
            $pallets,
            implode(' ', array_map('strval', array_values($closeResult['errors'] ?? []))),
            true,
            [
                'packaging_produced_units' => (string)($_POST['packaging_produced_units'] ?? ''),
                'packaging_finish_comments' => (string)($_POST['packaging_finish_comments'] ?? ''),
                'packaging_waste_weight' => is_array($_POST['packaging_waste_weight'] ?? null) ? $_POST['packaging_waste_weight'] : [],
                'packaging_waste_comment' => is_array($_POST['packaging_waste_comment'] ?? null) ? $_POST['packaging_waste_comment'] : [],
                'packaging_data' => is_array($_POST['packaging_data'] ?? null) ? $_POST['packaging_data'] : [],
                'close_packaging_data' => is_array($_POST['close_packaging_data'] ?? null) ? $_POST['close_packaging_data'] : [],
                'close_total_units' => (string)($_POST['close_total_units'] ?? ''),
                'close_warehouse_id' => (string)($_POST['close_warehouse_id'] ?? ''),
                'close_supervisor_username' => $approvalUsername,
                'close_supervisor_observation' => (string)($_POST['close_supervisor_observation'] ?? ''),
                'close_closure_classification' => (string)($_POST['close_closure_classification'] ?? ''),
                'close_modal_open' => '1',
            ]
        );
        exit;
    }

    header('Location: /work-orders/' . $workOrderId . '/packaging?closed=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/setup-event/finish$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    try {
        $service->finishWorkOrderPackagingSetupEvent(
            $workOrderId,
            (int)($_POST['start_event_id'] ?? 0),
            $currentOperatorName
        );
        header('Location: /work-orders/' . $workOrderId . '/packaging/setup-approval');
    } catch (Throwable $e) {
        header('Location: /work-orders/' . $workOrderId . '/packaging/setup?process_error=' . urlencode($e->getMessage()));
    }
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/setup-approval$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['approved'])) {
        $approvedBy = trim((string)($_GET['approved_by'] ?? ''));
        $flashMessage = $approvedBy !== ''
            ? 'Alistamiento de Embalaje validado por ' . $approvedBy . '.'
            : 'Alistamiento de Embalaje validado correctamente.';
    } elseif (isset($_GET['approval_error'])) {
        $flashMessage = trim((string)$_GET['approval_error']);
        $flashIsError = true;
    }
    renderWorkOrderPackagingSetupApprovalScreen(
        $ot,
        $outputRolls,
        $activeShiftSession,
        $helperOptions,
        $boxes,
        $pallets,
        (string)($_GET['role'] ?? 'SUPERVISOR'),
        $flashMessage,
        $flashIsError,
        false,
        '',
        isset($_GET['approved']) ? trim((string)($_GET['approved_by'] ?? '')) : null
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/setup-approval/validate$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $role = (string)($_POST['role'] ?? 'SUPERVISOR');
    $approvalUsername = trim((string)($_POST['approval_username'] ?? ''));
    $approvalPassword = (string)($_POST['approval_password'] ?? '');
    $approvalReady = trim((string)($_POST['approval_ready'] ?? '0')) === '1';

    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }

    $flashMessage = null;
    $flashIsError = false;
    $validatedByLabel = null;
    if (!$approvalReady) {
        $flashMessage = 'Debes completar todos los check de aprobación antes de validar el alistamiento de Embalaje.';
        $flashIsError = true;
    } else {
        $approvedUser = unibagFindAuthorizedUser($trzPdo, $approvalUsername, $approvalPassword, 'PRODUCTION', 1);
        if (!is_array($approvedUser)) {
            $flashMessage = 'Usuario o clave inválidos para validar la aprobación.';
            $flashIsError = true;
        } else {
            $validatedByLabel = trim((string)($approvedUser['display_name'] ?? '')) !== ''
                ? (string)$approvedUser['display_name'] . ' (' . (string)$approvedUser['username'] . ')'
                : (string)$approvedUser['username'];
            $approvalResult = $service->approveWorkOrderPackagingSetup(
                $workOrderId,
                $role,
                (string)$approvedUser['username'],
                trim((string)($approvedUser['display_name'] ?? ''))
            );
            if (($approvalResult['ok'] ?? false) !== true) {
                $flashMessage = implode(' ', array_map('strval', array_values($approvalResult['errors'] ?? [])));
                $flashIsError = true;
            } else {
                header('Location: /work-orders/' . $workOrderId . '/packaging/setup?setup_approved=1&approved_by=' . urlencode($validatedByLabel));
                exit;
            }
        }
    }

    renderWorkOrderPackagingSetupApprovalScreen(
        $ot,
        $outputRolls,
        $activeShiftSession,
        $helperOptions,
        $boxes,
        $pallets,
        $role,
        $flashMessage,
        $flashIsError,
        true,
        $approvalUsername,
        $validatedByLabel
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing/setup-event/finish$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    try {
        $service->finishWorkOrderSealingSetupEvent(
            $workOrderId,
            (int)($_POST['start_event_id'] ?? 0),
            $currentOperatorName
        );
        header('Location: /work-orders/' . $workOrderId . '/sealing/setup-approval');
    } catch (Throwable $e) {
        header('Location: /work-orders/' . $workOrderId . '/sealing/setup?process_error=' . urlencode($e->getMessage()));
    }
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing/setup-approval$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
    if ($finishApproval === null) {
        header('Location: /work-orders/' . $workOrderId . '/finish-approval');
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['approved'])) {
        $approvedBy = trim((string)($_GET['approved_by'] ?? ''));
        $flashMessage = $approvedBy !== ''
            ? 'Alistamiento de Selladora validado por ' . $approvedBy . '.'
            : 'Alistamiento de Selladora validado correctamente.';
    } elseif (isset($_GET['approval_error'])) {
        $flashMessage = trim((string)$_GET['approval_error']);
        $flashIsError = true;
    }
    renderWorkOrderSealingSetupApprovalScreen(
        $ot,
        $outputRolls,
        $activeShiftSession,
        $helperOptions,
        (string)($_GET['role'] ?? 'SUPERVISOR'),
        $flashMessage,
        $flashIsError,
        false,
        '',
        isset($_GET['approved']) ? trim((string)($_GET['approved_by'] ?? '')) : null
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing/setup-approval/validate$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $role = (string)($_POST['role'] ?? 'SUPERVISOR');
    $approvalUsername = trim((string)($_POST['approval_username'] ?? ''));
    $approvalPassword = (string)($_POST['approval_password'] ?? '');
    $approvalReady = trim((string)($_POST['approval_ready'] ?? '0')) === '1';

    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }

    $flashMessage = null;
    $flashIsError = false;
    $validatedByLabel = null;
    if (!$approvalReady) {
        $flashMessage = 'Debes completar todos los check de aprobación antes de validar el alistamiento de Selladora.';
        $flashIsError = true;
    } else {
        $approvedUser = unibagFindAuthorizedUser($trzPdo, $approvalUsername, $approvalPassword, 'PRODUCTION', 1);
        if (!is_array($approvedUser)) {
            $flashMessage = 'Usuario o clave inválidos para validar la aprobación.';
            $flashIsError = true;
        } else {
            $validatedByLabel = trim((string)($approvedUser['display_name'] ?? '')) !== ''
                ? (string)$approvedUser['display_name'] . ' (' . (string)$approvedUser['username'] . ')'
                : (string)$approvedUser['username'];
            $approvalResult = $service->approveWorkOrderSealingSetup(
                $workOrderId,
                $role,
                (string)$approvedUser['username'],
                trim((string)($approvedUser['display_name'] ?? ''))
            );
            if (($approvalResult['ok'] ?? false) !== true) {
                $flashMessage = implode(' ', array_map('strval', array_values($approvalResult['errors'] ?? [])));
                $flashIsError = true;
            } else {
                header('Location: /work-orders/' . $workOrderId . '/sealing/setup?setup_approved=1&approved_by=' . urlencode($validatedByLabel));
                exit;
            }
        }
    }

    renderWorkOrderSealingSetupApprovalScreen(
        $ot,
        $outputRolls,
        $activeShiftSession,
        $helperOptions,
        $role,
        $flashMessage,
        $flashIsError,
        $flashIsError,
        $approvalUsername,
        $validatedByLabel
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/setup$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $status = (string)($ot['status'] ?? '');
    if ($status === 'CUTTING') {
        $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
        if ($service->getLastWorkOrderFinish($workOrderId) !== null && $finishApproval === null) {
            header('Location: /work-orders/' . $workOrderId . '/finish-approval');
        } else {
            header('Location: /work-orders/' . $workOrderId . '/start');
        }
        exit;
    }
    if ($status === 'CLOSED') {
        header('Location: /work-orders/' . $workOrderId . '/start?closed=1');
        exit;
    }
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $assignedCliches = $service->listClichesAssignedToWorkOrder($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    $processEvents = $service->listWorkOrderProcessEvents($workOrderId);
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['anilox_saved']) && $_GET['anilox_saved'] === '1') {
        $flashMessage = 'Configuración de anilox guardada correctamente.';
    } elseif (isset($_GET['machine_header_saved']) && $_GET['machine_header_saved'] === '1') {
        $flashMessage = 'Cabecera de máquina actualizada correctamente.';
    } elseif (isset($_GET['setup_approved']) && $_GET['setup_approved'] === '1') {
        $approvedBy = trim((string)($_GET['approved_by'] ?? ''));
        $flashMessage = $approvedBy !== ''
            ? 'Alistamiento terminado: la máquina quedó configurada para producción y fue validada por ' . $approvedBy . '.'
            : 'Alistamiento terminado: la máquina quedó configurada para producción.';
    } elseif (isset($_GET['production_started']) && $_GET['production_started'] === '1') {
        $flashMessage = 'Producción iniciada correctamente.';
    } elseif (isset($_GET['process_started']) && $_GET['process_started'] === '1') {
        $flashMessage = 'Evento de producción iniciado correctamente.';
    } elseif (isset($_GET['process_finished']) && $_GET['process_finished'] === '1') {
        $flashMessage = 'Evento de producción terminado correctamente.';
    } elseif (isset($_GET['process_error']) && trim((string)$_GET['process_error']) !== '') {
        $flashMessage = trim((string)$_GET['process_error']);
        $flashIsError = true;
    }
    renderWorkOrderSetupScreen($ot, $activeShiftSession, $currentOperatorName, $chemicalInputs, $boxes, $pallets, $currentRoll, $outputRoll, $assignedCliches, $processEvents, $flashMessage, $flashIsError);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/in-progress$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $status = (string)($ot['status'] ?? '');
    if ($status === 'CUTTING') {
        $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
        if ($service->getLastWorkOrderFinish($workOrderId) !== null && $finishApproval === null) {
            header('Location: /work-orders/' . $workOrderId . '/finish-approval');
        } else {
            header('Location: /work-orders/' . $workOrderId . '/start');
        }
        exit;
    }
    if ($status === 'CLOSED') {
        header('Location: /work-orders/' . $workOrderId . '/start?closed=1');
        exit;
    }
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $assignedCliches = $service->listClichesAssignedToWorkOrder($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    $processEvents = $service->listWorkOrderProcessEvents($workOrderId);
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['process_started']) && $_GET['process_started'] === '1') {
        $flashMessage = 'Evento de producción iniciado correctamente.';
    } elseif (isset($_GET['process_finished']) && $_GET['process_finished'] === '1') {
        $flashMessage = 'Evento de producción terminado correctamente.';
    } elseif (isset($_GET['process_error']) && trim((string)$_GET['process_error']) !== '') {
        $flashMessage = trim((string)$_GET['process_error']);
        $flashIsError = true;
    }
    renderWorkOrderInProgressScreen($ot, $activeShiftSession, $chemicalInputs, $boxes, $currentRoll, $outputRoll, $assignedCliches, $processEvents, $flashMessage, $flashIsError);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/setup-approval$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['approved'])) {
        $approvedBy = trim((string)($_GET['approved_by'] ?? ''));
        $flashMessage = $approvedBy !== ''
            ? 'Partida validada correctamente por ' . $approvedBy . '.'
            : 'Partida validada correctamente.';
    } elseif (isset($_GET['approval_error'])) {
        $flashMessage = trim((string)$_GET['approval_error']);
        $flashIsError = true;
    }
    renderWorkOrderSetupApprovalScreen(
        $ot,
        $activeShiftSession,
        $chemicalInputs,
        $currentRoll,
        $outputRoll,
        (string)($_GET['role'] ?? 'SUPERVISOR'),
        $flashMessage,
        $flashIsError,
        false,
        '',
        isset($_GET['approved']) ? trim((string)($_GET['approved_by'] ?? '')) : null
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/setup-approval/validate$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $role = (string)($_POST['role'] ?? 'SUPERVISOR');
    $approvalUsername = trim((string)($_POST['approval_username'] ?? ''));
    $approvalPassword = (string)($_POST['approval_password'] ?? '');
    $approvalReady = trim((string)($_POST['approval_ready'] ?? '0')) === '1';

    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }

    $flashMessage = null;
    $flashIsError = false;
    $validatedByLabel = null;
    if (!$approvalReady) {
        $flashMessage = 'Debes completar todos los check de aprobación antes de validar la partida.';
        $flashIsError = true;
    } else {
        $approvedUser = unibagFindAuthorizedUser($trzPdo, $approvalUsername, $approvalPassword, 'PRODUCTION', 1);
        if (!is_array($approvedUser)) {
            $flashMessage = 'Usuario o clave inválidos para validar la aprobación.';
            $flashIsError = true;
        } else {
            $validatedByLabel = trim((string)($approvedUser['display_name'] ?? '')) !== ''
                ? (string)$approvedUser['display_name'] . ' (' . (string)$approvedUser['username'] . ')'
                : (string)$approvedUser['username'];
            $approvalResult = $service->approveWorkOrderSetup(
                $workOrderId,
                $role,
                (string)$approvedUser['username'],
                trim((string)($approvedUser['display_name'] ?? ''))
            );
            if (($approvalResult['ok'] ?? false) !== true) {
                $flashMessage = implode(' ', array_map('strval', array_values($approvalResult['errors'] ?? [])));
                $flashIsError = true;
            } else {
                header('Location: /work-orders/' . $workOrderId . '/setup?setup_approved=1&approved_by=' . urlencode($validatedByLabel));
                exit;
            }
        }
    }

    renderWorkOrderSetupApprovalScreen(
        $ot,
        $activeShiftSession,
        $chemicalInputs,
        $currentRoll,
        $outputRoll,
        $role,
        $flashMessage,
        $flashIsError,
        $flashIsError,
        $approvalUsername,
        $validatedByLabel
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/finish-approval/validate$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $role = (string)($_POST['role'] ?? 'SUPERVISOR');
    $approvalUsername = trim((string)($_POST['approval_username'] ?? ''));
    $approvalPassword = (string)($_POST['approval_password'] ?? '');
    $printed = (string)($_POST['printed'] ?? '');
    $boxPrinted = (string)($_POST['box_printed'] ?? '');
    $labelRollId = (string)($_POST['label_roll_id'] ?? '');

    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
    $wastes = $service->listProductionWastesByWorkOrder($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }

    $flashMessage = null;
    $flashIsError = false;
    $validatedByLabel = null;
    $approvedUser = unibagFindAuthorizedUser($trzPdo, $approvalUsername, $approvalPassword, 'PRODUCTION', 1);
    if (!is_array($approvedUser)) {
        $flashMessage = 'Usuario o clave inválidos para validar el cierre de flexografía.';
        $flashIsError = true;
    } else {
        $validatedByLabel = trim((string)($approvedUser['display_name'] ?? '')) !== ''
            ? (string)$approvedUser['display_name'] . ' (' . (string)$approvedUser['username'] . ')'
            : (string)$approvedUser['username'];
        $approvalResult = $service->approveWorkOrderFinish(
            $workOrderId,
            $role,
            (string)$approvedUser['username'],
            trim((string)($approvedUser['display_name'] ?? ''))
        );
        if (($approvalResult['ok'] ?? false) !== true) {
            $flashMessage = implode(' ', array_map('strval', array_values($approvalResult['errors'] ?? [])));
            $flashIsError = true;
        } else {
            $qs = '?finish_approved=1&approved_by=' . urlencode($validatedByLabel);
            if ($printed !== '') {
                $qs .= '&printed=' . urlencode($printed);
            }
            if ($boxPrinted !== '') {
                $qs .= '&box_printed=' . urlencode($boxPrinted);
            }
            if ($labelRollId !== '') {
                $qs .= '&label_roll_id=' . urlencode($labelRollId);
            }
            header('Location: /work-orders/' . $workOrderId . '/sealing' . $qs);
            exit;
        }
    }

    renderWorkOrderFinishApprovalScreen(
        $ot,
        $activeShiftSession,
        $lastFinish,
        $rollHistory,
        $wastes,
        $outputRoll,
        $role,
        $flashMessage,
        $flashIsError,
        $approvalUsername,
        $printed,
        $boxPrinted,
        $labelRollId,
        $validatedByLabel
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/production-event/start$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $returnScreen = strtoupper(trim((string)($_POST['return_screen'] ?? 'IN_PROGRESS')));
    if ($returnScreen === 'SETUP') {
        $redirectBase = '/work-orders/' . $workOrderId . '/setup';
    } elseif ($returnScreen === 'SEALING_SETUP') {
        $redirectBase = '/work-orders/' . $workOrderId . '/sealing/setup';
    } elseif ($returnScreen === 'PACKAGING_SETUP') {
        $redirectBase = '/work-orders/' . $workOrderId . '/packaging/setup';
    } else {
        $redirectBase = '/work-orders/' . $workOrderId . '/in-progress';
    }
    try {
        $service->startWorkOrderProcessEvent(
            $workOrderId,
            (string)($_POST['event_key'] ?? ''),
            (string)($_POST['comments'] ?? ''),
            $currentOperatorName
        );
        header('Location: ' . $redirectBase . '?process_started=1');
    } catch (Throwable $e) {
        header('Location: ' . $redirectBase . '?process_error=' . urlencode($e->getMessage()));
    }
    exit;
}

if (preg_match('#^/work-orders/(\d+)/production/start$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $result = $service->startWorkOrderProductionEvent(
        $workOrderId,
        $currentOperatorName,
        (string)($_POST['comments'] ?? '')
    );
    if (($result['ok'] ?? false) === true) {
        header('Location: /work-orders/' . $workOrderId . '/setup?production_started=1');
    } else {
        header('Location: /work-orders/' . $workOrderId . '/setup?process_error=' . urlencode(implode(' ', array_map('strval', array_values($result['errors'] ?? [])))));
    }
    exit;
}

if (preg_match('#^/work-orders/(\d+)/production-event/finish$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $returnScreen = strtoupper(trim((string)($_POST['return_screen'] ?? 'IN_PROGRESS')));
    if ($returnScreen === 'SETUP') {
        $redirectBase = '/work-orders/' . $workOrderId . '/setup';
    } elseif ($returnScreen === 'SEALING_SETUP') {
        $redirectBase = '/work-orders/' . $workOrderId . '/sealing/setup';
    } elseif ($returnScreen === 'PACKAGING_SETUP') {
        $redirectBase = '/work-orders/' . $workOrderId . '/packaging/setup';
    } else {
        $redirectBase = '/work-orders/' . $workOrderId . '/in-progress';
    }
    try {
        $service->finishWorkOrderProcessEvent(
            $workOrderId,
            (int)($_POST['start_event_id'] ?? 0),
            $currentOperatorName
        );
        header('Location: ' . $redirectBase . '?process_finished=1');
    } catch (Throwable $e) {
        header('Location: ' . $redirectBase . '?process_error=' . urlencode($e->getMessage()));
    }
    exit;
}

if (preg_match('#^/work-orders/(\d+)/request-materials$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $chemicals = $service->listChemicals();
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
    $availableMaterialRolls = $service->listAvailableRollsForMaterialRequest();
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $assignedCliches = $service->listClichesAssignedToWorkOrder($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['material_requested']) && $_GET['material_requested'] === '1') {
        $flashMessage = 'Solicitud enviada correctamente a bodega.';
    }
    renderWorkOrderRequestMaterialsScreen($ot, $chemicals, $currentRoll, $chemicalInputs, $materialRequests, $availableMaterialRolls, $boxes, $activeShiftSession, $assignedCliches, $outputRoll, $flashMessage, $flashIsError);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/materials$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
    $availableMaterialRolls = $service->listAvailableRollsForMaterialDelivery();
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $assignedCliches = $service->listClichesAssignedToWorkOrder($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['material_delivered']) && $_GET['material_delivered'] === '1') {
        $flashMessage = 'Tela ingresada correctamente a la OT.';
    } elseif (isset($_GET['material_attached']) && $_GET['material_attached'] === '1') {
        $flashMessage = 'Bobina ingresada correctamente a la máquina.';
    } elseif (isset($_GET['material_removed']) && $_GET['material_removed'] === '1') {
        $flashMessage = 'Entrada eliminada correctamente.';
    } elseif (isset($_GET['material_released']) && $_GET['material_released'] === '1') {
        $flashMessage = 'Salida de bobina registrada correctamente.';
    }
    renderWorkOrderMaterialUsageScreen($ot, $currentRoll, $chemicalInputs, $materialRequests, $availableMaterialRolls, $boxes, $activeShiftSession, $assignedCliches, $outputRoll, $flashMessage, $flashIsError);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/materials/attach$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $result = $service->attachRequestedRollToWorkOrder(
        (int)($_POST['request_id'] ?? 0),
        (int)($_POST['roll_id'] ?? 0),
        (float)($_POST['process_weight_kg'] ?? 0),
        $currentOperatorName
    );
    if (($result['ok'] ?? false) === true) {
        header('Location: /work-orders/' . $workOrderId . '/materials?material_attached=1');
        exit;
    }

    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        header('Location: /work-orders');
        exit;
    }
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
    $availableMaterialRolls = $service->listAvailableRollsForMaterialDelivery();
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $assignedCliches = $service->listClichesAssignedToWorkOrder($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    $firstError = (string)reset($result['errors']);
    renderWorkOrderMaterialUsageScreen(
        $ot,
        $currentRoll,
        $chemicalInputs,
        $materialRequests,
        $availableMaterialRolls,
        $boxes,
        $activeShiftSession,
        $assignedCliches,
        $outputRoll,
        $firstError !== '' ? $firstError : 'No se pudo ingresar la bobina a la máquina.',
        true
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/materials/release$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $releaseWasteLabels = [
        'approval' => 'Ajuste / Aprobación',
        'printing' => 'Impresión',
        'process' => 'Merma Proceso',
        'other' => 'Otros',
    ];
    $releaseWasteWeightPost = is_array($_POST['release_waste_detail_weight'] ?? null) ? $_POST['release_waste_detail_weight'] : [];
    $releaseWasteCommentPost = is_array($_POST['release_waste_detail_comment'] ?? null) ? $_POST['release_waste_detail_comment'] : [];
    $releaseWasteDetails = [];
    foreach ($releaseWasteLabels as $releaseWasteKey => $releaseWasteLabel) {
        $releaseWasteDetails[$releaseWasteKey] = [
            'label' => $releaseWasteLabel,
            'weight_kg' => (float)($releaseWasteWeightPost[$releaseWasteKey] ?? 0),
            'comment' => (string)($releaseWasteCommentPost[$releaseWasteKey] ?? ''),
        ];
    }
    $result = $service->releaseCurrentRollFromWorkOrder(
        $workOrderId,
        (float)($_POST['final_weight_kg'] ?? 0),
        (float)($_POST['waste_kg'] ?? 0),
        $currentOperatorName,
        $releaseWasteDetails,
        (int)($_POST['roll_id'] ?? 0),
        (int)($_POST['request_id'] ?? 0)
    );
    if (($result['ok'] ?? false) === true) {
        header('Location: /work-orders/' . $workOrderId . '/materials?material_released=1');
        exit;
    }

    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        header('Location: /work-orders');
        exit;
    }
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
    $availableMaterialRolls = $service->listAvailableRollsForMaterialDelivery();
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $assignedCliches = $service->listClichesAssignedToWorkOrder($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    $firstError = (string)reset($result['errors']);
    renderWorkOrderMaterialUsageScreen(
        $ot,
        $currentRoll,
        $chemicalInputs,
        $materialRequests,
        $availableMaterialRolls,
        $boxes,
        $activeShiftSession,
        $assignedCliches,
        $outputRoll,
        $firstError !== '' ? $firstError : 'No se pudo registrar la salida de la bobina.',
        true,
        [
            'final_weight_kg' => (string)($_POST['final_weight_kg'] ?? ''),
            'waste_kg' => (string)($_POST['waste_kg'] ?? '0'),
            'release_waste_detail_weight' => $releaseWasteWeightPost,
            'release_waste_detail_comment' => $releaseWasteCommentPost,
        ]
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/materials/remove$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $result = $service->removeCurrentRequestedRollFromWorkOrder(
        $workOrderId,
        (int)($_POST['request_id'] ?? 0),
        $currentOperatorName,
        (int)($_POST['roll_id'] ?? 0)
    );
    if (($result['ok'] ?? false) === true) {
        header('Location: /work-orders/' . $workOrderId . '/materials?material_removed=1');
        exit;
    }

    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        header('Location: /work-orders');
        exit;
    }
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
    $availableMaterialRolls = $service->listAvailableRollsForMaterialDelivery();
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $assignedCliches = $service->listClichesAssignedToWorkOrder($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    $firstError = (string)reset($result['errors']);
    renderWorkOrderMaterialUsageScreen(
        $ot,
        $currentRoll,
        $chemicalInputs,
        $materialRequests,
        $availableMaterialRolls,
        $boxes,
        $activeShiftSession,
        $assignedCliches,
        $outputRoll,
        $firstError !== '' ? $firstError : 'No se pudo eliminar la entrada de la bobina.',
        true
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/anilox-config$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }

    $unitInputs = isset($_POST['anilox_unit_no']) && is_array($_POST['anilox_unit_no']) ? $_POST['anilox_unit_no'] : [];
    $colorInputs = isset($_POST['anilox_color_name']) && is_array($_POST['anilox_color_name']) ? $_POST['anilox_color_name'] : [];
    $aniloxInputs = isset($_POST['anilox_id']) && is_array($_POST['anilox_id']) ? $_POST['anilox_id'] : [];
    $slotCount = max(count($unitInputs), count($colorInputs), count($aniloxInputs));
    $aniloxSlots = [];
    for ($index = 0; $index < $slotCount; $index++) {
        $aniloxSlots[] = [
            'unit_no' => isset($unitInputs[$index]) ? (int)$unitInputs[$index] : ($index + 1),
            'color_name' => isset($colorInputs[$index]) ? trim((string)$colorInputs[$index]) : '',
            'anilox_id' => isset($aniloxInputs[$index]) ? (int)$aniloxInputs[$index] : 0,
        ];
    }

    $result = $service->saveWorkOrderAniloxAssignments($workOrderId, $aniloxSlots, $currentOperatorName);
    if (($result['ok'] ?? false) !== true) {
        $chemicals = $service->listChemicals();
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
        $availableMaterialRolls = $service->listAvailableRollsForMaterialRequest();
        $wastes = $service->listProductionWastesByWorkOrder($workOrderId);
        $boxes = $service->listBoxesByWorkOrder($workOrderId);
        $pallets = $service->listPalletsByWorkOrder($workOrderId);
        $assignedCliches = $service->listClichesAssignedToWorkOrder($workOrderId);
        $clicheUsageHistory = $service->listClicheUsageLogsByWorkOrder($workOrderId);
        $cutWarehouses = array_values(array_filter($service->listWarehouses(), static fn(array $warehouse): bool => in_array((int)$warehouse['code'], [700, 1000], true)));
        $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
        if ($activeShiftSession === null) {
            $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
        }
        $outputRoll = null;
        if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
            $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
        }
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            implode(' ', array_map('strval', array_values($result['errors'] ?? []))),
            true,
            ['anilox_slots' => $aniloxSlots],
            $materialRequests,
            $wastes,
            $boxes,
            $pallets,
            $outputRoll,
            $availableMaterialRolls,
            $cutWarehouses,
            $activeShiftSession,
            $assignedCliches,
            $clicheUsageHistory
        );
        exit;
    }

    header('Location: /work-orders/' . $workOrderId . '/setup?anilox_saved=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/machine-header$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }

    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    if ($activeShiftSession === null || (int)($activeShiftSession['id'] ?? 0) <= 0) {
        header('Location: /work-orders/' . $workOrderId . '/start');
        exit;
    }

    $helperName = trim((string)($_POST['shift_helper_name'] ?? ''));
    $comments = trim((string)($_POST['shift_comments'] ?? ''));
    $result = $service->updateShiftSessionHeader((int)$activeShiftSession['id'], $helperName, $comments);
    if (($result['ok'] ?? false) !== true) {
        $chemicals = $service->listChemicals();
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
        $availableMaterialRolls = $service->listAvailableRollsForMaterialRequest();
        $wastes = $service->listProductionWastesByWorkOrder($workOrderId);
        $boxes = $service->listBoxesByWorkOrder($workOrderId);
        $pallets = $service->listPalletsByWorkOrder($workOrderId);
        $assignedCliches = $service->listClichesAssignedToWorkOrder($workOrderId);
        $clicheUsageHistory = $service->listClicheUsageLogsByWorkOrder($workOrderId);
        $cutWarehouses = array_values(array_filter($service->listWarehouses(), static fn(array $warehouse): bool => in_array((int)$warehouse['code'], [700, 1000], true)));
        $outputRoll = null;
        if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
            $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
        }
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            implode(' ', array_map('strval', array_values($result['errors'] ?? []))),
            true,
            [
                'shift_helper_name' => $helperName,
                'shift_comments' => $comments,
            ],
            $materialRequests,
            $wastes,
            $boxes,
            $pallets,
            $outputRoll,
            $availableMaterialRolls,
            $cutWarehouses,
            $activeShiftSession,
            $assignedCliches,
            $clicheUsageHistory
        );
        exit;
    }

    header('Location: /work-orders/' . $workOrderId . '/setup?machine_header_saved=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/material-request$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $returnContext = strtoupper(trim((string)($_POST['return_context'] ?? '')));
    $requestFilter = strtoupper(trim((string)($_POST['request_filter'] ?? ($_POST['request_type'] ?? 'ROLL'))));
    if (!in_array($requestFilter, ['ROLL', 'CHEMICAL', 'OTHER'], true)) {
        $requestFilter = 'ROLL';
    }
    $result = $service->createMaterialRequest(
        $workOrderId,
        (string)($_POST['request_type'] ?? 'ROLL'),
        (string)($_POST['requested_group_key'] ?? ''),
        isset($_POST['chemical_id']) && (int)$_POST['chemical_id'] > 0 ? (int)$_POST['chemical_id'] : null,
        (string)($_POST['requested_item'] ?? ''),
        (float)($_POST['requested_qty'] ?? 0),
        (string)($_POST['requested_unit'] ?? ''),
        (string)($_POST['request_notes'] ?? ''),
        $currentOperatorName
    );
    if (($result['ok'] ?? false) === true) {
        $redirectPath = $returnContext === 'REQUEST_WINDOW'
            ? withQuery('/work-orders/' . $workOrderId . '/request-materials', ['material_requested' => '1', 'request_filter' => $requestFilter])
            : '/work-orders/' . $workOrderId . '/start?material_requested=1';
        header('Location: ' . $redirectPath);
        exit;
    }

    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        header('Location: /work-orders');
        exit;
    }

    $formState = $_POST;
    $formState['request_filter'] = $requestFilter;
    if ((string)($_POST['request_type'] ?? '') === 'CHEMICAL') {
        $formState['chemical_request_id'] = (string)($_POST['chemical_id'] ?? '');
        $formState['chemical_requested_qty'] = (string)($_POST['requested_qty'] ?? '');
        $formState['chemical_request_notes'] = (string)($_POST['request_notes'] ?? '');
    } elseif ((string)($_POST['request_type'] ?? '') === 'OTHER') {
        $formState['other_requested_qty'] = (string)($_POST['requested_qty'] ?? '');
        $formState['other_requested_unit'] = (string)($_POST['requested_unit'] ?? '');
        $formState['other_request_notes'] = (string)($_POST['request_notes'] ?? '');
    }

    $chemicals = $service->listChemicals();
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $lastStart = $service->getLastWorkOrderStart($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
    $availableMaterialRolls = $service->listAvailableRollsForMaterialRequest();
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $assignedCliches = $service->listClichesAssignedToWorkOrder($workOrderId);
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    $firstError = (string)reset($result['errors']);
    if ($returnContext === 'REQUEST_WINDOW') {
        renderWorkOrderRequestMaterialsScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $chemicalInputs,
            $materialRequests,
            $availableMaterialRolls,
            $boxes,
            $activeShiftSession,
            $assignedCliches,
            $outputRoll,
            $firstError !== '' ? $firstError : 'No se pudo enviar la solicitud a bodega.',
            true,
            $formState
        );
        exit;
    }

    $wastes = $service->listProductionWastesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $cutWarehouses = $service->listWarehousesForCut();
    renderWorkOrderStartScreen(
        $ot,
        $chemicals,
        $currentRoll,
        $rollHistory,
        $chemicalInputs,
        $lastStart,
        $lastFinish,
        $currentOperatorName,
        $firstError !== '' ? $firstError : 'No se pudo enviar la solicitud a bodega.',
        true,
        $formState,
        $materialRequests,
        $wastes,
        $boxes,
        $pallets,
        $outputRoll,
        $availableMaterialRolls,
        $cutWarehouses
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/material-delivery$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $returnContext = strtoupper(trim((string)($_POST['return_context'] ?? '')));
    $rollId = isset($_POST['roll_id']) && (int)$_POST['roll_id'] > 0 ? (int)$_POST['roll_id'] : null;
    $result = $service->deliverMaterialRequest((int)($_POST['request_id'] ?? 0), $rollId, $currentOperatorName);
    if (($result['ok'] ?? false) === true) {
        $redirectPath = $returnContext === 'USAGE_SCREEN'
            ? '/work-orders/' . $workOrderId . '/materials?material_delivered=1'
            : '/work-orders/' . $workOrderId . '/start';
        header('Location: ' . $redirectPath);
        exit;
    }

    if ($returnContext === 'USAGE_SCREEN') {
        $ot = $service->getWorkOrder($workOrderId);
        if ($ot === null) {
            header('Location: /work-orders');
            exit;
        }
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
        $availableMaterialRolls = $service->listAvailableRollsForMaterialDelivery();
        $boxes = $service->listBoxesByWorkOrder($workOrderId);
        $assignedCliches = $service->listClichesAssignedToWorkOrder($workOrderId);
        $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
        if ($activeShiftSession === null) {
            $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
        }
        $outputRoll = null;
        if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
            $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
        }
        $firstError = (string)reset($result['errors']);
        renderWorkOrderMaterialUsageScreen(
            $ot,
            $currentRoll,
            $chemicalInputs,
            $materialRequests,
            $availableMaterialRolls,
            $boxes,
            $activeShiftSession,
            $assignedCliches,
            $outputRoll,
            $firstError !== '' ? $firstError : 'No se pudo ingresar la tela seleccionada.',
            true
        );
        exit;
    }

    header('Location: /work-orders/' . $workOrderId . '/start');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/waste$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $service->createProductionWaste(
        $workOrderId,
        $currentRoll !== null ? (int)$currentRoll['id'] : null,
        'PRODUCTION',
        (string)($_POST['waste_reason'] ?? ''),
        (float)($_POST['waste_weight_kg'] ?? 0),
        $currentOperatorName
    );
    header('Location: /work-orders/' . $workOrderId . '/start');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/traceability$#', $path, $m) === 1 && $method === 'GET') {
    if (!canAccessWorkOrderTraceability()) {
        http_response_code(403);
        render('Acceso restringido', '<div class="card"><div style="font-size:18px;font-weight:800;margin-bottom:8px">Trazabilidad solo disponible en ERP</div><div class="muted">Los operadores no necesitan acceder a esta vista. La consulta de trazabilidad se realiza desde ERP.</div><div style="margin-top:12px"><a class="btn secondary" href="/work-orders">Volver</a></div></div>');
        exit;
    }

    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }

    $events = $service->listWorkOrderTraceability($workOrderId);
    $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 50);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $inputRolls = [];
    foreach ($rollHistory as $rollEvent) {
        $rollId = (int)($rollEvent['roll_id'] ?? 0);
        if ($rollId > 0 && !isset($inputRolls[$rollId])) {
            $inputRolls[$rollId] = $rollEvent;
        }
    }
    $traceabilityNavParams = inventoryNavigationContext() ? inventoryNavigationParams() : [];
    $traceabilityBackUrl = inventoryNavigationContext() ? inventoryReturnUrl() : '/work-orders';

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Trazabilidad OT ' . h((string)$ot['ot_code']) . '</div>
          <div class="muted">Seguimiento completo de bobinas, tintas y eventos de la orden de trabajo.</div>
        </div>
        <div class="row">
          <a class="btn secondary" href="' . h(withQuery('/work-orders/' . (int)$ot['id'] . '/start', $traceabilityNavParams)) . '">Volver a OT</a>
          <a class="btn secondary" href="' . h($traceabilityBackUrl) . '">' . h(inventoryNavigationContext() ? 'Volver a inventario' : 'Volver a listado') . '</a>
        </div>
      </div>';

    $body .= '<div class="card" style="margin-bottom:12px">
        <div class="row">
          <div style="flex:1;min-width:220px"><div class="muted">OT</div><div style="font-weight:800">' . h((string)$ot['ot_code']) . '</div></div>
          <div style="flex:1;min-width:260px"><div class="muted">SKU final</div><div style="font-weight:800">' . h((string)$ot['sku_final']) . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Estado</div><div style="font-weight:800">' . h(workOrderStatusLabel((string)$ot['status'])) . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Cajas cierre</div><div style="font-weight:800">' . h((string)($lastFinish['box_qty'] ?? '-')) . '</div></div>
        </div>
      </div>';

    $body .= '<div class="card" style="margin-bottom:12px">
        <div style="font-weight:800;margin-bottom:8px">Cadena final OT</div>
        <div class="muted" style="margin-bottom:8px">Confirmación del encadenamiento OT -> bobina entrada -> bobina salida -> corte -> cajas -> pallets.</div>
        <div class="table-wrap"><table class="trace-table"><thead><tr><th>Bobina entrada</th><th>Bobina salida</th><th>Estado corte</th><th>Cajas</th><th>Pallets</th><th></th></tr></thead><tbody>';
    foreach ($outputRolls as $outputRoll) {
        $parentRollId = (int)($outputRoll['parent_roll_id'] ?? 0);
        $isCut = strtoupper((string)($outputRoll['process_stage'] ?? '')) === 'CUT' || strtoupper((string)($outputRoll['status'] ?? '')) === 'CONSUMED';
        $body .= '<tr>';
        if ($parentRollId > 0) {
            $body .= '<td><a href="' . h(withQuery('/rolls/' . $parentRollId, $traceabilityNavParams)) . '">' . h((string)($outputRoll['parent_roll_code'] ?? ('#' . $parentRollId))) . '</a></td>';
        } else {
            $body .= '<td>-</td>';
        }
        $body .= '<td><a href="' . h(withQuery('/rolls/' . (int)$outputRoll['id'], $traceabilityNavParams)) . '">' . h((string)$outputRoll['roll_code']) . '</a></td>';
        $body .= '<td>' . h($isCut ? 'Cortada' : 'Pendiente') . '</td>';
        $body .= '<td>' . h((string)($outputRoll['box_count'] ?? '0')) . '</td>';
        $body .= '<td>' . h((string)($outputRoll['pallet_count'] ?? '0')) . '</td>';
        $body .= '<td><a class="btn secondary" href="' . h(withQuery('/rolls/' . (int)$outputRoll['id'], $traceabilityNavParams)) . '">Ver bobina</a></td>';
        $body .= '</tr>';
    }
    if ($outputRolls === []) {
        foreach ($inputRolls as $inputRoll) {
            $body .= '<tr>';
            $body .= '<td><a href="' . h(withQuery('/rolls/' . (int)$inputRoll['roll_id'], $traceabilityNavParams)) . '">' . h((string)$inputRoll['roll_code']) . '</a></td>';
            $body .= '<td class="muted">Pendiente de generar</td>';
            $body .= '<td>Pendiente</td>';
            $body .= '<td>0</td>';
            $body .= '<td>0</td>';
            $body .= '<td><a class="btn secondary" href="' . h(withQuery('/rolls/' . (int)$inputRoll['roll_id'], $traceabilityNavParams)) . '">Ver bobina</a></td>';
            $body .= '</tr>';
        }
    }
    if ($outputRolls === [] && $inputRolls === []) {
        $body .= '<tr><td colspan="6" class="muted">Aún no hay bobinas ligadas a esta OT.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';

    $body .= '<div class="card" style="margin-bottom:12px">
        <div style="font-weight:800;margin-bottom:8px">Eventos de trazabilidad OT</div>
        <div class="table-wrap"><table class="trace-table"><thead><tr><th>Fecha</th><th>Evento</th><th>Operador</th><th>Detalle</th></tr></thead><tbody>';
    foreach ($events as $event) {
        $body .= '<tr>';
        $body .= '<td>' . h((string)$event['created_at']) . '</td>';
        $body .= '<td>' . h((string)($event['type_label'] ?? $event['type'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($event['operator_name'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($event['detail'] ?? '-')) . '</td>';
        $body .= '</tr>';
    }
    if ($events === []) {
        $body .= '<tr><td colspan="4" class="muted">Sin eventos de trazabilidad para esta OT.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';

    $body .= '<div class="trace-stack">
      <div class="card">
        <div style="font-weight:800;margin-bottom:8px">Trazabilidad de bobinas</div>';
    if ($rollHistory !== []) {
        $rollGroups = [];
        foreach ($rollHistory as $roll) {
            $rollId = (int)($roll['roll_id'] ?? 0);
            if (!isset($rollGroups[$rollId])) {
                $rollGroups[$rollId] = [
                    'roll' => $roll,
                    'events' => [],
                ];
            }
            $rollGroups[$rollId]['events'][] = $roll;
        }

        foreach ($rollGroups as $rollGroup) {
            $roll = $rollGroup['roll'];
            $originParts = [];
            if ((string)($roll['container_code'] ?? '') !== '') {
                $originParts[] = 'Contenedor ' . (string)$roll['container_code'];
            }
            if ((string)($roll['po_code'] ?? '') !== '') {
                $originParts[] = (string)$roll['po_code'];
            }
            if ((string)($roll['supplier_name'] ?? '') !== '') {
                $originParts[] = (string)$roll['supplier_name'];
            }
            $origin = $originParts !== []
                ? implode(' · ', $originParts)
                : ((string)($roll['warehouse_code'] ?? '') !== '' ? 'Bodega ' . (string)$roll['warehouse_code'] : '-');

            $body .= '<div class="trace-roll-card">';
            $body .= '<div class="trace-roll-header">';
            $body .= '<div>';
            $body .= '<div class="trace-roll-title">' . h((string)$roll['roll_code']) . '</div>';
            $body .= '<div class="muted trace-roll-subtitle">Código SKU ' . h((string)$roll['sku_code']) . ' · ID ' . (int)$roll['roll_id'] . '</div>';
            $body .= '</div>';
            $body .= '<div><a class="trace-roll-link" href="' . h(withQuery('/rolls/' . (int)$roll['roll_id'], $traceabilityNavParams)) . '">Ver trazabilidad bobina</a></div>';
            $body .= '</div>';

            $body .= '<div class="trace-roll-meta">';
            $body .= '<div class="trace-roll-stat"><div class="muted">Origen</div><div class="value">' . h($origin) . '</div></div>';
            $body .= '<div class="trace-roll-stat"><div class="muted">Contenedor</div><div class="value">' . h(trim((string)($roll['container_code'] ?? '')) !== '' ? (string)$roll['container_code'] : '-') . '</div></div>';
            $body .= '<div class="trace-roll-stat"><div class="muted">Bodega actual</div><div class="value">' . h((string)($roll['warehouse_code'] ?? '-')) . '</div></div>';
            $body .= '<div class="trace-roll-stat"><div class="muted">Peso actual</div><div class="value">' . h((string)($roll['weight_kg'] ?? '-')) . ' Kg</div></div>';
            $body .= '</div>';

            $body .= '<div class="trace-roll-events">';
            foreach ($rollGroup['events'] as $eventRoll) {
                $payload = $eventRoll['payload_data'] ?? [];
                $action = (string)$eventRoll['type'] === 'WORK_ORDER_ROLL_ATTACHED' ? 'Ingreso a OT' : 'Salida de OT';
                $weight = (string)$eventRoll['type'] === 'WORK_ORDER_ROLL_ATTACHED'
                    ? (string)($payload['process_weight_kg'] ?? '-')
                    : (string)($payload['final_weight_kg'] ?? '-');
                $detail = (string)$eventRoll['type'] === 'WORK_ORDER_ROLL_RELEASED'
                    ? ('Motivo: ' . (string)($payload['reason'] ?? '-'))
                    : ('Operador: ' . (string)($payload['operator_name'] ?? '-'));

                $body .= '<div class="item">';
                $body .= '<div class="trace-roll-event-top"><div style="font-weight:700">' . h($action) . '</div><div class="muted">' . h((string)$eventRoll['created_at']) . '</div></div>';
                $body .= '<div class="trace-roll-event-line">Peso ' . h($weight) . ' Kg · Merma ' . h((string)($payload['waste_kg'] ?? '0')) . ' Kg · ' . h($detail) . '</div>';
                $body .= '</div>';
            }
            $body .= '</div>';
            $body .= '</div>';
        }
    } else {
        $body .= '<div class="muted">Sin trazabilidad de bobinas.</div>';
    }
    $body .= '</div>
      <div class="card">
        <div style="font-weight:800;margin-bottom:8px">Trazabilidad de tintas</div>
        <div class="table-wrap"><table class="trace-table"><thead><tr><th>Fecha</th><th>Tinta</th><th>Peso</th><th>Operador</th></tr></thead><tbody>';
    foreach ($chemicalInputs as $input) {
        $body .= '<tr>';
        $body .= '<td>' . h((string)$input['created_at']) . '</td>';
        $body .= '<td>' . h((string)$input['chemical_code']) . ' - ' . h((string)($input['chemical_name'] ?? '')) . '</td>';
        $body .= '<td>' . h((string)$input['weight_kg']) . ' Kg</td>';
        $body .= '<td>' . h((string)($input['operator_name'] ?? '-')) . '</td>';
        $body .= '</tr>';
    }
    if ($chemicalInputs === []) {
        $body .= '<tr><td colspan="4" class="muted">Sin trazabilidad de tintas.</td></tr>';
    }
    $body .= '</tbody></table></div>
      </div>
      <div class="card">
        <div style="font-weight:800;margin-bottom:8px">Cajas generadas</div>
        <div class="table-wrap"><table class="trace-table"><thead><tr><th>Caja</th><th>Bobina origen</th><th>Unidades</th><th>Destino</th><th>Bodega</th><th>Pallet</th><th>Operador</th></tr></thead><tbody>';
    foreach ($boxes as $box) {
        $destination = (string)($box['destination_mode'] ?? '') === 'CUSTOMER_ORDER'
            ? 'OC cliente ' . (string)($box['customer_order_ref'] ?? '-')
            : 'Inventario';
        $warehouseLabel = trim((string)($box['warehouse_code'] ?? ''));
        if ($warehouseLabel === '') {
            $warehouseLabel = '-';
        }
        $body .= '<tr>';
        $body .= '<td><a href="' . h(withQuery('/boxes/' . (int)$box['id'], $traceabilityNavParams)) . '">' . h((string)$box['box_code']) . '</a></td>';
        $body .= '<td>' . h((string)($box['source_roll_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($box['units_qty'] ?? '-')) . '</td>';
        $body .= '<td>' . h($destination) . '</td>';
        $body .= '<td>' . h($warehouseLabel) . '</td>';
        $body .= '<td>' . h((string)($box['pallet_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($box['operator_name'] ?? '-')) . '</td>';
        $body .= '</tr>';
    }
    if ($boxes === []) {
        $body .= '<tr><td colspan="7" class="muted">Sin cajas registradas para esta OT.</td></tr>';
    }
    $body .= '</tbody></table></div>
      </div>
      <div class="card">
        <div style="font-weight:800;margin-bottom:8px">Pallets generados</div>
        <div class="table-wrap"><table class="trace-table"><thead><tr><th>Pallet</th><th>Bobina origen</th><th>Cajas</th><th>Destino</th><th>Bodega</th><th>Operador</th></tr></thead><tbody>';
    foreach ($pallets as $pallet) {
        $destination = (string)($pallet['destination_mode'] ?? '') === 'CUSTOMER_ORDER'
            ? 'OC cliente ' . (string)($pallet['customer_order_ref'] ?? '-')
            : 'Inventario';
        $warehouseLabel = trim((string)($pallet['warehouse_code'] ?? ''));
        if ($warehouseLabel === '') {
            $warehouseLabel = '-';
        }
        $body .= '<tr>';
        $body .= '<td><a href="' . h(withQuery('/pallets/' . (int)$pallet['id'], $traceabilityNavParams)) . '">' . h((string)$pallet['pallet_code']) . '</a></td>';
        $body .= '<td>' . h((string)($pallet['source_roll_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($pallet['box_count'] ?? '-')) . '</td>';
        $body .= '<td>' . h($destination) . '</td>';
        $body .= '<td>' . h($warehouseLabel) . '</td>';
        $body .= '<td>' . h((string)($pallet['operator_name'] ?? '-')) . '</td>';
        $body .= '</tr>';
    }
    if ($pallets === []) {
        $body .= '<tr><td colspan="6" class="muted">Sin pallets registrados para esta OT.</td></tr>';
    }
    $body .= '</tbody></table></div>
      </div>
    </div>';

    render('Trazabilidad OT', $body);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/attach-roll$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $scanCode = trim((string)($_POST['scan_code'] ?? ''));
    $roll = $service->getRollByScanCode($scanCode);
    if ($roll === null) {
        $chemicals = $service->listChemicals();
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            'No se encontró la bobina escaneada.',
            true,
            [
                'scan_code' => $scanCode,
                'process_weight_kg' => (string)($_POST['process_weight_kg'] ?? ''),
                'process_waste_kg' => (string)($_POST['process_waste_kg'] ?? '0'),
            ]
        );
        exit;
    }
    $result = $service->attachRollToWorkOrder(
        $workOrderId,
        (int)$roll['id'],
        (float)($_POST['process_weight_kg'] ?? 0),
        (float)($_POST['process_waste_kg'] ?? 0),
        $currentOperatorName
    );
    if ($result['ok'] !== true) {
        $chemicals = $service->listChemicals();
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            implode(' ', array_map('strval', array_values($result['errors']))),
            true,
            [
                'scan_code' => $scanCode,
                'process_weight_kg' => (string)($_POST['process_weight_kg'] ?? ''),
                'process_waste_kg' => (string)($_POST['process_waste_kg'] ?? '0'),
            ]
        );
        exit;
    }
    header('Location: /work-orders/' . $workOrderId . '/start?roll_attached=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/chemical-input$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $chemicalId = (int)($_POST['chemical_id'] ?? 0);
    $chemicalWeight = (float)($_POST['chemical_weight_kg'] ?? 0);
    $result = $service->createChemicalInput($workOrderId, $chemicalId, $chemicalWeight, $currentOperatorName);
    if ($result['ok'] !== true) {
        $chemicals = $service->listChemicals();
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            implode(' ', array_map('strval', array_values($result['errors']))),
            true,
            [
                'chemical_id' => (string)$chemicalId,
                'chemical_weight_kg' => (string)($_POST['chemical_weight_kg'] ?? ''),
            ]
        );
        exit;
    }
    header('Location: /work-orders/' . $workOrderId . '/start?chemical_input=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/change-roll$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $nextScanCode = trim((string)($_POST['change_scan_code'] ?? ''));
    $nextRoll = $service->getRollByScanCode($nextScanCode);
    if ($nextRoll === null) {
        $chemicals = $service->listChemicals();
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            'No se encontró la nueva bobina escaneada.',
            true,
            [
                'change_scan_code' => $nextScanCode,
                'change_final_roll_weight_kg' => (string)($_POST['change_final_roll_weight_kg'] ?? ''),
                'change_waste_kg' => (string)($_POST['change_waste_kg'] ?? '0'),
                'change_output_roll_weight_kg' => (string)($_POST['change_output_roll_weight_kg'] ?? ''),
                'change_next_process_weight_kg' => (string)($_POST['change_next_process_weight_kg'] ?? ''),
                'change_next_waste_kg' => (string)($_POST['change_next_waste_kg'] ?? '0'),
            ]
        );
        exit;
    }
    $result = $service->changeRollInWorkOrder(
        $workOrderId,
        (int)$nextRoll['id'],
        (float)($_POST['change_final_roll_weight_kg'] ?? 0),
        (float)($_POST['change_waste_kg'] ?? 0),
        (float)($_POST['change_output_roll_weight_kg'] ?? 0),
        (float)($_POST['change_next_process_weight_kg'] ?? 0),
        (float)($_POST['change_next_waste_kg'] ?? 0),
        $currentOperatorName
    );
    if ($result['ok'] !== true) {
        $chemicals = $service->listChemicals();
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            implode(' ', array_map('strval', array_values($result['errors']))),
            true,
            [
                'change_scan_code' => $nextScanCode,
                'change_final_roll_weight_kg' => (string)($_POST['change_final_roll_weight_kg'] ?? ''),
                'change_waste_kg' => (string)($_POST['change_waste_kg'] ?? '0'),
                'change_output_roll_weight_kg' => (string)($_POST['change_output_roll_weight_kg'] ?? ''),
                'change_next_process_weight_kg' => (string)($_POST['change_next_process_weight_kg'] ?? ''),
                'change_next_waste_kg' => (string)($_POST['change_next_waste_kg'] ?? '0'),
            ]
        );
        exit;
    }
    $printed = '0';
    $labelRollId = (int)($result['output_roll_id'] ?? 0);
    if ($labelRollId > 0 && $printer->isEnabled()) {
        $roll = $service->getRoll($labelRollId);
        if (is_array($roll)) {
            $p = $printer->printRollLabel($roll);
            if (($p['ok'] ?? false) === true) {
                $printed = '1';
            }
        }
    }
    header('Location: /work-orders/' . $workOrderId . '/start?roll_changed=1&printed=' . $printed);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/start$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $result = $service->startWorkOrder($workOrderId, $currentOperatorName);
    if ($result['ok'] !== true) {
        $ot = $service->getWorkOrder($workOrderId);
        if ($ot === null) {
            http_response_code(404);
            render('No encontrado', '<div class="card">No existe la OT.</div>');
            exit;
        }
        $chemicals = $service->listChemicals();
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            implode(' ', array_map('strval', array_values($result['errors']))),
            true
        );
        exit;
    }
    header('Location: /work-orders/' . $workOrderId . '/start');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/finish$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $result = $service->finishWorkOrder(
        $workOrderId,
        (float)($_POST['finish_final_roll_weight_kg'] ?? 0),
        (float)($_POST['finish_final_chemical_weight_kg'] ?? 0),
        (float)($_POST['finish_waste_kg'] ?? 0),
        (int)($_POST['finish_box_qty'] ?? 0),
        (float)($_POST['finish_output_roll_weight_kg'] ?? 0),
        $currentOperatorName,
        (float)($_POST['finish_production_meters'] ?? 0),
        (string)($_POST['finish_comments'] ?? '')
    );
    if ($result['ok'] !== true) {
        $chemicals = $service->listChemicals();
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            implode(' ', array_map('strval', array_values($result['errors']))),
            true,
            [
                'show_finish_data' => '1',
                'finish_final_roll_weight_kg' => (string)($_POST['finish_final_roll_weight_kg'] ?? ''),
                'finish_final_chemical_weight_kg' => (string)($_POST['finish_final_chemical_weight_kg'] ?? ''),
                'finish_box_qty' => (string)($_POST['finish_box_qty'] ?? ''),
                'finish_output_roll_weight_kg' => (string)($_POST['finish_output_roll_weight_kg'] ?? ''),
                'finish_waste_kg' => (string)($_POST['finish_waste_kg'] ?? '0'),
                'finish_production_meters' => (string)($_POST['finish_production_meters'] ?? ''),
                'finish_comments' => (string)($_POST['finish_comments'] ?? ''),
                'finish_chemical_return_kg' => is_array($_POST['finish_chemical_return_kg'] ?? null) ? $_POST['finish_chemical_return_kg'] : [],
                'finish_waste_detail_weight' => is_array($_POST['finish_waste_detail_weight'] ?? null) ? $_POST['finish_waste_detail_weight'] : [],
                'finish_waste_detail_comment' => is_array($_POST['finish_waste_detail_comment'] ?? null) ? $_POST['finish_waste_detail_comment'] : [],
            ],
            [],
            [],
            [],
            [],
            null,
            [],
            array_values(array_filter($service->listWarehouses(), static fn(array $warehouse): bool => in_array((int)$warehouse['code'], [700, 1000], true)))
        );
        exit;
    }
    $printed = '0';
    $boxPrinted = '';
    $finishBoxQty = max(0, (int)($_POST['finish_box_qty'] ?? 0));
    $labelRollId = (int)($result['output_roll_id'] ?? 0);
    if ($labelRollId > 0 && $printer->isEnabled()) {
        $roll = $service->getRoll($labelRollId);
        if (is_array($roll)) {
            $p = $printer->printRollLabel($roll);
            if (($p['ok'] ?? false) === true) {
                $printed = '1';
            }
        }
        if ($finishBoxQty > 0) {
            $box = $printer->printWorkOrderBoxLabel($ot, [
                'created_at' => date('Y-m-d H:i:s'),
                'box_qty' => $finishBoxQty,
                'operator_name' => $currentOperatorName,
            ]);
            $boxPrinted = ($box['ok'] ?? false) === true ? '1' : '0';
        }
    }
    $qs = '?printed=' . $printed;
    if ($boxPrinted !== '') {
        $qs .= '&box_printed=' . $boxPrinted;
    }
    if ($labelRollId > 0) {
        $qs .= '&label_roll_id=' . $labelRollId;
    }
    header('Location: /work-orders/' . $workOrderId . '/finish-approval' . $qs);
    exit;
}

if ($path === '/chemicals/weighings' && $method === 'GET') {
    $active = $service->getActiveWorkOrder();
    $activeId = is_array($active) ? (int)$active['id'] : 0;
    $chemicals = $service->listChemicals();
    $weighings = $service->listRecentChemicalWeighings();

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Pesaje de tintas</div>
          <div class="muted">Registro de peso inicial y retorno para calcular consumo neto por OT.</div>
        </div>
        <a class="btn" href="/chemicals/weighings/new">Nuevo pesaje</a>
      </div>';

    if ($activeId <= 0) {
        $body .= '<div class="err" style="margin-bottom:12px">No hay OT activa. Activa una OT en <a href="/work-orders">OT</a> para registrar consumos.</div>';
    }

    $body .= '<div class="card"><table><thead><tr>
        <th>ID</th><th>OT</th><th>SKU final</th><th>Tinta</th><th>Inicial (Kg)</th><th>Retorno (Kg)</th><th>Consumo (Kg)</th><th>Fecha</th>
      </tr></thead><tbody>';
    foreach ($weighings as $w) {
        $body .= '<tr>';
        $body .= '<td>' . (int)$w['id'] . '</td>';
        $body .= '<td>' . h((string)$w['ot_code']) . '</td>';
        $body .= '<td>' . h((string)$w['sku_final']) . '</td>';
        $body .= '<td>' . h((string)$w['chemical_code']) . '</td>';
        $body .= '<td>' . h((string)$w['initial_weight_kg']) . '</td>';
        $body .= '<td>' . h((string)$w['return_weight_kg']) . '</td>';
        $body .= '<td>' . h((string)$w['net_consumption_kg']) . '</td>';
        $body .= '<td>' . h((string)$w['created_at']) . '</td>';
        $body .= '</tr>';
    }
    if ($weighings === []) {
        $body .= '<tr><td colspan="8" class="muted">Sin registros todavía.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    render('Tintas', $body);
    exit;
}

if ($path === '/chemicals/weighings/new' && $method === 'GET') {
    $active = $service->getActiveWorkOrder();
    $activeId = is_array($active) ? (int)$active['id'] : 0;
    $chemicals = $service->listChemicals();

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Nuevo pesaje de tintas</div>
        <a class="btn secondary" href="/chemicals/weighings">Volver</a>
      </div>';

    if ($activeId <= 0) {
        $body .= '<div class="err" style="margin-bottom:12px">No hay OT activa. Activa una OT en <a href="/work-orders">OT</a>.</div>';
        render('Nuevo pesaje', $body);
        exit;
    }

    $body .= '<div class="card">
        <form method="post" action="/chemicals/weighings">
          <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
          <input type="hidden" name="work_order_id" value="' . (int)$activeId . '">
          <div class="row">
            <div style="flex:1;min-width:260px">
              <label>OT activa</label>
              <input type="text" value="' . h((string)$active['ot_code']) . ' - ' . h((string)$active['sku_final']) . '" disabled>
            </div>
            <div style="flex:1;min-width:260px">
              <label>Tinta</label>
              <select name="chemical_id" required>
                <option value="">Seleccionar</option>';
    foreach ($chemicals as $c) {
        $body .= '<option value="' . (int)$c['id'] . '">' . h((string)$c['code']) . ' - ' . h((string)$c['name']) . '</option>';
    }
    $body .= '</select>
            </div>
          </div>

          <div class="row" style="margin-top:10px;align-items:end">
            <div style="flex:1;min-width:220px">
              <label>Peso inicial (Kg)</label>
              <input id="initial_weight_kg" name="initial_weight_kg" type="number" step="0.001" min="0" required>
            </div>
            <div style="min-width:220px">
              <button class="btn secondary" type="button" id="read_scale_initial">Leer balanza</button>
            </div>
          </div>

          <div class="row" style="margin-top:10px;align-items:end">
            <div style="flex:1;min-width:220px">
              <label>Peso retorno (Kg)</label>
              <input id="return_weight_kg" name="return_weight_kg" type="number" step="0.001" min="0" required>
            </div>
            <div style="min-width:220px">
              <button class="btn secondary" type="button" id="read_scale_return">Leer balanza</button>
            </div>
          </div>

          <div style="margin-top:12px">
            <button class="btn" type="submit">Guardar pesaje</button>
          </div>
        </form>
        <script>
          (function () {
            async function readTo(inputId, btnId) {
              var btn = document.getElementById(btnId);
              var input = document.getElementById(inputId);
              if (!btn || !input) return;
              btn.addEventListener("click", async function () {
                btn.disabled = true;
                btn.textContent = "Leyendo...";
                try {
                  var res = await fetch("/api/scale/weight", { cache: "no-store" });
                  var data = await res.json();
                  if (!data || data.ok !== true) throw new Error((data && data.error) ? data.error : "No se pudo leer la balanza.");
                  input.value = data.weight_kg;
                } catch (e) {
                  alert(e && e.message ? e.message : "Error leyendo la balanza.");
                } finally {
                  btn.disabled = false;
                  btn.textContent = "Leer balanza";
                }
              });
            }
            readTo("initial_weight_kg", "read_scale_initial");
            readTo("return_weight_kg", "read_scale_return");
          })();
        </script>
      </div>';

    render('Nuevo pesaje', $body);
    exit;
}

if ($path === '/chemicals/weighings' && $method === 'POST') {
    requireCsrf();
    $result = $service->createChemicalWeighing($_POST);
    if ($result['ok'] === true) {
        header('Location: /chemicals/weighings');
        exit;
    }

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Nuevo pesaje de tintas</div>
        <a class="btn secondary" href="/chemicals/weighings">Volver</a>
      </div>';
    $body .= '<div class="err" style="margin-bottom:12px"><ul style="margin:0;padding-left:16px">';
    foreach ($result['errors'] as $msg) {
        $body .= '<li>' . h((string)$msg) . '</li>';
    }
    $body .= '</ul></div>';
    $body .= '<div class="card"><a class="btn secondary" href="/chemicals/weighings/new">Volver</a></div>';
    render('Nuevo pesaje', $body);
    exit;
}


if ($path === '/stock/chemicals' && $method === 'GET') {
    header('Location: /stock');
    exit;
}

if ($path === '/stock/material-requests' && $method === 'GET') {
    $requests = $service->listAllMaterialRequests(300);

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Solicitudes de materiales</div>
          <div class="muted">Vista centralizada de solicitudes hechas por producción hacia bodega.</div>
        </div>
        <div class="row">
          <a class="btn secondary" href="/mobile/picking">Vista móvil</a>
          <a class="btn secondary" href="/stock">Volver a inventario</a>
        </div>
      </div>';

    $body .= '<div class="card">
        <table><thead><tr><th>Fecha</th><th>Solicita</th><th>Qué solicita</th><th>Cantidad</th><th>Entregadas</th><th>OT</th><th>Estado</th><th>Última entrega</th><th>Acción</th></tr></thead><tbody>';
    foreach ($requests as $request) {
        $lastDelivered = trim((string)($request['delivered_roll_code'] ?? ''));
        $requestOperational = workOrderCanReceiveMaterials((string)($request['work_order_status'] ?? ''));
        $actionLabel = $requestOperational ? 'Atender' : 'Ver bloqueada';
        $body .= '<tr>';
        $body .= '<td>' . h((string)$request['created_at']) . '</td>';
        $body .= '<td>' . h((string)$request['requested_by']) . '</td>';
        $body .= '<td>' . h((string)$request['requested_item']) . '</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($request['requested_qty'] ?? 0), 'Unid.')) . '</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($request['delivered_qty'] ?? 0), 'Unid.')) . '</td>';
        $body .= '<td><a href="/work-orders/' . (int)$request['work_order_id'] . '/start">' . h((string)$request['ot_code']) . '</a></td>';
        $body .= '<td>' . h(materialRequestStatusLabel((string)$request['status'])) . ($requestOperational ? '' : '<div class="muted">OT no operativa</div>') . '</td>';
        $body .= '<td>' . h($lastDelivered !== '' ? ($lastDelivered . ' · ' . (string)($request['delivered_by'] ?? '-')) : '-') . '</td>';
        $body .= '<td><a class="btn secondary" href="/stock/material-requests/' . (int)$request['id'] . '">' . h($actionLabel) . '</a></td>';
        $body .= '</tr>';
    }
    if ($requests === []) {
        $body .= '<tr><td colspan="9" class="muted">No hay solicitudes registradas todavía.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    render('Solicitudes', $body);
    exit;
}

if ($path === '/mobile/picking' && $method === 'GET') {
    $requests = array_values(array_filter(
        $service->listAllMaterialRequests(200),
        static fn(array $request): bool => in_array((string)($request['status'] ?? ''), ['PENDING', 'ACCEPTED', 'PARTIAL'], true)
            && workOrderCanReceiveMaterials((string)($request['work_order_status'] ?? ''))
    ));

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:800">Picking móvil</div>
          <div class="muted">Solicitudes abiertas para atención rápida desde celular o tablet.</div>
        </div>
        <a class="btn secondary" href="/stock/material-requests">Vista escritorio</a>
      </div>';

    foreach ($requests as $request) {
        $pendingQty = max(0, (float)($request['requested_qty'] ?? 0) - (float)($request['delivered_qty'] ?? 0));
        $body .= '<div class="card" style="margin-bottom:12px;padding:16px">
          <div class="row" style="justify-content:space-between;align-items:flex-start;margin-bottom:8px">
            <div>
              <div style="font-size:17px;font-weight:800">' . h((string)$request['requested_item']) . '</div>
              <div class="muted" style="margin-top:4px">OT ' . h((string)$request['ot_code']) . ' · ' . h((string)$request['requested_by']) . '</div>
            </div>
            <div class="panel" style="padding:8px 10px;font-weight:800">' . h(materialRequestStatusLabel((string)$request['status'])) . '</div>
          </div>
          <div class="row" style="margin-bottom:10px">
            <div style="flex:1;min-width:130px"><div class="muted">Tipo</div><div style="font-weight:800">' . h(materialRequestTypeLabel((string)($request['request_type'] ?? 'ROLL'))) . '</div></div>
            <div style="flex:1;min-width:130px"><div class="muted">Pendiente</div><div style="font-weight:800">' . h(formatReceptionValue($pendingQty, (string)($request['requested_unit'] ?? 'Unid.'))) . ' ' . h((string)($request['requested_unit'] ?? 'Unid.')) . '</div></div>
            <div style="flex:1;min-width:130px"><div class="muted">Fecha</div><div style="font-weight:800">' . h((string)$request['created_at']) . '</div></div>
          </div>
          <a class="btn" style="width:100%;text-align:center" href="/mobile/picking/' . (int)$request['id'] . '">Abrir solicitud</a>
        </div>';
    }

    if ($requests === []) {
        $body .= '<div class="card"><div class="muted">No hay solicitudes abiertas para picking en este momento.</div></div>';
    }

    render('Picking móvil', $body);
    exit;
}

if (preg_match('#^/stock/material-requests/(\d+)/accept$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $requestId = (int)$m[1];
    $returnMode = (string)($_POST['return_mode'] ?? '') === 'mobile' ? 'mobile' : 'default';
    $result = $service->acceptMaterialRequest($requestId, $currentOperatorName);
    $query = (($result['ok'] ?? false) === true)
        ? '?ok=' . rawurlencode('Solicitud aceptada por bodega.')
        : '?error=' . rawurlencode((string)reset($result['errors']));
    $redirectBase = $returnMode === 'mobile'
        ? '/mobile/picking/' . $requestId
        : '/stock/material-requests/' . $requestId;
    header('Location: ' . $redirectBase . $query);
    exit;
}

if (preg_match('#^/stock/material-requests/(\d+)/scan$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $requestId = (int)$m[1];
    $returnMode = (string)($_POST['return_mode'] ?? '') === 'mobile' ? 'mobile' : 'default';
    $redirectBase = $returnMode === 'mobile'
        ? '/mobile/picking/' . $requestId
        : '/stock/material-requests/' . $requestId;
    $scanCode = trim((string)($_POST['scan_code'] ?? ''));
    if ($scanCode === '') {
        header('Location: ' . $redirectBase . '?error=' . rawurlencode('Debes escanear el código de barras de la bobina.'));
        exit;
    }

    $roll = $service->getRollByScanCode($scanCode);
    if ($roll === null) {
        header('Location: ' . $redirectBase . '?error=' . rawurlencode('No se encontró una bobina con ese código.'));
        exit;
    }

    $result = $service->deliverMaterialRequest($requestId, (int)$roll['id'], $currentOperatorName);
    $query = (($result['ok'] ?? false) === true)
        ? '?ok=' . rawurlencode('Bobina ingresada correctamente a la solicitud.')
        : '?error=' . rawurlencode((string)reset($result['errors']));
    header('Location: ' . $redirectBase . $query);
    exit;
}

if (preg_match('#^/stock/material-requests/(\d+)/deliver-manual$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $requestId = (int)$m[1];
    $returnMode = (string)($_POST['return_mode'] ?? '') === 'mobile' ? 'mobile' : 'default';
    $result = $service->deliverGenericMaterialRequest(
        $requestId,
        (float)($_POST['delivered_qty'] ?? 0),
        (string)($_POST['delivery_note'] ?? ''),
        $currentOperatorName
    );
    $query = (($result['ok'] ?? false) === true)
        ? '?ok=' . rawurlencode('Material entregado correctamente.')
        : '?error=' . rawurlencode((string)reset($result['errors']));
    $redirectBase = $returnMode === 'mobile'
        ? '/mobile/picking/' . $requestId
        : '/stock/material-requests/' . $requestId;
    header('Location: ' . $redirectBase . $query);
    exit;
}

if (preg_match('#^/mobile/picking/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $requestId = (int)$m[1];
    $request = $service->getMaterialRequest($requestId);
    if ($request === null) {
        render('No encontrado', '<div class="card">No existe la solicitud.</div>');
        exit;
    }

    $deliveries = $service->listMaterialDeliveriesByRequest($requestId);
    $flashOk = trim((string)($_GET['ok'] ?? ''));
    $flashError = trim((string)($_GET['error'] ?? ''));
    $requestedQty = (float)($request['requested_qty'] ?? 0);
    $deliveredQty = (float)($request['delivered_qty'] ?? 0);
    $pendingQty = max(0, $requestedQty - $deliveredQty);
    $requestOperational = workOrderCanReceiveMaterials((string)($request['work_order_status'] ?? ''));

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:800">Picking móvil</div>
          <div class="muted">Atención rápida de solicitud desde dispositivo móvil.</div>
        </div>
        <a class="btn secondary" href="/mobile/picking">Volver</a>
      </div>';

    if ($flashOk !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($flashOk) . '</div>';
    }
    if ($flashError !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($flashError) . '</div>';
    }
    if (!$requestOperational) {
        $body .= '<div class="err" style="margin-bottom:12px">La OT ya no está disponible para seguir atendiendo esta solicitud.</div>';
    }

    $body .= '<div class="card" style="margin-bottom:12px;padding:16px">
        <div style="font-size:18px;font-weight:800;margin-bottom:6px">' . h((string)$request['requested_item']) . '</div>
        <div class="row" style="margin-bottom:10px">
          <div style="flex:1;min-width:120px"><div class="muted">OT</div><div style="font-weight:800">' . h((string)$request['ot_code']) . '</div></div>
          <div style="flex:1;min-width:120px"><div class="muted">Solicita</div><div style="font-weight:800">' . h((string)$request['requested_by']) . '</div></div>
          <div style="flex:1;min-width:120px"><div class="muted">Estado</div><div style="font-weight:800">' . h(materialRequestStatusLabel((string)$request['status'])) . '</div></div>
        </div>
        <div class="row">
          <div style="flex:1;min-width:120px"><div class="muted">Tipo</div><div style="font-weight:800">' . h(materialRequestTypeLabel((string)($request['request_type'] ?? 'ROLL'))) . '</div></div>
          <div style="flex:1;min-width:120px"><div class="muted">Pedido</div><div style="font-weight:800">' . h(formatReceptionValue($requestedQty, (string)($request['requested_unit'] ?? 'Unid.'))) . ' ' . h((string)($request['requested_unit'] ?? 'Unid.')) . '</div></div>
          <div style="flex:1;min-width:120px"><div class="muted">Pendiente</div><div style="font-weight:800">' . h(formatReceptionValue($pendingQty, (string)($request['requested_unit'] ?? 'Unid.'))) . ' ' . h((string)($request['requested_unit'] ?? 'Unid.')) . '</div></div>
        </div>
      </div>';

    if ($requestOperational && (string)$request['status'] === 'PENDING') {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:10px">Aceptar solicitud</div>
          <form method="post" action="/stock/material-requests/' . $requestId . '/accept">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <input type="hidden" name="return_mode" value="mobile">
            <button class="btn" style="width:100%" type="submit">Tomar solicitud</button>
          </form>
        </div>';
    } elseif (!$requestOperational) {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Solicitud bloqueada</div>
          <div class="row">
            <div style="flex:1;min-width:140px"><div class="muted">Aceptó</div><div style="font-weight:800">' . h((string)($request['accepted_by'] ?? '-')) . '</div></div>
            <div style="flex:1;min-width:140px"><div class="muted">Fecha</div><div style="font-weight:800">' . h((string)($request['accepted_at'] ?? '-')) . '</div></div>
          </div>
        </div>';
    } else {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Solicitud tomada</div>
          <div class="row">
            <div style="flex:1;min-width:140px"><div class="muted">Aceptó</div><div style="font-weight:800">' . h((string)($request['accepted_by'] ?? '-')) . '</div></div>
            <div style="flex:1;min-width:140px"><div class="muted">Fecha</div><div style="font-weight:800">' . h((string)($request['accepted_at'] ?? '-')) . '</div></div>
          </div>
        </div>';
    }

    if ($requestOperational && $pendingQty > 0 && in_array((string)$request['status'], ['ACCEPTED', 'PARTIAL'], true) && (string)($request['request_type'] ?? 'ROLL') === 'ROLL') {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Escaneo de bobina</div>
          <form method="post" action="/stock/material-requests/' . $requestId . '/scan">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <input type="hidden" name="return_mode" value="mobile">
            <label>Código de barras / ID</label>
            <input id="request_scan_code" name="scan_code" type="text" value="" autofocus placeholder="Escanear bobina o escribir código">
            <button class="btn" style="width:100%;margin-top:10px" type="submit">Ingresar bobina</button>
          </form>
        </div>';

        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Cámara</div>
          <div class="muted" id="camera_scan_status" style="margin-bottom:10px">Abre la cámara trasera o toma una foto del código de barras.</div>
          <div class="row" style="margin-bottom:10px">
            <button class="btn secondary" style="flex:1" type="button" id="camera_scan_start">Abrir cámara</button>
            <button class="btn secondary" style="flex:1" type="button" id="camera_scan_stop" disabled>Detener</button>
            <button class="btn secondary" style="flex:1" type="button" id="camera_scan_capture">Tomar foto</button>
            <input type="file" id="camera_scan_file" accept="image/*" capture="environment" style="display:none">
          </div>
          <div style="background:#111;border:1px solid #cfd5da;padding:8px">
            <video id="camera_scan_video" playsinline muted style="width:100%;max-height:320px;display:block;background:#000"></video>
          </div>
        </div>';

        $body .= '<script>
          (function () {
            var form = document.querySelector(\'form[action="/stock/material-requests/' . $requestId . '/scan"]\');
            var input = document.getElementById("request_scan_code");
            var startButton = document.getElementById("camera_scan_start");
            var stopButton = document.getElementById("camera_scan_stop");
            var captureButton = document.getElementById("camera_scan_capture");
            var fileInput = document.getElementById("camera_scan_file");
            var video = document.getElementById("camera_scan_video");
            var statusBox = document.getElementById("camera_scan_status");
            if (!form || !input || !startButton || !stopButton || !captureButton || !fileInput || !video || !statusBox) {
              return;
            }

            var stream = null;
            var detector = null;
            var scanTimer = null;
            var isRunning = false;
            var isSubmitting = false;

            function setStatus(message, isError) {
              statusBox.textContent = message;
              statusBox.style.color = isError ? "#b42318" : "#475467";
            }

            function cleanupCamera() {
              if (scanTimer !== null) {
                window.clearTimeout(scanTimer);
                scanTimer = null;
              }
              if (stream) {
                stream.getTracks().forEach(function (track) { track.stop(); });
                stream = null;
              }
              video.srcObject = null;
              isRunning = false;
              startButton.disabled = false;
              stopButton.disabled = true;
            }

            function insecureContextMessage() {
              if (window.isSecureContext) {
                return "";
              }
              if (location.protocol === "http:" && location.hostname !== "localhost" && location.hostname !== "127.0.0.1") {
                return "La cámara en vivo está bloqueada porque abriste el sistema por HTTP en red local. En celular/tablet esto normalmente requiere HTTPS.";
              }
              return "";
            }

            async function buildDetector() {
              if (!("BarcodeDetector" in window)) {
                return null;
              }
              try {
                var supported = typeof BarcodeDetector.getSupportedFormats === "function"
                  ? await BarcodeDetector.getSupportedFormats()
                  : [];
                var preferred = ["code_39", "code_128", "ean_13", "ean_8", "upc_a", "upc_e"];
                var formats = preferred.filter(function (item) { return supported.indexOf(item) !== -1; });
                return formats.length > 0 ? new BarcodeDetector({ formats: formats }) : new BarcodeDetector();
              } catch (error) {
                return new BarcodeDetector();
              }
            }

            async function scanLoop() {
              if (!isRunning || !detector || isSubmitting) {
                return;
              }
              try {
                var codes = await detector.detect(video);
                if (codes && codes.length > 0) {
                  var rawValue = (codes[0].rawValue || "").trim();
                  if (rawValue !== "") {
                    input.value = rawValue;
                    isSubmitting = true;
                    setStatus("Código detectado. Ingresando bobina...", false);
                    cleanupCamera();
                    form.submit();
                    return;
                  }
                }
              } catch (error) {
                setStatus("No se pudo leer el código todavía. Mantén la cámara enfocando la etiqueta.", false);
              }
              scanTimer = window.setTimeout(scanLoop, 250);
            }

            async function detectFromFile(file) {
              detector = await buildDetector();
              if (!detector) {
                setStatus("Este navegador no soporta leer códigos desde cámara o foto. Usa Chrome o Edge actualizado.", true);
                return;
              }
              try {
                var bitmap = await createImageBitmap(file);
                var codes = await detector.detect(bitmap);
                if (bitmap && typeof bitmap.close === "function") {
                  bitmap.close();
                }
                if (codes && codes.length > 0) {
                  var rawValue = (codes[0].rawValue || "").trim();
                  if (rawValue !== "") {
                    input.value = rawValue;
                    isSubmitting = true;
                    setStatus("Código detectado desde la foto. Ingresando bobina...", false);
                    form.submit();
                    return;
                  }
                }
                setStatus("No se pudo leer el código desde la foto. Acércate más a la etiqueta o mejora la luz.", true);
              } catch (error) {
                setStatus("No se pudo procesar la foto del código. Intenta nuevamente.", true);
              }
            }

            async function startCamera() {
              if (isRunning) {
                return;
              }
              if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                var reason = insecureContextMessage();
                setStatus(reason !== "" ? reason + " Usa \"Tomar foto\" o el campo manual." : "Este navegador no permite abrir la cámara. Usa \"Tomar foto\", el campo manual o un lector externo.", true);
                return;
              }
              detector = await buildDetector();
              if (!detector) {
                setStatus("Este navegador no soporta detección de código con cámara. Usa Chrome o Edge en el dispositivo, o prueba con \"Tomar foto\".", true);
                return;
              }

              try {
                stream = await navigator.mediaDevices.getUserMedia({
                  video: {
                    facingMode: { ideal: "environment" },
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                  },
                  audio: false
                });
                video.srcObject = stream;
                await video.play();
                isRunning = true;
                startButton.disabled = true;
                stopButton.disabled = false;
                setStatus("Cámara activa. Apunta al código de barras de la bobina.", false);
                scanLoop();
              } catch (error) {
                cleanupCamera();
                var contextMessage = insecureContextMessage();
                setStatus(contextMessage !== "" ? contextMessage + " También puedes usar \"Tomar foto\"." : "No se pudo abrir la cámara. Revisa permisos del navegador y usa HTTPS o localhost.", true);
              }
            }

            startButton.addEventListener("click", function () {
              startCamera();
            });

            stopButton.addEventListener("click", function () {
              cleanupCamera();
              setStatus("Cámara detenida.", false);
            });

            captureButton.addEventListener("click", function () {
              fileInput.click();
            });

            fileInput.addEventListener("change", function () {
              if (!fileInput.files || !fileInput.files[0] || isSubmitting) {
                return;
              }
              setStatus("Procesando foto del código...", false);
              detectFromFile(fileInput.files[0]);
              fileInput.value = "";
            });

            window.addEventListener("beforeunload", cleanupCamera);
          })();
        </script>';
    }

    if ($requestOperational && $pendingQty > 0 && in_array((string)$request['status'], ['ACCEPTED', 'PARTIAL'], true) && (string)($request['request_type'] ?? 'ROLL') !== 'ROLL') {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Entrega manual</div>
          <form method="post" action="/stock/material-requests/' . $requestId . '/deliver-manual">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <input type="hidden" name="return_mode" value="mobile">
            <label>Cantidad entregada (' . h((string)($request['requested_unit'] ?? 'Unid.')) . ')</label>
            <input name="delivered_qty" type="number" step="0.001" min="0.001" value="' . h((string)$pendingQty) . '" required>
            <label style="margin-top:10px">Nota entrega</label>
            <input name="delivery_note" type="text" value="" placeholder="Detalle de la entrega">
            <button class="btn" style="width:100%;margin-top:10px" type="submit">Registrar entrega</button>
          </form>
        </div>';
    }

    $body .= '<div class="card">
      <div style="font-weight:800;margin-bottom:8px">Entregas registradas</div>';
    foreach ($deliveries as $delivery) {
        $body .= '<div style="padding:10px 0;border-bottom:1px solid #e5e7eb">';
        if ((int)($delivery['roll_id'] ?? 0) > 0) {
            $body .= '<div style="font-weight:800">' . h((string)$delivery['roll_code']) . '</div>';
        } else {
            $detail = trim((string)($delivery['delivered_item'] ?? ''));
            $note = trim((string)($delivery['delivery_note'] ?? ''));
            $body .= '<div style="font-weight:800">' . h($detail !== '' ? $detail : '-') . '</div>';
            if ($note !== '') {
                $body .= '<div class="muted">' . h($note) . '</div>';
            }
        }
        $body .= '<div class="muted">' . h((string)$delivery['created_at']) . ' · ' . h(formatReceptionValue((float)($delivery['delivered_qty'] ?? 0), (string)($delivery['requested_unit'] ?? 'Unid.'))) . ' ' . h((string)($delivery['requested_unit'] ?? 'Unid.')) . ' · ' . h((string)$delivery['operator_name']) . '</div>
        </div>';
    }
    if ($deliveries === []) {
        $body .= '<div class="muted">Todavía no hay entregas registradas para esta solicitud.</div>';
    }
    $body .= '</div>';

    render('Picking móvil', $body);
    exit;
}

if (preg_match('#^/stock/material-requests/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $requestId = (int)$m[1];
    $request = $service->getMaterialRequest($requestId);
    if ($request === null) {
        render('No encontrado', '<div class="card">No existe la solicitud.</div>');
        exit;
    }

    $deliveries = $service->listMaterialDeliveriesByRequest($requestId);
    $flashOk = trim((string)($_GET['ok'] ?? ''));
    $flashError = trim((string)($_GET['error'] ?? ''));
    $requestedQty = (float)($request['requested_qty'] ?? 0);
    $deliveredQty = (float)($request['delivered_qty'] ?? 0);
    $pendingQty = max(0, $requestedQty - $deliveredQty);
    $requestOperational = workOrderCanReceiveMaterials((string)($request['work_order_status'] ?? ''));

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Atención de solicitud</div>
          <div class="muted">Bodega acepta la solicitud y registra las bobinas entregadas por código de barras.</div>
        </div>
        <a class="btn secondary" href="/stock/material-requests">Volver a solicitudes</a>
      </div>';

    if ($flashOk !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($flashOk) . '</div>';
    }
    if ($flashError !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($flashError) . '</div>';
    }
    if (!$requestOperational) {
        $body .= '<div class="err" style="margin-bottom:12px">La OT ya no está disponible para seguir atendiendo esta solicitud.</div>';
    }

    $body .= '<div class="card" style="margin-bottom:12px">
        <div style="font-weight:800;margin-bottom:8px">Datos de la solicitud</div>
        <div class="row">
          <div style="flex:1;min-width:220px"><div class="muted">Solicita</div><div style="font-weight:800">' . h((string)$request['requested_by']) . '</div></div>
          <div style="flex:1;min-width:220px"><div class="muted">OT</div><div style="font-weight:800"><a href="/work-orders/' . (int)$request['work_order_id'] . '/start">' . h((string)$request['ot_code']) . '</a></div></div>
          <div style="flex:1;min-width:220px"><div class="muted">Estado</div><div style="font-weight:800">' . h(materialRequestStatusLabel((string)$request['status'])) . '</div></div>
          <div style="flex:1;min-width:220px"><div class="muted">Tipo</div><div style="font-weight:800">' . h(materialRequestTypeLabel((string)($request['request_type'] ?? 'ROLL'))) . '</div></div>
        </div>
        <div class="row" style="margin-top:10px">
          <div style="flex:2;min-width:280px"><div class="muted">Material solicitado</div><div style="font-weight:800">' . h((string)$request['requested_item']) . '</div></div>
          <div style="flex:1;min-width:160px"><div class="muted">Cantidad pedida</div><div style="font-weight:800">' . h(formatReceptionValue($requestedQty, (string)($request['requested_unit'] ?? 'Unid.'))) . '</div></div>
          <div style="flex:1;min-width:160px"><div class="muted">Pendiente</div><div style="font-weight:800">' . h(formatReceptionValue($pendingQty, (string)($request['requested_unit'] ?? 'Unid.'))) . '</div></div>
        </div>
      </div>';

    if ($requestOperational && (string)$request['status'] === 'PENDING') {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Aceptar solicitud</div>
          <div class="muted" style="margin-bottom:10px">Bodega toma la solicitud antes de comenzar a escanear las bobinas entregadas.</div>
          <form method="post" action="/stock/material-requests/' . $requestId . '/accept">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <button class="btn" type="submit">Aceptar solicitud</button>
          </form>
        </div>';
    } elseif (!$requestOperational) {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Solicitud bloqueada</div>
          <div class="row">
            <div style="flex:1;min-width:220px"><div class="muted">Aceptó</div><div style="font-weight:800">' . h((string)($request['accepted_by'] ?? '-')) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">Fecha aceptación</div><div style="font-weight:800">' . h((string)($request['accepted_at'] ?? '-')) . '</div></div>
          </div>
        </div>';
    } else {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Solicitud tomada por bodega</div>
          <div class="row">
            <div style="flex:1;min-width:220px"><div class="muted">Aceptó</div><div style="font-weight:800">' . h((string)($request['accepted_by'] ?? '-')) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">Fecha aceptación</div><div style="font-weight:800">' . h((string)($request['accepted_at'] ?? '-')) . '</div></div>
          </div>
        </div>';
    }

    if ($requestOperational && $pendingQty > 0 && in_array((string)$request['status'], ['ACCEPTED', 'PARTIAL'], true) && (string)($request['request_type'] ?? 'ROLL') === 'ROLL') {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Ingresar producto por código de barras</div>
          <div class="muted" style="margin-bottom:10px">Puedes escanear con lector o con la cámara del celular/tablet. Al detectar el código, la bobina se ingresa automáticamente.</div>
          <form method="post" action="/stock/material-requests/' . $requestId . '/scan">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <div class="row nowrap">
              <div style="flex:1;min-width:260px">
                <label>Código de barras / ID de bobina</label>
                <input id="request_scan_code" name="scan_code" type="text" value="" autofocus placeholder="Escanear bobina o ingresar código manual">
              </div>
              <div style="min-width:200px;align-self:end">
                <button class="btn" type="submit">Ingresar producto</button>
              </div>
            </div>
          </form>
        </div>';

        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Escaneo con cámara</div>
          <div class="muted" id="camera_scan_status" style="margin-bottom:10px">Presiona "Abrir cámara" para usar la cámara trasera del dispositivo. Si el navegador bloquea la cámara en esta red local, usa "Tomar foto".</div>
          <div class="row" style="margin-bottom:10px">
            <button class="btn secondary" type="button" id="camera_scan_start">Abrir cámara</button>
            <button class="btn secondary" type="button" id="camera_scan_stop" disabled>Detener cámara</button>
            <button class="btn secondary" type="button" id="camera_scan_capture">Tomar foto</button>
            <input type="file" id="camera_scan_file" accept="image/*" capture="environment" style="display:none">
          </div>
          <div style="background:#111;border:1px solid #cfd5da;padding:8px;max-width:520px">
            <video id="camera_scan_video" playsinline muted style="width:100%;max-height:320px;display:block;background:#000"></video>
          </div>
        </div>';

        $body .= '<script>
          (function () {
            var form = document.querySelector(\'form[action="/stock/material-requests/' . $requestId . '/scan"]\');
            var input = document.getElementById("request_scan_code");
            var startButton = document.getElementById("camera_scan_start");
            var stopButton = document.getElementById("camera_scan_stop");
            var captureButton = document.getElementById("camera_scan_capture");
            var fileInput = document.getElementById("camera_scan_file");
            var video = document.getElementById("camera_scan_video");
            var statusBox = document.getElementById("camera_scan_status");
            if (!form || !input || !startButton || !stopButton || !captureButton || !fileInput || !video || !statusBox) {
              return;
            }

            var stream = null;
            var detector = null;
            var scanTimer = null;
            var isRunning = false;
            var isSubmitting = false;

            function setStatus(message, isError) {
              statusBox.textContent = message;
              statusBox.style.color = isError ? "#b42318" : "#475467";
            }

            function cleanupCamera() {
              if (scanTimer !== null) {
                window.clearTimeout(scanTimer);
                scanTimer = null;
              }
              if (stream) {
                stream.getTracks().forEach(function (track) { track.stop(); });
                stream = null;
              }
              video.srcObject = null;
              isRunning = false;
              startButton.disabled = false;
              stopButton.disabled = true;
            }

            function insecureContextMessage() {
              if (window.isSecureContext) {
                return "";
              }
              if (location.protocol === "http:" && location.hostname !== "localhost" && location.hostname !== "127.0.0.1") {
                return "La cámara en vivo está bloqueada porque abriste el sistema por HTTP en red local. En celular/tablet esto normalmente requiere HTTPS.";
              }
              return "";
            }

            async function buildDetector() {
              if (!("BarcodeDetector" in window)) {
                return null;
              }
              try {
                var supported = typeof BarcodeDetector.getSupportedFormats === "function"
                  ? await BarcodeDetector.getSupportedFormats()
                  : [];
                var preferred = ["code_39", "code_128", "ean_13", "ean_8", "upc_a", "upc_e"];
                var formats = preferred.filter(function (item) { return supported.indexOf(item) !== -1; });
                return formats.length > 0 ? new BarcodeDetector({ formats: formats }) : new BarcodeDetector();
              } catch (error) {
                return new BarcodeDetector();
              }
            }

            async function scanLoop() {
              if (!isRunning || !detector || isSubmitting) {
                return;
              }
              try {
                var codes = await detector.detect(video);
                if (codes && codes.length > 0) {
                  var rawValue = (codes[0].rawValue || "").trim();
                  if (rawValue !== "") {
                    input.value = rawValue;
                    isSubmitting = true;
                    setStatus("Código detectado. Ingresando bobina...", false);
                    cleanupCamera();
                    form.submit();
                    return;
                  }
                }
              } catch (error) {
                setStatus("No se pudo leer el código todavía. Mantén la cámara enfocando la etiqueta.", false);
              }
              scanTimer = window.setTimeout(scanLoop, 250);
            }

            async function detectFromFile(file) {
              detector = await buildDetector();
              if (!detector) {
                setStatus("Este navegador no soporta leer códigos desde cámara o foto. Usa Chrome o Edge actualizado.", true);
                return;
              }
              try {
                var bitmap = await createImageBitmap(file);
                var codes = await detector.detect(bitmap);
                if (bitmap && typeof bitmap.close === "function") {
                  bitmap.close();
                }
                if (codes && codes.length > 0) {
                  var rawValue = (codes[0].rawValue || "").trim();
                  if (rawValue !== "") {
                    input.value = rawValue;
                    isSubmitting = true;
                    setStatus("Código detectado desde la foto. Ingresando bobina...", false);
                    form.submit();
                    return;
                  }
                }
                setStatus("No se pudo leer el código desde la foto. Acércate más a la etiqueta o mejora la luz.", true);
              } catch (error) {
                setStatus("No se pudo procesar la foto del código. Intenta nuevamente.", true);
              }
            }

            async function startCamera() {
              if (isRunning) {
                return;
              }
              if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                var reason = insecureContextMessage();
                setStatus(reason !== "" ? reason + " Usa \"Tomar foto\" o el campo manual." : "Este navegador no permite abrir la cámara. Usa \"Tomar foto\", el campo manual o un lector externo.", true);
                return;
              }
              detector = await buildDetector();
              if (!detector) {
                setStatus("Este navegador no soporta detección de código con cámara. Usa Chrome o Edge en el dispositivo, o prueba con \"Tomar foto\".", true);
                return;
              }

              try {
                stream = await navigator.mediaDevices.getUserMedia({
                  video: {
                    facingMode: { ideal: "environment" },
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                  },
                  audio: false
                });
                video.srcObject = stream;
                await video.play();
                isRunning = true;
                startButton.disabled = true;
                stopButton.disabled = false;
                setStatus("Cámara activa. Apunta al código de barras de la bobina.", false);
                scanLoop();
              } catch (error) {
                cleanupCamera();
                var contextMessage = insecureContextMessage();
                setStatus(contextMessage !== "" ? contextMessage + " También puedes usar \"Tomar foto\"." : "No se pudo abrir la cámara. Revisa permisos del navegador y usa HTTPS o localhost.", true);
              }
            }

            startButton.addEventListener("click", function () {
              startCamera();
            });

            stopButton.addEventListener("click", function () {
              cleanupCamera();
              setStatus("Cámara detenida.", false);
            });

            captureButton.addEventListener("click", function () {
              fileInput.click();
            });

            fileInput.addEventListener("change", function () {
              if (!fileInput.files || !fileInput.files[0] || isSubmitting) {
                return;
              }
              setStatus("Procesando foto del código...", false);
              detectFromFile(fileInput.files[0]);
              fileInput.value = "";
            });

            window.addEventListener("beforeunload", cleanupCamera);
          })();
        </script>';
    }

    if ($requestOperational && $pendingQty > 0 && in_array((string)$request['status'], ['ACCEPTED', 'PARTIAL'], true) && (string)($request['request_type'] ?? 'ROLL') !== 'ROLL') {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Registrar entrega manual</div>
          <div class="muted" style="margin-bottom:10px">Usa esta opción para tintas y otros insumos que no se entregan por bobina escaneada.</div>
          <form method="post" action="/stock/material-requests/' . $requestId . '/deliver-manual">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <div class="row nowrap">
              <div style="flex:1;min-width:220px">
                <label>Cantidad entregada (' . h((string)($request['requested_unit'] ?? 'Unid.')) . ')</label>
                <input name="delivered_qty" type="number" step="0.001" min="0.001" value="' . h((string)$pendingQty) . '" required>
              </div>
              <div style="flex:2;min-width:260px">
                <label>Nota entrega</label>
                <input name="delivery_note" type="text" value="" placeholder="Ej: entregado a línea 2, lote 15">
              </div>
              <div style="min-width:200px;align-self:end">
                <button class="btn" type="submit">Registrar entrega</button>
              </div>
            </div>
          </form>
        </div>';
    }

    $body .= '<div class="card">
      <div style="font-weight:800;margin-bottom:8px">Entregas registradas</div>
      <table><thead><tr><th>Fecha</th><th>Detalle</th><th>Cantidad</th><th>Operador</th></tr></thead><tbody>';
    foreach ($deliveries as $delivery) {
        $body .= '<tr>';
        $body .= '<td>' . h((string)$delivery['created_at']) . '</td>';
        if ((int)($delivery['roll_id'] ?? 0) > 0) {
            $body .= '<td><a href="/rolls/' . (int)$delivery['roll_id'] . '">' . h((string)$delivery['roll_code']) . '</a></td>';
        } else {
            $detail = trim((string)($delivery['delivered_item'] ?? ''));
            $note = trim((string)($delivery['delivery_note'] ?? ''));
            $body .= '<td>' . h($detail !== '' ? $detail : '-') . ($note !== '' ? '<div class="muted">' . h($note) . '</div>' : '') . '</td>';
        }
        $body .= '<td>' . h(formatReceptionValue((float)($delivery['delivered_qty'] ?? 0), (string)($delivery['requested_unit'] ?? 'Unid.'))) . '</td>';
        $body .= '<td>' . h((string)$delivery['operator_name']) . '</td>';
        $body .= '</tr>';
    }
    if ($deliveries === []) {
        $body .= '<tr><td colspan="4" class="muted">Todavía no se han registrado entregas para esta solicitud.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    render('Atención solicitud', $body);
    exit;
}

if ($path === '/stock/transfers' && $method === 'GET') {
    $warehouses = $service->listWarehouses();
    $transferWorkOrders = $service->listWorkOrdersForTransfer();
    $scanCode = trim((string)($_GET['code'] ?? ''));
    $roll = $scanCode !== '' ? $service->getRollByScanCode($scanCode) : null;

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Traspaso por pistoleo</div>
          <div class="muted">Escanea el ID o código de la bobina para moverla de bodega o enviarla a una OT disponible.</div>
        </div>
        <a class="btn secondary" href="/stock">Volver a stock</a>
      </div>';

    $body .= '<div class="card" style="margin-bottom:12px">
        <form method="get" action="/stock/transfers">
          <div class="row nowrap">
            <div style="flex:1;min-width:260px">
              <label>Código pieza / ID</label>
              <input name="code" type="text" value="' . h($scanCode) . '" autofocus placeholder="Escanear código">
            </div>
            <div style="min-width:180px;align-self:end">
              <button class="btn" type="submit">Buscar</button>
            </div>
          </div>
        </form>
      </div>';

    if ($scanCode !== '' && $roll === null) {
        $body .= '<div class="err" style="margin-bottom:12px">No se encontró una bobina con ese código.</div>';
    }

    if ($roll !== null) {
        $transferAvailability = rollTransferAvailability($roll);
        $warehouseTransferEnabled = (bool)$transferAvailability['warehouse']['allowed'];
        $workOrderTransferEnabled = (bool)$transferAvailability['work_order']['allowed'] && $transferWorkOrders !== [];
        $workOrderTransferReason = !$transferAvailability['work_order']['allowed']
            ? (string)$transferAvailability['work_order']['reason']
            : ($transferWorkOrders === [] ? 'No hay OTs disponibles para recibir bobinas.' : '');
        $selectedTransferMode = $warehouseTransferEnabled ? 'warehouse' : ($workOrderTransferEnabled ? 'work_order' : 'warehouse');
        $canSubmitTransfer = $warehouseTransferEnabled || $workOrderTransferEnabled;

        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Información bobina</div>
          <div class="row">
            <div style="flex:1;min-width:220px"><div class="muted">ID</div><div style="font-weight:800">' . (int)$roll['id'] . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">Código</div><div style="font-weight:800">' . h((string)$roll['roll_code']) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">Bodega actual</div><div style="font-weight:800">' . h((string)$roll['warehouse_code']) . ' - ' . h((string)$roll['warehouse_name']) . '</div></div>
          </div>
          <div class="row" style="margin-top:10px">
            <div style="flex:1;min-width:220px"><div class="muted">Especificación</div><div style="font-weight:800">' . h((string)$roll['sku_description']) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">Código SKU</div><div style="font-weight:800">' . h((string)$roll['sku_code']) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">Peso</div><div style="font-weight:800">' . h((string)$roll['weight_kg']) . ' Kg</div></div>
          </div>
          <div class="row" style="margin-top:10px">
            <div style="flex:1;min-width:220px"><div class="muted">Proveedor</div><div style="font-weight:800">' . h((string)($roll['supplier_name'] ?? '-')) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">OC</div><div style="font-weight:800">' . h((string)($roll['po_code'] ?? '-')) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">OT actual</div><div style="font-weight:800">' . h((string)($roll['work_order_code'] ?? '-')) . '</div></div>
          </div>
          <div class="row" style="margin-top:10px">
            <div style="flex:1;min-width:220px"><div class="muted">Estado</div><div style="font-weight:800">' . h(rollStatusLabel((string)($roll['status'] ?? ''))) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">Etapa</div><div style="font-weight:800">' . h(rollProcessStageLabel((string)($roll['process_stage'] ?? 'RAW'))) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">Disponibilidad</div><div style="font-weight:800">' . h($canSubmitTransfer ? 'Transferible' : 'Bloqueada') . '</div></div>
          </div>
        </div>';

        if (!$canSubmitTransfer) {
            $body .= '<div class="err" style="margin-bottom:12px">' . h((string)($transferAvailability['warehouse']['reason'] ?: $workOrderTransferReason)) . '</div>';
        } else {
            $body .= '<div class="card" style="margin-bottom:12px;background:#f8fafc">
              <div style="font-weight:800;margin-bottom:6px">Validación previa</div>
              <div class="muted">A bodega: ' . h($warehouseTransferEnabled ? 'permitido' : ((string)$transferAvailability['warehouse']['reason'] ?: 'no disponible')) . '</div>
              <div class="muted">A OT: ' . h($workOrderTransferEnabled ? 'permitido' : ($workOrderTransferReason !== '' ? $workOrderTransferReason : 'no disponible')) . '</div>
            </div>';
        }

        $body .= '<div class="card">
          <div style="font-weight:800;margin-bottom:8px">Ejecutar traspaso</div>
          <form method="post" action="/stock/transfers">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <input type="hidden" name="roll_id" value="' . (int)$roll['id'] . '">
            <input type="hidden" name="code" value="' . h($scanCode) . '">
            <div class="row">
              <div style="flex:1;min-width:260px">
                <label>Operador</label>
                <div class="panel" style="padding:10px 12px;font-weight:700">' . h($currentOperatorName) . '</div>
              </div>
              <div style="flex:1;min-width:260px">
                <label>Tipo traspaso</label>
                <select name="transfer_mode" id="transfer_mode">
                  <option value="warehouse"' . ($selectedTransferMode === 'warehouse' ? ' selected' : '') . ($warehouseTransferEnabled ? '' : ' disabled') . '>Traspaso a bodega</option>';
        if ($transferWorkOrders !== [] || !$transferAvailability['work_order']['allowed']) {
            $body .= '<option value="work_order"' . ($selectedTransferMode === 'work_order' ? ' selected' : '') . ($workOrderTransferEnabled ? '' : ' disabled') . '>Traspaso a OT</option>';
        }
        $body .= '</select>
              </div>
            </div>
            <div class="muted" style="margin-top:8px">' .
                (!$warehouseTransferEnabled && $selectedTransferMode === 'warehouse'
                    ? h((string)$transferAvailability['warehouse']['reason'])
                    : ($selectedTransferMode === 'work_order' && !$workOrderTransferEnabled
                        ? h($workOrderTransferReason)
                        : 'Selecciona un destino válido para esta bobina.')) .
            '</div>
            <div class="row" style="margin-top:10px" id="transfer_warehouse_wrap">
              <div style="flex:1;min-width:260px">
                <label>Bodega destino</label>
                <select name="to_warehouse_id"' . ($warehouseTransferEnabled ? '' : ' disabled') . '>
                  <option value="">Seleccionar</option>';
        foreach ($warehouses as $w) {
            $disabled = ((int)$w['code'] === (int)$roll['warehouse_code']) ? ' disabled' : '';
            $body .= '<option value="' . (int)$w['id'] . '"' . $disabled . '>' . h((string)$w['code']) . ' - ' . h((string)$w['name']) . '</option>';
        }
        $body .= '</select>
              </div>
            </div>';
        if ($transferWorkOrders !== []) {
            $body .= '<div id="transfer_ot_wrap" style="margin-top:10px;display:none">
                <label>OT destino</label>
                <select name="work_order_id"' . ($workOrderTransferEnabled ? '' : ' disabled') . '>
                  <option value="">Seleccionar</option>';
            foreach ($transferWorkOrders as $wo) {
                $statusLabel = (string)$wo['status'] === 'ACTIVE' ? ' [ACTIVA]' : '';
                $body .= '<option value="' . (int)$wo['id'] . '">' . h((string)$wo['ot_code']) . ' - ' . h((string)$wo['sku_final']) . $statusLabel . '</option>';
            }
            $body .= '</select>
              </div>';
        }
        $body .= '<div style="margin-top:12px" class="row">
              <button class="btn" type="submit"' . ($canSubmitTransfer ? '' : ' disabled') . '>Confirmar traspaso</button>
              <a class="btn secondary" href="/rolls/' . (int)$roll['id'] . '">Ver trazabilidad</a>
            </div>
          </form>
          <script>
            (function () {
              var mode = document.getElementById("transfer_mode");
              var whWrap = document.getElementById("transfer_warehouse_wrap");
              var otWrap = document.getElementById("transfer_ot_wrap");
              if (!mode) return;
              function syncMode() {
                var isOt = mode.value === "work_order";
                if (whWrap) whWrap.style.display = isOt ? "none" : "";
                if (otWrap) otWrap.style.display = isOt ? "" : "none";
              }
              mode.addEventListener("change", syncMode);
              syncMode();
            })();
          </script>
        </div>';
    }

    render('Traspaso', $body);
    exit;
}

if ($path === '/stock/transfers' && $method === 'POST') {
    requireCsrf();
    $rollId = (int)($_POST['roll_id'] ?? 0);
    $scanCode = trim((string)($_POST['code'] ?? ''));
    $transferMode = (string)($_POST['transfer_mode'] ?? 'warehouse');
    $toWarehouseId = $transferMode === 'warehouse' ? (int)($_POST['to_warehouse_id'] ?? 0) : 0;
    $transferWorkOrders = $service->listWorkOrdersForTransfer();
    $workOrderId = $transferMode === 'work_order' ? (int)($_POST['work_order_id'] ?? 0) : null;
    $result = $service->transferRoll($rollId, $toWarehouseId, $currentOperatorName, $workOrderId);
    if ($result['ok'] === true) {
        header('Location: /rolls/' . $rollId);
        exit;
    }

    $warehouses = $service->listWarehouses();
    $transferWorkOrders = $service->listWorkOrdersForTransfer();
    $roll = $service->getRoll($rollId);
    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Traspaso por pistoleo</div>
        <a class="btn secondary" href="/stock/transfers' . ($scanCode !== '' ? '?code=' . urlencode($scanCode) : '') . '">Volver</a>
      </div>';
    $body .= '<div class="err" style="margin-bottom:12px"><ul style="margin:0;padding-left:16px">';
    foreach ($result['errors'] as $msg) {
        $body .= '<li>' . h((string)$msg) . '</li>';
    }
    $body .= '</ul></div>';
    if ($roll !== null) {
        $transferAvailability = rollTransferAvailability($roll);
        $warehouseTransferEnabled = (bool)$transferAvailability['warehouse']['allowed'];
        $workOrderTransferEnabled = (bool)$transferAvailability['work_order']['allowed'] && $transferWorkOrders !== [];
        $workOrderTransferReason = !$transferAvailability['work_order']['allowed']
            ? (string)$transferAvailability['work_order']['reason']
            : ($transferWorkOrders === [] ? 'No hay OTs disponibles para recibir bobinas.' : '');
        $selectedTransferMode = (string)($_POST['transfer_mode'] ?? ($warehouseTransferEnabled ? 'warehouse' : ($workOrderTransferEnabled ? 'work_order' : 'warehouse')));
        if ($selectedTransferMode === 'work_order' && !$workOrderTransferEnabled) {
            $selectedTransferMode = $warehouseTransferEnabled ? 'warehouse' : 'work_order';
        }
        if ($selectedTransferMode === 'warehouse' && !$warehouseTransferEnabled && $workOrderTransferEnabled) {
            $selectedTransferMode = 'work_order';
        }
        $canSubmitTransfer = $warehouseTransferEnabled || $workOrderTransferEnabled;

        $body .= '<div class="card">
          <form method="post" action="/stock/transfers">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <input type="hidden" name="roll_id" value="' . (int)$roll['id'] . '">
            <input type="hidden" name="code" value="' . h($scanCode) . '">
            <div class="row">
              <div style="flex:1;min-width:260px">
                <label>Operador</label>
                <div class="panel" style="padding:10px 12px;font-weight:700">' . h($currentOperatorName) . '</div>
              </div>
              <div style="flex:1;min-width:260px">
                <label>Tipo traspaso</label>
                <select name="transfer_mode" id="transfer_mode">
                  <option value="warehouse"' . ($selectedTransferMode === 'warehouse' ? ' selected' : '') . ($warehouseTransferEnabled ? '' : ' disabled') . '>Traspaso a bodega</option>';
        if ($transferWorkOrders !== [] || !$transferAvailability['work_order']['allowed']) {
            $body .= '<option value="work_order"' . ($selectedTransferMode === 'work_order' ? ' selected' : '') . ($workOrderTransferEnabled ? '' : ' disabled') . '>Traspaso a OT</option>';
        }
        $body .= '</select>
              </div>
            </div>
            <div class="muted" style="margin-top:8px">' .
                (!$warehouseTransferEnabled && $selectedTransferMode === 'warehouse'
                    ? h((string)$transferAvailability['warehouse']['reason'])
                    : ($selectedTransferMode === 'work_order' && !$workOrderTransferEnabled
                        ? h($workOrderTransferReason)
                        : 'Selecciona un destino válido para esta bobina.')) .
            '</div>
            <div class="row" style="margin-top:10px" id="transfer_warehouse_wrap">
              <div style="flex:1;min-width:260px">
                <label>Bodega destino</label>
                <select name="to_warehouse_id"' . ($warehouseTransferEnabled ? '' : ' disabled') . '>
                  <option value="">Seleccionar</option>';
        foreach ($warehouses as $w) {
            $selected = ((int)$w['id'] === $toWarehouseId) ? ' selected' : '';
            $disabled = ((int)$w['code'] === (int)$roll['warehouse_code']) ? ' disabled' : '';
            $body .= '<option value="' . (int)$w['id'] . '"' . $selected . $disabled . '>' . h((string)$w['code']) . ' - ' . h((string)$w['name']) . '</option>';
        }
        $body .= '</select>
              </div>
            </div>';
        if ($transferWorkOrders !== []) {
            $selectedWorkOrderId = (int)($_POST['work_order_id'] ?? 0);
            $body .= '<div id="transfer_ot_wrap" style="margin-top:10px;display:none">
                <label>OT destino</label>
                <select name="work_order_id"' . ($workOrderTransferEnabled ? '' : ' disabled') . '>
                  <option value="">Seleccionar</option>';
            foreach ($transferWorkOrders as $wo) {
                $selected = (int)$wo['id'] === $selectedWorkOrderId ? ' selected' : '';
                $statusLabel = (string)$wo['status'] === 'ACTIVE' ? ' [ACTIVA]' : '';
                $body .= '<option value="' . (int)$wo['id'] . '"' . $selected . '>' . h((string)$wo['ot_code']) . ' - ' . h((string)$wo['sku_final']) . $statusLabel . '</option>';
            }
            $body .= '</select>
              </div>';
        }
        $body .= '<div style="margin-top:12px"><button class="btn" type="submit"' . ($canSubmitTransfer ? '' : ' disabled') . '>Confirmar traspaso</button></div>
          <script>
            (function () {
              var mode = document.getElementById("transfer_mode");
              var whWrap = document.getElementById("transfer_warehouse_wrap");
              var otWrap = document.getElementById("transfer_ot_wrap");
              if (!mode) return;
              function syncMode() {
                var isOt = mode.value === "work_order";
                if (whWrap) whWrap.style.display = isOt ? "none" : "";
                if (otWrap) otWrap.style.display = isOt ? "" : "none";
              }
              mode.addEventListener("change", syncMode);
              syncMode();
            })();
          </script>
          </form>
        </div>';
    }

    render('Traspaso', $body);
    exit;
}

if (str_starts_with($path, '/admin/skus')) {
    header('Location: /');
    exit;
}

if ($path === '/rolls/new' && $method === 'GET') {
    $warehouses = $service->listWarehousesForReception();
    $skus = $service->listSkus();

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Nueva recepción</div>
        <a class="btn secondary" href="/">Volver</a>
      </div>';

    $body .= '<div class="grid" style="grid-template-columns: 1fr">
        <div class="card">
          <form method="post" action="/rolls">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <div class="row">
              <div style="flex:1;min-width:220px">
                <label>SKU interno</label>
                <select name="sku_id" required>
                  <option value="">Seleccionar</option>';
    foreach ($skus as $s) {
        $body .= '<option value="' . (int)$s['id'] . '">' . h((string)$s['code']) . ' - ' . h((string)$s['description']) . '</option>';
    }
    $body .= '</select>
              </div>
              <div style="flex:1;min-width:220px">
                <label>Bodega (100/200)</label>
                <select name="warehouse_id" required>
                  <option value="">Seleccionar</option>';
    foreach ($warehouses as $w) {
        $body .= '<option value="' . (int)$w['id'] . '">' . h((string)$w['code']) . ' - ' . h((string)$w['name']) . '</option>';
    }
    $body .= '</select>
              </div>
            </div>

            <div class="row" style="margin-top:10px;align-items:end">
              <div style="flex:1;min-width:220px">
                <label>Peso real (Kg)</label>
                <input id="weight_kg" name="weight_kg" type="number" step="0.001" min="0" required>
              </div>
              <div style="min-width:220px">
                <button class="btn secondary" type="button" id="read_scale">Leer balanza</button>
              </div>
            </div>

            <div class="card" style="margin-top:12px;background:#f8fafc">
              <div style="font-weight:800;margin-bottom:6px">Especificaciones (desde ERP)</div>
              <div class="row">
                <div style="flex:1;min-width:160px">
                  <label>Gramos</label>
                  <input id="microns" name="microns" type="number" step="1" min="0" disabled>
                </div>
                <div style="flex:1;min-width:160px">
                  <label>Ancho (mm)</label>
                  <input id="width_mm" name="width_mm" type="number" step="1" min="0" disabled>
                </div>
                <div style="flex:1;min-width:220px">
                  <label>Color</label>
                  <input id="color" name="color" type="text" maxlength="60" disabled>
                </div>
                <div style="flex:1;min-width:220px">
                  <label>Metros lineales</label>
                  <input id="meters" name="meters" type="number" step="0.01" min="0" disabled>
                </div>
              </div>
              <div class="muted" style="margin-top:6px">Modo prueba: habilitar edición de especificaciones (solo para pruebas mientras no se integre el ERP).</div>
              <div style="margin-top:6px">
                <label style="display:flex;gap:8px;align-items:center">
                  <input id="toggle_specs" type="checkbox" style="width:auto">
                  <span>Editar especificaciones</span>
                </label>
              </div>
            </div>

            <div style="margin-top:12px">
              <button class="btn" type="submit">Guardar recepción</button>
            </div>
          </form>
          <script>
            (function () {
              var btn = document.getElementById("read_scale");
              var weight = document.getElementById("weight_kg");
              var toggle = document.getElementById("toggle_specs");
              var specs = ["microns","width_mm","color","meters"].map(function (id) { return document.getElementById(id); });

              function setSpecsEnabled(on) {
                specs.forEach(function (el) { if (!el) return; el.disabled = !on; });
              }

              if (toggle) {
                toggle.addEventListener("change", function () { setSpecsEnabled(!!toggle.checked); });
                setSpecsEnabled(false);
              }

              if (btn) {
                btn.addEventListener("click", async function () {
                  btn.disabled = true;
                  btn.textContent = "Leyendo...";
                  try {
                    var res = await fetch("/api/scale/weight", { cache: "no-store" });
                    var data = await res.json();
                    if (!data || data.ok !== true) {
                      throw new Error((data && data.error) ? data.error : "No se pudo leer la balanza.");
                    }
                    weight.value = data.weight_kg;
                  } catch (e) {
                    alert(e && e.message ? e.message : "Error leyendo la balanza.");
                  } finally {
                    btn.disabled = false;
                    btn.textContent = "Leer balanza";
                  }
                });
              }
            })();
          </script>
        </div>
      </div>';

    render('Nueva recepción', $body);
    exit;
}

if ($path === '/rolls' && $method === 'POST') {
    requireCsrf();
    $postData = $_POST;
    $postData['operator_name'] = $currentOperatorName;
    $result = $service->createRoll($postData);
    if ($result['ok'] === true) {
        header('Location: /rolls/' . (int)$result['id']);
        exit;
    }

    $errors = $result['errors'];
    $warehouses = $service->listWarehousesForReception();
    $skus = $service->listSkus();

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Nueva recepción</div>
        <a class="btn secondary" href="/">Volver</a>
      </div>';

    $body .= '<div class="err" style="margin-bottom:12px"><div style="font-weight:700;margin-bottom:6px">Revisar datos</div><ul style="margin:0;padding-left:16px">';
    foreach ($errors as $msg) {
        $body .= '<li>' . h((string)$msg) . '</li>';
    }
    $body .= '</ul></div>';

    $body .= '<div class="card"><form method="post" action="/rolls">
        <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
        <div class="row">
          <div style="flex:1;min-width:220px">
            <label>SKU interno</label>
            <select name="sku_id" required>
              <option value="">Seleccionar</option>';
    foreach ($skus as $s) {
        $selected = ((string)($_POST['sku_id'] ?? '') === (string)$s['id']) ? ' selected' : '';
        $body .= '<option value="' . (int)$s['id'] . '"' . $selected . '>' . h((string)$s['code']) . ' - ' . h((string)$s['description']) . '</option>';
    }
    $body .= '</select>
          </div>
          <div style="flex:1;min-width:220px">
            <label>Bodega (100/200)</label>
            <select name="warehouse_id" required>
              <option value="">Seleccionar</option>';
    foreach ($warehouses as $w) {
        $selected = ((string)($_POST['warehouse_id'] ?? '') === (string)$w['id']) ? ' selected' : '';
        $body .= '<option value="' . (int)$w['id'] . '"' . $selected . '>' . h((string)$w['code']) . ' - ' . h((string)$w['name']) . '</option>';
    }
    $body .= '</select>
          </div>
        </div>

        <div class="row" style="margin-top:10px">
          <div style="flex:1;min-width:220px">
            <label>Peso real (Kg)</label>
            <input name="weight_kg" type="number" step="0.001" min="0" required value="' . h((string)($_POST['weight_kg'] ?? '')) . '">
          </div>
        </div>

        <div class="card" style="margin-top:12px;background:#f8fafc">
          <div style="font-weight:800;margin-bottom:6px">Especificaciones (opcional, modo prueba)</div>
          <div class="row">
            <div style="flex:1;min-width:160px">
              <label>Gramos</label>
              <input name="microns" type="number" step="1" min="0" value="' . h((string)($_POST['microns'] ?? '')) . '">
            </div>
            <div style="flex:1;min-width:160px">
              <label>Ancho (mm)</label>
              <input name="width_mm" type="number" step="1" min="0" value="' . h((string)($_POST['width_mm'] ?? '')) . '">
            </div>
            <div style="flex:1;min-width:220px">
              <label>Color</label>
              <input name="color" type="text" maxlength="60" value="' . h((string)($_POST['color'] ?? '')) . '">
            </div>
            <div style="flex:1;min-width:220px">
              <label>Metros lineales</label>
              <input name="meters" type="number" step="0.01" min="0" value="' . h((string)($_POST['meters'] ?? '')) . '">
            </div>
          </div>
        </div>

        <div style="margin-top:12px">
          <button class="btn" type="submit">Guardar recepción</button>
        </div>
      </form></div>';

    render('Nueva recepción', $body);
    exit;
}

if (preg_match('#^/rolls/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $id = (int)$m[1];
    $roll = $service->getRoll($id);
    if ($roll === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la bobina.</div>');
        exit;
    }
    $traceability = $service->listRollTraceability($id);
    $operationalTraceability = $service->listRollOperationalTraceability($id);
    $childRolls = $service->listChildRollsByParentRoll($id);
    $boxesFromRoll = $service->listBoxesBySourceRoll($id);
    $palletsFromRoll = $service->listPalletsBySourceRoll($id);
    $sourceWorkOrder = (int)($roll['source_work_order_id'] ?? 0) > 0 ? $service->getWorkOrder((int)$roll['source_work_order_id']) : null;
    $transferAvailability = rollTransferAvailability($roll);
    $rollNavParams = inventoryNavigationContext() ? inventoryNavigationParams() : [];
    $rollBackUrl = inventoryNavigationContext() ? inventoryReturnUrl() : ('/stock?bodega=' . (int)$roll['warehouse_code']);

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Recepción #' . (int)$roll['id'] . '</div>
        <div class="row">
          <a class="btn secondary" href="' . h($rollBackUrl) . '">' . h(inventoryNavigationContext() ? 'Volver a inventario' : 'Volver') . '</a>
          ' . ($transferAvailability['can_transfer']
            ? '<a class="btn secondary" href="' . h(withQuery('/rolls/' . (int)$roll['id'] . '/transfer', $rollNavParams)) . '">Transferir</a>'
            : '<span class="btn secondary" style="opacity:.55;cursor:not-allowed" title="' . h((string)($transferAvailability['warehouse']['reason'] ?: $transferAvailability['work_order']['reason'])) . '">Transferir</span>') . '
          <a class="btn" href="/rolls/' . (int)$roll['id'] . '/label">Etiqueta</a>
        </div>
      </div>';

    $body .= '<div class="card">
        <div class="row">
          <div style="flex:1;min-width:240px">
            <div class="muted">Código bobina</div>
            <div style="font-size:20px;font-weight:800">' . h((string)$roll['roll_code']) . '</div>
          </div>
          <div style="flex:1;min-width:240px">
            <div class="muted">Bodega</div>
            <div style="font-weight:700">' . h((string)$roll['warehouse_code']) . ' - ' . h((string)$roll['warehouse_name']) . '</div>
          </div>
          <div style="flex:1;min-width:240px">
            <div class="muted">Especificación</div>
            <div style="font-weight:700">' . h((string)$roll['sku_description']) . '</div>
          </div>
          <div style="flex:1;min-width:240px">
            <div class="muted">Código SKU</div>
            <div style="font-weight:700">' . h((string)$roll['sku_code']) . '</div>
          </div>
        </div>

        <div class="row" style="margin-top:10px">
          <div style="flex:1;min-width:160px"><div class="muted">Peso (Kg)</div><div style="font-weight:700">' . h((string)$roll['weight_kg']) . '</div></div>
          <div style="flex:1;min-width:160px"><div class="muted">Cantidad</div><div style="font-weight:700">' . h(formatReceptionValue((float)($roll['received_qty'] ?? 1), 'Unid.')) . '</div></div>
          <div style="flex:1;min-width:160px"><div class="muted">Gramos</div><div style="font-weight:700">' . h((string)($roll['grams'] ?? '')) . '</div></div>
          <div style="flex:1;min-width:160px"><div class="muted">Ancho (mm)</div><div style="font-weight:700">' . h((string)$roll['width_mm']) . '</div></div>
          <div style="flex:1;min-width:160px"><div class="muted">Color</div><div style="font-weight:700">' . h((string)$roll['color']) . '</div></div>
          <div style="flex:1;min-width:160px"><div class="muted">Metros</div><div style="font-weight:700">' . h((string)$roll['meters']) . '</div></div>
        </div>

        <div class="row" style="margin-top:10px">
          <div style="flex:1;min-width:240px"><div class="muted">Estado</div><div style="font-weight:700">' . h(rollStatusLabel((string)$roll['status'])) . '</div></div>
          <div style="flex:1;min-width:240px"><div class="muted">Fecha</div><div style="font-weight:700">' . h((string)$roll['created_at']) . '</div></div>
          <div style="flex:1;min-width:240px"><div class="muted">Contenedor</div><div style="font-weight:700">' . h(trim((string)($roll['container_code'] ?? '')) !== '' ? (string)$roll['container_code'] : '-') . '</div></div>
          <div style="flex:1;min-width:240px"><div class="muted">OT activa</div><div style="font-weight:700">' . h((string)($roll['work_order_code'] ?? '-')) . '</div></div>
        </div>
      </div>';

    $body .= '<div class="card" style="margin-top:12px">
        <div style="font-weight:800;margin-bottom:8px">Cadena de fabricación</div>
        <div class="row">
          <div style="flex:1;min-width:220px"><div class="muted">OT origen</div><div style="font-weight:700">';
    if (is_array($sourceWorkOrder)) {
        $sourceOtLabel = (string)$sourceWorkOrder['ot_code'];
        $body .= canAccessWorkOrderTraceability()
            ? '<a href="' . h(withQuery('/work-orders/' . (int)$sourceWorkOrder['id'] . '/traceability', $rollNavParams)) . '">' . h($sourceOtLabel) . '</a>'
            : h($sourceOtLabel);
    } else {
        $body .= '-';
    }
    $body .= '</div></div>
          <div style="flex:1;min-width:220px"><div class="muted">Bobina entrada</div><div style="font-weight:700">';
    if ((int)($roll['parent_roll_id'] ?? 0) > 0) {
        $body .= '<a href="/rolls/' . (int)$roll['parent_roll_id'] . '">' . h((string)($roll['parent_roll_code'] ?? ('#' . (int)$roll['parent_roll_id']))) . '</a>';
    } else {
        $body .= '-';
    }
    $body .= '</div></div>
          <div style="flex:1;min-width:220px"><div class="muted">Bobinas salida generadas</div><div style="font-weight:700">' . h((string)count($childRolls)) . '</div></div>
          <div style="flex:1;min-width:220px"><div class="muted">Cajas</div><div style="font-weight:700">' . h((string)count($boxesFromRoll)) . '</div></div>
          <div style="flex:1;min-width:220px"><div class="muted">Pallets</div><div style="font-weight:700">' . h((string)count($palletsFromRoll)) . '</div></div>
        </div>';
    if ($childRolls !== []) {
        $body .= '<div style="font-weight:700;margin-top:10px">Bobinas salida ligadas</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Bobina</th><th>OT</th><th>Etapa</th><th>Cajas</th><th>Pallets</th><th></th></tr></thead><tbody>';
        foreach ($childRolls as $childRoll) {
            $body .= '<tr>';
            $body .= '<td><a href="/rolls/' . (int)$childRoll['id'] . '">' . h((string)$childRoll['roll_code']) . '</a></td>';
            $body .= '<td>' . h((string)($childRoll['ot_code'] ?? '-')) . '</td>';
            $body .= '<td>' . h(rollProcessStageLabel((string)($childRoll['process_stage'] ?? '-'))) . ' / ' . h(rollStatusLabel((string)($childRoll['status'] ?? '-'))) . '</td>';
            $body .= '<td>' . h((string)($childRoll['box_count'] ?? '0')) . '</td>';
            $body .= '<td>' . h((string)($childRoll['pallet_count'] ?? '0')) . '</td>';
            $body .= '<td><a class="btn secondary" href="/rolls/' . (int)$childRoll['id'] . '">Ver</a></td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div>';
    }
    if ($boxesFromRoll !== []) {
        $body .= '<div style="font-weight:700;margin-top:10px">Cajas generadas desde esta bobina</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Caja</th><th>Unidades</th><th>Pallet</th><th></th></tr></thead><tbody>';
        foreach ($boxesFromRoll as $boxItem) {
            $body .= '<tr>';
            $body .= '<td><a href="/boxes/' . (int)$boxItem['id'] . '">' . h((string)$boxItem['box_code']) . '</a></td>';
            $body .= '<td>' . h((string)($boxItem['units_qty'] ?? '-')) . '</td>';
            $body .= '<td>' . h((string)($boxItem['pallet_code'] ?? '-')) . '</td>';
            $body .= '<td><a class="btn secondary" href="/boxes/' . (int)$boxItem['id'] . '">Ver caja</a></td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div>';
    }
    if ($palletsFromRoll !== []) {
        $body .= '<div style="font-weight:700;margin-top:10px">Pallets generados desde esta bobina</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Pallet</th><th>Cajas</th><th></th></tr></thead><tbody>';
        foreach ($palletsFromRoll as $palletItem) {
            $body .= '<tr>';
            $body .= '<td><a href="/pallets/' . (int)$palletItem['id'] . '">' . h((string)$palletItem['pallet_code']) . '</a></td>';
            $body .= '<td>' . h((string)($palletItem['box_count'] ?? '-')) . '</td>';
            $body .= '<td><a class="btn secondary" href="/pallets/' . (int)$palletItem['id'] . '">Ver pallet</a></td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div>';
    }
    $body .= '</div>';

    $body .= '<div class="card" style="margin-top:12px">
        <div style="font-weight:800;margin-bottom:8px">Trazabilidad</div>
        <table><thead><tr><th>Fecha</th><th>Movimiento</th><th>Origen</th><th>Destino</th><th>Operador</th><th>OT</th><th>Detalle</th></tr></thead><tbody>';
    $traceabilityWorkOrders = [];
    foreach ($traceability as $t) {
        $payload = $t['payload_data'] ?? [];
        $operatorName = is_array($payload) ? (string)($payload['operator_name'] ?? '-') : '-';
        $workOrderLabel = '-';
        if (is_array($payload) && isset($payload['work_order_id']) && (int)$payload['work_order_id'] > 0) {
            $traceabilityWorkOrderId = (int)$payload['work_order_id'];
            if (!isset($traceabilityWorkOrders[$traceabilityWorkOrderId])) {
                $traceabilityWorkOrders[$traceabilityWorkOrderId] = $service->getWorkOrder($traceabilityWorkOrderId);
            }
            $traceabilityWorkOrder = $traceabilityWorkOrders[$traceabilityWorkOrderId];
            $workOrderLabel = is_array($traceabilityWorkOrder)
                ? (string)($traceabilityWorkOrder['ot_code'] ?? ('OT #' . $traceabilityWorkOrderId))
                : ('OT #' . $traceabilityWorkOrderId);
        }
        $detail = [];
        if (is_array($payload) && isset($payload['weight_kg'])) { $detail[] = 'Peso ' . h((string)$payload['weight_kg']) . ' Kg'; }
        if (is_array($payload) && isset($payload['received_qty'])) { $detail[] = 'Cantidad ' . h(formatReceptionValue((float)$payload['received_qty'], 'Unid.')); }
        if (is_array($payload) && isset($payload['microns'])) { $detail[] = 'Gramos ' . h((string)$payload['microns']); }
        if (is_array($payload) && isset($payload['width_mm'])) { $detail[] = 'Ancho ' . h((string)$payload['width_mm']) . ' mm'; }
        if (is_array($payload) && isset($payload['color']) && (string)$payload['color'] !== '') { $detail[] = 'Color ' . h((string)$payload['color']); }
        if (is_array($payload) && isset($payload['meters'])) { $detail[] = 'Metros ' . h((string)$payload['meters']); }
        if ((string)($roll['container_code'] ?? '') !== '' && (string)($t['movement_type'] ?? '') === 'RECEIPT') { $detail[] = 'Contenedor ' . h((string)$roll['container_code']); }
        $fromWarehouseLabel = trim((string)($t['from_warehouse_code'] ?? '')) !== ''
            ? ((string)$t['from_warehouse_code'] . ' - ' . (string)($t['from_warehouse_name'] ?? ''))
            : '-';
        $toWarehouseLabel = trim((string)($t['to_warehouse_code'] ?? '')) !== ''
            ? ((string)$t['to_warehouse_code'] . ' - ' . (string)($t['to_warehouse_name'] ?? ''))
            : '-';
        $body .= '<tr>';
        $body .= '<td>' . h((string)$t['created_at']) . '</td>';
        $body .= '<td>' . h(movementTypeLabel((string)$t['movement_type'])) . '</td>';
        $body .= '<td>' . h($fromWarehouseLabel) . '</td>';
        $body .= '<td>' . h($toWarehouseLabel) . '</td>';
        $body .= '<td>' . h($operatorName) . '</td>';
        $body .= '<td>' . h($workOrderLabel) . '</td>';
        $body .= '<td>' . ($detail === [] ? '-' : implode(' · ', $detail)) . '</td>';
        $body .= '</tr>';
    }
    if ($traceability === []) {
        $body .= '<tr><td colspan="7" class="muted">Sin trazabilidad registrada.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= '<div class="card" style="margin-top:12px">
        <div style="font-weight:800;margin-bottom:8px">Trazabilidad operativa OT</div>
        <table><thead><tr><th>Fecha</th><th>Evento</th><th>OT</th><th>Detalle</th></tr></thead><tbody>';
    foreach ($operationalTraceability as $event) {
        $payload = $event['payload_data'] ?? [];
        $detail = [];
        if ((string)$event['type'] === 'WORK_ORDER_ROLL_ATTACHED') {
            $detail[] = 'Peso proceso ' . (string)($payload['process_weight_kg'] ?? '0') . ' Kg';
        }
        if ((string)$event['type'] === 'WORK_ORDER_ROLL_RELEASED') {
            $detail[] = 'Peso final ' . (string)($payload['final_weight_kg'] ?? '0') . ' Kg';
            $detail[] = 'Motivo ' . (string)($payload['reason'] ?? '-');
        }
        if ((string)$event['type'] === 'WORK_ORDER_FINISHED') {
            $detail[] = 'Cierre OT';
        }
        if (isset($payload['operator_name'])) {
            $detail[] = 'Operador ' . (string)$payload['operator_name'];
        }
        $body .= '<tr>';
        $body .= '<td>' . h((string)$event['created_at']) . '</td>';
        $body .= '<td>' . h(eventTypeLabel((string)$event['type'])) . '</td>';
        $body .= '<td>' . h((string)($event['work_order_label'] ?? '-')) . '</td>';
        $body .= '<td>' . h($detail === [] ? '-' : implode(' · ', $detail)) . '</td>';
        $body .= '</tr>';
    }
    if ($operationalTraceability === []) {
        $body .= '<tr><td colspan="4" class="muted">Sin trazabilidad operativa en OT para esta bobina.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    render('Detalle recepción', $body);
    exit;
}

if (preg_match('#^/rolls/(\d+)/transfer$#', $path, $m) === 1 && $method === 'GET') {
    $id = (int)$m[1];
    $roll = $service->getRoll($id);
    $transferWorkOrders = $service->listWorkOrdersForTransfer();
    if ($roll === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la bobina.</div>');
        exit;
    }

    $warehouses = $service->listWarehouses();
    $transferAvailability = rollTransferAvailability($roll);
    $warehouseTransferEnabled = (bool)$transferAvailability['warehouse']['allowed'];
    $workOrderTransferEnabled = (bool)$transferAvailability['work_order']['allowed'] && $transferWorkOrders !== [];
    $workOrderTransferReason = !$transferAvailability['work_order']['allowed']
        ? (string)$transferAvailability['work_order']['reason']
        : ($transferWorkOrders === [] ? 'No hay OTs disponibles para recibir bobinas.' : '');
    $selectedTransferMode = $warehouseTransferEnabled ? 'warehouse' : ($workOrderTransferEnabled ? 'work_order' : 'warehouse');
    $canSubmitTransfer = $warehouseTransferEnabled || $workOrderTransferEnabled;
    $transferNavParams = inventoryNavigationContext() ? inventoryNavigationParams() : [];

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Transferir bobina</div>
        <a class="btn secondary" href="' . h(withQuery('/rolls/' . (int)$roll['id'], $transferNavParams)) . '">Volver</a>
      </div>';

    $body .= '<div class="card">
        <div class="muted">Bobina</div>
        <div style="font-size:18px;font-weight:800;margin-bottom:10px">' . h((string)$roll['roll_code']) . '</div>';
    if (!$canSubmitTransfer) {
        $body .= '<div class="err" style="margin-bottom:12px">' . h((string)($transferAvailability['warehouse']['reason'] ?: $workOrderTransferReason)) . '</div>';
    }
    $body .= '<form method="post" action="/rolls/' . (int)$roll['id'] . '/transfer">
          <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
          <label>Operador</label>
          <div class="panel" style="padding:10px 12px;font-weight:700">' . h($currentOperatorName) . '</div>
          <div style="height:10px"></div>
          <label>Tipo traspaso</label>
          <select name="transfer_mode" id="transfer_mode">
            <option value="warehouse"' . ($selectedTransferMode === 'warehouse' ? ' selected' : '') . ($warehouseTransferEnabled ? '' : ' disabled') . '>Traspaso a bodega</option>';
    if ($transferWorkOrders !== [] || !$transferAvailability['work_order']['allowed']) {
        $body .= '<option value="work_order"' . ($selectedTransferMode === 'work_order' ? ' selected' : '') . ($workOrderTransferEnabled ? '' : ' disabled') . '>Traspaso a OT</option>';
    }
    $body .= '</select>
          <div class="muted" style="margin-top:8px">' .
              (!$warehouseTransferEnabled && $selectedTransferMode === 'warehouse'
                  ? h((string)$transferAvailability['warehouse']['reason'])
                  : ($selectedTransferMode === 'work_order' && !$workOrderTransferEnabled
                      ? h($workOrderTransferReason)
                      : 'Selecciona un destino válido para esta bobina.')) .
          '</div>
          <div class="row" style="margin-top:10px">
            <div style="flex:1;min-width:220px"><div class="muted">Estado</div><div style="font-weight:800">' . h(rollStatusLabel((string)($roll['status'] ?? ''))) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">Etapa</div><div style="font-weight:800">' . h(rollProcessStageLabel((string)($roll['process_stage'] ?? 'RAW'))) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">OT actual</div><div style="font-weight:800">' . h((string)($roll['work_order_code'] ?? '-')) . '</div></div>
          </div>
          <div id="transfer_warehouse_wrap" style="margin-top:10px">
          <label>Bodega destino</label>
          <select name="to_warehouse_id"' . ($warehouseTransferEnabled ? '' : ' disabled') . '>
            <option value=\"\">Seleccionar</option>';
    foreach ($warehouses as $w) {
        $disabled = ((int)$w['code'] === (int)$roll['warehouse_code']) ? ' disabled' : '';
        $body .= '<option value="' . (int)$w['id'] . '"' . $disabled . '>' . h((string)$w['code']) . ' - ' . h((string)$w['name']) . '</option>';
    }
    $body .= '</select></div>';
    if ($transferWorkOrders !== []) {
        $body .= '<div id="transfer_ot_wrap" style="margin-top:10px;display:none">
            <label>OT destino</label>
            <select name="work_order_id"' . ($workOrderTransferEnabled ? '' : ' disabled') . '>
              <option value="">Seleccionar</option>';
        foreach ($transferWorkOrders as $wo) {
            $statusLabel = (string)$wo['status'] === 'ACTIVE' ? ' [ACTIVA]' : '';
            $body .= '<option value="' . (int)$wo['id'] . '">' . h((string)$wo['ot_code']) . ' - ' . h((string)$wo['sku_final']) . $statusLabel . '</option>';
        }
        $body .= '</select>
          </div>';
    }
    $body .= '
          <div style="margin-top:12px">
            <button class="btn" type="submit"' . ($canSubmitTransfer ? '' : ' disabled') . '>Confirmar traspaso</button>
          </div>
          <script>
            (function () {
              var mode = document.getElementById("transfer_mode");
              var whWrap = document.getElementById("transfer_warehouse_wrap");
              var otWrap = document.getElementById("transfer_ot_wrap");
              if (!mode) return;
              function syncMode() {
                var isOt = mode.value === "work_order";
                if (whWrap) whWrap.style.display = isOt ? "none" : "";
                if (otWrap) otWrap.style.display = isOt ? "" : "none";
              }
              mode.addEventListener("change", syncMode);
              syncMode();
            })();
          </script>
        </form>
      </div>';

    render('Transferir', $body);
    exit;
}

if (preg_match('#^/rolls/(\d+)/transfer$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $id = (int)$m[1];
    $transferMode = (string)($_POST['transfer_mode'] ?? 'warehouse');
    $toWarehouseId = $transferMode === 'warehouse' ? (int)($_POST['to_warehouse_id'] ?? 0) : 0;
    $workOrderId = $transferMode === 'work_order' ? (int)($_POST['work_order_id'] ?? 0) : null;
    $result = $service->transferRoll($id, $toWarehouseId, $currentOperatorName, $workOrderId);
    if ($result['ok'] === true) {
        header('Location: /rolls/' . $id);
        exit;
    }

    $roll = $service->getRoll($id);
    if ($roll === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la bobina.</div>');
        exit;
    }
    $warehouses = $service->listWarehouses();
    $transferWorkOrders = $service->listWorkOrdersForTransfer();
    $transferAvailability = rollTransferAvailability($roll);
    $warehouseTransferEnabled = (bool)$transferAvailability['warehouse']['allowed'];
    $workOrderTransferEnabled = (bool)$transferAvailability['work_order']['allowed'] && $transferWorkOrders !== [];
    $workOrderTransferReason = !$transferAvailability['work_order']['allowed']
        ? (string)$transferAvailability['work_order']['reason']
        : ($transferWorkOrders === [] ? 'No hay OTs disponibles para recibir bobinas.' : '');
    $selectedTransferMode = (string)($_POST['transfer_mode'] ?? ($warehouseTransferEnabled ? 'warehouse' : ($workOrderTransferEnabled ? 'work_order' : 'warehouse')));
    if ($selectedTransferMode === 'work_order' && !$workOrderTransferEnabled) {
        $selectedTransferMode = $warehouseTransferEnabled ? 'warehouse' : 'work_order';
    }
    if ($selectedTransferMode === 'warehouse' && !$warehouseTransferEnabled && $workOrderTransferEnabled) {
        $selectedTransferMode = 'work_order';
    }
    $canSubmitTransfer = $warehouseTransferEnabled || $workOrderTransferEnabled;

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Transferir bobina</div>
        <a class="btn secondary" href="/rolls/' . (int)$roll['id'] . '">Volver</a>
      </div>';
    $body .= '<div class="err" style="margin-bottom:12px"><ul style="margin:0;padding-left:16px">';
    foreach ($result['errors'] as $msg) {
        $body .= '<li>' . h((string)$msg) . '</li>';
    }
    $body .= '</ul></div>';

    $body .= '<div class="card">
        <div class="muted">Bobina</div>
        <div style="font-size:18px;font-weight:800;margin-bottom:10px">' . h((string)$roll['roll_code']) . '</div>
        <form method="post" action="/rolls/' . (int)$roll['id'] . '/transfer">
          <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
          <label>Operador</label>
          <div class="panel" style="padding:10px 12px;font-weight:700">' . h($currentOperatorName) . '</div>
          <div style="height:10px"></div>
          <label>Tipo traspaso</label>
          <select name="transfer_mode" id="transfer_mode">
            <option value="warehouse"' . ($selectedTransferMode === 'warehouse' ? ' selected' : '') . ($warehouseTransferEnabled ? '' : ' disabled') . '>Traspaso a bodega</option>';
    if ($transferWorkOrders !== [] || !$transferAvailability['work_order']['allowed']) {
        $body .= '<option value="work_order"' . ($selectedTransferMode === 'work_order' ? ' selected' : '') . ($workOrderTransferEnabled ? '' : ' disabled') . '>Traspaso a OT</option>';
    }
    $body .= '</select>
          <div class="muted" style="margin-top:8px">' .
              (!$warehouseTransferEnabled && $selectedTransferMode === 'warehouse'
                  ? h((string)$transferAvailability['warehouse']['reason'])
                  : ($selectedTransferMode === 'work_order' && !$workOrderTransferEnabled
                      ? h($workOrderTransferReason)
                      : 'Selecciona un destino válido para esta bobina.')) .
          '</div>
          <div class="row" style="margin-top:10px">
            <div style="flex:1;min-width:220px"><div class="muted">Estado</div><div style="font-weight:800">' . h(rollStatusLabel((string)($roll['status'] ?? ''))) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">Etapa</div><div style="font-weight:800">' . h(rollProcessStageLabel((string)($roll['process_stage'] ?? 'RAW'))) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">OT actual</div><div style="font-weight:800">' . h((string)($roll['work_order_code'] ?? '-')) . '</div></div>
          </div>
          <div id="transfer_warehouse_wrap" style="margin-top:10px">
          <label>Bodega destino</label>
          <select name="to_warehouse_id"' . ($warehouseTransferEnabled ? '' : ' disabled') . '>
            <option value=\"\">Seleccionar</option>';
    foreach ($warehouses as $w) {
        $selected = ((int)$w['id'] === (int)($_POST['to_warehouse_id'] ?? 0)) ? ' selected' : '';
        $disabled = ((int)$w['code'] === (int)$roll['warehouse_code']) ? ' disabled' : '';
        $body .= '<option value="' . (int)$w['id'] . '"' . $selected . $disabled . '>' . h((string)$w['code']) . ' - ' . h((string)$w['name']) . '</option>';
    }
    $body .= '</select></div>';
    if ($transferWorkOrders !== []) {
        $selectedWorkOrderId = (int)($_POST['work_order_id'] ?? 0);
        $body .= '<div id="transfer_ot_wrap" style="margin-top:10px;display:none">
            <label>OT destino</label>
            <select name="work_order_id"' . ($workOrderTransferEnabled ? '' : ' disabled') . '>
              <option value="">Seleccionar</option>';
        foreach ($transferWorkOrders as $wo) {
            $selected = (int)$wo['id'] === $selectedWorkOrderId ? ' selected' : '';
            $statusLabel = (string)$wo['status'] === 'ACTIVE' ? ' [ACTIVA]' : '';
            $body .= '<option value="' . (int)$wo['id'] . '"' . $selected . '>' . h((string)$wo['ot_code']) . ' - ' . h((string)$wo['sku_final']) . $statusLabel . '</option>';
        }
        $body .= '</select>
          </div>';
    }
    $body .= '
          <div style="margin-top:12px">
            <button class="btn" type="submit"' . ($canSubmitTransfer ? '' : ' disabled') . '>Confirmar traspaso</button>
          </div>
          <script>
            (function () {
              var mode = document.getElementById("transfer_mode");
              var whWrap = document.getElementById("transfer_warehouse_wrap");
              var otWrap = document.getElementById("transfer_ot_wrap");
              if (!mode) return;
              function syncMode() {
                var isOt = mode.value === "work_order";
                if (whWrap) whWrap.style.display = isOt ? "none" : "";
                if (otWrap) otWrap.style.display = isOt ? "" : "none";
              }
              mode.addEventListener("change", syncMode);
              syncMode();
            })();
          </script>
        </form>
      </div>';

    render('Transferir', $body);
    exit;
}

if ($path === '/cut' && $method === 'GET') {
    $scanCode = trim((string)($_GET['code'] ?? ''));
    $cutRoll = $scanCode !== '' ? $service->getRollByScanCode($scanCode) : null;
    $cutRolls = $service->listProducedRollsReadyForCut();
    $warehouses = array_values(array_filter($service->listWarehouses(), static fn(array $warehouse): bool => in_array((int)$warehouse['code'], [700, 1000], true)));
    $cutMessage = trim((string)($_GET['msg'] ?? ''));
    $cutError = trim((string)($_GET['error'] ?? ''));
    if ($cutRoll !== null) {
        $cutStage = strtoupper(trim((string)($cutRoll['process_stage'] ?? 'RAW')));
        $cutStatus = strtoupper(trim((string)($cutRoll['status'] ?? '')));
        if ($cutStage !== 'PRINTED' || $cutStatus === 'CONSUMED') {
            $cutError = 'La bobina escaneada no está disponible para corte.';
            $cutRoll = null;
        }
    }

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Proceso de corte</div>
          <div class="muted">Escanea la bobina salida de producción y conviértela en unidades, cajas y pallets.</div>
        </div>
        <a class="btn secondary" href="/pallets">Ver pallets terminados</a>
      </div>';
    if ($cutMessage !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($cutMessage) . '</div>';
    }
    if ($cutError !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($cutError) . '</div>';
    }

    $body .= '<div class="card" style="margin-bottom:12px">
        <form method="get" action="/cut">
          <div class="row nowrap">
            <div style="flex:1;min-width:260px">
              <label>Bobina salida producción</label>
              <input name="code" type="text" value="' . h($scanCode) . '" placeholder="Escanear código de bobina">
            </div>
            <div style="min-width:180px;align-self:end"><button class="btn" type="submit">Buscar</button></div>
          </div>
        </form>
      </div>';

    if ($cutRoll !== null) {
        $body .= '<div class="card" style="margin-bottom:12px">
            <div style="font-weight:800;margin-bottom:8px">Bobina lista para corte</div>
            <div class="row">
              <div style="flex:1;min-width:180px"><div class="muted">Código</div><div style="font-weight:800">' . h((string)$cutRoll['roll_code']) . '</div></div>
              <div style="flex:1;min-width:180px"><div class="muted">Especificación</div><div style="font-weight:800">' . h((string)$cutRoll['sku_description']) . '</div></div>
              <div style="flex:1;min-width:180px"><div class="muted">Peso</div><div style="font-weight:800">' . h((string)$cutRoll['weight_kg']) . ' Kg</div></div>
              <div style="flex:1;min-width:180px"><div class="muted">Etapa</div><div style="font-weight:800">' . h((string)($cutRoll['process_stage'] ?? '-')) . '</div></div>
            </div>
          </div>';

        $body .= '<div class="card">
            <form method="post" action="/cut/process">
              <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
              <input type="hidden" name="source_roll_id" value="' . (int)$cutRoll['id'] . '">
              <div class="row">
                <div style="flex:1;min-width:220px">
                  <label>Destino</label>
                  <select name="destination_mode" id="cut_destination_mode">
                    <option value="STOCK">Almacenar en stock</option>
                    <option value="CUSTOMER_ORDER">Orden de compra cliente</option>
                  </select>
                </div>
                <div style="flex:1;min-width:220px" id="cut_customer_wrap" style="display:none">
                  <label>OC cliente</label>
                  <input name="customer_order_ref" type="text" placeholder="OC cliente">
                </div>
              </div>
              <div class="row" style="margin-top:10px">
                <div style="flex:1;min-width:220px">
                  <label>Asignación de bodega</label>
                  <input type="text" value="La realiza Bodega al recibir el pallet terminado" readonly>
                </div>
                <div style="flex:1;min-width:180px">
                  <label>Unidades totales</label>
                  <input name="units_total" type="number" step="1" min="1" required>
                </div>
                <div style="flex:1;min-width:180px">
                  <label>Cantidad de cajas</label>
                  <input name="box_qty" type="number" step="1" min="1" required>
                </div>
                <div style="flex:1;min-width:180px">
                  <label>Cajas por pallet</label>
                  <input name="boxes_per_pallet" type="number" step="1" min="1" value="10" required>
                </div>
              </div>
              <div style="margin-top:12px"><button class="btn" type="submit">Procesar corte</button></div>
            </form>
            <script>
              (function(){
                var mode = document.getElementById("cut_destination_mode");
                var customerWrap = document.getElementById("cut_customer_wrap");
                if (!mode) return;
                function syncCutMode(){
                  var isCustomer = mode.value === "CUSTOMER_ORDER";
                  if (customerWrap) customerWrap.style.display = isCustomer ? "" : "none";
                }
                mode.addEventListener("change", syncCutMode);
                syncCutMode();
              })();
            </script>
          </div>';
    }

    $body .= '<div class="card" style="margin-top:12px">
        <div style="font-weight:800;margin-bottom:8px">Bobinas disponibles para corte</div>
        <table><thead><tr><th>Código</th><th>OT</th><th>SKU final</th><th>Peso</th><th></th></tr></thead><tbody>';
    foreach ($cutRolls as $roll) {
        $body .= '<tr>';
        $body .= '<td>' . h((string)$roll['roll_code']) . '</td>';
        $body .= '<td>' . h((string)($roll['ot_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($roll['sku_final'] ?? $roll['sku_description'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)$roll['weight_kg']) . ' Kg</td>';
        $body .= '<td><a class="btn secondary" href="/cut?code=' . urlencode((string)$roll['roll_code']) . '">Usar</a></td>';
        $body .= '</tr>';
    }
    if ($cutRolls === []) {
        $body .= '<tr><td colspan="5" class="muted">Sin bobinas pendientes de corte.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    render('Corte', $body);
    exit;
}

if ($path === '/cut/process' && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $result = $service->processCutRoll(
        (int)($_POST['source_roll_id'] ?? 0),
        (int)($_POST['units_total'] ?? 0),
        (int)($_POST['box_qty'] ?? 0),
        (int)($_POST['boxes_per_pallet'] ?? 0),
        (string)($_POST['destination_mode'] ?? 'STOCK'),
        isset($_POST['customer_order_ref']) ? (string)$_POST['customer_order_ref'] : null,
        isset($_POST['warehouse_id']) && (int)$_POST['warehouse_id'] > 0 ? (int)$_POST['warehouse_id'] : null,
        $currentOperatorName
    );
    $sourceRoll = $service->getRoll((int)($_POST['source_roll_id'] ?? 0));
    $workOrderId = $sourceRoll !== null ? (int)($sourceRoll['source_work_order_id'] ?? 0) : 0;
    $redirectCode = $sourceRoll !== null ? urlencode((string)$sourceRoll['roll_code']) : '';
    $redirectBase = $workOrderId > 0
        ? '/work-orders/' . $workOrderId . '/start'
        : '/cut' . ($redirectCode !== '' ? '?code=' . $redirectCode : '');
    $separator = str_contains($redirectBase, '?') ? '&' : '?';
    if (($result['ok'] ?? false) !== true) {
        $errorText = 'No se pudo procesar el corte.';
        if (isset($result['errors']) && is_array($result['errors']) && $result['errors'] !== []) {
            $errorText = implode(' | ', array_map(static fn($value): string => (string)$value, array_values($result['errors'])));
        }
        $errorKey = $workOrderId > 0 ? 'cut_error' : 'error';
        header('Location: ' . $redirectBase . $separator . $errorKey . '=' . urlencode($errorText));
        exit;
    }

    $printedBoxes = 0;
    $printedPallets = 0;
    $printErrors = [];
    if ($printer->isEnabled()) {
        foreach ((array)($result['boxes'] ?? []) as $createdBox) {
            $boxId = (int)($createdBox['id'] ?? 0);
            if ($boxId <= 0) {
                continue;
            }
            $box = $service->getBox($boxId);
            if (!is_array($box)) {
                $printErrors[] = 'No se encontró la caja #' . $boxId . ' para imprimir.';
                continue;
            }
            $printResult = $printer->printBoxLabel($box);
            if (($printResult['ok'] ?? false) === true) {
                $printedBoxes++;
            } else {
                $printErrors[] = (string)($printResult['error'] ?? ('No se pudo imprimir la caja ' . ($box['box_code'] ?? ('#' . $boxId))));
            }
        }

        foreach ((array)($result['pallet_ids'] ?? []) as $palletId) {
            $pallet = $service->getPallet((int)$palletId);
            if (!is_array($pallet)) {
                $printErrors[] = 'No se encontró el pallet #' . (int)$palletId . ' para imprimir.';
                continue;
            }
            $palletBoxes = $service->listBoxesByPallet((int)$palletId);
            $printResult = $printer->printPalletLabel($pallet, $palletBoxes);
            if (($printResult['ok'] ?? false) === true) {
                $printedPallets++;
            } else {
                $printErrors[] = (string)($printResult['error'] ?? ('No se pudo imprimir el pallet ' . ($pallet['pallet_code'] ?? ('#' . (int)$palletId))));
            }
        }
    }

    $message = 'Corte procesado: '
        . count((array)($result['boxes'] ?? []))
        . ' cajas y '
        . count((array)($result['pallet_ids'] ?? []))
        . ' pallets.';
    if ($printer->isEnabled()) {
        $message .= ' Impresión Zebra: '
            . $printedBoxes
            . ' cajas y '
            . $printedPallets
            . ' pallets.';
    }

    if ($workOrderId > 0) {
        header('Location: ' . $redirectBase . $separator . 'cut_completed=1' . ($printErrors !== [] ? '&cut_error=' . urlencode(implode(' | ', $printErrors)) : ''));
        exit;
    }
    header('Location: ' . $redirectBase . $separator . 'msg=' . urlencode($message) . ($printErrors !== [] ? '&error=' . urlencode(implode(' | ', $printErrors)) : ''));
    exit;
}

if (preg_match('#^/boxes/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $box = $service->getBox((int)$m[1]);
    if ($box === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la caja.</div>');
        exit;
    }
    $boxSourceRoll = $service->getRoll((int)$box['source_roll_id']);
    $boxNavParams = inventoryNavigationContext() ? inventoryNavigationParams() : [];
    $boxBackUrl = inventoryNavigationContext() ? inventoryReturnUrl() : '/cut';

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Caja ' . h((string)$box['box_code']) . '</div>
        <div class="row"><a class="btn secondary" href="' . h($boxBackUrl) . '">' . h(inventoryNavigationContext() ? 'Volver a inventario' : 'Volver') . '</a><a class="btn secondary" href="' . h(withQuery('/rolls/' . (int)$box['source_roll_id'], $boxNavParams)) . '">Bobina origen</a><a class="btn" href="/boxes/' . (int)$box['id'] . '/label?auto_print=1" target="_blank" rel="noopener">Etiqueta</a><a class="btn secondary" href="/boxes/' . (int)$box['id'] . '/label?server_print=1" target="_blank" rel="noopener">Imprimir Zebra</a></div>
      </div>';
    $body .= '<div class="card"><div class="row">
        <div style="flex:1;min-width:180px"><div class="muted">OT</div><div style="font-weight:800">' . h((string)($box['ot_code'] ?? '-')) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Bobina origen</div><div style="font-weight:800">' . h((string)$box['source_roll_code']) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Unidades</div><div style="font-weight:800">' . h((string)$box['units_qty']) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Pallet</div><div style="font-weight:800">' . h((string)($box['pallet_code'] ?? '-')) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Bodega actual</div><div style="font-weight:800">' . h(((string)($box['warehouse_code'] ?? '') !== '' ? ((string)$box['warehouse_code'] . ' - ' . (string)($box['warehouse_name'] ?? '')) : '-')) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Estado</div><div style="font-weight:800">' . h(boxStatusLabel((string)($box['status'] ?? ''))) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Operador</div><div style="font-weight:800">' . h((string)$box['operator_name']) . '</div></div>
      </div></div>';
    $body .= '<div class="card" style="margin-top:12px"><div style="font-weight:800;margin-bottom:8px">Cadena de trazabilidad</div><div class="row">';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">OT</div><div style="font-weight:700">';
    if ((int)($box['work_order_id'] ?? 0) > 0) {
        $boxOtLabel = (string)($box['ot_code'] ?? ('OT #' . (int)$box['work_order_id']));
        $body .= canAccessWorkOrderTraceability()
            ? '<a href="' . h(withQuery('/work-orders/' . (int)$box['work_order_id'] . '/traceability', $boxNavParams)) . '">' . h($boxOtLabel) . '</a>'
            : h($boxOtLabel);
    } else {
        $body .= '-';
    }
    $body .= '</div></div>';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">Bobina salida</div><div style="font-weight:700"><a href="' . h(withQuery('/rolls/' . (int)$box['source_roll_id'], $boxNavParams)) . '">' . h((string)$box['source_roll_code']) . '</a></div></div>';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">Bobina entrada</div><div style="font-weight:700">';
    if (is_array($boxSourceRoll) && (int)($boxSourceRoll['parent_roll_id'] ?? 0) > 0) {
        $body .= '<a href="' . h(withQuery('/rolls/' . (int)$boxSourceRoll['parent_roll_id'], $boxNavParams)) . '">' . h((string)($boxSourceRoll['parent_roll_code'] ?? ('#' . (int)$boxSourceRoll['parent_roll_id']))) . '</a>';
    } else {
        $body .= '-';
    }
    $body .= '</div></div>';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">Pallet</div><div style="font-weight:700">';
    if ((int)($box['pallet_id'] ?? 0) > 0) {
        $body .= '<a href="' . h(withQuery('/pallets/' . (int)$box['pallet_id'], $boxNavParams)) . '">' . h((string)($box['pallet_code'] ?? ('#' . (int)$box['pallet_id']))) . '</a>';
    } else {
        $body .= '-';
    }
    $body .= '</div></div>';
    $body .= '</div></div>';
    render('Caja', $body);
    exit;
}

if ($path === '/pallets' && $method === 'GET') {
    $isWarehousePalletArea = isWarehousePalletAssignmentArea();
    $palletSearch = trim((string)($_GET['q'] ?? ''));
    $palletWarehouseId = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;
    $palletWarehouses = $service->listWarehouses();
    $pallets = $service->listPallets($palletSearch, $palletWarehouseId > 0 ? $palletWarehouseId : null, true);
    $palletMessage = trim((string)($_GET['msg'] ?? ''));
    $palletError = trim((string)($_GET['error'] ?? ''));
    $returnQuery = [];
    if ($palletSearch !== '') {
        $returnQuery['q'] = $palletSearch;
    }
    if ($palletWarehouseId > 0) {
        $returnQuery['warehouse_id'] = (string)$palletWarehouseId;
    }
    $returnTo = '/pallets' . ($returnQuery !== [] ? '?' . http_build_query($returnQuery) : '');

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Asignación de pallets terminados</div>
          <div class="muted">Bandeja de pallets pendientes por asignar a una bodega final.</div>
        </div>
        <a class="btn secondary" href="' . ($isWarehousePalletArea ? '/stock' : '/cut') . '">Volver</a>
      </div>';
    if ($palletMessage !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($palletMessage) . '</div>';
    }
    if ($palletError !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($palletError) . '</div>';
    }

    if (!$isWarehousePalletArea) {
        $body .= '<div class="card" style="margin-bottom:12px"><div class="muted">La asignación de pallets a bodega se realiza desde el área de Bodega/Inventario.</div></div>';
    }

    $body .= '<div class="card" style="margin-bottom:12px">
        <form method="get" action="/pallets">
          <div class="row nowrap">
            <div style="flex:1;min-width:220px">
              <label>Buscar</label>
              <input type="text" name="q" value="' . h($palletSearch) . '" placeholder="Código pallet, OT, bobina, SKU u OC cliente">
            </div>
            <div style="flex:1;min-width:220px">
              <label>Bodega actual</label>
              <select name="warehouse_id">
                <option value="">Todas</option>';
    foreach ($palletWarehouses as $warehouse) {
        $selected = (int)$warehouse['id'] === $palletWarehouseId ? ' selected' : '';
        $body .= '<option value="' . (int)$warehouse['id'] . '"' . $selected . '>' . h((string)$warehouse['code']) . ' - ' . h((string)$warehouse['name']) . '</option>';
    }
    $body .= '</select>
            </div>
            <div style="min-width:220px;align-self:end;display:flex;gap:8px">
              <button class="btn" type="submit">Filtrar</button>
              <a class="btn secondary" href="/pallets">Limpiar</a>
            </div>
          </div>
        </form>
      </div>';

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Pallets pendientes de asignación</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Pallet</th><th>OT</th><th>Bobina origen</th><th>Cajas</th><th>Unidades</th><th>Bodega actual</th><th>Estado</th><th>Destino</th><th>Asignación</th><th></th></tr></thead><tbody>';
    foreach ($pallets as $palletRow) {
        $warehouseLabel = trim((string)($palletRow['warehouse_code'] ?? ''));
        if ($warehouseLabel === '') {
            $warehouseLabel = 'Sin bodega';
        } elseif ((string)($palletRow['warehouse_name'] ?? '') !== '') {
            $warehouseLabel .= ' - ' . (string)$palletRow['warehouse_name'];
        }
        $body .= '<tr>';
        $body .= '<td>' . h((string)$palletRow['pallet_code']) . '</td>';
        $body .= '<td>' . h((string)($palletRow['ot_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($palletRow['source_roll_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($palletRow['box_count'] ?? '0')) . '</td>';
        $body .= '<td>' . h((string)($palletRow['units_total'] ?? '0')) . '</td>';
        $body .= '<td>' . h($warehouseLabel) . '</td>';
        $body .= '<td>' . h(palletStatusLabel((string)($palletRow['status'] ?? ''))) . '</td>';
        $body .= '<td>' . h((string)($palletRow['destination_mode'] ?? '-')) . '</td>';
        if ($isWarehousePalletArea) {
            $body .= '<td><form method="post" action="/pallets/' . (int)$palletRow['id'] . '/move" style="margin:0"><input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><input type="hidden" name="return_to" value="' . h($returnTo) . '"><div class="row nowrap" style="gap:6px;align-items:end">';
            $body .= '<select name="warehouse_id" style="min-width:180px"><option value="">Asignar a...</option>';
            foreach ($palletWarehouses as $warehouse) {
                $disabled = (int)$warehouse['id'] === (int)($palletRow['warehouse_id'] ?? 0) ? ' disabled' : '';
                $body .= '<option value="' . (int)$warehouse['id'] . '"' . $disabled . '>' . h((string)$warehouse['code']) . ' - ' . h((string)$warehouse['name']) . '</option>';
            }
            $body .= '</select><button class="btn secondary" type="submit">Asignar</button></div></form></td>';
        } else {
            $body .= '<td><span class="muted">Solo Bodega</span></td>';
        }
        $body .= '<td><a class="btn secondary" href="/pallets/' . (int)$palletRow['id'] . '">Ver</a></td>';
        $body .= '</tr>';
    }
    if ($pallets === []) {
        $body .= '<tr><td colspan="10" class="muted">No hay pallets para los filtros seleccionados.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    render('Pallets', $body);
    exit;
}

if (preg_match('#^/pallets/(\d+)/move$#', $path, $m) === 1 && $method === 'POST') {
    if (!isWarehousePalletAssignmentArea()) {
        http_response_code(403);
        render('Acceso denegado', '<div class="card">La asignación de pallets a bodega solo puede realizarla el área de Bodega.</div>');
        exit;
    }
    requireCsrf();
    $palletId = (int)$m[1];
    $returnTo = trim((string)($_POST['return_to'] ?? ''));
    if ($returnTo === '' || !str_starts_with($returnTo, '/')) {
        $returnTo = '/pallets/' . $palletId;
    }
    $result = $service->movePalletToWarehouse(
        $palletId,
        (int)($_POST['warehouse_id'] ?? 0),
        $currentOperatorName
    );
    $separator = str_contains($returnTo, '?') ? '&' : '?';
    if (($result['ok'] ?? false) !== true) {
        $errorText = 'No se pudo mover el pallet.';
        if (isset($result['errors']) && is_array($result['errors']) && $result['errors'] !== []) {
            $errorText = implode(' | ', array_map(static fn($value): string => (string)$value, array_values($result['errors'])));
        }
        header('Location: ' . $returnTo . $separator . 'error=' . urlencode($errorText));
        exit;
    }
    header('Location: ' . $returnTo . $separator . 'msg=' . urlencode('Pallet movido correctamente a la bodega seleccionada.'));
    exit;
}

if (preg_match('#^/pallets/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $isWarehousePalletArea = isWarehousePalletAssignmentArea();
    $pallet = $service->getPallet((int)$m[1]);
    if ($pallet === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe el pallet.</div>');
        exit;
    }
    $palletSourceRoll = $service->getRoll((int)$pallet['source_roll_id']);
    $palletBoxes = $service->listBoxesByPallet((int)$pallet['id']);
    $activeMaquilaOrder = $service->getOpenMaquilaOrderByPallet((int)$pallet['id']);
    $palletMaquilaOrders = $service->listMaquilaOrdersByPallet((int)$pallet['id']);
    $palletWarehouses = $service->listWarehouses();
    $palletMessage = trim((string)($_GET['msg'] ?? ''));
    $palletError = trim((string)($_GET['error'] ?? ''));
    $palletNavParams = inventoryNavigationContext() ? inventoryNavigationParams() : [];
    $palletBackUrl = inventoryNavigationContext() ? inventoryReturnUrl() : '/pallets';
    $currentWarehouseLabel = trim((string)($pallet['warehouse_code'] ?? ''));
    if ($currentWarehouseLabel === '') {
        $currentWarehouseLabel = 'Sin bodega';
    } elseif ((string)($pallet['warehouse_name'] ?? '') !== '') {
        $currentWarehouseLabel .= ' - ' . (string)$pallet['warehouse_name'];
    }

    $maquilaAction = '';
    if (currentSessionArea() === 'RECEPTION') {
        $maquilaAction = $activeMaquilaOrder !== null
            ? '<a class="btn secondary" href="/maquila/' . (int)$activeMaquilaOrder['id'] . '">Ver maquila</a>'
            : '<a class="btn secondary" href="/maquila?pallet_id=' . (int)$pallet['id'] . '">Enviar a maquila</a>';
    }

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Pallet ' . h((string)$pallet['pallet_code']) . '</div>
        <div class="row"><a class="btn secondary" href="' . h($palletBackUrl) . '">' . h(inventoryNavigationContext() ? 'Volver a inventario' : 'Volver') . '</a>' . $maquilaAction . '<a class="btn secondary" href="' . h(withQuery('/rolls/' . (int)$pallet['source_roll_id'], $palletNavParams)) . '">Bobina origen</a><a class="btn" href="/pallets/' . (int)$pallet['id'] . '/label?auto_print=1" target="_blank" rel="noopener">Etiqueta</a><a class="btn secondary" href="/pallets/' . (int)$pallet['id'] . '/label?server_print=1" target="_blank" rel="noopener">Imprimir Zebra</a></div>
      </div>';
    if ($palletMessage !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($palletMessage) . '</div>';
    }
    if ($palletError !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($palletError) . '</div>';
    }
    $body .= '<div class="card"><div class="row">
        <div style="flex:1;min-width:180px"><div class="muted">OT</div><div style="font-weight:800">' . h((string)($pallet['ot_code'] ?? '-')) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Bobina origen</div><div style="font-weight:800">' . h((string)$pallet['source_roll_code']) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Cajas</div><div style="font-weight:800">' . h((string)$pallet['box_count']) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Destino</div><div style="font-weight:800">' . h((string)$pallet['destination_mode']) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Bodega actual</div><div style="font-weight:800">' . h($currentWarehouseLabel) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Estado</div><div style="font-weight:800">' . h(palletStatusLabel((string)($pallet['status'] ?? ''))) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Operador</div><div style="font-weight:800">' . h((string)$pallet['operator_name']) . '</div></div>
      </div></div>';
    if ($isWarehousePalletArea) {
        $body .= '<div class="card" style="margin-top:12px"><div style="font-weight:800;margin-bottom:8px">Asignar pallet a bodega</div>
            <form method="post" action="/pallets/' . (int)$pallet['id'] . '/move">
              <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
              <input type="hidden" name="return_to" value="/pallets/' . (int)$pallet['id'] . '">
              <div class="row" style="align-items:end">
                <div style="flex:1;min-width:260px">
                  <label>Bodega destino</label>
                  <select name="warehouse_id" required>
                    <option value="">Seleccionar</option>';
        foreach ($palletWarehouses as $warehouse) {
            $disabled = (int)$warehouse['id'] === (int)($pallet['warehouse_id'] ?? 0) ? ' disabled' : '';
            $body .= '<option value="' . (int)$warehouse['id'] . '"' . $disabled . '>' . h((string)$warehouse['code']) . ' - ' . h((string)$warehouse['name']) . '</option>';
        }
        $body .= '</select>
                </div>
                <div style="min-width:220px">
                  <button class="btn" type="submit">Asignar a bodega</button>
                </div>
              </div>
            </form>
          </div>';
    } else {
        $body .= '<div class="card" style="margin-top:12px"><div class="muted">La asignación de este pallet a bodega debe realizarla el área de Bodega desde Inventario.</div></div>';
    }
    if ($palletMaquilaOrders !== []) {
        $body .= '<div class="card" style="margin-top:12px"><div style="font-weight:800;margin-bottom:8px">Historial de maquila</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Orden</th><th>Taller</th><th>Salida</th><th>Retorno</th><th>Merma</th><th>Estado</th><th></th></tr></thead><tbody>';
        foreach ($palletMaquilaOrders as $palletMaquilaOrder) {
            $body .= '<tr>';
            $body .= '<td>#' . (int)$palletMaquilaOrder['id'] . '</td>';
            $body .= '<td>' . h((string)$palletMaquilaOrder['workshop_name']) . '</td>';
            $body .= '<td>' . h(formatReceptionValue((float)($palletMaquilaOrder['outgoing_weight_kg'] ?? 0), 'Kg')) . ' Kg</td>';
            $body .= '<td>' . h(formatReceptionValue((float)($palletMaquilaOrder['returned_weight_kg'] ?? 0), 'Kg')) . ' Kg</td>';
            $body .= '<td>' . h(formatReceptionValue((float)($palletMaquilaOrder['waste_weight_kg'] ?? 0), 'Kg')) . ' Kg</td>';
            $body .= '<td>' . h(maquilaStatusLabel((string)$palletMaquilaOrder['status'])) . '</td>';
            $body .= '<td><a class="btn secondary" href="/maquila/' . (int)$palletMaquilaOrder['id'] . '">Ver orden</a></td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div></div>';
    }
    $body .= '<div class="card" style="margin-top:12px"><div style="font-weight:800;margin-bottom:8px">Cadena de trazabilidad</div><div class="row">';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">OT</div><div style="font-weight:700">';
    if ((int)($pallet['work_order_id'] ?? 0) > 0) {
        $palletOtLabel = (string)($pallet['ot_code'] ?? ('OT #' . (int)$pallet['work_order_id']));
        $body .= canAccessWorkOrderTraceability()
            ? '<a href="' . h(withQuery('/work-orders/' . (int)$pallet['work_order_id'] . '/traceability', $palletNavParams)) . '">' . h($palletOtLabel) . '</a>'
            : h($palletOtLabel);
    } else {
        $body .= '-';
    }
    $body .= '</div></div>';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">Bobina salida</div><div style="font-weight:700"><a href="' . h(withQuery('/rolls/' . (int)$pallet['source_roll_id'], $palletNavParams)) . '">' . h((string)$pallet['source_roll_code']) . '</a></div></div>';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">Bobina entrada</div><div style="font-weight:700">';
    if (is_array($palletSourceRoll) && (int)($palletSourceRoll['parent_roll_id'] ?? 0) > 0) {
        $body .= '<a href="' . h(withQuery('/rolls/' . (int)$palletSourceRoll['parent_roll_id'], $palletNavParams)) . '">' . h((string)($palletSourceRoll['parent_roll_code'] ?? ('#' . (int)$palletSourceRoll['parent_roll_id']))) . '</a>';
    } else {
        $body .= '-';
    }
    $body .= '</div></div>';
    $body .= '</div></div>';
    $body .= '<div class="card" style="margin-top:12px"><div style="font-weight:800;margin-bottom:8px">Cajas contenidas</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Caja</th><th>Unidades</th><th>Bodega</th><th>Estado</th><th></th></tr></thead><tbody>';
    foreach ($palletBoxes as $palletBox) {
        $warehouseLabel = trim((string)($palletBox['warehouse_code'] ?? ''));
        if ($warehouseLabel === '') {
            $warehouseLabel = '-';
        } elseif ((string)($palletBox['warehouse_name'] ?? '') !== '') {
            $warehouseLabel .= ' - ' . (string)$palletBox['warehouse_name'];
        }
        $body .= '<tr>';
        $body .= '<td><a href="' . h(withQuery('/boxes/' . (int)$palletBox['id'], $palletNavParams)) . '">' . h((string)$palletBox['box_code']) . '</a></td>';
        $body .= '<td>' . h((string)($palletBox['units_qty'] ?? '-')) . '</td>';
        $body .= '<td>' . h($warehouseLabel) . '</td>';
        $body .= '<td>' . h(boxStatusLabel((string)($palletBox['status'] ?? ''))) . '</td>';
        $body .= '<td><a class="btn secondary" href="' . h(withQuery('/boxes/' . (int)$palletBox['id'], $palletNavParams)) . '">Ver caja</a></td>';
        $body .= '</tr>';
    }
    if ($palletBoxes === []) {
        $body .= '<tr><td colspan="5" class="muted">Este pallet aún no tiene cajas asociadas.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    render('Pallet', $body);
    exit;
}

if ($path === '/maquila' && $method === 'GET') {
    $statusFilter = strtoupper(trim((string)($_GET['status'] ?? 'OPEN')));
    if (!in_array($statusFilter, ['ALL', 'OPEN', 'PARTIAL', 'RETURNED'], true)) {
        $statusFilter = 'OPEN';
    }
    $selectedPalletId = isset($_GET['pallet_id']) ? (int)$_GET['pallet_id'] : 0;
    $maquilaMessage = trim((string)($_GET['msg'] ?? ''));
    $maquilaError = trim((string)($_GET['error'] ?? ''));
    $availablePallets = $service->listPalletsAvailableForMaquila(250);
    $maquilaOrders = $service->listMaquilaOrders($statusFilter === 'ALL' ? null : $statusFilter);

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Maquila y talleres externos</div>
          <div class="muted">Salida a taller externo, retorno parcial o total y conciliación de merma por pallet/OT.</div>
        </div>
        <a class="btn secondary" href="/stock">Volver a inventario</a>
      </div>';
    if ($maquilaMessage !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($maquilaMessage) . '</div>';
    }
    if ($maquilaError !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($maquilaError) . '</div>';
    }

    $body .= '<div class="card" style="margin-bottom:12px">
      <div style="font-weight:800;margin-bottom:8px">Nueva salida a maquila</div>
      <form method="post" action="/maquila">
        <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
        <div class="row">
          <div style="flex:2;min-width:280px">
            <label>Pallet / lote</label>
            <select name="pallet_id" required>
              <option value="">Seleccionar</option>';
    foreach ($availablePallets as $availablePallet) {
        $selected = (int)$availablePallet['id'] === $selectedPalletId ? ' selected' : '';
        $warehouseLabel = trim((string)($availablePallet['warehouse_code'] ?? ''));
        if ($warehouseLabel !== '' && (string)($availablePallet['warehouse_name'] ?? '') !== '') {
            $warehouseLabel .= ' - ' . (string)$availablePallet['warehouse_name'];
        }
        $body .= '<option value="' . (int)$availablePallet['id'] . '"' . $selected . '>' . h((string)$availablePallet['pallet_code']) . ' | OT ' . h((string)($availablePallet['ot_code'] ?? '-')) . ' | ' . h((string)$warehouseLabel) . '</option>';
    }
    $body .= '</select>
          </div>
          <div style="flex:1;min-width:220px">
            <label>Taller externo</label>
            <input type="text" name="workshop_name" value="' . h((string)($_GET['workshop_name'] ?? '')) . '" placeholder="Ej: Taller Externo Norte" required>
          </div>
          <div style="flex:1;min-width:180px">
            <label>Peso salida (Kg)</label>
            <input type="number" step="0.001" min="0.001" name="outgoing_weight_kg" value="' . h((string)($_GET['outgoing_weight_kg'] ?? '')) . '" required>
          </div>
        </div>
        <div style="margin-top:10px">
          <label>Observación</label>
          <input type="text" name="notes" value="' . h((string)($_GET['notes'] ?? '')) . '" placeholder="Detalle de envío, guía o comentario">
        </div>
        <div style="margin-top:12px">
          <button class="btn" type="submit">Registrar salida</button>
        </div>
      </form>
    </div>';

    if ($availablePallets === []) {
        $body .= '<div class="card" style="margin-bottom:12px"><div class="muted">No hay pallets internos disponibles para enviar a maquila.</div></div>';
    }

    $body .= '<div class="card">
      <div class="row" style="justify-content:space-between;align-items:end;margin-bottom:8px">
        <div style="font-weight:800">Órdenes de maquila</div>
        <form method="get" action="/maquila">
          <div class="row nowrap">
            <div style="min-width:200px">
              <label>Estado</label>
              <select name="status">
                <option value="OPEN"' . ($statusFilter === 'OPEN' ? ' selected' : '') . '>En maquila</option>
                <option value="PARTIAL"' . ($statusFilter === 'PARTIAL' ? ' selected' : '') . '>Retorno parcial</option>
                <option value="RETURNED"' . ($statusFilter === 'RETURNED' ? ' selected' : '') . '>Retornadas</option>
                <option value="ALL"' . ($statusFilter === 'ALL' ? ' selected' : '') . '>Todas</option>
              </select>
            </div>
            <div style="min-width:160px;align-self:end">
              <button class="btn secondary" type="submit">Ver</button>
            </div>
          </div>
        </form>
      </div>
      <div class="table-wrap"><table class="trace-table"><thead><tr><th>Orden</th><th>Pallet</th><th>OT</th><th>Taller</th><th>Salida</th><th>Retorno</th><th>Merma</th><th>Pendiente</th><th>Estado</th><th></th></tr></thead><tbody>';
    foreach ($maquilaOrders as $maquilaOrder) {
        $pendingWeightKg = max(0, round((float)($maquilaOrder['outgoing_weight_kg'] ?? 0) - (float)($maquilaOrder['returned_weight_kg'] ?? 0) - (float)($maquilaOrder['waste_weight_kg'] ?? 0), 3));
        $body .= '<tr>';
        $body .= '<td>#' . (int)$maquilaOrder['id'] . '</td>';
        $body .= '<td>' . h((string)$maquilaOrder['pallet_code']) . '</td>';
        $body .= '<td>' . h((string)($maquilaOrder['ot_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)$maquilaOrder['workshop_name']) . '</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($maquilaOrder['outgoing_weight_kg'] ?? 0), 'Kg')) . ' Kg</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($maquilaOrder['returned_weight_kg'] ?? 0), 'Kg')) . ' Kg</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($maquilaOrder['waste_weight_kg'] ?? 0), 'Kg')) . ' Kg</td>';
        $body .= '<td>' . h(formatReceptionValue($pendingWeightKg, 'Kg')) . ' Kg</td>';
        $body .= '<td>' . h(maquilaStatusLabel((string)$maquilaOrder['status'])) . '</td>';
        $body .= '<td><a class="btn secondary" href="/maquila/' . (int)$maquilaOrder['id'] . '">Ver</a></td>';
        $body .= '</tr>';
    }
    if ($maquilaOrders === []) {
        $body .= '<tr><td colspan="10" class="muted">No hay órdenes de maquila para el filtro seleccionado.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    render('Maquila', $body);
    exit;
}

if ($path === '/maquila' && $method === 'POST') {
    requireCsrf();
    $palletId = (int)($_POST['pallet_id'] ?? 0);
    $workshopName = trim((string)($_POST['workshop_name'] ?? ''));
    $outgoingWeightKg = (float)($_POST['outgoing_weight_kg'] ?? 0);
    $notes = trim((string)($_POST['notes'] ?? ''));
    $result = $service->createMaquilaOrder($palletId, $workshopName, $outgoingWeightKg, $notes, $currentOperatorName);
    if (($result['ok'] ?? false) === true) {
        header('Location: /maquila/' . (int)$result['maquila_order_id'] . '?msg=' . urlencode('Salida a maquila registrada correctamente.'));
        exit;
    }

    $query = http_build_query([
        'error' => (string)reset($result['errors']),
        'pallet_id' => $palletId > 0 ? (string)$palletId : '',
        'workshop_name' => $workshopName,
        'outgoing_weight_kg' => $outgoingWeightKg > 0 ? (string)$outgoingWeightKg : '',
        'notes' => $notes,
    ]);
    header('Location: /maquila' . ($query !== '' ? '?' . $query : ''));
    exit;
}

if (preg_match('#^/maquila/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $maquilaOrderId = (int)$m[1];
    $maquilaOrder = $service->getMaquilaOrder($maquilaOrderId);
    if ($maquilaOrder === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">La orden de maquila no existe.</div>');
        exit;
    }

    $maquilaReturns = $service->listMaquilaReturns($maquilaOrderId);
    $maquilaMessage = trim((string)($_GET['msg'] ?? ''));
    $maquilaError = trim((string)($_GET['error'] ?? ''));
    $pendingWeightKg = max(0, round((float)($maquilaOrder['outgoing_weight_kg'] ?? 0) - (float)($maquilaOrder['returned_weight_kg'] ?? 0) - (float)($maquilaOrder['waste_weight_kg'] ?? 0), 3));

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Orden de maquila #' . (int)$maquilaOrder['id'] . '</div>
          <div class="muted">Seguimiento de salida, retornos y diferencia por taller externo.</div>
        </div>
        <div class="row"><a class="btn secondary" href="/maquila">Volver</a><a class="btn secondary" href="/pallets/' . (int)$maquilaOrder['pallet_id'] . '">Ver pallet</a></div>
      </div>';
    if ($maquilaMessage !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($maquilaMessage) . '</div>';
    }
    if ($maquilaError !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($maquilaError) . '</div>';
    }

    $body .= '<div class="card" style="margin-bottom:12px"><div class="row">
      <div style="flex:1;min-width:180px"><div class="muted">Pallet</div><div style="font-weight:800">' . h((string)$maquilaOrder['pallet_code']) . '</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">OT</div><div style="font-weight:800">' . h((string)($maquilaOrder['ot_code'] ?? '-')) . '</div></div>
      <div style="flex:1;min-width:220px"><div class="muted">Taller</div><div style="font-weight:800">' . h((string)$maquilaOrder['workshop_name']) . '</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Estado</div><div style="font-weight:800">' . h(maquilaStatusLabel((string)$maquilaOrder['status'])) . '</div></div>
    </div>
    <div class="row" style="margin-top:10px">
      <div style="flex:1;min-width:180px"><div class="muted">Salida</div><div style="font-weight:800">' . h(formatReceptionValue((float)($maquilaOrder['outgoing_weight_kg'] ?? 0), 'Kg')) . ' Kg</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Retorno acumulado</div><div style="font-weight:800">' . h(formatReceptionValue((float)($maquilaOrder['returned_weight_kg'] ?? 0), 'Kg')) . ' Kg</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Merma acumulada</div><div style="font-weight:800">' . h(formatReceptionValue((float)($maquilaOrder['waste_weight_kg'] ?? 0), 'Kg')) . ' Kg</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Pendiente</div><div style="font-weight:800">' . h(formatReceptionValue($pendingWeightKg, 'Kg')) . ' Kg</div></div>
    </div>
    <div class="row" style="margin-top:10px">
      <div style="flex:1;min-width:180px"><div class="muted">Bodega salida</div><div style="font-weight:800">' . h((string)$maquilaOrder['outgoing_warehouse_code']) . ' - ' . h((string)$maquilaOrder['outgoing_warehouse_name']) . '</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Bodega externa</div><div style="font-weight:800">' . h((string)$maquilaOrder['external_warehouse_code']) . ' - ' . h((string)$maquilaOrder['external_warehouse_name']) . '</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Bodega retorno</div><div style="font-weight:800">' . h((string)$maquilaOrder['return_warehouse_code']) . ' - ' . h((string)$maquilaOrder['return_warehouse_name']) . '</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Operador salida</div><div style="font-weight:800">' . h((string)$maquilaOrder['operator_name']) . '</div></div>
    </div></div>';

    if (in_array((string)$maquilaOrder['status'], ['OPEN', 'PARTIAL'], true)) {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Registrar retorno</div>
          <form method="post" action="/maquila/' . $maquilaOrderId . '/return">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <div class="row">
              <div style="flex:1;min-width:180px">
                <label>Peso retorno (Kg)</label>
                <input type="number" step="0.001" min="0" max="' . h((string)$pendingWeightKg) . '" name="return_weight_kg" value="' . h((string)$pendingWeightKg) . '">
              </div>
              <div style="flex:1;min-width:180px">
                <label>Cajas retornadas</label>
                <input type="number" step="1" min="0" name="returned_box_count" value="0">
              </div>
              <div style="flex:1;min-width:180px">
                <label>Unidades retornadas</label>
                <input type="number" step="0.001" min="0" name="returned_units_qty" value="0">
              </div>
              <div style="flex:1;min-width:180px">
                <label>Merma (Kg)</label>
                <input type="number" step="0.001" min="0" name="waste_weight_kg" value="0">
              </div>
            </div>
            <div style="margin-top:10px">
              <label>Observación retorno</label>
              <input type="text" name="notes" value="" placeholder="Detalle de retorno parcial, total o diferencia detectada">
            </div>
            <div style="margin-top:12px">
              <button class="btn" type="submit">Registrar retorno</button>
            </div>
          </form>
        </div>';
    }

    $body .= '<div class="card">
      <div style="font-weight:800;margin-bottom:8px">Historial de retornos</div>
      <div class="table-wrap"><table class="trace-table"><thead><tr><th>Fecha</th><th>Peso retorno</th><th>Cajas</th><th>Unidades</th><th>Merma</th><th>Operador</th><th>Observación</th></tr></thead><tbody>';
    foreach ($maquilaReturns as $maquilaReturn) {
        $body .= '<tr>';
        $body .= '<td>' . h((string)$maquilaReturn['created_at']) . '</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($maquilaReturn['return_weight_kg'] ?? 0), 'Kg')) . ' Kg</td>';
        $body .= '<td>' . h((string)($maquilaReturn['returned_box_count'] ?? '0')) . '</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($maquilaReturn['returned_units_qty'] ?? 0), 'Unid.')) . '</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($maquilaReturn['waste_weight_kg'] ?? 0), 'Kg')) . ' Kg</td>';
        $body .= '<td>' . h((string)$maquilaReturn['operator_name']) . '</td>';
        $body .= '<td>' . h((string)($maquilaReturn['notes'] ?? '-')) . '</td>';
        $body .= '</tr>';
    }
    if ($maquilaReturns === []) {
        $body .= '<tr><td colspan="7" class="muted">Todavía no se registran retornos para esta orden.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    render('Maquila', $body);
    exit;
}

if (preg_match('#^/maquila/(\d+)/return$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $maquilaOrderId = (int)$m[1];
    $result = $service->registerMaquilaReturn(
        $maquilaOrderId,
        (float)($_POST['return_weight_kg'] ?? 0),
        (int)($_POST['returned_box_count'] ?? 0),
        (float)($_POST['returned_units_qty'] ?? 0),
        (float)($_POST['waste_weight_kg'] ?? 0),
        (string)($_POST['notes'] ?? ''),
        $currentOperatorName
    );
    $query = (($result['ok'] ?? false) === true)
        ? '?msg=' . urlencode('Retorno de maquila registrado correctamente.')
        : '?error=' . urlencode((string)reset($result['errors']));
    header('Location: /maquila/' . $maquilaOrderId . $query);
    exit;
}

if ($path === '/cliches' && $method === 'GET') {
    $search = trim((string)($_GET['search'] ?? ''));
    $location = trim((string)($_GET['location'] ?? ''));
    $status = strtoupper(trim((string)($_GET['status'] ?? 'ALL')));
    if (!in_array($status, ['ALL', 'AVAILABLE', 'IN_USE', 'MAINTENANCE', 'INACTIVE'], true)) {
        $status = 'ALL';
    }
    $selectedWorkOrderId = isset($_GET['work_order_id']) ? (int)$_GET['work_order_id'] : 0;
    $selectedWorkOrder = $selectedWorkOrderId > 0 ? $service->getWorkOrder($selectedWorkOrderId) : null;
    $cliches = $service->listCliches($search !== '' ? $search : null, $location !== '' ? $location : null, $status !== 'ALL' ? $status : null);
    $message = trim((string)($_GET['msg'] ?? ''));
    $error = trim((string)($_GET['error'] ?? ''));

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Clisés</div>
          <div class="muted">Inventario digital, retiro a OT y devolución con trazabilidad histórica.</div>
        </div>
        <a class="btn secondary" href="/stock">Volver a inventario</a>
      </div>';
    if ($message !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($message) . '</div>';
    }
    if ($error !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($error) . '</div>';
    }
    if ($selectedWorkOrder !== null) {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:6px">Contexto OT</div>
          <div class="row">
            <div style="flex:1;min-width:220px"><div class="muted">OT</div><div style="font-weight:800"><a href="/work-orders/' . (int)$selectedWorkOrder['id'] . '/start">' . h((string)$selectedWorkOrder['ot_code']) . '</a></div></div>
            <div style="flex:1;min-width:220px"><div class="muted">SKU final</div><div style="font-weight:800">' . h((string)($selectedWorkOrder['sku_final'] ?? '-')) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">Estado</div><div style="font-weight:800">' . h(workOrderStatusLabel((string)($selectedWorkOrder['status'] ?? ''))) . '</div></div>
          </div>
        </div>';
    }

    $body .= '<div class="card" style="margin-bottom:12px">
      <div class="row" style="justify-content:space-between;align-items:end;margin-bottom:8px">
        <div style="font-weight:800">Buscar clisés</div>
        <form method="get" action="/cliches">
          <input type="hidden" name="work_order_id" value="' . ($selectedWorkOrderId > 0 ? (int)$selectedWorkOrderId : '') . '">
          <div class="row nowrap">
            <div style="min-width:220px"><label>Código o descripción</label><input type="text" name="search" value="' . h($search) . '"></div>
            <div style="min-width:180px"><label>Ubicación</label><input type="text" name="location" value="' . h($location) . '"></div>
            <div style="min-width:180px"><label>Estado</label><select name="status">
              <option value="ALL"' . ($status === 'ALL' ? ' selected' : '') . '>Todos</option>
              <option value="AVAILABLE"' . ($status === 'AVAILABLE' ? ' selected' : '') . '>Disponibles</option>
              <option value="IN_USE"' . ($status === 'IN_USE' ? ' selected' : '') . '>En uso</option>
              <option value="MAINTENANCE"' . ($status === 'MAINTENANCE' ? ' selected' : '') . '>Mantención</option>
              <option value="INACTIVE"' . ($status === 'INACTIVE' ? ' selected' : '') . '>Inactivos</option>
            </select></div>
            <div style="min-width:120px;align-self:end"><button class="btn secondary" type="submit">Buscar</button></div>
          </div>
        </form>
      </div>
    </div>';

    $body .= '<div class="card" style="margin-bottom:12px">
      <div style="font-weight:800;margin-bottom:8px">Alta de clisé</div>
      <form method="post" action="/cliches">
        <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
        <input type="hidden" name="work_order_id" value="' . ($selectedWorkOrderId > 0 ? (int)$selectedWorkOrderId : '') . '">
        <div class="row">
          <div style="flex:1;min-width:200px"><label>Código</label><input type="text" name="code" required></div>
          <div style="flex:2;min-width:260px"><label>Descripción</label><input type="text" name="description" required></div>
        </div>
        <div class="row" style="margin-top:10px">
          <div style="flex:1;min-width:200px"><label>Ubicación física</label><input type="text" name="location_code" placeholder="Ej: RACK-C1" required></div>
          <div style="flex:2;min-width:260px"><label>Detalle ubicación</label><input type="text" name="location_detail" placeholder="Ej: Estante superior, caja azul"></div>
        </div>
        <div style="margin-top:10px"><label>Observación</label><input type="text" name="notes" placeholder="Comentario inicial del activo"></div>
        <div style="margin-top:12px"><button class="btn" type="submit">Guardar clisé</button></div>
      </form>
    </div>';

    $body .= '<div class="card">
      <div style="font-weight:800;margin-bottom:8px">Inventario de clisés</div>
      <div class="table-wrap"><table class="trace-table"><thead><tr><th>Código</th><th>Descripción</th><th>Ubicación</th><th>Estado</th><th>OT</th><th></th></tr></thead><tbody>';
    foreach ($cliches as $cliche) {
        $detailUrl = '/cliches/' . (int)$cliche['id'] . ($selectedWorkOrderId > 0 ? '?work_order_id=' . $selectedWorkOrderId : '');
        $locationLabel = trim((string)($cliche['location_code'] ?? ''));
        $locationDetail = trim((string)($cliche['location_detail'] ?? ''));
        if ($locationDetail !== '') {
            $locationLabel .= ' · ' . $locationDetail;
        }
        $body .= '<tr>';
        $body .= '<td>' . h((string)$cliche['code']) . '</td>';
        $body .= '<td>' . h((string)$cliche['description']) . '</td>';
        $body .= '<td>' . h($locationLabel !== '' ? $locationLabel : '-') . '</td>';
        $body .= '<td>' . h(clicheStatusLabel((string)$cliche['status'])) . '</td>';
        $body .= '<td>' . h((string)($cliche['ot_code'] ?? '-')) . '</td>';
        $body .= '<td><a class="btn secondary" href="' . h($detailUrl) . '">Ver</a></td>';
        $body .= '</tr>';
    }
    if ($cliches === []) {
        $body .= '<tr><td colspan="6" class="muted">No hay clisés para los filtros seleccionados.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    render('Clisés', $body);
    exit;
}

if ($path === '/cliches' && $method === 'POST') {
    requireCsrf();
    $selectedWorkOrderId = (int)($_POST['work_order_id'] ?? 0);
    $result = $service->createCliche(
        (string)($_POST['code'] ?? ''),
        (string)($_POST['description'] ?? ''),
        (string)($_POST['location_code'] ?? ''),
        (string)($_POST['location_detail'] ?? ''),
        (string)($_POST['notes'] ?? ''),
        $currentOperatorName
    );
    if (($result['ok'] ?? false) === true) {
        $redirect = '/cliches/' . (int)$result['cliche_id'] . '?msg=' . urlencode('Clisé registrado correctamente.');
        if ($selectedWorkOrderId > 0) {
            $redirect .= '&work_order_id=' . $selectedWorkOrderId;
        }
        header('Location: ' . $redirect);
        exit;
    }
    $query = http_build_query([
        'error' => (string)reset($result['errors']),
        'work_order_id' => $selectedWorkOrderId > 0 ? (string)$selectedWorkOrderId : '',
    ]);
    header('Location: /cliches' . ($query !== '' ? '?' . $query : ''));
    exit;
}

if (preg_match('#^/cliches/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $clicheId = (int)$m[1];
    $cliche = $service->getCliche($clicheId);
    if ($cliche === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">El clisé no existe.</div>');
        exit;
    }
    $selectedWorkOrderId = isset($_GET['work_order_id']) ? (int)$_GET['work_order_id'] : 0;
    $message = trim((string)($_GET['msg'] ?? ''));
    $error = trim((string)($_GET['error'] ?? ''));
    $workOrders = $service->listWorkOrdersForClicheAssignment();
    $usageLogs = $service->listClicheUsageLogsByCliche($clicheId);
    $currentWorkOrder = (int)($cliche['current_work_order_id'] ?? 0) > 0 ? $service->getWorkOrder((int)$cliche['current_work_order_id']) : null;

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Clisé ' . h((string)$cliche['code']) . '</div>
          <div class="muted">Control de ubicación, retiro a OT y devolución.</div>
        </div>
        <div class="row"><a class="btn secondary" href="/cliches' . ($selectedWorkOrderId > 0 ? '?work_order_id=' . $selectedWorkOrderId : '') . '">Volver</a></div>
      </div>';
    if ($message !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($message) . '</div>';
    }
    if ($error !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($error) . '</div>';
    }

    $locationLabel = trim((string)($cliche['location_code'] ?? ''));
    $locationDetail = trim((string)($cliche['location_detail'] ?? ''));
    if ($locationDetail !== '') {
        $locationLabel .= ' · ' . $locationDetail;
    }
    $body .= '<div class="card" style="margin-bottom:12px"><div class="row">
      <div style="flex:1;min-width:180px"><div class="muted">Código</div><div style="font-weight:800">' . h((string)$cliche['code']) . '</div></div>
      <div style="flex:2;min-width:240px"><div class="muted">Descripción</div><div style="font-weight:800">' . h((string)$cliche['description']) . '</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Estado</div><div style="font-weight:800">' . h(clicheStatusLabel((string)$cliche['status'])) . '</div></div>
      <div style="flex:1;min-width:220px"><div class="muted">Ubicación actual</div><div style="font-weight:800">' . h($locationLabel !== '' ? $locationLabel : '-') . '</div></div>
    </div>
    <div class="row" style="margin-top:10px">
      <div style="flex:1;min-width:180px"><div class="muted">OT actual</div><div style="font-weight:800">' . ((int)($cliche['current_work_order_id'] ?? 0) > 0 ? '<a href="/work-orders/' . (int)$cliche['current_work_order_id'] . '/start">' . h((string)($cliche['ot_code'] ?? ('OT #' . (int)$cliche['current_work_order_id']))) . '</a>' : '-') . '</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Asignado por</div><div style="font-weight:800">' . h((string)($cliche['current_operator_name'] ?? '-')) . '</div></div>
      <div style="flex:1;min-width:220px"><div class="muted">Fecha uso actual</div><div style="font-weight:800">' . h((string)($cliche['current_assigned_at'] ?? '-')) . '</div></div>
      <div style="flex:2;min-width:240px"><div class="muted">Observación</div><div style="font-weight:800">' . h((string)($cliche['notes'] ?? '-')) . '</div></div>
    </div></div>';

    if (strtoupper(trim((string)($cliche['status'] ?? ''))) === 'AVAILABLE') {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Retirar a OT</div>
          <form method="post" action="/cliches/' . $clicheId . '/assign">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <input type="hidden" name="return_work_order_id" value="' . ($selectedWorkOrderId > 0 ? (int)$selectedWorkOrderId : '') . '">
            <div class="row">
              <div style="flex:2;min-width:260px">
                <label>OT destino</label>
                <select name="work_order_id" required>
                  <option value="">Seleccionar</option>';
        foreach ($workOrders as $workOrder) {
            $selected = (int)$workOrder['id'] === $selectedWorkOrderId ? ' selected' : '';
            $body .= '<option value="' . (int)$workOrder['id'] . '"' . $selected . '>' . h((string)$workOrder['ot_code']) . ' - ' . h((string)$workOrder['sku_final']) . ' [' . h(workOrderStatusLabel((string)$workOrder['status'])) . ']</option>';
        }
        $body .= '</select>
              </div>
              <div style="flex:2;min-width:260px">
                <label>Observación</label>
                <input type="text" name="notes" value="" placeholder="Ej: retiro para impresión línea 2">
              </div>
            </div>
            <div style="margin-top:12px"><button class="btn" type="submit">Asignar a OT</button></div>
          </form>
        </div>';
    } elseif (strtoupper(trim((string)($cliche['status'] ?? ''))) === 'IN_USE') {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Devolver clisé</div>
          <form method="post" action="/cliches/' . $clicheId . '/return">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <input type="hidden" name="return_work_order_id" value="' . ($selectedWorkOrderId > 0 ? (int)$selectedWorkOrderId : ((int)($cliche['current_work_order_id'] ?? 0) > 0 ? (int)$cliche['current_work_order_id'] : '')) . '">
            <div class="row">
              <div style="flex:1;min-width:220px"><label>Ubicación retorno</label><input type="text" name="location_code" value="" placeholder="Ej: RACK-C1" required></div>
              <div style="flex:2;min-width:260px"><label>Detalle ubicación</label><input type="text" name="location_detail" value="" placeholder="Ej: Estante superior"></div>
            </div>
            <div style="margin-top:10px"><label>Observación</label><input type="text" name="notes" value="" placeholder="Ej: devuelto después de cierre de OT"></div>
            <div style="margin-top:12px"><button class="btn" type="submit">Registrar devolución</button></div>
          </form>
        </div>';
    }

    if ($currentWorkOrder !== null) {
        $body .= '<div class="card" style="margin-bottom:12px;background:#f8fafc">
          <div style="font-weight:800;margin-bottom:6px">Uso actual</div>
          <div class="muted">Este clisé está asignado a la OT <a href="/work-orders/' . (int)$currentWorkOrder['id'] . '/start">' . h((string)$currentWorkOrder['ot_code']) . '</a> y no puede asignarse a otra OT hasta registrar su devolución.</div>
        </div>';
    }

    $body .= '<div class="card">
      <div style="font-weight:800;margin-bottom:8px">Historial de uso</div>
      <div class="table-wrap"><table class="trace-table"><thead><tr><th>Fecha</th><th>Acción</th><th>OT</th><th>Origen</th><th>Destino</th><th>Operador</th><th>Observación</th></tr></thead><tbody>';
    foreach ($usageLogs as $usageLog) {
        $body .= '<tr>';
        $body .= '<td>' . h((string)$usageLog['created_at']) . '</td>';
        $body .= '<td>' . h(eventTypeLabel('CLICHE_' . (string)$usageLog['action_type'])) . '</td>';
        $body .= '<td>' . h((string)($usageLog['ot_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($usageLog['from_location_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($usageLog['to_location_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)$usageLog['operator_name']) . '</td>';
        $body .= '<td>' . h((string)($usageLog['notes'] ?? '-')) . '</td>';
        $body .= '</tr>';
    }
    if ($usageLogs === []) {
        $body .= '<tr><td colspan="7" class="muted">Todavía no hay historial para este clisé.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    render('Clisés', $body);
    exit;
}

if (preg_match('#^/cliches/(\d+)/assign$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $clicheId = (int)$m[1];
    $returnWorkOrderId = (int)($_POST['return_work_order_id'] ?? 0);
    $result = $service->assignClicheToWorkOrder(
        $clicheId,
        (int)($_POST['work_order_id'] ?? 0),
        (string)($_POST['notes'] ?? ''),
        $currentOperatorName
    );
    $query = (($result['ok'] ?? false) === true)
        ? '?msg=' . urlencode('Clisé asignado correctamente.')
        : '?error=' . urlencode((string)reset($result['errors']));
    if ($returnWorkOrderId > 0) {
        $query .= '&work_order_id=' . $returnWorkOrderId;
    }
    header('Location: /cliches/' . $clicheId . $query);
    exit;
}

if (preg_match('#^/cliches/(\d+)/return$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $clicheId = (int)$m[1];
    $returnWorkOrderId = (int)($_POST['return_work_order_id'] ?? 0);
    $result = $service->returnCliche(
        $clicheId,
        (string)($_POST['location_code'] ?? ''),
        (string)($_POST['location_detail'] ?? ''),
        (string)($_POST['notes'] ?? ''),
        $currentOperatorName
    );
    $query = (($result['ok'] ?? false) === true)
        ? '?msg=' . urlencode('Clisé devuelto correctamente.')
        : '?error=' . urlencode((string)reset($result['errors']));
    if ($returnWorkOrderId > 0) {
        $query .= '&work_order_id=' . $returnWorkOrderId;
    }
    header('Location: /cliches/' . $clicheId . $query);
    exit;
}

if (preg_match('#^/rolls/(\d+)/label$#', $path, $m) === 1 && $method === 'GET') {
    $id = (int)$m[1];
    $roll = $service->getRoll($id);
    if ($roll === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la bobina.</div>');
        exit;
    }

    $autoPrint = isset($_GET['auto_print']) && (string)$_GET['auto_print'] === '1';

    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Etiqueta</title>';
    echo '<style>
      *{box-sizing:border-box}
      body{font-family:Arial,sans-serif;margin:0;background:#fff;color:#111}
      .wrap{padding:10px}
      .label{border:2px solid #111;padding:12px;max-width:620px}
      .head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
      .brand{font-weight:900;letter-spacing:1px;font-size:24px;line-height:1}
      .title{margin-top:2px;font-size:13px;font-weight:700;letter-spacing:.5px}
      .sku{margin-top:10px;border:2px solid #111;padding:8px 10px}
      .sku .k{font-size:11px;color:#444;text-transform:uppercase}
      .sku .v{font-size:22px;font-weight:900;line-height:1.1}
      .desc{margin-top:8px;border:1px solid #111;padding:8px 10px}
      .grid{display:grid;grid-template-columns:1fr 1fr;gap:0;margin-top:8px;border-top:1px solid #111;border-left:1px solid #111}
      .cell{border-right:1px solid #111;border-bottom:1px solid #111;padding:7px 8px;min-height:56px}
      .k{font-size:11px;color:#444;text-transform:uppercase}
      .v{font-size:16px;font-weight:800;line-height:1.15;margin-top:2px;word-break:break-word}
      .v-sm{font-size:14px}
      .bottom{margin-top:10px;border:2px solid #111;padding:10px 8px;display:flex;flex-direction:column;align-items:center;justify-content:center}
      .barcode{margin:6px 0 4px}
      .idtxt{font-size:18px;font-weight:900;letter-spacing:.5px}
      @media print{
        body{margin:0}
        .wrap{padding:0}
        .label{border:none}
      }
    </style></head><body><div class="wrap"><div class="label">';

    $barcodeValue = (string)(int)$roll['id'];
    $barcodeSvg = code39Svg($barcodeValue, 70, 2, 5);
    $arrivalDate = formatLabelDate((string)($roll['arrival_date'] ?? $roll['created_at'] ?? ''));
    $containerCode = trim((string)($roll['container_code'] ?? ''));
    $grams = trim((string)($roll['grams'] ?? ''));
    $meters = trim((string)($roll['meters'] ?? ''));

    echo '<div class="head"><div><div class="brand">UNIBAG</div><div class="title">ETIQUETA RECEPCION</div></div></div>';
    echo '<div class="sku"><div class="k">SKU</div><div class="v">' . h((string)($roll['sku_code'] ?? '-')) . '</div></div>';
    echo '<div class="desc"><div class="k">Descripcion</div><div class="v v-sm">' . h((string)($roll['sku_description'] ?? '-')) . '</div></div>';
    echo '<div class="grid">';
    echo '<div class="cell"><div class="k">Fecha de arribo</div><div class="v">' . h($arrivalDate) . '</div></div>';
    echo '<div class="cell"><div class="k">Serial contenedor</div><div class="v">' . h($containerCode !== '' ? $containerCode : '-') . '</div></div>';
    echo '<div class="cell"><div class="k">Unidad de medida</div><div class="v">Gramos / Metro</div></div>';
    echo '<div class="cell"><div class="k">Codigo bulto</div><div class="v">' . h($barcodeValue) . '</div></div>';
    echo '<div class="cell"><div class="k">Gramos</div><div class="v">' . h($grams !== '' ? $grams : '-') . '</div></div>';
    echo '<div class="cell"><div class="k">Metro</div><div class="v">' . h($meters !== '' ? $meters : '-') . '</div></div>';
    echo '</div>';

    echo '<div class="bottom">';
    echo '<div class="k" style="color:#111;font-weight:700">Codigo bulto</div>';
    if ($barcodeSvg !== '') {
        echo '<div class="barcode">' . $barcodeSvg . '</div>';
    }
    echo '<div class="idtxt">' . h($barcodeValue) . '</div>';
    echo '</div>';

    echo '</div></div>';
    if ($autoPrint) {
        echo '<script>
          (function(){
            function doPrint(){
              try { window.focus(); } catch(e) {}
              try { window.print(); } catch(e) {}
            }
            function schedule(){
              try { setTimeout(doPrint, 800); } catch(e) {}
            }
            if (document.readyState === "complete") schedule();
            else window.addEventListener("load", schedule);
          })();
        </script>';
    }
    echo '</body></html>';
    exit;
}

if (preg_match('#^/boxes/(\d+)/label$#', $path, $m) === 1 && $method === 'GET') {
    $id = (int)$m[1];
    $box = $service->getBox($id);
    if ($box === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la caja.</div>');
        exit;
    }

    $autoPrint = isset($_GET['auto_print']) && (string)$_GET['auto_print'] === '1';
    $serverPrint = isset($_GET['server_print']) && (string)$_GET['server_print'] === '1';
    $serverPrintResult = null;
    if ($serverPrint && $printer->isEnabled()) {
        $serverPrintResult = $printer->printBoxLabel($box);
    }

    $barcodeValue = strtoupper(trim((string)$box['box_code']));
    $barcodeSvg = code39Svg($barcodeValue, 70, 2, 5);
    $destinationLabel = (string)$box['destination_mode'] === 'CUSTOMER_ORDER'
        ? 'OC cliente: ' . (string)($box['customer_order_ref'] ?? '-')
        : 'Bodega: ' . (string)($box['warehouse_code'] ?? '-');

    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Etiqueta caja</title>';
    echo '<style>
      *{box-sizing:border-box}
      body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:0;background:#fff;color:#111}
      .wrap{padding:10px}
      .label{border:2px solid #111;border-radius:10px;padding:14px;max-width:520px}
      .brand{font-weight:900;letter-spacing:1px;font-size:18px}
      .kind{margin-top:4px;font-size:13px;font-weight:800}
      .product{margin-top:8px;font-size:22px;font-weight:900;line-height:1.15}
      .divider{height:2px;background:#111;margin:10px 0}
      .rows{display:grid;grid-template-columns:120px 1fr;gap:6px 10px}
      .k{font-size:12px;color:#374151}
      .v{font-size:14px;font-weight:800}
      .bottom{margin-top:12px;display:flex;flex-direction:column;align-items:center;justify-content:center}
      .barcode{margin:6px 0 4px}
      .idtxt{font-size:16px;font-weight:900;letter-spacing:.5px}
      .ok{background:#ecfeff;border:1px solid #a5f3fc;color:#155e75;padding:10px;border-radius:10px;margin-bottom:10px}
      .err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px;border-radius:10px;margin-bottom:10px}
      @media print{
        body{margin:0}
        .wrap{padding:0}
        .label{border:none}
      }
    </style></head><body><div class="wrap">';
    if (is_array($serverPrintResult)) {
        if (($serverPrintResult['ok'] ?? false) === true) {
            echo '<div class="ok">Etiqueta enviada a impresora Zebra.</div>';
        } else {
            echo '<div class="err">No se pudo imprimir: ' . h((string)($serverPrintResult['error'] ?? 'Error desconocido.')) . '</div>';
        }
    }
    echo '<div class="label">';
    echo '<div class="brand">UNIBAG</div>';
    echo '<div class="kind">CAJA TRAZABLE</div>';
    echo '<div class="product">' . h((string)$box['final_sku']) . '</div>';
    echo '<div class="divider"></div>';
    echo '<div class="rows">';
    echo '<div class="k">Caja</div><div class="v">' . h((string)$box['box_code']) . '</div>';
    echo '<div class="k">OT</div><div class="v">' . h((string)($box['ot_code'] ?? '-')) . '</div>';
    echo '<div class="k">Bobina origen</div><div class="v">' . h((string)$box['source_roll_code']) . '</div>';
    echo '<div class="k">Unidades</div><div class="v">' . h((string)$box['units_qty']) . '</div>';
    echo '<div class="k">Pallet</div><div class="v">' . h((string)($box['pallet_code'] ?? '-')) . '</div>';
    echo '<div class="k">Operador</div><div class="v">' . h((string)$box['operator_name']) . '</div>';
    echo '<div class="k">Destino</div><div class="v">' . h($destinationLabel) . '</div>';
    echo '</div>';
    echo '<div class="bottom">';
    if ($barcodeSvg !== '') {
        echo '<div class="barcode">' . $barcodeSvg . '</div>';
    }
    echo '<div class="idtxt">' . h($barcodeValue) . '</div>';
    echo '</div></div></div>';
    if ($autoPrint) {
        echo '<script>
          (function(){
            function doPrint(){
              try { window.focus(); } catch(e) {}
              try { window.print(); } catch(e) {}
            }
            function schedule(){
              try { setTimeout(doPrint, 800); } catch(e) {}
            }
            if (document.readyState === "complete") schedule();
            else window.addEventListener("load", schedule);
          })();
        </script>';
    }
    echo '</body></html>';
    exit;
}

if (preg_match('#^/pallets/(\d+)/label$#', $path, $m) === 1 && $method === 'GET') {
    $id = (int)$m[1];
    $pallet = $service->getPallet($id);
    if ($pallet === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe el pallet.</div>');
        exit;
    }

    $palletBoxes = $service->listBoxesByPallet($id);
    $autoPrint = isset($_GET['auto_print']) && (string)$_GET['auto_print'] === '1';
    $serverPrint = isset($_GET['server_print']) && (string)$_GET['server_print'] === '1';
    $serverPrintResult = null;
    if ($serverPrint && $printer->isEnabled()) {
        $serverPrintResult = $printer->printPalletLabel($pallet, $palletBoxes);
    }

    $barcodeValue = strtoupper(trim((string)$pallet['pallet_code']));
    $barcodeSvg = code39Svg($barcodeValue, 70, 2, 5);
    $destinationLabel = (string)$pallet['destination_mode'] === 'CUSTOMER_ORDER'
        ? 'OC cliente: ' . (string)($pallet['customer_order_ref'] ?? '-')
        : 'Bodega: ' . (string)($pallet['warehouse_code'] ?? '-');
    $boxCodes = array_map(
        static fn(array $box): string => (string)($box['box_code'] ?? ''),
        array_filter($palletBoxes, static fn(array $box): bool => trim((string)($box['box_code'] ?? '')) !== '')
    );

    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Etiqueta pallet</title>';
    echo '<style>
      *{box-sizing:border-box}
      body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:0;background:#fff;color:#111}
      .wrap{padding:10px}
      .label{border:2px solid #111;border-radius:10px;padding:14px;max-width:520px}
      .brand{font-weight:900;letter-spacing:1px;font-size:18px}
      .kind{margin-top:4px;font-size:13px;font-weight:800}
      .product{margin-top:8px;font-size:22px;font-weight:900;line-height:1.15}
      .divider{height:2px;background:#111;margin:10px 0}
      .rows{display:grid;grid-template-columns:120px 1fr;gap:6px 10px}
      .k{font-size:12px;color:#374151}
      .v{font-size:14px;font-weight:800}
      .bottom{margin-top:12px;display:flex;flex-direction:column;align-items:center;justify-content:center}
      .barcode{margin:6px 0 4px}
      .idtxt{font-size:16px;font-weight:900;letter-spacing:.5px}
      .ok{background:#ecfeff;border:1px solid #a5f3fc;color:#155e75;padding:10px;border-radius:10px;margin-bottom:10px}
      .err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px;border-radius:10px;margin-bottom:10px}
      @media print{
        body{margin:0}
        .wrap{padding:0}
        .label{border:none}
      }
    </style></head><body><div class="wrap">';
    if (is_array($serverPrintResult)) {
        if (($serverPrintResult['ok'] ?? false) === true) {
            echo '<div class="ok">Etiqueta enviada a impresora Zebra.</div>';
        } else {
            echo '<div class="err">No se pudo imprimir: ' . h((string)($serverPrintResult['error'] ?? 'Error desconocido.')) . '</div>';
        }
    }
    echo '<div class="label">';
    echo '<div class="brand">UNIBAG</div>';
    echo '<div class="kind">PALLET TRAZABLE</div>';
    echo '<div class="product">' . h((string)$pallet['final_sku']) . '</div>';
    echo '<div class="divider"></div>';
    echo '<div class="rows">';
    echo '<div class="k">Pallet</div><div class="v">' . h((string)$pallet['pallet_code']) . '</div>';
    echo '<div class="k">OT</div><div class="v">' . h((string)($pallet['ot_code'] ?? '-')) . '</div>';
    echo '<div class="k">Bobina origen</div><div class="v">' . h((string)$pallet['source_roll_code']) . '</div>';
    echo '<div class="k">Cajas</div><div class="v">' . h((string)$pallet['box_count']) . '</div>';
    echo '<div class="k">Operador</div><div class="v">' . h((string)$pallet['operator_name']) . '</div>';
    echo '<div class="k">Destino</div><div class="v">' . h($destinationLabel) . '</div>';
    echo '<div class="k">Códigos caja</div><div class="v">' . h($boxCodes === [] ? '-' : implode(', ', $boxCodes)) . '</div>';
    echo '</div>';
    echo '<div class="bottom">';
    if ($barcodeSvg !== '') {
        echo '<div class="barcode">' . $barcodeSvg . '</div>';
    }
    echo '<div class="idtxt">' . h($barcodeValue) . '</div>';
    echo '</div></div></div>';
    if ($autoPrint) {
        echo '<script>
          (function(){
            function doPrint(){
              try { window.focus(); } catch(e) {}
              try { window.print(); } catch(e) {}
            }
            function schedule(){
              try { setTimeout(doPrint, 800); } catch(e) {}
            }
            if (document.readyState === "complete") schedule();
            else window.addEventListener("load", schedule);
          })();
        </script>';
    }
    echo '</body></html>';
    exit;
}

if (preg_match('#^/work-orders/(\d+)/box-label$#', $path, $m) === 1 && $method === 'GET') {
    $id = (int)$m[1];
    $ot = $service->getWorkOrder($id);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }

    $finish = $service->getLastWorkOrderFinish($id);
    $autoPrint = isset($_GET['auto_print']) && (string)$_GET['auto_print'] === '1';
    $barcodeValue = strtoupper(trim((string)$ot['ot_code']));
    $barcodeSvg = code39Svg($barcodeValue, 70, 2, 5);

    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Etiqueta cajas</title>';
    echo '<style>
      *{box-sizing:border-box}
      body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:0;background:#fff;color:#111}
      .wrap{padding:10px}
      .label{border:2px solid #111;border-radius:10px;padding:14px;max-width:520px}
      .brand{font-weight:900;letter-spacing:1px;font-size:18px}
      .kind{margin-top:4px;font-size:13px;font-weight:800}
      .product{margin-top:8px;font-size:24px;font-weight:900;line-height:1.15}
      .divider{height:2px;background:#111;margin:10px 0}
      .rows{display:grid;grid-template-columns:120px 1fr;gap:6px 10px}
      .k{font-size:12px;color:#374151}
      .v{font-size:14px;font-weight:800}
      .bottom{margin-top:12px;display:flex;flex-direction:column;align-items:center;justify-content:center}
      .barcode{margin:6px 0 4px}
      .idtxt{font-size:16px;font-weight:900;letter-spacing:.5px}
      @media print{
        body{margin:0}
        .wrap{padding:0}
        .label{border:none}
      }
    </style></head><body><div class="wrap"><div class="label">';

    echo '<div class="brand">UNIBAG</div>';
    echo '<div class="kind">CAJA PRODUCTO TERMINADO</div>';
    echo '<div class="product">' . h((string)$ot['sku_final']) . '</div>';
    echo '<div class="divider"></div>';
    echo '<div class="rows">';
    echo '<div class="k">OT</div><div class="v">' . h((string)$ot['ot_code']) . '</div>';
    echo '<div class="k">Fecha cierre</div><div class="v">' . h((string)($finish['created_at'] ?? '-')) . '</div>';
    echo '<div class="k">Operador</div><div class="v">' . h((string)($finish['operator_name'] ?? '-')) . '</div>';
    echo '<div class="k">Cantidad cajas</div><div class="v">' . h((string)($finish['box_qty'] ?? '-')) . '</div>';
    echo '</div>';
    echo '<div class="bottom">';
    if ($barcodeSvg !== '') {
        echo '<div class="barcode">' . $barcodeSvg . '</div>';
    }
    echo '<div class="idtxt">' . h($barcodeValue) . '</div>';
    echo '</div>';
    echo '</div></div>';
    if ($autoPrint) {
        echo '<script>
          (function(){
            function doPrint(){
              try { window.focus(); } catch(e) {}
              try { window.print(); } catch(e) {}
            }
            function schedule(){
              try { setTimeout(doPrint, 800); } catch(e) {}
            }
            if (document.readyState === "complete") schedule();
            else window.addEventListener("load", schedule);
          })();
        </script>';
    }
    echo '</body></html>';
    exit;
}

http_response_code(404);
render('No encontrado', '<div class="card">Ruta no encontrada.</div>');
