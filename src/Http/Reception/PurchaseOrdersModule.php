<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Órdenes de Compra Nacionales (src/Http/Reception/PurchaseOrdersModule.php)
// =============================================================================

function handlePurchaseOrdersRoutes(string $path, string $method, ReceptionService $service, PrintService $printer, string $currentOperatorName = ''): bool
{
    // Nombre del operador: lo usan la vista de recepción y el registro de la bobina.
    // Si el router no lo entrega, se toma de la sesión (antes quedaba sin definir y mostraba "Undefined variable").
    if ($currentOperatorName === '') {
        $currentOperatorName = trim((string)($_SESSION['operator_name'] ?? $_SESSION['auth_display_name'] ?? 'Operador'));
        if ($currentOperatorName === '') {
            $currentOperatorName = 'Operador';
        }
    }
if ($path === '/purchase-orders/export-excel' && $method === 'GET') {
    $supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $status = isset($_GET['status']) ? (string)$_GET['status'] : 'active';
    if (!in_array($status, ['active', 'complete'], true)) {
        $status = 'active';
    }
    $supplierType = isset($_GET['supplier_type']) ? strtoupper(trim((string)$_GET['supplier_type'])) : 'NATIONAL';
    $dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
    $dateTo = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';

    $pos = $service->listPurchaseOrders(
        $supplierId > 0 ? $supplierId : null,
        $q !== '' ? $q : null,
        $status,
        $supplierType,
        5000,
        $dateFrom !== '' ? $dateFrom : null,
        $dateTo !== '' ? $dateTo : null
    );

    $isComplete = ($status === 'complete');
    $statusText = $isComplete ? 'Finalizadas' : 'Activas';
    $filename = 'informe_recepcion_nacional_' . ($isComplete ? 'finalizadas' : 'activas') . '_' . date('Ymd_His') . '.xlsx';

    $metaFilters = [];
    $metaFilters[] = 'Estado: ' . $statusText;
    if ($dateFrom !== '' || $dateTo !== '') {
        $metaFilters[] = 'Rango Fechas: ' . ($dateFrom ?: 'Inicio') . ' al ' . ($dateTo ?: 'Fin');
    }
    if ($supplierId > 0 && !empty($pos)) {
        $metaFilters[] = 'Proveedor ID: ' . $supplierId;
    }
    if ($q !== '') {
        $metaFilters[] = 'Búsqueda OC: ' . $q;
    }
    $metaFilters[] = 'Total Órdenes: ' . count($pos);
    $metaLine = implode(' | ', $metaFilters);

    $poIds = array_column($pos, 'id');
    $allLines = $service->listPurchaseOrderLinesForOrders($poIds);
    $linesByPo = [];
    $wOrdByPo = [];
    $wRecByPo = [];
    foreach ($allLines as $ln) {
        $pId = (int)$ln['purchase_order_id'];
        $linesByPo[$pId][] = $ln;
        $wOrdByPo[$pId] = ($wOrdByPo[$pId] ?? 0.0) + (float)($ln['ordered_weight_kg'] ?? 0);
        $wRecByPo[$pId] = ($wRecByPo[$pId] ?? 0.0) + (float)($ln['received_weight_kg'] ?? 0);
    }

    $html = '<!doctype html><html><head><meta charset="utf-8"></head><body>';
    $html .= '<h1>INFORME DE RECEPCIÓN NACIONAL (' . strtoupper($statusText) . ')</h1>';
    $html .= '<div class="meta">Fecha de descarga: ' . date('d/m/Y H:i') . ' | ' . h($metaLine) . '</div>';

    // Parte 1: Resumen General
    $html .= '<h2>1. RESUMEN GENERAL DE ÓRDENES DE COMPRA (INCLUYE COMPARATIVA DE KILOS)</h2>';
    $html .= '<table border="1">';
    $html .= '<thead><tr>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Orden de Compra</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Proveedor</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Tipo Proveedor</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">País</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Estado</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Líneas Completadas</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Total Líneas</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Avance Líneas (%)</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Kilos a Recibir (Debían Recibirse)</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Kilos Recibidos</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Diferencia Kilos (kg)</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Cumplimiento Kilos (%)</th>
        <th style="background-color:#0f172a;color:#ffffff;font-weight:bold">Fecha Creación</th>
    </tr></thead><tbody>';
    foreach ($pos as $po) {
        $pId = (int)$po['id'];
        $compl = (int)($po['completed_lines'] ?? 0);
        $tot = (int)($po['total_lines'] ?? 0);
        $pct = $tot > 0 ? (int)round(($compl / $tot) * 100) : 0;
        $stLabel = match((string)$po['status']) {
            'COMPLETE' => 'Finalizada',
            'PARTIAL' => 'Parcial',
            default => 'Abierta',
        };
        $wOrd = (float)($wOrdByPo[$pId] ?? 0.0);
        $wRec = (float)($wRecByPo[$pId] ?? 0.0);
        $wDiff = max(0, $wOrd - $wRec);
        $wPct = $wOrd > 0 ? (round(($wRec / $wOrd) * 100, 1) . '%') : '-';

        $html .= '<tr>';
        $html .= '<td class="text-cell">' . h((string)$po['po_code']) . '</td>';
        $html .= '<td>' . h((string)$po['supplier_name']) . '</td>';
        $html .= '<td>' . h((string)($po['supplier_type'] ?? 'Nacional')) . '</td>';
        $html .= '<td>' . h((string)($po['supplier_country_name'] ?? '-')) . '</td>';
        $html .= '<td>' . $stLabel . '</td>';
        $html .= '<td class="num-int">' . $compl . '</td>';
        $html .= '<td class="num-int">' . $tot . '</td>';
        $html .= '<td class="num-int">' . $pct . '%</td>';
        $html .= '<td class="num-dec2">' . number_format($wOrd, 2, '.', '') . '</td>';
        $html .= '<td class="num-dec2">' . number_format($wRec, 2, '.', '') . '</td>';
        $html .= '<td class="num-dec2">' . number_format($wDiff, 2, '.', '') . '</td>';
        $html .= '<td class="num-int">' . $wPct . '</td>';
        $html .= '<td>' . h((string)$po['created_at']) . '</td>';
        $html .= '</tr>';
    }
    if ($pos === []) {
        $html .= '<tr><td colspan="13">Sin órdenes encontradas con los filtros seleccionados.</td></tr>';
    }
    $html .= '</tbody></table>';

    // Parte 2: Detalle por cada una de las recepciones
    $html .= '<h2>2. DETALLE DE LÍNEAS Y RECEPCIÓN POR ORDEN DE COMPRA (KILOS RECIBIDOS VS DEBÍAN RECIBIRSE)</h2>';
    $html .= '<table border="1">';
    $html .= '<thead><tr>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Orden de Compra</th>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Proveedor</th>
        <th style="background-color:#1e3a8a;color:#ffffff;font-weight:bold">Estado OC</th>
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
    foreach ($pos as $po) {
        $lines = $linesByPo[(int)$po['id']] ?? [];
        $poStLabel = match((string)$po['status']) {
            'COMPLETE' => 'Finalizada',
            'PARTIAL' => 'Parcial',
            default => 'Abierta',
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
            $html .= '<td class="text-cell">' . h((string)$po['po_code']) . '</td>';
            $html .= '<td>' . h((string)$po['supplier_name']) . '</td>';
            $html .= '<td>' . $poStLabel . '</td>';
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
        $html .= '<tr><td colspan="14">Sin líneas de recepción en las órdenes seleccionadas.</td></tr>';
    }
    $html .= '</tbody></table>';
    $html .= '</body></html>';

    SimpleXlsx::streamHtml($filename, $html, 'Recepción Nacional');
    exit;
}

if ($path === '/purchase-orders' && $method === 'GET') {
    $supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $status = isset($_GET['status']) ? (string)$_GET['status'] : 'active';
    if (!in_array($status, ['active', 'complete'], true)) {
        $status = 'active';
    }
    $supplierType = isset($_GET['supplier_type']) ? strtoupper(trim((string)$_GET['supplier_type'])) : '';
    if (!in_array($supplierType, ['', 'ALL', 'NATIONAL', 'IMPORT'], true)) {
        $supplierType = '';
    }
    if ($supplierType === '') {
        $supplierType = 'NATIONAL';
    }
    $dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
    $dateTo = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';

    if ($supplierType === 'IMPORT') {
        $query = ['status=' . rawurlencode($status)];
        if ($supplierId > 0) {
            $query[] = 'supplier_id=' . $supplierId;
        }
        if ($q !== '') {
            $query[] = 'po_code=' . rawurlencode($q);
        }
        if ($dateFrom !== '') {
            $query[] = 'date_from=' . rawurlencode($dateFrom);
        }
        if ($dateTo !== '') {
            $query[] = 'date_to=' . rawurlencode($dateTo);
        }
        header('Location: /import-containers?' . implode('&', $query));
        exit;
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = 20;
    $totalCount = $service->countPurchaseOrders(
        $supplierId > 0 ? $supplierId : null,
        $q !== '' ? $q : null,
        $status,
        $supplierType,
        $dateFrom !== '' ? $dateFrom : null,
        $dateTo !== '' ? $dateTo : null
    );
    $totalPages = max(1, (int)ceil($totalCount / $pageSize));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $pageSize;

    $suppliers = $service->listSuppliersForPurchaseOrders($status, $supplierType);
    $pos = $service->listPurchaseOrders(
        $supplierId > 0 ? $supplierId : null,
        $q !== '' ? $q : null,
        $status,
        $supplierType,
        $pageSize,
        $dateFrom !== '' ? $dateFrom : null,
        $dateTo !== '' ? $dateTo : null,
        $offset
    );

    $isComplete = ($status === 'complete');
    $activeTab = $isComplete ? 'national_complete' : 'national_active';
    $pageTitle = $isComplete ? 'Recepciones Nacionales Finalizadas' : 'Recepción Nacional Activa';
    $pageSubtitle = $isComplete
        ? 'Historial de órdenes de compra nacionales recepcionadas y cerradas (desde 2024 en adelante).'
        : 'Órdenes de compra nacionales vigentes para recepcionar en planta.';

    $body = renderView('reception/purchase_orders_list', get_defined_vars());
    render($pageTitle, $body);
    exit;
}

if (preg_match('#^/purchase-orders/(\d+)/rolls-excel$#', $path, $m) === 1 && $method === 'GET') {
    $poId = (int)$m[1];
    $lineId = isset($_GET['line']) ? (int)$_GET['line'] : null;
    $rolls = $service->listRollsByPurchaseOrder($poId, $lineId, 5000);
    $po = $service->getPurchaseOrder($poId);
    $poCode = $po ? trim((string)$po['po_code']) : (string)$poId;
    unibagOutputWarehouseRollsExcel(
        'bobinas-oc-' . $poCode . '-' . date('Ymd-His') . '.xlsx',
        $rolls,
        'Orden de Compra ' . $poCode
    );
    exit;
}

if (preg_match('#^/purchase-orders/(\\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $poId = (int)$m[1];
    $po = $service->getPurchaseOrder($poId);
    if ($po === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OC.</div>');
        exit;
    }
    $lines = $service->listPurchaseOrderLines($poId);

    $totalPendingUnits = 0;
    foreach ($lines as $ln) {
        $sm = receptionLineSummary($ln);
        $totalPendingUnits += max(0, (int)($sm['pending_rolls'] ?? 0));
    }

    $closure = $service->getReceptionClosure('PURCHASE_ORDER', $poId);
    $closureBanner = renderReceptionClosureBanner($closure);

    $isComplete = ((string)$po['status'] === 'COMPLETE');
    $closeBtn = '';
    if (!$isComplete) {
        $closeBtn = '<button type="button" class="btn" style="background:#dc2626;border-color:#dc2626;color:#fff;font-weight:600;display:inline-flex;align-items:center;gap:6px" onclick="openCloseReceptionModal()"><span>🔒</span> Cerrar Recepción (Con Faltantes)</button>';
    }

    $successAlert = '';
    if (isset($_GET['closed']) && $_GET['closed'] === '1') {
        $successAlert = '<div style="margin-bottom:16px;padding:12px 16px;background:#ecfdf5;border:1px solid #a7f3d0;border-left:5px solid #10b981;border-radius:10px;color:#065f46;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px"><span>✅</span> La recepción de la orden de compra ha sido cerrada y finalizada exitosamente.</div>';
    }

    $backUrl = $isComplete ? '/purchase-orders?status=complete&supplier_type=NATIONAL' : '/purchase-orders?status=active&supplier_type=NATIONAL';
    $typeLabel = ((string)($po['supplier_type'] ?? 'NATIONAL')) === 'IMPORT' ? 'Importación' : 'Nacional';

    $body = renderView('reception/purchase_order_detail', get_defined_vars());

    render('OC ' . (string)$po['po_code'], $body);
    exit;
}

if (preg_match('#^/purchase-orders/(\\d+)/receive$#', $path, $m) === 1 && $method === 'GET') {
    $poId = (int)$m[1];
    $po = $service->getPurchaseOrder($poId);
    if ($po === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OC.</div>');
        exit;
    }
    $isReceptionLocked = false;
    $lineId = isset($_GET['line']) ? (int)$_GET['line'] : 0;
    $line = $service->getPurchaseOrderLine($lineId);
    if ($line === null || (int)$line['purchase_order_id'] !== $poId) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la línea de OC.</div>');
        exit;
    }

    $warehouses = $service->listWarehousesForReception();
    $allLineRolls = $service->listRollsByPurchaseOrder($poId, $lineId, 2000);
    if (empty($allLineRolls)) {
        $allLineRolls = $service->listRollsByPurchaseOrderLine($lineId, 2000);
    }
    $totalLineRollsCount = count($allLineRolls);
    $totalLineRollsWeight = array_sum(array_column($allLineRolls, 'weight_kg'));
    $recent = $allLineRolls;
    $lineSummary = receptionLineSummary($line);
    $isWeightMode = $lineSummary['mode'] === 'WEIGHT';
    $lineUnit = $lineSummary['unit'];
    $pendingInputMax = $isWeightMode ? number_format((float)$lineSummary['pending'], 3, '.', '') : '';

    $allPoLines = $service->listPurchaseOrderLines($poId);
    $poPendingUnits = 0;
    foreach ($allPoLines as $pLn) {
        $sm = receptionLineSummary($pLn);
        $poPendingUnits += max(0, (int)($sm['pending_rolls'] ?? 0));
    }

    $previewUrl = '/reception/test-label?type=po&line=' . (int)$lineId;
    $savedRollId = isset($_GET['saved_roll_id']) ? (int)$_GET['saved_roll_id'] : 0;
    $printedParam = isset($_GET['printed']) ? (string)$_GET['printed'] : '';
    $latestRoll = $savedRollId > 0 ? $service->getRoll($savedRollId) : ($recent !== [] ? $recent[0] : null);
    $activeCardRollId = $latestRoll ? (int)$latestRoll['id'] : 0;
    $hasReceivedRoll = $activeCardRollId > 0;
    $cardIframeSrc = $hasReceivedRoll ? ('/rolls/' . $activeCardRollId . '/label') : $previewUrl;
    $zebraProfileQuery = '&printer_profile=zebra';
    $barposProfileQuery = '&printer_profile=barpos';

    $body = renderView('reception/purchase_order_receive', get_defined_vars());

    render('Recepción OC', $body);
    exit;
}

if (preg_match('#^/purchase-orders/(\\d+)/receive$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $poId = (int)$m[1];
    $lineId = isset($_POST['purchase_order_line_id']) ? (int)$_POST['purchase_order_line_id'] : 0;
    $warehouseId = isset($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : 0;
    $weight = isset($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : 0.0;
    $receivedQty = isset($_POST['received_qty']) ? (float)$_POST['received_qty'] : 1.0;
    $receptionMode = isset($_POST['reception_mode']) ? (string)$_POST['reception_mode'] : 'QUANTITY';
    $line = $service->getPurchaseOrderLine($lineId);
    if ($line === null || (int)$line['purchase_order_id'] !== $poId) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la línea de OC.</div>');
        exit;
    }

    $result = $service->createRollFromPurchaseOrderLine($lineId, $warehouseId, $weight, $currentOperatorName, $receivedQty, $receptionMode);
    if ($result['ok'] === true) {
        $rollId = (int)($result['id'] ?? 0);
        $batch = saveReceptionLabelBatch($result, $service, $printer);
        header('Location: /purchase-orders/' . $poId . '/receive?' . http_build_query([
            'line' => $lineId, 'saved_roll_id' => $rollId,
            'saved_batch' => $batch['token'], 'printed' => $batch['printed'],
        ]));
        exit;
    }

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Recepcionar bobina</div>
        <a class="btn secondary" href="/purchase-orders/' . $poId . '">Volver</a>
      </div>';
    $body .= '<div class="err" style="margin-bottom:12px"><ul style="margin:0;padding-left:16px">';
    foreach ($result['errors'] as $msg) {
        $body .= '<li>' . h((string)$msg) . '</li>';
    }
    $body .= '</ul></div>';
    $body .= '<div class="card"><a class="btn secondary" href="/purchase-orders/' . $poId . '">Volver</a></div>';
    render('Recepción OC', $body);
    exit;
}

    return false;
}
