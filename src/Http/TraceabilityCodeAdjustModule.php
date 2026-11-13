<?php

declare(strict_types=1);

/**
 * Módulo HTTP · Bodega → Ajuste de códigos de trazabilidad.
 *
 *  - GET  /stock/ajuste-codigos?a=X&b=Y ...... Juntar: se escanean dos cajas y el operador elige cuál queda.
 *  - POST /stock/ajuste-codigos/juntar ........ Pasa todo el saldo de la caja que no queda a la que queda.
 *  - GET  /stock/ajuste-codigos?tab=borrar .... Borrar: se cargan varias etiquetas en una lista y se borran juntas.
 *  - GET  /stock/ajuste-codigos/info?code=X ... JSON con los datos de un código (para la lista de borrado).
 *  - POST /stock/ajuste-codigos/borrar ........ Borra todos los códigos de la lista (todo o nada; solo códigos sin uso).
 *
 * Solo cambia saldos de etiquetas en trazabilidad; el stock del ERP no se toca (ver TraceabilityCodeAdjustService).
 * Pantallas sin subtítulos ni textos de ayuda (pedido del usuario).
 */

require_once __DIR__ . '/../TraceabilityCodeAdjustService.php';

const CODE_ADJUST_ASSET_VERSION = '20261113c';

