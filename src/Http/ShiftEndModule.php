<?php

declare(strict_types=1);

/**
 * Módulo HTTP · Final del turno (solo lectura del ERP).
 *
 *  - /turno ............ redirige a Producción.
 *  - /turno/produccion . Lo producido en el día por máquina; «+» despliega el detalle de cada máquina.
 *  - /turno/bodega ..... Pendiente de definir.
 */

require_once __DIR__ . '/../ShiftEndProductionService.php';
require_once __DIR__ . '/../ShiftEndWarehouseService.php';

const SHIFT_END_ASSET_VERSION = '20261112d';

function handleShiftEndRoutes(string $path, string $method, string $name): bool
{
    if (!in_array($path, ['/turno', '/turno/produccion', '/turno/bodega', '/turno/bodega/excel'], true) || $method !== 'GET') {
        return false;
    }
    if ($path === '/turno') {
        redirectResponse('/turno/produccion');
        return true;
    }
    $styles = '<link rel="stylesheet" href="/assets/css/unibag-shift-end.css?v=' . SHIFT_END_ASSET_VERSION . '">';
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        render('Final del turno', $styles . '<div class="se-shell"><div class="se-alert">Final del turno requiere permisos del área ERP.</div></div>');
        return true;
    }
    $company = (string)($_SESSION['company_name'] ?? 'UNIBAG CHILE');
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $date = ShiftEndProductionService::normalizeDate((string)($_GET['fecha'] ?? date('Y-m-d')));
    if ($path === '/turno/bodega' || $path === '/turno/bodega/excel') {
        try {
            $trz = null;
            try {
                $trz = Db::trzPdo();
            } catch (Throwable) {
                $trz = null;
            }
            $data = (new ShiftEndWarehouseService(Db::erpPdo(), $trz))->cachedDay($date, isset($_GET['refresh']));
            if ($path === '/turno/bodega/excel') {
                shiftEndWarehouseExcel($data);
                return true;
            }
            render('Final del turno · Bodega', $styles . shiftEndWarehousePage($data, $company));
        } catch (Throwable $e) {
            render('Final del turno · Bodega', $styles . '<div class="se-shell">' . shiftEndHeader($date, 'bodega')
                . '<div class="se-alert">No se pudo generar el cierre de bodega: ' . h($e->getMessage()) . '</div></div>');
        }
        return true;
    }
    try {
        $data = (new ShiftEndProductionService(Db::erpPdo()))->cachedDay($date, isset($_GET['refresh']));
        render('Final del turno · Producción', $styles . shiftEndProductionPage($data, $company));
    } catch (Throwable $e) {
        render('Final del turno · Producción', $styles . '<div class="se-shell">' . shiftEndHeader($date)
            . '<div class="se-alert">No se pudo leer la producción del ERP: ' . h($e->getMessage()) . '</div></div>');
    }
    return true;
}

function seN(float|int|null $v, int $dec = 0): string
{
    return $v === null ? '—' : number_format((float)$v, $dec, ',', '.');
}

function shiftEndDayLabel(string $date): string
{
    $d = new DateTimeImmutable($date);
    $days = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    return $days[(int)$d->format('w')] . ' ' . $d->format('d/m/Y');
}

function shiftEndHeader(string $date, string $area = 'produccion'): string
{
    $d = new DateTimeImmutable($date);
    $prev = $d->modify('-1 day')->format('Y-m-d');
    $next = $d->modify('+1 day')->format('Y-m-d');
    $isToday = $date === date('Y-m-d');
    $base = '/turno/' . $area;
    $html = '<div class="se-head se-noprint"><div><div class="se-title">Final del turno</div>'
        . '<nav class="se-tabs"><a href="/turno/produccion?fecha=' . h($date) . '"' . ($area === 'bodega' ? '' : ' class="on"') . '>🏭 Producción</a>'
        . '<a href="/turno/bodega?fecha=' . h($date) . '"' . ($area === 'bodega' ? ' class="on"' : '') . '>📦 Bodega</a></nav></div>';
    $html .= '<form class="se-actions" method="get" action="' . h($base) . '">'
        . '<div class="se-datebox"><a class="se-nav" href="' . h($base) . '?fecha=' . h($prev) . '" title="Día anterior">◀</a>'
        . '<input type="date" name="fecha" value="' . h($date) . '" max="' . h(date('Y-m-d')) . '" onchange="this.form.submit()" aria-label="Fecha del turno">'
        . ($isToday ? '<span class="se-nav off">▶</span>' : '<a class="se-nav" href="' . h($base) . '?fecha=' . h($next) . '" title="Día siguiente">▶</a>') . '</div>'
        . ($isToday ? '' : '<a class="se-btn sec" href="' . h($base) . '">Hoy</a>')
        . '<a class="se-btn sec" href="' . h($base) . '?fecha=' . h($date) . '&refresh=1" title="Volver a leer los datos del ERP">↻ Actualizar</a>'
        . '<button type="button" class="se-btn sec" onclick="window.print()">🖨 Imprimir</button>';
    if ($area === 'bodega') {
        $html .= '<a class="se-btn" href="/turno/bodega/excel?fecha=' . h($date) . '">⬇ Excel</a>';
    }
    return $html . '</form></div>';
}

