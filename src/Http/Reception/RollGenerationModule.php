<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Generación, Consulta y Movimiento de Bobinas (src/Http/Reception/RollGenerationModule.php)
// =============================================================================

function handleRollGenerationRoutes(string $path, string $method, ReceptionService $service, PrintService $printer, string $currentOperatorName): bool
{
if ($path === '/rolls/new' && $method === 'GET') {
    $warehouses = $service->listWarehousesForReception();
    $skus = $service->listSkus();

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Nueva recepción</div>
        <a class="btn secondary" href="/">Volver</a>
      </div>';

    $body .= '<div class="grid" style="grid-template-columns: 1fr">
        <div class="card">
          <form method="post" action="/rolls">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <div class="row">
              <div style="flex:1;min-width:220px">
                <label>SKU interno</label>
                <select name="sku_id" required>
                  <option value="">Seleccionar</option>';
    foreach ($skus as $s) {
        $body .= '<option value="' . (int)$s['id'] . '">' . h((string)$s['code']) . ' - ' . h((string)$s['description']) . '</option>';
    }
    $body .= '</select>
              </div>
              <div style="flex:1;min-width:220px">
                <label>Bodega (100/200)</label>
                <select name="warehouse_id" required>
                  <option value="">Seleccionar</option>';
    foreach ($warehouses as $w) {
        $body .= '<option value="' . (int)$w['id'] . '">' . h((string)$w['code']) . ' - ' . h((string)$w['name']) . '</option>';
    }
    $body .= '</select>
              </div>
            </div>

            <div class="row" style="margin-top:10px;align-items:end">
              <div style="flex:1;min-width:220px">
                <label>Peso real (Kg)</label>
                <input id="weight_kg" name="weight_kg" type="number" step="0.001" min="0" required>
              </div>
              <div style="min-width:220px">
                <button class="btn secondary" type="button" id="read_scale">Leer balanza</button>
              </div>
            </div>

            <div class="card" style="margin-top:12px;background:#f8fafc">
              <div style="font-weight:800;margin-bottom:6px">Especificaciones (desde ERP)</div>
              <div class="row">
                <div style="flex:1;min-width:160px">
                  <label>Gramos</label>
                  <input id="microns" name="microns" type="number" step="1" min="0" disabled>
                </div>
                <div style="flex:1;min-width:160px">
                  <label>Ancho (mm)</label>
                  <input id="width_mm" name="width_mm" type="number" step="1" min="0" disabled>
                </div>
                <div style="flex:1;min-width:220px">
                  <label>Color</label>
                  <input id="color" name="color" type="text" maxlength="60" disabled>
                </div>
                <div style="flex:1;min-width:220px">
                  <label>Metros lineales</label>
                  <input id="meters" name="meters" type="number" step="0.01" min="0" disabled>
                </div>
              </div>
              <div class="muted" style="margin-top:6px">Modo prueba: habilitar edición de especificaciones (solo para pruebas mientras no se integre el ERP).</div>
              <div style="margin-top:6px">
                <label style="display:flex;gap:8px;align-items:center">
                  <input id="toggle_specs" type="checkbox" style="width:auto">
                  <span>Editar especificaciones</span>
                </label>
              </div>
            </div>

            <div style="margin-top:12px">
              <button class="btn" type="submit">Guardar recepción</button>
            </div>
          </form>
          <script>
            (function () {
              var btn = document.getElementById("read_scale");
              var weight = document.getElementById("weight_kg");
              var toggle = document.getElementById("toggle_specs");
              var specs = ["microns","width_mm","color","meters"].map(function (id) { return document.getElementById(id); });

              function setSpecsEnabled(on) {
                specs.forEach(function (el) { if (!el) return; el.disabled = !on; });
              }

              if (toggle) {
                toggle.addEventListener("change", function () { setSpecsEnabled(!!toggle.checked); });
                setSpecsEnabled(false);
              }

              if (btn) {
                btn.addEventListener("click", async function () {
                  btn.disabled = true;
                  btn.textContent = "Leyendo...";
                  try {
                    // Lectura con estabilidad: varias lecturas iguales, estables y > 0 (no se acepta peso en movimiento).
                    var data = await (window.UnibagHardware ? window.UnibagHardware.readStableWeight() : fetch("/api/scale/weight", { cache: "no-store" }).then(function (r) { return r.json(); }));
                    if (!data || data.ok !== true) {
                      throw new Error((data && data.error) ? data.error : "No se pudo leer la balanza.");
                    }
                    weight.value = data.weight_kg;
                  } catch (e) {
                    alert(e && e.message ? e.message : "Error leyendo la balanza.");
                  } finally {
                    btn.disabled = false;
                    btn.textContent = "Leer balanza";
                  }
                });
              }
            })();
          </script>
        </div>
      </div>';

    render('Nueva recepción', $body);
    exit;
}

if ($path === '/rolls' && $method === 'POST') {
    requireCsrf();
    $postData = $_POST;
    $postData['operator_name'] = $currentOperatorName;
    $result = $service->createRoll($postData);
    if ($result['ok'] === true) {
        header('Location: /rolls/' . (int)$result['id']);
        exit;
    }

    $errors = $result['errors'];
    $warehouses = $service->listWarehousesForReception();
    $skus = $service->listSkus();

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Nueva recepción</div>
        <a class="btn secondary" href="/">Volver</a>
      </div>';

    $body .= '<div class="err" style="margin-bottom:12px"><div style="font-weight:700;margin-bottom:6px">Revisar datos</div><ul style="margin:0;padding-left:16px">';
    foreach ($errors as $msg) {
        $body .= '<li>' . h((string)$msg) . '</li>';
    }
    $body .= '</ul></div>';

    $body .= '<div class="card"><form method="post" action="/rolls">
        <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
        <div class="row">
          <div style="flex:1;min-width:220px">
            <label>SKU interno</label>
            <select name="sku_id" required>
              <option value="">Seleccionar</option>';
    foreach ($skus as $s) {
        $selected = ((string)($_POST['sku_id'] ?? '') === (string)$s['id']) ? ' selected' : '';
        $body .= '<option value="' . (int)$s['id'] . '"' . $selected . '>' . h((string)$s['code']) . ' - ' . h((string)$s['description']) . '</option>';
    }
    $body .= '</select>
          </div>
          <div style="flex:1;min-width:220px">
            <label>Bodega (100/200)</label>
            <select name="warehouse_id" required>
              <option value="">Seleccionar</option>';
    foreach ($warehouses as $w) {
        $selected = ((string)($_POST['warehouse_id'] ?? '') === (string)$w['id']) ? ' selected' : '';
        $body .= '<option value="' . (int)$w['id'] . '"' . $selected . '>' . h((string)$w['code']) . ' - ' . h((string)$w['name']) . '</option>';
    }
    $body .= '</select>
          </div>
        </div>

        <div class="row" style="margin-top:10px">
          <div style="flex:1;min-width:220px">
            <label>Peso real (Kg)</label>
            <input name="weight_kg" type="number" step="0.001" min="0" required value="' . h((string)($_POST['weight_kg'] ?? '')) . '">
          </div>
        </div>

        <div class="card" style="margin-top:12px;background:#f8fafc">
          <div style="font-weight:800;margin-bottom:6px">Especificaciones (opcional, modo prueba)</div>
          <div class="row">
            <div style="flex:1;min-width:160px">
              <label>Gramos</label>
              <input name="microns" type="number" step="1" min="0" value="' . h((string)($_POST['microns'] ?? '')) . '">
            </div>
            <div style="flex:1;min-width:160px">
              <label>Ancho (mm)</label>
              <input name="width_mm" type="number" step="1" min="0" value="' . h((string)($_POST['width_mm'] ?? '')) . '">
            </div>
            <div style="flex:1;min-width:220px">
              <label>Color</label>
              <input name="color" type="text" maxlength="60" value="' . h((string)($_POST['color'] ?? '')) . '">
            </div>
            <div style="flex:1;min-width:220px">
              <label>Metros lineales</label>
              <input name="meters" type="number" step="0.01" min="0" value="' . h((string)($_POST['meters'] ?? '')) . '">
            </div>
          </div>
        </div>

        <div style="margin-top:12px">
          <button class="btn" type="submit">Guardar recepción</button>
        </div>
      </form></div>';

    render('Nueva recepción', $body);
    exit;
}

if (preg_match('#^/rolls/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $id = (int)$m[1];
    $roll = $service->getRoll($id);
    if ($roll === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la bobina.</div>');
        exit;
    }
    $traceability = $service->listRollTraceability($id);
    $operationalTraceability = $service->listRollOperationalTraceability($id);
    $childRolls = $service->listChildRollsByParentRoll($id);
    $boxesFromRoll = $service->listBoxesBySourceRoll($id);
    $palletsFromRoll = $service->listPalletsBySourceRoll($id);
    $sourceWorkOrder = (int)($roll['source_work_order_id'] ?? 0) > 0 ? $service->getWorkOrder((int)$roll['source_work_order_id']) : null;
    $transferAvailability = rollTransferAvailability($roll);
    $rollNavParams = inventoryNavigationContext() ? inventoryNavigationParams() : [];
    $rollBackUrl = inventoryNavigationContext() ? inventoryReturnUrl() : ('/stock?bodega=' . (int)$roll['warehouse_code']);
    $canDeleteReceivedRoll = (string)($roll['status'] ?? '') === 'RECEIVED'
        && (int)($roll['current_work_order_id'] ?? 0) <= 0
        && $childRolls === []
        && $boxesFromRoll === []
        && $palletsFromRoll === [];
    $deleteRedirectUrl = '/reception';
    if ((int)($roll['import_container_id'] ?? 0) > 0 && (int)($roll['import_container_item_id'] ?? 0) > 0) {
        $deleteRedirectUrl = '/import-containers/' . (int)$roll['import_container_id'] . '/receive?line=' . (int)$roll['import_container_item_id'];
    } elseif ((int)($roll['purchase_order_id'] ?? 0) > 0 && (int)($roll['purchase_order_line_id'] ?? 0) > 0) {
        $deleteRedirectUrl = '/purchase-orders/' . (int)$roll['purchase_order_id'] . '/receive?line=' . (int)$roll['purchase_order_line_id'];
    }

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Recepción #' . (int)$roll['id'] . '</div>
        <div class="row">
          <a class="btn secondary" href="' . h($rollBackUrl) . '">' . h(inventoryNavigationContext() ? 'Volver a inventario' : 'Volver') . '</a>
          ' . ($transferAvailability['can_transfer']
            ? '<a class="btn secondary" href="' . h(withQuery('/rolls/' . (int)$roll['id'] . '/transfer', $rollNavParams)) . '">Transferir</a>'
            : '<span class="btn secondary" style="opacity:.55;cursor:not-allowed" title="' . h((string)($transferAvailability['warehouse']['reason'] ?: $transferAvailability['work_order']['reason'])) . '">Transferir</span>') . '
          ' . ($canDeleteReceivedRoll
            ? '<form method="post" action="/rolls/' . (int)$roll['id'] . '/delete" onsubmit="return confirm(\'¿Borrar esta bobina recepcionada? Esta acción quitará su registro y permitirá recepcionarla nuevamente si hubo un error.\');" style="display:inline-flex;margin:0">
                <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
                <input type="hidden" name="redirect_to" value="' . h($deleteRedirectUrl) . '">
                <button class="btn" type="submit" style="background:#fee2e2;border-color:#fca5a5;color:#b91c1c">Borrar recepción</button>
              </form>'
            : '') . '
          <a class="btn" href="/rolls/' . (int)$roll['id'] . '/label">Etiqueta</a>
        </div>
      </div>';

    $body .= '<div class="card">
        <div class="row">
          <div style="flex:1;min-width:240px">
            <div class="muted">Código bobina</div>
            <div style="font-size:20px;font-weight:800">' . h((string)$roll['roll_code']) . '</div>
          </div>
          <div style="flex:1;min-width:240px">
            <div class="muted">Bodega</div>
            <div style="font-weight:700">' . h((string)$roll['warehouse_code']) . ' - ' . h((string)$roll['warehouse_name']) . '</div>
          </div>
          <div style="flex:1;min-width:240px">
            <div class="muted">Especificación</div>
            <div style="font-weight:700">' . h((string)$roll['sku_description']) . '</div>
          </div>
          <div style="flex:1;min-width:240px">
            <div class="muted">Código SKU</div>
            <div style="font-weight:700">' . h((string)$roll['sku_code']) . '</div>
          </div>
        </div>

        <div class="row" style="margin-top:10px">
          <div style="flex:1;min-width:160px"><div class="muted">Peso (Kg)</div><div style="font-weight:700">' . h((string)$roll['weight_kg']) . '</div></div>
          <div style="flex:1;min-width:160px"><div class="muted">Cantidad</div><div style="font-weight:700">' . h(formatReceptionValue((float)($roll['received_qty'] ?? 1), 'Unid.')) . '</div></div>
          <div style="flex:1;min-width:160px"><div class="muted">Gramos</div><div style="font-weight:700">' . h((string)($roll['grams'] ?? '')) . '</div></div>
          <div style="flex:1;min-width:160px"><div class="muted">Ancho (mm)</div><div style="font-weight:700">' . h((string)$roll['width_mm']) . '</div></div>
          <div style="flex:1;min-width:160px"><div class="muted">Color</div><div style="font-weight:700">' . h((string)$roll['color']) . '</div></div>
          <div style="flex:1;min-width:160px"><div class="muted">Metros</div><div style="font-weight:700">' . h((string)$roll['meters']) . '</div></div>
        </div>

        <div class="row" style="margin-top:10px">
          <div style="flex:1;min-width:240px"><div class="muted">Estado</div><div style="font-weight:700">' . h(rollStatusLabel((string)$roll['status'])) . '</div></div>
          <div style="flex:1;min-width:240px"><div class="muted">Fecha</div><div style="font-weight:700">' . h((string)$roll['created_at']) . '</div></div>
          <div style="flex:1;min-width:240px"><div class="muted">Contenedor</div><div style="font-weight:700">' . h(trim((string)($roll['container_code'] ?? '')) !== '' ? (string)$roll['container_code'] : '-') . '</div></div>
          <div style="flex:1;min-width:240px"><div class="muted">OT activa</div><div style="font-weight:700">' . h((string)($roll['work_order_code'] ?? '-')) . '</div></div>
        </div>
      </div>';

    $body .= '<div class="card" style="margin-top:12px">
        <div style="font-weight:800;margin-bottom:8px">Cadena de fabricación</div>
        <div class="row">
          <div style="flex:1;min-width:220px"><div class="muted">OT origen</div><div style="font-weight:700">';
    if (is_array($sourceWorkOrder)) {
        $sourceOtLabel = (string)$sourceWorkOrder['ot_code'];
        $body .= canAccessWorkOrderTraceability()
            ? '<a href="' . h(withQuery('/work-orders/' . (int)$sourceWorkOrder['id'] . '/traceability', $rollNavParams)) . '">' . h($sourceOtLabel) . '</a>'
            : h($sourceOtLabel);
    } else {
        $body .= '-';
    }
    $body .= '</div></div>
          <div style="flex:1;min-width:220px"><div class="muted">Bobina entrada</div><div style="font-weight:700">';
    if ((int)($roll['parent_roll_id'] ?? 0) > 0) {
        $body .= '<a href="/rolls/' . (int)$roll['parent_roll_id'] . '">' . h((string)($roll['parent_roll_code'] ?? ('#' . (int)$roll['parent_roll_id']))) . '</a>';
    } else {
        $body .= '-';
    }
    $body .= '</div></div>
          <div style="flex:1;min-width:220px"><div class="muted">Bobinas salida generadas</div><div style="font-weight:700">' . h((string)count($childRolls)) . '</div></div>
          <div style="flex:1;min-width:220px"><div class="muted">Cajas</div><div style="font-weight:700">' . h((string)count($boxesFromRoll)) . '</div></div>
          <div style="flex:1;min-width:220px"><div class="muted">Pallets</div><div style="font-weight:700">' . h((string)count($palletsFromRoll)) . '</div></div>
        </div>';
    if ($childRolls !== []) {
        $body .= '<div style="font-weight:700;margin-top:10px">Bobinas salida ligadas</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Bobina</th><th>OT</th><th>Etapa</th><th>Cajas</th><th>Pallets</th><th></th></tr></thead><tbody>';
        foreach ($childRolls as $childRoll) {
            $body .= '<tr>';
            $body .= '<td><a href="/rolls/' . (int)$childRoll['id'] . '">' . h((string)$childRoll['roll_code']) . '</a></td>';
            $body .= '<td>' . h((string)($childRoll['ot_code'] ?? '-')) . '</td>';
            $body .= '<td>' . h(rollProcessStageLabel((string)($childRoll['process_stage'] ?? '-'))) . ' / ' . h(rollStatusLabel((string)($childRoll['status'] ?? '-'))) . '</td>';
            $body .= '<td>' . h((string)($childRoll['box_count'] ?? '0')) . '</td>';
            $body .= '<td>' . h((string)($childRoll['pallet_count'] ?? '0')) . '</td>';
            $body .= '<td><a class="btn secondary" href="/rolls/' . (int)$childRoll['id'] . '">Ver</a></td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div>';
    }
    if ($boxesFromRoll !== []) {
        $body .= '<div style="font-weight:700;margin-top:10px">Cajas generadas desde esta bobina</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Caja</th><th>Unidades</th><th>Pallet</th><th></th></tr></thead><tbody>';
        foreach ($boxesFromRoll as $boxItem) {
            $body .= '<tr>';
            $body .= '<td><a href="/boxes/' . (int)$boxItem['id'] . '">' . h((string)$boxItem['box_code']) . '</a></td>';
            $body .= '<td>' . h((string)($boxItem['units_qty'] ?? '-')) . '</td>';
            $body .= '<td>' . h((string)($boxItem['pallet_code'] ?? '-')) . '</td>';
            $body .= '<td><a class="btn secondary" href="/boxes/' . (int)$boxItem['id'] . '">Ver caja</a></td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div>';
    }
    if ($palletsFromRoll !== []) {
        $body .= '<div style="font-weight:700;margin-top:10px">Pallets generados desde esta bobina</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Pallet</th><th>Cajas</th><th></th></tr></thead><tbody>';
        foreach ($palletsFromRoll as $palletItem) {
            $body .= '<tr>';
            $body .= '<td><a href="/pallets/' . (int)$palletItem['id'] . '">' . h((string)$palletItem['pallet_code']) . '</a></td>';
            $body .= '<td>' . h((string)($palletItem['box_count'] ?? '-')) . '</td>';
            $body .= '<td><a class="btn secondary" href="/pallets/' . (int)$palletItem['id'] . '">Ver pallet</a></td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div>';
    }
    $body .= '</div>';

    $body .= '<div class="card" style="margin-top:12px">
        <div style="font-weight:800;margin-bottom:8px">Trazabilidad</div>
        <table><thead><tr><th>Fecha</th><th>Movimiento</th><th>Origen</th><th>Destino</th><th>Operador</th><th>OT</th><th>Detalle</th></tr></thead><tbody>';
    $traceabilityWorkOrders = [];
    foreach ($traceability as $t) {
        $payload = $t['payload_data'] ?? [];
        $operatorName = is_array($payload) ? (string)($payload['operator_name'] ?? '-') : '-';
        $workOrderLabel = '-';
        if (is_array($payload) && isset($payload['work_order_id']) && (int)$payload['work_order_id'] > 0) {
            $traceabilityWorkOrderId = (int)$payload['work_order_id'];
            if (!isset($traceabilityWorkOrders[$traceabilityWorkOrderId])) {
                $traceabilityWorkOrders[$traceabilityWorkOrderId] = $service->getWorkOrder($traceabilityWorkOrderId);
            }
            $traceabilityWorkOrder = $traceabilityWorkOrders[$traceabilityWorkOrderId];
            $workOrderLabel = is_array($traceabilityWorkOrder)
                ? (string)($traceabilityWorkOrder['ot_code'] ?? ('OT #' . $traceabilityWorkOrderId))
                : ('OT #' . $traceabilityWorkOrderId);
        }
        $detail = [];
        if (is_array($payload) && isset($payload['weight_kg'])) { $detail[] = 'Peso ' . h((string)$payload['weight_kg']) . ' Kg'; }
        if (is_array($payload) && isset($payload['received_qty'])) { $detail[] = 'Cantidad ' . h(formatReceptionValue((float)$payload['received_qty'], 'Unid.')); }
        if (is_array($payload) && isset($payload['microns'])) { $detail[] = 'Gramos ' . h((string)$payload['microns']); }
        if (is_array($payload) && isset($payload['width_mm'])) { $detail[] = 'Ancho ' . h((string)$payload['width_mm']) . ' mm'; }
        if (is_array($payload) && isset($payload['color']) && (string)$payload['color'] !== '') { $detail[] = 'Color ' . h((string)$payload['color']); }
        if (is_array($payload) && isset($payload['meters'])) { $detail[] = 'Metros ' . h((string)$payload['meters']); }
        if ((string)($roll['container_code'] ?? '') !== '' && (string)($t['movement_type'] ?? '') === 'RECEIPT') { $detail[] = 'Contenedor ' . h((string)$roll['container_code']); }
        $fromWarehouseLabel = trim((string)($t['from_warehouse_code'] ?? '')) !== ''
            ? ((string)$t['from_warehouse_code'] . ' - ' . (string)($t['from_warehouse_name'] ?? ''))
            : '-';
        $toWarehouseLabel = trim((string)($t['to_warehouse_code'] ?? '')) !== ''
            ? ((string)$t['to_warehouse_code'] . ' - ' . (string)($t['to_warehouse_name'] ?? ''))
            : '-';
        $body .= '<tr>';
        $body .= '<td>' . h((string)$t['created_at']) . '</td>';
        $body .= '<td>' . h(movementTypeLabel((string)$t['movement_type'])) . '</td>';
        $body .= '<td>' . h($fromWarehouseLabel) . '</td>';
        $body .= '<td>' . h($toWarehouseLabel) . '</td>';
        $body .= '<td>' . h($operatorName) . '</td>';
        $body .= '<td>' . h($workOrderLabel) . '</td>';
        $body .= '<td>' . ($detail === [] ? '-' : implode(' · ', $detail)) . '</td>';
        $body .= '</tr>';
    }
    if ($traceability === []) {
        $body .= '<tr><td colspan="7" class="muted">Sin trazabilidad registrada.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    $body .= '<div class="card" style="margin-top:12px">
        <div style="font-weight:800;margin-bottom:8px">Trazabilidad operativa OT</div>
        <table><thead><tr><th>Fecha</th><th>Evento</th><th>OT</th><th>Detalle</th></tr></thead><tbody>';
    foreach ($operationalTraceability as $event) {
        $payload = $event['payload_data'] ?? [];
        $detail = [];
        if ((string)$event['type'] === 'WORK_ORDER_ROLL_ATTACHED') {
            $detail[] = 'Peso proceso ' . (string)($payload['process_weight_kg'] ?? '0') . ' Kg';
        }
        if ((string)$event['type'] === 'WORK_ORDER_ROLL_RELEASED') {
            $detail[] = 'Peso final ' . (string)($payload['final_weight_kg'] ?? '0') . ' Kg';
            $detail[] = 'Motivo ' . (string)($payload['reason'] ?? '-');
        }
        if ((string)$event['type'] === 'WORK_ORDER_FINISHED') {
            $detail[] = 'Cierre OT';
        }
        if (isset($payload['operator_name'])) {
            $detail[] = 'Operador ' . (string)$payload['operator_name'];
        }
        $body .= '<tr>';
        $body .= '<td>' . h((string)$event['created_at']) . '</td>';
        $body .= '<td>' . h(eventTypeLabel((string)$event['type'])) . '</td>';
        $body .= '<td>' . h((string)($event['work_order_label'] ?? '-')) . '</td>';
        $body .= '<td>' . h($detail === [] ? '-' : implode(' · ', $detail)) . '</td>';
        $body .= '</tr>';
    }
    if ($operationalTraceability === []) {
        $body .= '<tr><td colspan="4" class="muted">Sin trazabilidad operativa en OT para esta bobina.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    render('Detalle recepción', $body);
    exit;
}

if (preg_match('#^/rolls/(\d+)/transfer$#', $path, $m) === 1 && $method === 'GET') {
    $id = (int)$m[1];
    $roll = $service->getRoll($id);
    $transferWorkOrders = $service->listWorkOrdersForTransfer();
    if ($roll === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la bobina.</div>');
        exit;
    }

    $warehouses = $service->listWarehouses();
    $transferAvailability = rollTransferAvailability($roll);
    $warehouseTransferEnabled = (bool)$transferAvailability['warehouse']['allowed'];
    $workOrderTransferEnabled = (bool)$transferAvailability['work_order']['allowed'] && $transferWorkOrders !== [];
    $workOrderTransferReason = !$transferAvailability['work_order']['allowed']
        ? (string)$transferAvailability['work_order']['reason']
        : ($transferWorkOrders === [] ? 'No hay OTs disponibles para recibir bobinas.' : '');
    $selectedTransferMode = $warehouseTransferEnabled ? 'warehouse' : ($workOrderTransferEnabled ? 'work_order' : 'warehouse');
    $canSubmitTransfer = $warehouseTransferEnabled || $workOrderTransferEnabled;
    $transferNavParams = inventoryNavigationContext() ? inventoryNavigationParams() : [];

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Transferir bobina</div>
        <a class="btn secondary" href="' . h(withQuery('/rolls/' . (int)$roll['id'], $transferNavParams)) . '">Volver</a>
      </div>';

    $body .= '<div class="card">
        <div class="muted">Bobina</div>
        <div style="font-size:18px;font-weight:800;margin-bottom:10px">' . h((string)$roll['roll_code']) . '</div>';
    if (!$canSubmitTransfer) {
        $body .= '<div class="err" style="margin-bottom:12px">' . h((string)($transferAvailability['warehouse']['reason'] ?: $workOrderTransferReason)) . '</div>';
    }
    $body .= '<form method="post" action="/rolls/' . (int)$roll['id'] . '/transfer">
          <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
          <label>Operador</label>
          <div class="panel" style="padding:10px 12px;font-weight:700">' . h($currentOperatorName) . '</div>
          <div style="height:10px"></div>
          <label>Tipo traspaso</label>
          <select name="transfer_mode" id="transfer_mode">
            <option value="warehouse"' . ($selectedTransferMode === 'warehouse' ? ' selected' : '') . ($warehouseTransferEnabled ? '' : ' disabled') . '>Traspaso a bodega</option>';
    if ($transferWorkOrders !== [] || !$transferAvailability['work_order']['allowed']) {
        $body .= '<option value="work_order"' . ($selectedTransferMode === 'work_order' ? ' selected' : '') . ($workOrderTransferEnabled ? '' : ' disabled') . '>Traspaso a OT</option>';
    }
    $body .= '</select>
          <div class="muted" style="margin-top:8px">' .
              (!$warehouseTransferEnabled && $selectedTransferMode === 'warehouse'
                  ? h((string)$transferAvailability['warehouse']['reason'])
                  : ($selectedTransferMode === 'work_order' && !$workOrderTransferEnabled
                      ? h($workOrderTransferReason)
                      : 'Selecciona un destino válido para esta bobina.')) .
          '</div>
          <div class="row" style="margin-top:10px">
            <div style="flex:1;min-width:220px"><div class="muted">Estado</div><div style="font-weight:800">' . h(rollStatusLabel((string)($roll['status'] ?? ''))) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">Etapa</div><div style="font-weight:800">' . h(rollProcessStageLabel((string)($roll['process_stage'] ?? 'RAW'))) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">OT actual</div><div style="font-weight:800">' . h((string)($roll['work_order_code'] ?? '-')) . '</div></div>
          </div>
          <div id="transfer_warehouse_wrap" style="margin-top:10px">
          <label>Bodega destino</label>
          <select name="to_warehouse_id"' . ($warehouseTransferEnabled ? '' : ' disabled') . '>
            <option value=\"\">Seleccionar</option>';
    foreach ($warehouses as $w) {
        $disabled = ((int)$w['code'] === (int)$roll['warehouse_code']) ? ' disabled' : '';
        $body .= '<option value="' . (int)$w['id'] . '"' . $disabled . '>' . h((string)$w['code']) . ' - ' . h((string)$w['name']) . '</option>';
    }
    $body .= '</select></div>';
    if ($transferWorkOrders !== []) {
        $body .= '<div id="transfer_ot_wrap" style="margin-top:10px;display:none">
            <label>OT destino</label>
            <select name="work_order_id"' . ($workOrderTransferEnabled ? '' : ' disabled') . '>
              <option value="">Seleccionar</option>';
        foreach ($transferWorkOrders as $wo) {
            $statusLabel = (string)$wo['status'] === 'ACTIVE' ? ' [ACTIVA]' : '';
            $body .= '<option value="' . (int)$wo['id'] . '">' . h((string)$wo['ot_code']) . ' - ' . h((string)$wo['sku_final']) . $statusLabel . '</option>';
        }
        $body .= '</select>
          </div>';
    }
    $body .= '
          <div style="margin-top:12px">
            <button class="btn" type="submit"' . ($canSubmitTransfer ? '' : ' disabled') . '>Confirmar traspaso</button>
          </div>
          <script>
            (function () {
              var mode = document.getElementById("transfer_mode");
              var whWrap = document.getElementById("transfer_warehouse_wrap");
              var otWrap = document.getElementById("transfer_ot_wrap");
              if (!mode) return;
              function syncMode() {
                var isOt = mode.value === "work_order";
                if (whWrap) whWrap.style.display = isOt ? "none" : "";
                if (otWrap) otWrap.style.display = isOt ? "" : "none";
              }
              mode.addEventListener("change", syncMode);
              syncMode();
            })();
          </script>
        </form>
      </div>';

    render('Transferir', $body);
    exit;
}

if (preg_match('#^/rolls/(\d+)/transfer$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $id = (int)$m[1];
    $transferMode = (string)($_POST['transfer_mode'] ?? 'warehouse');
    $toWarehouseId = $transferMode === 'warehouse' ? (int)($_POST['to_warehouse_id'] ?? 0) : 0;
    $workOrderId = $transferMode === 'work_order' ? (int)($_POST['work_order_id'] ?? 0) : null;
    $result = $service->transferRoll($id, $toWarehouseId, $currentOperatorName, $workOrderId);
    if ($result['ok'] === true) {
        header('Location: /rolls/' . $id);
        exit;
    }

    $roll = $service->getRoll($id);
    if ($roll === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la bobina.</div>');
        exit;
    }
    $warehouses = $service->listWarehouses();
    $transferWorkOrders = $service->listWorkOrdersForTransfer();
    $transferAvailability = rollTransferAvailability($roll);
    $warehouseTransferEnabled = (bool)$transferAvailability['warehouse']['allowed'];
    $workOrderTransferEnabled = (bool)$transferAvailability['work_order']['allowed'] && $transferWorkOrders !== [];
    $workOrderTransferReason = !$transferAvailability['work_order']['allowed']
        ? (string)$transferAvailability['work_order']['reason']
        : ($transferWorkOrders === [] ? 'No hay OTs disponibles para recibir bobinas.' : '');
    $selectedTransferMode = (string)($_POST['transfer_mode'] ?? ($warehouseTransferEnabled ? 'warehouse' : ($workOrderTransferEnabled ? 'work_order' : 'warehouse')));
    if ($selectedTransferMode === 'work_order' && !$workOrderTransferEnabled) {
        $selectedTransferMode = $warehouseTransferEnabled ? 'warehouse' : 'work_order';
    }
    if ($selectedTransferMode === 'warehouse' && !$warehouseTransferEnabled && $workOrderTransferEnabled) {
        $selectedTransferMode = 'work_order';
    }
    $canSubmitTransfer = $warehouseTransferEnabled || $workOrderTransferEnabled;

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Transferir bobina</div>
        <a class="btn secondary" href="/rolls/' . (int)$roll['id'] . '">Volver</a>
      </div>';
    $body .= '<div class="err" style="margin-bottom:12px"><ul style="margin:0;padding-left:16px">';
    foreach ($result['errors'] as $msg) {
        $body .= '<li>' . h((string)$msg) . '</li>';
    }
    $body .= '</ul></div>';

    $body .= '<div class="card">
        <div class="muted">Bobina</div>
        <div style="font-size:18px;font-weight:800;margin-bottom:10px">' . h((string)$roll['roll_code']) . '</div>
        <form method="post" action="/rolls/' . (int)$roll['id'] . '/transfer">
          <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
          <label>Operador</label>
          <div class="panel" style="padding:10px 12px;font-weight:700">' . h($currentOperatorName) . '</div>
          <div style="height:10px"></div>
          <label>Tipo traspaso</label>
          <select name="transfer_mode" id="transfer_mode">
            <option value="warehouse"' . ($selectedTransferMode === 'warehouse' ? ' selected' : '') . ($warehouseTransferEnabled ? '' : ' disabled') . '>Traspaso a bodega</option>';
    if ($transferWorkOrders !== [] || !$transferAvailability['work_order']['allowed']) {
        $body .= '<option value="work_order"' . ($selectedTransferMode === 'work_order' ? ' selected' : '') . ($workOrderTransferEnabled ? '' : ' disabled') . '>Traspaso a OT</option>';
    }
    $body .= '</select>
          <div class="muted" style="margin-top:8px">' .
              (!$warehouseTransferEnabled && $selectedTransferMode === 'warehouse'
                  ? h((string)$transferAvailability['warehouse']['reason'])
                  : ($selectedTransferMode === 'work_order' && !$workOrderTransferEnabled
                      ? h($workOrderTransferReason)
                      : 'Selecciona un destino válido para esta bobina.')) .
          '</div>
          <div class="row" style="margin-top:10px">
            <div style="flex:1;min-width:220px"><div class="muted">Estado</div><div style="font-weight:800">' . h(rollStatusLabel((string)($roll['status'] ?? ''))) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">Etapa</div><div style="font-weight:800">' . h(rollProcessStageLabel((string)($roll['process_stage'] ?? 'RAW'))) . '</div></div>
            <div style="flex:1;min-width:220px"><div class="muted">OT actual</div><div style="font-weight:800">' . h((string)($roll['work_order_code'] ?? '-')) . '</div></div>
          </div>
          <div id="transfer_warehouse_wrap" style="margin-top:10px">
          <label>Bodega destino</label>
          <select name="to_warehouse_id"' . ($warehouseTransferEnabled ? '' : ' disabled') . '>
            <option value=\"\">Seleccionar</option>';
    foreach ($warehouses as $w) {
        $selected = ((int)$w['id'] === (int)($_POST['to_warehouse_id'] ?? 0)) ? ' selected' : '';
        $disabled = ((int)$w['code'] === (int)$roll['warehouse_code']) ? ' disabled' : '';
        $body .= '<option value="' . (int)$w['id'] . '"' . $selected . $disabled . '>' . h((string)$w['code']) . ' - ' . h((string)$w['name']) . '</option>';
    }
    $body .= '</select></div>';
    if ($transferWorkOrders !== []) {
        $selectedWorkOrderId = (int)($_POST['work_order_id'] ?? 0);
        $body .= '<div id="transfer_ot_wrap" style="margin-top:10px;display:none">
            <label>OT destino</label>
            <select name="work_order_id"' . ($workOrderTransferEnabled ? '' : ' disabled') . '>
              <option value="">Seleccionar</option>';
        foreach ($transferWorkOrders as $wo) {
            $selected = (int)$wo['id'] === $selectedWorkOrderId ? ' selected' : '';
            $statusLabel = (string)$wo['status'] === 'ACTIVE' ? ' [ACTIVA]' : '';
            $body .= '<option value="' . (int)$wo['id'] . '"' . $selected . '>' . h((string)$wo['ot_code']) . ' - ' . h((string)$wo['sku_final']) . $statusLabel . '</option>';
        }
        $body .= '</select>
          </div>';
    }
    $body .= '
          <div style="margin-top:12px">
            <button class="btn" type="submit"' . ($canSubmitTransfer ? '' : ' disabled') . '>Confirmar traspaso</button>
          </div>
          <script>
            (function () {
              var mode = document.getElementById("transfer_mode");
              var whWrap = document.getElementById("transfer_warehouse_wrap");
              var otWrap = document.getElementById("transfer_ot_wrap");
              if (!mode) return;
              function syncMode() {
                var isOt = mode.value === "work_order";
                if (whWrap) whWrap.style.display = isOt ? "none" : "";
                if (otWrap) otWrap.style.display = isOt ? "" : "none";
              }
              mode.addEventListener("change", syncMode);
              syncMode();
            })();
          </script>
        </form>
      </div>';

    render('Transferir', $body);
    exit;
}

if (preg_match('#^/rolls/(\d+)/delete$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $rollId = (int)$m[1];
    $redirectUrl = (string)($_POST['redirect_to'] ?? '/reception');
    $parsed = parse_url($redirectUrl);
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $qParams);
        unset($qParams['saved_roll_id'], $qParams['printed']);
        $base = ($parsed['path'] ?? '/reception');
        $redirectUrl = $qParams !== [] ? ($base . '?' . http_build_query($qParams)) : $base;
    }
    $res = $service->deleteReceivedRoll($rollId);
    $delim = str_contains($redirectUrl, '?') ? '&' : '?';
    if (($res['ok'] ?? false) === true) {
        redirectResponse($redirectUrl . $delim . 'roll_deleted=' . urlencode((string)($res['roll_code'] ?? $rollId)));
    } else {
        redirectResponse($redirectUrl . $delim . 'delete_error=' . urlencode((string)($res['error'] ?? 'No se pudo eliminar la bobina')));
    }
}

if ($path === '/reception/rolls/move' && $method === 'POST') {
    requireCsrf();
    $rollIdsRaw = trim((string)($_POST['roll_ids'] ?? ''));
    $toWarehouseId = (int)($_POST['to_warehouse_id'] ?? 0);
    $redirectUrl = (string)($_POST['redirect_to'] ?? '/reception');
    $parsed = parse_url($redirectUrl);
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $qParams);
        unset($qParams['saved_roll_id'], $qParams['printed'], $qParams['roll_moved'], $qParams['move_error']);
        $base = ($parsed['path'] ?? '/reception');
        $redirectUrl = $qParams !== [] ? ($base . '?' . http_build_query($qParams)) : $base;
    }

    $rollIds = array_values(array_unique(array_filter(array_map(
        static fn(string $value): int => (int)trim($value),
        explode(',', $rollIdsRaw)
    ), static fn(int $id): bool => $id > 0)));

    if ($rollIds === []) {
        $delim = str_contains($redirectUrl, '?') ? '&' : '?';
        redirectResponse($redirectUrl . $delim . 'move_error=' . urlencode('Selecciona al menos una bobina para mover.'));
    }
    if ($toWarehouseId <= 0) {
        $delim = str_contains($redirectUrl, '?') ? '&' : '?';
        redirectResponse($redirectUrl . $delim . 'move_error=' . urlencode('Selecciona la bodega destino.'));
    }

    $moved = [];
    $errors = [];
    foreach ($rollIds as $rollId) {
        $res = $service->transferRoll($rollId, $toWarehouseId, $currentOperatorName, null);
        if (($res['ok'] ?? false) === true) {
            $roll = $service->getRoll($rollId);
            $moved[] = is_array($roll) ? (string)($roll['roll_code'] ?? ('#' . $rollId)) : ('#' . $rollId);
            continue;
        }
        $errors[] = '#' . $rollId . ': ' . implode(' ', array_map('strval', (array)($res['errors'] ?? ['No se pudo mover la bobina.'])));
    }

    $params = [];
    if ($moved !== []) {
        $params['roll_moved'] = implode(', ', $moved);
    }
    if ($errors !== []) {
        $params['move_error'] = implode(' | ', $errors);
    }
    $targetUrl = withQuery($redirectUrl, $params);
    redirectResponse($targetUrl);
}

    return false;
}
