<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Módulo HTTP · Dashboard, KPIs y Gráficos ERP
 * (src/Http/Reports/DashboardAndGraphicsModule.php)
 * =============================================================================
 */

require_once dirname(__DIR__, 2) . '/ChileBusinessCalendar.php';

/**
 * Caché corto de los cálculos del panel (cada uno tarda 0,3 a 1 s porque recorre los eventos del ERP).
 * Período que incluye hoy: 2 minutos. Período ya terminado: 6 horas (sus cifras ya no cambian).
 */
function unibagDashboardCached(string $name, string $start, string $end, callable $compute): mixed
{
    $endTs = strtotime($end);
    $key = 'dash-' . $name . '-' . $start . '-' . $end;
    if ($endTs !== false && $endTs < time() - 3600) {
        return ResultCache::remember($key, 21600, $compute);
    }
    // Período en curso: si lo guardado tiene menos de 30 min, se muestra y se actualiza después de enviar la página.
    return ResultCache::rememberStale($key, 120, 1800, $compute);
}

/**
 * Proyección de cierre del período (26 al 25) con las unidades de Corte y Sellado.
 *
 * Fórmula: unidades acumuladas / días hábiles cumplidos × días hábiles totales del período.
 * Un día hábil es lunes a viernes que no sea feriado nacional. Solo cuentan los días ya terminados
 * (antes de hoy), para no castigar la proyección con la producción parcial del día en curso.
 */
function unibagBuildClosingProjection(ReceptionService $service, DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd): array
{
    $zone = new DateTimeZone('America/Santiago');
    $start = new DateTimeImmutable($periodStart->format('Y-m-d'), $zone);
    $endExclusive = (new DateTimeImmutable($periodEnd->format('Y-m-d'), $zone))->modify('+1 day');
    $today = new DateTimeImmutable('today', $zone);
    $todayKey = $today->format('Y-m-d');

    $note = '';
    try {
        $calendar = (new ChileBusinessCalendar())->forPeriod(['start' => $start->getTimestamp(), 'end' => $endExclusive->getTimestamp()]);
        $dates = $calendar['dates'];
    } catch (Throwable) {
        $dates = [];
        for ($day = $start; $day < $endExclusive; $day = $day->modify('+1 day')) {
            if ((int)$day->format('N') <= 5) {
                $dates[] = $day->format('Y-m-d');
            }
        }
        $note = 'Sin feriados (no se pudo consultar el calendario)';
    }

    $total = count($dates);
    $elapsed = 0;
    foreach ($dates as $date) {
        if ($date < $todayKey) {
            $elapsed++;
        }
    }
    $lastDay = $today->modify('-1 day');
    $periodEndDay = $endExclusive->modify('-1 day');
    if ($lastDay > $periodEndDay) {
        $lastDay = $periodEndDay;
    }

    $units = 0.0;
    if ($elapsed > 0 && $lastDay >= $start) {
        $fromStr = $start->format('Y-m-d') . ' 00:00:00';
        $toStr = $lastDay->format('Y-m-d') . ' 23:59:59';
        $units = (float)unibagDashboardCached('cys-units', $fromStr, $toStr, static fn() => $service->getCorteSelladoProducedUnits($fromStr, $toStr));
        if ($units <= 0) {
            // Con días hábiles ya cumplidos, 0 unidades es casi siempre una consulta fallida que quedó guardada
            // (el 12-11 dejó la proyección en 0 por 6 horas): se vuelve a consultar y se reemplaza lo guardado.
            $units = (float)$service->getCorteSelladoProducedUnits($fromStr, $toStr);
            if ($units > 0) {
                ResultCache::put('dash-cys-units-' . $fromStr . '-' . $toStr, $units);
            }
        }
    }
    $closed = $elapsed >= $total && $total > 0;
    $daily = $elapsed > 0 ? $units / $elapsed : 0.0;

    return [
        'total_days' => $total,
        'elapsed_days' => $elapsed,
        'remaining_days' => max(0, $total - $elapsed),
        'units' => $units,
        'daily_avg' => $daily,
        'projected' => $elapsed > 0 ? $daily * $total : null,
        'closed' => $closed,
        'note' => $note,
        'cutoff' => $lastDay >= $start ? $lastDay->format('d/m/Y') : '',
    ];
}

/**
 * Renderiza el dashboard “ERP” según el área actual.
 *
 * - Si el área es PRODUCTION: redirige al dashboard de turnos.
 * - Si el área es RECEPTION: redirige a órdenes de compra activas.
 * - Si el área es ERP (u otra): muestra el dashboard de producción.
 *
 * ---
 *
 * Renders the “ERP” dashboard depending on the current area.
 *
 * - If area is PRODUCTION: redirects to the shifts dashboard.
 * - If area is RECEPTION: redirects to active purchase orders.
 * - If area is ERP (or other): renders the production dashboard.
 */
function unibagRenderErpDashboardPage(ReceptionService $service): void
{
    $currentArea = normalizeErpArea((string)($_SESSION['erp_area'] ?? 'ERP'));
    if ($currentArea === 'PRODUCTION' && empty($_GET['view'])) {
        redirectResponse('/production/machines');
        return;
    }
    if ($currentArea === 'RECEPTION') {
        redirectResponse(erpAreaDefinitions()['RECEPTION']['home'] ?? '/purchase-orders?status=active&supplier_type=NATIONAL');
        return;
    }
    if ($currentArea === 'SCALE') {
        redirectResponse(erpAreaDefinitions()['SCALE']['home'] ?? '/scale/sealing-waste');
        return;
    }
    unibagRenderErpOnlyProductionDashboardPage($service, true);
}

