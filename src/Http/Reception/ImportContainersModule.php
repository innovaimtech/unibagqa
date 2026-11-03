<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Contenedores de Importación y Aduana (src/Http/Reception/ImportContainersModule.php)
// =============================================================================

function handleImportContainersRoutes(string $path, string $method, ReceptionService $service, PrintService $printer, string $currentOperatorName = ''): bool
{
    // Nombre del operador: lo usan la vista de recepción y el registro de la bobina.
    // Si el router no lo entrega, se toma de la sesión (antes quedaba sin definir y mostraba "Undefined variable").
    if ($currentOperatorName === '') {
        $currentOperatorName = trim((string)($_SESSION['operator_name'] ?? $_SESSION['auth_display_name'] ?? 'Operador'));
        if ($currentOperatorName === '') {
            $currentOperatorName = 'Operador';
        }
    }
if (preg_match('#^/import-containers/(\d+)/rolls-excel$#', $path, $m) === 1 && $method === 'GET') {
    $containerId = (int)$m[1];
    $lineId = isset($_GET['line']) ? (int)$_GET['line'] : null;
    $rolls = $service->listRollsByImportContainer($containerId, $lineId, 5000);
    $container = $service->getImportContainer($containerId);
    $cCode = $container ? trim((string)$container['container_code']) : (string)$containerId;
    unibagOutputWarehouseRollsExcel(
        'bobinas-contenedor-' . $cCode . '-' . date('Ymd-His') . '.xlsx',
        $rolls,
        'Contenedor ' . $cCode
    );
    exit;
}

if ($path === '/import-containers/export-excel' && $method === 'GET') {
    $supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
    $status = isset($_GET['status']) ? (string)$_GET['status'] : 'active';
    if (!in_array($status, ['active', 'complete'], true)) {
        $status = 'active';
    }
    $dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
    $dateTo = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';
    $poCode = isset($_GET['po_code']) ? trim((string)$_GET['po_code']) : '';
    $bl = isset($_GET['bl']) ? trim((string)$_GET['bl']) : '';
    $containerCode = isset($_GET['container_code']) ? trim((string)$_GET['container_code']) : '';
    $productType = isset($_GET['product_type']) ? trim((string)$_GET['product_type']) : '';
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

    $containers = $service->listImportContainers(
        $supplierId > 0 ? $supplierId : null,
        $q !== '' ? $q : null,
        $status,
        5000,
        $dateFrom !== '' ? $dateFrom : null,
        $dateTo !== '' ? $dateTo : null,
        $poCode !== '' ? $poCode : null,
        $bl !== '' ? $bl : null,
        $containerCode !== '' ? $containerCode : null,
        $productType !== '' ? $productType : null
    );

    $isComplete = ($status === 'complete');
    $statusText = $isComplete ? 'Finalizados' : 'Activos';
    $filename = 'informe_recepcion_importacion_' . ($isComplete ? 'finalizados' : 'activos') . '_' . date('Ymd_His') . '.xlsx';

    $metaFilters = [];
    $metaFilters[] = 'Estado: ' . $statusText;
    if ($dateFrom !== '' || $dateTo !== '') {
        $metaFilters[] = 'Rango Fechas: ' . ($dateFrom ?: 'Inicio') . ' al ' . ($dateTo ?: 'Fin');
    }
    if ($containerCode !== '') $metaFilters[] = 'Contenedor: ' . $containerCode;
    if ($bl !== '') $metaFilters[] = 'BL: ' . $bl;
    if ($poCode !== '') $metaFilters[] = 'OC: ' . $poCode;
    if ($productType !== '') $metaFilters[] = 'Tipo Producto: ' . $productType;
    if ($supplierId > 0) $metaFilters[] = 'Proveedor ID: ' . $supplierId;
    $metaFilters[] = 'Total Cargamentos: ' . count($containers);
    $metaLine = implode(' | ', $metaFilters);

    $cIds = array_column($containers, 'id');
    $allLines = $service->listImportContainerLinesForContainers($cIds);
    $linesByContainer = [];
    $wOrdByContainer = [];
    $wRecByContainer = [];
    foreach ($allLines as $ln) {
        $cId = (int)$ln['import_container_id'];
        $linesByContainer[$cId][] = $ln;
        $wOrdByContainer[$cId] = ($wOrdByContainer[$cId] ?? 0.0) + (float)($ln['ordered_weight_kg'] ?? 0);
        $wRecByContainer[$cId] = ($wRecByContainer[$cId] ?? 0.0) + (float)($ln['received_weight_kg'] ?? 0);
    }

    $html = '<!doctype html><html><head><meta charset="utf-8"></head><body>';
    $html .= '<h1>INFORME DE IMPORTACIÓN Y CONTENEDORES (' . strtoupper($statusText) . ')</h1>';
    $html .= '<div class="meta">Fecha de descarga: ' . date('d/m/Y H:i') . ' | ' . h($metaLine) . '</div>';

    // Parte 1: Resumen General
    $html .= '<h2>1. RESUMEN GENERAL DE CARGAMENTOS Y CONTENEDORES (INCLUYE COMPARATIVA DE KILOS)</h2>';
    $html .= '<table border="1">';
    $html .= '<thead><tr>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Contenedor</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Bill of Lading (BL)</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Embarcador</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Buque</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">OCs Asociadas</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Estado</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Líneas Completadas</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Total Líneas</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Avance Líneas (%)</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Kilos a Recibir (Debían Recibirse)</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Kilos Recibidos</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Diferencia Kilos (kg)</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Cumplimiento Kilos (%)</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">ETA Planta</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Fecha Registro</th>
    </tr></thead><tbody>';
    foreach ($containers as $c) {
        $cId = (int)$c['id'];
        $compl = (int)($c['completed_lines'] ?? 0);
        $tot = (int)($c['total_lines'] ?? 0);
        $pct = $tot > 0 ? (int)round(($compl / $tot) * 100) : 0;
        $cCode = trim((string)$c['container_code']);
        if ($cCode === '') {
            $blVal = trim((string)$c['bill_of_lading']);
            $cCode = $blVal !== '' ? ('BL ' . $blVal) : ('Cargamento #' . $cId);
        }
        $stLabel = match((string)$c['status']) {
            'COMPLETE' => 'Finalizado',
            'PARTIAL' => 'Parcial',
            default => 'Abierto',
        };
        $wOrd = (float)($wOrdByContainer[$cId] ?? 0.0);
        $wRec = (float)($wRecByContainer[$cId] ?? 0.0);
        $wDiff = max(0, $wOrd - $wRec);
        $wPct = $wOrd > 0 ? (round(($wRec / $wOrd) * 100, 1) . '%') : '-';

        $html .= '<tr>';
        $html .= '<td class="text-cell">' . h($cCode) . '</td>';
        $html .= '<td class="text-cell">' . h((string)($c['bill_of_lading'] ?: '-')) . '</td>';
        $html .= '<td>' . h((string)($c['forwarder_name'] ?: '-')) . '</td>';
        $html .= '<td>' . h((string)($c['vessel_name'] ?: '-')) . '</td>';
        $html .= '<td class="text-cell">' . h((string)($c['po_codes'] ?: '-')) . '</td>';
        $html .= '<td>' . $stLabel . '</td>';
        $html .= '<td class="num-int">' . $compl . '</td>';
        $html .= '<td class="num-int">' . $tot . '</td>';
        $html .= '<td class="num-int">' . $pct . '%</td>';
        $html .= '<td class="num-dec2">' . number_format($wOrd, 2, '.', '') . '</td>';
        $html .= '<td class="num-dec2">' . number_format($wRec, 2, '.', '') . '</td>';
        $html .= '<td class="num-dec2">' . number_format($wDiff, 2, '.', '') . '</td>';
        $html .= '<td class="num-int">' . $wPct . '</td>';
        $html .= '<td>' . h((string)($c['eta_plant'] ?: '-')) . '</td>';
        $html .= '<td>' . h((string)($c['created_at'] ?? '-')) . '</td>';
        $html .= '</tr>';
    }
    if ($containers === []) {
        $html .= '<tr><td colspan="15">Sin cargamentos encontrados con los filtros seleccionados.</td></tr>';
    }
    $html .= '</tbody></table>';

    // Parte 2: Detalle por cada una de las recepciones
    $html .= '<h2>2. DETALLE DE LÍNEAS Y RECEPCIÓN POR CARGAMENTO (KILOS RECIBIDOS VS DEBÍAN RECIBIRSE)</h2>';
    $html .= '<table border="1">';
    $html .= '<thead><tr>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Contenedor</th>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Bill of Lading</th>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Orden de Compra</th>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Proveedor</th>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Estado Cargamento</th>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">SKU / Código Item</th>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Modo Recepción</th>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Especificación / Título</th>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Rollos / Unid. Ordenadas</th>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Rollos / Unid. Recibidas</th>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Rollos / Unid. Pendientes</th>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Kilos a Recibir (Debían Recibirse)</th>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Kilos Recibidos</th>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Diferencia / Pendiente Kilos (kg)</th>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Cumplimiento Kilos (%)</th>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Estado Línea</th>
    </tr></thead><tbody>';
    $totalLinesDetail = 0;
    foreach ($containers as $c) {
        $lines = $linesByContainer[(int)$c['id']] ?? [];
        $cCode = trim((string)$c['container_code']);
        if ($cCode === '') {
            $blVal = trim((string)$c['bill_of_lading']);
            $cCode = $blVal !== '' ? ('BL ' . $blVal) : ('Cargamento #' . (int)$c['id']);
        }
        $cStLabel = match((string)$c['status']) {
            'COMPLETE' => 'Finalizado',
            'PARTIAL' => 'Parcial',
            default => 'Abierto',
        };
        foreach ($lines as $ln) {
            $totalLinesDetail++;
            $sm = $service->summarizeReceptionLine($ln);
            $spec = buildReceptionSpec($ln);
            $lnStLabel = $sm['is_complete'] ? 'Completa' : ($sm['has_progress'] ? 'Parcial' : 'Pendiente');
            $modeLabel = ((string)($ln['reception_mode'] ?? '')) === 'WEIGHT' ? 'Peso (kg)' : 'Cantidad (Rollos)';

            $lWOrd = (float)($ln['ordered_weight_kg'] ?? 0);
            $lWRec = (float)($ln['received_weight_kg'] ?? 0);
            $lWDiff = (float)($sm['pending_weight_kg'] ?? max(0, $lWOrd - $lWRec));
            $lWPct = $lWOrd > 0 ? (round(($lWRec / $lWOrd) * 100, 1) . '%') : '-';

            $html .= '<tr>';
            $html .= '<td class="text-cell">' . h($cCode) . '</td>';
            $html .= '<td class="text-cell">' . h((string)($c['bill_of_lading'] ?: '-')) . '</td>';
            $html .= '<td class="text-cell">' . h((string)($ln['po_code'] ?? '')) . '</td>';
            $html .= '<td>' . h((string)($ln['supplier_name'] ?? '')) . '</td>';
            $html .= '<td>' . $cStLabel . '</td>';
            $html .= '<td class="text-cell">' . h((string)($ln['sku_code'] ?? '')) . '</td>';
            $html .= '<td>' . $modeLabel . '</td>';
            $html .= '<td>' . h($spec) . '</td>';
            $html .= '<td class="num-int">' . (int)($ln['ordered_rolls'] ?? 0) . '</td>';
            $html .= '<td class="num-int">' . (int)($ln['received_rolls'] ?? 0) . '</td>';
            $html .= '<td class="num-int">' . (int)($sm['pending_rolls'] ?? 0) . '</td>';
            $html .= '<td class="num-dec2">' . number_format($lWOrd, 2, '.', '') . '</td>';
            $html .= '<td class="num-dec2">' . number_format($lWRec, 2, '.', '') . '</td>';
            $html .= '<td class="num-dec2">' . number_format($lWDiff, 2, '.', '') . '</td>';
            $html .= '<td class="num-int">' . $lWPct . '</td>';
            $html .= '<td>' . $lnStLabel . '</td>';
            $html .= '</tr>';
        }
    }
    if ($totalLinesDetail === 0) {
        $html .= '<tr><td colspan="16">Sin líneas de recepción en los cargamentos seleccionados.</td></tr>';
    }
    $html .= '</tbody></table>';
    $html .= '</body></html>';

    SimpleXlsx::streamHtml($filename, $html, 'Importación');
    exit;
}

if ($path === '/reports/reception-audit' && $method === 'GET') {
    require_once dirname(__DIR__) . '/Reports/ReceptionAuditModule.php';
    handleReceptionAuditList($service);
    exit;
}

if (preg_match('#^/reports/reception-audit/container/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
    require_once dirname(__DIR__) . '/Reports/ReceptionAuditModule.php';
    handleReceptionAuditContainerDetail($service, (int)$m[1]);
    exit;
}

if ($path === '/import-containers' && $method === 'GET') {
    $supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
    $status = isset($_GET['status']) ? (string)$_GET['status'] : 'active';
    if (!in_array($status, ['active', 'complete'], true)) {
        $status = 'active';
    }
    $dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
    $dateTo = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';
    $poCode = isset($_GET['po_code']) ? trim((string)$_GET['po_code']) : '';
    $bl = isset($_GET['bl']) ? trim((string)$_GET['bl']) : '';
    $containerCode = isset($_GET['container_code']) ? trim((string)$_GET['container_code']) : '';
    $productType = isset($_GET['product_type']) ? trim((string)$_GET['product_type']) : '';
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = 20;
    $totalCount = $service->countImportContainers(
        $supplierId > 0 ? $supplierId : null,
        $q !== '' ? $q : null,
        $status,
        $dateFrom !== '' ? $dateFrom : null,
        $dateTo !== '' ? $dateTo : null,
        $poCode !== '' ? $poCode : null,
        $bl !== '' ? $bl : null,
        $containerCode !== '' ? $containerCode : null,
        $productType !== '' ? $productType : null
    );
    $totalPages = max(1, (int)ceil($totalCount / $pageSize));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $pageSize;

    $suppliers = $service->listSuppliersForImportContainers($status);
    $containers = $service->listImportContainers(
        $supplierId > 0 ? $supplierId : null,
        $q !== '' ? $q : null,
        $status,
        $pageSize,
        $dateFrom !== '' ? $dateFrom : null,
        $dateTo !== '' ? $dateTo : null,
        $poCode !== '' ? $poCode : null,
        $bl !== '' ? $bl : null,
        $containerCode !== '' ? $containerCode : null,
        $productType !== '' ? $productType : null,
        $offset
    );

    $isComplete = ($status === 'complete');
    $activeTab = $isComplete ? 'import_complete' : 'import_active';
    $pageTitle = $isComplete ? 'Cargamentos de Importación Finalizados' : 'Importación Activa (Contenedores)';
    $pageSubtitle = $isComplete
        ? 'Historial completo de contenedores y cargamentos internacionales finalizados e históricos.'
        : 'Contenedores y cargamentos internacionales en planta o puerto para recepción.';

    $body = renderView('reception/import_containers_list', get_defined_vars());
    render($pageTitle, $body);
    exit;
}

if (preg_match('#^/import-containers/(\\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $containerId = (int)$m[1];
    $container = $service->getImportContainer($containerId);
    if ($container === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe el contenedor.</div>');
        exit;
    }
    $lines = $service->listImportContainerLines($containerId);

    $totalPendingUnits = 0;
    foreach ($lines as $ln) {
        $sm = receptionLineSummary($ln);
        $totalPendingUnits += max(0, (int)($sm['pending_rolls'] ?? 0));
    }

    $closure = $service->getReceptionClosure('IMPORT_CONTAINER', $containerId);
    $closureBanner = renderReceptionClosureBanner($closure);

    $isComplete = ((string)$container['status'] === 'COMPLETE');
    $archiveBtn = '';
    if (!$isComplete) {
        $archiveBtn = '<button type="button" class="btn" style="background:#dc2626;border-color:#dc2626;color:#fff;font-weight:600;display:inline-flex;align-items:center;gap:6px" onclick="openCloseReceptionModal()"><span>🔒</span> Cerrar Recepción (Con Faltantes)</button>';
    }

    $successAlert = '';
    if (isset($_GET['closed']) && $_GET['closed'] === '1') {
        $successAlert = '<div style="margin-bottom:16px;padding:12px 16px;background:#ecfdf5;border:1px solid #a7f3d0;border-left:5px solid #10b981;border-radius:10px;color:#065f46;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px"><span>✅</span> La recepción del cargamento ha sido cerrada y finalizada exitosamente.</div>';
    }

    $cCode = trim((string)$container['container_code']);
    $cTitle = $cCode !== '' ? ('Contenedor ' . $cCode) : (trim((string)$container['bill_of_lading']) !== '' ? ('Cargamento BL ' . trim((string)$container['bill_of_lading'])) : ('Cargamento #' . $containerId));
    $containerOrderedUnits = formatReceptionValue((float)($container['ordered_rolls'] ?? 0), 'Unid.') . ' Unid.';
    $containerReceivedUnits = formatReceptionValue((float)($container['received_rolls'] ?? 0), 'Unid.') . ' Unid.';
    $containerPendingUnits = formatReceptionValue((float)($container['pending_rolls'] ?? 0), 'Unid.') . ' Unid.';
    $containerOrderedWeight = formatReceptionValue((float)($container['ordered_weight_kg'] ?? 0), 'Kg') . ' Kg';
    $containerReceivedWeight = formatReceptionValue((float)($container['received_weight_kg'] ?? 0), 'Kg') . ' Kg';
    $containerPendingWeight = formatReceptionValue((float)($container['pending_weight_kg'] ?? 0), 'Kg') . ' Kg';
    $containerErpStatus = (bool)($container['erp_is_complete'] ?? false) ? 'Completo en ERP' : 'Pendiente en ERP';
    $containerErpColor = (bool)($container['erp_is_complete'] ?? false) ? '#065f46' : '#9a3412';
    $containerErpBg = (bool)($container['erp_is_complete'] ?? false) ? '#ecfdf5' : '#fff7ed';
    $containerErpBorder = (bool)($container['erp_is_complete'] ?? false) ? '#a7f3d0' : '#fdba74';

    $backUrl = $isComplete ? '/import-containers?status=complete' : '/import-containers?status=active';
    $body = renderView('reception/import_container_detail', get_defined_vars());

    render('Contenedor ' . $cCode, $body);
    exit;
}

if (preg_match('#^/import-containers/(\\d+)/receive$#', $path, $m) === 1 && $method === 'GET') {
    $containerId = (int)$m[1];
    $container = $service->getImportContainer($containerId);
    if ($container === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe el contenedor.</div>');
        exit;
    }
    $isReceptionLocked = false;
    $containerItemId = isset($_GET['line']) ? (int)$_GET['line'] : 0;
    $line = $service->getImportContainerLine($containerItemId);
    if ($line === null || (int)($line['import_container_id'] ?? 0) !== $containerId) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la línea de contenedor.</div>');
        exit;
    }

    $warehouses = $service->listWarehousesForReception();
    $allLineRolls = $service->listRollsByImportContainer($containerId, (int)$line['import_container_item_id'], 2000);
    if (empty($allLineRolls)) {
        $allLineRolls = $service->listRollsByPurchaseOrderLine((int)$line['id'], 2000, (int)$line['import_container_item_id']);
    }
    $totalLineRollsCount = count($allLineRolls);
    $totalLineRollsWeight = array_sum(array_column($allLineRolls, 'weight_kg'));
    $recent = $allLineRolls;
    $lineSummary = receptionLineSummary($line);
    $isWeightMode = $lineSummary['mode'] === 'WEIGHT';
    $lineUnit = $lineSummary['unit'];
    $pendingInputMax = $isWeightMode ? number_format((float)$lineSummary['pending'], 3, '.', '') : '';

    $allContainerLines = $service->listImportContainerLines($containerId);
    $cPendingUnits = 0;
    foreach ($allContainerLines as $cLn) {
        $sm = receptionLineSummary($cLn);
        $cPendingUnits += max(0, (int)($sm['pending_rolls'] ?? 0));
    }

    $cCode = trim((string)($line['container_code'] ?: ('#' . $containerId)));
    $previewUrl = '/reception/test-label?type=import&line=' . (int)$containerItemId;
    $savedRollId = isset($_GET['saved_roll_id']) ? (int)$_GET['saved_roll_id'] : 0;
    $printedParam = isset($_GET['printed']) ? (string)$_GET['printed'] : '';
    $latestRoll = $savedRollId > 0 ? $service->getRoll($savedRollId) : ($recent !== [] ? $recent[0] : null);
    $activeCardRollId = $latestRoll ? (int)$latestRoll['id'] : 0;
    $hasReceivedRoll = $activeCardRollId > 0;
    $cardIframeSrc = $hasReceivedRoll ? ('/rolls/' . $activeCardRollId . '/label') : $previewUrl;
    $zebraProfileQuery = '&printer_profile=zebra';
    $barposProfileQuery = '&printer_profile=barpos';

    $body = renderView('reception/import_container_receive', get_defined_vars());

    render('Recepción importación', $body);
    exit;
}

if (preg_match('#^/import-containers/(\\d+)/receive$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $containerId = (int)$m[1];
    $containerItemId = isset($_POST['import_container_item_id']) ? (int)$_POST['import_container_item_id'] : 0;
    $warehouseId = isset($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : 0;
    $weight = isset($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : 0.0;
    $receivedQty = isset($_POST['received_qty']) ? (float)$_POST['received_qty'] : 1.0;
    $receptionMode = isset($_POST['reception_mode']) ? (string)$_POST['reception_mode'] : 'QUANTITY';
    $line = $service->getImportContainerLine($containerItemId);
    if ($line === null || (int)($line['import_container_id'] ?? 0) !== $containerId) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la línea de contenedor.</div>');
        exit;
    }

    $result = $service->createRollFromImportContainerLine($containerItemId, $warehouseId, $weight, $currentOperatorName, $receivedQty, $receptionMode);
    if ($result['ok'] === true) {
        $rollId = (int)($result['id'] ?? 0);
        $batch = saveReceptionLabelBatch($result, $service, $printer);
        header('Location: /import-containers/' . $containerId . '/receive?' . http_build_query([
            'line' => $containerItemId, 'saved_roll_id' => $rollId,
            'saved_batch' => $batch['token'], 'printed' => $batch['printed'],
        ]));
        exit;
    }

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Recepcionar bobina importada</div>
        <a class="btn secondary" href="/import-containers/' . $containerId . '">Volver</a>
      </div>';
    $body .= '<div class="err" style="margin-bottom:12px"><ul style="margin:0;padding-left:16px">';
    foreach ($result['errors'] as $msg) {
        $body .= '<li>' . h((string)$msg) . '</li>';
    }
    $body .= '</ul></div>';
    $body .= '<div class="card"><a class="btn secondary" href="/import-containers/' . $containerId . '">Volver</a></div>';
    render('Recepción importación', $body);
    exit;
}

if (preg_match('#^/import-containers/(\d+)/sync-reception$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $containerId = (int)$m[1];
    $lineId = (int)($_POST['line'] ?? 0);
    $line = $service->getImportContainerLine($lineId);
    if ($line === null || (int)($line['import_container_id'] ?? 0) !== $containerId) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la linea de contenedor.</div>');
        exit;
    }
    $result = $service->retryImportContainerReceptionSync($containerId, (int)($_SESSION['user_id'] ?? 0));
    $query = ['line' => $lineId];
    $query[$result['ok'] ? 'synced' : 'sync_error'] = $result['ok'] ? '1' : $result['error'];
    header('Location: /import-containers/' . $containerId . '/receive?' . http_build_query($query));
    exit;
}

    return false;
}
