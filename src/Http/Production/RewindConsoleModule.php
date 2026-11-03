<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Consola Operativa de Rebobinadora (Metros, Cortes, Empalmes y Manillas)
// (src/Http/Production/RewindConsoleModule.php)
// =============================================================================

function renderRewindConsoleInfoSection(array $__vars): void
{
    echo renderView('production/rewind/info_section', $__vars);
}

function renderRewindConsoleSubformSection(array $__vars): void
{
    echo renderView('production/rewind/subform_section', $__vars);
}

function handleRewindConsoleRoutes(
    string $path,
    string $method,
    ProductionService $prodService,
    int $workerId
): bool {
    if (($path === '/production/work-orders/events/rebo-weights' || (isset($_REQUEST['reboexec']) && $_REQUEST['reboexec'] === 'save')) && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $reqId = (int)($_REQUEST['req_id'] ?? ($_REQUEST['prd_reqid'] ?? 0));
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        if ($reqId > 0 && $workerOtId > 0) {
            $prodService->saveReboInputWeights($reqId, $workerOtId, $_POST);
        }
        redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=reboprod&rebo_saved=1');
        return true;
    }


    return false;
}
