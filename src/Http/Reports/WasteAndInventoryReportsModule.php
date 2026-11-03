<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Módulo HTTP · Informes de Merma por Operador e Inventario ERP
 * (src/Http/Reports/WasteAndInventoryReportsModule.php)
 * =============================================================================
 */

/**
 * Renderiza el listado histórico de inventarios realizados (cabeceras).
 *
 * Desde esta pantalla se puede:
 * - Ver el detalle de una toma.
 * - Descargar el Excel del registro.
 *
 * ---
 *
 * Renders the historical list of performed inventory counts (headers).
 *
 * From this screen you can:
 * - View a count detail.
 * - Download the record Excel.
 */
function unibagRenderInventoryReportsPage(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $msg = trim((string)($_GET['msg'] ?? ''));
    $inventoryCounts = $service->listErpStockcounts(200);

    $body = '<div class="erp-prod-shell" style="max-width:100%;width:100%">';

    $body .= '<div class="ub-filter-card">
        <div class="ub-filter-header">
          <div>
            <div class="ub-filter-title">📋 Informe de Inventario · ERP</div>
            <div class="ub-filter-sub">Histórico de recuentos físicos (stockcounts) registrados en la base de datos <code>unibag_unibag</code>.</div>
          </div>
          <div class="ub-filter-actions">
            <a class="ub-btn-secondary" href="/stock">Consultar stock actual</a>
            <a class="ub-btn-primary" href="/stock/inventory-counts">+ Nueva toma de inventario</a>
          </div>
        </div>
      </div>';

    if ($msg !== '') {
        $body .= '<div class="ok" style="margin-bottom:14px;padding:12px 16px;font-weight:600;border-radius:10px">' . h($msg) . '</div>';
    }

    $body .= '<div class="ub-table-card">
        <div style="padding:16px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
          <div style="font-weight:800;font-size:15px;color:#0f172a">Recuentos de inventario registrados</div>
          <div class="muted" style="font-size:12px">Total ' . count($inventoryCounts) . ' tomas</div>
        </div>
        <div class="table-wrap">
          <table style="width:100%;margin:0;border-collapse:collapse;font-size:13px">
            <thead>
              <tr style="background:#f1f5f9;border-bottom:2px solid #cbd5e1">
                <th style="padding:10px 14px;text-align:left">N° Recuento</th>
                <th style="padding:10px 14px;text-align:center">Fecha</th>
                <th style="padding:10px 14px;text-align:left">Bodega ERP</th>
                <th style="padding:10px 14px;text-align:left">Glosa / Observación</th>
                <th style="padding:10px 14px;text-align:center">Artículos</th>
                <th style="padding:10px 14px;text-align:right">Stock Sistema</th>
                <th style="padding:10px 14px;text-align:right">Conteo Físico</th>
                <th style="padding:10px 14px;text-align:right">Diferencia</th>
                <th style="padding:10px 14px;text-align:left">Realizado por</th>
                <th style="padding:10px 14px;text-align:center">Acciones</th>
              </tr>
            </thead>
            <tbody>';

    foreach ($inventoryCounts as $count) {
        $inventoryId = (int)($count['id'] ?? 0);
        $stcNum = trim((string)($count['stc_num'] ?? ('IR#' . $inventoryId)));
        $dt = !empty($count['stc_crtdat']) ? date('d.m.Y H:i', (int)$count['stc_crtdat']) : '-';
        $storehouseName = trim((string)($count['storehouse_name'] ?? 'Bodega ERP'));
        $annotation = trim((string)($count['stc_annotation'] ?? ''));
        $itemsCount = (int)($count['items_count'] ?? 0);
        $sysQty = (float)($count['total_system_qty'] ?? 0);
        $phyQty = (float)($count['total_physical_qty'] ?? 0);
        $diffQty = (float)($count['total_diff_qty'] ?? 0);
        $operator = trim((string)($count['operator_name'] ?? 'Sistema'));

        $diffColor = $diffQty === 0.0 ? '#64748b' : ($diffQty > 0 ? '#16a34a' : '#dc2626');

        $body .= '<tr style="border-bottom:1px solid #e2e8f0">
            <td style="padding:10px 14px;font-weight:800;color:#0F766E;white-space:nowrap">
              <a href="/reports/inventory/' . $inventoryId . '" style="color:#0F766E;text-decoration:none">' . h($stcNum) . '</a>
            </td>
            <td style="padding:10px 14px;text-align:center;color:#64748b;white-space:nowrap">' . h($dt) . '</td>
            <td style="padding:10px 14px;font-weight:600;color:#0f172a">' . h($storehouseName) . '</td>
            <td style="padding:10px 14px;color:#475569;max-width:240px">' . h($annotation !== '' ? $annotation : '-') . '</td>
            <td style="padding:10px 14px;text-align:center;font-weight:700">' . number_format($itemsCount, 0, ',', '.') . '</td>
            <td style="padding:10px 14px;text-align:right;font-weight:600">' . number_format($sysQty, 2, ',', '.') . '</td>
            <td style="padding:10px 14px;text-align:right;font-weight:700">' . number_format($phyQty, 2, ',', '.') . '</td>
            <td style="padding:10px 14px;text-align:right;font-weight:800;color:' . $diffColor . '">' . ($diffQty > 0 ? '+' : '') . number_format($diffQty, 2, ',', '.') . '</td>
            <td style="padding:10px 14px;color:#334155;white-space:nowrap">' . h($operator) . '</td>
            <td style="padding:10px 14px;text-align:center;white-space:nowrap">
              <div style="display:inline-flex;gap:6px">
                <a class="btn secondary" href="/reports/inventory/' . $inventoryId . '" style="padding:4px 10px;font-size:12px">Ver</a>
                <a class="btn" href="/reports/inventory/' . $inventoryId . '/excel" style="padding:4px 10px;font-size:12px" title="Descargar Excel">📥 Excel</a>
              </div>
            </td>
          </tr>';
    }

    if ($inventoryCounts === []) {
        $body .= '<tr><td colspan="10" style="padding:30px;text-align:center;color:#64748b">Aún no hay tomas de inventario registradas en el ERP.</td></tr>';
    }

    $body .= '</tbody></table></div></div></div>';

    render('Informe inventario · ERP', $body);
}

