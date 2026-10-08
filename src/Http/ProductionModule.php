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

        $scheduledOrders = $prodService->listScheduledOrdersForMachine($equipoId, $plantaId, $dateFilter);
        $activeOt = $prodService->getOpenWorkerOt($workerId, $plantaId);

        $activeStandby = $prodService->getActiveMachineStandby($equipoId, (int)$activeInit['id']);

        renderProductionNewWorkOrderScreen($activeInit, $scheduledOrders, $activeOt, $currentOperatorName, $plantaName, $activeStandby, $dateFilter);
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

    // -------------------------------------------------------------------------
    // 3. ACCIONES Y FORMULARIOS DE LA OT (POST o GET especiales)
    // -------------------------------------------------------------------------
    // Guardar asignación de Anilox (editxid.php líneas 108-158)
    if (isset($_REQUEST['save_anilox']) && (int)$_REQUEST['save_anilox'] === 1) {
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

        // Caso 2: Finalización de Tiraje o Actualización de Avance
        $amount = (float)str_replace(',', '.', (string)($_REQUEST['prod_amount'] ?? 0));
        $amount2 = (float)str_replace(',', '.', (string)($_REQUEST['prod_amount2'] ?? 0));
        $bobinaKg = (float)str_replace(',', '.', (string)($_REQUEST['prod_bobina_kg'] ?? 0));
        $mmaq = (int)($_REQUEST['evt_amount_metros_maquina'] ?? 0);
        $mlin = (int)($_REQUEST['evt_amount_metros_lineales'] ?? 0);
        $mtype = trim((string)($_REQUEST['evt_metrotype'] ?? ''));
        $isEnd = ($submode === 'end' || $actionType === 'end');

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

        // Guardar Merma de Alistamiento si fue ingresada en el formulario
        $mermaAmt = (float)str_replace(',', '.', (string)($_REQUEST['merma_alis_amount'] ?? 0));
        $mermaKgs = (float)str_replace(',', '.', (string)($_REQUEST['merma_alis_kgs'] ?? 0));
        $mermaMts = (float)str_replace(',', '.', (string)($_REQUEST['merma_alis_mts'] ?? 0));
        $mermaComm = trim((string)($_REQUEST['merma_alis_comments'] ?? ''));
        if ($mermaAmt > 0 || $mermaKgs > 0 || $mermaMts > 0 || $mermaComm !== '') {
            $prodService->saveAlistamientoMerma($workerOtId, $mermaAmt, $mermaKgs, $mermaMts, $mermaComm);
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
                redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=semielaborado&roll_created=' . $childId . '&roll_code=' . urlencode($res['child_roll_code']));
            }
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=semielaborado&error=' . rawurlencode((string)($res['error'] ?? 'Error al crear bobina semielaborada.')));
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

        if (!empty($rollIds)) {
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
                 (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments)
                 VALUES
                 (:ot_id, :amt, :crtdat, 1, 'materiales', :comments)"
            );
            $stmtErp->execute([
                ':ot_id' => $workerOtId,
                ':amt' => $matQty,
                ':crtdat' => $now,
                ':comments' => $desc,
            ]);
            $res = ['ok' => true];
        }

        if ($res['ok']) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=materiales&saved_material=1');
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=materiales&error=' . rawurlencode((string)($res['error'] ?? 'Error al solicitar material.')));
        }
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
            $res = $prodService->mountRollOnMachine($workerOtId, $rollId, $currentOperatorName, $comments);
            $param = $res['ok'] ? '&mounted=1' : '&error=' . rawurlencode((string)($res['error'] ?? 'Error al montar bobina'));
        } elseif ($actionType === 'unmount') {
            $res = $prodService->unmountRollFromMachine($workerOtId, $rollId, $currentOperatorName);
            $param = $res['ok'] ? '&unmounted=1' : '&error=' . rawurlencode((string)($res['error'] ?? 'Error al desmontar bobina'));
        } elseif ($actionType === 'unassign') {
            $res = $prodService->unassignRollFromWorkOrder($workerOtId, $rollId, $currentOperatorName);
            $param = $res['ok'] ? '&unassigned=1' : '&error=' . rawurlencode((string)($res['error'] ?? 'Error al devolver bobina'));
        } else {
            $param = '&error=' . rawurlencode('Acción no válida');
        }

        redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=materiales' . $param);
        return true;
    }

    // Registro de Consumo Real y Retazo de Bobina (Trazabilidad)
    if ($path === '/production/work-orders/materials/consume' && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        $rollId = (int)($_REQUEST['roll_id'] ?? 0);
        $consumedWeight = (float)str_replace(',', '.', (string)($_REQUEST['consumed_weight'] ?? 0));
        $scrapWeight = (float)str_replace(',', '.', (string)($_REQUEST['scrap_weight'] ?? 0));
        $comments = trim((string)($_REQUEST['comments'] ?? ''));

        $res = $prodService->recordRollConsumptionAndScrap(
            $workerOtId,
            $rollId,
            $consumedWeight,
            $scrapWeight,
            $comments,
            $currentOperatorName
        );

        if ($res['ok']) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=materiales&saved_consumption=1');
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=materiales&error=' . rawurlencode((string)($res['error'] ?? 'Error al registrar consumo y retazo.')));
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
                $resRebo = $prodService->executeReboFinalize($agId, $_POST, (int)($_SESSION['user_id'] ?? 4075), (int)($_SESSION['company_id'] ?? 20010), $workerOtId);
                $cutCount = !empty($resRebo['created_rolls']) ? count($resRebo['created_rolls']) : 0;
                redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=reboprod&rebo_done=1&cuts=' . $cutCount);
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
        if ($mode === 'materiales' || $mode === 'cargar') {
            $availableRolls = $prodService->listAvailableRollsForProduction(
                null,
                150,
                $targetColor,
                $targetWidth,
                $targetMaterial,
                $targetGrammage
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
        $scheduledOrders = $prodService->listScheduledOrdersForMachine($equipoId, $plantaId);

        $selectedAgId = isset($_GET['agid']) ? (int)$_GET['agid'] : (int)($_GET['ag_id'] ?? 0);
        if ($selectedAgId <= 0 && !empty($activeOrders)) {
            $selectedAgId = (int)$activeOrders[0]['ag_id'];
        } elseif ($selectedAgId <= 0 && !empty($scheduledOrders)) {
            $selectedAgId = (int)$scheduledOrders[0]['ag_id'];
        }

        $selectedAgenda = $selectedAgId > 0 ? $prodService->getWorkOrderAgendaDetails($selectedAgId) : null;
        $workerOt = ($selectedAgId > 0) ? $prodService->getOpenWorkerOtByAgId($workerId, $plantaId, $selectedAgId) : null;
        $workerOtId = $workerOt !== null ? (int)$workerOt['id'] : 0;

        $bodega3000Rolls = $prodService->listAvailableRollsInBodega3000($selectedAgId);
        $centralRolls = ($selectedAgenda !== null) ? $prodService->listAvailableRollsForProduction(
            null,
            100,
            (string)($selectedAgenda['fabric_color'] ?? ''),
            (float)($selectedAgenda['req_operador_tela_width'] ?? 0),
            (string)($selectedAgenda['fab_type'] ?? ''),
            (float)($selectedAgenda['fab_mat_gramms'] ?? 0)
        ) : [];

        $assignedRolls = ($workerOtId > 0) ? ($prodService->getWorkOrderMaterialQuota($selectedAgId, $workerOtId)['assigned_rolls'] ?? []) : [];

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
            $currentOperatorName,
            $plantaName
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
                    $agId
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
                redirectResponse('/production/materials/request?agid=' . $agId . '&saved_material=1');
            } else {
                redirectResponse('/production/materials/request?agid=' . $agId . '&error=' . rawurlencode((string)($res['error'] ?? 'Error al solicitar bobinas.')));
            }
            return true;
        } elseif ($actionMode === 'insumos_directos') {
            $matType = trim((string)($_POST['material_type'] ?? 'Insumo Directo'));
            $matQty = (float)str_replace(',', '.', (string)($_POST['material_qty'] ?? 0));
            $matUnit = trim((string)($_POST['material_unit'] ?? 'unid'));
            $comments = trim((string)($_POST['comments'] ?? ''));
            $desc = "Solicitud Insumo: {$matType} - Cant: {$matQty} {$matUnit}";
            if ($comments !== '') $desc .= " ({$comments})";

            Db::erpPdo()->prepare("
                INSERT INTO prod_worker_ot_events
                 (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments)
                VALUES
                 (:ot_id, :amt, :crtdat, 1, 'materiales', :comments)
            ")->execute([
                ':ot_id' => $workerOtId,
                ':amt' => $matQty,
                ':crtdat' => time(),
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
            $resRebo = $prodService->executeReboFinalize($agId, $_POST, (int)($_SESSION['user_id'] ?? 4075), (int)($_SESSION['company_id'] ?? 20010), $workerOtId);
            $cutCount = !empty($resRebo['created_rolls']) ? count($resRebo['created_rolls']) : 0;
            $firstCutId = !empty($resRebo['created_rolls'][0]['id']) ? (int)$resRebo['created_rolls'][0]['id'] : 0;
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=reboprod&rebo_done=1&cuts=' . $cutCount . '&first_cut=' . $firstCutId);
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
    $isExclusiveAutocontrol = (!$isRebobinadora && ($gotoAutocontrol || $mode === 'autocontrol') && !in_array($mode, ['materiales', 'cargar', 'mantencion', 'pause', 'terminarot'], true));

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
        ?>

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
                    <td class="tdleft">Tipo de Tela</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)($agenda['fab_type'] ?? '-'))?></b></td>
                    <td class="tdleft">Anilox N° 6</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($aniloxDescMap[6] ?? '-'))?></td>
                </tr>
                </table>
            </div>
        </div>

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

                <?php
                $savedAlisMerma = ($workerOtId > 0) ? (new ProductionService(Db::erpPdo(), Db::trzPdo()))->getAlistamientoMerma($workerOtId) : null;
                $defaultAlisAmt = $savedAlisMerma['amount'] ?? '';
                $defaultAlisKgs = $savedAlisMerma['kgs'] ?? '';
                $defaultAlisMts = $savedAlisMerma['mts'] ?? '';
                $defaultAlisComm = $savedAlisMerma['comments'] ?? '';
                ?>
                <div style="background:#FFFBEB;border:1.5px solid #FCD34D;border-radius:6px;padding:16px;margin-bottom:15px">
                    <div style="font-weight:bold;font-size:14px;color:#92400E;margin-bottom:6px;display:flex;align-items:center;gap:8px">
                        <i class="fa fa-balance-scale" style="font-size:18px"></i> Registro de Merma de Alistamiento (Setup / Puesta a Punto)
                    </div>
                    <div style="font-size:12.5px;color:#B45309;margin-bottom:12px;line-height:1.4">
                        Indique la merma generada durante el alistamiento y calibración de la máquina. Estos valores se transferirán automáticamente a la merma de la OT al <b>Finalizar Producción</b>, por lo que no tendrá que volver a acordarse ni ingresarla al terminar el turno.
                    </div>
                    <table border="0" cellpadding="6" cellspacing="0" style="width:100%">
                    <tr>
                        <td width="25%" style="padding:4px">
                            <label style="display:block;font-size:11.5px;font-weight:bold;color:#78350F;margin-bottom:3px">Bolsas / Unidades:</label>
                            <input type="number" step="1" name="merma_alis_amount" class="inptxt" style="width:100%;font-weight:bold;background:#fff" placeholder="0" value="<?=$defaultAlisAmt > 0 ? (int)$defaultAlisAmt : ''?>">
                        </td>
                        <td width="25%" style="padding:4px">
                            <label style="display:block;font-size:11.5px;font-weight:bold;color:#78350F;margin-bottom:3px">Kilos (kg):</label>
                            <input type="number" step="0.1" name="merma_alis_kgs" class="inptxt" style="width:100%;font-weight:bold;background:#fff" placeholder="0.0" value="<?=$defaultAlisKgs > 0 ? $defaultAlisKgs : ''?>">
                        </td>
                        <td width="25%" style="padding:4px">
                            <label style="display:block;font-size:11.5px;font-weight:bold;color:#78350F;margin-bottom:3px">Metros (m):</label>
                            <input type="number" step="1" name="merma_alis_mts" class="inptxt" style="width:100%;font-weight:bold;background:#fff" placeholder="0" value="<?=$defaultAlisMts > 0 ? (int)$defaultAlisMts : ''?>">
                        </td>
                        <td width="25%" style="padding:4px">
                            <label style="display:block;font-size:11.5px;font-weight:bold;color:#78350F;margin-bottom:3px">Observación / Causa:</label>
                            <input type="text" name="merma_alis_comments" class="inptxt" style="width:100%;background:#fff" placeholder="Ej: Ajuste de registro, calibración..." value="<?=htmlspecialchars((string)$defaultAlisComm)?>">
                        </td>
                    </tr>
                    </table>
                </div>

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
        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px">
        <tr>
            <td width="33%" style="padding-right:3px">
                <div class="btnblue" style="width:100%;box-sizing:border-box;background-color:#293865;padding:10px 0;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=materiales&fromautocontrol=1'">
                    <i class="fa fa-fw <?= $isRebobinadora ? 'fa-dolly' : 'fa-cubes' ?>"></i> <?= $isRebobinadora ? 'Solicitar bobina' : ($isSeriPulpo ? 'Solicitar / Cargar materiales' : 'Utilizar materiales') ?>
                </div>
            </td>
            <td width="34%" style="padding:0 3px">
                <div class="btnred" style="width:100%;box-sizing:border-box;background-color:#D64141;padding:10px 0;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=mantencion&fromautocontrol=1'">
                    <i class="fa fa-fw fa-wrench"></i> Registrar mantención
                </div>
            </td>
            <td width="33%" style="padding-left:3px">
                <div class="btngrey" style="width:100%;box-sizing:border-box;background-color:#7F7F7F;padding:10px 0;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=pause&fromautocontrol=1'">
                    <i class="fa fa-fw fa-coffee"></i> Pausa
                </div>
            </td>
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
            <i class="fa fa-check-circle"></i> <b>Conformación de Anilox guardada exitosamente.</b>
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
                <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=production&refid=<?=$openProdEvent['id']?>&submode=end" class="btnorange" style="font-size:13.5px;padding:9px 20px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-weight:bold;box-shadow:0 2px 4px rgba(0,0,0,0.2)">
                    <i class="fa fa-stop"></i> ⏹️ Finalizar Tiraje de Producción
                </a>
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
                    <td class="tdleft">Bolsas producidas / en curso</td>
                    <td class="tdnrm"><?=$fmt($stats['total_produced_this'])?> de <?=$fmt($agenda['ag_amount'])?> (<?=$fmt($stats['percent_completed_this'], 2)?> %)</td>
                    <td class="tdleft">Anilox N° 4</td>
                    <td class="tdnrm" id="summary_anilox_4"><?=htmlspecialchars((string)($aniloxDescMap[4] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Bolsas producidas / total</td>
                    <td class="tdnrm"><?=$fmt($stats['total_produced_all'])?> de <?=$fmt($agenda['item_amount'])?> (<?=$fmt($stats['percent_completed_all'], 2)?> %)</td>
                    <td class="tdleft">Anilox N° 5</td>
                    <td class="tdnrm" id="summary_anilox_5"><?=htmlspecialchars((string)($aniloxDescMap[5] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Saldo total</td>
                    <td class="tdnrm"><b><?=$fmt($stats['saldo_total'])?> unidades</b></td>
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
                </table>
            </div>
        </div>

        <!-- 3. CONFORMACIÓN DE ANILOX (editxid.php líneas 3829 - 3950) -->
        <style>
        .anilox-swap-highlight {
            background-color: #E8F5E9 !important;
            transition: background-color 0.8s ease;
        }
        .anilox-row-transition {
            transition: background-color 0.3s ease;
        }
        </style>
        <script>
        var isAniloxMoving = false;

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

        function GrabaAnilox(unidadCount) {
            var saveBtn = document.getElementById('btn_grabar_anilox');
            var origHtml = saveBtn ? saveBtn.innerHTML : '';
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fa fa-fw fa-spinner fa-spin" style="color:white;"></i> Guardando...';
            }

            var maxUnits = (typeof unidadCount === 'number' && unidadCount > 1) ? unidadCount : 7;
            var params = new URLSearchParams();
            params.append('agid', '<?=$agId?>');
            params.append('save_anilox', '1');
            params.append('ajax', '1');

            for (var x = 1; x < maxUnits; x++) {
                var rev = document.getElementById('id_anilox_' + x);
                if (rev) {
                    params.append('anilox' + x, rev.value);
                    var sumEl = document.getElementById('summary_anilox_' + x);
                    if (sumEl) {
                        var selectedText = rev.options[rev.selectedIndex] ? rev.options[rev.selectedIndex].text : '-';
                        if (rev.value === '') selectedText = '-';
                        sumEl.innerText = selectedText;
                    }
                }
            }

            fetch('/production/work-orders/operate?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(data) {
                if (data && data.ok) {
                    showAniloxToast('Conformación de Anilox guardada exitosamente.', 'success');
                } else {
                    alert(data && data.error ? data.error : 'Error al guardar Anilox');
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

            var colCurr = document.getElementById('color_' + unidad);
            var colTgt  = document.getElementById('color_' + targetUnidad);
            var selCurr = document.getElementById('id_anilox_' + unidad);
            var selTgt  = document.getElementById('id_anilox_' + targetUnidad);
            var trCurr  = document.getElementById('tr_anilox_' + unidad);
            var trTgt   = document.getElementById('tr_anilox_' + targetUnidad);
            var sumCurr = document.getElementById('summary_anilox_' + unidad);
            var sumTgt  = document.getElementById('summary_anilox_' + targetUnidad);

            if (!colCurr || !colTgt || !selCurr || !selTgt) {
                return;
            }

            // Guardar valores previos para rollback si falla
            var prevColCurr = colCurr.value;
            var prevColTgt  = colTgt.value;
            var prevSelCurr = selCurr.value;
            var prevSelTgt  = selTgt.value;
            var prevSumCurr = sumCurr ? sumCurr.innerText : '';
            var prevSumTgt  = sumTgt ? sumTgt.innerText : '';

            // Intercambio instantáneo en DOM (0ms delay perceptible)
            colCurr.value = prevColTgt;
            colTgt.value  = prevColCurr;
            selCurr.value = prevSelTgt;
            selTgt.value  = prevSelCurr;

            if (sumCurr && sumTgt) {
                sumCurr.innerText = prevSumTgt;
                sumTgt.innerText  = prevSumCurr;
            }

            // Animación visual de intercambio
            if (trCurr) {
                trCurr.classList.add('anilox-swap-highlight');
                setTimeout(function() { trCurr.classList.remove('anilox-swap-highlight'); }, 800);
            }
            if (trTgt) {
                trTgt.classList.add('anilox-swap-highlight');
                setTimeout(function() { trTgt.classList.remove('anilox-swap-highlight'); }, 800);
            }

            // Bloquear temporalmente botones de movimiento para evitar condiciones de carrera
            isAniloxMoving = true;
            var allBtns = document.querySelectorAll('.btn-anilox-mover');
            allBtns.forEach(function(b) {
                b.style.opacity = '0.5';
                b.style.cursor = 'not-allowed';
            });

            // Sincronización en segundo plano con el servidor
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
                    // Revertir DOM si el backend reportó error
                    colCurr.value = prevColCurr;
                    colTgt.value  = prevColTgt;
                    selCurr.value = prevSelCurr;
                    selTgt.value  = prevSelTgt;
                    if (sumCurr && sumTgt) {
                        sumCurr.innerText = prevSumCurr;
                        sumTgt.innerText  = prevSumTgt;
                    }
                    alert(data && data.error ? data.error : 'Error al mover anilox.');
                } else {
                    showAniloxToast('Unidad ' + unidad + ' intercambiada con Unidad ' + targetUnidad, 'success');
                }
            })
            .catch(function(err) {
                // Revertir DOM si falló la red
                colCurr.value = prevColCurr;
                colTgt.value  = prevColTgt;
                selCurr.value = prevSelCurr;
                selTgt.value  = prevSelTgt;
                if (sumCurr && sumTgt) {
                    sumCurr.innerText = prevSumCurr;
                    sumTgt.innerText  = prevSumTgt;
                }
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
        </script>
        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_anilox', 'icon_info_anilox')">
                <span><i class="fa fa-fw fa-circle-notch"></i> Conformación de Anilox</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_anilox">Minimizar</span>
                    <i id="icon_info_anilox" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_anilox" style="padding:15px">
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="60">
                    <col width="30%">
                    <col>
                    <col width="90">
                </colgroup>
                <tr>
                    <td class="tdheader" align="center">Unidad</td>
                    <td class="tdheader" align="center">Color</td>
                    <td class="tdheader" align="center">Anilox</td>
                    <td class="tdheader" align="center">Opciones</td>
                </tr>
                <?php
                $xAni = 1;
                foreach ($detalleAnilox as $ani):
                ?>
                <tr id="tr_anilox_<?=$ani['paow_unidad']?>" class="anilox-row-transition">
                    <td class="tdnrm" style="border-left:1px solid #DDDDDD;text-align:center"><b><?=$ani['paow_unidad']?></b></td>
                    <td class="tdnrm" style="border-left:1px solid #DDDDDD">
                        <input type="text" class="inptxt" id="color_<?=$ani['paow_unidad']?>" name="color_<?=$ani['paow_unidad']?>" style="width:100%" value="<?=htmlspecialchars((string)$ani['paow_color'])?>" disabled>
                    </td>
                    <td class="tdnrm" style="border-left:1px solid #DDDDDD">
                        <select name="id_anilox_<?=$ani['paow_unidad']?>" id="id_anilox_<?=$ani['paow_unidad']?>" class="inptxt" style="width:100%;background-color:#FFFFFF">
                            <option value="">&lt; Seleccione Anilox &gt;</option>
                            <?php foreach ($itemAnilox as $ia): ?>
                                <option value="<?=$ia['id']?>" <?=(int)$ia['id'] === (int)$ani['paow_anilox'] ? 'selected' : ''?>>
                                    <?=htmlspecialchars((string)($ia['item_number_prod'] . '-' . $ia['item_title']))?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="tdnrm" style="border-left:1px solid #DDDDDD;text-align:center">
                        <button type="button" class="btngrey btn-anilox-mover" style="padding:4px 8px;font-size:12px;cursor:pointer" title="Mover arriba" onclick="MoverFlecha(1, <?=$ani['paow_unidad']?>)">
                            <i class="fa fa-fw fa-arrow-up"></i>
                        </button>
                        <button type="button" class="btngrey btn-anilox-mover" style="padding:4px 8px;font-size:12px;cursor:pointer;margin-left:4px" title="Mover abajo" onclick="MoverFlecha(2, <?=$ani['paow_unidad']?>)">
                            <i class="fa fa-fw fa-arrow-down"></i>
                        </button>
                    </td>
                </tr>
                <?php
                $xAni++;
                endforeach;
                ?>
                <tr>
                    <td colspan="4" style="padding-top:10px">
                        <button type="button" id="btn_grabar_anilox" class="btngreen" style="font-size:13px;padding:8px 22px;cursor:pointer" onclick="GrabaAnilox(<?=$xAni?>)">
                            <i class="fa fa-fw fa-save" style="color:white;"></i> Registrar Anilox&nbsp;
                        </button>
                    </td>
                </tr>
                </table>
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
                    <td class="tdleft">Bolsas producidas / en curso</td>
                    <td class="tdnrm" colspan="3"><?=$fmt($stats['total_produced_this'])?> de <?=$fmt($agenda['ag_amount'])?> (<?=$fmt($stats['percent_completed_this'], 2)?> %)</td>
                </tr>
                <tr>
                    <td class="tdleft">Bolsas producidas / total</td>
                    <td class="tdnrm" colspan="3"><?=$fmt($stats['total_produced_all'])?> de <?=$fmt($agenda['item_amount'])?> (<?=$fmt($stats['percent_completed_all'], 2)?> %)</td>
                </tr>
                <tr>
                    <td class="tdleft">Saldo total</td>
                    <td class="tdnrm" colspan="3"><b><?=$fmt($stats['saldo_total'])?> unidades</b></td>
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
                    <td class="tdleft">Pasadas totales a imprimir</td>
                    <td class="tdnrm" colspan="3">
                        <b><?=$fmt(round((float)$agenda['ag_amount'] / max(1, (int)($agenda['req_operador_bastidoramt'] ?? 1))))?></b>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft">Imágenes impresas en bastidor</td>
                    <td class="tdnrm" colspan="3">
                        <form method="get" action="/production/work-orders/operate" style="margin:0;display:inline-flex;align-items:center;gap:10px">
                            <input type="hidden" name="agid" value="<?=$agId?>">
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
                    <div style="font-weight:bold;color:#115452;font-size:13.5px;margin-bottom:8px">
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
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">N° CC</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['req_number'])?></td>
                    <td class="tdleft">Color N° 2</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_2'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Producto</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_number_prod'])?> | <?=htmlspecialchars((string)$agenda['item_title'])?></td>
                    <td class="tdleft">Color N° 3</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_3'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Materialidad</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                    <td class="tdleft">Color N° 4</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_4'] ?? ''))?></td>
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
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Corte de bolsa</td>
                    <td class="tdnrm"><b><?=$fmt($corte_m2, 4)?> mtrs</b></td>
                    <td class="tdleft">Color N° 2</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_2'] ?? ''))?></td>
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
                            <i class="fa fa-fw fa-play"></i> Iniciar alistamiento&nbsp;
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
        $isStarting = ($openProdEvent === null && ($refId <= 0 || ($_GET['action'] ?? '') === 'start'));
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
        // Pre-llenar automáticamente la Merma de Alistamiento (Tipo 9: Ajuste / Aprobación) registrada en autocontrol
        if ($workerOtId > 0 && empty($mermaMap[9])) {
            $alisMerma = $prodServiceInstance->getAlistamientoMerma($workerOtId);
            if ($alisMerma !== null && ((float)($alisMerma['evt_amount'] ?? 0) > 0 || (float)($alisMerma['evt_kgstounits'] ?? 0) > 0 || (float)($alisMerma['evt_mtstounits'] ?? 0) > 0 || trim((string)($alisMerma['evt_comments'] ?? '')) !== '')) {
                $mermaMap[9] = $alisMerma;
            }
        }
        $valAmount = (float)($currentRefEvt['evt_amount'] ?? 0);
        $valAmount2 = (float)($currentRefEvt['evt_amount2'] ?? 0);
        $valBobinaKg = (float)($currentRefEvt['prod_bobina_kg'] ?? 0);
        $valMmaq = (int)($currentRefEvt['evt_amount_metros_maquina'] ?? 0);
        $valMlin = (int)($currentRefEvt['evt_amount_metros_lineales'] ?? 0);
        $valMtype = (string)($currentRefEvt['evt_metrotype'] ?? ($isFlexo ? 'metros_lineales' : ''));
        $valComments = (string)($currentRefEvt['evt_comments'] ?? '');
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
                        <tr>
                            <td class="tdleft">Tipo de Lectura Medición</td>
                            <td class="tdnrm">
                                <select name="evt_metrotype" class="inptxt" style="width:250px">
                                    <option value="metros_lineales">Metros Lineales</option>
                                    <option value="metros_maquina">Metros Máquina</option>
                                </select>
                            </td>
                        </tr>
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
                                <i class="fa fa-fw fa-play"></i> ▶️ Comenzar Producción Ahora
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
                        <tr>
                            <td class="tdleft">Tipo de Lectura</td>
                            <td class="tdnrm">
                                <select name="evt_metrotype" class="inptxt" style="width:250px">
                                    <option value="metros_lineales" <?=$valMtype === 'metros_lineales' ? 'selected' : ''?>>Metros Lineales</option>
                                    <option value="metros_maquina" <?=$valMtype === 'metros_maquina' ? 'selected' : ''?>>Metros Máquina</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td class="tdleft">Metros Lineales Producidos</td>
                            <td class="tdnrm"><input type="number" step="1" name="evt_amount_metros_lineales" class="inptxt" style="width:200px" value="<?=$valMlin > 0 ? $valMlin : ''?>" placeholder="0"> mtrs</td>
                        </tr>
                        <tr>
                            <td class="tdleft">Metros Máquina</td>
                            <td class="tdnrm"><input type="number" step="1" name="evt_amount_metros_maquina" class="inptxt" style="width:200px" value="<?=$valMmaq > 0 ? $valMmaq : ''?>" placeholder="0"> mtrs</td>
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
                                ?>
                                <tr>
                                    <td class="tdnrm"><b><?=htmlspecialchars((string)$mt['merma_title'])?></b></td>
                                    <td class="tdnrm" align="center">
                                        <input type="number" step="1" name="merma_amount_<?=$mtId?>" class="inptxt" style="width:90px;text-align:center" value="<?=$mAmt > 0 ? (int)$mAmt : ''?>">
                                    </td>
                                    <td class="tdnrm" align="center">
                                        <input type="number" step="0.1" name="merma_kgs_<?=$mtId?>" class="inptxt" style="width:90px;text-align:center" value="<?=$mKgs > 0 ? $mKgs : ''?>">
                                    </td>
                                    <td class="tdnrm" align="center">
                                        <input type="number" step="1" name="merma_mts_<?=$mtId?>" class="inptxt" style="width:90px;text-align:center" value="<?=$mMts > 0 ? $mMts : ''?>">
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
                                <button type="submit" class="btnorange" style="font-size:14px;padding:10px 24px;font-weight:bold" onclick="document.getElementById('prod_submode').value='end'">
                                    <i class="fa fa-fw fa-stop"></i> ⏹️ Finalizar y Cerrar Tiraje
                                </button>
                                <button type="submit" class="btngreen" style="font-size:14px;padding:10px 22px;margin-left:10px" onclick="document.getElementById('prod_submode').value='update'">
                                    <i class="fa fa-fw fa-save"></i> 💾 Guardar Avance sin Cerrar
                                </button>
                            <?php elseif ($refId > 0): ?>
                                <button type="submit" class="btngreen" style="font-size:14px;padding:10px 24px;font-weight:bold" onclick="document.getElementById('prod_submode').value='update'">
                                    <i class="fa fa-fw fa-save"></i> Guardar Cambios
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btnorange" style="font-size:14px;padding:10px 24px;font-weight:bold" onclick="document.getElementById('prod_submode').value='end'">
                                    <i class="fa fa-fw fa-check"></i> Registrar Producción
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    </table>
                </form>
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
                                <div class="btnorange" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=autocontrol&end_alis=1';" style="margin-left:5px;float:right;cursor:pointer;text-align:center;padding:8px 16px;font-weight:bold">
                                    <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar alistamiento / Ir a Autocontrol
                                </div>
                                <div class="btngrey" onclick="document.getElementById('ap_submode').value='update';document.getElementById('form_apertura_edit').submit();" style="margin-left:5px;float:right;cursor:pointer;text-align:center;padding:8px 14px">
                                    <i class="fa fa-fw fa-save" style="color:white;"></i> Actualizar datos
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
                        <i class="fa fa-fw fa-play" style="color:white;"></i> Iniciar alistamiento&nbsp;
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
        ?>
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver al listado de eventos
            </div>
        </div>

        <?php if ($isSeriPulpo): ?>
            <!-- SECCIÓN: Insumos Directos de Serigrafía Pulpo (Tintas, Bastidores, Bolsas Base) -->
            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:18px;margin-bottom:18px">
                <div style="font-size:16px;font-weight:bold;color:#1E3A8A;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-palette"></i> Carga de Insumos Directos &mdash; Serigrafía Pulpo (OT #<?=htmlspecialchars((string)$agenda['prd_number'])?>)
                </div>
                <div style="font-size:12.5px;color:#64748B;margin-top:4px">
                    En esta sección puede solicitar y registrar tintas serigráficas, bastidores con malla mesh, bolsas base/sustrato, solventes y aditivos para la OT.
                </div>
            </div>

            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px;margin-bottom:20px">
                <div style="font-size:16px;font-weight:bold;color:#3F4C9B;margin-bottom:15px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-fw fa-paper-plane"></i> Solicitar / Registrar Insumo para la OT
                </div>

                <form method="post" action="/production/work-orders/materials/assign">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">

                    <table border="0" width="100%" cellpadding="8" cellspacing="0">
                    <colgroup><col width="220"><col></colgroup>
                    <tr>
                        <td class="tdleft">Tipo de Material / Insumo *</td>
                        <td class="tdnrm">
                            <select name="material_type" class="inptxt" style="width:320px;font-weight:bold" required>
                                <option value="Tinta Serigráfica">Tinta Serigráfica</option>
                                <option value="Bastidor / Malla Mesh">Bastidor / Malla Mesh</option>
                                <option value="Bolsas Base / Sustrato para Impresión">Bolsas Base / Sustrato para Impresión</option>
                                <option value="Solvente / Retardador / Limpiador">Solvente / Retardador / Limpiador</option>
                                <option value="Aditivo / Emulsión">Aditivo / Emulsión</option>
                                <option value="Otro Insumo de Producción">Otro Insumo de Producción</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Cantidad Solicitada / Utilizada *</td>
                        <td class="tdnrm">
                            <input type="number" step="0.01" name="material_qty" class="inptxt" style="width:160px" placeholder="0.00" required>
                            <select name="material_unit" class="inptxt" style="width:130px;margin-left:8px">
                                <option value="kg">Kilos (kg)</option>
                                <option value="litros">Litros (L)</option>
                                <option value="unidades">Unidades / Bolsas</option>
                                <option value="bastidores">Bastidores</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Observaciones / Color / Detalles</td>
                        <td class="tdnrm">
                            <textarea name="comments" class="inptxt" style="width:100%;height:65px" placeholder="Ej: Tinta Color Blanco Frente, Bastidor 77T, Lote sustrato..."></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm">
                            <button type="submit" class="btnblue" style="font-size:14px;padding:9px 24px">
                                <i class="fa fa-fw fa-paper-plane"></i> Solicitar / Registrar Material en OT
                            </button>
                        </td>
                    </tr>
                    </table>
                </form>
            </div>
        <?php endif; ?>

        <!-- SECCIÓN DE BOBINAS DE MATERIA PRIMA (CONECTADA A BASE DE DATOS REAL unibag_unibag) -->
        <!-- PANEL DE CONTROL DE CUOTA Y ESPECIFICACIONES -->
            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:18px;margin-bottom:18px">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;border-bottom:1px solid #EEEEEE;padding-bottom:10px">
                    <div style="font-size:16px;font-weight:bold;color:#1E3A8A;display:flex;align-items:center;gap:8px">
                        <i class="fa fa-balance-scale"></i> Control de Cuota de Bobinas &mdash; OT #<?=htmlspecialchars((string)$agenda['prd_number'])?>
                    </div>
                    <div style="font-size:13px">
                        Estado Cupo: 
                        <?php if ($requiresSupervisor): ?>
                            <span style="background:#FEE2E2;color:#991B1B;padding:4px 10px;border-radius:4px;font-weight:bold">
                                <i class="fa fa-lock"></i> Cuota Excedida (Requiere Supervisor)
                            </span>
                        <?php else: ?>
                            <span style="background:#DCFCE7;color:#166534;padding:4px 10px;border-radius:4px;font-weight:bold">
                                <i class="fa fa-check-circle"></i> En Cupo Permitido (<?=count($assignedRolls)?> / <?=$maxAllowedRolls?> bobinas)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:12px;font-size:12.5px">
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:4px;padding:10px">
                        <div style="color:#64748B;font-size:11px;text-transform:uppercase;font-weight:bold">Bobinas Asignadas</div>
                        <div style="font-size:18px;font-weight:bold;color:#0F172A;margin-top:2px"><?=count($assignedRolls)?> <span style="font-size:13px;font-weight:normal;color:#64748B">/ <?=$maxAllowedRolls?> máx</span></div>
                    </div>
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:4px;padding:10px">
                        <div style="color:#64748B;font-size:11px;text-transform:uppercase;font-weight:bold">Materialidad Requerida</div>
                        <div style="font-size:15px;font-weight:bold;color:#0284C7;margin-top:4px">
                            <?=htmlspecialchars($normTargetMat ?: ($targetMaterial ?: 'Según ficha'))?>
                            <span style="font-size:11px;font-weight:normal;color:#64748B">
                                (<?=$normTargetMat === 'PLA' ? 'Bodega 100 MP PLA' : ($normTargetMat === 'PP' ? 'Bodega 200 MP PP' : 'Bodega MP')?>)
                            </span>
                        </div>
                    </div>
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:4px;padding:10px">
                        <div style="color:#64748B;font-size:11px;text-transform:uppercase;font-weight:bold">Color Requerido</div>
                        <div style="font-size:15px;font-weight:bold;color:#0F172A;margin-top:4px"><?=htmlspecialchars($targetColor ?: 'Según ficha')?></div>
                    </div>
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:4px;padding:10px">
                        <div style="color:#64748B;font-size:11px;text-transform:uppercase;font-weight:bold">Ancho / Gramaje</div>
                        <div style="font-size:15px;font-weight:bold;color:#0F172A;margin-top:4px"><?=$targetWidth > 0 ? ($targetWidth . ' cm') : '-'?> · <?=$targetGrammage > 0 ? ($targetGrammage . ' gr') : '-'?></div>
                    </div>
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:4px;padding:10px">
                        <div style="color:#64748B;font-size:11px;text-transform:uppercase;font-weight:bold">Kilos Planificados</div>
                        <div style="font-size:18px;font-weight:bold;color:#0F172A;margin-top:2px"><?=$plannedKg > 0 ? number_format($plannedKg, 1, ',', '.') : '-'?> <span style="font-size:13px;font-weight:normal;color:#64748B">kg</span></div>
                    </div>
                </div>

                <?php if ($requiresSupervisor): ?>
                    <div style="margin-top:14px;background:#FFFBEB;border:1.5px solid #FCD34D;border-radius:5px;padding:12px 16px;color:#92400E;display:flex;align-items:center;gap:12px">
                        <i class="fa fa-exclamation-triangle" style="font-size:22px;color:#D97706"></i>
                        <div>
                            <div style="font-weight:bold;font-size:13.5px">Límite de Cupo de Bobinas Alcanzado</div>
                            <div style="font-size:12.5px;margin-top:2px">
                                Se ha alcanzado el límite máximo de <b><?=$maxAllowedRolls?> bobinas</b> para esta orden de trabajo. Para solicitar bobinas adicionales, un supervisor debe ingresar su usuario y contraseña para autorizar la excepción.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- FORMULARIO DE SOLICITUD DE BOBINAS A BODEGA -->
            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px;margin-bottom:20px">
                <div style="font-size:16px;font-weight:bold;color:#3F4C9B;margin-bottom:12px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-fw fa-dolly"></i> <?= $isRebobinadora ? 'Solicitar Bobina para Rebobinadora' : 'Solicitar Bobinas a Bodega (100 MP PLA / 200 MP PP)' ?>
                </div>

                <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:12px 16px;margin-bottom:16px">
                    <div style="font-size:13px;font-weight:bold;color:#1E293B;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                        <i class="fa fa-warehouse" style="color:#0284C7"></i> Bodegas Autorizadas: <b>100 (MP PLA)</b> y <b>200 (MP PP)</b>
                        <?php if ($normTargetMat !== ''): ?>
                            &middot; <span style="background:#E0F2FE;color:#0369A1;padding:2px 8px;border-radius:4px;font-size:11.5px">Material OT: <b><?=$normTargetMat?></b></span>
                        <?php endif; ?>
                        <?php if ($targetColor !== ''): ?>
                            &middot; <span style="background:#FEF3C7;color:#92400E;padding:2px 8px;border-radius:4px;font-size:11.5px">Color OT: <b><?=htmlspecialchars($targetColor)?></b></span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:12px;color:#64748B;margin-top:3px">
                        <?= $isRebobinadora
                            ? 'Seleccione la bobina de materia prima correspondiente (filtrada según materialidad PLA/PP y color de la OT) para alimentar la rebobinadora. Puede alternar los filtros superiores si requiere revisar otras bobinas.'
                            : 'Puede seleccionar <b>una o varias bobinas</b> para solicitarlas a bodega. Al solicitarlas quedarán reservadas para esta OT y disponibles al pie de máquina. Luego, presione <b>"⚙️ Montar en Máquina"</b> en la tabla inferior al momento de colocarlas físicamente en el desbobinador.'
                        ?>
                    </div>
                </div>

                <!-- BARRA DE FILTROS INTELIGENTES PARA BOBINAS -->
                <div style="background:#F1F5F9;border:1px solid #CBD5E1;border-radius:6px;padding:12px 14px;margin-bottom:12px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px">
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;font-size:12.5px">
                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:bold;color:#0F172A;user-select:none">
                            <input type="checkbox" id="chk_filter_compatible" checked onchange="applyRollFilters()" style="width:17px;height:17px;cursor:pointer">
                            <span><i class="fa fa-star" style="color:#10B981"></i> Solo bobinas acordes a la OT (Color y Material)</span>
                        </label>

                        <div style="display:flex;align-items:center;gap:5px">
                            <span style="color:#475569;font-weight:600">Materialidad:</span>
                            <select id="sel_filter_material" class="inptxt" style="font-size:12px;padding:4px 8px;width:160px;font-weight:bold" onchange="applyRollFilters()">
                                <option value="all">Todas (PLA y PP)</option>
                                <option value="PLA" <?=$normTargetMat === 'PLA' ? 'selected' : ''?>>PLA (Bodega 100)</option>
                                <option value="PP" <?=$normTargetMat === 'PP' ? 'selected' : ''?>>PP / TNT (Bodega 200)</option>
                                <option value="OTRO">Otras</option>
                            </select>
                        </div>

                        <div style="display:flex;align-items:center;gap:5px">
                            <span style="color:#475569;font-weight:600">Color:</span>
                            <select id="sel_filter_color" class="inptxt" style="font-size:12px;padding:4px 8px;width:150px;font-weight:bold" onchange="applyRollFilters()">
                                <option value="all">Todos los Colores</option>
                                <?php if ($targetColorClean !== ''): ?>
                                    <option value="<?=htmlspecialchars($targetColorClean)?>" selected>Acorde OT (<?=htmlspecialchars($targetColor)?>)</option>
                                <?php endif; ?>
                                <option value="blanco">Blanco</option>
                                <option value="negro">Negro</option>
                                <option value="natural">Natural / Crudo / Beige</option>
                                <option value="azul">Azul</option>
                                <option value="rojo">Rojo</option>
                                <option value="verde">Verde</option>
                                <option value="amarillo">Amarillo</option>
                                <option value="naranjo">Naranjo</option>
                                <option value="gris">Gris</option>
                                <option value="transparente">Transparente</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:flex;align-items:center;gap:8px">
                        <input type="text" id="txt_filter_search" class="inptxt" style="font-size:12px;padding:5px 8px;width:210px" placeholder="🔍 Buscar código, SKU..." oninput="applyRollFilters()">
                        <span id="rolls_filter_counter" style="font-size:11.5px;color:#64748B;font-weight:bold;white-space:nowrap"></span>
                    </div>
                </div>

                <form method="post" action="/production/work-orders/materials/assign" id="form_request_rolls" onsubmit="return validateRollsRequestSubmit()">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                    <?php if ($requiresSupervisor): ?>
                        <input type="hidden" name="requires_supervisor" value="1">
                    <?php endif; ?>

                    <div style="border:1px solid #CBD5E1;border-radius:5px;max-height:360px;overflow-y:auto;margin-bottom:14px;background:#fff">
                        <table border="0" width="100%" cellpadding="6" cellspacing="0" style="font-size:12.5px" id="table_rolls_request">
                        <thead style="position:sticky;top:0;background:#F1F5F9;z-index:2;border-bottom:1px solid #CBD5E1">
                            <tr>
                                <th width="40" align="center" style="padding:8px">Sel.</th>
                                <th width="65" align="center" style="padding:8px">Cant.</th>
                                <th align="left" style="padding:8px">SKU / Bobina</th>
                                <th align="left" style="padding:8px">Descripción Técnica (ERP unibag_unibag)</th>
                                <th align="center" style="padding:8px">Color</th>
                                <th align="center" style="padding:8px">Ancho</th>
                                <th align="right" style="padding:8px">Peso Est.</th>
                                <th align="center" style="padding:8px">Stock Disp.</th>
                                <th align="center" style="padding:8px">Bodega ERP</th>
                                <th align="center" style="padding:8px">Compatibilidad</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $allDisplayRolls = array_merge($compatibleRolls, $otherRolls);
                        ?>
                        <?php if (empty($allDisplayRolls)): ?>
                            <tr><td colspan="10" align="center" style="padding:25px;color:#94A3B8">No hay bobinas disponibles en las bodegas del ERP unibag_unibag en este momento.</td></tr>
                        <?php else: ?>
                            <?php foreach ($allDisplayRolls as $r): ?>
                                <?php
                                $rMat = (string)($r['roll_material'] ?? 'OTRO');
                                $isComp = !empty($r['is_compatible']);
                                $matMatch = !empty($r['is_material_match']);
                                $colMatch = !empty($r['is_color_match']);
                                $rowColor = mb_strtolower(trim((string)($r['color'] ?? '')));
                                $rowSearch = mb_strtolower(($r['roll_code'] ?? '') . ' ' . ($r['sku_code'] ?? '') . ' ' . ($r['sku_desc'] ?? '') . ' ' . ($r['color'] ?? '') . ' ' . ($r['warehouse_name'] ?? '') . ' ' . $rMat);
                                $stockAvailable = (float)($r['stock_qty'] ?? 0);
                                $maxSelectable = max(1, (int)floor($stockAvailable));
                                ?>
                                <tr class="roll-row-item"
                                    data-compatible="<?=$isComp ? '1' : '0'?>"
                                    data-mat="<?=$rMat?>"
                                    data-mat-match="<?=$matMatch ? '1' : '0'?>"
                                    data-col-match="<?=$colMatch ? '1' : '0'?>"
                                    data-color="<?=htmlspecialchars($rowColor)?>"
                                    data-search="<?=htmlspecialchars($rowSearch)?>"
                                    style="<?=$isComp ? 'background:#F0FDF4;border-bottom:1px solid #DCFCE7' : 'border-bottom:1px solid #F1F5F9'?>">
                                    <td align="center">
                                        <input type="checkbox" name="roll_ids[]" value="<?=$r['id']?>" id="chk_roll_<?=$r['id']?>" class="chk-roll-item" style="width:16px;height:16px;cursor:pointer">
                                    </td>
                                    <td align="center">
                                        <input type="number" name="roll_qty[<?=$r['id']?>]" value="1" min="1" max="<?=$maxSelectable?>" style="width:48px;padding:2px 4px;font-size:11.5px;text-align:center" class="inptxt" title="Cantidad de bobinas a solicitar">
                                    </td>
                                    <td>
                                        <label for="chk_roll_<?=$r['id']?>" style="font-weight:bold;color:<?=$isComp ? '#047857' : '#334155'?>;cursor:pointer">
                                            <?=htmlspecialchars((string)($r['sku_code'] ?? $r['roll_code'] ?? 'S/C'))?>
                                        </label>
                                    </td>
                                    <td>
                                        <span style="font-size:12px;font-weight:600;color:#1E293B"><?=htmlspecialchars((string)($r['sku_desc'] ?? ''))?></span>
                                    </td>
                                    <td align="center">
                                        <span style="<?=$colMatch ? 'background:#DCFCE7;color:#166534;font-weight:bold' : 'background:#F1F5F9;color:#475569'?>;padding:2px 6px;border-radius:3px;font-size:11.5px">
                                            <?=htmlspecialchars((string)($r['color'] ?? '-'))?>
                                        </span>
                                    </td>
                                    <td align="center"><?=(float)($r['width_mm'] ?? 0) > 0 ? (number_format((float)$r['width_mm'] / 10, 1) . ' cm') : '-'?></td>
                                    <td align="right"><b style="color:#0F172A"><?=number_format((float)($r['weight_kg'] ?? $r['current_weight_kg'] ?? 0), 1, ',', '.')?> kg</b></td>
                                    <td align="center">
                                        <b style="color:#0284C7"><?=number_format($stockAvailable, 0, ',', '.')?></b>
                                        <span style="font-size:10.5px;color:#64748B"> disp.</span>
                                    </td>
                                    <td align="center">
                                        <span style="background:#F1F5F9;color:#334155;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:bold;border:1px solid #E2E8F0">
                                            <?=htmlspecialchars((string)($r['warehouse_name'] ?? 'Bodega'))?>
                                        </span>
                                    </td>
                                    <td align="center">
                                        <?php if ($isComp): ?>
                                            <span style="background:#10B981;color:white;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold"><i class="fa fa-star"></i> Acorde a OT</span>
                                        <?php elseif (!$matMatch): ?>
                                            <span style="background:#FEE2E2;color:#991B1B;padding:2px 6px;border-radius:4px;font-size:11px"><i class="fa fa-times"></i> Mat. <?=$rMat?> (OT: <?=$normTargetMat?:'Distinto'?>)</span>
                                        <?php elseif (!$colMatch): ?>
                                            <span style="background:#FEF3C7;color:#92400E;padding:2px 6px;border-radius:4px;font-size:11px"><i class="fa fa-exclamation-triangle"></i> Color <?=htmlspecialchars((string)$r['color'])?></span>
                                        <?php else: ?>
                                            <span style="color:#94A3B8;font-size:11px">No coincide</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="no_rolls_filter_msg" style="display:none">
                                <td colspan="10" align="center" style="padding:25px;color:#94A3B8">
                                    <i class="fa fa-filter" style="font-size:24px;margin-bottom:6px;display:block"></i>
                                    No hay bobinas que coincidan con los filtros seleccionados (Materialidad / Color). Desmarque <i>"Solo bobinas acordes a la OT"</i> o cambie los filtros para ver otras opciones.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                        </table>
                    </div>

                    <?php if ($requiresSupervisor): ?>
                        <table border="0" width="100%" cellpadding="6" cellspacing="0" style="margin-bottom:12px">
                        <colgroup><col width="200"><col></colgroup>
                        <tr>
                            <td class="tdleft" style="background:#FEF3C7;color:#92400E"><b>Usuario Supervisor *</b></td>
                            <td class="tdnrm" style="background:#FEF3C7">
                                <input type="text" name="sup_user" class="inptxt" style="width:220px" required autocomplete="off" placeholder="Login supervisor">
                                <span style="font-size:12px;color:#92400E;margin-left:8px">Autorización por exceso de cupo</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="tdleft" style="background:#FEF3C7;color:#92400E"><b>Contraseña Supervisor *</b></td>
                            <td class="tdnrm" style="background:#FEF3C7">
                                <input type="password" name="sup_pass" class="inptxt" style="width:220px" required placeholder="Contraseña">
                            </td>
                        </tr>
                        </table>
                    <?php endif; ?>

                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <colgroup><col width="200"><col></colgroup>
                    <tr>
                        <td class="tdleft">Observaciones de Solicitud</td>
                        <td class="tdnrm"><input type="text" name="comments" class="inptxt" style="width:100%" placeholder="Observaciones opcionales para la solicitud de bobinas..."></td>
                    </tr>
                    <tr>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm" style="padding-top:10px">
                            <button type="submit" class="btnblue" style="font-size:14px;padding:9px 24px;cursor:pointer">
                                <i class="fa fa-fw fa-dolly"></i> <?= $isRebobinadora ? '📥 Solicitar Bobina Seleccionada' : '📥 Solicitar Bobinas Seleccionadas' ?>
                            </button>
                        </td>
                    </tr>
                    </table>
                </form>

                <script>
                function validateRollsRequestSubmit() {
                    var chks = document.querySelectorAll('.chk-roll-item:checked');
                    if (chks.length === 0) {
                        alert('Debe marcar la casilla de al menos una bobina para solicitar.');
                        return false;
                    }
                    return true;
                }

                function applyRollFilters() {
                    var chkComp = document.getElementById('chk_filter_compatible');
                    var onlyComp = chkComp ? chkComp.checked : false;
                    var selMat = document.getElementById('sel_filter_material');
                    var matVal = selMat ? selMat.value : 'all';
                    var selCol = document.getElementById('sel_filter_color');
                    var colVal = selCol ? selCol.value.toLowerCase().trim() : 'all';
                    var txtSearch = document.getElementById('txt_filter_search');
                    var qVal = txtSearch ? txtSearch.value.toLowerCase().trim() : '';

                    var rows = document.querySelectorAll('.roll-row-item');
                    var visible = 0;
                    var total = rows.length;

                    rows.forEach(function(row) {
                        var isComp = row.getAttribute('data-compatible') === '1';
                        var rMat = row.getAttribute('data-mat') || '';
                        var colMatch = row.getAttribute('data-col-match') === '1';
                        var rCol = (row.getAttribute('data-color') || '').toLowerCase();
                        var searchTxt = (row.getAttribute('data-search') || '').toLowerCase();

                        var show = true;

                        if (onlyComp && !isComp) {
                            show = false;
                        }

                        if (show && matVal !== 'all') {
                            if (matVal === 'PLA' && rMat !== 'PLA') show = false;
                            else if (matVal === 'PP' && rMat !== 'PP') show = false;
                            else if (matVal === 'OTRO' && (rMat === 'PLA' || rMat === 'PP')) show = false;
                        }

                        if (show && colVal !== 'all') {
                            var targetClean = '<?=htmlspecialchars(addslashes($targetColorClean))?>';
                            if (colVal === targetClean) {
                                if (!colMatch) show = false;
                            } else {
                                if (colVal === 'natural' || colVal === 'crudo' || colVal === 'beige') {
                                    if (rCol !== 'natural' && rCol !== 'crudo' && rCol !== 'beige' && !searchTxt.includes('natural') && !searchTxt.includes('crudo') && !searchTxt.includes('beige')) {
                                        show = false;
                                    }
                                } else if (!rCol.includes(colVal) && !searchTxt.includes(colVal)) {
                                    show = false;
                                }
                            }
                        }

                        if (show && qVal !== '') {
                            if (!searchTxt.includes(qVal)) {
                                show = false;
                            }
                        }

                        row.style.display = show ? '' : 'none';
                        if (show) visible++;
                    });

                    var counter = document.getElementById('rolls_filter_counter');
                    if (counter) {
                        counter.textContent = 'Mostrando ' + visible + ' de ' + total + ' bobinas';
                    }

                    var noRows = document.getElementById('no_rolls_filter_msg');
                    if (noRows) {
                        noRows.style.display = (visible === 0) ? '' : 'none';
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', applyRollFilters);
                } else {
                    applyRollFilters();
                }
                </script>
            </div>

            <!-- ============================================================== -->
            <!-- TABLA DE TRAZABILIDAD: BOBINAS DE LA OT                        -->
            <!-- SEPARACIÓN: SOLICITADA / DISPONIBLE vs MONTADA EN MÁQUINA      -->
            <!-- ============================================================== -->
            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
                <div style="font-size:16px;font-weight:bold;color:#115452;margin-bottom:15px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-fw fa-link"></i> Bobinas Asignadas a esta OT &mdash; Trazabilidad, Montaje y Registro de Consumo
                </div>

                <?php if (empty($assignedRolls)): ?>
                    <div style="background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:4px;padding:25px;text-align:center;color:#64748B">
                        <i class="fa fa-boxes" style="font-size:28px;color:#94A3B8;margin-bottom:8px"></i><br>
                        Actualmente no hay bobinas solicitadas ni montadas en esta orden de trabajo.<br>
                        Seleccione una o varias bobinas en la tabla superior para solicitarlas a bodega.
                    </div>
                <?php else: ?>
                    <table border="0" width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #E2E8F0;border-radius:4px;overflow:hidden">
                    <thead>
                        <tr style="background:#F1F5F9;color:#334155;font-size:12.5px">
                            <th class="tdheader" align="left">Bobina / Código</th>
                            <th class="tdheader" align="left">Material / SKU</th>
                            <th class="tdheader" align="center">Color</th>
                            <th class="tdheader" align="center">Ancho</th>
                            <th class="tdheader" align="center">Peso Inicial</th>
                            <th class="tdheader" align="center" width="220">Estado de Bobina</th>
                            <th class="tdheader" align="center" width="340">Acción / Montaje / Consumo</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($assignedRolls as $ar): 
                        $rollState = (string)($ar['status'] ?? 'ASSIGNED');
                        $isClosed = in_array($rollState, ['CONSUMED', 'RETURNED'], true);
                        $isMounted = !empty($ar['is_mounted']);
                    ?>
                        <tr>
                            <td class="tdnrm">
                                <b><?=htmlspecialchars((string)($ar['roll_code'] ?? ('#' . $ar['id'])))?></b><br>
                                <span style="font-size:11px;color:#64748B">ID: #<?=$ar['id']?></span>
                            </td>
                            <td class="tdnrm">
                                <?=htmlspecialchars((string)($ar['sku_code'] ?? 'Sustrato'))?><br>
                                <span style="font-size:11px;color:#64748B"><?=htmlspecialchars((string)($ar['sku_desc'] ?? ''))?></span>
                            </td>
                            <td class="tdnrm" align="center">
                                <span style="background:#E2E8F0;color:#1E293B;padding:3px 8px;border-radius:3px;font-size:12px;font-weight:bold">
                                    <?=htmlspecialchars((string)($ar['color'] ?? '-'))?>
                                </span>
                            </td>
                            <td class="tdnrm" align="center">
                                <?=(float)($ar['width_mm'] ?? 0) > 0 ? (number_format((float)$ar['width_mm'] / 10, 1) . ' cm') : '-'?>
                            </td>
                            <td class="tdnrm" align="center">
                                <b style="color:#0F172A"><?=number_format((float)($ar['weight_kg'] ?? 0), 1, ',', '.')?> kg</b>
                            </td>
                            <td class="tdnrm" align="center">
                                <?php if ($isClosed): ?>
                                    <span style="background:#DCFCE7;color:#166534;padding:4px 9px;border-radius:4px;font-size:11.5px;font-weight:bold">
                                        <i class="fa fa-check"></i> CONSUMIDA
                                    </span>
                                <?php elseif ($isMounted): ?>
                                    <span style="background:#DBEAFE;color:#1E40AF;padding:4px 9px;border-radius:4px;font-size:11.5px;font-weight:bold">
                                        <i class="fa fa-cog fa-spin"></i> MONTADA EN MÁQUINA
                                    </span>
                                    <?php if (!empty($ar['mounted_at'])): ?>
                                        <div style="font-size:10.5px;color:#64748B;margin-top:2px"><?=htmlspecialchars($ar['mounted_at'])?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="background:#FEF3C7;color:#92400E;padding:4px 9px;border-radius:4px;font-size:11.5px;font-weight:bold">
                                        <i class="fa fa-box"></i> SOLICITADA (NO MONTADA)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm">
                                <?php if ($isClosed): ?>
                                    <span style="color:#059669;font-size:12px">
                                        <i class="fa fa-check-circle"></i> Bobina procesada y trazabilidad registrada.
                                    </span>
                                <?php elseif ($isMounted): ?>
                                    <!-- FORMULARIO DE CONSUMO CUANDO ESTÁ MONTADA -->
                                    <form method="post" action="/production/work-orders/materials/consume" style="margin:0">
                                        <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                        <input type="hidden" name="agid" value="<?=$agId?>">
                                        <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                        <input type="hidden" name="roll_id" value="<?=$ar['id']?>">

                                        <table border="0" cellpadding="2" cellspacing="0" width="100%">
                                        <tr>
                                            <td style="font-size:11.5px;color:#475569">Kg Consumidos:</td>
                                            <td>
                                                <input type="number" step="0.1" name="consumed_weight" class="inptxt" style="width:85px;padding:3px 6px" placeholder="0.0" required> kg
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:11.5px;color:#475569">Kg Retazo:</td>
                                            <td>
                                                <input type="number" step="0.1" name="scrap_weight" class="inptxt" style="width:85px;padding:3px 6px" placeholder="0.0"> kg
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:11.5px;color:#475569">Detalle:</td>
                                            <td>
                                                <input type="text" name="comments" class="inptxt" style="width:100%;padding:3px 6px" placeholder="Causa retazo / lote...">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>&nbsp;</td>
                                            <td style="padding-top:4px">
                                                <button type="submit" class="btngreen" style="font-size:12px;padding:4px 12px;cursor:pointer">
                                                    <i class="fa fa-save"></i> ⚖️ Registrar Consumo y Retazo
                                                </button>
                                            </td>
                                        </tr>
                                        </table>
                                    </form>
                                    <div style="margin-top:6px;text-align:right">
                                        <form method="post" action="/production/work-orders/materials/mount" style="display:inline">
                                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                            <input type="hidden" name="agid" value="<?=$agId?>">
                                            <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                            <input type="hidden" name="roll_id" value="<?=$ar['id']?>">
                                            <input type="hidden" name="action_type" value="unmount">
                                            <button type="submit" class="btngrey" style="font-size:11px;padding:3px 8px;cursor:pointer" title="Desmontar temporalmente del desbobinador">
                                                <i class="fa fa-pause"></i> Desmontar
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <!-- BOTÓN PARA MONTAR EN MÁQUINA O DEVOLVER A BODEGA -->
                                    <div style="display:flex;align-items:center;gap:8px">
                                        <form method="post" action="/production/work-orders/materials/mount" style="margin:0">
                                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                            <input type="hidden" name="agid" value="<?=$agId?>">
                                            <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                            <input type="hidden" name="roll_id" value="<?=$ar['id']?>">
                                            <input type="hidden" name="action_type" value="mount">
                                            <button type="submit" class="btngreen" style="font-size:12px;padding:6px 14px;cursor:pointer;font-weight:bold">
                                                <i class="fa fa-play-circle"></i> ⚙️ Montar en Máquina
                                            </button>
                                        </form>
                                        <form method="post" action="/production/work-orders/materials/mount" style="margin:0">
                                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                            <input type="hidden" name="agid" value="<?=$agId?>">
                                            <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                            <input type="hidden" name="roll_id" value="<?=$ar['id']?>">
                                            <input type="hidden" name="action_type" value="unassign">
                                            <button type="submit" class="btngrey" style="font-size:12px;padding:6px 10px;cursor:pointer" onclick="return confirm('¿Devolver esta bobina a bodega?')">
                                                <i class="fa fa-undo"></i> Devolver
                                            </button>
                                        </form>
                                    </div>
                                    <div style="font-size:11px;color:#64748B;margin-top:4px">Disponible al pie de máquina. Presione <b>Montar</b> para colocar en desbobinador.</div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    </table>
                <?php endif; ?>
            </div>
    </div>

    <?php elseif ($mode === 'consumo'): ?>
        <!-- SUBFORMULARIO: Consumo de Insumos (form.consumo.php) -->
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver al listado de eventos
            </div>
        </div>
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <div style="font-size:16px;font-weight:bold;color:#E1A500;margin-bottom:15px">
                <i class="fa fa-fw fa-box-open"></i> Registrar Consumo Rápido
            </div>
            <form method="post" action="/production/work-orders/events/production">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <table border="0" width="100%" cellpadding="8" cellspacing="0">
                <colgroup><col width="220"><col></colgroup>
                <tr>
                    <td class="tdleft">Cantidad Consumida (Kg/Mtr)</td>
                    <td class="tdnrm"><input type="number" step="0.1" name="prod_bobina_kg" class="inptxt" style="width:200px" required></td>
                </tr>
                <tr>
                    <td class="tdleft">Comentarios</td>
                    <td class="tdnrm"><textarea name="evt_comments" class="inptxt" style="width:100%;height:60px"></textarea></td>
                </tr>
                <tr>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">
                        <button type="submit" class="btnorange" style="font-size:14px;padding:8px 20px">
                            <i class="fa fa-fw fa-check"></i> Guardar Consumo
                        </button>
                    </td>
                </tr>
                </table>
            </form>
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
                </div>
                <div>
                    <?php if (!empty($_GET['first_cut'])): ?>
                        <a href="/rolls/<?= (int)$_GET['first_cut'] ?>/label?server_print=1" target="_blank" class="btnblue" style="text-decoration:none;font-weight:bold">
                            <i class="fa fa-print"></i> 🖨️ Mandar a Imprimir Etiquetas Bobinas Hijas (100×70mm)
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
                                    $cutsList[] = ['rollo_amt' => (float)($rv['rollo_amt'] ?? 1), 'rollo_dims' => (float)($rv['rollo_dims'] ?? 0)];
                                }
                            } elseif (!empty($reboValues['metro'])) {
                                foreach ($reboValues['metro'] as $rv) {
                                    $cutsList[] = ['rollo_amt' => (float)($rv['rollo_amt'] ?? 1), 'rollo_dims' => (float)($rv['rollo_dims'] ?? 0)];
                                }
                            } elseif (!empty($subproducts)) {
                                foreach ($subproducts as $sp) {
                                    $cutsList[] = ['rollo_amt' => (float)($sp['subprod_amount'] ?? 1), 'rollo_dims' => (float)($sp['subprod_ancho_cm'] ?? 0)];
                                }
                            }
                            if (empty($cutsList)) {
                                $cutsList[] = ['rollo_amt' => 1, 'rollo_dims' => (float)($itemsel['item_reg_width'] ?? 100)];
                            }

                            $x = 0;
                            $_TOTAL_WIDTH = 0.0;
                            $rollWidth = (float)($itemsel['item_reg_width'] ?? 100);
                            if ($rollWidth <= 0) $rollWidth = 100.0;

                            foreach ($cutsList as $reboval):
                                $cReqAmt = isset($_REQUEST["reboamt_{$x}"]) ? (int)$_REQUEST["reboamt_{$x}"] : (int)$reboval['rollo_amt'];
                                $cReqDims = isset($_REQUEST["rollo_dims_{$x}"]) ? (float)str_replace(',', '.', (string)$_REQUEST["rollo_dims_{$x}"]) : (float)$reboval['rollo_dims'];

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
                                <tr>
                                    <td class="tdnrm" style="border-left:1px solid #DDDDDD;">
                                        <input type="text" class="inptxt clsreboamt" style="width:100%;text-align:center"
                                        name="reboamt_<?=$x?>" value="<?=$cReqAmt?>"
                                        onkeyup="$('#idx_btn_term').hide(0);$('#idx_btn_save').show(0);recalcTotalWidth();">
                                    </td>
                                    <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="left">
                                        <b style="color:#007BFF">[CORTE]</b>
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
                                    <input type="button" class="btngreen" id="idx_btn_term" value="Finalizar Corte (Consumir Bobina)"
                                    style="<?=$widthMatches ? '' : 'display:none;'?>padding:8px 16px;border:0px;font-size:14px;font-weight:bold;cursor:pointer;border-radius:4px"
                                    onclick="if(confirm('¿Está seguro de finalizar el corte? Se consumirá la bobina del inventario (Bodega 3000), se darán de alta los rollos de subproductos cortados y se registrará en el informe oficial de consumo.')) { document.xform_itemcalc.rebofinalize.value='1'; document.xform_itemcalc.submit(); }">

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
                                    onclick="if(confirm('¿Está seguro de finalizar el rebobinado? Se consumirá la bobina de inventario y quedará registrada en el informe oficial de consumo.')) { document.xform_itemcalc.rebofinalize.value='1'; document.xform_itemcalc.submit(); }">
                                </td>
                            </tr>
                            </table>
                        <?php endif; ?>
                    </td>
                </tr>
                </table>
            </form>
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
                                <select name="parent_roll_id" class="inptxt" style="width:100%">
                                    <option value="">-- Sin bobina directa / Auto-detectar --</option>
                                    <?php foreach ($assignedRolls as $ar): ?>
                                        <option value="<?=$ar['id']?>" <?=!empty($ar['is_mounted']) ? 'selected' : ''?>>
                                            <?=htmlspecialchars($ar['roll_code'])?> (<?=number_format((float)$ar['weight_kg'], 1)?> Kg<?=!empty($ar['is_mounted']) ? ' - MONTADA' : ''?>)
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
                        <td class="tdleft">Metros Lineales:</td>
                        <td class="tdnrm">
                            <div style="display:flex;gap:8px;align-items:center">
                                <input type="number" step="1" min="1" name="meters" id="flexo-meters" class="inptxt" style="width:130px;font-weight:bold" value="<?=$suggestedMeters > 0 ? (int)$suggestedMeters : 1000?>" required>
                                <span style="font-size:13px;color:#64748B">metros</span>
                                <?php if ($suggestedMeters > 0): ?>
                                    <button type="button" class="btngrey" style="padding:4px 8px;font-size:11px" onclick="document.getElementById('flexo-meters').value = '<?=(int)$suggestedMeters?>'">
                                        Usar programado (<?=(int)$suggestedMeters?> m)
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Peso Balanza (Kg):</td>
                        <td class="tdnrm">
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                                <input type="number" step="0.01" min="0.1" name="weight_kg" id="flexo-weight-kg" class="inptxt" style="width:130px;font-weight:bold;font-size:15px;color:#0F172A" placeholder="0.00" required>
                                <span style="font-size:13px;color:#64748B">Kg</span>
                                <button type="button" id="btn-read-scale-flexo" class="btnorange" style="padding:6px 14px;font-size:12px;font-weight:bold;cursor:pointer" onclick="captureFlexoScaleWeight()">
                                    <i class="fa fa-balance-scale"></i> ⚖️ Capturar Balanza
                                </button>
                            </div>
                            <div id="scale-status-msg" style="font-size:11.5px;color:#64748B;margin-top:4px">
                                Puede ingresar el peso manualmente o presionar Capturar Balanza para lectura en vivo.
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
        async function captureFlexoScaleWeight() {
            var btn = document.getElementById('btn-read-scale-flexo');
            var input = document.getElementById('flexo-weight-kg');
            var statusMsg = document.getElementById('scale-status-msg');
            if (!input || !btn) return;
            var oldHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Leyendo...';
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
        </script>

    <?php elseif ($mode === 'terminarot'): ?>
        <!-- SUBFORMULARIO: Cierre de OT con Supervisor (fancy.embalaje.termot.php) -->
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Cancelar y Volver a la OT
            </div>
        </div>
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:25px;max-width:700px;margin:0 auto">
            <div style="font-size:18px;font-weight:bold;color:#D64141;margin-bottom:10px;text-align:center">
                <i class="fa fa-fw fa-lock"></i> Finalizar y Cerrar Orden de Trabajo
            </div>
            <div style="text-align:center;color:#666;margin-bottom:20px;font-size:13px">
                Se requiere autorización de Supervisor para cerrar la OT #<b><?=htmlspecialchars((string)$agenda['prd_number'])?></b>.
            </div>
            <?php if ($isSeriPulpo): ?>
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
                            <i class="fa fa-fw fa-check-circle"></i> Confirmar y Terminar OT
                        </button>
                    </td>
                </tr>
                </table>
            </form>
        </div>

    <?php endif; ?>

    <?php if ($mode === '' && !$showSupervisorApproval && ($hasApertura || $hasAlis || $isSeri || $isRebobinadora)): ?>
        <!-- ============================================================== -->
        <!-- BARRA DE BOTONES DE ACCIÓN (editxid.php líneas 4647 - 4780)    -->
        <!-- Visible en Alistamiento, en Producción y en Pulpo/Serigrafía    -->
        <!-- ============================================================== -->
        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px;margin-bottom:10px">
        <tr>
            <?php if (!$hasAlis && $hasApertura && !$isRebobinadora): ?>
                <td style="padding-right:4px">
                    <div class="btnorange" style="background:#E1A500;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=autocontrol&end_alis=1'">
                        <nobr><i class="fa fa-fw fa-stop" style="color:white;"></i> ⏹️ Terminar alistamiento / Autocontrol&nbsp;</nobr>
                    </div>
                </td>
            <?php endif; ?>
            <?php if ($hasAlis || $isSeri || $isRebobinadora): ?>
                <?php if ($isRebobinadora): ?>
                    <td style="padding-right:4px">
                        <div class="btngreen" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod'">
                            <nobr><i class="fa fa-fw fa-cogs" style="color:white;"></i> Rebobinado&nbsp;</nobr>
                        </div>
                    </td>
                <?php else: ?>
                    <td style="padding-right:4px">
                        <?php if ($openProdId > 0): ?>
                            <div class="btnorange" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=production&refid=<?=$openProdId?>&submode=end'">
                                <nobr><i class="fa fa-fw fa-stop" style="color:white;"></i> ⏹️ Finalizar producción&nbsp;</nobr>
                            </div>
                        <?php else: ?>
                            <div class="btngreen" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=production&action=start'">
                                <nobr><i class="fa fa-fw fa-play" style="color:white;"></i> ▶️ Iniciar producción&nbsp;</nobr>
                            </div>
                        <?php endif; ?>
                    </td>
                    <?php if ($isFlexo): ?>
                        <td style="padding-right:4px">
                            <div class="btnblue" style="background:#6366f1;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=semielaborado'">
                                <nobr><i class="fa fa-fw fa-tag" style="color:white;"></i> Bobina Impresa (SE)&nbsp;</nobr>
                            </div>
                        </td>
                    <?php endif; ?>
                    <?php if ($isSelladora): ?>
                        <td style="padding-right:4px">
                            <div class="btngrey" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=embalaje'">
                                <nobr><i class="fa fa-fw fa-box" style="color:white;"></i> Embalaje / Cajas&nbsp;</nobr>
                            </div>
                        </td>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <?php $isBarExpanded = ($hasAlis || $isSeri || $isRebobinadora); ?>
            <td style="<?=$isBarExpanded ? 'padding-right:4px;padding-left:4px' : 'padding-right:2px;width:25%'?>">
                <div class="btnblue" style="<?=$isBarExpanded ? '' : 'width:100%;box-sizing:border-box;background-color:#293865;padding:10px 0;text-align:center;display:block'?>" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=materiales'">
                    <nobr><i class="fa fa-fw <?= $isRebobinadora ? 'fa-dolly' : 'fa-cubes' ?>" style="color:white;"></i> <?= $isRebobinadora ? 'Solicitar bobina' : ($isSeriPulpo ? 'Solicitar / Cargar materiales' : ($isSeri ? 'Cargar Insumos / Tintas' : 'Utilizar materiales')) ?>&nbsp;</nobr>
                </div>
            </td>

            <?php if (!$isPrinter && $hasAlis && !$isRebobinadora): ?>
                <td style="padding-right:4px;padding-left:4px">
                    <div class="btnorange" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=apertura'">
                        <nobr><i class="fa fa-fw fa-wrench" style="color:white;"></i> Apertura de Medida&nbsp;</nobr>
                    </div>
                </td>
            <?php endif; ?>

            <td style="<?=$isBarExpanded ? 'padding-right:4px;padding-left:4px' : 'padding:0 2px;width:25%'?>">
                <div class="btnred" style="<?=$isBarExpanded ? '' : 'width:100%;box-sizing:border-box;background-color:#D64141;padding:10px 0;text-align:center;display:block'?>" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=mantencion'">
                    <nobr><i class="fa fa-fw fa-tools" style="color:white;"></i> Registrar mantención&nbsp;</nobr>
                </div>
            </td>

            <td style="<?=$isBarExpanded ? 'padding-right:4px;padding-left:4px' : 'padding:0 2px;width:25%'?>">
                <div class="btngrey" style="<?=$isBarExpanded ? '' : 'width:100%;box-sizing:border-box;background-color:#7F7F7F;padding:10px 0;text-align:center;display:block'?>" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=pause'">
                    <nobr><i class="fa fa-fw fa-coffee" style="color:white;"></i> Pausa&nbsp;</nobr>
                </div>
            </td>

            <?php if ($isSeri || $isSeriPulpo): ?>
                <!-- BOTÓN COLACIÓN ESPECÍFICO SERIGRAFÍA Y PULPO -->
                <td style="padding-right:4px;padding-left:4px">
                    <div class="btnblue" style="background-color:#0284C7;cursor:pointer" onclick="openLunchModal()" title="Registrar horario de colación">
                        <nobr><i class="fa fa-fw fa-coffee" style="color:white;"></i> Colación&nbsp;</nobr>
                    </div>
                </td>
                <!-- BOTÓN SIN PRODUCCIÓN ESPECÍFICO SERIGRAFÍA Y PULPO -->
                <td style="padding-right:4px;padding-left:4px">
                    <?php if ($activeStandby !== null): ?>
                        <div class="btnorange" style="background-color:#EA580C;cursor:pointer" onclick="openStandbyEndModal()" title="Finalizar estado sin producción">
                            <nobr><i class="fa fa-fw fa-play-circle" style="color:white;"></i> Finalizar Sin Prod.&nbsp;</nobr>
                        </div>
                    <?php else: ?>
                        <div class="btnorange" style="background-color:#D97706;cursor:pointer" onclick="openStandbyStartModal()" title="Informar máquina sin producción / espera de OT / mantención">
                            <nobr><i class="fa fa-fw fa-pause-circle" style="color:white;"></i> Sin Producción&nbsp;</nobr>
                        </div>
                    <?php endif; ?>
                </td>
            <?php endif; ?>

            <td style="<?=$isBarExpanded ? 'padding-left:4px' : 'padding-left:2px;width:25%'?>">
                <div class="btnorange" style="<?=$isBarExpanded ? '' : 'width:100%;box-sizing:border-box;background-color:#E1A500;padding:10px 0;text-align:center;display:block'?>" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=consumo'">
                    <nobr><i class="fa fa-fw fa-box-open" style="color:white;"></i> Consumo&nbsp;</nobr>
                </div>
            </td>
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
                    } elseif ((float)($ev['evt_amount'] ?? 0) > 0 || (float)($ev['evt_amount_pulpo_frente'] ?? 0) > 0 || (float)($ev['evt_amount_pulpo_dorso'] ?? 0) > 0) {
                        $amtStr = $fmt($ev['evt_amount'] ?? 0) . ' bols';
                        if ((float)($ev['evt_amount_pulpo_frente'] ?? 0) > 0 || (float)($ev['evt_amount_pulpo_dorso'] ?? 0) > 0) {
                            $amtStr .= '<br><span style="font-size:11px;color:#64748B;font-weight:normal">(F: ' . $fmt($ev['evt_amount_pulpo_frente'] ?? 0) . ', D: ' . $fmt($ev['evt_amount_pulpo_dorso'] ?? 0) . ')</span>';
                        }
                    }
                    ?>
                    <tr>
                        <td class="tdnrm"><b><?=$evttype?></b></td>
                        <td class="tdnrm"><?=date('d.m.Y H:i:s', $start)?></td>
                        <td class="tdnrm"><?=$end > 0 ? date('d.m.Y H:i:s', $end) : '<span style="color:#00A85A;font-weight:bold"><i class="fa fa-clock"></i> En curso</span>'?></td>
                        <td class="tdnrm" align="center"><?=$hours?>h <?=$mins?>m</td>
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
                            <?php if ($end === 0): ?>
                                <?php if ($ev['evt_type'] === 'apertura'): ?>
                                    <div class="btnorange" style="padding:4px 10px;font-size:12px;display:inline-block;cursor:pointer"
                                         onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=autocontrol&end_alis=1'">
                                        <i class="fa fa-stop"></i> Terminar Alistamiento / Autocontrol
                                    </div>
                                <?php else: ?>
                                    <div class="btnorange" style="padding:4px 10px;font-size:12px;display:inline-block;cursor:pointer"
                                         onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=<?=$ev['evt_type']?>&refid=<?=$ev['id']?>'">
                                        <i class="fa fa-stop"></i> Terminar
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($ev['evt_type'] !== 'apertura'): ?>
                                    <div class="btngrey" style="padding:4px 10px;font-size:12px;display:inline-block;cursor:pointer"
                                         onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=<?=$ev['evt_type']?>&refid=<?=$ev['id']?>'">
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
                    <input type="hidden" name="init_id" value="<?=(int)$activeInit['id']?>">
                    <input type="hidden" name="machine_id" value="<?=(int)$activeInit['win_equipoid']?>">
                    <input type="hidden" name="machine_name" value="<?=htmlspecialchars((string)$activeInit['equipo_name'])?>">
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
        <!-- DISEÑO ESPECÍFICO REBOBINADORA: OTs Programadas para esta Máquina -->
        <!-- ============================================================== -->
        <div style="font-size:20px;margin-bottom:10px"><b>Ordenes de trabajo</b></div>
        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
        <tr>
            <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
                <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
                <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                <b>Iniciar nueva OT</b> en máquina <b><?=htmlspecialchars((string)($activeInit['equipo_name'] ?? 'Rebobinadora'))?></b>
            </td>
        </tr>
        </table>

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
                            <form method="post" action="/production/work-orders/init" style="margin:0">
                                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                <input type="hidden" name="ag_id" value="<?=$so['ag_id']?>">
                                <button type="submit" class="btngreen" style="padding:6px 14px"><i class="fa fa-play"></i> Iniciar OT</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            </table>
        </div>
    <?php elseif ($isFlexo): ?>
        <!-- ============================================================== -->
        <!-- DISEÑO ESPECÍFICO FLEXOGRAFÍA: OTs Programadas para esta Máquina -->
        <!-- ============================================================== -->
        <div style="font-size:20px;margin-bottom:10px"><b>Ordenes de trabajo</b></div>
        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
        <tr>
            <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
                <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
                <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                <b>Iniciar nueva OT</b> en máquina <b><?=htmlspecialchars((string)($activeInit['equipo_name'] ?? 'Flexografía'))?></b>
            </td>
        </tr>
        </table>

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
                        <?php if (!empty($dateFilter)): ?>
                            <a href="/production/work-orders/new?plan_date=all" class="btngrey" style="padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Todas</a>
                        <?php else: ?>
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
                    <td class="tdheader" align="right">Cantidad Bolsas</td>
                    <td class="tdheader" align="center">Imagen</td>
                    <td class="tdheader" align="center">Opciones</td>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($scheduledOrders)): ?>
                <tr>
                    <td colspan="13" class="tdnrm" style="text-align:center;padding:35px 20px;color:#64748B">
                        <i class="fa fa-calendar-times" style="font-size:26px;color:#94A3B8;margin-bottom:8px;display:block"></i>
                        No hay órdenes de trabajo planificadas para esta máquina en la fecha <b><?=htmlspecialchars($dateFilter ?: date('d.m.Y'))?></b>.
                        <div style="margin-top:10px">
                            <a href="/production/work-orders/new?plan_date=all" class="btnblue" style="font-size:12px;text-decoration:none;padding:5px 14px;display:inline-block">
                                <i class="fa fa-list"></i> Ver todas las órdenes de la agenda
                            </a>
                        </div>
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
                            <form method="post" action="/production/work-orders/init" style="margin:0">
                                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                <input type="hidden" name="ag_id" value="<?=$so['ag_id']?>">
                                <button type="submit" class="btngreen" style="padding:6px 14px"><i class="fa fa-play"></i> Iniciar OT</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            </table>
        </div>
    <?php elseif ($isSeri): ?>
        <!-- ============================================================== -->
        <!-- DISEÑO ESPECÍFICO SERIGRAFÍA: Iniciar nueva OT                 -->
        <!-- ============================================================== -->
        <div style="font-size:20px;margin-bottom:10px"><b>Ordenes de trabajo</b></div>
        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
        <tr>
            <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
                <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
                <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                <b>Iniciar nueva OT</b> en máquina <b><?=htmlspecialchars((string)($activeInit['equipo_name'] ?? 'Serigrafía'))?></b>
            </td>
        </tr>
        </table>

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

                <!-- Filtro rápido por fecha -->
                <div style="display:flex;align-items:center;gap:8px">
                    <form method="get" action="/production/work-orders/new" style="margin:0;display:inline-flex;align-items:center;gap:6px">
                        <label style="font-size:12px;color:#64748B;font-weight:600"><i class="fa fa-calendar-alt"></i> Fecha:</label>
                        <input type="date" name="plan_date" value="<?= !empty($dateFilter) && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateFilter, $mD) ? "{$mD[3]}-{$mD[2]}-{$mD[1]}" : date('Y-m-d') ?>" style="padding:4px 8px;border:1px solid #CBD5E1;border-radius:4px;font-size:12px" onchange="this.form.submit()">
                        <?php if (!empty($dateFilter) && $dateFilter !== date('d.m.Y')): ?>
                            <a href="/production/work-orders/new" class="btn" style="background:#0284C7;color:#fff;padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Hoy</a>
                        <?php endif; ?>
                        <?php if (!empty($dateFilter)): ?>
                            <a href="/production/work-orders/new?plan_date=all" class="btngrey" style="padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Todas</a>
                        <?php else: ?>
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
                        <div style="margin-top:10px">
                            <a href="/production/work-orders/new?plan_date=all" class="btnblue" style="font-size:12px;text-decoration:none;padding:5px 14px;display:inline-block">
                                <i class="fa fa-list"></i> Ver todas las órdenes de la agenda
                            </a>
                        </div>
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
                            <form method="post" action="/production/work-orders/init" style="margin:0">
                                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                <input type="hidden" name="ag_id" value="<?=$so['ag_id']?>">
                                <button type="submit" class="btngreen" style="padding:6px 14px"><i class="fa fa-play"></i> Iniciar OT</button>
                            </form>
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
        <font style="font-size:20px"><b>Iniciar nueva OT</b></font>
        <div style="clear:both;height:10px"></div>
        <table border="0" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background-color:#FFFFFF;padding:10px;color:#666666;font-size:14px;border-radius:5px;padding:20px">
                <i class="fa fa-fw fa-laptop-code" style="font-size:14px;"></i> Ordenes de trabajo
                <i class="fa fa-fw fa-chevron-right" style="font-size:14px;"></i>
                Iniciar nueva OT
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
                <?php if (empty($scheduledOrders)): ?>
                    <b class="msg_save_err" style="color:#C00"><i class="fa fa-fw fa-exclamation-triangle"></i> No existen OT's planificadas.</b>
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
                        <td class="tdheader" align="center">Cantidad</td>
                        <td class="tdheader" align="center">Producido</td>
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
                            <form method="post" action="/production/work-orders/init" style="margin:0">
                                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                <input type="hidden" name="ag_id" value="<?=$so['ag_id']?>">
                                <div class="<?=$btnCls?>" style="cursor:pointer;display:inline-block" onclick="this.closest('form').submit();">
                                    <nobr><i class="fa fa-fw fa-play" style="color:white;"></i> Iniciar OT&nbsp;</nobr>
                                </div>
                            </form>
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
                <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
                <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                <b>En cursos</b> (OTs actualmente abiertas en máquina <b><?=htmlspecialchars((string)($activeInit['equipo_name'] ?? 'Rebobinadora'))?></b>)
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
                            <a href="/production/work-orders/operate?agid=<?=$row['ag_id']?>" class="btnblue" style="padding:6px 14px;text-decoration:none">
                                <i class="fa fa-desktop"></i> Operar
                            </a>
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
                <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
                <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                <b>En cursos</b> (OTs actualmente abiertas en máquina <b><?=htmlspecialchars((string)($activeInit['equipo_name'] ?? 'Flexografía'))?></b>)
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
                        <?php if (!empty($dateFilter)): ?>
                            <a href="/production/work-orders/active?plan_date=all" class="btngrey" style="padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Todas</a>
                        <?php else: ?>
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
                    <td class="tdheader" align="right">Cantidad Bolsas</td>
                    <td class="tdheader" align="center">Imagen</td>
                    <td class="tdheader" align="center">Opciones</td>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($activeList)): ?>
                <tr>
                    <td colspan="13" class="tdnrm" style="text-align:center;padding:35px 20px;color:#64748B">
                        <div style="font-size:16px;font-weight:bold;color:#334155;margin-bottom:6px">
                            <i class="fa fa-info-circle" style="color:#0284C7"></i> No hay órdenes en curso <?= !empty($dateFilter) ? 'planificadas para el ' . htmlspecialchars($dateFilter) : 'activas actualmente' ?>.
                        </div>
                        <?php if (!empty($dateFilter)): ?>
                            <div style="font-size:12.5px;color:#64748B;margin-top:6px">
                                Puedes revisar <a href="/production/work-orders/active?plan_date=all" style="color:#0284C7;font-weight:600;text-decoration:underline">todas las fechas</a> o iniciar una orden planificada para hoy en <a href="/production/work-orders/new" style="color:#059669;font-weight:600;text-decoration:underline">Iniciar nueva OT</a>.
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
                            <a href="/production/work-orders/operate?agid=<?=$row['ag_id']?>" class="btnblue" style="padding:6px 14px;text-decoration:none">
                                <i class="fa fa-desktop"></i> Operar
                            </a>
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
                <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
                <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                <b>En curso</b> en máquina <b><?=htmlspecialchars((string)($activeInit['equipo_name'] ?? 'Serigrafía'))?></b>
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
                        <?php if (!empty($dateFilter)): ?>
                            <a href="/production/work-orders/active?plan_date=all" class="btngrey" style="padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Todas</a>
                        <?php else: ?>
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
                        <div style="margin-top:10px">
                            <a href="/production/work-orders/active?plan_date=all" class="btnblue" style="font-size:12px;text-decoration:none;padding:5px 14px;display:inline-block">
                                <i class="fa fa-list"></i> Ver todas las órdenes de la agenda
                            </a>
                        </div>
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
                            <a href="/production/work-orders/operate?agid=<?=$row['ag_id']?>" class="btnblue" style="padding:6px 14px;text-decoration:none">
                                <i class="fa fa-desktop"></i> Operar
                            </a>
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
            <td style="background-color:#FFFFFF;padding:10px;color:#666666;font-size:14px;border-radius:5px;padding:20px">
                <i class="fa fa-fw fa-laptop-code" style="font-size:14px;"></i> Ordenes de trabajo
                <i class="fa fa-fw fa-chevron-right" style="font-size:14px;"></i>
                En cursos
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
                        <td class="tdheader" align="center">Cantidad</td>
                        <td class="tdheader" align="center">Producido</td>
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
                            <div class="btngreen" style="cursor:pointer;display:inline-block" onclick="location.href='/production/work-orders/operate?agid=<?=$row['ag_id']?>'">
                                <nobr><i class="fa fa-fw fa-edit" style="color:white;"></i> Ingresar a OT&nbsp;</nobr>
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
    string $operatorName,
    string $plantaName
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

    $savedMaterial = isset($_GET['saved_material']);
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

    <?php if ($error !== ''): ?>
        <div style="background:#FEE2E2;border:1.5px solid #EF4444;border-radius:6px;padding:12px 18px;margin-bottom:15px;color:#991B1B;font-size:13px;display:flex;align-items:center;gap:10px">
            <i class="fa fa-exclamation-triangle" style="font-size:20px;color:#DC2626"></i>
            <div><b>Error al cargar material:</b> <?=htmlspecialchars($error)?></div>
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
            <select class="inptxt" style="width:100%;max-width:550px;background:#FFF;font-size:13px;padding:8px" onchange="location.href='/production/materials/request?agid=' + this.value;">
                <option value="">&lt; Seleccione una OT en producción de esta máquina &gt;</option>
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
                    <?php if (!empty($fabricColor) || !empty($colores) || $agAmount > 0): ?>
                        <tr>
                            <td colspan="7" style="background:#FFFFFF;padding:8px 12px;border-bottom:1px solid #E2E8F0;font-size:12px">
                                <?php if (!empty($fabricColor)): ?>
                                    <span style="margin-right:15px"><b>Color Tela:</b> <span style="background:#FEF3C7;color:#92400E;padding:2px 6px;border-radius:3px"><?=htmlspecialchars($fabricColor)?></span></span>
                                <?php endif; ?>
                                <?php if (!empty($colores)): ?>
                                    <span style="margin-right:15px"><b>Colores Impresión:</b> <?=htmlspecialchars(implode(', ', $colores))?></span>
                                <?php endif; ?>
                                <?php if ($agAmount > 0): ?>
                                    <span><b>Cantidad Programada:</b> <b style="color:#0F766E"><?=number_format($agAmount, 0, ',', '.')?></b> bolsas</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
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
                        <i class="fa fa-info-circle" style="color:#059669"></i>
                        <b>Bobinas actualmente ubicadas en Bodega 3000 (Planta / Insumos en Producción).</b>
                        Estas bobinas ya fueron trasladadas a producción y pueden cargarse directamente a la máquina y operario con 1 clic.
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
                                No hay bobinas registradas actualmente en Bodega 3000.<br>
                                Puede cargar una bobina manual en la pestaña <b>"Cargar Sin Trazabilidad"</b> o solicitar bobinas desde la Bodega Central (100 / 200).
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bodega3000Rolls as $bRoll):
                            $rCode = (string)($bRoll['roll_code'] ?? $bRoll['sku_code'] ?? '-');
                            $rMat = (string)($bRoll['roll_material'] ?? 'TNT');
                            $rDesc = (string)($bRoll['sku_desc'] ?? '-');
                            $rCol = (string)($bRoll['color'] ?? '-');
                            $rW = (float)($bRoll['width_cm'] ?? round(($bRoll['width_mm'] ?? 0) / 10));
                            $rGramms = (int)($bRoll['microns'] ?? 80);
                            $rMts = (float)($bRoll['meters'] ?? 0);
                            $rKg = (float)($bRoll['weight_kg'] ?? $bRoll['current_weight_kg'] ?? 0);
                            $isCompat = !empty($bRoll['is_compatible']);
                            $rawId = (string)($bRoll['id'] ?? $bRoll['raw_id'] ?? '');
                        ?>
                            <tr style="<?=$isCompat ? 'background-color:#F0FDF4' : ''?>" onmouseover="this.style.backgroundColor='#E6F4EA'" onmouseout="this.style.backgroundColor='<?=$isCompat ? '#F0FDF4' : '#FFFFFF'?>'">
                                <td class="tdnrm"><b><?=htmlspecialchars($rCode)?></b></td>
                                <td class="tdnrm" align="center">
                                    <span style="background:#E2E8F0;color:#1E293B;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:bold"><?=htmlspecialchars($rMat)?></span>
                                </td>
                                <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($rDesc)?></td>
                                <td class="tdnrm"><?=htmlspecialchars($rCol)?></td>
                                <td class="tdnrm" align="center" style="white-space:nowrap">
                                    <?=$rW > 0 ? "{$rW} cm" : '-'?> &bull; <?=$rGramms > 0 ? "{$rGramms}g" : '-'?>
                                </td>
                                <td class="tdnrm" align="right"><?=$rMts > 0 ? number_format($rMts, 0, ',', '.') . ' m' : '-'?></td>
                                <td class="tdnrm" align="right"><b style="color:#0F766E"><?=number_format($rKg, 1, ',', '.')?> kg</b></td>
                                <td class="tdnrm" align="center">
                                    <?php if ($isCompat): ?>
                                        <span style="background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:bold">
                                            ⭐ COMPATIBLE
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#94A3B8;font-size:11px">Stock General</span>
                                    <?php endif; ?>
                                </td>
                                <td class="tdnrm" align="center">
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
                        Seleccione las bobinas que requiere transferir a la máquina (Bodega 3000) para su uso en esta OT.
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
                    <?php if (empty($centralRolls)): ?>
                        <tr><td colspan="8" class="tdnrm" style="text-align:center;padding:30px;color:#94A3B8">No se encontraron bobinas adicionales en las bodegas centrales para las características de esta OT.</td></tr>
                    <?php else: ?>
                        <?php foreach (array_slice($centralRolls, 0, 80) as $cRoll):
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

