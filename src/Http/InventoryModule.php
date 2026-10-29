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
 * Normaliza el parámetro `bodega` para aceptar tanto el ID ERP como el código local de la bodega TRZ.
 *
 * En la UI se suele navegar con valores como `100`, `200`, etc., pero el módulo ERP
 * trabaja internamente con `company_shops_storehouses.id` (por ejemplo, `100 (MP PLA)` -> `5`).
 */
function unibagResolveRequestedStorehouseId(ReceptionService $service, array $storehouses, int $requestedId): int
{
    if ($requestedId <= 0) {
        return 0;
    }

    foreach ($storehouses as $st) {
        if ((int)($st['id'] ?? 0) === $requestedId) {
            return $requestedId;
        }
    }

    try {
        foreach ($service->listWarehousesWithCapacities() as $warehouse) {
            $localId = (int)($warehouse['id'] ?? 0);
            $localCode = (int)($warehouse['code'] ?? 0);
            if ($localId !== $requestedId && $localCode !== $requestedId) {
                continue;
            }

            $erpStorehouseId = (int)($warehouse['erp_storehouse_id'] ?? 0);
            if ($erpStorehouseId <= 0) {
                break;
            }

            foreach ($storehouses as $st) {
                if ((int)($st['id'] ?? 0) === $erpStorehouseId) {
                    return $erpStorehouseId;
                }
            }

            return $erpStorehouseId;
        }
    } catch (Throwable) {
        return $requestedId;
    }

    return $requestedId;
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
 * Emite la planilla Excel con el detalle de bobinas físicas y trazabilidad de la bodega en formato XLS HTML.
 *
 * @param array<int, array<string, mixed>> $rolls
 */
function unibagOutputWarehouseRollsExcel(string $filename, array $rolls, string $storehouseName): void
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
    echo '<tr><th colspan="12" style="font-size:14px;padding:10px;background:#1e293b;color:#fff">Bobinas con Trazabilidad en Bodega - ' . h($storehouseName) . ' (' . date('d.m.Y H:i') . ')</th></tr>';
    echo '<tr><th>N°</th><th>Código Bobina</th><th>Código Trazabilidad</th><th>SKU</th><th>Descripción Artículo</th><th>Peso (Kg)</th><th>Ancho (mm)</th><th>Micras (µm)</th><th>Metros (m)</th><th>Origen (Cont./OC)</th><th>Fecha Registro</th><th>Operador</th></tr>';
    $idx = 0;
    $sumWeight = 0.0;
    $sumMeters = 0.0;
    foreach ($rolls as $r) {
        $idx++;
        $w = (float)($r['weight_kg'] ?? 0);
        $m = (float)($r['meters'] ?? 0);
        $sumWeight += $w;
        $sumMeters += $m;
        $origin = '';
        if (!empty($r['container_code'])) {
            $origin = 'Cont. ' . $r['container_code'];
        } elseif (!empty($r['import_container_id'])) {
            $origin = 'Cont. #' . $r['import_container_id'];
        } elseif (!empty($r['purchase_order_id'])) {
            $origin = 'OC #' . $r['purchase_order_id'];
        }
        $trzCode = (string)($r['traceability_code'] ?? ($r['roll_code'] ?? ''));
        echo '<tr>';
        echo '<td class="num-int">' . $idx . '</td>';
        echo '<td class="text-cell" style="font-weight:700">' . h((string)($r['roll_code'] ?? '')) . '</td>';
        echo '<td class="text-cell" style="font-weight:700;color:#0f766e">' . h($trzCode) . '</td>';
        echo '<td class="text-cell">' . h((string)($r['sku_code'] ?? '')) . '</td>';
        echo '<td>' . h((string)($r['sku_description'] ?? '')) . '</td>';
        echo '<td class="num-dec3" style="font-weight:700">' . number_format($w, 3, '.', '') . '</td>';
        echo '<td class="num-int">' . (int)($r['width_mm'] ?? 0) . '</td>';
        echo '<td class="num-int">' . (int)($r['microns'] ?? 0) . '</td>';
        echo '<td class="num-dec3">' . number_format($m, 2, '.', '') . '</td>';
        echo '<td class="text-cell">' . h($origin) . '</td>';
        echo '<td class="text-cell">' . h((string)($r['created_at'] ?? '')) . '</td>';
        echo '<td>' . h((string)($r['operator_name'] ?? '')) . '</td>';
        echo '</tr>';
    }
    if ($rolls === []) {
        echo '<tr><td colspan="12" style="text-align:center;padding:12px;color:#64748b">Sin bobinas físicas registradas en esta bodega.</td></tr>';
    } else {
        echo '<tr style="background:#0f172a;color:#fff;font-weight:700">';
        echo '<td colspan="5" style="padding:8px;text-align:right;color:#fff;font-weight:700">TOTAL (' . count($rolls) . ' bobinas)</td>';
        echo '<td class="num-dec3" style="color:#fff;font-weight:700">' . number_format($sumWeight, 3, '.', '') . '</td>';
        echo '<td colspan="2"></td>';
        echo '<td class="num-dec3" style="color:#fff;font-weight:700">' . number_format($sumMeters, 2, '.', '') . '</td>';
        echo '<td colspan="3"></td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</body></html>';
    $html = ob_get_clean();

    SimpleXlsx::streamHtml($filename, $html, 'Bobinas');
}

/**
 * Renderiza la pantalla principal de stock por bodega conectada al ERP unibag_unibag.
 */
function unibagRenderStockPage(ReceptionService $service): void
{
    // Listar solo las bodegas que tienen inventario registrado
    $storehouses = $service->listErpStorehouses(true);
    $requestedStId = isset($_GET['bodega']) ? (int)$_GET['bodega'] : 0;
    $requestedStId = unibagResolveRequestedStorehouseId($service, $storehouses, $requestedStId);
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

    $warehouseRolls = $storehouseId > 0 ? $service->listRollsForWarehouseInventory($storehouseId) : [];
    $totalWarehouseRolls = count($warehouseRolls);
    $totalWarehouseRollsWeight = array_sum(array_column($warehouseRolls, 'weight_kg'));
    $totalWarehouseRollsMeters = array_sum(array_column($warehouseRolls, 'meters'));

    if (isset($_GET['download']) && (string)$_GET['download'] === 'excel') {
        unibagOutputErpStockExcel(
            'inventario-erp-bodega-' . $storehouseId . '-' . date('Ymd-His') . '.xlsx',
            $items,
            $storehouseName
        );
    }

    if (isset($_GET['download']) && (string)$_GET['download'] === 'rolls_excel') {
        unibagOutputWarehouseRollsExcel(
            'bobinas-trazabilidad-bodega-' . $storehouseId . '-' . date('Ymd-His') . '.xlsx',
            $warehouseRolls,
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

    if ($trzRolls === 0 && $totalWarehouseRolls > 0) {
        $trzRolls = $totalWarehouseRolls;
    }

    $stockMessage = trim((string)($_GET['msg'] ?? ''));
    $stockError = trim((string)($_GET['error'] ?? ''));
    $activeMainTab = (isset($_GET['tab']) && (string)$_GET['tab'] === 'rolls') ? 'rolls' : 'items';

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
          <input type="hidden" name="tab" id="hidden_active_tab" value="' . h($activeMainTab) . '">
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
            <a class="btn secondary" href="/stock?bodega=' . $storehouseId . '&search=' . rawurlencode($search) . '&only_stock=' . ($onlyWithStock ? '1' : '0') . '&download=excel" style="height:40px;display:inline-flex;align-items:center;gap:6px;padding:0 16px" title="Exportar existencias ERP a Excel">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
              Excel ERP
            </a>
            <a class="btn secondary" href="/stock?bodega=' . $storehouseId . '&download=rolls_excel" style="height:40px;display:inline-flex;align-items:center;gap:6px;padding:0 16px;background:#f0fdfa;border-color:#99f6e4;color:#0f766e" title="Exportar bobinas con trazabilidad a Excel">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
              Excel Bobinas (' . $totalWarehouseRolls . ')
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
              . (int)$trzPallets . ' pallets · <a href="#panel_stock_rolls" onclick="switchStockMainTab(\'rolls\'); return false;" style="color:#1d4ed8;font-weight:800;text-decoration:underline" title="Ver detalle de todas las bobinas físicas con código de trazabilidad">' . (int)$trzRolls . ' bobinas</a> · ' . (int)$trzBoxes . ' cajas</div>
          </div>
        </div>
      </div>';

    // Pestañas principales de navegación: Artículos ERP vs Bobinas Físicas con Trazabilidad
    $body .= '<div style="display:flex;gap:10px;margin-bottom:16px;border-bottom:2px solid #e2e8f0;padding-bottom:10px;flex-wrap:wrap">
        <button type="button" id="tab_btn_items" class="stock-main-tab-btn" onclick="switchStockMainTab(\'items\')" style="padding:9px 18px;border-radius:8px;font-weight:800;font-size:13.5px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;' . ($activeMainTab === 'items' ? 'background:#0f172a;color:#fff;border:1px solid #0f172a;' : 'background:#fff;color:#475569;border:1px solid #cbd5e1;') . '">
          📦 Existencias ERP (' . count($items) . ' artículos)
        </button>
        <button type="button" id="tab_btn_rolls" class="stock-main-tab-btn" onclick="switchStockMainTab(\'rolls\')" style="padding:9px 18px;border-radius:8px;font-weight:800;font-size:13.5px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;' . ($activeMainTab === 'rolls' ? 'background:#0f172a;color:#fff;border:1px solid #0f172a;' : 'background:#fff;color:#475569;border:1px solid #cbd5e1;') . '">
          🏷️ Bobinas con Trazabilidad (' . $totalWarehouseRolls . ' bobinas · ' . number_format($totalWarehouseRollsWeight, 1, ',', '.') . ' Kg)
        </button>
      </div>';

    // PANEL 1: Existencias por Artículo (ERP)
    $body .= '<div id="panel_stock_items" style="' . ($activeMainTab === 'items' ? '' : 'display:none') . '">';
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
        $viewCodesUrl = '/stock/traceability-entry?bodega=' . $storehouseId
            . '&q=' . rawurlencode($sku)
            . '&sku=' . rawurlencode($sku)
            . '&desc=' . rawurlencode((string)$it['item_title'])
            . '&unit=' . rawurlencode((string)$it['unit_name']);

        if ($disp <= 0 && $inv <= 0) {
            $statusBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:#f1f5f9;color:#64748b">Agotado</span>';
        } elseif ($min > 0 && $disp <= $min) {
            $statusBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:#fef2f2;color:#dc2626;border:1px solid #fecaca">Crítico</span>';
        } else {
            $statusBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0">Disponible</span>';
        }

        // Columna de trazabilidad
        if ($isFullyTagged) {
            $trzButton = '<div style="display:inline-flex;align-items:center;gap:6px">';
            $trzButton .= '<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:11.5px;font-weight:700;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0" title="Todo el stock físico está rotulado con código de trazabilidad (' . number_format($alreadyTagged, 1, ',', '.') . ' / ' . number_format($inv, 1, ',', '.') . ' ' . h((string)$it['unit_name']) . ')">✅ Rotulado</span>';
            $trzButton .= '<a class="btn secondary" href="' . $viewCodesUrl . '" style="padding:3px 7px;font-size:11px;font-weight:700;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:3px" title="Ver todos los códigos de trazabilidad registrados para ' . h($sku) . '">🔎 Códigos</a>';
            $trzButton .= '<a class="btn" href="' . $viewCodesUrl . '" style="padding:3px 7px;font-size:11px;font-weight:700;background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:3px" title="Ver y borrar bultos registrados por error para ' . h($sku) . '">🗑️ Borrar</a>';
            $trzButton .= '</div>';
        } elseif ($isPartiallyTagged) {
            $trzButton = '<div style="display:inline-flex;align-items:center;gap:6px">';
            $trzButton .= '<a class="btn secondary" href="/stock/traceability-entry?bodega=' . $storehouseId . '&item_id=' . $itemId . '&sku=' . rawurlencode($sku) . '&desc=' . rawurlencode((string)$it['item_title']) . '&unit=' . rawurlencode((string)$it['unit_name']) . '" style="padding:4px 8px;font-size:11.5px;font-weight:700;background:#fffbeb;color:#b45309;border:1px solid #fde68a;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:4px" title="Faltan ' . number_format($pendingQty, 1, ',', '.') . ' por rotular de ' . number_format($inv, 1, ',', '.') . '">📦 +Trazable <span style="font-size:10px;background:#fef3c7;padding:1px 5px;border-radius:4px">(' . number_format($pendingQty, 0, ',', '.') . ' pend)</span></a>';
            $trzButton .= '<a class="btn secondary" href="' . $viewCodesUrl . '" style="padding:4px 7px;font-size:11px;font-weight:700;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:3px" title="Ver todos los códigos de trazabilidad ya rotulados para ' . h($sku) . '">🔎 Códigos</a>';
            $trzButton .= '<a class="btn" href="' . $viewCodesUrl . '" style="padding:4px 7px;font-size:11px;font-weight:700;background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:3px" title="Ver y borrar bultos ya rotulados de este artículo">🗑️</a>';
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
    $body .= '</div>'; // Fin panel_stock_items

    // PANEL 2: Bobinas Físicas en Bodega con Código de Trazabilidad
    $body .= '<div id="panel_stock_rolls" style="' . ($activeMainTab === 'rolls' ? '' : 'display:none') . '">';
    $body .= '<div class="card" style="border-radius:14px;padding:0;overflow:hidden;box-shadow:0 2px 10px rgba(15,23,42,.03)">
        <div style="padding:16px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
          <div>
            <div style="font-weight:800;font-size:16px;color:#0f172a;display:flex;align-items:center;gap:8px">
              🏷️ Bobinas Físicas en Bodega con Código de Trazabilidad
              <span id="rolls-visible-badge" style="font-size:11.5px;font-weight:800;background:#e0f2fe;color:#0369a1;padding:2px 8px;border-radius:12px">' . $totalWarehouseRolls . ' bobinas</span>
              <span id="rolls-weight-badge" style="font-size:11.5px;font-weight:800;background:#f0fdf4;color:#16a34a;padding:2px 8px;border-radius:12px">' . number_format($totalWarehouseRollsWeight, 2, ',', '.') . ' Kg</span>
            </div>
            <div style="font-size:12px;color:#64748b;margin-top:3px">
              Detalle de cada bobina física con su código de trazabilidad 360°, peso real capturado en balanza, especificaciones y procedencia.
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <input id="stock-rolls-live-filter" type="text" placeholder="🔍 Filtrar bobina, trazabilidad, SKU, contenedor..." style="height:36px;padding:0 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:12px;width:260px" oninput="filterStockRollsTable(this.value)">
            <a class="btn secondary" href="/stock?bodega=' . $storehouseId . '&download=rolls_excel" style="height:36px;display:inline-flex;align-items:center;gap:6px;padding:0 14px;font-size:12px">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
              Descargar Excel
            </a>
            <a class="btn" href="/stock/traceability-entry?bodega=' . $storehouseId . '" style="background:#00A9A6;height:36px;display:inline-flex;align-items:center;gap:6px;padding:0 14px;font-size:12px">
              📦 + Ingreso Trazable
            </a>
          </div>
        </div>
        <div class="table-wrap" style="max-height:680px;overflow-y:auto">
          <table id="stock-rolls-table" style="width:100%;margin:0;border-collapse:collapse;font-size:12.5px">
            <thead>
              <tr style="background:#0f172a;color:#fff;position:sticky;top:0;z-index:2">
                <th style="padding:10px 12px;text-align:center;width:40px">N°</th>
                <th style="padding:10px 12px;text-align:left">Código Bobina</th>
                <th style="padding:10px 12px;text-align:left">Cód. Trazabilidad</th>
                <th style="padding:10px 12px;text-align:left">Artículo / SKU</th>
                <th style="padding:10px 12px;text-align:right">Peso Real (Kg)</th>
                <th style="padding:10px 12px;text-align:center">Especificación</th>
                <th style="padding:10px 12px;text-align:left">Origen (Cont./OC)</th>
                <th style="padding:10px 12px;text-align:left">Fecha / Operador</th>
                <th style="padding:10px 12px;text-align:center">Estado</th>
                <th style="padding:10px 12px;text-align:center">Acciones</th>
              </tr>
            </thead>
            <tbody id="stock-rolls-tbody">';

    if ($warehouseRolls === []) {
        $body .= '<tr><td colspan="10" style="padding:36px;text-align:center;color:#64748b">';
        $body .= '<div style="font-size:32px;margin-bottom:8px">📦</div>';
        $body .= 'No hay bobinas físicas registradas en esta bodega actualmente.<br>Las bobinas recepcionadas en cargamentos aparecerán automáticamente aquí.';
        $body .= '</td></tr>';
    }

    $rollIdx = 0;
    foreach ($warehouseRolls as $r) {
        $rollIdx++;
        $rId = (int)$r['id'];
        $rCode = (string)($r['roll_code'] ?? '');
        $rTrzCode = (string)($r['traceability_code'] ?? $rCode);
        $rSku = (string)($r['sku_code'] ?? '');
        $rDesc = (string)($r['sku_description'] ?? '');
        $rWeight = (float)($r['weight_kg'] ?? 0);
        $rWidth = (int)($r['width_mm'] ?? 0);
        $rMicrons = (int)($r['microns'] ?? 0);
        $rMeters = (float)($r['meters'] ?? 0);
        $rDate = !empty($r['created_at']) ? date('d/m/Y H:i', strtotime((string)$r['created_at'])) : '-';
        $rOp = (string)($r['operator_name'] ?? '');
        $rStatus = (string)($r['status'] ?? 'RECEIVED');

        $originLabel = '';
        if (!empty($r['container_code'])) {
            $originLabel = '<a href="/import-containers/' . (int)$r['import_container_id'] . '" style="color:#0284c7;font-weight:700;text-decoration:none" title="Ver contenedor internacional">🚢 Cont. ' . h((string)$r['container_code']) . '</a>';
        } elseif (!empty($r['import_container_id'])) {
            $originLabel = '<a href="/import-containers/' . (int)$r['import_container_id'] . '" style="color:#0284c7;font-weight:700;text-decoration:none">🚢 Cont. #' . (int)$r['import_container_id'] . '</a>';
        } elseif (!empty($r['purchase_order_id'])) {
            $originLabel = '<a href="/purchase-orders/' . (int)$r['purchase_order_id'] . '" style="color:#0284c7;font-weight:700;text-decoration:none">📄 OC #' . (int)$r['purchase_order_id'] . '</a>';
        } else {
            $originLabel = '<span class="muted">-</span>';
        }

        $specLabel = '';
        if ($rWidth > 0 || $rMicrons > 0 || $rMeters > 0) {
            $specLabel = ($rWidth > 0 ? $rWidth . 'mm' : '')
                . ($rMicrons > 0 ? ' × ' . $rMicrons . 'µm' : '')
                . ($rMeters > 0 ? ' × ' . number_format($rMeters, 0, ',', '.') . 'm' : '');
        } else {
            $specLabel = '<span class="muted">-</span>';
        }

        $rowFilterText = mb_strtolower($rCode . ' ' . $rTrzCode . ' ' . $rSku . ' ' . $rDesc . ' ' . $rOp . ' ' . $rDate);

        $body .= '<tr class="stock-roll-row" style="border-bottom:1px solid #f1f5f9"'
            . ' data-text="' . h($rowFilterText) . '"'
            . ' data-weight="' . number_format($rWeight, 3, '.', '') . '"'
            . ' data-meters="' . number_format($rMeters, 2, '.', '') . '">';
        $body .= '<td style="padding:10px 12px;text-align:center;color:#64748b;font-size:11.5px">' . $rollIdx . '</td>';
        $body .= '<td style="padding:10px 12px;white-space:nowrap;font-family:monospace;font-weight:800;color:#0f172a">' . h($rCode) . '</td>';
        $body .= '<td style="padding:10px 12px;white-space:nowrap">';
        $body .= '<button type="button" class="trz-tag-code" style="cursor:pointer;border:1px solid #ccfbf1" onclick="openTraceabilityTreeModal(' . h(json_encode($rTrzCode)) . ')" title="Ver árbol de trazabilidad 360° en vivo">' . h($rTrzCode) . ' 🔍</button>';
        $body .= ' <a href="/stock/traceability?q=' . rawurlencode($rTrzCode) . '" target="_blank" style="font-size:11.5px;color:#0f766e;text-decoration:none;font-weight:700" title="Abrir árbol en pantalla completa">↗</a>';
        $body .= '</td>';
        $body .= '<td style="padding:10px 12px">';
        $body .= '<div style="font-weight:700;color:#0f172a">' . h($rSku) . '</div>';
        $body .= '<div style="font-size:11px;color:#64748b;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . h($rDesc) . '</div>';
        $body .= '</td>';
        $body .= '<td style="padding:10px 12px;text-align:right;font-weight:800;font-size:13.5px;color:#0f172a;white-space:nowrap">' . number_format($rWeight, 3, ',', '.') . ' <span style="font-size:11px;color:#64748b;font-weight:600">Kg</span></td>';
        $body .= '<td style="padding:10px 12px;text-align:center;font-size:11.5px;color:#475569;white-space:nowrap">' . $specLabel . '</td>';
        $body .= '<td style="padding:10px 12px;white-space:nowrap">' . $originLabel . '</td>';
        $body .= '<td style="padding:10px 12px;font-size:11.5px;color:#475569;white-space:nowrap">';
        $body .= '<div style="font-weight:700;color:#334155">' . h($rDate) . '</div>';
        if ($rOp !== '') {
            $body .= '<div style="font-size:10.5px;color:#64748b">👤 ' . h($rOp) . '</div>';
        }
        $body .= '</td>';
        $body .= '<td style="padding:10px 12px;text-align:center;white-space:nowrap"><span class="badge-type ' . ($rStatus === 'RECEIVED' ? 'PRODUCT' : 'BOX') . '" style="font-size:10.5px">' . ($rStatus === 'RECEIVED' ? '✅ En Bodega' : h($rStatus)) . '</span></td>';
        $body .= '<td style="padding:10px 12px;text-align:center;white-space:nowrap">';
        $body .= '<div style="display:inline-flex;gap:4px;align-items:center">';
        $body .= '<a class="btn" style="padding:4px 8px;font-size:11px;border-radius:6px;background:#0284c7;border-color:#0284c7;color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:3px;font-weight:700" href="/rolls/' . $rId . '/label?auto_print=1" target="_blank" title="Imprimir etiqueta física 10x7 cm">🖨️ 10x7</a>';
        $body .= '<a class="btn secondary" style="padding:4px 7px;font-size:11px;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:3px" href="/rolls/' . $rId . '/label?server_print=1" target="_blank" title="Impresión térmica directa a impresora de red">⚡</a>';
        $body .= '<button type="button" class="btn secondary" style="padding:4px 7px;font-size:11px;border-radius:6px;cursor:pointer" onclick="openTraceabilityTreeModal(' . h(json_encode($rTrzCode)) . ')" title="Ver árbol de trazabilidad 360°">🌳</button>';
        $body .= '</div>';
        $body .= '</td>';
        $body .= '</tr>';
    }

    $body .= '</tbody>'
        . '<tfoot id="stock-rolls-tfoot">'
        . '<tr style="background:#0f172a;color:#fff;border-top:2px solid #334155">'
        . '<td colspan="4" style="padding:12px 14px;font-weight:800;font-size:13px;color:#e2e8f0">'
        . 'TOTAL &mdash; <span id="tfoot-rolls-count">' . $totalWarehouseRolls . '</span> bobinas registradas'
        . '</td>'
        . '<td style="padding:12px 14px;text-align:right;font-weight:900;font-size:13px;color:#4ade80" id="tfoot-rolls-weight">' . number_format($totalWarehouseRollsWeight, 3, ',', '.') . ' Kg</td>'
        . '<td style="padding:12px 14px;text-align:center;font-weight:700;font-size:12px;color:#93c5fd" id="tfoot-rolls-meters">' . number_format($totalWarehouseRollsMeters, 0, ',', '.') . ' m</td>'
        . '<td colspan="4" style="padding:12px 14px"></td>'
        . '</tr>'
        . '</tfoot>'
        . '</table></div></div>';
    $body .= '</div>'; // Fin panel_stock_rolls

    // MODAL ÁRBOL DE TRAZABILIDAD 360°
    $body .= '<div class="print-modal" id="traceabilityTreeModal" onclick="if(event.target===this)closeTraceabilityTreeModal()">';
    $body .= '<div class="print-card" style="width:min(920px,96vw);max-height:88vh;padding:22px;border-radius:16px">';
    $body .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;border-bottom:1px solid #e2e8f0;padding-bottom:10px">';
    $body .= '<div style="font-weight:800;font-size:16px;color:#0f172a">🌳 Árbol de Trazabilidad 360° · <span id="trz_modal_code_title" style="color:#0f766e;font-family:monospace"></span></div>';
    $body .= '<div style="display:flex;gap:8px;align-items:center">';
    $body .= '<a id="btn_modal_fullscreen_tree" href="#" target="_blank" class="btn secondary" style="font-size:11.5px;padding:4px 10px;text-decoration:none">Pantalla completa ↗</a>';
    $body .= '<button type="button" onclick="closeTraceabilityTreeModal()" style="border:none;background:none;font-size:24px;cursor:pointer;color:#64748b">&times;</button>';
    $body .= '</div></div>';
    $body .= '<div id="trzTreeModalContent" style="overflow-y:auto;max-height:calc(88vh - 110px)"></div>';
    $body .= '</div></div>';

    // Scripts de navegación de pestañas y filtrado en vivo
    $body .= '<script>
    var currentTrzTab = "all";
    function switchStockMainTab(tab) {
        var panelItems = document.getElementById("panel_stock_items");
        var panelRolls = document.getElementById("panel_stock_rolls");
        var btnItems = document.getElementById("tab_btn_items");
        var btnRolls = document.getElementById("tab_btn_rolls");
        var hiddenTab = document.getElementById("hidden_active_tab");
        if (hiddenTab) hiddenTab.value = tab;

        if (tab === "rolls") {
            if (panelItems) panelItems.style.display = "none";
            if (panelRolls) panelRolls.style.display = "";
            if (btnItems) { btnItems.style.background = "#fff"; btnItems.style.color = "#475569"; btnItems.style.borderColor = "#cbd5e1"; }
            if (btnRolls) { btnRolls.style.background = "#0f172a"; btnRolls.style.color = "#fff"; btnRolls.style.borderColor = "#0f172a"; }
        } else {
            if (panelRolls) panelRolls.style.display = "none";
            if (panelItems) panelItems.style.display = "";
            if (btnRolls) { btnRolls.style.background = "#fff"; btnRolls.style.color = "#475569"; btnRolls.style.borderColor = "#cbd5e1"; }
            if (btnItems) { btnItems.style.background = "#0f172a"; btnItems.style.color = "#fff"; btnItems.style.borderColor = "#0f172a"; }
        }

        try {
            var url = new URL(window.location.href);
            url.searchParams.set("tab", tab);
            window.history.replaceState({}, "", url.toString());
        } catch(e) {}
    }

    function filterStockRollsTable(query) {
        var q = (query || "").trim().toLowerCase();
        var rows = document.querySelectorAll("#stock-rolls-tbody .stock-roll-row");
        var sumWeight = 0, sumMeters = 0, visible = 0;
        rows.forEach(function(row) {
            var text = row.getAttribute("data-text") || "";
            var show = (q === "" || text.indexOf(q) !== -1);
            row.style.display = show ? "" : "none";
            if (show) {
                sumWeight += parseFloat(row.getAttribute("data-weight") || "0");
                sumMeters += parseFloat(row.getAttribute("data-meters") || "0");
                visible++;
            }
        });
        var fmt = function(n, dec) {
            return n.toLocaleString("es-CL", {minimumFractionDigits: dec, maximumFractionDigits: dec});
        };
        var cntElem = document.getElementById("tfoot-rolls-count");
        var wElem = document.getElementById("tfoot-rolls-weight");
        var mElem = document.getElementById("tfoot-rolls-meters");
        var bVis = document.getElementById("rolls-visible-badge");
        var bW = document.getElementById("rolls-weight-badge");

        if (cntElem) cntElem.textContent = visible;
        if (wElem) wElem.textContent = fmt(sumWeight, 3) + " Kg";
        if (mElem) mElem.textContent = fmt(sumMeters, 0) + " m";
        if (bVis) bVis.textContent = visible + " bobinas";
        if (bW) bW.textContent = fmt(sumWeight, 2) + " Kg";
    }

    function openTraceabilityTreeModal(code) {
        var modal = document.getElementById("traceabilityTreeModal");
        var title = document.getElementById("trz_modal_code_title");
        var content = document.getElementById("trzTreeModalContent");
        var fsBtn = document.getElementById("btn_modal_fullscreen_tree");
        if (!modal) return;
        modal.classList.add("open");
        modal.style.display = "flex";
        if (title) title.textContent = code;
        if (fsBtn) fsBtn.href = "/stock/traceability?q=" + encodeURIComponent(code);
        if (content) content.innerHTML = "<div style=\"text-align:center;padding:40px;color:#64748b\"><div style=\"font-size:32px;margin-bottom:10px\">⏳</div>Cargando trazabilidad 360°...</div>";

        fetch("/stock/traceability/json?q=" + encodeURIComponent(code))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok && data.html) {
                    content.innerHTML = data.html;
                } else {
                    content.innerHTML = "<div class=\"err\" style=\"padding:16px;border-radius:10px\">⚠️ " + (data.error || "No se encontraron registros de trazabilidad para este código.") + "</div>";
                }
            })
            .catch(function() {
                content.innerHTML = "<div class=\"err\" style=\"padding:16px;border-radius:10px\">⚠️ Error al conectar con el servidor.</div>";
            });
    }

    function closeTraceabilityTreeModal() {
        var modal = document.getElementById("traceabilityTreeModal");
        if (modal) {
            modal.classList.remove("open");
            modal.style.display = "none";
        }
    }

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
 * Renderiza la toma de inventario en el ambiente ERP conectado.
 */
function unibagRenderInventoryCountsPage(ReceptionService $service): void
{
    // Listar solo bodegas que tienen inventario
    $storehouses = $service->listErpStorehouses(true);
    $requestedStId = isset($_GET['bodega']) ? (int)$_GET['bodega'] : 0;
    $requestedStId = unibagResolveRequestedStorehouseId($service, $storehouses, $requestedStId);
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
    $request = bin2hex(random_bytes(24));
    $_SESSION['inventory_count_requests'] = array_slice((array)($_SESSION['inventory_count_requests'] ?? []), -19, null, true);
    $_SESSION['inventory_count_requests'][$request] = ['warehouse' => $storehouseId, 'items' => count($draftItems)];

    $body = '<div class="erp-prod-shell" style="max-width:1440px;margin:0 auto">';

    $body .= '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:16px">
        <div>
          <div style="font-size:22px;font-weight:800;color:#0f172a">Toma de inventario · ERP</div>
          <div class="muted" style="font-size:13px;margin-top:2px">
            Al guardar se ajustan las existencias y se registran las diferencias en el kardex como toma de inventario.
          </div>
        </div>
        <div style="display:flex;gap:8px">
          <a class="btn secondary" href="/' . (userCanAccessArea('ERP', sessionAreaPermissions()) ? 'stock?bodega=' . $storehouseId : 'purchase-orders?status=active&supplier_type=NATIONAL') . '">Volver</a>
          ' . (userCanAccessArea('ERP', sessionAreaPermissions()) ? '<a class="btn secondary" href="/reports/inventory">Historial</a>' : '') . '
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
    $body .= '<form id="inventory-count-form" method="post" action="/stock/inventory-counts">';
    $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
    $body .= '<input type="hidden" name="request_key" value="' . h($request) . '">';
    $body .= '<input type="hidden" name="items_json" id="inventory-count-payload" value="">';
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
        $sysQty = ErpInventoryCountPostingService::quantity($item['system_qty'] ?? '0');
        $phyQty = ErpInventoryCountPostingService::quantity($item['physical_qty'] ?? $sysQty);
        $diff = 0.0;
        $prefix = 'items[' . $idx . ']';

        $body .= '<tr style="border-bottom:1px solid #e2e8f0">
            <td style="padding:8px 10px;font-weight:700;white-space:nowrap">
              ' . h((string)$item['item_number_prod']) . '
              <input type="hidden" name="' . $prefix . '[item_id]" value="' . (int)$item['item_id'] . '">
              <input type="hidden" name="' . $prefix . '[system_qty]" value="' . h($sysQty) . '">
            </td>
            <td style="padding:8px 10px;font-weight:600;color:#334155">' . h((string)$item['item_title']) . '</td>
            <td style="padding:8px 10px;text-align:center;color:#64748b">' . h((string)$item['unit_name']) . '</td>
            <td style="padding:8px 10px;text-align:right;font-weight:700">' . h(rtrim(rtrim($sysQty, '0'), '.')) . '</td>
            <td style="padding:8px 10px;text-align:right">
              <input data-system-qty="' . h($sysQty) . '" data-diff-target="diff-' . $idx . '" name="' . $prefix . '[physical_qty]" type="number" step="0.0000000001" min="0" required value="' . h(rtrim(rtrim($phyQty, '0'), '.')) . '" style="width:150px;height:34px;text-align:right;font-weight:700;border:1px solid #cbd5e1;border-radius:6px;padding:0 6px">
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

    $canEdit = unibagCanPerformInventoryCount();
    if ($canEdit) {
        $body .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding-top:16px;border-top:1px solid #e2e8f0">
            <div class="muted" style="font-size:12px">Faltantes: salida. Sobrantes: entrada. Sin diferencia: no genera movimiento.</div>
            <button class="btn" type="button" onclick="document.getElementById(&quot;inventory-count-confirm&quot;).showModal()"' . ($draftItems === [] ? ' disabled' : '') . ' style="padding:10px 24px;font-size:14px;font-weight:800">
              Guardar inventario
            </button>
          </div>';
    } else {
        $body .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding-top:16px;border-top:1px solid #e2e8f0">
            <div class="muted" style="font-size:12px">La toma de inventario requiere acceso de Bodega o autorización de responsable.</div>
            <button class="btn secondary" type="button" disabled style="padding:10px 24px;font-size:14px;font-weight:700;color:#94a3b8">
              🔒 Modificación restringida
            </button>
          </div>';
    }

    $body .= '</form>';
    $body .= '<dialog id="inventory-count-confirm" style="border:0;border-radius:12px;padding:24px;max-width:480px">
        <h3>Guardar inventario</h3><p>Se ajustará el stock de esta bodega al conteo físico y se registrarán las entradas o salidas en el kardex. ¿Confirmas el conteo?</p>
        <button class="btn secondary" type="button" onclick="this.closest(&quot;dialog&quot;).close()">Volver</button>
        <button class="btn" type="button" onclick="this.closest(&quot;dialog&quot;).close();document.getElementById(&quot;inventory-count-form&quot;).requestSubmit()">Confirmar</button>
        </dialog>';

    $body .= '<script>
        (function () {
          var inputs = document.querySelectorAll("input[data-system-qty][data-diff-target]");
          function recalc(input) {
            function scaled(value) {
              if (!/^\d+(\.\d{0,10})?$/.test(value)) return null;
              var parts = value.split(".");
              return BigInt(parts[0]) * 10000000000n + BigInt((parts[1] || "").padEnd(10, "0"));
            }
            var sys = scaled(input.getAttribute("data-system-qty") || "0");
            var phy = scaled(input.value);
            var diff = phy === null || sys === null ? null : phy - sys;
            var targetId = input.getAttribute("data-diff-target");
            var span = targetId ? document.getElementById(targetId) : null;
            if (span) {
              var digits = diff === null ? "" : (diff < 0n ? -diff : diff).toString().padStart(11, "0");
              var fraction = digits.slice(-10).replace(/0+$/, "");
              span.textContent = diff === null ? "Revisar" : (diff < 0n ? "-" : (diff > 0n ? "+" : "")) + digits.slice(0, -10) + (fraction ? "," + fraction : "");
              span.style.color = diff === 0n ? "#64748b" : (diff !== null && diff > 0n ? "#16a34a" : "#dc2626");
            }
          }
          for (var i = 0; i < inputs.length; i++) {
            inputs[i].addEventListener("input", function () { recalc(this); });
            recalc(inputs[i]);
          }
          // JSON evita truncar planillas grandes por max_input_vars.
          // JSON avoids PHP input-variable truncation on large count sheets.
          document.getElementById("inventory-count-form").addEventListener("submit", function () {
            var rows = {};
            var fields = this.querySelectorAll("[name^=\"items[\"]");
            fields.forEach(function (field) {
              var match = field.name.match(/^items\[(\d+)\]\[(\w+)\]$/);
              if (match) { rows[match[1]] = rows[match[1]] || {}; rows[match[1]][match[2]] = field.value; }
            });
            document.getElementById("inventory-count-payload").value = JSON.stringify(Object.values(rows));
            fields.forEach(function (field) { field.removeAttribute("name"); });
            this.querySelectorAll("button").forEach(function (button) { button.disabled = true; });
          });
        })();
      </script>';

    $body .= '</div></div>';

    render('Toma de Inventario · ERP', $body);
}

function unibagRenderTraceabilityBatchesModal(array $batches, int $warehouseId, string $query, int $page, bool $hasNext): string
{
    $base = '/stock/traceability-entry?bodega=' . $warehouseId . '&show_batches=1&batch_q=' . rawurlencode($query);
    $html = '<div class="print-modal open" id="traceabilityBatchesModal" onclick="if(event.target===this)this.classList.remove(&quot;open&quot;)">'
        . '<div class="print-card" style="width:min(1200px,96vw);max-height:94vh">'
        . '<div style="display:flex;justify-content:space-between;align-items:center"><h3>Lotes de etiquetas</h3>'
        . '<button type="button" class="btn secondary" onclick="document.getElementById(&quot;traceabilityBatchesModal&quot;).classList.remove(&quot;open&quot;)">Cerrar</button></div>'
        . '<form method="get" action="/stock/traceability-entry" style="display:flex;gap:8px;margin:12px 0">'
        . '<input type="hidden" name="bodega" value="' . $warehouseId . '"><input type="hidden" name="show_batches" value="1">'
        . '<input class="form-control" name="batch_q" value="' . h($query) . '" placeholder="SKU, descripcion, operador o codigo de etiqueta">'
        . '<button class="btn secondary" type="submit">Buscar</button></form>'
        . '<div style="overflow:auto"><table style="width:100%;border-collapse:collapse" cellpadding="9">'
        . '<thead><tr><th>Lote</th><th>Fecha</th><th>SKU</th><th>Etiquetas</th><th>Operador</th><th></th></tr></thead><tbody>';
    foreach ($batches as $batch) {
        $count = (int)$batch['label_count'];
        $expected = max(1, (int)$batch['expected_count']);
        $complete = $count === $expected && (int)$batch['first_number'] === 1 && (int)$batch['last_number'] === $expected;
        $date = strtotime((string)$batch['created_at']);
        $identifier = empty($batch['batch_id']) ? '#' . (int)$batch['legacy_start']
            : strtoupper(substr((string)$batch['batch_id'], 0, 8));
        $html .= '<tr style="border-top:1px solid #e2e8f0"><td><b>Lote ' . h($identifier) . '</b>'
            . (empty($batch['batch_id']) ? '<div style="font-size:11px;color:#64748b">Anterior</div>' : '')
            . '</td><td style="white-space:nowrap">' . ($date ? date('d/m/Y H:i:s', $date) : '-') . '</td>'
            . '<td><b>' . h($batch['item_code']) . '</b><div style="font-size:11px;color:#64748b">' . h($batch['entity_type']) . '</div></td>'
            . '<td style="white-space:nowrap"><b>' . $count . '</b>'
            . (!$complete ? '<div style="font-size:11px;color:#b45309">' . $count . ' de ' . $expected . ' disponibles</div>' : '')
            . '</td><td>' . h($batch['operator_name']) . '</td><td>'
            . '<a class="btn secondary" href="' . h($base . '&batch_page=' . $page . '&print_batch=' . (int)$batch['anchor_id']) . '">Reimprimir</a></td></tr>';
    }
    if ($batches === []) $html .= '<tr><td colspan="6">No hay lotes registrados para esta busqueda.</td></tr>';
    $html .= '</tbody></table></div><div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">';
    if ($page > 1) $html .= '<a class="btn secondary" href="' . h($base . '&batch_page=' . ($page - 1)) . '">Anterior</a>';
    $html .= '<span>Pagina ' . $page . '</span>';
    if ($hasNext) $html .= '<a class="btn secondary" href="' . h($base . '&batch_page=' . ($page + 1)) . '">Siguiente</a>';
    return $html . '</div></div></div>';
}

/**
 * Renderiza la pantalla de ingreso de productos a bodega con código de trazabilidad.
 */
function unibagRenderTraceabilityEntryPage(ReceptionService $service, string $currentOperatorName): void
{
    $service->ensureTraceabilityWarehouseEntriesTable();
    $maxLabelsPerBatch = ReceptionService::MAX_WAREHOUSE_TRACEABILITY_LABELS;
    $canEdit = true;
    $storehouses = $service->listErpStorehouses();
    $requestedWhId = isset($_GET['bodega']) ? (int)$_GET['bodega'] : 0;
    $requestedWhId = unibagResolveRequestedStorehouseId($service, $storehouses, $requestedWhId);

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
    $initialQuery = trim((string)($_GET['q'] ?? ($_GET['filter'] ?? '')));
    $err = trim((string)($_GET['error'] ?? ''));

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

    $recentEntriesLimit = $initialQuery !== '' ? 5000 : 500;
    $recentEntries = $service->listWarehouseTraceabilityEntries(
        $storehouseId > 0 ? $storehouseId : null,
        $initialQuery !== '' ? $initialQuery : null,
        $recentEntriesLimit
    );
    $groupedRecentEntries = [];
    foreach ($recentEntries as $entry) {
        $groupSku = strtoupper(trim((string)($entry['item_code'] ?? '')));
        $groupKey = $groupSku !== '' ? ('sku_' . $groupSku) : ('code_' . (string)($entry['traceability_code'] ?? ''));
        if (!isset($groupedRecentEntries[$groupKey])) {
            $groupedRecentEntries[$groupKey] = [
                'group_key' => $groupKey,
                'item_code' => (string)($entry['item_code'] ?? ''),
                'item_description' => (string)($entry['item_description'] ?? ''),
                'entity_type' => strtoupper((string)($entry['entity_type'] ?? 'PRODUCT')),
                'warehouse_name' => (string)($entry['warehouse_name'] ?? ''),
                'unit_name' => (string)($entry['unit_name'] ?? 'UNID'),
                'operator_name' => (string)($entry['operator_name'] ?? ''),
                'latest_created_at' => (string)($entry['created_at'] ?? ''),
                'latest_traceability_code' => (string)($entry['traceability_code'] ?? ''),
                'label_count' => 0,
                'total_quantity' => 0.0,
                'entries' => [],
            ];
        }

        $groupedRecentEntries[$groupKey]['label_count']++;
        $groupedRecentEntries[$groupKey]['total_quantity'] += (float)($entry['quantity'] ?? 1);
        $groupedRecentEntries[$groupKey]['entries'][] = $entry;

        $entryCreatedAt = (string)($entry['created_at'] ?? '');
        if (
            $groupedRecentEntries[$groupKey]['latest_created_at'] === ''
            || ($entryCreatedAt !== '' && strtotime($entryCreatedAt) > strtotime((string)$groupedRecentEntries[$groupKey]['latest_created_at']))
        ) {
            $groupedRecentEntries[$groupKey]['latest_created_at'] = $entryCreatedAt;
            $groupedRecentEntries[$groupKey]['operator_name'] = (string)($entry['operator_name'] ?? '');
            $groupedRecentEntries[$groupKey]['latest_traceability_code'] = (string)($entry['traceability_code'] ?? '');
        }
    }
    $groupedRecentEntries = array_values($groupedRecentEntries);
    $suggestedCode = $service->generateTraceabilityCode('PRODUCT');
    $msg = trim((string)($_GET['msg'] ?? ''));
    $batchAnchorId = max(0, (int)($_GET['print_batch'] ?? 0));
    $showBatches = !empty($_GET['show_batches']);
    $batchPage = max(1, min(100000, (int)($_GET['batch_page'] ?? 1)));
    $batchQuery = trim((string)($_GET['batch_q'] ?? ''));
    $labelBatches = [];
    $batchError = '';
    if ($showBatches) {
        try {
            $labelBatches = $service->listWarehouseTraceabilityBatches($storehouseId, $batchQuery, $batchPage);
        } catch (Throwable $e) {
            $batchError = 'No se pudieron consultar los lotes de etiquetas.';
            error_log('Traceability batches: ' . $e->getMessage());
        }
    }
    $hasNextBatchPage = count($labelBatches) > 25;
    $labelBatches = array_slice($labelBatches, 0, 25);

    // Si viene de guardar lote de bultos, preparar etiquetas automáticas para imprimir
    $printCodesParam = trim((string)($_GET['print_codes'] ?? ''));
    $autoPrintEntries = [];
    if ($batchAnchorId > 0) {
        try {
            $autoPrintEntries = $service->getWarehouseTraceabilityBatchEntries($storehouseId, $batchAnchorId);
            if ($autoPrintEntries === []) $batchError = 'El lote no existe en esta bodega o sus etiquetas fueron eliminadas.';
        } catch (Throwable $e) {
            $batchError = 'No se pudieron recuperar las etiquetas del lote.';
            error_log('Traceability batch reprint: ' . $e->getMessage());
        }
    } elseif ($printCodesParam !== '') {
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
        #printLabelModal .print-card { display:flex; flex-direction:column; overflow:hidden; }
        #printLabelModal .print-card > :not(#printableArea) { flex-shrink:0; }
        #printableArea { min-height:0; overflow:auto; flex:1; }
        .printable-label { background: #fff; border: 2px solid #000; border-radius: 8px; padding: 10px 12px; text-align: center; color: #000; margin-bottom: 16px; font-family: Arial, sans-serif; }
        .barcode-svg-wrap { width: 94%; margin: 6px auto 4px auto; }
        .barcode-svg-wrap svg { display: block; width: 100%; height: 48px; }

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
    $body .= '<a class="btn" href="/stock/traceability?bodega=' . $storehouseId . '" style="background:#0f766e;display:inline-flex;align-items:center;gap:6px">Trazabilidad</a>';
    $body .= '<a class="btn secondary" href="/stock/traceability-entry?bodega=' . $storehouseId . '&show_batches=1">Lotes</a>';
    $body .= '</div>';
    $body .= '</div>';

    // Alertas
    if ($msg !== '') {
        $body .= '<div class="ok" style="margin-bottom:16px;padding:12px 16px;border-radius:10px;font-weight:700">✅ ' . h($msg) . '</div>';
    }
    if ($err !== '') {
        $body .= '<div class="err" style="margin-bottom:16px;padding:12px 16px;border-radius:10px;font-weight:700">⚠️ ' . h($err) . '</div>';
    }
    if ($batchError !== '') $body .= '<div class="err" style="margin-bottom:16px">' . h($batchError) . '</div>';

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
    $body .= '<input type="text" name="item_code" id="input_item_code" class="form-control" maxlength="60" value="' . h($preSku) . '" placeholder="Ej: POL-BL-80" required>';
    $body .= '</div>';
    $body .= '<div class="form-group">';
    $body .= '<label>Unidad Medida</label>';
    $body .= '<input type="text" name="unit_name" id="input_unit_name" class="form-control" value="' . h($preUnit) . '" placeholder="UNID, KG, M">';
    $body .= '</div>';
    $body .= '</div>';

    $body .= '<div class="form-group">';
    $body .= '<label>Descripción del Producto *</label>';
    $body .= '<input type="text" name="item_description" id="input_item_desc" class="form-control" maxlength="255" value="' . h($preDesc) . '" placeholder="Nombre o descripción completa del producto..." required>';
    $body .= '</div>';

    // SECCIÓN CANTIDAD EN MODO UNITARIO (1 etiqueta por cada producto)
    $body .= '<div id="sec_mode_unitary">';
    $body .= '<div class="form-group">';
    $body .= '<label id="lbl_unitary_count">Cantidad de Productos / Bultos a Rotular (Etiquetas a Imprimir) *</label>';
    $body .= '<input type="number" min="1" max="' . $maxLabelsPerBatch . '" name="package_count" id="input_package_count" class="form-control" value="1" required oninput="calcTotals()">';
    $body .= '<div class="muted" style="font-size:11.5px;margin-top:4px;color:#0f766e;font-weight:600">👉 Se generará una etiqueta física individual con código único para CADA producto/bulto físico. Máximo ' . $maxLabelsPerBatch . ' etiquetas por vez.</div>';
    $body .= '</div>';
    $body .= '</div>';

    // SECCIÓN CANTIDAD EN MODO AGRUPADO (Cajas con múltiples unidades)
    $body .= '<div id="sec_mode_pack" style="display:none">';
    $body .= '<div class="trz-fields-2col">';
    $body .= '<div class="form-group">';
    $body .= '<label>1. Cantidad de Bultos (Etiquetas a Imprimir) *</label>';
    $body .= '<input type="number" min="1" max="' . $maxLabelsPerBatch . '" id="input_package_count_pack" class="form-control" value="1" oninput="calcTotals()">';
    $body .= '<div class="muted" style="font-size:11px;margin-top:3px">Número de pallets, cajas o bultos físicos a rotular. Máximo ' . $maxLabelsPerBatch . ' etiquetas por vez.</div>';
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
    $body .= '<span style="font-size:12px;font-weight:700;color:#64748b">' . count($groupedRecentEntries) . ' códigos agrupados · ' . count($recentEntries) . ' etiquetas</span>';
    $body .= '</div>';

    // Barra de búsqueda rápida en vivo y botón para borrar lote completo si se filtra
    $body .= '<div style="margin-bottom:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
    $body .= '<div style="flex:1;min-width:240px">';
    $body .= '<input type="text" id="trz-live-filter" placeholder="🔍 Buscar por SKU, descripción, código reciente u operador..." class="form-control" style="font-size:12.5px" value="' . h($initialQuery) . '" oninput="filterRecentEntries(this.value)">';
    $body .= '</div>';
    $body .= '<button type="button" id="btn_delete_filtered_sku" class="btn" style="display:none;background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;padding:0 12px;height:42px;font-size:12px;font-weight:700;cursor:pointer" onclick="deleteAllFilteredBySku()">🗑️ Borrar código mostrado</button>';
    $body .= '</div>';

    $body .= '<div class="table-wrap" style="max-height:680px;overflow-y:auto;overflow-x:auto">';
    $body .= '<table id="table-recent-entries" style="width:100%;border-collapse:collapse;font-size:12.5px">';
    $body .= '<thead>';
    $body .= '<tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;position:sticky;top:0;z-index:2">';
    $body .= '<th style="padding:10px 12px;text-align:left;font-weight:700">Código / SKU</th>';
    $body .= '<th style="padding:10px 12px;text-align:left;font-weight:700">Tipo</th>';
    $body .= '<th style="padding:10px 12px;text-align:left;font-weight:700">Artículo</th>';
    $body .= '<th style="padding:10px 12px;text-align:right;font-weight:700">Etiquetas / Cantidad</th>';
    $body .= '<th style="padding:10px 12px;text-align:left;font-weight:700">Último registro</th>';
    $body .= '<th style="padding:10px 12px;text-align:center;font-weight:700">Acciones</th>';
    $body .= '</tr>';
    $body .= '</thead>';
    $body .= '<tbody>';

    if ($groupedRecentEntries === []) {
        $body .= '<tr><td colspan="6" style="padding:32px 14px;text-align:center;color:#64748b">';
        $body .= '<div style="font-size:32px;margin-bottom:8px">📦</div>';
        $body .= 'Aún no se registran bultos con código de trazabilidad en esta bodega.<br>Complete el formulario para realizar el primer ingreso.';
        $body .= '</td></tr>';
    }

    foreach ($groupedRecentEntries as $group) {
        $gType = strtoupper((string)($group['entity_type'] ?? 'PRODUCT'));
        $gCode = (string)($group['latest_traceability_code'] ?? '');
        $gSku = (string)($group['item_code'] ?? '');
        $gDesc = (string)($group['item_description'] ?? '');
        $gQty = (float)($group['total_quantity'] ?? 0);
        $gUnit = (string)($group['unit_name'] ?? 'UNID');
        $gWh = (string)($group['warehouse_name'] ?? '');
        $gOp = (string)($group['operator_name'] ?? '');
        $gCount = (int)($group['label_count'] ?? 0);
        $gEntries = (array)($group['entries'] ?? []);
        $gLatestDateRaw = (string)($group['latest_created_at'] ?? '');
        $gLatestDate = $gLatestDateRaw !== '' ? date('d/m/Y H:i', strtotime($gLatestDateRaw)) : '-';
        $gRowText = mb_strtolower(trim($gSku . ' ' . $gDesc . ' ' . $gCode . ' ' . $gWh . ' ' . $gOp));

        $body .= '<tr class="trz-entry-row" style="border-bottom:1px solid #f1f5f9" data-text="' . h($gRowText) . '" data-sku="' . h($gSku) . '" data-label-count="' . $gCount . '">';
        $body .= '<td style="padding:10px 12px;white-space:nowrap">';
        $body .= '<div style="font-weight:800;color:#0f172a">' . h($gSku !== '' ? $gSku : $gCode) . '</div>';
        if ($gCode !== '') {
            $body .= '<button type="button" class="trz-tag-code" style="cursor:pointer;border:1px solid #ccfbf1;margin-top:4px" onclick="openTraceabilityTreeModal(' . h(json_encode($gCode)) . ')" title="Ver árbol de trazabilidad 360° del último registro">' . h($gCode) . ' 🔍</button>';
            $body .= ' <a href="/stock/traceability?q=' . rawurlencode($gCode) . '" target="_blank" style="font-size:11.5px;color:#0f766e;text-decoration:none;font-weight:700" title="Abrir en pantalla completa">↗</a>';
        }
        $body .= '<div style="font-size:10.5px;color:#64748b;margin-top:4px">' . $gCount . ' etiqueta(s) agrupadas</div>';
        $body .= '</td>';
        $body .= '<td style="padding:10px 12px;white-space:nowrap"><span class="badge-type ' . h($gType) . '">' . h($gType) . '</span></td>';
        $body .= '<td style="padding:10px 12px">';
        $body .= '<div style="font-weight:700;color:#0f172a">' . h($gDesc) . '</div>';
        $body .= '<div style="font-size:11px;color:#64748b;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . h($gWh) . '</div>';
        $body .= '</td>';
        $body .= '<td style="padding:10px 12px;text-align:right;font-weight:800;color:#0f172a;white-space:nowrap">';
        $body .= '<div>' . $gCount . ' etiqueta(s)</div>';
        $body .= '<div style="font-size:11px;color:#64748b;margin-top:2px">' . number_format($gQty, 2, ',', '.') . ' ' . h($gUnit) . '</div>';
        $body .= '</td>';
        $body .= '<td style="padding:10px 12px;font-size:11.5px;color:#475569">';
        $body .= '<div style="font-weight:700;color:#334155">' . h($gLatestDate) . '</div>';
        $body .= '<div style="font-size:11px;color:#64748b">👤 ' . h($gOp) . '</div>';
        $body .= '</td>';
        $body .= '<td style="padding:10px 12px;text-align:center;white-space:nowrap">';
        $body .= '<button type="button" class="btn secondary" style="padding:5px 9px;font-size:11.5px;font-weight:700;background:#f8fafc;border:1px solid #cbd5e1;color:#334155" onclick="openPrintLabel(' . h(json_encode($gEntries, JSON_HEX_APOS | JSON_HEX_QUOT)) . ')">🖨️ Ver etiquetas</button>';
        $body .= ' <a class="btn secondary" style="padding:5px 9px;font-size:11.5px" href="/stock/traceability-entry?bodega=' . $storehouseId . '&show_batches=1&batch_q=' . rawurlencode($gSku) . '">Lotes</a>';
        $body .= ' <button type="button" class="btn" style="padding:5px 9px;font-size:11.5px;font-weight:700;background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;margin-left:4px;cursor:pointer" onclick="deleteGroupedSku(' . h(json_encode($gSku)) . ', ' . $gCount . ')" title="Eliminar todas las etiquetas agrupadas de este código">🗑️ Borrar grupo</button>';
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

    if ($showBatches) {
        $body .= unibagRenderTraceabilityBatchesModal($labelBatches, $storehouseId, $batchQuery, $batchPage, $hasNextBatchPage);
    }

    // MODAL DE IMPRESIÓN DE ETIQUETA
    $body .= '<div class="print-modal" id="printLabelModal" onclick="if(event.target===this)closePrintLabel()">';
    $body .= '<div class="print-card">';
    $body .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">';
    $body .= '<div style="font-weight:800;font-size:16px;color:#0f172a">🏷️ Etiqueta(s) con Código de Trazabilidad <span id="lbl_modal_label_count" style="font-size:13px;color:#00A9A6"></span></div>';
    $body .= '<button type="button" onclick="closePrintLabel()" style="border:none;background:none;font-size:22px;cursor:pointer;color:#64748b">&times;</button>';
    $body .= '</div>';

    // Barra de opciones de impresión múltiple (si se abrió para un registro con cantidad > 1)
    $body .= '<div id="print_batch_notice" style="display:none;background:#f0fdfa;padding:10px;margin-bottom:12px"></div>';
    $body .= '<div id="print_popup_error" style="display:none;color:#b91c1c;margin-bottom:12px"></div>';
    $body .= '<div id="multi_label_toolbar" style="display:none;background:#f0fdfa;border:1px solid #99f6e4;border-radius:10px;padding:10px 14px;margin-bottom:14px">';
    $body .= '<div style="font-size:12px;font-weight:700;color:#0f766e;margin-bottom:6px">⚙️ Opciones de impresión:</div>';
    $body .= '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
    $body .= '<button type="button" class="btn secondary" id="btn_split_labels" style="padding:4px 10px;font-size:12px;font-weight:700" onclick="splitCurrentEntryLabels()">🖨️ 1 Etiqueta por cada producto (<span id="lbl_split_count"></span> etiquetas)</button>';
    $body .= '<button type="button" class="btn secondary" id="btn_single_label" style="padding:4px 10px;font-size:12px;font-weight:700" onclick="singleCurrentEntryLabel()">🖨️ 1 Etiqueta lote consolidado</button>';
    $body .= '</div>';
    $body .= '</div>';

    $body .= '<div id="same_sku_toolbar" style="display:none;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:10px 14px;margin-bottom:14px">';
    $body .= '<div style="display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:8px">';
    $body .= '<div>';
    $body .= '<div style="font-size:12px;font-weight:700;color:#1d4ed8">🧾 Impresión por SKU</div>';
    $body .= '<div id="same_sku_summary" style="font-size:11.5px;color:#475569;margin-top:2px"></div>';
    $body .= '</div>';
    $body .= '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
    $body .= '<button type="button" class="btn secondary" id="btn_print_selected_current" style="padding:4px 10px;font-size:12px;font-weight:700" onclick="renderCurrentSpecificLabel()">Solo esta etiqueta</button>';
    $body .= '<button type="button" class="btn secondary" id="btn_print_same_sku_all" style="padding:4px 10px;font-size:12px;font-weight:700" onclick="renderAllSameSkuLabels()">Todas las de este SKU</button>';
    $body .= '</div>';
    $body .= '</div>';
    $body .= '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
    $body .= '<select id="same_sku_entry_select" class="form-control" style="flex:1;min-width:260px;height:38px;padding:0 10px;font-size:12px" onchange="renderSelectedSameSkuLabel()"></select>';
    $body .= '<button type="button" class="btn secondary" style="padding:4px 10px;font-size:12px;font-weight:700" onclick="renderSelectedSameSkuLabel()">Ver etiqueta seleccionada</button>';
    $body .= '</div>';
    $body .= '</div>';

    $body .= '<div id="delete_label_toolbar" style="display:none;background:#fff1f2;border:1px solid #fecdd3;border-radius:10px;padding:10px 14px;margin-bottom:14px">';
    $body .= '<div style="display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap">';
    $body .= '<div>';
    $body .= '<div style="font-size:12px;font-weight:700;color:#be123c">🗑️ Borrado de etiquetas</div>';
    $body .= '<div id="delete_label_summary" style="font-size:11.5px;color:#475569;margin-top:2px"></div>';
    $body .= '</div>';
    $body .= '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
    $body .= '<button type="button" class="btn" id="btn_delete_selected_label" style="padding:4px 10px;font-size:12px;font-weight:700;background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5" onclick="deleteSelectedLabelFromModal()">Borrar etiqueta seleccionada</button>';
    $body .= '<button type="button" class="btn" id="btn_delete_same_sku_all" style="display:none;padding:4px 10px;font-size:12px;font-weight:700;background:#fecaca;color:#991b1b;border:1px solid #f87171" onclick="deleteAllSameSkuLabelsFromModal()">Borrar todas las del SKU</button>';
    $body .= '</div>';
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
        var recentTraceabilityEntries = ' . json_encode($recentEntries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';
        var currentEntryForSplit = null;
        var currentSameSkuEntries = [];
        var currentPrintSourceEntry = null;
        var MAX_TRACEABILITY_LABELS = ' . $maxLabelsPerBatch . ';
        var reprintBatchAnchorId = ' . $batchAnchorId . ';
        var returnToLabelBatches = false;

        function normalizeSku(value) {
            return String(value || "").trim().toUpperCase();
        }

        function getEntryCode(entry) {
            return String((entry && (entry.traceability_code || entry.code)) || "").trim();
        }

        function clampPackageCount(value) {
            var n = parseInt(value || "1", 10);
            if (isNaN(n) || n < 1) return 1;
            return Math.min(MAX_TRACEABILITY_LABELS, n);
        }

        function getEntriesForSameSku(entry) {
            if (!entry) return [];
            var targetSku = normalizeSku(entry.item_code);
            if (!targetSku) return [entry];

            var found = [];
            var seenCodes = {};
            recentTraceabilityEntries.forEach(function(row) {
                if (normalizeSku(row.item_code) !== targetSku) return;
                var code = getEntryCode(row);
                if (!code || seenCodes[code]) return;
                seenCodes[code] = true;
                found.push(row);
            });

            var currentCode = getEntryCode(entry);
            if (currentCode && !seenCodes[currentCode]) {
                found.unshift(entry);
            }

            return found.length > 0 ? found : [entry];
        }

        function setSameSkuToolbarVisibility(isVisible) {
            var toolbar = document.getElementById("same_sku_toolbar");
            if (toolbar) toolbar.style.display = isVisible ? "block" : "none";
        }

        function setDeleteToolbarVisibility(isVisible) {
            var toolbar = document.getElementById("delete_label_toolbar");
            if (toolbar) toolbar.style.display = isVisible ? "block" : "none";
        }

        function setupDeleteToolbar(entry) {
            var summary = document.getElementById("delete_label_summary");
            var deleteAllBtn = document.getElementById("btn_delete_same_sku_all");
            if (!entry) {
                if (summary) summary.textContent = "";
                if (deleteAllBtn) deleteAllBtn.style.display = "none";
                setDeleteToolbarVisibility(false);
                return;
            }

            var code = getEntryCode(entry);
            var skuText = String(entry.item_code || "").trim();
            var totalSkuEntries = currentSameSkuEntries.length;
            if (summary) {
                summary.textContent = "Etiqueta seleccionada: " + code + (skuText ? (" · SKU " + skuText) : "");
            }
            if (deleteAllBtn) {
                deleteAllBtn.style.display = totalSkuEntries > 1 ? "inline-flex" : "none";
                deleteAllBtn.textContent = "Borrar todas las del SKU (" + totalSkuEntries + ")";
            }
            setDeleteToolbarVisibility(true);
        }

        function submitDeleteForm(entryId, sku) {
            document.getElementById("delete_entry_id").value = entryId > 0 ? String(entryId) : "0";
            document.getElementById("delete_sku").value = sku || "";
            document.getElementById("form_delete_trz_entry").submit();
        }

        function setupSameSkuToolbar(entry) {
            var summary = document.getElementById("same_sku_summary");
            var select = document.getElementById("same_sku_entry_select");
            currentPrintSourceEntry = entry || null;
            currentSameSkuEntries = getEntriesForSameSku(entry);

            if (!summary || !select || !entry || currentSameSkuEntries.length <= 1) {
                if (summary) summary.textContent = "";
                if (select) select.innerHTML = "";
                setSameSkuToolbarVisibility(false);
                setupDeleteToolbar(entry || null);
                return;
            }

            var currentCode = getEntryCode(entry);
            var skuText = String(entry.item_code || "").trim();
            summary.textContent = skuText + " · " + currentSameSkuEntries.length + " etiqueta(s) registradas para este SKU.";
            select.innerHTML = "";
            currentSameSkuEntries.forEach(function(row) {
                var option = document.createElement("option");
                var code = getEntryCode(row);
                var qty = parseFloat(row.quantity || 1);
                var unit = String(row.unit_name || "UNID").trim();
                var createdAt = row.created_at ? String(row.created_at) : "";
                option.value = code;
                option.textContent = code + " · " + qty.toLocaleString("es-CL", {minimumFractionDigits: 0, maximumFractionDigits: 2}) + " " + unit + (createdAt ? (" · " + createdAt) : "");
                if (code === currentCode) option.selected = true;
                select.appendChild(option);
            });

            setSameSkuToolbarVisibility(true);
            setupDeleteToolbar(entry);
        }

        function renderCurrentSpecificLabel() {
            if (!currentPrintSourceEntry) return;
            var select = document.getElementById("same_sku_entry_select");
            if (select) select.value = getEntryCode(currentPrintSourceEntry);
            renderLabelList([currentPrintSourceEntry]);
            setupDeleteToolbar(currentPrintSourceEntry);
        }

        function renderAllSameSkuLabels() {
            if (!currentSameSkuEntries || currentSameSkuEntries.length === 0) return;
            renderLabelList(currentSameSkuEntries);
        }

        function renderSelectedSameSkuLabel() {
            var select = document.getElementById("same_sku_entry_select");
            if (!select || !currentSameSkuEntries || currentSameSkuEntries.length === 0) return;
            var selectedCode = String(select.value || "");
            var selectedEntry = currentSameSkuEntries.find(function(row) {
                return getEntryCode(row) === selectedCode;
            });
            if (!selectedEntry) return;
            currentPrintSourceEntry = selectedEntry;
            renderLabelList([selectedEntry]);
            setupDeleteToolbar(selectedEntry);
        }

        function deleteSelectedLabelFromModal() {
            if (!currentPrintSourceEntry) return;
            var entryId = parseInt(currentPrintSourceEntry.id || 0, 10);
            var code = getEntryCode(currentPrintSourceEntry);
            if (!entryId || entryId <= 0) {
                alert("No se pudo identificar la etiqueta seleccionada para borrarla.");
                return;
            }
            var msg = "¿Está seguro de borrar la etiqueta " + code + "?\\n\\nEsta acción eliminará el registro trazable y liberará el stock nuevamente.";
            if (confirm(msg)) {
                submitDeleteForm(entryId, "");
            }
        }

        function deleteAllSameSkuLabelsFromModal() {
            if (!currentPrintSourceEntry) return;
            var sku = normalizeSku(currentPrintSourceEntry.item_code);
            if (!sku) {
                alert("No se pudo identificar el SKU para borrarlo en bloque.");
                return;
            }
            var totalSkuEntries = currentSameSkuEntries.length || 0;
            var msg = "⚠️ ¿Está seguro de borrar todas las etiquetas del SKU " + sku + "?\\n\\nSe eliminarán " + totalSkuEntries + " registro(s) trazables y el stock quedará liberado nuevamente.";
            if (confirm(msg)) {
                submitDeleteForm(0, sku);
            }
        }

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
            var countPackInput = document.getElementById("input_package_count_pack");
            var countUnitaryInput = document.getElementById("input_package_count");

            if (mode === "UNITARY") {
                pkgs = clampPackageCount(countUnitaryInput ? countUnitaryInput.value : "1");
                if (countUnitaryInput) countUnitaryInput.value = pkgs;
                unitsPerPkg = 1;
                document.getElementById("input_quantity").value = "1";
            } else {
                pkgs = clampPackageCount(countPackInput ? countPackInput.value : "1");
                if (countPackInput) countPackInput.value = pkgs;
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
                        summaryBox.innerHTML = "<strong>📊 Resumen de rotulación:</strong> Se generarán <strong>" + pkgs + " etiquetas únicas e individuales</strong> (1 por cada producto/bulto físico) para un total de <strong>" + total.toLocaleString("es-CL", {minimumFractionDigits: 1, maximumFractionDigits: 2}) + "</strong> unidades de producto. Límite operativo: <strong>" + MAX_TRACEABILITY_LABELS + " etiquetas por vez</strong>.";
                    } else {
                        summaryBox.innerHTML = "<strong>📊 Resumen de rotulación:</strong> Se generarán <strong>" + pkgs + " etiquetas</strong> (1 por cada bulto agrupado) para un total de <strong>" + total.toLocaleString("es-CL", {minimumFractionDigits: 1, maximumFractionDigits: 2}) + "</strong> unidades de producto. Límite operativo: <strong>" + MAX_TRACEABILITY_LABELS + " etiquetas por vez</strong>.";
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
                document.getElementById("input_package_count").value = Math.min(MAX_TRACEABILITY_LABELS, Math.max(1, Math.round(pending)));
            } else if (upper.indexOf("PALLET") !== -1) {
                selectEntityType("PALLET");
                setPackagingMode("UNITARY");
                document.getElementById("input_package_count").value = Math.min(MAX_TRACEABILITY_LABELS, Math.max(1, Math.round(pending)));
            } else if (upper.indexOf("CAJA") !== -1 || upper.indexOf("BOX") !== -1) {
                selectEntityType("BOX");
            } else {
                setPackagingMode("UNITARY");
                document.getElementById("input_package_count").value = Math.min(MAX_TRACEABILITY_LABELS, Math.max(1, Math.round(pending)));
            }

            calcTotals();
        }

        function filterRecentEntries(q) {
            q = (q || "").toLowerCase().trim();
            var rows = document.querySelectorAll(".trz-entry-row");
            var visibleRows = [];
            rows.forEach(function(r) {
                var text = r.getAttribute("data-text") || "";
                var match = (q === "" || text.indexOf(q) !== -1);
                r.style.display = match ? "" : "none";
                if (match) visibleRows.push(r);
            });
            var btnBulk = document.getElementById("btn_delete_filtered_sku");
            if (btnBulk) {
                if (q.length >= 2 && visibleRows.length === 1) {
                    var targetRow = visibleRows[0];
                    var targetSku = String(targetRow.getAttribute("data-sku") || "").trim();
                    var labelCount = parseInt(targetRow.getAttribute("data-label-count") || "0", 10);
                    btnBulk.dataset.targetSku = targetSku;
                    btnBulk.style.display = targetSku !== "" ? "inline-flex" : "none";
                    btnBulk.innerHTML = "🗑️ Borrar código " + escapeHtml(targetSku) + " (" + labelCount + " etiqueta" + (labelCount === 1 ? "" : "s") + ")";
                } else {
                    btnBulk.dataset.targetSku = "";
                    btnBulk.style.display = "none";
                }
            }
        }

        function escapeHtml(str) {
            var div = document.createElement("div");
            div.textContent = str || "";
            return div.innerHTML;
        }

        function buildCode39Svg(value) {
            var text = String(value || "").trim().toUpperCase();
            if (!text) return "";
            if (!/^[0-9A-Z. \\-$/+%]+$/.test(text)) return "";

            var map = {
                "0":"nnnwwnwnn","1":"wnnwnnnnw","2":"nnwwnnnnw","3":"wnwwnnnnn","4":"nnnwwnnnw","5":"wnnwwnnnn","6":"nnwwwnnnn","7":"nnnwnnwnw","8":"wnnwnnwnn","9":"nnwwnnwnn",
                "A":"wnnnnwnnw","B":"nnwnnwnnw","C":"wnwnnwnnn","D":"nnnnwwnnw","E":"wnnnwwnnn","F":"nnwnwwnnn","G":"nnnnnwwnw","H":"wnnnnwwnn","I":"nnwnnwwnn","J":"nnnnwwwnn",
                "K":"wnnnnnnww","L":"nnwnnnnww","M":"wnwnnnnwn","N":"nnnnwnnww","O":"wnnnwnnwn","P":"nnwnwnnwn","Q":"nnnnnnwww","R":"wnnnnnwwn","S":"nnwnnnwwn","T":"nnnnwnwwn",
                "U":"wwnnnnnnw","V":"nwwnnnnnw","W":"wwwnnnnnn","X":"nwnnwnnnw","Y":"wwnnwnnnn","Z":"nwwnwnnnn","-":"nwnnnnwnw",".":"wwnnnnwnn"," ":"nwwnnnwnn","$":"nwnwnwnnn",
                "/":"nwnwnnnwn","+":"nwnnnwnwn","%":"nnnwnwnwn","*":"nwnnwnwnn"
            };

            var encoded = "*" + text + "*";
            var x = 10;
            var narrow = 2;
            var wide = 5;
            var bars = [];
            for (var i = 0; i < encoded.length; i++) {
                var pattern = map[encoded.charAt(i)];
                if (!pattern) return "";
                for (var j = 0; j < pattern.length; j++) {
                    var isBar = (j % 2) === 0;
                    var width = pattern.charAt(j) === "w" ? wide : narrow;
                    if (isBar) {
                        bars.push("<rect x=\"" + x + "\" y=\"0\" width=\"" + width + "\" height=\"72\" fill=\"#000\"></rect>");
                    }
                    x += width;
                }
                x += narrow;
            }
            var totalWidth = x + 10;
            return "<div class=\"barcode-svg-wrap\"><svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 " + totalWidth + " 72\" role=\"img\" aria-label=\"" + escapeHtml(text) + "\">" + bars.join("") + "</svg></div>";
        }

        function buildLabelCardHtml(data, idx, totalCount) {
            var wh = data.warehouse_name || "Bodega";
            var code = data.traceability_code || data.code || "-";
            var sku = (data.item_code || "-");
            var desc = data.item_description || "";
            var meta = (data.created_at || "") + " · Op: " + (data.operator_name || "");
            var barcodeHtml = buildCode39Svg(code);
            var bultoBadge = (totalCount > 1 || (data.total_packages && data.total_packages > 1))
                ? ("<div style=\"font-size:13px;font-weight:900;color:#000;margin-bottom:5px;background:#fff;border:2px solid #000;padding:3px 10px;border-radius:6px;display:inline-block\">BULTO " + (data.package_number || (idx + 1)) + " DE " + (data.total_packages || totalCount) + "</div>")
                : "";

            var specsArr = [];
            if (data.width_mm && parseFloat(data.width_mm) > 0) specsArr.push("Ancho: " + Math.round(data.width_mm) + " mm");
            if (data.microns && parseFloat(data.microns) > 0) specsArr.push("Micras: " + Math.round(data.microns) + " µm");
            if (data.meters && parseFloat(data.meters) > 0) specsArr.push("Largo: " + parseFloat(data.meters).toLocaleString("es-CL") + " m");
            var specsHtml = specsArr.length > 0 ? ("<div style=\"font-size:10px;font-weight:900;color:#000;margin-bottom:5px;line-height:1.15;word-break:break-word\">ESPEC.: " + escapeHtml(specsArr.join(" · ")) + "</div>") : "";

            return "<div class=\"printable-label printable-label-page\" style=\"margin-top:" + (idx > 0 ? "14px" : "0") + "\">" +
                "<div style=\"font-size:15px;font-weight:900;color:#000;letter-spacing:0.8px;margin-bottom:1px;line-height:1.05\">UNIBAG CHILE</div>" +
                "<div style=\"font-size:10px;font-weight:900;color:#000;text-transform:uppercase;margin-bottom:5px;line-height:1.05\">" + escapeHtml(wh) + "</div>" +
                bultoBadge +
                barcodeHtml +
                "<div style=\"font-family:monospace;font-size:18px;font-weight:900;color:#000;letter-spacing:1px;margin:2px 0 6px;line-height:1.05;word-break:break-word\">" + escapeHtml(code) + "</div>" +
                "<div style=\"border-top:2px solid #000;padding-top:7px;text-align:left;font-size:11px\">" +
                    "<div style=\"font-weight:900;font-size:44px;color:#000;margin-bottom:3px;line-height:0.92;letter-spacing:0.35px\">" + escapeHtml(sku) + "</div>" +
                    "<div style=\"color:#000;margin-bottom:5px;font-size:10.5px;font-weight:800;line-height:1.15;word-break:break-word\">" + escapeHtml(desc) + "</div>" +
                    specsHtml +
                    "<div style=\"font-size:8.5px;color:#000;margin-top:6px;text-align:center;font-weight:800;line-height:1.1\">" + escapeHtml(meta) + "</div>" +
                "</div>" +
            "</div>";
        }

        function renderLabelList(list) {
            var container = document.getElementById("printableArea");
            container.innerHTML = list.map(function(data, idx) {
                return buildLabelCardHtml(data, idx, list.length);
            }).join("");
            var count = list.length;
            document.getElementById("lbl_modal_label_count").textContent = count > 1 ? (" (" + count + " etiquetas)") : " (1 etiqueta)";
        }

        function openPrintLabel(dataOrList, exactBatch) {
            var list = Array.isArray(dataOrList) ? dataOrList : [dataOrList];
            if (list.length === 0) return;
            document.getElementById("print_batch_notice").style.display = "none";
            document.getElementById("print_popup_error").style.display = "none";

            var toolbar = document.getElementById("multi_label_toolbar");
            currentEntryForSplit = null;
            var sameSkuInList = list.every(function(row) {
                return normalizeSku(row.item_code) === normalizeSku(list[0].item_code);
            });
            setupSameSkuToolbar(sameSkuInList ? (list[0] || null) : null);

            // Si es un solo registro pero tiene quantity > 1 (ej: 10 bobinas), dar la opción de imprimir 1 etiqueta por cada unidad
            if (!exactBatch && list.length === 1 && parseFloat(list[0].quantity) > 1.0001) {
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

            if (exactBatch) {
                var batchesModal = document.getElementById("traceabilityBatchesModal");
                returnToLabelBatches = !!batchesModal && batchesModal.classList.contains("open");
                if (batchesModal) batchesModal.classList.remove("open");
                document.getElementById("same_sku_toolbar").style.display = "none";
                document.getElementById("delete_label_toolbar").style.display = "none";
                var expected = Math.max(1, Number(list[0].total_packages) || 1);
                var batchNotice = document.getElementById("print_batch_notice");
                var batchIdentifier = list[0].batch_id ? String(list[0].batch_id).slice(0, 8).toUpperCase()
                    : "#" + (Number(list[0].id) - Math.max(1, Number(list[0].package_number) || 1) + 1);
                batchNotice.textContent = "Lote " + batchIdentifier + ": " + list.length
                    + (list.length === 1 ? " etiqueta con su codigo original." : " etiquetas con sus codigos originales.")
                    + " No genera un nuevo ingreso."
                    + (list.length !== expected ? " Solo hay " + list.length + " de " + expected + " etiquetas disponibles." : "");
                batchNotice.style.display = "block";
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
            if (returnToLabelBatches) {
                document.getElementById("traceabilityBatchesModal").classList.add("open");
                returnToLabelBatches = false;
            }
            currentSameSkuEntries = [];
            currentPrintSourceEntry = null;
            setDeleteToolbarVisibility(false);
        }

        function printLabelNow() {
            var printable = document.getElementById("printableArea").innerHTML;
            var win = window.open("", "_blank", "width=520,height=700");
            var popupError = document.getElementById("print_popup_error");
            popupError.style.display = win ? "none" : "block";
            if (!win) {
                popupError.textContent = "El navegador bloqueo la ventana de impresion. Habilita las ventanas emergentes y vuelve a imprimir; las etiquetas siguen disponibles.";
                return;
            }
            win.document.write("<!doctype html><html><head><title>Imprimir Etiquetas</title><style>" +
                "@page { size: 100mm 70mm; margin: 0; }" +
                "* { box-sizing: border-box; }" +
                "body { font-family: -apple-system, BlinkMacSystemFont, Arial, sans-serif; margin: 0; padding: 0; background: #fff; color: #000; text-align: center; -webkit-print-color-adjust: exact; print-color-adjust: exact; }" +
                ".barcode-svg-wrap { width:94%; margin:1mm auto 0.8mm auto; }" +
                ".barcode-svg-wrap svg { display:block; width:100%; height:11mm; }" +
                ".printable-label { width: 100mm; height: 70mm; max-width: 100mm; max-height: 70mm; border: 0.8mm solid #000; border-radius: 0; padding: 1.8mm 2.4mm; margin: 0 auto 10px auto; box-sizing: border-box; page-break-inside: avoid; break-inside: avoid; overflow: hidden; display: flex; flex-direction: column; justify-content: flex-start; color:#000 !important; }" +
                "@media print {" +
                    "body { margin: 0 !important; padding: 0 !important; width: 100mm !important; height: 70mm !important; }" +
                    ".printable-label-page { width: 100mm !important; height: 70mm !important; margin: 0 !important; padding: 1.8mm 2.4mm !important; page-break-after: always !important; break-after: page !important; page-break-inside: avoid !important; break-inside: avoid !important; border: none !important; color:#000 !important; }" +
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

        function deleteGroupedSku(sku, labelCount) {
            sku = String(sku || "").trim();
            if (!sku) {
                alert("No se pudo identificar el código agrupado para borrarlo.");
                return;
            }
            var countText = parseInt(labelCount || 0, 10);
            var msg = "⚠️ ¿Está seguro de borrar todas las etiquetas del código " + sku + "?\\n\\nSe eliminarán " + countText + " etiqueta(s) agrupadas y el stock quedará liberado nuevamente.";
            if (confirm(msg)) {
                submitDeleteForm(0, sku);
            }
        }

        function deleteAllFilteredBySku() {
            var btnBulk = document.getElementById("btn_delete_filtered_sku");
            var targetSku = btnBulk ? String(btnBulk.dataset.targetSku || "").trim() : "";
            if (!targetSku) return;
            var msg = "⚠️ ¿Está completamente seguro de eliminar todas las etiquetas del código \x27" + targetSku + "\x27 en esta bodega?\\n\\nEsta acción borrará las etiquetas agrupadas y liberará el stock en bodega.";
            if (confirm(msg)) {
                submitDeleteForm(0, targetSku);
            }
        }

        // Si se abrió con filtro en URL, aplicar filtro inmediatamente
        var initFilterInput = document.getElementById("trz-live-filter");
        if (initFilterInput && initFilterInput.value.trim() !== "") {
            filterRecentEntries(initFilterInput.value);
        }

        // Si se generaron etiquetas en esta petición, abrir modal automáticamente
        if (autoPrintData && autoPrintData.length > 0) {
            setTimeout(function() { openPrintLabel(autoPrintData, reprintBatchAnchorId > 0); }, 250);
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
    $parentRoll = $treeData['parent_roll'] ?? null;
    $virginRoll = $treeData['virgin_roll'] ?? null;
    $pallet = $treeData['pallet'] ?? null;
    $box = $treeData['box'] ?? null;
    $po = $treeData['purchase_order'] ?? null;
    $supplier = $treeData['supplier'] ?? null;
    $wo = $treeData['work_order'] ?? null;
    $events = (array)($treeData['events'] ?? []);
    $childRolls = (array)($treeData['child_rolls'] ?? []);
    $boxes = (array)($treeData['boxes'] ?? []);
    $pallets = (array)($treeData['pallets'] ?? []);
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
    $weightKg = !empty($roll['weight_kg']) ? (float)$roll['weight_kg'] : 0.0;

    $fmtQty = static function (float $value, int $decimals = 0): string {
        return number_format($value, $decimals, ',', '.');
    };
    $fmtDate = static function ($value): string {
        if (empty($value)) {
            return '-';
        }
        if (is_numeric($value)) {
            $ts = (int)$value;
            return $ts > 0 ? date('d/m/Y H:i', $ts) : '-';
        }
        $ts = strtotime((string)$value);
        return $ts ? date('d/m/Y H:i', $ts) : '-';
    };
    $eventLabel = static function (string $evtType): string {
        return match ($evtType) {
            'apertura' => 'Alistamiento',
            'prod', 'prodsericolor' => 'Producción',
            'pause' => 'Pausa',
            'mantencion' => 'Mantención',
            'merma' => 'Merma',
            'material' => 'Material',
            'colacion' => 'Colación',
            default => ucfirst(strtolower(str_replace('_', ' ', $evtType))),
        };
    };
    $eventBadge = static function (string $evtType): array {
        return match ($evtType) {
            'apertura' => ['#DBEAFE', '#1D4ED8'],
            'prod', 'prodsericolor' => ['#DCFCE7', '#166534'],
            'pause' => ['#FEE2E2', '#B91C1C'],
            'mantencion' => ['#F3E8FF', '#7E22CE'],
            'merma' => ['#FFE4E6', '#BE123C'],
            'material' => ['#FEF3C7', '#B45309'],
            'colacion' => ['#E0F2FE', '#0369A1'],
            default => ['#E2E8F0', '#334155'],
        };
    };

    $plannedQty = (float)($wo['planned_qty'] ?? $wo['ag_amount'] ?? 0);
    $totalProducedQty = (float)($wo['total_produced_qty'] ?? 0);
    $totalOutputKg = (float)($wo['total_output_kg'] ?? 0);
    $totalOutputMeters = (float)($wo['total_output_meters'] ?? 0);
    $totalMermaUnits = (float)($wo['total_merma_units'] ?? 0);
    $totalMermaKg = (float)($wo['total_merma_kg'] ?? 0);
    if ($totalProducedQty <= 0 || $totalOutputKg <= 0 || $totalOutputMeters <= 0) {
        foreach ($events as $eventRow) {
            $evtType = (string)($eventRow['evt_type'] ?? '');
            if (!in_array($evtType, ['prod', 'prodsericolor'], true)) {
                continue;
            }
            if ($totalProducedQty <= 0) {
                $totalProducedQty += (float)($eventRow['evt_amount'] ?? 0);
            }
            if ($totalOutputKg <= 0) {
                $totalOutputKg += (float)($eventRow['prod_bobina_kg'] ?? 0);
            }
            if ($totalOutputMeters <= 0) {
                $totalOutputMeters += (float)($eventRow['evt_amount_metros_lineales'] ?? 0);
            }
        }
    }
    $completionPct = ($plannedQty > 0 && $totalProducedQty > 0) ? min(999.0, ($totalProducedQty / $plannedQty) * 100) : 0.0;

    $childRollCount = count($childRolls);
    $childRollKg = 0.0;
    $childRollMeters = 0.0;
    foreach ($childRolls as $cRoll) {
        $childRollKg += (float)($cRoll['weight_kg'] ?? 0);
        $childRollMeters += (float)($cRoll['meters'] ?? 0);
    }

    $boxCount = count($boxes);
    $boxUnits = 0.0;
    foreach ($boxes as $bx) {
        $boxUnits += (float)($bx['units_qty'] ?? 0);
    }

    $allPallets = [];
    if (!empty($pallet)) {
        $allPallets[] = $pallet;
    }
    foreach ($pallets as $pal) {
        $allPallets[] = $pal;
    }
    foreach ($boxes as $bx) {
        if (!empty($bx['pallet_id'])) {
            $allPallets[] = ['id' => (int)$bx['pallet_id']];
        }
    }
    $uniquePallets = [];
    foreach ($allPallets as $pal) {
        $palKey = (string)($pal['pallet_code'] ?? ('ID-' . (int)($pal['id'] ?? 0)));
        if ($palKey === 'ID-0') {
            continue;
        }
        $uniquePallets[$palKey] = $pal;
    }
    $palletCount = count($uniquePallets);

    $html = '<div class="tree-flow-wrap" style="font-family:inherit">';

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

    $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(165px,1fr));gap:10px;margin:0 0 18px 0">';
    $html .= '<div class="trz-card" style="padding:14px;background:#fff;border:1px solid #E2E8F0"><div style="font-size:11px;font-weight:800;color:#64748B;text-transform:uppercase">Ubicación actual</div><div style="font-size:16px;font-weight:900;color:#0F172A;margin-top:4px">' . h($whName) . '</div><div style="font-size:12px;color:#64748B;margin-top:3px">' . h($statusText) . '</div></div>';
    $html .= '<div class="trz-card" style="padding:14px;background:#fff;border:1px solid #E2E8F0"><div style="font-size:11px;font-weight:800;color:#64748B;text-transform:uppercase">Cantidad actual</div><div style="font-size:16px;font-weight:900;color:#0F172A;margin-top:4px">' . $fmtQty($qty, 2) . ' <span style="font-size:11px;color:#64748B">' . h($unit) . '</span></div><div style="font-size:12px;color:#64748B;margin-top:3px">' . ($weightKg > 0 ? ($fmtQty($weightKg, 2) . ' kg') : 'Material trazado') . '</div></div>';
    $html .= '<div class="trz-card" style="padding:14px;background:#fff;border:1px solid #E2E8F0"><div style="font-size:11px;font-weight:800;color:#64748B;text-transform:uppercase">Producido OT</div><div style="font-size:16px;font-weight:900;color:#166534;margin-top:4px">' . $fmtQty($totalProducedQty, 0) . ' un</div><div style="font-size:12px;color:#64748B;margin-top:3px">' . ($plannedQty > 0 ? ('Plan: ' . $fmtQty($plannedQty, 0) . ' · ' . $fmtQty($completionPct, 1) . ' %') : 'Sin meta planificada') . '</div></div>';
    $html .= '<div class="trz-card" style="padding:14px;background:#fff;border:1px solid #E2E8F0"><div style="font-size:11px;font-weight:800;color:#64748B;text-transform:uppercase">Salida generada</div><div style="font-size:16px;font-weight:900;color:#6D28D9;margin-top:4px">' . $childRollCount . ' bob. · ' . $boxCount . ' cajas</div><div style="font-size:12px;color:#64748B;margin-top:3px">' . $palletCount . ' pallets · ' . $fmtQty($boxUnits, 0) . ' un embaladas</div></div>';
    $html .= '</div>';

    $html .= '<div style="display:flex;flex-direction:column;gap:14px">';

    $html .= '<div class="trz-card" style="padding:16px;background:#fff">';
    $html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">';
    $html .= '<span style="font-size:11.5px;font-weight:800;color:#0369a1;background:#e0f2fe;border:1px solid #bae6fd;padding:3px 10px;border-radius:6px">1. Origen y adquisición</span>';
    if ($po && !empty($po['date'])) {
        $html .= '<span style="font-size:11.5px;font-weight:700;color:#64748b">' . h((string)$po['date']) . '</span>';
    }
    $html .= '</div>';

    if ($po || $supplier) {
        $html .= '<div style="font-size:14px;font-weight:800;color:#0f172a">' . h((string)($supplier['name'] ?? 'Proveedor Nacional')) . '</div>';
        $html .= '<div style="font-size:12px;color:#64748b;margin-top:2px">RUT: <strong>' . h((string)($supplier['rut'] ?? '-')) . '</strong> · País: <strong>' . h((string)($supplier['country'] ?? 'Chile')) . '</strong></div>';
        if ($po && !empty($po['po_code'])) {
            $html .= '<div style="margin-top:8px;font-size:12.5px;color:#0369a1;background:#f0f9ff;padding:5px 10px;border-radius:6px;font-weight:700;display:inline-block">Orden de Compra ERP: ' . h((string)$po['po_code']) . '</div>';
        }
    } else {
        $html .= '<div style="font-size:12.5px;color:#475569">Ingreso directo a existencias en bodega / Catálogo ERP (sin orden de compra importada asociada).</div>';
    }

    if ($virginRoll || $parentRoll || $roll) {
        $html .= '<div style="margin-top:12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:10px 12px">';
        $html .= '<div style="font-size:11px;font-weight:800;color:#64748B;text-transform:uppercase;margin-bottom:6px">Cadena del material</div>';
        $chainParts = [];
        if ($virginRoll) {
            $chainParts[] = '<span style="font-family:monospace;font-weight:800;color:#0F172A">' . h((string)($virginRoll['roll_code'] ?? '')) . '</span><span style="color:#64748B"> Bobina virgen</span>';
        }
        if ($parentRoll && (!$virginRoll || (int)($parentRoll['id'] ?? 0) !== (int)($virginRoll['id'] ?? 0))) {
            $chainParts[] = '<span style="font-family:monospace;font-weight:800;color:#0F172A">' . h((string)($parentRoll['roll_code'] ?? '')) . '</span><span style="color:#64748B"> Bobina madre</span>';
        }
        if ($roll) {
            $chainParts[] = '<span style="font-family:monospace;font-weight:800;color:#0F766E">' . h((string)($roll['roll_code'] ?? '')) . '</span><span style="color:#64748B"> Elemento consultado</span>';
        }
        $html .= '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;font-size:12.5px">' . implode('<span style="color:#94A3B8">→</span>', $chainParts) . '</div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    $html .= '<div style="text-align:center;color:#00A9A6;font-size:18px;margin:-8px 0;user-select:none">⬇</div>';

    $html .= '<div class="trz-card" style="padding:18px;border:2px solid #00A9A6;background:#fcfefe">';
    $html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">';
    $html .= '<span style="font-size:11.5px;font-weight:800;color:#0f766e;background:#f0fdfa;border:1px solid #99f6e4;padding:3px 10px;border-radius:6px">2. Existencia física y bodega</span>';
    $html .= '<span style="font-size:12px;font-weight:700;color:#0f766e">' . h($whName) . '</span>';
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
        $html .= '<div style="margin-top:10px;font-size:12px;color:#64748b;background:#f8fafc;padding:6px 10px;border-radius:6px">Observaciones: ' . h($comments) . '</div>';
    }

    $html .= '<div style="margin-top:12px;padding-top:10px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;font-size:11.5px;color:#64748b">';
    $html .= '<span>Operador Responsable: <strong>' . h($operator) . '</strong></span>';
    $html .= '<span>Fecha de Ingreso: <strong>' . h($date) . '</strong></span>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '<div style="text-align:center;color:#00A9A6;font-size:18px;margin:-8px 0;user-select:none">⬇</div>';

    $html .= '<div class="trz-card" style="padding:16px;background:#fff">';
    $html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">';
    $html .= '<span style="font-size:11.5px;font-weight:800;color:#b45309;background:#fffbeb;border:1px solid #fde68a;padding:3px 10px;border-radius:6px">3. Producción y transformación</span>';
    if ($wo) {
        $html .= '<span style="font-size:12px;font-weight:800;color:#b45309">OT #' . h((string)($wo['prd_number'] ?? '')) . '</span>';
    } else {
        $html .= '<span style="font-size:11.5px;font-weight:700;color:#0f766e">En Stock Bodega</span>';
    }
    $html .= '</div>';

    if ($wo) {
        $html .= '<div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:flex-start">';
        $html .= '<div>';
        $html .= '<div style="font-size:13.5px;font-weight:800;color:#0f172a">Máquina: ' . h((string)($wo['equipo_name'] ?? '')) . ' <span style="font-size:12px;color:#64748b">(' . h((string)($wo['type_ant_title'] ?? '')) . ')</span></div>';
        $html .= '<div style="font-size:12px;color:#475569;margin-top:2px">Operador OT: <strong>' . h(trim((string)(($wo['wrk_firstname'] ?? '') . ' ' . ($wo['wrk_lastname'] ?? '')))) . '</strong> · Pedido: <strong>' . h((string)($wo['req_number'] ?? '-')) . '</strong></div>';
        $html .= '</div>';
        $html .= '<div style="background:#FFF7ED;border:1px solid #FED7AA;border-radius:8px;padding:8px 10px;min-width:190px">';
        $html .= '<div style="font-size:11px;font-weight:800;color:#9A3412;text-transform:uppercase">Cumplimiento OT</div>';
        $html .= '<div style="font-size:18px;font-weight:900;color:#9A3412;margin-top:3px">' . $fmtQty($completionPct, 1) . ' %</div>';
        $html .= '<div style="font-size:11.5px;color:#7C2D12;margin-top:2px">' . $fmtQty($totalProducedQty, 0) . ' / ' . ($plannedQty > 0 ? $fmtQty($plannedQty, 0) : '-') . ' un</div>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-top:12px">';
        $summaryCards = [
            ['label' => 'Planificado', 'value' => ($plannedQty > 0 ? $fmtQty($plannedQty, 0) . ' un' : '-'), 'sub' => 'Meta programada OT', 'bg' => '#F8FAFC', 'border' => '#E2E8F0', 'color' => '#0F172A'],
            ['label' => 'Producido real', 'value' => $fmtQty($totalProducedQty, 0) . ' un', 'sub' => ($totalOutputMeters > 0 ? $fmtQty($totalOutputMeters, 0) . ' m lineales' : 'Sin metros registrados'), 'bg' => '#ECFDF5', 'border' => '#A7F3D0', 'color' => '#166534'],
            ['label' => 'Salida en kg', 'value' => $fmtQty($totalOutputKg, 2) . ' kg', 'sub' => ($childRollCount > 0 ? ($childRollCount . ' bobinas hijas') : 'Sin bobinas hijas'), 'bg' => '#EFF6FF', 'border' => '#BFDBFE', 'color' => '#1D4ED8'],
            ['label' => 'Merma registrada', 'value' => $fmtQty($totalMermaKg, 2) . ' kg', 'sub' => ($totalMermaUnits > 0 ? ($fmtQty($totalMermaUnits, 0) . ' un') : 'Sin unidades de merma'), 'bg' => '#FFF1F2', 'border' => '#FECDD3', 'color' => '#BE123C'],
        ];
        foreach ($summaryCards as $card) {
            $html .= '<div style="background:' . $card['bg'] . ';border:1px solid ' . $card['border'] . ';border-radius:10px;padding:12px 14px">';
            $html .= '<div style="font-size:11px;font-weight:800;color:#64748B;text-transform:uppercase">' . h($card['label']) . '</div>';
            $html .= '<div style="font-size:18px;font-weight:900;color:' . $card['color'] . ';margin-top:4px">' . h($card['value']) . '</div>';
            $html .= '<div style="font-size:11.5px;color:#64748B;margin-top:3px">' . h($card['sub']) . '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';

        if ($events !== []) {
            $recentEvents = array_slice(array_reverse($events), 0, 8);
            $html .= '<div style="margin-top:12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:10px 12px;font-size:11.5px">';
            $html .= '<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px">';
            $html .= '<div style="font-weight:800;color:#334155">Eventos relevantes de la OT</div>';
            $html .= '<div style="font-size:11px;color:#64748B">' . count($events) . ' registros</div>';
            $html .= '</div>';
            $html .= '<div style="display:flex;flex-direction:column;gap:6px">';
            foreach ($recentEvents as $ev) {
                $evtType = (string)($ev['evt_type'] ?? '');
                [$badgeBg, $badgeColor] = $eventBadge($evtType);
                $evtAmount = (float)($ev['evt_amount'] ?? 0);
                $evtKg = (float)($ev['prod_bobina_kg'] ?? 0);
                $evtMts = (float)($ev['evt_amount_metros_lineales'] ?? 0);
                $evtMetrics = [];
                if (in_array($evtType, ['prod', 'prodsericolor'], true) && $evtAmount > 0) {
                    $evtMetrics[] = $fmtQty($evtAmount, 0) . ' un';
                }
                if ($evtKg > 0) {
                    $evtMetrics[] = $fmtQty($evtKg, 2) . ' kg';
                }
                if ($evtMts > 0) {
                    $evtMetrics[] = $fmtQty($evtMts, 0) . ' m';
                }
                $evtStatusText = ((int)($ev['evt_status'] ?? 0) === 1) ? 'Abierto' : 'Cerrado';
                $html .= '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;background:#fff;border:1px solid #E2E8F0;border-radius:8px;padding:8px 10px">';
                $html .= '<div style="display:flex;gap:8px;align-items:flex-start;min-width:220px;flex:1">';
                $html .= '<span style="background:' . $badgeBg . ';color:' . $badgeColor . ';border-radius:999px;padding:4px 8px;font-size:10.5px;font-weight:800;white-space:nowrap">' . h($eventLabel($evtType)) . '</span>';
                $html .= '<div><div style="font-size:12px;font-weight:700;color:#0F172A">' . h((string)($ev['evt_desc'] ?? $eventLabel($evtType))) . '</div><div style="font-size:11px;color:#64748B;margin-top:2px">' . h($fmtDate($ev['evt_crtdat'] ?? null)) . ' · ' . h($evtStatusText) . '</div></div>';
                $html .= '</div>';
                $html .= '<div style="font-size:11.5px;font-weight:700;color:#334155">' . h(!empty($evtMetrics) ? implode(' · ', $evtMetrics) : 'Sin cantidad asociada') . '</div>';
                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
    } else {
        $html .= '<div style="font-size:12.5px;color:#64748b">Bulto en estado de inventario disponible para uso o despacho (no ha sido transformado en máquina aún).</div>';
    }
    $html .= '</div>';

    if ($childRolls !== [] || $boxes !== [] || $palletCount > 0) {
        $html .= '<div style="text-align:center;color:#00A9A6;font-size:18px;margin:-8px 0;user-select:none">⬇</div>';
        $html .= '<div class="trz-card" style="padding:16px;background:#fff">';
        $html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">';
        $html .= '<span style="font-size:11.5px;font-weight:800;color:#6d28d9;background:#f5f3ff;border:1px solid #ddd6fe;padding:3px 10px;border-radius:6px">4. Salida generada</span>';
        $html .= '<span style="font-size:11.5px;font-weight:700;color:#6d28d9">' . ($childRollCount + $boxCount + $palletCount) . ' registros de salida</span>';
        $html .= '</div>';
        $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:12px">';
        $html .= '<div style="background:#FAF5FF;border:1px solid #E9D5FF;border-radius:10px;padding:12px"><div style="font-size:11px;font-weight:800;color:#7C3AED;text-transform:uppercase">Bobinas hijas</div><div style="font-size:18px;font-weight:900;color:#6D28D9;margin-top:4px">' . $childRollCount . '</div><div style="font-size:11.5px;color:#6B7280;margin-top:3px">' . $fmtQty($childRollKg, 2) . ' kg · ' . $fmtQty($childRollMeters, 0) . ' m</div></div>';
        $html .= '<div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:12px"><div style="font-size:11px;font-weight:800;color:#B45309;text-transform:uppercase">Cajas</div><div style="font-size:18px;font-weight:900;color:#B45309;margin-top:4px">' . $boxCount . '</div><div style="font-size:11.5px;color:#6B7280;margin-top:3px">' . $fmtQty($boxUnits, 0) . ' unidades embaladas</div></div>';
        $html .= '<div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:12px"><div style="font-size:11px;font-weight:800;color:#1D4ED8;text-transform:uppercase">Pallets</div><div style="font-size:18px;font-weight:900;color:#1D4ED8;margin-top:4px">' . $palletCount . '</div><div style="font-size:11.5px;color:#6B7280;margin-top:3px">Consolidación de despacho</div></div>';
        $html .= '</div>';

        $html .= '<div style="display:flex;flex-direction:column;gap:8px">';
        foreach ($childRolls as $cRoll) {
            $cRollDesc = (string)($cRoll['sku_desc'] ?? '');
            $targetSkuBadge = '';
            if (str_contains($cRollDesc, 'Cargada a SKU:')) {
                $parts = explode(' - Cargada a SKU: ', $cRollDesc, 2);
                $targetSkuBadge = ' <span style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;padding:1px 6px;border-radius:4px;font-size:10.5px;font-weight:700">🎯 Cargada a SKU: ' . h($parts[1]) . '</span>';
            }
            $html .= '<div style="background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;padding:10px 12px;display:flex;justify-content:space-between;align-items:center;font-size:12px;flex-wrap:wrap;gap:8px">';
            $html .= '<div><span style="font-weight:800;color:#6d28d9">Bobina hija: ' . h((string)$cRoll['roll_code']) . '</span> <span style="color:#64748b">(' . h((string)($cRoll['sku_code'] ?? '')) . ')</span>' . $targetSkuBadge . '<div style="font-size:11px;color:#64748B;margin-top:2px">' . h((string)($cRoll['status'] ?? '')) . '</div></div>';
            $html .= '<div style="font-weight:800;color:#0f172a;text-align:right">' . $fmtQty((float)($cRoll['weight_kg'] ?? 0), 2) . ' kg<div style="font-size:11px;color:#64748B;margin-top:2px">' . $fmtQty((float)($cRoll['meters'] ?? 0), 0) . ' m</div></div>';
            $html .= '</div>';
        }
        foreach ($boxes as $bx) {
            $boxPalletRef = !empty($bx['pallet_id']) ? (' · pallet #' . (int)$bx['pallet_id']) : '';
            $html .= '<div style="background:#fffbeb;border:1px solid #fef3c7;border-radius:8px;padding:10px 12px;display:flex;justify-content:space-between;align-items:center;font-size:12px;flex-wrap:wrap;gap:8px">';
            $html .= '<div><span style="font-weight:800;color:#b45309">Caja: ' . h((string)$bx['box_code']) . '</span> <span style="color:#64748b">(' . h((string)($bx['final_sku'] ?? '')) . ')</span><div style="font-size:11px;color:#64748B;margin-top:2px">' . h((string)($bx['status'] ?? '')) . $boxPalletRef . '</div></div>';
            $html .= '<div style="font-weight:800;color:#0f172a">' . $fmtQty((float)($bx['units_qty'] ?? 1), 0) . ' un</div>';
            $html .= '</div>';
        }
        foreach ($uniquePallets as $pal) {
            $palCode = (string)($pal['pallet_code'] ?? ('PALLET #' . (int)($pal['id'] ?? 0)));
            $palBoxCount = (int)($pal['box_count'] ?? 0);
            $html .= '<div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:8px;padding:10px 12px;display:flex;justify-content:space-between;align-items:center;font-size:12px;flex-wrap:wrap;gap:8px">';
            $html .= '<div><span style="font-weight:800;color:#1D4ED8">Pallet: ' . h($palCode) . '</span> <span style="color:#64748b">(' . h((string)($pal['final_sku'] ?? $sku)) . ')</span><div style="font-size:11px;color:#64748B;margin-top:2px">' . h((string)($pal['status'] ?? '')) . '</div></div>';
            $html .= '<div style="font-weight:800;color:#0f172a">' . ($palBoxCount > 0 ? ($palBoxCount . ' cajas') : 'Consolidado') . '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }

    if ($movements !== []) {
        $html .= '<div style="text-align:center;color:#00A9A6;font-size:18px;margin:-8px 0;user-select:none">⬇</div>';
        $html .= '<div class="trz-card" style="padding:16px;background:#fff">';
        $html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">';
        $html .= '<span style="font-size:11.5px;font-weight:800;color:#334155;background:#f1f5f9;border:1px solid #cbd5e1;padding:3px 10px;border-radius:6px">5. Cadena de custodia y movimientos</span>';
        $html .= '<span style="font-size:11.5px;font-weight:700;color:#64748b">' . count($movements) . ' registros</span>';
        $html .= '</div>';
        $html .= '<div style="display:flex;flex-direction:column;gap:6px">';
        foreach ($movements as $m) {
            $mDate = !empty($m['created_at']) ? date('d/m/Y H:i', strtotime((string)$m['created_at'])) : '-';
            $mType = (string)($m['movement_type'] ?? 'MOVIMIENTO');
            $mPayload = [];
            if (!empty($m['payload'])) {
                $decoded = json_decode((string)$m['payload'], true);
                if (is_array($decoded)) {
                    $mPayload = $decoded;
                }
            }
            $mOperator = trim((string)($mPayload['operator_name'] ?? $mPayload['operator'] ?? ''));
            $toWh = (int)($m['to_warehouse_id'] ?? 0);
            $fromWh = (int)($m['from_warehouse_id'] ?? 0);
            $html .= '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px">';
            $html .= '<div><strong style="color:#0f766e">' . h($mType) . '</strong>' . ($fromWh > 0 ? (' <span style="color:#64748b">Bodega #' . $fromWh . '</span>') : '') . ($toWh > 0 ? (' <span style="color:#64748b">→ Bodega #' . $toWh . '</span>') : '') . ($mOperator !== '' ? (' <span style="color:#94A3B8">· ' . h($mOperator) . '</span>') : '') . '</div>';
            $html .= '<div style="font-size:11px;color:#94a3b8">' . h($mDate) . '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }

    if (!empty($treeData['supply_chain'])) {
        $html .= '<div class="trz-card" style="padding:16px;background:#fff"><h3>Solicitud, Entrega y Recepcion</h3>';
        $html .= '<div style="overflow:auto"><table style="width:100%;font-size:13px"><thead><tr>';
        $html .= '<th>Solicitud / Traspaso ERP</th><th>Solicitado por</th><th>Entregado por</th><th>Recibido por</th></tr></thead><tbody>';
        foreach ($treeData['supply_chain'] as $delivery) {
            $html .= '<tr><td>' . h(substr($delivery['request_key'], 0, 8)) . ' / ' . h((string)($delivery['erp_transfer_id'] ?? '-')) . '</td>';
            foreach (['requested_by', 'delivered_by', 'received_by'] as $field) {
                $actor = json_decode((string)($delivery[$field] ?? '{}'), true) ?: [];
                $dateField = $field === 'received_by' ? 'received_at' : ($field === 'delivered_by' ? 'delivered_at' : 'requested_at');
                $html .= '<td>' . h((string)($actor['name'] ?? 'Pendiente')) . '<br>' . $fmtDate($delivery[$dateField] ?? null) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div></div>';
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

    $body = '<div class="trz-shell">';

    // Header
    $body .= '<div class="trz-header">';
    $body .= '<div>';
    $body .= '<div class="trz-title">Trazabilidad</div>';
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
 * Renderiza la pantalla completa de Traspasos a Producción (Bodega 3000)
 * con registro trazable de solicitante, despachador, máquina y orden de trabajo.
 */
function unibagCanOverrideTransferOperator(string $operatorName): bool
{
    $normalized = strtoupper(trim($operatorName));
    return $normalized !== '' && (
        strpos($normalized, 'JAVIER') !== false
        || strpos($normalized, 'HECTOR') !== false
    );
}

function unibagRenderWarehouseTransfersPage(ReceptionService $service, ?ProductionService $prodService, string $currentOperatorName): void
{
    $availableRolls = $service->listAvailableRollsForTransfer(150);
    $allTransferRolls = $service->listAllRollsForWarehouseTransfer(null, 2500);
    $warehouses = array_values(array_filter(
        $service->listWarehouses(),
        static fn(array $warehouse): bool => (int)($warehouse['id'] ?? 0) > 0
    ));
    $workOrders = $service->listWorkOrdersForTransfer();
    $machines = $service->listProductionMachines();
    $pendingRequests = $service->listPendingMaterialRequestsFromProduction(30);
    $history = $service->listProductionTransferHistory(50);

    $isDispatched = isset($_GET['dispatched']) && (string)$_GET['dispatched'] === '1';
    $isWarehouseMoved = isset($_GET['moved']) && (string)$_GET['moved'] === '1';
    $isBulkDone = isset($_GET['bulk_done']) && (string)$_GET['bulk_done'] === '1';
    $bulkSuccessCount = (int)($_GET['success_count'] ?? 0);
    $bulkFailCount = (int)($_GET['fail_count'] ?? 0);
    $bulkToWh = trim((string)($_GET['to_wh'] ?? ''));
    $bulkTotalWeight = (float)($_GET['total_weight'] ?? 0);
    $bulkTraceOnly = isset($_GET['trace_only']) && (string)$_GET['trace_only'] === '1';
    $bulkErrors = trim((string)($_GET['bulk_errors'] ?? ''));

    $dispatchedRollId = (int)($_GET['roll_id'] ?? 0);
    $dispatchedRollCode = trim((string)($_GET['roll_code'] ?? ''));
    $dispatchedOt = trim((string)($_GET['ot'] ?? ''));
    $dispatchedMachine = trim((string)($_GET['machine'] ?? ''));
    $dispatchedSolicito = trim((string)($_GET['solicito'] ?? ''));
    $dispatchedSaco = trim((string)($_GET['saco'] ?? ''));
    $dispatchedWeight = (float)($_GET['weight'] ?? 0);
    $movedFromWarehouse = trim((string)($_GET['from_wh'] ?? ''));
    $movedToWarehouse = trim((string)($_GET['to_wh'] ?? ''));
    $movedTraceabilityOnly = isset($_GET['trace_only']) && (string)$_GET['trace_only'] === '1';
    $errorMessage = trim((string)($_GET['error'] ?? ''));

    $prefillOt = trim((string)($_GET['prefill_ot'] ?? ''));
    $prefillMachine = trim((string)($_GET['prefill_machine'] ?? ''));
    $prefillSolicitante = trim((string)($_GET['prefill_solicitante'] ?? ''));
    $prefillAmount = trim((string)($_GET['prefill_amount'] ?? ''));
    $canOverrideTransferOperator = unibagCanOverrideTransferOperator($currentOperatorName);
    $transferOperatorFieldAttrs = $canOverrideTransferOperator ? '' : ' readonly';
    $transferOperatorFieldStyle = $canOverrideTransferOperator
        ? 'width:100%;padding:8px 11px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px'
        : 'width:100%;padding:8px 11px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;background:#f8fafc;color:#334155';
    $transferOperatorHelp = $canOverrideTransferOperator
        ? 'Puede ajustar manualmente el operador del traspaso.'
        : 'Se registrará automáticamente el operador actual.';
    $activeTab = trim((string)($_GET['tab'] ?? (($isDispatched || $isWarehouseMoved) ? 'historial' : 'masivo')));

    // Agrupación y métricas de bobinas disponibles para traspaso masivo
    $originWhCounts = [];
    $totalKgAvailable = 0.0;
    $countChildRolls = 0;
    foreach ($allTransferRolls as $r) {
        $wId = (int)$r['warehouse_id'];
        $wName = trim((string)($r['wh_name'] ?? ''));
        $wCode = trim((string)($r['wh_code'] ?? ''));
        $displayName = $wCode !== '' ? "{$wCode} - {$wName}" : $wName;
        if (!isset($originWhCounts[$wId])) {
            $originWhCounts[$wId] = ['id' => $wId, 'name' => $displayName, 'count' => 0];
        }
        $originWhCounts[$wId]['count']++;
        $totalKgAvailable += (float)($r['weight_kg'] ?? 0);
        $isChild = !empty($r['parent_roll_id']) || stripos((string)$r['roll_code'], 'ROLL-') === 0 || ($r['process_stage'] ?? '') === 'CUT';
        if ($isChild) {
            $countChildRolls++;
        }
    }
    uasort($originWhCounts, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);

    // Ordenar bodegas destino priorizando las más frecuentes
    $priorityOrder = [58, 1, 2, 21, 9, 8, 10, 18, 19, 20];
    $sortedWarehouses = $warehouses;
    usort($sortedWarehouses, static function (array $a, array $b) use ($priorityOrder): int {
        $idA = (int)($a['id'] ?? 0);
        $idB = (int)($b['id'] ?? 0);
        $idxA = array_search($idA, $priorityOrder, true);
        $idxB = array_search($idB, $priorityOrder, true);
        if ($idxA !== false && $idxB !== false) {
            return $idxA <=> $idxB;
        }
        if ($idxA !== false) {
            return -1;
        }
        if ($idxB !== false) {
            return 1;
        }
        return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    });

    $body = '<div class="transfers-shell" style="max-width:1280px;margin:0 auto;padding:12px 16px 40px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif">';

    // Encabezado
    $body .= '<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #e2e8f0">';
    $body .= '  <div>';
    $body .= '    <div style="display:flex;align-items:center;gap:10px">';
    $body .= '      <h1 style="margin:0;font-size:24px;font-weight:800;color:#0f172a;letter-spacing:-0.5px">🔄 Traspasos de Bodega</h1>';
    $body .= '      <span style="background:#e0f2fe;color:#0369a1;font-size:12px;font-weight:700;padding:3px 9px;border-radius:12px">Producción y entre bodegas</span>';
    $body .= '    </div>';
    $body .= '    <p style="margin:6px 0 0;font-size:13.5px;color:#64748b;max-width:820px">';
    $body .= '      Mueva bobinas con trazabilidad completa. Puede despacharlas a <strong>producción (Bodega 3000)</strong> o hacer un ';
    $body .= '      <strong>traspaso directo entre bodegas</strong> cuando todavía no exista máquina ni OT definidas.';
    $body .= '    </p>';
    $body .= '  </div>';
    $isJavierUser = unibagIsUserJavier() || (stripos($currentOperatorName, 'JAVIER') !== false);
    $body .= '  <div style="display:flex;align-items:center;gap:8px">';
    if ($isJavierUser) {
        $body .= '    <a href="/stock/bajas" class="btn" style="background:#7c3aed;color:#fff;font-size:13px;font-weight:700;padding:8px 14px;border-radius:6px;text-decoration:none" title="Módulo exclusivo Javier Perozo">🗑️ Baja de Bobinas (Javier)</a>';
    }
    $body .= '    <a href="/stock" class="btn secondary" style="font-size:13px;padding:8px 14px">📦 Consulta de Stock</a>';
    $body .= '    <a href="/stock/traceability" class="btn secondary" style="font-size:13px;padding:8px 14px">Trazabilidad</a>';
    $body .= '  </div>';
    $body .= '</div>';

    // Banner de error si existe
    if ($errorMessage !== '') {
        $body .= '<div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 16px;border-radius:8px;margin-bottom:18px;display:flex;align-items:center;gap:10px">';
        $body .= '  <span style="font-size:20px">⚠️</span>';
        $body .= '  <div style="font-weight:600">' . h($errorMessage) . '</div>';
        $body .= '</div>';
    }

    // Banner de éxito si acaba de despachar
    if ($isDispatched) {
        $body .= '<div style="background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);border:1px solid #86efac;border-radius:12px;padding:18px 22px;margin-bottom:22px;box-shadow:0 4px 12px rgba(22,101,52,0.08)">';
        $body .= '  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px">';
        $body .= '    <div>';
        $body .= '      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">';
        $body .= '        <span style="font-size:22px">✅</span>';
        $body .= '        <span style="font-size:17px;font-weight:800;color:#166534">¡Traspaso a Bodega 3000 registrado exitosamente!</span>';
        $body .= '      </div>';
        $body .= '      <div style="font-size:13.5px;color:#14532d;line-height:1.6">';
        $body .= '        Bobina con código de trazabilidad: <span style="display:inline-block;background:#0f172a;color:#fff;font-family:monospace;font-size:14px;font-weight:700;padding:2px 8px;border-radius:4px;letter-spacing:0.5px">' . h($dispatchedRollCode) . '</span>';
        if ($dispatchedOt !== '') {
            $body .= ' &nbsp;|&nbsp; <strong>OT Destino:</strong> #' . h($dispatchedOt);
        }
        if ($dispatchedMachine !== '') {
            $body .= ' &nbsp;|&nbsp; <strong>Máquina:</strong> ' . h($dispatchedMachine);
        }
        if ($dispatchedSolicito !== '') {
            $body .= ' &nbsp;|&nbsp; <strong>Solicitada por:</strong> ' . h($dispatchedSolicito);
        }
        if ($dispatchedSaco !== '') {
            $body .= ' &nbsp;|&nbsp; <strong>Despachada por:</strong> ' . h($dispatchedSaco);
        }
        if ($dispatchedWeight > 0) {
            $body .= ' &nbsp;|&nbsp; <strong>Peso:</strong> ' . number_format($dispatchedWeight, 2, ',', '.') . ' kg';
        }
        $body .= '      </div>';
        $body .= '    </div>';
        $body .= '    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">';
        if ($dispatchedRollId > 0) {
            $body .= '      <a href="/rolls/' . $dispatchedRollId . '/label?server_print=1" target="_blank" class="btn" style="background:#15803d;color:#fff;font-size:13px;padding:9px 16px;border-radius:6px;font-weight:700;display:inline-flex;align-items:center;gap:6px">';
            $body .= '        <span>🖨️ Imprimir Zebra (100×70)</span>';
            $body .= '      </a>';
            $body .= '      <a href="/rolls/' . $dispatchedRollId . '/label" target="_blank" class="btn secondary" style="font-size:13px;padding:9px 14px;border-radius:6px">';
            $body .= '        <span>👁️ Vista Previa</span>';
            $body .= '      </a>';
        }
        if ($dispatchedRollCode !== '') {
            $body .= '      <a href="/stock/traceability?q=' . urlencode($dispatchedRollCode) . '" class="btn secondary" style="font-size:13px;padding:9px 14px;border-radius:6px">';
            $body .= '        <span>🌳 Ver Árbol 360°</span>';
            $body .= '      </a>';
        }
        $body .= '      <a href="/stock/transfers" class="btn" style="background:#0f172a;color:#fff;font-size:13px;padding:9px 14px;border-radius:6px">';
        $body .= '        <span>➕ Otro Traspaso</span>';
        $body .= '      </a>';
        $body .= '    </div>';
        $body .= '  </div>';
        $body .= '</div>';
    }

    if ($isWarehouseMoved) {
        $body .= '<div style="background:linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);border:1px solid #93c5fd;border-radius:12px;padding:18px 22px;margin-bottom:22px;box-shadow:0 4px 12px rgba(37,99,235,0.08)">';
        $body .= '  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px">';
        $body .= '    <div>';
        $body .= '      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">';
        $body .= '        <span style="font-size:22px">✅</span>';
        $body .= '        <span style="font-size:17px;font-weight:800;color:#1d4ed8">' . ($movedTraceabilityOnly ? '¡Traspaso solo de trazabilidad registrado exitosamente!' : '¡Traspaso entre bodegas registrado exitosamente!') . '</span>';
        $body .= '      </div>';
        $body .= '      <div style="font-size:13.5px;color:#1e3a8a;line-height:1.6">';
        $body .= '        Bobina: <span style="display:inline-block;background:#0f172a;color:#fff;font-family:monospace;font-size:14px;font-weight:700;padding:2px 8px;border-radius:4px;letter-spacing:0.5px">' . h($dispatchedRollCode) . '</span>';
        if ($movedFromWarehouse !== '') {
            $body .= ' &nbsp;|&nbsp; <strong>Desde:</strong> ' . h($movedFromWarehouse);
        }
        if ($movedToWarehouse !== '') {
            $body .= ' &nbsp;|&nbsp; <strong>Hacia:</strong> ' . h($movedToWarehouse);
        }
        if ($dispatchedWeight > 0) {
            $body .= ' &nbsp;|&nbsp; <strong>Peso:</strong> ' . number_format($dispatchedWeight, 2, ',', '.') . ' kg';
        }
        if ($movedTraceabilityOnly) {
            $body .= ' &nbsp;|&nbsp; <strong>Modo:</strong> Solo trazabilidad (sin impacto en inventario)';
        }
        $body .= '      </div>';
        $body .= '    </div>';
        $body .= '    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">';
        if ($dispatchedRollId > 0) {
            $body .= '      <a href="/rolls/' . $dispatchedRollId . '/label?server_print=1" target="_blank" class="btn" style="background:#15803d;color:#fff;font-size:13px;padding:9px 16px;border-radius:6px;font-weight:700;display:inline-flex;align-items:center;gap:6px">';
            $body .= '        <span>🖨️ Imprimir Zebra (100×70)</span>';
            $body .= '      </a>';
            $body .= '      <a href="/rolls/' . $dispatchedRollId . '/label" target="_blank" class="btn secondary" style="font-size:13px;padding:9px 14px;border-radius:6px">';
            $body .= '        <span>👁️ Vista Previa</span>';
            $body .= '      </a>';
        }
        if ($dispatchedRollCode !== '') {
            $body .= '      <a href="/stock/traceability?q=' . urlencode($dispatchedRollCode) . '" class="btn secondary" style="font-size:13px;padding:9px 14px;border-radius:6px">';
            $body .= '        <span>🌳 Ver Trazabilidad</span>';
            $body .= '      </a>';
        }
        $body .= '      <a href="/stock/transfers" class="btn" style="background:#0f172a;color:#fff;font-size:13px;padding:9px 14px;border-radius:6px">';
        $body .= '        <span>➕ Otro Traspaso</span>';
        $body .= '      </a>';
        $body .= '    </div>';
        $body .= '  </div>';
        $body .= '</div>';
    }

    if ($isBulkDone) {
        $body .= '<div style="background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);border:1px solid #86efac;border-radius:12px;padding:18px 22px;margin-bottom:22px;box-shadow:0 4px 12px rgba(22,163,74,0.08)">';
        $body .= '  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px">';
        $body .= '    <div>';
        $body .= '      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">';
        $body .= '        <span style="font-size:24px">⚡</span>';
        $body .= '        <span style="font-size:17px;font-weight:800;color:#15803d">¡Traspaso masivo registrado exitosamente!</span>';
        $body .= '      </div>';
        $body .= '      <div style="font-size:13.5px;color:#166534;line-height:1.6">';
        $body .= '        Se traspasaron con éxito <strong>' . $bulkSuccessCount . ' bobina(s)</strong> (total <strong>' . number_format($bulkTotalWeight, 2, ',', '.') . ' kg</strong>)';
        if ($bulkToWh !== '') {
            $body .= ' hacia <strong>' . h($bulkToWh) . '</strong>';
        }
        $body .= '.<br>';
        $body .= '        <span style="font-size:12.5px;display:inline-block;margin-top:2px;color:#15803d"><strong>Modo:</strong> ' . ($bulkTraceOnly ? 'Solo trazabilidad (sin movimiento en inventario contable ERP)' : 'Traspaso completo (Trazabilidad TRZ + Inventario ERP)') . '</span>';
        if ($bulkFailCount > 0) {
            $body .= '        <div style="margin-top:6px;color:#b91c1c;font-weight:700">⚠️ ' . $bulkFailCount . ' bobina(s) tuvieron errores: ' . h($bulkErrors) . '</div>';
        }
        $body .= '      </div>';
        $body .= '    </div>';
        $body .= '    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
        $body .= '      <a href="/stock/transfers?tab=masivo" class="btn" style="background:#15803d;color:#fff;font-size:13px;padding:9px 14px;border-radius:6px;text-decoration:none">🔄 Otro Traspaso Masivo</a>';
        $body .= '      <a href="/stock/transfers?tab=historial" class="btn secondary" style="font-size:13px;padding:9px 14px;border-radius:6px;text-decoration:none">📜 Ver Historial</a>';
        $body .= '    </div>';
        $body .= '  </div>';
        $body .= '</div>';
    }

    // Mini KPIs informativos
    $body .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;margin-bottom:20px">';
    $body .= '  <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px">';
    $body .= '    <div style="font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase">Traspaso Rápido Masivo</div>';
    $body .= '    <div style="font-size:18px;font-weight:800;color:#2563eb;margin-top:2px">' . count($allTransferRolls) . ' bobinas</div>';
    $body .= '    <div style="font-size:11.5px;color:#0369a1;margin-top:2px">Con selección múltiple y filtros</div>';
    $body .= '  </div>';
    $body .= '  <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px">';
    $body .= '    <div style="font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase">OTs Disponibles</div>';
    $body .= '    <div style="font-size:18px;font-weight:800;color:#0f172a;margin-top:2px">' . count($workOrders) . ' en planta</div>';
    $body .= '    <div style="font-size:11.5px;color:#166534;margin-top:2px">Listas para recibir material</div>';
    $body .= '  </div>';
    $body .= '  <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px">';
    $body .= '    <div style="font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase">Bobinas Bodega Central</div>';
    $body .= '    <div style="font-size:18px;font-weight:800;color:#0f172a;margin-top:2px">' . count($availableRolls) . ' disponibles</div>';
    $body .= '    <div style="font-size:11.5px;color:#475569;margin-top:2px">Bodegas 100, 200 y stock TRZ</div>';
    $body .= '  </div>';
    $body .= '  <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px">';
    $body .= '    <div style="font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase">Traspasos Recientes</div>';
    $body .= '    <div style="font-size:18px;font-weight:800;color:#0f172a;margin-top:2px">' . count($history) . ' registrados</div>';
    $body .= '    <div style="font-size:11.5px;color:#7c3aed;margin-top:2px">Con trazabilidad completa</div>';
    $body .= '  </div>';
    $body .= '</div>';

    // Barra de Navegación por Pestañas
    $body .= '<div style="display:flex;gap:8px;border-bottom:2px solid #e2e8f0;margin-bottom:20px;overflow-x:auto">';
    $body .= '  <button type="button" class="tab-btn" id="btn_tab_masivo" onclick="switchTransferTab(\'masivo\')" style="padding:10px 18px;font-size:14px;font-weight:700;border:none;background:none;cursor:pointer;border-bottom:3px solid transparent;color:#64748b;margin-bottom:-2px">';
    $body .= '    ⚡ Traspaso Rápido Masivo <span style="background:#dbeafe;color:#1e40af;font-size:11px;font-weight:800;padding:2px 7px;border-radius:10px;margin-left:4px">' . count($allTransferRolls) . '</span>';
    $body .= '  </button>';
    $body .= '  <button type="button" class="tab-btn" id="btn_tab_nuevo" onclick="switchTransferTab(\'nuevo\')" style="padding:10px 18px;font-size:14px;font-weight:700;border:none;background:none;cursor:pointer;border-bottom:3px solid transparent;color:#64748b;margin-bottom:-2px">';
    $body .= '    🚚 Traspaso Individual / a OT';
    $body .= '  </button>';
    $body .= '  <button type="button" class="tab-btn" id="btn_tab_solicitudes" onclick="switchTransferTab(\'solicitudes\')" style="padding:10px 18px;font-size:14px;font-weight:700;border:none;background:none;cursor:pointer;border-bottom:3px solid transparent;color:#64748b;margin-bottom:-2px">';
    $body .= '    📥 Solicitudes y Agenda Planta <span style="background:#e2e8f0;color:#334155;font-size:11px;padding:2px 7px;border-radius:10px;margin-left:4px">' . count($pendingRequests) . '</span>';
    $body .= '  </button>';
    $body .= '  <button type="button" class="tab-btn" id="btn_tab_historial" onclick="switchTransferTab(\'historial\')" style="padding:10px 18px;font-size:14px;font-weight:700;border:none;background:none;cursor:pointer;border-bottom:3px solid transparent;color:#64748b;margin-bottom:-2px">';
    $body .= '    📜 Historial & Trazabilidad <span style="background:#e2e8f0;color:#334155;font-size:11px;padding:2px 7px;border-radius:10px;margin-left:4px">' . count($history) . '</span>';
    $body .= '  </button>';
    if ($isJavierUser) {
        $body .= '  <button type="button" class="tab-btn" onclick="window.location.href=\'/stock/bajas\'" style="padding:10px 18px;font-size:14px;font-weight:700;border:none;background:none;cursor:pointer;border-bottom:3px solid transparent;color:#7c3aed;margin-bottom:-2px" title="Módulo exclusivo Javier Perozo">';
        $body .= '    🗑️ Baja de Bobinas (Exclusivo Javier)';
        $body .= '  </button>';
    }
    $body .= '</div>';

    // =========================================================================
    // TAB 0: TRASPASO RÁPIDO MASIVO (SELECCIÓN CON CHECKBOX Y FILTROS)
    // =========================================================================
    $body .= '<div id="panel_tab_masivo" class="transfer-panel">';
    $body .= '  <form method="post" action="/stock/transfers" id="form_mass_transfer" onsubmit="return validateMassTransferForm()">';
    $body .= '    <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
    $body .= '    <input type="hidden" name="action_mode" value="bulk_transfer">';
    if ($canOverrideTransferOperator) {
        $body .= '    <input type="hidden" name="transfer_operator_name" value="' . h($currentOperatorName) . '">';
    }

    // Barra de Acción Adherente (Sticky Bar)
    $body .= '    <div style="position:sticky;top:10px;z-index:40;background:#ffffff;border:2px solid #3b82f6;border-radius:12px;padding:16px 20px;margin-bottom:18px;box-shadow:0 10px 25px -5px rgba(59,130,246,0.18)">';
    $body .= '      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #e2e8f0">';
    $body .= '        <div style="display:flex;align-items:center;gap:12px">';
    $body .= '          <span style="font-size:26px">⚡</span>';
    $body .= '          <div>';
    $body .= '            <div style="font-size:16px;font-weight:800;color:#0f172a" id="lbl_mass_selection_summary">0 bobinas seleccionadas (0,00 kg)</div>';
    $body .= '            <div style="font-size:12px;color:#64748b" id="lbl_mass_subtext">Marque los checkboxes en la tabla para armar su lote de traspaso.</div>';
    $body .= '          </div>';
    $body .= '        </div>';
    $body .= '        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">';
    $body .= '          <button type="button" class="btn secondary" onclick="massSelectVisibleOnly(true)" style="padding:7px 12px;font-size:12px;font-weight:700">✓ Marcar visibles (<span id="lbl_visible_quick_count">' . count($allTransferRolls) . '</span>)</button>';
    $body .= '          <button type="button" class="btn secondary" onclick="massSelectVisibleOnly(false)" style="padding:7px 12px;font-size:12px">✕ Desmarcar visibles</button>';
    $body .= '          <button type="button" class="btn secondary" onclick="massDeselectAll()" style="padding:7px 12px;font-size:12px;color:#dc2626">Limpiar selección</button>';
    $body .= '        </div>';
    $body .= '      </div>';

    $body .= '      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px">';
    $body .= '        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">';
    $body .= '          <div style="display:flex;align-items:center;gap:8px">';
    $body .= '            <label for="sel_mass_to_warehouse" style="font-size:13px;font-weight:700;color:#0f172a;white-space:nowrap">Bodega Destino <span style="color:#ef4444">*</span>:</label>';
    $body .= '            <select name="to_warehouse_id" id="sel_mass_to_warehouse" required style="padding:9px 14px;border:1.5px solid #3b82f6;border-radius:8px;font-size:13.5px;font-weight:700;color:#0f172a;background:#f0f7ff;min-width:270px;cursor:pointer">';
    $body .= '              <option value="">-- Seleccionar Bodega Destino --</option>';
    foreach ($sortedWarehouses as $w) {
        $wId = (int)$w['id'];
        $wCode = (string)($w['code'] ?? '');
        $wName = (string)($w['name'] ?? '');
        $isPrio = in_array($wId, [58, 1, 2, 21, 9, 8], true);
        $optStyle = $wId === 58 ? ' style="font-weight:800;background:#dbeafe"' : '';
        $star = $isPrio ? '⭐ ' : '';
        $disp = $wCode !== '' ? "{$wCode} - {$wName}" : $wName;
        $body .= '              <option value="' . $wId . '"' . $optStyle . '>' . $star . h($disp) . '</option>';
    }
    $body .= '            </select>';
    $body .= '          </div>';

    $body .= '          <label style="display:inline-flex;align-items:center;gap:8px;background:#f0fdf4;padding:8px 14px;border-radius:8px;border:1.5px solid #86efac;font-size:13px;font-weight:700;color:#166534;cursor:pointer" title="Active esta casilla si solo desea registrar el cambio de ubicación en Trazabilidad TRZ sin alterar el stock contable en el ERP">';
    $body .= '            <input type="checkbox" name="traceability_only" value="1" id="chk_mass_trace_only" style="width:18px;height:18px;cursor:pointer">';
    $body .= '            <span>🏷️ Solo mover código de trazabilidad (sin mover ERP)</span>';
    $body .= '          </label>';
    $body .= '        </div>';

    $body .= '        <div>';
    $body .= '          <button type="submit" id="btn_submit_mass_transfer" class="btn" style="background:#2563eb;color:#fff;font-size:14px;font-weight:800;padding:11px 24px;border-radius:8px;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(37,99,235,0.35);transition:all 0.2s">';
    $body .= '            <span>🚀 Traspasar Bobinas Seleccionadas</span>';
    $body .= '          </button>';
    $body .= '        </div>';
    $body .= '      </div>';
    $body .= '    </div>';

    // Barra de Búsqueda y Filtros
    $body .= '    <div style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:16px 20px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">';
    $body .= '      <div style="display:grid;grid-template-columns:minmax(280px, 2fr) minmax(220px, 1.2fr) minmax(200px, 1fr) auto;gap:12px;align-items:center">';
    $body .= '        <div>';
    $body .= '          <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:4px">🔍 Filtro Instantáneo por SKU o Código de Bobina (Hija / Madre):</label>';
    $body .= '          <div style="position:relative">';
    $body .= '            <input type="text" id="mass_search_input" placeholder="Escriba SKU (ej: 0405...), Código Hijo (ROLL-MAN-..., ROLL-BOL-...), Madre (BOB-...) o nombre..." oninput="applyMassRollsFilter()" style="width:100%;padding:10px 14px 10px 36px;border:1.5px solid #94a3b8;border-radius:8px;font-size:13.5px;box-sizing:border-box">';
    $body .= '            <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:15px;color:#94a3b8">🔍</span>';
    $body .= '          </div>';
    $body .= '        </div>';

    $body .= '        <div>';
    $body .= '          <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:4px">🏢 Bodega Origen:</label>';
    $body .= '          <select id="mass_filter_wh" onchange="applyMassRollsFilter()" style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;box-sizing:border-box">';
    $body .= '            <option value="">Todas las bodegas de origen (' . count($allTransferRolls) . ')</option>';
    foreach ($originWhCounts as $ow) {
        $body .= '            <option value="' . $ow['id'] . '">' . h($ow['name']) . ' (' . $ow['count'] . ')</option>';
    }
    $body .= '          </select>';
    $body .= '        </div>';

    $body .= '        <div>';
    $body .= '          <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:4px">📋 Tipo / Etapa:</label>';
    $body .= '          <select id="mass_filter_stage" onchange="applyMassRollsFilter()" style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;box-sizing:border-box">';
    $body .= '            <option value="">Todos los tipos y etapas</option>';
    $body .= '            <option value="child">👶 Bobinas Hijas / Cortes (' . $countChildRolls . ')</option>';
    $body .= '            <option value="mother">📦 Bobinas Madres / MP (' . (count($allTransferRolls) - $countChildRolls) . ')</option>';
    $body .= '            <option value="PRINT">🖨️ Impresas (PRINT)</option>';
    $body .= '            <option value="CUT">✂️ Corte (CUT)</option>';
    $body .= '            <option value="RECEIVED">📥 En Bodega (RECEIVED)</option>';
    $body .= '          </select>';
    $body .= '        </div>';

    $body .= '        <div style="padding-top:18px">';
    $body .= '          <button type="button" class="btn secondary" onclick="resetMassFilters()" style="padding:10px 16px;font-size:13px;font-weight:600;white-space:nowrap">✕ Limpiar Filtros</button>';
    $body .= '        </div>';
    $body .= '      </div>';

    $body .= '      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-top:14px;padding-top:12px;border-top:1px solid #f1f5f9">';
    $body .= '        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">';
    $body .= '          <span style="font-size:12px;font-weight:700;color:#64748b;margin-right:4px">Filtros rápidos:</span>';
    $body .= '          <button type="button" class="filter-pill" onclick="setQuickFilter(\'all\')" style="border:1px solid #cbd5e1;background:#f8fafc;padding:3px 10px;border-radius:14px;font-size:11.5px;font-weight:600;color:#334155;cursor:pointer">✨ Todas (' . count($allTransferRolls) . ')</button>';
    $body .= '          <button type="button" class="filter-pill" onclick="setQuickFilter(\'child\')" style="border:1px solid #bfdbfe;background:#eff6ff;padding:3px 10px;border-radius:14px;font-size:11.5px;font-weight:700;color:#1e40af;cursor:pointer">👶 Solo Hijas / Cortes (' . $countChildRolls . ')</button>';
    if (isset($originWhCounts[58])) {
        $body .= '          <button type="button" class="filter-pill" onclick="setQuickFilter(\'wh\', \'58\')" style="border:1px solid #fde68a;background:#fefce8;padding:3px 10px;border-radius:14px;font-size:11.5px;font-weight:700;color:#854d0e;cursor:pointer">🏭 Bodega 3000 Insumos (' . $originWhCounts[58]['count'] . ')</button>';
    }
    if (isset($originWhCounts[1])) {
        $body .= '          <button type="button" class="filter-pill" onclick="setQuickFilter(\'wh\', \'1\')" style="border:1px solid #cbd5e1;background:#f8fafc;padding:3px 10px;border-radius:14px;font-size:11.5px;font-weight:600;color:#334155;cursor:pointer">🏢 Bodega 100 PLA (' . $originWhCounts[1]['count'] . ')</button>';
    }
    if (isset($originWhCounts[2])) {
        $body .= '          <button type="button" class="filter-pill" onclick="setQuickFilter(\'wh\', \'2\')" style="border:1px solid #cbd5e1;background:#f8fafc;padding:3px 10px;border-radius:14px;font-size:11.5px;font-weight:600;color:#334155;cursor:pointer">🏢 Bodega 200 PP (' . $originWhCounts[2]['count'] . ')</button>';
    }
    $body .= '        </div>';

    $body .= '        <div style="font-size:13px;color:#475569">';
    $body .= '          Mostrando <strong id="lbl_visible_count" style="color:#0f172a">' . count($allTransferRolls) . '</strong> de ' . count($allTransferRolls) . ' bobinas (<strong id="lbl_visible_kg" style="color:#0f172a">' . number_format($totalKgAvailable, 2, ',', '.') . '</strong> kg disponibles)';
    $body .= '        </div>';
    $body .= '      </div>';
    $body .= '    </div>';

    // Tabla de Bobinas con Selección de Checkbox
    $body .= '    <div style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05);margin-bottom:20px">';
    $body .= '      <div style="overflow-x:auto;max-height:700px;overflow-y:auto">';
    $body .= '        <table style="width:100%;border-collapse:collapse;text-align:left;font-size:13px" id="table_mass_rolls">';
    $body .= '          <thead style="background:#f8fafc;border-bottom:2px solid #e2e8f0;position:sticky;top:0;z-index:20">';
    $body .= '            <tr>';
    $body .= '              <th style="padding:12px 14px;width:40px;text-align:center">';
    $body .= '                <input type="checkbox" id="chk_mass_master" onclick="toggleMassSelectAll(this)" title="Marcar/Desmarcar todas las bobinas visibles" style="width:18px;height:18px;cursor:pointer">';
    $body .= '              </th>';
    $body .= '              <th style="padding:12px 14px;font-weight:700;color:#334155">Código Bobina / Trazabilidad</th>';
    $body .= '              <th style="padding:12px 14px;font-weight:700;color:#334155">Tipo / Genealogía</th>';
    $body .= '              <th style="padding:12px 14px;font-weight:700;color:#334155">SKU / Material</th>';
    $body .= '              <th style="padding:12px 14px;font-weight:700;color:#334155">Bodega Actual</th>';
    $body .= '              <th style="padding:12px 14px;font-weight:700;color:#334155;text-align:right">Peso (Kg)</th>';
    $body .= '              <th style="padding:12px 14px;font-weight:700;color:#334155;text-align:right">Metros / Ancho</th>';
    $body .= '              <th style="padding:12px 14px;font-weight:700;color:#334155;text-align:center">Etapa</th>';
    $body .= '              <th style="padding:12px 14px;font-weight:700;color:#334155;text-align:center">Acciones</th>';
    $body .= '            </tr>';
    $body .= '          </thead>';
    $body .= '          <tbody>';

    foreach ($allTransferRolls as $r) {
        $rId = (int)$r['id'];
        $rCode = (string)$r['roll_code'];
        $rSku = (string)$r['sku_code'];
        $rDesc = (string)($r['sku_description'] ?: $rSku);
        $rKg = (float)$r['weight_kg'];
        $rMeters = (float)($r['meters'] ?? 0);
        $rWidth = (float)($r['width_mm'] ?? 0);
        $rWhName = (string)$r['wh_name'];
        $rWhCode = (string)$r['wh_code'];
        $rWhId = (int)$r['warehouse_id'];
        $rStage = (string)($r['process_stage'] ?? '');
        $rParentId = (int)($r['parent_roll_id'] ?? 0);
        $rParentCode = (string)($r['parent_roll_code'] ?? '');
        $isChild = $rParentId > 0 || stripos($rCode, 'ROLL-') === 0 || $rStage === 'CUT';
        $isSplice = stripos($rCode, 'EMP-') === 0;

        $searchBlob = strtolower(implode(' ', [
            $rCode,
            $rSku,
            $rDesc,
            $rParentCode,
            $rWhName,
            $rWhCode,
            $rStage,
            $isChild ? 'hija corte child' : 'madre mother mp',
        ]));

        $body .= '            <tr class="mass-roll-row" id="mass_row_' . $rId . '" data-roll-id="' . $rId . '" data-wh-id="' . $rWhId . '" data-stage="' . h($rStage) . '" data-is-child="' . ($isChild ? '1' : '0') . '" data-is-splice="' . ($isSplice ? '1' : '0') . '" data-kg="' . $rKg . '" data-search="' . h($searchBlob) . '" onclick="onMassRowClick(event, this)" style="border-bottom:1px solid #f1f5f9;cursor:pointer;transition:background-color 0.15s">';
        $body .= '              <td style="padding:10px 14px;text-align:center" onclick="event.stopPropagation()">';
        $body .= '                <input type="checkbox" name="selected_rolls[]" value="' . $rId . '" class="mass-roll-chk" data-kg="' . $rKg . '" data-code="' . h($rCode) . '" onchange="onMassRowCheckChange(this)" style="width:18px;height:18px;cursor:pointer">';
        $body .= '              </td>';
        $body .= '              <td style="padding:10px 14px">';
        $body .= '                <div style="display:flex;align-items:center;gap:8px">';
        $body .= '                  <span style="font-family:monospace;font-size:13.5px;font-weight:700;color:#0f172a">' . h($rCode) . '</span>';
        $body .= '                  <a href="/stock/traceability?q=' . urlencode($rCode) . '" target="_blank" onclick="event.stopPropagation()" style="text-decoration:none;font-size:13px" title="Ver Árbol de Trazabilidad 360°">🌳</a>';
        $body .= '                </div>';
        $body .= '              </td>';
        $body .= '              <td style="padding:10px 14px">';
        if ($isChild) {
            $body .= '                <span style="display:inline-flex;align-items:center;gap:4px;background:#eff6ff;color:#1e40af;font-size:11.5px;font-weight:700;padding:2px 8px;border-radius:12px;border:1px solid #bfdbfe">👶 Hija Corte ' . ($rParentCode !== '' ? '(Madre: ' . h($rParentCode) . ')' : '') . '</span>';
        } elseif ($isSplice) {
            $body .= '                <span style="display:inline-flex;align-items:center;gap:4px;background:#fef3c7;color:#92400e;font-size:11.5px;font-weight:700;padding:2px 8px;border-radius:12px;border:1px solid #fde68a">🔗 Empalme</span>';
        } else {
            $body .= '                <span style="display:inline-flex;align-items:center;gap:4px;background:#f8fafc;color:#475569;font-size:11.5px;font-weight:600;padding:2px 8px;border-radius:12px;border:1px solid #e2e8f0">📦 Madre / MP</span>';
        }
        $body .= '              </td>';
        $body .= '              <td style="padding:10px 14px">';
        $body .= '                <div style="font-weight:700;color:#0f172a">' . h($rSku) . '</div>';
        $body .= '                <div style="font-size:11.5px;color:#64748b;max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="' . h($rDesc) . '">' . h($rDesc) . '</div>';
        $body .= '              </td>';
        $body .= '              <td style="padding:10px 14px">';
        $whStyle = $rWhId === 58 ? 'background:#fefce8;color:#854d0e;border:1px solid #fef08a' : 'background:#f1f5f9;color:#334155;border:1px solid #e2e8f0';
        $body .= '                <span style="' . $whStyle . ';padding:3px 8px;border-radius:6px;font-size:12px;font-weight:700">' . h($rWhCode !== '' ? "{$rWhCode} - {$rWhName}" : $rWhName) . '</span>';
        $body .= '              </td>';
        $body .= '              <td style="padding:10px 14px;text-align:right;font-weight:800;color:#0f172a">' . number_format($rKg, 2, ',', '.') . ' kg</td>';
        $body .= '              <td style="padding:10px 14px;text-align:right;font-size:12px;color:#64748b">';
        $body .= ($rMeters > 0 ? number_format($rMeters, 0, ',', '.') . ' m' : '-');
        if ($rWidth > 0) {
            $body .= ' · ' . number_format($rWidth, 0, ',', '.') . ' mm';
        }
        $body .= '              </td>';
        $body .= '              <td style="padding:10px 14px;text-align:center">';
        if ($rStage === 'CUT') {
            $body .= '                <span style="background:#e0f2fe;color:#0369a1;padding:2px 7px;border-radius:4px;font-size:11px;font-weight:800">CORTE</span>';
        } elseif ($rStage === 'PRINT') {
            $body .= '                <span style="background:#f3e8ff;color:#7e22ce;padding:2px 7px;border-radius:4px;font-size:11px;font-weight:800">IMPRESIÓN</span>';
        } else {
            $body .= '                <span style="background:#f1f5f9;color:#64748b;padding:2px 7px;border-radius:4px;font-size:11px;font-weight:600">' . h($rStage ?: 'STOCK') . '</span>';
        }
        $body .= '              </td>';
        $body .= '              <td style="padding:10px 14px;text-align:center;white-space:nowrap" onclick="event.stopPropagation()">';
        $body .= '                <a href="/rolls/' . $rId . '/label?server_print=1" target="_blank" class="btn" style="background:#15803d;color:#fff;font-size:11px;padding:3px 7px;border-radius:4px;text-decoration:none;margin-right:2px" title="Imprimir Zebra (100×70)">🖨️</a>';
        $body .= '                <a href="/rolls/' . $rId . '/label" target="_blank" class="btn secondary" style="font-size:11px;padding:3px 7px;border-radius:4px;text-decoration:none;margin-right:2px" title="Vista Previa Etiqueta">👁️</a>';
        $body .= '                <a href="/stock/traceability?q=' . urlencode($rCode) . '" target="_blank" class="btn secondary" style="font-size:11px;padding:3px 7px;border-radius:4px;text-decoration:none" title="Ver Árbol 360°">🌳</a>';
        $body .= '              </td>';
        $body .= '            </tr>';
    }

    $body .= '            <tr id="mass_no_results_row" style="display:none">';
    $body .= '              <td colspan="9" style="text-align:center;padding:36px;color:#64748b;font-size:14px">🔍 No se encontraron bobinas con los filtros ingresados. Intente con otro SKU o código hijo.</td>';
    $body .= '            </tr>';
    $body .= '          </tbody>';
    $body .= '        </table>';
    $body .= '      </div>';
    $body .= '    </div>';
    $body .= '  </form>';
    $body .= '</div>';

    // =========================================================================
    // TAB 1: FORMULARIO DE NUEVO TRASPASO
    // =========================================================================
    $body .= '<div id="panel_tab_nuevo" class="transfer-panel">';
    $body .= '  <form method="post" action="/stock/transfers" id="form_transfer_prod" onsubmit="return validateTransferForm()">';
    $body .= '    <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
    $body .= '    <input type="hidden" name="action_mode" id="hid_action_mode" value="to_production">';
    $body .= '    <input type="hidden" name="roll_id" id="hid_roll_id" value="">';
    $body .= '    <input type="hidden" name="code" id="hid_roll_code" value="">';
    $body .= '    <input type="hidden" name="sku" id="hid_sku" value="">';
    $body .= '    <input type="hidden" name="sku_desc" id="hid_sku_desc" value="">';
    $body .= '    <input type="hidden" name="from_warehouse_id" id="hid_from_wh" value="1">';

    $body .= '    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:20px;align-items:start">';

    // Panel Izquierdo: Selección de Bobina / Material
    $body .= '      <div style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">';
    $body .= '        <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid #f1f5f9">';
    $body .= '          <span style="font-size:18px">📦</span>';
    $body .= '          <h2 style="margin:0;font-size:16px;font-weight:800;color:#0f172a">1. Bobina o Material en Bodega Central</h2>';
    $body .= '        </div>';

    // Selector de Modo de Origen
    $body .= '        <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap">';
    $body .= '          <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#334155;cursor:pointer">';
    $body .= '            <input type="radio" name="origin_mode" value="list" checked onchange="toggleOriginMode(\'list\')"> Bobina en Stock Bodega';
    $body .= '          </label>';
    $body .= '          <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#334155;cursor:pointer">';
    $body .= '            <input type="radio" name="origin_mode" value="scan" onchange="toggleOriginMode(\'scan\')"> Pistolear / Escanear Código';
    $body .= '          </label>';
    $body .= '          <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#334155;cursor:pointer">';
    $body .= '            <input type="radio" name="origin_mode" value="manual" onchange="toggleOriginMode(\'manual\')"> Bobina Nueva / Sin Código';
    $body .= '          </label>';
    $body .= '        </div>';

    // Modo 1: Lista desplegable de Bobinas en Bodega
    $body .= '        <div id="origin_block_list" style="margin-bottom:14px">';
    $body .= '          <label style="display:block;font-size:12.5px;font-weight:700;color:#475569;margin-bottom:6px">Seleccionar Bobina de Bodega Central <span style="color:#ef4444">*</span></label>';
    $body .= '          <select id="sel_available_roll" onchange="onAvailableRollSelected(this)" style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px">';
    $body .= '            <option value="">-- Seleccionar bobina disponible --</option>';
    foreach ($availableRolls as $ar) {
        $rId = (int)$ar['id'];
        $rCode = (string)$ar['roll_code'];
        $rSku = (string)$ar['sku_code'];
        $rDesc = (string)($ar['sku_description'] ?: $rSku);
        $rKg = (float)$ar['weight_kg'];
        $rWhName = (string)$ar['wh_name'];
        $rWhId = (int)$ar['warehouse_id'];
        $body .= '            <option value="' . $rId . '" data-id="' . $rId . '" data-code="' . h($rCode) . '" data-sku="' . h($rSku) . '" data-desc="' . h($rDesc) . '" data-kg="' . $rKg . '" data-whid="' . $rWhId . '" data-whname="' . h($rWhName) . '">';
        $body .= h($rCode) . ' | ' . h($rSku) . ' | ' . number_format($rKg, 1, ',', '.') . ' kg (' . h($rWhName) . ')';
        $body .= '</option>';
    }
    $body .= '          </select>';
    $body .= '          <div style="font-size:11.5px;color:#64748b;margin-top:4px">Muestra bobinas activas registradas en Bodegas 100, 200 y stock TRZ.</div>';
    $body .= '        </div>';

    // Modo 2: Pistolear Código
    $body .= '        <div id="origin_block_scan" style="display:none;margin-bottom:14px">';
    $body .= '          <label style="display:block;font-size:12.5px;font-weight:700;color:#475569;margin-bottom:6px">Escanear / Pistolear Código de Barra o QR</label>';
    $body .= '          <div style="display:flex;gap:8px">';
    $body .= '            <input type="text" id="inp_scan_code" placeholder="Ej: RB-20261007-81B877..." style="flex:1;padding:9px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;font-family:monospace">';
    $body .= '            <button type="button" class="btn secondary" onclick="lookupScannedRoll()" style="padding:9px 14px;font-size:13px">Buscar</button>';
    $body .= '          </div>';
    $body .= '          <div style="font-size:11.5px;color:#64748b;margin-top:4px">Presione Enter o Buscar tras pistolear la etiqueta física.</div>';
    $body .= '        </div>';

    // Modo 3: Material nuevo sin código previo (Generar código nuevo)
    $body .= '        <div id="origin_block_manual" style="display:none;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:14px">';
    $body .= '          <div style="font-size:12px;font-weight:700;color:#0369a1;margin-bottom:8px">ℹ️ Al salir de bodega se generará un código de trazabilidad único nuevo (BOB-...).</div>';
    $body .= '          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:8px">';
    $body .= '            <div>';
    $body .= '              <label style="display:block;font-size:11.5px;font-weight:700;color:#475569;margin-bottom:3px">SKU / Código Material</label>';
    $body .= '              <input type="text" name="sku_manual" id="inp_sku_manual" placeholder="Ej: TEL0044" style="width:100%;padding:7px 10px;border:1px solid #cbd5e1;border-radius:5px;font-size:13px">';
    $body .= '            </div>';
    $body .= '            <div>';
    $body .= '              <label style="display:block;font-size:11.5px;font-weight:700;color:#475569;margin-bottom:3px">Color</label>';
    $body .= '              <input type="text" name="color" id="inp_color_manual" placeholder="Ej: BLANCO" style="width:100%;padding:7px 10px;border:1px solid #cbd5e1;border-radius:5px;font-size:13px">';
    $body .= '            </div>';
    $body .= '          </div>';
    $body .= '          <div style="margin-bottom:8px">';
    $body .= '            <label style="display:block;font-size:11.5px;font-weight:700;color:#475569;margin-bottom:3px">Descripción de Material</label>';
    $body .= '            <input type="text" name="sku_desc_manual" id="inp_sku_desc_manual" placeholder="Ej: TELA TNT BLANCO 80GR..." style="width:100%;padding:7px 10px;border:1px solid #cbd5e1;border-radius:5px;font-size:13px">';
    $body .= '          </div>';
    $body .= '          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">';
    $body .= '            <div>';
    $body .= '              <label style="display:block;font-size:11.5px;font-weight:700;color:#475569;margin-bottom:3px">Ancho (mm / cm)</label>';
    $body .= '              <input type="number" step="any" name="width_mm" id="inp_width_manual" placeholder="Ej: 1080" style="width:100%;padding:7px 10px;border:1px solid #cbd5e1;border-radius:5px;font-size:13px" oninput="calcManualRollDims(\'width\')">';
    $body .= '            </div>';
    $body .= '            <div>';
    $body .= '              <label style="display:block;font-size:11.5px;font-weight:700;color:#475569;margin-bottom:3px">Metros (m)</label>';
    $body .= '              <input type="number" step="any" name="meters" id="inp_meters_manual" placeholder="Ej: 1400" style="width:100%;padding:7px 10px;border:1px solid #cbd5e1;border-radius:5px;font-size:13px" oninput="calcManualRollDims(\'meters\')">';
    $body .= '            </div>';
    $body .= '            <div>';
    $body .= '              <label style="display:block;font-size:11.5px;font-weight:700;color:#0f766e;margin-bottom:3px">Peso (Kg) opcional</label>';
    $body .= '              <input type="number" step="0.01" name="manual_weight_kg" id="inp_weight_manual" placeholder="Ej: 85.0" style="width:100%;padding:7px 10px;border:1.5px solid #0f766e;border-radius:5px;font-size:13px;font-weight:700" oninput="calcManualRollDims(\'weight\')">';
    $body .= '            </div>';
    $body .= '          </div>';
    $body .= '        </div>';
    $body .= '';
    $body .= '        <!-- Ficha resumen de la bobina seleccionada y Peso a Despachar -->';
    $body .= '        <div id="card_selected_roll_preview" style="background:#f8fafc;border:1.5px solid #cbd5e1;border-radius:10px;padding:14px;margin-top:12px">';
    $body .= '          <div style="font-size:11.5px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:6px">Ficha de Bobina a Traspasar</div>';
    $body .= '          <div id="preview_roll_code" style="font-size:15px;font-weight:800;color:#0f172a;font-family:monospace">Sin bobina seleccionada</div>';
    $body .= '          <div id="preview_roll_desc" style="font-size:12.5px;color:#475569;margin-top:2px">Seleccione una bobina del stock o pistolee su etiqueta.</div>';
    $body .= '          <div style="display:flex;gap:14px;margin-top:8px;font-size:12px;color:#334155">';
    $body .= '            <div><strong>Bodega Origen:</strong> <span id="preview_roll_wh">-</span></div>';
    $body .= '            <div><strong>Peso Registrado:</strong> <span id="preview_roll_kg">-</span> kg</div>';
    $body .= '          </div>';
    $body .= '';
    $body .= '          <div style="margin-top:12px;padding-top:10px;border-top:1px dashed #cbd5e1">';
    $body .= '            <label style="display:block;font-size:12px;font-weight:700;color:#0f172a;margin-bottom:4px">';
    $body .= '              ⚖️ Peso Real de Balanza a Traspasar (Kg) <span style="color:#64748b;font-weight:600">(opcional)</span>';
    $body .= '            </label>';
    $body .= '            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
    $body .= '              <input type="number" step="0.01" name="weight_kg" id="inp_weight_kg" placeholder="0.00" style="width:140px;padding:8px 12px;border:1.5px solid #0284c7;border-radius:6px;font-size:16px;font-weight:800;color:#0f172a;background:#fff">';
    $body .= '              <span style="font-size:13px;font-weight:700;color:#475569">Kg</span>';
    $body .= '              <button type="button" class="btn secondary" id="btn_scale_transfer" onclick="captureTransferScaleWeight()" style="padding:7px 12px;font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:6px">';
    $body .= '                <span>⚖️ Balanza en Vivo</span>';
    $body .= '              </button>';
    $body .= '            </div>';
    $body .= '            <div id="transfer_scale_msg" style="font-size:11.5px;color:#64748b;margin-top:4px">';
    $body .= '              Si la bobina antigua no tiene peso registrado, puede continuar igual y pesarlo después.';
    $body .= '            </div>';
    $body .= '          </div>';
    $body .= '        </div>';
    $body .= '      </div>';

    // Panel Derecho: Destino en Planta de Producción & Trazabilidad
    $body .= '      <div style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">';
    $body .= '        <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid #f1f5f9">';
    $body .= '          <span style="font-size:18px">🏭</span>';
    $body .= '          <h2 style="margin:0;font-size:16px;font-weight:800;color:#0f172a">2. Destino del Traspaso & Trazabilidad</h2>';
    $body .= '        </div>';
    $body .= '        <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap">';
    $body .= '          <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:#334155;cursor:pointer">';
    $body .= '            <input type="radio" name="destination_mode" value="production" checked onchange="toggleDestinationMode(\'production\')"> A Producción (Bodega 3000)
    ';
    $body .= '          </label>';
    $body .= '          <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:#334155;cursor:pointer">';
    $body .= '            <input type="radio" name="destination_mode" value="warehouse" onchange="toggleDestinationMode(\'warehouse\')"> Entre Bodegas
    ';
    $body .= '          </label>';
    $body .= '        </div>';
    $body .= '        <div id="destination_block_production">';

    // OT Destino
    $body .= '        <div style="margin-bottom:12px">';
    $body .= '          <label style="display:block;font-size:12.5px;font-weight:700;color:#475569;margin-bottom:4px">Orden de Trabajo (OT) en Producción <span style="color:#ef4444">*</span></label>';
    $body .= '          <select name="ot_id" id="sel_work_order" onchange="onWorkOrderChanged(this)" style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px" required>';
    $body .= '            <option value="">-- Seleccionar OT en planta --</option>';
    foreach ($workOrders as $wo) {
        $woId = (int)$wo['id'];
        $woCode = (string)$wo['ot_code'];
        $woSku = (string)$wo['sku_final'];
        $woStatus = (string)$wo['status'];
        $isSelected = ($prefillOt !== '' && ($woCode === $prefillOt || (string)$woId === $prefillOt)) ? ' selected' : '';
        $body .= '            <option value="' . $woId . '" data-ot="' . h($woCode) . '"' . $isSelected . '>';
        $body .= 'OT #' . h($woCode) . ' - ' . h($woSku) . ' (' . ($woStatus === 'ACTIVE' ? 'ACTIVA' : 'PROGRAMADA') . ')';
        $body .= '</option>';
    }
    $body .= '          </select>';
    $body .= '          <input type="hidden" name="ot_number" id="hid_ot_number" value="' . h($prefillOt) . '">';
    $body .= '        </div>';

    // Máquina Destino
    $body .= '        <div style="margin-bottom:12px">';
    $body .= '          <label style="display:block;font-size:12.5px;font-weight:700;color:#475569;margin-bottom:4px">Máquina Destino en Planta <span style="color:#ef4444">*</span></label>';
    $body .= '          <select name="machine_id" id="sel_machine" onchange="onMachineChanged(this)" style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px" required>';
    $body .= '            <option value="">-- Seleccionar máquina receptora --</option>';
    foreach ($machines as $m) {
        $mId = (int)$m['id'];
        $mName = (string)$m['equipo_name'];
        $isSelected = ($prefillMachine !== '' && ($mName === $prefillMachine || (string)$mId === $prefillMachine)) ? ' selected' : '';
        $body .= '            <option value="' . $mId . '" data-name="' . h($mName) . '"' . $isSelected . '>' . h($mName) . '</option>';
    }
    $body .= '          </select>';
    $body .= '          <input type="hidden" name="machine_name" id="hid_machine_name" value="' . h($prefillMachine) . '">';
    $body .= '        </div>';

    // Quién la solicitó & Quién la sacó (Crucial para requerimiento de trazabilidad)
    $body .= '        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">';
    $body .= '          <div>';
    $body .= '            <label style="display:block;font-size:12.5px;font-weight:700;color:#475569;margin-bottom:4px">Quién la solicitó <span style="color:#ef4444">*</span></label>';
    $body .= '            <input type="text" name="quien_la_solicito" id="inp_quien_solicito" value="' . h($prefillSolicitante) . '" placeholder="Nombre del maquinista/operador" style="width:100%;padding:8px 11px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px" required>';
    $body .= '            <div style="font-size:11px;color:#64748b;margin-top:2px">Operador o maquinista que pide el material</div>';
    $body .= '          </div>';
    $body .= '          <div>';
    $body .= '            <label style="display:block;font-size:12.5px;font-weight:700;color:#475569;margin-bottom:4px">Quién la sacó (Bodeguero) <span style="color:#ef4444">*</span></label>';
    $body .= '            <input type="text" name="quien_la_saco" id="inp_quien_saco" value="' . h($currentOperatorName) . '" placeholder="Operador de bodega" style="' . $transferOperatorFieldStyle . '"' . $transferOperatorFieldAttrs . ' required>';
    $body .= '            <div style="font-size:11px;color:#64748b;margin-top:2px">' . h($transferOperatorHelp) . '</div>';
    $body .= '          </div>';
    $body .= '        </div>';

    // Kilos a despachar
    $body .= '        <div style="margin-bottom:12px">';
    $body .= '          <label style="display:block;font-size:12.5px;font-weight:700;color:#475569;margin-bottom:4px">Peso a Traspasar (Kg) <span style="color:#64748b;font-weight:600">(opcional)</span></label>';
    $body .= '          <div style="display:flex;align-items:center;gap:6px">';
    $body .= '            <input type="number" step="0.001" name="weight_kg" id="inp_weight_kg" value="' . h($prefillAmount) . '" placeholder="0.00" style="flex:1;padding:8px 11px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px;font-weight:700">';
    $body .= '            <span style="font-size:13px;font-weight:700;color:#64748b">Kg</span>';
    $body .= '          </div>';
    $body .= '          <div style="font-size:11px;color:#64748b;margin-top:2px">Para bobinas antiguas etiquetadas sin peso, puede dejarlo vacío.</div>';
    $body .= '        </div>';

    // Observaciones / Comentarios
    $body .= '        <div style="margin-bottom:18px">';
    $body .= '          <label style="display:block;font-size:12.5px;font-weight:700;color:#475569;margin-bottom:4px">Observaciones / Motivo</label>';
    $body .= '          <textarea name="comments" rows="2" placeholder="Notas adicionales de entrega a planta..." style="width:100%;padding:8px 11px;border:1px solid #cbd5e1;border-radius:6px;font-size:12.5px"></textarea>';
    $body .= '        </div>';
    $body .= '        </div>';
    $body .= '        <div id="destination_block_warehouse" style="display:none">';
    $body .= '          <div style="margin-bottom:12px">';
    $body .= '            <label style="display:block;font-size:12.5px;font-weight:700;color:#475569;margin-bottom:4px">Bodega Destino <span style="color:#ef4444">*</span></label>';
    $body .= '            <select name="to_warehouse_id" id="sel_destination_warehouse" style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px">';
    $body .= '              <option value="">-- Seleccionar bodega destino --</option>';
    foreach ($warehouses as $warehouse) {
        $whId = (int)($warehouse['id'] ?? 0);
        $whCode = trim((string)($warehouse['code'] ?? ''));
        $whName = trim((string)($warehouse['name'] ?? ''));
        if ($whId <= 0) {
            continue;
        }
        $body .= '              <option value="' . $whId . '">' . h(($whCode !== '' ? $whCode . ' - ' : '') . $whName) . '</option>';
    }
    $body .= '            </select>';
    $body .= '            <div style="font-size:11px;color:#64748b;margin-top:2px">Use este modo cuando aún no existe máquina ni OT de destino.</div>';
    $body .= '          </div>';
    $body .= '          <div style="margin-bottom:12px">';
    $body .= '            <label style="display:block;font-size:12.5px;font-weight:700;color:#475569;margin-bottom:4px">Quién mueve la bobina <span style="color:#ef4444">*</span></label>';
    $body .= '            <input type="text" name="transfer_operator_name" id="inp_transfer_operator" value="' . h($currentOperatorName) . '" style="' . $transferOperatorFieldStyle . '"' . $transferOperatorFieldAttrs . ' required>';
    $body .= '            <div style="font-size:11px;color:#64748b;margin-top:2px">' . h($transferOperatorHelp) . '</div>';
    $body .= '          </div>';
    $body .= '          <label style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;background:#f8fafc;border:1px solid #dbeafe;border-radius:8px;margin-bottom:10px;cursor:pointer">';
    $body .= '            <input type="checkbox" name="traceability_only" id="chk_traceability_only" value="1" style="margin-top:2px">';
    $body .= '            <span style="font-size:12.5px;color:#334155;line-height:1.45">';
    $body .= '              <strong>Solo mover trazabilidad</strong><br>';
    $body .= '              Use esta opción cuando el traslado físico ya se hizo en el sistema antiguo y aquí solo necesita mover el código trazable a la bodega destino, sin tocar inventario.';
    $body .= '            </span>';
    $body .= '          </label>';
    $body .= '        </div>';

    // Botón de Confirmación Destacado
    $body .= '        <button type="submit" class="btn" style="width:100%;background:#15803d;color:#fff;font-size:15px;font-weight:800;padding:12px 20px;border-radius:8px;display:flex;justify-content:center;align-items:center;gap:8px;box-shadow:0 4px 10px rgba(21,128,61,0.25)">';
    $body .= '          <span id="transfer_submit_label">🚀 Confirmar Traspaso y Despachar a Bodega 3000</span>';
    $body .= '        </button>';
    $body .= '        <div id="transfer_submit_help" style="text-align:center;font-size:11.5px;color:#64748b;margin-top:8px">';
    $body .= '          Se actualizará automáticamente la bodega del rollo a Bodega 3000 y se registrará el evento de trazabilidad auditado.';
    $body .= '        </div>';
    $body .= '      </div>';

    $body .= '    </div>';
    $body .= '  </form>';
    $body .= '</div>';

    // =========================================================================
    // TAB 2: SOLICITUDES Y AGENDA DE PLANTA
    // =========================================================================
    $body .= '<div id="panel_tab_solicitudes" class="transfer-panel" style="display:none">';
    if (function_exists('renderSupplyRequestHistory')) {
        $supply = new MaterialSupplyService(Db::erpPdo(), Db::trzPdo());
        ob_start();
        renderSupplyRequestHistory($supply, $supply->requests(), 0, true);
        $body .= ob_get_clean();
    }
    $body .= '  <div style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">';
    $body .= '    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">';
    $body .= '      <div>';
    $body .= '        <h2 style="margin:0;font-size:17px;font-weight:800;color:#0f172a">📥 Agenda y Registros Anteriores</h2>';
    $body .= '        <p style="margin:4px 0 0;font-size:13px;color:#64748b">Haga clic en <strong>⚡ Despachar Material</strong> para auto-completar el formulario de traspaso con los datos de la máquina y la OT.</p>';
    $body .= '      </div>';
    $body .= '      <span style="background:#f1f5f9;color:#475569;font-size:12px;font-weight:700;padding:4px 10px;border-radius:6px">' . count($pendingRequests) . ' solicitudes</span>';
    $body .= '    </div>';

    if (empty($pendingRequests)) {
        $body .= '    <div style="text-align:center;padding:40px 20px;color:#94a3b8">';
        $body .= '      <div style="font-size:32px;margin-bottom:8px">📭</div>';
        $body .= '      <div style="font-size:15px;font-weight:700;color:#475569">No hay solicitudes de materiales pendientes en este momento.</div>';
        $body .= '      <div style="font-size:13px;margin-top:4px">Puede registrar un traspaso manual en la pestaña <strong>Nuevo Traspaso</strong>.</div>';
        $body .= '    </div>';
    } else {
        $body .= '    <div style="overflow-x:auto">';
        $body .= '      <table class="data-table" style="width:100%;border-collapse:collapse;font-size:13px">';
        $body .= '        <thead>';
        $body .= '          <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;text-align:left">';
        $body .= '            <th style="padding:10px 12px">OT / Req #</th>';
        $body .= '            <th style="padding:10px 12px">Cliente & Producto</th>';
        $body .= '            <th style="padding:10px 12px">Máquina</th>';
        $body .= '            <th style="padding:10px 12px">Cantidad / Meta</th>';
        $body .= '            <th style="padding:10px 12px">Comentarios / Estado</th>';
        $body .= '            <th style="padding:10px 12px;text-align:center">Acción</th>';
        $body .= '          </tr>';
        $body .= '        </thead>';
        $body .= '        <tbody>';
        foreach ($pendingRequests as $pr) {
            $otNum = (string)($pr['prd_number'] ?? '');
            $reqNum = (string)($pr['req_number'] ?? '');
            $custName = (string)($pr['cust_name'] ?? '');
            $itemTitle = (string)($pr['item_title'] ?? '');
            $eqName = (string)($pr['equipo_name'] ?? '');
            $eqId = (int)($pr['equipo_id'] ?? $pr['ag_equipoid'] ?? 0);
            $amount = (float)($pr['ag_amount'] ?? $pr['evt_amount'] ?? 0);
            $comments = (string)($pr['evt_comments'] ?? '');

            $body .= '          <tr style="border-bottom:1px solid #f1f5f9">';
            $body .= '            <td style="padding:10px 12px;font-weight:700;color:#0f172a">';
            $body .= '              <div>#' . h($otNum) . '</div>';
            if ($reqNum !== '') {
                $body .= '              <div style="font-size:11px;color:#64748b;font-weight:normal">Req #' . h($reqNum) . '</div>';
            }
            $body .= '            </td>';
            $body .= '            <td style="padding:10px 12px">';
            $body .= '              <div style="font-weight:600;color:#1e293b">' . h($custName) . '</div>';
            $body .= '              <div style="font-size:12px;color:#64748b">' . h($itemTitle) . '</div>';
            $body .= '            </td>';
            $body .= '            <td style="padding:10px 12px">';
            $body .= '              <span style="display:inline-block;background:#f0fdf4;color:#166534;font-weight:700;font-size:11.5px;padding:3px 8px;border-radius:4px">' . h($eqName) . '</span>';
            $body .= '            </td>';
            $body .= '            <td style="padding:10px 12px;font-weight:600">' . number_format($amount, 0, ',', '.') . ' unid</td>';
            $body .= '            <td style="padding:10px 12px;font-size:12px;color:#475569">' . h($comments) . '</td>';
            $body .= '            <td style="padding:10px 12px;text-align:center">';
            $body .= '              <button type="button" class="btn" onclick="prefillTransferFromRequest(\'' . h($otNum) . '\', \'' . h($eqName) . '\', ' . $eqId . ', \'\')" style="background:#0369a1;color:#fff;font-size:12px;font-weight:700;padding:6px 12px;border-radius:5px">';
            $body .= '                ⚡ Despachar Material';
            $body .= '              </button>';
            $body .= '            </td>';
            $body .= '          </tr>';
        }
        $body .= '        </tbody>';
        $body .= '      </table>';
        $body .= '    </div>';
    }
    $body .= '  </div>';
    $body .= '</div>';

    // =========================================================================
    // TAB 3: HISTORIAL & TRAZABILIDAD DE TRASPASOS
    // =========================================================================
    $body .= '<div id="panel_tab_historial" class="transfer-panel" style="display:none">';
    $body .= '  <div style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">';
    $body .= '    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">';
    $body .= '      <div>';
    $body .= '        <h2 style="margin:0;font-size:17px;font-weight:800;color:#0f172a">📜 Historial de Traspasos a Bodega 3000 (Planta)</h2>';
    $body .= '        <p style="margin:4px 0 0;font-size:13px;color:#64748b">Registro auditado de bobinas traspasadas: quién solicitó, quién sacó, máquina receptora y etiquetas Zebra 100×70.</p>';
    $body .= '      </div>';
    $body .= '      <span style="background:#f1f5f9;color:#475569;font-size:12px;font-weight:700;padding:4px 10px;border-radius:6px">' . count($history) . ' registros</span>';
    $body .= '    </div>';

    if (empty($history)) {
        $body .= '    <div style="text-align:center;padding:40px 20px;color:#94a3b8">';
        $body .= '      <div style="font-size:32px;margin-bottom:8px">📋</div>';
        $body .= '      <div style="font-size:15px;font-weight:700;color:#475569">Aún no se registran traspasos a Bodega 3000.</div>';
        $body .= '    </div>';
    } else {
        $body .= '    <div style="overflow-x:auto">';
        $body .= '      <table class="data-table" style="width:100%;border-collapse:collapse;font-size:13px">';
        $body .= '        <thead>';
        $body .= '          <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;text-align:left">';
        $body .= '            <th style="padding:10px 12px">Fecha</th>';
        $body .= '            <th style="padding:10px 12px">Código Trazabilidad</th>';
        $body .= '            <th style="padding:10px 12px">SKU / Material</th>';
        $body .= '            <th style="padding:10px 12px">Peso</th>';
        $body .= '            <th style="padding:10px 12px">Quién la solicitó</th>';
        $body .= '            <th style="padding:10px 12px">Quién la sacó</th>';
        $body .= '            <th style="padding:10px 12px">Máquina</th>';
        $body .= '            <th style="padding:10px 12px">OT Destino</th>';
        $body .= '            <th style="padding:10px 12px;text-align:center">Acciones</th>';
        $body .= '          </tr>';
        $body .= '        </thead>';
        $body .= '        <tbody>';
        foreach ($history as $h) {
            $hId = (int)$h['id'];
            $rId = (int)$h['roll_id'];
            $rCode = (string)$h['roll_code'];
            $rSku = (string)$h['sku'];
            $rWeight = (float)$h['weight_kg'];
            $qSol = (string)$h['quien_la_solicito'];
            $qSac = (string)$h['quien_la_saco'];
            $mach = (string)$h['machine_name'];
            $otNum = (string)$h['ot_number'];
            $dt = (string)$h['created_at'];

            $body .= '          <tr style="border-bottom:1px solid #f1f5f9">';
            $body .= '            <td style="padding:10px 12px;font-size:12px;color:#64748b;white-space:nowrap">' . h($dt) . '</td>';
            $body .= '            <td style="padding:10px 12px">';
            $body .= '              <a href="/stock/traceability?q=' . urlencode($rCode) . '" style="display:inline-block;background:#0f172a;color:#fff;font-family:monospace;font-size:12px;font-weight:700;padding:2px 7px;border-radius:4px;text-decoration:none" title="Ver Árbol de Trazabilidad 360°">';
            $body .= h($rCode);
            $body .= '              </a>';
            $body .= '            </td>';
            $body .= '            <td style="padding:10px 12px;font-weight:600;color:#1e293b">' . h($rSku) . '</td>';
            $body .= '            <td style="padding:10px 12px;font-weight:700">' . number_format($rWeight, 2, ',', '.') . ' kg</td>';
            $body .= '            <td style="padding:10px 12px;color:#1e293b"><strong>' . h($qSol) . '</strong></td>';
            $body .= '            <td style="padding:10px 12px;color:#475569">' . h($qSac) . '</td>';
            $body .= '            <td style="padding:10px 12px">';
            if ($mach !== '-' && $mach !== '') {
                $body .= '              <span style="background:#e0f2fe;color:#0369a1;font-weight:700;font-size:11px;padding:2px 6px;border-radius:4px">' . h($mach) . '</span>';
            } else {
                $body .= '-';
            }
            $body .= '            </td>';
            $body .= '            <td style="padding:10px 12px;font-weight:700;color:#0f172a">' . ($otNum !== '' && $otNum !== '-' ? '#' . h($otNum) : '-') . '</td>';
            $body .= '            <td style="padding:10px 12px;text-align:center;white-space:nowrap">';
            if ($rId > 0) {
                $body .= '              <a href="/rolls/' . $rId . '/label?server_print=1" target="_blank" class="btn" style="background:#15803d;color:#fff;font-size:11px;padding:4px 8px;border-radius:4px;margin-right:4px;text-decoration:none" title="Imprimir etiqueta en Zebra">';
                $body .= '🖨️ Zebra';
                $body .= '              </a>';
                $body .= '              <a href="/rolls/' . $rId . '/label" target="_blank" class="btn secondary" style="font-size:11px;padding:4px 8px;border-radius:4px;margin-right:4px;text-decoration:none" title="Ver etiqueta 100×70">';
                $body .= '👁️ Ver';
                $body .= '              </a>';
            }
            $body .= '              <a href="/stock/traceability?q=' . urlencode($rCode) . '" class="btn secondary" style="font-size:11px;padding:4px 8px;border-radius:4px;text-decoration:none" title="Ver árbol genealógico completo">';
            $body .= '🌳 Árbol';
            $body .= '              </a>';
            $body .= '            </td>';
            $body .= '          </tr>';
        }
        $body .= '        </tbody>';
        $body .= '      </table>';
        $body .= '    </div>';
    }
    $body .= '  </div>';
    $body .= '</div>';

    // Script interactivo de UI
    $body .= '<script>
      function switchTransferTab(tab) {
        var panels = {
          "masivo": document.getElementById("panel_tab_masivo"),
          "nuevo": document.getElementById("panel_tab_nuevo"),
          "solicitudes": document.getElementById("panel_tab_solicitudes"),
          "historial": document.getElementById("panel_tab_historial")
        };
        for (var key in panels) {
          if (panels[key]) {
            panels[key].style.display = key === tab ? "block" : "none";
          }
          var btn = document.getElementById("btn_tab_" + key);
          if (btn) {
            if (key === tab) {
              btn.style.color = "#0f172a";
              btn.style.borderBottomColor = "#2563eb";
            } else {
              btn.style.color = "#64748b";
              btn.style.borderBottomColor = "transparent";
            }
          }
        }
      }

      function applyMassRollsFilter() {
        var searchInput = document.getElementById("mass_search_input");
        var query = searchInput ? (searchInput.value || "").trim().toLowerCase() : "";
        var whSelect = document.getElementById("mass_filter_wh");
        var whFilter = whSelect ? whSelect.value : "";
        var stageSelect = document.getElementById("mass_filter_stage");
        var stageFilter = stageSelect ? stageSelect.value : "";

        var tokens = query ? query.split(/\\s+/).filter(Boolean) : [];
        var rows = document.querySelectorAll(".mass-roll-row");
        var visibleCount = 0;
        var visibleKg = 0;

        for (var i = 0; i < rows.length; i++) {
          var row = rows[i];
          var searchBlob = row.getAttribute("data-search") || "";
          var rowWh = row.getAttribute("data-wh-id") || "";
          var rowStage = row.getAttribute("data-stage") || "";
          var isChild = row.getAttribute("data-is-child") === "1";
          var isSplice = row.getAttribute("data-is-splice") === "1";
          var rowKg = parseFloat(row.getAttribute("data-kg") || "0");

          var matchesText = true;
          for (var t = 0; t < tokens.length; t++) {
            if (searchBlob.indexOf(tokens[t]) === -1) {
              matchesText = false;
              break;
            }
          }

          var matchesWh = !whFilter || rowWh === whFilter;

          var matchesStage = true;
          if (stageFilter === "child") {
            matchesStage = isChild;
          } else if (stageFilter === "mother") {
            matchesStage = !isChild;
          } else if (stageFilter === "splice") {
            matchesStage = isSplice;
          } else if (stageFilter) {
            matchesStage = rowStage === stageFilter;
          }

          if (matchesText && matchesWh && matchesStage) {
            row.style.display = "";
            visibleCount++;
            visibleKg += rowKg;
          } else {
            row.style.display = "none";
          }
        }

        var noResRow = document.getElementById("mass_no_results_row");
        if (noResRow) {
          noResRow.style.display = visibleCount === 0 ? "" : "none";
        }

        var lblCount = document.getElementById("lbl_visible_count");
        if (lblCount) lblCount.textContent = visibleCount;
        var lblQuick = document.getElementById("lbl_visible_quick_count");
        if (lblQuick) lblQuick.textContent = visibleCount;
        var lblKg = document.getElementById("lbl_visible_kg");
        if (lblKg) lblKg.textContent = visibleKg.toLocaleString("es-CL", {minimumFractionDigits: 2, maximumFractionDigits: 2});

        syncMassMasterCheckbox();
      }

      function onMassRowClick(event, row) {
        if (!row) return;
        var chk = row.querySelector(".mass-roll-chk");
        if (chk) {
          chk.checked = !chk.checked;
          onMassRowCheckChange(chk);
        }
      }

      function onMassRowCheckChange(chk) {
        if (!chk) return;
        var row = chk.closest(".mass-roll-row");
        if (row) {
          row.style.backgroundColor = chk.checked ? "#eff6ff" : "";
        }
        updateMassSelectionSummary();
        syncMassMasterCheckbox();
      }

      function toggleMassSelectAll(masterChk) {
        var state = masterChk.checked;
        var rows = document.querySelectorAll(".mass-roll-row");
        for (var i = 0; i < rows.length; i++) {
          if (rows[i].style.display !== "none") {
            var chk = rows[i].querySelector(".mass-roll-chk");
            if (chk) {
              chk.checked = state;
              rows[i].style.backgroundColor = state ? "#eff6ff" : "";
            }
          }
        }
        updateMassSelectionSummary();
      }

      function massSelectVisibleOnly(state) {
        var rows = document.querySelectorAll(".mass-roll-row");
        for (var i = 0; i < rows.length; i++) {
          if (rows[i].style.display !== "none") {
            var chk = rows[i].querySelector(".mass-roll-chk");
            if (chk) {
              chk.checked = state;
              rows[i].style.backgroundColor = state ? "#eff6ff" : "";
            }
          }
        }
        updateMassSelectionSummary();
        syncMassMasterCheckbox();
      }

      function massDeselectAll() {
        var chks = document.querySelectorAll(".mass-roll-chk");
        for (var i = 0; i < chks.length; i++) {
          chks[i].checked = false;
          var row = chks[i].closest(".mass-roll-row");
          if (row) row.style.backgroundColor = "";
        }
        updateMassSelectionSummary();
        syncMassMasterCheckbox();
      }

      function syncMassMasterCheckbox() {
        var master = document.getElementById("chk_mass_master");
        if (!master) return;
        var rows = document.querySelectorAll(".mass-roll-row");
        var visibleCount = 0;
        var checkedVisibleCount = 0;
        for (var i = 0; i < rows.length; i++) {
          if (rows[i].style.display !== "none") {
            visibleCount++;
            var chk = rows[i].querySelector(".mass-roll-chk");
            if (chk && chk.checked) {
              checkedVisibleCount++;
            }
          }
        }
        if (visibleCount === 0) {
          master.checked = false;
          master.indeterminate = false;
        } else if (checkedVisibleCount === visibleCount) {
          master.checked = true;
          master.indeterminate = false;
        } else if (checkedVisibleCount > 0) {
          master.checked = false;
          master.indeterminate = true;
        } else {
          master.checked = false;
          master.indeterminate = false;
        }
      }

      function updateMassSelectionSummary() {
        var chks = document.querySelectorAll(".mass-roll-chk:checked");
        var totalCount = chks.length;
        var totalKg = 0;
        for (var i = 0; i < chks.length; i++) {
          totalKg += parseFloat(chks[i].getAttribute("data-kg") || "0");
        }

        var summaryLbl = document.getElementById("lbl_mass_selection_summary");
        var subtextLbl = document.getElementById("lbl_mass_subtext");
        var submitBtn = document.getElementById("btn_submit_mass_transfer");

        if (summaryLbl) {
          summaryLbl.textContent = totalCount + " bobina(s) seleccionada(s) (" + totalKg.toLocaleString("es-CL", {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " kg)";
        }
        if (subtextLbl) {
          subtextLbl.textContent = totalCount > 0
            ? "Listo para traspasar. Seleccione la bodega destino y confirme."
            : "Marque los checkboxes en la tabla para armar su lote de traspaso.";
        }
        if (submitBtn) {
          submitBtn.innerHTML = totalCount > 0
            ? "<span>🚀 Traspasar " + totalCount + " Bobinas (" + totalKg.toFixed(1) + " kg)</span>"
            : "<span>🚀 Traspasar Bobinas Seleccionadas</span>";
        }
      }

      function setQuickFilter(type, value) {
        var searchInput = document.getElementById("mass_search_input");
        var whSelect = document.getElementById("mass_filter_wh");
        var stageSelect = document.getElementById("mass_filter_stage");

        if (type === "all") {
          if (searchInput) searchInput.value = "";
          if (whSelect) whSelect.value = "";
          if (stageSelect) stageSelect.value = "";
        } else if (type === "child") {
          if (stageSelect) stageSelect.value = "child";
        } else if (type === "wh") {
          if (whSelect) whSelect.value = value;
        }
        applyMassRollsFilter();
      }

      function resetMassFilters() {
        var searchInput = document.getElementById("mass_search_input");
        var whSelect = document.getElementById("mass_filter_wh");
        var stageSelect = document.getElementById("mass_filter_stage");
        if (searchInput) searchInput.value = "";
        if (whSelect) whSelect.value = "";
        if (stageSelect) stageSelect.value = "";
        applyMassRollsFilter();
      }

      function validateMassTransferForm() {
        var chks = document.querySelectorAll(".mass-roll-chk:checked");
        if (chks.length === 0) {
          alert("Debe seleccionar al menos una bobina para realizar el traspaso masivo.");
          return false;
        }

        var selWh = document.getElementById("sel_mass_to_warehouse");
        if (!selWh || !selWh.value) {
          alert("Debe seleccionar la bodega destino para las bobinas seleccionadas.");
          if (selWh) selWh.focus();
          return false;
        }

        var whText = selWh.options[selWh.selectedIndex].text.trim();
        var traceChk = document.getElementById("chk_mass_trace_only");
        var traceOnly = traceChk ? traceChk.checked : false;
        var totalKg = 0;
        for (var i = 0; i < chks.length; i++) {
          totalKg += parseFloat(chks[i].getAttribute("data-kg") || "0");
        }

        var modeMsg = traceOnly
          ? "⚠️ MODO SOLO TRAZABILIDAD (sin mover inventario contable en el ERP)"
          : "📦 TRASPASO COMPLETO (Trazabilidad TRZ + Inventario ERP)";

        var confirmMsg = "¿Confirma el traspaso de " + chks.length + " bobina(s) (" + totalKg.toFixed(2) + " kg) hacia:\n\n"
          + "📍 " + whText + "\n\n"
          + modeMsg + "?";

        if (!confirm(confirmMsg)) {
          return false;
        }

        var submitBtn = document.getElementById("btn_submit_mass_transfer");
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = "<span>⏳ Procesando traspaso masivo...</span>";
        }
        return true;
      }

      function toggleOriginMode(mode) {
        document.getElementById("origin_block_list").style.display = mode === "list" ? "block" : "none";
        document.getElementById("origin_block_scan").style.display = mode === "scan" ? "block" : "none";
        document.getElementById("origin_block_manual").style.display = mode === "manual" ? "block" : "none";

        if (mode === "scan") {
          var inp = document.getElementById("inp_scan_code");
          if (inp) inp.focus();
        } else if (mode === "manual") {
          document.getElementById("hid_roll_id").value = "0";
          document.getElementById("hid_roll_code").value = "";
          updateRollPreview("[Se generará código BOB-...]", "Material sin código previo", 0, "Bodega Central");
        }
      }

      function normalizeScannedCode(value) {
        return (value || "")
          .trim()
          .replace(/[\\s\\r\\n\\t]+/g, "")
          .replace(/[\'`´’‘‛]/g, "-")
          .toUpperCase();
      }

      function toggleDestinationMode(mode) {
        var isProduction = mode === "production";
        var selWo = document.getElementById("sel_work_order");
        var selMachine = document.getElementById("sel_machine");
        var inpSolicito = document.getElementById("inp_quien_solicito");
        var inpSaco = document.getElementById("inp_quien_saco");
        var selWarehouse = document.getElementById("sel_destination_warehouse");
        var inpWarehouseOperator = document.getElementById("inp_transfer_operator");
        document.getElementById("destination_block_production").style.display = isProduction ? "block" : "none";
        document.getElementById("destination_block_warehouse").style.display = isProduction ? "none" : "block";
        document.getElementById("hid_action_mode").value = isProduction ? "to_production" : "to_warehouse";
        if (selWo) selWo.required = isProduction;
        if (selMachine) selMachine.required = isProduction;
        if (inpSolicito) inpSolicito.required = isProduction;
        if (inpSaco) inpSaco.required = isProduction;
        if (selWarehouse) selWarehouse.required = !isProduction;
        if (inpWarehouseOperator) inpWarehouseOperator.required = !isProduction;
        document.getElementById("transfer_submit_label").textContent = isProduction
          ? "🚀 Confirmar Traspaso y Despachar a Bodega 3000"
          : "🚚 Confirmar Traspaso Entre Bodegas";
        document.getElementById("transfer_submit_help").textContent = isProduction
          ? "Se actualizará automáticamente la bodega del rollo a Bodega 3000 y se registrará el evento de trazabilidad auditado."
          : "Se actualizará la bodega actual del rollo y el movimiento quedará registrado para trazabilidad e informe de stock.";
      }

      function onAvailableRollSelected(select) {
        var opt = select.options[select.selectedIndex];
        if (!opt || !opt.value) {
          document.getElementById("hid_roll_id").value = "";
          document.getElementById("hid_roll_code").value = "";
          document.getElementById("hid_sku").value = "";
          document.getElementById("hid_sku_desc").value = "";
          updateRollPreview("Sin bobina seleccionada", "Seleccione una bobina del stock o pistolee su etiqueta.", "-", "-");
          return;
        }

        var rId = opt.getAttribute("data-id") || "";
        var rCode = opt.getAttribute("data-code") || "";
        var rSku = opt.getAttribute("data-sku") || "";
        var rDesc = opt.getAttribute("data-desc") || "";
        var rKg = opt.getAttribute("data-kg") || "0";
        var rWhName = opt.getAttribute("data-whname") || "Bodega Central";
        var rWhId = opt.getAttribute("data-whid") || "1";

        document.getElementById("hid_roll_id").value = rId;
        document.getElementById("hid_roll_code").value = rCode;
        document.getElementById("hid_sku").value = rSku;
        document.getElementById("hid_sku_desc").value = rDesc;
        document.getElementById("hid_from_wh").value = rWhId;
        document.getElementById("inp_weight_kg").value = rKg;

        updateRollPreview(rCode, rSku + " - " + rDesc, rKg, rWhName);
      }

      function updateRollPreview(code, desc, kg, wh) {
        document.getElementById("preview_roll_code").textContent = code;
        document.getElementById("preview_roll_desc").textContent = desc;
        document.getElementById("preview_roll_kg").textContent = kg;
        document.getElementById("preview_roll_wh").textContent = wh;
      }

      async function captureTransferScaleWeight() {
        var btn = document.getElementById("btn_scale_transfer");
        var inp = document.getElementById("inp_weight_kg");
        var msg = document.getElementById("transfer_scale_msg");
        if (!inp || !btn) return;
        var old = btn.innerHTML;
        btn.innerHTML = "<span>⏳ Leyendo...</span>";
        btn.disabled = true;
        try {
          var res = await fetch("/api/scale/weight", { cache: "no-store" });
          var data = await res.json();
          if (data && data.ok && data.weight_kg !== undefined) {
            var w = parseFloat(data.weight_kg);
            inp.value = w.toFixed(2);
            document.getElementById("preview_roll_kg").textContent = w.toFixed(2);
            if (msg) {
              msg.innerHTML = \'<span style="color:#16a34a;font-weight:bold">✅ Peso capturado de balanza: \' + w.toFixed(2) + \' Kg</span>\';
            }
          } else {
            alert("No se pudo leer la balanza: " + (data.error || "Sin señal"));
          }
        } catch(e) {
          alert("Error de comunicación con balanza: " + e.message);
        } finally {
          btn.innerHTML = old;
          btn.disabled = false;
        }
      }

      function calcManualRollDims(changed) {
        var wInp = document.getElementById("inp_width_manual");
        var mInp = document.getElementById("inp_meters_manual");
        var kgInp = document.getElementById("inp_weight_manual");
        var mainKgInp = document.getElementById("inp_weight_kg");
        if (!wInp || !mInp || !kgInp || !mainKgInp) return;
        var widthVal = parseFloat(wInp.value || "0");
        var metersVal = parseFloat(mInp.value || "0");
        var kgVal = parseFloat(kgInp.value || "0");
        var widthM = widthVal > 100 ? (widthVal / 1000) : (widthVal / 100);
        var gsm = 80;

        if (changed === "weight" && kgVal > 0) {
          mainKgInp.value = kgVal.toFixed(2);
          document.getElementById("preview_roll_kg").textContent = kgVal.toFixed(2);
          if (widthM > 0 && metersVal <= 0) {
            var calcMts = Math.round((kgVal * 1000) / (widthM * gsm));
            mInp.value = calcMts;
          }
        } else if (changed === "meters" && metersVal > 0) {
          if (widthM > 0 && kgVal <= 0) {
            var calcKg = (metersVal * widthM * gsm) / 1000;
            kgInp.value = calcKg.toFixed(2);
            mainKgInp.value = calcKg.toFixed(2);
            document.getElementById("preview_roll_kg").textContent = calcKg.toFixed(2);
          }
        }
      }

      function lookupScannedRoll() {
        var code = normalizeScannedCode(document.getElementById("inp_scan_code").value || "");
        if (!code) {
          alert("Por favor ingrese o escanee un código de bobina.");
          return;
        }
        document.getElementById("inp_scan_code").value = code;
        var sel = document.getElementById("sel_available_roll");
        var found = false;
        for (var i = 0; i < sel.options.length; i++) {
          var opt = sel.options[i];
          if (normalizeScannedCode(opt.getAttribute("data-code") || "") === code || opt.value === code) {
            sel.selectedIndex = i;
            onAvailableRollSelected(sel);
            found = true;
            break;
          }
        }
        if (found) {
          alert("¡Bobina encontrada en el stock de bodega!");
          toggleOriginMode("list");
          document.querySelector("input[name=\'origin_mode\'][value=\'list\']").checked = true;
        } else {
          document.getElementById("hid_roll_id").value = "0";
          document.getElementById("hid_roll_code").value = code;
          updateRollPreview(code, "Bobina pistoleada (se actualizará en TRZ)", document.getElementById("inp_weight_kg").value || "-", "Bodega Central");
          alert("Código ingresado: " + code + ". Proceda a completar la OT y máquina.");
        }
      }

      document.getElementById("inp_scan_code") && document.getElementById("inp_scan_code").addEventListener("keydown", function(e) {
        if (e.key === "Enter") {
          e.preventDefault();
          lookupScannedRoll();
        }
      });

      function onWorkOrderChanged(select) {
        var opt = select.options[select.selectedIndex];
        var otNum = opt ? (opt.getAttribute("data-ot") || "") : "";
        document.getElementById("hid_ot_number").value = otNum;
      }

      function onMachineChanged(select) {
        var opt = select.options[select.selectedIndex];
        var mName = opt ? (opt.getAttribute("data-name") || "") : "";
        document.getElementById("hid_machine_name").value = mName;
      }

      function prefillTransferFromRequest(otNum, machineName, machineId, solicitante) {
        switchTransferTab("nuevo");
        var selWo = document.getElementById("sel_work_order");
        if (selWo) {
          for (var i = 0; i < selWo.options.length; i++) {
            if ((selWo.options[i].getAttribute("data-ot") || "") === otNum) {
              selWo.selectedIndex = i;
              onWorkOrderChanged(selWo);
              break;
            }
          }
        }
        document.getElementById("hid_ot_number").value = otNum;

        var selM = document.getElementById("sel_machine");
        if (selM) {
          for (var j = 0; j < selM.options.length; j++) {
            if ((selM.options[j].getAttribute("data-name") || "").toUpperCase() === machineName.toUpperCase() || selM.options[j].value == machineId) {
              selM.selectedIndex = j;
              onMachineChanged(selM);
              break;
            }
          }
        }
        document.getElementById("hid_machine_name").value = machineName;

        if (solicitante) {
          document.getElementById("inp_quien_solicito").value = solicitante;
        }
      }

      function validateTransferForm() {
        var actionMode = document.getElementById("hid_action_mode").value || "to_production";
        var rollId = parseInt(document.getElementById("hid_roll_id").value || "0", 10);
        if (actionMode === "to_warehouse") {
          if (!rollId || rollId <= 0) {
            var hiddenCode = normalizeScannedCode(document.getElementById("hid_roll_code").value || "");
            var scanInput = normalizeScannedCode(document.getElementById("inp_scan_code").value || "");
            var fallbackCode = hiddenCode || scanInput;
            if (!fallbackCode) {
              alert("Para traspasar entre bodegas debe seleccionar una bobina o escanear su código.");
              return false;
            }
            document.getElementById("hid_roll_code").value = fallbackCode;
          }
          var whSel = document.getElementById("sel_destination_warehouse");
          if (!whSel.value) {
            alert("Debe seleccionar la bodega destino.");
            whSel.focus();
            return false;
          }
          var warehouseOperator = document.getElementById("inp_transfer_operator");
          if (warehouseOperator && !warehouseOperator.value.trim()) {
            alert("Debe indicar quién realiza el traspaso.");
            warehouseOperator.focus();
            return false;
          }
        }
        if (actionMode !== "to_production") {
          return true;
        }
        var selWo = document.getElementById("sel_work_order");
        if (!selWo.value) {
          alert("Debe seleccionar una OT de destino en planta.");
          selWo.focus();
          return false;
        }
        var selM = document.getElementById("sel_machine");
        if (!selM.value) {
          alert("Debe seleccionar la máquina receptora en planta.");
          selM.focus();
          return false;
        }
        var sol = document.getElementById("inp_quien_solicito").value.trim();
        if (!sol) {
          alert("Debe ingresar quién solicitó la bobina.");
          document.getElementById("inp_quien_solicito").focus();
          return false;
        }
        var sac = document.getElementById("inp_quien_saco").value.trim();
        if (!sac) {
          alert("Debe ingresar quién sacó/despachó la bobina de bodega.");
          document.getElementById("inp_quien_saco").focus();
          return false;
        }
        return true;
      }

      // Inicializar pestaña activa
      switchTransferTab("' . h($activeTab) . '");
      toggleDestinationMode("production");
      applyMassRollsFilter();
      updateMassSelectionSummary();
    </script>';

    $body .= '</div>'; // Fin transfers-shell

    render('Traspasos de Bodega', $body);
}

/**
 * Pantalla de Baja y Descuento de Bobinas Ocupadas (Exclusivo Javier Perozo).
 * Permite seleccionar con checkboxes o pegar/pistolear códigos para darlos de baja
 * retirándolos de bodega (traceability_warehouse_entries) y marcando estado CONSUMED.
 */
function unibagRenderRollsDecommissionPage(ReceptionService $service, ?ProductionService $prodService, string $currentOperatorName): void
{
    $isJavier = unibagIsUserJavier() || (stripos($currentOperatorName, 'JAVIER') !== false);
    if (!$isJavier) {
        $body = '<div style="max-width:620px;margin:50px auto;padding:32px;background:#ffffff;border-radius:14px;border:1px solid #fee2e2;border-left:6px solid #ef4444;box-shadow:0 10px 25px -5px rgba(239,68,68,0.12);font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif">';
        $body .= '  <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px">';
        $body .= '    <span style="font-size:36px">⛔</span>';
        $body .= '    <div>';
        $body .= '      <h2 style="margin:0;font-size:20px;font-weight:800;color:#991b1b">Acceso Restringido</h2>';
        $body .= '      <div style="font-size:13px;color:#dc2626;font-weight:600">Autorización exclusiva requerida</div>';
        $body .= '    </div>';
        $body .= '  </div>';
        $body .= '  <p style="color:#475569;font-size:14px;line-height:1.6;margin-bottom:20px">';
        $body .= '    El módulo de <strong>Baja y Descuento de Bobinas Ocupadas</strong> realiza modificaciones directas sobre el inventario y está reservado exclusivamente para <strong>Javier Perozo</strong>.';
        $body .= '  </p>';
        $body .= '  <div style="display:flex;gap:10px;flex-wrap:wrap">';
        $body .= '    <a href="/stock/transfers" class="btn" style="background:#0f172a;color:#fff;font-size:13px;padding:9px 18px;border-radius:8px;text-decoration:none">← Ir a Traspasos</a>';
        $body .= '    <a href="/stock" class="btn secondary" style="font-size:13px;padding:9px 18px;border-radius:8px;text-decoration:none">📦 Consulta de Stock</a>';
        $body .= '  </div>';
        $body .= '</div>';
        render('Acceso Restringido - Baja de Bobinas', $body);
        return;
    }

    $allAvailableRolls = $service->listAllRollsForWarehouseTransfer(null, 3000);
    $history = $service->listDecommissionedRollsHistory(60);

    $isDone = isset($_GET['done']) && (string)$_GET['done'] === '1';
    $doneCount = (int)($_GET['count'] ?? 0);
    $doneFail = (int)($_GET['fail'] ?? 0);
    $doneWeight = (float)($_GET['weight'] ?? 0);
    $doneTraceOnly = isset($_GET['trace_only']) && (string)$_GET['trace_only'] === '1';
    $doneReason = trim((string)($_GET['reason'] ?? ''));
    $bulkErrors = trim((string)($_GET['bulk_errors'] ?? ''));
    $errorMessage = trim((string)($_GET['error'] ?? ''));
    $activeTab = trim((string)($_GET['tab'] ?? ($isDone ? 'bajas' : 'masivo')));

    $originWhCounts = [];
    $totalKgAvailable = 0.0;
    $countChildRolls = 0;
    foreach ($allAvailableRolls as $r) {
        $wId = (int)$r['warehouse_id'];
        $wName = trim((string)($r['wh_name'] ?? ''));
        $wCode = trim((string)($r['wh_code'] ?? ''));
        $displayName = $wCode !== '' ? "{$wCode} - {$wName}" : $wName;
        if (!isset($originWhCounts[$wId])) {
            $originWhCounts[$wId] = ['id' => $wId, 'name' => $displayName, 'count' => 0];
        }
        $originWhCounts[$wId]['count']++;
        $totalKgAvailable += (float)($r['weight_kg'] ?? 0);
        $isChild = !empty($r['parent_roll_id']) || stripos((string)$r['roll_code'], 'ROLL-') === 0 || ($r['process_stage'] ?? '') === 'CUT';
        if ($isChild) {
            $countChildRolls++;
        }
    }
    uasort($originWhCounts, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);

    $body = '<div class="decom-shell" style="max-width:1320px;margin:0 auto;padding:14px 18px 45px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif">';

    // Encabezado
    $body .= '<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #e2e8f0">';
    $body .= '  <div>';
    $body .= '    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">';
    $body .= '      <h1 style="margin:0;font-size:24px;font-weight:800;color:#0f172a;letter-spacing:-0.5px">🗑️ Baja y Descuento de Bobinas Ocupadas</h1>';
    $body .= '      <span style="background:#f3e8ff;color:#7e22ce;font-size:12px;font-weight:800;padding:3px 10px;border-radius:12px;border:1px solid #e9d5ff">🔒 Exclusivo Javier Perozo</span>';
    $body .= '    </div>';
    $body .= '    <p style="margin:6px 0 0;font-size:13.5px;color:#64748b;max-width:860px">';
    $body .= '      Módulo administrativo para dar de baja códigos de trazabilidad que <strong>ya fueron ocupados o consumidos en planta</strong> y retirarlos del inventario activo de bodega. Puede marcar bobinas con checkboxes o pegar una lista de códigos.';
    $body .= '    </p>';
    $body .= '  </div>';
    $body .= '  <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">';
    $body .= '    <a href="/stock/transfers" class="btn secondary" style="font-size:13px;padding:8px 14px;text-decoration:none">🔄 Traspasos de Bodega</a>';
    $body .= '    <a href="/stock" class="btn secondary" style="font-size:13px;padding:8px 14px;text-decoration:none">📦 Consulta de Stock</a>';
    $body .= '    <a href="/stock/traceability" class="btn secondary" style="font-size:13px;padding:8px 14px;text-decoration:none">🌳 Trazabilidad 360°</a>';
    $body .= '  </div>';
    $body .= '</div>';

    // Banner de error si existe
    if ($errorMessage !== '') {
        $body .= '<div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 16px;border-radius:8px;margin-bottom:18px;display:flex;align-items:center;gap:10px">';
        $body .= '  <span style="font-size:20px">⚠️</span>';
        $body .= '  <div style="font-weight:600">' . h($errorMessage) . '</div>';
        $body .= '</div>';
    }

    // Banner de éxito si acaba de dar de baja
    if ($isDone) {
        $body .= '<div style="background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);border:1px solid #86efac;border-radius:12px;padding:18px 22px;margin-bottom:22px;box-shadow:0 4px 12px rgba(22,101,52,0.08)">';
        $body .= '  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px">';
        $body .= '    <div>';
        $body .= '      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">';
        $body .= '        <span style="font-size:22px">✅</span>';
        $body .= '        <span style="font-size:17px;font-weight:800;color:#166534">¡Baja de bobinas procesada exitosamente!</span>';
        $body .= '      </div>';
        $body .= '      <div style="font-size:13.5px;color:#14532d;line-height:1.6">';
        $body .= '        Se dieron de baja y descontaron <strong>' . $doneCount . ' bobinas</strong> por un peso total de <strong>' . number_format($doneWeight, 2, ',', '.') . ' kg</strong>.<br>';
        $body .= '        Modo aplicado: ' . ($doneTraceOnly ? '<span style="background:#fef08a;color:#854d0e;padding:2px 7px;border-radius:4px;font-weight:700">Solo Trazabilidad (sin mover inventario contable ERP)</span>' : '<span style="background:#bbf7d0;color:#166534;padding:2px 7px;border-radius:4px;font-weight:700">Trazabilidad + Descuento contable en ERP</span>');
        if ($doneFail > 0) {
            $body .= '<br><span style="color:#b91c1c;font-weight:700">⚠️ ' . $doneFail . ' bobina(s) presentaron inconvenientes. ' . h($bulkErrors) . '</span>';
        }
        $body .= '      </div>';
        $body .= '    </div>';
        $body .= '    <a href="/stock/bajas" class="btn" style="background:#166534;color:#fff;padding:8px 14px;font-size:13px;border-radius:6px;text-decoration:none">✓ Aceptar</a>';
        $body .= '  </div>';
        $body .= '</div>';
    }

    // Mini KPIs informativos
    $body .= '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));gap:14px;margin-bottom:20px">';
    $body .= '  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;box-shadow:0 1px 3px rgba(0,0,0,0.04)">';
    $body .= '    <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase">Bobinas Disponibles</div>';
    $body .= '    <div style="font-size:24px;font-weight:800;color:#0f172a;margin-top:4px">' . number_format(count($allAvailableRolls), 0, ',', '.') . '</div>';
    $body .= '    <div style="font-size:12px;color:#94a3b8;margin-top:2px">Listas para regularizar</div>';
    $body .= '  </div>';
    $body .= '  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;box-shadow:0 1px 3px rgba(0,0,0,0.04)">';
    $body .= '    <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase">Peso Total Disponible</div>';
    $body .= '    <div style="font-size:24px;font-weight:800;color:#0f172a;margin-top:4px">' . number_format($totalKgAvailable, 2, ',', '.') . ' <span style="font-size:14px;font-weight:600;color:#64748b">kg</span></div>';
    $body .= '    <div style="font-size:12px;color:#94a3b8;margin-top:2px">Todas las bodegas activas</div>';
    $body .= '  </div>';
    $body .= '  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;box-shadow:0 1px 3px rgba(0,0,0,0.04)">';
    $body .= '    <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase">Bobinas Hijas / Cortes</div>';
    $body .= '    <div style="font-size:24px;font-weight:800;color:#0284c7;margin-top:4px">' . number_format($countChildRolls, 0, ',', '.') . '</div>';
    $body .= '    <div style="font-size:12px;color:#94a3b8;margin-top:2px">Cortes ROLL-MAN / BOL</div>';
    $body .= '  </div>';
    $body .= '  <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;box-shadow:0 1px 3px rgba(0,0,0,0.04)">';
    $body .= '    <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase">Historial de Bajas</div>';
    $body .= '    <div style="font-size:24px;font-weight:800;color:#7c3aed;margin-top:4px">' . count($history) . '</div>';
    $body .= '    <div style="font-size:12px;color:#94a3b8;margin-top:2px">Bajas auditadas registradas</div>';
    $body .= '  </div>';
    $body .= '</div>';

    // Tabs de navegación
    $body .= '<div style="display:flex;gap:4px;border-bottom:2px solid #e2e8f0;margin-bottom:20px">';
    $body .= '  <button type="button" class="tab-btn" id="btn_tab_decom_masivo" onclick="switchDecomTab(\'masivo\')" style="padding:10px 18px;font-size:14px;font-weight:700;border:none;background:none;cursor:pointer;border-bottom:3px solid #dc2626;color:#0f172a;margin-bottom:-2px">';
    $body .= '    ⚡ Baja Rápida Masiva <span style="background:#fee2e2;color:#991b1b;font-size:11px;padding:2px 7px;border-radius:10px;margin-left:4px">' . count($allAvailableRolls) . '</span>';
    $body .= '  </button>';
    $body .= '  <button type="button" class="tab-btn" id="btn_tab_decom_pistolear" onclick="switchDecomTab(\'pistolear\')" style="padding:10px 18px;font-size:14px;font-weight:700;border:none;background:none;cursor:pointer;border-bottom:3px solid transparent;color:#64748b;margin-bottom:-2px">';
    $body .= '    📋 Pistolear / Pegar Códigos';
    $body .= '  </button>';
    $body .= '  <button type="button" class="tab-btn" id="btn_tab_decom_historial" onclick="switchDecomTab(\'historial\')" style="padding:10px 18px;font-size:14px;font-weight:700;border:none;background:none;cursor:pointer;border-bottom:3px solid transparent;color:#64748b;margin-bottom:-2px">';
    $body .= '    📜 Historial Auditado <span style="background:#e2e8f0;color:#334155;font-size:11px;padding:2px 7px;border-radius:10px;margin-left:4px">' . count($history) . '</span>';
    $body .= '  </button>';
    $body .= '</div>';

    // =========================================================================
    // TAB 1: FORMULARIO DE BAJA MASIVA
    // =========================================================================
    $body .= '<div id="panel_tab_decom_masivo" class="decom-panel">';
    $body .= '  <form method="post" action="/stock/bajas" id="form_decommission" onsubmit="return validateDecommissionForm()">';
    $body .= '    <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
    $body .= '    <input type="hidden" name="action_mode" value="decommission_rolls">';
    $body .= '    <input type="hidden" name="operator_name" value="' . h($currentOperatorName) . '">';

    // Barra de Acción Adherente (Sticky Bar)
    $body .= '    <div style="position:sticky;top:10px;z-index:40;background:#ffffff;border:2px solid #dc2626;border-radius:12px;padding:16px 20px;margin-bottom:18px;box-shadow:0 10px 25px -5px rgba(220,38,38,0.22)">';
    $body .= '      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #fee2e2">';
    $body .= '        <div style="display:flex;align-items:center;gap:12px">';
    $body .= '          <span style="font-size:26px">🗑️</span>';
    $body .= '          <div>';
    $body .= '            <div style="font-size:16px;font-weight:800;color:#991b1b" id="lbl_decom_summary">0 bobina(s) seleccionada(s) (0,00 kg)</div>';
    $body .= '            <div style="font-size:12px;color:#64748b" id="lbl_decom_subtext">Marque las bobinas en la tabla o use la pestaña "Pistolear / Pegar Códigos" para cargarlas.</div>';
    $body .= '          </div>';
    $body .= '        </div>';
    $body .= '        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
    $body .= '          <button type="button" class="btn secondary" onclick="decomSelectVisibleOnly(true)" style="font-size:12px;padding:6px 12px;background:#f8fafc">✓ Marcar visibles</button>';
    $body .= '          <button type="button" class="btn secondary" onclick="decomSelectVisibleOnly(false)" style="font-size:12px;padding:6px 12px;background:#f8fafc">✕ Desmarcar visibles</button>';
    $body .= '          <button type="button" class="btn secondary" onclick="decomDeselectAll()" style="font-size:12px;padding:6px 12px;background:#f8fafc;color:#ef4444">Limpiar todo</button>';
    $body .= '        </div>';
    $body .= '      </div>';

    $body .= '      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:14px;align-items:flex-end">';
    $body .= '        <div>';
    $body .= '          <label style="display:block;font-size:12px;font-weight:800;color:#0f172a;margin-bottom:5px">Motivo de la Baja / Consumo <span style="color:#ef4444">*</span></label>';
    $body .= '          <select name="reason" id="sel_decom_reason" style="width:100%;height:40px;padding:6px 10px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:13px;font-weight:700;color:#0f172a;background:#fff">';
    $body .= '            <option value="CONSUMED_IN_PRODUCTION" selected>✅ Ya ocupada en producción (Consumo directo)</option>';
    $body .= '            <option value="MACHINE_CONSUMED">✂️ Bobina consumida / agotada en máquina</option>';
    $body .= '            <option value="SCRAP_DISCARD">⚠️ Descarte / Merma de producción</option>';
    $body .= '            <option value="INVENTORY_ADJUSTMENT">📝 Ajuste manual de inventario</option>';
    $body .= '            <option value="DUPLICATE_REGULARIZATION">🔄 Código duplicado / regularización</option>';
    $body .= '          </select>';
    $body .= '        </div>';

    $body .= '        <div>';
    $body .= '          <label style="display:block;font-size:12px;font-weight:800;color:#0f172a;margin-bottom:5px">Observación / OT / Detalle (Opcional)</label>';
    $body .= '          <input type="text" name="notes" id="inp_decom_notes" placeholder="Ej: Ocupada en OT 10542 / Descarte en máquina / Turno noche" style="width:100%;height:40px;padding:6px 12px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:13px;box-sizing:border-box">';
    $body .= '        </div>';

    $body .= '        <div>';
    $body .= '          <label style="display:inline-flex;align-items:center;gap:8px;background:#fef3c7;padding:9px 12px;border-radius:8px;border:1.5px solid #fde68a;font-size:12.5px;font-weight:700;color:#92400e;cursor:pointer;width:100%;box-sizing:border-box" title="Si está marcado, solo retira la bobina del sistema de trazabilidad (TRZ). Si desmarca, también ajusta/descuenta el inventario contable en el ERP.">';
    $body .= '            <input type="checkbox" name="traceability_only" value="1" id="chk_decom_trace_only" checked style="width:18px;height:18px;cursor:pointer">';
    $body .= '            <span>🏷️ Solo dar de baja en Trazabilidad (sin mover ERP)</span>';
    $body .= '          </label>';
    $body .= '        </div>';

    $body .= '        <div>';
    $body .= '          <button type="submit" id="btn_submit_decom" class="btn" style="width:100%;height:40px;background:#dc2626;color:#fff;font-size:13.5px;font-weight:800;border-radius:8px;border:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 12px rgba(220,38,38,0.35);transition:all 0.15s ease">';
    $body .= '            <span>🗑️ Dar de Baja Bobinas Seleccionadas</span>';
    $body .= '          </button>';
    $body .= '        </div>';
    $body .= '      </div>';
    $body .= '    </div>';

    // Barra de Búsqueda y Filtros en Tiempo Real
    $body .= '    <div style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:16px 20px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">';
    $body .= '      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:12px;align-items:flex-end">';
    $body .= '        <div>';
    $body .= '          <label style="display:block;font-size:12px;font-weight:800;color:#0f172a;margin-bottom:4px">🔍 Búsqueda Rápida (SKU o Código de Bobina)</label>';
    $body .= '          <input type="text" id="decom_search_input" oninput="applyDecomFilter()" placeholder="Escriba SKU, ROLL-MAN, BOB, descripción..." style="width:100%;height:38px;padding:6px 12px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:13px;box-sizing:border-box">';
    $body .= '        </div>';

    $body .= '        <div>';
    $body .= '          <label style="display:block;font-size:12px;font-weight:800;color:#0f172a;margin-bottom:4px">Filtrar por Bodega Actual</label>';
    $body .= '          <select id="decom_filter_wh" onchange="applyDecomFilter()" style="width:100%;height:38px;padding:6px 10px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:13px;color:#0f172a;background:#fff">';
    $body .= '            <option value="">-- Todas las Bodegas (' . count($allAvailableRolls) . ' bobinas) --</option>';
    foreach ($originWhCounts as $owh) {
        $body .= '          <option value="' . (int)$owh['id'] . '">' . h($owh['name']) . ' (' . (int)$owh['count'] . ')</option>';
    }
    $body .= '          </select>';
    $body .= '        </div>';

    $body .= '        <div>';
    $body .= '          <label style="display:block;font-size:12px;font-weight:800;color:#0f172a;margin-bottom:4px">Tipo / Etapa de Proceso</label>';
    $body .= '          <select id="decom_filter_stage" onchange="applyDecomFilter()" style="width:100%;height:38px;padding:6px 10px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:13px;color:#0f172a;background:#fff">';
    $body .= '            <option value="">-- Todos los tipos --</option>';
    $body .= '            <option value="child">👶 Bobinas Hijas / Cortes (ROLL-MAN / BOL)</option>';
    $body .= '            <option value="mother">📦 Bobinas Madres / MP</option>';
    $body .= '            <option value="PRINTED">🖨️ Impresas (PRINTED)</option>';
    $body .= '            <option value="CUT">✂️ Corte (CUT)</option>';
    $body .= '            <option value="RAW">🌱 Materia Prima (RAW)</option>';
    $body .= '            <option value="splice">🔗 Empalmes</option>';
    $body .= '          </select>';
    $body .= '        </div>';

    $body .= '        <div style="display:flex;gap:6px">';
    $body .= '          <button type="button" class="btn secondary" onclick="resetDecomFilters()" style="height:38px;padding:0 14px;font-size:13px;border-radius:8px">✕ Limpiar Filtros</button>';
    $body .= '        </div>';
    $body .= '      </div>';

    // Chips de Acceso Rápido
    $body .= '      <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-top:12px;padding-top:10px;border-top:1px solid #f1f5f9">';
    $body .= '        <span style="font-size:11.5px;font-weight:700;color:#64748b">Filtros rápidos:</span>';
    $body .= '        <button type="button" onclick="setDecomQuickFilter(\'all\', \'\')" class="btn secondary" style="font-size:11.5px;padding:3px 9px;border-radius:14px;background:#f8fafc">✨ Todas (<span id="lbl_decom_quick_count">' . count($allAvailableRolls) . '</span>)</button>';
    $body .= '        <button type="button" onclick="setDecomQuickFilter(\'child\', \'\')" class="btn secondary" style="font-size:11.5px;padding:3px 9px;border-radius:14px;background:#e0f2fe;color:#0369a1;border-color:#bae6fd">👶 Solo Hijas / Cortes (' . $countChildRolls . ')</button>';
    if (isset($originWhCounts[58])) {
        $body .= '      <button type="button" onclick="setDecomQuickFilter(\'wh\', \'58\')" class="btn secondary" style="font-size:11.5px;padding:3px 9px;border-radius:14px;background:#fef3c7;color:#92400e;border-color:#fde68a">🏭 Bodega 3000 Planta (' . (int)$originWhCounts[58]['count'] . ')</button>';
    }
    if (isset($originWhCounts[1])) {
        $body .= '      <button type="button" onclick="setDecomQuickFilter(\'wh\', \'1\')" class="btn secondary" style="font-size:11.5px;padding:3px 9px;border-radius:14px;background:#f1f5f9">🏢 Bodega Central (' . (int)$originWhCounts[1]['count'] . ')</button>';
    }
    $body .= '        <div style="margin-left:auto;font-size:12.5px;color:#64748b">';
    $body .= '          Mostrando <strong id="lbl_decom_visible_count">' . count($allAvailableRolls) . '</strong> bobinas (<strong id="lbl_decom_visible_kg">' . number_format($totalKgAvailable, 2, ',', '.') . '</strong> kg)';
    $body .= '        </div>';
    $body .= '      </div>';
    $body .= '    </div>';

    // Tabla de Bobinas con Checkboxes
    $body .= '    <div style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05)">';
    $body .= '      <div style="overflow-x:auto;max-height:680px;overflow-y:auto">';
    $body .= '        <table id="table_decom_rolls" class="data-table" style="width:100%;border-collapse:collapse;font-size:12.5px">';
    $body .= '          <thead style="position:sticky;top:0;z-index:10;background:#f8fafc;border-bottom:2px solid #cbd5e1">';
    $body .= '            <tr>';
    $body .= '              <th style="padding:10px 12px;text-align:center;width:44px">';
    $body .= '                <input type="checkbox" id="chk_decom_master" onclick="toggleDecomSelectAll(this)" title="Marcar/desmarcar visibles" style="width:18px;height:18px;cursor:pointer">';
    $body .= '              </th>';
    $body .= '              <th style="padding:10px 12px;text-align:left">Código Bobina</th>';
    $body .= '              <th style="padding:10px 12px;text-align:left">Tipo / Genealogía</th>';
    $body .= '              <th style="padding:10px 12px;text-align:left">SKU / Material</th>';
    $body .= '              <th style="padding:10px 12px;text-align:left">Bodega Actual</th>';
    $body .= '              <th style="padding:10px 12px;text-align:right">Peso (Kg)</th>';
    $body .= '              <th style="padding:10px 12px;text-align:right">Metros / Ancho</th>';
    $body .= '              <th style="padding:10px 12px;text-align:center">Etapa</th>';
    $body .= '              <th style="padding:10px 12px;text-align:center">Acciones</th>';
    $body .= '            </tr>';
    $body .= '          </thead>';
    $body .= '          <tbody>';

    if (empty($allAvailableRolls)) {
        $body .= '          <tr>';
        $body .= '            <td colspan="9" style="text-align:center;padding:40px 20px;color:#94a3b8">';
        $body .= '              <div style="font-size:32px;margin-bottom:8px">📦</div>';
        $body .= '              <div style="font-size:15px;font-weight:700;color:#475569">No se encontraron bobinas activas disponibles.</div>';
        $body .= '            </td>';
        $body .= '          </tr>';
    } else {
        foreach ($allAvailableRolls as $r) {
            $rId = (int)$r['id'];
            $rCode = (string)$r['roll_code'];
            $rSku = (string)$r['sku_code'];
            $rSkuDesc = (string)($r['sku_description'] ?? '');
            $rWeight = (float)$r['weight_kg'];
            $rMeters = (float)($r['meters'] ?? 0);
            $rWidth = (int)($r['width_mm'] ?? 0);
            $rStage = (string)($r['process_stage'] ?? 'RAW');
            $rWhId = (int)$r['warehouse_id'];
            $rWhName = (string)($r['wh_name'] ?? '');
            $rWhCode = (string)($r['wh_code'] ?? '');
            $pId = !empty($r['parent_roll_id']) ? (int)$r['parent_roll_id'] : null;
            $pCode = (string)($r['parent_roll_code'] ?? '');

            $isChild = $pId !== null || stripos($rCode, 'ROLL-MAN-') === 0 || stripos($rCode, 'ROLL-BOL-') === 0 || $rStage === 'CUT';
            $isSplice = stripos($rCode, 'ROLL-EMP-') === 0;

            $searchBlob = strtolower("{$rCode} {$rSku} {$rSkuDesc} {$rWhName} {$rWhCode} {$pCode} {$rStage}");

            $body .= '          <tr class="decom-roll-row"';
            $body .= '              data-roll-id="' . $rId . '"';
            $body .= '              data-roll-code="' . h($rCode) . '"';
            $body .= '              data-wh-id="' . $rWhId . '"';
            $body .= '              data-stage="' . h($rStage) . '"';
            $body .= '              data-is-child="' . ($isChild ? '1' : '0') . '"';
            $body .= '              data-is-splice="' . ($isSplice ? '1' : '0') . '"';
            $body .= '              data-kg="' . $rWeight . '"';
            $body .= '              data-search="' . h($searchBlob) . '"';
            $body .= '              style="border-bottom:1px solid #f1f5f9;transition:background 0.1s ease">';

            // Checkbox
            $body .= '            <td style="padding:8px 12px;text-align:center" onclick="event.stopPropagation()">';
            $body .= '              <input type="checkbox" name="selected_rolls[]" value="' . $rId . '" class="decom-roll-chk" data-kg="' . $rWeight . '" data-code="' . h($rCode) . '" onchange="onDecomRowCheckChange(this)" style="width:18px;height:18px;cursor:pointer">';
            $body .= '            </td>';

            // Código
            $body .= '            <td style="padding:8px 12px;font-family:monospace;font-weight:700;font-size:12.5px" onclick="onDecomRowClick(event, this.parentElement)">';
            $body .= '              <a href="/stock/traceability?q=' . urlencode($rCode) . '" target="_blank" onclick="event.stopPropagation()" style="color:#0f172a;text-decoration:none;display:inline-flex;align-items:center;gap:4px" title="Ver trazabilidad 360°">';
            $body .= '                <span>' . h($rCode) . '</span>';
            $body .= '                <span style="font-size:10px;color:#94a3b8">↗</span>';
            $body .= '              </a>';
            $body .= '            </td>';

            // Tipo / Genealogía
            $body .= '            <td style="padding:8px 12px" onclick="onDecomRowClick(event, this.parentElement)">';
            if ($isSplice) {
                $body .= '              <span style="background:#fef3c7;color:#92400e;font-size:11px;font-weight:700;padding:2px 6px;border-radius:4px">🔗 Empalme</span>';
            } elseif ($isChild) {
                $body .= '              <span style="background:#e0f2fe;color:#0369a1;font-size:11px;font-weight:700;padding:2px 6px;border-radius:4px" title="Bobina hija de corte">👶 Hija / Corte</span>';
                if ($pCode !== '') {
                    $body .= '              <div style="font-size:10.5px;color:#64748b;margin-top:2px">Madre: <a href="/stock/traceability?q=' . urlencode($pCode) . '" target="_blank" onclick="event.stopPropagation()" style="color:#475569;text-decoration:underline">' . h($pCode) . '</a></div>';
                }
            } else {
                $body .= '              <span style="background:#f1f5f9;color:#475569;font-size:11px;font-weight:700;padding:2px 6px;border-radius:4px">📦 Madre / MP</span>';
            }
            $body .= '            </td>';

            // SKU
            $body .= '            <td style="padding:8px 12px" onclick="onDecomRowClick(event, this.parentElement)">';
            $body .= '              <div style="font-weight:700;color:#0f172a">' . h($rSku) . '</div>';
            if ($rSkuDesc !== '') {
                $body .= '              <div style="font-size:11px;color:#64748b;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' . h($rSkuDesc) . '">' . h($rSkuDesc) . '</div>';
            }
            $body .= '            </td>';

            // Bodega Actual
            $body .= '            <td style="padding:8px 12px" onclick="onDecomRowClick(event, this.parentElement)">';
            $body .= '              <span style="background:#f8fafc;border:1px solid #e2e8f0;color:#1e293b;font-size:11.5px;font-weight:700;padding:2px 7px;border-radius:4px">';
            $body .= h($rWhCode !== '' ? "{$rWhCode} - {$rWhName}" : $rWhName);
            $body .= '              </span>';
            $body .= '            </td>';

            // Peso
            $body .= '            <td style="padding:8px 12px;text-align:right;font-weight:800;color:#0f172a" onclick="onDecomRowClick(event, this.parentElement)">';
            $body .= number_format($rWeight, 2, ',', '.') . ' kg';
            $body .= '            </td>';

            // Metros / Ancho
            $body .= '            <td style="padding:8px 12px;text-align:right;color:#475569" onclick="onDecomRowClick(event, this.parentElement)">';
            $body .= ($rMeters > 0 ? number_format($rMeters, 0, ',', '.') . ' m' : '-');
            if ($rWidth > 0) {
                $body .= ' · ' . $rWidth . ' mm';
            }
            $body .= '            </td>';

            // Etapa
            $body .= '            <td style="padding:8px 12px;text-align:center" onclick="onDecomRowClick(event, this.parentElement)">';
            $stageColor = '#475569';
            $stageBg = '#f1f5f9';
            if ($rStage === 'CUT') {
                $stageColor = '#0284c7';
                $stageBg = '#e0f2fe';
            } elseif ($rStage === 'PRINTED') {
                $stageColor = '#7c3aed';
                $stageBg = '#f3e8ff';
            }
            $body .= '              <span style="background:' . $stageBg . ';color:' . $stageColor . ';font-size:10.5px;font-weight:800;padding:2px 6px;border-radius:4px">' . h($rStage) . '</span>';
            $body .= '            </td>';

            // Acciones
            $body .= '            <td style="padding:8px 12px;text-align:center;white-space:nowrap" onclick="event.stopPropagation()">';
            $body .= '              <a href="/stock/traceability?q=' . urlencode($rCode) . '" target="_blank" class="btn secondary" style="font-size:11px;padding:3px 7px" title="Ver árbol de trazabilidad 360°">🌳 Árbol</a>';
            $body .= '            </td>';
            $body .= '          </tr>';
        }
    }

    $body .= '            <tr id="decom_no_results_row" style="display:none">';
    $body .= '              <td colspan="9" style="text-align:center;padding:40px 20px;color:#94a3b8">';
    $body .= '                <div style="font-size:28px;margin-bottom:6px">🔍</div>';
    $body .= '                <div style="font-size:14px;font-weight:700;color:#475569">No hay bobinas que coincidan con los filtros aplicados.</div>';
    $body .= '                <button type="button" class="btn secondary" onclick="resetDecomFilters()" style="margin-top:10px;font-size:12px;padding:4px 12px">✕ Limpiar Filtros</button>';
    $body .= '              </td>';
    $body .= '            </tr>';

    $body .= '          </tbody>';
    $body .= '        </table>';
    $body .= '      </div>';
    $body .= '    </div>';
    $body .= '  </form>';
    $body .= '</div>'; // Fin panel masivo

    // =========================================================================
    // TAB 2: PISTOLEAR / PEGAR CÓDIGOS
    // =========================================================================
    $body .= '<div id="panel_tab_decom_pistolear" class="decom-panel" style="display:none">';
    $body .= '  <div style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:22px;box-shadow:0 1px 3px rgba(0,0,0,0.05);max-width:900px;margin:0 auto">';
    $body .= '    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">';
    $body .= '      <span style="font-size:28px">📋</span>';
    $body .= '      <div>';
    $body .= '        <h2 style="margin:0;font-size:18px;font-weight:800;color:#0f172a">Pistolear o Pegar Lista de Códigos de Bobinas</h2>';
    $body .= '        <p style="margin:4px 0 0;font-size:13px;color:#64748b">Pegue una columna de Excel, mensajes de texto o escanee con la pistola de códigos. Se marcarán automáticamente en la lista de baja.</p>';
    $body .= '      </div>';
    $body .= '    </div>';

    $body .= '    <div style="margin-bottom:14px">';
    $body .= '      <textarea id="txt_paste_codes" placeholder="Pegue aquí los códigos de bobina (uno por línea o separados por coma o espacio):&#10;ROLL-MAN-01034&#10;ROLL-MAN-01035&#10;ROLL-BOL-00501&#10;BOB-00123" style="width:100%;height:160px;padding:12px 14px;border:1.5px solid #cbd5e1;border-radius:8px;font-family:monospace;font-size:13px;box-sizing:border-box"></textarea>';
    $body .= '    </div>';

    $body .= '    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">';
    $body .= '      <button type="button" class="btn" onclick="selectPastedCodes()" style="background:#4f46e5;color:#fff;font-size:13.5px;font-weight:800;padding:10px 20px;border-radius:8px;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:8px;box-shadow:0 4px 12px rgba(79,70,229,0.3)">';
    $body .= '        <span>🔍 Buscar y Seleccionar en la Lista</span>';
    $body .= '      </button>';
    $body .= '      <div id="lbl_paste_status" style="font-size:13px;font-weight:700"></div>';
    $body .= '    </div>';
    $body .= '  </div>';
    $body .= '</div>';

    // =========================================================================
    // TAB 3: HISTORIAL AUDITADO DE BAJAS
    // =========================================================================
    $body .= '<div id="panel_tab_decom_historial" class="decom-panel" style="display:none">';
    $body .= '  <div style="background:#fff;border:1px solid #cbd5e1;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">';
    $body .= '    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">';
    $body .= '      <div>';
    $body .= '        <h2 style="margin:0;font-size:17px;font-weight:800;color:#0f172a">📜 Historial de Bobinas Dadas de Baja (Auditado)</h2>';
    $body .= '        <p style="margin:4px 0 0;font-size:13px;color:#64748b">Registro auditado de bobinas retiradas de stock por Javier Perozo, motivo de consumo y peso.</p>';
    $body .= '      </div>';
    $body .= '      <span style="background:#f1f5f9;color:#475569;font-size:12px;font-weight:700;padding:4px 10px;border-radius:6px">' . count($history) . ' registros</span>';
    $body .= '    </div>';

    if (empty($history)) {
        $body .= '    <div style="text-align:center;padding:40px 20px;color:#94a3b8">';
        $body .= '      <div style="font-size:32px;margin-bottom:8px">📋</div>';
        $body .= '      <div style="font-size:15px;font-weight:700;color:#475569">Aún no se registran bajas manuales de bobinas en este módulo.</div>';
        $body .= '    </div>';
    } else {
        $body .= '    <div style="overflow-x:auto">';
        $body .= '      <table class="data-table" style="width:100%;border-collapse:collapse;font-size:13px">';
        $body .= '        <thead>';
        $body .= '          <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;text-align:left">';
        $body .= '            <th style="padding:10px 12px">Fecha / Hora</th>';
        $body .= '            <th style="padding:10px 12px">Código Bobina</th>';
        $body .= '            <th style="padding:10px 12px">SKU / Material</th>';
        $body .= '            <th style="padding:10px 12px">Bodega de Origen</th>';
        $body .= '            <th style="padding:10px 12px">Peso</th>';
        $body .= '            <th style="padding:10px 12px">Motivo de Baja</th>';
        $body .= '            <th style="padding:10px 12px">Observación / OT</th>';
        $body .= '            <th style="padding:10px 12px">Operador</th>';
        $body .= '            <th style="padding:10px 12px">Modo</th>';
        $body .= '            <th style="padding:10px 12px;text-align:center">Trazabilidad</th>';
        $body .= '          </tr>';
        $body .= '        </thead>';
        $body .= '        <tbody>';
        foreach ($history as $h) {
            $rCode = (string)$h['roll_code'];
            $rSku = (string)$h['sku_code'];
            $rWeight = (float)$h['weight_kg'];
            $whName = (string)($h['wh_name'] ?? '');
            $whCode = (string)($h['wh_code'] ?? '');
            $dt = (string)$h['decommissioned_at'];
            $payload = json_decode((string)($h['movement_payload'] ?? '{}'), true) ?: [];
            $reason = (string)($payload['reason'] ?? 'CONSUMED');
            $notes = (string)($payload['notes'] ?? '');
            $op = (string)($payload['operator_name'] ?? 'Javier Perozo');
            $traceOnly = !empty($payload['traceability_only']);

            $reasonMap = [
                'CONSUMED_IN_PRODUCTION' => '✅ Ocupada en producción',
                'MACHINE_CONSUMED' => '✂️ Consumida en máquina',
                'SCRAP_DISCARD' => '⚠️ Descarte / Merma',
                'INVENTORY_ADJUSTMENT' => '📝 Ajuste de inventario',
                'DUPLICATE_REGULARIZATION' => '🔄 Regularización duplicado',
            ];
            $reasonLabel = $reasonMap[$reason] ?? $reason;

            $body .= '          <tr style="border-bottom:1px solid #f1f5f9">';
            $body .= '            <td style="padding:10px 12px;font-size:12px;color:#64748b;white-space:nowrap">' . h($dt) . '</td>';
            $body .= '            <td style="padding:10px 12px;font-family:monospace;font-weight:700">' . h($rCode) . '</td>';
            $body .= '            <td style="padding:10px 12px;font-weight:600">' . h($rSku) . '</td>';
            $body .= '            <td style="padding:10px 12px">' . h($whCode !== '' ? "{$whCode} - {$whName}" : $whName) . '</td>';
            $body .= '            <td style="padding:10px 12px;font-weight:700">' . number_format($rWeight, 2, ',', '.') . ' kg</td>';
            $body .= '            <td style="padding:10px 12px"><span style="background:#fee2e2;color:#991b1b;font-size:11px;font-weight:700;padding:2px 7px;border-radius:4px">' . h($reasonLabel) . '</span></td>';
            $body .= '            <td style="padding:10px 12px;color:#475569">' . h($notes ?: '-') . '</td>';
            $body .= '            <td style="padding:10px 12px;font-weight:700;color:#0f172a">' . h($op) . '</td>';
            $body .= '            <td style="padding:10px 12px">';
            $body .= $traceOnly
                ? '<span style="background:#fef08a;color:#854d0e;font-size:10.5px;font-weight:700;padding:2px 6px;border-radius:4px">Solo TRZ</span>'
                : '<span style="background:#bbf7d0;color:#166534;font-size:10.5px;font-weight:700;padding:2px 6px;border-radius:4px">TRZ + ERP</span>';
            $body .= '            </td>';
            $body .= '            <td style="padding:10px 12px;text-align:center">';
            $body .= '              <a href="/stock/traceability?q=' . urlencode($rCode) . '" target="_blank" class="btn secondary" style="font-size:11px;padding:3px 7px">🌳 Árbol</a>';
            $body .= '            </td>';
            $body .= '          </tr>';
        }
        $body .= '        </tbody>';
        $body .= '      </table>';
        $body .= '    </div>';
    }
    $body .= '  </div>';
    $body .= '</div>';

    // JavaScript interactivo
    $body .= '<script>
      function switchDecomTab(tab) {
        var panels = {
          "masivo": document.getElementById("panel_tab_decom_masivo"),
          "pistolear": document.getElementById("panel_tab_decom_pistolear"),
          "historial": document.getElementById("panel_tab_decom_historial")
        };
        for (var key in panels) {
          if (panels[key]) {
            panels[key].style.display = key === tab ? "block" : "none";
          }
          var btn = document.getElementById("btn_tab_decom_" + key);
          if (btn) {
            if (key === tab) {
              btn.style.color = "#0f172a";
              btn.style.borderBottomColor = "#dc2626";
            } else {
              btn.style.color = "#64748b";
              btn.style.borderBottomColor = "transparent";
            }
          }
        }
      }

      function applyDecomFilter() {
        var searchInput = document.getElementById("decom_search_input");
        var query = searchInput ? (searchInput.value || "").trim().toLowerCase() : "";
        var whSelect = document.getElementById("decom_filter_wh");
        var whFilter = whSelect ? whSelect.value : "";
        var stageSelect = document.getElementById("decom_filter_stage");
        var stageFilter = stageSelect ? stageSelect.value : "";

        var tokens = query ? query.split(/\\s+/).filter(Boolean) : [];
        var rows = document.querySelectorAll(".decom-roll-row");
        var visibleCount = 0;
        var visibleKg = 0;

        for (var i = 0; i < rows.length; i++) {
          var row = rows[i];
          var searchBlob = row.getAttribute("data-search") || "";
          var rowWh = row.getAttribute("data-wh-id") || "";
          var rowStage = row.getAttribute("data-stage") || "";
          var isChild = row.getAttribute("data-is-child") === "1";
          var isSplice = row.getAttribute("data-is-splice") === "1";
          var rowKg = parseFloat(row.getAttribute("data-kg") || "0");

          var matchesText = true;
          for (var t = 0; t < tokens.length; t++) {
            if (searchBlob.indexOf(tokens[t]) === -1) {
              matchesText = false;
              break;
            }
          }

          var matchesWh = !whFilter || rowWh === whFilter;

          var matchesStage = true;
          if (stageFilter === "child") {
            matchesStage = isChild;
          } else if (stageFilter === "mother") {
            matchesStage = !isChild;
          } else if (stageFilter === "splice") {
            matchesStage = isSplice;
          } else if (stageFilter) {
            matchesStage = rowStage === stageFilter;
          }

          if (matchesText && matchesWh && matchesStage) {
            row.style.display = "";
            visibleCount++;
            visibleKg += rowKg;
          } else {
            row.style.display = "none";
          }
        }

        var noResRow = document.getElementById("decom_no_results_row");
        if (noResRow) {
          noResRow.style.display = visibleCount === 0 ? "" : "none";
        }

        var lblCount = document.getElementById("lbl_decom_visible_count");
        if (lblCount) lblCount.textContent = visibleCount;
        var lblQuick = document.getElementById("lbl_decom_quick_count");
        if (lblQuick) lblQuick.textContent = visibleCount;
        var lblKg = document.getElementById("lbl_decom_visible_kg");
        if (lblKg) lblKg.textContent = visibleKg.toLocaleString("es-CL", {minimumFractionDigits: 2, maximumFractionDigits: 2});

        syncDecomMasterCheckbox();
      }

      function onDecomRowClick(event, row) {
        if (!row) return;
        var chk = row.querySelector(".decom-roll-chk");
        if (chk) {
          chk.checked = !chk.checked;
          onDecomRowCheckChange(chk);
        }
      }

      function onDecomRowCheckChange(chk) {
        if (!chk) return;
        var row = chk.closest(".decom-roll-row");
        if (row) {
          row.style.backgroundColor = chk.checked ? "#fee2e2" : "";
        }
        updateDecomSelectionSummary();
        syncDecomMasterCheckbox();
      }

      function toggleDecomSelectAll(masterChk) {
        var state = masterChk.checked;
        var rows = document.querySelectorAll(".decom-roll-row");
        for (var i = 0; i < rows.length; i++) {
          if (rows[i].style.display !== "none") {
            var chk = rows[i].querySelector(".decom-roll-chk");
            if (chk) {
              chk.checked = state;
              rows[i].style.backgroundColor = state ? "#fee2e2" : "";
            }
          }
        }
        updateDecomSelectionSummary();
      }

      function decomSelectVisibleOnly(state) {
        var rows = document.querySelectorAll(".decom-roll-row");
        for (var i = 0; i < rows.length; i++) {
          if (rows[i].style.display !== "none") {
            var chk = rows[i].querySelector(".decom-roll-chk");
            if (chk) {
              chk.checked = state;
              rows[i].style.backgroundColor = state ? "#fee2e2" : "";
            }
          }
        }
        updateDecomSelectionSummary();
        syncDecomMasterCheckbox();
      }

      function decomDeselectAll() {
        var chks = document.querySelectorAll(".decom-roll-chk");
        for (var i = 0; i < chks.length; i++) {
          chks[i].checked = false;
          var row = chks[i].closest(".decom-roll-row");
          if (row) row.style.backgroundColor = "";
        }
        updateDecomSelectionSummary();
        syncDecomMasterCheckbox();
      }

      function syncDecomMasterCheckbox() {
        var master = document.getElementById("chk_decom_master");
        if (!master) return;
        var rows = document.querySelectorAll(".decom-roll-row");
        var visibleCount = 0;
        var checkedVisibleCount = 0;
        for (var i = 0; i < rows.length; i++) {
          if (rows[i].style.display !== "none") {
            visibleCount++;
            var chk = rows[i].querySelector(".decom-roll-chk");
            if (chk && chk.checked) {
              checkedVisibleCount++;
            }
          }
        }
        if (visibleCount === 0) {
          master.checked = false;
          master.indeterminate = false;
        } else if (checkedVisibleCount === visibleCount) {
          master.checked = true;
          master.indeterminate = false;
        } else if (checkedVisibleCount > 0) {
          master.checked = false;
          master.indeterminate = true;
        } else {
          master.checked = false;
          master.indeterminate = false;
        }
      }

      function updateDecomSelectionSummary() {
        var chks = document.querySelectorAll(".decom-roll-chk:checked");
        var totalCount = chks.length;
        var totalKg = 0;
        for (var i = 0; i < chks.length; i++) {
          totalKg += parseFloat(chks[i].getAttribute("data-kg") || "0");
        }

        var summaryLbl = document.getElementById("lbl_decom_summary");
        var subtextLbl = document.getElementById("lbl_decom_subtext");
        var submitBtn = document.getElementById("btn_submit_decom");

        if (summaryLbl) {
          summaryLbl.textContent = totalCount + " bobina(s) seleccionada(s) (" + totalKg.toLocaleString("es-CL", {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " kg)";
        }
        if (subtextLbl) {
          subtextLbl.textContent = totalCount > 0
            ? "Listo para procesar la baja. Revise el motivo y confirme la acción."
            : "Marque las bobinas en la tabla o pegue una lista de códigos para darlas de baja.";
        }
        if (submitBtn) {
          submitBtn.innerHTML = totalCount > 0
            ? "<span>🗑️ Dar de Baja " + totalCount + " Bobinas (" + totalKg.toFixed(1) + " kg)</span>"
            : "<span>🗑️ Dar de Baja Bobinas Seleccionadas</span>";
        }
      }

      function setDecomQuickFilter(type, value) {
        var searchInput = document.getElementById("decom_search_input");
        var whSelect = document.getElementById("decom_filter_wh");
        var stageSelect = document.getElementById("decom_filter_stage");

        if (type === "all") {
          if (searchInput) searchInput.value = "";
          if (whSelect) whSelect.value = "";
          if (stageSelect) stageSelect.value = "";
        } else if (type === "child") {
          if (stageSelect) stageSelect.value = "child";
        } else if (type === "wh") {
          if (whSelect) whSelect.value = value;
        }
        applyDecomFilter();
      }

      function resetDecomFilters() {
        var searchInput = document.getElementById("decom_search_input");
        var whSelect = document.getElementById("decom_filter_wh");
        var stageSelect = document.getElementById("decom_filter_stage");
        if (searchInput) searchInput.value = "";
        if (whSelect) whSelect.value = "";
        if (stageSelect) stageSelect.value = "";
        applyDecomFilter();
      }

      function selectPastedCodes() {
        var txtArea = document.getElementById("txt_paste_codes");
        var txt = txtArea ? (txtArea.value || "").trim() : "";
        if (!txt) {
          alert("Pegue o escanee al menos un código de bobina en el área de texto.");
          if (txtArea) txtArea.focus();
          return;
        }

        var lines = txt.split(/[\\r\\n,;\\t\\s]+/);
        var targetCodes = {};
        for (var i = 0; i < lines.length; i++) {
          var clean = lines[i].trim().toUpperCase();
          if (clean) {
            targetCodes[clean] = true;
          }
        }

        var rows = document.querySelectorAll(".decom-roll-row");
        var foundCount = 0;
        var notFound = [];
        var codeToRow = {};
        for (var j = 0; j < rows.length; j++) {
          var rCode = (rows[j].getAttribute("data-roll-code") || "").toUpperCase();
          if (rCode) {
            codeToRow[rCode] = rows[j];
          }
        }

        for (var codeKey in targetCodes) {
          if (codeToRow[codeKey]) {
            var rowEl = codeToRow[codeKey];
            var chkEl = rowEl.querySelector(".decom-roll-chk");
            if (chkEl) {
              chkEl.checked = true;
              rowEl.style.backgroundColor = "#fee2e2";
              foundCount++;
            }
          } else {
            notFound.push(codeKey);
          }
        }

        updateDecomSelectionSummary();
        syncDecomMasterCheckbox();

        var statusEl = document.getElementById("lbl_paste_status");
        if (statusEl) {
          var msg = "<span style=\'color:#166534\'>✓ Se encontraron y marcaron " + foundCount + " bobina(s).</span>";
          if (notFound.length > 0) {
            msg += "<br><span style=\'color:#dc2626\'>⚠️ No encontradas (o ya consumidas): " + notFound.slice(0, 5).join(", ") + (notFound.length > 5 ? " y " + (notFound.length - 5) + " más" : "") + "</span>";
          }
          statusEl.innerHTML = msg;
        }

        // Cambiar a la pestaña de lista para que Javier revise la selección
        switchDecomTab("masivo");
      }

      function validateDecommissionForm() {
        var chks = document.querySelectorAll(".decom-roll-chk:checked");
        if (chks.length === 0) {
          alert("Debe seleccionar al menos una bobina para procesar la baja.");
          return false;
        }

        var selReason = document.getElementById("sel_decom_reason");
        var reasonText = selReason ? selReason.options[selReason.selectedIndex].text.trim() : "Consumo en producción";
        var traceChk = document.getElementById("chk_decom_trace_only");
        var traceOnly = traceChk ? traceChk.checked : false;

        var totalKg = 0;
        for (var i = 0; i < chks.length; i++) {
          totalKg += parseFloat(chks[i].getAttribute("data-kg") || "0");
        }

        var modeMsg = traceOnly
          ? "⚠️ MODO SOLO TRAZABILIDAD (da de baja en TRZ sin modificar stock contable ERP)"
          : "📦 DESCUENTO TOTAL (da de baja en TRZ y descuenta inventario ERP)";

        var confirmMsg = "¿Confirma DAR DE BAJA Y DESCONTAR " + chks.length + " bobina(s) (" + totalKg.toFixed(2) + " kg)?\n\n"
          + "Motivo: " + reasonText + "\n"
          + modeMsg + "\n\n"
          + "Esta acción marcará las bobinas como CONSUMIDAS y las retirará del stock activo de bodega.";

        if (!confirm(confirmMsg)) {
          return false;
        }

        var submitBtn = document.getElementById("btn_submit_decom");
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.innerHTML = "<span>⏳ Procesando baja de bobinas...</span>";
        }
        return true;
      }

      // Inicializar
      switchDecomTab("' . h($activeTab) . '");
      applyDecomFilter();
      updateDecomSelectionSummary();
    </script>';

    $body .= '</div>'; // Fin decom-shell

    render('Baja de Bobinas - Exclusivo Javier', $body);
}

/**
 * Router de rutas de inventario.
 */
function handleInventoryRoutes(string $path, string $method, ReceptionService $service, string $currentOperatorName, ?ProductionService $prodService = null): bool
{
    // GET: Módulo rápido exclusivo Javier para dar de baja / descontar bobinas ocupadas
    if (($path === '/stock/bajas' || $path === '/stock/consume' || $path === '/stock/decommission') && $method === 'GET') {
        unibagRenderRollsDecommissionPage($service, $prodService, $currentOperatorName);
        return true;
    }

    // POST: Procesar baja de bobinas ocupadas (Exclusivo Javier)
    if (($path === '/stock/bajas' || $path === '/stock/consume' || $path === '/stock/decommission') && $method === 'POST') {
        requireCsrf();
        $isJavier = unibagIsUserJavier() || (stripos($currentOperatorName, 'JAVIER') !== false);
        if (!$isJavier) {
            redirectResponse('/stock/transfers?error=' . rawurlencode('Acceso denegado: solo Javier puede realizar bajas de bobinas.'));
            return true;
        }

        try {
            $selectedRollIds = array_map('intval', (array)($_POST['selected_rolls'] ?? []));
            $selectedRollIds = array_values(array_filter($selectedRollIds, static fn(int $id): bool => $id > 0));
            $reason = trim((string)($_POST['reason'] ?? 'CONSUMED_IN_PRODUCTION'));
            $notes = trim((string)($_POST['notes'] ?? ''));
            $traceabilityOnly = !empty($_POST['traceability_only']);
            $operatorName = trim((string)($_POST['operator_name'] ?? $currentOperatorName));
            if ($operatorName === '') {
                $operatorName = 'Javier Perozo';
            }

            if (empty($selectedRollIds)) {
                redirectResponse('/stock/bajas?error=' . rawurlencode('No seleccionó ninguna bobina para dar de baja.'));
                return true;
            }

            $result = $service->decommissionRollsBulk($selectedRollIds, $operatorName, $reason, $notes, $traceabilityOnly);
            $params = [
                'tab' => 'bajas',
                'done' => 1,
                'count' => (int)($result['success_count'] ?? 0),
                'fail' => (int)($result['fail_count'] ?? 0),
                'weight' => (float)($result['total_weight'] ?? 0),
                'trace_only' => $traceabilityOnly ? 1 : 0,
                'reason' => $reason,
            ];
            if (!empty($result['errors'])) {
                $params['bulk_errors'] = implode(' | ', array_slice((array)$result['errors'], 0, 5));
            }
            redirectResponse('/stock/bajas?' . http_build_query($params));
            return true;
        } catch (Throwable $e) {
            redirectResponse('/stock/bajas?error=' . rawurlencode('Error al procesar la baja de bobinas: ' . $e->getMessage()));
            return true;
        }
    }

    // GET: Pantalla completa de Traspasos a Bodega 3000 (Planta)
    if (($path === '/stock/transfers' || $path === '/stock/traspasos') && $method === 'GET') {
        unibagRenderWarehouseTransfersPage($service, $prodService, $currentOperatorName);
        return true;
    }

    // POST: Registrar Traspaso a Bodega 3000 (Planta) con trazabilidad integral
    if (($path === '/stock/transfers' || $path === '/stock/traspasos') && $method === 'POST') {
        requireCsrf();
        $actionMode = (string)($_POST['action_mode'] ?? '');
        $canOverrideTransferOperator = unibagCanOverrideTransferOperator($currentOperatorName);
        $postedTransferOperator = trim((string)($_POST['transfer_operator_name'] ?? $_POST['quien_la_saco'] ?? ''));
        $effectiveTransferOperator = $canOverrideTransferOperator && $postedTransferOperator !== ''
            ? $postedTransferOperator
            : $currentOperatorName;
        $isToProd = $actionMode === 'to_production'
            || !empty($_POST['quien_la_solicito'])
            || !empty($_POST['machine_name'])
            || !empty($_POST['ot_number'])
            || !empty($_POST['ot_id']);

        if ($actionMode === 'bulk_transfer') {
            try {
                $toWarehouseId = (int)($_POST['to_warehouse_id'] ?? 0);
                $selectedRollIds = array_map('intval', (array)($_POST['selected_rolls'] ?? []));
                $selectedRollIds = array_values(array_filter($selectedRollIds, static fn(int $id): bool => $id > 0));
                $traceabilityOnly = !empty($_POST['traceability_only']);

                if (empty($selectedRollIds)) {
                    redirectResponse('/stock/transfers?tab=masivo&error=' . rawurlencode('No seleccionó ninguna bobina para el traspaso masivo.'));
                    return true;
                }
                if ($toWarehouseId <= 0) {
                    redirectResponse('/stock/transfers?tab=masivo&error=' . rawurlencode('Debe seleccionar la bodega destino para el traspaso masivo.'));
                    return true;
                }

                $toWarehouseName = '';
                foreach ($service->listWarehouses() as $warehouse) {
                    if ((int)($warehouse['id'] ?? 0) === $toWarehouseId) {
                        $toWarehouseName = trim((string)(($warehouse['code'] ?? '') . ' - ' . ($warehouse['name'] ?? '')));
                        break;
                    }
                }

                $successCount = 0;
                $failCount = 0;
                $totalWeight = 0.0;
                $errors = [];

                foreach ($selectedRollIds as $rollId) {
                    $roll = $service->getRoll($rollId);
                    $rollCode = (string)($roll['roll_code'] ?? "#{$rollId}");
                    $result = $traceabilityOnly
                        ? $service->transferRollTraceabilityOnly($rollId, $toWarehouseId, $effectiveTransferOperator)
                        : $service->transferRoll($rollId, $toWarehouseId, $effectiveTransferOperator, null);

                    if (($result['ok'] ?? false) === true) {
                        $successCount++;
                        $totalWeight += (float)($roll['weight_kg'] ?? 0);
                    } else {
                        $failCount++;
                        $errStr = !empty($result['errors']) ? implode('; ', (array)$result['errors']) : 'Error desconocido';
                        $errors[] = "{$rollCode}: {$errStr}";
                    }
                }

                $params = [
                    'tab' => 'masivo',
                    'bulk_done' => 1,
                    'success_count' => $successCount,
                    'fail_count' => $failCount,
                    'to_wh' => $toWarehouseName,
                    'total_weight' => round($totalWeight, 2),
                    'trace_only' => $traceabilityOnly ? 1 : 0,
                ];
                if (!empty($errors)) {
                    $params['bulk_errors'] = implode(' | ', array_slice($errors, 0, 5));
                }
                redirectResponse('/stock/transfers?' . http_build_query($params));
                return true;
            } catch (Throwable $e) {
                redirectResponse('/stock/transfers?tab=masivo&error=' . rawurlencode('Error interno en traspaso masivo: ' . $e->getMessage()));
                return true;
            }
        }

        if ($actionMode === 'to_warehouse') {
            try {
                $rollId = (int)($_POST['roll_id'] ?? 0);
                $toWarehouseId = (int)($_POST['to_warehouse_id'] ?? 0);
                $scanCode = trim((string)($_POST['code'] ?? ''));
                $fromWarehouseId = (int)($_POST['from_warehouse_id'] ?? 0);
                $skuCode = trim((string)($_POST['sku'] ?? $_POST['sku_manual'] ?? ''));
                $skuDescription = trim((string)($_POST['sku_desc'] ?? $_POST['sku_desc_manual'] ?? ''));
                $traceabilityOnly = !empty($_POST['traceability_only']);

                if ($rollId <= 0 && $scanCode !== '') {
                    $ensureResult = $service->ensureTransferableRollByCode(
                        $scanCode,
                        $fromWarehouseId,
                        $effectiveTransferOperator,
                        $skuCode,
                        $skuDescription
                    );
                    if (($ensureResult['ok'] ?? false) !== true) {
                        $err = !empty($ensureResult['errors']) ? implode(', ', (array)$ensureResult['errors']) : 'No se pudo registrar la bobina escaneada para el traspaso.';
                        redirectResponse('/stock/transfers?error=' . rawurlencode($err));
                        return true;
                    }
                    $rollId = (int)($ensureResult['roll_id'] ?? 0);
                }

                $roll = $rollId > 0 ? $service->getRoll($rollId) : null;
                $fromWarehouseName = trim((string)($roll['warehouse_name'] ?? ''));
                $toWarehouseName = '';
                foreach ($service->listWarehouses() as $warehouse) {
                    if ((int)($warehouse['id'] ?? 0) === $toWarehouseId) {
                        $toWarehouseName = trim((string)(($warehouse['code'] ?? '') . ' - ' . ($warehouse['name'] ?? '')));
                        break;
                    }
                }

                if ($rollId <= 0 || $toWarehouseId <= 0) {
                    redirectResponse('/stock/transfers?error=' . rawurlencode('Debe seleccionar o escanear una bobina y definir la bodega destino.'));
                    return true;
                }

                $result = $traceabilityOnly
                    ? $service->transferRollTraceabilityOnly($rollId, $toWarehouseId, $effectiveTransferOperator)
                    : $service->transferRoll($rollId, $toWarehouseId, $effectiveTransferOperator, null);
                if (($result['ok'] ?? false) !== true) {
                    $err = !empty($result['errors']) ? implode(', ', (array)$result['errors']) : ($traceabilityOnly ? 'Error al registrar traspaso solo de trazabilidad.' : 'Error al registrar traspaso entre bodegas.');
                    redirectResponse('/stock/transfers?error=' . rawurlencode($err));
                    return true;
                }

                $params = http_build_query([
                    'moved' => 1,
                    'roll_id' => $rollId,
                    'roll_code' => (string)($roll['roll_code'] ?? ''),
                    'from_wh' => $fromWarehouseName,
                    'to_wh' => $toWarehouseName,
                    'weight' => (float)($roll['weight_kg'] ?? 0),
                    'saco' => $effectiveTransferOperator,
                    'trace_only' => $traceabilityOnly ? 1 : 0,
                ]);
                redirectResponse('/stock/transfers?' . $params);
                return true;
            } catch (Throwable $e) {
                redirectResponse('/stock/transfers?error=' . rawurlencode('Error interno al registrar el traspaso: ' . $e->getMessage()));
                return true;
            }
        }

        if ($isToProd) {
            $_POST['quien_la_saco'] = $effectiveTransferOperator;
            $res = $service->dispatchWarehouseTransferToProduction($_POST, $currentOperatorName);
            if (!$res['ok']) {
                $err = !empty($res['errors']) ? implode(', ', (array)$res['errors']) : 'Error al registrar traspaso a producción.';
                redirectResponse('/stock/transfers?error=' . rawurlencode($err));
                return true;
            }
            $params = http_build_query([
                'dispatched' => 1,
                'roll_id' => (int)($res['roll_id'] ?? 0),
                'roll_code' => (string)($res['roll_code'] ?? ''),
                'ot' => (string)($res['ot_number'] ?? ''),
                'machine' => (string)($res['machine_name'] ?? ''),
                'solicito' => (string)($res['quien_la_solicito'] ?? ''),
                'saco' => (string)($res['quien_la_saco'] ?? ''),
                'weight' => (float)($res['weight_kg'] ?? 0),
            ]);
            redirectResponse('/stock/transfers?' . $params);
            return true;
        }

        // Fallback para traspaso tradicional si se invoca con transfer_mode clásico
        $toWarehouseId = (int)($_POST['to_warehouse_id'] ?? 0);
        $rollId = (int)($_POST['roll_id'] ?? 0);
        $workOrderId = !empty($_POST['work_order_id']) ? (int)$_POST['work_order_id'] : null;
        if ($toWarehouseId > 0 && $rollId > 0) {
            $result = $service->transferRoll($rollId, $toWarehouseId, $effectiveTransferOperator, $workOrderId);
            if ($result['ok'] === true) {
                redirectResponse('/rolls/' . $rollId);
                return true;
            }
            $err = !empty($result['errors']) ? implode(', ', (array)$result['errors']) : 'Error al transferir bobina.';
            redirectResponse('/stock/transfers?error=' . rawurlencode($err));
            return true;
        }
    }

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
        $bodegaId = unibagResolveRequestedStorehouseId($service, $service->listErpStorehouses(true), $bodegaId);
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
        $postedWarehouseId = (int)($_POST['warehouse_id'] ?? 0);
        $normalizedWarehouseId = unibagResolveRequestedStorehouseId($service, $service->listErpStorehouses(true), $postedWarehouseId);
        if ($normalizedWarehouseId > 0) {
            $_POST['warehouse_id'] = $normalizedWarehouseId;
        }
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
        if (!unibagCanPerformInventoryCount()) {
            redirectResponse('/stock/inventory-counts?bodega=' . ((int)($_POST['bodega'] ?? 0)) . '&error=' . rawurlencode('La toma de inventario requiere acceso de Bodega o autorización de responsable.'));
            return true;
        }
        $storehouseId = (int)($_POST['bodega'] ?? 0);
        $storehouseName = trim((string)($_POST['storehouse_name'] ?? ''));
        $annotation = trim((string)($_POST['annotation'] ?? ''));
        $operatorName = trim((string)($_POST['operator_name'] ?? $currentOperatorName));

        $request = (string)($_POST['request_key'] ?? '');
        $sheet = $_SESSION['inventory_count_requests'][$request] ?? [];
        $postedItems = json_decode((string)($_POST['items_json'] ?? ''), true);
        if (!is_array($postedItems)) $postedItems = is_array($_POST['items'] ?? null) ? $_POST['items'] : [];
        if ((int)($sheet['warehouse'] ?? 0) !== $storehouseId || count($postedItems) !== (int)($sheet['items'] ?? -1)) {
            redirectResponse('/stock/inventory-counts?bodega=' . $storehouseId . '&error=' . rawurlencode('La planilla está incompleta o vencida. Cárgala nuevamente antes de guardar.'));
            return true;
        }
        $items = [];
        foreach ($postedItems as $it) {
            if (!is_array($it)) $it = [];
            $itemId = (int)($it['item_id'] ?? 0);
            $sysQty = (string)($it['system_qty'] ?? '');
            $phyQty = (string)($it['physical_qty'] ?? '');
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
            $items,
            $request
        );

        if (!$result['ok']) {
            redirectResponse('/stock/inventory-counts?bodega=' . $storehouseId . '&error=' . rawurlencode(implode(' ', (array)($result['errors'] ?? ['Error desconocido.']))));
            return true;
        }

        $stcNum = (string)($result['stc_num'] ?? '');
        $numbers = array_column((array)($result['adjustments'] ?? []), 'number');
        $message = 'Inventario ' . $stcNum . ' contabilizado. ' . ($numbers ? 'Ajustes: ' . implode(', ', $numbers) . '.' : 'Sin diferencias: no se generaron movimientos.');
        if (!empty($result['warning'])) $message .= ' ' . $result['warning'];
        redirectResponse('/stock/inventory-counts?bodega=' . $storehouseId . '&msg=' . rawurlencode($message));
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
