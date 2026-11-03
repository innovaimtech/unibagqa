<?php
    $body = '<div class="rec-shell">';
    $body .= '<div class="rec-header-card">
        <div class="rec-title-group">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
            <h1>' . h($cTitle) . '</h1>
            ' . renderReceptionStatusBadge((string)$container['status']) . '
          </div>
          <p>Cargamento internacional · OCs: ' . h((string)($container['po_codes'] ?: '-')) . ' · BL: ' . h((string)($container['bill_of_lading'] ?: '-')) . '</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
          ' . ((unibagIsUserJavier() || unibagCanUserPerformModifications()) ? '<a class="btn" style="background:#0f766e;border-color:#0f766e;color:#fff;font-weight:700;display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px" href="/import-containers/' . $containerId . '/no-weight-labels" target="_blank" title="Imprimir etiquetas sin peso con datos del contenedor (Exclusivo Javier)"><span>🏷️</span> Etiquetas Sin Peso</a>' : '') . '
          ' . $archiveBtn . '
          <a class="rec-btn-clear" href="' . h($backUrl) . '">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Volver
          </a>
        </div>
      </div>';

    $body .= $successAlert;
    $body .= $closureBanner;

    $body .= '<div class="rec-detail-meta-grid">
        <div class="rec-meta-box"><div class="rec-meta-label">Buque</div><div class="rec-meta-val">' . h((string)($container['vessel_name'] ?: '-')) . '</div></div>
        <div class="rec-meta-box"><div class="rec-meta-label">Embarcador</div><div class="rec-meta-val">' . h((string)($container['forwarder_name'] ?: '-')) . '</div></div>
        <div class="rec-meta-box"><div class="rec-meta-label">Incoterm</div><div class="rec-meta-val">' . h((string)($container['incoterm'] ?: '-')) . '</div></div>
        <div class="rec-meta-box"><div class="rec-meta-label">ETA planta</div><div class="rec-meta-val">' . h((string)($container['eta_plant'] ?: '-')) . '</div></div>
        <div class="rec-meta-box"><div class="rec-meta-label">Ordenado</div><div class="rec-meta-val">' . h($containerOrderedUnits) . '<div style="font-size:12px;color:#64748b;margin-top:2px">' . h($containerOrderedWeight) . '</div></div></div>
        <div class="rec-meta-box"><div class="rec-meta-label">Recepcionado</div><div class="rec-meta-val" style="color:#065f46">' . h($containerReceivedUnits) . '<div style="font-size:12px;color:#047857;margin-top:2px">' . h($containerReceivedWeight) . '</div></div></div>
        <div class="rec-meta-box"><div class="rec-meta-label">Pendiente</div><div class="rec-meta-val" style="color:' . (((float)($container['pending_rolls'] ?? 0) > 0 || (float)($container['pending_weight_kg'] ?? 0) > 0) ? '#9a3412' : '#065f46') . '">' . h($containerPendingUnits) . '<div style="font-size:12px;color:#64748b;margin-top:2px">' . h($containerPendingWeight) . '</div></div></div>
        <div class="rec-meta-box" style="background:' . $containerErpBg . ';border-color:' . $containerErpBorder . '"><div class="rec-meta-label">Estado ERP</div><div class="rec-meta-val" style="color:' . $containerErpColor . '">' . h($containerErpStatus) . '<div style="font-size:12px;color:' . $containerErpColor . ';margin-top:2px">Líneas completas: ' . h((string)((int)($container['completed_lines'] ?? 0))) . ' / ' . h((string)((int)($container['total_lines'] ?? 0))) . '</div></div></div>
      </div>';

    $body .= '<div class="rec-table-card"><div class="rec-table-wrap"><table class="rec-table"><thead><tr>
        <th>OC</th><th>SKU</th><th>Modo</th><th>Especificación</th><th>Ordenado (Unid.)</th><th>Recibido (Unid.)</th><th>Pendiente (Unid.)</th><th style="text-align:right">Acción</th>
      </tr></thead><tbody>';
    foreach ($lines as $ln) {
        $lineSummary = receptionLineSummary($ln);
        $ordered = formatReceptionDual($lineSummary, 'ordered');
        $received = formatReceptionDual($lineSummary, 'received');
        $pending = formatReceptionDual($lineSummary, 'pending');
        $specText = buildReceptionSpec($ln);

        $body .= '<tr>';
        $body .= '<td><span class="rec-code-link">' . h((string)$ln['po_code']) . '</span></td>';
        $body .= '<td><span style="font-weight:700">' . h((string)$ln['sku_code']) . '</span></td>';
        $body .= '<td>' . h(receptionModeLabel((string)$ln['reception_mode'])) . '</td>';
        $body .= '<td>' . $specText . '</td>';
        $body .= '<td>' . $ordered . '</td>';
        $body .= '<td>' . $received . '</td>';
        $body .= '<td>' . $pending . '</td>';
        $testLabelBtn = '<a class="btn secondary" style="padding:4px 9px;font-size:11.5px;color:#0284c7;border-color:#bae6fd;background:#f0f9ff;display:inline-flex;align-items:center;gap:4px" href="/reception/test-label?type=import&line=' . (int)$ln['import_container_item_id'] . '" target="_blank" title="Ver diseño 10x7 e imprimir prueba física sin recepcionar">🏷️ Probar Etiqueta</a>';
        $noWeightLineBtn = (unibagIsUserJavier() || unibagCanUserPerformModifications())
            ? '<a class="btn secondary" style="padding:4px 9px;font-size:11.5px;color:#0f766e;border-color:#99f6e4;background:#f0fdfa;display:inline-flex;align-items:center;gap:4px" href="/import-containers/' . $containerId . '/no-weight-labels?line=' . (int)$ln['import_container_item_id'] . '" target="_blank" title="Imprimir etiquetas sin peso para esta línea">🏷️ Sin Peso</a>'
            : '';
        if ((string)$container['status'] === 'COMPLETE') {
            $body .= '<td style="text-align:right;white-space:nowrap"><div style="display:flex;gap:6px;justify-content:flex-end;align-items:center">' . $testLabelBtn . $noWeightLineBtn . '<span class="rec-badge rec-badge-complete">Finalizada</span></div></td>';
        } elseif ($lineSummary['pending'] <= 0) {
            $body .= '<td style="text-align:right;white-space:nowrap"><div style="display:flex;gap:6px;justify-content:flex-end;align-items:center">' . $testLabelBtn . $noWeightLineBtn . '<span class="rec-badge rec-badge-complete">Completa</span></div></td>';
        } else {
            $body .= '<td style="text-align:right;white-space:nowrap"><div style="display:flex;gap:6px;justify-content:flex-end;align-items:center">' . $testLabelBtn . $noWeightLineBtn . '<a class="btn" style="padding:5px 12px;font-size:12px" href="/import-containers/' . $containerId . '/receive?line=' . (int)$ln['import_container_item_id'] . '">Recepcionar</a></div></td>';
        }
        $body .= '</tr>';
    }
    if ($lines === []) {
        $body .= '<tr><td colspan="8"><div class="rec-empty">Sin líneas en este cargamento.</div></td></tr>';
    }
    $body .= '</tbody></table></div></div>';

    $cntRolls = $service->listRollsByImportContainer($containerId, null, 2000);
    $cntRollsCount = count($cntRolls);
    $cntRollsWeight = array_sum(array_column($cntRolls, 'weight_kg'));
    if ($cntRollsCount > 0) {
        $body .= '<div class="rec-table-card" style="margin-top:16px"><div style="padding:14px 18px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">';
        $body .= '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">';
        $body .= '<div style="font-weight:800;font-size:15px;color:#0f172a">📦 Bobinas con Trazabilidad Registradas en este Cargamento</div>';
        $body .= '<span id="cnt_rolls_badge_count" style="font-size:11.5px;font-weight:800;background:#e0f2fe;color:#0369a1;padding:2px 8px;border-radius:12px">' . $cntRollsCount . ' bobinas</span>';
        if ($cntRollsWeight > 0) {
            $body .= '<span id="cnt_rolls_badge_weight" style="font-size:11.5px;font-weight:800;background:#f0fdf4;color:#16a34a;padding:2px 8px;border-radius:12px">' . number_format($cntRollsWeight, 2, ',', '.') . ' Kg</span>';
        }
        $body .= '</div>';
        $body .= '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
        $body .= '<input id="cnt_rolls_search" type="text" placeholder="🔍 Filtrar bobina, trazabilidad, SKU..." style="height:32px;padding:0 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:12px;width:240px" oninput="filterCntRolls(this.value)">';
        $body .= '<a class="btn secondary" href="/import-containers/' . $containerId . '/rolls-excel" style="height:32px;display:inline-flex;align-items:center;gap:5px;font-size:12px;padding:0 12px">';
        $body .= '<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg> Descargar Excel (' . $cntRollsCount . ')';
        $body .= '</a>';
        $body .= '</div></div>';
        $body .= '<div class="rec-table-wrap" style="max-height:550px;overflow-y:auto">';
        $body .= '<table id="cnt_rolls_table" class="rec-table" style="font-size:12.5px"><thead><tr style="position:sticky;top:0;background:#f8fafc;z-index:2">';
        $body .= '<th style="width:40px;text-align:center">N°</th><th>Código Bobina</th><th>Cód. Trazabilidad</th><th>SKU / Artículo</th><th>Bodega</th><th style="text-align:right">Peso Real</th><th style="text-align:center">Especificación</th><th>Fecha / Hora</th><th>Operador</th><th style="text-align:right">Acciones</th>';
        $body .= '</tr></thead><tbody id="cnt_rolls_tbody">';
        $cIdx = 0;
        foreach ($cntRolls as $cr) {
            $cIdx++;
            $crId = (int)$cr['id'];
            $crCode = (string)($cr['roll_code'] ?? '');
            $crTrz = (string)($cr['traceability_code'] ?? $crCode);
            $crWeight = (float)($cr['weight_kg'] ?? 0);
            $crMode = (string)($cr['reception_mode'] ?? '');
            $crSku = (string)($cr['sku_code'] ?? '');
            $crDesc = (string)($cr['sku_description'] ?? '');
            $crWh = trim((string)($cr['warehouse_name'] ?? '')) ?: (string)($cr['warehouse_code'] ?? '-');
            $crDate = !empty($cr['created_at']) ? date('d/m/Y H:i', strtotime((string)$cr['created_at'])) : '-';
            $crOp = (string)($cr['operator_name'] ?? '');
            $crWidth = (int)($cr['width_mm'] ?? 0);
            $crMic = (int)($cr['microns'] ?? 0);
            $crMet = (float)($cr['meters'] ?? 0);
            $crSpec = ($crWidth > 0 || $crMic > 0 || $crMet > 0) ? ($crWidth . 'mm × ' . $crMic . 'µm × ' . number_format($crMet, 0, ',', '.') . 'm') : '-';
            $crFilter = mb_strtolower($crCode . ' ' . $crTrz . ' ' . $crSku . ' ' . $crDesc . ' ' . $crWh . ' ' . $crOp);

            $modeTag = $crMode === 'WAREHOUSE_STOCK' ? '<span style="font-size:10px;background:#f0fdfa;color:#0f766e;border:1px solid #99f6e4;padding:1px 5px;border-radius:4px;font-weight:700;margin-left:4px">Bodega</span>' : '';
            $weightCellHtml = ($crMode === 'WAREHOUSE_STOCK' || $crWeight <= 0) ? '<span style="color:#64748b;font-weight:700">-</span>' : ('<strong>' . number_format($crWeight, 3, ',', '.') . ' Kg</strong>');

            $body .= '<tr class="cnt-roll-row" data-text="' . h($crFilter) . '" data-weight="' . number_format($crWeight, 3, '.', '') . '">';
            $body .= '<td style="text-align:center;color:#64748b">' . $cIdx . '</td>';
            $body .= '<td><span class="rec-code-link" style="font-family:monospace;font-weight:700">' . h($crCode) . '</span>' . $modeTag . '</td>';
            $body .= '<td><a href="/stock/traceability?q=' . rawurlencode($crTrz) . '" target="_blank" style="font-family:monospace;font-weight:700;color:#0f766e;text-decoration:none;background:#f0fdfa;border:1px solid #ccfbf1;padding:2px 6px;border-radius:4px" title="Ver árbol de trazabilidad 360°">' . h($crTrz) . ' 🔍</a></td>';
            $body .= '<td><div style="font-weight:700">' . h($crSku) . '</div><div style="font-size:11px;color:#64748b;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . h($crDesc) . '</div></td>';
            $body .= '<td><span class="wh-badge" style="font-size:11px;padding:2px 7px;font-weight:600">' . h($crWh) . '</span></td>';
            $body .= '<td style="text-align:right">' . $weightCellHtml . '</td>';
            $body .= '<td style="text-align:center;color:#64748b;font-size:11.5px">' . h($crSpec) . '</td>';
            $body .= '<td style="color:#64748b;font-size:12px">' . h($crDate) . '</td>';
            $body .= '<td style="color:#64748b;font-size:11.5px">' . ($crOp !== '' ? ('👤 ' . h($crOp)) : '-') . '</td>';
            $body .= '<td style="text-align:right;white-space:nowrap"><div style="display:flex;gap:4px;justify-content:flex-end">';
            $body .= '<a class="btn" style="padding:3px 8px;font-size:11px;background:#0284c7;border-color:#0284c7;color:#fff" href="/rolls/' . $crId . '/label?auto_print=1" target="_blank" title="Imprimir etiqueta">🖨️ 10x7</a>';
            if (unibagIsUserJavier() || unibagCanUserPerformModifications()) {
                $body .= '<a class="btn secondary" style="padding:3px 7px;font-size:11px;color:#0f766e;border-color:#99f6e4;background:#f0fdfa;font-weight:700" href="/rolls/' . $crId . '/label?no_weight=1&server_print=1" target="_blank" title="Imprimir etiqueta sin peso directa en impresora térmica automática (sin navegador)">⚡ Sin Peso Térmica</a>';
            }
            if ($printer->isEnabled()) {
                $body .= '<a class="btn secondary" style="padding:3px 6px;font-size:11px" href="/rolls/' . $crId . '/label?server_print=1" target="_blank" title="Térmico">⚡</a>';
            }
            $body .= '</div></td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div></div>';
        $body .= '<script>
        function filterCntRolls(q) {
            q = (q || "").trim().toLowerCase();
            var rows = document.querySelectorAll("#cnt_rolls_tbody .cnt-roll-row");
            var vis = 0, sumW = 0;
            rows.forEach(function(r) {
                var text = r.getAttribute("data-text") || "";
                var show = (q === "" || text.indexOf(q) !== -1);
                r.style.display = show ? "" : "none";
                if (show) { vis++; sumW += parseFloat(r.getAttribute("data-weight") || "0"); }
            });
            var bC = document.getElementById("cnt_rolls_badge_count");
            var bW = document.getElementById("cnt_rolls_badge_weight");
            if (bC) bC.textContent = vis + " bobinas";
            if (bW) bW.textContent = sumW.toLocaleString("es-CL", {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " Kg";
        }
        </script>';
    }

    if (!$isComplete) {
        $body .= renderCloseReceptionModal('/import-containers/' . $containerId . '/close', $cTitle, $totalPendingUnits);
    }
    $body .= '</div>';

return $body;