/** Encabezado del informe (pantalla e impresión). */
function shiftEndDocHead(string $company, string $title, string $date, string $generatedAt, string $source): string
{
    return '<header class="se-doc-head"><div><div class="se-doc-co">' . h($company) . '</div><h1>' . h($title) . '</h1>'
        . '<div class="se-doc-date">Turno del <b>' . h(shiftEndDayLabel($date)) . '</b> · de 00:00 a 23:59</div></div>'
        . '<div class="se-doc-meta">Emitido: <b>' . h(date('d/m/Y H:i', strtotime($generatedAt))) . '</b><br>Fuente: ' . h($source) . '</div></header>';
}

/** Indicador grande del resumen. $tone: prod | waste | info | ok | warn. */
function shiftEndKpi(string $label, string $value, string $note, string $tone = 'info'): string
{
    return '<div class="se-kpi t-' . h($tone) . '"><span>' . h($label) . '</span><b>' . $value . '</b><small>' . $note . '</small></div>';
}

/** Cantidad sin decimales cuando es entera (120 y no 120,00). */
function seQ(float|int|null $v): string
{
    return $v === null ? '—' : seN($v, abs((float)$v - round((float)$v)) < 0.005 ? 0 : 2);
}

/** Tabla con encabezado; $rows ya en HTML. */
function shiftEndTable(array $heads, string $rows, string $emptyText, int $count, string $foot = ''): string
{
    if ($count === 0) {
        return '<div class="se-none">' . h($emptyText) . '</div>';
    }
    $th = '';
    foreach ($heads as $hd) {
        $num = str_starts_with($hd, '#');
        $th .= '<th' . ($num ? ' class="n"' : '') . '>' . h($num ? substr($hd, 1) : $hd) . '</th>';
    }
    return '<div class="se-table-wrap"><table class="se-table doc"><thead><tr>' . $th . '</tr></thead><tbody>' . $rows . '</tbody>'
        . ($foot !== '' ? '<tfoot>' . $foot . '</tfoot>' : '') . '</table></div>';
}

