<?php
    $body = '<div class="rec-shell">';
    $body .= renderReceptionNavTabs($activeTab);

    // Header Card
    $body .= '<div class="rec-header-card">
        <div class="rec-title-group">
          <h1>' . h($pageTitle) . '</h1>
        </div>
        <div class="rec-counter-pill">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <span>' . $totalCount . ' ' . ($totalCount === 1 ? 'cargamento' : 'cargamentos') . '</span>
        </div>
      </div>';

    // Filter Card
    $clearUrl = '/import-containers?status=' . h($status);
    $exportParams = [
        'status' => $status,
    ];
    if ($supplierId > 0) $exportParams['supplier_id'] = (string)$supplierId;
    if ($poCode !== '') $exportParams['po_code'] = $poCode;
    if ($bl !== '') $exportParams['bl'] = $bl;
    if ($containerCode !== '') $exportParams['container_code'] = $containerCode;
    if ($productType !== '') $exportParams['product_type'] = $productType;
    if ($q !== '') $exportParams['q'] = $q;
    if ($dateFrom !== '') $exportParams['date_from'] = $dateFrom;
    if ($dateTo !== '') $exportParams['date_to'] = $dateTo;
    $exportUrl = '/import-containers/export-excel?' . http_build_query($exportParams);

    $body .= '<div class="rec-filter-card">
        <form method="get" action="/import-containers" class="rec-filter-form">
          <input type="hidden" name="status" value="' . h($status) . '">
          <div class="rec-filter-grid-imp">
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
        $cName = trim((string)($s['country_name'] ?? ''));
        $body .= '<option value="' . (int)$s['id'] . '"' . $selected . '>' . h((string)$s['name'] . ($cName !== '' ? ' (' . $cName . ')' : '')) . '</option>';
    }
    $body .= '</select>
            </div>
            <div class="rec-field">
              <label><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>Tipo de Producto</label>
              <input name="product_type" type="text" value="' . h($productType) . '" placeholder="Ej. PLA, W80, Bobina, SKU">
            </div>
            <div class="rec-field">
              <label><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="22" height="13" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>N° Contenedor</label>
              <input name="container_code" type="text" value="' . h($containerCode) . '" placeholder="Ej. FFAU7684870">
            </div>
            <div class="rec-field">
              <label><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>Bill of Lading (BL)</label>
              <input name="bl" type="text" value="' . h($bl) . '" placeholder="Ej. STL26055086">
            </div>
            <div class="rec-field">
              <label><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>Orden de Compra (OC)</label>
              <input name="po_code" type="text" value="' . h($poCode) . '" placeholder="Ej. OC0003963">
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
        <th>Contenedor</th><th>Bill of Lading</th><th>Embarcador</th><th>OCs Asociadas</th><th>Estado</th><th>Avance</th><th>ETA Planta</th><th style="text-align:right">Acción</th>
      </tr></thead><tbody>';
    foreach ($containers as $container) {
        $cLabel = trim((string)$container['container_code']);
        if ($cLabel === '') {
            $blVal = trim((string)$container['bill_of_lading']);
            $cLabel = $blVal !== '' ? ('BL ' . $blVal) : ('Cargamento #' . (int)$container['id']);
        }
        $cUrl = '/import-containers/' . (int)$container['id'];
        $containerProgress = renderReceptionProgressCol((int)($container['completed_lines'] ?? 0), (int)($container['total_lines'] ?? 0));
        $containerProgress .= '<div style="margin-top:6px;font-size:11px;line-height:1.35;color:#475569">'
            . '<div><strong>Recibido:</strong> '
            . h(formatReceptionValue((float)($container['received_rolls'] ?? 0), 'Unid.')) . ' / '
            . h(formatReceptionValue((float)($container['ordered_rolls'] ?? 0), 'Unid.')) . ' Unid.</div>'
            . '<div><strong>Peso:</strong> '
            . h(formatReceptionValue((float)($container['received_weight_kg'] ?? 0), 'Kg')) . ' / '
            . h(formatReceptionValue((float)($container['ordered_weight_kg'] ?? 0), 'Kg')) . ' Kg</div>'
            . '<div><strong>ERP:</strong> '
            . (((bool)($container['erp_is_complete'] ?? false))
                ? '<span style="color:#15803d;font-weight:700">Completo</span>'
                : '<span style="color:#b45309;font-weight:700">Pendiente</span>')
            . '</div>'
            . '</div>';
        $body .= '<tr>';
        $body .= '<td><a class="rec-code-link" href="' . h($cUrl) . '">' . h($cLabel) . '</a></td>';
        $body .= '<td>' . h((string)($container['bill_of_lading'] ?: '-')) . '</td>';
        $body .= '<td><span style="font-weight:600">' . h((string)($container['forwarder_name'] ?: '-')) . '</span></td>';
        $body .= '<td><span style="font-family:ui-monospace,monospace;font-size:12px;color:#0f766e">' . h((string)($container['po_codes'] ?: '-')) . '</span></td>';
        $body .= '<td>' . renderReceptionStatusBadge((string)$container['status']) . '</td>';
        $body .= '<td>' . $containerProgress . '</td>';
        $body .= '<td><span style="color:#64748b;font-size:12px">' . h((string)($container['eta_plant'] ?: '-')) . '</span></td>';
        $body .= '<td style="text-align:right"><a class="btn" style="padding:5px 12px;font-size:12px" href="' . h($cUrl) . '">' . ($isComplete ? 'Ver detalle' : 'Recepcionar') . '</a></td>';
        $body .= '</tr>';
    }
    if ($containers === []) {
        $body .= '<tr><td colspan="8"><div class="rec-empty">
          <div class="rec-empty-icon">🚢</div>
          <div style="font-weight:700;font-size:14px;color:#334155;margin-bottom:4px">No se encontraron contenedores o cargamentos</div>
          <div style="font-size:12px">Intenta modificar los filtros de búsqueda o fecha seleccionados.</div>
        </div></td></tr>';
    }
    $body .= '</tbody></table></div>';

    $queryParams = [
        'status' => $status,
    ];
    if ($supplierId > 0) $queryParams['supplier_id'] = $supplierId;
    if ($poCode !== '') $queryParams['po_code'] = $poCode;
    if ($bl !== '') $queryParams['bl'] = $bl;
    if ($containerCode !== '') $queryParams['container_code'] = $containerCode;
    if ($productType !== '') $queryParams['product_type'] = $productType;
    if ($q !== '') $queryParams['q'] = $q;
    if ($dateFrom !== '') $queryParams['date_from'] = $dateFrom;
    if ($dateTo !== '') $queryParams['date_to'] = $dateTo;

    $body .= renderReceptionPagination($page, $totalPages, $totalCount, $pageSize, '/import-containers', $queryParams);
    $body .= '</div>'; // Closes .rec-table-card
    $body .= '</div>';


return $body;
