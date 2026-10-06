<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Inventario (Conexión directa ERP unibag_unibag)
//
// Este módulo implementa la gestión de inventario conectada a la base de datos
// de ERP (unibag_unibag):
// - Consulta de stock por bodega ERP (/stock)
// - Descarga Excel de stock disponible
// - Toma de inventario / recuento físico por bodega (/stock/inventory-counts)
//   con persistencia directa en las tablas de stockcounts de ERP.
// - Visualización de la ocupación física (pallets/cajas/bobinas) desde TRZ.
// =============================================================================

/**
 * Genera las opciones HTML (<option>) para el selector de bodegas ERP.
 *
 * @param array<int, array{id:int, name:string, desc:string, clasificacion:string, capacidad:int}> $storehouses
 */
function unibagInventoryStorehouseOptionsHtml(array $storehouses, int $selectedId): string
{
    $html = '';
    foreach ($storehouses as $st) {
        $id = (int)($st['id'] ?? 0);
        $name = trim((string)($st['name'] ?? ''));
        $clasif = trim((string)($st['clasificacion'] ?? ''));
        $extra = $clasif !== '' ? ' (' . $clasif . ')' : '';
        $selected = $id === $selectedId ? ' selected' : '';
        $html .= '<option value="' . $id . '"' . $selected . '>'
            . h($name !== '' ? $name . $extra : 'Bodega #' . $id)
            . '</option>';
    }

    return $html;
}

/**
 * Emite la planilla Excel con el stock de la bodega en formato XLS HTML.
 *
 * @param array<int, array{item_id:int, item_number_prod:string, item_title:string, unit_name:string, iss_inventory:float, iss_inventory_reserved:float, iss_inventory_min:float, available_qty:float}> $items
 */
