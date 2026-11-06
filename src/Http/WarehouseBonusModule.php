<?php

declare(strict_types=1);

/**
 * Módulo HTTP · Bonificación de bodega
 *
 * Rutas:
 *  - /stock/bonificacion ................ Evaluación del mes (?tab=montos: operadores y montos).
 *  - /stock/bonificacion/save ........... Guarda evaluaciones (también el guardado automático).
 *  - /stock/bonificacion/operators-save . Selección de operadores.
 *  - /stock/bonificacion/amounts-save ... Montos por operador y KPI.
 *  - /stock/bonificacion/export-xls ..... Planilla y fichas de KPI.
 *
 * Solo la ven y modifican Javier, Héctor y Gabriel (jefe de bodega).
 */

require_once dirname(__DIR__) . '/WarehouseBonusService.php';

/** Usuario ERP GABRIEL (Gabriel Rodolfo Muñoz Messina), jefe de bodega. */
const WAREHOUSE_BONUS_WAREHOUSE_HEAD_ID = 4074;

function warehouseBonusCanAccess(): bool
{
    if (!unibagIsAuthenticated()) {
        return false;
    }
    if (unibagCanUserPerformModifications()) {
        return true;
    }
    $auth = (int)($_SESSION['auth_user_id'] ?? 0);
    $legacy = (int)($_SESSION['user_id'] ?? 0);
    if ($auth > 0 && $legacy > 0 && $auth !== $legacy) {
        return false;
    }
    return ($auth ?: $legacy) === WAREHOUSE_BONUS_WAREHOUSE_HEAD_ID;
}

function handleWarehouseBonusRoutes(string $path, string $method, string $name): bool
{
    if ($path !== '/stock/bonificacion' && !str_starts_with($path, '/stock/bonificacion/')) {
        return false;
    }
    $isAjax = ($_POST['ajax'] ?? '') === '1';
    if (!warehouseBonusCanAccess()) {
        http_response_code(403);
        if ($isAjax) {
            wbnJson(['ok' => false, 'error' => 'No tienes acceso a esta bonificación.']);
            return true;
        }
        render('Bonificación', '<div class="card"><b>Sin acceso</b><p>La bonificación de bodega solo está disponible para Javier, Héctor y Gabriel.</p></div>');
        return true;
    }
    $month = trim((string)($_POST['month'] ?? $_GET['month'] ?? ''));
    if (!WarehouseBonusService::validMonth($month)) {
        $month = WarehouseBonusService::defaultMonth();
    }
    $service = new WarehouseBonusService(Db::trzPdo(), Db::erpPdo());
    $actor = trim($name) !== '' ? trim($name) : 'Usuario';
    $tab = ($_POST['tab'] ?? $_GET['tab'] ?? '') === 'montos' ? 'montos' : 'evaluacion';
    $back = '/stock/bonificacion?' . http_build_query(['month' => $month] + ($tab === 'montos' ? ['tab' => 'montos'] : []));

    if ($path === '/stock/bonificacion' && $method === 'GET') {
        render('Bonificación de bodega', wbnPage($service, $month, $tab));
        return true;
    }
    if ($path === '/stock/bonificacion/export-xls' && $method === 'GET') {
        try {
            SimpleXlsx::streamHtmlSheets('bonificacion_bodega_' . $month . '.xlsx', wbnExportSheets($month, $service->rows($month)));
        } catch (Throwable $e) {
            error_log('Bonificación bodega, Excel: ' . $e->getMessage());
            redirectResponse($back . '&error=' . rawurlencode('No se pudo generar el Excel.'));
        }
        return true;
    }
    if ($method !== 'POST' || !in_array($path, ['/stock/bonificacion/save', '/stock/bonificacion/operators-save', '/stock/bonificacion/amounts-save'], true)) {
        http_response_code(404);
        render('Bonificación', '<div class="card">Página no encontrada.</div>');
        return true;
    }
    requireCsrf();
    try {
        if ($path === '/stock/bonificacion/save') {
            $rows = $_POST['ops'] ?? [];
            $totals = $service->saveEvaluations($month, is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [], $actor);
            if ($isAjax) {
                wbnJson(['ok' => true, 'totals' => $totals]);
                return true;
            }
        } elseif ($path === '/stock/bonificacion/operators-save') {
            $selected = $_POST['selected'] ?? [];
            $service->saveOperators($month, is_array($selected) ? $selected : [], (string)($_POST['manual_name'] ?? ''), $actor);
        } else {
            $amounts = [];
            $operators = is_array($_POST['operators'] ?? null) ? $_POST['operators'] : [];
            foreach ($operators as $index => $operator) {
                $amounts[(string)$operator] = is_array($_POST['amounts'][$index] ?? null) ? $_POST['amounts'][$index] : [];
            }
            $service->saveAmounts($month, $amounts, $actor);
        }
        redirectResponse($back . '&saved=1');
    } catch (Throwable $e) {
        error_log('Bonificación bodega: ' . $e->getMessage());
        $message = $e instanceof PDOException || !$e instanceof RuntimeException ? 'No se pudo guardar. Intenta de nuevo.' : $e->getMessage();
        if ($isAjax) {
            http_response_code(422);
            wbnJson(['ok' => false, 'error' => $message]);
            return true;
        }
        redirectResponse($back . '&error=' . rawurlencode($message));
    }
    return true;
}

