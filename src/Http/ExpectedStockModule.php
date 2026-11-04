<?php

declare(strict_types=1);

/**
 * Módulo HTTP · Stock esperado (Informes)
 *
 *  - /reports/stock-esperado ........... Informe (stock ERP contra objetivo) y maestro editable.
 *  - /reports/stock-esperado/data ...... JSON del informe (refresco automático).
 *  - /reports/stock-esperado/excel ..... Exportación a Excel.
 *  - /reports/stock-esperado/save|add|import (POST) .... Cambios al maestro.
 */

require_once __DIR__ . '/../ExpectedStockService.php';

function expectedStockRoute(string $path): bool
{
    return in_array($path, ['/reports/stock-esperado', '/reports/stock-esperado/data', '/reports/stock-esperado/excel',
        '/reports/stock-esperado/save', '/reports/stock-esperado/add', '/reports/stock-esperado/import'], true);
}

function handleExpectedStockRoutes(string $path, string $method, string $name): bool
{
    if (!expectedStockRoute($path)) {
        return false;
    }
    try {
        if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
            throw new RuntimeException('El informe de stock esperado requiere permisos del área ERP.');
        }
        $service = new ExpectedStockService(Db::erpPdo(), Db::trzPdo());
        $actor = ['name' => $name];
        if ($method === 'POST') {
            requireCsrf();
            $back = '/reports/stock-esperado?tab=master';
            if ($path === '/reports/stock-esperado/save') {
                $n = $service->saveRows((array)($_POST['rows'] ?? []), $actor);
                redirectResponse($back . '&saved=' . rawurlencode($n . ' cambio(s) guardado(s)'));
                return true;
            }
            if ($path === '/reports/stock-esperado/add') {
                $r = $service->addItem((string)($_POST['code'] ?? ''), (string)($_POST['description'] ?? ''), $actor);
                $msg = 'Agregado ' . $r['code'] . ($r['exists_in_erp'] === false ? ' (no existe en el ERP: revisa el código)' : '');
                redirectResponse($back . '&saved=' . rawurlencode($msg));
                return true;
            }
            if ($path === '/reports/stock-esperado/import') {
                $r = $service->importList((string)($_POST['list'] ?? ''), $actor);
                $msg = $r['added'] . ' código(s) agregado(s)' . ($r['skipped'] !== [] ? '. No agregados: ' . implode(' | ', array_slice($r['skipped'], 0, 4)) : '');
                redirectResponse($back . '&saved=' . rawurlencode($msg));
                return true;
            }
            return false;
        }
        if ($method !== 'GET') {
            return false;
        }
        if ($path === '/reports/stock-esperado/data') {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode($service->report(), JSON_UNESCAPED_UNICODE);
            return true;
        }
        if ($path === '/reports/stock-esperado/excel') {
            expectedStockExcel($service->report());
            return true;
        }
        $tab = (string)($_GET['tab'] ?? 'report') === 'master' ? 'master' : 'report';
        render('Stock esperado', expectedStockPage($service, $tab));
    } catch (Throwable $e) {
        if ($method === 'POST') {
            redirectResponse('/reports/stock-esperado?tab=master&error=' . rawurlencode($e->getMessage()));
            return true;
        }
        render('Stock esperado', expectedStockStyles() . '<div class="es-shell"><div class="es-alert err">' . h($e->getMessage()) . '</div></div>');
    }
    return true;
}

function expectedStockExcel(array $report): void
{
    $html = '<table><tr><th>Código</th><th>Descripción</th><th>Bodega</th><th>Stock</th><th>Reservado</th><th>Disponible</th><th>Pallets</th><th>Cajas</th><th>Unid. sueltas</th>'
        . '<th>Objetivo</th><th>Mínimo</th><th>Diferencia</th><th>Estado</th><th>Otra bodega</th></tr>';
    foreach ($report['rows'] as $r) {
        $html .= '<tr><td>' . h($r['code']) . '</td><td>' . h($r['description']) . '</td><td>' . h($r['warehouse_code'] ?? '') . '</td><td>' . (int)$r['stock'] . '</td><td>' . (int)$r['reserved']
            . '</td><td>' . (int)$r['available'] . '</td><td>' . h($r['pallets'] ?? '') . '</td><td>' . h($r['boxes'] ?? '') . '</td><td>' . h($r['loose'] ?? '')
            . '</td><td>' . h($r['target_units'] ?? '') . '</td><td>' . h($r['min_units'] ?? '') . '</td><td>' . h($r['diff'] ?? '') . '</td><td>' . h($r['status'])
            . '</td><td>' . (int)$r['other_stock'] . '</td></tr>';
    }
    SimpleXlsx::streamHtml('stock_esperado_' . date('Ymd_Hi') . '.xlsx', $html . '</table>', 'Stock esperado');
}

