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
    .mr-slot-box{background:#f8fafc;border:1px solid #cbd5e1;border-radius:12px;padding:14px;margin-bottom:14px}
    .mr-slot-bar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px}
    .mr-slot-bar label{font-size:12px;font-weight:800;color:#334155;text-transform:uppercase;letter-spacing:.04em}
    .mr-slot-count{width:84px;height:38px;padding:0 10px;border:1px solid #cbd5e1;border-radius:9px;font-size:15px;font-weight:800;text-align:center;background:#fff}
    .mr-slot-sum{margin-left:auto;background:#e0f2fe;color:#0369a1;padding:5px 12px;border-radius:999px;font-size:12.5px;font-weight:800}
    .mr-slot-list{display:flex;flex-direction:column;gap:8px;max-height:360px;overflow-y:auto;padding-right:2px}
    .mr-slot-row{display:flex;align-items:center;gap:8px}
    .mr-slot-idx{min-width:38px;height:42px;border-radius:10px;background:#e2e8f0;color:#334155;font-weight:800;font-size:13px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
    .mr-slot-row.filled .mr-slot-idx{background:#16a34a;color:#fff}
    .mr-slot-row.dup .mr-slot-idx{background:#dc2626;color:#fff}
    .mr-slot-input{flex:1;min-width:0;height:44px;padding:0 14px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px;font-family:monospace;font-weight:700;background:#fff}
    .mr-slot-input:focus{outline:2px solid #5eead4;outline-offset:1px;border-color:#14b8a6}
    .mr-slot-row.filled .mr-slot-input{border-color:#22c55e;background:#f0fdf4}
    .mr-slot-row.dup .mr-slot-input{border-color:#ef4444;background:#fef2f2}
    .mr-slot-tag{font-size:12px;font-weight:800;white-space:nowrap}
    .mr-slot-row.filled .mr-slot-tag{color:#15803d}
    .mr-slot-row.dup .mr-slot-tag{color:#b91c1c}
    .mr-slot-rm{border:1px solid #cbd5e1;background:#fff;color:#64748b;border-radius:10px;width:40px;height:42px;font-size:14px;font-weight:800;cursor:pointer;flex-shrink:0}
    .mr-slot-rm:hover{background:#fee2e2;color:#991b1b;border-color:#fca5a5}
    @media(max-width:820px){
      .mr-pad{padding:14px 16px}
      .mr-slot-sum{margin-left:0;width:100%;text-align:center}
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

/** Escaneo por casillas múltiples (código de barras + cámara). Los ids se mantienen: los usa el script de cámara. */
function mrScanCards(int $requestId, bool $mobile, float $pendingQty = 1.0): string
{
    $defaultSlots = max(1, min(30, (int)ceil($pendingQty > 0 ? $pendingQty : 1)));
    $slotsHtml = '';
    for ($s = 1; $s <= $defaultSlots; $s++) {
        $slotsHtml .= '<div class="mr-slot-row">'
            . '<span class="mr-slot-idx">#' . $s . '</span>'
            . '<input ' . ($s === 1 ? 'id="request_scan_code" autofocus ' : '') . 'name="scan_codes[]" class="mr-slot-input" type="text" value="" maxlength="240" autocomplete="off" placeholder="Escanea la etiqueta o escribe el código #' . $s . '">'
            . '<span class="mr-slot-tag"></span>'
            . '<button type="button" class="mr-slot-rm" title="Limpiar o quitar casilla">✕</button>'
            . '</div>';
    }
    $form = '<form method="post" action="/stock/material-requests/' . $requestId . '/scan" id="mr_batch_scan_form" autocomplete="off">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . ($mobile ? '<input type="hidden" name="return_mode" value="mobile">' : '')
        . '<div class="mr-slot-box">'
        . '<div class="mr-slot-bar">'
        . '<label for="mr_slot_count">Casillas a cargar:</label>'
        . '<input id="mr_slot_count" class="mr-slot-count" type="number" min="1" max="100" value="' . $defaultSlots . '">'
        . '<button class="mr-btn sec" type="button" id="mr_slot_apply">Generar casillas</button>'
        . '<button class="mr-btn sec" type="button" id="mr_slot_add">+ Agregar casilla</button>'
        . '<button class="mr-btn sec" type="button" id="mr_slot_clean">Limpiar vacías</button>'
        . '<span class="mr-slot-sum" id="mr_slot_sum">0 de ' . $defaultSlots . ' casilla(s) con código</span>'
        . '</div>'
        . '<div class="mr-hint" style="margin:0 0 10px">💡 Escanea cada bobina seguida: al leer un código pasa automáticamente a la siguiente casilla sin recargar la página. Cuando termines, presiona el botón para cargar todos los códigos y atender la solicitud de una sola vez.</div>'
        . '<div class="mr-slot-list" id="mr_slot_list">' . $slotsHtml . '</div>'
        . '</div>'
        . '<button class="mr-btn big block" type="submit" id="mr_submit_all_btn" disabled>🚀 Cargar todos los códigos y atender solicitud</button></form>'
        . '<script>(function(){'
        . 'var form=document.getElementById("mr_batch_scan_form"),list=document.getElementById("mr_slot_list"),cntInp=document.getElementById("mr_slot_count"),sumEl=document.getElementById("mr_slot_sum"),subBtn=document.getElementById("mr_submit_all_btn");'
        . 'if(!form||!list)return;'
        . 'function norm(v){return String(v||"").replace(/[\'`´’‘‛]/g,"-").trim();}'
        . 'function splitCodes(raw){var s=String(raw||"").replace(/[\'`´’‘‛]/g,"-").replace(/([A-Za-z0-9])((?:RB|CJ|BT|TRZ)-\\d)/gi,"$1 $2");return s.split(/[\\s,;]+/).map(function(p){return p.trim();}).filter(Boolean);}'
        . 'function makeRow(idx,val){var d=document.createElement("div");d.className="mr-slot-row";d.innerHTML=\'<span class="mr-slot-idx">#\'+idx+\'</span><input name="scan_codes[]" class="mr-slot-input" type="text" value="" maxlength="240" autocomplete="off" placeholder="Escanea la etiqueta o escribe el código #\'+idx+\'"><span class="mr-slot-tag"></span><button type="button" class="mr-slot-rm" title="Limpiar o quitar casilla">✕</button>\';var inp=d.querySelector(".mr-slot-input");if(val)inp.value=val;return d;}'
        . 'function refresh(){'
        . 'var rows=[].slice.call(list.querySelectorAll(".mr-slot-row"));if(cntInp)cntInp.value=rows.length;'
        . 'var seen={},valid=[],dups=0;'
        . 'rows.forEach(function(r,i){'
        . 'var idxEl=r.querySelector(".mr-slot-idx");if(idxEl)idxEl.textContent="#"+(i+1);'
        . 'var inp=r.querySelector(".mr-slot-input");if(!inp)return;'
        . 'if(i===0)inp.id="request_scan_code";else if(inp.id==="request_scan_code")inp.removeAttribute("id");'
        . 'inp.placeholder="Escanea la etiqueta o escribe el código #"+(i+1);'
        . 'var tag=r.querySelector(".mr-slot-tag");var v=norm(inp.value);'
        . 'if(inp.value!==v&&document.activeElement!==inp)inp.value=v;'
        . 'r.classList.remove("filled","dup");if(tag)tag.textContent="";'
        . 'if(v!==""){var up=v.toUpperCase();if(seen[up]){r.classList.add("dup");if(tag)tag.textContent="⚠️ Repetido";dups++;}else{seen[up]=true;r.classList.add("filled");if(tag)tag.textContent="✓ Listo";valid.push(v);}}'
        . '});'
        . 'if(sumEl)sumEl.textContent=valid.length+" de "+rows.length+" casilla(s) con código"+(dups?" · "+dups+" repetido(s)":"");'
        . 'if(subBtn){subBtn.disabled=(valid.length===0||dups>0);subBtn.textContent=valid.length>0?("🚀 Cargar "+valid.length+" código(s) y atender solicitud"):"🚀 Cargar todos los códigos y atender solicitud";}'
        . '}'
        . 'function distribute(inp,advance){'
        . 'var parts=splitCodes(inp.value);var inputs=[].slice.call(list.querySelectorAll(".mr-slot-input"));var idx=inputs.indexOf(inp);'
        . 'if(parts.length===0){inp.value="";refresh();return;}'
        . 'var seen={};inputs.forEach(function(o,j){if(j!==idx){var ov=norm(o.value).toUpperCase();if(ov)seen[ov]=true;}});'
        . 'var uniq=[];parts.forEach(function(p){var u=p.toUpperCase();if(!seen[u]){seen[u]=true;uniq.push(p);}});'
        . 'if(uniq.length===0){inp.value="";refresh();inp.focus();return;}'
        . 'inp.value=uniq[0];var cur=idx;'
        . 'for(var k=1;k<uniq.length;k++){cur++;inputs=[].slice.call(list.querySelectorAll(".mr-slot-input"));while(cur<inputs.length&&norm(inputs[cur].value)!=="")cur++;if(cur>=inputs.length){list.appendChild(makeRow(inputs.length+1,uniq[k]));}else{inputs[cur].value=uniq[k];}}'
        . 'refresh();'
        . 'if(advance||uniq.length>1){'
        . 'inputs=[].slice.call(list.querySelectorAll(".mr-slot-input"));var next=null;'
        . 'for(var m=cur+1;m<inputs.length;m++){if(norm(inputs[m].value)===""){next=inputs[m];break;}}'
        . 'if(!next){for(var n=0;n<inputs.length;n++){if(norm(inputs[n].value)===""){next=inputs[n];break;}}}'
        . 'if(!next){var nr=makeRow(inputs.length+1,"");list.appendChild(nr);refresh();next=nr.querySelector(".mr-slot-input");}'
        . 'if(next)next.focus();'
        . '}'
        . '}'
        . 'window.mrAddScannedCode=function(raw){'
        . 'var code=norm(raw);if(!code)return false;'
        . 'var inputs=[].slice.call(list.querySelectorAll(".mr-slot-input"));'
        . 'var target=inputs.find(function(x){return norm(x.value)==="";});'
        . 'if(!target){var nr=makeRow(inputs.length+1,"");list.appendChild(nr);target=nr.querySelector(".mr-slot-input");}'
        . 'target.value=code;distribute(target,true);return true;'
        . '};'
        . 'list.addEventListener("keydown",function(e){if(e.target&&e.target.classList.contains("mr-slot-input")&&(e.key==="Enter"||e.keyCode===13)){e.preventDefault();e.stopPropagation();distribute(e.target,true);}},true);'
        . 'list.addEventListener("input",function(e){var t=e.target;if(!t||!t.classList.contains("mr-slot-input"))return;var v=t.value.replace(/[\'`´’‘‛]/g,"-");if(v!==t.value)t.value=v;if(/[\\s,;]/.test(v)||/([A-Za-z0-9])((?:RB|CJ|BT|TRZ)-\\d)/i.test(v)){distribute(t,true);}else{refresh();}});'
        . 'list.addEventListener("click",function(e){var b=e.target.closest(".mr-slot-rm");if(!b)return;var r=b.closest(".mr-slot-row");if(!r)return;var inp=r.querySelector(".mr-slot-input");if(inp&&norm(inp.value)!==""){inp.value="";refresh();inp.focus();return;}if(list.querySelectorAll(".mr-slot-row").length>1){r.remove();refresh();}});'
        . 'function applyCount(){var target=Math.max(1,Math.min(100,parseInt(cntInp?cntInp.value:"1",10)||1));var rows=[].slice.call(list.querySelectorAll(".mr-slot-row"));if(rows.length<target){for(var i=rows.length;i<target;i++)list.appendChild(makeRow(i+1,""));}else if(rows.length>target){for(var j=rows.length-1;j>=0&&list.querySelectorAll(".mr-slot-row").length>target;j--){var inp=rows[j].querySelector(".mr-slot-input");if(!inp||norm(inp.value)==="")rows[j].remove();}}refresh();var fe=[].slice.call(list.querySelectorAll(".mr-slot-input")).find(function(x){return norm(x.value)==="";});if(fe)fe.focus();}'
        . 'var btnApply=document.getElementById("mr_slot_apply");if(btnApply)btnApply.addEventListener("click",applyCount);'
        . 'if(cntInp)cntInp.addEventListener("keydown",function(e){if(e.key==="Enter"||e.keyCode===13){e.preventDefault();applyCount();}});'
        . 'var btnAdd=document.getElementById("mr_slot_add");if(btnAdd)btnAdd.addEventListener("click",function(){var n=list.querySelectorAll(".mr-slot-row").length+1;var r=makeRow(n,"");list.appendChild(r);refresh();var inp=r.querySelector(".mr-slot-input");if(inp)inp.focus();});'
        . 'var btnClean=document.getElementById("mr_slot_clean");if(btnClean)btnClean.addEventListener("click",function(){[].slice.call(list.querySelectorAll(".mr-slot-row")).forEach(function(r){var inp=r.querySelector(".mr-slot-input");if(list.querySelectorAll(".mr-slot-row").length>1&&(!inp||norm(inp.value)===""))r.remove();});refresh();});'
        . 'form.addEventListener("submit",function(e){var inputs=[].slice.call(list.querySelectorAll(".mr-slot-input")),any=false;inputs.forEach(function(x){x.value=norm(x.value);if(x.value!=="")any=true;});if(!any){e.preventDefault();return;}if(subBtn){subBtn.disabled=true;subBtn.textContent="Procesando códigos...";}});'
        . 'refresh();'
        . '})();</script>'
        . ($mobile ? '' : '<div class="mr-hint">Al cargar los códigos, el sistema mueve automáticamente el inventario y la trazabilidad de todas las bobinas a la <b>3000 Insumos en Producción</b> y las asigna a la OT.</div>');
    $cam = '<div class="mr-cam-status" id="camera_scan_status">'
        . ($mobile ? 'Abre la cámara trasera o toma una foto del código de barras.' : 'Pulsa «Abrir cámara» para usar la cámara del dispositivo. Si el navegador la bloquea en esta red, usa «Tomar foto».')
        . '</div><div class="mr-cam-btns">'
        . '<button class="mr-btn sec" type="button" id="camera_scan_start">Abrir cámara</button>'
        . '<button class="mr-btn sec" type="button" id="camera_scan_stop" disabled>Detener</button>'
        . '<button class="mr-btn sec" type="button" id="camera_scan_capture">Tomar foto</button>'
        . '<input type="file" id="camera_scan_file" accept="image/*" capture="environment" style="display:none"></div>'
        . '<div class="mr-video"><video id="camera_scan_video" playsinline muted></video></div>';
    return mrSection('Paso 2 · Cargar códigos de trazabilidad por casillas', 'Define cuántas casillas necesitas, escanea todas las etiquetas seguidas y envíalas juntas con un solo botón.', $form)
        . mrSection('Escanear con cámara', 'Alternativa si no tienes pistola lectora (cada lectura se agrega a una casilla).', $cam);
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
