<?php

declare(strict_types=1);

function unibagRenderErpDashboardPage(ReceptionService $service): void
{
    $currentArea = normalizeErpArea((string)($_SESSION['erp_area'] ?? 'ERP'));
    if ($currentArea === 'PRODUCTION') {
        redirectResponse('/production/shifts');
    }
    if ($currentArea === 'RECEPTION') {
        redirectResponse('/purchase-orders?status=active&supplier_type=NATIONAL');
    }

    $summary = $service->getErpDashboardSummary();
    $recentTraceability = $service->listDashboardRecentTraceability(8);
    $recentEvents = $service->listRecentOperationalEvents(8);

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Panel ERP</div>
          <div class="muted">Informes ejecutivos y trazabilidad completa, sin accesos operativos de máquinas o turnos.</div>
        </div>
      </div>';

    $body .= '<div class="kpi-grid" style="margin-bottom:12px">';
    $body .= '<div class="kpi-card"><div class="kpi-label">OT activas</div><div class="kpi-value">' . h((string)$summary['work_orders']['active']) . '</div><div class="kpi-sub">En producción ahora</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">OT en corte</div><div class="kpi-value">' . h((string)$summary['work_orders']['cutting']) . '</div><div class="kpi-sub">Impresas y pendientes de cierre</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Bobinas listas corte</div><div class="kpi-value">' . h((string)$summary['rolls']['ready_for_cut']) . '</div><div class="kpi-sub">Salida de impresión disponible</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Cajas / pallets</div><div class="kpi-value">' . h((string)$summary['packaging']['boxes']) . ' / ' . h((string)$summary['packaging']['pallets']) . '</div><div class="kpi-sub">Empaque total generado</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">OT fabricadas</div><div class="kpi-value">' . h((string)($summary['work_orders']['completed'] ?? $summary['work_orders']['closed'] ?? 0)) . '</div><div class="kpi-sub">Órdenes cerradas correctamente</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Bobinas bloqueadas</div><div class="kpi-value">' . h((string)($summary['rolls']['blocked'] ?? 0)) . '</div><div class="kpi-sub">Revisión o contingencia operativa</div></div>';
    $body .= '</div>';

    $body .= '<div class="dashboard-grid" style="margin-bottom:12px">';
    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Accesos rápidos</div>';
    $body .= '<div class="trace-grid">';
    $body .= '<div class="kpi-card"><div class="kpi-label">Informes</div><div style="font-weight:800;margin-bottom:8px">Histórico de inventarios por bodega</div><a class="btn secondary" href="/reports/inventory">Ver informe</a></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Trazabilidad</div><div style="font-weight:800;margin-bottom:8px">Órdenes y avance por etapa</div><a class="btn secondary" href="/work-orders?view=pending">Ver órdenes</a></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Corte</div><div style="font-weight:800;margin-bottom:8px">Seguimiento de bobinas, cajas y pallets</div><a class="btn secondary" href="/cut">Ver corte</a></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Tintas</div><div style="font-weight:800;margin-bottom:8px">Pesajes y consumo por OT</div><a class="btn secondary" href="/chemicals/weighings">Ver pesajes</a></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Pallets</div><div style="font-weight:800;margin-bottom:8px">Seguimiento de salida final</div><a class="btn secondary" href="/pallets">Ver pallets</a></div>';
    $body .= '</div></div>';
    $body .= '</div>';

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Actividad reciente de trazabilidad</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Fecha</th><th>Evento</th><th>Detalle</th></tr></thead><tbody>';
    foreach ($recentEvents as $event) {
        $payload = $event['payload_data'] ?? [];
        $detailParts = [];
        if (isset($payload['work_order_id']) && (int)$payload['work_order_id'] > 0) { $detailParts[] = 'OT #' . (int)$payload['work_order_id']; }
        if (isset($payload['roll_id']) && (int)$payload['roll_id'] > 0) { $detailParts[] = 'Bobina #' . (int)$payload['roll_id']; }
        if (isset($payload['output_roll_id']) && (int)$payload['output_roll_id'] > 0) { $detailParts[] = 'Salida #' . (int)$payload['output_roll_id']; }
        if (isset($payload['operator_name']) && (string)$payload['operator_name'] !== '') { $detailParts[] = 'Operador ' . (string)$payload['operator_name']; }
        $body .= '<tr>';
        $body .= '<td>' . h((string)$event['created_at']) . '</td>';
        $body .= '<td>' . h(eventTypeLabel((string)$event['type'])) . '</td>';
        $body .= '<td>' . h($detailParts === [] ? '-' : implode(' · ', $detailParts)) . '</td>';
        $body .= '</tr>';
    }
    if ($recentEvents === []) {
        $body .= '<tr><td colspan="3" class="muted">Sin actividad reciente.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    $body .= '</div>';

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Trazabilidad reciente</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>OT</th><th>Bobina entrada</th><th>Bobina salida</th><th>Etapa</th><th>Cajas</th><th>Pallets</th><th></th></tr></thead><tbody>';
    foreach ($recentTraceability as $traceRow) {
        $traceStageLabel = rollProcessStageLabel((string)($traceRow['process_stage'] ?? ''));
        $traceStatusLabel = rollStatusLabel((string)($traceRow['status'] ?? ''));
        $body .= '<tr>';
        $traceWorkOrderLabel = (string)($traceRow['ot_code'] ?? ('OT #' . (int)($traceRow['work_order_id'] ?? 0)));
        $body .= '<td>' . ((int)($traceRow['work_order_id'] ?? 0) > 0
            ? (canAccessWorkOrderTraceability()
                ? '<a href="/work-orders/' . (int)$traceRow['work_order_id'] . '/traceability">' . h($traceWorkOrderLabel) . '</a>'
                : h($traceWorkOrderLabel))
            : '-') . '</td>';
        $body .= '<td>' . h((string)($traceRow['parent_roll_code'] ?? '-')) . '</td>';
        $body .= '<td><a href="/rolls/' . (int)$traceRow['id'] . '">' . h((string)$traceRow['roll_code']) . '</a></td>';
        $body .= '<td>' . h($traceStageLabel) . ' / ' . h($traceStatusLabel) . '</td>';
        $body .= '<td>' . h((string)($traceRow['box_count'] ?? '0')) . '</td>';
        $body .= '<td>' . h((string)($traceRow['pallet_count'] ?? '0')) . '</td>';
        $body .= '<td><a class="btn secondary" href="/rolls/' . (int)$traceRow['id'] . '">Ver</a></td>';
        $body .= '</tr>';
    }
    if ($recentTraceability === []) {
        $body .= '<tr><td colspan="7" class="muted">Aún no hay bobinas de salida generadas.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';

    render('ERP', $body);
}

function unibagRenderInventoryReportsPage(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $inventoryCounts = $service->listInventoryCounts(200);
    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Informe de inventario</div>
          <div class="muted">Histórico de inventarios realizados por bodega y acceso a su Excel.</div>
        </div>
        <a class="btn secondary" href="/">Volver al panel ERP</a>
      </div>';

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Inventarios realizados</div>';
    $body .= '<table><thead><tr><th>Fecha</th><th>Bodega</th><th>Nombre bodega</th><th>SKUs</th><th>Sistema</th><th>Fisico</th><th>Dif</th><th>Realizado por</th><th></th></tr></thead><tbody>';
    foreach ($inventoryCounts as $inventoryCount) {
        $inventoryId = (int)($inventoryCount['id'] ?? 0);
        $body .= '<tr>';
        $body .= '<td>' . h((string)($inventoryCount['created_at'] ?? '')) . '</td>';
        $body .= '<td>' . h((string)($inventoryCount['warehouse_code'] ?? '')) . '</td>';
        $body .= '<td>' . h((string)($inventoryCount['warehouse_name'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($inventoryCount['total_skus'] ?? '0')) . '</td>';
        $body .= '<td>' . h(number_format((float)($inventoryCount['total_system_qty'] ?? 0), 3, '.', '')) . '</td>';
        $body .= '<td>' . h(number_format((float)($inventoryCount['total_physical_qty'] ?? 0), 3, '.', '')) . '</td>';
        $body .= '<td>' . h(number_format((float)($inventoryCount['total_diff_qty'] ?? 0), 3, '.', '')) . '</td>';
        $body .= '<td>' . h((string)($inventoryCount['created_by'] ?? '-')) . '</td>';
        $body .= '<td><div class="row"><a class="btn secondary" href="/reports/inventory/' . $inventoryId . '">Ver</a><a class="btn secondary" href="/reports/inventory/' . $inventoryId . '/excel">Descargar Excel</a></div></td>';
        $body .= '</tr>';
    }
    if ($inventoryCounts === []) {
        $body .= '<tr><td colspan="9" class="muted">Aún no hay inventarios realizados.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    render('Informe inventario', $body);
}

function unibagOutputInventoryReportExcel(ReceptionService $service, int $inventoryCountId): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $inventoryCount = $service->getInventoryCount($inventoryCountId);
    if ($inventoryCount === null) {
        render('No encontrado', '<div class="card">Inventario no encontrado.</div>');
        exit;
    }
    $rows = $service->listInventoryCountItems($inventoryCountId);
    outputInventoryCountDetailExcel(
        'informe-inventario-bodega-' . (int)($inventoryCount['warehouse_code'] ?? 0) . '-registro-' . $inventoryCountId . '.xls',
        $rows,
        $inventoryCount
    );
}

function unibagRenderInventoryReportDetailPage(ReceptionService $service, int $inventoryCountId): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $inventoryCount = $service->getInventoryCount($inventoryCountId);
    if ($inventoryCount === null) {
        render('No encontrado', '<div class="card">Inventario no encontrado.</div>');
        return;
    }
    $items = $service->listInventoryCountItems($inventoryCountId);

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Inventario realizado</div>
          <div class="muted">Bodega ' . h((string)($inventoryCount['warehouse_code'] ?? '')) . ' · ' . h((string)($inventoryCount['warehouse_name'] ?? '')) . '</div>
        </div>
        <div class="row">
          <a class="btn secondary" href="/reports/inventory">Volver al informe</a>
          <a class="btn secondary" href="/reports/inventory/' . $inventoryCountId . '/excel">Descargar Excel</a>
        </div>
      </div>';

    $body .= '<div class="card" style="margin-bottom:12px"><div class="row">'
        . '<div style="flex:1;min-width:180px"><div class="muted">Fecha</div><div style="font-weight:800">' . h((string)($inventoryCount['created_at'] ?? '')) . '</div></div>'
        . '<div style="flex:1;min-width:180px"><div class="muted">Realizado por</div><div style="font-weight:800">' . h((string)($inventoryCount['created_by'] ?? '-')) . '</div></div>'
        . '<div style="flex:1;min-width:180px"><div class="muted">SKUs</div><div style="font-weight:800">' . h((string)($inventoryCount['total_skus'] ?? '0')) . '</div></div>'
        . '<div style="flex:1;min-width:180px"><div class="muted">Sistema</div><div style="font-weight:800">' . h(number_format((float)($inventoryCount['total_system_qty'] ?? 0), 3, '.', '')) . '</div></div>'
        . '<div style="flex:1;min-width:180px"><div class="muted">Fisico</div><div style="font-weight:800">' . h(number_format((float)($inventoryCount['total_physical_qty'] ?? 0), 3, '.', '')) . '</div></div>'
        . '<div style="flex:1;min-width:180px"><div class="muted">Diferencia</div><div style="font-weight:800">' . h(number_format((float)($inventoryCount['total_diff_qty'] ?? 0), 3, '.', '')) . '</div></div>'
        . '</div></div>';

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Detalle del inventario</div>';
    $body .= '<table><thead><tr><th>Numero</th><th>Articulo</th><th>Familia</th><th>Cod. color</th><th>Alto</th><th>Gramos</th><th>Metros</th><th>Unidad</th><th>Bodega</th><th>Sistema</th><th>Fisico</th><th>Dif</th></tr></thead><tbody>';
    foreach ($items as $item) {
        $body .= '<tr>';
        $body .= '<td>' . h((string)($item['sku_code'] ?? '')) . '</td>';
        $body .= '<td>' . h((string)($item['article_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($item['family_color'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($item['color_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($item['height_mm'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($item['grams'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($item['meters'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($item['unit_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($inventoryCount['warehouse_code'] ?? '')) . ' (' . h((string)($inventoryCount['warehouse_name'] ?? '-')) . ')</td>';
        $body .= '<td>' . h(number_format((float)($item['system_qty'] ?? 0), 3, '.', '')) . '</td>';
        $body .= '<td>' . h(number_format((float)($item['physical_qty'] ?? 0), 3, '.', '')) . '</td>';
        $body .= '<td>' . h(number_format((float)($item['diff_qty'] ?? 0), 3, '.', '')) . '</td>';
        $body .= '</tr>';
    }
    if ($items === []) {
        $body .= '<tr><td colspan="12" class="muted">Este inventario no registró stock disponible.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    render('Detalle inventario', $body);
}

function handleErpReportRoutes(string $path, string $method, ReceptionService $service): bool
{
    if ($path === '/' && $method === 'GET') {
        unibagRenderErpDashboardPage($service);
        return true;
    }

    if ($path === '/reports/inventory' && $method === 'GET') {
        unibagRenderInventoryReportsPage($service);
        return true;
    }

    if (preg_match('#^/reports/inventory/(\d+)/excel$#', $path, $matches) === 1 && $method === 'GET') {
        unibagOutputInventoryReportExcel($service, (int)$matches[1]);
        return true;
    }

    if (preg_match('#^/reports/inventory/(\d+)$#', $path, $matches) === 1 && $method === 'GET') {
        unibagRenderInventoryReportDetailPage($service, (int)$matches[1]);
        return true;
    }

    return false;
}
