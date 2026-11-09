<?php

declare(strict_types=1);

// =============================================================================
// Vistas de Solicitudes MP (Bodega 3000): listado, picking móvil y atención.
// Solo presentación: las rutas, formularios (nombres de campos) y scripts de cámara
// siguen en StockAndOperationsModule.php.
// =============================================================================

function mrStyles(): string
{
    return '<style>
    .mr{max-width:1180px;margin:0 auto;color:#0f172a}
    .mr *{box-sizing:border-box}
    .mr-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 4px 16px rgba(15,23,42,.04);margin-bottom:14px;overflow:hidden}
    .mr-pad{padding:18px 22px}
    .mr-top{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap}
    .mr h1,.mr h2,.mr h3{margin:0;color:#0f172a;letter-spacing:-.01em}
    .mr h1{font-size:21px;font-weight:900}
    .mr h2{font-size:19px;font-weight:900;line-height:1.25}
    .mr h3{font-size:15px;font-weight:900}
    .mr-sub{margin:4px 0 0;color:#64748b;font-size:13px;line-height:1.45}
    .mr-links{display:flex;gap:8px;flex-wrap:wrap}
    .mr-link,.mr-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:40px;padding:0 16px;border-radius:10px;font-weight:800;font-size:14px;text-decoration:none;cursor:pointer;font-family:inherit;white-space:nowrap}
    .mr-link{border:1px solid #cbd5e1;background:#fff;color:#334155}
    .mr-link:hover{background:#f1f5f9}
    .mr-btn{border:0;background:#00A9A6;color:#fff}
    .mr-btn:hover{background:#008f8c}
    .mr-btn.sec{background:#fff;color:#334155;border:1px solid #cbd5e1}
    .mr-btn.sec:hover{background:#f1f5f9}
    .mr-btn:disabled{opacity:.45;cursor:not-allowed}
    .mr-btn.block{width:100%}
    .mr-btn.big{min-height:50px;font-size:16px}
    .mr-tabs{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:16px}
    .mr-tab{display:flex;flex-direction:column;gap:2px;padding:11px 14px;border:1px solid #e2e8f0;border-top:3px solid #cbd5e1;border-radius:12px;background:#f8fafc;text-decoration:none;color:#334155}
    .mr-tab b{font-size:22px;font-weight:900;font-variant-numeric:tabular-nums;color:#0f172a;line-height:1.1}
    .mr-tab span{font-size:12px;font-weight:700;color:#64748b}
    .mr-tab:hover{background:#f0fdfa}
    .mr-tab.active{background:#f0fdfa;border-color:#99f6e4;border-top-color:#00A9A6}
    .mr-search{display:flex;gap:10px;align-items:center;padding:12px 18px;border-bottom:1px solid #e2e8f0;background:#f8fafc}
    .mr-search input{flex:1;min-width:0;height:40px;padding:0 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;background:#fff}
    .mr-search small{color:#64748b;white-space:nowrap;font-weight:700}
    .mr-badge{display:inline-flex;align-items:center;padding:3px 11px;border-radius:999px;font-size:12px;font-weight:800;white-space:nowrap;background:#f1f5f9;color:#475569}
    .mr-badge.pend{background:#fef3c7;color:#92400e}.mr-badge.acc{background:#dbeafe;color:#1e40af}
    .mr-badge.part{background:#ede9fe;color:#5b21b6}.mr-badge.done{background:#dcfce7;color:#166534}
    .mr-prog{min-width:130px}
    .mr-prog-txt{font-size:13px;font-weight:800;font-variant-numeric:tabular-nums}
    .mr-prog-txt span{color:#64748b;font-weight:600}
    .mr-bar{height:7px;border-radius:99px;background:#e2e8f0;margin-top:5px;overflow:hidden}
    .mr-bar i{display:block;height:100%;background:#00A9A6;border-radius:99px}
    .mr-bar.full i{background:#16a34a}
    .mr-table{width:100%;border-collapse:collapse;font-size:13px}
    .mr-table th{background:#f1f5f9;color:#475569;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;text-align:left;padding:11px 14px;border-bottom:1px solid #e2e8f0;white-space:nowrap}
    .mr-table td{padding:12px 14px;border-bottom:1px solid #eef2f7;vertical-align:middle}
    .mr-table tbody tr:hover td{background:#f8fffe}
    .mr-item{font-weight:800;font-size:14px}
    .mr-muted{color:#64748b;font-size:12px;margin-top:2px}
    .mr-ot{font-weight:800;color:#0f766e;text-decoration:none}
    .mr-ot:hover{text-decoration:underline}
    .mr-empty{text-align:center;color:#64748b;padding:34px 16px;font-size:14px}
    .mr-warn{font-size:11px;color:#b45309;font-weight:700;margin-top:3px}
    .mr-alert{border-radius:12px;padding:12px 16px;font-size:14px;font-weight:700;margin-bottom:14px;border:1px solid}
    .mr-alert.ok{background:#dcfce7;color:#166534;border-color:#86efac}
    .mr-alert.err{background:#fee2e2;color:#991b1b;border-color:#fca5a5}
    .mr-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:16px}
    .mr-step{display:flex;align-items:center;gap:9px;padding:10px 12px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;color:#94a3b8;font-size:13px;font-weight:800}
    .mr-step i{flex:0 0 26px;height:26px;border-radius:50%;background:#e2e8f0;color:#64748b;font-style:normal;display:flex;align-items:center;justify-content:center;font-size:13px}
    .mr-step.done{color:#166534;background:#f0fdf4;border-color:#bbf7d0}.mr-step.done i{background:#16a34a;color:#fff}
    .mr-step.now{color:#0f172a;background:#f0fdfa;border-color:#5eead4}.mr-step.now i{background:#00A9A6;color:#fff}
    .mr-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:16px}
    .mr-kpi{padding:10px 12px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}
    .mr-kpi small{display:block;font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
    .mr-kpi b{display:block;margin-top:2px;font-size:15px;font-weight:900;overflow-wrap:anywhere}
    .mr-qty{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:14px}
    .mr-qty div{padding:12px 14px;border-radius:12px;background:#f8fafc;text-align:center}
    .mr-qty small{display:block;font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
    .mr-qty b{display:block;font-size:24px;font-weight:900;font-variant-numeric:tabular-nums;margin-top:2px;line-height:1.15}
    .mr-qty div:nth-child(3){background:#f0fdfa}.mr-qty div:nth-child(3) b{color:#0f766e}
    .mr-qty em{font-style:normal;font-size:12px;color:#64748b;font-weight:700}
    .mr-who{margin-top:14px;padding:10px 14px;border-radius:12px;background:#f8fafc;color:#475569;font-size:13px}
    .mr-sec-head{padding:14px 20px;border-bottom:1px solid #e2e8f0;background:#f8fafc;border-left:4px solid #00A9A6}
    .mr-sec-head p{margin:3px 0 0;font-size:13px;color:#64748b;line-height:1.45}
    .mr-sec-body{padding:18px 20px}
    .mr-field label{display:block;font-size:12px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px}
    .mr-field input{width:100%;height:46px;padding:0 14px;border:1px solid #cbd5e1;border-radius:10px;font-size:16px;background:#fff}
    .mr-field input:focus,.mr-search input:focus{outline:2px solid #5eead4;outline-offset:1px;border-color:#14b8a6}
    .mr-form-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:end}
    .mr-form-row.three{grid-template-columns:minmax(0,1fr) minmax(0,2fr) auto}
    .mr-hint{margin-top:10px;padding:10px 14px;border-radius:10px;background:#f0fdfa;border:1px solid #99f6e4;color:#115e59;font-size:13px;line-height:1.5}
    .mr-cam-btns{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:12px}
    .mr-cam-status{font-size:13px;color:#475569;line-height:1.5}
    .mr-video{margin-top:12px;background:#0f172a;border-radius:12px;padding:6px;max-width:560px}
    .mr-video video{width:100%;max-height:320px;display:block;background:#000;border-radius:8px}
    .mr-deliv{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;padding:12px 20px;border-bottom:1px solid #eef2f7}
    .mr-deliv:last-child{border-bottom:0}
    .mr-deliv b{font-size:14px}
    .mr-deliv .q{font-weight:900;font-size:15px;white-space:nowrap;color:#0f766e}
    @media(max-width:820px){
      .mr-pad{padding:14px 16px}
      .mr-tabs{grid-template-columns:repeat(2,minmax(0,1fr))}
      .mr-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
      .mr-steps{grid-template-columns:1fr}
      .mr-form-row,.mr-form-row.three{grid-template-columns:1fr}
      .mr-form-row .mr-btn{width:100%;min-height:50px}
      .mr-links{width:100%}.mr-links .mr-link{flex:1}
      .mr-card.tbl{border:0;background:transparent;box-shadow:none;overflow:visible}
      .mr-table thead{display:none}
      .mr-table,.mr-table tbody,.mr-table tr,.mr-table td{display:block;width:100%}
      .mr-table tr{background:#fff;border:1px solid #e2e8f0;border-radius:14px;margin-bottom:10px;padding:8px 14px;box-shadow:0 2px 8px rgba(15,23,42,.04)}
      .mr-table td{display:flex;justify-content:space-between;align-items:center;gap:14px;border:0;padding:7px 0;text-align:right}
      .mr-table td::before{content:attr(data-label);font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;text-align:left;flex-shrink:0}
      .mr-table td.main{display:block;text-align:left;padding:6px 0 8px;border-bottom:1px solid #eef2f7}
      .mr-table td.main::before{display:none}
      .mr-table td.act{padding-top:10px}.mr-table td.act::before{display:none}.mr-table td.act .mr-btn{width:100%;min-height:46px}
      .mr-prog{min-width:0;width:60%}
      .mr-search{padding:10px 0;background:transparent;border:0}
    }
    @media(max-width:420px){.mr-qty b{font-size:20px}.mr-cam-btns{grid-template-columns:1fr}}
    </style>';
}

function mrBadge(string $status): string
{
    $cls = match (strtoupper(trim($status))) {
        'PENDING' => 'pend',
        'ACCEPTED' => 'acc',
        'PARTIAL' => 'part',
        'DELIVERED' => 'done',
        default => '',
    };
    return '<span class="mr-badge ' . $cls . '">' . h(materialRequestStatusLabel($status)) . '</span>';
}

/** Número sin ceros sobrantes: 12,5 / 4 / 1.250,25 */
function mrNum(float $value, string $unit = ''): string
{
    $text = number_format($value, 3, ',', '.');
    return rtrim(rtrim($text, '0'), ',');
}

function mrDate(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }
    $ts = strtotime($value);
    return $ts !== false ? date('d/m/Y H:i', $ts) : $value;
}

function mrQty(float $qty, string $unit): string
{
    return h(mrNum($qty, $unit)) . ' ' . h($unit);
}

function mrProgress(float $requested, float $delivered, string $unit): string
{
    $pct = $requested > 0 ? max(0, min(100, (int)round($delivered / $requested * 100))) : 0;
    return '<div class="mr-prog"><div class="mr-prog-txt">' . h(mrNum($delivered, $unit))
        . ' <span>de ' . h(mrNum($requested, $unit)) . ' ' . h($unit) . '</span></div>'
        . '<div class="mr-bar' . ($pct >= 100 ? ' full' : '') . '"><i style="width:' . $pct . '%"></i></div></div>';
}

function mrAlerts(string $ok, string $error, bool $operational): string
{
    $html = '';
    if ($ok !== '') {
        $html .= '<div class="mr-alert ok">' . h($ok) . '</div>';
    }
    if ($error !== '') {
        $html .= '<div class="mr-alert err">' . h($error) . '</div>';
    }
    if (!$operational) {
        $html .= '<div class="mr-alert err">La OT ya no está disponible para seguir atendiendo esta solicitud.</div>';
    }
    return $html;
}

function mrPageHeader(string $title, string $subtitle, string $backHref, string $backLabel): string
{
    return '<div class="mr-card mr-pad"><div class="mr-top"><div><h1>' . h($title) . '</h1>' . ($subtitle !== '' ? '<p class="mr-sub">' . h($subtitle) . '</p>' : '') . '</div>'
        . '<div class="mr-links"><a class="mr-link" href="' . h($backHref) . '">' . h($backLabel) . '</a></div></div></div>';
}

/** Resumen de la solicitud: material, pasos, cantidades y quién la tomó. */
function mrDetailTop(array $request, bool $operational, float $requested, float $delivered, float $pending): string
{
    $unit = (string)($request['requested_unit'] ?? 'Unid.');
    $status = strtoupper((string)($request['status'] ?? ''));
    $step = match ($status) {
        'ACCEPTED', 'PARTIAL' => 2,
        'DELIVERED' => 3,
        default => 1,
    };
    $labels = ['Aceptar solicitud', 'Entregar material', 'Completada'];
    $steps = '';
    foreach ($labels as $i => $label) {
        $n = $i + 1;
        $cls = $n < $step || $step === 3 ? 'done' : ($n === $step ? 'now' : '');
        $steps .= '<div class="mr-step ' . $cls . '"><i>' . ($cls === 'done' ? '✓' : (string)$n) . '</i>' . h($label) . '</div>';
    }
    $otId = (int)($request['work_order_id'] ?? 0);
    $ot = $otId > 0
        ? '<a class="mr-ot" href="/work-orders/' . $otId . '/start">' . h((string)$request['ot_code']) . '</a>'
        : h((string)$request['ot_code']);
    $who = trim((string)($request['accepted_by'] ?? ''));
    $whoHtml = $who !== ''
        ? '<div class="mr-who">Tomada por <b>' . h($who) . '</b> · ' . h(mrDate((string)($request['accepted_at'] ?? ''))) . '</div>'
        : '';
    return '<div class="mr-card mr-pad">'
        . '<div class="mr-top"><div><div class="mr-muted" style="margin:0 0 4px;font-weight:800;text-transform:uppercase;letter-spacing:.05em">Solicitud #' . (int)($request['id'] ?? 0) . '</div>'
        . '<h2>' . h((string)$request['requested_item']) . '</h2></div>' . mrBadge((string)$request['status']) . '</div>'
        . ($operational ? '<div class="mr-steps">' . $steps . '</div>' : '')
        . '<div class="mr-qty"><div><small>Pedido</small><b>' . h(mrNum($requested, $unit)) . '</b><em>' . h($unit) . '</em></div>'
        . '<div><small>Entregado</small><b>' . h(mrNum($delivered, $unit)) . '</b><em>' . h($unit) . '</em></div>'
        . '<div><small>Falta entregar</small><b>' . h(mrNum($pending, $unit)) . '</b><em>' . h($unit) . '</em></div></div>'
        . '<div class="mr-kpis"><div class="mr-kpi"><small>OT</small><b>' . $ot . '</b></div>'
        . '<div class="mr-kpi"><small>Solicita</small><b>' . h((string)$request['requested_by']) . '</b></div>'
        . '<div class="mr-kpi"><small>Tipo</small><b>' . h(materialRequestTypeLabel((string)($request['request_type'] ?? 'ROLL'))) . '</b></div>'
        . '<div class="mr-kpi"><small>Fecha</small><b>' . h(mrDate((string)($request['created_at'] ?? ''))) . '</b></div></div>'
        . $whoHtml . '</div>';
}

function mrSection(string $title, string $hint, string $inner): string
{
    return '<div class="mr-card"><div class="mr-sec-head"><h3>' . $title . '</h3>' . ($hint !== '' ? '<p>' . $hint . '</p>' : '') . '</div><div class="mr-sec-body">' . $inner . '</div></div>';
}

function mrAcceptCard(int $requestId, bool $mobile): string
{
    return mrSection('Paso 1 · Aceptar la solicitud', 'Bodega toma la solicitud antes de empezar a entregar material.',
        '<form method="post" action="/stock/material-requests/' . $requestId . '/accept">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . ($mobile ? '<input type="hidden" name="return_mode" value="mobile">' : '')
        . '<button class="mr-btn big' . ($mobile ? ' block' : '') . '" type="submit">' . ($mobile ? 'Tomar solicitud' : 'Aceptar solicitud') . '</button></form>');
}

/** Escaneo por código de barras + cámara. Los ids se mantienen: los usa el script de cámara. */
function mrScanCards(int $requestId, bool $mobile): string
{
    $form = '<form method="post" action="/stock/material-requests/' . $requestId . '/scan">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . ($mobile ? '<input type="hidden" name="return_mode" value="mobile">' : '')
        . '<div class="mr-form-row"><div class="mr-field"><label for="request_scan_code">Código de barras / ID de bobina</label>'
        . '<input id="request_scan_code" name="scan_code" type="text" value="" autofocus autocomplete="off" placeholder="Escanea la etiqueta o escribe el código"></div>'
        . '<button class="mr-btn big" type="submit">Ingresar bobina</button></div></form>'
        // La pistola en teclado español escribe «'» en lugar de «-»: se corrige al escribir y antes de enviar.
        . '<script>(function(){var i=document.getElementById("request_scan_code");if(!i)return;'
        . 'function fix(){var v=i.value,n=v.replace(/[\'`´’‘‛]/g,"-");if(n!==v){var p=i.selectionStart;i.value=n;try{i.setSelectionRange(p,p);}catch(e){}}}'
        . 'i.addEventListener("input",fix);if(i.form)i.form.addEventListener("submit",function(){fix();i.value=i.value.trim();},true);})();</script>'
        . ($mobile ? '' : '<div class="mr-hint">Al registrar la bobina, el sistema mueve automáticamente el inventario y la trazabilidad a la <b>3000 Insumos en Producción</b> y la asigna a la OT.</div>');
    $cam = '<div class="mr-cam-status" id="camera_scan_status">'
        . ($mobile ? 'Abre la cámara trasera o toma una foto del código de barras.' : 'Pulsa «Abrir cámara» para usar la cámara del dispositivo. Si el navegador la bloquea en esta red, usa «Tomar foto».')
        . '</div><div class="mr-cam-btns">'
        . '<button class="mr-btn sec" type="button" id="camera_scan_start">Abrir cámara</button>'
        . '<button class="mr-btn sec" type="button" id="camera_scan_stop" disabled>Detener</button>'
        . '<button class="mr-btn sec" type="button" id="camera_scan_capture">Tomar foto</button>'
        . '<input type="file" id="camera_scan_file" accept="image/*" capture="environment" style="display:none"></div>'
        . '<div class="mr-video"><video id="camera_scan_video" playsinline muted></video></div>';
    return mrSection('Paso 2 · Entregar la bobina', 'Escanea la etiqueta de la bobina que se entrega.', $form)
        . mrSection('Escanear con cámara', 'Alternativa si no tienes pistola lectora.', $cam);
}

function mrManualCard(int $requestId, string $unit, float $pending, bool $mobile): string
{
    $inner = '<form method="post" action="/stock/material-requests/' . $requestId . '/deliver-manual">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . ($mobile ? '<input type="hidden" name="return_mode" value="mobile">' : '')
        . '<div class="mr-form-row three"><div class="mr-field"><label>Cantidad entregada (' . h($unit) . ')</label>'
        . '<input name="delivered_qty" type="number" step="0.001" min="0.001" value="' . h((string)$pending) . '" required inputmode="decimal"></div>'
        . '<div class="mr-field"><label>Nota de entrega (opcional)</label><input name="delivery_note" type="text" value="" placeholder="' . ($mobile ? 'Detalle de la entrega' : 'Ej: entregado a línea 2, lote 15') . '"></div>'
        . '<button class="mr-btn big" type="submit">Registrar entrega</button></div></form>';
    return mrSection('Paso 2 · Registrar entrega', 'Para tintas y otros insumos que no se entregan por bobina escaneada.', $inner);
}

function mrDeliveries(array $deliveries): string
{
    $rows = '';
    foreach ($deliveries as $d) {
        $unit = (string)($d['requested_unit'] ?? 'Unid.');
        if ((int)($d['roll_id'] ?? 0) > 0) {
            $title = '<a class="mr-ot" href="/rolls/' . (int)$d['roll_id'] . '">' . h((string)$d['roll_code']) . '</a>';
            $note = '';
        } else {
            $detail = trim((string)($d['delivered_item'] ?? ''));
            $title = h($detail !== '' ? $detail : '-');
            $n = trim((string)($d['delivery_note'] ?? ''));
            $note = $n !== '' ? '<div class="mr-muted">' . h($n) . '</div>' : '';
        }
        $rows .= '<div class="mr-deliv"><div><b>' . $title . '</b>' . $note
            . '<div class="mr-muted">' . h(mrDate((string)($d['created_at'] ?? ''))) . ' · ' . h((string)($d['operator_name'] ?? '-')) . '</div></div>'
            . '<div class="q">' . mrQty((float)($d['delivered_qty'] ?? 0), $unit) . '</div></div>';
    }
    if ($rows === '') {
        $rows = '<div class="mr-empty">Todavía no hay entregas registradas para esta solicitud.</div>';
    }
    return '<div class="mr-card"><div class="mr-sec-head"><h3>Entregas registradas (' . count($deliveries) . ')</h3></div>' . $rows . '</div>';
}
