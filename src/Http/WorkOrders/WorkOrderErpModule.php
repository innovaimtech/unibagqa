<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Órdenes de Trabajo en ERP y Fichas Técnicas (src/Http/WorkOrders/WorkOrderErpModule.php)
// =============================================================================

require_once __DIR__ . '/AniloxClicheAssignModule.php';
require_once __DIR__ . '/SetupApprovalModule.php';

/**
 * Renderiza la pantalla de “alistamiento” (setup) de una OT.
 *
 * Integra información de:
 * - OT y turno activo
 * - bobina actual y bobina de salida
 * - tintas/pesajes
 * - pallets/cajas
 * - clichés asignados
 * - eventos de proceso (bitácora)
 *
 * Además, muestra mensajes flash opcionales (éxito/error).
 *
 * ---
 *
 * Renders the Work Order “setup” screen.
 *
 * It integrates:
 * - Work order and active shift session
 * - current roll and output roll
 * - inks/weighings
 * - pallets/boxes
 * - assigned cliches
 * - process events (audit log)
 *
 * Also shows optional flash messages (success/error).
 */
/**
 * Resuelve las medidas técnicas, cliente y materialidad de una OT combinando datos directos del ERP y texto descriptivo.
 *
 * @return array{width: string, height: string, gusset: string, material: string, client: string}
 */
function unibagResolveWorkOrderDimensionsAndClient(array $ot, string $sheetText = ''): array
{
    if ($sheetText === '') {
        $sheetText = trim((string)($ot['erp_plan_desc'] ?? '') . ' ' . (string)($ot['sku_final'] ?? ''));
    }

    $widthLabel = '-';
    $heightLabel = '-';
    $gussetLabel = '-';
    if (!empty($ot['fab_med_width']) && (float)$ot['fab_med_width'] > 0) {
        $widthLabel = (string)(float)$ot['fab_med_width'] . ' cm';
    }
    if (!empty($ot['fab_med_height']) && (float)$ot['fab_med_height'] > 0) {
        $heightLabel = (string)(float)$ot['fab_med_height'] . ' cm';
    }
    if (isset($ot['fab_med_fuelle'])) {
        $gussetVal = (float)$ot['fab_med_fuelle'];
        $gussetLabel = $gussetVal > 0 ? ((string)$gussetVal . ' cm') : '0 cm';
    }
    if ($widthLabel === '-' && preg_match('/(\d+(?:[.,]\d+)?)\s*[xX]\s*(\d+(?:[.,]\d+)?)(?:\s*[xX]\s*(\d+(?:[.,]\d+)?))?/', $sheetText, $dm) === 1) {
        $widthLabel = $dm[1] . ' cm';
        $heightLabel = $dm[2] . ' cm';
        $gussetLabel = trim((string)($dm[3] ?? '')) !== '' ? ($dm[3] . ' cm') : '-';
    }

    $materialLabel = trim((string)($ot['fab_type'] ?? ''));
    if ($materialLabel === '') {
        foreach (['PLA', 'BOPP', 'PP', 'PEBD', 'PEAD', 'PE'] as $mk) {
            if (stripos($sheetText, $mk) !== false) {
                $materialLabel = $mk;
                break;
            }
        }
        if ($materialLabel === '') {
            $materialLabel = '-';
        }
    }

    $clientLabel = trim((string)($ot['cust_name'] ?? $ot['erp_customer_name'] ?? ''));
    if ($clientLabel === '') {
        $clientLabel = '-';
    }

    return [
        'width' => $widthLabel,
        'height' => $heightLabel,
        'gusset' => $gussetLabel,
        'material' => $materialLabel,
        'client' => $clientLabel,
    ];
}

/**
 * Genera la matriz de filas para la tabla "Información Cliente" de órdenes de trabajo.
 * Rescata cliente, diseño, medidas técnicas, fechas de cliché e imágenes reales descargadas del ERP.
 */
function unibagBuildLegacyClientRows(
    array $ot,
    array $colorSlots,
    string $materialLabel,
    string $widthLabel,
    string $heightLabel,
    string $gussetLabel,
    string $targetQtyLabel,
    string $stationLabel
): array {
    $clientLabel = trim((string)($ot['cust_name'] ?? $ot['erp_customer_name'] ?? ''));
    if ($clientLabel === '') {
        $clientLabel = '-';
    }

    $clicheSolicLabel = !empty($ot['req_cliche_peli_solic_dat']) && (int)$ot['req_cliche_peli_solic_dat'] > 0
        ? date('d.m.Y', (int)$ot['req_cliche_peli_solic_dat'])
        : '-';
    $clicheRecepLabel = !empty($ot['req_cliche_peli_recep_dat']) && (int)$ot['req_cliche_peli_recep_dat'] > 0
        ? date('d.m.Y', (int)$ot['req_cliche_peli_recep_dat'])
        : '-';

    // Imagen Diseño
    $designImageHash = trim((string)($ot['fab_design_imagehash'] ?? ''));
    if ($designImageHash !== '' && $designImageHash !== 'dummy') {
        $imgUrl = '/docs/order/' . rawurlencode($designImageHash);
        $designHtml = '<div style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap">'
            . '<a href="' . h($imgUrl) . '" target="_blank" title="Clic para ampliar foto de diseño">'
            . '<img src="' . h($imgUrl) . '" alt="Diseño" style="max-height:75px;max-width:115px;object-fit:contain;border:1px solid #cbd5e1;border-radius:6px;background:#fff;vertical-align:middle;box-shadow:0 1px 3px rgba(0,0,0,0.12)">'
            . '</a>'
            . '<a class="btn" href="' . h($imgUrl) . '" target="_blank" style="padding:5px 12px;font-size:12px;font-weight:700;background:#00A9A6;color:#fff;text-decoration:none;border-radius:4px;display:inline-flex;align-items:center;gap:5px">'
            . '🔍 Ver Foto Completa'
            . '</a>'
            . '</div>';
    } else {
        $designHtml = '<span style="color:#94a3b8;font-size:12px">Sin imagen de diseño</span>';
    }

    // Adjuntos de producción (req_prod_adjfile_0 a 4)
    $attachments = [];
    for ($x = 0; $x < 5; $x++) {
        $adj = trim((string)($ot["req_prod_adjfile_{$x}"] ?? ''));
        if ($adj !== '') {
            $comment = trim((string)($ot["req_prod_adjcomments_{$x}"] ?? ''));
            $adjUrl = '/docs/prod/' . rawurlencode($adj);
            $isImg = unibagAttachmentIsImage($adj);
            $btnText = $isImg ? '📷 Ver Adjunto #' . ($x + 1) : '📄 Descargar #' . ($x + 1);

            $adjHtml = '<div style="display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap">';
            if ($isImg) {
                $adjHtml .= '<a href="' . h($adjUrl) . '" target="_blank" title="Clic para ampliar">'
                    . '<img src="' . h($adjUrl) . '" alt="Adjunto ' . ($x + 1) . '" style="max-height:55px;max-width:85px;object-fit:contain;border:1px solid #cbd5e1;border-radius:4px;background:#fff;vertical-align:middle">'
                    . '</a>';
            }
            $adjHtml .= '<a class="btn secondary" href="' . h($adjUrl) . '" target="_blank" style="padding:4px 10px;font-size:12px;text-decoration:none;border-radius:4px">'
                . h($btnText) . '</a>';
            if ($comment !== '') {
                $adjHtml .= '<span style="font-size:11.5px;color:#475569">(' . h($comment) . ')</span>';
            }
            $adjHtml .= '</div>';
            $attachments[] = ['label' => 'Imagen #' . ($x + 1), 'html' => $adjHtml];
        }
    }

    $firstAdjLabel = 'Imagen #5';
    $firstAdjHtml = '<span style="color:#94a3b8;font-size:12px">-</span>';
    if ($attachments !== []) {
        $first = array_shift($attachments);
        $firstAdjLabel = $first['label'];
        $firstAdjHtml = $first['html'];
    }

    $rows = [
        ['N° OT', h((string)$ot['ot_code']), 'Color N° 1', h((string)$colorSlots[0])],
        ['Cliente', h($clientLabel), 'Color N° 2', h((string)$colorSlots[1])],
        ['N° CC', trim((string)($ot['erp_req_id'] ?? '')) !== '' ? h((string)$ot['erp_req_id']) : '-', 'Color N° 3', h((string)$colorSlots[2])],
        ['Diseño', trim((string)($ot['erp_plan_desc'] ?? '')) !== '' ? h((string)$ot['erp_plan_desc']) : '-', 'Color N° 4', h((string)$colorSlots[3])],
        ['Producto', h((string)$ot['sku_final']), 'Color N° 5', h((string)$colorSlots[4])],
        ['Materialidad', h($materialLabel), 'Color N° 6', h((string)$colorSlots[5])],
        ['Ancho', h($widthLabel), 'Alto', h($heightLabel)],
        ['Fuelle', h($gussetLabel), 'Cantidad de Bolsas', h($targetQtyLabel)],
        ['Fecha de Entrega', trim((string)($ot['erp_plan_date'] ?? '')) !== '' ? h(formatLabelDate((string)$ot['erp_plan_date'])) : '-', 'Maquina', h($stationLabel)],
        ['Fecha de solicitud de cliché', h($clicheSolicLabel), 'Fecha de recepción de cliché', h($clicheRecepLabel)],
        ['Imagen Diseño', $designHtml, $firstAdjLabel, $firstAdjHtml],
    ];

    foreach ($attachments as $att) {
        $rows[] = [$att['label'], $att['html'], '', ''];
    }

    return $rows;
}


/**
 * Renderiza la pantalla de “solicitud de materiales” para una OT.
 *
 * Muestra:
 * - Resumen de OT (medidas/objetivo/máquina).
 * - Materiales disponibles (bobinas) y solicitudes (work_order_material_requests).
 * - Acciones para solicitar/aceptar/entregar, según estado y permisos.
 * - Secciones complementarias: tintas, cajas, clichés, etc.
 *
 * ---
 *
 * Renders the “materials request” screen for a Work Order.
 *
 * Shows:
 * - Work order summary (dimensions/target/machine).
 * - Available materials (rolls) and requests (work_order_material_requests).
 * - Actions to request/accept/deliver based on status and permissions.
 * - Complementary sections: inks, boxes, cliches, etc.
 */