function wbnJson(array $payload): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

function wbnMoney(float $value): string
{
    return '$ ' . number_format($value, 0, ',', '.');
}

function wbnNumber(float $value): string
{
    return str_replace('.', ',', (string)$value);
}

function wbnMonthLabel(string $month): string
{
    $names = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return ($names[(int)substr($month, 5, 2)] ?? $month) . ' ' . substr($month, 0, 4);
}

function wbnPage(WarehouseBonusService $service, string $month, string $tab): string
{
    $rows = $service->rows($month);
    $query = '?month=' . rawurlencode($month);
    $html = '<link rel="stylesheet" href="/assets/css/unibag-wh-bonus.css?v=20261105b">'
        . '<div class="wbn"><header class="wbn-head"><h1>Bonificación de bodega</h1><div class="wbn-actions">'
        . '<form method="get" action="/stock/bonificacion" class="wbn-month">' . ($tab === 'montos' ? '<input type="hidden" name="tab" value="montos">' : '')
        . '<label for="wbn-month">Mes</label><input id="wbn-month" type="month" name="month" required value="' . h($month) . '" onchange="this.form.submit()"></form>'
        . '<button type="button" class="wbn-btn sec" onclick="document.getElementById(\'wbn-history\').showModal()"><i class="fas fa-history" aria-hidden="true"></i>Historial</button>'
        . '<a id="wbn-export" class="wbn-btn sec" href="/stock/bonificacion/export-xls' . $query . '"><i class="fas fa-file-excel" aria-hidden="true"></i>Excel</a>'
        . '</div></header>';
    if (!empty($_GET['saved'])) {
        $html .= '<div class="wbn-alert ok" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i>Guardado</div>';
    }
    if (!empty($_GET['error'])) {
        $html .= '<div class="wbn-alert err" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i>' . h((string)$_GET['error']) . '</div>';
    }
    $html .= '<nav class="wbn-tabs" aria-label="Secciones">'
        . '<a href="/stock/bonificacion' . $query . '"' . ($tab === 'evaluacion' ? ' class="on" aria-current="page"' : '') . '>Evaluación del mes</a>'
        . '<a href="/stock/bonificacion' . $query . '&amp;tab=montos"' . ($tab === 'montos' ? ' class="on" aria-current="page"' : '') . '>Operadores y montos</a></nav>';
    $html .= $tab === 'montos' ? wbnAmountsTab($month, $rows) : wbnEvaluationTab($month, $rows);
    $html .= wbnOperatorsDialog($service, $month, $tab) . wbnHistoryDialog($service, $month);
    return $html . '</div><script src="/assets/js/unibag-wh-bonus.js?v=20261105"></script>';
}

function wbnSelectButton(): string
{
    return '<button type="button" class="wbn-btn" onclick="document.getElementById(\'wbn-operators\').showModal()"><i class="fas fa-user-check" aria-hidden="true"></i>Seleccionar operadores</button>';
}