/** @param array<string,mixed> $d */
function shiftEndWarehousePage(array $d, string $company): string
{
    $t = $d['totals'];
    $c = static fn($v, string $cls = ''): string => '<td' . ($cls !== '' ? ' class="' . $cls . '"' : '') . '>' . h((string)$v) . '</td>';
    $html = '<div class="se-shell">' . shiftEndHeader($d['date'], 'bodega');
    $html .= '<article class="se-doc">';
    $html .= shiftEndDocHead($company, 'Informe de cierre de turno · Bodega', (string)$d['date'], (string)$d['generated_at'], 'ERP y trazabilidad');
    foreach ($d['warnings'] as $w) {
        $html .= '<div class="se-alert warn">' . h($w) . '</div>';
    }

    // Resumen.
    $html .= '<section class="se-sec"><h2>Resumen del día</h2><div class="se-kpis four">'
        . shiftEndKpi('Bobinas recibidas', seN($t['rolls']), seN($t['rolls_kg'], 1) . ' kg · ' . (int)$t['purchase_lines'] . ' línea(s) de otras compras', 'info')
        . shiftEndKpi('Despachos', seN($t['guides']), seN($t['dispatched_units']) . ' unidades despachadas', 'prod')
        . shiftEndKpi('Producto terminado ingresado', seN($t['finished_units']), 'unidades en ' . (int)$t['finished_closures'] . ' cierre(s) de embalaje', 'ok')
        . shiftEndKpi('Ingresos registrados por bodega', seN($t['entries']), '', 'warn') . '</div>';
    $rows = '';
    foreach ($d['by_warehouse'] as $w) {
        $rows .= '<tr' . ($w['warehouse'] === 'Sin bodega' ? ' class="se-warnrow"' : '') . '>' . $c($w['warehouse']) . $c($w['rolls_in'] > 0 ? seN($w['rolls_in']) . ' (' . seN($w['kg_in'], 1) . ' kg)' : '—', 'n')
            . $c($w['purchases_in'] > 0 ? seN($w['purchases_in']) : '—', 'n') . $c($w['production_in'] > 0 ? seN($w['production_in']) : '—', 'n')
            . $c($w['entries_in'] > 0 ? seN($w['entries_in']) : '—', 'n') . $c($w['dispatched'] > 0 ? seN($w['dispatched']) : '—', 'n') . '</tr>';
    }
    $html .= '<h3>Movimientos por bodega</h3>' . shiftEndTable(['Bodega', '#Bobinas recibidas', '#Otras compras', '#Desde producción (und)', '#Ingresos de bodega', '#Despachado (und)'],
        $rows, 'Sin movimientos de bodega en el día.', count($d['by_warehouse'])) . '</section>';

    // 1. Recepciones.
    $rows = '';
    foreach ($d['receptions'] as $r) {
        $codes = $r['codes'];
        $codeText = implode(', ', array_slice($codes, 0, 4)) . (count($codes) > 4 ? ' y ' . (count($codes) - 4) . ' más' : '');
        $rows .= '<tr>' . $c($r['first'] . ($r['last'] !== $r['first'] ? '–' . $r['last'] : '')) . $c($r['document'] ?: '—') . $c($r['supplier'] ?: '—')
            . '<td><b>' . h($r['sku']) . '</b> ' . h($r['description']) . '<div class="se-small">' . h($codeText) . '</div></td>'
            . $c(seN($r['rolls']), 'n') . $c(seN($r['kg'], 1), 'n') . $c($r['warehouse']) . '<td><b>' . h($r['received_by']) . '</b></td></tr>';
    }
    $foot = $d['receptions'] === [] ? '' : '<tr><td colspan="4">Total</td>' . $c(seN($t['rolls']), 'n') . $c(seN($t['rolls_kg'], 1), 'n') . '<td colspan="2"></td></tr>';
    $html .= '<section class="se-sec"><h2>1. Recepciones de proveedores</h2><h3>1.1 Bobinas recibidas por orden de compra o contenedor</h3>'
        . shiftEndTable(['Hora', 'Documento', 'Proveedor', 'Artículo', '#Bobinas', '#Kg', 'Bodega de destino', 'Recibido por'], $rows,
            'No se recibieron bobinas en el día.', count($d['receptions']), $foot);
    $rows = '';
    foreach ($d['purchases'] as $r) {
        $rows .= '<tr>' . $c($r['time']) . $c($r['document']) . '<td><b>' . h($r['sku']) . '</b> ' . h($r['description']) . '</td>'
            . $c(seQ($r['qty']), 'n') . $c($r['warehouse']) . $c($r['user']) . '</tr>';
    }
    $html .= '<h3>1.2 Otras compras ingresadas en el ERP</h3>' . shiftEndTable(['Hora', 'Documento', 'Artículo', '#Cantidad', 'Bodega', 'Ingresado por'], $rows,
        'No hay otras compras ingresadas en el ERP en el día.', count($d['purchases'])) . '</section>';

    // 2. Despachos.
    $rows = '';
    foreach ($d['dispatches'] as $g) {
        $who = $g['confirmed_by'] !== '' ? '<b>' . h($g['confirmed_by']) . '</b>' . ($g['picked_by'] !== '' && $g['picked_by'] !== $g['confirmed_by'] ? '<div class="se-small">Preparó: ' . h($g['picked_by']) . '</div>' : '')
            : '<span class="se-small">Sin escaneo en bodega</span>';
        $rows .= '<tr class="se-group">' . $c($g['time']) . '<td><b>' . h($g['guide']) . '</b></td>' . $c($g['customer'] ?: '—') . $c(implode(', ', $g['cc']) ?: '—')
            . $c(implode(', ', $g['warehouses'])) . $c(seN($g['units']), 'n strong') . $c($g['issued_by']) . '<td>' . $who . '</td></tr>';
        foreach ($g['items'] as $it) {
            $rows .= '<tr class="se-item"><td></td><td colspan="3">↳ <b>' . h($it['sku']) . '</b> ' . h($it['description']) . '</td>' . $c($it['warehouse']) . $c(seN($it['qty']), 'n') . '<td colspan="2"></td></tr>';
        }
    }
    $foot = $d['dispatches'] === [] ? '' : '<tr><td colspan="5">Total despachado por guía</td>' . $c(seN(array_sum(array_column($d['dispatches'], 'units'))), 'n') . '<td colspan="2"></td></tr>';
    $html .= '<section class="se-sec"><h2>2. Despachos</h2><h3>2.1 Salidas por guía de despacho</h3>'
        . shiftEndTable(['Hora', 'Guía', 'Cliente', 'CC', 'Bodega de salida', '#Unidades', 'Emitida por', 'Despacho confirmado por'], $rows,
            'No hubo despachos por guía en el día.', count($d['dispatches']), $foot);
    if ($d['unmatched_dispatches'] !== []) {
        $rows = '';
        foreach ($d['unmatched_dispatches'] as $u) {
            $rows .= '<tr>' . $c($u['time']) . $c($u['picking']) . $c($u['document']) . $c($u['customer'] ?: '—') . $c($u['type'])
                . $c(seN($u['units']), 'n') . $c($u['scanned'], 'n') . $c($u['picked_by']) . '<td><b>' . h($u['confirmed_by']) . '</b></td></tr>';
        }
        $html .= '<h3>2.2 Despachos confirmados en bodega con documento de otro día o factura</h3>'
            . shiftEndTable(['Hora', 'Despacho', 'Documento', 'Cliente', 'Tipo', '#Unidades', '#Códigos escaneados', 'Preparó', 'Confirmó'], $rows, '', count($d['unmatched_dispatches']));
    }
    $html .= '</section>';

    // 3. Producto terminado.
    $rows = '';
    foreach ($d['finished'] as $r) {
        $rows .= '<tr>' . $c($r['time']) . '<td>' . h($r['process']) . '<div class="se-small">' . h($r['machine']) . '</div></td>' . $c($r['ot'] ?: '—')
            . '<td>' . h($r['cc'] ?: '—') . ($r['mixed'] ? ' <span class="se-tag semi">Mixto</span>' : '') . '<div class="se-small">' . h($r['customer']) . '</div></td>'
            . '<td><b>' . h($r['sku']) . '</b> ' . h($r['description']) . '</td>' . $c(seN($r['boxes']), 'n') . $c(seN($r['units']), 'n strong')
            . '<td>' . ($r['warehouse'] === 'Sin bodega' ? '<span class="se-tag bad">Sin bodega</span>' : h($r['warehouse'])) . '</td>'
            . $c($r['operator']) . '<td><b>' . h($r['supervisor']) . '</b></td></tr>';
    }
    $foot = $d['finished'] === [] ? '' : '<tr><td colspan="5">Total ingresado desde producción</td>' . $c(seN(array_sum(array_column($d['finished'], 'boxes'))), 'n')
        . $c(seN($t['finished_units']), 'n') . '<td colspan="3"></td></tr>';
    $html .= '<section class="se-sec"><h2>3. Producto terminado desde Corte y Sellado y Embalaje</h2>'
        . shiftEndTable(['Hora', 'Proceso / máquina', 'OT', 'CC / cliente', 'Producto', '#Cajas', '#Unidades', 'Bodega de destino', 'Embaló', 'Validó el cierre'], $rows,
            'No hubo cierres de embalaje en el día.', count($d['finished']), $foot) . '</section>';

    // 4. Ingresos de bodega.
    $rows = '';
    foreach ($d['entries'] as $r) {
        $rows .= '<tr>' . $c($r['time']) . $c($r['type']) . '<td><b>' . h($r['sku']) . '</b> ' . h($r['description']) . '</td>'
            . $c(seN($r['qty']) . ' ' . $r['unit'], 'n') . $c(seN($r['labels']), 'n') . $c($r['warehouse']) . $c($r['reference'] ?: '—') . '<td><b>' . h($r['received_by']) . '</b></td></tr>';
    }
    $html .= '<section class="se-sec"><h2>4. Mercadería recibida y guardada por bodega</h2>'
        . shiftEndTable(['Hora', 'Tipo', 'Artículo', '#Cantidad', '#Etiquetas', 'Bodega donde se guardó', 'Referencia', 'Recibido por'], $rows,
            'No hubo ingresos registrados por bodega en el día.', count($d['entries'])) . '</section>';

    return $html . '</article></div>';
}

