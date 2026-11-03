<?php

declare(strict_types=1);

// =============================================================================
// Enrutador HTTP · Área de Producción y Planta (src/Http/Production/ProductionRouter.php)
// =============================================================================

require_once __DIR__ . '/MachineShiftModule.php';
require_once __DIR__ . '/WorkOrderLifecycleModule.php';
require_once __DIR__ . '/ProductionEventsModule.php';
require_once __DIR__ . '/QualityChecklistModule.php';
require_once __DIR__ . '/ProductionClosureModule.php';
require_once __DIR__ . '/ProductionMaterialsModule.php';
require_once __DIR__ . '/FlexoConsoleModule.php';
require_once __DIR__ . '/SerigrafiaConsoleModule.php';
require_once __DIR__ . '/SealingConsoleModule.php';
require_once __DIR__ . '/RewindConsoleModule.php';

function handleProductionRouter(
    string $path,
    string $method,
    ProductionService $prodService,
    ReceptionService $receptionService,
    ScaleService $scaleService,
    string $currentOperatorName = ''
): bool {
    if ($currentOperatorName === '') {
        $currentOperatorName = trim((string)($_SESSION['operator_name'] ?? $_SESSION['auth_display_name'] ?? 'Operador'));
        if ($currentOperatorName === '') {
            $currentOperatorName = 'Operador';
        }
    }

    $workerId = (int)($_SESSION['wrk_id'] ?? 0);
    $plantaId = (int)($_SESSION['user_planta_id'] ?? 1);
    $plantaName = (string)($_SESSION['planta_name'] ?? 'PLANTA');

    // Compatibilidad directa con prodwrk.php?mid=...
    if ($path === '/prodwrk.php' || $path === '/prodwrk') {
        $mid = isset($_REQUEST['mid']) ? (int)$_REQUEST['mid'] : 0;
        if ($mid === 0) {
            redirectResponse('/production/machines');
            return true;
        }
        if ($mid === 1) {
            redirectResponse('/production/work-orders/new');
            return true;
        }
        if ($mid === 2) {
            $agId = (int)($_REQUEST['agid'] ?? 0);
            $mode = trim((string)($_REQUEST['mode'] ?? ''));
            $url = '/production/work-orders/operate?agid=' . $agId;
            if ($mode !== '') {
                $url .= '&mode=' . rawurlencode($mode);
            }
            if (isset($_REQUEST['refid'])) {
                $url .= '&refid=' . (int)$_REQUEST['refid'];
            }
            redirectResponse($url);
            return true;
        }
        if ($mid === 3) {
            redirectResponse('/production/work-orders/history');
            return true;
        }
        if ($mid === 5) {
            redirectResponse('/production/work-orders/active');
            return true;
        }
    }

    if ($path === '/production' || $path === '/production/' || $path === '/production/shifts') {
        redirectResponse('/production/machines');
        return true;
    }

    // Despacho modular por responsabilidad
    if (handleMachineShiftRoutes($path, $method, $prodService, $receptionService, $currentOperatorName, $workerId, $plantaId, $plantaName)) {
        return true;
    }
    if (handleWorkOrderLifecycleRoutes($path, $method, $prodService, $receptionService, $currentOperatorName, $workerId, $plantaId, $plantaName)) {
        return true;
    }
    if (handleProductionEventsRoutes($path, $method, $prodService, $workerId, $plantaId, $currentOperatorName)) {
        return true;
    }
    if (handleRewindConsoleRoutes($path, $method, $prodService, $workerId)) {
        return true;
    }
    if (handleSerigrafiaConsoleRoutes($path, $method, $prodService, $workerId)) {
        return true;
    }
    if (handleQualityChecklistRoutes($path, $method, $prodService, $currentOperatorName, $workerId, $plantaId)) {
        return true;
    }
    if (handleProductionClosureRoutes($path, $method, $prodService)) {
        return true;
    }
    if (handleProductionMaterialsRoutes($path, $method, $prodService, $receptionService, $currentOperatorName, $workerId, $plantaId, $plantaName)) {
        return true;
    }
    if (handleProductionOperateRoutes($path, $method, $prodService, $receptionService, $currentOperatorName, $workerId, $plantaId, $plantaName)) {
        return true;
    }

    return false;
}

function handleProductionRoutes(
    string $path,
    string $method,
    ProductionService $prodService,
    ReceptionService $receptionService,
    ScaleService $scaleService,
    string $currentOperatorName = ''
): bool {
    return handleProductionRouter($path, $method, $prodService, $receptionService, $scaleService, $currentOperatorName);
}