function wbnEmptyState(string $month): string
{
    return '<div class="wbn-empty"><i class="fas fa-users" aria-hidden="true"></i><h2>Sin operadores en ' . h(wbnMonthLabel($month)) . '</h2>' . wbnSelectButton() . '</div>';
}

function wbnKpiSheet(array $kpi): string
{
    if ($kpi['objective'] === '') {
        return '';
    }
    $html = '<details class="wbn-sheet"><summary>Ficha</summary><dl>';
    foreach (['objective' => 'Objetivo', 'indicator' => 'Indicador', 'periodicity' => 'Periodicidad', 'failure' => '¿Cuándo no se cumple?', 'supervisor' => 'Supervisa'] as $field => $label) {
        $html .= '<dt>' . h($label) . '</dt><dd>' . h($kpi[$field]) . '</dd>';
    }
    return $html . '</dl></details>';
}

function wbnEvaluationTab(string $month, array $rows): string
{
    if (!$rows) {
        return wbnEmptyState($month);
    }
    $sumKpis = array_sum(array_column($rows, 'subtotal'));
    $sumAdditional = array_sum(array_column($rows, 'additional'));
    $pending = count(array_filter($rows, static fn(array $r): bool => !$r['has_evaluation']));
    $html = '<div class="wbn-stats">'
        . '<div class="wbn-stat"><span>Operadores</span><b>' . count($rows) . '</b></div>'
        . '<div class="wbn-stat"><span>Sin evaluar</span><b id="wbn-pending">' . $pending . '</b></div>'
        . '<div class="wbn-stat"><span>Bonos KPI</span><b id="wbn-sum-kpis">' . wbnMoney($sumKpis) . '</b></div>'
        . '<div class="wbn-stat"><span>Incentivos</span><b id="wbn-sum-additional">' . wbnMoney($sumAdditional) . '</b></div>'
        . '<div class="wbn-stat strong"><span>Total a pagar</span><b id="wbn-sum-total">' . wbnMoney($sumKpis + $sumAdditional) . '</b></div></div>'
        . '<form id="wbn-eval" method="post" action="/stock/bonificacion/save" data-month="' . h($month) . '">'
        . '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><input type="hidden" name="month" value="' . h($month) . '">';
    foreach ($rows as $i => $row) {
        $field = 'ops[' . $i . ']';
        $name = (string)$row['operator'];
        $html .= '<section class="wbn-op" data-op><input type="hidden" name="' . $field . '[operator]" value="' . h($name) . '">'
            . '<div class="wbn-op-head"><div><h2>' . h($name) . '</h2><span class="wbn-status" data-status data-state="' . ($row['has_evaluation'] ? 'saved' : 'none') . '" role="status">' . ($row['has_evaluation'] ? 'Guardado' : 'Sin evaluar') . '</span></div>'
            . '<div class="wbn-op-total"><span>Total a pagar</span><b data-total>' . wbnMoney((float)$row['total']) . '</b></div></div>'
            . '<div class="wbn-notes" role="group" aria-label="Notas de ' . h($name) . '"><span class="wbn-notes-title">Notas <small>(no cambian el pago)</small></span>';
        foreach (['proactividad' => 'Proactividad', 'eficiencia' => 'Eficiencia', 'multitarea' => 'Multitarea'] as $key => $label) {
            $score = (float)$row[$key];
            $html .= '<label>' . h($label) . '<input data-note type="text" inputmode="decimal" required pattern="[0-9]+([.,][0-9]{1,2})?" name="' . $field . '[' . $key . ']" value="' . h(wbnNumber($score)) . '">'
                . '<span class="wbn-chip" data-note-pct>' . (int)(BonusConfigurationService::helperScoreFactor($score) * 100) . '%</span></label>';
        }
        $html .= '</div>';
        if ($row['kpis']) {
            $html .= '<div class="wbn-table-wrap"><table class="wbn-table"><thead><tr><th>Bono</th><th class="r">Monto</th><th class="r">Cumplimiento</th><th class="r">A pagar</th><th>Observación</th></tr></thead><tbody>';
            foreach ($row['kpis'] as $code => $kpi) {
                $definition = WarehouseBonusService::KPIS[$code];
                $kpiField = $field . '[kpi][' . $code . ']';
                $html .= '<tr data-kpi data-amount="' . h((string)$kpi['amount']) . '"><td class="wbn-kpi"><b>' . h($definition['label']) . '</b>' . wbnKpiSheet($definition) . '</td>'
                    . '<td class="r">' . wbnMoney((float)$kpi['amount']) . '</td>'
                    . '<td class="r"><span class="wbn-pct"><input data-pct type="text" inputmode="decimal" required pattern="[0-9]+([.,][0-9]{1,2})?" aria-label="Cumplimiento ' . h($definition['label']) . ' de ' . h($name) . '" name="' . $kpiField . '[pct]" value="' . h(wbnNumber((float)$kpi['pct'])) . '"> %</span></td>'
                    . '<td class="r"><b data-paid>' . wbnMoney((float)$kpi['paid']) . '</b></td>'
                    . '<td><input class="wbn-wide" maxlength="255" aria-label="Observación ' . h($definition['label']) . ' de ' . h($name) . '" name="' . $kpiField . '[obs]" value="' . h($kpi['obs']) . '"></td></tr>';
            }
            $html .= '</tbody></table></div>';
        } else {
            $html .= '<p class="wbn-warn"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i>Sin montos. <a href="/stock/bonificacion?month=' . h($month) . '&amp;tab=montos">Asignar en Operadores y montos</a></p>';
        }
        $html .= '<div class="wbn-op-foot"><label>Incentivo adicional<span class="wbn-money">$ <input data-additional type="number" min="0" max="9999999999" step="1" name="' . $field . '[additional]" value="' . h((string)round((float)$row['additional'], 2)) . '"></span></label>'
            . '<label class="grow">Observaciones<input class="wbn-wide" maxlength="255" name="' . $field . '[observations]" value="' . h($row['observations']) . '"></label></div></section>';
    }
    return $html . '<div class="wbn-save"><button class="wbn-btn"><i class="fas fa-save" aria-hidden="true"></i>Guardar mes</button></div></form>';
}