function expectedStockStyles(): string
{
    return '<style>
.es-shell{max-width:1500px;margin:0 auto;padding:6px 2px}
.es-head{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:12px}
.es-title{font-size:24px;font-weight:800;color:#0f172a}
.es-tabs{display:flex;gap:8px;flex-wrap:wrap}
.es-tab{padding:9px 16px;border-radius:10px;border:1px solid #cbd5e1;background:#fff;color:#0f172a;font-weight:700;text-decoration:none}
.es-tab.on{background:#0f766e;border-color:#0f766e;color:#fff}
.es-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px;margin-bottom:14px}
.es-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:end}
.es-bar label{display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:3px}
.es-bar input,.es-bar select,.es-card textarea,.es-in{padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;box-sizing:border-box}
.es-btn{display:inline-block;padding:9px 16px;border:0;border-radius:10px;background:#0f766e;color:#fff;font-weight:800;font-size:14px;cursor:pointer;text-decoration:none}
.es-btn.sec{background:#475569}
.es-wrap{overflow-x:auto}
.es-table{width:100%;border-collapse:collapse;font-size:13.5px}
.es-table th{background:#f1f5f9;text-align:left;padding:8px;font-size:11.5px;color:#475569;text-transform:uppercase;white-space:nowrap;position:sticky;top:0}
.es-table td{padding:7px 8px;border-top:1px solid #e2e8f0;vertical-align:middle}
.es-table td.n,.es-table th.n{text-align:right;font-variant-numeric:tabular-nums}
.es-pill{display:inline-block;border-radius:999px;padding:2px 10px;font-size:12px;font-weight:800;white-space:nowrap;background:#e2e8f0;color:#0f172a}
.es-pill.ok{background:#dcfce7;color:#166534}.es-pill.low{background:#fef3c7;color:#92400e}.es-pill.crit{background:#fee2e2;color:#991b1b}
.es-pill.cfg{background:#dbeafe;color:#1e40af}.es-pill.na{background:#f1f5f9;color:#64748b}
.es-alert{padding:12px 16px;border-radius:10px;margin-bottom:12px;font-weight:700}
.es-alert.err{background:#fee2e2;color:#991b1b}.es-alert.ok{background:#dcfce7;color:#166534}
.es-chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
.es-chip{border:1px solid #cbd5e1;border-radius:999px;padding:4px 12px;font-size:13px;font-weight:700;background:#fff;cursor:pointer}
.es-chip.on{background:#0f766e;color:#fff;border-color:#0f766e}
.es-in{width:100%}.es-in.num{width:90px;text-align:right}
.es-foot td{font-weight:800;background:#f8fafc}
.es-stamp{font-size:12px;color:#64748b}
@media(max-width:700px){.es-title{font-size:20px}}
</style>';
}

function expectedStockFlash(): string
{
    $html = '';
    if (!empty($_GET['error'])) {
        $html .= '<div class="es-alert err">' . h((string)$_GET['error']) . '</div>';
    }
    if (!empty($_GET['saved'])) {
        $html .= '<div class="es-alert ok">' . h((string)$_GET['saved']) . '</div>';
    }
    return $html;
}

function expectedStockPage(ExpectedStockService $service, string $tab): string
{
    $html = expectedStockStyles() . '<div class="es-shell"><div class="es-head"><div class="es-title">Stock esperado</div><div class="es-tabs">'
        . '<a class="es-tab' . ($tab === 'report' ? ' on' : '') . '" href="/reports/stock-esperado?tab=report">Informe</a>'
        . '<a class="es-tab' . ($tab === 'master' ? ' on' : '') . '" href="/reports/stock-esperado?tab=master">Maestro</a>'
        . '</div></div>' . expectedStockFlash();
    return $html . ($tab === 'master' ? expectedStockMaster($service) : expectedStockReport($service)) . '</div>';
}

function expectedStockReport(ExpectedStockService $service): string
{
    $report = $service->report();
    $json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    $html = '<div class="es-card"><div class="es-bar">'
        . '<div><label>Bodega</label><select id="es_wh"><option value="">Todas</option><option value="1000">1000</option><option value="400">400</option></select></div>'
        . '<div><label>Buscar</label><input type="text" id="es_q" placeholder="Código o descripción"></div>'
        . '<div><a class="es-btn sec" href="/reports/stock-esperado/excel">Excel</a></div>'
        . '<div class="es-stamp" id="es_stamp" style="margin-left:auto"></div></div></div>'
        . '<div class="es-chips" id="es_chips"></div><div id="es_err"></div>'
        . '<div class="es-card"><div class="es-wrap"><table class="es-table"><thead><tr>'
        . '<th>Código</th><th>Descripción</th><th>Bodega</th><th class="n">Stock</th><th class="n">Reservado</th><th class="n">Disponible</th>'
        . '<th class="n">Pallets</th><th class="n">Cajas</th><th class="n">Sueltas</th><th class="n">Objetivo</th><th class="n">Mínimo</th><th class="n">Diferencia</th>'
        . '<th>Estado</th><th class="n">Otra bodega</th></tr></thead><tbody id="es_body"></tbody><tfoot id="es_foot"></tfoot></table></div></div>';
    $js = <<<'JS'
(function(){
  var state = window.__ES, filterStatus = '', cls = {'OK':'ok','Bajo':'low','Crítico':'crit','Falta configurar':'cfg','Sin objetivo':'na','No existe en ERP':'crit','Sin conexión ERP':'crit'};
  function n(v){ return v===null||v===undefined ? '' : Number(v).toLocaleString('es-CL'); }
  function esc(s){ var d=document.createElement('div'); d.textContent=(s===null||s===undefined)?'':String(s); return d.innerHTML; }
  function visible(){
    var wh=document.getElementById('es_wh').value, q=document.getElementById('es_q').value.trim().toLowerCase();
    return state.rows.filter(function(r){
      return (!wh || String(r.warehouse_code)===wh) && (!filterStatus || r.status===filterStatus) &&
        (!q || (r.code+' '+r.description).toLowerCase().indexOf(q)>=0);
    });
  }
  function render(){
    var rows=visible(), counts={}, body='';
    state.rows.forEach(function(r){ counts[r.status]=(counts[r.status]||0)+1; });
    var chips='<span class="es-chip'+(filterStatus===''?' on':'')+'" data-s="">Todos '+state.rows.length+'</span>';
    Object.keys(counts).sort().forEach(function(s){ chips+='<span class="es-chip'+(filterStatus===s?' on':'')+'" data-s="'+esc(s)+'">'+esc(s)+' '+counts[s]+'</span>'; });
    document.getElementById('es_chips').innerHTML=chips;
    var tot={1000:{s:0,p:0},400:{s:0,p:0}};
    rows.forEach(function(r){
      body+='<tr title="'+esc(r.erp_title)+'"><td><b>'+esc(r.code)+'</b></td><td>'+esc(r.description)+'</td><td>'+esc(r.warehouse_code||'')+'</td>'+
        '<td class="n"><b>'+n(r.stock)+'</b></td><td class="n">'+n(r.reserved)+'</td><td class="n">'+n(r.available)+'</td>'+
        '<td class="n">'+n(r.pallets)+'</td><td class="n">'+n(r.boxes)+'</td><td class="n">'+n(r.loose)+'</td>'+
        '<td class="n">'+n(r.target_units)+'</td><td class="n">'+n(r.min_units)+'</td>'+
        '<td class="n" style="color:'+(r.diff!==null&&r.diff<0?'#b91c1c':'#166534')+'">'+(r.diff===null?'':(r.diff>0?'+':'')+n(r.diff))+'</td>'+
        '<td><span class="es-pill '+(cls[r.status]||'')+'">'+esc(r.status)+'</span></td><td class="n">'+n(r.other_stock)+'</td></tr>';
      if(tot[r.warehouse_code]){ tot[r.warehouse_code].s+=r.stock; tot[r.warehouse_code].p+=(r.pallets||0); }
    });
    document.getElementById('es_body').innerHTML=body||'<tr><td colspan="14">Sin productos.</td></tr>';
    document.getElementById('es_foot').innerHTML=[1000,400].map(function(w){
      return '<tr class="es-foot"><td colspan="3">Total bodega '+w+'</td><td class="n">'+n(tot[w].s)+'</td><td colspan="2"></td><td class="n">'+n(tot[w].p)+'</td><td colspan="7"></td></tr>';
    }).join('');
    document.getElementById('es_stamp').textContent='Actualizado '+state.generated_at.substr(11,8)+' · se refresca cada 30 s';
    document.getElementById('es_err').innerHTML=state.error?'<div class="es-alert err">'+esc(state.error)+'</div>':'';
  }
  document.getElementById('es_chips').addEventListener('click',function(e){ var s=e.target.getAttribute('data-s'); if(s!==null){ filterStatus=s; render(); } });
  document.getElementById('es_wh').addEventListener('change',render);
  document.getElementById('es_q').addEventListener('input',render);
  function refresh(){
    if(document.hidden) return;
    fetch('/reports/stock-esperado/data',{credentials:'same-origin',cache:'no-store'}).then(function(r){ return r.json(); })
      .then(function(d){ if(d&&d.rows){ state=d; render(); } })
      .catch(function(){ document.getElementById('es_err').innerHTML='<div class="es-alert err">Sin conexión con el servidor; se reintenta en 30 s.</div>'; });
  }
  setInterval(refresh,30000);
  document.addEventListener('visibilitychange',function(){ if(!document.hidden) refresh(); });
  render();
})();
JS;
    return $html . '<script>window.__ES=' . $json . ';</script><script>' . $js . '</script>';
}

function expectedStockMaster(ExpectedStockService $service): string
{
    $csrf = '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
    $html = '<div class="es-card"><div class="es-bar">'
        . '<form method="post" action="/reports/stock-esperado/add" class="es-bar">' . $csrf
        . '<div><label>Código</label><input type="text" name="code" placeholder="BOL0000" required maxlength="40"></div>'
        . '<div><label>Descripción</label><input type="text" name="description" maxlength="200" style="width:280px"></div>'
        . '<div><button class="es-btn" type="submit">Agregar</button></div></form></div></div>'
        . '<div class="es-card"><form method="post" action="/reports/stock-esperado/import">' . $csrf
        . '<label style="font-size:12px;font-weight:700;color:#475569">Pegar lista (una línea por producto: descripción y código separados por tabulación)</label>'
        . '<textarea name="list" rows="4" style="width:100%;margin:6px 0"></textarea><button class="es-btn sec" type="submit">Agregar lista</button></form></div>';
    $rows = $service->master(false);
    $html .= '<form method="post" action="/reports/stock-esperado/save" class="es-card">' . $csrf
        . '<div class="es-wrap"><table class="es-table"><thead><tr><th>Código</th><th>Descripción</th><th>Grupo</th><th>Bodega</th>'
        . '<th class="n">Unid. por caja</th><th class="n">Cajas por pallet</th><th class="n">Objetivo</th><th class="n">Mínimo</th><th>Activo</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        $w = (int)($r['warehouse_code'] ?? 0);
        $num = static fn(string $f): string => '<td class="n"><input class="es-in num" type="number" min="0" step="1" name="rows[' . $id . '][' . $f . ']" value="' . h($r[$f] ?? '') . '"></td>';
        $html .= '<tr' . ((int)$r['active'] === 1 ? '' : ' style="opacity:.55"') . '><td><b>' . h($r['item_code']) . '</b></td>'
            . '<td><input class="es-in" style="min-width:240px" name="rows[' . $id . '][description]" value="' . h($r['description']) . '" maxlength="200"></td>'
            . '<td><input class="es-in" style="width:110px" name="rows[' . $id . '][group_label]" value="' . h($r['group_label'] ?? '') . '" maxlength="80"></td>'
            . '<td><select class="es-in" name="rows[' . $id . '][warehouse_code]"><option value="">—</option>'
            . '<option value="1000"' . ($w === 1000 ? ' selected' : '') . '>1000</option><option value="400"' . ($w === 400 ? ' selected' : '') . '>400</option></select></td>'
            . $num('units_per_box') . $num('boxes_per_pallet') . $num('target_units') . $num('min_units')
            . '<td><input type="hidden" name="rows[' . $id . '][active]" value="0"><input type="checkbox" name="rows[' . $id . '][active]" value="1"' . ((int)$r['active'] === 1 ? ' checked' : '') . '></td></tr>';
    }
    $html .= '</tbody></table></div><div style="margin-top:12px"><button class="es-btn" type="submit">Guardar cambios</button></div></form>';
    $html .= '<div class="es-card"><b>Historial de cambios</b><div class="es-wrap"><table class="es-table"><thead><tr><th>Fecha</th><th>Código</th><th>Campo</th><th>Antes</th><th>Después</th><th>Usuario</th></tr></thead><tbody>';
    foreach ($service->history(60) as $hrow) {
        $html .= '<tr><td>' . h(substr((string)$hrow['created_at'], 0, 16)) . '</td><td>' . h($hrow['item_code']) . '</td><td>' . h(ExpectedStockService::fieldLabel((string)$hrow['field']))
            . '</td><td>' . h($hrow['old_value'] ?? '') . '</td><td>' . h($hrow['new_value'] ?? '') . '</td><td>' . h($hrow['actor']) . '</td></tr>';
    }
    return $html . '</tbody></table></div></div>';
}
