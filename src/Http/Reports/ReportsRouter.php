<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Enrutador Principal de Reportes ERP y Carga de Submódulos
 * (src/Http/Reports/ReportsRouter.php)
 * =============================================================================
 */

require_once __DIR__ . '/DashboardAndGraphicsModule.php';
require_once __DIR__ . '/WasteAndInventoryReportsModule.php';
require_once __DIR__ . '/MachineReportsModule.php';
require_once __DIR__ . '/ServiceAndDispatchReportsModule.php';
require_once __DIR__ . '/ReceptionAuditModule.php';
require_once __DIR__ . '/OperatorLunchModule.php';

/**
 * ERP reports (/reports/*) and dashboard router.
 *
 * Maps routes to screens or exports:
 * - GET /                            ERP dashboard (area-aware)
 * - GET /reports/graphics            charts
 * - GET /reports/production-dashboard production dashboard
 * - GET /reports/production-dashboard/excel metric card export (metric=...)
 * - GET /reports/operator-waste      informe de merma por operador
 * - GET /reports/operator-waste/excel export informe de merma por operador
 * - GET /reports/occupancy/excel     occupancy export (optional warehouse_filter)
 * - GET /reports/occupancy/{code}/excel occupancy export for a single warehouse
 * - GET /reports/inventory           inventory counts list
 * - GET /reports/inventory/{id}      inventory detail
 * - GET /reports/inventory/{id}/excel inventory detail export
 * - GET /reports/machine-staff       machine staff report
 * - GET /reports/machine-staff/excel machine staff report export
 * - GET /reports/machine-production  machine production report
 * - GET /reports/machine-production/excel machine production report export
 * - GET /reports/colacion            informe de colación
 * - GET /reports/colacion/excel      export informe de colación
 * - GET /reports/detencion           informe de detenciones
 * - GET /reports/detencion/excel     export informe de detenciones
 * - GET /reports/cambio-configuracion informe cambio de configuración
 * - GET /reports/cambio-configuracion/excel export cambio de configuración
 * - GET /reports/nivel-servicio      informe nivel de servicio
 * - GET /reports/nivel-servicio/excel export nivel de servicio
 *
 * @return bool true si manejó la ruta; false si no corresponde a este módulo
 */
