<?php

declare(strict_types=1);

/**
 * Módulo HTTP de Producción · Operación de Planta y Consola de Operador
 * 
 * Recreación fiel y completa de:
 * - prodwrk.php (layout, topbar #008A87/#00A9A4, sidebar #0E7572, navegación mid=0,1,2,3,5)
 * - editxid.php (4 ramas por máquina: Flexografía, Serigrafía Pulpo, Serigrafía Plana, Selladora/Confección/Rebobinadora/Embalaje)
 * - Información Cliente e Información de Fabricación completas con todos sus campos
 * - Barra de herramientas de acciones y tabla de eventos de la OT
 * - Subformularios dedicados por modo: production, apertura, mantencion, pause, materiales, consumo, reboprod, terminarot
 */

function handleProductionRoutes(
    string $path,
    string $method,
    ProductionService $prodService,
    ReceptionService $receptionService,
    ScaleService $scaleService,
    string $currentOperatorName
): bool {
    $workerId = (int)($_SESSION['wrk_id'] ?? 0);
    $plantaId = (int)($_SESSION['user_planta_id'] ?? 1);
    $plantaName = (string)($_SESSION['planta_name'] ?? 'PLANTA');

    // -------------------------------------------------------------------------
    // Compatibilidad directa con prodwrk.php?mid=...
    // -------------------------------------------------------------------------
    if ($path === '/prodwrk.php' || $path === '/prodwrk') {
        $mid = isset($_REQUEST['mid']) ? (int)$_REQUEST['mid'] : 0;
        if ($mid === 0) {
            redirectResponse('/production/machines');
            return true;
        } elseif ($mid === 1) {
            redirectResponse('/production/work-orders/new');
            return true;
        } elseif ($mid === 2) {
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
        } elseif ($mid === 3) {
            redirectResponse('/production/work-orders/history');
            return true;
        } elseif ($mid === 5) {
            redirectResponse('/production/work-orders/active');
            return true;
        }
    }

    if ($path === '/production' || $path === '/production/' || $path === '/production/shifts') {
        redirectResponse('/production/machines');
        return true;
    }

    // -------------------------------------------------------------------------
    // 1. ASIGNACIÓN DE MÁQUINAS / TURNOS (/production/machines · mid=0)
    // -------------------------------------------------------------------------
    if ($path === '/production/machines' && $method === 'GET') {
        $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
        $machines = $prodService->listPlantMachines($plantaId);
        $activeStandby = null;
        if ($activeInit !== null) {
            $activeStandby = $prodService->getActiveMachineStandby((int)$activeInit['win_equipoid'], (int)$activeInit['id']);
        }
        renderProductionMachinesScreen($machines, $activeInit, $currentOperatorName, $plantaName, $activeStandby);
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

        $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
        $today = date('Y-m-d');
        $startTime = trim((string)($_POST['start_time'] ?? '13:00'));
        $endTime = trim((string)($_POST['end_time'] ?? '13:30'));
        $comments = trim((string)($_POST['comments'] ?? 'Colación registrada desde producción'));
        $initId = $activeInit !== null ? (int)$activeInit['id'] : (int)($_POST['init_id'] ?? 0);
        $machineId = $activeInit !== null ? (int)$activeInit['win_equipoid'] : (int)($_POST['machine_id'] ?? 0);
        $machineName = $activeInit !== null ? (string)$activeInit['equipo_name'] : trim((string)($_POST['machine_name'] ?? ''));

        $res = $receptionService->saveOperatorLunchBreak(
            $today,
            $currentOperatorName,
            $startTime,
            $endTime,
            $workerId,
            $initId,
            $machineId,
            $machineName,
            $comments,
            $currentOperatorName
        );

        if ($res['ok']) {
            redirectResponse($redirectTo . $sep . 'lunch_saved=1');
        } else {
            redirectResponse($redirectTo . $sep . 'error=' . rawurlencode((string)($res['error'] ?? 'Error al registrar colación.')));
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
    // -------------------------------------------------------------------------
    if ($path === '/production/work-orders/new' && $method === 'GET') {
        $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
        if ($activeInit === null) {
            renderProductionNeedMachineScreen($currentOperatorName, $plantaName);
            return true;
        }

        $equipoId = (int)$activeInit['win_equipoid'];
        $equipoTypeId = (int)($activeInit['equipo_type_id'] ?? 0);
        $isFlexo = ($equipoTypeId === 7
            || (int)($activeInit['equipo_prod_isprinter_flexo'] ?? 0) === 1
            || stripos((string)($activeInit['type_ant_title'] ?? ''), 'flexo') !== false
            || stripos((string)($activeInit['equipo_name'] ?? ''), 'flexo') !== false);
        $isSeri = ((int)($activeInit['equipo_prod_isprinter_seri'] ?? 0) === 1)
            || ($equipoTypeId === 11 || $equipoTypeId === 8 || $equipoTypeId === 22)
            || stripos((string)($activeInit['type_ant_title'] ?? ''), 'seri') !== false
            || stripos((string)($activeInit['equipo_name'] ?? ''), 'seri') !== false
            || stripos((string)($activeInit['equipo_name'] ?? ''), 'pulpo') !== false;

        $dateFilter = trim((string)($_GET['plan_date'] ?? $_GET['date'] ?? ''));
        if ($dateFilter === '' && ($isFlexo || $isSeri)) {
            $dateFilter = date('d.m.Y');
        } elseif ($dateFilter === 'all') {
            $dateFilter = null;
        }

        $activeTab = 'scheduled';
        $searchQuery = trim((string)($_GET['search'] ?? $_GET['q'] ?? ''));

        $scheduledOrders = $prodService->listScheduledOrdersForMachine($equipoId, $plantaId, $dateFilter);
        $unscheduledOrders = [];

        $activeOt = $prodService->getOpenWorkerOt($workerId, $plantaId);
        $activeStandby = $prodService->getActiveMachineStandby($equipoId, (int)$activeInit['id']);

        renderProductionNewWorkOrderScreen(
            $activeInit,
            $scheduledOrders,
            $activeOt,
            $currentOperatorName,
            $plantaName,
            $activeStandby,
            $dateFilter,
            $unscheduledOrders,
            $activeTab,
            $searchQuery
        );
        return true;
    }

    if ($path === '/production/work-orders/init' && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_POST['ag_id'] ?? 0);

        $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
        if ($activeInit === null) {
            redirectResponse('/production/machines?error=' . rawurlencode('Debes iniciar turno en una máquina primero.'));
            return true;
        }

        $res = $prodService->startWorkerOt($agId, (int)$activeInit['id']);
        if ($res['ok']) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&started=1');
        } else {
            redirectResponse('/production/work-orders/new?error=' . rawurlencode((string)($res['error'] ?? 'Error al iniciar OT.')));
        }
        return true;
    }

    if ($path === '/production/work-orders/init-unscheduled' && $method === 'POST') {
        requireCsrf();
        redirectResponse('/production/work-orders/new?error=' . rawurlencode('Productos fuera de planificación deshabilitados para evitar sobrecarga del sistema.'));
        return true;
    }

    // -------------------------------------------------------------------------
    // 3. ACCIONES Y FORMULARIOS DE LA OT (POST o GET especiales)
    // -------------------------------------------------------------------------
    // Guardar asignación de Anilox (editxid.php líneas 108-158)
    if (isset($_REQUEST['save_anilox']) && (int)$_REQUEST['save_anilox'] === 1) {
        if ($method === 'POST') {
            requireCsrf();
        }
        $agId = (int)($_REQUEST['agid'] ?? 0);
        if ($agId <= 0) {
            $activeOt = $prodService->getOpenWorkerOt($workerId, $plantaId);
            if ($activeOt !== null && (int)$activeOt['wok_ag_id'] > 0) {
                $agId = (int)$activeOt['wok_ag_id'];
            }
        }
        $agenda = $prodService->getAgendaById($agId);
        $reqId = (int)($agenda['ag_reqid'] ?? 0);
        $aniloxMap = [];
        for ($x = 1; $x <= 6; $x++) {
            if (isset($_REQUEST['anilox' . $x])) {
                $aniloxMap[$x] = (int)$_REQUEST['anilox' . $x];
            }
        }
        $res = $prodService->saveAniloxAssignments($agId, $reqId, $aniloxMap, (int)($_SESSION['user_id'] ?? 0));
        if ($res['ok'] && $method === 'POST') {
            $openOt = $prodService->getOpenWorkerOtByAgId($workerId, $plantaId, $agId);
            $workerOtId = (int)($openOt['id'] ?? 0);
            $aniloxInkRows = [];
            $unitCount = max(6, (int)($_REQUEST['anilox_unit_count'] ?? 0));
            for ($x = 1; $x <= $unitCount; $x++) {
                $row = [
                    'unidad' => $x,
                    'posicion' => $x,
                    'color_label' => trim((string)($_REQUEST['color' . $x] ?? '')),
                    'anilox_id' => (int)($aniloxMap[$x] ?? 0),
                    'ink_item_id' => (int)($_REQUEST['ink_item_' . $x] ?? 0),
                    'loaded_kg' => (float)str_replace(',', '.', (string)($_REQUEST['ink_total_loaded_' . $x] ?? $_REQUEST['ink_loaded_' . $x] ?? 0)),
                    'leftover_kg' => (float)str_replace(',', '.', (string)($_REQUEST['ink_leftover_' . $x] ?? 0)),
                    'is_mixed' => !empty($_REQUEST['ink_is_mix_' . $x]) ? 1 : 0,
                    'mixture_notes' => trim((string)($_REQUEST['mix_notes_' . $x] ?? '')),
                    'components' => [],
                ];

                $componentItems = $_REQUEST['mix_component_item_' . $x] ?? [];
                $componentQtys = $_REQUEST['mix_component_qty_' . $x] ?? [];
                if (is_array($componentItems) || is_array($componentQtys)) {
                    $componentItems = is_array($componentItems) ? $componentItems : [];
                    $componentQtys = is_array($componentQtys) ? $componentQtys : [];
                    $componentsCount = max(count($componentItems), count($componentQtys));
                    for ($mixIdx = 0; $mixIdx < $componentsCount; $mixIdx++) {
                        $row['components'][] = [
                            'item_id' => (int)($componentItems[$mixIdx] ?? 0),
                            'qty_kg' => (float)str_replace(',', '.', (string)($componentQtys[$mixIdx] ?? 0)),
                        ];
                    }
                }

                $aniloxInkRows[] = $row;
            }

            $inkRes = $prodService->saveFlexoAniloxInkConfig($agId, $reqId, $workerOtId, $aniloxInkRows);
            if (!($inkRes['ok'] ?? false)) {
                $res = $inkRes;
            }
        }
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_REQUEST['ajax']) && (int)$_REQUEST['ajax'] === 1)
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            return true;
        }
        if (!$res['ok']) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&error=' . rawurlencode($res['error']));
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&saved_anilox=1');
        }
        return true;
    }

    // Mover unidades de Anilox (editxid.php líneas 160-265)
    if (isset($_REQUEST['mover']) && in_array((int)$_REQUEST['mover'], [1, 2], true) && isset($_REQUEST['unidad'])) {
        $agId = (int)($_REQUEST['agid'] ?? 0);
        if ($agId <= 0) {
            $activeOt = $prodService->getOpenWorkerOt($workerId, $plantaId);
            if ($activeOt !== null && (int)$activeOt['wok_ag_id'] > 0) {
                $agId = (int)$activeOt['wok_ag_id'];
            }
        }
        $unidad = (int)$_REQUEST['unidad'];
        $mover = (int)$_REQUEST['mover'];
        $agenda = $prodService->getAgendaById($agId);
        $reqId = (int)($agenda['ag_reqid'] ?? 0);
        $res = $prodService->moveAniloxUnit($agId, $reqId, $unidad, $mover);
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_REQUEST['ajax']) && (int)$_REQUEST['ajax'] === 1)
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            return true;
        }
        if (!$res['ok']) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&error=' . rawurlencode($res['error']));
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId);
        }
        return true;
    }

    // Evento Delete (soporta GET o POST tradicional de editxid.php)
    if (($path === '/production/work-orders/events/delete' || (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'eventdelete')) && ($method === 'GET' || $method === 'POST')) {
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $delId = (int)($_REQUEST['delid'] ?? ($_REQUEST['event_id'] ?? 0));
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        if ($delId > 0) {
            $prodService->deleteEvent($delId, $workerOtId);
        }
        redirectResponse('/production/work-orders/operate?agid=' . $agId . '&event_deleted=1');
        return true;
    }

    // Guardar parámetros específicos (ej: bastidor pulpo)
    if (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'setspecparams') {
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $bastidorAmt = (int)($_REQUEST['bastidoramt'] ?? 0);
        $reqId = (int)($_REQUEST['req_id'] ?? 0);
        if ($reqId > 0 && $bastidorAmt > 0) {
            $erpPdo = Db::erpPdo();
            $stmt = $erpPdo->prepare("UPDATE orders SET req_operador_bastidoramt = :amt WHERE id = :id");
            $stmt->execute([':amt' => $bastidorAmt, ':id' => $reqId]);
        }
        redirectResponse('/production/work-orders/operate?agid=' . $agId . '&saved_params=1');
        return true;
    }

    // Guardar pesaje de bobinas y subproductos para Rebobinadora (editxid.php 347-380 / reboexec = save)
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

    // Guardar producción (productionsave - editxid.php 1226-1570)
    if (($path === '/production/work-orders/events/production' || (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'productionsave')) && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        $submode = trim((string)($_REQUEST['submode'] ?? ''));
        $actionType = trim((string)($_REQUEST['action_type'] ?? ''));
        $eventId = (int)($_REQUEST['refid'] ?? 0);
        $overrideEmbalaje = (int)($_REQUEST['overrideembalaje'] ?? 0);
        $comments = trim((string)($_REQUEST['evt_comments'] ?? ''));

        // Caso 1: Inicio de Tiraje de Producción (arranque de máquina)
        if ($actionType === 'start' || $submode === 'start' || ($eventId <= 0 && $submode !== 'end' && empty($_REQUEST['prod_amount']))) {
            $mtype = trim((string)($_REQUEST['evt_metrotype'] ?? ''));
            $res = $prodService->recordEventProduction(
                $workerOtId,
                0.0,
                0.0,
                0.0,
                0,
                0,
                $mtype,
                $comments !== '' ? $comments : 'Inicio de tiraje de producción',
                null,
                false,
                $overrideEmbalaje
            );
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&prod_started=1');
            return true;
        }

        $amount = (float)str_replace(',', '.', (string)($_REQUEST['prod_amount'] ?? 0));
        $amount2 = (float)str_replace(',', '.', (string)($_REQUEST['prod_amount2'] ?? 0));
        $bobinaKg = (float)str_replace(',', '.', (string)($_REQUEST['prod_bobina_kg'] ?? 0));
        $mmaq = (int)($_REQUEST['evt_amount_metros_maquina'] ?? 0);
        $mlin = (int)($_REQUEST['evt_amount_metros_lineales'] ?? 0);
        $mtype = trim((string)($_REQUEST['evt_metrotype'] ?? ''));
        $isEnd = ($submode === 'end' || $actionType === 'end');

        // Automatización: Si no vinieron kilos o metros, autocompletar con la suma de bobinas impresas emitidas
        if ($workerOtId > 0 && ($bobinaKg <= 0 || $mlin <= 0)) {
            $prSummary = $prodService->getPrintedRollsSummary($workerOtId);
            if (!empty($prSummary['rolls_count']) && $prSummary['rolls_count'] > 0) {
                if ($bobinaKg <= 0) $bobinaKg = (float)$prSummary['total_weight_kg'];
                if ($mlin <= 0) $mlin = (int)round($prSummary['total_meters']);
            }
        }

        // Procesar mermas y reparaciones por tipo
        $mermas = [];
        $repairs = [];
        foreach ($_REQUEST as $k => $v) {
            if (str_starts_with($k, 'merma_amount_')) {
                $tid = (int)substr($k, strlen('merma_amount_'));
                $mermas[$tid] = [
                    'amount' => (float)str_replace(',', '.', (string)$v),
                    'kgs' => (float)str_replace(',', '.', (string)($_REQUEST['merma_kgs_' . $tid] ?? 0)),
                    'mts' => (int)($_REQUEST['merma_mts_' . $tid] ?? 0),
                    'comments' => trim((string)($_REQUEST['merma_comments_' . $tid] ?? '')),
                ];
            } elseif (str_starts_with($k, 'repair_amount_')) {
                $tid = (int)substr($k, strlen('repair_amount_'));
                $repairs[$tid] = [
                    'amount' => (float)str_replace(',', '.', (string)$v),
                    'kgs' => (float)str_replace(',', '.', (string)($_REQUEST['repair_kgs_' . $tid] ?? 0)),
                    'comments' => trim((string)($_REQUEST['repair_comments_' . $tid] ?? '')),
                ];
            }
        }

        $amountFrente = (float)str_replace(',', '.', (string)($_REQUEST['amount_pulpo_frente'] ?? $_REQUEST['idx_pulpo_seri_pasadas_inp_frente'] ?? 0));
        $amountDorso = (float)str_replace(',', '.', (string)($_REQUEST['amount_pulpo_dorso'] ?? $_REQUEST['idx_pulpo_seri_pasadas_inp_dorso'] ?? 0));
        $seriCounterValues = [];
        foreach ($_REQUEST as $reqKey => $reqVal) {
            if (!str_starts_with((string)$reqKey, 'seri_counter_color_')) {
                continue;
            }
            $colorIdx = (int)substr((string)$reqKey, strlen('seri_counter_color_'));
            if ($colorIdx <= 0) {
                continue;
            }
            $seriCounterValues[$colorIdx] = (float)str_replace(',', '.', (string)$reqVal);
        }
        if (!empty($seriCounterValues)) {
            $seriDevPrints = max(1, (int)($_REQUEST['seri_bastidor_multiplier'] ?? $_REQUEST['seri_devprints_multiplier'] ?? ($agenda['req_operador_bastidoramt'] ?? 1)));
            $seriColorIndexes = array_keys($seriCounterValues);
            sort($seriColorIndexes);
            $seriPrevCounters = array_fill_keys($seriColorIndexes, 0.0);
            if ($workerOtId > 0) {
                $seriPrevEvents = $prodService->getWorkOrderEvents($workerOtId);
                foreach ($seriPrevEvents as $seriPrevEvent) {
                    if (!in_array((string)($seriPrevEvent['evt_type'] ?? ''), ['prod', 'prodsericolor'], true)) {
                        continue;
                    }
                    if ((int)($seriPrevEvent['id'] ?? 0) === $refId) {
                        continue;
                    }
                    $seriPrevComments = (string)($seriPrevEvent['evt_comments'] ?? '');
                    if (!preg_match('/\[SERI_COUNTERS:\s*(.*?)\]/', $seriPrevComments, $seriPrevMetaMatch)) {
                        continue;
                    }
                    $seriPrevMeta = trim((string)($seriPrevMetaMatch[1] ?? ''));
                    $seriPrevMeta = trim((string)preg_replace('/\s+\|\s+(?:img\/bast|imp\/des)=.*$/', '', $seriPrevMeta));
                    if ($seriPrevMeta === '') {
                        continue;
                    }
                    foreach (preg_split('/\s*,\s*/', $seriPrevMeta) as $seriPrevToken) {
                        if (!preg_match('/^C(\d+):.*?=(\d+(?:\.\d+)?)$/', trim((string)$seriPrevToken), $seriPrevCounterMatch)) {
                            continue;
                        }
                        $seriPrevIdx = (int)$seriPrevCounterMatch[1];
                        if (!array_key_exists($seriPrevIdx, $seriPrevCounters)) {
                            continue;
                        }
                        $seriPrevCounters[$seriPrevIdx] += (float)$seriPrevCounterMatch[2];
                    }
                }
            }

            $seriPrevFinishedBase = !empty($seriPrevCounters) ? (float)min($seriPrevCounters) : 0.0;
            $seriNewTotals = [];
            foreach ($seriColorIndexes as $seriColorIdx) {
                $seriNewTotals[$seriColorIdx] = (float)($seriPrevCounters[$seriColorIdx] ?? 0) + (float)($seriCounterValues[$seriColorIdx] ?? 0);
            }
            $seriNewFinishedBase = !empty($seriNewTotals) ? (float)min($seriNewTotals) : 0.0;
            $seriFinishedDeltaBase = max(0.0, $seriNewFinishedBase - $seriPrevFinishedBase);

            $amount2 = $seriFinishedDeltaBase;
            if ($amount <= 0) {
                $amount = $seriFinishedDeltaBase * $seriDevPrints;
            }

            $commentsBase = preg_replace('/\s*\[SERI_COUNTERS:[^\]]*\]\s*$/', '', $comments);
            $comments = is_string($commentsBase) ? trim($commentsBase) : $comments;
            $seriCounterParts = [];
            foreach ($seriCounterValues as $colorIdx => $counterVal) {
                if ($counterVal <= 0) {
                    continue;
                }
                $colorLabel = trim((string)($_REQUEST['seri_counter_label_' . $colorIdx] ?? ('Color ' . $colorIdx)));
                $seriCounterParts[] = 'C' . $colorIdx . ':' . $colorLabel . '=' . (int)round($counterVal);
            }
            if (!empty($seriCounterParts)) {
                $seriMeta = '[SERI_COUNTERS: ' . implode(', ', $seriCounterParts) . ' | img/bast=' . $seriDevPrints . ' | base=' . (int)round($seriFinishedDeltaBase) . ']';
                $comments = $comments !== '' ? ($comments . ' ' . $seriMeta) : $seriMeta;
            }
        }

        // Lógica de cálculo acumulativo de bolsas terminadas en Pulpo Serigráfico:
        // Considera las pasadas previas de frente y dorso guardadas en la OT para acumular bolsas terminadas.
        // Ejemplo: Si previamente se hicieron 1000 frentes y 200 dorsos (200 bolsas terminadas y 800 pendientes),
        // y en este tiraje se hacen 600 dorsos más, se terminan 600 bolsas más y quedan 200 frentes pendientes sin dorso.
        if ($amountFrente > 0 || $amountDorso > 0) {
            $prevFrente = 0.0;
            $prevDorso = 0.0;
            $prevFinished = 0.0;
            try {
                $stmtPrev = Db::erpPdo()->prepare(
                    "SELECT SUM(t3.evt_amount) AS prev_amt,
                            SUM(t3.evt_amount_pulpo_frente) AS prev_frente,
                            SUM(t3.evt_amount_pulpo_dorso) AS prev_dorso
                     FROM prod_worker_ot t2
                     INNER JOIN prod_worker_ot_events t3 ON t3.evt_prod_worker_otid = t2.id
                     WHERE t2.wok_ag_id = :ag_id
                       AND t3.evt_type = 'prod'
                       AND t3.evt_status > 0"
                    . ($eventId > 0 ? " AND t3.id != " . (int)$eventId : "")
                );
                $stmtPrev->execute([':ag_id' => $agId]);
                $pRow = $stmtPrev->fetch();
                if (is_array($pRow)) {
                    $prevFrente = (float)($pRow['prev_frente'] ?? 0);
                    $prevDorso = (float)($pRow['prev_dorso'] ?? 0);
                    $prevFinished = (float)($pRow['prev_amt'] ?? 0);
                }
            } catch (Throwable) {}

            $fCum = $prevFrente + $amountFrente;
            $dCum = $prevDorso + $amountDorso;

            // Verificar si la OT lleva dorso
            $hasDorsoColor = false;
            try {
                $stmtColors = Db::erpPdo()->prepare("SELECT fab_print_colors_back_1, fab_print_colors_back_2, fab_print_colors_back_3, fab_print_colors_back_4, fab_print_colors_back_5 FROM orders_items WHERE req_id = (SELECT ag_reqid FROM prod_agenda WHERE id = :agid LIMIT 1)");
                $stmtColors->execute([':agid' => $agId]);
                $cRow = $stmtColors->fetch();
                if (is_array($cRow)) {
                    for ($ci = 1; $ci <= 5; $ci++) {
                        if (!empty($cRow["fab_print_colors_back_{$ci}"])) {
                            $hasDorsoColor = true;
                            break;
                        }
                    }
                }
            } catch (Throwable) {}

            if ($hasDorsoColor || $amountDorso > 0 || $prevDorso > 0) {
                $finishedCum = min($fCum, $dCum);
                $calcPulpoAmount = max(0.0, $finishedCum - $prevFinished);
            } else {
                $calcPulpoAmount = $amountFrente;
            }

            if ($amount <= 0 || isset($_REQUEST['amount_pulpo_frente']) || isset($_REQUEST['amount_pulpo_dorso'])) {
                $amount = $calcPulpoAmount;
            }
        }

        $prodService->recordEventProduction(
            $workerOtId,
            $amount,
            $amount2,
            $bobinaKg,
            $mmaq,
            $mlin,
            $mtype,
            $comments,
            $eventId > 0 ? $eventId : null,
            $isEnd,
            $overrideEmbalaje,
            $mermas,
            $repairs,
            $amountFrente,
            $amountDorso
        );

        $prodService->syncPulpoAgendaStats($agId);

        if ($isEnd) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&prod_ended=1');
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&saved_prod=1');
        }
        return true;
    }

    // Guardar Información de Fabricación Pulpo V2 (form.v2pulpo.php)
    if (($path === '/production/work-orders/events/pulpo-fabricacion' || (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'pulposave')) && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $colorMode = trim((string)($_REQUEST['colormode'] ?? 'Frente'));
        if ($colorMode !== 'Dorso') $colorMode = 'Frente';
        $header = [
            'temp_horno' => (int)($_POST['temp_horno'] ?? 0),
            'maq_speed' => (float)str_replace(',', '.', (string)($_POST['maq_speed'] ?? 0)),
            'horno_speed' => (float)str_replace(',', '.', (string)($_POST['horno_speed'] ?? 0)),
            'medidas_camillas' => trim((string)($_POST['medidas_camillas'] ?? '')),
        ];
        $units = [];
        for ($px = 0; $px < 10; $px++) {
            $units[$px] = [
                'color_id' => trim((string)($_POST["unit_color_{$px}"] ?? '')),
                'val_temp_secador' => (float)str_replace(',', '.', (string)($_POST["unit_temp_sec_{$px}"] ?? 0)),
                'val_num_bastidor' => trim((string)($_POST["unit_bastidor_{$px}"] ?? '')),
                'val_mesh' => (float)str_replace(',', '.', (string)($_POST["unit_mesh_{$px}"] ?? 0)),
                'val_total_tinta' => (float)str_replace(',', '.', (string)($_POST["unit_tinta_{$px}"] ?? 0)),
                'val_carga_tinta' => (float)str_replace(',', '.', (string)($_POST["unit_carga_tinta_{$px}"] ?? 0)),
                'val_sobrante_tinta' => (float)str_replace(',', '.', (string)($_POST["unit_sobrante_tinta_{$px}"] ?? 0)),
            ];
        }
        $ok = $prodService->savePulpoV2Data($agId, $colorMode, $header, $units);
        if ($ok) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&colormode=' . rawurlencode($colorMode) . '&saved_pulpo=1');
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&colormode=' . rawurlencode($colorMode) . '&error=' . rawurlencode('Error al guardar fabricación Pulpo.'));
        }
        return true;
    }

    // Guardar apertura / alistamiento (aperturasave)
    if (($path === '/production/work-orders/events/apertura' || (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'aperturasave')) && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        $medFrom = (int)($_REQUEST['evt_medida_fromid'] ?? 0);
        $medTo = (int)($_REQUEST['evt_medida_toid'] ?? 0);
        $idAyudante = (int)($_REQUEST['evt_idayudante'] ?? 0);
        $comments = trim((string)($_REQUEST['evt_comments'] ?? ''));
        $submode = trim((string)($_REQUEST['submode'] ?? ''));
        $eventId = (int)($_REQUEST['refid'] ?? 0);

        if ($workerOtId <= 0 && $agId > 0) {
            $openOt = $prodService->getOpenWorkerOtByAgId($workerId, $plantaId, $agId);
            if ($openOt !== null) {
                $workerOtId = (int)$openOt['id'];
            }
        }

        if ($workerOtId <= 0) {
            $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
            if ($activeInit !== null && $agId > 0) {
                $startRes = $prodService->startWorkerOt($agId, (int)$activeInit['id']);
                if ($startRes['ok']) {
                    $workerOtId = (int)$startRes['worker_ot_id'];
                }
            }
        }

        $isEndingAlis = in_array($submode, ['end', 'cerrar', 'end_and_autocontrol'], true);

        if ($eventId <= 0 && $workerOtId > 0 && $isEndingAlis) {
            $alisStatus = $prodService->getWorkOrderAlistamientoStatus($workerOtId);
            if (!empty($alisStatus['event']['id'])) {
                $eventId = (int)$alisStatus['event']['id'];
            }
        }

        if ($eventId > 0 && $isEndingAlis) {
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

            if (!empty($selectedPoints) && $workerOtId > 0) {
                $prodService->sendAutocontrolToSupervisor($workerOtId, $selectedPoints, (int)($_SESSION['user_id'] ?? 0));
            }

            $prodService->recordEventApertura($workerOtId, $medFrom, $medTo, $comments, $eventId, true, $idAyudante);

            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=autocontrol&alis_finished=1');
            return true;
        } elseif ($eventId > 0) {
            $prodService->recordEventApertura($workerOtId, $medFrom, $medTo, $comments, $eventId, false, $idAyudante);
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&alis_updated=1');
        } else {
            $prodService->recordEventApertura($workerOtId, $medFrom, $medTo, $comments, null, false, $idAyudante);
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&alis_started=1');
        }
        return true;
    }

    // Guardar pausa (pausesave)
    if (($path === '/production/work-orders/events/pause' || (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'pausesave')) && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        $pauseId = (int)($_REQUEST['evt_pause_id'] ?? 0);
        $comments = trim((string)($_REQUEST['evt_comments'] ?? ''));
        $submode = trim((string)($_REQUEST['submode'] ?? ''));
        $eventId = (int)($_REQUEST['refid'] ?? 0);

        if ($eventId > 0) {
            $isEnd = ($submode === 'end');
            $prodService->recordEventPause($workerOtId, $pauseId, $comments, $eventId, $isEnd);
        } else {
            $prodService->recordEventPause($workerOtId, $pauseId, $comments);
        }
        redirectResponse('/production/work-orders/operate?agid=' . $agId . '&saved_pause=1');
        return true;
    }

    // Guardar mantención (mantencionsave)
    if (($path === '/production/work-orders/events/mantencion' || (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'mantencionsave')) && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        $repairId = (int)($_REQUEST['evt_equipo_mantid'] ?? 0);
        $comments = trim((string)($_REQUEST['evt_comments'] ?? ''));
        $submode = trim((string)($_REQUEST['submode'] ?? ''));
        $eventId = (int)($_REQUEST['refid'] ?? 0);

        if ($eventId > 0 && $submode === 'end') {
            $prodService->endEvent($eventId, $workerOtId, 0, $comments);
        } else {
            $prodService->recordEventMantencion($workerOtId, $repairId, 0, $comments);
        }
        redirectResponse('/production/work-orders/operate?agid=' . $agId . '&saved_mant=1');
        return true;
    }

    // Guardar autocontrol de calidad del operario (autoctrlsave)
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

        // Asegurar cierre de evento de alistamiento
        $prodService->closeOpenAperturaEvent($workerOtId);

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

    // Aprobación de Partida por Supervisor (superautoctrlsave / liderautoctrlsave)
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
        $supUser = trim((string)($_REQUEST['axx_username'] ?? ''));
        $supPass = trim((string)($_REQUEST['axx_pass'] ?? ''));

        $selectedPoints = [];
        if (isset($_POST['superbaseacids']) && is_array($_POST['superbaseacids'])) {
            foreach ($_POST['superbaseacids'] as $baseacid) {
                $baseacid = (int)$baseacid;
                if (!empty($_POST['superacids_' . $baseacid])) {
                    $selectedPoints[] = $baseacid;
                }
            }
        } elseif (isset($_POST['liderbaseacids']) && is_array($_POST['liderbaseacids'])) {
            foreach ($_POST['liderbaseacids'] as $baseacid) {
                $baseacid = (int)$baseacid;
                if (!empty($_POST['lideracids_' . $baseacid])) {
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

    // Cierre de OT con validación de supervisor (close o terminarotsave)
    if (($path === '/production/work-orders/close' || (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'terminarotsave')) && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        $supUser = (string)($_REQUEST['sup_user'] ?? ($_REQUEST['askprdclose_axx_username'] ?? ''));
        $supPass = (string)($_REQUEST['sup_pass'] ?? ($_REQUEST['askprdclose_axx_pass'] ?? ''));

        $res = $prodService->closeWorkOrder($workerOtId, $supUser, $supPass);
        if ($res['ok']) {
            $prodService->syncPulpoAgendaStats($agId);
            redirectResponse('/production/work-orders/active?closed=1');
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=terminarot&error=' . rawurlencode((string)($res['error'] ?? 'Error al cerrar OT.')));
        }
        return true;
    }

    // Generar bobina semielaborada impresa (Flexografía)
    if ($path === '/production/work-orders/rolls/generate-semielaborated' && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_POST['agid'] ?? 0);
        $workerOtId = (int)($_POST['worker_ot_id'] ?? 0);
        $parentRollId = !empty($_POST['parent_roll_id']) ? (int)$_POST['parent_roll_id'] : null;
        $skuCode = trim((string)($_POST['sku_code'] ?? ''));
        $weightKg = (float)str_replace(',', '.', (string)($_POST['weight_kg'] ?? 0));
        $meters = (float)str_replace(',', '.', (string)($_POST['meters'] ?? 0));
        $autoPrint = !empty($_POST['auto_print']);

        // Calcular metros lineales automáticamente a partir del peso de la bobina saliente
        if ($meters <= 0 && $weightKg > 0) {
            $ctxAgenda = $prodService->getWorkOrderAgendaDetails($agId);
            $wCm = (float)($ctxAgenda['req_operador_tela_width'] ?? ($ctxAgenda['fab_med_width'] ?? 0));
            $g = (float)($ctxAgenda['fab_mat_gramms'] ?? 0);
            if ($parentRollId !== null && $parentRollId > 0 && Db::trzPdo() !== null) {
                $stParent = Db::trzPdo()->prepare("SELECT width_mm, microns FROM rolls WHERE id = :id LIMIT 1");
                $stParent->execute([':id' => $parentRollId]);
                $pRow = $stParent->fetch(PDO::FETCH_ASSOC);
                if (!empty($pRow['width_mm']) && (float)$pRow['width_mm'] > 0) {
                    $wCm = (float)$pRow['width_mm'] / 10.0;
                }
                if (!empty($pRow['microns']) && (float)$pRow['microns'] > 0) {
                    $g = (float)$pRow['microns'];
                }
            }
            if ($wCm <= 0) $wCm = 80.0;
            if ($g <= 0) $g = 80.0;
            $meters = round(($weightKg * 100000.0) / ($wCm * $g));
        }

        $res = $prodService->createOutputRollFromWorkOrder(
            $workerOtId,
            $parentRollId,
            $skuCode !== '' ? $skuCode : 'BOBINA-IMPRESA',
            $weightKg,
            $meters,
            500,
            $currentOperatorName
        );

        if ($res['ok']) {
            $childId = (int)$res['child_roll_id'];
            if ($autoPrint) {
                redirectResponse('/rolls/' . $childId . '/label?server_print=1');
            } else {
                redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=materiales&roll_created=' . $childId . '&roll_code=' . urlencode($res['child_roll_code']));
            }
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=materiales&error=' . rawurlencode((string)($res['error'] ?? 'Error al crear bobina semielaborada.')));
        }
        return true;
    }

    // Generar cajas de producto terminado y pallet (Selladora / Confección / Embalaje)
    if ($path === '/production/work-orders/boxes/generate' && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_POST['agid'] ?? 0);
        $workerOtId = (int)($_POST['worker_ot_id'] ?? 0);
        $skuFinal = trim((string)($_POST['sku_final'] ?? ''));
        $boxQty = (int)($_POST['box_qty'] ?? 0);
        $unitsPerBox = (int)($_POST['units_per_box'] ?? 0);
        $sourceRollId = !empty($_POST['source_roll_id']) ? (int)$_POST['source_roll_id'] : null;
        $boxesPerPallet = (int)($_POST['boxes_per_pallet'] ?? 0);
        $destMode = trim((string)($_POST['destination_mode'] ?? 'STOCK'));
        $custRef = trim((string)($_POST['customer_order_ref'] ?? ''));

        $res = $prodService->createOutputBoxesFromWorkOrder(
            $workerOtId,
            $skuFinal,
            $boxQty,
            $unitsPerBox,
            $currentOperatorName,
            $sourceRollId,
            $custRef !== '' ? $custRef : null,
            $boxesPerPallet,
            $destMode
        );

        if ($res['ok']) {
            $firstBoxId = !empty($res['box_ids']) ? (int)$res['box_ids'][0] : 0;
            $palletId = !empty($res['pallet_id']) ? (int)$res['pallet_id'] : 0;
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=embalaje&boxes_done=' . $boxQty . '&first_box=' . $firstBoxId . '&pallet_id=' . $palletId);
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=embalaje&error=' . rawurlencode((string)($res['error'] ?? 'Error al generar cajas.')));
        }
        return true;
    }

    // Asignación de materiales/bobina o insumos directos (tintas, bastidores, bolsas base)
    if ($path === '/production/work-orders/materials/assign' && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        $actionMode = trim((string)($_REQUEST['action_mode'] ?? ''));
        $comments = trim((string)($_REQUEST['comments'] ?? ''));
        $supUser = trim((string)($_REQUEST['sup_user'] ?? ''));
        $supPass = trim((string)($_REQUEST['sup_pass'] ?? ''));
        $requiresSupervisor = !empty($_REQUEST['requires_supervisor']);

        $rollIds = [];
        if (!empty($_REQUEST['roll_ids']) && is_array($_REQUEST['roll_ids'])) {
            foreach ($_REQUEST['roll_ids'] as $rid) {
                $rid = trim((string)$rid);
                if ($rid !== '') $rollIds[] = $rid;
            }
        } elseif (!empty($_REQUEST['roll_id'])) {
            $rid = trim((string)$_REQUEST['roll_id']);
            if ($rid !== '') $rollIds[] = $rid;
        }

        $rollQuantities = [];
        if (!empty($_REQUEST['roll_qty']) && is_array($_REQUEST['roll_qty'])) {
            foreach ($_REQUEST['roll_qty'] as $k => $q) {
                $rollQuantities[trim((string)$k)] = max(1, (int)$q);
            }
        }

        if ($actionMode === 'take_bodega3000' && !empty($rollIds)) {
            $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
            $equipoId = $activeInit !== null ? (int)($activeInit['win_equipoid'] ?? 0) : 0;
            $weightKg = (float)str_replace(',', '.', (string)($_REQUEST['weight_kg'] ?? 0));
            $confirmedTraceabilityCode = trim((string)($_REQUEST['confirmed_traceability_code'] ?? ''));
            $traceabilityMode = trim((string)($_REQUEST['traceability_mode'] ?? 'auto'));
            $res = $prodService->assignBodega3000RollToMachine(
                $workerOtId,
                (string)$rollIds[0],
                $weightKg,
                $currentOperatorName,
                $equipoId,
                $comments,
                false,
                $agId,
                $confirmedTraceabilityCode,
                $traceabilityMode
            );
            if (!empty($res['ok'])) {
                $assignedRollId = (int)($res['roll_id'] ?? 0);
                if ($assignedRollId > 0) {
                    $mountRes = $prodService->mountRollOnMachine($workerOtId, $assignedRollId, $currentOperatorName, $weightKg, $comments);
                    if (!empty($mountRes['ok'])) {
                        $res = [
                            'ok' => true,
                            'mounted' => true,
                            'entry_weight_kg' => (float)($mountRes['entry_weight_kg'] ?? $weightKg),
                            'roll_id' => $assignedRollId,
                            'roll_code' => (string)($res['roll_code'] ?? $mountRes['roll_code'] ?? ''),
                        ];
                    } else {
                        $res = [
                            'ok' => false,
                            'error' => (string)($mountRes['error'] ?? 'La bobina se tomó desde Bodega 3000, pero no se pudo montar directamente en máquina.'),
                        ];
                    }
                } else {
                    $res = [
                        'ok' => false,
                        'error' => 'La bobina se asignó a la OT, pero no se pudo resolver su identificador para montarla en máquina.',
                    ];
                }
            }
        } elseif (!empty($rollIds)) {
            $res = $prodService->requestRollsForWorkOrder(
                $workerOtId,
                $rollIds,
                $comments,
                $currentOperatorName,
                $supUser,
                $supPass,
                $requiresSupervisor,
                $rollQuantities
            );
        } else {
            $matType = trim((string)($_REQUEST['material_type'] ?? 'Insumo / Material Serigrafía'));
            $matQty = (float)str_replace(',', '.', (string)($_REQUEST['material_qty'] ?? 0));
            $matUnit = trim((string)($_REQUEST['material_unit'] ?? 'unid'));
            $desc = "Solicitud / Carga: {$matType} - Cant: {$matQty} {$matUnit}";
            if ($comments !== '') {
                $desc .= " ({$comments})";
            }
            $now = time();
            $erpPdo = Db::erpPdo();
            $stmtErp = $erpPdo->prepare(
                "INSERT INTO prod_worker_ot_events
                 (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                 VALUES
                 (:ot_id, :amt, :crtdat, :enddat, 2, 'materiales', :comments)"
            );
            $stmtErp->execute([
                ':ot_id' => $workerOtId,
                ':amt' => $matQty,
                ':crtdat' => $now,
                ':enddat' => $now,
                ':comments' => $desc,
            ]);
            $res = ['ok' => true];
        }

        if ($res['ok']) {
            if ($actionMode === 'take_bodega3000') {
                redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=materiales&mounted=1&m_kg=' . rawurlencode((string)($res['entry_weight_kg'] ?? $weightKg ?? 0)) . '&restore_scroll=1');
            }
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=materiales&saved_material=1');
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=materiales&error=' . rawurlencode((string)($res['error'] ?? 'Error al solicitar material.')));
        }
        return true;
    }

    if ($path === '/production/work-orders/inks/manage' && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        $inkAction = trim((string)($_REQUEST['ink_action'] ?? ''));
        $comments = trim((string)($_REQUEST['comments'] ?? ''));

        if ($workerOtId <= 0 && $agId > 0) {
            $openOt = $prodService->getOpenWorkerOtByAgId($workerId, $plantaId, $agId);
            if ($openOt !== null) {
                $workerOtId = (int)($openOt['id'] ?? 0);
            }
        }

        if ($workerOtId <= 0) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=tintas&error=' . rawurlencode('No se encontró la OT activa para registrar tintas.'));
        }

        if ($inkAction === 'load') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $qty = (float)str_replace(',', '.', (string)($_POST['qty_kg'] ?? 0));
            $res = $prodService->loadInkToMachine($workerOtId, $agId, $itemId, $qty, $currentOperatorName, $comments, 16);
            $param = $res['ok'] ? '&ink_loaded=1' : '&error=' . rawurlencode((string)($res['error'] ?? 'Error al cargar tinta a máquina.'));
        } elseif ($inkAction === 'consume') {
            $loadId = (int)($_POST['load_id'] ?? 0);
            $qty = (float)str_replace(',', '.', (string)($_POST['qty_kg'] ?? 0));
            $res = $prodService->consumeMachineInkLoad($loadId, $workerOtId, $qty, $currentOperatorName, $comments);
            $param = $res['ok'] ? '&ink_consumed=1' : '&error=' . rawurlencode((string)($res['error'] ?? 'Error al registrar consumo de tinta.'));
        } elseif ($inkAction === 'return') {
            $loadId = (int)($_POST['load_id'] ?? 0);
            $qty = (float)str_replace(',', '.', (string)($_POST['qty_kg'] ?? 0));
            $res = $prodService->returnMachineInkLoad($loadId, $workerOtId, $qty, $currentOperatorName, $comments);
            $param = $res['ok'] ? '&ink_returned=1' : '&error=' . rawurlencode((string)($res['error'] ?? 'Error al devolver tinta a 3000 Insumos de Producción.'));
        } else {
            $param = '&error=' . rawurlencode('Acción de tinta no válida.');
        }

        redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=tintas' . $param);
        return true;
    }

    // Montaje / Desmontaje / Devolución de Bobina en Máquina
    if ($path === '/production/work-orders/materials/mount' && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        $rollId = (int)($_REQUEST['roll_id'] ?? 0);
        $actionType = trim((string)($_REQUEST['action_type'] ?? 'mount'));
        $comments = trim((string)($_REQUEST['comments'] ?? ''));

        if ($actionType === 'mount') {
            $entryWeightKg = (float)str_replace(',', '.', (string)($_REQUEST['entry_weight_kg'] ?? 0));
            $res = $prodService->mountRollOnMachine($workerOtId, $rollId, $currentOperatorName, $entryWeightKg, $comments);
            $param = $res['ok'] ? ('&mounted=1&m_kg=' . ($res['entry_weight_kg'] ?? $entryWeightKg) . '&restore_scroll=1') : '&error=' . rawurlencode((string)($res['error'] ?? 'Error al montar bobina'));
        } elseif ($actionType === 'unmount') {
            $res = $prodService->unmountRollFromMachine($workerOtId, $rollId, $currentOperatorName);
            $param = $res['ok'] ? '&unmounted=1&restore_scroll=1' : '&error=' . rawurlencode((string)($res['error'] ?? 'Error al desmontar bobina'));
        } elseif ($actionType === 'unassign') {
            $res = $prodService->unassignRollFromWorkOrder($workerOtId, $rollId, $currentOperatorName);
            $param = $res['ok'] ? '&unassigned=1&restore_scroll=1' : '&error=' . rawurlencode((string)($res['error'] ?? 'Error al devolver bobina'));
        } else {
            $param = '&error=' . rawurlencode('Acción no válida');
        }

        redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=materiales' . $param);
        return true;
    }

    // Registro de Desmontaje y Salida de Bobina (Peso Bobina, mermas por tipo, sobrante trazable)
    if ($path === '/production/work-orders/materials/consume' && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        $rollId = (int)($_REQUEST['roll_id'] ?? 0);
        $outputBobinaKg = (float)str_replace(',', '.', (string)($_REQUEST['output_bobina_kg'] ?? $_REQUEST['output_binas_kg'] ?? 0));
        $scrapKg = (float)str_replace(',', '.', (string)($_REQUEST['scrap_weight'] ?? $_REQUEST['scrap_kg'] ?? 0));
        $leftoverKg = (float)str_replace(',', '.', (string)($_REQUEST['leftover_weight'] ?? $_REQUEST['leftover_kg'] ?? 0));
        $comments = trim((string)($_REQUEST['comments'] ?? ''));
        $createOutputRoll = !empty($_REQUEST['create_output_roll']);
        $consumeAgenda = $agId > 0 ? $prodService->getWorkOrderAgendaDetails($agId) : null;
        $outputSkuCode = trim((string)($_REQUEST['output_sku_code'] ?? ''));
        if ($outputSkuCode === '') {
            $outputSkuCode = trim((string)($consumeAgenda['item_number_prod'] ?? $consumeAgenda['item_title'] ?? $consumeAgenda['fab_type'] ?? ''));
        }
        $outputMeters = (float)str_replace(',', '.', (string)($_REQUEST['output_meters'] ?? 0));
        $autoPrintOutput = !empty($_REQUEST['auto_print_output']);
        $keepRemainingMounted = !empty($_REQUEST['keep_remaining_mounted']);
        $remainingMountedRollId = (int)($_REQUEST['remaining_mounted_roll_id'] ?? 0);
        $remainingMountedKg = (float)str_replace(',', '.', (string)($_REQUEST['remaining_mounted_kg'] ?? 0));

        // Desglose de mermas por tipo si vienen en el formulario
        $mermasDetail = [];
        $totalScrapDetail = 0.0;
        if (!empty($_POST['merma_kgs']) && is_array($_POST['merma_kgs'])) {
            foreach ($_POST['merma_kgs'] as $mtId => $val) {
                $kgs = (float)str_replace(',', '.', (string)$val);
                $comm = trim((string)($_POST['merma_comm'][$mtId] ?? ''));
                if ($kgs > 0 || $comm !== '') {
                    $mermasDetail[(int)$mtId] = [
                        'kgs' => $kgs,
                        'comments' => $comm,
                    ];
                    $totalScrapDetail += $kgs;
                }
            }
        }
        if ($totalScrapDetail > 0) {
            $scrapKg = $totalScrapDetail;
        }
        $mergedSourceRollIds = [];
        if (!empty($_POST['source_roll_ids']) && is_array($_POST['source_roll_ids'])) {
            foreach ($_POST['source_roll_ids'] as $sourceRollId) {
                $sourceRollId = (int)$sourceRollId;
                if ($sourceRollId > 0 && $sourceRollId !== $rollId) {
                    $mergedSourceRollIds[] = $sourceRollId;
                }
            }
            $mergedSourceRollIds = array_values(array_unique($mergedSourceRollIds));
        }

        $res = $prodService->recordRollOutputAndLeftover(
            $workerOtId,
            $rollId,
            $outputBobinaKg,
            $scrapKg,
            $leftoverKg,
            $comments,
            $currentOperatorName,
            $mermasDetail,
            $createOutputRoll,
            $outputSkuCode,
            $outputMeters,
            $mergedSourceRollIds,
            $keepRemainingMounted,
            $remainingMountedRollId,
            $remainingMountedKg
        );

        if ($res['ok']) {
            $param = '&saved_output=1';
            if (!empty($res['output_roll_code'])) {
                $param .= '&output_id=' . (int)($res['output_roll_id'] ?? 0)
                    . '&output_code=' . rawurlencode((string)$res['output_roll_code'])
                    . '&output_kg=' . rawurlencode((string)($res['output_bobina_kg'] ?? 0));
                if (!empty($res['output_meters'])) {
                    $param .= '&output_meters=' . rawurlencode((string)$res['output_meters']);
                }
                if ($autoPrintOutput) {
                    $param .= '&output_autoprint=1';
                }
            }
            if (!empty($res['leftover_roll_code'])) {
                $param .= '&leftover_id=' . (int)($res['leftover_roll_id'] ?? 0) . '&leftover_code=' . rawurlencode((string)$res['leftover_roll_code']) . '&leftover_kg=' . $res['leftover_kg'];
            }
            if (!empty($res['remaining_mounted_roll_code'])) {
                $param .= '&remaining_mounted_code=' . rawurlencode((string)$res['remaining_mounted_roll_code'])
                    . '&remaining_mounted_kg=' . rawurlencode((string)($res['remaining_mounted_kg'] ?? 0));
            }
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=materiales' . $param);
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=materiales&error=' . rawurlencode((string)($res['error'] ?? 'Error al registrar salida de bobina.')));
        }
        return true;
    }


    // -------------------------------------------------------------------------
    // 4. CONSOLA DE OPERACIÓN DE LA OT (/production/work-orders/operate · mid=2)
    // -------------------------------------------------------------------------
    $isOperateRoute = ($path === '/production/work-orders/operate');
    $agIdFromPath = 0;
    if (!$isOperateRoute && preg_match('#^/production/work-orders/(\d+)/operate$#', $path, $mOperate) === 1) {
        $isOperateRoute = true;
        $agIdFromPath = (int)$mOperate[1];
    }

    if ($isOperateRoute) {
        $agId = $agIdFromPath > 0 ? $agIdFromPath : (int)($_REQUEST['agid'] ?? 0);
        if ($agId <= 0) {
            // Intentar cargar la OT actualmente abierta del trabajador
            $activeOt = $prodService->getOpenWorkerOt($workerId, $plantaId);
            if ($activeOt !== null && (int)$activeOt['wok_ag_id'] > 0) {
                $agId = (int)$activeOt['wok_ag_id'];
            }
        }

        if ($agId <= 0) {
            redirectResponse('/production/work-orders/new');
            return true;
        }

        if ($method === 'POST') {
            requireCsrf();
            if (!empty($_POST['delsthids'])) {
                $prodService->deleteReboStockChanges((array)$_POST['delsthids'], (int)($_SESSION['user_id'] ?? 4075));
                redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=reboprod');
                return true;
            }
        }

        $scanReboInput = !empty($_POST['scan_rebo_code']) ? trim((string)$_POST['scan_rebo_code']) : (!empty($_GET['scan_rebo_code']) ? trim((string)$_GET['scan_rebo_code']) : '');
        if ($scanReboInput !== '') {
            $agenda = $prodService->getAgendaById($agId);
            $scanRes = $prodService->scanAndPrepareReboRoll($scanReboInput, $agenda ?: [], (int)($_SESSION['user_id'] ?? 4075));
            if ($scanRes['ok'] && !empty($scanRes['item_id'])) {
                $scannedId = (int)$scanRes['item_id'];
                $rollId = (int)($scanRes['roll_id'] ?? 0);
                $rCode = (string)($scanRes['roll_code'] ?? $scanReboInput);
                redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=reboprod&selitemid1=' . $scannedId . '&roll_id=' . $rollId . '&scan_ok=1&scanned_code=' . urlencode($rCode));
                return true;
            } else {
                $errMsg = $scanRes['error'] ?? "No se encontró la bobina escaneada '{$scanReboInput}'.";
                redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=reboprod&scan_err=' . urlencode($errMsg));
                return true;
            }
        }

        if ($method === 'POST') {
            if (!empty($_POST['rebofinalize'])) {
                $openOt = $prodService->getOpenWorkerOt($workerId, $plantaId);
                $workerOtId = $openOt !== null ? (int)$openOt['id'] : 0;
                $resRebo = $prodService->executeReboFinalize($agId, $_POST, (int)($_SESSION['user_id'] ?? 4075), (int)($_SESSION['company_id'] ?? 20010), $workerOtId, $currentOperatorName);
                $cutCount = !empty($resRebo['created_rolls']) ? count($resRebo['created_rolls']) : 0;
                $firstBagRollId = !empty($resRebo['created_bag_rolls'][0]['id'])
                    ? (int)$resRebo['created_bag_rolls'][0]['id']
                    : (!empty($resRebo['created_rolls'][0]['id']) ? (int)$resRebo['created_rolls'][0]['id'] : 0);
                $palletId = !empty($resRebo['handle_pallet']['id']) ? (int)$resRebo['handle_pallet']['id'] : 0;
                $handleCount = !empty($resRebo['created_handle_rolls']) ? count($resRebo['created_handle_rolls']) : 0;
                redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=reboprod&rebo_done=1&cuts=' . $cutCount . '&first_cut=' . $firstBagRollId . '&pallet_id=' . $palletId . '&handle_rolls=' . $handleCount);
                return true;
            }
        }

        $ctx = $prodService->getWorkOrderOperatingContext($agId, $workerId, $plantaId);
        if ($ctx === null) {
            http_response_code(404);
            $content = '<div style="background:#fff;padding:25px;border-radius:5px;border:1px solid #ddd;color:#c00;font-weight:bold">'
                . '<i class="fa fa-exclamation-triangle"></i> No se encontró la información de la orden de trabajo (ID: ' . $agId . ').'
                . '<div style="margin-top:15px"><a href="/production/work-orders/new" class="btnblue" style="text-decoration:none"><i class="fa fa-arrow-left"></i> Volver a listado</a></div></div>';
            renderProdwrkShell('OT no encontrada', $content, 2, $currentOperatorName, $plantaName);
            return true;
        }

        $workerOt = $ctx['worker_ot'];
        if ($workerOt === null && !empty($ctx['machine_init']['id'])) {
            $initId = (int)$ctx['machine_init']['id'];
            $startRes = $prodService->startWorkerOt($agId, $initId);
            if ($startRes['ok']) {
                $ctx = $prodService->getWorkOrderOperatingContext($agId, $workerId, $plantaId) ?? $ctx;
                $workerOt = $ctx['worker_ot'];
            }
        }
        $workerOtId = $workerOt !== null ? (int)$workerOt['id'] : 0;
        $events = $workerOtId > 0 ? $prodService->getWorkOrderEvents($workerOtId) : [];
        $pauses = $prodService->listPauseTypes();
        $repairs = $prodService->listRepairTypes();
        $medidas = $prodService->listMedidas();

        $equipoTypeId = (int)($ctx['machine_init']['equipo_type_id'] ?? 0);
        $autocontrolPoints = $prodService->listAutocontrolPoints($equipoTypeId);
        $autocontrolResponses = $workerOtId > 0 ? $prodService->getAutocontrolResponses($workerOtId) : [];
        $activeStandby = (!empty($ctx['machine_init']['id']))
            ? $prodService->getActiveMachineStandby((int)$ctx['machine_init']['win_equipoid'], (int)$ctx['machine_init']['id'])
            : null;
        $ctx['active_standby'] = $activeStandby;

        // Filtro inteligente de bobinas según especificaciones de la OT (carga bajo demanda solo en modo materiales/cargar)
        $targetColor = (string)($ctx['agenda']['fabric_color'] ?? $ctx['agenda']['ag_color_tela'] ?? '');
        $targetWidth = (float)($ctx['agenda']['req_operador_tela_width'] ?? $ctx['agenda']['fab_med_width'] ?? 0);
        $targetMaterial = (string)($ctx['agenda']['fab_type'] ?? '');
        $targetGrammage = (float)($ctx['agenda']['fab_mat_gramms'] ?? 0);

        $availableRolls = [];
        $materialQuota = [
            'target_color' => $targetColor,
            'target_material' => $targetMaterial,
            'target_width' => $targetWidth,
            'target_grammage' => $targetGrammage,
        ];

        $mode = trim((string)($_GET['mode'] ?? $_POST['mode'] ?? ''));
        if ($mode === 'prod') {
            $mode = 'production';
        }
        if ($mode === 'semielaborado') {
            $mode = 'materiales';
        }
        if ($mode === 'materiales' || $mode === 'cargar') {
            $availableRolls = $prodService->listAvailableRollsInBodega3000(
                $agId,
                150,
                null,
                (int)($ctx['machine_init']['win_equipoid'] ?? 0),
                true
            );

            $materialQuota = $prodService->getWorkOrderMaterialQuota($agId, $workerOtId);
            $materialQuota['target_color'] = $targetColor;
            $materialQuota['target_material'] = $targetMaterial;
            $materialQuota['target_width'] = $targetWidth;
            $materialQuota['target_grammage'] = $targetGrammage;
        }
        $ctx['material_quota'] = $materialQuota;

        $alisStatus = $workerOtId > 0
            ? $prodService->getWorkOrderAlistamientoStatus($workerOtId)
            : ['has_apertura' => false, 'has_alis' => false, 'event' => null];
        $autoctrlStatus = $workerOtId > 0
            ? $prodService->getWorkOrderAutocontrolStatus($workerOtId, $equipoTypeId)
            : ['has_points' => false, 'points' => [], 'goto_autocontrol' => false, 'worker_completed' => true, 'super_inputs' => 0, 'super_pending' => false, 'super_approved' => true, 'worker_responses' => [], 'super_responses' => []];
        $helpers = $prodService->listActiveHelpers();

        // Datos Pulpo V2 y Bastidores
        $hasBackColors = false;
        for ($c = 1; $c <= 10; $c++) {
            if (!empty($ctx['agenda']["fab_print_colors_back_{$c}"])) {
                $hasBackColors = true;
                break;
            }
        }
        $colorMode = trim((string)($_GET['colormode'] ?? 'Frente'));
        if ($colorMode !== 'Dorso' || !$hasBackColors) {
            $colorMode = 'Frente';
        }
        $ctx['pulpo_v2'] = $prodService->getPulpoV2Data($agId, $colorMode);
        $ctx['bastidores'] = $prodService->listBastidores();
        $ctx['colormode'] = $colorMode;
        $ctx['has_back_colors'] = $hasBackColors;
        $ctx['prod_service'] = $prodService;
        $activeInitForInk = (isset($activeInit) && is_array($activeInit)) ? $activeInit : [];
        $isFlexoContext = (int)($activeInitForInk['equipo_prod_isprinter_flexo'] ?? ($ctx['agenda']['equipo_prod_isprinter_flexo'] ?? 0)) === 1;
        if ($isFlexoContext) {
            $inkWarehouse = productionResolveInkWarehouseForAgenda($activeInitForInk, $ctx['agenda'] ?? null);
            $ctx['ink_warehouse'] = $inkWarehouse;
            $ctx['ink_items'] = $receptionService->listWarehouseItemsByErpStorehouseId(
                (int)($inkWarehouse['erp_storehouse_id'] ?? 16),
                true,
                (string)($inkWarehouse['item_prefix'] ?? 'TIN')
            );
        } else {
            $ctx['ink_warehouse'] = [];
            $ctx['ink_items'] = [];
        }
        $ctx['machine_ink_loads'] = $workerOtId > 0 ? $prodService->listMachineInkLoadsForWorkOrder($workerOtId, false) : [];
        $ctx['bodega3000_ink_items'] = $receptionService->listWarehouseItemsByErpStorehouseId(16, true, 'TIN');

        renderProductionOperatorConsole(
            $ctx,
            $events,
            $pauses,
            $repairs,
            $medidas,
            $autocontrolPoints,
            $autocontrolResponses,
            $availableRolls,
            $currentOperatorName,
            $plantaName,
            $alisStatus,
            $autoctrlStatus,
            $helpers
        );
        return true;
    }

    // -------------------------------------------------------------------------
    // 5. MONITOREO: OTs EN CURSO (mid=5) E HISTORIAL (mid=3)
    // -------------------------------------------------------------------------
    if ($path === '/production/work-orders/active' && $method === 'GET') {
        $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
        $showAll = !empty($_GET['all']);
        $filterEquipoId = (!$showAll && $activeInit !== null) ? (int)$activeInit['win_equipoid'] : 0;

        $equipoTypeId = (int)($activeInit['equipo_type_id'] ?? 0);
        $isFlexo = ($equipoTypeId === 7
            || (int)($activeInit['equipo_prod_isprinter_flexo'] ?? 0) === 1
            || stripos((string)($activeInit['type_ant_title'] ?? ''), 'flexo') !== false
            || stripos((string)($activeInit['equipo_name'] ?? ''), 'flexo') !== false);
        $isSeri = ((int)($activeInit['equipo_prod_isprinter_seri'] ?? 0) === 1)
            || ($equipoTypeId === 11 || $equipoTypeId === 8 || $equipoTypeId === 22)
            || stripos((string)($activeInit['type_ant_title'] ?? ''), 'seri') !== false
            || stripos((string)($activeInit['equipo_name'] ?? ''), 'seri') !== false
            || stripos((string)($activeInit['equipo_name'] ?? ''), 'pulpo') !== false;

        $dateFilter = null;
        if ($isFlexo || $isSeri) {
            $reqPlanDate = trim((string)($_GET['plan_date'] ?? ''));
            if ($reqPlanDate === 'all') {
                $dateFilter = null;
            } elseif ($reqPlanDate !== '') {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $reqPlanDate, $m)) {
                    $dateFilter = date('d.m.Y', strtotime($reqPlanDate));
                } else {
                    $dateFilter = $reqPlanDate;
                }
            } else {
                $dateFilter = date('d.m.Y');
            }
        } else {
            $reqPlanDate = trim((string)($_GET['plan_date'] ?? ''));
            if ($reqPlanDate !== '' && $reqPlanDate !== 'all') {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $reqPlanDate, $m)) {
                    $dateFilter = date('d.m.Y', strtotime($reqPlanDate));
                } else {
                    $dateFilter = $reqPlanDate;
                }
            }
        }

        $activeList = $prodService->listActiveOrdersInCourse($plantaId, $filterEquipoId, $dateFilter);
        $activeStandby = ($activeInit !== null) ? $prodService->getActiveMachineStandby((int)$activeInit['win_equipoid'], (int)$activeInit['id']) : null;
        renderProductionActiveOrdersScreen($activeList, $currentOperatorName, $plantaName, $activeInit, $activeStandby, $dateFilter);
        return true;
    }

    if ($path === '/production/work-orders/history' && $method === 'GET') {
        $historyList = $prodService->listHistoryOrders($plantaId, 60);
        renderProductionHistoryScreen($historyList, $currentOperatorName, $plantaName);
        return true;
    }

    if (($path === '/production/traceability' || $path === '/stock/traceability') && $method === 'GET') {
        $query = trim((string)($_GET['q'] ?? ($_GET['query'] ?? '')));
        $tree = $query !== '' ? $prodService->getTraceabilityTree($query) : null;
        renderProductionTraceabilityScreen($query, $tree, $currentOperatorName, $plantaName);
        return true;
    }

    // -------------------------------------------------------------------------
    // SOLICITAR MATERIALES / BOBINAS EN BODEGA 3000 (Menú lateral · mid=7 y mid=8)
    // -------------------------------------------------------------------------
    if ($path === '/production/materials/request' && $method === 'GET') {
        $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
        if ($activeInit === null) {
            renderProductionNeedMachineScreen($currentOperatorName, $plantaName);
            return true;
        }

        $equipoId = (int)$activeInit['win_equipoid'];
        $activeOrders = $prodService->listActiveOrdersInCourse($plantaId, $equipoId);
        $scheduledOrders = $prodService->listScheduledOrdersForMachine($equipoId, $plantaId, date('Y-m-d'));
        $unscheduledOrders = [];

        $selectedAgId = isset($_GET['agid']) ? (int)$_GET['agid'] : (int)($_GET['ag_id'] ?? 0);
        $prdId = (int)($_GET['prd_id'] ?? 0);
        if ($selectedAgId <= 0 && $prdId > 0 && $equipoId > 0) {
            $selectedAgId = $prodService->ensureAgendaForUnscheduledOrder($prdId, $equipoId, $plantaId);
        }
        if ($selectedAgId <= 0 && !empty($activeOrders)) {
            $selectedAgId = (int)$activeOrders[0]['ag_id'];
        } elseif ($selectedAgId <= 0 && !empty($scheduledOrders)) {
            $selectedAgId = (int)$scheduledOrders[0]['ag_id'];
        }

        $selectedAgenda = $selectedAgId > 0 ? $prodService->getWorkOrderAgendaDetails($selectedAgId) : null;
        $workerOt = ($selectedAgId > 0) ? $prodService->getOpenWorkerOtByAgId($workerId, $plantaId, $selectedAgId) : null;
        $workerOtId = $workerOt !== null ? (int)$workerOt['id'] : 0;

        $bodega3000Rolls = $prodService->listAvailableRollsInBodega3000($selectedAgId, 60, null, $equipoId, true);
        $centralRolls = ($selectedAgenda !== null) ? $prodService->listAvailableRollsForProduction(
            null,
            60,
            (string)($selectedAgenda['fabric_color'] ?? ''),
            (float)($selectedAgenda['req_operador_tela_width'] ?? 0),
            (string)($selectedAgenda['fab_type'] ?? ''),
            (float)($selectedAgenda['fab_mat_gramms'] ?? 0)
        ) : [];

        $assignedRolls = ($workerOtId > 0) ? ($prodService->getWorkOrderMaterialQuota($selectedAgId, $workerOtId)['assigned_rolls'] ?? []) : [];
        $inkWarehouse = productionResolveInkWarehouseForAgenda($activeInit, $selectedAgenda);
        $chemicals = $receptionService->listWarehouseItemsByErpStorehouseId(
            (int)($inkWarehouse['erp_storehouse_id'] ?? 16),
            true,
            (string)($inkWarehouse['item_prefix'] ?? 'TIN')
        );

        renderProductionMaterialsRequestScreen(
            $activeInit,
            $activeOrders,
            $scheduledOrders,
            $selectedAgId,
            $selectedAgenda,
            $workerOtId,
            $bodega3000Rolls,
            $centralRolls,
            $assignedRolls,
            $chemicals,
            $inkWarehouse,
            $currentOperatorName,
            $plantaName,
            $unscheduledOrders
        );
        return true;
    }

    if ($path === '/production/materials/request/assign' && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_POST['agid'] ?? 0);
        $workerOtId = (int)($_POST['worker_ot_id'] ?? 0);
        $actionMode = trim((string)($_POST['action_mode'] ?? 'bodega3000'));
        $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
        $equipoId = $activeInit !== null ? (int)$activeInit['win_equipoid'] : 0;

        if ($workerOtId <= 0 && $agId > 0 && $activeInit !== null) {
            $startRes = $prodService->startWorkerOt($agId, (int)$activeInit['id']);
            if ($startRes['ok']) {
                $workerOtId = (int)$startRes['worker_ot_id'];
            }
        }

        if ($actionMode === 'manual_sin_trazabilidad') {
            $mat = trim((string)($_POST['manual_material'] ?? 'TNT'));
            $col = trim((string)($_POST['manual_color'] ?? ''));
            $wCm = (float)str_replace(',', '.', (string)($_POST['manual_width'] ?? 0));
            $kilos = (float)str_replace(',', '.', (string)($_POST['manual_kg'] ?? 0));
            $metros = (float)str_replace(',', '.', (string)($_POST['manual_meters'] ?? 0));
            $comm = trim((string)($_POST['manual_comments'] ?? ''));

            $ident = "BOBINA-MANUAL-{$mat}" . ($col !== '' ? "-{$col}" : '');
            if ($wCm > 0) $ident .= "-{$wCm}cm";

            $res = $prodService->assignBodega3000RollToMachine(
                $workerOtId,
                $ident,
                $kilos,
                $currentOperatorName,
                $equipoId,
                "Material: {$mat}, Color: {$col}, Ancho: {$wCm}cm, Metros: {$metros}m. {$comm}",
                true,
                $agId
            );
            if ($res['ok']) {
                redirectResponse('/production/materials/request?agid=' . $agId . '&saved_material=1&no_trace=1');
            } else {
                redirectResponse('/production/materials/request?agid=' . $agId . '&error=' . rawurlencode((string)($res['error'] ?? 'Error al cargar bobina manual.')));
            }
            return true;
        } elseif ($actionMode === 'bodega3000') {
            $rollIds = (array)($_POST['roll_ids'] ?? []);
            if (empty($rollIds) && !empty($_POST['roll_id'])) {
                $rollIds = [$_POST['roll_id']];
            }
            $weightKg = (float)str_replace(',', '.', (string)($_POST['weight_kg'] ?? 0));
            $comments = trim((string)($_POST['comments'] ?? ''));
            $confirmedTraceabilityCode = trim((string)($_POST['confirmed_traceability_code'] ?? ''));
            $traceabilityMode = trim((string)($_POST['traceability_mode'] ?? 'auto'));

            $lastRes = ['ok' => false, 'error' => 'No se seleccionó ninguna bobina de Bodega 3000.'];
            foreach ($rollIds as $rid) {
                $lastRes = $prodService->assignBodega3000RollToMachine(
                    $workerOtId,
                    (string)$rid,
                    $weightKg,
                    $currentOperatorName,
                    $equipoId,
                    $comments,
                    false,
                    $agId,
                    $confirmedTraceabilityCode,
                    $traceabilityMode
                );
            }
            if ($lastRes['ok']) {
                redirectResponse('/production/materials/request?agid=' . $agId . '&saved_material=1');
            } else {
                redirectResponse('/production/materials/request?agid=' . $agId . '&error=' . rawurlencode((string)($lastRes['error'] ?? 'Error al cargar bobina.')));
            }
            return true;
        } elseif ($actionMode === 'solicitar_central') {
            $rollIds = (array)($_POST['roll_ids'] ?? []);
            $comments = trim((string)($_POST['comments'] ?? ''));
            $supUser = trim((string)($_POST['sup_user'] ?? ''));
            $supPass = trim((string)($_POST['sup_pass'] ?? ''));
            $reqSup = !empty($_POST['requires_supervisor']);
            $res = $prodService->requestRollsForWorkOrder($workerOtId, $rollIds, $comments, $currentOperatorName, $supUser, $supPass, $reqSup);
            if ($res['ok']) {
                redirectResponse('/production/materials/request?agid=' . $agId . '&saved_request=1&request_type=rolls');
            } else {
                redirectResponse('/production/materials/request?agid=' . $agId . '&error=' . rawurlencode((string)($res['error'] ?? 'Error al solicitar bobinas.')));
            }
            return true;
        } elseif ($actionMode === 'chemical_request') {
            $agenda = $agId > 0 ? $prodService->getWorkOrderAgendaDetails($agId) : null;
            $workOrderId = (int)($agenda['req_id'] ?? $agenda['ag_reqid'] ?? 0);
            $chemicalId = (int)($_POST['chemical_id'] ?? 0);
            $requestedItemText = trim((string)($_POST['requested_item_text'] ?? ''));
            $requestedQty = (float)str_replace(',', '.', (string)($_POST['material_qty'] ?? 0));
            $requestedUnit = trim((string)($_POST['material_unit'] ?? 'Kg'));
            $comments = trim((string)($_POST['comments'] ?? ''));
            $warehouseCode = (int)($_POST['warehouse_code'] ?? 0);
            $warehouseName = trim((string)($_POST['warehouse_name'] ?? ''));
            if ($warehouseCode > 0) {
                $warehouseMsg = 'Bodega sugerida: ' . $warehouseCode . ($warehouseName !== '' ? ' (' . $warehouseName . ')' : '');
                $comments = $comments !== '' ? ($warehouseMsg . ' · ' . $comments) : $warehouseMsg;
            }

            if ($chemicalId > 0) {
                $res = $receptionService->createMaterialRequest(
                    $workOrderId,
                    'CHEMICAL',
                    '',
                    $chemicalId,
                    '',
                    $requestedQty,
                    0.0,
                    $requestedUnit,
                    $comments,
                    $currentOperatorName
                );
            } else {
                $res = $receptionService->createMaterialRequest(
                    $workOrderId,
                    'OTHER',
                    '',
                    null,
                    $requestedItemText,
                    $requestedQty,
                    0.0,
                    $requestedUnit,
                    $comments,
                    $currentOperatorName
                );
            }

            if (($res['ok'] ?? false) === true) {
                redirectResponse('/production/materials/request?agid=' . $agId . '&saved_request=1&request_type=chemical&material_kind=inks');
            }

            $firstError = isset($res['errors']) && is_array($res['errors']) ? (string)reset($res['errors']) : '';
            redirectResponse('/production/materials/request?agid=' . $agId . '&error=' . rawurlencode($firstError !== '' ? $firstError : 'Error al solicitar tinta.') . '&request_type=chemical&material_kind=inks');
            return true;
        } elseif ($actionMode === 'insumos_directos') {
            $matType = trim((string)($_POST['material_type'] ?? 'Insumo Directo'));
            $matQty = (float)str_replace(',', '.', (string)($_POST['material_qty'] ?? 0));
            $matUnit = trim((string)($_POST['material_unit'] ?? 'unid'));
            $comments = trim((string)($_POST['comments'] ?? ''));
            $desc = "Solicitud Insumo: {$matType} - Cant: {$matQty} {$matUnit}";
            if ($comments !== '') $desc .= " ({$comments})";

            $now = time();
            Db::erpPdo()->prepare("
                INSERT INTO prod_worker_ot_events
                 (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                VALUES
                 (:ot_id, :amt, :crtdat, :enddat, 2, 'materiales', :comments)
            ")->execute([
                ':ot_id' => $workerOtId,
                ':amt' => $matQty,
                ':crtdat' => $now,
                ':enddat' => $now,
                ':comments' => $desc,
            ]);
            redirectResponse('/production/materials/request?agid=' . $agId . '&saved_material=1');
            return true;
        }

        redirectResponse('/production/materials/request?agid=' . $agId);
        return true;
    }

    if ($path === '/production/materials/bodega3000' && $method === 'GET') {
        $bodega3000Rolls = $prodService->listAvailableRollsInBodega3000(null, 300);
        renderProductionBodega3000Screen($bodega3000Rolls, $currentOperatorName, $plantaName);
        return true;
    }

    return false;
}

// =============================================================================
// SHELL / LAYOUT PRODWRK (Idéntico a prodwrk.php)
// =============================================================================

function renderProdwrkShell(
    string $title,
    string $content,
    int $mid,
    string $operatorName,
    string $plantaName
): void {
    ?>
<!DOCTYPE html>
<html lang="es" style="padding:0px;margin:0px;width:100%;height:100%">
<head>
    <meta charset="utf-8">
    <title><?=htmlspecialchars($title)?> - Producción Operador</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/assets/fontawesome/css/all.min.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            font-size: 13px;
            color: #333333;
            background-color: #EDF0F6;
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
        }
        * { box-sizing: border-box; }
        .tdheader {
            border-bottom: 1px solid #DDDDDD;
            color: #115452;
            font-weight: bold;
            font-size: 13px;
            padding: 7px 10px;
            background-color: #E8F0EF;
        }
        .tdleft {
            border-bottom: 1px solid #DDDDDD;
            font-weight: bold;
            background-color: #EEEEEE;
            padding: 6px 10px;
            font-size: 13px;
            color: #333333;
        }
        .tdnrm {
            font-size: 13px;
            border-bottom: 1px solid #DDDDDD;
            background-color: #FFFFFF;
            padding: 6px 10px;
            color: #333333;
        }
        .btnred {
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            padding: 8px 14px;
            background-color: #D64141;
            color: white !important;
            text-align: center;
            border-radius: 4px;
            display: inline-block;
            user-select: none;
            border: none;
            text-decoration: none;
        }
        .btnred:hover { background-color: #b82e2e; }
        .btngreen {
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            padding: 8px 14px;
            background-color: #00A85A;
            color: white !important;
            text-align: center;
            border-radius: 4px;
            display: inline-block;
            user-select: none;
            border: none;
            text-decoration: none;
        }
        .btngreen:hover { background-color: #008f4c; }
        .btnblue {
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            padding: 8px 14px;
            background-color: #3F4C9B;
            color: white !important;
            text-align: center;
            border-radius: 4px;
            display: inline-block;
            user-select: none;
            border: none;
            text-decoration: none;
        }
        .btnblue:hover { background-color: #2f3a7a; }
        .btnorange {
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            padding: 8px 14px;
            background-color: #E1A500;
            color: white !important;
            text-align: center;
            border-radius: 4px;
            display: inline-block;
            user-select: none;
            border: none;
            text-decoration: none;
        }
        .btnorange:hover { background-color: #c48f00; }
        .btngrey {
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            padding: 8px 14px;
            background-color: #888888;
            color: white !important;
            text-align: center;
            border-radius: 4px;
            display: inline-block;
            user-select: none;
            border: none;
            text-decoration: none;
        }
        .btngrey:hover { background-color: #6e6e6e; }
        .inptxt {
            padding: 6px 8px;
            font-size: 13px;
            font-family: Arial, sans-serif;
            border: 1px solid #CCCCCC;
            border-radius: 4px;
            color: #333333;
            background-color: #FFFFFF;
        }
        .msg_save_ok { color: #00A85A; font-weight: bold; }
        .msg_save_err { color: #D64141; font-weight: bold; }
        .tdmenu a { color: inherit; text-decoration: none; display: block; }
    </style>
</head>
<body style="background-color:#EDF0F6;padding:0px;margin:0px;width:100%;height:100%">

<!-- Topbar #008A87 / #00A9A4 -->
<table border="0" width="100%" cellpadding="0" cellspacing="0" height="60" style="table-layout:fixed">
<tr>
    <td width="280" style="background-color:#008A87" valign="middle" align="left" class="tdmenu">
        <div style="padding-left:20px;display:flex;align-items:center;gap:10px">
            <span style="font-weight:900;color:#FFFFFF;font-size:22px;letter-spacing:1px">UNIBAG</span>
            <span style="background:#0E7572;color:#FFF;font-size:11px;padding:2px 8px;border-radius:3px;font-weight:bold">PROD</span>
        </div>
    </td>
    <td style="background-color:#00A9A4" valign="middle" align="left">
        <table border="0" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="60">
                <i class="fa fa-fw fa-bars" style="font-size:24px;color:#FFFFFF;margin-left:15px;cursor:pointer"
                   onclick="var m=document.querySelectorAll('.tdmenu'); m.forEach(function(el){el.style.display=(el.style.display==='none'?'':'none');});"></i>
            </td>
            <td style="font-size:12px;color:white;line-height:1.5">
                <i class="fa fa-fw fa-user" style="font-size:12px;color:#FFFFFF;"></i> USUARIO: <b><?=htmlspecialchars($operatorName !== '' ? $operatorName : 'OPERARIO')?></b><br>
                <i class="fa fa-fw fa-home" style="font-size:12px;color:#FFFFFF;"></i> PLANTA: <?=htmlspecialchars($plantaName !== '' ? $plantaName : 'PLANTA 1')?>
            </td>
            <td style="font-size:12px;color:white" align="right" width="140">
                <i class="fa fa-fw fa-bell" style="font-size:22px;color:#FFFFFF;cursor:pointer" title="Notificaciones"></i>
                <i class="fa fa-fw fa-times-circle" style="font-size:24px;color:#FFFFFF;margin-right:20px;margin-left:20px;cursor:pointer"
                   title="Cerrar Sesión / Salir" onclick="if(confirm('¿Desea cerrar sesión?')) location.href='/logout';"></i>
            </td>
        </tr>
        </table>
    </td>
</tr>
</table>

<!-- Body Table with Sidebar #0E7572 -->
<table border="0" width="100%" cellpadding="0" cellspacing="0" style="min-height: calc(100vh - 60px)">
<tr>
    <td width="280" style="background-color:#0E7572;vertical-align:top" class="tdmenu">
        <div style="width:100%;background-color:#116663;">
            <div style="padding:10px 15px;font-size:12px;color:#CCCCCC;font-weight:bold;text-transform:uppercase;letter-spacing:0.5px">Navegación</div>
        </div>
        <div style="clear:both;height:10px"></div>
        <div style="font-size:14px;padding-left:15px;color:white;font-weight:bold">
            <i class="fa fa-fw fa-laptop-code" style="font-size:16px;"></i> Asignación máquina
            <span style="float:right;padding-right:15px"><i class="fa fa-fw fa-chevron-down" style="font-size:12px"></i></span>
        </div>
        <div style="clear:both;height:8px"></div>
        <div style="cursor:pointer;font-size:14px;padding-left:25px;color:<?=$mid === 0 ? '#FFFFFF' : '#B8D5D3'?>;background-color:<?=$mid === 0 ? '#115452' : 'transparent'?>;padding-top:10px;padding-bottom:10px"
             onclick="location.href='/production/machines';">
            <i class="fa fa-fw fa-circle" style="font-size:12px;color:<?=$mid === 0 ? '#00A85A' : '#7FA8A5'?>"></i> Iniciar / Terminar turno
        </div>
        
        <div style="clear:both;height:12px"></div>
        <div style="font-size:14px;padding-left:15px;color:white;font-weight:bold">
            <i class="fa fa-fw fa-calendar-alt" style="font-size:16px;"></i> Ordenes de trabajo
            <span style="float:right;padding-right:15px"><i class="fa fa-fw fa-chevron-down" style="font-size:12px"></i></span>
        </div>
        <div style="clear:both;height:8px"></div>
        <div style="cursor:pointer;font-size:14px;padding-left:25px;color:<?=$mid === 1 ? '#FFFFFF' : '#B8D5D3'?>;background-color:<?=$mid === 1 ? '#115452' : 'transparent'?>;padding-top:10px;padding-bottom:10px"
             onclick="location.href='/production/work-orders/new';">
            <i class="fa fa-fw fa-circle" style="font-size:12px;color:<?=$mid === 1 ? '#00A85A' : '#7FA8A5'?>"></i> Iniciar nueva OT
        </div>
        <div style="cursor:pointer;font-size:14px;padding-left:25px;color:<?=$mid === 5 ? '#FFFFFF' : '#B8D5D3'?>;background-color:<?=$mid === 5 ? '#115452' : 'transparent'?>;padding-top:10px;padding-bottom:10px"
             onclick="location.href='/production/work-orders/active';">
            <i class="fa fa-fw fa-circle" style="font-size:12px;color:<?=$mid === 5 ? '#00A85A' : '#7FA8A5'?>"></i> En cursos
        </div>
        <div style="cursor:pointer;font-size:14px;padding-left:25px;color:<?=$mid === 7 ? '#FFFFFF' : '#B8D5D3'?>;background-color:<?=$mid === 7 ? '#115452' : 'transparent'?>;padding-top:10px;padding-bottom:10px"
             onclick="location.href='/production/materials/request';">
            <i class="fa fa-fw fa-dolly" style="font-size:12px;color:<?=$mid === 7 ? '#00A85A' : '#7FA8A5'?>"></i> Solicitar materiales
        </div>
        <div style="cursor:pointer;font-size:14px;padding-left:25px;color:<?=$mid === 8 ? '#FFFFFF' : '#B8D5D3'?>;background-color:<?=$mid === 8 ? '#115452' : 'transparent'?>;padding-top:10px;padding-bottom:10px"
             onclick="location.href='/production/materials/bodega3000';">
            <i class="fa fa-fw fa-warehouse" style="font-size:12px;color:<?=$mid === 8 ? '#00A85A' : '#7FA8A5'?>"></i> Bobinas Bodega 3000
        </div>
        
        <div style="clear:both;height:12px"></div>
        <div style="font-size:14px;padding-left:15px;color:white;font-weight:bold">
            <i class="fa fa-fw fa-envelope" style="font-size:16px;"></i> Mensajes
            <span style="float:right;padding-right:15px"><i class="fa fa-fw fa-chevron-down" style="font-size:12px"></i></span>
        </div>
        <div style="clear:both;height:8px"></div>
        <div style="cursor:pointer;font-size:14px;padding-left:25px;color:#B8D5D3;padding-top:10px;padding-bottom:10px"
             onclick="alert('Sin mensajes pendientes.');">
            <i class="fa fa-fw fa-circle" style="font-size:12px;color:#7FA8A5"></i> Buzon
        </div>
    </td>
    <td valign="top" style="padding:20px;background-color:#EDF0F6;vertical-align:top">
        <?php if (isset($_GET['lunch_saved'])): ?>
            <div style="background:#E0F2FE;border:1px solid #7DD3FC;border-radius:4px;padding:12px 15px;margin-bottom:15px;color:#0369A1">
                <i class="fa fa-coffee" style="font-size:16px"></i> <b>Colación registrada exitosamente.</b>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['standby_started'])): ?>
            <div style="background:#FEF3C7;border:1px solid #FCD34D;border-radius:4px;padding:12px 15px;margin-bottom:15px;color:#92400E">
                <i class="fa fa-pause-circle" style="color:#D97706;font-size:16px"></i> <b>Evento Sin Producción iniciado.</b> La máquina figura en estado de espera informada.
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['standby_ended'])): ?>
            <div style="background:#D1FAE5;border:1px solid #6EE7B7;border-radius:4px;padding:12px 15px;margin-bottom:15px;color:#065F46">
                <i class="fa fa-play-circle" style="color:#059669;font-size:16px"></i> <b>Evento Sin Producción finalizado.</b> La máquina vuelve a estar disponible para operar.
            </div>
        <?php endif; ?>
        <?=$content?>
    </td>
</tr>
</table>

</body>
</html>
<?php
}

// =============================================================================
// CONSOLA DEL OPERARIO (Recreación exacta de editxid.php)
// =============================================================================

function renderProductionOperatorConsole(
    array $ctx,
    array $events,
    array $pauses,
    array $repairs,
    array $medidas,
    array $autocontrolPoints = [],
    array $autocontrolResponses = [],
    array $availableRolls = [],
    string $currentOperatorName = '',
    string $plantaName = '',
    array $alisStatus = [],
    array $autoctrlStatus = [],
    array $helpers = []
): void {
    $agenda = $ctx['agenda'];
    $machineInit = $ctx['machine_init'] ?? [];
    $workerOt = $ctx['worker_ot'];
    $stats = $ctx['stats'];

    $itemAnilox = $ctx['item_anilox'] ?? [];
    $detalleAnilox = $ctx['detalle_anilox'] ?? [];
    $aniloxDescMap = $ctx['anilox_desc_map'] ?? [];
    $aniloxInkConfigByUnit = $ctx['anilox_ink_config_by_unit'] ?? [];
    $inkWarehouse = $ctx['ink_warehouse'] ?? [];
    $inkItems = $ctx['ink_items'] ?? [];
    $machineInkLoads = $ctx['machine_ink_loads'] ?? [];
    $bodega3000InkItems = $ctx['bodega3000_ink_items'] ?? [];
    $mermatypes = $ctx['mermatypes'] ?? [];
    $repairtypes = $ctx['repairtypes'] ?? [];

    $reboValues = $ctx['rebo_values'] ?? [];
    $subproducts = $ctx['subproducts'] ?? [];
    $inputWeights = $ctx['input_weights'] ?? [];
    $reboOrder = $ctx['rebo_order'] ?? [];
    $reqGenRefId = (int)($agenda['req_rebo_cc_genrefid'] ?? 0);
    $transcomInfos = $ctx['transcom_infos'] ?? [];
    $entregas = $ctx['entregas'] ?? [];

    $pulpoV2 = $ctx['pulpo_v2'] ?? [];
    $pulpoAgendaData = $pulpoV2['agenda'] ?? $agenda;
    $pulpoUnits = $pulpoV2['units'] ?? [];
    $bastidoresList = $ctx['bastidores'] ?? [];
    $colorMode = $ctx['colormode'] ?? 'Frente';

    $workerOtId = $workerOt !== null ? (int)$workerOt['id'] : 0;
    $agId = (int)$agenda['id'];
    $mode = trim((string)($_GET['mode'] ?? ''));
    $refId = (int)($_GET['refid'] ?? 0);
    $overrideEmbalaje = (int)($_GET['overrideembalaje'] ?? 0);

    // Tipos de máquina
    $equipoTypeId = (int)($machineInit['equipo_type_id'] ?? 0);
    $isFlexo = (int)($machineInit['equipo_prod_isprinter_flexo'] ?? 0) === 1;
    $isSeri = (int)($machineInit['equipo_prod_isprinter_seri'] ?? 0) === 1
        || ($equipoTypeId === 11)
        || ($equipoTypeId === 22)
        || stripos((string)($machineInit['type_ant_title'] ?? ''), 'seri') !== false
        || stripos((string)($machineInit['equipo_name'] ?? ''), 'seri') !== false;
    $isSeriPulpo = ($isSeri || $equipoTypeId === 22 || (int)($machineInit['win_equipoid'] ?? 0) === 36)
        && (stripos((string)($machineInit['equipo_name'] ?? ''), 'pulpo') !== false || (int)($machineInit['win_equipoid'] ?? 0) === 36);
    $isSeriPlana = $isSeri && !$isSeriPulpo;
    $isPrinter = $isFlexo || $isSeri;
    $isSelladora = stripos((string)($machineInit['type_ant_title'] ?? ''), 'selladora') !== false;
    $isRebobinadora = ($equipoTypeId === 12)
        || stripos((string)($machineInit['type_ant_title'] ?? ''), 'rebobin') !== false
        || stripos((string)($machineInit['equipo_name'] ?? ''), 'rebob') !== false;
    $isEmbalaje = (int)($machineInit['type_createstock_act'] ?? 0) === 1;
    $activeStandby = $ctx['active_standby'] ?? null;

    $prodService = $ctx['prod_service'] ?? new ProductionService(Db::erpPdo(), Db::trzPdo());

    // Bobinas asignadas a la orden de trabajo (para selección en Embalaje y Semielaborado)
    $assignedRolls = [];
    if ($workerOtId > 0) {
        try {
            $quota = $prodService->getWorkOrderMaterialQuota($agId, $workerOtId);
            $assignedRolls = $quota['assigned_rolls'] ?? [];
        } catch (\Throwable) {}
    }

    // Carga de datos y acciones para Rebobinado (form.reboprod.php)
    $reboAllItems = [];
    $reboMoveOuts = [];
    $reboItemSel1 = null;
    $reboItemSel2 = null;
    if ($isRebobinadora || $mode === 'reboprod') {
        if (!empty($_REQUEST['delsthids'])) {
            $prodService->deleteReboStockChanges((array)$_REQUEST['delsthids'], (int)($_SESSION['user_id'] ?? 4075));
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=reboprod');
            return;
        }
        if (!empty($_POST['rebofinalize'])) {
            $resRebo = $prodService->executeReboFinalize($agId, $_POST, (int)($_SESSION['user_id'] ?? 4075), (int)($_SESSION['company_id'] ?? 20010), $workerOtId, $currentOperatorName);
            $cutCount = !empty($resRebo['created_rolls']) ? count($resRebo['created_rolls']) : 0;
            $firstCutId = !empty($resRebo['created_bag_rolls'][0]['id'])
                ? (int)$resRebo['created_bag_rolls'][0]['id']
                : (!empty($resRebo['created_rolls'][0]['id']) ? (int)$resRebo['created_rolls'][0]['id'] : 0);
            $palletId = !empty($resRebo['handle_pallet']['id']) ? (int)$resRebo['handle_pallet']['id'] : 0;
            $handleCount = !empty($resRebo['created_handle_rolls']) ? count($resRebo['created_handle_rolls']) : 0;
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=reboprod&rebo_done=1&cuts=' . $cutCount . '&first_cut=' . $firstCutId . '&pallet_id=' . $palletId . '&handle_rolls=' . $handleCount);
            return;
        }

        $reboRequestedRolls = $prodService->getRequestedRollsForRebo($workerOtId, $agId);
        $reboMoveOuts = $prodService->getReboFinishedMoves($agId);

        $selItemId1 = (int)($_REQUEST['selitemid1'] ?? 0);
        $selItemId2 = (int)($_REQUEST['selitemid2'] ?? 0);

        if ($selItemId1 > 0) {
            $stmtSingle = Db::erpPdo()->prepare("
                SELECT i.*, 
                       COALESCE((SELECT t4b.add_name FROM tran_comments_item_vals t4 INNER JOIN tran_comments_vals t4b ON t4.val_id = t4b.id WHERE t4.item_id = i.id AND t4.com_id = 17 LIMIT 1), i.item_title) as colordesc,
                       COALESCE((SELECT t6.add_name FROM tran_comments_item_vals t5 INNER JOIN tran_comments_vals t6 ON t5.val_id = t6.id WHERE t5.item_id = i.id AND t5.com_id = 18 LIMIT 1), 'TNT') as material,
                       COALESCE((SELECT SUM(iss_inventory) FROM item_shops_storehouses WHERE item_id = i.id AND st_id = 16), 1) as iss_inventory,
                       16 as st_id
                FROM item i WHERE i.id = :id LIMIT 1
            ");
            $stmtSingle->execute([':id' => $selItemId1]);
            $reboItemSel1 = $stmtSingle->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    if (isset($_GET['end_alis']) || $mode === 'autocontrol') {
        if ($workerOtId > 0) {
            $prodServiceInstance = new ProductionService(Db::erpPdo(), Db::trzPdo());
            $prodServiceInstance->closeOpenAperturaEvent($workerOtId);
            $hasAlis = true;
            $alisStatus['has_alis'] = true;
        }
    }

    $hasApertura = !empty($alisStatus['has_apertura']);
    $hasAlis = !empty($alisStatus['has_alis']) || isset($_GET['end_alis']) || ($mode === 'autocontrol');
    $superInputs = (int)($autoctrlStatus['super_inputs'] ?? 0);
    $superPending = !empty($autoctrlStatus['super_pending']);
    // Rebobinadora no utiliza autocontrol
    $gotoAutocontrol = (!$isRebobinadora && ($hasAlis || $mode === 'autocontrol') && (!empty($autoctrlStatus['goto_autocontrol']) || $mode === 'autocontrol'));

    // Formatear colores exactamente como en autocontrol.form.php (Foto 3)
    $printcolors = '';
    for ($xx = 1; $xx <= 10; $xx++) {
        $f = (int)($agenda["fab_print_colors_front_{$xx}"] ?? 0);
        $b = (int)($agenda["fab_print_colors_back_{$xx}"] ?? 0);
        $desc = trim((string)($agenda["fab_print_colordesc_{$xx}"] ?? ''));
        if ($f > 0 || $b > 0) {
            if ($f > 0 && $b === 0) {
                $printcolors .= "Frente: {$desc}, ";
            } elseif ($f === 0 && $b > 0) {
                $printcolors .= "Dorso: {$desc}, ";
            } elseif ($f > 0 && $b > 0) {
                $printcolors .= "Frente/Dorso: {$desc}, ";
            }
        }
    }
    $printcolors = rtrim($printcolors, ', ');

    // Pantalla de Supervisor (Foto 3): Si se informó al supervisor o líder y está pendiente de aprobación (Rebobinadora no utiliza autocontrol)
    $showSupervisorApproval = (!$isRebobinadora && ($superPending || ($superInputs > 0 && $gotoAutocontrol)));

    // Pantalla de Autocontrol Operador (Foto 2): Cuando el alistamiento terminó y aún no se aprueba la partida
    $showOperatorAutocontrol = (!$isRebobinadora && ($gotoAutocontrol || $mode === 'autocontrol') && !$showSupervisorApproval);

    // Si la OT requiere autocontrol y no está en un submodo auxiliar (materiales, cargar, mantencion, pause, terminarot),
    // se renderiza EXCLUSIVAMENTE la pantalla de Autocontrol (autocontrol.form.php) tal como en el sistema original de Unibag.
    $isExclusiveAutocontrol = (!$isRebobinadora && ($gotoAutocontrol || $mode === 'autocontrol') && !in_array($mode, ['materiales', 'cargar', 'tintas', 'mantencion', 'pause', 'terminarot'], true));

    // Detectar evento de producción abierto
    $openProdEvent = null;
    foreach ($events as $ev) {
        if ($ev['evt_type'] === 'prod' && (int)($ev['evt_enddat'] ?? 0) === 0) {
            $openProdEvent = $ev;
            break;
        }
    }
    $openProdId = $openProdEvent !== null ? (int)$openProdEvent['id'] : 0;
    if ($mode === 'production' && $refId <= 0 && $openProdId > 0) {
        $refId = $openProdId;
    }
    $printedRollsSummary = ($workerOtId > 0 && $isFlexo) ? $prodService->getPrintedRollsSummary($workerOtId) : [];

    // Helper format (reproduce printPrice de Unibag eliminando ceros innecesarios a la derecha)
    $fmt = function($val, $dec = 0, $blankIfNull = false) {
        if ($val === null || $val === '') return $blankIfNull ? '&nbsp;' : '-';
        $formatted = number_format((float)$val, $dec, ',', '.');
        if ($dec > 0 && strpos($formatted, ',') !== false) {
            $parts = explode(',', $formatted);
            $decPart = rtrim($parts[1], '0');
            $formatted = $decPart !== '' ? ($parts[0] . ',' . $decPart) : $parts[0];
        }
        if ($blankIfNull && ($formatted === '0' || $formatted === '')) return '&nbsp;';
        return $formatted;
    };
    $internalFabricationNote = trim((string)($agenda['req_desc_intern'] ?? ''));
    $internalFabricationNoteHtml = $internalFabricationNote !== ''
        ? nl2br(htmlspecialchars($internalFabricationNote))
        : '<span style="color:#999">-</span>';
    $internalFabricationNoteLabelStyle = 'font-size:15px;font-weight:800;color:#0f172a';
    $internalFabricationNoteValueStyle = 'white-space:normal;line-height:1.6;font-size:15px;font-weight:800;color:#111827';

    // Cálculos de desarrollo, corte, metros y kg
    $corte_m2 = 0.0;
    $corte_z = 0;
    if ($isFlexo || $isSeriPlana || $isSelladora) {
        $param_medida = (int)$agenda['fab_med_width'];
        if ((int)($agenda['item_prodcalc_fuelle_act'] ?? 0) === 1) {
            $param_medida += (int)$agenda['fab_med_fuelle'];
        }
        $erpPdo = Db::erpPdo();
        $winEquipoid = (int)($machineInit['win_equipoid'] ?? 0);
        $stmtEq = $erpPdo->prepare("SELECT * FROM equipo_params WHERE param_equipo_id = :eq AND param_medida >= :med ORDER BY param_medida ASC LIMIT 1");
        $stmtEq->execute([':eq' => $winEquipoid, ':med' => $param_medida]);
        $eqParams = $stmtEq->fetch();
        if ($eqParams) {
            $corte_m2 = (float)($eqParams['param_corte'] ?? 0);
            $corte_z = (int)($eqParams['param_z'] ?? 0);
        } else {
            $corte_m2 = round(((float)$agenda['fab_med_height'] + (float)$agenda['fab_med_fuelle']) / 100, 4);
        }
    }

    $mlin_prog = round((float)$agenda['ag_amount'] * $corte_m2);
    $mlin_addi = round($mlin_prog / 100 * (float)($agenda['req_operador_mermaperc'] ?? 0));
    $metrosAImprimir = round($mlin_prog + $mlin_addi);
    $kgAImprimir = round($metrosAImprimir * (float)($agenda['req_operador_tela_width'] ?? 0) / 100 * (float)($agenda['fab_mat_gramms'] ?? 70) / 1000, 2);

    // Cálculos de selladora / bobinas / manillas
    $medidasStr = sprintf("%02d", (int)$agenda["fab_med_width"]) . "X" . sprintf("%02d", (int)$agenda["fab_med_height"]) . "X" . sprintf("%02d", (int)$agenda["fab_med_fuelle"]);
    $part1 = (float)substr($medidasStr, 0, 2);
    $itemTypeStr = strtoupper((string)($transcomInfos['itemtype'] ?? $agenda['item_title'] ?? ''));
    if (strpos($itemTypeStr, 'BOUTIQUE') !== false) {
        $part2 = (float)substr($medidasStr, 6, 2);
        $corteSelladora = $part1 + $part2 + 1.5;
    } else {
        $corteSelladora = $part1;
    }

    $cantProg = (float)($agenda['ag_amount'] ?? 0);
    $gramaje = (int)($agenda['fab_mat_gramms'] ?? 70);
    $mat = (string)($agenda['fab_type'] ?? '');
    if ($mat === 'PP') {
        $cantBobinas = ($corteSelladora / 100) * $cantProg / 1100;
    } elseif ($gramaje === 70) {
        $cantBobinas = ($corteSelladora / 100) * $cantProg / 1950;
    } elseif ($gramaje === 55) {
        $cantBobinas = ($corteSelladora / 100) * $cantProg / 2100;
    } elseif ($gramaje === 80) {
        $cantBobinas = ($corteSelladora / 100) * $cantProg / 1700;
    } else {
        $cantBobinas = ($corteSelladora / 100) * $cantProg / 1400;
    }
    $cantManillas = ((float)($agenda['fab_manilla_length'] ?? 40) / 100) * $cantProg * 2 / 1200;

    // Colores pulpo y serigrafía
    $amtColorsFrente = 0;
    $amtColorsDorso = 0;
    for ($xx = 1; $xx <= 10; $xx++) {
        $hasFront = !empty($agenda["fab_print_colors_front_{$xx}"]);
        $hasBack = !empty($agenda["fab_print_colors_back_{$xx}"]);
        if ($hasFront) {
            $amtColorsFrente++;
        }
        if ($hasBack) {
            $amtColorsDorso++;
        }
        if (!$hasFront && !$hasBack && !empty($agenda["fab_print_colordesc_{$xx}"])) {
            $amtColorsFrente++;
        }
    }
    $amtColorsSeri = max($amtColorsFrente, 1);

    // Totales y saldo de frentes y dorsos acumulados en la OT (pulpo)
    $prevFrente = 0.0;
    $prevDorso = 0.0;
    $prevFinished = 0.0;
    try {
        $stmtPrev = Db::erpPdo()->prepare(
            "SELECT SUM(t3.evt_amount) AS prev_amt,
                    SUM(t3.evt_amount_pulpo_frente) AS prev_frente,
                    SUM(t3.evt_amount_pulpo_dorso) AS prev_dorso
             FROM prod_worker_ot t2
             INNER JOIN prod_worker_ot_events t3 ON t3.evt_prod_worker_otid = t2.id
             WHERE t2.wok_ag_id = :ag_id
               AND t3.evt_type = 'prod'
               AND t3.evt_status > 0"
            . ($refId > 0 ? " AND t3.id != " . (int)$refId : "")
        );
        $stmtPrev->execute([':ag_id' => $agId]);
        $pRow = $stmtPrev->fetch();
        if (is_array($pRow)) {
            $prevFrente = (float)($pRow['prev_frente'] ?? 0);
            $prevDorso = (float)($pRow['prev_dorso'] ?? 0);
            $prevFinished = (float)($pRow['prev_amt'] ?? 0);
        }
    } catch (Throwable) {
        $prevFrente = (float)($stats['total_pulpo_frente_agenda'] ?? 0);
        $prevDorso = (float)($stats['total_pulpo_dorso_agenda'] ?? 0);
        $prevFinished = (float)($stats['total_produced_agenda'] ?? 0);
    }
    $prevSurplusFrente = max(0.0, $prevFrente - $prevDorso);
    $prevSurplusDorso = max(0.0, $prevDorso - $prevFrente);

    $medidasMap = [];
    foreach ($medidas as $m) {
        $medidasMap[(int)$m['id']] = (string)$m['med_name'];
    }
    $helpersMap = [];
    foreach ($helpers as $h) {
        $helpersMap[(int)$h['id']] = (string)$h['ayudantes'];
    }

    $openAlisEvent = $alisStatus['open_event'] ?? ($hasApertura && !$hasAlis ? ($alisStatus['event'] ?? null) : null);
    $openAlisId = $openAlisEvent !== null ? (int)$openAlisEvent['id'] : 0;

    $aperturaAyudanteName = '-';
    $aperturaComments = '';
    foreach ($events as $ev) {
        if (($ev['evt_type'] ?? '') === 'apertura') {
            $aperturaComments = (string)($ev['evt_comments'] ?? '');
            $aid = (int)($ev['evt_idayudante'] ?? 0);
            if ($aid > 0 && isset($helpersMap[$aid])) {
                $aperturaAyudanteName = $helpersMap[$aid];
            }
            break;
        }
    }

    ob_start();
    ?>

    <?php if ($activeStandby !== null): ?>
        <div style="background:#FFF7ED;border:1px solid #FED7AA;border-radius:6px;padding:12px 18px;margin-bottom:15px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
            <div style="display:flex;align-items:center;gap:10px">
                <i class="fa fa-pause-circle" style="font-size:24px;color:#EA580C"></i>
                <div>
                    <div style="font-weight:bold;color:#9A3412;font-size:14px">MÁQUINA EN ESPERA / SIN PRODUCCIÓN: <?=htmlspecialchars((string)$activeStandby['reason'])?></div>
                    <div style="font-size:12px;color:#C2410C">Iniciado a las <b><?=htmlspecialchars((string)$activeStandby['start_time'])?></b> por <b><?=htmlspecialchars((string)$activeStandby['operator_name'])?></b><?php if (!empty($activeStandby['comments'])): ?> &bull; <i><?=htmlspecialchars((string)$activeStandby['comments'])?></i><?php endif; ?></div>
                </div>
            </div>
            <button type="button" class="btnorange" style="background:#EA580C;padding:7px 16px;border:none;cursor:pointer;font-size:13px" onclick="openStandbyEndModal()">
                <i class="fa fa-play"></i> Finalizar Sin Producción
            </button>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['roll_created'])): ?>
        <div style="background:#E8F8F5;border:1.5px solid #2ECC71;color:#117A65;padding:16px 20px;border-radius:6px;margin-bottom:18px;font-size:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <i class="fa fa-fw fa-check-circle" style="font-size:20px;color:#27AE60"></i>
                <b>¡Bobina semielaborada generada exitosamente!</b> Se registró <b><?=htmlspecialchars($_GET['roll_code'] ?? ('ID #' . $_GET['roll_created']))?></b> en la OT #<b><?=htmlspecialchars((string)$agenda['prd_number'])?></b>.
            </div>
            <div style="display:flex;gap:8px">
                <a href="/rolls/<?=(int)$_GET['roll_created']?>/label?server_print=1" target="_blank" class="btnblue" style="text-decoration:none;font-weight:bold">
                    <i class="fa fa-print"></i> 🖨️ Mandar a Imprimir en Zebra
                </a>
                <a href="/rolls/<?=(int)$_GET['roll_created']?>/label" target="_blank" class="btngrey" style="text-decoration:none;font-weight:bold">
                    <i class="fa fa-eye"></i> Vista Previa (100×70)
                </a>
                <a href="/production/traceability?q=<?=urlencode((string)($_GET['roll_code'] ?? ''))?>" target="_blank" class="btngreen" style="text-decoration:none;font-weight:bold">
                    <i class="fa fa-sitemap"></i> 🌳 Trazabilidad
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])): ?>
        <div style="background:#FDECEC;border:1.5px solid #EF4444;color:#991B1B;padding:14px 18px;border-radius:6px;margin-bottom:18px;font-size:14px">
            <i class="fa fa-fw fa-exclamation-triangle" style="font-size:18px;color:#DC2626"></i>
            <b>Error:</b> <?=htmlspecialchars((string)$_GET['error'])?>
        </div>
    <?php endif; ?>

    <?php if ($isExclusiveAutocontrol): ?>
        <!-- ============================================================== -->
        <!-- PANTALLA EXCLUSIVA DE AUTOCONTROL (autocontrol.form.php)       -->
        <!-- Se muestra únicamente cuando el alistamiento ha terminado y    -->
        <!-- la partida aún no ha sido aprobada por el supervisor.         -->
        <!-- ============================================================== -->
        <div style="font-size:20px;margin-bottom:10px"><b>En curso</b></div>

        <?php if (isset($_GET['alis_finished'])): ?>
            <div style="background:#E8F5E9;color:#2E7D32;padding:12px 18px;border-radius:4px;border:1px solid #A5D6A7;margin-bottom:12px;font-size:13px">
                <i class="fa fa-check-circle"></i> <b>Alistamiento y confirmaciones de máquina completados.</b> Complete el autocontrol de calidad a continuación para solicitar la aprobación de partida del supervisor.
            </div>
        <?php elseif (isset($_GET['supervisor_notified'])): ?>
            <div style="background:#E8F5E9;color:#2E7D32;padding:12px 18px;border-radius:4px;border:1px solid #A5D6A7;margin-bottom:12px;font-size:13px">
                <i class="fa fa-check-circle"></i> <b>Se ha notificado al Supervisor de Planta.</b> El supervisor debe ingresar sus credenciales para aprobar la partida y habilitar la producción.
            </div>
        <?php elseif (isset($_GET['lider_notified'])): ?>
            <div style="background:#E8F5E9;color:#2E7D32;padding:12px 18px;border-radius:4px;border:1px solid #A5D6A7;margin-bottom:12px;font-size:13px">
                <i class="fa fa-check-circle"></i> <b>Se ha informado al Líder de Área.</b>
            </div>
        <?php elseif (isset($_GET['error'])): ?>
            <div style="background:#FFEBEE;color:#C62828;padding:12px 18px;border-radius:4px;border:1px solid #EF9A9A;margin-bottom:12px;font-size:13px">
                <i class="fa fa-exclamation-circle"></i> <?=htmlspecialchars((string)$_GET['error'])?>
            </div>
        <?php endif; ?>

        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px">
        <tr>
            <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td align="left">
                        <i class="fa fa-fw fa-laptop-code" style="font-size:14px;"></i> Ordenes de trabajo
                        <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                        <b>En curso</b>: OT #<?=htmlspecialchars((string)$agenda['prd_number'])?> | Máquina: <b><?=htmlspecialchars((string)($machineInit['equipo_name'] ?? '-'))?></b>
                    </td>
                </tr>
                </table>
            </td>
        </tr>
        </table>

        <style>
        .unibag-collapse-header {
            background-color: #165552;
            color: #FFFFFF;
            padding: 8px 14px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
            font-weight: bold;
            font-size: 13px;
            transition: background-color 0.15s ease;
        }
        .unibag-collapse-header:hover {
            background-color: #124341;
        }
        .unibag-collapse-btn {
            font-size: 11px;
            font-weight: normal;
            opacity: 0.9;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.15);
            padding: 3px 8px;
            border-radius: 3px;
        }
        .unibag-collapse-header:hover .unibag-collapse-btn {
            background: rgba(255,255,255,0.25);
        }
        </style>
        <script>
        function toggleCollapseSection(secId, iconId) {
            var sec = document.getElementById(secId);
            var icon = document.getElementById(iconId);
            var lblId = iconId.replace('icon_', 'lbl_');
            var lbl = document.getElementById(lblId);
            if (!sec) return;

            var isHidden = (sec.style.display === 'none');
            if (isHidden) {
                sec.style.display = '';
                if (icon) icon.className = 'fa fa-chevron-up';
                if (lbl) lbl.textContent = 'Minimizar';
                try { localStorage.setItem('unibag_collapse_' + secId, '0'); } catch(e){}
            } else {
                sec.style.display = 'none';
                if (icon) icon.className = 'fa fa-chevron-down';
                if (lbl) lbl.textContent = 'Expandir';
                try { localStorage.setItem('unibag_collapse_' + secId, '1'); } catch(e){}
            }
        }
        </script>

        <?php
        $acImgHash = trim((string)($agenda['fab_design_imagehash'] ?? $agenda['item_img_hash'] ?? ''));
        $acHasImg = ($acImgHash !== '' && $acImgHash !== 'dummy');
        $acImgUrl = $acHasImg ? ('/production/design-image?folder=order&hash=' . rawurlencode($acImgHash)) : '';
        $acProdSku = trim((string)($agenda['item_number_prod'] ?? ''));
        $acProdTitle = trim((string)($agenda['item_title'] ?? ''));
        $acDesignName = trim((string)($agenda['fab_design_name'] ?? ''));
        $acFullDesign = ($acProdSku !== '' && $acProdTitle !== '') ? "{$acProdSku} | {$acProdTitle}" : ($acProdTitle ?: ($acProdSku ?: ($acDesignName ?: '-')));
        $acW = (int)($agenda['fab_med_width'] ?? 0);
        $acH = (int)($agenda['fab_med_height'] ?? 0);
        $acFuelle = (int)($agenda['fab_med_fuelle'] ?? 0);
        $acMedidasStr = $acW > 0 ? ("{$acW} x {$acH}" . ($acFuelle > 0 ? " + {$acFuelle}" : "") . " cm") : '-';
        $acTelaWidth = (float)($agenda['req_operador_tela_width'] ?? 0);
        $acSeriMermaPerc = (float)($agenda['req_operador_mermaperc'] ?? 0);
        $acSeriDevPrints = max(1, (int)($agenda['req_solic_devprints_cc'] ?? 1));
        $acSeriMlinProg = round((float)$agenda['ag_amount'] * (float)$corte_m2);
        $acSeriMlinAddi = round($acSeriMlinProg / 100 * $acSeriMermaPerc);
        $acSeriMlinPrint = round($acSeriMlinProg + $acSeriMlinAddi);
        $acSeriMermaUnits = round((float)$agenda['ag_amount'] / 100 * $acSeriMermaPerc);
        $acSeriRealUnits = (float)$agenda['ag_amount'] + $acSeriMermaUnits;
        $acSeriPasadasPrint = (int)round($acSeriRealUnits / $acSeriDevPrints);
        $acSeriKgsProcess = round(
            $acSeriMlinPrint
            * (float)($agenda['req_operador_tela_width'] ?? 0)
            / 100
            * (float)($agenda['fab_mat_gramms'] ?? 0)
            / 1000
        );
        ?>

        <?php if ($isSeriPlana): ?>
            <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
                <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_autoctrl_info_cliente', 'icon_autoctrl_info_cliente')">
                    <span><i class="fa fa-fw fa-user"></i> Información Cliente</span>
                    <span class="unibag-collapse-btn">
                        <span id="lbl_autoctrl_info_cliente">Minimizar</span>
                        <i id="icon_autoctrl_info_cliente" class="fa fa-chevron-up"></i>
                    </span>
                </div>
                <div id="sec_autoctrl_info_cliente">
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <colgroup>
                        <col width="200">
                        <col width="40%">
                        <col width="185">
                        <col>
                    </colgroup>
                    <tr>
                        <td class="tdleft">N° OT</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$agenda['prd_number'])?></td>
                        <td class="tdleft">Cant. Colores</td>
                        <td class="tdnrm"><b><?=$fmt($amtColorsSeri)?></b></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Cliente</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                        <td class="tdleft">Color N° 1</td>
                        <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_1'] ?? 0) || (int)($agenda['fab_print_colors_back_1'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? '')) : ''?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">N° CC</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$agenda['req_number'])?></td>
                        <td class="tdleft">Color N° 2</td>
                        <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_2'] ?? 0) || (int)($agenda['fab_print_colors_back_2'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_2'] ?? '')) : ''?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Producto</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_number_prod'])?> | <?=htmlspecialchars((string)$agenda['item_title'])?></td>
                        <td class="tdleft">Color N° 3</td>
                        <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_3'] ?? 0) || (int)($agenda['fab_print_colors_back_3'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_3'] ?? '')) : ''?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Materialidad</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                        <td class="tdleft">Color N° 4</td>
                        <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_4'] ?? 0) || (int)($agenda['fab_print_colors_back_4'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_4'] ?? '')) : ''?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Ancho</td>
                        <td class="tdnrm"><?=(int)$agenda['fab_med_width']?></td>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="tdleft">Alto</td>
                        <td class="tdnrm"><?=(int)$agenda['fab_med_height']?></td>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="tdleft">Fuelle</td>
                        <td class="tdnrm"><?=(int)$agenda['fab_med_fuelle']?></td>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="tdleft">Cantidad de Bolsas</td>
                        <td class="tdnrm"><b><?=$fmt($agenda['ag_amount'])?></b></td>
                        <td class="tdleft">Fecha de Entrega</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($agenda['req_despacho_desc'] !== '' ? $agenda['req_despacho_desc'] : $agenda['ag_date']))?></b></td>
                    </tr>
                    <tr>
                        <td class="tdleft" colspan="2">Fecha solicitud película</td>
                        <td class="tdnrm" colspan="2"><?=(int)($agenda['req_cliche_peli_solic_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_solic_dat']) : '<b class="msg_save_err">N/A</b>'?></td>
                    </tr>
                    <tr>
                        <td class="tdleft" colspan="2">Fecha recepción película</td>
                        <td class="tdnrm" colspan="2"><?=(int)($agenda['req_cliche_peli_recep_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_recep_dat']) : '<b class="msg_save_err">N/A</b>'?></td>
                    </tr>
                    <tr>
                        <td class="tdleft" colspan="2">Imagen diseño</td>
                        <td class="tdnrm" colspan="2">
                            <?php if ($acHasImg): ?>
                                <a href="<?=$acImgUrl?>" target="_blank" onclick="openDesignModal('<?=$acImgUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($acFullDesign))?>', '<?=htmlspecialchars($acMedidasStr)?>'); return false;" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                    <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen
                                </a>
                            <?php else: ?>
                                <span style="color:#999">Sin imagen cargada</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php for ($x = 0; $x < 5; $x++): ?>
                        <?php if (!empty($agenda["req_prod_adjfile_{$x}"])):
                            $adjFile = (string)$agenda["req_prod_adjfile_{$x}"];
                            $adjIsImage = (bool)preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $adjFile);
                            $adjUrl = '/docs/prod/' . rawurlencode($adjFile);
                        ?>
                        <tr>
                            <td class="tdleft" colspan="2">Imagen x #<?=($x + 1)?></td>
                            <td class="tdnrm" colspan="2">
                                <?php if ($adjIsImage): ?>
                                    <a href="<?=$adjUrl?>" target="_blank" onclick="openDesignModal('<?=$adjUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($acMedidasStr)?>'); return false;" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                        <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen
                                    </a>
                                <?php else: ?>
                                    <a href="<?=$adjUrl?>" target="_blank" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                        <i class="fa fa-fw fa-file" style="color:white;"></i> Mostrar Documento
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($agenda["req_prod_adjcomments_{$x}"])): ?>
                                    <span style="margin-left:8px;color:#64748B;font-size:11.5px"><?=htmlspecialchars((string)$agenda["req_prod_adjcomments_{$x}"])?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <tr>
                        <td class="tdleft" colspan="2" style="<?=$internalFabricationNoteLabelStyle?>">Observación interna</td>
                        <td class="tdnrm" colspan="2" style="<?=$internalFabricationNoteValueStyle?>"><?=$internalFabricationNoteHtml?></td>
                    </tr>
                    </table>
                </div>
            </div>

            <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
                <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_autoctrl_info_fab', 'icon_autoctrl_info_fab')">
                    <span><i class="fa fa-fw fa-info-circle"></i> Información de Fabricación</span>
                    <span class="unibag-collapse-btn">
                        <span id="lbl_autoctrl_info_fab">Minimizar</span>
                        <i id="icon_autoctrl_info_fab" class="fa fa-chevron-up"></i>
                    </span>
                </div>
                <div id="sec_autoctrl_info_fab">
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <colgroup>
                        <col width="200">
                        <col width="40%">
                        <col width="185">
                        <col>
                    </colgroup>
                    <tr>
                        <td class="tdleft">Desarrollo</td>
                        <td class="tdnrm"><b><?=$fmt($corte_m2, 4)?> mtrs</b></td>
                        <td class="tdleft">Color N° 1</td>
                        <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_1'] ?? 0) || (int)($agenda['fab_print_colors_back_1'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? '')) : ''?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Pie de Imprenta</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_pie_imprenta'] ?? '-'))?></td>
                        <td class="tdleft">Color N° 2</td>
                        <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_2'] ?? 0) || (int)($agenda['fab_print_colors_back_2'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_2'] ?? '')) : ''?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">N° Código de Barra</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['item_sellprice_barcodenumber'] ?? '-'))?></td>
                        <td class="tdleft">Color N° 3</td>
                        <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_3'] ?? 0) || (int)($agenda['fab_print_colors_back_3'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_3'] ?? '')) : ''?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Impresiones en cilindro</td>
                        <td class="tdnrm">&nbsp;</td>
                        <td class="tdleft">Color N° 4</td>
                        <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_4'] ?? 0) || (int)($agenda['fab_print_colors_back_4'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_4'] ?? '')) : ''?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Impresiones por desarrollo</td>
                        <td class="tdnrm"><?=(int)($agenda['req_solic_devprints_cc'] ?? 0)?> impresiones</td>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm">&nbsp;</td>
                    </tr>
                    <tr>
                        <td colspan="4"><div style="height:10px"></div></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Materialidad</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_type'] ?? '-'))?></td>
                        <td class="tdleft">Metros a imprimir</td>
                        <td class="tdnrm"><?=$fmt($acSeriMlinPrint)?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Color Tela</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fabric_color'] ?? '-'))?></td>
                        <td class="tdleft">Contador impresora</td>
                        <td class="tdnrm"><?=$fmt($acSeriPasadasPrint)?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Ancho Tela</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_operador_tela_width'] ?? '-'))?></td>
                        <td class="tdleft">Kg a imprimir</td>
                        <td class="tdnrm"><?=$fmt($acSeriKgsProcess)?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Gramaje</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_mat_gramms'] ?? '-'))?> gr</td>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="tdleft" colspan="2">Cantidad de imágenes impresas en bastidor</td>
                        <td class="tdnrm" colspan="2">
                            <form method="get" action="/production/work-orders/operate" style="margin:0;display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap">
                                <input type="hidden" name="agid" value="<?=$agId?>">
                                <input type="hidden" name="mode" value="setspecparams">
                                <input type="hidden" name="req_id" value="<?=(int)($agenda['ag_reqid'] ?? $agenda['req_id'] ?? 0)?>">
                                <select class="inptxt" name="bastidoramt" style="width:120px">
                                    <option value="">Seleccione</option>
                                    <?php for ($zzz = 1; $zzz <= 5; $zzz++): ?>
                                        <option value="<?=$zzz?>" <?=((int)($agenda['req_operador_bastidoramt'] ?? 1) === $zzz) ? 'selected' : ''?>><?=$zzz?></option>
                                    <?php endfor; ?>
                                </select>
                                <input type="submit" class="btngreen" value="Guardar" style="padding:5px 12px;border:none">
                            </form>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft" colspan="2">Imagen diseño</td>
                        <td class="tdnrm" colspan="2">
                            <?php if ($acHasImg): ?>
                                <a href="<?=$acImgUrl?>" target="_blank" onclick="openDesignModal('<?=$acImgUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($acFullDesign))?>', '<?=htmlspecialchars($acMedidasStr)?>'); return false;" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                    <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen
                                </a>
                            <?php else: ?>
                                <span style="color:#999">Sin imagen cargada</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php for ($x = 0; $x < 5; $x++): ?>
                        <?php if (!empty($agenda["req_prod_adjfile_{$x}"])):
                            $adjFile = (string)$agenda["req_prod_adjfile_{$x}"];
                            $adjIsImage = (bool)preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $adjFile);
                            $adjUrl = '/docs/prod/' . rawurlencode($adjFile);
                        ?>
                        <tr>
                            <td class="tdleft" colspan="2">Imagen x #<?=($x + 1)?></td>
                            <td class="tdnrm" colspan="2">
                                <?php if ($adjIsImage): ?>
                                    <a href="<?=$adjUrl?>" target="_blank" onclick="openDesignModal('<?=$adjUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($acMedidasStr)?>'); return false;" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                        <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen
                                    </a>
                                <?php else: ?>
                                    <a href="<?=$adjUrl?>" target="_blank" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                        <i class="fa fa-fw fa-file" style="color:white;"></i> Mostrar Documento
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($agenda["req_prod_adjcomments_{$x}"])): ?>
                                    <span style="margin-left:8px;color:#64748B;font-size:11.5px"><?=htmlspecialchars((string)$agenda["req_prod_adjcomments_{$x}"])?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endfor; ?>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <!-- Tarjeta 1: Información Cliente y Diseño para Autocontrol -->
            <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
                <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_autoctrl_info_cliente', 'icon_autoctrl_info_cliente')">
                    <span><i class="fa fa-fw fa-user"></i> 1. Información Cliente y Diseño</span>
                    <span class="unibag-collapse-btn">
                        <span id="lbl_autoctrl_info_cliente">Minimizar</span>
                        <i id="icon_autoctrl_info_cliente" class="fa fa-chevron-up"></i>
                    </span>
                </div>
                <div id="sec_autoctrl_info_cliente" style="padding:15px">
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <colgroup>
                        <col width="180">
                        <col width="32%">
                        <col width="160">
                        <col>
                    </colgroup>
                    <tr>
                        <td class="tdleft">N° OT</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$agenda['prd_number'])?></b></td>
                        <td class="tdleft">Color N° 1</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Cliente</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$agenda['cust_name'])?></b></td>
                        <td class="tdleft">Color N° 2</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_2'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">N° CC</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$agenda['req_number'])?></b></td>
                        <td class="tdleft">Color N° 3</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_3'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Diseño</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_design_name'] !== '' ? $agenda['fab_design_name'] : $agenda['item_title']))?></td>
                        <td class="tdleft">Color N° 4</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_4'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Producto</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_number_prod'])?> | <?=htmlspecialchars((string)$agenda['item_title'])?></td>
                        <td class="tdleft">Color N° 5</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_5'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Materialidad</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                        <td class="tdleft">Color N° 6</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_6'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Medidas (Ancho x Alto)</td>
                        <td class="tdnrm"><b><?=(int)$agenda['fab_med_width']?> x <?=(int)$agenda['fab_med_height']?> cm</b></td>
                        <td class="tdleft">Colores Resumen</td>
                        <td class="tdnrm"><?=htmlspecialchars($printcolors ?: '-')?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Fuelle</td>
                        <td class="tdnrm"><?=(int)$agenda['fab_med_fuelle']?> cm</td>
                        <td class="tdleft">Fecha de Entrega</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($agenda['req_despacho_desc'] !== '' ? $agenda['req_despacho_desc'] : $agenda['ag_date']))?></b></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Cantidad de Bolsas</td>
                        <td class="tdnrm"><b><?=$fmt($agenda['ag_amount'])?></b></td>
                        <td class="tdleft">Fecha solicitud cliché</td>
                        <td class="tdnrm"><?=(int)($agenda['req_cliche_peli_solic_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_solic_dat']) : '-'?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Color Tela</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fabric_color'] ?? '-'))?></td>
                        <td class="tdleft">Fecha recepción cliché</td>
                        <td class="tdnrm"><?=(int)($agenda['req_cliche_peli_recep_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_recep_dat']) : '-'?></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="background:#EDF0F6;padding:4px"></td>
                    </tr>
                    <tr>
                        <td class="tdleft" valign="middle">Imagen Diseño</td>
                        <td class="tdnrm" colspan="3" valign="middle">
                            <?php if ($acHasImg): ?>
                                <div style="display:inline-flex;align-items:center;gap:14px;flex-wrap:wrap">
                                    <div style="cursor:pointer;display:inline-flex;align-items:center;gap:12px;background:#F8FAFC;border:2px solid #0284C7;padding:8px 14px;border-radius:6px;box-shadow:0 2px 6px rgba(2,132,199,0.15)"
                                         onclick="openDesignModal('<?=htmlspecialchars($acImgUrl)?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($acFullDesign))?>', '<?=htmlspecialchars($acMedidasStr)?>')"
                                         title="Clic para ver foto del diseño en ventana flotante">
                                        <img src="<?=htmlspecialchars($acImgUrl)?>" alt="Foto Diseño" style="height:70px;max-width:120px;object-fit:contain;border:1px solid #CBD5E1;border-radius:4px;background:#fff">
                                        <div>
                                            <div style="font-size:13px;font-weight:700;color:#0284C7"><i class="fa fa-search-plus"></i> Abrir en Ventana Flotante</div>
                                            <div style="font-size:11px;color:#64748B;margin-top:2px">Clic aquí para ampliar y revisar diseño</div>
                                        </div>
                                    </div>
                                    <a href="<?=$acImgUrl?>" target="_blank" class="btngrey" style="padding:6px 12px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px">
                                        <i class="fa fa-fw fa-external-link-alt" style="color:white;"></i> Pestaña nueva
                                    </a>
                                </div>
                            <?php else: ?>
                                <span style="color:#999;font-style:italic">Sin imagen de diseño cargada</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php for ($x = 0; $x < 5; $x++): ?>
                        <?php if (!empty($agenda["req_prod_adjfile_{$x}"])):
                            $adjFile = (string)$agenda["req_prod_adjfile_{$x}"];
                            $isImg = (bool)preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $adjFile);
                            $adjUrl = "/docs/prod/" . rawurlencode($adjFile);
                        ?>
                            <tr>
                                <td class="tdleft">Imagen Adjunta #<?=($x + 1)?></td>
                                <td class="tdnrm" colspan="3">
                                    <div style="display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap">
                                        <?php if ($isImg): ?>
                                            <img src="<?=$adjUrl?>" alt="Adjunto #<?=($x + 1)?>" style="height:48px;max-width:80px;object-fit:contain;border:1px solid #cbd5e1;border-radius:4px;background:#fff;cursor:pointer"
                                                 onclick="openDesignModal('<?=$adjUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($acMedidasStr)?>')"
                                                 title="Clic para ampliar en ventana flotante">
                                        <?php endif; ?>
                                        <button type="button" class="btngrey" style="padding:4px 10px;font-size:12px;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:5px"
                                                onclick="openDesignModal('<?=$adjUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($acMedidasStr)?>')">
                                            <i class="fa fa-fw fa-<?= $isImg ? 'image' : 'file' ?>" style="color:white;"></i> <?= $isImg ? 'Ver Imagen #' . ($x + 1) : 'Descargar Adjunto #' . ($x + 1) ?>
                                        </button>
                                        <?php if (!empty($agenda["req_prod_adjcomments_{$x}"])): ?>
                                            <span style="margin-left:6px;color:#475569;font-size:11.5px"><?=htmlspecialchars((string)$agenda["req_prod_adjcomments_{$x}"])?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endfor; ?>
                    </table>
                </div>
            </div>

            <!-- Tarjeta 2: Información de Fabricación para Autocontrol -->
            <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
                <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_autoctrl_info_fab', 'icon_autoctrl_info_fab')">
                    <span><i class="fa fa-fw fa-cogs"></i> 2. Información de Fabricación</span>
                    <span class="unibag-collapse-btn">
                        <span id="lbl_autoctrl_info_fab">Minimizar</span>
                        <i id="icon_autoctrl_info_fab" class="fa fa-chevron-up"></i>
                    </span>
                </div>
                <div id="sec_autoctrl_info_fab" style="padding:15px">
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <colgroup>
                        <col width="180">
                        <col width="32%">
                        <col width="160">
                        <col>
                    </colgroup>
                    <tr>
                        <td class="tdleft">Rodillo a utilizar</td>
                        <td class="tdnrm"><b style="color:#0284C7;font-size:14px">Z = <?=$fmt($corte_z, 0)?></b></td>
                        <td class="tdleft">Unidad N° 1</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Corte de bolsa</td>
                        <td class="tdnrm"><?=$fmt($corte_m2, 4)?> mtrs</td>
                        <td class="tdleft">Unidad N° 2</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_2'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Ubicación Clisé</td>
                        <td class="tdnrm"><b style="color:#0F766E"><?=htmlspecialchars((string)($agenda['req_infoaddprd_cliche_ubicacion'] ?? '-'))?></b></td>
                        <td class="tdleft">Unidad N° 3</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_3'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Código Cliché</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($agenda['req_infoaddprd_cliche_codigo'] ?? '-'))?></b></td>
                        <td class="tdleft">Unidad N° 4</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_4'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Pie de Imprenta / Máquina</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($agenda['req_pie_imprenta'] ?? '-'))?></b></td>
                        <td class="tdleft">Unidad N° 5</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_5'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">N° Código de Barra</td>
                        <td class="tdnrm"><b style="font-family:monospace;font-size:13px"><?=htmlspecialchars((string)($agenda['item_sellprice_barcodenumber'] ?? '-'))?></b></td>
                        <td class="tdleft">Unidad N° 6</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_6'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Ancho Bobina / Tela</td>
                        <td class="tdnrm"><b><?= $acTelaWidth > 0 ? $acTelaWidth . ' cm' : '-' ?></b></td>
                        <td class="tdleft">Anilox N° 1</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($aniloxDescMap[1] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Impresiones por desarrollo</td>
                        <td class="tdnrm"><?=(int)($agenda['req_solic_devprints_cc'] ?? 0)?> impresiones</td>
                        <td class="tdleft">Anilox N° 2</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($aniloxDescMap[2] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">% de merma</td>
                        <td class="tdnrm"><?=$fmt($agenda['req_operador_mermaperc'], 2)?> %</td>
                        <td class="tdleft">Anilox N° 3</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($aniloxDescMap[3] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Metros a imprimir</td>
                        <td class="tdnrm"><b><?=$fmt($metrosAImprimir, 2)?> mtrs</b></td>
                        <td class="tdleft">Anilox N° 4</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($aniloxDescMap[4] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Color Tela</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($agenda['fabric_color'] ?? '-'))?></b></td>
                        <td class="tdleft">Anilox N° 5</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($aniloxDescMap[5] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Saldo total</td>
                        <td class="tdnrm"><b><?=$fmt($stats['saldo_total'] ?? 0)?> unidades</b></td>
                        <td class="tdleft">Anilox N° 6</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($aniloxDescMap[6] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="background:#EDF0F6;padding:4px"></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Materialidad</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                        <td class="tdleft">Metros a imprimir</td>
                        <td class="tdnrm"><b><?=$fmt($metrosAImprimir, 2)?> mtrs</b></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Color Tela</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fabric_color'] ?? '-'))?></td>
                        <td class="tdleft">Contador impresora</td>
                        <td class="tdnrm"><b><?=$fmt(round($metrosAImprimir / 0.41))?></b></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Ancho Tela</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_operador_tela_width'] ?? '-'))?> cm</td>
                        <td class="tdleft">Kg a imprimir</td>
                        <td class="tdnrm"><b><?=$fmt($kgAImprimir, 2)?> kgs</b></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Gramaje</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_mat_gramms'] ?? '70'))?> gr</td>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="tdleft" style="<?=$internalFabricationNoteLabelStyle?>">Observación interna</td>
                        <td class="tdnrm" colspan="3" style="<?=$internalFabricationNoteValueStyle?>"><?=$internalFabricationNoteHtml?></td>
                    </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Tarjeta 2: AUTOCONTROL o APROBACIÓN DE PARTIDA -->
        <?php if ($showSupervisorApproval): ?>
            <!-- FOTO 3: APROBACIÓN PARTIDA POR SUPERVISOR (autocontrol.form.php) -->
            <?php
            $pts = !empty($autoctrlStatus['points']) ? $autoctrlStatus['points'] : $autocontrolPoints;
            ?>
            <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px">
            <tr>
                <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <colgroup>
                        <col width="25">
                        <col width="25%">
                        <col width="25">
                        <col width="25%">
                    </colgroup>
                    <tr>
                        <td class="tdleft" colspan="4" style="background-color:#FFFF00;border:0px;border-radius:4px;color:black;font-weight:bold;text-align:center" align="center">AUTOCONTROL</td>
                    </tr>
                    <tr>
                    <?php
                    $px = 0;
                    foreach ($pts as $p):
                    ?>
                        <td class="tdnrm">
                            <input type="checkbox" value="1" checked disabled>
                            <?=htmlspecialchars((string)$p['ac_name'])?>
                        </td>
                        <?php
                        $px++;
                        if ($px >= 4):
                            $px = 0;
                            echo '</tr><tr>';
                        endif;
                    endforeach;
                    ?>
                    </tr>
                    </table>
                </td>
            </tr>
            </table>

            <form action="/production/work-orders/autocontrol/supervisor" method="post" name="xform_autoctrl_super" id="xform_autoctrl_super">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <input type="hidden" name="mode" value="superautoctrlsave">
                <input type="hidden" name="submode" value="supersave">

                <table border="0" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
                        <table border="0" width="100%" cellpadding="6" cellspacing="0">
                        <colgroup>
                            <col width="25">
                            <col width="25%">
                            <col width="25">
                            <col width="25%">
                        </colgroup>
                        <tr>
                            <td class="tdleft" colspan="4" style="background-color:#92D050;border:0px;border-radius:4px;color:black;font-weight:bold;text-align:center" align="center">
                                APROBACIÓN PARTIDA
                                <span style="float:left;font-weight:normal">
                                    <label style="cursor:pointer">
                                        <input type="checkbox" name="dummy_aprobpart_markall" id="dummy_aprobpart_markall" value="1"
                                               onclick="document.querySelectorAll('.superaprobchk').forEach(c=>c.checked=this.checked); checkSuperAprob();">
                                        Marcar todos
                                    </label>
                                </span>
                            </td>
                        </tr>
                        <tr>
                        <?php
                        $px = 0;
                        foreach ($pts as $p):
                            $acid = (int)$p['id'];
                        ?>
                            <td class="tdnrm">
                                <input type="hidden" name="superbaseacids[]" value="<?=$acid?>">
                                <input type="checkbox" value="1" name="superacids_<?=$acid?>" class="superaprobchk clsaprobpartchks"
                                       id="super_acid_<?=$acid?>"
                                       onclick="checkSuperAprob()">
                                <label for="super_acid_<?=$acid?>" style="cursor:pointer"><?=htmlspecialchars((string)$p['ac_name'])?></label>
                            </td>
                            <?php
                            $px++;
                            if ($px >= 4):
                                $px = 0;
                                echo '</tr><tr>';
                            endif;
                        endforeach;
                        ?>
                        </tr>
                        </table>
                    </td>
                </tr>
                </table>

                <div style="clear:both;height:12px"></div>
                <table border="0" width="100%" cellpadding="0" cellspacing="0" id="idx_btnsuperaprob" style="display:none">
                <tr>
                    <td width="210">
                        <input type="text" class="inptxt" name="axx_username" id="axx_username" placeholder="Usuario Supervisor" style="width:200px" autocomplete="off">
                    </td>
                    <td width="210">
                        <input type="password" class="inptxt" name="axx_pass" id="axx_pass" placeholder="Contraseña Supervisor" style="width:200px" autocomplete="off">
                    </td>
                    <td>
                        <button type="submit" class="btngreen" style="padding:8px 22px;border:none;cursor:pointer;font-size:14px">
                            <i class="fa fa-fw fa-save" style="color:white;"></i> Aprobar partida&nbsp;
                        </button>
                    </td>
                </tr>
                </table>
            </form>

            <script>
            function checkSuperAprob() {
                var canaprob = true;
                var chks = document.querySelectorAll('.superaprobchk');
                if (chks.length === 0) canaprob = false;
                chks.forEach(function(c) {
                    if (!c.checked) canaprob = false;
                });
                var box = document.getElementById('idx_btnsuperaprob');
                if (box) {
                    if (canaprob) {
                        box.style.display = '';
                        var u = document.getElementById('axx_username');
                        if (u && !u.value) u.focus();
                    } else {
                        box.style.display = 'none';
                    }
                }
            }
            checkSuperAprob();
            </script>

        <?php else: ?>
            <!-- FOTO 2: AUTOCONTROL OPERADOR (autocontrol.form.php) -->
            <form action="/production/work-orders/autocontrol" method="post" name="xform_autoctrl" id="xform_autoctrl">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <input type="hidden" name="mode" value="autoctrlsave">
                <input type="hidden" name="submode" id="autoctrl_submode" value="close">

                <table border="0" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
                        <table border="0" width="100%" cellpadding="6" cellspacing="0">
                        <colgroup>
                            <col width="25">
                            <col width="25%">
                            <col width="25">
                            <col width="25%">
                        </colgroup>
                        <tr>
                            <td class="tdleft" colspan="4" style="background-color:#FFFF00;border:0px;border-radius:4px;color:black;font-weight:bold;font-size:14px;padding:8px;text-align:center" align="center">
                                AUTOCONTROL
                            </td>
                        </tr>
                        <tr>
                        <?php
                        $px = 0;
                        $workerResponses = $autoctrlStatus['worker_responses'] ?? [];
                        foreach ($autocontrolPoints as $p):
                            $acid = (int)$p['id'];
                            $isChk = !empty($workerResponses[$acid]);
                        ?>
                            <td class="tdnrm">
                                <input type="hidden" name="baseacids[]" value="<?=$acid?>">
                                <input type="checkbox" value="1" name="acids_<?=$acid?>" class="clsautocontrolchks"
                                       id="acid_chk_<?=$acid?>"
                                       <?=($isChk ? 'checked' : '')?>
                                       onchange="checkOperatorAutocontrolReady()">
                                <label for="acid_chk_<?=$acid?>" style="cursor:pointer"><?=htmlspecialchars((string)$p['ac_name'])?></label>
                            </td>
                            <?php
                            $px++;
                            if ($px >= 4):
                                $px = 0;
                                echo '</tr><tr>';
                            endif;
                        endforeach;
                        ?>
                        </tr>
                        </table>
                    </td>
                </tr>
                </table>

                <div style="clear:both;height:12px"></div>

                <?php if ($equipoTypeId === 7 || $isFlexo): ?>
                    <div id="idx_lider_btn_wrap" style="margin-bottom:8px">
                        <button type="submit" class="btngreen" id="idx_lider_btn"
                                style="width:100%;box-sizing:border-box;background-color:#FF5722;color:white;font-weight:bold;font-size:14px;padding:10px 0;border:none;border-radius:4px;cursor:pointer;display:block;text-align:center"
                                onclick="document.getElementById('autoctrl_submode').value='close2';">
                            <i class="fa fa-fw fa-envelope" style="color:white;"></i> Informar Lider de Área
                        </button>
                    </div>
                <?php endif; ?>

                <div id="idx_supervisor_btn_wrap" style="margin-bottom:12px">
                    <button type="submit" class="btngreen" id="idx_supervisor_btn"
                                style="width:100%;box-sizing:border-box;background-color:#4597C2;color:white;font-weight:bold;font-size:14px;padding:10px 0;border:none;border-radius:4px;cursor:pointer;display:block;text-align:center"
                                onclick="document.getElementById('autoctrl_submode').value='close';">
                        <i class="fa fa-fw fa-envelope" style="color:white;"></i> Informa supervisor
                    </button>
                </div>
            </form>

            <script>
            function checkOperatorAutocontrolReady() {
                var allChecked = true;
                var chks = document.querySelectorAll('.clsautocontrolchks');
                if (chks.length === 0) allChecked = false;
                chks.forEach(function(c) {
                    if (!c.checked) allChecked = false;
                });
                var btnSup = document.getElementById('idx_supervisor_btn');
                var btnLid = document.getElementById('idx_lider_btn');
                if (btnSup) {
                    btnSup.style.opacity = allChecked ? '1' : '0.5';
                }
                if (btnLid) {
                    btnLid.style.opacity = allChecked ? '1' : '0.5';
                }
                return allChecked;
            }
            document.getElementById('xform_autoctrl').addEventListener('submit', function(e) {
                if (!checkOperatorAutocontrolReady()) {
                    e.preventDefault();
                    alert('Debe seleccionar todos los puntos de autocontrol antes de informar.');
                    return false;
                }
            });
            checkOperatorAutocontrolReady();

            </script>
        <?php endif; ?>

        <!-- Tarjeta 3: Botones auxiliares de autocontrol.form.php -->
        <?php
        $autoHidePauseShortcut = ($isSeri || $isSeriPulpo);
        $autoMaterialsLabel = $isRebobinadora ? 'Bobina' : (($isSeri || $isSeriPulpo) ? 'Insumos' : 'Materiales');
        $autoHasTintasShortcut = $isFlexo;
        ?>
        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px">
        <tr>
            <td width="<?=$autoHasTintasShortcut ? ($autoHidePauseShortcut ? '34%' : '25%') : ($autoHidePauseShortcut ? '50%' : '33%')?>" style="padding-right:3px">
                <div class="btnblue" style="width:100%;box-sizing:border-box;background-color:#293865;padding:10px 0;cursor:pointer;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16);font-weight:800" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=materiales&fromautocontrol=1'">
                    <i class="fa fa-fw <?= $isRebobinadora ? 'fa-dolly' : 'fa-cubes' ?>"></i> <?=$autoMaterialsLabel?>
                </div>
            </td>
            <?php if ($autoHasTintasShortcut): ?>
                <td width="<?=$autoHidePauseShortcut ? '33%' : '25%'?>" style="padding:0 3px">
                    <div class="btnblue" style="width:100%;box-sizing:border-box;background-color:#1D4ED8;padding:10px 0;cursor:pointer;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16);font-weight:800" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=tintas&fromautocontrol=1'">
                        <i class="fa fa-fw fa-tint"></i> Tintas
                    </div>
                </td>
            <?php endif; ?>
            <td width="<?=$autoHasTintasShortcut ? ($autoHidePauseShortcut ? '33%' : '25%') : ($autoHidePauseShortcut ? '50%' : '34%')?>" style="padding:0 3px">
                <div class="btnred" style="width:100%;box-sizing:border-box;background-color:#D64141;padding:10px 0;cursor:pointer;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16);font-weight:800" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=mantencion&fromautocontrol=1'">
                    <i class="fa fa-fw fa-wrench"></i> Mantención
                </div>
            </td>
            <?php if (!$autoHidePauseShortcut): ?>
                <td width="<?=$autoHasTintasShortcut ? '25%' : '33%'?>" style="padding-left:3px">
                    <div class="btngrey" style="width:100%;box-sizing:border-box;background-color:#7F7F7F;padding:10px 0;cursor:pointer;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16);font-weight:800" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=pause&fromautocontrol=1'">
                        <i class="fa fa-fw fa-coffee"></i> Pausa
                    </div>
                </td>
            <?php endif; ?>
        </tr>
        </table>
        <?php
        echo renderDesignPreviewModal();
        $content = ob_get_clean();
        renderProdwrkShell('Operar OT #' . $agenda['prd_number'], $content, 2, $currentOperatorName, $plantaName);
        return;
    ?>
    <?php endif; ?>

    <!-- Encabezado de Navegación / Breadcrumb idéntico a editxid.php -->
    <div style="font-size:20px;margin-bottom:10px"><b>En curso</b></div>

    <?php if (isset($_GET['alis_started'])): ?>
        <div style="background:#E8F5E9;color:#2E7D32;padding:12px 18px;border-radius:4px;border:1px solid #A5D6A7;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle"></i> <b>Alistamiento iniciado exitosamente como evento de la OT.</b> El evento figura en el registro inferior como &quot;En curso&quot;. Complete la preparación de la máquina y luego seleccione todas las confirmaciones para terminarlo.
        </div>
    <?php elseif (isset($_GET['alis_finished'])): ?>
        <div style="background:#E8F5E9;color:#2E7D32;padding:12px 18px;border-radius:4px;border:1px solid #A5D6A7;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle"></i> <b>Alistamiento y confirmaciones de máquina completados.</b> El evento de alistamiento ha sido finalizado. Proceda a la aprobación de partida con las credenciales del supervisor.
        </div>
    <?php elseif (isset($_GET['partida_approved'])): ?>
        <div style="background:#E8F5E9;color:#2E7D32;padding:12px 18px;border-radius:4px;border:1px solid #A5D6A7;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle"></i> <b>¡Partida aprobada por Supervisor!</b> La orden de trabajo se encuentra 100% habilitada para registrar producción.
        </div>
    <?php elseif (isset($_GET['saved_anilox'])): ?>
        <div style="background:#E8F5E9;color:#2E7D32;padding:12px 18px;border-radius:4px;border:1px solid #A5D6A7;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle"></i> <b>Conformación de Anilox y tintas guardada exitosamente.</b>
        </div>
    <?php elseif (isset($_GET['prod_started'])): ?>
        <div style="background:#E8F5E9;color:#1B5E20;padding:12px 18px;border-radius:4px;border:1px solid #81C784;margin-bottom:12px;font-size:13px">
            <i class="fa fa-play-circle" style="font-size:15px"></i> <b>▶️ Tiraje de Producción iniciado exitosamente.</b> La máquina se encuentra ahora en producción activa. Al terminar la tirada o cambiar de turno, pulse &quot;Finalizar Producción&quot; para registrar las bolsas/metros y mermas reales.
        </div>
    <?php elseif (isset($_GET['prod_ended'])): ?>
        <div style="background:#E8F5E9;color:#1B5E20;padding:12px 18px;border-radius:4px;border:1px solid #81C784;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle" style="font-size:15px"></i> <b>⏹️ Tiraje de Producción finalizado correctamente.</b> Se han guardado las unidades, metros y registro de mermas/defectos.
        </div>
    <?php elseif (isset($_GET['saved_prod'])): ?>
        <div style="background:#E8F5E9;color:#1B5E20;padding:12px 18px;border-radius:4px;border:1px solid #81C784;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle"></i> <b>Registro de producción actualizado exitosamente.</b>
        </div>
    <?php elseif (isset($_GET['saved_material'])): ?>
        <div style="background:#ECFDF5;color:#065F46;padding:12px 18px;border-radius:4px;border:1px solid #6EE7B7;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle"></i> <b>Bobinas / Insumos solicitados exitosamente desde bodega para la OT.</b> Quedan disponibles al pie de máquina.
        </div>
    <?php elseif (isset($_GET['mounted'])): ?>
        <div style="background:#ECFDF5;color:#065F46;padding:12px 18px;border-radius:4px;border:1px solid #6EE7B7;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle"></i> <b>⚙️ Bobina montada exitosamente en la máquina (desbobinador).</b> Ya puede procesarse en producción y registrar su consumo.
        </div>
    <?php elseif (isset($_GET['unmounted'])): ?>
        <div style="background:#FFFBEB;color:#92400E;padding:12px 18px;border-radius:4px;border:1px solid #FCD34D;margin-bottom:12px;font-size:13px">
            <i class="fa fa-pause-circle"></i> <b>Bobina desmontada de la máquina.</b> Permanece asignada a la OT para posterior uso.
        </div>
    <?php elseif (isset($_GET['unassigned'])): ?>
        <div style="background:#EFF6FF;color:#1E40AF;padding:12px 18px;border-radius:4px;border:1px solid #BFDBFE;margin-bottom:12px;font-size:13px">
            <i class="fa fa-undo"></i> <b>Bobina devuelta a bodega exitosamente.</b> Ha sido liberada de esta OT.
        </div>
    <?php elseif (isset($_GET['saved_consumption'])): ?>
        <div style="background:#ECFDF5;color:#065F46;padding:12px 18px;border-radius:4px;border:1px solid #6EE7B7;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle"></i> <b>Consumo y retazo de bobina registrados exitosamente.</b> Trazabilidad de materia prima actualizada.
        </div>
    <?php elseif (isset($_GET['error'])): ?>
        <div style="background:#FFEBEE;color:#C62828;padding:12px 18px;border-radius:4px;border:1px solid #EF9A9A;margin-bottom:12px;font-size:13px">
            <i class="fa fa-exclamation-circle"></i> <?=htmlspecialchars((string)$_GET['error'])?>
        </div>
    <?php endif; ?>

    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px">
    <tr>
        <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td align="left">
                    <i class="fa fa-fw fa-laptop-code" style="font-size:14px;"></i> Ordenes de trabajo
                    <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                    <b>En curso</b>: OT #<?=htmlspecialchars((string)$agenda['prd_number'])?> | Máquina: <b><?=htmlspecialchars((string)($machineInit['equipo_name'] ?? '-'))?></b>
                </td>
                <td align="right" width="220">
                    <div class="btngreen" style="display:inline-block" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=terminarot'">
                        <i class="fa fa-fw fa-check" style="color:white;"></i> Terminar OT&nbsp;
                    </div>
                </td>
            </tr>
            </table>
        </td>
    </tr>
    </table>

    <?php if ($isSeriPulpo && ($prevFrente > 0 || $prevDorso > 0 || $prevFinished > 0)): ?>
        <div style="background:#FFFBEB;border:1px solid #FCD34D;border-left:5px solid #D97706;padding:12px 18px;border-radius:4px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td align="left">
                    <div style="font-size:14px;font-weight:bold;color:#92400E;display:flex;align-items:center;gap:7px">
                        <i class="fa fa-layer-group" style="font-size:16px;color:#D97706"></i>
                        Estado Acumulado Serigrafía Pulpo en esta OT
                    </div>
                    <div style="font-size:12.5px;color:#78350F;margin-top:4px">
                        Pasadas registradas: <b><?=number_format($prevFrente, 0, ',', '.')?></b> Frente &nbsp;|&nbsp; 
                        <b><?=number_format($prevDorso, 0, ',', '.')?></b> Dorso &nbsp;→&nbsp; 
                        <b><?=number_format($prevFinished, 0, ',', '.')?></b> bolsas terminadas completas
                    </div>
                </td>
                <td align="right">
                    <?php if ($prevSurplusFrente > 0): ?>
                        <span style="background:#FEF3C7;color:#92400E;border:1px solid #F59E0B;padding:6px 14px;border-radius:4px;font-weight:bold;font-size:13px;display:inline-flex;align-items:center;gap:6px">
                            <i class="fa fa-exclamation-circle" style="color:#D97706"></i>
                            Quedan <?=number_format($prevSurplusFrente, 0, ',', '.')?> bolsas impresas por el FRENTE (pendientes de dorso)
                        </span>
                    <?php elseif ($prevSurplusDorso > 0): ?>
                        <span style="background:#FEF3C7;color:#92400E;border:1px solid #F59E0B;padding:6px 14px;border-radius:4px;font-weight:bold;font-size:13px;display:inline-flex;align-items:center;gap:6px">
                            <i class="fa fa-exclamation-circle" style="color:#D97706"></i>
                            Quedan <?=number_format($prevSurplusDorso, 0, ',', '.')?> bolsas impresas por el DORSO (pendientes de frente)
                        </span>
                    <?php else: ?>
                        <span style="background:#ECFDF5;color:#065F46;border:1px solid #A7F3D0;padding:6px 14px;border-radius:4px;font-weight:bold;font-size:12px;display:inline-flex;align-items:center;gap:6px">
                            <i class="fa fa-check-circle" style="color:#059669"></i>
                            Frente y Dorso al día (0 pendientes entre caras)
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($openProdEvent !== null): ?>
        <div style="background:linear-gradient(135deg, #059669 0%, #10B981 100%);color:white;padding:12px 18px;border-radius:6px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1)">
            <div style="display:flex;align-items:center;gap:12px">
                <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#A7F3D0;box-shadow:0 0 0 4px rgba(167,243,208,0.4)"></span>
                <div>
                    <div style="font-size:15px;font-weight:bold;letter-spacing:0.5px">MÁQUINA EN PRODUCCIÓN ACTIVA</div>
                    <div style="font-size:12.5px;opacity:0.95">
                        Tiraje #<?=$openProdEvent['id']?> iniciado a las <?=date('H:i:s', (int)$openProdEvent['evt_crtdat'])?> (hace <?=max(0, round((time() - (int)$openProdEvent['evt_crtdat']) / 60))?> min)
                    </div>
                </div>
            </div>
            <div>
                <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=production&refid=<?=$openProdEvent['id']?>&submode=end" class="btnorange" style="font-size:13.5px;padding:9px 20px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-weight:bold;box-shadow:0 2px 4px rgba(0,0,0,0.2);border-radius:8px">
                    <i class="fa fa-stop"></i> Finalizar
                </a>
            </div>
        </div>
    <?php endif; ?>
 
    <?php if ($isFlexo && !empty($printedRollsSummary['rolls_count']) && $printedRollsSummary['rolls_count'] > 0): ?>
        <div style="background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:6px;padding:14px 18px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;color:#166534;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
            <div>
                <div style="font-size:14px;font-weight:bold;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-tag" style="color:#059669;font-size:16px"></i> Bobinas Impresas Producidas en esta OT:
                    <b style="color:#15803D;font-size:16px"><?=number_format($printedRollsSummary['total_weight_kg'], 2, ',', '.')?> kg</b>
                    <span style="font-size:13px;color:#047857">(<?=number_format($printedRollsSummary['total_meters'], 0, ',', '.')?> metros &bull; <?=$printedRollsSummary['rolls_count']?> bobina<?=$printedRollsSummary['rolls_count'] > 1 ? 's' : ''?>)</span>
                </div>
                <div style="font-size:12px;color:#166534;margin-top:4px">
                    Rollos emitidos: <b><?=implode(', ', array_map(fn($r) => $r['roll_code'] . ' (' . number_format($r['weight_kg'], 1, ',', '.') . ' kg)', $printedRollsSummary['rolls_list']))?></b>.
                    Estos valores se sumarán y pasarán automáticamente al cierre de producción.
                </div>
            </div>
            <div style="display:flex;gap:8px">
                <?php if ($openProdEvent !== null): ?>
                    <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=production&refid=<?=$openProdEvent['id']?>&submode=end" class="btngreen" style="font-size:12px;padding:6px 14px;text-decoration:none;border-radius:8px">
                        <i class="fa fa-flag-checkered"></i> Finalizar
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <style>
    .unibag-collapse-header {
        background-color: #165552;
        color: #FFFFFF;
        padding: 8px 14px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        user-select: none;
        font-weight: bold;
        font-size: 13px;
        transition: background-color 0.15s ease;
    }
    .unibag-collapse-header:hover {
        background-color: #124341;
    }
    .unibag-collapse-btn {
        font-size: 11px;
        font-weight: normal;
        opacity: 0.9;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(255,255,255,0.15);
        padding: 3px 8px;
        border-radius: 3px;
    }
    .unibag-collapse-header:hover .unibag-collapse-btn {
        background: rgba(255,255,255,0.25);
    }
    </style>
    <script>
    function toggleCollapseSection(secId, iconId) {
        var sec = document.getElementById(secId);
        var icon = document.getElementById(iconId);
        var lblId = iconId.replace('icon_', 'lbl_');
        var lbl = document.getElementById(lblId);
        if (!sec) return;

        var isHidden = (sec.style.display === 'none');
        if (isHidden) {
            sec.style.display = '';
            if (icon) {
                icon.className = 'fa fa-chevron-up';
            }
            if (lbl) {
                lbl.textContent = 'Minimizar';
            }
            try { localStorage.setItem('unibag_collapse_' + secId, '0'); } catch(e){}
        } else {
            sec.style.display = 'none';
            if (icon) {
                icon.className = 'fa fa-chevron-down';
            }
            if (lbl) {
                lbl.textContent = 'Expandir';
            }
            try { localStorage.setItem('unibag_collapse_' + secId, '1'); } catch(e){}
        }
    }

    function initCollapsibleSections() {
        ['sec_info_cliente', 'sec_info_fab', 'sec_info_anilox', 'sec_info_fab_pulpo'].forEach(function(secId) {
            try {
                var saved = localStorage.getItem('unibag_collapse_' + secId);
                if (saved === '1') {
                    var sec = document.getElementById(secId);
                    var iconId = secId.replace('sec_', 'icon_');
                    var lblId = secId.replace('sec_', 'lbl_');
                    var icon = document.getElementById(iconId);
                    var lbl = document.getElementById(lblId);
                    if (sec) sec.style.display = 'none';
                    if (icon) icon.className = 'fa fa-chevron-down';
                    if (lbl) lbl.textContent = 'Expandir';
                }
            } catch(e){}
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCollapsibleSections);
    } else {
        initCollapsibleSections();
    }
    </script>

    <!-- Bloque de Información según tipo de máquina -->
    <div style="margin-bottom:15px">

    <?php if ($isFlexo): ?>
        <!-- ============================================================== -->
        <!-- RAMA A: FLEXOGRAFÍA (editxid.php líneas 2750 - 3080)           -->
        <!-- ============================================================== -->

        <!-- 1. INFORMACIÓN CLIENTE -->
        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_cliente', 'icon_info_cliente')">
                <span><i class="fa fa-fw fa-user"></i> Información Cliente</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_cliente">Minimizar</span>
                    <i id="icon_info_cliente" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_cliente">
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="220">
                    <col width="30%">
                    <col width="160">
                    <col>
                </colgroup>
                <tr>
                    <td class="tdleft">N° OT</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['prd_number'])?></td>
                    <td class="tdleft">Color N° 1</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Cliente</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                    <td class="tdleft">Color N° 2</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_2'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">N° CC</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['req_number'])?></td>
                    <td class="tdleft">Color N° 3</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_3'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Diseño</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_design_name'] !== '' ? $agenda['fab_design_name'] : $agenda['item_title']))?></td>
                    <td class="tdleft">Color N° 4</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_4'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Producto</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_number_prod'])?> | <?=htmlspecialchars((string)$agenda['item_title'])?></td>
                    <td class="tdleft">Color N° 5</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_5'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Materialidad</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                    <td class="tdleft">Color N° 6</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_6'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Ancho</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_width']?></td>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">&nbsp;</td>
                </tr>
                <tr>
                    <td class="tdleft">Alto</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_height']?></td>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">&nbsp;</td>
                </tr>
                <tr>
                    <td class="tdleft">Fuelle</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_fuelle']?></td>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">&nbsp;</td>
                </tr>
                <tr>
                    <td class="tdleft">Cantidad de Bolsas</td>
                    <td class="tdnrm"><b><?=$fmt($agenda['ag_amount'])?></b></td>
                    <td class="tdleft">Fecha de Entrega</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)($agenda['req_despacho_desc'] !== '' ? $agenda['req_despacho_desc'] : $agenda['ag_date']))?></b></td>
                </tr>
                <tr>
                    <td colspan="4" style="background:#EDF0F6;padding:4px"></td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Fecha de solicitud de cliché</td>
                    <td class="tdnrm" colspan="2">
                        <?=(int)($agenda['req_cliche_peli_solic_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_solic_dat']) : '<span style="color:#999">-</span>'?>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Fecha de recepción de cliché</td>
                    <td class="tdnrm" colspan="2">
                        <?=(int)($agenda['req_cliche_peli_recep_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_recep_dat']) : '<span style="color:#999">-</span>'?>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Imagen Diseño</td>
                    <td class="tdnrm" colspan="2">
                        <?php if (!empty($agenda['fab_design_imagehash']) && $agenda['fab_design_imagehash'] !== 'dummy'): ?>
                            <?php 
                                $dUrl = '/production/design-image?folder=order&hash=' . rawurlencode((string)$agenda['fab_design_imagehash']); 
                                $consFullDesign = trim((string)($agenda['prd_name'] ?? ''));
                                $consMedidasStr = '';
                                if (!empty($agenda['fab_cut_cm'])) $consMedidasStr .= 'Corte ' . (float)$agenda['fab_cut_cm'] . 'cm';
                                if (!empty($agenda['fab_ancho_cm'])) $consMedidasStr .= ($consMedidasStr ? ' · ' : '') . 'Ancho ' . (float)$agenda['fab_ancho_cm'] . 'cm';
                                if (!empty($agenda['fab_alto_cm'])) $consMedidasStr .= ($consMedidasStr ? ' · ' : '') . 'Alto ' . (float)$agenda['fab_alto_cm'] . 'cm';
                            ?>
                            <div style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap">
                                <a href="<?=$dUrl?>" target="_blank" onclick="openDesignModal('<?=$dUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($consFullDesign))?>', '<?=htmlspecialchars($consMedidasStr)?>'); return false;" title="Clic para ampliar foto de lo que se va a producir">
                                    <img src="<?=$dUrl?>" alt="Foto Diseño" style="max-height:85px;max-width:130px;object-fit:contain;border:1px solid #cbd5e1;border-radius:6px;background:#fff;vertical-align:middle;box-shadow:0 1px 3px rgba(0,0,0,0.12);cursor:pointer">
                                </a>
                                <a href="<?=$dUrl?>" target="_blank" onclick="openDesignModal('<?=$dUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($consFullDesign))?>', '<?=htmlspecialchars($consMedidasStr)?>'); return false;" class="btngrey" style="padding:6px 12px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                    <i class="fa fa-fw fa-image" style="color:white;"></i> Ver Foto Completa
                                </a>
                            </div>
                        <?php else: ?>
                            <span style="color:#999">Sin imagen cargada</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php for ($x = 0; $x < 5; $x++): ?>
                    <?php if (!empty($agenda["req_prod_adjfile_{$x}"])): 
                        $adjFile = (string)$agenda["req_prod_adjfile_{$x}"];
                        $isImg = (bool)preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $adjFile);
                    ?>
                        <tr>
                            <td class="tdleft" colspan="2">Imagen #<?=($x + 1)?></td>
                            <td class="tdnrm" colspan="2">
                                <div style="display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap">
                                    <?php if ($isImg): ?>
                                        <a href="/docs/prod/<?=htmlspecialchars($adjFile)?>" target="_blank" onclick="openDesignModal('/docs/prod/<?=htmlspecialchars(addslashes($adjFile))?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($consMedidasStr ?? '')?>'); return false;" title="Clic para ampliar">
                                            <img src="/docs/prod/<?=htmlspecialchars($adjFile)?>" alt="Adjunto #<?=($x + 1)?>" style="max-height:55px;max-width:85px;object-fit:contain;border:1px solid #cbd5e1;border-radius:4px;background:#fff;vertical-align:middle;cursor:pointer">
                                        </a>
                                    <?php endif; ?>
                                    <a href="/docs/prod/<?=htmlspecialchars($adjFile)?>" target="_blank" <?= $isImg ? 'onclick="openDesignModal(\'/docs/prod/' . htmlspecialchars(addslashes($adjFile)) . '\', \'' . htmlspecialchars((string)$agenda['prd_number']) . '\', \'' . htmlspecialchars(addslashes((string)$agenda['cust_name'])) . '\', \'Adjunto #' . ($x + 1) . '\', \'' . htmlspecialchars($consMedidasStr ?? '') . '\'); return false;"' : '' ?> class="btngrey" style="padding:4px 10px;font-size:12px;display:inline-flex;align-items:center;gap:5px;cursor:pointer">
                                        <i class="fa fa-fw fa-<?= $isImg ? 'image' : 'file' ?>" style="color:white;"></i> <?= $isImg ? 'Ver Imagen #' . ($x + 1) : 'Descargar Adjunto #' . ($x + 1) ?>
                                    </a>
                                    <?php if (!empty($agenda["req_prod_adjcomments_{$x}"])): ?>
                                        <span style="margin-left:6px;color:#475569;font-size:11.5px"><?=htmlspecialchars((string)$agenda["req_prod_adjcomments_{$x}"])?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endfor; ?>
                </table>
            </div>
        </div>

        <!-- 2. INFORMACIÓN DE FABRICACIÓN -->
        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_fab', 'icon_info_fab')">
                <span><i class="fa fa-fw fa-info-circle"></i> Información de Fabricación</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_fab">Minimizar</span>
                    <i id="icon_info_fab" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_fab">
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="220">
                    <col width="30%">
                    <col width="160">
                    <col>
                </colgroup>
                <tr>
                    <td class="tdleft">Rodillo a utilizar</td>
                    <td class="tdnrm"><b>Z = <?=$fmt($corte_z, 0)?></b></td>
                    <td class="tdleft">Unidad N° 1</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Corte de bolsa</td>
                    <td class="tdnrm"><?=$fmt($corte_m2, 4)?> mtrs</td>
                    <td class="tdleft">Unidad N° 2</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_2'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Ubicación Clisé</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_infoaddprd_cliche_ubicacion'] ?? '-'))?></td>
                    <td class="tdleft">Unidad N° 3</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_3'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Código Cliché</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_infoaddprd_cliche_codigo'] ?? '-'))?></td>
                    <td class="tdleft">Unidad N° 4</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_4'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Pie de Imprenta</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_pie_imprenta'] ?? '-'))?></td>
                    <td class="tdleft">Unidad N° 5</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_5'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">N° Código de Barra</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['item_sellprice_barcodenumber'] ?? '-'))?></td>
                    <td class="tdleft">Unidad N° 6</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_6'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Impresiones al eje</td>
                    <td class="tdnrm">Sin información</td>
                    <td class="tdleft">Anilox N° 1</td>
                    <td class="tdnrm" id="summary_anilox_1"><?=htmlspecialchars((string)($aniloxDescMap[1] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Impresiones por desarrollo</td>
                    <td class="tdnrm"><?=(int)($agenda['req_solic_devprints_cc'] ?? 0)?> impresiones</td>
                    <td class="tdleft">Anilox N° 2</td>
                    <td class="tdnrm" id="summary_anilox_2"><?=htmlspecialchars((string)($aniloxDescMap[2] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">% de merma</td>
                    <td class="tdnrm"><?=$fmt($agenda['req_operador_mermaperc'], 2)?> %</td>
                    <td class="tdleft">Anilox N° 3</td>
                    <td class="tdnrm" id="summary_anilox_3"><?=htmlspecialchars((string)($aniloxDescMap[3] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft"><?=$isFlexo ? 'Metros producidos / en curso' : 'Bolsas producidas / en curso'?></td>
                    <td class="tdnrm"><?=$fmt($stats['total_produced_this'])?> de <?=$fmt($stats['target_amount'] ?? $agenda['ag_amount'])?> (<?=$fmt($stats['percent_completed_this'], 2)?> %)<?=$isFlexo ? ' mtrs' : ''?></td>
                    <td class="tdleft">Anilox N° 4</td>
                    <td class="tdnrm" id="summary_anilox_4"><?=htmlspecialchars((string)($aniloxDescMap[4] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft"><?=$isFlexo ? 'Metros producidos / total' : 'Bolsas producidas / total'?></td>
                    <td class="tdnrm"><?=$fmt($stats['total_produced_all'])?> de <?=$fmt($stats['order_amount'] ?? $agenda['item_amount'])?> (<?=$fmt($stats['percent_completed_all'], 2)?> %)<?=$isFlexo ? ' mtrs' : ''?></td>
                    <td class="tdleft">Anilox N° 5</td>
                    <td class="tdnrm" id="summary_anilox_5"><?=htmlspecialchars((string)($aniloxDescMap[5] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Saldo total</td>
                    <td class="tdnrm"><b><?=$fmt($stats['saldo_total'])?> <?=$isFlexo ? 'mtrs' : 'unidades'?></b></td>
                    <td class="tdleft">Anilox N° 6</td>
                    <td class="tdnrm" id="summary_anilox_6"><?=htmlspecialchars((string)($aniloxDescMap[6] ?? '-'))?></td>
                </tr>
                <tr>
                    <td colspan="4" style="background:#EDF0F6;padding:4px"></td>
                </tr>
                <tr>
                    <td class="tdleft">Materialidad</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                    <td class="tdleft">Metros a imprimir</td>
                    <td class="tdnrm"><b><?=$fmt($metrosAImprimir, 2)?> mtrs</b></td>
                </tr>
                <tr>
                    <td class="tdleft">Color Tela</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fabric_color'] ?? '-'))?></td>
                    <td class="tdleft">Contador impresora</td>
                    <td class="tdnrm"><b><?=$fmt(round($metrosAImprimir / 0.41))?></b></td>
                </tr>
                <tr>
                    <td class="tdleft">Ancho Tela</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_operador_tela_width'] ?? '-'))?> cm</td>
                    <td class="tdleft">Kg a imprimir</td>
                    <td class="tdnrm"><b><?=$fmt($kgAImprimir, 2)?> kgs</b></td>
                </tr>
                <tr>
                    <td class="tdleft">Gramaje</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_mat_gramms'] ?? '70'))?> gr</td>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">&nbsp;</td>
                </tr>
                <tr>
                    <td class="tdleft" style="<?=$internalFabricationNoteLabelStyle?>">Observación interna</td>
                    <td class="tdnrm" colspan="3" style="<?=$internalFabricationNoteValueStyle?>"><?=$internalFabricationNoteHtml?></td>
                </tr>
                </table>
            </div>
        </div>

        <!-- 3. CONFORMACIÓN DE ANILOX Y CONSUMO DE TINTAS -->
        <style>
        .anilox-swap-highlight {
            background-color: #E8F5E9 !important;
            transition: background-color 0.8s ease;
        }
        .anilox-row-transition {
            transition: background-color 0.3s ease;
        }
        .anilox-kg-badge {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-width:92px;
            padding:6px 10px;
            border-radius:999px;
            font-size:12px;
            font-weight:bold;
            background:#EFF6FF;
            color:#1D4ED8;
            border:1px solid #BFDBFE;
        }
        .anilox-mix-card {
            background:#F8FAFC;
            border:1px solid #D8E2EE;
            border-radius:8px;
            padding:12px;
        }
        .anilox-mix-grid {
            display:grid;
            grid-template-columns: minmax(220px, 1fr) 110px;
            gap:8px;
            align-items:center;
        }
        .anilox-mix-modal {
            position: fixed;
            inset: 0;
            z-index: 99995;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, 0.55);
        }
        .anilox-mix-modal-card {
            width: min(920px, calc(100vw - 40px));
            max-height: calc(100vh - 40px);
            overflow: auto;
            background: #FFFFFF;
            border-radius: 14px;
            box-shadow: 0 18px 48px rgba(15, 23, 42, 0.30);
            border: 1px solid #CBD5E1;
        }
        .anilox-mix-slot {
            display:none;
            grid-template-columns: minmax(220px, 1fr) 110px 42px;
            gap:8px;
            align-items:center;
            margin-bottom:8px;
        }
        .anilox-mix-slot.is-visible {
            display:grid;
        }
        </style>
        <script>
        var isAniloxMoving = false;
        var aniloxMixSlots = 6;

        function showAniloxToast(msg, type) {
            var toast = document.getElementById('anilox_toast_msg');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'anilox_toast_msg';
                toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:999999;padding:12px 20px;border-radius:6px;font-size:13px;font-weight:bold;box-shadow:0 4px 16px rgba(0,0,0,0.2);transition:opacity 0.3s ease;display:none;';
                document.body.appendChild(toast);
            }
            if (type === 'success') {
                toast.style.backgroundColor = '#2E7D32';
                toast.style.color = '#FFFFFF';
                toast.innerHTML = '<i class="fa fa-check-circle" style="margin-right:6px;"></i> ' + msg;
            } else {
                toast.style.backgroundColor = '#C62828';
                toast.style.color = '#FFFFFF';
                toast.innerHTML = '<i class="fa fa-exclamation-circle" style="margin-right:6px;"></i> ' + msg;
            }
            toast.style.display = 'block';
            toast.style.opacity = '1';
            if (window._aniloxToastTimer) clearTimeout(window._aniloxToastTimer);
            window._aniloxToastTimer = setTimeout(function() {
                toast.style.opacity = '0';
                setTimeout(function() { toast.style.display = 'none'; }, 300);
            }, 2600);
        }

        function aniloxParseNumber(value) {
            var parsed = parseFloat(String(value || '').replace(/,/g, '.'));
            return isNaN(parsed) ? 0 : parsed;
        }

        function refreshAniloxSummaryLabel(unit) {
            var sel = document.getElementById('id_anilox_' + unit);
            var sumEl = document.getElementById('summary_anilox_' + unit);
            if (!sel || !sumEl) return;
            var selectedText = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '-';
            if (String(sel.value || '') === '') {
                selectedText = '-';
            }
            sumEl.innerText = selectedText;
        }

        function calcAniloxInkConsumed(unit) {
            var loadedInp = document.getElementById('ink_total_loaded_' + unit);
            var leftoverInp = document.getElementById('ink_leftover_' + unit);
            var badge = document.getElementById('ink_consumed_badge_' + unit);
            var loaded = loadedInp ? aniloxParseNumber(loadedInp.value) : 0;
            var leftover = leftoverInp ? aniloxParseNumber(leftoverInp.value) : 0;
            var consumed = Math.max(0, loaded - leftover);
            if (badge) {
                badge.innerText = consumed.toFixed(2) + ' kg';
                badge.style.backgroundColor = consumed > 0 ? '#ECFDF5' : '#EFF6FF';
                badge.style.borderColor = consumed > 0 ? '#A7F3D0' : '#BFDBFE';
                badge.style.color = consumed > 0 ? '#047857' : '#1D4ED8';
            }
        }

        function calcAniloxLoadTotal(unit) {
            var baseInp = document.getElementById('ink_loaded_base_' + unit);
            var totalInp = document.getElementById('ink_total_loaded_' + unit);
            var totalDisplayInp = document.getElementById('ink_total_loaded_display_' + unit);
            var loadNowInp = document.getElementById('ink_load_now_' + unit);
            var mixChk = document.getElementById('ink_is_mix_' + unit);
            var useMix = !!(mixChk && mixChk.checked);
            var baseLoaded = baseInp ? aniloxParseNumber(baseInp.value) : 0;
            var loadNow = 0;

            if (useMix) {
                for (var idx = 0; idx < aniloxMixSlots; idx++) {
                    var qtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
                    loadNow += qtyInp ? aniloxParseNumber(qtyInp.value) : 0;
                }
                if (loadNowInp) {
                    loadNowInp.value = loadNow > 0 ? loadNow.toFixed(2) : '';
                }
            } else {
                loadNow = loadNowInp ? aniloxParseNumber(loadNowInp.value) : 0;
            }

            var totalLoaded = Math.max(0, baseLoaded + loadNow);
            if (totalInp) {
                totalInp.value = totalLoaded > 0 ? totalLoaded.toFixed(2) : '';
            }
            if (totalDisplayInp) {
                totalDisplayInp.value = totalLoaded > 0 ? totalLoaded.toFixed(2) : '';
            }
            calcAniloxInkConsumed(unit);
            return {
                baseLoaded: baseLoaded,
                loadNow: loadNow,
                totalLoaded: totalLoaded
            };
        }

        function calcAniloxMixTotal(unit) {
            var total = 0;
            var usedCount = 0;
            for (var idx = 0; idx < aniloxMixSlots; idx++) {
                var itemSel = document.getElementById('mix_component_item_' + unit + '_' + idx);
                var qtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
                var qty = qtyInp ? aniloxParseNumber(qtyInp.value) : 0;
                var itemId = itemSel ? String(itemSel.value || '') : '';
                if (itemId !== '' || qty > 0) {
                    usedCount++;
                }
                total += qty;
            }

            var totalEl = document.getElementById('mix_total_badge_' + unit);
            var diffEl = document.getElementById('mix_diff_badge_' + unit);
            var countEl = document.getElementById('mix_count_badge_' + unit);
            var totals = calcAniloxLoadTotal(unit);

            if (totalEl) {
                totalEl.innerText = 'A cargar: ' + total.toFixed(2) + ' kg';
            }
            if (countEl) {
                countEl.innerText = usedCount + ' componente(s)';
            }
            if (diffEl) {
                if (totals.totalLoaded > 0) {
                    diffEl.innerText = 'Total: ' + totals.totalLoaded.toFixed(2) + ' kg';
                    diffEl.style.color = '#047857';
                } else {
                    diffEl.innerText = 'Total: 0.00 kg';
                    diffEl.style.color = '#64748B';
                }
            }
            refreshAniloxMixSummary(unit);
        }

        function toggleAniloxMixRow(unit) {
            var chk = document.getElementById('ink_is_mix_' + unit);
            var singleWrap = document.getElementById('anilox_single_ink_wrap_' + unit);
            var inkItemSel = document.getElementById('ink_item_' + unit);
            var loadNowInp = document.getElementById('ink_load_now_' + unit);
            var openBtn = document.getElementById('btn_open_mix_' + unit);
            var summary = document.getElementById('mix_summary_inline_' + unit);
            var useMix = !!(chk && chk.checked);
            if (singleWrap) {
                singleWrap.style.display = useMix ? 'none' : 'block';
            }
            if (inkItemSel) {
                inkItemSel.disabled = useMix;
                if (useMix) {
                    inkItemSel.value = '';
                }
            }
            if (loadNowInp) {
                loadNowInp.readOnly = useMix;
                loadNowInp.style.backgroundColor = useMix ? '#EFF6FF' : '#FFFFFF';
            }
            if (openBtn) {
                openBtn.style.display = useMix ? 'inline-block' : 'none';
            }
            if (summary) {
                summary.style.display = useMix ? 'inline-flex' : 'none';
            }
            if (!useMix) {
                closeAniloxMixModal(unit);
            }
            normalizeAniloxMixSlots(unit);
            calcAniloxMixTotal(unit);
        }

        function openAniloxMixModal(unit) {
            var chk = document.getElementById('ink_is_mix_' + unit);
            if (chk && !chk.checked) {
                chk.checked = true;
            }
            toggleAniloxMixRow(unit);
            var modal = document.getElementById('anilox_mix_modal_' + unit);
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeAniloxMixModal(unit) {
            var modal = document.getElementById('anilox_mix_modal_' + unit);
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function getUsedAniloxMixCount(unit) {
            var usedCount = 0;
            for (var idx = 0; idx < aniloxMixSlots; idx++) {
                var itemSel = document.getElementById('mix_component_item_' + unit + '_' + idx);
                var qtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
                var itemId = itemSel ? String(itemSel.value || '') : '';
                var qty = qtyInp ? aniloxParseNumber(qtyInp.value) : 0;
                if (itemId !== '' || qty > 0) {
                    usedCount++;
                }
            }
            return usedCount;
        }

        function setAniloxMixSlotVisibility(unit, idx, visible) {
            var slot = document.getElementById('mix_slot_' + unit + '_' + idx);
            if (!slot) return;
            slot.classList.toggle('is-visible', !!visible);
        }

        function normalizeAniloxMixSlots(unit) {
            var highestUsedCount = 0;
            var currentVisibleCount = 0;
            for (var idx = 0; idx < aniloxMixSlots; idx++) {
                var slot = document.getElementById('mix_slot_' + unit + '_' + idx);
                var itemSel = document.getElementById('mix_component_item_' + unit + '_' + idx);
                var qtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
                var itemId = itemSel ? String(itemSel.value || '') : '';
                var qty = qtyInp ? aniloxParseNumber(qtyInp.value) : 0;
                var isUsed = itemId !== '' || qty > 0;
                if (isUsed) {
                    highestUsedCount = idx + 1;
                }
                if (slot && slot.classList.contains('is-visible')) {
                    currentVisibleCount = idx + 1;
                }
            }

            var visibleCount = Math.max(1, highestUsedCount, currentVisibleCount);
            for (var showIdx = 0; showIdx < aniloxMixSlots; showIdx++) {
                setAniloxMixSlotVisibility(unit, showIdx, showIdx < visibleCount);
            }

            for (var hideIdx = visibleCount; hideIdx < aniloxMixSlots; hideIdx++) {
                var hideItemSel = document.getElementById('mix_component_item_' + unit + '_' + hideIdx);
                var hideQtyInp = document.getElementById('mix_component_qty_' + unit + '_' + hideIdx);
                if (hideIdx > 0 && hideItemSel && hideQtyInp && String(hideItemSel.value || '') === '' && aniloxParseNumber(hideQtyInp.value) <= 0) {
                    setAniloxMixSlotVisibility(unit, hideIdx, false);
                }
            }
            var addBtn = document.getElementById('btn_add_mix_slot_' + unit);
            if (addBtn) {
                addBtn.style.display = visibleCount >= aniloxMixSlots ? 'none' : 'inline-block';
            }
            refreshAniloxMixSummary(unit);
        }

        function addAniloxMixSlot(unit) {
            for (var idx = 0; idx < aniloxMixSlots; idx++) {
                var slot = document.getElementById('mix_slot_' + unit + '_' + idx);
                if (slot && !slot.classList.contains('is-visible')) {
                    setAniloxMixSlotVisibility(unit, idx, true);
                    var itemSel = document.getElementById('mix_component_item_' + unit + '_' + idx);
                    if (itemSel) itemSel.focus();
                    break;
                }
            }
            normalizeAniloxMixSlots(unit);
        }

        function clearAniloxMixSlot(unit, idx) {
            var itemSel = document.getElementById('mix_component_item_' + unit + '_' + idx);
            var qtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
            if (itemSel) itemSel.value = '';
            if (qtyInp) qtyInp.value = '';
            if (idx > 0) {
                setAniloxMixSlotVisibility(unit, idx, false);
            }
            normalizeAniloxMixSlots(unit);
            calcAniloxMixTotal(unit);
        }

        function clearAniloxMixQtys(unit) {
            for (var idx = 0; idx < aniloxMixSlots; idx++) {
                var qtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
                if (qtyInp) {
                    qtyInp.value = '';
                }
            }
            normalizeAniloxMixSlots(unit);
            calcAniloxMixTotal(unit);
        }

        function refreshAniloxMixSummary(unit) {
            var summary = document.getElementById('mix_summary_inline_' + unit);
            if (!summary) return;
            var usedCount = getUsedAniloxMixCount(unit);
            var total = 0;
            for (var idx = 0; idx < aniloxMixSlots; idx++) {
                var qtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
                total += qtyInp ? aniloxParseNumber(qtyInp.value) : 0;
            }
            summary.innerText = usedCount > 0 ? (usedCount + ' comp. / ' + total.toFixed(2) + ' kg') : 'Sin mezcla';
        }

        function collectAniloxRowState(unit) {
            var state = {
                color: '',
                colorHidden: '',
                anilox: '',
                inkItem: '',
                loaded: '',
                loadedBase: '',
                loadNow: '',
                leftover: '',
                isMix: false,
                notes: '',
                componentItems: [],
                componentQtys: []
            };

            var colorInp = document.getElementById('color_' + unit);
            var colorHiddenInp = document.getElementById('color_hidden_' + unit);
            var aniloxSel = document.getElementById('id_anilox_' + unit);
            var inkItemSel = document.getElementById('ink_item_' + unit);
            var loadedInp = document.getElementById('ink_total_loaded_' + unit);
            var loadedBaseInp = document.getElementById('ink_loaded_base_' + unit);
            var loadNowInp = document.getElementById('ink_load_now_' + unit);
            var leftoverInp = document.getElementById('ink_leftover_' + unit);
            var mixChk = document.getElementById('ink_is_mix_' + unit);
            var notesInp = document.getElementById('mix_notes_' + unit);

            state.color = colorInp ? colorInp.value : '';
            state.colorHidden = colorHiddenInp ? colorHiddenInp.value : '';
            state.anilox = aniloxSel ? aniloxSel.value : '';
            state.inkItem = inkItemSel ? inkItemSel.value : '';
            state.loaded = loadedInp ? loadedInp.value : '';
            state.loadedBase = loadedBaseInp ? loadedBaseInp.value : '';
            state.loadNow = loadNowInp ? loadNowInp.value : '';
            state.leftover = leftoverInp ? leftoverInp.value : '';
            state.isMix = mixChk ? !!mixChk.checked : false;
            state.notes = notesInp ? notesInp.value : '';

            for (var idx = 0; idx < aniloxMixSlots; idx++) {
                var componentItemSel = document.getElementById('mix_component_item_' + unit + '_' + idx);
                var componentQtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
                state.componentItems.push(componentItemSel ? componentItemSel.value : '');
                state.componentQtys.push(componentQtyInp ? componentQtyInp.value : '');
            }

            return state;
        }

        function applyAniloxRowState(unit, state) {
            var colorInp = document.getElementById('color_' + unit);
            var colorHiddenInp = document.getElementById('color_hidden_' + unit);
            var aniloxSel = document.getElementById('id_anilox_' + unit);
            var inkItemSel = document.getElementById('ink_item_' + unit);
            var loadedInp = document.getElementById('ink_total_loaded_' + unit);
            var loadedDisplayInp = document.getElementById('ink_total_loaded_display_' + unit);
            var loadedBaseInp = document.getElementById('ink_loaded_base_' + unit);
            var loadNowInp = document.getElementById('ink_load_now_' + unit);
            var leftoverInp = document.getElementById('ink_leftover_' + unit);
            var mixChk = document.getElementById('ink_is_mix_' + unit);
            var notesInp = document.getElementById('mix_notes_' + unit);

            if (colorInp) colorInp.value = state.color || '';
            if (colorHiddenInp) colorHiddenInp.value = state.colorHidden || state.color || '';
            if (aniloxSel) aniloxSel.value = state.anilox || '';
            if (inkItemSel) inkItemSel.value = state.inkItem || '';
            if (loadedInp) loadedInp.value = state.loaded || '';
            if (loadedDisplayInp) loadedDisplayInp.value = state.loaded || '';
            if (loadedBaseInp) loadedBaseInp.value = state.loadedBase || '';
            if (loadNowInp) loadNowInp.value = state.loadNow || '';
            if (leftoverInp) leftoverInp.value = state.leftover || '';
            if (mixChk) mixChk.checked = !!state.isMix;
            if (notesInp) notesInp.value = state.notes || '';

            for (var idx = 0; idx < aniloxMixSlots; idx++) {
                var componentItemSel = document.getElementById('mix_component_item_' + unit + '_' + idx);
                var componentQtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
                if (componentItemSel) componentItemSel.value = state.componentItems[idx] || '';
                if (componentQtyInp) componentQtyInp.value = state.componentQtys[idx] || '';
            }

            refreshAniloxSummaryLabel(unit);
            calcAniloxInkConsumed(unit);
            toggleAniloxMixRow(unit);
        }

        function GrabaAnilox(unidadCount) {
            var saveBtn = document.getElementById('btn_grabar_anilox');
            var form = document.getElementById('anilox_form');
            var origHtml = saveBtn ? saveBtn.innerHTML : '';
            if (!form) {
                alert('No se encontró el formulario de anilox.');
                return;
            }

            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fa fa-fw fa-spinner fa-spin" style="color:white;"></i> Guardando...';
            }

            var maxUnits = (typeof unidadCount === 'number' && unidadCount > 1) ? unidadCount : <?=count($detalleAnilox)?>;
            for (var x = 1; x <= maxUnits; x++) {
                closeAniloxMixModal(x);
                normalizeAniloxMixSlots(x);
                refreshAniloxSummaryLabel(x);
                calcAniloxInkConsumed(x);
                calcAniloxMixTotal(x);
            }

            var formData = new FormData(form);
            formData.append('save_anilox', '1');
            formData.append('ajax', '1');

            fetch('/production/work-orders/operate', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(data) {
                if (data && data.ok) {
                    for (var x = 1; x <= maxUnits; x++) {
                        var totalInp = document.getElementById('ink_total_loaded_' + x);
                        var baseInp = document.getElementById('ink_loaded_base_' + x);
                        var loadNowInp = document.getElementById('ink_load_now_' + x);
                        if (baseInp && totalInp) {
                            baseInp.value = totalInp.value || '';
                        }
                        if (loadNowInp) {
                            loadNowInp.value = '';
                        }
                        clearAniloxMixQtys(x);
                        calcAniloxLoadTotal(x);
                        calcAniloxMixTotal(x);
                    }
                    showAniloxToast('Conformación de Anilox y tintas guardada exitosamente.', 'success');
                } else {
                    alert(data && data.error ? data.error : 'Error al guardar Anilox y tintas.');
                }
            })
            .catch(function(err) {
                alert('Error de conexión al guardar Anilox: ' + err.message);
            })
            .finally(function() {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = origHtml;
                }
            });
        }

        function MoverFlecha(ir, unidad) {
            if (isAniloxMoving) {
                return;
            }
            var totalUnidades = <?=count($detalleAnilox)?>;
            if (ir === 1 && unidad <= 1) {
                alert("No puede Mover, estas en la primera posición");
                return;
            }
            if (ir === 2 && totalUnidades > 0 && unidad >= totalUnidades) {
                alert("No puede Mover, estas en la última posición");
                return;
            }

            var targetUnidad = (ir === 1) ? (unidad - 1) : (unidad + 1);

            var trCurr = document.getElementById('tr_anilox_' + unidad);
            var trTgt = document.getElementById('tr_anilox_' + targetUnidad);
            var mixRowCurr = document.getElementById('tr_anilox_mix_' + unidad);
            var mixRowTgt = document.getElementById('tr_anilox_mix_' + targetUnidad);

            var prevStateCurr = collectAniloxRowState(unidad);
            var prevStateTgt = collectAniloxRowState(targetUnidad);

            applyAniloxRowState(unidad, prevStateTgt);
            applyAniloxRowState(targetUnidad, prevStateCurr);

            if (trCurr) {
                trCurr.classList.add('anilox-swap-highlight');
                setTimeout(function() { trCurr.classList.remove('anilox-swap-highlight'); }, 800);
            }
            if (trTgt) {
                trTgt.classList.add('anilox-swap-highlight');
                setTimeout(function() { trTgt.classList.remove('anilox-swap-highlight'); }, 800);
            }
            if (mixRowCurr) {
                mixRowCurr.classList.add('anilox-swap-highlight');
                setTimeout(function() { mixRowCurr.classList.remove('anilox-swap-highlight'); }, 800);
            }
            if (mixRowTgt) {
                mixRowTgt.classList.add('anilox-swap-highlight');
                setTimeout(function() { mixRowTgt.classList.remove('anilox-swap-highlight'); }, 800);
            }

            isAniloxMoving = true;
            var allBtns = document.querySelectorAll('.btn-anilox-mover');
            allBtns.forEach(function(b) {
                b.style.opacity = '0.5';
                b.style.cursor = 'not-allowed';
            });

            var url = '/production/work-orders/operate?agid=<?=$agId?>&mover=' + ir + '&unidad=' + unidad + '&ajax=1';
            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(data) {
                if (!data || !data.ok) {
                    applyAniloxRowState(unidad, prevStateCurr);
                    applyAniloxRowState(targetUnidad, prevStateTgt);
                    alert(data && data.error ? data.error : 'Error al mover anilox.');
                } else {
                    showAniloxToast('Unidad ' + unidad + ' intercambiada con Unidad ' + targetUnidad, 'success');
                }
            })
            .catch(function(err) {
                applyAniloxRowState(unidad, prevStateCurr);
                applyAniloxRowState(targetUnidad, prevStateTgt);
                alert('Error de conexión al mover anilox: ' + err.message);
            })
            .finally(function() {
                isAniloxMoving = false;
                allBtns.forEach(function(b) {
                    b.style.opacity = '1';
                    b.style.cursor = 'pointer';
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            var totalUnidades = <?=count($detalleAnilox)?>;
            for (var x = 1; x <= totalUnidades; x++) {
                refreshAniloxSummaryLabel(x);
                calcAniloxInkConsumed(x);
                toggleAniloxMixRow(x);
            }
        });
        </script>
        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_anilox', 'icon_info_anilox')">
                <span><i class="fa fa-fw fa-circle-notch"></i> Conformación de Anilox y Tintas</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_anilox">Minimizar</span>
                    <i id="icon_info_anilox" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_anilox" style="padding:15px">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px">
                    <div style="font-size:12px;color:#334155">
                        Bodega de tintas: <b><?=htmlspecialchars((string)($inkWarehouse['name'] ?? 'Sin definir'))?></b>
                    </div>
                    <div style="font-size:12px;color:#64748B">
                        Registre carga, sobrante y mezcla por anilox.
                    </div>
                </div>
                <form id="anilox_form" method="post" action="/production/work-orders/operate" onsubmit="return false;">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="anilox_unit_count" value="<?=count($detalleAnilox)?>">
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <colgroup>
                        <col width="55">
                        <col width="20%">
                        <col width="19%">
                        <col width="20%">
                        <col width="90">
                        <col width="90">
                        <col width="95">
                        <col width="105">
                        <col width="95">
                        <col width="92">
                    </colgroup>
                    <tr>
                        <td class="tdheader" align="center">Unid.</td>
                        <td class="tdheader" align="center">Color</td>
                        <td class="tdheader" align="center">Anilox</td>
                        <td class="tdheader" align="center">Tinta</td>
                        <td class="tdheader" align="center">Total kg</td>
                        <td class="tdheader" align="center">Cargar kg</td>
                        <td class="tdheader" align="center">Sobrante kg</td>
                        <td class="tdheader" align="center">Consumido</td>
                        <td class="tdheader" align="center">Mezcla</td>
                        <td class="tdheader" align="center">Opciones</td>
                    </tr>
                    <?php
                    $xAni = 1;
                    foreach ($detalleAnilox as $ani):
                        $unidadAnilox = (int)($ani['paow_unidad'] ?? 0);
                        $savedInkConfig = $aniloxInkConfigByUnit[$unidadAnilox] ?? [];
                        $savedInkItemId = (int)($savedInkConfig['ink_item_id'] ?? 0);
                        $savedLoadedKg = (float)($savedInkConfig['loaded_kg'] ?? 0);
                        $savedLeftoverKg = (float)($savedInkConfig['leftover_kg'] ?? 0);
                        $savedConsumedKg = (float)($savedInkConfig['consumed_kg'] ?? max(0, $savedLoadedKg - $savedLeftoverKg));
                        $savedIsMixed = (int)($savedInkConfig['is_mixed'] ?? 0) === 1;
                        $savedMixNotes = trim((string)($savedInkConfig['mixture_notes'] ?? ''));
                        $savedComponents = array_values($savedInkConfig['components'] ?? []);
                    ?>
                    <tr id="tr_anilox_<?=$unidadAnilox?>" class="anilox-row-transition">
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD;text-align:center"><b><?=$unidadAnilox?></b></td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD">
                            <input type="text" class="inptxt" id="color_<?=$unidadAnilox?>" style="width:100%;background:#F8FAFC" value="<?=htmlspecialchars((string)($ani['paow_color'] ?? ''))?>" readonly>
                            <input type="hidden" id="color_hidden_<?=$unidadAnilox?>" name="color<?=$unidadAnilox?>" value="<?=htmlspecialchars((string)($ani['paow_color'] ?? ''))?>">
                        </td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD">
                            <select name="anilox<?=$unidadAnilox?>" id="id_anilox_<?=$unidadAnilox?>" class="inptxt" style="width:100%;background-color:#FFFFFF">
                                <option value="">&lt; Seleccione Anilox &gt;</option>
                                <?php foreach ($itemAnilox as $ia): ?>
                                    <option value="<?=$ia['id']?>" <?=(int)$ia['id'] === (int)($ani['paow_anilox'] ?? 0) ? 'selected' : ''?>>
                                        <?=htmlspecialchars((string)($ia['item_number_prod'] . '-' . $ia['item_title']))?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD">
                            <div id="anilox_single_ink_wrap_<?=$unidadAnilox?>">
                                <select name="ink_item_<?=$unidadAnilox?>" id="ink_item_<?=$unidadAnilox?>" class="inptxt" style="width:100%;background-color:#FFFFFF">
                                    <option value="">&lt; Seleccione Tinta &gt;</option>
                                    <?php foreach ($inkItems as $inkItem): ?>
                                        <?php
                                        $inkItemId = (int)($inkItem['id'] ?? 0);
                                        $inkItemCode = trim((string)($inkItem['code'] ?? ''));
                                        $inkItemName = trim((string)($inkItem['name'] ?? ''));
                                        ?>
                                        <option value="<?=$inkItemId?>" <?=$inkItemId === $savedInkItemId ? 'selected' : ''?>>
                                            <?=htmlspecialchars(trim($inkItemCode . ' - ' . $inkItemName, ' -'))?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD;text-align:center">
                            <input type="hidden" name="ink_loaded_base_<?=$unidadAnilox?>" id="ink_loaded_base_<?=$unidadAnilox?>" value="<?=$savedLoadedKg > 0 ? number_format($savedLoadedKg, 2, '.', '') : ''?>">
                            <input type="hidden" name="ink_total_loaded_<?=$unidadAnilox?>" id="ink_total_loaded_<?=$unidadAnilox?>" value="<?=$savedLoadedKg > 0 ? number_format($savedLoadedKg, 2, '.', '') : ''?>">
                            <input type="number" step="0.01" min="0" id="ink_total_loaded_display_<?=$unidadAnilox?>" class="inptxt" style="width:82px;text-align:center;background:#F8FAFC" value="<?=$savedLoadedKg > 0 ? number_format($savedLoadedKg, 2, '.', '') : ''?>" placeholder="0.00" readonly>
                        </td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD;text-align:center">
                            <input type="number" step="0.01" min="0" name="ink_load_now_<?=$unidadAnilox?>" id="ink_load_now_<?=$unidadAnilox?>" class="inptxt" style="width:82px;text-align:center" value="" placeholder="0.00" oninput="calcAniloxLoadTotal(<?=$unidadAnilox?>);calcAniloxMixTotal(<?=$unidadAnilox?>)">
                        </td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD;text-align:center">
                            <input type="number" step="0.01" min="0" name="ink_leftover_<?=$unidadAnilox?>" id="ink_leftover_<?=$unidadAnilox?>" class="inptxt" style="width:82px;text-align:center" value="<?=$savedLeftoverKg > 0 ? number_format($savedLeftoverKg, 2, '.', '') : ''?>" placeholder="0.00" oninput="calcAniloxInkConsumed(<?=$unidadAnilox?>)">
                        </td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD;text-align:center">
                            <span id="ink_consumed_badge_<?=$unidadAnilox?>" class="anilox-kg-badge"><?=number_format($savedConsumedKg, 2, '.', '')?> kg</span>
                        </td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD;text-align:center">
                            <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:bold;color:#1D4ED8;cursor:pointer">
                                <input type="checkbox" name="ink_is_mix_<?=$unidadAnilox?>" id="ink_is_mix_<?=$unidadAnilox?>" value="1" <?=$savedIsMixed ? 'checked' : ''?> onchange="toggleAniloxMixRow(<?=$unidadAnilox?>)">
                                Mezcla
                            </label>
                            <button type="button" id="btn_open_mix_<?=$unidadAnilox?>" class="btnblue" style="display:<?=$savedIsMixed ? 'inline-block' : 'none'?>;padding:4px 8px;font-size:11px;cursor:pointer;margin-left:6px" onclick="openAniloxMixModal(<?=$unidadAnilox?>)">
                                <i class="fa fa-flask"></i> Abrir
                            </button>
                            <div id="mix_summary_inline_<?=$unidadAnilox?>" style="display:<?=$savedIsMixed ? 'inline-flex' : 'none'?>;margin-top:6px;font-size:10.5px;font-weight:bold;color:#475569;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:999px;padding:3px 8px">
                                Sin mezcla
                            </div>
                        </td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD;text-align:center">
                            <button type="button" class="btngrey btn-anilox-mover" style="padding:4px 8px;font-size:12px;cursor:pointer" title="Mover arriba" onclick="MoverFlecha(1, <?=$unidadAnilox?>)">
                                <i class="fa fa-fw fa-arrow-up"></i>
                            </button>
                            <button type="button" class="btngrey btn-anilox-mover" style="padding:4px 8px;font-size:12px;cursor:pointer;margin-left:4px" title="Mover abajo" onclick="MoverFlecha(2, <?=$unidadAnilox?>)">
                                <i class="fa fa-fw fa-arrow-down"></i>
                            </button>
                        </td>
                    </tr>
                    <tr id="tr_anilox_mix_<?=$unidadAnilox?>" style="height:0">
                        <td colspan="10" class="tdnrm" style="padding:0;border:none;height:0;line-height:0">
                            <div id="anilox_mix_modal_<?=$unidadAnilox?>" class="anilox-mix-modal" onclick="if(event.target===this){closeAniloxMixModal(<?=$unidadAnilox?>)}">
                                <div class="anilox-mix-modal-card">
                                    <div style="padding:16px 18px;border-bottom:1px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                                        <div>
                                            <div style="font-size:17px;font-weight:800;color:#0F172A">
                                                Mezcla Unidad <?=$unidadAnilox?> · <?=htmlspecialchars((string)($ani['paow_color'] ?? 'Sin color'))?>
                                            </div>
                                            <div style="font-size:12px;color:#64748B;margin-top:3px">
                                                La suma de componentes se carga automáticamente en <b>Carga kg</b>.
                                            </div>
                                        </div>
                                        <button type="button" class="btngrey" style="padding:6px 12px;font-size:12px;cursor:pointer" onclick="closeAniloxMixModal(<?=$unidadAnilox?>)">
                                            <i class="fa fa-times"></i> Cerrar
                                        </button>
                                    </div>
                                    <div style="padding:16px 18px">
                                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px">
                                            <div style="font-size:12px;color:#475569">
                                                Componentes tomados desde la misma bodega de tintas de esta OT.
                                            </div>
                                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                                <span id="mix_count_badge_<?=$unidadAnilox?>" class="anilox-kg-badge" style="background:#F8FAFC;border-color:#E2E8F0;color:#475569">0 componente(s)</span>
                                                <span id="mix_total_badge_<?=$unidadAnilox?>" class="anilox-kg-badge" style="background:#EEF2FF;border-color:#C7D2FE;color:#3730A3">Mezcla: 0.00 kg</span>
                                                <span id="mix_diff_badge_<?=$unidadAnilox?>" class="anilox-kg-badge" style="background:#FFF7ED;border-color:#FED7AA;color:#B45309">Dif.: 0.00 kg</span>
                                            </div>
                                        </div>
                                        <div style="display:flex;flex-direction:column;gap:0">
                                            <?php for ($mixIdx = 0; $mixIdx < 6; $mixIdx++): ?>
                                                <?php $savedComponent = $savedComponents[$mixIdx] ?? []; ?>
                                                <div id="mix_slot_<?=$unidadAnilox?>_<?=$mixIdx?>" class="anilox-mix-slot<?=((int)($savedComponent['item_id'] ?? 0) > 0 || (float)($savedComponent['qty_kg'] ?? 0) > 0) ? ' is-visible' : ''?>">
                                                    <select name="mix_component_item_<?=$unidadAnilox?>[]" id="mix_component_item_<?=$unidadAnilox?>_<?=$mixIdx?>" class="inptxt" style="width:100%" onchange="normalizeAniloxMixSlots(<?=$unidadAnilox?>);calcAniloxMixTotal(<?=$unidadAnilox?>)">
                                                        <option value="">&lt; Componente <?=($mixIdx + 1)?> &gt;</option>
                                                        <?php foreach ($inkItems as $inkItem): ?>
                                                            <?php
                                                            $inkItemId = (int)($inkItem['id'] ?? 0);
                                                            $inkItemCode = trim((string)($inkItem['code'] ?? ''));
                                                            $inkItemName = trim((string)($inkItem['name'] ?? ''));
                                                            ?>
                                                            <option value="<?=$inkItemId?>" <?=$inkItemId === (int)($savedComponent['item_id'] ?? 0) ? 'selected' : ''?>>
                                                                <?=htmlspecialchars(trim($inkItemCode . ' - ' . $inkItemName, ' -'))?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="number" step="0.01" min="0" name="mix_component_qty_<?=$unidadAnilox?>[]" id="mix_component_qty_<?=$unidadAnilox?>_<?=$mixIdx?>" class="inptxt" style="width:100%;text-align:center" value="<?=!empty($savedComponent['qty_kg']) ? number_format((float)$savedComponent['qty_kg'], 2, '.', '') : ''?>" placeholder="kg" oninput="normalizeAniloxMixSlots(<?=$unidadAnilox?>);calcAniloxMixTotal(<?=$unidadAnilox?>)">
                                                    <button type="button" class="btngrey" style="padding:6px 0;font-size:12px;cursor:pointer" onclick="clearAniloxMixSlot(<?=$unidadAnilox?>, <?=$mixIdx?>)">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                        <div style="margin-top:10px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
                                            <button type="button" id="btn_add_mix_slot_<?=$unidadAnilox?>" class="btnblue" style="padding:6px 12px;font-size:12px;cursor:pointer" onclick="addAniloxMixSlot(<?=$unidadAnilox?>)">
                                                <i class="fa fa-plus"></i> Agregar componente
                                            </button>
                                            <div style="font-size:11.5px;color:#64748B">
                                                Máximo 6 componentes
                                            </div>
                                        </div>
                                        <div style="margin-top:10px">
                                            <input type="text" name="mix_notes_<?=$unidadAnilox?>" id="mix_notes_<?=$unidadAnilox?>" class="inptxt" style="width:100%" value="<?=htmlspecialchars($savedMixNotes)?>" placeholder="Observación de mezcla">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php
                    $xAni++;
                    endforeach;
                    ?>
                    <tr>
                        <td colspan="9" style="padding-top:10px">
                            <button type="button" id="btn_grabar_anilox" class="btngreen" style="font-size:13px;padding:8px 22px;cursor:pointer" onclick="GrabaAnilox(<?=count($detalleAnilox)?>)">
                                <i class="fa fa-fw fa-save" style="color:white;"></i> Guardar Anilox y Tintas
                            </button>
                        </td>
                    </tr>
                    </table>
                </form>
            </div>
        </div>


    <?php elseif ($isSeriPulpo): ?>
        <!-- ============================================================== -->
        <!-- RAMA B: SERIGRAFÍA PULPO (editxid.php líneas 3085 - 3395)      -->
        <!-- ============================================================== -->
        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_cliente', 'icon_info_cliente')">
                <span><i class="fa fa-fw fa-user"></i> Información Cliente</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_cliente">Minimizar</span>
                    <i id="icon_info_cliente" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_cliente">
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="250">
                    <col width="30%">
                    <col width="160">
                    <col>
                </colgroup>
                <tr>
                    <td class="tdleft">N° OT</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['prd_number'])?></td>
                    <td class="tdleft">Ancho</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_width']?></td>
                </tr>
                <tr>
                    <td class="tdleft">Cliente</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                    <td class="tdleft">Alto</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_height']?></td>
                </tr>
                <tr>
                    <td class="tdleft">N° CC</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['req_number'])?></td>
                    <td class="tdleft">Fuelle</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_fuelle']?></td>
                </tr>
                <tr>
                    <td class="tdleft">Producto</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_number_prod'])?> | <?=htmlspecialchars((string)$agenda['item_title'])?></td>
                    <td class="tdleft">Cantidad de Bolsas</td>
                    <td class="tdnrm"><b><?=$fmt($agenda['ag_amount'])?></b></td>
                </tr>
                <tr>
                    <td class="tdleft">Materialidad</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">&nbsp;</td>
                </tr>
                <tr>
                    <td colspan="4" style="background:#EDF0F6;padding:4px"></td>
                </tr>
                <tr>
                    <td class="tdleft">Cant. Colores Frente</td>
                    <td class="tdnrm"><b><?=$fmt($amtColorsFrente)?></b></td>
                    <td class="tdleft">Cant. Colores Dorso</td>
                    <td class="tdnrm"><b><?=$fmt($amtColorsDorso)?></b></td>
                </tr>
                <?php for ($xx = 1; $xx <= 10; $xx++): ?>
                    <?php 
                        $colDesc = trim((string)($agenda["fab_print_colordesc_{$xx}"] ?? ''));
                        if ($colDesc === '') continue;
                        $hasFront = !empty($agenda["fab_print_colors_front_{$xx}"]) || ($amtColorsDorso == 0 && empty($agenda["fab_print_colors_back_{$xx}"]));
                        $hasBack = !empty($agenda["fab_print_colors_back_{$xx}"]);
                        if (!$hasFront && !$hasBack) continue;
                    ?>
                    <tr>
                        <td class="tdleft">Color N° <?=$xx?> (Frente)</td>
                        <td class="tdnrm"><?=$hasFront ? htmlspecialchars($colDesc) : '<span style="color:#94a3b8">-</span>'?></td>
                        <td class="tdleft">Color N° <?=$xx?> (Dorso)</td>
                        <td class="tdnrm"><?=$hasBack ? htmlspecialchars($colDesc) : '<span style="color:#94a3b8">-</span>'?></td>
                    </tr>
                <?php endfor; ?>
                <tr>
                    <td colspan="4" style="background:#EDF0F6;padding:4px"></td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Fecha de Entrega</td>
                    <td class="tdnrm" colspan="2"><b><?=htmlspecialchars((string)($agenda['req_despacho_desc'] !== '' ? $agenda['req_despacho_desc'] : $agenda['ag_date']))?></b></td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Fecha de solicitud de película</td>
                    <td class="tdnrm" colspan="2">
                        <?=(int)($agenda['req_cliche_peli_solic_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_solic_dat']) : '<b class="msg_save_err">N/A</b>'?>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Fecha de recepción de película</td>
                    <td class="tdnrm" colspan="2">
                        <?=(int)($agenda['req_cliche_peli_recep_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_recep_dat']) : '<b class="msg_save_err">N/A</b>'?>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Imagen Diseño</td>
                    <td class="tdnrm" colspan="2">
                        <?php if (!empty($agenda['fab_design_imagehash']) && $agenda['fab_design_imagehash'] !== 'dummy'): ?>
                            <?php $dUrl = '/production/design-image?folder=order&hash=' . rawurlencode((string)$agenda['fab_design_imagehash']); ?>
                            <div style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap">
                                <a href="<?=$dUrl?>" target="_blank" title="Clic para ampliar">
                                    <img src="<?=$dUrl?>" alt="Foto Diseño" style="max-height:75px;max-width:120px;object-fit:contain;border:1px solid #cbd5e1;border-radius:4px;background:#fff;vertical-align:middle;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
                                </a>
                                <a href="<?=$dUrl?>" target="_blank" class="btngrey" style="padding:6px 12px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px">
                                    <i class="fa fa-fw fa-image" style="color:white;"></i> Ver Foto Completa
                                </a>
                            </div>
                        <?php else: ?>
                            <span style="color:#999">Sin imagen</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">% de merma</td>
                    <td class="tdnrm" colspan="2"><?=$fmt($agenda['req_operador_mermaperc'], 2)?> %</td>
                </tr>
                <tr>
                    <td class="tdleft"><?=$isFlexo ? 'Metros producidos / en curso' : 'Bolsas producidas / en curso'?></td>
                    <td class="tdnrm" colspan="3"><?=$fmt($stats['total_produced_this'])?> de <?=$fmt($stats['target_amount'] ?? $agenda['ag_amount'])?> (<?=$fmt($stats['percent_completed_this'], 2)?> %)<?=$isFlexo ? ' mtrs' : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft"><?=$isFlexo ? 'Metros producidos / total' : 'Bolsas producidas / total'?></td>
                    <td class="tdnrm" colspan="3"><?=$fmt($stats['total_produced_all'])?> de <?=$fmt($stats['order_amount'] ?? $agenda['item_amount'])?> (<?=$fmt($stats['percent_completed_all'], 2)?> %)<?=$isFlexo ? ' mtrs' : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft">Saldo total</td>
                    <td class="tdnrm" colspan="3"><b><?=$fmt($stats['saldo_total'])?> <?=$isFlexo ? 'mtrs' : 'unidades'?></b></td>
                </tr>
                <?php if ($isSeriPulpo): ?>
                    <tr>
                        <td class="tdleft">Pasadas Frente acumuladas</td>
                        <td class="tdnrm"><b><?=$fmt($stats['total_pulpo_frente_agenda'] ?? 0)?></b> pasadas</td>
                        <td class="tdleft">Pasadas Dorso acumuladas</td>
                        <td class="tdnrm"><b><?=$fmt($stats['total_pulpo_dorso_agenda'] ?? 0)?></b> pasadas</td>
                    </tr>
                    <tr>
                        <td class="tdleft">Bolsas en proceso (semi-terminadas)</td>
                        <td class="tdnrm" colspan="3">
                            <?php
                            $sF = (float)($stats['surplus_frente'] ?? 0);
                            $sD = (float)($stats['surplus_dorso'] ?? 0);
                            ?>
                            <?php if ($sF > 0): ?>
                                <span style="background:#FEF3C7;color:#92400E;padding:4px 10px;border-radius:4px;font-weight:bold;display:inline-flex;align-items:center;gap:6px">
                                    <i class="fa fa-exclamation-circle" style="color:#D97706"></i>
                                    <?=$fmt($sF)?> bolsas con FRENTE impreso (pendientes de dorso)
                                </span>
                            <?php elseif ($sD > 0): ?>
                                <span style="background:#FEF3C7;color:#92400E;padding:4px 10px;border-radius:4px;font-weight:bold;display:inline-flex;align-items:center;gap:6px">
                                    <i class="fa fa-exclamation-circle" style="color:#D97706"></i>
                                    <?=$fmt($sD)?> bolsas con DORSO impreso (pendientes de frente)
                                </span>
                            <?php else: ?>
                                <span style="color:#059669;font-weight:bold">
                                    <i class="fa fa-check-circle"></i> 0 bolsas pendientes (Frente y Dorso al día)
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td class="tdleft">Contador total estimado</td>
                    <td class="tdnrm" colspan="3">
                        <b><?=$fmt(round((float)$agenda['ag_amount'] / max(1, (int)($agenda['req_solic_devprints_cc'] ?? 1))))?></b>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft">Imágenes impresas en bastidor</td>
                    <td class="tdnrm" colspan="3">
                        <form method="get" action="/production/work-orders/operate" style="margin:0;display:inline-flex;align-items:center;gap:10px">
                            <input type="hidden" name="agid" value="<?=$selectedAgId?>">
                            <input type="hidden" name="mode" value="setspecparams">
                            <input type="hidden" name="req_id" value="<?=(int)($agenda['ag_reqid'] ?? $agenda['req_id'] ?? 0)?>">
                            <select class="inptxt" name="bastidoramt" style="width:120px">
                                <?php for ($zzz = 1; $zzz <= 5; $zzz++): ?>
                                    <option value="<?=$zzz?>" <?=((int)($agenda['req_operador_bastidoramt'] ?? 1) === $zzz) ? 'selected' : ''?>><?=$zzz?></option>
                                <?php endfor; ?>
                            </select>
                            <input type="submit" class="btngreen" value="Guardar" style="padding:5px 12px;border:none">
                        </form>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft">Tipo</td>
                    <td class="tdnrm" colspan="3"><?=htmlspecialchars((string)($machineInit['type_ant_title'] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Máquina</td>
                    <td class="tdnrm" colspan="3"><?=htmlspecialchars((string)($machineInit['equipo_name'] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Comentarios Apertura</td>
                    <td class="tdnrm" colspan="3"><?=htmlspecialchars($aperturaComments !== '' ? $aperturaComments : 'Sin comentarios')?></td>
                </tr>
                </table>
            </div>
        </div>

        <!-- SELECTOR: LADO A IMPRIMIR (Frente / Dorso) -->
        <div style="background:#FFFFFF;border:1.5px solid #115452;border-radius:5px;padding:12px 18px;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;box-shadow:0 1px 3px rgba(0,0,0,0.06)">
            <div style="font-size:14px;font-weight:bold;color:#115452;display:flex;align-items:center;gap:10px">
                <i class="fa fa-print" style="font-size:18px;color:#059669"></i> Lado a imprimir seleccionado:
                <span style="background:#E8F0EF;color:#115452;padding:4px 14px;border-radius:4px;font-size:13px;font-weight:bold;letter-spacing:0.5px">
                    <?=$amtColorsDorso > 0 ? strtoupper($colorMode) : 'FRENTE (SOLO FRENTE)'?>
                </span>
            </div>
            <?php if ($amtColorsDorso > 0): ?>
            <div style="display:flex;align-items:center;gap:8px">
                <span style="font-size:12.5px;color:#64748B;font-weight:bold">Seleccionar lado:</span>
                <a href="/production/work-orders/operate?agid=<?=$agId?>&colormode=Frente" class="<?=$colorMode === 'Frente' ? 'btngreen' : 'btngrey'?>" style="padding:7px 18px;text-decoration:none;font-weight:bold;font-size:13px;display:inline-flex;align-items:center;gap:6px">
                    <i class="fa fa-arrow-right"></i> Frente
                </a>
                <a href="/production/work-orders/operate?agid=<?=$agId?>&colormode=Dorso" class="<?=$colorMode === 'Dorso' ? 'btngreen' : 'btngrey'?>" style="padding:7px 18px;text-decoration:none;font-weight:bold;font-size:13px;display:inline-flex;align-items:center;gap:6px">
                    <i class="fa fa-arrow-left"></i> Dorso
                </a>
            </div>
            <?php else: ?>
            <div style="font-size:12.5px;color:#64748B">
                <i class="fa fa-info-circle" style="color:#0284C7"></i> <i>Esta OT no tiene impresión en dorso (0 colores dorso).</i>
            </div>
            <?php endif; ?>
        </div>

        <!-- 2. INFORMACIÓN DE FABRICACIÓN PULPO (form.v2pulpo.php) -->
        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_fab_pulpo', 'icon_info_fab_pulpo')">
                <span><i class="fa fa-fw fa-industry"></i> Información de Fabricación (Pulpo &mdash; Lado <?=htmlspecialchars($colorMode)?>)</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_fab_pulpo">Minimizar</span>
                    <i id="icon_info_fab_pulpo" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_fab_pulpo" style="padding:15px">
                <?php if (isset($_GET['saved_pulpo'])): ?>
                    <div style="background:#ECFDF5;border:1px solid #6EE7B7;color:#065F46;padding:10px 14px;border-radius:4px;margin-bottom:12px;font-size:13px">
                        <i class="fa fa-check-circle"></i> Información de Fabricación guardada exitosamente para lado <?=htmlspecialchars($colorMode)?>.
                    </div>
                <?php endif; ?>
                <div style="margin-bottom:12px;background:#F8FAFC;border:1px solid #CBD5E1;border-radius:4px;padding:10px 12px">
                    <div class="tdleft" style="margin-bottom:4px;<?=$internalFabricationNoteLabelStyle?>">Observación interna</div>
                    <div class="tdnrm" style="<?=$internalFabricationNoteValueStyle?>"><?=$internalFabricationNoteHtml?></div>
                </div>
                <form method="post" action="/production/work-orders/events/pulpo-fabricacion">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="colormode" value="<?=htmlspecialchars($colorMode)?>">

                    <!-- Parámetros de Horno y Máquina -->
                    <table border="0" width="100%" cellpadding="6" cellspacing="0" style="margin-bottom:15px;background:#FAFAFA;border:1px solid #E5E7EB;border-radius:4px">
                    <colgroup><col width="22%"><col width="28%"><col width="22%"><col width="28%"></colgroup>
                    <tr>
                        <td class="tdleft">Temperatura Horno (°C)</td>
                        <td class="tdnrm">
                            <input type="number" step="1" name="temp_horno" class="inptxt" style="width:140px" value="<?=htmlspecialchars((string)($pulpoAgendaData['pulpo_temp_horno_' . strtolower($colorMode)] ?? ''))?>" placeholder="0"> °C
                        </td>
                        <td class="tdleft">Velocidad Máquina</td>
                        <td class="tdnrm">
                            <input type="number" step="0.1" name="maq_speed" class="inptxt" style="width:140px" value="<?=htmlspecialchars((string)($pulpoAgendaData['pulpo_maq_speed_' . strtolower($colorMode)] ?? ''))?>" placeholder="0.0">
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Velocidad Horno</td>
                        <td class="tdnrm">
                            <input type="number" step="0.1" name="horno_speed" class="inptxt" style="width:140px" value="<?=htmlspecialchars((string)($pulpoAgendaData['pulpo_horno_speed_' . strtolower($colorMode)] ?? ''))?>" placeholder="0.0">
                        </td>
                        <td class="tdleft">Medidas Camillas</td>
                        <td class="tdnrm">
                            <input type="text" name="medidas_camillas" class="inptxt" style="width:200px" value="<?=htmlspecialchars((string)($pulpoAgendaData['pulpo_medidas_camillas_' . strtolower($colorMode)] ?? ''))?>" placeholder="Ej: 45 x 55 cm">
                        </td>
                    </tr>
                    </table>

                    <!-- Tabla de 10 Unidades de Bastidores y Secadores -->
                    <div id="pulpo-config-10" style="font-weight:bold;color:#115452;font-size:13.5px;margin-bottom:8px">
                        <i class="fa fa-th"></i> Configuración de 10 Unidades de Bastidores / Secadores
                    </div>
                    <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border:1px solid #DDDDDD">
                    <thead>
                        <tr>
                            <td class="tdheader" align="center" width="45">Unid.</td>
                            <td class="tdheader" width="220">Color Asignado / Acción</td>
                            <td class="tdheader" align="center" width="110">N° Bastidor</td>
                            <td class="tdheader" width="150">Malla / Mesh</td>
                            <td class="tdheader" align="center" width="120">Temp. Secador (°C)</td>
                            <td class="tdheader" align="center" width="100">Tinta Utilizada</td>
                            <td class="tdheader" align="center" width="95">Carga Tinta</td>
                            <td class="tdheader" align="center" width="95">Sobrante Tinta</td>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sideColors = [];
                    for ($cIdx = 1; $cIdx <= 10; $cIdx++) {
                        $fCheck = (int)($agenda["fab_print_colors_front_{$cIdx}"] ?? 0);
                        $bCheck = (int)($agenda["fab_print_colors_back_{$cIdx}"] ?? 0);
                        $cDesc = trim((string)($agenda["fab_print_colordesc_{$cIdx}"] ?? ''));
                        if ($cDesc !== '') {
                            if ($colorMode === 'Frente' && ($fCheck > 0 || ($bCheck === 0 && $amtColorsDorso === 0))) {
                                $sideColors[$cIdx] = "Color #{$cIdx}: " . $cDesc;
                            } elseif ($colorMode === 'Dorso' && $bCheck > 0) {
                                $sideColors[$cIdx] = "Color #{$cIdx}: " . $cDesc;
                            }
                        }
                    }

                    for ($px = 0; $px < 10; $px++):
                        $unit = $pulpoUnits[$px] ?? [];
                        $savedColor = trim((string)($unit['color_id'] ?? ''));
                        $savedBastidor = (string)($unit['val_num_bastidor'] ?? '');
                        $savedMesh = (float)($unit['val_mesh'] ?? 0);
                        $savedTempSec = (float)($unit['val_temp_secador'] ?? 0);
                        $savedTotalTinta = (float)($unit['val_total_tinta'] ?? 0);
                    ?>
                    <tr>
                        <td class="tdnrm" align="center" style="font-weight:bold;background:#F8FAFC"><?=$px + 1?></td>
                        <td class="tdnrm">
                            <select name="unit_color_<?=$px?>" class="inptxt" style="width:100%">
                                <option value="">&lt; Unidad Libre &gt;</option>
                                <option value="-1" <?=$savedColor === '-1' ? 'selected' : ''?>>-- Secador Intermedio --</option>
                                <?php foreach ($sideColors as $cid => $clbl): ?>
                                    <option value="<?=$cid?>" <?=$savedColor === (string)$cid ? 'selected' : ''?>><?=htmlspecialchars($clbl)?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="tdnrm" align="center">
                            <input type="text" name="unit_bastidor_<?=$px?>" class="inptxt" style="width:95px;text-align:center" value="<?=htmlspecialchars($savedBastidor)?>" placeholder="N°">
                        </td>
                        <td class="tdnrm">
                            <select name="unit_mesh_<?=$px?>" class="inptxt" style="width:100%">
                                <option value="0">&lt; Seleccione &gt;</option>
                                <?php foreach ($bastidoresList as $b): ?>
                                    <option value="<?=$b['densidad']?>" <?=abs($savedMesh - (float)$b['densidad']) < 0.01 ? 'selected' : ''?>>
                                        <?=htmlspecialchars((string)$b['descripcion'])?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="tdnrm" align="center">
                            <input type="number" step="1" name="unit_temp_sec_<?=$px?>" class="inptxt" style="width:95px;text-align:center" value="<?=$savedTempSec > 0 ? (int)$savedTempSec : ''?>" placeholder="°C">
                        </td>
                        <td class="tdnrm" align="center">
                            <input type="number" step="0.01" name="unit_tinta_<?=$px?>" class="inptxt" style="width:85px;text-align:center" value="<?=$savedTotalTinta > 0 ? $savedTotalTinta : ''?>" placeholder="0.00">
                        </td>
                        <td class="tdnrm" align="center">
                            <input type="number" step="0.01" name="unit_carga_tinta_<?=$px?>" class="inptxt" style="width:80px;text-align:center" placeholder="0.00">
                        </td>
                        <td class="tdnrm" align="center">
                            <input type="number" step="0.01" name="unit_sobrante_tinta_<?=$px?>" class="inptxt" style="width:80px;text-align:center" placeholder="0.00">
                        </td>
                    </tr>
                    <?php endfor; ?>
                    </tbody>
                    </table>

                    <div style="margin-top:12px;text-align:right">
                        <button type="submit" class="btngreen" style="padding:9px 24px;font-size:13.5px;border:none;cursor:pointer">
                            <i class="fa fa-save"></i> Guardar Fabricación Pulpo (Lado <?=htmlspecialchars($colorMode)?>)
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <?php elseif ($isSeriPlana): ?>
        <!-- ============================================================== -->
        <!-- RAMA C: SERIGRAFÍA PLANA (editxid.php líneas 3409 - 3650)      -->
        <!-- ============================================================== -->
        <?php
            $seriImgHash = trim((string)($agenda['fab_design_imagehash'] ?? $agenda['item_img_hash'] ?? ''));
            $seriHasImg = ($seriImgHash !== '' && $seriImgHash !== 'dummy');
            $seriImgUrl = $seriHasImg ? ('/production/design-image?folder=order&hash=' . rawurlencode($seriImgHash)) : '';
            $seriFullDesign = trim((string)($agenda['prd_name'] ?? ($agenda['item_title'] ?? '')));
            $seriMedidasStr = (int)($agenda['fab_med_width'] ?? 0) . ' x ' . (int)($agenda['fab_med_height'] ?? 0);
            if ((int)($agenda['fab_med_fuelle'] ?? 0) > 0) {
                $seriMedidasStr .= ' + ' . (int)$agenda['fab_med_fuelle'];
            }
            $seriMedidasStr .= ' cm';
            $seriMermaPerc = (float)($agenda['req_operador_mermaperc'] ?? 0);
            $seriDevPrints = max(1, (int)($agenda['req_solic_devprints_cc'] ?? 1));
            $seriMlinProg = round((float)$agenda['ag_amount'] * (float)$corte_m2);
            $seriMlinAddi = round($seriMlinProg / 100 * $seriMermaPerc);
            $seriMlinPrint = round($seriMlinProg + $seriMlinAddi);
            $seriMermaUnits = round((float)$agenda['ag_amount'] / 100 * $seriMermaPerc);
            $seriRealUnits = (float)$agenda['ag_amount'] + $seriMermaUnits;
            $seriPasadasPrint = (int)round($seriRealUnits / $seriDevPrints);
            $seriKgsProcess = round(
                $seriMlinPrint
                * (float)($agenda['req_operador_tela_width'] ?? 0)
                / 100
                * (float)($agenda['fab_mat_gramms'] ?? 0)
                / 1000
            );
        ?>
        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_cliente', 'icon_info_cliente')">
                <span><i class="fa fa-fw fa-user"></i> Información Cliente</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_cliente">Minimizar</span>
                    <i id="icon_info_cliente" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_cliente">
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="200">
                    <col width="40%">
                    <col width="185">
                    <col>
                </colgroup>
                <tr>
                    <td class="tdleft">N° OT</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['prd_number'])?></td>
                    <td class="tdleft">Cant. Colores</td>
                    <td class="tdnrm"><b><?=$fmt($amtColorsSeri)?></b></td>
                </tr>
                <tr>
                    <td class="tdleft">Cliente</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                    <td class="tdleft">Color N° 1</td>
                    <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_1'] ?? 0) || (int)($agenda['fab_print_colors_back_1'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? '')) : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft">N° CC</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['req_number'])?></td>
                    <td class="tdleft">Color N° 2</td>
                    <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_2'] ?? 0) || (int)($agenda['fab_print_colors_back_2'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_2'] ?? '')) : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft">Producto</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_number_prod'])?> | <?=htmlspecialchars((string)$agenda['item_title'])?></td>
                    <td class="tdleft">Color N° 3</td>
                    <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_3'] ?? 0) || (int)($agenda['fab_print_colors_back_3'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_3'] ?? '')) : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft">Materialidad</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                    <td class="tdleft">Color N° 4</td>
                    <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_4'] ?? 0) || (int)($agenda['fab_print_colors_back_4'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_4'] ?? '')) : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft">Ancho</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_width']?></td>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">&nbsp;</td>
                </tr>
                <tr>
                    <td class="tdleft">Alto</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_height']?></td>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">&nbsp;</td>
                </tr>
                <tr>
                    <td class="tdleft">Fuelle</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_fuelle']?></td>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">&nbsp;</td>
                </tr>
                <tr>
                    <td class="tdleft">Cantidad de Bolsas</td>
                    <td class="tdnrm"><b><?=$fmt($agenda['ag_amount'])?></b></td>
                    <td class="tdleft">Fecha de Entrega</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)($agenda['req_despacho_desc'] !== '' ? $agenda['req_despacho_desc'] : $agenda['ag_date']))?></b></td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Fecha solicitud película</td>
                    <td class="tdnrm" colspan="2"><?=(int)($agenda['req_cliche_peli_solic_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_solic_dat']) : '<b class="msg_save_err">N/A</b>'?></td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Fecha recepción película</td>
                    <td class="tdnrm" colspan="2"><?=(int)($agenda['req_cliche_peli_recep_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_recep_dat']) : '<b class="msg_save_err">N/A</b>'?></td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Imagen diseño</td>
                    <td class="tdnrm" colspan="2">
                        <?php if ($seriHasImg): ?>
                            <a href="<?=$seriImgUrl?>" target="_blank" onclick="openDesignModal('<?=$seriImgUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($seriFullDesign))?>', '<?=htmlspecialchars($seriMedidasStr)?>'); return false;" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen
                            </a>
                        <?php else: ?>
                            <span style="color:#999">Sin imagen cargada</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php for ($x = 0; $x < 5; $x++): ?>
                    <?php if (!empty($agenda["req_prod_adjfile_{$x}"])):
                        $adjFile = (string)$agenda["req_prod_adjfile_{$x}"];
                        $adjIsImage = (bool)preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $adjFile);
                        $adjUrl = '/docs/prod/' . rawurlencode($adjFile);
                    ?>
                    <tr>
                        <td class="tdleft" colspan="2">Imagen x #<?=($x + 1)?></td>
                        <td class="tdnrm" colspan="2">
                            <?php if ($adjIsImage): ?>
                                <a href="<?=$adjUrl?>" target="_blank" onclick="openDesignModal('<?=$adjUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($seriMedidasStr)?>'); return false;" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                    <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen
                                </a>
                            <?php else: ?>
                                <a href="<?=$adjUrl?>" target="_blank" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                    <i class="fa fa-fw fa-file" style="color:white;"></i> Mostrar Documento
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($agenda["req_prod_adjcomments_{$x}"])): ?>
                                <span style="margin-left:8px;color:#64748B;font-size:11.5px"><?=htmlspecialchars((string)$agenda["req_prod_adjcomments_{$x}"])?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endfor; ?>
                </table>
            </div>
        </div>

        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_fab', 'icon_info_fab')">
                <span><i class="fa fa-fw fa-info-circle"></i> Información de Fabricación</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_fab">Minimizar</span>
                    <i id="icon_info_fab" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_fab">
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="200">
                    <col width="40%">
                    <col width="185">
                    <col>
                </colgroup>
                <tr>
                    <td class="tdleft">Desarrollo</td>
                    <td class="tdnrm"><b><?=$fmt($corte_m2, 4)?> mtrs</b></td>
                    <td class="tdleft">Color N° 1</td>
                    <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_1'] ?? 0) || (int)($agenda['fab_print_colors_back_1'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? '')) : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft">Pie de Imprenta</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_pie_imprenta'] ?? '-'))?></td>
                    <td class="tdleft">Color N° 2</td>
                    <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_2'] ?? 0) || (int)($agenda['fab_print_colors_back_2'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_2'] ?? '')) : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft">N° Código de Barra</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['item_sellprice_barcodenumber'] ?? '-'))?></td>
                    <td class="tdleft">Color N° 3</td>
                    <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_3'] ?? 0) || (int)($agenda['fab_print_colors_back_3'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_3'] ?? '')) : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft">Impresiones en cilindro</td>
                    <td class="tdnrm">&nbsp;</td>
                    <td class="tdleft">Color N° 4</td>
                    <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_4'] ?? 0) || (int)($agenda['fab_print_colors_back_4'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_4'] ?? '')) : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft">Impresiones por desarrollo</td>
                    <td class="tdnrm"><?=(int)($agenda['req_solic_devprints_cc'] ?? 0)?> impresiones</td>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">&nbsp;</td>
                </tr>
                <tr>
                    <td colspan="4"><div style="height:10px"></div></td>
                </tr>
                <tr>
                    <td class="tdleft">Materialidad</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_type'] ?? '-'))?></td>
                    <td class="tdleft">Metros a imprimir</td>
                    <td class="tdnrm"><?=$fmt($seriMlinPrint)?></td>
                </tr>
                <tr>
                    <td class="tdleft">Color Tela</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fabric_color'] ?? '-'))?></td>
                    <td class="tdleft">Contador impresora</td>
                    <td class="tdnrm"><?=$fmt($seriPasadasPrint)?></td>
                </tr>
                <tr>
                    <td class="tdleft">Ancho Tela</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_operador_tela_width'] ?? '-'))?></td>
                    <td class="tdleft">Kg a imprimir</td>
                    <td class="tdnrm"><?=$fmt($seriKgsProcess)?></td>
                </tr>
                <tr>
                    <td class="tdleft">Gramaje</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_mat_gramms'] ?? '-'))?> gr</td>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">&nbsp;</td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Cantidad de imágenes impresas en bastidor</td>
                    <td class="tdnrm" colspan="2">
                        <form method="get" action="/production/work-orders/operate" style="margin:0;display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap">
                            <input type="hidden" name="agid" value="<?=$agId?>">
                            <input type="hidden" name="mode" value="setspecparams">
                            <input type="hidden" name="req_id" value="<?=(int)($agenda['ag_reqid'] ?? $agenda['req_id'] ?? 0)?>">
                            <select class="inptxt" name="bastidoramt" style="width:120px">
                                <option value="">Seleccione</option>
                                <?php for ($zzz = 1; $zzz <= 5; $zzz++): ?>
                                    <option value="<?=$zzz?>" <?=((int)($agenda['req_operador_bastidoramt'] ?? 1) === $zzz) ? 'selected' : ''?>><?=$zzz?></option>
                                <?php endfor; ?>
                            </select>
                            <input type="submit" class="btngreen" value="Guardar" style="padding:5px 12px;border:none">
                        </form>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Imagen diseño</td>
                    <td class="tdnrm" colspan="2">
                        <?php if ($seriHasImg): ?>
                            <a href="<?=$seriImgUrl?>" target="_blank" onclick="openDesignModal('<?=$seriImgUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($seriFullDesign))?>', '<?=htmlspecialchars($seriMedidasStr)?>'); return false;" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen
                            </a>
                        <?php else: ?>
                            <span style="color:#999">Sin imagen cargada</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php for ($x = 0; $x < 5; $x++): ?>
                    <?php if (!empty($agenda["req_prod_adjfile_{$x}"])):
                        $adjFile = (string)$agenda["req_prod_adjfile_{$x}"];
                        $adjIsImage = (bool)preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $adjFile);
                        $adjUrl = '/docs/prod/' . rawurlencode($adjFile);
                    ?>
                    <tr>
                        <td class="tdleft" colspan="2">Imagen x #<?=($x + 1)?></td>
                        <td class="tdnrm" colspan="2">
                            <?php if ($adjIsImage): ?>
                                <a href="<?=$adjUrl?>" target="_blank" onclick="openDesignModal('<?=$adjUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($seriMedidasStr)?>'); return false;" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                    <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen
                                </a>
                            <?php else: ?>
                                <a href="<?=$adjUrl?>" target="_blank" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                    <i class="fa fa-fw fa-file" style="color:white;"></i> Mostrar Documento
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($agenda["req_prod_adjcomments_{$x}"])): ?>
                                <span style="margin-left:8px;color:#64748B;font-size:11.5px"><?=htmlspecialchars((string)$agenda["req_prod_adjcomments_{$x}"])?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endfor; ?>
                <tr>
                    <td class="tdleft" colspan="2" style="<?=$internalFabricationNoteLabelStyle?>">Observación interna</td>
                    <td class="tdnrm" colspan="2" style="<?=$internalFabricationNoteValueStyle?>"><?=$internalFabricationNoteHtml?></td>
                </tr>
                </table>
            </div>
        </div>

    <?php elseif ($isEmbalaje): ?>
        <!-- ============================================================== -->
        <!-- RAMA D1: EMBALAJE (editxid.php líneas 1880 - 2050)             -->
        <!-- ============================================================== -->
        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_cliente', 'icon_info_cliente')">
                <span><i class="fa fa-fw fa-user"></i> Información Cliente</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_cliente">Minimizar</span>
                    <i id="icon_info_cliente" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_cliente">
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="180">
                    <col width="32%">
                    <col width="160">
                    <col>
                </colgroup>
                <tr>
                    <td class="tdleft">N° O.T.</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)$agenda['prd_number'])?></b></td>
                    <td class="tdleft">Cliente</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                </tr>
                <tr>
                    <td class="tdleft">N° C.C.</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['req_number'])?></td>
                    <td class="tdleft">Producto</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_title'])?></td>
                </tr>
                <tr>
                    <td class="tdleft">Medidas</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_width']?> x <?=(int)$agenda['fab_med_height']?> x <?=(int)$agenda['fab_med_fuelle']?> cm</td>
                    <td class="tdleft">Cantidad Bolsas</td>
                    <td class="tdnrm"><b><?=$fmt($agenda['ag_amount'])?></b></td>
                </tr>
                </table>
            </div>
        </div>

        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_fab', 'icon_info_fab')">
                <span><i class="fa fa-fw fa-info-circle"></i> Información de Fabricación</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_fab">Minimizar</span>
                    <i id="icon_info_fab" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_fab">
                <table border="0" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="left" valign="top" width="50%" style="padding-right:10px">
                        <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-right:1px solid #DDDDDD">
                        <colgroup><col width="160"><col></colgroup>
                        <tr><td class="tdleft">N° O.T.</td><td class="tdnrm"><?=htmlspecialchars((string)$agenda['prd_number'])?></td></tr>
                        <tr><td class="tdleft">Cliente</td><td class="tdnrm"><?=htmlspecialchars((string)$agenda['cust_name'])?></td></tr>
                        <tr><td class="tdleft">N° C.C.</td><td class="tdnrm"><?=htmlspecialchars((string)$agenda['req_number'])?></td></tr>
                        <tr><td class="tdleft">Tipo de Material</td><td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td></tr>
                        <tr><td class="tdleft">Tipo de Bolsa</td><td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_title'])?></td></tr>
                        <tr><td class="tdleft">Medidas</td><td class="tdnrm"><?=(int)$agenda['fab_med_width']?>x<?=(int)$agenda['fab_med_height']?>x<?=(int)$agenda['fab_med_fuelle']?></td></tr>
                        <tr><td class="tdleft">Procedencia</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_infoaddprd_procedencia'] ?? '-'))?></td></tr>
                        <tr><td class="tdleft">Tipo Impresión</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_printtype'] ?? '-'))?></td></tr>
                        <tr><td class="tdleft">Colores</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? ''))?></td></tr>
                        <tr><td class="tdleft">Color Manillas</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['manilla_color'] ?? '-'))?></td></tr>
                        <tr><td class="tdleft">Medida Manillas</td><td class="tdnrm"><?=(int)($agenda['fab_manilla_length'] ?? 0)?></td></tr>
                        <tr><td class="tdleft">Reversa</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_infoaddprd_reversa_act'] ?? 'No'))?></td></tr>
                        </table>
                    </td>
                    <td align="left" valign="top" width="50%" style="padding-left:10px">
                        <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD">
                        <colgroup><col width="170"><col></colgroup>
                        <tr>
                            <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="2" align="center">
                                <i class="fa fa-fw fa-cube"></i> Datos Embalaje
                            </td>
                        </tr>
                        <tr><td class="tdleft">Mezcla</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['reqnum_mezcla_1'] ?? 'Sin Mezcla'))?></td></tr>
                        <tr><td class="tdleft">Medida de Caja</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_embalaje_medidas_caja'] ?? '-'))?></td></tr>
                        <tr><td class="tdleft">Logotipo empresa</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_caja_impresa'] ?? '-'))?></td></tr>
                        <tr><td class="tdleft">Cant. Cajas por Pallet</td><td class="tdnrm"><?=$fmt($agenda['req_embalaje_cajas_por_pallet_amt'])?></td></tr>
                        <tr><td class="tdleft">Cant. Cajas a Utilizar</td><td class="tdnrm"><?=$fmt($agenda['req_embalaje_cajas_completas_amt'])?></td></tr>
                        <tr><td class="tdleft">Unidades por caja</td><td class="tdnrm"><b><?=$fmt($agenda['req_embalaje_bolsas_por_caja_amt'])?></b></td></tr>
                        <tr><td class="tdleft">Unidades Caja Final</td><td class="tdnrm"><?=$fmt($agenda['req_embalaje_caja_final'])?></td></tr>
                        <tr><td class="tdleft">Total Pedido</td><td class="tdnrm"><b><?=$fmt($agenda['ag_amount'])?> bolsas</b></td></tr>
                        </table>
                    </td>
                </tr>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- ============================================================== -->
        <!-- RAMA D2: SELLADORA / CONFECCIÓN / REBOBINADORA (2050 - 2600)   -->
        <!-- ============================================================== -->
        <?php if (!$isRebobinadora && !$isSelladora): ?>
        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_cliente', 'icon_info_cliente')">
                <span><i class="fa fa-fw fa-user"></i> Información Cliente</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_cliente">Minimizar</span>
                    <i id="icon_info_cliente" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_cliente">
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="180">
                    <col width="32%">
                    <col width="160">
                    <col>
                </colgroup>
                <tr>
                    <td class="tdleft">N° O.T.</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)$agenda['prd_number'])?></b></td>
                    <td class="tdleft">Cliente</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                </tr>
                <tr>
                    <td class="tdleft">N° C.C.</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['req_number'])?></td>
                    <td class="tdleft">Producto</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_title'])?></td>
                </tr>
                <tr>
                    <td class="tdleft">Medidas</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_width']?> x <?=(int)$agenda['fab_med_height']?> x <?=(int)$agenda['fab_med_fuelle']?> cm</td>
                    <td class="tdleft">Cantidad Bolsas</td>
                    <td class="tdnrm"><b><?=$fmt($agenda['ag_amount'])?></b></td>
                </tr>
                <tr>
                    <td class="tdleft">Materialidad</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                    <td class="tdleft">Fecha Entrega</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)($agenda['req_despacho_desc'] !== '' ? $agenda['req_despacho_desc'] : $agenda['ag_date']))?></b></td>
                </tr>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_fab', 'icon_info_fab')">
                <span><i class="fa fa-fw fa-info-circle"></i> Información de Fabricación</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_fab">Minimizar</span>
                    <i id="icon_info_fab" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_fab">
                <!-- Encabezado exacto de editxid.php: N° OT, Cliente, N° CC -->
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="160">
                    <col>
                </colgroup>
                <tr>
                    <td class="tdleft" style="background-color:#EEEEEE;">N° O.T.</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)$agenda['prd_number'])?></b></td>
                </tr>
                <tr>
                    <td class="tdleft" style="background-color:#EEEEEE;">Cliente</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                </tr>
                <tr>
                    <td class="tdleft" style="background-color:#EEEEEE;">N° C.C.</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['req_number'])?></td>
                </tr>
                </table>

                <!-- Tablas de Configuración e Insumos en 2 columnas (editxid.php 2170-2340) -->
                <table border="0" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="left" valign="top" width="50%" style="padding-right:10px">
                        <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                        <colgroup>
                            <col width="160">
                            <col>
                        </colgroup>
                        <tr>
                            <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="2" align="center">
                                <i class="fa fa-fw fa-wrench"></i> Configuración
                            </td>
                        </tr>
                        <tr>
                            <td class="tdleft" style="background-color:#EEEEEE;">Producto</td>
                            <td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_title'])?></td>
                        </tr>
                        <?php if ($isSelladora): ?>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Corte de Bolsa</td>
                                <td class="tdnrm"><b><?=$fmt($corte_m2, 4)?> mtrs</b></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!$reqGenRefId): ?>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Ancho</td>
                                <td class="tdnrm"><?=(int)$agenda['fab_med_width']?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Alto (Tiro)</td>
                                <td class="tdnrm"><?=htmlspecialchars((string)($transcomInfos['alto_tiro'] ?? (string)$agenda['fab_med_height']))?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Alto (Retiro)</td>
                                <td class="tdnrm"><?=htmlspecialchars((string)($transcomInfos['alto_retiro'] ?? (string)$agenda['fab_med_height']))?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Doblez superior</td>
                                <td class="tdnrm"><?=htmlspecialchars((string)($transcomInfos['doblez_superior'] ?? '3'))?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Fuelle</td>
                                <td class="tdnrm"><?=(int)$agenda['fab_med_fuelle']?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Dado de Manillas</td>
                                <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_infoaddprd_dado_manillas'] ?? ''))?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Cabezal</td>
                                <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_infoaddprd_cabezal_act'] ?? ''))?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Alarma</td>
                                <td class="tdnrm"><?=stripos((string)($agenda['dispositivo'] ?? ''), 'ALARMA') !== false ? 'Si' : 'No'?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Código</td>
                                <td class="tdnrm"><?=htmlspecialchars((string)($agenda['item_sellprice_barcodenumber'] ?? ''))?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Etiqueta adhesiva</td>
                                <td class="tdnrm"><?=stripos((string)($agenda['dispositivo'] ?? ''), 'ETIQUETA') !== false ? 'Si' : 'No'?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Código</td>
                                <td class="tdnrm"><?=!empty($agenda['item_sellprice_barcodenumber']) ? 'Si' : 'No'?></td>
                            </tr>
                        <?php endif; ?>
                        </table>
                    </td>
                    <td align="left" valign="top" width="50%" style="padding-left:10px">
                        <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                        <colgroup>
                            <col width="160">
                            <col>
                        </colgroup>
                        <tr>
                            <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="2" align="center">
                                <i class="fa fa-fw fa-cubes"></i> Insumos
                            </td>
                        </tr>
                        <tr>
                            <td class="tdleft" style="background-color:#D5EDEB" colspan="2" align="center">
                                Características de tela
                            </td>
                        </tr>
                        <tr>
                            <td class="tdleft" style="background-color:#EEEEEE;">Materialidad</td>
                            <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                        </tr>
                        <tr>
                            <td class="tdleft" style="background-color:#EEEEEE;">Color Tela</td>
                            <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fabric_color'] ?? '-'))?></td>
                        </tr>
                        <tr>
                            <td class="tdleft" style="background-color:#EEEEEE;">Ancho Tela</td>
                            <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_operador_tela_width'] ?? '-'))?></td>
                        </tr>
                        <tr>
                            <td class="tdleft" style="background-color:#EEEEEE;">Gramaje</td>
                            <td class="tdnrm"><?=$gramaje?> gr</td>
                        </tr>
                        <?php if (!$reqGenRefId): ?>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Cant. Bobinas</td>
                                <td class="tdnrm"><b><?=$fmt($cantBobinas, 2)?></b></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#D5EDEB" colspan="2" align="center">
                                    Características de manillas
                                </td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Color Manilla</td>
                                <td class="tdnrm"><?=htmlspecialchars((string)($agenda['manilla_color'] ?? '-'))?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Ancho de Manillas</td>
                                <td class="tdnrm">
                                    <?php
                                    $anchoMan = trim((string)($transcomInfos['ancho_manillas'] ?? ''));
                                    if ($anchoMan === '') {
                                        foreach ($subproducts as $sp) {
                                            if (strtoupper((string)($sp['subprod_destino'] ?? '')) === 'MANILLAS') {
                                                $anchoMan = (string)(float)($sp['subprod_ancho_cm'] ?? 0);
                                                break;
                                            }
                                        }
                                    }
                                    if ($anchoMan === '' && stripos((string)($agenda['item_title'] ?? ''), 'BOUTIQUE') !== false) {
                                        $anchoMan = '2.5';
                                    }
                                    echo htmlspecialchars($anchoMan !== '' ? $anchoMan : '-');
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Largo de Manillas</td>
                                <td class="tdnrm"><?=(int)($agenda['fab_manilla_length'] ?? 0)?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Cant. Manillas</td>
                                <td class="tdnrm"><b><?=$fmt($cantManillas, 2)?></b></td>
                            </tr>
                        <?php endif; ?>
                        </table>
                    </td>
                </tr>
                </table>

                <?php if ($isRebobinadora): ?>
                    <!-- TABLA TAREA REBOBINADORA Y DISTRIBUCIONES (editxid.php 2370-2600) -->
                    <?php
                    $reborder = !empty($reboOrder) ? $reboOrder : $agenda;
                    if (!empty($reborder['req_rebo_type'])):
                    ?>
                        <div style="height:10px"></div>
                        <table border="0" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td colspan="2" style="background-color:#1AAAA4;color:#FFFFFF;padding:6px;font-weight:bold;text-align:center">
                                <i class="fa fa-fw fa-wrench"></i> Tarea Rebobinadora
                            </td>
                        </tr>
                        <tr>
                            <td align="left" valign="top" width="50%" style="padding-right:10px">
                                <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                                <colgroup>
                                    <col width="160">
                                    <col>
                                </colgroup>
                                <tr>
                                    <td class="tdleft" style="background-color:#EEEEEE;">Tarea</td>
                                    <td class="tdnrm"><b><?=htmlspecialchars((string)$reborder['req_rebo_type'])?></b></td>
                                </tr>
                                <tr>
                                    <td class="tdleft" style="background-color:#EEEEEE;">Tipo</td>
                                    <td class="tdnrm"><?=htmlspecialchars((string)(!empty($reborder['req_rebo_rolloscc_opttype']) ? $reborder['req_rebo_rolloscc_opttype'] : 'Por rollo'))?></td>
                                </tr>
                                <tr>
                                    <td class="tdleft" style="background-color:#EEEEEE;">Rollos</td>
                                    <td class="tdnrm">
                                        <?php
                                        $opt = (string)($reborder['req_rebo_rolloscc_opt'] ?? '');
                                        if ($opt === '100cm_manillas') {
                                            echo 'Rollos de 100cm para manillas';
                                        } elseif ($opt === '100cm_algunos_metros') {
                                            echo 'Rollos de 100cm (algunos metros) a rollo de 76cm más manillas y restante debe mantener código';
                                        } elseif ($opt === 'solo_rebobinar') {
                                            echo 'Rollos solo rebobinar';
                                        } elseif (!empty($reborder['req_rebo_cortescc']) || count($subproducts) > 0 || !empty($reboValues['rollo'])) {
                                            $telaW = (int)($reborder['req_operador_tela_width'] ?? $agenda['req_operador_tela_width'] ?? 100);
                                            if ($telaW <= 0) $telaW = 100;
                                            $bagW = 0;
                                            foreach ($subproducts as $sp) {
                                                if (strtoupper((string)($sp['subprod_destino'] ?? '')) !== 'MANILLAS') {
                                                    $bagW = (int)$sp['subprod_ancho_cm'];
                                                    break;
                                                }
                                            }
                                            if ($bagW <= 0) {
                                                foreach ($reboValues['rollo'] ?? [] as $rv) {
                                                    $w = (int)($rv['rollo_dims'] ?? 0);
                                                    if ($w > 50) { $bagW = $w; break; }
                                                }
                                            }
                                            if ($bagW > 0) {
                                                echo "Rollos de {$telaW}cm a rollo de {$bagW}cm más manillas y restante debe mantener código";
                                            } else {
                                                echo "Rollos de {$telaW}cm para corte más manillas";
                                            }
                                        } else {
                                            echo '- - -';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                </table>
                            </td>
                            <td align="left" valign="top" width="50%" style="padding-left:10px">
                                <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                                <colgroup>
                                    <col width="160">
                                    <col>
                                </colgroup>
                                <tr>
                                    <td class="tdleft" style="background-color:#EEEEEE;">Estado</td>
                                    <td class="tdnrm"><?=htmlspecialchars((string)($reborder['req_rebo_state'] ?? ''))?></td>
                                </tr>
                                <tr>
                                    <td class="tdleft" style="background-color:#EEEEEE;">Cantidad de cortes</td>
                                    <td class="tdnrm"><?=(float)($reborder['req_rebo_cortescc'] ?? 0) > 0 ? $fmt($reborder['req_rebo_cortescc']) : '- - -'?></td>
                                </tr>
                                <tr>
                                    <td class="tdleft" style="background-color:#EEEEEE;">&nbsp;</td>
                                    <td class="tdnrm">&nbsp;</td>
                                </tr>
                                </table>
                            </td>
                        </tr>
                        </table>

                        <?php if (($reborder['req_rebo_rolloscc_opttype'] ?? '') === 'Por metro'): ?>
                            <!-- MODO METRO: Distribución solicitado y Largos -->
                            <div style="height:6px"></div>
                            <table border="0" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td align="left" valign="top" width="50%" style="padding-right:10px">
                                    <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                                    <colgroup>
                                        <col width="160">
                                        <col>
                                        <col>
                                    </colgroup>
                                    <tr>
                                        <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="3" align="center">
                                            <i class="fa fa-fw fa-info"></i> Distribución solicitado
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Cantidad</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Medida</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Subtotal</td>
                                    </tr>
                                    <?php
                                    $rebovalsMetro = array_values($reboValues['metro'] ?? []);
                                    $xtotalMetro = 0.0;
                                    for ($x = 0; $x < count($rebovalsMetro); $x++):
                                        $amt = (float)($rebovalsMetro[$x]['rollo_amt'] ?? 0);
                                        $dims = (float)($rebovalsMetro[$x]['rollo_dims'] ?? 0);
                                        $subtotal = $amt * $dims;
                                    ?>
                                        <tr>
                                            <td class="tdnrm"><?=(int)($rebovalsMetro[$x]['id'] ?? 0) ? $fmt($amt) : ''?></td>
                                            <td class="tdnrm"><?=(int)($rebovalsMetro[$x]['id'] ?? 0) ? $fmt($dims, 2) : ''?></td>
                                            <td class="tdnrm"><?=(int)($rebovalsMetro[$x]['id'] ?? 0) ? $fmt($subtotal, 2) : ''?></td>
                                        </tr>
                                    <?php
                                        $xtotalMetro += $subtotal;
                                    endfor;
                                    ?>
                                    <tr>
                                        <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Total</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;"><b><?=$fmt($xtotalMetro, 2)?></b></td>
                                    </tr>
                                    </table>
                                </td>
                                <td align="left" valign="top" width="50%" style="padding-left:10px">
                                    <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                                    <tr>
                                        <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="5" align="center">
                                            <i class="fa fa-fw fa-info"></i> Largos
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Materialidad</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Color</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Gramaje</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Ancho</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Largo</td>
                                    </tr>
                                    <?php
                                    $rebovalsMetroAmt = array_values($reboValues['metro_amt'] ?? []);
                                    $xtotalMetroAmt = 0.0;
                                    for ($x = 0; $x < count($rebovalsMetroAmt); $x++):
                                        $amt = (float)($rebovalsMetroAmt[$x]['rollo_amt'] ?? 0);
                                    ?>
                                        <tr>
                                            <td class="tdnrm"><?php if ($x === 0) echo htmlspecialchars((string)$agenda['fab_type']); ?></td>
                                            <td class="tdnrm"><?php if ($x === 0) echo htmlspecialchars((string)($agenda['fabric_color'] ?? '-')); ?></td>
                                            <td class="tdnrm"><?php if ($x === 0) echo $fmt($agenda['fab_mat_gramms']) . ' gr'; ?></td>
                                            <td class="tdnrm"><?php if ($x === 0) echo htmlspecialchars((string)($reborder['req_operador_tela_width'] ?? $agenda['req_operador_tela_width'] ?? '-')); ?></td>
                                            <td class="tdnrm"><?=(int)($rebovalsMetroAmt[$x]['id'] ?? 0) ? $fmt($amt) : ''?></td>
                                        </tr>
                                    <?php
                                        $xtotalMetroAmt += $amt;
                                    endfor;
                                    ?>
                                    <tr>
                                        <td class="tdleft" style="background-color:#EEEEEE;" colspan="4">Total</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;"><b><?=$fmt($xtotalMetroAmt, 0)?></b></td>
                                    </tr>
                                    </table>
                                </td>
                            </tr>
                            </table>
                            <div style="height:6px"></div>
                        <?php endif; ?>

                        <?php if (($reborder['req_rebo_rolloscc_opttype'] ?? '') !== 'Por metro'): ?>
                            <!-- MODO ROLLO: Distribución solicitado y Rollos -->
                            <div style="height:6px"></div>
                            <table border="0" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td align="left" valign="top" width="50%" style="padding-right:10px">
                                    <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                                    <colgroup>
                                        <col width="160">
                                        <col>
                                        <col>
                                    </colgroup>
                                    <tr>
                                        <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="3" align="center">
                                            <i class="fa fa-fw fa-info"></i> Distribución solicitado
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Cantidad</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Medida</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Subtotal</td>
                                    </tr>
                                    <?php
                                    $rolloVals = array_values($reboValues['rollo'] ?? []);
                                    if (empty($rolloVals) && !empty($subproducts)) {
                                        foreach ($subproducts as $sp) {
                                            $amt = (float)($sp['subprod_amount'] ?? 0);
                                            $dims = (float)($sp['subprod_ancho_cm'] ?? 0);
                                            if ($amt > 0 && $dims > 0) {
                                                $rolloVals[] = [
                                                    'id' => 1,
                                                    'rollo_amt' => $amt,
                                                    'rollo_dims' => $dims,
                                                ];
                                            }
                                        }
                                    }
                                    $xtotalRollo = 0.0;
                                    for ($x = 0; $x < count($rolloVals); $x++):
                                        $amt = (float)($rolloVals[$x]['rollo_amt'] ?? 0);
                                        $dims = (float)($rolloVals[$x]['rollo_dims'] ?? 0);
                                        $subtotal = $amt * $dims;
                                    ?>
                                        <tr>
                                            <td class="tdnrm"><?=(int)($rolloVals[$x]['id'] ?? 0) ? $fmt($amt) : ''?></td>
                                            <td class="tdnrm"><?=(int)($rolloVals[$x]['id'] ?? 0) ? $fmt($dims, 2) : ''?></td>
                                            <td class="tdnrm"><?=(int)($rolloVals[$x]['id'] ?? 0) ? $fmt($subtotal, 2) : ''?></td>
                                        </tr>
                                    <?php
                                        $xtotalRollo += $subtotal;
                                    endfor;
                                    ?>
                                    <tr>
                                        <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Total</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;"><b><?=$fmt($xtotalRollo, 2)?></b></td>
                                    </tr>
                                    </table>
                                </td>
                                <td align="left" valign="top" width="50%" style="padding-left:10px">
                                    <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                                    <tr>
                                        <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="5" align="center">
                                            <i class="fa fa-fw fa-info"></i> Rollos
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Cantidad de rollos</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Materialidad</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Color</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Gramaje</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Ancho</td>
                                    </tr>
                                    <?php
                                    $rolloAmtVals = array_values($reboValues['rollo_amt'] ?? []);
                                    if (empty($rolloAmtVals)) {
                                        $cantRollosMadre = (float)($reborder['req_rebo_rolloscc'] ?? 1);
                                        if ($cantRollosMadre <= 0) $cantRollosMadre = 1.0;
                                        $rolloAmtVals[] = [
                                            'id' => 1,
                                            'rollo_amt' => $cantRollosMadre,
                                        ];
                                    }
                                    $xtotalRolloAmt = 0.0;
                                    for ($x = 0; $x < count($rolloAmtVals); $x++):
                                        $amt = (float)($rolloAmtVals[$x]['rollo_amt'] ?? 0);
                                    ?>
                                        <tr>
                                            <td class="tdnrm"><?=(int)($rolloAmtVals[$x]['id'] ?? 0) ? $fmt($amt) : ''?></td>
                                            <td class="tdnrm"><?php if ($x === 0) echo htmlspecialchars((string)$agenda['fab_type']); ?></td>
                                            <td class="tdnrm"><?php if ($x === 0) echo htmlspecialchars((string)($agenda['fabric_color'] ?? '-')); ?></td>
                                            <td class="tdnrm"><?php if ($x === 0) echo $fmt($agenda['fab_mat_gramms']) . ' gr'; ?></td>
                                            <td class="tdnrm"><?php if ($x === 0) echo htmlspecialchars((string)($reborder['req_operador_tela_width'] ?? $agenda['req_operador_tela_width'] ?? '-')); ?></td>
                                        </tr>
                                    <?php
                                        $xtotalRolloAmt += $amt;
                                    endfor;
                                    ?>
                                    <tr>
                                        <td class="tdleft" style="background-color:#EEEEEE;" colspan="4">Total</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;"><b><?=$fmt($xtotalRolloAmt, 0)?></b></td>
                                    </tr>
                                    </table>
                                </td>
                            </tr>
                            </table>
                            <div style="height:6px"></div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!$reqGenRefId): ?>
                    <!-- TABLA FECHAS DE ENTREGA (editxid.php 2593-2638) -->
                    <table border="0" width="100%" cellpadding="6" cellspacing="0" style="margin-top:6px">
                    <tr>
                        <td class="tdleft" style="background-color:#165552;color:#FFFFFF" colspan="4" align="center">
                            <i class="fa fa-fw fa-calendar"></i> Fechas de entrega
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;width:calc(50% - 150px)">Cantidad programada</td>
                        <td class="tdnrm" style="width:150px"><b><?=$fmt($agenda['ag_amount'])?></b></td>
                        <td class="tdleft" style="background-color:#EEEEEE;width:calc(50% - 150px)">&nbsp;</td>
                        <td class="tdnrm" style="width:150px">&nbsp;</td>
                    </tr>
                    <?php
                    $hasEntregasPlan = false;
                    foreach ($entregas as $entrega): 
                        $hasEntregasPlan = true;
                        $entDate = (int)($entrega['prodplan_date'] ?? 0);
                        $entDateStr = $entDate > 0 ? date('d.m.Y', $entDate) : '-';
                    ?>
                        <tr>
                            <td class="tdleft" style="background-color:#EEEEEE;width:calc(50% - 150px)">Cantidad de Bolsas</td>
                            <td class="tdnrm" style="width:150px"><b><?=$fmt($entrega['prodplan_amt'] ?? 0)?></b></td>
                            <td class="tdleft" style="background-color:#EEEEEE;width:calc(50% - 150px)">Fechas de Entrega</td>
                            <td class="tdnrm" style="width:150px"><?=$entDateStr?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$hasEntregasPlan): ?>
                        <tr>
                            <td class="tdleft" align="center" colspan="4"><b class="msg_save_err" style="color:#C00;">No hay fechas de entrega registradas</b></td>
                        </tr>
                    <?php endif; ?>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    </div>

    <!-- ============================================================== -->
    <!-- DESPACHO DE SUBFORMULARIOS SEGÚN MODE (editxid.php 4594-4609)  -->
    <!-- ============================================================== -->

    <?php if (!$hasApertura && !$isSeriPulpo && !$isRebobinadora && empty($mode)): ?>
        <!-- ============================================================== -->
        <!-- FORMULARIO DIRECTO DE ALISTAMIENTO (editxid.php 5657 - 5780)   -->
        <!-- Se muestra obligatoriamente antes de iniciar la OT             -->
        <!-- ============================================================== -->
        <div style="background:#FFF8E7;border:1px solid #FFE082;border-radius:4px;padding:12px 18px;margin-bottom:15px;color:#8A6D3B;font-size:13px">
            <i class="fa fa-fw fa-info-circle"></i> <b>Paso 1: Alistamiento de Máquina</b> &mdash; Antes de iniciar la operación de la OT, debe registrar el alistamiento indicando ayudante y comentarios.
        </div>
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <div style="font-size:16px;font-weight:bold;color:#E1A500;margin-bottom:15px">
                <i class="fa fa-fw fa-wrench"></i> Iniciar Alistamiento
            </div>
            <form method="post" action="/production/work-orders/events/apertura" id="form_direct_alis">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <input type="hidden" name="mode" value="aperturasave">
                <input type="hidden" name="submode" value="start">

                <table border="0" width="100%" cellpadding="8" cellspacing="0">
                <colgroup><col width="160"><col></colgroup>
                <tr>
                    <td class="tdleft">Tipo</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)($machineInit['type_ant_title'] ?? $agenda['type_ant_title'] ?? '-'))?></b></td>
                </tr>
                <tr>
                    <td class="tdleft">Máquina</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)($machineInit['equipo_name'] ?? $agenda['equipo_name'] ?? '-'))?></b></td>
                </tr>
                <?php if (!empty($machineInit['type_ant_inpmedidas_act'])): ?>
                    <tr>
                        <td class="tdleft">De Medida *</td>
                        <td class="tdnrm">
                            <select name="evt_medida_fromid" class="inptxt" style="width:350px;background:#fff">
                                <option value="">&lt; Seleccione medida actual &gt;</option>
                                <?php foreach ($medidas as $m): ?>
                                    <option value="<?=$m['id']?>"><?=htmlspecialchars((string)$m['med_name'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">A Medida *</td>
                        <td class="tdnrm">
                            <select name="evt_medida_toid" class="inptxt" style="width:350px;background:#fff">
                                <option value="">&lt; Seleccione nueva medida &gt;</option>
                                <?php foreach ($medidas as $m): ?>
                                    <option value="<?=$m['id']?>"><?=htmlspecialchars((string)$m['med_name'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td class="tdleft">Ayudante</td>
                    <td class="tdnrm">
                        <select name="evt_idayudante" class="inptxt" style="width:350px;background:#fff">
                            <option value="0">&lt; Sin ayudante asignado &gt;</option>
                            <?php foreach ($helpers as $h): ?>
                                <option value="<?=$h['id']?>"><?=htmlspecialchars((string)$h['ayudantes'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft" valign="top">Comentarios</td>
                    <td class="tdnrm">
                        <textarea class="inptxt" name="evt_comments" id="evt_comments" style="width:100%;height:65px" placeholder="Detalles u observaciones de alistamiento..."></textarea>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">
                        <button type="submit" class="btngreen" style="font-size:14px;padding:9px 24px">
                            <i class="fa fa-fw fa-play"></i> Iniciar&nbsp;
                        </button>
                    </td>
                </tr>
                </table>
            </form>
        </div>

    <?php elseif ($mode === 'production'): ?>
        <!-- SUBFORMULARIO: Registrar Producción (form.production.php) -->
        <?php
        $currentRefEvt = null;
        if ($refId > 0) {
            foreach ($events as $ev) {
                if ((int)$ev['id'] === $refId) {
                    $currentRefEvt = $ev;
                    break;
                }
            }
        }
        $isEvtOpen = ($currentRefEvt !== null && (int)($currentRefEvt['evt_enddat'] ?? 0) === 0);
        $isStarting = ($openProdEvent === null && ($refId <= 0 || ($_GET['action'] ?? '') === 'start') && ($_GET['submode'] ?? '') !== 'end');
        $prodServiceInstance = new ProductionService(Db::erpPdo(), Db::trzPdo());
        $defectUnits = ($refId > 0) ? $prodServiceInstance->getEventDefectUnits($refId) : [];
        $mermaMap = [];
        $repairMap = [];
        foreach ($defectUnits as $du) {
            if ($du['evt_type'] === 'merma') {
                $mermaMap[(int)$du['evt_merma_typeid']] = $du;
            } elseif ($du['evt_type'] === 'repair') {
                $repairMap[(int)$du['evt_repair_typeid']] = $du;
            }
        }
        // Pre-llenar automáticamente las mermas registradas al desmontar bobina(s)
        $dismountMermas = ($workerOtId > 0) ? $prodServiceInstance->getRollDismountMermas($workerOtId) : [];
        $totalDismountKgs = 0.0;
        foreach ($dismountMermas as $dmTypeId => $dmInfo) {
            $dmKgs = (float)($dmInfo['kgs'] ?? 0);
            $totalDismountKgs += $dmKgs;
            if (empty($mermaMap[$dmTypeId]) || (float)($mermaMap[$dmTypeId]['evt_kgstounits'] ?? 0) <= 0) {
                $mermaMap[$dmTypeId] = [
                    'evt_amount' => (float)($dmInfo['amount'] ?? 0),
                    'evt_kgstounits' => $dmKgs,
                    'evt_mtstounits' => (int)($dmInfo['mts'] ?? 0),
                    'evt_comments' => (string)($dmInfo['comments'] ?? ''),
                    'from_dismount' => true,
                    'dismount_kgs' => $dmKgs,
                ];
            } else {
                $mermaMap[$dmTypeId]['dismount_kgs'] = $dmKgs;
            }
        }
        $valAmount = (float)($currentRefEvt['evt_amount'] ?? 0);
        $valAmount2 = (float)($currentRefEvt['evt_amount2'] ?? 0);
        $valBobinaKg = (float)($currentRefEvt['prod_bobina_kg'] ?? 0);
        $valMmaq = (int)($currentRefEvt['evt_amount_metros_maquina'] ?? 0);
        $valMlin = (int)($currentRefEvt['evt_amount_metros_lineales'] ?? 0);
        $valMtype = (string)($currentRefEvt['evt_metrotype'] ?? ($isFlexo ? 'metros_lineales' : ''));
        $valComments = (string)($currentRefEvt['evt_comments'] ?? '');
        $seriDevPrintsDefault = max(1, (int)($agenda['req_operador_bastidoramt'] ?? $agenda['req_solic_devprints_cc'] ?? 1));
        $seriCounterBaseValue = $valAmount2 > 0 ? (int)round($valAmount2) : 0;
        $seriCounterColors = [];
        $seriSavedCounters = [];
        $seriPrevCounters = [];
        $parseSeriCounterMap = static function (string $commentsText): array {
            $counterMap = [];
            if (!preg_match('/\[SERI_COUNTERS:\s*(.*?)\]/', $commentsText, $counterMetaMatch)) {
                return $counterMap;
            }
            $counterMeta = trim((string)($counterMetaMatch[1] ?? ''));
            $counterMeta = trim((string)preg_replace('/\s+\|\s+(?:img\/bast|imp\/des)=.*$/', '', $counterMeta));
            if ($counterMeta === '') {
                return $counterMap;
            }
            foreach (preg_split('/\s*,\s*/', $counterMeta) as $counterToken) {
                if (!preg_match('/^C(\d+):.*?=(\d+(?:\.\d+)?)$/', trim((string)$counterToken), $counterMatch)) {
                    continue;
                }
                $counterIdx = (int)$counterMatch[1];
                $counterMap[$counterIdx] = (float)$counterMatch[2];
            }
            return $counterMap;
        };
        foreach ($events as $seriEvent) {
            if (!in_array((string)($seriEvent['evt_type'] ?? ''), ['prod', 'prodsericolor'], true)) {
                continue;
            }
            if ((int)($seriEvent['id'] ?? 0) === $refId) {
                continue;
            }
            foreach ($parseSeriCounterMap((string)($seriEvent['evt_comments'] ?? '')) as $counterIdx => $counterVal) {
                $seriPrevCounters[$counterIdx] = (float)($seriPrevCounters[$counterIdx] ?? 0) + (float)$counterVal;
            }
        }
        if (preg_match('/\[SERI_COUNTERS:\s*(.*?)\]/', $valComments, $seriCounterMetaMatch)) {
            $seriCounterMeta = trim((string)($seriCounterMetaMatch[1] ?? ''));
            $seriCounterMeta = trim((string)preg_replace('/\s+\|\s+imp\/des=.*$/', '', $seriCounterMeta));
            if ($seriCounterMeta !== '') {
                foreach (preg_split('/\s*,\s*/', $seriCounterMeta) as $seriCounterToken) {
                    if (preg_match('/^C(\d+):.*?=(\d+)$/', trim((string)$seriCounterToken), $seriCounterMatch)) {
                        $seriSavedCounters[(int)$seriCounterMatch[1]] = (int)$seriCounterMatch[2];
                    }
                }
            }
        }
        for ($seriIdx = 1; $seriIdx <= 10; $seriIdx++) {
            $seriColorDesc = trim((string)($agenda['fab_print_colordesc_' . $seriIdx] ?? ''));
            $seriHasFront = (int)($agenda['fab_print_colors_front_' . $seriIdx] ?? 0) > 0;
            $seriHasBack = (int)($agenda['fab_print_colors_back_' . $seriIdx] ?? 0) > 0;
            if (!$seriHasFront && !$seriHasBack && $seriColorDesc === '') {
                continue;
            }
            $seriFaces = [];
            if ($seriHasFront) {
                $seriFaces[] = 'Frente';
            }
            if ($seriHasBack) {
                $seriFaces[] = 'Dorso';
            }
            $seriCounterColors[] = [
                'idx' => $seriIdx,
                'label' => $seriColorDesc !== '' ? $seriColorDesc : ('Color N° ' . $seriIdx),
                'faces' => implode(' / ', $seriFaces),
                'value' => (int)($seriSavedCounters[$seriIdx] ?? 0),
                'prev_value' => (int)round((float)($seriPrevCounters[$seriIdx] ?? 0)),
            ];
        }

        // Consultar resumen de bobinas impresas emitidas en la OT para automatizar suma de peso y metros
        $printedRollsSummary = ($workerOtId > 0) ? $prodServiceInstance->getPrintedRollsSummary($workerOtId) : [];
        if (!empty($printedRollsSummary['rolls_count']) && $printedRollsSummary['rolls_count'] > 0) {
            if ($valBobinaKg <= 0 || $isEvtOpen) {
                $valBobinaKg = (float)$printedRollsSummary['total_weight_kg'];
            }
            if ($valMlin <= 0 || $isEvtOpen) {
                $valMlin = (int)round($printedRollsSummary['total_meters']);
            }
            if ($valAmount <= 0) {
                if ((float)($corte_m2 ?? 0) > 0) {
                    $valAmount = (float)round($valMlin / (float)$corte_m2);
                } else {
                    $valAmount = 0.0;
                }
            }
        }

        $telaWProd = (float)($agenda['req_operador_tela_width'] ?? 80);
        $telaGsmProd = (float)($agenda['fab_mat_gramms'] ?? 70);
        $prodMeterWeightKg = ($telaWProd > 0 && $telaGsmProd > 0) ? round(($telaWProd / 100) * ($telaGsmProd / 1000), 4) : 0.056;
        $prodUnitWeightKg = 0.035;
        if ((float)($agenda['ag_amount'] ?? 0) > 0 && ($kgAImprimir ?? 0) > 0) {
            $prodUnitWeightKg = round(((float)$kgAImprimir) / (float)$agenda['ag_amount'], 4);
        }
        $flexoApproxUnits = $valAmount > 0
            ? (int)round($valAmount)
            : (((float)($corte_m2 ?? 0) > 0 && $valMlin > 0) ? (int)round($valMlin / (float)$corte_m2) : 0);
        ?>
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver al listado de eventos
            </div>
        </div>

        <?php if ($isStarting): ?>
            <!-- ============================================================== -->
            <!-- PANTALLA 1: INICIAR TIRAJE DE PRODUCCIÓN (ARRANQUE DE MÁQUINA) -->
            <!-- ============================================================== -->
            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:24px">
                <div style="font-size:17px;font-weight:bold;color:#00A85A;margin-bottom:15px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-play-circle" style="font-size:22px"></i> Iniciar Tiraje de Producción
                    <?=($overrideEmbalaje === 1 ? ' [Modo Embalaje]' : '')?>
                </div>

                <div style="background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:6px;padding:16px;margin-bottom:20px;color:#166534">
                    <div style="font-size:14px;font-weight:bold;margin-bottom:6px">
                        <i class="fa fa-info-circle"></i> Arranque de Producción
                    </div>
                    <div style="font-size:13px;line-height:1.5">
                        Al presionar <b>Comenzar Producción</b>, la máquina pasará a estado <b>EN PRODUCCIÓN ACTIVA</b> y comenzará a registrar el tiempo del tiraje.
                        Las unidades o metros fabricados y el registro de mermas se ingresarán al <b>Finalizar Producción</b> al concluir la tirada o cambiar de turno.
                    </div>
                </div>

                <form method="post" action="/production/work-orders/events/production">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                    <input type="hidden" name="action_type" value="start">
                    <input type="hidden" name="submode" value="start">
                    <input type="hidden" name="overrideembalaje" value="<?=$overrideEmbalaje?>">

                    <table border="0" width="100%" cellpadding="9" cellspacing="0">
                    <colgroup><col width="220"><col></colgroup>
                    <tr>
                        <td class="tdleft">Orden de Trabajo</td>
                        <td class="tdnrm"><b>OT #<?=htmlspecialchars((string)$agenda['prd_number'])?></b> &mdash; <?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Producto</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$agenda['item_title'])?></b> (<?=htmlspecialchars((string)$agenda['fab_type'])?>)</td>
                    </tr>
                    <tr>
                        <td class="tdleft">Cantidad Programada</td>
                        <td class="tdnrm"><b style="font-size:15px;color:#115452"><?=$fmt($agenda['ag_amount'])?></b> unidades / bolsas</td>
                    </tr>
                    <tr>
                        <td class="tdleft">Máquina / Proceso</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($machineInit['equipo_name'] ?? $agenda['equipo_name'] ?? '-'))?></b> (<?=htmlspecialchars((string)($machineInit['type_ant_title'] ?? $agenda['type_ant_title'] ?? '-'))?>)</td>
                    </tr>
                    <tr>
                        <td class="tdleft">Operador Responsable</td>
                        <td class="tdnrm"><b><?=htmlspecialchars($currentOperatorName)?></b></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Hora de Inicio</td>
                        <td class="tdnrm"><b style="color:#059669"><i class="fa fa-clock"></i> <?=date('d.m.Y H:i')?></b></td>
                    </tr>
                    <?php if ($isFlexo): ?>
                        <input type="hidden" name="evt_metrotype" value="metros_lineales">
                    <?php endif; ?>
                    <tr>
                        <td class="tdleft">Observaciones de Inicio</td>
                        <td class="tdnrm">
                            <textarea name="evt_comments" class="inptxt" style="width:100%;height:55px" placeholder="Observaciones iniciales de arranque (opcional)..."></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm" style="padding-top:18px">
                            <button type="submit" class="btngreen" style="font-size:15px;padding:12px 30px;box-shadow:0 2px 4px rgba(0,0,0,0.15)">
                                <i class="fa fa-fw fa-play"></i> Iniciar
                            </button>
                        </td>
                    </tr>
                    </table>
                </form>
            </div>

        <?php else: ?>
            <!-- ============================================================== -->
            <!-- PANTALLA 2: FINALIZAR PRODUCCIÓN O MODIFICAR REGISTRO          -->
            <!-- ============================================================== -->
            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
                <div style="font-size:16px;font-weight:bold;color:#115452;margin-bottom:15px">
                    <i class="fa fa-fw fa-flag-checkered"></i> <?=($refId > 0 ? ($isEvtOpen ? 'Finalizar / Registrar Avance de Producción' : 'Detalle de Producción') : 'Cierre de Producción')?>
                    <?=($overrideEmbalaje === 1 ? ' [Modo Embalaje]' : '')?>
                </div>

                <?php if ($isEvtOpen && $currentRefEvt !== null): ?>
                    <div style="background:#EFF6FF;border:1px solid #93C5FD;border-radius:4px;padding:12px 16px;margin-bottom:18px;color:#1E40AF">
                        <i class="fa fa-stopwatch"></i> Tiraje en curso iniciado el <b><?=date('d.m.Y H:i:s', (int)$currentRefEvt['evt_crtdat'])?></b>. Ingrese a continuación las bolsas/metros terminados y mermas para cerrar el evento.
                    </div>
                <?php endif; ?>

                <form method="post" action="/production/work-orders/events/production">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                    <input type="hidden" name="refid" value="<?=$refId?>">
                    <input type="hidden" name="overrideembalaje" value="<?=$overrideEmbalaje?>">
                    <input type="hidden" name="submode" id="prod_submode" value="<?=$isEvtOpen ? 'end' : 'update'?>">

                    <table border="0" width="100%" cellpadding="8" cellspacing="0">
                    <colgroup><col width="220"><col></colgroup>
                    <tr>
                        <td class="tdleft">Tipo</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($machineInit['type_ant_title'] ?? $agenda['type_ant_title'] ?? '-'))?></b></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Máquina</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($machineInit['equipo_name'] ?? $agenda['equipo_name'] ?? '-'))?></b></td>
                    </tr>
                    <?php if ($isFlexo): ?>
                        <input type="hidden" name="evt_metrotype" value="metros_lineales">
                        <input type="hidden" name="evt_amount_metros_maquina" value="<?=$valMmaq?>">
                        <?php if (!empty($printedRollsSummary['rolls_count']) && $printedRollsSummary['rolls_count'] > 0): ?>
                            <tr>
                                <td colspan="2" style="padding:0 0 14px 0">
                                    <div style="background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:6px;padding:12px 16px;color:#166534">
                                        <div style="font-size:13.5px;font-weight:bold;margin-bottom:5px;display:flex;align-items:center;gap:7px">
                                            <i class="fa fa-tag" style="color:#059669"></i> Bobinas Impresas Producidas en esta OT: 
                                            <b style="font-size:15px;color:#15803D"><?=number_format($printedRollsSummary['total_weight_kg'], 2, ',', '.')?> kg</b>
                                            <span style="font-size:12.5px;color:#047857">(<?=number_format($printedRollsSummary['total_meters'], 0, ',', '.')?> metros &bull; <?=$printedRollsSummary['rolls_count']?> bobina<?=$printedRollsSummary['rolls_count'] > 1 ? 's' : ''?>)</span>
                                        </div>
                                        <div style="font-size:12px;color:#166534;line-height:1.4">
                                            Se han sumado y precargado automáticamente los kilos y metros de las bobinas semielaboradas emitidas:
                                            <b><?=implode(', ', array_map(fn($r) => $r['roll_code'] . ' (' . number_format($r['weight_kg'], 2, ',', '.') . ' kg, ' . number_format($r['meters'], 0, ',', '.') . ' m)', $printedRollsSummary['rolls_list']))?></b>.
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="tdleft">Metros Lineales Producidos *</td>
                            <td class="tdnrm">
                                <input type="number" step="1" min="0" name="evt_amount_metros_lineales" id="inp_flexo_mlin" class="inptxt" style="width:200px;font-size:15px;font-weight:bold;<?=!empty($printedRollsSummary['rolls_count']) ? 'background:#F0FDF4;color:#166534' : ''?>" value="<?=$valMlin > 0 ? $valMlin : ''?>" placeholder="0" required oninput="calcFlexoKgs()"> mtrs
                                <?php if (!empty($printedRollsSummary['rolls_count'])): ?>
                                    <span style="font-size:11px;background:#059669;color:#FFFFFF;padding:3px 8px;border-radius:4px;margin-left:6px;font-weight:bold"><i class="fa fa-check-circle"></i> Suma de <?=count($printedRollsSummary['rolls_list'])?> bobinas impresas</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="tdleft">Unidades Aproximadas *</td>
                            <td class="tdnrm">
                                <input type="number" step="1" min="0" name="prod_amount" id="inp_flexo_amount" class="inptxt" style="width:200px;font-size:15px;font-weight:bold;background:#F8FAFC" value="<?=$flexoApproxUnits > 0 ? $flexoApproxUnits : ''?>" placeholder="0" required>
                                <div id="flexo_units_hint" style="font-size:11.5px;color:#64748B;margin-top:5px">
                                    <i class="fa fa-info-circle"></i>
                                    Aproximado segun el corte de la bolsa (<?=number_format((float)($corte_m2 ?? 0), 4, ',', '.')?> m por unidad). Puede corregirlo antes de guardar.
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="tdleft">Kilos Producidos *</td>
                            <td class="tdnrm">
                                <input type="number" step="0.01" min="0" name="prod_bobina_kg" id="inp_flexo_kgs" class="inptxt" style="width:200px;font-size:15px;font-weight:bold;background:#F0FDF4;color:#166534" value="<?=$valBobinaKg > 0 ? number_format($valBobinaKg, 2, '.', '') : ''?>" placeholder="0.00" required> kg
                                <?php if (!empty($printedRollsSummary['rolls_count'])): ?>
                                    <span style="font-size:11px;background:#059669;color:#FFFFFF;padding:3px 8px;border-radius:4px;margin-left:6px;font-weight:bold"><i class="fa fa-check-circle"></i> Suma de <?=count($printedRollsSummary['rolls_list'])?> bobinas impresas</span>
                                    <button type="button" class="btngrey" style="font-size:11px;padding:3px 8px;margin-left:6px;cursor:pointer" onclick="restorePrintedRollsValues()"><i class="fa fa-sync-alt"></i> Restablecer suma (<?=number_format($printedRollsSummary['total_weight_kg'], 2, ',', '.')?> kg)</button>
                                <?php else: ?>
                                    <span style="font-size:12px;color:#64748B;margin-left:8px"><i class="fa fa-calculator"></i> Calculado automáticamente según gramaje y ancho de tela (<?=$prodMeterWeightKg?> kg/m). Puede ajustarse manualmente si pesó en balanza.</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php elseif ($isSeriPulpo): ?>
                        <?php if ($amtColorsDorso > 0): ?>
                            <tr>
                                <td colspan="2" style="padding:0 0 12px 0">
                                    <div style="background:#F8FAFC;border:1.5px solid #CBD5E1;border-radius:6px;padding:12px 16px">
                                        <div style="font-weight:bold;color:#1E293B;margin-bottom:6px;font-size:13px;display:flex;align-items:center;gap:6px">
                                            <i class="fa fa-history" style="color:#0284C7"></i> Balance acumulado de tirajes anteriores en esta OT:
                                        </div>
                                        <div style="display:flex;gap:20px;flex-wrap:wrap;font-size:12.5px;color:#334155;margin-bottom:8px">
                                            <div>&bull; Frentes acumulados previos: <b><?=number_format($prevFrente, 0, ',', '.')?></b> pasadas</div>
                                            <div>&bull; Dorsos acumulados previos: <b><?=number_format($prevDorso, 0, ',', '.')?></b> pasadas</div>
                                            <div>&bull; Bolsas ya terminadas: <b><?=number_format($prevFinished, 0, ',', '.')?></b> unidades</div>
                                        </div>
                                        <div style="padding-top:6px;border-top:1px dashed #E2E8F0;font-size:12.5px">
                                            <?php if ($prevSurplusFrente > 0): ?>
                                                <span style="color:#D97706;font-weight:bold">
                                                    <i class="fa fa-exclamation-triangle"></i> Hay <?=number_format($prevSurplusFrente, 0, ',', '.')?> bolsas en proceso con FRENTE ya impreso (pendientes de dorso).
                                                </span>
                                            <?php elseif ($prevSurplusDorso > 0): ?>
                                                <span style="color:#D97706;font-weight:bold">
                                                    <i class="fa fa-exclamation-triangle"></i> Hay <?=number_format($prevSurplusDorso, 0, ',', '.')?> bolsas en proceso con DORSO ya impreso (pendientes de frente).
                                                </span>
                                            <?php else: ?>
                                                <span style="color:#059669;font-weight:bold">
                                                    <i class="fa fa-check-circle"></i> No hay bolsas pendientes entre caras (Frente y Dorso al día).
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="tdleft">Pasadas Frente (este tiraje)</td>
                                <td class="tdnrm">
                                    <input type="number" step="1" min="0" name="amount_pulpo_frente" id="idx_pulpo_frente" class="inptxt" style="width:200px" value="<?=(float)($currentRefEvt['evt_amount_pulpo_frente'] ?? 0) > 0 ? (int)$currentRefEvt['evt_amount_pulpo_frente'] : ''?>" placeholder="0" oninput="calcPulpoUnits()"> pasadas
                                </td>
                            </tr>
                            <tr>
                                <td class="tdleft">Pasadas Dorso (este tiraje)</td>
                                <td class="tdnrm">
                                    <input type="number" step="1" min="0" name="amount_pulpo_dorso" id="idx_pulpo_dorso" class="inptxt" style="width:200px" value="<?=(float)($currentRefEvt['evt_amount_pulpo_dorso'] ?? 0) > 0 ? (int)$currentRefEvt['evt_amount_pulpo_dorso'] : ''?>" placeholder="0" oninput="calcPulpoUnits()"> pasadas
                                </td>
                            </tr>
                            <tr>
                                <td class="tdleft">Bolsas Terminadas Completas *</td>
                                <td class="tdnrm">
                                    <input type="number" step="1" min="0" name="prod_amount" id="idx_pulpo_amount" class="inptxt" style="width:200px;font-weight:bold;background:#F8FAFC" value="<?=$valAmount > 0 ? (int)$valAmount : ''?>" placeholder="0" required> unidades
                                    <div id="pulpo_surplus_preview" style="font-size:12px;margin-top:5px"></div>
                                    <div style="font-size:11.5px;color:#64748B;margin-top:4px">
                                        <i class="fa fa-info-circle"></i> Se calculan las bolsas que completan ambas caras considerando también el saldo previo de frentes o dorsos disponibles.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td class="tdleft">Total Pasadas Frente *</td>
                                <td class="tdnrm">
                                    <input type="number" step="1" name="amount_pulpo_frente" id="idx_pulpo_frente" class="inptxt" style="width:200px" value="<?=(float)($currentRefEvt['evt_amount_pulpo_frente'] ?? 0) > 0 ? (int)$currentRefEvt['evt_amount_pulpo_frente'] : ''?>" placeholder="0" required oninput="calcPulpoUnitsSoloFrente()"> pasadas
                                    <input type="hidden" name="amount_pulpo_dorso" id="idx_pulpo_dorso" value="0">
                                </td>
                            </tr>
                            <tr>
                                <td class="tdleft">Bolsas Terminadas *</td>
                                <td class="tdnrm">
                                    <input type="number" step="1" name="prod_amount" id="idx_pulpo_amount" class="inptxt" style="width:200px;font-weight:bold;background:#F8FAFC" value="<?=$valAmount > 0 ? (int)$valAmount : ''?>" placeholder="0" required> unidades
                                    <div style="font-size:11.5px;color:#64748B;margin-top:4px">
                                        <i class="fa fa-info-circle"></i> Al ser solo frente, 1 pasada = 1 bolsa terminada.
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php elseif ($isSeri): ?>
                        <input type="hidden" name="prod_amount2" id="idx_seri_counter_base" value="<?=$seriCounterBaseValue > 0 ? $seriCounterBaseValue : ''?>">
                        <tr>
                            <td class="tdleft">Contador libre por color *</td>
                            <td class="tdnrm">
                                <div style="display:flex;flex-direction:column;gap:10px">
                                    <?php foreach ($seriCounterColors as $seriColor): ?>
                                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:8px 10px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px">
                                            <div style="min-width:220px;font-size:12.5px;font-weight:bold;color:#1E293B">
                                                <?=htmlspecialchars((string)$seriColor['label'])?>
                                                <?php if ($seriColor['faces'] !== ''): ?>
                                                    <div style="font-size:11px;font-weight:normal;color:#64748B"><?=htmlspecialchars((string)$seriColor['faces'])?></div>
                                                <?php endif; ?>
                                            </div>
                                            <input type="hidden" name="seri_counter_label_<?=$seriColor['idx']?>" value="<?=htmlspecialchars((string)$seriColor['label'])?>">
                                            <input type="number" step="1" min="0" name="seri_counter_color_<?=$seriColor['idx']?>" id="idx_seri_counter_<?=$seriColor['idx']?>" class="inptxt seri-counter-input" style="width:180px;font-size:15px;font-weight:bold" value="<?=$seriColor['value'] > 0 ? $seriColor['value'] : ''?>" placeholder="0" data-prev="<?=$seriColor['prev_value']?>" oninput="calcSeriUnitsFromCounters()">
                                            <span style="font-size:12px;color:#64748B">contador libre</span>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($seriCounterColors)): ?>
                                        <div style="font-size:12px;color:#64748B;background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:6px;padding:10px 12px">
                                            No hay colores configurados en la ficha. Puede registrar el contador igualmente en la cantidad final.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="tdleft">Cantidad de imágenes impresas en bastidor *</td>
                            <td class="tdnrm">
                                <input type="number" step="1" min="1" max="10" name="seri_bastidor_multiplier" id="idx_seri_bastidor_multiplier" class="inptxt" style="width:200px;font-size:15px;font-weight:bold" value="<?=$seriDevPrintsDefault?>" oninput="calcSeriUnitsFromCounters()"> imagen<?=($seriDevPrintsDefault !== 1 ? 'es' : '')?>
                            </td>
                        </tr>
                        <tr>
                            <td class="tdleft">Total unidades terminadas *</td>
                            <td class="tdnrm">
                                <input type="number" step="1" min="0" name="prod_amount" id="idx_seri_amount" class="inptxt" style="width:200px;font-weight:bold;background:#F8FAFC" value="<?=$valAmount > 0 ? (int)$valAmount : ''?>" placeholder="0" required> unidades
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td class="tdleft">Contador Principal / Bolsas *</td>
                            <td class="tdnrm"><input type="number" step="1" name="prod_amount" class="inptxt" style="width:200px" value="<?=$valAmount > 0 ? (int)$valAmount : ''?>" placeholder="0" required> unidades</td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="tdleft">Comentarios</td>
                        <td class="tdnrm"><textarea name="evt_comments" class="inptxt" style="width:100%;height:55px" placeholder="Observaciones de producción..."><?=htmlspecialchars($valComments)?></textarea></td>
                    </tr>

                    <?php if (!empty($mermatypes)): ?>
                        <!-- TABLA DE MERMAS (editxid.php líneas 850-915 y form.production.php 990-1010) -->
                        <tr>
                            <td colspan="2" style="padding-top:15px">
                                <?php if ($totalDismountKgs > 0): ?>
                                    <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:4px;padding:10px 14px;margin-bottom:12px;color:#166534;font-size:12.5px;display:flex;align-items:center;gap:8px">
                                        <i class="fa fa-dolly" style="font-size:16px;color:#059669"></i>
                                        <span>Se han precargado automáticamente <b><?=number_format($totalDismountKgs, 2, ',', '.')?> kg</b> de mermas registradas al desmontar bobina(s). Puede revisarlas, complementarlas o ajustarlas antes de finalizar.</span>
                                    </div>
                                <?php endif; ?>
                                <table border="0" width="100%" cellpadding="6" cellspacing="0" style="background:#FAFAFA;border:1px solid #DDDDDD;border-radius:4px">
                                <colgroup>
                                    <col width="220">
                                    <col width="120">
                                    <col width="120">
                                    <col width="120">
                                    <col>
                                </colgroup>
                                <tr>
                                    <td colspan="5" class="tdleft" style="background-color:#E8F0FE;color:#1A73E8;font-weight:bold">
                                        <i class="fa fa-fw fa-exclamation-triangle"></i> Registro de Mermas por Tipo
                                    </td>
                                </tr>
                                <tr>
                                    <td class="tdheader">Tipo / Causa</td>
                                    <td class="tdheader" align="center">Bolsas / Unid.</td>
                                    <td class="tdheader" align="center">Kilos</td>
                                    <td class="tdheader" align="center">Metros</td>
                                    <td class="tdheader">Comentarios</td>
                                </tr>
                                <?php foreach ($mermatypes as $mt):
                                    $mtId = (int)$mt['id'];
                                    $existM = $mermaMap[$mtId] ?? [];
                                    $mAmt = (float)($existM['evt_amount'] ?? 0);
                                    $mKgs = (float)($existM['evt_kgstounits'] ?? 0);
                                    $mMts = (int)($existM['evt_mtstounits'] ?? 0);
                                    $mComm = (string)($existM['evt_comments'] ?? '');
                                    $fromAlis = !empty($existM['from_alistamiento']);
                                    $fromDismount = !empty($existM['from_dismount']);
                                    $dismountKgs = (float)($existM['dismount_kgs'] ?? 0);
                                ?>
                                <tr <?=$fromDismount ? 'style="background:#F0FDF4"' : ($fromAlis ? 'style="background:#FEF9C3"' : '')?>>
                                    <td class="tdnrm">
                                        <b><?=htmlspecialchars((string)$mt['merma_title'])?></b>
                                        <?php if ($fromDismount && $dismountKgs > 0): ?>
                                            <span style="font-size:11px;background:#059669;color:#FFFFFF;padding:2px 7px;border-radius:4px;margin-left:6px;font-weight:bold" title="Cargado automáticamente desde el desmontaje de bobinas">
                                                <i class="fa fa-dolly"></i> Desmontaje: <?=number_format($dismountKgs, 2, ',', '.')?> kg
                                            </span>
                                        <?php elseif ($fromAlis): ?>
                                            <span style="font-size:11px;background:#CA8A04;color:#FFFFFF;padding:2px 7px;border-radius:4px;margin-left:6px;font-weight:bold"><i class="fa fa-check-circle"></i> Desde Autocontrol</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="tdnrm" align="center">
                                        <input type="number" step="1" min="0" name="merma_amount_<?=$mtId?>" id="merma_amt_<?=$mtId?>" class="inptxt" style="width:90px;text-align:center" value="<?=$mAmt > 0 ? (int)$mAmt : ''?>" oninput="syncMermaRow(<?=$mtId?>, 'amount')">
                                    </td>
                                    <td class="tdnrm" align="center">
                                        <input type="number" step="0.01" min="0" name="merma_kgs_<?=$mtId?>" id="merma_kgs_<?=$mtId?>" class="inptxt" style="width:90px;text-align:center;<?=$fromDismount ? 'background:#DCFCE7;font-weight:bold;color:#166534' : ($fromAlis ? 'background:#FFFBEB;font-weight:bold;color:#92400E' : '')?>" value="<?=$mKgs > 0 ? $mKgs : ''?>" oninput="syncMermaRow(<?=$mtId?>, 'kgs')">
                                    </td>
                                    <td class="tdnrm" align="center">
                                        <input type="number" step="1" min="0" name="merma_mts_<?=$mtId?>" id="merma_mts_<?=$mtId?>" class="inptxt" style="width:90px;text-align:center" value="<?=$mMts > 0 ? $mMts : ''?>" oninput="syncMermaRow(<?=$mtId?>, 'mts')">
                                    </td>
                                    <td class="tdnrm">
                                        <input type="text" name="merma_comments_<?=$mtId?>" class="inptxt" style="width:100%" value="<?=htmlspecialchars($mComm)?>" placeholder="Detalle...">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </table>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php if ($isSelladora && !empty($repairtypes)): ?>
                        <!-- TABLA DE REPARACIONES (editxid.php líneas 916-960) -->
                        <tr>
                            <td colspan="2" style="padding-top:15px">
                                <table border="0" width="100%" cellpadding="6" cellspacing="0" style="background:#FAFAFA;border:1px solid #DDDDDD;border-radius:4px">
                                <colgroup>
                                    <col width="220">
                                    <col width="120">
                                    <col width="120">
                                    <col>
                                </colgroup>
                                <tr>
                                    <td colspan="4" class="tdleft" style="background-color:#FFF3E0;color:#E65100;font-weight:bold">
                                        <i class="fa fa-fw fa-wrench"></i> Registro de Reparaciones / Defectos
                                    </td>
                                </tr>
                                <tr>
                                    <td class="tdheader">Tipo Reparación</td>
                                    <td class="tdheader" align="center">Unidades</td>
                                    <td class="tdheader" align="center">Kilos</td>
                                    <td class="tdheader">Comentarios</td>
                                </tr>
                                <?php foreach ($repairtypes as $rt):
                                    $rtId = (int)$rt['id'];
                                    $existR = $repairMap[$rtId] ?? [];
                                    $rAmt = (float)($existR['evt_amount'] ?? 0);
                                    $rKgs = (float)($existR['evt_kgstounits'] ?? 0);
                                    $rComm = (string)($existR['evt_comments'] ?? '');
                                ?>
                                <tr>
                                    <td class="tdnrm"><b><?=htmlspecialchars((string)$rt['repair_title'])?></b></td>
                                    <td class="tdnrm" align="center">
                                        <input type="number" step="1" name="repair_amount_<?=$rtId?>" class="inptxt" style="width:90px;text-align:center" value="<?=$rAmt > 0 ? (int)$rAmt : ''?>">
                                    </td>
                                    <td class="tdnrm" align="center">
                                        <input type="number" step="0.1" name="repair_kgs_<?=$rtId?>" class="inptxt" style="width:90px;text-align:center" value="<?=$rKgs > 0 ? $rKgs : ''?>">
                                    </td>
                                    <td class="tdnrm">
                                        <input type="text" name="repair_comments_<?=$rtId?>" class="inptxt" style="width:100%" value="<?=htmlspecialchars($rComm)?>" placeholder="Detalle...">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </table>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm" style="padding-top:15px">
                            <?php if ($refId > 0 && $isEvtOpen): ?>
                                <button type="submit" class="btnorange" style="font-size:14px;padding:10px 24px;font-weight:bold;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16)" onclick="document.getElementById('prod_submode').value='end'">
                                    <i class="fa fa-fw fa-stop"></i> Finalizar
                                </button>
                                <button type="submit" class="btngreen" style="font-size:14px;padding:10px 22px;margin-left:10px;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16)" onclick="document.getElementById('prod_submode').value='update'">
                                    <i class="fa fa-fw fa-save"></i> Guardar
                                </button>
                            <?php elseif ($refId > 0): ?>
                                <button type="submit" class="btngreen" style="font-size:14px;padding:10px 24px;font-weight:bold;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16)" onclick="document.getElementById('prod_submode').value='update'">
                                    <i class="fa fa-fw fa-save"></i> Guardar Cambios
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btnorange" style="font-size:14px;padding:10px 24px;font-weight:bold;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16)" onclick="document.getElementById('prod_submode').value='end'">
                                    <i class="fa fa-fw fa-check"></i> Finalizar
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    </table>
                </form>

                <script>
                var PROD_METER_WEIGHT_KG = <?=(float)$prodMeterWeightKg?>;
                var PROD_UNIT_WEIGHT_KG = <?=(float)$prodUnitWeightKg?>;
                var FLEXO_CUT_M2 = <?=(float)($corte_m2 ?? 0)?>;
                var IS_FLEXO = <?=$isFlexo ? 'true' : 'false'?>;
                var HAS_PRINTED_ROLLS = <?=!empty($printedRollsSummary['rolls_count']) ? 'true' : 'false'?>;
                var PRINTED_ROLLS_TOTAL_KG = <?=(float)($printedRollsSummary['total_weight_kg'] ?? 0)?>;
                var PRINTED_ROLLS_TOTAL_METERS = <?=(float)($printedRollsSummary['total_meters'] ?? 0)?>;

                function calcFlexoApproxUnits(mlin) {
                    var meters = parseFloat(mlin) || 0;
                    if (meters <= 0) return 0;
                    if (FLEXO_CUT_M2 > 0) {
                        return Math.round(meters / FLEXO_CUT_M2);
                    }
                    return 0;
                }

                function updateFlexoUnitsHint(estimatedUnits) {
                    var hint = document.getElementById('flexo_units_hint');
                    if (!hint) return;
                    var units = parseInt(String(estimatedUnits || '0'), 10) || 0;
                    if (units > 0) {
                        hint.innerHTML = '<i class=\"fa fa-info-circle\"></i> Aproximado segun el corte de la bolsa (' + String(FLEXO_CUT_M2 || 0).replace('.', ',') + ' m por unidad): <b>' + units.toLocaleString('es-CL') + ' unidades</b>. Puede corregirlo antes de guardar.';
                    } else {
                        hint.innerHTML = '<i class=\"fa fa-info-circle\"></i> Aproximado segun el corte de la bolsa (' + String(FLEXO_CUT_M2 || 0).replace('.', ',') + ' m por unidad). Puede corregirlo antes de guardar.';
                    }
                }

                function restorePrintedRollsValues() {
                    var mlinInp = document.getElementById('inp_flexo_mlin');
                    var kgsInp = document.getElementById('inp_flexo_kgs');
                    var amtInp = document.getElementById('inp_flexo_amount');
                    if (mlinInp) mlinInp.value = Math.round(PRINTED_ROLLS_TOTAL_METERS);
                    if (kgsInp) kgsInp.value = PRINTED_ROLLS_TOTAL_KG.toFixed(2);
                    if (amtInp) amtInp.value = calcFlexoApproxUnits(PRINTED_ROLLS_TOTAL_METERS) || '';
                    updateFlexoUnitsHint(calcFlexoApproxUnits(PRINTED_ROLLS_TOTAL_METERS));
                }

                function calcFlexoKgs() {
                    var mlinInp = document.getElementById('inp_flexo_mlin');
                    var kgsInp = document.getElementById('inp_flexo_kgs');
                    var amtInp = document.getElementById('inp_flexo_amount');
                    if (!mlinInp || !kgsInp) return;
                    var mlin = parseFloat(mlinInp.value) || 0;
                    if (HAS_PRINTED_ROLLS && Math.abs(mlin - PRINTED_ROLLS_TOTAL_METERS) < 1.0) {
                        kgsInp.value = PRINTED_ROLLS_TOTAL_KG.toFixed(2);
                    } else if (mlin > 0 && PROD_METER_WEIGHT_KG > 0) {
                        var kgs = (mlin * PROD_METER_WEIGHT_KG).toFixed(2);
                        kgsInp.value = kgs;
                    }
                    if (amtInp) {
                        var approxUnits = calcFlexoApproxUnits(mlin);
                        amtInp.value = approxUnits > 0 ? approxUnits : '';
                        updateFlexoUnitsHint(approxUnits);
                    }
                }

                updateFlexoUnitsHint(calcFlexoApproxUnits(document.getElementById('inp_flexo_mlin') ? document.getElementById('inp_flexo_mlin').value : 0));

                function calcSeriUnitsFromCounters() {
                    var factorInp = document.getElementById('idx_seri_bastidor_multiplier');
                    var amountInp = document.getElementById('idx_seri_amount');
                    var baseInp = document.getElementById('idx_seri_counter_base');
                    if (!factorInp || !amountInp || !baseInp) return;

                    var factor = parseInt(String(factorInp.value || '0').replace(',', '.'), 10) || 0;
                    if (factor <= 0) factor = 1;

                    var counters = [];
                    var prevCounters = [];
                    document.querySelectorAll('.seri-counter-input').forEach(function(inp) {
                        var val = parseInt(String(inp.value || '0').replace(',', '.'), 10) || 0;
                        var prevVal = parseInt(String(inp.getAttribute('data-prev') || '0').replace(',', '.'), 10) || 0;
                        prevCounters.push(prevVal);
                        counters.push(prevVal + val);
                    });

                    if (counters.length === 0) {
                        baseInp.value = '';
                        return;
                    }

                    var prevBaseCounter = prevCounters.length > 0 ? Math.min.apply(Math, prevCounters) : 0;
                    var newBaseCounter = Math.min.apply(Math, counters);
                    var baseCounter = Math.max(0, newBaseCounter - prevBaseCounter);
                    var totalUnits = baseCounter * factor;
                    baseInp.value = String(baseCounter);
                    amountInp.value = String(totalUnits);
                }

                function syncMermaRow(id, field) {
                    var amtInp = document.getElementById('merma_amt_' + id);
                    var kgsInp = document.getElementById('merma_kgs_' + id);
                    var mtsInp = document.getElementById('merma_mts_' + id);
                    if (!amtInp || !kgsInp || !mtsInp) return;

                    var amt = parseFloat(amtInp.value) || 0;
                    var kgs = parseFloat(kgsInp.value) || 0;
                    var mts = parseFloat(mtsInp.value) || 0;

                    if (field === 'amount' && amt > 0) {
                        if (PROD_UNIT_WEIGHT_KG > 0 && (!kgs || kgs === 0)) {
                            kgsInp.value = (amt * PROD_UNIT_WEIGHT_KG).toFixed(2);
                        }
                    } else if (field === 'mts' && mts > 0) {
                        if (PROD_METER_WEIGHT_KG > 0 && (!kgs || kgs === 0)) {
                            kgsInp.value = (mts * PROD_METER_WEIGHT_KG).toFixed(2);
                        }
                    } else if (field === 'kgs' && kgs > 0) {
                        if (IS_FLEXO && PROD_METER_WEIGHT_KG > 0 && (!mts || mts === 0)) {
                            mtsInp.value = Math.round(kgs / PROD_METER_WEIGHT_KG);
                        } else if (!IS_FLEXO && PROD_UNIT_WEIGHT_KG > 0 && (!amt || amt === 0)) {
                            amtInp.value = Math.round(kgs / PROD_UNIT_WEIGHT_KG);
                        }
                    }
                }

                if (document.getElementById('idx_seri_bastidor_multiplier')) {
                    calcSeriUnitsFromCounters();
                }
                </script>
            </div>
        <?php endif; ?>

    <?php elseif ($mode === 'apertura'): ?>
        <!-- SUBFORMULARIO: Apertura / Alistamiento de Medida (form.apertura.php) -->
        <?php
        $currentRefEvt = null;
        if ($refId > 0) {
            foreach ($events as $ev) {
                if ((int)$ev['id'] === $refId) {
                    $currentRefEvt = $ev;
                    break;
                }
            }
        }
        $isEvtOpen = ($currentRefEvt !== null && (int)($currentRefEvt['evt_enddat'] ?? 0) === 0);
        $savedComments = (string)($currentRefEvt['evt_comments'] ?? '');
        $savedMedFrom = (int)($currentRefEvt['evt_medida_fromid'] ?? 0);
        $savedMedTo = (int)($currentRefEvt['evt_medida_toid'] ?? 0);
        $savedAyudante = (int)($currentRefEvt['evt_idayudante'] ?? 0);
        ?>
        <?php if ($refId > 0 && !$isEvtOpen): ?>
            <script>location.href = '/production/work-orders/operate?agid=<?=$agId?>';</script>
        <?php else: ?>
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver al listado de eventos
            </div>
        </div>
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <div style="font-size:16px;font-weight:bold;color:#E1A500;margin-bottom:15px">
                <i class="fa fa-fw fa-wrench"></i> <?=($refId > 0 ? ($isEvtOpen ? 'Terminar Alistamiento / Ir a Autocontrol' : 'Detalle de Alistamiento') : 'Registrar Alistamiento')?>
            </div>
            <form method="post" action="/production/work-orders/events/apertura" id="form_apertura_edit" onsubmit="return validateAperturaEditSubmit();">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <input type="hidden" name="refid" value="<?=$refId?>">
                <input type="hidden" name="submode" id="ap_submode" value="">

                <table border="0" width="100%" cellpadding="8" cellspacing="0">
                <colgroup><col width="180"><col></colgroup>
                <tr>
                    <td class="tdleft">Tipo</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)($machineInit['type_ant_title'] ?? $agenda['type_ant_title'] ?? '-'))?></b></td>
                </tr>
                <tr>
                    <td class="tdleft">Máquina</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)($machineInit['equipo_name'] ?? $agenda['equipo_name'] ?? '-'))?></b></td>
                </tr>
                <?php if (!empty($machineInit['type_ant_inpmedidas_act'])): ?>
                    <tr>
                        <td class="tdleft">De Medida</td>
                        <td class="tdnrm">
                            <select name="evt_medida_fromid" class="inptxt" style="width:300px;background:#fff">
                                <option value="">Seleccione medida actual...</option>
                                <?php foreach ($medidas as $m): ?>
                                    <option value="<?=$m['id']?>" <?=$savedMedFrom === (int)$m['id'] ? 'selected' : ''?>><?=htmlspecialchars((string)$m['med_name'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">A Medida</td>
                        <td class="tdnrm">
                            <select name="evt_medida_toid" class="inptxt" style="width:300px;background:#fff">
                                <option value="">Seleccione nueva medida...</option>
                                <?php foreach ($medidas as $m): ?>
                                    <option value="<?=$m['id']?>" <?=$savedMedTo === (int)$m['id'] ? 'selected' : ''?>><?=htmlspecialchars((string)$m['med_name'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td class="tdleft">Ayudante</td>
                    <td class="tdnrm">
                        <select name="evt_idayudante" class="inptxt" style="width:300px;background:#fff">
                            <option value="0">&lt; Sin ayudante asignado &gt;</option>
                            <?php foreach ($helpers as $h): ?>
                                <option value="<?=$h['id']?>" <?=$savedAyudante === (int)$h['id'] ? 'selected' : ''?>><?=htmlspecialchars((string)$h['ayudantes'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft" valign="top">Comentarios</td>
                    <td class="tdnrm"><textarea name="evt_comments" id="evt_comments" class="inptxt" style="width:100%;height:65px" placeholder="Detalles de alistamiento..."><?=htmlspecialchars($savedComments)?></textarea></td>
                </tr>
                </table>
                <div style="clear:both;height:10px"></div>
                <?php if ($refId > 0): ?>
                    <table border="0" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="50%" style="padding-right:5px">
                            <div class="btngrey" onclick="location.href = '/production/work-orders/operate?agid=<?=$agId?>';" style="float:left;width:40%;cursor:pointer;text-align:center">
                                <i class="fa fa-fw fa-chevron-left" style="color:white;"></i> Volver&nbsp;
                            </div>
                        </td>
                        <td width="50%" style="padding-left:5px" align="right">
                            <?php if ($isEvtOpen): ?>
                                <div class="btnorange" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=autocontrol&end_alis=1';" style="margin-left:5px;float:right;cursor:pointer;text-align:center;padding:8px 16px;font-weight:bold;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16)">
                                    <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar
                                </div>
                                <div class="btngrey" onclick="document.getElementById('ap_submode').value='update';document.getElementById('form_apertura_edit').submit();" style="margin-left:5px;float:right;cursor:pointer;text-align:center;padding:8px 14px">
                                    <i class="fa fa-fw fa-save" style="color:white;"></i> Guardar
                                </div>
                            <?php else: ?>
                                <div class="btngreen" onclick="document.getElementById('ap_submode').value='update';document.getElementById('form_apertura_edit').submit();" style="width:40%;cursor:pointer;text-align:center">
                                    <i class="fa fa-fw fa-save" style="color:white;"></i> Guardar&nbsp;
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    </table>
                <?php else: ?>
                    <div class="btngreen" onclick="document.getElementById('ap_submode').value='start';document.getElementById('form_apertura_edit').submit();" style="cursor:pointer;display:inline-block">
                        <i class="fa fa-fw fa-play" style="color:white;"></i> Iniciar&nbsp;
                    </div>
                <?php endif; ?>
                    </td>
                </tr>
                </table>
            </form>

            <script>
            function validateAperturaEditSubmit() {
                var submode = document.getElementById('ap_submode').value;
                if (submode === 'end_and_autocontrol') {
                    var chks = document.querySelectorAll('.workerautochk_ap');
                    var allChecked = true;
                    chks.forEach(function(c) {
                        if (!c.checked) allChecked = false;
                    });
                    if (chks.length > 0 && !allChecked) {
                        alert('Debe seleccionar todos los puntos de autocontrol antes de ir a autocontrol.');
                        return false;
                    }
                }
                return true;
            }
            </script>
        </div>
        <?php endif; ?>

    <?php elseif ($mode === 'mantencion'): ?>
        <!-- SUBFORMULARIO: Mantención (form.mantencion.php) -->
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver al listado de eventos
            </div>
        </div>
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <div style="font-size:16px;font-weight:bold;color:#D64141;margin-bottom:15px">
                <i class="fa fa-fw fa-tools"></i> <?=($refId > 0 ? 'Terminar Mantención' : 'Registrar Mantención')?>
            </div>
            <form method="post" action="/production/work-orders/events/mantencion">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <input type="hidden" name="refid" value="<?=$refId?>">
                <input type="hidden" name="submode" id="mant_submode" value="">

                <table border="0" width="100%" cellpadding="8" cellspacing="0">
                <colgroup><col width="220"><col></colgroup>
                <tr>
                    <td class="tdleft">Tipo Mantención</td>
                    <td class="tdnrm">
                        <select name="evt_equipo_mantid" class="inptxt" style="width:250px" required>
                            <option value="">Seleccione tipo...</option>
                            <?php foreach ($repairs as $r): ?>
                                <option value="<?=$r['id']?>"><?=htmlspecialchars((string)$r['mant_title'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft">Comentarios / Causa</td>
                    <td class="tdnrm"><textarea name="evt_comments" class="inptxt" style="width:100%;height:60px" placeholder="Descripción de la mantención o falla..."></textarea></td>
                </tr>
                <tr>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">
                        <button type="submit" class="btnred" style="font-size:14px;padding:8px 20px" onclick="document.getElementById('mant_submode').value='start'">
                            <i class="fa fa-fw fa-play"></i> Iniciar Mantención
                        </button>
                        <?php if ($refId > 0): ?>
                            <button type="submit" class="btngreen" style="font-size:14px;padding:8px 20px;margin-left:10px" onclick="document.getElementById('mant_submode').value='end'">
                                <i class="fa fa-fw fa-check"></i> Terminar Mantención
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                </table>
            </form>
        </div>

    <?php elseif ($mode === 'pause'): ?>
        <?php
        $refEvent = null;
        if ($refId > 0) {
            foreach ($events as $ev) {
                if ((int)$ev['id'] === $refId) {
                    $refEvent = $ev;
                    break;
                }
            }
        }
        $selectedPauseId = $refEvent ? (int)($refEvent['evt_pause_id'] ?? 0) : 1; // 1 = Colación por defecto
        $pauseComments = $refEvent ? (string)($refEvent['evt_comments'] ?? '') : '';
        $isPauseEnded = $refEvent && !empty($refEvent['evt_enddat']) && (int)$refEvent['evt_enddat'] > 0;
        ?>
        <!-- SUBFORMULARIO: Pausa (form.pause.php) -->
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver al listado de eventos
            </div>
        </div>
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <div style="font-size:16px;font-weight:bold;color:#666666;margin-bottom:15px">
                <i class="fa fa-fw fa-coffee"></i> <?=($refId > 0 ? ($isPauseEnded ? 'Detalle de Pausa' : 'Terminar Pausa') : 'Registrar Pausa')?>
            </div>
            <form method="post" action="/production/work-orders/events/pause">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <input type="hidden" name="refid" value="<?=$refId?>">
                <input type="hidden" name="submode" id="pause_submode" value="<?=$refId > 0 && !$isPauseEnded ? 'end' : ''?>">

                <table border="0" width="100%" cellpadding="8" cellspacing="0">
                <colgroup><col width="220"><col></colgroup>
                <tr>
                    <td class="tdleft">Motivo de Pausa</td>
                    <td class="tdnrm">
                        <select name="evt_pause_id" class="inptxt" style="width:250px" required>
                            <option value="">Seleccione motivo...</option>
                            <?php foreach ($pauses as $p): ?>
                                <option value="<?=$p['id']?>" <?=((int)$p['id'] === $selectedPauseId ? 'selected' : '')?>><?=htmlspecialchars((string)$p['pause_name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft">Comentarios</td>
                    <td class="tdnrm"><textarea name="evt_comments" class="inptxt" style="width:100%;height:60px" placeholder="Observaciones..."><?=htmlspecialchars($pauseComments)?></textarea></td>
                </tr>
                <tr>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">
                        <?php if ($refId <= 0): ?>
                            <button type="submit" class="btngrey" style="font-size:14px;padding:8px 20px" onclick="document.getElementById('pause_submode').value='start'">
                                <i class="fa fa-fw fa-pause"></i> Iniciar Pausa
                            </button>
                        <?php elseif (!$isPauseEnded): ?>
                            <button type="submit" class="btngreen" style="font-size:14px;padding:8px 20px" onclick="document.getElementById('pause_submode').value='end'">
                                <i class="fa fa-fw fa-play"></i> Terminar Pausa
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btngreen" style="font-size:14px;padding:8px 20px">
                                <i class="fa fa-fw fa-save"></i> Guardar Cambios
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                </table>
            </form>
        </div>

    <?php elseif ($mode === 'tintas'): ?>
        <?php
        $availableInkRows = is_array($bodega3000InkItems) ? $bodega3000InkItems : [];
        $activeMachineInkLoads = [];
        $closedMachineInkLoads = [];
        $totalInkLoadedQty = 0.0;
        $totalInkAvailableQty = 0.0;
        foreach ($machineInkLoads as $machineInkLoad) {
            $availableQty = (float)($machineInkLoad['available_qty'] ?? 0);
            $totalInkLoadedQty += (float)($machineInkLoad['loaded_qty'] ?? 0);
            $totalInkAvailableQty += $availableQty;
            if ($availableQty > 0.0001) {
                $activeMachineInkLoads[] = $machineInkLoad;
            } else {
                $closedMachineInkLoads[] = $machineInkLoad;
            }
        }
        ?>
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver al listado de eventos
            </div>
        </div>

        <?php if (!empty($_GET['ink_loaded'])): ?>
            <div style="background:#ECFDF5;border:1.5px solid #10B981;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#065F46;font-size:13px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-check-circle" style="font-size:18px;color:#059669"></i> Tinta cargada a máquina exitosamente desde 3000 Insumos de Producción.
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['ink_consumed'])): ?>
            <div style="background:#EFF6FF;border:1.5px solid #3B82F6;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#1E40AF;font-size:13px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-check-circle" style="font-size:18px;color:#2563EB"></i> Consumo de tinta registrado correctamente.
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['ink_returned'])): ?>
            <div style="background:#F8FAFC;border:1.5px solid #CBD5E1;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#475569;font-size:13px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-undo" style="font-size:16px"></i> Sobrante de tinta devuelto a 3000 Insumos de Producción.
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div style="background:#FEF2F2;border:1.5px solid #EF4444;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#991B1B;font-size:13px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-exclamation-triangle" style="font-size:18px;color:#DC2626"></i>
                <div><?=htmlspecialchars((string)$_GET['error'])?></div>
            </div>
        <?php endif; ?>

        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:6px;padding:16px 20px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;box-shadow:0 1px 2px rgba(0,0,0,0.04)">
            <div>
                <div style="font-size:16px;font-weight:bold;color:#1E3A8A;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-tint"></i> Control de Tintas en Máquina &mdash; OT #<?=htmlspecialchars((string)$agenda['prd_number'])?>
                </div>
                <div style="font-size:12.5px;color:#64748B;margin-top:3px">
                    Cargue tintas desde <b>3000 Insumos de Producción</b> a la máquina, registre consumo parcial y devuelva el sobrante cuando corresponda.
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <span style="background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;padding:6px 10px;border-radius:999px;font-size:11.5px;font-weight:800">
                    <i class="fa fa-warehouse"></i> 3000 Insumos de Producción
                </span>
                <span style="background:#F8FAFC;color:#334155;border:1px solid #E2E8F0;padding:6px 10px;border-radius:999px;font-size:11.5px;font-weight:800">
                    <i class="fa fa-flask"></i> En máquina: <?=number_format($totalInkAvailableQty, 2, ',', '.')?> kg
                </span>
            </div>
        </div>

        <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,0.05);margin-bottom:18px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:14px">
                <div>
                    <div style="font-size:17px;font-weight:800;color:#1E3A8A;display:flex;align-items:center;gap:9px;margin-bottom:4px">
                        <span style="width:34px;height:34px;border-radius:10px;background:#DBEAFE;color:#1D4ED8;display:inline-flex;align-items:center;justify-content:center;font-size:15px">
                            <i class="fa fa-warehouse"></i>
                        </span>
                        Tintas disponibles en 3000 Insumos de Producción
                    </div>
                    <div style="font-size:12.5px;color:#64748B;line-height:1.55;max-width:760px">
                        Seleccione una tinta `TIN`, filtre por código o color y cargue solo los kilos que realmente entran a la máquina.
                    </div>
                </div>
                <div style="font-size:11.5px;color:#64748B;font-weight:800">
                    <?=count($availableInkRows)?> item(s) con stock
                </div>
            </div>

            <div style="margin-bottom:12px">
                <input type="text" id="filter_machine_inks" placeholder="Buscar por código, descripción o color..." class="inptxt" style="width:100%;max-width:380px;font-size:12px;padding:8px 12px;border-radius:8px;background:#FFF" onkeyup="filterMachineInkTable(this.value)">
            </div>

            <div style="overflow:auto;max-height:360px;border:1px solid #CBD5E1;border-radius:10px;background:#FFF">
                <table id="tbl_machine_inks" border="0" width="100%" cellpadding="9" cellspacing="0" style="min-width:980px">
                    <colgroup>
                        <col width="150">
                        <col>
                        <col width="110">
                        <col width="150">
                        <col width="250">
                    </colgroup>
                    <thead style="position:sticky;top:0;background:#F8FAFC;z-index:1;box-shadow:0 1px 0 #E2E8F0">
                        <tr>
                            <td class="tdheader">Código</td>
                            <td class="tdheader">Descripción</td>
                            <td class="tdheader" align="right">Stock</td>
                            <td class="tdheader" align="center">Cargar kg</td>
                            <td class="tdheader" align="center">Acción</td>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($availableInkRows)): ?>
                        <tr>
                            <td colspan="5" class="tdnrm" style="text-align:center;padding:18px;color:#64748B">
                                No se encontraron tintas `TIN` con stock en 3000 Insumos de Producción.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($availableInkRows as $inkRow): ?>
                            <?php
                            $inkItemId = (int)($inkRow['item_id'] ?? 0);
                            $inkCode = trim((string)($inkRow['code'] ?? ''));
                            $inkName = trim((string)($inkRow['name'] ?? ''));
                            $inkStock = (float)($inkRow['available_qty'] ?? 0);
                            $inkLoadFormId = 'ink_load_form_' . $inkItemId;
                            ?>
                            <tr style="border-bottom:1px solid #F1F5F9" onmouseover="this.style.backgroundColor='#F8FBFF'" onmouseout="this.style.backgroundColor=''">
                                <td class="tdnrm"><b><?=htmlspecialchars($inkCode !== '' ? $inkCode : '-')?></b></td>
                                <td class="tdnrm"><?=htmlspecialchars($inkName !== '' ? $inkName : '-')?></td>
                                <td class="tdnrm" align="right"><b style="color:#0F766E"><?=number_format($inkStock, 2, ',', '.')?> kg</b></td>
                                <td class="tdnrm" align="center">
                                    <form id="<?=$inkLoadFormId?>" method="post" action="/production/work-orders/inks/manage" style="display:none;margin:0">
                                        <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                        <input type="hidden" name="agid" value="<?=$agId?>">
                                        <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                        <input type="hidden" name="ink_action" value="load">
                                        <input type="hidden" name="item_id" value="<?=$inkItemId?>">
                                    </form>
                                    <input form="<?=$inkLoadFormId?>" type="number" step="0.01" min="0.01" max="<?=htmlspecialchars(number_format($inkStock, 3, '.', ''))?>" name="qty_kg" class="inptxt" style="width:90px;text-align:center;font-weight:bold" value="">
                                </td>
                                <td class="tdnrm" align="center">
                                        <input form="<?=$inkLoadFormId?>" type="text" name="comments" class="inptxt" style="width:120px" placeholder="Obs.">
                                        <button form="<?=$inkLoadFormId?>" type="submit" class="btnblue" style="padding:5px 12px;font-size:12px;cursor:pointer" onclick="return confirm('¿Cargar esta tinta desde 3000 Insumos de Producción a la máquina?');">
                                            <i class="fa fa-sign-in-alt"></i> Cargar
                                        </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,0.05);margin-bottom:18px">
            <div style="font-size:15px;font-weight:bold;color:#115452;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                <div style="display:flex;align-items:center;gap:8px">
                    <i class="fa fa-flask"></i> Tintas actualmente cargadas en máquina
                </div>
                <div style="font-size:12.5px;color:#64748B;font-weight:normal">
                    Activas: <b><?=count($activeMachineInkLoads)?></b> &bull; Cargadas históricas: <b><?=count($machineInkLoads)?></b>
                </div>
            </div>

            <?php if (empty($machineInkLoads)): ?>
                <div style="padding:15px;color:#64748B;font-size:13px;font-style:italic">
                    Aún no hay tintas cargadas en esta OT. Use la tabla superior para cargar desde 3000 Insumos de Producción.
                </div>
            <?php else: ?>
                <table border="0" width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #E2E8F0;border-radius:6px;overflow:hidden">
                    <thead>
                        <tr style="background:#F1F5F9;color:#334155;font-size:12px;text-transform:uppercase;letter-spacing:0.5px">
                            <th class="tdheader" align="left">Tinta</th>
                            <th class="tdheader" align="center">Cargado</th>
                            <th class="tdheader" align="center">Consumido</th>
                            <th class="tdheader" align="center">Devuelto</th>
                            <th class="tdheader" align="center">Disponible</th>
                            <th class="tdheader" align="center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($machineInkLoads as $inkLoad): ?>
                        <?php
                        $inkLoadId = (int)($inkLoad['id'] ?? 0);
                        $inkAvailable = (float)($inkLoad['available_qty'] ?? 0);
                        $inkIsOpen = $inkAvailable > 0.0001;
                        ?>
                        <tr style="border-bottom:1px solid #E2E8F0;background:<?=$inkIsOpen ? '#FFFFFF' : '#F8FAFC'?>">
                            <td class="tdnrm">
                                <div style="font-weight:700;color:#0F172A"><?=htmlspecialchars(trim((string)($inkLoad['item_code'] ?? '') . ' - ' . (string)($inkLoad['item_name'] ?? ''), ' -'))?></div>
                                <div style="font-size:11px;color:#64748B;margin-top:2px">Carga #<?=$inkLoadId?></div>
                            </td>
                            <td class="tdnrm" align="center"><b><?=number_format((float)($inkLoad['loaded_qty'] ?? 0), 2, ',', '.')?> kg</b></td>
                            <td class="tdnrm" align="center"><?=number_format((float)($inkLoad['consumed_qty'] ?? 0), 2, ',', '.')?> kg</td>
                            <td class="tdnrm" align="center"><?=number_format((float)($inkLoad['returned_qty'] ?? 0), 2, ',', '.')?> kg</td>
                            <td class="tdnrm" align="center">
                                <span style="background:<?=$inkIsOpen ? '#DBEAFE' : '#E5E7EB'?>;color:<?=$inkIsOpen ? '#1D4ED8' : '#475569'?>;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:800">
                                    <?=number_format($inkAvailable, 2, ',', '.')?> kg
                                </span>
                            </td>
                            <td class="tdnrm" align="center">
                                <?php if ($inkIsOpen): ?>
                                    <div style="display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap">
                                        <form method="post" action="/production/work-orders/inks/manage" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin:0">
                                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                            <input type="hidden" name="agid" value="<?=$agId?>">
                                            <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                            <input type="hidden" name="ink_action" value="consume">
                                            <input type="hidden" name="load_id" value="<?=$inkLoadId?>">
                                            <input type="number" step="0.01" min="0.01" max="<?=htmlspecialchars(number_format($inkAvailable, 3, '.', ''))?>" name="qty_kg" class="inptxt" style="width:78px;text-align:center" placeholder="kg">
                                            <button type="submit" class="btngreen" style="padding:4px 10px;font-size:11px;cursor:pointer" onclick="return confirm('¿Registrar consumo de esta tinta en la máquina?');">
                                                <i class="fa fa-minus-circle"></i> Consumir
                                            </button>
                                        </form>
                                        <form method="post" action="/production/work-orders/inks/manage" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin:0">
                                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                            <input type="hidden" name="agid" value="<?=$agId?>">
                                            <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                            <input type="hidden" name="ink_action" value="return">
                                            <input type="hidden" name="load_id" value="<?=$inkLoadId?>">
                                            <input type="number" step="0.01" min="0.01" max="<?=htmlspecialchars(number_format($inkAvailable, 3, '.', ''))?>" name="qty_kg" class="inptxt" style="width:78px;text-align:center" placeholder="kg">
                                            <button type="submit" class="btngrey" style="padding:4px 10px;font-size:11px;cursor:pointer" onclick="return confirm('¿Devolver este sobrante a 3000 Insumos de Producción?');">
                                                <i class="fa fa-undo"></i> Devolver
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span style="background:#DCFCE7;color:#166534;padding:4px 10px;border-radius:4px;font-size:11.5px;font-weight:bold;display:inline-block">
                                        <i class="fa fa-check-circle"></i> CERRADA
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <script>
        function filterMachineInkTable(query) {
            query = String(query || '').toLowerCase().trim();
            var rows = document.querySelectorAll('#tbl_machine_inks tbody tr');
            rows.forEach(function (tr) {
                var text = String(tr.innerText || tr.textContent || '').toLowerCase();
                tr.style.display = (query === '' || text.indexOf(query) !== -1) ? '' : 'none';
            });
        }
        </script>

    <?php elseif ($mode === 'materiales'): ?>
        <!-- SUBFORMULARIO: Materiales y Bobinas con Trazabilidad -->
        <?php
        $materialQuota = $ctx['material_quota'] ?? [];
        $assignedRolls = $materialQuota['assigned_rolls'] ?? [];
        $maxAllowedRolls = (int)($materialQuota['max_allowed_rolls'] ?? 2);
        $plannedKg = (float)($materialQuota['planned_kg'] ?? 0);
        $requiresSupervisor = !empty($materialQuota['requires_supervisor']);
        $targetColor = (string)($materialQuota['target_color'] ?? $ctx['agenda']['fabric_color'] ?? $ctx['agenda']['ag_color_tela'] ?? '');
        $targetWidth = (float)($materialQuota['target_width'] ?? $ctx['agenda']['req_operador_tela_width'] ?? $ctx['agenda']['fab_med_width'] ?? 0);
        $targetMaterial = (string)($materialQuota['target_material'] ?? $ctx['agenda']['fab_type'] ?? '');
        $targetGrammage = (float)($materialQuota['target_grammage'] ?? $ctx['agenda']['fab_mat_gramms'] ?? 0);

        $tMatUpper = mb_strtoupper(trim($targetMaterial));
        $normTargetMat = '';
        if ($tMatUpper === 'PLA' || $tMatUpper === 'P.L.A' || str_contains($tMatUpper, 'PLA')) {
            $normTargetMat = 'PLA';
        } elseif ($tMatUpper === 'PP' || $tMatUpper === 'TNT' || $tMatUpper === 'TNT60' || str_contains($tMatUpper, 'POLIPROP')) {
            $normTargetMat = 'PP';
        } elseif ($tMatUpper !== '') {
            $normTargetMat = $tMatUpper;
        }
        $targetColorClean = mb_strtolower(trim($targetColor));
        $targetColorClean = trim((string)preg_replace('/\(pantone[^\)]*\)/i', '', $targetColorClean));
        $compatibleRolls = [];
        $otherRolls = [];
        foreach ($availableRolls as $r) {
            if (!empty($r['is_compatible'])) {
                $compatibleRolls[] = $r;
            } else {
                $otherRolls[] = $r;
            }
        }
        usort($compatibleRolls, static function (array $a, array $b): int {
            $aSource = (string)($a['source'] ?? '');
            $bSource = (string)($b['source'] ?? '');
            $aPriority = $aSource === 'TRZ' ? 0 : 1;
            $bPriority = $bSource === 'TRZ' ? 0 : 1;
            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }

            $aCode = (string)($a['roll_code'] ?? $a['sku_code'] ?? '');
            $bCode = (string)($b['roll_code'] ?? $b['sku_code'] ?? '');
            return strcasecmp($aCode, $bCode);
        });
        $availableBodega3000Rolls = $compatibleRolls;
        $availableBodega3000Count = count($availableBodega3000Rolls);
        $availableBodega3000Kg = 0.0;
        foreach ($availableBodega3000Rolls as $availableRoll) {
            $unitKg = (float)($availableRoll['weight_kg'] ?? $availableRoll['current_weight_kg'] ?? 0);
            $qty = max(1.0, (float)($availableRoll['stock_qty'] ?? 1));
            $availableBodega3000Kg += (!empty($availableRoll['source']) && $availableRoll['source'] === 'ERP')
                ? ($unitKg * $qty)
                : $unitKg;
        }
        $mountedMergeCandidates = [];
        foreach ($assignedRolls as $candidateRoll) {
            $candidateStatus = (string)($candidateRoll['status'] ?? 'ASSIGNED');
            $candidateClosed = in_array($candidateStatus, ['CONSUMED', 'RETURNED'], true);
            if (empty($candidateRoll['is_mounted']) || $candidateClosed) {
                continue;
            }
            $mountedMergeCandidates[] = [
                'id' => (int)($candidateRoll['id'] ?? 0),
                'roll_code' => (string)($candidateRoll['roll_code'] ?? ('#' . (int)($candidateRoll['id'] ?? 0))),
                'sku_code' => (string)($candidateRoll['sku_code'] ?? ''),
                'weight_kg' => (float)($candidateRoll['entry_weight_kg'] ?? $candidateRoll['weight_kg'] ?? 0),
            ];
        }
        $compactMaterialCode = static function (string $code, string $fallback = ''): string {
            $code = trim($code);
            $fallback = trim($fallback);
            if ($fallback !== '' && preg_match('/^(TEL|TIN)[A-Z0-9\-]+$/i', $fallback)) {
                return strtoupper($fallback);
            }
            if ($code !== '' && preg_match('/\b((?:TEL|TIN)[A-Z0-9\-]+)\b/i', $code, $m)) {
                return strtoupper((string)$m[1]);
            }
            if ($fallback !== '') {
                return strtoupper($fallback);
            }
            return strtoupper($code);
        };
        $activeAssignedRolls = [];
        $closedAssignedRolls = [];
        foreach ($assignedRolls as $assignedRoll) {
            $assignedStatus = (string)($assignedRoll['status'] ?? 'ASSIGNED');
            $assignedClosed = in_array($assignedStatus, ['CONSUMED', 'RETURNED'], true);
            if ($assignedClosed) {
                $closedAssignedRolls[] = $assignedRoll;
            } else {
                $activeAssignedRolls[] = $assignedRoll;
            }
        }
        ?>
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver al listado de eventos
            </div>
        </div>

        <?php if ($isSeriPulpo): ?>
            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:18px;margin-bottom:18px">
                <div style="font-size:16px;font-weight:bold;color:#1E3A8A;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-palette"></i> Insumos - Serigrafía Pulpo (OT #<?=htmlspecialchars((string)$agenda['prd_number'])?>)
                </div>
                <div style="font-size:12.5px;color:#64748B;margin-top:4px">
                    En Pulpo la tinta no se registra en esta pantalla. La solicitud se hace a bodega y la utilización queda configurada en la tabla de <b>10 Unidades de Bastidores / Secadores</b>.
                </div>
            </div>

            <div style="background:#F8FAFC;border:1px solid #CBD5E1;border-radius:6px;padding:14px 16px;margin-bottom:18px">
                <div style="font-size:13px;font-weight:bold;color:#1E293B;margin-bottom:6px">
                    <i class="fa fa-shopping-bag" style="color:#0F766E"></i> Sustrato base de esta OT
                </div>
                <div style="font-size:12.5px;color:#334155">
                    <b><?=htmlspecialchars((string)($agenda['item_number_prod'] ?? ''))?></b>
                    <?php if (!empty($agenda['item_title'])): ?>
                        - <?=htmlspecialchars((string)$agenda['item_title'])?>
                    <?php endif; ?>
                </div>
                <div style="font-size:11.5px;color:#64748B;margin-top:4px">
                    En Pulpo la impresión se realiza directamente sobre estas bolsas/unidades base. Por eso aquí no corresponde montaje de bobinas ni consumo manual de tinta.
                </div>
            </div>

            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px;margin-bottom:18px">
                <div style="font-size:15px;font-weight:bold;color:#3F4C9B;margin-bottom:10px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-fw fa-sitemap"></i> Flujo correcto para Pulpo
                </div>
                <div style="font-size:12.5px;color:#475569;line-height:1.6;margin-bottom:14px">
                    1. <b>Solicitar tintas e insumos a bodega</b> desde el módulo exterior.<br>
                    2. <b>Configurar bastidores, mesh, secadores y total de tinta</b> en la tabla de 10 unidades dentro de la OT.<br>
                    3. Continuar el tiraje sin mezclar este proceso con bobinas montadas.
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <a href="/production/materials/request?agid=<?=$agId?>&material_kind=inks" class="btnblue" style="text-decoration:none;font-size:13px;padding:9px 18px;display:inline-flex;align-items:center;gap:7px">
                        <i class="fa fa-paper-plane"></i> Solicitar Tintas e Insumos
                    </a>
                    <a href="/production/work-orders/operate?agid=<?=$agId?>#pulpo-config-10" class="btngreen" style="text-decoration:none;font-size:13px;padding:9px 18px;display:inline-flex;align-items:center;gap:7px">
                        <i class="fa fa-th"></i> Ir a Configuración 10 Unidades
                    </a>
                    <a href="/production/work-orders/operate?agid=<?=$agId?>" class="btngrey" style="text-decoration:none;font-size:13px;padding:9px 18px;display:inline-flex;align-items:center;gap:7px">
                        <i class="fa fa-arrow-left"></i> Volver a la OT
                    </a>
                </div>
            </div>

            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:18px;margin-bottom:18px">
                <div style="font-size:15px;font-weight:bold;color:#115452;margin-bottom:10px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-check-circle"></i> Qué se hace en cada pantalla
                </div>
                <table border="0" width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #E2E8F0;border-radius:6px;overflow:hidden">
                    <thead>
                        <tr style="background:#F8FAFC;color:#334155;font-size:12px;text-transform:uppercase">
                            <th class="tdheader" align="left">Pantalla</th>
                            <th class="tdheader" align="left">Función</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom:1px solid #E2E8F0">
                            <td class="tdnrm"><b>Insumos</b></td>
                            <td class="tdnrm">Solicitar tintas e insumos a bodega para la OT.</td>
                        </tr>
                        <tr style="border-bottom:1px solid #E2E8F0">
                            <td class="tdnrm"><b>Configuración de 10 Unidades</b></td>
                            <td class="tdnrm">Registrar bastidor, mesh, secador y total de tinta por unidad/color.</td>
                        </tr>
                        <tr>
                            <td class="tdnrm"><b>Producción</b></td>
                            <td class="tdnrm">Ejecutar el tiraje sobre bolsas base, sin mezclar este flujo con bobinas.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!$isSeriPulpo): ?>
        <?php
        $printerOutputDefaultSku = trim((string)($agenda['item_number_prod'] ?? $agenda['item_title'] ?? $agenda['fab_type'] ?? 'BOBINA-IMPRESA'));
        $printerOutputSuggestedMeters = (float)($metros_a_imprimir ?? ($stats['total_meters'] ?? 0));
        $printerTelaWidth = (float)($agenda['req_operador_tela_width'] ?? ($agenda['fab_med_width'] ?? 80));
        if ($printerTelaWidth <= 0) {
            $printerTelaWidth = 80.0;
        }
        $printerTelaGramms = (float)($agenda['fab_mat_gramms'] ?? 80);
        if ($printerTelaGramms <= 0) {
            $printerTelaGramms = 80.0;
        }
        $printerMetersPerKg = round(100000.0 / ($printerTelaWidth * $printerTelaGramms), 2);
        ?>

        <!-- NOTIFICACIONES Y ALERTS DE MONTAJE Y SALIDA DE BOBINA -->
        <?php if (!empty($_GET['saved_output'])): ?>
            <div style="background:#ECFDF5;border:1.5px solid #10B981;border-radius:6px;padding:16px 20px;margin-bottom:18px;color:#065F46">
                <div style="font-weight:bold;font-size:15px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-check-circle" style="font-size:22px;color:#059669"></i> ¡Salida y desmontaje de bobina registrados exitosamente!
                </div>
                <?php if (!empty($_GET['output_code'])): ?>
                    <div style="margin-top:12px;background:#FFFFFF;border:1.5px solid #6366F1;padding:14px 18px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
                        <div style="font-size:13px;color:#4338CA;margin-bottom:6px">
                            <i class="fa fa-tag" style="color:#4F46E5"></i> <b>NUEVA BOBINA SALIENTE GENERADA:</b>
                        </div>
                        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                            <span style="font-size:17px;font-family:monospace;font-weight:bold;color:#3730A3;background:#EEF2FF;padding:5px 12px;border-radius:4px;border:1px solid #C7D2FE">
                                <?=htmlspecialchars((string)$_GET['output_code'])?>
                            </span>
                            <span style="font-size:14px;font-weight:bold;color:#1F2937">
                                Peso salida: <b style="color:#4338CA"><?=number_format((float)($_GET['output_kg'] ?? 0), 2, ',', '.')?> kg</b>
                            </span>
                            <?php if (!empty($_GET['output_meters'])): ?>
                                <span style="font-size:13px;color:#4338CA">
                                    <?=number_format((float)$_GET['output_meters'], 0, ',', '.')?> m aprox.
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($_GET['output_id'])): ?>
                                <a href="/rolls/<?=(int)$_GET['output_id']?>/label?server_print=1" target="_blank" class="btnblue" style="font-size:13px;padding:7px 16px;text-decoration:none;font-weight:bold;display:inline-flex;align-items:center;gap:6px;border-radius:4px;background:#4F46E5">
                                    <i class="fa fa-print"></i> 🖨️ Imprimir Etiqueta Salida
                                </a>
                                <a href="/rolls/<?=(int)$_GET['output_id']?>/label" target="_blank" class="btngrey" style="font-size:13px;padding:7px 14px;text-decoration:none;font-weight:bold;display:inline-flex;align-items:center;gap:6px;border-radius:4px">
                                    <i class="fa fa-eye"></i> Ver Etiqueta
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($_GET['leftover_code'])): ?>
                    <div style="margin-top:12px;background:#FFFFFF;border:1.5px solid #10B981;padding:14px 18px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
                        <div style="font-size:13px;color:#065F46;margin-bottom:6px">
                            <i class="fa fa-tags" style="color:#059669"></i> <b>NUEVA BOBINA SOBRANTE GENERADA &mdash; ETIQUETA LISTA PARA IMPRIMIR:</b>
                        </div>
                        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                            <span style="font-size:17px;font-family:monospace;font-weight:bold;color:#047857;background:#DCFCE7;padding:5px 12px;border-radius:4px;border:1px solid #86EFAC">
                                <?=htmlspecialchars((string)$_GET['leftover_code'])?>
                            </span>
                            <span style="font-size:14px;font-weight:bold;color:#1F2937">
                                Peso Sobrante: <b style="color:#059669"><?=number_format((float)($_GET['leftover_kg'] ?? 0), 2, ',', '.')?> kg</b>
                            </span>
                            <?php if (!empty($_GET['leftover_id'])): ?>
                                <a href="/rolls/<?=(int)$_GET['leftover_id']?>/label?server_print=1" target="_blank" class="btngreen" style="font-size:13px;padding:7px 16px;text-decoration:none;font-weight:bold;display:inline-flex;align-items:center;gap:6px;border-radius:4px">
                                    <i class="fa fa-print"></i> 🖨️ Mandar a Imprimir Etiqueta Sobrante (Zebra / 100×70mm)
                                </a>
                                <a href="/rolls/<?=(int)$_GET['leftover_id']?>/label" target="_blank" class="btnblue" style="font-size:13px;padding:7px 14px;text-decoration:none;font-weight:bold;display:inline-flex;align-items:center;gap:6px;border-radius:4px">
                                    <i class="fa fa-eye"></i> 👁️ Ver Etiqueta
                                </a>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:12px;color:#4B5563;margin-top:8px">
                            <i class="fa fa-info-circle"></i> La bobina sobrante ha quedado ingresada con trazabilidad completa vinculada a la bobina original y disponible en <b>Bodega 3000</b>.
                        </div>
                    </div>
                <?php elseif (!empty($_GET['remaining_mounted_code'])): ?>
                    <div style="margin-top:12px;background:#FFFFFF;border:1.5px solid #3B82F6;padding:14px 18px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
                        <div style="font-size:13px;color:#1D4ED8;margin-bottom:6px">
                            <i class="fa fa-layer-group" style="color:#2563EB"></i> <b>BOBINA QUE QUEDÓ MONTADA PARA EL SIGUIENTE TIRAJE:</b>
                        </div>
                        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                            <span style="font-size:17px;font-family:monospace;font-weight:bold;color:#1D4ED8;background:#DBEAFE;padding:5px 12px;border-radius:4px;border:1px solid #93C5FD">
                                <?=htmlspecialchars((string)$_GET['remaining_mounted_code'])?>
                            </span>
                            <span style="font-size:14px;font-weight:bold;color:#1F2937">
                                Peso que sigue montado: <b style="color:#2563EB"><?=number_format((float)($_GET['remaining_mounted_kg'] ?? 0), 2, ',', '.')?> kg</b>
                            </span>
                        </div>
                        <div style="font-size:12px;color:#475569;margin-top:8px">
                            <i class="fa fa-info-circle"></i> No se generó un código nuevo para este remanente; la misma bobina quedó activa y montada para el siguiente uso.
                        </div>
                    </div>
                <?php else: ?>
                    <div style="margin-top:6px;font-size:12.5px;color:#047857">
                        La bobina se registró como consumida en su totalidad para esta orden de trabajo (sin saldo sobrante).
                    </div>
                <?php endif; ?>
                <?php if (!empty($_GET['output_autoprint']) && !empty($_GET['output_id'])): ?>
                    <script>
                    window.addEventListener('load', function() {
                        window.open('/rolls/<?=(int)$_GET['output_id']?>/label?server_print=1', '_blank');
                    }, { once: true });
                    </script>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['mounted'])): ?>
            <div style="background:#EFF6FF;border:1.5px solid #3B82F6;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#1E40AF;font-size:13px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-check-circle" style="font-size:18px;color:#2563EB"></i>
                <div>
                    Bobina montada en máquina exitosamente.
                    <?php if (!empty($_GET['m_kg'])): ?>
                        Peso de ingreso a máquina registrado: <b><?=number_format((float)$_GET['m_kg'], 2, ',', '.')?> kg</b>.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['unmounted'])): ?>
            <div style="background:#F1F5F9;border:1.5px solid #94A3B8;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#334155;font-size:13px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-pause-circle" style="font-size:18px"></i> Bobina desmontada temporalmente de la máquina.
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['unassigned'])): ?>
            <div style="background:#F8FAFC;border:1.5px solid #CBD5E1;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#475569;font-size:13px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-undo" style="font-size:16px"></i> Bobina devuelta a bodega exitosamente.
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['error'])): ?>
            <div style="background:#FEF2F2;border:1.5px solid #EF4444;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#991B1B;font-size:13px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-exclamation-triangle" style="font-size:18px;color:#DC2626"></i>
                <div><?=htmlspecialchars((string)$_GET['error'])?></div>
            </div>
        <?php endif; ?>

        <!-- ENCABEZADO Y TABLERO COMPACTO FLEXOGRAFÍA -->
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:6px;padding:16px 20px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;box-shadow:0 1px 2px rgba(0,0,0,0.04)">
            <div>
                <div style="font-size:16px;font-weight:bold;color:#1E3A8A;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-link"></i> Control de Bobinas Flexografía &mdash; OT #<?=htmlspecialchars((string)$agenda['prd_number'])?>
                </div>
                <div style="font-size:12.5px;color:#64748B;margin-top:3px">
                    Izquierda: stock compatible en <b>Bodega 3000</b>. Derecha: bobinas que ya están en la máquina o al pie de máquina para seguir operando.
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="/production/materials/request?agid=<?=$agId?>" target="_blank" class="btnblue" style="text-decoration:none;font-weight:bold;font-size:13px;padding:8px 18px;display:inline-flex;align-items:center;gap:6px">
                    <i class="fa fa-dolly"></i> Solicitar más bobinas
                </a>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:minmax(0,1.2fr) minmax(360px,0.9fr);gap:18px;margin-bottom:18px;align-items:start">
            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:6px;padding:18px;min-width:0">
                <div style="font-size:15px;font-weight:bold;color:#115452;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
                    <div style="display:flex;align-items:center;gap:8px">
                        <i class="fa fa-fw fa-warehouse"></i> Bodega 3000
                    </div>
                    <div style="font-size:12px;color:#64748B;font-weight:normal">
                        <b><?=$availableBodega3000Count?></b> rollos &bull; <b><?=number_format($availableBodega3000Kg, 1, ',', '.')?></b> kg aprox.
                    </div>
                </div>

                <?php if (empty($availableBodega3000Rolls)): ?>
                    <div style="background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:6px;padding:24px 20px;text-align:center;color:#64748B">
                        <i class="fa fa-box-open" style="font-size:28px;color:#94A3B8;margin-bottom:10px;display:block"></i>
                        No hay bobinas compatibles disponibles ahora mismo en <b>Bodega 3000</b>.
                    </div>
                <?php else: ?>
                    <table border="0" width="100%" cellpadding="7" cellspacing="0" style="border:1px solid #E2E8F0;border-radius:6px;overflow:hidden">
                        <thead>
                            <tr style="background:#F8FAFC;color:#334155;font-size:11.5px;text-transform:uppercase;letter-spacing:0.3px">
                                <th class="tdheader" align="left" style="padding:9px 8px">Código</th>
                                <th class="tdheader" align="left" style="padding:9px 8px">Descripción</th>
                                <th class="tdheader" align="center" style="padding:9px 8px">Stock</th>
                                <th class="tdheader" align="center" style="padding:9px 8px">Peso</th>
                                <th class="tdheader" align="center" style="padding:9px 8px">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($availableBodega3000Rolls as $b3000Roll):
                            $b3000Id = (string)($b3000Roll['id'] ?? $b3000Roll['raw_id'] ?? '');
                            $b3000Code = (string)($b3000Roll['roll_code'] ?? $b3000Roll['sku_code'] ?? '-');
                            $b3000Desc = (string)($b3000Roll['sku_desc'] ?? '-');
                            $b3000Weight = (float)($b3000Roll['weight_kg'] ?? $b3000Roll['current_weight_kg'] ?? 0);
                            $b3000Qty = max(1.0, (float)($b3000Roll['stock_qty'] ?? 1));
                            $b3000Source = (string)($b3000Roll['source'] ?? '');
                            $b3000HasTraceability = ($b3000Source === 'TRZ');
                            $b3000IsCompatible = !empty($b3000Roll['is_compatible']);
                            $b3000DisplayCode = $compactMaterialCode($b3000Code, (string)($b3000Roll['sku_code'] ?? ''));
                        ?>
                            <tr style="border-bottom:1px solid #E2E8F0" onmouseover="this.style.backgroundColor='#F8FBFF'" onmouseout="this.style.backgroundColor=''">
                                <td class="tdnrm" style="padding:9px 8px;vertical-align:top">
                                    <div style="font-weight:800;color:#0F172A;font-size:13px"><?=htmlspecialchars($b3000DisplayCode)?></div>
                                    <div style="font-size:10.5px;color:#64748B;margin-top:2px">
                                        <?=$b3000HasTraceability ? 'Con trazabilidad' : 'Sin trazabilidad'?>
                                    </div>
                                </td>
                                <td class="tdnrm" style="padding:9px 8px;vertical-align:top">
                                    <div style="font-size:12.5px;color:#1E293B;line-height:1.35"><?=htmlspecialchars($b3000Desc)?></div>
                                </td>
                                <td class="tdnrm" align="center" style="padding:9px 8px;vertical-align:middle">
                                    <span style="background:#ECFDF5;color:#166534;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:800">
                                        <?=number_format($b3000Qty, 0, ',', '.')?>
                                    </span>
                                </td>
                                <td class="tdnrm" align="center" style="padding:9px 8px;vertical-align:middle">
                                    <b><?=number_format($b3000Weight, 1, ',', '.')?> kg</b>
                                </td>
                                <td class="tdnrm" align="center" style="padding:9px 8px;vertical-align:middle">
                                    <?php if ($b3000IsCompatible): ?>
                                        <button
                                            type="button"
                                            class="btngreen"
                                            style="font-size:12px;padding:6px 12px;cursor:pointer;font-weight:bold"
                                            onclick="openTakeBodega3000Modal(
                                                <?=htmlspecialchars(json_encode($b3000Id), ENT_QUOTES)?>,
                                                <?=htmlspecialchars(json_encode($b3000Code, JSON_UNESCAPED_UNICODE), ENT_QUOTES)?>,
                                                <?=htmlspecialchars(json_encode($b3000DisplayCode, JSON_UNESCAPED_UNICODE), ENT_QUOTES)?>,
                                                <?=htmlspecialchars(json_encode($b3000Desc, JSON_UNESCAPED_UNICODE), ENT_QUOTES)?>,
                                                <?=htmlspecialchars(json_encode($b3000Source), ENT_QUOTES)?>,
                                                <?=htmlspecialchars(json_encode(number_format($b3000Weight, 2, '.', '')), ENT_QUOTES)?>,
                                                <?=htmlspecialchars(json_encode($b3000HasTraceability ? 'with' : 'without'), ENT_QUOTES)?>,
                                                <?=htmlspecialchars(json_encode($b3000HasTraceability), ENT_QUOTES)?>
                                            )"
                                        >
                                            <i class="fa fa-sign-in-alt"></i> Montar en máquina
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btngrey" style="font-size:12px;padding:6px 12px;font-weight:bold;opacity:.75;cursor:not-allowed" disabled>
                                            <i class="fa fa-ban"></i> No tomar
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:6px;padding:18px;min-width:0">
                <?php
                $mountedCount = 0;
                foreach ($activeAssignedRolls as $activeAssignedRoll) {
                    if (!empty($activeAssignedRoll['is_mounted'])) {
                        $mountedCount++;
                    }
                }
                ?>
                <div style="font-size:15px;font-weight:bold;color:#115452;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
                    <div style="display:flex;align-items:center;gap:8px">
                        <i class="fa fa-fw fa-industry"></i> Máquina / pie de máquina
                    </div>
                    <div style="font-size:12px;color:#64748B;font-weight:normal">
                        <b><?=$mountedCount?></b> montadas &bull; <b><?=count($activeAssignedRolls)?></b> activas
                    </div>
                </div>

                <?php if (empty($activeAssignedRolls)): ?>
                    <div style="background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:6px;padding:24px 18px;text-align:center;color:#64748B">
                        <i class="fa fa-cogs" style="font-size:28px;color:#94A3B8;margin-bottom:10px;display:block"></i>
                        Aún no hay bobinas activas en esta máquina.
                    </div>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:12px">
                        <?php foreach ($activeAssignedRolls as $ar):
                            $isMounted = !empty($ar['is_mounted']);
                            $entryKg = (float)($ar['entry_weight_kg'] ?? $ar['weight_kg'] ?? 0);
                            $arDisplayCode = $compactMaterialCode((string)($ar['roll_code'] ?? ('#' . $ar['id'])), (string)($ar['sku_code'] ?? ''));
                        ?>
                            <div style="border:1px solid <?=$isMounted ? '#86EFAC' : '#E2E8F0'?>;background:<?=$isMounted ? '#F0FDF4' : '#F8FAFC'?>;border-radius:8px;padding:12px 14px">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
                                    <div>
                                        <div style="font-size:14px;font-weight:800;color:#0F172A"><?=htmlspecialchars($arDisplayCode)?></div>
                                        <div style="font-size:12px;color:#475569;margin-top:2px"><?=htmlspecialchars((string)($ar['sku_desc'] ?? ''))?></div>
                                    </div>
                                    <div style="text-align:right">
                                        <div style="font-size:12px;font-weight:bold;color:<?=$isMounted ? '#166534' : '#92400E'?>">
                                            <?=$isMounted ? 'Montada en máquina' : 'Al pie de máquina'?>
                                        </div>
                                        <div style="font-size:12px;color:#334155;margin-top:2px">
                                            <?=number_format($entryKg > 0 ? $entryKg : (float)($ar['weight_kg'] ?? 0), 2, ',', '.')?> kg
                                        </div>
                                    </div>
                                </div>

                                <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                                    <?php if ($isMounted): ?>
                                        <button type="button" class="btnblue" style="font-size:12px;padding:7px 12px;cursor:pointer;font-weight:bold;display:inline-flex;align-items:center;gap:6px" onclick="openRollOutputModal(<?= (int)$ar['id'] ?>, <?= htmlspecialchars(json_encode($ar['roll_code'] ?? ('#' . $ar['id']))) ?>, <?= (float)$entryKg ?>)">
                                            <i class="fa fa-sign-out-alt"></i> Desmontar / salida
                                        </button>
                                        <form method="post" action="/production/work-orders/materials/mount" style="display:inline" class="js-preserve-material-scroll">
                                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                            <input type="hidden" name="agid" value="<?=$agId?>">
                                            <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                            <input type="hidden" name="roll_id" value="<?=$ar['id']?>">
                                            <input type="hidden" name="action_type" value="unmount">
                                            <button type="submit" class="btngrey" style="font-size:11px;padding:6px 10px;cursor:pointer">
                                                <i class="fa fa-pause"></i> Desmontar temp.
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="/production/work-orders/materials/mount" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center" class="js-preserve-material-scroll">
                                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                            <input type="hidden" name="agid" value="<?=$agId?>">
                                            <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                            <input type="hidden" name="roll_id" value="<?=$ar['id']?>">
                                            <input type="hidden" name="action_type" value="mount">
                                            <input type="number" step="0.01" min="0.1" name="entry_weight_kg" value="<?=number_format((float)($ar['weight_kg'] ?? 0), 2, '.', '')?>" class="inptxt" style="width:100px;padding:5px 7px;font-weight:bold;font-size:12px" required title="Peso real medido al ingresar la bobina a máquina">
                                            <button type="submit" class="btngreen" style="font-size:12px;padding:6px 10px;cursor:pointer;font-weight:bold">
                                                <i class="fa fa-play"></i> Montar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="/production/work-orders/materials/mount" style="display:inline" class="js-preserve-material-scroll">
                                        <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                        <input type="hidden" name="agid" value="<?=$agId?>">
                                        <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                        <input type="hidden" name="roll_id" value="<?=$ar['id']?>">
                                        <input type="hidden" name="action_type" value="unassign">
                                        <button type="submit" class="btngrey" style="font-size:11px;padding:6px 10px;cursor:pointer">
                                            <i class="fa fa-undo"></i> Devolver a 3000
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($closedAssignedRolls)): ?>
            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:6px;padding:18px;margin-bottom:18px">
                <div style="font-size:15px;font-weight:bold;color:#115452;margin-bottom:12px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-history"></i> Historial de salidas registradas
                </div>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <?php foreach ($closedAssignedRolls as $ar): ?>
                        <div style="border:1px solid #E2E8F0;background:#FAFAFA;border-radius:8px;padding:12px 14px">
                            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                                <div>
                                    <div style="font-size:13.5px;font-weight:800;color:#0F172A"><?=htmlspecialchars($compactMaterialCode((string)($ar['roll_code'] ?? ('#' . $ar['id'])), (string)($ar['sku_code'] ?? '')))?></div>
                                    <div style="font-size:12px;color:#64748B;margin-top:2px"><?=htmlspecialchars((string)($ar['sku_desc'] ?? ''))?></div>
                                </div>
                                <div style="font-size:12px;color:#166534;font-weight:bold">
                                    Salida registrada
                                </div>
                            </div>
                            <div style="margin-top:8px;font-size:12px;color:#334155;display:flex;gap:14px;flex-wrap:wrap">
                                <span>Ingreso: <b><?=number_format((float)($ar['entry_weight_kg'] ?? $ar['weight_kg'] ?? 0), 2, ',', '.')?> kg</b></span>
                                <span>Salida: <b><?=number_format((float)($ar['output_bobina_kg'] ?? 0), 2, ',', '.')?> kg</b></span>
                                <span>Mermas: <b style="color:#DC2626"><?=number_format((float)($ar['scrap_kg'] ?? 0), 2, ',', '.')?> kg</b></span>
                                <?php if (!empty($ar['output_at'])): ?>
                                    <span><?=htmlspecialchars((string)$ar['output_at'])?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($ar['remaining_mounted_roll_code']) || (float)($ar['remaining_mounted_kg'] ?? 0) > 0): ?>
                                <div style="margin-top:8px;background:#EFF6FF;border:1px solid #93C5FD;border-radius:6px;padding:8px 10px;font-size:12px;color:#1D4ED8">
                                    <b>Quedó montada:</b> <?=htmlspecialchars((string)($ar['remaining_mounted_roll_code'] ?? ''))?> (<?=number_format((float)($ar['remaining_mounted_kg'] ?? 0), 2, ',', '.')?> kg)
                                    <?php if (!empty($ar['remaining_mounted_formula'])): ?>
                                        <div style="font-size:11px;color:#334155;margin-top:3px">Cálculo: <?=htmlspecialchars((string)$ar['remaining_mounted_formula'])?></div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif (!empty($ar['leftover_roll_code']) || (float)($ar['leftover_kg'] ?? 0) > 0): ?>
                                <div style="margin-top:8px;background:#F0FDF4;border:1px solid #86EFAC;border-radius:6px;padding:8px 10px;font-size:12px;color:#166534">
                                    <b>Sobrante:</b> <?=htmlspecialchars((string)($ar['leftover_roll_code'] ?? ''))?> (<?=number_format((float)($ar['leftover_kg'] ?? 0), 2, ',', '.')?> kg)
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div id="modal_take_bodega3000" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.62);z-index:99998;align-items:center;justify-content:center;padding:18px;box-sizing:border-box">
            <div style="width:100%;max-width:560px;background:#FFFFFF;border-radius:10px;box-shadow:0 20px 45px rgba(15,23,42,0.35);border:1px solid #CBD5E1;overflow:hidden">
                <div style="background:#1E3A8A;color:#FFFFFF;padding:14px 18px;display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <div style="font-size:16px;font-weight:bold"><i class="fa fa-sign-in-alt"></i> Montar bobina en máquina</div>
                        <div style="font-size:12px;color:#BFDBFE;margin-top:3px" id="modal_take_bodega3000_subtitle">-</div>
                    </div>
                    <button type="button" onclick="closeTakeBodega3000Modal()" style="background:transparent;border:none;color:#FFFFFF;font-size:24px;cursor:pointer;line-height:1">&times;</button>
                </div>
                <form method="post" action="/production/work-orders/materials/assign" style="margin:0;padding:18px" class="js-preserve-material-scroll" onsubmit="return validateTakeBodega3000Modal()">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                    <input type="hidden" name="action_mode" value="take_bodega3000">
                    <input type="hidden" name="roll_id" id="modal_take_bodega3000_roll_id" value="">
                    <input type="hidden" name="traceability_source" id="modal_take_bodega3000_source" value="">
                    <input type="hidden" name="expected_traceability_code" id="modal_take_bodega3000_expected_code" value="">

                    <div style="font-size:13px;color:#334155;line-height:1.5;margin-bottom:14px">
                        <b id="modal_take_bodega3000_display_code">-</b><br>
                        <span id="modal_take_bodega3000_desc">-</span>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:12px">
                        <div>
                            <label style="display:block;font-size:12px;font-weight:bold;color:#334155;margin-bottom:5px">Peso ingreso máquina (kg)</label>
                            <input type="number" step="0.01" min="0.1" name="weight_kg" id="modal_take_bodega3000_weight" class="inptxt" style="width:100%;padding:8px 10px;font-size:14px;font-weight:bold" required>
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;font-weight:bold;color:#334155;margin-bottom:5px">Trazabilidad</label>
                            <select name="traceability_mode" id="modal_take_bodega3000_traceability_mode" class="inptxt" style="width:100%;padding:8px 10px;font-size:13px" onchange="toggleTakeBodega3000TraceabilityMode()">
                                <option value="without">Sin trazabilidad</option>
                                <option value="with">Con trazabilidad</option>
                            </select>
                        </div>
                    </div>

                    <div id="modal_take_bodega3000_trace_scan_wrap" style="display:none;margin-bottom:14px">
                        <label style="display:block;font-size:12px;font-weight:bold;color:#334155;margin-bottom:5px">Código de trazabilidad</label>
                        <input type="text" name="confirmed_traceability_code" id="modal_take_bodega3000_trace_code" class="inptxt" style="width:100%;padding:8px 10px;font-size:13px;font-family:monospace;text-transform:uppercase" placeholder="Escanee o escriba el código">
                        <div id="modal_take_bodega3000_trace_hint" style="font-size:11px;color:#64748B;margin-top:4px"></div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap">
                        <button type="button" class="btngrey" style="padding:7px 14px;cursor:pointer" onclick="closeTakeBodega3000Modal()">Cancelar</button>
                        <button type="submit" class="btngreen" style="padding:7px 16px;cursor:pointer;font-weight:bold">
                            <i class="fa fa-check"></i> Montar en máquina
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        var MATERIAL_SCROLL_KEY = 'unibag_material_scroll_<?=$agId?>';

        function rememberMaterialScrollPosition() {
            try {
                sessionStorage.setItem(MATERIAL_SCROLL_KEY, String(window.scrollY || window.pageYOffset || 0));
            } catch (e) {}
        }

        (function restoreMaterialScrollPosition() {
            try {
                var params = new URLSearchParams(window.location.search || '');
                if (params.get('restore_scroll') !== '1') {
                    return;
                }
                var savedScroll = sessionStorage.getItem(MATERIAL_SCROLL_KEY);
                if (savedScroll === null || savedScroll === '') {
                    return;
                }
                window.addEventListener('load', function() {
                    window.scrollTo(0, parseInt(savedScroll, 10) || 0);
                }, { once: true });
            } catch (e) {}
        })();

        function openTakeBodega3000Modal(rollId, code, displayCode, desc, source, weightKg, traceabilityMode, forceTraceability) {
            var modal = document.getElementById('modal_take_bodega3000');
            if (!modal) return;
            document.getElementById('modal_take_bodega3000_roll_id').value = rollId || '';
            document.getElementById('modal_take_bodega3000_source').value = source || '';
            document.getElementById('modal_take_bodega3000_expected_code').value = code || '';
            document.getElementById('modal_take_bodega3000_display_code').innerText = displayCode || code || '-';
            document.getElementById('modal_take_bodega3000_desc').innerText = desc || '-';
            document.getElementById('modal_take_bodega3000_subtitle').innerText = 'Código base: ' + (code || '-');
            document.getElementById('modal_take_bodega3000_weight').value = weightKg || '';

            var select = document.getElementById('modal_take_bodega3000_traceability_mode');
            if (select) {
                select.value = traceabilityMode || 'without';
                select.dataset.forceTraceability = forceTraceability ? '1' : '0';
            }
            var traceInput = document.getElementById('modal_take_bodega3000_trace_code');
            if (traceInput) {
                traceInput.value = '';
            }
            toggleTakeBodega3000TraceabilityMode();
            modal.style.display = 'flex';
            setTimeout(function() {
                var weightInput = document.getElementById('modal_take_bodega3000_weight');
                if (weightInput) weightInput.focus();
            }, 40);
        }

        function closeTakeBodega3000Modal() {
            var modal = document.getElementById('modal_take_bodega3000');
            if (modal) modal.style.display = 'none';
        }

        function toggleTakeBodega3000TraceabilityMode() {
            var select = document.getElementById('modal_take_bodega3000_traceability_mode');
            var wrap = document.getElementById('modal_take_bodega3000_trace_scan_wrap');
            var input = document.getElementById('modal_take_bodega3000_trace_code');
            var hint = document.getElementById('modal_take_bodega3000_trace_hint');
            var expectedCode = String((document.getElementById('modal_take_bodega3000_expected_code') || {}).value || '');
            if (select && String(select.dataset.forceTraceability || '') === '1') {
                select.value = 'with';
            }
            var useTraceability = select && String(select.value || '').toLowerCase() === 'with';
            if (wrap) wrap.style.display = useTraceability ? 'block' : 'none';
            if (input) {
                input.required = useTraceability;
                if (!useTraceability) {
                    input.value = '';
                }
            }
            if (hint) {
                hint.innerText = useTraceability
                    ? ((select && String(select.dataset.forceTraceability || '') === '1')
                        ? ('Este rollo ya tiene trazabilidad. Confirme el código exacto: ' + expectedCode)
                        : ('Confirme el código exacto que ingresará a máquina: ' + expectedCode))
                    : 'Se registrará como ingreso sin trazabilidad individual.';
            }
        }

        function validateTakeBodega3000Modal() {
            var weight = parseFloat(String((document.getElementById('modal_take_bodega3000_weight') || {}).value || '').replace(',', '.')) || 0;
            if (weight <= 0) {
                return false;
            }
            var select = document.getElementById('modal_take_bodega3000_traceability_mode');
            var traceInput = document.getElementById('modal_take_bodega3000_trace_code');
            if (select && String(select.value || '').toLowerCase() === 'with') {
                if (!traceInput || String(traceInput.value || '').trim() === '') {
                    if (traceInput) traceInput.focus();
                    return false;
                }
            }
            rememberMaterialScrollPosition();
            return true;
        }

        document.addEventListener('DOMContentLoaded', function() {
            var forms = document.querySelectorAll('.js-preserve-material-scroll');
            forms.forEach(function(form) {
                form.addEventListener('submit', function() {
                    rememberMaterialScrollPosition();
                });
            });
        });
        </script>
        <?php endif; ?>

        <?php if (!$isSeriPulpo): ?>
        <!-- VENTANA FLOTANTE (MODAL): DESMONTAJE Y SALIDA DE BOBINA -->
        <div id="modal_roll_output" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.65);z-index:99999;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;backdrop-filter:blur(3px)">
            <div style="background:#FFFFFF;border-radius:10px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.3),0 10px 10px -5px rgba(0,0,0,0.2);width:100%;max-width:680px;max-height:92vh;overflow-y:auto;position:relative;border:1px solid #CBD5E1">
                <!-- Modal Header -->
                <div style="background:#1E3A8A;color:#FFFFFF;padding:14px 20px;border-top-left-radius:9px;border-top-right-radius:9px;display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <div style="font-size:16px;font-weight:bold;display:flex;align-items:center;gap:8px">
                            <i class="fa fa-sign-out-alt"></i> Desmontaje y Salida de Bobina
                        </div>
                        <div style="font-size:12.5px;color:#BFDBFE;margin-top:3px" id="modal_roll_subtitle">
                            Bobina: <b id="modal_roll_code_txt" style="color:#FFFFFF">-</b> &bull; Peso Ingreso: <b id="modal_roll_entry_txt" style="color:#FFFFFF">0.00 kg</b>
                        </div>
                    </div>
                    <button type="button" onclick="closeRollOutputModal()" style="background:transparent;border:none;color:#FFFFFF;font-size:24px;cursor:pointer;padding:0 6px;line-height:1" title="Cerrar ventana">
                        &times;
                    </button>
                </div>

                <!-- Modal Body / Form -->
                <form method="post" action="/production/work-orders/materials/consume" style="margin:0;padding:20px" onsubmit="return validateRollOutputModal();">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                    <input type="hidden" name="roll_id" id="modal_roll_id" value="0">
                    <input type="hidden" name="scrap_weight" id="modal_scrap_weight" value="0">

                    <?php if ($isPrinter): ?>
                    <div style="background:#EEF2FF;border:1.5px solid #C7D2FE;border-radius:8px;padding:14px;margin-bottom:16px">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px">
                            <label style="font-size:13.5px;font-weight:bold;color:#3730A3;display:flex;align-items:center;gap:8px;cursor:pointer">
                                <input type="checkbox" name="create_output_roll" id="modal_create_output_roll" value="1" checked onchange="toggleOutputRollSection()">
                                <span><i class="fa fa-tag"></i> Generar nueva bobina saliente con etiqueta</span>
                            </label>
                            <label style="font-size:12px;color:#4338CA;display:flex;align-items:center;gap:6px;cursor:pointer">
                                <input type="checkbox" name="auto_print_output" value="1" checked>
                                Imprimir etiqueta al guardar
                            </label>
                        </div>
                        <div id="modal_output_roll_section">
                            <div style="font-size:12px;color:#4338CA;margin-bottom:10px">
                                La bobina saliente quedará trazada como semielaborada y lista para impresión de etiqueta desde este mismo desmontaje.
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:10px">
                                <div>
                                    <label style="display:block;font-size:12px;font-weight:bold;color:#312E81;margin-bottom:4px">Peso Bobina Saliente (kg) *</label>
                                    <input type="number" step="0.01" min="0" name="output_bobina_kg" id="modal_output_bobina_kg" class="inptxt" style="width:100%;padding:8px 10px;font-size:14px;font-weight:bold;border:2px solid #6366F1;border-radius:6px" value="0.00" placeholder="0.00" oninput="recalcModalOutputMeters()" onchange="recalcModalOutputMeters()">
                                </div>
                                <div>
                                    <label style="display:block;font-size:12px;font-weight:bold;color:#312E81;margin-bottom:4px">Metros aprox.</label>
                                    <input type="number" step="1" min="0" name="output_meters" id="modal_output_meters" class="inptxt" style="width:100%;padding:8px 10px;font-size:14px;font-weight:bold;border:2px solid #A5B4FC;border-radius:6px;background:#F8FAFF" value="<?=$printerOutputSuggestedMeters > 0 ? (int)$printerOutputSuggestedMeters : 0?>" placeholder="0">
                                </div>
                                <div style="grid-column:span 2">
                                    <label style="display:block;font-size:12px;font-weight:bold;color:#312E81;margin-bottom:4px">Descripción / SKU bobina saliente</label>
                                    <input type="hidden" name="output_sku_code" id="modal_output_sku_code" value="<?=htmlspecialchars($printerOutputDefaultSku)?>">
                                    <div style="width:100%;padding:8px 10px;font-size:13px;font-weight:bold;color:#312E81;background:#F8FAFF;border:1px solid #C7D2FE;border-radius:6px;box-sizing:border-box">
                                        <?=htmlspecialchars($printerOutputDefaultSku)?>
                                    </div>
                                    <div style="margin-top:4px;font-size:11px;color:#4338CA">
                                        Código fijo definido por la OT. No se puede editar manualmente.
                                    </div>
                                </div>
                            </div>
                            <div style="margin-top:10px;background:#E0E7FF;border:1px solid #C7D2FE;border-radius:6px;padding:8px 12px;font-size:12px;color:#3730A3">
                                <i class="fa fa-calculator"></i> Relación estimada: <b id="modal_output_ratio_text"><?=number_format($printerMetersPerKg, 2, ',', '.')?> m/kg</b> con ancho <b><?=number_format($printerTelaWidth, 1, ',', '.')?> cm</b> y gramaje <b><?=number_format($printerTelaGramms, 0, ',', '.')?> g/m2</b>.
                                <div style="margin-top:4px;color:#4338CA">
                                    Cálculo automático actual: <b id="modal_output_calc_text"><?=$printerOutputSuggestedMeters > 0 ? number_format((float)$printerOutputSuggestedMeters, 0, ',', '.') : '0'?> m aprox.</b>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="output_bobina_kg" value="0">
                    <?php endif; ?>

                    <div style="background:#EFF6FF;border:1.5px solid #93C5FD;border-radius:8px;padding:14px;margin-bottom:16px">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px">
                            <div>
                                <label style="font-size:13.5px;font-weight:bold;color:#1D4ED8;display:flex;align-items:center;gap:6px">
                                    <i class="fa fa-random"></i> Bobinas que se unieron a esta salida
                                </label>
                                <div style="font-size:12px;color:#1E40AF">
                                    Si esta nueva bobina terminada se formó usando 2, 3 o más bobinas montadas, márquelas aquí para cerrarlas juntas en una sola salida.
                                </div>
                            </div>
                            <div style="background:#DBEAFE;border:1px solid #93C5FD;padding:4px 10px;border-radius:5px;font-size:12px;font-weight:bold;color:#1D4ED8" id="modal_merge_count_badge">
                                Adicionales: 0
                            </div>
                        </div>
                        <?php if (!empty($mountedMergeCandidates)): ?>
                            <div id="modal_merge_rolls_list" style="display:flex;flex-direction:column;gap:8px;max-height:180px;overflow:auto;padding-right:2px">
                                <?php foreach ($mountedMergeCandidates as $mergeCandidate): ?>
                                    <label
                                        class="modal-merge-roll-row"
                                        data-roll-id="<?=$mergeCandidate['id']?>"
                                        data-roll-code="<?=htmlspecialchars($mergeCandidate['roll_code'], ENT_QUOTES)?>"
                                        data-roll-weight="<?=number_format((float)$mergeCandidate['weight_kg'], 2, '.', '')?>"
                                        style="background:#FFFFFF;border:1px solid #BFDBFE;border-radius:6px;padding:8px 10px;display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer"
                                    >
                                        <div style="display:flex;align-items:center;gap:10px;min-width:0">
                                            <input type="checkbox" class="modal-merge-roll-checkbox" name="source_roll_ids[]" value="<?=$mergeCandidate['id']?>" onchange="updateMergeRollSelection()" style="transform:scale(1.1)">
                                            <div style="min-width:0">
                                                <div style="font-size:12.5px;font-weight:bold;color:#0F172A;word-break:break-all">
                                                    <?=htmlspecialchars($mergeCandidate['roll_code'])?>
                                                </div>
                                                <div style="font-size:11px;color:#475569">
                                                    <?=htmlspecialchars($mergeCandidate['sku_code'] !== '' ? $mergeCandidate['sku_code'] : 'Sin SKU')?> · <?=number_format((float)$mergeCandidate['weight_kg'], 2, ',', '.')?> kg
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-merge-roll-hint" style="font-size:11px;font-weight:bold;color:#2563EB;white-space:nowrap">
                                            Sumar a esta salida
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="background:#FFFFFF;border:1px dashed #93C5FD;border-radius:6px;padding:10px 12px;font-size:12px;color:#1E40AF">
                                No hay otras bobinas montadas disponibles para combinar en esta salida.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="background:#F8FAFC;border:1.5px solid #CBD5E1;border-radius:8px;padding:14px;margin-bottom:16px">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px">
                            <label style="font-size:13.5px;font-weight:bold;color:#0F172A;display:flex;align-items:center;gap:8px;cursor:pointer">
                                <input type="checkbox" name="keep_remaining_mounted" id="modal_keep_remaining_mounted" value="1" onchange="toggleRemainingMountedSection()">
                                <span><i class="fa fa-layer-group"></i> Dejar bobina montada para el siguiente tiraje</span>
                            </label>
                        </div>
                        <div id="modal_remaining_mounted_section" style="display:none">
                            <div style="font-size:12px;color:#334155;margin-bottom:10px">
                                El sistema calculará el peso remanente para la bobina que seguirá montada, sin generar un código nuevo. La referencia base será:
                                <b>peso entrada total - bobina saliente - mermas</b>.
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:10px">
                                <div>
                                    <label style="display:block;font-size:12px;font-weight:bold;color:#334155;margin-bottom:4px">Bobina que quedará montada</label>
                                    <select name="remaining_mounted_roll_id" id="modal_remaining_mounted_roll_id" class="inptxt" style="width:100%;padding:8px 10px;font-size:13px" onchange="recalcRemainingMountedWeight()">
                                        <option value="0">Seleccione bobina</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display:block;font-size:12px;font-weight:bold;color:#334155;margin-bottom:4px">Peso que quedará montado (kg)</label>
                                    <input type="number" step="0.01" min="0" name="remaining_mounted_kg" id="modal_remaining_mounted_kg" class="inptxt" style="width:100%;padding:8px 10px;font-size:14px;font-weight:bold;background:#EFF6FF;border:2px solid #93C5FD;border-radius:6px;color:#1D4ED8" value="0.00" readonly>
                                </div>
                            </div>
                            <div id="modal_remaining_formula" style="margin-top:10px;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:6px;padding:8px 12px;font-size:12px;color:#1D4ED8">
                                Cálculo: 0,00 - 0,00 - 0,00 = 0,00 kg
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 1: DESGLOSE DE MERMAS POR TIPO -->
                    <div style="background:#FFF1F2;border:1.5px solid #FECDD3;border-radius:8px;padding:14px;margin-bottom:16px">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;flex-wrap:wrap;gap:8px">
                            <div>
                                <label style="font-size:13.5px;font-weight:bold;color:#9F1239;display:flex;align-items:center;gap:6px">
                                    <i class="fa fa-trash-alt"></i> Mermas de Salida por Tipo
                                </label>
                                <div style="font-size:12px;color:#9F1239">
                                    Desglose los kilos de merma según el tipo que corresponda. En la primera bobina puede cargar aquí <b>Ajuste / Aprobación</b> para absorber la merma de alistamiento.
                                </div>
                            </div>
                            <div style="background:#FFE4E6;border:1px solid #FDA4AF;padding:4px 10px;border-radius:5px;font-size:12px;font-weight:bold;color:#9F1239" id="modal_total_merma_badge">
                                Total Mermas: 0.00 kg
                            </div>
                        </div>

                        <div style="display:flex;flex-direction:column;gap:8px;margin-top:10px">
                        <?php 
                        $typesList = !empty($mermatypes) ? $mermatypes : [
                            ['id' => 6, 'merma_title' => 'Merma Proceso'],
                            ['id' => 7, 'merma_title' => 'Impresión (Selladora)'],
                            ['id' => 8, 'merma_title' => 'Otros'],
                            ['id' => 9, 'merma_title' => 'Ajuste / Aprobación'],
                        ];
                        foreach ($typesList as $mt): 
                            $mtId = (int)$mt['id'];
                            $mtTitle = (string)$mt['merma_title'];
                        ?>
                            <div style="background:#FFFFFF;border:1px solid #FBCFE8;border-radius:6px;padding:8px 10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                                <div style="width:170px;font-size:12.5px;font-weight:bold;color:#881337">
                                    <?=htmlspecialchars($mtTitle)?>:
                                </div>
                                <div style="display:flex;align-items:center;gap:4px">
                                    <input type="number" step="0.01" min="0" name="merma_kgs[<?=$mtId?>]" class="inptxt modal-merma-input" style="width:90px;padding:5px 8px;font-size:13px;font-weight:bold;text-align:right" value="0.00" oninput="calcModalMermaTotal()" placeholder="0.00">
                                    <span style="font-size:11.5px;color:#64748B">kg</span>
                                </div>
                                <div style="flex:1;min-width:180px">
                                    <input type="text" name="merma_comm[<?=$mtId?>]" class="inptxt" style="width:100%;padding:5px 8px;font-size:12px" placeholder="Motivo / observación (opcional)...">
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: SOBRANTE DE BOBINA (CON GENERACIÓN DE ETIQUETA) -->
                    <div style="background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:8px;padding:14px;margin-bottom:16px">
                        <label style="display:block;font-size:13.5px;font-weight:bold;color:#166534;margin-bottom:4px">
                            <i class="fa fa-box-open" style="color:#059669"></i> Sobrante de Bobina (kg)
                        </label>
                        <div style="font-size:12px;color:#15803D;margin-bottom:8px">
                            Kilos de tela que quedaron sin utilizar en el cono / eje (dejar en 0 si se consumió por completo).
                        </div>
                        <div style="display:flex;align-items:center;gap:10px">
                            <input type="number" step="0.01" min="0" name="leftover_weight" id="modal_leftover_kg" class="inptxt" style="width:160px;padding:8px 12px;font-size:15px;font-weight:bold;color:#065F46;border:2px solid #10B981;border-radius:6px" value="0.00" placeholder="0.00">
                            <span style="font-size:13px;font-weight:bold;color:#15803D">kg sobrante</span>
                        </div>
                        <div style="margin-top:10px;background:#DCFCE7;border:1px solid #86EFAC;border-radius:6px;padding:8px 12px;font-size:12px;color:#14532D;line-height:1.4">
                            <i class="fa fa-tag" style="color:#059669"></i> <b>Generación automática de Etiqueta:</b> Si ingresa un sobrante mayor a 0 kg, el sistema creará inmediatamente un <b>nuevo código de bobina</b> con trazabilidad en <b>Bodega 3000</b> y habilitará su <b>etiqueta para impresión Zebra (100×70mm)</b>.
                        </div>
                        <div id="modal_leftover_mount_note" style="display:none;margin-top:10px;background:#DBEAFE;border:1px solid #93C5FD;border-radius:6px;padding:8px 12px;font-size:12px;color:#1D4ED8;line-height:1.4">
                            <i class="fa fa-info-circle"></i> El remanente quedará montado en máquina. En este modo no se generará un código nuevo de sobrante en Bodega 3000.
                        </div>
                    </div>

                    <!-- SECCIÓN 3: OBSERVACIONES GENERALES -->
                    <div style="margin-bottom:18px">
                        <label style="display:block;font-size:12.5px;font-weight:bold;color:#334155;margin-bottom:4px">
                            Observaciones de Desmontaje / Turno (Opcional):
                        </label>
                        <input type="text" name="comments" class="inptxt" style="width:100%;padding:7px 10px;font-size:12.5px" placeholder="Detalle adicional del desmontaje...">
                    </div>

                    <!-- MODAL FOOTER / BOTONES -->
                    <div style="display:flex;justify-content:flex-end;align-items:center;gap:10px;border-top:1px solid #E2E8F0;padding-top:14px">
                        <button type="button" onclick="closeRollOutputModal()" class="btngrey" style="font-size:13px;padding:8px 18px;cursor:pointer">
                            Cancelar
                        </button>
                        <button type="submit" class="btngreen" style="font-size:13px;padding:8px 22px;cursor:pointer;font-weight:bold;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 4px rgba(0,0,0,0.1)">
                            <i class="fa fa-check-circle"></i> 💾 Confirmar Desmontaje de Bobina
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function getModalCurrentEntryWeight() {
            var txt = document.getElementById('modal_roll_entry_txt');
            if (!txt) return 0;
            var raw = String(txt.innerText || '').replace(' kg', '').replace(',', '.');
            return parseFloat(raw) || 0;
        }

        function getSelectedMergeRolls() {
            var rolls = [];
            var rows = document.querySelectorAll('.modal-merge-roll-row');
            rows.forEach(function(row) {
                var checkbox = row.querySelector('.modal-merge-roll-checkbox');
                if (!checkbox || !checkbox.checked || checkbox.disabled) return;
                rolls.push({
                    id: parseInt(row.getAttribute('data-roll-id') || '0', 10) || 0,
                    code: String(row.getAttribute('data-roll-code') || ''),
                    weight: parseFloat(String(row.getAttribute('data-roll-weight') || '').replace(',', '.')) || 0
                });
            });
            return rolls;
        }

        function rebuildRemainingMountedOptions() {
            var select = document.getElementById('modal_remaining_mounted_roll_id');
            var currentRollId = parseInt(document.getElementById('modal_roll_id').value || '0', 10) || 0;
            var currentRollCode = String(document.getElementById('modal_roll_code_txt').innerText || '').trim();
            var currentEntryWeight = getModalCurrentEntryWeight();
            if (!select) return;

            var currentValue = parseInt(select.value || '0', 10) || 0;
            var options = [
                {
                    id: currentRollId,
                    code: currentRollCode,
                    weight: currentEntryWeight
                }
            ].concat(getSelectedMergeRolls());

            select.innerHTML = '';
            if (options.length === 0) {
                select.innerHTML = '<option value="0">Seleccione bobina</option>';
                return;
            }
            options.forEach(function(item) {
                if (!item || !item.id) return;
                var opt = document.createElement('option');
                opt.value = String(item.id);
                opt.text = item.code + ' (' + item.weight.toFixed(2).replace('.', ',') + ' kg)';
                select.appendChild(opt);
            });
            if (currentValue > 0) {
                select.value = String(currentValue);
            }
            if (!select.value && options.length > 0) {
                select.value = String(options[0].id);
            }
        }

        function recalcRemainingMountedWeight() {
            var section = document.getElementById('modal_remaining_mounted_section');
            if (!section || section.style.display === 'none') return;

            var currentEntryWeight = getModalCurrentEntryWeight();
            var selectedMergeRolls = getSelectedMergeRolls();
            var totalEntry = currentEntryWeight;
            selectedMergeRolls.forEach(function(item) { totalEntry += item.weight || 0; });

            var outputKg = parseFloat(String((document.getElementById('modal_output_bobina_kg') || {}).value || '').replace(',', '.')) || 0;
            var scrapKg = parseFloat(String((document.getElementById('modal_scrap_weight') || {}).value || '').replace(',', '.')) || 0;
            var remainingKg = Math.max(0, totalEntry - outputKg - scrapKg);

            var remainInput = document.getElementById('modal_remaining_mounted_kg');
            if (remainInput) remainInput.value = remainingKg.toFixed(2);

            var formula = document.getElementById('modal_remaining_formula');
            if (formula) {
                formula.innerHTML = 'Cálculo: <b>' + totalEntry.toFixed(2).replace('.', ',')
                    + '</b> kg entrada total - <b>' + outputKg.toFixed(2).replace('.', ',')
                    + '</b> kg salida - <b>' + scrapKg.toFixed(2).replace('.', ',')
                    + '</b> kg mermas = <b>' + remainingKg.toFixed(2).replace('.', ',')
                    + '</b> kg que seguirá montado.';
            }
        }

        function toggleRemainingMountedSection() {
            var chk = document.getElementById('modal_keep_remaining_mounted');
            var box = document.getElementById('modal_remaining_mounted_section');
            var leftoverInput = document.getElementById('modal_leftover_kg');
            var leftoverNote = document.getElementById('modal_leftover_mount_note');
            if (!chk || !box) return;

            box.style.display = chk.checked ? 'block' : 'none';
            if (leftoverInput) {
                if (chk.checked) {
                    leftoverInput.value = '0.00';
                    leftoverInput.readOnly = true;
                    leftoverInput.style.background = '#F1F5F9';
                    leftoverInput.style.opacity = '0.85';
                } else {
                    leftoverInput.readOnly = false;
                    leftoverInput.style.background = '#FFFFFF';
                    leftoverInput.style.opacity = '1';
                }
            }
            if (leftoverNote) {
                leftoverNote.style.display = chk.checked ? 'block' : 'none';
            }
            rebuildRemainingMountedOptions();
            recalcRemainingMountedWeight();
        }

        function resetMergeRollSelection(currentRollId) {
            var rows = document.querySelectorAll('.modal-merge-roll-row');
            rows.forEach(function(row) {
                var rowRollId = parseInt(row.getAttribute('data-roll-id') || '0', 10);
                var checkbox = row.querySelector('.modal-merge-roll-checkbox');
                var hint = row.querySelector('.modal-merge-roll-hint');
                if (!checkbox) return;
                checkbox.checked = false;
                if (rowRollId === currentRollId) {
                    checkbox.disabled = true;
                    row.style.opacity = '0.65';
                    row.style.background = '#E2E8F0';
                    if (hint) hint.innerText = 'Bobina principal';
                } else {
                    checkbox.disabled = false;
                    row.style.opacity = '1';
                    row.style.background = '#FFFFFF';
                    if (hint) hint.innerText = 'Sumar a esta salida';
                }
            });
            updateMergeRollSelection();
        }

        function updateMergeRollSelection() {
            var count = 0;
            var checkboxes = document.querySelectorAll('.modal-merge-roll-checkbox');
            checkboxes.forEach(function(checkbox) {
                if (checkbox.checked && !checkbox.disabled) {
                    count++;
                }
            });
            var badge = document.getElementById('modal_merge_count_badge');
            if (badge) {
                badge.innerText = 'Adicionales: ' + count;
            }
            rebuildRemainingMountedOptions();
            recalcRemainingMountedWeight();
        }

        function openRollOutputModal(rollId, rollCode, entryKg) {
            document.getElementById('modal_roll_id').value = rollId;
            document.getElementById('modal_roll_code_txt').innerText = rollCode;
            document.getElementById('modal_roll_entry_txt').innerText = Number(entryKg).toFixed(2) + ' kg';
            document.getElementById('modal_leftover_kg').value = '0.00';
            var outputKg = document.getElementById('modal_output_bobina_kg');
            if (outputKg) outputKg.value = '0.00';
            var outputMeters = document.getElementById('modal_output_meters');
            if (outputMeters) outputMeters.value = '<?=$printerOutputSuggestedMeters > 0 ? (int)$printerOutputSuggestedMeters : 0?>';
            var outputSku = document.getElementById('modal_output_sku_code');
            if (outputSku) outputSku.value = <?=json_encode($printerOutputDefaultSku, JSON_UNESCAPED_UNICODE)?>;
            var createChk = document.getElementById('modal_create_output_roll');
            if (createChk) createChk.checked = true;
            var keepMountedChk = document.getElementById('modal_keep_remaining_mounted');
            if (keepMountedChk) keepMountedChk.checked = false;
            toggleOutputRollSection();
            recalcModalOutputMeters();
            resetMergeRollSelection(parseInt(rollId, 10) || 0);
            toggleRemainingMountedSection();
            
            // Reset mermas
            var mInputs = document.querySelectorAll('.modal-merma-input');
            mInputs.forEach(function(inp) { inp.value = '0.00'; });
            calcModalMermaTotal();
            
            var modal = document.getElementById('modal_roll_output');
            modal.style.display = 'flex';
            setTimeout(function() {
                var lInp = document.getElementById('modal_leftover_kg');
                if (lInp) lInp.focus();
            }, 100);
        }

        function closeRollOutputModal() {
            var modal = document.getElementById('modal_roll_output');
            if (modal) modal.style.display = 'none';
        }

        function calcModalMermaTotal() {
            var total = 0;
            var mInputs = document.querySelectorAll('.modal-merma-input');
            mInputs.forEach(function(inp) {
                var val = parseFloat(String(inp.value).replace(',', '.')) || 0;
                total += val;
            });
            var hidScrap = document.getElementById('modal_scrap_weight');
            if (hidScrap) hidScrap.value = total.toFixed(2);
            var badge = document.getElementById('modal_total_merma_badge');
            if (badge) badge.innerText = 'Total Mermas: ' + total.toFixed(2) + ' kg';
            recalcRemainingMountedWeight();
        }

        function toggleOutputRollSection() {
            var chk = document.getElementById('modal_create_output_roll');
            var box = document.getElementById('modal_output_roll_section');
            if (!chk || !box) return;
            box.style.display = chk.checked ? 'block' : 'none';
        }

        var MODAL_OUTPUT_METERS_PER_KG = <?=json_encode((float)$printerMetersPerKg)?>;
        function recalcModalOutputMeters() {
            var kgInput = document.getElementById('modal_output_bobina_kg');
            var metersInput = document.getElementById('modal_output_meters');
            var calcText = document.getElementById('modal_output_calc_text');
            if (!kgInput || !metersInput) return;

            var kg = parseFloat(String(kgInput.value || '').replace(',', '.')) || 0;
            var meters = 0;
            if (kg > 0 && MODAL_OUTPUT_METERS_PER_KG > 0) {
                meters = Math.max(1, Math.round(kg * MODAL_OUTPUT_METERS_PER_KG));
            }
            metersInput.value = meters > 0 ? String(meters) : '0';
            if (calcText) {
                calcText.innerText = (meters > 0 ? meters : 0) + ' m aprox.';
            }
            recalcRemainingMountedWeight();
        }

        function validateRollOutputModal() {
            var selectedMergeCount = 0;
            var mergeCheckboxes = document.querySelectorAll('.modal-merge-roll-checkbox');
            mergeCheckboxes.forEach(function(checkbox) {
                if (checkbox.checked && !checkbox.disabled) {
                    selectedMergeCount++;
                }
            });
            var createChk = document.getElementById('modal_create_output_roll');
            if (selectedMergeCount > 0 && (!createChk || !createChk.checked)) {
                alert('Para convertir varias bobinas en una sola, debe generar una bobina saliente con etiqueta.');
                return false;
            }
            if (createChk && createChk.checked) {
                var outputKg = parseFloat(String(document.getElementById('modal_output_bobina_kg').value || '').replace(',', '.')) || 0;
                if (outputKg <= 0) {
                    alert('Debes ingresar el peso de la nueva bobina saliente para generar su etiqueta.');
                    return false;
                }
            }
            var keepMountedChk = document.getElementById('modal_keep_remaining_mounted');
            if (keepMountedChk && keepMountedChk.checked) {
                var keepRollId = parseInt(String((document.getElementById('modal_remaining_mounted_roll_id') || {}).value || '0'), 10) || 0;
                var keepWeight = parseFloat(String((document.getElementById('modal_remaining_mounted_kg') || {}).value || '').replace(',', '.')) || 0;
                if (keepRollId <= 0) {
                    alert('Debe seleccionar qué bobina quedará montada para el siguiente tiraje.');
                    return false;
                }
                if (keepWeight <= 0) {
                    alert('El cálculo del peso que quedará montado dio 0 kg. Revise el peso de salida o las mermas antes de guardar.');
                    return false;
                }
            }
            var confirmMessage = selectedMergeCount > 0
                ? '¿Confirmar registro de desmontaje y unir esta salida con ' + selectedMergeCount + ' bobina(s) adicional(es)?'
                : '¿Confirmar registro de desmontaje y cierre de esta bobina?';
            return confirm(confirmMessage);
        }

        // Cerrar con Escape
        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeRollOutputModal();
            }
        });
        </script>
        <?php endif; ?>

    <?php elseif ($mode === 'consumo'): ?>
        <!-- Redirigir a modo materiales para gestionar montaje y salida de bobinas con trazabilidad -->
        <script>location.href = '/production/work-orders/operate?agid=<?=$agId?>&mode=materiales';</script>
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px;text-align:center">
            <p>Redirigiendo a <b>Montaje y Salida de Bobinas</b>...</p>
            <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=materiales" class="btnblue">Ir a Bobinas y Materiales</a>
        </div>

    <?php elseif ($mode === 'reboprod'): ?>
        <!-- SUBFORMULARIO: Rebobinado (form.reboprod.php) -->
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-chevron-left" style="color:white;"></i> Volver&nbsp;
            </div>
        </div>

        <?php if (!empty($_GET['rebo_done'])): ?>
            <div style="background:#E8F8F5;border:1.5px solid #2ECC71;color:#117A65;padding:16px 20px;border-radius:6px;margin-bottom:18px;font-size:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div>
                    <i class="fa fa-fw fa-check-circle" style="font-size:20px;color:#27AE60"></i>
                    <b>¡Producción de corte / rebobinado procesada exitosamente!</b> Se generaron <b><?= (int)($_GET['cuts'] ?? 0) ?></b> bobinas hijas fraccionadas registradas con trazabilidad.
                    <?php if (!empty($_GET['handle_rolls'])): ?>
                        <span> Además se consolidó un <b>pallet de manillas</b> con <b><?= (int)$_GET['handle_rolls'] ?></b> rollos.</span>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if (!empty($_GET['first_cut'])): ?>
                        <a href="/rolls/<?= (int)$_GET['first_cut'] ?>/label?server_print=1" target="_blank" class="btnblue" style="text-decoration:none;font-weight:bold">
                            <i class="fa fa-print"></i> Imprimir Etiqueta Bobina (100×70mm)
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($_GET['pallet_id'])): ?>
                        <a href="/pallets/<?= (int)$_GET['pallet_id'] ?>/label?server_print=1" target="_blank" class="btngreen" style="text-decoration:none;font-weight:bold;margin-left:8px">
                            <i class="fa fa-pallet"></i> Imprimir Etiqueta Pallet Manillas
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php
        $reboType = trim((string)($reboOrder['req_rebo_type'] ?? ($agenda['req_rebo_type'] ?? 'Corte')));
        $isSoloManillas = $prodService->isReboSoloManillas($agenda, $reboOrder, $subproducts);
        if (isset($_GET['case'])) {
            $isSoloManillas = ($_GET['case'] === 'manillas');
        }
        $itemsel = $reboItemSel1;
        $lidx = 1;
        ?>

        <!-- BANNER DE CASO OPERATIVO: CASO 1 (BOLSAS POR CORTAR) vs CASO 2 (SOLO MANILLAS) -->
        <div style="background:#FFFFFF;border:1px solid #D5D8DC;border-radius:6px;padding:14px 18px;margin-bottom:15px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
            <div style="display:flex;align-items:center;gap:12px">
                <?php if ($isSoloManillas): ?>
                    <span style="background:#1B4F72;color:#FFFFFF;padding:6px 14px;border-radius:4px;font-weight:bold;font-size:13px;letter-spacing:0.5px">
                        <i class="fa fa-cut"></i> CASO 2: SOLO MANILLAS
                    </span>
                    <span style="font-size:13px;color:#2C3E50">
                        Corte longitudinal exclusivo para tiras de manillas. El operario trabaja únicamente con las bobinas solicitadas para esta orden con trazabilidad (ver tabla inferior) o puede <b>escanear directamente</b> la bobina entregada.
                    </span>
                <?php else: ?>
                    <span style="background:#6C3483;color:#FFFFFF;padding:6px 14px;border-radius:4px;font-weight:bold;font-size:13px;letter-spacing:0.5px">
                        <i class="fa fa-shopping-bag"></i> CASO 1: BOLSAS POR CORTAR
                    </span>
                    <span style="font-size:13px;color:#2C3E50">
                        Corte para cuerpos de bolsa. Bodega entrega la bobina física en máquina. <b>Escanee el código de barra o QR</b> en el lector para ingresarla al proceso con trazabilidad completa.
                    </span>
                <?php endif; ?>
            </div>
            <div style="font-size:12px">
                <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod&case=<?=$isSoloManillas ? 'bolsas' : 'manillas'?>" style="color:#7F8C8D;text-decoration:underline">
                    Cambiar vista a <?= $isSoloManillas ? 'Bolsas por cortar' : 'Solo manillas' ?>
                </a>
            </div>
        </div>

        <!-- MÓDULO SCANNER DE BOBINA (CÓDIGO DE BARRA / QR) -->
        <div style="background:#EBF5FB;border:2px dashed #2980B9;border-radius:6px;padding:14px 18px;margin-bottom:15px">
            <form action="/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod" method="post" style="margin:0;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="mode" value="reboprod">
                <div style="font-size:22px;color:#2980B9">
                    <i class="fa fa-barcode"></i> <i class="fa fa-qrcode"></i>
                </div>
                <div style="font-weight:bold;color:#1B4F72;font-size:14px">
                    Lector de Bobina:
                </div>
                <input type="text" name="scan_rebo_code" id="scan_rebo_code" autofocus
                       placeholder="Escanee código de barra o QR (Ej: TEL0165, BTE0075, R-XXXX)..."
                       style="flex:1;min-width:280px;padding:9px 14px;font-size:15px;border:1px solid #2980B9;border-radius:4px;font-weight:bold;background:#FFFFFF"
                       onkeydown="if(event.key === 'Enter'){ this.form.submit(); }">
                <button type="submit" class="btngreen" style="padding:9px 18px;font-size:14px;font-weight:bold;cursor:pointer;border:none;border-radius:4px">
                    <i class="fa fa-search"></i> Escanear / Cargar Bobina
                </button>
            </form>
        </div>

        <?php if (!empty($_GET['scan_ok'])): ?>
            <div style="background:#D4EFDF;border:1px solid #27AE60;color:#145A32;padding:12px 16px;border-radius:6px;margin-bottom:15px;font-size:14px">
                <i class="fa fa-check-circle"></i> <b>Bobina escaneada con éxito:</b> '<?=htmlspecialchars($_GET['scanned_code'] ?? '')?>' cargada en ENTRADA REBOBINADORA con trazabilidad activa.
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['scan_err'])): ?>
            <div style="background:#FADBD8;border:1px solid #E74C3C;color:#78281F;padding:12px 16px;border-radius:6px;margin-bottom:15px;font-size:14px">
                <i class="fa fa-exclamation-triangle"></i> <b>Error al escanear bobina:</b> <?=htmlspecialchars($_GET['scan_err'])?>
            </div>
        <?php endif; ?>

        <?php if ($itemsel !== null): ?>
            <?php
            // Cálculos de entrada de bobina
            $rawIngresoKg = isset($_REQUEST["ingresokgamt_{$lidx}"]) ? trim((string)$_REQUEST["ingresokgamt_{$lidx}"]) : '';
            if ($rawIngresoKg === '') {
                $rawIngresoKg = number_format((float)($itemsel['item_reg_kg'] ?? 0), 2, ',', '.');
            }
            $ingresoKg = (float)str_replace(',', '.', $rawIngresoKg);
            $rawSobrante = isset($_REQUEST["sobranteamt_{$lidx}"]) ? trim((string)$_REQUEST["sobranteamt_{$lidx}"]) : '0';
            $sobranteKg = (float)str_replace(',', '.', $rawSobrante);
            $useamt = max(0.0, $ingresoKg - $sobranteKg);
            $origusediffamt = (float)($itemsel['item_reg_kg'] ?? 0) - $useamt;
            $baseRegKg = (float)($itemsel['item_reg_kg'] ?? 1);
            if ($baseRegKg <= 0) $baseRegKg = 1.0;
            $baseLength = (float)($itemsel['item_reg_length'] ?? 0) - (($origusediffamt * (float)($itemsel['item_reg_length'] ?? 0)) / $baseRegKg);

            $rawMerma = isset($_REQUEST['itemmermakg']) ? trim((string)$_REQUEST['itemmermakg']) : '0';
            $itemMermaKg = (float)str_replace(',', '.', $rawMerma);
            $isReboCalc = !empty($_REQUEST['rebocalc']);
            ?>

            <script language="Javascript">
            function recalcUsedAmt(xlineid) {
                var itemregkg = parseFloat($('#ingresokgamt_' + xlineid).val().replace(/,/g, "."));
                var sobranteamt = parseFloat($('#sobranteamt_' + xlineid).val().replace(/,/g, "."));
                if (isNaN(sobranteamt)) sobranteamt = 0;
                if (isNaN(itemregkg)) itemregkg = 0;
                var useamt = itemregkg - sobranteamt;
                if (useamt < 0) useamt = 0;
                $('#useamt_' + xlineid).val(roundToDecimal(useamt, 2).toFixed(2).replace(/\./g, ","));
                $('#idx_btn_term').hide(0);
                $('#idx_btn_save').show(0);
            }
            function roundToDecimal(num, decimalPlaces) {
                const factor = Math.pow(10, decimalPlaces);
                return Math.round(num * factor) / factor;
            }
            function recalcTotalWidth() {}
            </script>

            <form action="/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod#xform_itemcalc" method="post" name="xform_itemcalc" id="xform_itemcalc">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="mode" value="reboprod">
                <input type="hidden" name="selitemid1" value="<?=$selItemId1?>">
                <input type="hidden" name="selitemid2" value="<?=$selItemId2?>">
                <input type="hidden" name="scanned_roll_id" value="<?=(int)($_REQUEST['roll_id'] ?? 0)?>">
                <input type="hidden" name="scanned_roll_code" value="<?=htmlspecialchars((string)($_REQUEST['scanned_code'] ?? ($itemsel['item_number_prod'] ?? '')))?>">
                <input type="hidden" name="req_rebo_type" value="<?=htmlspecialchars($reboType)?>">
                <input type="hidden" name="rebocalc" value="1">
                <input type="hidden" name="rebofinalize" value="0">

                <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
                <tr>
                    <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:0px">
                        <br>
                        <table border="0" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td colspan="2" style="background-color:#1AAAA4;color:#FFFFFF;padding:6px;font-weight:bold;text-align:center">
                                <i class="fa fa-fw fa-chevron-right"></i> ENTRADA REBOBINADORA
                            </td>
                        </tr>
                        </table>
                        <table border="0" width="100%" cellpadding="6" cellspacing="0">
                        <tr>
                            <td class="tdheader" width="100" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Código</td>
                            <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Materialidad</td>
                            <td align="left" class="tdheader" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Color</td>
                            <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Gramaje</td>
                            <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Ancho</td>
                            <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Largo</td>
                            <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Pesa</td>
                            <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Sobrante</td>
                            <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Usado</td>
                        </tr>
                        <tr>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;"><?=htmlspecialchars((string)$itemsel['item_number_prod'])?></td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=htmlspecialchars((string)$itemsel['material'])?></td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="left"><?=htmlspecialchars((string)$itemsel['colordesc'])?></td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($itemsel['item_reg_gsm'])?></td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($itemsel['item_reg_width'])?></td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($baseLength)?></td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                <input type="text" class="inptxt" id="ingresokgamt_<?=$lidx?>" name="ingresokgamt_<?=$lidx?>"
                                style="width:100%;text-align:center" onkeyup="recalcUsedAmt('<?=$lidx?>')"
                                value="<?=number_format($ingresoKg, 2, ',', '.')?>">
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                <input type="text" class="inptxt" id="sobranteamt_<?=$lidx?>" name="sobranteamt_<?=$lidx?>"
                                style="width:100%;text-align:center" onkeyup="recalcUsedAmt('<?=$lidx?>')"
                                value="<?=number_format($sobranteKg, 2, ',', '.')?>">
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                <input type="text" class="inptxt" id="useamt_<?=$lidx?>" name="useamt_<?=$lidx?>"
                                style="width:100%;text-align:center;background-color:#EEEEEE" readonly
                                value="<?=number_format($useamt, 2, ',', '.')?>">
                            </td>
                        </tr>
                        </table>

                        <table border="0" cellpadding="6" cellspacing="0" style="margin-top:8px">
                        <colgroup>
                            <col width="100">
                            <col width="100">
                            <col width="100">
                        </colgroup>
                        <tr>
                            <td width="100">Merma Kg</td>
                            <td width="100">
                                <input type="text" class="inptxt" name="itemmermakg" id="itemmermakg" style="width:100%;text-align:center"
                                onkeyup="$('#idx_btn_term').hide(0);$('#idx_btn_save').show(0);"
                                value="<?=$itemMermaKg > 0 ? number_format($itemMermaKg, 2, ',', '.') : ''?>">
                            </td>
                            <td>
                                <input type="button" class="btngrey" value="Calcular" style="padding:6px 14px;border:0px;font-size:14px;cursor:pointer"
                                onclick="document.xform_itemcalc.rebocalc.value='1'; document.xform_itemcalc.rebofinalize.value='0'; document.xform_itemcalc.submit();">
                            </td>
                        </tr>
                        </table>

                        <?php if ($reboType === 'Corte'): ?>
                            <!-- SALIDA REBOBINADORA: CORTE DE BOBINA (MANILLAS Y BOLSAS) -->
                            <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px">
                            <tr>
                                <td colspan="2" style="background-color:#1AAAA4;color:#FFFFFF;padding:6px;font-weight:bold;text-align:center">
                                    <i class="fa fa-fw fa-chevron-left"></i> SALIDA REBOBINADORA
                                </td>
                            </tr>
                            </table>

                            <table border="0" width="100%" cellpadding="6" cellspacing="0">
                            <tr>
                                <td class="tdheader" width="100" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Cantidad</td>
                                <td class="tdheader" width="100" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Código</td>
                                <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Materialidad</td>
                                <td align="left" class="tdheader" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Color</td>
                                <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Gramaje</td>
                                <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Ancho</td>
                                <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Total</td>
                                <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Unit.</td>
                                <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Merma</td>
                                <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Largo</td>
                            </tr>
                            <?php
                            $cutsList = [];
                            if (!empty($reboValues['rollo'])) {
                                foreach ($reboValues['rollo'] as $rv) {
                                    $cutsList[] = [
                                        'rollo_amt' => (float)($rv['rollo_amt'] ?? 1),
                                        'rollo_dims' => (float)($rv['rollo_dims'] ?? 0),
                                        'destino' => (string)($rv['subprod_destino'] ?? ''),
                                    ];
                                }
                            } elseif (!empty($reboValues['metro'])) {
                                foreach ($reboValues['metro'] as $rv) {
                                    $cutsList[] = [
                                        'rollo_amt' => (float)($rv['rollo_amt'] ?? 1),
                                        'rollo_dims' => (float)($rv['rollo_dims'] ?? 0),
                                        'destino' => (string)($rv['subprod_destino'] ?? ''),
                                    ];
                                }
                            } elseif (!empty($subproducts)) {
                                foreach ($subproducts as $sp) {
                                    $cutsList[] = [
                                        'rollo_amt' => (float)($sp['subprod_amount'] ?? 1),
                                        'rollo_dims' => (float)($sp['subprod_ancho_cm'] ?? 0),
                                        'destino' => (string)($sp['subprod_destino'] ?? ''),
                                    ];
                                }
                            }
                            if (empty($cutsList)) {
                                $cutsList[] = [
                                    'rollo_amt' => 1,
                                    'rollo_dims' => (float)($itemsel['item_reg_width'] ?? 100),
                                    'destino' => '',
                                ];
                            }

                            $x = 0;
                            $_TOTAL_WIDTH = 0.0;
                            $rollWidth = (float)($itemsel['item_reg_width'] ?? 100);
                            if ($rollWidth <= 0) $rollWidth = 100.0;

                            foreach ($cutsList as $reboval):
                                $cReqAmt = isset($_REQUEST["reboamt_{$x}"]) ? (int)$_REQUEST["reboamt_{$x}"] : (int)$reboval['rollo_amt'];
                                $cReqDims = isset($_REQUEST["rollo_dims_{$x}"]) ? (float)str_replace(',', '.', (string)$_REQUEST["rollo_dims_{$x}"]) : (float)$reboval['rollo_dims'];
                                $cDestino = trim((string)($_REQUEST["rebo_destino_{$x}"] ?? ($reboval['destino'] ?? '')));

                                $_TOTAL_WIDTH += ($cReqAmt * $cReqDims);

                                $_LARGO = (float)($itemsel['item_reg_length'] ?? 0)
                                    - (($itemMermaKg * (float)($itemsel['item_reg_length'] ?? 0)) / $baseRegKg)
                                    - (($sobranteKg * (float)($itemsel['item_reg_length'] ?? 0)) / $baseRegKg)
                                    - (($origusediffamt * (float)($itemsel['item_reg_length'] ?? 0)) / $baseRegKg);

                                $_KG = (($cReqDims * ((float)($itemsel['item_reg_kg'] ?? 0) - $sobranteKg - $itemMermaKg - $origusediffamt)) / $rollWidth) * $cReqAmt;
                                if ($_KG < 0) $_KG = 0;
                                $unitKg = $cReqAmt > 0 ? ($_KG / $cReqAmt) : 0;
                            ?>
                                <input type="hidden" name="stockplustotalkg_<?=$x?>" value="<?=round($_KG, 2)?>">
                                <input type="hidden" name="stockpluslargo_<?=$x?>" value="<?=round($_LARGO, 2)?>">
                                <input type="hidden" name="stockplusitemid_<?=$x?>" value="">
                                <input type="hidden" name="rebo_destino_<?=$x?>" value="<?=htmlspecialchars($cDestino)?>">
                                <tr>
                                    <td class="tdnrm" style="border-left:1px solid #DDDDDD;">
                                        <input type="text" class="inptxt clsreboamt" style="width:100%;text-align:center"
                                        name="reboamt_<?=$x?>" value="<?=$cReqAmt?>"
                                        onkeyup="$('#idx_btn_term').hide(0);$('#idx_btn_save').show(0);recalcTotalWidth();">
                                    </td>
                                    <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="left">
                                        <b style="color:#007BFF">[<?=htmlspecialchars($cDestino !== '' ? mb_strtoupper($cDestino) : 'CORTE')?>]</b>
                                    </td>
                                    <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=htmlspecialchars((string)$itemsel['material'])?></td>
                                    <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="left"><?=htmlspecialchars((string)$itemsel['colordesc'])?></td>
                                    <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($itemsel['item_reg_gsm'])?></td>
                                    <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                        <input type="text" class="inptxt" id="rollo_dims_<?=$x?>" name="rollo_dims_<?=$x?>"
                                        style="width:100%;text-align:center"
                                        onkeyup="$('#idx_btn_term').hide(0);$('#idx_btn_save').show(0);recalcTotalWidth();"
                                        value="<?=number_format($cReqDims, 2, ',', '.')?>">
                                    </td>
                                    <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=number_format($_KG, 2, ',', '.')?></td>
                                    <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=number_format($unitKg, 2, ',', '.')?></td>
                                    <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=number_format($itemMermaKg / max(1, count($cutsList)), 2, ',', '.')?></td>
                                    <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($_LARGO)?></td>
                                </tr>
                            <?php
                                $x++;
                            endforeach;
                            ?>
                            </table>

                            <table border="0" cellpadding="6" cellspacing="0" style="margin-top:8px">
                            <tr>
                                <td width="100" height="41"></td>
                                <td width="120">
                                    <?php
                                    $widthMatches = round($_TOTAL_WIDTH, 1) == round((float)($itemsel['item_reg_width'] ?? 0), 1);
                                    ?>
                                    <input type="button" class="btngreen" id="idx_btn_term" value="Finalizar"
                                    style="<?=$widthMatches ? '' : 'display:none;'?>padding:8px 16px;border:0px;font-size:14px;font-weight:bold;cursor:pointer;border-radius:4px"
                                    onclick="openReboFinalizeModal('corte');">

                                    <input type="button" class="btngrey" id="idx_btn_save" value="Actualizar"
                                    style="<?=$widthMatches ? 'display:none;' : ''?>width:100%;padding:6px;border:0px;font-size:14px;cursor:pointer"
                                    onclick="document.xform_itemcalc.rebofinalize.value='0'; document.xform_itemcalc.submit();">
                                </td>
                                <td width="350" style="color:<?=$widthMatches ? 'green' : 'red'?>;font-size:12px">
                                    <?=$widthMatches ? '✓ Ancho distribuido coincide con el ancho de bobina' : '⚠ El ancho total sumado debe ser igual al ancho de la bobina entrante (' . $fmt($itemsel['item_reg_width']) . ' cm)'?>
                                </td>
                                <td width="100" class="tdnrm" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD;" align="center">
                                    <b id="idx_total_width"><?=number_format($_TOTAL_WIDTH, 2, ',', '.')?> cm</b>
                                </td>
                            </tr>
                            </table>

                        <?php else: ?>
                            <!-- SALIDA REBOBINADORA: SOLO REBOBINADO (NO CONSUME LA BOBINA) -->
                            <?php
                            $_LARGO = (float)($itemsel['item_reg_length'] ?? 0)
                                - (($itemMermaKg * (float)($itemsel['item_reg_length'] ?? 0)) / $baseRegKg)
                                - (($sobranteKg * (float)($itemsel['item_reg_length'] ?? 0)) / $baseRegKg)
                                - (($origusediffamt * (float)($itemsel['item_reg_length'] ?? 0)) / $baseRegKg);
                            $salidaKg = max(0.0, $useamt - $itemMermaKg);
                            ?>
                            <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px">
                            <tr>
                                <td colspan="2" style="background-color:#1AAAA4;color:#FFFFFF;padding:6px;font-weight:bold;text-align:center">
                                    <i class="fa fa-fw fa-chevron-left"></i> SALIDA REBOBINADORA (SOLO REBOBINADO)
                                </td>
                            </tr>
                            </table>
                            <table border="0" width="100%" cellpadding="6" cellspacing="0">
                            <tr>
                                <td class="tdheader" width="100" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Código</td>
                                <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Materialidad</td>
                                <td align="left" class="tdheader" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Color</td>
                                <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Gramaje</td>
                                <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Ancho</td>
                                <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg</td>
                                <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Merma</td>
                                <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Salida</td>
                                <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Largo</td>
                            </tr>
                            <tr>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;"><b><?=htmlspecialchars((string)$itemsel['item_number_prod'])?></b></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=htmlspecialchars((string)$itemsel['material'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="left"><?=htmlspecialchars((string)$itemsel['colordesc'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($itemsel['item_reg_gsm'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($itemsel['item_reg_width'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=number_format($useamt, 2, ',', '.')?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=number_format($itemMermaKg, 2, ',', '.')?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=number_format($salidaKg, 2, ',', '.')?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($_LARGO)?></td>
                            </tr>
                            </table>

                            <table border="0" cellpadding="6" cellspacing="0" style="margin-top:8px">
                            <tr>
                                <td width="100">
                                    <input type="button" class="btngreen" id="idx_btn_term" value="Finalizar"
                                    style="width:100%;padding:6px;border:0px;font-size:14px;cursor:pointer"
                                    onclick="openReboFinalizeModal('rebobinado');">
                                </td>
                            </tr>
                            </table>
                        <?php endif; ?>
                    </td>
                </tr>
                </table>
            </form>

            <div id="rebo_finalize_modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:99998;align-items:center;justify-content:center;padding:18px;box-sizing:border-box">
                <div style="background:#FFFFFF;border-radius:10px;box-shadow:0 20px 35px rgba(0,0,0,0.28);width:100%;max-width:560px;border:1px solid #CBD5E1;overflow:hidden">
                    <div style="background:#0F766E;color:#FFFFFF;padding:14px 18px;font-size:16px;font-weight:bold;display:flex;align-items:center;gap:10px">
                        <i class="fa fa-check-circle"></i> Confirmar finalización
                    </div>
                    <div style="padding:20px 18px;color:#334155;font-size:14px;line-height:1.6">
                        <div id="rebo_finalize_modal_message">
                            ¿Desea continuar con este registro?
                        </div>
                    </div>
                    <div style="padding:0 18px 18px;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap">
                        <button type="button" class="btngrey" style="padding:8px 18px;border:0;cursor:pointer" onclick="closeReboFinalizeModal()">Cancelar</button>
                        <button type="button" class="btngreen" style="padding:8px 18px;border:0;cursor:pointer" onclick="submitReboFinalize()">Sí, finalizar</button>
                    </div>
                </div>
            </div>
            <script>
            (function () {
                var reboFinalizeKind = '';
                window.openReboFinalizeModal = function (kind) {
                    reboFinalizeKind = kind || 'corte';
                    var message = document.getElementById('rebo_finalize_modal_message');
                    if (message) {
                        if (reboFinalizeKind === 'rebobinado') {
                            message.innerHTML = 'Se consumirá la bobina de inventario y quedará registrada en el informe oficial de consumo. ¿Desea finalizar el rebobinado ahora?';
                        } else {
                            message.innerHTML = 'Se consumirá la bobina del inventario, se darán de alta los rollos cortados y, si existen manillas, se generará además su pallet con etiqueta. ¿Desea finalizar el corte ahora?';
                        }
                    }
                    var modal = document.getElementById('rebo_finalize_modal');
                    if (modal) {
                        modal.style.display = 'flex';
                    }
                };
                window.closeReboFinalizeModal = function () {
                    var modal = document.getElementById('rebo_finalize_modal');
                    if (modal) {
                        modal.style.display = 'none';
                    }
                };
                window.submitReboFinalize = function () {
                    if (!document.xform_itemcalc) return;
                    document.xform_itemcalc.rebofinalize.value = '1';
                    document.xform_itemcalc.submit();
                };
                window.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        window.closeReboFinalizeModal();
                    }
                });
            })();
            </script>
        <?php endif; ?>

        <!-- TABLA: BOBINAS ESPECÍFICAMENTE SOLICITADAS PARA ESTA ORDEN (TRAZABILIDAD) -->
        <?php if ($isSoloManillas || !empty($reboRequestedRolls)): ?>
            <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
            <tr>
                <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:0px">
                    <table border="0" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="background-color:#1B4F72;color:#FFFFFF;padding:10px 14px;font-weight:bold;font-size:14px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                                <div>
                                    <i class="fa fa-fw fa-clipboard-list"></i> BOBINAS SOLICITADAS PARA ESTA ORDEN (TRAZABILIDAD)
                                </div>
                                <div>
                                    <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=materiales" class="btngreen" style="text-decoration:none;padding:6px 14px;font-size:12px;font-weight:bold;border-radius:4px;display:inline-block">
                                        <i class="fa fa-dolly"></i> Solicitar Bobina a Bodega
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    </table>
                    <table border="0" width="100%" cellpadding="8" cellspacing="0">
                    <tr>
                        <td class="tdheader" width="160" style="color:white;background-color:#2C3E50;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Código Bobina / Rollo</td>
                        <td align="left" class="tdheader" style="color:white;background-color:#2C3E50;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">SKU / Materialidad</td>
                        <td align="left" class="tdheader" width="140" style="color:white;background-color:#2C3E50;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Color</td>
                        <td align="center" class="tdheader" width="90" style="color:white;background-color:#2C3E50;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Ancho (cm)</td>
                        <td align="center" class="tdheader" width="100" style="color:white;background-color:#2C3E50;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Peso (Kg)</td>
                        <td align="center" class="tdheader" width="90" style="color:white;background-color:#2C3E50;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Metros</td>
                        <td align="center" class="tdheader" width="110" style="color:white;background-color:#2C3E50;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Estado</td>
                        <td align="center" class="tdheader" width="140" style="color:white;background-color:#2C3E50;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Acción</td>
                    </tr>
                    <?php if (empty($reboRequestedRolls)): ?>
                        <tr>
                            <td colspan="8" align="center" style="padding:28px 15px;color:#7F8C8D;font-size:13px">
                                <i class="fa fa-info-circle fa-2x" style="color:#BDC3C7;margin-bottom:8px;display:block"></i>
                                Aún no se han solicitado bobinas para esta orden de trabajo.<br>
                                Para cortar manillas con trazabilidad, solicite una bobina con el botón <b>"Solicitar Bobina a Bodega"</b> o <b>escanee directamente</b> el código de la bobina en el lector superior.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reboRequestedRolls as $rr):
                            $isCurrent = (!empty($itemsel) && (
                                ($rr['erp_item_id'] > 0 && (int)$itemsel['id'] === (int)$rr['erp_item_id']) ||
                                (!empty($_REQUEST['scanned_code']) && $_REQUEST['scanned_code'] === $rr['roll_code'])
                            ));
                            $rowBg = $isCurrent ? 'background-color:#FFF5A0;' : '';
                        ?>
                            <tr style="<?=$rowBg?>">
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;<?=$rowBg?>">
                                    <b style="font-size:14px;color:#1B4F72"><?=htmlspecialchars((string)$rr['roll_code'])?></b>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;<?=$rowBg?>" align="left">
                                    <b><?=htmlspecialchars((string)$rr['sku_code'])?></b> - <?=htmlspecialchars((string)$rr['material'])?>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;<?=$rowBg?>" align="left">
                                    <?=htmlspecialchars((string)$rr['colordesc'])?>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;<?=$rowBg?>" align="center">
                                    <?=number_format((float)$rr['width_cm'], 1, ',', '.')?>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;<?=$rowBg?>" align="center">
                                    <b><?=number_format((float)$rr['weight_kg'], 2, ',', '.')?> Kg</b>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;<?=$rowBg?>" align="center">
                                    <?=number_format((float)$rr['meters'], 0, ',', '.')?> m
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;<?=$rowBg?>" align="center">
                                    <span style="background:#D4EFDF;color:#196F3D;padding:3px 8px;border-radius:3px;font-size:11px;font-weight:bold">
                                        <?=htmlspecialchars((string)$rr['status'])?>
                                    </span>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;<?=$rowBg?>" align="center">
                                    <?php if ($isCurrent): ?>
                                        <span style="color:#27AE60;font-weight:bold;font-size:13px">
                                            <i class="fa fa-check-circle"></i> Cargada
                                        </span>
                                    <?php else: ?>
                                        <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod&scan_rebo_code=<?=urlencode($rr['roll_code'])?>"
                                           class="btnblue" style="text-decoration:none;padding:5px 12px;font-size:12px;font-weight:bold;border-radius:4px;display:inline-block">
                                            <i class="fa fa-arrow-up"></i> Cargar a Entrada
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </table>
                </td>
            </tr>
            </table>
        <?php endif; ?>

        <!-- TABLA: PRODUCCIÓN FINALIZADA (form.reboprod.php líneas 700-880) -->
        <?php if (!empty($reboMoveOuts)): ?>
            <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
            <tr>
                <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:0px">
                    <br>
                    <table border="0" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td colspan="2" style="background-color:#1AAAA4;color:#FFFFFF;padding:6px;font-weight:bold;text-align:center">
                            <i class="fa fa-fw fa-info"></i> PRODUCCIÓN FINALIZADA
                        </td>
                    </tr>
                    </table>
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <tr>
                        <td class="tdheader" width="100" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Transacción</td>
                        <td class="tdheader" width="100" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Tipo</td>
                        <td class="tdheader" width="100" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Fecha</td>
                        <td class="tdheader" width="100" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Código</td>
                        <td class="tdheader" width="100" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Material</td>
                        <td class="tdheader" width="100" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Cantidad</td>
                        <td class="tdheader" width="100" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Unit.</td>
                    </tr>
                    <?php
                    $_PROD_AMOUNT = 0;
                    foreach ($reboMoveOuts as $mo):
                        $delParam = "&delsthids[]={$mo['id']}";
                        foreach (($mo['moveins'] ?? []) as $mi) {
                            $delParam .= "&delsthids[]={$mi['id']}";
                        }
                    ?>
                        <tr>
                            <td style="border-top:3px double #666666;"><?=htmlspecialchars((string)$mo['stk_num'])?></td>
                            <td style="border-top:3px double #666666;color:red"><nobr><i class="fa fa-fw fa-minus"></i> Uso material</nobr></td>
                            <td style="border-top:3px double #666666;"><?=date("d.m.Y", (int)$mo['stk_bookdate'])?></td>
                            <td style="border-top:3px double #666666;"><?=htmlspecialchars((string)$mo['item_number_prod'])?></td>
                            <td style="border-top:3px double #666666;"><?=htmlspecialchars((string)$mo['item_title'])?></td>
                            <td style="border-top:3px double #666666;"><?=$fmt($mo['item_reboprod_amt'])?></td>
                            <td style="border-top:3px double #666666;">
                                <?=number_format((float)$mo['item_reboprod_unitkg'], 2, ',', '.')?>
                                <span style="float:right">
                                    <i class="fa fa-fw fa-times-circle" style="color:red;cursor:pointer" title="Anular transacción"
                                    onclick="if(confirm('¿Está seguro de anular este movimiento y revertir el inventario?')) { location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod<?=$delParam?>'; }"></i>
                                </span>
                            </td>
                        </tr>
                        <?php
                        $_PROD_AMOUNT += (float)$mo['item_amount'];
                        foreach (($mo['moveins'] ?? []) as $mi):
                        ?>
                            <tr style="background-color:#EEEEEE">
                                <td><?=htmlspecialchars((string)$mi['stk_num'])?></td>
                                <td style="color:green"><nobr><i class="fa fa-fw fa-plus"></i> Ingreso material</nobr></td>
                                <td><?=date("d.m.Y", (int)$mi['stk_bookdate'])?></td>
                                <td><?=htmlspecialchars((string)$mi['item_number_prod'])?></td>
                                <td><?=htmlspecialchars((string)$mi['item_title'])?></td>
                                <td><?=$fmt($mi['item_reboprod_amt'])?></td>
                                <td><?=number_format((float)$mi['item_reboprod_unitkg'], 2, ',', '.')?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    </table>

                    <?php
                    $reqTotal = (float)($reboOrder['req_rebo_rolloscc'] ?? ($agenda['req_rebo_rolloscc'] ?? 1));
                    ?>
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <tr>
                        <td class="tdheader" align="center" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Cantidad requerida</td>
                        <td class="tdheader" align="center" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Cantidad procesada</td>
                        <td class="tdheader" align="center" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Cantidad pendiente</td>
                    </tr>
                    <tr>
                        <td align="center" style="background-color:darkorange;font-weight:bold;color:#FFFFFF"><?=$fmt($reqTotal)?></td>
                        <td align="center" style="background-color:green;font-weight:bold;color:#FFFFFF"><?=$fmt($_PROD_AMOUNT)?></td>
                        <td align="center" style="background-color:red;font-weight:bold;color:#FFFFFF"><?=$fmt(max(0.0, $reqTotal - $_PROD_AMOUNT))?></td>
                    </tr>
                    </table>
                </td>
            </tr>
            </table>
        <?php endif; ?>

        <!-- TABLA: BOBINAS HIJAS FRACCIONADAS / CORTADAS (TRAZABILIDAD 100×70) -->
        <?php
        $reboCutRolls = [];
        if (Db::trzPdo() !== null) {
            try {
                $stmtCuts = Db::trzPdo()->prepare("
                    SELECT r.*, pr.roll_code AS parent_roll_code, s.code AS sku_code, s.description AS sku_desc
                    FROM rolls r 
                    LEFT JOIN rolls pr ON pr.id = r.parent_roll_id 
                    LEFT JOIN skus s ON s.id = r.sku_id
                    WHERE (r.source_work_order_id = :otid " . ($agId > 0 ? "OR r.source_work_order_id = :agid" : "") . ")
                      AND r.process_stage = 'CUT'
                    ORDER BY r.id DESC LIMIT 50
                ");
                $paramsCuts = [':otid' => $workerOtId];
                if ($agId > 0) {
                    $paramsCuts[':agid'] = $agId;
                }
                $stmtCuts->execute($paramsCuts);
                $reboCutRolls = $stmtCuts->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable) {}
        }
        ?>
        <?php if (!empty($reboCutRolls)): ?>
            <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
            <tr>
                <td style="background-color:#FFFFFF;border:1px solid #D5D8DC;border-radius:6px;overflow:hidden">
                    <table border="0" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="background:#2C3E50;color:#FFFFFF;padding:12px 16px;font-weight:bold;font-size:14px">
                            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                                <div>
                                    <i class="fa fa-fw fa-cut" style="color:#F39C12"></i> BOBINAS HIJAS GENERADAS / FRACCIONADAS (TRAZABILIDAD 100×70)
                                    <span style="font-size:12px;color:#BDC3C7;margin-left:8px">(<?=count($reboCutRolls)?> bobinas cortadas)</span>
                                </div>
                                <div>
                                    <a href="/rolls/<?=$reboCutRolls[0]['id']?>/label?server_print=1" target="_blank" class="btngreen" style="text-decoration:none;padding:5px 12px;font-size:12px;font-weight:bold;border-radius:4px;display:inline-block">
                                        <i class="fa fa-print"></i> Imprimir Última Etiqueta en Zebra
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    </table>
                    <table border="0" width="100%" cellpadding="8" cellspacing="0">
                    <tr style="background:#34495E;color:white;font-size:12px">
                        <td width="160" style="border-left:1px solid #4A6572">Código Bobina Hija</td>
                        <td width="150" style="border-left:1px solid #4A6572">Bobina Madre</td>
                        <td align="left" style="border-left:1px solid #4A6572">SKU / Detalle</td>
                        <td align="center" width="90" style="border-left:1px solid #4A6572">Ancho (mm)</td>
                        <td align="center" width="90" style="border-left:1px solid #4A6572">Metros</td>
                        <td align="center" width="90" style="border-left:1px solid #4A6572">Peso (Kg)</td>
                        <td align="center" width="90" style="border-left:1px solid #4A6572">Etapa</td>
                        <td align="center" width="220" style="border-left:1px solid #4A6572">Acciones</td>
                    </tr>
                    <?php foreach ($reboCutRolls as $cr): ?>
                        <tr style="border-bottom:1px solid #EEEEEE;font-size:12.5px">
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;font-weight:bold;color:#2980B9">
                                <i class="fa fa-barcode"></i> <?=htmlspecialchars($cr['roll_code'])?>
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD">
                                <?=htmlspecialchars($cr['parent_roll_code'] ?: ('ID #' . ($cr['parent_roll_id'] ?? '-')))?>
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="left">
                                <b><?=htmlspecialchars((string)($cr['sku_code'] ?? ''))?></b> <?=htmlspecialchars((string)($cr['sku_desc'] ?? ''))?>
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">
                                <?=!empty($cr['width_mm']) ? number_format((float)$cr['width_mm'], 0) . ' mm' : '-'?>
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">
                                <?=!empty($cr['meters']) ? number_format((float)$cr['meters'], 0) . ' m' : '-'?>
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">
                                <b><?=number_format((float)$cr['weight_kg'], 2, ',', '.')?> Kg</b>
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">
                                <span style="background:#EBF5FB;color:#2980B9;padding:2px 7px;border-radius:3px;font-weight:bold;font-size:11px">
                                    <?=htmlspecialchars($cr['process_stage'] ?? 'CUT')?>
                                </span>
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">
                                <a href="/rolls/<?=$cr['id']?>/label" target="_blank" class="btngrey" style="text-decoration:none;padding:4px 8px;font-size:11.5px;margin-right:4px" title="Ver e imprimir etiqueta en navegador">
                                    <i class="fa fa-tag"></i> 100×70
                                </a>
                                <a href="/rolls/<?=$cr['id']?>/label?server_print=1" target="_blank" class="btnblue" style="text-decoration:none;padding:4px 8px;font-size:11.5px;margin-right:4px" title="Enviar directamente a impresora Zebra">
                                    <i class="fa fa-print"></i> Zebra
                                </a>
                                <a href="/production/traceability?q=<?=urlencode($cr['roll_code'])?>" target="_blank" class="btngreen" style="text-decoration:none;padding:4px 8px;font-size:11.5px" title="Ver árbol genealógico completo">
                                    <i class="fa fa-sitemap"></i> 🌳
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </table>
                </td>
            </tr>
            </table>
        <?php endif; ?>

    <?php elseif ($mode === 'embalaje'): ?>
        <!-- SUBFORMULARIO: Embalaje, Generación de Cajas y Pallet (Selladora / Confección) -->
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver a la Consola de OT
            </div>
        </div>

        <?php if (!empty($_GET['boxes_done'])): ?>
            <div style="background:#E8F8F5;border:1.5px solid #2ECC71;color:#117A65;padding:16px 20px;border-radius:6px;margin-bottom:18px;font-size:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div>
                    <i class="fa fa-fw fa-check-circle" style="font-size:20px;color:#27AE60"></i>
                    <b>¡Cajas generadas exitosamente!</b> Se registraron <b><?= (int)$_GET['boxes_done'] ?></b> cajas vinculadas a la OT #<b><?= htmlspecialchars((string)$agenda['prd_number']) ?></b>.
                    <?php if (!empty($_GET['pallet_id'])): ?>
                        <span> · Pallet ID: <b><?= (int)$_GET['pallet_id'] ?></b></span>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if (!empty($_GET['first_box'])): ?>
                        <a href="/boxes/<?= (int)$_GET['first_box'] ?>/label?server_print=1" target="_blank" class="btnblue" style="text-decoration:none;font-weight:bold">
                            <i class="fa fa-print"></i> 🖨️ Mandar a Imprimir Etiquetas (100×70mm)
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php
        $woBoxes = [];
        $woPallet = null;
        if ($workerOtId > 0 && Db::trzPdo() !== null) {
            try {
                $stmtWoBoxes = Db::trzPdo()->prepare("SELECT * FROM boxes WHERE work_order_id = :otid ORDER BY id DESC");
                $stmtWoBoxes->execute([':otid' => $workerOtId]);
                $woBoxes = $stmtWoBoxes->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $stmtWoPallet = Db::trzPdo()->prepare("SELECT * FROM pallets WHERE work_order_id = :otid ORDER BY id DESC LIMIT 1");
                $stmtWoPallet->execute([':otid' => $workerOtId]);
                $woPallet = $stmtWoPallet->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Throwable) {}
        }
        $skuFinal = (string)($agenda['item_title'] ?? $agenda['fab_type'] ?? 'PRODUCTO-TERMINADO');
        ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(400px, 1fr));gap:20px;margin-bottom:20px">
            <!-- COLUMNA 1: FORMULARIO GENERACIÓN DE CAJAS -->
            <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
                <div style="font-size:17px;font-weight:bold;color:#0F172A;margin-bottom:14px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-box" style="color:#F59E0B"></i> Generación de Cajas y Pallet
                </div>

                <form method="post" action="/production/work-orders/boxes/generate">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                    <input type="hidden" name="sku_final" value="<?=htmlspecialchars($skuFinal)?>">

                    <table border="0" width="100%" cellpadding="8" cellspacing="0">
                    <tr>
                        <td class="tdleft" width="160">OT Activa:</td>
                        <td class="tdnrm"><b>OT #<?=htmlspecialchars((string)$agenda['prd_number'])?></b> &mdash; <?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Producto / SKU:</td>
                        <td class="tdnrm"><b><?=htmlspecialchars($skuFinal)?></b></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Bobina de Origen:</td>
                        <td class="tdnrm">
                            <?php if (!empty($assignedRolls)): ?>
                                <select name="source_roll_id" class="inptxt" style="width:100%">
                                    <?php foreach ($assignedRolls as $ar): ?>
                                        <option value="<?=$ar['id']?>" <?=!empty($ar['is_mounted']) ? 'selected' : ''?>>
                                            <?=htmlspecialchars($ar['roll_code'])?> (<?=number_format((float)$ar['weight_kg'], 1)?> Kg <?=!empty($ar['is_mounted']) ? ' - MONTADA' : ''?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <span style="color:#64748B;font-size:12px;font-style:italic">Se auto-detectará la bobina activa de la orden.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Cantidad de Cajas:</td>
                        <td class="tdnrm">
                            <input type="number" name="box_qty" min="1" step="1" value="10" required class="inptxt" style="width:120px;font-weight:bold;font-size:15px"> cajas
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Unidades por Caja:</td>
                        <td class="tdnrm">
                            <input type="number" name="units_per_box" min="1" step="1" value="500" required class="inptxt" style="width:120px;font-weight:bold;font-size:15px"> unidades
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Cajas por Pallet:</td>
                        <td class="tdnrm">
                            <input type="number" name="boxes_per_pallet" min="0" step="1" value="20" class="inptxt" style="width:120px">
                            <span style="font-size:11px;color:#64748B">(Opcional: agrupa en etiqueta PAL-XXXXXX)</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Destino:</td>
                        <td class="tdnrm">
                            <select name="destination_mode" class="inptxt" style="width:160px">
                                <option value="STOCK">STOCK BODEGA</option>
                                <option value="CUSTOMER_ORDER">ORDEN CLIENTE</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm" style="padding-top:16px">
                            <button type="submit" class="btngreen" style="font-size:15px;padding:12px 26px;box-shadow:0 2px 4px rgba(0,0,0,0.15)">
                                <i class="fa fa-boxes"></i> 📦 Generar Cajas y Rotular Etiquetas
                            </button>
                        </td>
                    </tr>
                    </table>
                </form>
            </div>

            <!-- COLUMNA 2: PALLET Y RESUMEN DE EMBALAJE -->
            <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
                <div style="font-size:17px;font-weight:bold;color:#0F172A;margin-bottom:14px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-pallet" style="color:#8B5CF6"></i> Pallet de Consolidación Activo
                </div>

                <?php if ($woPallet): ?>
                    <div style="background:#F5F3FF;border:1.5px solid #DDD6FE;border-radius:6px;padding:16px;margin-bottom:16px">
                        <div style="font-size:18px;font-weight:bold;color:#6B21A8;margin-bottom:4px">
                            <?= htmlspecialchars((string)$woPallet['pallet_code']) ?>
                        </div>
                        <div style="font-size:13px;color:#475569;margin-bottom:12px">
                            Total Cajas Agrupadas: <b><?= (int)($woPallet['box_count'] ?? 0) ?></b> · Estado: <b><?= htmlspecialchars((string)$woPallet['status']) ?></b>
                        </div>
                        <a href="/pallets/<?= (int)$woPallet['id'] ?>/label" target="_blank" class="btnblue" style="text-decoration:none;font-weight:bold;display:inline-flex;align-items:center;gap:6px">
                            <i class="fa fa-print"></i> Imprimir Etiqueta Pallet 100×70mm
                        </a>
                    </div>
                <?php else: ?>
                    <div style="background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:6px;padding:20px;text-align:center;color:#64748B;font-size:13px;margin-bottom:16px">
                        <i class="fa fa-pallet" style="font-size:28px;color:#94A3B8;margin-bottom:8px"></i><br>
                        Aún no se ha consolidado un pallet en esta orden. Al ingresar "Cajas por Pallet" en el formulario se generará automáticamente.
                    </div>
                <?php endif; ?>

                <div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:6px;padding:12px;color:#92400E;font-size:12.5px;line-height:1.5">
                    <i class="fa fa-info-circle"></i> <b>Norma de Trazabilidad Confección:</b> Cada caja llevará su código de barras único <b>BOX-XXXXXX</b> vinculado a la bobina de origen pesada en balanza. Al escanear la caja en despacho se audita toda la cadena de custodia.
                </div>
            </div>
        </div>

        <!-- TABLA: HISTORIAL DE CAJAS GENERADAS EN ESTA OT -->
        <?php if (!empty($woBoxes)): ?>
            <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);margin-bottom:20px">
                <div style="font-size:16px;font-weight:bold;color:#0F172A;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between">
                    <div><i class="fa fa-boxes"></i> Cajas Generadas en esta OT (<?= count($woBoxes) ?> cajas)</div>
                </div>
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr style="background:#F1F5F9;color:#334155;text-align:left;border-bottom:2px solid #CBD5E1">
                            <th style="padding:8px">Código Caja</th>
                            <th style="padding:8px">Unidades</th>
                            <th style="padding:8px">Bobina Origen</th>
                            <th style="padding:8px">Pallet</th>
                            <th style="padding:8px">Destino</th>
                            <th style="padding:8px">Operario</th>
                            <th style="padding:8px">Fecha</th>
                            <th style="padding:8px;text-align:center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($woBoxes as $wb): ?>
                            <tr style="border-bottom:1px solid #E2E8F0">
                                <td style="padding:8px"><b><?= htmlspecialchars((string)$wb['box_code']) ?></b></td>
                                <td style="padding:8px"><b><?= number_format((float)$wb['units_qty'], 0, ',', '.') ?></b> uds</td>
                                <td style="padding:8px"><?= (int)($wb['source_roll_id'] ?? 0) > 0 ? '#' . (int)$wb['source_roll_id'] : 'Auto-detect' ?></td>
                                <td style="padding:8px"><?= !empty($wb['pallet_id']) ? 'PAL-' . (int)$wb['pallet_id'] : '-' ?></td>
                                <td style="padding:8px"><?= htmlspecialchars((string)($wb['destination_mode'] ?? 'STOCK')) ?></td>
                                <td style="padding:8px"><?= htmlspecialchars((string)($wb['operator_name'] ?? '-')) ?></td>
                                <td style="padding:8px"><?= htmlspecialchars((string)($wb['created_at'] ?? '-')) ?></td>
                                <td style="padding:8px;text-align:center">
                                    <a href="/boxes/<?= (int)$wb['id'] ?>/label" target="_blank" class="btnblue" style="padding:3px 8px;font-size:11px;text-decoration:none">
                                        <i class="fa fa-print"></i> 100×70
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <?php elseif ($mode === 'semielaborado'): ?>
        <!-- SUBFORMULARIO: Bobina Semielaborada Impresa (Flexografía) -->
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver a la Consola de OT
            </div>
        </div>

        <?php if (!empty($_GET['roll_created'])): ?>
            <div style="background:#E8F8F5;border:1.5px solid #2ECC71;color:#117A65;padding:16px 20px;border-radius:6px;margin-bottom:18px;font-size:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div>
                    <i class="fa fa-fw fa-check-circle" style="font-size:20px;color:#27AE60"></i>
                    <b>¡Bobina semielaborada generada exitosamente!</b> Se registró <b><?=htmlspecialchars($_GET['roll_code'] ?? ('ID #' . $_GET['roll_created']))?></b> vinculada a la OT #<b><?=htmlspecialchars((string)$agenda['prd_number'])?></b>.
                </div>
                <div style="display:flex;gap:8px">
                    <a href="/rolls/<?=(int)$_GET['roll_created']?>/label?server_print=1" target="_blank" class="btnblue" style="text-decoration:none;font-weight:bold">
                        <i class="fa fa-print"></i> 🖨️ Mandar a Imprimir en Zebra
                    </a>
                    <a href="/rolls/<?=(int)$_GET['roll_created']?>/label" target="_blank" class="btngrey" style="text-decoration:none;font-weight:bold">
                        <i class="fa fa-eye"></i> Vista Previa (100×70)
                    </a>
                    <a href="/production/traceability?q=<?=urlencode((string)($_GET['roll_code'] ?? ''))?>" target="_blank" class="btngreen" style="text-decoration:none;font-weight:bold">
                        <i class="fa fa-sitemap"></i> 🌳 Trazabilidad
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['error'])): ?>
            <div style="background:#FDEDEC;border:1.5px solid #E74C3C;color:#922B21;padding:14px 18px;border-radius:6px;margin-bottom:18px;font-size:14px">
                <i class="fa fa-fw fa-exclamation-triangle" style="font-size:18px"></i>
                <b>Error:</b> <?=htmlspecialchars((string)$_GET['error'])?>
            </div>
        <?php endif; ?>

        <?php
        $woSemielaboratedRolls = [];
        if ($workerOtId > 0 && Db::trzPdo() !== null) {
            try {
                $stmtWoSE = Db::trzPdo()->prepare("
                    SELECT r.*, pr.roll_code AS parent_roll_code, s.code AS sku_code, s.description AS sku_desc
                    FROM rolls r 
                    LEFT JOIN rolls pr ON pr.id = r.parent_roll_id 
                    LEFT JOIN skus s ON s.id = r.sku_id
                    WHERE (r.source_work_order_id = :otid " . ($agId > 0 ? "OR r.source_work_order_id = :agid" : "") . ")
                      AND r.process_stage = 'PRINTED'
                    ORDER BY r.id DESC
                ");
                $paramsSE = [':otid' => $workerOtId];
                if ($agId > 0) {
                    $paramsSE[':agid'] = $agId;
                }
                $stmtWoSE->execute($paramsSE);
                $woSemielaboratedRolls = $stmtWoSE->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable) {}
        }
        $skuPrintDefault = (string)($agenda['item_title'] ?? $agenda['fab_type'] ?? 'BOBINA-IMPRESA');
        $suggestedMeters = (float)($metros_a_imprimir ?? ($stats['total_meters'] ?? 0));
        $telaWidth = (float)($agenda['req_operador_tela_width'] ?? ($agenda['fab_med_width'] ?? 80));
        if ($telaWidth <= 0) $telaWidth = 80.0;
        $telaGramms = (float)($agenda['fab_mat_gramms'] ?? 80);
        if ($telaGramms <= 0) $telaGramms = 80.0;
        $metersPerKg = round(100000.0 / ($telaWidth * $telaGramms), 2);
        ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(420px, 1fr));gap:20px;margin-bottom:20px">
            <!-- COLUMNA 1: FORMULARIO GENERACIÓN DE BOBINA SEMIELABORADA -->
            <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
                <div style="font-size:17px;font-weight:bold;color:#0F172A;margin-bottom:14px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-tag" style="color:#6366F1"></i> Emisión de Bobina Impresa Semielaborada
                </div>
                <div style="font-size:13px;color:#64748B;margin-bottom:18px">
                    Registre el tiraje de la bobina impresa al salir de Flexografía. Se creará con código <b>ROLL-SE-XXXXX</b>, vinculada genealógicamente a la bobina virgen y lista para rotularse en 100×70 mm.
                </div>

                <form method="post" action="/production/work-orders/rolls/generate-semielaborated" id="form_semielaborado">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">

                    <table border="0" width="100%" cellpadding="8" cellspacing="0">
                    <tr>
                        <td class="tdleft" width="160">OT Activa:</td>
                        <td class="tdnrm"><b>OT #<?=htmlspecialchars((string)$agenda['prd_number'])?></b> &mdash; <?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Bobina Madre (Virgen):</td>
                        <td class="tdnrm">
                            <?php if (!empty($assignedRolls)): ?>
                                <select name="parent_roll_id" id="parent_roll_id" class="inptxt" style="width:100%" onchange="onParentRollChange()">
                                    <option value="" data-width="<?=$telaWidth?>" data-gramms="<?=$telaGramms?>">-- Sin bobina directa / Auto-detectar --</option>
                                    <?php foreach ($assignedRolls as $ar): 
                                        $arWidth = (float)($ar['width_mm'] ?? 0) > 0 ? round((float)$ar['width_mm'] / 10, 1) : $telaWidth;
                                        $arGramms = (float)($ar['microns'] ?? 0) > 0 ? (float)$ar['microns'] : $telaGramms;
                                    ?>
                                        <option value="<?=$ar['id']?>" 
                                                data-width="<?=$arWidth?>" 
                                                data-gramms="<?=$arGramms?>"
                                                <?=!empty($ar['is_mounted']) ? 'selected' : ''?>>
                                            <?=htmlspecialchars($ar['roll_code'])?> (<?=number_format((float)$ar['weight_kg'], 1)?> Kg<?=!empty($ar['is_mounted']) ? ' - MONTADA' : ''?> &bull; Ancho: <?=$arWidth?> cm)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <span style="color:#64748B;font-size:12px;font-style:italic">Se auto-detectará la bobina activa montada en la OT.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Descripción / SKU:</td>
                        <td class="tdnrm">
                            <input type="text" name="sku_code" class="inptxt" style="width:100%" value="<?=htmlspecialchars($skuPrintDefault)?>" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft" style="font-weight:bold;color:#0F172A">
                            <i class="fa fa-weight-hanging" style="color:#2563EB"></i> Peso Bobina Saliente (Kg):
                        </td>
                        <td class="tdnrm">
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                                <input type="number" step="0.01" min="0.1" name="weight_kg" id="flexo-weight-kg" class="inptxt" style="width:140px;font-weight:bold;font-size:16px;color:#0F172A;border:2px solid #3B82F6;border-radius:6px;padding:7px 10px" placeholder="0.00" required oninput="recalcFlexoMetersFromWeight()" onchange="recalcFlexoMetersFromWeight()">
                                <span style="font-size:14px;font-weight:bold;color:#475569">Kg neto</span>
                                <button type="button" id="btn-read-scale-flexo" class="btnorange" style="padding:7px 16px;font-size:12.5px;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:6px" onclick="captureFlexoScaleWeight()">
                                    <i class="fa fa-balance-scale"></i> ⚖️ Capturar Balanza
                                </button>
                            </div>
                            <div id="scale-status-msg" style="font-size:11.5px;color:#64748B;margin-top:5px">
                                Ingrese el peso de la bobina impresa saliente o presione <b>Capturar Balanza</b> para pesaje directo en vivo.
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft" style="font-weight:bold;color:#0F172A">
                            <i class="fa fa-ruler-combined" style="color:#059669"></i> Metros Lineales:
                        </td>
                        <td class="tdnrm">
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                                <input type="number" step="1" min="1" name="meters" id="flexo-meters" class="inptxt" style="width:140px;font-weight:bold;font-size:16px;color:#065F46;border:2px solid #10B981;border-radius:6px;padding:7px 10px" value="<?=$suggestedMeters > 0 ? (int)$suggestedMeters : 0?>" required>
                                <span style="font-size:14px;font-weight:bold;color:#065F46">metros aprox.</span>
                                <button type="button" class="btngrey" style="padding:6px 12px;font-size:11.5px;cursor:pointer;display:inline-flex;align-items:center;gap:4px" onclick="recalcFlexoMetersFromWeight(true)" title="Recalcular metros según el peso actual">
                                    <i class="fa fa-sync-alt"></i> Recalcular por Peso
                                </button>
                                <?php if ($suggestedMeters > 0): ?>
                                    <button type="button" class="btngrey" style="padding:6px 10px;font-size:11px;cursor:pointer" onclick="document.getElementById('flexo-meters').value = '<?=(int)$suggestedMeters?>'">
                                        Usar programado (<?=(int)$suggestedMeters?> m)
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div id="meters-calc-info" style="font-size:12px;color:#059669;margin-top:6px;background:#F0FDF4;border:1px solid #BBF7D0;padding:6px 10px;border-radius:4px;display:inline-block">
                                <i class="fa fa-calculator"></i> <b>Cálculo automático según peso:</b> <span id="meters-ratio-text">~<?=$metersPerKg?> metros por kg</span> (Ancho: <span id="calc-width-text"><?=$telaWidth?></span> cm &bull; Gramaje: <span id="calc-gramms-text"><?=$telaGramms?></span> g/m²).
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Impresión Rótulo:</td>
                        <td class="tdnrm">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
                                <input type="checkbox" name="auto_print" value="1" checked>
                                <b>Mandar a imprimir etiqueta automáticamente en Zebra (100×70mm)</b>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm" style="padding-top:14px">
                            <button type="submit" class="btngreen" style="font-size:14px;padding:11px 22px;border:none;cursor:pointer;border-radius:4px;display:inline-flex;align-items:center;gap:8px">
                                <i class="fa fa-tag"></i> 🏷️ Generar y Rotular Bobina Semielaborada
                            </button>
                        </td>
                    </tr>
                    </table>
                </form>
            </div>

            <!-- COLUMNA 2: RESUMEN Y BOBINAS IMPRESAS GENERADAS -->
            <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
                <div style="font-size:17px;font-weight:bold;color:#0F172A;margin-bottom:14px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-history" style="color:#0284C7"></i> Bobinas Impresas Emitidas en esta OT
                </div>

                <?php
                $totSeWeight = 0.0;
                $totSeMeters = 0.0;
                foreach ($woSemielaboratedRolls as $wr) {
                    $totSeWeight += (float)($wr['weight_kg'] ?? 0);
                    $totSeMeters += (float)($wr['meters'] ?? 0);
                }
                ?>

                <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:10px;margin-bottom:16px">
                    <div style="background:#F0F9FF;border:1px solid #BAE6FD;border-radius:6px;padding:12px;text-align:center">
                        <div style="font-size:11px;font-weight:bold;color:#0369A1;text-transform:uppercase">Bobinas</div>
                        <div style="font-size:20px;font-weight:900;color:#0C4A6E"><?=count($woSemielaboratedRolls)?></div>
                    </div>
                    <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:6px;padding:12px;text-align:center">
                        <div style="font-size:11px;font-weight:bold;color:#15803D;text-transform:uppercase">Kilos Prod.</div>
                        <div style="font-size:20px;font-weight:900;color:#14532D"><?=number_format($totSeWeight, 1, ',', '.')?></div>
                    </div>
                    <div style="background:#FAF5FF;border:1px solid #E9D5FF;border-radius:6px;padding:12px;text-align:center">
                        <div style="font-size:11px;font-weight:bold;color:#7E22CE;text-transform:uppercase">Metros</div>
                        <div style="font-size:20px;font-weight:900;color:#581C87"><?=number_format($totSeMeters, 0, ',', '.')?></div>
                    </div>
                </div>

                <?php if (empty($woSemielaboratedRolls)): ?>
                    <div style="text-align:center;padding:32px 16px;color:#94A3B8;font-size:13px;background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:6px">
                        <i class="fa fa-info-circle fa-2x" style="color:#CBD5E1;margin-bottom:8px;display:block"></i>
                        Aún no se han generado bobinas semielaboradas para esta OT.<br>
                        Complete el formulario para emitir la primera bobina impresa.
                    </div>
                <?php else: ?>
                    <div style="max-height:280px;overflow-y:auto;border:1px solid #E2E8F0;border-radius:6px">
                        <table style="width:100%;border-collapse:collapse;font-size:12.5px">
                        <thead>
                            <tr style="background:#F8FAFC;color:#475569;border-bottom:1px solid #CBD5E1">
                                <th style="padding:8px;text-align:left">Código Rollo</th>
                                <th style="padding:8px;text-align:left">Madre</th>
                                <th style="padding:8px;text-align:right">Peso</th>
                                <th style="padding:8px;text-align:right">Metros</th>
                                <th style="padding:8px;text-align:center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($woSemielaboratedRolls as $wr): ?>
                                <tr style="border-bottom:1px solid #F1F5F9">
                                    <td style="padding:8px;font-weight:bold;color:#6366F1">
                                        <i class="fa fa-barcode"></i> <?=htmlspecialchars($wr['roll_code'])?>
                                    </td>
                                    <td style="padding:8px;color:#64748B">
                                        <?=htmlspecialchars($wr['parent_roll_code'] ?: ('ID #' . ($wr['parent_roll_id'] ?? '-')))?>
                                    </td>
                                    <td style="padding:8px;text-align:right">
                                        <b><?=number_format((float)$wr['weight_kg'], 2, ',', '.')?> Kg</b>
                                    </td>
                                    <td style="padding:8px;text-align:right">
                                        <?=number_format((float)$wr['meters'], 0, ',', '.')?> m
                                    </td>
                                    <td style="padding:8px;text-align:center">
                                        <a href="/rolls/<?=(int)$wr['id']?>/label" target="_blank" class="btngrey" style="padding:3px 7px;font-size:11px;text-decoration:none;margin-right:3px" title="Ver etiqueta 100×70">
                                            <i class="fa fa-tag"></i> 100×70
                                        </a>
                                        <a href="/rolls/<?=(int)$wr['id']?>/label?server_print=1" target="_blank" class="btnblue" style="padding:3px 7px;font-size:11px;text-decoration:none;margin-right:3px" title="Imprimir en Zebra">
                                            <i class="fa fa-print"></i> Zebra
                                        </a>
                                        <a href="/production/traceability?q=<?=urlencode($wr['roll_code'])?>" target="_blank" class="btngreen" style="padding:3px 7px;font-size:11px;text-decoration:none" title="Ver árbol de trazabilidad">
                                            <i class="fa fa-sitemap"></i> 🌳
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        var defaultTelaWidth = <?=(float)$telaWidth?>;
        var defaultTelaGramms = <?=(float)$telaGramms?>;
        var activeTelaWidth = defaultTelaWidth;
        var activeTelaGramms = defaultTelaGramms;

        function onParentRollChange() {
            var sel = document.getElementById('parent_roll_id');
            if (sel) {
                var opt = sel.options[sel.selectedIndex];
                if (opt) {
                    var w = parseFloat(opt.getAttribute('data-width')) || defaultTelaWidth;
                    var g = parseFloat(opt.getAttribute('data-gramms')) || defaultTelaGramms;
                    activeTelaWidth = w > 0 ? w : defaultTelaWidth;
                    activeTelaGramms = g > 0 ? g : defaultTelaGramms;
                }
            }
            updateMetersRatioDisplay();
            var wInp = document.getElementById('flexo-weight-kg');
            if (wInp && parseFloat(wInp.value) > 0) {
                recalcFlexoMetersFromWeight(false);
            }
        }

        function updateMetersRatioDisplay() {
            var ratio = 100000.0 / (activeTelaWidth * activeTelaGramms);
            var rTxt = document.getElementById('meters-ratio-text');
            if (rTxt) rTxt.innerText = '~' + ratio.toFixed(2) + ' metros por kg';
            var wTxt = document.getElementById('calc-width-text');
            if (wTxt) wTxt.innerText = activeTelaWidth;
            var gTxt = document.getElementById('calc-gramms-text');
            if (gTxt) gTxt.innerText = activeTelaGramms;
        }

        function recalcFlexoMetersFromWeight(forceFlash) {
            var weightInput = document.getElementById('flexo-weight-kg');
            var metersInput = document.getElementById('flexo-meters');
            if (!weightInput || !metersInput) return;
            
            var weight = parseFloat(String(weightInput.value).replace(',', '.')) || 0;
            if (weight > 0) {
                // Metros aproximados según peso, ancho de tela y gramaje:
                // metros = (peso_kg * 100000) / (ancho_cm * gramaje_g_m2)
                var ratio = 100000.0 / (activeTelaWidth * activeTelaGramms);
                var approxMeters = Math.round(weight * ratio);
                if (approxMeters <= 0) approxMeters = 1;
                metersInput.value = approxMeters;
                
                var infoBox = document.getElementById('meters-calc-info');
                if (infoBox) {
                    infoBox.innerHTML = '<i class="fa fa-check-circle" style="color:#16a34a"></i> <b>Aproximado por peso:</b> <b>' + approxMeters.toLocaleString('es-CL') + ' metros</b> (~' + ratio.toFixed(2) + ' m/kg con Ancho ' + activeTelaWidth + ' cm y ' + activeTelaGramms + ' g/m²).';
                }
                
                if (forceFlash) {
                    metersInput.style.backgroundColor = '#dcfce7';
                    setTimeout(function() { metersInput.style.backgroundColor = '#ffffff'; }, 1000);
                }
            } else {
                var infoBox = document.getElementById('meters-calc-info');
                if (infoBox) {
                    var ratio = 100000.0 / (activeTelaWidth * activeTelaGramms);
                    infoBox.innerHTML = '<i class="fa fa-calculator"></i> <b>Cálculo automático según peso:</b> ~' + ratio.toFixed(2) + ' metros por kg (Ancho: ' + activeTelaWidth + ' cm &bull; Gramaje: ' + activeTelaGramms + ' g/m²). Ingrese el peso para calcular.';
                }
            }
        }

        async function captureFlexoScaleWeight() {
            var btn = document.getElementById('btn-read-scale-flexo');
            var input = document.getElementById('flexo-weight-kg');
            var statusMsg = document.getElementById('scale-status-msg');
            if (!input || !btn) return;
            var oldHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Leyendo balanza...';
            btn.disabled = true;
            try {
                var res = await fetch('/api/scale/weight', { cache: 'no-store' });
                var data = await res.json();
                if (data && data.ok && data.weight_kg !== undefined) {
                    var w = parseFloat(data.weight_kg);
                    input.value = w.toFixed(2);
                    input.style.backgroundColor = '#dcfce7';
                    if (statusMsg) {
                        statusMsg.innerHTML = '<span style="color:#16a34a;font-weight:bold"><i class="fa fa-check"></i> Peso capturado desde balanza: ' + w.toFixed(2) + ' Kg</span>';
                    }
                    // Calcular de inmediato los metros aproximados según el peso:
                    recalcFlexoMetersFromWeight(true);
                    setTimeout(function() { input.style.backgroundColor = '#ffffff'; }, 1200);
                } else {
                    alert('No se pudo obtener peso de balanza: ' + (data.error || 'Sin lectura'));
                }
            } catch(e) {
                alert('Error al conectar con la balanza: ' + e.message);
            } finally {
                btn.innerHTML = oldHtml;
                btn.disabled = false;
            }
        }

        // Inicializar al cargar
        window.addEventListener('DOMContentLoaded', function() {
            onParentRollChange();
        });
        </script>

    <?php elseif ($mode === 'terminarot'): ?>
        <!-- SUBFORMULARIO: Cierre de OT con Supervisor (fancy.embalaje.termot.php) -->
        <?php $useCloseSupervisorModal = ($isFlexo || $isSeri || $isSeriPulpo); ?>
        <?php if (!$useCloseSupervisorModal): ?>
            <div style="margin-bottom:15px">
                <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                    <i class="fa fa-fw fa-arrow-left"></i> Cancelar y Volver a la OT
                </div>
            </div>
        <?php endif; ?>
        <div style="<?= $useCloseSupervisorModal ? 'padding:0;max-width:none;margin:0;border:none;background:transparent' : 'background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:25px;max-width:700px;margin:0 auto' ?>">
            <?php if (!$useCloseSupervisorModal): ?>
                <div style="font-size:18px;font-weight:bold;color:#D64141;margin-bottom:10px;text-align:center">
                    <i class="fa fa-fw fa-lock"></i> Finalizar y Cerrar Orden de Trabajo
                </div>
                <div style="text-align:center;color:#666;margin-bottom:20px;font-size:13px">
                    Se requiere autorización de Supervisor para cerrar la OT #<b><?=htmlspecialchars((string)$agenda['prd_number'])?></b>.
                </div>
            <?php endif; ?>
            <?php if ($isSeriPulpo && !$useCloseSupervisorModal): ?>
                <div style="background:#F8FAFC;border:1px solid #CBD5E1;border-radius:5px;padding:14px 18px;margin-bottom:18px">
                    <div style="font-size:13px;font-weight:bold;color:#1E293B;margin-bottom:8px">
                        <i class="fa fa-layer-group" style="color:#008A87"></i> Balance de Producción Serigrafía Pulpo al Cierre:
                    </div>
                    <table border="0" width="100%" cellpadding="4" cellspacing="0" style="font-size:12.5px">
                        <tr>
                            <td style="color:#64748B" width="220">Pasadas Frente acumuladas:</td>
                            <td><b><?=number_format($prevFrente, 0, ',', '.')?></b> pasadas</td>
                        </tr>
                        <tr>
                            <td style="color:#64748B">Pasadas Dorso acumuladas:</td>
                            <td><b><?=number_format($prevDorso, 0, ',', '.')?></b> pasadas</td>
                        </tr>
                        <tr>
                            <td style="color:#64748B">Bolsas terminadas completas:</td>
                            <td><b style="color:#059669"><?=number_format($prevFinished, 0, ',', '.')?></b> bolsas</td>
                        </tr>
                        <tr>
                            <td style="color:#64748B">Bolsas sin terminar al cierre:</td>
                            <td>
                                <?php if ($prevSurplusFrente > 0): ?>
                                    <span style="color:#D97706;font-weight:bold">
                                        <i class="fa fa-exclamation-triangle"></i> <?=number_format($prevSurplusFrente, 0, ',', '.')?> bolsas con Frente impreso (pendientes de dorso). Quedarán guardadas para el próximo ingreso.
                                    </span>
                                <?php elseif ($prevSurplusDorso > 0): ?>
                                    <span style="color:#D97706;font-weight:bold">
                                        <i class="fa fa-exclamation-triangle"></i> <?=number_format($prevSurplusDorso, 0, ',', '.')?> bolsas con Dorso impreso (pendientes de frente). Quedarán guardadas para el próximo ingreso.
                                    </span>
                                <?php else: ?>
                                    <span style="color:#059669;font-weight:bold">
                                        <i class="fa fa-check-circle"></i> 0 bolsas pendientes (Frente y Dorso al día).
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>

            <?php
            // Balance general de producción y mermas de la OT al cierre
            $otSummaryProduced = 0.0;
            $otSummaryMlin = 0.0;
            $otSummaryKg = 0.0;
            try {
                $stSumProd = Db::erpPdo()->prepare("
                    SELECT SUM(evt_amount) AS total_amt, 
                           SUM(evt_amount_metros_lineales) AS total_mlin, 
                           SUM(prod_bobina_kg) AS total_kg
                    FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :ot_id
                      AND evt_type IN ('prod', 'prodsericolor')
                      AND evt_status > 0
                      AND (evt_comments IS NULL OR evt_comments NOT LIKE '%Bobina Semielaborada%')
                ");
                $stSumProd->execute([':ot_id' => $workerOtId]);
                $rSumProd = $stSumProd->fetch(PDO::FETCH_ASSOC);
                if ($rSumProd) {
                    $otSummaryProduced = (float)($rSumProd['total_amt'] ?? 0);
                    $otSummaryMlin = (float)($rSumProd['total_mlin'] ?? 0);
                    $otSummaryKg = (float)($rSumProd['total_kg'] ?? 0);
                }
            } catch (Throwable) {}

            $prSummary = $prodService->getPrintedRollsSummary($workerOtId);

            $otSummaryWasteKg = 0.0;
            $otWasteBreakdown = [];
            $otProducedDetail = [];
            $otWasteDetail = [];
            $otSummaryProducedDisplay = 0.0;
            $parseSeriProdMeta = static function (string $commentsText): array {
                $result = ['imp_des' => 0, 'img_bast' => 0, 'base' => 0];
                if ($commentsText === '') {
                    return $result;
                }
                if (preg_match('/\bimp\/des=(\d+(?:\.\d+)?)\b/i', $commentsText, $mImp)) {
                    $result['imp_des'] = (float)($mImp[1] ?? 0);
                }
                if (preg_match('/\bimg\/bast=(\d+(?:\.\d+)?)\b/i', $commentsText, $mBast)) {
                    $result['img_bast'] = (float)($mBast[1] ?? 0);
                }
                if (preg_match('/\bbase=(\d+(?:\.\d+)?)\b/i', $commentsText, $mBase)) {
                    $result['base'] = (float)($mBase[1] ?? 0);
                }
                return $result;
            };
            try {
                $stProdDetail = Db::erpPdo()->prepare("
                    SELECT id,
                           evt_type,
                           evt_amount,
                           evt_amount2,
                           evt_amount_metros_lineales,
                           prod_bobina_kg,
                           evt_comments,
                           evt_crtdat
                    FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :ot_id
                      AND evt_type IN ('prod', 'prodsericolor')
                      AND evt_status > 0
                      AND (evt_comments IS NULL OR evt_comments NOT LIKE '%Bobina Semielaborada%')
                    ORDER BY evt_crtdat ASC, id ASC
                ");
                $stProdDetail->execute([':ot_id' => $workerOtId]);
                while ($pRow = $stProdDetail->fetch(PDO::FETCH_ASSOC)) {
                    $commentsText = (string)($pRow['evt_comments'] ?? '');
                    $isSeriDetail = ($isSeri || $isSeriPulpo || (string)($pRow['evt_type'] ?? '') === 'prodsericolor' || str_contains($commentsText, '[SERI_COUNTERS:'));
                    $meta = $parseSeriProdMeta($commentsText);
                    $displayCounter = 0.0;
                    $displayDevPrints = 0.0;
                    $displayUnits = (float)($pRow['evt_amount'] ?? 0);
                    if ($isSeriDetail) {
                        $displayCounter = (float)($pRow['evt_amount2'] ?? 0);
                        if ($displayCounter <= 0 && (float)$meta['base'] > 0) {
                            $displayCounter = (float)$meta['base'];
                        }
                        $displayDevPrints = (float)$meta['img_bast'];
                        if ($displayDevPrints <= 0) {
                            $displayDevPrints = (float)$meta['imp_des'];
                        }
                        if ($displayDevPrints <= 0) {
                            $displayDevPrints = max(1.0, (float)($agenda['req_operador_bastidoramt'] ?? 1));
                        }
                        if ($displayUnits <= 0 && $displayCounter > 0) {
                            $displayUnits = $displayCounter * $displayDevPrints;
                        }
                    }
                    $pRow['display_is_seri'] = $isSeriDetail ? 1 : 0;
                    $pRow['display_counter'] = $displayCounter;
                    $pRow['display_devprints'] = $displayDevPrints;
                    $pRow['display_units'] = $displayUnits;
                    $otSummaryProducedDisplay += $displayUnits;
                    $otProducedDetail[] = $pRow;
                }
            } catch (Throwable) {}
            if ($otSummaryProducedDisplay <= 0) {
                $otSummaryProducedDisplay = $otSummaryProduced;
            }
            try {
                $stSumWaste = Db::erpPdo()->prepare("
                    SELECT COALESCE(t2.merma_title, 'Merma') AS merma_title,
                           SUM(d.evt_kgstounits) AS kgs
                    FROM prod_worker_ot_defectunits d
                    INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                    LEFT JOIN prod_mermatypes t2 ON d.evt_merma_typeid = t2.id
                    WHERE e.evt_prod_worker_otid = :ot_id
                      AND d.evt_status > 0
                    GROUP BY d.evt_merma_typeid, t2.merma_title
                    HAVING kgs > 0
                ");
                $stSumWaste->execute([':ot_id' => $workerOtId]);
                while ($wRow = $stSumWaste->fetch(PDO::FETCH_ASSOC)) {
                    $wKgs = (float)$wRow['kgs'];
                    $otSummaryWasteKg += $wKgs;
                    $otWasteBreakdown[] = htmlspecialchars($wRow['merma_title']) . ': <b>' . number_format($wKgs, 2, ',', '.') . ' kg</b>';
                }
            } catch (Throwable) {}
            try {
                $stWasteDetail = Db::erpPdo()->prepare("
                    SELECT COALESCE(t2.merma_title, 'Merma') AS merma_title,
                           d.evt_amount,
                           d.evt_kgstounits,
                           d.evt_mtstounits,
                           d.evt_comments,
                           d.evt_crtdat
                    FROM prod_worker_ot_defectunits d
                    INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                    LEFT JOIN prod_mermatypes t2 ON d.evt_merma_typeid = t2.id
                    WHERE e.evt_prod_worker_otid = :ot_id
                      AND d.evt_status > 0
                    ORDER BY d.evt_crtdat ASC, d.id ASC
                ");
                $stWasteDetail->execute([':ot_id' => $workerOtId]);
                while ($wRow = $stWasteDetail->fetch(PDO::FETCH_ASSOC)) {
                    $otWasteDetail[] = $wRow;
                }
            } catch (Throwable) {}
            ?>
            <?php if ($useCloseSupervisorModal): ?>
                <div id="close_ot_supervisor_modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.62);z-index:99999;align-items:center;justify-content:center;padding:18px;box-sizing:border-box">
                    <div style="background:#FFFFFF;border-radius:12px;box-shadow:0 24px 48px rgba(0,0,0,0.3);width:100%;max-width:1100px;max-height:92vh;border:1px solid #CBD5E1;overflow:hidden;display:flex;flex-direction:column">
                        <div style="background:#991B1B;color:#FFFFFF;padding:16px 20px;font-size:17px;font-weight:bold;display:flex;align-items:center;justify-content:space-between;gap:12px">
                            <span><i class="fa fa-lock"></i> Cierre de OT con Supervisor</span>
                            <button type="button" onclick="closeCloseOtSupervisorModal()" style="background:transparent;border:none;color:#FFFFFF;font-size:22px;cursor:pointer;line-height:1">&times;</button>
                        </div>
                        <div style="padding:18px 20px 6px;color:#475569;font-size:13px">
                            OT #<b><?=htmlspecialchars((string)$agenda['prd_number'])?></b>. Ingrese las credenciales del supervisor para confirmar el cierre.
                        </div>
                        <div style="padding:0 20px 8px;overflow:auto;flex:1 1 auto">
                            <?php if ($isSeriPulpo): ?>
                                <div style="background:#F8FAFC;border:1px solid #CBD5E1;border-radius:5px;padding:14px 18px;margin-bottom:16px">
                                    <div style="font-size:13px;font-weight:bold;color:#1E293B;margin-bottom:8px">
                                        <i class="fa fa-layer-group" style="color:#008A87"></i> Balance de Producción Serigrafía Pulpo al Cierre
                                    </div>
                                    <table border="0" width="100%" cellpadding="4" cellspacing="0" style="font-size:12.5px">
                                        <tr>
                                            <td style="color:#64748B" width="220">Pasadas Frente acumuladas:</td>
                                            <td><b><?=number_format($prevFrente, 0, ',', '.')?></b> pasadas</td>
                                        </tr>
                                        <tr>
                                            <td style="color:#64748B">Pasadas Dorso acumuladas:</td>
                                            <td><b><?=number_format($prevDorso, 0, ',', '.')?></b> pasadas</td>
                                        </tr>
                                        <tr>
                                            <td style="color:#64748B">Bolsas terminadas completas:</td>
                                            <td><b style="color:#059669"><?=number_format($prevFinished, 0, ',', '.')?></b> bolsas</td>
                                        </tr>
                                        <tr>
                                            <td style="color:#64748B">Bolsas sin terminar al cierre:</td>
                                            <td>
                                                <?php if ($prevSurplusFrente > 0): ?>
                                                    <span style="color:#D97706;font-weight:bold">
                                                        <i class="fa fa-exclamation-triangle"></i> <?=number_format($prevSurplusFrente, 0, ',', '.')?> bolsas con Frente impreso (pendientes de dorso). Quedarán guardadas para el próximo ingreso.
                                                    </span>
                                                <?php elseif ($prevSurplusDorso > 0): ?>
                                                    <span style="color:#D97706;font-weight:bold">
                                                        <i class="fa fa-exclamation-triangle"></i> <?=number_format($prevSurplusDorso, 0, ',', '.')?> bolsas con Dorso impreso (pendientes de frente). Quedarán guardadas para el próximo ingreso.
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color:#059669;font-weight:bold">
                                                        <i class="fa fa-check-circle"></i> 0 bolsas pendientes (Frente y Dorso al día).
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <div style="background:#F8FAFC;border:1px solid #CBD5E1;border-radius:5px;padding:14px 18px;margin-bottom:16px">
                                <div style="font-size:13px;font-weight:bold;color:#1E293B;margin-bottom:8px">
                                    <i class="fa fa-balance-scale" style="color:#008A87"></i> Resumen Acumulado de la OT al Cierre
                                </div>
                                <table border="0" width="100%" cellpadding="4" cellspacing="0" style="font-size:12.5px">
                                    <?php if ($isFlexo): ?>
                                        <tr>
                                            <td style="color:#64748B" width="220">Metros Lineales terminados:</td>
                                            <td><b style="color:#059669"><?=number_format($prSummary['total_meters'] > 0 ? $prSummary['total_meters'] : $otSummaryMlin, 0, ',', '.')?></b> mtrs <?=($prSummary['total_weight_kg'] > 0 ? ' (' . number_format($prSummary['total_weight_kg'], 2, ',', '.') . ' kg)' : ($otSummaryKg > 0 ? ' (' . number_format($otSummaryKg, 2, ',', '.') . ' kg)' : ''))?></td>
                                        </tr>
                                        <?php if ($prSummary['rolls_count'] > 0): ?>
                                            <tr>
                                                <td style="color:#64748B">Bobinas Impresas producidas:</td>
                                                <td>
                                                    <b style="color:#1E293B"><?=number_format($prSummary['total_weight_kg'], 2, ',', '.')?> kg</b>
                                                    <span style="font-size:11.5px;color:#64748B;margin-left:6px">(<?=$prSummary['rolls_count']?> bobina<?=$prSummary['rolls_count'] > 1 ? 's' : ''?>: <?=implode(', ', array_map(fn($r) => $r['roll_code'], $prSummary['rolls_list']))?>)</span>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td style="color:#64748B" width="220">Producción total registrada:</td>
                                            <td><b style="color:#059669"><?=number_format($otSummaryProducedDisplay, 0, ',', '.')?></b> unidades / bolsas</td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td style="color:#64748B">Total Mermas acumuladas:</td>
                                        <td>
                                            <?php if ($otSummaryWasteKg > 0): ?>
                                                <b style="color:#DC2626"><?=number_format($otSummaryWasteKg, 2, ',', '.')?> kg</b>
                                                <?php if (!empty($otWasteBreakdown)): ?>
                                                    <span style="font-size:11.5px;color:#64748B;margin-left:6px">(<?=implode(', ', $otWasteBreakdown)?>)</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color:#64748B">0.00 kg</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;padding:14px 18px;margin-bottom:16px">
                                <div style="font-size:13px;font-weight:bold;color:#1E293B;margin-bottom:10px;display:flex;align-items:center;gap:8px">
                                    <i class="fa fa-industry" style="color:#0284C7"></i> Detalle de lo Producido en esta OT
                                </div>
                                <?php if ($isFlexo && !empty($prSummary['rolls_list'])): ?>
                                    <div style="overflow:auto;margin-bottom:12px">
                                        <table border="0" width="100%" cellpadding="6" cellspacing="0" style="font-size:12.5px;min-width:640px">
                                            <tr style="background:#F8FAFC;color:#334155">
                                                <td><b>Bobina</b></td>
                                                <td><b>Kg</b></td>
                                                <td><b>Metros</b></td>
                                            </tr>
                                            <?php foreach ($prSummary['rolls_list'] as $rollInfo): ?>
                                                <tr>
                                                    <td style="border-top:1px solid #E2E8F0"><b><?=htmlspecialchars((string)$rollInfo['roll_code'])?></b></td>
                                                    <td style="border-top:1px solid #E2E8F0"><?=number_format((float)($rollInfo['weight_kg'] ?? 0), 2, ',', '.')?></td>
                                                    <td style="border-top:1px solid #E2E8F0"><?=number_format((float)($rollInfo['meters'] ?? 0), 0, ',', '.')?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($otProducedDetail)): ?>
                                    <div style="overflow:auto">
                                        <table border="0" width="100%" cellpadding="6" cellspacing="0" style="font-size:12.5px;min-width:760px">
                                            <tr style="background:#F8FAFC;color:#334155">
                                                <td><b>Fecha</b></td>
                                                <td><b>Tipo</b></td>
                                                <?php if ($isSeri || $isSeriPulpo): ?>
                                                    <td><b>Contador</b></td>
                                                    <td><b>Img/Bast.</b></td>
                                                    <td><b>Unidades</b></td>
                                                <?php elseif ($isFlexo): ?>
                                                    <td><b>Metros</b></td>
                                                    <td><b>Kg</b></td>
                                                <?php else: ?>
                                                    <td><b>Unidades</b></td>
                                                    <td><b>Kg</b></td>
                                                <?php endif; ?>
                                                <?php if ($isSeri || $isSeriPulpo): ?>
                                                    <td><b>Kg</b></td>
                                                <?php endif; ?>
                                            </tr>
                                            <?php foreach ($otProducedDetail as $prodRow): ?>
                                                <?php
                                                $prodTs = (int)($prodRow['evt_crtdat'] ?? 0);
                                                $prodTypeLabel = ((string)($prodRow['evt_type'] ?? '') === 'prodsericolor') ? 'Producción color' : 'Producción';
                                                ?>
                                                <tr>
                                                    <td style="border-top:1px solid #E2E8F0"><?=($prodTs > 0 ? date('d/m/Y H:i', $prodTs) : '-')?></td>
                                                    <td style="border-top:1px solid #E2E8F0"><?=$prodTypeLabel?></td>
                                                    <?php if ($isSeri || $isSeriPulpo): ?>
                                                        <td style="border-top:1px solid #E2E8F0"><?=number_format((float)($prodRow['display_counter'] ?? 0), 0, ',', '.')?></td>
                                                        <td style="border-top:1px solid #E2E8F0"><?=number_format((float)($prodRow['display_devprints'] ?? 0), 0, ',', '.')?></td>
                                                        <td style="border-top:1px solid #E2E8F0"><b style="color:#059669"><?=number_format((float)($prodRow['display_units'] ?? 0), 0, ',', '.')?></b></td>
                                                        <td style="border-top:1px solid #E2E8F0"><?=number_format((float)($prodRow['prod_bobina_kg'] ?? 0), 2, ',', '.')?></td>
                                                    <?php elseif ($isFlexo): ?>
                                                        <td style="border-top:1px solid #E2E8F0"><?=number_format((float)($prodRow['evt_amount_metros_lineales'] ?? 0), 0, ',', '.')?></td>
                                                        <td style="border-top:1px solid #E2E8F0"><?=number_format((float)($prodRow['prod_bobina_kg'] ?? 0), 2, ',', '.')?></td>
                                                    <?php else: ?>
                                                        <td style="border-top:1px solid #E2E8F0"><b style="color:#059669"><?=number_format((float)($prodRow['display_units'] ?? 0), 0, ',', '.')?></b></td>
                                                        <td style="border-top:1px solid #E2E8F0"><?=number_format((float)($prodRow['prod_bobina_kg'] ?? 0), 2, ',', '.')?></td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size:12.5px;color:#64748B">No hay registros de producción acumulada para mostrar en esta OT.</div>
                                <?php endif; ?>
                            </div>

                            <div style="background:#FFFFFF;border:1px solid #FECACA;border-radius:8px;padding:14px 18px;margin-bottom:16px">
                                <div style="font-size:13px;font-weight:bold;color:#991B1B;margin-bottom:10px;display:flex;align-items:center;gap:8px">
                                    <i class="fa fa-trash" style="color:#DC2626"></i> Detalle de Mermas de la OT
                                </div>
                                <?php if (!empty($otWasteDetail)): ?>
                                    <div style="overflow:auto">
                                        <table border="0" width="100%" cellpadding="6" cellspacing="0" style="font-size:12.5px;min-width:760px">
                                            <tr style="background:#FEF2F2;color:#7F1D1D">
                                                <td><b>Fecha</b></td>
                                                <td><b>Tipo de merma</b></td>
                                                <td><b>Unidades</b></td>
                                                <td><b>Kg</b></td>
                                                <td><b>Metros</b></td>
                                                <td><b>Observación</b></td>
                                            </tr>
                                            <?php foreach ($otWasteDetail as $wasteRow): ?>
                                                <?php $wasteTs = (int)($wasteRow['evt_crtdat'] ?? 0); ?>
                                                <tr>
                                                    <td style="border-top:1px solid #FECACA"><?=($wasteTs > 0 ? date('d/m/Y H:i', $wasteTs) : '-')?></td>
                                                    <td style="border-top:1px solid #FECACA"><b><?=htmlspecialchars((string)($wasteRow['merma_title'] ?? 'Merma'))?></b></td>
                                                    <td style="border-top:1px solid #FECACA"><?=number_format((float)($wasteRow['evt_amount'] ?? 0), 0, ',', '.')?></td>
                                                    <td style="border-top:1px solid #FECACA;color:#B91C1C"><?=number_format((float)($wasteRow['evt_kgstounits'] ?? 0), 2, ',', '.')?></td>
                                                    <td style="border-top:1px solid #FECACA"><?=number_format((float)($wasteRow['evt_mtstounits'] ?? 0), 0, ',', '.')?></td>
                                                    <td style="border-top:1px solid #FECACA;color:#7F1D1D"><?=htmlspecialchars((string)($wasteRow['evt_comments'] ?? ''))?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size:12.5px;color:#64748B">No hay mermas registradas en esta OT.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <form method="post" action="/production/work-orders/close" id="close_ot_supervisor_form">
                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                            <input type="hidden" name="agid" value="<?=$agId?>">
                            <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">

                            <div style="padding:14px 20px 20px;border-top:1px solid #E2E8F0">
                                <div style="display:grid;grid-template-columns:1fr;gap:14px">
                                    <div>
                                        <label style="display:block;font-size:12.5px;font-weight:bold;color:#334155;margin-bottom:5px">Usuario Supervisor</label>
                                        <input type="text" name="sup_user" id="close_ot_sup_user" class="inptxt" style="width:100%" required autocomplete="off">
                                    </div>
                                    <div>
                                        <label style="display:block;font-size:12.5px;font-weight:bold;color:#334155;margin-bottom:5px">Contraseña</label>
                                        <input type="password" name="sup_pass" id="close_ot_sup_pass" class="inptxt" style="width:100%" required>
                                    </div>
                                    <div>
                                        <label style="display:block;font-size:12.5px;font-weight:bold;color:#334155;margin-bottom:5px">Motivo / Cierre</label>
                                        <select name="close_reason" class="inptxt" style="width:100%">
                                            <option value="completa">Producción Completada al 100%</option>
                                            <option value="parcial">Cierre Parcial por Cambio de Turno</option>
                                            <option value="interrumpida">Interrumpida por Falla Mecánica / Insumo</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:18px">
                                    <button type="button" class="btngrey" style="font-size:14px;padding:10px 18px;border:none;cursor:pointer" onclick="closeCloseOtSupervisorModal()">Cancelar</button>
                                    <button type="submit" class="btnred" style="font-size:14px;padding:10px 24px;border:none;cursor:pointer">
                                        <i class="fa fa-fw fa-check-circle"></i> Terminar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <script>
                (function () {
                    var modal = document.getElementById('close_ot_supervisor_modal');
                    var firstInput = document.getElementById('close_ot_sup_user');
                    window.openCloseOtSupervisorModal = function () {
                        if (!modal) return;
                        modal.style.display = 'flex';
                        if (firstInput) {
                            setTimeout(function () { firstInput.focus(); }, 50);
                        }
                    };
                    window.closeCloseOtSupervisorModal = function () {
                        if (!modal) return;
                        modal.style.display = 'none';
                    };
                    window.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape' && modal && modal.style.display === 'flex') {
                            window.closeCloseOtSupervisorModal();
                        }
                    });
                    <?php if ($mode === 'terminarot'): ?>
                    window.openCloseOtSupervisorModal();
                    <?php endif; ?>
                })();
                </script>
            <?php else: ?>
                <form method="post" action="/production/work-orders/close">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">

                    <table border="0" width="100%" cellpadding="8" cellspacing="0">
                    <tr>
                        <td class="tdleft" width="180">Usuario Supervisor</td>
                        <td class="tdnrm"><input type="text" name="sup_user" class="inptxt" style="width:100%" required autocomplete="off"></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Contraseña</td>
                        <td class="tdnrm"><input type="password" name="sup_pass" class="inptxt" style="width:100%" required></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Motivo / Cierre</td>
                        <td class="tdnrm">
                            <select name="close_reason" class="inptxt" style="width:100%">
                                <option value="completa">Producción Completada al 100%</option>
                                <option value="parcial">Cierre Parcial por Cambio de Turno</option>
                                <option value="interrumpida">Interrumpida por Falla Mecánica / Insumo</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm">
                            <button type="submit" class="btnred" style="font-size:14px;padding:10px 24px;width:100%">
                                <i class="fa fa-fw fa-check-circle"></i> Terminar
                            </button>
                        </td>
                    </tr>
                    </table>
                </form>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <?php if ($mode === '' && !$showSupervisorApproval && ($hasApertura || $hasAlis || $isSeri || $isRebobinadora)): ?>
        <!-- ============================================================== -->
        <!-- BARRA DE BOTONES DE ACCIÓN (editxid.php líneas 4647 - 4780)    -->
        <!-- Visible en Alistamiento, en Producción y en Pulpo/Serigrafía    -->
        <!-- ============================================================== -->
        <?php
        $isBarExpanded = ($hasAlis || $isSeri || $isRebobinadora);
        $hidePauseShortcut = ($isSeri || $isSeriPulpo);
        $actionBtnBaseStyle = 'border-radius:8px;padding:10px 14px;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;min-height:42px;box-shadow:0 1px 3px rgba(15,23,42,0.16);cursor:pointer';
        $actionBtnBlockStyle = $actionBtnBaseStyle . ($isBarExpanded ? '' : 'width:100%;box-sizing:border-box;text-align:center;display:flex;');
        $materialsActionLabel = $isRebobinadora ? 'Bobina' : (($isSeri || $isSeriPulpo) ? 'Insumos' : 'Materiales');
        ?>
        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px;margin-bottom:10px">
        <tr>
            <?php if (!$hasAlis && $hasApertura && !$isRebobinadora): ?>
                <td style="padding-right:4px">
                    <div class="btnorange" style="<?=$actionBtnBaseStyle?>;background:#E1A500" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=autocontrol&end_alis=1'">
                        <nobr><i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar&nbsp;</nobr>
                    </div>
                </td>
            <?php endif; ?>
            <?php if ($hasAlis || $isSeri || $isRebobinadora): ?>
                <?php if ($isRebobinadora): ?>
                    <td style="padding-right:4px">
                        <div class="btngreen" style="<?=$actionBtnBaseStyle?>" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod'">
                            <nobr><i class="fa fa-fw fa-cogs" style="color:white;"></i> Rebobinado&nbsp;</nobr>
                        </div>
                    </td>
                <?php else: ?>
                    <td style="padding-right:4px">
                        <?php if ($openProdId > 0): ?>
                            <div class="btnorange" style="<?=$actionBtnBaseStyle?>" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=production&refid=<?=$openProdId?>&submode=end'">
                                <nobr><i class="fa fa-fw fa-stop" style="color:white;"></i> Finalizar&nbsp;</nobr>
                            </div>
                        <?php else: ?>
                            <div class="btngreen" style="<?=$actionBtnBaseStyle?>" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=production&action=start'">
                                <nobr><i class="fa fa-fw fa-play" style="color:white;"></i> Iniciar&nbsp;</nobr>
                            </div>
                        <?php endif; ?>
                    </td>
                    <?php if ($isSelladora): ?>
                        <td style="padding-right:4px">
                            <div class="btngrey" style="<?=$actionBtnBaseStyle?>" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=embalaje'">
                                <nobr><i class="fa fa-fw fa-box" style="color:white;"></i> Embalaje&nbsp;</nobr>
                            </div>
                        </td>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <td style="<?=$isBarExpanded ? 'padding-right:4px;padding-left:4px' : 'padding-right:2px;width:25%'?>">
                <div class="btnblue" style="<?=$actionBtnBlockStyle?>;background-color:#293865" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=materiales'">
                    <nobr><i class="fa fa-fw <?= $isRebobinadora ? 'fa-dolly' : 'fa-cubes' ?>" style="color:white;"></i> <?=$materialsActionLabel?>&nbsp;</nobr>
                </div>
            </td>

            <?php if ($isFlexo): ?>
                <td style="<?=$isBarExpanded ? 'padding-right:4px;padding-left:4px' : 'padding-right:2px;width:25%'?>">
                    <div class="btnblue" style="<?=$actionBtnBlockStyle?>;background-color:#1D4ED8" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=tintas'">
                        <nobr><i class="fa fa-fw fa-tint" style="color:white;"></i> Tintas&nbsp;</nobr>
                    </div>
                </td>
            <?php endif; ?>

            <?php if (!$isPrinter && $hasAlis && !$isRebobinadora): ?>
                <td style="padding-right:4px;padding-left:4px">
                    <div class="btnorange" style="<?=$actionBtnBaseStyle?>" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=apertura'">
                        <nobr><i class="fa fa-fw fa-wrench" style="color:white;"></i> Apertura&nbsp;</nobr>
                    </div>
                </td>
            <?php endif; ?>

            <td style="<?=$isBarExpanded ? 'padding-right:4px;padding-left:4px' : 'padding:0 2px;width:25%'?>">
                <div class="btnred" style="<?=$actionBtnBlockStyle?>;background-color:#D64141" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=mantencion'">
                    <nobr><i class="fa fa-fw fa-tools" style="color:white;"></i> Mantención&nbsp;</nobr>
                </div>
            </td>

            <?php if (!$hidePauseShortcut): ?>
                <td style="<?=$isBarExpanded ? 'padding-right:4px;padding-left:4px' : 'padding:0 2px;width:25%'?>">
                    <div class="btngrey" style="<?=$actionBtnBlockStyle?>;background-color:#7F7F7F" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=pause'">
                        <nobr><i class="fa fa-fw fa-coffee" style="color:white;"></i> Pausa&nbsp;</nobr>
                    </div>
                </td>
            <?php endif; ?>

            <?php if ($isSeri || $isSeriPulpo): ?>
                <!-- BOTÓN COLACIÓN ESPECÍFICO SERIGRAFÍA Y PULPO -->
                <td style="padding-right:4px;padding-left:4px">
                    <div class="btnblue" style="<?=$actionBtnBaseStyle?>;background-color:#0284C7" onclick="openLunchModal()" title="Registrar horario de colación">
                        <nobr><i class="fa fa-fw fa-coffee" style="color:white;"></i> Colación&nbsp;</nobr>
                    </div>
                </td>
                <!-- BOTÓN SIN PRODUCCIÓN ESPECÍFICO SERIGRAFÍA Y PULPO -->
                <td style="padding-right:4px;padding-left:4px">
                    <?php if ($activeStandby !== null): ?>
                        <div class="btnorange" style="<?=$actionBtnBaseStyle?>;background-color:#EA580C" onclick="openStandbyEndModal()" title="Finalizar estado sin producción">
                            <nobr><i class="fa fa-fw fa-play-circle" style="color:white;"></i> Reanudar&nbsp;</nobr>
                        </div>
                    <?php else: ?>
                        <div class="btnorange" style="<?=$actionBtnBaseStyle?>;background-color:#D97706" onclick="openStandbyStartModal()" title="Informar máquina sin producción / espera de OT / mantención">
                            <nobr><i class="fa fa-fw fa-pause-circle" style="color:white;"></i> Espera&nbsp;</nobr>
                        </div>
                    <?php endif; ?>
                </td>
            <?php endif; ?>

        </tr>
        </table>
    <?php endif; ?>

    <!-- ============================================================== -->
    <!-- TABLA DE EVENTOS DE LA OT (editxid.php líneas 4785 - 4970)     -->
    <!-- SIEMPRE VISIBLE EN TODOS LOS ESTADOS (Alistamiento, Prod, etc) -->
    <!-- ============================================================== -->
    <div style="clear:both;height:12px"></div>
    <table border="0" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <div style="font-size:15px;font-weight:bold;color:#115452;margin-bottom:12px">
                <i class="fa fa-fw fa-list-alt"></i> Registro de Eventos de la OT
            </div>
            <table border="0" width="100%" cellpadding="6" cellspacing="0">
            <colgroup>
                <col width="140">
                <col width="160">
                <col width="160">
                <col width="90">
                <col width="120">
                <col>
                <col width="160">
            </colgroup>
            <thead>
                <tr>
                    <td class="tdheader">Evento</td>
                    <td class="tdheader">Inicio</td>
                    <td class="tdheader">Término</td>
                    <td class="tdheader" align="center">Tiempo</td>
                    <td class="tdheader" align="center">Cantidad</td>
                    <td class="tdheader">Comentarios</td>
                    <td class="tdheader" align="center">Opciones</td>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($events)): ?>
                <tr><td colspan="7" class="tdnrm" style="text-align:center;padding:20px;color:#999">No hay eventos registrados aún en esta orden.</td></tr>
            <?php else: ?>
                <?php foreach ($events as $ev): ?>
                    <?php
                    $evttype = match($ev['evt_type']) {
                        'prod', 'prodsericolor' => ((int)($ev['overrideembalaje_act'] ?? 0) === 1 ? 'Prod. Embalaje' : 'Producción'),
                        'apertura' => 'Alistamiento',
                        'merma' => 'Merma de Bobina',
                        'mantencion' => !empty($ev['repair_name']) ? 'Mantención: ' . htmlspecialchars((string)$ev['repair_name']) : 'Mantención',
                        'pause' => !empty($ev['pause_name']) ? 'Pausa: ' . htmlspecialchars((string)$ev['pause_name']) : 'Pausa',
                        default => ucfirst((string)$ev['evt_type'])
                    };

                    $start = (int)$ev['evt_crtdat'];
                    $end = (int)($ev['evt_enddat'] ?? 0);
                    $elapsed = ($end > 0 ? $end : time()) - $start;
                    $hours = (int)($elapsed / 3600);
                    $mins = (int)(($elapsed % 3600) / 60);

                    $amtStr = '-';
                    if (!empty($ev['evt_metrotype'])) {
                        if ($ev['evt_metrotype'] === 'metros_maquina') {
                            $amtStr = $fmt($ev['evt_amount_metros_maquina']) . ' m maq';
                        } else {
                            $amtStr = $fmt($ev['evt_amount_metros_lineales']) . ' m lin';
                        }
                        if ((float)($ev['prod_bobina_kg'] ?? 0) > 0) {
                            $amtStr .= '<br><span style="font-size:11px;color:#15803D;font-weight:bold">' . number_format((float)$ev['prod_bobina_kg'], 2, ',', '.') . ' kg</span>';
                        }
                    } elseif ((float)($ev['evt_amount'] ?? 0) > 0 || (float)($ev['evt_amount_pulpo_frente'] ?? 0) > 0 || (float)($ev['evt_amount_pulpo_dorso'] ?? 0) > 0) {
                        if ($ev['evt_type'] === 'merma') {
                            $amtStr = number_format((float)($ev['evt_amount'] ?? 0), 2, ',', '.') . ' kg';
                        } else {
                            $amtStr = $fmt($ev['evt_amount'] ?? 0) . ' bols';
                            if ((float)($ev['prod_bobina_kg'] ?? 0) > 0) {
                                $amtStr .= '<br><span style="font-size:11px;color:#15803D;font-weight:bold">' . number_format((float)$ev['prod_bobina_kg'], 2, ',', '.') . ' kg</span>';
                            }
                            if ((float)($ev['evt_amount_pulpo_frente'] ?? 0) > 0 || (float)($ev['evt_amount_pulpo_dorso'] ?? 0) > 0) {
                                $amtStr .= '<br><span style="font-size:11px;color:#64748B;font-weight:normal">(F: ' . $fmt($ev['evt_amount_pulpo_frente'] ?? 0) . ', D: ' . $fmt($ev['evt_amount_pulpo_dorso'] ?? 0) . ')</span>';
                            }
                        }
                    } elseif ((float)($ev['prod_bobina_kg'] ?? 0) > 0) {
                        $amtStr = '<span style="font-size:11px;color:#15803D;font-weight:bold">' . number_format((float)$ev['prod_bobina_kg'], 2, ',', '.') . ' kg</span>';
                    }
                    $targetMode = match($ev['evt_type']) {
                        'prod', 'merma' => 'production',
                        default => $ev['evt_type']
                    };
                    $isDurational = in_array($ev['evt_type'], ['prod', 'prodsericolor', 'apertura', 'pause', 'mantencion'], true);
                    $isSemiRoll = (strpos((string)($ev['evt_comments'] ?? ''), 'Bobina Semielaborada') !== false);
                    if ($isSemiRoll) {
                        $isDurational = false;
                    }
                    $isCompleted = (!$isDurational) || ($end > 0) || ((int)($ev['evt_status'] ?? 0) === 2);
                    ?>
                    <tr>
                        <td class="tdnrm"><b><?=$evttype?></b></td>
                        <td class="tdnrm"><?=date('d.m.Y H:i:s', $start)?></td>
                        <td class="tdnrm">
                            <?php if (!$isCompleted): ?>
                                <span style="color:#00A85A;font-weight:bold"><i class="fa fa-clock"></i> En curso</span>
                            <?php else: ?>
                                <?=date('d.m.Y H:i:s', $end > 0 ? $end : $start)?>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm" align="center"><?=$isDurational ? ($hours . 'h ' . $mins . 'm') : '-'?></td>
                        <td class="tdnrm" align="center"><b><?=$amtStr?></b></td>
                        <td class="tdnrm">
                            <?php if ($ev['evt_type'] === 'apertura'): ?>
                                <?php
                                $mFrom = (int)($ev['evt_medida_fromid'] ?? 0);
                                $mTo = (int)($ev['evt_medida_toid'] ?? 0);
                                $fromName = $medidasMap[$mFrom] ?? '';
                                $toName = $medidasMap[$mTo] ?? '';
                                $aid = (int)($ev['evt_idayudante'] ?? 0);
                                $ayudanteName = $helpersMap[$aid] ?? '';
                                ?>
                                <?php if ($fromName !== '' || $toName !== ''): ?>
                                    <span style="font-weight:bold;color:#115452">De <?=htmlspecialchars($fromName ?: '-')?> a <?=htmlspecialchars($toName ?: '-')?></span><br>
                                <?php endif; ?>
                                <?php if ($ayudanteName !== ''): ?>
                                    <span style="color:#555"><i class="fa fa-user-friends"></i> Ayudante: <?=htmlspecialchars($ayudanteName)?></span><br>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?=htmlspecialchars((string)($ev['evt_comments'] ?? ''))?>
                        </td>
                        <td class="tdnrm" align="center">
                            <?php if (!$isCompleted): ?>
                                <?php if ($ev['evt_type'] === 'apertura'): ?>
                                    <div class="btnorange" style="padding:4px 10px;font-size:12px;display:inline-block;cursor:pointer"
                                         onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=autocontrol&end_alis=1'">
                                        <i class="fa fa-stop"></i> Terminar
                                    </div>
                                <?php elseif (in_array($ev['evt_type'], ['prod', 'prodsericolor'], true)): ?>
                                    <div class="btnorange" style="padding:4px 10px;font-size:12px;display:inline-block;cursor:pointer"
                                         onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=production&refid=<?=$ev['id']?>&submode=end'">
                                        <i class="fa fa-stop"></i> Finalizar
                                    </div>
                                <?php elseif ($ev['evt_type'] === 'pause'): ?>
                                    <div class="btnorange" style="padding:4px 10px;font-size:12px;display:inline-block;cursor:pointer"
                                         onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=pause&refid=<?=$ev['id']?>'">
                                        <i class="fa fa-stop"></i> Terminar Pausa
                                    </div>
                                <?php elseif ($ev['evt_type'] === 'mantencion'): ?>
                                    <div class="btnorange" style="padding:4px 10px;font-size:12px;display:inline-block;cursor:pointer"
                                         onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=mantencion&refid=<?=$ev['id']?>'">
                                        <i class="fa fa-stop"></i> Terminar Mantención
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (in_array($ev['evt_type'], ['prod', 'prodsericolor'], true) && !$isSemiRoll): ?>
                                    <div class="btngrey" style="padding:4px 10px;font-size:12px;display:inline-block;cursor:pointer"
                                         onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=production&refid=<?=$ev['id']?>'">
                                        <i class="fa fa-edit"></i> Ver
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div class="btnred" style="padding:4px 8px;font-size:12px;display:inline-block;margin-left:4px;cursor:pointer"
                                  onclick="if(confirm('¿Seguro de eliminar este evento?')) location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=eventdelete&delid=<?=$ev['id']?>&worker_ot_id=<?=$workerOtId?>'">
                                <i class="fa fa-trash"></i>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            </table>
        </td>
    </tr>
    </table>

    <script>
    function calcPulpoUnits() {
        var prevF = <?= (int)($prevFrente ?? 0) ?>;
        var prevD = <?= (int)($prevDorso ?? 0) ?>;
        var prevFinished = <?= (int)($prevFinished ?? 0) ?>;

        var fInp = document.getElementById('idx_pulpo_frente');
        var dInp = document.getElementById('idx_pulpo_dorso');
        var fNew = fInp && fInp.value !== '' ? parseInt(fInp.value, 10) : 0;
        var dNew = dInp && dInp.value !== '' ? parseInt(dInp.value, 10) : 0;
        if (isNaN(fNew) || fNew < 0) fNew = 0;
        if (isNaN(dNew) || dNew < 0) dNew = 0;

        var fCum = prevF + fNew;
        var dCum = prevD + dNew;
        var finishedCum = Math.min(fCum, dCum);
        var finishedThisEvent = Math.max(0, finishedCum - prevFinished);

        var amtInp = document.getElementById('idx_pulpo_amount');
        if (amtInp) {
            amtInp.value = finishedThisEvent > 0 ? finishedThisEvent : (fNew > 0 || dNew > 0 ? '0' : '');
        }

        var surplusEl = document.getElementById('pulpo_surplus_preview');
        if (surplusEl) {
            var remF = fCum - finishedCum;
            var remD = dCum - finishedCum;
            if (remF > 0) {
                surplusEl.innerHTML = '<span style="color:#D97706;font-weight:bold"><i class="fa fa-info-circle"></i> Al guardar este tiraje, quedarán <b>' + remF.toLocaleString('es-CL') + '</b> bolsas con frente impreso (pendientes de dorso).</span>';
            } else if (remD > 0) {
                surplusEl.innerHTML = '<span style="color:#D97706;font-weight:bold"><i class="fa fa-info-circle"></i> Al guardar este tiraje, quedarán <b>' + remD.toLocaleString('es-CL') + '</b> bolsas con dorso impreso (pendientes de frente).</span>';
            } else {
                surplusEl.innerHTML = '<span style="color:#059669;font-weight:bold"><i class="fa fa-check-circle"></i> Al guardar este tiraje, ambas caras quedan balanceadas (0 pendientes).</span>';
            }
        }
    }
    function calcPulpoUnitsSoloFrente() {
        var fInp = document.getElementById('idx_pulpo_frente');
        var f = fInp && fInp.value !== '' ? parseInt(fInp.value, 10) : 0;
        var amtInp = document.getElementById('idx_pulpo_amount');
        if (amtInp) {
            amtInp.value = f > 0 ? f : '';
        }
    }

    if (document.getElementById('idx_pulpo_frente') && document.getElementById('idx_pulpo_dorso')) {
        calcPulpoUnits();
    }
    </script>
    <?php
    echo renderDesignPreviewModal();
    renderStandbyAndLunchModals($activeStandby, $machineInit, $currentOperatorName, '/production/work-orders/operate?agid=' . $agId);
    $content = ob_get_clean();
    renderProdwrkShell('Operar OT #' . $agenda['prd_number'], $content, 2, $currentOperatorName, $plantaName);
}

// =============================================================================
// MODALES COMUNES: REGISTRAR COLACIÓN Y EVENTO SIN PRODUCCIÓN
// =============================================================================

function renderStandbyAndLunchModals(?array $activeStandby, ?array $activeInit, string $operatorName, string $redirectTo = ''): void
{
?>
    <!-- MODAL 1: REGISTRAR COLACIÓN -->
    <div id="modal_lunch" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:99999;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:8px;max-width:480px;width:92%;padding:24px;box-shadow:0 10px 25px rgba(0,0,0,0.3)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;border-bottom:1px solid #e2e8f0;padding-bottom:10px">
                <div style="font-size:17px;font-weight:bold;color:#0369A1;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-coffee"></i> Registrar Horario de Colación
                </div>
                <button type="button" onclick="closeLunchModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#64748b">&times;</button>
            </div>
            <form method="post" action="/production/machines/lunch">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <?php if ($redirectTo !== ''): ?>
                    <input type="hidden" name="redirect_to" value="<?=htmlspecialchars($redirectTo)?>">
                <?php endif; ?>
                <?php if ($activeInit !== null): ?>
                    <input type="hidden" name="init_id" value="<?=(int)($activeInit['id'] ?? 0)?>">
                    <input type="hidden" name="machine_id" value="<?=(int)($activeInit['win_equipoid'] ?? $activeInit['equipo_id'] ?? 0)?>">
                    <input type="hidden" name="machine_name" value="<?=htmlspecialchars((string)($activeInit['equipo_name'] ?? ''))?>">
                <?php endif; ?>

                <div style="margin-bottom:12px">
                    <label style="display:block;font-size:12.5px;font-weight:bold;color:#475569;margin-bottom:4px">Operario</label>
                    <input type="text" class="inptxt" style="width:100%;background:#f8fafc" value="<?=htmlspecialchars($operatorName)?>" readonly>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                    <div>
                        <label style="display:block;font-size:12.5px;font-weight:bold;color:#475569;margin-bottom:4px">Hora Inicio *</label>
                        <input type="time" name="start_time" class="inptxt" style="width:100%" value="13:00" required>
                    </div>
                    <div>
                        <label style="display:block;font-size:12.5px;font-weight:bold;color:#475569;margin-bottom:4px">Hora Término *</label>
                        <input type="time" name="end_time" class="inptxt" style="width:100%" value="13:30" required>
                    </div>
                </div>
                <div style="margin-bottom:18px">
                    <label style="display:block;font-size:12.5px;font-weight:bold;color:#475569;margin-bottom:4px">Observaciones / Motivo (opcional)</label>
                    <textarea name="comments" class="inptxt" style="width:100%;height:55px" placeholder="Colación de turno"></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px">
                    <button type="button" class="btngrey" style="padding:8px 16px" onclick="closeLunchModal()">Cancelar</button>
                    <button type="submit" class="btnblue" style="background:#0284C7;padding:8px 20px;border:none"><i class="fa fa-save"></i> Guardar Colación</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: INICIAR EVENTO SIN PRODUCCIÓN -->
    <div id="modal_standby_start" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:99999;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:8px;max-width:500px;width:92%;padding:24px;box-shadow:0 10px 25px rgba(0,0,0,0.3)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;border-bottom:1px solid #e2e8f0;padding-bottom:10px">
                <div style="font-size:17px;font-weight:bold;color:#D97706;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-pause-circle"></i> Registrar Evento Sin Producción
                </div>
                <button type="button" onclick="closeStandbyStartModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#64748b">&times;</button>
            </div>
            <form method="post" action="/production/machines/standby">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="action" value="start">
                <?php if ($redirectTo !== ''): ?>
                    <input type="hidden" name="redirect_to" value="<?=htmlspecialchars($redirectTo)?>">
                <?php endif; ?>

                <div style="margin-bottom:12px">
                    <label style="display:block;font-size:12.5px;font-weight:bold;color:#475569;margin-bottom:4px">Motivo / Causa de Parada *</label>
                    <select name="reason" class="inptxt" style="width:100%" required>
                        <option value="Espera de Orden de Trabajo (OT)">Espera de Orden de Trabajo (OT)</option>
                        <option value="Falta de Material / Insumos / Sustratos">Falta de Material / Insumos / Sustratos</option>
                        <option value="Mantención Mecánica / Falla de Máquina">Mantención Mecánica / Falla de Máquina</option>
                        <option value="Limpieza y Preparación de Máquina">Limpieza y Preparación de Máquina</option>
                        <option value="Falta de Operador / Relevo">Falta de Operador / Relevo</option>
                        <option value="Corte de Energía / Suministro">Corte de Energía / Suministro</option>
                        <option value="Otro Motivo">Otro Motivo</option>
                    </select>
                </div>
                <div style="margin-bottom:12px">
                    <label style="display:block;font-size:12.5px;font-weight:bold;color:#475569;margin-bottom:4px">Hora Inicio *</label>
                    <input type="time" name="start_time" class="inptxt" style="width:160px" value="<?=date('H:i')?>" required>
                </div>
                <div style="margin-bottom:18px">
                    <label style="display:block;font-size:12.5px;font-weight:bold;color:#475569;margin-bottom:4px">Observaciones / Detalles</label>
                    <textarea name="comments" class="inptxt" style="width:100%;height:65px" placeholder="Indicar detalles del por qué la máquina no tiene producción..." required></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px">
                    <button type="button" class="btngrey" style="padding:8px 16px" onclick="closeStandbyStartModal()">Cancelar</button>
                    <button type="submit" class="btnorange" style="background:#D97706;padding:8px 20px;border:none"><i class="fa fa-play"></i> Iniciar Sin Producción</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: FINALIZAR EVENTO SIN PRODUCCIÓN -->
    <?php if ($activeStandby !== null): ?>
    <div id="modal_standby_end" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:99999;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:8px;max-width:460px;width:92%;padding:24px;box-shadow:0 10px 25px rgba(0,0,0,0.3)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;border-bottom:1px solid #e2e8f0;padding-bottom:10px">
                <div style="font-size:17px;font-weight:bold;color:#EA580C;display:flex;align-items:gap:8px">
                    <i class="fa fa-play-circle"></i> Finalizar Sin Producción
                </div>
                <button type="button" onclick="closeStandbyEndModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#64748b">&times;</button>
            </div>
            <form method="post" action="/production/machines/standby">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="action" value="end">
                <input type="hidden" name="standby_id" value="<?=(int)$activeStandby['id']?>">
                <?php if ($redirectTo !== ''): ?>
                    <input type="hidden" name="redirect_to" value="<?=htmlspecialchars($redirectTo)?>">
                <?php endif; ?>

                <div style="margin-bottom:12px;background:#fff7ed;padding:10px 14px;border-radius:5px;border:1px solid #fed7aa;font-size:13px;color:#9a3412">
                    <b>Motivo:</b> <?=htmlspecialchars((string)$activeStandby['reason'])?><br>
                    <b>Iniciado:</b> <?=htmlspecialchars((string)$activeStandby['start_time'])?>
                </div>
                <div style="margin-bottom:18px">
                    <label style="display:block;font-size:12.5px;font-weight:bold;color:#475569;margin-bottom:4px">Hora Término *</label>
                    <input type="time" name="end_time" class="inptxt" style="width:160px" value="<?=date('H:i')?>" required>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px">
                    <button type="button" class="btngrey" style="padding:8px 16px" onclick="closeStandbyEndModal()">Cancelar</button>
                    <button type="submit" class="btnorange" style="background:#EA580C;padding:8px 20px;border:none"><i class="fa fa-check"></i> Finalizar y Reanudar</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function openLunchModal() {
        var m = document.getElementById('modal_lunch');
        if (m) m.style.display = 'flex';
    }
    function closeLunchModal() {
        var m = document.getElementById('modal_lunch');
        if (m) m.style.display = 'none';
    }
    function openStandbyStartModal() {
        var m = document.getElementById('modal_standby_start');
        if (m) m.style.display = 'flex';
    }
    function closeStandbyStartModal() {
        var m = document.getElementById('modal_standby_start');
        if (m) m.style.display = 'none';
    }
    function openStandbyEndModal() {
        var m = document.getElementById('modal_standby_end');
        if (m) m.style.display = 'flex';
    }
    function closeStandbyEndModal() {
        var m = document.getElementById('modal_standby_end');
        if (m) m.style.display = 'none';
    }
    </script>
<?php
}

// =============================================================================
// OTRAS PANTALLAS: ASIGNACIÓN, NUEVA OT, ACTIVAS, HISTÓRICO
// =============================================================================

function renderProductionMachinesScreen(array $machines, ?array $activeInit, string $operatorName, string $plantaName, ?array $activeStandby = null): void
{
    ob_start();
    ?>
    <div style="font-size:20px;margin-bottom:10px"><b>Asignación máquina</b></div>

    <?php if (isset($_GET['lunch_saved'])): ?>
        <div style="background:#ECFDF5;border:1px solid #6EE7B7;color:#065F46;padding:12px 18px;border-radius:5px;margin-bottom:15px;font-size:13.5px">
            <i class="fa fa-check-circle" style="color:#059669;font-size:16px"></i> <b>Colación registrada exitosamente.</b> El horario ha quedado guardado sin necesidad de tener una OT en curso.
        </div>
    <?php elseif (isset($_GET['standby_started'])): ?>
        <div style="background:#FFFBEB;border:1px solid #FCD34D;color:#92400E;padding:12px 18px;border-radius:5px;margin-bottom:15px;font-size:13.5px">
            <i class="fa fa-pause-circle" style="color:#D97706;font-size:16px"></i> <b>Evento Sin Producción iniciado.</b> La máquina figura en estado de espera informada.
        </div>
    <?php elseif (isset($_GET['standby_ended'])): ?>
        <div style="background:#ECFDF5;border:1px solid #6EE7B7;color:#065F46;padding:12px 18px;border-radius:5px;margin-bottom:15px;font-size:13.5px">
            <i class="fa fa-play-circle" style="color:#059669;font-size:16px"></i> <b>Evento Sin Producción finalizado.</b> La máquina vuelve a estar disponible para operar.
        </div>
    <?php elseif (isset($_GET['machine_ended'])): ?>
        <div style="background:#F1F5F9;border:1px solid #CBD5E1;color:#334155;padding:12px 18px;border-radius:5px;margin-bottom:15px;font-size:13.5px">
            <i class="fa fa-check-circle" style="color:#64748B;font-size:16px"></i> Turno cerrado correctamente en la máquina.
        </div>
    <?php elseif (isset($_GET['error'])): ?>
        <div style="background:#FEF2F2;border:1px solid #FCA5A5;color:#991B1B;padding:12px 18px;border-radius:5px;margin-bottom:15px;font-size:13.5px">
            <i class="fa fa-exclamation-circle" style="color:#DC2626;font-size:16px"></i> <?=htmlspecialchars((string)$_GET['error'])?>
        </div>
    <?php endif; ?>

    <?php if ($activeStandby !== null): ?>
        <div style="background:#FFF7ED;border:1.5px solid #F97316;border-radius:5px;padding:12px 16px;margin-bottom:15px;color:#9A3412;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
            <div style="display:flex;align-items:center;gap:10px">
                <i class="fa fa-pause-circle" style="font-size:22px;color:#EA580C"></i>
                <div>
                    <span style="font-weight:bold;font-size:14px;text-transform:uppercase">MÁQUINA EN ESPERA / SIN PRODUCCIÓN: <?=htmlspecialchars((string)$activeStandby['reason'])?></span>
                    <div style="font-size:12.5px;color:#C2410C;margin-top:2px">
                        Iniciado a las <b><?=htmlspecialchars((string)$activeStandby['start_time'])?></b> por <b><?=htmlspecialchars((string)$activeStandby['operator_name'])?></b>
                        <?php if (!empty($activeStandby['comments'])): ?>
                            &bull; <i><?=htmlspecialchars((string)$activeStandby['comments'])?></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <button type="button" class="btnorange" style="background:#EA580C;padding:7px 16px;font-size:13px;cursor:pointer;border:none" onclick="openStandbyEndModal()">
                <i class="fa fa-play"></i> Finalizar Sin Producción
            </button>
        </div>
    <?php endif; ?>

    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
    <tr>
        <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                <div>
                    <i class="fa fa-fw fa-laptop-code"></i> Asignación máquina
                    <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                    <b>Iniciar / Terminar turno</b>
                </div>
                <?php if ($activeInit !== null): ?>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <span style="background:#E8F0EF;color:#115452;padding:6px 12px;border-radius:4px;font-weight:bold">
                            <i class="fa fa-check-circle" style="color:#00A85A"></i> Máquina Activa: <?=htmlspecialchars((string)$activeInit['equipo_name'])?>
                        </span>
                        <a href="/production/work-orders/new" class="btngreen" style="padding:6px 12px;text-decoration:none"><i class="fa fa-play"></i> Ir a OTs</a>
                        
                        <!-- BOTÓN 1: REGISTRAR COLACIÓN SIN ESTAR PRODUCIENDO -->
                        <button type="button" class="btnblue" style="background:#0284C7;padding:6px 12px;cursor:pointer;border:none" onclick="openLunchModal()" title="Ingresar colación sin tener que estar produciendo">
                            <i class="fa fa-coffee"></i> Registrar Colación
                        </button>

                        <!-- BOTÓN 2: EVENTO SIN PRODUCCIÓN -->
                        <?php if ($activeStandby !== null): ?>
                            <button type="button" class="btnorange" style="background:#EA580C;padding:6px 12px;cursor:pointer;border:none" onclick="openStandbyEndModal()" title="Finalizar estado sin producción">
                                <i class="fa fa-play-circle"></i> Finalizar Sin Prod.
                            </button>
                        <?php else: ?>
                            <button type="button" class="btnorange" style="background:#D97706;padding:6px 12px;cursor:pointer;border:none" onclick="openStandbyStartModal()" title="Informar máquina sin producción / espera de OT / mantención">
                                <i class="fa fa-pause-circle"></i> Sin Producción
                            </button>
                        <?php endif; ?>

                        <!-- BOTÓN 3: CERRAR TURNO -->
                        <form method="post" action="/production/machines/end" style="margin:0;display:inline-block">
                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                            <button type="submit" class="btnred" style="padding:6px 12px" onclick="return confirm('¿Seguro que deseas cerrar turno en esta máquina?')">Cerrar turno</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    </table>

    <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
        <div style="font-size:15px;font-weight:bold;color:#115452;margin-bottom:12px">
            <i class="fa fa-fw fa-cogs"></i> Seleccionar Máquina para Operar
        </div>
        <table border="0" width="100%" cellpadding="8" cellspacing="0">
        <colgroup><col width="180"><col><col width="220"><col width="140"></colgroup>
        <thead>
            <tr>
                <td class="tdheader">Tipo Máquina</td>
                <td class="tdheader">Nombre Máquina</td>
                <td class="tdheader">Características</td>
                <td class="tdheader" align="center">Acción</td>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($machines as $m): ?>
            <?php $isThisActive = $activeInit !== null && (int)$activeInit['win_equipoid'] === (int)$m['id']; ?>
            <tr>
                <td class="tdnrm"><b><?=htmlspecialchars((string)($m['type_ant_title'] ?? 'General'))?></b></td>
                <td class="tdnrm" style="font-size:14px;font-weight:bold"><?=htmlspecialchars((string)$m['equipo_name'])?></td>
                <td class="tdnrm" style="color:#666">
                    <?php
                    $feats = [];
                    if ((int)($m['equipo_prod_isprinter_flexo'] ?? 0) === 1) $feats[] = 'Flexografía';
                    if ((int)($m['equipo_prod_isprinter_seri'] ?? 0) === 1) $feats[] = 'Serigrafía / Pulpo';
                    if ((int)($m['type_createstock_act'] ?? 0) === 1) $feats[] = 'Genera Stock';
                    echo !empty($feats) ? implode(' · ', $feats) : 'Estándar';
                    ?>
                </td>
                <td class="tdnrm" align="center">
                    <?php if ($isThisActive): ?>
                        <span class="btngreen" style="padding:6px 12px;cursor:default"><i class="fa fa-check"></i> Activa</span>
                    <?php else: ?>
                        <form method="post" action="/production/machines/init" style="margin:0">
                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                            <input type="hidden" name="equipo_id" value="<?=$m['id']?>">
                            <button type="submit" class="btnblue" style="padding:6px 14px">Iniciar</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
    </div>

    <?php
    renderStandbyAndLunchModals($activeStandby, $activeInit, $operatorName, '/production/machines');
    $content = ob_get_clean();
    renderProdwrkShell('Asignación de Máquina', $content, 0, $operatorName, $plantaName);
}

function renderProductionNeedMachineScreen(string $operatorName, string $plantaName): void
{
    ob_start();
    ?>
    <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:30px;text-align:center;max-width:600px;margin:30px auto">
        <div style="font-size:36px;color:#E1A500;margin-bottom:15px"><i class="fa fa-exclamation-triangle"></i></div>
        <div style="font-size:18px;font-weight:bold;color:#333;margin-bottom:10px">No tienes una máquina asignada actualmente</div>
        <div style="color:#666;font-size:13px;margin-bottom:20px">Debes iniciar turno en una máquina para poder visualizar y comenzar órdenes de trabajo.</div>
        <a href="/production/machines" class="btngreen" style="padding:10px 24px;font-size:14px;text-decoration:none"><i class="fa fa-arrow-right"></i> Ir a Asignación de Máquinas</a>
    </div>
    <?php
    $content = ob_get_clean();
    renderProdwrkShell('Asignación Requerida', $content, 1, $operatorName, $plantaName);
}

function renderDesignPreviewModal(): string
{
    return <<<HTML
    <!-- MODAL PREVISUALIZACIÓN DE DISEÑO / FOTO DE BOLSA -->
    <div id="modal_design_preview" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.75);backdrop-filter:blur(2px);z-index:99999;align-items:center;justify-content:center;padding:16px" onclick="if(event.target===this)closeDesignModal()">
        <div style="background:#FFFFFF;border-radius:10px;max-width:680px;width:96%;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 25px 50px -12px rgba(0,0,0,0.45);overflow:hidden;border:1px solid #CBD5E1">
            <div style="padding:12px 18px;background:#0F172A;color:#FFFFFF;display:flex;justify-content:space-between;align-items:center">
                <div style="font-weight:bold;font-size:15px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-camera" style="color:#38BDF8"></i>
                    <span id="modal_design_title">Foto de Diseño de la Bolsa</span>
                </div>
                <button type="button" onclick="closeDesignModal()" style="background:none;border:none;color:#94A3B8;font-size:24px;cursor:pointer;line-height:1">&times;</button>
            </div>
            <div style="padding:16px;overflow:auto;text-align:center;background:#F8FAFC;flex:1;display:flex;align-items:center;justify-content:center;min-height:280px">
                <img id="modal_design_img" src="" alt="Diseño de bolsa" style="max-width:100%;max-height:60vh;object-fit:contain;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,0.12);background:#FFFFFF;border:1px solid #E2E8F0">
            </div>
            <div style="padding:12px 18px;background:#FFFFFF;border-top:1px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                <div>
                    <div id="modal_design_subtitle" style="font-size:13px;font-weight:600;color:#1E293B"></div>
                    <div id="modal_design_meta" style="font-size:11.5px;color:#64748B"></div>
                </div>
                <div style="display:flex;gap:8px">
                    <a id="modal_design_link" href="#" target="_blank" class="btn" style="background:#0284C7;color:#FFFFFF;padding:6px 14px;border-radius:4px;font-size:12px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px">
                        <i class="fa fa-external-link-alt"></i> Ver en pestaña nueva
                    </a>
                    <button type="button" class="btngrey" style="padding:6px 14px;font-size:12px" onclick="closeDesignModal()">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    function openDesignModal(imgUrl, otNumber, custName, designName, medInfo) {
        var modal = document.getElementById('modal_design_preview');
        if (!modal) return;
        document.getElementById('modal_design_img').src = imgUrl;
        document.getElementById('modal_design_title').textContent = 'Diseño de Bolsa · OT #' + otNumber;
        document.getElementById('modal_design_subtitle').textContent = custName ? ('Cliente: ' + custName) : '';
        var meta = '';
        if (designName) meta += 'Diseño: ' + designName;
        if (medInfo) meta += (meta ? ' · ' : '') + 'Medidas: ' + medInfo;
        document.getElementById('modal_design_meta').textContent = meta;
        document.getElementById('modal_design_link').href = imgUrl;
        modal.style.display = 'flex';
    }
    function closeDesignModal() {
        var modal = document.getElementById('modal_design_preview');
        if (modal) {
            modal.style.display = 'none';
            document.getElementById('modal_design_img').src = '';
        }
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeDesignModal();
    });
    </script>
HTML;
}

function renderProductionNewWorkOrderScreen(
    array $activeInit,
    array $scheduledOrders,
    ?array $activeOt,
    string $operatorName,
    string $plantaName,
    ?array $activeStandby = null,
    ?string $dateFilter = null,
    array $unscheduledOrders = [],
    string $activeTab = 'scheduled',
    ?string $searchQuery = null
): void {
    $equipoTypeId = (int)($activeInit['equipo_type_id'] ?? 0);
    $isRebobinadora = ($equipoTypeId === 12 || stripos((string)($activeInit['type_ant_title'] ?? ''), 'rebobin') !== false || stripos((string)($activeInit['equipo_name'] ?? ''), 'rebobin') !== false);
    $isFlexo = ($equipoTypeId === 7
        || (int)($activeInit['equipo_prod_isprinter_flexo'] ?? 0) === 1
        || stripos((string)($activeInit['type_ant_title'] ?? ''), 'flexo') !== false
        || stripos((string)($activeInit['equipo_name'] ?? ''), 'flexo') !== false);
    $isSeri = ((int)($activeInit['equipo_prod_isprinter_seri'] ?? 0) === 1)
        || ($equipoTypeId === 11 || $equipoTypeId === 8 || $equipoTypeId === 22)
        || stripos((string)($activeInit['type_ant_title'] ?? ''), 'seri') !== false
        || stripos((string)($activeInit['equipo_name'] ?? ''), 'seri') !== false
        || stripos((string)($activeInit['equipo_name'] ?? ''), 'pulpo') !== false;

    $machineName = (string)($activeInit['equipo_name'] ?? ($isFlexo ? 'Flexografía' : ($isRebobinadora ? 'Rebobinadora' : ($isSeri ? 'Serigrafía' : 'Máquina'))));

    ob_start();
    ?>
    <!-- ============================================================== -->
    <!-- CABECERA PRINCIPAL Y ACCIONES RÁPIDAS                          -->
    <!-- ============================================================== -->
    <div style="font-size:20px;margin-bottom:10px"><b>Ordenes de trabajo</b></div>
    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px">
    <tr>
        <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                <div>
                    <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
                    <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                    <b>Iniciar nueva OT</b> en máquina <b><?=htmlspecialchars($machineName)?></b>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <a href="/production/materials/request" class="btngreen" style="padding:5px 12px;font-size:12px;text-decoration:none;border-radius:4px;display:inline-flex;align-items:center;gap:6px" title="Solicitar o cargar bobinas a la máquina">
                        <i class="fa fa-dolly"></i> Solicitar Bobinas
                    </a>
                    <button type="button" class="btnblue" style="background:#0284C7;padding:5px 12px;font-size:12px;cursor:pointer;border:none;border-radius:4px" onclick="openLunchModal()" title="Registrar horario de colación">
                        <i class="fa fa-coffee"></i> Registrar Colación
                    </button>
                    <?php if ($activeStandby !== null): ?>
                        <button type="button" class="btnorange" style="background:#EA580C;padding:5px 12px;font-size:12px;cursor:pointer;border:none;border-radius:4px" onclick="openStandbyEndModal()" title="Finalizar estado sin producción">
                            <i class="fa fa-play-circle"></i> Finalizar Sin Producción
                        </button>
                    <?php else: ?>
                        <button type="button" class="btnorange" style="background:#D97706;padding:5px 12px;font-size:12px;cursor:pointer;border:none;border-radius:4px" onclick="openStandbyStartModal()" title="Informar máquina sin producción / espera de OT / mantención">
                            <i class="fa fa-pause-circle"></i> Sin Producción
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </td>
    </tr>
    </table>

    <?php if ($activeStandby !== null): ?>
        <div style="background:#FFF7ED;border:1.5px solid #F97316;border-radius:5px;padding:12px 16px;margin-bottom:12px;color:#9A3412;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
            <div style="display:flex;align-items:center;gap:10px">
                <i class="fa fa-pause-circle" style="font-size:22px;color:#EA580C"></i>
                <div>
                    <span style="font-weight:bold;font-size:14px;text-transform:uppercase">MÁQUINA EN ESPERA / SIN PRODUCCIÓN: <?=htmlspecialchars((string)$activeStandby['reason'])?></span>
                    <div style="font-size:12.5px;color:#C2410C;margin-top:2px">
                        Iniciado a las <b><?=htmlspecialchars((string)$activeStandby['start_time'])?></b> por <b><?=htmlspecialchars((string)$activeStandby['operator_name'])?></b>
                        <?php if (!empty($activeStandby['comments'])): ?>
                            &bull; <i><?=htmlspecialchars((string)$activeStandby['comments'])?></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <button type="button" class="btnorange" style="background:#EA580C;padding:7px 16px;font-size:13px;cursor:pointer;border:none" onclick="openStandbyEndModal()">
                <i class="fa fa-play"></i> Finalizar Sin Producción
            </button>
        </div>
    <?php endif; ?>

    <!-- PESTAÑA: PROGRAMADAS EN AGENDA -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px">
        <div style="display:flex;align-items:center;gap:6px">
            <a href="/production/work-orders/new?tab=scheduled<?= !empty($dateFilter) ? '&plan_date=' . urlencode($dateFilter) : '' ?>"
               style="padding:8px 16px;border-radius:6px;font-size:13px;font-weight:bold;text-decoration:none;display:inline-flex;align-items:center;gap:8px;border:1.5px solid <?= $activeTab === 'scheduled' ? '#0F766E' : '#CBD5E1' ?>;background:<?= $activeTab === 'scheduled' ? '#0F766E' : '#FFFFFF' ?>;color:<?= $activeTab === 'scheduled' ? '#FFFFFF' : '#475569' ?>;box-shadow:0 1px 3px rgba(0,0,0,0.06)">
                <i class="fa fa-calendar-check"></i> OTs Programadas en Agenda
                <span style="background:<?= $activeTab === 'scheduled' ? '#115E59' : '#E2E8F0' ?>;color:<?= $activeTab === 'scheduled' ? '#FFFFFF' : '#334155' ?>;padding:2px 8px;border-radius:10px;font-size:11px">
                    <?= count($scheduledOrders) ?>
                </span>
            </a>
        </div>
    </div>

    <?php if ($activeTab === 'unscheduled'): ?>
        <!-- ============================================================== -->
        <!-- VISTA DE PRODUCTOS FUERA DE PLANIFICACIÓN (BACKLOG)           -->
        <!-- ============================================================== -->
        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px;overflow-x:auto">
            <div style="background:#FFFBEB;border:1.5px solid #FCD34D;border-radius:6px;padding:12px 16px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
                <div>
                    <div style="font-size:14.5px;font-weight:bold;color:#92400E;display:flex;align-items:center;gap:8px">
                        <i class="fa fa-exclamation-triangle" style="font-size:16px"></i>
                        <span>Productos Aprobados Fuera de Planificación de Agenda</span>
                        <span style="background:#FEF3C7;color:#92400E;border:1px solid #F59E0B;font-size:12px;padding:2px 8px;border-radius:12px">
                            <?= count($unscheduledOrders) ?> órdenes disponibles
                        </span>
                    </div>
                    <div style="font-size:12.5px;color:#78350F;margin-top:3px">
                        Estas órdenes cuentan con ficha técnica aprobada en ERP, pero aún no están calendarizadas en la agenda de esta máquina.
                        Puedes iniciar cualquiera de ellas de urgencia haciendo clic en <b>"Iniciar OT"</b>.
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <form method="get" action="/production/work-orders/new" style="margin:0;display:inline-flex;align-items:center;gap:6px">
                        <input type="hidden" name="tab" value="unscheduled">
                        <input type="text" name="search" value="<?=htmlspecialchars((string)$searchQuery)?>" placeholder="Buscar OT, CC, cliente..." style="padding:5px 10px;border:1px solid #CBD5E1;border-radius:4px;font-size:12px;width:220px">
                        <button type="submit" class="btnblue" style="padding:5px 10px;font-size:12px"><i class="fa fa-search"></i> Buscar</button>
                        <?php if (!empty($searchQuery)): ?>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <table border="0" width="100%" cellpadding="8" cellspacing="0" style="min-width:1100px">
            <colgroup>
                <col width="90">
                <col width="80">
                <col width="160">
                <col width="180">
                <col width="75">
                <col width="110">
                <?php if ($isFlexo): ?>
                    <col width="140">
                    <col width="85">
                    <col width="90">
                <?php elseif ($isSeri): ?>
                    <col width="140">
                    <col width="90">
                <?php elseif ($isRebobinadora): ?>
                    <col width="150">
                    <col width="90">
                <?php else: ?>
                    <col width="130">
                <?php endif; ?>
                <col width="90">
                <col width="65">
                <col width="120">
            </colgroup>
            <thead>
                <tr>
                    <td class="tdheader">N° C.C.</td>
                    <td class="tdheader">N° O.T.</td>
                    <td class="tdheader">Cliente</td>
                    <td class="tdheader">Diseño / Producto</td>
                    <td class="tdheader" align="center">Material</td>
                    <td class="tdheader">Medidas</td>
                    <?php if ($isFlexo): ?>
                        <td class="tdheader">Colores</td>
                        <td class="tdheader" align="center">Rodillo [Z]</td>
                        <td class="tdheader" align="right">Metros</td>
                    <?php elseif ($isSeri): ?>
                        <td class="tdheader">Colores</td>
                        <td class="tdheader" align="right">Contador</td>
                    <?php elseif ($isRebobinadora): ?>
                        <td class="tdheader">Actividad</td>
                        <td class="tdheader" align="right">Metros</td>
                    <?php else: ?>
                        <td class="tdheader">Tipo Impresión</td>
                    <?php endif; ?>
                    <td class="tdheader" align="right">Planificado</td>
                    <td class="tdheader" align="right">Producidas</td>
                    <td class="tdheader" align="center">Imagen</td>
                    <td class="tdheader" align="center">Acción</td>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($unscheduledOrders)): ?>
                <tr>
                    <td colspan="13" class="tdnrm" style="text-align:center;padding:35px 20px;color:#64748B">
                        <i class="fa fa-check-circle" style="font-size:28px;color:#10B981;margin-bottom:8px;display:block"></i>
                        <?php if (!empty($searchQuery)): ?>
                            No se encontraron órdenes fuera de planificación que coincidan con "<b><?=htmlspecialchars($searchQuery)?></b>".
                        <?php else: ?>
                            ¡Excelente! No hay órdenes pendientes fuera de planificación compatibles con esta máquina.
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($unscheduledOrders as $uo):
                    $imgHash = trim((string)($uo['fab_design_imagehash'] ?? $uo['item_img_hash'] ?? ''));
                    $hasImg = ($imgHash !== '' && $imgHash !== 'dummy');
                    $imgUrl = $hasImg ? ('/production/design-image?folder=docs.order&hash=' . rawurlencode($imgHash)) : '';
                    
                    $prodSku = trim((string)($uo['item_number_prod'] ?? ''));
                    $prodTitle = trim((string)($uo['item_title'] ?? ''));
                    $designName = trim((string)($uo['fab_design_name'] ?? ''));
                    $fullDesign = ($prodSku !== '' && $prodTitle !== '') ? "{$prodSku} | {$prodTitle}" : ($prodTitle ?: ($prodSku ?: ($designName ?: '-')));
                    
                    $w = (int)($uo['fab_med_width'] ?? 0);
                    $h = (int)($uo['fab_med_height'] ?? 0);
                    $fuelle = (int)($uo['fab_med_fuelle'] ?? 0);
                    $medidasStr = $w > 0 ? ("{$w} x {$h}" . ($fuelle > 0 ? " + {$fuelle}" : "") . " cm") : ((float)($uo['req_operador_tela_width'] ?? 0) > 0 ? (float)$uo['req_operador_tela_width'] . ' cm' : '-');
                    $mat = !empty($uo['fab_type']) ? (string)$uo['fab_type'] : 'TNT';
                    
                    $colores = [];
                    for ($i = 1; $i <= 6; $i++) {
                        $c = trim((string)($uo["fab_print_colordesc_{$i}"] ?? ''));
                        if ($c !== '') {
                            $colores[] = $c;
                        }
                    }
                    
                        $cantBolsas = (float)($uo['item_amount'] ?? 0);
                        $producidas = (float)($uo['total_produced'] ?? 0);
                    $metros = (float)($uo['flexo_metros'] ?? 0);
                    $z = (int)($uo['flexo_z'] ?? 0);
                    $contador = (float)($uo['seri_contador'] ?? 0);
                    $actividad = !empty($uo['rebo_distribucion']) && $uo['rebo_distribucion'] !== '-' ? $uo['rebo_distribucion'] : (!empty($uo['req_rebo_type']) ? ('Corte ' . ucfirst((string)$uo['req_rebo_type'])) : 'Corte / Rebobinado');
                ?>
                    <tr onmouseover="this.style.backgroundColor='#FEF9C3'" onmouseout="this.style.backgroundColor='transparent'">
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$uo['req_number'])?></b></td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$uo['prd_number'])?></b></td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$uo['cust_name'])?></b></td>
                        <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($fullDesign)?></td>
                        <td class="tdnrm" align="center">
                            <span style="background:#F1F5F9;color:#334155;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600"><?=htmlspecialchars($mat)?></span>
                        </td>
                        <td class="tdnrm" style="white-space:nowrap"><?=htmlspecialchars($medidasStr)?></td>
                        <?php if ($isFlexo): ?>
                            <td class="tdnrm">
                                <?php if (empty($colores)): ?>
                                    <span style="color:#94A3B8;font-size:12px"><i>Sin colores</i></span>
                                <?php else: ?>
                                    <div style="display:flex;flex-direction:column;gap:2px">
                                        <?php foreach ($colores as $idx => $colorName): ?>
                                            <span style="font-size:11px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:3px;padding:1px 5px;color:#334155;white-space:nowrap">
                                                <b><?=($idx + 1)?>:</b> <?=htmlspecialchars($colorName)?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="center">
                                <?php if ($z > 0): ?>
                                    <span style="background:#FEF3C7;color:#92400E;padding:3px 8px;border-radius:4px;font-weight:700;font-size:12px">Z = <?=$z?></span>
                                <?php else: ?>
                                    <span style="color:#94A3B8;font-size:12px">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="right"><b><?=number_format($metros, 0, ',', '.')?></b> m</td>
                        <?php elseif ($isSeri): ?>
                            <td class="tdnrm">
                                <?php if (empty($colores)): ?>
                                    <span style="color:#94A3B8;font-size:12px"><i>Sin colores</i></span>
                                <?php else: ?>
                                    <div style="display:flex;flex-direction:column;gap:2px">
                                        <?php foreach ($colores as $idx => $colorName): ?>
                                            <span style="font-size:11px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:3px;padding:1px 5px;color:#334155;white-space:nowrap">
                                                <b><?=($idx + 1)?>:</b> <?=htmlspecialchars($colorName)?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="right"><b style="color:#0F766E"><?=number_format($contador, 0, ',', '.')?></b></td>
                        <?php elseif ($isRebobinadora): ?>
                            <td class="tdnrm"><span style="color:#0F766E;font-weight:600;font-size:12.5px"><i class="fa fa-cut"></i> <?=htmlspecialchars($actividad)?></span></td>
                            <td class="tdnrm" align="right"><b><?=number_format($metros, 0, ',', '.')?></b> m</td>
                        <?php else: ?>
                            <td class="tdnrm"><?=htmlspecialchars((string)$uo['fab_printtype'])?></td>
                        <?php endif; ?>
                        <td class="tdnrm" align="right"><b><?=number_format($cantBolsas, 0, ',', '.')?></b></td>
                        <td class="tdnrm" align="right"><b style="color:#0F766E"><?=number_format($producidas, 0, ',', '.')?></b></td>
                        <td class="tdnrm" align="center">
                            <?php if ($hasImg): ?>
                                <div style="display:inline-flex;align-items:center;gap:4px;cursor:pointer" onclick="openDesignModal('<?=htmlspecialchars($imgUrl)?>', '<?=htmlspecialchars((string)$uo['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$uo['cust_name']))?>', '<?=htmlspecialchars(addslashes($fullDesign))?>', '<?=htmlspecialchars($medidasStr)?>')" title="Click para ver foto del diseño">
                                    <img src="<?=htmlspecialchars($imgUrl)?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #CBD5E1;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
                                    <i class="fa fa-search-plus" style="color:#0284C7;font-size:11px"></i>
                                </div>
                            <?php else: ?>
                                <span style="color:#94A3B8;font-size:12px"><i>Sin foto</i></span>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm" align="center">
                            <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                                <form method="post" action="/production/work-orders/init-unscheduled" style="margin:0">
                                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                    <input type="hidden" name="prd_id" value="<?=$uo['prd_id']?>">
                                    <button type="submit" class="btngreen" style="padding:6px 12px;background:#15803D" onclick="return confirm('¿Confirmas incorporar esta OT no planificada (#<?=htmlspecialchars((string)$uo['prd_number'])?>) a esta máquina e iniciar producción ahora?');">
                                        <i class="fa fa-play"></i> Iniciar OT
                                    </button>
                                </form>
                                <a href="/production/materials/request?prd_id=<?=$uo['prd_id']?>" class="btnblue" style="padding:6px 10px;font-size:11px;text-decoration:none" title="Solicitar o cargar bobinas para esta OT no planificada">
                                    <i class="fa fa-dolly"></i> Solicitar Bobina
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            </table>
        </div>
    <?php else: ?>
        <!-- ============================================================== -->
        <!-- VISTA DE PRODUCTOS PROGRAMADOS EN AGENDA                       -->
        <!-- ============================================================== -->
        <?php if (empty($scheduledOrders)): ?>
            <div style="background:#EFF6FF;border:1px solid #BFDBFE;color:#1E40AF;padding:14px 18px;border-radius:6px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div style="display:flex;align-items:center;gap:10px;font-size:13.5px">
                    <i class="fa fa-info-circle" style="font-size:22px;color:#3B82F6"></i>
                    <div>
                        <b>No hay órdenes de trabajo programadas para esta fecha.</b>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($isRebobinadora): ?>
            <!-- DISEÑO REBOBINADORA: OTs Programadas -->
            <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
                <div style="font-size:15px;font-weight:bold;color:#115452;margin-bottom:12px">
                    <i class="fa fa-fw fa-tasks"></i> OTs Programadas para esta Máquina
                </div>
                <table border="0" width="100%" cellpadding="8" cellspacing="0">
                <colgroup>
                    <col width="95">
                    <col width="85">
                    <col>
                    <col width="120">
                    <col width="85">
                    <col width="160">
                    <col width="120">
                    <col width="90">
                    <col width="115">
                </colgroup>
                <thead>
                    <tr>
                        <td class="tdheader">N° C.C.</td>
                        <td class="tdheader">N° O.T.</td>
                        <td class="tdheader">Cliente</td>
                        <td class="tdheader" align="center">Diseño</td>
                        <td class="tdheader" align="center">Material</td>
                        <td class="tdheader">Actividad</td>
                        <td class="tdheader">Medidas</td>
                        <td class="tdheader" align="right">Metros</td>
                        <td class="tdheader" align="center">Acción</td>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($scheduledOrders)): ?>
                    <tr><td colspan="9" class="tdnrm" style="text-align:center;padding:25px;color:#999">No hay órdenes de trabajo programadas para esta máquina.</td></tr>
                <?php else: ?>
                    <?php foreach ($scheduledOrders as $so):
                        $imgHash = trim((string)($so['fab_design_imagehash'] ?? $so['item_img_hash'] ?? ''));
                        $hasImg = ($imgHash !== '' && $imgHash !== 'dummy');
                        $imgUrl = $hasImg ? ('/production/design-image?folder=docs.order&hash=' . rawurlencode($imgHash)) : '';
                        $designName = (string)($so['fab_design_name'] ?? '');
                        $w = (int)($so['fab_med_width'] ?? 0);
                        $h = (int)($so['fab_med_height'] ?? 0);
                        $fuelle = (int)($so['fab_med_fuelle'] ?? 0);
                        $medidasStr = $w > 0 ? ("{$w}x{$h}" . ($fuelle > 0 ? " + {$fuelle}" : "") . " cm") : ((float)($so['req_operador_tela_width'] ?? 0) > 0 ? (float)$so['req_operador_tela_width'] . ' cm' : '-');
                        $mat = !empty($so['fab_type']) ? (string)$so['fab_type'] : 'TNT';
                        $actividad = !empty($so['rebo_distribucion']) && $so['rebo_distribucion'] !== '-' ? $so['rebo_distribucion'] : (!empty($so['req_rebo_type']) ? ('Corte ' . ucfirst((string)$so['req_rebo_type'])) : 'Corte / Rebobinado');
                        $metros = (float)($so['ag_amount'] ?? 0);
                    ?>
                        <tr onmouseover="this.style.backgroundColor='#F0F7F6'" onmouseout="this.style.backgroundColor='transparent'">
                            <td class="tdnrm"><b><?=htmlspecialchars((string)$so['req_number'])?></b></td>
                            <td class="tdnrm"><b><?=htmlspecialchars((string)$so['prd_number'])?></b></td>
                            <td class="tdnrm">
                                <b><?=htmlspecialchars((string)$so['cust_name'])?></b>
                                <?php if (!empty($so['item_title'])): ?>
                                    <div style="font-size:11.5px;color:#64748B"><?=htmlspecialchars((string)$so['item_title'])?></div>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="center">
                                <?php if ($hasImg): ?>
                                    <div style="display:inline-flex;align-items:center;gap:6px;cursor:pointer" onclick="openDesignModal('<?=htmlspecialchars($imgUrl)?>', '<?=htmlspecialchars((string)$so['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$so['cust_name']))?>', '<?=htmlspecialchars(addslashes($designName))?>', '<?=htmlspecialchars($medidasStr)?>')" title="Click para ver foto del diseño">
                                        <img src="<?=htmlspecialchars($imgUrl)?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #CBD5E1;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
                                        <i class="fa fa-search-plus" style="color:#0284C7;font-size:12px"></i>
                                    </div>
                                <?php else: ?>
                                    <span style="color:#94A3B8;font-size:12px"><i>Sin foto</i></span>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="center">
                                <span style="background:#F1F5F9;color:#334155;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600"><?=htmlspecialchars($mat)?></span>
                            </td>
                            <td class="tdnrm">
                                <span style="color:#0F766E;font-weight:600;font-size:12.5px"><i class="fa fa-cut"></i> <?=htmlspecialchars($actividad)?></span>
                            </td>
                            <td class="tdnrm"><?=htmlspecialchars($medidasStr)?></td>
                            <td class="tdnrm" align="right"><b><?=number_format($metros, 0, ',', '.')?></b> m</td>
                            <td class="tdnrm" align="center">
                                <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                                    <form method="post" action="/production/work-orders/init" style="margin:0">
                                        <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                        <input type="hidden" name="ag_id" value="<?=$so['ag_id']?>">
                                        <button type="submit" class="btngreen" style="padding:6px 12px"><i class="fa fa-play"></i> Iniciar OT</button>
                                    </form>
                                    <a href="/production/materials/request?agid=<?=$so['ag_id']?>" class="btnblue" style="padding:6px 10px;font-size:11px;text-decoration:none" title="Solicitar o cargar bobinas antes de iniciar la OT">
                                        <i class="fa fa-dolly"></i> Solicitar Bobina
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                </table>
            </div>
        <?php elseif ($isFlexo): ?>
            <!-- DISEÑO FLEXOGRAFÍA: OTs Programadas -->
            <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px;overflow-x:auto">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px">
                    <div style="font-size:15px;font-weight:bold;color:#115452;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <span><i class="fa fa-fw fa-tasks"></i> OTs Programadas para esta Máquina</span>
                        <?php if (!empty($dateFilter)): ?>
                            <span style="font-size:12px;background:#E0F2FE;color:#0369A1;padding:3px 10px;border-radius:12px;font-weight:600;border:1px solid #BAE6FD">
                                <i class="fa fa-calendar-day"></i> Planificado para: <b><?=htmlspecialchars($dateFilter)?></b><?= $dateFilter === date('d.m.Y') ? ' (Hoy)' : '' ?>
                            </span>
                        <?php else: ?>
                            <span style="font-size:12px;background:#F1F5F9;color:#475569;padding:3px 10px;border-radius:12px;font-weight:600">
                                Todas las fechas
                            </span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px">
                        <form method="get" action="/production/work-orders/new" style="margin:0;display:inline-flex;align-items:center;gap:6px">
                            <label style="font-size:12px;color:#64748B;font-weight:600"><i class="fa fa-calendar-alt"></i> Fecha:</label>
                            <input type="date" name="plan_date" value="<?= !empty($dateFilter) && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateFilter, $mD) ? "{$mD[3]}-{$mD[2]}-{$mD[1]}" : date('Y-m-d') ?>" style="padding:4px 8px;border:1px solid #CBD5E1;border-radius:4px;font-size:12px" onchange="this.form.submit()">
                            <?php if (!empty($dateFilter) && $dateFilter !== date('d.m.Y')): ?>
                                <a href="/production/work-orders/new" class="btn" style="background:#0284C7;color:#fff;padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Hoy</a>
                            <?php endif; ?>
                            <?php if (empty($dateFilter)): ?>
                                <a href="/production/work-orders/new" class="btngreen" style="padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Solo Hoy</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <table border="0" width="100%" cellpadding="8" cellspacing="0" style="min-width:1150px">
                <colgroup>
                    <col width="90">
                    <col width="75">
                    <col width="150">
                    <col width="170">
                    <col width="70">
                    <col width="105">
                    <col width="140">
                    <col width="85">
                    <col width="95">
                    <col width="85">
                    <col width="90">
                    <col width="65">
                    <col width="105">
                </colgroup>
                <thead>
                    <tr>
                        <td class="tdheader">N° C.C.</td>
                        <td class="tdheader">N° O.T.</td>
                        <td class="tdheader">Cliente</td>
                        <td class="tdheader">Diseño</td>
                        <td class="tdheader" align="center">Material</td>
                        <td class="tdheader">Medidas</td>
                        <td class="tdheader">Colores</td>
                        <td class="tdheader" align="center">Rodillo [Z]</td>
                        <td class="tdheader" align="center">Ubicación Clisé</td>
                        <td class="tdheader" align="right">Metros</td>
                        <td class="tdheader" align="right">Planificado</td>
                        <td class="tdheader" align="right">Producidas</td>
                        <td class="tdheader" align="center">Imagen</td>
                        <td class="tdheader" align="center">Opciones</td>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($scheduledOrders)): ?>
                    <tr>
                        <td colspan="14" class="tdnrm" style="text-align:center;padding:35px 20px;color:#64748B">
                            <i class="fa fa-calendar-times" style="font-size:26px;color:#94A3B8;margin-bottom:8px;display:block"></i>
                            No hay órdenes de trabajo planificadas para esta máquina en la fecha <b><?=htmlspecialchars($dateFilter ?: date('d.m.Y'))?></b>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($scheduledOrders as $so):
                        $imgHash = trim((string)($so['fab_design_imagehash'] ?? $so['item_img_hash'] ?? ''));
                        $hasImg = ($imgHash !== '' && $imgHash !== 'dummy');
                        $imgUrl = $hasImg ? ('/production/design-image?folder=docs.order&hash=' . rawurlencode($imgHash)) : '';
                        
                        $prodSku = trim((string)($so['item_number_prod'] ?? ''));
                        $prodTitle = trim((string)($so['item_title'] ?? ''));
                        $designName = trim((string)($so['fab_design_name'] ?? ''));
                        $fullDesign = ($prodSku !== '' && $prodTitle !== '') ? "{$prodSku} | {$prodTitle}" : ($prodTitle ?: ($prodSku ?: ($designName ?: '-')));
                        
                        $w = (int)($so['fab_med_width'] ?? 0);
                        $h = (int)($so['fab_med_height'] ?? 0);
                        $fuelle = (int)($so['fab_med_fuelle'] ?? 0);
                        $medidasStr = $w > 0 ? ("{$w} x {$h}" . ($fuelle > 0 ? " + {$fuelle}" : "") . " cm") : '-';
                        $mat = !empty($so['fab_type']) ? (string)$so['fab_type'] : 'TNT';
                        
                        $colores = [];
                        for ($i = 1; $i <= 6; $i++) {
                            $c = trim((string)($so["fab_print_colordesc_{$i}"] ?? ''));
                            if ($c !== '') {
                                $colores[] = $c;
                            }
                        }
                        
                        $z = (int)($so['flexo_z'] ?? 0);
                        $ubicacion = trim((string)($so['req_infoaddprd_cliche_ubicacion'] ?? ''));
                        $codCliche = trim((string)($so['req_infoaddprd_cliche_codigo'] ?? ''));
                        
                        $metros = (float)($so['flexo_metros'] ?? 0);
                        $cantBolsas = (float)($so['ag_amount'] ?? 0);
                        $producidas = (float)($so['total_produced'] ?? 0);
                    ?>
                        <tr onmouseover="this.style.backgroundColor='#F0F7F6'" onmouseout="this.style.backgroundColor='transparent'">
                            <td class="tdnrm"><b><?=htmlspecialchars((string)$so['req_number'])?></b></td>
                            <td class="tdnrm"><b><?=htmlspecialchars((string)$so['prd_number'])?></b></td>
                            <td class="tdnrm"><b><?=htmlspecialchars((string)$so['cust_name'])?></b></td>
                            <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($fullDesign)?></td>
                            <td class="tdnrm" align="center">
                                <span style="background:#F1F5F9;color:#334155;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600"><?=htmlspecialchars($mat)?></span>
                            </td>
                            <td class="tdnrm" style="white-space:nowrap"><?=htmlspecialchars($medidasStr)?></td>
                            <td class="tdnrm">
                                <?php if (empty($colores)): ?>
                                    <span style="color:#94A3B8;font-size:12px"><i>Sin colores</i></span>
                                <?php else: ?>
                                    <div style="display:flex;flex-direction:column;gap:2px">
                                        <?php foreach ($colores as $idx => $colorName): ?>
                                            <span style="font-size:11px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:3px;padding:1px 5px;color:#334155;white-space:nowrap">
                                                <b><?=($idx + 1)?>:</b> <?=htmlspecialchars($colorName)?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="center">
                                <?php if ($z > 0): ?>
                                    <span style="background:#FEF3C7;color:#92400E;padding:3px 8px;border-radius:4px;font-weight:700;font-size:12px">Z = <?=$z?></span>
                                <?php else: ?>
                                    <span style="color:#94A3B8;font-size:12px">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="center">
                                <?php if ($ubicacion !== ''): ?>
                                    <div style="font-weight:700;color:#0F766E;font-size:13px"><?=htmlspecialchars($ubicacion)?></div>
                                    <?php if ($codCliche !== '' && $codCliche !== $ubicacion): ?>
                                        <div style="font-size:10.5px;color:#64748B"><?=htmlspecialchars($codCliche)?></div>
                                    <?php endif; ?>
                                <?php elseif ($codCliche !== ''): ?>
                                    <div style="font-weight:600;font-size:12px;color:#334155"><?=htmlspecialchars($codCliche)?></div>
                                <?php else: ?>
                                    <span style="color:#94A3B8;font-size:12px">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="right"><b><?=number_format($metros, 0, ',', '.')?></b> m</td>
                            <td class="tdnrm" align="right"><b><?=number_format($cantBolsas, 0, ',', '.')?></b></td>
                            <td class="tdnrm" align="right"><b style="color:#0F766E"><?=number_format($producidas, 0, ',', '.')?></b></td>
                            <td class="tdnrm" align="center">
                                <?php if ($hasImg): ?>
                                    <div style="display:inline-flex;align-items:center;gap:4px;cursor:pointer" onclick="openDesignModal('<?=htmlspecialchars($imgUrl)?>', '<?=htmlspecialchars((string)$so['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$so['cust_name']))?>', '<?=htmlspecialchars(addslashes($fullDesign))?>', '<?=htmlspecialchars($medidasStr)?>')" title="Click para ver foto del diseño">
                                        <img src="<?=htmlspecialchars($imgUrl)?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #CBD5E1;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
                                        <i class="fa fa-search-plus" style="color:#0284C7;font-size:11px"></i>
                                    </div>
                                <?php else: ?>
                                    <span style="color:#94A3B8;font-size:12px"><i>Sin foto</i></span>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="center">
                                <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                                    <form method="post" action="/production/work-orders/init" style="margin:0">
                                        <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                        <input type="hidden" name="ag_id" value="<?=$so['ag_id']?>">
                                        <button type="submit" class="btngreen" style="padding:6px 12px"><i class="fa fa-play"></i> Iniciar OT</button>
                                    </form>
                                    <a href="/production/materials/request?agid=<?=$so['ag_id']?>" class="btnblue" style="padding:6px 10px;font-size:11px;text-decoration:none" title="Solicitar o cargar bobinas antes de iniciar la OT">
                                        <i class="fa fa-dolly"></i> Solicitar Bobina
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                </table>
            </div>
        <?php elseif ($isSeri): ?>
            <!-- DISEÑO SERIGRAFÍA: OTs Programadas -->
            <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
                    <div style="font-size:15px;font-weight:bold;color:#115452">
                        <i class="fa fa-fw fa-paint-brush"></i> OTs Programadas para Serigrafía
                        <?php if (!empty($dateFilter)): ?>
                            <span style="font-size:13px;font-weight:normal;color:#666666;margin-left:8px">
                                (Fecha: <b><?=htmlspecialchars($dateFilter)?></b>)
                            </span>
                        <?php else: ?>
                            <span style="font-size:13px;font-weight:normal;color:#666666;margin-left:8px">
                                (Todas las fechas)
                            </span>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex;align-items:center;gap:8px">
                        <form method="get" action="/production/work-orders/new" style="margin:0;display:inline-flex;align-items:center;gap:6px">
                            <label style="font-size:12px;color:#64748B;font-weight:600"><i class="fa fa-calendar-alt"></i> Fecha:</label>
                            <input type="date" name="plan_date" value="<?= !empty($dateFilter) && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateFilter, $mD) ? "{$mD[3]}-{$mD[2]}-{$mD[1]}" : date('Y-m-d') ?>" style="padding:4px 8px;border:1px solid #CBD5E1;border-radius:4px;font-size:12px" onchange="this.form.submit()">
                            <?php if (!empty($dateFilter) && $dateFilter !== date('d.m.Y')): ?>
                                <a href="/production/work-orders/new" class="btn" style="background:#0284C7;color:#fff;padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Hoy</a>
                            <?php endif; ?>
                            <?php if (empty($dateFilter)): ?>
                                <a href="/production/work-orders/new" class="btngreen" style="padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Solo Hoy</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <table border="0" width="100%" cellpadding="8" cellspacing="0" style="min-width:1050px">
                <colgroup>
                    <col width="90">
                    <col width="75">
                    <col width="160">
                    <col width="180">
                    <col width="75">
                    <col width="110">
                    <col width="150">
                    <col width="95">
                    <col width="95">
                    <col width="65">
                    <col width="110">
                </colgroup>
                <thead>
                    <tr>
                        <td class="tdheader">N° C.C.</td>
                        <td class="tdheader">N° O.T.</td>
                        <td class="tdheader">Cliente</td>
                        <td class="tdheader">Diseño</td>
                        <td class="tdheader" align="center">Material</td>
                        <td class="tdheader">Medidas</td>
                        <td class="tdheader">Colores</td>
                        <td class="tdheader" align="right">Contador</td>
                        <td class="tdheader" align="right">Cantidad Bolsas</td>
                        <td class="tdheader" align="center">Imagen</td>
                        <td class="tdheader" align="center">Opciones</td>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($scheduledOrders)): ?>
                    <tr>
                        <td colspan="11" class="tdnrm" style="text-align:center;padding:35px 20px;color:#64748B">
                            <i class="fa fa-calendar-times" style="font-size:26px;color:#94A3B8;margin-bottom:8px;display:block"></i>
                            No hay órdenes de trabajo planificadas para esta máquina en la fecha <b><?=htmlspecialchars($dateFilter ?: date('d.m.Y'))?></b>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($scheduledOrders as $so):
                        $imgHash = trim((string)($so['fab_design_imagehash'] ?? $so['item_img_hash'] ?? ''));
                        $hasImg = ($imgHash !== '' && $imgHash !== 'dummy');
                        $imgUrl = $hasImg ? ('/production/design-image?folder=docs.order&hash=' . rawurlencode($imgHash)) : '';
                        
                        $prodSku = trim((string)($so['item_number_prod'] ?? ''));
                        $prodTitle = trim((string)($so['item_title'] ?? ''));
                        $designName = trim((string)($so['fab_design_name'] ?? ''));
                        $fullDesign = ($prodSku !== '' && $prodTitle !== '') ? "{$prodSku} | {$prodTitle}" : ($prodTitle ?: ($prodSku ?: ($designName ?: '-')));
                        
                        $w = (int)($so['fab_med_width'] ?? 0);
                        $h = (int)($so['fab_med_height'] ?? 0);
                        $fuelle = (int)($so['fab_med_fuelle'] ?? 0);
                        $medidasStr = $w > 0 ? ("{$w} x {$h}" . ($fuelle > 0 ? " + {$fuelle}" : "") . " cm") : '-';
                        $mat = !empty($so['fab_type']) ? (string)$so['fab_type'] : 'TNT';
                        
                        $colores = [];
                        for ($i = 1; $i <= 6; $i++) {
                            $c = trim((string)($so["fab_print_colordesc_{$i}"] ?? ''));
                            if ($c !== '') {
                                $colores[] = $c;
                            }
                        }
                        
                        $cantBolsas = (float)($so['ag_amount'] ?? 0);
                        $contador = (float)($so['seri_contador'] ?? 0);
                    ?>
                        <tr onmouseover="this.style.backgroundColor='#F0F7F6'" onmouseout="this.style.backgroundColor='transparent'">
                            <td class="tdnrm"><b><?=htmlspecialchars((string)$so['req_number'])?></b></td>
                            <td class="tdnrm"><b><?=htmlspecialchars((string)$so['prd_number'])?></b></td>
                            <td class="tdnrm"><b><?=htmlspecialchars((string)$so['cust_name'])?></b></td>
                            <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($fullDesign)?></td>
                            <td class="tdnrm" align="center">
                                <span style="background:#F1F5F9;color:#334155;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600"><?=htmlspecialchars($mat)?></span>
                            </td>
                            <td class="tdnrm" style="white-space:nowrap"><?=htmlspecialchars($medidasStr)?></td>
                            <td class="tdnrm">
                                <?php if (empty($colores)): ?>
                                    <span style="color:#94A3B8;font-size:12px"><i>Sin colores</i></span>
                                <?php else: ?>
                                    <div style="display:flex;flex-direction:column;gap:2px">
                                        <?php foreach ($colores as $idx => $colorName): ?>
                                            <span style="font-size:11px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:3px;padding:1px 5px;color:#334155;white-space:nowrap">
                                                <b><?=($idx + 1)?>:</b> <?=htmlspecialchars($colorName)?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="right">
                                <b style="color:#0F766E"><?=number_format($contador, 0, ',', '.')?></b>
                            </td>
                            <td class="tdnrm" align="right"><b><?=number_format($cantBolsas, 0, ',', '.')?></b></td>
                            <td class="tdnrm" align="center">
                                <?php if ($hasImg): ?>
                                    <div style="display:inline-flex;align-items:center;gap:4px;cursor:pointer" onclick="openDesignModal('<?=htmlspecialchars($imgUrl)?>', '<?=htmlspecialchars((string)$so['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$so['cust_name']))?>', '<?=htmlspecialchars(addslashes($fullDesign))?>', '<?=htmlspecialchars($medidasStr)?>')" title="Click para ver foto del diseño">
                                        <img src="<?=htmlspecialchars($imgUrl)?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #CBD5E1;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
                                        <i class="fa fa-search-plus" style="color:#0284C7;font-size:11px"></i>
                                    </div>
                                <?php else: ?>
                                    <span style="color:#94A3B8;font-size:12px"><i>Sin foto</i></span>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="center">
                                <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                                    <form method="post" action="/production/work-orders/init" style="margin:0">
                                        <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                        <input type="hidden" name="ag_id" value="<?=$so['ag_id']?>">
                                        <button type="submit" class="btngreen" style="padding:6px 12px"><i class="fa fa-play"></i> Iniciar OT</button>
                                    </form>
                                    <a href="/production/materials/request?agid=<?=$so['ag_id']?>" class="btnblue" style="padding:6px 10px;font-size:11px;text-decoration:none" title="Solicitar o cargar bobinas antes de iniciar la OT">
                                        <i class="fa fa-dolly"></i> Solicitar Bobina
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                </table>
            </div>
        <?php else: ?>
            <!-- DISEÑO ESTÁNDAR / OTRAS MÁQUINAS -->
            <table border="0" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
                    <?php if (empty($scheduledOrders)): ?>
                        <b class="msg_save_err" style="color:#C00"><i class="fa fa-fw fa-exclamation-triangle"></i> No existen OT's planificadas para esta fecha.</b>
                    <?php else: ?>
                        <table border="0" width="100%" cellpadding="6" cellspacing="0">
                        <colgroup>
                            <col width="60">
                            <col width="60">
                            <col>
                            <col>
                            <col>
                            <col>
                            <col>
                            <col>
                            <col>
                            <col>
                            <col>
                            <col width="110">
                        </colgroup>
                        <tr>
                            <td class="tdheader">OT</td>
                            <td class="tdheader">CC</td>
                            <td class="tdheader">Cliente</td>
                            <td class="tdheader">Código</td>
                            <td class="tdheader">Descripción</td>
                            <td class="tdheader">Tipo</td>
                            <td class="tdheader">Material</td>
                            <td class="tdheader" align="center"><?=$isFlexo ? 'Planificado' : 'Cantidad'?></td>
                            <td class="tdheader" align="center"><?=$isFlexo ? 'Producidas' : 'Producido'?></td>
                            <td class="tdheader" align="center">Saldo</td>
                            <td class="tdheader" align="center">Estado</td>
                            <td class="tdheader" align="center">Opciones</td>
                        </tr>
                        <?php foreach ($scheduledOrders as $so):
                            $prodAmt = (float)($so["total_produced"] ?? 0);
                            $itemAmt = (float)($so["item_amount"] ?? $so["ag_amount"]);
                            $saldo = max(0, $itemAmt - $prodAmt);
                            $isActive = (int)($so["ag_active"] ?? 1) > 0;
                            $btnCls = $isActive ? "btngreen" : "btnorange";
                        ?>
                        <tr onmouseover="this.style.backgroundColor='#F0F7F6'" onmouseout="this.style.backgroundColor='transparent'">
                            <td class="tdleft"><?=htmlspecialchars((string)$so["prd_number"])?>&nbsp;</td>
                            <td class="tdnrm"><nobr><?=htmlspecialchars((string)$so["req_number"])?></nobr>&nbsp;</td>
                            <td class="tdnrm"><?=htmlspecialchars((string)$so["cust_name"])?>&nbsp;</td>
                            <td class="tdnrm"><?=htmlspecialchars((string)$so["item_number_prod"])?>&nbsp;</td>
                            <td class="tdnrm"><?=htmlspecialchars((string)$so["item_title"])?>&nbsp;</td>
                            <td class="tdnrm"><?=htmlspecialchars((string)$so["fab_printtype"])?>&nbsp;</td>
                            <td class="tdnrm"><?=htmlspecialchars((string)$so["fab_type"])?>&nbsp;</td>
                            <td class="tdnrm" align="center"><?=number_format((float)$so["ag_amount"], 0, ',', '.')?>&nbsp;</td>
                            <td class="tdnrm" align="center"><?=number_format($prodAmt, 0, ',', '.')?></td>
                            <td class="tdnrm" align="center"><?=number_format($saldo, 0, ',', '.')?></td>
                            <td class="tdnrm" align="center">
                                <?php if ($isActive): ?>
                                    <b class="msg_save_ok" style="color:#00A85A">Activado</b>
                                <?php else: ?>
                                    <b class="msg_save_err" style="color:#C00">Inactivo</b>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="center">
                                <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                                    <form method="post" action="/production/work-orders/init" style="margin:0">
                                        <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                        <input type="hidden" name="ag_id" value="<?=$so['ag_id']?>">
                                        <div class="<?=$btnCls?>" style="cursor:pointer;display:inline-block" onclick="this.closest('form').submit();">
                                            <nobr><i class="fa fa-fw fa-play" style="color:white;"></i> Iniciar OT&nbsp;</nobr>
                                        </div>
                                    </form>
                                    <a href="/production/materials/request?agid=<?=$so['ag_id']?>" class="btnblue" style="padding:6px 10px;font-size:11px;text-decoration:none" title="Solicitar o cargar bobinas antes de iniciar la OT">
                                        <nobr><i class="fa fa-dolly"></i> Solicitar Bobina</nobr>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </td>
            </tr>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <?php
    echo renderDesignPreviewModal();
    renderStandbyAndLunchModals($activeStandby, $activeInit, $operatorName, '/production/work-orders/new');
    $content = ob_get_clean();
    renderProdwrkShell('Iniciar nueva OT', $content, 1, $operatorName, $plantaName);
}

function renderProductionActiveOrdersScreen(
    array $activeList,
    string $operatorName,
    string $plantaName,
    ?array $activeInit = null,
    ?array $activeStandby = null,
    ?string $dateFilter = null
): void {
    $equipoTypeId = (int)($activeInit['equipo_type_id'] ?? 0);
    $isRebobinadora = ($equipoTypeId === 12 || stripos((string)($activeInit['type_ant_title'] ?? ''), 'rebobin') !== false || stripos((string)($activeInit['equipo_name'] ?? ''), 'rebobin') !== false);
    $isFlexo = ($equipoTypeId === 7
        || (int)($activeInit['equipo_prod_isprinter_flexo'] ?? 0) === 1
        || stripos((string)($activeInit['type_ant_title'] ?? ''), 'flexo') !== false
        || stripos((string)($activeInit['equipo_name'] ?? ''), 'flexo') !== false);
    $isSeri = ((int)($activeInit['equipo_prod_isprinter_seri'] ?? 0) === 1)
        || ($equipoTypeId === 11 || $equipoTypeId === 8 || $equipoTypeId === 22)
        || stripos((string)($activeInit['type_ant_title'] ?? ''), 'seri') !== false
        || stripos((string)($activeInit['equipo_name'] ?? ''), 'seri') !== false
        || stripos((string)($activeInit['equipo_name'] ?? ''), 'pulpo') !== false;

    ob_start();
    ?>
    <?php if ($isRebobinadora): ?>
        <!-- ============================================================== -->
        <!-- DISEÑO ESPECÍFICO REBOBINADORA: En curso                       -->
        <!-- ============================================================== -->
        <div style="font-size:20px;margin-bottom:10px"><b>Ordenes de trabajo</b></div>
        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
        <tr>
            <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                    <div>
                        <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
                        <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                        <b>En cursos</b> (OTs actualmente abiertas en máquina <b><?=htmlspecialchars((string)($activeInit['equipo_name'] ?? 'Rebobinadora'))?></b>)
                    </div>
                    <div>
                        <a href="/production/materials/request" class="btngreen" style="padding:5px 12px;font-size:12px;text-decoration:none;border-radius:4px;display:inline-flex;align-items:center;gap:6px" title="Solicitar o cargar bobinas a la máquina">
                            <i class="fa fa-dolly"></i> Solicitar Bobinas
                        </a>
                    </div>
                </div>
            </td>
        </tr>
        </table>

        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <table border="0" width="100%" cellpadding="8" cellspacing="0">
            <colgroup>
                <col width="95">
                <col width="85">
                <col>
                <col width="120">
                <col width="85">
                <col width="160">
                <col width="120">
                <col width="90">
                <col width="115">
            </colgroup>
            <thead>
                <tr>
                    <td class="tdheader">N° C.C.</td>
                    <td class="tdheader">N° O.T.</td>
                    <td class="tdheader">Cliente</td>
                    <td class="tdheader" align="center">Diseño</td>
                    <td class="tdheader" align="center">Material</td>
                    <td class="tdheader">Actividad</td>
                    <td class="tdheader">Medidas</td>
                    <td class="tdheader" align="right">Metros</td>
                    <td class="tdheader" align="center">Acción</td>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($activeList)): ?>
                <tr><td colspan="9" class="tdnrm" style="text-align:center;padding:25px;color:#999">No hay órdenes en curso activas actualmente.</td></tr>
            <?php else: ?>
                <?php foreach ($activeList as $row):
                    $imgHash = trim((string)($row['fab_design_imagehash'] ?? $row['item_img_hash'] ?? ''));
                    $hasImg = ($imgHash !== '' && $imgHash !== 'dummy');
                    $imgUrl = $hasImg ? ('/production/design-image?folder=docs.order&hash=' . rawurlencode($imgHash)) : '';
                    $designName = (string)($row['fab_design_name'] ?? '');
                    $w = (int)($row['fab_med_width'] ?? 0);
                    $h = (int)($row['fab_med_height'] ?? 0);
                    $fuelle = (int)($row['fab_med_fuelle'] ?? 0);
                    $medidasStr = $w > 0 ? ("{$w}x{$h}" . ($fuelle > 0 ? " + {$fuelle}" : "") . " cm") : ((float)($row['req_operador_tela_width'] ?? 0) > 0 ? (float)$row['req_operador_tela_width'] . ' cm' : '-');
                    $mat = !empty($row['fab_type']) ? (string)$row['fab_type'] : 'TNT';
                    $actividad = !empty($row['rebo_distribucion']) && $row['rebo_distribucion'] !== '-' ? $row['rebo_distribucion'] : (!empty($row['req_rebo_type']) ? ('Corte ' . ucfirst((string)$row['req_rebo_type'])) : 'Corte / Rebobinado');
                    $metros = (float)($row['ag_amount'] ?? 0);
                ?>
                    <tr onmouseover="this.style.backgroundColor='#F0F7F6'" onmouseout="this.style.backgroundColor='transparent'">
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$row['req_number'])?></b></td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$row['prd_number'])?></b></td>
                        <td class="tdnrm">
                            <b><?=htmlspecialchars((string)$row['cust_name'])?></b>
                            <?php if (!empty($row['item_title'])): ?>
                                <div style="font-size:11.5px;color:#64748B"><?=htmlspecialchars((string)$row['item_title'])?></div>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm" align="center">
                            <?php if ($hasImg): ?>
                                <div style="display:inline-flex;align-items:center;gap:6px;cursor:pointer" onclick="openDesignModal('<?=htmlspecialchars($imgUrl)?>', '<?=htmlspecialchars((string)$row['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$row['cust_name']))?>', '<?=htmlspecialchars(addslashes($designName))?>', '<?=htmlspecialchars($medidasStr)?>')" title="Click para ver foto del diseño">
                                    <img src="<?=htmlspecialchars($imgUrl)?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #CBD5E1;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
                                    <i class="fa fa-search-plus" style="color:#0284C7;font-size:12px"></i>
                                </div>
                            <?php else: ?>
                                <span style="color:#94A3B8;font-size:12px"><i>Sin foto</i></span>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm" align="center">
                            <span style="background:#F1F5F9;color:#334155;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600"><?=htmlspecialchars($mat)?></span>
                        </td>
                        <td class="tdnrm">
                            <span style="color:#0F766E;font-weight:600;font-size:12.5px"><i class="fa fa-cut"></i> <?=htmlspecialchars($actividad)?></span>
                        </td>
                        <td class="tdnrm"><?=htmlspecialchars($medidasStr)?></td>
                        <td class="tdnrm" align="right"><b><?=number_format($metros, 0, ',', '.')?></b> m</td>
                        <td class="tdnrm" align="center">
                            <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                                <a href="/production/work-orders/operate?agid=<?=$row['ag_id']?>" class="btnblue" style="padding:6px 12px;text-decoration:none">
                                    <i class="fa fa-desktop"></i> Operar
                                </a>
                                <a href="/production/materials/request?agid=<?=$row['ag_id']?>" class="btngreen" style="padding:6px 10px;text-decoration:none;font-size:11px" title="Solicitar o cargar bobinas para esta OT">
                                    <i class="fa fa-dolly"></i> Bobinas
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            </table>
        </div>
    <?php elseif ($isFlexo): ?>
        <!-- ============================================================== -->
        <!-- DISEÑO ESPECÍFICO FLEXOGRAFÍA: En curso                       -->
        <!-- ============================================================== -->
        <div style="font-size:20px;margin-bottom:10px"><b>Ordenes de trabajo</b></div>
        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
        <tr>
            <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                    <div>
                        <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
                        <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                        <b>En cursos</b> (OTs actualmente abiertas en máquina <b><?=htmlspecialchars((string)($activeInit['equipo_name'] ?? 'Flexografía'))?></b>)
                    </div>
                    <div>
                        <a href="/production/materials/request" class="btngreen" style="padding:5px 12px;font-size:12px;text-decoration:none;border-radius:4px;display:inline-flex;align-items:center;gap:6px" title="Solicitar o cargar bobinas a la máquina">
                            <i class="fa fa-dolly"></i> Solicitar Bobinas
                        </a>
                    </div>
                </div>
            </td>
        </tr>
        </table>

        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px;overflow-x:auto">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px">
                <div style="font-size:15px;font-weight:bold;color:#115452;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <span><i class="fa fa-fw fa-play-circle"></i> OTs en Curso en esta Máquina</span>
                    <?php if (!empty($dateFilter)): ?>
                        <span style="font-size:12px;background:#E0F2FE;color:#0369A1;padding:3px 10px;border-radius:12px;font-weight:600;border:1px solid #BAE6FD">
                            <i class="fa fa-calendar-day"></i> Planificado para: <b><?=htmlspecialchars($dateFilter)?></b><?= $dateFilter === date('d.m.Y') ? ' (Hoy)' : '' ?>
                        </span>
                    <?php else: ?>
                        <span style="font-size:12px;background:#F1F5F9;color:#475569;padding:3px 10px;border-radius:12px;font-weight:600">
                            Todas las fechas
                        </span>
                    <?php endif; ?>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <form method="get" action="/production/work-orders/active" style="margin:0;display:inline-flex;align-items:center;gap:6px">
                        <label style="font-size:12px;color:#64748B;font-weight:600"><i class="fa fa-calendar-alt"></i> Fecha:</label>
                        <input type="date" name="plan_date" value="<?= !empty($dateFilter) && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateFilter, $mD) ? "{$mD[3]}-{$mD[2]}-{$mD[1]}" : date('Y-m-d') ?>" style="padding:4px 8px;border:1px solid #CBD5E1;border-radius:4px;font-size:12px" onchange="this.form.submit()">
                        <?php if (!empty($dateFilter) && $dateFilter !== date('d.m.Y')): ?>
                            <a href="/production/work-orders/active" class="btn" style="background:#0284C7;color:#fff;padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Hoy</a>
                        <?php endif; ?>
                        <?php if (empty($dateFilter)): ?>
                            <a href="/production/work-orders/active" class="btngreen" style="padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Solo Hoy</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <table border="0" width="100%" cellpadding="8" cellspacing="0" style="min-width:1150px">
            <colgroup>
                <col width="90">
                <col width="85">
                <col width="150">
                <col width="170">
                <col width="70">
                <col width="105">
                <col width="140">
                <col width="85">
                <col width="95">
                <col width="85">
                <col width="90">
                <col width="65">
                <col width="105">
            </colgroup>
            <thead>
                <tr>
                    <td class="tdheader">N° C.C.</td>
                    <td class="tdheader">N° O.T.</td>
                    <td class="tdheader">Cliente</td>
                    <td class="tdheader">Diseño</td>
                    <td class="tdheader" align="center">Material</td>
                    <td class="tdheader">Medidas</td>
                    <td class="tdheader">Colores</td>
                    <td class="tdheader" align="center">Rodillo [Z]</td>
                    <td class="tdheader" align="center">Ubicación Clisé</td>
                    <td class="tdheader" align="right">Metros</td>
                    <td class="tdheader" align="right">Planificado</td>
                    <td class="tdheader" align="right">Producidas</td>
                    <td class="tdheader" align="center">Imagen</td>
                    <td class="tdheader" align="center">Opciones</td>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($activeList)): ?>
                <tr>
                    <td colspan="14" class="tdnrm" style="text-align:center;padding:35px 20px;color:#64748B">
                        <div style="font-size:16px;font-weight:bold;color:#334155;margin-bottom:6px">
                            <i class="fa fa-info-circle" style="color:#0284C7"></i> No hay órdenes en curso <?= !empty($dateFilter) ? 'planificadas para el ' . htmlspecialchars($dateFilter) : 'activas actualmente' ?>.
                        </div>
                        <?php if (!empty($dateFilter)): ?>
                            <div style="font-size:12.5px;color:#64748B;margin-top:6px">
                                Puedes iniciar una orden planificada para hoy en <a href="/production/work-orders/new" style="color:#059669;font-weight:600;text-decoration:underline">Iniciar nueva OT</a>.
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($activeList as $row):
                    $imgHash = trim((string)($row['fab_design_imagehash'] ?? $row['item_img_hash'] ?? ''));
                    $hasImg = ($imgHash !== '' && $imgHash !== 'dummy');
                    $imgUrl = $hasImg ? ('/production/design-image?folder=docs.order&hash=' . rawurlencode($imgHash)) : '';
                    
                    $prodSku = trim((string)($row['item_number_prod'] ?? ''));
                    $prodTitle = trim((string)($row['item_title'] ?? ''));
                    $designName = trim((string)($row['fab_design_name'] ?? ''));
                    $fullDesign = ($prodSku !== '' && $prodTitle !== '') ? "{$prodSku} | {$prodTitle}" : ($prodTitle ?: ($prodSku ?: ($designName ?: '-')));
                    
                    $w = (int)($row['fab_med_width'] ?? 0);
                    $h = (int)($row['fab_med_height'] ?? 0);
                    $fuelle = (int)($row['fab_med_fuelle'] ?? 0);
                    $medidasStr = $w > 0 ? ("{$w} x {$h}" . ($fuelle > 0 ? " + {$fuelle}" : "") . " cm") : '-';
                    $mat = !empty($row['fab_type']) ? (string)$row['fab_type'] : 'TNT';
                    
                    $colores = [];
                    for ($i = 1; $i <= 6; $i++) {
                        $c = trim((string)($row["fab_print_colordesc_{$i}"] ?? ''));
                        if ($c !== '') {
                            $colores[] = $c;
                        }
                    }
                    
                    $z = (int)($row['flexo_z'] ?? 0);
                    $ubicacion = trim((string)($row['req_infoaddprd_cliche_ubicacion'] ?? ''));
                    $codCliche = trim((string)($row['req_infoaddprd_cliche_codigo'] ?? ''));
                    
                    $metros = (float)($row['flexo_metros'] ?? 0);
                    $cantBolsas = (float)($row['ag_amount'] ?? 0);
                    $producidas = (float)($row['total_produced'] ?? 0);
                ?>
                    <tr onmouseover="this.style.backgroundColor='#F0F7F6'" onmouseout="this.style.backgroundColor='transparent'">
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$row['req_number'])?></b></td>
                        <td class="tdnrm">
                            <b><?=htmlspecialchars((string)$row['prd_number'])?></b>
                            <?php if (!empty($row['ag_date'])): ?>
                                <div style="font-size:10.5px;color:#0284C7;font-weight:600;white-space:nowrap;margin-top:2px">
                                    <i class="fa fa-calendar-day"></i> <?=htmlspecialchars((string)$row['ag_date'])?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$row['cust_name'])?></b></td>
                        <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($fullDesign)?></td>
                        <td class="tdnrm" align="center">
                            <span style="background:#F1F5F9;color:#334155;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600"><?=htmlspecialchars($mat)?></span>
                        </td>
                        <td class="tdnrm" style="white-space:nowrap"><?=htmlspecialchars($medidasStr)?></td>
                        <td class="tdnrm">
                            <?php if (empty($colores)): ?>
                                <span style="color:#94A3B8;font-size:12px"><i>Sin colores</i></span>
                            <?php else: ?>
                                <div style="display:flex;flex-direction:column;gap:2px">
                                    <?php foreach ($colores as $idx => $colorName): ?>
                                        <span style="font-size:11px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:3px;padding:1px 5px;color:#334155;white-space:nowrap">
                                            <b><?=($idx + 1)?>:</b> <?=htmlspecialchars($colorName)?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm" align="center">
                            <?php if ($z > 0): ?>
                                <span style="background:#FEF3C7;color:#92400E;padding:3px 8px;border-radius:4px;font-weight:700;font-size:12px">Z = <?=$z?></span>
                            <?php else: ?>
                                <span style="color:#94A3B8;font-size:12px">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm" align="center">
                            <?php if ($ubicacion !== ''): ?>
                                <div style="font-weight:700;color:#0F766E;font-size:13px"><?=htmlspecialchars($ubicacion)?></div>
                                <?php if ($codCliche !== '' && $codCliche !== $ubicacion): ?>
                                    <div style="font-size:10.5px;color:#64748B"><?=htmlspecialchars($codCliche)?></div>
                                <?php endif; ?>
                            <?php elseif ($codCliche !== ''): ?>
                                <div style="font-weight:600;font-size:12px;color:#334155"><?=htmlspecialchars($codCliche)?></div>
                            <?php else: ?>
                                <span style="color:#94A3B8;font-size:12px">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm" align="right"><b><?=number_format($metros, 0, ',', '.')?></b> m</td>
                    <td class="tdnrm" align="right"><b><?=number_format($cantBolsas, 0, ',', '.')?></b></td>
                    <td class="tdnrm" align="right"><b style="color:#0F766E"><?=number_format($producidas, 0, ',', '.')?></b></td>
                        <td class="tdnrm" align="center">
                            <?php if ($hasImg): ?>
                                <div style="display:inline-flex;align-items:center;gap:4px;cursor:pointer" onclick="openDesignModal('<?=htmlspecialchars($imgUrl)?>', '<?=htmlspecialchars((string)$row['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$row['cust_name']))?>', '<?=htmlspecialchars(addslashes($fullDesign))?>', '<?=htmlspecialchars($medidasStr)?>')" title="Click para ver foto del diseño">
                                    <img src="<?=htmlspecialchars($imgUrl)?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #CBD5E1;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
                                    <i class="fa fa-search-plus" style="color:#0284C7;font-size:11px"></i>
                                </div>
                            <?php else: ?>
                                <span style="color:#94A3B8;font-size:12px"><i>Sin foto</i></span>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm" align="center">
                            <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                                <a href="/production/work-orders/operate?agid=<?=$row['ag_id']?>" class="btnblue" style="padding:6px 12px;text-decoration:none">
                                    <i class="fa fa-desktop"></i> Operar
                                </a>
                                <a href="/production/materials/request?agid=<?=$row['ag_id']?>" class="btngreen" style="padding:6px 10px;text-decoration:none;font-size:11px" title="Solicitar o cargar bobinas para esta OT">
                                    <i class="fa fa-dolly"></i> Bobinas
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            </table>
        </div>
    <?php elseif ($isSeri): ?>
        <!-- ============================================================== -->
        <!-- DISEÑO ESPECÍFICO SERIGRAFÍA: En curso                         -->
        <!-- ============================================================== -->
        <div style="font-size:20px;margin-bottom:10px"><b>Ordenes de trabajo</b></div>
        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
        <tr>
            <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                    <div>
                        <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
                        <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                        <b>En curso</b> en máquina <b><?=htmlspecialchars((string)($activeInit['equipo_name'] ?? 'Serigrafía'))?></b>
                    </div>
                    <div>
                        <a href="/production/materials/request" class="btngreen" style="padding:5px 12px;font-size:12px;text-decoration:none;border-radius:4px;display:inline-flex;align-items:center;gap:6px" title="Solicitar o cargar bobinas a la máquina">
                            <i class="fa fa-dolly"></i> Solicitar Bobinas
                        </a>
                    </div>
                </div>
            </td>
        </tr>
        </table>

        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
                <div style="font-size:15px;font-weight:bold;color:#115452">
                    <i class="fa fa-fw fa-paint-brush"></i> OTs En Curso para Serigrafía
                    <?php if (!empty($dateFilter)): ?>
                        <span style="font-size:13px;font-weight:normal;color:#666666;margin-left:8px">
                            (Fecha: <b><?=htmlspecialchars($dateFilter)?></b>)
                        </span>
                    <?php else: ?>
                        <span style="font-size:13px;font-weight:normal;color:#666666;margin-left:8px">
                            (Todas las fechas)
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Filtro rápido por fecha -->
                <div style="display:flex;align-items:center;gap:8px">
                    <form method="get" action="/production/work-orders/active" style="margin:0;display:inline-flex;align-items:center;gap:6px">
                        <label style="font-size:12px;color:#64748B;font-weight:600"><i class="fa fa-calendar-alt"></i> Fecha:</label>
                        <input type="date" name="plan_date" value="<?= !empty($dateFilter) && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateFilter, $mD) ? "{$mD[3]}-{$mD[2]}-{$mD[1]}" : date('Y-m-d') ?>" style="padding:4px 8px;border:1px solid #CBD5E1;border-radius:4px;font-size:12px" onchange="this.form.submit()">
                        <?php if (!empty($dateFilter) && $dateFilter !== date('d.m.Y')): ?>
                            <a href="/production/work-orders/active" class="btn" style="background:#0284C7;color:#fff;padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Hoy</a>
                        <?php endif; ?>
                        <?php if (empty($dateFilter)): ?>
                            <a href="/production/work-orders/active" class="btngreen" style="padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Solo Hoy</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <table border="0" width="100%" cellpadding="8" cellspacing="0" style="min-width:1050px">
            <colgroup>
                <col width="90">
                <col width="75">
                <col width="160">
                <col width="180">
                <col width="75">
                <col width="110">
                <col width="150">
                <col width="95">
                <col width="95">
                <col width="65">
                <col width="110">
            </colgroup>
            <thead>
                <tr>
                    <td class="tdheader">N° C.C.</td>
                    <td class="tdheader">N° O.T.</td>
                    <td class="tdheader">Cliente</td>
                    <td class="tdheader">Diseño</td>
                    <td class="tdheader" align="center">Material</td>
                    <td class="tdheader">Medidas</td>
                    <td class="tdheader">Colores</td>
                    <td class="tdheader" align="right">Contador</td>
                    <td class="tdheader" align="right">Cantidad Bolsas</td>
                    <td class="tdheader" align="center">Imagen</td>
                    <td class="tdheader" align="center">Opciones</td>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($activeList)): ?>
                <tr>
                    <td colspan="11" class="tdnrm" style="text-align:center;padding:35px 20px;color:#64748B">
                        <i class="fa fa-calendar-times" style="font-size:26px;color:#94A3B8;margin-bottom:8px;display:block"></i>
                        No hay órdenes en curso para esta máquina en la fecha <b><?=htmlspecialchars($dateFilter ?: date('d.m.Y'))?></b>.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($activeList as $row):
                    $imgHash = trim((string)($row['fab_design_imagehash'] ?? $row['item_img_hash'] ?? ''));
                    $hasImg = ($imgHash !== '' && $imgHash !== 'dummy');
                    $imgUrl = $hasImg ? ('/production/design-image?folder=docs.order&hash=' . rawurlencode($imgHash)) : '';
                    
                    $prodSku = trim((string)($row['item_number_prod'] ?? ''));
                    $prodTitle = trim((string)($row['item_title'] ?? ''));
                    $designName = trim((string)($row['fab_design_name'] ?? ''));
                    $fullDesign = ($prodSku !== '' && $prodTitle !== '') ? "{$prodSku} | {$prodTitle}" : ($prodTitle ?: ($prodSku ?: ($designName ?: '-')));
                    
                    $w = (int)($row['fab_med_width'] ?? 0);
                    $h = (int)($row['fab_med_height'] ?? 0);
                    $fuelle = (int)($row['fab_med_fuelle'] ?? 0);
                    $medidasStr = $w > 0 ? ("{$w} x {$h}" . ($fuelle > 0 ? " + {$fuelle}" : "") . " cm") : '-';
                    $mat = !empty($row['fab_type']) ? (string)$row['fab_type'] : 'TNT';
                    
                    $colores = [];
                    for ($i = 1; $i <= 6; $i++) {
                        $c = trim((string)($row["fab_print_colordesc_{$i}"] ?? ''));
                        if ($c !== '') {
                            $colores[] = $c;
                        }
                    }
                    
                    $cantBolsas = (float)($row['ag_amount'] ?? 0);
                    $contador = (float)($row['seri_contador'] ?? 0);
                ?>
                    <tr onmouseover="this.style.backgroundColor='#F0F7F6'" onmouseout="this.style.backgroundColor='transparent'">
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$row['req_number'])?></b></td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$row['prd_number'])?></b></td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$row['cust_name'])?></b></td>
                        <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($fullDesign)?></td>
                        <td class="tdnrm" align="center">
                            <span style="background:#F1F5F9;color:#334155;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600"><?=htmlspecialchars($mat)?></span>
                        </td>
                        <td class="tdnrm" style="white-space:nowrap"><?=htmlspecialchars($medidasStr)?></td>
                        <td class="tdnrm">
                            <?php if (empty($colores)): ?>
                                <span style="color:#94A3B8;font-size:12px"><i>Sin colores</i></span>
                            <?php else: ?>
                                <div style="display:flex;flex-direction:column;gap:2px">
                                    <?php foreach ($colores as $idx => $colorName): ?>
                                        <span style="font-size:11px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:3px;padding:1px 5px;color:#334155;white-space:nowrap">
                                            <b><?=($idx + 1)?>:</b> <?=htmlspecialchars($colorName)?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm" align="right">
                            <b style="color:#0F766E"><?=number_format($contador, 0, ',', '.')?></b>
                        </td>
                        <td class="tdnrm" align="right"><b><?=number_format($cantBolsas, 0, ',', '.')?></b></td>
                        <td class="tdnrm" align="center">
                            <?php if ($hasImg): ?>
                                <div style="display:inline-flex;align-items:center;gap:4px;cursor:pointer" onclick="openDesignModal('<?=htmlspecialchars($imgUrl)?>', '<?=htmlspecialchars((string)$row['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$row['cust_name']))?>', '<?=htmlspecialchars(addslashes($fullDesign))?>', '<?=htmlspecialchars($medidasStr)?>')" title="Click para ver foto del diseño">
                                    <img src="<?=htmlspecialchars($imgUrl)?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #CBD5E1;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
                                    <i class="fa fa-search-plus" style="color:#0284C7;font-size:11px"></i>
                                </div>
                            <?php else: ?>
                                <span style="color:#94A3B8;font-size:12px"><i>Sin foto</i></span>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm" align="center">
                            <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                                <a href="/production/work-orders/operate?agid=<?=$row['ag_id']?>" class="btnblue" style="padding:6px 12px;text-decoration:none">
                                    <i class="fa fa-desktop"></i> Operar
                                </a>
                                <a href="/production/materials/request?agid=<?=$row['ag_id']?>" class="btngreen" style="padding:6px 10px;text-decoration:none;font-size:11px" title="Solicitar o cargar bobinas para esta OT">
                                    <i class="fa fa-dolly"></i> Bobinas
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            </table>
        </div>
    <?php else: ?>
        <!-- ============================================================== -->
        <!-- FORMATO ESTÁNDAR / LEGACY DE UNIBAG PARA OTRAS MÁQUINAS         -->
        <!-- ============================================================== -->
        <font style="font-size:20px"><b>En cursos</b></font>
        <div style="clear:both;height:10px"></div>
        <table border="0" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                    <div>
                        <i class="fa fa-fw fa-laptop-code" style="font-size:14px;"></i> Ordenes de trabajo
                        <i class="fa fa-fw fa-chevron-right" style="font-size:14px;"></i>
                        En cursos
                    </div>
                    <div>
                        <a href="/production/materials/request" class="btngreen" style="padding:5px 12px;font-size:12px;text-decoration:none;border-radius:4px;display:inline-flex;align-items:center;gap:6px" title="Solicitar o cargar bobinas a la máquina">
                            <i class="fa fa-dolly"></i> Solicitar Bobinas
                        </a>
                    </div>
                </div>
            </td>
        </tr>
        </table>
        <div style="clear:both;height:10px"></div>

        <?php if (!empty($activeInit['id'])): ?>
        <?php if ($activeStandby !== null): ?>
            <div style="background:#FFF7ED;border:1.5px solid #F97316;border-radius:5px;padding:12px 16px;margin-bottom:12px;color:#9A3412;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                <div style="display:flex;align-items:center;gap:10px">
                    <i class="fa fa-pause-circle" style="font-size:22px;color:#EA580C"></i>
                    <div>
                        <span style="font-weight:bold;font-size:14px;text-transform:uppercase">MÁQUINA EN ESPERA / SIN PRODUCCIÓN: <?=htmlspecialchars((string)$activeStandby['reason'])?></span>
                        <div style="font-size:12.5px;color:#C2410C;margin-top:2px">
                            Iniciado a las <b><?=htmlspecialchars((string)$activeStandby['start_time'])?></b> por <b><?=htmlspecialchars((string)$activeStandby['operator_name'])?></b>
                            <?php if (!empty($activeStandby['comments'])): ?>
                                &bull; <i><?=htmlspecialchars((string)$activeStandby['comments'])?></i>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <button type="button" class="btnorange" style="background:#EA580C;padding:7px 16px;font-size:13px;cursor:pointer;border:none" onclick="openStandbyEndModal()">
                    <i class="fa fa-play"></i> Finalizar Sin Producción
                </button>
            </div>
        <?php endif; ?>
        <table border="0" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="120">
                    <col>
                </colgroup>
                <tr>
                    <td class="tdleft">Fecha inicio</td>
                    <td class="tdnrm"><?=date("d.m.Y", (int)($activeInit["win_crtdat"] ?? time()))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Hora inicio</td>
                    <td class="tdnrm"><?=date("H:i:s", (int)($activeInit["win_crtdat"] ?? time()))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Tipo</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($activeInit["type_ant_title"] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Máquina</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($activeInit["equipo_name"] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Acciones</td>
                    <td class="tdnrm">
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                            <button type="button" class="btnblue" style="background:#0284C7;padding:6px 14px;cursor:pointer;border:none" onclick="openLunchModal()" title="Registrar horario de colación">
                                <i class="fa fa-coffee"></i> Registrar Colación
                            </button>
                            <?php if ($activeStandby !== null): ?>
                                <button type="button" class="btnorange" style="background:#EA580C;padding:6px 14px;cursor:pointer;border:none" onclick="openStandbyEndModal()" title="Finalizar estado sin producción">
                                    <i class="fa fa-play-circle"></i> Finalizar Sin Producción
                                </button>
                            <?php else: ?>
                                <button type="button" class="btnorange" style="background:#D97706;padding:6px 14px;cursor:pointer;border:none" onclick="openStandbyStartModal()" title="Informar máquina sin producción / espera de OT / mantención">
                                    <i class="fa fa-pause-circle"></i> Sin Producción
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                </table>
                <div style="clear:both;height:10px"></div>
            </td>
        </tr>
        </table>
        <div style="clear:both;height:10px"></div>
        <?php endif; ?>

        <table border="0" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
                <?php if (empty($activeList)): ?>
                    <b class="msg_save_err" style="color:#C00"><i class="fa fa-fw fa-exclamation-triangle"></i> No existen OT's en curso.</b>
                <?php else: ?>
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <colgroup>
                        <col width="60">
                        <col width="60">
                        <col>
                        <col>
                        <col>
                        <col>
                        <col>
                        <col>
                        <col>
                        <col>
                        <col>
                        <col width="110">
                    </colgroup>
                    <tr>
                        <td class="tdheader">OT</td>
                        <td class="tdheader">CC</td>
                        <td class="tdheader">Cliente</td>
                        <td class="tdheader">Código</td>
                        <td class="tdheader">Descripción</td>
                        <td class="tdheader">Tipo</td>
                        <td class="tdheader">Material</td>
                        <td class="tdheader" align="center"><?=$isFlexo ? 'Planificado' : 'Cantidad'?></td>
                        <td class="tdheader" align="center"><?=$isFlexo ? 'Producidas' : 'Producido'?></td>
                        <td class="tdheader" align="center">Saldo</td>
                        <td class="tdheader" align="center">Estado</td>
                        <td class="tdheader" align="center">Opciones</td>
                    </tr>
                    <?php foreach ($activeList as $row):
                        $prodAmt = (float)($row["total_produced"] ?? 0);
                        $itemAmt = (float)($row["item_amount"] ?? $row["ag_amount"]);
                        $saldo = max(0, $itemAmt - $prodAmt);
                    ?>
                    <tr onmouseover="this.style.backgroundColor='#F0F7F6'" onmouseout="this.style.backgroundColor='transparent'">
                        <td class="tdleft"><?=htmlspecialchars((string)$row["prd_number"])?>&nbsp;</td>
                        <td class="tdnrm"><nobr><?=htmlspecialchars((string)$row["req_number"])?></nobr>&nbsp;</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$row["cust_name"])?>&nbsp;</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$row["item_number_prod"])?>&nbsp;</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$row["item_title"])?>&nbsp;</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$row["fab_printtype"])?>&nbsp;</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$row["fab_type"])?>&nbsp;</td>
                        <td class="tdnrm" align="center"><?=number_format((float)$row["ag_amount"], 0, ',', '.')?>&nbsp;</td>
                        <td class="tdnrm" align="center"><?=number_format($prodAmt, 0, ',', '.')?></td>
                        <td class="tdnrm" align="center"><?=number_format($saldo, 0, ',', '.')?></td>
                        <td class="tdnrm" align="center">
                            <b class="msg_save_ok" style="color:#00A85A">Activado</b>
                        </td>
                        <td class="tdnrm" align="center">
                            <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                                <div class="btngreen" style="cursor:pointer;display:inline-block" onclick="location.href='/production/work-orders/operate?agid=<?=$row['ag_id']?>'">
                                    <nobr><i class="fa fa-fw fa-edit" style="color:white;"></i> Ingresar a OT&nbsp;</nobr>
                                </div>
                                <a href="/production/materials/request?agid=<?=$row['ag_id']?>" class="btnblue" style="padding:6px 10px;text-decoration:none;font-size:11px" title="Solicitar o cargar bobinas para esta OT">
                                    <nobr><i class="fa fa-dolly"></i> Bobinas</nobr>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </td>
        </tr>
        </table>
    <?php endif; ?>
    <?php
    echo renderDesignPreviewModal();
    renderStandbyAndLunchModals($activeStandby, $activeInit, $operatorName, '/production/work-orders/active');
    $content = ob_get_clean();
    renderProdwrkShell('En cursos', $content, 5, $operatorName, $plantaName);
}

function renderProductionHistoryScreen(array $historyList, string $operatorName, string $plantaName): void
{
    ob_start();
    ?>
    <div style="font-size:20px;margin-bottom:10px"><b>Ordenes de trabajo</b></div>
    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
    <tr>
        <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
            <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
            <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
            <b>Historico</b> de órdenes finalizadas
        </td>
    </tr>
    </table>

    <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
        <table border="0" width="100%" cellpadding="8" cellspacing="0">
        <colgroup><col width="90"><col width="90"><col width="160"><col><col width="160"><col width="140"><col width="100"></colgroup>
        <thead>
            <tr>
                <td class="tdheader">N° OT</td>
                <td class="tdheader">N° CC</td>
                <td class="tdheader">Máquina</td>
                <td class="tdheader">Operario</td>
                <td class="tdheader">Cliente / Producto</td>
                <td class="tdheader">Fecha Cierre</td>
                <td class="tdheader" align="center">Ver</td>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($historyList)): ?>
            <tr><td colspan="7" class="tdnrm" style="text-align:center;padding:25px;color:#999">No hay órdenes en el historial reciente.</td></tr>
        <?php else: ?>
            <?php foreach ($historyList as $row): ?>
                <tr>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)$row['prd_number'])?></b></td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$row['req_number'])?></td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$row['equipo_name'])?></td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$row['wrk_firstname'])?> <?=htmlspecialchars((string)$row['wrk_lastname'])?></td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$row['cust_name'])?> · <?=htmlspecialchars((string)$row['item_title'])?></td>
                    <td class="tdnrm"><?=date('d.m.Y H:i', (int)$row['wok_enddat'])?></td>
                    <td class="tdnrm" align="center">
                        <a href="/production/work-orders/operate?agid=<?=$row['ag_id']?>" class="btngrey" style="padding:4px 10px;text-decoration:none">
                            <i class="fa fa-eye"></i> Detalle
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        </table>
    </div>
    <?php
    $content = ob_get_clean();
    renderProdwrkShell('Historico OTs', $content, 3, $operatorName, $plantaName);
}

function renderProductionTraceabilityScreen(
    string $query,
    ?array $tree,
    string $operatorName = 'Operador Planta',
    string $plantaName = 'Planta Santiago'
): void {
    $body = '<div class="card" style="margin-bottom:20px;background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%);color:#fff;padding:24px;border-radius:12px">';
    $body .= '<div style="font-size:22px;font-weight:900;margin-bottom:6px;display:flex;align-items:center;gap:10px">';
    $body .= '<i class="fa fa-fw fa-sitemap" style="color:#38bdf8"></i> Trazabilidad Integral "Inicio a Fin"';
    $body .= '</div>';
    $body .= '<div style="font-size:13px;color:#94a3b8;margin-bottom:20px">';
    $body .= 'Rastreo genealógico 360° desde la Orden de Compra y Proveedor remoto, pasando por la bobina pesada en balanza, su procesamiento en máquinas y operarios, hasta la caja o bobina hija resultante.';
    $body .= '</div>';

    $body .= '<form method="get" action="/production/traceability" style="display:flex;gap:10px;flex-wrap:wrap;margin:0">';
    $body .= '<input class="input" type="text" name="q" value="' . htmlspecialchars($query) . '" required placeholder="Ingresa Bobina (ROLL-...), Caja (BOX-...), OT (22079) u OC (OC0004205)" style="flex:1;min-width:280px;background:#1e293b;border-color:#334155;color:#fff;font-size:15px">';
    $body .= '<button class="btn" type="submit" style="background:#38bdf8;border-color:#0284c7;color:#0f172a;font-weight:800;padding:10px 24px"><i class="fa fa-fw fa-search"></i> Rastrear</button>';
    if ($query !== '') {
        $body .= '<a class="btn secondary" href="/production/traceability" style="background:#334155;color:#f8fafc;border-color:#475569">Limpiar</a>';
    }
    $body .= '</form>';
    $body .= '</div>';

    if ($query === '') {
        $body .= '<div class="card" style="text-align:center;padding:48px 20px">';
        $body .= '<div style="font-size:48px;color:#94a3b8;margin-bottom:16px"><i class="fa fa-project-diagram"></i></div>';
        $body .= '<div style="font-size:18px;font-weight:800;color:#1e293b;margin-bottom:8px">Consulta de Trazabilidad</div>';
        $body .= '<div class="muted" style="max-width:520px;margin:0 auto 20px">Ingresa en el buscador superior el número de bobina, código de barras de caja, orden de trabajo u orden de compra para auditar toda la cadena de custodia.</div>';
        $body .= '</div>';
        renderProdwrkShell('Trazabilidad Integral', $body, 6, $operatorName, $plantaName);
        return;
    }

    if ($tree === null || ($tree['roll'] === null && $tree['work_order'] === null && $tree['purchase_order'] === null && empty($tree['boxes']) && empty($tree['pallet']))) {
        $body .= '<div class="card" style="text-align:center;padding:40px 20px">';
        $body .= '<div style="font-size:40px;color:#ef4444;margin-bottom:12px"><i class="fa fa-exclamation-triangle"></i></div>';
        $body .= '<div style="font-size:18px;font-weight:800;color:#1e293b;margin-bottom:6px">No se encontraron registros para "' . htmlspecialchars($query) . '"</div>';
        $body .= '<div class="muted">Verifica que el identificador ingresado esté escrito correctamente o haya sido recepcionado en el sistema.</div>';
        $body .= '</div>';
        renderProdwrkShell('Trazabilidad Integral', $body, 6, $operatorName, $plantaName);
        return;
    }

    if (function_exists('unibagBuildTraceabilityTreeHtml')) {
        $treeForFlow = $tree;
        $treeForFlow['query'] = $query;

        $body .= '<div class="card" style="margin-bottom:18px;background:#ffffff;border:1px solid #dbeafe;border-radius:12px;padding:16px 18px;box-shadow:0 2px 8px rgba(15,23,42,0.05)">';
        $body .= '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">';
        $body .= '<div>';
        $body .= '<div style="font-size:17px;font-weight:900;color:#0f172a;margin-bottom:4px">Flujo real de planta</div>';
        $body .= '<div style="font-size:12.5px;color:#475569;max-width:820px">';
        $body .= 'La trazabilidad se muestra siguiendo el recorrido operativo del material: origen y compra, ingreso físico a bodega, uso en máquina, transformación a bobinas hijas o cajas, y finalmente cadena de custodia / despacho.';
        $body .= '</div>';
        $body .= '</div>';
        $body .= '<div style="display:flex;gap:8px;flex-wrap:wrap">';
        $body .= '<span style="background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;padding:5px 10px;border-radius:999px;font-size:11.5px;font-weight:800">Origen</span>';
        $body .= '<span style="background:#f0fdfa;color:#0f766e;border:1px solid #99f6e4;padding:5px 10px;border-radius:999px;font-size:11.5px;font-weight:800">Bodega</span>';
        $body .= '<span style="background:#fffbeb;color:#b45309;border:1px solid #fde68a;padding:5px 10px;border-radius:999px;font-size:11.5px;font-weight:800">Producción</span>';
        $body .= '<span style="background:#faf5ff;color:#6d28d9;border:1px solid #e9d5ff;padding:5px 10px;border-radius:999px;font-size:11.5px;font-weight:800">Bobinas / Cajas</span>';
        $body .= '<span style="background:#f8fafc;color:#334155;border:1px solid #cbd5e1;padding:5px 10px;border-radius:999px;font-size:11.5px;font-weight:800">Custodia</span>';
        $body .= '</div>';
        $body .= '</div>';
        $body .= '</div>';

        $body .= unibagBuildTraceabilityTreeHtml($treeForFlow, false);
        renderProdwrkShell('Trazabilidad ' . htmlspecialchars($query), $body, 6, $operatorName, $plantaName);
        return;
    }

    $po = $tree['purchase_order'];
    $supp = $tree['supplier'];
    $roll = $tree['roll'];
    $parentRoll = $tree['parent_roll'];
    $wo = $tree['work_order'];
    $boxes = $tree['boxes'] ?? [];
    $pallet = $tree['pallet'];
    $childRolls = $tree['child_rolls'] ?? [];
    $movs = $tree['movements'] ?? [];

    // Determinar la entidad primaria rastreada
    $primaryType = 'ELEMENTO EN PLANTA';
    $primaryCode = $query;
    $primaryBadgeColor = '#3b82f6';
    if ($tree['box'] !== null) {
        $primaryType = 'CAJA TERMINADA';
        $primaryCode = (string)$tree['box']['box_code'];
        $primaryBadgeColor = '#f59e0b';
    } elseif ($pallet !== null) {
        $primaryType = 'PALLET DE DESPACHO';
        $primaryCode = (string)$pallet['pallet_code'];
        $primaryBadgeColor = '#8b5cf6';
    } elseif ($roll !== null) {
        $stage = (string)($roll['process_stage'] ?? 'RAW');
        if ($stage === 'PRINTED') {
            $primaryType = 'BOBINA SEMIELABORADA (FLEXO)';
            $primaryBadgeColor = '#0284c7';
        } elseif ($stage === 'CUT') {
            $primaryType = 'BOBINA CORTADA (REBOBINADORA)';
            $primaryBadgeColor = '#7c3aed';
        } else {
            $primaryType = 'BOBINA VIRGEN (RECEPCIÓN)';
            $primaryBadgeColor = '#10b981';
        }
        $primaryCode = (string)$roll['roll_code'];
    } elseif ($wo !== null) {
        $primaryType = 'ORDEN DE TRABAJO (OT)';
        $primaryCode = 'OT #' . ($wo['prd_number'] ?? $query);
        $primaryBadgeColor = '#ea580c';
    } elseif ($po !== null) {
        $primaryType = 'ORDEN DE COMPRA (OC)';
        $primaryCode = 'OC #' . ($po['po_code'] ?? $query);
        $primaryBadgeColor = '#059669';
    }

    // Encabezado de Identificación y Diagnóstico
    $body .= '<div class="card" style="margin-bottom:20px;padding:20px;border-left:6px solid ' . $primaryBadgeColor . ';background:#fff;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.06)">';
    $body .= '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px">';
    $body .= '<div>';
    $body .= '<div style="font-size:12px;font-weight:bold;color:#64748b;text-transform:uppercase;letter-spacing:0.5px">Identificador Activo</div>';
    $body .= '<div style="font-size:24px;font-weight:900;color:#0f172a;display:flex;align-items:center;gap:10px">';
    $body .= htmlspecialchars($primaryCode);
    $body .= '<span style="font-size:12px;background:' . $primaryBadgeColor . ';color:#fff;padding:4px 10px;border-radius:12px;font-weight:bold">' . $primaryType . '</span>';
    $body .= '</div>';
    $body .= '</div>';

    $body .= '<div style="display:flex;gap:10px;flex-wrap:wrap">';
    if ($roll !== null) {
        $body .= '<a href="/rolls/' . (int)$roll['id'] . '/label" target="_blank" class="btn" style="background:#0284c7;color:#fff;text-decoration:none"><i class="fa fa-print"></i> Etiqueta Bobina 100×70</a>';
    }
    if ($tree['box'] !== null) {
        $body .= '<a href="/boxes/' . (int)$tree['box']['id'] . '/label" target="_blank" class="btn" style="background:#f59e0b;color:#fff;text-decoration:none"><i class="fa fa-print"></i> Etiqueta Caja 100×70</a>';
    }
    if ($pallet !== null) {
        $body .= '<a href="/pallets/' . (int)$pallet['id'] . '/label" target="_blank" class="btn" style="background:#8b5cf6;color:#fff;text-decoration:none"><i class="fa fa-print"></i> Etiqueta Pallet 100×70</a>';
    }
    if ($wo !== null && !empty($wo['wok_ag_id'])) {
        $body .= '<a href="/production/work-orders/operate?agid=' . (int)$wo['wok_ag_id'] . '" class="btn" style="background:#115452;color:#fff;text-decoration:none"><i class="fa fa-cogs"></i> Consola Planta</a>';
    }
    $body .= '</div>';
    $body .= '</div>';

    // Barra de etapas activas en la genealogía
    $hasStage1 = ($po !== null || $supp !== null || ($roll !== null && ($roll['process_stage'] ?? '') === 'RAW') || $parentRoll !== null || !empty($tree['virgin_roll']));
    $hasStage2 = ($wo !== null || ($roll !== null && ($roll['process_stage'] ?? '') === 'PRINTED') || ($parentRoll !== null && ($parentRoll['process_stage'] ?? '') === 'PRINTED'));
    $hasStage3 = (!empty($childRolls) || ($roll !== null && ($roll['process_stage'] ?? '') === 'CUT'));
    $hasStage4 = ($wo !== null && !empty($wo['type_ant_title']) && stripos($wo['type_ant_title'], 'seri') !== false);
    $hasStage5 = (!empty($boxes) || $pallet !== null);
    $hasStage6 = (!empty($movs));

    $body .= '<div style="margin-top:15px;padding-top:15px;border-top:1px solid #e2e8f0;display:flex;gap:8px;flex-wrap:wrap;font-size:12px">';
    $body .= '<span style="padding:4px 10px;border-radius:4px;font-weight:bold;background:' . ($hasStage1 ? '#dcfce7;color:#166534' : '#f1f5f9;color:#94a3b8') . '"><i class="fa fa-check-circle"></i> 1. Recepción MP</span>';
    $body .= '<span style="padding:4px 10px;border-radius:4px;font-weight:bold;background:' . ($hasStage2 ? '#e0f2fe;color:#0369a1' : '#f1f5f9;color:#94a3b8') . '"><i class="fa fa-check-circle"></i> 2. Flexografía</span>';
    $body .= '<span style="padding:4px 10px;border-radius:4px;font-weight:bold;background:' . ($hasStage3 ? '#f3e8ff;color:#6b21a8' : '#f1f5f9;color:#94a3b8') . '"><i class="fa fa-check-circle"></i> 3. Rebobinadora</span>';
    $body .= '<span style="padding:4px 10px;border-radius:4px;font-weight:bold;background:' . ($hasStage4 ? '#fce7f3;color:#9d174d' : '#f1f5f9;color:#94a3b8') . '"><i class="fa fa-check-circle"></i> 4. Serigrafía</span>';
    $body .= '<span style="padding:4px 10px;border-radius:4px;font-weight:bold;background:' . ($hasStage5 ? '#fef3c7;color:#92400e' : '#f1f5f9;color:#94a3b8') . '"><i class="fa fa-check-circle"></i> 5. Selladora / Cajas</span>';
    $body .= '<span style="padding:4px 10px;border-radius:4px;font-weight:bold;background:' . ($hasStage6 ? '#e0e7ff;color:#3730a3' : '#f1f5f9;color:#94a3b8') . '"><i class="fa fa-check-circle"></i> 6. Despacho PT</span>';
    $body .= '</div>';
    $body .= '</div>';

    // =========================================================================
    // ETAPA 1: RECEPCIÓN Y MATERIA PRIMA (PROVEEDOR & BOBINA VIRGEN)
    // =========================================================================
    $virgin = $tree['virgin_roll'] ?? ($parentRoll && ($parentRoll['process_stage'] ?? '') === 'RAW' ? $parentRoll : ($parentRoll ?? ($roll && ($roll['process_stage'] ?? '') === 'RAW' ? $roll : null)));
    $body .= '<div class="card" style="margin-bottom:18px;border-radius:8px;overflow:hidden;border:1px solid #cbd5e1;background:#fff">';
    $body .= '<div style="background:#10b981;color:#fff;padding:12px 18px;font-weight:bold;font-size:15px;display:flex;align-items:center;justify-content:space-between">';
    $body .= '<div><i class="fa fa-truck-loading"></i> ETAPA 1: Ingreso de Mercadería y Proveedor (Materia Prima)</div>';
    if ($virgin) {
        $body .= '<a href="/rolls/' . (int)$virgin['id'] . '/label" target="_blank" style="color:#fff;font-size:12px;background:rgba(0,0,0,0.2);padding:4px 10px;border-radius:4px;text-decoration:none"><i class="fa fa-print"></i> Etiqueta Recepción</a>';
    }
    $body .= '</div>';
    $body .= '<div style="padding:18px">';

    $body .= '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:16px">';
    $body .= '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:12px">';
    $body .= '<div style="font-size:11px;font-weight:bold;color:#64748b;text-transform:uppercase;margin-bottom:4px">Proveedor / Origen</div>';
    $body .= '<div style="font-size:14px;font-weight:bold;color:#1e293b">' . htmlspecialchars((string)($supp['name'] ?? 'Proveedor Extranjero / Nacional')) . '</div>';
    $body .= '<div style="font-size:12px;color:#475569">RUT: ' . htmlspecialchars((string)($supp['rut'] ?? '-')) . ' · País: ' . htmlspecialchars((string)($supp['country'] ?? 'Chile')) . '</div>';
    if ($po) {
        $body .= '<div style="margin-top:6px;font-size:12px;color:#0369a1;font-weight:bold"><i class="fa fa-file-invoice"></i> Orden de Compra: ' . htmlspecialchars($po['po_code']) . ' (' . htmlspecialchars($po['date']) . ')</div>';
    }
    $body .= '</div>';

    $body .= '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:12px">';
    $body .= '<div style="font-size:11px;font-weight:bold;color:#64748b;text-transform:uppercase;margin-bottom:4px">Bobina Virgen Recepcionada</div>';
    if ($virgin) {
        $body .= '<div style="font-size:14px;font-weight:bold;color:#1e293b">' . htmlspecialchars((string)$virgin['roll_code']) . ' <span style="font-size:11px;color:#10b981;font-weight:bold">[VIRGEN]</span></div>';
        $body .= '<div style="font-size:12px;color:#475569">SKU: ' . htmlspecialchars((string)($virgin['sku_code'] ?? '-')) . ' · ' . htmlspecialchars((string)($virgin['sku_desc'] ?? '')) . '</div>';
        $body .= '<div style="font-size:12px;color:#0f172a;margin-top:4px"><b>' . number_format((float)($virgin['weight_kg'] ?? 0), 2, ',', '.') . ' Kg</b> · ' . number_format((float)($virgin['meters'] ?? 0), 0, ',', '.') . ' m · ' . (int)($virgin['width_mm'] ?? 0) . ' mm</div>';
    } else {
        $body .= '<div style="font-size:13px;color:#94a3b8;font-style:italic">Bobina madre asignada directamente desde inventario central (Bodega 3000).</div>';
    }
    $body .= '</div>';
    $body .= '</div>';
    $body .= '</div>';
    $body .= '</div>';

    // =========================================================================
    // ETAPA 2: FLEXOGRAFÍA (IMPRESIÓN EN MÁQUINA)
    // =========================================================================
    $printedRoll = ($roll && ($roll['process_stage'] ?? '') === 'PRINTED') ? $roll : (($parentRoll && ($parentRoll['process_stage'] ?? '') === 'PRINTED') ? $parentRoll : null);
    $body .= '<div class="card" style="margin-bottom:18px;border-radius:8px;overflow:hidden;border:1px solid #cbd5e1;background:#fff">';
    $body .= '<div style="background:#0284c7;color:#fff;padding:12px 18px;font-weight:bold;font-size:15px;display:flex;align-items:center;justify-content:space-between">';
    $body .= '<div><i class="fa fa-print"></i> ETAPA 2: Flexografía (Tiraje de Impresión)</div>';
    if ($printedRoll) {
        $body .= '<a href="/rolls/' . (int)$printedRoll['id'] . '/label" target="_blank" style="color:#fff;font-size:12px;background:rgba(0,0,0,0.2);padding:4px 10px;border-radius:4px;text-decoration:none"><i class="fa fa-print"></i> Etiqueta Bobina Impresa</a>';
    }
    $body .= '</div>';
    $body .= '<div style="padding:18px">';

    if ($wo) {
        $body .= '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:16px">';
        $body .= '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:12px">';
        $body .= '<div style="font-size:11px;font-weight:bold;color:#0369a1;text-transform:uppercase;margin-bottom:4px">Orden de Trabajo ERP</div>';
        $body .= '<div style="font-size:15px;font-weight:bold;color:#0c4a6e">OT #' . htmlspecialchars((string)$wo['prd_number']) . ' &mdash; Pedido #' . htmlspecialchars((string)($wo['req_number'] ?? '-')) . '</div>';
        $body .= '<div style="font-size:12px;color:#0284c7;font-weight:bold">' . htmlspecialchars((string)($wo['cust_name'] ?? 'Cliente')) . '</div>';
        $body .= '<div style="font-size:12px;color:#475569;margin-top:4px">Máquina: <b>' . htmlspecialchars((string)($wo['equipo_name'] ?? '-')) . '</b> · Operador: <b>' . htmlspecialchars((string)(($wo['wrk_firstname'] ?? '') . ' ' . ($wo['wrk_lastname'] ?? ''))) . '</b></div>';
        $body .= '</div>';

        $body .= '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:12px">';
        $body .= '<div style="font-size:11px;font-weight:bold;color:#0369a1;text-transform:uppercase;margin-bottom:4px">Bobina Semielaborada Resultante (ROLL-SE)</div>';
        if ($printedRoll) {
            $body .= '<div style="font-size:14px;font-weight:bold;color:#0369a1">' . htmlspecialchars((string)$printedRoll['roll_code']) . ' <span style="font-size:11px;color:#0284c7;font-weight:bold">[IMPRESA]</span></div>';
            $body .= '<div style="font-size:12px;color:#475569">Peso: <b>' . number_format((float)($printedRoll['weight_kg'] ?? 0), 2, ',', '.') . ' Kg</b> · Metros: <b>' . number_format((float)($printedRoll['meters'] ?? 0), 0, ',', '.') . ' m</b></div>';
            $body .= '<div style="font-size:12px;color:#0284c7;margin-top:4px">Estado: <b>' . htmlspecialchars((string)$printedRoll['status']) . '</b></div>';
        } else {
            $body .= '<div style="font-size:13px;color:#64748b;font-style:italic">Tiraje procesado en línea o transferido a rebobinado.</div>';
        }
        $body .= '</div>';
        $body .= '</div>';
    } else {
        $body .= '<div style="font-size:13px;color:#94a3b8;font-style:italic">No se registran eventos de flexografía para este código específico.</div>';
    }
    $body .= '</div>';
    $body .= '</div>';

    // =========================================================================
    // ETAPA 3: REBOBINADORA (CORTE / FRACCIONAMIENTO A BOBINAS HIJAS)
    // =========================================================================
    $body .= '<div class="card" style="margin-bottom:18px;border-radius:8px;overflow:hidden;border:1px solid #cbd5e1;background:#fff">';
    $body .= '<div style="background:#7c3aed;color:#fff;padding:12px 18px;font-weight:bold;font-size:15px;display:flex;align-items:center;justify-content:space-between">';
    $body .= '<div><i class="fa fa-cut"></i> ETAPA 3: Rebobinadora (Fraccionamiento a Bobinas Hijas)</div>';
    $body .= '<span style="font-size:12px;background:rgba(0,0,0,0.2);padding:4px 10px;border-radius:4px">' . count($childRolls) . ' bobinas generadas</span>';
    $body .= '</div>';
    $body .= '<div style="padding:18px">';

    if (!empty($childRolls)) {
        $body .= '<div style="overflow-x:auto">';
        $body .= '<table style="width:100%;border-collapse:collapse;font-size:13px">';
        $body .= '<thead><tr style="background:#f5f3ff;color:#5b21b6;text-align:left;border-bottom:2px solid #ddd6fe">';
        $body .= '<th style="padding:8px">Código Bobina Hija</th><th style="padding:8px">Ancho (mm)</th><th style="padding:8px">Largo (m)</th><th style="padding:8px">Peso (Kg)</th><th style="padding:8px">Estado</th><th style="padding:8px;text-align:center">Acción</th>';
        $body .= '</tr></thead><tbody>';
        foreach ($childRolls as $cr) {
            $isCur = ($roll && (int)$roll['id'] === (int)$cr['id']);
            $body .= '<tr style="border-bottom:1px solid #e2e8f0;' . ($isCur ? 'background:#ecfdf5;font-weight:bold' : '') . '">';
            $body .= '<td style="padding:8px"><b>' . htmlspecialchars((string)$cr['roll_code']) . '</b>' . ($isCur ? ' <span style="color:#059669">[CONSULTADA]</span>' : '') . '</td>';
            $body .= '<td style="padding:8px">' . ((int)($cr['width_mm'] ?? 0) > 0 ? (int)$cr['width_mm'] . ' mm' : '-') . '</td>';
            $body .= '<td style="padding:8px">' . number_format((float)($cr['meters'] ?? 0), 0, ',', '.') . ' m</td>';
            $body .= '<td style="padding:8px">' . number_format((float)($cr['weight_kg'] ?? 0), 2, ',', '.') . ' Kg</td>';
            $body .= '<td style="padding:8px"><span style="background:#ddd6fe;color:#5b21b6;padding:2px 8px;border-radius:10px;font-size:11px">' . htmlspecialchars((string)$cr['status']) . '</span></td>';
            $body .= '<td style="padding:8px;text-align:center"><a href="/rolls/' . (int)$cr['id'] . '/label" target="_blank" class="btn" style="background:#7c3aed;color:#fff;padding:3px 8px;font-size:11px;text-decoration:none"><i class="fa fa-print"></i> 100×70</a></td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table>';
        $body .= '</div>';
    } elseif ($roll && ($roll['process_stage'] ?? '') === 'CUT') {
        $body .= '<div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:6px;padding:12px">';
        $body .= '<div style="font-weight:bold;color:#5b21b6;margin-bottom:4px"><i class="fa fa-info-circle"></i> Bobina Hija Seleccionada: ' . htmlspecialchars((string)$roll['roll_code']) . '</div>';
        $body .= '<div style="font-size:12px;color:#475569">Ancho: <b>' . (int)($roll['width_mm'] ?? 0) . ' mm</b> · Metros: <b>' . number_format((float)($roll['meters'] ?? 0), 0, ',', '.') . ' m</b> · Peso: <b>' . number_format((float)($roll['weight_kg'] ?? 0), 2, ',', '.') . ' Kg</b></div>';
        if ($parentRoll) {
            $body .= '<div style="margin-top:6px;font-size:12px;color:#7c3aed">Bobina Madre de origen: <a href="/production/traceability?q=' . urlencode((string)$parentRoll['roll_code']) . '" style="color:#7c3aed;font-weight:bold;text-decoration:underline">' . htmlspecialchars((string)$parentRoll['roll_code']) . '</a></div>';
        }
        $body .= '</div>';
    } else {
        $body .= '<div style="font-size:13px;color:#94a3b8;font-style:italic">No se registran cortes de rebobinadora para esta bobina.</div>';
    }
    $body .= '</div>';
    $body .= '</div>';

    // =========================================================================
    // ETAPA 4: SERIGRAFÍA (SI APLICA EN LA OT)
    // =========================================================================
    if ($hasStage4) {
        $body .= '<div class="card" style="margin-bottom:18px;border-radius:8px;overflow:hidden;border:1px solid #cbd5e1;background:#fff">';
        $body .= '<div style="background:#ec4899;color:#fff;padding:12px 18px;font-weight:bold;font-size:15px">';
        $body .= '<i class="fa fa-palette"></i> ETAPA 4: Serigrafía Pulpo / Plana (Estampado)';
        $body .= '</div>';
        $body .= '<div style="padding:18px;font-size:13px;color:#334155">';
        $body .= 'Proceso de estampado serigráfico registrado en OT #' . htmlspecialchars((string)($wo['prd_number'] ?? '')) . ' con control de pasadas Frente y Dorso.';
        $body .= '</div>';
        $body .= '</div>';
    }

    // =========================================================================
    // ETAPA 5: SELLADORA / CONFECCIÓN (CAJAS Y PALLETIZADO)
    // =========================================================================
    $body .= '<div class="card" style="margin-bottom:18px;border-radius:8px;overflow:hidden;border:1px solid #cbd5e1;background:#fff">';
    $body .= '<div style="background:#f59e0b;color:#fff;padding:12px 18px;font-weight:bold;font-size:15px;display:flex;align-items:center;justify-content:space-between">';
    $body .= '<div><i class="fa fa-box-open"></i> ETAPA 5: Confección / Selladora (Cajas y Pallets PT)</div>';
    if ($pallet) {
        $body .= '<a href="/pallets/' . (int)$pallet['id'] . '/label" target="_blank" style="color:#fff;font-size:12px;background:rgba(0,0,0,0.2);padding:4px 10px;border-radius:4px;text-decoration:none"><i class="fa fa-print"></i> Etiqueta Pallet ' . htmlspecialchars($pallet['pallet_code']) . '</a>';
    }
    $body .= '</div>';
    $body .= '<div style="padding:18px">';

    if (!empty($boxes)) {
        $totalUnitsInBoxes = 0;
        foreach ($boxes as $b) {
            $totalUnitsInBoxes += (int)($b['units_qty'] ?? 0);
        }
        $body .= '<div style="margin-bottom:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">';
        $body .= '<span style="font-size:13px;background:#fef3c7;color:#92400e;padding:6px 12px;border-radius:6px;font-weight:bold"><i class="fa fa-boxes"></i> ' . count($boxes) . ' Cajas Generadas</span>';
        $body .= '<span style="font-size:13px;background:#fef3c7;color:#92400e;padding:6px 12px;border-radius:6px;font-weight:bold"><i class="fa fa-shopping-bag"></i> ' . number_format($totalUnitsInBoxes, 0, ',', '.') . ' Unidades Totales</span>';
        if ($pallet) {
            $body .= '<span style="font-size:13px;background:#8b5cf6;color:#fff;padding:6px 12px;border-radius:6px;font-weight:bold"><i class="fa fa-pallet"></i> Pallet: ' . htmlspecialchars($pallet['pallet_code']) . ' (' . (int)($pallet['box_count'] ?? count($boxes)) . ' cajas)</span>';
        }
        $body .= '</div>';

        $body .= '<div style="overflow-x:auto">';
        $body .= '<table style="width:100%;border-collapse:collapse;font-size:13px">';
        $body .= '<thead><tr style="background:#fef3c7;color:#92400e;text-align:left;border-bottom:2px solid #fde68a">';
        $body .= '<th style="padding:8px">Código de Caja</th><th style="padding:8px">Unidades</th><th style="padding:8px">Pallet</th><th style="padding:8px">Destino</th><th style="padding:8px">Operario</th><th style="padding:8px;text-align:center">Acción</th>';
        $body .= '</tr></thead><tbody>';
        foreach ($boxes as $b) {
            $isCurBox = ($tree['box'] && (int)$tree['box']['id'] === (int)$b['id']);
            $body .= '<tr style="border-bottom:1px solid #e2e8f0;' . ($isCurBox ? 'background:#fef9c3;font-weight:bold' : '') . '">';
            $body .= '<td style="padding:8px"><b>' . htmlspecialchars((string)$b['box_code']) . '</b>' . ($isCurBox ? ' <span style="color:#b45309">[CONSULTADA]</span>' : '') . '</td>';
            $body .= '<td style="padding:8px">' . number_format((float)($b['units_qty'] ?? 0), 0, ',', '.') . ' uds</td>';
            $body .= '<td style="padding:8px">' . htmlspecialchars((string)($pallet['pallet_code'] ?? ($b['pallet_id'] ? 'PAL-' . $b['pallet_id'] : '-'))) . '</td>';
            $body .= '<td style="padding:8px">' . htmlspecialchars((string)($b['destination_mode'] ?? 'STOCK')) . '</td>';
            $body .= '<td style="padding:8px">' . htmlspecialchars((string)($b['operator_name'] ?? '-')) . '</td>';
            $body .= '<td style="padding:8px;text-align:center"><a href="/boxes/' . (int)$b['id'] . '/label" target="_blank" class="btn" style="background:#f59e0b;color:#fff;padding:3px 8px;font-size:11px;text-decoration:none"><i class="fa fa-print"></i> 100×70</a></td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table>';
        $body .= '</div>';
    } else {
        $body .= '<div style="font-size:13px;color:#94a3b8;font-style:italic">Esta orden de trabajo o bobina aún no tiene cajas de confección generadas.</div>';
    }
    $body .= '</div>';
    $body .= '</div>';

    // =========================================================================
    // ETAPA 6: BODEGA PT & HISTORIAL DE MOVIMIENTOS Y DESPACHO
    // =========================================================================
    $body .= '<div class="card" style="margin-bottom:18px;border-radius:8px;overflow:hidden;border:1px solid #cbd5e1;background:#fff">';
    $body .= '<div style="background:#4f46e5;color:#fff;padding:12px 18px;font-weight:bold;font-size:15px">';
    $body .= '<i class="fa fa-dolly"></i> ETAPA 6: Bodega de Producto Terminado & Despacho';
    $body .= '</div>';
    $body .= '<div style="padding:18px">';

    if (!empty($movs)) {
        $body .= '<div style="overflow-x:auto">';
        $body .= '<table style="width:100%;border-collapse:collapse;font-size:13px">';
        $body .= '<thead><tr style="background:#eef2ff;color:#3730a3;text-align:left;border-bottom:2px solid #c7d2fe">';
        $body .= '<th style="padding:8px">Fecha</th><th style="padding:8px">Tipo de Movimiento</th><th style="padding:8px">Entidad</th><th style="padding:8px">Detalle / Destino</th>';
        $body .= '</tr></thead><tbody>';
        foreach ($movs as $mv) {
            $pld = !empty($mv['payload']) ? json_decode((string)$mv['payload'], true) : [];
            $body .= '<tr style="border-bottom:1px solid #e2e8f0">';
            $body .= '<td style="padding:8px">' . htmlspecialchars((string)($mv['created_at'] ?? '-')) . '</td>';
            $body .= '<td style="padding:8px"><b style="color:#4f46e5">' . htmlspecialchars((string)($mv['type'] ?? $mv['movement_type'] ?? 'TRASLADO')) . '</b></td>';
            $body .= '<td style="padding:8px">' . htmlspecialchars((string)($mv['entity_type'] ?? 'ITEM')) . ' #' . (int)($mv['entity_id'] ?? 0) . '</td>';
            $body .= '<td style="padding:8px">' . htmlspecialchars((string)($pld['comments'] ?? ($pld['traceability_code'] ?? '-'))) . '</td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table>';
        $body .= '</div>';
    } else {
        $body .= '<div style="font-size:13px;color:#64748b;line-height:1.6">';
        $body .= 'El producto se encuentra registrado en bodega de planta y disponible para consolidación o despacho final al cliente.';
        $body .= '</div>';
    }
    $body .= '</div>';
    $body .= '</div>';

    renderProdwrkShell('Trazabilidad ' . htmlspecialchars($query), $body, 6, $operatorName, $plantaName);
}

function productionNormalizeMaterialToken(string $value): string
{
    $value = trim(mb_strtolower($value));
    if ($value === '') {
        return '';
    }
    $map = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
    ];
    $value = strtr($value, $map);
    $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';
    return trim($value);
}

function productionResolveInkWarehouseForAgenda(?array $activeInit, ?array $selectedAgenda): array
{
    $activeInit = is_array($activeInit) ? $activeInit : [];
    $selectedAgenda = is_array($selectedAgenda) ? $selectedAgenda : [];
    $machineText = implode(' ', array_filter([
        (string)($activeInit['equipo_name'] ?? ''),
        (string)($activeInit['type_ant_title'] ?? ''),
        (string)($selectedAgenda['equipo_name'] ?? ''),
        (string)($selectedAgenda['type_ant_title'] ?? ''),
    ]));
    $machineNorm = productionNormalizeMaterialToken($machineText);
    $isFlexo = (int)($activeInit['equipo_prod_isprinter_flexo'] ?? $selectedAgenda['equipo_prod_isprinter_flexo'] ?? 0) === 1
        || str_contains($machineNorm, 'flexo');
    $isPulpo = str_contains($machineNorm, 'pulpo');
    $isSeri = (int)($activeInit['equipo_prod_isprinter_seri'] ?? $selectedAgenda['equipo_prod_isprinter_seri'] ?? 0) === 1
        || str_contains($machineNorm, 'seri');

    if ($isFlexo) {
        return ['code' => 3000, 'name' => '3000 INSUMOS EN PRODUCCION', 'erp_storehouse_id' => 16, 'item_prefix' => 'TIN'];
    }
    if ($isPulpo) {
        return ['code' => 3000, 'name' => '3000 INSUMOS EN PRODUCCION', 'erp_storehouse_id' => 16, 'item_prefix' => 'TIN'];
    }
    if ($isSeri) {
        return ['code' => 3000, 'name' => '3000 INSUMOS EN PRODUCCION', 'erp_storehouse_id' => 16, 'item_prefix' => 'TIN'];
    }

    return ['code' => 3000, 'name' => '3000 INSUMOS EN PRODUCCION', 'erp_storehouse_id' => 16, 'item_prefix' => 'TIN'];
}

/**
 * @param array<int,array<string,mixed>> $chemicals
 * @param array<int,string> $colors
 * @return array{recommended: array<int,array<string,mixed>>, others: array<int,array<string,mixed>>}
 */
function productionSplitChemicalsForOt(array $chemicals, array $colors): array
{
    $tokens = [];
    foreach ($colors as $color) {
        $norm = productionNormalizeMaterialToken((string)$color);
        if ($norm === '') {
            continue;
        }
        $tokens[] = $norm;
        foreach (preg_split('/\s+/', $norm) ?: [] as $part) {
            if (mb_strlen($part) >= 3) {
                $tokens[] = $part;
            }
        }
    }
    $tokens = array_values(array_unique(array_filter($tokens)));

    if ($tokens === []) {
        return ['recommended' => [], 'others' => $chemicals];
    }

    $recommended = [];
    $others = [];
    foreach ($chemicals as $chemical) {
        $haystack = productionNormalizeMaterialToken(
            trim((string)($chemical['code'] ?? '')) . ' ' . trim((string)($chemical['name'] ?? ''))
        );
        $matched = false;
        foreach ($tokens as $token) {
            if ($token !== '' && str_contains($haystack, $token)) {
                $matched = true;
                break;
            }
        }
        if ($matched) {
            $recommended[] = $chemical;
        } else {
            $others[] = $chemical;
        }
    }

    return ['recommended' => $recommended, 'others' => $others];
}

// =============================================================================
// PANTALLA: SOLICITAR / CARGAR MATERIALES A LA MÁQUINA (mid=7)
// =============================================================================
function renderProductionMaterialsRequestScreen(
    array $activeInit,
    array $activeOrders,
    array $scheduledOrders,
    int $selectedAgId,
    ?array $selectedAgenda,
    int $workerOtId,
    array $bodega3000Rolls,
    array $centralRolls,
    array $assignedRolls,
    array $chemicals,
    array $inkWarehouse,
    string $operatorName,
    string $plantaName,
    array $unscheduledOrders = []
): void {
    $machineName = (string)($activeInit['equipo_name'] ?? 'MÁQUINA');
    $allOrders = array_merge($activeOrders, $scheduledOrders);
    // Eliminar duplicados por ag_id
    $uniqueOrders = [];
    foreach ($allOrders as $ord) {
        $aid = (int)($ord['ag_id'] ?? 0);
        if ($aid > 0 && !isset($uniqueOrders[$aid])) {
            $uniqueOrders[$aid] = $ord;
        }
    }
    if ($selectedAgId > 0 && $selectedAgenda !== null && !isset($uniqueOrders[$selectedAgId])) {
        $uniqueOrders[$selectedAgId] = $selectedAgenda;
    }

    $savedMaterial = isset($_GET['saved_material']);
    $savedRequest = isset($_GET['saved_request']);
    $savedRequestType = trim((string)($_GET['request_type'] ?? ''));
    $materialKind = trim((string)($_GET['material_kind'] ?? ''));
    $noTrace = isset($_GET['no_trace']);
    $error = isset($_GET['error']) ? trim((string)$_GET['error']) : '';

    $otTitle = $selectedAgenda !== null ? (string)($selectedAgenda['prd_number'] ?? $selectedAgId) : '';
    $custName = $selectedAgenda !== null ? (string)($selectedAgenda['cust_name'] ?? '-') : '-';
    $prodSku = $selectedAgenda !== null ? trim((string)($selectedAgenda['item_number_prod'] ?? '')) : '';
    $prodTitle = $selectedAgenda !== null ? trim((string)($selectedAgenda['item_title'] ?? '')) : '';
    $designName = $selectedAgenda !== null ? trim((string)($selectedAgenda['fab_design_name'] ?? '')) : '';
    $fullDesign = ($prodSku !== '' && $prodTitle !== '') ? "{$prodSku} | {$prodTitle}" : ($prodTitle ?: ($prodSku ?: ($designName ?: '-')));

    $mat = $selectedAgenda !== null ? (!empty($selectedAgenda['fab_type']) ? (string)$selectedAgenda['fab_type'] : 'TNT') : 'TNT';
    $w = $selectedAgenda !== null ? (int)($selectedAgenda['fab_med_width'] ?? 0) : 0;
    $h = $selectedAgenda !== null ? (int)($selectedAgenda['fab_med_height'] ?? 0) : 0;
    $fuelle = $selectedAgenda !== null ? (int)($selectedAgenda['fab_med_fuelle'] ?? 0) : 0;
    $medidasStr = $w > 0 ? ("{$w} x {$h}" . ($fuelle > 0 ? " + {$fuelle}" : "") . " cm") : '-';
    $fabricColor = $selectedAgenda !== null ? (string)($selectedAgenda['fabric_color'] ?? '') : '';
    $agAmount = $selectedAgenda !== null ? (float)($selectedAgenda['ag_amount'] ?? 0) : 0;

    $colores = [];
    if ($selectedAgenda !== null) {
        for ($i = 1; $i <= 6; $i++) {
            $c = trim((string)($selectedAgenda["fab_print_colordesc_{$i}"] ?? ''));
            if ($c !== '') {
                $colores[] = $c;
            }
        }
    }

    $imgHash = $selectedAgenda !== null ? trim((string)($selectedAgenda['fab_design_imagehash'] ?? $selectedAgenda['item_img_hash'] ?? '')) : '';
    $hasImg = ($imgHash !== '' && $imgHash !== 'dummy');
    $imgUrl = $hasImg ? ('/production/design-image?folder=docs.order&hash=' . rawurlencode($imgHash)) : '';

    $otGrams = (float)($selectedAgenda['ot_grams'] ?? 0);
    $otGramsStr = (string)($selectedAgenda['ot_grams_str'] ?? ($otGrams > 0 ? (int)$otGrams . ' gr' : '-'));
    $compatibleCentralRolls = array_values(array_filter(
        $centralRolls,
        static fn(array $roll): bool => !empty($roll['is_compatible'])
    ));
    $inkSuggestion = !empty($colores) ? ('Colores OT: ' . implode(', ', $colores)) : '';
    $inkWarehouseCode = (int)($inkWarehouse['code'] ?? 0);
    $inkWarehouseName = (string)($inkWarehouse['name'] ?? '');
    $inkItemsCount = count($chemicals);
    $preferredMaterialKind = ($materialKind === 'inks' || $savedRequestType === 'chemical') ? 'inks' : 'rolls';
    $isPulpoRequest = (
        stripos((string)($activeInit['equipo_name'] ?? ''), 'pulpo') !== false
        || stripos((string)($activeInit['type_ant_title'] ?? ''), 'pulpo') !== false
        || ($selectedAgenda !== null && stripos((string)($selectedAgenda['equipo_name'] ?? ''), 'pulpo') !== false)
        || ($selectedAgenda !== null && in_array((int)($selectedAgenda['equipo_type_id'] ?? 0), [11, 22], true) && stripos((string)($selectedAgenda['type_ant_title'] ?? ''), 'seri') !== false)
    );
    $isPrinterMachine = (
        (int)($activeInit['equipo_prod_isprinter_flexo'] ?? 0) === 1
        || (int)($activeInit['equipo_prod_isprinter_seri'] ?? 0) === 1
        || in_array((int)($activeInit['equipo_type_id'] ?? 0), [7, 11, 22], true)
        || stripos((string)($activeInit['equipo_name'] ?? ''), 'flexo') !== false
        || stripos((string)($activeInit['equipo_name'] ?? ''), 'seri') !== false
        || stripos((string)($activeInit['equipo_name'] ?? ''), 'pulpo') !== false
        || stripos((string)($activeInit['type_ant_title'] ?? ''), 'flexo') !== false
        || stripos((string)($activeInit['type_ant_title'] ?? ''), 'seri') !== false
        || ($selectedAgenda !== null && (
            (int)($selectedAgenda['equipo_prod_isprinter_flexo'] ?? 0) === 1
            || (int)($selectedAgenda['equipo_prod_isprinter_seri'] ?? 0) === 1
            || in_array((int)($selectedAgenda['equipo_type_id'] ?? 0), [7, 11, 22], true)
            || stripos((string)($selectedAgenda['type_ant_title'] ?? ''), 'seri') !== false
            || stripos((string)($selectedAgenda['equipo_name'] ?? ''), 'seri') !== false
            || stripos((string)($selectedAgenda['type_ant_title'] ?? ''), 'flexo') !== false
            || stripos((string)($selectedAgenda['equipo_name'] ?? ''), 'flexo') !== false
            || stripos((string)($selectedAgenda['equipo_name'] ?? ''), 'pulpo') !== false
        ))
    );

    ob_start();
    ?>
    <div style="font-size:20px;font-weight:bold;color:#115452;margin-bottom:15px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div>
            <i class="fa fa-dolly" style="color:#00A85A"></i> Solicitar / Cargar Materiales a Máquina: <span style="color:#0E7572"><?=htmlspecialchars($machineName)?></span>
        </div>
        <div style="font-size:13px;font-weight:normal;color:#64748B">
            <span style="background:#E2E8F0;padding:4px 10px;border-radius:4px;color:#1E293B">
                <i class="fa fa-user"></i> <?=htmlspecialchars($operatorName)?> &bull; <i class="fa fa-building"></i> <?=htmlspecialchars($plantaName)?>
            </span>
        </div>
    </div>

    <?php if ($savedMaterial): ?>
        <div style="background:#D1FAE5;border:1.5px solid #10B981;border-radius:6px;padding:12px 18px;margin-bottom:15px;color:#065F46;font-size:13px;display:flex;align-items:center;gap:10px">
            <i class="fa fa-check-circle" style="font-size:20px;color:#059669"></i>
            <div>
                <b>¡Material cargado exitosamente a la máquina y operario!</b>
                <?php if ($noTrace): ?>
                    <span style="background:#FEF3C7;color:#92400E;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:bold;margin-left:8px">Sin Trazabilidad Física</span>
                    La bobina quedó registrada directamente para su uso en la orden de trabajo.
                <?php else: ?>
                    La bobina quedó cargada con su código de trazabilidad en Bodega 3000.
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($savedRequest): ?>
        <div style="background:#DBEAFE;border:1.5px solid #60A5FA;border-radius:6px;padding:12px 18px;margin-bottom:15px;color:#1D4ED8;font-size:13px;display:flex;align-items:center;gap:10px">
            <i class="fa fa-paper-plane" style="font-size:20px;color:#2563EB"></i>
            <div>
                <?php if ($savedRequestType === 'chemical'): ?>
                    <b>Solicitud de tinta registrada correctamente.</b>
                    La OT quedó solicitada contra la bodega sugerida <?=$inkWarehouseCode > 0 ? '<b>' . htmlspecialchars((string)$inkWarehouseCode) . '</b>' : ''?><?=($inkWarehouseName !== '' ? ' (' . htmlspecialchars($inkWarehouseName) . ')' : '')?>.
                <?php elseif ($savedRequestType === 'rolls'): ?>
                    <b>Solicitud de bobina registrada correctamente.</b>
                    La bobina quedó traspasada y disponible en <b>Bodega 3000</b> para que luego la tome la máquina que la vaya a consumir.
                <?php else: ?>
                    <b>Solicitud de material registrada correctamente.</b>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div style="background:#FEE2E2;border:1.5px solid #EF4444;border-radius:6px;padding:12px 18px;margin-bottom:15px;color:#991B1B;font-size:13px;display:flex;align-items:center;gap:10px">
            <i class="fa fa-exclamation-triangle" style="font-size:20px;color:#DC2626"></i>
            <div><b>Error en la solicitud de material:</b> <?=htmlspecialchars($error)?></div>
        </div>
    <?php endif; ?>

    <!-- ============================================================== -->
    <!-- SECCIÓN 1: SELECCIONAR ORDEN DE TRABAJO EN PRODUCCIÓN          -->
    <!-- ============================================================== -->
    <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:18px;margin-bottom:18px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
        <div style="font-size:14px;font-weight:bold;color:#0F766E;margin-bottom:12px;display:flex;align-items:center;gap:8px">
            <i class="fa fa-tasks"></i> 1. Seleccione la Orden de Trabajo (OT) que recibirá los materiales
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <label style="font-size:13px;color:#334155;font-weight:bold">Orden de Trabajo:</label>
            <select class="inptxt" style="width:100%;max-width:550px;background:#FFF;font-size:13px;padding:8px" onchange="if(this.value.startsWith('prd:')){ location.href='/production/materials/request?prd_id=' + this.value.substring(4); } else if(this.value !== '') { location.href='/production/materials/request?agid=' + this.value; }">
                <option value="">&lt; Seleccione una OT de esta máquina &gt;</option>
                <?php if (!empty($uniqueOrders)): ?>
                <optgroup label="OTs en Producción / Programadas">
                <?php foreach ($uniqueOrders as $ord):
                    $aid = (int)$ord['ag_id'];
                    $pNum = (string)($ord['prd_number'] ?? $ord['ag_id']);
                    $cName = (string)($ord['cust_name'] ?? '');
                    $pTitle = (string)($ord['item_title'] ?? $ord['fab_design_name'] ?? '');
                    $isSelected = ($aid === $selectedAgId);
                ?>
                    <option value="<?=$aid?>" <?=$isSelected ? 'selected' : ''?>>
                        OT #<?=$pNum?> &mdash; <?=htmlspecialchars($cName)?> (<?=htmlspecialchars(mb_substr($pTitle, 0, 40))?>)
                    </option>
                <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
                <?php if (!empty($unscheduledOrders)): ?>
                <optgroup label="OTs Fuera de Planificación">
                <?php foreach ($unscheduledOrders as $uo):
                    $uPrdId = (int)($uo['prd_id'] ?? 0);
                    $pNum = (string)($uo['prd_number'] ?? $uPrdId);
                    $cName = (string)($uo['cust_name'] ?? '');
                    $pTitle = (string)($uo['item_title'] ?? $uo['fab_design_name'] ?? '');
                    $isSelected = ($selectedAgenda !== null && (int)($selectedAgenda['prd_id'] ?? 0) === $uPrdId);
                ?>
                    <option value="prd:<?=$uPrdId?>" <?=$isSelected ? 'selected' : ''?>>
                        OT #<?=$pNum?> &mdash; <?=htmlspecialchars($cName)?> (<?=htmlspecialchars(mb_substr($pTitle, 0, 40))?>) [Sin planificar]
                    </option>
                <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
            </select>

            <?php if ($selectedAgId > 0): ?>
                <a href="/production/work-orders/operate?agid=<?=$selectedAgId?>" class="btnblue" style="text-decoration:none;padding:7px 15px;font-size:12px">
                    <i class="fa fa-desktop"></i> Ir a Consola de la OT
                </a>
            <?php endif; ?>
        </div>

        <?php if ($selectedAgenda !== null): ?>
            <!-- FICHA TÉCNICA DE LA OT SELECCIONADA -->
            <div style="margin-top:15px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:14px">
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="110">
                    <col width="120">
                    <col width="180">
                    <col>
                    <col width="100">
                    <col width="120">
                    <col width="90">
                </colgroup>
                <thead>
                    <tr style="border-bottom:1px solid #CBD5E1">
                        <td class="tdheader">N° C.C.</td>
                        <td class="tdheader">N° O.T.</td>
                        <td class="tdheader">Cliente</td>
                        <td class="tdheader">Diseño / Producto</td>
                        <td class="tdheader" align="center">Material</td>
                        <td class="tdheader">Medidas</td>
                        <td class="tdheader" align="center">Imagen</td>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($selectedAgenda['req_number'] ?? '-'))?></b></td>
                        <td class="tdnrm"><b style="color:#0F766E">OT #<?=htmlspecialchars($otTitle)?></b></td>
                        <td class="tdnrm"><b><?=htmlspecialchars($custName)?></b></td>
                        <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($fullDesign)?></td>
                        <td class="tdnrm" align="center">
                            <span style="background:#E0F2FE;color:#0369A1;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:bold"><?=htmlspecialchars($mat)?></span>
                        </td>
                        <td class="tdnrm" style="white-space:nowrap"><?=htmlspecialchars($medidasStr)?></td>
                        <td class="tdnrm" align="center">
                            <?php if ($hasImg): ?>
                                <img src="<?=htmlspecialchars($imgUrl)?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #CBD5E1;cursor:pointer" onclick="openDesignModal('<?=htmlspecialchars($imgUrl)?>', '<?=htmlspecialchars($otTitle)?>', '<?=htmlspecialchars(addslashes($custName))?>', '<?=htmlspecialchars(addslashes($fullDesign))?>', '<?=htmlspecialchars($medidasStr)?>')" title="Ver diseño">
                            <?php else: ?>
                                <span style="color:#94A3B8;font-size:12px">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="7" style="background:#FFFFFF;padding:8px 12px;border-bottom:1px solid #E2E8F0;font-size:12px">
                            <?php if (!empty($fabricColor)): ?>
                                <span style="margin-right:15px"><b>Color Tela:</b> <span style="background:#FEF3C7;color:#92400E;padding:2px 6px;border-radius:3px"><?=htmlspecialchars($fabricColor)?></span></span>
                            <?php endif; ?>
                            <?php if ($otGrams > 0): ?>
                                <span style="margin-right:15px"><b>Gramaje Requerido:</b> <span style="background:#E0E7FF;color:#3730A3;padding:2px 6px;border-radius:3px;font-weight:bold"><?=htmlspecialchars($otGramsStr)?></span></span>
                            <?php endif; ?>
                            <?php if ($isPrinterMachine): ?>
                                <span style="margin-right:15px;background:#F0FDF4;color:#166534;border:1px solid #BBF7D0;padding:2px 8px;border-radius:4px;font-weight:bold"><i class="fa fa-print"></i> Requiere Bobina Virgen (Sin Impresión)</span>
                            <?php endif; ?>
                            <?php if (!empty($colores)): ?>
                                <span style="margin-right:15px"><b>Colores Impresión:</b> <?=htmlspecialchars(implode(', ', $colores))?></span>
                            <?php endif; ?>
                            <?php if ($agAmount > 0): ?>
                                <span><b>Cantidad Programada:</b> <b style="color:#0F766E"><?=number_format($agAmount, 0, ',', '.')?></b> bolsas</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($selectedAgId <= 0): ?>
        <div style="background:#FFF;border:1px dashed #CBD5E1;border-radius:8px;padding:40px 20px;text-align:center;color:#64748B">
            <i class="fa fa-arrow-up" style="font-size:32px;color:#94A3B8;margin-bottom:10px;display:block"></i>
            Por favor, <b>seleccione una Orden de Trabajo arriba</b> para consultar las bobinas disponibles en Bodega 3000 o realizar la carga de materiales.
        </div>
    <?php else: ?>

        <!-- ============================================================== -->
        <!-- SECCIÓN 2: PESTAÑAS DE CARGA DE MATERIALES                     -->
        <!-- ============================================================== -->
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
            <button type="button" id="request_kind_btn_rolls" onclick="switchMaterialKind('rolls')" style="padding:9px 16px;border-radius:6px;border:1px solid <?=$preferredMaterialKind === 'rolls' ? '#86efac' : '#cbd5e1'?>;background:<?=$preferredMaterialKind === 'rolls' ? '#ecfdf5' : '#fff'?>;color:<?=$preferredMaterialKind === 'rolls' ? '#166534' : '#475569'?>;font-size:13px;font-weight:<?=$preferredMaterialKind === 'rolls' ? '800' : '700'?>;cursor:pointer">
                <i class="fa fa-dolly"></i> Bobinas Compatibles
            </button>
            <button type="button" id="request_kind_btn_inks" onclick="switchMaterialKind('inks')" style="padding:9px 16px;border-radius:6px;border:1px solid <?=$preferredMaterialKind === 'inks' ? '#93c5fd' : '#cbd5e1'?>;background:<?=$preferredMaterialKind === 'inks' ? '#eff6ff' : '#fff'?>;color:<?=$preferredMaterialKind === 'inks' ? '#1d4ed8' : '#475569'?>;font-size:13px;font-weight:<?=$preferredMaterialKind === 'inks' ? '800' : '700'?>;cursor:pointer">
                <i class="fa fa-tint"></i> Tintas e Insumos
            </button>
        </div>

        <div id="material_kind_rolls" style="display:<?=$preferredMaterialKind === 'inks' ? 'none' : 'block'?>">
        <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05);margin-bottom:18px">
            <div style="display:flex;border-bottom:1px solid #CBD5E1;background:#F8FAFC">
                <button type="button" id="tab_btn_bodega3000" class="tab-btn active" onclick="switchMaterialTab('bodega3000')" style="padding:12px 20px;font-size:13px;font-weight:bold;border:none;background:#FFF;border-bottom:3px solid #00A85A;color:#065F46;cursor:pointer;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-warehouse" style="color:#00A85A"></i> Bobinas en Bodega 3000 (<?=count($bodega3000Rolls)?>)
                </button>
                <button type="button" id="tab_btn_manual" class="tab-btn" onclick="switchMaterialTab('manual')" style="padding:12px 20px;font-size:13px;font-weight:bold;border:none;background:transparent;border-bottom:3px solid transparent;color:#64748B;cursor:pointer;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-bolt" style="color:#D97706"></i> Cargar Sin Trazabilidad (Saltar Trazabilidad)
                </button>
                <button type="button" id="tab_btn_central" class="tab-btn" onclick="switchMaterialTab('central')" style="padding:12px 20px;font-size:13px;font-weight:bold;border:none;background:transparent;border-bottom:3px solid transparent;color:#64748B;cursor:pointer;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-truck-loading" style="color:#0284C7"></i> Solicitar de Bodega Central (100 / 200)
                </button>
            </div>

            <!-- TAB 1: BOBINAS EN BODEGA 3000 -->
            <div id="tab_pane_bodega3000" style="padding:18px;display:block">
                <div style="background:#ECFDF5;border:1px solid #A7F3D0;border-radius:6px;padding:12px 16px;margin-bottom:15px;color:#065F46;font-size:13px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
                    <div>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                            <i class="fa fa-info-circle" style="color:#059669"></i>
                            <b>Bobinas actualmente ubicadas en Bodega 3000 (Planta / Insumos en Producción).</b>
                            <?php if ($isPrinterMachine): ?>
                                <span style="background:#E0F2FE;border:1px solid #BAE6FD;color:#0369A1;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:bold">
                                    <i class="fa fa-filter"></i> Solo Telas Vírgenes (TEL...)
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top:4px;font-size:12px;color:#047857">
                            Estas bobinas ya fueron trasladadas a producción y se muestran solo si son compatibles con la OT seleccionada.
                            <?php if ($isPrinterMachine): ?>
                                <span style="color:#0284C7;font-weight:500">(En Flexografía, Serigrafía y Pulpo se listan exclusivamente telas vírgenes TEL compatibles con la OT).</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <input type="text" id="filter_b3000" placeholder="🔍 Buscar por código, color o descripción..." class="inptxt" style="width:260px;font-size:12px;padding:6px 10px" onkeyup="filterTable('tbl_b3000', this.value)">
                    </div>
                </div>

                <div style="overflow-x:auto">
                    <table id="tbl_b3000" border="0" width="100%" cellpadding="8" cellspacing="0" style="min-width:980px">
                    <colgroup>
                        <col width="140">
                        <col width="90">
                        <col>
                        <col width="100">
                        <col width="110">
                        <col width="100">
                        <col width="110">
                        <col width="130">
                        <col width="140">
                    </colgroup>
                    <thead>
                        <tr>
                            <td class="tdheader">Código / SKU</td>
                            <td class="tdheader" align="center">Material</td>
                            <td class="tdheader">Descripción de Tela</td>
                            <td class="tdheader">Color</td>
                            <td class="tdheader" align="center">Ancho / Gramaje</td>
                            <td class="tdheader" align="right">Metros</td>
                            <td class="tdheader" align="right">Kilos Disp.</td>
                            <td class="tdheader" align="center">Compatibilidad</td>
                            <td class="tdheader" align="center">Acción</td>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($bodega3000Rolls)): ?>
                        <tr>
                            <td colspan="9" class="tdnrm" style="text-align:center;padding:35px 20px;color:#64748B">
                                <i class="fa fa-box-open" style="font-size:28px;color:#94A3B8;margin-bottom:8px;display:block"></i>
                                <?php if ($isPrinterMachine): ?>
                                    No hay bobinas de tela virgen compatibles con esta OT registradas actualmente con stock en Bodega 3000.<br>
                                    Puede cargar una bobina manual en la pestaña <b>"Cargar Sin Trazabilidad"</b> o solicitar telas desde la Bodega Central (100 / 200).
                                <?php else: ?>
                                    No hay bobinas compatibles registradas actualmente en Bodega 3000 para esta OT.<br>
                                    Puede cargar una bobina manual en la pestaña <b>"Cargar Sin Trazabilidad"</b> o solicitar bobinas desde la Bodega Central (100 / 200).
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bodega3000Rolls as $bRoll):
                            $rCode = (string)($bRoll['roll_code'] ?? $bRoll['sku_code'] ?? '-');
                            $rMat = (string)($bRoll['roll_material'] ?? 'TNT');
                            $rDesc = (string)($bRoll['sku_desc'] ?? '-');
                            $rCol = (string)($bRoll['color'] ?? '-');
                            $rW = (float)($bRoll['width_cm'] ?? round(($bRoll['width_mm'] ?? 0) / 10));
                            $rGramms = (int)($bRoll['grammage'] ?? $bRoll['microns'] ?? 80);
                            $rMts = (float)($bRoll['meters'] ?? 0);
                            $rKg = (float)($bRoll['weight_kg'] ?? $bRoll['current_weight_kg'] ?? 0);
                            $isCompat = !empty($bRoll['is_compatible']);
                            $rawId = (string)($bRoll['id'] ?? $bRoll['raw_id'] ?? '');
                            $rIsPrinted = !empty($bRoll['is_printed']);
                            $isBlockedForPrinter = ($isPrinterMachine && $rIsPrinted);
                        ?>
                            <tr style="<?=$isCompat ? 'background-color:#F0FDF4' : ''?>" onmouseover="this.style.backgroundColor='#E6F4EA'" onmouseout="this.style.backgroundColor='<?=$isCompat ? '#F0FDF4' : '#FFFFFF'?>'">
                                <td class="tdnrm">
                                    <b><?=htmlspecialchars($rCode)?></b>
                                    <?php if ($rIsPrinted): ?>
                                        <div style="margin-top:2px"><span style="background:#FEE2E2;color:#991B1B;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:bold"><i class="fa fa-print"></i> Bobina Impresa</span></div>
                                    <?php else: ?>
                                        <div style="margin-top:2px"><span style="background:#F0FDF4;color:#166534;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:bold"><i class="fa fa-check"></i> Bobina Virgen</span></div>
                                    <?php endif; ?>
                                </td>
                                <td class="tdnrm" align="center">
                                    <span style="background:#E2E8F0;color:#1E293B;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:bold"><?=htmlspecialchars($rMat)?></span>
                                </td>
                                <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($rDesc)?></td>
                                <td class="tdnrm"><?=htmlspecialchars($rCol)?></td>
                                <td class="tdnrm" align="center" style="white-space:nowrap">
                                    <b><?=$rW > 0 ? "{$rW} cm" : '-'?></b> &bull; <?=$rGramms > 0 ? "{$rGramms}g" : '-'?>
                                </td>
                                <td class="tdnrm" align="right"><?=$rMts > 0 ? number_format($rMts, 0, ',', '.') . ' m' : '-'?></td>
                                <td class="tdnrm" align="right"><b style="color:#0F766E"><?=number_format($rKg, 1, ',', '.')?> kg</b></td>
                                <td class="tdnrm" align="center">
                                    <?php if ($isBlockedForPrinter): ?>
                                        <span style="background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:bold" title="No apta para Flexo/Serigrafía porque ya fue impresa">
                                            🚫 YA IMPRESA
                                        </span>
                                    <?php elseif ($isCompat): ?>
                                        <span style="background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:bold">
                                            ⭐ COMPATIBLE
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#94A3B8;font-size:11px">Stock General</span>
                                    <?php endif; ?>
                                </td>
                                <td class="tdnrm" align="center">
                                    <?php if ($isBlockedForPrinter): ?>
                                        <button type="button" class="btnblue" style="background:#94A3B8;border-color:#94A3B8;cursor:not-allowed;padding:5px 12px;font-size:12px;opacity:0.7" title="Las máquinas de impresión requieren bobinas vírgenes sin uso previo" disabled>
                                            <i class="fa fa-ban"></i> No Apta
                                        </button>
                                    <?php else: ?>
                                        <form method="post" action="/production/materials/request/assign" style="margin:0" onsubmit="return confirm('¿Confirmar carga de bobina <?=htmlspecialchars(addslashes($rCode))?> (<?=$rKg?> kg) a la máquina y operario?');">
                                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                            <input type="hidden" name="agid" value="<?=$selectedAgId?>">
                                            <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                            <input type="hidden" name="action_mode" value="bodega3000">
                                            <input type="hidden" name="roll_id" value="<?=htmlspecialchars($rawId)?>">
                                            <input type="hidden" name="weight_kg" value="<?=$rKg?>">
                                            <button type="submit" class="btngreen" style="padding:5px 12px;font-size:12px;cursor:pointer">
                                                <i class="fa fa-arrow-circle-right"></i> Cargar a Máquina
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: CARGAR SIN TRAZABILIDAD (SALTAR TRAZABILIDAD) -->
            <div id="tab_pane_manual" style="padding:22px;display:none">
                <div style="background:#FFFBEB;border:1.5px solid #FCD34D;border-radius:6px;padding:16px;margin-bottom:20px;color:#92400E">
                    <div style="font-weight:bold;font-size:14px;margin-bottom:6px;display:flex;align-items:center;gap:8px">
                        <i class="fa fa-bolt" style="font-size:18px;color:#D97706"></i> Carga Directa Sin Trazabilidad (Saltar Trazabilidad Físicamente)
                    </div>
                    <div style="font-size:13px;line-height:1.5">
                        Como recién estamos iniciando la operación del sistema, esta opción le permite <b>saltarse la trazabilidad física por un momento</b> y utilizar bobinas que aún no cuenten con código QR o etiqueta de barras impresa.
                        El material quedará cargado inmediatamente a la máquina <b><?=htmlspecialchars($machineName)?></b> y al operario <b><?=htmlspecialchars($operatorName)?></b> bajo la Bodega 3000, permitiendo continuar la producción sin demoras.
                    </div>
                </div>

                <form method="post" action="/production/materials/request/assign" style="max-width:750px">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$selectedAgId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                    <input type="hidden" name="action_mode" value="manual_sin_trazabilidad">

                    <table border="0" width="100%" cellpadding="8" cellspacing="0">
                    <colgroup><col width="220"><col></colgroup>
                    <tr>
                        <td class="tdleft">Tipo de Material *</td>
                        <td class="tdnrm">
                            <select name="manual_material" class="inptxt" style="width:280px;background:#FFF" required>
                                <option value="TNT" <?=$mat === 'TNT' ? 'selected' : ''?>>TNT (Polipropileno Non-Woven)</option>
                                <option value="PLA" <?=$mat === 'PLA' ? 'selected' : ''?>>PLA (Biodegradable)</option>
                                <option value="PP" <?=$mat === 'PP' ? 'selected' : ''?>>PP (Polipropileno)</option>
                                <option value="OTRO">Otro Material</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Color de la Tela *</td>
                        <td class="tdnrm">
                            <input type="text" name="manual_color" class="inptxt" style="width:280px" value="<?=htmlspecialchars($fabricColor)?>" placeholder="Ej. Blanco, Azul Marino, Negro..." required>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Ancho de la Bobina (cm)</td>
                        <td class="tdnrm">
                            <input type="number" step="0.5" name="manual_width" class="inptxt" style="width:180px" value="<?=$w > 0 ? $w : ''?>" placeholder="Ej. 65"> cm
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Peso Estimado / Kilos (kg) *</td>
                        <td class="tdnrm">
                            <input type="number" step="0.1" name="manual_kg" class="inptxt" style="width:180px" value="85" placeholder="85.0" required> kg
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Metros Lineales Aprox.</td>
                        <td class="tdnrm">
                            <input type="number" step="1" name="manual_meters" class="inptxt" style="width:180px" value="1100" placeholder="1100"> m
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft" valign="top">Comentarios / Observaciones</td>
                        <td class="tdnrm">
                            <textarea name="manual_comments" class="inptxt" style="width:100%;height:60px" placeholder="Lote de fábrica del proveedor, estado del rollo o motivo de bypass de trazabilidad..."></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm" style="padding-top:15px">
                            <button type="submit" class="btngreen" style="font-size:14px;padding:10px 24px;cursor:pointer">
                                <i class="fa fa-play-circle"></i> Cargar a Máquina sin Trazabilidad
                            </button>
                        </td>
                    </tr>
                    </table>
                </form>
            </div>

            <!-- TAB 3: SOLICITAR DE BODEGA CENTRAL -->
            <div id="tab_pane_central" style="padding:18px;display:none">
                <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:6px;padding:12px 16px;margin-bottom:15px;color:#1E40AF;font-size:13px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
                    <div>
                        <i class="fa fa-info-circle"></i> <b>Stock disponible en Bodega Central (100 PLA / 200 PP).</b>
                        Seleccione las bobinas compatibles que requiere <b>traspasar a Bodega 3000</b>. Luego la máquina las tomará desde el stock de producción cuando corresponda.
                    </div>
                    <div>
                        <input type="text" placeholder="🔍 Filtrar stock central..." class="inptxt" style="width:240px;font-size:12px;padding:6px 10px" onkeyup="filterTable('tbl_central', this.value)">
                    </div>
                </div>

                <div style="overflow-x:auto">
                    <table id="tbl_central" border="0" width="100%" cellpadding="8" cellspacing="0" style="min-width:980px">
                    <colgroup>
                        <col width="140">
                        <col width="90">
                        <col>
                        <col width="110">
                        <col width="100">
                        <col width="110">
                        <col width="130">
                        <col width="140">
                    </colgroup>
                    <thead>
                        <tr>
                            <td class="tdheader">Código / SKU</td>
                            <td class="tdheader" align="center">Material</td>
                            <td class="tdheader">Descripción de Tela</td>
                            <td class="tdheader">Bodega Origen</td>
                            <td class="tdheader">Color</td>
                            <td class="tdheader" align="right">Kilos</td>
                            <td class="tdheader" align="center">Compatibilidad</td>
                            <td class="tdheader" align="center">Acción</td>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($compatibleCentralRolls)): ?>
                        <tr><td colspan="8" class="tdnrm" style="text-align:center;padding:30px;color:#94A3B8">No se encontraron bobinas adicionales en las bodegas centrales para las características de esta OT.</td></tr>
                    <?php else: ?>
                        <?php foreach (array_slice($compatibleCentralRolls, 0, 80) as $cRoll):
                            $rCode = (string)($cRoll['roll_code'] ?? $cRoll['sku_code'] ?? '-');
                            $rMat = (string)($cRoll['roll_material'] ?? 'TNT');
                            $rDesc = (string)($cRoll['sku_desc'] ?? '-');
                            $whName = (string)($cRoll['warehouse_name'] ?? 'Bodega Central');
                            $rCol = (string)($cRoll['color'] ?? '-');
                            $rKg = (float)($cRoll['weight_kg'] ?? 0);
                            $isCompat = !empty($cRoll['is_compatible']);
                            $rawId = (string)($cRoll['id'] ?? $cRoll['raw_id'] ?? '');
                        ?>
                            <tr onmouseover="this.style.backgroundColor='#F1F5F9'" onmouseout="this.style.backgroundColor='transparent'">
                                <td class="tdnrm"><b><?=htmlspecialchars($rCode)?></b></td>
                                <td class="tdnrm" align="center"><span style="background:#E2E8F0;padding:2px 6px;border-radius:3px;font-size:11px;font-weight:bold"><?=htmlspecialchars($rMat)?></span></td>
                                <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($rDesc)?></td>
                                <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($whName)?></td>
                                <td class="tdnrm"><?=htmlspecialchars($rCol)?></td>
                                <td class="tdnrm" align="right"><b><?=number_format($rKg, 1, ',', '.')?> kg</b></td>
                                <td class="tdnrm" align="center">
                                    <?php if ($isCompat): ?>
                                        <span style="background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7;padding:2px 6px;border-radius:3px;font-size:11px;font-weight:bold">⭐ Compatible</span>
                                    <?php else: ?>
                                        <span style="color:#94A3B8;font-size:11px">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="tdnrm" align="center">
                                    <form method="post" action="/production/materials/request/assign" style="margin:0">
                                        <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                        <input type="hidden" name="agid" value="<?=$selectedAgId?>">
                                        <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                        <input type="hidden" name="action_mode" value="solicitar_central">
                                        <input type="hidden" name="roll_ids[]" value="<?=htmlspecialchars($rawId)?>">
                                        <button type="submit" class="btnblue" style="padding:4px 10px;font-size:12px;cursor:pointer">
                                            <i class="fa fa-share"></i> Solicitar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    </table>
                </div>
            </div>
        </div>
        </div>

        <div id="material_kind_inks" style="display:<?=$preferredMaterialKind === 'inks' ? 'block' : 'none'?>">
            <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,0.05);margin-bottom:18px">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:14px">
                    <div>
                        <div style="font-size:17px;font-weight:800;color:#1E3A8A;display:flex;align-items:center;gap:9px;margin-bottom:4px">
                            <span style="width:34px;height:34px;border-radius:10px;background:#DBEAFE;color:#1D4ED8;display:inline-flex;align-items:center;justify-content:center;font-size:15px">
                                <i class="fa fa-tint"></i>
                            </span>
                            Solicitud de Tintas para la OT
                        </div>
                        <div style="font-size:12.5px;color:#64748B;line-height:1.55;max-width:760px">
                            Seleccione las tintas disponibles en la bodega correspondiente y registre la cantidad necesaria para la orden de trabajo.
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <span style="background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;padding:6px 10px;border-radius:999px;font-size:11.5px;font-weight:800">
                            <i class="fa fa-warehouse"></i> Bodega <?=htmlspecialchars((string)$inkWarehouseCode)?>
                        </span>
                        <span style="background:#F8FAFC;color:#334155;border:1px solid #E2E8F0;padding:6px 10px;border-radius:999px;font-size:11.5px;font-weight:800">
                            <i class="fa fa-list"></i> <?=htmlspecialchars((string)$inkItemsCount)?> items con stock
                        </span>
                    </div>
                </div>

                <?php if ($isPulpoRequest): ?>
                    <div style="background:#F8FAFC;border:1px solid #CBD5E1;border-radius:10px;padding:12px 14px;margin-bottom:14px;color:#334155;font-size:12.5px;line-height:1.55">
                        <i class="fa fa-info-circle" style="color:#2563EB"></i>
                        En <b>Pulpo</b>, esta pantalla es solo para <b>solicitar tintas e insumos a bodega</b>. La utilización de tinta por color se configura después en la tabla de <b>10 Unidades de Bastidores / Secadores</b> dentro de la OT.
                    </div>
                <?php endif; ?>

                <div style="display:grid;grid-template-columns:minmax(260px, 1.2fr) minmax(240px, 1fr);gap:12px;margin-bottom:16px">
                    <div style="background:linear-gradient(180deg,#F8FBFF 0%,#EFF6FF 100%);border:1px solid #BFDBFE;border-radius:10px;padding:14px 16px;color:#1E40AF">
                        <div style="font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#2563EB;margin-bottom:6px">Bodega de Solicitud</div>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                            <span style="background:#DBEAFE;color:#1D4ED8;padding:4px 10px;border-radius:999px;font-weight:900;font-size:13px"><?=htmlspecialchars((string)$inkWarehouseCode)?></span>
                            <span style="font-size:13px;font-weight:700"><?=htmlspecialchars($inkWarehouseName)?></span>
                        </div>
                        <div style="margin-top:6px;font-size:12px;line-height:1.5;color:#1D4ED8">
                            La solicitud queda asociada a esta bodega para que el equipo de bodega la prepare correctamente.
                        </div>
                    </div>
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:14px 16px;color:#334155">
                        <div style="font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#475569;margin-bottom:6px">Referencia de la OT</div>
                        <div style="font-size:12.5px;line-height:1.55;color:#475569">
                            <?php if ($inkSuggestion !== ''): ?>
                                <?=htmlspecialchars($inkSuggestion)?>
                            <?php else: ?>
                                Sin referencia de color cargada en la OT.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <form method="post" action="/production/materials/request/assign" style="max-width:980px">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$selectedAgId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                    <input type="hidden" name="action_mode" value="chemical_request">
                    <input type="hidden" name="warehouse_code" value="<?=$inkWarehouseCode?>">
                    <input type="hidden" name="warehouse_name" value="<?=htmlspecialchars($inkWarehouseName)?>">
                    <input type="hidden" name="material_unit" value="Unid.">

                    <table border="0" width="100%" cellpadding="8" cellspacing="0">
                    <colgroup><col width="220"><col></colgroup>
                    <tr>
                        <td class="tdleft">Tinta en Bodega *</td>
                        <td class="tdnrm">
                            <div style="margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                                    <input type="text" id="filter_inks" placeholder="Buscar por codigo, descripcion o stock..." class="inptxt" style="width:100%;max-width:360px;font-size:12px;padding:8px 12px;border-radius:8px;background:#FFF" onkeyup="filterTable('tbl_inks', this.value)">
                                    <span style="font-size:11.5px;color:#64748B">Seleccione una fila para solicitarla.</span>
                                </div>
                                <div style="font-size:11.5px;color:#64748B;font-weight:700">
                                    Vista tipo lista para busqueda rapida
                                </div>
                            </div>
                            <div style="overflow:auto;max-height:360px;border:1px solid #CBD5E1;border-radius:10px;background:#FFF;box-shadow:inset 0 1px 0 rgba(255,255,255,0.5)">
                                <table id="tbl_inks" border="0" width="100%" cellpadding="9" cellspacing="0" style="min-width:760px">
                                    <colgroup>
                                        <col width="70">
                                        <col width="170">
                                        <col>
                                        <col width="140">
                                        <col width="120">
                                    </colgroup>
                                    <thead style="position:sticky;top:0;background:#F8FAFC;z-index:1;box-shadow:0 1px 0 #E2E8F0">
                                        <tr>
                                            <td class="tdheader" align="center">Sel.</td>
                                            <td class="tdheader">Codigo</td>
                                            <td class="tdheader">Descripcion</td>
                                            <td class="tdheader" align="right">Stock</td>
                                            <td class="tdheader" align="center">Unidad</td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($chemicals)): ?>
                                        <tr>
                                            <td colspan="5" class="tdnrm" style="text-align:center;padding:18px;color:#64748B">
                                                No se encontraron articulos con stock en esta bodega de tintas.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($chemicals as $idx => $chemical): ?>
                                            <?php
                                                $itemCode = trim((string)($chemical['code'] ?? ''));
                                                $itemName = trim((string)($chemical['name'] ?? ''));
                                                $itemUnit = trim((string)($chemical['unit_name'] ?? 'UNID'));
                                                $itemStock = (float)($chemical['available_qty'] ?? 0);
                                                $requestLabel = (string)($chemical['request_label'] ?? '');
                                                $radioId = 'ink_item_' . $idx;
                                            ?>
                                            <tr onclick="var radio=document.getElementById('<?=htmlspecialchars($radioId)?>'); if(radio){ radio.checked = true; }" style="cursor:pointer;border-bottom:1px solid #F1F5F9" onmouseover="this.style.backgroundColor='#F8FBFF'" onmouseout="this.style.backgroundColor=''">
                                                <td class="tdnrm" align="center" style="vertical-align:middle">
                                                    <input type="radio" id="<?=htmlspecialchars($radioId)?>" name="requested_item_text" value="<?=htmlspecialchars($requestLabel)?>" required style="transform:scale(1.1)">
                                                </td>
                                                <td class="tdnrm" style="vertical-align:middle">
                                                    <div style="font-weight:800;color:#0F172A"><?=htmlspecialchars($itemCode !== '' ? $itemCode : '-')?></div>
                                                </td>
                                                <td class="tdnrm" style="vertical-align:middle">
                                                    <div style="color:#0F172A;font-weight:700"><?=htmlspecialchars($itemName !== '' ? $itemName : $requestLabel)?></div>
                                                    <div style="margin-top:2px;font-size:11px;color:#64748B">Disponible en bodega <?=htmlspecialchars((string)$inkWarehouseCode)?></div>
                                                </td>
                                                <td class="tdnrm" align="right" style="vertical-align:middle">
                                                    <span style="display:inline-flex;align-items:center;justify-content:flex-end;min-width:88px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:999px;padding:4px 10px;font-weight:800;color:#0F766E">
                                                        <?=htmlspecialchars(number_format($itemStock, 2, ',', '.'))?>
                                                    </span>
                                                </td>
                                                <td class="tdnrm" align="center" style="vertical-align:middle">
                                                    <span style="display:inline-flex;align-items:center;justify-content:center;background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;border-radius:999px;padding:4px 10px;font-size:11px;font-weight:800">
                                                        <?=htmlspecialchars($itemUnit !== '' ? $itemUnit : 'UNID')?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Cantidad de Unidades *</td>
                        <td class="tdnrm">
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                                <input type="number" step="1" min="1" name="material_qty" class="inptxt" style="width:180px;height:40px;font-weight:800;text-align:center;border-radius:8px" value="1" required>
                                <span style="color:#475569;font-size:12px;font-weight:bold">unidades</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm" style="padding-top:12px">
                            <button type="submit" class="btnblue" style="padding:11px 22px;font-size:13px;font-weight:800;cursor:pointer;border-radius:8px;box-shadow:0 2px 6px rgba(37,99,235,0.18)">
                                <i class="fa fa-paper-plane"></i> Solicitar Tinta a Bodega <?=htmlspecialchars((string)$inkWarehouseCode)?>
                            </button>
                        </td>
                    </tr>
                    </table>
                </form>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- SECCIÓN 3: MATERIALES Y BOBINAS ASIGNADAS A ESTA OT            -->
        <!-- ============================================================== -->
        <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
            <div style="font-size:14px;font-weight:bold;color:#0F766E;margin-bottom:12px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-history"></i> Materiales y Bobinas Asignadas / Consumidas en esta OT
            </div>

            <?php if (empty($assignedRolls)): ?>
                <div style="padding:15px;color:#64748B;font-size:13px;font-style:italic">
                    Aún no hay bobinas asignadas activamente a esta orden de trabajo. Utilice las pestañas superiores para cargar bobinas de Bodega 3000 o realizar una carga sin trazabilidad.
                </div>
            <?php else: ?>
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <td class="tdheader">Código / Identificador</td>
                        <td class="tdheader">Descripción</td>
                        <td class="tdheader" align="right">Peso (Kg)</td>
                        <td class="tdheader">Operador</td>
                        <td class="tdheader">Fecha Carga</td>
                        <td class="tdheader" align="center">Estado</td>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($assignedRolls as $ar): ?>
                    <tr>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($ar['roll_code'] ?? '-'))?></b></td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($ar['sku_desc'] ?? '-'))?></td>
                        <td class="tdnrm" align="right"><b><?=number_format((float)($ar['weight_kg'] ?? 0), 1, ',', '.')?> kg</b></td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($ar['operator_name'] ?? $operatorName))?></td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($ar['created_at'] ?? date('d.m.Y H:i')))?></td>
                        <td class="tdnrm" align="center">
                            <span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:bold">CARGADA EN MÁQUINA</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <script>
    function switchMaterialTab(tabName) {
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.style.background = 'transparent';
            btn.style.color = '#64748B';
            btn.style.borderBottom = '3px solid transparent';
        });
        document.getElementById('tab_pane_bodega3000').style.display = 'none';
        document.getElementById('tab_pane_manual').style.display = 'none';
        document.getElementById('tab_pane_central').style.display = 'none';

        var activeBtn = document.getElementById('tab_btn_' + tabName);
        if (activeBtn) {
            activeBtn.style.background = '#FFF';
            activeBtn.style.color = '#065F46';
            activeBtn.style.borderBottom = '3px solid #00A85A';
        }
        var activePane = document.getElementById('tab_pane_' + tabName);
        if (activePane) {
            activePane.style.display = 'block';
        }
    }

    function switchMaterialKind(kindName) {
        var rollsBtn = document.getElementById('request_kind_btn_rolls');
        var inksBtn = document.getElementById('request_kind_btn_inks');
        var rollsPane = document.getElementById('material_kind_rolls');
        var inksPane = document.getElementById('material_kind_inks');

        if (rollsPane) rollsPane.style.display = (kindName === 'rolls') ? 'block' : 'none';
        if (inksPane) inksPane.style.display = (kindName === 'inks') ? 'block' : 'none';

        if (rollsBtn) {
            rollsBtn.style.background = (kindName === 'rolls') ? '#ecfdf5' : '#fff';
            rollsBtn.style.color = (kindName === 'rolls') ? '#166534' : '#475569';
            rollsBtn.style.borderColor = (kindName === 'rolls') ? '#86efac' : '#cbd5e1';
        }
        if (inksBtn) {
            inksBtn.style.background = (kindName === 'inks') ? '#eff6ff' : '#fff';
            inksBtn.style.color = (kindName === 'inks') ? '#1d4ed8' : '#475569';
            inksBtn.style.borderColor = (kindName === 'inks') ? '#93c5fd' : '#cbd5e1';
        }
    }

    function filterTable(tableId, query) {
        query = query.toLowerCase();
        var rows = document.querySelectorAll('#' + tableId + ' tbody tr');
        rows.forEach(function(tr) {
            var text = tr.innerText.toLowerCase();
            tr.style.display = (text.indexOf(query) !== -1 || query === '') ? '' : 'none';
        });
    }
    </script>
    <?php
    $content = ob_get_clean();
    renderProdwrkShell('Solicitar Materiales', $content, 7, $operatorName, $plantaName);
}

// =============================================================================
// PANTALLA: BOBINAS EN BODEGA 3000 (mid=8)
// =============================================================================
function renderProductionBodega3000Screen(
    array $bodega3000Rolls,
    string $operatorName,
    string $plantaName
): void {
    $totalKgs = 0.0;
    foreach ($bodega3000Rolls as $r) {
        $totalKgs += (float)($r['weight_kg'] ?? $r['current_weight_kg'] ?? 0);
    }

    ob_start();
    ?>
    <div style="font-size:20px;font-weight:bold;color:#115452;margin-bottom:15px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div>
            <i class="fa fa-warehouse" style="color:#00A85A"></i> Inventario de Bobinas e Insumos en Bodega 3000 (Producción)
        </div>
        <div style="font-size:13px;font-weight:normal">
            <a href="/production/materials/request" class="btngreen" style="text-decoration:none;padding:7px 16px;font-size:12px">
                <i class="fa fa-dolly"></i> Solicitar / Cargar a OT en Curso
            </a>
        </div>
    </div>

    <!-- TARJETAS DE RESUMEN -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:15px;margin-bottom:18px">
        <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:16px;border-left:4px solid #00A85A">
            <div style="font-size:12px;color:#64748B;font-weight:bold;text-transform:uppercase">Total Bobinas / Ítems</div>
            <div style="font-size:24px;font-weight:bold;color:#0F766E;margin-top:4px"><?=number_format(count($bodega3000Rolls), 0, ',', '.')?></div>
            <div style="font-size:11px;color:#94A3B8;margin-top:4px">Disponibles en Planta</div>
        </div>
        <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:16px;border-left:4px solid #0284C7">
            <div style="font-size:12px;color:#64748B;font-weight:bold;text-transform:uppercase">Stock Total en Peso</div>
            <div style="font-size:24px;font-weight:bold;color:#0284C7;margin-top:4px"><?=number_format($totalKgs, 1, ',', '.')?> kg</div>
            <div style="font-size:11px;color:#94A3B8;margin-top:4px">Materia prima e insumos</div>
        </div>
        <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:16px;border-left:4px solid #D97706">
            <div style="font-size:12px;color:#64748B;font-weight:bold;text-transform:uppercase">Bodegas ERP Incluidas</div>
            <div style="font-size:14px;font-weight:bold;color:#92400E;margin-top:6px">16 (Insumos Prod) &bull; 27 (MP Prod)</div>
            <div style="font-size:11px;color:#94A3B8;margin-top:4px">Trazabilidad sincronizada</div>
        </div>
    </div>

    <!-- TABLA DE BOBINAS BODEGA 3000 -->
    <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:15px;flex-wrap:wrap;gap:10px">
            <div style="font-size:14px;font-weight:bold;color:#0F766E">
                <i class="fa fa-boxes"></i> Detalle de Bobinas Físicas en Bodega 3000
            </div>
            <div>
                <input type="text" id="filter_b3000_full" placeholder="🔍 Filtrar por código, tela, color..." class="inptxt" style="width:300px;font-size:12px;padding:6px 12px" onkeyup="filterBodega3000(this.value)">
            </div>
        </div>

        <div style="overflow-x:auto">
            <table id="tbl_b3000_full" border="0" width="100%" cellpadding="8" cellspacing="0" style="min-width:1000px">
            <colgroup>
                <col width="160">
                <col width="100">
                <col>
                <col width="130">
                <col width="120">
                <col width="110">
                <col width="100">
                <col width="110">
                <col width="140">
            </colgroup>
            <thead>
                <tr>
                    <td class="tdheader">Código / Identificador</td>
                    <td class="tdheader" align="center">Material</td>
                    <td class="tdheader">Descripción</td>
                    <td class="tdheader">Bodega ERP / TRZ</td>
                    <td class="tdheader">Color</td>
                    <td class="tdheader" align="center">Ancho / Gramaje</td>
                    <td class="tdheader" align="right">Metros</td>
                    <td class="tdheader" align="right">Stock / Kilos</td>
                    <td class="tdheader" align="center">Acción</td>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($bodega3000Rolls)): ?>
                <tr>
                    <td colspan="9" class="tdnrm" style="text-align:center;padding:35px 20px;color:#64748B">
                        <i class="fa fa-box-open" style="font-size:28px;color:#94A3B8;margin-bottom:8px;display:block"></i>
                        No hay bobinas registradas actualmente en Bodega 3000.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($bodega3000Rolls as $r):
                    $rCode = (string)($r['roll_code'] ?? $r['sku_code'] ?? '-');
                    $rMat = (string)($r['roll_material'] ?? 'TNT');
                    $rDesc = (string)($r['sku_desc'] ?? '-');
                    $whName = (string)($r['warehouse_name'] ?? 'Bodega 3000');
                    $rCol = (string)($r['color'] ?? '-');
                    $rW = (float)($r['width_cm'] ?? round(($r['width_mm'] ?? 0) / 10));
                    $rGramms = (int)($r['microns'] ?? 80);
                    $rMts = (float)($r['meters'] ?? 0);
                    $rKg = (float)($r['weight_kg'] ?? $r['current_weight_kg'] ?? 0);
                    $rawId = (string)($r['id'] ?? $r['raw_id'] ?? '');
                ?>
                    <tr onmouseover="this.style.backgroundColor='#F0FDF4'" onmouseout="this.style.backgroundColor='transparent'">
                        <td class="tdnrm"><b><?=htmlspecialchars($rCode)?></b></td>
                        <td class="tdnrm" align="center"><span style="background:#E2E8F0;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:bold"><?=htmlspecialchars($rMat)?></span></td>
                        <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($rDesc)?></td>
                        <td class="tdnrm" style="font-size:11px;color:#64748B"><?=htmlspecialchars($whName)?></td>
                        <td class="tdnrm"><?=htmlspecialchars($rCol)?></td>
                        <td class="tdnrm" align="center" style="white-space:nowrap"><?=$rW > 0 ? "{$rW} cm" : '-'?> &bull; <?=$rGramms > 0 ? "{$rGramms}g" : '-'?></td>
                        <td class="tdnrm" align="right"><?=$rMts > 0 ? number_format($rMts, 0, ',', '.') . ' m' : '-'?></td>
                        <td class="tdnrm" align="right"><b style="color:#0F766E"><?=number_format($rKg, 1, ',', '.')?> kg</b></td>
                        <td class="tdnrm" align="center">
                            <a href="/production/materials/request?roll_id=<?=rawurlencode($rawId)?>" class="btnblue" style="padding:4px 10px;font-size:11px;text-decoration:none">
                                <i class="fa fa-dolly"></i> Asignar a OT
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            </table>
        </div>
    </div>

    <script>
    function filterBodega3000(query) {
        query = query.toLowerCase();
        var rows = document.querySelectorAll('#tbl_b3000_full tbody tr');
        rows.forEach(function(tr) {
            var text = tr.innerText.toLowerCase();
            tr.style.display = (text.indexOf(query) !== -1 || query === '') ? '' : 'none';
        });
    }
    </script>
    <?php
    $content = ob_get_clean();
    renderProdwrkShell('Bobinas Bodega 3000', $content, 8, $operatorName, $plantaName);
}