/** @param array<string,mixed> $d */
function shiftEndWarehouseExcel(array $d): void
{
    $c = static fn($v): string => '<td>' . h($v === null ? '' : (string)$v) . '</td>';
    $title = '<h1>Cierre de turno bodega · ' . h(shiftEndDayLabel($d['date'])) . '</h1>';
    $table = static function (array $heads, array $rows) use ($c, $title): string {
        $t = $title . '<table><tr>' . implode('', array_map(static fn($x) => '<th>' . h($x) . '</th>', $heads)) . '</tr>';
        foreach ($rows as $r) {
            $t .= '<tr>' . implode('', array_map($c, $r)) . '</tr>';
        }
        return $t . '</table>';
    };
    SimpleXlsx::streamHtmlSheets('cierre_bodega_' . $d['date'] . '.xlsx', [
        'Por bodega' => $table(['Bodega', 'Bobinas recibidas', 'Kg recibidos', 'Otras compras', 'Desde producción', 'Ingresos de bodega', 'Despachado'],
            array_map(static fn($w) => [$w['warehouse'], $w['rolls_in'], round($w['kg_in'], 1), $w['purchases_in'], $w['production_in'], $w['entries_in'], $w['dispatched']], $d['by_warehouse'])),
        'Recepciones' => $table(['Hora', 'Documento', 'Proveedor', 'Código', 'Artículo', 'Bobinas', 'Kg', 'Bodega', 'Recibido por', 'Códigos'],
            array_map(static fn($r) => [$r['first'], $r['document'], $r['supplier'], $r['sku'], $r['description'], $r['rolls'], round($r['kg'], 1), $r['warehouse'], $r['received_by'], implode(' ', $r['codes'])], $d['receptions'])),
        'Otras compras' => $table(['Hora', 'Documento', 'Código', 'Artículo', 'Cantidad', 'Bodega', 'Ingresado por'],
            array_map(static fn($r) => [$r['time'], $r['document'], $r['sku'], $r['description'], $r['qty'], $r['warehouse'], $r['user']], $d['purchases'])),
        'Despachos' => $table(['Hora', 'Guía', 'Cliente', 'CC', 'Código', 'Artículo', 'Cantidad', 'Bodega', 'Emitida por', 'Preparó', 'Confirmó'],
            array_merge(...array_map(static fn($g) => array_map(static fn($it) => [$g['time'], $g['guide'], $g['customer'], $it['cc'], $it['sku'], $it['description'], $it['qty'], $it['warehouse'],
                $g['issued_by'], $g['picked_by'], $g['confirmed_by']], $g['items']), $d['dispatches'] ?: [['items' => []]]))),
        'Desde producción' => $table(['Hora', 'Proceso', 'Máquina', 'OT', 'CC', 'Cliente', 'Código', 'Producto', 'Cajas', 'Unidades', 'Bodega', 'Embaló', 'Validó'],
            array_map(static fn($r) => [$r['time'], $r['process'], $r['machine'], $r['ot'], $r['cc'], $r['customer'], $r['sku'], $r['description'], $r['boxes'], $r['units'], $r['warehouse'], $r['operator'], $r['supervisor']], $d['finished'])),
        'Ingresos bodega' => $table(['Hora', 'Tipo', 'Código', 'Artículo', 'Cantidad', 'Unidad', 'Etiquetas', 'Bodega', 'Referencia', 'Recibido por'],
            array_map(static fn($r) => [$r['time'], $r['type'], $r['sku'], $r['description'], $r['qty'], $r['unit'], $r['labels'], $r['warehouse'], $r['reference'], $r['received_by']], $d['entries'])),
    ]);
}


