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
        renderProductionMachinesScreen($machines, $activeInit, $currentOperatorName, $plantaName);
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
        $scheduledOrders = $prodService->listScheduledOrdersForMachine($equipoId, $plantaId);
        $activeOt = $prodService->getOpenWorkerOt($workerId, $plantaId);

        renderProductionNewWorkOrderScreen($activeInit, $scheduledOrders, $activeOt, $currentOperatorName, $plantaName);
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

    // Guardar producción (productionsave - editxid.php 1226-1570)
    if (($path === '/production/work-orders/events/production' || (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'productionsave')) && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        $amount = (float)str_replace(',', '.', (string)($_REQUEST['prod_amount'] ?? 0));
        $amount2 = (float)str_replace(',', '.', (string)($_REQUEST['prod_amount2'] ?? 0));
        $bobinaKg = (float)str_replace(',', '.', (string)($_REQUEST['prod_bobina_kg'] ?? 0));
        $mmaq = (int)($_REQUEST['evt_amount_metros_maquina'] ?? 0);
        $mlin = (int)($_REQUEST['evt_amount_metros_lineales'] ?? 0);
        $mtype = trim((string)($_REQUEST['evt_metrotype'] ?? ''));
        $comments = trim((string)($_REQUEST['evt_comments'] ?? ''));
        $submode = trim((string)($_REQUEST['submode'] ?? ''));
        $overrideEmbalaje = (int)($_REQUEST['overrideembalaje'] ?? 0);
        $eventId = (int)($_REQUEST['refid'] ?? 0);
        $isEnd = ($submode === 'end');

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
            $repairs
        );

        redirectResponse('/production/work-orders/operate?agid=' . $agId . '&saved_prod=1');
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

        if ($workerOtId <= 0) {
            $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
            if ($activeInit !== null && $agId > 0) {
                $startRes = $prodService->startWorkerOt($agId, (int)$activeInit['id']);
                if ($startRes['ok']) {
                    $workerOtId = (int)$startRes['worker_ot_id'];
                }
            }
        }

        if ($eventId <= 0 && $workerOtId > 0 && ($submode === 'end' || $submode === 'cerrar')) {
            $alisStatus = $prodService->getWorkOrderAlistamientoStatus($workerOtId);
            if (!empty($alisStatus['event']['id'])) {
                $eventId = (int)$alisStatus['event']['id'];
            }
        }

        if ($eventId > 0 && ($submode === 'end' || $submode === 'cerrar')) {
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

            if (!empty($selectedPoints)) {
                $prodService->sendAutocontrolToSupervisor($workerOtId, $selectedPoints, (int)($_SESSION['user_id'] ?? 0));
            }

            $prodService->recordEventApertura($workerOtId, $medFrom, $medTo, $comments, $eventId, true, $idAyudante);

            if ($submode === 'cerrar') {
                $now = time();
                $stmtCloseOt = Db::erpPdo()->prepare("UPDATE prod_worker_ot SET wok_enddat = :now, wok_status = 2 WHERE id = :id");
                $stmtCloseOt->execute([':now' => $now, ':id' => $workerOtId]);
                redirectResponse('/production/work-orders/new?closed=1');
                return true;
            }

            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&alis_finished=1');
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

        if ($eventId > 0 && $submode === 'end') {
            $prodService->endEvent($eventId, $workerOtId, 0, $comments);
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
            redirectResponse('/production/work-orders/active?closed=1');
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=terminarot&error=' . rawurlencode((string)($res['error'] ?? 'Error al cerrar OT.')));
        }
        return true;
    }

    // Asignación de materiales/bobina
    if ($path === '/production/work-orders/materials/assign' && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        $rollId = (int)($_REQUEST['roll_id'] ?? 0);
        $consumedWeight = (float)str_replace(',', '.', (string)($_REQUEST['consumed_weight'] ?? 0));
        $comments = trim((string)($_REQUEST['comments'] ?? ''));

        $res = $prodService->assignRollToWorkOrder($workerOtId, $rollId, $consumedWeight, $comments, $currentOperatorName);
        if ($res['ok']) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&saved_material=1');
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&error=' . rawurlencode((string)($res['error'] ?? 'Error al asignar material.')));
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

    if ($isOperateRoute && $method === 'GET') {
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
        $availableRolls = $prodService->listAvailableRollsForProduction(null, 60);

        $alisStatus = $workerOtId > 0
            ? $prodService->getWorkOrderAlistamientoStatus($workerOtId)
            : ['has_apertura' => false, 'has_alis' => false, 'event' => null];
        $autoctrlStatus = $workerOtId > 0
            ? $prodService->getWorkOrderAutocontrolStatus($workerOtId, $equipoTypeId)
            : ['has_points' => false, 'points' => [], 'goto_autocontrol' => false, 'worker_completed' => true, 'super_inputs' => 0, 'super_pending' => false, 'super_approved' => true, 'worker_responses' => [], 'super_responses' => []];
        $helpers = $prodService->listActiveHelpers();

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
        $activeList = $prodService->listActiveOrdersInCourse($plantaId);
        renderProductionActiveOrdersScreen($activeList, $currentOperatorName, $plantaName);
        return true;
    }

    if ($path === '/production/work-orders/history' && $method === 'GET') {
        $historyList = $prodService->listHistoryOrders($plantaId, 60);
        renderProductionHistoryScreen($historyList, $currentOperatorName, $plantaName);
        return true;
    }

    if ($path === '/production/traceability' && $method === 'GET') {
        $query = trim((string)($_GET['q'] ?? ''));
        $treeData = $query !== '' ? $prodService->getTraceabilityTree($query) : null;
        renderProductionTraceabilityScreen($query, $treeData);
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
        <div style="cursor:pointer;font-size:14px;padding-left:25px;color:<?=$mid === 3 ? '#FFFFFF' : '#B8D5D3'?>;background-color:<?=$mid === 3 ? '#115452' : 'transparent'?>;padding-top:10px;padding-bottom:10px"
             onclick="location.href='/production/work-orders/history';">
            <i class="fa fa-fw fa-circle" style="font-size:12px;color:<?=$mid === 3 ? '#00A85A' : '#7FA8A5'?>"></i> Historico
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

    $workerOtId = $workerOt !== null ? (int)$workerOt['id'] : 0;
    $agId = (int)$agenda['id'];
    $mode = trim((string)($_GET['mode'] ?? ''));
    $refId = (int)($_GET['refid'] ?? 0);
    $overrideEmbalaje = (int)($_GET['overrideembalaje'] ?? 0);

    $hasApertura = !empty($alisStatus['has_apertura']);
    $hasAlis = !empty($alisStatus['has_alis']);
    $superInputs = (int)($autoctrlStatus['super_inputs'] ?? 0);
    $superPending = !empty($autoctrlStatus['super_pending']);
    $gotoAutocontrol = ($hasAlis && !empty($autoctrlStatus['goto_autocontrol']));

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

    // Pantalla de Supervisor (Foto 3): Si se informó al supervisor y está pendiente de aprobación
    $showSupervisorApproval = ($superPending || ($superInputs > 0 && $gotoAutocontrol));

    // Pantalla de Autocontrol Operador (Foto 2): Cuando el operador hace clic en "Terminar" en alistamiento y aún no informa al supervisor
    $showOperatorAutocontrol = ($mode === 'autocontrol' && !$showSupervisorApproval);

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

    // Tipos de máquina
    $equipoTypeId = (int)($machineInit['equipo_type_id'] ?? 0);
    $isFlexo = (int)($machineInit['equipo_prod_isprinter_flexo'] ?? 0) === 1;
    $isSeri = (int)($machineInit['equipo_prod_isprinter_seri'] ?? 0) === 1;
    $isSeriPulpo = $isSeri && (stripos((string)($machineInit['equipo_name'] ?? ''), 'pulpo') !== false);
    $isSeriPlana = $isSeri && !$isSeriPulpo;
    $isPrinter = $isFlexo || $isSeri;
    $isSelladora = stripos((string)($machineInit['type_ant_title'] ?? ''), 'selladora') !== false;
    $isRebobinadora = stripos((string)($machineInit['type_ant_title'] ?? ''), 'rebobin') !== false;
    $isEmbalaje = (int)($machineInit['type_createstock_act'] ?? 0) === 1;

    // Helper format
    $fmt = function($val, $dec = 0) {
        if ($val === null || $val === '') return '-';
        return number_format((float)$val, $dec, ',', '.');
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
    if (stripos((string)($agenda['item_title'] ?? ''), 'BOUTIQUE') !== false) {
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
        if (!empty($agenda["fab_print_colors_front_{$xx}"]) || !empty($agenda["fab_print_colordesc_{$xx}"])) {
            $amtColorsFrente++;
        }
        if (!empty($agenda["fab_print_colors_back_{$xx}"])) {
            $amtColorsDorso++;
        }
    }
    $amtColorsSeri = max($amtColorsFrente, 1);

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
        ['sec_info_cliente', 'sec_info_fab', 'sec_info_anilox'].forEach(function(secId) {
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
                            <a href="/docs/<?=$agenda['fab_design_imagehash']?>" target="_blank" class="btngrey" style="padding:4px 10px;font-size:12px">
                                <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen
                            </a>
                        <?php else: ?>
                            <span style="color:#999">Sin imagen cargada</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php for ($x = 0; $x < 5; $x++): ?>
                    <?php if (!empty($agenda["req_prod_adjfile_{$x}"])): ?>
                        <tr>
                            <td class="tdleft" colspan="2">Imagen #<?=($x + 1)?></td>
                            <td class="tdnrm" colspan="2">
                                <a href="/docs/<?=$agenda["req_prod_adjfile_{$x}"]?>" target="_blank" class="btngrey" style="padding:4px 10px;font-size:12px">
                                    <i class="fa fa-fw fa-file" style="color:white;"></i> Ver adjunto
                                </a>
                                <span style="margin-left:10px;color:#666"><?=htmlspecialchars((string)($agenda["req_prod_adjcomments_{$x}"] ?? ''))?></span>
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
                    <?php if (!empty($agenda["fab_print_colordesc_{$xx}"])): ?>
                        <tr>
                            <td class="tdleft">Color N° <?=$xx?> (Frente)</td>
                            <td class="tdnrm"><?=htmlspecialchars((string)$agenda["fab_print_colordesc_{$xx}"])?></td>
                            <td class="tdleft">Color N° <?=$xx?> (Dorso)</td>
                            <td class="tdnrm"><?=htmlspecialchars((string)($agenda["fab_print_colordesc_{$xx}"] ?? ''))?></td>
                        </tr>
                    <?php endif; ?>
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
                            <a href="/docs/<?=$agenda['fab_design_imagehash']?>" target="_blank" class="btngrey" style="padding:4px 10px;font-size:12px">
                                <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen
                            </a>
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
                            <input type="hidden" name="req_id" value="<?=(int)$agenda['ag_reqid']?>">
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
        <!-- RAMA D2: SELLADORA / CONFECCIÓN / REBOBINADORA (2050 - 2330)   -->
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
                <tr>
                    <td class="tdleft">Materialidad</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                    <td class="tdleft">Fecha Entrega</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)($agenda['req_despacho_desc'] !== '' ? $agenda['req_despacho_desc'] : $agenda['ag_date']))?></b></td>
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
                        <tr>
                            <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="2" align="center">
                                <i class="fa fa-fw fa-wrench"></i> Configuración
                            </td>
                        </tr>
                        <tr><td class="tdleft">Producto</td><td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_title'])?></td></tr>
                        <?php if ($isSelladora): ?>
                            <tr><td class="tdleft">Corte de Bolsa</td><td class="tdnrm"><b><?=$fmt($corte_m2, 4)?> mtrs</b></td></tr>
                        <?php endif; ?>
                        <tr><td class="tdleft">Ancho</td><td class="tdnrm"><?=(int)$agenda['fab_med_width']?> cm</td></tr>
                        <tr><td class="tdleft">Alto (Tiro)</td><td class="tdnrm"><?=(int)$agenda['fab_med_height']?> cm</td></tr>
                        <tr><td class="tdleft">Alto (Retiro)</td><td class="tdnrm"><?=(int)$agenda['fab_med_height']?> cm</td></tr>
                        <tr><td class="tdleft">Doblez superior</td><td class="tdnrm">3 cm</td></tr>
                        <tr><td class="tdleft">Fuelle</td><td class="tdnrm"><?=(int)$agenda['fab_med_fuelle']?> cm</td></tr>
                        <tr><td class="tdleft">Dado de Manillas</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_infoaddprd_dado_manillas'] ?? 'Estándar'))?></td></tr>
                        <tr><td class="tdleft">Cabezal</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_infoaddprd_cabezal_act'] ?? 'No'))?></td></tr>
                        <tr><td class="tdleft">Alarma</td><td class="tdnrm"><?=stripos((string)($agenda['dispositivo'] ?? ''), 'ALARMA') !== false ? 'Sí' : 'No'?></td></tr>
                        <tr><td class="tdleft">Código</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['item_sellprice_barcodenumber'] ?? '-'))?></td></tr>
                        <tr><td class="tdleft">Etiqueta adhesiva</td><td class="tdnrm"><?=stripos((string)($agenda['dispositivo'] ?? ''), 'ETIQUETA') !== false ? 'Sí' : 'No'?></td></tr>
                        </table>
                    </td>
                    <td align="left" valign="top" width="50%" style="padding-left:10px">
                        <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD">
                        <colgroup><col width="160"><col></colgroup>
                        <tr>
                            <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="2" align="center">
                                <i class="fa fa-fw fa-cubes"></i> Insumos
                            </td>
                        </tr>
                        <tr><td class="tdleft" style="background-color:#D5EDEB" colspan="2" align="center">Características de tela</td></tr>
                        <tr><td class="tdleft">Materialidad</td><td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td></tr>
                        <tr><td class="tdleft">Color Tela</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['fabric_color'] ?? '-'))?></td></tr>
                        <tr><td class="tdleft">Ancho Tela</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_operador_tela_width'] ?? '-'))?> cm</td></tr>
                        <tr><td class="tdleft">Gramaje</td><td class="tdnrm"><?=$gramaje?> gr</td></tr>
                        <tr><td class="tdleft">Cant. Bobinas</td><td class="tdnrm"><b><?=$fmt($cantBobinas, 2)?></b></td></tr>

                        <tr><td class="tdleft" style="background-color:#D5EDEB" colspan="2" align="center">Características de manillas</td></tr>
                        <tr><td class="tdleft">Color Manilla</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['manilla_color'] ?? '-'))?></td></tr>
                        <tr><td class="tdleft">Ancho de Manillas</td><td class="tdnrm">2.5 cm</td></tr>
                        <tr><td class="tdleft">Largo de Manillas</td><td class="tdnrm"><?=(int)($agenda['fab_manilla_length'] ?? 40)?> cm</td></tr>
                        <tr><td class="tdleft">Cant. Manillas</td><td class="tdnrm"><b><?=$fmt($cantManillas, 2)?></b></td></tr>
                        </table>
                    </td>
                </tr>
                </table>
            </div>
        </div>

        <?php if ($isRebobinadora): ?>
            <div style="height:10px"></div>
            <table border="0" width="100%" cellpadding="6" cellspacing="0">
            <tr>
                <td colspan="4" style="background-color:#1AAAA4;color:#FFFFFF;font-weight:bold;text-align:center">
                    <i class="fa fa-fw fa-wrench"></i> Tarea Rebobinadora
                </td>
            </tr>
            <tr>
                <td class="tdleft">Tarea</td>
                <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_rebo_type'] ?? 'Corte y Rebobinado'))?></td>
                <td class="tdleft">Estado</td>
                <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_rebo_state'] ?? 'Pendiente'))?></td>
            </tr>
            <tr>
                <td class="tdleft">Cantidad de cortes</td>
                <td class="tdnrm"><?=$fmt($agenda['req_rebo_cortescc'])?></td>
                <td class="tdleft">Rollos Solicitados</td>
                <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_rebo_rolloscc'] ?? '-'))?></td>
            </tr>
            </table>
        <?php endif; ?>

    <?php endif; ?>

    </div>

    <!-- ============================================================== -->
    <!-- DESPACHO DE SUBFORMULARIOS SEGÚN MODE (editxid.php 4594-4609)  -->
    <!-- ============================================================== -->

    <?php if (!$hasApertura): ?>
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

    <?php elseif ($showSupervisorApproval): ?>
        <!-- ============================================================== -->
        <!-- FOTO 3: APROBACIÓN PARTIDA POR SUPERVISOR (autocontrol.form.php)-->
        <!-- ============================================================== -->
        <?php
        $pts = !empty($autoctrlStatus['points']) ? $autoctrlStatus['points'] : $autocontrolPoints;
        ?>
        <!-- Tarjeta 1: Información de la OT -->
        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px">
        <tr>
            <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="120">
                    <col width="40%">
                    <col width="120">
                    <col width="40%">
                </colgroup>
                <tr>
                    <td class="tdleft">Nº OT</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)$agenda['prd_number'])?></b></td>
                    <td class="tdleft">Nº CC</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)$agenda['req_number'])?></b></td>
                </tr>
                <tr>
                    <td class="tdleft">Cliente</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                    <td class="tdleft">Producto</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_number_prod'])?> | <?=htmlspecialchars((string)$agenda['item_title'])?></td>
                </tr>
                <tr>
                    <td class="tdleft">Medidas</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_width']?> x <?=(int)$agenda['fab_med_height']?></td>
                    <td class="tdleft">Fuelle</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_fuelle']?></td>
                </tr>
                <tr>
                    <td class="tdleft">Area</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_print_width']?> x <?=(int)$agenda['fab_print_height']?></td>
                    <td class="tdleft">Tela</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fabric_color'] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Manillas</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['manilla_color'] ?? '-'))?></td>
                    <td class="tdleft">Colores</td>
                    <td class="tdnrm"><?=htmlspecialchars($printcolors ?: '-')?></td>
                </tr>
                <tr>
                    <td class="tdleft">Cantidad</td>
                    <td class="tdnrm"><b><?=$fmt($agenda['ag_amount'])?></b></td>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">&nbsp;</td>
                </tr>
                </table>
            </td>
        </tr>
        </table>

        <!-- Tarjeta 2: AUTOCONTROL (Revisado por Operario) -->
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

        <!-- Tarjeta 3: APROBACIÓN PARTIDA (Supervisor) -->
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

    <?php elseif ($showOperatorAutocontrol): ?>
        <!-- ============================================================== -->
        <!-- FOTO 2: AUTOCONTROL OPERADOR (autocontrol.form.php)             -->
        <!-- ============================================================== -->
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
                        <td class="tdleft" colspan="4" style="background-color:#FFFF00;border:0px;border-radius:4px;color:black;font-weight:bold;font-size:14px;padding:8px" align="center">
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

        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px">
        <tr>
            <td width="25%" style="padding-right:2px">
                <div class="btnblue" style="width:100%;box-sizing:border-box;background-color:#293865;padding:10px 0;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=materiales'">
                    <i class="fa fa-fw fa-cubes"></i> Utilizar materiales
                </div>
            </td>
            <td width="25%" style="padding:0 2px">
                <div class="btnred" style="width:100%;box-sizing:border-box;background-color:#D64141;padding:10px 0;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=mantencion'">
                    <i class="fa fa-fw fa-wrench"></i> Registrar mantención
                </div>
            </td>
            <td width="25%" style="padding:0 2px">
                <div class="btngrey" style="width:100%;box-sizing:border-box;background-color:#7F7F7F;padding:10px 0;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=pause'">
                    <i class="fa fa-fw fa-coffee"></i> Pausa
                </div>
            </td>
            <td width="25%" style="padding-left:2px">
                <div class="btngrey" style="width:100%;box-sizing:border-box;background-color:#555555;padding:10px 0;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                    <i class="fa fa-fw fa-arrow-left"></i> Volver a la OT
                </div>
            </td>
        </tr>
        </table>

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
        $valAmount = $currentRefEvt !== null ? (float)$currentRefEvt['evt_amount'] : 0.0;
        $valAmount2 = $currentRefEvt !== null ? (float)$currentRefEvt['evt_amount2'] : 0.0;
        $valBobinaKg = $currentRefEvt !== null ? (float)$currentRefEvt['prod_bobina_kg'] : 0.0;
        $valMmaq = $currentRefEvt !== null ? (int)$currentRefEvt['evt_amount_metros_maquina'] : 0;
        $valMlin = $currentRefEvt !== null ? (int)$currentRefEvt['evt_amount_metros_lineales'] : 0;
        $valMtype = $currentRefEvt !== null ? (string)$currentRefEvt['evt_metrotype'] : ($isFlexo ? 'metros_lineales' : '');
        $valComments = $currentRefEvt !== null ? (string)$currentRefEvt['evt_comments'] : '';
        ?>
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver al listado de eventos
            </div>
        </div>
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <div style="font-size:16px;font-weight:bold;color:#115452;margin-bottom:15px">
                <i class="fa fa-fw fa-plus-circle"></i> <?=($refId > 0 ? ($isEvtOpen ? 'Modificar / Terminar Producción en Curso' : 'Detalle de Producción') : 'Iniciar Registro de Producción')?>
                <?=($overrideEmbalaje === 1 ? ' [Modo Embalaje]' : '')?>
            </div>
            <form method="post" action="/production/work-orders/events/production">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <input type="hidden" name="refid" value="<?=$refId?>">
                <input type="hidden" name="overrideembalaje" value="<?=$overrideEmbalaje?>">
                <input type="hidden" name="submode" id="prod_submode" value="">

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
                <?php if ($isPrinter): ?>
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
                <?php else: ?>
                    <tr>
                        <td class="tdleft">Contador Principal / Bolsas</td>
                        <td class="tdnrm"><input type="number" step="1" name="prod_amount" class="inptxt" style="width:200px" value="<?=$valAmount > 0 ? (int)$valAmount : ''?>" placeholder="0"> unidades</td>
                    </tr>
                    <tr>
                        <td class="tdleft">Contador Recibidor / Segunda</td>
                        <td class="tdnrm"><input type="number" step="1" name="prod_amount2" class="inptxt" style="width:200px" value="<?=$valAmount2 > 0 ? (int)$valAmount2 : ''?>" placeholder="0"> unidades</td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td class="tdleft">Kilos Bobina / Saldo Sobrante</td>
                    <td class="tdnrm"><input type="number" step="0.1" name="prod_bobina_kg" class="inptxt" style="width:200px" value="<?=$valBobinaKg > 0 ? $valBobinaKg : ''?>" placeholder="0.0"> kg</td>
                </tr>
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
                            <button type="submit" class="btngreen" style="font-size:14px;padding:9px 24px" onclick="document.getElementById('prod_submode').value='update'">
                                <i class="fa fa-fw fa-save"></i> Guardar Cambios
                            </button>
                            <button type="submit" class="btnorange" style="font-size:14px;padding:9px 24px;margin-left:10px" onclick="document.getElementById('prod_submode').value='end'">
                                <i class="fa fa-fw fa-check"></i> Terminar Producción
                            </button>
                        <?php elseif ($refId > 0): ?>
                            <button type="submit" class="btngreen" style="font-size:14px;padding:9px 24px" onclick="document.getElementById('prod_submode').value='update'">
                                <i class="fa fa-fw fa-save"></i> Guardar Cambios
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btngreen" style="font-size:14px;padding:9px 24px" onclick="document.getElementById('prod_submode').value='start'">
                                <i class="fa fa-fw fa-play"></i> Iniciar Producción
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                </table>
            </form>
        </div>

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
                    <td class="tdleft">Comentarios</td>
                    <td class="tdnrm"><textarea name="evt_comments" class="inptxt" style="width:100%;height:65px" placeholder="Detalles de alistamiento..."><?=htmlspecialchars($savedComments)?></textarea></td>
                </tr>
                <?php if ($refId > 0 && $isEvtOpen && !empty($autocontrolPoints)): ?>
                    <tr>
                        <td colspan="2" style="padding:15px 0 0 0">
                            <table border="0" width="100%" cellpadding="6" cellspacing="0" style="background-color:#FAFAFA;border:1px solid #DDDDDD;border-radius:4px">
                            <colgroup><col width="25"><col width="25%"><col width="25"><col width="25%"></colgroup>
                            <tr>
                                <td class="tdleft" colspan="4" style="background-color:#FFFF00;border:0px;border-radius:3px;color:#000;font-weight:bold;text-align:center">
                                    AUTOCONTROL (Puntos de Revisión Técnica Obligatoria por Máquina)
                                    <span style="float:left;font-weight:normal">
                                        <label style="cursor:pointer">
                                            <input type="checkbox" id="mark_all_worker_ap" onclick="var chks=document.querySelectorAll('.workerautochk_ap'); chks.forEach(c=>c.checked=this.checked);">
                                            Marcar todos
                                        </label>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                            <?php
                            $px = 0;
                            foreach ($autocontrolPoints as $p):
                                $acid = (int)$p['id'];
                            ?>
                                <td class="tdnrm">
                                    <input type="hidden" name="baseacids[]" value="<?=$acid?>">
                                    <input type="checkbox" value="1" name="acids_<?=$acid?>" class="workerautochk_ap">
                                    <span style="font-size:12px"><?=htmlspecialchars((string)$p['ac_name'])?></span>
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
                <?php endif; ?>
                <tr>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">
                        <?php if ($refId > 0 && $isEvtOpen): ?>
                            <button type="submit" class="btnorange" style="font-size:14px;padding:8px 20px" onclick="document.getElementById('ap_submode').value='end'">
                                <i class="fa fa-fw fa-stop"></i> Terminar e Ir a Autocontrol
                            </button>
                            <button type="submit" class="btngrey" style="font-size:14px;padding:8px 20px;margin-left:8px" onclick="document.getElementById('ap_submode').value='update'">
                                <i class="fa fa-fw fa-save"></i> Guardar Cambios
                            </button>
                            <button type="submit" class="btnred" style="font-size:14px;padding:8px 20px;margin-left:8px" onclick="document.getElementById('ap_submode').value='end'">
                                <i class="fa fa-fw fa-check"></i> Terminar Alistamiento
                            </button>
                        <?php elseif ($refId > 0): ?>
                            <button type="submit" class="btngreen" style="font-size:14px;padding:8px 20px" onclick="document.getElementById('ap_submode').value='update'">
                                <i class="fa fa-fw fa-save"></i> Guardar
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btngreen" style="font-size:14px;padding:8px 20px" onclick="document.getElementById('ap_submode').value='start'">
                                <i class="fa fa-fw fa-play"></i> Iniciar Alistamiento
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                </table>
            </form>

            <script>
            function validateAperturaEditSubmit() {
                var submode = document.getElementById('ap_submode').value;
                if (submode === 'end') {
                    var chks = document.querySelectorAll('.workerautochk_ap');
                    var allChecked = true;
                    chks.forEach(function(c) {
                        if (!c.checked) allChecked = false;
                    });
                    if (chks.length > 0 && !allChecked) {
                        alert('Debe seleccionar todos los puntos de autocontrol para que el alistamiento esté listo.');
                        return false;
                    }
                }
                return true;
            }
            </script>
        </div>

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
        <!-- SUBFORMULARIO: Pausa (form.pause.php) -->
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver al listado de eventos
            </div>
        </div>
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <div style="font-size:16px;font-weight:bold;color:#666666;margin-bottom:15px">
                <i class="fa fa-fw fa-coffee"></i> <?=($refId > 0 ? 'Terminar Pausa' : 'Registrar Pausa')?>
            </div>
            <form method="post" action="/production/work-orders/events/pause">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <input type="hidden" name="refid" value="<?=$refId?>">
                <input type="hidden" name="submode" id="pause_submode" value="">

                <table border="0" width="100%" cellpadding="8" cellspacing="0">
                <colgroup><col width="220"><col></colgroup>
                <tr>
                    <td class="tdleft">Motivo de Pausa</td>
                    <td class="tdnrm">
                        <select name="evt_pause_id" class="inptxt" style="width:250px" required>
                            <option value="">Seleccione motivo...</option>
                            <?php foreach ($pauses as $p): ?>
                                <option value="<?=$p['id']?>"><?=htmlspecialchars((string)$p['pause_name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft">Comentarios</td>
                    <td class="tdnrm"><textarea name="evt_comments" class="inptxt" style="width:100%;height:60px" placeholder="Observaciones..."></textarea></td>
                </tr>
                <tr>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">
                        <button type="submit" class="btngrey" style="font-size:14px;padding:8px 20px" onclick="document.getElementById('pause_submode').value='start'">
                            <i class="fa fa-fw fa-pause"></i> Iniciar Pausa
                        </button>
                        <?php if ($refId > 0): ?>
                            <button type="submit" class="btngreen" style="font-size:14px;padding:8px 20px;margin-left:10px" onclick="document.getElementById('pause_submode').value='end'">
                                <i class="fa fa-fw fa-play"></i> Terminar Pausa
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                </table>
            </form>
        </div>

    <?php elseif ($mode === 'materiales'): ?>
        <!-- SUBFORMULARIO: Materiales (form.materiales.php) -->
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver al listado de eventos
            </div>
        </div>
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <div style="font-size:16px;font-weight:bold;color:#3F4C9B;margin-bottom:15px">
                <i class="fa fa-fw fa-cubes"></i> Cargar Insumos y Utilizar Materiales
            </div>
            <form method="post" action="/production/work-orders/materials/assign">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">

                <table border="0" width="100%" cellpadding="8" cellspacing="0">
                <colgroup><col width="220"><col></colgroup>
                <tr>
                    <td class="tdleft">Seleccionar Bobina / Rollo</td>
                    <td class="tdnrm">
                        <select name="roll_id" class="inptxt" style="width:100%" required>
                            <option value="">Seleccione rollo disponible...</option>
                            <?php foreach ($availableRolls as $r): ?>
                                <option value="<?=$r['id']?>">
                                    [#<?=$r['id']?>] <?=htmlspecialchars((string)$r['roll_number'])?> · <?=htmlspecialchars((string)$r['sku_code'])?> · <?=number_format((float)$r['current_weight_kg'], 1, ',', '.')?> kg (<?=htmlspecialchars((string)$r['warehouse_name'])?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft">Kilos Consumidos</td>
                    <td class="tdnrm"><input type="number" step="0.1" name="consumed_weight" class="inptxt" style="width:200px" placeholder="0.0" required></td>
                </tr>
                <tr>
                    <td class="tdleft">Observaciones</td>
                    <td class="tdnrm"><textarea name="comments" class="inptxt" style="width:100%;height:60px"></textarea></td>
                </tr>
                <tr>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">
                        <button type="submit" class="btnblue" style="font-size:14px;padding:8px 20px">
                            <i class="fa fa-fw fa-save"></i> Asignar Insumo a OT
                        </button>
                    </td>
                </tr>
                </table>
            </form>
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
                <i class="fa fa-fw fa-arrow-left"></i> Volver al listado de eventos
            </div>
        </div>
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <div style="font-size:16px;font-weight:bold;color:#00A85A;margin-bottom:15px">
                <i class="fa fa-fw fa-cogs"></i> Registrar Producción Rebobinado
            </div>
            <form method="post" action="/production/work-orders/events/production">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <table border="0" width="100%" cellpadding="8" cellspacing="0">
                <colgroup><col width="220"><col></colgroup>
                <tr>
                    <td class="tdleft">Cortes Realizados</td>
                    <td class="tdnrm"><input type="number" step="1" name="prod_amount" class="inptxt" style="width:200px" required></td>
                </tr>
                <tr>
                    <td class="tdleft">Metros Rebobinados</td>
                    <td class="tdnrm"><input type="number" step="1" name="evt_amount_metros_lineales" class="inptxt" style="width:200px"></td>
                </tr>
                <tr>
                    <td class="tdleft">Comentarios</td>
                    <td class="tdnrm"><textarea name="evt_comments" class="inptxt" style="width:100%;height:60px"></textarea></td>
                </tr>
                <tr>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">
                        <button type="submit" class="btngreen" style="font-size:14px;padding:8px 20px">
                            <i class="fa fa-fw fa-check"></i> Guardar Rebobinado
                        </button>
                    </td>
                </tr>
                </table>
            </form>
        </div>

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

    <?php if ($mode === '' && !$showSupervisorApproval && ($hasApertura || $hasAlis)): ?>
        <!-- ============================================================== -->
        <!-- BARRA DE BOTONES DE ACCIÓN (editxid.php líneas 4647 - 4780)    -->
        <!-- Visible en Alistamiento (Foto 1) y en Producción               -->
        <!-- ============================================================== -->
        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px;margin-bottom:10px">
        <tr>
            <?php if ($hasAlis && !$gotoAutocontrol): ?>
                <?php if ($isSelladora): ?>
                    <td style="padding-right:4px">
                        <div class="btngreen" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=production&overrideembalaje=1'">
                            <nobr><i class="fa fa-fw fa-plus" style="color:white;"></i> Embalaje&nbsp;</nobr>
                        </div>
                    </td>
                <?php elseif ($isRebobinadora): ?>
                    <td style="padding-right:4px">
                        <div class="btngreen" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod'">
                            <nobr><i class="fa fa-fw fa-cogs" style="color:white;"></i> Rebobinado&nbsp;</nobr>
                        </div>
                    </td>
                <?php else: ?>
                    <td style="padding-right:4px">
                        <?php if ($openProdId > 0): ?>
                            <div class="btngreen" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=production&refid=<?=$openProdId?>'">
                                <nobr><i class="fa fa-fw fa-edit" style="color:white;"></i> Modificar / Terminar producción&nbsp;</nobr>
                            </div>
                        <?php else: ?>
                            <div class="btngreen" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=production'">
                                <nobr><i class="fa fa-fw fa-plus" style="color:white;"></i> Registrar producción&nbsp;</nobr>
                            </div>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            <?php endif; ?>

            <td style="<?=$hasAlis ? 'padding-right:4px;padding-left:4px' : 'padding-right:2px;width:25%'?>">
                <div class="btnblue" style="<?=$hasAlis ? '' : 'width:100%;box-sizing:border-box;background-color:#293865;padding:10px 0;text-align:center;display:block'?>" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=materiales'">
                    <nobr><i class="fa fa-fw fa-cubes" style="color:white;"></i> Utilizar materiales&nbsp;</nobr>
                </div>
            </td>

            <?php if (!$isPrinter && $hasAlis): ?>
                <td style="padding-right:4px;padding-left:4px">
                    <div class="btnorange" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=apertura'">
                        <nobr><i class="fa fa-fw fa-wrench" style="color:white;"></i> Apertura de Medida&nbsp;</nobr>
                    </div>
                </td>
            <?php endif; ?>

            <td style="<?=$hasAlis ? 'padding-right:4px;padding-left:4px' : 'padding:0 2px;width:25%'?>">
                <div class="btnred" style="<?=$hasAlis ? '' : 'width:100%;box-sizing:border-box;background-color:#D64141;padding:10px 0;text-align:center;display:block'?>" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=mantencion'">
                    <nobr><i class="fa fa-fw fa-tools" style="color:white;"></i> Registrar mantención&nbsp;</nobr>
                </div>
            </td>

            <td style="<?=$hasAlis ? 'padding-right:4px;padding-left:4px' : 'padding:0 2px;width:25%'?>">
                <div class="btngrey" style="<?=$hasAlis ? '' : 'width:100%;box-sizing:border-box;background-color:#7F7F7F;padding:10px 0;text-align:center;display:block'?>" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=pause'">
                    <nobr><i class="fa fa-fw fa-coffee" style="color:white;"></i> Pausa&nbsp;</nobr>
                </div>
            </td>

            <td style="<?=$hasAlis ? 'padding-left:4px' : 'padding-left:2px;width:25%'?>">
                <div class="btnorange" style="<?=$hasAlis ? '' : 'width:100%;box-sizing:border-box;background-color:#E1A500;padding:10px 0;text-align:center;display:block'?>" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=consumo'">
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
                        'mantencion' => 'Mantención',
                        'pause' => 'Pausa',
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
                    } elseif ((float)($ev['evt_amount'] ?? 0) > 0) {
                        $amtStr = $fmt($ev['evt_amount']) . ' bols';
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
                                         onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=autocontrol&refid=<?=$ev['id']?>'">
                                        <i class="fa fa-stop"></i> Terminar
                                    </div>
                                <?php else: ?>
                                    <div class="btnorange" style="padding:4px 10px;font-size:12px;display:inline-block;cursor:pointer"
                                         onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=<?=$ev['evt_type']?>&refid=<?=$ev['id']?>'">
                                        <i class="fa fa-stop"></i> Terminar
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="btngrey" style="padding:4px 10px;font-size:12px;display:inline-block;cursor:pointer"
                                     onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=<?=$ev['evt_type']?>&refid=<?=$ev['id']?>'">
                                    <i class="fa fa-edit"></i> Ver
                                </div>
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

    <?php
    $content = ob_get_clean();
    renderProdwrkShell('Operar OT #' . $agenda['prd_number'], $content, 2, $currentOperatorName, $plantaName);
}

// =============================================================================
// OTRAS PANTALLAS: ASIGNACIÓN, NUEVA OT, ACTIVAS, HISTÓRICO
// =============================================================================

function renderProductionMachinesScreen(array $machines, ?array $activeInit, string $operatorName, string $plantaName): void
{
    ob_start();
    ?>
    <div style="font-size:20px;margin-bottom:10px"><b>Asignación máquina</b></div>
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
                    <div style="display:flex;align-items:center;gap:10px">
                        <span style="background:#E8F0EF;color:#115452;padding:6px 12px;border-radius:4px;font-weight:bold">
                            <i class="fa fa-check-circle" style="color:#00A85A"></i> Máquina Activa: <?=htmlspecialchars((string)$activeInit['equipo_name'])?>
                        </span>
                        <a href="/production/work-orders/new" class="btngreen" style="padding:6px 12px"><i class="fa fa-play"></i> Ir a OTs</a>
                        <form method="post" action="/production/machines/end" style="margin:0">
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

function renderProductionNewWorkOrderScreen(
    array $activeInit,
    array $scheduledOrders,
    ?array $activeOt,
    string $operatorName,
    string $plantaName
): void {
    ob_start();
    ?>
    <div style="font-size:20px;margin-bottom:10px"><b>Ordenes de trabajo</b></div>
    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
    <tr>
        <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
            <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
            <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
            <b>Iniciar nueva OT</b> en máquina <b><?=htmlspecialchars((string)$activeInit['equipo_name'])?></b>
        </td>
    </tr>
    </table>

    <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
        <div style="font-size:15px;font-weight:bold;color:#115452;margin-bottom:12px">
            <i class="fa fa-fw fa-tasks"></i> OTs Programadas para esta Máquina
        </div>
        <table border="0" width="100%" cellpadding="8" cellspacing="0">
        <colgroup><col width="100"><col width="100"><col><col><col width="100"><col width="100"><col width="130"></colgroup>
        <thead>
            <tr>
                <td class="tdheader">N° OT</td>
                <td class="tdheader">N° CC</td>
                <td class="tdheader">Cliente</td>
                <td class="tdheader">Producto</td>
                <td class="tdheader" align="right">Programada</td>
                <td class="tdheader">Fecha Prog.</td>
                <td class="tdheader" align="center">Acción</td>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($scheduledOrders)): ?>
            <tr><td colspan="7" class="tdnrm" style="text-align:center;padding:25px;color:#999">No hay órdenes de trabajo programadas para esta máquina.</td></tr>
        <?php else: ?>
            <?php foreach ($scheduledOrders as $so): ?>
                <tr>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)$so['prd_number'])?></b></td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$so['req_number'])?></td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$so['cust_name'])?></td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$so['item_title'])?></td>
                    <td class="tdnrm" align="right"><b><?=number_format((float)$so['ag_amount'], 0, ',', '.')?></b></td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($so['ag_date'] ?? date('d/m/Y')))?></td>
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
    <?php
    $content = ob_get_clean();
    renderProdwrkShell('Iniciar nueva OT', $content, 1, $operatorName, $plantaName);
}

