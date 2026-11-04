<?php

declare(strict_types=1);

/**
 * Módulo HTTP · Despacho (Bodega)
 *
 * Rutas:
 *  - /stock/dispatch ............ Nuevo picking y listado de pickeados.
 *  - /stock/dispatch/view ....... Detalle de un picking: escaneo, cierre, validación de guía y confirmación.
 *  - /stock/dispatch/create|scan|void|close|cancel|confirm (POST).
 */

require_once __DIR__ . '/../DispatchService.php';

function dispatchActor(string $name): array
{
    return ['user_id' => (int)($_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? 0), 'name' => $name];
}

function dispatchRoute(string $path): bool
{
    return in_array($path, ['/stock/dispatch', '/stock/dispatch/view', '/stock/dispatch/create', '/stock/dispatch/scan',
        '/stock/dispatch/void', '/stock/dispatch/close', '/stock/dispatch/cancel', '/stock/dispatch/confirm'], true);
}

function handleDispatchRoutes(string $path, string $method, string $name): bool
{
    if (!dispatchRoute($path)) {
        return false;
    }
    $key = (string)($_POST['picking_key'] ?? $_GET['key'] ?? '');
    $validKey = preg_match('/^[a-f0-9]{32}$/D', $key) === 1;
    $back = $validKey ? '/stock/dispatch/view?key=' . $key : '/stock/dispatch';
    try {
        if (!userCanAccessArea('ERP', sessionAreaPermissions()) && !userCanAccessArea('RECEPTION', sessionAreaPermissions())) {
            throw new RuntimeException('El despacho requiere permisos de bodega.');
        }
        $service = new DispatchService(Db::erpPdo(), Db::trzPdo());
        $actor = dispatchActor($name);
        if ($method === 'POST') {
            requireCsrf();
            $sep = str_contains($back, '?') ? '&' : '?';
            if ($path === '/stock/dispatch/create') {
                $key = $service->createPicking($key, (string)($_POST['dispatch_type'] ?? ''), (int)($_POST['warehouse_id'] ?? 0), (string)($_POST['note'] ?? ''), $actor);
                redirectResponse('/stock/dispatch/view?key=' . $key . '&saved=1');
                return true;
            }
            if ($path === '/stock/dispatch/scan') {
                $code = $service->scan($key, (string)($_POST['code'] ?? ''), $actor);
                redirectResponse($back . $sep . 'saved=' . rawurlencode('Agregado ' . $code));
                return true;
            }
            if ($path === '/stock/dispatch/void') {
                $service->voidItem((int)($_POST['item_id'] ?? 0), (string)($_POST['reason'] ?? ''), $actor);
                redirectResponse($back . $sep . 'saved=1');
                return true;
            }
            if ($path === '/stock/dispatch/close') {
                $service->close($key, $actor);
                redirectResponse($back . $sep . 'saved=1');
                return true;
            }
            if ($path === '/stock/dispatch/cancel') {
                $service->cancel($key, (string)($_POST['reason'] ?? ''), $actor);
                redirectResponse('/stock/dispatch?tab=list&saved=1');
                return true;
            }
            // Confirmar con la guía validada
            $guide = (string)($_POST['guide'] ?? '');
            try {
                $service->confirmGuide($key, $guide, !empty($_POST['accept_differences']), $actor);
            } catch (Throwable $e) {
                redirectResponse($back . $sep . 'guide=' . rawurlencode($guide) . '&error=' . rawurlencode($e->getMessage()));
                return true;
            }
            redirectResponse($back . $sep . 'saved=' . rawurlencode('Despacho confirmado'));
            return true;
        }
        if ($method !== 'GET') {
            return false;
        }
        if ($path === '/stock/dispatch/view') {
            if (!$validKey) {
                throw new RuntimeException('Picking no válido.');
            }
            render('Despacho', dispatchViewPage($service, $key, trim((string)($_GET['guide'] ?? ''))));
            return true;
        }
        render('Despacho', dispatchHomePage($service, (string)($_GET['tab'] ?? 'new')));
    } catch (Throwable $e) {
        if ($method === 'POST') {
            $sep = str_contains($back, '?') ? '&' : '?';
            redirectResponse($back . $sep . 'error=' . rawurlencode($e->getMessage()));
            return true;
        }
        render('Despacho', dispatchStyles() . '<div class="dsp-shell"><div class="dsp-alert err">' . h($e->getMessage()) . '</div></div>');
    }
    return true;
}

