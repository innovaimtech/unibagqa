<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Pantallas y Lógica de VoBo de Setup Inicial y Cierre (src/Http/WorkOrders/SetupApprovalModule.php)
// =============================================================================

function renderWorkOrderSetupScreen(
    array $ot,
    ?array $activeShiftSession,
    string $currentOperatorName,
    array $chemicalInputs = [],
    array $boxes = [],
    array $pallets = [],
    ?array $currentRoll = null,
    ?array $outputRoll = null,
    array $assignedCliches = [],
    array $processEvents = [],
    ?string $flashMessage = null,
    bool $flashIsError = false,
    bool $showFinishData = false
): void {
    $service = $GLOBALS['service'] ?? null;
    $sessionForDisplay = null;
    if ((int)($ot['shift_session_id'] ?? 0) > 0) {
        $sessionForDisplay = [
            'id' => (int)($ot['shift_session_id'] ?? 0),
            'machine_type_name' => (string)($ot['shift_machine_type_name'] ?? ''),
            'machine_name' => (string)($ot['shift_machine_name'] ?? ''),
            'operator_name' => (string)($ot['shift_operator_name'] ?? ''),
            'helper_name' => (string)($ot['shift_helper_name'] ?? ''),
            'comments' => (string)($ot['shift_comments'] ?? ''),
        ];
    } elseif ($activeShiftSession !== null) {
        $sessionForDisplay = $activeShiftSession;
    }

    $machineTypeLabel = trim((string)($sessionForDisplay['machine_type_name'] ?? ''));
    if ($machineTypeLabel === '') {
        $machineTypeLabel = 'Pendiente de asignación';
    }
    $machineLabel = trim((string)($sessionForDisplay['machine_name'] ?? ''));
    if ($machineLabel === '') {
        $machineLabel = trim((string)($ot['erp_machine_label'] ?? ''));
    }
    if ($machineLabel === '') {
        $machineLabel = 'Sin máquina asignada';
    }
    $operatorLabel = trim((string)($sessionForDisplay['operator_name'] ?? ''));
    if ($operatorLabel === '') {
        $operatorLabel = trim((string)($ot['erp_worker_name'] ?? ''));
    }
    if ($operatorLabel === '') {
        $operatorLabel = $currentOperatorName;
    }
    $helperLabel = trim((string)($sessionForDisplay['helper_name'] ?? ''));
    $commentsLabel = trim((string)($sessionForDisplay['comments'] ?? ''));
    $sheetText = trim((string)($ot['erp_plan_desc'] ?? '') . ' ' . (string)($ot['sku_final'] ?? ''));
    $stationLabel = $machineLabel;
    $targetQtyLabel = trim((string)($ot['erp_target_qty'] ?? '')) !== ''
        ? (string)$ot['erp_target_qty']
        : (trim((string)($ot['target_qty'] ?? '')) !== '' ? (string)$ot['target_qty'] : '-');
    $dim = unibagResolveWorkOrderDimensionsAndClient($ot, $sheetText);
    $widthLabel = $dim['width'];
    $heightLabel = $dim['height'];
    $gussetLabel = $dim['gusset'];
    $materialLabel = $dim['material'];
    $detectedColors = [];
    for ($c = 1; $c <= 6; $c++) {
        $cd = trim((string)($ot["fab_print_colordesc_{$c}"] ?? ''));
        if ($cd !== '' && !in_array($cd, $detectedColors, true)) {
            $detectedColors[] = $cd;
        }
    }
    foreach ([trim((string)($currentRoll['color'] ?? '')), trim((string)($outputRoll['color'] ?? ''))] as $colorValue) {
        if ($colorValue !== '' && !in_array($colorValue, $detectedColors, true)) {
            $detectedColors[] = $colorValue;
        }
    }
    foreach ($chemicalInputs as $chemicalInput) {
        foreach ([
            trim((string)($chemicalInput['chemical_name'] ?? '')),
            trim((string)($chemicalInput['chemical_code'] ?? '')),
        ] as $colorValue) {
            if ($colorValue !== '' && !in_array($colorValue, $detectedColors, true)) {
                $detectedColors[] = $colorValue;
            }
        }
    }
    foreach (['Natural', 'Blanco', 'Beige', 'Azul', 'Rojo', 'Verde', 'Negro', 'Transparente'] as $colorKeyword) {
        if (stripos($sheetText, $colorKeyword) !== false && !in_array($colorKeyword, $detectedColors, true)) {
            $detectedColors[] = $colorKeyword;
        }
    }
    $colorSlots = array_pad(array_slice($detectedColors, 0, 6), 6, '-');
    $aniloxCatalog = $service instanceof ReceptionService ? $service->listAniloxCatalog() : [];
    $savedAniloxAssignments = $service instanceof ReceptionService ? $service->getWorkOrderAniloxAssignments((int)$ot['id']) : [];
    $aniloxSlots = buildWorkOrderAniloxSlots($colorSlots, $chemicalInputs, $savedAniloxAssignments, null);
    $suggestedAniloxColors = extractSuggestedAniloxColors($colorSlots, $chemicalInputs, $aniloxSlots);
    $totalUnitsQty = 0;
    foreach ($boxes as $box) {
        $totalUnitsQty += (int)($box['units_qty'] ?? 0);
    }
    $targetQtyNumber = is_numeric((string)$targetQtyLabel) ? (float)$targetQtyLabel : 0.0;
    $producedPercent = $targetQtyNumber > 0 ? ($totalUnitsQty * 100) / $targetQtyNumber : 0.0;
    $producedProgressLabel = number_format((float)$totalUnitsQty, 0, '.', '.')
        . ' de '
        . ($targetQtyNumber > 0 ? number_format($targetQtyNumber, 0, '.', '.') : '0')
        . ' (' . number_format($producedPercent, 0, '.', '.') . ' %)';
    $saldoTotalUnits = max(0.0, $targetQtyNumber - (float)$totalUnitsQty);
    $saldoTotalLabel = $targetQtyNumber > 0
        ? number_format($saldoTotalUnits, 0, '.', '.') . ' unidades'
        : '-';
    $fabricColorLabel = trim((string)($currentRoll['color'] ?? '')) !== ''
        ? (string)$currentRoll['color']
        : ((string)$colorSlots[0] !== '-' ? (string)$colorSlots[0] : '-');
    $fabricWidthLabel = isset($currentRoll['width_mm']) && (string)$currentRoll['width_mm'] !== ''
        ? rtrim(rtrim(number_format((float)$currentRoll['width_mm'], 2, '.', ''), '0'), '.')
        : $widthLabel;
    $fabricGramajeLabel = isset($currentRoll['grams']) && (string)$currentRoll['grams'] !== ''
        ? rtrim(rtrim(number_format((float)$currentRoll['grams'], 2, '.', ''), '0'), '.') . ' gr'
        : '-';
    $fabricMetersLabel = isset($currentRoll['meters']) && (string)$currentRoll['meters'] !== ''
        ? number_format((float)$currentRoll['meters'], 0, '.', '.')
        : '-';
    $fabricKgLabel = isset($currentRoll['weight_kg']) && (string)$currentRoll['weight_kg'] !== ''
        ? rtrim(rtrim(number_format((float)$currentRoll['weight_kg'], 2, '.', ''), '0'), '.') . ' kgs'
        : '-';
    $primaryCliche = $assignedCliches[0] ?? null;
    $primaryClicheLocationLabel = trim((string)($primaryCliche['location_detail'] ?? $primaryCliche['location_code'] ?? ''));
    if ($primaryClicheLocationLabel === '') {
        $primaryClicheLocationLabel = trim((string)($ot['erp_machine_label'] ?? ''));
    }
    $primaryClicheCodeLabel = trim((string)($primaryCliche['code'] ?? ''));
    if ($primaryClicheCodeLabel === '') {
        $primaryClicheCodeLabel = trim((string)($ot['erp_prod_number'] ?? ''));
    }
    $primarySelectedAniloxLabel = '-';
    foreach ($aniloxSlots as $aniloxSlot) {
        if ((int)($aniloxSlot['anilox_id'] ?? 0) <= 0) {
            continue;
        }
        foreach ($aniloxCatalog as $aniloxRow) {
            if ((int)($aniloxRow['id'] ?? 0) === (int)$aniloxSlot['anilox_id']) {
                $primarySelectedAniloxLabel = trim((string)($aniloxRow['display_label'] ?? ''));
                if ($primarySelectedAniloxLabel === '') {
                    $primarySelectedAniloxLabel = trim((string)($aniloxRow['code'] ?? '')) . ' - ' . trim((string)($aniloxRow['name'] ?? ''));
                }
                break 2;
            }
        }
    }
    $configuredColorCount = count(array_values(array_filter(
        array_map(static fn(array $slot): string => trim((string)($slot['color_name'] ?? '')), $aniloxSlots),
        static fn(string $value): bool => $value !== ''
    )));
    $impressionsPerDevelopmentLabel = $configuredColorCount > 0 ? ($configuredColorCount . ' impresiones') : 'Sin información';
    $counterPrinterLabel = $totalUnitsQty > 0 ? number_format((float)$totalUnitsQty, 0, '.', '.') : '-';
    $topUnitRows = [];
    for ($unitIndex = 0; $unitIndex < 6; $unitIndex++) {
        $topUnitRows[] = [
            'label' => 'Unidad N° ' . ($unitIndex + 1),
            'value' => trim((string)($aniloxSlots[$unitIndex]['color_name'] ?? '')) !== ''
                ? h((string)$aniloxSlots[$unitIndex]['color_name'])
                : '-',
        ];
    }
    $bottomUnitRows = [];
    for ($unitIndex = 0; $unitIndex < 6; $unitIndex++) {
        $bottomUnitRows[] = [
            'label' => 'Unidad N° ' . ($unitIndex + 1),
            'value' => '-',
        ];
    }
    $legacyClientRows = unibagBuildLegacyClientRows(
        $ot,
        $colorSlots,
        $materialLabel,
        $widthLabel,
        $heightLabel,
        $gussetLabel,
        $targetQtyLabel,
        $stationLabel
    );
    $legacyFabricationRows = [
        ['Rodillo a utilizar', h($primarySelectedAniloxLabel), $topUnitRows[0]['label'], $topUnitRows[0]['value']],
        ['Corte de bolsa', h($heightLabel !== '-' ? ($heightLabel . ' mtrs') : '-'), $topUnitRows[1]['label'], $topUnitRows[1]['value']],
        ['Ubicación Cliché', h($primaryClicheLocationLabel !== '' ? $primaryClicheLocationLabel : '-'), $topUnitRows[2]['label'], $topUnitRows[2]['value']],
        ['Código Cliché', h($primaryClicheCodeLabel !== '' ? $primaryClicheCodeLabel : '-'), $topUnitRows[3]['label'], $topUnitRows[3]['value']],
        ['Pie de Imprenta', 'Cliente', $topUnitRows[4]['label'], $topUnitRows[4]['value']],
        ['N° Código de Barra', trim((string)($currentRoll['roll_code'] ?? '')) !== '' ? h((string)$currentRoll['roll_code']) : '-', $topUnitRows[5]['label'], $topUnitRows[5]['value']],
        ['Impresiones al eje', 'Sin información', $bottomUnitRows[0]['label'], $bottomUnitRows[0]['value']],
        ['Impresiones por desarrollo', h($impressionsPerDevelopmentLabel), $bottomUnitRows[1]['label'], $bottomUnitRows[1]['value']],
        ['% de merma', '-', $bottomUnitRows[2]['label'], $bottomUnitRows[2]['value']],
        ['Bolsas producidas / en curso', h($producedProgressLabel), $bottomUnitRows[3]['label'], $bottomUnitRows[3]['value']],
        ['Bolsas producidas / total', h($producedProgressLabel), $bottomUnitRows[4]['label'], $bottomUnitRows[4]['value']],
        ['Saldo total', h($saldoTotalLabel), $bottomUnitRows[5]['label'], $bottomUnitRows[5]['value']],
        ['', '', '', ''],
        ['Materialidad', h($materialLabel), 'Metros a imprimir', h($fabricMetersLabel)],
        ['Color Tela', h($fabricColorLabel), 'Contador impresora', h($counterPrinterLabel)],
        ['Ancho Tela', h($fabricWidthLabel), 'Kg a imprimir', h($fabricKgLabel)],
        ['Gramaje', h($fabricGramajeLabel), '', ''],
    ];
    $setupStartedAt = trim((string)($sessionForDisplay['started_at'] ?? ''));
    $setupApproval = $service instanceof ReceptionService ? $service->getLastWorkOrderSetupApproval((int)$ot['id']) : null;
    $setupEndedAt = trim((string)($setupApproval['created_at'] ?? ''));
    $lastProductionStart = $service instanceof ReceptionService ? $service->getLastWorkOrderStart((int)$ot['id']) : null;
    $productionStartedAt = trim((string)($lastProductionStart['created_at'] ?? ''));
    $setupDurationLabel = formatElapsedLabel($setupStartedAt !== '' ? $setupStartedAt : null, $setupEndedAt !== '' ? $setupEndedAt : null);
    $setupApprovalRoleLabel = strtoupper(trim((string)($setupApproval['role'] ?? ''))) === 'LEADER' ? 'Líder de Área' : 'Supervisor';
    $setupApprovalUserLabel = trim((string)($setupApproval['approved_display_name'] ?? '')) !== ''
        ? trim((string)$setupApproval['approved_display_name'])
        : trim((string)($setupApproval['approved_username'] ?? ''));
    $setupCommentParts = [];
    if ($commentsLabel !== '') {
        $setupCommentParts[] = $commentsLabel;
    }
    if ($setupEndedAt !== '') {
        $setupCommentParts[] = 'Máquina configurada para producción';
        $setupCommentParts[] = 'Validado por ' . $setupApprovalRoleLabel . ': ' . ($setupApprovalUserLabel !== '' ? $setupApprovalUserLabel : '-');
    }
    $setupCommentLabel = $setupCommentParts !== [] ? h(implode(' | ', $setupCommentParts)) : '-';
    $setupReadyForProduction = $setupEndedAt !== ''
        || (isset($_GET['setup_approved']) && $_GET['setup_approved'] === '1')
        || $productionStartedAt !== '';
    $productionActionButton = $productionStartedAt !== ''
        ? '<span class="legacy-setup-action production" style="opacity:.75;cursor:default">Producción iniciada</span>'
        : ($setupReadyForProduction
            ? '<button class="legacy-setup-action production" type="button" data-progress-modal-open="production" style="border:0">Producción</button>'
            : '<span class="legacy-setup-action production" style="opacity:.5;cursor:not-allowed" title="Finaliza y valida el alistamiento para iniciar la producción.">Producción</span>');
    $setupActionButtons = '<div class="legacy-setup-actions" style="grid-template-columns:repeat(6,minmax(0,1fr))">'
        . '<a class="legacy-setup-action request" href="/work-orders/' . (int)$ot['id'] . '/request-materials" target="_blank" rel="noopener">Solicitar materiales</a>'
        . '<a class="legacy-setup-action materials active" href="/work-orders/' . (int)$ot['id'] . '/materials">Utilizar materiales</a>'
        . $productionActionButton
        . '<button class="legacy-setup-action maintenance" type="button" data-progress-modal-open="maintenance" style="border:0">Registrar mantención</button>'
        . '<button class="legacy-setup-action pause" type="button" data-progress-modal-open="pause" style="border:0">Pausa</button>'
        . '<a class="legacy-setup-action consumption" href="#">Consumo</a>'
        . '</div>';

    $body = '<div class="legacy-screen-title">Alistamiento</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Alistamiento</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/start">Volver a ficha</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Informacion Cliente</th></tr></thead><tbody>';
    foreach ($legacyClientRows as $legacyRow) {
        $body .= '<tr>'
            . '<td class="legacy-label-cell">' . h((string)$legacyRow[0]) . '</td>'
            . '<td class="legacy-value-cell">' . $legacyRow[1] . '</td>'
            . '<td class="legacy-label-cell">' . h((string)$legacyRow[2]) . '</td>'
            . '<td class="legacy-value-cell">' . $legacyRow[3] . '</td>'
            . '</tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Información de Fabricación</th></tr></thead><tbody>';
    foreach ($legacyFabricationRows as $legacyRow) {
        if (
            trim((string)$legacyRow[0]) === ''
            && trim((string)$legacyRow[1]) === ''
            && trim((string)$legacyRow[2]) === ''
            && trim((string)$legacyRow[3]) === ''
        ) {
            $body .= '<tr><td class="legacy-value-cell" colspan="4" style="background:#f3f4f6;padding:8px"></td></tr>';
            continue;
        }
        $body .= '<tr>'
            . '<td class="legacy-label-cell">' . h((string)$legacyRow[0]) . '</td>'
            . '<td class="legacy-value-cell">' . $legacyRow[1] . '</td>'
            . '<td class="legacy-label-cell">' . h((string)$legacyRow[2]) . '</td>'
            . '<td class="legacy-value-cell">' . $legacyRow[3] . '</td>'
            . '</tr>';
    }
    $body .= '</tbody></table></div>';
    if ($showFinishData) {
        $finishRollWeightState = (string)($formState['finish_final_roll_weight_kg'] ?? '');
        $finishChemicalWeightState = (string)($formState['finish_final_chemical_weight_kg'] ?? '');
        $finishBoxQtyState = (string)($formState['finish_box_qty'] ?? '');
        $finishWasteState = (string)($formState['finish_waste_kg'] ?? '0');
        $finishOutputRollWeightState = (string)($formState['finish_output_roll_weight_kg'] ?? '');
        if ($finishOutputRollWeightState === '') {
            $finishOutputRollWeightState = $finishRollWeightState;
        }
        $finishProductionMetersState = (string)($formState['finish_production_meters'] ?? '');
        $finishCommentsState = (string)($formState['finish_comments'] ?? '');
        $chemicalReturnState = isset($formState['finish_chemical_return_kg']) && is_array($formState['finish_chemical_return_kg'])
            ? $formState['finish_chemical_return_kg']
            : [];
        $wasteWeightState = isset($formState['finish_waste_detail_weight']) && is_array($formState['finish_waste_detail_weight'])
            ? $formState['finish_waste_detail_weight']
            : [];
        $wasteCommentState = isset($formState['finish_waste_detail_comment']) && is_array($formState['finish_waste_detail_comment'])
            ? $formState['finish_waste_detail_comment']
            : [];
        $finishSupplyRows = [];
        foreach ($chemicalInputs as $input) {
            $chemicalId = (string)($input['chemical_id'] ?? 0);
            $finishSupplyRows[] = [
                'key' => $chemicalId,
                'code' => (string)($input['chemical_code'] ?? ('INS' . $chemicalId)),
                'detail' => trim((string)($input['chemical_name'] ?? '')) !== ''
                    ? (string)$input['chemical_name']
                    : (string)($input['chemical_code'] ?? 'Insumo'),
                'entry' => (float)($input['weight_kg'] ?? 0),
                'return' => (string)($chemicalReturnState[$chemicalId] ?? ''),
            ];
        }
        $finishUsedRolls = [];
        $currentFinishRollId = (int)($currentRoll['id'] ?? 0);
        $finishRollHistory = $service instanceof ReceptionService ? $service->listWorkOrderRollHistory((int)$ot['id']) : [];
        foreach ($finishRollHistory as $historyRow) {
            $rollId = (int)($historyRow['roll_id'] ?? 0);
            if ($rollId <= 0) {
                continue;
            }
            $eventType = strtoupper(trim((string)($historyRow['type'] ?? '')));
            $payload = isset($historyRow['payload_data']) && is_array($historyRow['payload_data'])
                ? $historyRow['payload_data']
                : [];
            if (!isset($finishUsedRolls[$rollId])) {
                $finishUsedRolls[$rollId] = [
                    'roll_id' => $rollId,
                    'roll_code' => trim((string)($historyRow['roll_code'] ?? '')) !== '' ? (string)$historyRow['roll_code'] : ('#' . $rollId),
                    'sku_code' => (string)($historyRow['sku_code'] ?? '-'),
                    'sku_description' => (string)($historyRow['sku_description'] ?? '-'),
                    'warehouse_code' => (string)($historyRow['warehouse_code'] ?? '-'),
                    'meters' => (float)($historyRow['meters'] ?? 0),
                    'attached_at' => '',
                    'attached_weight_kg' => '',
                    'released_at' => '',
                    'final_weight_kg' => '',
                    'waste_kg' => '',
                    'status_label' => 'En maquina',
                ];
            }
            if ($eventType === 'WORK_ORDER_ROLL_ATTACHED') {
                $finishUsedRolls[$rollId]['attached_at'] = (string)($historyRow['created_at'] ?? '');
                $finishUsedRolls[$rollId]['attached_weight_kg'] = (string)($payload['process_weight_kg'] ?? '');
            } elseif ($eventType === 'WORK_ORDER_ROLL_RELEASED') {
                $finishUsedRolls[$rollId]['released_at'] = (string)($historyRow['created_at'] ?? '');
                $finishUsedRolls[$rollId]['final_weight_kg'] = (string)($payload['final_weight_kg'] ?? '');
                $finishUsedRolls[$rollId]['waste_kg'] = (string)($payload['waste_kg'] ?? '');
                $finishUsedRolls[$rollId]['status_label'] = 'Liberada';
            }
        }
        $finishRollMetrics = [];
        foreach ($finishUsedRolls as $usedRoll) {
            $finishRollMetrics[] = [
                'roll_id' => (int)($usedRoll['roll_id'] ?? 0),
                'meters' => (float)($usedRoll['meters'] ?? 0),
                'entry_weight_kg' => (float)($usedRoll['attached_weight_kg'] !== '' ? $usedRoll['attached_weight_kg'] : 0),
                'final_weight_kg' => (float)($usedRoll['final_weight_kg'] !== '' ? $usedRoll['final_weight_kg'] : 0),
                'is_current' => (int)($usedRoll['roll_id'] ?? 0) === $currentFinishRollId,
            ];
        }
        $finishRollMetricsJson = json_encode($finishRollMetrics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($finishRollMetricsJson) || $finishRollMetricsJson === '') {
            $finishRollMetricsJson = '[]';
        }
        $formatFinishKg = static function ($value): string {
            if ($value === null || $value === '') {
                return '-';
            }
            $number = (float)$value;
            $label = rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
            return ($label !== '' ? $label : '0') . ' Kg';
        };
        $wasteRows = [
            ['key' => 'approval', 'label' => 'Ajuste / Aprobación'],
            ['key' => 'printing', 'label' => 'Impresión'],
            ['key' => 'process', 'label' => 'Merma Proceso'],
            ['key' => 'other', 'label' => 'Otros'],
        ];

        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="5">Cargar Insumos</th></tr></thead><tbody>';
        $body .= '<tr><td class="legacy-label-cell">Código</td><td class="legacy-label-cell">Detalle</td><td class="legacy-label-cell">Entrada</td><td class="legacy-label-cell">Salida</td><td class="legacy-label-cell">Consumo</td></tr>';
        foreach ($finishSupplyRows as $supplyRow) {
            $entryLabel = rtrim(rtrim(number_format((float)$supplyRow['entry'], 3, '.', ''), '0'), '.');
            if ($entryLabel === '') {
                $entryLabel = '0';
            }
            $body .= '<tr>'
                . '<td class="legacy-value-cell">' . h((string)$supplyRow['code']) . '</td>'
                . '<td class="legacy-value-cell">' . h((string)$supplyRow['detail']) . '</td>'
                . '<td class="legacy-value-cell"><input type="text" value="' . h($entryLabel) . ' Kg" readonly style="width:100%;background:#f3f4f6"></td>'
                . '<td class="legacy-value-cell"><input type="number" step="0.001" min="0" name="finish_chemical_return_kg[' . h((string)$supplyRow['key']) . ']" form="finish-production-form" value="' . h((string)$supplyRow['return']) . '" data-finish-entry="' . h(number_format((float)$supplyRow['entry'], 3, '.', '')) . '" data-finish-return-input style="width:100%"></td>'
                . '<td class="legacy-value-cell"><input type="text" value="0 Kg" data-finish-consumption readonly style="width:100%;background:#f3f4f6"></td>'
                . '</tr>';
        }
        if ($finishSupplyRows === []) {
            $body .= '<tr><td class="legacy-value-cell" colspan="5">Sin insumos registrados para esta OT.</td></tr>';
        }
        $body .= '</tbody></table></div>';
        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="7">Bobinas usadas</th></tr></thead><tbody>';
        $body .= '<tr><td class="legacy-label-cell">Codigo</td><td class="legacy-label-cell">Detalle</td><td class="legacy-label-cell">Bodega</td><td class="legacy-label-cell">Ingreso</td><td class="legacy-label-cell">Peso ingreso</td><td class="legacy-label-cell">Peso final</td><td class="legacy-label-cell">Estado</td></tr>';
        foreach ($finishUsedRolls as $usedRoll) {
            $detailLabel = trim((string)$usedRoll['sku_code']) !== '' && trim((string)$usedRoll['sku_code']) !== '-'
                ? (string)$usedRoll['sku_code'] . ' - ' . (string)$usedRoll['sku_description']
                : (string)$usedRoll['sku_description'];
            $body .= '<tr>'
                . '<td class="legacy-value-cell">' . h((string)$usedRoll['roll_code']) . '</td>'
                . '<td class="legacy-value-cell">' . h($detailLabel !== '' ? $detailLabel : '-') . '</td>'
                . '<td class="legacy-value-cell">' . h((string)$usedRoll['warehouse_code']) . '</td>'
                . '<td class="legacy-value-cell">' . h(trim((string)$usedRoll['attached_at']) !== '' ? formatLabelDateTime((string)$usedRoll['attached_at']) : '-') . '</td>'
                . '<td class="legacy-value-cell">' . h($formatFinishKg($usedRoll['attached_weight_kg'])) . '</td>'
                . '<td class="legacy-value-cell">' . h($formatFinishKg($usedRoll['final_weight_kg'])) . '</td>'
                . '<td class="legacy-value-cell">' . h((string)$usedRoll['status_label']) . '</td>'
                . '</tr>';
        }
        if ($finishUsedRolls === []) {
            $body .= '<tr><td class="legacy-value-cell" colspan="7">Sin bobinas registradas en esta OT.</td></tr>';
        }
        $body .= '</tbody></table></div>';

        $body .= '<div class="legacy-sheet-card finish-production-card" style="margin-top:12px"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/finish" id="finish-production-form">'
            . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
            . '<input type="hidden" name="show_finish_data" value="1">'
            . '<input type="hidden" name="finish_final_chemical_weight_kg" id="finish_final_chemical_weight_kg" value="' . h($finishChemicalWeightState) . '">'
            . '<input type="hidden" name="finish_waste_kg" id="finish_waste_kg" value="' . h($finishWasteState) . '">'
            . '<input type="hidden" name="finish_output_roll_weight_kg" id="finish_output_roll_weight_kg" value="' . h($finishOutputRollWeightState) . '">'
            . '<div class="finish-form-row finish-form-row-inline">'
            . '<div class="finish-label">Tipo</div>'
            . '<div class="finish-static-value">' . h($machineTypeLabel) . '</div>'
            . '</div>'
            . '<div class="finish-form-row finish-form-row-inline">'
            . '<div class="finish-label">Máquina</div>'
            . '<div class="finish-static-value">' . h($stationLabel) . '</div>'
            . '</div>'
            . '<div class="finish-form-row finish-form-row-production">'
            . '<div class="finish-label">Producción</div>'
            . '<div class="finish-inline-input"><input name="finish_production_meters" type="number" step="0.001" min="0" value="' . h($finishProductionMetersState) . '"></div>'
            . '<div class="finish-inline-suffix">mtrs/lineales</div>'
            . '</div>'
            . '<div class="finish-form-row finish-form-row-inline">'
            . '<div class="finish-label">Peso Bobina Sobrante</div>'
            . '<div class="finish-short-input"><input id="finish_final_roll_weight_kg" name="finish_final_roll_weight_kg" type="number" step="0.001" min="0" value="' . h($finishRollWeightState) . '"></div>'
            . '<div class="finish-inline-suffix">Kg</div>'
            . '<div class="finish-button-inline"><button class="btn secondary finish-grey-btn" type="button" id="read_scale_finish_roll">Leer balanza</button></div>'
            . '</div>'
            . '<div class="finish-form-row finish-form-row-comments">'
            . '<div class="finish-label">Comentarios</div>'
            . '<div class="finish-comments-wrap"><textarea name="finish_comments" rows="3">' . h($finishCommentsState) . '</textarea></div>'
            . '</div>'
            . '<div class="finish-actions">'
            . '<div class="finish-actions-left"><a class="btn secondary finish-grey-btn" href="/work-orders/' . (int)$ot['id'] . '/setup">Volver</a><button class="btn secondary finish-red-btn" type="reset">Eliminar</button></div>'
            . '<div class="finish-actions-right"><button class="btn secondary finish-grey-btn" type="button" id="finish-production-refresh">Actualizar</button><button class="btn finish-yellow-btn" type="submit">Terminar producción</button></div>'
            . '</div>'
            . '<div class="finish-merma-block" style="margin-top:12px">';
        foreach ($wasteRows as $wasteRow) {
            $body .= '<div class="finish-form-row finish-merma-row">'
                . '<div class="finish-merma-title">Merma</div>'
                . '<div class="finish-merma-detail">' . h((string)$wasteRow['label']) . '</div>'
                . '<div class="finish-merma-unit"><select disabled><option>Kilos</option></select></div>'
                . '<div class="finish-merma-weight"><input type="number" step="0.001" min="0" name="finish_waste_detail_weight[' . h((string)$wasteRow['key']) . ']" value="' . h((string)($wasteWeightState[$wasteRow['key']] ?? '')) . '" data-finish-waste-input></div>'
                . '<div class="finish-merma-comment-label">Comentarios</div>'
                . '<div class="finish-merma-comment"><input type="text" name="finish_waste_detail_comment[' . h((string)$wasteRow['key']) . ']" value="' . h((string)($wasteCommentState[$wasteRow['key']] ?? '')) . '"></div>'
                . '</div>';
        }
        $body .= '</div>'
            . '</form></div>'
            . '<script>
                (function () {
                    var form = document.getElementById("finish-production-form");
                    if (!form) return;
                    var finishRollMetrics = ' . $finishRollMetricsJson . ';
                    function syncFinishOutputWeight() {
                        var finalRollInput = document.getElementById("finish_final_roll_weight_kg");
                        var outputRollInput = document.getElementById("finish_output_roll_weight_kg");
                        if (!finalRollInput || !outputRollInput) return;
                        outputRollInput.value = finalRollInput.value;
                    }
                    function syncFinishProductionMeters() {
                        var finalRollInput = document.getElementById("finish_final_roll_weight_kg");
                        var productionMetersInput = form.querySelector("input[name=\"finish_production_meters\"]");
                        if (!productionMetersInput || !Array.isArray(finishRollMetrics)) return;
                        var currentFinalWeight = finalRollInput ? parseFloat(finalRollInput.value || "0") : 0;
                        if (!isFinite(currentFinalWeight) || currentFinalWeight < 0) currentFinalWeight = 0;
                        var totalMeters = 0;
                        finishRollMetrics.forEach(function (rollMetric) {
                            var entryWeight = parseFloat(rollMetric.entry_weight_kg || "0");
                            var sourceMeters = parseFloat(rollMetric.meters || "0");
                            if (!isFinite(entryWeight) || entryWeight <= 0 || !isFinite(sourceMeters) || sourceMeters <= 0) {
                                return;
                            }
                            var finalWeight = rollMetric.is_current
                                ? currentFinalWeight
                                : parseFloat(rollMetric.final_weight_kg || "0");
                            if (!isFinite(finalWeight) || finalWeight < 0) finalWeight = 0;
                            if (finalWeight > entryWeight) finalWeight = entryWeight;
                            var consumedWeight = entryWeight - finalWeight;
                            var consumedMeters = sourceMeters * (consumedWeight / entryWeight);
                            if (isFinite(consumedMeters) && consumedMeters > 0) {
                                totalMeters += consumedMeters;
                            }
                        });
                        productionMetersInput.value = totalMeters > 0 ? totalMeters.toFixed(3) : "";
                    }
                    function formatKg(value) {
                        var rounded = (Math.round(value * 1000) / 1000).toString();
                        return rounded + " Kg";
                    }
                    function recalcFinish() {
                        var chemicalTotal = 0;
                        document.querySelectorAll("[data-finish-return-input]").forEach(function (input) {
                            var entry = parseFloat(input.getAttribute("data-finish-entry") || "0");
                            var output = parseFloat(input.value || "0");
                            if (!isFinite(output) || output < 0) output = 0;
                            chemicalTotal += output;
                            var consumption = Math.max(0, entry - output);
                            var target = input.parentElement && input.parentElement.nextElementSibling
                                ? input.parentElement.nextElementSibling.querySelector("[data-finish-consumption]")
                                : null;
                            if (target) target.value = formatKg(consumption);
                        });
                        var wasteTotal = 0;
                        document.querySelectorAll("[data-finish-waste-input]").forEach(function (input) {
                            var value = parseFloat(input.value || "0");
                            if (isFinite(value) && value > 0) wasteTotal += value;
                        });
                        var chemicalField = document.getElementById("finish_final_chemical_weight_kg");
                        if (chemicalField) chemicalField.value = chemicalTotal.toFixed(3);
                        var wasteField = document.getElementById("finish_waste_kg");
                        if (wasteField) wasteField.value = wasteTotal.toFixed(3);
                        syncFinishProductionMeters();
                    }
                    document.querySelectorAll("[data-finish-return-input],[data-finish-waste-input]").forEach(function (input) {
                        input.addEventListener("input", recalcFinish);
                    });
                    var refreshBtn = document.getElementById("finish-production-refresh");
                    if (refreshBtn) refreshBtn.addEventListener("click", recalcFinish);
                    var finalRollInput = document.getElementById("finish_final_roll_weight_kg");
                    if (finalRollInput) {
                        finalRollInput.addEventListener("input", syncFinishOutputWeight);
                        finalRollInput.addEventListener("change", syncFinishOutputWeight);
                        finalRollInput.addEventListener("input", syncFinishProductionMeters);
                        finalRollInput.addEventListener("change", syncFinishProductionMeters);
                    }
                    form.addEventListener("submit", syncFinishOutputWeight);
                    syncFinishOutputWeight();
                    recalcFinish();
                })();
            </script>';
        render('Terminar producción', $body);
        return;
    }

    $body .= renderWorkOrderAniloxConfigCard((int)$ot['id'], $aniloxSlots, $aniloxCatalog, $suggestedAniloxColors);
    $body .= $setupActionButtons;
    $body .= '<div class="legacy-sheet-card"><table class="legacy-sheet-table legacy-setup-event-table"><thead><tr><th>Evento</th><th>Inicio</th><th>Termino</th><th>Tiempo</th><th>Cantidad</th><th>Comentarios</th><th>Opciones</th></tr></thead><tbody>';
    $setupOptionsHtml = ($setupEndedAt !== '' || $setupReadyForProduction || $productionStartedAt !== '')
        ? '<span class="btn secondary legacy-setup-finish-btn" style="opacity:.75;cursor:default">Configurado</span>'
        : '<a class="btn secondary legacy-setup-finish-btn" href="/work-orders/' . (int)$ot['id'] . '/in-progress">Terminar</a>';
    $body .= '<tr>'
        . '<td>Alistamiento</td>'
        . '<td>' . h($setupStartedAt !== '' ? $setupStartedAt : '-') . '</td>'
        . '<td>' . h($setupEndedAt !== '' ? formatLabelDateTime($setupEndedAt) : '-') . '</td>'
        . '<td>' . h($setupDurationLabel) . '</td>'
        . '<td>-</td>'
        . '<td>' . $setupCommentLabel . '</td>'
        . '<td>' . $setupOptionsHtml . '</td>'
        . '</tr>';
    if ($productionStartedAt !== '') {
        $productionEndedAt = trim((string)($lastFinish['created_at'] ?? ''));
        $productionComments = trim((string)($lastProductionStart['comments'] ?? ''));
        $productionOptionsHtml = $productionEndedAt !== ''
            ? '<span class="btn secondary legacy-setup-finish-btn" style="opacity:.75;cursor:default">Terminado</span>'
            : '<a class="btn secondary legacy-setup-finish-btn" href="/work-orders/' . (int)$ot['id'] . '/finish-data">Terminar producción</a>';
        $body .= '<tr>'
            . '<td>Producción</td>'
            . '<td>' . h(formatLabelDateTime($productionStartedAt)) . '</td>'
            . '<td>' . h($productionEndedAt !== '' ? formatLabelDateTime($productionEndedAt) : '-') . '</td>'
            . '<td>' . h(formatElapsedLabel($productionStartedAt, $productionEndedAt !== '' ? $productionEndedAt : null)) . '</td>'
            . '<td>-</td>'
            . '<td>' . h($productionComments !== '' ? $productionComments : ($productionEndedAt !== '' ? 'Producción cerrada.' : 'Producción en curso.')) . '</td>'
            . '<td>' . $productionOptionsHtml . '</td>'
            . '</tr>';
    }
    foreach ($processEvents as $processEvent) {
        $startedAt = (string)($processEvent['started_at'] ?? '');
        $endedAt = (string)($processEvent['ended_at'] ?? '');
        $isOpen = strtoupper((string)($processEvent['status'] ?? '')) === 'OPEN';
        $optionsHtml = '<span class="muted">-</span>';
        if ($isOpen) {
            $optionsHtml = '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/production-event/finish" style="margin:0">'
                . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                . '<input type="hidden" name="start_event_id" value="' . (int)($processEvent['start_event_id'] ?? 0) . '">'
                . '<input type="hidden" name="return_screen" value="SETUP">'
                . '<button class="btn secondary legacy-setup-finish-btn" type="submit">Terminar</button>'
                . '</form>';
        }
        $body .= '<tr>'
            . '<td>' . h((string)($processEvent['event_label'] ?? '-')) . '</td>'
            . '<td>' . h(formatLabelDateTime($startedAt)) . '</td>'
            . '<td>' . h($endedAt !== '' ? formatLabelDateTime($endedAt) : '-') . '</td>'
            . '<td>' . h(formatElapsedLabel($startedAt, $endedAt !== '' ? $endedAt : null)) . '</td>'
            . '<td>-</td>'
            . '<td>' . h((string)($processEvent['comments'] ?? '-')) . '</td>'
            . '<td>' . $optionsHtml . '</td>'
            . '</tr>';
    }
    $body .= '</tbody></table></div>';

    $modalBaseStyle = 'display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto';
    $modalCardStyle = 'max-width:720px;margin:20px auto;background:#f4f5f7;border:1px solid #d6d6d6;border-radius:8px;padding:16px 18px;box-shadow:0 20px 45px rgba(15,23,42,.22)';
    if ($setupReadyForProduction && $productionStartedAt === '') {
        $body .= '<div class="legacy-inline-modal" id="progress-modal-production" style="' . $modalBaseStyle . '">'
            . '<div style="' . $modalCardStyle . '">'
            . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Iniciar producción</div><button type="button" class="btn secondary" data-progress-modal-close="production">Cerrar</button></div>'
            . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/production/start">'
            . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
            . '<div class="legacy-sheet-card"><table class="legacy-sheet-table" style="margin:0"><tbody>'
            . '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell">Producción</td><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>'
            . '<tr><td class="legacy-label-cell">Comentario</td><td class="legacy-value-cell" colspan="3"><textarea name="comments" rows="4" required placeholder="Ingresa el comentario para iniciar la producción." style="width:100%;border:1px solid #cfd4dc;border-radius:3px;padding:8px"></textarea></td></tr>'
            . '</tbody></table></div>'
            . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-progress-modal-close="production">Cancelar</button><button class="btn" type="submit">Guardar inicio</button></div>'
            . '</form></div></div>';
    }
    $body .= '<div class="legacy-inline-modal" id="progress-modal-maintenance" style="' . $modalBaseStyle . '">'
        . '<div style="' . $modalCardStyle . '">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Registrar mantención</div><button type="button" class="btn secondary" data-progress-modal-close="maintenance">Cerrar</button></div>'
        . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/production-event/start">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="event_key" value="MAINTENANCE">'
        . '<input type="hidden" name="return_screen" value="SETUP">'
        . '<div class="legacy-sheet-card"><table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell">Mantención</td><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Comentario</td><td class="legacy-value-cell" colspan="3"><textarea name="comments" rows="4" required placeholder="Describe la mantención realizada o el motivo del registro." style="width:100%;border:1px solid #cfd4dc;border-radius:3px;padding:8px"></textarea></td></tr>'
        . '</tbody></table></div>'
        . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-progress-modal-close="maintenance">Cancelar</button><button class="btn" type="submit">Guardar inicio</button></div>'
        . '</form></div></div>';
    $body .= '<div class="legacy-inline-modal" id="progress-modal-pause" style="' . $modalBaseStyle . '">'
        . '<div style="' . $modalCardStyle . '">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Registrar pausa</div><button type="button" class="btn secondary" data-progress-modal-close="pause">Cerrar</button></div>'
        . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/production-event/start">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="event_key" value="PAUSE">'
        . '<input type="hidden" name="return_screen" value="SETUP">'
        . '<div class="legacy-sheet-card"><table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell">Pausa</td><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Comentario</td><td class="legacy-value-cell" colspan="3"><textarea name="comments" rows="4" required placeholder="Indica el motivo de la pausa." style="width:100%;border:1px solid #cfd4dc;border-radius:3px;padding:8px"></textarea></td></tr>'
        . '</tbody></table></div>'
        . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-progress-modal-close="pause">Cancelar</button><button class="btn" type="submit">Guardar inicio</button></div>'
        . '</form></div></div>';
    $body .= '<script>
        (function () {
            function toggleProgressModal(key, open) {
                var modal = document.getElementById("progress-modal-" + key);
                if (!modal) {
                    return;
                }
                modal.style.display = open ? "block" : "none";
            }
            document.querySelectorAll("[data-progress-modal-open]").forEach(function (button) {
                button.addEventListener("click", function () {
                    toggleProgressModal(button.getAttribute("data-progress-modal-open"), true);
                });
            });
            document.querySelectorAll("[data-progress-modal-close]").forEach(function (button) {
                button.addEventListener("click", function () {
                    toggleProgressModal(button.getAttribute("data-progress-modal-close"), false);
                });
            });
            document.querySelectorAll(".legacy-inline-modal").forEach(function (modal) {
                modal.addEventListener("click", function (event) {
                    if (event.target === modal && modal.id.indexOf("progress-modal-") === 0) {
                        modal.style.display = "none";
                    }
                });
            });
        })();
    </script>';

    render('Alistamiento', $body);
}


/**
 * Renderiza la pantalla de aprobación de partida (setup) de una OT.
 *
 * Flujo típico:
 * - Supervisor (u otro rol) valida checklist del alistamiento.
 * - Puede requerir credencial/usuario de aprobación ($approvalUsername).
 * - Al aprobar, la OT pasa a siguiente estado (según backend).
 *
 * ---
 *
 * Renders the Work Order setup (start) approval screen.
 *
 * Typical flow:
 * - Supervisor (or other role) validates the setup checklist.
 * - May require an approval credential/username ($approvalUsername).
 * - On approval, the work order transitions to the next state (handled by backend).
 */
function renderWorkOrderSetupApprovalScreen(
    array $ot,
    ?array $activeShiftSession,
    array $chemicalInputs = [],
    ?array $currentRoll = null,
    ?array $outputRoll = null,
    string $role = 'SUPERVISOR',
    ?string $flashMessage = null,
    bool $flashIsError = false,
    bool $showValidationForm = false,
    string $approvalUsername = '',
    ?string $validatedByLabel = null
): void {
    $sessionForDisplay = null;
    if ((int)($ot['shift_session_id'] ?? 0) > 0) {
        $sessionForDisplay = [
            'machine_name' => (string)($ot['shift_machine_name'] ?? ''),
        ];
    } elseif (is_array($activeShiftSession) && $activeShiftSession !== []) {
        $sessionForDisplay = $activeShiftSession;
    }

    $sheetText = trim((string)($ot['erp_plan_desc'] ?? '') . ' ' . (string)($ot['sku_final'] ?? ''));
    $targetQtyLabel = trim((string)($ot['erp_target_qty'] ?? '')) !== ''
        ? (string)$ot['erp_target_qty']
        : (trim((string)($ot['target_qty'] ?? '')) !== '' ? (string)$ot['target_qty'] : '-');
    $stationLabel = trim((string)($sessionForDisplay['machine_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['machine_name']
        : (trim((string)($ot['erp_machine_label'] ?? '')) !== '' ? (string)$ot['erp_machine_label'] : 'Sin máquina asignada');
    $dim = unibagResolveWorkOrderDimensionsAndClient($ot, $sheetText);
    $widthLabel = $dim['width'];
    $heightLabel = $dim['height'];
    $gussetLabel = $dim['gusset'];
    $detectedColors = [];
    foreach ([trim((string)($currentRoll['color'] ?? '')), trim((string)($outputRoll['color'] ?? ''))] as $colorValue) {
        if ($colorValue !== '' && !in_array($colorValue, $detectedColors, true)) {
            $detectedColors[] = $colorValue;
        }
    }
    foreach ($chemicalInputs as $chemicalInput) {
        foreach ([trim((string)($chemicalInput['chemical_name'] ?? '')), trim((string)($chemicalInput['chemical_code'] ?? ''))] as $colorValue) {
            if ($colorValue !== '' && !in_array($colorValue, $detectedColors, true)) {
                $detectedColors[] = $colorValue;
            }
        }
    }
    foreach (['Natural', 'Blanco', 'Beige', 'Azul', 'Rojo', 'Verde', 'Negro', 'Transparente'] as $colorKeyword) {
        if (stripos($sheetText, $colorKeyword) !== false && !in_array($colorKeyword, $detectedColors, true)) {
            $detectedColors[] = $colorKeyword;
        }
    }
    $colorSlots = array_values(array_filter(array_pad(array_slice($detectedColors, 0, 6), 6, ''), static fn(string $value): bool => trim($value) !== ''));
    $productLabel = trim((string)($ot['erp_plan_desc'] ?? '')) !== '' ? (string)$ot['erp_plan_desc'] : (string)$ot['sku_final'];
    $clientLabel = trim((string)($ot['erp_customer_name'] ?? '')) !== '' ? (string)$ot['erp_customer_name'] : '-';
    $reqLabel = trim((string)($ot['erp_req_id'] ?? '')) !== '' ? (string)$ot['erp_req_id'] : '-';
    $measuresLabel = ($widthLabel !== '-' || $heightLabel !== '-') ? trim($widthLabel . ' x ' . $heightLabel) : '-';
    $fabricLabel = trim((string)($currentRoll['color'] ?? '')) !== '' ? (string)$currentRoll['color'] : '-';
    $handlesLabel = trim((string)($currentRoll['color'] ?? '')) !== '' ? (string)$currentRoll['color'] : '-';
    $colorsLabel = $colorSlots !== [] ? implode(', ', $colorSlots) : '-';
    $autocontrolItems = [
        'Paso de Taca',
        'Color de Tela',
        'Revisión Detalles Impresión vs CC',
        'Caras Impresión',
        'Fecha de Impresión',
        'Ancho Bobina',
        'Calce de Colores',
        'Posicion Taca',
        'Colores de Impresión',
        'Pie de Máquina',
        'Tipo de Tela',
        'Código Barras',
    ];
    $role = strtoupper(trim($role)) === 'LEADER' ? 'LEADER' : 'SUPERVISOR';
    $roleLabel = $role === 'LEADER' ? 'Líder de Área' : 'Supervisor';

    $body = '<div class="legacy-screen-title">Aprobación partida</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Aprobación partida</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/in-progress">Volver</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= '<div class="approval-role-note">Validación pendiente por: <strong>' . h($roleLabel) . '</strong>. Esta aprobación confirma que la máquina quedó configurada con los parámetros de producción y que el alistamiento puede darse por terminado.</div>';
    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><tbody>'
        . '<tr><td class="legacy-label-cell">N° OT</td><td class="legacy-value-cell">' . h((string)$ot['ot_code']) . '</td><td class="legacy-label-cell">N° CC</td><td class="legacy-value-cell">' . h($reqLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cliente</td><td class="legacy-value-cell">' . h($clientLabel) . '</td><td class="legacy-label-cell">Producto</td><td class="legacy-value-cell">' . h($productLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Medidas</td><td class="legacy-value-cell">' . h($measuresLabel) . '</td><td class="legacy-label-cell">Fuelle</td><td class="legacy-value-cell">' . h($gussetLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Area</td><td class="legacy-value-cell">' . h($measuresLabel) . '</td><td class="legacy-label-cell">Tela</td><td class="legacy-value-cell">' . h($fabricLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Manillas</td><td class="legacy-value-cell">' . h($handlesLabel) . '</td><td class="legacy-label-cell">Colores</td><td class="legacy-value-cell">' . h($colorsLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad</td><td class="legacy-value-cell">' . h($targetQtyLabel) . '</td><td class="legacy-label-cell">Máquina</td><td class="legacy-value-cell">' . h($stationLabel) . '</td></tr>'
        . '</tbody></table></div>';

    $body .= '<div class="autocontrol-panel" style="margin-bottom:12px"><div class="autocontrol-panel-header">AUTOCONTROL</div><table class="autocontrol-table"><tbody>';
    for ($rowIndex = 0; $rowIndex < count($autocontrolItems); $rowIndex += 4) {
        $body .= '<tr>';
        foreach (array_slice($autocontrolItems, $rowIndex, 4) as $autocontrolItem) {
            $body .= '<td><label class="autocontrol-checkline">'
                . '<input type="checkbox" checked disabled>'
                . '<span>' . h($autocontrolItem) . '</span>'
                . '</label></td>';
        }
        $body .= '</tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= '<div class="autocontrol-panel"><div class="approval-panel-header"><div class="approval-header-actions"><label class="approval-mark-all"><input type="checkbox" id="approval-mark-all"><span>Marcar todos</span></label></div><div class="title">APROBACIÓN PARTIDA</div></div><table class="autocontrol-table"><tbody>';
    for ($rowIndex = 0; $rowIndex < count($autocontrolItems); $rowIndex += 4) {
        $body .= '<tr>';
        foreach (array_slice($autocontrolItems, $rowIndex, 4) as $autocontrolItem) {
            $body .= '<td><label class="autocontrol-checkline">'
                . '<input type="checkbox" data-approval-check="1">'
                . '<span>' . h($autocontrolItem) . '</span>'
                . '</label></td>';
        }
        $body .= '</tr>';
    }
    $body .= '</tbody></table>';
    $body .= '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/setup-approval/validate" id="approval-validation-form" class="approval-validation' . ($showValidationForm ? ' visible' : '') . '" data-force-visible="' . ($showValidationForm ? '1' : '0') . '">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="role" value="' . h($role) . '">'
        . '<input type="hidden" name="approval_ready" id="approval-ready" value="0">'
        . '<input type="text" name="approval_username" value="' . h($approvalUsername) . '" placeholder="Usuario ' . h(strtolower($roleLabel)) . '" autocomplete="username" required>'
        . '<input type="password" name="approval_password" placeholder="Clave" autocomplete="current-password" required>'
        . '<button class="btn" type="submit">Aprobar partida</button>'
        . '</form>';
    if ($validatedByLabel !== null && trim($validatedByLabel) !== '') {
        $body .= '<div class="approval-validation-status ok">Partida validada por <strong>' . h($roleLabel) . '</strong>: ' . h($validatedByLabel) . '.</div>';
    }
    $body .= '</div>';

    $body .= '<script>
        (function () {
            var master = document.getElementById("approval-mark-all");
            var checks = Array.prototype.slice.call(document.querySelectorAll("[data-approval-check]"));
            var validationForm = document.getElementById("approval-validation-form");
            var readyInput = document.getElementById("approval-ready");
            if (!master || !checks.length) {
                return;
            }
            function syncApprovalState() {
                var allChecked = checks.every(function (check) {
                    return check.checked;
                });
                master.checked = allChecked;
                if (readyInput) {
                    readyInput.value = allChecked ? "1" : "0";
                }
                if (validationForm) {
                    var shouldShow = allChecked || validationForm.getAttribute("data-force-visible") === "1";
                    validationForm.classList.toggle("visible", shouldShow);
                }
            }
            master.addEventListener("change", function () {
                checks.forEach(function (check) {
                    check.checked = master.checked;
                });
                syncApprovalState();
            });
            checks.forEach(function (check) {
                check.addEventListener("change", syncApprovalState);
            });
            syncApprovalState();
        })();
    </script>';

    render('Aprobación partida', $body);
}

/**
 * Renderiza la pantalla de aprobación de término (finish) de una OT.
 *
 * Integra:
 * - Último “finish” reportado (metros, merma, etc.).
 * - Historial de bobinas utilizadas.
 * - Mermas registradas.
 * - Bobina de salida y trazabilidad de etiquetas (impresión).
 *
 * ---
 *
 * Renders the Work Order finish approval screen.
 *
 * Integrates:
 * - Last reported finish data (meters, waste, etc.).
 * - History of used rolls.
 * - Recorded wastes.
 * - Output roll and label printing traceability.
 */
function renderWorkOrderFinishApprovalScreen(
    array $ot,
    ?array $activeShiftSession,
    ?array $lastFinish = null,
    array $rollHistory = [],
    array $wastes = [],
    ?array $outputRoll = null,
    string $role = 'SUPERVISOR',
    ?string $flashMessage = null,
    bool $flashIsError = false,
    string $approvalUsername = '',
    string $printed = '',
    string $boxPrinted = '',
    string $labelRollId = '',
    ?string $validatedByLabel = null
): void {
    $sessionForDisplay = null;
    if ((int)($ot['shift_session_id'] ?? 0) > 0) {
        $sessionForDisplay = [
            'machine_name' => (string)($ot['shift_machine_name'] ?? ''),
        ];
    } elseif (is_array($activeShiftSession) && $activeShiftSession !== []) {
        $sessionForDisplay = $activeShiftSession;
    }

    $sheetText = trim((string)($ot['erp_plan_desc'] ?? '') . ' ' . (string)($ot['sku_final'] ?? ''));
    $stationLabel = trim((string)($sessionForDisplay['machine_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['machine_name']
        : (trim((string)($ot['erp_machine_label'] ?? '')) !== '' ? (string)$ot['erp_machine_label'] : 'Sin máquina asignada');
    $clientLabel = trim((string)($ot['erp_customer_name'] ?? '')) !== '' ? (string)$ot['erp_customer_name'] : '-';
    $productLabel = trim((string)($ot['erp_plan_desc'] ?? '')) !== '' ? (string)$ot['erp_plan_desc'] : (string)($ot['sku_final'] ?? '-');
    $productionMeters = (float)($lastFinish['production_meters'] ?? 0);
    $reportedWasteKg = 0.0;
    foreach ($wastes as $wasteRow) {
        $reportedWasteKg += (float)($wasteRow['weight_kg'] ?? 0);
    }
    if ($reportedWasteKg <= 0) {
        $reportedWasteKg = (float)($lastFinish['waste_kg'] ?? 0);
    }
    $usedRollIds = [];
    foreach ($rollHistory as $rollEvent) {
        $rollId = (int)($rollEvent['roll_id'] ?? 0);
        if ($rollId > 0) {
            $usedRollIds[$rollId] = true;
        }
    }
    $usedRollCount = count($usedRollIds);
    $printedRollCount = ((int)($lastFinish['output_roll_id'] ?? 0) > 0 || $outputRoll !== null) ? 1 : 0;
    $remainingRollWeight = (float)($lastFinish['final_roll_weight_kg'] ?? 0);
    $unitsPrinted = 0;
    $role = strtoupper(trim($role)) === 'LEADER' ? 'LEADER' : 'SUPERVISOR';
    $roleLabel = $role === 'LEADER' ? 'Líder de Área' : 'Supervisor';
    $formatKg = static function ($value): string {
        $label = rtrim(rtrim(number_format((float)$value, 3, '.', ''), '0'), '.');
        return $label !== '' ? $label : '0';
    };
    $colorSlots = [];
    foreach (['Natural', 'Blanco', 'Beige', 'Azul', 'Rojo', 'Verde', 'Negro', 'Amarillo', 'Rosado', 'Fucsia', 'Celeste'] as $colorKeyword) {
        if (stripos($sheetText, $colorKeyword) !== false) {
            $colorSlots[] = $colorKeyword;
        }
    }
    $colorSlots = array_values(array_unique($colorSlots));
    $colorStyleMap = [
        'NATURAL' => '#d6c6aa',
        'BLANCO' => '#e5e7eb',
        'BEIGE' => '#c8b28a',
        'AZUL' => '#2f80ed',
        'ROJO' => '#d92d20',
        'VERDE' => '#16a34a',
        'NEGRO' => '#101828',
        'AMARILLO' => '#facc15',
        'ROSADO' => '#ec4899',
        'FUCSIA' => '#d946ef',
        'CELESTE' => '#38bdf8',
    ];

    $body = '<div class="legacy-screen-title">Validación cierre flexo</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Validación cierre flexo</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/start">Volver</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= '<div style="display:grid;grid-template-columns:minmax(320px,1fr) minmax(360px,1.1fr);gap:12px;align-items:stretch">';
    $body .= '<div class="legacy-sheet-card" style="margin:0;padding:0;overflow:hidden">'
        . '<div style="background:#0f766e;color:#fff;padding:10px 14px;font-size:18px;font-weight:800">Diseño</div>'
        . '<div style="padding:18px;min-height:360px;display:flex;flex-direction:column;justify-content:space-between">'
        . '<div style="border:1px dashed #d0d5dd;border-radius:12px;padding:18px;min-height:250px;background:#f8fafc">'
        . '<div style="font-size:12px;color:#667085;text-transform:uppercase;letter-spacing:.04em;margin-bottom:10px">Referencia de impresión</div>'
        . '<div style="font-size:22px;font-weight:800;color:#101828;line-height:1.2">' . h($productLabel) . '</div>'
        . '<div style="margin-top:14px;color:#475467;font-size:14px">OT: <strong>' . h((string)$ot['ot_code']) . '</strong></div>'
        . '<div style="margin-top:6px;color:#475467;font-size:14px">Máquina: <strong>' . h($stationLabel) . '</strong></div>'
        . '<div style="margin-top:6px;color:#475467;font-size:14px">Bobina impresa: <strong>' . h((string)($outputRoll['roll_code'] ?? '-')) . '</strong></div>'
        . '<div style="margin-top:6px;color:#475467;font-size:14px">Comentarios: ' . h(trim((string)($lastFinish['comments'] ?? '')) !== '' ? (string)$lastFinish['comments'] : '-') . '</div>'
        . '</div>'
        . '<div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-top:18px">';
    foreach ($colorSlots as $colorSlot) {
        $colorKey = strtoupper($colorSlot);
        $body .= '<div title="' . h($colorSlot) . '" style="width:26px;height:26px;border-radius:999px;border:2px solid #fff;box-shadow:0 0 0 1px #d0d5dd;background:' . h($colorStyleMap[$colorKey] ?? '#9ca3af') . '"></div>';
    }
    if ($colorSlots === []) {
        $body .= '<div style="color:#667085;font-size:13px">Sin colores detectados en la OT.</div>';
    }
    $body .= '</div></div></div>';

    $body .= '<div class="legacy-sheet-card" style="margin:0;padding:0;overflow:hidden">'
        . '<div style="background:#0f766e;color:#fff;padding:10px 14px;font-size:18px;font-weight:800">Información</div>'
        . '<div style="padding:0">'
        . '<table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Cliente</td><td class="legacy-value-cell">' . h($clientLabel) . '</td><td class="legacy-label-cell" style="width:64px"> </td></tr>'
        . '<tr><td class="legacy-label-cell">Metros impresos</td><td class="legacy-value-cell">' . h($formatKg($productionMeters)) . '</td><td class="legacy-label-cell">M</td></tr>'
        . '<tr><td class="legacy-label-cell">Unidades impresas</td><td class="legacy-value-cell">' . h((string)$unitsPrinted) . '</td><td class="legacy-label-cell">Und</td></tr>'
        . '<tr><td class="legacy-label-cell">Total mermas reportadas</td><td class="legacy-value-cell">' . h($formatKg($reportedWasteKg)) . '</td><td class="legacy-label-cell">Kg</td></tr>'
        . '<tr><td class="legacy-label-cell">Total bobinas consumidas</td><td class="legacy-value-cell">' . h((string)$usedRollCount) . '</td><td class="legacy-label-cell">Und</td></tr>'
        . '<tr><td class="legacy-label-cell">Total bobinas impresas</td><td class="legacy-value-cell">' . h((string)$printedRollCount) . '</td><td class="legacy-label-cell">Und</td></tr>'
        . '<tr><td class="legacy-label-cell">Peso Bobina Sobrante</td><td class="legacy-value-cell">' . h($formatKg($remainingRollWeight)) . '</td><td class="legacy-label-cell">Kg</td></tr>'
        . '</tbody></table>'
        . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/finish-approval/validate" style="padding:14px 14px 18px">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="role" value="' . h($role) . '">'
        . '<input type="hidden" name="printed" value="' . h($printed) . '">'
        . '<input type="hidden" name="box_printed" value="' . h($boxPrinted) . '">'
        . '<input type="hidden" name="label_roll_id" value="' . h($labelRollId) . '">'
        . '<div class="row" style="gap:10px">'
        . '<div style="flex:1;min-width:180px"><input type="text" name="approval_username" value="' . h($approvalUsername) . '" placeholder="Usuario ' . h(strtolower($roleLabel)) . '" autocomplete="username" required></div>'
        . '<div style="flex:1;min-width:180px"><input type="password" name="approval_password" placeholder="Clave" autocomplete="current-password" required></div>'
        . '</div>'
        . '<div class="row" style="margin-top:14px;justify-content:space-between">'
        . '<a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/start" style="min-width:220px;text-align:center">Volver</a>'
        . '<button class="btn" type="submit" style="min-width:220px">Guardar</button>'
        . '</div>'
        . '</form>';
    if ($validatedByLabel !== null && trim($validatedByLabel) !== '') {
        $body .= '<div class="approval-validation-status ok" style="margin:0 14px 14px">Cierre flexo validado por <strong>' . h($roleLabel) . '</strong>: ' . h($validatedByLabel) . '.</div>';
    }
    $body .= '</div></div></div>';

    render('Validación cierre flexo', $body);
}


function renderWorkOrderPackagingApprovalSummaryHtml(array $ot, array $display): string
{
    $measureLabel = (string)$display['measure_compact'] !== '-' ? (string)$display['measure_compact'] : '-';
    $colorsLabel = trim((string)$display['printed_colors_label']) !== '' ? (string)$display['printed_colors_label'] : '-';

    return '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><tbody>'
        . '<tr><td class="legacy-label-cell">NÂ° OT</td><td class="legacy-value-cell">' . h((string)$ot['ot_code']) . '</td><td class="legacy-label-cell">NÂ° CC</td><td class="legacy-value-cell">' . h((string)$display['request_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cliente</td><td class="legacy-value-cell">' . h((string)$display['client_label']) . '</td><td class="legacy-label-cell">Producto</td><td class="legacy-value-cell">' . h((string)$display['product_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Medidas</td><td class="legacy-value-cell">' . h($measureLabel) . '</td><td class="legacy-label-cell">Fuelle</td><td class="legacy-value-cell">' . h((string)$display['gusset_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Area</td><td class="legacy-value-cell">' . h($measureLabel) . '</td><td class="legacy-label-cell">Tela</td><td class="legacy-value-cell">' . h((string)$display['material_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Manillas</td><td class="legacy-value-cell">' . h((string)$display['handle_color_label']) . '</td><td class="legacy-label-cell">Colores</td><td class="legacy-value-cell">' . h($colorsLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad</td><td class="legacy-value-cell">' . h((string)$display['requested_units_label']) . '</td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
        . '</tbody></table></div>';
}

function renderWorkOrderPackagingSetupScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    array $boxes = [],
    array $pallets = [],
    ?string $flashMessage = null,
    bool $flashIsError = false
): void {
    $service = $GLOBALS['service'] ?? null;
    $display = buildWorkOrderPackagingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions, $boxes, $pallets);
    $setupEvents = $service instanceof ReceptionService ? $service->listWorkOrderPackagingSetupEvents((int)$ot['id']) : [];
    $productionEvents = $service instanceof ReceptionService ? $service->listWorkOrderPackagingProductionEvents((int)$ot['id']) : [];
    $processEvents = $service instanceof ReceptionService ? $service->listWorkOrderProcessEvents((int)$ot['id']) : [];
    $setupApproval = $service instanceof ReceptionService ? $service->getLastWorkOrderPackagingSetupApproval((int)$ot['id']) : null;
    $lastPackagingFinish = $service instanceof ReceptionService ? $service->getLastWorkOrderPackagingFinish((int)$ot['id']) : null;
    $setupApproved = $setupApproval !== null;
    $packagingFinishAfterSetup = false;
    if ($lastPackagingFinish !== null) {
        if ($setupApproval === null) {
            $packagingFinishAfterSetup = true;
        } else {
            $finishTs = strtotime((string)($lastPackagingFinish['created_at'] ?? ''));
            $setupTs = strtotime((string)($setupApproval['created_at'] ?? ''));
            $packagingFinishAfterSetup = ($finishTs === false || $setupTs === false) ? true : ($finishTs >= $setupTs);
        }
    }
    $hasOpenProduction = false;
    foreach ($productionEvents as $productionEvent) {
        if (strtoupper((string)($productionEvent['status'] ?? '')) === 'OPEN') {
            $hasOpenProduction = true;
            break;
        }
    }
    $productionActionButton = $packagingFinishAfterSetup
        ? '<span class="legacy-setup-action production" style="opacity:.75;cursor:default">Producción terminada</span>'
        : ($hasOpenProduction
            ? '<span class="legacy-setup-action production" style="opacity:.75;cursor:default">Producción iniciada</span>'
            : ($setupApproved
                ? '<button class="legacy-setup-action production" type="button" data-progress-modal-open="production" style="border:0">Producción</button>'
                : '<span class="legacy-setup-action production" style="opacity:.5;cursor:not-allowed" title="Finaliza y valida el alistamiento para iniciar la producción.">Producción</span>'));

    $body = '<div class="legacy-screen-title">Alistamiento Embalaje</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Embalaje &gt; Alistamiento</div>'
        . '<div class="legacy-actionbar"><div class="row" style="display:flex;justify-content:space-between;align-items:center;gap:8px">'
        . '<a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/packaging">Volver</a>'
        . '<a class="btn" href="/work-orders/' . (int)$ot['id'] . '/packaging/close">Cerrar OT</a>'
        . '</div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= renderWorkOrderPackagingApprovalSummaryHtml($ot, $display);
    $body .= '<div class="legacy-setup-actions" style="grid-template-columns:repeat(5,minmax(0,1fr));margin-top:12px">'
        . '<a class="legacy-setup-action materials active" href="/work-orders/' . (int)$ot['id'] . '/materials">Utilizar materiales</a>'
        . $productionActionButton
        . '<button class="legacy-setup-action maintenance" type="button" data-progress-modal-open="maintenance" style="border:0">Registrar mantencion</button>'
        . '<button class="legacy-setup-action pause" type="button" data-progress-modal-open="pause" style="border:0">Pausa</button>'
        . '<a class="legacy-setup-action consumption" href="#">Consumo</a>'
        . '</div>';
    $body .= '<div class="legacy-sheet-card" style="margin-top:12px"><div class="table-wrap"><table class="legacy-sheet-table legacy-setup-event-table" style="margin:0"><thead><tr><th>Evento</th><th>Inicio</th><th>Termino</th><th>Tiempo</th><th>Cantidad</th><th>Comentarios</th><th>Opciones</th></tr></thead><tbody>';
    foreach ($setupEvents as $setupEvent) {
        $startedAt = (string)($setupEvent['started_at'] ?? '');
        $endedAt = (string)($setupEvent['ended_at'] ?? '');
        $isOpen = strtoupper((string)($setupEvent['status'] ?? '')) === 'OPEN';
        $eventComments = trim((string)($setupEvent['detail'] ?? '')) !== ''
            ? (string)$setupEvent['detail']
            : (trim((string)($setupEvent['comments'] ?? '')) !== '' ? (string)$setupEvent['comments'] : '-');
        $optionsHtml = '<span class="muted">-</span>';
        if ($isOpen) {
            $optionsHtml = '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/packaging/setup-event/finish" style="margin:0">'
                . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                . '<input type="hidden" name="start_event_id" value="' . (int)($setupEvent['start_event_id'] ?? 0) . '">'
                . '<button class="btn secondary legacy-setup-finish-btn" type="submit">Terminar</button>'
                . '</form>';
        }
        $body .= '<tr>'
            . '<td>' . h((string)($setupEvent['event_label'] ?? 'Alistamiento')) . '</td>'
            . '<td>' . h(formatLabelDateTime($startedAt)) . '</td>'
            . '<td>' . h($endedAt !== '' ? formatLabelDateTime($endedAt) : '-') . '</td>'
            . '<td>' . h(formatElapsedLabel($startedAt, $endedAt !== '' ? $endedAt : null)) . '</td>'
            . '<td>' . h((string)$display['requested_units_label']) . '</td>'
            . '<td>' . h($eventComments) . '</td>'
            . '<td>' . $optionsHtml . '</td>'
            . '</tr>';
    }
    foreach ($productionEvents as $productionEvent) {
        $startedAt = (string)($productionEvent['started_at'] ?? '');
        $endedAt = (string)($productionEvent['ended_at'] ?? '');
        $isOpen = strtoupper((string)($productionEvent['status'] ?? '')) === 'OPEN';
        $productionComments = trim((string)($productionEvent['detail'] ?? '')) !== ''
            ? (string)$productionEvent['detail']
            : (trim((string)($productionEvent['comments'] ?? '')) !== '' ? (string)$productionEvent['comments'] : '-');
        $optionsHtml = $isOpen
            ? '<a class="btn secondary legacy-setup-finish-btn" href="/work-orders/' . (int)$ot['id'] . '/packaging/finish-data">Terminar producción</a>'
            : '<span class="btn secondary legacy-setup-finish-btn" style="opacity:.75;cursor:default">Terminado</span>';
        $body .= '<tr>'
            . '<td>' . h((string)($productionEvent['event_label'] ?? 'Producción')) . '</td>'
            . '<td>' . h($startedAt !== '' ? formatLabelDateTime($startedAt) : '-') . '</td>'
            . '<td>' . h($endedAt !== '' ? formatLabelDateTime($endedAt) : '-') . '</td>'
            . '<td>' . h(formatElapsedLabel($startedAt, $endedAt !== '' ? $endedAt : null)) . '</td>'
            . '<td>' . h((string)$display['requested_units_label']) . '</td>'
            . '<td>' . h($productionComments) . '</td>'
            . '<td>' . $optionsHtml . '</td>'
            . '</tr>';
    }
    foreach ($processEvents as $processEvent) {
        $startedAt = (string)($processEvent['started_at'] ?? '');
        $endedAt = (string)($processEvent['ended_at'] ?? '');
        $isOpen = strtoupper((string)($processEvent['status'] ?? '')) === 'OPEN';
        $optionsHtml = '<span class="muted">-</span>';
        if ($isOpen) {
            $optionsHtml = '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/production-event/finish" style="margin:0">'
                . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                . '<input type="hidden" name="start_event_id" value="' . (int)($processEvent['start_event_id'] ?? 0) . '">'
                . '<input type="hidden" name="return_screen" value="PACKAGING_SETUP">'
                . '<button class="btn secondary legacy-setup-finish-btn" type="submit">Terminar</button>'
                . '</form>';
        }
        $body .= '<tr>'
            . '<td>' . h((string)($processEvent['event_label'] ?? '-')) . '</td>'
            . '<td>' . h(formatLabelDateTime($startedAt)) . '</td>'
            . '<td>' . h($endedAt !== '' ? formatLabelDateTime($endedAt) : '-') . '</td>'
            . '<td>' . h(formatElapsedLabel($startedAt, $endedAt !== '' ? $endedAt : null)) . '</td>'
            . '<td>-</td>'
            . '<td>' . h((string)($processEvent['comments'] ?? '-')) . '</td>'
            . '<td>' . $optionsHtml . '</td>'
            . '</tr>';
    }
    if ($setupEvents === [] && $productionEvents === [] && $processEvents === []) {
        $body .= '<tr><td colspan="7" class="legacy-value-cell">Todavia no hay eventos registrados para Embalaje.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    if ($setupApproval !== null) {
        $approvedBy = trim((string)($setupApproval['approved_display_name'] ?? ''));
        if ($approvedBy === '') {
            $approvedBy = trim((string)($setupApproval['approved_username'] ?? ''));
        }
        $body .= '<div class="ok" style="margin-top:12px">Alistamiento de Embalaje validado correctamente' . ($approvedBy !== '' ? ' por ' . h($approvedBy) : '') . '.</div>';
    }
    if ($setupApproved && !$hasOpenProduction && !$packagingFinishAfterSetup) {
        $body .= '<div class="legacy-inline-modal" id="progress-modal-production" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto">'
            . '<div style="max-width:720px;margin:20px auto;background:#f4f5f7;border:1px solid #d6d6d6;border-radius:8px;padding:16px 18px;box-shadow:0 20px 45px rgba(15,23,42,.22)">'
            . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Iniciar producción Embalaje</div><button type="button" class="btn secondary" data-progress-modal-close="production">Cerrar</button></div>'
            . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/packaging/production/start">'
            . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
            . '<div class="legacy-sheet-card"><table class="legacy-sheet-table" style="margin:0"><tbody>'
            . '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell">Producción</td><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>'
            . '<tr><td class="legacy-label-cell">Comentario</td><td class="legacy-value-cell" colspan="3"><textarea name="comments" rows="4" required placeholder="Ingresa el comentario para iniciar la producción de Embalaje." style="width:100%;border:1px solid #cfd4dc;border-radius:3px;padding:8px"></textarea></td></tr>'
            . '</tbody></table></div>'
            . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-progress-modal-close="production">Cancelar</button><button class="btn" type="submit">Guardar inicio</button></div>'
            . '</form></div></div>';
    }
    $body .= renderWorkOrderSealingProgressModalsHtml((int)$ot['id'], 'PACKAGING_SETUP');

    render('Alistamiento Embalaje', $body);
}

function renderWorkOrderPackagingSetupApprovalScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    array $boxes = [],
    array $pallets = [],
    string $role = 'SUPERVISOR',
    ?string $flashMessage = null,
    bool $flashIsError = false,
    bool $showValidationForm = false,
    string $approvalUsername = '',
    ?string $validatedByLabel = null
): void {
    $display = buildWorkOrderPackagingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions, $boxes, $pallets);
    $autocontrolItems = [
        'Medidas (Bolsa / Manillas)',
        'Sellado / Costura (Bolsa / Manillas)',
        'Color Tela (Bolsa / Manillas)',
        'Materialidad de Bolsa',
        'Etiquetas / Alarma',
        'Logo Manillas',
    ];
    $role = strtoupper(trim($role)) === 'LEADER' ? 'LEADER' : 'SUPERVISOR';
    $roleLabel = $role === 'LEADER' ? 'Lider de Area' : 'Supervisor';

    $body = '<div class="legacy-screen-title">Aprobacion alistamiento Embalaje</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Embalaje &gt; Aprobacion</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/packaging/setup">Volver</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= renderWorkOrderPackagingApprovalSummaryHtml($ot, $display);
    $body .= '<div class="autocontrol-panel"><div class="autocontrol-panel-header">AUTOCONTROL</div><table class="autocontrol-table"><tbody>';
    for ($rowIndex = 0; $rowIndex < count($autocontrolItems); $rowIndex += 4) {
        $body .= '<tr>';
        foreach (array_slice($autocontrolItems, $rowIndex, 4) as $autocontrolItem) {
            $body .= '<td><label class="autocontrol-checkline">'
                . '<input type="checkbox" data-packaging-autocontrol-check="1">'
                . '<span>' . h($autocontrolItem) . '</span>'
                . '</label></td>';
        }
        $body .= '</tr>';
    }
    $body .= '</tbody></table></div>';

    $approvalPanelVisible = $showValidationForm || ($validatedByLabel !== null && trim($validatedByLabel) !== '');
    $body .= '<div class="approval-actions" id="packaging-autocontrol-actions">'
        . '<button type="button" class="approval-action-btn supervisor hidden" id="packaging-open-approval-btn" style="border:0;cursor:pointer">Informar supervisor</button>'
        . '</div>';

    $body .= '<div class="autocontrol-panel" id="packaging-approval-panel" style="' . ($approvalPanelVisible ? '' : 'display:none;') . '"><div class="approval-panel-header"><div class="approval-header-actions"><label class="approval-mark-all"><input type="checkbox" id="packaging-approval-mark-all"><span>Marcar todos</span></label></div><div class="title">APROBACION PARTIDA</div></div><table class="autocontrol-table"><tbody>';
    for ($rowIndex = 0; $rowIndex < count($autocontrolItems); $rowIndex += 4) {
        $body .= '<tr>';
        foreach (array_slice($autocontrolItems, $rowIndex, 4) as $autocontrolItem) {
            $body .= '<td><label class="autocontrol-checkline">'
                . '<input type="checkbox" data-packaging-approval-check="1">'
                . '<span>' . h($autocontrolItem) . '</span>'
                . '</label></td>';
        }
        $body .= '</tr>';
    }
    $body .= '</tbody></table>';
    $body .= '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/packaging/setup-approval/validate" id="packaging-approval-validation-form" class="approval-validation' . ($showValidationForm ? ' visible' : '') . '" data-force-visible="' . ($showValidationForm ? '1' : '0') . '">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="role" value="' . h($role) . '">'
        . '<input type="hidden" name="approval_ready" id="packaging-approval-ready" value="0">'
        . '<input type="text" name="approval_username" value="' . h($approvalUsername) . '" placeholder="Usuario ' . h(strtolower($roleLabel)) . '" autocomplete="username" required>'
        . '<input type="password" name="approval_password" placeholder="Clave" autocomplete="current-password" required>'
        . '<button class="btn" type="submit">Aprobar partida</button>'
        . '</form>';
    if ($validatedByLabel !== null && trim($validatedByLabel) !== '') {
        $body .= '<div class="approval-validation-status ok">Partida validada por <strong>' . h($roleLabel) . '</strong>: ' . h($validatedByLabel) . '.</div>';
    }
    $body .= '</div>';
    $body .= '<div class="legacy-setup-actions" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-top:12px">'
        . '<a class="legacy-setup-action materials active" href="/work-orders/' . (int)$ot['id'] . '/materials">Utilizar materiales</a>'
        . '<button class="legacy-setup-action maintenance" type="button" data-progress-modal-open="maintenance" style="border:0">Registrar mantencion</button>'
        . '<button class="legacy-setup-action pause" type="button" data-progress-modal-open="pause" style="border:0">Pausa</button>'
        . '</div>';
    $body .= renderWorkOrderSealingProgressModalsHtml((int)$ot['id'], 'PACKAGING_SETUP');
    $body .= '<script>
        (function () {
            var autocontrolChecks = Array.prototype.slice.call(document.querySelectorAll("[data-packaging-autocontrol-check]"));
            var openApprovalButton = document.getElementById("packaging-open-approval-btn");
            var approvalPanel = document.getElementById("packaging-approval-panel");
            var master = document.getElementById("packaging-approval-mark-all");
            var checks = Array.prototype.slice.call(document.querySelectorAll("[data-packaging-approval-check]"));
            var validationForm = document.getElementById("packaging-approval-validation-form");
            var readyInput = document.getElementById("packaging-approval-ready");
            if (!master || !checks.length) {
                return;
            }
            function syncAutocontrolState() {
                if (!autocontrolChecks.length || !openApprovalButton) {
                    return;
                }
                var allAutocontrolChecked = autocontrolChecks.every(function (check) {
                    return check.checked;
                });
                openApprovalButton.classList.toggle("hidden", !allAutocontrolChecked);
            }
            function syncApprovalState() {
                var allChecked = checks.every(function (check) {
                    return check.checked;
                });
                master.checked = allChecked;
                if (readyInput) {
                    readyInput.value = allChecked ? "1" : "0";
                }
                if (validationForm) {
                    var shouldShow = allChecked || validationForm.getAttribute("data-force-visible") === "1";
                    validationForm.classList.toggle("visible", shouldShow);
                }
            }
            if (openApprovalButton) {
                openApprovalButton.addEventListener("click", function () {
                    if (approvalPanel) {
                        approvalPanel.style.display = "";
                    }
                });
            }
            master.addEventListener("change", function () {
                checks.forEach(function (check) {
                    check.checked = master.checked;
                });
                syncApprovalState();
            });
            autocontrolChecks.forEach(function (check) {
                check.addEventListener("change", syncAutocontrolState);
            });
            checks.forEach(function (check) {
                check.addEventListener("change", syncApprovalState);
            });
            syncAutocontrolState();
            syncApprovalState();
        })();
    </script>';

    render('Aprobacion alistamiento Embalaje', $body);
}


function renderWorkOrderSealingSetupScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    ?string $flashMessage = null,
    bool $flashIsError = false
): void {
    $service = $GLOBALS['service'] ?? null;
    $display = buildWorkOrderSealingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions);
    $setupEvents = $service instanceof ReceptionService ? $service->listWorkOrderSealingSetupEvents((int)$ot['id']) : [];
    $productionEvents = $service instanceof ReceptionService ? $service->listWorkOrderSealingProductionEvents((int)$ot['id']) : [];
    $processEvents = $service instanceof ReceptionService ? $service->listWorkOrderProcessEvents((int)$ot['id']) : [];
    $setupApproval = $service instanceof ReceptionService ? $service->getLastWorkOrderSealingSetupApproval((int)$ot['id']) : null;
    $lastSealingFinish = $service instanceof ReceptionService ? $service->getLastWorkOrderSealingFinish((int)$ot['id']) : null;
    $setupApproved = $setupApproval !== null;
    $sealingFinishAfterSetup = false;
    if ($lastSealingFinish !== null) {
        if ($setupApproval === null) {
            $sealingFinishAfterSetup = true;
        } else {
            $finishTs = strtotime((string)($lastSealingFinish['created_at'] ?? ''));
            $setupTs = strtotime((string)($setupApproval['created_at'] ?? ''));
            $sealingFinishAfterSetup = ($finishTs === false || $setupTs === false) ? true : ($finishTs >= $setupTs);
        }
    }
    $hasOpenProduction = false;
    foreach ($productionEvents as $productionEvent) {
        if (strtoupper((string)($productionEvent['status'] ?? '')) === 'OPEN') {
            $hasOpenProduction = true;
            break;
        }
    }
    $productionActionButton = $sealingFinishAfterSetup
        ? '<span class="legacy-setup-action production" style="opacity:.75;cursor:default">Producción terminada</span>'
        : ($hasOpenProduction
            ? '<span class="legacy-setup-action production" style="opacity:.75;cursor:default">Producción iniciada</span>'
            : ($setupApproved
                ? '<button class="legacy-setup-action production" type="button" data-progress-modal-open="production" style="border:0">Producción</button>'
                : '<span class="legacy-setup-action production" style="opacity:.5;cursor:not-allowed" title="Finaliza y valida el alistamiento para iniciar la producción.">Producción</span>'));

    $body = '<div class="legacy-screen-title">Alistamiento Selladora</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Selladora &gt; Alistamiento</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/sealing">Volver</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= renderWorkOrderSealingOverviewHtml($ot, $display);
    $body .= '<div class="legacy-setup-actions" style="grid-template-columns:repeat(5,minmax(0,1fr));margin-top:12px">'
        . '<a class="legacy-setup-action materials active" href="/work-orders/' . (int)$ot['id'] . '/materials">Utilizar materiales</a>'
        . $productionActionButton
        . '<button class="legacy-setup-action maintenance" type="button" data-progress-modal-open="maintenance" style="border:0">Registrar mantención</button>'
        . '<button class="legacy-setup-action pause" type="button" data-progress-modal-open="pause" style="border:0">Pausa</button>'
        . '<a class="legacy-setup-action consumption" href="#">Consumo</a>'
        . '</div>';
    $body .= '<div class="legacy-sheet-card" style="margin-top:12px"><div class="table-wrap"><table class="legacy-sheet-table legacy-setup-event-table" style="margin:0"><thead><tr><th>Evento</th><th>Inicio</th><th>Termino</th><th>Tiempo</th><th>Cantidad</th><th>Comentarios</th><th>Opciones</th></tr></thead><tbody>';
    foreach ($setupEvents as $setupEvent) {
        $startedAt = (string)($setupEvent['started_at'] ?? '');
        $endedAt = (string)($setupEvent['ended_at'] ?? '');
        $isOpen = strtoupper((string)($setupEvent['status'] ?? '')) === 'OPEN';
        $eventComments = trim((string)($setupEvent['detail'] ?? '')) !== ''
            ? (string)$setupEvent['detail']
            : (trim((string)($setupEvent['comments'] ?? '')) !== '' ? (string)$setupEvent['comments'] : '-');
        $optionsHtml = '<span class="muted">-</span>';
        if ($isOpen) {
            $optionsHtml = '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/sealing/setup-event/finish" style="margin:0">'
                . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                . '<input type="hidden" name="start_event_id" value="' . (int)($setupEvent['start_event_id'] ?? 0) . '">'
                . '<button class="btn secondary legacy-setup-finish-btn" type="submit">Terminar</button>'
                . '</form>';
        }
        $body .= '<tr>'
            . '<td>' . h((string)($setupEvent['event_label'] ?? 'Alistamiento')) . '</td>'
            . '<td>' . h(formatLabelDateTime($startedAt)) . '</td>'
            . '<td>' . h($endedAt !== '' ? formatLabelDateTime($endedAt) : '-') . '</td>'
            . '<td>' . h(formatElapsedLabel($startedAt, $endedAt !== '' ? $endedAt : null)) . '</td>'
            . '<td>' . h((string)$display['target_qty_label']) . '</td>'
            . '<td>' . h($eventComments) . '</td>'
            . '<td>' . $optionsHtml . '</td>'
            . '</tr>';
    }
    foreach ($productionEvents as $productionEvent) {
        $startedAt = (string)($productionEvent['started_at'] ?? '');
        $endedAt = (string)($productionEvent['ended_at'] ?? '');
        $isOpen = strtoupper((string)($productionEvent['status'] ?? '')) === 'OPEN';
        $productionComments = trim((string)($productionEvent['detail'] ?? '')) !== ''
            ? (string)$productionEvent['detail']
            : (trim((string)($productionEvent['comments'] ?? '')) !== '' ? (string)$productionEvent['comments'] : '-');
        $optionsHtml = $isOpen
            ? '<a class="btn secondary legacy-setup-finish-btn" href="/work-orders/' . (int)$ot['id'] . '/sealing/finish-data">Terminar producción</a>'
            : '<span class="btn secondary legacy-setup-finish-btn" style="opacity:.75;cursor:default">Terminado</span>';
        $body .= '<tr>'
            . '<td>' . h((string)($productionEvent['event_label'] ?? 'Producción')) . '</td>'
            . '<td>' . h($startedAt !== '' ? formatLabelDateTime($startedAt) : '-') . '</td>'
            . '<td>' . h($endedAt !== '' ? formatLabelDateTime($endedAt) : '-') . '</td>'
            . '<td>' . h(formatElapsedLabel($startedAt, $endedAt !== '' ? $endedAt : null)) . '</td>'
            . '<td>' . h((string)$display['target_qty_label']) . '</td>'
            . '<td>' . h($productionComments) . '</td>'
            . '<td>' . $optionsHtml . '</td>'
            . '</tr>';
    }
    foreach ($processEvents as $processEvent) {
        $startedAt = (string)($processEvent['started_at'] ?? '');
        $endedAt = (string)($processEvent['ended_at'] ?? '');
        $isOpen = strtoupper((string)($processEvent['status'] ?? '')) === 'OPEN';
        $optionsHtml = '<span class="muted">-</span>';
        if ($isOpen) {
            $optionsHtml = '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/production-event/finish" style="margin:0">'
                . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                . '<input type="hidden" name="start_event_id" value="' . (int)($processEvent['start_event_id'] ?? 0) . '">'
                . '<input type="hidden" name="return_screen" value="SEALING_SETUP">'
                . '<button class="btn secondary legacy-setup-finish-btn" type="submit">Terminar</button>'
                . '</form>';
        }
        $body .= '<tr>'
            . '<td>' . h((string)($processEvent['event_label'] ?? '-')) . '</td>'
            . '<td>' . h(formatLabelDateTime($startedAt)) . '</td>'
            . '<td>' . h($endedAt !== '' ? formatLabelDateTime($endedAt) : '-') . '</td>'
            . '<td>' . h(formatElapsedLabel($startedAt, $endedAt !== '' ? $endedAt : null)) . '</td>'
            . '<td>-</td>'
            . '<td>' . h((string)($processEvent['comments'] ?? '-')) . '</td>'
            . '<td>' . $optionsHtml . '</td>'
            . '</tr>';
    }
    if ($setupEvents === [] && $productionEvents === [] && $processEvents === []) {
        $body .= '<tr><td colspan="7" class="legacy-value-cell">Todavía no hay eventos registrados para Selladora.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    if ($setupApproved && !$hasOpenProduction && !$sealingFinishAfterSetup) {
        $body .= '<div class="legacy-inline-modal" id="progress-modal-production" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto">'
            . '<div style="max-width:720px;margin:20px auto;background:#f4f5f7;border:1px solid #d6d6d6;border-radius:8px;padding:16px 18px;box-shadow:0 20px 45px rgba(15,23,42,.22)">'
            . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Iniciar producción Selladora</div><button type="button" class="btn secondary" data-progress-modal-close="production">Cerrar</button></div>'
            . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/sealing/production/start">'
            . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
            . '<div class="legacy-sheet-card"><table class="legacy-sheet-table" style="margin:0"><tbody>'
            . '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell">Producción</td><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>'
            . '<tr><td class="legacy-label-cell">Comentario</td><td class="legacy-value-cell" colspan="3"><textarea name="comments" rows="4" required placeholder="Ingresa el comentario para iniciar la producción de Selladora." style="width:100%;border:1px solid #cfd4dc;border-radius:3px;padding:8px"></textarea></td></tr>'
            . '</tbody></table></div>'
            . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-progress-modal-close="production">Cancelar</button><button class="btn" type="submit">Guardar inicio</button></div>'
            . '</form></div></div>';
    }
    $body .= renderWorkOrderSealingProgressModalsHtml((int)$ot['id'], 'SEALING_SETUP');
    $body .= renderWorkOrderSealingStockModalHtml($outputRolls);

    render('Alistamiento Selladora', $body);
}

function renderWorkOrderSealingSetupApprovalScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    string $role = 'SUPERVISOR',
    ?string $flashMessage = null,
    bool $flashIsError = false,
    bool $showValidationForm = false,
    string $approvalUsername = '',
    ?string $validatedByLabel = null
): void {
    $display = buildWorkOrderSealingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions);
    $autocontrolItems = [
        'Sellado Bolsa',
        'Medidas Bolsa',
        'Medidas Fuelle',
        'Color Bolsa',
        'Sellado Manillas',
        'Largo Manillas',
        'Etiquetas / Alarma',
        'Dados Manillas',
    ];
    $role = strtoupper(trim($role)) === 'LEADER' ? 'LEADER' : 'SUPERVISOR';
    $roleLabel = $role === 'LEADER' ? 'Líder de Área' : 'Supervisor';
    $measuresLabel = trim((string)$display['width_label'] . ' x ' . (string)$display['height_label']);
    if ($measuresLabel === 'x' || trim($measuresLabel) === '') {
        $measuresLabel = (string)$display['measure_compact'];
    }
    $areaLabel = (string)$display['gusset_label'] !== '-'
        ? trim((string)$display['width_label'] . ' x ' . (string)$display['gusset_label'])
        : $measuresLabel;
    $fabricLabel = (string)$display['color_label'] !== '' ? (string)$display['color_label'] : '-';
    $handlesLabel = $fabricLabel !== '' ? $fabricLabel : '-';
    $colorsLabel = $fabricLabel !== '' ? $fabricLabel : '-';

    $body = '<div class="legacy-screen-title">Aprobación alistamiento Selladora</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Selladora &gt; Aprobación</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/sealing/setup">Volver</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><tbody>'
        . '<tr><td class="legacy-label-cell">N° OT</td><td class="legacy-value-cell">' . h((string)$ot['ot_code']) . '</td><td class="legacy-label-cell">N° CC</td><td class="legacy-value-cell">' . h((string)$display['request_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cliente</td><td class="legacy-value-cell">' . h((string)$display['client_label']) . '</td><td class="legacy-label-cell">Producto</td><td class="legacy-value-cell">' . h((string)$display['product_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Medidas</td><td class="legacy-value-cell">' . h($measuresLabel !== '' ? $measuresLabel : '-') . '</td><td class="legacy-label-cell">Fuelle</td><td class="legacy-value-cell">' . h((string)$display['gusset_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Area</td><td class="legacy-value-cell">' . h($areaLabel !== '' ? $areaLabel : '-') . '</td><td class="legacy-label-cell">Tela</td><td class="legacy-value-cell">' . h($fabricLabel !== '' ? $fabricLabel : '-') . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Manillas</td><td class="legacy-value-cell">' . h($handlesLabel !== '' ? $handlesLabel : '-') . '</td><td class="legacy-label-cell">Colores</td><td class="legacy-value-cell">' . h($colorsLabel !== '' ? $colorsLabel : '-') . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad</td><td class="legacy-value-cell">' . h((string)$display['target_qty_label']) . '</td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
        . '</tbody></table></div>';

    $body .= '<div class="autocontrol-panel"><div class="autocontrol-panel-header">AUTOCONTROL</div><table class="autocontrol-table"><tbody>';
    for ($rowIndex = 0; $rowIndex < count($autocontrolItems); $rowIndex += 4) {
        $body .= '<tr>';
        foreach (array_slice($autocontrolItems, $rowIndex, 4) as $autocontrolItem) {
            $body .= '<td><label class="autocontrol-checkline">'
                . '<input type="checkbox" data-sealing-autocontrol-check="1">'
                . '<span>' . h($autocontrolItem) . '</span>'
                . '</label></td>';
        }
        $body .= '</tr>';
    }
    $body .= '</tbody></table></div>';

    $approvalPanelVisible = $showValidationForm || ($validatedByLabel !== null && trim($validatedByLabel) !== '');
    $body .= '<div class="approval-actions" id="sealing-autocontrol-actions">'
        . '<button type="button" class="approval-action-btn supervisor hidden" id="sealing-open-approval-btn" style="border:0;cursor:pointer">Informar supervisor</button>'
        . '</div>';

    $body .= '<div class="autocontrol-panel" id="sealing-approval-panel" style="' . ($approvalPanelVisible ? '' : 'display:none;') . '"><div class="approval-panel-header"><div class="approval-header-actions"><label class="approval-mark-all"><input type="checkbox" id="sealing-approval-mark-all"><span>Marcar todos</span></label></div><div class="title">APROBACIÓN PARTIDA</div></div><table class="autocontrol-table"><tbody>';
    for ($rowIndex = 0; $rowIndex < count($autocontrolItems); $rowIndex += 4) {
        $body .= '<tr>';
        foreach (array_slice($autocontrolItems, $rowIndex, 4) as $autocontrolItem) {
            $body .= '<td><label class="autocontrol-checkline">'
                . '<input type="checkbox" data-sealing-approval-check="1">'
                . '<span>' . h($autocontrolItem) . '</span>'
                . '</label></td>';
        }
        $body .= '</tr>';
    }
    $body .= '</tbody></table>';
    $body .= '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/sealing/setup-approval/validate" id="sealing-approval-validation-form" class="approval-validation' . ($showValidationForm ? ' visible' : '') . '" data-force-visible="' . ($showValidationForm ? '1' : '0') . '">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="role" value="' . h($role) . '">'
        . '<input type="hidden" name="approval_ready" id="sealing-approval-ready" value="0">'
        . '<input type="text" name="approval_username" value="' . h($approvalUsername) . '" placeholder="Usuario ' . h(strtolower($roleLabel)) . '" autocomplete="username" required>'
        . '<input type="password" name="approval_password" placeholder="Clave" autocomplete="current-password" required>'
        . '<button class="btn" type="submit">Aprobar partida</button>'
        . '</form>';
    if ($validatedByLabel !== null && trim($validatedByLabel) !== '') {
        $body .= '<div class="approval-validation-status ok">Partida validada por <strong>' . h($roleLabel) . '</strong>: ' . h($validatedByLabel) . '.</div>';
    }
    $body .= '</div>';
    $body .= '<div class="legacy-setup-actions" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-top:12px">'
        . '<a class="legacy-setup-action materials active" href="/work-orders/' . (int)$ot['id'] . '/materials">Utilizar materiales</a>'
        . '<button class="legacy-setup-action maintenance" type="button" data-progress-modal-open="maintenance" style="border:0">Registrar mantención</button>'
        . '<button class="legacy-setup-action pause" type="button" data-progress-modal-open="pause" style="border:0">Pausa</button>'
        . '</div>';
    $body .= renderWorkOrderSealingProgressModalsHtml((int)$ot['id'], 'SEALING_SETUP');
    $body .= renderWorkOrderSealingStockModalHtml($outputRolls);
    $body .= '<script>
        (function () {
            var autocontrolChecks = Array.prototype.slice.call(document.querySelectorAll("[data-sealing-autocontrol-check]"));
            var openApprovalButton = document.getElementById("sealing-open-approval-btn");
            var approvalPanel = document.getElementById("sealing-approval-panel");
            var master = document.getElementById("sealing-approval-mark-all");
            var checks = Array.prototype.slice.call(document.querySelectorAll("[data-sealing-approval-check]"));
            var validationForm = document.getElementById("sealing-approval-validation-form");
            var readyInput = document.getElementById("sealing-approval-ready");
            if (!master || !checks.length) {
                return;
            }
            function syncAutocontrolState() {
                if (!autocontrolChecks.length || !openApprovalButton) {
                    return;
                }
                var allAutocontrolChecked = autocontrolChecks.every(function (check) {
                    return check.checked;
                });
                openApprovalButton.classList.toggle("hidden", !allAutocontrolChecked);
            }
            function syncApprovalState() {
                var allChecked = checks.every(function (check) {
                    return check.checked;
                });
                master.checked = allChecked;
                if (readyInput) {
                    readyInput.value = allChecked ? "1" : "0";
                }
                if (validationForm) {
                    var shouldShow = allChecked || validationForm.getAttribute("data-force-visible") === "1";
                    validationForm.classList.toggle("visible", shouldShow);
                }
            }
            if (openApprovalButton) {
                openApprovalButton.addEventListener("click", function () {
                    if (approvalPanel) {
                        approvalPanel.style.display = "";
                    }
                });
            }
            master.addEventListener("change", function () {
                checks.forEach(function (check) {
                    check.checked = master.checked;
                });
                syncApprovalState();
            });
            autocontrolChecks.forEach(function (check) {
                check.addEventListener("change", syncAutocontrolState);
            });
            checks.forEach(function (check) {
                check.addEventListener("change", syncApprovalState);
            });
            syncAutocontrolState();
            syncApprovalState();
        })();
    </script>';

    render('Aprobación alistamiento Selladora', $body);
}


function handleSetupApprovalRoutes(string $path, string $method, ReceptionService $service, PDO $erpPdo, string $currentOperatorName): bool
{
if (preg_match('#^/work-orders/(\d+)/finish-approval$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    if ($lastFinish === null) {
        header('Location: /work-orders/' . $workOrderId . '/finish-data?_redirect_from_finishapproval=1');
        exit;
    }
    $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
    if ($finishApproval !== null) {
        $validatedByLabel = trim((string)($finishApproval['approved_display_name'] ?? '')) !== ''
            ? (string)$finishApproval['approved_display_name'] . ' (' . (string)($finishApproval['approved_username'] ?? '') . ')'
            : (string)($finishApproval['approved_username'] ?? '');
        $approvedAt = trim((string)($finishApproval['created_at'] ?? ''));
        $comments = trim((string)($finishApproval['comments'] ?? ''));
        $extra = '';
        if ($approvedAt !== '') {
            $extra .= '<div class="muted" style="margin-bottom:6px">Fecha: ' . h($approvedAt) . '</div>';
        }
        if ($comments !== '') {
            $extra .= '<div class="muted" style="margin-bottom:10px">Comentarios: ' . h($comments) . '</div>';
        }
        $screen = '<div class="card"><div style="font-size:18px;font-weight:800;margin-bottom:8px;color:#16a34a">Cierre de flexografía ya validado</div>'
            . '<div class="muted" style="margin-bottom:10px">Esta validación ya fue aplicada por <strong>' . h($validatedByLabel) . '</strong>. Puedes continuar con Selladora o Embalaje según corresponda, o volver a la ficha de la OT.</div>'
            . $extra
            . '<div class="row" style="gap:8px;justify-content:flex-start;flex-wrap:wrap"><a class="btn secondary" href="/work-orders/' . (int)$workOrderId . '/start">Volver a la OT</a><a class="btn" href="/work-orders/' . (int)$workOrderId . '/sealing?_redirect_from_finishapproval=1">Ir a Selladora</a><a class="btn secondary" href="/work-orders/' . (int)$workOrderId . '/packaging?_redirect_from_finishapproval=1">Ir a Embalaje</a></div></div>';
        render('Validación de cierre ya aplicada', $screen);
        exit;
    }
    $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
    $wastes = $service->listProductionWastesByWorkOrder($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    renderWorkOrderFinishApprovalScreen(
        $ot,
        $activeShiftSession,
        $lastFinish,
        $rollHistory,
        $wastes,
        $outputRoll,
        (string)($_GET['role'] ?? 'SUPERVISOR'),
        null,
        false,
        '',
        (string)($_GET['printed'] ?? ''),
        (string)($_GET['box_printed'] ?? ''),
        (string)($_GET['label_roll_id'] ?? ''),
        null
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing/setup/start$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
    if ($finishApproval === null) {
        header('Location: /work-orders/' . $workOrderId . '/finish-approval');
        exit;
    }

    $comments = trim((string)($_POST['comments'] ?? ''));
    $helperName = trim((string)($_POST['helper_name'] ?? ''));
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }

    if ($helperName === '') {
        renderWorkOrderSealingInfoScreen(
            $ot,
            $outputRolls,
            $activeShiftSession,
            $service->getLastWorkOrderFinish($workOrderId),
            [],
            $helperOptions,
            'Debes seleccionar un ayudante para iniciar el alistamiento de Selladora.',
            true,
            ['helper_name' => '', 'comments' => $comments]
        );
        exit;
    }

    $sessionId = (int)($ot['shift_session_id'] ?? 0);
    if ($sessionId <= 0) {
        $sessionId = (int)($activeShiftSession['id'] ?? 0);
    }
    if ($sessionId > 0) {
        $service->updateShiftSessionHeader($sessionId, $helperName, $comments);
        $service->assignActiveShiftSessionToWorkOrder($workOrderId, $currentOperatorName);
    }

    $detailParts = [];
    $measureCompact = trim((string)($ot['sku_final'] ?? ''));
    if (trim((string)($ot['erp_plan_desc'] ?? '')) !== '') {
        $detailParts[] = 'De ' . trim((string)$ot['erp_plan_desc']);
    }
    if ($measureCompact !== '') {
        $detailParts[] = 'a ' . $measureCompact;
    }
    $setupResult = $service->startWorkOrderSealingSetupEvent(
        $workOrderId,
        $comments,
        $currentOperatorName,
        trim(implode(' ', $detailParts))
    );
    if (($setupResult['ok'] ?? false) !== true) {
        renderWorkOrderSealingInfoScreen(
            $ot,
            $outputRolls,
            $activeShiftSession,
            $service->getLastWorkOrderFinish($workOrderId),
            [],
            $helperOptions,
            implode(' ', array_map('strval', array_values($setupResult['errors'] ?? []))),
            true,
            ['helper_name' => $helperName, 'comments' => $comments]
        );
        exit;
    }

    header('Location: /work-orders/' . $workOrderId . '/sealing/setup?setup_started=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing/setup$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
    if ($finishApproval === null) {
        header('Location: /work-orders/' . $workOrderId . '/finish-approval');
        exit;
    }
    $setupApproval = $service->getLastWorkOrderSealingSetupApproval($workOrderId);
    $lastSealingFinish = $service->getLastWorkOrderSealingFinish($workOrderId);
    $sealingFinishAfterSetup = false;
    if ($lastSealingFinish !== null) {
        if ($setupApproval === null) {
            $sealingFinishAfterSetup = true;
        } else {
            $finishTs = strtotime((string)($lastSealingFinish['created_at'] ?? ''));
            $setupTs = strtotime((string)($setupApproval['created_at'] ?? ''));
            $sealingFinishAfterSetup = ($finishTs === false || $setupTs === false) ? true : ($finishTs >= $setupTs);
        }
    }
    if ($sealingFinishAfterSetup) {
        header('Location: /work-orders/' . $workOrderId . '/packaging?sealing_finished=1');
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['setup_started']) && $_GET['setup_started'] === '1') {
        $flashMessage = 'Alistamiento de Selladora iniciado correctamente.';
    } elseif (isset($_GET['setup_approved']) && $_GET['setup_approved'] === '1') {
        $approvedBy = trim((string)($_GET['approved_by'] ?? ''));
        $flashMessage = $approvedBy !== ''
            ? 'Alistamiento de Selladora validado por ' . $approvedBy . '.'
            : 'Alistamiento de Selladora validado correctamente.';
    } elseif (isset($_GET['production_started']) && $_GET['production_started'] === '1') {
        $flashMessage = 'Producción de Selladora iniciada correctamente.';
    } elseif (isset($_GET['sealing_finished']) && $_GET['sealing_finished'] === '1') {
        $flashMessage = 'Producción de Selladora terminada correctamente.';
    } elseif (isset($_GET['process_started']) && $_GET['process_started'] === '1') {
        $flashMessage = 'Evento de producción iniciado correctamente.';
    } elseif (isset($_GET['process_finished']) && $_GET['process_finished'] === '1') {
        $flashMessage = 'Evento de producción terminado correctamente.';
    } elseif (isset($_GET['process_error']) && trim((string)$_GET['process_error']) !== '') {
        $flashMessage = trim((string)$_GET['process_error']);
        $flashIsError = true;
    }
    renderWorkOrderSealingSetupScreen($ot, $outputRolls, $activeShiftSession, $helperOptions, $flashMessage, $flashIsError);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/setup/start$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }

    $helperName = trim((string)($_POST['helper_name'] ?? ''));
    $comments = trim((string)($_POST['comments'] ?? ''));
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }

    $sessionId = (int)($ot['shift_session_id'] ?? 0);
    if ($sessionId <= 0) {
        $sessionId = (int)($activeShiftSession['id'] ?? 0);
    }
    if ($sessionId > 0) {
        $service->updateShiftSessionHeader($sessionId, '', $comments);
        $service->assignActiveShiftSessionToWorkOrder($workOrderId, $currentOperatorName);
    }

    $setupResult = $service->startWorkOrderPackagingSetupEvent(
        $workOrderId,
        $comments,
        $currentOperatorName,
        'Alistamiento inicial de Embalaje.'
    );
    if (($setupResult['ok'] ?? false) !== true) {
        renderWorkOrderPackagingInfoScreen(
            $ot,
            $outputRolls,
            $activeShiftSession,
            $helperOptions,
            $boxes,
            $pallets,
            implode(' ', array_map('strval', array_values($setupResult['errors'] ?? []))),
            true,
            ['comments' => $comments]
        );
        exit;
    }

    header('Location: /work-orders/' . $workOrderId . '/packaging/setup?setup_started=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/setup$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    if ((string)($ot['status'] ?? '') === 'CLOSED') {
        header('Location: /work-orders/' . $workOrderId . '/start?closed=1');
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['setup_started']) && $_GET['setup_started'] === '1') {
        $flashMessage = 'Alistamiento de Embalaje iniciado correctamente.';
    } elseif (isset($_GET['setup_approved']) && $_GET['setup_approved'] === '1') {
        $approvedBy = trim((string)($_GET['approved_by'] ?? ''));
        $flashMessage = $approvedBy !== ''
            ? 'Alistamiento de Embalaje validado por ' . $approvedBy . '.'
            : 'Alistamiento de Embalaje validado correctamente.';
    } elseif (isset($_GET['production_started']) && $_GET['production_started'] === '1') {
        $flashMessage = 'Producción de Embalaje iniciada correctamente.';
    } elseif (isset($_GET['packaging_finished']) && $_GET['packaging_finished'] === '1') {
        $flashMessage = 'Producción de Embalaje terminada correctamente.';
    } elseif (isset($_GET['process_started']) && $_GET['process_started'] === '1') {
        $flashMessage = 'Evento de producción iniciado correctamente.';
    } elseif (isset($_GET['process_finished']) && $_GET['process_finished'] === '1') {
        $flashMessage = 'Evento de producción terminado correctamente.';
    } elseif (isset($_GET['process_error']) && trim((string)$_GET['process_error']) !== '') {
        $flashMessage = trim((string)$_GET['process_error']);
        $flashIsError = true;
    }
    renderWorkOrderPackagingSetupScreen($ot, $outputRolls, $activeShiftSession, $helperOptions, $boxes, $pallets, $flashMessage, $flashIsError);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/setup-event/finish$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    try {
        $service->finishWorkOrderPackagingSetupEvent(
            $workOrderId,
            (int)($_POST['start_event_id'] ?? 0),
            $currentOperatorName
        );
        header('Location: /work-orders/' . $workOrderId . '/packaging/setup-approval');
    } catch (Throwable $e) {
        header('Location: /work-orders/' . $workOrderId . '/packaging/setup?process_error=' . urlencode($e->getMessage()));
    }
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/setup-approval$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['approved'])) {
        $approvedBy = trim((string)($_GET['approved_by'] ?? ''));
        $flashMessage = $approvedBy !== ''
            ? 'Alistamiento de Embalaje validado por ' . $approvedBy . '.'
            : 'Alistamiento de Embalaje validado correctamente.';
    } elseif (isset($_GET['approval_error'])) {
        $flashMessage = trim((string)$_GET['approval_error']);
        $flashIsError = true;
    }
    renderWorkOrderPackagingSetupApprovalScreen(
        $ot,
        $outputRolls,
        $activeShiftSession,
        $helperOptions,
        $boxes,
        $pallets,
        (string)($_GET['role'] ?? 'SUPERVISOR'),
        $flashMessage,
        $flashIsError,
        false,
        '',
        isset($_GET['approved']) ? trim((string)($_GET['approved_by'] ?? '')) : null
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/setup-approval/validate$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $role = (string)($_POST['role'] ?? 'SUPERVISOR');
    $approvalUsername = trim((string)($_POST['approval_username'] ?? ''));
    $approvalPassword = (string)($_POST['approval_password'] ?? '');
    $approvalReady = trim((string)($_POST['approval_ready'] ?? '0')) === '1';

    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }

    $flashMessage = null;
    $flashIsError = false;
    $validatedByLabel = null;
    if (!$approvalReady) {
        $flashMessage = 'Debes completar todos los check de aprobación antes de validar el alistamiento de Embalaje.';
        $flashIsError = true;
    } else {
        $approvedUser = unibagFindAuthorizedUser($trzPdo, $approvalUsername, $approvalPassword, 'PRODUCTION', 1);
        if (!is_array($approvedUser)) {
            $flashMessage = 'Usuario o clave inválidos para validar la aprobación.';
            $flashIsError = true;
        } else {
            $validatedByLabel = trim((string)($approvedUser['display_name'] ?? '')) !== ''
                ? (string)$approvedUser['display_name'] . ' (' . (string)$approvedUser['username'] . ')'
                : (string)$approvedUser['username'];
            $approvalResult = $service->approveWorkOrderPackagingSetup(
                $workOrderId,
                $role,
                (string)$approvedUser['username'],
                trim((string)($approvedUser['display_name'] ?? ''))
            );
            if (($approvalResult['ok'] ?? false) !== true) {
                $flashMessage = implode(' ', array_map('strval', array_values($approvalResult['errors'] ?? [])));
                $flashIsError = true;
            } else {
                header('Location: /work-orders/' . $workOrderId . '/packaging/setup?setup_approved=1&approved_by=' . urlencode($validatedByLabel));
                exit;
            }
        }
    }

    renderWorkOrderPackagingSetupApprovalScreen(
        $ot,
        $outputRolls,
        $activeShiftSession,
        $helperOptions,
        $boxes,
        $pallets,
        $role,
        $flashMessage,
        $flashIsError,
        true,
        $approvalUsername,
        $validatedByLabel
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing/setup-event/finish$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    try {
        $service->finishWorkOrderSealingSetupEvent(
            $workOrderId,
            (int)($_POST['start_event_id'] ?? 0),
            $currentOperatorName
        );
        header('Location: /work-orders/' . $workOrderId . '/sealing/setup-approval');
    } catch (Throwable $e) {
        header('Location: /work-orders/' . $workOrderId . '/sealing/setup?process_error=' . urlencode($e->getMessage()));
    }
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing/setup-approval$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
    if ($finishApproval === null) {
        header('Location: /work-orders/' . $workOrderId . '/finish-approval');
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['approved'])) {
        $approvedBy = trim((string)($_GET['approved_by'] ?? ''));
        $flashMessage = $approvedBy !== ''
            ? 'Alistamiento de Selladora validado por ' . $approvedBy . '.'
            : 'Alistamiento de Selladora validado correctamente.';
    } elseif (isset($_GET['approval_error'])) {
        $flashMessage = trim((string)$_GET['approval_error']);
        $flashIsError = true;
    }
    renderWorkOrderSealingSetupApprovalScreen(
        $ot,
        $outputRolls,
        $activeShiftSession,
        $helperOptions,
        (string)($_GET['role'] ?? 'SUPERVISOR'),
        $flashMessage,
        $flashIsError,
        false,
        '',
        isset($_GET['approved']) ? trim((string)($_GET['approved_by'] ?? '')) : null
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing/setup-approval/validate$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $role = (string)($_POST['role'] ?? 'SUPERVISOR');
    $approvalUsername = trim((string)($_POST['approval_username'] ?? ''));
    $approvalPassword = (string)($_POST['approval_password'] ?? '');
    $approvalReady = trim((string)($_POST['approval_ready'] ?? '0')) === '1';

    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }

    $flashMessage = null;
    $flashIsError = false;
    $validatedByLabel = null;
    if (!$approvalReady) {
        $flashMessage = 'Debes completar todos los check de aprobación antes de validar el alistamiento de Selladora.';
        $flashIsError = true;
    } else {
        $approvedUser = unibagFindAuthorizedUser($trzPdo, $approvalUsername, $approvalPassword, 'PRODUCTION', 1);
        if (!is_array($approvedUser)) {
            $flashMessage = 'Usuario o clave inválidos para validar la aprobación.';
            $flashIsError = true;
        } else {
            $validatedByLabel = trim((string)($approvedUser['display_name'] ?? '')) !== ''
                ? (string)$approvedUser['display_name'] . ' (' . (string)$approvedUser['username'] . ')'
                : (string)$approvedUser['username'];
            $approvalResult = $service->approveWorkOrderSealingSetup(
                $workOrderId,
                $role,
                (string)$approvedUser['username'],
                trim((string)($approvedUser['display_name'] ?? ''))
            );
            if (($approvalResult['ok'] ?? false) !== true) {
                $flashMessage = implode(' ', array_map('strval', array_values($approvalResult['errors'] ?? [])));
                $flashIsError = true;
            } else {
                header('Location: /work-orders/' . $workOrderId . '/sealing/setup?setup_approved=1&approved_by=' . urlencode($validatedByLabel));
                exit;
            }
        }
    }

    renderWorkOrderSealingSetupApprovalScreen(
        $ot,
        $outputRolls,
        $activeShiftSession,
        $helperOptions,
        $role,
        $flashMessage,
        $flashIsError,
        $flashIsError,
        $approvalUsername,
        $validatedByLabel
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/setup$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $status = (string)($ot['status'] ?? '');
    if ($status === 'CUTTING') {
        $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
        if ($service->getLastWorkOrderFinish($workOrderId) !== null && $finishApproval === null) {
            header('Location: /work-orders/' . $workOrderId . '/finish-approval');
        } else {
            header('Location: /work-orders/' . $workOrderId . '/start');
        }
        exit;
    }
    if ($status === 'CLOSED') {
        header('Location: /work-orders/' . $workOrderId . '/start?closed=1');
        exit;
    }
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $assignedCliches = $service->listClichesAssignedToWorkOrder($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    $processEvents = $service->listWorkOrderProcessEvents($workOrderId);
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['anilox_saved']) && $_GET['anilox_saved'] === '1') {
        $flashMessage = 'Configuración de anilox guardada correctamente.';
    } elseif (isset($_GET['machine_header_saved']) && $_GET['machine_header_saved'] === '1') {
        $flashMessage = 'Cabecera de máquina actualizada correctamente.';
    } elseif (isset($_GET['setup_approved']) && $_GET['setup_approved'] === '1') {
        $approvedBy = trim((string)($_GET['approved_by'] ?? ''));
        $flashMessage = $approvedBy !== ''
            ? 'Alistamiento terminado: la máquina quedó configurada para producción y fue validada por ' . $approvedBy . '.'
            : 'Alistamiento terminado: la máquina quedó configurada para producción.';
    } elseif (isset($_GET['production_started']) && $_GET['production_started'] === '1') {
        $flashMessage = 'Producción iniciada correctamente.';
    } elseif (isset($_GET['process_started']) && $_GET['process_started'] === '1') {
        $flashMessage = 'Evento de producción iniciado correctamente.';
    } elseif (isset($_GET['process_finished']) && $_GET['process_finished'] === '1') {
        $flashMessage = 'Evento de producción terminado correctamente.';
    } elseif (isset($_GET['process_error']) && trim((string)$_GET['process_error']) !== '') {
        $flashMessage = trim((string)$_GET['process_error']);
        $flashIsError = true;
    }
    renderWorkOrderSetupScreen($ot, $activeShiftSession, $currentOperatorName, $chemicalInputs, $boxes, $pallets, $currentRoll, $outputRoll, $assignedCliches, $processEvents, $flashMessage, $flashIsError);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/in-progress$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $status = (string)($ot['status'] ?? '');
    if ($status === 'CUTTING') {
        $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
        if ($service->getLastWorkOrderFinish($workOrderId) !== null && $finishApproval === null) {
            header('Location: /work-orders/' . $workOrderId . '/finish-approval');
        } else {
            header('Location: /work-orders/' . $workOrderId . '/start');
        }
        exit;
    }
    if ($status === 'CLOSED') {
        header('Location: /work-orders/' . $workOrderId . '/start?closed=1');
        exit;
    }
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $assignedCliches = $service->listClichesAssignedToWorkOrder($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    $processEvents = $service->listWorkOrderProcessEvents($workOrderId);
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['process_started']) && $_GET['process_started'] === '1') {
        $flashMessage = 'Evento de producción iniciado correctamente.';
    } elseif (isset($_GET['process_finished']) && $_GET['process_finished'] === '1') {
        $flashMessage = 'Evento de producción terminado correctamente.';
    } elseif (isset($_GET['process_error']) && trim((string)$_GET['process_error']) !== '') {
        $flashMessage = trim((string)$_GET['process_error']);
        $flashIsError = true;
    }
    renderWorkOrderInProgressScreen($ot, $activeShiftSession, $chemicalInputs, $boxes, $currentRoll, $outputRoll, $assignedCliches, $processEvents, $flashMessage, $flashIsError);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/setup-approval$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['approved'])) {
        $approvedBy = trim((string)($_GET['approved_by'] ?? ''));
        $flashMessage = $approvedBy !== ''
            ? 'Partida validada correctamente por ' . $approvedBy . '.'
            : 'Partida validada correctamente.';
    } elseif (isset($_GET['approval_error'])) {
        $flashMessage = trim((string)$_GET['approval_error']);
        $flashIsError = true;
    }
    renderWorkOrderSetupApprovalScreen(
        $ot,
        $activeShiftSession,
        $chemicalInputs,
        $currentRoll,
        $outputRoll,
        (string)($_GET['role'] ?? 'SUPERVISOR'),
        $flashMessage,
        $flashIsError,
        false,
        '',
        isset($_GET['approved']) ? trim((string)($_GET['approved_by'] ?? '')) : null
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/setup-approval/validate$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $role = (string)($_POST['role'] ?? 'SUPERVISOR');
    $approvalUsername = trim((string)($_POST['approval_username'] ?? ''));
    $approvalPassword = (string)($_POST['approval_password'] ?? '');
    $approvalReady = trim((string)($_POST['approval_ready'] ?? '0')) === '1';

    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }

    $flashMessage = null;
    $flashIsError = false;
    $validatedByLabel = null;
    if (!$approvalReady) {
        $flashMessage = 'Debes completar todos los check de aprobación antes de validar la partida.';
        $flashIsError = true;
    } else {
        $approvedUser = unibagFindAuthorizedUser($trzPdo, $approvalUsername, $approvalPassword, 'PRODUCTION', 1);
        if (!is_array($approvedUser)) {
            $flashMessage = 'Usuario o clave inválidos para validar la aprobación.';
            $flashIsError = true;
        } else {
            $validatedByLabel = trim((string)($approvedUser['display_name'] ?? '')) !== ''
                ? (string)$approvedUser['display_name'] . ' (' . (string)$approvedUser['username'] . ')'
                : (string)$approvedUser['username'];
            $approvalResult = $service->approveWorkOrderSetup(
                $workOrderId,
                $role,
                (string)$approvedUser['username'],
                trim((string)($approvedUser['display_name'] ?? ''))
            );
            if (($approvalResult['ok'] ?? false) !== true) {
                $flashMessage = implode(' ', array_map('strval', array_values($approvalResult['errors'] ?? [])));
                $flashIsError = true;
            } else {
                header('Location: /work-orders/' . $workOrderId . '/setup?setup_approved=1&approved_by=' . urlencode($validatedByLabel));
                exit;
            }
        }
    }

    renderWorkOrderSetupApprovalScreen(
        $ot,
        $activeShiftSession,
        $chemicalInputs,
        $currentRoll,
        $outputRoll,
        $role,
        $flashMessage,
        $flashIsError,
        $flashIsError,
        $approvalUsername,
        $validatedByLabel
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/finish-approval/validate$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $role = (string)($_POST['role'] ?? 'SUPERVISOR');
    $approvalUsername = trim((string)($_POST['approval_username'] ?? ''));
    $approvalPassword = (string)($_POST['approval_password'] ?? '');
    $printed = (string)($_POST['printed'] ?? '');
    $boxPrinted = (string)($_POST['box_printed'] ?? '');
    $labelRollId = (string)($_POST['label_roll_id'] ?? '');

    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
    $wastes = $service->listProductionWastesByWorkOrder($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }

    $flashMessage = null;
    $flashIsError = false;
    $validatedByLabel = null;
    $approvedUser = unibagFindAuthorizedUser($trzPdo, $approvalUsername, $approvalPassword, 'PRODUCTION', 1);
    if (!is_array($approvedUser)) {
        $flashMessage = 'Usuario o clave inválidos para validar el cierre de flexografía.';
        $flashIsError = true;
    } else {
        $validatedByLabel = trim((string)($approvedUser['display_name'] ?? '')) !== ''
            ? (string)$approvedUser['display_name'] . ' (' . (string)$approvedUser['username'] . ')'
            : (string)$approvedUser['username'];
        $approvalResult = $service->approveWorkOrderFinish(
            $workOrderId,
            $role,
            (string)$approvedUser['username'],
            trim((string)($approvedUser['display_name'] ?? ''))
        );
        if (($approvalResult['ok'] ?? false) !== true) {
            $flashMessage = implode(' ', array_map('strval', array_values($approvalResult['errors'] ?? [])));
            $flashIsError = true;
        } else {
            $qs = '?finish_approved=1&approved_by=' . urlencode($validatedByLabel);
            if ($printed !== '') {
                $qs .= '&printed=' . urlencode($printed);
            }
            if ($boxPrinted !== '') {
                $qs .= '&box_printed=' . urlencode($boxPrinted);
            }
            if ($labelRollId !== '') {
                $qs .= '&label_roll_id=' . urlencode($labelRollId);
            }
            header('Location: /work-orders/' . $workOrderId . '/sealing' . $qs);
            exit;
        }
    }

    renderWorkOrderFinishApprovalScreen(
        $ot,
        $activeShiftSession,
        $lastFinish,
        $rollHistory,
        $wastes,
        $outputRoll,
        $role,
        $flashMessage,
        $flashIsError,
        $approvalUsername,
        $printed,
        $boxPrinted,
        $labelRollId,
        $validatedByLabel
    );
    exit;
}

    return false;
}
