<?php

declare(strict_types=1);

/**
 * Módulo HTTP · Bodega → Informe (stock consolidado). Solo lectura del ERP.
 *
 *  - /stock/informe ........ Pantalla: stock por bodega, ocupación de bobinas, cliente B1000, rotación, algodón y detalle.
 *  - /stock/informe/excel .. Descarga Excel con las mismas hojas.
 */

require_once __DIR__ . '/../WarehouseStockReportService.php';

const WH_REPORT_ASSET_VERSION = '20261111a';

function handleWarehouseStockReportRoutes(string $path, string $method, string $name): bool
{
    if (!in_array($path, ['/stock/informe', '/stock/informe/excel'], true) || $method !== 'GET') {
        return false;
    }
    $styles = '<link rel="stylesheet" href="/assets/css/unibag-wh-report.css?v=' . WH_REPORT_ASSET_VERSION . '">';
    try {
        $perms = sessionAreaPermissions();
        if (!userCanAccessArea('ERP', $perms) && !userCanAccessArea('RECEPTION', $perms)) {
            throw new RuntimeException('El informe de bodega requiere permisos del área ERP o Recepción.');
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $report = (new WarehouseStockReportService(Db::erpPdo()))->cachedReport(isset($_GET['refresh']));
        if ($path === '/stock/informe/excel') {
            warehouseStockReportExcel($report);
            return true;
        }
        render('Informe de bodega', $styles . warehouseStockReportPage($report));
    } catch (Throwable $e) {
        render('Informe de bodega', $styles . '<div class="wr-shell"><div class="wr-alert">No se pudo generar el informe: ' . h($e->getMessage()) . '</div></div>');
    }
    return true;
}

function whrN(float|int|null $v, int $dec = 0): string
{
    return $v === null ? '—' : number_format((float)$v, $dec, ',', '.');
}

/** @param array<string,mixed> $report */
function warehouseStockReportPage(array $report): string
{
    $wh = $report['warehouses'];
    $goods = array_values(array_filter($wh, static fn($w) => $w['kind'] === 'goods'));
    $rolls = array_values(array_filter($wh, static fn($w) => $w['kind'] === 'rolls'));
    $rot = $report['rotation'];

    $html = '<div class="wr-shell">';
    $html .= '<div class="wr-head"><div><div class="wr-title">Informe de stock consolidado</div>'
        . '<div class="wr-stamp">Stock actual del ERP · ' . h($report['generated_at']) . '</div></div>'
        . '<div class="wr-actions"><a class="wr-btn sec" href="/stock/informe?refresh=1">↻ Actualizar</a>'
        . '<a class="wr-btn" href="/stock/informe/excel">⬇ Descargar Excel</a></div></div>';
    if ($report['missing'] !== []) {
        $html .= '<div class="wr-alert warn">No se encontraron en el ERP las bodegas: ' . h(implode(', ', $report['missing'])) . '.</div>';
    }

    // Indicadores por bodega.
    $html .= '<div class="wr-kpis">';
    $html .= '<div class="wr-kpi total"><span>Total producto terminado</span><b>' . whrN($report['goods_total']) . '</b><small>unidades en bodegas 400, 700, 1000 y 5000</small></div>';
    foreach ($goods as $w) {
        $html .= '<div class="wr-kpi"><span>' . h($w['name']) . '</span><b>' . whrN($w['units']) . '</b><small>' . whrN($w['share'], 1) . ' % del total · ' . (int)$w['item_count'] . ' artículos</small></div>';
    }
    $html .= '</div>';

    // Ocupación de bobinas.
    $html .= '<div class="wr-grid">';
    $html .= '<div class="wr-card"><div class="wr-card-t">Nivel de ocupación · bodegas de bobinas</div>';
    foreach ($rolls as $w) {
        $occ = $w['occupancy'];
        $cls = $occ === null ? '' : ($occ >= 90 ? ' hi' : ($occ >= 70 ? ' mid' : ''));
        $html .= '<div class="wr-occ"><div class="wr-occ-h"><b>' . h($w['name']) . '</b><span>' . whrN($w['units']) . ' bobinas'
            . ($w['capacity'] !== null ? ' de ' . whrN($w['capacity']) : '') . ' · ' . whrN($w['kg']) . ' kg</span></div>'
            . '<div class="wr-occ-bar"><i class="' . trim($cls) . '" style="width:' . min(100, (float)($occ ?? 0)) . '%"></i></div>'
            . '<div class="wr-occ-p">' . ($occ === null ? 'Sin capacidad definida' : whrN($occ, 1) . ' % ocupado') . '</div></div>';
    }
    $html .= '<p class="wr-note">Capacidad = ubicaciones × columnas × altura (100: 1.824, 200: 384), salvo que la bodega tenga capacidad cargada en el ERP.</p></div>';

    $html .= '<div class="wr-card"><div class="wr-card-t">Total bodegas · participación</div><div class="wr-chart sm"><canvas id="wr-share"></canvas></div></div>';
    $html .= '<div class="wr-card"><div class="wr-card-t">Rotación de stock</div><div class="wr-chart sm"><canvas id="wr-rot"></canvas></div>'
        . '<p class="wr-note">Con movimiento: ' . whrN($rot['moved']) . ' · Sin rotación (bodega 5000, productos inmovilizados): ' . whrN($rot['still']) . ' (' . whrN($rot['still_pct'], 1) . ' %)</p></div>';
    $html .= '</div>';

    $html .= '<div class="wr-grid two">';
    $html .= '<div class="wr-card"><div class="wr-card-t">Stock consolidado por cliente · bodega 1000 (retail)</div><div class="wr-chart tall"><canvas id="wr-retail"></canvas></div></div>';
    $html .= '<div class="wr-card"><div class="wr-card-t">Detalle artículos sin rotación (bodega 5000) por cliente</div><div class="wr-chart tall"><canvas id="wr-still"></canvas></div></div>';
    $html .= '</div>';

    // Algodón.
    $html .= '<div class="wr-card"><div class="wr-card-t">Stock consolidado algodón · ' . whrN($report['cotton_total']) . ' unidades</div>';
    if ($report['cotton'] === []) {
        $html .= '<p class="wr-note">No hay bolsas de algodón con stock.</p>';
    } else {
        $html .= '<div class="wr-table-wrap"><table class="wr-table"><thead><tr><th>SKU</th><th>Descripción</th><th>Unidad</th><th>Bodega</th><th class="n">Cantidad</th></tr></thead><tbody>';
        foreach ($report['cotton'] as $c) {
            $html .= '<tr><td>' . h($c['sku']) . '</td><td>' . h($c['title']) . '</td><td>' . h($c['unit']) . '</td><td>' . (int)$c['warehouse'] . '</td><td class="n">' . whrN($c['qty']) . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
    }
    $html .= '</div>';

    // Detalle consolidado.
    $html .= '<div class="wr-card"><div class="wr-card-t">Stock consolidado · detalle por artículo, bodega y CC</div>'
        . '<div class="wr-bar"><select id="wr-f-wh" aria-label="Bodega"><option value="">Todas las bodegas</option>';
    foreach ($wh as $w) {
        $html .= '<option value="' . (int)$w['code'] . '">' . h($w['name']) . '</option>';
    }
    $html .= '</select><input type="search" id="wr-f-q" placeholder="Buscar SKU, descripción, CC o cliente" aria-label="Buscar">'
        . '<span id="wr-f-count" class="wr-count"></span></div>'
        . '<div class="wr-table-wrap tall"><table class="wr-table"><thead><tr><th>SKU</th><th>Descripción</th><th>Unidad</th><th>Bodega</th><th class="n">Stock físico</th><th>CC</th><th>Cliente</th></tr></thead>'
        . '<tbody id="wr-rows"></tbody><tfoot><tr><td colspan="4">Total</td><td class="n" id="wr-sum"></td><td colspan="2"></td></tr></tfoot></table></div></div>';
    $html .= '</div>';

    $top = static function (array $map, int $n): array {
        $out = array_slice($map, 0, $n, true);
        $rest = array_sum(array_slice($map, $n, null, true));
        if ($rest > 0) {
            $out['Varios'] = ($out['Varios'] ?? 0) + $rest;
        }
        return $out;
    };
    $data = [
        'share' => array_map(static fn($w) => ['label' => (string)$w['code'], 'value' => $w['units']], $goods),
        'rotation' => $rot,
        'retail' => $top($report['retail_by_customer'], 14),
        'still' => $top($report['still_by_customer'], 8),
        'rows' => $report['rows'],
    ];
    $html .= '<script src="/js/chart.umd.min.js"></script><script>window.WR_DATA=' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
    $html .= <<<'JS'
<script>
(function () {
    var D = window.WR_DATA, fmt = function (v) { return Number(v || 0).toLocaleString('es-CL', {maximumFractionDigits: 0}); };
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]; }); };
    var palette = ['#0f766e', '#2563eb', '#eab308', '#dc2626', '#7c3aed', '#ea580c', '#0891b2', '#65a30d', '#db2777', '#475569', '#14b8a6', '#a16207', '#4f46e5', '#be123c', '#94a3b8'];
    if (typeof Chart !== 'undefined') {
        var pct = function (ctx) { var t = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0); return ctx.label + ': ' + fmt(ctx.raw) + (t ? ' (' + (ctx.raw / t * 100).toFixed(1).replace('.', ',') + ' %)' : ''); };
        new Chart(document.getElementById('wr-share'), {type: 'doughnut',
            data: {labels: D.share.map(function (s) { return 'Bodega ' + s.label; }), datasets: [{data: D.share.map(function (s) { return s.value; }), backgroundColor: palette}]},
            options: {maintainAspectRatio: false, plugins: {legend: {position: 'right'}, tooltip: {callbacks: {label: pct}}}}});
        new Chart(document.getElementById('wr-rot'), {type: 'doughnut',
            data: {labels: ['Con movimiento', 'Sin rotación'], datasets: [{data: [D.rotation.moved, D.rotation.still], backgroundColor: ['#0f766e', '#dc2626']}]},
            options: {maintainAspectRatio: false, plugins: {legend: {position: 'right'}, tooltip: {callbacks: {label: pct}}}}});
        var bars = function (id, map, color) {
            var labels = Object.keys(map);
            new Chart(document.getElementById(id), {type: 'bar',
                data: {labels: labels, datasets: [{label: 'Unidades', data: labels.map(function (k) { return map[k]; }), backgroundColor: color, borderRadius: 4, maxBarThickness: 26}]},
                options: {indexAxis: 'y', maintainAspectRatio: false, plugins: {legend: {display: false}, tooltip: {callbacks: {label: function (c) { return fmt(c.raw) + ' unidades'; }}}},
                    scales: {x: {ticks: {callback: function (v) { return fmt(v); }}}}}});
        };
        bars('wr-retail', D.retail, '#0f766e');
        bars('wr-still', D.still, '#dc2626');
    }
    var sel = document.getElementById('wr-f-wh'), q = document.getElementById('wr-f-q'), body = document.getElementById('wr-rows');
    function draw() {
        var w = sel.value, s = q.value.toLowerCase().trim(), html = '', n = 0, sum = 0;
        D.rows.forEach(function (r) {
            if (w && String(r.warehouse) !== w) { return; }
            if (s && (r.sku + ' ' + r.title + ' ' + r.cc + ' ' + r.customer).toLowerCase().indexOf(s) < 0) { return; }
            n++; sum += Number(r.qty) || 0;
            if (n > 1500) { return; }
            html += '<tr><td>' + esc(r.sku) + '</td><td>' + esc(r.title) + '</td><td>' + esc(r.unit) + '</td><td>' + esc(r.warehouse_name) + '</td><td class="n">'
                + fmt(r.qty) + '</td><td>' + esc(r.cc) + '</td><td>' + esc(r.customer) + '</td></tr>';
        });
        body.innerHTML = html || '<tr><td colspan="7" class="wr-empty">Sin resultados</td></tr>';
        document.getElementById('wr-sum').textContent = fmt(sum);
        document.getElementById('wr-f-count').textContent = n + ' fila(s)' + (n > 1500 ? ' · se muestran 1.500; usa el filtro o el Excel' : '');
    }
    sel.addEventListener('change', draw);
    q.addEventListener('input', draw);
    draw();
})();
</script>
JS;
    return $html;
}