function dispatchStyles(): string
{
    return '<style>
.dsp-shell{max-width:1180px;margin:0 auto;padding:6px 2px}
.dsp-head{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:14px}
.dsp-title{font-size:24px;font-weight:800;color:#0f172a}
.dsp-tabs{display:flex;gap:8px;flex-wrap:wrap}
.dsp-tab{padding:9px 16px;border-radius:10px;border:1px solid #cbd5e1;background:#fff;color:#0f172a;font-weight:700;text-decoration:none}
.dsp-tab.on{background:#0f766e;border-color:#0f766e;color:#fff}
.dsp-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:16px;margin-bottom:14px}
.dsp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
.dsp-field label{display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:4px}
.dsp-field input[type=text],.dsp-field select,.dsp-field textarea{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-size:15px;box-sizing:border-box}
.dsp-types{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px}
.dsp-type{display:block;border:2px solid #cbd5e1;border-radius:12px;padding:14px;cursor:pointer;font-weight:800;color:#0f172a}
.dsp-type input{margin-right:8px}
.dsp-type:has(input:checked){border-color:#0f766e;background:#f0fdfa}
.dsp-btn{display:inline-block;padding:10px 18px;border:0;border-radius:10px;background:#0f766e;color:#fff;font-weight:800;font-size:15px;cursor:pointer;text-decoration:none}
.dsp-btn.sec{background:#475569}.dsp-btn.red{background:#b91c1c}.dsp-btn[disabled]{opacity:.45;cursor:not-allowed}
.dsp-table{width:100%;border-collapse:collapse;font-size:14px}
.dsp-table th{background:#f1f5f9;text-align:left;padding:8px;font-size:12px;color:#475569}
.dsp-table td{padding:8px;border-top:1px solid #e2e8f0;vertical-align:middle}
.dsp-wrap{overflow-x:auto}
.dsp-pill{display:inline-block;border-radius:999px;padding:2px 10px;font-size:12px;font-weight:800;background:#e2e8f0;color:#0f172a}
.dsp-pill.PICKING{background:#fef3c7;color:#92400e}.dsp-pill.AWAITING_GUIDE{background:#dbeafe;color:#1e40af}
.dsp-pill.DISPATCHED{background:#dcfce7;color:#166534}.dsp-pill.CANCELLED{background:#fee2e2;color:#991b1b}
.dsp-alert{padding:12px 16px;border-radius:10px;margin-bottom:14px;font-weight:700}
.dsp-alert.err{background:#fee2e2;color:#991b1b}.dsp-alert.ok{background:#dcfce7;color:#166534}.dsp-alert.warn{background:#fef3c7;color:#92400e}
.dsp-scan{display:flex;gap:10px;flex-wrap:wrap}.dsp-scan input{flex:1;min-width:220px;font-size:18px;padding:12px;border:2px solid #0f766e;border-radius:10px}
.dsp-kv{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px}
.dsp-kv b{display:block;font-size:12px;color:#475569}
.dsp-void{display:flex;gap:6px}.dsp-void input{width:150px;padding:6px;border:1px solid #cbd5e1;border-radius:6px}
@media(max-width:640px){.dsp-title{font-size:20px}.dsp-btn{width:100%;text-align:center}}
</style>';
}

function dispatchFlash(): string
{
    $html = '';
    if (!empty($_GET['error'])) {
        $html .= '<div class="dsp-alert err">' . h((string)$_GET['error']) . '</div>';
    }
    if (!empty($_GET['saved'])) {
        $msg = (string)$_GET['saved'];
        $html .= '<div class="dsp-alert ok">' . h($msg === '1' ? 'Guardado' : $msg) . '</div>';
    }
    return $html;
}

function dispatchHead(string $tab): string
{
    return '<div class="dsp-head"><div class="dsp-title">Despacho</div><div class="dsp-tabs">'
        . '<a class="dsp-tab' . ($tab === 'new' ? ' on' : '') . '" href="/stock/dispatch?tab=new">Nuevo picking</a>'
        . '<a class="dsp-tab' . ($tab === 'list' ? ' on' : '') . '" href="/stock/dispatch?tab=list">Pickeados</a>'
        . '</div></div>';
}

function dispatchHomePage(DispatchService $service, string $tab): string
{
    $tab = $tab === 'list' ? 'list' : 'new';
    $html = dispatchStyles() . '<div class="dsp-shell">' . dispatchHead($tab) . dispatchFlash();
    if ($tab === 'new') {
        $html .= '<form class="dsp-card" method="post" action="/stock/dispatch/create">'
            . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
            . '<input type="hidden" name="picking_key" value="' . bin2hex(random_bytes(16)) . '">'
            . '<div class="dsp-types">'
            . '<label class="dsp-type"><input type="radio" name="dispatch_type" value="SALE" required>Venta directa</label>'
            . '<label class="dsp-type"><input type="radio" name="dispatch_type" value="MAQUILA" required>Envío a procesar a otra empresa</label>'
            . '</div><div class="dsp-grid" style="margin-top:14px">'
            . '<div class="dsp-field"><label>Bodega de origen</label><select name="warehouse_id" required><option value="">Seleccionar</option>';
        foreach ($service->warehouses() as $w) {
            $html .= '<option value="' . (int)$w['id'] . '">' . h($w['code'] . ' · ' . $w['name']) . '</option>';
        }
        $html .= '</select></div><div class="dsp-field"><label>Nota</label><input type="text" name="note" maxlength="200"></div></div>'
            . '<div style="margin-top:14px"><button class="dsp-btn" type="submit">Crear picking</button></div></form>';
        $open = $service->list('PICKING');
        if ($open !== []) {
            $html .= '<div class="dsp-card"><b>Pickings en curso</b>' . dispatchTable($open) . '</div>';
        }
        return $html . '</div>';
    }
    $status = (string)($_GET['status'] ?? '');
    $type = (string)($_GET['type'] ?? '');
    $q = (string)($_GET['q'] ?? '');
    $html .= '<form class="dsp-card dsp-grid" method="get" action="/stock/dispatch"><input type="hidden" name="tab" value="list">'
        . '<div class="dsp-field"><label>Estado</label><select name="status"><option value="">Todos</option>';
    foreach (['PICKING', 'AWAITING_GUIDE', 'DISPATCHED', 'CANCELLED'] as $s) {
        $html .= '<option value="' . $s . '"' . ($status === $s ? ' selected' : '') . '>' . h(DispatchService::statusLabel($s)) . '</option>';
    }
    $html .= '</select></div><div class="dsp-field"><label>Tipo</label><select name="type"><option value="">Todos</option>'
        . '<option value="SALE"' . ($type === 'SALE' ? ' selected' : '') . '>Venta directa</option>'
        . '<option value="MAQUILA"' . ($type === 'MAQUILA' ? ' selected' : '') . '>Maquila</option></select></div>'
        . '<div class="dsp-field"><label>Código o guía</label><input type="text" name="q" value="' . h($q) . '"></div>'
        . '<div class="dsp-field"><label>&nbsp;</label><button class="dsp-btn" type="submit">Filtrar</button></div></form>';
    $rows = $service->list($status, $type, $q);
    return $html . '<div class="dsp-card">' . ($rows === [] ? 'Sin pickings.' : dispatchTable($rows)) . '</div></div>';
}

function dispatchTable(array $rows): string
{
    $html = '<div class="dsp-wrap"><table class="dsp-table"><thead><tr><th>Picking</th><th>Tipo</th><th>Bodega</th><th>Estado</th><th>Códigos</th><th>Unidades</th><th>Guía</th><th>Creado</th><th></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $html .= '<tr><td><b>' . h($r['code']) . '</b></td><td>' . h(DispatchService::typeLabel((string)$r['dispatch_type'])) . '</td>'
            . '<td>' . h($r['warehouse_name'] ?? '') . '</td>'
            . '<td><span class="dsp-pill ' . h($r['status']) . '">' . h(DispatchService::statusLabel((string)$r['status'])) . '</span></td>'
            . '<td>' . (int)$r['item_count'] . '</td><td>' . h(number_format((float)$r['qty_total'], 0, ',', '.')) . '</td>'
            . '<td>' . h($r['guide_number'] ?? '') . '</td><td>' . h(substr((string)$r['created_at'], 0, 16)) . '</td>'
            . '<td><a class="dsp-btn sec" style="padding:6px 12px;font-size:13px" href="/stock/dispatch/view?key=' . h($r['picking_key']) . '">Abrir</a></td></tr>';
    }
    return $html . '</tbody></table></div>';
}

function dispatchNum(float $n): string
{
    return number_format($n, 0, ',', '.');
}

function dispatchViewPage(DispatchService $service, string $key, string $guideNumber): string
{
    $p = $service->detail($key);
    $html = dispatchStyles() . '<div class="dsp-shell">' . dispatchHead('list') . dispatchFlash();
    $csrf = '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><input type="hidden" name="picking_key" value="' . h($key) . '">';
    $html .= '<div class="dsp-card"><div class="dsp-kv">'
        . '<div><b>Picking</b>' . h($p['code']) . '</div>'
        . '<div><b>Estado</b><span class="dsp-pill ' . h($p['status']) . '">' . h(DispatchService::statusLabel((string)$p['status'])) . '</span></div>'
        . '<div><b>Tipo</b>' . h(DispatchService::typeLabel((string)$p['dispatch_type'])) . '</div>'
        . '<div><b>Bodega de origen</b>' . h($p['warehouse_name'] ?? '') . '</div>'
        . '<div><b>Creado por</b>' . h($p['created_by']) . '</div>'
        . ($p['guide_number'] ? '<div><b>Guía</b>' . h($p['guide_number']) . '</div>' : '')
        . ($p['note'] ? '<div><b>Nota</b>' . h($p['note']) . '</div>' : '')
        . '</div></div>';

    if ($p['status'] === 'PICKING') {
        $html .= '<form class="dsp-card dsp-scan" method="post" action="/stock/dispatch/scan">' . $csrf
            . '<input type="text" name="code" placeholder="Escanear pallet o caja" autocomplete="off" autofocus required>'
            . '<button class="dsp-btn" type="submit">Agregar</button></form>';
    }

    $html .= '<div class="dsp-card"><div class="dsp-wrap"><table class="dsp-table"><thead><tr><th>Código</th><th>Tipo</th><th>Artículo</th><th>Cajas</th><th>Unidades</th><th>Estado</th><th></th></tr></thead><tbody>';
    $total = 0.0;
    foreach ($p['items'] as $it) {
        $active = $it['status'] !== 'VOIDED';
        if ($active) {
            $total += (float)$it['qty'];
        }
        $html .= '<tr' . ($active ? '' : ' style="opacity:.5"') . '><td><b>' . h($it['code']) . '</b></td>'
            . '<td>' . ($it['entity_type'] === 'PALLET' ? 'Pallet' : 'Caja') . '</td><td>' . h($it['sku']) . '</td>'
            . '<td>' . (int)$it['box_count'] . '</td><td>' . h(dispatchNum((float)$it['qty'])) . '</td>'
            . '<td>' . h($it['status'] === 'PICKED' ? 'Pickeado' : ($it['status'] === 'DISPATCHED' ? 'Despachado' : 'Quitado' . ($it['void_reason'] ? ': ' . $it['void_reason'] : ''))) . '</td><td>';
        if ($it['status'] === 'PICKED' && $p['status'] === 'PICKING') {
            $html .= '<form class="dsp-void" method="post" action="/stock/dispatch/void">' . $csrf
                . '<input type="hidden" name="item_id" value="' . (int)$it['id'] . '"><input type="text" name="reason" placeholder="Motivo" required minlength="5">'
                . '<button class="dsp-btn red" style="padding:6px 12px;font-size:13px" type="submit">Quitar</button></form>';
        }
        $html .= '</td></tr>';
    }
    if ($p['items'] === []) {
        $html .= '<tr><td colspan="7">Sin códigos.</td></tr>';
    }
    $html .= '</tbody></table></div><div style="margin-top:8px;font-weight:800">Total: ' . h(dispatchNum($total)) . ' unidades</div></div>';

    if ($p['status'] === 'PICKING') {
        $html .= '<div class="dsp-card" style="display:flex;gap:10px;flex-wrap:wrap">'
            . '<form method="post" action="/stock/dispatch/close">' . $csrf . '<button class="dsp-btn" type="submit">Cerrar picking y esperar guía</button></form>'
            . dispatchCancelForm($csrf) . '</div>';
    } elseif ($p['status'] === 'AWAITING_GUIDE') {
        $html .= '<form class="dsp-card dsp-scan" method="get" action="/stock/dispatch/view"><input type="hidden" name="key" value="' . h($key) . '">'
            . '<input type="text" name="guide" value="' . h($guideNumber) . '" placeholder="Número o folio de la guía de despacho" autocomplete="off" required>'
            . '<button class="dsp-btn" type="submit">Buscar guía</button></form>';
        if ($guideNumber !== '') {
            $html .= dispatchGuidePanel($service, $key, $guideNumber, $csrf);
        }
        $html .= '<div class="dsp-card">' . dispatchCancelForm($csrf) . '</div>';
    } elseif ($p['status'] === 'DISPATCHED' && is_array($p['guide'])) {
        $html .= '<div class="dsp-card"><b>Guía asociada</b>' . dispatchGuideSummary($p['guide'])
            . '<div style="margin-top:8px;color:#475569">Confirmado por ' . h($p['confirmed_by']) . ' el ' . h(substr((string)$p['confirmed_at'], 0, 16)) . '</div></div>';
    } elseif ($p['status'] === 'CANCELLED') {
        $html .= '<div class="dsp-alert warn">Picking cancelado: ' . h($p['cancel_reason']) . '</div>';
    }

    $html .= '<div class="dsp-card"><b>Historial</b><div class="dsp-wrap"><table class="dsp-table"><tbody>';
    foreach ($p['audit'] as $a) {
        $html .= '<tr><td>' . h(substr((string)$a['created_at'], 0, 16)) . '</td><td>' . h($a['action']) . '</td><td>' . h($a['actor']) . '</td></tr>';
    }
    return $html . '</tbody></table></div></div></div>';
}

function dispatchCancelForm(string $csrf): string
{
    return '<form class="dsp-void" method="post" action="/stock/dispatch/cancel" onsubmit="return confirm(\'¿Cancelar este picking?\')">' . $csrf
        . '<input type="text" name="reason" placeholder="Motivo de cancelación" required minlength="5" style="width:240px">'
        . '<button class="dsp-btn red" type="submit">Cancelar picking</button></form>';
}

function dispatchGuideSummary(array $g): string
{
    $html = '<div class="dsp-kv" style="margin-top:8px">'
        . '<div><b>Guía</b>' . h($g['number'] ?? '') . '</div><div><b>Folio SII</b>' . h($g['docnum'] ?? '') . '</div>'
        . '<div><b>Fecha</b>' . h($g['date'] ?? '') . '</div><div><b>Cliente</b>' . h($g['customer'] ?? '') . '</div>'
        . '<div><b>RUT</b>' . h($g['rut'] ?? '') . '</div><div><b>Dirección</b>' . h($g['address'] ?? '') . '</div>'
        . (!empty($g['oc']) ? '<div><b>OC</b>' . h($g['oc']) . '</div>' : '')
        . (!empty($g['bultos']) ? '<div><b>Bultos</b>' . h($g['bultos']) . '</div>' : '')
        . '</div><div class="dsp-wrap" style="margin-top:10px"><table class="dsp-table"><thead><tr><th>Artículo</th><th>Descripción</th><th>Cantidad</th></tr></thead><tbody>';
    foreach ($g['items'] ?? [] as $it) {
        $html .= '<tr><td>' . h($it['sku']) . '</td><td>' . h($it['title']) . '</td><td>' . h(dispatchNum((float)$it['qty'])) . '</td></tr>';
    }
    return $html . '</tbody></table></div>';
}

function dispatchGuidePanel(DispatchService $service, string $key, string $number, string $csrf): string
{
    $v = $service->validation($key, $number);
    $cmp = $v['compare'];
    $html = '<div class="dsp-card">';
    foreach ($cmp['errors'] as $e) {
        $html .= '<div class="dsp-alert err">' . h($e) . '</div>';
    }
    if ($v['guide'] !== null) {
        $html .= dispatchGuideSummary($v['guide']);
        $html .= '<div class="dsp-wrap" style="margin-top:12px"><table class="dsp-table"><thead><tr><th>Artículo</th><th>Pickeado</th><th>Guía</th><th></th></tr></thead><tbody>';
        foreach ($cmp['rows'] as $r) {
            $html .= '<tr><td>' . h($r['sku'] !== '' ? $r['sku'] : '(sin código)') . '</td><td>' . h(dispatchNum((float)$r['picked'])) . '</td><td>' . h(dispatchNum((float)$r['guide'])) . '</td>'
                . '<td>' . ($r['ok'] ? '✔' : '<span class="dsp-pill CANCELLED">Diferente</span>') . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
    }
    foreach ($cmp['warnings'] as $w) {
        $html .= '<div class="dsp-alert warn" style="margin-top:10px">' . h($w) . '</div>';
    }
    if ($cmp['errors'] === [] && $v['guide'] !== null) {
        $html .= '<form method="post" action="/stock/dispatch/confirm" style="margin-top:14px">' . $csrf
            . '<input type="hidden" name="guide" value="' . h($number) . '">';
        if ($cmp['warnings'] !== []) {
            $html .= '<label style="display:block;margin-bottom:10px;font-weight:700"><input type="checkbox" name="accept_differences" value="1" required> Acepto las diferencias y confirmo que esta es la guía correcta</label>';
        }
        $html .= '<button class="dsp-btn" type="submit" onclick="return confirm(\'Se descontarán los códigos de la bodega y quedarán asociados a la guía. ¿Confirmar?\')">Confirmar guía y despachar</button></form>';
    }
    return $html . '</div>';
}
