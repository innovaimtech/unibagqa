<?php

declare(strict_types=1);

/**
 * Módulo HTTP · Bodega → Traspasos (reemplaza «Traspasos a Producción»).
 *
 *  - GET  /stock/traspasos ................ Traspasar: casillas de escaneo → «Cargar» → lista → destino → «Traspasar».
 *  - GET  /stock/traspasos?tab=historial ... Historial (quién envió, quién aceptó o rechazó).
 *  - GET  /stock/traspasos/ver?id=N ........ Detalle con los códigos y los documentos del ERP.
 *  - GET  /stock/traspasos/aceptar ......... Traspasos a la 3000 por aceptar (supervisor con usuario y clave).
 *  - POST /stock/traspasos/enviar | /aceptar | /rechazar | /reintentar
 *
 * Enviar: áreas ERP o Recepción. Aceptar/rechazar: cualquier sesión, con usuario y clave de supervisor del ERP.
 * Pantallas sin subtítulos ni textos de ayuda (pedido del usuario).
 */

require_once __DIR__ . '/../StockTransferService.php';

const STOCK_TRANSFER_ASSET_VERSION = '20261113a';

function stockTransferCanSend(): bool
{
    $perms = sessionAreaPermissions();
    return userCanAccessArea('ERP', $perms) || userCanAccessArea('RECEPTION', $perms);
}

function stockTransferUserId(): int
{
    return (int)($_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? 0);
}

