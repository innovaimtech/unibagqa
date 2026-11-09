<?php

declare(strict_types=1);

/**
 * Módulo HTTP · Stock esperado (Informes)
 *
 *  - /reports/stock-esperado ........... Informe (stock ERP, reservado, en producción, proyectado) y maestro editable.
 *  - /reports/stock-esperado/data ...... JSON del informe (refresco automático).
 *  - /reports/stock-esperado/detail .... JSON (o Excel con format=xlsx) de la ventana: type=reserved (NV sin despachar)
 *                                         o type=production (CC/OT por fabricar). Solo lectura.
 *  - /reports/stock-esperado/excel ..... Exportación a Excel.
 *  - /reports/stock-esperado/save|add|import (POST) .... Cambios al maestro.
 */

require_once __DIR__ . '/../ExpectedStockService.php';

const EXPECTED_STOCK_ASSET_VERSION = '20261109f';

function expectedStockRoute(string $path): bool
{
    return in_array($path, ['/reports/stock-esperado', '/reports/stock-esperado/data', '/reports/stock-esperado/excel', '/reports/stock-esperado/detail',
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
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode($service->report(), JSON_UNESCAPED_UNICODE);
            return true;
        }
        if ($path === '/reports/stock-esperado/detail') {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            $type = (string)($_GET['type'] ?? '') === 'production' ? 'production' : 'reserved';
            $code = (string)($_GET['code'] ?? '');
            $asExcel = (string)($_GET['format'] ?? '') === 'xlsx';
            try {
                $detail = $type === 'production' ? $service->productionDetail($code) : $service->reservedDetail($code);
            } catch (Throwable $e) {
                if ($asExcel) {
                    throw $e;
                }
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store');
                echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
                return true;
            }
            if ($asExcel) {
                expectedStockDetailExcel($type, $detail);
                return true;
            }
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode(['ok' => true, 'type' => $type] + $detail, JSON_UNESCAPED_UNICODE);
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
    $cell = static fn($v): string => '<td>' . h($v === null ? '' : (string)$v) . '</td>';
    $html = '<table><tr><th>Código</th><th>Descripción</th><th>Grupo</th><th>Bodega</th><th>Stock</th><th>Pallets</th><th>Cajas</th><th>Unid. sueltas</th>'
        . '<th>Vendido sin despachar (NV)</th><th>N° NV</th><th>Libre</th><th>Esperando impresión</th><th>Esperando corte y sellado</th><th>N° CC</th><th>Proyectado</th>'
        . '<th>Objetivo</th><th>Mínimo</th><th>Diferencia</th><th>Diferencia proyectada</th><th>Estado</th><th>Estado proyectado</th><th>Otra bodega</th></tr>';
    foreach ($report['rows'] as $r) {
        $html .= '<tr>' . $cell($r['code']) . $cell($r['description']) . $cell($r['group_label'] ?? '') . $cell($r['warehouse_code'] ?? '') . $cell((int)$r['stock'])
            . $cell($r['pallets'] ?? '') . $cell($r['boxes'] ?? '') . $cell($r['loose'] ?? '')
            . $cell($r['reserved']) . $cell($r['reserved_orders'] ?? '') . $cell($r['free']) . $cell($r['to_print'] ?? '') . $cell($r['to_seal'] ?? '') . $cell($r['production_orders'] ?? '')
            . $cell($r['projected']) . $cell($r['target_units'] ?? '') . $cell($r['min_units'] ?? '') . $cell($r['diff'] ?? '') . $cell($r['projected_diff'] ?? '')
            . $cell($r['status']) . $cell($r['projected_status'] ?? '') . $cell((int)$r['other_stock']) . '</tr>';
    }
    SimpleXlsx::streamHtml('stock_esperado_' . date('Ymd_Hi') . '.xlsx', $html . '</table>', 'Stock esperado');
}

function expectedStockDetailExcel(string $type, array $detail): void
{
    $cell = static fn($v): string => '<td>' . h($v === null ? '' : (string)$v) . '</td>';
    $code = (string)($detail['item']['code'] ?? '');
    if ($type === 'production') {
        $html = '<table><tr><th>CC</th><th>OT</th><th>Cliente</th><th>Vendedor</th><th>Fecha NV</th><th>Entrega comprometida</th><th>Próxima entrega planificada</th>'
            . '<th>Pedido</th><th>Impreso</th><th>Sellado/embalado</th><th>Esperando impresión</th><th>Esperando corte y sellado</th><th>Avance %</th><th>Etapa</th><th>Última actividad</th><th>Stock de la CC</th><th>Estado NV</th><th>Máquinas</th></tr>';
        foreach ($detail['rows'] as $r) {
            $machines = implode(' | ', array_map(static fn($a) => $a['process'] . ' ' . $a['machine'] . ' (' . $a['date'] . '): ' . $a['produced'] . '/' . $a['planned'] . ' ' . $a['unit'] . ' ' . $a['state'], $r['agendas']));
            $html .= '<tr>' . $cell($r['cc']) . $cell($r['ot']) . $cell($r['customer']) . $cell($r['seller']) . $cell($r['created']) . $cell($r['due']) . $cell($r['next_plan'])
                . $cell($r['ordered']) . $cell($r['needs_print'] ? $r['printed'] : 'sin impresión') . $cell($r['sealed']) . $cell($r['to_print']) . $cell($r['to_seal']) . $cell($r['progress']) . $cell($r['stage']) . $cell($r['last_activity'])
                . $cell($r['stock']) . $cell($r['status']) . $cell($machines) . '</tr>';
        }
        $title = 'En producción ' . $code;
    } else {
        $html = '<table><tr><th>NV</th><th>Cliente</th><th>Vendedor</th><th>OC cliente</th><th>Fecha NV</th><th>Días</th><th>Entrega comprometida</th><th>Estado</th>'
            . '<th>Pedido</th><th>Despachado</th><th>Pendiente</th><th>Guías</th><th>Última guía</th><th>Stock asignado</th><th>Dónde</th><th>OT</th></tr>';
        foreach ($detail['rows'] as $r) {
            $html .= '<tr>' . $cell($r['nv']) . $cell($r['customer']) . $cell($r['seller']) . $cell($r['oc']) . $cell($r['created']) . $cell($r['age_days'])
                . $cell($r['due']) . $cell($r['status']) . $cell($r['ordered']) . $cell($r['shipped']) . $cell($r['pending']) . $cell($r['guides'])
                . $cell($r['last_guide']) . $cell($r['stock']) . $cell($r['stock_places']) . $cell($r['ots']) . '</tr>';
        }
        $title = 'Vendido sin despachar ' . $code;
    }
    SimpleXlsx::streamHtml(($type === 'production' ? 'en_produccion_' : 'nv_pendientes_') . preg_replace('/\W/', '', $code) . '_' . date('Ymd_Hi') . '.xlsx', $html . '</table>', $title);
}

function expectedStockStyles(): string
{
    return '<link rel="stylesheet" href="/assets/css/unibag-expected-stock.css?v=' . EXPECTED_STOCK_ASSET_VERSION . '">';
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
    $html = expectedStockStyles() . '<div class="es-shell"><div class="es-head"><div><div class="es-title">Stock esperado</div>'
        . '<div class="es-stamp" id="es_stamp"></div></div><div class="es-tabs">'
        . '<a class="es-tab' . ($tab === 'report' ? ' on' : '') . '" href="/reports/stock-esperado?tab=report">Informe</a>'
        . '<a class="es-tab' . ($tab === 'master' ? ' on' : '') . '" href="/reports/stock-esperado?tab=master">Maestro</a>'
        . '</div></div>' . expectedStockFlash();
    return $html . ($tab === 'master' ? expectedStockMaster($service) : expectedStockReport($service)) . '</div>';
}

function expectedStockReport(ExpectedStockService $service): string
{
    $report = $service->report();
    $json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    $groups = array_values(array_unique(array_filter(array_map(static fn($r) => trim((string)($r['group_label'] ?? '')), $report['rows']))));
    sort($groups);
    $groupOpts = '';
    foreach ($groups as $g) {
        $groupOpts .= '<option value="' . h($g) . '">' . h($g) . '</option>';
    }
    // Un solo panel: barra de filtros, pestañas de estado y la tabla, que se desplaza con la página (sin barra de desplazamiento propia).
    $html = '<div class="es-kpis" id="es_kpis"></div>'
        . '<section class="es-panel">'
        . '<div class="es-toolbar">'
        . '<div class="es-search"><i class="fas fa-search" aria-hidden="true"></i><input type="search" id="es_q" placeholder="Buscar código o descripción" aria-label="Buscar código o descripción" autocomplete="off"></div>'
        . '<select id="es_wh" aria-label="Bodega"><option value="">Todas las bodegas</option><option value="1000">Bodega 1000</option><option value="400">Bodega 400</option></select>'
        . ($groupOpts !== '' ? '<select id="es_grp" aria-label="Grupo"><option value="">Todos los grupos</option>' . $groupOpts . '</select>' : '')
        . '<span class="es-sep" aria-hidden="true"></span>'
        . '<label class="es-switch"><input type="checkbox" id="es_only_res"><span class="es-switch-ui" aria-hidden="true"></span>Con ventas sin despachar</label>'
        . '<label class="es-switch"><input type="checkbox" id="es_only_prod"><span class="es-switch-ui" aria-hidden="true"></span>En producción</label>'
        . '<div class="es-toolbar-end"><button type="button" class="es-btn ghost" id="es_reset"><i class="fas fa-undo" aria-hidden="true"></i> Limpiar</button>'
        . '<a class="es-btn sec" href="/reports/stock-esperado/excel"><i class="fas fa-file-excel" aria-hidden="true"></i> Excel</a></div>'
        . '</div>'
        . '<div class="es-statusbar"><div class="es-chips" id="es_chips" role="tablist" aria-label="Estado"></div><div class="es-count" id="es_count"></div></div>'
        . '<div id="es_err"></div>'
        . '<div class="es-wrap"><table class="es-table" id="es_table"><thead>'
        . '<tr class="es-groups"><th class="g-prod sticky">Producto</th><th class="g-stock gs">Bodega</th>'
        . '<th colspan="2" class="g-res gs">Ventas</th><th colspan="2" class="g-mfg gs">En producción</th><th colspan="2" class="g-proj gs">Proyección</th>'
        . '<th colspan="2" class="g-goal gs">Meta</th><th colspan="2" class="g-state gs">Estado</th></tr>'
        . '<tr class="es-cols">'
        . '<th data-sort="code" class="sticky">Código</th>'
        . '<th data-sort="stock" class="n gs" title="Unidades en la bodega del producto (pallets y cajas debajo)">Stock</th>'
        . '<th data-sort="reserved" class="n gs" title="Vendido en NV de venta de los últimos ' . ExpectedStockService::SALES_MAX_AGE_DAYS . ' días que aún no se despacha. Clic para ver las NV.">Sin despachar</th>'
        . '<th data-sort="free" class="n" title="Stock − sin despachar">Libre</th>'
        . '<th data-sort="to_print" class="n gs" title="CC en curso: pedido − ya impreso en flexografía o serigrafía. Clic para ver las CC y OT.">Por imprimir</th>'
        . '<th data-sort="to_seal" class="n" title="CC en curso: impreso (o pedido si no lleva impresión) − ya sellado o embalado. Clic para ver las CC y OT.">Por cortar y sellar</th>'
        . '<th data-sort="projected" class="n gs" title="Stock + por imprimir + por cortar y sellar − sin despachar">Proyectado</th>'
        . '<th data-sort="projected_diff" class="n" title="Proyectado − objetivo">vs objetivo</th>'
        . '<th data-sort="target_units" class="n gs">Objetivo</th><th data-sort="min_units" class="n">Mínimo</th>'
        . '<th data-sort="status_rank" class="gs">Hoy</th><th data-sort="projected_rank">Proyectado</th>'
        . '</tr></thead><tbody id="es_body"></tbody><tfoot id="es_foot"></tfoot></table></div></section>'
        . '<div class="es-modal" id="es_modal" hidden><div class="es-modal-box" role="dialog" aria-modal="true" aria-labelledby="es_modal_title">'
        . '<div class="es-modal-head"><div><div class="es-modal-kicker" id="es_modal_kicker"></div><div class="es-modal-title" id="es_modal_title"></div></div>'
        . '<button type="button" class="es-modal-x" id="es_modal_close" aria-label="Cerrar">&times;</button></div>'
        . '<div class="es-modal-body" id="es_modal_body"></div></div></div>';
    return $html . '<script>window.__ES=' . $json . ';</script>'
        . '<script src="/assets/js/unibag-expected-stock.js?v=' . EXPECTED_STOCK_ASSET_VERSION . '"></script>';
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
        . '<label style="font-size:12px;font-weight:700;color:var(--es-muted)">Pegar lista (una línea por producto: descripción y código separados por tabulación)</label>'
        . '<textarea name="list" rows="4" style="width:100%;margin:6px 0"></textarea><button class="es-btn sec" type="submit">Agregar lista</button></form></div>';
    $rows = $service->master(false);
    $html .= '<form method="post" action="/reports/stock-esperado/save" class="es-card">' . $csrf
        . '<div class="es-wrap"><table class="es-table es-master"><thead><tr><th>Código</th><th>Descripción</th><th>Grupo</th><th>Bodega</th>'
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
    $html .= '<div class="es-card"><b>Historial de cambios</b><div class="es-wrap"><table class="es-table es-master"><thead><tr><th>Fecha</th><th>Código</th><th>Campo</th><th>Antes</th><th>Después</th><th>Usuario</th></tr></thead><tbody>';
    foreach ($service->history(60) as $hrow) {
        $html .= '<tr><td>' . h(substr((string)$hrow['created_at'], 0, 16)) . '</td><td>' . h($hrow['item_code']) . '</td><td>' . h(ExpectedStockService::fieldLabel((string)$hrow['field']))
            . '</td><td>' . h($hrow['old_value'] ?? '') . '</td><td>' . h($hrow['new_value'] ?? '') . '</td><td>' . h($hrow['actor']) . '</td></tr>';
    }
    return $html . '</tbody></table></div></div>';
}
