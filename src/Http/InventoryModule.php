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
        <div style="display:flex;gap:8px">
          <a class="btn secondary" href="/reports/inventory" style="display:inline-flex;align-items:center;gap:6px">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Informe de inventario
          </a>
          <a class="btn" href="/stock/inventory-counts?bodega=' . $storehouseId . '" style="display:inline-flex;align-items:center;gap:6px">
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
        <div style="padding:16px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
          <div style="font-weight:800;font-size:15px;color:#0f172a">Detalle de existencias por artículo</div>
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
              </tr>
            </thead>
            <tbody id="stock-tbody">';

    if ($items === []) {
        $body .= '<tr><td colspan="8" style="padding:30px;text-align:center;color:#64748b">No se encontraron artículos con los criterios seleccionados.</td></tr>';
    }

    foreach ($items as $it) {
        $inv = (float)($it['iss_inventory'] ?? 0);
        $res = (float)($it['iss_inventory_reserved'] ?? 0);
        $disp = (float)($it['available_qty'] ?? 0);
        $min = (float)($it['iss_inventory_min'] ?? 0);

        if ($disp <= 0 && $inv <= 0) {
            $statusBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:#f1f5f9;color:#64748b">Agotado</span>';
        } elseif ($min > 0 && $disp <= $min) {
            $statusBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:#fef2f2;color:#dc2626;border:1px solid #fecaca">Crítico</span>';
        } else {
            $statusBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0">Disponible</span>';
        }

        $body .= '<tr class="stock-row" style="border-bottom:1px solid #e2e8f0"'
            . ' data-inv="' . number_format($inv, 4, '.', '') . '"'
            . ' data-res="' . number_format($res, 4, '.', '') . '"'
            . ' data-disp="' . number_format($disp, 4, '.', '') . '"'
            . ' data-min="' . number_format($min, 4, '.', '') . '">'
            . '<td style="padding:10px 14px;font-weight:700;white-space:nowrap;color:#0f172a">' . h((string)$it['item_number_prod']) . '</td>'
            . '<td style="padding:10px 14px;font-weight:600;color:#334155">' . h((string)$it['item_title']) . '</td>'
            . '<td style="padding:10px 14px;text-align:center;white-space:nowrap;color:#64748b;font-weight:600">' . h((string)$it['unit_name']) . '</td>'
            . '<td style="padding:10px 14px;text-align:right;font-weight:700">' . number_format($inv, 2, ',', '.') . '</td>'
            . '<td style="padding:10px 14px;text-align:right;color:#64748b">' . number_format($res, 2, ',', '.') . '</td>'
            . '<td style="padding:10px 14px;text-align:right;font-weight:800;color:' . ($disp > 0 ? '#16a34a' : '#94a3b8') . '">' . number_format($disp, 2, ',', '.') . '</td>'
            . '<td style="padding:10px 14px;text-align:right;color:#64748b">' . number_format($min, 2, ',', '.') . '</td>'
            . '<td style="padding:10px 14px;text-align:center">' . $statusBadge . '</td>'
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
        . '</tr>'
        . '</tfoot>'
        . '</table></div></div>';

    // Script de filtrado en vivo que actualiza el tfoot
    $body .= '<script>
    function stockFilterTable(query) {
        var q = query.trim().toLowerCase();
        var rows = document.querySelectorAll("#stock-tbody .stock-row");
        var sumInv = 0, sumRes = 0, sumDisp = 0, sumMin = 0, visible = 0;
        rows.forEach(function(row) {
            var text = row.textContent.toLowerCase();
            var show = q === "" || text.indexOf(q) !== -1;
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

    $body .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding-top:16px;border-top:1px solid #e2e8f0">
        <div class="muted" style="font-size:12px">Al guardar, se creará el registro de recuento <code>IR0000XXX</code> en la base de datos ERP.</div>
        <button class="btn" type="submit"' . ($draftItems === [] ? ' disabled' : '') . ' style="padding:10px 24px;font-size:14px;font-weight:800">
          Guardar inventario realizado en ERP
        </button>
      </div>';

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
 * Router de rutas de inventario.
 */
function handleInventoryRoutes(string $path, string $method, ReceptionService $service, string $currentOperatorName): bool
{
    if ($path === '/stock/inventory-counts' && $method === 'POST') {
        requireCsrf();
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
