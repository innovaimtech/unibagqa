<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Cierre de Orden de Trabajo, Clave de Supervisor y Balance CC de 5 Campos
// (src/Http/Production/ProductionClosureModule.php)
// =============================================================================

function renderProductionClosingRightPanel(
    array $agenda,
    ?array $currentRefEvt,
    int $refId,
    float $valAmount,
    int $overrideEmbalaje,
    bool $isSelladora
): void {
    echo renderView('production/closure/closing_right_panel', get_defined_vars());
}


function renderProductionClosureSubformSection(array $__vars): void
{
    echo renderView('production/closure/closure_subform', $__vars);
}

function handleProductionClosureRoutes(
    string $path,
    string $method,
    ProductionService $prodService
): bool {
    if (($path === '/production/work-orders/close' || (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'terminarotsave')) && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        $supUser = (string)($_REQUEST['sup_user'] ?? ($_REQUEST['askprdclose_axx_username'] ?? ''));
        $supPass = (string)($_REQUEST['sup_pass'] ?? ($_REQUEST['askprdclose_axx_pass'] ?? ''));

        if ($workerOtId <= 0 && $agId > 0) {
            $openOt = $prodService->getOpenWorkerOtByAgId(0, 0, $agId); // antes faltaban argumentos y fallaba
            if ($openOt && !empty($openOt['id'])) {
                $workerOtId = (int)$openOt['id'];
            }
        }

        $res = $prodService->closeWorkOrder($workerOtId, $supUser, $supPass,
            !empty($_REQUEST['confirm_bag_difference']), (string)($_REQUEST['bag_difference_reason'] ?? ''));
        if ($res['ok']) {
            $prodService->syncPulpoAgendaStats($agId);
            $closedParam = !empty($res['discarded']) ? 'closed=1&noprod=1&discarded=1' : (!empty($res['without_supervisor']) ? 'closed=1&noprod=1' : 'closed=1');
            redirectResponse('/production/work-orders/active?' . $closedParam);
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=terminarot&error=' . rawurlencode((string)($res['error'] ?? 'Error al cerrar OT.')));
        }
        return true;
    }


    return false;
}
