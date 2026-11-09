<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Montaje y Consumo de Bobinas, Asas, Tintas y Bodega 3000
// (src/Http/Production/ProductionMaterialsModule.php)
// =============================================================================

function renderCysHandleMaterials(int $agId, int $otId, string $operator): void
{
    echo renderView('production/materials/cys_handles', get_defined_vars());
}

function renderMachineRollScannerBox(int $agId, string $currentMode, bool $isSelladora, bool $isFlexo, bool $isSeri): void
{
    echo renderView('production/materials/roll_scanner_box', get_defined_vars());
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
    $machineName = (string)($activeInit['equipo_name'] ?? ($selectedAgenda['equipo_name'] ?? 'MÁQUINA'));
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

    $content = renderView('production/materials/request_screen', get_defined_vars());
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

    $content = renderView('production/materials/bodega3000_screen', get_defined_vars());
    renderProdwrkShell('Bobinas Bodega 3000', $content, 8, $operatorName, $plantaName);
}


function renderProductionMaterialsSubformSection(array $__vars): void
{
    echo renderView('production/materials/materials_subform', $__vars);
}

function handleProductionMaterialsRoutes(
    string $path,
    string $method,
    ProductionService $prodService,
    ReceptionService $receptionService,
    string $currentOperatorName,
    int $workerId,
    int $plantaId,
    string $plantaName
): bool {
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
    // Etiquetas de las cajas con código (las crea el cierre de embalaje): un trabajo de impresión térmica con todas.
    if ($path === '/production/boxes/print-job' && $method === 'POST') {
        requireCsrf();
        header('Content-Type: application/json; charset=utf-8');
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', explode('-', (string)($_POST['ids'] ?? '')))))), 0, 300);
        try {
            if ($ids === [] || Db::trzPdo() === null) {
                throw new RuntimeException('No hay cajas para imprimir.');
            }
            $inventory = new WarehouseInventoryService(Db::trzPdo(), Db::erpPdo());
            $printer = new PrintService($_ENV);
            $parts = [];
            foreach ($ids as $id) {
                $box = $inventory->getBox($id);
                if ($box !== null) {
                    $parts[] = $printer->buildBoxCommand($box);
                }
            }
            if ($parts === []) {
                throw new RuntimeException('No se encontraron las cajas.');
            }
            echo json_encode(['ok' => true, 'print_job' => $printer->buildClientJob(implode("

", $parts), 1, count($parts) . ' etiqueta(s) de caja')], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        return true;
    }

    // Pulpo Serigráfico: cajas de bolsas en la máquina (poner, registrar bolsas usadas, anular, bajar, reintentar).
    if ($path === '/production/work-orders/pulpo-bags' && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_POST['agid'] ?? 0);
        $back = '/production/work-orders/operate?agid=' . $agId . '&mode=materiales';
        try {
            if (isErpProductionReadOnlyMode($path)) {
                throw new RuntimeException('Esta vista de producción es de solo lectura.');
            }
            $openOt = $prodService->getOpenWorkerOtByAgId($workerId, $plantaId, $agId);
            $workerOtId = (int)($openOt['id'] ?? 0);
            if ($workerOtId <= 0 || Db::trzPdo() === null) {
                throw new RuntimeException('Abre la OT en la máquina antes de usar cajas de bolsas.');
            }
            $bags = new PulpoBagService(Db::erpPdo(), Db::trzPdo());
            $action = (string)($_POST['bag_action'] ?? '');
            $msg = '';
            $warning = '';
            if ($action === 'mount') {
                $box = $bags->mount($workerOtId, (string)($_POST['code'] ?? ''), $currentOperatorName);
                $msg = !empty($box['already']) ? 'La caja ' . $box['box_code'] . ' ya estaba en la máquina.'
                    : 'Caja ' . $box['box_code'] . ' en máquina: ' . number_format((float)$box['units_qty'], 0, ',', '.') . ' bolsas.';
            } elseif ($action === 'consume') {
                $res = $bags->consume($workerOtId, (int)($_POST['box_id'] ?? 0), (float)($_POST['qty'] ?? 0), $currentOperatorName, (string)($_POST['op_key'] ?? ''));
                $msg = 'Bolsas usadas registradas y descontadas del inventario. Quedan ' . number_format($res['units_after'], 0, ',', '.') . ' en la caja.';
                $warning = $res['warning'];
            } elseif ($action === 'reverse') {
                $res = $bags->reverse($workerOtId, (int)($_POST['move_id'] ?? 0), $currentOperatorName, (string)($_POST['reason'] ?? ''));
                $msg = 'Consumo anulado: las bolsas volvieron a la caja y al inventario.';
                $warning = $res['warning'];
            } elseif ($action === 'unmount') {
                $bags->unmount($workerOtId, (int)($_POST['box_id'] ?? 0), $currentOperatorName);
                $msg = 'Caja bajada de la máquina; queda en Bodega 3000 con su saldo.';
            } elseif ($action === 'resume') {
                $msg = $bags->resumePending($workerOtId) . ' movimiento(s) pendiente(s) completado(s).';
            } else {
                throw new RuntimeException('Acción no válida.');
            }
            redirectResponse($back . '&bag_ok=' . rawurlencode($msg) . ($warning !== '' ? '&supply_warning=' . rawurlencode($warning) : '') . '#pulpo-bags');
        } catch (Throwable $e) {
            redirectResponse($back . '&error=' . rawurlencode($e->getMessage()) . '#pulpo-bags');
        }
        return true;
    }

    if ($path === '/production/work-orders/boxes/generate' && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_POST['agid'] ?? 0);
        // Las cajas con código se crean solas al cerrar el embalaje, ligadas al CC y al stock del ERP.
        // Generarlas a mano dejaba cajas sin stock y con otro formato de código.
        redirectResponse('/production/work-orders/operate?agid=' . $agId . '&error='
            . rawurlencode('Las cajas con código se crean al finalizar el embalaje. Finaliza el embalaje e imprime sus etiquetas.'));
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
            $activeOt = $activeInit !== null
                ? $prodService->getOpenWorkerOtForInit((int)$activeInit['id'], $agId) : null;
            if ($activeOt === null) {
                redirectResponse('/production/work-orders/operate?agid=' . $agId . '&mode=materiales&error='
                    . rawurlencode('Abre la OT en la maquina conectada antes de cargar la bobina.'));
                return true;
            }
            $workerOtId = (int)$activeOt['id'];
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
            redirectResponse('/production/materials/request?agid=' . $agId
                . '&error=' . rawurlencode('Solicita materiales de Bodega 100 o 200 desde esta pantalla. El despacho lo registra bodega.'));
            return true;
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
            $isKg = (stripos($matUnit, 'kg') !== false || stripos($matUnit, 'kilo') !== false || stripos($matType, 'bobina') !== false);
            $amtBolsas = $isKg ? 0 : $matQty;
            $bobinaKg = $isKg ? $matQty : 0;
            $stmtErp = $erpPdo->prepare(
                "INSERT INTO prod_worker_ot_events
                 (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                 VALUES
                 (:ot_id, :amt, :bobina_kg, :crtdat, :enddat, 2, 'materiales', :comments)"
            );
            $stmtErp->execute([
                ':ot_id' => $workerOtId,
                ':amt' => $amtBolsas,
                ':bobina_kg' => $bobinaKg,
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
        $returnTo = trim((string)($_REQUEST['return_to'] ?? ''));

        if ($workerOtId <= 0 && $agId > 0) {
            $openOt = $prodService->getOpenWorkerOtByAgId($workerId, $plantaId, $agId);
            if ($openOt !== null) {
                $workerOtId = (int)($openOt['id'] ?? 0);
            }
        }

        if ($workerOtId <= 0 && $agId > 0) {
            // Si la OT aún no tiene registro en worker_ot, intentar iniciarla
            $activeInit = $prodService->getOpenWorkerInit($workerId, $plantaId);
            if ($activeInit !== null) {
                $startRes = $prodService->startWorkerOt($agId, (int)$activeInit['id']);
                if ($startRes['ok']) {
                    $workerOtId = (int)$startRes['worker_ot_id'];
                }
            }
        }

        if ($workerOtId <= 0 && $agId <= 0) {
            $errorDest = $returnTo !== '' ? $returnTo : ('/production/work-orders/operate?agid=' . $agId . '&mode=tintas');
            $sep = str_contains($errorDest, '?') ? '&' : '?';
            redirectResponse($errorDest . $sep . 'error=' . rawurlencode('No se encontró la OT activa para registrar tintas.'));
            return true;
        }

        if ($inkAction === 'seri_consume') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $initialWeightKg = (float)str_replace(',', '.', (string)($_POST['initial_weight_kg'] ?? 0));
            $loadedKg = (float)str_replace(',', '.', (string)($_POST['loaded_qty'] ?? 0));
            $leftoverKg = (float)str_replace(',', '.', (string)($_POST['leftover_qty'] ?? 0));
            $consumedKg = (float)str_replace(',', '.', (string)($_POST['consumed_qty'] ?? 0));
            $colorLabel = trim((string)($_POST['color_label'] ?? ''));
            $res = $prodService->recordSerigrafiaInkConsumption(
                $workerOtId,
                $agId,
                $itemId,
                $initialWeightKg,
                $loadedKg,
                $leftoverKg,
                $consumedKg,
                $colorLabel,
                $currentOperatorName,
                $comments,
                16
            );
            $param = $res['ok'] ? '&ink_consumed=1' . (($res['warning'] ?? '') !== '' ? '&supply_warning=' . rawurlencode((string)$res['warning']) : '')
                : '&error=' . rawurlencode((string)($res['error'] ?? 'Error al registrar consumo de tinta de serigrafía.'));
        } elseif ($inkAction === 'delete_load') {
            $loadId = (int)($_POST['load_id'] ?? 0);
            $res = $prodService->deleteMachineInkLoad($loadId, $workerOtId, $currentOperatorName);
            $param = $res['ok'] ? '&ink_deleted=1' : '&error=' . rawurlencode((string)($res['error'] ?? 'Error al revertir registro de tinta.'));
        } elseif ($inkAction === 'load') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $qty = (float)str_replace(',', '.', (string)($_POST['qty_kg'] ?? 0));
            $res = $prodService->loadInkToMachine($workerOtId, $agId, $itemId, $qty, $currentOperatorName, $comments, 16);
            $param = $res['ok'] ? '&ink_loaded=1' : '&error=' . rawurlencode((string)($res['error'] ?? 'Error al cargar tinta a máquina.'));
        } elseif ($inkAction === 'consume') {
            $loadId = (int)($_POST['load_id'] ?? 0);
            $qty = (float)str_replace(',', '.', (string)($_POST['qty_kg'] ?? 0));
            $res = $prodService->consumeMachineInkLoad($loadId, $workerOtId, $qty, $currentOperatorName, $comments);
            $param = $res['ok'] ? '&ink_consumed=1' . (($res['warning'] ?? '') !== '' ? '&supply_warning=' . rawurlencode((string)$res['warning']) : '')
                : '&error=' . rawurlencode((string)($res['error'] ?? 'Error al registrar consumo de tinta.'));
        } elseif ($inkAction === 'return') {
            $loadId = (int)($_POST['load_id'] ?? 0);
            $qty = (float)str_replace(',', '.', (string)($_POST['qty_kg'] ?? 0));
            $res = $prodService->returnMachineInkLoad($loadId, $workerOtId, $qty, $currentOperatorName, $comments);
            $param = $res['ok'] ? '&ink_returned=1' : '&error=' . rawurlencode((string)($res['error'] ?? 'Error al devolver tinta a 3000 Insumos de Producción.'));
        } else {
            $param = '&error=' . rawurlencode('Acción de tinta no válida.');
        }

        if ($returnTo !== '') {
            $sep = str_contains($returnTo, '?') ? '&' : '?';
            redirectResponse($returnTo . $sep . ltrim($param, '&'));
            return true;
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

    if (($path === '/production/work-orders/rolls/scrap' || $path === '/rolls/scrap') && $method === 'POST') {
        requireCsrf();
        $rollId = (int)($_REQUEST['roll_id'] ?? 0);
        $agId = (int)($_REQUEST['agid'] ?? 0);
        $workerOtId = (int)($_REQUEST['worker_ot_id'] ?? 0);
        $reason = trim((string)($_REQUEST['reason'] ?? 'BOBINA_SUCIA'));
        $returnTo = trim((string)($_REQUEST['return_to'] ?? ''));

        if ($workerOtId <= 0 && $agId > 0) {
            $openOt = $prodService->getOpenWorkerOtByAgId($workerId, $plantaId, $agId);
            if ($openOt !== null) {
                $workerOtId = (int)($openOt['id'] ?? 0);
            }
        }

        $res = $prodService->scrapRollToMerma($rollId, $workerId, $workerOtId, $reason);

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_REQUEST['ajax']);
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            return true;
        }

        $dest = $returnTo !== '' ? $returnTo : ('/production/work-orders/operate?agid=' . $agId . '&mode=reboprod');
        $sep = str_contains($dest, '?') ? '&' : '?';
        if ($res['ok']) {
            redirectResponse($dest . $sep . 'scrapped=1&scrapped_code=' . urlencode((string)($res['roll_code'] ?? '')));
        } else {
            redirectResponse($dest . $sep . 'error=' . urlencode((string)($res['error'] ?? 'Error al descartar')));
        }
        return true;
    }

    // Registro de Desmontaje y Salida de Bobina (Peso Bobina, mermas por tipo, sobrante trazable)
    if ($path === '/production/work-orders/handles/manage' && $method === 'POST') {
        requireCsrf();
        $agId = (int)($_POST['agid'] ?? 0);
        $otId = (int)($_POST['worker_ot_id'] ?? 0);
        $base = '/production/work-orders/operate?agid=' . $agId . '&mode=materiales';
        try {
            $handles = new HandleConsumptionService(Db::erpPdo(), Db::trzPdo());
            $handleContext = $handles->operatorContext($otId, $workerId, $plantaId);
            if ((int)$handleContext['ag_id'] !== $agId) throw new RuntimeException('La OT no corresponde a esta agenda.');
            $action = (string)($_POST['handle_action'] ?? '');
            $key = (string)($_POST['operation_key'] ?? '');
            $userId = (int)($_SESSION['user_id'] ?? 0);
            if ($userId <= 0) throw new RuntimeException('La sesion no tiene un usuario ERP valido.');
            if ($action === 'scan') {
                $code = trim((string)($_POST['handle_code'] ?? ''));
                $scanned = $handles->scan($otId, $workerId, $plantaId, $code);
                $key = bin2hex(random_bytes(16));
                $confirmations = (array)($_SESSION['cys_handle_confirmations'] ?? []);
                $confirmations[$key] = ['ot_id' => $otId, 'code' => $code, 'worker_id' => $workerId,
                    'weight_kg' => (float)$scanned['weight_kg'], 'received_qty' => (float)$scanned['received_qty'], 'time' => time()];
                $_SESSION['cys_handle_confirmations'] = array_slice($confirmations, -50, null, true);
                redirectResponse($base . '&handle_confirm=' . $key);
                return true;
            }
            if ($action === 'consume') {
                $confirmation = $_SESSION['cys_handle_confirmations'][$key] ?? null;
                if (!$confirmation || (int)$confirmation['ot_id'] !== $otId
                    || (int)$confirmation['worker_id'] !== $workerId || time() - (int)$confirmation['time'] > 7200) {
                    throw new RuntimeException('La confirmacion vencio. Escanea nuevamente las manillas.');
                }
                $mode = (string)($_POST['handle_mode'] ?? '');
                if (!in_array($mode, ['full', 'partial'], true)) throw new RuntimeException('Selecciona el tipo de consumo.');
                $leftoverText = str_replace(',', '.', trim((string)($_POST['handle_leftover_kg'] ?? '')));
                if ($mode === 'partial' && (!is_numeric($leftoverText) || (float)$leftoverText < 0.001)) {
                    throw new RuntimeException('Para consumo parcial indica el peso sobrante mayor que cero.');
                }
                $handles->consume($otId, $workerId, $plantaId, $userId, $currentOperatorName,
                    $key, $confirmation['code'], $mode === 'full' ? 0 : (float)$leftoverText,
                    !empty($_POST['accept_other_product']), $confirmation);
            } elseif ($action === 'resume') {
                $handles->resume($otId, $workerId, $plantaId, $key);
            } elseif ($action === 'reverse' || $action === 'cancel') {
                $supervisor = trim((string)($_POST['handle_supervisor'] ?? ''));
                if (!$prodService->validateSupervisor($supervisor,
                    (string)($_POST['handle_password'] ?? ''))) {
                    throw new RuntimeException('Las credenciales del supervisor no son validas.');
                }
                $reason = trim((string)($_POST['handle_reason'] ?? ''));
                if ($action === 'reverse') {
                    $handles->reverse($otId, $workerId, $plantaId, $userId, $currentOperatorName,
                        $key, (string)($_POST['original_key'] ?? ''), $reason, $supervisor);
                } else {
                    $handles->cancelPending($otId, $workerId, $plantaId, $key, $reason, $supervisor);
                }
            } else {
                throw new RuntimeException('Accion de manillas no valida.');
            }
            redirectResponse($base . '&handles_saved=1');
        } catch (Throwable $e) {
            redirectResponse($base . '&handle_error=' . rawurlencode($e->getMessage()));
        }
        return true;
    }

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
        $workerOt = ($selectedAgId > 0)
            ? $prodService->getOpenWorkerOtForInit((int)$activeInit['id'], $selectedAgId) : null;
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

        // No reutilizar el ID enviado si corresponde a otra sesion o a una OT cerrada.
        // Never reuse a submitted ID belonging to another session or a closed job.
        $activeOt = $activeInit !== null
            ? $prodService->getOpenWorkerOtForInit((int)$activeInit['id'], $agId) : null;
        $workerOtId = $activeOt !== null ? (int)$activeOt['id'] : 0;
        if ($workerOtId <= 0 && $agId > 0 && $activeInit !== null) {
            $startRes = $prodService->startWorkerOt($agId, (int)$activeInit['id']);
            if ($startRes['ok']) {
                $workerOtId = (int)$startRes['worker_ot_id'];
            } else {
                redirectResponse('/production/materials/request?agid=' . $agId . '&error='
                    . rawurlencode((string)($startRes['error'] ?? 'No se pudo abrir la OT en la maquina conectada.')));
                return true;
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
            $isKg = (stripos($matUnit, 'kg') !== false || stripos($matUnit, 'kilo') !== false || stripos($matType, 'bobina') !== false);
            $amtBolsas = $isKg ? 0 : $matQty;
            $bobinaKg = $isKg ? $matQty : 0;
            Db::erpPdo()->prepare("
                INSERT INTO prod_worker_ot_events
                 (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                VALUES
                 (:ot_id, :amt, :bobina_kg, :crtdat, :enddat, 2, 'materiales', :comments)
            ")->execute([
                ':ot_id' => $workerOtId,
                ':amt' => $amtBolsas,
                ':bobina_kg' => $bobinaKg,
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