/** @param array<string,mixed> $report */
function warehouseStockReportExcel(array $report): void
{
    $c = static fn($v): string => '<td>' . h($v === null ? '' : (string)$v) . '</td>';
    $title = '<h1>Informe de stock consolidado · ' . h($report['generated_at']) . '</h1>';

    $detail = $title . '<table><tr><th>Numero</th><th>Articulo/Familia</th><th>Unidad</th><th>Bodega</th><th>Stock Fisico</th><th>CC</th><th>Cliente</th><th>Kg (bobinas)</th></tr>';
    foreach ($report['rows'] as $r) {
        $detail .= '<tr>' . $c($r['sku']) . $c($r['title']) . $c($r['unit']) . $c($r['warehouse_name']) . $c($r['qty']) . $c($r['cc']) . $c($r['customer']) . $c($r['kg']) . '</tr>';
    }
    $detail .= '</table>';

    $byWh = $title . '<table><tr><th>Bodega</th><th>Tipo</th><th>Stock</th><th>Artículos</th><th>% participación</th><th>Kg</th><th>Capacidad (bobinas)</th><th>% ocupación</th></tr>';
    foreach ($report['warehouses'] as $w) {
        $byWh .= '<tr>' . $c($w['name']) . $c($w['kind'] === 'rolls' ? 'Bobinas' : 'Producto terminado') . $c($w['units']) . $c($w['item_count'])
            . $c($w['share']) . $c($w['kg']) . $c($w['capacity']) . $c($w['occupancy']) . '</tr>';
    }
    $byWh .= '<tr><td>Total producto terminado</td><td></td>' . $c($report['goods_total']) . '<td></td><td>100</td><td></td><td></td><td></td></tr></table>';
    $rot = $report['rotation'];
    $byWh .= '<table><tr><th>Rotación</th><th>Unidades</th></tr><tr><td>Con movimiento</td>' . $c($rot['moved']) . '</tr><tr><td>Sin rotación (bodega 5000)</td>'
        . $c($rot['still']) . '</tr></table>';

    $map = static function (array $m, string $head) use ($title, $c): string {
        $t = $title . '<table><tr><th>Cliente</th><th>' . h($head) . '</th></tr>';
        foreach ($m as $k => $v) {
            $t .= '<tr>' . $c($k) . $c($v) . '</tr>';
        }
        return $t . '<tr><td>Total</td>' . $c(array_sum($m)) . '</tr></table>';
    };
    $cotton = $title . '<table><tr><th>SKU</th><th>Descripción</th><th>Unidad</th><th>Bodega</th><th>Cantidad</th></tr>';
    foreach ($report['cotton'] as $r) {
        $cotton .= '<tr>' . $c($r['sku']) . $c($r['title']) . $c($r['unit']) . $c($r['warehouse']) . $c($r['qty']) . '</tr>';
    }
    $cotton .= '<tr><td>Total</td><td></td><td></td><td></td>' . $c($report['cotton_total']) . '</tr></table>';

    SimpleXlsx::streamHtmlSheets('informe_stock_consolidado_' . date('Ymd_Hi') . '.xlsx', [
        ['title' => 'Stock consolidado', 'html' => $detail],
        ['title' => 'Por bodega', 'html' => $byWh],
        ['title' => 'Cliente B1000', 'html' => $map($report['retail_by_customer'], 'Stock bodega 1000')],
        ['title' => 'Sin rotación', 'html' => $map($report['still_by_customer'], 'Stock bodega 5000')],
        ['title' => 'Algodón', 'html' => $cotton],
    ]);
}
