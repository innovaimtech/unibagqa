<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Asignación de Clichés y Slots Anilox a la OT (src/Http/WorkOrders/AniloxClicheAssignModule.php)
// =============================================================================

/**
 * Construye la matriz de unidades (1..6) para configuración de Anilox en una OT.
 *
 * Prioridad de fuentes:
 * 1) $formSlots (lo que viene del formulario, para re-render con errores)
 * 2) $savedAssignments (config guardada previamente)
 * 3) Detección automática a partir de:
 *    - $colorSlots (colores detectados en la OT)
 *    - $chemicalInputs (tintas/pesajes asociados)
 *
 * Siempre devuelve 6 filas (unidad 1 a 6), completando con valores vacíos.
 *
 * ---
 *
 * Builds the 1..6 unit matrix for a Work Order Anilox configuration.
 *
 * Source priority:
 * 1) $formSlots (incoming form values, for re-render on errors)
 * 2) $savedAssignments (previously saved config)
 * 3) Auto-detection from:
 *    - $colorSlots (colors detected in the work order)
 *    - $chemicalInputs (related inks/weighings)
 *
 * It always returns 6 rows (units 1 to 6), padding with empty values.
 *
 * @param array<int,string> $colorSlots
 * @param array<int,array<string,mixed>> $chemicalInputs
 * @param array<int,array<string,mixed>> $savedAssignments
 * @param array<int,array<string,mixed>>|null $formSlots
 * @return array<int,array{unit_no:int,color_name:string,anilox_id:int}>
 */
function buildWorkOrderAniloxSlots(array $colorSlots, array $chemicalInputs, array $savedAssignments = [], ?array $formSlots = null): array
{
    $normalize = static function (array $slots): array {
        $normalized = [];
        foreach ($slots as $index => $slot) {
            $unitNo = isset($slot['unit_no']) ? (int)$slot['unit_no'] : ($index + 1);
            if ($unitNo < 1 || $unitNo > 6) {
                continue;
            }
            $normalized[$unitNo] = [
                'unit_no' => $unitNo,
                'color_name' => trim((string)($slot['color_name'] ?? '')),
                'anilox_id' => isset($slot['anilox_id']) ? max(0, (int)$slot['anilox_id']) : 0,
            ];
        }

        $result = [];
        for ($unit = 1; $unit <= 6; $unit++) {
            $result[] = $normalized[$unit] ?? [
                'unit_no' => $unit,
                'color_name' => '',
                'anilox_id' => 0,
            ];
        }

        return $result;
    };

    if (is_array($formSlots) && $formSlots !== []) {
        return $normalize($formSlots);
    }

    if ($savedAssignments !== []) {
        return $normalize($savedAssignments);
    }

    $detected = [];
    foreach ($colorSlots as $colorLabel) {
        $colorLabel = trim((string)$colorLabel);
        if ($colorLabel !== '' && $colorLabel !== '-' && !in_array($colorLabel, $detected, true)) {
            $detected[] = $colorLabel;
        }
    }
    foreach ($chemicalInputs as $input) {
        foreach ([
            trim((string)($input['chemical_name'] ?? '')),
            trim((string)($input['chemical_code'] ?? '')),
        ] as $candidate) {
            if ($candidate !== '' && !in_array($candidate, $detected, true)) {
                $detected[] = $candidate;
            }
        }
    }

    $detected = array_pad(array_slice($detected, 0, 6), 6, '');
    $result = [];
    for ($unit = 1; $unit <= 6; $unit++) {
        $result[] = [
            'unit_no' => $unit,
            'color_name' => (string)$detected[$unit - 1],
            'anilox_id' => 0,
        ];
    }

    return $result;
}

/**
 * Extrae colores sugeridos para el datalist de autocompletado en Anilox.
 *
 * Se alimenta de:
 * - colores detectados en la OT ($colorSlots)
 * - nombres/códigos de tintas en pesajes ($chemicalInputs)
 * - nombres de color ya cargados en slots de anilox ($aniloxSlots)
 *
 * Devuelve una lista única (sin duplicados) en orden de descubrimiento.
 *
 * ---
 *
 * Extracts suggested colors for the Anilox autocomplete datalist.
 *
 * Sources:
 * - detected colors in the work order ($colorSlots)
 * - ink names/codes from weighings ($chemicalInputs)
 * - already entered slot color names ($aniloxSlots)
 *
 * Returns a unique list (no duplicates) in discovery order.
 *
 * @param array<int,string> $colorSlots
 * @param array<int,array<string,mixed>> $chemicalInputs
 * @param array<int,array<string,mixed>> $aniloxSlots
 * @return array<int,string>
 */