function unibagOutputErpStockExcel(string $filename, array $items, string $storehouseName): void
{
    ob_start();
    echo '<html><head><meta charset="UTF-8"><style>';
    echo 'body{font-family:Arial,sans-serif;color:#0f172a}';
    echo 'table{border-collapse:collapse;width:100%}';
    echo 'th,td{border:1px solid #cbd5e1;padding:6px 8px}';
    echo 'th{background:#0f172a;color:#fff;font-weight:700;text-align:center}';
    echo 'tr:nth-child(even){background:#f8fafc}';
    echo '.num-int{mso-number-format:"\#\,\#\#0";text-align:right}';
    echo '.num-dec3{mso-number-format:"\#\,\#\#0\.000";text-align:right}';
    echo '.text-cell{mso-number-format:"\@"}';
    echo '</style></head><body>';
    echo '<table border="1">';
    echo '<tr><th colspan="7" style="font-size:14px;padding:10px;background:#1e293b;color:#fff">Inventario de Stock ERP - ' . h($storehouseName) . ' (' . date('d.m.Y H:i') . ')</th></tr>';
    echo '<tr><th>Código</th><th>Artículo / Descripción</th><th>Unidad</th><th>Stock actual</th><th>Reservado</th><th>Disponible</th><th>Mínimo</th></tr>';
    foreach ($items as $item) {
        $inv = (float)($item['iss_inventory'] ?? 0);
        $res = (float)($item['iss_inventory_reserved'] ?? 0);
        $disp = (float)($item['available_qty'] ?? 0);
        $min = (float)($item['iss_inventory_min'] ?? 0);
        echo '<tr>';
        echo '<td class="text-cell">' . h((string)($item['item_number_prod'] ?? '')) . '</td>';
        echo '<td>' . h((string)($item['item_title'] ?? '')) . '</td>';
        echo '<td class="text-cell" style="text-align:center">' . h((string)($item['unit_name'] ?? 'UNID')) . '</td>';
        echo '<td class="num-dec3">' . number_format($inv, 2, '.', '') . '</td>';
        echo '<td class="num-dec3">' . number_format($res, 2, '.', '') . '</td>';
        echo '<td class="num-dec3">' . number_format($disp, 2, '.', '') . '</td>';
        echo '<td class="num-dec3">' . number_format($min, 2, '.', '') . '</td>';
        echo '</tr>';
    }
    if ($items === []) {
        echo '<tr><td colspan="7" style="text-align:center;padding:12px;color:#64748b">Sin artículos registrados en esta bodega.</td></tr>';
    }
    // Fila de TOTALES al final del Excel
    if ($items !== []) {
        $exSumStock = 0.0;
        $exSumRes   = 0.0;
        $exSumDisp  = 0.0;
        $exSumMin   = 0.0;
        foreach ($items as $it) {
            $exSumStock += (float)($it['iss_inventory'] ?? 0);
            $exSumRes   += (float)($it['iss_inventory_reserved'] ?? 0);
            $exSumDisp  += (float)($it['available_qty'] ?? 0);
            $exSumMin   += (float)($it['iss_inventory_min'] ?? 0);
        }
        echo '<tr style="background:#0f172a;color:#fff;font-weight:700">';
        echo '<td colspan="3" style="padding:8px;text-align:right;color:#fff;font-weight:700">TOTAL (' . count($items) . ' artículos)</td>';
        echo '<td class="num-dec3" style="color:#fff;font-weight:700">' . number_format($exSumStock, 2, '.', '') . '</td>';
        echo '<td class="num-dec3" style="color:#fff;font-weight:700">' . number_format($exSumRes, 2, '.', '') . '</td>';
        echo '<td class="num-dec3" style="color:#fff;font-weight:700">' . number_format($exSumDisp, 2, '.', '') . '</td>';
        echo '<td class="num-dec3" style="color:#fff;font-weight:700">' . number_format($exSumMin, 2, '.', '') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</body></html>';
    $html = ob_get_clean();

    SimpleXlsx::streamHtml($filename, $html, 'Inventario');
}

/**
 * Renderiza la pantalla principal de stock por bodega conectada al ERP unibag_unibag.
 */
function unibagRenderStockPage(ReceptionService $service): void
{
    // Listar solo las bodegas que tienen inventario registrado
    $storehouses = $service->listErpStorehouses(true);
    $requestedStId = isset($_GET['bodega']) ? (int)$_GET['bodega'] : 0;
    $search = trim((string)($_GET['search'] ?? ''));
    // Por defecto mostrar solo los que tienen inventario (stock > 0)
    $onlyWithStock = !isset($_GET['only_stock']) || (string)$_GET['only_stock'] === '1';

    $selectedStorehouse = null;
    if ($requestedStId > 0) {
        foreach ($storehouses as $st) {
            if ((int)$st['id'] === $requestedStId) {
                $selectedStorehouse = $st;
                break;
            }
        }
    }
    if ($selectedStorehouse === null && $storehouses !== []) {
        $selectedStorehouse = $storehouses[0];
        $requestedStId = (int)$selectedStorehouse['id'];
    }

    $storehouseId = $selectedStorehouse !== null ? (int)$selectedStorehouse['id'] : 0;
    $storehouseName = $selectedStorehouse !== null ? (string)$selectedStorehouse['name'] : 'Sin bodegas';
    $storehouseDesc = $selectedStorehouse !== null ? (string)$selectedStorehouse['desc'] : '';
    $storehouseClasif = $selectedStorehouse !== null ? (string)$selectedStorehouse['clasificacion'] : '';

    $items = [];
    if ($storehouseId > 0) {
        $items = $service->getErpStockByStorehouse($storehouseId, $search !== '' ? $search : null, $onlyWithStock);
    }

    if (isset($_GET['download']) && (string)$_GET['download'] === 'excel') {
        unibagOutputErpStockExcel(
            'inventario-erp-bodega-' . $storehouseId . '-' . date('Ymd-His') . '.xlsx',
            $items,
            $storehouseName
        );
    }

    // Estadísticas del stock ERP consultado
    $totalItems = count($items);
    $itemsWithStock = 0;
    $sumStock = 0.0;
    $sumReserved = 0.0;
    $sumAvailable = 0.0;
    foreach ($items as $it) {
        $inv = (float)($it['iss_inventory'] ?? 0);
        $res = (float)($it['iss_inventory_reserved'] ?? 0);
        $disp = (float)($it['available_qty'] ?? 0);
        if ($inv > 0) {
            $itemsWithStock++;
        }
        $sumStock += $inv;
        $sumReserved += $res;
        $sumAvailable += $disp;
    }

    // Mapa de trazabilidad ya registrada para verificar qué artículos están 100% rotulados
    $stockTaggedMap = $storehouseId > 0 ? $service->getWarehouseTraceabilityTaggedQuantities($storehouseId) : [];
    $countTrzFullyTagged = 0;
    $countTrzPending = 0;
    foreach ($items as $it) {
        $invCheck = (float)($it['iss_inventory'] ?? 0);
        $idCheck = (int)$it['item_id'];
        $skuCheck = strtoupper(trim((string)$it['item_number_prod']));
        $taggedCheck = (float)($stockTaggedMap['id_' . $idCheck] ?? ($stockTaggedMap['code_' . $skuCheck] ?? 0.0));
        if ($invCheck > 0) {
            if ($taggedCheck >= ($invCheck - 0.0001)) {
                $countTrzFullyTagged++;
            } else {
                $countTrzPending++;
            }
        }
    }

    // Métricas de ocupación física en Trazabilidad para esta bodega
    $trzPallets = 0;
    $trzBoxes = 0;
    $trzRolls = 0;
    $trzCapacityPallets = 0;
    $trzOccupancy = null;
    try {
        $trzWhList = $service->listWarehousesWithCapacities();
        foreach ($trzWhList as $tw) {
            if ((int)($tw['erp_storehouse_id'] ?? 0) === $storehouseId || (string)($tw['code'] ?? '') === (string)$storehouseId) {
                $trzPallets = (int)($tw['pallets_count'] ?? 0);
                $trzBoxes = (int)($tw['boxes_count'] ?? 0);
                $trzRolls = (int)($tw['rolls_count'] ?? 0);
                $trzCapacityPallets = (int)($tw['capacity_pallets'] ?? 0);
                $trzOccupancy = $tw['occupancy_percent'];
                break;
            }
        }
    } catch (Throwable) {}

    $stockMessage = trim((string)($_GET['msg'] ?? ''));
    $stockError = trim((string)($_GET['error'] ?? ''));

    $body = '<div class="erp-prod-shell" style="max-width:1440px;margin:0 auto">';

    $body .= '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:16px">
        <div>
          <div style="font-size:22px;font-weight:800;color:#0f172a;letter-spacing:-.02em">Inventario de Bodegas · ERP</div>
          <div class="muted" style="font-size:13px;margin-top:2px">
            Conexión directa a base de datos ERP (<code>unibag_unibag</code>) · Ocupación física administrada en Trazabilidad.
          </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <a class="btn" href="/stock/traceability-entry?bodega=' . $storehouseId . '" style="background:#00A9A6;display:inline-flex;align-items:center;gap:6px">
            <span style="font-size:15px">📦</span> Ingresar con Trazabilidad
          </a>
          <a class="btn secondary" href="/reports/inventory" style="display:inline-flex;align-items:center;gap:6px">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Informe de inventario
          </a>
          <a class="btn secondary" href="/stock/inventory-counts?bodega=' . $storehouseId . '" style="display:inline-flex;align-items:center;gap:6px">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
            Toma de inventario
          </a>
        </div>
      </div>';

    if ($stockMessage !== '') {
        $body .= '<div class="ok" style="margin-bottom:14px;padding:12px 16px;font-weight:600;border-radius:10px">' . h($stockMessage) . '</div>';
    }
    if ($stockError !== '') {
        $body .= '<div class="err" style="margin-bottom:14px;padding:12px 16px;font-weight:600;border-radius:10px">' . h($stockError) . '</div>';
    }

    // Filtro y selección de bodega
    $body .= '<div class="card" style="margin-bottom:16px;border-radius:14px;padding:18px 20px;box-shadow:0 2px 10px rgba(15,23,42,.03)">
        <form method="get" action="/stock" class="row" style="align-items:flex-end;gap:14px;flex-wrap:wrap">
          <div style="flex:2;min-width:260px">
            <label style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:6px;display:block">Bodega ERP</label>
            <select name="bodega" onchange="this.form.submit()" style="height:40px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;font-weight:600;width:100%">'
            . unibagInventoryStorehouseOptionsHtml($storehouses, $storehouseId) .
            '</select>
          </div>
          <div style="flex:2;min-width:220px">
            <label style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:6px;display:block">Buscar por código o descripción</label>
            <input name="search" type="text" placeholder="Ej: POLIPROPILENO, BOLSA..." value="' . h($search) . '" style="height:40px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;width:100%;padding:0 12px">
          </div>
          <div style="display:flex;align-items:center;height:40px;gap:8px">
            <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#334155;cursor:pointer;margin:0">
              <input type="checkbox" name="only_stock" value="1" ' . ($onlyWithStock ? 'checked' : '') . ' onchange="this.form.submit()"> Solo con stock
            </label>
          </div>
          <div style="display:flex;gap:8px;align-items:center;height:40px">
            <button class="btn secondary" type="submit" style="height:40px;padding:0 16px">Buscar</button>
            <a class="btn secondary" href="/stock?bodega=' . $storehouseId . '&search=' . rawurlencode($search) . '&only_stock=' . ($onlyWithStock ? '1' : '0') . '&download=excel" style="height:40px;display:inline-flex;align-items:center;gap:6px;padding:0 16px">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
              Descargar Excel
            </a>
          </div>
        </form>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:16px;padding-top:16px;border-top:1px solid #f1f5f9">
          <div style="background:#f8fafc;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0">
            <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Bodega activa</div>
            <div style="font-weight:800;font-size:15px;color:#0f172a;margin-top:2px">' . h($storehouseName) . '</div>
            <div class="muted" style="font-size:11px">' . h($storehouseClasif !== '' ? $storehouseClasif : 'ID #' . $storehouseId) . '</div>
          </div>
          <div style="background:#f8fafc;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0">
            <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Artículos listados</div>
            <div style="font-weight:800;font-size:18px;color:#0f172a;margin-top:2px">' . number_format($totalItems, 0, ',', '.') . '</div>
            <div class="muted" style="font-size:11px">' . number_format($itemsWithStock, 0, ',', '.') . ' con stock > 0</div>
          </div>
          <div style="background:#f8fafc;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0">
            <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Stock total ERP</div>
            <div style="font-weight:800;font-size:18px;color:#2563eb;margin-top:2px">' . number_format($sumStock, 2, ',', '.') . '</div>
            <div class="muted" style="font-size:11px">' . number_format($sumReserved, 2, ',', '.') . ' reservados</div>
          </div>
          <div style="background:#f8fafc;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0">
            <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Disponible ERP</div>
            <div style="font-weight:800;font-size:18px;color:#16a34a;margin-top:2px">' . number_format($sumAvailable, 2, ',', '.') . '</div>
            <div class="muted" style="font-size:11px">Stock neto para uso/despacho</div>
          </div>
          <div style="background:#eff6ff;padding:12px 14px;border-radius:10px;border:1px solid #bfdbfe">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#1e40af">Ocupación Trazabilidad</div>
            <div style="font-weight:800;font-size:18px;color:#1e3a8a;margin-top:2px">'
              . ($trzOccupancy !== null ? number_format((float)$trzOccupancy, 1, ',', '.') . '%' : 'N/D') . '</div>
            <div class="muted" style="font-size:11px;color:#3b82f6">'
              . (int)$trzPallets . ' pallets · ' . (int)$trzRolls . ' bobinas · ' . (int)$trzBoxes . ' cajas</div>
          </div>
        </div>
      </div>';

    // Tabla de artículos
    $body .= '<div class="card" style="border-radius:14px;padding:0;overflow:hidden;box-shadow:0 2px 10px rgba(15,23,42,.03)">
        <div style="padding:16px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
          <div>
            <div style="font-weight:800;font-size:15px;color:#0f172a">Detalle de existencias por artículo</div>
            <div style="display:flex;gap:6px;align-items:center;margin-top:6px;flex-wrap:wrap">
              <button type="button" class="trz-tab active" data-trz-tab="all" onclick="setStockTrzTab(\'all\', this)" style="border:1px solid #cbd5e1;background:#0f172a;color:#fff;border-radius:6px;padding:3px 10px;font-size:11.5px;font-weight:700;cursor:pointer">Todos (' . count($items) . ')</button>
              <button type="button" class="trz-tab" data-trz-tab="pending" onclick="setStockTrzTab(\'pending\', this)" style="border:1px solid #fde68a;background:#fffbeb;color:#b45309;border-radius:6px;padding:3px 10px;font-size:11.5px;font-weight:700;cursor:pointer">⚠️ Pendientes de Rotular (' . $countTrzPending . ')</button>
              <button type="button" class="trz-tab" data-trz-tab="tagged" onclick="setStockTrzTab(\'tagged\', this)" style="border:1px solid #bbf7d0;background:#f0fdf4;color:#16a34a;border-radius:6px;padding:3px 10px;font-size:11.5px;font-weight:700;cursor:pointer">✅ Ya Rotulados (' . $countTrzFullyTagged . ')</button>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:10px">
            <span class="muted" style="font-size:12px">Mostrando <span id="stock-visible-count">' . count($items) . '</span> de ' . count($items) . ' registros</span>
            <input id="stock-live-search" type="text" placeholder="Filtrar en tabla..." style="height:34px;padding:0 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:12px;width:220px" oninput="stockFilterTable(this.value)">
          </div>
        </div>
        <div class="table-wrap" style="max-height:650px;overflow-y:auto">
          <table id="stock-table" style="width:100%;margin:0;border-collapse:collapse;font-size:13px">
            <thead>
              <tr style="background:#f1f5f9;border-bottom:2px solid #cbd5e1;position:sticky;top:0;z-index:2">
                <th style="padding:10px 14px;text-align:left;font-weight:700">Código</th>
                <th style="padding:10px 14px;text-align:left;font-weight:700">Artículo / Descripción</th>
                <th style="padding:10px 14px;text-align:center;font-weight:700">Unidad</th>
                <th style="padding:10px 14px;text-align:right;font-weight:700">Stock actual</th>
                <th style="padding:10px 14px;text-align:right;font-weight:700">Reservado</th>
                <th style="padding:10px 14px;text-align:right;font-weight:700">Disponible</th>
                <th style="padding:10px 14px;text-align:right;font-weight:700">Mínimo</th>
                <th style="padding:10px 14px;text-align:center;font-weight:700">Estado</th>
                <th style="padding:10px 14px;text-align:center;font-weight:700">Trazabilidad</th>
              </tr>
            </thead>
            <tbody id="stock-tbody">';

    if ($items === []) {
        $body .= '<tr><td colspan="9" style="padding:30px;text-align:center;color:#64748b">No se encontraron artículos con los criterios seleccionados.</td></tr>';
    }

    foreach ($items as $it) {
        $inv = (float)($it['iss_inventory'] ?? 0);
        $res = (float)($it['iss_inventory_reserved'] ?? 0);
        $disp = (float)($it['available_qty'] ?? 0);
        $min = (float)($it['iss_inventory_min'] ?? 0);
        $itemId = (int)$it['item_id'];
        $sku = strtoupper(trim((string)$it['item_number_prod']));
        $alreadyTagged = (float)($stockTaggedMap['id_' . $itemId] ?? ($stockTaggedMap['code_' . $sku] ?? 0.0));
        $pendingQty = max(0.0, $inv - $alreadyTagged);

        $isFullyTagged = ($inv > 0 && $alreadyTagged >= ($inv - 0.0001));
        $isPartiallyTagged = ($alreadyTagged > 0 && $pendingQty > 0.0001);
        $trzCat = ($inv <= 0) ? 'exhausted' : ($isFullyTagged ? 'tagged' : 'pending');

        if ($disp <= 0 && $inv <= 0) {
            $statusBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:#f1f5f9;color:#64748b">Agotado</span>';
        } elseif ($min > 0 && $disp <= $min) {
            $statusBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:#fef2f2;color:#dc2626;border:1px solid #fecaca">Crítico</span>';
        } else {
            $statusBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0">Disponible</span>';
        }

        // Columna de trazabilidad: si ya está 100% ingresado, NO vuelve a salir para ingresar pero permite borrar por error
        if ($isFullyTagged) {
            $trzButton = '<div style="display:inline-flex;align-items:center;gap:6px">';
            $trzButton .= '<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:11.5px;font-weight:700;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0" title="Todo el stock físico está rotulado con código de trazabilidad (' . number_format($alreadyTagged, 1, ',', '.') . ' / ' . number_format($inv, 1, ',', '.') . ' ' . h((string)$it['unit_name']) . ')">✅ Rotulado</span>';
            $trzButton .= '<a class="btn" href="/stock/traceability-entry?bodega=' . $storehouseId . '&q=' . rawurlencode($sku) . '" style="padding:3px 7px;font-size:11px;font-weight:700;background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:3px" title="Ver y borrar bultos registrados por error para ' . h($sku) . '">🗑️ Borrar</a>';
            $trzButton .= '</div>';
        } elseif ($isPartiallyTagged) {
            $trzButton = '<div style="display:inline-flex;align-items:center;gap:6px">';
            $trzButton .= '<a class="btn secondary" href="/stock/traceability-entry?bodega=' . $storehouseId . '&item_id=' . $itemId . '&sku=' . rawurlencode($sku) . '&desc=' . rawurlencode((string)$it['item_title']) . '&unit=' . rawurlencode((string)$it['unit_name']) . '" style="padding:4px 8px;font-size:11.5px;font-weight:700;background:#fffbeb;color:#b45309;border:1px solid #fde68a;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:4px" title="Faltan ' . number_format($pendingQty, 1, ',', '.') . ' por rotular de ' . number_format($inv, 1, ',', '.') . '">📦 +Trazable <span style="font-size:10px;background:#fef3c7;padding:1px 5px;border-radius:4px">(' . number_format($pendingQty, 0, ',', '.') . ' pend)</span></a>';
            $trzButton .= '<a class="btn" href="/stock/traceability-entry?bodega=' . $storehouseId . '&q=' . rawurlencode($sku) . '" style="padding:4px 7px;font-size:11px;font-weight:700;background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:3px" title="Ver y borrar bultos ya rotulados de este artículo">🗑️</a>';
            $trzButton .= '</div>';
        } elseif ($inv > 0) {
            $trzButton = '<a class="btn secondary" href="/stock/traceability-entry?bodega=' . $storehouseId . '&item_id=' . $itemId . '&sku=' . rawurlencode($sku) . '&desc=' . rawurlencode((string)$it['item_title']) . '&unit=' . rawurlencode((string)$it['unit_name']) . '" style="padding:4px 8px;font-size:11.5px;font-weight:700;background:#f0fdfa;color:#0f766e;border:1px solid #ccfbf1;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:4px" title="Ingresar bulto a bodega con código de trazabilidad">📦 +Trazable</a>';
        } else {
            $trzButton = '<span class="muted" style="font-size:11.5px">-</span>';
        }

        $body .= '<tr class="stock-row" style="border-bottom:1px solid #e2e8f0"'
            . ' data-inv="' . number_format($inv, 4, '.', '') . '"'
            . ' data-res="' . number_format($res, 4, '.', '') . '"'
            . ' data-disp="' . number_format($disp, 4, '.', '') . '"'
            . ' data-min="' . number_format($min, 4, '.', '') . '"'
            . ' data-trz-cat="' . $trzCat . '">'
            . '<td style="padding:10px 14px;font-weight:700;white-space:nowrap;color:#0f172a">' . h((string)$it['item_number_prod']) . '</td>'
            . '<td style="padding:10px 14px;font-weight:600;color:#334155">' . h((string)$it['item_title']) . '</td>'
            . '<td style="padding:10px 14px;text-align:center;white-space:nowrap;color:#64748b;font-weight:600">' . h((string)$it['unit_name']) . '</td>'
            . '<td style="padding:10px 14px;text-align:right;font-weight:700">' . number_format($inv, 2, ',', '.') . '</td>'
            . '<td style="padding:10px 14px;text-align:right;color:#64748b">' . number_format($res, 2, ',', '.') . '</td>'
            . '<td style="padding:10px 14px;text-align:right;font-weight:800;color:' . ($disp > 0 ? '#16a34a' : '#94a3b8') . '">' . number_format($disp, 2, ',', '.') . '</td>'
            . '<td style="padding:10px 14px;text-align:right;color:#64748b">' . number_format($min, 2, ',', '.') . '</td>'
            . '<td style="padding:10px 14px;text-align:center">' . $statusBadge . '</td>'
            . '<td style="padding:8px 10px;text-align:center;white-space:nowrap">' . $trzButton . '</td>'
            . '</tr>';
    }

    // Calcular suma de mínimos para el tfoot
    $sumMin = 0.0;
    foreach ($items as $it) {
        $sumMin += (float)($it['iss_inventory_min'] ?? 0);
    }

    $body .= '</tbody>'
        . '<tfoot id="stock-tfoot">'
        . '<tr style="background:#0f172a;color:#fff;border-top:2px solid #334155">'
        . '<td colspan="3" style="padding:12px 14px;font-weight:800;font-size:13px;color:#e2e8f0">'
        . 'TOTAL &mdash; <span id="tfoot-count">' . count($items) . '</span> artículos'
        . '</td>'
        . '<td style="padding:12px 14px;text-align:right;font-weight:900;font-size:13px;color:#93c5fd" id="tfoot-inv">' . number_format($sumStock, 2, ',', '.') . '</td>'
        . '<td style="padding:12px 14px;text-align:right;font-weight:700;font-size:13px;color:#94a3b8" id="tfoot-res">' . number_format($sumReserved, 2, ',', '.') . '</td>'
        . '<td style="padding:12px 14px;text-align:right;font-weight:900;font-size:13px;color:#4ade80" id="tfoot-disp">' . number_format($sumAvailable, 2, ',', '.') . '</td>'
        . '<td style="padding:12px 14px;text-align:right;font-weight:700;font-size:13px;color:#94a3b8" id="tfoot-min">' . number_format($sumMin, 2, ',', '.') . '</td>'
        . '<td style="padding:12px 14px"></td>'
        . '<td style="padding:12px 14px"></td>'
        . '</tr>'
        . '</tfoot>'
        . '</table></div></div>';

    // Script de filtrado en vivo que actualiza el tfoot
    $body .= '<script>
    var currentTrzTab = "all";
    function setStockTrzTab(tab, btn) {
        currentTrzTab = tab;
        document.querySelectorAll(".trz-tab").forEach(function(b) {
            b.classList.remove("active");
            if (b.getAttribute("data-trz-tab") === "all") {
                b.style.background = "#f1f5f9";
                b.style.color = "#334155";
            }
        });
        btn.classList.add("active");
        if (tab === "all") {
            btn.style.background = "#0f172a";
            btn.style.color = "#fff";
        }
        var searchInput = document.getElementById("stock-live-search");
        stockFilterTable(searchInput ? searchInput.value : "");
    }

    function stockFilterTable(query) {
        var q = (query || "").trim().toLowerCase();
        var rows = document.querySelectorAll("#stock-tbody .stock-row");
        var sumInv = 0, sumRes = 0, sumDisp = 0, sumMin = 0, visible = 0;
        rows.forEach(function(row) {
            var text = row.textContent.toLowerCase();
            var matchesText = (q === "" || text.indexOf(q) !== -1);
            var trzCat = row.getAttribute("data-trz-cat") || "pending";
            var matchesTab = (currentTrzTab === "all") || (currentTrzTab === trzCat);
            var show = matchesText && matchesTab;
            row.style.display = show ? "" : "none";
            if (show) {
                sumInv  += parseFloat(row.getAttribute("data-inv")  || "0");
                sumRes  += parseFloat(row.getAttribute("data-res")  || "0");
                sumDisp += parseFloat(row.getAttribute("data-disp") || "0");
                sumMin  += parseFloat(row.getAttribute("data-min")  || "0");
                visible++;
            }
        });
        var fmt = function(n) {
            return n.toLocaleString("es-CL", {minimumFractionDigits: 2, maximumFractionDigits: 2});
        };
        document.getElementById("tfoot-count").textContent = visible;
        document.getElementById("tfoot-inv").textContent   = fmt(sumInv);
        document.getElementById("tfoot-res").textContent   = fmt(sumRes);
        document.getElementById("tfoot-disp").textContent  = fmt(sumDisp);
        document.getElementById("tfoot-min").textContent   = fmt(sumMin);
        document.getElementById("stock-visible-count").textContent = visible;
    }
    </script>';

    $body .= '</div>';

    render('Stock de Bodegas · ERP', $body);
}

/**
 * Renderiza la pantalla de toma de inventario físico conectada al ERP unibag_unibag.
 */
function unibagRenderInventoryCountsPage(ReceptionService $service): void
{
    // Listar solo bodegas que tienen inventario
    $storehouses = $service->listErpStorehouses(true);
    $requestedStId = isset($_GET['bodega']) ? (int)$_GET['bodega'] : 0;
    $stockMessage = trim((string)($_GET['msg'] ?? ''));
    $stockError = trim((string)($_GET['error'] ?? ''));

    $selectedStorehouse = null;
    if ($requestedStId > 0) {
        foreach ($storehouses as $st) {
            if ((int)$st['id'] === $requestedStId) {
                $selectedStorehouse = $st;
                break;
            }
        }
    }
    if ($selectedStorehouse === null && $storehouses !== []) {
        $selectedStorehouse = $storehouses[0];
        $requestedStId = (int)$selectedStorehouse['id'];
    }

    $storehouseId = $selectedStorehouse !== null ? (int)$selectedStorehouse['id'] : 0;
    $storehouseName = $selectedStorehouse !== null ? (string)$selectedStorehouse['name'] : 'Sin bodega';

    $draftItems = [];
    if ($storehouseId > 0) {
        $draftItems = $service->getErpInventoryCountDraft($storehouseId);
    }

    $body = '<div class="erp-prod-shell" style="max-width:1440px;margin:0 auto">';

    $body .= '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:16px">
        <div>
          <div style="font-size:22px;font-weight:800;color:#0f172a">Toma de inventario · ERP</div>
          <div class="muted" style="font-size:13px;margin-top:2px">
            Registro de recuento físico directo en <code>unibag_unibag</code> (stockcounts). Las diferencias se calculan automáticamente.
          </div>
        </div>
        <div style="display:flex;gap:8px">
          <a class="btn secondary" href="/stock?bodega=' . $storehouseId . '">← Volver a consulta de stock</a>
          <a class="btn secondary" href="/reports/inventory">Ver historial de inventarios</a>
        </div>
      </div>';

    if ($stockMessage !== '') {
        $body .= '<div class="ok" style="margin-bottom:14px;padding:12px 16px;font-weight:600;border-radius:10px">' . h($stockMessage) . '</div>';
    }
    if ($stockError !== '') {
        $body .= '<div class="err" style="margin-bottom:14px;padding:12px 16px;font-weight:600;border-radius:10px">' . h($stockError) . '</div>';
    }

    $body .= '<div class="card" style="margin-bottom:16px;border-radius:14px;padding:18px 20px">
        <form method="get" action="/stock/inventory-counts" class="row" style="align-items:flex-end;gap:14px">
          <div style="flex:2;min-width:280px">
            <label style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:6px;display:block">Seleccionar bodega a inventariar</label>
            <select name="bodega" onchange="this.form.submit()" style="height:40px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;font-weight:600;width:100%">'
            . unibagInventoryStorehouseOptionsHtml($storehouses, $storehouseId) .
            '</select>
          </div>
          <div style="display:flex;align-items:flex-end;height:40px">
            <button class="btn secondary" type="submit">Cargar planilla</button>
          </div>
        </form>
      </div>';

    $body .= '<div class="card" style="border-radius:14px;padding:20px">';
    $body .= '<form method="post" action="/stock/inventory-counts">';
    $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
    $body .= '<input type="hidden" name="bodega" value="' . $storehouseId . '">';
    $body .= '<input type="hidden" name="storehouse_name" value="' . h($storehouseName) . '">';

    $body .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin-bottom:18px;padding-bottom:16px;border-bottom:1px solid #e2e8f0">
        <div>
          <label style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:4px;display:block">Anotación / Glosa de la toma</label>
          <input name="annotation" type="text" value="Recuento físico ' . date('d.m.Y') . ' - ' . h($storehouseName) . '" required style="height:38px;border-radius:8px;border:1px solid #cbd5e1;width:100%;padding:0 10px;font-weight:600">
        </div>
        <div>
          <label style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:4px;display:block">Responsable / Operador</label>
          <input name="operator_name" type="text" value="' . h((string)($_SESSION['auth_user_name'] ?? $_SESSION['user_name'] ?? 'Operador')) . '" required style="height:38px;border-radius:8px;border:1px solid #cbd5e1;width:100%;padding:0 10px;font-weight:600">
        </div>
        <div>
          <label style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:4px;display:block">Fecha contable</label>
          <input type="text" value="' . date('d.m.Y H:i') . '" disabled style="height:38px;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;width:100%;padding:0 10px;color:#64748b">
        </div>
      </div>';

    $body .= '<div style="font-weight:800;font-size:15px;color:#0f172a;margin-bottom:10px">Planilla de conteo (' . count($draftItems) . ' artículos)</div>';
    $body .= '<div class="table-wrap" style="max-height:600px;overflow-y:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
          <thead>
            <tr style="background:#f1f5f9;border-bottom:2px solid #cbd5e1;position:sticky;top:0;z-index:2">
              <th style="padding:10px;text-align:left">Código</th>
              <th style="padding:10px;text-align:left">Artículo / Descripción</th>
              <th style="padding:10px;text-align:center">Unidad</th>
              <th style="padding:10px;text-align:right">Stock Sistema</th>
              <th style="padding:10px;text-align:right;width:130px">Conteo Físico</th>
              <th style="padding:10px;text-align:right;width:110px">Diferencia</th>
              <th style="padding:10px;text-align:left">Observación</th>
            </tr>
          </thead>
          <tbody>';

    if ($draftItems === []) {
        $body .= '<tr><td colspan="7" style="padding:30px;text-align:center;color:#64748b">No hay artículos registrados para esta bodega.</td></tr>';
    }

    foreach ($draftItems as $idx => $item) {
        $sysQty = (float)($item['system_qty'] ?? 0);
        $phyQty = (float)($item['physical_qty'] ?? $sysQty);
        $diff = $phyQty - $sysQty;
        $prefix = 'items[' . $idx . ']';

        $body .= '<tr style="border-bottom:1px solid #e2e8f0">
            <td style="padding:8px 10px;font-weight:700;white-space:nowrap">
              ' . h((string)$item['item_number_prod']) . '
              <input type="hidden" name="' . $prefix . '[item_id]" value="' . (int)$item['item_id'] . '">
              <input type="hidden" name="' . $prefix . '[item_number_prod]" value="' . h((string)$item['item_number_prod']) . '">
              <input type="hidden" name="' . $prefix . '[item_title]" value="' . h((string)$item['item_title']) . '">
              <input type="hidden" name="' . $prefix . '[unit_name]" value="' . h((string)$item['unit_name']) . '">
              <input type="hidden" name="' . $prefix . '[system_qty]" value="' . number_format($sysQty, 3, '.', '') . '">
            </td>
            <td style="padding:8px 10px;font-weight:600;color:#334155">' . h((string)$item['item_title']) . '</td>
            <td style="padding:8px 10px;text-align:center;color:#64748b">' . h((string)$item['unit_name']) . '</td>
            <td style="padding:8px 10px;text-align:right;font-weight:700">' . number_format($sysQty, 2, ',', '.') . '</td>
            <td style="padding:8px 10px;text-align:right">
              <input data-system-qty="' . number_format($sysQty, 3, '.', '') . '" data-diff-target="diff-' . $idx . '" name="' . $prefix . '[physical_qty]" type="number" step="0.01" min="0" value="' . number_format($phyQty, 2, '.', '') . '" style="width:110px;height:34px;text-align:right;font-weight:700;border:1px solid #cbd5e1;border-radius:6px;padding:0 6px">
            </td>
            <td style="padding:8px 10px;text-align:right;font-weight:800">
              <span id="diff-' . $idx . '" style="color:' . ($diff === 0.0 ? '#64748b' : ($diff > 0 ? '#16a34a' : '#dc2626')) . '">' . number_format($diff, 2, ',', '.') . '</span>
            </td>
            <td style="padding:8px 10px">
              <input name="' . $prefix . '[comment]" type="text" placeholder="Opcional..." value="' . h((string)($item['comment'] ?? '')) . '" style="width:100%;height:34px;border:1px solid #cbd5e1;border-radius:6px;padding:0 8px;font-size:12px">
            </td>
          </tr>';
    }

    $body .= '</tbody></table></div>';

    $canEdit = unibagCanUserPerformModifications();
    if ($canEdit) {
        $body .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding-top:16px;border-top:1px solid #e2e8f0">
            <div class="muted" style="font-size:12px">Al guardar, se creará el registro de recuento <code>IR0000XXX</code> en la base de datos ERP.</div>
            <button class="btn" type="submit"' . ($draftItems === [] ? ' disabled' : '') . ' style="padding:10px 24px;font-size:14px;font-weight:800">
              Guardar inventario realizado en ERP
            </button>
          </div>';
    } else {
        $body .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding-top:16px;border-top:1px solid #e2e8f0">
            <div class="muted" style="font-size:12px">🔒 La toma y modificación de inventario físico está restringida exclusivamente a HECTOR y JAVIER.</div>
            <button class="btn secondary" type="button" disabled style="padding:10px 24px;font-size:14px;font-weight:700;color:#94a3b8">
              🔒 Modificación restringida
            </button>
          </div>';
    }

    $body .= '</form>';

    $body .= '<script>
        (function () {
          var inputs = document.querySelectorAll("input[data-system-qty][data-diff-target]");
          function recalc(input) {
            var sys = parseFloat(input.getAttribute("data-system-qty") || "0");
            var phy = parseFloat(input.value || "0");
            if (isNaN(phy)) phy = 0;
            var diff = phy - sys;
            var targetId = input.getAttribute("data-diff-target");
            var span = targetId ? document.getElementById(targetId) : null;
            if (span) {
              span.textContent = (diff > 0 ? "+" : "") + diff.toLocaleString("es-CL", {minimumFractionDigits: 2, maximumFractionDigits: 2});
              span.style.color = diff === 0 ? "#64748b" : (diff > 0 ? "#16a34a" : "#dc2626");
            }
          }
          for (var i = 0; i < inputs.length; i++) {
            inputs[i].addEventListener("input", function () { recalc(this); });
            recalc(inputs[i]);
          }
        })();
      </script>';

    $body .= '</div></div>';

    render('Toma de Inventario · ERP', $body);
}

/**
 * Renderiza la pantalla de ingreso de productos a bodega con código de trazabilidad.
 */
function unibagRenderTraceabilityEntryPage(ReceptionService $service, string $currentOperatorName): void
{
    $service->ensureTraceabilityWarehouseEntriesTable();
    $canEdit = true;
    $storehouses = $service->listErpStorehouses();
    $requestedWhId = isset($_GET['bodega']) ? (int)$_GET['bodega'] : 0;

    $selectedStorehouse = null;
    if ($requestedWhId > 0) {
        foreach ($storehouses as $st) {
            if ((int)$st['id'] === $requestedWhId) {
                $selectedStorehouse = $st;
                break;
            }
        }
    }
    if ($selectedStorehouse === null && $storehouses !== []) {
        $selectedStorehouse = $storehouses[0];
        $requestedWhId = (int)$selectedStorehouse['id'];
    }

    $storehouseId = $selectedStorehouse !== null ? (int)$selectedStorehouse['id'] : 0;
    $storehouseName = $selectedStorehouse !== null ? (string)$selectedStorehouse['name'] : 'Bodega';

    $preItemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
    $preSku = trim((string)($_GET['sku'] ?? ''));
    $preDesc = trim((string)($_GET['desc'] ?? ''));
    $preUnit = trim((string)($_GET['unit'] ?? 'UNID')) ?: 'UNID';

    // Obtener catálogo de artículos disponibles en esta bodega para autocompletar (SOLO con stock pendiente de etiquetar > 0)
    $items = [];
    $taggedMap = [];
    $preItemFullyTagged = false;
    if ($storehouseId > 0) {
        try {
            $rawItems = $service->getErpStockByStorehouse($storehouseId, null, true);
            $taggedMap = $service->getWarehouseTraceabilityTaggedQuantities($storehouseId);
            foreach ($rawItems as $it) {
                $totalStock = (float)($it['iss_inventory'] ?? 0);
                $itemId = (int)$it['item_id'];
                $itemSku = strtoupper(trim((string)$it['item_number_prod']));
                $alreadyTagged = (float)($taggedMap['id_' . $itemId] ?? ($taggedMap['code_' . $itemSku] ?? 0.0));
                $pendingQty = max(0.0, $totalStock - $alreadyTagged);

                if ($itemId === $preItemId && $pendingQty <= 0.0001 && $totalStock > 0) {
                    $preItemFullyTagged = true;
                }

                // Si ya fue completamente ingresado a trazabilidad, NUNCA APARECE en el selector
                if ($pendingQty > 0.0001) {
                    $it['already_tagged'] = $alreadyTagged;
                    $it['pending_qty'] = $pendingQty;
                    $items[] = $it;
                }
            }
        } catch (Throwable) {
            $items = [];
        }
    }

    if ($preItemFullyTagged) {
        $msgPre = "El artículo '{$preSku}' ya se encuentra 100% ingresado en trazabilidad en esta bodega. No tiene existencias pendientes de rotular.";
        $preItemId = 0;
        $preSku = '';
        $preDesc = '';
        $preUnit = 'UNID';
        if ($err === '') {
            $err = $msgPre;
        }
    }

    $preWidth = 0.0;
    $preMicrons = 0.0;
    $preMeters = 0.0;
    if ($preItemId > 0 || $preSku !== '') {
        foreach ($items as $it) {
            if (($preItemId > 0 && (int)$it['item_id'] === $preItemId) || ($preSku !== '' && strcasecmp((string)$it['item_number_prod'], $preSku) === 0)) {
                if ($preSku === '') $preSku = (string)$it['item_number_prod'];
                if ($preDesc === '') $preDesc = (string)$it['item_title'];
                if ($preUnit === 'UNID' && !empty($it['unit_name'])) $preUnit = (string)$it['unit_name'];
                $preWidth = (float)($it['item_reg_width'] ?? 0);
                $preMicrons = (float)($it['item_reg_gsm'] ?? 0);
                $preMeters = (float)($it['item_reg_length'] ?? 0);
                break;
            }
        }
    }

    $recentEntries = $service->listWarehouseTraceabilityEntries($storehouseId > 0 ? $storehouseId : null, null, 60);
    $suggestedCode = $service->generateTraceabilityCode('PRODUCT');
    $msg = trim((string)($_GET['msg'] ?? ''));
    $err = trim((string)($_GET['error'] ?? ''));

    // Si viene de guardar lote de bultos, preparar etiquetas automáticas para imprimir
    $printCodesParam = trim((string)($_GET['print_codes'] ?? ''));
    $autoPrintEntries = [];
    if ($printCodesParam !== '') {
        $codesToPrint = array_values(array_filter(array_map('trim', explode(',', $printCodesParam))));
        if ($codesToPrint !== []) {
            $autoPrintEntries = $service->getWarehouseTraceabilityEntriesByCodes($codesToPrint);
        }
    }

    $body = '<style>
        .trz-shell { max-width: 1440px; margin: 0 auto; width: 100%; box-sizing: border-box; }
        .trz-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 20px; }
        .trz-title { font-size: 22px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; display: flex; align-items: center; gap: 10px; }
        .trz-sub { font-size: 13px; color: #64748b; margin-top: 3px; }
        
        .trz-grid { display: grid; grid-template-columns: minmax(360px, 480px) minmax(0, 1fr); gap: 20px; align-items: start; }
        .trz-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; box-shadow: 0 4px 16px rgba(15,23,42,.03); }
        .trz-card-title { font-size: 15px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; gap: 8px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }

        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 11.5px; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 5px; }
        .form-control { width: 100%; height: 42px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0 12px; background: #f8fafc; font-size: 13.5px; font-weight: 600; color: #0f172a; box-sizing: border-box; transition: all .15s ease; outline: none; }
        .form-control:focus { border-color: #00A9A6; background: #fff; box-shadow: 0 0 0 3px rgba(0,169,166,.15); }
        .form-control-code { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 15px; font-weight: 800; letter-spacing: .05em; color: #0f766e; background: #f0fdfa; border-color: #99f6e4; }

        .type-selector { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-bottom: 14px; }
        .type-btn { height: 42px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc; color: #475569; font-weight: 700; font-size: 12px; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1.1; transition: all .15s ease; user-select: none; }
        .type-btn:hover { background: #f1f5f9; color: #0f172a; }
        .type-btn.active { background: #00A9A6; border-color: #00A9A6; color: #ffffff; box-shadow: 0 2px 8px rgba(0,169,166,.25); }

        .mode-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 14px; }
        .mode-btn { border: 1px solid #cbd5e1; background: #f8fafc; border-radius: 10px; padding: 10px 12px; text-align: left; cursor: pointer; transition: all .15s ease; }
        .mode-btn:hover { background: #f1f5f9; }
        .mode-btn.active { border-color: #00A9A6; background: #f0fdfa; box-shadow: 0 0 0 2px rgba(0,169,166,.2); }
        .mode-btn-title { font-weight: 800; font-size: 13px; color: #334155; }
        .mode-btn.active .mode-btn-title { color: #0f766e; }
        .mode-btn-desc { font-size: 11px; color: #64748b; margin-top: 2px; }

        .btn-action-primary { width: 100%; height: 46px; background: linear-gradient(135deg, #00A9A6, #0f766e); color: #fff; border: none; border-radius: 10px; font-weight: 800; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 14px rgba(0,169,166,.3); transition: all .15s ease; }
        .btn-action-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,169,166,.4); }

        .code-input-wrap { display: flex; gap: 8px; }
        .btn-gen-code { height: 42px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 10px; background: #f1f5f9; color: #334155; font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; }
        .btn-gen-code:hover { background: #e2e8f0; color: #0f172a; }

        .badge-type { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 800; letter-spacing: .03em; }
        .badge-type.PALLET { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .badge-type.BOX { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-type.ROLL { background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; }
        .badge-type.PRODUCT { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }

        .trz-tag-code { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-weight: 800; font-size: 12.5px; color: #0f766e; background: #f0fdfa; border: 1px solid #ccfbf1; padding: 3px 8px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .trz-tag-code:hover { background: #00A9A6; color: #fff; border-color: #00A9A6; }

        /* Modal Imprimir Etiqueta */
        .print-modal { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.6); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; padding: 14px; }
        .print-modal.open { display: flex; }
        .print-card { background: #fff; border-radius: 16px; width: min(560px, 96vw); max-height: 90vh; overflow-y: auto; padding: 22px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3); }
        .printable-label { background: #fff; border: 2px solid #0f172a; border-radius: 8px; padding: 16px; text-align: center; color: #0f172a; margin-bottom: 16px; font-family: Arial, sans-serif; }
        .barcode-visual { height: 50px; margin: 10px auto; background: repeating-linear-gradient(90deg, #111 0, #111 2px, #fff 2px, #fff 4px, #111 4px, #111 7px, #fff 7px, #fff 9px); width: 85%; }

        .trz-fields-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .trz-fields-3col { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; }

        @media (max-width: 992px) {
            .trz-grid { grid-template-columns: 1fr; }
            .type-selector { grid-template-columns: repeat(2, 1fr); }
            .trz-header { flex-direction: column; align-items: stretch; }
        }
        @media (max-width: 640px) {
            .trz-fields-2col, .trz-fields-3col { grid-template-columns: 1fr; gap: 8px; }
            .mode-selector { grid-template-columns: 1fr; }
            .type-selector { grid-template-columns: 1fr 1fr; }
            .code-input-wrap { flex-direction: column; }
            .btn-gen-code { width: 100%; justify-content: center; }
            .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .trz-card { padding: 14px; border-radius: 12px; }
            .print-card { width: calc(100vw - 20px); padding: 14px; }
        }
    </style>';

    $body .= '<div class="trz-shell">';

    // Encabezado
    $body .= '<div class="trz-header">';
    $body .= '<div>';
    $body .= '<div class="trz-title"><span>📦</span> Ingreso a Bodega con Código de Trazabilidad</div>';
    $body .= '<div class="trz-sub">Registro y rotulación de bultos físicos con códigos de trazabilidad únicos para existencias en bodega.</div>';
    $body .= '</div>';
    $body .= '<div style="display:flex;gap:8px;flex-wrap:wrap">';
    $body .= '<a class="btn secondary" href="/stock?bodega=' . $storehouseId . '" style="display:inline-flex;align-items:center;gap:6px">← Volver a Stock</a>';
    $body .= '<a class="btn secondary" href="/stock/inventory-counts?bodega=' . $storehouseId . '" style="display:inline-flex;align-items:center;gap:6px">Toma de Inventario</a>';
    $body .= '<a class="btn" href="/stock/traceability?bodega=' . $storehouseId . '" style="background:#0f766e;display:inline-flex;align-items:center;gap:6px">🌳 Árbol Trazabilidad</a>';
    $body .= '</div>';
    $body .= '</div>';

    // Alertas
    if ($msg !== '') {
        $body .= '<div class="ok" style="margin-bottom:16px;padding:12px 16px;border-radius:10px;font-weight:700">✅ ' . h($msg) . '</div>';
    }
    if ($err !== '') {
        $body .= '<div class="err" style="margin-bottom:16px;padding:12px 16px;border-radius:10px;font-weight:700">⚠️ ' . h($err) . '</div>';
    }

    $body .= '<div class="trz-grid">';

    // COLUMNA 1: Formulario de Ingreso
    $body .= '<div class="trz-card">';
    $body .= '<div class="trz-card-title">';
    $body .= '<span>📝 Nuevo Ingreso / Rotulación</span>';
    $body .= '<span style="font-size:11px;font-weight:700;color:#00A9A6;background:#f0fdfa;padding:3px 8px;border-radius:6px">ERP & TRZ</span>';
    $body .= '</div>';

    $body .= '<form method="post" action="/stock/traceability-entry" id="form-trz-entry">';
    $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
    $body .= '<input type="hidden" name="entity_type" id="input_entity_type" value="PRODUCT">';
    $body .= '<input type="hidden" name="packaging_mode" id="input_packaging_mode" value="UNITARY">';
    $body .= '<input type="hidden" name="item_id" id="input_item_id" value="' . (int)$preItemId . '">';

    // Bodega destino
    $body .= '<div class="form-group">';
    $body .= '<label>Bodega Destino *</label>';
    $body .= '<select name="warehouse_id" id="select_warehouse" class="form-control" onchange="changeWarehouse(this.value)">';
    foreach ($storehouses as $st) {
        $stId = (int)$st['id'];
        $selected = ($stId === $storehouseId) ? ' selected' : '';
        $body .= '<option value="' . $stId . '"' . $selected . '>' . h((string)$st['name']) . ' (' . h((string)($st['clasificacion'] ?: 'Bodega #' . $stId)) . ')</option>';
    }
    $body .= '</select>';
    $body .= '</div>';

    // Tipo de bulto
    $body .= '<div class="form-group">';
    $body .= '<label>Tipo de Bulto / Unidad *</label>';
    $body .= '<div class="type-selector">';
    $body .= '<button type="button" class="type-btn active" data-type="PRODUCT" onclick="selectEntityType(\'PRODUCT\')"><span>🛍️</span><span>Producto</span></button>';
    $body .= '<button type="button" class="type-btn" data-type="PALLET" onclick="selectEntityType(\'PALLET\')"><span>🏷️</span><span>Pallet</span></button>';
    $body .= '<button type="button" class="type-btn" data-type="BOX" onclick="selectEntityType(\'BOX\')"><span>📦</span><span>Caja</span></button>';
    $body .= '<button type="button" class="type-btn" data-type="ROLL" onclick="selectEntityType(\'ROLL\')"><span>🌀</span><span>Bobina</span></button>';
    $body .= '</div>';
    $body .= '</div>';

    // Selector de Modalidad de Rotulación (Unitario vs Agrupado)
    $body .= '<div class="form-group">';
    $body .= '<label>Modalidad de Rotulación *</label>';
    $body .= '<div class="mode-selector">';
    $body .= '<button type="button" class="mode-btn active" id="btn_mode_unitary" onclick="setPackagingMode(\'UNITARY\')">';
    $body .= '<div class="mode-btn-title">🏷️ 1 Etiqueta por cada Unidad / Producto</div>';
    $body .= '<div class="mode-btn-desc">Para Bobinas, Pallets o piezas donde cada una lleva su propia etiqueta única.</div>';
    $body .= '</button>';
    $body .= '<button type="button" class="mode-btn" id="btn_mode_pack" onclick="setPackagingMode(\'PACK\')">';
    $body .= '<div class="mode-btn-title">📦 Bultos con Contenido Agrupado</div>';
    $body .= '<div class="mode-btn-desc">Cajas o pallets que contienen múltiples unidades adentro (ej. 5 cajas de 500 bolsas).</div>';
    $body .= '</button>';
    $body .= '</div>';
    $body .= '</div>';

    // Código de Trazabilidad
    $body .= '<div class="form-group">';
    $body .= '<label>Código de Trazabilidad (o prefijo autogenerado)</label>';
    $body .= '<div class="code-input-wrap">';
    $body .= '<input type="text" name="traceability_code" id="input_trace_code" class="form-control form-control-code" value="' . h($suggestedCode) . '" placeholder="Escanee código o autogenere..." autocomplete="off">';
    $body .= '<button type="button" class="btn-gen-code" onclick="autoGenerateCode()" title="Generar nuevo código único">⚡ Nuevo</button>';
    $body .= '</div>';
    $body .= '<div class="muted" style="font-size:11px;margin-top:4px">Al ingresar más de 1 bulto, cada producto tendrá su código único consecutivo y su etiqueta individual.</div>';
    $body .= '</div>';

    // Selector de Artículo del ERP (Solo artículos con stock pendiente)
    $body .= '<div class="form-group">';
    $body .= '<label>Seleccionar Artículo de Inventario (Pendientes de Rotular)</label>';
    $body .= '<select id="erp_item_select" class="form-control" onchange="pickErpItem(this)">';
    $body .= '<option value="">-- Seleccionar de catálogo bodega (' . count($items) . ' con stock pendiente) --</option>';
    foreach ($items as $it) {
        $itId = (int)$it['item_id'];
        $itSku = trim((string)$it['item_number_prod']);
        $itTitle = trim((string)$it['item_title']);
        $itUnit = trim((string)$it['unit_name']) ?: 'UNID';
        $itStock = (float)($it['iss_inventory'] ?? 0);
        $itPending = (float)($it['pending_qty'] ?? $itStock);
        $itWidth = (float)($it['item_reg_width'] ?? 0);
        $itMicrons = (float)($it['item_reg_gsm'] ?? 0);
        $itMeters = (float)($it['item_reg_length'] ?? 0);
        $sel = ($itId === $preItemId || ($preSku !== '' && strcasecmp($itSku, $preSku) === 0)) ? ' selected' : '';
        $body .= '<option value="' . $itId . '" data-sku="' . h($itSku) . '" data-desc="' . h($itTitle) . '" data-unit="' . h($itUnit) . '" data-pending="' . $itPending . '" data-width="' . $itWidth . '" data-microns="' . $itMicrons . '" data-meters="' . $itMeters . '"' . $sel . '>';
        $body .= h($itSku . ' · ' . $itTitle . ' (Pendiente: ' . number_format($itPending, 1, ',', '.') . ' ' . $itUnit . ' / Total: ' . number_format($itStock, 1, ',', '.') . ')');
        $body .= '</option>';
    }
    $body .= '</select>';
    $body .= '</div>';

    // SKU / Código y Descripción
    $body .= '<div class="trz-fields-2col">';
    $body .= '<div class="form-group">';
    $body .= '<label>Código / SKU *</label>';
    $body .= '<input type="text" name="item_code" id="input_item_code" class="form-control" value="' . h($preSku) . '" placeholder="Ej: POL-BL-80" required>';
    $body .= '</div>';
    $body .= '<div class="form-group">';
    $body .= '<label>Unidad Medida</label>';
    $body .= '<input type="text" name="unit_name" id="input_unit_name" class="form-control" value="' . h($preUnit) . '" placeholder="UNID, KG, M">';
    $body .= '</div>';
    $body .= '</div>';

    $body .= '<div class="form-group">';
    $body .= '<label>Descripción del Producto *</label>';
    $body .= '<input type="text" name="item_description" id="input_item_desc" class="form-control" value="' . h($preDesc) . '" placeholder="Nombre o descripción completa del producto..." required>';
    $body .= '</div>';

    // SECCIÓN CANTIDAD EN MODO UNITARIO (1 etiqueta por cada producto)
    $body .= '<div id="sec_mode_unitary">';
    $body .= '<div class="form-group">';
    $body .= '<label id="lbl_unitary_count">Cantidad de Productos / Bultos a Rotular (Etiquetas a Imprimir) *</label>';
    $body .= '<input type="number" min="1" max="200" name="package_count" id="input_package_count" class="form-control" value="1" required oninput="calcTotals()">';
    $body .= '<div class="muted" style="font-size:11.5px;margin-top:4px;color:#0f766e;font-weight:600">👉 Se generará una etiqueta física individual con código único para CADA producto/bulto físico.</div>';
    $body .= '</div>';
    $body .= '</div>';

    // SECCIÓN CANTIDAD EN MODO AGRUPADO (Cajas con múltiples unidades)
    $body .= '<div id="sec_mode_pack" style="display:none">';
    $body .= '<div class="trz-fields-2col">';
    $body .= '<div class="form-group">';
    $body .= '<label>1. Cantidad de Bultos (Etiquetas a Imprimir) *</label>';
    $body .= '<input type="number" min="1" max="200" id="input_package_count_pack" class="form-control" value="1" oninput="calcTotals()">';
    $body .= '<div class="muted" style="font-size:11px;margin-top:3px">Número de pallets, cajas o bultos físicos a rotular.</div>';
    $body .= '</div>';
    $body .= '<div class="form-group">';
    $body .= '<label>2. Unidades de Producto por Bulto *</label>';
    $body .= '<input type="number" step="0.001" min="0.001" name="quantity" id="input_quantity" class="form-control" value="1" required oninput="calcTotals()">';
    $body .= '<div class="muted" style="font-size:11px;margin-top:3px">Unidades contenidas en cada bulto (ej. 500 bolsas en cada caja).</div>';
    $body .= '</div>';
    $body .= '</div>';
    $body .= '</div>';

    // Resumen en vivo
    $body .= '<div id="box_calc_summary" style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:13px;color:#0f766e">';
    $body .= '<strong>📊 Resumen de rotulación:</strong> Se generará <strong id="lbl_calc_packages">1</strong> etiqueta única (1 por producto) para un total de <strong id="lbl_calc_total">1.00</strong> unidades de producto.';
    $body .= '</div>';

    // Cajas por pallet (solo cuando se selecciona modalidad PALLET)
    $body .= '<div class="form-group" id="group_box_count" style="display:none">';
    $body .= '<label>Cajas contenidas por Pallet</label>';
    $body .= '<input type="number" min="1" name="box_count" class="form-control" placeholder="Ej: 50 cajas">';
    $body .= '</div>';

    // Campos ocultos para datos técnicos rescatados del sistema (Ancho, Micras, Metros)
    // El usuario no necesita ingresarlos uno a uno, y el peso para productos del inventario no se toma en cuenta
    $body .= '<input type="hidden" name="width_mm" id="input_width_mm" value="' . ($preWidth > 0 ? (int)round($preWidth) : '') . '">';
    $body .= '<input type="hidden" name="microns" id="input_microns" value="' . ($preMicrons > 0 ? (int)round($preMicrons) : '') . '">';
    $body .= '<input type="hidden" name="meters" id="input_meters" value="' . ($preMeters > 0 ? $preMeters : '') . '">';

    $preSpecsArr = [];
    if ($preWidth > 0) $preSpecsArr[] = 'Ancho: ' . (int)round($preWidth) . ' mm';
    if ($preMicrons > 0) $preSpecsArr[] = 'Micras: ' . (int)round($preMicrons) . ' µm';
    if ($preMeters > 0) $preSpecsArr[] = 'Largo: ' . number_format($preMeters, 1, ',', '.') . ' m';
    $hasPreSpecs = $preSpecsArr !== [];

    $body .= '<div id="erp_specs_badge" style="display:' . ($hasPreSpecs ? 'block' : 'none') . ';background:#f0fdfa;border:1px solid #99f6e4;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:12.5px;color:#0f766e">';
    $body .= '<strong>📐 Especificaciones técnicas (rescatadas del catálogo):</strong> ';
    $body .= '<span id="erp_specs_text" style="font-weight:700">' . h(implode(' · ', $preSpecsArr)) . '</span>';
    $body .= '</div>';

    // Operador Responsable
    $body .= '<div class="form-group">';
    $body .= '<label>Operador Responsable *</label>';
    $body .= '<input type="text" name="operator_name" class="form-control" value="' . h($currentOperatorName ?: 'Bodeguero') . '" required>';
    $body .= '</div>';

    $body .= '<div class="form-group">';
    $body .= '<label>Observaciones (opcional)</label>';
    $body .= '<textarea name="comments" class="form-control" style="height:55px;padding:8px" placeholder="Comentarios del ingreso físico..."></textarea>';
    $body .= '</div>';

    $body .= '<button type="submit" class="btn-action-primary">';
    $body .= '<span>💾</span> Registrar Ingreso y Generar Etiquetas';
    $body .= '</button>';

    $body .= '</form>';
    $body .= '</div>'; // Fin Columna 1

    // COLUMNA 2: Historial de Ingresos Trazables Recientes
    $body .= '<div class="trz-card">';
    $body .= '<div class="trz-card-title">';
    $body .= '<span>📋 Ingresos Trazables Recientes</span>';
    $body .= '<span style="font-size:12px;font-weight:700;color:#64748b">' . count($recentEntries) . ' registrados</span>';
    $body .= '</div>';

    // Barra de búsqueda rápida en vivo y botón para borrar lote completo si se filtra
    $initialQuery = trim((string)($_GET['q'] ?? ($_GET['filter'] ?? '')));
    $body .= '<div style="margin-bottom:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
    $body .= '<div style="flex:1;min-width:240px">';
    $body .= '<input type="text" id="trz-live-filter" placeholder="🔍 Buscar por código, SKU, descripción u operador..." class="form-control" style="font-size:12.5px" value="' . h($initialQuery) . '" oninput="filterRecentEntries(this.value)">';
    $body .= '</div>';
    $body .= '<button type="button" id="btn_delete_filtered_sku" class="btn" style="display:none;background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;padding:0 12px;height:42px;font-size:12px;font-weight:700;cursor:pointer" onclick="deleteAllFilteredBySku()">🗑️ Borrar bultos mostrados</button>';
    $body .= '</div>';

    $body .= '<div class="table-wrap" style="max-height:680px;overflow-y:auto;overflow-x:auto">';
    $body .= '<table id="table-recent-entries" style="width:100%;border-collapse:collapse;font-size:12.5px">';
    $body .= '<thead>';
    $body .= '<tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;position:sticky;top:0;z-index:2">';
    $body .= '<th style="padding:10px 12px;text-align:left;font-weight:700">Código Trazable</th>';
    $body .= '<th style="padding:10px 12px;text-align:left;font-weight:700">Tipo</th>';
    $body .= '<th style="padding:10px 12px;text-align:left;font-weight:700">Artículo / SKU</th>';
    $body .= '<th style="padding:10px 12px;text-align:right;font-weight:700">Cantidad</th>';
    $body .= '<th style="padding:10px 12px;text-align:left;font-weight:700">Fecha / Operador</th>';
    $body .= '<th style="padding:10px 12px;text-align:center;font-weight:700">Acciones</th>';
    $body .= '</tr>';
    $body .= '</thead>';
    $body .= '<tbody>';

    if ($recentEntries === []) {
        $body .= '<tr><td colspan="6" style="padding:32px 14px;text-align:center;color:#64748b">';
        $body .= '<div style="font-size:32px;margin-bottom:8px">📦</div>';
        $body .= 'Aún no se registran bultos con código de trazabilidad en esta bodega.<br>Complete el formulario para realizar el primer ingreso.';
        $body .= '</td></tr>';
    }

    foreach ($recentEntries as $entry) {
        $eType = strtoupper((string)($entry['entity_type'] ?? 'PRODUCT'));
        $eCode = (string)$entry['traceability_code'];
        $eSku = (string)$entry['item_code'];
        $eDesc = (string)$entry['item_description'];
        $eQty = (float)($entry['quantity'] ?? 1);
        $eUnit = (string)($entry['unit_name'] ?? 'UNID');
        $eWh = (string)($entry['warehouse_name'] ?? '');
        $eDate = date('d/m/Y H:i', strtotime((string)$entry['created_at']));
        $eOp = (string)($entry['operator_name'] ?? '');
        $pkgNum = (int)($entry['package_number'] ?? 1);
        $totPkg = (int)($entry['total_packages'] ?? 1);

        $body .= '<tr class="trz-entry-row" style="border-bottom:1px solid #f1f5f9" data-text="' . h(mb_strtolower($eCode . ' ' . $eSku . ' ' . $eDesc . ' ' . $eWh . ' ' . $eOp)) . '">';
        $body .= '<td style="padding:10px 12px;white-space:nowrap">';
        $body .= '<button type="button" class="trz-tag-code" style="cursor:pointer;border:1px solid #ccfbf1" onclick="openTraceabilityTreeModal(' . h(json_encode($eCode)) . ')" title="Ver árbol de trazabilidad 360° en vivo">' . h($eCode) . ' 🔍</button>';
        $body .= ' <a href="/stock/traceability?q=' . rawurlencode($eCode) . '" target="_blank" style="font-size:11.5px;color:#0f766e;text-decoration:none;font-weight:700" title="Abrir en pantalla completa">↗</a>';
        if ($totPkg > 1) {
            $body .= '<div style="font-size:10.5px;color:#0f766e;font-weight:700;margin-top:2px">Bulto ' . $pkgNum . ' / ' . $totPkg . '</div>';
        }
        $body .= '</td>';
        $body .= '<td style="padding:10px 12px;white-space:nowrap"><span class="badge-type ' . h($eType) . '">' . h($eType) . '</span></td>';
        $body .= '<td style="padding:10px 12px">';
        $body .= '<div style="font-weight:700;color:#0f172a">' . h($eSku) . '</div>';
        $body .= '<div style="font-size:11px;color:#64748b;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . h($eDesc) . '</div>';
        $body .= '</td>';
        $body .= '<td style="padding:10px 12px;text-align:right;font-weight:800;color:#0f172a;white-space:nowrap">' . number_format($eQty, 2, ',', '.') . ' <span style="font-size:11px;color:#64748b">' . h($eUnit) . '</span></td>';
        $body .= '<td style="padding:10px 12px;font-size:11.5px;color:#475569">';
        $body .= '<div style="font-weight:700;color:#334155">' . h($eDate) . '</div>';
        $body .= '<div style="font-size:11px;color:#64748b">👤 ' . h($eOp) . '</div>';
        $body .= '</td>';
        $body .= '<td style="padding:10px 12px;text-align:center;white-space:nowrap">';
        $body .= '<button type="button" class="btn secondary" style="padding:5px 9px;font-size:11.5px;font-weight:700;background:#f8fafc;border:1px solid #cbd5e1;color:#334155" onclick="openPrintLabel(' . h(json_encode($entry, JSON_HEX_APOS | JSON_HEX_QUOT)) . ')">🖨️ Etiqueta</button>';
        $body .= ' <button type="button" class="btn" style="padding:5px 9px;font-size:11.5px;font-weight:700;background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;margin-left:4px;cursor:pointer" onclick="deleteTraceabilityEntry(' . (int)$entry['id'] . ', ' . h(json_encode($eCode)) . ')" title="Eliminar este bulto ingresado por error">🗑️ Borrar</button>';
        $body .= '</td>';
        $body .= '</tr>';
    }

    $body .= '</tbody></table></div>';
    $body .= '</div>'; // Fin Columna 2

    $body .= '</div>'; // Fin trz-grid
    $body .= '</div>'; // Fin trz-shell

    // Formulario oculto para eliminación
    $body .= '<form id="form_delete_trz_entry" method="post" action="/stock/traceability-entry/delete" style="display:none">';
    $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
    $body .= '<input type="hidden" name="warehouse_id" value="' . $storehouseId . '">';
    $body .= '<input type="hidden" name="entry_id" id="delete_entry_id" value="0">';
    $body .= '<input type="hidden" name="delete_sku" id="delete_sku" value="">';
    $body .= '</form>';

    // MODAL DE IMPRESIÓN DE ETIQUETA
    $body .= '<div class="print-modal" id="printLabelModal" onclick="if(event.target===this)closePrintLabel()">';
    $body .= '<div class="print-card">';
    $body .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">';
    $body .= '<div style="font-weight:800;font-size:16px;color:#0f172a">🏷️ Etiqueta(s) con Código de Trazabilidad <span id="lbl_modal_label_count" style="font-size:13px;color:#00A9A6"></span></div>';
    $body .= '<button type="button" onclick="closePrintLabel()" style="border:none;background:none;font-size:22px;cursor:pointer;color:#64748b">&times;</button>';
    $body .= '</div>';

    // Barra de opciones de impresión múltiple (si se abrió para un registro con cantidad > 1)
    $body .= '<div id="multi_label_toolbar" style="display:none;background:#f0fdfa;border:1px solid #99f6e4;border-radius:10px;padding:10px 14px;margin-bottom:14px">';
    $body .= '<div style="font-size:12px;font-weight:700;color:#0f766e;margin-bottom:6px">⚙️ Opciones de impresión:</div>';
    $body .= '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
    $body .= '<button type="button" class="btn secondary" id="btn_split_labels" style="padding:4px 10px;font-size:12px;font-weight:700" onclick="splitCurrentEntryLabels()">🖨️ 1 Etiqueta por cada producto (<span id="lbl_split_count"></span> etiquetas)</button>';
    $body .= '<button type="button" class="btn secondary" id="btn_single_label" style="padding:4px 10px;font-size:12px;font-weight:700" onclick="singleCurrentEntryLabel()">🖨️ 1 Etiqueta lote consolidado</button>';
    $body .= '</div>';
    $body .= '</div>';

    $body .= '<div id="printableArea"></div>';

    $body .= '<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">';
    $body .= '<button type="button" class="btn secondary" onclick="closePrintLabel()">Cerrar</button>';
    $body .= '<button type="button" class="btn" style="background:#00A9A6" onclick="printLabelNow()">🖨️ Imprimir Todas las Etiquetas</button>';
    $body .= '</div>';
    $body .= '</div>';
    $body .= '</div>';

    // MODAL DE ÁRBOL DE TRAZABILIDAD 360° (Directo en inventario)
    $body .= '<div class="print-modal" id="traceabilityTreeModal" onclick="if(event.target===this)closeTraceabilityTreeModal()">';
    $body .= '<div class="print-card" style="width:min(900px, 96vw);max-height:92vh;overflow-y:auto;padding:24px">';
    $body .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid #e2e8f0;padding-bottom:12px">';
    $body .= '<div style="font-weight:900;font-size:18px;color:#0f172a;display:flex;align-items:center;gap:8px">';
    $body .= '<span>🌳</span> Árbol de Trazabilidad 360° <span id="trz_modal_code_title" style="font-size:14px;color:#00A9A6;font-family:monospace"></span>';
    $body .= '</div>';
    $body .= '<button type="button" onclick="closeTraceabilityTreeModal()" style="border:none;background:none;font-size:24px;cursor:pointer;color:#64748b">&times;</button>';
    $body .= '</div>';
    $body .= '<div id="trzTreeModalContent">';
    $body .= '<div style="text-align:center;padding:30px;color:#64748b">Cargando árbol de trazabilidad...</div>';
    $body .= '</div>';
    $body .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;border-top:1px solid #e2e8f0;padding-top:14px">';
    $body .= '<a id="btn_modal_fullscreen_tree" href="#" target="_blank" class="btn secondary" style="font-size:12px">Ver en Pantalla Completa ↗</a>';
    $body .= '<button type="button" class="btn" style="background:#64748b;color:#fff" onclick="closeTraceabilityTreeModal()">Cerrar</button>';
    $body .= '</div>';
    $body .= '</div>';
    $body .= '</div>';

    // JAVASCRIPT INTERACTIVO
    $body .= '<script>
        var autoPrintData = ' . json_encode($autoPrintEntries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';
        var currentEntryForSplit = null;

        function openTraceabilityTreeModal(code) {
            var modal = document.getElementById("traceabilityTreeModal");
            var title = document.getElementById("trz_modal_code_title");
            var content = document.getElementById("trzTreeModalContent");
            var fsBtn = document.getElementById("btn_modal_fullscreen_tree");
            if (!modal) return;
            modal.classList.add("open");
            title.textContent = code;
            fsBtn.href = "/stock/traceability?q=" + encodeURIComponent(code);
            content.innerHTML = "<div style=\"text-align:center;padding:40px;color:#64748b\"><div style=\"font-size:32px;margin-bottom:10px\">⏳</div>Cargando trazabilidad 360°...</div>";

            fetch("/stock/traceability/json?q=" + encodeURIComponent(code))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ok && data.html) {
                        content.innerHTML = data.html;
                    } else {
                        content.innerHTML = "<div class=\"err\" style=\"padding:16px;border-radius:10px\">⚠️ " + escapeHtml(data.error || "No se encontraron registros de trazabilidad para este código.") + "</div>";
                    }
                })
                .catch(function() {
                    content.innerHTML = "<div class=\"err\" style=\"padding:16px;border-radius:10px\">⚠️ Error al conectar con el servidor.</div>";
                });
        }

        function closeTraceabilityTreeModal() {
            var modal = document.getElementById("traceabilityTreeModal");
            if (modal) modal.classList.remove("open");
        }

        function changeWarehouse(whId) {
            window.location.href = "/stock/traceability-entry?bodega=" + encodeURIComponent(whId);
        }

        function setPackagingMode(mode) {
            document.getElementById("input_packaging_mode").value = mode;
            var isUnitary = (mode === "UNITARY");
            document.getElementById("btn_mode_unitary").classList.toggle("active", isUnitary);
            document.getElementById("btn_mode_pack").classList.toggle("active", !isUnitary);
            document.getElementById("sec_mode_unitary").style.display = isUnitary ? "block" : "none";
            document.getElementById("sec_mode_pack").style.display = isUnitary ? "none" : "block";

            var countPack = document.getElementById("input_package_count_pack");
            var countUnitary = document.getElementById("input_package_count");
            if (isUnitary) {
                countUnitary.name = "package_count";
                countPack.removeAttribute("name");
                document.getElementById("input_quantity").value = "1";
            } else {
                countUnitary.removeAttribute("name");
                countPack.name = "package_count";
                if (parseFloat(document.getElementById("input_quantity").value || "1") <= 1) {
                    document.getElementById("input_quantity").value = "1";
                }
            }
            calcTotals();
        }

        function selectEntityType(type) {
            document.getElementById("input_entity_type").value = type;
            document.querySelectorAll(".type-btn").forEach(function(b) {
                b.classList.toggle("active", b.getAttribute("data-type") === type);
            });
            var isPallet = (type === "PALLET");
            var groupBox = document.getElementById("group_box_count");
            if (groupBox) groupBox.style.display = isPallet ? "block" : "none";
            autoGenerateCode();
        }

        function autoGenerateCode() {
            var type = document.getElementById("input_entity_type").value || "PRODUCT";
            var prefix = "TRZ";
            if (type === "PALLET") prefix = "PL";
            if (type === "BOX") prefix = "BX";
            if (type === "ROLL") prefix = "RB";
            var now = new Date();
            var y = now.getFullYear();
            var m = String(now.getMonth() + 1).padStart(2, "0");
            var d = String(now.getDate()).padStart(2, "0");
            var rand = Math.random().toString(36).substring(2, 8).toUpperCase();
            var code = prefix + "-" + y + m + d + "-" + rand;
            document.getElementById("input_trace_code").value = code;
        }

        function calcTotals() {
            var mode = document.getElementById("input_packaging_mode").value || "UNITARY";
            var pkgs = 1;
            var unitsPerPkg = 1;

            if (mode === "UNITARY") {
                pkgs = parseInt(document.getElementById("input_package_count").value || "1", 10);
                if (isNaN(pkgs) || pkgs < 1) pkgs = 1;
                unitsPerPkg = 1;
                document.getElementById("input_quantity").value = "1";
            } else {
                pkgs = parseInt(document.getElementById("input_package_count_pack").value || "1", 10);
                if (isNaN(pkgs) || pkgs < 1) pkgs = 1;
                unitsPerPkg = parseFloat(document.getElementById("input_quantity").value || "1");
                if (isNaN(unitsPerPkg) || unitsPerPkg < 0) unitsPerPkg = 0;
            }

            var total = pkgs * unitsPerPkg;
            document.getElementById("lbl_calc_packages").textContent = pkgs;
            document.getElementById("lbl_calc_total").textContent = total.toLocaleString("es-CL", {minimumFractionDigits: 1, maximumFractionDigits: 2});
            
            var sel = document.getElementById("erp_item_select");
            var opt = (sel && sel.selectedIndex >= 0) ? sel.options[sel.selectedIndex] : null;
            var pending = (opt && opt.getAttribute("data-pending")) ? parseFloat(opt.getAttribute("data-pending")) : 0;
            var summaryBox = document.getElementById("box_calc_summary");
            if (summaryBox) {
                if (pending > 0 && total > pending) {
                    summaryBox.style.background = "#fff1f2";
                    summaryBox.style.borderColor = "#fecdd3";
                    summaryBox.style.color = "#be123c";
                    summaryBox.innerHTML = "⚠️ <strong>Atención:</strong> El total a rotular (" + total.toLocaleString("es-CL") + ") supera el stock pendiente de esta bodega (" + pending.toLocaleString("es-CL") + ").";
                } else {
                    summaryBox.style.background = "#f0fdfa";
                    summaryBox.style.borderColor = "#99f6e4";
                    summaryBox.style.color = "#0f766e";
                    if (mode === "UNITARY") {
                        summaryBox.innerHTML = "<strong>📊 Resumen de rotulación:</strong> Se generarán <strong>" + pkgs + " etiquetas únicas e individuales</strong> (1 por cada producto/bulto físico) para un total de <strong>" + total.toLocaleString("es-CL", {minimumFractionDigits: 1, maximumFractionDigits: 2}) + "</strong> unidades de producto.";
                    } else {
                        summaryBox.innerHTML = "<strong>📊 Resumen de rotulación:</strong> Se generarán <strong>" + pkgs + " etiquetas</strong> (1 por cada bulto agrupado) para un total de <strong>" + total.toLocaleString("es-CL", {minimumFractionDigits: 1, maximumFractionDigits: 2}) + "</strong> unidades de producto.";
                    }
                }
            }
        }

        function pickErpItem(sel) {
            var opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) return;
            var sku = opt.getAttribute("data-sku") || "";
            var desc = opt.getAttribute("data-desc") || "";
            var unit = opt.getAttribute("data-unit") || "UNID";
            var pending = parseFloat(opt.getAttribute("data-pending") || "1");
            if (isNaN(pending) || pending <= 0) pending = 1;

            document.getElementById("input_item_id").value = opt.value;
            document.getElementById("input_item_code").value = sku;
            document.getElementById("input_item_desc").value = desc;
            document.getElementById("input_unit_name").value = unit;

            // Rescatar especificaciones técnicas directamente del ERP (Ancho, Micras, Metros)
            var width = parseFloat(opt.getAttribute("data-width") || "0");
            var microns = parseFloat(opt.getAttribute("data-microns") || "0");
            var meters = parseFloat(opt.getAttribute("data-meters") || "0");
            var inputWidth = document.getElementById("input_width_mm");
            var inputMicrons = document.getElementById("input_microns");
            var inputMeters = document.getElementById("input_meters");
            if (inputWidth) inputWidth.value = width > 0 ? Math.round(width) : "";
            if (inputMicrons) inputMicrons.value = microns > 0 ? Math.round(microns) : "";
            if (inputMeters) inputMeters.value = meters > 0 ? meters : "";

            var specsBox = document.getElementById("erp_specs_badge");
            var specsText = document.getElementById("erp_specs_text");
            var specsArr = [];
            if (width > 0) specsArr.push("Ancho: " + Math.round(width) + " mm");
            if (microns > 0) specsArr.push("Micras: " + Math.round(microns) + " µm");
            if (meters > 0) specsArr.push("Largo: " + meters.toLocaleString("es-CL") + " m");
            if (specsBox && specsText) {
                if (specsArr.length > 0) {
                    specsText.textContent = specsArr.join(" · ");
                    specsBox.style.display = "block";
                } else {
                    specsBox.style.display = "none";
                }
            }

            // Detección automática de tipo y modalidad según nombre y unidad
            var upper = (desc + " " + unit).toUpperCase();
            if (upper.indexOf("BOB") !== -1 || upper.indexOf("ROLL") !== -1) {
                selectEntityType("ROLL");
                setPackagingMode("UNITARY");
                document.getElementById("input_package_count").value = Math.max(1, Math.round(pending));
            } else if (upper.indexOf("PALLET") !== -1) {
                selectEntityType("PALLET");
                setPackagingMode("UNITARY");
                document.getElementById("input_package_count").value = Math.max(1, Math.round(pending));
            } else if (upper.indexOf("CAJA") !== -1 || upper.indexOf("BOX") !== -1) {
                selectEntityType("BOX");
            } else {
                setPackagingMode("UNITARY");
                document.getElementById("input_package_count").value = Math.max(1, Math.round(pending));
            }

            calcTotals();
        }

        function filterRecentEntries(q) {
            q = (q || "").toLowerCase().trim();
            var rows = document.querySelectorAll(".trz-entry-row");
            var visibleCount = 0;
            rows.forEach(function(r) {
                var text = r.getAttribute("data-text") || "";
                var match = (q === "" || text.indexOf(q) !== -1);
                r.style.display = match ? "" : "none";
                if (match) visibleCount++;
            });
            var btnBulk = document.getElementById("btn_delete_filtered_sku");
            if (btnBulk) {
                btnBulk.style.display = (q.length >= 2 && visibleCount > 0) ? "inline-flex" : "none";
                btnBulk.innerHTML = "🗑️ Borrar los " + visibleCount + " bulto(s) mostrados de \x27" + escapeHtml(q) + "\x27";
            }
        }

        function escapeHtml(str) {
            var div = document.createElement("div");
            div.textContent = str || "";
            return div.innerHTML;
        }

        function buildLabelCardHtml(data, idx, totalCount) {
            var wh = data.warehouse_name || "Bodega";
            var code = data.traceability_code || data.code || "-";
            var type = (data.entity_type || "PRODUCTO").toUpperCase();
            var sku = (data.item_code || "-") + " · " + (data.unit_name || "UNID");
            var desc = data.item_description || "";
            var qty = (parseFloat(data.quantity) || 1).toLocaleString("es-CL", {minimumFractionDigits: 1, maximumFractionDigits: 2}) + " " + (data.unit_name || "UNID");
            var meta = (data.created_at || "") + " · Op: " + (data.operator_name || "");
            var bultoBadge = (totalCount > 1 || (data.total_packages && data.total_packages > 1))
                ? ("<div style=\"font-size:12.5px;font-weight:900;color:#0f766e;margin-bottom:4px;background:#f0fdfa;border:1px solid #ccfbf1;padding:2px 8px;border-radius:6px;display:inline-block\">BULTO " + (data.package_number || (idx + 1)) + " DE " + (data.total_packages || totalCount) + "</div>")
                : "";

            var specsArr = [];
            if (data.width_mm && parseFloat(data.width_mm) > 0) specsArr.push("Ancho: " + Math.round(data.width_mm) + " mm");
            if (data.microns && parseFloat(data.microns) > 0) specsArr.push("Micras: " + Math.round(data.microns) + " µm");
            if (data.meters && parseFloat(data.meters) > 0) specsArr.push("Largo: " + parseFloat(data.meters).toLocaleString("es-CL") + " m");
            var specsHtml = specsArr.length > 0 ? ("<div style=\"font-size:11px;font-weight:700;color:#0f766e;margin-bottom:4px\">📐 " + escapeHtml(specsArr.join(" · ")) + "</div>") : "";

            return "<div class=\"printable-label printable-label-page\" style=\"margin-top:" + (idx > 0 ? "14px" : "0") + "\">" +
                "<div style=\"font-size:16px;font-weight:900;color:#00A9A6;letter-spacing:1px;margin-bottom:2px\">UNIBAG CHILE</div>" +
                "<div style=\"font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:6px\">" + escapeHtml(wh) + "</div>" +
                bultoBadge +
                "<div class=\"barcode-visual\"></div>" +
                "<div style=\"font-family:monospace;font-size:20px;font-weight:900;color:#0f172a;letter-spacing:2px;margin:4px 0 8px\">" + escapeHtml(code) + "</div>" +
                "<div style=\"display:inline-block;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:800;margin-bottom:8px;background:#00A9A6;color:#fff\">" + escapeHtml(type) + "</div>" +
                "<div style=\"border-top:1px dashed #cbd5e1;padding-top:8px;text-align:left;font-size:12px\">" +
                    "<div style=\"font-weight:800;font-size:13px;color:#0f172a\">" + escapeHtml(sku) + "</div>" +
                    "<div style=\"color:#475569;margin-bottom:6px\">" + escapeHtml(desc) + "</div>" +
                    specsHtml +
                    "<div style=\"display:flex;justify-content:space-between;font-weight:800;font-size:14px;color:#0f172a\">" +
                        "<span>CANTIDAD:</span>" +
                        "<span style=\"color:#0f766e\">" + escapeHtml(qty) + "</span>" +
                    "</div>" +
                    "<div style=\"font-size:10px;color:#94a3b8;margin-top:8px;text-align:center\">" + escapeHtml(meta) + "</div>" +
                "</div>" +
            "</div>";
        }

        function renderLabelList(list) {
            var container = document.getElementById("printableArea");
            container.innerHTML = "";
            list.forEach(function(data, idx) {
                container.innerHTML += buildLabelCardHtml(data, idx, list.length);
            });
            var count = list.length;
            document.getElementById("lbl_modal_label_count").textContent = count > 1 ? (" (" + count + " etiquetas)") : " (1 etiqueta)";
        }

        function openPrintLabel(dataOrList) {
            var list = Array.isArray(dataOrList) ? dataOrList : [dataOrList];
            if (list.length === 0) return;

            var toolbar = document.getElementById("multi_label_toolbar");
            currentEntryForSplit = null;

            // Si es un solo registro pero tiene quantity > 1 (ej: 10 bobinas), dar la opción de imprimir 1 etiqueta por cada unidad
            if (list.length === 1 && parseFloat(list[0].quantity) > 1.0001) {
                currentEntryForSplit = list[0];
                var nUnits = Math.round(parseFloat(list[0].quantity));
                document.getElementById("lbl_split_count").textContent = nUnits;
                toolbar.style.display = "block";
                // Por defecto mostrar ya divididas en N etiquetas individuales para comodidad del bodeguero
                splitCurrentEntryLabels();
            } else {
                toolbar.style.display = "none";
                renderLabelList(list);
            }

            document.getElementById("printLabelModal").classList.add("open");
        }

        function splitCurrentEntryLabels() {
            if (!currentEntryForSplit) return;
            var base = currentEntryForSplit;
            var nUnits = Math.round(parseFloat(base.quantity));
            var splittedList = [];
            for (var i = 0; i < nUnits; i++) {
                splittedList.push({
                    warehouse_name: base.warehouse_name,
                    traceability_code: base.traceability_code + "-" + String(i + 1).padStart(2, "0"),
                    entity_type: base.entity_type,
                    item_code: base.item_code,
                    item_description: base.item_description,
                    quantity: 1.0,
                    unit_name: base.unit_name,
                    package_number: i + 1,
                    total_packages: nUnits,
                    operator_name: base.operator_name,
                    created_at: base.created_at
                });
            }
            renderLabelList(splittedList);
        }

        function singleCurrentEntryLabel() {
            if (!currentEntryForSplit) return;
            renderLabelList([currentEntryForSplit]);
        }

        function closePrintLabel() {
            document.getElementById("printLabelModal").classList.remove("open");
        }

        function printLabelNow() {
            var printable = document.getElementById("printableArea").innerHTML;
            var win = window.open("", "_blank", "width=520,height=700");
            win.document.write("<!doctype html><html><head><title>Imprimir Etiquetas</title><style>" +
                "@page { size: 100mm 70mm; margin: 0; }" +
                "* { box-sizing: border-box; }" +
                "body { font-family: -apple-system, BlinkMacSystemFont, Arial, sans-serif; margin: 0; padding: 0; background: #fff; color: #000; text-align: center; -webkit-print-color-adjust: exact; print-color-adjust: exact; }" +
                ".barcode-visual { height: 36px; margin: 4px auto; background: repeating-linear-gradient(90deg,#000 0,#000 2px,#fff 2px,#fff 4px,#000 4px,#000 7px,#fff 7px,#fff 9px); width: 85%; }" +
                ".printable-label { width: 100mm; height: 70mm; max-width: 100mm; max-height: 70mm; border: 1.5pt solid #000; border-radius: 4px; padding: 2.5mm 3.5mm; margin: 0 auto 10px auto; box-sizing: border-box; page-break-inside: avoid; break-inside: avoid; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; }" +
                "@media print {" +
                    "body { margin: 0 !important; padding: 0 !important; width: 100mm !important; height: 70mm !important; }" +
                    ".printable-label-page { width: 100mm !important; height: 70mm !important; margin: 0 !important; padding: 2.5mm 3.5mm !important; page-break-after: always !important; break-after: page !important; page-break-inside: avoid !important; break-inside: avoid !important; border: none !important; }" +
                    ".printable-label-page:last-child { page-break-after: auto !important; break-after: auto !important; margin-bottom: 0 !important; }" +
                "}" +
            "</style></head><body>" + printable + "</body></html>");
            win.document.close();
            win.focus();
            setTimeout(function() { win.print(); win.close(); }, 400);
        }

        function deleteTraceabilityEntry(id, code) {
            var msg = "¿Está seguro de eliminar el ingreso con código " + code + "?\\n\\nEsta acción borrará la etiqueta/bulto y liberará el stock para que vuelva a estar disponible.";
            if (confirm(msg)) {
                document.getElementById("delete_sku").value = "";
                document.getElementById("delete_entry_id").value = id;
                document.getElementById("form_delete_trz_entry").submit();
            }
        }

        function deleteAllFilteredBySku() {
            var q = (document.getElementById("trz-live-filter").value || "").trim();
            if (!q) return;
            var msg = "⚠️ ¿Está completamente seguro de eliminar TODOS los bultos mostrados para el artículo/filtro \x27" + q + "\x27 en esta bodega?\\n\\nEsta acción borrará las etiquetas y liberará el stock en bodega.";
            if (confirm(msg)) {
                document.getElementById("delete_entry_id").value = "0";
                document.getElementById("delete_sku").value = q;
                document.getElementById("form_delete_trz_entry").submit();
            }
        }

        // Si se abrió con filtro en URL, aplicar filtro inmediatamente
        var initFilterInput = document.getElementById("trz-live-filter");
        if (initFilterInput && initFilterInput.value.trim() !== "") {
            filterRecentEntries(initFilterInput.value);
        }

        // Si se generaron etiquetas en esta petición, abrir modal automáticamente
        if (autoPrintData && autoPrintData.length > 0) {
            setTimeout(function() { openPrintLabel(autoPrintData); }, 250);
        }
    </script>';

    render('Ingreso Trazable · Bodega', $body);
}

/**
 * Genera el HTML del árbol genealógico de trazabilidad 360° para visualización en inventario.
 *
 * @param array<string, mixed> $treeData
 */
function unibagBuildTraceabilityTreeHtml(array $treeData, bool $isModal = false): string
{
    $query = (string)($treeData['query'] ?? '');
    $whEntry = $treeData['warehouse_entry'] ?? null;
    $roll = $treeData['roll'] ?? null;
    $pallet = $treeData['pallet'] ?? null;
    $box = $treeData['box'] ?? null;
    $po = $treeData['purchase_order'] ?? null;
    $supplier = $treeData['supplier'] ?? null;
    $wo = $treeData['work_order'] ?? null;
    $events = (array)($treeData['events'] ?? []);
    $childRolls = (array)($treeData['child_rolls'] ?? []);
    $boxes = (array)($treeData['boxes'] ?? []);
    $movements = (array)($treeData['movements'] ?? []);

    $code = (string)($whEntry['traceability_code'] ?? $roll['roll_code'] ?? $box['box_code'] ?? $pallet['pallet_code'] ?? $query);
    $type = strtoupper((string)($whEntry['entity_type'] ?? ($roll ? 'ROLL' : ($box ? 'BOX' : ($pallet ? 'PALLET' : 'PRODUCT')))));
    $sku = (string)($whEntry['item_code'] ?? $roll['sku_code'] ?? $box['final_sku'] ?? $pallet['final_sku'] ?? '-');
    $desc = (string)($whEntry['item_description'] ?? $roll['sku_desc'] ?? '-');
    $qty = (float)($whEntry['quantity'] ?? $roll['received_qty'] ?? $box['units_qty'] ?? 1);
    $unit = (string)($whEntry['unit_name'] ?? 'UNID');
    $whName = (string)($whEntry['warehouse_name'] ?? $roll['warehouse_name'] ?? 'Bodega');
    $date = !empty($whEntry['created_at']) ? date('d/m/Y H:i', strtotime((string)$whEntry['created_at'])) : (!empty($roll['created_at']) ? date('d/m/Y H:i', strtotime((string)$roll['created_at'])) : '-');
    $operator = (string)($whEntry['operator_name'] ?? $roll['operator_name'] ?? '-');

    $width = !empty($whEntry['width_mm']) ? (float)$whEntry['width_mm'] : (!empty($roll['width_mm']) ? (float)$roll['width_mm'] : 0);
    $microns = !empty($whEntry['microns']) ? (float)$whEntry['microns'] : (!empty($roll['microns']) ? (float)$roll['microns'] : 0);
    $meters = !empty($whEntry['meters']) ? (float)$whEntry['meters'] : (!empty($roll['meters']) ? (float)$roll['meters'] : 0);

    $html = '<div class="tree-flow-wrap" style="font-family:inherit">';

    // Banner de identificación
    $html .= '<div style="background:linear-gradient(135deg, #0f172a, #1e293b);color:#fff;padding:16px 20px;border-radius:12px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">';
    $html .= '<div>';
    $html .= '<div style="font-size:11px;font-weight:700;color:#38bdf8;text-transform:uppercase;letter-spacing:.05em">Consulta de Trazabilidad 360°</div>';
    $html .= '<div style="font-family:monospace;font-size:20px;font-weight:900;color:#fff;letter-spacing:1px;margin-top:2px">' . h($code) . '</div>';
    $html .= '<div style="font-size:12.5px;color:#94a3b8;margin-top:2px">' . h($sku) . ' · ' . h($desc) . '</div>';
    $html .= '</div>';
    $html .= '<div style="display:flex;gap:8px;align-items:center">';
    $html .= '<span class="badge-type ' . h($type) . '" style="font-size:12px;padding:5px 12px">' . h($type) . '</span>';
    $statusText = (string)($whEntry['status'] ?? $roll['status'] ?? 'EN BODEGA');
    $html .= '<span style="font-size:11.5px;font-weight:800;background:#00A9A6;color:#fff;padding:5px 10px;border-radius:6px">' . h($statusText) . '</span>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div style="display:flex;flex-direction:column;gap:14px">';

    // NODO 1: Origen / Compra
    $html .= '<div class="trz-card" style="padding:16px;background:#fff">';
    $html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">';
    $html .= '<span style="font-size:11.5px;font-weight:800;color:#0369a1;background:#e0f2fe;border:1px solid #bae6fd;padding:3px 10px;border-radius:6px">🏢 1. Origen & Adquisición</span>';
    if ($po && !empty($po['date'])) {
        $html .= '<span style="font-size:11.5px;font-weight:700;color:#64748b">📅 ' . h((string)$po['date']) . '</span>';
    }
    $html .= '</div>';

    if ($po || $supplier) {
        $html .= '<div style="font-size:14px;font-weight:800;color:#0f172a">' . h((string)($supplier['name'] ?? 'Proveedor Nacional')) . '</div>';
        $html .= '<div style="font-size:12px;color:#64748b;margin-top:2px">RUT: <strong>' . h((string)($supplier['rut'] ?? '-')) . '</strong> · País: <strong>' . h((string)($supplier['country'] ?? 'Chile')) . '</strong></div>';
        if ($po && !empty($po['po_code'])) {
            $html .= '<div style="margin-top:8px;font-size:12.5px;color:#0369a1;background:#f0f9ff;padding:5px 10px;border-radius:6px;font-weight:700;display:inline-block">📄 Orden de Compra ERP: ' . h((string)$po['po_code']) . '</div>';
        }
    } else {
        $html .= '<div style="font-size:12.5px;color:#475569">Ingreso directo a existencias en bodega / Catálogo ERP (sin orden de compra importada asociada).</div>';
    }
    $html .= '</div>';

    // Conector
    $html .= '<div style="text-align:center;color:#00A9A6;font-size:18px;margin:-8px 0;user-select:none">⬇</div>';

    // NODO 2: Existencia FÍSICA en Bodega (Tarjeta Principal)
    $html .= '<div class="trz-card" style="padding:18px;border:2px solid #00A9A6;background:#fcfefe">';
    $html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">';
    $html .= '<span style="font-size:11.5px;font-weight:800;color:#0f766e;background:#f0fdfa;border:1px solid #99f6e4;padding:3px 10px;border-radius:6px">📦 2. Bulto / Existencia Física en Bodega</span>';
    $html .= '<span style="font-size:12px;font-weight:700;color:#0f766e">📍 ' . h($whName) . '</span>';
    $html .= '</div>';

    $html .= '<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">';
    $html .= '<div style="flex:1;min-width:240px">';
    $html .= '<div style="font-family:monospace;font-size:17px;font-weight:900;color:#0f766e">' . h($code) . '</div>';
    $html .= '<div style="font-size:14px;font-weight:800;color:#0f172a;margin-top:2px">' . h($sku) . '</div>';
    $html .= '<div style="font-size:12.5px;color:#475569;margin-top:2px">' . h($desc) . '</div>';
    $html .= '</div>';
    $html .= '<div style="text-align:right">';
    $html .= '<div style="font-size:20px;font-weight:900;color:#0f172a">' . number_format($qty, 2, ',', '.') . ' <span style="font-size:12px;color:#64748b">' . h($unit) . '</span></div>';
    $html .= '<div style="font-size:11px;color:#64748b;margin-top:2px">Cantidad rotulada</div>';
    $html .= '</div>';
    $html .= '</div>';

    if ($width > 0 || $microns > 0 || $meters > 0) {
        $html .= '<div style="margin-top:12px;background:#f0fdfa;border:1px dashed #99f6e4;padding:8px 12px;border-radius:8px;font-size:12px;color:#0f766e;display:flex;gap:12px;flex-wrap:wrap">';
        $html .= '<span>📐 <strong>Especificaciones Técnicas:</strong></span>';
        if ($width > 0) $html .= '<span>Ancho: <strong>' . (int)round($width) . ' mm</strong></span>';
        if ($microns > 0) $html .= '<span>Micras: <strong>' . (int)round($microns) . ' µm</strong></span>';
        if ($meters > 0) $html .= '<span>Largo: <strong>' . number_format($meters, 1, ',', '.') . ' m</strong></span>';
        $html .= '</div>';
    }

    $comments = trim((string)($whEntry['comments'] ?? ''));
    if ($comments !== '') {
        $html .= '<div style="margin-top:10px;font-size:12px;color:#64748b;background:#f8fafc;padding:6px 10px;border-radius:6px">💬 Observaciones: ' . h($comments) . '</div>';
    }

    $html .= '<div style="margin-top:12px;padding-top:10px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;font-size:11.5px;color:#64748b">';
    $html .= '<span>👤 Operador Responsable: <strong>' . h($operator) . '</strong></span>';
    $html .= '<span>📅 Fecha de Ingreso: <strong>' . h($date) . '</strong></span>';
    $html .= '</div>';
    $html .= '</div>';

    // Conector
    $html .= '<div style="text-align:center;color:#00A9A6;font-size:18px;margin:-8px 0;user-select:none">⬇</div>';

    // NODO 3: Transformación en Planta / OT
    $html .= '<div class="trz-card" style="padding:16px;background:#fff">';
    $html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">';
    $html .= '<span style="font-size:11.5px;font-weight:800;color:#b45309;background:#fffbeb;border:1px solid #fde68a;padding:3px 10px;border-radius:6px">⚙️ 3. Procesamiento en Planta (OT)</span>';
    if ($wo) {
        $html .= '<span style="font-size:12px;font-weight:800;color:#b45309">OT #' . h((string)($wo['prd_number'] ?? '')) . '</span>';
    } else {
        $html .= '<span style="font-size:11.5px;font-weight:700;color:#0f766e">✅ En Stock Bodega</span>';
    }
    $html .= '</div>';

    if ($wo) {
        $html .= '<div style="font-size:13.5px;font-weight:800;color:#0f172a">Máquina: ' . h((string)($wo['equipo_name'] ?? '')) . ' <span style="font-size:12px;color:#64748b">(' . h((string)($wo['type_ant_title'] ?? '')) . ')</span></div>';
        $html .= '<div style="font-size:12px;color:#475569;margin-top:2px">Operador OT: <strong>' . h((string)(($wo['wrk_firstname'] ?? '') . ' ' . ($wo['wrk_lastname'] ?? ''))) . '</strong></div>';
        if ($events !== []) {
            $html .= '<div style="margin-top:10px;background:#f8fafc;border-radius:8px;padding:8px 12px;font-size:11.5px">';
            $html .= '<div style="font-weight:700;color:#334155;margin-bottom:4px">Historial de eventos de máquina:</div>';
            foreach ($events as $ev) {
                $evTime = !empty($ev['evt_startdat']) ? date('H:i d/m', strtotime((string)$ev['evt_startdat'])) : '';
                $html .= '<div style="color:#64748b;margin-bottom:2px">⏱️ ' . h($evTime) . ' · ' . h((string)($ev['evt_desc'] ?? '')) . '</div>';
            }
            $html .= '</div>';
        }
    } else {
        $html .= '<div style="font-size:12.5px;color:#64748b">Bulto en estado de inventario disponible para uso o despacho (no ha sido transformado en máquina aún).</div>';
    }
    $html .= '</div>';

    // NODO 4: Bultos Hijos Resultantes (si aplica)
    if ($childRolls !== [] || $boxes !== []) {
        $html .= '<div style="text-align:center;color:#00A9A6;font-size:18px;margin:-8px 0;user-select:none">⬇</div>';
        $html .= '<div class="trz-card" style="padding:16px;background:#fff">';
        $html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">';
        $html .= '<span style="font-size:11.5px;font-weight:800;color:#6d28d9;background:#f5f3ff;border:1px solid #ddd6fe;padding:3px 10px;border-radius:6px">📦 4. Bultos Hijos Resultantes</span>';
        $html .= '<span style="font-size:11.5px;font-weight:700;color:#6d28d9">' . (count($childRolls) + count($boxes)) . ' generados</span>';
        $html .= '</div>';
        $html .= '<div style="display:flex;flex-direction:column;gap:6px">';
        foreach ($childRolls as $cRoll) {
            $html .= '<div style="background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;padding:8px 12px;display:flex;justify-content:space-between;align-items:center;font-size:12px">';
            $html .= '<div><span style="font-weight:800;color:#6d28d9">🌀 Bobina Hija: ' . h((string)$cRoll['roll_code']) . '</span> <span style="color:#64748b">(' . h((string)($cRoll['sku_code'] ?? '')) . ')</span></div>';
            $html .= '<div style="font-weight:800;color:#0f172a">' . number_format((float)($cRoll['received_qty'] ?? 1), 2, ',', '.') . '</div>';
            $html .= '</div>';
        }
        foreach ($boxes as $bx) {
            $html .= '<div style="background:#fffbeb;border:1px solid #fef3c7;border-radius:8px;padding:8px 12px;display:flex;justify-content:space-between;align-items:center;font-size:12px">';
            $html .= '<div><span style="font-weight:800;color:#b45309">📦 Caja: ' . h((string)$bx['box_code']) . '</span> <span style="color:#64748b">(' . h((string)($bx['final_sku'] ?? '')) . ')</span></div>';
            $html .= '<div style="font-weight:800;color:#0f172a">' . number_format((float)($bx['units_qty'] ?? 1), 0, ',', '.') . ' un</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }

    // NODO 5: Auditoría de Movimientos
    if ($movements !== []) {
        $html .= '<div style="text-align:center;color:#00A9A6;font-size:18px;margin:-8px 0;user-select:none">⬇</div>';
        $html .= '<div class="trz-card" style="padding:16px;background:#fff">';
        $html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">';
        $html .= '<span style="font-size:11.5px;font-weight:800;color:#334155;background:#f1f5f9;border:1px solid #cbd5e1;padding:3px 10px;border-radius:6px">📜 5. Cadena de Custodia & Movimientos</span>';
        $html .= '<span style="font-size:11.5px;font-weight:700;color:#64748b">' . count($movements) . ' registros</span>';
        $html .= '</div>';
        $html .= '<div style="display:flex;flex-direction:column;gap:6px">';
        foreach ($movements as $m) {
            $mDate = !empty($m['created_at']) ? date('d/m/Y H:i', strtotime((string)$m['created_at'])) : '-';
            $mType = (string)($m['movement_type'] ?? 'MOVIMIENTO');
            $toWh = (int)($m['to_warehouse_id'] ?? 0);
            $html .= '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px">';
            $html .= '<div><strong style="color:#0f766e">' . h($mType) . '</strong>' . ($toWh > 0 ? (' <span style="color:#64748b">→ Bodega #' . $toWh . '</span>') : '') . '</div>';
            $html .= '<div style="font-size:11px;color:#94a3b8">📅 ' . h($mDate) . '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '</div>'; // Fin flex column
    $html .= '</div>'; // Fin tree-flow-wrap

    return $html;
}

/**
 * Renderiza la pantalla del Árbol de Trazabilidad 360° dentro del módulo de Inventario.
 */
function unibagRenderStockTraceabilityTreePage(ReceptionService $service, ?ProductionService $prodService, string $currentOperatorName): void
{
    $service->ensureTraceabilityWarehouseEntriesTable();
    $bodegaId = isset($_GET['bodega']) ? (int)$_GET['bodega'] : 0;
    $query = trim((string)($_GET['q'] ?? ($_GET['query'] ?? '')));

    $treeData = null;
    $error = '';
    if ($query !== '') {
        if ($prodService !== null) {
            $treeData = $prodService->getTraceabilityTree($query);
        }
        if ($treeData === null || ($treeData['warehouse_entry'] === null && $treeData['roll'] === null && $treeData['box'] === null && $treeData['pallet'] === null && $treeData['work_order'] === null && $treeData['purchase_order'] === null)) {
            $error = 'No se encontraron registros de trazabilidad para "' . $query . '". Verifique el código ingresado.';
        }
    }

    $recentEntries = $service->listWarehouseTraceabilityEntries($bodegaId > 0 ? $bodegaId : null, null, 15);

    $body = '<div class="trz-shell">';

    // Header
    $body .= '<div class="trz-header">';
    $body .= '<div>';
    $body .= '<div class="trz-title"><span>🌳</span> Árbol Genealógico de Trazabilidad 360°</div>';
    $body .= '<div class="trz-sub">Auditoría completa e integral de existencias: origen en OC, rotulación en bodega, transformaciones en máquinas y bultos resultantes.</div>';
    $body .= '</div>';
    $body .= '<div style="display:flex;gap:8px;flex-wrap:wrap">';
    $body .= '<a class="btn secondary" href="/stock' . ($bodegaId > 0 ? ('?bodega=' . $bodegaId) : '') . '">← Volver a Stock</a>';
    $body .= '<a class="btn secondary" href="/stock/traceability-entry' . ($bodegaId > 0 ? ('?bodega=' . $bodegaId) : '') . '">📦 Ingreso Trazable</a>';
    $body .= '<a class="btn secondary" href="/stock/inventory-counts' . ($bodegaId > 0 ? ('?bodega=' . $bodegaId) : '') . '">Toma de Inventario</a>';
    $body .= '</div>';
    $body .= '</div>';

    // Buscador
    $body .= '<div class="trz-card" style="margin-bottom:20px;background:#f8fafc">';
    $body .= '<form method="get" action="/stock/traceability" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
    if ($bodegaId > 0) {
        $body .= '<input type="hidden" name="bodega" value="' . $bodegaId . '">';
    }
    $body .= '<div style="flex:1;min-width:280px">';
    $body .= '<input type="text" name="q" class="form-control" style="font-size:14px;background:#fff;border-color:#99f6e4" value="' . h($query) . '" placeholder="🔍 Ingrese código trazable (TRZ-...), bobina (ROLL-...), caja (BOX-...), pallet (PL-...), OT u OC..." required autofocus>';
    $body .= '</div>';
    $body .= '<button type="submit" class="btn" style="background:#00A9A6;color:#fff;padding:0 20px;height:42px;font-weight:700">🔍 Rastrear 360°</button>';
    if ($query !== '') {
        $body .= '<a class="btn secondary" href="/stock/traceability' . ($bodegaId > 0 ? ('?bodega=' . $bodegaId) : '') . '" style="height:42px;display:inline-flex;align-items:center">Limpiar</a>';
    }
    $body .= '</form>';

    // Chips de accesos rápidos recientes
    if ($recentEntries !== []) {
        $body .= '<div style="margin-top:14px;display:flex;gap:6px;align-items:center;flex-wrap:wrap">';
        $body .= '<span style="font-size:11.5px;font-weight:700;color:#64748b">Bultos recientes en bodega:</span>';
        foreach (array_slice($recentEntries, 0, 8) as $re) {
            $rCode = (string)$re['traceability_code'];
            $rSku = (string)$re['item_code'];
            $body .= '<a href="/stock/traceability?q=' . rawurlencode($rCode) . ($bodegaId > 0 ? ('&bodega=' . $bodegaId) : '') . '" class="trz-tag-code" style="font-size:11.5px;padding:2px 8px" title="' . h($rSku) . '">' . h($rCode) . '</a>';
        }
        $body .= '</div>';
    }
    $body .= '</div>';

    // Alertas o Resultados
    if ($error !== '') {
        $body .= '<div class="err" style="margin-bottom:20px;padding:16px 20px;border-radius:12px;font-weight:700">⚠️ ' . h($error) . '</div>';
    } elseif ($query !== '' && $treeData !== null) {
        $body .= unibagBuildTraceabilityTreeHtml($treeData, false);
    } else {
        // Pantalla vacía con guía
        $body .= '<div class="trz-card" style="text-align:center;padding:48px 20px">';
        $body .= '<div style="font-size:48px;margin-bottom:12px">🌳</div>';
        $body .= '<div style="font-size:18px;font-weight:800;color:#0f172a;margin-bottom:6px">Consulta de Trazabilidad 360°</div>';
        $body .= '<div style="font-size:13.5px;color:#64748b;max-width:540px;margin:0 auto 16px">Ingrese cualquier código de trazabilidad de bodega, bobina, caja, pallet, orden de trabajo u orden de compra para auditar el origen, medidas, operador y movimientos.</div>';
        $body .= '</div>';
    }

    $body .= '</div>'; // Fin trz-shell

    render('Árbol de Trazabilidad · Bodega', $body);
}

/**
 * Router de rutas de inventario.
 */
function handleInventoryRoutes(string $path, string $method, ReceptionService $service, string $currentOperatorName, ?ProductionService $prodService = null): bool
{
    // AJAX: Sugerir código de trazabilidad
    if ($path === '/stock/traceability-code-suggest' && $method === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        $type = (string)($_GET['type'] ?? 'PRODUCT');
        $code = $service->generateTraceabilityCode($type);
        echo json_encode(['ok' => true, 'code' => $code]);
        return true;
    }

    // POST: Eliminar ingreso a bodega con código de trazabilidad
    if ($path === '/stock/traceability-entry/delete' && $method === 'POST') {
        requireCsrf();
        $bodegaId = (int)($_POST['warehouse_id'] ?? 0);
        $entryId = (int)($_POST['entry_id'] ?? 0);
        $deleteSku = trim((string)($_POST['delete_sku'] ?? ''));

        if ($deleteSku !== '') {
            $res = $service->deleteWarehouseTraceabilityEntriesByItem($bodegaId, $deleteSku);
            if (!$res['ok']) {
                redirectResponse('/stock/traceability-entry?bodega=' . $bodegaId . '&error=' . rawurlencode((string)($res['error'] ?? 'Error al eliminar ingresos del artículo.')));
                return true;
            }
            $count = (int)($res['deleted_count'] ?? 0);
            redirectResponse('/stock/traceability-entry?bodega=' . $bodegaId . '&msg=' . rawurlencode("Se eliminaron {$count} bulto(s) correspondientes a '{$deleteSku}'. El stock ha sido liberado nuevamente."));
            return true;
        }

        $res = $service->deleteWarehouseTraceabilityEntry($entryId);
        if (!$res['ok']) {
            redirectResponse('/stock/traceability-entry?bodega=' . $bodegaId . '&error=' . rawurlencode((string)($res['error'] ?? 'Error al eliminar ingreso trazable.')));
            return true;
        }

        $code = (string)($res['code'] ?? '');
        redirectResponse('/stock/traceability-entry?bodega=' . $bodegaId . '&msg=' . rawurlencode("Registro de trazabilidad '{$code}' eliminado correctamente. El stock ha sido liberado nuevamente."));
        return true;
    }

    // POST: Guardar ingreso a bodega con código de trazabilidad
    if (($path === '/stock/traceability-entry' || $path === '/stock/ingreso-trazable') && $method === 'POST') {
        requireCsrf();
        $res = $service->registerWarehouseTraceabilityEntry($_POST);
        $bodegaId = (int)($_POST['warehouse_id'] ?? 0);
        if (!$res['ok']) {
            redirectResponse('/stock/traceability-entry?bodega=' . $bodegaId . '&error=' . rawurlencode((string)($res['error'] ?? 'Error al registrar ingreso trazable.')));
            return true;
        }

        $count = (int)($res['count'] ?? 1);
        $code = (string)($res['code'] ?? '');
        $type = (string)($res['entity_type'] ?? 'PRODUCT');
        $msg = ($count > 1)
            ? "Se generaron exitosamente {$count} bultos con códigos de trazabilidad ({$type})."
            : "Ingreso registrado exitosamente. Código de Trazabilidad: {$code} ({$type}).";

        $printCodesParam = '';
        if (!empty($res['codes'])) {
            $printCodesParam = '&print_codes=' . rawurlencode(implode(',', $res['codes']));
        }

        redirectResponse('/stock/traceability-entry?bodega=' . $bodegaId . '&msg=' . rawurlencode($msg) . $printCodesParam);
        return true;
    }

    // GET: Pantalla de ingreso a bodega con código de trazabilidad
    if (($path === '/stock/traceability-entry' || $path === '/stock/ingreso-trazable') && $method === 'GET') {
        unibagRenderTraceabilityEntryPage($service, $currentOperatorName);
        return true;
    }

    // AJAX: Modal árbol de trazabilidad en inventario
    if ($path === '/stock/traceability/json' && $method === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        $code = trim((string)($_GET['q'] ?? ($_GET['code'] ?? '')));
        if ($code === '' || $prodService === null) {
            echo json_encode(['ok' => false, 'error' => 'Código no proporcionado o servicio de producción no disponible']);
            return true;
        }
        $treeData = $prodService->getTraceabilityTree($code);
        $html = unibagBuildTraceabilityTreeHtml($treeData, true);
        echo json_encode(['ok' => true, 'html' => $html, 'tree' => $treeData]);
        return true;
    }

    // GET: Pantalla completa del Árbol de Trazabilidad 360° en Inventario / Bodega
    if (($path === '/stock/traceability' || $path === '/stock/arbol-trazabilidad') && $method === 'GET') {
        unibagRenderStockTraceabilityTreePage($service, $prodService, $currentOperatorName);
        return true;
    }

    if ($path === '/stock/inventory-counts' && $method === 'POST') {
        requireCsrf();
        if (!unibagCanUserPerformModifications()) {
            redirectResponse('/stock/inventory-counts?bodega=' . ((int)($_POST['bodega'] ?? 0)) . '&error=' . rawurlencode('Modificaciones restringidas exclusivamente a HECTOR y JAVIER.'));
            return true;
        }
        $storehouseId = (int)($_POST['bodega'] ?? 0);
        $storehouseName = trim((string)($_POST['storehouse_name'] ?? ''));
        $annotation = trim((string)($_POST['annotation'] ?? ''));
        $operatorName = trim((string)($_POST['operator_name'] ?? $currentOperatorName));

        $postedItems = is_array($_POST['items'] ?? null) ? $_POST['items'] : [];
        $items = [];
        foreach ($postedItems as $it) {
            if (!is_array($it)) continue;
            $itemId = (int)($it['item_id'] ?? 0);
            if ($itemId <= 0) continue;

            $sysQty = (float)($it['system_qty'] ?? 0);
            $phyQty = (float)($it['physical_qty'] ?? $sysQty);
            $comment = trim((string)($it['comment'] ?? ''));

            $items[] = [
                'item_id' => $itemId,
                'system_qty' => $sysQty,
                'physical_qty' => $phyQty,
                'comment' => $comment,
            ];
        }

        if ($items === []) {
            redirectResponse('/stock/inventory-counts?bodega=' . $storehouseId . '&error=' . rawurlencode('No hay líneas válidas para registrar en el inventario.'));
            return true;
        }

        $result = $service->createErpInventoryCount(
            $storehouseId,
            $storehouseName,
            $annotation,
            $operatorName,
            $items
        );

        if (!$result['ok']) {
            redirectResponse('/stock/inventory-counts?bodega=' . $storehouseId . '&error=' . rawurlencode(implode(' ', (array)($result['errors'] ?? ['Error desconocido.']))));
            return true;
        }

        $stcNum = (string)($result['stc_num'] ?? '');
        redirectResponse('/reports/inventory?msg=' . rawurlencode('Inventario ' . $stcNum . ' guardado exitosamente en el ERP unibag_unibag.'));
        return true;
    }

    if ($path === '/stock/inventory-counts' && $method === 'GET') {
        unibagRenderInventoryCountsPage($service);
        return true;
    }

    if ($path === '/stock' && $method === 'GET') {
        unibagRenderStockPage($service);
        return true;
    }

    return false;
}
