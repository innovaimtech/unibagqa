<?php
    $body = '<div class="rec-shell">';
    $body .= '<div class="rec-header-card">
        <div class="rec-title-group">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
            <h1>OC ' . h((string)$po['po_code']) . '</h1>
            ' . renderReceptionStatusBadge((string)$po['status']) . '
          </div>
          <p>Recepción ' . h($typeLabel) . ' · Proveedor: ' . h((string)$po['supplier_name']) . ' · País: ' . h((string)($po['supplier_country_name'] ?? '-')) . '</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
          ' . $closeBtn . '
          <a class="rec-btn-clear" href="' . h($backUrl) . '">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Volver
          </a>
        </div>
      </div>';

    $body .= $successAlert;
    $body .= $closureBanner;

    $body .= '<div class="rec-detail-meta-grid">
        <div class="rec-meta-box"><div class="rec-meta-label">Proveedor</div><div class="rec-meta-val">' . h((string)$po['supplier_name']) . '</div></div>
        <div class="rec-meta-box"><div class="rec-meta-label">Tipo Proveedor</div><div class="rec-meta-val">' . h($typeLabel) . '</div></div>
        <div class="rec-meta-box"><div class="rec-meta-label">País</div><div class="rec-meta-val">' . h((string)($po['supplier_country_name'] ?? '-')) . '</div></div>
        <div class="rec-meta-box"><div class="rec-meta-label">Fecha Creación</div><div class="rec-meta-val">' . h((string)$po['created_at']) . '</div></div>
      </div>';

    $body .= '<div class="rec-table-card"><div class="rec-table-wrap"><table class="rec-table"><thead><tr>
        <th>SKU</th><th>Modo</th><th>Especificación</th><th>Ordenado (Unid.)</th><th>Recibido (Unid.)</th><th>Pendiente (Unid.)</th><th style="text-align:right">Acción</th>
      </tr></thead><tbody>';
    foreach ($lines as $ln) {
        $lineSummary = receptionLineSummary($ln);
        $ordered = formatReceptionDual($lineSummary, 'ordered');
        $received = formatReceptionDual($lineSummary, 'received');
        $pending = formatReceptionDual($lineSummary, 'pending');
        $specText = buildReceptionSpec($ln);

        $body .= '<tr>';
        $body .= '<td><span class="rec-code-link">' . h((string)$ln['sku_code']) . '</span></td>';
        $body .= '<td>' . h(receptionModeLabel((string)$ln['reception_mode'])) . '</td>';
        $body .= '<td>' . $specText . '</td>';
        $body .= '<td>' . $ordered . '</td>';
        $body .= '<td>' . $received . '</td>';
        $body .= '<td>' . $pending . '</td>';
        $testLabelBtn = '<a class="btn secondary" style="padding:4px 9px;font-size:11.5px;color:#0284c7;border-color:#bae6fd;background:#f0f9ff;display:inline-flex;align-items:center;gap:4px" href="/reception/test-label?type=po&line=' . (int)$ln['id'] . '" target="_blank" title="Ver diseño 10x7 e imprimir prueba física sin recepcionar">🏷️ Probar Etiqueta</a>';
        if ($isComplete) {
            $body .= '<td style="text-align:right;white-space:nowrap"><div style="display:flex;gap:6px;justify-content:flex-end;align-items:center">' . $testLabelBtn . '<span class="rec-badge rec-badge-complete">Finalizada</span></div></td>';
        } elseif ($lineSummary['pending'] <= 0) {
            $body .= '<td style="text-align:right;white-space:nowrap"><div style="display:flex;gap:6px;justify-content:flex-end;align-items:center">' . $testLabelBtn . '<span class="rec-badge rec-badge-complete">Completa</span></div></td>';
        } else {
            $body .= '<td style="text-align:right;white-space:nowrap"><div style="display:flex;gap:6px;justify-content:flex-end;align-items:center">' . $testLabelBtn . '<a class="btn" style="padding:5px 12px;font-size:12px" href="/purchase-orders/' . (int)$poId . '/receive?line=' . (int)$ln['id'] . '">Recepcionar</a></div></td>';
        }
        $body .= '</tr>';
    }
    if ($lines === []) {
        $body .= '<tr><td colspan="7"><div class="rec-empty">Sin líneas en esta orden de compra.</div></td></tr>';
    }
    $body .= '</tbody></table></div></div>';

    if (!$isComplete) {
        $body .= renderCloseReceptionModal('/purchase-orders/' . $poId . '/close', 'OC ' . (string)$po['po_code'], $totalPendingUnits);
    }
    $body .= '</div>';

return $body;