function extractSuggestedAniloxColors(array $colorSlots, array $chemicalInputs, array $aniloxSlots): array
{
    $values = [];
    foreach ($colorSlots as $colorLabel) {
        $colorLabel = trim((string)$colorLabel);
        if ($colorLabel !== '' && $colorLabel !== '-' && !in_array($colorLabel, $values, true)) {
            $values[] = $colorLabel;
        }
    }
    foreach ($chemicalInputs as $input) {
        foreach ([
            trim((string)($input['chemical_name'] ?? '')),
            trim((string)($input['chemical_code'] ?? '')),
        ] as $candidate) {
            if ($candidate !== '' && !in_array($candidate, $values, true)) {
                $values[] = $candidate;
            }
        }
    }
    foreach ($aniloxSlots as $slot) {
        $candidate = trim((string)($slot['color_name'] ?? ''));
        if ($candidate !== '' && !in_array($candidate, $values, true)) {
            $values[] = $candidate;
        }
    }

    return $values;
}

/**
 * Renderiza la tarjeta de configuración de Anilox para una OT.
 *
 * - Muestra 6 unidades (slots) con color + selección de anilox.
 * - Incluye CSRF y botón de guardado.
 * - Incluye un <datalist> con colores sugeridos para autocompletar.
 *
 * ---
 *
 * Renders the Anilox configuration card for a Work Order.
 *
 * - Shows 6 units (slots) with color + anilox selection.
 * - Includes CSRF and a save button.
 * - Includes a <datalist> with suggested colors for autocomplete.
 *
 * @param array<int,array{unit_no:int,color_name:string,anilox_id:int}> $slots
 * @param array<int,array<string,mixed>> $aniloxCatalog
 * @param array<int,string> $suggestedColors
 */
function renderWorkOrderAniloxConfigCard(int $workOrderId, array $slots, array $aniloxCatalog, array $suggestedColors): string
{
    $datalistId = 'anilox-color-options-' . $workOrderId;
    $html = '<div class="legacy-sheet-card legacy-anilox-card" style="margin-bottom:12px">'
        . '<form method="post" action="/work-orders/' . $workOrderId . '/anilox-config">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<table class="legacy-sheet-table">'
        . '<thead><tr><th colspan="4">Configuración de Anilox</th></tr></thead>'
        . '<tbody data-anilox-table>';

    foreach ($slots as $slot) {
        $unitNo = (int)($slot['unit_no'] ?? 0);
        $selectedAniloxId = (int)($slot['anilox_id'] ?? 0);
        $html .= '<tr data-anilox-row>'
            . '<td class="legacy-label-cell legacy-anilox-unit-cell">'
            . '<span data-anilox-unit>' . $unitNo . '</span>'
            . '<input type="hidden" name="anilox_unit_no[]" value="' . $unitNo . '" data-anilox-unit-input>'
            . '</td>'
            . '<td class="legacy-value-cell">'
            . '<input class="legacy-anilox-input" type="text" name="anilox_color_name[]" value="' . h((string)($slot['color_name'] ?? '')) . '" list="' . h($datalistId) . '" placeholder="Color o tinta">'
            . '</td>'
            . '<td class="legacy-value-cell">'
            . '<select class="legacy-anilox-select" name="anilox_id[]">'
            . '<option value="0">&lt; Por favor seleccione &gt;</option>';
        foreach ($aniloxCatalog as $anilox) {
            $optionId = (int)($anilox['id'] ?? 0);
            $selected = $optionId === $selectedAniloxId ? ' selected' : '';
            $label = trim((string)($anilox['display_label'] ?? ''));
            if ($label === '') {
                $label = trim((string)($anilox['code'] ?? '')) . ' - ' . trim((string)($anilox['name'] ?? ''));
            }
            $html .= '<option value="' . $optionId . '"' . $selected . '>' . h($label) . '</option>';
        }
        $html .= '</select>'
            . '</td>'
            . '<td class="legacy-value-cell legacy-anilox-actions">'
            . '<button class="anilox-move-btn" type="button" data-direction="up" aria-label="Subir unidad">&#8593;</button>'
            . '<button class="anilox-move-btn" type="button" data-direction="down" aria-label="Bajar unidad">&#8595;</button>'
            . '</td>'
            . '</tr>';
    }

    $html .= '<tr><td class="legacy-value-cell" colspan="4">'
        . '<div class="legacy-anilox-footer">'
        . '<div class="legacy-anilox-help">Usa las flechas para reordenar las unidades y luego guarda la configuración.</div>'
        . '<button class="btn" type="submit">Guardar configuración anilox</button>'
        . '</div>'
        . '</td></tr>'
        . '</tbody></table>';

    if ($suggestedColors !== []) {
        $html .= '<datalist id="' . h($datalistId) . '">';
        foreach ($suggestedColors as $colorOption) {
            $html .= '<option value="' . h($colorOption) . '"></option>';
        }
        $html .= '</datalist>';
    }

    $html .= '</form></div>';

    return $html;
}