function handleStockTransferRoutes(string $path, string $method, string $operator): bool
{
    // La pantalla anterior («Traspasos a Producción») se reemplazó por esta.
    if (in_array($path, ['/stock/transfers', '/stock/transfers/sync'], true) && $method === 'GET') {
        redirectResponse('/stock/traspasos');
        return true;
    }
    if ($path !== '/stock/traspasos' && !str_starts_with($path, '/stock/traspasos/')) {
        return false;
    }
    $styles = '<link rel="stylesheet" href="/assets/css/unibag-code-adjust.css?v=' . CODE_ADJUST_ASSET_VERSION . '">'
        . '<link rel="stylesheet" href="/assets/css/unibag-stock-transfer.css?v=' . STOCK_TRANSFER_ASSET_VERSION . '">';
    $isAccept = $path === '/stock/traspasos/aceptar' || $path === '/stock/traspasos/rechazar';
    if (!$isAccept && !stockTransferCanSend()) {
        render('Traspasos', $styles . '<div class="ca-shell"><div class="ca-alert err">Requiere permisos del área ERP o Recepción (Bodega).</div></div>');
        return true;
    }
    try {
        $service = new StockTransferService(Db::trzPdo(), Db::erpPdo());
        $service->ensureSchema();
    } catch (Throwable $e) {
        render('Traspasos', $styles . '<div class="ca-shell"><div class="ca-alert err">' . h($e->getMessage()) . '</div></div>');
        return true;
    }

    if ($method === 'POST') {
        requireCsrf();
        $id = (int)($_POST['id'] ?? 0);
        try {
            if ($path === '/stock/traspasos/enviar') {
                $codes = StockTransferService::parseCodes(explode(',', (string)($_POST['codes'] ?? '')));
                $res = $service->create($codes, (int)($_POST['to_st'] ?? 0), ['user_id' => stockTransferUserId(), 'name' => $operator], (string)($_POST['note'] ?? ''));
                $msg = $res['status'] === 'PENDING'
                    ? 'Traspaso N° ' . $res['id'] . ' enviado: queda por aceptar en la 3000.'
                    : 'Traspaso N° ' . $res['id'] . ' realizado.';
                redirectResponse('/stock/traspasos/ver?id=' . $res['id'] . '&ok=' . rawurlencode($msg));
            } elseif ($path === '/stock/traspasos/aceptar') {
                $t = $service->accept($id, (string)($_POST['login'] ?? ''), (string)($_POST['password'] ?? ''));
                redirectResponse('/stock/traspasos/aceptar?ok=' . rawurlencode('Traspaso N° ' . $id . ' aceptado por ' . $t['accepted_by_name'] . '.'));
            } elseif ($path === '/stock/traspasos/rechazar') {
                $t = $service->reject($id, (string)($_POST['reason'] ?? ''), (string)($_POST['login'] ?? ''), (string)($_POST['password'] ?? ''));
                redirectResponse('/stock/traspasos/aceptar?ok=' . rawurlencode('Traspaso N° ' . $id . ' rechazado por ' . $t['accepted_by_name'] . '.'));
            } elseif ($path === '/stock/traspasos/reintentar') {
                $service->retry($id, stockTransferUserId());
                redirectResponse('/stock/traspasos/ver?id=' . $id . '&ok=' . rawurlencode('Traspaso N° ' . $id . ' realizado.'));
            } else {
                return false;
            }
        } catch (Throwable $e) {
            $back = match ($path) {
                '/stock/traspasos/enviar' => '/stock/traspasos?codes=' . rawurlencode((string)($_POST['codes'] ?? '')),
                '/stock/traspasos/reintentar' => '/stock/traspasos/ver?id=' . $id,
                default => '/stock/traspasos/aceptar',
            };
            // Si el traspaso alcanzó a registrarse (quedó con error), se muestra su detalle para reintentar.
            if ($path === '/stock/traspasos/enviar' && ($last = $service->history(1)[0] ?? null) !== null
                && (string)$last['status'] === 'ERROR' && (int)$last['sent_at'] >= time() - 30) {
                $back = '/stock/traspasos/ver?id=' . (int)$last['id'];
            }
            redirectResponse($back . (str_contains($back, '?') ? '&' : '?') . 'err=' . rawurlencode($e->getMessage()));
        }
        return true;
    }
    if ($method !== 'GET') {
        return false;
    }

    $ok = (string)($_GET['ok'] ?? '');
    $err = (string)($_GET['err'] ?? '');
    try {
        if ($path === '/stock/traspasos/aceptar') {
            $body = stockTransferAcceptPage($service->pending());
            $tab = 'aceptar';
        } elseif ($path === '/stock/traspasos/ver') {
            $t = $service->transfer((int)($_GET['id'] ?? 0));
            if ($t === null) {
                throw new RuntimeException('No existe el traspaso.');
            }
            $body = stockTransferDetailPage($t, $service->items((int)$t['id']));
            $tab = 'historial';
        } elseif ($path === '/stock/traspasos') {
            $tab = (string)($_GET['tab'] ?? '') === 'historial' ? 'historial' : 'traspasar';
            if ($tab === 'historial') {
                $body = stockTransferHistoryPage($service->history(100));
            } else {
                $raw = $_GET['codes'] ?? [];
                $codes = StockTransferService::parseCodes(is_array($raw) ? $raw : explode(',', (string)$raw));
                $body = stockTransferSendPage($service->inspect($codes), $service->storehouses(), (int)($_GET['to'] ?? 0));
            }
        } else {
            return false;
        }
    } catch (Throwable $e) {
        $err = $err !== '' ? $err : $e->getMessage();
        $body = '';
        $tab = $tab ?? 'traspasar';
    }
    $pendingCount = 0;
    try {
        $pendingCount = $service->pendingCount();
    } catch (Throwable) {
    }
    render('Traspasos', $styles . stockTransferLayout($tab, $ok, $err, $body, $pendingCount));
    return true;
}

function stTrN(float $v): string
{
    return StockLedgerService::fmt($v);
}

function stockTransferStatus(string $s): string
{
    return [
        'PENDING' => '<span class="ca-tag warn">Por aceptar</span>',
        'DONE' => '<span class="ca-tag ok">Realizado</span>',
        'REJECTED' => '<span class="ca-tag mute">Rechazado</span>',
        'ERROR' => '<span class="ca-tag err">Error</span>',
        'SENDING' => '<span class="ca-tag mute">En proceso</span>',
    ][$s] ?? h($s);
}

