<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Eventos de Producción, Alistamiento, Pausas y Consola Operativa
// (src/Http/Production/ProductionEventsModule.php)
// =============================================================================

function handleProductionEventsRoutes(
    string $path,
    string $method,
    ProductionService $prodService,
    int $workerId,
    int $plantaId,
    string $currentOperatorName
): bool {
    if (($path === '/production/work-orders/events/delete' || (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'eventdelete')) && ($method === 'GET' || $method === 'POST')) {
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $eventId = (int)($_REQUEST['event_id'] ?? $_REQUEST['refid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);

        if ($workerOtId <= 0 && $agId > 0) {
            $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
            if ($activeInit !== null) {
                $activeOt = $prodService->getOpenWorkerOtForInit((int)$activeInit['id'], $agId);
                if ($activeOt !== null) {
                    $workerOtId = (int)$activeOt['id'];
                }
            }
        }

        if ($workerOtId <= 0 && $eventId > 0) {
            try {
                $stEv = Db::erpPdo()->prepare("SELECT evt_prod_worker_otid FROM prod_worker_ot_events WHERE id = :id LIMIT 1");
                $stEv->execute([':id' => $eventId]);
                $workerOtId = (int)$stEv->fetchColumn();
            } catch (Throwable) {}
        }

        $supUser = trim((string)($_REQUEST['supervisor_user'] ?? $_REQUEST['pack_supervisor_user'] ?? ''));
        $supPass = trim((string)($_REQUEST['supervisor_pass'] ?? $_REQUEST['pack_supervisor_pass'] ?? ''));

        $authCheck = unibagCanDeleteProductionRecord($supUser, $supPass);
        if (!$authCheck['ok']) {
            $errMsg = $authCheck['error'] ?? 'Acceso denegado: únicamente Javier Perozo y Héctor Matus tienen autorización para eliminar registros de producción.';
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&error=' . rawurlencode($errMsg));
            return true;
        }

        requireCsrf();

        $authorizer = (string)($authCheck['authorizer'] ?? 'Javier / Héctor');
        $delRes = $prodService->deleteEvent($eventId, $workerOtId, $authorizer);

        if (!empty($delRes['ok'])) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&event_deleted=' . $eventId . '&authorizer=' . urlencode($authorizer));
        } else {
            $errMsg = (string)($delRes['error'] ?? 'No se pudo eliminar el registro de producción.');
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&error=' . rawurlencode($errMsg));
        }
        return true;
    }

    // Guardar parámetros específicos (ej: bastidor pulpo / serigrafía)
    if (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'setspecparams') {
        $agId = (int)($_REQUEST['agid'] ?? 0);
        if ($agId <= 0) {
            $activeOt = $prodService->getOpenWorkerOt($workerId, $plantaId);
            if ($activeOt !== null && (int)$activeOt['wok_ag_id'] > 0) {
                $agId = (int)$activeOt['wok_ag_id'];
            }
        }
        $bastidorAmt = (int)($_REQUEST['bastidoramt'] ?? 0);
        $reqId = (int)($_REQUEST['req_id'] ?? 0);
        if ($reqId <= 0 && $agId > 0) {
            $agenda = $prodService->getAgendaById($agId);
            $reqId = (int)($agenda['ag_reqid'] ?? $agenda['req_id'] ?? 0);
        }
        if ($reqId > 0 && $bastidorAmt > 0) {
            $erpPdo = Db::erpPdo();
            $stmt = $erpPdo->prepare("UPDATE orders SET req_operador_bastidoramt = :amt WHERE id = :id");
            $stmt->execute([':amt' => $bastidorAmt, ':id' => $reqId]);
        }

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_REQUEST['ajax']) && (int)$_REQUEST['ajax'] === 1)
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'bastidoramt' => $bastidorAmt,
                'req_id' => $reqId,
                'agid' => $agId,
                'message' => 'Imágenes en bastidor guardadas exitosamente.'
            ], JSON_UNESCAPED_UNICODE);
            return true;
        }

        // Fallback tradicional (no-AJAX): preservar exactamente la vista actual (ej: si estaba en finalizar orden mode=terminarot)
        $returnUrl = trim((string)($_REQUEST['return_to'] ?? ''));
        if ($returnUrl === '' && !empty($_SERVER['HTTP_REFERER'])) {
            $refPath = (string)parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
            if (str_contains($refPath, '/production/work-orders/operate')) {
                $returnUrl = $_SERVER['HTTP_REFERER'];
            }
        }
        if ($returnUrl === '') {
            $origMode = trim((string)($_REQUEST['orig_mode'] ?? ''));
            $returnUrl = '/production/work-orders/operate?agid=' . $agId . ($origMode !== '' ? '&mode=' . urlencode($origMode) : '') . '&saved_params=1';
        }
        redirectResponse($returnUrl);
        return true;
    }


    if (($path === '/production/work-orders/events/production' || (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'productionsave')) && $method === 'POST') {
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
        $actionType = trim((string)($_REQUEST['action_type'] ?? ''));
        $eventId = (int)($_REQUEST['refid'] ?? 0);
        $overrideEmbalaje = (int)($_REQUEST['overrideembalaje'] ?? 0);
        $comments = trim((string)($_REQUEST['evt_comments'] ?? ''));
        $operatingContext = $prodService->getWorkOrderOperatingContext($agId, $workerId, $plantaId);
        if ((int)($operatingContext['worker_ot']['id'] ?? 0) !== $workerOtId) {
            redirectResponse('/production/work-orders/active?error=' . rawurlencode('La OT no corresponde al operador conectado.'));
            return true;
        }
        $machine = $operatingContext['machine_init'] ?? [];
        if ((int)($machine['type_createstock_act'] ?? 0) === 1) $overrideEmbalaje = 1;
        $isCysPhase = (int)($machine['equipo_type_id'] ?? 0) === 8
            || stripos((string)($machine['type_ant_title'] ?? ''), 'selladora') !== false;
        $phaseActor = ($isCysPhase || $overrideEmbalaje === 1) ? [
            'worker_id' => $workerId,
            'name' => $currentOperatorName,
            'receiver_worker_id' => (int)($_POST['phase_receiver_worker_id'] ?? 0),
        ] : [];
        if (!empty($_POST['cys_packing_save'])) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&error='
                . rawurlencode('El cierre cambio: registra el embalaje por separado y vuelve a abrir Corte y Sellado.'));
            return true;
        }
        if ($eventId > 0) {
            $event = $prodService->getEventById($eventId, $workerOtId);
            if ($event === null || (int)$event['evt_prod_worker_otid'] !== $workerOtId) {
                redirectResponse('/production/work-orders/operate?agid=' . $agId . '&error=' . rawurlencode('El evento no corresponde a esta OT.'));
                return true;
            }
            $overrideEmbalaje = (int)($machine['type_createstock_act'] ?? 0) === 1
                ? 1 : (int)($event['overrideembalaje_act'] ?? 0);
        }

        // Caso 1: Inicio de Tiraje de Producción (arranque de máquina)
        if ($actionType === 'start' || $submode === 'start' || ($eventId <= 0 && $submode !== 'end' && empty($_REQUEST['prod_amount']))) {
            if ($overrideEmbalaje === 1) {
                foreach ($prodService->getWorkOrderEvents($workerOtId) as $activeEvent) {
                    if ((int)($activeEvent['overrideembalaje_act'] ?? 0) === 1
                        && (int)$activeEvent['evt_prod_worker_otid'] === $workerOtId
                        && (int)$activeEvent['evt_status'] === 1 && (int)($activeEvent['evt_enddat'] ?? 0) === 0
                        && (string)$activeEvent['evt_type'] === 'prod') {
                        redirectResponse('/production/work-orders/operate?agid=' . $agId);
                        return true;
                    }
                }
            }
            $alisCheck = $prodService->getWorkOrderAlistamientoStatus($workerOtId);
            $machineInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
            if ($machineInit === null && $agId > 0) {
                $ctx = $prodService->getWorkOrderOperatingContext($agId, $workerId, $plantaId);
                $machineInit = $ctx['machine_init'] ?? null;
            }
            $equipoTypeId = (int)($machineInit['equipo_type_id'] ?? 0);
            $isRebob = ($equipoTypeId === 12)
                || stripos((string)($machineInit['type_ant_title'] ?? ''), 'rebobin') !== false
                || stripos((string)($machineInit['equipo_name'] ?? ''), 'rebob') !== false;

            if ($overrideEmbalaje !== 1 && !$isRebob && empty($alisCheck['has_alis'])) {
                redirectResponse('/production/work-orders/operate?agid=' . $agId . '&error=' . rawurlencode('No puede iniciar producción sin haber terminado el alistamiento de la máquina.'));
                return true;
            }

            $mtype = trim((string)($_REQUEST['evt_metrotype'] ?? ''));
            $res = $prodService->recordEventProduction(
                $workerOtId,
                0.0,
                0.0,
                0.0,
                0,
                0,
                $mtype,
                $comments !== '' ? $comments : ($overrideEmbalaje === 1 ? 'Inicio de embalaje' : 'Inicio de produccion'),
                null,
                false,
                $overrideEmbalaje,
                [], [], 0.0, 0.0, [], $phaseActor
            );
            if (empty($res['ok'])) {
                redirectResponse('/production/work-orders/operate?agid=' . $agId
                    . '&mode=production&overrideembalaje=' . $overrideEmbalaje . '&action=start&error='
                    . rawurlencode((string)($res['error'] ?? 'No se pudo iniciar la produccion.')));
                return true;
            }
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
            $bastidorMultiplier = (int)($_REQUEST['seri_bastidor_multiplier'] ?? 0);
            $reqId = (int)($agenda['ag_reqid'] ?? $agenda['req_id'] ?? 0);
            if ($reqId > 0 && $bastidorMultiplier > 0 && $bastidorMultiplier !== (int)($agenda['req_operador_bastidoramt'] ?? 0)) {
                $erpPdo = Db::erpPdo();
                $stmt = $erpPdo->prepare("UPDATE orders SET req_operador_bastidoramt = :amt WHERE id = :id");
                $stmt->execute([':amt' => $bastidorMultiplier, ':id' => $reqId]);
            }
            $seriColorIndexes = array_keys($seriCounterValues);
            sort($seriColorIndexes);
            $seriPrevCounters = array_fill_keys($seriColorIndexes, 0.0);
            if ($workerOtId > 0) {
                $seriPrevEvents = $prodService->getWorkOrderEvents($workerOtId);
                foreach ($seriPrevEvents as $seriPrevEvent) {
                    if (!in_array((string)($seriPrevEvent['evt_type'] ?? ''), ['prod', 'prodsericolor'], true)) {
                        continue;
                    }
                    // Se omite el propio evento que se está editando ($eventId); antes usaba $refId, que no existe aquí.
                    if ($eventId > 0 && (int)($seriPrevEvent['id'] ?? 0) === $eventId) {
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

        $packagingDetail = null;
        $packagingPdo = null;
        try {
            if ($overrideEmbalaje === 1 && $isEnd && !empty($_REQUEST['close_without_production'])) {
                // Cierre de embalaje sin producción: no se registra detalle de cajas, no hay descuento de insumos ni correo.
                $noProdReason = trim((string)($_REQUEST['close_without_production_reason'] ?? ''));
                if (mb_strlen($noProdReason) < 5) {
                    throw new RuntimeException('Indica el motivo del cierre sin producción.');
                }
                $amount = 0.0;
                $amount2 = $bobinaKg = $amountFrente = $amountDorso = 0;
                $mmaq = $mlin = 0;
                $mtype = '';
                $mermas = $repairs = [];
                $comments = 'Cierre de embalaje sin producción. Motivo: ' . $noProdReason;
            } elseif ($overrideEmbalaje === 1) {
                $packagingDetail = ProductionService::normalizePackagingDetail($_REQUEST);
                $amount = (float)$packagingDetail['total_units'];
                $amount2 = $bobinaKg = $amountFrente = $amountDorso = 0;
                $mmaq = $mlin = 0;
                $mtype = '';
                $mermas = $repairs = [];
                $summary = 'Embalaje: ' . $packagingDetail['req_embalaje_cajas_completas_amt'] . ' cajas completas, '
                    . $packagingDetail['req_embalaje_caja_final'] . ' unidades en caja final, '
                    . $packagingDetail['req_embalaje_bolsas_sobrantes_amt'] . ' sobrantes y '
                    . $packagingDetail['req_embalaje_showroom'] . ' para showroom. Total: ' . $packagingDetail['total_units'] . ' unidades.';
                $comments = preg_replace('/^Embalaje: .*?Total: \\d+ unidades\\.\\s*(?:Observacion:\\s*)?/s', '', $comments) ?? $comments;
                $comments = $summary . ($comments !== '' ? ' Observacion: ' . $comments : '');
                $packagingPdo = Db::erpPdo();
                $packagingPdo->beginTransaction();
            }
        $productionResult = $prodService->recordEventProduction(
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
            $amountDorso,
            [],
            $phaseActor
        );
            if (empty($productionResult['ok'])) {
                throw new RuntimeException((string)($productionResult['error'] ?? 'No se pudo guardar la produccion.'));
            }
            if ($packagingDetail !== null) {
                $prodService->savePackagingEventDetail((int)$productionResult['event_id'], $workerOtId, $packagingDetail,
                    (string)($_REQUEST['pack_supervisor_user'] ?? ''), (string)($_REQUEST['pack_supervisor_pass'] ?? ''));
                $packagingPdo->commit();
                $prodService->sendPendingPackagingBillingMail();
            }
        } catch (Throwable $e) {
            if ($packagingPdo !== null && $packagingPdo->inTransaction()) $packagingPdo->rollBack();
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=production&refid=' . $eventId
                . '&overrideembalaje=' . $overrideEmbalaje . '&error=' . rawurlencode($e->getMessage()));
            return true;
        }

        $prodService->syncPulpoAgendaStats($agId);

        // Si el descuento automático de pallets/cajas/cinta falló, se avisa al operador.
        $supplyWarning = ($packagingDetail !== null && method_exists($prodService, 'getLastSupplyConsumptionWarning'))
            ? $prodService->getLastSupplyConsumptionWarning() : '';
        $supplyWarningQuery = $supplyWarning !== '' ? '&supply_warning=' . rawurlencode($supplyWarning) : '';

        if ($isEnd) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&prod_ended=1' . $supplyWarningQuery);
        } else {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&saved_prod=1' . $supplyWarningQuery);
        }
        return true;
    }


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

        try {
            $prodService->validateCysSetupMeasuresForAgenda($agId,$medFrom,$medTo);
        } catch (RuntimeException $e) {
            redirectResponse('/production/work-orders/operate?agid='.$agId.'&mode=apertura&error='.rawurlencode($e->getMessage()));
            return true;
        }

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
            $result = $prodService->recordEventApertura($workerOtId, $medFrom, $medTo, $comments, $eventId, true, $idAyudante);
            if (empty($result['ok'])) {
                redirectResponse('/production/work-orders/operate?agid='.$agId.'&mode=apertura&refid='.$eventId.'&error='.rawurlencode($result['error']??'No se pudo guardar el alistamiento.'));
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

            if (!empty($selectedPoints) && $workerOtId > 0) {
                $prodService->sendAutocontrolToSupervisor($workerOtId, $selectedPoints, (int)($_SESSION['user_id'] ?? 0));
            }

            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=autocontrol&alis_finished=1');
            return true;
        } elseif ($eventId > 0) {
            $result = $prodService->recordEventApertura($workerOtId, $medFrom, $medTo, $comments, $eventId, false, $idAyudante);
            if (empty($result['ok'])) {
                redirectResponse('/production/work-orders/operate?agid='.$agId.'&mode=apertura&refid='.$eventId.'&error='.rawurlencode($result['error']??'No se pudo guardar el alistamiento.'));
                return true;
            }
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&alis_updated=1');
        } else {
            $result = $prodService->recordEventApertura($workerOtId, $medFrom, $medTo, $comments, null, false, $idAyudante);
            if (empty($result['ok'])) {
                redirectResponse('/production/work-orders/operate?agid='.$agId.'&mode=apertura&error='.rawurlencode($result['error']??'No se pudo guardar el alistamiento.'));
                return true;
            }
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&alis_started=1');
        }
        return true;
    }

    // Guardar pausa (pausesave)
    if (($path === '/production/work-orders/events/pause' || (isset($_REQUEST['mode']) && $_REQUEST['mode'] === 'pausesave')) && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        if ($workerOtId <= 0 && $agId > 0) {
            $openOt = $prodService->getOpenWorkerOtByAgId($workerId, $plantaId, $agId);
            if ($openOt !== null) {
                $workerOtId = (int)$openOt['id'];
            }
        }
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
        $ubimId = (int)($_REQUEST['evt_ubim_id'] ?? 0);
        $comments = trim((string)($_REQUEST['evt_comments'] ?? ''));
        $submode = trim((string)($_REQUEST['submode'] ?? ''));
        $eventId = (int)($_REQUEST['refid'] ?? 0);

        if ($repairId <= 0) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=mantencion&error=' . rawurlencode('Selecciona el motivo de la mantención.'));
            return true;
        }
        if ($eventId > 0 && $submode === 'end') {
            $prodService->recordEventMantencion($workerOtId, $repairId, $ubimId, $comments, $eventId, true);
        } elseif ($eventId > 0) {
            $prodService->recordEventMantencion($workerOtId, $repairId, $ubimId, $comments, $eventId, false);
        } else {
            $prodService->recordEventMantencion($workerOtId, $repairId, $ubimId, $comments);
        }
        redirectResponse('/production/work-orders/operate?agid=' . $agId . '&saved_mant=1');
        return true;
    }


    return false;
}

