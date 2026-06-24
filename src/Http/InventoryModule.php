<?php

declare(strict_types=1);

/**
 * @return array{code:int,name:string}
 */
function unibagInventoryResolveWarehouse(array $warehouses, int $requestedCode, int $defaultCode = 100): array
{
    $availableWarehouseCodes = array_values(array_map(static fn(array $warehouse): int => (int)($warehouse['code'] ?? 0), $warehouses));
    $resolvedCode = $requestedCode > 0 ? $requestedCode : $defaultCode;
    if ($availableWarehouseCodes !== [] && !in_array($resolvedCode, $availableWarehouseCodes, true)) {
        $resolvedCode = $availableWarehouseCodes[0];
    }

    $resolvedName = '';
    foreach ($warehouses as $warehouse) {
        if ((int)($warehouse['code'] ?? 0) === $resolvedCode) {
            $resolvedName = trim((string)($warehouse['name'] ?? ''));
            break;
        }
    }

    return [
        'code' => $resolvedCode,
        'name' => $resolvedName,
    ];
}

function unibagInventoryWarehouseOptionsHtml(array $warehouses, int $selectedCode): string
{
    $html = '';
    foreach ($warehouses as $warehouse) {
        $warehouseCode = (int)($warehouse['code'] ?? 0);
        $warehouseName = trim((string)($warehouse['name'] ?? ''));
        $selected = $warehouseCode === $selectedCode ? ' selected' : '';
        $html .= '<option value="' . $warehouseCode . '"' . $selected . '>'
            . h((string)$warehouseCode . ' - ' . ($warehouseName !== '' ? $warehouseName : 'Sin nombre'))
            . '</option>';
    }

    return $html;
}

function unibagInventoryCountRowsHtml(array $inventoryDraftRows, int $warehouseCode, string $warehouseName): string
{
    $html = '';
    foreach ($inventoryDraftRows as $index => $inventoryRow) {
        $systemQty = (float)($inventoryRow['system_qty'] ?? 0);
        $physicalQty = (float)($inventoryRow['physical_qty'] ?? $systemQty);
        $diffQty = $physicalQty - $systemQty;
        $fieldPrefix = 'items[' . $index . ']';
        $html .= '<tr>';
        $html .= '<td>' . h((string)($inventoryRow['sku_code'] ?? '')) . '<input type="hidden" name="' . $fieldPrefix . '[sku_code]" value="' . h((string)($inventoryRow['sku_code'] ?? '')) . '"><input type="hidden" name="' . $fieldPrefix . '[sku_description]" value="' . h((string)($inventoryRow['sku_description'] ?? '')) . '"></td>';
        $html .= '<td>' . h((string)($inventoryRow['article_code'] ?? '')) . '<input type="hidden" name="' . $fieldPrefix . '[article_code]" value="' . h((string)($inventoryRow['article_code'] ?? '')) . '"></td>';
        $html .= '<td>' . h((string)($inventoryRow['family_color'] ?? '')) . '<input type="hidden" name="' . $fieldPrefix . '[family_color]" value="' . h((string)($inventoryRow['family_color'] ?? '')) . '"></td>';
        $html .= '<td>' . h((string)($inventoryRow['color_code'] ?? '')) . '<input type="hidden" name="' . $fieldPrefix . '[color_code]" value="' . h((string)($inventoryRow['color_code'] ?? '')) . '"></td>';
        $html .= '<td>' . h((string)($inventoryRow['height_mm'] ?? '')) . '<input type="hidden" name="' . $fieldPrefix . '[height_mm]" value="' . h((string)($inventoryRow['height_mm'] ?? '')) . '"></td>';
        $html .= '<td>' . h((string)($inventoryRow['grams'] ?? '')) . '<input type="hidden" name="' . $fieldPrefix . '[grams]" value="' . h((string)($inventoryRow['grams'] ?? '')) . '"></td>';
        $html .= '<td>' . h((string)($inventoryRow['meters'] ?? '')) . '<input type="hidden" name="' . $fieldPrefix . '[meters]" value="' . h((string)($inventoryRow['meters'] ?? '')) . '"></td>';
        $html .= '<td>' . h((string)($inventoryRow['unit_code'] ?? 'BOB')) . '<input type="hidden" name="' . $fieldPrefix . '[unit_code]" value="' . h((string)($inventoryRow['unit_code'] ?? 'BOB')) . '"></td>';
        $html .= '<td>' . h((string)$warehouseCode . ' (' . ($warehouseName !== '' ? $warehouseName : 'Sin nombre') . ')') . '</td>';
        $html .= '<td>' . h(number_format($systemQty, 3, '.', '')) . '<input type="hidden" name="' . $fieldPrefix . '[system_qty]" value="' . h(number_format($systemQty, 3, '.', '')) . '"></td>';
        $html .= '<td><input data-system-qty="' . h(number_format($systemQty, 3, '.', '')) . '" data-diff-target="inventory-diff-' . $index . '" name="' . $fieldPrefix . '[physical_qty]" type="number" step="0.001" min="0" value="' . h(number_format($physicalQty, 3, '.', '')) . '" style="min-width:110px"></td>';
        $html .= '<td><span id="inventory-diff-' . $index . '">' . h(number_format($diffQty, 3, '.', '')) . '</span></td>';
        $html .= '</tr>';
    }

    if ($inventoryDraftRows === []) {
        $html .= '<tr><td colspan="12" class="muted">No hay bobinas disponibles en esta bodega para realizar la toma de inventario.</td></tr>';
    }

    return $html;
}