/**
 * Emite el Excel del detalle de una toma de inventario desde el ERP.
 */
function unibagOutputInventoryReportExcel(ReceptionService $service, int $inventoryCountId): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $inventoryCount = $service->getErpStockcount($inventoryCountId);
    if ($inventoryCount === null) {
        render('No encontrado', '<div class="card">Inventario no encontrado en el ERP.</div>');
        exit;
    }

    $items = $service->listErpStockcountItems($inventoryCountId);
    $stcNum = trim((string)($inventoryCount['stc_num'] ?? ('IR' . $inventoryCountId)));
    $storehouseName = trim((string)($inventoryCount['storehouse_name'] ?? 'Bodega'));

    $filename = 'inventario-erp-' . $stcNum . '.xlsx';
    ob_start();

    echo '<html><head><meta charset="UTF-8"><style>';
    echo 'body{font-family:Arial,sans-serif;color:#0f172a}';
    echo 'table{border-collapse:collapse;width:100%}';
    echo 'th,td{border:1px solid #cbd5e1;padding:6px 8px}';
    echo 'th{background:#0f172a;color:#fff;font-weight:700;text-align:center}';
    echo 'tr:nth-child(even){background:#f8fafc}';
    echo '.num-dec2{mso-number-format:"\#\,\#\#0\.00";text-align:right}';
    echo '.text-cell{mso-number-format:"\@"}';
    echo '</style></head><body>';
    echo '<table border="1">';
    echo '<tr><th colspan="7" style="font-size:14px;padding:10px;background:#1e293b;color:#fff">Inventario Realizado ' . h($stcNum) . ' - ' . h($storehouseName) . '</th></tr>';
    echo '<tr><td colspan="7"><strong>Fecha:</strong> ' . (!empty($inventoryCount['stc_crtdat']) ? date('d.m.Y H:i', (int)$inventoryCount['stc_crtdat']) : '-') . ' | <strong>Operador:</strong> ' . h((string)($inventoryCount['operator_name'] ?? '')) . ' | <strong>Glosa:</strong> ' . h((string)($inventoryCount['stc_annotation'] ?? '')) . '</td></tr>';
    echo '<tr><th>Código</th><th>Artículo / Descripción</th><th>Unidad</th><th>Stock Sistema</th><th>Conteo Físico</th><th>Diferencia</th><th>Observación</th></tr>';
    foreach ($items as $item) {
        $sysQty = (float)($item['item_amount_stock'] ?? 0);
        $phyQty = (float)($item['item_amount_count'] ?? 0);
        $diffQty = (float)($item['item_amount_book'] ?? 0);
        echo '<tr>';
        echo '<td class="text-cell">' . h((string)($item['item_number_prod'] ?? '')) . '</td>';
        echo '<td>' . h((string)($item['item_title'] ?? '')) . '</td>';
        echo '<td class="text-cell" style="text-align:center">' . h((string)($item['unit_name'] ?? 'UNID')) . '</td>';
        echo '<td class="num-dec2">' . number_format($sysQty, 2, '.', '') . '</td>';
        echo '<td class="num-dec2">' . number_format($phyQty, 2, '.', '') . '</td>';
        echo '<td class="num-dec2">' . number_format($diffQty, 2, '.', '') . '</td>';
        echo '<td>' . h((string)($item['item_comment'] ?? '')) . '</td>';
        echo '</tr>';
    }
    if ($items === []) {
        echo '<tr><td colspan="7" style="text-align:center;padding:10px">Sin líneas registradas</td></tr>';
    }
    echo '</table>';
    echo '</body></html>';
    $html = ob_get_clean();

    SimpleXlsx::streamHtml($filename, $html, 'Inventario');
}

/**
 * Renderiza el detalle (web) de un inventario realizado en el ERP.
 */