function stockTransferLayout(string $tab, string $ok, string $err, string $body, int $pendingCount): string
{
    $html = '<div class="ca-shell"><div class="ca-head"><div class="ca-title">Traspasos</div><nav class="ca-tabs">';
    if (stockTransferCanSend()) {
        $html .= '<a class="' . ($tab === 'traspasar' ? 'on' : '') . '" href="/stock/traspasos">🔄 Traspasar</a>';
    }
    $html .= '<a class="' . ($tab === 'aceptar' ? 'on' : '') . '" href="/stock/traspasos/aceptar">✅ Por aceptar'
        . ($pendingCount > 0 ? ' <b class="st-badge">' . $pendingCount . '</b>' : '') . '</a>';
    if (stockTransferCanSend()) {
        $html .= '<a class="' . ($tab === 'historial' ? 'on' : '') . '" href="/stock/traspasos?tab=historial">📜 Historial</a>';
    }
    $html .= '</nav></div>';
    if ($ok !== '') {
        $html .= '<div class="ca-alert ok">✔ ' . h($ok) . '</div>';
    }
    if ($err !== '') {
        $html .= '<div class="ca-alert err">✖ ' . h($err) . '</div>';
    }
    return $html . $body . '</div>';
}

/** Tabla de códigos (cargados o de un traspaso). @param list<array<string,mixed>> $rows */
function stockTransferCodesTable(array $rows, bool $editable, array $loadedCodes = []): string
{
    $html = '<div class="ca-table-wrap"><table class="ca-table"><thead><tr><th>#</th><th>Código</th><th>Artículo</th><th>CC</th><th>Desde</th>'
        . '<th class="num">Cantidad</th>' . ($editable ? '<th></th><th></th>' : '<th>Doc. ERP</th>') . '</tr></thead><tbody>';
    foreach ($rows as $i => $r) {
        $problem = (string)($r['problem'] ?? '');
        $html .= '<tr' . ($problem !== '' ? ' class="bad"' : '') . '><td>' . ($i + 1) . '</td><td class="mono"><b>' . h((string)$r['code']) . '</b></td>'
            . '<td>' . h((string)$r['item_code']) . ' <span class="ca-muted">' . h((string)$r['description']) . '</span></td>'
            . '<td>' . h((string)($r['order_ref'] ?? '') !== '' ? (string)$r['order_ref'] : '—') . '</td>'
            . '<td>' . h((string)$r['from_name']) . '</td>'
            . '<td class="num">' . ((float)$r['qty'] > 0 ? stTrN((float)$r['qty']) : '') . '</td>';
        if ($editable) {
            $others = array_values(array_filter($loadedCodes, static fn($x) => $x !== $r['code']));
            $html .= '<td>' . ($problem !== '' ? '<span class="ca-bad">✖ ' . h($problem) . '</span>' : '<span class="ca-ok">✓</span>') . '</td>'
                . '<td><a class="ca-x" href="/stock/traspasos' . ($others !== [] ? '?codes=' . h(rawurlencode(implode(',', $others))) : '') . '">✕</a></td>';
        } else {
            $html .= '<td class="mono">' . h((string)($r['erp_doc'] ?? '')) . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

/**
 * @param list<array<string,mixed>> $rows códigos cargados con su revisión
 * @param array<int,string> $stores
 */
function stockTransferSendPage(array $rows, array $stores, int $to): string
{
    $loaded = array_column($rows, 'code');
    $valid = array_values(array_filter($rows, static fn($r) => $r['problem'] === ''));
    $html = '<form class="ca-card ca-slots" method="get" action="/stock/traspasos" autocomplete="off" data-existing="' . h((string)json_encode($loaded)) . '">';
    foreach ($loaded as $c) {
        $html .= '<input type="hidden" name="codes[]" value="' . h($c) . '">';
    }
    $html .= '<div class="ca-slot-bar"><span class="ca-slot-summary"></span></div><div class="ca-slot-list"></div>'
        . '<button type="submit" class="ca-btn ca-load-btn" data-label="Cargar" disabled>Cargar (0)</button></form>';
    if ($rows !== []) {
        $html .= '<div class="ca-card">' . stockTransferCodesTable($rows, true, $loaded);
        $byFrom = [];
        foreach ($valid as $r) {
            $byFrom[$r['from_name']] = ($byFrom[$r['from_name']] ?? 0) + 1;
        }
        if ($byFrom !== []) {
            $html .= '<div class="st-from">';
            foreach ($byFrom as $name => $n) {
                $html .= '<span>' . h((string)$name) . ' <b>' . $n . '</b></span>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
        $options = '<option value=""></option>';
        foreach ($stores as $id => $name) {
            $options .= '<option value="' . $id . '"' . ($id === $to ? ' selected' : '') . '>' . h($name) . ($id === StockTransferService::PRODUCTION_ST ? ' · requiere aceptación' : '') . '</option>';
        }
        $html .= '<form class="ca-card st-send" method="post" action="/stock/traspasos/enviar" id="st_send">'
            . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
            . '<input type="hidden" name="codes" value="' . h(implode(',', array_column($valid, 'code'))) . '">'
            . '<label>Bodega de destino<select name="to_st" required>' . $options . '</select></label>'
            . '<label>Nota<input name="note" maxlength="200"></label>'
            . '<button type="submit" class="ca-btn"' . ($valid === [] ? ' disabled' : '') . ' data-n="' . count($valid) . '">Traspasar (' . count($valid) . ')</button></form>';
    }
    $html .= '<script src="/assets/js/unibag-scan-slots.js?v=' . CODE_ADJUST_ASSET_VERSION . '"></script>';
    $html .= <<<'JS'
<script>
(function () {
  var f = document.getElementById("st_send");
  if (f) f.addEventListener("submit", function (e) {
    var b = f.querySelector("button[type=submit]"), sel = f.querySelector("select");
    if (!window.confirm("¿Traspasar " + b.getAttribute("data-n") + " código(s) a " + sel.options[sel.selectedIndex].text + "?")) { e.preventDefault(); return; }
    b.disabled = true; b.textContent = "Traspasando…";
  });
})();
</script>
JS;
    return $html;
}

/** @param list<array<string,mixed>> $pending */
function stockTransferAcceptPage(array $pending): string
{
    if ($pending === []) {
        return '<div class="ca-card"><div class="ca-empty">0 traspasos por aceptar</div></div>';
    }
    $html = '';
    foreach ($pending as $t) {
        $id = (int)$t['id'];
        $units = array_sum(array_map(static fn($i) => (float)$i['qty'], $t['items']));
        $html .= '<div class="ca-card st-pending">'
            . '<div class="st-pend-head"><div><b class="st-num">N° ' . $id . '</b> → ' . h((string)$t['to_name']) . '</div>'
            . '<div class="st-who">' . h((string)$t['sent_by_name']) . ' · ' . h(date('d-m-Y H:i', (int)$t['sent_at'])) . '</div>'
            . '<div class="st-sum">' . count($t['items']) . ' código(s) · ' . stTrN($units) . ' unid.</div></div>'
            . ($t['note'] ? '<div class="st-note">' . h((string)$t['note']) . '</div>' : '')
            . stockTransferCodesTable($t['items'], false)
            . '<div class="st-actions">'
            . '<form method="post" action="/stock/traspasos/aceptar" class="st-auth" data-confirm="¿Aceptar el traspaso N° ' . $id . '?" autocomplete="off">'
            . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><input type="hidden" name="id" value="' . $id . '">'
            . '<label>Usuario<input name="login" required autocomplete="off"></label>'
            . '<label>Clave<input type="password" name="password" required autocomplete="new-password"></label>'
            . '<button type="submit" class="ca-btn">Aceptar</button></form>'
            . '<details class="st-reject"><summary>Rechazar</summary>'
            . '<form method="post" action="/stock/traspasos/rechazar" class="st-auth" data-confirm="¿Rechazar el traspaso N° ' . $id . '?" autocomplete="off">'
            . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><input type="hidden" name="id" value="' . $id . '">'
            . '<label>Motivo<input name="reason" required maxlength="200"></label>'
            . '<label>Usuario<input name="login" required autocomplete="off"></label>'
            . '<label>Clave<input type="password" name="password" required autocomplete="new-password"></label>'
            . '<button type="submit" class="ca-btn danger">Rechazar</button></form></details>'
            . '</div></div>';
    }
    $html .= '<script>document.querySelectorAll("form[data-confirm]").forEach(function (f) { f.addEventListener("submit", function (e) {
        if (!window.confirm(f.getAttribute("data-confirm"))) { e.preventDefault(); return; }
        f.querySelectorAll("button[type=submit]").forEach(function (b) { b.disabled = true; }); }); });</script>';
    return $html;
}

/** @param list<array<string,mixed>> $history */
function stockTransferHistoryPage(array $history): string
{
    $html = '<div class="ca-card">';
    if ($history === []) {
        return $html . '<div class="ca-empty">—</div></div>';
    }
    $html .= '<div class="ca-table-wrap"><table class="ca-table"><thead><tr><th>N°</th><th>Fecha</th><th>Destino</th><th class="num">Códigos</th>'
        . '<th class="num">Unidades</th><th>Estado</th><th>Envió</th><th>Aceptó / rechazó</th></tr></thead><tbody>';
    foreach ($history as $t) {
        $html .= '<tr><td><a href="/stock/traspasos/ver?id=' . (int)$t['id'] . '"><b>' . (int)$t['id'] . '</b></a></td>'
            . '<td>' . h(date('d-m-Y H:i', (int)$t['sent_at'])) . '</td><td>' . h((string)$t['to_name']) . '</td>'
            . '<td class="num">' . (int)$t['codes'] . '</td><td class="num">' . stTrN((float)$t['units']) . '</td>'
            . '<td>' . stockTransferStatus((string)$t['status']) . '</td><td>' . h((string)$t['sent_by_name']) . '</td>'
            . '<td>' . ($t['accepted_by_name'] ? h((string)$t['accepted_by_name']) . ' <span class="ca-muted">' . h(date('d-m-Y H:i', (int)$t['accepted_at'])) . '</span>' : '—') . '</td></tr>';
    }
    return $html . '</tbody></table></div></div>';
}

/**
 * @param array<string,mixed> $t
 * @param list<array<string,mixed>> $items
 */
function stockTransferDetailPage(array $t, array $items): string
{
    $id = (int)$t['id'];
    $units = array_sum(array_map(static fn($i) => (float)$i['qty'], $items));
    $html = '<div class="ca-card st-detail"><div class="st-pend-head"><div><b class="st-num">N° ' . $id . '</b> → ' . h((string)$t['to_name']) . '</div>'
        . '<div>' . stockTransferStatus((string)$t['status']) . '</div>'
        . '<div class="st-sum">' . count($items) . ' código(s) · ' . stTrN($units) . ' unid.</div></div>'
        . '<dl class="st-facts"><div><dt>Envió</dt><dd>' . h((string)$t['sent_by_name']) . ' · ' . h(date('d-m-Y H:i', (int)$t['sent_at'])) . '</dd></div>'
        . '<div><dt>' . ((string)$t['status'] === 'REJECTED' ? 'Rechazó' : 'Aceptó') . '</dt><dd>'
        . ($t['accepted_by_name'] ? h((string)$t['accepted_by_name']) . ' · ' . h(date('d-m-Y H:i', (int)$t['accepted_at'])) : '—') . '</dd></div>'
        . ($t['reject_reason'] ? '<div><dt>Motivo</dt><dd>' . h((string)$t['reject_reason']) . '</dd></div>' : '')
        . ($t['note'] ? '<div><dt>Nota</dt><dd>' . h((string)$t['note']) . '</dd></div>' : '')
        . '</dl>';
    if ((string)$t['status'] === 'ERROR') {
        $html .= '<div class="ca-alert err">' . h((string)$t['error_message']) . '</div>'
            . '<form method="post" action="/stock/traspasos/reintentar"><input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
            . '<input type="hidden" name="id" value="' . $id . '"><button type="submit" class="ca-btn">Reintentar</button></form>';
    }
    return $html . '</div><div class="ca-card">' . stockTransferCodesTable($items, false) . '</div>';
}
