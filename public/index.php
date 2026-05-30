<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/ReceptionService.php';
require_once __DIR__ . '/../src/ScaleService.php';
require_once __DIR__ . '/../src/PrintService.php';

Env::load(__DIR__ . '/../.env');

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

if ($path === '/logout') {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    expireCsrfCookie();
    session_start();
    header('Location: /login');
    exit;
}

if ($path === '/login' && $method === 'POST') {
    requireCsrf();

    try {
        $trzPdo = Db::trzPdo();
    } catch (Throwable $e) {
        renderDatabaseConnectionError($e);
    }
    ensureAuthSchema($trzPdo);

    $username = trim((string)($_POST['user_login'] ?? ''));
    $password = (string)($_POST['user_pass'] ?? '');
    $companyId = isset($_POST['user_company_id']) ? (int)$_POST['user_company_id'] : 1;
    $erpArea = normalizeErpArea((string)($_POST['erp_area'] ?? 'ERP'));
    $appMode = 0;
    $plantId = 0;

    $modes = authModeDefinitions();
    $companies = authCompanyDefinitions();
    $plants = authPlantDefinitions();
    $mode = $modes[$appMode] ?? $modes[0];
    $company = $companies[$companyId] ?? $companies[1];
    $plant = null;

    $user = null;
    if ($username !== '') {
        $stmt = $trzPdo->prepare('SELECT * FROM auth_users WHERE username = :username AND is_active = 1 LIMIT 1');
        $stmt->execute(['username' => $username]);
        $found = $stmt->fetch();
        if (is_array($found) && password_verify($password, (string)$found['password_hash'])) {
            $permissionColumn = authPermissionColumn($appMode);
            $areaPermissions = userAreaPermissions($found);
            if ($permissionColumn !== '' && (int)($found[$permissionColumn] ?? 0) === 1 && userCanAccessArea($erpArea, $areaPermissions)) {
                $user = $found;
            }
        }
    }

    if (!is_array($user)) {
        renderLoginPage('Usuario, clave o modo sin acceso.', [
            'user_login' => $username,
            'user_company_id' => $companyId,
            'erp_area' => $erpArea,
            'appmode' => $appMode,
            'user_planta_id' => $plantId,
        ]);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['auth_user_id'] = (int)$user['id'];
    $_SESSION['auth_username'] = (string)$user['username'];
    $_SESSION['auth_display_name'] = (string)$user['display_name'];
    $_SESSION['operator_name'] = (string)$user['display_name'];
    $_SESSION['menu_appmode'] = (int)$mode['id'];
    $_SESSION['app_mode_label'] = (string)$mode['label'];
    $_SESSION['user_company_id'] = (int)$company['id'];
    $_SESSION['company_name'] = (string)$company['label'];
    $_SESSION['user_planta_id'] = $plant !== null ? (int)$plant['id'] : 0;
    $_SESSION['plant_name'] = $plant !== null ? (string)$plant['label'] : '';
    $areaPermissions = userAreaPermissions($user);
    $_SESSION['perm_area_erp'] = $areaPermissions['ERP'] ? 1 : 0;
    $_SESSION['perm_area_reception'] = $areaPermissions['RECEPTION'] ? 1 : 0;
    $_SESSION['perm_area_production'] = $areaPermissions['PRODUCTION'] ? 1 : 0;
    $_SESSION['erp_area'] = $erpArea;
    $_SESSION['erp_area_label'] = erpAreaDefinitions()[$erpArea]['label'] ?? 'ERP';

    $erpAreaHome = erpAreaDefinitions()[$erpArea]['home'] ?? '/';
    header('Location: ' . $erpAreaHome);
    exit;
}

$isAuthenticated = (int)($_SESSION['auth_user_id'] ?? $_SESSION['user_id'] ?? 0) > 0;
if ($path === '/login' && $method === 'GET') {
    if ($isAuthenticated) {
        header('Location: /');
        exit;
    }
    renderLoginPage();
    exit;
}

if (!$isAuthenticated) {
    header('Location: /login');
    exit;
}

$sessionAreaPermissions = sessionAreaPermissions();
$requestedArea = detectRequestedArea($path);
if (!userCanAccessArea($requestedArea, $sessionAreaPermissions)) {
    header('Location: ' . firstAllowedAreaHome($sessionAreaPermissions));
    exit;
}

try {
    $trzPdo = Db::trzPdo();
    $erpPdo = Db::erpPdo();
} catch (Throwable $e) {
    renderDatabaseConnectionError($e);
}

$service = new ReceptionService($trzPdo, $erpPdo);
$scale = new ScaleService($_ENV);
$printer = new PrintService($_ENV);

if ($path === '/api/scale/weight' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $result = $scale->readWeightKg();
    if ($result['ok'] !== true) {
        http_response_code(502);
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/api/receptions/receive' && $method === 'POST') {
    requireCsrf();
    header('Content-Type: application/json; charset=utf-8');

    $lineId = isset($_POST['purchase_order_line_id']) ? (int)$_POST['purchase_order_line_id'] : 0;
    $containerItemId = isset($_POST['import_container_item_id']) ? (int)$_POST['import_container_item_id'] : 0;
    $warehouseId = isset($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : 0;
    $weight = isset($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : 0.0;
    $receivedQty = isset($_POST['received_qty']) ? (float)$_POST['received_qty'] : 1.0;
    $receptionMode = isset($_POST['reception_mode']) ? (string)$_POST['reception_mode'] : 'QUANTITY';
    if ($containerItemId > 0) {
        $result = $service->createRollFromImportContainerLine($containerItemId, $warehouseId, $weight, $currentOperatorName, $receivedQty, $receptionMode);
    } else {
        $result = $service->createRollFromPurchaseOrderLine($lineId, $warehouseId, $weight, $currentOperatorName, $receivedQty, $receptionMode);
    }
    if ($result['ok'] !== true) {
        http_response_code(422);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rollId = (int)$result['id'];
    $printed = false;
    $printError = null;
    if ($printer->isEnabled()) {
        $roll = $service->getRoll($rollId);
        if (is_array($roll)) {
            $p = $printer->printRollLabel($roll);
            $printed = ($p['ok'] ?? false) === true;
            $printError = $printed ? null : (string)($p['error'] ?? 'No se pudo imprimir.');
        } else {
            $printError = 'No se encontró la bobina para imprimir.';
        }
    }
    echo json_encode([
        'ok' => true,
        'id' => $rollId,
        'label_url' => '/rolls/' . $rollId . '/label?auto_print=1',
        'printed' => $printed,
        'print_error' => $printError,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
    return 'Por unidades';
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
        'ACTIVE' => 'En producción',
        'CUTTING' => 'Corte pendiente',
        'CLOSED' => 'Cerrada',
        default => trim((string)$status) !== '' ? (string)$status : '-',
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

function materialRequestTypeLabel(?string $type): string
{
    return match (strtoupper(trim((string)$type))) {
        'ROLL' => 'Bobina',
        'CHEMICAL' => 'Tinta',
        'OTHER' => 'Otro insumo',
        default => trim((string)$type) !== '' ? (string)$type : '-',
    };
}

function eventTypeLabel(?string $type): string
{
    return match (strtoupper(trim((string)$type))) {
        'WORK_ORDER_STARTED' => 'OT iniciada',
        'WORK_ORDER_FINISHED' => 'OT finalizada',
        'WORK_ORDER_ROLL_ATTACHED' => 'Bobina ingresada a OT',
        'WORK_ORDER_ROLL_RELEASED' => 'Bobina salida de OT',
        'MATERIAL_REQUESTED' => 'Material solicitado',
        'MATERIAL_ACCEPTED' => 'Solicitud aceptada',
        'MATERIAL_DELIVERED' => 'Material entregado',
        'CHEMICAL_INPUT_RECORDED' => 'Tinta de entrada registrada',
        'OUTPUT_ROLL_CREATED' => 'Bobina de salida creada',
        'CUT_COMPLETED' => 'Corte completado',
        'BOX_CREATED' => 'Caja creada',
        'PALLET_CREATED' => 'Pallet creado',
        'PALLET_TRANSFERRED' => 'Pallet trasladado',
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
    $mode = 'QUANTITY';
    $ordered = round((float)($line['ordered_rolls'] ?? 0), 3);
    $received = round((float)($line['received_qty'] ?? $line['received_rolls'] ?? 0), 3);
    $unit = 'Unid.';

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
            'home' => '/work-orders?view=pending',
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
        str_starts_with($path, '/purchase-orders')
        || str_starts_with($path, '/import-containers')
        || str_starts_with($path, '/stock')
        || (str_starts_with($path, '/pallets') && currentSessionArea() === 'RECEPTION')
    ) {
        return 'RECEPTION';
    }
    if (
        str_starts_with($path, '/work-orders')
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
    $link = static function (string $href, string $label, bool $isActive): string {
        return '<a class="prod-nav-link' . ($isActive ? ' active' : '') . '" href="' . h($href) . '">' . h($label) . '</a>';
    };

    echo '<div class="prod-shell" id="prodShell">';
    echo '<button class="prod-sidebar-backdrop" id="prodSidebarBackdrop" type="button" aria-label="Cerrar menú"></button>';
    echo '<aside class="prod-sidebar" id="prodSidebar">';
    echo '<div class="prod-brand">';
    echo '<div class="prod-brand-box">Unibag</div>';
    echo '<div class="prod-brand-sub">Producción</div>';
    echo '</div>';
    echo '<div class="prod-nav-title">Navegación</div>';
    echo '<div class="prod-nav-group">';
    echo '<div class="prod-nav-group-label">Panel</div>';
    echo $link('/', 'Inicio producción', $currentPath === '/');
    echo '</div>';
    echo '<div class="prod-nav-group">';
    echo '<div class="prod-nav-group-label">Órdenes de trabajo</div>';
    echo $link('/work-orders?view=pending', 'OT pendientes', str_starts_with($currentPath, '/work-orders') && ($view === 'pending' || $view === ''));
    echo $link('/work-orders?view=active', 'OT en proceso', str_starts_with($currentPath, '/work-orders') && $view === 'active');
    echo $link('/work-orders?view=closed', 'OT finalizadas', str_starts_with($currentPath, '/work-orders') && $view === 'closed');
    echo '</div>';
    echo '<div class="prod-nav-group">';
    echo '<div class="prod-nav-group-label">Trazabilidad</div>';
    echo $link('/cut', 'Proceso de corte', str_starts_with($currentPath, '/cut') || str_starts_with($currentPath, '/boxes'));
    echo $link('/chemicals/weighings', 'Pesajes de tintas', str_starts_with($currentPath, '/chemicals'));
    echo '</div>';
    echo '</aside>';
    echo '<div class="prod-main-wrap">';
    echo '<header class="prod-topbar">';
    echo '<div class="prod-topbar-title">';
    echo '<div class="prod-topbar-brand">Unibag</div>';
    echo '<button class="prod-toggle-btn" id="prodSidebarToggle" type="button" aria-expanded="false" aria-controls="prodSidebar" aria-label="Abrir menú">|||</button>';
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
        var shell = document.getElementById("prodShell");
        var sidebar = document.getElementById("prodSidebar");
        var toggle = document.getElementById("prodSidebarToggle");
        var backdrop = document.getElementById("prodSidebarBackdrop");
        if (!shell || !sidebar || !toggle || !backdrop) return;
        var sync = function (open) {
          shell.classList.toggle("menu-open", open);
          toggle.setAttribute("aria-expanded", open ? "true" : "false");
          toggle.setAttribute("aria-label", open ? "Cerrar menú" : "Abrir menú");
        };
        toggle.addEventListener("click", function () {
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
        .login-box{width:560px;border:1px solid #a6a6a6;background:#efefef;box-shadow:0 1px 0 #fff inset}
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
}

function render(string $title, string $body): void
{
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($title) . '</title>';
    echo '<style>
        *{box-sizing:border-box}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:0;background:#f6f7f9;color:#111}
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
        .btn{display:inline-block;background:#0aa;color:#fff;border:none;border-radius:8px;padding:10px 12px;font-weight:600;text-decoration:none;cursor:pointer}
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
          .row.nowrap{flex-wrap:wrap}
          .trace-grid{grid-template-columns:1fr}
          .dashboard-grid{grid-template-columns:1fr}
          .kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
          .ot-stage-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
          .ot-request-grid{grid-template-columns:1fr}
          .ot-request-grid{grid-template-columns:1fr}
          .prod-shell{flex-direction:column}
        }
        @media (max-width: 640px){
          .ot-stage-grid{grid-template-columns:1fr}
          .prod-topbar{align-items:flex-start}
          .prod-topbar-brand{font-size:22px}
        }
        table{width:100%;border-collapse:collapse}
        th,td{border-bottom:1px solid #e5e7eb;padding:8px;text-align:left;font-size:13px}
        .table-wrap{overflow-x:auto}
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
        .prod-sidebar-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.35);border:none;padding:0;margin:0;opacity:0;pointer-events:none;transition:opacity .2s ease;z-index:39}
        .prod-sidebar{width:280px;background:#0b6665;color:#fff;display:flex;flex-direction:column;box-shadow:2px 0 6px rgba(15,23,42,.12);position:fixed;top:0;left:0;bottom:0;z-index:40;transform:translateX(-100%);transition:transform .22s ease}
        .prod-shell.menu-open .prod-sidebar{transform:translateX(0)}
        .prod-shell.menu-open .prod-sidebar-backdrop{opacity:1;pointer-events:auto}
        .prod-brand{padding:16px 16px 12px;border-bottom:1px solid rgba(255,255,255,.14)}
        .prod-brand-box{background:#fff;color:#0aa;padding:10px 14px;border-radius:4px;font-size:34px;font-weight:800;line-height:1}
        .prod-brand-sub{margin-top:8px;font-size:13px;font-weight:700;letter-spacing:.04em;color:#d9fffe;text-transform:uppercase}
        .prod-nav-title{padding:12px 16px 8px;font-size:14px;font-weight:700;color:#d9fffe}
        .prod-nav-group{padding:0 0 12px}
        .prod-nav-group-label{padding:10px 16px 6px;font-size:13px;font-weight:800;color:#b8f3f1;text-transform:uppercase}
        .prod-nav-link{display:block;padding:11px 16px;color:#fff;text-decoration:none;font-weight:700;border-left:4px solid transparent}
        .prod-nav-link:hover{background:rgba(255,255,255,.08)}
        .prod-nav-link.active{background:rgba(255,255,255,.12);border-left-color:#f2b21b}
        .prod-main-wrap{flex:1;display:flex;flex-direction:column;min-width:0}
        .prod-topbar{background:#08a8a6;color:#fff;padding:14px 18px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;box-shadow:0 2px 6px rgba(15,23,42,.08)}
        .prod-topbar-title{display:flex;align-items:center;gap:10px;font-size:18px;font-weight:800}
        .prod-topbar-brand{background:#fff;color:#0aa;padding:8px 12px;border-radius:4px;font-size:28px;font-weight:800;line-height:1}
        .prod-toggle-btn{display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border:1px solid rgba(255,255,255,.3);background:rgba(0,0,0,.12);color:#fff;border-radius:8px;cursor:pointer;font-size:16px;font-weight:800;line-height:1;transform:rotate(90deg)}
        .prod-toggle-btn:hover{background:rgba(0,0,0,.2)}
        .prod-topbar-right{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .prod-user-pill{background:rgba(0,0,0,.12);padding:8px 10px;border-radius:6px;font-size:13px;font-weight:700}
        .prod-user-pill.logout{color:#fff;text-decoration:none}
        .prod-main{padding:18px;max-width:none}
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
        return;
    }

    echo '<div class="topbar"><div class="inner">';
    echo '<nav class="menu">';
    if ($displayArea === 'ERP' && userCanAccessArea('ERP', $areaPermissions)) {
        echo $link('/', 'ERP', $currentPath === '/');
        echo $link('/purchase-orders?status=active&supplier_type=NATIONAL', 'Recepción', str_starts_with($currentPath, '/purchase-orders') || str_starts_with($currentPath, '/import-containers'));
        echo $link('/stock', 'Inventario', str_starts_with($currentPath, '/stock'));
        echo $link('/work-orders?view=pending', 'Trazabilidad', str_starts_with($currentPath, '/work-orders') || str_starts_with($currentPath, '/chemicals') || str_starts_with($currentPath, '/cut') || str_starts_with($currentPath, '/boxes') || str_starts_with($currentPath, '/pallets'));
    } elseif ($displayArea === 'RECEPTION') {
        echo $link('/purchase-orders?status=active&supplier_type=NATIONAL', 'Recepción', str_starts_with($currentPath, '/purchase-orders') || str_starts_with($currentPath, '/import-containers'));
        echo $link('/stock', 'Inventario', str_starts_with($currentPath, '/stock') || str_starts_with($currentPath, '/pallets'));
    } else {
        echo $link('/work-orders?view=pending', 'Producción', str_starts_with($currentPath, '/work-orders') || str_starts_with($currentPath, '/chemicals') || str_starts_with($currentPath, '/cut') || str_starts_with($currentPath, '/boxes') || str_starts_with($currentPath, '/pallets'));
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
    if (str_starts_with($currentPath, '/purchase-orders') || str_starts_with($currentPath, '/import-containers')) { $activeModule = 'reception'; }
    elseif (str_starts_with($currentPath, '/stock') || (str_starts_with($currentPath, '/pallets') && currentSessionArea() === 'RECEPTION')) { $activeModule = 'inventory'; }
    elseif (str_starts_with($currentPath, '/work-orders') || str_starts_with($currentPath, '/chemicals') || str_starts_with($currentPath, '/cut') || str_starts_with($currentPath, '/boxes') || str_starts_with($currentPath, '/pallets')) { $activeModule = 'production'; }

    echo '<div class="submenu">';
    if ($activeModule === 'reception') {
        echo '<a class="subitem" href="/purchase-orders?status=active&supplier_type=NATIONAL"><span>Recepción nacional</span></a>';
        echo '<a class="subitem" href="/import-containers?status=active"><span>Importación</span></a>';
        echo '<a class="subitem" href="/purchase-orders?status=complete"><span>Recepciones finalizadas</span></a>';
    } elseif ($activeModule === 'inventory') {
        echo '<a class="subitem" href="/stock"><span>Inventario</span></a>';
        echo '<a class="subitem" href="/stock/material-requests"><span>Solicitudes</span></a>';
        echo '<a class="subitem" href="/stock/transfers"><span>Traspaso</span></a>';
        echo '<a class="subitem" href="/pallets"><span>Asignación de pallets</span></a>';
    } elseif ($activeModule === 'production') {
        echo '<a class="subitem" href="/work-orders?view=pending"><span>OT pendientes</span></a>';
        echo '<a class="subitem" href="/work-orders?view=active"><span>OT en proceso</span></a>';
        echo '<a class="subitem" href="/work-orders?view=closed"><span>OT finalizadas</span></a>';
        echo '<a class="subitem" href="/cut"><span>Proceso de corte</span></a>';
        echo '<a class="subitem" href="/chemicals/weighings"><span>Pesajes de tintas</span></a>';
    }
    echo '</div>';
    echo '</div></div>';
    echo '<main>' . $body . '</main></body></html>';
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
    array $cutWarehouses = []
): void {
    if (isErpProductionReadOnlyMode()) {
        renderErpWorkOrderReadOnlyScreen($ot, $currentRoll, $rollHistory, $chemicalInputs, $lastStart, $lastFinish, $materialRequests, $wastes, $boxes, $pallets, $outputRoll);
        return;
    }

    $status = (string)$ot['status'];
    $statusLabel = match ($status) {
        'ACTIVE' => 'Producción',
        'CUTTING' => 'Corte pendiente',
        'CLOSED' => 'Fabricada completa',
        default => 'Pendiente',
    };
    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Inicio OT ' . h((string)$ot['ot_code']) . '</div>
          <div class="muted">Pesaje de bobina en proceso, impresión, corte y cierre total de la OT.</div>
        </div>
        <div class="row">
          <a class="btn secondary" href="/work-orders">Volver</a>
          <a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/traceability">Trazabilidad OT</a>
          <a class="btn secondary" href="/chemicals/weighings">Tintas</a>
        </div>
      </div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= '<div class="card" style="margin-bottom:12px">
        <div class="ot-meta-grid">
          <div class="ot-meta"><div class="muted">OT</div><div class="value">' . h((string)$ot['ot_code']) . '</div></div>
          <div class="ot-meta"><div class="muted">SKU final</div><div class="value">' . h((string)$ot['sku_final']) . '</div></div>
          <div class="ot-meta"><div class="muted">Estado</div><div class="value">' . h($statusLabel) . '</div></div>
          <div class="ot-meta"><div class="muted">Operador</div><div class="value">' . h($currentOperatorName) . '</div></div>
        </div>';
    if ($lastFinish !== null) {
        $body .= '<details class="fold" style="margin-top:12px"><summary>Último cierre OT</summary><div class="fold-body">
            <div class="ot-meta-grid">
              <div class="ot-meta"><div class="muted">Fecha</div><div class="value">' . h((string)$lastFinish['created_at']) . '</div></div>
              <div class="ot-meta"><div class="muted">Peso final bobina</div><div class="value">' . h((string)($lastFinish['final_roll_weight_kg'] ?? '0')) . ' Kg</div></div>
              <div class="ot-meta"><div class="muted">Peso final tintas</div><div class="value">' . h((string)($lastFinish['final_chemical_weight_kg'] ?? '0')) . ' Kg</div></div>
              <div class="ot-meta"><div class="muted">Cajas</div><div class="value">' . h((string)($lastFinish['box_qty'] ?? '0')) . '</div></div>
              <div class="ot-meta"><div class="muted">Merma</div><div class="value">' . h((string)($lastFinish['waste_kg'] ?? '0')) . ' Kg</div></div>
            </div>';
        if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
            $body .= '<div class="row" style="margin-top:10px"><a class="btn secondary" href="/rolls/' . (int)$lastFinish['output_roll_id'] . '/label?auto_print=1" target="_blank" rel="noopener">Etiqueta bobina final</a><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/box-label?auto_print=1" target="_blank" rel="noopener">Etiqueta cajas producto</a></div>';
        }
        $body .= '</div></details>';
    }
    $body .= '</div>';

    $pendingRequests = array_values(array_filter($materialRequests, static fn(array $req): bool => (string)($req['status'] ?? '') !== 'DELIVERED'));
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
    $currentStage = $isClosed ? 5 : ($isCutting ? 4 : ($showFinishData ? 3 : ($isStarted ? 2 : 1)));
    $stageTitles = [
        1 => 'Preparación OT',
        2 => 'Producción activa',
        3 => 'Cierre de impresión',
        4 => 'Corte de la OT',
        5 => 'Fabricación completa',
    ];
    $nextTasks = [];
    if ($currentStage === 1) {
        if ($currentRoll === null) {
            $nextTasks[] = 'Registrar la bobina inicial.';
        }
        if ($chemicalInputs === []) {
            $nextTasks[] = 'Registrar las tintas de entrada.';
        }
        $nextTasks[] = 'Iniciar producción cuando la preparación esté lista.';
    } elseif ($currentStage === 2) {
        if ($pendingRequests !== []) {
            $nextTasks[] = 'Revisar solicitudes pendientes en bodega.';
        }
        $nextTasks[] = 'Registrar merma y cambio de bobina solo si es necesario.';
        $nextTasks[] = 'Pasar al cierre de impresión cuando termine la producción.';
    } elseif ($currentStage === 3) {
        $nextTasks[] = 'Registrar pesos finales de bobina y tintas.';
        $nextTasks[] = 'Indicar cajas objetivo y peso de la nueva bobina de salida.';
        $nextTasks[] = 'Guardar el cierre para generar la salida de producción y pasar a corte.';
    } elseif ($currentStage === 4) {
        $nextTasks[] = 'Escanear o confirmar la bobina salida de impresión.';
        $nextTasks[] = 'Registrar unidades, cajas, pallets y destino final.';
        $nextTasks[] = 'Completar corte para cerrar la OT.';
    } else {
        $nextTasks[] = 'Revisar la bobina de salida, cajas y pallets generados.';
        $nextTasks[] = 'Usar la trazabilidad OT para revisar el proceso completo.';
    }

    $body .= '<div class="ot-stage-grid" style="margin-bottom:12px">';
    foreach ([1, 2, 3, 4, 5] as $stageNumber) {
        $isDone = $stageNumber < $currentStage;
        $isCurrent = $stageNumber === $currentStage;
        $statusLabel = $isDone ? 'Completado' : ($isCurrent ? 'En curso' : 'Pendiente');
        $statusColor = $isDone ? '#067647' : ($isCurrent ? '#259492' : '#667085');
        $stageClass = 'ot-stage-card' . ($isCurrent ? ' current' : '') . ($isDone ? ' done' : '');
        $body .= '<div class="' . $stageClass . '">
            <div class="muted">Etapa ' . $stageNumber . '</div>
            <div style="font-weight:800;margin:4px 0 6px">' . h($stageTitles[$stageNumber]) . '</div>
            <div style="font-size:12px;color:' . $statusColor . ';font-weight:700">' . $statusLabel . '</div>
          </div>';
    }
    $body .= '</div>';

    $body .= '<div class="card" style="margin-bottom:12px">
        <div style="font-weight:800;margin-bottom:8px">Etapa actual: ' . h($stageTitles[$currentStage]) . '</div>
        <div class="muted" style="margin-bottom:10px">La pantalla muestra solo las acciones principales de esta etapa para que el operador vea con claridad lo que falta por hacer.</div>
        <div class="ot-tasks">';
    foreach ($nextTasks as $task) {
        $body .= '<div class="ot-task"><div style="font-weight:700">Pendiente</div><div class="muted" style="margin-top:4px">' . h($task) . '</div></div>';
    }
    $body .= '</div></div>';

    if (in_array($currentStage, [1, 2], true)) {
        $sectionTitle = $currentStage === 1 ? '1. Solicitudes a bodega' : 'Solicitudes adicionales durante producción';
        $sectionHint = $currentStage === 1
            ? 'Prepara bobinas, tintas e insumos de la OT antes de iniciar producción.'
            : 'Si durante la producción falta material, desde aquí se solicitan los faltantes a bodega.';

        $body .= '<div class="ot-request-grid" style="margin-bottom:12px">
          <div class="card">
            <div style="font-weight:800;margin-bottom:6px">' . $sectionTitle . '</div>
            <div class="muted" style="margin-bottom:12px">' . $sectionHint . '</div>
            <details class="fold">
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
            </details>
            <details class="fold">
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
            </details>
            <details class="fold">
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
            </details>
          </div>
          <div class="card">
          <div style="font-weight:800;margin-bottom:6px">Solicitudes de la OT</div>
          <div class="muted" style="margin-bottom:12px">Aquí ves qué pidió la OT, cuánto se entregó y qué sigue pendiente en bodega.</div>
          <details class="fold"' . ($materialRequests !== [] ? ' open' : '') . '>
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
          <div class="muted" style="margin-top:10px">La atención de estas solicitudes se realiza desde `Inventario -> Solicitudes`, donde bodega acepta y entrega el material correspondiente.</div>
          </div></details></div></div>';
    }

    if ($currentStage === 2) {
        $body .= '<div class="grid" style="margin-bottom:12px">
        <div class="card">
          <div style="font-weight:800;margin-bottom:8px">Registro de merma</div>
          <form method="post" action="/work-orders/' . (int)$ot['id'] . '/waste">
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
          </form>
        </div>
        <div class="card">
          <div style="font-weight:800;margin-bottom:8px">Mermas registradas</div>
          <table><thead><tr><th>Fecha</th><th>Motivo</th><th>Peso</th><th>Operador</th></tr></thead><tbody>';
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
    $body .= '</tbody></table>
        </div>
      </div>';
    }

    if ($currentStage >= 4 && ($outputRoll !== null || $boxes !== [] || $pallets !== [])) {
        $body .= '<details class="fold" open style="margin-bottom:12px"><summary>Salida de producción y corte</summary><div class="fold-body">';
        if ($outputRoll !== null) {
            $body .= '<div class="panel" style="margin-bottom:10px">
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
              </div>';
        }
        if ($boxes !== []) {
            $body .= '<div style="font-weight:800;margin-bottom:6px">Cajas generadas</div><div class="table-wrap"><table class="table-compact"><thead><tr><th>Código</th><th>Bobina origen</th><th>Unidades</th><th>Pallet</th><th></th></tr></thead><tbody>';
            foreach ($boxes as $box) {
                $body .= '<tr><td>' . h((string)$box['box_code']) . '</td><td>' . h((string)($box['source_roll_code'] ?? '-')) . '</td><td>' . h((string)$box['units_qty']) . '</td><td>' . h((string)($box['pallet_code'] ?? '-')) . '</td><td><a class="btn secondary" href="/boxes/' . (int)$box['id'] . '">Ver</a></td></tr>';
            }
            $body .= '</tbody></table></div>';
        }
        if ($pallets !== []) {
            $body .= '<div style="font-weight:800;margin:10px 0 6px">Pallets generados</div><div class="table-wrap"><table class="table-compact"><thead><tr><th>Código</th><th>Cajas</th><th>Destino</th><th></th></tr></thead><tbody>';
            foreach ($pallets as $pallet) {
                $body .= '<tr><td>' . h((string)$pallet['pallet_code']) . '</td><td>' . h((string)$pallet['box_count']) . '</td><td>' . h((string)$pallet['destination_mode']) . '</td><td><a class="btn secondary" href="/pallets/' . (int)$pallet['id'] . '">Ver</a></td></tr>';
            }
            $body .= '</tbody></table></div>';
        }
        $body .= '</div></details>';
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

    if (!$isStarted) {
        $body .= '<div class="card" style="margin-bottom:12px">
            <div style="font-weight:800;margin-bottom:6px">1. Registro de inicio</div>
            <div class="muted">Registra la bobina de arranque y las tintas antes de iniciar producción.</div>
          </div>';

        $body .= '<div class="grid">
          <div class="card">
            <div style="font-weight:800;margin-bottom:8px">Registro bobina inicial</div>
            <form method="post" action="/work-orders/' . (int)$ot['id'] . '/attach-roll">
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
              <div class="muted" style="margin-top:6px">Aquí registras el peso real con que la bobina entra a producción.</div>
              <div style="margin-top:12px">
                <button class="btn" type="submit">Guardar bobina inicial</button>
              </div>
            </form>
          </div>
          <div class="card">
            <div style="font-weight:800;margin-bottom:8px">Bobina lista para iniciar</div>';
        if ($currentRoll !== null) {
            $body .= '<div class="panel">
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
        $body .= '</div>
        </div>';

        $body .= '<div class="grid" style="margin-top:12px">
          <div class="card">
            <div style="font-weight:800;margin-bottom:8px">Registro de tintas de inicio</div>
            <form method="post" action="/work-orders/' . (int)$ot['id'] . '/chemical-input">
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
            </form>
          </div>
          <div class="card">
            <div style="font-weight:800;margin-bottom:8px">Tintas registradas</div>
            <table><thead><tr><th>Tinta</th><th>Peso</th><th>Fecha</th></tr></thead><tbody>';
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
        $body .= '</tbody></table>
          </div>
        </div>';

        $body .= '<div class="card" style="margin-top:12px">
            <div style="font-weight:800;margin-bottom:8px">2. Iniciar producción</div>
            <div class="muted" style="margin-bottom:10px">Cuando el registro inicial esté listo, inicia formalmente la producción.</div>
            <form method="post" action="/work-orders/' . (int)$ot['id'] . '/start">
              <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
              <button class="btn" type="submit">Iniciar producción</button>
            </form>
          </div>';

        $body .= '<div class="panel" style="margin-top:12px">
            <div style="font-weight:800;margin-bottom:6px">3. Cambio de bobina</div>
            <div class="muted">Disponible solo si es necesario, después de iniciar producción.</div>
          </div>';

        $body .= '<div class="panel" style="margin-top:12px">
            <div style="font-weight:800;margin-bottom:6px">4. Finalizar producción</div>
            <div class="muted">Los datos de cierre y la etiqueta final se registran después del término de producción.</div>
          </div>';
    } elseif (!$isClosed && !$isCutting) {
        if (!$showFinishData) {
            $body .= '<div class="card" style="margin-bottom:12px">
                <div style="font-weight:800;margin-bottom:8px">Bobina en producción</div>';
            if ($currentRoll !== null) {
                $body .= '<div class="row">
                    <div style="flex:1;min-width:180px"><div class="muted">Código</div><div style="font-weight:800">' . h((string)$currentRoll['roll_code']) . '</div></div>
                    <div style="flex:1;min-width:180px"><div class="muted">Código SKU</div><div style="font-weight:800">' . h((string)$currentRoll['sku_code']) . '</div></div>
                    <div style="flex:1;min-width:180px"><div class="muted">Bodega</div><div style="font-weight:800">' . h((string)$currentRoll['warehouse_code']) . '</div></div>
                    <div style="flex:1;min-width:180px"><div class="muted">Peso actual</div><div style="font-weight:800">' . h((string)$currentRoll['weight_kg']) . ' Kg</div></div>
                  </div>';
            } else {
                $body .= '<div class="muted">No hay una bobina activa en producción.</div>';
            }
            $body .= '</div>';

            $body .= '<div class="card" style="margin-top:12px">
                <div style="font-weight:800;margin-bottom:8px">3. Cambio de bobina (opcional)</div>
                <div class="muted" style="margin-bottom:10px">Usa esta sección solo si la bobina actual no alcanza para terminar la producción.</div>
                <form method="post" action="/work-orders/' . (int)$ot['id'] . '/change-roll">
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
                    <div style="flex:2;min-width:220px">
                      <div class="muted" style="padding-bottom:10px">Al confirmar el cambio se genera una bobina nueva con código propio, ligada a la bobina usada, y se imprime su etiqueta.</div>
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
                </form>
              </div>';

            $body .= '<div class="card" style="margin-top:12px">
                <div style="font-weight:800;margin-bottom:8px">4. Finalizar producción</div>
                <div class="muted" style="margin-bottom:10px">Cuando termine la impresión, continúa con el cierre para registrar datos finales y generar la bobina que pasará a corte.</div>
                <div class="row">
                  <a class="btn" href="/work-orders/' . (int)$ot['id'] . '/start?finish_data=1">Registrar cierre de impresión</a>
                </div>
              </div>';
        } else {
            $body .= '<div class="card" style="margin-top:12px">
                <div style="font-weight:800;margin-bottom:8px">5. Datos finalizar impresión</div>
                <div class="muted" style="margin-bottom:10px">Registra el peso final de la bobina, las tintas y la merma. Luego se imprime la etiqueta y la OT pasa a corte.</div>
                <form method="post" action="/work-orders/' . (int)$ot['id'] . '/finish">
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
                </form>
              </div>';
        }
    }

    if ($isCutting && $outputRoll !== null) {
        $body .= '<div class="card" id="cut-stage" style="margin-top:12px">
            <div style="font-weight:800;margin-bottom:8px">5. Corte final de la OT</div>
            <div class="muted" style="margin-bottom:10px">La OT sigue abierta hasta que la bobina impresa pase por corte y se generen cajas y pallets. La asignación a bodega final la realiza Bodega.</div>
            <form method="post" action="/cut/process">
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
            </form>
          </div>';
    }

    if ($rollHistory !== [] && $currentStage >= 2) {
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
      })();
    </script>';

    render('Inicio OT', $body);
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
    ?array $outputRoll
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
      </div></div>';

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Observación ERP</div>
        <div class="muted" style="margin-bottom:10px">Esta vista está orientada a seguimiento gerencial. Desde aquí solo se consulta avance, estado y trazabilidad; la ejecución se realiza en Producción.</div>
        <div class="panel">
          <div style="font-weight:700;margin-bottom:6px">Situación actual</div>
          <div class="muted">Estado: ' . h($statusLabel) . ' · Etapa: ' . h($stageTitles[$currentStage]) . '</div>
          <div class="muted" style="margin-top:6px">Bobina actual: ' . h((string)($currentRoll['roll_code'] ?? '-')) . ' · Bobina salida: ' . h((string)($outputRoll['roll_code'] ?? '-')) . '</div>
        </div></div>';
    $body .= '</div>';

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

if ($path === '/' && $method === 'GET') {
    $currentArea = normalizeErpArea((string)($_SESSION['erp_area'] ?? 'ERP'));
    if ($currentArea === 'PRODUCTION') {
        $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Producción</div>
          <div class="muted">Acceso directo a las órdenes de trabajo desde impresión hasta corte final.</div>
        </div>
      </div>';
        $body .= '<div class="trace-grid">';
        $body .= '<div class="card"><div style="font-size:16px;font-weight:800;margin-bottom:8px">Órdenes de trabajo</div><div class="muted" style="margin-bottom:12px">Administra OT pendientes, en producción, en corte y fabricadas completas.</div><a class="btn" href="/work-orders?view=pending">Ir a OT</a></div>';
        $body .= '<div class="card"><div style="font-size:16px;font-weight:800;margin-bottom:8px">Proceso de corte</div><div class="muted" style="margin-bottom:12px">Controla corte, cajas y pallets desde producción.</div><a class="btn" href="/cut">Ir a corte</a></div>';
        $body .= '</div>';
        render('Producción', $body);
        exit;
    }
    if ($currentArea === 'RECEPTION') {
        header('Location: /purchase-orders?status=active&supplier_type=NATIONAL');
        exit;
    }

    $summary = $service->getErpDashboardSummary();
    $alerts = $service->listErpDashboardAlerts();
    $recentTraceability = $service->listDashboardRecentTraceability(8);
    $recentEvents = $service->listRecentOperationalEvents(8);
    $stockSummary = $service->stockSummary();
    $maxWeight = 0.0;
    foreach ($stockSummary as $stockRow) {
        $maxWeight = max($maxWeight, (float)($stockRow['total_weight_kg'] ?? 0));
    }

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Panel ERP</div>
          <div class="muted">Resumen ejecutivo del estado de recepción, producción, corte y trazabilidad.</div>
        </div>
      </div>';

    $body .= '<div class="kpi-grid" style="margin-bottom:12px">';
    $body .= '<div class="kpi-card"><div class="kpi-label">OC pendientes</div><div class="kpi-value">' . h((string)$summary['reception']['purchase_orders_pending']) . '</div><div class="kpi-sub">Recepción nacional por completar</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Contenedores pendientes</div><div class="kpi-value">' . h((string)$summary['reception']['containers_pending']) . '</div><div class="kpi-sub">Importaciones aún abiertas</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">OT activas</div><div class="kpi-value">' . h((string)$summary['work_orders']['active']) . '</div><div class="kpi-sub">En producción ahora</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">OT en corte</div><div class="kpi-value">' . h((string)$summary['work_orders']['cutting']) . '</div><div class="kpi-sub">Impresas y pendientes de cierre</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Bobinas listas corte</div><div class="kpi-value">' . h((string)$summary['rolls']['ready_for_cut']) . '</div><div class="kpi-sub">Salida de impresión disponible</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Cajas / pallets</div><div class="kpi-value">' . h((string)$summary['packaging']['boxes']) . ' / ' . h((string)$summary['packaging']['pallets']) . '</div><div class="kpi-sub">Empaque total generado</div></div>';
    $body .= '</div>';

    $body .= '<div class="dashboard-grid" style="margin-bottom:12px">';
    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Alertas y foco del día</div>';
    foreach ($alerts as $alert) {
        $level = (string)($alert['level'] ?? 'info');
        $body .= '<div class="dashboard-alert ' . h($level) . '" style="margin-bottom:10px">';
        $body .= '<div style="font-weight:800;margin-bottom:4px">' . h((string)$alert['title']) . '</div>';
        $body .= '<div class="muted" style="margin-bottom:8px">' . h((string)$alert['detail']) . '</div>';
        $body .= '<a class="btn secondary" href="' . h((string)$alert['link']) . '">Revisar</a>';
        $body .= '</div>';
    }
    $body .= '</div>';

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Accesos rápidos</div>';
    $body .= '<div class="trace-grid">';
    $body .= '<div class="kpi-card"><div class="kpi-label">Recepción</div><div style="font-weight:800;margin-bottom:8px">OC y contenedores pendientes</div><a class="btn secondary" href="/purchase-orders?status=active&supplier_type=NATIONAL">Recepción nacional</a></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Producción</div><div style="font-weight:800;margin-bottom:8px">OT y cambio de bobinas</div><a class="btn secondary" href="/work-orders?view=pending">Órdenes de trabajo</a></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Corte</div><div style="font-weight:800;margin-bottom:8px">Bobinas listas, cajas y pallets</div><a class="btn secondary" href="/cut">Ir a corte</a></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Inventario</div><div style="font-weight:800;margin-bottom:8px">Inventario por bodega y especificación</div><a class="btn secondary" href="/stock">Ver inventario</a></div>';
    $body .= '</div></div>';
    $body .= '</div>';

    $body .= '<div class="dashboard-grid" style="margin-bottom:12px">';
    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Inventario por bodega</div>';
    foreach ($stockSummary as $stockRow) {
        $weight = (float)($stockRow['total_weight_kg'] ?? 0);
        $ratio = $maxWeight > 0 ? min(100, ($weight / $maxWeight) * 100) : 0;
        $body .= '<div style="margin-bottom:10px">';
        $body .= '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:4px"><div style="font-weight:700">' . h((string)$stockRow['warehouse_code']) . ' - ' . h((string)$stockRow['warehouse_name']) . '</div><div class="muted">' . h((string)$stockRow['rolls_count']) . ' IDs · ' . h(number_format($weight, 1, ',', '.')) . ' Kg</div></div>';
        $body .= '<div class="bar-track"><div class="bar-fill" style="width:' . h(number_format($ratio, 2, '.', '')) . '%"></div></div>';
        $body .= '</div>';
    }
    if ($stockSummary === []) {
        $body .= '<div class="muted">Sin stock registrado todavía.</div>';
    }
    $body .= '</div>';

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Actividad reciente</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Fecha</th><th>Evento</th><th>Detalle</th></tr></thead><tbody>';
    foreach ($recentEvents as $event) {
        $payload = $event['payload_data'] ?? [];
        $detailParts = [];
        if (isset($payload['work_order_id']) && (int)$payload['work_order_id'] > 0) { $detailParts[] = 'OT #' . (int)$payload['work_order_id']; }
        if (isset($payload['roll_id']) && (int)$payload['roll_id'] > 0) { $detailParts[] = 'Bobina #' . (int)$payload['roll_id']; }
        if (isset($payload['output_roll_id']) && (int)$payload['output_roll_id'] > 0) { $detailParts[] = 'Salida #' . (int)$payload['output_roll_id']; }
        if (isset($payload['operator_name']) && (string)$payload['operator_name'] !== '') { $detailParts[] = 'Operador ' . (string)$payload['operator_name']; }
        $body .= '<tr>';
        $body .= '<td>' . h((string)$event['created_at']) . '</td>';
        $body .= '<td>' . h(eventTypeLabel((string)$event['type'])) . '</td>';
        $body .= '<td>' . h($detailParts === [] ? '-' : implode(' · ', $detailParts)) . '</td>';
        $body .= '</tr>';
    }
    if ($recentEvents === []) {
        $body .= '<tr><td colspan="3" class="muted">Sin actividad reciente.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    $body .= '</div>';

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Trazabilidad reciente</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>OT</th><th>Bobina entrada</th><th>Bobina salida</th><th>Etapa</th><th>Cajas</th><th>Pallets</th><th></th></tr></thead><tbody>';
    foreach ($recentTraceability as $traceRow) {
        $traceStageLabel = rollProcessStageLabel((string)($traceRow['process_stage'] ?? ''));
        $traceStatusLabel = rollStatusLabel((string)($traceRow['status'] ?? ''));
        $body .= '<tr>';
        $body .= '<td>' . ((int)($traceRow['work_order_id'] ?? 0) > 0 ? '<a href="/work-orders/' . (int)$traceRow['work_order_id'] . '/traceability">' . h((string)($traceRow['ot_code'] ?? ('OT #' . (int)$traceRow['work_order_id']))) . '</a>' : '-') . '</td>';
        $body .= '<td>' . h((string)($traceRow['parent_roll_code'] ?? '-')) . '</td>';
        $body .= '<td><a href="/rolls/' . (int)$traceRow['id'] . '">' . h((string)$traceRow['roll_code']) . '</a></td>';
        $body .= '<td>' . h($traceStageLabel) . ' / ' . h($traceStatusLabel) . '</td>';
        $body .= '<td>' . h((string)($traceRow['box_count'] ?? '0')) . '</td>';
        $body .= '<td>' . h((string)($traceRow['pallet_count'] ?? '0')) . '</td>';
        $body .= '<td><a class="btn secondary" href="/rolls/' . (int)$traceRow['id'] . '">Ver</a></td>';
        $body .= '</tr>';
    }
    if ($recentTraceability === []) {
        $body .= '<tr><td colspan="7" class="muted">Aún no hay bobinas de salida generadas.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    render('ERP', $body);
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
    $isWeightMode = true;
    $lineUnit = $lineSummary['unit'];

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
          <div style="flex:1;min-width:180px"><div class="muted">Recepción</div><div style="font-weight:800">Se contabiliza por unidades</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Ordenado contenedor</div><div style="font-weight:800">' . h(formatReceptionValue($lineSummary['ordered'], $lineUnit)) . ' ' . h($lineUnit) . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Recibido contenedor</div><div style="font-weight:800">' . h(formatReceptionValue($lineSummary['received'], $lineUnit)) . ' ' . h($lineUnit) . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Pendiente</div><div style="font-weight:800">' . h(formatReceptionValue($lineSummary['pending'], $lineUnit)) . ' ' . h($lineUnit) . '</div></div>
        </div>
      </div>';

    $body .= '<div class="card">
        <form method="post" id="receive_form" action="/import-containers/' . $containerId . '/receive">
          <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
          <input type="hidden" name="import_container_item_id" value="' . (int)$containerItemId . '">
          <input type="hidden" name="reception_mode" id="reception_mode" value="WEIGHT">
          <input type="hidden" id="received_qty" name="received_qty" value="1">
          <input type="hidden" id="server_print_enabled" value="' . ($printer->isEnabled() ? '1' : '0') . '">
          <div class="row" style="align-items:end">
            <div style="flex:1;min-width:220px">
              <label>Operador</label>
              <div class="panel" style="padding:10px 12px;font-weight:700">' . h($currentOperatorName) . '</div>
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
            <div id="weight_field_wrapper" style="flex:1;min-width:220px' . (!$isWeightMode ? ';display:none' : '') . '">
              <label id="primary_value_label">Peso real (Kg)</label>
              <input id="weight_kg" name="weight_kg" type="number" step="0.001" min="0" value="">
            </div>
            <div id="qty_field_wrapper" style="flex:1;min-width:220px">
              <label>Control</label>
              <div class="panel" style="padding:10px 12px;font-weight:700">Cada recepción registra 1 unidad vendida por el proveedor</div>
            </div>
            <div id="action_buttons_wrapper" style="flex:0 0 ' . ($isWeightMode ? '300px' : '190px') . ';display:flex;flex-direction:row;gap:8px;align-items:flex-end;justify-content:flex-end;flex-wrap:nowrap">';
    $body .= '<button class="btn secondary" type="button" id="toggle_scale"' . (!$isWeightMode ? ' style="display:none"' : '') . '>Activar balanza</button>
              <button class="btn" type="button" id="save_print"' . (!$isWeightMode ? ' style="display:none"' : '') . ' disabled>Guardar e imprimir</button>
              <button class="btn" type="submit" id="submit_primary"' . ($isWeightMode ? ' style="display:none"' : '') . '>Guardar e imprimir</button></div>
          </div>
          <div class="row" id="scale_status_row" style="margin-top:6px' . ($isWeightMode ? '' : ';display:none') . '">
            <div style="flex:1;min-width:220px"></div>
            <div style="flex:1;min-width:220px"></div>
            <div style="flex:1;min-width:220px"><div class="muted" id="scale_status">Balanza: inactiva</div></div>
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
        var weight = document.getElementById("weight_kg");
        var qty = document.getElementById("received_qty");
        var toggle = document.getElementById("toggle_scale");
        var saveBtn = document.getElementById("save_print");
        var submitPrimary = document.getElementById("submit_primary");
        var actionButtonsWrapper = document.getElementById("action_buttons_wrapper");
        var weightFieldWrapper = document.getElementById("weight_field_wrapper");
        var qtyFieldWrapper = document.getElementById("qty_field_wrapper");
        var scaleStatusRow = document.getElementById("scale_status_row");
        var scaleStatusSpacer = document.getElementById("scale_status_spacer");
        var status = document.getElementById("scale_status");
        var tbody = document.getElementById("recent_tbody");
        var preview = document.getElementById("label_preview");
        if (!form || !modeField || !weight || !qty || !toggle || !saveBtn || !status) return;

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

        function getMode() { return "WEIGHT"; }

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
          qty.value = "1";
          weight.step = "0.001";
          weight.required = true;
          if (weightFieldWrapper) weightFieldWrapper.style.display = "";
          if (qtyFieldWrapper) qtyFieldWrapper.style.display = "";
          if (toggle) toggle.style.display = "";
          if (saveBtn) saveBtn.style.display = "";
          if (submitPrimary) submitPrimary.style.display = "none";
          if (scaleStatusRow) scaleStatusRow.style.display = "";
          if (actionButtonsWrapper) actionButtonsWrapper.style.flexBasis = "300px";
          if (scaleStatusSpacer) scaleStatusSpacer.style.flexBasis = "300px";
          if (!active) {
            setStatus(canManualWeightSave() ? "Listo para guardar e imprimir 1 unidad" : "Ingresa peso manual o activa balanza");
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
        modeField.value = "WEIGHT";
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
    $isWeightMode = true;
    $lineUnit = $lineSummary['unit'];

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Recepcionar producto</div>
        <a class="btn secondary" href="/purchase-orders/' . (int)$poId . '">Volver</a>
      </div>';

    $body .= '<div class="card" style="margin-bottom:12px">
        <div class="row">
          <div style="flex:1;min-width:260px"><div class="muted">OC</div><div style="font-weight:800">' . h((string)$line['po_code']) . '</div></div>
          <div style="flex:1;min-width:260px"><div class="muted">Proveedor</div><div style="font-weight:800">' . h((string)$line['supplier_name']) . '</div></div>
          <div style="flex:1;min-width:260px"><div class="muted">SKU</div><div style="font-weight:800">' . h((string)$line['sku_code']) . ' - ' . h((string)$line['sku_description']) . '</div></div>
          <div style="flex:1;min-width:220px"><div class="muted">Recepción</div><div style="font-weight:800">Se contabiliza por unidades</div></div>
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
          <input type="hidden" name="reception_mode" id="reception_mode" value="WEIGHT">
          <input type="hidden" id="received_qty" name="received_qty" value="1">
          <input type="hidden" id="server_print_enabled" value="' . ($printer->isEnabled() ? '1' : '0') . '">
          <div class="row" style="align-items:end">
            <div style="flex:1;min-width:220px">
              <label>Operador</label>
              <div class="panel" style="padding:10px 12px;font-weight:700">' . h($currentOperatorName) . '</div>
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
            <div id="weight_field_wrapper" style="flex:1;min-width:220px' . (!$isWeightMode ? ';display:none' : '') . '">
              <label id="primary_value_label">Peso real (Kg)</label>
              <input id="weight_kg" name="weight_kg" type="number" step="0.001" min="0" value="">
            </div>
            <div id="qty_field_wrapper" style="flex:1;min-width:220px">
              <label>Control</label>
              <div class="panel" style="padding:10px 12px;font-weight:700">Cada recepción registra 1 unidad vendida por el proveedor</div>
            </div>
            <div id="action_buttons_wrapper" style="flex:0 0 ' . ($isWeightMode ? '300px' : '190px') . ';display:flex;flex-direction:row;gap:8px;align-items:flex-end;justify-content:flex-end;flex-wrap:nowrap">';
    $body .= '<button class="btn secondary" type="button" id="toggle_scale"' . (!$isWeightMode ? ' style="display:none"' : '') . '>Activar balanza</button>
              <button class="btn" type="button" id="save_print"' . (!$isWeightMode ? ' style="display:none"' : '') . ' disabled>Guardar e imprimir</button>
              <button class="btn" type="submit" id="submit_primary"' . ($isWeightMode ? ' style="display:none"' : '') . '>Guardar e imprimir</button></div>
          </div>
          <div class="row" id="scale_status_row" style="margin-top:6px' . ($isWeightMode ? '' : ';display:none') . '">
            <div style="flex:1;min-width:220px"></div>
            <div style="flex:1;min-width:220px"></div>
            <div style="flex:1;min-width:220px"><div class="muted" id="scale_status">Balanza: inactiva</div></div>
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
        var weight = document.getElementById("weight_kg");
        var qty = document.getElementById("received_qty");
        var toggle = document.getElementById("toggle_scale");
        var saveBtn = document.getElementById("save_print");
        var submitPrimary = document.getElementById("submit_primary");
        var actionButtonsWrapper = document.getElementById("action_buttons_wrapper");
        var weightFieldWrapper = document.getElementById("weight_field_wrapper");
        var qtyFieldWrapper = document.getElementById("qty_field_wrapper");
        var scaleStatusRow = document.getElementById("scale_status_row");
        var scaleStatusSpacer = document.getElementById("scale_status_spacer");
        var status = document.getElementById("scale_status");
        var tbody = document.getElementById("recent_tbody");
        var preview = document.getElementById("label_preview");
        if (!form || !modeField || !weight || !qty || !toggle || !saveBtn || !status) return;

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

        function getMode() { return "WEIGHT"; }

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
          qty.value = "1";
          weight.step = "0.001";
          weight.required = true;
          if (weightFieldWrapper) weightFieldWrapper.style.display = "";
          if (qtyFieldWrapper) qtyFieldWrapper.style.display = "";
          if (toggle) toggle.style.display = "";
          if (saveBtn) saveBtn.style.display = "";
          if (submitPrimary) submitPrimary.style.display = "none";
          if (scaleStatusRow) scaleStatusRow.style.display = "";
          if (actionButtonsWrapper) actionButtonsWrapper.style.flexBasis = "300px";
          if (scaleStatusSpacer) scaleStatusSpacer.style.flexBasis = "300px";
          if (!active) {
            setStatus(canManualWeightSave() ? "Listo para guardar e imprimir 1 unidad" : "Ingresa peso manual o activa balanza");
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
        modeField.value = "WEIGHT";
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
        $body .= '<div class="card"><table><thead><tr><th>ID</th><th>OT</th><th>SKU final</th><th>Cantidad</th><th>Fecha</th><th></th></tr></thead><tbody>';
        foreach ($wos as $wo) {
            $body .= '<tr>';
            $body .= '<td>' . (int)$wo['id'] . '</td>';
            $body .= '<td>' . h((string)$wo['ot_code']) . '</td>';
            $body .= '<td>' . h((string)$wo['sku_final']) . '</td>';
            $body .= '<td>' . h((string)($wo['target_qty'] ?? '')) . '</td>';
            $body .= '<td>' . h((string)$wo['created_at']) . '</td>';
            $body .= '<td>' . ($erpReadOnly
                ? '<a class="btn secondary" href="/work-orders/' . (int)$wo['id'] . '/start">Ver seguimiento</a>'
                : '<form method="post" action="/work-orders/' . (int)$wo['id'] . '/activate" style="margin:0"><input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><button class="btn secondary" type="submit">Activar</button></form>')
                . '</td>';
            $body .= '</tr>';
        }
        if ($wos === []) {
            $body .= '<tr><td colspan="6" class="muted">Sin OTs pendientes.</td></tr>';
        }
        $body .= '</tbody></table></div>';
    } elseif ($view === 'active') {
        $body .= '<div class="card"><table><thead><tr><th>ID</th><th>OT</th><th>SKU final</th><th>Etapa</th><th>Operador</th><th>Referencia</th><th>Detalle</th><th></th><th></th></tr></thead><tbody>';
        foreach ($wos as $wo) {
            $body .= '<tr>';
            $body .= '<td>' . (int)$wo['id'] . '</td>';
            $body .= '<td>' . h((string)$wo['ot_code']) . '</td>';
            $body .= '<td>' . h((string)$wo['sku_final']) . '</td>';
            $body .= '<td>' . h((string)($wo['status_label'] ?? '-')) . '</td>';
            $body .= '<td>' . h((string)$wo['operator_label']) . '</td>';
            $body .= '<td>' . h((string)$wo['current_roll_code']) . '</td>';
            $body .= '<td>' . h((string)$wo['current_chemical_label']) . '</td>';
            $body .= '<td><a class="btn secondary" href="/work-orders/' . (int)$wo['id'] . '/start">' . ($erpReadOnly ? 'Ver seguimiento' : 'Ver OT') . '</a></td>';
            $body .= '<td><a class="btn secondary" href="/work-orders/' . (int)$wo['id'] . '/traceability">Trazabilidad</a></td>';
            $body .= '</tr>';
        }
        if ($wos === []) {
            $body .= '<tr><td colspan="9" class="muted">Sin OTs en producción o corte.</td></tr>';
        }
        $body .= '</tbody></table></div>';
    } else {
        $body .= '<div class="card"><table><thead><tr><th>ID</th><th>OT</th><th>SKU final</th><th>Operador final</th><th>Fecha fabricación</th><th>Cajas</th><th></th><th></th></tr></thead><tbody>';
        foreach ($wos as $wo) {
            $body .= '<tr>';
            $body .= '<td>' . (int)$wo['id'] . '</td>';
            $body .= '<td>' . h((string)$wo['ot_code']) . '</td>';
            $body .= '<td>' . h((string)$wo['sku_final']) . '</td>';
            $body .= '<td>' . h((string)$wo['operator_label']) . '</td>';
            $body .= '<td>' . h((string)($wo['finished_at'] !== '' ? $wo['finished_at'] : '-')) . '</td>';
            $body .= '<td>' . h((string)($wo['box_qty'] !== '' ? $wo['box_qty'] : '-')) . '</td>';
            $body .= '<td><a class="btn secondary" href="/work-orders/' . (int)$wo['id'] . '/start">' . ($erpReadOnly ? 'Ver seguimiento' : 'Ver OT') . '</a></td>';
            $body .= '<td><a class="btn secondary" href="/work-orders/' . (int)$wo['id'] . '/traceability">Trazabilidad</a></td>';
            $body .= '</tr>';
        }
        if ($wos === []) {
            $body .= '<tr><td colspan="8" class="muted">Sin OTs finalizadas.</td></tr>';
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
    $cutWarehouses = array_values(array_filter($service->listWarehouses(), static fn(array $warehouse): bool => in_array((int)$warehouse['code'], [700, 1000], true)));
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
    }
    renderWorkOrderStartScreen($ot, $chemicals, $currentRoll, $rollHistory, $chemicalInputs, $lastStart, $lastFinish, $currentOperatorName, $flashMessage, $flashIsError, [], $materialRequests, $wastes, $boxes, $pallets, $outputRoll, $availableMaterialRolls, $cutWarehouses);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/material-request$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
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
        header('Location: /work-orders/' . $workOrderId . '/start?material_requested=1');
        exit;
    }

    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        header('Location: /work-orders');
        exit;
    }

    $formState = $_POST;
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
    $wastes = $service->listProductionWastesByWorkOrder($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    $cutWarehouses = $service->listWarehousesForCut();
    $firstError = (string)reset($result['errors']);

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
    $service->deliverMaterialRequest((int)($_POST['request_id'] ?? 0), null, $currentOperatorName);
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

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Trazabilidad OT ' . h((string)$ot['ot_code']) . '</div>
          <div class="muted">Seguimiento completo de bobinas, tintas y eventos de la orden de trabajo.</div>
        </div>
        <div class="row">
          <a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/start">Volver a OT</a>
          <a class="btn secondary" href="/work-orders">Volver a listado</a>
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
            $body .= '<td><a href="/rolls/' . $parentRollId . '">' . h((string)($outputRoll['parent_roll_code'] ?? ('#' . $parentRollId))) . '</a></td>';
        } else {
            $body .= '<td>-</td>';
        }
        $body .= '<td><a href="/rolls/' . (int)$outputRoll['id'] . '">' . h((string)$outputRoll['roll_code']) . '</a></td>';
        $body .= '<td>' . h($isCut ? 'Cortada' : 'Pendiente') . '</td>';
        $body .= '<td>' . h((string)($outputRoll['box_count'] ?? '0')) . '</td>';
        $body .= '<td>' . h((string)($outputRoll['pallet_count'] ?? '0')) . '</td>';
        $body .= '<td><a class="btn secondary" href="/rolls/' . (int)$outputRoll['id'] . '">Ver bobina</a></td>';
        $body .= '</tr>';
    }
    if ($outputRolls === []) {
        foreach ($inputRolls as $inputRoll) {
            $body .= '<tr>';
            $body .= '<td><a href="/rolls/' . (int)$inputRoll['roll_id'] . '">' . h((string)$inputRoll['roll_code']) . '</a></td>';
            $body .= '<td class="muted">Pendiente de generar</td>';
            $body .= '<td>Pendiente</td>';
            $body .= '<td>0</td>';
            $body .= '<td>0</td>';
            $body .= '<td><a class="btn secondary" href="/rolls/' . (int)$inputRoll['roll_id'] . '">Ver bobina</a></td>';
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
            $origin = '-';
            if ((string)($roll['po_code'] ?? '') !== '' || (string)($roll['supplier_name'] ?? '') !== '') {
                $origin = trim((string)($roll['po_code'] ?? '-') . ' · ' . (string)($roll['supplier_name'] ?? '-'));
            } elseif ((string)($roll['warehouse_code'] ?? '') !== '') {
                $origin = 'Bodega ' . (string)$roll['warehouse_code'];
            }

            $body .= '<div class="trace-roll-card">';
            $body .= '<div class="trace-roll-header">';
            $body .= '<div>';
            $body .= '<div class="trace-roll-title">' . h((string)$roll['roll_code']) . '</div>';
            $body .= '<div class="muted trace-roll-subtitle">Código SKU ' . h((string)$roll['sku_code']) . ' · ID ' . (int)$roll['roll_id'] . '</div>';
            $body .= '</div>';
            $body .= '<div><a class="trace-roll-link" href="/rolls/' . (int)$roll['roll_id'] . '">Ver trazabilidad bobina</a></div>';
            $body .= '</div>';

            $body .= '<div class="trace-roll-meta">';
            $body .= '<div class="trace-roll-stat"><div class="muted">Origen</div><div class="value">' . h($origin) . '</div></div>';
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
        $body .= '<td><a href="/boxes/' . (int)$box['id'] . '">' . h((string)$box['box_code']) . '</a></td>';
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
        $body .= '<td><a href="/pallets/' . (int)$pallet['id'] . '">' . h((string)$pallet['pallet_code']) . '</a></td>';
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
                'show_finish_data' => '1',
                'finish_final_roll_weight_kg' => (string)($_POST['finish_final_roll_weight_kg'] ?? ''),
                'finish_final_chemical_weight_kg' => (string)($_POST['finish_final_chemical_weight_kg'] ?? ''),
                'finish_box_qty' => (string)($_POST['finish_box_qty'] ?? ''),
                'finish_output_roll_weight_kg' => (string)($_POST['finish_output_roll_weight_kg'] ?? ''),
                'finish_waste_kg' => (string)($_POST['finish_waste_kg'] ?? '0'),
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
    $boxPrinted = '0';
    $labelRollId = (int)($result['output_roll_id'] ?? 0);
    if ($labelRollId > 0 && $printer->isEnabled()) {
        $roll = $service->getRoll($labelRollId);
        if (is_array($roll)) {
            $p = $printer->printRollLabel($roll);
            if (($p['ok'] ?? false) === true) {
                $printed = '1';
            }
        }
        $box = $printer->printWorkOrderBoxLabel($ot, [
            'created_at' => date('Y-m-d H:i:s'),
            'box_qty' => (int)($_POST['finish_box_qty'] ?? 0),
            'operator_name' => $currentOperatorName,
        ]);
        if (($box['ok'] ?? false) === true) {
            $boxPrinted = '1';
        }
    }
    $qs = '?finished=1&printed=' . $printed . '&box_printed=' . $boxPrinted;
    if ($labelRollId > 0) {
        $qs .= '&label_roll_id=' . $labelRollId;
    }
    header('Location: /work-orders/' . $workOrderId . '/start' . $qs);
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

if ($path === '/stock' && $method === 'GET') {
    $summary = $service->stockSummary();
    $code = isset($_GET['bodega']) ? (int)$_GET['bodega'] : 100;
    $selectedSku = trim((string)($_GET['sku'] ?? ''));
    $rolls = $service->listRollsByWarehouseCode($code);
    $warehousePallets = $service->listPalletsByWarehouseCode($code);
    $chemicalWeighings = $service->listRecentChemicalWeighings(12);
    $selectedWarehouseName = '';
    $selectedWarehouseStockUnits = 0.0;
    $selectedWarehousePalletsCount = 0;
    foreach ($summary as $summaryRow) {
        if ((int)($summaryRow['warehouse_code'] ?? 0) === $code) {
            $selectedWarehouseName = trim((string)($summaryRow['warehouse_name'] ?? ''));
            $selectedWarehouseStockUnits = (float)($summaryRow['stock_units_total'] ?? 0);
            $selectedWarehousePalletsCount = (int)($summaryRow['pallets_count'] ?? 0);
            break;
        }
    }
    $selectedWarehouseLabel = (string)$code;
    $skuSummary = [];
    foreach ($rolls as $roll) {
        $skuCode = trim((string)($roll['sku_code'] ?? ''));
        if ($skuCode === '') {
            $skuCode = 'SIN-SKU';
        }
        if (!isset($skuSummary[$skuCode])) {
            $skuSummary[$skuCode] = [
                'summary_key' => $skuCode,
                'sku_code' => $skuCode,
                'sku_description' => trim((string)($roll['sku_description'] ?? '')),
                'count' => 0,
                'total_weight_kg' => 0.0,
                'rolls' => [],
                'pallets' => [],
            ];
        }
        $skuSummary[$skuCode]['count']++;
        $skuSummary[$skuCode]['total_weight_kg'] += (float)($roll['weight_kg'] ?? 0);
        $skuSummary[$skuCode]['rolls'][] = $roll;
    }
    foreach ($warehousePallets as $palletRow) {
        $specification = trim((string)($palletRow['final_sku'] ?? ''));
        if ($specification === '') {
            $specification = 'SIN-ESPECIFICACION';
        }
        $summaryKey = 'PALLET|' . $specification;
        if (!isset($skuSummary[$summaryKey])) {
            $skuSummary[$summaryKey] = [
                'summary_key' => $summaryKey,
                'sku_code' => 'PALLET',
                'sku_description' => $specification,
                'count' => 0,
                'total_weight_kg' => 0.0,
                'rolls' => [],
                'pallets' => [],
            ];
        }
        $skuSummary[$summaryKey]['count']++;
        $skuSummary[$summaryKey]['pallets'][] = $palletRow;
    }
    ksort($skuSummary);
    $selectedSkuRolls = $selectedSku !== '' && isset($skuSummary[$selectedSku])
        ? $skuSummary[$selectedSku]['rolls']
        : [];
    $selectedSkuPallets = $selectedSku !== '' && isset($skuSummary[$selectedSku])
        ? ($skuSummary[$selectedSku]['pallets'] ?? [])
        : [];
    $selectedSkuInfo = $selectedSku !== '' && isset($skuSummary[$selectedSku])
        ? $skuSummary[$selectedSku]
        : null;

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Inventario por bodega</div>
        </div>
        <a class="btn secondary" href="/">Volver</a>
      </div>';

    $body .= '<div class="card" style="margin-bottom:12px">
        <div class="row">
          <div style="flex:1;min-width:220px"><div class="muted">Bodega seleccionada</div><div style="font-weight:800">' . h($selectedWarehouseLabel) . '</div></div>
          <div style="flex:2;min-width:280px"><div class="muted">Nombre bodega</div><div style="font-weight:800">' . h($selectedWarehouseName !== '' ? $selectedWarehouseName : '-') . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Especificaciones</div><div style="font-weight:800">' . count($skuSummary) . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Unidades disponibles</div><div style="font-weight:800">' . h(number_format($selectedWarehouseStockUnits, 0, ',', '.')) . ' Unid.</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Pallets almacenados</div><div style="font-weight:800">' . h((string)$selectedWarehousePalletsCount) . '</div></div>
        </div>
      </div>';

    $body .= '<div class="grid">
        <div class="card">
          <div style="font-weight:800;margin-bottom:8px">Bodegas</div>
          <table><thead><tr><th>Bodega</th><th>Nombre bodega</th><th>Unidades</th><th></th></tr></thead><tbody>';
    foreach ($summary as $s) {
        $selected = ((int)$s['warehouse_code'] === $code) ? ' style="font-weight:800"' : '';
        $warehouseName = trim((string)($s['warehouse_name'] ?? ''));
        $body .= '<tr' . $selected . '>';
        $body .= '<td>' . h((string)$s['warehouse_code']) . '</td>';
        $body .= '<td>' . h($warehouseName !== '' ? $warehouseName : '-') . '</td>';
        $body .= '<td>' . h(number_format((float)($s['stock_units_total'] ?? 0), 0, ',', '.')) . '</td>';
        $body .= '<td><a class="btn secondary" href="/stock?bodega=' . (int)$s['warehouse_code'] . '">Ver</a></td>';
        $body .= '</tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= '<div class="card">
        <div class="row" style="justify-content:space-between;align-items:center;margin-bottom:8px">
          <div style="font-weight:800">Productos por especificación</div>
          <div class="muted">Haz clic en un producto para ver sus bobinas y pallets con el mismo detalle del inventario.</div>
        </div>
        <table><thead><tr>
            <th>Especificación</th><th>Código SKU</th><th>Disponibles</th><th>Peso total (Kg)</th><th></th>
          </tr></thead><tbody>';
    foreach ($skuSummary as $skuRow) {
        $skuLink = '/stock?bodega=' . $code . '&sku=' . rawurlencode((string)($skuRow['summary_key'] ?? $skuRow['sku_code']));
        $detailLabel = ($skuRow['pallets'] ?? []) !== [] && ($skuRow['rolls'] ?? []) === [] ? 'Ver pallets' : 'Ver detalle';
        $body .= '<tr>';
        $body .= '<td><a href="' . h($skuLink) . '">' . h((string)($skuRow['sku_description'] !== '' ? $skuRow['sku_description'] : '-')) . '</a></td>';
        $body .= '<td>' . h((string)$skuRow['sku_code']) . '</td>';
        $body .= '<td>' . h((string)$skuRow['count']) . '</td>';
        $body .= '<td>' . h(number_format((float)$skuRow['total_weight_kg'], 3, '.', '')) . '</td>';
        $body .= '<td><a class="btn secondary" href="' . h($skuLink) . '">' . h($detailLabel) . '</a></td>';
        $body .= '</tr>';
    }
    if ($skuSummary === []) {
        $body .= '<tr><td colspan="5" class="muted">Sin productos disponibles en esta bodega.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= '</div>';

    if ($selectedSkuInfo !== null) {
        $closeLink = '/stock?bodega=' . $code;
        $body .= '<div id="stock_ids_modal" style="position:fixed;inset:0;z-index:9998;padding:40px 20px;overflow:auto">
            <a href="' . h($closeLink) . '" aria-label="Cerrar ventana" style="position:fixed;inset:0;background:rgba(15,23,42,.45);display:block"></a>
            <div class="card" style="width:min(1100px,100%);margin:0 auto;position:relative;z-index:9999">
              <div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
                <div>
                  <div style="font-size:18px;font-weight:700">Disponibles por especificación</div>
                  <div class="muted">Código SKU ' . h((string)$selectedSkuInfo['sku_code']) . ' · Especificación '
                    . h((string)($selectedSkuInfo['sku_description'] !== '' ? $selectedSkuInfo['sku_description'] : '-')) . '</div>
                </div>
                <a class="btn secondary" href="' . h($closeLink) . '">Cerrar</a>
              </div>
              <div style="font-weight:800;margin-bottom:8px">Bobinas disponibles</div>
              <table><thead><tr>
                  <th>ID</th><th>Código</th><th>Recibió</th><th>OT activa</th><th>Peso (Kg)</th><th>Estado</th><th></th>
                </tr></thead><tbody>';
        foreach ($selectedSkuRolls as $r) {
            $body .= '<tr>';
            $body .= '<td><a href="/rolls/' . (int)$r['id'] . '">' . (int)$r['id'] . '</a></td>';
            $body .= '<td>' . h((string)$r['roll_code']) . '</td>';
            $body .= '<td>' . h((string)($r['received_by'] ?? '-')) . '</td>';
            $body .= '<td>' . h((string)($r['work_order_code'] ?? '-')) . '</td>';
            $body .= '<td>' . h((string)$r['weight_kg']) . '</td>';
            $body .= '<td>' . h(rollStatusLabel((string)$r['status'])) . '</td>';
            $body .= '<td><a class="btn secondary" href="/rolls/' . (int)$r['id'] . '">Trazabilidad</a></td>';
            $body .= '</tr>';
        }
        if ($selectedSkuRolls === []) {
            $body .= '<tr><td colspan="7" class="muted">No hay IDs disponibles para este producto.</td></tr>';
        }
        $body .= '</tbody></table>';
        $body .= '<div style="font-weight:800;margin:16px 0 8px">Pallets almacenados</div>';
        $body .= '<table><thead><tr><th>Pallet</th><th>OT</th><th>Bobina origen</th><th>SKU final</th><th>Cajas</th><th>Unidades</th><th></th></tr></thead><tbody>';
        foreach ($selectedSkuPallets as $palletRow) {
            $body .= '<tr>';
            $body .= '<td>' . h((string)$palletRow['pallet_code']) . '</td>';
            $body .= '<td>' . h((string)($palletRow['ot_code'] ?? '-')) . '</td>';
            $body .= '<td>' . h((string)($palletRow['source_roll_code'] ?? '-')) . '</td>';
            $body .= '<td>' . h((string)($palletRow['final_sku'] ?? '-')) . '</td>';
            $body .= '<td>' . h((string)($palletRow['box_count'] ?? '0')) . '</td>';
            $body .= '<td>' . h(number_format((float)($palletRow['units_total'] ?? 0), 3, '.', '')) . '</td>';
            $body .= '<td><a class="btn secondary" href="/pallets/' . (int)$palletRow['id'] . '">Ver pallet</a></td>';
            $body .= '</tr>';
        }
        if ($selectedSkuPallets === []) {
            $body .= '<tr><td colspan="7" class="muted">No hay pallets almacenados para esta especificación.</td></tr>';
        }
        $body .= '</tbody></table></div></div>';
        $body .= '<script>
          (function () {
            var modal = document.getElementById("stock_ids_modal");
            if (!modal) return;
            document.addEventListener("keydown", function (event) {
              if (event.key === "Escape") {
                window.location.href = ' . json_encode($closeLink, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';
              }
            });
          })();
        </script>';
    }

    render('Stock', $body);
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
        <a class="btn secondary" href="/stock">Volver a inventario</a>
      </div>';

    $body .= '<div class="card">
        <table><thead><tr><th>Fecha</th><th>Solicita</th><th>Qué solicita</th><th>Cantidad</th><th>Entregadas</th><th>OT</th><th>Estado</th><th>Última entrega</th><th>Acción</th></tr></thead><tbody>';
    foreach ($requests as $request) {
        $lastDelivered = trim((string)($request['delivered_roll_code'] ?? ''));
        $body .= '<tr>';
        $body .= '<td>' . h((string)$request['created_at']) . '</td>';
        $body .= '<td>' . h((string)$request['requested_by']) . '</td>';
        $body .= '<td>' . h((string)$request['requested_item']) . '</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($request['requested_qty'] ?? 0), 'Unid.')) . '</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($request['delivered_qty'] ?? 0), 'Unid.')) . '</td>';
        $body .= '<td><a href="/work-orders/' . (int)$request['work_order_id'] . '/start">' . h((string)$request['ot_code']) . '</a></td>';
        $body .= '<td>' . h(materialRequestStatusLabel((string)$request['status'])) . '</td>';
        $body .= '<td>' . h($lastDelivered !== '' ? ($lastDelivered . ' · ' . (string)($request['delivered_by'] ?? '-')) : '-') . '</td>';
        $body .= '<td><a class="btn secondary" href="/stock/material-requests/' . (int)$request['id'] . '">Atender</a></td>';
        $body .= '</tr>';
    }
    if ($requests === []) {
        $body .= '<tr><td colspan="9" class="muted">No hay solicitudes registradas todavía.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    render('Solicitudes', $body);
    exit;
}

if (preg_match('#^/stock/material-requests/(\d+)/accept$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $requestId = (int)$m[1];
    $result = $service->acceptMaterialRequest($requestId, $currentOperatorName);
    $query = (($result['ok'] ?? false) === true)
        ? '?ok=' . rawurlencode('Solicitud aceptada por bodega.')
        : '?error=' . rawurlencode((string)reset($result['errors']));
    header('Location: /stock/material-requests/' . $requestId . $query);
    exit;
}

if (preg_match('#^/stock/material-requests/(\d+)/scan$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $requestId = (int)$m[1];
    $scanCode = trim((string)($_POST['scan_code'] ?? ''));
    if ($scanCode === '') {
        header('Location: /stock/material-requests/' . $requestId . '?error=' . rawurlencode('Debes escanear el código de barras de la bobina.'));
        exit;
    }

    $roll = $service->getRollByScanCode($scanCode);
    if ($roll === null) {
        header('Location: /stock/material-requests/' . $requestId . '?error=' . rawurlencode('No se encontró una bobina con ese código.'));
        exit;
    }

    $result = $service->deliverMaterialRequest($requestId, (int)$roll['id'], $currentOperatorName);
    $query = (($result['ok'] ?? false) === true)
        ? '?ok=' . rawurlencode('Bobina ingresada correctamente a la solicitud.')
        : '?error=' . rawurlencode((string)reset($result['errors']));
    header('Location: /stock/material-requests/' . $requestId . $query);
    exit;
}

if (preg_match('#^/stock/material-requests/(\d+)/deliver-manual$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $requestId = (int)$m[1];
    $result = $service->deliverGenericMaterialRequest(
        $requestId,
        (float)($_POST['delivered_qty'] ?? 0),
        (string)($_POST['delivery_note'] ?? ''),
        $currentOperatorName
    );
    $query = (($result['ok'] ?? false) === true)
        ? '?ok=' . rawurlencode('Material entregado correctamente.')
        : '?error=' . rawurlencode((string)reset($result['errors']));
    header('Location: /stock/material-requests/' . $requestId . $query);
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

    if ((string)$request['status'] === 'PENDING') {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Aceptar solicitud</div>
          <div class="muted" style="margin-bottom:10px">Bodega toma la solicitud antes de comenzar a escanear las bobinas entregadas.</div>
          <form method="post" action="/stock/material-requests/' . $requestId . '/accept">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <button class="btn" type="submit">Aceptar solicitud</button>
          </form>
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

    if ($pendingQty > 0 && in_array((string)$request['status'], ['ACCEPTED', 'PARTIAL'], true) && (string)($request['request_type'] ?? 'ROLL') === 'ROLL') {
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

    if ($pendingQty > 0 && in_array((string)$request['status'], ['ACCEPTED', 'PARTIAL'], true) && (string)($request['request_type'] ?? 'ROLL') !== 'ROLL') {
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
            $body .= '<td><a href="/rolls/' . (int)$delivery['roll_id'] . '/traceability">' . h((string)$delivery['roll_code']) . '</a></td>';
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
        </div>';

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
                  <option value="warehouse">Traspaso a bodega</option>';
        if ($transferWorkOrders !== []) {
            $body .= '<option value="work_order">Traspaso a OT</option>';
        }
        $body .= '</select>
              </div>
            </div>
            <div class="row" style="margin-top:10px" id="transfer_warehouse_wrap">
              <div style="flex:1;min-width:260px">
                <label>Bodega destino</label>
                <select name="to_warehouse_id">
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
                <select name="work_order_id">
                  <option value="">Seleccionar</option>';
            foreach ($transferWorkOrders as $wo) {
                $statusLabel = (string)$wo['status'] === 'ACTIVE' ? ' [ACTIVA]' : '';
                $body .= '<option value="' . (int)$wo['id'] . '">' . h((string)$wo['ot_code']) . ' - ' . h((string)$wo['sku_final']) . $statusLabel . '</option>';
            }
            $body .= '</select>
              </div>';
        }
        $body .= '<div style="margin-top:12px" class="row">
              <button class="btn" type="submit">Confirmar traspaso</button>
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
                  <option value="warehouse"' . (((string)($_POST['transfer_mode'] ?? 'warehouse') === 'warehouse') ? ' selected' : '') . '>Traspaso a bodega</option>';
        if ($transferWorkOrders !== []) {
            $body .= '<option value="work_order"' . (((string)($_POST['transfer_mode'] ?? '') === 'work_order') ? ' selected' : '') . '>Traspaso a OT</option>';
        }
        $body .= '</select>
              </div>
            </div>
            <div class="row" style="margin-top:10px" id="transfer_warehouse_wrap">
              <div style="flex:1;min-width:260px">
                <label>Bodega destino</label>
                <select name="to_warehouse_id">
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
                <select name="work_order_id">
                  <option value="">Seleccionar</option>';
            foreach ($transferWorkOrders as $wo) {
                $selected = (int)$wo['id'] === $selectedWorkOrderId ? ' selected' : '';
                $statusLabel = (string)$wo['status'] === 'ACTIVE' ? ' [ACTIVA]' : '';
                $body .= '<option value="' . (int)$wo['id'] . '"' . $selected . '>' . h((string)$wo['ot_code']) . ' - ' . h((string)$wo['sku_final']) . $statusLabel . '</option>';
            }
            $body .= '</select>
              </div>';
        }
        $body .= '<div style="margin-top:12px"><button class="btn" type="submit">Confirmar traspaso</button></div>
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

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Recepción #' . (int)$roll['id'] . '</div>
        <div class="row">
          <a class="btn secondary" href="/stock?bodega=' . (int)$roll['warehouse_code'] . '">Volver</a>
          <a class="btn secondary" href="/rolls/' . (int)$roll['id'] . '/transfer">Transferir</a>
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
          <div style="flex:1;min-width:240px"><div class="muted">OT activa</div><div style="font-weight:700">' . h((string)($roll['work_order_code'] ?? '-')) . '</div></div>
        </div>
      </div>';

    $body .= '<div class="card" style="margin-top:12px">
        <div style="font-weight:800;margin-bottom:8px">Cadena de fabricación</div>
        <div class="row">
          <div style="flex:1;min-width:220px"><div class="muted">OT origen</div><div style="font-weight:700">';
    if (is_array($sourceWorkOrder)) {
        $body .= '<a href="/work-orders/' . (int)$sourceWorkOrder['id'] . '/traceability">' . h((string)$sourceWorkOrder['ot_code']) . '</a>';
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
    foreach ($traceability as $t) {
        $payload = $t['payload_data'] ?? [];
        $operatorName = is_array($payload) ? (string)($payload['operator_name'] ?? '-') : '-';
        $workOrderLabel = '-';
        if (is_array($payload) && isset($payload['work_order_id']) && (int)$payload['work_order_id'] > 0) {
            $workOrderLabel = 'OT #' . (int)$payload['work_order_id'];
        }
        $detail = [];
        if (is_array($payload) && isset($payload['weight_kg'])) { $detail[] = 'Peso ' . h((string)$payload['weight_kg']) . ' Kg'; }
        if (is_array($payload) && isset($payload['received_qty'])) { $detail[] = 'Cantidad ' . h(formatReceptionValue((float)$payload['received_qty'], 'Unid.')); }
        if (is_array($payload) && isset($payload['microns'])) { $detail[] = 'Gramos ' . h((string)$payload['microns']); }
        if (is_array($payload) && isset($payload['width_mm'])) { $detail[] = 'Ancho ' . h((string)$payload['width_mm']) . ' mm'; }
        if (is_array($payload) && isset($payload['color']) && (string)$payload['color'] !== '') { $detail[] = 'Color ' . h((string)$payload['color']); }
        if (is_array($payload) && isset($payload['meters'])) { $detail[] = 'Metros ' . h((string)$payload['meters']); }
        $body .= '<tr>';
        $body .= '<td>' . h((string)$t['created_at']) . '</td>';
        $body .= '<td>' . h((string)$t['movement_type']) . '</td>';
        $body .= '<td>' . h((string)($t['from_warehouse_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($t['to_warehouse_code'] ?? '-')) . '</td>';
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

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Transferir bobina</div>
        <a class="btn secondary" href="/rolls/' . (int)$roll['id'] . '">Volver</a>
      </div>';

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
            <option value="warehouse">Traspaso a bodega</option>';
    if ($transferWorkOrders !== []) {
        $body .= '<option value="work_order">Traspaso a OT</option>';
    }
    $body .= '</select>
          <div id="transfer_warehouse_wrap" style="margin-top:10px">
          <label>Bodega destino</label>
          <select name="to_warehouse_id">
            <option value=\"\">Seleccionar</option>';
    foreach ($warehouses as $w) {
        $disabled = ((int)$w['code'] === (int)$roll['warehouse_code']) ? ' disabled' : '';
        $body .= '<option value="' . (int)$w['id'] . '"' . $disabled . '>' . h((string)$w['code']) . ' - ' . h((string)$w['name']) . '</option>';
    }
    $body .= '</select></div>';
    if ($transferWorkOrders !== []) {
        $body .= '<div id="transfer_ot_wrap" style="margin-top:10px;display:none">
            <label>OT destino</label>
            <select name="work_order_id">
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
            <button class="btn" type="submit">Confirmar transferencia</button>
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
            <option value="warehouse"' . (((string)($_POST['transfer_mode'] ?? 'warehouse') === 'warehouse') ? ' selected' : '') . '>Traspaso a bodega</option>';
    if ($transferWorkOrders !== []) {
        $body .= '<option value="work_order"' . (((string)($_POST['transfer_mode'] ?? '') === 'work_order') ? ' selected' : '') . '>Traspaso a OT</option>';
    }
    $body .= '</select>
          <div id="transfer_warehouse_wrap" style="margin-top:10px">
          <label>Bodega destino</label>
          <select name="to_warehouse_id">
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
            <select name="work_order_id">
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
            <button class="btn" type="submit">Confirmar transferencia</button>
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

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Caja ' . h((string)$box['box_code']) . '</div>
        <div class="row"><a class="btn secondary" href="/cut">Volver</a><a class="btn secondary" href="/rolls/' . (int)$box['source_roll_id'] . '">Bobina origen</a><a class="btn" href="/boxes/' . (int)$box['id'] . '/label?auto_print=1" target="_blank" rel="noopener">Etiqueta</a><a class="btn secondary" href="/boxes/' . (int)$box['id'] . '/label?server_print=1" target="_blank" rel="noopener">Imprimir Zebra</a></div>
      </div>';
    $body .= '<div class="card"><div class="row">
        <div style="flex:1;min-width:180px"><div class="muted">OT</div><div style="font-weight:800">' . h((string)($box['ot_code'] ?? '-')) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Bobina origen</div><div style="font-weight:800">' . h((string)$box['source_roll_code']) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Unidades</div><div style="font-weight:800">' . h((string)$box['units_qty']) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Pallet</div><div style="font-weight:800">' . h((string)($box['pallet_code'] ?? '-')) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Operador</div><div style="font-weight:800">' . h((string)$box['operator_name']) . '</div></div>
      </div></div>';
    $body .= '<div class="card" style="margin-top:12px"><div style="font-weight:800;margin-bottom:8px">Cadena de trazabilidad</div><div class="row">';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">OT</div><div style="font-weight:700">';
    if ((int)($box['work_order_id'] ?? 0) > 0) {
        $body .= '<a href="/work-orders/' . (int)$box['work_order_id'] . '/traceability">' . h((string)($box['ot_code'] ?? ('OT #' . (int)$box['work_order_id']))) . '</a>';
    } else {
        $body .= '-';
    }
    $body .= '</div></div>';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">Bobina salida</div><div style="font-weight:700"><a href="/rolls/' . (int)$box['source_roll_id'] . '">' . h((string)$box['source_roll_code']) . '</a></div></div>';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">Bobina entrada</div><div style="font-weight:700">';
    if (is_array($boxSourceRoll) && (int)($boxSourceRoll['parent_roll_id'] ?? 0) > 0) {
        $body .= '<a href="/rolls/' . (int)$boxSourceRoll['parent_roll_id'] . '">' . h((string)($boxSourceRoll['parent_roll_code'] ?? ('#' . (int)$boxSourceRoll['parent_roll_id']))) . '</a>';
    } else {
        $body .= '-';
    }
    $body .= '</div></div>';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">Pallet</div><div style="font-weight:700">';
    if ((int)($box['pallet_id'] ?? 0) > 0) {
        $body .= '<a href="/pallets/' . (int)$box['pallet_id'] . '">' . h((string)($box['pallet_code'] ?? ('#' . (int)$box['pallet_id']))) . '</a>';
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

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Pallets pendientes de asignación</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Pallet</th><th>OT</th><th>Bobina origen</th><th>Cajas</th><th>Unidades</th><th>Bodega actual</th><th>Destino</th><th>Asignación</th><th></th></tr></thead><tbody>';
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
        $body .= '<tr><td colspan="9" class="muted">No hay pallets para los filtros seleccionados.</td></tr>';
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
    $palletWarehouses = $service->listWarehouses();
    $palletMessage = trim((string)($_GET['msg'] ?? ''));
    $palletError = trim((string)($_GET['error'] ?? ''));
    $currentWarehouseLabel = trim((string)($pallet['warehouse_code'] ?? ''));
    if ($currentWarehouseLabel === '') {
        $currentWarehouseLabel = 'Sin bodega';
    } elseif ((string)($pallet['warehouse_name'] ?? '') !== '') {
        $currentWarehouseLabel .= ' - ' . (string)$pallet['warehouse_name'];
    }

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Pallet ' . h((string)$pallet['pallet_code']) . '</div>
        <div class="row"><a class="btn secondary" href="/pallets">Volver</a><a class="btn secondary" href="/rolls/' . (int)$pallet['source_roll_id'] . '">Bobina origen</a><a class="btn" href="/pallets/' . (int)$pallet['id'] . '/label?auto_print=1" target="_blank" rel="noopener">Etiqueta</a><a class="btn secondary" href="/pallets/' . (int)$pallet['id'] . '/label?server_print=1" target="_blank" rel="noopener">Imprimir Zebra</a></div>
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
    $body .= '<div class="card" style="margin-top:12px"><div style="font-weight:800;margin-bottom:8px">Cadena de trazabilidad</div><div class="row">';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">OT</div><div style="font-weight:700">';
    if ((int)($pallet['work_order_id'] ?? 0) > 0) {
        $body .= '<a href="/work-orders/' . (int)$pallet['work_order_id'] . '/traceability">' . h((string)($pallet['ot_code'] ?? ('OT #' . (int)$pallet['work_order_id']))) . '</a>';
    } else {
        $body .= '-';
    }
    $body .= '</div></div>';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">Bobina salida</div><div style="font-weight:700"><a href="/rolls/' . (int)$pallet['source_roll_id'] . '">' . h((string)$pallet['source_roll_code']) . '</a></div></div>';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">Bobina entrada</div><div style="font-weight:700">';
    if (is_array($palletSourceRoll) && (int)($palletSourceRoll['parent_roll_id'] ?? 0) > 0) {
        $body .= '<a href="/rolls/' . (int)$palletSourceRoll['parent_roll_id'] . '">' . h((string)($palletSourceRoll['parent_roll_code'] ?? ('#' . (int)$palletSourceRoll['parent_roll_id']))) . '</a>';
    } else {
        $body .= '-';
    }
    $body .= '</div></div>';
    $body .= '</div></div>';
    $body .= '<div class="card" style="margin-top:12px"><div style="font-weight:800;margin-bottom:8px">Cajas contenidas</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Caja</th><th>Unidades</th><th>Bodega</th><th></th></tr></thead><tbody>';
    foreach ($palletBoxes as $palletBox) {
        $warehouseLabel = trim((string)($palletBox['warehouse_code'] ?? ''));
        if ($warehouseLabel === '') {
            $warehouseLabel = '-';
        } elseif ((string)($palletBox['warehouse_name'] ?? '') !== '') {
            $warehouseLabel .= ' - ' . (string)$palletBox['warehouse_name'];
        }
        $body .= '<tr>';
        $body .= '<td><a href="/boxes/' . (int)$palletBox['id'] . '">' . h((string)$palletBox['box_code']) . '</a></td>';
        $body .= '<td>' . h((string)($palletBox['units_qty'] ?? '-')) . '</td>';
        $body .= '<td>' . h($warehouseLabel) . '</td>';
        $body .= '<td><a class="btn secondary" href="/boxes/' . (int)$palletBox['id'] . '">Ver caja</a></td>';
        $body .= '</tr>';
    }
    if ($palletBoxes === []) {
        $body .= '<tr><td colspan="4" class="muted">Este pallet aún no tiene cajas asociadas.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    render('Pallet', $body);
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