function unibagRenderInventoryReportDetailPage(ReceptionService $service, int $inventoryCountId): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $inventoryCount = $service->getErpStockcount($inventoryCountId);
    if ($inventoryCount === null) {
        render('No encontrado', '<div class="card" style="text-align:center"><div style="font-size:18px;font-weight:800;margin-bottom:10px">Inventario no encontrado en el ERP</div><a class="btn secondary" href="/reports/inventory">Volver al listado</a></div>');
        return;
    }

    $items = $service->listErpStockcountItems($inventoryCountId);
    $stcNum = trim((string)($inventoryCount['stc_num'] ?? ('IR#' . $inventoryCountId)));
    $storehouseName = trim((string)($inventoryCount['storehouse_name'] ?? 'Bodega'));
    $dt = !empty($inventoryCount['stc_crtdat']) ? date('d.m.Y H:i', (int)$inventoryCount['stc_crtdat']) : '-';
    $operator = trim((string)($inventoryCount['operator_name'] ?? 'Sistema'));
    $annotation = trim((string)($inventoryCount['stc_annotation'] ?? ''));

    $sysTotal = (float)($inventoryCount['total_system_qty'] ?? 0);
    $phyTotal = (float)($inventoryCount['total_physical_qty'] ?? 0);
    $diffTotal = (float)($inventoryCount['total_diff_qty'] ?? 0);

    $body = '<div class="erp-prod-shell" style="max-width:100%;width:100%">';

    $body .= '<div class="ub-filter-card">
        <div class="ub-filter-header">
          <div>
            <div class="ub-filter-title">📋 Detalle de Inventario Realizado · ' . h($stcNum) . '</div>
            <div class="ub-filter-sub">Bodega ERP: <strong>' . h($storehouseName) . '</strong> · ' . h($annotation) . '</div>
          </div>
          <div class="ub-filter-actions">
            <a class="ub-btn-secondary" href="/reports/inventory">← Volver al listado</a>
            <a class="ub-btn-excel" href="/reports/inventory/' . $inventoryCountId . '/excel">📥 Exportar Excel</a>
          </div>
        </div>
      </div>';

    $body .= '<div class="card" style="margin-bottom:16px;border-radius:14px;padding:18px 20px">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
          <div style="background:#f8fafc;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0">
            <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Fecha y hora</div>
            <div style="font-weight:800;font-size:15px;color:#0f172a;margin-top:2px">' . h($dt) . '</div>
          </div>
          <div style="background:#f8fafc;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0">
            <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Responsable</div>
            <div style="font-weight:800;font-size:15px;color:#0f172a;margin-top:2px">' . h($operator) . '</div>
          </div>
          <div style="background:#f8fafc;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0">
            <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Artículos contados</div>
            <div style="font-weight:800;font-size:18px;color:#0f172a;margin-top:2px">' . count($items) . '</div>
          </div>
          <div style="background:#f8fafc;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0">
            <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Stock Sistema</div>
            <div style="font-weight:800;font-size:18px;color:#0F766E;margin-top:2px">' . number_format($sysTotal, 2, ',', '.') . '</div>
          </div>
          <div style="background:#f8fafc;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0">
            <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Conteo Físico</div>
            <div style="font-weight:800;font-size:18px;color:#0f172a;margin-top:2px">' . number_format($phyTotal, 2, ',', '.') . '</div>
          </div>
          <div style="background:#f8fafc;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0">
            <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Diferencia neta</div>
            <div style="font-weight:800;font-size:18px;color:' . ($diffTotal === 0.0 ? '#64748b' : ($diffTotal > 0 ? '#16a34a' : '#dc2626')) . ';margin-top:2px">'
              . ($diffTotal > 0 ? '+' : '') . number_format($diffTotal, 2, ',', '.') . '</div>
          </div>
        </div>
      </div>';

    $body .= '<div class="ub-table-card">
        <div style="padding:16px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0">
          <div style="font-weight:800;font-size:15px;color:#0f172a">Líneas de recuento físico</div>
        </div>
        <div class="table-wrap">
          <table style="width:100%;margin:0;border-collapse:collapse;font-size:13px">
            <thead>
              <tr style="background:#f1f5f9;border-bottom:2px solid #cbd5e1">
                <th style="padding:10px 14px;text-align:left">Código</th>
                <th style="padding:10px 14px;text-align:left">Artículo / Descripción</th>
                <th style="padding:10px 14px;text-align:center">Unidad</th>
                <th style="padding:10px 14px;text-align:right">Stock Sistema</th>
                <th style="padding:10px 14px;text-align:right">Conteo Físico</th>
                <th style="padding:10px 14px;text-align:right">Diferencia</th>
                <th style="padding:10px 14px;text-align:left">Observación</th>
              </tr>
            </thead>
            <tbody>';

    foreach ($items as $item) {
        $sysQty = (float)($item['item_amount_stock'] ?? 0);
        $phyQty = (float)($item['item_amount_count'] ?? 0);
        $diffQty = (float)($item['item_amount_book'] ?? 0);
        $diffColor = $diffQty === 0.0 ? '#64748b' : ($diffQty > 0 ? '#16a34a' : '#dc2626');

        $body .= '<tr style="border-bottom:1px solid #e2e8f0">
            <td style="padding:10px 14px;font-weight:700;white-space:nowrap;color:#0f172a">' . h((string)$item['item_number_prod']) . '</td>
            <td style="padding:10px 14px;font-weight:600;color:#334155">' . h((string)$item['item_title']) . '</td>
            <td style="padding:10px 14px;text-align:center;color:#64748b">' . h((string)$item['unit_name']) . '</td>
            <td style="padding:10px 14px;text-align:right;font-weight:600">' . number_format($sysQty, 2, ',', '.') . '</td>
            <td style="padding:10px 14px;text-align:right;font-weight:700">' . number_format($phyQty, 2, ',', '.') . '</td>
            <td style="padding:10px 14px;text-align:right;font-weight:800;color:' . $diffColor . '">' . ($diffQty > 0 ? '+' : '') . number_format($diffQty, 2, ',', '.') . '</td>
            <td style="padding:10px 14px;color:#64748b">' . h((string)($item['item_comment'] !== '' ? $item['item_comment'] : '-')) . '</td>
          </tr>';
    }

    if ($items === []) {
        $body .= '<tr><td colspan="7" style="padding:30px;text-align:center;color:#64748b">No hay líneas en este inventario.</td></tr>';
    }

    $body .= '</tbody></table></div></div></div>';

    render('Detalle inventario ' . $stcNum, $body);
}

/**
 * Renderiza el módulo e informe completo de mermas por operador.
 *
 * Incluye filtros unificados (período/rango, proceso, operador, búsqueda),
 * tarjetas KPI consolidadas, ranking interactivo por operador, tasas de merma
 * con semáforo visual de calidad, desglose por proceso y modal interactivo
 * de órdenes de trabajo (OTs) por trabajador.
 */