function unibagInventoryCountDiffScript(): string
{
    return '<script>
        (function () {
          var inputs = document.querySelectorAll("input[data-system-qty][data-diff-target]");
          function recalc(input) {
            var systemQty = Number(input.getAttribute("data-system-qty") || "0");
            var physicalQty = Number(input.value || "0");
            var diffQty = physicalQty - systemQty;
            var targetId = input.getAttribute("data-diff-target");
            var target = targetId ? document.getElementById(targetId) : null;
            if (target) target.textContent = diffQty.toFixed(3);
          }
          for (var i = 0; i < inputs.length; i++) {
            inputs[i].addEventListener("input", function () { recalc(this); });
            recalc(inputs[i]);
          }
        })();
      </script>';
}

function unibagRenderInventoryCountsPage(ReceptionService $service): void
{
    $warehouses = $service->listWarehouses();
    $requestedCode = isset($_GET['bodega']) ? (int)$_GET['bodega'] : 100;
    $stockMessage = trim((string)($_GET['msg'] ?? ''));
    $stockError = trim((string)($_GET['error'] ?? ''));
    $warehouse = unibagInventoryResolveWarehouse($warehouses, $requestedCode);
    $inventoryDraftRows = $service->inventoryCountDraftRowsByWarehouseCode($warehouse['code']);

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Toma de inventario</div>
          <div class="muted">Conteo fisico por bodega en una pantalla separada del inventario general.</div>
        </div>
        <a class="btn secondary" href="/stock">Volver a inventario</a>
      </div>';

    if ($stockMessage !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($stockMessage) . '</div>';
    }
    if ($stockError !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($stockError) . '</div>';
    }

    $body .= '<div class="card" style="margin-bottom:12px">
        <form method="get" action="/stock/inventory-counts" class="row" style="align-items:end;margin-bottom:12px">
          <div style="flex:1;min-width:260px">
            <label>Seleccionar bodega</label>
            <select name="bodega" onchange="this.form.submit()">'
            . unibagInventoryWarehouseOptionsHtml($warehouses, $warehouse['code']) .
            '</select>
          </div>
          <div class="row" style="align-items:end">
            <button class="btn secondary" type="submit">Ver bodega</button>
          </div>
        </form>
        <div class="row">
          <div style="flex:1;min-width:220px"><div class="muted">Bodega seleccionada</div><div style="font-weight:800">' . h((string)$warehouse['code']) . '</div></div>
          <div style="flex:2;min-width:280px"><div class="muted">Nombre bodega</div><div style="font-weight:800">' . h($warehouse['name'] !== '' ? $warehouse['name'] : '-') . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Lineas de conteo</div><div style="font-weight:800">' . h((string)count($inventoryDraftRows)) . '</div></div>
        </div>
      </div>';

    $body .= '<div class="card">';
    $body .= '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:8px">';
    $body .= '<div><div style="font-weight:800">Planilla de toma</div><div class="muted">Registra el conteo fisico. La diferencia se calcula automaticamente.</div></div>';
    $body .= '</div>';
    $body .= '<form method="post" action="/stock/inventory-counts">';
    $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
    $body .= '<input type="hidden" name="bodega" value="' . $warehouse['code'] . '">';
    $body .= '<div class="table-wrap"><table><thead><tr><th>Numero</th><th>Articulo</th><th>Familia</th><th>Cod. color</th><th>Alto</th><th>Gramos</th><th>Metros</th><th>Unidad</th><th>Bodega</th><th>Sistema</th><th>Fisico</th><th>Dif</th></tr></thead><tbody>';
    $body .= unibagInventoryCountRowsHtml($inventoryDraftRows, $warehouse['code'], $warehouse['name']);
    $body .= '</tbody></table></div>';
    $body .= '<div class="row" style="justify-content:flex-end;margin-top:12px"><button class="btn" type="submit"' . ($inventoryDraftRows === [] ? ' disabled' : '') . '>Guardar inventario realizado</button></div>';
    $body .= '</form>';
    $body .= unibagInventoryCountDiffScript();
    $body .= '</div>';

    render('Toma de inventario', $body);
}

