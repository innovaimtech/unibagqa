<?php

declare(strict_types=1);

/**
 * Módulo HTTP · Despacho (Bodega)
 *
 * Rutas:
 *  - /stock/dispatch ............ Nuevo picking y listado de pickeados.
 *  - /stock/dispatch/view ....... Detalle de un picking: escaneo, cierre, validación de guía y confirmación.
 *  - /stock/dispatch/create|scan|qty|void|close|cancel|confirm (POST).
 */

require_once __DIR__ . '/../DispatchService.php';

function dispatchActor(string $name): array
{
    return ['user_id' => (int)($_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? 0), 'name' => $name];
}

function dispatchRoute(string $path): bool
{
    return in_array($path, ['/stock/dispatch', '/stock/dispatch/view', '/stock/dispatch/create', '/stock/dispatch/scan', '/stock/dispatch/qty',
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
                $key = $service->createPicking($key, (string)($_POST['dispatch_type'] ?? ''), (string)($_POST['note'] ?? ''), $actor);
                redirectResponse('/stock/dispatch/view?key=' . $key . '&saved=1');
                return true;
            }
            if ($path === '/stock/dispatch/scan') {
                $code = $service->scan($key, (string)($_POST['code'] ?? ''), $actor);
                redirectResponse($back . $sep . 'saved=' . rawurlencode('Agregado ' . $code));
                return true;
            }
            if ($path === '/stock/dispatch/qty') {
                $service->setQuantity((int)($_POST['item_id'] ?? 0), (string)($_POST['qty'] ?? ''), $actor);
                redirectResponse($back . $sep . 'saved=' . rawurlencode('Cantidad actualizada'));
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
        render('Despacho', dispatchStyles() . '<div class="dsp"><div class="dsp-alert err">' . dispatchIcon('exclamation-circle') . '<div>' . h($e->getMessage()) . '</div></div></div>');
    }
    return true;
}

function dispatchStyles(): string
{
    return '<link rel="stylesheet" href="/assets/css/unibag-dispatch.css?v=20261105b">';
}

function dispatchIcon(string $name): string
{
    return '<i class="fas fa-' . h($name) . '" aria-hidden="true"></i>';
}

function dispatchStatusIcon(string $status): string
{
    return ['PICKING' => 'barcode', 'AWAITING_GUIDE' => 'file-alt', 'DISPATCHED' => 'truck', 'CANCELLED' => 'ban'][$status] ?? 'circle';
}

function dispatchPill(string $status): string
{
    return '<span class="dsp-pill tone-' . h($status) . '">' . h(DispatchService::statusLabel($status)) . '</span>';
}

function dispatchNum(float $n): string
{
    return number_format($n, 0, ',', '.');
}

function dispatchWarehouses(?string $names): string
{
    return str_replace(',', ', ', (string)$names);
}

function dispatchDate(?string $value): string
{
    $ts = $value ? strtotime($value) : false;
    return $ts ? date('d/m/Y H:i', $ts) : '';
}

function dispatchFlash(): string
{
    $html = '';
    if (!empty($_GET['error'])) {
        $html .= '<div class="dsp-alert err">' . dispatchIcon('exclamation-circle') . '<div>' . h((string)$_GET['error']) . '</div></div>';
    }
    if (!empty($_GET['saved'])) {
        $msg = (string)$_GET['saved'];
        $html .= '<div class="dsp-alert ok">' . dispatchIcon('check-circle') . '<div>' . h($msg === '1' ? 'Guardado' : $msg) . '</div></div>';
    }
    return $html;
}

function dispatchKpis(array $counts, string $current): string
{
    $html = '<div class="dsp-kpis">';
    foreach (['PICKING', 'AWAITING_GUIDE', 'DISPATCHED', 'CANCELLED'] as $status) {
        $html .= '<a class="dsp-kpi' . ($current === $status ? ' on' : '') . '" href="/stock/dispatch?tab=list&amp;status=' . $status . '">'
            . '<div class="dsp-kpi-icon tone-' . $status . '">' . dispatchIcon(dispatchStatusIcon($status)) . '</div>'
            . '<div><b>' . (int)($counts[$status] ?? 0) . '</b><span>' . h(DispatchService::statusLabel($status)) . '</span></div></a>';
    }
    return $html . '</div>';
}

function dispatchHomePage(DispatchService $service, string $tab): string
{
    $tab = $tab === 'list' ? 'list' : 'new';
    $status = (string)($_GET['status'] ?? '');
    $html = dispatchStyles() . '<div class="dsp"><div class="dsp-head"><h1>Despacho</h1></div>' . dispatchFlash()
        . dispatchKpis($service->counts(), $tab === 'list' ? $status : '')
        . '<div class="dsp-tabs">'
        . '<a class="dsp-tab' . ($tab === 'new' ? ' on' : '') . '" href="/stock/dispatch?tab=new">Nuevo picking</a>'
        . '<a class="dsp-tab' . ($tab === 'list' ? ' on' : '') . '" href="/stock/dispatch?tab=list">Pickeados</a>'
        . '</div>';
    return $html . ($tab === 'new' ? dispatchNewTab($service) : dispatchListTab($service, $status)) . '</div>';
}

function dispatchNewTab(DispatchService $service): string
{
    $html = '<div class="dsp-layout form-left"><form class="dsp-card" method="post" action="/stock/dispatch/create">'
        . '<div class="dsp-card-head"><h2>Nuevo picking</h2></div><div class="dsp-card-body">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="picking_key" value="' . bin2hex(random_bytes(16)) . '">'
        . '<div class="dsp-types">'
        . '<label class="dsp-type"><input type="radio" name="dispatch_type" value="SALE" required><span class="dsp-type-box">' . dispatchIcon('shopping-cart') . 'Venta directa</span></label>'
        . '<label class="dsp-type"><input type="radio" name="dispatch_type" value="MAQUILA" required><span class="dsp-type-box">' . dispatchIcon('industry') . 'Envío a procesar a otra empresa</span></label>'
        . '</div><div class="dsp-field"><label class="dsp-lbl" for="dsp-note">Nota</label><input id="dsp-note" type="text" name="note" maxlength="200"></div>'
        . '<button class="dsp-btn block" type="submit">' . dispatchIcon('plus') . 'Crear picking</button></div></form><div>';
    foreach (['PICKING' => 'Pickings en curso', 'AWAITING_GUIDE' => 'Esperando guía'] as $status => $title) {
        $rows = $service->list($status);
        $html .= '<div class="dsp-card"><div class="dsp-card-head"><h2>' . h($title) . '<span class="dsp-count">' . count($rows) . '</span></h2></div>'
            . ($rows === [] ? '<div class="dsp-empty">Sin pickings</div>' : dispatchTable($rows, false)) . '</div>';
    }
    return $html . '</div></div>';
}

function dispatchListTab(DispatchService $service, string $status): string
{
    $type = (string)($_GET['type'] ?? '');
    $q = (string)($_GET['q'] ?? '');
    $html = '<div class="dsp-card"><form class="dsp-filters" method="get" action="/stock/dispatch"><input type="hidden" name="tab" value="list">'
        . '<div><label class="dsp-lbl" for="dsp-f-st">Estado</label><select id="dsp-f-st" name="status"><option value="">Todos</option>';
    foreach (['PICKING', 'AWAITING_GUIDE', 'DISPATCHED', 'CANCELLED'] as $s) {
        $html .= '<option value="' . $s . '"' . ($status === $s ? ' selected' : '') . '>' . h(DispatchService::statusLabel($s)) . '</option>';
    }
    $html .= '</select></div><div><label class="dsp-lbl" for="dsp-f-ty">Tipo</label><select id="dsp-f-ty" name="type"><option value="">Todos</option>'
        . '<option value="SALE"' . ($type === 'SALE' ? ' selected' : '') . '>Venta directa</option>'
        . '<option value="MAQUILA"' . ($type === 'MAQUILA' ? ' selected' : '') . '>Maquila</option></select></div>'
        . '<div class="wide"><label class="dsp-lbl" for="dsp-f-q">Código o guía</label><input id="dsp-f-q" type="text" name="q" value="' . h($q) . '"></div>'
        . '<button class="dsp-btn" type="submit">' . dispatchIcon('search') . 'Filtrar</button>'
        . '<a class="dsp-btn sec" href="/stock/dispatch?tab=list">Limpiar</a></form>';
    $rows = $service->list($status, $type, $q);
    return $html . ($rows === [] ? '<div class="dsp-empty">' . dispatchIcon('box-open') . 'Sin pickings</div>' : dispatchTable($rows, true)) . '</div>';
}

function dispatchTable(array $rows, bool $withGuide): string
{
    $html = '<div class="dsp-wrap"><table class="dsp-table"><thead><tr><th>Picking</th><th>Tipo</th><th>Bodega</th><th>Estado</th><th class="num">Códigos</th><th class="num">Unidades</th>'
        . ($withGuide ? '<th>Guía</th>' : '') . '<th>Creado</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $url = '/stock/dispatch/view?key=' . h($r['picking_key']);
        $html .= '<tr class="link" onclick="location.href=\'' . $url . '\'">'
            . '<td class="dsp-code"><a href="' . $url . '">' . h($r['code']) . '</a></td>'
            . '<td class="dsp-nowrap">' . h(DispatchService::typeLabel((string)$r['dispatch_type'])) . '</td>'
            . '<td>' . h(dispatchWarehouses($r['warehouse_name'] ?? '')) . '</td>'
            . '<td>' . dispatchPill((string)$r['status']) . '</td>'
            . '<td class="num">' . (int)$r['item_count'] . '</td>'
            . '<td class="num">' . h(dispatchNum((float)$r['qty_total'])) . '</td>'
            . ($withGuide ? '<td class="dsp-nowrap">' . h($r['guide_number'] ?? '') . '</td>' : '')
            . '<td class="dsp-nowrap">' . h(dispatchDate((string)$r['created_at'])) . '<span class="dsp-sub">' . h($r['created_by'] ?? '') . '</span></td></tr>';
    }
    return $html . '</tbody></table></div>';
}

