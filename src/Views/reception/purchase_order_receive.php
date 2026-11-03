<?php
    $body = '<div class="rec-header-card" style="margin-bottom:14px">
        <div class="rec-title-group">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
            <h1>Recepcionar Producto Nacional</h1>
            <span class="rec-badge" style="background:#e0f2fe;color:#0284c7;border:1px solid #bae6fd">Orden de Compra</span>
          </div>
          <p>OC: <strong>' . h((string)$line['po_code']) . '</strong> · Proveedor: <strong>' . h((string)$line['supplier_name']) . '</strong> · Tipo: <strong>' . h(((string)($line['supplier_type'] ?? 'NATIONAL')) === 'IMPORT' ? 'Importación' : 'Nacional') . '</strong></p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <a class="btn" style="background:#0284c7;border-color:#0284c7;color:#fff;font-size:12.5px;padding:7px 15px;font-weight:700;display:inline-flex;align-items:center;gap:6px;border-radius:8px" href="' . h($previewUrl) . '&auto_print=1" target="_blank" title="Imprimir una muestra física de prueba sin registrar datos en BD">
            <span>🏷️</span> Probar Impresión de Etiqueta (Sin Guardar)
          </a>
          <button type="button" class="btn" style="background:#dc2626;border-color:#dc2626;color:#fff;font-size:12px;padding:7px 14px;font-weight:600;display:inline-flex;align-items:center;gap:6px;border-radius:8px" onclick="openCloseReceptionModal()">
            <span>🔒</span> Cerrar Recepción (Faltantes)
          </button>
          <a class="rec-btn-clear" style="padding:7px 14px;border-radius:8px" href="/purchase-orders/' . (int)$poId . '">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Volver
          </a>
        </div>
      </div>';

    if ($isReceptionLocked) {
        $body .= '<div class="card" style="background:#fff7ed;border:1px solid #fdba74;color:#9a3412;padding:12px 18px;border-radius:10px;margin-bottom:14px">
            <strong>Recepción cerrada temporalmente para agregar:</strong> esta línea está marcada como finalizada, pero puedes seguir borrando bobinas desde esta misma pantalla para corregir pruebas o ingresos erróneos.
        </div>';
    }

    $body .= renderReceptionBatchNotice();
    if ($savedRollId > 0 && empty($_GET['saved_batch'])) {
        $savedRoll = $service->getRoll($savedRollId);
        $savedRollCode = is_array($savedRoll) ? (string)($savedRoll['roll_code'] ?? ('#' . $savedRollId)) : ('#' . $savedRollId);
        $savedRollWeight = is_array($savedRoll) ? number_format((float)($savedRoll['weight_kg'] ?? 0), 3, '.', '') : '0';
        $body .= '<div class="card" style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:12px 18px;border-radius:10px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">';
        $body .= '<div><strong>✅ Bobina ' . h($savedRollCode) . ' recepcionada con éxito (' . h($savedRollWeight) . ' Kg).</strong></div>';
        $body .= '<div style="display:flex;gap:6px;align-items:center">';
        $body .= '<a class="btn" style="background:#0284c7;color:#fff;padding:6px 14px;font-size:12.5px;border-radius:6px;font-weight:700;display:inline-flex;align-items:center;gap:5px" href="/rolls/' . $savedRollId . '/label?auto_print=1" target="_blank">🖨️ Imprimir 10x7</a>';
        if ($printer->isEnabled()) {
            $body .= '<a class="btn secondary" style="padding:6px 10px;font-size:12px;border-radius:6px;font-weight:600" href="/rolls/' . $savedRollId . '/label?server_print=1' . $zebraProfileQuery . '" target="_blank" title="Imprimir directo en Zebra">⚡ Zebra</a>';
            $body .= '<a class="btn secondary" style="padding:6px 10px;font-size:12px;border-radius:6px;font-weight:600" href="/rolls/' . $savedRollId . '/label?server_print=1' . $barposProfileQuery . '" target="_blank" title="Imprimir directo en Barpos">⚡ Barpos</a>';
        }
        $body .= '<button type="button" class="btn btn-delete-roll" style="background:#fee2e2;border-color:#fca5a5;color:#b91c1c;padding:6px 12px;font-size:12px;border-radius:6px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px" data-roll-id="' . (int)$savedRollId . '" data-roll-code="' . h($savedRollCode) . '" data-roll-weight="' . h($savedRollWeight) . '" title="Borrar este pesaje si hubo un error">🗑️ Borrar este pesaje (error)</button>';
        $body .= '</div></div>';
    }

    if (isset($_GET['roll_deleted'])) {
        $body .= '<div class="card" style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 18px;border-radius:10px;margin-bottom:14px;display:flex;align-items:center;gap:10px">
            <span style="font-size:18px">🗑️</span>
            <div><strong>Pesaje eliminado:</strong> La bobina <strong>' . h((string)$_GET['roll_deleted']) . '</strong> fue eliminada y el peso ingresado se ha descontado de la recepción.</div>
        </div>';
    }

    if (isset($_GET['delete_error'])) {
        $body .= '<div class="card" style="background:#fef2f2;border:1px solid #f87171;color:#b91c1c;padding:12px 18px;border-radius:10px;margin-bottom:14px">
            <strong>❌ Error al eliminar:</strong> ' . h((string)$_GET['delete_error']) . '
        </div>';
    }

    if (isset($_GET['roll_moved'])) {
        $body .= '<div class="card" style="background:#ecfeff;border:1px solid #a5f3fc;color:#155e75;padding:12px 18px;border-radius:10px;margin-bottom:14px;display:flex;align-items:center;gap:10px">
            <span style="font-size:18px">📦</span>
            <div><strong>Bobina(s) movida(s):</strong> ' . h((string)$_GET['roll_moved']) . '</div>
        </div>';
    }

    if (isset($_GET['move_error'])) {
        $body .= '<div class="card" style="background:#fef2f2;border:1px solid #f87171;color:#b91c1c;padding:12px 18px;border-radius:10px;margin-bottom:14px">
            <strong>❌ Error al mover bobinas:</strong> ' . h((string)$_GET['move_error']) . '
        </div>';
    }

    $body .= '<div class="card" style="margin-bottom:14px;border-radius:12px;border:1px solid #e2e8f0;padding:16px 20px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.03)">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;border-bottom:1px solid #f1f5f9;padding-bottom:12px;margin-bottom:14px">
          <div>
            <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em">Artículo a Recepcionar</div>
            <div style="font-size:17px;font-weight:800;color:#0f172a;margin-top:2px">
              <span style="color:#00A9A6">' . h((string)$line['sku_code']) . '</span> — ' . h((string)$line['sku_description']) . '
            </div>
          </div>
          <div style="display:flex;gap:6px;align-items:center">
            <span class="rec-badge" style="background:#f1f5f9;color:#334155;border:1px solid #cbd5e1">Modo: ' . h(receptionModeLabel((string)$line['reception_mode'])) . '</span>
            <a class="btn secondary" style="padding:4px 10px;font-size:12px;color:#0284c7;background:#f0f9ff;border-color:#bae6fd;display:inline-flex;align-items:center;gap:4px" href="' . h($previewUrl) . '" target="_blank">
              <span>👁️</span> Ver Etiqueta 10x7
            </a>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px">
          <div style="background:#f8fafc;padding:10px 14px;border-radius:8px;border:1px solid #e2e8f0">
            <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase">Ordenado OC</div>
            <div style="font-size:15px;font-weight:800;color:#0f172a;margin-top:3px">' . formatReceptionDual($lineSummary, 'ordered') . '</div>
          </div>
          <div style="background:#ecfdf5;padding:10px 14px;border-radius:8px;border:1px solid #a7f3d0">
            <div style="font-size:11px;font-weight:700;color:#047857;text-transform:uppercase">Recibido Hasta Ahora</div>
            <div style="font-size:15px;font-weight:800;color:#065f46;margin-top:3px">' . formatReceptionDual($lineSummary, 'received') . '</div>
          </div>
          <div style="background:' . ($lineSummary['pending'] > 0 ? '#fff7ed' : '#ecfdf5') . ';padding:10px 14px;border-radius:8px;border:1px solid ' . ($lineSummary['pending'] > 0 ? '#fed7aa' : '#a7f3d0') . '">
            <div style="font-size:11px;font-weight:700;color:' . ($lineSummary['pending'] > 0 ? '#c2410c' : '#047857') . ';text-transform:uppercase">Pendiente de Ingreso</div>
            <div style="font-size:15px;font-weight:800;color:' . ($lineSummary['pending'] > 0 ? '#9a3412' : '#065f46') . ';margin-top:3px">' . formatReceptionDual($lineSummary, 'pending') . '</div>
          </div>
          <div style="background:#f8fafc;padding:10px 14px;border-radius:8px;border:1px solid #e2e8f0">
            <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase">Ficha Técnica</div>
            <div style="font-size:13px;font-weight:700;color:#334155;margin-top:3px">
              ' . h((string)($line['width_mm'] ? round((float)$line['width_mm']) . 'mm' : '-')) . ' · ' .
              h((string)($line['grams'] ? round((float)$line['grams']) . 'g' : '-')) . ' · ' .
              h((string)($line['meters'] ? number_format((float)$line['meters'], 0, ',', '.') . 'm' : '-')) . '
            </div>
          </div>
        </div>
      </div>';

    if ($isWeightMode) {
        $orderedUnits = (int)$lineSummary['ordered_rolls'];
        $receivedUnits = (int)$lineSummary['received_rolls'];
        $pendingUnits = (int)$lineSummary['pending_rolls'];
        $body .= '<div class="card" style="background:#f0fdfa;border:1px solid #ccfbf1;border-radius:12px;padding:12px 18px;margin-bottom:14px">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
              <div>
                <span style="font-weight:800;font-size:13.5px;color:#0f766e">📦 Control de Unidades / Bobinas a ingresar:</span>
                <span style="margin-left:8px;color:#334155;font-size:13px">Cada pesaje registra <strong>1 bobina/unidad</strong> con su peso real en balanza e imprime su etiqueta 10x7 cm.</span>
              </div>
              <div style="display:flex;gap:16px;font-size:13px">
                <div>Total ordenadas: <strong>' . number_format($orderedUnits, 0, ',', '.') . ' Unid.</strong></div>
                <div>Recibidas: <strong style="color:#16a34a">' . number_format($receivedUnits, 0, ',', '.') . ' Unid.</strong></div>
                <div>Pendientes: <strong style="color:' . ($pendingUnits > 0 ? '#ea580c' : '#16a34a') . '">' . number_format($pendingUnits, 0, ',', '.') . ' Unid.</strong></div>
              </div>
            </div>
        </div>';
    }

    $body .= '<div class="card" style="border-radius:12px;border:1px solid #e2e8f0;padding:18px 20px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.03)">
        <div style="font-weight:800;font-size:15px;color:#0f172a;margin-bottom:12px;display:flex;align-items:center;gap:6px">
          <span>⚖️</span> Registro de Recepción y Balanza
        </div>
        <form method="post" id="receive_form" action="/purchase-orders/' . (int)$poId . '/receive">
          <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
          <input type="hidden" name="purchase_order_line_id" value="' . (int)$lineId . '">
          <input type="hidden" name="reception_mode" id="reception_mode" value="' . h((string)$lineSummary['mode']) . '">
          <input type="hidden" id="received_qty" name="received_qty" value="1">
          <input type="hidden" id="server_print_enabled" value="' . ($printer->isEnabled() ? '1' : '0') . '">
          <div class="row" style="align-items:end;gap:12px">
            <div style="flex:1;min-width:200px">
              <label style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase">👤 Operador</label>
              <div class="panel" style="padding:9px 12px;font-weight:700;border-radius:8px;background:#f8fafc;border:1px solid #cbd5e1">' . h($currentOperatorName) . '</div>
            </div>
            <div style="flex:1;min-width:200px">
              <label style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase">⚙️ Modo recepción</label>
              <select id="reception_mode_selector"' . ($isReceptionLocked ? ' disabled' : '') . ' style="height:38px;border-radius:8px;border:1px solid #cbd5e1;background:#f8fafc;width:100%;font-weight:600">
                <option value="WEIGHT"' . ($isWeightMode ? ' selected' : '') . '>Por peso</option>
                <option value="QUANTITY"' . (!$isWeightMode ? ' selected' : '') . '>Por unidades</option>
              </select>
            </div>
            <div style="flex:1;min-width:220px">
              <label style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase">🏭 Bodega de Destino *</label>
              <select name="warehouse_id" required' . ($isReceptionLocked ? ' disabled' : '') . ' style="height:38px;border-radius:8px;border:1px solid #cbd5e1;background:#f8fafc;width:100%;font-weight:600">
                <option value="">Seleccionar bodega</option>';
    foreach ($warehouses as $w) {
        $whLabel = trim((string)$w['name']) !== '' ? (string)$w['name'] : ('Bodega ' . (string)$w['code']);
        $body .= '<option value="' . (int)$w['id'] . '" data-name="' . h($whLabel) . '">' . h($whLabel) . '</option>';
    }
    $body .= '</select>
            </div>
            <div id="weight_field_wrapper" style="flex:1;min-width:200px">
              <label id="primary_value_label" style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase">⚖️ Peso real (Kg)</label>
              <input id="weight_kg" name="weight_kg" type="number" step="0.001" min="15" value=""' . ($isReceptionLocked ? ' disabled' : '') . ' placeholder="15.000" style="height:38px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;font-size:15px;font-weight:700;color:#0f172a">
            </div>
            <div id="qty_field_wrapper" style="flex:1;min-width:200px">
              <label id="qty_value_label" style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase">🔢 Cantidad recibida (Unid.)</label>
              <input id="received_qty_input" type="number" step="0.001" min="0.001" value="1"' . ($isReceptionLocked ? ' disabled' : '') . ' style="height:38px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;font-size:14px;font-weight:700">
            </div>
            <div id="action_buttons_wrapper" style="flex:0 0 ' . ($isWeightMode ? '310px' : '200px') . ';display:flex;flex-direction:row;gap:8px;align-items:flex-end;justify-content:flex-end;flex-wrap:nowrap">';
    $body .= '<button class="btn secondary" type="button" id="toggle_scale"' . ((!$isWeightMode || $isReceptionLocked) ? ' style="display:none"' : '') . ' style="height:38px;padding:0 14px;border-radius:8px;font-weight:700">Activar balanza</button>
              <button class="btn" type="button" id="save_print"' . ((!$isWeightMode || $isReceptionLocked) ? ' style="display:none"' : '') . ' disabled style="height:38px;padding:0 16px;border-radius:8px;font-weight:800;background:#00A9A6">Guardar e imprimir</button>
              <button class="btn" type="submit" id="submit_primary"' . (($isWeightMode || $isReceptionLocked) ? ' style="display:none"' : '') . ' style="height:38px;padding:0 16px;border-radius:8px;font-weight:800;background:#00A9A6">Guardar e imprimir</button></div>
          </div>
          <div class="row" id="scale_status_row" style="margin-top:8px' . ($isWeightMode ? '' : ';display:none') . ';align-items:center">
            <div style="flex:1"></div>
            <div style="flex:2;text-align:right">
              <span class="muted" id="scale_status" style="font-size:12.5px;font-weight:600;background:#f8fafc;border:1px solid #e2e8f0;padding:4px 12px;border-radius:20px;display:inline-block">' . ($isReceptionLocked ? 'Recepción cerrada para nuevos ingresos · usa borrar para corregir' : ('Balanza: inactiva · Máximo pendiente ' . h(formatReceptionValue((float)$lineSummary['pending'], $lineUnit)) . ' ' . h($lineUnit))) . '</span>
            </div>
          </div>
        </form>
      </div>';

    $body .= '<div class="card" style="margin-top:14px;border-radius:12px;border:1px solid #e2e8f0;padding:16px 20px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.03)">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;border-bottom:1px solid #f1f5f9;padding-bottom:12px">
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span style="font-weight:800;font-size:16px;color:#0f172a">📦 Bobinas Recepcionadas en esta Línea</span>
            <span class="rec-badge" id="rec_badge_count" style="background:#0284c7;color:#fff;font-size:12px;font-weight:700;padding:3px 10px;border-radius:12px" data-total="' . $totalLineRollsCount . '">' . $totalLineRollsCount . ' bobinas</span>
            <span class="rec-badge" id="rec_badge_weight" style="background:#e0f2fe;color:#0369a1;font-size:12px;font-weight:700;padding:3px 10px;border-radius:12px">' . number_format($totalLineRollsWeight, 2, ',', '.') . ' Kg</span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <a class="btn" href="/purchase-orders/' . $poId . '/rolls-excel?line=' . (int)$lineId . '" style="background:#059669;border-color:#059669;color:#fff;font-size:12px;padding:6px 14px;border-radius:8px;font-weight:700;display:inline-flex;align-items:center;gap:6px" title="Exportar bobinas de esta línea a Excel">
              <span>📊</span> Exportar Excel Línea
            </a>
            <a class="btn secondary" href="/purchase-orders/' . $poId . '/rolls-excel" style="font-size:12px;padding:6px 12px;border-radius:8px;font-weight:700;display:inline-flex;align-items:center;gap:5px" title="Exportar todas las bobinas de la orden de compra">
              <span>📋</span> Toda la OC
            </a>
          </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;flex:1;max-width:600px">
            <input type="text" id="rec_live_search" placeholder="🔍 Buscar por código bobina, trazabilidad o bodega..." oninput="filterRecLiveTable()" style="height:34px;border-radius:8px;border:1px solid #cbd5e1;padding:0 12px;font-size:12.5px;flex:1;min-width:240px;background:#f8fafc">
            <div style="display:inline-flex;border-radius:8px;border:1px solid #cbd5e1;overflow:hidden">
              <button type="button" id="btn_rec_all" onclick="toggleRecView(\'all\')" style="padding:6px 12px;font-size:12px;font-weight:700;background:#0284c7;color:#fff;border:none;cursor:pointer">Todas (' . $totalLineRollsCount . ')</button>
              <button type="button" id="btn_rec_recent" onclick="toggleRecView(\'recent\')" style="padding:6px 12px;font-size:12px;font-weight:600;background:#f8fafc;color:#475569;border:none;cursor:pointer">Últimas 10</button>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <select id="move_selected_warehouse" style="height:34px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;font-size:12px;font-weight:600;min-width:180px">
              <option value="">Mover a bodega...</option>';
    foreach ($warehouses as $w) {
        $moveWhLabel = trim((string)$w['name']) !== '' ? (string)$w['name'] : ('Bodega ' . (string)$w['code']);
        $body .= '<option value="' . (int)$w['id'] . '">' . h($moveWhLabel) . '</option>';
    }
    $body .= '</select>
            <button type="button" id="move_selected_rolls" class="btn secondary" style="font-size:12px;padding:6px 12px;border-radius:8px;font-weight:700">📦 Mover seleccionadas</button>
            <button type="button" id="delete_selected_rolls" class="btn" style="background:#fee2e2;border-color:#fca5a5;color:#b91c1c;font-size:12px;padding:6px 12px;border-radius:8px;font-weight:700">🗑️ Borrar seleccionadas</button>
          </div>
        </div>

        <div class="rec-table-wrap">
        <table class="rec-table">
          <thead>
            <tr>
              <th style="width:38px"><input type="checkbox" id="select_all_rolls" title="Seleccionar todas"></th>
              <th>Código Bobina</th>
              <th>Cód. Trazabilidad</th>
              <th>Bodega</th>
              <th>Peso Real (Kg)</th>
              <th>Medidas / SKU</th>
              <th>Fecha Registro</th>
              <th>Operador</th>
              <th style="text-align:right">Acciones</th>
            </tr>
          </thead>
          <tbody id="recent_tbody">';

    $idx = 0;
    foreach ($recent as $r) {
        $idx++;
        $recentWeight = formatReceptionValue((float)($r['weight_kg'] ?? 0), 'Kg');
        $whDisplayName = trim((string)($r['warehouse_name'] ?? '')) ?: (string)($r['warehouse_code'] ?? '-');
        $rollWeightStr = number_format((float)($r['weight_kg'] ?? 0), 3, '.', '');
        $rollCodeStr = (string)($r['roll_code'] ?? ('#' . $r['id']));
        $trzCode = (string)($r['traceability_code'] ?: $rollCodeStr);
        $specParts = [];
        if (!empty($r['width_mm'])) $specParts[] = (float)$r['width_mm'] . 'mm';
        if (!empty($r['microns'])) $specParts[] = (float)$r['microns'] . 'µ';
        if (!empty($r['meters'])) $specParts[] = (float)$r['meters'] . 'm';
        $specText = !empty($specParts) ? implode(' · ', $specParts) : (string)($r['sku_code'] ?? '-');
        $opName = trim((string)($r['operator_name'] ?? '')) ?: '-';

        $body .= '<tr class="rec-row-item" data-index="' . $idx . '" data-search="' . h(strtolower($rollCodeStr . ' ' . $trzCode . ' ' . $whDisplayName . ' ' . $opName . ' ' . $specText)) . '">';
        $body .= '<td><input type="checkbox" class="recent-roll-check" value="' . (int)$r['id'] . '" data-roll-code="' . h($rollCodeStr) . '" style="width:16px;height:16px;cursor:pointer"></td>';
        $body .= '<td><span class="rec-code-link" style="font-family:monospace;font-size:13px;font-weight:700">' . h($rollCodeStr) . '</span></td>';
        $body .= '<td><a href="/stock/traceability?q=' . urlencode($trzCode) . '" target="_blank" style="font-family:monospace;font-size:12px;color:#0284c7;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:4px" title="Ver árbol de trazabilidad 360°">🌳 ' . h($trzCode) . '</a></td>';
        $body .= '<td><span class="wh-badge" style="font-size:11px;padding:3px 8px;font-weight:600" title="Código: ' . h((string)$r['warehouse_code']) . '">' . h($whDisplayName) . '</span></td>';
        $body .= '<td><strong>' . h($recentWeight) . ' Kg</strong></td>';
        $body .= '<td><span style="font-size:11.5px;color:#475569">' . h($specText) . '</span></td>';
        $body .= '<td><span style="color:#64748b;font-size:12px">' . h((string)$r['created_at']) . '</span></td>';
        $body .= '<td><span style="font-size:11.5px;color:#475569">' . h($opName) . '</span></td>';
        $body .= '<td style="text-align:right"><div style="display:flex;gap:5px;justify-content:flex-end;align-items:center">';
        $body .= '<a class="btn" style="padding:4px 10px;font-size:12px;border-radius:6px;background:#0284c7;border-color:#0284c7;color:#fff;display:inline-flex;align-items:center;gap:4px" href="/rolls/' . (int)$r['id'] . '/label?auto_print=1" target="_blank" rel="noopener">🖨️ Imprimir 10x7</a>';
        if ($printer->isEnabled()) {
            $body .= '<a class="btn secondary" style="padding:4px 8px;font-size:11.5px;border-radius:6px;display:inline-flex;align-items:center;gap:3px" href="/rolls/' . (int)$r['id'] . '/label?server_print=1' . $zebraProfileQuery . '" target="_blank" title="Imprimir en Zebra">⚡ Zebra</a>';
            $body .= '<a class="btn secondary" style="padding:4px 8px;font-size:11.5px;border-radius:6px;display:inline-flex;align-items:center;gap:3px" href="/rolls/' . (int)$r['id'] . '/label?server_print=1' . $barposProfileQuery . '" target="_blank" title="Imprimir en Barpos">⚡ Barpos</a>';
        }
        $body .= '<a class="btn secondary" style="padding:4px 8px;font-size:11.5px;border-radius:6px;display:inline-flex;align-items:center;gap:3px" href="/rolls/' . (int)$r['id'] . '/label" target="_blank" title="Ver diseño">👁️ Ver</a>';
        $body .= '<button type="button" class="btn btn-delete-roll" style="padding:4px 8px;font-size:11.5px;border-radius:6px;background:#fee2e2;border-color:#fca5a5;color:#b91c1c;display:inline-flex;align-items:center;gap:3px;font-weight:700;cursor:pointer" data-roll-id="' . (int)$r['id'] . '" data-roll-code="' . h($rollCodeStr) . '" data-roll-weight="' . h($rollWeightStr) . '" title="Borrar este pesaje si hubo error">🗑️ Borrar</button>';
        $body .= '</div></td>';
        $body .= '</tr>';
    }
    if ($recent === []) {
        $body .= '<tr><td colspan="9"><div class="rec-empty" style="padding:18px"><div class="rec-empty-icon" style="font-size:24px">📦</div><div style="font-weight:700">Sin recepciones registradas aún para esta línea.</div><div style="font-size:12px">Pesa la primera bobina para registrar e imprimir automáticamente.</div></div></td></tr>';
    }
    $body .= '</tbody></table></div></div>';

    // Sección destacada de Vista Previa y Prueba de Etiqueta 10x7 cm
    $cardTitle = $hasReceivedRoll
        ? ('Última Etiqueta Generada: <span style="color:#00A9A6;font-family:monospace">' . h((string)($latestRoll['roll_code'] ?: ('#' . $activeCardRollId))) . '</span>')
        : 'Vista Previa y Prueba de Etiqueta 10x7 cm';
    $cardBadge = $hasReceivedRoll ? ('Bobina #' . $activeCardRollId) : 'Muestra sin guardar';

    $body .= '<div class="card" style="margin-top:14px;border-radius:12px;border:1px solid #e2e8f0;background:#fff;padding:16px 20px;box-shadow:0 1px 3px rgba(0,0,0,0.03)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:10px;border-bottom:1px solid #f1f5f9;padding-bottom:10px">
          <div>
            <div style="font-weight:800;font-size:15px;color:#0f172a;display:flex;align-items:center;gap:8px">
              <span style="font-size:18px">🏷️</span> ' . $cardTitle . '
              <span style="font-size:11px;font-weight:800;background:#e0f2fe;color:#0369a1;padding:2px 8px;border-radius:12px">' . h($cardBadge) . '</span>
            </div>
            <div style="font-size:12px;color:#64748b;margin-top:2px">' .
              ($hasReceivedRoll
                ? 'Etiqueta de la bobina recepcionada. Puedes re-imprimirla directo en Zebra o Barpos, o descargar el comando térmico.'
                : 'Visualiza el diseño físico o prueba la salida térmica en Zebra o Barpos sin registrar datos.') . '
            </div>
          </div>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';

    if ($hasReceivedRoll) {
        $body .= '<a class="btn" style="background:#0284c7;border-color:#0284c7;color:#fff;font-size:12px;padding:6px 14px;font-weight:700;display:inline-flex;align-items:center;gap:6px;border-radius:8px" href="/rolls/' . $activeCardRollId . '/label?auto_print=1" target="_blank">
          🖨️ Imprimir Etiqueta 10x7
        </a>';
        if ($printer->isEnabled()) {
            $body .= '<a class="btn secondary" style="font-size:12px;padding:6px 12px;border-radius:8px;display:inline-flex;align-items:center;gap:5px" href="/rolls/' . $activeCardRollId . '/label?server_print=1' . $zebraProfileQuery . '" target="_blank">
              ⚡ Zebra Directa
            </a>';
            $body .= '<a class="btn secondary" style="font-size:12px;padding:6px 12px;border-radius:8px;display:inline-flex;align-items:center;gap:5px" href="/rolls/' . $activeCardRollId . '/label?server_print=1' . $barposProfileQuery . '" target="_blank">
              ⚡ Barpos Directa
            </a>';
        }
        $body .= '<a class="btn secondary" style="font-size:12px;padding:6px 12px;border-radius:8px;display:inline-flex;align-items:center;gap:5px" href="/rolls/' . $activeCardRollId . '/label?download_zpl=1' . $zebraProfileQuery . '" download>
          <span>⬇️</span> Zebra ZPL
        </a>
        <a class="btn secondary" style="font-size:12px;padding:6px 12px;border-radius:8px;display:inline-flex;align-items:center;gap:5px" href="/rolls/' . $activeCardRollId . '/label?download_zpl=1' . $barposProfileQuery . '" download>
          <span>⬇️</span> Barpos TSPL
        </a>
        <a class="btn secondary" style="font-size:12px;padding:6px 12px;border-radius:8px;display:inline-flex;align-items:center;gap:5px" href="/rolls/' . $activeCardRollId . '/label' . $zebraProfileQuery . '" target="_blank">
          <span>🔍</span> Abrir Ampliada
        </a>
        <a class="btn secondary" style="font-size:12px;padding:6px 12px;border-radius:8px;display:inline-flex;align-items:center;gap:5px" href="' . h($previewUrl . $zebraProfileQuery) . '" target="_blank" title="Ver muestra sin datos reales">
          <span>🧪</span> Muestra sin Guardar
        </a>';
    } else {
        $body .= '<a class="btn" style="background:#0284c7;border-color:#0284c7;color:#fff;font-size:12px;padding:6px 14px;font-weight:700;display:inline-flex;align-items:center;gap:6px;border-radius:8px" href="' . h($previewUrl) . '&auto_print=1" target="_blank">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
          Imprimir Muestra de Prueba
        </a>
        <a class="btn secondary" style="font-size:12px;padding:6px 12px;border-radius:8px;display:inline-flex;align-items:center;gap:5px" href="' . h($previewUrl . '&server_print=1' . $zebraProfileQuery) . '" target="_blank">
          <span>⚡</span> Zebra Directa
        </a>
        <a class="btn secondary" style="font-size:12px;padding:6px 12px;border-radius:8px;display:inline-flex;align-items:center;gap:5px" href="' . h($previewUrl . '&server_print=1' . $barposProfileQuery) . '" target="_blank">
          <span>⚡</span> Barpos Directa
        </a>
        <a class="btn secondary" style="font-size:12px;padding:6px 12px;border-radius:8px;display:inline-flex;align-items:center;gap:5px" href="' . h($previewUrl . '&download_zpl=1' . $zebraProfileQuery) . '" download>
          <span>⬇️</span> Zebra ZPL
        </a>
        <a class="btn secondary" style="font-size:12px;padding:6px 12px;border-radius:8px;display:inline-flex;align-items:center;gap:5px" href="' . h($previewUrl . '&download_zpl=1' . $barposProfileQuery) . '" download>
          <span>⬇️</span> Barpos TSPL
        </a>
        <a class="btn secondary" style="font-size:12px;padding:6px 12px;border-radius:8px;display:inline-flex;align-items:center;gap:5px" href="' . h($previewUrl . $zebraProfileQuery) . '" target="_blank">
          <span>🔍</span> Abrir Ampliada
        </a>';
    }

    $body .= '</div></div>
        <iframe id="label_preview" src="' . h($cardIframeSrc) . '" title="Vista previa etiqueta" style="width:100%;height:380px;border:1px solid #f1f5f9;border-radius:8px;background:#f8fafc"></iframe>
      </div>';

    $body .= '<script>
      window.deleteRoll = async function (rollId, rollCode, weightKg, skipReload) {
        rollId = Number(rollId);
        if (!rollId || isNaN(rollId)) {
          alert("ID de bobina no válido.");
          return null;
        }
        var weightLabel = (weightKg && Number(weightKg) > 0) ? " de " + Number(weightKg).toFixed(3) + " Kg" : "";
        var confirmMsg = "¿Estás seguro de BORRAR el pesaje de la bobina " + (rollCode || ("#" + rollId)) + weightLabel + "?\n\nEsta acción revertirá el peso ingresado y permitirá volver a ingresarlo si hubo un error.";
        if (!skipReload && !window.confirm(confirmMsg)) return null;

        var csrfInput = document.querySelector("input[name=_csrf]");
        var csrfVal = csrfInput ? csrfInput.value : "";

        try {
          var body = new URLSearchParams();
          body.set("_csrf", csrfVal);
          var res = await fetch("/api/rolls/" + rollId + "/delete", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body.toString()
          });
          var data = await res.json();
          if (!data || data.ok !== true) {
            alert("Error al borrar: " + ((data && data.error) ? data.error : "No se pudo eliminar el pesaje"));
            return null;
          }
          if (skipReload) {
            return data;
          }
          var currentUrl = new URL(window.location.href);
          currentUrl.searchParams.set("roll_deleted", data.roll_code || ("#" + rollId));
          currentUrl.searchParams.delete("saved_roll_id");
          window.location.href = currentUrl.toString();
          return data;
        } catch (err) {
          if (skipReload) {
            alert("Error al borrar: no se pudo completar el borrado múltiple.");
            return null;
          }
          var f = document.createElement("form");
          f.method = "POST";
          f.action = "/rolls/" + rollId + "/delete";
          var cInput = document.createElement("input");
          cInput.type = "hidden";
          cInput.name = "_csrf";
          cInput.value = csrfVal;
          f.appendChild(cInput);
          var rInput = document.createElement("input");
          rInput.type = "hidden";
          rInput.name = "redirect_to";
          rInput.value = window.location.href;
          f.appendChild(rInput);
          document.body.appendChild(f);
          f.submit();
          return null;
        }
      };

      document.addEventListener("click", function (e) {
        var btn = e.target.closest(".btn-delete-roll");
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var id = btn.getAttribute("data-roll-id");
        var code = btn.getAttribute("data-roll-code") || ("#" + id);
        var weight = btn.getAttribute("data-roll-weight") || "";
        window.deleteRoll(id, code, weight);
      });

      (function () {
        var form = document.getElementById("receive_form");
        var modeField = document.getElementById("reception_mode");
        var modeSelector = document.getElementById("reception_mode_selector");
        var weight = document.getElementById("weight_kg");
        var qty = document.getElementById("received_qty");
        var qtyInput = document.getElementById("received_qty_input");
        var toggle = document.getElementById("toggle_scale");
        var saveBtn = document.getElementById("save_print");
        var submitBtn = document.getElementById("submit_primary");
        var submitPrimary = document.getElementById("submit_primary");
        var actionButtonsWrapper = document.getElementById("action_buttons_wrapper");
        var weightFieldWrapper = document.getElementById("weight_field_wrapper");
        var qtyFieldWrapper = document.getElementById("qty_field_wrapper");
        var primaryValueLabel = document.getElementById("primary_value_label");
        var qtyValueLabel = document.getElementById("qty_value_label");
        var scaleStatusRow = document.getElementById("scale_status_row");
        var scaleStatusSpacer = document.getElementById("scale_status_spacer");
        var status = document.getElementById("scale_status");
        var tbody = document.getElementById("recent_tbody");
        var preview = document.getElementById("label_preview");
        var selectAllRolls = document.getElementById("select_all_rolls");
        var moveSelectedBtn = document.getElementById("move_selected_rolls");
        var moveSelectedWarehouse = document.getElementById("move_selected_warehouse");
        var deleteSelectedBtn = document.getElementById("delete_selected_rolls");
        var receptionLocked = ' . json_encode($isReceptionLocked) . ';
        if (!form || !modeField || !weight || !qty || !qtyInput || !toggle || !saveBtn || !status) return;
        if (receptionLocked) {
          saveBtn.disabled = true;
          if (submitPrimary) submitPrimary.disabled = true;
        }

        var active = false;
        var timer = null;
        var polling = false;
        var last = null;
        var stable = 0;
        var lastSaved = null;
        var saving = false;
        var armed = true;
        var printWindow = null;
        var printQueue = [];
        var printing = false;
        var printTimer = null;
        var lastPrintedAt = 0;
        var lastPrintedWeight = null;
        var MIN_LABEL_WEIGHT_KG = 15;
        var TRIGGER_ON_KG = 0.100;
        var TRIGGER_OFF_KG = 0.050;
        var MIN_INTERVAL_MS = 1000;
        var STABLE_DELTA_KG = 0.040;
        var REQUIRED_STABLE_READS = 2;
        var POLL_INTERVAL_MS = 250;
        var serverPrintEnabled = (function(){
          var el = document.getElementById("server_print_enabled");
          return !!(el && String(el.value) === "1");
        })();
        var serverPrinterName = ' . json_encode($printer->getConfiguredPrinterName()) . ';
        var printLangLabel = ' . json_encode(strtoupper($printer->getConfiguredPrintLanguage())) . ';
        var currentOpName = ' . json_encode($currentOperatorName) . ';

        function setStatus(text) { status.textContent = text; }

        function scheduleNextPoll(delayMs) {
          if (timer) clearTimeout(timer);
          timer = null;
          if (active && !saving) {
            timer = setTimeout(poll, typeof delayMs === "number" ? delayMs : POLL_INTERVAL_MS);
          }
        }

        function enqueuePrint(url) {
          if (!active) return;
          if (serverPrintEnabled) return;
          printQueue.push(url);
          if (!printing) processPrintQueue();
        }

        function getWarehouseSelect() {
          return form.querySelector("select[name=warehouse_id]");
        }

        function getMode() { return modeField.value === "WEIGHT" ? "WEIGHT" : "QUANTITY"; }

        function syncQtyValue() {
          qty.value = qtyInput.value && Number(qtyInput.value) > 0 ? qtyInput.value : "1";
        }

        function canManualWeightSave() {
          var wh = getWarehouseSelect();
          var weightValue = Number(weight.value);
          return getMode() === "WEIGHT"
            && !!(wh && wh.value)
            && Number.isFinite(weightValue)
            && weightValue >= MIN_LABEL_WEIGHT_KG;
        }

        function syncWeightSaveButton() {
          if (!saveBtn) return;
          if (active) {
            return;
          }
          saveBtn.disabled = !canManualWeightSave();
        }

        function stopScale() {
          active = false;
          polling = false;
          toggle.textContent = "Activar balanza";
          syncWeightSaveButton();
          if (timer) clearInterval(timer);
          timer = null;
          if (printTimer) clearTimeout(printTimer);
          printTimer = null;
          printQueue = [];
          printing = false;
          try { if (printWindow && !printWindow.closed) { printWindow.close(); } } catch (e) {}
        }

        function applyModeUI() {
          if (modeSelector) modeSelector.value = getMode();
          weight.step = "0.001";
          weight.required = getMode() === "WEIGHT";
          weight.disabled = getMode() !== "WEIGHT";
          qtyInput.step = "1";
          qtyInput.min = "1";
          qtyInput.max = "5000";
          if (weightFieldWrapper) weightFieldWrapper.style.display = getMode() === "WEIGHT" ? "" : "none";
          if (getMode() === "WEIGHT") {
            qty.value = "1";
            qtyInput.value = "1";
            if (primaryValueLabel) primaryValueLabel.textContent = "Peso real (Kg)";
            if (qtyFieldWrapper) qtyFieldWrapper.style.display = "none";
            if (toggle) toggle.style.display = "";
            if (saveBtn) saveBtn.style.display = "";
            if (submitPrimary) submitPrimary.style.display = "none";
            if (scaleStatusRow) scaleStatusRow.style.display = "";
            if (actionButtonsWrapper) actionButtonsWrapper.style.flexBasis = "300px";
            if (scaleStatusSpacer) scaleStatusSpacer.style.flexBasis = "300px";
            if (!active) {
              setStatus(canManualWeightSave() ? "Listo para guardar e imprimir 1 unidad" : "Ingresa al menos 15 Kg o activa balanza");
            }
          } else {
            syncQtyValue();
            if (primaryValueLabel) primaryValueLabel.textContent = "Peso real de la recepción (Kg)";
            if (qtyValueLabel) qtyValueLabel.textContent = "Cantidad recibida (Unid.)";
            if (qtyFieldWrapper) qtyFieldWrapper.style.display = "";
            if (toggle) toggle.style.display = "none";
            if (saveBtn) saveBtn.style.display = "none";
            if (submitPrimary) submitPrimary.style.display = "";
            if (scaleStatusRow) scaleStatusRow.style.display = "none";
            if (actionButtonsWrapper) actionButtonsWrapper.style.flexBasis = "190px";
            if (scaleStatusSpacer) scaleStatusSpacer.style.flexBasis = "190px";
            setStatus("Ingresa unidades: se genera una etiqueta por unidad, sin peso.");
          }
          syncWeightSaveButton();
        }

        function processPrintQueue() {
          if (!active || serverPrintEnabled) {
            printQueue = [];
            printing = false;
            return;
          }
          if (printQueue.length === 0) {
            printing = false;
            return;
          }
          printing = true;
          var url = printQueue.shift();
          if (printWindow && !printWindow.closed) {
            printWindow.location = url;
            try { printWindow.focus(); } catch (e) {}
          } else {
            printWindow = window.open(url, "label_print");
          }
          if (printTimer) clearTimeout(printTimer);
          printTimer = setTimeout(processPrintQueue, 2500);
        }

        async function poll() {
          if (!active || saving || getMode() !== "WEIGHT") {
            return;
          }
          if (polling) return;
          polling = true;
          try {
            var res = await fetch("/api/scale/weight", { cache: "no-store" });
            var data = await res.json();
            if (!data || data.ok !== true) {
              var scaleMsg = (data && data.error) ? data.error : "Balanza: esperando lectura";
              setStatus(scaleMsg);
              saveBtn.disabled = true;
              return;
            }
            var w = Number(data.weight_kg);
            if (!Number.isFinite(w)) throw new Error("Lectura inválida.");
            weight.value = w.toFixed(3);
            // Si la balanza marca el peso como inestable no se usa para estabilizar ni guardar.
            if (data.stable === false) {
              stable = 0;
              last = null;
              saveBtn.disabled = true;
              setStatus("Balanza: peso inestable, espera...");
              return;
            }

            if (last !== null && Math.abs(w - last) <= STABLE_DELTA_KG) stable++; else stable = 0;
            last = w;

            var wh = getWarehouseSelect();
            var hasWarehouse = !!(wh && wh.value);

            if (w < TRIGGER_OFF_KG) {
              armed = true;
              stable = 0;
              last = null;
              lastPrintedWeight = null;
              saveBtn.disabled = true;
              setStatus("Balanza: activa (lista)");
              return;
            }

            if (w >= TRIGGER_ON_KG && !hasWarehouse) {
              saveBtn.disabled = true;
              setStatus("Balanza: activa (selecciona bodega)");
              return;
            }

            if (w < MIN_LABEL_WEIGHT_KG) {
              saveBtn.disabled = true;
              setStatus("Balanza: peso bajo mínimo (15 Kg)");
              return;
            }

            saveBtn.disabled = true;
            var now = Date.now();
            var intervalOk = (now - lastPrintedAt) >= MIN_INTERVAL_MS;
            var isStable = stable >= REQUIRED_STABLE_READS;

            if (!saving && armed && hasWarehouse && w >= TRIGGER_ON_KG) {
              if (!isStable) {
                setStatus("Balanza: captando peso (" + (stable > 0 ? "confirmando..." : "estabilizando...") + ")");
                return;
              }
              if (intervalOk) {
                armed = false;
                setStatus("Balanza: activa (guardando...)");
                saveAndPrint(w);
                return;
              }
              setStatus("Balanza: activa (imprimiendo...)");
              return;
            }

            if (!armed) {
              setStatus("Balanza: activa (bulto presente)");
              return;
            }
            setStatus("Balanza: activa");
          } catch (e) {
            setStatus("Balanza: error");
            saveBtn.disabled = true;
          } finally {
            polling = false;
            scheduleNextPoll();
          }
        }

        async function saveAndPrint(weightOverride) {
          if (saving) return;
          var wh = getWarehouseSelect();
          var pol = form.querySelector("input[name=purchase_order_line_id]");
          var containerItem = form.querySelector("input[name=import_container_item_id]");
          var csrf = form.querySelector("input[name=_csrf]");
          if (!wh || (!pol && !containerItem) || !csrf) return;
          if (!wh.value) { alert("Selecciona la bodega."); return; }
          if (getMode() !== "WEIGHT") return;

          var weightToSave = (weightOverride !== undefined && weightOverride !== null) ? Number(weightOverride) : Number(weight.value);
          if (!Number.isFinite(weightToSave) || weightToSave < MIN_LABEL_WEIGHT_KG) {
            setStatus("Balanza: peso bajo mínimo (15 Kg)");
            return;
          }
          weight.value = weightToSave.toFixed(3);

          saving = true;
          saveBtn.disabled = true;
          saveBtn.textContent = "Guardando...";
          try {
            var params = new URLSearchParams();
            params.set("_csrf", csrf.value);
            if (pol && pol.value) {
              params.set("purchase_order_line_id", pol.value);
            }
            if (containerItem && containerItem.value) {
              params.set("import_container_item_id", containerItem.value);
            }
            params.set("reception_mode", getMode());
            params.set("warehouse_id", wh.value);
            params.set("weight_kg", weightToSave.toFixed(3));
            params.set("received_qty", qty.value || "1");
            var res = await fetch("/api/receptions/receive", {
              method: "POST",
              headers: { "Content-Type": "application/x-www-form-urlencoded" },
              body: params.toString()
            });
            var data = await res.json();
            if (!data || data.ok !== true) {
              if (data && data.errors && typeof data.errors === "object") {
                var poErr = data.errors.purchase_order_line_id ? String(data.errors.purchase_order_line_id) : "";
                var containerErr = data.errors.import_container_item_id ? String(data.errors.import_container_item_id) : "";
                var lineErr = poErr || containerErr;
                if (lineErr.indexOf("no permite") >= 0 || lineErr.indexOf("completa") >= 0 || lineErr.indexOf("finalizada") >= 0) {
                  setStatus("Recepción completa (pausada)");
                  active = false;
                  toggle.textContent = "Activar balanza";
                  saveBtn.disabled = true;
                  if (timer) clearInterval(timer);
                  timer = null;
                  return;
                }
              }
              var msg = (data && data.errors) ? Object.values(data.errors).join("\\n") : "No se pudo guardar.";
              throw new Error(msg);
            }
            var labelHref = "";
            try { labelHref = new URL(data.label_url, window.location.origin).toString(); } catch (e) { labelHref = data.label_url; }
            lastSaved = weightToSave;
            lastPrintedAt = Date.now();
            lastPrintedWeight = weightToSave;
            setStatus("Balanza: activa (retira la bobina)");

            if (tbody) {
              var tr = document.createElement("tr");
              tr.className = "rec-row-item";
              var dynRollCode = data.roll_code || ("#" + data.id);
              var trzCode = data.traceability_code || dynRollCode;
              var now = new Date();
              var whSelectedOpt = wh.options[wh.selectedIndex];
              var whDisplayName = whSelectedOpt ? (whSelectedOpt.getAttribute("data-name") || whSelectedOpt.text) : "";
              var dt = now.toISOString().slice(0, 19).replace("T", " ");

              tr.setAttribute("data-search", (dynRollCode + " " + trzCode + " " + whDisplayName + " " + currentOpName).toLowerCase());

              var td0 = document.createElement("td");
              td0.innerHTML = "<input type=\"checkbox\" class=\"recent-roll-check\" value=\"" + data.id + "\" data-roll-code=\"" + dynRollCode + "\" style=\"width:16px;height:16px;cursor:pointer\">";

              var td1 = document.createElement("td");
              td1.innerHTML = "<span class=\"rec-code-link\" style=\"font-family:monospace;font-size:13px;font-weight:700\">" + dynRollCode + "</span>";

              var tdTraz = document.createElement("td");
              tdTraz.innerHTML = "<a href=\"/stock/traceability?q=" + encodeURIComponent(trzCode) + "\" target=\"_blank\" style=\"font-family:monospace;font-size:12px;color:#0284c7;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:4px\" title=\"Ver árbol de trazabilidad 360°\">🌳 " + trzCode + "</a>";

              var td2 = document.createElement("td");
              td2.innerHTML = "<span class=\"wh-badge\" style=\"font-size:11px;padding:3px 8px;font-weight:600\">" + whDisplayName + "</span>";

              var td4 = document.createElement("td");
              td4.innerHTML = "<strong>" + weightToSave.toFixed(3) + " Kg</strong>";

              var tdSku = document.createElement("td");
              tdSku.innerHTML = "<span style=\"font-size:11.5px;color:#475569\">-</span>";

              var td5 = document.createElement("td");
              td5.innerHTML = "<span style=\"color:#64748b;font-size:12px\">" + dt + "</span>";

              var tdOp = document.createElement("td");
              tdOp.innerHTML = "<span style=\"font-size:11.5px;color:#475569\">" + (currentOpName || "Operador") + "</span>";

              var td6 = document.createElement("td");
              td6.style.textAlign = "right";

              var divActions = document.createElement("div");
              divActions.style.display = "flex";
              divActions.style.gap = "5px";
              divActions.style.justifyContent = "flex-end";
              divActions.style.alignItems = "center";

              var btnAuto = document.createElement("a");
              btnAuto.className = "btn";
              btnAuto.style.padding = "4px 10px";
              btnAuto.style.fontSize = "12px";
              btnAuto.style.borderRadius = "6px";
              btnAuto.style.background = "#0284c7";
              btnAuto.style.borderColor = "#0284c7";
              btnAuto.style.color = "#fff";
              btnAuto.style.display = "inline-flex";
              btnAuto.style.alignItems = "center";
              btnAuto.style.gap = "4px";
              btnAuto.target = "_blank";
              btnAuto.rel = "noopener";
              btnAuto.href = "/rolls/" + data.id + "/label?auto_print=1";
              btnAuto.textContent = "🖨️ Imprimir 10x7";
              divActions.appendChild(btnAuto);

              if (serverPrintEnabled) {
                var btnZebra = document.createElement("a");
                btnZebra.className = "btn secondary";
                btnZebra.style.padding = "4px 8px";
                btnZebra.style.fontSize = "11.5px";
                btnZebra.style.borderRadius = "6px";
                btnZebra.style.display = "inline-flex";
                btnZebra.style.alignItems = "center";
                btnZebra.style.gap = "3px";
                btnZebra.target = "_blank";
                btnZebra.rel = "noopener";
                btnZebra.href = "/rolls/" + data.id + "/label?server_print=1&printer_profile=zebra";
                btnZebra.textContent = "⚡ Zebra";
                divActions.appendChild(btnZebra);

                var btnBarpos = document.createElement("a");
                btnBarpos.className = "btn secondary";
                btnBarpos.style.padding = "4px 8px";
                btnBarpos.style.fontSize = "11.5px";
                btnBarpos.style.borderRadius = "6px";
                btnBarpos.style.display = "inline-flex";
                btnBarpos.style.alignItems = "center";
                btnBarpos.style.gap = "3px";
                btnBarpos.target = "_blank";
                btnBarpos.rel = "noopener";
                btnBarpos.href = "/rolls/" + data.id + "/label?server_print=1&printer_profile=barpos";
                btnBarpos.textContent = "⚡ Barpos";
                divActions.appendChild(btnBarpos);
              }

              var btnView = document.createElement("a");
              btnView.className = "btn secondary";
              btnView.style.padding = "4px 8px";
              btnView.style.fontSize = "11.5px";
              btnView.style.borderRadius = "6px";
              btnView.target = "_blank";
              btnView.rel = "noopener";
              btnView.href = data.label_url || ("/rolls/" + data.id + "/label");
              btnView.textContent = "👁️ Ver";
              divActions.appendChild(btnView);

              var btnDel = document.createElement("button");
              btnDel.type = "button";
              btnDel.className = "btn btn-delete-roll";
              btnDel.setAttribute("data-roll-id", data.id);
              btnDel.setAttribute("data-roll-code", dynRollCode);
              btnDel.setAttribute("data-roll-weight", weightToSave.toFixed(3));
              btnDel.style.padding = "4px 8px";
              btnDel.style.fontSize = "11.5px";
              btnDel.style.borderRadius = "6px";
              btnDel.style.background = "#fee2e2";
              btnDel.style.borderColor = "#fca5a5";
              btnDel.style.color = "#b91c1c";
              btnDel.style.fontWeight = "700";
              btnDel.style.cursor = "pointer";
              btnDel.textContent = "🗑️ Borrar";
              btnDel.onclick = function() {
                deleteRoll(data.id, dynRollCode, weightToSave.toFixed(3));
              };
              divActions.appendChild(btnDel);

              td6.appendChild(divActions);

              tr.appendChild(td0);
              tr.appendChild(td1);
              tr.appendChild(tdTraz);
              tr.appendChild(td2);
              tr.appendChild(td4);
              tr.appendChild(tdSku);
              tr.appendChild(td5);
              tr.appendChild(tdOp);
              tr.appendChild(td6);
              tbody.prepend(tr);

              var countBadge = document.getElementById("rec_badge_count");
              if (countBadge) {
                var currentCount = (parseInt(countBadge.getAttribute("data-total") || "0", 10) || 0) + 1;
                countBadge.setAttribute("data-total", currentCount);
                countBadge.textContent = currentCount + " bobinas";
                var btnAll = document.getElementById("btn_rec_all");
                if (btnAll) btnAll.textContent = "Todas (" + currentCount + ")";
              }
            }

            if (preview) {
              preview.src = data.label_url || ("/rolls/" + data.id + "/label");
            }

            if (data.printed === true) {
              setStatus("Balanza: activa (etiqueta emitida en " + (serverPrinterName || "impresora") + ")");
            } else if (data.print_job && window.UnibagHardware && typeof window.UnibagHardware.printJob === "function") {
              var hwRes = await window.UnibagHardware.printJob(data.print_job, { fallbackUrl: data.label_url || ("/rolls/" + data.id + "/label") });
              setStatus(hwRes && hwRes.ok
                ? ("Balanza: activa (etiqueta emitida en " + (hwRes.printer || serverPrinterName || "impresora") + ")")
                : "Recepción guardada · selecciona o conecta impresora en el aviso");
            } else if (window.UnibagHardware && typeof window.UnibagHardware.showPrintFallbackToast === "function") {
              window.UnibagHardware.showPrintFallbackToast(data.label_url || ("/rolls/" + data.id + "/label"), data.roll_code || ("#" + data.id));
              setStatus("Recepción guardada · etiqueta lista para imprimir");
            } else {
              var printTarget = data.print_url || (labelHref + "?auto_print=1");
              if (active && !serverPrintEnabled) {
                enqueuePrint(printTarget);
              } else {
                window.open(printTarget, "_blank", "noopener");
                setStatus(serverPrintEnabled
                  ? "Recepción guardada; impresión directa no respondió, se abrió respaldo"
                  : "Recepción guardada e impresión lista");
              }
            }
          } catch (e) {
            alert(e && e.message ? e.message : "Error guardando.");
          } finally {
            saving = false;
            saveBtn.textContent = "Guardar e imprimir";
            syncWeightSaveButton();
            scheduleNextPoll(200);
          }
        }

        toggle.addEventListener("click", function () {
          var wh = getWarehouseSelect();
          active = !active;
          if (active) {
            if (!wh || !wh.value) {
              active = false;
              setStatus("Balanza: selecciona la bodega antes de activar");
              alert("Selecciona la bodega antes de activar la balanza.");
              return;
            }
            toggle.textContent = "Desactivar balanza";
            setStatus("Balanza: activa");
            armed = true;
            stable = 0;
            last = null;
            lastPrintedWeight = null;
            try {
              if (!serverPrintEnabled && !window.UnibagHardware && !printing && (printQueue.length === 0)) {
                printWindow = window.open("about:blank", "label_print");
                if (printWindow && printWindow.document) {
                  var doc = printWindow.document;
                  doc.open();
                  doc.close();
                  var html = doc.documentElement;
                  var head = doc.head || doc.createElement("head");
                  var body = doc.body || doc.createElement("body");
                  if (!doc.head) html.appendChild(head);
                  if (!doc.body) html.appendChild(body);

                  doc.title = "Etiquetas";

                  var metaCharset = doc.createElement("meta");
                  metaCharset.setAttribute("charset", "utf-8");
                  head.appendChild(metaCharset);

                  var metaViewport = doc.createElement("meta");
                  metaViewport.setAttribute("name", "viewport");
                  metaViewport.setAttribute("content", "width=device-width, initial-scale=1");
                  head.appendChild(metaViewport);

                  var style = doc.createElement("style");
                  style.textContent = "body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:0;padding:16px;background:#f6f7f9;color:#111}.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px}.muted{color:#6b7280;font-size:12px}";
                  head.appendChild(style);

                  var card = doc.createElement("div");
                  card.className = "card";
                  var title = doc.createElement("div");
                  title.style.fontWeight = "800";
                  title.style.marginBottom = "6px";
                  title.textContent = "Ventana de impresión";
                  var msg = doc.createElement("div");
                  msg.className = "muted";
                  msg.textContent = "Se abrirá la etiqueta automáticamente cuando se guarde un pesaje.";
                  card.appendChild(title);
                  card.appendChild(msg);
                  body.appendChild(card);
                }
                try { printWindow.focus(); } catch (e) {}
              }
            } catch (e) {
              printWindow = null;
            }
            poll();
          } else {
            stopScale();
            setStatus("Balanza: inactiva");
          }
        });

        var whSelect = getWarehouseSelect();
        if (whSelect) {
          whSelect.addEventListener("change", function () {
            if (!active) {
              if (!whSelect.value) {
                setStatus("Balanza: selecciona bodega");
              } else if (canManualWeightSave()) {
                setStatus("Listo para guardar e imprimir 1 unidad");
              } else {
                setStatus("Ingresa al menos 15 Kg o activa balanza");
              }
            }
            syncWeightSaveButton();
          });
          if (!whSelect.value) {
            setStatus("Balanza: selecciona bodega");
          }
        }

        var previewDebounce = null;
        var sampleBaseUrl = ' . json_encode($previewUrl) . ';
        function updatePreviewWeight(wVal) {
          if (!preview || !sampleBaseUrl) return;
          clearTimeout(previewDebounce);
          previewDebounce = setTimeout(function() {
            var n = Number(wVal);
            if (Number.isFinite(n) && n > 0) {
              preview.src = sampleBaseUrl + "&weight=" + encodeURIComponent(n.toFixed(2));
            }
          }, 600);
        }

        weight.addEventListener("input", function () {
          if (getMode() === "WEIGHT" && !active) {
            setStatus(canManualWeightSave() ? "Listo para guardar e imprimir 1 unidad" : "Ingresa al menos 15 Kg o activa balanza");
          }
          syncWeightSaveButton();
          updatePreviewWeight(weight.value);
        });
        if (modeSelector) {
          modeSelector.addEventListener("change", function () {
            modeField.value = modeSelector.value === "WEIGHT" ? "WEIGHT" : "QUANTITY";
            stopScale();
            applyModeUI();
          });
        }
        qtyInput.addEventListener("input", function () {
          syncQtyValue();
        });
        form.addEventListener("submit", function (event) {
          if (getMode() === "WEIGHT") {
            qty.value = "1";
          } else {
            syncQtyValue();
            if (submitBtn) {
              if (submitBtn.dataset.submitting === "1") {
                event.preventDefault();
                return;
              }
              submitBtn.dataset.submitting = "1";
              submitBtn.disabled = true;
              submitBtn.textContent = "Guardando...";
            }
          }
        });
        modeField.value = modeField.value === "WEIGHT" ? "WEIGHT" : "QUANTITY";
        applyModeUI();

        function getSelectedRollChecks() {
          return Array.prototype.slice.call(document.querySelectorAll(".recent-roll-check:checked"));
        }

        if (selectAllRolls) {
          selectAllRolls.addEventListener("change", function () {
            var checks = document.querySelectorAll(".recent-roll-check");
            for (var i = 0; i < checks.length; i++) {
              checks[i].checked = !!selectAllRolls.checked;
            }
          });
        }

        if (deleteSelectedBtn) {
          deleteSelectedBtn.addEventListener("click", async function () {
            var selected = getSelectedRollChecks();
            if (!selected.length) {
              alert("Selecciona al menos una bobina para borrar.");
              return;
            }
            var labels = selected.map(function (el) { return el.getAttribute("data-roll-code") || ("#" + el.value); });
            var ok = confirm("¿Borrar " + selected.length + " bobina(s) desde esta recepción?\n\n" + labels.join(", "));
            if (!ok) return;
            var deletedLabels = [];
            for (var i = 0; i < selected.length; i++) {
              var result = await deleteRoll(Number(selected[i].value), selected[i].getAttribute("data-roll-code") || ("#" + selected[i].value), "", true);
              if (result && result.ok === true) {
                deletedLabels.push(result.roll_code || ("#" + selected[i].value));
              }
            }
            if (deletedLabels.length) {
              var currentUrl = new URL(window.location.href);
              currentUrl.searchParams.set("roll_deleted", deletedLabels.join(", "));
              currentUrl.searchParams.delete("saved_roll_id");
              window.location.href = currentUrl.toString();
            }
          });
        }

        if (moveSelectedBtn) {
          moveSelectedBtn.addEventListener("click", function () {
            var selected = getSelectedRollChecks();
            if (!selected.length) {
              alert("Selecciona al menos una bobina para mover.");
              return;
            }
            if (!moveSelectedWarehouse || !moveSelectedWarehouse.value) {
              alert("Selecciona la bodega destino.");
              return;
            }
            var labels = selected.map(function (el) { return el.getAttribute("data-roll-code") || ("#" + el.value); });
            var warehouseLabel = moveSelectedWarehouse.options[moveSelectedWarehouse.selectedIndex]
              ? moveSelectedWarehouse.options[moveSelectedWarehouse.selectedIndex].text
              : ("#" + moveSelectedWarehouse.value);
            var ok = confirm("¿Mover " + selected.length + " bobina(s) a " + warehouseLabel + "?\n\n" + labels.join(", "));
            if (!ok) return;

            var f = document.createElement("form");
            f.method = "post";
            f.action = "/reception/rolls/move";

            var csrfInput = form.querySelector("input[name=_csrf]");
            var cInput = document.createElement("input");
            cInput.type = "hidden";
            cInput.name = "_csrf";
            cInput.value = (csrfInput ? csrfInput.value : "");
            f.appendChild(cInput);

            var rInput = document.createElement("input");
            rInput.type = "hidden";
            rInput.name = "redirect_to";
            rInput.value = window.location.href;
            f.appendChild(rInput);

            var idsInput = document.createElement("input");
            idsInput.type = "hidden";
            idsInput.name = "roll_ids";
            idsInput.value = selected.map(function (el) { return el.value; }).join(",");
            f.appendChild(idsInput);

            var whInput = document.createElement("input");
            whInput.type = "hidden";
            whInput.name = "to_warehouse_id";
            whInput.value = moveSelectedWarehouse.value;
            f.appendChild(whInput);

            document.body.appendChild(f);
            f.submit();
          });
        }

        window.toggleRecView = function(mode) {
          var btnAll = document.getElementById("btn_rec_all");
          var btnRecent = document.getElementById("btn_rec_recent");
          var rows = document.querySelectorAll("#recent_tbody tr.rec-row-item");
          if (mode === "recent") {
            if (btnAll) { btnAll.style.background = "#f8fafc"; btnAll.style.color = "#475569"; btnAll.style.fontWeight = "600"; }
            if (btnRecent) { btnRecent.style.background = "#0284c7"; btnRecent.style.color = "#fff"; btnRecent.style.fontWeight = "700"; }
            for (var i = 0; i < rows.length; i++) {
              rows[i].style.display = i < 10 ? "" : "none";
            }
          } else {
            if (btnAll) { btnAll.style.background = "#0284c7"; btnAll.style.color = "#fff"; btnAll.style.fontWeight = "700"; }
            if (btnRecent) { btnRecent.style.background = "#f8fafc"; btnRecent.style.color = "#475569"; btnRecent.style.fontWeight = "600"; }
            for (var i = 0; i < rows.length; i++) {
              rows[i].style.display = "";
            }
          }
        };

        window.filterRecLiveTable = function() {
          var input = document.getElementById("rec_live_search");
          if (!input) return;
          var q = (input.value || "").trim().toLowerCase();
          var rows = document.querySelectorAll("#recent_tbody tr.rec-row-item");
          for (var i = 0; i < rows.length; i++) {
            var searchData = rows[i].getAttribute("data-search") || rows[i].textContent.toLowerCase();
            if (!q || searchData.indexOf(q) !== -1) {
              rows[i].style.display = "";
            } else {
              rows[i].style.display = "none";
            }
          }
        };

        saveBtn.addEventListener("click", function () { saveAndPrint(); });
      })();
    </script>';

    $body .= renderCloseReceptionModal('/purchase-orders/' . $poId . '/close', 'OC ' . (string)($po['po_code'] ?? ('#' . $poId)), $poPendingUnits);

return $body;