function unibagRenderStockPage(ReceptionService $service): void
{
    $summary = $service->stockSummary();
    $warehouses = $service->listWarehouses();
    $requestedCode = isset($_GET['bodega']) ? (int)$_GET['bodega'] : 100;
    $selectedSku = trim((string)($_GET['sku'] ?? ''));
    $stockMessage = trim((string)($_GET['msg'] ?? ''));
    $stockError = trim((string)($_GET['error'] ?? ''));
    $warehouse = unibagInventoryResolveWarehouse($warehouses, $requestedCode);
    $code = $warehouse['code'];
    $selectedWarehouseName = $warehouse['name'];

    $rolls = $service->listRollsByWarehouseCode($code);
    $warehousePallets = $service->listPalletsByWarehouseCode($code);
    $selectedWarehouseStockUnits = 0.0;
    $selectedWarehousePalletsCount = 0;
    $selectedWarehouseAvailableRolls = 0;
    $selectedWarehouseUnavailableRolls = 0;

    foreach ($summary as $summaryRow) {
        if ((int)($summaryRow['warehouse_code'] ?? 0) === $code) {
            $selectedWarehouseStockUnits = (float)($summaryRow['stock_units_total'] ?? 0);
            $selectedWarehousePalletsCount = (int)($summaryRow['pallets_count'] ?? 0);
            $selectedWarehouseAvailableRolls = (int)($summaryRow['available_rolls_count'] ?? 0);
            $selectedWarehouseUnavailableRolls = (int)($summaryRow['unavailable_rolls_count'] ?? 0);
            break;
        }
    }

    $skuSummary = [];
    foreach ($rolls as $roll) {
        $skuCode = trim((string)($roll['sku_code'] ?? ''));
        if ($skuCode === '') {
            $skuCode = 'SIN-SKU';
        }
        if (!isset($skuSummary[$skuCode])) {
            $skuSummary[$skuCode] = [
                'summary_key' => $skuCode,
                'sku_code' => $skuCode,
                'sku_description' => trim((string)($roll['sku_description'] ?? '')),
                'count' => 0,
                'available_count' => 0,
                'unavailable_count' => 0,
                'total_weight_kg' => 0.0,
                'rolls' => [],
                'pallets' => [],
            ];
        }
        $skuSummary[$skuCode]['count']++;
        if (strtoupper(trim((string)($roll['status'] ?? ''))) === 'RECEIVED') {
            $skuSummary[$skuCode]['available_count']++;
        } else {
            $skuSummary[$skuCode]['unavailable_count']++;
        }
        $skuSummary[$skuCode]['total_weight_kg'] += (float)($roll['weight_kg'] ?? 0);
        $skuSummary[$skuCode]['rolls'][] = $roll;
    }

    foreach ($warehousePallets as $palletRow) {
        $specification = trim((string)($palletRow['final_sku'] ?? ''));
        if ($specification === '') {
            $specification = 'SIN-ESPECIFICACION';
        }
        $summaryKey = 'PALLET|' . $specification;
        if (!isset($skuSummary[$summaryKey])) {
            $skuSummary[$summaryKey] = [
                'summary_key' => $summaryKey,
                'sku_code' => 'PALLET',
                'sku_description' => $specification,
                'count' => 0,
                'available_count' => 0,
                'unavailable_count' => 0,
                'total_weight_kg' => 0.0,
                'rolls' => [],
                'pallets' => [],
            ];
        }
        $skuSummary[$summaryKey]['count']++;
        $skuSummary[$summaryKey]['available_count']++;
        $skuSummary[$summaryKey]['pallets'][] = $palletRow;
    }

    ksort($skuSummary);

    if (isset($_GET['download']) && (string)$_GET['download'] === 'excel') {
        outputInventoryExcel(
            'inventario-bodega-' . $code . '-disponible.xls',
            $service->inventoryAvailableSkuRowsByWarehouseCode($code)
        );
    }

    $selectedSkuRolls = $selectedSku !== '' && isset($skuSummary[$selectedSku])
        ? $skuSummary[$selectedSku]['rolls']
        : [];
    $selectedSkuPallets = $selectedSku !== '' && isset($skuSummary[$selectedSku])
        ? ($skuSummary[$selectedSku]['pallets'] ?? [])
        : [];
    $selectedSkuInfo = $selectedSku !== '' && isset($skuSummary[$selectedSku])
        ? $skuSummary[$selectedSku]
        : null;

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Inventario por bodega</div>
        </div>
        <a class="btn secondary" href="/">Volver</a>
      </div>';

    if ($stockMessage !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($stockMessage) . '</div>';
    }
    if ($stockError !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($stockError) . '</div>';
    }

    $body .= '<div class="card" style="margin-bottom:12px">
        <form method="get" action="/stock" class="row" style="align-items:end;margin-bottom:12px">
          <div style="flex:1;min-width:260px">
            <label>Seleccionar bodega</label>
            <select name="bodega" onchange="this.form.submit()">'
            . unibagInventoryWarehouseOptionsHtml($warehouses, $code) .
            '</select>
          </div>';
    if ($selectedSku !== '') {
        $body .= '<input type="hidden" name="sku" value="' . h($selectedSku) . '">';
    }
    $body .= '<div class="row" style="align-items:end">
            <button class="btn secondary" type="submit">Ver bodega</button>
            <a class="btn secondary" href="/stock?bodega=' . $code . '&download=excel">Descargar Excel</a>
            <a class="btn secondary" href="/stock/inventory-counts?bodega=' . $code . '">Ir a toma inventario</a>
          </div>
        </form>
        <div class="row">
          <div style="flex:1;min-width:220px"><div class="muted">Bodega seleccionada</div><div style="font-weight:800">' . h((string)$code) . '</div></div>
          <div style="flex:2;min-width:280px"><div class="muted">Nombre bodega</div><div style="font-weight:800">' . h($selectedWarehouseName !== '' ? $selectedWarehouseName : '-') . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Especificaciones</div><div style="font-weight:800">' . count($skuSummary) . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Unidades disponibles</div><div style="font-weight:800">' . h(number_format($selectedWarehouseStockUnits, 0, ',', '.')) . ' Unid.</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Bobinas disponibles</div><div style="font-weight:800">' . h((string)$selectedWarehouseAvailableRolls) . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Bloqueadas / en proceso</div><div style="font-weight:800">' . h((string)$selectedWarehouseUnavailableRolls) . '</div></div>
          <div style="flex:1;min-width:180px"><div class="muted">Pallets almacenados</div><div style="font-weight:800">' . h((string)$selectedWarehousePalletsCount) . '</div></div>
        </div>
      </div>';

    $body .= '<div class="card">
        <div class="row" style="justify-content:space-between;align-items:center;margin-bottom:8px">
          <div style="font-weight:800">Productos por especificación</div>
          <div class="muted">Haz clic en un producto para ver sus bobinas y pallets, separando lo disponible de lo bloqueado o en proceso.</div>
        </div>
        <table><thead><tr>
            <th>Especificación</th><th>Código SKU</th><th>IDs en bodega</th><th>Disponibles</th><th>Bloqueadas / proceso</th><th>Peso total (Kg)</th><th></th>
          </tr></thead><tbody>';
    foreach ($skuSummary as $skuRow) {
        $skuLink = '/stock?bodega=' . $code . '&sku=' . rawurlencode((string)($skuRow['summary_key'] ?? $skuRow['sku_code']));
        $detailLabel = ($skuRow['pallets'] ?? []) !== [] && ($skuRow['rolls'] ?? []) === [] ? 'Ver pallets' : 'Ver detalle';
        $body .= '<tr>';
        $body .= '<td><a href="' . h($skuLink) . '">' . h((string)($skuRow['sku_description'] !== '' ? $skuRow['sku_description'] : '-')) . '</a></td>';
        $body .= '<td>' . h((string)$skuRow['sku_code']) . '</td>';
        $body .= '<td>' . h((string)$skuRow['count']) . '</td>';
        $body .= '<td>' . h((string)$skuRow['available_count']) . '</td>';
        $body .= '<td>' . h((string)$skuRow['unavailable_count']) . '</td>';
        $body .= '<td>' . h(number_format((float)$skuRow['total_weight_kg'], 3, '.', '')) . '</td>';
        $body .= '<td><a class="btn secondary" href="' . h($skuLink) . '">' . h($detailLabel) . '</a></td>';
        $body .= '</tr>';
    }
    if ($skuSummary === []) {
        $body .= '<tr><td colspan="7" class="muted">Sin productos disponibles en esta bodega.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    if ($selectedSkuInfo !== null) {
        $closeLink = '/stock?bodega=' . $code;
        $body .= '<div id="stock_ids_modal" style="position:fixed;inset:0;z-index:9998;padding:40px 20px;overflow:auto">
            <a href="' . h($closeLink) . '" aria-label="Cerrar ventana" style="position:fixed;inset:0;background:rgba(15,23,42,.45);display:block"></a>
            <div class="card" style="width:min(1100px,100%);margin:0 auto;position:relative;z-index:9999">
              <div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
                <div>
                  <div style="font-size:18px;font-weight:700">Detalle por especificación</div>
                  <div class="muted">Código SKU ' . h((string)$selectedSkuInfo['sku_code']) . ' · Especificación '
                    . h((string)($selectedSkuInfo['sku_description'] !== '' ? $selectedSkuInfo['sku_description'] : '-')) . '</div>
                </div>
                <a class="btn secondary" href="' . h($closeLink) . '">Cerrar</a>
              </div>
              <div style="font-weight:800;margin-bottom:8px">Bobinas en bodega</div>
              <table><thead><tr>
                  <th>ID</th><th>Código</th><th>Recibió</th><th>OT activa</th><th>Peso (Kg)</th><th>Estado</th><th>Disponibilidad</th><th></th>
                </tr></thead><tbody>';
        foreach ($selectedSkuRolls as $roll) {
            $isAvailable = strtoupper(trim((string)($roll['status'] ?? ''))) === 'RECEIVED';
            $rollDetailUrl = withQuery('/rolls/' . (int)$roll['id'], inventoryNavigationParams($code));
            $body .= '<tr>';
            $body .= '<td><a href="' . h($rollDetailUrl) . '">' . (int)$roll['id'] . '</a></td>';
            $body .= '<td>' . h((string)$roll['roll_code']) . '</td>';
            $body .= '<td>' . h((string)($roll['received_by'] ?? '-')) . '</td>';
            $body .= '<td>' . h((string)($roll['work_order_code'] ?? '-')) . '</td>';
            $body .= '<td>' . h((string)$roll['weight_kg']) . '</td>';
            $body .= '<td>' . h(rollStatusLabel((string)$roll['status'])) . '</td>';
            $body .= '<td>' . h($isAvailable ? 'Disponible' : 'No disponible') . '</td>';
            $body .= '<td><a class="btn secondary" href="' . h($rollDetailUrl) . '">Trazabilidad</a></td>';
            $body .= '</tr>';
        }
        if ($selectedSkuRolls === []) {
            $body .= '<tr><td colspan="8" class="muted">No hay IDs registrados para este producto en esta bodega.</td></tr>';
        }
        $body .= '</tbody></table>';
        $body .= '<div style="font-weight:800;margin:16px 0 8px">Pallets almacenados</div>';
        $body .= '<table><thead><tr><th>Pallet</th><th>OT</th><th>Bobina origen</th><th>SKU final</th><th>Cajas</th><th>Unidades</th><th></th></tr></thead><tbody>';
        foreach ($selectedSkuPallets as $palletRow) {
            $body .= '<tr>';
            $body .= '<td>' . h((string)$palletRow['pallet_code']) . '</td>';
            $body .= '<td>' . h((string)($palletRow['ot_code'] ?? '-')) . '</td>';
            $body .= '<td>' . h((string)($palletRow['source_roll_code'] ?? '-')) . '</td>';
            $body .= '<td>' . h((string)($palletRow['final_sku'] ?? '-')) . '</td>';
            $body .= '<td>' . h((string)($palletRow['box_count'] ?? '0')) . '</td>';
            $body .= '<td>' . h(number_format((float)($palletRow['units_total'] ?? 0), 3, '.', '')) . '</td>';
            $body .= '<td><a class="btn secondary" href="' . h(withQuery('/pallets/' . (int)$palletRow['id'], inventoryNavigationParams($code))) . '">Ver pallet</a></td>';
            $body .= '</tr>';
        }
        if ($selectedSkuPallets === []) {
            $body .= '<tr><td colspan="7" class="muted">No hay pallets almacenados para esta especificación.</td></tr>';
        }
        $body .= '</tbody></table></div></div>';
        $body .= '<script>
          (function () {
            var modal = document.getElementById("stock_ids_modal");
            if (!modal) return;
            document.addEventListener("keydown", function (event) {
              if (event.key === "Escape") {
                window.location.href = ' . json_encode($closeLink, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';
              }
            });
          })();
        </script>';
    }

    render('Stock', $body);
}

