/* Informes · Stock esperado: tabla, indicadores, filtros, orden y ventanas de detalle (NV reservadas / en producción). */
(function () {
  'use strict';
  var state = window.__ES || { rows: [] };
  var STORE = 'unibag_es_filters_v2';
  var RANK = { 'Crítico': 0, 'No existe en ERP': 1, 'Sin conexión ERP': 1, 'Bajo': 2, 'Falta configurar': 3, 'Sin objetivo': 4, 'OK': 5, '': 6 };
  var CLS = { 'OK': 'ok', 'Bajo': 'low', 'Crítico': 'crit', 'Falta configurar': 'cfg', 'Sin objetivo': 'na', 'No existe en ERP': 'crit', 'Sin conexión ERP': 'crit' };
  var f = { q: '', wh: '', grp: '', status: '', kpi: '', onlyRes: false, onlyProd: false, sort: 'status_rank', dir: 1 };
  try { var saved = JSON.parse(localStorage.getItem(STORE) || 'null'); if (saved && typeof saved === 'object') { for (var k in f) { if (k in saved) { f[k] = saved[k]; } } } } catch (e) { /* sin almacenamiento */ }
  function persist() { try { localStorage.setItem(STORE, JSON.stringify(f)); } catch (e) { /* sin almacenamiento */ } }

  function $(id) { return document.getElementById(id); }
  function n(v) { return v === null || v === undefined || v === '' ? '' : Number(v).toLocaleString('es-CL'); }
  function esc(s) { var d = document.createElement('div'); d.textContent = (s === null || s === undefined) ? '' : String(s); return d.innerHTML; }
  function signed(v) { return v === null || v === undefined ? '' : (v > 0 ? '+' : '') + n(v); }
  function dmy(iso) { if (!iso) { return ''; } var p = String(iso).split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : iso; }
  function today() { var d = new Date(); return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
  function pill(s) { return s ? '<span class="es-pill ' + (CLS[s] || '') + '">' + esc(s) + '</span>' : ''; }

  function enrich() {
    state.rows.forEach(function (r) {
      r.status_rank = RANK[r.status] !== undefined ? RANK[r.status] : 6;
      r.projected_rank = RANK[r.projected_status] !== undefined ? RANK[r.projected_status] : 6;
    });
  }

  function kpiMatch(r) {
    switch (f.kpi) {
      case 'crit': return r.status === 'Crítico';
      case 'low': return r.status === 'Bajo';
      case 'stock': return r.stock > 0;
      case 'res': return r.reserved > 0;
      case 'prod': return r.in_production > 0;
      case 'print': return r.to_print > 0;
      case 'seal': return r.to_seal > 0;
      case 'proj': return r.projected_status === 'Crítico' || r.projected_status === 'Bajo';
      default: return true;
    }
  }

  function visible() {
    var q = f.q.trim().toLowerCase();
    var rows = state.rows.filter(function (r) {
      return (!f.wh || String(r.warehouse_code) === f.wh) && (!f.grp || r.group_label === f.grp) && (!f.status || r.status === f.status) &&
        (!f.onlyRes || r.reserved > 0) && (!f.onlyProd || r.in_production > 0) && kpiMatch(r) &&
        (!q || (r.code + ' ' + r.description + ' ' + (r.erp_title || '')).toLowerCase().indexOf(q) >= 0);
    });
    var key = f.sort, dir = f.dir;
    rows.sort(function (a, b) {
      var x = a[key], y = b[key];
      var xn = x === null || x === undefined || x === '', yn = y === null || y === undefined || y === '';
      if (xn && yn) { return a.code < b.code ? -1 : 1; }
      if (xn) { return 1; }
      if (yn) { return -1; }
      if (typeof x === 'string' || typeof y === 'string') { return String(x).localeCompare(String(y), 'es') * dir; }
      return (x - y) * dir || (a.code < b.code ? -1 : 1);
    });
    return rows;
  }

  function renderKpis() {
    var s = { all: state.rows.length, crit: 0, low: 0, stock: 0, pal: 0, res: 0, resNv: 0, prod: 0, prodCc: 0, pr: 0, sl: 0, proj: 0 };
    state.rows.forEach(function (r) {
      if (r.status === 'Crítico') { s.crit++; }
      if (r.status === 'Bajo') { s.low++; }
      s.stock += r.stock || 0; s.pal += r.pallets || 0;
      s.res += r.reserved || 0; s.resNv += r.reserved_orders || 0;
      s.prod += r.in_production || 0; s.prodCc += r.production_orders || 0; s.pr += r.to_print || 0; s.sl += r.to_seal || 0;
      if (r.projected_status === 'Crítico' || r.projected_status === 'Bajo') { s.proj++; }
    });
    var flow = state.flow_ok !== false;
    var cards = [
      ['', 'k-all', n(s.all), 'Productos', 'en el maestro'],
      ['crit', 'k-crit', n(s.crit), 'Críticos', 'bajo el mínimo'],
      ['low', 'k-low', n(s.low), 'Bajo objetivo', 'sobre el mínimo'],
      ['stock', 'k-stock', n(s.stock), 'Stock en bodega', n(s.pal) + ' pallets completos'],
      ['res', 'k-res', flow ? n(s.res) : '—', 'Vendido sin despachar', flow ? n(s.resNv) + ' NV pendientes' : 'sin datos'],
      ['print', 'k-print', flow ? n(s.pr) : '—', 'Esperando impresión', flow ? 'por imprimir en flexo o serigrafía' : 'sin datos'],
      ['seal', 'k-prod', flow ? n(s.sl) : '—', 'Esperando corte y sellado', flow ? 'impresas y sin impresión' : 'sin datos'],
      ['proj', 'k-proj', flow ? n(s.proj) : '—', 'Proyectado bajo', 'objetivo o mínimo']
    ];
    $('es_kpis').innerHTML = cards.map(function (c) {
      return '<button type="button" class="es-kpi ' + c[1] + (f.kpi === c[0] && c[0] !== '' ? ' on' : '') + '" data-kpi="' + c[0] + '"><span>' + c[3] + '</span><b>' + c[2] + '</b><small>' + esc(c[4]) + '</small></button>';
    }).join('');
  }

  function renderChips() {
    var counts = {};
    state.rows.forEach(function (r) { counts[r.status] = (counts[r.status] || 0) + 1; });
    var html = '<button type="button" class="es-chip' + (f.status === '' ? ' on' : '') + '" data-s="">Todos ' + state.rows.length + '</button>';
    Object.keys(counts).sort(function (a, b) { return (RANK[a] || 0) - (RANK[b] || 0); }).forEach(function (s) {
      html += '<button type="button" class="es-chip' + (f.status === s ? ' on' : '') + '" data-s="' + esc(s) + '">' + esc(s) + ' ' + counts[s] + '</button>';
    });
    $('es_chips').innerHTML = html;
  }

  function linkCell(r, type, field) {
    var qty = type === 'reserved' ? r.reserved : r[field || 'in_production'];
    var cnt = type === 'reserved' ? r.reserved_orders : r.production_orders;
    if (qty === null || qty === undefined) { return '<span class="es-zero">—</span>'; }
    if (!qty) { return '<span class="es-zero">0</span>'; }
    var extra = '';
    if (type === 'reserved' && r.oldest_due) {
      extra = r.oldest_due < today() ? ' · <span class="es-neg">vencida</span>' : ' · ' + dmy(r.oldest_due);
    }
    return '<button type="button" class="es-link ' + (type === 'reserved' ? 'res' : 'prod') + '" data-code="' + esc(r.code) + '" data-type="' + type + '" title="Ver detalle">' +
      '<b>' + n(qty) + '</b>' + (type === 'reserved' ? '<span class="es-sub">' + n(cnt) + ' NV' + extra + '</span>' : '') + '</button>';
  }

  function renderTable() {
    var rows = visible(), body = '';
    var tot = {};
    rows.forEach(function (r) {
      var palCell = r.pallets === null || r.pallets === undefined ? '<span class="es-zero">—</span>'
        : n(r.pallets) + ' p · ' + n(r.boxes) + ' c<span class="es-sub">+ ' + n(r.loose) + ' u · ' + n(r.per_pallet) + ' u/pallet</span>';
      var projCls = r.projected === null ? '' : (r.projected < 0 ? 'es-neg' : '');
      body += '<tr>' +
        '<td class="sticky" data-l=""><div class="es-prod-code">' + esc(r.code) + (r.group_label ? ' <span class="es-tag">' + esc(r.group_label) + '</span>' : '') + '</div>' +
        '<div class="es-prod-desc" title="' + esc(r.erp_title || r.description) + '">' + esc(r.description) + '</div></td>' +
        '<td data-l="Bodega">' + esc(r.warehouse_code || '—') + (r.other_stock ? '<span class="es-sub" title="Stock en la otra bodega">otra: ' + n(r.other_stock) + '</span>' : '') + '</td>' +
        '<td class="n" data-l="Stock"><b>' + n(r.stock) + '</b></td>' +
        '<td class="n" data-l="Pallets · cajas">' + palCell + '</td>' +
        '<td class="n" data-l="Vendido sin despachar">' + linkCell(r, 'reserved') + '</td>' +
        '<td class="n' + (r.free !== null && r.free < 0 ? ' es-neg' : '') + '" data-l="Libre">' + (r.free === null ? '—' : n(r.free)) + '</td>' +
        '<td class="n" data-l="Esperando impresión">' + linkCell(r, 'production', 'to_print') + '</td>' +
        '<td class="n" data-l="Esperando corte y sellado">' + linkCell(r, 'production', 'to_seal') + (r.production_orders ? '<span class="es-sub">' + n(r.production_orders) + ' CC</span>' : '') + '</td>' +
        '<td class="n ' + projCls + '" data-l="Proyectado"><b>' + (r.projected === null ? '—' : n(r.projected)) + '</b></td>' +
        '<td class="n ' + (r.projected_diff === null ? '' : (r.projected_diff < 0 ? 'es-neg' : 'es-pos')) + '" data-l="Proyectado vs objetivo">' + signed(r.projected_diff) +
        (r.diff !== null && r.diff !== undefined ? '<span class="es-sub">hoy ' + signed(r.diff) + '</span>' : '') + '</td>' +
        '<td class="n" data-l="Objetivo">' + n(r.target_units) + '</td>' +
        '<td class="n" data-l="Mínimo">' + n(r.min_units) + '</td>' +
        '<td data-l="Estado hoy">' + pill(r.status) + '</td>' +
        '<td data-l="Estado proyectado">' + pill(r.projected_status) + '</td>' +
        '</tr>';
      var w = r.warehouse_code || 0;
      if (!tot[w]) { tot[w] = { n: 0, s: 0, p: 0, r: 0, pr: 0, sl: 0, j: 0 }; }
      tot[w].n++; tot[w].s += r.stock || 0; tot[w].p += r.pallets || 0; tot[w].r += r.reserved || 0; tot[w].pr += r.to_print || 0; tot[w].sl += r.to_seal || 0; tot[w].j += r.projected || 0;
    });
    $('es_body').innerHTML = body || '<tr><td colspan="14" class="es-empty">Ningún producto coincide con los filtros.</td></tr>';
    $('es_foot').innerHTML = Object.keys(tot).sort().reverse().map(function (w) {
      var t = tot[w];
      return '<tr class="es-foot"><td class="sticky" data-l="">' + (w === '0' ? 'Sin bodega' : 'Total bodega ' + w) + ' <span class="es-sub">' + t.n + ' productos</span></td><td></td>' +
        '<td class="n" data-l="Stock">' + n(t.s) + '</td><td class="n" data-l="Pallets">' + n(t.p) + ' p</td><td class="n" data-l="Vendido sin despachar">' + n(t.r) + '</td><td></td>' +
        '<td class="n" data-l="Esperando impresión">' + n(t.pr) + '</td><td class="n" data-l="Esperando corte y sellado">' + n(t.sl) + '</td><td class="n" data-l="Proyectado">' + n(t.j) + '</td><td colspan="5"></td></tr>';
    }).join('');
    Array.prototype.forEach.call(document.querySelectorAll('#es_table .es-cols th'), function (th) {
      var on = th.getAttribute('data-sort') === f.sort;
      th.classList.toggle('sorted', on);
      th.classList.toggle('desc', on && f.dir < 0);
    });
  }

  function render() {
    renderKpis();
    renderChips();
    renderTable();
    $('es_stamp').textContent = state.generated_at ? 'Actualizado ' + state.generated_at.substr(11, 8) + ' · stock cada 30 s, NV y producción cada 2 min' : '';
    var err = '';
    if (state.error) { err += '<div class="es-alert err">' + esc(state.error) + '</div>'; }
    if (state.flow_error) { err += '<div class="es-alert warn">' + esc(state.flow_error) + '</div>'; }
    $('es_err').innerHTML = err;
  }

  function syncInputs() {
    $('es_q').value = f.q; $('es_wh').value = f.wh;
    if ($('es_grp')) { $('es_grp').value = f.grp; if ($('es_grp').value !== f.grp) { f.grp = ''; } } else { f.grp = ''; }
    $('es_only_res').checked = !!f.onlyRes; $('es_only_prod').checked = !!f.onlyProd;
  }

  // ---------------------------------------------------------------- eventos de la tabla
  $('es_kpis').addEventListener('click', function (e) {
    var b = e.target.closest('[data-kpi]'); if (!b) { return; }
    var k = b.getAttribute('data-kpi');
    f.kpi = (k === '' || f.kpi === k) ? '' : k; persist(); render();
  });
  $('es_chips').addEventListener('click', function (e) {
    var b = e.target.closest('[data-s]'); if (!b) { return; }
    f.status = b.getAttribute('data-s'); persist(); render();
  });
  $('es_q').addEventListener('input', function () { f.q = this.value; persist(); renderTable(); });
  $('es_wh').addEventListener('change', function () { f.wh = this.value; persist(); renderTable(); });
  if ($('es_grp')) { $('es_grp').addEventListener('change', function () { f.grp = this.value; persist(); renderTable(); }); }
  $('es_only_res').addEventListener('change', function () { f.onlyRes = this.checked; persist(); renderTable(); });
  $('es_only_prod').addEventListener('change', function () { f.onlyProd = this.checked; persist(); renderTable(); });
  $('es_reset').addEventListener('click', function () {
    f.q = ''; f.wh = ''; f.grp = ''; f.status = ''; f.kpi = ''; f.onlyRes = false; f.onlyProd = false; f.sort = 'status_rank'; f.dir = 1;
    persist(); syncInputs(); render();
  });
  document.querySelector('#es_table thead').addEventListener('click', function (e) {
    var th = e.target.closest('th[data-sort]'); if (!th) { return; }
    var key = th.getAttribute('data-sort');
    if (f.sort === key) { f.dir = -f.dir; } else { f.sort = key; f.dir = /rank|code|warehouse/.test(key) ? 1 : -1; }
    persist(); renderTable();
  });
  $('es_body').addEventListener('click', function (e) {
    var b = e.target.closest('.es-link'); if (!b) { return; }
    openDetail(b.getAttribute('data-code'), b.getAttribute('data-type'), b);
  });

  // ---------------------------------------------------------------- ventana de detalle
  var modal = $('es_modal'), lastFocus = null, current = null, reqSeq = 0;
  function closeModal() {
    modal.hidden = true; document.body.classList.remove('es-noscroll'); current = null;
    if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
  }
  $('es_modal_close').addEventListener('click', closeModal);
  modal.addEventListener('click', function (e) { if (e.target === modal) { closeModal(); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) { closeModal(); } });

  function detailUrl(code, type, xlsx) {
    return '/reports/stock-esperado/detail?code=' + encodeURIComponent(code) + '&type=' + type + (xlsx ? '&format=xlsx' : '');
  }

  function openDetail(code, type, from) {
    lastFocus = from || null;
    current = { code: code, type: type };
    var row = null;
    state.rows.forEach(function (r) { if (r.code === code) { row = r; } });
    $('es_modal_kicker').textContent = type === 'reserved' ? 'Vendido sin despachar · NV de venta pendientes' : 'En producción · CC y OT por fabricar';
    $('es_modal_title').textContent = code + (row ? ' — ' + row.description : '');
    $('es_modal_body').innerHTML = '<div class="es-loading">Cargando detalle…</div>';
    modal.hidden = false; document.body.classList.add('es-noscroll');
    $('es_modal_close').focus();
    var seq = ++reqSeq;
    fetch(detailUrl(code, type, false), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (seq !== reqSeq || !current) { return; }
        if (!d || !d.ok) { throw new Error(d && d.error ? d.error : 'Respuesta inválida del servidor.'); }
        renderDetail(d, row);
      })
      .catch(function (err) {
        if (seq !== reqSeq) { return; }
        $('es_modal_body').innerHTML = '<div class="es-alert err">No se pudo cargar el detalle: ' + esc(err.message || err) + '</div>' +
          '<button type="button" class="es-btn sec" id="es_retry">Reintentar</button>';
        $('es_retry').addEventListener('click', function () { openDetail(code, type, lastFocus); });
      });
  }

  function summaryHtml(s) {
    if (!s) { return ''; }
    function box(label, v, cls) { return '<div><span>' + label + '</span><b class="' + (cls || '') + '">' + (v === null || v === undefined ? '—' : n(v)) + '</b></div>'; }
    return '<div class="es-sum">' + box('Stock bodega ' + (s.warehouse_code || ''), s.stock) + box('Vendido sin despachar', s.reserved) +
      box('Libre (stock − vendido)', s.free, s.free < 0 ? 'es-neg' : '') + box('Esperando impresión', s.to_print) + box('Esperando corte y sellado', s.to_seal) + box('Proyectado', s.projected, s.projected < 0 ? 'es-neg' : '') + '</div>';
  }

  function toolsHtml(d, count) {
    return '<div class="es-modal-tools"><input type="search" id="es_dsearch" placeholder="Buscar NV, CC, OT, cliente, vendedor…" autocomplete="off">' +
      '<span class="es-sub">' + count + (d.type === 'reserved' ? ' NV' : ' CC') + '</span>' +
      '<a class="es-btn sec sm" href="' + detailUrl(d.item.code, d.type, true) + '"><i class="fas fa-file-excel"></i> Excel</a></div>';
  }

  function nvLink(id, num) { return '<a href="/trazabilidad/detalle?id=' + encodeURIComponent(id) + '" target="_blank" rel="noopener" title="Abrir trazabilidad 360°">' + esc(num) + '</a>'; }

  function renderDetail(d, row) {
    var html = summaryHtml(d.summary || row);
    if (!d.rows.length) {
      $('es_modal_body').innerHTML = html + '<div class="es-empty">' + (d.type === 'reserved' ? 'No hay NV de venta pendientes de despacho para este producto.' : 'No hay CC con unidades por fabricar para este producto.') + '</div>' + excludedHtml(d);
      return;
    }
    html += toolsHtml(d, d.rows.length) + '<div class="es-wrap" style="max-height:none"><table class="es-dt" id="es_dt">';
    var t = d.totals;
    if (d.type === 'reserved') {
      html += '<thead><tr><th>NV</th><th>Cliente · vendedor</th><th>OC cliente</th><th>Fecha NV</th><th>Entrega</th><th>Estado</th>' +
        '<th class="n">Pedido</th><th class="n">Despachado</th><th class="n">Pendiente</th><th>Guías</th><th class="n">Stock asignado</th><th>OT</th></tr></thead><tbody>';
      d.rows.forEach(function (r) {
        var search = [r.nv, r.customer, r.seller, r.oc, r.ots].join(' ').toLowerCase();
        html += '<tr data-search="' + esc(search) + '"><td>' + nvLink(r.order_id, r.nv) + '</td>' +
          '<td>' + esc(r.customer) + (r.seller ? '<span class="es-sub">' + esc(r.seller) + '</span>' : '') + '</td>' +
          '<td>' + esc(r.oc) + '</td>' +
          '<td>' + dmy(r.created) + (r.age_days !== null ? '<span class="es-sub">hace ' + r.age_days + ' d</span>' : '') + '</td>' +
          '<td>' + (r.due ? '<span class="' + (r.overdue ? 'es-neg' : '') + '">' + dmy(r.due) + '</span>' + (r.overdue ? '<span class="es-sub es-neg">vencida</span>' : '') : '<span class="es-zero">—</span>') + '</td>' +
          '<td><span class="es-tag ' + (r.status === 'Despacho parcial' ? 'amber' : 'blue') + '">' + esc(r.status) + '</span>' + (r.is_reserva ? '<span class="es-tag">Reserva</span>' : '') + '</td>' +
          '<td class="n">' + n(r.ordered) + '</td><td class="n">' + n(r.shipped) + '</td><td class="n"><b>' + n(r.pending) + '</b></td>' +
          '<td>' + (r.guides ? n(r.guides) + (r.last_guide ? '<span class="es-sub">última ' + dmy(r.last_guide) + '</span>' : '') : '<span class="es-zero">—</span>') + '</td>' +
          '<td class="n">' + (r.stock ? n(r.stock) + '<span class="es-sub">' + esc(r.stock_places) + '</span>' : '<span class="es-zero">0</span>') + '</td>' +
          '<td>' + (r.ots ? esc(r.ots) : '<span class="es-zero">—</span>') + '</td></tr>';
      });
      html += '</tbody><tfoot><tr><td colspan="6">Total ' + n(t.orders) + ' NV</td><td class="n">' + n(t.ordered) + '</td><td class="n">' + n(t.shipped) + '</td><td class="n">' + n(t.pending) +
        '</td><td></td><td class="n">' + n(t.stock) + '</td><td></td></tr></tfoot>';
    } else {
      html += '<thead><tr><th></th><th>CC · OT</th><th>Cliente · vendedor</th><th>Fecha NV</th><th>Entrega</th><th class="n">Pedido</th><th class="n">Impreso</th><th class="n">Sellado</th>' +
        '<th class="n">Esperando impresión</th><th class="n">Esperando corte y sellado</th><th>Avance</th><th>Etapa actual</th><th class="n">Stock de la CC</th></tr></thead><tbody>';
      d.rows.forEach(function (r, i) {
        var search = [r.cc, r.ot, r.customer, r.seller, r.stage].join(' ').toLowerCase();
        var idle = r.idle_days !== null && r.idle_days > 30 ? '<span class="es-tag amber">sin movimiento ' + r.idle_days + ' d</span>' : '';
        var entrega = (r.due ? '<span class="' + (r.overdue ? 'es-neg' : '') + '">' + dmy(r.due) + '</span>' : '<span class="es-zero">—</span>') +
          (r.next_plan ? '<span class="es-sub">próx. plan ' + dmy(r.next_plan) + '</span>' : '');
        html += '<tr data-search="' + esc(search) + '" data-i="' + i + '">' +
          '<td>' + (r.agendas.length || r.plans.length ? '<button type="button" class="es-expand" data-i="' + i + '" aria-expanded="false" title="Ver máquinas">+</button>' : '') + '</td>' +
          '<td>' + nvLink(r.order_id, r.cc) + '<span class="es-sub">' + (r.ot ? 'OT ' + esc(r.ot) : 'sin OT') + '</span></td>' +
          '<td>' + esc(r.customer) + (r.seller ? '<span class="es-sub">' + esc(r.seller) + '</span>' : '') + '</td>' +
          '<td>' + dmy(r.created) + '</td><td>' + entrega + '</td>' +
          '<td class="n">' + n(r.ordered) + '</td><td class="n">' + (r.needs_print ? n(r.printed) : '<span class="es-tag" title="Sin máquina de impresión programada">sin impresión</span>') + '</td><td class="n">' + n(r.sealed) + '</td>' +
          '<td class="n"><b>' + (r.to_print ? n(r.to_print) : '<span class="es-zero">0</span>') + '</b></td><td class="n"><b>' + (r.to_seal ? n(r.to_seal) : '<span class="es-zero">0</span>') + '</b></td>' +
          '<td><b>' + r.progress + '%</b><div class="es-bar-p"><i style="width:' + r.progress + '%"></i></div></td>' +
          '<td>' + esc(r.stage) + (r.last_activity ? '<span class="es-sub">última actividad ' + dmy(r.last_activity) + '</span>' : '') + idle +
          (r.multi_product ? '<span class="es-tag" title="La CC tiene varios productos: lo embalado se reparte según lo pedido de cada uno">varios productos</span>' : '') +
          (r.status === 'Despacho parcial' ? '<span class="es-tag amber">Despacho parcial</span>' : '') + '</td>' +
          '<td class="n">' + (r.stock ? n(r.stock) + '<span class="es-sub">' + esc(r.stock_places) + '</span>' : '<span class="es-zero">0</span>') + '</td></tr>' +
          '<tr class="es-agendas" data-for="' + i + '" hidden><td colspan="13">' + agendaHtml(r) + '</td></tr>';
      });
      html += '</tbody><tfoot><tr><td></td><td colspan="4">Total ' + n(t.orders) + ' CC</td><td class="n">' + n(t.ordered) + '</td><td class="n">' + n(t.printed) + '</td><td class="n">' + n(t.sealed) +
        '</td><td class="n">' + n(t.to_print) + '</td><td class="n">' + n(t.to_seal) + '</td><td colspan="2"></td><td class="n">' + n(t.stock) + '</td></tr></tfoot>';
    }
    html += '</table></div>';
    if (d.type === 'production') {
      html += '<div class="es-legend" style="margin:10px 0 0">Esperando impresión = pedido − lo ya impreso (flexografía o serigrafía). Esperando corte y sellado = lo impreso (o el pedido, si la CC no lleva impresión) − lo ya sellado o embalado. Todo en unidades; el avance es lo sellado sobre lo pedido.</div>';
    }
    html += excludedHtml(d);
    $('es_modal_body').innerHTML = html;
    var input = $('es_dsearch');
    input.addEventListener('input', function () {
      var q = this.value.trim().toLowerCase();
      Array.prototype.forEach.call(document.querySelectorAll('#es_dt tbody tr[data-search]'), function (tr) {
        var show = !q || tr.getAttribute('data-search').indexOf(q) >= 0;
        tr.hidden = !show;
        var i = tr.getAttribute('data-i');
        if (i !== null && !show) { var sub = document.querySelector('#es_dt tr[data-for="' + i + '"]'); if (sub) { sub.hidden = true; } }
      });
    });
    var dt = $('es_dt');
    dt.addEventListener('click', function (e) {
      var b = e.target.closest('.es-expand'); if (!b) { return; }
      var sub = dt.querySelector('tr[data-for="' + b.getAttribute('data-i') + '"]');
      var open = sub.hidden;
      sub.hidden = !open; b.textContent = open ? '−' : '+'; b.setAttribute('aria-expanded', open ? 'true' : 'false');
      b.closest('tr').classList.toggle('es-open-row', open);
    });
  }

  function excludedHtml(d) {
    var x = d.excluded || [], t = d.excluded_totals || { orders: 0, pending: 0 };
    if (!x.length) { return ''; }
    var html = '<details class="es-excl"><summary>';
    if (d.type === 'reserved') {
      html += n(t.orders) + ' NV de venta con más de ' + d.max_age_days + ' días siguen abiertas en el ERP (' + n(t.pending) + ' u.). No se cuentan: ya se entregaron o quedaron sin cerrar en el ERP.</summary>' +
        '<table class="es-dt"><thead><tr><th>NV</th><th>Cliente</th><th>Fecha NV</th><th class="n">Días</th><th class="n">Pedido</th><th class="n">Despachado</th><th class="n">Saldo</th><th class="n">Guías</th></tr></thead><tbody>';
      x.forEach(function (r) {
        html += '<tr><td>' + nvLink(r.order_id, r.nv) + '</td><td>' + esc(r.customer) + '</td><td>' + dmy(r.created) + '</td><td class="n">' + n(r.age_days) + '</td><td class="n">' + n(r.ordered) + '</td><td class="n">' + n(r.shipped) + '</td><td class="n">' + n(r.pending) + '</td><td class="n">' + n(r.guides) + '</td></tr>';
      });
    } else {
      html += n(t.orders) + ' CC con saldo que no se cuentan (' + n(t.pending) + ' u.): merma del cierre, sin movimiento hace más de ' + d.idle_days + ' días o que nunca empezaron.</summary>' +
        '<table class="es-dt"><thead><tr><th>CC</th><th>Cliente</th><th>Fecha</th><th class="n">Pedido</th><th class="n">Embalado</th><th class="n">Por imprimir</th><th class="n">Por cortar y sellar</th><th>Última actividad</th><th>Motivo</th></tr></thead><tbody>';
      x.forEach(function (r) {
        html += '<tr><td>' + nvLink(r.order_id, r.cc) + '</td><td>' + esc(r.customer) + '</td><td>' + dmy(r.created) + '</td><td class="n">' + n(r.ordered) + '</td><td class="n">' + n(r.packed) + '</td><td class="n">' + n(r.to_print) + '</td><td class="n">' + n(r.to_seal) + '</td><td>' + (r.last_activity ? dmy(r.last_activity) : 'sin actividad') + '</td><td>' + esc(r.reason) + '</td></tr>';
      });
    }
    return html + '</tbody></table></details>';
  }

  function agendaHtml(r) {
    var html = '';
    if (r.plans.length) {
      html += '<div style="margin:2px 0 8px"><b>Entregas planificadas:</b> ' + r.plans.map(function (p) { return '<span class="es-tag green">' + dmy(p.date) + ' · ' + n(p.qty) + ' u</span>'; }).join(' ') + '</div>';
    }
    if (!r.agendas.length) { return html + '<div class="es-sub">Sin máquinas programadas.</div>'; }
    html += '<table><thead><tr><th>Proceso</th><th>Máquina</th><th>Fecha programada</th><th class="n">Programado</th><th class="n">Producido</th><th>Estado</th><th>Operador</th><th>Última producción</th></tr></thead><tbody>';
    r.agendas.forEach(function (a) {
      var tag = a.state === 'En curso' ? 'blue' : (a.state === 'Con producción' ? 'green' : '');
      html += '<tr><td>' + esc(a.process) + '</td><td>' + esc(a.machine) + '</td><td>' + esc(a.date) + '</td>' +
        '<td class="n">' + n(a.planned) + '</td><td class="n">' + n(a.produced) + ' ' + a.unit + '</td>' +
        '<td><span class="es-tag ' + tag + '">' + esc(a.state) + '</span></td><td>' + esc(a.operator) + '</td><td>' + dmy(a.last) + '</td></tr>';
    });
    return html + '</tbody></table>';
  }

  // ---------------------------------------------------------------- refresco
  function refresh() {
    if (document.hidden) { return; }
    fetch('/reports/stock-esperado/data', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d && d.rows) { state = d; enrich(); render(); } })
      .catch(function () { $('es_err').innerHTML = '<div class="es-alert err">Sin conexión con el servidor; se reintenta en 30 s.</div>'; });
  }
  setInterval(refresh, 30000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { refresh(); } });

  enrich();
  syncInputs();
  render();
})();