/** @param array<string,mixed> $data */
function shiftEndProductionPage(array $data, string $company = 'UNIBAG CHILE'): string
{
    $html = '<div class="se-shell">' . shiftEndHeader($data['date']);
    $t = $data['totals'];
    $machines = $data['machines'];
    $html .= '<article class="se-doc">' . shiftEndDocHead($company, 'Informe de cierre de turno · Producción', (string)$data['date'], (string)$data['generated_at'], 'ERP (registros de producción y merma)');

    if ((int)$t['open_records'] > 0) {
        $html .= '<div class="se-alert warn">⚠ ' . (int)$t['open_records'] . ' registro(s) de producción siguen abiertos (sin hora de término): su cantidad puede cambiar cuando el operador los cierre.</div>';
    }
    if ($machines === []) {
        return $html . '<div class="se-empty">No hay producción registrada en este día.</div></article></div>';
    }

    // Totales por proceso (sumados desde las máquinas).
    $procs = [];
    $operators = [];
    $wasteUnits = 0.0;
    $meters = 0.0;
    foreach ($machines as $m) {
        $p = $m['process'];
        $procs[$p] ??= ['machines' => 0, 'units' => 0.0, 'meters' => 0.0, 'waste_kg' => 0.0, 'waste_units' => 0.0, 'records' => 0, 'list' => []];
        $procs[$p]['machines']++;
        $procs[$p]['units'] += $m['units'];
        $procs[$p]['meters'] += $m['meters'];
        $procs[$p]['waste_kg'] += $m['waste_kg'];
        $procs[$p]['waste_units'] += $m['waste_units'];
        $procs[$p]['records'] += $m['records'];
        $procs[$p]['list'][] = $m;
        foreach ($m['operators'] as $op) {
            $operators[$op] = true;
        }
        $wasteUnits += $m['waste_units'];
        $meters += $m['meters'];
    }
    $totalUnits = (float)$t['units'];

    // 1. Resumen.
    $html .= '<section class="se-sec"><h2>1. Resumen del día</h2><div class="se-kpis four">'
        . shiftEndKpi('Unidades producidas', seN($totalUnits), '', 'prod')
        . shiftEndKpi('Máquinas que trabajaron', (string)(int)$t['machines'], count($operators) . ' operador(es) · ' . count($procs) . ' proceso(s)', 'info')
        . shiftEndKpi('Merma registrada', seN($t['waste_kg'], 1) . ' <em>kg</em>', seN($wasteUnits) . ' unidades de merma', 'waste')
        . shiftEndKpi('Registros de producción', (string)(int)$t['records'], (int)$t['open_records'] > 0 ? (int)$t['open_records'] . ' abierto(s), revisar' : 'todos cerrados', (int)$t['open_records'] > 0 ? 'warn' : 'ok')
        . '</div>';
    $rows = '';
    foreach ($procs as $name => $p) {
        $share = $totalUnits > 0 ? $p['units'] / $totalUnits * 100 : 0;
        $rows .= '<tr><td><b>' . h($name) . '</b></td><td class="n">' . $p['machines'] . '</td><td class="n strong">' . seN($p['units']) . '</td>'
            . '<td><div class="se-bar"><i style="width:' . round($share, 1) . '%"></i></div><span class="se-small">' . seN($share, 1) . '% del día</span></td>'
            . '<td class="n">' . ($p['meters'] > 0 ? seN($p['meters']) : '—') . '</td><td class="n">' . seN($p['waste_kg'], 1) . '</td><td class="n">' . seN($p['waste_units']) . '</td></tr>';
    }
    $foot = '<tr><td>Total del día</td><td class="n">' . (int)$t['machines'] . '</td><td class="n">' . seN($totalUnits) . '</td><td></td><td class="n">' . ($meters > 0 ? seN($meters) : '—')
        . '</td><td class="n">' . seN($t['waste_kg'], 1) . '</td><td class="n">' . seN($wasteUnits) . '</td></tr>';
    $html .= '<h3>Producción por proceso</h3>' . shiftEndTable(['Proceso', '#Máquinas', '#Unidades', 'Participación', '#Metros', '#Merma (kg)', '#Merma (und)'], $rows, '', count($procs), $foot) . '</section>';

    // 2. Detalle por máquina, agrupado por proceso.
    $html .= '<section class="se-sec"><h2>2. Detalle por máquina</h2>'
        . '<div class="se-tools se-noprint">'
        . '<button type="button" class="se-btn sec sm" data-se-all="1">＋ Abrir todas</button><button type="button" class="se-btn sec sm" data-se-all="0">－ Cerrar todas</button></div>';
    $html .= '<div class="se-table-wrap"><table class="se-table se-machines"><thead><tr><th class="se-col-btn"></th><th>Máquina</th><th>Operador(es)</th>'
        . '<th class="n">OT</th><th class="n">Unidades</th><th class="n">Metros</th><th class="n">Merma (kg)</th><th class="n">Merma (und)</th><th>Horario</th><th>Estado</th></tr></thead><tbody>';
    $i = 0;
    foreach ($procs as $name => $p) {
        $html .= '<tr class="se-proc"><td colspan="10">' . h($name) . ' <span>· ' . $p['machines'] . ' máquina(s)</span></td></tr>';
        foreach ($p['list'] as $m) {
            $id = 'se-d-' . $i++;
            $state = [];
            if ($m['open_records'] > 0) {
                $state[] = '<span class="se-tag open">' . (int)$m['open_records'] . ' abierto(s)</span>';
            }
            if ($m['units'] <= 0 && $m['records'] > $m['semi_records']) {
                $state[] = '<span class="se-tag bad">Sin unidades</span>';
            }
            if ($m['semi_records'] > 0) {
                $state[] = '<span class="se-tag semi" title="Bobinas semielaboradas: se muestran en el detalle pero no suman unidades">' . (int)$m['semi_records'] . ' bobina(s) SE</span>';
            }
            if ($state === []) {
                $state[] = '<span class="se-tag ok">✓ Cerrado</span>';
            }
            $html .= '<tr class="se-row"><td class="se-col-btn"><button type="button" class="se-plus" aria-expanded="false" aria-controls="' . $id . '" title="Ver detalle">+</button></td>'
                . '<td><b>' . h($m['machine_name']) . '</b></td><td>' . h(implode(', ', $m['operators'])) . '</td>'
                . '<td class="n">' . count($m['ots']) . '</td><td class="n strong">' . seN($m['units']) . '</td><td class="n">' . ($m['meters'] > 0 ? seN($m['meters']) : '—') . '</td>'
                . '<td class="n">' . seN($m['waste_kg'], 1) . '</td><td class="n">' . seN($m['waste_units']) . '</td>'
                . '<td class="nowrap">' . h(($m['first'] ?? '—') . ' – ' . ($m['last'] ?? '—')) . '</td><td>' . implode(' ', $state) . '</td></tr>';
            $html .= '<tr class="se-detail" id="' . $id . '" hidden><td colspan="10">' . shiftEndMachineDetail($m) . '</td></tr>';
        }
        $html .= '<tr class="se-subtotal"><td class="se-col-btn"></td><td colspan="3">Subtotal ' . h($name) . '</td><td class="n">' . seN($p['units']) . '</td><td class="n">' . ($p['meters'] > 0 ? seN($p['meters']) : '—')
            . '</td><td class="n">' . seN($p['waste_kg'], 1) . '</td><td class="n">' . seN($p['waste_units']) . '</td><td colspan="2"></td></tr>';
    }
    $html .= '</tbody><tfoot><tr><td class="se-col-btn"></td><td colspan="3">Total del día</td><td class="n">' . seN($totalUnits) . '</td><td class="n">' . ($meters > 0 ? seN($meters) : '—')
        . '</td><td class="n">' . seN($t['waste_kg'], 1) . '</td><td class="n">' . seN($wasteUnits) . '</td><td colspan="2"></td></tr></tfoot></table></div></section>';

    $html .= '</article>';

    $html .= '<script>(function(){function set(b,open){var r=document.getElementById(b.getAttribute("aria-controls"));if(!r)return;r.hidden=!open;b.setAttribute("aria-expanded",open?"true":"false");b.textContent=open?"−":"+";b.closest("tr").classList.toggle("is-open",open);}'
        . 'document.querySelectorAll(".se-plus").forEach(function(b){b.addEventListener("click",function(){set(b,b.getAttribute("aria-expanded")!=="true");});});'
        . 'document.querySelectorAll("[data-se-all]").forEach(function(x){x.addEventListener("click",function(){var o=x.getAttribute("data-se-all")==="1";document.querySelectorAll(".se-plus").forEach(function(b){set(b,o);});});});})();</script>';
    return $html . '</div>';
}