/**
 * Renderiza una tarjeta de cabecera para la OT (tipo/máquina + ayudante + comentarios).
 *
 * Esta tarjeta se usa como punto de partida del alistamiento:
 * - Permite fijar ayudante y comentarios asociados al turno/OT.
 * - El botón “Iniciar alistamiento” se deshabilita si no hay turno activo.
 *
 * ---
 *
 * Renders a header card for the Work Order (type/machine + helper + comments).
 *
 * This card is used as the setup entry point:
 * - Allows selecting a helper and adding comments linked to the shift/WO.
 * - The “Start setup” button is disabled if there is no active shift session.
 */
function renderWorkOrderMachineHeaderCard(
    int $workOrderId,
    ?array $sessionForDisplay,
    string $machineTypeLabel,
    string $machineLabel,
    string $helperValue,
    string $commentValue,
    array $helperOptions
): string {
    $hasActiveSession = (int)($sessionForDisplay['id'] ?? 0) > 0;
    $resolvedType = trim($machineTypeLabel) !== '' ? $machineTypeLabel : '-';
    $resolvedMachine = trim($machineLabel) !== '' ? $machineLabel : '-';
    $resolvedHelper = trim($helperValue);
    $resolvedComment = trim($commentValue);
    $options = array_values(array_unique(array_filter(array_map(
        static fn($value): string => trim((string)$value),
        $helperOptions
    ), static fn(string $value): bool => $value !== '')));
    natcasesort($options);
    $options = array_values($options);

    $html = '<div class="legacy-sheet-card" style="margin-bottom:12px">'
        . '<form method="post" action="/work-orders/' . $workOrderId . '/machine-header">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<table class="legacy-sheet-table">'
        . '<tbody>';

    $html .= '<tr>'
        . '<td class="legacy-label-cell">Tipo</td>'
        . '<td class="legacy-value-cell" colspan="3">' . h($resolvedType) . '</td>'
        . '</tr>';
    $html .= '<tr>'
        . '<td class="legacy-label-cell">Máquina</td>'
        . '<td class="legacy-value-cell" colspan="3">' . h($resolvedMachine) . '</td>'
        . '</tr>';
    $html .= '<tr>'
        . '<td class="legacy-label-cell">Ayudante</td>'
        . '<td class="legacy-value-cell" colspan="3">'
        . '<select name="shift_helper_name" style="max-width:260px;min-height:30px;padding:4px 8px;font-size:12px;border-radius:4px">'
        . '<option value="">&lt; Por favor seleccione &gt;</option>';
    foreach ($options as $helperOption) {
        $selected = $helperOption === $resolvedHelper ? ' selected' : '';
        $html .= '<option value="' . h($helperOption) . '"' . $selected . '>' . h($helperOption) . '</option>';
    }
    if ($resolvedHelper !== '' && !in_array($resolvedHelper, $options, true)) {
        $html .= '<option value="' . h($resolvedHelper) . '" selected>' . h($resolvedHelper) . '</option>';
    }
    $html .= '</select></td></tr>';
    $html .= '<tr>'
        . '<td class="legacy-label-cell">Comentarios</td>'
        . '<td class="legacy-value-cell" colspan="3">'
        . '<textarea name="shift_comments" rows="2" style="width:100%;min-height:38px;resize:vertical;border:2px solid #5f6368;border-radius:4px;padding:8px 10px;background:#fff">' . h($resolvedComment) . '</textarea>'
        . '</td></tr>';

    $buttonStyle = 'width:100%;min-width:0;border:0;border-radius:0;padding:8px 14px';
    if (!$hasActiveSession) {
        $buttonStyle .= ';opacity:.6;cursor:not-allowed';
    }
    $html .= '<tr><td class="legacy-value-cell" colspan="4" style="padding:10px">'
        . '<button class="legacy-primary-btn" type="submit" style="' . h($buttonStyle) . '"'
        . ($hasActiveSession ? '' : ' disabled title="Inicia o asigna un turno para continuar"')
        . '>&#9654; Iniciar alistamiento</button>'
        . '</td></tr>';

    $html .= '</tbody></table></form></div>';

    return $html;
}


