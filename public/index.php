<?php

declare(strict_types=1);

// Servidor integrado de PHP (php -S): servir recursos estáticos existentes directamente (excepto documentos ERP)
if (PHP_SAPI === 'cli-server') {
    $reqUri = $_SERVER['REQUEST_URI'] ?? '';
    $reqPath = parse_url($reqUri, PHP_URL_PATH) ?? '';
    $isErpDoc = str_starts_with($reqPath, '/docs/')
        || str_starts_with($reqPath, '/docs.')
        || $reqPath === '/production/design-image'
        || $reqPath === '/api/document'
        || $reqPath === '/doc';
    if (!$isErpDoc) {
        // Solo archivos estaticos reales DENTRO de /public (evita path traversal y nunca archivos ocultos ni .php)
        $publicRoot = realpath(__DIR__);
        $staticFile = ($reqPath !== '' && $reqPath !== '/' && $publicRoot !== false) ? realpath($publicRoot . $reqPath) : false;
        if (
            $staticFile !== false
            && is_file($staticFile)
            && str_starts_with(strtolower(str_replace('\\', '/', $staticFile)), strtolower(str_replace('\\', '/', $publicRoot)) . '/')
            && !str_starts_with(basename($staticFile), '.')
            && strtolower(pathinfo($staticFile, PATHINFO_EXTENSION)) !== 'php'
        ) {
            return false;
        }
    }
}

// =============================================================================
// Front Controller · Kernel HTTP (public/index.php)
// =============================================================================
require_once __DIR__ . '/../src/bootstrap.php';

Env::load(__DIR__ . '/../.env');
RequestProfiler::start();
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
ob_start('unibagInjectDatePickerAssets');

$trzPdo = null;
$erpPdo = null;
$service = null;
$scale = null;
$printer = null;
$currentOperatorName = trim((string)($_SESSION['operator_name'] ?? $_SESSION['auth_display_name'] ?? 'Operador'));
if ($currentOperatorName === '') {
    $currentOperatorName = 'Operador';
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? rtrim($path, '/') : '/';
if ($path === '') {
    $path = '/';
}

// 1. Documentos y diseños adjuntos ERP
if (unibagServeErpDocument($path)) {
    exit;
}

// 2. Autenticación y control de acceso por área
if (handleAuthRoutes($path, $method)) {
    exit;
}
unibagEnforceAuthenticatedAreaAccess($path);

// 3. Fast-path para lectura de balanza sin abrir conexión a BD
if ($path === '/api/scale/weight' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    $fastScale = new ScaleService($_ENV);
    echo json_encode($fastScale->readWeightKg(), JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Conexiones a Base de Datos y Servicios
try {
    $trzPdo = Db::trzPdo();
    $erpPdo = Db::erpPdo();
} catch (Throwable $e) {
    renderDatabaseConnectionError($e);
}

$service = new ReceptionService($trzPdo, $erpPdo);
$prodService = new ProductionService($erpPdo, $trzPdo);
$scale = new ScaleService($_ENV);
$printer = new PrintService($_ENV);

// 5. Enrutamiento Modular (Despacho a cada módulo HTTP especializado)
if (handleApiRoutes($path, $method, $service, $scale, $printer, $currentOperatorName)) {
    exit;
}
if (handleMaterialSupplyRoutes($path, $method, $prodService, $currentOperatorName)) {
    exit;
}
if (handleDispatchRoutes($path, $method, $currentOperatorName)) {
    exit;
}
if (handleExpectedStockRoutes($path, $method, $currentOperatorName)) {
    exit;
}
if (handleInventoryRoutes($path, $method, $service, $currentOperatorName, $prodService)) {
    exit;
}
if (handleErpReportRoutes($path, $method, $service)) {
    exit;
}
if (handleWarehousesRoutes($path, $method, $service)) {
    exit;
}
if ($path === '/reports/colaciones' || $path === '/reports/lunch-breaks' || $path === '/erp/colaciones') {
    unibagHandleOperatorLunchBreaks($service);
    exit;
}
if ($path === '/production/lunch-breaks' || $path === '/production/colaciones') {
    redirectResponse('/reports/colaciones');
    exit;
}
if (function_exists('handleProductionRouter') && handleProductionRouter($path, $method, $prodService, $service, $scale, $currentOperatorName)) {
    exit;
}
if (handleTraceabilityRoutes($path, $method, $service, $prodService)) {
    exit;
}
if (handleWasteRoutes($path, $method, $prodService, $service, $scale, $currentOperatorName)) {
    exit;
}
if (handleBonusesRoutes($path, $method, $erpPdo, $trzPdo, $service)) {
    exit;
}
if (handleReceptionRouter($path, $method, $service, $scale, $printer, $currentOperatorName, $trzPdo)) {
    exit;
}
if (handleWorkOrderErpRoutes($path, $method, $service, $printer, $erpPdo, $currentOperatorName)) {
    exit;
}
if (handleChemicalsRoutes($path, $method, $service)) {
    exit;
}
if (handleStockAndOperationsRoutes($path, $method, $service, $printer, $currentOperatorName)) {
    exit;
}

// 6. Ruta por defecto / 404
http_response_code(404);
render('No encontrado', '<div class="card">Ruta no encontrada.</div>');