function dispatchSteps(string $status): string
{
    $current = ['PICKING' => 0, 'AWAITING_GUIDE' => 1, 'DISPATCHED' => 3][$status] ?? -1;
    $html = '<div class="dsp-steps">';
    foreach (['Picking', 'Guía de despacho', 'Despachado'] as $i => $label) {
        $state = $i < $current ? 'done' : ($i === $current ? 'now' : '');
        if ($i > 0) {
            $html .= '<div class="dsp-step-line' . ($i <= $current ? ' done' : '') . '"></div>';
        }
        $html .= '<div class="dsp-step ' . $state . '"><span class="dsp-step-dot">' . ($state === 'done' ? dispatchIcon('check') : ($i + 1)) . '</span>' . h($label) . '</div>';
    }
    return $html . '</div>';
}

function dispatchViewPage(DispatchService $service, string $key, string $guideNumber): string
{
    $p = $service->detail($key);
    $status = (string)$p['status'];
    $csrf = '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><input type="hidden" name="picking_key" value="' . h($key) . '">';

    $html = dispatchStyles() . '<div class="dsp"><div class="dsp-head"><div class="dsp-head-left">'
        . '<a class="dsp-back" href="/stock/dispatch?tab=list" aria-label="Volver a pickeados">' . dispatchIcon('arrow-left') . '</a>'
        . '<h1>' . h($p['code']) . '</h1>' . dispatchPill($status)
        . '<div class="dsp-meta"><span>' . dispatchIcon($p['dispatch_type'] === 'SALE' ? 'shopping-cart' : 'industry') . h(DispatchService::typeLabel((string)$p['dispatch_type'])) . '</span>'
        . (!empty($p['warehouse_name']) ? '<span>' . dispatchIcon('warehouse') . h(dispatchWarehouses($p['warehouse_name'])) . '</span>' : '') . '</div></div></div>' . dispatchFlash();

    if ($status === 'CANCELLED') {
        $html .= '<div class="dsp-alert warn">' . dispatchIcon('ban') . '<div>Picking cancelado: ' . h($p['cancel_reason']) . '</div></div>';
    } else {
        $html .= dispatchSteps($status);
    }

    $html .= '<div class="dsp-layout"><div>';
    if ($status === 'PICKING') {
        $html .= '<form class="dsp-card dsp-scan" method="post" action="/stock/dispatch/scan">' . $csrf
            . '<div class="dsp-scan-input">' . dispatchIcon('barcode') . '<input type="text" name="code" placeholder="Escanear pallet, caja o producto" aria-label="Código de pallet, caja o producto" autocomplete="off" autofocus required></div>'
            . '<button class="dsp-btn" type="submit">' . dispatchIcon('plus') . 'Agregar</button></form>';
    } elseif ($status === 'AWAITING_GUIDE') {
        $html .= '<form class="dsp-card dsp-scan" method="get" action="/stock/dispatch/view"><input type="hidden" name="key" value="' . h($key) . '">'
            . '<div class="dsp-scan-input">' . dispatchIcon('file-alt') . '<input type="text" name="guide" value="' . h($guideNumber) . '" placeholder="Número o folio de la guía de despacho" aria-label="Número o folio de la guía" autocomplete="off" autofocus required></div>'
            . '<button class="dsp-btn" type="submit">' . dispatchIcon('search') . 'Buscar guía</button></form>';
        if ($guideNumber !== '') {
            $html .= dispatchGuidePanel($service, $key, $guideNumber, $csrf);
        }
    } elseif ($status === 'DISPATCHED' && is_array($p['guide'])) {
        $html .= '<div class="dsp-card"><div class="dsp-card-head"><h2>Guía asociada</h2></div>' . dispatchGuideSummary($p['guide']) . '</div>';
    }

    // Códigos del picking
    $active = array_values(array_filter($p['items'], static fn(array $it): bool => $it['status'] !== 'VOIDED'));
    $totalQty = array_sum(array_map(static fn(array $it): float => (float)$it['qty'], $active));
    $totalBoxes = array_sum(array_map(static fn(array $it): int => (int)$it['box_count'], $active));
    $canVoid = $status === 'PICKING';
    $html .= '<div class="dsp-card"><div class="dsp-card-head"><h2>Códigos<span class="dsp-count">' . count($active) . '</span></h2></div>';
    if ($p['items'] === []) {
        $html .= '<div class="dsp-empty">' . dispatchIcon('barcode') . 'Sin códigos</div>';
    } else {
        $html .= '<div class="dsp-wrap"><table class="dsp-table"><thead><tr><th>Código</th><th>Tipo</th><th>Bodega</th><th>Artículo</th><th class="num">Cajas</th><th class="num">Unidades</th><th>Estado</th>' . ($canVoid ? '<th></th>' : '') . '</tr></thead><tbody>';
        foreach ($p['items'] as $it) {
            $voided = $it['status'] === 'VOIDED';
            $itemStatus = $it['status'] === 'PICKED' ? 'Pickeado' : ($it['status'] === 'DISPATCHED' ? 'Despachado' : 'Quitado');
            $html .= '<tr' . ($voided ? ' class="voided"' : '') . '><td class="dsp-code">' . h($it['code']) . '</td>'
                . '<td><span class="dsp-chip">' . (['PALLET' => 'Pallet', 'BOX' => 'Caja', 'PRODUCT' => 'Producto'][$it['entity_type']] ?? '') . '</span></td>'
                . '<td>' . h($it['from_warehouse_name'] ?? '') . '</td>'
                . '<td>' . h($it['sku']) . '</td>'
                . '<td class="num">' . (int)$it['box_count'] . '</td>'
                . '<td class="num">' . dispatchQtyCell($it, $canVoid, $csrf) . '</td>'
                . '<td>' . h($itemStatus) . ($voided && $it['void_reason'] ? '<span class="dsp-sub">' . h($it['void_reason']) . '</span>' : '') . '</td>';
            if ($canVoid) {
                $html .= '<td class="num">';
                if ($it['status'] === 'PICKED') {
                    $html .= '<details class="dsp-fold"><summary class="dsp-btn danger sm">Quitar</summary>'
                        . '<form class="dsp-fold-form" method="post" action="/stock/dispatch/void">' . $csrf
                        . '<input type="hidden" name="item_id" value="' . (int)$it['id'] . '">'
                        . '<input type="text" name="reason" placeholder="Motivo" aria-label="Motivo para quitar el código" required minlength="5">'
                        . '<button class="dsp-btn solid-danger sm" type="submit">Quitar código</button></form></details>';
                }
                $html .= '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
    }
    $html .= '</div></div>';

    // Columna lateral: resumen, acciones e historial
    $html .= '<aside><div class="dsp-card"><div class="dsp-totals">'
        . '<div class="dsp-total"><b>' . count($active) . '</b><span>Códigos</span></div>'
        . '<div class="dsp-total"><b>' . h(dispatchNum((float)$totalBoxes)) . '</b><span>Cajas</span></div>'
        . '<div class="dsp-total"><b>' . h(dispatchNum((float)$totalQty)) . '</b><span>Unidades</span></div></div>'
        . '<dl class="dsp-kv">'
        . '<div><dt>Creado por</dt><dd>' . h($p['created_by']) . '</dd></div>'
        . '<div><dt>Fecha</dt><dd>' . h(dispatchDate((string)$p['created_at'])) . '</dd></div>'
        . ($p['guide_number'] ? '<div><dt>Guía</dt><dd>' . h($p['guide_number']) . '</dd></div>' : '')
        . ($p['confirmed_by'] ? '<div><dt>Confirmado por</dt><dd>' . h($p['confirmed_by']) . '</dd></div><div><dt>Confirmado el</dt><dd>' . h(dispatchDate((string)$p['confirmed_at'])) . '</dd></div>' : '')
        . ($p['note'] ? '<div><dt>Nota</dt><dd>' . h($p['note']) . '</dd></div>' : '')
        . '</dl>';
    if ($status === 'PICKING' || $status === 'AWAITING_GUIDE') {
        $html .= '<div class="dsp-actions">';
        if ($status === 'PICKING') {
            $html .= '<form method="post" action="/stock/dispatch/close">' . $csrf . '<button class="dsp-btn block" type="submit">' . dispatchIcon('lock') . 'Cerrar picking y esperar guía</button></form>';
        }
        $html .= '<details class="dsp-fold"><summary class="dsp-btn danger block">Cancelar picking</summary>'
            . '<form class="dsp-fold-form" method="post" action="/stock/dispatch/cancel" onsubmit="return confirm(\'¿Cancelar este picking?\')">' . $csrf
            . '<input type="text" name="reason" placeholder="Motivo de cancelación" aria-label="Motivo de cancelación" required minlength="5">'
            . '<button class="dsp-btn solid-danger block" type="submit">Confirmar cancelación</button></form></details></div>';
    }
    $html .= '</div>';

    $html .= '<div class="dsp-card"><div class="dsp-card-head"><h2>Historial</h2></div><ul class="dsp-log">';
    foreach (array_reverse($p['audit']) as $a) {
        $html .= '<li><b>' . h(dispatchAuditLabel((string)$a['action'], (string)($a['detail'] ?? ''))) . '</b><span>' . h($a['actor']) . ' · ' . h(dispatchDate((string)$a['created_at'])) . '</span></li>';
    }
    return $html . '</ul></div></aside></div></div>';
}

/** Unidades de la línea: editable mientras el picking está abierto, con el saldo que dejará en el código. */
function dispatchQtyCell(array $it, bool $editable, string $csrf): string
{
    $qty = (float)$it['qty'];
    $full = (float)($it['full_qty'] ?? $qty);
    $balance = $full - $qty > 0.0005 ? '<span class="dsp-sub">de ' . h(dispatchNum($full)) . ' · saldo ' . h(dispatchNum($full - $qty)) . '</span>' : '';
    if (!$editable || $it['status'] !== 'PICKED' || $it['entity_type'] === 'PALLET') {
        return h(dispatchNum($qty)) . $balance;
    }
    $value = rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
    return '<form class="dsp-qty" method="post" action="/stock/dispatch/qty">' . $csrf
        . '<input type="hidden" name="item_id" value="' . (int)$it['id'] . '">'
        . '<input type="number" name="qty" value="' . h($value) . '" min="0.001" max="' . h((string)$full) . '" step="any" required aria-label="Unidades a despachar de ' . h($it['code']) . '">'
        . '<button class="dsp-btn sec sm" type="submit">Guardar</button></form>' . $balance;
}

function dispatchAuditLabel(string $action, string $detailJson): string
{
    $detail = json_decode($detailJson, true);
    $detail = is_array($detail) ? $detail : [];
    return match ($action) {
        'CREATE' => 'Picking creado',
        'SCAN' => 'Agregado ' . ($detail['code'] ?? ''),
        'QTY' => 'Cantidad de ' . ($detail['code'] ?? '') . ': ' . dispatchNum((float)($detail['qty'] ?? 0)) . ' de ' . dispatchNum((float)($detail['available'] ?? 0)),
        'VOID' => 'Quitado ' . ($detail['code'] ?? ''),
        'CLOSE' => 'Picking cerrado',
        'CANCEL' => 'Picking cancelado',
        'CONFIRM' => 'Despacho confirmado' . (!empty($detail['guide']) ? ' con guía ' . $detail['guide'] : ''),
        default => $action,
    };
}

function dispatchGuideSummary(array $g): string
{
    $fields = ['Guía' => $g['number'] ?? '', 'Folio SII' => $g['docnum'] ?? '', 'Fecha' => $g['date'] ?? '', 'Cliente' => $g['customer'] ?? '',
        'RUT' => $g['rut'] ?? '', 'Dirección' => $g['address'] ?? '', 'OC' => $g['oc'] ?? '', 'Bultos' => $g['bultos'] ?? ''];
    $html = '<div class="dsp-guide-grid">';
    foreach ($fields as $label => $value) {
        if ((string)$value !== '' && (string)$value !== '0') {
            $html .= '<div><span>' . h($label) . '</span><b>' . h((string)$value) . '</b></div>';
        }
    }
    $html .= '</div><div class="dsp-wrap"><table class="dsp-table"><thead><tr><th>Artículo</th><th>Descripción</th><th class="num">Cantidad</th></tr></thead><tbody>';
    foreach ($g['items'] ?? [] as $it) {
        $html .= '<tr><td class="dsp-code">' . h($it['sku']) . '</td><td>' . h($it['title']) . '</td><td class="num">' . h(dispatchNum((float)$it['qty'])) . '</td></tr>';
    }
    return $html . '</tbody></table></div>';
}

function dispatchGuidePanel(DispatchService $service, string $key, string $number, string $csrf): string
{
    $v = $service->validation($key, $number);
    $cmp = $v['compare'];
    $html = '';
    foreach ($cmp['errors'] as $e) {
        $html .= '<div class="dsp-alert err">' . dispatchIcon('exclamation-circle') . '<div>' . h($e) . '</div></div>';
    }
    if ($v['guide'] === null) {
        return $html;
    }
    $html .= '<div class="dsp-card"><div class="dsp-card-head"><h2>Guía de despacho</h2></div>' . dispatchGuideSummary($v['guide'])
        . '<div class="dsp-section-title" style="border-top:1px solid var(--line)">Pickeado contra guía</div>'
        . '<div class="dsp-wrap"><table class="dsp-table"><thead><tr><th>Artículo</th><th class="num">Pickeado</th><th class="num">Guía</th><th>Resultado</th></tr></thead><tbody>';
    foreach ($cmp['rows'] as $r) {
        $html .= '<tr><td class="dsp-code">' . h($r['sku'] !== '' ? $r['sku'] : '(sin código)') . '</td>'
            . '<td class="num">' . h(dispatchNum((float)$r['picked'])) . '</td><td class="num">' . h(dispatchNum((float)$r['guide'])) . '</td><td>'
            . ($r['ok'] ? '<span class="dsp-diff ok">' . dispatchIcon('check') . 'Coincide</span>' : '<span class="dsp-diff bad">' . dispatchIcon('exclamation-triangle') . 'Diferente</span>')
            . '</td></tr>';
    }
    $html .= '</tbody></table></div>';
    if ($cmp['warnings'] !== []) {
        $html .= '<div class="dsp-card-body" style="border-top:1px solid var(--line)">';
        foreach ($cmp['warnings'] as $w) {
            $html .= '<div class="dsp-alert warn">' . dispatchIcon('exclamation-triangle') . '<div>' . h($w) . '</div></div>';
        }
        $html .= '</div>';
    }
    if ($cmp['errors'] === []) {
        $html .= '<form class="dsp-confirm" method="post" action="/stock/dispatch/confirm">' . $csrf
            . '<input type="hidden" name="guide" value="' . h($number) . '">';
        if ($cmp['warnings'] !== []) {
            $html .= '<label><input type="checkbox" name="accept_differences" value="1" required> Acepto las diferencias y confirmo que esta es la guía correcta</label>';
        }
        $html .= '<button class="dsp-btn" type="submit" onclick="return confirm(\'Se descontarán de cada bodega las unidades indicadas y quedarán asociadas a la guía. ¿Confirmar?\')">' . dispatchIcon('truck') . 'Confirmar guía y despachar</button></form>';
    }
    return $html . '</div>';
}