function unibagRenderErpOnlyProductionDashboardPage(ReceptionService $service, bool $embeddedInErp = false): void
{
    $perms = sessionAreaPermissions();
    if (!userCanAccessArea('ERP', $perms) && !userCanAccessArea('RECEPTION', $perms) && !userCanAccessArea('PRODUCTION', $perms)) {
        redirectResponse(firstAllowedAreaHome($perms));
    }

    $filters = unibagResolveProductionDashboardFilters();
    /** @var DateTimeImmutable $start */
    $start = $filters['start'];
    /** @var DateTimeImmutable $end */
    $end = $filters['end'];
    $defaultFilterType = (string)$filters['filter_type'];
    $periodYm = (string)$filters['period'];
    $rangeStartInput = (string)$filters['start_date'];
    $rangeEndInput = (string)$filters['end_date'];
    $activeFilterLabel = (string)$filters['active_filter_label'];

    $fmtInt = static function (float $value): string {
        return number_format($value, 0, ',', '.');
    };
    $fmtUnits = static function ($value): string {
        if ($value === null || !is_numeric($value)) {
            return 'N/D';
        }
        return number_format((float)$value, 0, ',', '.');
    };
    $linkWithParams = static function (string $basePath, array $params): string {
        $query = http_build_query($params);
        return $query !== '' ? ($basePath . '?' . $query) : $basePath;
    };

    $kpiFrom = $start->format('Y-m-d H:i:s');
    $kpiTo = $end->format('Y-m-d H:i:s');
    $kpis = (array)unibagDashboardCached('kpis', $kpiFrom, $kpiTo, static fn() => $service->getErpOnlyProductionDashboardKpis($kpiFrom, $kpiTo));
    $producedUnits = (float)($kpis['produced_units'] ?? 0.0);
    $pendingUnits = (float)($kpis['pending_units'] ?? 0.0);
    $waste = is_array($kpis['waste'] ?? null) ? $kpis['waste'] : [];

    $wastePercent = array_key_exists('percent', $waste) ? $waste['percent'] : null;
    $wastePercentLabel = 'N/D';
    if (is_numeric($wastePercent)) {
        $wastePercentLabel = number_format((float)$wastePercent, 2, ',', '.') . '%';
    }

    $wasteUnits = array_key_exists('waste_units', $waste) ? $waste['waste_units'] : null;
    $wasteBaseUnits = array_key_exists('base_units', $waste) ? $waste['base_units'] : null;
    $wasteDeclaredUnits = array_key_exists('declared_waste_units', $waste) ? $waste['declared_waste_units'] : null;
    $wasteDeclaredBase = array_key_exists('declared_base_units', $waste) ? $waste['declared_base_units'] : null;

    $wasteCardValue = $wastePercentLabel;
    $wasteSubLabel = 'Sin datos';
    if (is_numeric($wasteDeclaredUnits) && is_numeric($wasteDeclaredBase) && (float)$wasteDeclaredBase > 0) {
        $wasteSubLabel = $fmtInt((float)$wasteDeclaredUnits) . ' / ' . $fmtInt((float)$wasteDeclaredBase) . ' unid (prod)';
    } elseif (is_numeric($wasteUnits) && is_numeric($wasteBaseUnits) && (float)$wasteBaseUnits > 0) {
        $wasteSubLabel = $fmtInt((float)$wasteUnits) . ' / ' . $fmtInt((float)$wasteBaseUnits) . ' unid (prod)';
    }
    $selectedKpi = strtolower(trim((string)($_GET['kpi'] ?? '')));
    if (!in_array($selectedKpi, ['waste'], true)) {
        $selectedKpi = '';
    }

    $userId = (int)($_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? 0);
    $home = (array)ResultCache::remember('dash-home-' . $userId, 600, static fn() => $service->getLegacyErpHomeDashboard($userId));
    $dashboardUrl = trim((string)($home['dashboard_url'] ?? ''));

    $body = '<div class="dashboard-shell">';
    
    // Header y Filtros Unificados tipo Gráficos
    $formAction = $embeddedInErp ? '/' : '/reports/production-dashboard';
    $body .= '<div class="erp-filter-card">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">📊 PANEL DE CONTROL Y PRODUCCIÓN ERP</div>';
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . '</div>';
    $body .= '</div>';

    $body .= '<form id="erp-dashboard-filter-form" method="get" action="' . h($formAction) . '" class="erp-filter-form">';
    $body .= '<div class="erp-filter-field">';
    $body .= '<label for="filter_type_dash">Tipo de filtro</label>';
    $body .= '<select id="filter_type_dash" name="filter_type">';
    $body .= '<option value="period"' . ($defaultFilterType === 'period' ? ' selected' : '') . '>Período (26 al 25)</option>';
    $body .= '<option value="range"' . ($defaultFilterType === 'range' ? ' selected' : '') . '>Rango personalizado</option>';
    $body .= '</select>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'period' ? '' : ' is-hidden') . '" data-filter-group="period">';
    $body .= '<label for="period_dash">Mes del período</label>';
    $body .= '<input id="period_dash" type="month" name="period" value="' . h($periodYm) . '"' . ($defaultFilterType === 'period' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="start_date_dash">Fecha inicio</label>';
    $body .= '<input id="start_date_dash" type="date" name="start_date" value="' . h($rangeStartInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="end_date_dash">Fecha término</label>';
    $body .= '<input id="end_date_dash" type="date" name="end_date" value="' . h($rangeEndInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<button type="submit" class="btn-filter-apply">🔍 Aplicar Filtros</button>';
    $body .= '</form>';
    $body .= '</div>';


    $basePath = $embeddedInErp ? '/' : '/reports/production-dashboard';
    $baseParams = [
        'filter_type' => $defaultFilterType,
        'period' => $periodYm,
        'start_date' => $rangeStartInput,
        'end_date' => $rangeEndInput,
    ];

    // Tarjetas KPI Principales
    $body .= '<div class="dashboard-kpis-grid">';
    
    // KPI 1: Producidas (Corte y Sellado)
    $body .= '<div class="kpi-card-premium cyan">';
    $body .= '<div class="kpi-header-row"><span class="kpi-tag">Corte y Sellado</span><span style="font-size:18px;">✂️</span></div>';
    $body .= '<div style="display:flex; flex-direction:column; align-items:center; text-align:center; margin:6px 0;">';
    $body .= '<div class="kpi-num">' . h($fmtInt($producedUnits)) . '</div>';
    $body .= '</div>';
    $body .= '<div class="kpi-footer-hint" style="justify-content:center; color:#0d9488;">Total acumulado del período</div>';
    $body .= '</div>';

    // KPI 2: Pendientes
    $body .= '<div class="kpi-card-premium blue">';
    $body .= '<div class="kpi-header-row"><span class="kpi-tag">Plan Abierto</span><span style="font-size:18px;">⏳</span></div>';
    $body .= '<div style="display:flex; flex-direction:column; align-items:center; text-align:center; margin:6px 0;">';
    $body .= '<div class="kpi-num">' . h($fmtInt($pendingUnits)) . '</div>';
    $body .= '</div>';
    $body .= '<div class="kpi-footer-hint" style="justify-content:center; color:#1d4ed8;">Órdenes en curso de producción</div>';
    $body .= '</div>';

    // KPI 3: Merma
    $wasteHref = $linkWithParams($basePath, array_merge($baseParams, ['kpi' => 'waste']));
    $body .= '<a class="kpi-card-premium amber' . ($selectedKpi === 'waste' ? ' active' : '') . '" href="' . h($wasteHref) . '">';
    $body .= '<div class="kpi-header-row"><span class="kpi-tag">Control de Calidad</span><span style="font-size:18px;">⚠️</span></div>';
    $body .= '<div style="display:flex; flex-direction:column; align-items:center; text-align:center; margin:6px 0;">';
    $body .= '<div class="kpi-num">' . h($wasteCardValue) . '</div>';
    $body .= '<div class="kpi-desc">' . h($wasteSubLabel) . '</div>';
    $body .= '</div>';
    $body .= '<div class="kpi-footer-hint" style="justify-content:center; color:#b45309;">Ver desglose detallado →</div>';
    $body .= '</a>';

    // KPI 4: Proyección de cierre del período (según días hábiles transcurridos)
    $projection = unibagBuildClosingProjection($service, $filters['period_start'], $filters['period_end']);
    $projValue = $projection['projected'] === null ? 'N/D' : $fmtInt((float)$projection['projected']);
    $projPct = $projection['total_days'] > 0 ? (int)round($projection['elapsed_days'] / $projection['total_days'] * 100) : 0;
    $body .= '<div class="kpi-card-premium green">';
    $body .= '<div class="kpi-header-row"><span class="kpi-tag">' . ($projection['closed'] ? 'Cierre del período' : 'Proyección de cierre') . '</span><span style="font-size:18px;">📈</span></div>';
    $body .= '<div style="display:flex; flex-direction:column; align-items:center; text-align:center; margin:6px 0;">';
    $body .= '<div class="kpi-num">' . h($projValue) . '</div>';
    if ($projection['projected'] === null) {
        $body .= '<div class="kpi-desc">Aún no hay días hábiles completos en el período</div>';
    } else {
        $body .= '<div class="kpi-desc">' . ($projection['closed'] ? 'Unidades producidas (Corte y Sellado)' : 'Unidades estimadas al cierre (Corte y Sellado)') . '</div>';
    }
    $body .= '</div>';
    $body .= '<div style="height:6px; border-radius:99px; background:#e2e8f0; overflow:hidden; margin-top:4px;"><div style="height:100%; width:' . $projPct . '%; background:#059669;"></div></div>';
    $body .= '<div class="kpi-footer-hint" style="justify-content:center; color:#047857; text-align:center; flex-wrap:wrap;">'
        . h((string)$projection['elapsed_days']) . ' de ' . h((string)$projection['total_days']) . ' días hábiles'
        . ($projection['elapsed_days'] > 0 ? ' · ' . h($fmtInt((float)$projection['daily_avg'])) . ' unid./día' : '')
        . '</div>';
    if ($projection['elapsed_days'] > 0 && !$projection['closed']) {
        $body .= '<div class="kpi-desc" style="font-size:11px;">' . h($fmtInt((float)$projection['units'])) . ' unid. al ' . h($projection['cutoff']) . ' · faltan ' . h((string)$projection['remaining_days']) . ' días hábiles</div>';
    }
    if ($projection['note'] !== '') {
        $body .= '<div class="kpi-desc" style="font-size:11px; color:#b45309;">' . h($projection['note']) . '</div>';
    }
    $body .= '</div>';

    $body .= '</div>';

    // Alerta de Horario de Colación (Panel Faltas por día límite 14:00)
    $evalDate = date('Y-m-d');
    if ($defaultFilterType === 'range' && !empty($rangeStartInput) && $rangeStartInput === $rangeEndInput) {
        $evalDate = $rangeStartInput;
    }
    $lunchReport = $service->getOperatorLunchReport($evalDate);
    $missingLunchCount = $lunchReport['missing_count'];
    $isPastLunchDeadline = $lunchReport['is_past_deadline'];

    if ($missingLunchCount > 0 && $isPastLunchDeadline) {
        $body .= '<div class="alert-card-premium" style="border-color:#fdba74; background:linear-gradient(180deg, #fffbf5 0%, #ffffff 100%); margin-bottom:24px; box-shadow:0 10px 25px -5px rgba(234,88,12,0.08);">';
        $body .= '<div class="alert-header">';
        $body .= '<div style="display:flex; align-items:center; gap:12px;">';
        $body .= '<div style="width:42px; height:42px; border-radius:50%; background:#ffedd5; color:#c2410c; display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:800; flex-shrink:0;">🍱</div>';
        $body .= '<div>';
        $body .= '<div style="font-size:16px; font-weight:800; color:#9a3412;">Alerta de Colaciones: ' . $missingLunchCount . ' Maquinistas sin Registro de Colación (Límite 14:00)</div>';
        $body .= '<div style="font-size:12.5px; color:#64748b; margin-top:2px;">Jornada del día ' . date('d/m/Y', strtotime($evalDate)) . ': Maquinistas con turno iniciado en máquina que pasadas las 14:00 hrs no registran horario de almuerzo (excluye embalaje, ayudantes y puestos sin máquina).</div>';
        $body .= '</div>';
        $body .= '</div>';
        $body .= '<div style="display:flex; align-items:center; gap:10px;">';
        $manageLunchUrl = '/reports/colaciones?date=' . urlencode($evalDate);
        $body .= '<a class="btn secondary" href="' . h($manageLunchUrl) . '" style="background:#ea580c; border-color:#c2410c; color:#fff; font-weight:700; font-size:12px; display:inline-flex; align-items:center; gap:6px; box-shadow:0 2px 4px rgba(234,88,12,0.2);">⚡ Gestionar Colaciones (' . $missingLunchCount . ' pendientes) →</a>';
        $body .= '<span class="alert-badge" style="background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5;">Horario Límite: 14:00 hrs</span>';
        $body .= '</div>';
        $body .= '</div>';

        $body .= '<div style="max-height:280px; overflow-y:auto; border:1px solid #fed7aa; border-radius:12px; background:#fff; box-shadow:inset 0 1px 2px rgba(0,0,0,.02);">';
        $body .= '<table class="table-premium">';
        $body .= '<thead><tr>';
        $body .= '<th>Operador</th>';
        $body .= '<th>Máquina / Equipo</th>';
        $body .= '<th class="text-center">Inicio Turno</th>';
        $body .= '<th class="text-center">Fin Turno</th>';
        $body .= '<th class="text-center">Estado Colación</th>';
        $body .= '<th class="text-center">Acción</th>';
        $body .= '</tr></thead><tbody>';

        foreach ($lunchReport['missing_operators'] as $op) {
            $assignUrl = '/reports/colaciones?date=' . urlencode($evalDate) . '&edit_op=' . rawurlencode((string)$op['operator_name']);
            $body .= '<tr>';
            $body .= '<td style="font-weight:700; color:#1e293b;"><div style="display:flex; align-items:center; gap:8px;"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#ef4444;"></span>' . h($op['operator_name']) . '</div></td>';
            $body .= '<td style="font-weight:600; color:#475569;"><span style="display:inline-block; padding:2px 8px; background:#f1f5f9; border-radius:6px; font-size:12px;">' . h($op['machine_name']) . '</span></td>';
            $body .= '<td class="text-center" style="font-family:monospace; font-weight:700; color:#0f172a;">' . h($op['shift_start']) . '</td>';
            $body .= '<td class="text-center" style="font-size:12px; color:#64748b;">' . h($op['shift_end']) . '</td>';
            $body .= '<td class="text-center"><span style="display:inline-block; padding:3px 10px; border-radius:999px; font-weight:800; font-size:11.5px; background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5;">⚠️ Sin Registro (>14:00)</span></td>';
            $body .= '<td class="text-center"><a class="btn secondary" style="padding:4px 10px; font-size:11.5px; font-weight:700; background:#fff7ed; border-color:#fdba74; color:#c2410c;" href="' . h($assignUrl) . '">Asignar Horario</a></td>';
            $body .= '</tr>';
        }

        $body .= '</tbody></table></div>';
        $body .= '</div>';
    }

    // Alerta de Producciones con Merma Crítica (> 5%)
    $cwFrom = $start->format('Y-m-d H:i:s');
    $cwTo = $end->format('Y-m-d H:i:s');
    $criticalWasteOrders = (array)unibagDashboardCached('critical-waste', $cwFrom, $cwTo, static fn() => $service->getCriticalWasteWorkOrders($cwFrom, $cwTo, 5.0));
    $criticalCount = count($criticalWasteOrders);

    if ($criticalCount > 0) {
        $body .= '<div class="alert-card-premium">';

        $body .= '<div class="alert-header">';
        $body .= '<div style="display:flex; align-items:center; gap:12px;">';
        $body .= '<div style="width:42px; height:42px; border-radius:50%; background:#fee2e2; color:#dc2626; display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:800; flex-shrink:0;">⚠️</div>';
        $body .= '<div>';
        $body .= '<div style="font-size:16px; font-weight:800; color:#991b1b;">Alerta de Calidad: ' . $criticalCount . ' Órdenes de Trabajo con Merma Superior al 5%</div>';
        $body .= '</div>';
        $body .= '</div>';
        $body .= '<div style="display:flex; align-items:center; gap:10px;">';
        $exportCriticalUrl = '/reports/critical-waste/excel?' . http_build_query($baseParams);
        $body .= '<a class="btn secondary" href="' . h($exportCriticalUrl) . '" style="background:#fff; border-color:#fca5a5; color:#991b1b; font-weight:700; font-size:12px; display:inline-flex; align-items:center; gap:6px;">📥 Exportar Excel (' . $criticalCount . ')</a>';
        $body .= '<span class="alert-badge">Umbral Crítico > 5.0%</span>';
        $body .= '</div>';
        $body .= '</div>';

        $body .= '<div style="max-height:300px; overflow-y:auto; border:1px solid #fed7aa; border-radius:12px; background:#fff; box-shadow:inset 0 1px 2px rgba(0,0,0,.02);">';
        $body .= '<table class="table-premium">';
        $body .= '<thead><tr>';
        $body .= '<th class="text-center">OT</th>';
        $body .= '<th class="text-center">Centro Costo</th>';
        $body .= '<th>Cliente</th>';
        $body .= '<th>Proceso</th>';
        $body .= '<th>Máquina</th>';
        $body .= '<th class="text-right">U. Buenas</th>';
        $body .= '<th class="text-right">U. Merma</th>';
        $body .= '<th class="text-right">Total Prod.</th>';
        $body .= '<th class="text-center">% Merma</th>';
        $body .= '<th class="text-center">Acción</th>';
        $body .= '</tr></thead><tbody>';

        foreach ($criticalWasteOrders as $ot) {
            $wRate = (float)$ot['waste_rate'];
            $rateBadgeStyle = $wRate >= 10.0 
                ? 'background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5;' 
                : 'background:#ffedd5; color:#c2410c; border:1px solid #fdba74;';
            $otSearchUrl = '/reports/machine-production?' . http_build_query([
                'filter_type' => $defaultFilterType,
                'period' => $periodYm,
                'start_date' => $rangeStartInput,
                'end_date' => $rangeEndInput,
                'q' => $ot['ot_number'],
            ]);

            $body .= '<tr>';
            $body .= '<td class="text-center" style="font-weight:800; font-family:monospace; color:#0f172a;">' . h($ot['ot_number']) . '</td>';
            $body .= '<td class="text-center" style="font-size:11.5px; font-weight:700; color:#475569; font-family:monospace;">' . h($ot['cost_center']) . '</td>';
            $body .= '<td style="font-weight:700; color:#1e293b;">' . h($ot['customer_name']) . '</td>';
            $body .= '<td style="font-size:11.5px; color:#64748b;">' . h($ot['process_name']) . '</td>';
            $body .= '<td style="font-weight:600;">' . h($ot['machine_name']) . '</td>';
            $body .= '<td class="text-right">' . number_format($ot['good_units'], 0, ',', '.') . '</td>';
            $body .= '<td class="text-right" style="font-weight:800; color:#dc2626;">' . number_format($ot['waste_units'], 0, ',', '.') . '</td>';
            $body .= '<td class="text-right" style="font-weight:600;">' . number_format($ot['total_units'], 0, ',', '.') . '</td>';
            $body .= '<td class="text-center"><span style="display:inline-block; padding:3px 10px; border-radius:999px; font-weight:800; font-size:11.5px; ' . $rateBadgeStyle . '">' . number_format($wRate, 2, ',', '.') . '%</span></td>';
            $body .= '<td class="text-center"><a class="btn secondary" style="padding:4px 10px; font-size:11.5px; font-weight:700;" href="' . h($otSearchUrl) . '" title="Ver eventos de esta OT">Ver Detalle</a></td>';
            $body .= '</tr>';
        }

        $body .= '</tbody></table></div>';
        $body .= '</div>';
    }

    if ($selectedKpi === 'waste') {
        $wasteCloseHref = $linkWithParams($basePath, $baseParams);
        $wasteExcelHref = '/reports/dashboard-waste-detail/excel?' . http_build_query($baseParams);
        $wasteUnitsTotal = array_key_exists('waste_units', $waste) && is_numeric($waste['waste_units']) ? (float)$waste['waste_units'] : null;
        $wasteKgTotal = array_key_exists('waste_kg', $waste) && is_numeric($waste['waste_kg']) ? (float)$waste['waste_kg'] : null;
        $wasteBaseTotal = array_key_exists('base_units', $waste) && is_numeric($waste['base_units']) ? (float)$waste['base_units'] : null;
        $wasteRequestedTotal = array_key_exists('requested_units', $waste) && is_numeric($waste['requested_units']) ? (float)$waste['requested_units'] : null;

        $wdFrom = $start->format('Y-m-d H:i:s');
        $wdTo = $end->format('Y-m-d H:i:s');
        $details = (array)unibagDashboardCached('waste-details', $wdFrom, $wdTo, static fn() => $service->getErpWasteDashboardDetails($wdFrom, $wdTo));
        $byMachine = is_array($details['by_machine'] ?? null) ? $details['by_machine'] : [];
        $byType = is_array($details['by_type'] ?? null) ? $details['by_type'] : [];
        $top10 = is_array($details['top10'] ?? null) ? $details['top10'] : [];

        $machineTotal = 0.0;
        foreach ($byMachine as $r) {
            if (is_array($r)) {
                $machineTotal += (float)($r['waste_units'] ?? 0.0);
            }
        }
        $machineShareTotal = is_numeric($wasteUnitsTotal) && (float)$wasteUnitsTotal > 0 ? (float)$wasteUnitsTotal : $machineTotal;
        $typeTotal = 0.0;
        foreach ($byType as $r) {
            if (is_array($r)) {
                $typeTotal += (float)($r['waste_units'] ?? 0.0);
            }
        }

        $body .= '<div class="modal-backdrop open" id="waste-modal" data-close-href="' . h($wasteCloseHref) . '">';
        $body .= '<div class="modal-card" role="dialog" aria-modal="true" aria-label="Desglose de merma">';
        $body .= '<div class="modal-head">';
        $body .= '<div class="modal-title">Merma — Detalle Operativo Real</div>';
        $body .= '<div style="display:flex; align-items:center; gap:8px;">';
        $body .= '<a class="btn secondary" href="' . h($wasteExcelHref) . '" style="font-weight:700; font-size:12.5px;">📥 Exportar Excel</a>';
        $body .= '<a class="btn secondary" href="' . h($wasteCloseHref) . '">Cerrar</a>';
        $body .= '</div>';
        $body .= '</div>';
        $body .= '<div class="modal-body">';

        $body .= '<div class="dashboard-subtitle" style="margin-bottom:10px">Resumen</div>';
        $body .= '<div class="ot-meta-grid">';
        $body .= '<div class="ot-meta"><div class="muted">Producción total (unid)</div><div class="value">' . h($fmtUnits($wasteBaseTotal)) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Merma total (unid)</div><div class="value">' . h($fmtUnits($wasteUnitsTotal)) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Merma total (kg)</div><div class="value">' . h($wasteKgTotal !== null ? (number_format($wasteKgTotal, 2, ',', '.') . ' kg') : 'N/D') . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">% Merma (sobre producción)</div><div class="value">' . h($wastePercentLabel) . '</div></div>';
        $body .= '<div class="ot-meta"><div class="muted">Plan solicitado (unid)</div><div class="value">' . h($fmtUnits($wasteRequestedTotal)) . '</div></div>';
        $body .= '</div>';



        // 1. Informe de Merma por Tipo de Producción
        $byProductionType = is_array($details['by_production_type'] ?? null) ? $details['by_production_type'] : [];
        $body .= '<div class="dashboard-subtitle" style="margin-top:16px; display:flex; align-items:center; gap:8px;"><span>🏭</span> Informe de Merma por Tipo de Producción</div>';

        if ($byProductionType === []) {
            $body .= '<div class="erp-prod-empty">Sin datos de procesos para el período.</div>';
        } else {
            // Mini Cards Grid
            $body .= '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:12px; margin-bottom:14px;">';
            foreach ($byProductionType as $pt) {
                $ptTitle = (string)$pt['title'];
                $ptIcon = (string)($pt['icon'] ?? '⚙️');
                $ptUnits = (float)($pt['waste_units'] ?? 0.0);
                $ptKg = (float)($pt['waste_kg'] ?? 0.0);
                $ptProd = (float)($pt['produced_units'] ?? 0.0);
                $ptRate = $pt['waste_percent'] !== null ? (float)$pt['waste_percent'] : null;
                $ptShare = (float)($pt['share_percent'] ?? 0.0);
                $ptRateBadge = $ptRate !== null 
                    ? ($ptRate > 5.0 ? 'background:#fee2e2;color:#b91c1c;' : ($ptRate > 2.0 ? 'background:#fef3c7;color:#b45309;' : 'background:#ecfdf5;color:#047857;'))
                    : 'background:#f1f5f9;color:#64748b;';

                $body .= '<div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; box-shadow:0 2px 8px rgba(0,0,0,.03); display:flex; flex-direction:column; justify-content:space-between; gap:10px;">';
                $body .= '<div style="display:flex; justify-content:space-between; align-items:center;">';
                $body .= '<div style="font-weight:800; font-size:13px; color:#0f172a; display:flex; align-items:center; gap:6px;"><span>' . $ptIcon . '</span> ' . h($ptTitle) . '</div>';
                $body .= '<span style="font-size:11px; font-weight:800; padding:2px 7px; border-radius:6px; ' . $ptRateBadge . '">' . ($ptRate !== null ? number_format($ptRate, 2, ',', '.') . '%' : 'N/D') . '</span>';
                $body .= '</div>';
                $body .= '<div style="display:flex; justify-content:space-between; align-items:flex-end;">';
                $body .= '<div>';
                $body .= '<div style="font-size:18px; font-weight:800; color:#dc2626;">' . h($fmtUnits($ptUnits)) . ' <span style="font-size:11px; font-weight:600; color:#64748b;">unid</span></div>';
                $body .= '<div style="font-size:12px; font-weight:700; color:#475569; margin-top:2px;">' . number_format($ptKg, 2, ',', '.') . ' kg</div>';
                $body .= '</div>';
                $body .= '<div style="text-align:right;">';
                $body .= '<div style="font-size:11px; color:#64748b;">Participación</div>';
                $body .= '<div style="font-size:14px; font-weight:800; color:#0284c7;">' . number_format($ptShare, 1, ',', '.') . '%</div>';
                $body .= '</div>';
                $body .= '</div>';
                $body .= '<div style="font-size:11px; color:#94a3b8; border-top:1px solid #f1f5f9; padding-top:6px; display:flex; justify-content:space-between;">';
                $body .= '<span>Base prod: ' . h($fmtUnits($ptProd)) . '</span>';
                $body .= '<span>' . (int)$pt['machines_count'] . ' eq.</span>';
                $body .= '</div>';
                $body .= '</div>';
            }
            $body .= '</div>';

            // Summary Table
            $body .= '<div class="erp-prod-table-wrap" style="margin-bottom:18px;"><table class="erp-prod-table table-compact"><thead><tr>';
            $body .= '<th>Tipo de Producción</th><th style="text-align:center">Equipos</th><th style="text-align:right">Base Producción</th><th style="text-align:right">Merma (unid)</th><th style="text-align:right">Merma (kg)</th><th style="text-align:right">% Merma</th><th style="text-align:right">% del Total</th>';
            $body .= '</tr></thead><tbody>';
            foreach ($byProductionType as $pt) {
                $units = (float)($pt['waste_units'] ?? 0.0);
                $kg = (float)($pt['waste_kg'] ?? 0.0);
                $pProd = (float)($pt['produced_units'] ?? 0.0);
                $rate = $pt['waste_percent'] !== null ? (float)$pt['waste_percent'] : null;
                $share = (float)($pt['share_percent'] ?? 0.0);
                $rateBadge = $rate !== null 
                    ? ($rate > 5.0 ? 'background:#fee2e2;color:#b91c1c;' : ($rate > 2.0 ? 'background:#fef3c7;color:#b45309;' : 'background:#ecfdf5;color:#047857;'))
                    : '';
                $body .= '<tr>';
                $body .= '<td style="font-weight:700;"><span style="margin-right:6px;">' . ($pt['icon'] ?? '⚙️') . '</span>' . h((string)$pt['title']) . '</td>';
                $body .= '<td style="text-align:center; font-weight:600;">' . (int)$pt['machines_count'] . '</td>';
                $body .= '<td style="text-align:right;">' . h($fmtUnits($pProd)) . '</td>';
                $body .= '<td style="text-align:right; font-weight:800; color:#dc2626;">' . h($fmtUnits($units)) . '</td>';
                $body .= '<td style="text-align:right; font-weight:600;">' . number_format($kg, 2, ',', '.') . ' kg</td>';
                $body .= '<td style="text-align:right;"><span style="display:inline-block; padding:2px 8px; border-radius:6px; font-weight:800; font-size:11px; ' . $rateBadge . '">' . ($rate === null ? 'N/D' : number_format($rate, 2, ',', '.') . '%') . '</span></td>';
                $body .= '<td style="text-align:right; font-weight:700; color:#0284c7;">' . number_format($share, 1, ',', '.') . '%</td>';
                $body .= '</tr>';
            }
            $body .= '</tbody></table></div>';
        }

        // 2. Informe de Merma por Tipo de Máquina
        $body .= '<div class="dashboard-subtitle" style="margin-top:16px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">';
        $body .= '<div style="display:flex; align-items:center; gap:8px;"><span>⚙️</span> Informe de Merma por Tipo de Máquina</div>';
        $body .= '<div style="display:flex; gap:6px; flex-wrap:wrap;" id="machine-waste-filter-pills">';
        $body .= '<button type="button" class="btn secondary" data-machine-filter="all" style="padding:3px 10px; font-size:11.5px; font-weight:700; background:#00A9A6; color:#fff; border-color:#00A9A6;" onclick="unibagFilterWasteMachines(\'all\', this)">Todas (' . count($byMachine) . ')</button>';
        $body .= '<button type="button" class="btn secondary" data-machine-filter="impresion" style="padding:3px 10px; font-size:11.5px; font-weight:700;" onclick="unibagFilterWasteMachines(\'impresion\', this)">🖨️ Impresión</button>';
        $body .= '<button type="button" class="btn secondary" data-machine-filter="corte_sellado" style="padding:3px 10px; font-size:11.5px; font-weight:700;" onclick="unibagFilterWasteMachines(\'corte_sellado\', this)">✂️ Corte y Sellado</button>';
        $body .= '<button type="button" class="btn secondary" data-machine-filter="embalaje" style="padding:3px 10px; font-size:11.5px; font-weight:700;" onclick="unibagFilterWasteMachines(\'embalaje\', this)">📦 Embalaje</button>';
        $body .= '<button type="button" class="btn secondary" data-machine-filter="pulpo" style="padding:3px 10px; font-size:11.5px; font-weight:700;" onclick="unibagFilterWasteMachines(\'pulpo\', this)">🐙 Pulpo</button>';
        $body .= '</div>';
        $body .= '</div>';

        if ($byMachine === []) {
            $body .= '<div class="erp-prod-empty">Sin datos para el rango.</div>';
        } else {
            $body .= '<div class="erp-prod-table-wrap"><table class="erp-prod-table table-compact" id="table-waste-by-machine"><thead><tr>';
            $body .= '<th>Tipo / Proceso</th><th>Máquina</th><th style="text-align:right">Producción</th><th style="text-align:right">Merma (unid)</th><th style="text-align:right">Merma (kg)</th><th style="text-align:right">% Merma</th><th style="text-align:right">% del total</th>';
            $body .= '</tr></thead><tbody>';
            foreach ($byMachine as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $units = (float)($r['waste_units'] ?? 0.0);
                $kg = (float)($r['waste_kg'] ?? 0.0);
                $baseUnits = array_key_exists('requested_units', $r) && is_numeric($r['requested_units']) ? (float)$r['requested_units'] : null;
                $rate = $baseUnits !== null && $baseUnits > 0 ? (($units / $baseUnits) * 100.0) : null;
                $share = $machineShareTotal > 0 ? (($units / $machineShareTotal) * 100.0) : 0.0;
                $pCode = (string)($r['production_type_code'] ?? 'otros');
                $pTitle = (string)($r['production_type_title'] ?? 'Otros');
                $pIcon = (string)($r['production_type_icon'] ?? '⚙️');
                $rateBadge = $rate !== null 
                    ? ($rate > 5.0 ? 'background:#fee2e2;color:#b91c1c;' : ($rate > 2.0 ? 'background:#fef3c7;color:#b45309;' : 'background:#ecfdf5;color:#047857;'))
                    : '';

                $body .= '<tr data-process-code="' . h($pCode) . '">';
                $body .= '<td style="font-size:11.5px; font-weight:700; color:#475569;"><span style="margin-right:4px;">' . $pIcon . '</span>' . h($pTitle) . '</td>';
                $body .= '<td style="font-weight:700; color:#0f172a;">' . h((string)($r['machine_label'] ?? '')) . '</td>';
                $body .= '<td style="text-align:right">' . h($fmtUnits($baseUnits)) . '</td>';
                $body .= '<td style="text-align:right; font-weight:800; color:#dc2626;">' . h($fmtUnits($units)) . '</td>';
                $body .= '<td style="text-align:right; font-weight:600;">' . number_format($kg, 2, ',', '.') . ' kg</td>';
                $body .= '<td style="text-align:right;"><span style="display:inline-block; padding:2px 8px; border-radius:6px; font-weight:800; font-size:11px; ' . $rateBadge . '">' . ($rate === null ? 'N/D' : number_format($rate, 2, ',', '.') . '%') . '</span></td>';
                $body .= '<td style="text-align:right; font-weight:700; color:#0284c7;">' . number_format($share, 1, ',', '.') . '%</td>';
                $body .= '</tr>';
            }
            $body .= '</tbody></table></div>';
            $body .= '<script>
                function unibagFilterWasteMachines(procCode, btn) {
                    var container = document.getElementById("machine-waste-filter-pills");
                    if (container) {
                        container.querySelectorAll("button").forEach(function(b) {
                            b.style.background = "";
                            b.style.color = "";
                            b.style.borderColor = "";
                        });
                    }
                    if (btn) {
                        btn.style.background = "#00A9A6";
                        btn.style.color = "#fff";
                        btn.style.borderColor = "#00A9A6";
                    }
                    var table = document.getElementById("table-waste-by-machine");
                    if (!table) return;
                    table.querySelectorAll("tbody tr").forEach(function(row) {
                        if (procCode === "all" || row.getAttribute("data-process-code") === procCode) {
                            row.style.display = "";
                        } else {
                            row.style.display = "none";
                        }
                    });
                }
            </script>';
        }

        if ($byType !== []) {
            $body .= '<div class="dashboard-subtitle" style="margin-top:14px">Merma por tipo</div>';
            $body .= '<div class="erp-prod-table-wrap"><table class="erp-prod-table table-compact"><thead><tr>';
            $body .= '<th>Tipo</th><th style="text-align:right">Merma (unid)</th><th style="text-align:right">Merma (kg)</th><th style="text-align:right">Participación</th>';
            $body .= '</tr></thead><tbody>';
            foreach ($byType as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $units = (float)($r['waste_units'] ?? 0.0);
                $kg = (float)($r['waste_kg'] ?? 0.0);
                $share = $typeTotal > 0 ? (($units / $typeTotal) * 100.0) : 0.0;
                $body .= '<tr>';
                $body .= '<td>' . h((string)($r['type_label'] ?? '')) . '</td>';
                $body .= '<td style="text-align:right">' . h($fmtUnits($units)) . '</td>';
                $body .= '<td style="text-align:right">' . h(number_format($kg, 2, ',', '.')) . ' kg</td>';
                $body .= '<td style="text-align:right">' . h(number_format($share, 1, ',', '.')) . '%</td>';
                $body .= '</tr>';
            }
            $body .= '</tbody></table></div>';
        }

        $body .= '<div class="dashboard-subtitle" style="margin-top:14px">Top 10 mayores mermas</div>';
        if ($top10 === []) {
            $body .= '<div class="erp-prod-empty">Sin datos para el rango.</div>';
        } else {
            $body .= '<div class="erp-prod-table-wrap"><table class="erp-prod-table table-compact"><thead><tr>';
            $body .= '<th>OT</th><th>CC</th><th>Operador</th><th>Máquina</th><th style="text-align:right">Solicitadas</th><th style="text-align:right">Producidas</th><th style="text-align:right">Merma (unid)</th><th style="text-align:right">Merma (kg)</th><th style="text-align:right">% Merma</th>';
            $body .= '</tr></thead><tbody>';
            foreach ($top10 as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $requestedUnits = array_key_exists('requested_units', $r) && is_numeric($r['requested_units']) ? (float)$r['requested_units'] : null;
                $producedUnits = array_key_exists('produced_units', $r) && is_numeric($r['produced_units']) ? (float)$r['produced_units'] : null;
                $units = (float)($r['waste_units'] ?? 0.0);
                $kg = (float)($r['waste_kg'] ?? 0.0);
                $rate = array_key_exists('waste_percent', $r) && is_numeric($r['waste_percent']) ? (float)$r['waste_percent'] : null;
                $body .= '<tr>';
                $body .= '<td><div class="erp-prod-code">' . h((string)($r['work_order_number'] ?? '')) . '</div></td>';
                $body .= '<td>' . h((string)($r['cost_center'] ?? '')) . '</td>';
                $body .= '<td>' . h((string)($r['operator_name'] ?? '')) . '</td>';
                $body .= '<td>' . h((string)($r['machine_label'] ?? '')) . '</td>';
                $body .= '<td style="text-align:right">' . h($fmtUnits($requestedUnits)) . '</td>';
                $body .= '<td style="text-align:right">' . h($fmtUnits($producedUnits)) . '</td>';
                $body .= '<td style="text-align:right">' . h($fmtUnits($units)) . '</td>';
                $body .= '<td style="text-align:right">' . h(number_format($kg, 2, ',', '.')) . ' kg</td>';
                $body .= '<td style="text-align:right">' . h($rate === null ? 'N/D' : (number_format($rate, 2, ',', '.') . '%')) . '</td>';
                $body .= '</tr>';
            }
            $body .= '</tbody></table></div>';
        }

        $body .= '</div>';
        $body .= '</div>';
        $body .= '</div>';
        $body .= '<script>(function(){var b=document.getElementById("waste-modal");if(!b)return;function c(){var h=b.getAttribute("data-close-href");if(h)window.location.href=h;}b.addEventListener("click",function(e){if(e.target===b)c();});document.addEventListener("keydown",function(e){if(e.key==="Escape")c();});})();</script>';
    }

    $body .= '</div>'; // fin dashboard-shell
    render('Dashboard', $body);
}

function unibagRenderLegacyErpHomePage(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $userId = (int)($_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? 0);
    $home = (array)ResultCache::remember('dash-home-' . $userId, 600, static fn() => $service->getLegacyErpHomeDashboard($userId));
    $dashboardUrl = trim((string)($home['dashboard_url'] ?? ''));
    $userLabel = trim((string)($_SESSION['auth_display_name'] ?? $_SESSION['auth_username'] ?? $_SESSION['user_name'] ?? ''));
    if ($userLabel === '') {
        $userLabel = 'Usuario';
    }

    $salesRows = is_array($home['sales_by_year'] ?? null) ? $home['sales_by_year'] : [];
    $salesHtml = '<div class="erp-prod-empty">Sin datos.</div>';
    if ($salesRows !== []) {
        $salesHtml = '<div class="erp-prod-table-wrap"><table class="erp-prod-table"><thead><tr><th>Año</th><th>Total neto</th></tr></thead><tbody>';
        foreach ($salesRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $year = (string)($row['year'] ?? '');
            $total = (float)($row['total'] ?? 0.0);
            $salesHtml .= '<tr><td>' . h($year) . '</td><td>' . h(number_format($total, 0, ',', '.')) . '</td></tr>';
        }
        $salesHtml .= '</tbody></table></div>';
    }

    $body = '';
    $body .= '<div class="dashboard-grid">';
    $body .= '<div class="trace-stack">';
    $body .= '<div class="card">';
    $body .= '<h2 style="margin:0 0 10px">Inicio</h2>';
    $body .= '<div class="erp-prod-muted">Sesión: <strong>' . h($userLabel) . '</strong></div>';
    $body .= '<div class="row" style="margin-top:12px;gap:10px;flex-wrap:wrap">';
    $body .= '<a class="btn secondary" href="/reports/production-dashboard">Dashboard Producción</a>';
    $body .= '<a class="btn secondary" href="/bonificaciones?view=bonoflexo">Bonoflexo</a>';
    $body .= '<a class="btn secondary" href="/reception">Recepción de Bobinas</a>';
    $body .= '</div>';
    $body .= '</div>';

    if ($dashboardUrl !== '') {
        $body .= '<div class="card" style="padding:0;overflow:hidden">';
        $body .= '<div class="toolbar" style="border-bottom:1px solid #e5e7eb;padding:10px 12px"><strong>Dashboard</strong></div>';
        $body .= '<iframe src="' . h($dashboardUrl) . '" style="width:100%;height:680px;border:0"></iframe>';
        $body .= '</div>';
    }
    $body .= '</div>';
    $body .= '<div class="trace-stack">';
    $body .= '<div class="card">';
    $body .= '<h3 style="margin:0 0 10px">Ventas (neto) por año</h3>';
    $body .= $salesHtml;
    $body .= '</div>';
    $body .= '</div>';
    $body .= '</div>';

    render('Inicio', $body);
}

/**
 * Resuelve los filtros del dashboard de producción.
 *
 * Soporta dos modos:
 * - period: período operativo 26–25 basado en un mes “final” (YYYY-MM)
 * - range: rango explícito (start_date/end_date)
 *
 * Retorna además labels y params normalizados para re-render y export.
 *
 * ---
 *
 * Resolves production dashboard filters.
 *
 * Supports two modes:
 * - period: operational period 26–25 based on a “final month” (YYYY-MM)
 * - range: explicit range (start_date/end_date)
 *
 * Also returns normalized labels and params for re-rendering and exporting.
 *
 * @return array<string, mixed>
 */
function unibagResolveProductionDashboardFilters(): array
{
    $today = new DateTimeImmutable('today');
    $defaultFilterType = strtolower(trim((string)($_GET['filter_type'] ?? 'period')));
    if (!in_array($defaultFilterType, ['period', 'range'], true)) {
        $defaultFilterType = 'period';
    }

    $defaultPeriodYm = ((int)$today->format('d') >= 26)
        ? $today->modify('first day of next month')->format('Y-m')
        : $today->modify('first day of this month')->format('Y-m');

    $periodYm = trim((string)($_GET['period'] ?? $defaultPeriodYm));
    if (preg_match('/^\d{4}-\d{2}$/', $periodYm) !== 1) {
        $periodYm = $defaultPeriodYm;
    }

    $periodMonth = DateTimeImmutable::createFromFormat('Y-m-d', $periodYm . '-01') ?: new DateTimeImmutable('first day of this month');
    $periodStart = $periodMonth->modify('-1 month')->setDate(
        (int)$periodMonth->modify('-1 month')->format('Y'),
        (int)$periodMonth->modify('-1 month')->format('m'),
        26
    )->setTime(0, 0, 0);
    $periodEnd = $periodMonth->setDate(
        (int)$periodMonth->format('Y'),
        (int)$periodMonth->format('m'),
        25
    )->setTime(23, 59, 59);
    $periodLabel = 'Período operativo ' . $periodStart->format('d/m/Y') . ' - ' . $periodEnd->format('d/m/Y');

    $defaultRangeStart = $today->modify('-6 days')->format('Y-m-d');
    $defaultRangeEnd = $today->format('Y-m-d');
    $rangeStartInput = trim((string)($_GET['start_date'] ?? $defaultRangeStart));
    $rangeEndInput = trim((string)($_GET['end_date'] ?? $defaultRangeEnd));
    $rangeStartDate = DateTimeImmutable::createFromFormat('Y-m-d', $rangeStartInput) ?: DateTimeImmutable::createFromFormat('Y-m-d', $defaultRangeStart);
    $rangeEndDate = DateTimeImmutable::createFromFormat('Y-m-d', $rangeEndInput) ?: DateTimeImmutable::createFromFormat('Y-m-d', $defaultRangeEnd);
    if (!$rangeStartDate instanceof DateTimeImmutable) {
        $rangeStartDate = $today->modify('-6 days');
    }
    if (!$rangeEndDate instanceof DateTimeImmutable) {
        $rangeEndDate = $today;
    }
    if ($rangeStartDate > $rangeEndDate) {
        [$rangeStartDate, $rangeEndDate] = [$rangeEndDate, $rangeStartDate];
        $rangeStartInput = $rangeStartDate->format('Y-m-d');
        $rangeEndInput = $rangeEndDate->format('Y-m-d');
    }

    if ($defaultFilterType === 'range') {
        $start = $rangeStartDate->setTime(0, 0, 0);
        $end = $rangeEndDate->setTime(23, 59, 59);
        $activeFilterLabel = 'Rango ' . $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y');
    } else {
        $start = $periodStart;
        $end = $periodEnd;
        $activeFilterLabel = $periodLabel;
    }

    return [
        'filter_type' => $defaultFilterType,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'period' => $periodYm,
        'start_date' => $rangeStartInput,
        'end_date' => $rangeEndInput,
        'start' => $start,
        'end' => $end,
        'active_filter_label' => $activeFilterLabel,
        'period_label' => $periodLabel,
        'filter_params' => [
            'filter_type' => $defaultFilterType,
        ] + ($defaultFilterType === 'range' ? [
            'start_date' => $rangeStartInput,
            'end_date' => $rangeEndInput,
        ] : [
            'period' => $periodYm,
        ]),
    ];
}

/**
 * Filtra y ordena filas de métricas del dashboard.
 *
 * - Aplica un filtro distinto por modo (produced/pending/dispatched/waste/processed/semi).
 * - Ordena por “score” descendente del modo seleccionado.
 *
 * ---
 *
 * Filters and sorts dashboard metric rows.
 *
 * - Applies a mode-specific filter (produced/pending/dispatched/waste/processed/semi).
 * - Sorts by the selected mode “score” descending.
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function unibagProductionDashboardMetricRows(string $mode, array $rows): array
{
    $filteredRows = array_values(array_filter($rows, static function (array $row) use ($mode): bool {
        return match ($mode) {
            'produced' => (float)($row['produced_units'] ?? 0) > 0 || (float)($row['target_qty'] ?? 0) > 0,
            'pending' => (float)($row['pending_units'] ?? 0) > 0,
            'dispatched' => (float)($row['dispatched_units'] ?? 0) > 0,
            'waste' => (float)($row['waste_kg'] ?? 0) > 0 || (float)($row['processed_kg'] ?? 0) > 0,
            'processed' => (float)($row['processed_kg'] ?? 0) > 0,
            'semi' => (int)($row['semi_rolls_count'] ?? 0) > 0,
            default => false,
        };
    }));

    usort($filteredRows, static function (array $left, array $right) use ($mode): int {
        $leftScore = match ($mode) {
            'produced' => (float)($left['produced_units'] ?? 0),
            'pending' => (float)($left['pending_units'] ?? 0),
            'dispatched' => (float)($left['dispatched_units'] ?? 0),
            'waste' => (float)($left['waste_kg'] ?? 0),
            'processed' => (float)($left['processed_kg'] ?? 0),
            'semi' => (float)($left['semi_rolls_count'] ?? 0),
            default => 0.0,
        };
        $rightScore = match ($mode) {
            'produced' => (float)($right['produced_units'] ?? 0),
            'pending' => (float)($right['pending_units'] ?? 0),
            'dispatched' => (float)($right['dispatched_units'] ?? 0),
            'waste' => (float)($right['waste_kg'] ?? 0),
            'processed' => (float)($right['processed_kg'] ?? 0),
            'semi' => (float)($right['semi_rolls_count'] ?? 0),
            default => 0.0,
        };
        if ($leftScore === $rightScore) {
            return (int)($right['id'] ?? 0) <=> (int)($left['id'] ?? 0);
        }

        return $rightScore <=> $leftScore;
    });

    return $filteredRows;
}

/**
 * Define el export (XLS HTML) para una métrica específica del dashboard.
 *
 * Retorna null si no hay filas para exportar.
 *
 * ---
 *
 * Builds the export (HTML-based XLS) definition for a given dashboard metric.
 *
 * Returns null when there are no rows to export.
 *
 * @return array<string, mixed>|null
 */
function unibagProductionDashboardMetricExportDefinition(string $metric, array $workOrders, string $activeFilterLabel): ?array
{
    $rows = unibagProductionDashboardMetricRows($metric, $workOrders);
    if ($rows === []) {
        return null;
    }

    $generatedAt = date('d/m/Y H:i');
    $base = [
        'generated_at' => $generatedAt,
        'filter_label' => $activeFilterLabel,
    ];

    return match ($metric) {
        'produced' => array_merge($base, [
            'filename' => 'dashboard-produccion-unidades-producidas.xlsx',
            'title' => 'Dashboard Producción - Unidades producidas por OT',
            'summary' => [
                ['label' => 'OTs con producción', 'value' => (string)count($rows)],
                ['label' => 'Unidades producidas', 'value' => number_format(array_sum(array_map(static fn(array $row): float => (float)($row['produced_units'] ?? 0), $rows)), 0, '.', '')],
                ['label' => 'Unidades pendientes', 'value' => number_format(array_sum(array_map(static fn(array $row): float => (float)($row['pending_units'] ?? 0), $rows)), 0, '.', '')],
            ],
            'columns' => ['OT', 'Máquina', 'SKU final', 'Estado', 'Objetivo', 'Producidas', 'Pendientes', 'Avance %', 'Cajas'],
            'rows' => array_map(static function (array $row): array {
                return [
                    (string)($row['ot_code'] ?? ('OT #' . (int)($row['id'] ?? 0))),
                    (string)($row['machine_name'] ?? '-'),
                    (string)($row['sku_final'] ?? '-'),
                    (string)($row['dashboard_status'] ?? '-'),
                    number_format((float)($row['target_qty'] ?? 0), 0, '.', ''),
                    number_format((float)($row['produced_units'] ?? 0), 0, '.', ''),
                    number_format((float)($row['pending_units'] ?? 0), 0, '.', ''),
                    number_format((float)($row['progress_percent'] ?? 0), 2, '.', ''),
                    (string)($row['boxes_count'] ?? 0),
                ];
            }, $rows),
        ]),
        'pending' => array_merge($base, [
            'filename' => 'dashboard-produccion-pendientes-por-ot.xlsx',
            'title' => 'Dashboard Producción - Pendientes por OT',
            'summary' => [
                ['label' => 'OTs con pendiente', 'value' => (string)count($rows)],
                ['label' => 'Pendiente total', 'value' => number_format(array_sum(array_map(static fn(array $row): float => (float)($row['pending_units'] ?? 0), $rows)), 0, '.', '')],
                ['label' => 'OTs activas', 'value' => (string)count(array_filter($rows, static fn(array $row): bool => in_array((string)($row['dashboard_status'] ?? ''), ['Con avance', 'En produccion', 'Pendiente', 'En corte'], true)))],
            ],
            'columns' => ['OT', 'Máquina', 'SKU final', 'Estado', 'Objetivo', 'Producidas', 'Pendientes', 'Avance %'],
            'rows' => array_map(static function (array $row): array {
                return [
                    (string)($row['ot_code'] ?? ('OT #' . (int)($row['id'] ?? 0))),
                    (string)($row['machine_name'] ?? '-'),
                    (string)($row['sku_final'] ?? '-'),
                    (string)($row['dashboard_status'] ?? '-'),
                    number_format((float)($row['target_qty'] ?? 0), 0, '.', ''),
                    number_format((float)($row['produced_units'] ?? 0), 0, '.', ''),
                    number_format((float)($row['pending_units'] ?? 0), 0, '.', ''),
                    number_format((float)($row['progress_percent'] ?? 0), 2, '.', ''),
                ];
            }, $rows),
        ]),
        'dispatched' => array_merge($base, [
            'filename' => 'dashboard-produccion-despachos-por-ot.xlsx',
            'title' => 'Dashboard Producción - Despachos por OT',
            'summary' => [
                ['label' => 'OTs con despacho', 'value' => (string)count($rows)],
                ['label' => 'Unidades despachadas', 'value' => number_format(array_sum(array_map(static fn(array $row): float => (float)($row['dispatched_units'] ?? 0), $rows)), 0, '.', '')],
                ['label' => 'Cobertura promedio %', 'value' => number_format(array_sum(array_map(static fn(array $row): float => (float)($row['dispatch_coverage_percent'] ?? 0), $rows)) / max(count($rows), 1), 2, '.', '')],
            ],
            'columns' => ['OT', 'SKU final', 'Estado', 'Producidas', 'Despachadas', 'Cobertura %', 'Último movimiento'],
            'rows' => array_map(static function (array $row): array {
                return [
                    (string)($row['ot_code'] ?? ('OT #' . (int)($row['id'] ?? 0))),
                    (string)($row['sku_final'] ?? '-'),
                    (string)($row['dashboard_status'] ?? '-'),
                    number_format((float)($row['produced_units'] ?? 0), 0, '.', ''),
                    number_format((float)($row['dispatched_units'] ?? 0), 0, '.', ''),
                    number_format((float)($row['dispatch_coverage_percent'] ?? 0), 2, '.', ''),
                    (string)($row['last_box_at'] ?? '-'),
                ];
            }, $rows),
        ]),
        'waste' => array_merge($base, [
            'filename' => 'dashboard-produccion-merma-por-ot.xlsx',
            'title' => 'Dashboard Producción - Merma por OT',
            'summary' => [
                ['label' => 'OTs con merma', 'value' => (string)count($rows)],
                ['label' => 'Kg merma', 'value' => number_format(array_sum(array_map(static fn(array $row): float => (float)($row['waste_kg'] ?? 0), $rows)), 3, '.', '')],
                ['label' => 'Registros de merma', 'value' => (string)array_sum(array_map(static fn(array $row): int => (int)($row['waste_records'] ?? 0), $rows))],
            ],
            'columns' => ['OT', 'SKU final', 'Estado', 'Kg merma', 'Kg procesados', 'Merma %', 'Registros'],
            'rows' => array_map(static function (array $row): array {
                return [
                    (string)($row['ot_code'] ?? ('OT #' . (int)($row['id'] ?? 0))),
                    (string)($row['sku_final'] ?? '-'),
                    (string)($row['dashboard_status'] ?? '-'),
                    number_format((float)($row['waste_kg'] ?? 0), 3, '.', ''),
                    number_format((float)($row['processed_kg'] ?? 0), 3, '.', ''),
                    number_format((float)($row['waste_percent'] ?? 0), 2, '.', ''),
                    (string)($row['waste_records'] ?? 0),
                ];
            }, $rows),
        ]),
        'processed' => array_merge($base, [
            'filename' => 'dashboard-produccion-kg-procesados-por-ot.xlsx',
            'title' => 'Dashboard Producción - Kg procesados por OT',
            'summary' => [
                ['label' => 'OTs con proceso', 'value' => (string)count($rows)],
                ['label' => 'Kg procesados', 'value' => number_format(array_sum(array_map(static fn(array $row): float => (float)($row['processed_kg'] ?? 0), $rows)), 3, '.', '')],
                ['label' => 'Entradas registradas', 'value' => (string)array_sum(array_map(static fn(array $row): int => (int)($row['attached_events'] ?? 0), $rows))],
            ],
            'columns' => ['OT', 'SKU final', 'Estado', 'Kg procesados', 'Kg merma', 'Merma %', 'Entradas'],
            'rows' => array_map(static function (array $row): array {
                return [
                    (string)($row['ot_code'] ?? ('OT #' . (int)($row['id'] ?? 0))),
                    (string)($row['sku_final'] ?? '-'),
                    (string)($row['dashboard_status'] ?? '-'),
                    number_format((float)($row['processed_kg'] ?? 0), 3, '.', ''),
                    number_format((float)($row['waste_kg'] ?? 0), 3, '.', ''),
                    number_format((float)($row['waste_percent'] ?? 0), 2, '.', ''),
                    (string)($row['attached_events'] ?? 0),
                ];
            }, $rows),
        ]),
        'semi' => array_merge($base, [
            'filename' => 'dashboard-produccion-semielaboradas-por-ot.xlsx',
            'title' => 'Dashboard Producción - Semielaboradas por OT',
            'summary' => [
                ['label' => 'OTs con semielaboradas', 'value' => (string)count($rows)],
                ['label' => 'Bobinas pendientes', 'value' => (string)array_sum(array_map(static fn(array $row): int => (int)($row['semi_rolls_count'] ?? 0), $rows))],
                ['label' => 'Kg semielaborados', 'value' => number_format(array_sum(array_map(static fn(array $row): float => (float)($row['semi_weight_kg'] ?? 0), $rows)), 3, '.', '')],
            ],
            'columns' => ['OT', 'SKU final', 'Estado', 'Bobinas', 'Kg', 'Metros', 'Avance %'],
            'rows' => array_map(static function (array $row): array {
                return [
                    (string)($row['ot_code'] ?? ('OT #' . (int)($row['id'] ?? 0))),
                    (string)($row['sku_final'] ?? '-'),
                    (string)($row['dashboard_status'] ?? '-'),
                    (string)($row['semi_rolls_count'] ?? 0),
                    number_format((float)($row['semi_weight_kg'] ?? 0), 3, '.', ''),
                    number_format((float)($row['semi_meters'] ?? 0), 0, '.', ''),
                    number_format((float)($row['progress_percent'] ?? 0), 2, '.', ''),
                ];
            }, $rows),
        ]),
        default => null,
    };
}

/**
 * Exporta una tarjeta (métrica) del dashboard de producción a Excel (XLS vía HTML).
 *
 * Flujo:
 * - Valida permiso de área ERP.
 * - Resuelve filtros (período 26–25 o rango).
 * - Consulta KPIs y OTs al servicio.
 * - Arma definición de export (título, columnas, filas, resumen) según la métrica.
 * - Emite headers HTTP de descarga y el HTML del XLS.
 *
 * ---
 *
 * Exports a production dashboard card (metric) to Excel (HTML-based XLS).
 *
 * Flow:
 * - Validates ERP area permission.
 * - Resolves filters (26–25 period or explicit range).
 * - Queries KPIs and work orders via the service.
 * - Builds an export definition (title, columns, rows, summary) for the selected metric.
 * - Outputs download headers and the XLS HTML.
 */
function unibagOutputProductionDashboardMetricExcel(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $metric = strtolower(trim((string)($_GET['metric'] ?? '')));
    $filters = unibagResolveProductionDashboardFilters();
    $kpis = $service->getProductionDashboardKpis(
        $filters['start']->format('Y-m-d H:i:s'),
        $filters['end']->format('Y-m-d H:i:s')
    );
    $workOrders = is_array($kpis['work_orders'] ?? null) ? $kpis['work_orders'] : [];
    $definition = unibagProductionDashboardMetricExportDefinition($metric, $workOrders, (string)$filters['active_filter_label']);
    if ($definition === null) {
        render('No encontrado', '<div class="card">No hay información disponible para exportar en esta tarjeta.</div>');
        exit;
    }

    ob_start();

    echo '<html><head><meta charset="UTF-8"><style>';
    echo 'body{font-family:Arial,sans-serif;color:#0f172a}';
    echo '.title{font-size:22px;font-weight:700;color:#0f172a}';
    echo '.sub{font-size:12px;color:#475569;margin-bottom:14px}';
    echo '.meta{margin:8px 0 18px 0;font-size:12px;color:#334155}';
    echo '.summary{border-collapse:collapse;margin-bottom:18px;width:100%}';
    echo '.summary td,.summary th{border:1px solid #cbd5e1;padding:8px 10px}';
    echo '.summary th{background:#e2e8f0;text-align:left}';
    echo '.report{border-collapse:collapse;width:100%}';
    echo '.report td,.report th{border:1px solid #cbd5e1;padding:8px 10px}';
    echo '.report th{background:#0f172a;color:#fff;text-align:left;font-size:12px}';
    echo '.report tr:nth-child(even) td{background:#f8fafc}';
    echo '.num-int{mso-number-format:"\#\,\#\#0";text-align:right}';
    echo '.num-dec{mso-number-format:"\#\,\#\#0\.00";text-align:right}';
    echo '.text-cell{mso-number-format:"\@"}';
    echo '</style></head><body>';
    echo '<div class="title">' . h((string)$definition['title']) . '</div>';
    echo '<div class="meta"><strong>Filtro aplicado:</strong> ' . h((string)$definition['filter_label']) . '<br><strong>Generado:</strong> ' . h((string)$definition['generated_at']) . '</div>';
    echo '<table class="summary"><tr><th>Indicador</th><th>Valor</th></tr>';
    foreach ((array)$definition['summary'] as $summaryRow) {
        $valStr = (string)($summaryRow['value'] ?? '');
        $cls = (is_numeric($valStr) && (!str_starts_with($valStr, '0') || $valStr === '0'))
            ? (str_contains($valStr, '.') ? 'num-dec' : 'num-int')
            : '';
        echo '<tr><td>' . h((string)($summaryRow['label'] ?? '')) . '</td><td class="' . $cls . '">' . h($valStr) . '</td></tr>';
    }
    echo '</table>';
    echo '<table class="report"><tr>';
    foreach ((array)$definition['columns'] as $column) {
        echo '<th>' . h((string)$column) . '</th>';
    }
    echo '</tr>';
    foreach ((array)$definition['rows'] as $row) {
        echo '<tr>';
        foreach ((array)$row as $cell) {
            $cellStr = (string)$cell;
            if (is_numeric($cellStr) && (!str_starts_with($cellStr, '0') || $cellStr === '0')) {
                $cls = str_contains($cellStr, '.') ? 'num-dec' : 'num-int';
                echo '<td class="' . $cls . '">' . h($cellStr) . '</td>';
            } else {
                echo '<td>' . h($cellStr) . '</td>';
            }
        }
        echo '</tr>';
    }
    echo '</table></body></html>';
    $html = ob_get_clean();

    SimpleXlsx::streamHtml((string)$definition['filename'], $html, 'Dashboard');
}

/**
 * Exporta el detalle operativo completo de merma del dashboard (modal de merma) a Excel.
 */
function unibagOutputDashboardWasteDetailExcel(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    $startStr = $filters['start']->format('Y-m-d H:i:s');
    $endStr = $filters['end']->format('Y-m-d H:i:s');
    $activeFilterLabel = (string)$filters['active_filter_label'];

    $kpis = (array)unibagDashboardCached('kpis', $startStr, $endStr, static fn() => $service->getErpOnlyProductionDashboardKpis($startStr, $endStr));
    $waste = is_array($kpis['waste'] ?? null) ? $kpis['waste'] : [];
    $details = (array)unibagDashboardCached('waste-details', $startStr, $endStr, static fn() => $service->getErpWasteDashboardDetails($startStr, $endStr));

    $wasteUnitsTotal = array_key_exists('waste_units', $waste) && is_numeric($waste['waste_units']) ? (float)$waste['waste_units'] : 0.0;
    $wasteKgTotal = array_key_exists('waste_kg', $waste) && is_numeric($waste['waste_kg']) ? (float)$waste['waste_kg'] : 0.0;
    $wasteBaseTotal = array_key_exists('base_units', $waste) && is_numeric($waste['base_units']) ? (float)$waste['base_units'] : (float)($kpis['produced_units'] ?? 0.0);
    $wasteRequestedTotal = array_key_exists('requested_units', $waste) && is_numeric($waste['requested_units']) ? (float)$waste['requested_units'] : 0.0;
    $wastePercent = $wasteBaseTotal > 0 ? (($wasteUnitsTotal / $wasteBaseTotal) * 100.0) : 0.0;

    $byMachine = is_array($details['by_machine'] ?? null) ? $details['by_machine'] : [];
    $byType = is_array($details['by_type'] ?? null) ? $details['by_type'] : [];
    $top10 = is_array($details['top10'] ?? null) ? $details['top10'] : [];

    $filename = 'detalle-merma-dashboard-' . date('Ymd-His') . '.xlsx';
    $machineShareTotal = $wasteUnitsTotal > 0
        ? $wasteUnitsTotal
        : array_sum(array_map(static fn(array $row): float => (float)($row['waste_units'] ?? 0), $byMachine));
    ob_start();

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Detalle de Merma</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>';
    echo 'body{font-family:Calibri,Arial,sans-serif;font-size:11pt;color:#1e293b;background:#ffffff}';
    echo '.report-header{margin-bottom:16px}';
    echo '.report-title{font-size:16pt;font-weight:bold;color:#0f172a}';
    echo '.report-meta{font-size:10pt;color:#475569;margin-top:4px}';
    echo '.section-title{font-size:12pt;font-weight:bold;color:#0f172a;background:#f1f5f9;padding:6px 10px;border-left:4px solid #00a9a6;margin:18px 0 8px 0}';
    echo 'table{border-collapse:collapse;width:100%;margin-bottom:18px}';
    echo 'th{background:#1e293b;color:#ffffff;font-weight:bold;font-size:10pt;padding:8px 10px;border:1px solid #94a3b8;vertical-align:middle}';
    echo 'td{font-size:10pt;padding:6px 10px;border:1px solid #cbd5e1;vertical-align:middle;color:#1e293b}';
    echo '.th-sub{background:#e2e8f0;color:#0f172a;font-weight:bold}';
    echo '.num-int{mso-number-format:"\#\,\#\#0";text-align:right}';
    echo '.num-dec{mso-number-format:"\#\,\#\#0\.00";text-align:right}';
    echo '.num-pct{mso-number-format:"0\.00%";text-align:right}';
    echo '.text-center{text-align:center}';
    echo '.text-right{text-align:right}';
    echo '.font-bold{font-weight:bold}';
    echo '.highlight-red{color:#dc2626;font-weight:bold}';
    echo '.row-even{background:#f8fafc}';
    echo '</style></head><body>';

    echo '<div class="report-header">';
    echo '<div class="report-title">Control de Calidad — Detalle Operativo de Merma</div>';
    echo '<div class="report-meta"><strong>Filtro aplicado:</strong> ' . h($activeFilterLabel) . ' | <strong>Generado el:</strong> ' . date('d/m/Y H:i') . '</div>';
    echo '</div>';

    // 1. Resumen General
    echo '<div class="section-title">1. Resumen General de Operación</div>';
    echo '<table>';
    echo '<tr><th class="th-sub" style="width:340px;text-align:left">Indicador Global</th><th class="th-sub" style="text-align:right;width:180px">Valor Reportado</th></tr>';
    echo '<tr><td>Producción total base (unidades)</td><td class="num-int font-bold">' . (int)round($wasteBaseTotal) . '</td></tr>';
    echo '<tr class="row-even"><td>Merma total acumulada (unidades)</td><td class="num-int highlight-red">' . (int)round($wasteUnitsTotal) . '</td></tr>';
    echo '<tr><td>Merma total acumulada (kg)</td><td class="num-dec font-bold">' . round($wasteKgTotal, 2) . ' kg</td></tr>';
    echo '<tr class="row-even"><td>% Merma global sobre producción</td><td class="text-right font-bold' . ($wastePercent > 5.0 ? ' highlight-red' : '') . '">' . number_format($wastePercent, 2, ',', '.') . '%</td></tr>';
    if ($wasteRequestedTotal > 0) {
        echo '<tr><td>Total unidades solicitadas (plan)</td><td class="num-int">' . (int)round($wasteRequestedTotal) . '</td></tr>';
    }
    echo '</table>';

    // 2. Merma por Tipo de Producción
    $byProductionType = is_array($details['by_production_type'] ?? null) ? $details['by_production_type'] : [];
    echo '<div class="section-title">2. Merma Acumulada por Tipo de Producción (Impresión, Corte y Sellado, Embalaje, Pulpo)</div>';
    echo '<table>';
    echo '<thead><tr>';
    echo '<th style="text-align:left">Tipo de Producción</th><th style="text-align:center">Equipos</th><th style="text-align:right">Base Producción (unid)</th><th style="text-align:right">Merma (unid)</th><th style="text-align:right">Merma (kg)</th><th style="text-align:right">% Merma</th><th style="text-align:right">% del Total</th>';
    echo '</tr></thead><tbody>';
    if ($byProductionType === []) {
        echo '<tr><td colspan="7" class="text-center">Sin registros de procesos para el período.</td></tr>';
    } else {
        $idx = 0;
        foreach ($byProductionType as $pt) {
            $idx++;
            $units = (float)($pt['waste_units'] ?? 0.0);
            $kg = (float)($pt['waste_kg'] ?? 0.0);
            $prod = (float)($pt['produced_units'] ?? 0.0);
            $rate = $pt['waste_percent'] !== null ? (float)$pt['waste_percent'] : null;
            $share = (float)($pt['share_percent'] ?? 0.0);
            $rowCls = ($idx % 2 === 0) ? ' class="row-even"' : '';
            echo '<tr' . $rowCls . '>';
            echo '<td class="font-bold">' . h((string)$pt['title']) . '</td>';
            echo '<td class="text-center font-bold">' . (int)$pt['machines_count'] . '</td>';
            echo '<td class="num-int">' . (int)round($prod) . '</td>';
            echo '<td class="num-int highlight-red">' . (int)round($units) . '</td>';
            echo '<td class="num-dec">' . round($kg, 2) . ' kg</td>';
            echo '<td class="text-right font-bold' . ($rate !== null && $rate > 5.0 ? ' highlight-red' : '') . '">' . ($rate !== null ? (number_format($rate, 2, ',', '.') . '%') : 'N/D') . '</td>';
            echo '<td class="text-right font-bold">' . number_format($share, 1, ',', '.') . '%</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';

    // 3. Merma por Máquina
    echo '<div class="section-title">3. Merma Acumulada por Tipo de Máquina</div>';
    echo '<table>';
    echo '<thead><tr>';
    echo '<th style="text-align:left">Tipo / Proceso</th><th style="text-align:left">Máquina</th><th style="text-align:right">Producción (unid)</th><th style="text-align:right">Merma (unid)</th><th style="text-align:right">Merma (kg)</th><th style="text-align:right">% Merma</th><th style="text-align:right">% del total</th>';
    echo '</tr></thead><tbody>';
    if ($byMachine === []) {
        echo '<tr><td colspan="7" class="text-center">Sin registros de merma para el período.</td></tr>';
    } else {
        $idx = 0;
        foreach ($byMachine as $r) {
            $idx++;
            $units = (float)($r['waste_units'] ?? 0.0);
            $kg = (float)($r['waste_kg'] ?? 0.0);
            $req = array_key_exists('requested_units', $r) && is_numeric($r['requested_units']) ? (float)$r['requested_units'] : null;
            $rate = ($req !== null && $req > 0) ? (($units / $req) * 100.0) : null;
            $share = $machineShareTotal > 0 ? (($units / $machineShareTotal) * 100.0) : 0.0;
            $pTitle = (string)($r['production_type_title'] ?? 'Otros');
            $rowCls = ($idx % 2 === 0) ? ' class="row-even"' : '';
            echo '<tr' . $rowCls . '>';
            echo '<td>' . h($pTitle) . '</td>';
            echo '<td class="font-bold">' . h((string)($r['machine_label'] ?? '')) . '</td>';
            echo '<td class="num-int">' . ($req !== null ? (int)round($req) : 'N/D') . '</td>';
            echo '<td class="num-int highlight-red">' . (int)round($units) . '</td>';
            echo '<td class="num-dec">' . round($kg, 2) . ' kg</td>';
            echo '<td class="text-right font-bold' . ($rate !== null && $rate > 5.0 ? ' highlight-red' : '') . '">' . ($rate !== null ? (number_format($rate, 2, ',', '.') . '%') : 'N/D') . '</td>';
            echo '<td class="text-right font-bold">' . number_format($share, 1, ',', '.') . '%</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';

    // 4. Merma por Tipo de Defecto
    echo '<div class="section-title">4. Distribución de Merma por Tipo de Defecto</div>';
    echo '<table>';
    echo '<thead><tr>';
    echo '<th style="text-align:left">Tipo de Defecto</th><th style="text-align:right">Merma (unid)</th><th style="text-align:right">Merma (kg)</th><th style="text-align:right">% Participación</th>';
    echo '</tr></thead><tbody>';
    if ($byType === []) {
        echo '<tr><td colspan="4" class="text-center">Sin registros de defectos clasificados.</td></tr>';
    } else {
        $typeSum = array_sum(array_map(fn($r) => (float)($r['waste_units'] ?? 0), $byType));
        $idx = 0;
        foreach ($byType as $r) {
            $idx++;
            $units = (float)($r['waste_units'] ?? 0.0);
            $kg = (float)($r['waste_kg'] ?? 0.0);
            $share = $typeSum > 0 ? (($units / $typeSum) * 100.0) : 0.0;
            $rowCls = ($idx % 2 === 0) ? ' class="row-even"' : '';
            echo '<tr' . $rowCls . '>';
            echo '<td>' . h((string)($r['type_label'] ?? '')) . '</td>';
            echo '<td class="num-int highlight-red">' . (int)round($units) . '</td>';
            echo '<td class="num-dec">' . round($kg, 2) . ' kg</td>';
            echo '<td class="text-right font-bold">' . number_format($share, 1, ',', '.') . '%</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';

    // 4. Top Mayores Mermas
    echo '<div class="section-title">4. Top 10 Órdenes de Trabajo con Mayor Merma</div>';
    echo '<table>';
    echo '<thead><tr>';
    echo '<th class="text-center">OT</th><th class="text-center">Centro Costo</th><th>Operador</th><th>Máquina</th><th style="text-align:right">Solicitadas</th><th style="text-align:right">Producidas</th><th style="text-align:right">Merma (unid)</th><th style="text-align:right">Merma (kg)</th><th style="text-align:right">% Merma</th>';
    echo '</tr></thead><tbody>';
    if ($top10 === []) {
        echo '<tr><td colspan="9" class="text-center">Sin registros de OTs para el período.</td></tr>';
    } else {
        $idx = 0;
        foreach ($top10 as $r) {
            $idx++;
            $req = (float)($r['requested_units'] ?? 0.0);
            $prod = (float)($r['produced_units'] ?? 0.0);
            $units = (float)($r['waste_units'] ?? 0.0);
            $kg = (float)($r['waste_kg'] ?? 0.0);
            $rate = array_key_exists('waste_percent', $r) && is_numeric($r['waste_percent']) ? (float)$r['waste_percent'] : null;
            $rowCls = ($idx % 2 === 0) ? ' class="row-even"' : '';
            echo '<tr' . $rowCls . '>';
            echo '<td class="text-center font-bold" style="mso-number-format:\'\@\'">' . h((string)($r['work_order_number'] ?? '')) . '</td>';
            echo '<td class="text-center" style="mso-number-format:\'\@\'">' . h((string)($r['cost_center'] ?? '')) . '</td>';
            echo '<td>' . h((string)($r['operator_name'] ?? '')) . '</td>';
            echo '<td>' . h((string)($r['machine_label'] ?? '')) . '</td>';
            echo '<td class="num-int">' . (int)round($req) . '</td>';
            echo '<td class="num-int">' . (int)round($prod) . '</td>';
            echo '<td class="num-int highlight-red">' . (int)round($units) . '</td>';
            echo '<td class="num-dec">' . round($kg, 2) . ' kg</td>';
            echo '<td class="text-right font-bold' . ($rate !== null && $rate > 5.0 ? ' highlight-red' : '') . '">' . ($rate !== null ? (number_format($rate, 2, ',', '.') . '%') : 'N/D') . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';

    echo '</body></html>';
    $html = ob_get_clean();
    SimpleXlsx::streamHtml($filename, $html, 'Detalle Merma');
}

/**
 * Exporta el listado de Órdenes de Trabajo con Merma Crítica (> 5%) a Excel.
 */
function unibagOutputCriticalWasteExcel(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    $startStr = $filters['start']->format('Y-m-d H:i:s');
    $endStr = $filters['end']->format('Y-m-d H:i:s');
    $activeFilterLabel = (string)$filters['active_filter_label'];

    $criticalWasteOrders = (array)unibagDashboardCached('critical-waste', $startStr, $endStr, static fn() => $service->getCriticalWasteWorkOrders($startStr, $endStr, 5.0));

    $filename = 'alerta-merma-critica-dashboard-' . date('Ymd-His') . '.xlsx';
    ob_start();

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Merma Crítica</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>';
    echo 'body{font-family:Calibri,Arial,sans-serif;font-size:11pt;color:#1e293b;background:#ffffff}';
    echo '.report-header{margin-bottom:16px}';
    echo '.report-title{font-size:16pt;font-weight:bold;color:#991b1b}';
    echo '.report-meta{font-size:10pt;color:#475569;margin-top:4px}';
    echo 'table{border-collapse:collapse;width:100%}';
    echo 'th{background:#991b1b;color:#ffffff;font-weight:bold;font-size:10pt;padding:8px 10px;border:1px solid #7f1d1d;vertical-align:middle}';
    echo 'td{font-size:10pt;padding:6px 10px;border:1px solid #cbd5e1;vertical-align:middle;color:#1e293b}';
    echo '.num-int{mso-number-format:"\#\,\#\#0";text-align:right}';
    echo '.text-right{text-align:right}';
    echo '.text-center{text-align:center}';
    echo '.font-bold{font-weight:bold}';
    echo '.highlight-red{color:#dc2626;font-weight:bold}';
    echo '.row-even{background:#fef2f2}';
    echo '</style></head><body>';

    echo '<div class="report-header">';
    echo '<div class="report-title">Alerta de Calidad — OTs con Merma Crítica (> 5.0%)</div>';
    echo '<div class="report-meta"><strong>Filtro aplicado:</strong> ' . h($activeFilterLabel) . ' | <strong>Total órdenes detectadas:</strong> ' . count($criticalWasteOrders) . ' | <strong>Generado el:</strong> ' . date('d/m/Y H:i') . '</div>';
    echo '</div>';

    echo '<table><thead><tr>';
    echo '<th class="text-center">OT</th>';
    echo '<th class="text-center">Centro Costo</th>';
    echo '<th style="text-align:left">Cliente</th>';
    echo '<th style="text-align:left">Proceso</th>';
    echo '<th style="text-align:left">Máquina</th>';
    echo '<th style="text-align:right">U. Buenas</th>';
    echo '<th style="text-align:right">U. Merma</th>';
    echo '<th style="text-align:right">Total Producido</th>';
    echo '<th class="text-center">% Merma</th>';
    echo '</tr></thead><tbody>';

    if ($criticalWasteOrders === []) {
        echo '<tr><td colspan="9" class="text-center font-bold" style="padding:16px;color:#16a34a">✓ Excelente: No se registraron órdenes de trabajo con merma superior al 5% en este período.</td></tr>';
    } else {
        $idx = 0;
        foreach ($criticalWasteOrders as $ot) {
            $idx++;
            $wRate = (float)$ot['waste_rate'];
            $rowCls = ($idx % 2 === 0) ? ' class="row-even"' : '';
            echo '<tr' . $rowCls . '>';
            echo '<td class="text-center font-bold" style="mso-number-format:\'\@\'">' . h((string)$ot['ot_number']) . '</td>';
            echo '<td class="text-center" style="mso-number-format:\'\@\'">' . h((string)$ot['cost_center']) . '</td>';
            echo '<td>' . h((string)$ot['customer_name']) . '</td>';
            echo '<td>' . h((string)$ot['process_name']) . '</td>';
            echo '<td>' . h((string)$ot['machine_name']) . '</td>';
            echo '<td class="num-int">' . (int)round((float)$ot['good_units']) . '</td>';
            echo '<td class="num-int highlight-red">' . (int)round((float)$ot['waste_units']) . '</td>';
            echo '<td class="num-int font-bold">' . (int)round((float)$ot['total_units']) . '</td>';
            echo '<td class="text-center font-bold" style="color:' . ($wRate >= 10 ? '#b91c1c' : '#c2410c') . '">' . number_format($wRate, 2, ',', '.') . '%</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
    echo '</body></html>';
    $html = ob_get_clean();
    SimpleXlsx::streamHtml($filename, $html, 'Merma Critica');
}

/**
 * Exporta el reporte de ocupación de bodegas a Excel (XLS vía HTML).
 *
 * Características:
 * - Aplica los mismos filtros de período/rango del dashboard para mantener consistencia.
 * - Soporta filtro adicional por bodega (warehouse_filter=CODE o ALL).
 * - Calcula totales (stock, bobinas, cajas, pallets, peso) para mostrar resumen.
 *
 * ---
 *
 * Exports the warehouse occupancy report to Excel (HTML-based XLS).
 *
 * Features:
 * - Uses the same period/range filters as the dashboard for consistency.
 * - Supports an additional warehouse filter (warehouse_filter=CODE or ALL).
 * - Computes totals (stock, rolls, boxes, pallets, weight) to display a summary.
 */
function unibagOutputWarehouseOccupancyExcel(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    $service->getProductionDashboardKpis(
        $filters['start']->format('Y-m-d H:i:s'),
        $filters['end']->format('Y-m-d H:i:s')
    );
    $warehouses = $service->stockSummaryWithCapacities();
    $warehouseFilterCode = null;
    if (isset($_GET['warehouse_filter']) && is_string($_GET['warehouse_filter'])) {
        $rawWh = trim($_GET['warehouse_filter']);
        if ($rawWh !== '' && strtoupper($rawWh) !== 'ALL') {
            $warehouseFilterCode = $rawWh;
        }
    }
    $filteredWarehouses = [];
    foreach ($warehouses as $warehouseRow) {
        $rowWhCode = trim((string)($warehouseRow['warehouse_code'] ?? ''));
        if ($warehouseFilterCode !== null && $warehouseFilterCode !== $rowWhCode) {
            continue;
        }
        $filteredWarehouses[] = $warehouseRow;
    }

    $generatedAt = (new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->format('d/m/Y H:i:s');
    $filterLabel = (string)($filters['active_filter_label'] ?? 'Sin filtro de período');
    if ($warehouseFilterCode !== null) {
        $filterLabel .= ' · Bodega ' . $warehouseFilterCode;
    } else {
        $filterLabel .= ' · Todas las bodegas';
    }

    $totalStock = 0.0;
    $totalRolls = 0;
    $totalBoxes = 0;
    $totalPallets = 0;
    $totalWeightKg = 0.0;
    foreach ($filteredWarehouses as $summaryRow) {
        $totalStock += (float)($summaryRow['stock_units_total'] ?? 0);
        $totalRolls += (int)($summaryRow['rolls_count'] ?? 0);
        $totalBoxes += (int)($summaryRow['boxes_count'] ?? 0);
        $totalPallets += (int)($summaryRow['pallets_count'] ?? 0);
        $totalWeightKg += (float)($summaryRow['total_weight_kg'] ?? 0);
    }
    $occupancyValues = [];
    foreach ($filteredWarehouses as $summaryRow) {
        if (($summaryRow['occupancy_percent'] ?? null) !== null) {
            $occupancyValues[] = (float)$summaryRow['occupancy_percent'];
        }
    }
    $avgOccupancy = $occupancyValues !== [] ? round(array_sum($occupancyValues) / count($occupancyValues), 2) : null;
    $maxOccupancy = $occupancyValues !== [] ? round(max($occupancyValues), 2) : null;

    $filename = 'informe-ocupacion-bodegas-' . (new DateTimeImmutable('now'))->format('Ymd-His') . '.xlsx';
    ob_start();

    echo '<html><head><meta charset="UTF-8"><style>';
    echo 'body{font-family:Arial,sans-serif;color:#0f172a}';
    echo '.title{font-size:22px;font-weight:700;color:#0f172a}';
    echo '.sub{font-size:12px;color:#475569;margin-bottom:14px}';
    echo '.meta{margin:8px 0 18px 0;font-size:12px;color:#334155}';
    echo '.summary,.report,.detail{border-collapse:collapse;width:100%;margin-bottom:18px}';
    echo '.summary td,.summary th,.report td,.report th,.detail td,.detail th{border:1px solid #cbd5e1;padding:8px 10px}';
    echo '.summary th,.report th,.detail th{background:#0f172a;color:#fff;text-align:left;font-size:12px}';
    echo '.report tr:nth-child(even) td,.detail tr:nth-child(even) td{background:#f8fafc}';
    echo '.num-int{mso-number-format:"\#\,\#\#0";text-align:right}';
    echo '.num-dec3{mso-number-format:"\#\,\#\#0\.000";text-align:right}';
    echo '.section{font-size:16px;font-weight:700;margin:22px 0 8px 0;color:#0f172a;border-bottom:2px solid #e2e8f0;padding-bottom:4px}';
    echo '.subsection{font-size:14px;font-weight:700;margin:18px 0 8px 0;color:#1d4ed8}';
    echo '.empty{font-size:12px;color:#64748b}';
    echo '</style></head><body>';
    echo '<div class="title">Informe ocupación de bodegas</div>';
    echo '<div class="sub">Unibag · Panel ERP</div>';
    echo '<div class="meta"><strong>Filtro aplicado:</strong> ' . h($filterLabel) . '<br><strong>Generado:</strong> ' . h($generatedAt) . '</div>';

    echo '<div class="section">Resumen ejecutivo</div>';
    echo '<table class="summary"><tr><th>Indicador</th><th>Valor</th></tr>';
    echo '<tr><td>Bodegas informadas</td><td>' . count($filteredWarehouses) . '</td></tr>';
    echo '<tr><td>Unidades en stock (equivalentes)</td><td class="num-int">' . (int)round($totalStock) . '</td></tr>';
    echo '<tr><td>Rollos totales</td><td class="num-int">' . (int)$totalRolls . '</td></tr>';
    echo '<tr><td>Cajas totales</td><td class="num-int">' . (int)$totalBoxes . '</td></tr>';
    echo '<tr><td>Pallets totales</td><td class="num-int">' . (int)$totalPallets . '</td></tr>';
    echo '<tr><td>Peso total en rollos</td><td class="num-dec3">' . round($totalWeightKg, 3) . ' kg</td></tr>';
    echo '<tr><td>Ocupación promedio</td><td>' . ($avgOccupancy !== null ? h(number_format($avgOccupancy, 2, '.', '') . '%') : 'Sin capacidad configurada') . '</td></tr>';
    echo '<tr><td>Ocupación máxima</td><td>' . ($maxOccupancy !== null ? h(number_format($maxOccupancy, 2, '.', '') . '%') : 'Sin capacidad configurada') . '</td></tr>';
    echo '</table>';

    echo '<div class="section">Resumen por bodega</div>';
    echo '<table class="report"><tr><th>Código</th><th>Nombre</th><th>Stock (unid.)</th><th>Rollos</th><th>Cajas</th><th>Pallets</th><th>Peso rollos (kg)</th><th>Ocupación</th><th>Cap. pallets</th><th>Cap. unidades</th></tr>';
    foreach ($filteredWarehouses as $summaryRow) {
        $occValue = ($summaryRow['occupancy_percent'] ?? null) !== null ? number_format((float)$summaryRow['occupancy_percent'], 2, '.', '') . '%' : 'Sin capacidad';
        $capPallets = (int)($summaryRow['capacity_pallets'] ?? 0) > 0 ? (string)(int)$summaryRow['capacity_pallets'] : 'Sin configurar';
        $capUnits = (float)($summaryRow['capacity_units_total'] ?? 0) > 0 ? (int)round((float)$summaryRow['capacity_units_total']) : 'Sin configurar';
        echo '<tr>';
        echo '<td>' . h((string)($summaryRow['warehouse_code'] ?? '')) . '</td>';
        echo '<td>' . h((string)($summaryRow['warehouse_name'] ?? '')) . '</td>';
        echo '<td class="num-int">' . (int)round((float)($summaryRow['stock_units_total'] ?? 0)) . '</td>';
        echo '<td class="num-int">' . (int)($summaryRow['rolls_count'] ?? 0) . '</td>';
        echo '<td class="num-int">' . (int)($summaryRow['boxes_count'] ?? 0) . '</td>';
        echo '<td class="num-int">' . (int)($summaryRow['pallets_count'] ?? 0) . '</td>';
        echo '<td class="num-dec3">' . round((float)($summaryRow['total_weight_kg'] ?? 0), 3) . '</td>';
        echo '<td>' . h($occValue) . '</td>';
        echo '<td>' . h($capPallets) . '</td>';
        echo '<td class="num-int">' . h((string)$capUnits) . '</td>';
        echo '</tr>';
    }
    echo '</table>';

    echo '<div class="section">Detalle por bodega</div>';
    foreach ($filteredWarehouses as $summaryRow) {
        $whCodeRaw = trim((string)($summaryRow['warehouse_code'] ?? ''));
        $whCodeInt = $whCodeRaw !== '' && is_numeric($whCodeRaw) ? (int)$whCodeRaw : 0;
        $whTitle = trim((string)($summaryRow['warehouse_code'] ?? '') . ' · ' . (string)($summaryRow['warehouse_name'] ?? ''));
        $rolls = $whCodeInt > 0 ? $service->listRollsByWarehouseCode($whCodeInt, 1000) : [];
        $pallets = $whCodeInt > 0 ? $service->listPalletsByWarehouseCode($whCodeInt, 1000) : [];
        $boxes = $whCodeInt > 0 ? $service->listBoxesByWarehouseCode($whCodeInt, 1000) : [];

        echo '<div class="subsection">Bodega ' . h($whTitle) . '</div>';
        echo '<table class="summary" style="max-width:960px"><tr><th>Indicador</th><th>Valor</th></tr>';
        $occValue = ($summaryRow['occupancy_percent'] ?? null) !== null ? number_format((float)$summaryRow['occupancy_percent'], 2, '.', '') . '%' : 'Sin capacidad';
        echo '<tr><td>Unidades en stock</td><td class="num-int">' . (int)round((float)($summaryRow['stock_units_total'] ?? 0)) . '</td></tr>';
        echo '<tr><td>Ocupación</td><td>' . h($occValue) . '</td></tr>';
        echo '<tr><td>Rollos</td><td class="num-int">' . (int)($summaryRow['rolls_count'] ?? 0) . '</td></tr>';
        echo '<tr><td>Cajas</td><td class="num-int">' . (int)($summaryRow['boxes_count'] ?? 0) . '</td></tr>';
        echo '<tr><td>Pallets</td><td class="num-int">' . (int)($summaryRow['pallets_count'] ?? 0) . '</td></tr>';
        echo '<tr><td>Peso en rollos</td><td class="num-dec3">' . round((float)($summaryRow['total_weight_kg'] ?? 0), 3) . ' kg</td></tr>';
        echo '</table>';

        echo '<div class="subsection">Bobinas (rollos) en bodega</div>';
        if ($rolls === []) {
            echo '<div class="empty">No hay bobinas ubicadas en esta bodega.</div>';
        } else {
            echo '<table class="detail"><tr><th>Código</th><th>SKU / Descripción</th><th>Peso (kg)</th><th>Metros</th><th>Estado</th><th>OT actual</th><th>Recibido</th></tr>';
            foreach ($rolls as $row) {
                echo '<tr>';
                echo '<td>' . h((string)($row['roll_code'] ?? '')) . '</td>';
                echo '<td>' . h((string)($row['sku_code'] ?? '-')) . '<br><span class="empty">' . h((string)($row['sku_description'] ?? '')) . '</span></td>';
                echo '<td>' . h(number_format((float)($row['weight_kg'] ?? 0), 3, '.', ',')) . '</td>';
                echo '<td>' . h((string)($row['meters'] ?? '-')) . '</td>';
                echo '<td>' . h((string)($row['status'] ?? '-')) . '</td>';
                echo '<td>' . h((string)($row['work_order_code'] ?? '-')) . '</td>';
                echo '<td>' . h((string)($row['created_at'] ?? '-')) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }

        echo '<div class="subsection">Pallets en bodega</div>';
        if ($pallets === []) {
            echo '<div class="empty">No hay pallets almacenados en esta bodega.</div>';
        } else {
            echo '<table class="detail"><tr><th>Pallet</th><th>SKU final</th><th>Unidades</th><th>Cajas</th><th>Destino / Pedido</th><th>OT</th><th>Creado</th></tr>';
            foreach ($pallets as $row) {
                $destinationLabel = match ((string)($row['destination_mode'] ?? '')) {
                    'CUSTOMER_ORDER' => 'Orden cliente',
                    default => 'Stock',
                };
                echo '<tr>';
                echo '<td>' . h((string)($row['pallet_code'] ?? '')) . '</td>';
                echo '<td>' . h((string)($row['final_sku'] ?? '-')) . '</td>';
                echo '<td>' . h(number_format((float)($row['units_total'] ?? 0), 0, '.', ',')) . '</td>';
                echo '<td>' . h(number_format((int)($row['box_count'] ?? 0), 0, '.', ',')) . '</td>';
                echo '<td>' . h($destinationLabel) . '<br><span class="empty">' . h((string)($row['customer_order_ref'] ?? '')) . '</span></td>';
                echo '<td>' . h((string)($row['ot_code'] ?? '-')) . '</td>';
                echo '<td>' . h((string)($row['created_at'] ?? '-')) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }

        echo '<div class="subsection">Cajas en bodega</div>';
        if ($boxes === []) {
            echo '<div class="empty">No hay cajas de forma directa en esta bodega (la mayoría estarán en pallets).</div>';
        } else {
            echo '<table class="detail"><tr><th>Caja</th><th>SKU final</th><th>Unidades</th><th>Pallet</th><th>Bobina origen</th><th>Destino / Pedido</th><th>OT</th><th>Creada</th></tr>';
            foreach ($boxes as $row) {
                $destinationLabel = match ((string)($row['destination_mode'] ?? '')) {
                    'CUSTOMER_ORDER' => 'Orden cliente',
                    default => 'Stock',
                };
                echo '<tr>';
                echo '<td>' . h((string)($row['box_code'] ?? '')) . '</td>';
                echo '<td>' . h((string)($row['final_sku'] ?? '-')) . '</td>';
                echo '<td>' . h(number_format((float)($row['units_qty'] ?? 0), 0, '.', ',')) . '</td>';
                echo '<td>' . h((string)($row['pallet_code'] ?? '-')) . '</td>';
                echo '<td>' . h((string)($row['source_roll_code'] ?? '-')) . '</td>';
                echo '<td>' . h($destinationLabel) . '<br><span class="empty">' . h((string)($row['customer_order_ref'] ?? '')) . '</span></td>';
                echo '<td>' . h((string)($row['ot_code'] ?? '-')) . '</td>';
                echo '<td>' . h((string)($row['created_at'] ?? '-')) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
    }

    echo '</body></html>';
    $html = ob_get_clean();
    SimpleXlsx::streamHtml($filename, $html, 'Ocupacion Bodegas');
}

/**
 * Renderiza el dashboard de producción (web).
 *
 * Muestra:
 * - KPIs agregados (producidas, pendientes, despachadas, merma, semielaboradas).
 * - Tablas y tarjetas con OTs relevantes.
 * - Filtros por período operativo 26–25 o rango.
 * - Filtro por bodega (ocupación), con export a Excel.
 *
 * $embeddedInErp permite reutilizar la vista como “home” del área ERP.
 *
 * ---
 *
 * Renders the production dashboard (web).
 *
 * Displays:
 * - Aggregated KPIs (produced, pending, dispatched, waste, semi-finished).
 * - Tables and cards with relevant work orders.
 * - Filters by operational period 26–25 or explicit range.
 * - Warehouse (occupancy) filter, with Excel export.
 *
 * $embeddedInErp allows reusing this view as the ERP area “home”.
 */
function unibagRenderProductionDashboardPage(ReceptionService $service, bool $embeddedInErp = false): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    /** @var DateTimeImmutable $start */
    $start = $filters['start'];
    /** @var DateTimeImmutable $end */
    $end = $filters['end'];
    $defaultFilterType = (string)$filters['filter_type'];
    $periodYm = (string)$filters['period'];
    $rangeStartInput = (string)$filters['start_date'];
    $rangeEndInput = (string)$filters['end_date'];
    $activeFilterLabel = (string)$filters['active_filter_label'];
    $baseDashboardFilterParams = [
        'filter_type' => $defaultFilterType,
        'period' => $periodYm,
        'start_date' => $rangeStartInput,
        'end_date' => $rangeEndInput,
    ];

    $kpis = $service->getProductionDashboardKpis($start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));

    $producedUnits = (float)($kpis['produced_units'] ?? 0);
    $pendingUnits = (float)($kpis['pending_units'] ?? 0);
    $dispatchedUnits = (float)($kpis['dispatched_units'] ?? 0);
    $semiRollCount = (int)($kpis['semi_rolls']['count'] ?? 0);
    $wastePercent = (float)($kpis['waste']['percent'] ?? 0);
    $wasteKg = (float)($kpis['waste']['waste_kg'] ?? 0);
    $processedKg = (float)($kpis['waste']['processed_kg'] ?? 0);
    $workOrders = is_array($kpis['work_orders'] ?? null) ? $kpis['work_orders'] : [];
    $semiRows = is_array($kpis['semi_rolls']['rows'] ?? null) ? $kpis['semi_rolls']['rows'] : [];
    $warehouses = is_array($kpis['warehouses'] ?? null) ? $kpis['warehouses'] : [];

    $warehouseFilterInput = trim((string)($_GET['warehouse_filter'] ?? 'ALL'));
    $availableWarehouseCodes = [];
    foreach ($warehouses as $whRow) {
        $whCode = trim((string)($whRow['warehouse_code'] ?? ''));
        if ($whCode !== '') {
            $availableWarehouseCodes[$whCode] = true;
        }
    }
    $warehouseFilterCode = null;
    if ($warehouseFilterInput !== '' && strtoupper($warehouseFilterInput) !== 'ALL') {
        if (isset($availableWarehouseCodes[$warehouseFilterInput])) {
            $warehouseFilterCode = $warehouseFilterInput;
        }
    }

    $filteredWarehouses = $warehouses;
    if ($warehouseFilterCode !== null) {
        $filteredWarehouses = array_values(array_filter($filteredWarehouses, static function (array $row) use ($warehouseFilterCode): bool {
            return trim((string)($row['warehouse_code'] ?? '')) === $warehouseFilterCode;
        }));
    }

    $bestOccupancy = 0.0;
    $topWarehouse = null;
    foreach ($filteredWarehouses as $warehouseRow) {
        $warehouseOccupancy = (float)($warehouseRow['occupancy_percent'] ?? 0);
        if ($warehouseOccupancy >= $bestOccupancy) {
            $bestOccupancy = $warehouseOccupancy;
            $topWarehouse = $warehouseRow;
        }
    }
    $warehouseOptionsHtml = '<option value="ALL"' . ($warehouseFilterCode === null ? ' selected' : '') . '>Todas las bodegas</option>';
    foreach ($warehouses as $whOptionRow) {
        $whOptionCode = trim((string)($whOptionRow['warehouse_code'] ?? ''));
        if ($whOptionCode === '') {
            continue;
        }
        $whOptionLabel = $whOptionCode . ' · ' . trim((string)($whOptionRow['warehouse_name'] ?? ''));
        $warehouseOptionsHtml .= '<option value="' . h($whOptionCode) . '"' . ($warehouseFilterCode === $whOptionCode ? ' selected' : '') . '>' . h($whOptionLabel) . '</option>';
    }

    $warehouseDetailMap = [];
    $renderWarehouseRollTable = static function (array $rows, int $previewLimit = 10): string {
        $rows = array_values($rows);
        if ($rows === []) {
            return '<div class="erp-prod-empty">No hay bobinas ubicadas en esta bodega.</div>';
        }
        $previewRows = array_slice($rows, 0, $previewLimit);
        $html = '<div class="erp-prod-table-wrap"><table class="erp-prod-table"><thead><tr><th>Bobina</th><th>SKU</th><th>Peso</th><th>Metros</th><th>OT actual</th></tr></thead><tbody>';
        foreach ($previewRows as $row) {
            $html .= '<tr>';
            $html .= '<td><a class="erp-prod-code" href="/rolls/' . (int)($row['id'] ?? 0) . '">' . h((string)($row['roll_code'] ?? '')) . '</a><div class="erp-prod-muted">Recibido: ' . h((string)($row['created_at'] ?? '-')) . '</div></td>';
            $html .= '<td><div class="erp-prod-code">' . h((string)($row['sku_code'] ?? '-')) . '</div><div class="erp-prod-muted">' . h((string)($row['sku_description'] ?? '')) . '</div></td>';
            $html .= '<td>' . h(number_format((float)($row['weight_kg'] ?? 0), 3, '.', '')) . ' kg</td>';
            $html .= '<td>' . h((string)($row['meters'] ?? '-')) . '</td>';
            $html .= '<td><strong>' . h((string)($row['work_order_code'] ?? '-')) . '</strong></td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
        if (count($rows) > $previewLimit) {
            $html .= '<div class="erp-prod-muted" style="margin-top:8px">Mostrando ' . (int)$previewLimit . ' de ' . count($rows) . ' rollos. Ir a inventario para ver el total.</div>';
        }
        return $html;
    };
    $renderWarehousePalletTable = static function (array $rows, int $previewLimit = 10): string {
        $rows = array_values($rows);
        if ($rows === []) {
            return '<div class="erp-prod-empty">No hay pallets almacenados en esta bodega.</div>';
        }
        $previewRows = array_slice($rows, 0, $previewLimit);
        $html = '<div class="erp-prod-table-wrap"><table class="erp-prod-table"><thead><tr><th>Pallet</th><th>SKU final</th><th>Unidades</th><th>Cajas</th><th>OT</th></tr></thead><tbody>';
        foreach ($previewRows as $row) {
            $html .= '<tr>';
            $html .= '<td><a class="erp-prod-code" href="/pallets/' . (int)($row['id'] ?? 0) . '">' . h((string)($row['pallet_code'] ?? '')) . '</a><div class="erp-prod-muted">Creado: ' . h((string)($row['created_at'] ?? '-')) . '</div></td>';
            $html .= '<td>' . h((string)($row['final_sku'] ?? '-')) . '</td>';
            $html .= '<td><strong>' . h(number_format((float)($row['units_total'] ?? 0), 0, '.', '')) . '</strong></td>';
            $html .= '<td>' . h((string)($row['box_count'] ?? 0)) . '</td>';
            $html .= '<td><strong>' . h((string)($row['ot_code'] ?? '-')) . '</strong></td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
        if (count($rows) > $previewLimit) {
            $html .= '<div class="erp-prod-muted" style="margin-top:8px">Mostrando ' . (int)$previewLimit . ' de ' . count($rows) . ' pallets. Ir a inventario para ver el total.</div>';
        }
        return $html;
    };
    $renderWarehouseBoxTable = static function (array $rows, int $previewLimit = 10): string {
        $rows = array_values($rows);
        if ($rows === []) {
            return '<div class="erp-prod-empty">No hay cajas almacenadas de forma directa en esta bodega.</div>';
        }
        $previewRows = array_slice($rows, 0, $previewLimit);
        $html = '<div class="erp-prod-table-wrap"><table class="erp-prod-table"><thead><tr><th>Caja</th><th>SKU final</th><th>Unidades</th><th>Pallet</th><th>OT</th></tr></thead><tbody>';
        foreach ($previewRows as $row) {
            $html .= '<tr>';
            $html .= '<td><a class="erp-prod-code" href="/boxes/' . (int)($row['id'] ?? 0) . '">' . h((string)($row['box_code'] ?? '')) . '</a><div class="erp-prod-muted">Creada: ' . h((string)($row['created_at'] ?? '-')) . '</div></td>';
            $html .= '<td>' . h((string)($row['final_sku'] ?? '-')) . '</td>';
            $html .= '<td><strong>' . h(number_format((float)($row['units_qty'] ?? 0), 0, '.', '')) . '</strong></td>';
            $html .= '<td>' . h((string)($row['pallet_code'] ?? '-')) . '</td>';
            $html .= '<td><strong>' . h((string)($row['ot_code'] ?? '-')) . '</strong></td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
        if (count($rows) > $previewLimit) {
            $html .= '<div class="erp-prod-muted" style="margin-top:8px">Mostrando ' . (int)$previewLimit . ' de ' . count($rows) . ' cajas. Ir a inventario para ver el total.</div>';
        }
        return $html;
    };
    foreach ($filteredWarehouses as $warehouseSummaryRow) {
        $whCode = trim((string)($warehouseSummaryRow['warehouse_code'] ?? ''));
        if ($whCode === '' || !is_numeric($whCode)) {
            continue;
        }
        $whCodeInt = (int)$whCode;
        $warehouseDetailMap[$whCode] = [
            'summary' => $warehouseSummaryRow,
            'modal_id' => 'erp-modal-warehouse-' . $whCode,
            'rolls' => $service->listRollsByWarehouseCode($whCodeInt, 50),
            'pallets' => $service->listPalletsByWarehouseCode($whCodeInt, 50),
            'boxes' => $service->listBoxesByWarehouseCode($whCodeInt, 50),
        ];
    }

    $dashboardFilterParams = $baseDashboardFilterParams;
    if ($warehouseFilterCode !== null) {
        $dashboardFilterParams['warehouse_filter'] = $warehouseFilterCode;
    }
    $buildExcelUrl = static function (string $metric) use ($dashboardFilterParams): string {
        return withQuery('/reports/production-dashboard/excel', array_merge($dashboardFilterParams, ['metric' => $metric]));
    };
    $warehouseFilterHiddenInputs = '';
    foreach ($baseDashboardFilterParams as $paramName => $paramValue) {
        $warehouseFilterHiddenInputs .= '<input type="hidden" name="' . h((string)$paramName) . '" value="' . h((string)$paramValue) . '">';
    }
    $plannedUnits = $producedUnits + $pendingUnits;
    $completionPercent = $plannedUnits > 0 ? round(($producedUnits / $plannedUnits) * 100, 2) : 0.0;
    $dispatchCoverage = $producedUnits > 0 ? round(($dispatchedUnits / $producedUnits) * 100, 2) : 0.0;
    $pendingVsProduced = $producedUnits > 0 ? round(($pendingUnits / $producedUnits) * 100, 2) : 0.0;
    $yieldPercent = $processedKg > 0 ? round(100 - $wastePercent, 2) : 0.0;
    $netProcessedKg = max(0.0, round($processedKg - $wasteKg, 3));
    $semiWeightTotal = 0.0;
    $semiMetersTotal = 0.0;
    $semiEstimatedUnitsTotal = 0.0;
    foreach ($semiRows as $semiRow) {
        $semiWeightTotal += (float)($semiRow['weight_kg'] ?? 0);
        $semiMetersTotal += (float)($semiRow['meters'] ?? 0);
        if (($semiRow['estimated_units'] ?? null) !== null) {
            $semiEstimatedUnitsTotal += (float)$semiRow['estimated_units'];
        }
    }
    $topWarehouseLabel = $topWarehouse !== null
        ? trim((string)($topWarehouse['warehouse_code'] ?? '') . ' · ' . (string)($topWarehouse['warehouse_name'] ?? ''))
        : 'Sin dato';
    $topWarehouseOccupancyText = $topWarehouse !== null && ($topWarehouse['occupancy_percent'] ?? null) !== null
        ? number_format((float)$topWarehouse['occupancy_percent'], 2, '.', '') . '%'
        : 'Sin capacidad';

    $renderMiniStat = static function (string $label, string $value, string $note = ''): string {
        $html = '<div class="erp-prod-mini"><div class="erp-prod-mini-label">' . h($label) . '</div><div class="erp-prod-mini-value">' . h($value) . '</div>';
        if ($note !== '') {
            $html .= '<div class="erp-prod-muted">' . h($note) . '</div>';
        }
        $html .= '</div>';

        return $html;
    };
    $renderModalSection = static function (string $title, string $content): string {
        return '<div class="erp-prod-modal-section"><div class="erp-prod-modal-section-title">' . h($title) . '</div>' . $content . '</div>';
    };
    $renderStatusChip = static function (string $label): string {
        $normalized = strtolower(trim($label));
        $className = 'erp-prod-chip';
        if (in_array($normalized, ['terminada', 'cerrada'], true)) {
            $className .= ' erp-prod-chip-success';
        } elseif (in_array($normalized, ['con avance', 'en produccion'], true)) {
            $className .= ' erp-prod-chip-warning';
        } elseif ($normalized === 'pendiente') {
            $className .= ' erp-prod-chip-neutral';
        }

        return '<span class="' . h($className) . '">' . h($label) . '</span>';
    };
    $renderMetricWorkOrderSection = static function (string $mode, array $rows) use ($renderMiniStat, $renderModalSection, $renderStatusChip): string {
        $filteredRows = unibagProductionDashboardMetricRows($mode, $rows);

        $summaryHtml = '';
        $tableHtml = '';
        $note = '';

        if ($filteredRows === []) {
            $emptyMessage = match ($mode) {
                'produced' => 'No hay producción por OT para mostrar en este período.',
                'pending' => 'No hay pendientes por OT en este período.',
                'dispatched' => 'No hay despachos por OT dentro del período seleccionado.',
                'waste' => 'No hay merma registrada por OT en este período.',
                'processed' => 'No hay kg procesados registrados por OT en este período.',
                'semi' => 'No hay semielaboradas agrupadas por OT para mostrar.',
                default => 'No hay datos por OT para mostrar.',
            };

            return $renderModalSection('Detalle por OT', '<div class="erp-prod-empty">' . h($emptyMessage) . '</div>');
        }

        $previewRows = array_slice($filteredRows, 0, 12);

        if ($mode === 'produced') {
            $totalProduced = array_sum(array_map(static fn(array $row): float => (float)($row['produced_units'] ?? 0), $filteredRows));
            $totalPending = array_sum(array_map(static fn(array $row): float => (float)($row['pending_units'] ?? 0), $filteredRows));
            $completedCount = count(array_filter($filteredRows, static fn(array $row): bool => (string)($row['dashboard_status'] ?? '') === 'Terminada'));
            $summaryHtml = '<div class="erp-prod-mini-grid">'
                . $renderMiniStat('OTs con producción', (string)count($filteredRows), 'Órdenes visibles en este indicador')
                . $renderMiniStat('Unidades producidas', number_format($totalProduced, 0, '.', ''), 'Total por OT del período')
                . $renderMiniStat('Unidades pendientes', number_format($totalPending, 0, '.', ''), 'Restante estimado por OT')
                . $renderMiniStat('OTs terminadas', (string)$completedCount, 'Meta cubierta o cerradas')
                . '</div>';
            $tableHtml = '<div class="erp-prod-table-wrap"><table class="erp-prod-table"><thead><tr><th>OT</th><th>Máquina</th><th>Estado</th><th>Objetivo</th><th>Producidas</th><th>Pendientes</th><th>Avance</th></tr></thead><tbody>';
            foreach ($previewRows as $row) {
                $tableHtml .= '<tr>';
                $tableHtml .= '<td><div class="erp-prod-code">' . h((string)($row['ot_code'] ?? ('OT #' . (int)($row['id'] ?? 0)))) . '</div><div class="erp-prod-muted">' . h((string)($row['sku_final'] ?? '-')) . '</div></td>';
                $tableHtml .= '<td>' . h((string)($row['machine_name'] ?? '-')) . '</td>';
                $tableHtml .= '<td>' . $renderStatusChip((string)($row['dashboard_status'] ?? '-')) . '<div class="erp-prod-muted">Estado sistema: ' . h((string)($row['status'] ?? '-')) . '</div></td>';
                $tableHtml .= '<td>' . h(number_format((float)($row['target_qty'] ?? 0), 0, '.', '')) . '</td>';
                $tableHtml .= '<td><strong>' . h(number_format((float)($row['produced_units'] ?? 0), 0, '.', '')) . '</strong><div class="erp-prod-muted">Cajas: ' . h((string)($row['boxes_count'] ?? 0)) . '</div></td>';
                $tableHtml .= '<td>' . h(number_format((float)($row['pending_units'] ?? 0), 0, '.', '')) . '</td>';
                $tableHtml .= '<td class="erp-prod-occupancy"><strong>' . h(number_format((float)($row['progress_percent'] ?? 0), 2, '.', '')) . '%</strong><div class="erp-prod-bar"><div class="erp-prod-bar-fill" style="width:' . h(number_format(max(0.0, min(100.0, (float)($row['progress_percent'] ?? 0))), 2, '.', '')) . '%"></div></div></td>';
                $tableHtml .= '</tr>';
            }
            $tableHtml .= '</tbody></table></div>';
            $note = 'Esta tabla muestra por OT cuáles ya avanzaron en producción, cuánto llevan producido y cuánto volumen sigue pendiente.';
        } elseif ($mode === 'pending') {
            $totalPending = array_sum(array_map(static fn(array $row): float => (float)($row['pending_units'] ?? 0), $filteredRows));
            $activeCount = count(array_filter($filteredRows, static fn(array $row): bool => in_array((string)($row['dashboard_status'] ?? ''), ['Con avance', 'En produccion', 'Pendiente', 'En corte'], true)));
            $summaryHtml = '<div class="erp-prod-mini-grid">'
                . $renderMiniStat('OTs con pendiente', (string)count($filteredRows), 'Con unidades aún por completar')
                . $renderMiniStat('Pendiente total', number_format($totalPending, 0, '.', ''), 'Restante estimado del período')
                . $renderMiniStat('OTs activas', (string)$activeCount, 'Con trabajo aún abierto')
                . $renderMiniStat('Mayor pendiente', number_format((float)($filteredRows[0]['pending_units'] ?? 0), 0, '.', ''), (string)($filteredRows[0]['ot_code'] ?? '-'))
                . '</div>';
            $tableHtml = '<div class="erp-prod-table-wrap"><table class="erp-prod-table"><thead><tr><th>OT</th><th>Máquina</th><th>Estado</th><th>Objetivo</th><th>Producidas</th><th>Pendientes</th><th>Avance</th></tr></thead><tbody>';
            foreach ($previewRows as $row) {
                $tableHtml .= '<tr>';
                $tableHtml .= '<td><div class="erp-prod-code">' . h((string)($row['ot_code'] ?? ('OT #' . (int)($row['id'] ?? 0)))) . '</div><div class="erp-prod-muted">' . h((string)($row['sku_final'] ?? '-')) . '</div></td>';
                $tableHtml .= '<td>' . h((string)($row['machine_name'] ?? '-')) . '</td>';
                $tableHtml .= '<td>' . $renderStatusChip((string)($row['dashboard_status'] ?? '-')) . '</td>';
                $tableHtml .= '<td>' . h(number_format((float)($row['target_qty'] ?? 0), 0, '.', '')) . '</td>';
                $tableHtml .= '<td>' . h(number_format((float)($row['produced_units'] ?? 0), 0, '.', '')) . '</td>';
                $tableHtml .= '<td><strong>' . h(number_format((float)($row['pending_units'] ?? 0), 0, '.', '')) . '</strong></td>';
                $tableHtml .= '<td class="erp-prod-occupancy"><strong>' . h(number_format((float)($row['progress_percent'] ?? 0), 2, '.', '')) . '%</strong><div class="erp-prod-bar"><div class="erp-prod-bar-fill" style="width:' . h(number_format(max(0.0, min(100.0, (float)($row['progress_percent'] ?? 0))), 2, '.', '')) . '%"></div></div></td>';
                $tableHtml .= '</tr>';
            }
            $tableHtml .= '</tbody></table></div>';
            $note = 'Aquí ves por OT qué órdenes siguen abiertas, cuánto les falta y cuáles ya están cerca de completarse.';
        } elseif ($mode === 'dispatched') {
            $totalDispatched = array_sum(array_map(static fn(array $row): float => (float)($row['dispatched_units'] ?? 0), $filteredRows));
            $summaryHtml = '<div class="erp-prod-mini-grid">'
                . $renderMiniStat('OTs con despacho', (string)count($filteredRows), 'Registraron salida comercial')
                . $renderMiniStat('Unidades despachadas', number_format($totalDispatched, 0, '.', ''), 'Acumulado del período')
                . $renderMiniStat('Mayor despacho', number_format((float)($filteredRows[0]['dispatched_units'] ?? 0), 0, '.', ''), (string)($filteredRows[0]['ot_code'] ?? '-'))
                . $renderMiniStat('Cobertura promedio', number_format(array_sum(array_map(static fn(array $row): float => (float)($row['dispatch_coverage_percent'] ?? 0), $filteredRows)) / max(count($filteredRows), 1), 2, '.', '') . '%', 'Despachado sobre producido')
                . '</div>';
            $tableHtml = '<div class="erp-prod-table-wrap"><table class="erp-prod-table"><thead><tr><th>OT</th><th>Estado</th><th>Producidas</th><th>Despachadas</th><th>Cobertura</th><th>Último movimiento</th></tr></thead><tbody>';
            foreach ($previewRows as $row) {
                $tableHtml .= '<tr>';
                $tableHtml .= '<td><div class="erp-prod-code">' . h((string)($row['ot_code'] ?? ('OT #' . (int)($row['id'] ?? 0)))) . '</div><div class="erp-prod-muted">' . h((string)($row['sku_final'] ?? '-')) . '</div></td>';
                $tableHtml .= '<td>' . $renderStatusChip((string)($row['dashboard_status'] ?? '-')) . '</td>';
                $tableHtml .= '<td>' . h(number_format((float)($row['produced_units'] ?? 0), 0, '.', '')) . '</td>';
                $tableHtml .= '<td><strong>' . h(number_format((float)($row['dispatched_units'] ?? 0), 0, '.', '')) . '</strong></td>';
                $tableHtml .= '<td>' . h(number_format((float)($row['dispatch_coverage_percent'] ?? 0), 2, '.', '')) . '%</td>';
                $tableHtml .= '<td>' . h((string)($row['last_box_at'] ?? '-')) . '</td>';
                $tableHtml .= '</tr>';
            }
            $tableHtml .= '</tbody></table></div>';
            $note = 'Este detalle deja ver por OT qué producción sí salió a cliente y qué tan cubierta queda frente a lo ya fabricado.';
        } elseif ($mode === 'waste') {
            $totalWaste = array_sum(array_map(static fn(array $row): float => (float)($row['waste_kg'] ?? 0), $filteredRows));
            $summaryHtml = '<div class="erp-prod-mini-grid">'
                . $renderMiniStat('OTs con merma', (string)count($filteredRows), 'Registraron desperdicio o proceso')
                . $renderMiniStat('Kg merma', number_format($totalWaste, 3, '.', '') . ' kg', 'Acumulado por OT')
                . $renderMiniStat('Peor % merma', number_format((float)($filteredRows[0]['waste_percent'] ?? 0), 2, '.', '') . '%', (string)($filteredRows[0]['ot_code'] ?? '-'))
                . $renderMiniStat('Registros de merma', (string)array_sum(array_map(static fn(array $row): int => (int)($row['waste_records'] ?? 0), $filteredRows)), 'Entradas del período')
                . '</div>';
            $tableHtml = '<div class="erp-prod-table-wrap"><table class="erp-prod-table"><thead><tr><th>OT</th><th>Estado</th><th>Kg merma</th><th>Kg procesados</th><th>% merma</th><th>Registros</th></tr></thead><tbody>';
            foreach ($previewRows as $row) {
                $tableHtml .= '<tr>';
                $tableHtml .= '<td><div class="erp-prod-code">' . h((string)($row['ot_code'] ?? ('OT #' . (int)($row['id'] ?? 0)))) . '</div><div class="erp-prod-muted">' . h((string)($row['sku_final'] ?? '-')) . '</div></td>';
                $tableHtml .= '<td>' . $renderStatusChip((string)($row['dashboard_status'] ?? '-')) . '</td>';
                $tableHtml .= '<td><strong>' . h(number_format((float)($row['waste_kg'] ?? 0), 3, '.', '')) . ' kg</strong></td>';
                $tableHtml .= '<td>' . h(number_format((float)($row['processed_kg'] ?? 0), 3, '.', '')) . ' kg</td>';
                $tableHtml .= '<td>' . h(number_format((float)($row['waste_percent'] ?? 0), 2, '.', '')) . '%</td>';
                $tableHtml .= '<td>' . h((string)($row['waste_records'] ?? 0)) . '</td>';
                $tableHtml .= '</tr>';
            }
            $tableHtml .= '</tbody></table></div>';
            $note = 'La tarjeta de merma ahora muestra exactamente qué OT está generando más desperdicio y su porcentaje frente al peso procesado.';
        } elseif ($mode === 'processed') {
            $totalProcessed = array_sum(array_map(static fn(array $row): float => (float)($row['processed_kg'] ?? 0), $filteredRows));
            $summaryHtml = '<div class="erp-prod-mini-grid">'
                . $renderMiniStat('OTs con proceso', (string)count($filteredRows), 'Registraron ingreso de bobina')
                . $renderMiniStat('Kg procesados', number_format($totalProcessed, 3, '.', '') . ' kg', 'Total por OT del período')
                . $renderMiniStat('Mayor proceso', number_format((float)($filteredRows[0]['processed_kg'] ?? 0), 3, '.', '') . ' kg', (string)($filteredRows[0]['ot_code'] ?? '-'))
                . $renderMiniStat('Entradas registradas', (string)array_sum(array_map(static fn(array $row): int => (int)($row['attached_events'] ?? 0), $filteredRows)), 'Eventos de ingreso')
                . '</div>';
            $tableHtml = '<div class="erp-prod-table-wrap"><table class="erp-prod-table"><thead><tr><th>OT</th><th>Estado</th><th>Kg procesados</th><th>Kg merma</th><th>% merma</th><th>Entradas</th></tr></thead><tbody>';
            foreach ($previewRows as $row) {
                $tableHtml .= '<tr>';
                $tableHtml .= '<td><div class="erp-prod-code">' . h((string)($row['ot_code'] ?? ('OT #' . (int)($row['id'] ?? 0)))) . '</div><div class="erp-prod-muted">' . h((string)($row['sku_final'] ?? '-')) . '</div></td>';
                $tableHtml .= '<td>' . $renderStatusChip((string)($row['dashboard_status'] ?? '-')) . '</td>';
                $tableHtml .= '<td><strong>' . h(number_format((float)($row['processed_kg'] ?? 0), 3, '.', '')) . ' kg</strong></td>';
                $tableHtml .= '<td>' . h(number_format((float)($row['waste_kg'] ?? 0), 3, '.', '')) . ' kg</td>';
                $tableHtml .= '<td>' . h(number_format((float)($row['waste_percent'] ?? 0), 2, '.', '')) . '%</td>';
                $tableHtml .= '<td>' . h((string)($row['attached_events'] ?? 0)) . '</td>';
                $tableHtml .= '</tr>';
            }
            $tableHtml .= '</tbody></table></div>';
            $note = 'Con esta vista puedes ver qué OT está consumiendo más peso de proceso y contrastarlo con la merma registrada.';
        } else {
            $totalSemiRolls = array_sum(array_map(static fn(array $row): int => (int)($row['semi_rolls_count'] ?? 0), $filteredRows));
            $summaryHtml = '<div class="erp-prod-mini-grid">'
                . $renderMiniStat('OTs con semielaboradas', (string)count($filteredRows), 'Tienen bobinas impresas pendientes')
                . $renderMiniStat('Bobinas pendientes', (string)$totalSemiRolls, 'Inventario semielaborado')
                . $renderMiniStat('Kg semielaborados', number_format(array_sum(array_map(static fn(array $row): float => (float)($row['semi_weight_kg'] ?? 0), $filteredRows)), 3, '.', '') . ' kg', 'Peso disponible por OT')
                . $renderMiniStat('Mayor acumulado', (string)($filteredRows[0]['semi_rolls_count'] ?? 0), (string)($filteredRows[0]['ot_code'] ?? '-'))
                . '</div>';
            $tableHtml = '<div class="erp-prod-table-wrap"><table class="erp-prod-table"><thead><tr><th>OT</th><th>Estado</th><th>Bobinas</th><th>Kg</th><th>Metros</th><th>Avance</th></tr></thead><tbody>';
            foreach ($previewRows as $row) {
                $tableHtml .= '<tr>';
                $tableHtml .= '<td><div class="erp-prod-code">' . h((string)($row['ot_code'] ?? ('OT #' . (int)($row['id'] ?? 0)))) . '</div><div class="erp-prod-muted">' . h((string)($row['sku_final'] ?? '-')) . '</div></td>';
                $tableHtml .= '<td>' . $renderStatusChip((string)($row['dashboard_status'] ?? '-')) . '</td>';
                $tableHtml .= '<td><strong>' . h((string)($row['semi_rolls_count'] ?? 0)) . '</strong></td>';
                $tableHtml .= '<td>' . h(number_format((float)($row['semi_weight_kg'] ?? 0), 3, '.', '')) . ' kg</td>';
                $tableHtml .= '<td>' . h(number_format((float)($row['semi_meters'] ?? 0), 0, '.', '')) . '</td>';
                $tableHtml .= '<td>' . h(number_format((float)($row['progress_percent'] ?? 0), 2, '.', '')) . '%</td>';
                $tableHtml .= '</tr>';
            }
            $tableHtml .= '</tbody></table></div>';
            $note = 'Así puedes identificar por OT dónde se está acumulando inventario semielaborado pendiente de transformar.';
        }

        return $renderModalSection('Detalle por OT', $summaryHtml . $tableHtml) . '<div class="erp-prod-modal-note">' . h($note) . '</div>';
    };
    $producedWorkOrdersHtml = $renderMetricWorkOrderSection('produced', $workOrders);
    $pendingWorkOrdersHtml = $renderMetricWorkOrderSection('pending', $workOrders);
    $dispatchedWorkOrdersHtml = $renderMetricWorkOrderSection('dispatched', $workOrders);
    $wasteWorkOrdersHtml = $renderMetricWorkOrderSection('waste', $workOrders);
    $processedWorkOrdersHtml = $renderMetricWorkOrderSection('processed', $workOrders);
    $semiWorkOrdersHtml = $renderMetricWorkOrderSection('semi', $workOrders);

    $semiRowsPreview = '';
    if ($semiRows === []) {
        $semiRowsPreview = '<div class="erp-prod-empty">No hay bobinas pendientes para mostrar en detalle.</div>';
    } else {
        $semiRowsPreview = '<div class="erp-prod-table-wrap"><table class="erp-prod-table"><thead><tr><th>Bobina</th><th>OT</th><th>Peso</th><th>Metros</th><th>Equiv. bolsas</th></tr></thead><tbody>';
        $semiPreviewCount = 0;
        foreach ($semiRows as $row) {
            if ($semiPreviewCount >= 8) {
                break;
            }
            $estimated = ($row['estimated_units'] ?? null) !== null ? number_format((float)$row['estimated_units'], 0, '.', '') : '-';
            $semiRowsPreview .= '<tr>';
            $semiRowsPreview .= '<td><a class="erp-prod-code" href="/rolls/' . (int)$row['id'] . '">' . h((string)($row['roll_code'] ?? '')) . '</a></td>';
            $semiRowsPreview .= '<td>' . h((string)($row['ot_code'] ?? '-')) . '</td>';
            $semiRowsPreview .= '<td>' . h(number_format((float)($row['weight_kg'] ?? 0), 3, '.', '')) . ' kg</td>';
            $semiRowsPreview .= '<td>' . h(number_format((float)($row['meters'] ?? 0), 0, '.', '')) . '</td>';
            $semiRowsPreview .= '<td>' . h($estimated) . '</td>';
            $semiRowsPreview .= '</tr>';
            $semiPreviewCount++;
        }
        $semiRowsPreview .= '</tbody></table></div>';
    }

    $kpiCards = [
        [
            'modal_id' => 'erp-modal-produced',
            'export_url' => $buildExcelUrl('produced'),
            'title' => 'Unidades producidas',
            'value' => number_format($producedUnits, 0, '.', ''),
            'sub' => 'Unidades finales generadas dentro del período seleccionado.',
            'accent' => 'Producción terminada',
            'detail_title' => 'Detalle de unidades producidas',
            'detail_html' => $renderModalSection('Resumen del período', '<div class="erp-prod-mini-grid">'
                . $renderMiniStat('Unidades producidas', number_format($producedUnits, 0, '.', ''), $activeFilterLabel)
                . $renderMiniStat('Plan total del corte', number_format($plannedUnits, 0, '.', ''), 'Producción + pendiente')
                . $renderMiniStat('Avance del plan', number_format($completionPercent, 2, '.', '') . '%', 'Cumplimiento del período')
                . $renderMiniStat('Unidades despachadas', number_format($dispatchedUnits, 0, '.', ''), 'Salida comercial registrada')
                . '</div>')
                . ''
                . $producedWorkOrdersHtml,
        ],
        [
            'modal_id' => 'erp-modal-pending',
            'export_url' => $buildExcelUrl('pending'),
            'title' => 'Unidades pendientes',
            'value' => number_format($pendingUnits, 0, '.', ''),
            'sub' => 'Carga abierta que sigue pendiente de fabricar en las OTs activas.',
            'accent' => 'Backlog actual',
            'detail_title' => 'Detalle de unidades pendientes',
            'detail_html' => $renderModalSection('Backlog operativo', '<div class="erp-prod-mini-grid">'
                . $renderMiniStat('Pendientes actuales', number_format($pendingUnits, 0, '.', ''), 'OTs abiertas o activas')
                . $renderMiniStat('Ya fabricadas', number_format($producedUnits, 0, '.', ''), 'Referencia del mismo corte')
                . $renderMiniStat('Relación vs producidas', number_format($pendingVsProduced, 2, '.', '') . '%', 'Pendiente frente a terminado')
                . $renderMiniStat('Mayor presión de bodega', $topWarehouseOccupancyText, $topWarehouseLabel)
                . '</div>')
                . ''
                . $pendingWorkOrdersHtml,
        ],
        [
            'modal_id' => 'erp-modal-dispatched',
            'export_url' => $buildExcelUrl('dispatched'),
            'title' => 'Unidades despachadas',
            'value' => number_format($dispatchedUnits, 0, '.', ''),
            'sub' => 'Producción orientada a orden de cliente registrada en el período.',
            'accent' => 'Salida comercial',
            'detail_title' => 'Detalle de unidades despachadas',
            'detail_html' => $renderModalSection('Salida del período', '<div class="erp-prod-mini-grid">'
                . $renderMiniStat('Unidades despachadas', number_format($dispatchedUnits, 0, '.', ''), 'Con destino a orden cliente')
                . $renderMiniStat('Producción terminada', number_format($producedUnits, 0, '.', ''), 'Base para cobertura')
                . $renderMiniStat('Cobertura de despacho', number_format($dispatchCoverage, 2, '.', '') . '%', 'Despachado sobre producido')
                . $renderMiniStat('Pendiente por atender', number_format($pendingUnits, 0, '.', ''), 'Carga aún abierta')
                . '</div>')
                . ''
                . $dispatchedWorkOrdersHtml,
        ],
        [
            'modal_id' => 'erp-modal-semi',
            'export_url' => $buildExcelUrl('semi'),
            'title' => 'Semielaboradas',
            'value' => (string)$semiRollCount,
            'sub' => 'Bobinas impresas que todavía no han pasado por corte y sellado.',
            'accent' => 'Pendiente de transformación',
            'detail_title' => 'Detalle de semielaboradas',
            'detail_html' => $renderModalSection('Resumen disponible', '<div class="erp-prod-mini-grid">'
                . $renderMiniStat('Bobinas pendientes', (string)$semiRollCount, 'Disponibles para transformación')
                . $renderMiniStat('Peso total', number_format($semiWeightTotal, 3, '.', '') . ' kg', 'Inventario semielaborado')
                . $renderMiniStat('Metros acumulados', number_format($semiMetersTotal, 0, '.', ''), 'Longitud disponible')
                . $renderMiniStat('Equiv. estimada', number_format($semiEstimatedUnitsTotal, 0, '.', ''), 'Bolsas aproximadas')
                . '</div>')
                . $renderModalSection('Bobinas recientes', $semiRowsPreview)
                . ''
                . $semiWorkOrdersHtml,
        ],
        [
            'modal_id' => 'erp-modal-waste',
            'export_url' => $buildExcelUrl('waste'),
            'title' => 'Merma',
            'value' => number_format($wastePercent, 2, '.', '') . '%',
            'sub' => number_format($wasteKg, 3, '.', '') . ' kg de merma registrados sobre el período.',
            'accent' => 'Control de desperdicio',
            'detail_title' => 'Detalle de merma',
            'detail_html' => $renderModalSection('Indicadores de desperdicio', '<div class="erp-prod-mini-grid">'
                . $renderMiniStat('% de merma', number_format($wastePercent, 2, '.', '') . '%', $activeFilterLabel)
                . $renderMiniStat('Kg merma', number_format($wasteKg, 3, '.', '') . ' kg', 'Registro acumulado')
                . $renderMiniStat('Kg procesados', number_format($processedKg, 3, '.', '') . ' kg', 'Base del cálculo')
                . $renderMiniStat('Rendimiento neto', number_format($yieldPercent, 2, '.', '') . '%', '100% - merma')
                . '</div>')
                . ''
                . $wasteWorkOrdersHtml,
        ],
        [
            'modal_id' => 'erp-modal-processed',
            'export_url' => $buildExcelUrl('processed'),
            'title' => 'Kg procesados',
            'value' => number_format($processedKg, 3, '.', ''),
            'sub' => 'Peso de proceso informado al ingreso de bobina en la OT.',
            'accent' => 'Base de cálculo',
            'detail_title' => 'Detalle de kg procesados',
            'detail_html' => $renderModalSection('Base del proceso', '<div class="erp-prod-mini-grid">'
                . $renderMiniStat('Kg procesados', number_format($processedKg, 3, '.', '') . ' kg', $activeFilterLabel)
                . $renderMiniStat('Kg netos aprovechados', number_format($netProcessedKg, 3, '.', '') . ' kg', 'Procesado menos merma')
                . $renderMiniStat('Kg merma asociados', number_format($wasteKg, 3, '.', '') . ' kg', 'Impacto del desperdicio')
                . $renderMiniStat('% merma vinculada', number_format($wastePercent, 2, '.', '') . '%', 'Sobre el total procesado')
                . '</div>')
                . ''
                . $processedWorkOrdersHtml,
        ],
    ];

    $body = '<div class="erp-prod-shell">';
    if (!$embeddedInErp) {
        $body .= '<div class="erp-prod-hero"><div class="erp-prod-hero-top"><div><div class="erp-prod-title">DASHBOARD DE PRODUCCIÓN</div><div class="erp-prod-badges"><div class="erp-prod-badge">Filtro aplicado: ' . h($activeFilterLabel) . '</div><div class="erp-prod-badge">Modo: ' . h($defaultFilterType === 'range' ? 'Rango de fechas' : 'Período 26 al 25') . '</div><div class="erp-prod-badge">Ocupación máxima: ' . h(number_format($bestOccupancy, 2, '.', '')) . '%</div></div></div><div class="erp-prod-hero-actions"><a class="btn secondary" href="/">Volver al panel ERP</a></div></div></div>';
    }

    $body .= '<div class="erp-prod-panel"><div class="erp-prod-panel-head"><div><div class="erp-prod-panel-title">Filtros del dashboard</div></div><button type="button" class="erp-collapse-toggle" data-collapse-toggle aria-label="Colapsar sección"><span class="chev">▾</span></button></div><div class="erp-prod-panel-body">';
    $body .= '<form method="get" action="' . ($embeddedInErp ? '/' : '/reports/production-dashboard') . '" id="erp-dashboard-filter-form"><div class="erp-prod-toolbar">';
    $body .= '<div class="erp-prod-field"><label for="filter_type">Tipo de filtro</label><select id="filter_type" name="filter_type"><option value="period"' . ($defaultFilterType === 'period' ? ' selected' : '') . '>Período 26 al 25</option><option value="range"' . ($defaultFilterType === 'range' ? ' selected' : '') . '>Rango de fechas</option></select></div>';
    $body .= '<div class="erp-prod-field' . ($defaultFilterType === 'period' ? '' : ' is-hidden') . '" data-filter-group="period"><label for="period">Período</label><input id="period" type="month" name="period" value="' . h($periodYm) . '"' . ($defaultFilterType === 'period' ? '' : ' disabled') . '></div>';
    $body .= '<div class="erp-prod-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range"><label for="start_date">Fecha inicio</label><input id="start_date" type="date" name="start_date" value="' . h($rangeStartInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '></div>';
    $body .= '<div class="erp-prod-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range"><label for="end_date">Fecha final</label><input id="end_date" type="date" name="end_date" value="' . h($rangeEndInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '></div>';
    $body .= '<div><button class="btn" type="submit">Actualizar dashboard</button></div>';
    $body .= '</div></form></div></div>';
    $body .= '<div class="erp-prod-kpis">';
    foreach ($kpiCards as $card) {
        $body .= '<button type="button" class="erp-prod-kpi erp-prod-kpi-button" data-modal-target="' . h($card['modal_id']) . '">';
        $body .= '<div class="erp-prod-kpi-label">' . h($card['title']) . '</div>';
        $body .= '<div class="erp-prod-kpi-value">' . h($card['value']) . '</div>';
        $body .= '<div class="erp-prod-kpi-sub">' . h($card['sub']) . '</div>';
        $body .= '<div class="erp-prod-kpi-accent">' . h($card['accent']) . '</div>';
        $body .= '<div class="erp-prod-kpi-hint">Ver detalle</div>';
        $body .= '</button>';
    }
    $body .= '</div>';
    foreach ($kpiCards as $card) {
        $titleId = $card['modal_id'] . '-title';
        $body .= '<div class="erp-prod-modal" id="' . h($card['modal_id']) . '" hidden>';
        $body .= '<div class="erp-prod-modal-backdrop" data-modal-close="1"></div>';
        $body .= '<div class="erp-prod-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="' . h($titleId) . '">';
        $body .= '<div class="erp-prod-modal-head"><div><div class="erp-prod-modal-title" id="' . h($titleId) . '">' . h($card['detail_title']) . '</div><div class="erp-prod-modal-sub">' . h($activeFilterLabel) . '</div></div><div class="erp-prod-modal-actions"><a class="btn secondary" href="' . h((string)($card['export_url'] ?? '#')) . '">Exportar Excel</a><button type="button" class="erp-prod-modal-close" aria-label="Cerrar" data-modal-close="1">&times;</button></div></div>';
        $body .= $card['detail_html'];
        $body .= '</div></div>';
    }

    $body .= '<div class="erp-prod-grid">';
    $body .= '<div class="erp-prod-panel"><div class="erp-prod-panel-head"><div><div class="erp-prod-panel-title">Semielaboradas pendientes</div></div><button type="button" class="erp-collapse-toggle" data-collapse-toggle aria-label="Colapsar sección"><span class="chev">▾</span></button></div><div class="erp-prod-panel-body">';
    if ($semiRows === []) {
        $body .= '<div class="erp-prod-empty">No hay bobinas impresas pendientes de corte en este momento.</div>';
    } else {
        $body .= '<div class="erp-prod-table-wrap"><table class="erp-prod-table"><thead><tr><th>Bobina</th><th>OT</th><th>Peso</th><th>Metros</th><th>Equiv. bolsas</th><th>Fecha</th></tr></thead><tbody>';
        foreach ($semiRows as $row) {
            $estimated = $row['estimated_units'] !== null ? number_format((float)$row['estimated_units'], 0, '.', '') : '-';
            $body .= '<tr>';
            $body .= '<td><a class="erp-prod-code" href="/rolls/' . (int)$row['id'] . '">' . h((string)($row['roll_code'] ?? '')) . '</a><div class="erp-prod-muted">Bobina semielaborada</div></td>';
            $body .= '<td><span class="erp-prod-chip">' . h((string)($row['ot_code'] ?? '-')) . '</span></td>';
            $body .= '<td>' . h(number_format((float)($row['weight_kg'] ?? 0), 3, '.', '')) . ' kg</td>';
            $body .= '<td>' . h((string)($row['meters'] ?? '-')) . '</td>';
            $body .= '<td><strong>' . h($estimated) . '</strong></td>';
            $body .= '<td>' . h((string)($row['created_at'] ?? '')) . '</td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div>';
    }
    $body .= '</div></div>';

    $body .= '<div style="display:flex;flex-direction:column;gap:18px">';
    $body .= '<div class="erp-prod-panel"><div class="erp-prod-panel-head"><div><div class="erp-prod-panel-title">Merma y lectura ejecutiva</div></div><button type="button" class="erp-collapse-toggle" data-collapse-toggle aria-label="Colapsar sección"><span class="chev">▾</span></button></div><div class="erp-prod-panel-body">';
    $body .= '<div class="erp-prod-callout" style="margin:0 0 18px 0"><div class="erp-prod-callout-title">Resumen de merma</div><div class="erp-prod-callout-value">' . h(number_format($wastePercent, 2, '.', '')) . '%</div><div class="erp-prod-callout-sub">Se registraron <strong>' . h(number_format($wasteKg, 3, '.', '')) . ' kg</strong> de merma sobre una base de <strong>' . h(number_format($processedKg, 3, '.', '')) . ' kg</strong> procesados.</div><div class="erp-prod-mini-grid"><div class="erp-prod-mini"><div class="erp-prod-mini-label">Kg merma</div><div class="erp-prod-mini-value">' . h(number_format($wasteKg, 3, '.', '')) . '</div></div><div class="erp-prod-mini"><div class="erp-prod-mini-label">Kg procesados</div><div class="erp-prod-mini-value">' . h(number_format($processedKg, 3, '.', '')) . '</div></div></div></div>';
    $body .= '<div class="erp-prod-mini-grid">';
    $body .= '<div class="erp-prod-mini"><div class="erp-prod-mini-label">Fabricado vs pendiente</div><div class="erp-prod-mini-value">' . h(number_format($producedUnits, 0, '.', '')) . '</div><div class="erp-prod-muted">Pendiente actual: ' . h(number_format($pendingUnits, 0, '.', '')) . '</div></div>';
    $body .= '<div class="erp-prod-mini"><div class="erp-prod-mini-label">Despacho</div><div class="erp-prod-mini-value">' . h(number_format($dispatchedUnits, 0, '.', '')) . '</div><div class="erp-prod-muted">Unidades asociadas a orden cliente</div></div>';
    $body .= '</div></div></div>';
    $body .= '</div>';
    $body .= '</div>';

    $warehouseFilterSubtitle = $warehouseFilterCode !== null
        ? 'Vista actual filtrada por la bodega seleccionada. Puedes volver a mostrar todas las bodegas desde el selector.'
        : 'Comparación entre el stock almacenado y la capacidad configurada de cada bodega. Usa el selector para ver el detalle de una sola bodega.';
    $occupancyExcelUrl = withQuery('/reports/occupancy/excel', $dashboardFilterParams);
    $body .= '<div class="erp-prod-panel"><div class="erp-prod-panel-head"><div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;flex:1"><div><div class="erp-prod-panel-title">Nivel de ocupación de bodegas</div></div><div class="row" style="flex-wrap:wrap;gap:10px;align-items:flex-end"><form method="get" action="' . ($embeddedInErp ? '/' : '/reports/production-dashboard') . '" class="erp-prod-panel-filter" style="margin:0"><div class="erp-prod-field" style="min-width:260px"><label for="warehouse_filter_occupancy">Ver bodega</label><select id="warehouse_filter_occupancy" name="warehouse_filter" onchange="this.form.submit()">' . $warehouseOptionsHtml . '</select></div>' . $warehouseFilterHiddenInputs . '</form><a class="btn secondary" href="' . h($occupancyExcelUrl) . '">Exportar Excel</a></div></div><button type="button" class="erp-collapse-toggle" data-collapse-toggle aria-label="Colapsar sección"><span class="chev">▾</span></button></div><div class="erp-prod-panel-body">';
    if ($filteredWarehouses === []) {
        $body .= '<div class="erp-prod-empty">Sin bodegas registradas para mostrar ocupación en esta selección.</div>';
    } else {
        $body .= '<div class="erp-prod-table-wrap"><table class="erp-prod-table"><thead><tr><th>Bodega</th><th>Stock</th><th>Ocupación</th><th>Capacidad pallets</th><th>Capacidad unidades</th></tr></thead><tbody>';
        foreach ($filteredWarehouses as $row) {
            $whCode = trim((string)($row['warehouse_code'] ?? ''));
            $whHasModal = $whCode !== '' && isset($warehouseDetailMap[$whCode]);
            $occValue = (float)($row['occupancy_percent'] ?? 0);
            $occText = $row['occupancy_percent'] !== null ? number_format($occValue, 2, '.', '') . '%' : 'Sin capacidad';
            $capPallets = (int)($row['capacity_pallets'] ?? 0) > 0 ? (string)(int)$row['capacity_pallets'] : '-';
            $capUnits = (float)($row['capacity_units_total'] ?? 0) > 0 ? number_format((float)$row['capacity_units_total'], 0, '.', '') : '-';
            $fillPercent = max(0, min(100, $occValue));
            $rowAttrs = $whHasModal
                ? ' class="erp-prod-warehouse-row" data-modal-target="' . h('erp-modal-warehouse-' . $whCode) . '" role="button" tabindex="0" aria-label="Abrir detalle de la bodega ' . h($whCode) . '"'
                : '';
            $body .= '<tr' . $rowAttrs . '>';
            $body .= '<td><div class="erp-prod-code">' . h((string)($row['warehouse_code'] ?? '')) . ' · ' . h((string)($row['warehouse_name'] ?? '')) . '</div><div class="erp-prod-muted">Rollos: ' . h((string)($row['rolls_count'] ?? 0)) . ' · Cajas: ' . h((string)($row['boxes_count'] ?? 0)) . ' · Pallets: ' . h((string)($row['pallets_count'] ?? 0)) . '</div>' . ($whHasModal ? '<div class="erp-prod-kpi-hint" style="margin-top:8px">Ver detalle de la bodega</div>' : '') . '</td>';
            $body .= '<td><strong>' . h(number_format((float)($row['stock_units_total'] ?? 0), 0, '.', '')) . '</strong><div class="erp-prod-muted">unidades equivalentes</div></td>';
            $body .= '<td class="erp-prod-occupancy"><strong>' . h($occText) . '</strong><div class="erp-prod-bar"><div class="erp-prod-bar-fill" style="width:' . h(number_format($fillPercent, 2, '.', '')) . '%"></div></div></td>';
            $body .= '<td>' . h($capPallets) . '</td>';
            $body .= '<td>' . h($capUnits) . '</td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div>';
    }
    $body .= '</div></div>';

    foreach ($warehouseDetailMap as $whDetailItem) {
        $summary = (array)$whDetailItem['summary'];
        $modalId = (string)$whDetailItem['modal_id'];
        $modalTitle = trim((string)($summary['warehouse_code'] ?? '') . ' · ' . (string)($summary['warehouse_name'] ?? ''));
        $summaryRolls = (int)($summary['rolls_count'] ?? 0);
        $summaryBoxes = (int)($summary['boxes_count'] ?? 0);
        $summaryPallets = (int)($summary['pallets_count'] ?? 0);
        $summaryWeightKg = (float)($summary['total_weight_kg'] ?? 0);
        $summaryStockUnits = (float)($summary['stock_units_total'] ?? 0);
        $summaryOccupancy = ($summary['occupancy_percent'] ?? null) !== null
            ? number_format((float)$summary['occupancy_percent'], 2, '.', '') . '%'
            : 'Sin capacidad';
        $summaryCapPallets = (int)($summary['capacity_pallets'] ?? 0) > 0 ? (string)(int)$summary['capacity_pallets'] : 'Sin configurar';
        $summaryCapUnits = (float)($summary['capacity_units_total'] ?? 0) > 0 ? number_format((float)$summary['capacity_units_total'], 0, '.', '') : 'Sin configurar';

        $summaryHtml = $renderModalSection('Resumen de ocupación', '<div class="erp-prod-mini-grid">'
            . $renderMiniStat('Unidades en stock', number_format($summaryStockUnits, 0, '.', ''), 'Rollos + cajas equivalentes')
            . $renderMiniStat('Ocupación', $summaryOccupancy, 'Pallets o unidades vs capacidad')
            . $renderMiniStat('Capacidad pallets', $summaryCapPallets, 'Máximo configurado')
            . $renderMiniStat('Capacidad unidades', $summaryCapUnits, 'Máximo configurado')
            . '</div>');
        $compositionHtml = $renderModalSection('Composición de la bodega', '<div class="erp-prod-mini-grid">'
            . $renderMiniStat('Rollos', (string)$summaryRolls, 'Bobinas ubicadas')
            . $renderMiniStat('Cajas', (string)$summaryBoxes, 'Cajas almacenadas')
            . $renderMiniStat('Pallets', (string)$summaryPallets, 'Pallets consolidados')
            . $renderMiniStat('Peso en rollos', number_format($summaryWeightKg, 3, '.', '') . ' kg', 'Peso total de bobinas')
            . '</div>');
        $rollHtml = $renderModalSection('Bobinas dentro de la bodega', $renderWarehouseRollTable((array)($whDetailItem['rolls'] ?? []), 8));
        $palletHtml = $renderModalSection('Pallets almacenados', $renderWarehousePalletTable((array)($whDetailItem['pallets'] ?? []), 8));
        $boxHtml = $renderModalSection('Cajas almacenadas', $renderWarehouseBoxTable((array)($whDetailItem['boxes'] ?? []), 8));

        $titleId = $modalId . '-title';
        $exportUrl = withQuery('/reports/occupancy/excel', array_merge($dashboardFilterParams, ['warehouse_filter' => (string)($summary['warehouse_code'] ?? '')]));
        $body .= '<div class="erp-prod-modal" id="' . h($modalId) . '" hidden>';
        $body .= '<div class="erp-prod-modal-backdrop" data-modal-close="1"></div>';
        $body .= '<div class="erp-prod-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="' . h($titleId) . '">';
        $body .= '<div class="erp-prod-modal-head"><div><div class="erp-prod-modal-title" id="' . h($titleId) . '">Detalle bodega · ' . h($modalTitle) . '</div></div><div class="erp-prod-modal-actions"><a class="btn secondary" href="' . h($exportUrl) . '">Exportar Excel</a><button type="button" class="erp-prod-modal-close" aria-label="Cerrar" data-modal-close="1">&times;</button></div></div>';
        $body .= $summaryHtml;
        $body .= $compositionHtml;
        $body .= $rollHtml;
        $body .= $palletHtml;
        $body .= $boxHtml;
        $body .= '</div></div>';
    }

    $body .= '</div>';

    $body .= '<script>
        (function () {
            function attachCollapsibleControls(root) {
                if (!root) root = document;
                root.querySelectorAll("[data-collapse-toggle]").forEach(function (btn) {
                    if (btn.getAttribute("data-collapse-bound") === "1") return;
                    btn.setAttribute("data-collapse-bound", "1");
                    btn.addEventListener("click", function () {
                        var panel = btn.closest(".erp-prod-panel, .erp-graph-panel");
                        if (!panel) return;
                        panel.classList.toggle("is-collapsed");
                    });
                    btn.addEventListener("keydown", function (event) {
                        if (!event) return;
                        if (event.key !== "Enter" && event.key !== " ") return;
                        event.preventDefault();
                        btn.click();
                    });
                });
            }
            function initDashboardPage() {
                function closeModal(modal) {
                    if (!modal) return;
                    modal.hidden = true;
                    document.body.style.overflow = "";
                }

                function openModal(modal) {
                    if (!modal) return;
                    modal.hidden = false;
                    document.body.style.overflow = "hidden";
                }

                attachCollapsibleControls(document);

                document.querySelectorAll("[data-modal-target]").forEach(function (button) {
                    button.addEventListener("click", function (event) {
                        if (event && event.target && event.target.closest && event.target.closest("a")) return;
                        var targetId = button.getAttribute("data-modal-target");
                        if (!targetId) return;
                        openModal(document.getElementById(targetId));
                    });
                    button.addEventListener("keydown", function (event) {
                        if (!event) return;
                        if (event.key !== "Enter" && event.key !== " ") return;
                        var targetId = button.getAttribute("data-modal-target");
                        if (!targetId) return;
                        event.preventDefault();
                        openModal(document.getElementById(targetId));
                    });
                });

                document.querySelectorAll(".erp-prod-modal").forEach(function (modal) {
                    modal.querySelectorAll("[data-modal-close]").forEach(function (closeButton) {
                        closeButton.addEventListener("click", function () {
                            closeModal(modal);
                        });
                    });
                });

                document.addEventListener("keydown", function (event) {
                    if (event.key !== "Escape") return;
                    document.querySelectorAll(".erp-prod-modal").forEach(function (modal) {
                        if (!modal.hidden) {
                            closeModal(modal);
                        }
                    });
                });
            }

            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", initDashboardPage);
            } else {
                initDashboardPage();
            }
        })();
    </script>';

    render($embeddedInErp ? 'ERP' : 'Dashboard Producción', $body);
}

/**
 * Renderiza la página de gráficos (charts) del área ERP.
 *
 * Reutiliza la misma resolución de filtros (período 26–25 / rango) y construye
 * datasets para:
 * - Tendencia de KPIs en meses recientes (ej. 6 períodos).
 * - Ranking de ocupación por bodega.
 *
 * ---
 *
 * Renders the ERP charts page.
 *
 * Reuses the same filter resolution (26–25 period / range) and builds datasets for:
 * - KPI trend over recent months (e.g., last 6 periods).
 * - Warehouse occupancy ranking.
 */
function unibagRenderGraphicsPage(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    /** @var DateTimeImmutable $start */
    $start = $filters['start'];
    /** @var DateTimeImmutable $end */
    $end = $filters['end'];
    $defaultFilterType = (string)$filters['filter_type'];
    $periodYm = (string)$filters['period'];
    $rangeStartInput = (string)$filters['start_date'];
    $rangeEndInput = (string)$filters['end_date'];
    $activeFilterLabel = (string)$filters['active_filter_label'];

    // Obtenemos los datos analíticos directamente de la BD real del ERP
    $analytics = $service->getErpGraphicsAnalytics($start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));

    $kpis = $analytics['kpis'] ?? [];
    $unitsCorteSellado = (float)($kpis['units_corte_sellado'] ?? 0);
    $unitsEmbalaje = (float)($kpis['units_embalaje'] ?? 0);
    $unitsFlexo = (float)($kpis['units_flexo'] ?? 0);
    $unitsSeri = (float)($kpis['units_seri'] ?? 0);
    $unitsPulpo = (float)($kpis['units_pulpo'] ?? 0);
    $unitsConfeccion = (float)($kpis['units_confeccion'] ?? 0);
    $unitsImpresion = (float)($kpis['units_impresion'] ?? 0);
    $totalUnits = (float)($kpis['total_units'] ?? 0);
    $wasteKg = (float)($kpis['waste_kg'] ?? 0);
    $wastePercent = (float)($kpis['waste_percent'] ?? 0);
    $activeMachines = (int)($kpis['active_machines'] ?? 0);
    $totalEvents = (int)($kpis['total_events'] ?? 0);

    $monthlyTrend = $analytics['monthly_trend'] ?? [];
    $periodEvolution = $analytics['period_evolution'] ?? [];
    $processDist = $analytics['process_distribution'] ?? [];
    $machineRanking = $analytics['machine_ranking'] ?? [];
    $wasteByMachine = $analytics['waste_by_machine'] ?? [];

    $fmtInt = static function (float $num): string {
        return number_format($num, 0, ',', '.');
    };
    $fmtDec = static function (float $num, int $dec = 2): string {
        return number_format($num, $dec, ',', '.');
    };

    $formAction = '/reports/graphics';
    $hiddenInputs = '';
    if ($defaultFilterType !== 'range') {
        $hiddenInputs .= '<input type="hidden" name="start_date" value="' . h($rangeStartInput) . '">';
        $hiddenInputs .= '<input type="hidden" name="end_date" value="' . h($rangeEndInput) . '">';
    }
    if ($defaultFilterType !== 'period') {
        $hiddenInputs .= '<input type="hidden" name="period" value="' . h($periodYm) . '">';
    }

    $body = '<style>
        .erp-kpi-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px}
        .erp-kpi-card{background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:16px;box-shadow:0 4px 16px rgba(15,23,42,.03);position:relative;overflow:hidden;transition:all .2s ease;display:flex;flex-direction:column;justify-content:space-between}
        .erp-kpi-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(15,23,42,.06)}
        .erp-kpi-card::before{content:"";position:absolute;top:0;left:0;right:0;height:4px}
        .erp-kpi-card.blue::before{background:linear-gradient(90deg,#00A9A6,#2dd4bf)}
        .erp-kpi-card.indigo::before{background:linear-gradient(90deg,#0284c7,#38bdf8)}
        .erp-kpi-card.cyan::before{background:linear-gradient(90deg,#0d9488,#5eead4)}
        .erp-kpi-card.purple::before{background:linear-gradient(90deg,#7c3aed,#a78bfa)}
        .erp-kpi-card.rose::before{background:linear-gradient(90deg,#e11d48,#fb7185)}
        .erp-kpi-card.amber::before{background:linear-gradient(90deg,#d97706,#fbbf24)}
        .erp-kpi-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:20px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}
        .erp-kpi-card.blue .erp-kpi-badge{background:#f0fdfa;color:#0f766e}
        .erp-kpi-card.indigo .erp-kpi-badge{background:#f0f9ff;color:#0369a1}
        .erp-kpi-card.cyan .erp-kpi-badge{background:#ecfdf5;color:#047857}
        .erp-kpi-card.purple .erp-kpi-badge{background:#faf5ff;color:#6b21a8}
        .erp-kpi-card.rose .erp-kpi-badge{background:#fff1f2;color:#be123c}
        .erp-kpi-card.amber .erp-kpi-badge{background:#fffbeb;color:#b45309}
        .erp-kpi-val{font-size:24px;font-weight:800;color:#0f172a;margin-top:8px;letter-spacing:-.02em;line-height:1.1}
        .erp-kpi-sub{font-size:11.5px;color:#64748b;margin-top:5px;font-weight:500;line-height:1.25}
        .erp-grid-2{display:grid;grid-template-columns:1.6fr 1fr;gap:20px}
        .erp-grid-2-even{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        .erp-chart-box{background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;padding:24px;box-shadow:0 6px 20px rgba(15,23,42,.03);display:flex;flex-direction:column}
        .erp-chart-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px}
        .erp-chart-title{font-size:16px;font-weight:800;color:#0f172a;letter-spacing:-.01em}
        .erp-chart-subtitle{font-size:12px;color:#64748b;margin-top:2px}
        .erp-chart-wrap{position:relative;width:100%;height:320px}
        .erp-chart-wrap.tall{height:380px}
        .erp-chart-wrap.short{height:260px}
        .erp-chart-wrap canvas{display:block;width:100%!important;height:100%!important}
        .chart-toggle-pills{display:inline-flex;background:#f1f5f9;border-radius:10px;padding:3px;gap:3px}
        .chart-pill{padding:5px 12px;font-size:11px;font-weight:700;border:none;background:transparent;color:#64748b;border-radius:8px;cursor:pointer;transition:all .15s}
        .chart-pill.active{background:#00A9A6;color:#ffffff;box-shadow:0 2px 6px rgba(0,169,166,.25)}
        @media (max-width: 1400px) {
            .erp-kpi-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
            .erp-grid-2,.erp-grid-2-even{grid-template-columns:1fr}
        }
        @media (max-width: 768px) { .erp-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))} }
        @media (max-width: 480px) { .erp-kpi-grid{grid-template-columns:1fr} }
    </style>';

    $body .= '<script src="/js/chart.umd.min.js"></script>';
    $body .= '<script>if (typeof Chart === "undefined") { document.write(\'<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"><\\/script>\'); }</script>';

    $body .= '<div class="erp-graph-shell">';

    // Barra de Filtros
    $body .= '<div class="erp-filter-card">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">GRÁFICOS Y MÉTRICAS ERP</div>';
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . '</div>';
    $body .= '</div>';
    $body .= '<form id="erp-graph-filter-form" method="get" action="' . h($formAction) . '" class="erp-filter-form">';
    $body .= '<div class="erp-filter-field">';
    $body .= '<label for="filter_type_graph">Tipo de filtro</label>';
    $body .= '<select id="filter_type_graph" name="filter_type">';
    $body .= '<option value="period"' . ($defaultFilterType === 'period' ? ' selected' : '') . '>Período (26 al 25)</option>';
    $body .= '<option value="range"' . ($defaultFilterType === 'range' ? ' selected' : '') . '>Rango personalizado</option>';
    $body .= '</select>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'period' ? '' : ' is-hidden') . '" data-filter-group="period">';
    $body .= '<label for="period_graph">Mes del período</label>';
    $body .= '<input id="period_graph" type="month" name="period" value="' . h($periodYm) . '"' . ($defaultFilterType === 'period' ? '' : ' disabled') . '>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="start_date_graph">Fecha inicio</label>';
    $body .= '<input id="start_date_graph" type="date" name="start_date" value="' . h($rangeStartInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="end_date_graph">Fecha término</label>';
    $body .= '<input id="end_date_graph" type="date" name="end_date" value="' . h($rangeEndInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';
    $body .= '<button type="submit" class="btn-filter-apply">🔍 Aplicar Filtros</button>';
    $body .= $hiddenInputs;
    $body .= '</form>';
    $body .= '</div>';

    // Tarjetas KPI Separadas por Máquinas / Procesos + Merma
    $body .= '<div class="erp-kpi-grid">';

    // KPI 1: Corte y Sellado
    $body .= '<div class="erp-kpi-card blue">';
    $body .= '<div class="erp-kpi-badge">✂️ Corte y Sellado</div>';
    $body .= '<div class="erp-kpi-val">' . $fmtInt($unitsCorteSellado) . '</div>';
    $body .= '</div>';

    // KPI 2: Embalaje
    $body .= '<div class="erp-kpi-card indigo">';
    $body .= '<div class="erp-kpi-badge">📦 Embalaje</div>';
    $body .= '<div class="erp-kpi-val">' . $fmtInt($unitsEmbalaje) . '</div>';
    $body .= '</div>';

    // KPI 3: Flexografía
    $body .= '<div class="erp-kpi-card cyan">';
    $body .= '<div class="erp-kpi-badge">🖨️ Flexografía</div>';
    $body .= '<div class="erp-kpi-val">' . $fmtInt($unitsFlexo) . '</div>';
    $body .= '</div>';

    // KPI 4: Serigrafía
    $body .= '<div class="erp-kpi-card purple">';
    $body .= '<div class="erp-kpi-badge">🎨 Serigrafía</div>';
    $body .= '<div class="erp-kpi-val">' . $fmtInt($unitsSeri) . '</div>';
    $body .= '<div class="erp-kpi-sub">Impresión serigráfica plana</div>';
    $body .= '</div>';

    // KPI 5: Pulpo Serigráfico
    $body .= '<div class="erp-kpi-card rose">';
    $body .= '<div class="erp-kpi-badge">🐙 Pulpo Serigráfico</div>';
    $body .= '<div class="erp-kpi-val">' . $fmtInt($unitsPulpo) . '</div>';
    $body .= '<div class="erp-kpi-sub">Impresión serigráfica en pulpo</div>';
    $body .= '</div>';

    // KPI 6: Merma del Período
    $body .= '<div class="erp-kpi-card amber">';
    $body .= '<div class="erp-kpi-badge">⚠️ Merma del Período</div>';
    $body .= '<div class="erp-kpi-val">' . $fmtDec($wastePercent, 2) . '%</div>';
    $body .= '<div class="erp-kpi-sub">' . $fmtDec($wasteKg, 2) . ' kg de merma registrados</div>';
    $body .= '</div>';

    $body .= '</div>'; // fin erp-kpi-grid

    // FILA 1: Evolución / Tendencia Temporal + Distribución por Proceso
    $body .= '<div class="erp-grid-2">';

    // Gráfico 1: Evolución / Tendencia
    $body .= '<div class="erp-chart-box">';
    $body .= '<div class="erp-chart-head">';
    $body .= '<div>';
    $body .= '<div class="erp-chart-title" id="trendChartTitle">Evolución de Producción en el Período</div>';
    $body .= '</div>';
    $body .= '<div class="chart-toggle-pills">';
    $body .= '<button type="button" class="chart-pill active" id="btnTrendPeriod" onclick="toggleTrendView(\'period\')">Día a Día</button>';
    $body .= '<button type="button" class="chart-pill" id="btnTrendMonthly" onclick="toggleTrendView(\'monthly\')">Histórico 6 Meses</button>';
    $body .= '</div>';
    $body .= '</div>';
    $body .= '<div class="erp-chart-wrap tall">';
    $body .= '<canvas id="erpTrendChart"></canvas>';
    $body .= '</div>';
    $body .= '</div>';

    // Gráfico 2: Composición por Proceso (Donut)
    $body .= '<div class="erp-chart-box">';
    $body .= '<div class="erp-chart-head">';
    $body .= '<div>';
    $body .= '<div class="erp-chart-title">Distribución por Proceso</div>';
    $body .= '</div>';
    $body .= '</div>';
    $body .= '<div class="erp-chart-wrap tall">';
    $body .= '<canvas id="erpProcessDonutChart"></canvas>';
    $body .= '</div>';
    $body .= '</div>';

    $body .= '</div>'; // fin erp-grid-2

    // FILA 2: Ranking de Producción por Máquina + Merma por Máquina
    $body .= '<div class="erp-grid-2-even">';

    // Gráfico 3: Ranking Máquinas
    $body .= '<div class="erp-chart-box">';
    $body .= '<div class="erp-chart-head">';
    $body .= '<div>';
    $body .= '<div class="erp-chart-title">Producción por Máquina</div>';
    $body .= '</div>';
    $body .= '</div>';
    $body .= '<div class="erp-chart-wrap tall">';
    $body .= '<canvas id="erpMachineProdChart"></canvas>';
    $body .= '</div>';
    $body .= '</div>';

    // Gráfico 4: Merma por Máquina
    $body .= '<div class="erp-chart-box">';
    $body .= '<div class="erp-chart-head">';
    $body .= '<div>';
    $body .= '<div class="erp-chart-title">Control de Calidad: Merma por Máquina</div>';
    $body .= '</div>';
    $body .= '</div>';
    $body .= '<div class="erp-chart-wrap tall">';
    $body .= '<canvas id="erpMachineWasteChart"></canvas>';
    $body .= '</div>';
    $body .= '</div>';

    $body .= '</div>'; // fin erp-grid-2-even

    $body .= '</div>'; // fin erp-graph-shell

    // Preparamos los Payloads JSON para Chart.js
    $periodLabels = array_column($periodEvolution, 'label');
    $periodConfeccion = array_column($periodEvolution, 'confeccion');
    $periodImpresion = array_column($periodEvolution, 'impresion');

    $monthlyLabels = array_column($monthlyTrend, 'label');
    $monthlyConfeccion = array_column($monthlyTrend, 'confeccion');
    $monthlyImpresion = array_column($monthlyTrend, 'impresion');

    $processLabels = array_column($processDist, 'process_name');
    $processValues = array_column($processDist, 'units');

    $machineLabels = array_column($machineRanking, 'machine_name');
    $machineValues = array_column($machineRanking, 'units');

    $wasteLabels = array_column($wasteByMachine, 'machine_name');
    $wasteValues = array_column($wasteByMachine, 'defect_kg');

    $clientPayload = [
        'period' => [
            'labels' => $periodLabels,
            'confeccion' => $periodConfeccion,
            'impresion' => $periodImpresion,
        ],
        'monthly' => [
            'labels' => $monthlyLabels,
            'confeccion' => $monthlyConfeccion,
            'impresion' => $monthlyImpresion,
        ],
        'process' => [
            'labels' => $processLabels,
            'values' => $processValues,
        ],
        'machines' => [
            'labels' => $machineLabels,
            'values' => $machineValues,
        ],
        'waste' => [
            'labels' => $wasteLabels,
            'values' => $wasteValues,
        ],
    ];

    $jsonPayload = json_encode($clientPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $body .= '<script>
        (function () {
            var data = ' . $jsonPayload . ';
            var numFmt = new Intl.NumberFormat("es-CL");

            // Paletas temáticas
            var primaryBlue = "#2563eb";
            var primaryCyan = "#0284c7";
            var warmAmber = "#d97706";
            var vibrantCoral = "#e11d48";
            var freshGreen = "#059669";
            var purpleTone = "#7c3aed";

            var processPalette = [
                "#2563eb", // Selladoras
                "#0284c7", // Flexo
                "#059669", // Seri
                "#8b5cf6", // Pulpo
                "#f59e0b", // Embalaje
                "#64748b"  // Otros
            ];

            // 1. GRÁFICO DE EVOLUCIÓN / TENDENCIA
            var trendCtx = document.getElementById("erpTrendChart");
            var trendChart = null;

            function renderTrend(mode) {
                var isMonthly = (mode === "monthly");
                var source = isMonthly ? data.monthly : data.period;

                if (trendChart) {
                    trendChart.destroy();
                }

                if (!trendCtx) return;

                trendChart = new Chart(trendCtx, {
                    type: "line",
                    data: {
                        labels: source.labels,
                        datasets: [
                            {
                                label: "Confección / Sellado",
                                data: source.confeccion,
                                borderColor: primaryBlue,
                                backgroundColor: "rgba(37, 99, 235, 0.12)",
                                fill: true,
                                tension: 0.35,
                                borderWidth: 2.5,
                                pointRadius: isMonthly ? 5 : 2.5,
                                pointHoverRadius: 6,
                                pointBackgroundColor: primaryBlue
                            },
                            {
                                label: "Impresión (Flexo / Seri)",
                                data: source.impresion,
                                borderColor: primaryCyan,
                                backgroundColor: "rgba(2, 132, 199, 0.08)",
                                fill: true,
                                tension: 0.35,
                                borderWidth: 2.5,
                                pointRadius: isMonthly ? 5 : 2.5,
                                pointHoverRadius: 6,
                                pointBackgroundColor: primaryCyan
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: "index", intersect: false },
                        plugins: {
                            legend: {
                                position: "top",
                                align: "end",
                                labels: { boxWidth: 12, font: { weight: 600, size: 12 } }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        return ctx.dataset.label + ": " + numFmt.format(ctx.parsed.y) + " unid";
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: "#f1f5f9" },
                                ticks: {
                                    callback: function (val) {
                                        if (val >= 1000000) return (val / 1000000).toFixed(1) + "M";
                                        if (val >= 1000) return (val / 1000).toFixed(0) + "k";
                                        return val;
                                    }
                                }
                            },
                            x: {
                                grid: { display: false }
                            }
                        }
                    }
                });
            }

            window.toggleTrendView = function (mode) {
                var btnP = document.getElementById("btnTrendPeriod");
                var btnM = document.getElementById("btnTrendMonthly");
                var title = document.getElementById("trendChartTitle");
                var sub = document.getElementById("trendChartSubtitle") || {};

                if (mode === "monthly") {
                    btnM.classList.add("active");
                    btnP.classList.remove("active");
                    title.innerText = "Tendencia Histórica Mensual";
                    sub.innerText = "Producción acumulada mes a mes (Últimos 6 meses)";
                } else {
                    btnP.classList.add("active");
                    btnM.classList.remove("active");
                    title.innerText = "Evolución de Producción en el Período";
                    sub.innerText = "Unidades producidas día a día (Confección vs Impresión)";
                }
                renderTrend(mode);
            };

            // Renderizado inicial en modo período
            renderTrend(data.period.labels.length > 0 ? "period" : "monthly");

            // 2. GRÁFICO DONUT DE PROCESOS
            var processCtx = document.getElementById("erpProcessDonutChart");
            if (processCtx && data.process.labels.length > 0) {
                new Chart(processCtx, {
                    type: "doughnut",
                    data: {
                        labels: data.process.labels,
                        datasets: [{
                            data: data.process.values,
                            backgroundColor: processPalette,
                            borderWidth: 3,
                            borderColor: "#ffffff",
                            hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: "bottom",
                                labels: { boxWidth: 12, padding: 14, font: { weight: 600, size: 12 } }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        var total = ctx.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                        var val = ctx.parsed;
                                        var pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                        return ctx.label + ": " + numFmt.format(val) + " unid (" + pct + "%)";
                                    }
                                }
                            }
                        },
                        cutout: "68%"
                    }
                });
            }

            // 3. RANKING DE PRODUCCIÓN POR MÁQUINA
            var machCtx = document.getElementById("erpMachineProdChart");
            if (machCtx && data.machines.labels.length > 0) {
                new Chart(machCtx, {
                    type: "bar",
                    data: {
                        labels: data.machines.labels,
                        datasets: [{
                            label: "Unidades",
                            data: data.machines.values,
                            backgroundColor: "rgba(37, 99, 235, 0.85)",
                            hoverBackgroundColor: "#2563eb",
                            borderRadius: 6,
                            maxBarThickness: 32
                        }]
                    },
                    options: {
                        indexAxis: "y",
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        return "Producción: " + numFmt.format(ctx.parsed.x) + " bolsas";
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                grid: { color: "#f1f5f9" },
                                ticks: {
                                    callback: function (val) {
                                        if (val >= 1000000) return (val / 1000000).toFixed(1) + "M";
                                        if (val >= 1000) return (val / 1000).toFixed(0) + "k";
                                        return val;
                                    }
                                }
                            },
                            y: { grid: { display: false } }
                        }
                    }
                });
            }

            // 4. CONTROL DE MERMA POR MÁQUINA
            var wasteCtx = document.getElementById("erpMachineWasteChart");
            if (wasteCtx && data.waste.labels.length > 0) {
                new Chart(wasteCtx, {
                    type: "bar",
                    data: {
                        labels: data.waste.labels,
                        datasets: [{
                            label: "Kg Merma",
                            data: data.waste.values,
                            backgroundColor: "rgba(225, 29, 72, 0.85)",
                            hoverBackgroundColor: "#e11d48",
                            borderRadius: 6,
                            maxBarThickness: 32
                        }]
                    },
                    options: {
                        indexAxis: "y",
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        return "Merma: " + numFmt.format(ctx.parsed.x) + " kg";
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                grid: { color: "#f1f5f9" },
                                ticks: {
                                    callback: function (val) { return val + " kg"; }
                                }
                            },
                            y: { grid: { display: false } }
                        }
                    }
                });
            }

        })();
    </script>';

    $body .= '</div>';

    render('Gráficos', $body);
}