function handleTraceabilityCodeAdjustRoutes(string $path, string $method, string $operator): bool
{
    if (!str_starts_with($path, '/stock/ajuste-codigos')) {
        return false;
    }
    $styles = '<link rel="stylesheet" href="/assets/css/unibag-code-adjust.css?v=' . CODE_ADJUST_ASSET_VERSION . '">';
    $isJson = $path === '/stock/ajuste-codigos/info';
    $perms = sessionAreaPermissions();
    if (!userCanAccessArea('ERP', $perms) && !userCanAccessArea('RECEPTION', $perms)) {
        if ($isJson) {
            codeAdjustJson(['ok' => false, 'error' => 'Sin permiso.']);
        } else {
            render('Ajuste de códigos', $styles . '<div class="ca-shell"><div class="ca-alert err">Requiere permisos del área ERP o Recepción (Bodega).</div></div>');
        }
        return true;
    }
    try {
        $service = new TraceabilityCodeAdjustService(Db::trzPdo(), Db::erpPdo());
        $service->ensureSchema();
    } catch (Throwable $e) {
        if ($isJson) {
            codeAdjustJson(['ok' => false, 'error' => $e->getMessage()]);
        } else {
            render('Ajuste de códigos', $styles . '<div class="ca-shell"><div class="ca-alert err">' . h($e->getMessage()) . '</div></div>');
        }
        return true;
    }

    if ($isJson && $method === 'GET') {
        $code = $service->find((string)($_GET['code'] ?? ''));
        if ($code === null) {
            codeAdjustJson(['ok' => false, 'error' => 'No existe el código ' . TraceabilityCodeAdjustService::normalizeCode((string)($_GET['code'] ?? '')) . '.']);
        } elseif (($problem = $service->deleteProblem($code)) !== '') {
            codeAdjustJson(['ok' => false, 'error' => $code['code'] . ': ' . $problem]);
        } else {
            codeAdjustJson(['ok' => true, 'code' => [
                'code' => $code['code'], 'item_code' => $code['item_code'], 'description' => $code['description'],
                'order_ref' => $code['order_ref'], 'warehouse_name' => $code['warehouse_name'], 'qty' => $code['qty'],
                'qty_txt' => TraceabilityCodeAdjustService::fmt((float)$code['qty']),
            ]]);
        }
        return true;
    }
    if ($method === 'POST' && $path === '/stock/ajuste-codigos/juntar') {
        requireCsrf();
        $a = TraceabilityCodeAdjustService::normalizeCode((string)($_POST['a'] ?? ''));
        $b = TraceabilityCodeAdjustService::normalizeCode((string)($_POST['b'] ?? ''));
        $keep = TraceabilityCodeAdjustService::normalizeCode((string)($_POST['keep'] ?? ''));
        try {
            if ($keep === '' || ($keep !== $a && $keep !== $b)) {
                throw new InvalidArgumentException('Elige la caja que queda.');
            }
            $from = $keep === $a ? $b : $a;
            $res = $service->merge($from, $keep, null, $operator);
            $msg = $res['from']['code'] . ' → ' . $res['to']['code'] . ': ' . TraceabilityCodeAdjustService::fmt($res['qty']) . ' unid. '
                . $res['to']['code'] . ' queda con ' . TraceabilityCodeAdjustService::fmt((float)$res['to']['qty']) . '.';
            redirectResponse('/stock/ajuste-codigos?ok=' . rawurlencode($msg) . '&done=' . rawurlencode($res['to']['code']) . '&wh=' . (int)$res['to']['warehouse_id']);
        } catch (Throwable $e) {
            redirectResponse('/stock/ajuste-codigos?a=' . rawurlencode($a) . '&b=' . rawurlencode($b) . '&err=' . rawurlencode($e->getMessage()));
        }
        return true;
    }
    if ($method === 'POST' && $path === '/stock/ajuste-codigos/borrar') {
        requireCsrf();
        $codes = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['codes'] ?? '')))));
        try {
            $done = $service->deleteMany($codes, (string)($_POST['reason'] ?? ''), $operator, (string)($_POST['note'] ?? ''));
            redirectResponse('/stock/ajuste-codigos?tab=borrar&ok=' . rawurlencode(count($done) . ' código(s) borrado(s).'));
        } catch (Throwable $e) {
            redirectResponse('/stock/ajuste-codigos?tab=borrar&err=' . rawurlencode($e->getMessage()) . '&codes=' . rawurlencode(implode(',', $codes)));
        }
        return true;
    }
    if ($method !== 'GET' || $path !== '/stock/ajuste-codigos') {
        return false;
    }

    $tab = (string)($_GET['tab'] ?? '') === 'borrar' ? 'borrar' : 'juntar';
    $error = (string)($_GET['err'] ?? '');
    $ok = (string)($_GET['ok'] ?? '');
    try {
        if ($tab === 'juntar') {
            $aQ = TraceabilityCodeAdjustService::normalizeCode((string)($_GET['a'] ?? ''));
            $bQ = TraceabilityCodeAdjustService::normalizeCode((string)($_GET['b'] ?? ''));
            $a = $aQ !== '' ? $service->find($aQ) : null;
            $b = $bQ !== '' ? $service->find($bQ) : null;
            if ($error === '') {
                if ($aQ !== '' && $a === null) {
                    $error = 'No existe el código ' . $aQ . '.';
                } elseif ($bQ !== '' && $b === null) {
                    $error = 'No existe el código ' . $bQ . '.';
                } elseif ($a !== null && $b !== null) {
                    $error = TraceabilityCodeAdjustService::mergeProblem($a, $b);
                }
            }
            $body = codeAdjustMergePage($a, $b, $aQ, $bQ, (string)($_GET['done'] ?? ''), $service->history(30, '', 'MERGE'), (int)($_GET['wh'] ?? 0));
        } else {
            // Códigos cargados: vienen de las casillas (codes[]) o de la lista ya cargada (codes=A,B,C).
            $raw = $_GET['codes'] ?? [];
            $raw = is_array($raw) ? $raw : explode(',', (string)$raw);
            $loaded = [];
            foreach ($raw as $part) {
                foreach (preg_split('/[\s,;]+/', (string)$part) ?: [] as $c) {
                    $c = TraceabilityCodeAdjustService::normalizeCode($c);
                    if ($c !== '' && count($loaded) < 300) {
                        $loaded[$c] = true;
                    }
                }
            }
            $rows = [];
            foreach (array_keys($loaded) as $c) {
                $info = $service->find($c);
                $rows[] = ['code' => $info['code'] ?? $c, 'info' => $info, 'problem' => $info === null ? 'No existe.' : $service->deleteProblem($info)];
            }
            $body = codeAdjustDeletePage($rows, $service->history(30, '', 'DELETE'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $body = '';
    }
    render('Ajuste de códigos', $styles . codeAdjustLayout($tab, $ok, $error, $body));
    return true;
}

function codeAdjustJson(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}

function caN(float $v): string
{
    return TraceabilityCodeAdjustService::fmt($v);
}

function codeAdjustLayout(string $tab, string $ok, string $error, string $body): string
{
    $html = '<div class="ca-shell"><div class="ca-head"><div class="ca-title">Ajuste de códigos</div>'
        . '<nav class="ca-tabs"><a class="' . ($tab === 'juntar' ? 'on' : '') . '" href="/stock/ajuste-codigos">⇄ Juntar</a>'
        . '<a class="' . ($tab === 'borrar' ? 'on danger' : '') . '" href="/stock/ajuste-codigos?tab=borrar">🗑 Borrar</a></nav></div>';
    if ($ok !== '') {
        $html .= '<div class="ca-alert ok">✔ ' . h($ok) . '</div>';
    }
    if ($error !== '') {
        $html .= '<div class="ca-alert err">✖ ' . h($error) . '</div>';
    }
    return $html . $body . '</div>';
}

/** @param array<string,mixed> $c */
function codeAdjustBoxCard(array $c, string $side, bool $selectable): string
{
    $inner = '<div class="ca-box-code mono">' . h((string)$c['code']) . '</div>'
        . '<div class="ca-box-item"><b>' . h((string)$c['item_code']) . '</b> ' . h((string)$c['description']) . '</div>'
        . '<div class="ca-box-qty">' . caN((float)$c['qty']) . '<small> unid.</small></div>'
        . '<dl class="ca-box-facts"><div><dt>CC</dt><dd>' . h($c['order_ref'] !== '' ? (string)$c['order_ref'] : 'Sin CC') . '</dd></div>'
        . '<div><dt>Bodega</dt><dd>' . h((string)$c['warehouse_name']) . '</dd></div></dl>';
    if (!$selectable) {
        return '<div class="ca-box">' . $inner . '</div>';
    }
    return '<label class="ca-box pick"><input type="radio" name="keep" value="' . h((string)$c['code']) . '" required>'
        . '<span class="ca-keep">Queda esta caja</span>' . $inner . '</label>';
}

/**
 * @param array<string,mixed>|null $a
 * @param array<string,mixed>|null $b
 * @param list<array<string,mixed>> $history
 */
function codeAdjustMergePage(?array $a, ?array $b, string $aQ, string $bQ, string $done, array $history, int $doneWarehouse = 0): string
{
    $html = '<form class="ca-card ca-scan2" method="get" action="/stock/ajuste-codigos" autocomplete="off">'
        . '<label>Caja 1<input id="ca_a" name="a" value="' . h($aQ) . '"' . ($aQ === '' ? ' autofocus' : '') . '></label>'
        . '<label>Caja 2<input id="ca_b" name="b" value="' . h($bQ) . '"' . ($aQ !== '' && $bQ === '' ? ' autofocus' : '') . '></label>'
        . '<div class="ca-scan2-btns"><button type="submit" class="ca-btn">Buscar</button>'
        . ($aQ !== '' || $bQ !== '' ? '<a class="ca-btn sec" href="/stock/ajuste-codigos">Limpiar</a>' : '') . '</div></form>';

    if ($a !== null && $b !== null && TraceabilityCodeAdjustService::mergeProblem($a, $b) === '') {
        $html .= '<form class="ca-card" method="post" action="/stock/ajuste-codigos/juntar" data-ca-confirm="¿Juntar las dos cajas?">'
            . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
            . '<input type="hidden" name="a" value="' . h((string)$a['code']) . '"><input type="hidden" name="b" value="' . h((string)$b['code']) . '">'
            . '<div class="ca-pair">' . codeAdjustBoxCard($a, 'a', true) . '<div class="ca-arrow">⇄</div>' . codeAdjustBoxCard($b, 'b', true) . '</div>'
            . '<div class="ca-total">Total: <b>' . caN((float)$a['qty'] + (float)$b['qty']) . '</b> unid.</div>'
            . '<button type="submit" class="ca-btn wide">Juntar</button></form>';
    } elseif ($a !== null || $b !== null) {
        $html .= '<div class="ca-pair solo">' . ($a !== null ? codeAdjustBoxCard($a, 'a', false) : '') . ($b !== null ? codeAdjustBoxCard($b, 'b', false) : '') . '</div>';
    }
    if ($done !== '') {
        $html .= '<div class="ca-reprint"><b class="mono">' . h($done) . '</b>'
            . '<a class="ca-btn sec" href="/stock/traceability-entry?' . ($doneWarehouse > 0 ? 'bodega=' . $doneWarehouse . '&amp;' : '') . 'q=' . rawurlencode($done) . '">🖨 Reimprimir etiqueta</a></div>';
    }
    $html .= codeAdjustHistory($history, true);
    $html .= '<script>
(function () {
  var a = document.getElementById("ca_a"), b = document.getElementById("ca_b");
  function caNorm(v) { return String(v || "").replace(/[\'`´’‘‛]/g, "-").replace(/\\s+/g, "").toUpperCase(); }
  function caFix() {
    var parts = String(a.value).replace(/[\'`´’‘‛]/g, "-").split(/[\\s,;]+/).filter(Boolean);
    if (parts.length > 1) { a.value = caNorm(parts[0]); if (!b.value.trim()) b.value = caNorm(parts[1]); b.focus(); }
  }
  if (a && b) {
    // Igual que Solicitudes MP: Enter en la caja 1 pasa a la caja 2 (sin perder lo que llega rápido) y si la pistola
    // junta dos códigos en la caja 1, el segundo pasa a la caja 2.
    document.addEventListener("keydown", function (e) {
      if (e.target === a && (e.key === "Enter" || e.keyCode === 13)) {
        caFix();
        if (!b.value.trim()) { e.preventDefault(); e.stopPropagation(); a.value = caNorm(a.value); b.focus(); }
      }
    }, true);
    a.addEventListener("input", function () { if (/[\\s,;]/.test(a.value)) caFix(); });
  }
  document.querySelectorAll("form[data-ca-confirm]").forEach(function (f) {
    f.addEventListener("submit", function (ev) {
      if (!window.confirm(f.getAttribute("data-ca-confirm"))) { ev.preventDefault(); return; }
      f.querySelectorAll("button[type=submit]").forEach(function (x) { x.disabled = true; });
    });
  });
})();
</script>';
    return $html;
}

/**
 * Borrar: casillas para escanear (igual que Solicitudes MP: cada lectura queda en su casilla, sin consultar al servidor,
 * así no se pierden códigos al escanear rápido) → «Cargar» revisa en el servidor → lista → «Borrar».
 *
 * @param list<array{code:string,info:?array,problem:string}> $rows códigos cargados con su revisión
 * @param list<array<string,mixed>> $history
 */
function codeAdjustDeletePage(array $rows, array $history): string
{
    $loadedCodes = array_column($rows, 'code');
    $valid = array_values(array_filter($rows, static fn($r) => $r['problem'] === ''));
    $html = '<form class="ca-card ca-slots" method="get" action="/stock/ajuste-codigos" autocomplete="off" data-existing="' . h((string)json_encode($loadedCodes)) . '">'
        . '<input type="hidden" name="tab" value="borrar">';
    foreach ($loadedCodes as $c) {
        $html .= '<input type="hidden" name="codes[]" value="' . h($c) . '">';
    }
    $html .= '<div class="ca-slot-bar"><span class="ca-slot-summary"></span></div><div class="ca-slot-list"></div>'
        . '<button type="submit" class="ca-btn ca-load-btn" disabled>Cargar (0)</button></form>';
    if ($rows !== []) {
        $html .= '<div class="ca-card"><div class="ca-table-wrap"><table class="ca-table"><thead><tr><th>#</th><th>Código</th><th>Artículo</th><th>CC</th><th>Bodega</th>'
            . '<th class="num">Saldo</th><th></th><th></th></tr></thead><tbody>';
        foreach ($rows as $i => $r) {
            $c = $r['info'];
            $others = array_values(array_filter($loadedCodes, static fn($x) => $x !== $r['code']));
            $removeUrl = '/stock/ajuste-codigos?tab=borrar' . ($others !== [] ? '&codes=' . rawurlencode(implode(',', $others)) : '');
            $html .= '<tr' . ($r['problem'] !== '' ? ' class="bad"' : '') . '><td>' . ($i + 1) . '</td><td class="mono"><b>' . h($r['code']) . '</b></td>'
                . '<td>' . ($c !== null ? h((string)$c['item_code']) . ' <span class="ca-muted">' . h((string)$c['description']) . '</span>' : '') . '</td>'
                . '<td>' . ($c !== null ? h($c['order_ref'] !== '' ? (string)$c['order_ref'] : 'Sin CC') : '') . '</td>'
                . '<td>' . ($c !== null ? h((string)$c['warehouse_name']) : '') . '</td>'
                . '<td class="num">' . ($c !== null ? caN((float)$c['qty']) : '') . '</td>'
                . '<td>' . ($r['problem'] !== '' ? '<span class="ca-bad">✖ ' . h($r['problem']) . '</span>' : '<span class="ca-ok">✓</span>') . '</td>'
                . '<td><a class="ca-x" href="' . h($removeUrl) . '">✕</a></td></tr>';
        }
        $html .= '</tbody></table></div></div>';
        $options = '<option value=""></option>';
        foreach (TraceabilityCodeAdjustService::DELETE_REASONS as $k => $label) {
            $options .= '<option value="' . h($k) . '">' . h($label) . '</option>';
        }
        $html .= '<form class="ca-card ca-void" method="post" action="/stock/ajuste-codigos/borrar" id="ca_void">'
            . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
            . '<input type="hidden" name="codes" value="' . h(implode(',', array_column($valid, 'code'))) . '">'
            . '<label>Motivo<select name="reason" required>' . $options . '</select></label>'
            . '<label>Detalle<input name="note" maxlength="200"></label>'
            . '<button type="submit" class="ca-btn danger"' . ($valid === [] ? ' disabled' : '') . ' data-n="' . count($valid) . '">Borrar (' . count($valid) . ')</button></form>';
    }
    $html .= codeAdjustHistory($history, false);
    $html .= <<<'JS'
<script>
(function () {
  var form = document.querySelector(".ca-slots"); if (!form) return;
  var list = form.querySelector(".ca-slot-list"),
      summary = form.querySelector(".ca-slot-summary"), loadBtn = form.querySelector(".ca-load-btn");
  var existing = {};
  try { JSON.parse(form.getAttribute("data-existing") || "[]").forEach(function (c) { existing[String(c).toUpperCase()] = true; }); } catch (e) {}
  function norm(v) { return String(v || "").replace(/['`´’‘‛]/g, "-").replace(/\s+/g, "").toUpperCase(); }
  function split(raw) { return String(raw || "").replace(/['`´’‘‛]/g, "-").split(/[\s,;]+/).map(norm).filter(Boolean); }
  function inputs() { return [].slice.call(list.querySelectorAll(".ca-slot-input")); }
  function row(n, val) {
    var r = document.createElement("div"); r.className = "ca-slot-row";
    r.innerHTML = '<span class="ca-slot-idx">#' + n + '</span><input type="text" name="codes[]" class="ca-slot-input" maxlength="80" autocomplete="off">'
      + '<span class="ca-slot-tag"></span><button type="button" class="ca-slot-rm">✕</button>';
    if (val) r.querySelector("input").value = val;
    return r;
  }
  function refresh() {
    var seen = {}, ok = 0, dup = 0;
    Object.keys(existing).forEach(function (k) { seen[k] = true; });
    inputs().forEach(function (inp, i) {
      var r = inp.closest(".ca-slot-row"), tag = r.querySelector(".ca-slot-tag"), v = norm(inp.value);
      r.querySelector(".ca-slot-idx").textContent = "#" + (i + 1);
      r.classList.remove("filled", "dup"); tag.textContent = "";
      if (!v) return;
      if (seen[v]) { r.classList.add("dup"); tag.textContent = "Repetido"; dup++; }
      else { seen[v] = true; r.classList.add("filled"); tag.textContent = "✓"; ok++; }
    });
    summary.textContent = ok + " código(s)" + (dup ? " · " + dup + " repetido(s)" : "");
    loadBtn.disabled = ok === 0 || dup > 0;
    loadBtn.textContent = "Cargar (" + ok + ")";
  }
  // Siempre hay una casilla vacía al final: se parte con 1 y cada código leído agrega la siguiente.
  function ensureEmpty() {
    var all = inputs();
    if (!all.length || norm(all[all.length - 1].value)) list.appendChild(row(all.length + 1, ""));
    refresh();
  }
  function nextEmpty(from) {
    var all = inputs();
    for (var i = from + 1; i < all.length; i++) if (!norm(all[i].value)) return all[i];
    for (var k = 0; k < all.length; k++) if (!norm(all[k].value)) return all[k];
    var r = row(all.length + 1, ""); list.appendChild(r); return r.querySelector("input");
  }
  // Reparte lo escrito en la casilla (la pistola puede juntar dos códigos) y pasa a la siguiente casilla vacía.
  function distribute(inp) {
    var parts = split(inp.value), all = inputs(), idx = all.indexOf(inp);
    var taken = {};
    Object.keys(existing).forEach(function (k) { taken[k] = true; });
    all.forEach(function (o, j) { if (j !== idx && norm(o.value)) taken[norm(o.value)] = true; });
    var unique = parts.filter(function (p) { if (taken[p]) return false; taken[p] = true; return true; });
    if (parts.length && !unique.length) { inp.value = ""; refresh(); inp.focus(); return; }
    inp.value = unique.length ? unique[0] : "";
    var cur = idx;
    for (var k = 1; k < unique.length; k++) { var t = nextEmpty(cur); t.value = unique[k]; cur = inputs().indexOf(t); }
    if (unique.length) nextEmpty(cur).focus(); else inp.focus();
    refresh();
  }
  document.addEventListener("keydown", function (e) {
    var t = e.target; if (!t || !t.classList) return;
    if (t.classList.contains("ca-slot-input") && (e.key === "Enter" || e.keyCode === 13)) { e.preventDefault(); e.stopPropagation(); distribute(t); }
  }, true);
  document.addEventListener("input", function (e) {
    var t = e.target; if (!t || !t.classList || !t.classList.contains("ca-slot-input")) return;
    if (/[\s,;]/.test(t.value)) distribute(t); else ensureEmpty();
  });
  list.addEventListener("click", function (e) {
    var b = e.target.closest(".ca-slot-rm"); if (!b) return;
    var r = b.closest(".ca-slot-row"), inp = r.querySelector("input");
    if (inputs().length > 1) r.remove(); else inp.value = "";
    ensureEmpty();
    var all = inputs(); all[all.length - 1].focus();
  });
  form.addEventListener("submit", function () {
    inputs().forEach(function (i) { i.value = norm(i.value); if (!i.value) i.disabled = true; });
    loadBtn.disabled = true; loadBtn.textContent = "Cargando…";
  });
  ensureEmpty();
  inputs()[0].focus();
  var del = document.getElementById("ca_void");
  if (del) del.addEventListener("submit", function (e) {
    var b = del.querySelector("button[type=submit]");
    if (!window.confirm("¿Borrar " + b.getAttribute("data-n") + " código(s)?")) { e.preventDefault(); return; }
    b.disabled = true;
  });
})();
</script>
JS;
    return $html;
}

/** @param list<array<string,mixed>> $history */
function codeAdjustHistory(array $history, bool $merge): string
{
    $html = '<div class="ca-card"><div class="ca-card-title">Historial</div>';
    if ($history === []) {
        return $html . '<div class="ca-empty">—</div></div>';
    }
    $html .= '<div class="ca-table-wrap"><table class="ca-table"><thead><tr><th>Fecha</th>'
        . ($merge ? '<th>Pasa</th><th>Queda</th>' : '<th>Código</th>')
        . '<th>Artículo</th><th class="num">Unidades</th>' . ($merge ? '' : '<th>Motivo</th>') . '<th>Usuario</th></tr></thead><tbody>';
    foreach ($history as $r) {
        $reason = TraceabilityCodeAdjustService::DELETE_REASONS[(string)($r['reason'] ?? '')] ?? '';
        $html .= '<tr><td>' . h(date('d-m-Y H:i', (int)$r['created_at'])) . '</td>'
            . '<td class="mono">' . h((string)$r['from_code']) . '</td>'
            . ($merge ? '<td class="mono">' . h((string)$r['to_code']) . ' <span class="ca-muted">' . caN((float)$r['to_before']) . ' → ' . caN((float)$r['to_after']) . '</span></td>' : '')
            . '<td>' . h((string)$r['item_code']) . ($r['order_ref'] ? ' <span class="ca-muted">CC ' . h((string)$r['order_ref']) . '</span>' : '') . '</td>'
            . '<td class="num">' . caN((float)$r['qty']) . '</td>'
            . ($merge ? '' : '<td>' . h(trim($reason . ($r['note'] ? ' — ' . $r['note'] : ''))) . '</td>')
            . '<td>' . h((string)$r['operator_name']) . '</td></tr>';
    }
    return $html . '</tbody></table></div></div>';
}