function wbnAmountsTab(string $month, array $rows): string
{
    $html = '<div class="wbn-tools">' . wbnSelectButton() . '</div>';
    if (!$rows) {
        return wbnEmptyState($month);
    }
    $html .= '<form id="wbn-amounts" method="post" action="/stock/bonificacion/amounts-save"><input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="month" value="' . h($month) . '"><input type="hidden" name="tab" value="montos">'
        . '<div class="wbn-table-wrap framed"><table class="wbn-table wbn-matrix"><thead><tr><th class="wbn-sticky">Operador</th>';
    foreach (WarehouseBonusService::KPIS as $kpi) {
        $html .= '<th class="r">' . h($kpi['label']) . '</th>';
    }
    $html .= '<th class="r">Bono fijo</th></tr></thead><tbody>';
    foreach ($rows as $i => $row) {
        $name = (string)$row['operator'];
        $html .= '<tr data-amount-row><td class="wbn-sticky"><b>' . h($name) . '</b><input type="hidden" name="operators[' . $i . ']" value="' . h($name) . '">'
            . '<small>Desde ' . h(wbnMonthLabel((string)$row['effective_from'])) . '</small></td>';
        $sum = 0.0;
        foreach (WarehouseBonusService::KPIS as $code => $kpi) {
            $amount = $row['kpis'][$code]['amount'] ?? null;
            $sum += (float)$amount;
            $html .= '<td class="r"><input data-amount type="number" min="0" max="9999999999" step="1" placeholder="NA" aria-label="' . h($kpi['label']) . ' de ' . h($name) . '" name="amounts[' . $i . '][' . $code . ']" value="' . ($amount === null ? '' : h((string)(int)$amount)) . '"></td>';
        }
        $html .= '<td class="r"><b data-amount-total>' . wbnMoney($sum) . '</b></td></tr>';
    }
    return $html . '</tbody></table></div><div class="wbn-save"><span>Rige desde ' . h(wbnMonthLabel($month)) . '. Vacío = NA.</span><button class="wbn-btn"><i class="fas fa-save" aria-hidden="true"></i>Guardar montos</button></div></form>';
}