function handleInventoryRoutes(string $path, string $method, ReceptionService $service, string $currentOperatorName): bool
{
    if ($path === '/stock/inventory-counts' && $method === 'POST') {
        requireCsrf();
        $warehouseCode = (int)($_POST['bodega'] ?? 0);
        $warehouses = $service->listWarehouses();
        $selectedWarehouse = null;
        foreach ($warehouses as $warehouse) {
            if ((int)($warehouse['code'] ?? 0) === $warehouseCode) {
                $selectedWarehouse = $warehouse;
                break;
            }
        }
        if ($selectedWarehouse === null) {
            redirectResponse('/stock?error=' . rawurlencode('La bodega seleccionada no existe.'));
        }

        $postedItems = is_array($_POST['items'] ?? null) ? $_POST['items'] : [];
        $inventoryRows = [];
        foreach ($postedItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $systemQty = (float)($item['system_qty'] ?? 0);
            $physicalQtyRaw = trim((string)($item['physical_qty'] ?? '0'));
            if ($physicalQtyRaw === '') {
                $physicalQtyRaw = '0';
            }
            $physicalQty = (float)$physicalQtyRaw;
            $inventoryRows[] = [
                'sku_code' => (string)($item['sku_code'] ?? ''),
                'sku_description' => (string)($item['sku_description'] ?? ''),
                'article_code' => (string)($item['article_code'] ?? ''),
                'family_color' => (string)($item['family_color'] ?? ''),
                'color_code' => (string)($item['color_code'] ?? ''),
                'height_mm' => $item['height_mm'] ?? null,
                'grams' => $item['grams'] ?? null,
                'meters' => $item['meters'] ?? null,
                'unit_code' => (string)($item['unit_code'] ?? 'BOB'),
                'system_qty' => $systemQty,
                'physical_qty' => $physicalQty,
                'diff_qty' => $physicalQty - $systemQty,
            ];
        }
        if ($inventoryRows === []) {
            redirectResponse('/stock/inventory-counts?bodega=' . $warehouseCode . '&error=' . rawurlencode('No hay lineas para registrar en el inventario.'));
        }

        $result = $service->createInventoryCount(
            $warehouseCode,
            trim((string)($selectedWarehouse['name'] ?? '')),
            $currentOperatorName,
            $inventoryRows
        );
        if (!$result['ok']) {
            redirectResponse('/stock/inventory-counts?bodega=' . $warehouseCode . '&error=' . rawurlencode((string)reset($result['errors'])));
        }

        redirectResponse(
            '/stock/inventory-counts?bodega=' . $warehouseCode . '&msg=' . rawurlencode('Inventario registrado correctamente. También quedó disponible en ERP > Informes.')
        );
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