function handleProductionOperateRoutes(
    string $path,
    string $method,
    ProductionService $prodService,
    ReceptionService $receptionService,
    string $currentOperatorName,
    int $workerId,
    int $plantaId,
    string $plantaName
): bool {
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
                $targetSlot = (int)($_POST['target_slot'] ?? $_GET['target_slot'] ?? 0);
                $curSlot1 = (int)($_REQUEST['selitemid1'] ?? 0);
                $curSlot2 = (int)($_REQUEST['selitemid2'] ?? 0);
                $taskParam = trim((string)($_REQUEST['task'] ?? ''));

                if ($targetSlot === 2 || ($targetSlot === 0 && $curSlot1 > 0 && $curSlot2 <= 0)) {
                    $redirectUrl = '/production/work-orders/operate?agid=' . $agId . '&mode=reboprod&selitemid1=' . $curSlot1 . '&selitemid2=' . $scannedId . '&roll_id_2=' . $rollId . '&scan_ok=1&scanned_code=' . urlencode($rCode);
                } else {
                    $redirectUrl = '/production/work-orders/operate?agid=' . $agId . '&mode=reboprod&selitemid1=' . $scannedId . ($curSlot2 > 0 ? '&selitemid2=' . $curSlot2 : '') . '&roll_id=' . $rollId . '&scan_ok=1&scanned_code=' . urlencode($rCode);
                }
                if ($taskParam !== '') {
                    $redirectUrl .= '&task=' . urlencode($taskParam);
                }
                $corteSubParam = trim((string)($_REQUEST['corte_sub'] ?? ''));
                if ($corteSubParam !== '') {
                    $redirectUrl .= '&corte_sub=' . urlencode($corteSubParam);
                }
                redirectResponse($redirectUrl);
                return true;
            } else {
                $errMsg = $scanRes['error'] ?? "No se encontró la bobina escaneada '{$scanReboInput}'.";
                $errUrl = '/production/work-orders/operate?agid=' . $agId . '&mode=reboprod&scan_err=' . urlencode($errMsg);
                if (!empty($_REQUEST['task'])) $errUrl .= '&task=' . urlencode((string)$_REQUEST['task']);
                if (!empty($_REQUEST['corte_sub'])) $errUrl .= '&corte_sub=' . urlencode((string)$_REQUEST['corte_sub']);
                redirectResponse($errUrl);
                return true;
            }
        }

        $scanMachineInput = !empty($_POST['scan_machine_code']) ? trim((string)$_POST['scan_machine_code']) : (!empty($_GET['scan_machine_code']) ? trim((string)$_GET['scan_machine_code']) : '');
        if ($scanMachineInput !== '') {
            $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
            $eqTypeId = (int)($activeInit['equipo_type_id'] ?? 0);
            $isSelladora = ($eqTypeId === 8
                || stripos((string)($activeInit['type_ant_title'] ?? ''), 'selladora') !== false
                || stripos((string)($activeInit['equipo_name'] ?? ''), 'selladora') !== false
                || stripos((string)($activeInit['type_ant_title'] ?? ''), 'confecc') !== false);
            $isHandleCode = (preg_match('/^ROLL-(MAN|HDL)-/i', $scanMachineInput) === 1 || !empty($_POST['is_handle_scan']));

            if ($isSelladora && $isHandleCode) {
                // Escaneo de manillas en Corte y Sellado
                $activeOt = $activeInit !== null ? $prodService->getOpenWorkerOtForInit((int)$activeInit['id'], $agId) : null;
                $workerOtId = $activeOt !== null ? (int)$activeOt['id'] : 0;
                if ($workerOtId <= 0 && $activeInit !== null) {
                    $startRes = $prodService->startWorkerOt($agId, (int)$activeInit['id']);
                    if (!empty($startRes['ok']) && !empty($startRes['worker_ot_id'])) {
                        $workerOtId = (int)$startRes['worker_ot_id'];
                    }
                }
                try {
                    $handles = new HandleConsumptionService(Db::erpPdo(), Db::trzPdo());
                    $scanned = $handles->scan($workerOtId, $workerId, $plantaId, $scanMachineInput);
                    $key = bin2hex(random_bytes(16));
                    $confirmations = (array)($_SESSION['cys_handle_confirmations'] ?? []);
                    $confirmations[$key] = [
                        'ot_id' => $workerOtId,
                        'code' => $scanMachineInput,
                        'worker_id' => $workerId,
                        'weight_kg' => (float)$scanned['weight_kg'],
                        'received_qty' => (float)$scanned['received_qty'],
                        'time' => time(),
                    ];
                    $_SESSION['cys_handle_confirmations'] = array_slice($confirmations, -50, null, true);
                    redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=materiales&handle_confirm=' . $key);
                    return true;
                } catch (Throwable $e) {
                    redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=materiales&handle_error=' . rawurlencode($e->getMessage()));
                    return true;
                }
            }

            // Escaneo de bobinas para Flexografía, Serigrafía o Corte y Sellado
            $activeOt = $activeInit !== null ? $prodService->getOpenWorkerOtForInit((int)$activeInit['id'], $agId) : null;
            $workerOtId = $activeOt !== null ? (int)$activeOt['id'] : 0;
            if ($workerOtId <= 0 && $activeInit !== null) {
                $startRes = $prodService->startWorkerOt($agId, (int)$activeInit['id']);
                if (!empty($startRes['ok']) && !empty($startRes['worker_ot_id'])) {
                    $workerOtId = (int)$startRes['worker_ot_id'];
                }
            }
            $equipoId = $activeInit !== null ? (int)($activeInit['win_equipoid'] ?? 0) : 0;
            $scanRes = $prodService->scanAndMountMachineRoll($agId, $workerOtId, $scanMachineInput, $currentOperatorName, $equipoId, $workerId, $plantaId);

            $returnMode = trim((string)($_POST['return_mode'] ?? $_GET['return_mode'] ?? ''));
            $redirectUrl = '/production/work-orders/operate?agid=' . $agId;
            if ($returnMode !== '') {
                $redirectUrl .= '&mode=' . urlencode($returnMode);
            }

            if (!empty($scanRes['ok'])) {
                $rCode = (string)($scanRes['roll_code'] ?? $scanMachineInput);
                $wKg = (float)($scanRes['weight_kg'] ?? 0);
                $msgParam = !empty($scanRes['already_mounted']) ? '&already_mounted=1' : '&scan_ok=1';
                redirectResponse($redirectUrl . $msgParam . '&scanned_code=' . urlencode($rCode) . '&m_kg=' . urlencode((string)$wKg));
                return true;
            } else {
                $errMsg = (string)($scanRes['error'] ?? "No se encontró ninguna bobina disponible con el código '{$scanMachineInput}'.");
                redirectResponse($redirectUrl . '&scan_err=' . urlencode($errMsg));
                return true;
            }
        }

        if (isset($_GET['removeslot'])) {
            $rem = (int)$_GET['removeslot'];
            $s1 = $rem === 1 ? 0 : (int)($_GET['selitemid1'] ?? 0);
            $s2 = $rem === 2 ? 0 : (int)($_GET['selitemid2'] ?? 0);
            $taskParam = trim((string)($_GET['task'] ?? ''));
            $corteSubParam = trim((string)($_GET['corte_sub'] ?? ''));
            $url = '/production/work-orders/operate?agid=' . $agId . '&mode=reboprod';
            if ($s1 > 0) $url .= '&selitemid1=' . $s1;
            if ($s2 > 0) $url .= '&selitemid2=' . $s2;
            if ($taskParam !== '') $url .= '&task=' . urlencode($taskParam);
            if ($corteSubParam !== '') $url .= '&corte_sub=' . urlencode($corteSubParam);
            redirectResponse($url);
            return true;
        }

        if ($method === 'POST') {
            if (!empty($_POST['rebofinalize'])) {
                $openOt = $prodService->getOpenWorkerOt($workerId, $plantaId);
                $workerOtId = $openOt !== null ? (int)$openOt['id'] : 0;
                $resRebo = $prodService->executeReboFinalize($agId, $_POST, (int)($_SESSION['user_id'] ?? 4075), (int)($_SESSION['company_id'] ?? 20010), $workerOtId, $currentOperatorName);
                if (empty($resRebo['ok'])) {
                    $errMsg = $resRebo['error'] ?? 'Error al procesar finalización de rebobinado.';
                    redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=reboprod&scan_err=' . urlencode($errMsg));
                    return true;
                }
                $isEmpalme = !empty($resRebo['is_empalme']) ? 1 : 0;
                $isRebobinado = (!empty($resRebo['rebo_type']) && $resRebo['rebo_type'] === 'Rebobinado') ? 1 : 0;
                $cutCount = !empty($resRebo['created_rolls']) ? count($resRebo['created_rolls']) : (!empty($resRebo['created_roll']) ? 1 : 0);
                $firstBagRollId = !empty($resRebo['created_bag_rolls'][0]['id'])
                    ? (int)$resRebo['created_bag_rolls'][0]['id']
                    : (!empty($resRebo['created_rolls'][0]['id']) ? (int)$resRebo['created_rolls'][0]['id'] : (!empty($resRebo['created_roll']['id']) ? (int)$resRebo['created_roll']['id'] : 0));
                $palletId = !empty($resRebo['handle_pallet']['id']) ? (int)$resRebo['handle_pallet']['id'] : 0;
                $handleCount = !empty($resRebo['created_handle_rolls']) ? count($resRebo['created_handle_rolls']) : 0;
                $mMermaAmt = (int)($resRebo['manillas_merma_amt'] ?? 0);
                $mMermaKg = (float)($resRebo['manillas_merma_kg'] ?? 0);
                $cutRollIds = array_column($resRebo['created_rolls'] ?? [], 'id');
                $cutIdsParam = !empty($cutRollIds) ? implode(',', $cutRollIds) : ($firstBagRollId > 0 ? (string)$firstBagRollId : '');
                redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=reboprod&rebo_done=1&is_empalme=' . $isEmpalme . '&is_rebobinado=' . $isRebobinado . '&cuts=' . $cutCount . '&first_cut=' . $firstBagRollId . '&cut_ids=' . urlencode($cutIdsParam) . '&pallet_id=' . $palletId . '&handle_rolls=' . $handleCount . '&merma_manillas=' . $mMermaAmt . '&merma_manillas_kg=' . $mMermaKg);
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
        $ctx['active_lunch'] = $receptionService->getActiveOperatorLunchBreak(
            $workerId, (int)($ctx['machine_init']['id'] ?? 0), $currentOperatorName
        );
        $activeInitForInk = (isset($activeInit) && is_array($activeInit)) ? $activeInit : [];
        $isFlexoContext = (int)($activeInitForInk['equipo_prod_isprinter_flexo'] ?? ($ctx['agenda']['equipo_prod_isprinter_flexo'] ?? 0)) === 1;
        $eqTypeIdForInk = (int)($activeInitForInk['equipo_type_id'] ?? ($ctx['agenda']['equipo_type_id'] ?? 0));
        $eqNameForInk = (string)($activeInitForInk['equipo_name'] ?? ($ctx['agenda']['equipo_name'] ?? ''));
        $isSeriContext = (int)($activeInitForInk['equipo_prod_isprinter_seri'] ?? 0) === 1
            || ($eqTypeIdForInk === 11)
            || ($eqTypeIdForInk === 22)
            || (int)($activeInitForInk['win_equipoid'] ?? 0) === 36
            || stripos($eqNameForInk, 'seri') !== false
            || stripos($eqNameForInk, 'pulpo') !== false;

        $rawBodega3000 = $receptionService->listWarehouseItemsByErpStorehouseId(16, false, 'TIN');
        usort($rawBodega3000, function ($a, $b) {
            $stockA = (float)($a['available_qty'] ?? 0);
            $stockB = (float)($b['available_qty'] ?? 0);
            if ($stockA > 0 && $stockB <= 0) return -1;
            if ($stockA <= 0 && $stockB > 0) return 1;
            return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });
        $ctx['bodega3000_ink_items'] = $rawBodega3000;

        if ($isFlexoContext) {
            $inkWarehouse = productionResolveInkWarehouseForAgenda($activeInitForInk, $ctx['agenda'] ?? null);
            $ctx['ink_warehouse'] = $inkWarehouse;
            $ctx['ink_items'] = $receptionService->listWarehouseItemsByErpStorehouseId(
                (int)($inkWarehouse['erp_storehouse_id'] ?? 16),
                true,
                (string)($inkWarehouse['item_prefix'] ?? 'TIN')
            );
        } elseif ($isSeriContext) {
            $ctx['ink_warehouse'] = [
                'id' => 16,
                'erp_storehouse_id' => 16,
                'storehouse_title' => '3000 INSUMOS EN PRODUCCION',
                'name' => '3000 Insumos en Producción',
                'item_prefix' => 'TIN',
            ];
            $ctx['ink_items'] = $rawBodega3000;
        } else {
            $ctx['ink_warehouse'] = [];
            $ctx['ink_items'] = [];
        }
        // Motivos y ubicaciones de mantención (equipo_manttype / equipos_ubimants del ERP).
        $ctx['maintenance_types'] = $prodService->listMaintenanceTypes();
        $ctx['maintenance_locations'] = $prodService->listMaintenanceLocations((int)($ctx['machine_init']['equipo_type_id'] ?? 0));
        $ctx['machine_ink_loads'] = ($workerOtId > 0 || $agId > 0) ? $prodService->listMachineInkLoadsForWorkOrder($workerOtId, false, $agId) : [];

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


    return false;
}

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
    if ($refId > 0) {
        $selectedEvent = (new ProductionService(Db::erpPdo(), Db::trzPdo()))->getEventById($refId, $workerOtId);
        if ($selectedEvent !== null && (int)$selectedEvent['evt_prod_worker_otid'] === $workerOtId) {
            $overrideEmbalaje = (int)($selectedEvent['overrideembalaje_act'] ?? 0);
        }
    }
    if ((int)($machineInit['type_createstock_act'] ?? 0) === 1) $overrideEmbalaje = 1;

    // Tipos de máquina
    $equipoTypeId = (int)($machineInit['equipo_type_id'] ?? 0);
    $isFlexo = $equipoTypeId === 7 || (int)($machineInit['equipo_prod_isprinter_flexo'] ?? 0) === 1;
    $isSeri = (int)($machineInit['equipo_prod_isprinter_seri'] ?? 0) === 1
        || ($equipoTypeId === 11)
        || ($equipoTypeId === 22)
        || stripos((string)($machineInit['type_ant_title'] ?? ''), 'seri') !== false
        || stripos((string)($machineInit['equipo_name'] ?? ''), 'seri') !== false;
    $isSeriPulpo = ($isSeri || $equipoTypeId === 22 || (int)($machineInit['win_equipoid'] ?? 0) === 36)
        && (stripos((string)($machineInit['equipo_name'] ?? ''), 'pulpo') !== false || (int)($machineInit['win_equipoid'] ?? 0) === 36);
    $isSeriPlana = $isSeri && !$isSeriPulpo;
    $isPrinter = $isFlexo || $isSeri;
    $isSelladora = $equipoTypeId === 8 || stripos((string)($machineInit['type_ant_title'] ?? ''), 'selladora') !== false;

    // Legacy packaging URLs lead to confirmation, never to box generation in CyS.
    // Las URLs antiguas de embalaje llevan a confirmar, no a generar cajas en CyS.
    if ($isSelladora && $mode === 'embalaje') {
        redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=production&overrideembalaje=1&action=start');
        return;
    }
    $isRebobinadora = ($equipoTypeId === 12)
        || stripos((string)($machineInit['type_ant_title'] ?? ''), 'rebobin') !== false
        || stripos((string)($machineInit['equipo_name'] ?? ''), 'rebob') !== false;
    $isEmbalaje = (int)($machineInit['type_createstock_act'] ?? 0) === 1;
    $materialsMachineProcessTitle = $isFlexo ? 'Flexografía' : ($isSeriPulpo ? 'Serigrafía Pulpo' : ($isSeri ? 'Serigrafía' : ($isSelladora ? 'Selladora' : ($isRebobinadora ? 'Rebobinadora' : (!empty($machineInit['equipo_name']) ? (string)$machineInit['equipo_name'] : 'Producción')))));
    $activeStandby = $ctx['active_standby'] ?? null;

    $activeLunch = $ctx['active_lunch'] ?? null;

    $prodService = $ctx['prod_service'] ?? new ProductionService(Db::erpPdo(), Db::trzPdo());
    $phaseResponsibilities = ($isSelladora || $isEmbalaje)
        ? $prodService->getProductionPhaseResponsibilities($workerOtId) : [];
    $phaseReceivers = ($mode === 'production' && ($isSelladora || $isEmbalaje || $overrideEmbalaje === 1))
        ? $prodService->listProductionReceivers() : [];

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
            if (empty($resRebo['ok'])) {
                $errMsg = $resRebo['error'] ?? 'Error al procesar finalización de rebobinado.';
                redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=reboprod&scan_err=' . urlencode($errMsg));
                return;
            }
            $isEmpalme = !empty($resRebo['is_empalme']) ? 1 : 0;
            $isRebobinado = (!empty($resRebo['rebo_type']) && $resRebo['rebo_type'] === 'Rebobinado') ? 1 : 0;
            $cutCount = !empty($resRebo['created_rolls']) ? count($resRebo['created_rolls']) : (!empty($resRebo['created_roll']) ? 1 : 0);
            $firstCutId = !empty($resRebo['created_bag_rolls'][0]['id'])
                ? (int)$resRebo['created_bag_rolls'][0]['id']
                : (!empty($resRebo['created_rolls'][0]['id']) ? (int)$resRebo['created_rolls'][0]['id'] : (!empty($resRebo['created_roll']['id']) ? (int)$resRebo['created_roll']['id'] : 0));
            $palletId = !empty($resRebo['handle_pallet']['id']) ? (int)$resRebo['handle_pallet']['id'] : 0;
            $handleCount = !empty($resRebo['created_handle_rolls']) ? count($resRebo['created_handle_rolls']) : 0;
            $mMermaAmt = (int)($resRebo['manillas_merma_amt'] ?? 0);
            $mMermaKg = (float)($resRebo['manillas_merma_kg'] ?? 0);
            $cutRollIds = array_column($resRebo['created_rolls'] ?? [], 'id');
            $cutIdsParam = !empty($cutRollIds) ? implode(',', $cutRollIds) : ($firstCutId > 0 ? (string)$firstCutId : '');
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=reboprod&rebo_done=1&is_empalme=' . $isEmpalme . '&is_rebobinado=' . $isRebobinado . '&cuts=' . $cutCount . '&first_cut=' . $firstCutId . '&cut_ids=' . urlencode($cutIdsParam) . '&pallet_id=' . $palletId . '&handle_rolls=' . $handleCount . '&merma_manillas=' . $mMermaAmt . '&merma_manillas_kg=' . $mMermaKg);
            return;
        }

        $reboRequestedRolls = $prodService->getRequestedRollsForRebo($workerOtId, $agId);
        $reboMoveOuts = $prodService->getReboFinishedMoves($agId);
        $reboAvailableMaterials = $prodService->getReboAvailableMaterials($agenda ?: []);

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

        if ($selItemId2 > 0) {
            $stmtSingle2 = Db::erpPdo()->prepare("
                SELECT i.*, 
                       COALESCE((SELECT t4b.add_name FROM tran_comments_item_vals t4 INNER JOIN tran_comments_vals t4b ON t4.val_id = t4b.id WHERE t4.item_id = i.id AND t4.com_id = 17 LIMIT 1), i.item_title) as colordesc,
                       COALESCE((SELECT t6.add_name FROM tran_comments_item_vals t5 INNER JOIN tran_comments_vals t6 ON t5.val_id = t6.id WHERE t5.item_id = i.id AND t5.com_id = 18 LIMIT 1), 'TNT') as material,
                       COALESCE((SELECT SUM(iss_inventory) FROM item_shops_storehouses WHERE item_id = i.id AND st_id = 16), 1) as iss_inventory,
                       16 as st_id
                FROM item i WHERE i.id = :id LIMIT 1
            ");
            $stmtSingle2->execute([':id' => $selItemId2]);
            $reboItemSel2 = $stmtSingle2->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    $hasApertura = !empty($alisStatus['has_apertura']);
    // Opening the confirmation screen must not finish the setup event.
    // Abrir la confirmacion no finaliza el evento de alistamiento.
    $hasAlis = !empty($alisStatus['has_alis']);
    $superInputs = (int)($autoctrlStatus['super_inputs'] ?? 0);
    $superPending = !empty($autoctrlStatus['super_pending']);
    $superApproved = !empty($autoctrlStatus['super_approved']);
    $liderInputs = (int)($autoctrlStatus['lider_inputs'] ?? 0);
    $liderPending = !empty($autoctrlStatus['lider_pending']);
    $liderApproved = !empty($autoctrlStatus['lider_approved']);

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

    // Pantalla de Líder de Área: cuando hay pendientes de líder de área y es Flexografía
    $showLiderApproval = (!$isRebobinadora && ($equipoTypeId === 7 || $isFlexo) && $liderPending);

    // Pantalla de Supervisor: cuando hay pendientes de supervisor (o inputs de supervisor pendientes de aprobación)
    $showSupervisorApproval = (!$isRebobinadora && !$showLiderApproval && ($superPending || ($superInputs > 0 && !$superApproved)));

    // Pantalla de Autocontrol Operador: cuando ni líder ni supervisor están pendientes de aprobación y aún no se aprueba
    $showOperatorAutocontrol = (!$isRebobinadora && ($gotoAutocontrol || $mode === 'autocontrol') && !$showLiderApproval && !$showSupervisorApproval);

    // Si la OT requiere autocontrol y no está en un submodo auxiliar (materiales, cargar, mantencion, pause, terminarot),
    // se renderiza EXCLUSIVAMENTE la pantalla de Autocontrol (autocontrol.form.php) tal como en el sistema original de Unibag.
    $isExclusiveAutocontrol = (!$isRebobinadora && ($gotoAutocontrol || $mode === 'autocontrol') && !in_array($mode, ['materiales', 'cargar', 'tintas', 'mantencion', 'pause', 'terminarot'], true));

    // Detectar evento de producción abierto
    $openProdEvent = ProductionService::findOpenProductionPhase($events, $workerOtId, 0);
    $openPackagingEvent = ProductionService::findOpenProductionPhase($events, $workerOtId, 1);
    if ($isEmbalaje) $openProdEvent = $openPackagingEvent;
    $openProdId = $openProdEvent !== null ? (int)$openProdEvent['id'] : 0;
    if ($mode === 'production' && $refId <= 0) {
        $phaseOpen = $overrideEmbalaje === 1 ? $openPackagingEvent : $openProdEvent;
        if ($phaseOpen !== null) $refId = (int)$phaseOpen['id'];
    }

    // Detectar evento de pausa abierto
    $openPauseEvent = null;
    foreach ($events as $ev) {
        if ($ev['evt_type'] === 'pause' && (int)($ev['evt_enddat'] ?? 0) === 0) {
            $openPauseEvent = $ev;
            break;
        }
    }
    $openPauseId = $openPauseEvent !== null ? (int)$openPauseEvent['id'] : 0;

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

    // Cálculos de desarrollo, corte, metros y kg
    $corte_m2 = 0.0;
    $corte_z = 0;
    if ($isFlexo || $isSeriPlana || $isSelladora) {
        $param_medida = (int)($agenda['fab_med_width'] ?? 0);
        if ((int)($agenda['item_prodcalc_fuelle_act'] ?? 0) === 1) {
            $param_medida += (int)($agenda['fab_med_fuelle'] ?? 0);
        }
        $erpPdo = Db::erpPdo();
        $targetEqId = (int)($machineInit['win_equipoid'] ?? 0);

        // Si la máquina actual no es impresora (ej. Selladora tipo 8), buscar la impresora (Flexo o Seri) de la OT
        $isPrintMachine = $isFlexo || $isSeri;
        if (!$isPrintMachine) {
            $reqId = (int)($agenda['ag_reqid'] ?? 0);
            if ($reqId > 0) {
                try {
                    $stmtPrn = $erpPdo->prepare("
                        SELECT t3.win_equipoid
                        FROM prod_worker_ot t1
                        INNER JOIN prod_agenda t2 ON t1.wok_ag_id = t2.id
                        INNER JOIN prod_worker_init t3 ON t1.wok_init_id = t3.id
                        INNER JOIN equipo t7 ON t3.win_equipoid = t7.id
                        WHERE t2.ag_reqid = :reqid AND t7.equipo_type_id IN (7, 11)
                        ORDER BY t1.id DESC LIMIT 1
                    ");
                    $stmtPrn->execute([':reqid' => $reqId]);
                    $foundPrn = (int)$stmtPrn->fetchColumn();
                    if ($foundPrn > 0) {
                        $targetEqId = $foundPrn;
                    } else {
                        $stmtPrnAg = $erpPdo->prepare("
                            SELECT pa.ag_equipo_id
                            FROM prod_agenda pa
                            INNER JOIN equipo eq ON pa.ag_equipo_id = eq.id
                            WHERE pa.ag_reqid = :reqid AND eq.equipo_type_id IN (7, 11)
                            ORDER BY pa.id DESC LIMIT 1
                        ");
                        $stmtPrnAg->execute([':reqid' => $reqId]);
                        $foundPrnAg = (int)$stmtPrnAg->fetchColumn();
                        if ($foundPrnAg > 0) {
                            $targetEqId = $foundPrnAg;
                        }
                    }
                } catch (\Throwable) {}
            }
            if ($targetEqId <= 0 || $targetEqId === (int)($machineInit['win_equipoid'] ?? 0)) {
                $targetEqId = (($agenda['fab_printtype'] ?? '') === 'SERI') ? 14 : 33;
            }
        }

        $stmtEq = $erpPdo->prepare("SELECT * FROM equipo_params WHERE param_equipo_id = :eq AND param_medida >= :med ORDER BY param_medida ASC LIMIT 1");
        $stmtEq->execute([':eq' => $targetEqId, ':med' => $param_medida]);
        $eqParams = $stmtEq->fetch();
        if (!$eqParams && $targetEqId !== 33 && $targetEqId !== 13) {
            $stmtEq->execute([':eq' => 33, ':med' => $param_medida]);
            $eqParams = $stmtEq->fetch();
        }

        if ($eqParams) {
            $corte_z = (int)($eqParams['param_z'] ?? 0);
            $polType = (string)($agenda['req_solic_devprints_poltype'] ?? '');
            $isSeriPrint = (($agenda['fab_printtype'] ?? '') === 'SERI') || (int)($eqParams['equipo_prod_isprinter_seri'] ?? 0) === 1;

            if ($isSeriPrint) {
                $corte_m2 = (float)($eqParams['param_corte'] ?? 0);
            } else {
                // Flexografía: selección según espesor de polímero del diseño (2.84 mm vs 1.7 mm)
                if ($polType === 'pol284' && !empty($eqParams['param_poly28']) && (float)$eqParams['param_poly28'] > 0) {
                    $corte_m2 = (float)$eqParams['param_poly28'];
                } elseif ($polType === 'pol170' && !empty($eqParams['param_poly17']) && (float)$eqParams['param_poly17'] > 0) {
                    $corte_m2 = (float)$eqParams['param_poly17'];
                } else {
                    $corte_m2 = (float)(!empty($eqParams['param_poly28']) ? $eqParams['param_poly28'] : ($eqParams['param_corte'] ?? 0));
                }
            }
        }
        if ($corte_m2 <= 0.0) {
            $corte_m2 = round(((float)($agenda['fab_med_height'] ?? 0) + (float)($agenda['fab_med_fuelle'] ?? 0)) / 100, 4);
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


    if ($isExclusiveAutocontrol) {
        ob_start();
        echo renderView('production/partials/operator_flash_banners', ['agenda' => $agenda]);
        renderQualityAutocontrolExclusiveSection(get_defined_vars());
        return;
    }

    $content = renderView('production/operator_console', get_defined_vars());
    renderProdwrkShell('Operar OT #' . $agenda['prd_number'], $content, 2, $currentOperatorName, $plantaName);
}