function wbnOperatorsDialog(WarehouseBonusService $service, string $month, string $tab): string
{
    $active = array_filter($service->settings($month), static fn(array $s): bool => $s['active']);
    $activeKeys = [];
    foreach (array_keys($active) as $name) {
        $activeKeys[WarehouseBonusService::nameKey((string)$name)] = true;
    }
    $names = $service->selectableNames($month);
    // Los seleccionados primero; dentro de cada grupo se mantiene el orden alfabético.
    usort($names, static fn(string $a, string $b): int => (int)isset($activeKeys[WarehouseBonusService::nameKey($b)]) <=> (int)isset($activeKeys[WarehouseBonusService::nameKey($a)]) ?: strnatcasecmp($a, $b));
    $html = '<dialog id="wbn-operators" class="wbn-dialog"><div class="wbn-dialog-head"><h2>Seleccionar operadores</h2>'
        . '<button type="button" class="wbn-btn sec sm" onclick="this.closest(\'dialog\').close()">Cerrar</button></div>'
        . '<form method="post" action="/stock/bonificacion/operators-save" class="wbn-dialog-body"><input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">'
        . '<input type="hidden" name="month" value="' . h($month) . '"><input type="hidden" name="tab" value="' . h($tab) . '">'
        . '<input id="wbn-operator-search" class="wbn-wide" type="search" placeholder="Buscar operador" aria-label="Buscar operador"><div class="wbn-operator-list">';
    foreach ($names as $name) {
        $html .= '<label data-search="' . h(WarehouseBonusService::nameKey($name)) . '"><input type="checkbox" name="selected[]" value="' . h($name) . '"'
            . (isset($activeKeys[WarehouseBonusService::nameKey($name)]) ? ' checked' : '') . '>' . h($name) . '</label>';
    }
    if (!$names) {
        $html .= '<p class="wbn-warn">No se pudo cargar el listado del ERP.</p>';
    }
    return $html . '</div><label class="wbn-manual">Agregar otro nombre<input class="wbn-wide" name="manual_name" maxlength="120"></label>'
        . '<div class="wbn-save"><span>Rige desde ' . h(wbnMonthLabel($month)) . '.</span><button class="wbn-btn">Guardar selección</button></div></form></dialog>';
}

function wbnHistoryDialog(WarehouseBonusService $service, string $month): string
{
    $titles = ['SELECCION' => 'Selección', 'MONTOS' => 'Montos', 'EVALUACION' => 'Evaluación'];
    $html = '<dialog id="wbn-history" class="wbn-dialog"><div class="wbn-dialog-head"><h2>Historial de ' . h(wbnMonthLabel($month)) . '</h2>'
        . '<button type="button" class="wbn-btn sec sm" onclick="this.closest(\'dialog\').close()">Cerrar</button></div><div class="wbn-dialog-body">';
    $entries = $service->history($month);
    foreach ($entries as $entry) {
        $html .= '<article class="wbn-history"><b>' . h(($titles[$entry['action']] ?? $entry['action']) . ' · ' . $entry['operator_name']) . '</b>'
            . '<small>' . h($entry['actor'] . ' · ' . date('d/m/Y H:i', (int)strtotime((string)$entry['created_at']))) . '</small>'
            . '<div class="wbn-table-wrap"><table class="wbn-table"><thead><tr><th>Dato</th><th>Antes</th><th>Después</th></tr></thead><tbody>';
        foreach (WarehouseBonusService::historyChanges($entry) as [$label, $before, $after]) {
            $html .= '<tr><td>' . h($label) . '</td><td>' . h($before) . '</td><td>' . h($after) . '</td></tr>';
        }
        $html .= '</tbody></table></div></article>';
    }
    if (!$entries) {
        $html .= '<p>Sin cambios en este mes.</p>';
    }
    return $html . '</div></dialog>';
}