/** @param array<string,mixed> $m */
function shiftEndMachineDetail(array $m): string
{
    $html = '<div class="se-detail-box">';
    // Por OT: programado vs producido hoy.
    $html .= '<div class="se-sub">Órdenes de trabajo (OT) <span>' . count($m['ots']) . '</span></div><div class="se-table-wrap"><table class="se-table inner"><thead><tr><th>OT</th><th>CC</th><th>Producto</th>'
        . '<th class="n">Programado OT</th><th class="n">Producido hoy</th><th>Avance del día</th><th class="n">Merma (und)</th><th class="n">Merma (kg)</th></tr></thead><tbody>';
    foreach ($m['ots'] as $o) {
        $pct = $o['planned'] > 0 ? $o['units'] / $o['planned'] * 100 : null;
        $html .= '<tr><td><b>' . h($o['ot'] ?: '—') . '</b></td><td>' . h($o['cc'] ?: '—') . '</td><td>' . ($o['sku'] !== '' ? '<b>' . h($o['sku']) . '</b> ' : '') . h($o['product'] ?: ($o['sku'] === '' ? '—' : '')) . '</td>'
            . '<td class="n">' . seN($o['planned']) . '</td><td class="n strong">' . seN($o['units']) . '</td>'
            . '<td>' . ($pct === null ? '—' : '<div class="se-bar"><i style="width:' . min(100, round($pct, 1)) . '%"></i></div><span class="se-small">' . seN($pct, 1) . '% de la OT</span>') . '</td>'
            . '<td class="n">' . seN($o['waste_units']) . '</td><td class="n">' . seN($o['waste_kg'], 1) . '</td></tr>';
    }
    $html .= '</tbody></table></div>';

    $html .= '<div class="se-sub">Registros de producción <span>' . count($m['production']) . '</span></div>';
    if ($m['production'] === []) {
        $html .= '<div class="se-muted">Sin registros de producción (solo merma).</div>';
    } else {
        $html .= '<div class="se-table-wrap"><table class="se-table inner"><thead><tr><th>Inicio</th><th>Término</th><th>Operador</th><th>OT</th><th>CC</th>'
            . '<th class="n">Cantidad</th><th class="n">Metros</th><th class="n">Kg</th><th>Observación</th></tr></thead><tbody>';
        foreach ($m['production'] as $p) {
            $cls = $p['semi'] ? ' class="semi"' : '';
            $html .= '<tr' . $cls . '><td>' . h($p['start']) . '</td><td>' . ($p['open'] ? '<span class="se-tag open">abierto</span>' : h((string)$p['end'])) . '</td>'
                . '<td>' . h($p['operator']) . '</td><td>' . h($p['ot'] ?: '—') . '</td><td>' . h($p['cc'] ?: '—') . '</td>'
                . '<td class="n strong">' . seN($p['units']) . ($p['semi'] ? ' <span class="se-tag semi" title="Bobina semielaborada: no suma unidades">SE</span>' : '') . '</td>'
                . '<td class="n">' . ($p['meters'] > 0 ? seN($p['meters']) : '—') . '</td><td class="n">' . ($p['kg'] > 0 ? seN($p['kg'], 1) : '—') . '</td>'
                . '<td class="obs">' . h($p['comments'] !== '' ? $p['comments'] : '—') . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
    }

    $html .= '<div class="se-sub">Mermas registradas <span>' . count($m['waste']) . '</span></div>';
    if ($m['waste'] === []) {
        $html .= '<div class="se-muted">Sin merma registrada en el día.</div>';
    } else {
        $html .= '<div class="se-table-wrap"><table class="se-table inner"><thead><tr><th>Hora</th><th>Operador</th><th>OT</th><th>Tipo de merma</th>'
            . '<th class="n">Unidades</th><th class="n">Kg</th><th>Observación</th></tr></thead><tbody>';
        foreach ($m['waste'] as $w) {
            $html .= '<tr><td>' . h($w['time']) . '</td><td>' . h($w['operator']) . '</td><td>' . h($w['ot'] ?: '—') . '</td><td>' . h($w['type']) . '</td>'
                . '<td class="n">' . seN($w['units']) . '</td><td class="n">' . seN($w['kg'], 1) . '</td><td class="obs">' . h($w['comments'] !== '' ? $w['comments'] : '—') . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
    }
    return $html . '</div>';
}