function handleAniloxClicheAssignRoutes(string $path, string $method, ReceptionService $service, string $currentOperatorName): bool
{
if (preg_match('#^/work-orders/(\d+)/anilox-config$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }

    $unitInputs = isset($_POST['anilox_unit_no']) && is_array($_POST['anilox_unit_no']) ? $_POST['anilox_unit_no'] : [];
    $colorInputs = isset($_POST['anilox_color_name']) && is_array($_POST['anilox_color_name']) ? $_POST['anilox_color_name'] : [];
    $aniloxInputs = isset($_POST['anilox_id']) && is_array($_POST['anilox_id']) ? $_POST['anilox_id'] : [];
    $slotCount = max(count($unitInputs), count($colorInputs), count($aniloxInputs));
    $aniloxSlots = [];
    for ($index = 0; $index < $slotCount; $index++) {
        $aniloxSlots[] = [
            'unit_no' => isset($unitInputs[$index]) ? (int)$unitInputs[$index] : ($index + 1),
            'color_name' => isset($colorInputs[$index]) ? trim((string)$colorInputs[$index]) : '',
            'anilox_id' => isset($aniloxInputs[$index]) ? (int)$aniloxInputs[$index] : 0,
        ];
    }

    $result = $service->saveWorkOrderAniloxAssignments($workOrderId, $aniloxSlots, $currentOperatorName);
    if (($result['ok'] ?? false) !== true) {
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
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            implode(' ', array_map('strval', array_values($result['errors'] ?? []))),
            true,
            ['anilox_slots' => $aniloxSlots],
            $materialRequests,
            $wastes,
            $boxes,
            $pallets,
            $outputRoll,
            $availableMaterialRolls,
            $cutWarehouses,
            $activeShiftSession,
            $assignedCliches,
            $clicheUsageHistory
        );
        exit;
    }

    header('Location: /work-orders/' . $workOrderId . '/setup?anilox_saved=1');
    exit;
}

if (preg_match('#^/work-orders/(\d+)/machine-header$#', $path, $m) === 1 && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $workOrderId = (int)$m[1];
    $ot = $service->getWorkOrder($workOrderId);
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }

    $activeShiftSession = $service->getActiveShiftSessionByWorkOrder($workOrderId);
    if ($activeShiftSession === null) {
        $activeShiftSession = $service->getActiveShiftSessionByOperator($currentOperatorName);
    }
    if ($activeShiftSession === null || (int)($activeShiftSession['id'] ?? 0) <= 0) {
        header('Location: /work-orders/' . $workOrderId . '/start');
        exit;
    }

    $helperName = trim((string)($_POST['shift_helper_name'] ?? ''));
    $comments = trim((string)($_POST['shift_comments'] ?? ''));
    $result = $service->updateShiftSessionHeader((int)$activeShiftSession['id'], $helperName, $comments);
    if (($result['ok'] ?? false) !== true) {
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
        $outputRoll = null;
        if ((int)($lastFinish['output_roll_id'] ?? 0) > 0) {
            $outputRoll = $service->getRoll((int)$lastFinish['output_roll_id']);
        }
        renderWorkOrderStartScreen(
            $ot,
            $chemicals,
            $currentRoll,
            $rollHistory,
            $chemicalInputs,
            $lastStart,
            $lastFinish,
            $currentOperatorName,
            implode(' ', array_map('strval', array_values($result['errors'] ?? []))),
            true,
            [
                'shift_helper_name' => $helperName,
                'shift_comments' => $comments,
            ],
            $materialRequests,
            $wastes,
            $boxes,
            $pallets,
            $outputRoll,
            $availableMaterialRolls,
            $cutWarehouses,
            $activeShiftSession,
            $assignedCliches,
            $clicheUsageHistory
        );
        exit;
    }

    header('Location: /work-orders/' . $workOrderId . '/setup?machine_header_saved=1');
    exit;
}