function unibagRenderOperatorWasteReportPage(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    /** @var DateTimeImmutable $start */
    $start = $filters['start'];
    /** @var DateTimeImmutable $end */
    $end = $filters['end'];
    $defaultFilterType = (string)$filters['filter_type'];
    $periodYm = (string)$filters['period'];
    $rangeStartInput = (string)$filters['start_date'];
    $rangeEndInput = (string)$filters['end_date'];
    $activeFilterLabel = (string)$filters['active_filter_label'];

    $filterOperatorId = isset($_GET['operator_id']) && is_numeric($_GET['operator_id']) ? (int)$_GET['operator_id'] : null;
    $filterProcess = isset($_GET['process']) ? strtolower(trim((string)$_GET['process'])) : null;
    if ($filterProcess === '' || $filterProcess === 'all') {
        $filterProcess = null;
    }
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    $reportData = $service->getErpOperatorWasteReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $filterOperatorId,
        $filterProcess
    );

    $summary = $reportData['summary'];
    $operators = $reportData['operators'];
    $catalog = $reportData['catalog'];
    $processes = $reportData['processes'];

    // Apply search filter in-memory if provided
    if ($search !== null && $search !== '') {
        $searchLower = mb_strtolower($search, 'UTF-8');
        $operators = array_values(array_filter($operators, static function (array $op) use ($searchLower): bool {
            $name = mb_strtolower((string)($op['operator_name'] ?? ''), 'UTF-8');
            $rut = mb_strtolower((string)($op['operator_rut'] ?? ''), 'UTF-8');
            $proc = mb_strtolower((string)($op['main_process'] ?? ''), 'UTF-8');
            return str_contains($name, $searchLower) || str_contains($rut, $searchLower) || str_contains($proc, $searchLower);
        }));
    }

    $fmtUnits = static fn(float $val): string => number_format($val, 0, ',', '.');
    $fmtDec = static fn(float $val): string => number_format($val, 2, ',', '.');

    // Build URL helper
    $buildUrl = static function (array $overrides) use ($defaultFilterType, $periodYm, $rangeStartInput, $rangeEndInput, $filterProcess, $filterOperatorId, $search): string {
        $params = [
            'filter_type' => $defaultFilterType,
            'period' => $periodYm,
            'start_date' => $rangeStartInput,
            'end_date' => $rangeEndInput,
            'process' => $filterProcess,
            'operator_id' => $filterOperatorId,
            'q' => $search,
        ];
        foreach ($overrides as $k => $v) {
            if ($v === null || $v === '') {
                unset($params[$k]);
            } else {
                $params[$k] = $v;
            }
        }
        return '/reports/operator-waste?' . http_build_query(array_filter($params, static fn($v) => $v !== null && $v !== ''));
    };

    $excelUrl = '/reports/operator-waste/excel?' . http_build_query(array_filter([
        'filter_type' => $defaultFilterType,
        'period' => $periodYm,
        'start_date' => $rangeStartInput,
        'end_date' => $rangeEndInput,
        'process' => $filterProcess,
        'operator_id' => $filterOperatorId,
        'q' => $search,
    ], static fn($v) => $v !== null && $v !== ''));

    $body = '<div class="report-shell">';

    // Card Filtros Unificada tipo PANEL DE CONTROL Y PRODUCCIÓN ERP
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">👷 INFORME DE MERMA POR OPERADOR</div>';
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . ' · Desempeño, ranking y trazabilidad analítica de mermas por trabajador</div>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-actions">';
    $body .= '<a class="btn-filter-excel" href="' . h($excelUrl) . '">📥 Exportar Excel</a>';
    $body .= '<a class="btn-filter-secondary" href="/">← Volver al Dashboard</a>';
    $body .= '</div>';
    $body .= '</div>';

    // Barra de Filtros Form
    $body .= '<form id="op-filter-form" method="get" action="/reports/operator-waste" class="erp-filter-form">';

    // Tipo de filtro
    $body .= '<div class="erp-filter-field">';
    $body .= '<label for="filter_type_op">Tipo de filtro</label>';
    $body .= '<select id="filter_type_op" name="filter_type">';
    $body .= '<option value="period"' . ($defaultFilterType === 'period' ? ' selected' : '') . '>Período (26 al 25)</option>';
    $body .= '<option value="range"' . ($defaultFilterType === 'range' ? ' selected' : '') . '>Rango personalizado</option>';
    $body .= '</select>';
    $body .= '</div>';

    // Periodo
    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'period' ? '' : ' is-hidden') . '" data-filter-group="period">';
    $body .= '<label for="period_op">Mes del período</label>';
    $body .= '<input id="period_op" type="month" name="period" value="' . h($periodYm) . '"' . ($defaultFilterType === 'period' ? '' : ' disabled') . '>';
    $body .= '</div>';

    // Rango
    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="start_date_op">Fecha inicio</label>';
    $body .= '<input id="start_date_op" type="date" name="start_date" value="' . h($rangeStartInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="end_date_op">Fecha término</label>';
    $body .= '<input id="end_date_op" type="date" name="end_date" value="' . h($rangeEndInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    // Proceso
    $body .= '<div class="erp-filter-field" style="min-width:160px">';
    $body .= '<label for="proc_filter_op">Proceso productivo</label>';
    $body .= '<select id="proc_filter_op" name="process">';
    $body .= '<option value="">Todos los procesos</option>';
    foreach ($processes as $pCode => $pTitle) {
        $body .= '<option value="' . h($pCode) . '"' . ($filterProcess === $pCode ? ' selected' : '') . '>' . h($pTitle) . '</option>';
    }
    $body .= '</select>';
    $body .= '</div>';

    // Operador Selector
    $body .= '<div class="erp-filter-field" style="flex:1;min-width:200px">';
    $body .= '<label for="operator_filter_op">Operador específico</label>';
    $body .= '<select id="operator_filter_op" name="operator_id">';
    $body .= '<option value="">Todos los operadores (' . count($catalog) . ' activos)</option>';
    foreach ($catalog as $catOp) {
        $cId = (int)$catOp['id'];
        $cName = (string)$catOp['name'];
        $cRut = (string)($catOp['rut'] ?? '');
        $label = $cRut !== '' ? "{$cName} ({$cRut})" : $cName;
        $body .= '<option value="' . $cId . '"' . ($filterOperatorId === $cId ? ' selected' : '') . '>' . h($label) . '</option>';
    }
    $body .= '</select>';
    $body .= '</div>';

    // Buscador rápido
    $body .= '<div class="erp-filter-field" style="min-width:180px">';
    $body .= '<label for="search_op">Buscar texto</label>';
    $body .= '<input id="search_op" type="text" name="q" value="' . h((string)$search) . '" placeholder="Nombre, RUT...">';
    $body .= '</div>';

    $hasOpCustomFilters = ($filterProcess !== null || $filterOperatorId !== null || ($search !== null && $search !== ''));

    $body .= '<div style="display:flex;gap:8px;align-items:flex-end">';
    $body .= '<button type="submit" class="btn-filter-apply">🔍 Aplicar Filtros</button>';
    if ($hasOpCustomFilters) {
        $body .= '<a class="btn-filter-secondary" href="/reports/operator-waste" title="Limpiar filtros">✕ Limpiar</a>';
    }
    $body .= '</div>';

    $body .= '</form>';
    $body .= '</div>';


    // KPI Cards estándar del ERP
    $totalOps = (int)($summary['total_operators'] ?? count($operators));
    $totWasteU = (float)($summary['total_waste_units'] ?? 0.0);
    $totWasteKg = (float)($summary['total_waste_kg'] ?? 0.0);
    $totProdU = (float)($summary['total_produced_units'] ?? 0.0);
    $globalPct = (float)($summary['global_waste_percent'] ?? 0.0);
    $topOp = (string)($summary['top_operator'] ?? 'N/D');

    $body .= '<div class="card" style="border-radius:18px;border:1px solid #e2e8f0;padding:20px 24px;box-shadow:0 4px 16px rgba(15,23,42,.03)">';
    $body .= '<div class="kpi-grid" style="grid-template-columns:repeat(5,minmax(0,1fr))">';

    $body .= '<div class="kpi-card">';
    $body .= '<div class="kpi-label">Operadores Evaluados</div>';
    $body .= '<div class="kpi-value">' . $totalOps . '</div>';
    $body .= '<div class="kpi-sub">Trabajadores en rango</div>';
    $body .= '</div>';

    $body .= '<div class="kpi-card">';
    $body .= '<div class="kpi-label">Merma Total Unidades</div>';
    $body .= '<div class="kpi-value" style="color:#dc2626">' . h($fmtUnits($totWasteU)) . '</div>';
    $body .= '<div class="kpi-sub">Unidades descartadas</div>';
    $body .= '</div>';

    $body .= '<div class="kpi-card">';
    $body .= '<div class="kpi-label">Merma Total Kilos</div>';
    $body .= '<div class="kpi-value">' . h($fmtDec($totWasteKg)) . ' <span style="font-size:14px;font-weight:600">kg</span></div>';
    $body .= '<div class="kpi-sub">Peso total reportado</div>';
    $body .= '</div>';

    $body .= '<div class="kpi-card">';
    $body .= '<div class="kpi-label">% Merma General Planta</div>';
    $body .= '<div class="kpi-value" style="color:#00A9A6">' . h($fmtDec($globalPct)) . '%</div>';
    $body .= '<div class="kpi-sub">Base prod: ' . h($fmtUnits($totProdU)) . ' unid</div>';
    $body .= '</div>';

    $body .= '<div class="kpi-card">';
    $body .= '<div class="kpi-label">Mayor Incidencia</div>';
    $body .= '<div class="kpi-value" style="font-size:15px;line-height:1.2;margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' . h($topOp) . '">' . h($topOp) . '</div>';
    $body .= '<div class="kpi-sub">Mayor merma en kilos</div>';
    $body .= '</div>';

    $body .= '</div>';
    $body .= '</div>'; // Fin card 1

    // Card 2: Tabs de Procesos
    $body .= '<div class="card" style="padding:10px 14px">';
    $body .= '<div class="report-tabs" style="margin:0">';
    $body .= '<a class="report-tab-btn' . ($filterProcess === null ? ' active' : '') . '" href="' . h($buildUrl(['process' => null])) . '">Todos los Procesos</a>';
    $body .= '<a class="report-tab-btn' . ($filterProcess === 'impresion' ? ' active' : '') . '" href="' . h($buildUrl(['process' => 'impresion'])) . '">Impresión</a>';
    $body .= '<a class="report-tab-btn' . ($filterProcess === 'corte_sellado' ? ' active' : '') . '" href="' . h($buildUrl(['process' => 'corte_sellado'])) . '">Corte y Sellado</a>';
    $body .= '<a class="report-tab-btn' . ($filterProcess === 'embalaje' ? ' active' : '') . '" href="' . h($buildUrl(['process' => 'embalaje'])) . '">Embalaje</a>';
    $body .= '<a class="report-tab-btn' . ($filterProcess === 'pulpo' ? ' active' : '') . '" href="' . h($buildUrl(['process' => 'pulpo'])) . '">Pulpo Serigráfico</a>';
    $body .= '</div>';
    $body .= '</div>'; // Fin card 2

    // Card 3: Tabla Principal
    $body .= '<div class="card">';
    $body .= '<div class="erp-prod-table-wrap">';
    $body .= '<table class="erp-prod-table table-compact">';
    $body .= '<thead><tr>';
    $body .= '<th style="width:40px;text-align:center">#</th>';
    $body .= '<th style="text-align:left">Operador</th>';
    $body .= '<th style="text-align:center">RUT</th>';
    $body .= '<th style="text-align:center">Proceso Principal</th>';
    $body .= '<th style="text-align:center">OTs</th>';
    $body .= '<th style="text-align:right">Producción (unid)</th>';
    $body .= '<th style="text-align:right">Merma (unid)</th>';
    $body .= '<th style="text-align:right">Merma (kg)</th>';
    $body .= '<th style="text-align:center">% Merma</th>';
    $body .= '<th style="text-align:right">Participación</th>';
    $body .= '<th style="text-align:left">Mayor Causa</th>';
    $body .= '<th style="text-align:center;width:110px">Acción</th>';
    $body .= '</tr></thead><tbody>';

    if (empty($operators)) {
        $body .= '<tr><td colspan="12" class="text-center" style="padding:40px; color:#64748b; font-size:13px;">No se encontraron registros de merma para los filtros seleccionados.</td></tr>';
    } else {
        $rank = 0;
        foreach ($operators as $idx => $op) {
            $rank++;
            $opId = (int)$op['operator_id'];
            $opName = (string)$op['operator_name'];
            $opRut = (string)$op['operator_rut'];
            $mainProc = (string)$op['main_process'];
            $otCount = (int)$op['ot_count'];
            $prodU = (float)$op['produced_units'];
            $wUnits = (float)$op['waste_units'];
            $wKg = (float)$op['waste_kg'];
            $wPct = (float)$op['waste_percent'];
            $sharePct = (float)$op['share_percent'];
            $topDefect = (string)$op['top_defect_type'];
            $otsList = is_array($op['ots'] ?? null) ? $op['ots'] : [];

            $rateClass = $wPct > 5.0 ? 'red' : ($wPct > 2.0 ? 'yellow' : 'green');
            $opJsonData = htmlspecialchars(json_encode($otsList, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');

            $body .= '<tr>';
            $body .= '<td class="text-center" style="color:#64748b;font-weight:700">' . $rank . '</td>';
            $body .= '<td style="font-weight:700;color:#0f172a">' . h($opName) . '</td>';
            $body .= '<td class="text-center" style="font-family:monospace;color:#475569;font-size:11.5px">' . h($opRut ?: '—') . '</td>';
            $body .= '<td class="text-center"><span style="display:inline-block;padding:2px 8px;border-radius:4px;background:#f1f5f9;font-weight:600;font-size:11px;color:#334155">' . h($mainProc) . '</span></td>';
            $body .= '<td class="text-center" style="font-weight:700;color:#0284c7">' . $otCount . '</td>';
            $body .= '<td class="text-right" style="font-weight:600">' . ($prodU > 0 ? h($fmtUnits($prodU)) : '—') . '</td>';
            $body .= '<td class="text-right" style="font-weight:800;color:#dc2626">' . h($fmtUnits($wUnits)) . '</td>';
            $body .= '<td class="text-right" style="font-weight:700">' . h($fmtDec($wKg)) . ' kg</td>';
            $body .= '<td class="text-center"><span class="rate-badge ' . $rateClass . '">' . h($fmtDec($wPct)) . '%</span></td>';
            $body .= '<td class="text-right" style="font-weight:700;color:#0f172a">' . number_format($sharePct, 1, ',', '.') . '%</td>';
            $body .= '<td style="font-size:11.5px;color:#475569">' . h($topDefect) . '</td>';
            $body .= '<td class="text-center">';
            $body .= '<button type="button" class="btn secondary" style="padding:4px 10px;font-size:11.5px;font-weight:700" onclick="unibagOpenOperatorModal(\'' . h(addslashes($opName)) . '\', ' . $opJsonData . ')">Ver OTs (' . count($otsList) . ')</button>';
            $body .= '</td>';
            $body .= '</tr>';
        }
    }

    $body .= '</tbody></table>';
    $body .= '</div>';
    $body .= '</div>'; // Fin card 3

    // Modal de Detalle de OTs por Operador con estilos estándar del ERP
    $body .= '<div class="modal-backdrop" id="operator-ot-modal">';
    $body .= '<div class="modal-card" style="width:min(1100px,calc(100vw - 24px));max-height:85vh">';
    $body .= '<div class="modal-head">';
    $body .= '<div>';
    $body .= '<div class="modal-title" id="modal-op-title">Detalle de Merma por Operador</div>';
    $body .= '<div class="erp-prod-muted" id="modal-op-count" style="margin-top:2px">0 eventos de merma registrados</div>';
    $body .= '</div>';
    $body .= '<button type="button" class="btn secondary" style="padding:4px 10px;font-size:12px" onclick="unibagCloseOperatorModal()">✕</button>';
    $body .= '</div>';

    $body .= '<div class="modal-body">';
    $body .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:10px;flex-wrap:wrap">';
    $body .= '<div class="erp-prod-muted">Detalle por evento registrado en las órdenes de trabajo</div>';
    $body .= '<input type="text" id="modal-op-search" placeholder="Filtrar por OT, CC, máquina..." style="max-width:280px;height:34px;padding:4px 10px;font-size:12px" oninput="unibagFilterModalOts(this.value)">';
    $body .= '</div>';

    $body .= '<div class="erp-prod-table-wrap">';
    $body .= '<table class="erp-prod-table table-compact" id="modal-op-table">';
    $body .= '<thead><tr>';
    $body .= '<th style="text-align:center">N° OT</th>';
    $body .= '<th style="text-align:center">Centro Costo</th>';
    $body .= '<th style="text-align:left">Máquina</th>';
    $body .= '<th style="text-align:center">Proceso</th>';
    $body .= '<th style="text-align:left">Tipo Defecto</th>';
    $body .= '<th style="text-align:right">Merma (unid)</th>';
    $body .= '<th style="text-align:right">Merma (kg)</th>';
    $body .= '<th style="text-align:center">Fecha y Hora</th>';
    $body .= '</tr></thead>';
    $body .= '<tbody id="modal-op-tbody"></tbody>';
    $body .= '</table></div>';
    $body .= '</div>'; // Fin modal-body
    $body .= '</div>'; // Fin modal-card
    $body .= '</div>'; // Fin modal-backdrop

    $body .= '<script>
        var currentModalOts = [];
        function unibagOpenOperatorModal(operatorName, ots) {
            currentModalOts = ots || [];
            var titleEl = document.getElementById("modal-op-title");
            var countEl = document.getElementById("modal-op-count");
            if (titleEl) titleEl.innerText = "Detalle de Merma — " + operatorName;
            if (countEl) countEl.innerText = currentModalOts.length + " eventos de merma registrados";
            var searchInput = document.getElementById("modal-op-search");
            if (searchInput) searchInput.value = "";
            renderModalRows(currentModalOts);
            var modal = document.getElementById("operator-ot-modal");
            if (modal) {
                modal.classList.add("open");
                modal.style.display = "flex";
            }
        }
        function unibagCloseOperatorModal() {
            var modal = document.getElementById("operator-ot-modal");
            if (modal) {
                modal.classList.remove("open");
                modal.style.display = "none";
            }
        }
        function renderModalRows(list) {
            var tbody = document.getElementById("modal-op-tbody");
            if (!tbody) return;
            if (!list || list.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"8\" class=\"text-center\" style=\"padding:24px; color:#64748b;\">Sin registros de merma para este operador en el período.</td></tr>";
                return;
            }
            var html = "";
            for (var i = 0; i < list.length; i++) {
                var item = list[i];
                html += "<tr>";
                html += "<td class=\"text-center\" style=\"color:#2563eb; font-weight:800;\">" + (item.ot_number || "—") + "</td>";
                html += "<td class=\"text-center\" style=\"color:#475569;\">" + (item.cost_center || "—") + "</td>";
                html += "<td style=\"font-weight:600;\">" + (item.machine_label || "—") + "</td>";
                html += "<td class=\"text-center\"><span class=\"badge\" style=\"background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:11px;\">" + (item.process_title || "—") + "</span></td>";
                html += "<td style=\"font-weight:600; color:#b91c1c;\">" + (item.merma_title || "Merma") + "</td>";
                html += "<td class=\"text-right\" style=\"color:#dc2626; font-weight:800;\">" + Number(item.units || 0).toLocaleString("es-CL") + "</td>";
                html += "<td class=\"text-right\" style=\"font-weight:700;\">" + Number(item.kg || 0).toLocaleString("es-CL", {minimumFractionDigits:2, maximumFractionDigits:2}) + " kg</td>";
                html += "<td class=\"text-center\" style=\"color:#64748b; font-size:11px;\">" + (item.datetime || "—") + "</td>";
                html += "</tr>";
            }
            tbody.innerHTML = html;
        }
        function unibagFilterModalOts(q) {
            if (!q) {
                renderModalRows(currentModalOts);
                return;
            }
            q = q.toLowerCase();
            var filtered = currentModalOts.filter(function(it) {
                var ot = (it.ot_number || "").toLowerCase();
                var cc = (it.cost_center || "").toLowerCase();
                var m = (it.machine_label || "").toLowerCase();
                var t = (it.merma_title || "").toLowerCase();
                return ot.indexOf(q) !== -1 || cc.indexOf(q) !== -1 || m.indexOf(q) !== -1 || t.indexOf(q) !== -1;
            });
            renderModalRows(filtered);
        }
        var modalBackdrop = document.getElementById("operator-ot-modal");
        if (modalBackdrop) {
            modalBackdrop.addEventListener("click", function(e) {
                if (e.target === modalBackdrop) unibagCloseOperatorModal();
            });
        }
        window.addEventListener("keydown", function(e) {
            if (e.key === "Escape") unibagCloseOperatorModal();
        });
    </script>';

    $body .= '</div>'; // End report-shell

    render('Informe de Merma por Operador · ERP', $body);
}

/**
 * Exporta a Excel (.xlsx streaming) el informe consolidado y analítico de merma por operador.
 */
function unibagOutputOperatorWasteExcel(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    $start = $filters['start'];
    $end = $filters['end'];
    $activeFilterLabel = (string)$filters['active_filter_label'];

    $filterOperatorId = isset($_GET['operator_id']) && is_numeric($_GET['operator_id']) ? (int)$_GET['operator_id'] : null;
    $filterProcess = isset($_GET['process']) ? strtolower(trim((string)$_GET['process'])) : null;
    if ($filterProcess === '' || $filterProcess === 'all') {
        $filterProcess = null;
    }
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    $reportData = $service->getErpOperatorWasteReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $filterOperatorId,
        $filterProcess
    );

    $summary = $reportData['summary'];
    $operators = $reportData['operators'];

    if ($search !== null && $search !== '') {
        $searchLower = mb_strtolower($search, 'UTF-8');
        $operators = array_values(array_filter($operators, static function (array $op) use ($searchLower): bool {
            $name = mb_strtolower((string)($op['operator_name'] ?? ''), 'UTF-8');
            $rut = mb_strtolower((string)($op['operator_rut'] ?? ''), 'UTF-8');
            $proc = mb_strtolower((string)($op['main_process'] ?? ''), 'UTF-8');
            return str_contains($name, $searchLower) || str_contains($rut, $searchLower) || str_contains($proc, $searchLower);
        }));
    }

    $filename = 'informe-merma-operadores-' . date('Ymd-His') . '.xlsx';
    ob_start();

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Merma por Operador</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>';
    echo 'body{font-family:Calibri,Arial,sans-serif;font-size:11pt;color:#1e293b;background:#ffffff}';
    echo '.report-header{margin-bottom:16px}';
    echo '.report-title{font-size:16pt;font-weight:bold;color:#0f172a}';
    echo '.report-meta{font-size:10pt;color:#475569;margin-top:4px}';
    echo '.section-title{font-size:12pt;font-weight:bold;color:#0f172a;background:#f1f5f9;padding:6px 10px;border-left:4px solid #0284c7;margin:18px 0 8px 0}';
    echo 'table{border-collapse:collapse;width:100%;margin-bottom:18px}';
    echo 'th{background:#0f172a;color:#ffffff;font-weight:bold;font-size:10pt;padding:8px 10px;border:1px solid #94a3b8;vertical-align:middle}';
    echo 'td{font-size:10pt;padding:6px 10px;border:1px solid #cbd5e1;vertical-align:middle;color:#1e293b}';
    echo '.th-sub{background:#e2e8f0;color:#0f172a;font-weight:bold}';
    echo '.num-int{mso-number-format:"\#\,\#\#0";text-align:right}';
    echo '.num-dec{mso-number-format:"\#\,\#\#0\.00";text-align:right}';
    echo '.text-center{text-align:center}';
    echo '.text-right{text-align:right}';
    echo '.font-bold{font-weight:bold}';
    echo '.highlight-red{color:#dc2626;font-weight:bold}';
    echo '.row-even{background:#f8fafc}';
    echo '</style></head><body>';

    echo '<div class="report-header">';
    echo '<div class="report-title">Control de Calidad — Informe Consolidado de Merma por Operador</div>';
    echo '<div class="report-meta"><strong>Filtro aplicado:</strong> ' . h($activeFilterLabel) . ' | <strong>Generado el:</strong> ' . date('d/m/Y H:i') . '</div>';
    echo '</div>';

    // 1. Resumen
    echo '<div class="section-title">1. Indicadores Globales de Merma por Operador</div>';
    echo '<table>';
    echo '<tr><th class="th-sub" style="width:340px;text-align:left">Indicador</th><th class="th-sub" style="text-align:right;width:200px">Valor</th></tr>';
    echo '<tr><td>Total Operadores Evaluados</td><td class="num-int font-bold">' . count($operators) . '</td></tr>';
    echo '<tr class="row-even"><td>Total Merma Acumulada (unidades)</td><td class="num-int highlight-red">' . (int)round((float)($summary['total_waste_units'] ?? 0)) . '</td></tr>';
    echo '<tr><td>Total Merma Acumulada (kg)</td><td class="num-dec font-bold">' . round((float)($summary['total_waste_kg'] ?? 0), 2) . ' kg</td></tr>';
    echo '<tr class="row-even"><td>Total Base de Producción (unidades)</td><td class="num-int font-bold">' . (int)round((float)($summary['total_produced_units'] ?? 0)) . '</td></tr>';
    echo '<tr><td>% Merma Global de Planta</td><td class="text-right font-bold">' . number_format((float)($summary['global_waste_percent'] ?? 0), 2, ',', '.') . '%</td></tr>';
    echo '<tr class="row-even"><td>Mayor Incidencia Individual</td><td class="text-right font-bold highlight-red">' . h((string)($summary['top_operator'] ?? 'N/D')) . '</td></tr>';
    echo '</table>';

    // 2. Ranking de Operadores
    echo '<div class="section-title">2. Ranking y Desempeño Consolidado por Operador</div>';
    echo '<table>';
    echo '<thead><tr>';
    echo '<th>#</th><th>Operador</th><th>RUT</th><th>Proceso Principal</th><th style="text-align:center">OTs</th><th style="text-align:right">Base Producción</th><th style="text-align:right">Merma (unid)</th><th style="text-align:right">Merma (kg)</th><th style="text-align:right">% Merma</th><th style="text-align:right">% Participación</th><th>Mayor Causa</th>';
    echo '</tr></thead><tbody>';

    if (empty($operators)) {
        echo '<tr><td colspan="11" class="text-center">Sin registros de merma para los filtros indicados.</td></tr>';
    } else {
        $r = 0;
        foreach ($operators as $op) {
            $r++;
            $rowCls = ($r % 2 === 0) ? ' class="row-even"' : '';
            $prod = (float)($op['produced_units'] ?? 0);
            $wU = (float)($op['waste_units'] ?? 0);
            $wKg = (float)($op['waste_kg'] ?? 0);
            $rate = (float)($op['waste_percent'] ?? 0);
            $share = (float)($op['share_percent'] ?? 0);

            echo '<tr' . $rowCls . '>';
            echo '<td class="text-center">' . $r . '</td>';
            echo '<td class="font-bold">' . h((string)($op['operator_name'] ?? '')) . '</td>';
            echo '<td>' . h((string)($op['operator_rut'] ?? '')) . '</td>';
            echo '<td>' . h((string)($op['main_process'] ?? '')) . '</td>';
            echo '<td class="num-int">' . (int)($op['ot_count'] ?? 0) . '</td>';
            echo '<td class="num-int">' . ($prod > 0 ? (int)round($prod) : 'N/D') . '</td>';
            echo '<td class="num-int highlight-red">' . (int)round($wU) . '</td>';
            echo '<td class="num-dec">' . round($wKg, 2) . ' kg</td>';
            echo '<td class="text-right font-bold' . ($rate > 5.0 ? ' highlight-red' : '') . '">' . number_format($rate, 2, ',', '.') . '%</td>';
            echo '<td class="text-right">' . number_format($share, 1, ',', '.') . '%</td>';
            echo '<td>' . h((string)($op['top_defect_type'] ?? '')) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';

    // 3. Detalle de Eventos por Orden de Trabajo
    echo '<div class="section-title">3. Detalle Exhaustivo de Eventos de Merma por OT y Trabajador</div>';
    echo '<table>';
    echo '<thead><tr>';
    echo '<th>Operador</th><th>OT</th><th>Centro Costo</th><th>Máquina</th><th>Proceso</th><th>Tipo Defecto</th><th style="text-align:right">Merma (unid)</th><th style="text-align:right">Merma (kg)</th><th style="text-align:center">Fecha/Hora</th>';
    echo '</tr></thead><tbody>';

    $hasEvents = false;
    $idxEvent = 0;
    foreach ($operators as $op) {
        $opName = (string)($op['operator_name'] ?? '');
        $ots = is_array($op['ots'] ?? null) ? $op['ots'] : [];
        foreach ($ots as $item) {
            $hasEvents = true;
            $idxEvent++;
            $rowCls = ($idxEvent % 2 === 0) ? ' class="row-even"' : '';
            echo '<tr' . $rowCls . '>';
            echo '<td class="font-bold">' . h($opName) . '</td>';
            echo '<td class="font-bold">' . h((string)($item['ot_number'] ?? '')) . '</td>';
            echo '<td>' . h((string)($item['cost_center'] ?? '')) . '</td>';
            echo '<td>' . h((string)($item['machine_label'] ?? '')) . '</td>';
            echo '<td>' . h((string)($item['process_title'] ?? '')) . '</td>';
            echo '<td style="color:#b91c1c;">' . h((string)($item['merma_title'] ?? '')) . '</td>';
            echo '<td class="num-int highlight-red">' . (int)round((float)($item['units'] ?? 0)) . '</td>';
            echo '<td class="num-dec">' . round((float)($item['kg'] ?? 0), 2) . ' kg</td>';
            echo '<td class="text-center">' . h((string)($item['datetime'] ?? '')) . '</td>';
            echo '</tr>';
        }
    }

    if (!$hasEvents) {
        echo '<tr><td colspan="9" class="text-center">Sin eventos de merma registrados.</td></tr>';
    }

    echo '</tbody></table></body></html>';

    $html = ob_get_clean();
    SimpleXlsx::streamHtml($filename, $html, 'Merma por Operador');
}

