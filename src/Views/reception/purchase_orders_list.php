<?php
    $body = '<div class="rec-shell">';
    $body .= renderReceptionNavTabs($activeTab);

    // Header Card
    $body .= '<div class="rec-header-card">
        <div class="rec-title-group">
          <h1>' . h($pageTitle) . '</h1>
          <p>' . h($pageSubtitle) . '</p>
        </div>
        <div class="rec-counter-pill">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
          <span>' . $totalCount . ' ' . ($totalCount === 1 ? 'orden' : 'órdenes') . '</span>
        </div>
      </div>';

    // Filter Card
    $clearUrl = '/purchase-orders?status=' . h($status) . '&supplier_type=NATIONAL';
    $exportParams = [
        'status' => $status,
        'supplier_type' => 'NATIONAL',
    ];
    if ($supplierId > 0) $exportParams['supplier_id'] = (string)$supplierId;
    if ($q !== '') $exportParams['q'] = $q;
    if ($dateFrom !== '') $exportParams['date_from'] = $dateFrom;
    if ($dateTo !== '') $exportParams['date_to'] = $dateTo;
    $exportUrl = '/purchase-orders/export-excel?' . http_build_query($exportParams);

    $body .= '<div class="rec-filter-card">
        <form method="get" action="/purchase-orders" class="rec-filter-form">
          <input type="hidden" name="status" value="' . h($status) . '">
          <input type="hidden" name="supplier_type" value="' . h($supplierType) . '">
          <div class="rec-filter-grid-nat">
            <div class="rec-field">
              <label><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Fecha Inicio</label>
              <input type="date" name="date_from" value="' . h($dateFrom) . '">
            </div>
            <div class="rec-field">
              <label><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Fecha Final</label>
              <input type="date" name="date_to" value="' . h($dateTo) . '">
            </div>
            <div class="rec-field">
              <label><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>Proveedor</label>
              <select name="supplier_id">
                <option value="">Todos los proveedores</option>';
    foreach ($suppliers as $s) {
        $selected = ((int)$s['id'] === $supplierId) ? ' selected' : '';
        $body .= '<option value="' . (int)$s['id'] . '"' . $selected . '>' . h((string)$s['name']) . '</option>';
    }
    $body .= '</select>
            </div>
            <div class="rec-field">
              <label><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>Orden de Compra (OC)</label>
              <input name="q" type="text" value="' . h($q) . '" placeholder="Ej. OC0004207">
            </div>
            <div class="rec-actions">
              <button class="rec-btn-filter" type="submit">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Aplicar Filtros
              </button>
              <a href="' . h($clearUrl) . '" class="rec-btn-clear">✕ Limpiar</a>
              <a href="' . h($exportUrl) . '" class="rec-btn-excel" title="Descargar informe en Excel">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Exportar Excel
              </a>
            </div>
          </div>
        </form>
      </div>';

    // Table Card
    $body .= '<div class="rec-table-card"><div class="rec-table-wrap"><table class="rec-table"><thead><tr>
        <th>Orden de Compra</th><th>Proveedor</th><th>País</th><th>Estado</th><th>Avance</th><th>Fecha Emisión</th><th style="text-align:right">Acción</th>
      </tr></thead><tbody>';
    foreach ($pos as $po) {
        $completedLines = (int)($po['completed_lines'] ?? 0);
        $totalLines = (int)($po['total_lines'] ?? 0);
        $poUrl = '/purchase-orders/' . (int)$po['id'];
        $body .= '<tr>';
        $body .= '<td><a class="rec-code-link" href="' . h($poUrl) . '">' . h((string)$po['po_code']) . '</a></td>';
        $body .= '<td><span style="font-weight:600">' . h((string)$po['supplier_name']) . '</span></td>';
        $body .= '<td>' . h((string)($po['supplier_country_name'] ?? '-')) . '</td>';
        $body .= '<td>' . renderReceptionStatusBadge((string)$po['status']) . '</td>';
        $body .= '<td>' . renderReceptionProgressCol($completedLines, $totalLines) . '</td>';
        $body .= '<td><span style="color:#64748b;font-size:12px">' . h((string)$po['created_at']) . '</span></td>';
        $body .= '<td style="text-align:right"><a class="btn" style="padding:5px 12px;font-size:12px" href="' . h($poUrl) . '">' . ($isComplete ? 'Ver detalle' : 'Recepcionar') . '</a></td>';
        $body .= '</tr>';
    }
    if ($pos === []) {
        $body .= '<tr><td colspan="7"><div class="rec-empty">
          <div class="rec-empty-icon">📦</div>
          <div style="font-weight:700;font-size:14px;color:#334155;margin-bottom:4px">No se encontraron órdenes de compra</div>
          <div style="font-size:12px">Intenta modificar los filtros de búsqueda o fecha seleccionados.</div>
        </div></td></tr>';
    }
    $body .= '</tbody></table></div>';

    $queryParams = [
        'status' => $status,
        'supplier_type' => $supplierType,
    ];
    if ($supplierId > 0) $queryParams['supplier_id'] = $supplierId;
    if ($q !== '') $queryParams['q'] = $q;
    if ($dateFrom !== '') $queryParams['date_from'] = $dateFrom;
    if ($dateTo !== '') $queryParams['date_to'] = $dateTo;

    $body .= renderReceptionPagination($page, $totalPages, $totalCount, $pageSize, '/purchase-orders', $queryParams);
    $body .= '</div>'; // Closes .rec-table-card
    $body .= '</div>';


return $body;