function renderProductionActiveOrdersScreen(array $activeList, string $operatorName, string $plantaName): void
{
    ob_start();
    ?>
    <div style="font-size:20px;margin-bottom:10px"><b>Ordenes de trabajo</b></div>
    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
    <tr>
        <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
            <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
            <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
            <b>En cursos</b> (OTs actualmente abiertas en planta)
        </td>
    </tr>
    </table>

    <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
        <table border="0" width="100%" cellpadding="8" cellspacing="0">
        <colgroup><col width="90"><col width="90"><col width="160"><col><col width="160"><col width="120"><col width="110"></colgroup>
        <thead>
            <tr>
                <td class="tdheader">N° OT</td>
                <td class="tdheader">N° CC</td>
                <td class="tdheader">Máquina</td>
                <td class="tdheader">Operario</td>
                <td class="tdheader">Cliente / Producto</td>
                <td class="tdheader">Inicio</td>
                <td class="tdheader" align="center">Acción</td>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($activeList)): ?>
            <tr><td colspan="7" class="tdnrm" style="text-align:center;padding:25px;color:#999">No hay órdenes en curso activas actualmente.</td></tr>
        <?php else: ?>
            <?php foreach ($activeList as $row): ?>
                <tr>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)$row['prd_number'])?></b></td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$row['req_number'])?></td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)$row['equipo_name'])?></b></td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$row['wrk_firstname'])?> <?=htmlspecialchars((string)$row['wrk_lastname'])?></td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$row['cust_name'])?> · <?=htmlspecialchars((string)$row['item_title'])?></td>
                    <td class="tdnrm"><?=date('d.m.Y H:i', (int)$row['wok_crtdat'])?></td>
                    <td class="tdnrm" align="center">
                        <a href="/production/work-orders/operate?agid=<?=$row['ag_id']?>" class="btnblue" style="padding:6px 12px;text-decoration:none">
                            <i class="fa fa-desktop"></i> Operar
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

function renderProductionTraceabilityScreen(string $query, ?array $tree): void
{
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
        render('Trazabilidad Integral', $body);
        return;
    }

    if ($tree === null || ($tree['roll'] === null && $tree['work_order'] === null && $tree['origin_purchase'] === null && $tree['purchase_order'] === null && $tree['boxes'] === [])) {
        $body .= '<div class="card" style="text-align:center;padding:40px 20px">';
        $body .= '<div style="font-size:40px;color:#ef4444;margin-bottom:12px"><i class="fa fa-exclamation-triangle"></i></div>';
        $body .= '<div style="font-size:18px;font-weight:800;color:#1e293b;margin-bottom:6px">No se encontraron registros para "' . htmlspecialchars($query) . '"</div>';
        $body .= '<div class="muted">Verifica que el identificador ingresado esté escrito correctamente o haya sido recepcionado en el sistema.</div>';
        $body .= '</div>';
        render('Trazabilidad Integral', $body);
        return;
    }

    render('Trazabilidad ' . htmlspecialchars($query), $body);
}