if ($path === '/cliches' && $method === 'GET') {
    $search = trim((string)($_GET['search'] ?? ''));
    $location = trim((string)($_GET['location'] ?? ''));
    $status = strtoupper(trim((string)($_GET['status'] ?? 'ALL')));
    if (!in_array($status, ['ALL', 'AVAILABLE', 'IN_USE', 'MAINTENANCE', 'INACTIVE'], true)) {
        $status = 'ALL';
    }
    $selectedWorkOrderId = isset($_GET['work_order_id']) ? (int)$_GET['work_order_id'] : 0;
    $selectedWorkOrder = $selectedWorkOrderId > 0 ? $service->getWorkOrder($selectedWorkOrderId) : null;
    $cliches = $service->listCliches($search !== '' ? $search : null, $location !== '' ? $location : null, $status !== 'ALL' ? $status : null);
    $message = trim((string)($_GET['msg'] ?? ''));
    $error = trim((string)($_GET['error'] ?? ''));

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Clisés</div>
          <div class="muted">Inventario digital, retiro a OT y devolución con trazabilidad histórica.</div>
        </div>
        <a class="btn secondary" href="/stock">Volver a inventario</a>
      </div>';
    if ($message !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($message) . '</div>';
    }
    if ($error !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($error) . '</div>';
    }
    if ($selectedWorkOrder !== null) {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:6px">Contexto OT</div>
          <div class="row">
            <div style="flex:1;min-width:220px"><div class="muted">OT</div><div style="font-weight:800"><a href="/work-orders/' . (int)$selectedWorkOrder['id'] . '/start">' . h((string)$selectedWorkOrder['ot_code']) . '</a></div></div>
            <div style="flex:1;min-width:220px"><div class="muted">SKU final</div><div style="font-weight:800">' . h((string)($selectedWorkOrder['sku_final'] ?? '-')) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">Estado</div><div style="font-weight:800">' . h(workOrderStatusLabel((string)($selectedWorkOrder['status'] ?? ''))) . '</div></div>
          </div>
        </div>';
    }

    $body .= '<div class="card" style="margin-bottom:12px">
      <div class="row" style="justify-content:space-between;align-items:end;margin-bottom:8px">
        <div style="font-weight:800">Buscar clisés</div>
        <form method="get" action="/cliches">
          <input type="hidden" name="work_order_id" value="' . ($selectedWorkOrderId > 0 ? (int)$selectedWorkOrderId : '') . '">
          <div class="row nowrap">
            <div style="min-width:220px"><label>Código o descripción</label><input type="text" name="search" value="' . h($search) . '"></div>
            <div style="min-width:180px"><label>Ubicación</label><input type="text" name="location" value="' . h($location) . '"></div>
            <div style="min-width:180px"><label>Estado</label><select name="status">
              <option value="ALL"' . ($status === 'ALL' ? ' selected' : '') . '>Todos</option>
              <option value="AVAILABLE"' . ($status === 'AVAILABLE' ? ' selected' : '') . '>Disponibles</option>
              <option value="IN_USE"' . ($status === 'IN_USE' ? ' selected' : '') . '>En uso</option>
              <option value="MAINTENANCE"' . ($status === 'MAINTENANCE' ? ' selected' : '') . '>Mantención</option>
              <option value="INACTIVE"' . ($status === 'INACTIVE' ? ' selected' : '') . '>Inactivos</option>
            </select></div>
            <div style="min-width:120px;align-self:end"><button class="btn secondary" type="submit">Buscar</button></div>
          </div>
        </form>
      </div>
    </div>';

    $body .= '<div class="card" style="margin-bottom:12px">
      <div style="font-weight:800;margin-bottom:8px">Alta de clisé</div>
      <form method="post" action="/cliches">
        <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
        <input type="hidden" name="work_order_id" value="' . ($selectedWorkOrderId > 0 ? (int)$selectedWorkOrderId : '') . '">
        <div class="row">
          <div style="flex:1;min-width:200px"><label>Código</label><input type="text" name="code" required></div>
          <div style="flex:2;min-width:260px"><label>Descripción</label><input type="text" name="description" required></div>
        </div>
        <div class="row" style="margin-top:10px">
          <div style="flex:1;min-width:200px"><label>Ubicación física</label><input type="text" name="location_code" placeholder="Ej: RACK-C1" required></div>
          <div style="flex:2;min-width:260px"><label>Detalle ubicación</label><input type="text" name="location_detail" placeholder="Ej: Estante superior, caja azul"></div>
        </div>
        <div style="margin-top:10px"><label>Observación</label><input type="text" name="notes" placeholder="Comentario inicial del activo"></div>
        <div style="margin-top:12px"><button class="btn" type="submit">Guardar clisé</button></div>
      </form>
    </div>';

    $body .= '<div class="card">
      <div style="font-weight:800;margin-bottom:8px">Inventario de clisés</div>
      <div class="table-wrap"><table class="trace-table"><thead><tr><th>Código</th><th>Descripción</th><th>Ubicación</th><th>Estado</th><th>OT</th><th></th></tr></thead><tbody>';
    foreach ($cliches as $cliche) {
        $detailUrl = '/cliches/' . (int)$cliche['id'] . ($selectedWorkOrderId > 0 ? '?work_order_id=' . $selectedWorkOrderId : '');
        $locationLabel = trim((string)($cliche['location_code'] ?? ''));
        $locationDetail = trim((string)($cliche['location_detail'] ?? ''));
        if ($locationDetail !== '') {
            $locationLabel .= ' · ' . $locationDetail;
        }
        $body .= '<tr>';
        $body .= '<td>' . h((string)$cliche['code']) . '</td>';
        $body .= '<td>' . h((string)$cliche['description']) . '</td>';
        $body .= '<td>' . h($locationLabel !== '' ? $locationLabel : '-') . '</td>';
        $body .= '<td>' . h(clicheStatusLabel((string)$cliche['status'])) . '</td>';
        $body .= '<td>' . h((string)($cliche['ot_code'] ?? '-')) . '</td>';
        $body .= '<td><a class="btn secondary" href="' . h($detailUrl) . '">Ver</a></td>';
        $body .= '</tr>';
    }
    if ($cliches === []) {
        $body .= '<tr><td colspan="6" class="muted">No hay clisés para los filtros seleccionados.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    render('Clisés', $body);
    exit;
}

if ($path === '/cliches' && $method === 'POST') {
    requireCsrf();
    $selectedWorkOrderId = (int)($_POST['work_order_id'] ?? 0);
    $result = $service->createCliche(
        (string)($_POST['code'] ?? ''),
        (string)($_POST['description'] ?? ''),
        (string)($_POST['location_code'] ?? ''),
        (string)($_POST['location_detail'] ?? ''),
        (string)($_POST['notes'] ?? ''),
        $currentOperatorName
    );
    if (($result['ok'] ?? false) === true) {
        $redirect = '/cliches/' . (int)$result['cliche_id'] . '?msg=' . urlencode('Clisé registrado correctamente.');
        if ($selectedWorkOrderId > 0) {
            $redirect .= '&work_order_id=' . $selectedWorkOrderId;
        }
        header('Location: ' . $redirect);
        exit;
    }
    $query = http_build_query([
        'error' => (string)reset($result['errors']),
        'work_order_id' => $selectedWorkOrderId > 0 ? (string)$selectedWorkOrderId : '',
    ]);
    header('Location: /cliches' . ($query !== '' ? '?' . $query : ''));
    exit;
}

if (preg_match('#^/cliches/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $clicheId = (int)$m[1];
    $cliche = $service->getCliche($clicheId);
    if ($cliche === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">El clisé no existe.</div>');
        exit;
    }
    $selectedWorkOrderId = isset($_GET['work_order_id']) ? (int)$_GET['work_order_id'] : 0;
    $message = trim((string)($_GET['msg'] ?? ''));
    $error = trim((string)($_GET['error'] ?? ''));
    $workOrders = $service->listWorkOrdersForClicheAssignment();
    $usageLogs = $service->listClicheUsageLogsByCliche($clicheId);
    $currentWorkOrder = (int)($cliche['current_work_order_id'] ?? 0) > 0 ? $service->getWorkOrder((int)$cliche['current_work_order_id']) : null;

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Clisé ' . h((string)$cliche['code']) . '</div>
          <div class="muted">Control de ubicación, retiro a OT y devolución.</div>
        </div>
        <div class="row"><a class="btn secondary" href="/cliches' . ($selectedWorkOrderId > 0 ? '?work_order_id=' . $selectedWorkOrderId : '') . '">Volver</a></div>
      </div>';
    if ($message !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($message) . '</div>';
    }
    if ($error !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($error) . '</div>';
    }

    $locationLabel = trim((string)($cliche['location_code'] ?? ''));
    $locationDetail = trim((string)($cliche['location_detail'] ?? ''));
    if ($locationDetail !== '') {
        $locationLabel .= ' · ' . $locationDetail;
    }
    $body .= '<div class="card" style="margin-bottom:12px"><div class="row">
      <div style="flex:1;min-width:180px"><div class="muted">Código</div><div style="font-weight:800">' . h((string)$cliche['code']) . '</div></div>
      <div style="flex:2;min-width:240px"><div class="muted">Descripción</div><div style="font-weight:800">' . h((string)$cliche['description']) . '</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Estado</div><div style="font-weight:800">' . h(clicheStatusLabel((string)$cliche['status'])) . '</div></div>
      <div style="flex:1;min-width:220px"><div class="muted">Ubicación actual</div><div style="font-weight:800">' . h($locationLabel !== '' ? $locationLabel : '-') . '</div></div>
    </div>
    <div class="row" style="margin-top:10px">
      <div style="flex:1;min-width:180px"><div class="muted">OT actual</div><div style="font-weight:800">' . ((int)($cliche['current_work_order_id'] ?? 0) > 0 ? '<a href="/work-orders/' . (int)$cliche['current_work_order_id'] . '/start">' . h((string)($cliche['ot_code'] ?? ('OT #' . (int)$cliche['current_work_order_id']))) . '</a>' : '-') . '</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Asignado por</div><div style="font-weight:800">' . h((string)($cliche['current_operator_name'] ?? '-')) . '</div></div>
      <div style="flex:1;min-width:220px"><div class="muted">Fecha uso actual</div><div style="font-weight:800">' . h((string)($cliche['current_assigned_at'] ?? '-')) . '</div></div>
      <div style="flex:2;min-width:240px"><div class="muted">Observación</div><div style="font-weight:800">' . h((string)($cliche['notes'] ?? '-')) . '</div></div>
    </div></div>';

    if (strtoupper(trim((string)($cliche['status'] ?? ''))) === 'AVAILABLE') {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Retirar a OT</div>
          <form method="post" action="/cliches/' . $clicheId . '/assign">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <input type="hidden" name="return_work_order_id" value="' . ($selectedWorkOrderId > 0 ? (int)$selectedWorkOrderId : '') . '">
            <div class="row">
              <div style="flex:2;min-width:260px">
                <label>OT destino</label>
                <select name="work_order_id" required>
                  <option value="">Seleccionar</option>';
        foreach ($workOrders as $workOrder) {
            $selected = (int)$workOrder['id'] === $selectedWorkOrderId ? ' selected' : '';
            $body .= '<option value="' . (int)$workOrder['id'] . '"' . $selected . '>' . h((string)$workOrder['ot_code']) . ' - ' . h((string)$workOrder['sku_final']) . ' [' . h(workOrderStatusLabel((string)$workOrder['status'])) . ']</option>';
        }
        $body .= '</select>
              </div>
              <div style="flex:2;min-width:260px">
                <label>Observación</label>
                <input type="text" name="notes" value="" placeholder="Ej: retiro para impresión línea 2">
              </div>
            </div>
            <div style="margin-top:12px"><button class="btn" type="submit">Asignar a OT</button></div>
          </form>
        </div>';
    } elseif (strtoupper(trim((string)($cliche['status'] ?? ''))) === 'IN_USE') {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Devolver clisé</div>
          <form method="post" action="/cliches/' . $clicheId . '/return">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <input type="hidden" name="return_work_order_id" value="' . ($selectedWorkOrderId > 0 ? (int)$selectedWorkOrderId : ((int)($cliche['current_work_order_id'] ?? 0) > 0 ? (int)$cliche['current_work_order_id'] : '')) . '">
            <div class="row">
              <div style="flex:1;min-width:220px"><label>Ubicación retorno</label><input type="text" name="location_code" value="" placeholder="Ej: RACK-C1" required></div>
              <div style="flex:2;min-width:260px"><label>Detalle ubicación</label><input type="text" name="location_detail" value="" placeholder="Ej: Estante superior"></div>
            </div>
            <div style="margin-top:10px"><label>Observación</label><input type="text" name="notes" value="" placeholder="Ej: devuelto después de cierre de OT"></div>
            <div style="margin-top:12px"><button class="btn" type="submit">Registrar devolución</button></div>
          </form>
        </div>';
    }

    if ($currentWorkOrder !== null) {
        $body .= '<div class="card" style="margin-bottom:12px;background:#f8fafc">
          <div style="font-weight:800;margin-bottom:6px">Uso actual</div>
          <div class="muted">Este clisé está asignado a la OT <a href="/work-orders/' . (int)$currentWorkOrder['id'] . '/start">' . h((string)$currentWorkOrder['ot_code']) . '</a> y no puede asignarse a otra OT hasta registrar su devolución.</div>
        </div>';
    }

    $body .= '<div class="card">
      <div style="font-weight:800;margin-bottom:8px">Historial de uso</div>
      <div class="table-wrap"><table class="trace-table"><thead><tr><th>Fecha</th><th>Acción</th><th>OT</th><th>Origen</th><th>Destino</th><th>Operador</th><th>Observación</th></tr></thead><tbody>';
    foreach ($usageLogs as $usageLog) {
        $body .= '<tr>';
        $body .= '<td>' . h((string)$usageLog['created_at']) . '</td>';
        $body .= '<td>' . h(eventTypeLabel('CLICHE_' . (string)$usageLog['action_type'])) . '</td>';
        $body .= '<td>' . h((string)($usageLog['ot_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($usageLog['from_location_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($usageLog['to_location_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)$usageLog['operator_name']) . '</td>';
        $body .= '<td>' . h((string)($usageLog['notes'] ?? '-')) . '</td>';
        $body .= '</tr>';
    }
    if ($usageLogs === []) {
        $body .= '<tr><td colspan="7" class="muted">Todavía no hay historial para este clisé.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    render('Clisés', $body);
    exit;
}

if (preg_match('#^/cliches/(\d+)/assign$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $clicheId = (int)$m[1];
    $returnWorkOrderId = (int)($_POST['return_work_order_id'] ?? 0);
    $result = $service->assignClicheToWorkOrder(
        $clicheId,
        (int)($_POST['work_order_id'] ?? 0),
        (string)($_POST['notes'] ?? ''),
        $currentOperatorName
    );
    $query = (($result['ok'] ?? false) === true)
        ? '?msg=' . urlencode('Clisé asignado correctamente.')
        : '?error=' . urlencode((string)reset($result['errors']));
    if ($returnWorkOrderId > 0) {
        $query .= '&work_order_id=' . $returnWorkOrderId;
    }
    header('Location: /cliches/' . $clicheId . $query);
    exit;
}

if (preg_match('#^/cliches/(\d+)/return$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $clicheId = (int)$m[1];
    $returnWorkOrderId = (int)($_POST['return_work_order_id'] ?? 0);
    $result = $service->returnCliche(
        $clicheId,
        (string)($_POST['location_code'] ?? ''),
        (string)($_POST['location_detail'] ?? ''),
        (string)($_POST['notes'] ?? ''),
        $currentOperatorName
    );
    $query = (($result['ok'] ?? false) === true)
        ? '?msg=' . urlencode('Clisé devuelto correctamente.')
        : '?error=' . urlencode((string)reset($result['errors']));
    if ($returnWorkOrderId > 0) {
        $query .= '&work_order_id=' . $returnWorkOrderId;
    }
    header('Location: /cliches/' . $clicheId . $query);
    exit;
}

    return false;
}