/** Planilla con el formato del Excel de bodega y fichas de KPI; montos calculados en el servidor. */
function wbnExportSheets(string $month, array $rows): array
{
    $label = wbnMonthLabel($month);
    $header = '<html><head><meta charset="UTF-8"></head><body><div class="title">Bonificación de bodega</div>'
        . '<div class="meta">Periodo: ' . h($label) . ' | Generado: ' . h(date('d/m/Y H:i:s')) . ' | Montos en CLP</div>';
    $text = static fn(string $value): string => '<td class="text-cell">' . h($value) . '</td>';
    $currency = static fn(float $value): string => '<td class="num-currency">' . (string)round($value) . '</td>';
    $percent = static fn(float $value): string => '<td class="num-percent">' . (string)$value . '</td>';

    $summary = $header . '<table class="report"><thead><tr><th>Periodo</th><th>Operador</th><th>Nota proactividad</th><th>Nota eficiencia</th><th>Nota multitarea</th>'
        . '<th>% proactividad</th><th>% eficiencia</th><th>% multitarea</th>';
    foreach (WarehouseBonusService::KPIS as $kpi) {
        $summary .= '<th>Bono ' . h($kpi['label']) . '</th>';
    }
    $summary .= '<th>Bono fijo</th><th>Incentivo adicional</th><th>Total a pagar</th><th>Observaciones</th></tr></thead><tbody>';
    $sheet = $header . '<table class="report"><thead><tr><th>Colaborador</th><th>KPI</th><th>Objetivo</th><th>Indicador</th><th>Periodicidad</th><th>¿Cuándo no se cumple?</th>'
        . '<th>Supervisa</th><th>Observación</th><th>Valor indicador</th><th>Cumplimiento %</th><th>Bono a pagar</th><th>Total acumulado</th><th>Periodo</th></tr></thead><tbody>';
    $totals = ['subtotal' => 0.0, 'additional' => 0.0, 'total' => 0.0];
    foreach ($rows as $row) {
        $summary .= '<tr>' . $text($label) . $text((string)$row['operator']);
        foreach (['proactividad', 'eficiencia', 'multitarea'] as $key) {
            $summary .= '<td class="num-dec">' . (string)(float)$row[$key] . '</td>';
        }
        foreach (['proactividad', 'eficiencia', 'multitarea'] as $key) {
            $summary .= $percent(BonusConfigurationService::helperScoreFactor((float)$row[$key]) * 100);
        }
        $accumulated = 0.0;
        foreach (WarehouseBonusService::KPIS as $code => $definition) {
            $kpi = $row['kpis'][$code] ?? null;
            $summary .= $kpi === null ? $text('NA') : $currency((float)$kpi['paid']);
            if ($kpi !== null) {
                $accumulated += (float)$kpi['paid'];
                $sheet .= '<tr>' . $text((string)$row['operator']) . $text($definition['kpi']) . $text($definition['objective']) . $text($definition['indicator'])
                    . $text($definition['periodicity']) . $text($definition['failure']) . $text($definition['supervisor']) . $text((string)$kpi['obs'])
                    . $currency((float)$kpi['amount']) . $percent((float)$kpi['pct']) . $currency((float)$kpi['paid']) . $currency($accumulated) . $text($label) . '</tr>';
            }
        }
        $observation = (string)$row['observations'];
        if (!$row['has_evaluation']) {
            $observation = 'SIN EVALUAR' . ($observation !== '' ? '. ' . $observation : '');
        }
        $summary .= $currency((float)$row['subtotal']) . $currency((float)$row['additional']) . $currency((float)$row['total']) . $text($observation) . '</tr>';
        foreach ($totals as $key => $value) {
            $totals[$key] = $value + (float)$row[$key];
        }
    }
    if (!$rows) {
        $summary .= '<tr><td class="text-cell" colspan="21">Sin operadores en este mes.</td></tr>';
        $sheet .= '<tr><td class="text-cell" colspan="13">Sin operadores en este mes.</td></tr>';
    }
    $summary .= '<tr><td class="text-cell" colspan="17">TOTAL GENERAL</td>' . $currency($totals['subtotal']) . $currency($totals['additional']) . $currency($totals['total']) . $text(count($rows) . ' operadores') . '</tr>';
    return [
        ['title' => 'Resumen', 'html' => $summary . '</tbody></table></body></html>'],
        ['title' => 'Fichas KPI', 'html' => $sheet . '</tbody></table></body></html>'],
    ];
}