function renderWorkOrderRequestMaterialsScreen(
    array $ot,
    array $chemicals,
    ?array $currentRoll,
    array $chemicalInputs,
    array $materialRequests,
    array $availableMaterialRolls,
    array $boxes,
    ?array $activeShiftSession = null,
    array $assignedCliches = [],
    ?array $outputRoll = null,
    ?string $flashMessage = null,
    bool $flashIsError = false,
    array $formState = []
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
            'shift_label' => (string)($ot['shift_shift_label'] ?? ''),
            'started_at' => (string)($ot['shift_started_at'] ?? ''),
            'comments' => (string)($ot['shift_comments'] ?? ''),
            'process_stage' => (string)($ot['shift_process_stage'] ?? ''),
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
    $colorSlots = array_pad(array_slice($detectedColors, 0, 6), 6, '-');
    $aniloxCatalog = $service instanceof ReceptionService ? $service->listAniloxCatalog() : [];
    $savedAniloxAssignments = $service instanceof ReceptionService ? $service->getWorkOrderAniloxAssignments((int)$ot['id']) : [];
    $aniloxSlots = buildWorkOrderAniloxSlots($colorSlots, $chemicalInputs, $savedAniloxAssignments, null);
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
    $saldoTotalLabel = $targetQtyNumber > 0 ? number_format($saldoTotalUnits, 0, '.', '.') . ' unidades' : '-';
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

    $body = '<div class="legacy-screen-title">Solicitar materiales</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Solicitar materiales</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/setup">Volver a alistamiento</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $requestFilter = strtoupper(trim((string)($formState['request_filter'] ?? ($_GET['request_filter'] ?? 'ROLL'))));
    if (!in_array($requestFilter, ['ROLL', 'CHEMICAL', 'OTHER'], true)) {
        $requestFilter = 'ROLL';
    }
    $requestFilterLabels = [
        'ROLL' => 'Bobinas',
        'CHEMICAL' => 'Tintas',
        'OTHER' => 'Otros',
    ];
    $service = $GLOBALS['service'] ?? null;
    $rollPlanning = $service instanceof ReceptionService
        ? $service->getRollRequestPlanningForWorkOrder((int)$ot['id'])
        : ['required_meters' => 0, 'suggested_roll_qty' => 0, 'suggested_group_key' => '', 'suggested_group_label' => '', 'hint' => [], 'config' => ['buffer_percent' => 5]];
    $defaultRollGroupKey = (string)($formState['requested_group_key'] ?? ($rollPlanning['suggested_group_key'] ?? ''));
    $defaultRequestedMeters = (string)($formState['requested_meters'] ?? (((float)($rollPlanning['required_meters'] ?? 0)) > 0 ? (string)$rollPlanning['required_meters'] : ''));
    $defaultRequestedQty = (string)($formState['requested_qty'] ?? (((int)($rollPlanning['suggested_roll_qty'] ?? 0)) > 0 ? (string)$rollPlanning['suggested_roll_qty'] : '1'));
    $filteredMaterialRequests = array_values(array_filter(
        $materialRequests,
        static fn(array $request): bool => strtoupper(trim((string)($request['request_type'] ?? 'ROLL'))) === $requestFilter
    ));

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Informacion Cliente</th></tr></thead><tbody>';
    foreach ($legacyClientRows as $legacyRow) {
        $body .= '<tr><td class="legacy-label-cell">' . h((string)$legacyRow[0]) . '</td><td class="legacy-value-cell">' . $legacyRow[1] . '</td><td class="legacy-label-cell">' . h((string)$legacyRow[2]) . '</td><td class="legacy-value-cell">' . $legacyRow[3] . '</td></tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Información de Fabricación</th></tr></thead><tbody>';
    foreach ($legacyFabricationRows as $legacyRow) {
        if (trim((string)$legacyRow[0]) === '' && trim((string)$legacyRow[1]) === '' && trim((string)$legacyRow[2]) === '' && trim((string)$legacyRow[3]) === '') {
            $body .= '<tr><td class="legacy-value-cell" colspan="4" style="background:#f3f4f6;padding:8px"></td></tr>';
            continue;
        }
        $body .= '<tr><td class="legacy-label-cell">' . h((string)$legacyRow[0]) . '</td><td class="legacy-value-cell">' . $legacyRow[1] . '</td><td class="legacy-label-cell">' . h((string)$legacyRow[2]) . '</td><td class="legacy-value-cell">' . $legacyRow[3] . '</td></tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= '<div class="row" style="gap:8px;flex-wrap:wrap;margin-bottom:12px">';
    foreach ($requestFilterLabels as $filterKey => $filterLabel) {
        $buttonClass = $requestFilter === $filterKey ? 'btn' : 'btn secondary';
        $body .= '<button type="button" class="' . $buttonClass . '" data-request-filter-tab="' . h($filterKey) . '" data-active-class="btn" data-inactive-class="btn secondary" style="min-width:120px;text-align:center">' . h($filterLabel) . '</button>';
    }
    $body .= '</div>';

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Solicitudes a bodega</th></tr></thead><tbody>';
    $rollPlanningHint = is_array($rollPlanning['hint'] ?? null) ? $rollPlanning['hint'] : [];
    $rollPlanningSummary = [];
    $rollPlanningSource = trim((string)($rollPlanningHint['required_meters_source'] ?? ''));
    if ((string)($rollPlanning['suggested_group_label'] ?? '') !== '') {
        $rollPlanningSummary[] = 'Sugerida: ' . (string)$rollPlanning['suggested_group_label'];
    }
    if ((float)($rollPlanning['required_meters'] ?? 0) > 0) {
        $rollPlanningSummary[] = 'ML ERP: ' . rtrim(rtrim(number_format((float)$rollPlanning['required_meters'], 3, '.', ''), '0'), '.') . ($rollPlanningSource !== '' ? ' (' . $rollPlanningSource . ')' : '');
    }
    if ((int)($rollPlanning['suggested_roll_qty'] ?? 0) > 0) {
        $rollPlanningSummary[] = 'Bobinas estimadas: ' . (string)$rollPlanning['suggested_roll_qty'];
    }
    $body .= '<tr data-request-filter-panel="ROLL"' . ($requestFilter === 'ROLL' ? '' : ' style="display:none"') . '><td class="legacy-label-cell">Bobinas</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/material-request"><input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><input type="hidden" name="request_type" value="ROLL"><input type="hidden" name="request_filter" value="ROLL"><input type="hidden" name="return_context" value="REQUEST_WINDOW"><table class="legacy-sheet-table" style="margin:0"><tbody>';
    $body .= '<tr><td class="legacy-label-cell">Planeación</td><td class="legacy-value-cell" colspan="3"><div style="display:flex;flex-direction:column;gap:4px"><div class="muted">El sistema recomienda la bobina más cercana a las medidas de la OT y calcula la cantidad usando el campo oficial de ML del ERP cuando esté mapeado, junto con el parámetro de holgura de ' . h((string)($rollPlanning['config']['buffer_percent'] ?? 5)) . '%.</div>';
    if ($rollPlanningSummary !== []) {
        $body .= '<div style="font-weight:700">' . h(implode(' | ', $rollPlanningSummary)) . '</div>';
    } else {
        $body .= '<div class="muted">Todavía no hay ML oficiales mapeados desde ERP para esta OT; puedes informarlos manualmente mientras terminamos el cruce.</div>';
    }
    if ((float)($rollPlanningHint['width_mm'] ?? 0) > 0 || trim((string)($rollPlanningHint['material'] ?? '')) !== '') {
        $body .= '<div class="muted">Referencia OT: ancho ' . h((string)($rollPlanningHint['width_mm'] ?? '-')) . ' mm, material ' . h((string)($rollPlanningHint['material'] ?? '-')) . ', color ' . h((string)($rollPlanningHint['color'] ?? '-')) . '.</div>';
    }
    $body .= '</div></td></tr>';
    $body .= '<tr><td class="legacy-label-cell">Tipo de bobina</td><td class="legacy-value-cell" colspan="3"><select name="requested_group_key" required data-roll-request-group><option value="">Seleccionar material</option>';
    foreach ($availableMaterialRolls as $availableRoll) {
        $selected = ($defaultRollGroupKey === (string)$availableRoll['group_key']) ? ' selected' : '';
        $body .= '<option value="' . h((string)$availableRoll['group_key']) . '"' . $selected . ' data-roll-meters="' . h((string)($availableRoll['meters'] ?? '')) . '" data-roll-qty="' . h((string)($availableRoll['available_qty'] ?? '0')) . '">' . h(materialRequestGroupLabel($availableRoll)) . '</option>';
    }
    $body .= '</select></td></tr><tr><td class="legacy-label-cell">Metros lineales</td><td class="legacy-value-cell"><input name="requested_meters" type="number" step="0.001" min="0" value="' . h($defaultRequestedMeters) . '" placeholder="Ej: 2500" data-roll-request-meters></td><td class="legacy-label-cell">Bobinas estimadas</td><td class="legacy-value-cell"><input name="requested_qty" type="number" step="1" min="1" value="' . h($defaultRequestedQty) . '" required readonly data-roll-request-qty><div class="muted" data-roll-request-summary style="margin-top:6px"></div></td></tr><tr><td class="legacy-label-cell">Nota</td><td class="legacy-value-cell" colspan="3"><input name="request_notes" type="text" value="' . h((string)($formState['request_notes'] ?? '')) . '" placeholder="Observaciones para bodega"></td></tr><tr><td class="legacy-label-cell">Acción</td><td class="legacy-value-cell" colspan="3"><button class="btn" type="submit">Solicitar bobinas</button></td></tr></tbody></table></form></td></tr>';
    $body .= '<tr data-request-filter-panel="CHEMICAL"' . ($requestFilter === 'CHEMICAL' ? '' : ' style="display:none"') . '><td class="legacy-label-cell">Tintas</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/material-request"><input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><input type="hidden" name="request_type" value="CHEMICAL"><input type="hidden" name="request_filter" value="CHEMICAL"><input type="hidden" name="return_context" value="REQUEST_WINDOW"><table class="legacy-sheet-table" style="margin:0"><tbody><tr><td class="legacy-label-cell">Tinta</td><td class="legacy-value-cell" colspan="3"><select name="chemical_id" required><option value="">Seleccionar tinta</option>';
    foreach ($chemicals as $chemical) {
        $selected = ((string)($formState['chemical_request_id'] ?? '') === (string)$chemical['id']) ? ' selected' : '';
        $body .= '<option value="' . (int)$chemical['id'] . '"' . $selected . '>' . h((string)$chemical['code']) . ' - ' . h((string)$chemical['name']) . '</option>';
    }
    $body .= '</select></td></tr><tr><td class="legacy-label-cell">Cantidad</td><td class="legacy-value-cell"><input name="requested_qty" type="number" step="0.001" min="0.001" value="' . h((string)($formState['chemical_requested_qty'] ?? '1')) . '" required></td><td class="legacy-label-cell">Unidad</td><td class="legacy-value-cell"><select name="requested_unit"><option value="Kg">Kg</option><option value="Lt">Lt</option><option value="Unid.">Unid.</option></select></td></tr><tr><td class="legacy-label-cell">Nota</td><td class="legacy-value-cell" colspan="3"><input name="request_notes" type="text" value="' . h((string)($formState['chemical_request_notes'] ?? '')) . '" placeholder="Ej: para mezcla inicial o reposición"></td></tr><tr><td class="legacy-label-cell">Acción</td><td class="legacy-value-cell" colspan="3"><button class="btn" type="submit">Solicitar tinta</button></td></tr></tbody></table></form></td></tr>';
    $body .= '<tr data-request-filter-panel="OTHER"' . ($requestFilter === 'OTHER' ? '' : ' style="display:none"') . '><td class="legacy-label-cell">Otros insumos</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/material-request"><input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><input type="hidden" name="request_type" value="OTHER"><input type="hidden" name="request_filter" value="OTHER"><input type="hidden" name="return_context" value="REQUEST_WINDOW"><table class="legacy-sheet-table" style="margin:0"><tbody><tr><td class="legacy-label-cell">Material o insumo</td><td class="legacy-value-cell" colspan="3"><input name="requested_item" type="text" value="' . h((string)($formState['requested_item'] ?? '')) . '" placeholder="Ej: tinta azul, cajas, bolsas, aditivo" required></td></tr><tr><td class="legacy-label-cell">Cantidad</td><td class="legacy-value-cell"><input name="requested_qty" type="number" step="0.001" min="0.001" value="' . h((string)($formState['other_requested_qty'] ?? '1')) . '" required></td><td class="legacy-label-cell">Unidad</td><td class="legacy-value-cell"><input name="requested_unit" type="text" value="' . h((string)($formState['other_requested_unit'] ?? 'Unid.')) . '" placeholder="Ej: Unid., Kg, Lt"></td></tr><tr><td class="legacy-label-cell">Nota</td><td class="legacy-value-cell" colspan="3"><input name="request_notes" type="text" value="' . h((string)($formState['other_request_notes'] ?? '')) . '" placeholder="Observaciones para bodega"></td></tr><tr><td class="legacy-label-cell">Acción</td><td class="legacy-value-cell" colspan="3"><button class="btn" type="submit">Solicitar insumo</button></td></tr></tbody></table></form></td></tr>';
    $body .= '</tbody></table></div>';

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Solicitudes de la OT</th></tr></thead><tbody>';
    $body .= '<tr><td class="legacy-label-cell">Resumen</td><td class="legacy-value-cell" colspan="3">Solicitudes registradas para <span data-request-filter-summary-label>' . h(strtolower($requestFilterLabels[$requestFilter])) . '</span>.</td></tr>';
    $body .= '<tr><td class="legacy-label-cell">Solicitudes OT</td><td class="legacy-value-cell" colspan="3"><div class="table-wrap"><table class="table-compact"><thead><tr><th>Tipo</th><th>Material solicitado</th><th>ML</th><th>Cant.</th><th>Entregadas</th><th>Estado</th><th>Última entrega</th></tr></thead><tbody>';
    foreach ($materialRequests as $request) {
        $deliveredRoll = trim((string)($request['delivered_roll_code'] ?? ''));
        $requestTypeValue = strtoupper(trim((string)($request['request_type'] ?? 'ROLL')));
        $requestedMetersLabel = (float)($request['requested_meters'] ?? 0) > 0
            ? rtrim(rtrim(number_format((float)$request['requested_meters'], 3, '.', ''), '0'), '.')
            : '-';
        $body .= '<tr data-request-filter-row="' . h($requestTypeValue) . '"' . ($requestTypeValue === $requestFilter ? '' : ' style="display:none"') . '><td>' . h(materialRequestTypeLabel((string)($request['request_type'] ?? 'ROLL'))) . '</td><td>' . h((string)$request['requested_item']) . '</td><td>' . h($requestedMetersLabel) . '</td><td>' . h(formatReceptionValue((float)($request['requested_qty'] ?? 0), (string)($request['requested_unit'] ?? 'Unid.'))) . '</td><td>' . h(formatReceptionValue((float)($request['delivered_qty'] ?? 0), (string)($request['requested_unit'] ?? 'Unid.'))) . '</td><td>' . h(materialRequestStatusLabel((string)$request['status'])) . '</td><td>' . h($deliveredRoll !== '' ? $deliveredRoll : (string)($request['delivered_by'] ?? '-')) . '</td></tr>';
    }
    $body .= '<tr data-request-filter-empty="' . h($requestFilter) . '"' . ($filteredMaterialRequests === [] ? '' : ' style="display:none"') . '><td colspan="7" class="muted">Sin solicitudes de <span data-request-filter-empty-label>' . h(strtolower($requestFilterLabels[$requestFilter])) . '</span> todavía.</td></tr>';
    $body .= '</tbody></table></div></td></tr></tbody></table></div>';
    $requestFilterJson = json_encode($requestFilterLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $body .= '<script>
        (function () {
            var labels = ' . ($requestFilterJson !== false ? $requestFilterJson : '{"ROLL":"Bobinas","CHEMICAL":"Tintas","OTHER":"Otros"}') . ';
            var buttons = document.querySelectorAll("[data-request-filter-tab]");
            var panels = document.querySelectorAll("[data-request-filter-panel]");
            var rows = document.querySelectorAll("[data-request-filter-row]");
            var summary = document.querySelector("[data-request-filter-summary-label]");
            var emptyRow = document.querySelector("[data-request-filter-empty]");
            var emptyLabel = document.querySelector("[data-request-filter-empty-label]");
            var rollGroup = document.querySelector("[data-roll-request-group]");
            var rollMeters = document.querySelector("[data-roll-request-meters]");
            var rollQty = document.querySelector("[data-roll-request-qty]");
            var rollSummary = document.querySelector("[data-roll-request-summary]");
            var bufferPercent = ' . json_encode((float)($rollPlanning['config']['buffer_percent'] ?? 5), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';

            function setFilter(filterKey) {
                var visibleRows = 0;
                buttons.forEach(function (button) {
                    var activeClass = button.getAttribute("data-active-class") || "btn";
                    var inactiveClass = button.getAttribute("data-inactive-class") || "btn secondary";
                    button.className = button.getAttribute("data-request-filter-tab") === filterKey ? activeClass : inactiveClass;
                });
                panels.forEach(function (panel) {
                    panel.style.display = panel.getAttribute("data-request-filter-panel") === filterKey ? "" : "none";
                });
                rows.forEach(function (row) {
                    var matches = row.getAttribute("data-request-filter-row") === filterKey;
                    row.style.display = matches ? "" : "none";
                    if (matches) {
                        visibleRows++;
                    }
                });
                if (summary) {
                    summary.textContent = (labels[filterKey] || "").toLowerCase();
                }
                if (emptyLabel) {
                    emptyLabel.textContent = (labels[filterKey] || "").toLowerCase();
                }
                if (emptyRow) {
                    emptyRow.style.display = visibleRows === 0 ? "" : "none";
                    emptyRow.setAttribute("data-request-filter-empty", filterKey);
                }
            }

            buttons.forEach(function (button) {
                button.addEventListener("click", function () {
                    setFilter(button.getAttribute("data-request-filter-tab"));
                });
            });

            function syncRollPlanning() {
                if (!rollGroup || !rollMeters || !rollQty) {
                    return;
                }
                var selected = rollGroup.options[rollGroup.selectedIndex];
                var rollLinearMeters = selected ? parseFloat(selected.getAttribute("data-roll-meters") || "0") : 0;
                var availableQty = selected ? parseFloat(selected.getAttribute("data-roll-qty") || "0") : 0;
                var requiredMeters = parseFloat(rollMeters.value || "0");
                if (!Number.isFinite(requiredMeters) || requiredMeters < 0) {
                    requiredMeters = 0;
                }
                if (!Number.isFinite(rollLinearMeters) || rollLinearMeters <= 0 || requiredMeters <= 0) {
                    rollQty.value = rollQty.value && parseFloat(rollQty.value) > 0 ? rollQty.value : "1";
                    if (rollSummary) {
                        rollSummary.textContent = availableQty > 0 ? "Disponibles en bodega: " + availableQty : "";
                    }
                    return;
                }
                var estimated = Math.max(1, Math.ceil((requiredMeters * (1 + (bufferPercent / 100))) / rollLinearMeters));
                rollQty.value = String(estimated);
                if (rollSummary) {
                    rollSummary.textContent = "Cada bobina aporta aprox. " + rollLinearMeters + " ML. Disponibles: " + availableQty + ".";
                }
            }

            if (rollGroup) {
                rollGroup.addEventListener("change", syncRollPlanning);
            }
            if (rollMeters) {
                rollMeters.addEventListener("input", syncRollPlanning);
            }
            syncRollPlanning();
        })();
    </script>';

    render('Solicitar materiales', $body);
}

/**
 * Renderiza la pantalla de OT “en curso”.
 *
 * Se usa durante la producción para:
 * - Ver datos principales de la OT y su turno.
 * - Registrar/autogestionar acciones operativas (según UI): autocontrol, avances,
 *   movimientos de bobinas, etc.
 * - Visualizar eventos y estado consolidado.
 *
 * ---
 *
 * Renders the “in progress” Work Order screen.
 *
 * Used during production to:
 * - View work order and shift core data.
 * - Perform operational actions (as per UI): self-check, progress, roll movements, etc.
 * - Visualize events and consolidated status.
 */
function renderWorkOrderInProgressScreen(
    array $ot,
    ?array $activeShiftSession,
    array $chemicalInputs = [],
    array $boxes = [],
    ?array $currentRoll = null,
    ?array $outputRoll = null,
    array $assignedCliches = [],
    array $processEvents = [],
    ?string $flashMessage = null,
    bool $flashIsError = false
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
            'shift_label' => (string)($ot['shift_shift_label'] ?? ''),
            'started_at' => (string)($ot['shift_started_at'] ?? ''),
            'comments' => (string)($ot['shift_comments'] ?? ''),
            'process_stage' => (string)($ot['shift_process_stage'] ?? ''),
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
    $clientLabel = $dim['client'];
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

    $body = '<div class="legacy-screen-title">En curso</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; En curso</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/setup">Volver</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><tbody>'
        . '<tr><td class="legacy-label-cell">N° OT</td><td class="legacy-value-cell">' . h((string)$ot['ot_code']) . '</td><td class="legacy-label-cell">N° CC</td><td class="legacy-value-cell">' . h($reqLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cliente</td><td class="legacy-value-cell">' . h($clientLabel) . '</td><td class="legacy-label-cell">Producto</td><td class="legacy-value-cell">' . h($productLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Medidas</td><td class="legacy-value-cell">' . h($measuresLabel) . '</td><td class="legacy-label-cell">Fuelle</td><td class="legacy-value-cell">' . h($gussetLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Area</td><td class="legacy-value-cell">' . h($measuresLabel) . '</td><td class="legacy-label-cell">Tela</td><td class="legacy-value-cell">' . h($fabricLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Manillas</td><td class="legacy-value-cell">' . h($handlesLabel) . '</td><td class="legacy-label-cell">Colores</td><td class="legacy-value-cell">' . h($colorsLabel) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad</td><td class="legacy-value-cell">' . h($targetQtyLabel) . '</td><td class="legacy-label-cell">Máquina</td><td class="legacy-value-cell">' . h($stationLabel) . '</td></tr>'
        . '</tbody></table></div>';

    $body .= '<div class="autocontrol-panel" id="autocontrol-current"><div class="autocontrol-panel-header">AUTOCONTROL</div><table class="autocontrol-table"><tbody>';
    for ($rowIndex = 0; $rowIndex < count($autocontrolItems); $rowIndex += 4) {
        $body .= '<tr>';
        foreach (array_slice($autocontrolItems, $rowIndex, 4) as $autocontrolItem) {
            $checked = $autocontrolItem === 'Caras Impresión' ? ' checked' : '';
            $body .= '<td><label class="autocontrol-checkline">'
                . '<input type="checkbox" data-autocontrol-current="1"' . $checked . '>'
                . '<span>' . h($autocontrolItem) . '</span>'
                . '</label></td>';
        }
        $body .= '</tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= '<div class="approval-actions" id="autocontrol-notify-actions">'
        . '<a class="approval-action-btn area hidden" id="notify-area-btn" href="/work-orders/' . (int)$ot['id'] . '/setup-approval?role=LEADER">Informar Líder de Área</a>'
        . '<a class="approval-action-btn supervisor hidden" id="notify-supervisor-btn" href="/work-orders/' . (int)$ot['id'] . '/setup-approval?role=SUPERVISOR">Informar supervisor</a>'
        . '</div>';

    $body .= '<div class="legacy-setup-actions" style="grid-template-columns:repeat(3,minmax(0,1fr))">'
        . '<a class="legacy-setup-action materials active" href="/work-orders/' . (int)$ot['id'] . '/materials">Utilizar materiales</a>'
        . '<button class="legacy-setup-action maintenance" type="button" data-progress-modal-open="maintenance" style="border:0">Registrar mantención</button>'
        . '<button class="legacy-setup-action pause" type="button" data-progress-modal-open="pause" style="border:0">Pausa</button>'
        . '</div>';

    $modalBaseStyle = 'display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto';
    $modalCardStyle = 'max-width:720px;margin:20px auto;background:#f4f5f7;border:1px solid #d6d6d6;border-radius:8px;padding:16px 18px;box-shadow:0 20px 45px rgba(15,23,42,.22)';
    $body .= '<div class="legacy-inline-modal" id="progress-modal-maintenance" style="' . $modalBaseStyle . '">'
        . '<div style="' . $modalCardStyle . '">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Registrar mantención</div><button type="button" class="btn secondary" data-progress-modal-close="maintenance">Cerrar</button></div>'
        . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/production-event/start">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="event_key" value="MAINTENANCE">'
        . '<input type="hidden" name="return_screen" value="IN_PROGRESS">'
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
        . '<input type="hidden" name="return_screen" value="IN_PROGRESS">'
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
            function syncAutocontrolActions() {
                var checks = Array.prototype.slice.call(document.querySelectorAll("[data-autocontrol-current]"));
                if (!checks.length) {
                    return;
                }
                var allChecked = checks.every(function (check) { return check.checked; });
                ["notify-area-btn", "notify-supervisor-btn"].forEach(function (id) {
                    var button = document.getElementById(id);
                    if (!button) {
                        return;
                    }
                    button.classList.toggle("hidden", !allChecked);
                });
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
            document.querySelectorAll("[data-autocontrol-current]").forEach(function (check) {
                check.addEventListener("change", syncAutocontrolActions);
            });
            syncAutocontrolActions();
        })();
    </script>';

    render('En curso', $body);
}


function buildWorkOrderSealingDisplayData(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = []
): array {
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
    } elseif (is_array($activeShiftSession) && $activeShiftSession !== []) {
        $sessionForDisplay = $activeShiftSession;
    }

    $sheetText = trim((string)($ot['erp_plan_desc'] ?? '') . ' ' . (string)($ot['sku_final'] ?? ''));
    $targetQtyLabel = trim((string)($ot['erp_target_qty'] ?? '')) !== ''
        ? (string)$ot['erp_target_qty']
        : (trim((string)($ot['target_qty'] ?? '')) !== '' ? (string)$ot['target_qty'] : '-');
    $dim = unibagResolveWorkOrderDimensionsAndClient($ot, $sheetText);
    $clientLabel = $dim['client'];
    $requestLabel = trim((string)($ot['erp_req_id'] ?? '')) !== '' ? (string)$ot['erp_req_id'] : '-';
    $stationLabel = trim((string)($sessionForDisplay['machine_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['machine_name']
        : (trim((string)($ot['erp_machine_label'] ?? '')) !== '' ? (string)$ot['erp_machine_label'] : 'Pendiente');
    $widthLabel = $dim['width'];
    $heightLabel = $dim['height'];
    $gussetLabel = $dim['gusset'];
    $materialLabel = '-';
    foreach (['PLA', 'BOPP', 'PP', 'PEBD', 'PEAD', 'PE'] as $materialKeyword) {
        if (stripos($sheetText, $materialKeyword) !== false) {
            $materialLabel = $materialKeyword;
            break;
        }
    }
    $colorLabel = '-';
    foreach (['Natural', 'Blanco', 'Beige', 'Azul', 'Rojo', 'Verde', 'Negro', 'Transparente', 'Amarillo', 'Rosado'] as $colorKeyword) {
        if (stripos($sheetText, $colorKeyword) !== false) {
            $colorLabel = $colorKeyword;
            break;
        }
    }
    $helperLabel = trim((string)($sessionForDisplay['helper_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['helper_name']
        : '-';
    $measureCompact = '-';
    if ($widthLabel !== '-' && $heightLabel !== '-') {
        $measureCompact = $widthLabel . 'x' . $heightLabel . ($gussetLabel !== '-' ? 'x' . $gussetLabel : '');
    }
    $measureLabel = trim((string)($ot['erp_plan_desc'] ?? '')) !== ''
        ? trim((string)$ot['erp_plan_desc']) . ($measureCompact !== '-' ? ' ' . $measureCompact : '')
        : ((string)$ot['sku_final'] !== '' ? (string)$ot['sku_final'] . ($measureCompact !== '-' ? ' ' . $measureCompact : '') : $measureCompact);
    $helperOptions = array_values(array_unique(array_filter(array_map(
        static fn($value): string => trim((string)$value),
        $helperOptions
    ), static fn(string $value): bool => $value !== '')));
    natcasesort($helperOptions);

    return [
        'session' => $sessionForDisplay,
        'target_qty_label' => $targetQtyLabel,
        'client_label' => $clientLabel,
        'request_label' => $requestLabel,
        'station_label' => $stationLabel,
        'width_label' => $widthLabel,
        'height_label' => $heightLabel,
        'gusset_label' => $gussetLabel,
        'material_label' => $materialLabel,
        'color_label' => $colorLabel,
        'helper_label' => $helperLabel,
        'measure_compact' => $measureCompact,
        'measure_label' => $measureLabel,
        'helper_options' => array_values($helperOptions),
        'product_label' => trim((string)($ot['erp_plan_desc'] ?? '')) !== '' ? (string)$ot['erp_plan_desc'] : (string)$ot['sku_final'],
        'output_roll_count' => count($outputRolls),
    ];
}

function renderWorkOrderSealingOverviewHtml(array $ot, array $display): string
{
    $body = '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Información de Fabricación</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">N° O.T.</td><td class="legacy-value-cell">' . h((string)$ot['ot_code']) . '</td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
        . '<tr><td class="legacy-label-cell">Cliente</td><td class="legacy-value-cell">' . h((string)$display['client_label']) . '</td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
        . '<tr><td class="legacy-label-cell">N° C.C.</td><td class="legacy-value-cell">' . h((string)$display['request_label']) . '</td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
        . '</tbody></table></div>';

    $body .= '<div style="display:grid;grid-template-columns:minmax(320px,1fr) minmax(320px,1fr);gap:12px">';
    $body .= '<div class="legacy-sheet-card" style="margin:0"><table class="legacy-sheet-table"><thead><tr><th colspan="2">Configuración</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">Producto</td><td class="legacy-value-cell">' . h((string)$display['product_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Ancho</td><td class="legacy-value-cell">' . h((string)$display['width_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Alto (Tiro)</td><td class="legacy-value-cell">' . h((string)$display['height_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Alto (Retiro)</td><td class="legacy-value-cell">' . h((string)$display['height_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Doblez superior</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Fuelle</td><td class="legacy-value-cell">' . h((string)$display['gusset_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Dado de Manillas</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Cabezal</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Alarma</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Código</td><td class="legacy-value-cell">' . h((string)$ot['sku_final']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Etiqueta adhesiva</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Máquina</td><td class="legacy-value-cell">' . h((string)$display['station_label']) . '</td></tr>'
        . '</tbody></table></div>';

    $body .= '<div class="legacy-sheet-card" style="margin:0"><table class="legacy-sheet-table"><thead><tr><th colspan="2">Insumos</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell" colspan="2" style="background:#eef6f6;font-weight:800">Características de tela</td></tr>'
        . '<tr><td class="legacy-label-cell">Materialidad</td><td class="legacy-value-cell">' . h((string)$display['material_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Color Tela</td><td class="legacy-value-cell">' . h((string)$display['color_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Ancho Tela</td><td class="legacy-value-cell">' . h((string)$display['width_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Gramaje</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Cant. Bobinas</td><td class="legacy-value-cell">' . h((string)$display['output_roll_count']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell" colspan="2" style="background:#eef6f6;font-weight:800">Características de manillas</td></tr>'
        . '<tr><td class="legacy-label-cell">Color Manilla</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Ancho de Manillas</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Largo de Manillas</td><td class="legacy-value-cell">-</td></tr>'
        . '<tr><td class="legacy-label-cell">Cant. Manillas</td><td class="legacy-value-cell">-</td></tr>'
        . '</tbody></table></div>';
    $body .= '</div>';

    $body .= '<div class="legacy-sheet-card" style="margin-top:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Fechas de entrega</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">Cantidad de Bolsas</td><td class="legacy-value-cell">' . h((string)$display['target_qty_label']) . '</td><td class="legacy-label-cell">Fechas de Entrega</td><td class="legacy-value-cell">' . h(trim((string)($ot['erp_plan_date'] ?? '')) !== '' ? formatLabelDate((string)$ot['erp_plan_date']) : '-') . '</td></tr>'
        . '</tbody></table></div>';

    $body .= '<div class="legacy-sheet-card" style="margin-top:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Adjuntos</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">Imagen Diseño</td><td class="legacy-value-cell"><span class="btn secondary" style="opacity:.65;cursor:default;display:inline-block;padding:6px 12px">Descargar</span></td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
        . '<tr><td class="legacy-label-cell">Montaje</td><td class="legacy-value-cell"><span class="btn secondary" style="opacity:.65;cursor:default;display:inline-block;padding:6px 12px">Descargar</span></td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
        . '<tr><td class="legacy-label-cell">Bobinas Impresas</td><td class="legacy-value-cell"><button class="btn secondary" type="button" data-sealing-stock-open="1" style="display:inline-block;padding:6px 12px">Ver stock actual</button></td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
        . '</tbody></table></div>';

    return $body;
}

function renderWorkOrderSealingStockModalHtml(array $outputRolls): string
{
    $formatNumber = static function ($value, int $decimals = 0): string {
        return rtrim(rtrim(number_format((float)$value, $decimals, '.', ''), '0'), '.') ?: '0';
    };

    $body = '<div class="legacy-inline-modal" id="sealing-stock-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto">'
        . '<div style="max-width:980px;margin:24px auto;background:#fff;border-radius:8px;box-shadow:0 12px 30px rgba(15,23,42,.22);padding:18px">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Bobinas impresas</div><button type="button" class="btn secondary" data-sealing-stock-close="1">Cerrar</button></div>'
        . '<div class="table-wrap"><table class="legacy-sheet-table" style="margin:0"><thead><tr><th>Código</th><th>Bobina origen</th><th>Descripción</th><th>Peso</th><th>Estado</th><th>Fecha</th></tr></thead><tbody>';
    if ($outputRolls !== []) {
        foreach ($outputRolls as $outputRoll) {
            $body .= '<tr>'
                . '<td class="legacy-value-cell"><a href="/rolls/' . (int)$outputRoll['id'] . '">' . h((string)($outputRoll['roll_code'] ?? '-')) . '</a></td>'
                . '<td class="legacy-value-cell">' . h((string)($outputRoll['parent_roll_code'] ?? '-')) . '</td>'
                . '<td class="legacy-value-cell">' . h((string)($outputRoll['sku_description'] ?? '-')) . '</td>'
                . '<td class="legacy-value-cell">' . h($formatNumber((float)($outputRoll['weight_kg'] ?? 0), 3)) . ' Kg</td>'
                . '<td class="legacy-value-cell">' . h(rollProcessStageLabel((string)($outputRoll['process_stage'] ?? 'PRINTED'))) . '</td>'
                . '<td class="legacy-value-cell">' . h((string)($outputRoll['created_at'] ?? '-')) . '</td>'
                . '</tr>';
        }
    } else {
        $body .= '<tr><td class="legacy-value-cell" colspan="6">Todavía no hay bobinas impresas registradas para esta OT.</td></tr>';
    }
    $body .= '</tbody></table></div></div></div>';
    $body .= '<script>
        (function () {
            var modal = document.getElementById("sealing-stock-modal");
            if (!modal) return;
            document.querySelectorAll("[data-sealing-stock-open]").forEach(function (button) {
                button.addEventListener("click", function () {
                    modal.style.display = "block";
                });
            });
            document.querySelectorAll("[data-sealing-stock-close]").forEach(function (button) {
                button.addEventListener("click", function () {
                    modal.style.display = "none";
                });
            });
            modal.addEventListener("click", function (event) {
                if (event.target === modal) {
                    modal.style.display = "none";
                }
            });
        })();
    </script>';

    return $body;
}

function buildWorkOrderPackagingDisplayData(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    array $boxes = [],
    array $pallets = []
): array {
    $base = buildWorkOrderSealingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions);
    $sheetText = trim((string)($ot['erp_plan_desc'] ?? '') . ' ' . (string)($ot['sku_final'] ?? ''));

    $bagTypeLabel = '-';
    foreach (['BOUTIQUE', 'CAMISETA', 'TROQUELADA', 'PLANA', 'RINONERA', 'BASURA'] as $bagKeyword) {
        if (stripos($sheetText, $bagKeyword) !== false) {
            $bagTypeLabel = $bagKeyword;
            break;
        }
    }

    $logoPrintedLabel = '-';
    if (stripos($sheetText, 'SIN LOGO') !== false) {
        $logoPrintedLabel = 'SIN LOGO';
    } elseif (stripos($sheetText, 'LOGO') !== false) {
        $logoPrintedLabel = 'CON LOGO';
    }

    $reverseLabel = stripos($sheetText, 'REVERSA') !== false ? 'Si' : 'No';
    $mixLabel = (string)$base['request_label'] !== '-' ? (string)$base['request_label'] . '-1' : '-';
    $boxMeasureLabel = (string)$base['measure_compact'] !== '-'
        ? (string)$base['measure_compact'] . ($logoPrintedLabel !== '-' ? ' ' . $logoPrintedLabel : '')
        : '-';

    $boxCount = count($boxes);
    $palletCount = count($pallets);
    $unitsTotal = 0.0;
    foreach ($boxes as $box) {
        $unitsTotal += (float)($box['units_qty'] ?? 0);
    }

    $boxesPerPallet = '-';
    if ($palletCount > 0) {
        $totalPalletBoxes = 0;
        foreach ($pallets as $pallet) {
            $totalPalletBoxes += (int)($pallet['box_count'] ?? 0);
        }
        $boxesPerPallet = (string)max(0, (int)round($totalPalletBoxes / max(1, $palletCount)));
    }

    $unitsPerBoxLabel = $boxCount > 0 ? rtrim(rtrim(number_format($unitsTotal / $boxCount, 3, '.', ''), '0'), '.') : '-';
    $finalBoxUnitsLabel = $boxCount > 0 ? rtrim(rtrim(number_format((float)($boxes[0]['units_qty'] ?? 0), 3, '.', ''), '0'), '.') : '0';

    return $base + [
        'bag_type_label' => $bagTypeLabel,
        'procedence_label' => 'Fabrica',
        'print_type_label' => 'Flexografia',
        'printed_colors_label' => (string)$base['color_label'],
        'handle_color_label' => (string)$base['color_label'],
        'handle_measure_label' => '-',
        'reverse_label' => $reverseLabel,
        'mix_label' => $mixLabel,
        'box_measure_label' => $boxMeasureLabel,
        'printed_logo_label' => $logoPrintedLabel,
        'boxes_per_pallet_label' => $boxesPerPallet,
        'boxes_to_use_label' => $boxCount > 0 ? (string)$boxCount : '-',
        'units_per_box_label' => $unitsPerBoxLabel,
        'final_box_units_label' => $finalBoxUnitsLabel,
        'total_order_label' => (string)$base['target_qty_label'],
        'requested_units_label' => (string)$base['target_qty_label'],
    ];
}

function renderWorkOrderPackagingOverviewHtml(array $ot, array $display): string
{
    $leftHeaderStyle = 'background:#145e5b;color:#fff;border-color:#145e5b';
    $rightHeaderStyle = 'background:#19b7b2;color:#fff;border-color:#19b7b2';

    $body = '<div style="display:grid;grid-template-columns:minmax(0,1.55fr) minmax(320px,1fr);gap:12px;align-items:start">'
        . '<div class="legacy-sheet-card" style="margin:0"><table class="legacy-sheet-table" style="margin:0"><thead><tr><th colspan="2" style="' . $leftHeaderStyle . '">Informacion de Fabricacion</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">N° O.T.</td><td class="legacy-value-cell">' . h((string)$ot['ot_code']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cliente</td><td class="legacy-value-cell">' . h((string)$display['client_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">N° C.C.</td><td class="legacy-value-cell">' . h((string)$display['request_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Tipo de Material</td><td class="legacy-value-cell">' . h((string)$display['material_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Tipo de Bolsa</td><td class="legacy-value-cell">' . h((string)$display['bag_type_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Medidas</td><td class="legacy-value-cell">' . h((string)$display['measure_compact']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Procedencia</td><td class="legacy-value-cell"><span style="display:inline-block;background:#2b78d2;color:#fff;padding:2px 6px;border-radius:3px;font-weight:700">' . h((string)$display['procedence_label']) . '</span></td></tr>'
        . '<tr><td class="legacy-label-cell">Tipo de Impresion</td><td class="legacy-value-cell">' . h((string)$display['print_type_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Colores</td><td class="legacy-value-cell">' . h((string)$display['printed_colors_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Color Manillas</td><td class="legacy-value-cell">' . h((string)$display['handle_color_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Medida Manillas</td><td class="legacy-value-cell">' . h((string)$display['handle_measure_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Reversa</td><td class="legacy-value-cell">' . h((string)$display['reverse_label']) . '</td></tr>'
        . '</tbody></table></div>'
        . '<div class="legacy-sheet-card" style="margin:0"><table class="legacy-sheet-table" style="margin:0"><thead><tr><th colspan="2" style="' . $rightHeaderStyle . '">Datos Embalaje</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">Mezcla</td><td class="legacy-value-cell">' . h((string)$display['mix_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Medida de Caja</td><td class="legacy-value-cell">' . h((string)$display['box_measure_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Logotipo impresa</td><td class="legacy-value-cell">' . h((string)$display['printed_logo_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cant. Cajas por Pallet</td><td class="legacy-value-cell">' . h((string)$display['boxes_per_pallet_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Cant. Cajas a Utilizar</td><td class="legacy-value-cell">' . h((string)$display['boxes_to_use_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Unidades por caja</td><td class="legacy-value-cell">' . h((string)$display['units_per_box_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Unidades caja final</td><td class="legacy-value-cell">' . h((string)$display['final_box_units_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Total de Pedido</td><td class="legacy-value-cell" style="font-weight:800">' . h((string)$display['total_order_label']) . '</td></tr>'
        . '</tbody></table></div>'
        . '</div>';

    $body .= '<div class="legacy-sheet-card" style="margin-top:12px"><table class="legacy-sheet-table" style="margin:0"><thead><tr><th colspan="2" style="' . $rightHeaderStyle . '">Adjuntos</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">Imagen Diseño</td><td class="legacy-value-cell" style="width:120px"><span class="btn secondary" style="opacity:.65;cursor:default;display:inline-block;padding:6px 12px;min-width:92px;text-align:center">Descargar</span></td></tr>'
        . '<tr><td class="legacy-label-cell">Montaje</td><td class="legacy-value-cell"><span class="btn secondary" style="opacity:.65;cursor:default;display:inline-block;padding:6px 12px;min-width:92px;text-align:center">Descargar</span></td></tr>'
        . '</tbody></table></div>';

    $body .= '<div class="legacy-sheet-card" style="margin-top:12px"><table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Unidades solicitadas</td><td class="legacy-value-cell" style="font-weight:800">' . h((string)$display['requested_units_label']) . '</td></tr>'
        . '</tbody></table></div>';

    return $body;
}

function renderWorkOrderPackagingStartScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    array $boxes = [],
    array $pallets = [],
    ?string $flashMessage = null,
    bool $flashIsError = false,
    array $formState = []
): void {
    $service = $GLOBALS['service'] ?? null;
    $display = buildWorkOrderPackagingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions, $boxes, $pallets);
    $helperValue = trim((string)($formState['helper_name'] ?? ($display['helper_label'] ?? '')));
    $commentsValue = (string)($formState['comments'] ?? '');

    $body = '<div class="legacy-screen-title">Embalaje</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Embalaje</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/sealing/setup">Volver a Selladora</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= renderWorkOrderPackagingOverviewHtml($ot, $display);
    $body .= '<div class="legacy-sheet-card" style="margin-top:12px"><table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Tipo</td><td class="legacy-value-cell">EMBALAJE</td></tr>'
        . '<tr><td class="legacy-label-cell">Máquina</td><td class="legacy-value-cell">EMBALAJE</td></tr>'
        . '<tr><td class="legacy-label-cell">Ayudante</td><td class="legacy-value-cell"><select style="max-width:340px;min-height:34px;padding:6px 8px;font-size:13px;border-radius:4px"><option value="">&lt; Por favor seleccione &gt;</option>';
    foreach ((array)$display['helper_options'] as $helperOption) {
        $selected = $helperOption === $helperValue ? ' selected' : '';
        $body .= '<option value="' . h((string)$helperOption) . '"' . $selected . '>' . h((string)$helperOption) . '</option>';
    }
    if ($helperValue !== '' && !in_array($helperValue, (array)$display['helper_options'], true)) {
        $body .= '<option value="' . h($helperValue) . '" selected>' . h($helperValue) . '</option>';
    }
    $body .= '</select></td></tr>'
        . '<tr><td class="legacy-label-cell">Comentarios</td><td class="legacy-value-cell"><textarea rows="3" style="width:100%;min-height:68px;resize:vertical;border:1px solid #c5cbd3;border-radius:4px;padding:8px 10px">' . h($commentsValue) . '</textarea></td></tr>'
        . '</tbody></table></div>';

    render('Embalaje', $body);
}

function renderWorkOrderPackagingInfoScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    array $boxes = [],
    array $pallets = [],
    ?string $flashMessage = null,
    bool $flashIsError = false,
    array $formState = []
): void {
    $display = buildWorkOrderPackagingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions, $boxes, $pallets);
    $helperValue = trim((string)($formState['helper_name'] ?? ($display['helper_label'] ?? '')));
    $commentsValue = (string)($formState['comments'] ?? '');

    $body = '<div class="legacy-screen-title">Embalaje</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Embalaje</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/sealing/setup">Volver a Selladora</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= renderWorkOrderPackagingOverviewHtml($ot, $display);
    $body .= '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/packaging/setup/start">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<div class="legacy-sheet-card" style="margin-top:12px"><table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Tipo</td><td class="legacy-value-cell">EMBALAJE</td></tr>'
        . '<tr><td class="legacy-label-cell">Maquina</td><td class="legacy-value-cell">EMBALAJE</td></tr>'
        . '<tr><td class="legacy-label-cell">Comentarios</td><td class="legacy-value-cell"><textarea name="comments" rows="3" style="width:100%;min-height:68px;resize:vertical;border:1px solid #c5cbd3;border-radius:4px;padding:8px 10px">' . h($commentsValue) . '</textarea></td></tr>'
        . '</tbody></table></div>'
        . '<div style="margin-top:12px"><button class="legacy-primary-btn" type="submit" style="width:100%;min-width:0;background:#166534;color:#fff;border:0;padding:12px 16px;border-radius:6px;font-weight:700">Iniciar producción</button></div>'
        . '</form>';

    render('Embalaje', $body);
}


function renderWorkOrderPackagingFinishScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    array $boxes = [],
    array $pallets = [],
    ?string $flashMessage = null,
    bool $flashIsError = false,
    array $formState = []
): void {
    $service = $GLOBALS['service'] ?? null;
    $display = buildWorkOrderPackagingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions, $boxes, $pallets);
    $wasteWeightState = isset($formState['packaging_waste_weight']) && is_array($formState['packaging_waste_weight'])
        ? $formState['packaging_waste_weight']
        : [];
    $wasteCommentState = isset($formState['packaging_waste_comment']) && is_array($formState['packaging_waste_comment'])
        ? $formState['packaging_waste_comment']
        : [];
    $packagingState = isset($formState['packaging_data']) && is_array($formState['packaging_data'])
        ? $formState['packaging_data']
        : [];
    $boxesPerPalletBase = (float)(is_numeric((string)($display['boxes_per_pallet_label'] ?? '')) ? $display['boxes_per_pallet_label'] : 0);
    $unitsPerBoxBase = (float)(is_numeric((string)($display['units_per_box_label'] ?? '')) ? $display['units_per_box_label'] : 0);
    $totalBoxesBase = count($boxes);
    $fullPalletsBase = count($pallets);
    $incompleteBoxesBase = max(0, $totalBoxesBase - ((int)round($boxesPerPalletBase) * $fullPalletsBase));
    $finalBoxUnitsBase = (float)(is_numeric((string)($display['final_box_units_label'] ?? '')) ? $display['final_box_units_label'] : 0);
    $measureState = (string)($packagingState['package_measure'] ?? (string)($display['box_measure_label'] ?? ''));
    $unitsPerBoxState = (string)($packagingState['units_per_box'] ?? ($unitsPerBoxBase > 0 ? rtrim(rtrim(number_format($unitsPerBoxBase, 3, '.', ''), '0'), '.') : ''));
    $boxesPerPalletState = (string)($packagingState['boxes_per_pallet'] ?? ($boxesPerPalletBase > 0 ? rtrim(rtrim(number_format($boxesPerPalletBase, 3, '.', ''), '0'), '.') : ''));
    $completePalletsState = (string)($packagingState['complete_pallets'] ?? ($fullPalletsBase > 0 ? (string)$fullPalletsBase : ''));
    $incompletePalletBoxesState = (string)($packagingState['incomplete_pallet_boxes'] ?? ($incompleteBoxesBase > 0 ? (string)$incompleteBoxesBase : ''));
    $totalCompleteBoxesState = (string)($packagingState['total_complete_boxes'] ?? ($totalBoxesBase > 0 ? (string)$totalBoxesBase : ''));
    $finalBoxUnitsState = (string)($packagingState['final_box_units'] ?? ($finalBoxUnitsBase > 0 ? rtrim(rtrim(number_format($finalBoxUnitsBase, 3, '.', ''), '0'), '.') : ''));
    $leftoverBagsState = (string)($packagingState['leftover_bags'] ?? '');
    $showroomBagsState = (string)($packagingState['showroom_bags'] ?? '');
    $productionState = (string)($formState['packaging_produced_units'] ?? '');
    if ($productionState === '') {
        $productionBase = ($totalBoxesBase * $unitsPerBoxBase) + $finalBoxUnitsBase;
        $productionState = $productionBase > 0 ? rtrim(rtrim(number_format($productionBase, 3, '.', ''), '0'), '.') : '';
    }
    $commentsState = (string)($formState['packaging_finish_comments'] ?? '');
    $closePackagingState = isset($formState['close_packaging_data']) && is_array($formState['close_packaging_data'])
        ? $formState['close_packaging_data']
        : [];
    $closeMeasureState = (string)($closePackagingState['package_measure'] ?? $measureState);
    $closeUnitsPerBoxState = (string)($closePackagingState['units_per_box'] ?? $unitsPerBoxState);
    $closeBoxesPerPalletState = (string)($closePackagingState['boxes_per_pallet'] ?? $boxesPerPalletState);
    $closeCompletePalletsState = (string)($closePackagingState['complete_pallets'] ?? $completePalletsState);
    $closeIncompletePalletBoxesState = (string)($closePackagingState['incomplete_pallet_boxes'] ?? $incompletePalletBoxesState);
    $closeTotalCompleteBoxesState = (string)($closePackagingState['total_complete_boxes'] ?? $totalCompleteBoxesState);
    $closeFinalBoxUnitsState = (string)($closePackagingState['final_box_units'] ?? $finalBoxUnitsState);
    $closeLeftoverBagsState = (string)($closePackagingState['leftover_bags'] ?? $leftoverBagsState);
    $closeShowroomBagsState = (string)($closePackagingState['showroom_bags'] ?? $showroomBagsState);
    $closeTotalUnitsState = (string)($formState['close_total_units'] ?? $productionState);
    $closeWarehouseState = (string)($formState['close_warehouse_id'] ?? '');
    $closeSupervisorUsernameState = (string)($formState['close_supervisor_username'] ?? '');
    $closeSupervisorObservationState = (string)($formState['close_supervisor_observation'] ?? '');
    $closeClassificationState = (string)($formState['close_closure_classification'] ?? '');
    $closeModalOpen = (($formState['close_modal_open'] ?? '') === '1');
    $closureOptions = [
        'CONFORME' => 'Conforme',
        'OBSERVADO' => 'Con observación',
        'PARCIAL' => 'Parcial',
    ];
    $closeWarehouses = $service instanceof ReceptionService
        ? array_values(array_filter($service->listWarehouses(), static fn(array $warehouse): bool => in_array((int)$warehouse['code'], [700, 1000], true)))
        : [];
    if ($closeWarehouses === [] && $service instanceof ReceptionService) {
        $closeWarehouses = $service->listWarehouses();
    }
    $wasteRows = [
        ['key' => 'setup', 'title' => 'Merma', 'label' => 'Configuración'],
        ['key' => 'printing', 'title' => 'Merma', 'label' => 'Impresión'],
        ['key' => 'other', 'title' => 'Merma', 'label' => 'Otros'],
        ['key' => 'repair', 'title' => 'Reparación', 'label' => 'Reparación Manillas / Configuración'],
    ];

    $body = '<div class="legacy-screen-title">Terminar producción Embalaje</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Embalaje &gt; Terminar producción</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/packaging/setup">Volver</a><button class="btn" type="button" id="packaging-open-close-modal">Cerrar OT</button></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= renderWorkOrderPackagingOverviewHtml($ot, $display);
    $body .= '<div class="legacy-sheet-card finish-production-card"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/packaging/finish" id="packaging-finish-form">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="close_modal_open" id="close_modal_open" value="' . ($closeModalOpen ? '1' : '0') . '">'
        . '<div class="finish-form-row finish-form-row-inline">'
        . '<div class="finish-label">Tipo</div>'
        . '<div class="finish-static-value">EMBALAJE</div>'
        . '</div>'
        . '<div class="finish-form-row finish-form-row-inline">'
        . '<div class="finish-label">Máquina</div>'
        . '<div class="finish-static-value">EMBALAJE</div>'
        . '</div>'
        . '<div class="finish-form-row finish-form-row-inline">'
        . '<div class="finish-label">Producción</div>'
        . '<div class="finish-inline-input"><input id="packaging_produced_units" name="packaging_produced_units" type="number" step="0.001" min="0" value="' . h($productionState) . '"></div>'
        . '<div class="finish-inline-suffix">unidades</div>'
        . '</div>'
        . '<div class="finish-form-row finish-form-row-comments">'
        . '<div class="finish-label">Comentarios</div>'
        . '<div class="finish-comments-wrap"><textarea name="packaging_finish_comments" rows="3">' . h($commentsState) . '</textarea></div>'
        . '</div>'
        . '<div style="margin-top:12px"><table class="legacy-sheet-table" style="margin:0"><thead><tr><th style="text-align:left">Medidas / Cantidades embalaje</th><th style="text-align:left">Predefinido</th><th style="text-align:left">Ingreso</th><th style="text-align:left"></th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">Medida de caja</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['box_measure_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_measure" type="text" name="packaging_data[package_measure]" value="' . h($measureState) . '" style="width:100%"></td><td class="legacy-value-cell"></td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad de bolsas por caja</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['units_per_box_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_units_per_box" type="number" step="0.001" min="0" name="packaging_data[units_per_box]" value="' . h($unitsPerBoxState) . '" style="width:100%"></td><td class="legacy-value-cell">(calcula unidades producidas)</td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad de cajas por pallets</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['boxes_per_pallet_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_boxes_per_pallet" type="number" step="0.001" min="0" name="packaging_data[boxes_per_pallet]" value="' . h($boxesPerPalletState) . '" style="width:100%"></td><td class="legacy-value-cell"></td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad de pallets completos</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$fullPalletsBase) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_complete_pallets" type="number" step="0.001" min="0" name="packaging_data[complete_pallets]" value="' . h($completePalletsState) . '" style="width:100%"></td><td class="legacy-value-cell"></td></tr>'
        . '<tr><td class="legacy-label-cell">Numero de cajas en pallet incompleto</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$incompleteBoxesBase) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_incomplete_boxes" type="number" step="0.001" min="0" name="packaging_data[incomplete_pallet_boxes]" value="' . h($incompletePalletBoxesState) . '" style="width:100%"></td><td class="legacy-value-cell"></td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad total de cajas completas</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$totalBoxesBase) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_total_boxes" type="number" step="0.001" min="0" name="packaging_data[total_complete_boxes]" value="' . h($totalCompleteBoxesState) . '" style="width:100%"></td><td class="legacy-value-cell">(calcula unidades producidas)</td></tr>'
        . '<tr><td class="legacy-label-cell">Caja final (completa pedido)</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['final_box_units_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_final_box_units" type="number" step="0.001" min="0" name="packaging_data[final_box_units]" value="' . h($finalBoxUnitsState) . '" style="width:100%"></td><td class="legacy-value-cell">(calcula unidades producidas)</td></tr>'
        . '<tr><td class="legacy-label-cell">Bolsas sobrantes</td><td class="legacy-value-cell"><input type="text" value="" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_leftover_bags" type="number" step="0.001" min="0" name="packaging_data[leftover_bags]" value="' . h($leftoverBagsState) . '" style="width:100%"></td><td class="legacy-value-cell">(calcula unidades producidas)</td></tr>'
        . '<tr><td class="legacy-label-cell">Bolsas a Showroom</td><td class="legacy-value-cell"><input type="text" value="" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="packaging_showroom_bags" type="number" step="0.001" min="0" name="packaging_data[showroom_bags]" value="' . h($showroomBagsState) . '" style="width:100%"></td><td class="legacy-value-cell">(calcula unidades producidas)</td></tr>'
        . '</tbody></table></div>'
        . '<div class="finish-merma-block" style="margin-top:12px">';
    foreach ($wasteRows as $wasteRow) {
        $body .= '<div class="finish-form-row finish-merma-row">'
            . '<div class="finish-merma-title">' . h((string)$wasteRow['title']) . '</div>'
            . '<div class="finish-merma-detail">' . h((string)$wasteRow['label']) . '</div>'
            . '<div class="finish-merma-unit"><select disabled><option>Cantidad</option></select></div>'
            . '<div class="finish-merma-weight"><input type="number" step="0.001" min="0" name="packaging_waste_weight[' . h((string)$wasteRow['key']) . ']" value="' . h((string)($wasteWeightState[$wasteRow['key']] ?? '')) . '"></div>'
            . '<div class="finish-merma-comment-label">Comentarios</div>'
            . '<div class="finish-merma-comment"><input type="text" name="packaging_waste_comment[' . h((string)$wasteRow['key']) . ']" value="' . h((string)($wasteCommentState[$wasteRow['key']] ?? '')) . '"></div>'
            . '</div>';
    }
    $body .= '</div>'
        . '<div class="legacy-inline-modal" id="packaging-close-modal" style="display:' . ($closeModalOpen ? 'block' : 'none') . ';position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:18px;overflow:auto">'
        . '<div style="max-width:980px;margin:18px auto;background:#f2f2f2;border:1px solid #d6d6d6;border-radius:4px;padding:12px 14px;box-shadow:0 20px 45px rgba(15,23,42,.22)">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px"><div style="font-size:18px;font-weight:800">Terminar OT</div><button type="button" class="btn secondary" id="packaging-close-modal-close">Cerrar</button></div>'
        . '<table class="legacy-sheet-table" style="margin:0"><thead><tr><th style="text-align:left">Medidas / Cantidades embalaje</th><th style="text-align:left">Predefinido</th><th style="text-align:left">Ingreso</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">Medida de caja</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['box_measure_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_package_measure" type="text" name="close_packaging_data[package_measure]" value="' . h($closeMeasureState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad de bolsas por caja</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['units_per_box_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_units_per_box" type="number" step="0.001" min="0" name="close_packaging_data[units_per_box]" value="' . h($closeUnitsPerBoxState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad de cajas por pallets</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['boxes_per_pallet_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_boxes_per_pallet" type="number" step="0.001" min="0" name="close_packaging_data[boxes_per_pallet]" value="' . h($closeBoxesPerPalletState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad de pallets completos</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$fullPalletsBase) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_complete_pallets" type="number" step="0.001" min="0" name="close_packaging_data[complete_pallets]" value="' . h($closeCompletePalletsState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Numero de cajas en pallet incompleto</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$incompleteBoxesBase) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_incomplete_pallet_boxes" type="number" step="0.001" min="0" name="close_packaging_data[incomplete_pallet_boxes]" value="' . h($closeIncompletePalletBoxesState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Cantidad total de cajas completas</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$totalBoxesBase) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_total_complete_boxes" type="number" step="0.001" min="0" name="close_packaging_data[total_complete_boxes]" value="' . h($closeTotalCompleteBoxesState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Caja final (completa pedido)</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['final_box_units_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_final_box_units" type="number" step="0.001" min="0" name="close_packaging_data[final_box_units]" value="' . h($closeFinalBoxUnitsState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Bolsas sobrantes</td><td class="legacy-value-cell"><input type="text" value="" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_leftover_bags" type="number" step="0.001" min="0" name="close_packaging_data[leftover_bags]" value="' . h($closeLeftoverBagsState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Bolsas a Showroom</td><td class="legacy-value-cell"><input type="text" value="" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_showroom_bags" type="number" step="0.001" min="0" name="close_packaging_data[showroom_bags]" value="' . h($closeShowroomBagsState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Totales</td><td class="legacy-value-cell"><input type="text" value="' . h((string)$display['requested_units_label']) . '" readonly style="width:100%"></td><td class="legacy-value-cell"><input id="close_total_units" type="number" step="0.001" min="0" name="close_total_units" value="' . h($closeTotalUnitsState) . '" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Bodega</td><td class="legacy-value-cell" colspan="2"><select name="close_warehouse_id" style="width:100%"><option value="">&lt; Por favor seleccione &gt;</option>';
    foreach ($closeWarehouses as $warehouseOption) {
        $selected = (string)($warehouseOption['id'] ?? '') === $closeWarehouseState ? ' selected' : '';
        $body .= '<option value="' . (int)($warehouseOption['id'] ?? 0) . '"' . $selected . '>' . h((string)($warehouseOption['code'] ?? '')) . ' - ' . h((string)($warehouseOption['name'] ?? '')) . '</option>';
    }
    $body .= '</select></td></tr>'
        . '<tr><td class="legacy-label-cell">Usuario supervisor</td><td class="legacy-value-cell"><input type="text" name="close_supervisor_username" value="' . h($closeSupervisorUsernameState) . '" placeholder="Usuario Supervisor" style="width:100%"></td><td class="legacy-value-cell"><input type="password" name="close_supervisor_password" placeholder="Contrasena Supervisor" autocomplete="current-password" style="width:100%"></td></tr>'
        . '<tr><td class="legacy-label-cell">Observacion Supervisor</td><td class="legacy-value-cell" colspan="2"><textarea name="close_supervisor_observation" rows="3" style="width:100%;min-height:60px;resize:vertical">' . h($closeSupervisorObservationState) . '</textarea></td></tr>'
        . '<tr><td class="legacy-label-cell">Clasificacion de Cierre</td><td class="legacy-value-cell" colspan="2"><select name="close_closure_classification" style="width:100%"><option value="">&lt; Por favor seleccione &gt;</option>';
    foreach ($closureOptions as $closureValue => $closureLabel) {
        $selected = $closureValue === $closeClassificationState ? ' selected' : '';
        $body .= '<option value="' . h($closureValue) . '"' . $selected . '>' . h($closureLabel) . '</option>';
    }
    $body .= '</select></td></tr>'
        . '</tbody></table>'
        . '<div style="display:flex;justify-content:space-between;gap:8px;margin-top:10px"><button class="btn secondary finish-grey-btn" type="button" id="packaging-close-modal-cancel">Volver</button><button class="btn finish-yellow-btn" type="submit" formaction="/work-orders/' . (int)$ot['id'] . '/packaging/finish-order">Terminar OT</button></div>'
        . '</div></div>'
        . '<div class="finish-actions">'
        . '<div class="finish-actions-left"><a class="btn secondary finish-grey-btn" href="/work-orders/' . (int)$ot['id'] . '/packaging/setup">Volver</a><button class="btn secondary finish-red-btn" type="reset">Eliminar</button></div>'
        . '<div class="finish-actions-right"><button class="btn secondary finish-grey-btn" type="button" id="packaging-finish-refresh">Actualizar</button><button class="btn finish-yellow-btn" type="submit">Terminar producción</button></div>'
        . '</div>'
        . '</form></div>'
        . '<script>
            (function () {
                var form = document.getElementById("packaging-finish-form");
                if (!form) return;
                var refreshButton = document.getElementById("packaging-finish-refresh");
                var actionsRight = form.querySelector(".finish-actions-right");
                var openCloseModalButton = document.getElementById("packaging-open-close-modal");
                var closeModal = document.getElementById("packaging-close-modal");
                var closeModalOpenInput = document.getElementById("close_modal_open");
                var closeModalCloseButton = document.getElementById("packaging-close-modal-close");
                var closeModalCancelButton = document.getElementById("packaging-close-modal-cancel");
                var producedInput = document.getElementById("packaging_produced_units");
                var measureInput = document.getElementById("packaging_measure");
                var unitsPerBoxInput = document.getElementById("packaging_units_per_box");
                var totalBoxesInput = document.getElementById("packaging_total_boxes");
                var finalBoxUnitsInput = document.getElementById("packaging_final_box_units");
                var leftoverBagsInput = document.getElementById("packaging_leftover_bags");
                var showroomBagsInput = document.getElementById("packaging_showroom_bags");
                var boxesPerPalletInput = document.getElementById("packaging_boxes_per_pallet");
                var completePalletsInput = document.getElementById("packaging_complete_pallets");
                var incompleteBoxesInput = document.getElementById("packaging_incomplete_boxes");
                var closeMeasureInput = document.getElementById("close_package_measure");
                var closeUnitsPerBoxInput = document.getElementById("close_units_per_box");
                var closeBoxesPerPalletInput = document.getElementById("close_boxes_per_pallet");
                var closeCompletePalletsInput = document.getElementById("close_complete_pallets");
                var closeIncompleteBoxesInput = document.getElementById("close_incomplete_pallet_boxes");
                var closeTotalBoxesInput = document.getElementById("close_total_complete_boxes");
                var closeFinalBoxUnitsInput = document.getElementById("close_final_box_units");
                var closeLeftoverBagsInput = document.getElementById("close_leftover_bags");
                var closeShowroomBagsInput = document.getElementById("close_showroom_bags");
                var closeTotalUnitsInput = document.getElementById("close_total_units");
                if (!openCloseModalButton && actionsRight) {
                    openCloseModalButton = document.createElement("button");
                    openCloseModalButton.type = "button";
                    openCloseModalButton.id = "packaging-open-close-modal";
                    openCloseModalButton.className = "btn secondary finish-grey-btn";
                    openCloseModalButton.textContent = "Cerrar OT";
                    if (refreshButton && refreshButton.nextSibling) {
                        actionsRight.insertBefore(openCloseModalButton, refreshButton.nextSibling);
                    } else {
                        actionsRight.appendChild(openCloseModalButton);
                    }
                }
                function toNumber(input) {
                    if (!input) return 0;
                    var value = parseFloat(input.value || "0");
                    return isFinite(value) && value >= 0 ? value : 0;
                }
                function normalizeNumericInputs() {
                    form.querySelectorAll("input[type=number]").forEach(function (input) {
                        var value = parseFloat(input.value || "0");
                        if (!isFinite(value) || value < 0) {
                            input.value = "";
                        }
                    });
                }
                function syncCloseModalValues() {
                    if (closeMeasureInput && measureInput) closeMeasureInput.value = measureInput.value;
                    if (closeUnitsPerBoxInput && unitsPerBoxInput) closeUnitsPerBoxInput.value = unitsPerBoxInput.value;
                    if (closeBoxesPerPalletInput && boxesPerPalletInput) closeBoxesPerPalletInput.value = boxesPerPalletInput.value;
                    if (closeCompletePalletsInput && completePalletsInput) closeCompletePalletsInput.value = completePalletsInput.value;
                    if (closeIncompleteBoxesInput && incompleteBoxesInput) closeIncompleteBoxesInput.value = incompleteBoxesInput.value;
                    if (closeTotalBoxesInput && totalBoxesInput) closeTotalBoxesInput.value = totalBoxesInput.value;
                    if (closeFinalBoxUnitsInput && finalBoxUnitsInput) closeFinalBoxUnitsInput.value = finalBoxUnitsInput.value;
                    if (closeLeftoverBagsInput && leftoverBagsInput) closeLeftoverBagsInput.value = leftoverBagsInput.value;
                    if (closeShowroomBagsInput && showroomBagsInput) closeShowroomBagsInput.value = showroomBagsInput.value;
                    if (closeTotalUnitsInput && producedInput) closeTotalUnitsInput.value = producedInput.value;
                }
                function setCloseModal(open) {
                    if (!closeModal) return;
                    closeModal.style.display = open ? "block" : "none";
                    if (closeModalOpenInput) {
                        closeModalOpenInput.value = open ? "1" : "0";
                    }
                    if (open) {
                        syncCloseModalValues();
                    }
                }
                function syncTotalBoxes() {
                    if (!totalBoxesInput || !boxesPerPalletInput || !completePalletsInput || !incompleteBoxesInput) return;
                    var totalBoxes = (toNumber(boxesPerPalletInput) * toNumber(completePalletsInput)) + toNumber(incompleteBoxesInput);
                    if (totalBoxes > 0) {
                        totalBoxesInput.value = totalBoxes % 1 === 0 ? String(totalBoxes) : totalBoxes.toFixed(3).replace(/\.000$/, "");
                    }
                }
                function syncProducedUnits() {
                    if (!producedInput) return;
                    var produced = (toNumber(totalBoxesInput) * toNumber(unitsPerBoxInput)) + toNumber(finalBoxUnitsInput) + toNumber(leftoverBagsInput) + toNumber(showroomBagsInput);
                    producedInput.value = produced > 0 ? (produced % 1 === 0 ? String(produced) : produced.toFixed(3).replace(/\.000$/, "")) : "";
                }
                [boxesPerPalletInput, completePalletsInput, incompleteBoxesInput].forEach(function (input) {
                    if (!input) return;
                    input.addEventListener("input", function () {
                        syncTotalBoxes();
                        syncProducedUnits();
                    });
                });
                [unitsPerBoxInput, totalBoxesInput, finalBoxUnitsInput, leftoverBagsInput, showroomBagsInput].forEach(function (input) {
                    if (!input) return;
                    input.addEventListener("input", syncProducedUnits);
                });
                if (refreshButton) {
                    refreshButton.addEventListener("click", function () {
                        normalizeNumericInputs();
                        syncTotalBoxes();
                        syncProducedUnits();
                    });
                }
                if (openCloseModalButton) {
                    openCloseModalButton.addEventListener("click", function () {
                        setCloseModal(true);
                    });
                }
                [closeModalCloseButton, closeModalCancelButton].forEach(function (button) {
                    if (!button) return;
                    button.addEventListener("click", function () {
                        setCloseModal(false);
                    });
                });
                if (closeModal) {
                    closeModal.addEventListener("click", function (event) {
                        if (event.target === closeModal) {
                            setCloseModal(false);
                        }
                    });
                }
                if (closeModalOpenInput && closeModalOpenInput.value === "1") {
                    setCloseModal(true);
                }
            })();
        </script>';

    render('Terminar producción Embalaje', $body);
}

function renderWorkOrderSealingProgressModalsHtml(int $workOrderId, string $returnScreen): string
{
    $modalBaseStyle = 'display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto';
    $modalCardStyle = 'max-width:720px;margin:20px auto;background:#f4f5f7;border:1px solid #d6d6d6;border-radius:8px;padding:16px 18px;box-shadow:0 20px 45px rgba(15,23,42,.22)';

    return '<div class="legacy-inline-modal" id="progress-modal-maintenance" style="' . $modalBaseStyle . '">'
        . '<div style="' . $modalCardStyle . '">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Registrar mantención</div><button type="button" class="btn secondary" data-progress-modal-close="maintenance">Cerrar</button></div>'
        . '<form method="post" action="/work-orders/' . $workOrderId . '/production-event/start">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="event_key" value="MAINTENANCE">'
        . '<input type="hidden" name="return_screen" value="' . h($returnScreen) . '">'
        . '<div class="legacy-sheet-card"><table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell">Mantención</td><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Comentario</td><td class="legacy-value-cell" colspan="3"><textarea name="comments" rows="4" required placeholder="Describe la mantención realizada o el motivo del registro." style="width:100%;border:1px solid #cfd4dc;border-radius:3px;padding:8px"></textarea></td></tr>'
        . '</tbody></table></div>'
        . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-progress-modal-close="maintenance">Cancelar</button><button class="btn" type="submit">Guardar inicio</button></div>'
        . '</form></div></div>'
        . '<div class="legacy-inline-modal" id="progress-modal-pause" style="' . $modalBaseStyle . '">'
        . '<div style="' . $modalCardStyle . '">'
        . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Registrar pausa</div><button type="button" class="btn secondary" data-progress-modal-close="pause">Cerrar</button></div>'
        . '<form method="post" action="/work-orders/' . $workOrderId . '/production-event/start">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="event_key" value="PAUSE">'
        . '<input type="hidden" name="return_screen" value="' . h($returnScreen) . '">'
        . '<div class="legacy-sheet-card"><table class="legacy-sheet-table" style="margin:0"><tbody>'
        . '<tr><td class="legacy-label-cell">Evento</td><td class="legacy-value-cell">Pausa</td><td class="legacy-label-cell">Inicio</td><td class="legacy-value-cell">' . h(formatLabelDateTime(date('Y-m-d H:i:s'))) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">Comentario</td><td class="legacy-value-cell" colspan="3"><textarea name="comments" rows="4" required placeholder="Indica el motivo de la pausa." style="width:100%;border:1px solid #cfd4dc;border-radius:3px;padding:8px"></textarea></td></tr>'
        . '</tbody></table></div>'
        . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-progress-modal-close="pause">Cancelar</button><button class="btn" type="submit">Guardar inicio</button></div>'
        . '</form></div></div>'
        . '<script>
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
}

function renderWorkOrderSealingInfoScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    ?array $lastFinish = null,
    array $sealingMachines = [],
    array $helperOptions = [],
    ?string $flashMessage = null,
    bool $flashIsError = false,
    array $formState = []
): void {
    $display = buildWorkOrderSealingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions);
    $helperValue = trim((string)($formState['helper_name'] ?? ''));
    if ($helperValue === '' && (string)$display['helper_label'] !== '-') {
        $helperValue = (string)$display['helper_label'];
    }
    $commentsValue = (string)($formState['comments'] ?? '');

    $body = '<div class="legacy-screen-title">Selladora</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Selladora</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/start">Volver</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= renderWorkOrderSealingOverviewHtml($ot, $display);
    $body .= '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/sealing/setup/start">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<div class="legacy-sheet-card" style="margin-top:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="2">Inicio de alistamiento Selladora</th></tr></thead><tbody>'
        . '<tr><td class="legacy-label-cell">Tipo</td><td class="legacy-value-cell">SELLADORAS</td></tr>'
        . '<tr><td class="legacy-label-cell">Máquina</td><td class="legacy-value-cell">' . h((string)$display['station_label']) . '</td></tr>'
        . '<tr><td class="legacy-label-cell">De Medida *</td><td class="legacy-value-cell"><select disabled style="max-width:340px;min-height:34px;padding:6px 8px;font-size:13px;border-radius:4px"><option selected>' . h((string)$display['measure_compact'] !== '-' ? (string)$display['measure_compact'] : 'Sin medida definida') . '</option></select></td></tr>'
        . '<tr><td class="legacy-label-cell">A Medida *</td><td class="legacy-value-cell"><select disabled style="max-width:340px;min-height:34px;padding:6px 8px;font-size:13px;border-radius:4px"><option selected>' . h((string)$display['measure_label']) . '</option></select></td></tr>'
        . '<tr><td class="legacy-label-cell">Ayudante *</td><td class="legacy-value-cell"><select name="helper_name" required style="max-width:340px;min-height:34px;padding:6px 8px;font-size:13px;border-radius:4px"><option value="">&lt; Por favor seleccione &gt;</option>';
    foreach ((array)$display['helper_options'] as $helperOption) {
        $selected = $helperOption === $helperValue ? ' selected' : '';
        $body .= '<option value="' . h((string)$helperOption) . '"' . $selected . '>' . h((string)$helperOption) . '</option>';
    }
    if ($helperValue !== '' && !in_array($helperValue, (array)$display['helper_options'], true)) {
        $body .= '<option value="' . h($helperValue) . '" selected>' . h($helperValue) . '</option>';
    }
    $body .= '</select></td></tr>'
        . '<tr><td class="legacy-label-cell">Comentarios</td><td class="legacy-value-cell"><textarea name="comments" rows="3" style="width:100%;min-height:68px;resize:vertical;border:1px solid #c5cbd3;border-radius:4px;padding:8px 10px">' . h($commentsValue) . '</textarea></td></tr>'
        . '<tr><td class="legacy-value-cell" colspan="2" style="padding:10px"><button class="legacy-primary-btn" type="submit" style="width:100%;min-width:0">&#9654; Iniciar alistamiento</button></td></tr>'
        . '</tbody></table></div></form>';

    $body .= renderWorkOrderSealingStockModalHtml($outputRolls);
    render('Selladora', $body);
}


function renderWorkOrderSealingFinishScreen(
    array $ot,
    array $outputRolls = [],
    ?array $activeShiftSession = null,
    array $helperOptions = [],
    ?string $flashMessage = null,
    bool $flashIsError = false,
    array $formState = []
): void {
    $display = buildWorkOrderSealingDisplayData($ot, $outputRolls, $activeShiftSession, $helperOptions);
    $principalCounterState = (string)($formState['sealing_principal_counter'] ?? '');
    $receiverCounterState = (string)($formState['sealing_receiver_counter'] ?? '');
    $commentsState = (string)($formState['sealing_finish_comments'] ?? '');
    $wasteWeightState = isset($formState['sealing_waste_weight']) && is_array($formState['sealing_waste_weight'])
        ? $formState['sealing_waste_weight']
        : [];
    $wasteCommentState = isset($formState['sealing_waste_comment']) && is_array($formState['sealing_waste_comment'])
        ? $formState['sealing_waste_comment']
        : [];
    $wasteRows = [
        ['key' => 'setup', 'title' => 'Merma', 'label' => 'Configuración'],
        ['key' => 'printing', 'title' => 'Merma', 'label' => 'Impresión'],
        ['key' => 'other', 'title' => 'Merma', 'label' => 'Otros'],
        ['key' => 'repair', 'title' => 'Reparación', 'label' => 'Reparación Manillas / Configuración'],
    ];

    $body = '<div class="legacy-screen-title">Terminar producción Selladora</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Selladora &gt; Terminar producción</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/sealing/setup">Volver</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= renderWorkOrderSealingOverviewHtml($ot, $display);
    $body .= renderWorkOrderSealingStockModalHtml($outputRolls);
    $body .= '<div class="legacy-sheet-card finish-production-card"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/sealing/finish" id="sealing-finish-form">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<div class="finish-form-row finish-form-row-inline">'
        . '<div class="finish-label">Tipo</div>'
        . '<div class="finish-static-value">SELLADORAS</div>'
        . '</div>'
        . '<div class="finish-form-row finish-form-row-inline">'
        . '<div class="finish-label">Máquina</div>'
        . '<div class="finish-static-value">' . h((string)$display['station_label']) . '</div>'
        . '</div>'
        . '<div class="finish-form-row finish-form-row-inline">'
        . '<div class="finish-label">Contador Tablero Principal</div>'
        . '<div class="finish-inline-input"><input name="sealing_principal_counter" type="number" step="1" min="0" value="' . h($principalCounterState) . '"></div>'
        . '<div class="finish-inline-suffix">unidades</div>'
        . '</div>'
        . '<div class="finish-form-row finish-form-row-inline">'
        . '<div class="finish-label">Contador Módulo Recibidor</div>'
        . '<div class="finish-inline-input"><input name="sealing_receiver_counter" type="number" step="1" min="0" value="' . h($receiverCounterState) . '"></div>'
        . '<div class="finish-inline-suffix">unidades</div>'
        . '</div>'
        . '<div class="finish-form-row finish-form-row-comments">'
        . '<div class="finish-label">Comentarios</div>'
        . '<div class="finish-comments-wrap"><textarea name="sealing_finish_comments" rows="3">' . h($commentsState) . '</textarea></div>'
        . '</div>'
        . '<div class="finish-actions">'
        . '<div class="finish-actions-left"><a class="btn secondary finish-grey-btn" href="/work-orders/' . (int)$ot['id'] . '/sealing/setup">Volver</a><button class="btn secondary finish-red-btn" type="reset">Eliminar</button></div>'
        . '<div class="finish-actions-right"><button class="btn secondary finish-grey-btn" type="button" id="sealing-finish-refresh">Actualizar</button><button class="btn finish-yellow-btn" type="submit">Terminar producción</button></div>'
        . '</div>'
        . '<div class="finish-merma-block" style="margin-top:12px">';
    foreach ($wasteRows as $wasteRow) {
        $body .= '<div class="finish-form-row finish-merma-row">'
            . '<div class="finish-merma-title">' . h((string)$wasteRow['title']) . '</div>'
            . '<div class="finish-merma-detail">' . h((string)$wasteRow['label']) . '</div>'
            . '<div class="finish-merma-unit"><select disabled><option>Kilos</option></select></div>'
            . '<div class="finish-merma-weight"><input type="number" step="0.001" min="0" name="sealing_waste_weight[' . h((string)$wasteRow['key']) . ']" value="' . h((string)($wasteWeightState[$wasteRow['key']] ?? '')) . '"></div>'
            . '<div class="finish-merma-comment-label">Comentarios</div>'
            . '<div class="finish-merma-comment"><input type="text" name="sealing_waste_comment[' . h((string)$wasteRow['key']) . ']" value="' . h((string)($wasteCommentState[$wasteRow['key']] ?? '')) . '"></div>'
            . '</div>';
    }
    $body .= '</div>'
        . '</form></div>'
        . '<script>
            (function () {
                var form = document.getElementById("sealing-finish-form");
                if (!form) return;
                var refreshButton = document.getElementById("sealing-finish-refresh");
                function normalizeNumericInputs() {
                    form.querySelectorAll("input[type=number]").forEach(function (input) {
                        var value = parseFloat(input.value || "0");
                        if (!isFinite(value) || value < 0) {
                            input.value = "";
                        }
                    });
                }
                if (refreshButton) {
                    refreshButton.addEventListener("click", normalizeNumericInputs);
                }
            })();
        </script>';

    $body .= renderWorkOrderSealingStockModalHtml($outputRolls);
    render('Terminar producción Selladora', $body);
}

function renderWorkOrderMaterialUsageScreen(
    array $ot,
    ?array $currentRoll,
    array $chemicalInputs,
    array $materialRequests,
    array $availableMaterialRolls,
    array $boxes,
    ?array $activeShiftSession = null,
    array $assignedCliches = [],
    ?array $outputRoll = null,
    ?string $flashMessage = null,
    bool $flashIsError = false,
    array $formState = []
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
            'shift_label' => (string)($ot['shift_shift_label'] ?? ''),
            'started_at' => (string)($ot['shift_started_at'] ?? ''),
            'comments' => (string)($ot['shift_comments'] ?? ''),
            'process_stage' => (string)($ot['shift_process_stage'] ?? ''),
        ];
    } elseif (is_array($activeShiftSession) && $activeShiftSession !== []) {
        $sessionForDisplay = $activeShiftSession;
    }
    $releaseFinalWeightState = (string)($formState['final_weight_kg'] ?? (isset($currentRoll['weight_kg'])
        ? rtrim(rtrim(number_format((float)$currentRoll['weight_kg'], 2, '.', ''), '0'), '.')
        : ''));
    $releaseFinalMetersState = (string)($formState['final_meters'] ?? (isset($currentRoll['meters'])
        ? rtrim(rtrim(number_format((float)$currentRoll['meters'], 3, '.', ''), '0'), '.')
        : ''));
    $releaseWasteTotalState = (string)($formState['waste_kg'] ?? '0');
    $releaseWasteWeightState = isset($formState['release_waste_detail_weight']) && is_array($formState['release_waste_detail_weight'])
        ? $formState['release_waste_detail_weight']
        : [];
    $releaseWasteCommentState = isset($formState['release_waste_detail_comment']) && is_array($formState['release_waste_detail_comment'])
        ? $formState['release_waste_detail_comment']
        : [];
    $releaseWasteRows = [
        ['key' => 'approval', 'label' => 'Ajuste / Aprobación'],
        ['key' => 'printing', 'label' => 'Impresión'],
        ['key' => 'process', 'label' => 'Merma Proceso'],
        ['key' => 'other', 'label' => 'Otros'],
    ];

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
    $saldoTotalLabel = $targetQtyNumber > 0 ? number_format($saldoTotalUnits, 0, '.', '.') . ' unidades' : '-';
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

    $availableRollsByGroup = [];
    foreach ($availableMaterialRolls as $availableRoll) {
        $groupKey = implode('|', [
            (string)($availableRoll['sku_code'] ?? ''),
            (string)($availableRoll['sku_description'] ?? ''),
            (string)($availableRoll['grams'] ?? ''),
            (string)($availableRoll['width_mm'] ?? ''),
            trim((string)($availableRoll['color'] ?? '')),
            (string)($availableRoll['meters'] ?? ''),
            (string)($availableRoll['process_stage'] ?? ''),
        ]);
        if (!isset($availableRollsByGroup[$groupKey])) {
            $availableRollsByGroup[$groupKey] = [];
        }
        $availableRollsByGroup[$groupKey][] = $availableRoll;
    }

    $rollRequests = array_values(array_filter(
        $materialRequests,
        static fn(array $request): bool => strtoupper(trim((string)($request['request_type'] ?? 'ROLL'))) === 'ROLL'
    ));
    $activeRolls = $service instanceof ReceptionService ? $service->listActiveRollsInWorkOrder((int)$ot['id']) : [];
    $activeRollIds = [];
    $activeRollsById = [];
    foreach ($activeRolls as $activeRoll) {
        $activeRollId = (int)($activeRoll['id'] ?? 0);
        if ($activeRollId <= 0) {
            continue;
        }
        $activeRollIds[$activeRollId] = true;
        $activeRollsById[$activeRollId] = $activeRoll;
    }
    $deliveriesByRequest = [];
    $deliveredRollRequestMap = [];
    if ($service instanceof ReceptionService) {
        foreach ($rollRequests as $rollRequest) {
            $requestId = (int)($rollRequest['id'] ?? 0);
            if ($requestId <= 0) {
                continue;
            }
            $deliveries = $service->listMaterialDeliveriesByRequest($requestId);
            $deliveredRolls = [];
            foreach ($deliveries as $delivery) {
                $deliveredRollId = (int)($delivery['roll_id'] ?? 0);
                if ($deliveredRollId <= 0 || isset($deliveredRolls[$deliveredRollId])) {
                    continue;
                }
                $deliveredRoll = $service->getRoll($deliveredRollId);
                if (!is_array($deliveredRoll)) {
                    continue;
                }
                $deliveredRolls[$deliveredRollId] = $deliveredRoll;
                $deliveredRollRequestMap[$deliveredRollId] = $requestId;
            }
            $deliveriesByRequest[$requestId] = array_values($deliveredRolls);
        }
    }
    $rollHistory = $service instanceof ReceptionService ? $service->listWorkOrderRollHistory((int)$ot['id']) : [];
    $activeAttachEventIds = [];
    $openAttachEventIdByRoll = [];
    foreach (array_reverse($rollHistory) as $historyRow) {
        $eventType = strtoupper(trim((string)($historyRow['type'] ?? '')));
        $historyEventId = (int)($historyRow['id'] ?? 0);
        $historyRollId = (int)($historyRow['roll_id'] ?? 0);
        if ($historyEventId <= 0 || $historyRollId <= 0) {
            continue;
        }
        if ($eventType === 'WORK_ORDER_ROLL_ATTACHED') {
            // Solo la ultima entrada vigente por bobina debe quedar operable en la grilla.
            $openAttachEventIdByRoll[$historyRollId] = $historyEventId;
            continue;
        }
        if ($eventType === 'WORK_ORDER_ROLL_RELEASED') {
            unset($openAttachEventIdByRoll[$historyRollId]);
        }
    }
    foreach ($openAttachEventIdByRoll as $openAttachEventId) {
        $activeAttachEventIds[(int)$openAttachEventId] = true;
    }
    $activeRollRequestMap = [];
    $activeRollRequestByRollId = [];
    foreach ($rollHistory as $historyRow) {
        $eventType = strtoupper(trim((string)($historyRow['type'] ?? '')));
        $historyEventId = (int)($historyRow['id'] ?? 0);
        if ($eventType !== 'WORK_ORDER_ROLL_ATTACHED' || !isset($activeAttachEventIds[$historyEventId])) {
            continue;
        }
        $historyRollId = (int)($historyRow['roll_id'] ?? 0);
        if ($historyRollId <= 0 || !isset($activeRollsById[$historyRollId])) {
            continue;
        }
        $payload = isset($historyRow['payload_data']) && is_array($historyRow['payload_data']) ? $historyRow['payload_data'] : [];
        $historyRequestId = (int)($payload['request_id'] ?? ($deliveredRollRequestMap[$historyRollId] ?? 0));
        if ($historyRequestId <= 0) {
            continue;
        }
        if (!isset($activeRollRequestMap[$historyRequestId])) {
            $activeRollRequestMap[$historyRequestId] = [];
        }
        $activeRollRequestMap[$historyRequestId][$historyRollId] = $activeRollsById[$historyRollId];
        $activeRollRequestByRollId[$historyRollId] = $historyRequestId;
    }
    $movementRows = [];
    foreach ($rollHistory as $historyRow) {
        $eventType = strtoupper(trim((string)($historyRow['type'] ?? '')));
        $payload = isset($historyRow['payload_data']) && is_array($historyRow['payload_data']) ? $historyRow['payload_data'] : [];
        $isAttach = $eventType === 'WORK_ORDER_ROLL_ATTACHED';
        $isRelease = $eventType === 'WORK_ORDER_ROLL_RELEASED';
        if (!$isAttach && !$isRelease) {
            continue;
        }
        $historyRollId = (int)($historyRow['roll_id'] ?? 0);
        $historyEventId = (int)($historyRow['id'] ?? 0);
        $requestIdForRow = (int)($payload['request_id'] ?? ($deliveredRollRequestMap[$historyRollId] ?? 0));
        $quantityValue = $isAttach
            ? (float)($payload['process_weight_kg'] ?? 0)
            : (float)($payload['final_weight_kg'] ?? 0);
        $movementRows[] = [
            'event_id' => $historyEventId,
            'transaction' => ($isAttach ? 'IA' : 'SA') . str_pad((string)((int)($historyRow['id'] ?? 0)), 7, '0', STR_PAD_LEFT),
            'date_time' => formatLabelDateTime((string)($historyRow['created_at'] ?? '')),
            'material' => trim((string)($historyRow['sku_description'] ?? '')) !== '' ? (string)$historyRow['sku_description'] : '-',
            'code' => trim((string)($historyRow['roll_code'] ?? '')) !== '' ? (string)$historyRow['roll_code'] : '-',
            'quantity' => rtrim(rtrim(number_format($quantityValue, 3, '.', ''), '0'), '.') . ' Kilogramos',
            'type' => $isAttach ? 'Entrada' : 'Salida',
            'request_id' => $requestIdForRow,
            'roll_id' => $historyRollId,
            'is_active' => $isAttach && isset($activeAttachEventIds[$historyEventId]),
        ];
    }

    $setupActionButtons = '<div class="legacy-setup-actions">'
        . '<a class="legacy-setup-action request" href="/work-orders/' . (int)$ot['id'] . '/request-materials" target="_blank" rel="noopener">Solicitar materiales</a>'
        . '<a class="legacy-setup-action materials active" href="/work-orders/' . (int)$ot['id'] . '/materials">Utilizar materiales</a>'
        . '<a class="legacy-setup-action maintenance" href="#">Registrar mantención</a>'
        . '<a class="legacy-setup-action pause" href="#">Pausa</a>'
        . '<a class="legacy-setup-action consumption" href="#">Consumo</a>'
        . '</div>';

    $body = '<div class="legacy-screen-title">Utilizar materiales</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h((string)$ot['ot_code']) . ' &gt; Utilizar materiales</div>'
        . '<div class="legacy-actionbar"><div class="row"><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/setup">Volver a alistamiento</a></div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Informacion Cliente</th></tr></thead><tbody>';
    foreach ($legacyClientRows as $legacyRow) {
        $body .= '<tr><td class="legacy-label-cell">' . h((string)$legacyRow[0]) . '</td><td class="legacy-value-cell">' . $legacyRow[1] . '</td><td class="legacy-label-cell">' . h((string)$legacyRow[2]) . '</td><td class="legacy-value-cell">' . $legacyRow[3] . '</td></tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Información de Fabricación</th></tr></thead><tbody>';
    foreach ($legacyFabricationRows as $legacyRow) {
        if (trim((string)$legacyRow[0]) === '' && trim((string)$legacyRow[1]) === '' && trim((string)$legacyRow[2]) === '' && trim((string)$legacyRow[3]) === '') {
            $body .= '<tr><td class="legacy-value-cell" colspan="4" style="background:#f3f4f6;padding:8px"></td></tr>';
            continue;
        }
        $body .= '<tr><td class="legacy-label-cell">' . h((string)$legacyRow[0]) . '</td><td class="legacy-value-cell">' . $legacyRow[1] . '</td><td class="legacy-label-cell">' . h((string)$legacyRow[2]) . '</td><td class="legacy-value-cell">' . $legacyRow[3] . '</td></tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= renderWorkOrderAniloxConfigCard((int)$ot['id'], $aniloxSlots, $aniloxCatalog, $suggestedAniloxColors);
    $body .= $setupActionButtons;
    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="7" style="background:#d94841;border-color:#d94841">Egresos/Ingresos contabilizados</th></tr></thead><tbody>';
    $body .= '<tr><td class="legacy-value-cell" colspan="7" style="padding:0"><div class="table-wrap"><table class="table-compact" style="width:100%"><thead><tr><th>Transacción</th><th>Fecha/Hora</th><th>Material</th><th>Código</th><th>Cantidad</th><th>Tipo</th><th>Opciones</th></tr></thead><tbody>';
    if ($movementRows === []) {
        $body .= '<tr><td colspan="7" class="muted">Todavía no hay movimientos realizados en esta orden de trabajo.</td></tr>';
    } else {
        foreach ($movementRows as $movementRow) {
            $rowEventId = (int)($movementRow['event_id'] ?? 0);
            $rowRequestId = (int)($movementRow['request_id'] ?? 0);
            $rowRollId = (int)($movementRow['roll_id'] ?? 0);
            $isActiveMovement = (bool)($movementRow['is_active'] ?? false);
            $rowReleaseModalId = 'material-row-release-' . $rowEventId;
            $rowOpenReleaseOnclick = ' onclick="var modal=document.getElementById(\'' . $rowReleaseModalId . '\'); if(modal){modal.style.display=\'block\';} return false;"';
            $rowCloseReleaseOnclick = ' onclick="var modal=document.getElementById(\'' . $rowReleaseModalId . '\'); if(modal){modal.style.display=\'none\';} return false;"';
            $rowActiveRoll = $activeRollsById[$rowRollId] ?? null;
            $rowReleaseWeight = is_array($rowActiveRoll) && isset($rowActiveRoll['weight_kg'])
                ? rtrim(rtrim(number_format((float)$rowActiveRoll['weight_kg'], 2, '.', ''), '0'), '.')
                : '';
            $rowOptions = $isActiveMovement
                ? '<div style="display:flex;gap:6px;align-items:center">'
                    . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/materials/remove" onsubmit="return confirm(\'¿Eliminar esta entrada por error?\')" style="margin:0">'
                    . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                    . '<input type="hidden" name="request_id" value="' . $rowRequestId . '">'
                    . '<input type="hidden" name="roll_id" value="' . $rowRollId . '">'
                    . '<button class="btn" type="submit" style="background:#d94841;border-color:#d94841;padding:6px 14px"' . ($rowRequestId > 0 && $rowRollId > 0 ? '' : ' disabled') . '>Eliminar</button>'
                    . '</form>'
                    . '<button class="btn secondary" type="button"' . $rowOpenReleaseOnclick . ($rowRollId > 0 ? '' : ' disabled') . '>Salida</button>'
                    . '<div class="legacy-inline-modal" id="' . $rowReleaseModalId . '" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto">'
                    . '<div style="max-width:520px;margin:40px auto;background:#fff;border-radius:8px;box-shadow:0 12px 30px rgba(15,23,42,.22);padding:18px">'
                    . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Salida de bobina</div><button type="button" class="btn secondary"' . $rowCloseReleaseOnclick . '>Cerrar</button></div>'
                    . '<div class="muted" style="margin-bottom:12px">Registra los kilos que salieron de la máquina y la merma generada.</div>'
                    . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/materials/release" data-release-form>'
                    . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                    . '<input type="hidden" name="request_id" value="' . $rowRequestId . '">'
                    . '<label>Bobina activa</label>'
                    . '<input type="hidden" name="roll_id" value="' . $rowRollId . '">'
                    . '<input type="text" value="' . h((string)($movementRow['code'] ?? '-')) . '" readonly>'
                    . '<label style="margin-top:10px">Kilos que salieron de la máquina</label>'
                    . '<input name="final_weight_kg" type="number" step="0.001" min="0" value="' . h($rowReleaseWeight) . '" required placeholder="Ej: 8.600">'
                    . '<label style="margin-top:10px">Metros lineales restantes</label>'
                    . '<input name="final_meters" type="number" step="0.001" min="0" value="' . h((string)($rowActiveRoll['meters'] ?? '')) . '" placeholder="Ej: 1800">'
                    . '<input name="waste_kg" type="hidden" value="' . h($releaseWasteTotalState) . '" data-release-waste-total>'
                    . '<div style="font-weight:700;margin-top:12px;margin-bottom:8px">Mermas de salida</div>'
                    . '<div style="display:grid;grid-template-columns:120px minmax(0,1fr) 86px minmax(0,1fr);gap:8px;align-items:center;margin-bottom:8px"><div style="font-weight:700">Ajuste / Aprobación</div><div><input type="number" step="0.001" min="0" name="release_waste_detail_weight[approval]" value="" data-release-waste-input placeholder="Kg" style="width:100%;box-sizing:border-box"></div><div style="font-size:12px;color:#555">Comentario</div><div><input type="text" name="release_waste_detail_comment[approval]" value="" style="width:100%;box-sizing:border-box"></div></div>'
                    . '<div style="display:grid;grid-template-columns:120px minmax(0,1fr) 86px minmax(0,1fr);gap:8px;align-items:center;margin-bottom:8px"><div style="font-weight:700">Impresión</div><div><input type="number" step="0.001" min="0" name="release_waste_detail_weight[printing]" value="" data-release-waste-input placeholder="Kg" style="width:100%;box-sizing:border-box"></div><div style="font-size:12px;color:#555">Comentario</div><div><input type="text" name="release_waste_detail_comment[printing]" value="" style="width:100%;box-sizing:border-box"></div></div>'
                    . '<div style="display:grid;grid-template-columns:120px minmax(0,1fr) 86px minmax(0,1fr);gap:8px;align-items:center;margin-bottom:8px"><div style="font-weight:700">Merma Proceso</div><div><input type="number" step="0.001" min="0" name="release_waste_detail_weight[process]" value="" data-release-waste-input placeholder="Kg" style="width:100%;box-sizing:border-box"></div><div style="font-size:12px;color:#555">Comentario</div><div><input type="text" name="release_waste_detail_comment[process]" value="" style="width:100%;box-sizing:border-box"></div></div>'
                    . '<div style="display:grid;grid-template-columns:120px minmax(0,1fr) 86px minmax(0,1fr);gap:8px;align-items:center;margin-bottom:8px"><div style="font-weight:700">Otros</div><div><input type="number" step="0.001" min="0" name="release_waste_detail_weight[other]" value="" data-release-waste-input placeholder="Kg" style="width:100%;box-sizing:border-box"></div><div style="font-size:12px;color:#555">Comentario</div><div><input type="text" name="release_waste_detail_comment[other]" value="" style="width:100%;box-sizing:border-box"></div></div>'
                    . '<label style="margin-top:10px">Merma total</label>'
                    . '<input type="text" value="' . h($releaseWasteTotalState) . ' Kg" readonly data-release-waste-display>'
                    . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px"><button class="btn secondary" type="button"' . $rowCloseReleaseOnclick . '>Cancelar</button><button class="btn" type="submit">Guardar salida</button></div>'
                    . '</form></div></div>'
                    . '</div>'
                : '<div class="muted">Realizado</div>';
            $body .= '<tr>'
                . '<td>' . h($movementRow['transaction']) . '</td>'
                . '<td>' . h($movementRow['date_time']) . '</td>'
                . '<td>' . h($movementRow['material']) . '</td>'
                . '<td>' . h($movementRow['code']) . '</td>'
                . '<td>' . h($movementRow['quantity']) . '</td>'
                . '<td>' . h($movementRow['type']) . '</td>'
                . '<td>' . $rowOptions . '</td>'
                . '</tr>';
        }
    }
    $body .= '</tbody></table></div></td></tr></tbody></table></div>';
    if ($currentRoll !== null) {
        $releaseWeight = isset($currentRoll['weight_kg'])
            ? rtrim(rtrim(number_format((float)$currentRoll['weight_kg'], 2, '.', ''), '0'), '.')
            : '';
        $currentRollId = (int)($currentRoll['id'] ?? 0);
        $currentRollRequestId = (int)($activeRollRequestByRollId[$currentRollId] ?? 0);
        $releaseMaterialLabel = trim((string)($currentRoll['sku_description'] ?? '')) !== '' ? (string)$currentRoll['sku_description'] : '-';
        $releaseGsmValue = isset($currentRoll['grams']) && (string)($currentRoll['grams'] ?? '') !== ''
            ? rtrim(rtrim(number_format((float)$currentRoll['grams'], 2, '.', ''), '0'), '.')
            : '-';
        $releaseWidthValue = isset($currentRoll['width_mm']) && (string)($currentRoll['width_mm'] ?? '') !== ''
            ? rtrim(rtrim(number_format(((float)$currentRoll['width_mm']) / 10, 2, '.', ''), '0'), '.')
            : '-';
        $releaseBaseMaterial = '-';
        if ($releaseMaterialLabel !== '-' && $releaseMaterialLabel !== '') {
            $releaseParts = explode('/', $releaseMaterialLabel);
            $releaseBaseMaterial = trim((string)($releaseParts[0] ?? ''));
            if ($releaseBaseMaterial === '') {
                $releaseBaseMaterial = $releaseMaterialLabel;
            }
        }
        $releaseClientLabel = trim((string)($ot['erp_customer_name'] ?? '')) !== '' ? (string)$ot['erp_customer_name'] : '-';
        $releaseRequestLabel = trim((string)($ot['erp_req_id'] ?? '')) !== '' ? (string)$ot['erp_req_id'] : (string)$ot['ot_code'];
        $releaseDesignLabel = trim((string)($ot['erp_plan_desc'] ?? '')) !== '' ? (string)$ot['erp_plan_desc'] : (string)$ot['sku_final'];
        $releaseColorCountLabel = $configuredColorCount > 0 ? (string)$configuredColorCount : '-';
        $body .= '<div class="legacy-inline-modal" id="material-modal-active-release" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto">'
            . '<div style="max-width:780px;margin:10px auto;background:#f6f6f3;border-radius:10px;box-shadow:0 12px 30px rgba(15,23,42,.22);padding:14px">'
            . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Salida de bobina</div><button type="button" class="btn secondary" data-material-modal-close="active-release">Cerrar</button></div>'
            . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/materials/release" data-release-form>'
            . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
            . '<input type="hidden" name="request_id" value="' . $currentRollRequestId . '">'
            . '<input type="hidden" name="roll_id" value="' . $currentRollId . '">'
            . '<div style="background:#fff;border:1px solid #d4d4cf;border-radius:4px;overflow:hidden;margin-bottom:12px">'
            . '<div style="background:#18a8a3;color:#fff;font-weight:800;padding:8px 10px">Información del producto : Tela</div>'
            . '<div style="padding:10px"><table class="legacy-sheet-table" style="margin:0"><tbody>'
            . '<tr><td class="legacy-label-cell">Codigo</td><td class="legacy-value-cell">' . h((string)($currentRoll['sku_code'] ?? '-')) . '</td><td class="legacy-label-cell"></td><td class="legacy-value-cell"></td></tr>'
            . '<tr><td class="legacy-label-cell">Descripción</td><td class="legacy-value-cell" colspan="3">' . h($releaseMaterialLabel) . '</td></tr>'
            . '<tr><td class="legacy-label-cell">Gramaje</td><td class="legacy-value-cell">' . h($releaseGsmValue) . '</td><td class="legacy-label-cell">Ancho Rollo</td><td class="legacy-value-cell">' . h($releaseWidthValue) . '</td></tr>'
            . '<tr><td class="legacy-label-cell">Color Tela</td><td class="legacy-value-cell">' . h(trim((string)($currentRoll['color'] ?? '')) !== '' ? (string)$currentRoll['color'] : '-') . '</td><td class="legacy-label-cell">Materialidad</td><td class="legacy-value-cell">' . h($releaseBaseMaterial) . '</td></tr>'
            . '</tbody></table></div></div>'
            . '<div style="background:#fff;border:1px solid #d4d4cf;border-radius:4px;overflow:hidden;margin-bottom:12px">'
            . '<div style="background:#18a8a3;color:#fff;font-weight:800;padding:8px 10px">Información de Confirmacion de Compras</div>'
            . '<div style="padding:10px"><table class="legacy-sheet-table" style="margin:0"><tbody>'
            . '<tr><td class="legacy-label-cell">Numero</td><td class="legacy-value-cell">' . h($releaseRequestLabel) . '</td><td class="legacy-label-cell">Cliente</td><td class="legacy-value-cell">' . h($releaseClientLabel) . '</td></tr>'
            . '<tr><td class="legacy-label-cell">Diseño</td><td class="legacy-value-cell">' . h($releaseDesignLabel) . '</td><td class="legacy-label-cell">Impreso en</td><td class="legacy-value-cell">FLEXOGRAFIA</td></tr>'
            . '<tr><td class="legacy-label-cell">Color Frente</td><td class="legacy-value-cell">' . h($releaseColorCountLabel) . '</td><td class="legacy-label-cell">Color Dorso</td><td class="legacy-value-cell">' . h($releaseColorCountLabel) . '</td></tr>'
            . '</tbody></table></div></div>'
            . '<div style="background:#fff;border:1px solid #d4d4cf;border-radius:4px;overflow:hidden">'
            . '<div style="background:#18a8a3;color:#fff;font-weight:800;padding:8px 10px">Opciones de salida de stock</div>'
            . '<div style="padding:10px">'
            . '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:14px;margin-bottom:12px">'
            . '<span style="font-weight:700">Opción de Ingreso</span>'
            . '<label style="display:flex;align-items:center;gap:4px"><input type="radio" disabled>Por unidad</label>'
            . '<label style="display:flex;align-items:center;gap:4px"><input type="radio" disabled>Por metros</label>'
            . '<label style="display:flex;align-items:center;gap:4px"><input type="radio" checked>Por peso</label>'
            . '</div>'
            . '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px">'
            . '<label style="font-weight:700;min-width:180px;margin:0">Kilogramos</label>'
            . '<input id="active-release-final-weight" name="final_weight_kg" type="number" step="0.001" min="0" value="' . h($releaseFinalWeightState) . '" required style="max-width:180px">'
            . '<span>kg</span>'
            . '</div>'
            . '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px">'
            . '<label style="font-weight:700;min-width:180px;margin:0">Metros lineales restantes</label>'
            . '<input name="final_meters" type="number" step="0.001" min="0" value="' . h($releaseFinalMetersState) . '" style="max-width:180px">'
            . '<span>ML</span>'
            . '</div>'
            . '<input name="waste_kg" type="hidden" value="' . h($releaseWasteTotalState) . '" data-release-waste-total>'
            . '<div style="margin-top:10px">'
            . '<div style="font-weight:700;margin-bottom:8px">Mermas de salida</div>';
        foreach ($releaseWasteRows as $releaseWasteRow) {
            $body .= '<div style="display:grid;grid-template-columns:140px minmax(160px,1fr) 78px 92px 92px minmax(180px,1fr);gap:8px;align-items:center;margin-bottom:8px">'
                . '<div style="font-weight:700">Merma</div>'
                . '<div>' . h((string)$releaseWasteRow['label']) . '</div>'
                . '<div><select disabled style="width:100%"><option>Kilos</option></select></div>'
                . '<div><input type="number" step="0.001" min="0" name="release_waste_detail_weight[' . h((string)$releaseWasteRow['key']) . ']" value="' . h((string)($releaseWasteWeightState[$releaseWasteRow['key']] ?? '')) . '" data-release-waste-input style="width:100%"></div>'
                . '<div style="font-size:12px;color:#555">Comentarios</div>'
                . '<div><input type="text" name="release_waste_detail_comment[' . h((string)$releaseWasteRow['key']) . ']" value="' . h((string)($releaseWasteCommentState[$releaseWasteRow['key']] ?? '')) . '" style="width:100%"></div>'
                . '</div>';
        }
        $body .= '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:6px">'
            . '<label style="font-weight:700;min-width:180px;margin:0">Merma total</label>'
            . '<input type="text" value="' . h($releaseWasteTotalState) . ' Kg" readonly data-release-waste-display style="max-width:180px;background:#f3f4f6">'
            . '<span>calculada automaticamente</span>'
            . '</div>'
            . '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:16px"><button class="btn secondary" type="button" data-material-modal-close="active-release">Volver</button><button class="btn" type="submit">Guardar</button></div>'
            . '</div></div>'
            . '</form></div></div>';
    }
    $body .= '<div class="legacy-sheet-card"><table class="legacy-sheet-table"><thead><tr><th colspan="9">Bobinas solicitadas</th></tr></thead><tbody>';
    $body .= '<tr><td class="legacy-label-cell">Resumen</td><td class="legacy-value-cell" colspan="8">Muestra las bobinas pendientes por ingresar a la máquina.</td></tr>';
    $body .= '<tr><td class="legacy-label-cell">Ingreso</td><td class="legacy-value-cell" colspan="8"><div class="table-wrap"><table class="table-compact"><thead><tr><th>Descripción</th><th>SKU</th><th>Código bobina</th><th>Ancho (cm)</th><th>Longitud (m)</th><th>GSM</th><th>Color</th><th>Kg</th><th>Entrada</th></tr></thead><tbody>';

    foreach ($rollRequests as $request) {
        $groupData = parseMaterialRequestGroupKey((string)($request['requested_group_key'] ?? ''));
        $requestId = (int)($request['id'] ?? 0);
        $matchingRollsById = [];
        foreach (($deliveriesByRequest[$requestId] ?? []) as $deliveredRequestRoll) {
            $deliveredRequestRollId = (int)($deliveredRequestRoll['id'] ?? 0);
            if ($deliveredRequestRollId <= 0 || isset($matchingRollsById[$deliveredRequestRollId])) {
                continue;
            }
            $matchingRollsById[$deliveredRequestRollId] = $deliveredRequestRoll;
        }
        $deliveredRollId = (int)($request['delivered_roll_id'] ?? 0);
        if ($deliveredRollId > 0 && !isset($matchingRollsById[$deliveredRollId]) && $service instanceof ReceptionService) {
            $deliveredRoll = $service->getRoll($deliveredRollId);
            if (is_array($deliveredRoll)) {
                $matchingRollsById[$deliveredRollId] = $deliveredRoll;
            }
        }
        foreach (($activeRollRequestMap[$requestId] ?? []) as $activeRequestRollId => $activeRequestRoll) {
            if ($activeRequestRollId <= 0 || isset($matchingRollsById[$activeRequestRollId])) {
                continue;
            }
            $matchingRollsById[$activeRequestRollId] = $activeRequestRoll;
        }
        foreach (($availableRollsByGroup[(string)($request['requested_group_key'] ?? '')] ?? []) as $availableGroupRoll) {
            $availableGroupRollId = (int)($availableGroupRoll['id'] ?? 0);
            if ($availableGroupRollId <= 0 || isset($matchingRollsById[$availableGroupRollId])) {
                continue;
            }
            $matchingRollsById[$availableGroupRollId] = $availableGroupRoll;
        }
        $matchingRolls = array_values($matchingRollsById);
        $activeRequestRolls = array_values(array_filter(
            $matchingRolls,
            static fn(array $roll): bool => isset($activeRollIds[(int)($roll['id'] ?? 0)])
        ));
        $referenceRoll = $matchingRolls[0] ?? null;
        $requestedQty = (float)($request['requested_qty'] ?? 0);
        $deliveredQty = (float)($request['delivered_qty'] ?? 0);
        $pendingQty = max(0.0, $requestedQty - $deliveredQty);
        $requestGroupKey = (string)($request['requested_group_key'] ?? '');
        $rowHasActiveRoll = $activeRequestRolls !== [];
        $widthCmLabel = ($groupData['width_mm'] !== '' && is_numeric($groupData['width_mm']))
            ? rtrim(rtrim(number_format(((float)$groupData['width_mm']) / 10, 2, '.', ''), '0'), '.')
            : '-';
        $metersLabel = $groupData['meters'] !== '' ? $groupData['meters'] : '-';
        $gramsLabel = $groupData['grams'] !== '' ? $groupData['grams'] : '-';
        $weightLabel = $referenceRoll !== null && (string)($referenceRoll['weight_kg'] ?? '') !== ''
            ? rtrim(rtrim(number_format((float)$referenceRoll['weight_kg'], 2, '.', ''), '0'), '.')
            : '-';
        $materialLabel = trim((string)($groupData['sku_description'] !== '' ? $groupData['sku_description'] : (string)($request['requested_item'] ?? '-')));
        $materialBaseLabel = '-';
        if ($materialLabel !== '' && $materialLabel !== '-') {
            $materialParts = explode('/', $materialLabel);
            $materialBaseLabel = trim((string)($materialParts[0] ?? ''));
            if ($materialBaseLabel === '') {
                $materialBaseLabel = $materialLabel;
            }
        }
        $selectedRollCode = $referenceRoll !== null && trim((string)($referenceRoll['roll_code'] ?? '')) !== ''
            ? (string)$referenceRoll['roll_code']
            : '-';
        $selectedRollWeight = $referenceRoll !== null && isset($referenceRoll['weight_kg'])
            ? rtrim(rtrim(number_format((float)$referenceRoll['weight_kg'], 3, '.', ''), '0'), '.')
            : '';
        $entryHtml = '<div class="muted">Sin disponibilidad en bodega.</div>';
        if (!workOrderCanReceiveMaterials((string)($ot['status'] ?? ''))) {
            $entryHtml = '<div class="muted">La OT ya no admite ingresos.</div>';
        } else {
            $canOpenAttachModal = in_array((string)($request['status'] ?? ''), ['PENDING', 'ACCEPTED', 'PARTIAL', 'DELIVERED'], true)
                && $matchingRolls !== [];
            $canAttach = $canOpenAttachModal;
            $canRelease = $rowHasActiveRoll;
            $attachDisabled = $canOpenAttachModal ? '' : ' disabled';
            $releaseDisabled = $canRelease ? '' : ' disabled';
            $attachButtonClass = $canOpenAttachModal ? 'btn' : 'btn secondary';
            $releaseButtonClass = $canRelease ? 'btn secondary' : 'btn secondary';
            $openAttachOnclick = ' onclick="var modal=document.getElementById(\'material-modal-attach-' . $requestId . '\'); if(modal){modal.style.display=\'block\';} return false;"';
            $openReleaseOnclick = ' onclick="var modal=document.getElementById(\'material-modal-release-' . $requestId . '\'); if(modal){modal.style.display=\'block\';} return false;"';
            $closeAttachOnclick = ' onclick="var modal=document.getElementById(\'material-modal-attach-' . $requestId . '\'); if(modal){modal.style.display=\'none\';} return false;"';
            $closeReleaseOnclick = ' onclick="var modal=document.getElementById(\'material-modal-release-' . $requestId . '\'); if(modal){modal.style.display=\'none\';} return false;"';
            $entryHtml = '<div style="display:flex;gap:8px;flex-wrap:wrap">'
                . '<button class="' . $attachButtonClass . '" type="button" data-material-modal-open="attach-' . $requestId . '"' . $openAttachOnclick . $attachDisabled . '>Entrada</button>'
                . '<button class="' . $releaseButtonClass . '" type="button" data-material-modal-open="release-' . $requestId . '"' . $openReleaseOnclick . $releaseDisabled . '>Salida</button>'
                . '</div>';
            if ((string)($request['status'] ?? '') === 'PENDING') {
                $entryHtml .= '<div class="muted" style="margin-top:6px">Pendiente de bodega.</div>';
            } elseif ((string)($request['status'] ?? '') === 'DELIVERED' && $matchingRolls !== []) {
                $entryHtml .= '<div class="muted" style="margin-top:6px">Entregadas por bodega: ' . h(implode(', ', array_map(static fn(array $roll): string => (string)($roll['roll_code'] ?? '-'), $matchingRolls))) . '</div>';
            } elseif ($rowHasActiveRoll) {
                $entryHtml .= '<div class="muted" style="margin-top:6px">Activas: ' . h(implode(', ', array_map(static fn(array $roll): string => (string)($roll['roll_code'] ?? '-'), $activeRequestRolls))) . '</div>';
            }

            $attachFormDisabled = $canAttach ? '' : ' disabled';
            $entryHtml .= '<div class="legacy-inline-modal" id="material-modal-attach-' . $requestId . '" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto">'
                . '<div style="max-width:780px;margin:10px auto;background:#f6f6f3;border-radius:10px;box-shadow:0 12px 30px rgba(15,23,42,.22);padding:14px">'
                . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Entrada de bobina</div><button type="button" class="btn secondary" data-material-modal-close="attach-' . $requestId . '"' . $closeAttachOnclick . '>Cerrar</button></div>'
                . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/materials/attach">'
                . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                . '<input type="hidden" name="request_id" value="' . $requestId . '">'
                . '<div style="background:#fff;border:1px solid #d4d4cf;border-radius:4px;overflow:hidden;margin-bottom:12px">'
                . '<div style="background:#18a8a3;color:#fff;font-weight:800;padding:8px 10px">Informacion del producto</div>'
                . '<div style="padding:10px">'
                . '<label style="font-weight:700;margin-bottom:6px">Bobina</label>'
                . '<select name="roll_id" required data-attach-roll-select="' . $requestId . '" style="margin-bottom:12px"' . $attachFormDisabled . '><option value="">Seleccionar bobina</option>';
            foreach ($matchingRolls as $matchingRoll) {
                $matchingRollCode = trim((string)($matchingRoll['roll_code'] ?? '')) !== '' ? (string)$matchingRoll['roll_code'] : '-';
                $matchingRollWeight = rtrim(rtrim(number_format((float)($matchingRoll['weight_kg'] ?? 0), 3, '.', ''), '0'), '.');
                $entryHtml .= '<option value="' . (int)$matchingRoll['id'] . '"'
                    . ' data-roll-code="' . h($matchingRollCode) . '"'
                    . ' data-roll-weight="' . h($matchingRollWeight !== '' ? $matchingRollWeight : '0') . '"'
                    . ' data-roll-warehouse="' . h((string)($matchingRoll['warehouse_name'] ?? '-')) . '"'
                    . '>' . h($matchingRollCode) . ' · ' . h((string)($matchingRoll['warehouse_name'] ?? '-')) . ' · ' . h(rtrim(rtrim(number_format((float)($matchingRoll['weight_kg'] ?? 0), 2, '.', ''), '0'), '.')) . ' Kg</option>';
            }
            $entryHtml .= '</select>'
                . '<table class="legacy-sheet-table" style="margin:0"><tbody>'
                . '<tr><td class="legacy-label-cell">Categoria</td><td class="legacy-value-cell">Tela</td></tr>'
                . '<tr><td class="legacy-label-cell">Material</td><td class="legacy-value-cell">' . h($materialLabel !== '' ? $materialLabel : '-') . '</td></tr>'
                . '<tr><td class="legacy-label-cell">SKU</td><td class="legacy-value-cell">' . h($groupData['sku_code'] !== '' ? $groupData['sku_code'] : '-') . '</td></tr>'
                . '<tr><td class="legacy-label-cell">Codigo bobina</td><td class="legacy-value-cell"><span id="attach-roll-code-' . $requestId . '">' . h($selectedRollCode) . '</span></td></tr>'
                . '<tr><td class="legacy-label-cell">Ancho</td><td class="legacy-value-cell">' . h($widthCmLabel) . ' cm</td></tr>'
                . '<tr><td class="legacy-label-cell">Longitud</td><td class="legacy-value-cell">' . h($metersLabel) . ' m</td></tr>'
                . '<tr><td class="legacy-label-cell">GSM</td><td class="legacy-value-cell">' . h($gramsLabel) . ' gr</td></tr>'
                . '<tr><td class="legacy-label-cell">Kilogramos</td><td class="legacy-value-cell"><span id="attach-roll-kg-' . $requestId . '">' . h($selectedRollWeight !== '' ? $selectedRollWeight : '-') . '</span> kg</td></tr>'
                . '<tr><td class="legacy-label-cell">Ancho de rollo</td><td class="legacy-value-cell">' . h($groupData['width_mm'] !== '' ? $groupData['width_mm'] : '-') . '</td></tr>'
                . '<tr><td class="legacy-label-cell">Gramaje tela</td><td class="legacy-value-cell">' . h($gramsLabel) . '</td></tr>'
                . '<tr><td class="legacy-label-cell">Longitud rollo</td><td class="legacy-value-cell">' . h($metersLabel) . '</td></tr>'
                . '<tr><td class="legacy-label-cell">Tela: Color</td><td class="legacy-value-cell">' . h($groupData['color'] !== '' ? $groupData['color'] : '-') . '</td></tr>'
                . '<tr><td class="legacy-label-cell">Tela: Material</td><td class="legacy-value-cell">' . h($materialBaseLabel) . '</td></tr>'
                . '<tr><td class="legacy-label-cell">Stock</td><td class="legacy-value-cell"><span id="attach-roll-stock-' . $requestId . '">' . h($selectedRollWeight !== '' ? $selectedRollWeight : '-') . '</span> kg</td></tr>'
                . '</tbody></table>'
                . '</div></div>'
                . '<div style="background:#fff;border:1px solid #d4d4cf;border-radius:4px;overflow:hidden">'
                . '<div style="background:#18a8a3;color:#fff;font-weight:800;padding:8px 10px">Opciones de retiro de stock</div>'
                . '<div style="padding:10px">'
                . '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:14px;margin-bottom:12px">'
                . '<span style="font-weight:700">Opcion de retiro</span>'
                . '<label style="display:flex;align-items:center;gap:4px"><input type="radio" disabled>Por unidad</label>'
                . '<label style="display:flex;align-items:center;gap:4px"><input type="radio" disabled>Por metros</label>'
                . '<label style="display:flex;align-items:center;gap:4px"><input type="radio" checked>Por peso</label>'
                . '</div>'
                . '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">'
                . '<label style="font-weight:700;min-width:120px;margin:0">Kilogramos</label>'
                . '<input name="process_weight_kg" type="number" step="0.001" min="0.001" required placeholder="Ej: 118.200" style="max-width:180px"' . $attachFormDisabled . '>'
                . '<span>kg</span>'
                . '</div>'
                . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px"><button class="btn secondary" type="button" data-material-modal-close="attach-' . $requestId . '"' . $closeAttachOnclick . '>Cancelar</button><button class="btn" type="submit"' . $attachFormDisabled . '>Guardar entrada</button></div>'
                . '</div></div>'
                . '</form></div></div>';

            $releaseRoll = $activeRequestRolls[0] ?? null;
            $releaseRollCode = $releaseRoll !== null ? (string)($releaseRoll['roll_code'] ?? '-') : '-';
            $releaseWeight = $releaseRoll !== null && isset($releaseRoll['weight_kg'])
                ? rtrim(rtrim(number_format((float)$releaseRoll['weight_kg'], 2, '.', ''), '0'), '.')
                : '';
            $entryHtml .= '<div class="legacy-inline-modal" id="material-modal-release-' . $requestId . '" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;padding:20px;overflow:auto">'
                . '<div style="max-width:520px;margin:40px auto;background:#fff;border-radius:8px;box-shadow:0 12px 30px rgba(15,23,42,.22);padding:18px">'
                . '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="font-size:18px;font-weight:800">Salida de bobina</div><button type="button" class="btn secondary" data-material-modal-close="release-' . $requestId . '"' . $closeReleaseOnclick . '>Cerrar</button></div>'
                . '<div class="muted" style="margin-bottom:12px">Registra los kilos que salieron de la máquina y la merma generada.</div>'
                . '<form method="post" action="/work-orders/' . (int)$ot['id'] . '/materials/release" data-release-form>'
                . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                . '<input type="hidden" name="request_id" value="' . $requestId . '">'
                . '<label>Bobina activa</label>';
            if (count($activeRequestRolls) > 1) {
                $entryHtml .= '<select name="roll_id" required>';
                foreach ($activeRequestRolls as $activeRequestRoll) {
                    $entryHtml .= '<option value="' . (int)($activeRequestRoll['id'] ?? 0) . '">' . h((string)($activeRequestRoll['roll_code'] ?? '-')) . ' · ' . h(rtrim(rtrim(number_format((float)($activeRequestRoll['weight_kg'] ?? 0), 2, '.', ''), '0'), '.')) . ' Kg</option>';
                }
                $entryHtml .= '</select>';
            } else {
                $entryHtml .= '<input type="hidden" name="roll_id" value="' . (int)($releaseRoll['id'] ?? 0) . '">'
                    . '<input type="text" value="' . h($releaseRollCode) . '" readonly>';
            }
            $entryHtml .= '<label style="margin-top:10px">Kilos que salieron de la máquina</label>'
                . '<input name="final_weight_kg" type="number" step="0.001" min="0" value="' . h($releaseWeight) . '" required placeholder="Ej: 8.600">'
                . '<label style="margin-top:10px">Metros lineales restantes</label>'
                . '<input name="final_meters" type="number" step="0.001" min="0" value="' . h((string)($releaseRoll['meters'] ?? '')) . '" placeholder="Ej: 1800">'
                . '<input name="waste_kg" type="hidden" value="' . h($releaseWasteTotalState) . '" data-release-waste-total>'
                . '<div style="font-weight:700;margin-top:12px;margin-bottom:8px">Mermas de salida</div>';
            foreach ($releaseWasteRows as $releaseWasteRow) {
                $entryHtml .= '<div style="display:grid;grid-template-columns:120px minmax(0,1fr) 86px minmax(0,1fr);gap:8px;align-items:center;margin-bottom:8px">'
                    . '<div style="font-weight:700">' . h((string)$releaseWasteRow['label']) . '</div>'
                    . '<div><input type="number" step="0.001" min="0" name="release_waste_detail_weight[' . h((string)$releaseWasteRow['key']) . ']" value="' . h((string)($releaseWasteWeightState[$releaseWasteRow['key']] ?? '')) . '" data-release-waste-input placeholder="Kg" style="width:100%;box-sizing:border-box"></div>'
                    . '<div style="font-size:12px;color:#555">Comentario</div>'
                    . '<div><input type="text" name="release_waste_detail_comment[' . h((string)$releaseWasteRow['key']) . ']" value="' . h((string)($releaseWasteCommentState[$releaseWasteRow['key']] ?? '')) . '" style="width:100%;box-sizing:border-box"></div>'
                    . '</div>';
            }
            $entryHtml .= '<label style="margin-top:10px">Merma total</label>'
                . '<input type="text" value="' . h($releaseWasteTotalState) . ' Kg" readonly data-release-waste-display>'
                . '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px"><button class="btn secondary" type="button" data-material-modal-close="release-' . $requestId . '"' . $closeReleaseOnclick . '>Cancelar</button><button class="btn" type="submit">Guardar salida</button></div>'
                . '</form></div></div>';
        }

        $body .= '<tr>';
        $body .= '<td>' . h($groupData['sku_description'] !== '' ? $groupData['sku_description'] : (string)($request['requested_item'] ?? '-')) . '</td>';
        $body .= '<td>' . h($groupData['sku_code'] !== '' ? $groupData['sku_code'] : '-') . '</td>';
        $body .= '<td>' . h($referenceRoll !== null && trim((string)($referenceRoll['roll_code'] ?? '')) !== '' ? (string)$referenceRoll['roll_code'] : '-') . '</td>';
        $body .= '<td>' . h($widthCmLabel) . '</td>';
        $body .= '<td>' . h($metersLabel) . '</td>';
        $body .= '<td>' . h($gramsLabel) . '</td>';
        $body .= '<td>' . h($groupData['color'] !== '' ? $groupData['color'] : '-') . '</td>';
        $body .= '<td>' . h($weightLabel) . '</td>';
        $body .= '<td>' . $entryHtml . '</td>';
        $body .= '</tr>';
    }
    if ($rollRequests === []) {
        $body .= '<tr><td colspan="9" class="muted">No hay telas pendientes por ingresar en esta orden de trabajo.</td></tr>';
    }
    $body .= '</tbody></table></div></td></tr></tbody></table></div>';
    $body .= '<script>
        (function () {
            function toggleModal(key, visible) {
                var modal = document.getElementById("material-modal-" + key);
                if (!modal) {
                    return;
                }
                modal.style.display = visible ? "block" : "none";
            }
            document.querySelectorAll("[data-material-modal-open]").forEach(function (button) {
                button.addEventListener("click", function () {
                    toggleModal(button.getAttribute("data-material-modal-open"), true);
                });
            });
            document.querySelectorAll("[data-material-modal-close]").forEach(function (button) {
                button.addEventListener("click", function () {
                    toggleModal(button.getAttribute("data-material-modal-close"), false);
                });
            });
            document.querySelectorAll("form[data-release-form]").forEach(function (form) {
                function syncReleaseWasteTotal() {
                    var total = 0;
                    form.querySelectorAll("[data-release-waste-input]").forEach(function (input) {
                        var value = parseFloat(input.value || "0");
                        if (isFinite(value) && value > 0) {
                            total += value;
                        }
                    });
                    var normalized = total > 0 ? total.toFixed(3) : "0";
                    var hidden = form.querySelector("[data-release-waste-total]");
                    var display = form.querySelector("[data-release-waste-display]");
                    if (hidden) hidden.value = normalized;
                    if (display) display.value = normalized + " Kg";
                }
                form.querySelectorAll("[data-release-waste-input]").forEach(function (input) {
                    input.addEventListener("input", syncReleaseWasteTotal);
                    input.addEventListener("change", syncReleaseWasteTotal);
                });
                syncReleaseWasteTotal();
            });
            document.querySelectorAll("[data-attach-roll-select]").forEach(function (select) {
                function updateAttachSummary() {
                    var requestId = select.getAttribute("data-attach-roll-select");
                    var option = select.options[select.selectedIndex];
                    var rollCode = option && option.getAttribute("data-roll-code") ? option.getAttribute("data-roll-code") : "-";
                    var rollWeight = option && option.getAttribute("data-roll-weight") ? option.getAttribute("data-roll-weight") : "-";
                    var codeNode = document.getElementById("attach-roll-code-" + requestId);
                    var kgNode = document.getElementById("attach-roll-kg-" + requestId);
                    var stockNode = document.getElementById("attach-roll-stock-" + requestId);
                    if (codeNode) {
                        codeNode.textContent = rollCode;
                    }
                    if (kgNode) {
                        kgNode.textContent = rollWeight;
                    }
                    if (stockNode) {
                        stockNode.textContent = rollWeight;
                    }
                }
                select.addEventListener("change", updateAttachSummary);
                updateAttachSummary();
            });
            document.querySelectorAll(".legacy-inline-modal").forEach(function (modal) {
                modal.addEventListener("click", function (event) {
                    if (event.target === modal) {
                        modal.style.display = "none";
                    }
                });
            });
        })();
    </script>';

    render('Utilizar materiales', $body);
}

/**
 * Renderiza la pantalla principal de una OT (flujo operativo).
 *
 * Esta pantalla actúa como “hub” y, según el estado/etapa:
 * - Muestra alistamiento, solicitud/uso de materiales, producción en curso, aprobación, etc.
 * - Si el usuario está en ERP y producción está en modo solo lectura, deriva a la vista read-only.
 *
 * ---
 *
 * Renders the main Work Order screen (operational flow).
 *
 * This screen acts as a “hub” and, depending on status/stage:
 * - Shows setup, materials request/usage, in-progress production, approvals, etc.
 * - If the user is in ERP and production is in read-only mode, it delegates to the read-only view.
 */
function renderWorkOrderStartScreen(
    array $ot,
    array $chemicals,
    ?array $currentRoll,
    array $rollHistory,
    array $chemicalInputs,
    ?array $lastStart,
    ?array $lastFinish,
    string $currentOperatorName,
    ?string $flashMessage = null,
    bool $flashIsError = false,
    array $formState = [],
    array $materialRequests = [],
    array $wastes = [],
    array $boxes = [],
    array $pallets = [],
    ?array $outputRoll = null,
    array $availableMaterialRolls = [],
    array $cutWarehouses = [],
    ?array $activeShiftSession = null,
    array $assignedCliches = [],
    array $clicheUsageHistory = []
): void {
    $service = $GLOBALS['service'] ?? null;
    if (isErpProductionReadOnlyMode()) {
        renderErpWorkOrderReadOnlyScreen($ot, $currentRoll, $rollHistory, $chemicalInputs, $lastStart, $lastFinish, $materialRequests, $wastes, $boxes, $pallets, $outputRoll, $assignedCliches, $clicheUsageHistory);
        return;
    }

    $status = (string)$ot['status'];
    $statusLabel = match ($status) {
        'ACTIVE' => 'Producción',
        'CUTTING' => 'Corte pendiente',
        'CLOSED' => 'Fabricada completa',
        default => 'Pendiente',
    };
    $finishApproval = $service instanceof ReceptionService ? $service->getLastWorkOrderFinishApproval((int)$ot['id']) : null;
    $finishApprovedAt = trim((string)($finishApproval['created_at'] ?? ''));
    $isFinishApproved = $finishApprovedAt !== '';
    $sealingSetupApproval = $service instanceof ReceptionService ? $service->getLastWorkOrderSealingSetupApproval((int)$ot['id']) : null;
    $sealingLastFinish = $service instanceof ReceptionService ? $service->getLastWorkOrderSealingFinish((int)$ot['id']) : null;
    $sealingFinished = false;
    if ($sealingLastFinish !== null) {
        if ($sealingSetupApproval === null) {
            $sealingFinished = true;
        } else {
            $finishTs = strtotime((string)($sealingLastFinish['created_at'] ?? ''));
            $setupTs = strtotime((string)($sealingSetupApproval['created_at'] ?? ''));
            $sealingFinished = ($finishTs === false || $setupTs === false) ? true : ($finishTs >= $setupTs);
        }
    }
    $sheetText = trim((string)($ot['erp_plan_desc'] ?? '') . ' ' . (string)($ot['sku_final'] ?? ''));
    $isStarted = $lastStart !== null;
    $isCutting = $status === 'CUTTING';
    $isClosed = $status === 'CLOSED';
    $showFinishData = !$isClosed
        && !$isCutting
        && $isStarted
        && (
            ((string)($_GET['finish_data'] ?? '0') === '1')
            || ((string)($formState['show_finish_data'] ?? '0') === '1')
        );
    $screenTitle = match ($status) {
        'ACTIVE' => $showFinishData ? 'Terminar producción' : 'En curso',
        'CUTTING' => $isFinishApproved ? ($sealingFinished ? 'Embalaje' : 'Selladora') : 'En corte',
        'CLOSED' => 'Historico',
        default => 'Inicio OT',
    };
    $showFinishShortcut = !$showFinishData && !$isClosed && !$isCutting && $isStarted;
    $targetQtyLabel = trim((string)($ot['erp_target_qty'] ?? '')) !== ''
        ? (string)$ot['erp_target_qty']
        : (trim((string)($ot['target_qty'] ?? '')) !== '' ? (string)$ot['target_qty'] : '-');
    $screenActionHtml = '<a class="btn secondary" href="/work-orders">Volver</a>';
    if ($showFinishShortcut) {
        $screenActionHtml .= '<a class="legacy-primary-btn" href="/work-orders/' . (int)$ot['id'] . '/finish-data">Terminar OT</a>';
    } elseif ($isCutting) {
        $screenActionHtml .= $isFinishApproved
            ? ($sealingFinished
                ? '<a class="legacy-primary-btn" href="/work-orders/' . (int)$ot['id'] . '/packaging?sealing_finished=1">Iniciar alistamiento Embalaje</a>'
                : '<a class="legacy-primary-btn" href="/work-orders/' . (int)$ot['id'] . '/sealing">Ir a Selladora</a>')
            : '<a class="legacy-primary-btn" href="/work-orders/' . (int)$ot['id'] . '/finish-approval">Validar cierre flexo</a>';
    }
    $body = '<div class="legacy-screen-title">' . h($screenTitle) . '</div>'
        . '<div class="legacy-breadcrumb">Ordenes de trabajo &gt; ' . h($screenTitle) . '</div>'
        . '<div class="legacy-actionbar"><div class="row">' . $screenActionHtml . '</div></div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    $expectedProcessStage = null;
    if ($status === 'ACTIVE') {
        $expectedProcessStage = 'PRODUCTION';
    } elseif ($status === 'CUTTING') {
        if (!$isFinishApproved) {
            $expectedProcessStage = 'CUTTING';
        } else {
            $expectedProcessStage = $sealingFinished ? 'PACKAGING' : 'SEALING';
        }
    }

    $sessionForDisplay = null;
    if ((int)($ot['shift_session_id'] ?? 0) > 0) {
        $sessionForDisplay = [
            'id' => (int)($ot['shift_session_id'] ?? 0),
            'machine_type_name' => (string)($ot['shift_machine_type_name'] ?? ''),
            'machine_name' => (string)($ot['shift_machine_name'] ?? ''),
            'machine_code' => (string)($ot['shift_machine_code'] ?? ''),
            'operator_name' => (string)($ot['shift_operator_name'] ?? ''),
            'helper_name' => (string)($ot['shift_helper_name'] ?? ''),
            'shift_label' => (string)($ot['shift_label'] ?? ''),
            'process_stage' => (string)($ot['shift_process_stage'] ?? ''),
            'comments' => (string)($ot['shift_comments'] ?? ''),
            'started_at' => (string)($activeShiftSession['started_at'] ?? ''),
            'work_order_id' => (int)$ot['id'],
        ];
    } elseif ($activeShiftSession !== null) {
        $sessionForDisplay = $activeShiftSession;
    }

    if ($sessionForDisplay !== null && $expectedProcessStage !== null) {
        $sessionProcessStage = strtoupper(trim((string)($sessionForDisplay['process_stage'] ?? '')));
        if ($sessionProcessStage !== '' && $sessionProcessStage !== $expectedProcessStage) {
            $sessionForDisplay = null;
        }
    }

    $operatorShiftMismatch = $activeShiftSession !== null
        && (int)($activeShiftSession['work_order_id'] ?? 0) > 0
        && (int)($activeShiftSession['work_order_id'] ?? 0) !== (int)$ot['id'];
    if ($operatorShiftMismatch) {
        $body .= '<div class="err" style="margin-bottom:12px">Tu turno activo está asociado a otra OT. Revisa la asignación de máquina antes de continuar con esta orden.</div>';
    }

    $stationLabel = trim((string)($sessionForDisplay['machine_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['machine_name']
        : (trim((string)($ot['erp_machine_label'] ?? '')) !== '' ? (string)$ot['erp_machine_label'] : 'Sin máquina asignada');
    $stationTypeLabel = trim((string)($sessionForDisplay['machine_type_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['machine_type_name']
        : 'Pendiente de asignación';
    $stationOperatorLabel = trim((string)($sessionForDisplay['operator_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['operator_name']
        : (trim((string)($ot['erp_worker_name'] ?? '')) !== '' ? (string)$ot['erp_worker_name'] : $currentOperatorName);
    $stationHelperLabel = trim((string)($sessionForDisplay['helper_name'] ?? '')) !== ''
        ? (string)$sessionForDisplay['helper_name']
        : '-';
    $stationShiftLabel = trim((string)($sessionForDisplay['shift_label'] ?? '')) !== ''
        ? (string)$sessionForDisplay['shift_label']
        : 'Sin turno iniciado';
    $stationStartedAt = trim((string)($sessionForDisplay['started_at'] ?? '')) !== ''
        ? (string)$sessionForDisplay['started_at']
        : '-';
    $stationCommentLabel = trim((string)($sessionForDisplay['comments'] ?? '')) !== ''
        ? (string)$sessionForDisplay['comments']
        : '-';
    $operationStageLabel = $sessionForDisplay !== null && trim((string)($sessionForDisplay['process_stage'] ?? '')) !== ''
        ? machineProcessStageLabel((string)$sessionForDisplay['process_stage'])
        : ($status === 'CUTTING' ? 'Corte' : ($status === 'CLOSED' ? 'Fabricación completa' : ($lastStart !== null ? 'Producción' : 'Preparación')));
    $nextOperationalArea = match (strtoupper(trim((string)($sessionForDisplay['process_stage'] ?? '')))) {
        'REWINDING' => 'Corte o embalaje',
        'PACKAGING' => 'Bodega final',
        'SEALING' => 'Corte o embalaje',
        'CUTTING' => 'Pallets y cierre final',
        default => ($status === 'CUTTING' ? 'Pallets y cierre final' : ($status === 'CLOSED' ? 'Proceso finalizado' : 'Rebobinado o corte')),
    };
    $pendingRequests = array_values(array_filter($materialRequests, static fn(array $req): bool => (string)($req['status'] ?? '') !== 'DELIVERED'));
    $deliveredRequests = array_values(array_filter($materialRequests, static fn(array $req): bool => in_array((string)($req['status'] ?? ''), ['PARTIAL', 'DELIVERED'], true)));
    $chemicalTotalWeightKg = 0.0;
    foreach ($chemicalInputs as $input) {
        $chemicalTotalWeightKg += (float)($input['weight_kg'] ?? 0);
    }
    $wasteTotalWeightKg = 0.0;
    foreach ($wastes as $waste) {
        $wasteTotalWeightKg += (float)($waste['weight_kg'] ?? 0);
    }
    $totalUnitsQty = 0;
    foreach ($boxes as $box) {
        $totalUnitsQty += (int)($box['units_qty'] ?? 0);
    }
    $totalBoxesOnPallets = 0;
    foreach ($pallets as $pallet) {
        $totalBoxesOnPallets += (int)($pallet['box_count'] ?? 0);
    }
    $outputRollLabel = $outputRoll !== null
        ? (string)$outputRoll['roll_code'] . ' (' . (string)$outputRoll['weight_kg'] . ' Kg)'
        : '-';
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
    foreach ([
        trim((string)($currentRoll['color'] ?? '')),
        trim((string)($outputRoll['color'] ?? '')),
    ] as $colorValue) {
        if ($colorValue !== '' && !in_array($colorValue, $detectedColors, true)) {
            $detectedColors[] = $colorValue;
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
    $formAniloxSlots = isset($formState['anilox_slots']) && is_array($formState['anilox_slots'])
        ? $formState['anilox_slots']
        : null;
    $aniloxSlots = buildWorkOrderAniloxSlots($colorSlots, $chemicalInputs, $savedAniloxAssignments, $formAniloxSlots);
    $suggestedAniloxColors = extractSuggestedAniloxColors($colorSlots, $chemicalInputs, $aniloxSlots);
    $mermaPercentLabel = '-';
    $finishWasteKg = (float)($lastFinish['waste_kg'] ?? 0);
    $finishRollKg = (float)($lastFinish['final_roll_weight_kg'] ?? 0);
    if ($finishWasteKg > 0 && ($finishWasteKg + $finishRollKg) > 0) {
        $mermaPercentLabel = number_format(($finishWasteKg * 100) / ($finishWasteKg + $finishRollKg), 2, '.', '') . ' %';
    }
    $targetQtyNumber = is_numeric((string)$targetQtyLabel) ? (float)$targetQtyLabel : 0.0;
    $producedUnits = $totalUnitsQty;
    $producedPercent = $targetQtyNumber > 0 ? ($producedUnits * 100) / $targetQtyNumber : 0.0;
    $producedProgressLabel = number_format((float)$producedUnits, 0, '.', '.')
        . ' de '
        . ($targetQtyNumber > 0 ? number_format($targetQtyNumber, 0, '.', '.') : '0')
        . ' (' . number_format($producedPercent, 0, '.', '.') . ' %)';
    $saldoTotalUnits = max(0.0, $targetQtyNumber - (float)$producedUnits);
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
    $impressionsPerAxisLabel = 'Sin información';
    $configuredColorCount = count(array_values(array_filter(
        array_map(static fn(array $slot): string => trim((string)($slot['color_name'] ?? '')), $aniloxSlots),
        static fn(string $value): bool => $value !== ''
    )));
    $impressionsPerDevelopmentLabel = $configuredColorCount > 0 ? ($configuredColorCount . ' impresiones') : 'Sin información';
    $wasteValueLabel = $mermaPercentLabel !== '-' ? str_replace(' %', '', $mermaPercentLabel) : '-';
    $counterPrinterLabel = $producedUnits > 0
        ? number_format((float)$producedUnits, 0, '.', '.')
        : '-';
    $helperOptions = $service instanceof ReceptionService ? $service->listProductionPersonnelNames() : [];
    $machineHeaderHelperValue = isset($formState['shift_helper_name'])
        ? trim((string)$formState['shift_helper_name'])
        : (trim((string)($sessionForDisplay['helper_name'] ?? '')) !== '' ? trim((string)$sessionForDisplay['helper_name']) : '');
    $machineHeaderCommentValue = isset($formState['shift_comments'])
        ? trim((string)$formState['shift_comments'])
        : (trim((string)($sessionForDisplay['comments'] ?? '')) !== '' ? trim((string)$sessionForDisplay['comments']) : '');
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
        ['Impresiones al eje', h($impressionsPerAxisLabel), $bottomUnitRows[0]['label'], $bottomUnitRows[0]['value']],
        ['Impresiones por desarrollo', h($impressionsPerDevelopmentLabel), $bottomUnitRows[1]['label'], $bottomUnitRows[1]['value']],
        ['% de merma', h($wasteValueLabel), $bottomUnitRows[2]['label'], $bottomUnitRows[2]['value']],
        ['Bolsas producidas / en curso', h($producedProgressLabel), $bottomUnitRows[3]['label'], $bottomUnitRows[3]['value']],
        ['Bolsas producidas / total', h($producedProgressLabel), $bottomUnitRows[4]['label'], $bottomUnitRows[4]['value']],
        ['Saldo total', h($saldoTotalLabel), $bottomUnitRows[5]['label'], $bottomUnitRows[5]['value']],
        ['', '', '', ''],
        ['Materialidad', h($materialLabel), 'Metros a imprimir', h($fabricMetersLabel)],
        ['Color Tela', h($fabricColorLabel), 'Contador impresora', h($counterPrinterLabel)],
        ['Ancho Tela', h($fabricWidthLabel), 'Kg a imprimir', h($fabricKgLabel)],
        ['Gramaje', h($fabricGramajeLabel), '', ''],
    ];
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

    $compactClicheLocationLabel = $primaryClicheLocationLabel !== ''
        ? $primaryClicheLocationLabel
        : 'Sin ubicación de cliché';
    $body .= '<div style="margin:-2px 0 12px 0">'
        . '<a class="btn secondary" href="/cliches?work_order_id=' . (int)$ot['id'] . '" style="padding:4px 10px;font-size:12px;line-height:1.2;min-height:auto;border-radius:8px">'
        . 'Ubicación Cliché: ' . h($compactClicheLocationLabel)
        . '</a>'
        . '</div>';

    if ($clicheUsageHistory !== []) {
        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Historial de clisés en OT</th></tr></thead><tbody>';
        foreach ($clicheUsageHistory as $clicheLog) {
            $detailText = trim((string)($clicheLog['notes'] ?? ''));
            $locationText = trim((string)($clicheLog['to_location_code'] ?? ''));
            $body .= '<tr>'
                . '<td class="legacy-label-cell">' . h(eventTypeLabel('CLICHE_' . (string)$clicheLog['action_type'])) . '</td>'
                . '<td class="legacy-value-cell"><a href="/cliches/' . (int)$clicheLog['cliche_id'] . '">' . h((string)$clicheLog['cliche_code']) . '</a></td>'
                . '<td class="legacy-label-cell">' . h((string)$clicheLog['created_at']) . '</td>'
                . '<td class="legacy-value-cell">' . h(($locationText !== '' ? $locationText : '-') . ($detailText !== '' ? ' | ' . $detailText : '')) . '</td>'
                . '</tr>';
        }
        $body .= '</tbody></table></div>';
    }
    $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Informacion de Fabricacion</th></tr></thead><tbody>';
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
    $body .= renderWorkOrderAniloxConfigCard((int)$ot['id'], $aniloxSlots, $aniloxCatalog, $suggestedAniloxColors);
    $body .= renderWorkOrderMachineHeaderCard(
        (int)$ot['id'],
        $sessionForDisplay,
        $stationTypeLabel,
        $stationLabel,
        $machineHeaderHelperValue,
        $machineHeaderCommentValue,
        $helperOptions
    );
    $showOperationalFlowOnLanding = false;
    $pendingRequests = array_values(array_filter($materialRequests, static fn(array $req): bool => (string)($req['status'] ?? '') !== 'DELIVERED'));
    $currentStage = $isClosed ? 5 : ($isCutting ? 4 : ($showFinishData ? 3 : ($isStarted ? 2 : 1)));

    if ($lastFinish !== null) {
        $legacyLastFinishRows = [
            ['Fecha', h((string)$lastFinish['created_at']), 'Peso final bobina', h((string)($lastFinish['final_roll_weight_kg'] ?? '0')) . ' Kg'],
            ['Peso final tintas', h((string)($lastFinish['final_chemical_weight_kg'] ?? '0')) . ' Kg', 'Cajas', h((string)($lastFinish['box_qty'] ?? '0'))],
            ['Merma', h((string)($lastFinish['waste_kg'] ?? '0')) . ' Kg', 'Bobina final', (int)($lastFinish['output_roll_id'] ?? 0) > 0 ? 'Generada' : '-'],
        ];
        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Ultimo cierre OT</th></tr></thead><tbody>';
        foreach ($legacyLastFinishRows as $legacyRow) {
            $body .= '<tr>'
                . '<td class="legacy-label-cell">' . h((string)$legacyRow[0]) . '</td>'
                . '<td class="legacy-value-cell">' . $legacyRow[1] . '</td>'
                . '<td class="legacy-label-cell">' . h((string)$legacyRow[2]) . '</td>'
                . '<td class="legacy-value-cell">' . $legacyRow[3] . '</td>'
                . '</tr>';
        }
        if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
            $body .= '<tr><td class="legacy-label-cell">Etiquetas</td><td class="legacy-value-cell" colspan="3"><div class="row"><a class="btn secondary" href="/rolls/' . (int)$lastFinish['output_roll_id'] . '/label?auto_print=1" target="_blank" rel="noopener">Etiqueta bobina final</a><a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/box-label?auto_print=1" target="_blank" rel="noopener">Etiqueta cajas producto</a></div></td></tr>';
        }
        $body .= '</tbody></table></div>';
    }

    if ($showOperationalFlowOnLanding && in_array($currentStage, [1, 2], true)) {
        $sectionTitle = $currentStage === 1 ? '1. Solicitudes a bodega' : 'Solicitudes adicionales durante producción';
        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">' . h($sectionTitle) . '</th></tr></thead><tbody>';
        $body .= '<tr><td class="legacy-label-cell">Bobinas</td><td class="legacy-value-cell" colspan="3"><details class="fold">
              <summary>Bobinas</summary>
              <div class="fold-body">
              <form method="post" action="/work-orders/' . (int)$ot['id'] . '/material-request">
                <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
                <input type="hidden" name="request_type" value="ROLL">
                <div style="margin-bottom:10px">
                  <label>Tipo de bobina</label>
                  <select name="requested_group_key" required>
                    <option value="">Seleccionar material</option>';
    foreach ($availableMaterialRolls as $availableRoll) {
        $selected = ((string)($formState['requested_group_key'] ?? '') === (string)$availableRoll['group_key']) ? ' selected' : '';
        $body .= '<option value="' . h((string)$availableRoll['group_key']) . '"' . $selected . '>' . h(materialRequestGroupLabel($availableRoll)) . '</option>';
    }
    $body .= '</select>
                </div>
                <div class="row">
                  <div style="flex:1;min-width:160px">
                    <label>Cantidad de bobinas</label>
                    <input name="requested_qty" type="number" step="1" min="1" value="' . h((string)($formState['requested_qty'] ?? '1')) . '" required>
                  </div>
                  <div style="flex:1;min-width:220px">
                    <label>Nota</label>
                    <input name="request_notes" type="text" value="' . h((string)($formState['request_notes'] ?? '')) . '" placeholder="Observaciones para bodega">
                  </div>
                </div>
                <div style="margin-top:12px"><button class="btn" type="submit">Solicitar bobinas</button></div>
              </form>
              </div>
            </details></td></tr>';
        $body .= '<tr><td class="legacy-label-cell">Tintas</td><td class="legacy-value-cell" colspan="3"><details class="fold">
              <summary>Tintas</summary>
              <div class="fold-body">
              <form method="post" action="/work-orders/' . (int)$ot['id'] . '/material-request">
                <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
                <input type="hidden" name="request_type" value="CHEMICAL">
                <div class="row">
                  <div style="flex:2;min-width:220px">
                    <label>Tinta</label>
                    <select name="chemical_id" required>
                      <option value="">Seleccionar tinta</option>';
    foreach ($chemicals as $chemical) {
        $selected = ((string)($formState['chemical_request_id'] ?? '') === (string)$chemical['id']) ? ' selected' : '';
        $body .= '<option value="' . (int)$chemical['id'] . '"' . $selected . '>' . h((string)$chemical['code']) . ' - ' . h((string)$chemical['name']) . '</option>';
    }
    $body .= '</select>
                </div>
                  <div style="flex:1;min-width:160px">
                    <label>Cantidad</label>
                    <input name="requested_qty" type="number" step="0.001" min="0.001" value="' . h((string)($formState['chemical_requested_qty'] ?? '1')) . '" required>
                  </div>
                  <div style="flex:1;min-width:160px">
                    <label>Unidad</label>
                    <select name="requested_unit">
                      <option value="Kg">Kg</option>
                      <option value="Lt">Lt</option>
                      <option value="Unid.">Unid.</option>
                    </select>
                  </div>
                </div>
                <div style="margin-top:10px">
                  <label>Nota</label>
                  <input name="request_notes" type="text" value="' . h((string)($formState['chemical_request_notes'] ?? '')) . '" placeholder="Ej: para mezcla inicial o reposición">
                </div>
                <div style="margin-top:12px"><button class="btn" type="submit">Solicitar tinta</button></div>
              </form>
              </div>
            </details></td></tr>';
        $body .= '<tr><td class="legacy-label-cell">Otros insumos</td><td class="legacy-value-cell" colspan="3"><details class="fold">
              <summary>Otros insumos</summary>
              <div class="fold-body">
              <form method="post" action="/work-orders/' . (int)$ot['id'] . '/material-request">
                <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
                <input type="hidden" name="request_type" value="OTHER">
                <div class="row">
                  <div style="flex:2;min-width:220px">
                    <label>Material o insumo</label>
                    <input name="requested_item" type="text" value="' . h((string)($formState['requested_item'] ?? '')) . '" placeholder="Ej: tinta azul, cajas, bolsas, aditivo" required>
                  </div>
                  <div style="flex:1;min-width:160px">
                    <label>Cantidad</label>
                    <input name="requested_qty" type="number" step="0.001" min="0.001" value="' . h((string)($formState['other_requested_qty'] ?? '1')) . '" required>
                  </div>
                  <div style="flex:1;min-width:160px">
                    <label>Unidad</label>
                    <input name="requested_unit" type="text" value="' . h((string)($formState['other_requested_unit'] ?? 'Unid.')) . '" placeholder="Ej: Unid., Kg, Lt">
                  </div>
                </div>
                <div style="margin-top:10px">
                  <label>Nota</label>
                  <input name="request_notes" type="text" value="' . h((string)($formState['other_request_notes'] ?? '')) . '" placeholder="Observaciones para bodega">
                </div>
                <div style="margin-top:12px"><button class="btn" type="submit">Solicitar insumo</button></div>
              </form>
              </div>
            </details></td></tr>';
        $body .= '</tbody></table></div>';

        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Solicitudes de la OT</th></tr></thead><tbody>';
        $body .= '<tr><td class="legacy-label-cell">Solicitudes OT</td><td class="legacy-value-cell" colspan="3"><details class="fold"' . ($materialRequests !== [] ? ' open' : '') . '>
          <summary>Solicitudes OT</summary>
          <div class="fold-body"><div class="table-wrap"><table class="table-compact"><thead><tr><th>Tipo</th><th>Material solicitado</th><th>Cant.</th><th>Entregadas</th><th>Estado</th><th>Última entrega</th></tr></thead><tbody>';
    foreach ($materialRequests as $request) {
        $deliveredRoll = trim((string)($request['delivered_roll_code'] ?? ''));
        $body .= '<tr>';
        $body .= '<td>' . h(materialRequestTypeLabel((string)($request['request_type'] ?? 'ROLL'))) . '</td>';
        $body .= '<td>' . h((string)$request['requested_item']) . '</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($request['requested_qty'] ?? 0), (string)($request['requested_unit'] ?? 'Unid.'))) . '</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($request['delivered_qty'] ?? 0), (string)($request['requested_unit'] ?? 'Unid.'))) . '</td>';
        $body .= '<td>' . h(materialRequestStatusLabel((string)$request['status'])) . '</td>';
        $body .= '<td>' . h($deliveredRoll !== '' ? $deliveredRoll : (string)($request['delivered_by'] ?? '-')) . '</td>';
        $body .= '</tr>';
    }
    if ($materialRequests === []) {
        $body .= '<tr><td colspan="6" class="muted">Sin solicitudes todavía.</td></tr>';
    }
        $body .= '</tbody></table></div>
          </div></details></td></tr></tbody></table></div>';
    }

    if (false && $currentStage === 2) {
        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Registro de merma</th></tr></thead><tbody>';
        $body .= '<tr><td class="legacy-label-cell">Acción</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/waste">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <div class="row" style="align-items:end">
              <div style="flex:2;min-width:220px">
                <label>Motivo</label>
                <input name="waste_reason" type="text" value="' . h((string)($formState['waste_reason'] ?? '')) . '" placeholder="Ej: ajuste máquina, rotura, prueba color" required>
              </div>
              <div style="flex:1;min-width:180px">
                <label>Peso merma (Kg)</label>
                <input id="waste_weight_kg" name="waste_weight_kg" type="number" step="0.001" min="0" value="' . h((string)($formState['waste_weight_kg'] ?? '')) . '" required>
              </div>
              <div style="min-width:160px">
                <button class="btn secondary" type="button" id="read_scale_waste">Leer balanza</button>
              </div>
            </div>
            <div style="margin-top:12px"><button class="btn" type="submit">Guardar merma</button></div>
          </form></td></tr></tbody></table></div>';
        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Mermas registradas</th></tr></thead><tbody><tr><td class="legacy-value-cell" colspan="4"><table><thead><tr><th>Fecha</th><th>Motivo</th><th>Peso</th><th>Operador</th></tr></thead><tbody>';
    foreach ($wastes as $waste) {
        $body .= '<tr>';
        $body .= '<td>' . h((string)$waste['created_at']) . '</td>';
        $body .= '<td>' . h((string)$waste['reason']) . '</td>';
        $body .= '<td>' . h((string)$waste['weight_kg']) . ' Kg</td>';
        $body .= '<td>' . h((string)$waste['operator_name']) . '</td>';
        $body .= '</tr>';
    }
    if ($wastes === []) {
        $body .= '<tr><td colspan="4" class="muted">Sin mermas registradas.</td></tr>';
    }
    $body .= '</tbody></table></td></tr></tbody></table></div>';
    }

    if ($currentStage >= 4 && ($outputRoll !== null || $boxes !== [] || $pallets !== [])) {
        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Salida de producción y corte</th></tr></thead><tbody>';
        if ($outputRoll !== null) {
            $body .= '<tr><td class="legacy-label-cell">Bobina salida</td><td class="legacy-value-cell" colspan="3"><div class="legacy-note" style="margin-bottom:10px">
                <div class="row">
                  <div style="flex:1;min-width:180px"><div class="muted">Nueva bobina</div><div style="font-weight:800">' . h((string)$outputRoll['roll_code']) . '</div></div>
                  <div style="flex:1;min-width:180px"><div class="muted">Peso</div><div style="font-weight:800">' . h((string)$outputRoll['weight_kg']) . ' Kg</div></div>
                  <div style="flex:1;min-width:180px"><div class="muted">Bodega</div><div style="font-weight:800">' . h((string)$outputRoll['warehouse_code']) . '</div></div>
                </div>
                <div class="row" style="margin-top:10px">
                  <a class="btn secondary" href="/rolls/' . (int)$outputRoll['id'] . '">Ver bobina salida</a>
                  ' . ($currentStage === 4 ? '<a class="btn secondary" href="#cut-stage">Registrar corte en esta OT</a>' : '') . '
                  <a class="btn secondary" href="/rolls/' . (int)$outputRoll['id'] . '/label?auto_print=1" target="_blank" rel="noopener">Etiqueta bobina salida</a>
                </div>
              </div></td></tr>';
        }
        if ($boxes !== []) {
            $body .= '<tr><td class="legacy-label-cell">Cajas generadas</td><td class="legacy-value-cell" colspan="3"><div class="table-wrap"><table class="table-compact"><thead><tr><th>Código</th><th>Bobina origen</th><th>Unidades</th><th>Pallet</th><th></th></tr></thead><tbody>';
            foreach ($boxes as $box) {
                $body .= '<tr><td>' . h((string)$box['box_code']) . '</td><td>' . h((string)($box['source_roll_code'] ?? '-')) . '</td><td>' . h((string)$box['units_qty']) . '</td><td>' . h((string)($box['pallet_code'] ?? '-')) . '</td><td><a class="btn secondary" href="/boxes/' . (int)$box['id'] . '">Ver</a></td></tr>';
            }
            $body .= '</tbody></table></div></td></tr>';
        }
        if ($pallets !== []) {
            $body .= '<tr><td class="legacy-label-cell">Pallets generados</td><td class="legacy-value-cell" colspan="3"><div class="table-wrap"><table class="table-compact"><thead><tr><th>Código</th><th>Cajas</th><th>Destino</th><th></th></tr></thead><tbody>';
            foreach ($pallets as $pallet) {
                $body .= '<tr><td>' . h((string)$pallet['pallet_code']) . '</td><td>' . h((string)$pallet['box_count']) . '</td><td>' . h((string)$pallet['destination_mode']) . '</td><td><a class="btn secondary" href="/pallets/' . (int)$pallet['id'] . '">Ver</a></td></tr>';
            }
            $body .= '</tbody></table></div></td></tr>';
        }
        $body .= '</tbody></table></div>';
    }

    $cutDestinationState = strtoupper(trim((string)($formState['destination_mode'] ?? 'STOCK')));
    if (!in_array($cutDestinationState, ['STOCK', 'CUSTOMER_ORDER'], true)) {
        $cutDestinationState = 'STOCK';
    }
    $cutCustomerOrderState = (string)($formState['customer_order_ref'] ?? '');
    $cutWarehouseState = (string)($formState['warehouse_id'] ?? '');
    $cutUnitsState = (string)($formState['units_total'] ?? '');
    $cutBoxQtyState = (string)($formState['box_qty'] ?? ($lastFinish['box_qty'] ?? ''));
    $cutBoxesPerPalletState = (string)($formState['boxes_per_pallet'] ?? '10');

    $scanCode = (string)($formState['scan_code'] ?? '');
    $processWeightState = (string)($formState['process_weight_kg'] ?? '');
    $processWasteState = (string)($formState['process_waste_kg'] ?? '0');
    $chemicalIdState = (string)($formState['chemical_id'] ?? '');
    $chemicalWeightState = (string)($formState['chemical_weight_kg'] ?? '');
    $changeScanCode = (string)($formState['change_scan_code'] ?? '');
    $changeFinalWeightState = (string)($formState['change_final_roll_weight_kg'] ?? '');
    $changeWasteState = (string)($formState['change_waste_kg'] ?? '0');
    $changeOutputRollWeightState = (string)($formState['change_output_roll_weight_kg'] ?? '');
    $changeNextWeightState = (string)($formState['change_next_process_weight_kg'] ?? '');
    $changeNextWasteState = (string)($formState['change_next_waste_kg'] ?? '0');
    $finishRollWeightState = (string)($formState['finish_final_roll_weight_kg'] ?? '');
    $finishChemicalWeightState = (string)($formState['finish_final_chemical_weight_kg'] ?? '');
    $finishBoxQtyState = (string)($formState['finish_box_qty'] ?? '');
    $finishWasteState = (string)($formState['finish_waste_kg'] ?? '0');
    $finishOutputRollWeightState = (string)($formState['finish_output_roll_weight_kg'] ?? '');

    if ($showOperationalFlowOnLanding && !$isStarted) {
        $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">1. Registro de inicio</th></tr></thead><tbody>';
        $body .= '<tr><td class="legacy-label-cell">Registro bobina inicial</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/attach-roll">
              <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
              <label>Código pieza / ID</label>
              <input name="scan_code" type="text" value="' . h($scanCode) . '" autofocus placeholder="Escanear bobina">
              <div class="row" style="align-items:end;margin-top:10px">
                <div style="flex:1;min-width:180px">
                  <label>Peso proceso (Kg)</label>
                  <input id="process_weight_kg" name="process_weight_kg" type="number" step="0.001" min="0" required value="' . h($processWeightState) . '">
                </div>
                <div style="min-width:160px">
                  <button class="btn secondary" type="button" id="read_scale_process_weight">Leer balanza</button>
                </div>
              </div>
              <div style="margin-top:10px">
                <label>Merma inicial (Kg)</label>
                <input name="process_waste_kg" type="number" step="0.001" min="0" value="' . h($processWasteState) . '">
              </div>
              <div style="margin-top:12px">
                <button class="btn" type="submit">Guardar bobina inicial</button>
              </div>
            </form></td></tr>';
        $body .= '<tr><td class="legacy-label-cell">Bobina lista para iniciar</td><td class="legacy-value-cell" colspan="3">';
        if ($currentRoll !== null) {
            $body .= '<div class="legacy-note">
                <div class="row">
                  <div style="flex:1;min-width:180px"><div class="muted">Código</div><div style="font-weight:800">' . h((string)$currentRoll['roll_code']) . '</div></div>
                  <div style="flex:1;min-width:180px"><div class="muted">Código SKU</div><div style="font-weight:800">' . h((string)$currentRoll['sku_code']) . '</div></div>
                  <div style="flex:1;min-width:180px"><div class="muted">Bodega</div><div style="font-weight:800">' . h((string)$currentRoll['warehouse_code']) . '</div></div>
                  <div style="flex:1;min-width:180px"><div class="muted">Peso actual</div><div style="font-weight:800">' . h((string)$currentRoll['weight_kg']) . ' Kg</div></div>
                </div>
              </div>';
        } else {
            $body .= '<div class="muted">Aún no hay una bobina registrada para el inicio.</div>';
        }
        $body .= '</td></tr>';
        $body .= '<tr><td class="legacy-label-cell">Registro de tintas de inicio</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/chemical-input">
              <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
              <div style="margin-bottom:10px">
                <label>Tinta</label>
                <select name="chemical_id" required>
                  <option value="">Seleccionar</option>';
        foreach ($chemicals as $chemical) {
            $selected = ((string)$chemical['id'] === $chemicalIdState) ? ' selected' : '';
            $body .= '<option value="' . (int)$chemical['id'] . '"' . $selected . '>' . h((string)$chemical['code']) . ' - ' . h((string)$chemical['name']) . '</option>';
        }
        $body .= '</select>
              </div>
              <div class="row" style="align-items:end">
                <div style="flex:1;min-width:220px">
                  <label>Peso entrada (Kg)</label>
                  <input id="chemical_input_weight_kg" name="chemical_weight_kg" type="number" step="0.001" min="0" required value="' . h($chemicalWeightState) . '">
                </div>
                <div style="min-width:180px">
                  <button class="btn secondary" type="button" id="read_scale_chemical_input">Leer balanza</button>
                </div>
              </div>
              <div style="margin-top:12px">
                <button class="btn" type="submit">Guardar tinta</button>
              </div>
            </form></td></tr>';
        $body .= '<tr><td class="legacy-label-cell">Tintas registradas</td><td class="legacy-value-cell" colspan="3"><table><thead><tr><th>Tinta</th><th>Peso</th><th>Fecha</th></tr></thead><tbody>';
        foreach ($chemicalInputs as $input) {
            $body .= '<tr>';
            $body .= '<td>' . h((string)$input['chemical_code']) . '</td>';
            $body .= '<td>' . h((string)$input['weight_kg']) . ' Kg</td>';
            $body .= '<td>' . h((string)$input['created_at']) . '</td>';
            $body .= '</tr>';
        }
        if ($chemicalInputs === []) {
            $body .= '<td colspan="3" class="muted">Sin tintas de inicio registradas.</td></tr>';
        }
        $body .= '</tbody></table></td></tr>';
        $body .= '<tr><td class="legacy-label-cell">2. Iniciar producción</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/start">
              <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
              <button class="btn" type="submit">Iniciar producción</button>
            </form></td></tr>';
        $body .= '<tr><td class="legacy-label-cell">3. Cambio de bobina</td><td class="legacy-value-cell" colspan="3">-</td></tr>';
        $body .= '<tr><td class="legacy-label-cell">4. Finalizar producción</td><td class="legacy-value-cell" colspan="3">-</td></tr>';
        $body .= '</tbody></table></div>';
    } elseif ($showOperationalFlowOnLanding && !$isClosed && !$isCutting) {
        if (!$showFinishData) {
            $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">Producción activa</th></tr></thead><tbody>';
            $body .= '<tr><td class="legacy-label-cell">Bobina en producción</td><td class="legacy-value-cell" colspan="3">';
            if ($currentRoll !== null) {
                $body .= '<div class="legacy-note"><div class="row">
                    <div style="flex:1;min-width:180px"><div class="muted">Código</div><div style="font-weight:800">' . h((string)$currentRoll['roll_code']) . '</div></div>
                    <div style="flex:1;min-width:180px"><div class="muted">Código SKU</div><div style="font-weight:800">' . h((string)$currentRoll['sku_code']) . '</div></div>
                    <div style="flex:1;min-width:180px"><div class="muted">Bodega</div><div style="font-weight:800">' . h((string)$currentRoll['warehouse_code']) . '</div></div>
                    <div style="flex:1;min-width:180px"><div class="muted">Peso actual</div><div style="font-weight:800">' . h((string)$currentRoll['weight_kg']) . ' Kg</div></div>
                  </div></div>';
            } else {
                $body .= '<div class="muted">No hay una bobina activa en producción.</div>';
            }
            $body .= '</td></tr>';
            $body .= '<tr><td class="legacy-label-cell">3. Cambio de bobina (opcional)</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/change-roll">
                  <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
                  <div class="row" style="align-items:end">
                    <div style="flex:1;min-width:180px">
                      <label>Peso remanente bobina actual (Kg)</label>
                      <input id="change_final_roll_weight_kg" name="change_final_roll_weight_kg" type="number" step="0.001" min="0" value="' . h($changeFinalWeightState) . '">
                    </div>
                    <div style="min-width:160px">
                      <button class="btn secondary" type="button" id="read_scale_change_final">Leer balanza</button>
                    </div>
                    <div style="flex:1;min-width:180px">
                      <label>Merma bobina actual (Kg)</label>
                      <input name="change_waste_kg" type="number" step="0.001" min="0" value="' . h($changeWasteState) . '">
                    </div>
                  </div>
                  <div class="row" style="align-items:end;margin-top:10px">
                    <div style="flex:1;min-width:180px">
                      <label>Peso bobina salida impresión (Kg)</label>
                      <input id="change_output_roll_weight_kg" name="change_output_roll_weight_kg" type="number" step="0.001" min="0" value="' . h($changeOutputRollWeightState) . '">
                    </div>
                    <div style="min-width:160px">
                      <button class="btn secondary" type="button" id="read_scale_change_output">Leer balanza</button>
                    </div>
                  </div>
                  <div class="row" style="align-items:end;margin-top:10px">
                    <div style="flex:1;min-width:180px">
                      <label>Nueva bobina código / ID</label>
                      <input name="change_scan_code" type="text" value="' . h($changeScanCode) . '" placeholder="Escanear nueva bobina">
                    </div>
                    <div style="flex:1;min-width:180px">
                      <label>Peso nueva bobina (Kg)</label>
                      <input id="change_next_process_weight_kg" name="change_next_process_weight_kg" type="number" step="0.001" min="0" value="' . h($changeNextWeightState) . '">
                    </div>
                    <div style="min-width:160px">
                      <button class="btn secondary" type="button" id="read_scale_change_next">Leer balanza</button>
                    </div>
                    <div style="flex:1;min-width:180px">
                      <label>Merma nueva bobina (Kg)</label>
                      <input name="change_next_waste_kg" type="number" step="0.001" min="0" value="' . h($changeNextWasteState) . '">
                    </div>
                  </div>
                  <div style="margin-top:12px">
                    <button class="btn" type="submit">Cambiar bobina</button>
                  </div>
                </form></td></tr>';
            $body .= '<tr><td class="legacy-label-cell">4. Finalizar producción</td><td class="legacy-value-cell" colspan="3"><div class="row">
                  <a class="btn" href="/work-orders/' . (int)$ot['id'] . '/finish-data">Registrar cierre de impresión</a>
                </div></td></tr></tbody></table></div>';
        } else {
            $body .= '<div class="legacy-sheet-card" style="margin-top:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">5. Datos finalizar impresión</th></tr></thead><tbody>';
            $body .= '<tr><td class="legacy-label-cell">Cierre de impresión</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/work-orders/' . (int)$ot['id'] . '/finish">
                  <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
                  <input type="hidden" name="show_finish_data" value="1">
                  <div class="row" style="align-items:end">
                    <div style="flex:1;min-width:180px">
                      <label>Peso final bobina (Kg)</label>
                      <input id="finish_final_roll_weight_kg" name="finish_final_roll_weight_kg" type="number" step="0.001" min="0" value="' . h($finishRollWeightState) . '">
                    </div>
                    <div style="min-width:160px">
                      <button class="btn secondary" type="button" id="read_scale_finish_roll">Leer balanza</button>
                    </div>
                    <div style="flex:1;min-width:180px">
                  <label>Peso final tintas (Kg)</label>
                      <input id="finish_final_chemical_weight_kg" name="finish_final_chemical_weight_kg" type="number" step="0.001" min="0" value="' . h($finishChemicalWeightState) . '">
                    </div>
                    <div style="min-width:160px">
                      <button class="btn secondary" type="button" id="read_scale_finish_chemicals">Leer balanza</button>
                    </div>
                    <div style="flex:1;min-width:180px">
                      <label>Cantidad de cajas</label>
                      <input name="finish_box_qty" type="number" step="1" min="1" value="' . h($finishBoxQtyState) . '">
                    </div>
                    <div style="flex:1;min-width:180px">
                      <label>Peso nueva bobina salida (Kg)</label>
                      <input id="finish_output_roll_weight_kg" name="finish_output_roll_weight_kg" type="number" step="0.001" min="0" value="' . h($finishOutputRollWeightState) . '">
                    </div>
                    <div style="min-width:160px">
                      <button class="btn secondary" type="button" id="read_scale_finish_output_roll">Leer balanza</button>
                    </div>
                    <div style="flex:1;min-width:180px">
                      <label>Merma final bobina (Kg)</label>
                      <input name="finish_waste_kg" type="number" step="0.001" min="0" value="' . h($finishWasteState) . '">
                    </div>
                  </div>
                  <div style="margin-top:12px">
                    <button class="btn" type="submit">Guardar cierre de impresión e imprimir etiqueta</button>
                  </div>
                </form></td></tr></tbody></table></div>';
        }
    }

    if ($isCutting && !$isFinishApproved) {
        $body .= '<div class="legacy-sheet-card" style="margin-top:12px;border-color:#f59e0b;background:#fffbeb"><div style="font-size:16px;font-weight:800;color:#92400e;margin-bottom:6px">Validación de supervisor pendiente</div><div style="color:#78350f">Antes de pasar a la siguiente máquina debes validar el cierre de flexografía con supervisor.</div><div style="margin-top:12px"><a class="btn" href="/work-orders/' . (int)$ot['id'] . '/finish-approval">Validar cierre flexo</a></div></div>';
    } elseif ($isCutting && $isFinishApproved) {
        $body .= '<div class="legacy-sheet-card" style="margin-top:12px;border-color:#0f766e;background:#f0fdfa"><div style="font-size:16px;font-weight:800;color:#115e59;margin-bottom:6px">Flexografía validada</div><div style="color:#134e4a">La OT quedó lista para continuar en Selladora. Desde aquí ya no se trabaja el cierre como corte.</div><div style="margin-top:12px"><a class="btn" href="/work-orders/' . (int)$ot['id'] . '/sealing">Abrir pantalla de Selladora</a></div></div>';
    } elseif ($isCutting && $outputRoll !== null) {
        $body .= '<div class="legacy-sheet-card" id="cut-stage" style="margin-top:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="4">5. Corte final de la OT</th></tr></thead><tbody>';
        $body .= '<tr><td class="legacy-label-cell">Registro de corte</td><td class="legacy-value-cell" colspan="3"><form method="post" action="/cut/process">
              <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
              <input type="hidden" name="source_roll_id" value="' . (int)$outputRoll['id'] . '">
              <div class="row">
                <div style="flex:1;min-width:220px">
                  <label>Bobina salida producción</label>
                  <input type="text" value="' . h((string)$outputRoll['roll_code']) . '" readonly>
                </div>
                <div style="flex:1;min-width:220px">
                  <label>Destino</label>
                  <select name="destination_mode" id="ot_cut_destination_mode">
                    <option value="STOCK"' . ($cutDestinationState === 'STOCK' ? ' selected' : '') . '>Almacenar en stock</option>
                    <option value="CUSTOMER_ORDER"' . ($cutDestinationState === 'CUSTOMER_ORDER' ? ' selected' : '') . '>Orden de compra cliente</option>
                  </select>
                </div>
                <div style="flex:1;min-width:220px" id="ot_cut_customer_wrap">
                  <label>OC cliente</label>
                  <input name="customer_order_ref" type="text" value="' . h($cutCustomerOrderState) . '" placeholder="OC cliente">
                </div>
              </div>
              <div class="row" style="margin-top:10px">
                <div style="flex:1;min-width:220px">
                  <label>Asignación de bodega</label>
                  <input type="text" value="La realiza Bodega al recibir el pallet terminado" readonly>
                </div>
                <div style="flex:1;min-width:180px">
                  <label>Unidades totales</label>
                  <input name="units_total" type="number" step="1" min="1" required value="' . h($cutUnitsState) . '">
                </div>
                <div style="flex:1;min-width:180px">
                  <label>Cantidad de cajas</label>
                  <input name="box_qty" type="number" step="1" min="1" required value="' . h($cutBoxQtyState) . '">
                </div>
                <div style="flex:1;min-width:180px">
                  <label>Cajas por pallet</label>
                  <input name="boxes_per_pallet" type="number" step="1" min="1" required value="' . h($cutBoxesPerPalletState) . '">
                </div>
              </div>
              <div style="margin-top:12px">
                <button class="btn" type="submit">Completar corte y cerrar OT</button>
              </div>
            </form></td></tr></tbody></table></div>';
    }

    if (false && $rollHistory !== [] && $currentStage >= 2) {
        $body .= '<details class="fold" style="margin-top:12px"><summary>' . ($currentStage >= 4 ? 'Historial final de bobinas OT' : 'Historial de bobinas en proceso') . '</summary><div class="fold-body"><div class="table-wrap"><table class="table-compact"><thead><tr><th>Fecha</th><th>Acción</th><th>Bobina</th><th>Peso</th><th>Merma</th><th>Detalle</th></tr></thead><tbody>';
        foreach ($rollHistory as $roll) {
            $payload = $roll['payload_data'] ?? [];
            $action = (string)$roll['type'] === 'WORK_ORDER_ROLL_ATTACHED' ? 'Ingreso OT' : 'Salida OT';
            $weight = (string)$roll['type'] === 'WORK_ORDER_ROLL_ATTACHED'
                ? (string)($payload['process_weight_kg'] ?? '-')
                : (string)($payload['final_weight_kg'] ?? '-');
            $detail = (string)$roll['type'] === 'WORK_ORDER_ROLL_RELEASED'
                ? ('Motivo: ' . (string)($payload['reason'] ?? '-'))
                : ('Operador: ' . (string)($payload['operator_name'] ?? '-'));
            $body .= '<tr>';
            $body .= '<td>' . h((string)$roll['created_at']) . '</td>';
            $body .= '<td>' . h($action) . '</td>';
            $body .= '<td>' . h((string)$roll['roll_code']) . ' - ' . h((string)$roll['sku_code']) . '</td>';
            $body .= '<td>' . h($weight) . ' Kg</td>';
            $body .= '<td>' . h((string)($payload['waste_kg'] ?? '0')) . ' Kg</td>';
            $body .= '<td>' . h($detail) . '</td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div></div></details>';
    }

    $body .= '<script>
      (function () {
        function bindScale(btnId, inputId) {
          var btn = document.getElementById(btnId);
          var input = document.getElementById(inputId);
          if (!btn || !input) return;
          btn.addEventListener("click", async function () {
            btn.disabled = true;
            btn.textContent = "Leyendo...";
            try {
              // Lectura con estabilidad: varias lecturas iguales, estables y > 0 (no se acepta peso en movimiento).
              var data = await (window.UnibagHardware ? window.UnibagHardware.readStableWeight() : fetch("/api/scale/weight", { cache: "no-store" }).then(function (r) { return r.json(); }));
              if (!data || data.ok !== true) throw new Error((data && data.error) ? data.error : "No se pudo leer la balanza.");
              input.value = data.weight_kg;
              input.dispatchEvent(new Event("input", { bubbles: true }));
              input.dispatchEvent(new Event("change", { bubbles: true }));
            } catch (e) {
              alert(e && e.message ? e.message : "Error leyendo la balanza.");
            } finally {
              btn.disabled = false;
              btn.textContent = "Leer balanza";
            }
          });
        }
        bindScale("read_scale_process_weight", "process_weight_kg");
        bindScale("read_scale_chemical_input", "chemical_input_weight_kg");
        bindScale("read_scale_change_final", "change_final_roll_weight_kg");
        bindScale("read_scale_change_output", "change_output_roll_weight_kg");
        bindScale("read_scale_change_next", "change_next_process_weight_kg");
        bindScale("read_scale_finish_roll", "finish_final_roll_weight_kg");
        bindScale("read_scale_finish_chemicals", "finish_final_chemical_weight_kg");
        bindScale("read_scale_finish_output_roll", "finish_output_roll_weight_kg");
        bindScale("read_scale_waste", "waste_weight_kg");
        var cutMode = document.getElementById("ot_cut_destination_mode");
        var cutCustomerWrap = document.getElementById("ot_cut_customer_wrap");
        if (cutMode) {
          var syncCutMode = function () {
            var isCustomer = cutMode.value === "CUSTOMER_ORDER";
            if (cutCustomerWrap) cutCustomerWrap.style.display = isCustomer ? "" : "none";
          };
          cutMode.addEventListener("change", syncCutMode);
          syncCutMode();
        }
        var syncAniloxTable = function (table) {
          var rows = Array.prototype.slice.call(table.querySelectorAll("tr[data-anilox-row]"));
          rows.forEach(function (row, index) {
            var unit = index + 1;
            var unitLabel = row.querySelector("[data-anilox-unit]");
            var unitInput = row.querySelector("[data-anilox-unit-input]");
            if (unitLabel) unitLabel.textContent = String(unit);
            if (unitInput) unitInput.value = String(unit);
            var upBtn = row.querySelector(".anilox-move-btn[data-direction=\"up\"]");
            var downBtn = row.querySelector(".anilox-move-btn[data-direction=\"down\"]");
            if (upBtn) upBtn.disabled = index === 0;
            if (downBtn) downBtn.disabled = index === rows.length - 1;
          });
        };
        Array.prototype.slice.call(document.querySelectorAll("[data-anilox-table]")).forEach(function (table) {
          syncAniloxTable(table);
          table.addEventListener("click", function (event) {
            var button = event.target.closest(".anilox-move-btn");
            if (!button) return;
            var row = button.closest("tr[data-anilox-row]");
            if (!row || !row.parentNode) return;
            if (button.getAttribute("data-direction") === "up" && row.previousElementSibling) {
              row.parentNode.insertBefore(row, row.previousElementSibling);
              syncAniloxTable(table);
            }
            if (button.getAttribute("data-direction") === "down" && row.nextElementSibling) {
              row.parentNode.insertBefore(row.nextElementSibling, row);
              syncAniloxTable(table);
            }
          });
        });
      })();
    </script>';

    render($screenTitle, $body);
}

function renderErpWorkOrderReadOnlyScreen(
    array $ot,
    ?array $currentRoll,
    array $rollHistory,
    array $chemicalInputs,
    ?array $lastStart,
    ?array $lastFinish,
    array $materialRequests,
    array $wastes,
    array $boxes,
    array $pallets,
    ?array $outputRoll,
    array $assignedCliches = [],
    array $clicheUsageHistory = []
): void {
    $status = (string)($ot['status'] ?? '');
    $statusLabel = workOrderStatusLabel($status);
    $isStarted = $lastStart !== null;
    $isCutting = $status === 'CUTTING';
    $isClosed = $status === 'CLOSED';
    $currentStage = $isClosed ? 5 : ($isCutting ? 4 : ($isStarted ? 2 : 1));
    $stageTitles = [
        1 => 'Preparación OT',
        2 => 'Producción activa',
        3 => 'Cierre de impresión',
        4 => 'Corte de la OT',
        5 => 'Fabricación completa',
    ];
    if ($lastFinish !== null && !$isClosed && !$isCutting) {
        $currentStage = 3;
    }

    $statusTone = match ($status) {
        'CLOSED' => ['#ecfdf3', '#067647', '#abefc6'],
        'CUTTING' => ['#fff7ed', '#c4320a', '#fdba74'],
        'ACTIVE' => ['#eff8ff', '#175cd3', '#b2ddff'],
        default => ['#f8fafc', '#475467', '#d0d5dd'],
    };

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:20px;font-weight:800">Seguimiento OT ' . h((string)$ot['ot_code']) . '</div>
          <div class="muted">Vista ejecutiva de trazabilidad y movimientos de la orden. Sin acciones operativas desde ERP.</div>
        </div>
        <div class="row">
          <a class="btn secondary" href="/work-orders">Volver</a>
          <a class="btn secondary" href="/work-orders/' . (int)$ot['id'] . '/traceability">Ver trazabilidad completa</a>
        </div>
      </div>';

    $body .= '<div class="card" style="margin-bottom:12px;border-color:' . $statusTone[2] . ';background:' . $statusTone[0] . '">
        <div class="row" style="justify-content:space-between;align-items:flex-start">
          <div style="font-size:18px;font-weight:800">' . h((string)$ot['sku_final']) . '</div>
          <div style="padding:8px 12px;border-radius:999px;background:#fff;color:' . $statusTone[1] . ';font-weight:800;border:1px solid ' . $statusTone[2] . '">' . h($statusLabel) . '</div>
        </div>
        <div class="ot-meta-grid" style="margin-top:12px">
          <div class="ot-meta"><div class="muted">OT</div><div class="value">' . h((string)$ot['ot_code']) . '</div></div>
          <div class="ot-meta"><div class="muted">SKU final</div><div class="value">' . h((string)$ot['sku_final']) . '</div></div>
          <div class="ot-meta"><div class="muted">Cantidad objetivo</div><div class="value">' . h((string)($ot['target_qty'] ?? '-')) . '</div></div>
          <div class="ot-meta"><div class="muted">Fecha creación</div><div class="value">' . h((string)($ot['created_at'] ?? '-')) . '</div></div>
          <div class="ot-meta"><div class="muted">Plan ERP</div><div class="value">' . h((string)($ot['erp_plan_date'] ?? '-')) . '</div></div>
          <div class="ot-meta"><div class="muted">Máquina ERP</div><div class="value">' . h((string)($ot['erp_machine_label'] ?? '-')) . '</div></div>
          <div class="ot-meta"><div class="muted">Producción ERP</div><div class="value">' . h((string)($ot['erp_prod_number'] ?? '-')) . '</div></div>
          <div class="ot-meta"><div class="muted">Req ERP</div><div class="value">' . h((string)($ot['erp_req_id'] ?? '-')) . '</div></div>
          <div class="ot-meta"><div class="muted">Operario ERP</div><div class="value">' . h((string)($ot['erp_worker_name'] ?? '-')) . '</div></div>
          <div class="ot-meta"><div class="muted">Inicio producción</div><div class="value">' . h((string)($lastStart['created_at'] ?? '-')) . '</div></div>
          <div class="ot-meta"><div class="muted">Último cierre</div><div class="value">' . h((string)($lastFinish['created_at'] ?? '-')) . '</div></div>
        </div>
      </div>';

    $body .= '<div class="ot-stage-grid" style="margin-bottom:12px">';
    foreach ([1, 2, 3, 4, 5] as $stageNumber) {
        $isDone = $stageNumber < $currentStage;
        $isCurrent = $stageNumber === $currentStage;
        $stageClass = 'ot-stage-card' . ($isCurrent ? ' current' : '') . ($isDone ? ' done' : '');
        $stageState = $isDone ? 'Completado' : ($isCurrent ? 'En curso' : 'Pendiente');
        $body .= '<div class="' . $stageClass . '"><div class="muted">Etapa ' . $stageNumber . '</div><div style="font-weight:800;margin:4px 0 6px">' . h($stageTitles[$stageNumber]) . '</div><div class="muted">' . h($stageState) . '</div></div>';
    }
    $body .= '</div>';

    $body .= '<div class="dashboard-grid" style="margin-bottom:12px">';
    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Resumen operativo</div><div class="ot-meta-grid">
        <div class="ot-meta"><div class="muted">Bobina en proceso</div><div class="value">' . h((string)($currentRoll['roll_code'] ?? '-')) . '</div></div>
        <div class="ot-meta"><div class="muted">Bobina salida</div><div class="value">' . h((string)($outputRoll['roll_code'] ?? '-')) . '</div></div>
        <div class="ot-meta"><div class="muted">Solicitudes</div><div class="value">' . count($materialRequests) . '</div></div>
        <div class="ot-meta"><div class="muted">Movimientos bobina</div><div class="value">' . count($rollHistory) . '</div></div>
        <div class="ot-meta"><div class="muted">Mermas</div><div class="value">' . count($wastes) . '</div></div>
        <div class="ot-meta"><div class="muted">Tintas</div><div class="value">' . count($chemicalInputs) . '</div></div>
        <div class="ot-meta"><div class="muted">Cajas</div><div class="value">' . count($boxes) . '</div></div>
        <div class="ot-meta"><div class="muted">Pallets</div><div class="value">' . count($pallets) . '</div></div>
        <div class="ot-meta"><div class="muted">Clisés activos</div><div class="value">' . count($assignedCliches) . '</div></div>
      </div></div>';

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Observación ERP</div>
        <div class="muted" style="margin-bottom:10px">Esta vista está orientada a seguimiento gerencial. Desde aquí solo se consulta avance, estado y trazabilidad; la ejecución se realiza en Producción.</div>
        <div class="panel">
          <div style="font-weight:700;margin-bottom:6px">Situación actual</div>
          <div class="muted">Estado: ' . h($statusLabel) . ' · Etapa: ' . h($stageTitles[$currentStage]) . '</div>
          <div class="muted" style="margin-top:6px">Bobina actual: ' . h((string)($currentRoll['roll_code'] ?? '-')) . ' · Bobina salida: ' . h((string)($outputRoll['roll_code'] ?? '-')) . '</div>
        </div></div>';
    $body .= '</div>';

    $body .= '<div class="card" style="margin-bottom:12px"><div style="font-weight:800;margin-bottom:8px">Clisés OT</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Código</th><th>Descripción</th><th>Ubicación</th><th>Estado</th><th></th></tr></thead><tbody>';
    foreach ($assignedCliches as $assignedCliche) {
        $locationLabel = trim((string)($assignedCliche['location_detail'] ?? $assignedCliche['location_code'] ?? ''));
        $body .= '<tr>';
        $body .= '<td>' . h((string)$assignedCliche['code']) . '</td>';
        $body .= '<td>' . h((string)$assignedCliche['description']) . '</td>';
        $body .= '<td>' . h($locationLabel !== '' ? $locationLabel : '-') . '</td>';
        $body .= '<td>' . h(clicheStatusLabel((string)$assignedCliche['status'])) . '</td>';
        $body .= '<td><a class="btn secondary" href="/cliches/' . (int)$assignedCliche['id'] . '?work_order_id=' . (int)$ot['id'] . '">Ver clisé</a></td>';
        $body .= '</tr>';
    }
    if ($assignedCliches === []) {
        $body .= '<tr><td colspan="5" class="muted">No hay clisés activos asociados a esta OT.</td></tr>';
    }
    $body .= '</tbody></table></div><div style="margin-top:10px"><a class="btn secondary" href="/cliches?work_order_id=' . (int)$ot['id'] . '">Gestionar clisés</a></div></div>';

    if ($clicheUsageHistory !== []) {
        $body .= '<div class="card" style="margin-bottom:12px"><div style="font-weight:800;margin-bottom:8px">Historial de clisés</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Fecha</th><th>Acción</th><th>Clisé</th><th>Detalle</th></tr></thead><tbody>';
        foreach ($clicheUsageHistory as $clicheLog) {
            $detailText = trim((string)($clicheLog['notes'] ?? ''));
            $locationText = trim((string)($clicheLog['to_location_code'] ?? ''));
            $body .= '<tr>';
            $body .= '<td>' . h((string)$clicheLog['created_at']) . '</td>';
            $body .= '<td>' . h(eventTypeLabel('CLICHE_' . (string)$clicheLog['action_type'])) . '</td>';
            $body .= '<td><a href="/cliches/' . (int)$clicheLog['cliche_id'] . '?work_order_id=' . (int)$ot['id'] . '">' . h((string)$clicheLog['cliche_code']) . '</a></td>';
            $body .= '<td>' . h(($locationText !== '' ? $locationText : '-') . ($detailText !== '' ? ' | ' . $detailText : '')) . '</td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div></div>';
    }

    $body .= '<div class="card" style="margin-bottom:12px"><div style="font-weight:800;margin-bottom:8px">Movimientos de bobinas</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Fecha</th><th>Acción</th><th>Bobina</th><th>Peso</th><th>Merma</th><th>Detalle</th></tr></thead><tbody>';
    foreach ($rollHistory as $roll) {
        $payload = $roll['payload_data'] ?? [];
        $action = (string)$roll['type'] === 'WORK_ORDER_ROLL_ATTACHED' ? 'Ingreso a OT' : 'Salida de OT';
        $weight = (string)$roll['type'] === 'WORK_ORDER_ROLL_ATTACHED'
            ? (string)($payload['process_weight_kg'] ?? '-')
            : (string)($payload['final_weight_kg'] ?? '-');
        $detail = (string)$roll['type'] === 'WORK_ORDER_ROLL_RELEASED'
            ? ('Motivo: ' . (string)($payload['reason'] ?? '-'))
            : ('Operador: ' . (string)($payload['operator_name'] ?? '-'));
        $body .= '<tr><td>' . h((string)$roll['created_at']) . '</td><td>' . h($action) . '</td><td>' . h((string)$roll['roll_code']) . '</td><td>' . h($weight) . ' Kg</td><td>' . h((string)($payload['waste_kg'] ?? '0')) . ' Kg</td><td>' . h($detail) . '</td></tr>';
    }
    if ($rollHistory === []) {
        $body .= '<tr><td colspan="6" class="muted">Sin movimientos de bobina registrados.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';

    $body .= '<div class="trace-grid" style="margin-bottom:12px">';
    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Solicitudes y abastecimiento</div><div class="table-wrap"><table><thead><tr><th>Tipo</th><th>Material</th><th>Pedida</th><th>Entregada</th><th>Estado</th></tr></thead><tbody>';
    foreach ($materialRequests as $request) {
        $body .= '<tr><td>' . h(materialRequestTypeLabel((string)($request['request_type'] ?? 'ROLL'))) . '</td><td>' . h((string)($request['requested_item'] ?? '-')) . '</td><td>' . h(formatReceptionValue((float)($request['requested_qty'] ?? 0), (string)($request['requested_unit'] ?? 'Unid.'))) . '</td><td>' . h(formatReceptionValue((float)($request['delivered_qty'] ?? 0), (string)($request['requested_unit'] ?? 'Unid.'))) . '</td><td>' . h(materialRequestStatusLabel((string)($request['status'] ?? ''))) . '</td></tr>';
    }
    if ($materialRequests === []) {
        $body .= '<tr><td colspan="5" class="muted">Sin solicitudes registradas.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Tintas y mermas</div><div class="table-wrap"><table><thead><tr><th>Tipo</th><th>Detalle</th><th>Valor</th><th>Fecha</th></tr></thead><tbody>';
    foreach ($chemicalInputs as $input) {
        $body .= '<tr><td>Tinta</td><td>' . h((string)$input['chemical_code']) . '</td><td>' . h((string)$input['weight_kg']) . ' Kg</td><td>' . h((string)$input['created_at']) . '</td></tr>';
    }
    foreach ($wastes as $waste) {
        $body .= '<tr><td>Merma</td><td>' . h((string)$waste['reason']) . '</td><td>' . h((string)$waste['weight_kg']) . ' Kg</td><td>' . h((string)$waste['created_at']) . '</td></tr>';
    }
    if ($chemicalInputs === [] && $wastes === []) {
        $body .= '<tr><td colspan="4" class="muted">Sin tintas ni mermas registradas.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    $body .= '</div>';

    $body .= '<div class="trace-grid">';
    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Cajas generadas</div><div class="table-wrap"><table><thead><tr><th>Código</th><th>Unidades</th><th>Pallet</th><th></th></tr></thead><tbody>';
    foreach ($boxes as $box) {
        $body .= '<tr><td>' . h((string)$box['box_code']) . '</td><td>' . h((string)$box['units_qty']) . '</td><td>' . h((string)($box['pallet_code'] ?? '-')) . '</td><td><a class="btn secondary" href="/boxes/' . (int)$box['id'] . '">Ver</a></td></tr>';
    }
    if ($boxes === []) {
        $body .= '<tr><td colspan="4" class="muted">Sin cajas generadas.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Pallets generados</div><div class="table-wrap"><table><thead><tr><th>Código</th><th>Cajas</th><th>Destino</th><th></th></tr></thead><tbody>';
    foreach ($pallets as $pallet) {
        $body .= '<tr><td>' . h((string)$pallet['pallet_code']) . '</td><td>' . h((string)$pallet['box_count']) . '</td><td>' . h((string)$pallet['destination_mode']) . '</td><td><a class="btn secondary" href="/pallets/' . (int)$pallet['id'] . '">Ver</a></td></tr>';
    }
    if ($pallets === []) {
        $body .= '<tr><td colspan="4" class="muted">Sin pallets generados.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    $body .= '</div>';

    render('Seguimiento OT', $body);
}

function renderProductionShiftSessionsScreen(
    array $machines,
    string $currentOperatorName,
    ?array $selectedMachine = null,
    ?array $currentShiftSession = null,
    ?string $flashMessage = null,
    bool $flashIsError = false
): void {
    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Iniciar / Terminar turno</div>
        </div>
        <div class="row">
          <a class="btn secondary" href="/">Inicio producción</a>
          <a class="btn secondary" href="/work-orders?view=active">OT en proceso</a>
        </div>
      </div>';

    if ($flashMessage !== null && $flashMessage !== '') {
        $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
    }

    if ($currentShiftSession !== null) {
        $body .= '<div class="card" style="margin-bottom:12px"><div style="font-weight:800;margin-bottom:8px">Terminar turno activo</div><div class="ot-meta-grid">';
        $body .= '<div class="ot-meta"><div class="muted">Operador</div><div class="value">' . h((string)($currentShiftSession['operator_name'] ?? $currentOperatorName)) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Tipo</div><div class="value">' . h((string)($currentShiftSession['machine_type_name'] ?? '-')) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Máquina</div><div class="value">' . h((string)($currentShiftSession['machine_name'] ?? '-')) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Etapa</div><div class="value">' . h(machineProcessStageLabel((string)($currentShiftSession['process_stage'] ?? ''))) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Turno</div><div class="value">' . h((string)($currentShiftSession['shift_label'] ?? '-')) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Inicio</div><div class="value">' . h((string)($currentShiftSession['started_at'] ?? '-')) . '</div></div>';
        $body .= '</div>';
        if (trim((string)($currentShiftSession['ot_code'] ?? '')) !== '') {
            $body .= '<div class="ok" style="margin-top:12px">Trabajando OT ' . h((string)$currentShiftSession['ot_code']) . ' en ' . h((string)($currentShiftSession['machine_name'] ?? '')) . '.</div>';
        }
        $body .= '<form method="post" action="/production/shifts/' . (int)$currentShiftSession['id'] . '/end" style="margin-top:12px">';
        $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
        $body .= '<button class="btn secondary" type="submit" style="min-width:220px">Terminar turno</button></form></div>';
    }

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Máquinas</div><div class="table-wrap"><table><thead><tr><th>Tipo</th><th>Máquina</th><th>Turno</th><th>Horario</th><th>Etapa</th><th>Opciones</th></tr></thead><tbody>';
    foreach ($machines as $machine) {
        $isActive = trim((string)($machine['session_status'] ?? '')) === 'ACTIVE';
        $turnLabel = $isActive ? shiftSessionStatusLabel((string)$machine['session_status']) : 'Sin turno';
        $timeLabel = $isActive ? (string)($machine['started_at'] ?? '-') : '-';
        $stageLabel = $isActive ? machineProcessStageLabel((string)($machine['process_stage'] ?? '')) : machineProcessStageLabel((string)($machine['production_area'] ?? $machine['machine_type_area'] ?? 'PRODUCTION'));
        $body .= '<tr><td>' . h((string)($machine['machine_type_name'] ?? '-')) . '</td><td>' . h((string)$machine['name']) . '</td><td>' . h($turnLabel) . '</td><td>' . h($timeLabel) . '</td><td>' . h($stageLabel) . '</td><td>';
        if ($isActive) {
            $body .= '<div class="muted" style="margin-bottom:6px">' . h((string)($machine['operator_name'] ?? '-')) . '</div>';
            if (trim((string)($machine['active_work_order_code'] ?? '')) !== '') {
                $body .= '<div class="muted" style="margin-bottom:6px">OT ' . h((string)$machine['active_work_order_code']) . '</div>';
            }
            $body .= '<span class="muted">Turno activo</span>';
        } else {
            $body .= '<form method="post" action="/production/shifts/start" style="margin:0">'
                . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
                . '<input type="hidden" name="machine_id" value="' . (int)$machine['id'] . '">'
                . '<button class="btn" type="submit">Iniciar turno</button>'
                . '</form>';
        }
        $body .= '</td></tr>';
    }
    if ($machines === []) {
        $body .= '<tr><td colspan="6" class="muted">No hay máquinas configuradas todavía.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';

    render('Iniciar / Terminar turno', $body);
}



function handleWorkOrderErpRoutes(string $path, string $method, ReceptionService $service, PrintService $printer, PDO $erpPdo, string $currentOperatorName): bool
{
    if (handleAniloxClicheAssignRoutes($path, $method, $service, $currentOperatorName)) {
        return true;
    }
    if (handleSetupApprovalRoutes($path, $method, $service, $erpPdo, $currentOperatorName)) {
        return true;
    }
if ($path === '/production/shifts' && $method === 'GET') {
    $machines = $service->listProductionMachinesWithSessions();
    $selectedMachineId = isset($_GET['machine_id']) ? (int)$_GET['machine_id'] : 0;
    $selectedMachine = $selectedMachineId > 0 ? $service->getProductionMachine($selectedMachineId) : null;
    $currentShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['started']) && $_GET['started'] === '1') {
        $flashMessage = 'Turno iniciado correctamente.';
    } elseif (isset($_GET['ended']) && $_GET['ended'] === '1') {
        $flashMessage = 'Turno cerrado correctamente.';
    } elseif (isset($_GET['error']) && trim((string)$_GET['error']) !== '') {
        $flashMessage = trim((string)$_GET['error']);
        $flashIsError = true;
    }
    renderProductionShiftSessionsScreen($machines, $currentOperatorName, $selectedMachine, $currentShiftSession, $flashMessage, $flashIsError);
    exit;
}

if ($path === '/production/shifts/start' && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $result = $service->startShiftSession(
        (int)($_POST['machine_id'] ?? 0),
        $currentOperatorName,
        (string)($_POST['helper_name'] ?? ''),
        null,
        null,
        (string)($_POST['comments'] ?? '')
    );
    if (($result['ok'] ?? false) === true) {
        $machine = $service->getProductionMachine((int)($_POST['machine_id'] ?? 0));
        if (
            $machine !== null
            && (
                strtoupper(trim((string)($machine['production_area'] ?? ''))) === 'RESIDUOS'
                || strtoupper(trim((string)($machine['machine_type_area'] ?? ''))) === 'RESIDUOS'
                || strtoupper(trim((string)($machine['machine_type_code'] ?? ''))) === 'GESTION_RESIDUOS'
            )
        ) {
            header('Location: /waste/management?started=1');
        } else {
            header('Location: /production/shifts?started=1');
        }
        exit;
    }
    $error = trim((string)reset($result['errors']));
    header('Location: /production/shifts?machine_id=' . (int)($_POST['machine_id'] ?? 0) . '&error=' . rawurlencode($error !== '' ? $error : 'No se pudo iniciar el turno.'));
    exit;
}

if (preg_match('#^/production/shifts/(\d+)/end$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $result = $service->endShiftSession((int)$m[1], '', (string)($_POST['comments'] ?? ''));
    if (($result['ok'] ?? false) === true) {
        header('Location: /production/shifts?ended=1');
        exit;
    }
    $error = trim((string)reset($result['errors']));
    header('Location: /production/shifts?error=' . rawurlencode($error !== '' ? $error : 'No se pudo cerrar el turno.'));
    exit;
}

if ($path === '/work-orders' && $method === 'GET') {
    if (currentSessionArea() === 'PRODUCTION') {
        $view = isset($_GET['view']) ? strtolower(trim((string)$_GET['view'])) : 'active';
        if ($view === 'pending') {
            redirectResponse('/production/work-orders/new');
            exit;
        }
        if ($view === 'closed' || $view === 'history') {
            redirectResponse('/production/work-orders/history');
            exit;
        }
        redirectResponse('/production/work-orders/active');
        exit;
    }
    $view = isset($_GET['view']) ? strtolower(trim((string)$_GET['view'])) : 'pending';
    if (!in_array($view, ['pending', 'active', 'closed'], true)) {
        $view = 'pending';
    }
    $wos = $service->listWorkOrdersByView($view);
    $erpReadOnly = isErpProductionReadOnlyMode();
    $activeShiftSession = currentSessionArea() === 'PRODUCTION'
        ? $service->getActiveShiftSessionByOperator($currentOperatorName)
        : null;
    if ($view === 'pending' && !$erpReadOnly) {
        $wos = filterPendingWorkOrdersByShiftMachine($wos, $activeShiftSession);
    }

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">' . ($erpReadOnly ? 'Seguimiento de órdenes de trabajo' : 'Traspasos de materiales por OT') . '</div>
          <div class="muted">' . ($erpReadOnly ? 'Consulta formal del estado de la OT, su proceso actual y los movimientos registrados.' : 'Consulta OT pendientes, en producción/corte y fabricadas completas con su trazabilidad.') . '</div>
        </div>
        <div class="row">
          <a class="btn secondary" href="' . (currentSessionArea() === 'PRODUCTION' ? '/production/machines' : '/') . '">Volver</a>
        </div>
      </div>';

    if ($view === 'pending') {
        $body .= '<div class="card"><table><thead><tr><th>ID</th><th>OT</th><th>SKU final</th><th>Cantidad</th><th>Plan ERP</th><th>Máquina</th><th></th></tr></thead><tbody>';
        foreach ($wos as $wo) {
            $planLabel = trim((string)($wo['erp_plan_label'] ?? ''));
            if ($planLabel === '') {
                $planLabel = (string)$wo['created_at'];
            }
            $body .= '<tr>';
            $body .= '<td>' . (int)$wo['id'] . '</td>';
            $body .= '<td>' . h((string)$wo['ot_code']) . '</td>';
            $body .= '<td>' . h((string)$wo['sku_final']) . '</td>';
            $body .= '<td>' . h((string)($wo['target_qty'] ?? '')) . '</td>';
            $body .= '<td>' . h($planLabel) . '</td>';
            $body .= '<td>' . h((string)($wo['erp_machine_label'] !== '' ? $wo['erp_machine_label'] : '-')) . '</td>';
            $body .= '<td>' . ($erpReadOnly
                ? '<a class="btn secondary" href="/work-orders/' . (int)$wo['id'] . '/start">Ver seguimiento</a>'
                : '<form method="post" action="/work-orders/' . (int)$wo['id'] . '/activate" style="margin:0"><input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><button class="btn secondary" type="submit">Activar</button></form>')
                . '</td>';
            $body .= '</tr>';
        }
        if ($wos === []) {
            $body .= '<tr><td colspan="7" class="muted">Sin OTs pendientes.</td></tr>';
        }
        $body .= '</tbody></table></div>';
    } elseif ($view === 'active') {
        $body .= '<div class="card"><table><thead><tr><th>ID</th><th>OT</th><th>SKU final</th><th>Etapa</th><th>Operador</th><th>Máquina</th><th>Referencia</th><th>Detalle</th><th></th><th></th></tr></thead><tbody>';
        foreach ($wos as $wo) {
            $erpReference = trim((string)($wo['erp_reference_label'] ?? ''));
            if ($erpReference === '') {
                $erpReference = '-';
            }
            $body .= '<tr>';
            $body .= '<td>' . (int)$wo['id'] . '</td>';
            $body .= '<td>' . h((string)$wo['ot_code']) . '</td>';
            $body .= '<td>' . h((string)$wo['sku_final']) . '</td>';
            $body .= '<td>' . h((string)($wo['status_label'] ?? '-')) . '</td>';
            $body .= '<td>' . h((string)$wo['operator_label']) . '</td>';
            $body .= '<td>' . h((string)($wo['erp_machine_label'] !== '' ? $wo['erp_machine_label'] : '-')) . '</td>';
            $body .= '<td>' . h($erpReference) . '</td>';
            $body .= '<td>' . h((string)$wo['current_chemical_label']) . '</td>';
            $body .= '<td><a class="btn secondary" href="/work-orders/' . (int)$wo['id'] . '/start">' . ($erpReadOnly ? 'Ver seguimiento' : 'Ver OT') . '</a></td>';
            $body .= '<td>' . (canAccessWorkOrderTraceability()
                ? '<a class="btn secondary" href="/work-orders/' . (int)$wo['id'] . '/traceability">Trazabilidad</a>'
                : '-') . '</td>';
            $body .= '</tr>';
        }
        if ($wos === []) {
            $body .= '<tr><td colspan="10" class="muted">Sin OTs en producción o corte.</td></tr>';
        }
        $body .= '</tbody></table></div>';
    } else {
        $body .= '<div class="card"><table><thead><tr><th>ID</th><th>OT</th><th>SKU final</th><th>Operador final</th><th>Plan ERP</th><th>Fecha fabricación</th><th>Cajas</th><th></th><th></th></tr></thead><tbody>';
        foreach ($wos as $wo) {
            $planLabel = trim((string)($wo['erp_plan_label'] ?? ''));
            if ($planLabel === '') {
                $planLabel = '-';
            }
            $body .= '<tr>';
            $body .= '<td>' . (int)$wo['id'] . '</td>';
            $body .= '<td>' . h((string)$wo['ot_code']) . '</td>';
            $body .= '<td>' . h((string)$wo['sku_final']) . '</td>';
            $body .= '<td>' . h((string)$wo['operator_label']) . '</td>';
            $body .= '<td>' . h($planLabel) . '</td>';
            $body .= '<td>' . h((string)($wo['finished_at'] !== '' ? $wo['finished_at'] : '-')) . '</td>';
            $body .= '<td>' . h((string)($wo['box_qty'] !== '' ? $wo['box_qty'] : '-')) . '</td>';
            $body .= '<td><a class="btn secondary" href="/work-orders/' . (int)$wo['id'] . '/start">' . ($erpReadOnly ? 'Ver seguimiento' : 'Ver OT') . '</a></td>';
            $body .= '<td>' . (canAccessWorkOrderTraceability()
                ? '<a class="btn secondary" href="/work-orders/' . (int)$wo['id'] . '/traceability">Trazabilidad</a>'
                : '-') . '</td>';
            $body .= '</tr>';
        }
        if ($wos === []) {
            $body .= '<tr><td colspan="9" class="muted">Sin OTs finalizadas.</td></tr>';
        }
        $body .= '</tbody></table></div>';
    }

    render('OTs', $body);
    exit;
}

if ($path === '/work-orders/new' && $method === 'GET') {
    // Redirigir de forma transparente al catálogo moderno de OTs agendadas para la máquina
    redirectResponse('/production/work-orders/new');
    exit;
}

if ($path === '/work-orders' && $method === 'POST') {
    http_response_code(403);
    render('OT desde ERP', '<div class="card"><div style="font-size:18px;font-weight:800;margin-bottom:8px">Creación manual no disponible</div><div class="muted">Las órdenes de trabajo deben ser cargadas desde el ERP. Este módulo no permite crear OT manualmente.</div><div style="margin-top:12px"><a class="btn secondary" href="/work-orders">Volver a OT</a></div></div>');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/activate$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $service->setActiveWorkOrder($workOrderId, $currentOperatorName);
    header('Location: /work-orders/' . $workOrderId . '/start?activated=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/start$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    if ((string)($ot['status'] ?? '') === 'CUTTING') {
        $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
        if ($finishApproval === null) {
            header('Location: /work-orders/' . $workOrderId . '/finish-approval?_redirect_from_start=1');
            exit;
        }

        $lastSealingFinish = $service->getLastWorkOrderSealingFinish($workOrderId);
        $packagingSetupApproval = $service->getLastWorkOrderPackagingSetupApproval($workOrderId);
        $packagingSetupEvents = $service->listWorkOrderPackagingSetupEvents($workOrderId);
        $packagingProductionEvents = $service->listWorkOrderPackagingProductionEvents($workOrderId);
        if ($packagingSetupApproval !== null || !empty($packagingSetupEvents) || !empty($packagingProductionEvents)) {
            header('Location: /work-orders/' . $workOrderId . '/packaging/setup?setup_started=1&_redirect_from_start=1');
            exit;
        }

        if ($lastSealingFinish !== null) {
            header('Location: /work-orders/' . $workOrderId . '/packaging?sealing_finished=1&_redirect_from_start=1');
            exit;
        }

        $setupApproval = $service->getLastWorkOrderSealingSetupApproval($workOrderId);
        $setupEvents = $service->listWorkOrderSealingSetupEvents($workOrderId);
        $productionEvents = $service->listWorkOrderSealingProductionEvents($workOrderId);
        if ($setupApproval !== null || !empty($setupEvents) || !empty($productionEvents)) {
            header('Location: /work-orders/' . $workOrderId . '/sealing/setup?_redirect_from_start=1');
            exit;
        }

        header('Location: /work-orders/' . $workOrderId . '/sealing?_redirect_from_start=1');
        exit;
    }

    if (!in_array((string)($ot['status'] ?? ''), ['CUTTING', 'CLOSED'], true)) {
        $setupApproval = $service->getLastWorkOrderSetupApproval($workOrderId);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        if ($lastStart !== null) {
            header('Location: /work-orders/' . $workOrderId . '/in-progress');
            exit;
        }
        if ($setupApproval !== null) {
            header('Location: /work-orders/' . $workOrderId . '/setup');
            exit;
        }
    }
    $chemicals = $service->listChemicals();
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $lastStart = $service->getLastWorkOrderStart($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
    $availableMaterialRolls = $service->listAvailableRollsForMaterialRequest();
    $wastes = $service->listProductionWastesByWorkOrder($workOrderId);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $assignedCliches = $service->listClichesAssignedToWorkOrder($workOrderId);
    $clicheUsageHistory = $service->listClicheUsageLogsByWorkOrder($workOrderId);
    $cutWarehouses = array_values(array_filter($service->listWarehouses(), static fn(array $warehouse): bool => in_array((int)$warehouse['code'], [700, 1000], true)));
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
    if (isset($_GET['activated']) && $_GET['activated'] === '1') {
        $flashMessage = 'OT activada. Continúa con bobina, tintas de entrada e inicio.';
    } elseif (isset($_GET['roll_attached']) && $_GET['roll_attached'] === '1') {
        $flashMessage = 'Bobina asignada y pesada correctamente para la OT.';
    } elseif (isset($_GET['chemical_input']) && $_GET['chemical_input'] === '1') {
        $flashMessage = 'Tinta de entrada registrada correctamente.';
    } elseif (isset($_GET['anilox_saved']) && $_GET['anilox_saved'] === '1') {
        $flashMessage = 'Configuración de anilox guardada correctamente.';
    } elseif (isset($_GET['machine_header_saved']) && $_GET['machine_header_saved'] === '1') {
        $flashMessage = 'Cabecera de máquina actualizada correctamente.';
    } elseif (isset($_GET['material_requested']) && $_GET['material_requested'] === '1') {
        $flashMessage = 'Solicitud enviada correctamente a bodega.';
    } elseif (isset($_GET['material_error']) && trim((string)$_GET['material_error']) !== '') {
        $flashMessage = trim((string)$_GET['material_error']);
        $flashIsError = true;
    } elseif (isset($_GET['roll_changed']) && $_GET['roll_changed'] === '1') {
        $flashMessage = 'Cambio de bobina registrado correctamente.';
        if (isset($_GET['printed']) && $_GET['printed'] === '1') {
            $flashMessage .= ' La etiqueta de la bobina salida se envió a impresión.';
        } elseif (isset($_GET['printed']) && $_GET['printed'] === '0') {
            $flashMessage .= ' Revisa la etiqueta de la bobina salida para imprimirla.';
        }
    } elseif (isset($_GET['finished']) && $_GET['finished'] === '1') {
        $flashMessage = 'La impresión fue finalizada y la OT pasó a corte.';
        if (isset($_GET['finish_approved']) && $_GET['finish_approved'] === '1') {
            $flashMessage .= ' El cierre de flexografía fue validado por supervisor.';
        }
        if (isset($_GET['printed']) && $_GET['printed'] === '1') {
            $flashMessage .= ' La etiqueta se envió a impresión.';
        } elseif (isset($_GET['printed']) && $_GET['printed'] === '0') {
            $flashMessage .= ' Revisa la etiqueta de la bobina salida para imprimirla.';
        }
        if (isset($_GET['box_printed']) && $_GET['box_printed'] === '1') {
            $flashMessage .= ' También se imprimió la etiqueta de cajas.';
        } elseif (isset($_GET['box_printed']) && $_GET['box_printed'] === '0') {
            $flashMessage .= ' Revisa la etiqueta de cajas para imprimirla.';
        }
    } elseif (isset($_GET['cut_completed']) && $_GET['cut_completed'] === '1') {
        $flashMessage = 'Corte completado. La OT quedó fabricada completamente.';
    } elseif (isset($_GET['cut_error']) && trim((string)$_GET['cut_error']) !== '') {
        $flashMessage = trim((string)$_GET['cut_error']);
        $flashIsError = true;
    } elseif (isset($_GET['closed']) && $_GET['closed'] === '1') {
        $flashMessage = 'La OT ya fue cerrada correctamente.';
    }
    renderWorkOrderStartScreen($ot, $chemicals, $currentRoll, $rollHistory, $chemicalInputs, $lastStart, $lastFinish, $currentOperatorName, $flashMessage, $flashIsError, [], $materialRequests, $wastes, $boxes, $pallets, $outputRoll, $availableMaterialRolls, $cutWarehouses, $activeShiftSession, $assignedCliches, $clicheUsageHistory);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/finish-data$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
    $status = (string)($ot['status'] ?? '');
    if (in_array($status, ['CUTTING', 'CLOSED'], true)) {
        if ($lastFinish !== null && $finishApproval === null) {
            header('Location: /work-orders/' . $workOrderId . '/finish-approval?_redirect_from_finishdata=1');
        } else {
            header('Location: /work-orders/' . $workOrderId . '/start?_redirect_from_finishdata=1');
        }
        exit;
    }
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
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
    renderWorkOrderSetupScreen(
        $ot,
        $activeShiftSession,
        $currentOperatorName,
        $chemicalInputs,
        $boxes,
        $pallets,
        $currentRoll,
        $outputRoll,
        $assignedCliches,
        $processEvents,
        null,
        false,
        true
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $finishApproval = $service->getLastWorkOrderFinishApproval($workOrderId);
    if ($finishApproval === null) {
        if (isset($_GET['_redirect_from_finishapproval']) && $_GET['_redirect_from_finishapproval'] === '1') {
            render('Validación pendiente', '<div class="card"><div style="font-size:18px;font-weight:800;margin-bottom:8px;color:#dc2626">Validación de cierre pendiente</div><div class="muted" style="margin-bottom:12px">La OT aún no tiene validada la etapa de flexografía por un supervisor. Debes aprobar primero el cierre de impresión.</div><div class="row" style="gap:8px;justify-content:flex-start"><a class="btn secondary" href="/work-orders/' . (int)$workOrderId . '/start">Volver a la OT</a><a class="btn" href="/work-orders/' . (int)$workOrderId . '/finish-approval?_redirect_from_sealing=1">Ir a validar cierre</a></div></div>');
            exit;
        }
        header('Location: /work-orders/' . $workOrderId . '/finish-approval?_redirect_from_sealing=1');
        exit;
    }
    // Check if setup is already approved or there are existing events
    $setupApproval = $service->getLastWorkOrderSealingSetupApproval($workOrderId);
    $setupEvents = $service->listWorkOrderSealingSetupEvents($workOrderId);
    $productionEvents = $service->listWorkOrderSealingProductionEvents($workOrderId);
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
        header('Location: /work-orders/' . $workOrderId . '/packaging?sealing_finished=1&_redirect_from_sealing=1');
        exit;
    }
    if ($setupApproval !== null || !empty($setupEvents) || !empty($productionEvents)) {
        header('Location: /work-orders/' . $workOrderId . '/sealing/setup?_redirect_from_sealing=1');
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $sealingMachines = array_values(array_filter(
        $service->listProductionMachinesWithSessions(),
        static fn(array $machine): bool => strtoupper(trim((string)($machine['machine_type_code'] ?? ''))) === 'SELLADO'
            || strtoupper(trim((string)($machine['production_area'] ?? ''))) === 'SEALING'
    ));
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['finish_approved']) && $_GET['finish_approved'] === '1') {
        $approvedBy = trim((string)($_GET['approved_by'] ?? ''));
        $flashMessage = $approvedBy !== ''
            ? 'Flexografía validada por ' . $approvedBy . '. Continúa con la información inicial de Selladora.'
            : 'Flexografía validada. Continúa con la información inicial de Selladora.';
    }
    renderWorkOrderSealingInfoScreen($ot, $outputRolls, $activeShiftSession, $lastFinish, $sealingMachines, $helperOptions, $flashMessage, $flashIsError);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing/production/start$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $result = $service->startWorkOrderSealingProductionEvent(
        $workOrderId,
        $currentOperatorName,
        (string)($_POST['comments'] ?? '')
    );
    if (($result['ok'] ?? false) === true) {
        header('Location: /work-orders/' . $workOrderId . '/sealing/setup?production_started=1');
    } else {
        header('Location: /work-orders/' . $workOrderId . '/sealing/setup?process_error=' . urlencode(implode(' ', array_map('strval', array_values($result['errors'] ?? [])))));
    }
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing/finish-data$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    if ($service->getLastWorkOrderFinishApproval($workOrderId) === null) {
        header('Location: /work-orders/' . $workOrderId . '/finish-approval');
        exit;
    }
    $openProductionEvent = $service->getOpenWorkOrderSealingProductionEvent($workOrderId);
    if ($openProductionEvent === null) {
        $setupApproval = $service->getLastWorkOrderSealingSetupApproval($workOrderId);
        $lastFinish = $service->getLastWorkOrderSealingFinish($workOrderId);
        $finishAfterSetup = false;
        if ($lastFinish !== null) {
            if ($setupApproval === null) {
                $finishAfterSetup = true;
            } else {
                $finishTs = strtotime((string)($lastFinish['created_at'] ?? ''));
                $setupTs = strtotime((string)($setupApproval['created_at'] ?? ''));
                $finishAfterSetup = ($finishTs === false || $setupTs === false) ? true : ($finishTs >= $setupTs);
            }
        }
        if ($finishAfterSetup) {
            header('Location: /work-orders/' . $workOrderId . '/packaging?sealing_finished=1');
        } else {
            header('Location: /work-orders/' . $workOrderId . '/sealing/setup?process_error=' . urlencode('Debes iniciar la producción de Selladora antes de terminarla.'));
        }
        exit;
    }
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $helperOptions = $service->listProductionPersonnelNames();
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    renderWorkOrderSealingFinishScreen($ot, $outputRolls, $activeShiftSession, $helperOptions);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/sealing/finish$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }

    $result = $service->finishWorkOrderSealingProduction(
        $workOrderId,
        (int)($_POST['sealing_principal_counter'] ?? 0),
        (int)($_POST['sealing_receiver_counter'] ?? 0),
        (string)($_POST['sealing_finish_comments'] ?? ''),
        is_array($_POST['sealing_waste_weight'] ?? null) ? $_POST['sealing_waste_weight'] : [],
        is_array($_POST['sealing_waste_comment'] ?? null) ? $_POST['sealing_waste_comment'] : [],
        $currentOperatorName
    );
    if (($result['ok'] ?? false) !== true) {
        $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
        $helperOptions = $service->listProductionPersonnelNames();
        $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
        if ($activeShiftSession === null) {
            $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
        }
        renderWorkOrderSealingFinishScreen(
            $ot,
            $outputRolls,
            $activeShiftSession,
            $helperOptions,
            implode(' ', array_map('strval', array_values($result['errors'] ?? []))),
            true,
            [
                'sealing_principal_counter' => (string)($_POST['sealing_principal_counter'] ?? ''),
                'sealing_receiver_counter' => (string)($_POST['sealing_receiver_counter'] ?? ''),
                'sealing_finish_comments' => (string)($_POST['sealing_finish_comments'] ?? ''),
                'sealing_waste_weight' => is_array($_POST['sealing_waste_weight'] ?? null) ? $_POST['sealing_waste_weight'] : [],
                'sealing_waste_comment' => is_array($_POST['sealing_waste_comment'] ?? null) ? $_POST['sealing_waste_comment'] : [],
            ]
        );
        exit;
    }
    header('Location: /work-orders/' . $workOrderId . '/packaging?sealing_finished=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging$#', $path, $m) === 1 && $method === 'GET') {
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
    $setupApproval = $service->getLastWorkOrderPackagingSetupApproval($workOrderId);
    $setupEvents = $service->listWorkOrderPackagingSetupEvents($workOrderId);
    $productionEvents = $service->listWorkOrderPackagingProductionEvents($workOrderId);
    if ($setupApproval !== null || !empty($setupEvents) || !empty($productionEvents)) {
        header('Location: /work-orders/' . $workOrderId . '/packaging/setup?setup_started=1&_redirect_from_packaging=1');
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
    $message = isset($_GET['sealing_finished'])
        ? 'Produccion de Selladora terminada. Continua con Embalaje.'
        : null;
    renderWorkOrderPackagingInfoScreen($ot, $outputRolls, $activeShiftSession, $helperOptions, $boxes, $pallets, $message);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/close$#', $path, $m) === 1 && $method === 'GET') {
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
    renderWorkOrderPackagingFinishScreen(
        $ot,
        $outputRolls,
        $activeShiftSession,
        $helperOptions,
        $boxes,
        $pallets,
        null,
        false,
        ['close_modal_open' => '1']
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/production/start$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $result = $service->startWorkOrderPackagingProductionEvent(
        $workOrderId,
        $currentOperatorName,
        (string)($_POST['comments'] ?? '')
    );
    if (($result['ok'] ?? false) === true) {
        header('Location: /work-orders/' . $workOrderId . '/packaging/setup?production_started=1');
    } else {
        header('Location: /work-orders/' . $workOrderId . '/packaging/setup?process_error=' . urlencode(implode(' ', array_map('strval', array_values($result['errors'] ?? [])))));
    }
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/finish-data$#', $path, $m) === 1 && $method === 'GET') {
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
    $openProductionEvent = $service->getOpenWorkOrderPackagingProductionEvent($workOrderId);
    if ($openProductionEvent === null) {
        $setupApproval = $service->getLastWorkOrderPackagingSetupApproval($workOrderId);
        $lastFinish = $service->getLastWorkOrderPackagingFinish($workOrderId);
        $finishAfterSetup = false;
        if ($lastFinish !== null) {
            if ($setupApproval === null) {
                $finishAfterSetup = true;
            } else {
                $finishTs = strtotime((string)($lastFinish['created_at'] ?? ''));
                $setupTs = strtotime((string)($setupApproval['created_at'] ?? ''));
                $finishAfterSetup = ($finishTs === false || $setupTs === false) ? true : ($finishTs >= $setupTs);
            }
        }
        if ($finishAfterSetup) {
            header('Location: /work-orders/' . $workOrderId . '/packaging/setup?packaging_finished=1');
        } else {
            header('Location: /work-orders/' . $workOrderId . '/packaging/setup?process_error=' . urlencode('Debes iniciar la producción de Embalaje antes de terminarla.'));
        }
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
    renderWorkOrderPackagingFinishScreen($ot, $outputRolls, $activeShiftSession, $helperOptions, $boxes, $pallets);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/finish$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }

    $result = $service->finishWorkOrderPackagingProduction(
        $workOrderId,
        (float)($_POST['packaging_produced_units'] ?? 0),
        (string)($_POST['packaging_finish_comments'] ?? ''),
        is_array($_POST['packaging_waste_weight'] ?? null) ? $_POST['packaging_waste_weight'] : [],
        is_array($_POST['packaging_waste_comment'] ?? null) ? $_POST['packaging_waste_comment'] : [],
        is_array($_POST['packaging_data'] ?? null) ? $_POST['packaging_data'] : [],
        $currentOperatorName
    );
    if (($result['ok'] ?? false) !== true) {
        $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
        $boxes = $service->listBoxesByWorkOrder($workOrderId);
        $pallets = $service->listPalletsByWorkOrder($workOrderId);
        $helperOptions = $service->listProductionPersonnelNames();
        $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
        if ($activeShiftSession === null) {
            $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
        }
        renderWorkOrderPackagingFinishScreen(
            $ot,
            $outputRolls,
            $activeShiftSession,
            $helperOptions,
            $boxes,
            $pallets,
            implode(' ', array_map('strval', array_values($result['errors'] ?? []))),
            true,
            [
                'packaging_produced_units' => (string)($_POST['packaging_produced_units'] ?? ''),
                'packaging_finish_comments' => (string)($_POST['packaging_finish_comments'] ?? ''),
                'packaging_waste_weight' => is_array($_POST['packaging_waste_weight'] ?? null) ? $_POST['packaging_waste_weight'] : [],
                'packaging_waste_comment' => is_array($_POST['packaging_waste_comment'] ?? null) ? $_POST['packaging_waste_comment'] : [],
                'packaging_data' => is_array($_POST['packaging_data'] ?? null) ? $_POST['packaging_data'] : [],
            ]
        );
        exit;
    }
    header('Location: /work-orders/' . $workOrderId . '/packaging/setup?packaging_finished=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/packaging/finish-order$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
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

    $approvalUsername = trim((string)($_POST['close_supervisor_username'] ?? ''));
    $approvalPassword = (string)($_POST['close_supervisor_password'] ?? '');
    $approvedUser = unibagFindAuthorizedUser($trzPdo, $approvalUsername, $approvalPassword, 'PRODUCTION', 1);
    if (!is_array($approvedUser)) {
        $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
        $boxes = $service->listBoxesByWorkOrder($workOrderId);
        $pallets = $service->listPalletsByWorkOrder($workOrderId);
        $helperOptions = $service->listProductionPersonnelNames();
        $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
        if ($activeShiftSession === null) {
            $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
        }
        renderWorkOrderPackagingFinishScreen(
            $ot,
            $outputRolls,
            $activeShiftSession,
            $helperOptions,
            $boxes,
            $pallets,
            'Usuario o clave invalidos para cerrar la OT.',
            true,
            [
                'packaging_produced_units' => (string)($_POST['packaging_produced_units'] ?? ''),
                'packaging_finish_comments' => (string)($_POST['packaging_finish_comments'] ?? ''),
                'packaging_waste_weight' => is_array($_POST['packaging_waste_weight'] ?? null) ? $_POST['packaging_waste_weight'] : [],
                'packaging_waste_comment' => is_array($_POST['packaging_waste_comment'] ?? null) ? $_POST['packaging_waste_comment'] : [],
                'packaging_data' => is_array($_POST['packaging_data'] ?? null) ? $_POST['packaging_data'] : [],
                'close_packaging_data' => is_array($_POST['close_packaging_data'] ?? null) ? $_POST['close_packaging_data'] : [],
                'close_total_units' => (string)($_POST['close_total_units'] ?? ''),
                'close_warehouse_id' => (string)($_POST['close_warehouse_id'] ?? ''),
                'close_supervisor_username' => $approvalUsername,
                'close_supervisor_observation' => (string)($_POST['close_supervisor_observation'] ?? ''),
                'close_closure_classification' => (string)($_POST['close_closure_classification'] ?? ''),
                'close_modal_open' => '1',
            ]
        );
        exit;
    }

    $closeResult = $service->closePackagingWorkOrder(
        $workOrderId,
        (int)($_POST['close_warehouse_id'] ?? 0),
        (string)($_POST['close_closure_classification'] ?? ''),
        (string)$approvedUser['username'],
        trim((string)($approvedUser['display_name'] ?? '')) !== '' ? (string)$approvedUser['display_name'] : (string)$approvedUser['username'],
        (string)($_POST['close_supervisor_observation'] ?? ''),
        (float)($_POST['close_total_units'] ?? 0),
        is_array($_POST['close_packaging_data'] ?? null) ? $_POST['close_packaging_data'] : [],
        $currentOperatorName
    );
    if (($closeResult['ok'] ?? false) !== true) {
        $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
        $boxes = $service->listBoxesByWorkOrder($workOrderId);
        $pallets = $service->listPalletsByWorkOrder($workOrderId);
        $helperOptions = $service->listProductionPersonnelNames();
        $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
        if ($activeShiftSession === null) {
            $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
        }
        renderWorkOrderPackagingFinishScreen(
            $ot,
            $outputRolls,
            $activeShiftSession,
            $helperOptions,
            $boxes,
            $pallets,
            implode(' ', array_map('strval', array_values($closeResult['errors'] ?? []))),
            true,
            [
                'packaging_produced_units' => (string)($_POST['packaging_produced_units'] ?? ''),
                'packaging_finish_comments' => (string)($_POST['packaging_finish_comments'] ?? ''),
                'packaging_waste_weight' => is_array($_POST['packaging_waste_weight'] ?? null) ? $_POST['packaging_waste_weight'] : [],
                'packaging_waste_comment' => is_array($_POST['packaging_waste_comment'] ?? null) ? $_POST['packaging_waste_comment'] : [],
                'packaging_data' => is_array($_POST['packaging_data'] ?? null) ? $_POST['packaging_data'] : [],
                'close_packaging_data' => is_array($_POST['close_packaging_data'] ?? null) ? $_POST['close_packaging_data'] : [],
                'close_total_units' => (string)($_POST['close_total_units'] ?? ''),
                'close_warehouse_id' => (string)($_POST['close_warehouse_id'] ?? ''),
                'close_supervisor_username' => $approvalUsername,
                'close_supervisor_observation' => (string)($_POST['close_supervisor_observation'] ?? ''),
                'close_closure_classification' => (string)($_POST['close_closure_classification'] ?? ''),
                'close_modal_open' => '1',
            ]
        );
        exit;
    }

    header('Location: /work-orders/' . $workOrderId . '/packaging?closed=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/production-event/start$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $returnScreen = strtoupper(trim((string)($_POST['return_screen'] ?? 'IN_PROGRESS')));
    if ($returnScreen === 'SETUP') {
        $redirectBase = '/work-orders/' . $workOrderId . '/setup';
    } elseif ($returnScreen === 'SEALING_SETUP') {
        $redirectBase = '/work-orders/' . $workOrderId . '/sealing/setup';
    } elseif ($returnScreen === 'PACKAGING_SETUP') {
        $redirectBase = '/work-orders/' . $workOrderId . '/packaging/setup';
    } else {
        $redirectBase = '/work-orders/' . $workOrderId . '/in-progress';
    }
    try {
        $service->startWorkOrderProcessEvent(
            $workOrderId,
            (string)($_POST['event_key'] ?? ''),
            (string)($_POST['comments'] ?? ''),
            $currentOperatorName
        );
        header('Location: ' . $redirectBase . '?process_started=1');
    } catch (Throwable $e) {
        header('Location: ' . $redirectBase . '?process_error=' . urlencode($e->getMessage()));
    }
    exit;
}

if (preg_match('#^/work-orders/(\d+)/production/start$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $result = $service->startWorkOrderProductionEvent(
        $workOrderId,
        $currentOperatorName,
        (string)($_POST['comments'] ?? '')
    );
    if (($result['ok'] ?? false) === true) {
        header('Location: /work-orders/' . $workOrderId . '/setup?production_started=1');
    } else {
        header('Location: /work-orders/' . $workOrderId . '/setup?process_error=' . urlencode(implode(' ', array_map('strval', array_values($result['errors'] ?? [])))));
    }
    exit;
}

if (preg_match('#^/work-orders/(\d+)/production-event/finish$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $returnScreen = strtoupper(trim((string)($_POST['return_screen'] ?? 'IN_PROGRESS')));
    if ($returnScreen === 'SETUP') {
        $redirectBase = '/work-orders/' . $workOrderId . '/setup';
    } elseif ($returnScreen === 'SEALING_SETUP') {
        $redirectBase = '/work-orders/' . $workOrderId . '/sealing/setup';
    } elseif ($returnScreen === 'PACKAGING_SETUP') {
        $redirectBase = '/work-orders/' . $workOrderId . '/packaging/setup';
    } else {
        $redirectBase = '/work-orders/' . $workOrderId . '/in-progress';
    }
    try {
        $service->finishWorkOrderProcessEvent(
            $workOrderId,
            (int)($_POST['start_event_id'] ?? 0),
            $currentOperatorName
        );
        header('Location: ' . $redirectBase . '?process_finished=1');
    } catch (Throwable $e) {
        header('Location: ' . $redirectBase . '?process_error=' . urlencode($e->getMessage()));
    }
    exit;
}

if (preg_match('#^/work-orders/(\d+)/request-materials$#', $path, $m) === 1 && $method === 'GET') {
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $chemicals = $service->listChemicals();
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
    $availableMaterialRolls = $service->listAvailableRollsForMaterialRequest();
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
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['material_requested']) && $_GET['material_requested'] === '1') {
        $flashMessage = 'Solicitud enviada correctamente a bodega.';
    }
    renderWorkOrderRequestMaterialsScreen($ot, $chemicals, $currentRoll, $chemicalInputs, $materialRequests, $availableMaterialRolls, $boxes, $activeShiftSession, $assignedCliches, $outputRoll, $flashMessage, $flashIsError);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/materials$#', $path, $m) === 1 && $method === 'GET') {
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
    $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
    $availableMaterialRolls = $service->listAvailableRollsForMaterialDelivery();
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
    $flashMessage = null;
    $flashIsError = false;
    if (isset($_GET['material_delivered']) && $_GET['material_delivered'] === '1') {
        $flashMessage = 'Tela ingresada correctamente a la OT.';
    } elseif (isset($_GET['material_attached']) && $_GET['material_attached'] === '1') {
        $flashMessage = 'Bobina ingresada correctamente a la máquina.';
    } elseif (isset($_GET['material_removed']) && $_GET['material_removed'] === '1') {
        $flashMessage = 'Entrada eliminada correctamente.';
    } elseif (isset($_GET['material_released']) && $_GET['material_released'] === '1') {
        $flashMessage = 'Salida de bobina registrada correctamente.';
    }
    renderWorkOrderMaterialUsageScreen($ot, $currentRoll, $chemicalInputs, $materialRequests, $availableMaterialRolls, $boxes, $activeShiftSession, $assignedCliches, $outputRoll, $flashMessage, $flashIsError);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/materials/attach$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $result = $service->attachRequestedRollToWorkOrder(
        (int)($_POST['request_id'] ?? 0),
        (int)($_POST['roll_id'] ?? 0),
        (float)($_POST['process_weight_kg'] ?? 0),
        $currentOperatorName
    );
    if (($result['ok'] ?? false) === true) {
        header('Location: /work-orders/' . $workOrderId . '/materials?material_attached=1');
        exit;
    }

    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        header('Location: /work-orders');
        exit;
    }
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
    $availableMaterialRolls = $service->listAvailableRollsForMaterialDelivery();
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
    $firstError = (string)reset($result['errors']);
    renderWorkOrderMaterialUsageScreen(
        $ot,
        $currentRoll,
        $chemicalInputs,
        $materialRequests,
        $availableMaterialRolls,
        $boxes,
        $activeShiftSession,
        $assignedCliches,
        $outputRoll,
        $firstError !== '' ? $firstError : 'No se pudo ingresar la bobina a la máquina.',
        true
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/materials/release$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $releaseWasteLabels = [
        'approval' => 'Ajuste / Aprobación',
        'printing' => 'Impresión',
        'process' => 'Merma Proceso',
        'other' => 'Otros',
    ];
    $releaseWasteWeightPost = is_array($_POST['release_waste_detail_weight'] ?? null) ? $_POST['release_waste_detail_weight'] : [];
    $releaseWasteCommentPost = is_array($_POST['release_waste_detail_comment'] ?? null) ? $_POST['release_waste_detail_comment'] : [];
    $releaseWasteDetails = [];
    foreach ($releaseWasteLabels as $releaseWasteKey => $releaseWasteLabel) {
        $releaseWasteDetails[$releaseWasteKey] = [
            'label' => $releaseWasteLabel,
            'weight_kg' => (float)($releaseWasteWeightPost[$releaseWasteKey] ?? 0),
            'comment' => (string)($releaseWasteCommentPost[$releaseWasteKey] ?? ''),
        ];
    }
    $result = $service->releaseCurrentRollFromWorkOrder(
        $workOrderId,
        (float)($_POST['final_weight_kg'] ?? 0),
        (float)($_POST['waste_kg'] ?? 0),
        $currentOperatorName,
        $releaseWasteDetails,
        (int)($_POST['roll_id'] ?? 0),
        (int)($_POST['request_id'] ?? 0),
        isset($_POST['final_meters']) && $_POST['final_meters'] !== '' ? (float)$_POST['final_meters'] : null
    );
    if (($result['ok'] ?? false) === true) {
        header('Location: /work-orders/' . $workOrderId . '/materials?material_released=1');
        exit;
    }

    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        header('Location: /work-orders');
        exit;
    }
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
    $availableMaterialRolls = $service->listAvailableRollsForMaterialDelivery();
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
    $firstError = (string)reset($result['errors']);
    renderWorkOrderMaterialUsageScreen(
        $ot,
        $currentRoll,
        $chemicalInputs,
        $materialRequests,
        $availableMaterialRolls,
        $boxes,
        $activeShiftSession,
        $assignedCliches,
        $outputRoll,
        $firstError !== '' ? $firstError : 'No se pudo registrar la salida de la bobina.',
        true,
        [
            'final_weight_kg' => (string)($_POST['final_weight_kg'] ?? ''),
            'final_meters' => (string)($_POST['final_meters'] ?? ''),
            'waste_kg' => (string)($_POST['waste_kg'] ?? '0'),
            'release_waste_detail_weight' => $releaseWasteWeightPost,
            'release_waste_detail_comment' => $releaseWasteCommentPost,
        ]
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/materials/remove$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $result = $service->removeCurrentRequestedRollFromWorkOrder(
        $workOrderId,
        (int)($_POST['request_id'] ?? 0),
        $currentOperatorName,
        (int)($_POST['roll_id'] ?? 0)
    );
    if (($result['ok'] ?? false) === true) {
        header('Location: /work-orders/' . $workOrderId . '/materials?material_removed=1');
        exit;
    }

    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        header('Location: /work-orders');
        exit;
    }
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
    $availableMaterialRolls = $service->listAvailableRollsForMaterialDelivery();
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
    $firstError = (string)reset($result['errors']);
    renderWorkOrderMaterialUsageScreen(
        $ot,
        $currentRoll,
        $chemicalInputs,
        $materialRequests,
        $availableMaterialRolls,
        $boxes,
        $activeShiftSession,
        $assignedCliches,
        $outputRoll,
        $firstError !== '' ? $firstError : 'No se pudo eliminar la entrada de la bobina.',
        true
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/material-request$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $returnContext = strtoupper(trim((string)($_POST['return_context'] ?? '')));
    $requestFilter = strtoupper(trim((string)($_POST['request_filter'] ?? ($_POST['request_type'] ?? 'ROLL'))));
    if (!in_array($requestFilter, ['ROLL', 'CHEMICAL', 'OTHER'], true)) {
        $requestFilter = 'ROLL';
    }
    $result = $service->createMaterialRequest(
        $workOrderId,
        (string)($_POST['request_type'] ?? 'ROLL'),
        (string)($_POST['requested_group_key'] ?? ''),
        isset($_POST['chemical_id']) && (int)$_POST['chemical_id'] > 0 ? (int)$_POST['chemical_id'] : null,
        (string)($_POST['requested_item'] ?? ''),
        (float)($_POST['requested_qty'] ?? 0),
        (float)($_POST['requested_meters'] ?? 0),
        (string)($_POST['requested_unit'] ?? ''),
        (string)($_POST['request_notes'] ?? ''),
        $currentOperatorName
    );
    if (($result['ok'] ?? false) === true) {
        $redirectPath = $returnContext === 'REQUEST_WINDOW'
            ? withQuery('/work-orders/' . $workOrderId . '/request-materials', ['material_requested' => '1', 'request_filter' => $requestFilter])
            : '/work-orders/' . $workOrderId . '/start?material_requested=1';
        header('Location: ' . $redirectPath);
        exit;
    }

    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        header('Location: /work-orders');
        exit;
    }

    $formState = $_POST;
    $formState['request_filter'] = $requestFilter;
    if ((string)($_POST['request_type'] ?? '') === 'CHEMICAL') {
        $formState['chemical_request_id'] = (string)($_POST['chemical_id'] ?? '');
        $formState['chemical_requested_qty'] = (string)($_POST['requested_qty'] ?? '');
        $formState['chemical_request_notes'] = (string)($_POST['request_notes'] ?? '');
    } elseif ((string)($_POST['request_type'] ?? '') === 'OTHER') {
        $formState['other_requested_qty'] = (string)($_POST['requested_qty'] ?? '');
        $formState['other_requested_unit'] = (string)($_POST['requested_unit'] ?? '');
        $formState['other_request_notes'] = (string)($_POST['request_notes'] ?? '');
    }

    $chemicals = $service->listChemicals();
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
    $lastStart = $service->getLastWorkOrderStart($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
    $availableMaterialRolls = $service->listAvailableRollsForMaterialRequest();
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    $assignedCliches = $service->listClichesAssignedToWorkOrder($workOrderId);
    $outputRoll = null;
    if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
        $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
    }
    $firstError = (string)reset($result['errors']);
    if ($returnContext === 'REQUEST_WINDOW') {
        renderWorkOrderRequestMaterialsScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $chemicalInputs,
            $materialRequests,
            $availableMaterialRolls,
            $boxes,
            $activeShiftSession,
            $assignedCliches,
            $outputRoll,
            $firstError !== '' ? $firstError : 'No se pudo enviar la solicitud a bodega.',
            true,
            $formState
        );
        exit;
    }

    $wastes = $service->listProductionWastesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $cutWarehouses = $service->listWarehousesForCut();
    renderWorkOrderStartScreen(
        $ot,
        $chemicals,
        $currentRoll,
        $rollHistory,
        $chemicalInputs,
        $lastStart,
        $lastFinish,
        $currentOperatorName,
        $firstError !== '' ? $firstError : 'No se pudo enviar la solicitud a bodega.',
        true,
        $formState,
        $materialRequests,
        $wastes,
        $boxes,
        $pallets,
        $outputRoll,
        $availableMaterialRolls,
        $cutWarehouses
    );
    exit;
}

if (preg_match('#^/work-orders/(\d+)/material-delivery$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $returnContext = strtoupper(trim((string)($_POST['return_context'] ?? '')));
    $rollId = isset($_POST['roll_id']) && (int)$_POST['roll_id'] > 0 ? (int)$_POST['roll_id'] : null;
    $result = $service->deliverMaterialRequest((int)($_POST['request_id'] ?? 0), $rollId, $currentOperatorName);
    if (($result['ok'] ?? false) === true) {
        $redirectPath = $returnContext === 'USAGE_SCREEN'
            ? '/work-orders/' . $workOrderId . '/materials?material_delivered=1'
            : '/work-orders/' . $workOrderId . '/start';
        header('Location: ' . $redirectPath);
        exit;
    }

    if ($returnContext === 'USAGE_SCREEN') {
        $ot = $service->getWorkOrder($workOrderId);
        if ($ot === null) {
            header('Location: /work-orders');
            exit;
        }
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        $materialRequests = $service->listMaterialRequestsByWorkOrder($workOrderId);
        $availableMaterialRolls = $service->listAvailableRollsForMaterialDelivery();
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
        $firstError = (string)reset($result['errors']);
        renderWorkOrderMaterialUsageScreen(
            $ot,
            $currentRoll,
            $chemicalInputs,
            $materialRequests,
            $availableMaterialRolls,
            $boxes,
            $activeShiftSession,
            $assignedCliches,
            $outputRoll,
            $firstError !== '' ? $firstError : 'No se pudo ingresar la tela seleccionada.',
            true
        );
        exit;
    }

    header('Location: /work-orders/' . $workOrderId . '/start');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/waste$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
    $service->createProductionWaste(
        $workOrderId,
        $currentRoll !== null ? (int)$currentRoll['id'] : null,
        'PRODUCTION',
        (string)($_POST['waste_reason'] ?? ''),
        (float)($_POST['waste_weight_kg'] ?? 0),
        $currentOperatorName
    );
    header('Location: /work-orders/' . $workOrderId . '/start');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/traceability$#', $path, $m) === 1 && $method === 'GET') {
    if (!canAccessWorkOrderTraceability()) {
        http_response_code(403);
        render('Acceso restringido', '<div class="card"><div style="font-size:18px;font-weight:800;margin-bottom:8px">Trazabilidad solo disponible en ERP</div><div class="muted">Los operadores no necesitan acceder a esta vista. La consulta de trazabilidad se realiza desde ERP.</div><div style="margin-top:12px"><a class="btn secondary" href="/work-orders">Volver</a></div></div>');
        exit;
    }

    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }

    $events = $service->listWorkOrderTraceability($workOrderId);
    $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
    $outputRolls = $service->listOutputRollsByWorkOrder($workOrderId);
    $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 50);
    $boxes = $service->listBoxesByWorkOrder($workOrderId);
    $pallets = $service->listPalletsByWorkOrder($workOrderId);
    $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
    $inputRolls = [];
    foreach ($rollHistory as $rollEvent) {
        $rollId = (int)($rollEvent['roll_id'] ?? 0);
        if ($rollId > 0 && !isset($inputRolls[$rollId])) {
            $inputRolls[$rollId] = $rollEvent;
        }
    }
    $traceabilityNavParams = inventoryNavigationContext() ? inventoryNavigationParams() : [];
    $traceabilityBackUrl = inventoryNavigationContext() ? inventoryReturnUrl() : '/work-orders';

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Trazabilidad OT ' . h((string)$ot['ot_code']) . '</div>
          <div class="muted">Seguimiento completo de bobinas, tintas y eventos de la orden de trabajo.</div>
        </div>
        <div class="row">
          <a class="btn secondary" href="' . h(withQuery('/work-orders/' . (int)$ot['id'] . '/start', $traceabilityNavParams)) . '">Volver a OT</a>
          <a class="btn secondary" href="' . h($traceabilityBackUrl) . '">' . h(inventoryNavigationContext() ? 'Volver a inventario' : 'Volver a listado') . '</a>
        </div>
      </div>';

    $body .= '<div class="card" style="margin-bottom:12px">
        <div class="row">
          <div style="flex:1;min-width:220px"><div class="muted">OT</div><div style="font-weight:800">' . h((string)$ot['ot_code']) . '</div></div>
          <div style="flex:1;min-width:260px"><div class="muted">SKU final</div><div style="font-weight:800">' . h((string)$ot['sku_final']) . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Estado</div><div style="font-weight:800">' . h(workOrderStatusLabel((string)$ot['status'])) . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Cajas cierre</div><div style="font-weight:800">' . h((string)($lastFinish['box_qty'] ?? '-')) . '</div></div>
        </div>
      </div>';

    $body .= '<div class="card" style="margin-bottom:12px">
        <div style="font-weight:800;margin-bottom:8px">Cadena final OT</div>
        <div class="muted" style="margin-bottom:8px">Confirmación del encadenamiento OT -> bobina entrada -> bobina salida -> corte -> cajas -> pallets.</div>
        <div class="table-wrap"><table class="trace-table"><thead><tr><th>Bobina entrada</th><th>Bobina salida</th><th>Estado corte</th><th>Cajas</th><th>Pallets</th><th></th></tr></thead><tbody>';
    foreach ($outputRolls as $outputRoll) {
        $parentRollId = (int)($outputRoll['parent_roll_id'] ?? 0);
        $isCut = strtoupper((string)($outputRoll['process_stage'] ?? '')) === 'CUT' || strtoupper((string)($outputRoll['status'] ?? '')) === 'CONSUMED';
        $body .= '<tr>';
        if ($parentRollId > 0) {
            $body .= '<td><a href="' . h(withQuery('/rolls/' . $parentRollId, $traceabilityNavParams)) . '">' . h((string)($outputRoll['parent_roll_code'] ?? ('#' . $parentRollId))) . '</a></td>';
        } else {
            $body .= '<td>-</td>';
        }
        $body .= '<td><a href="' . h(withQuery('/rolls/' . (int)$outputRoll['id'], $traceabilityNavParams)) . '">' . h((string)$outputRoll['roll_code']) . '</a></td>';
        $body .= '<td>' . h($isCut ? 'Cortada' : 'Pendiente') . '</td>';
        $body .= '<td>' . h((string)($outputRoll['box_count'] ?? '0')) . '</td>';
        $body .= '<td>' . h((string)($outputRoll['pallet_count'] ?? '0')) . '</td>';
        $body .= '<td><a class="btn secondary" href="' . h(withQuery('/rolls/' . (int)$outputRoll['id'], $traceabilityNavParams)) . '">Ver bobina</a></td>';
        $body .= '</tr>';
    }
    if ($outputRolls === []) {
        foreach ($inputRolls as $inputRoll) {
            $body .= '<tr>';
            $body .= '<td><a href="' . h(withQuery('/rolls/' . (int)$inputRoll['roll_id'], $traceabilityNavParams)) . '">' . h((string)$inputRoll['roll_code']) . '</a></td>';
            $body .= '<td class="muted">Pendiente de generar</td>';
            $body .= '<td>Pendiente</td>';
            $body .= '<td>0</td>';
            $body .= '<td>0</td>';
            $body .= '<td><a class="btn secondary" href="' . h(withQuery('/rolls/' . (int)$inputRoll['roll_id'], $traceabilityNavParams)) . '">Ver bobina</a></td>';
            $body .= '</tr>';
        }
    }
    if ($outputRolls === [] && $inputRolls === []) {
        $body .= '<tr><td colspan="6" class="muted">Aún no hay bobinas ligadas a esta OT.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';

    $body .= '<div class="card" style="margin-bottom:12px">
        <div style="font-weight:800;margin-bottom:8px">Eventos de trazabilidad OT</div>
        <div class="table-wrap"><table class="trace-table"><thead><tr><th>Fecha</th><th>Evento</th><th>Operador</th><th>Detalle</th></tr></thead><tbody>';
    foreach ($events as $event) {
        $body .= '<tr>';
        $body .= '<td>' . h((string)$event['created_at']) . '</td>';
        $body .= '<td>' . h((string)($event['type_label'] ?? $event['type'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($event['operator_name'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($event['detail'] ?? '-')) . '</td>';
        $body .= '</tr>';
    }
    if ($events === []) {
        $body .= '<tr><td colspan="4" class="muted">Sin eventos de trazabilidad para esta OT.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';

    $body .= '<div class="trace-stack">
      <div class="card">
        <div style="font-weight:800;margin-bottom:8px">Trazabilidad de bobinas</div>';
    if ($rollHistory !== []) {
        $rollGroups = [];
        foreach ($rollHistory as $roll) {
            $rollId = (int)($roll['roll_id'] ?? 0);
            if (!isset($rollGroups[$rollId])) {
                $rollGroups[$rollId] = [
                    'roll' => $roll,
                    'events' => [],
                ];
            }
            $rollGroups[$rollId]['events'][] = $roll;
        }

        foreach ($rollGroups as $rollGroup) {
            $roll = $rollGroup['roll'];
            $originParts = [];
            if ((string)($roll['container_code'] ?? '') !== '') {
                $originParts[] = 'Contenedor ' . (string)$roll['container_code'];
            }
            if ((string)($roll['po_code'] ?? '') !== '') {
                $originParts[] = (string)$roll['po_code'];
            }
            if ((string)($roll['supplier_name'] ?? '') !== '') {
                $originParts[] = (string)$roll['supplier_name'];
            }
            $origin = $originParts !== []
                ? implode(' · ', $originParts)
                : ((string)($roll['warehouse_code'] ?? '') !== '' ? 'Bodega ' . (string)$roll['warehouse_code'] : '-');

            $body .= '<div class="trace-roll-card">';
            $body .= '<div class="trace-roll-header">';
            $body .= '<div>';
            $body .= '<div class="trace-roll-title">' . h((string)$roll['roll_code']) . '</div>';
            $body .= '<div class="muted trace-roll-subtitle">Código SKU ' . h((string)$roll['sku_code']) . ' · ID ' . (int)$roll['roll_id'] . '</div>';
            $body .= '</div>';
            $body .= '<div><a class="trace-roll-link" href="' . h(withQuery('/rolls/' . (int)$roll['roll_id'], $traceabilityNavParams)) . '">Ver trazabilidad bobina</a></div>';
            $body .= '</div>';

            $body .= '<div class="trace-roll-meta">';
            $body .= '<div class="trace-roll-stat"><div class="muted">Origen</div><div class="value">' . h($origin) . '</div></div>';
            $body .= '<div class="trace-roll-stat"><div class="muted">Contenedor</div><div class="value">' . h(trim((string)($roll['container_code'] ?? '')) !== '' ? (string)$roll['container_code'] : '-') . '</div></div>';
            $body .= '<div class="trace-roll-stat"><div class="muted">Bodega actual</div><div class="value">' . h((string)($roll['warehouse_code'] ?? '-')) . '</div></div>';
            $body .= '<div class="trace-roll-stat"><div class="muted">Peso actual</div><div class="value">' . h((string)($roll['weight_kg'] ?? '-')) . ' Kg</div></div>';
            $body .= '</div>';

            $body .= '<div class="trace-roll-events">';
            foreach ($rollGroup['events'] as $eventRoll) {
                $payload = $eventRoll['payload_data'] ?? [];
                $action = (string)$eventRoll['type'] === 'WORK_ORDER_ROLL_ATTACHED' ? 'Ingreso a OT' : 'Salida de OT';
                $weight = (string)$eventRoll['type'] === 'WORK_ORDER_ROLL_ATTACHED'
                    ? (string)($payload['process_weight_kg'] ?? '-')
                    : (string)($payload['final_weight_kg'] ?? '-');
                $detail = (string)$eventRoll['type'] === 'WORK_ORDER_ROLL_RELEASED'
                    ? ('Motivo: ' . (string)($payload['reason'] ?? '-'))
                    : ('Operador: ' . (string)($payload['operator_name'] ?? '-'));

                $body .= '<div class="item">';
                $body .= '<div class="trace-roll-event-top"><div style="font-weight:700">' . h($action) . '</div><div class="muted">' . h((string)$eventRoll['created_at']) . '</div></div>';
                $body .= '<div class="trace-roll-event-line">Peso ' . h($weight) . ' Kg · Merma ' . h((string)($payload['waste_kg'] ?? '0')) . ' Kg · ' . h($detail) . '</div>';
                $body .= '</div>';
            }
            $body .= '</div>';
            $body .= '</div>';
        }
    } else {
        $body .= '<div class="muted">Sin trazabilidad de bobinas.</div>';
    }
    $body .= '</div>
      <div class="card">
        <div style="font-weight:800;margin-bottom:8px">Trazabilidad de tintas</div>
        <div class="table-wrap"><table class="trace-table"><thead><tr><th>Fecha</th><th>Tinta</th><th>Peso</th><th>Operador</th></tr></thead><tbody>';
    foreach ($chemicalInputs as $input) {
        $body .= '<tr>';
        $body .= '<td>' . h((string)$input['created_at']) . '</td>';
        $body .= '<td>' . h((string)$input['chemical_code']) . ' - ' . h((string)($input['chemical_name'] ?? '')) . '</td>';
        $body .= '<td>' . h((string)$input['weight_kg']) . ' Kg</td>';
        $body .= '<td>' . h((string)($input['operator_name'] ?? '-')) . '</td>';
        $body .= '</tr>';
    }
    if ($chemicalInputs === []) {
        $body .= '<tr><td colspan="4" class="muted">Sin trazabilidad de tintas.</td></tr>';
    }
    $body .= '</tbody></table></div>
      </div>
      <div class="card">
        <div style="font-weight:800;margin-bottom:8px">Cajas generadas</div>
        <div class="table-wrap"><table class="trace-table"><thead><tr><th>Caja</th><th>Bobina origen</th><th>Unidades</th><th>Destino</th><th>Bodega</th><th>Pallet</th><th>Operador</th></tr></thead><tbody>';
    foreach ($boxes as $box) {
        $destination = (string)($box['destination_mode'] ?? '') === 'CUSTOMER_ORDER'
            ? 'OC cliente ' . (string)($box['customer_order_ref'] ?? '-')
            : 'Inventario';
        $warehouseLabel = trim((string)($box['warehouse_code'] ?? ''));
        if ($warehouseLabel === '') {
            $warehouseLabel = '-';
        }
        $body .= '<tr>';
        $body .= '<td><a href="' . h(withQuery('/boxes/' . (int)$box['id'], $traceabilityNavParams)) . '">' . h((string)$box['box_code']) . '</a></td>';
        $body .= '<td>' . h((string)($box['source_roll_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($box['units_qty'] ?? '-')) . '</td>';
        $body .= '<td>' . h($destination) . '</td>';
        $body .= '<td>' . h($warehouseLabel) . '</td>';
        $body .= '<td>' . h((string)($box['pallet_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($box['operator_name'] ?? '-')) . '</td>';
        $body .= '</tr>';
    }
    if ($boxes === []) {
        $body .= '<tr><td colspan="7" class="muted">Sin cajas registradas para esta OT.</td></tr>';
    }
    $body .= '</tbody></table></div>
      </div>
      <div class="card">
        <div style="font-weight:800;margin-bottom:8px">Pallets generados</div>
        <div class="table-wrap"><table class="trace-table"><thead><tr><th>Pallet</th><th>Bobina origen</th><th>Cajas</th><th>Destino</th><th>Bodega</th><th>Operador</th></tr></thead><tbody>';
    foreach ($pallets as $pallet) {
        $destination = (string)($pallet['destination_mode'] ?? '') === 'CUSTOMER_ORDER'
            ? 'OC cliente ' . (string)($pallet['customer_order_ref'] ?? '-')
            : 'Inventario';
        $warehouseLabel = trim((string)($pallet['warehouse_code'] ?? ''));
        if ($warehouseLabel === '') {
            $warehouseLabel = '-';
        }
        $body .= '<tr>';
        $body .= '<td><a href="' . h(withQuery('/pallets/' . (int)$pallet['id'], $traceabilityNavParams)) . '">' . h((string)$pallet['pallet_code']) . '</a></td>';
        $body .= '<td>' . h((string)($pallet['source_roll_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($pallet['box_count'] ?? '-')) . '</td>';
        $body .= '<td>' . h($destination) . '</td>';
        $body .= '<td>' . h($warehouseLabel) . '</td>';
        $body .= '<td>' . h((string)($pallet['operator_name'] ?? '-')) . '</td>';
        $body .= '</tr>';
    }
    if ($pallets === []) {
        $body .= '<tr><td colspan="6" class="muted">Sin pallets registrados para esta OT.</td></tr>';
    }
    $body .= '</tbody></table></div>
      </div>
    </div>';

    render('Trazabilidad OT', $body);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/attach-roll$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $scanCode = trim((string)($_POST['scan_code'] ?? ''));
    $roll = $service->getRollByScanCode($scanCode);
    if ($roll === null) {
        $chemicals = $service->listChemicals();
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            'No se encontró la bobina escaneada.',
            true,
            [
                'scan_code' => $scanCode,
                'process_weight_kg' => (string)($_POST['process_weight_kg'] ?? ''),
                'process_waste_kg' => (string)($_POST['process_waste_kg'] ?? '0'),
            ]
        );
        exit;
    }
    $result = $service->attachRollToWorkOrder(
        $workOrderId,
        (int)$roll['id'],
        (float)($_POST['process_weight_kg'] ?? 0),
        (float)($_POST['process_waste_kg'] ?? 0),
        $currentOperatorName
    );
    if ($result['ok'] !== true) {
        $chemicals = $service->listChemicals();
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            implode(' ', array_map('strval', array_values($result['errors']))),
            true,
            [
                'scan_code' => $scanCode,
                'process_weight_kg' => (string)($_POST['process_weight_kg'] ?? ''),
                'process_waste_kg' => (string)($_POST['process_waste_kg'] ?? '0'),
            ]
        );
        exit;
    }
    header('Location: /work-orders/' . $workOrderId . '/start?roll_attached=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/chemical-input$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $chemicalId = (int)($_POST['chemical_id'] ?? 0);
    $chemicalWeight = (float)($_POST['chemical_weight_kg'] ?? 0);
    $result = $service->createChemicalInput($workOrderId, $chemicalId, $chemicalWeight, $currentOperatorName);
    if ($result['ok'] !== true) {
        $chemicals = $service->listChemicals();
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            implode(' ', array_map('strval', array_values($result['errors']))),
            true,
            [
                'chemical_id' => (string)$chemicalId,
                'chemical_weight_kg' => (string)($_POST['chemical_weight_kg'] ?? ''),
            ]
        );
        exit;
    }
    header('Location: /work-orders/' . $workOrderId . '/start?chemical_input=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/change-roll$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $nextScanCode = trim((string)($_POST['change_scan_code'] ?? ''));
    $nextRoll = $service->getRollByScanCode($nextScanCode);
    if ($nextRoll === null) {
        $chemicals = $service->listChemicals();
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            'No se encontró la nueva bobina escaneada.',
            true,
            [
                'change_scan_code' => $nextScanCode,
                'change_final_roll_weight_kg' => (string)($_POST['change_final_roll_weight_kg'] ?? ''),
                'change_waste_kg' => (string)($_POST['change_waste_kg'] ?? '0'),
                'change_output_roll_weight_kg' => (string)($_POST['change_output_roll_weight_kg'] ?? ''),
                'change_next_process_weight_kg' => (string)($_POST['change_next_process_weight_kg'] ?? ''),
                'change_next_waste_kg' => (string)($_POST['change_next_waste_kg'] ?? '0'),
            ]
        );
        exit;
    }
    $result = $service->changeRollInWorkOrder(
        $workOrderId,
        (int)$nextRoll['id'],
        (float)($_POST['change_final_roll_weight_kg'] ?? 0),
        (float)($_POST['change_waste_kg'] ?? 0),
        (float)($_POST['change_output_roll_weight_kg'] ?? 0),
        (float)($_POST['change_next_process_weight_kg'] ?? 0),
        (float)($_POST['change_next_waste_kg'] ?? 0),
        $currentOperatorName
    );
    if ($result['ok'] !== true) {
        $chemicals = $service->listChemicals();
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            implode(' ', array_map('strval', array_values($result['errors']))),
            true,
            [
                'change_scan_code' => $nextScanCode,
                'change_final_roll_weight_kg' => (string)($_POST['change_final_roll_weight_kg'] ?? ''),
                'change_waste_kg' => (string)($_POST['change_waste_kg'] ?? '0'),
                'change_output_roll_weight_kg' => (string)($_POST['change_output_roll_weight_kg'] ?? ''),
                'change_next_process_weight_kg' => (string)($_POST['change_next_process_weight_kg'] ?? ''),
                'change_next_waste_kg' => (string)($_POST['change_next_waste_kg'] ?? '0'),
            ]
        );
        exit;
    }
    $printed = '0';
    $labelRollId = (int)($result['output_roll_id'] ?? 0);
    if ($labelRollId > 0 && $printer->isEnabled()) {
        $roll = $service->getRoll($labelRollId);
        if (is_array($roll)) {
            $p = $printer->printRollLabel($roll);
            if (($p['ok'] ?? false) === true || !empty($p['client_queued'])) {
                $printed = '1';
            }
        }
    }
    header('Location: /work-orders/' . $workOrderId . '/start?roll_changed=1&printed=' . $printed);
    exit;
}

if (preg_match('#^/work-orders/(\d+)/start$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $result = $service->startWorkOrder($workOrderId, $currentOperatorName);
    if ($result['ok'] !== true) {
        $ot = $service->getWorkOrder($workOrderId);
        if ($ot === null) {
            http_response_code(404);
            render('No encontrado', '<div class="card">No existe la OT.</div>');
            exit;
        }
        $chemicals = $service->listChemicals();
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            implode(' ', array_map('strval', array_values($result['errors']))),
            true
        );
        exit;
    }
    header('Location: /work-orders/' . $workOrderId . '/start');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/finish$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }
    $result = $service->finishWorkOrder(
        $workOrderId,
        (float)($_POST['finish_final_roll_weight_kg'] ?? 0),
        (float)($_POST['finish_final_chemical_weight_kg'] ?? 0),
        (float)($_POST['finish_waste_kg'] ?? 0),
        (int)($_POST['finish_box_qty'] ?? 0),
        (float)($_POST['finish_output_roll_weight_kg'] ?? 0),
        $currentOperatorName,
        (float)($_POST['finish_production_meters'] ?? 0),
        (string)($_POST['finish_comments'] ?? '')
    );
    if ($result['ok'] !== true) {
        $chemicals = $service->listChemicals();
        $currentRoll = $service->getCurrentRollInWorkOrder($workOrderId);
        $rollHistory = $service->listWorkOrderRollHistory($workOrderId);
        $chemicalInputs = $service->listChemicalInputsByWorkOrder($workOrderId, 20);
        $lastStart = $service->getLastWorkOrderStart($workOrderId);
        $lastFinish = $service->getLastWorkOrderFinish($workOrderId);
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            implode(' ', array_map('strval', array_values($result['errors']))),
            true,
            [
                'show_finish_data' => '1',
                'finish_final_roll_weight_kg' => (string)($_POST['finish_final_roll_weight_kg'] ?? ''),
                'finish_final_chemical_weight_kg' => (string)($_POST['finish_final_chemical_weight_kg'] ?? ''),
                'finish_box_qty' => (string)($_POST['finish_box_qty'] ?? ''),
                'finish_output_roll_weight_kg' => (string)($_POST['finish_output_roll_weight_kg'] ?? ''),
                'finish_waste_kg' => (string)($_POST['finish_waste_kg'] ?? '0'),
                'finish_production_meters' => (string)($_POST['finish_production_meters'] ?? ''),
                'finish_comments' => (string)($_POST['finish_comments'] ?? ''),
                'finish_chemical_return_kg' => is_array($_POST['finish_chemical_return_kg'] ?? null) ? $_POST['finish_chemical_return_kg'] : [],
                'finish_waste_detail_weight' => is_array($_POST['finish_waste_detail_weight'] ?? null) ? $_POST['finish_waste_detail_weight'] : [],
                'finish_waste_detail_comment' => is_array($_POST['finish_waste_detail_comment'] ?? null) ? $_POST['finish_waste_detail_comment'] : [],
            ],
            [],
            [],
            [],
            [],
            null,
            [],
            array_values(array_filter($service->listWarehouses(), static fn(array $warehouse): bool => in_array((int)$warehouse['code'], [700, 1000], true)))
        );
        exit;
    }
    $printed = '0';
    $boxPrinted = '';
    $finishBoxQty = max(0, (int)($_POST['finish_box_qty'] ?? 0));
    $labelRollId = (int)($result['output_roll_id'] ?? 0);
    if ($labelRollId > 0 && $printer->isEnabled()) {
        $roll = $service->getRoll($labelRollId);
        if (is_array($roll)) {
            $p = $printer->printRollLabel($roll);
            if (($p['ok'] ?? false) === true || !empty($p['client_queued'])) {
                $printed = '1';
            }
        }
        if ($finishBoxQty > 0) {
            $box = $printer->printWorkOrderBoxLabel($ot, [
                'created_at' => date('Y-m-d H:i:s'),
                'box_qty' => $finishBoxQty,
                'operator_name' => $currentOperatorName,
            ]);
            $boxPrinted = (($box['ok'] ?? false) === true || !empty($box['client_queued'])) ? '1' : '0';
        }
    }
    $qs = '?printed=' . $printed;
    if ($boxPrinted !== '') {
        $qs .= '&box_printed=' . $boxPrinted;
    }
    if ($labelRollId > 0) {
        $qs .= '&label_roll_id=' . $labelRollId;
    }
    header('Location: /work-orders/' . $workOrderId . '/finish-approval' . $qs);
    exit;
}

    return false;
}
