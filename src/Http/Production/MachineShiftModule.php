<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Asignación de Máquina, Inicio/Fin de Turno, Colación y Standby
// (src/Http/Production/MachineShiftModule.php)
// =============================================================================

function renderStandbyAndLunchModals(?array $activeStandby, ?array $activeInit, string $operatorName, string $redirectTo = '', ?array $activeLunch = null): void
{
    echo renderView('production/shift/standby_lunch_modals', get_defined_vars());
}

// =============================================================================
// OTRAS PANTALLAS: ASIGNACIÓN, NUEVA OT, ACTIVAS, HISTÓRICO
// =============================================================================

function renderProductionMachinesScreen(array $machines, ?array $activeInit, string $operatorName, string $plantaName, ?array $activeStandby = null, ?array $activeLunch = null): void
{
    $content = renderView('production/shift/machines_screen', get_defined_vars());
    renderProdwrkShell('Asignación de Máquina', $content, 0, $operatorName, $plantaName);
}

function renderProductionNeedMachineScreen(string $operatorName, string $plantaName): void
{
    $content = renderView('production/need_machine');
    renderProdwrkShell('Asignación Requerida', $content, 1, $operatorName, $plantaName);
}

function handleMachineShiftRoutes(
    string $path,
    string $method,
    ProductionService $prodService,
    ReceptionService $receptionService, // Servicio de recepción: se usa para las colaciones (almuerzo) del operador
    string $currentOperatorName,
    int $workerId,
    int $plantaId,
    string $plantaName
): bool {
    if ($path === '/production/machines' && $method === 'GET') {
        $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
        $machines = $prodService->listPlantMachines($plantaId);
        $activeStandby = null;
        if ($activeInit !== null) {
            $activeStandby = $prodService->getActiveMachineStandby((int)$activeInit['win_equipoid'], (int)$activeInit['id']);
        }
        $activeLunch = $receptionService->getActiveOperatorLunchBreak($workerId, (int)($activeInit['id'] ?? 0), $currentOperatorName);
        renderProductionMachinesScreen($machines, $activeInit, $currentOperatorName, $plantaName, $activeStandby, $activeLunch);
        return true;
    }

    if ($path === '/production/machines/init' && $method === 'POST') {
        requireCsrf();
        $equipoId = (int)($_POST['equipo_id'] ?? 0);
        $assId = (int)($_POST['ass_id'] ?? 0);

        $res = $prodService->startWorkerMachineSession($workerId, $plantaId, $equipoId, $assId);
        if ($res['ok']) {
            if ($equipoId === 37 || $equipoId === 53 || !empty($_POST['is_waste'])) {
                redirectResponse('/waste/management?started=1');
            } else {
                redirectResponse('/production/work-orders/new?machine_started=1');
            }
        } else {
            redirectResponse('/production/machines?error=' . rawurlencode((string)($res['error'] ?? 'Error al iniciar máquina.')));
        }
        return true;
    }

    if ($path === '/production/machines/end' && $method === 'POST') {
        requireCsrf();
        $res = $prodService->endWorkerMachineSession($workerId, $plantaId);
        if ($res['ok']) {
            redirectResponse('/production/machines?machine_ended=1');
        } else {
            redirectResponse('/production/machines?error=' . rawurlencode((string)($res['error'] ?? 'Error al cerrar máquina.')));
        }
        return true;
    }

    if ($path === '/production/machines/lunch' && $method === 'POST') {
        requireCsrf();
        $redirectTo = trim((string)($_POST['redirect_to'] ?? '/production/machines'));
        if ($redirectTo === '' || !str_starts_with($redirectTo, '/')) {
            $redirectTo = '/production/machines';
        }
        $sep = str_contains($redirectTo, '?') ? '&' : '?';

        $action = trim((string)($_POST['action'] ?? 'start'));
        $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
        $initId = $activeInit !== null ? (int)$activeInit['id'] : (int)($_POST['init_id'] ?? 0);
        $machineId = $activeInit !== null ? (int)$activeInit['win_equipoid'] : (int)($_POST['machine_id'] ?? 0);
        $machineName = $activeInit !== null ? (string)$activeInit['equipo_name'] : trim((string)($_POST['machine_name'] ?? ''));
        $comments = trim((string)($_POST['comments'] ?? ''));

        if ($action === 'start') {
            $res = $receptionService->startOperatorLunchBreak(
                $currentOperatorName,
                $workerId,
                $initId,
                $machineId,
                $machineName,
                $comments
            );
            if ($res['ok']) {
                redirectResponse($redirectTo . $sep . 'lunch_started=1');
            } else {
                redirectResponse($redirectTo . $sep . 'error=' . rawurlencode((string)($res['error'] ?? 'Error al iniciar colación.')));
            }
        } elseif ($action === 'end') {
            $lunchId = (int)($_POST['lunch_id'] ?? 0);
            $res = $receptionService->endOperatorLunchBreak(
                $lunchId,
                $currentOperatorName,
                $workerId,
                $initId,
                $comments
            );
            if ($res['ok']) {
                $dur = (int)($res['duration'] ?? 0);
                redirectResponse($redirectTo . $sep . 'lunch_ended=1&duration=' . $dur);
            } else {
                redirectResponse($redirectTo . $sep . 'error=' . rawurlencode((string)($res['error'] ?? 'Error al finalizar colación.')));
            }
        } else {
            redirectResponse($redirectTo . $sep . 'error=' . rawurlencode('Accion de colacion no valida.'));
        }
        return true;
    }

    if ($path === '/production/machines/standby' && $method === 'POST') {
        requireCsrf();
        $redirectTo = trim((string)($_POST['redirect_to'] ?? '/production/machines'));
        if ($redirectTo === '' || !str_starts_with($redirectTo, '/')) {
            $redirectTo = '/production/machines';
        }
        $sep = str_contains($redirectTo, '?') ? '&' : '?';

        $action = trim((string)($_POST['action'] ?? 'start'));
        $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
        $initId = $activeInit !== null ? (int)$activeInit['id'] : 0;
        $machineId = $activeInit !== null ? (int)$activeInit['win_equipoid'] : 0;
        $machineName = $activeInit !== null ? (string)$activeInit['equipo_name'] : '';

        if ($action === 'start') {
            $reason = trim((string)($_POST['reason'] ?? 'Sin Producción'));
            $startTime = trim((string)($_POST['start_time'] ?? date('H:i')));
            $comments = trim((string)($_POST['comments'] ?? ''));
            $res = $prodService->startMachineStandby(
                $initId,
                $workerId,
                $currentOperatorName,
                $machineId,
                $machineName,
                $reason,
                $startTime,
                $comments
            );
            if ($res['ok']) {
                redirectResponse($redirectTo . $sep . 'standby_started=1');
            } else {
                redirectResponse($redirectTo . $sep . 'error=' . rawurlencode((string)($res['error'] ?? 'Error al registrar evento sin producción.')));
            }
        } elseif ($action === 'end') {
            $standbyId = (int)($_POST['standby_id'] ?? 0);
            $endTime = trim((string)($_POST['end_time'] ?? date('H:i')));
            $res = $prodService->endMachineStandby($standbyId, $endTime);
            if ($res['ok']) {
                redirectResponse($redirectTo . $sep . 'standby_ended=1');
            } else {
                redirectResponse($redirectTo . $sep . 'error=' . rawurlencode((string)($res['error'] ?? 'Error al finalizar evento sin producción.')));
            }
        }
        return true;
    }

    // -------------------------------------------------------------------------
    // 2. INICIAR NUEVA OT DESDE AGENDA (/production/work-orders/new · mid=1)

    return false;
}
