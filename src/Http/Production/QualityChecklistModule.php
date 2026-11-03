<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Autocontrol de Calidad por Máquina y Aprobación de Partida
// (src/Http/Production/QualityChecklistModule.php)
// =============================================================================

function renderMachineAutocontrolFabrication(array $agenda, array $machine, array $transcomInfos, float $cutMeters): void

{
    echo renderView('production/quality/autocontrol_fabrication', get_defined_vars());
}


function renderQualityAutocontrolExclusiveSection(array $__vars): void
{
    echo renderView('production/quality/autocontrol_exclusive', $__vars);
    $content = (string)ob_get_clean();
    renderProdwrkShell(
        'Operar OT #' . ($__vars['agenda']['prd_number'] ?? ''),
        $content,
        2,
        (string)($__vars['currentOperatorName'] ?? ''),
        (string)($__vars['plantaName'] ?? '')
    );
}

function handleQualityChecklistRoutes(
    string $path,
    string $method,
    ProductionService $prodService,
    string $currentOperatorName,
    int $workerId,
    int $plantaId = 1
): bool {
    if (($path === '/production/work-orders/autocontrol' || (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'autoctrlsave')) && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        if ($workerOtId <= 0 && $agId > 0) {
            $openOt = $prodService->getOpenWorkerOtByAgId($workerId, $plantaId, $agId);
            if ($openOt !== null) {
                $workerOtId = (int)$openOt['id'];
            }
        }
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $submode = trim((string)($_REQUEST['submode'] ?? ''));

        $measureStatus = $prodService->getCysSetupMeasuresStatus($workerOtId);
        if ($measureStatus['required'] && !$measureStatus['complete']) {
            redirectResponse('/production/work-orders/operate?agid='.$agId.'&error='.rawurlencode('Selecciona De Medida y A Medida antes de confirmar el autocontrol.'));
            return true;
        }

        $selectedPoints = [];
        if (isset($_POST['baseacids']) && is_array($_POST['baseacids'])) {
            foreach ($_POST['baseacids'] as $baseacid) {
                $baseacid = (int)$baseacid;
                if (!empty($_POST['acids_' . $baseacid])) {
                    $selectedPoints[] = $baseacid;
                }
            }
        } elseif (isset($_POST['autocontrol_points']) && is_array($_POST['autocontrol_points'])) {
            $selectedPoints = array_map('intval', $_POST['autocontrol_points']);
        }

        // Only explicit confirmation finishes setup, not saving a draft.
        // Solo la confirmacion finaliza el alistamiento, no guardar un borrador.
        if (in_array($submode, ['close', 'close2'], true)) {
            $prodService->closeOpenAperturaEvent($workerOtId);
        }

        if ($submode === 'close') {
            $prodService->sendAutocontrolToSupervisor($workerOtId, $selectedPoints, $userId);
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&supervisor_notified=1');
        } elseif ($submode === 'close2') {
            $prodService->sendAutocontrolToLider($workerOtId, $selectedPoints, $userId);
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&lider_notified=1');
        } else {
            $prodService->saveAutocontrol($workerOtId, $selectedPoints, $userId);
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&saved_autocontrol=1');
        }
        return true;
    }

    // Aprobación de Partida por Supervisor o Líder (superautoctrlsave / liderautoctrlsave)
    if (($path === '/production/work-orders/autocontrol/supervisor' || (isset($_REQUEST['mode']) && in_array($_REQUEST['mode'], ['superautoctrlsave', 'liderautoctrlsave'], true))) && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        if ($workerOtId <= 0 && $agId > 0) {
            $openOt = $prodService->getOpenWorkerOtByAgId($workerId, $plantaId, $agId);
            if ($openOt !== null) {
                $workerOtId = (int)$openOt['id'];
            }
        }
        $submode = trim((string)($_REQUEST['submode'] ?? ''));
        $reqMode = trim((string)($_REQUEST['mode'] ?? ''));
        $supUser = trim((string)($_REQUEST['axx_username'] ?? ''));
        $supPass = trim((string)($_REQUEST['axx_pass'] ?? ''));

        // Distinguir si es aprobación de Líder de Área o de Supervisor de Partida
        $isLiderSave = ($reqMode === 'liderautoctrlsave' || $submode === 'lidersave');

        if ($isLiderSave) {
            $selectedPoints = [];
            if (isset($_POST['liderbaseacids']) && is_array($_POST['liderbaseacids'])) {
                foreach ($_POST['liderbaseacids'] as $baseacid) {
                    $baseacid = (int)$baseacid;
                    if (!empty($_POST['lideracids_' . $baseacid])) {
                        $selectedPoints[] = $baseacid;
                    }
                }
            } elseif (isset($_POST['superbaseacids']) && is_array($_POST['superbaseacids'])) {
                foreach ($_POST['superbaseacids'] as $baseacid) {
                    $baseacid = (int)$baseacid;
                    if (!empty($_POST['superacids_' . $baseacid])) {
                        $selectedPoints[] = $baseacid;
                    }
                }
            }

            $res = $prodService->approveAutocontrolLider($workerOtId, $selectedPoints, $supUser, $supPass);
            if ($res['ok']) {
                redirectResponse('/production/work-orders/operate?agid=' . $agId . '&lider_approved=1');
            } else {
                redirectResponse('/production/work-orders/operate?agid=' . $agId . '&error=' . rawurlencode((string)($res['error'] ?? 'Usuario no válido para aprobación de líder de área.')));
            }
            return true;
        }

        // Aprobación de Partida por Supervisor
        $selectedPoints = [];
        if (isset($_POST['superbaseacids']) && is_array($_POST['superbaseacids'])) {
            foreach ($_POST['superbaseacids'] as $baseacid) {
                $baseacid = (int)$baseacid;
                if (!empty($_POST['superacids_' . $baseacid])) {
                    $selectedPoints[] = $baseacid;
                }
            }
        }

        $res = $prodService->approveAutocontrolPartida($workerOtId, $selectedPoints, $supUser, $supPass);
        if ($res['ok']) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&partida_approved=1');
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&error=' . rawurlencode((string)($res['error'] ?? 'Usuario no válido para aprobaciones de supervisor.')));
        }
        return true;
    }


    return false;
}