function handleErpReportRoutes(string $path, string $method, ReceptionService $service): bool
{
    if ($path === '/' && $method === 'GET') {
        unibagRenderErpDashboardPage($service);
        return true;
    }

    if (($path === '/reports/resumen-diario' || $path === '/dashboard/resumen' || $path === '/reports/daily-summary') && $method === 'GET') {
        unibagRenderDailySummaryPage($service);
        return true;
    }

    if (($path === '/reports/resumen-diario/excel' || $path === '/dashboard/resumen/excel' || $path === '/reports/daily-summary/excel') && $method === 'GET') {
        unibagOutputDailySummaryExcel($service);
        return true;
    }

    if ($path === '/reports/operator-waste' && $method === 'GET') {
        unibagRenderOperatorWasteReportPage($service);
        return true;
    }

    if ($path === '/reports/operator-waste/excel' && $method === 'GET') {
        unibagOutputOperatorWasteExcel($service);
        return true;
    }

    if ($path === '/reports/despachos' && $method === 'GET') {
        unibagRenderDispatchesReportPage($service);
        return true;
    }

    if ($path === '/reports/despachos/excel' && $method === 'GET') {
        unibagOutputDispatchesReportExcel($service);
        return true;
    }

    if ($path === '/reports/graphics' && $method === 'GET') {
        unibagRenderGraphicsPage($service);
        return true;
    }

    if ($path === '/reports/production-dashboard' && $method === 'GET') {
        unibagRenderErpOnlyProductionDashboardPage($service, false);
        return true;
    }

    if ($path === '/reports/production-dashboard/excel' && $method === 'GET') {
        unibagOutputProductionDashboardMetricExcel($service);
        return true;
    }

    if ($path === '/reports/dashboard-waste-detail/excel' && $method === 'GET') {
        unibagOutputDashboardWasteDetailExcel($service);
        return true;
    }

    if ($path === '/reports/critical-waste/excel' && $method === 'GET') {
        unibagOutputCriticalWasteExcel($service);
        return true;
    }

    if ($path === '/reports/occupancy/excel' && $method === 'GET') {
        unibagOutputWarehouseOccupancyExcel($service);
        return true;
    }

    if (preg_match('#^/reports/occupancy/(.+?)/excel$#', $path, $matches) === 1 && $method === 'GET') {
        $_GET['warehouse_filter'] = (string)$matches[1];
        unibagOutputWarehouseOccupancyExcel($service);
        return true;
    }

    if ($path === '/reports/machine-staff' && $method === 'GET') {
        $qs = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
        redirectResponse('/reports/machine-production' . $qs);
        return true;
    }

    if ($path === '/reports/machine-staff/excel' && $method === 'GET') {
        $qs = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
        redirectResponse('/reports/machine-production/excel' . $qs);
        return true;
    }

    if ($path === '/reports/machine-production' && $method === 'GET') {
        unibagRenderMachineProductionReportPage($service);
        return true;
    }

    if ($path === '/reports/machine-production/excel' && $method === 'GET') {
        unibagOutputMachineProductionExcel($service);
        return true;
    }

    // Consulta de bobinas cargadas/en proceso en máquinas (para modal y control en tiempo real)
    if ($path === '/reports/machine-production/loaded-rolls' && $method === 'GET') {
        if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'No tiene permisos suficientes para ver bobinas de producción.']);
            return true;
        }
        $workerOtId = isset($_GET['worker_ot_id']) && is_numeric($_GET['worker_ot_id']) ? (int)$_GET['worker_ot_id'] : null;
        $rolls = $service->getLoadedRollsAcrossMachines($workerOtId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'count' => count($rolls), 'rolls' => $rolls], JSON_UNESCAPED_UNICODE);
        return true;
    }

    // Devolución directa de bobina a Bodega 3000 desde el informe de producción por máquina
    if ($path === '/reports/machine-production/return-roll' && $method === 'POST') {
        if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'No tiene permisos suficientes para devolver bobinas a bodega.']);
            return true;
        }

        requireCsrf();

        $rollId = isset($_POST['roll_id']) ? (int)$_POST['roll_id'] : 0;
        $userName = trim((string)($_SESSION['user_name'] ?? $_SESSION['auth_username'] ?? $_SESSION['user_firstname'] ?? 'Supervisor'));
        $reason = trim((string)($_POST['reason'] ?? 'Devuelta a Bodega 3000 desde Informe de Producción por Máquina'));

        $result = $service->returnRollToBodega3000($rollId, $userName, $reason);

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            if (($result['ok'] ?? false) !== true) {
                http_response_code(422);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return true;
        }

        $redirectUrl = trim((string)($_POST['redirect_url'] ?? '/reports/machine-production'));
        if ($redirectUrl === '' || !str_starts_with($redirectUrl, '/reports/machine-production')) {
            $redirectUrl = '/reports/machine-production';
        }
        $sep = str_contains($redirectUrl, '?') ? '&' : '?';
        if (($result['ok'] ?? false) === true) {
            redirectResponse($redirectUrl . $sep . 'msg=' . rawurlencode((string)($result['message'] ?? 'Bobina devuelta a Bodega 3000.')));
        } else {
            redirectResponse($redirectUrl . $sep . 'error=' . rawurlencode((string)($result['error'] ?? 'Error al devolver bobina.')));
        }
        return true;
    }

    // Actualización de registro de producción por máquina (Modal de edición)
    if ($path === '/reports/machine-production/update' && $method === 'POST') {
        if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'No tiene permisos suficientes para modificar producción.']);
            return true;
        }

        if (!unibagCanUserPerformModifications()) {
            http_response_code(403);
            $errPermMsg = 'Acceso denegado: únicamente los usuarios autorizados (HECTOR y JAVIER) pueden realizar modificaciones en producción.';
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                   || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => $errPermMsg]);
                return true;
            }
            $redirectUrl = trim((string)($_POST['redirect_url'] ?? '/reports/machine-production'));
            if ($redirectUrl === '' || !str_starts_with($redirectUrl, '/reports/machine-production')) {
                $redirectUrl = '/reports/machine-production';
            }
            $sep = str_contains($redirectUrl, '?') ? '&' : '?';
            redirectResponse($redirectUrl . $sep . 'error=' . rawurlencode($errPermMsg));
            return true;
        }

        requireCsrf();

        $otId = isset($_POST['ot_id']) ? (int)$_POST['ot_id'] : 0;
        $userName = trim((string)($_SESSION['user_name'] ?? $_SESSION['auth_username'] ?? $_SESSION['user_firstname'] ?? 'Usuario'));
        $result = $service->updateProductionRecord($otId, $_POST, $userName);

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            if ($result['ok'] !== true) {
                http_response_code(422);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return true;
        }

        $redirectUrl = trim((string)($_POST['redirect_url'] ?? '/reports/machine-production'));
        if ($redirectUrl === '' || !str_starts_with($redirectUrl, '/reports/machine-production')) {
            $redirectUrl = '/reports/machine-production';
        }
        $sep = str_contains($redirectUrl, '?') ? '&' : '?';
        if ($result['ok'] === true) {
            redirectResponse($redirectUrl . $sep . 'msg=' . rawurlencode((string)($result['message'] ?? 'Registro modificado con éxito.')));
        } else {
            redirectResponse($redirectUrl . $sep . 'error=' . rawurlencode((string)($result['error'] ?? 'Error al actualizar.')));
        }
        return true;
    }

    // Eliminación de registro de producción (OT duplicada o registro erróneo)
    if ($path === '/reports/machine-production/delete' && $method === 'POST') {
        if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'No tiene permisos suficientes para eliminar producción.']);
            return true;
        }

        $supUser = trim((string)($_POST['supervisor_user'] ?? ''));
        $supPass = trim((string)($_POST['supervisor_pass'] ?? ''));
        $authCheck = unibagCanDeleteProductionRecord($supUser, $supPass);

        if (!$authCheck['ok']) {
            http_response_code(403);
            $errPermMsg = $authCheck['error'] ?? 'Acceso denegado: únicamente los usuarios autorizados (HECTOR y JAVIER) pueden eliminar registros de producción.';
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                   || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => $errPermMsg]);
                return true;
            }
            $redirectUrl = trim((string)($_POST['redirect_url'] ?? '/reports/machine-production'));
            if ($redirectUrl === '' || !str_starts_with($redirectUrl, '/reports/machine-production')) {
                $redirectUrl = '/reports/machine-production';
            }
            $sep = str_contains($redirectUrl, '?') ? '&' : '?';
            redirectResponse($redirectUrl . $sep . 'error=' . rawurlencode($errPermMsg));
            return true;
        }

        requireCsrf();

        $otId = isset($_POST['ot_id']) ? (int)$_POST['ot_id'] : 0;
        $eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
        $userName = (string)($authCheck['authorizer'] ?? trim((string)($_SESSION['user_name'] ?? $_SESSION['auth_username'] ?? $_SESSION['user_firstname'] ?? 'Javier / Héctor')));

        if ($eventId > 0 && $otId > 0) {
            $result = $service->deleteProductionEvent($otId, $eventId, $userName);
        } else {
            $result = $service->deleteProductionRecord($otId, $userName);
        }

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            if ($result['ok'] !== true) {
                http_response_code(422);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return true;
        }

        $redirectUrl = trim((string)($_POST['redirect_url'] ?? '/reports/machine-production'));
        if ($redirectUrl === '' || !str_starts_with($redirectUrl, '/reports/machine-production')) {
            $redirectUrl = '/reports/machine-production';
        }
        $sep = str_contains($redirectUrl, '?') ? '&' : '?';
        if ($result['ok'] === true) {
            redirectResponse($redirectUrl . $sep . 'msg=' . rawurlencode((string)($result['message'] ?? 'Registro eliminado con éxito.')));
        } else {
            redirectResponse($redirectUrl . $sep . 'error=' . rawurlencode((string)($result['error'] ?? 'Error al eliminar.')));
        }
        return true;
    }

    if ($path === '/reports/machine-production/delete-event' && $method === 'POST') {
        if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'No tiene permisos suficientes para anular eventos de producción.']);
            return true;
        }

        if (!unibagCanUserPerformModifications()) {
            http_response_code(403);
            $errPermMsg = 'Acceso denegado: únicamente los usuarios autorizados (HECTOR y JAVIER) pueden anular eventos de producción.';
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                   || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => $errPermMsg]);
                return true;
            }
            $redirectUrl = trim((string)($_POST['redirect_url'] ?? '/reports/machine-production'));
            if ($redirectUrl === '' || !str_starts_with($redirectUrl, '/reports/machine-production')) {
                $redirectUrl = '/reports/machine-production';
            }
            $sep = str_contains($redirectUrl, '?') ? '&' : '?';
            redirectResponse($redirectUrl . $sep . 'error=' . rawurlencode($errPermMsg));
            return true;
        }

        requireCsrf();

        $otId = isset($_POST['ot_id']) ? (int)$_POST['ot_id'] : 0;
        $eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
        $userName = trim((string)($_SESSION['user_name'] ?? $_SESSION['auth_username'] ?? $_SESSION['user_firstname'] ?? 'Usuario'));
        $result = $service->deleteProductionEvent($otId, $eventId, $userName);

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            if ($result['ok'] !== true) {
                http_response_code(422);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return true;
        }

        $redirectUrl = trim((string)($_POST['redirect_url'] ?? '/reports/machine-production'));
        if ($redirectUrl === '' || !str_starts_with($redirectUrl, '/reports/machine-production')) {
            $redirectUrl = '/reports/machine-production';
        }
        $sep = str_contains($redirectUrl, '?') ? '&' : '?';
        if ($result['ok'] === true) {
            redirectResponse($redirectUrl . $sep . 'msg=' . rawurlencode((string)($result['message'] ?? 'Evento anulado con éxito.')));
        } else {
            redirectResponse($redirectUrl . $sep . 'error=' . rawurlencode((string)($result['error'] ?? 'Error al anular el evento.')));
        }
        return true;
    }

    // Eventos de Máquina (Unificado: Detención, Colación, Cambio Configuración)
    if ($path === '/reports/machine-events' && $method === 'GET') {
        unibagRenderMachineEventsReportPage($service);
        return true;
    }

    if ($path === '/reports/machine-events/excel' && $method === 'GET') {
        unibagOutputMachineEventsExcel($service);
        return true;
    }

    // Colación (Redirige a pestaña unificada o renderiza)
    if ($path === '/reports/colacion' && $method === 'GET') {
        $query = $_GET;
        $query['tab'] = 'colacion';
        redirectResponse('/reports/machine-events?' . http_build_query($query));
        return true;
    }

    if ($path === '/reports/colacion/excel' && $method === 'GET') {
        unibagOutputColacionExcel($service);
        return true;
    }

    // Detención (Redirige a pestaña unificada o renderiza)
    if ($path === '/reports/detencion' && $method === 'GET') {
        $query = $_GET;
        $query['tab'] = 'detencion';
        redirectResponse('/reports/machine-events?' . http_build_query($query));
        return true;
    }

    if ($path === '/reports/detencion/excel' && $method === 'GET') {
        unibagOutputDetencionExcel($service);
        return true;
    }

    // Cambio de Configuración (Redirige a pestaña unificada o renderiza)
    if ($path === '/reports/cambio-configuracion' && $method === 'GET') {
        $query = $_GET;
        $query['tab'] = 'setup';
        redirectResponse('/reports/machine-events?' . http_build_query($query));
        return true;
    }

    if ($path === '/reports/cambio-configuracion/excel' && $method === 'GET') {
        unibagOutputCambioConfiguracionExcel($service);
        return true;
    }

    // Nivel de Servicio
    if ($path === '/reports/nivel-servicio' && $method === 'GET') {
        unibagRenderServiceLevelReportPage($service);
        return true;
    }

    if ($path === '/reports/nivel-servicio/excel' && $method === 'GET') {
        unibagOutputServiceLevelExcel($service);
        return true;
    }

    // Modificación de registro en Nivel de Servicio (OTIF) por acuerdo con cliente
    if ($path === '/reports/nivel-servicio/update' && $method === 'POST') {
        if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'No tiene permisos suficientes para modificar registros de nivel de servicio.']);
            return true;
        }

        if (!unibagCanUserPerformModifications()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Acceso denegado: únicamente los usuarios autorizados (HECTOR y JAVIER) pueden modificar nivel de servicio.']);
            return true;
        }

        requireCsrf();

        $userName = trim((string)($_SESSION['user_name'] ?? $_SESSION['auth_username'] ?? $_SESSION['user_firstname'] ?? 'Usuario'));
        $result = $service->saveServiceLevelAdjustment($_POST, $userName);

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            if ($result['ok'] !== true) {
                http_response_code(422);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return true;
        }

        $redirectUrl = trim((string)($_POST['redirect_url'] ?? '/reports/nivel-servicio'));
        $sep = str_contains($redirectUrl, '?') ? '&' : '?';
        if ($result['ok'] === true) {
            redirectResponse($redirectUrl . $sep . 'msg=' . rawurlencode((string)($result['message'] ?? 'Registro modificado con éxito.')));
        } else {
            redirectResponse($redirectUrl . $sep . 'error=' . rawurlencode((string)($result['error'] ?? 'Error al actualizar.')));
        }
        return true;
    }

    if ($path === '/reports/nivel-servicio/revert' && $method === 'POST') {
        if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'No tiene permisos suficientes.']);
            return true;
        }

        if (!unibagCanUserPerformModifications()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Acceso denegado: únicamente los usuarios autorizados (HECTOR y JAVIER) pueden revertir acuerdos de nivel de servicio.']);
            return true;
        }

        requireCsrf();

        $despachoId = isset($_POST['despacho_id']) ? (int)$_POST['despacho_id'] : 0;
        $userName = trim((string)($_SESSION['user_name'] ?? $_SESSION['auth_username'] ?? $_SESSION['user_firstname'] ?? 'Usuario'));
        $result = $service->revertServiceLevelAdjustment($despachoId, $userName);

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            if ($result['ok'] !== true) {
                http_response_code(422);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return true;
        }

        $redirectUrl = trim((string)($_POST['redirect_url'] ?? '/reports/nivel-servicio'));
        $sep = str_contains($redirectUrl, '?') ? '&' : '?';
        redirectResponse($redirectUrl . $sep . 'msg=' . rawurlencode((string)($result['message'] ?? 'Ajuste revertido.')));
        return true;
    }

    if ($path === '/reports/inventory' && $method === 'GET') {
        unibagRenderInventoryReportsPage($service);
        return true;
    }

    if (preg_match('#^/reports/inventory/(\d+)/excel$#', $path, $matches) === 1 && $method === 'GET') {
        unibagOutputInventoryReportExcel($service, (int)$matches[1]);
        return true;
    }

    if (preg_match('#^/reports/inventory/(\d+)$#', $path, $matches) === 1 && $method === 'GET') {
        unibagRenderInventoryReportDetailPage($service, (int)$matches[1]);
        return true;
    }

    // Informe Mensual PPTX
    if ($path === '/reports/monthly-presentation' && $method === 'GET') {
        unibagRenderMonthlyPresentationPage($service);
        return true;
    }

    if ($path === '/reports/monthly-presentation/download' && $method === 'GET') {
        unibagDownloadMonthlyPresentation($service);
        return true;
    }

    return false;
}
