<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Reportes/Dashboard ERP
//
// Este archivo contiene helpers de UI para el “dashboard” de ERP/Producción, con:
// - Selección de filtros por período operativo (26–25) o por rango.
// - Construcción de datasets/exports (XLS HTML) para métricas del dashboard.
//
// Nota: este proyecto no usa framework; estas funciones son invocadas desde el router principal.
//
// ---
//
// HTTP Module · ERP Reports/Dashboard
//
// This file contains UI helpers for the ERP/Production “dashboard”, including:
// - Filters by operational period (26–25) or by date range.
// - Building datasets/exports (HTML-based XLS) for dashboard metrics.
//
// Note: this project does not use a framework; these functions are called from the main router.
// =============================================================================

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

    $kpis = $service->getErpOnlyProductionDashboardKpis($start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));
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
    $home = $service->getLegacyErpHomeDashboard($userId);
    $dashboardUrl = trim((string)($home['dashboard_url'] ?? ''));

    $body = '<style>
        main { max-width: 1440px !important; width: 100% !important; margin: 0 auto !important; padding: 16px 24px !important; box-sizing: border-box !important; }
        .dashboard-shell { max-width: 100%; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 20px; }
        
        .erp-filter-card { width: 100%; box-sizing: border-box; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 18px 24px; box-shadow: 0 4px 16px rgba(15,23,42,.04); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .erp-filter-title { font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; text-transform: uppercase; }
        .erp-filter-sub { font-size: 13px; color: #64748b; font-weight: 500; margin-top: 2px; }
        .erp-filter-form { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
        .erp-filter-field { display: flex; flex-direction: column; gap: 5px; }
        .erp-filter-field.is-hidden { display: none; }
        .erp-filter-field label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .05em; }
        .erp-filter-field select, .erp-filter-field input { height: 40px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0 12px; background: #f8fafc; font-size: 13px; font-weight: 600; color: #1e293b; outline: none; transition: all .15s ease; }
        .erp-filter-field select:focus, .erp-filter-field input:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
        .btn-filter-apply { height: 40px; padding: 0 20px; border-radius: 10px; background: linear-gradient(135deg,#2563eb,#1d4ed8); color: #fff; font-weight: 700; font-size: 13px; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,.25); transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; }
        .btn-filter-apply:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(37,99,235,.35); }

        .dashboard-kpis-grid { width: 100%; box-sizing: border-box; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
        .kpi-card-premium { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 22px 24px; position: relative; overflow: hidden; box-shadow: 0 4px 16px rgba(15,23,42,.03); transition: all .2s ease; text-decoration: none; display: flex; flex-direction: column; justify-content: space-between; min-height: 160px; box-sizing: border-box; }
        .kpi-card-premium:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(15,23,42,.06); }
        .kpi-card-premium::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
        .kpi-card-premium.blue::before { background: linear-gradient(90deg, #2563eb, #60a5fa); }
        .kpi-card-premium.cyan::before { background: linear-gradient(90deg, #00A9A6, #2dd4bf); }
        .kpi-card-premium.amber::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .kpi-card-premium.active { border-color: #00A9A6; box-shadow: 0 0 0 2px #00A9A6, 0 8px 24px rgba(0,169,166,.15); }
        .kpi-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; width: 100%; }
        .kpi-tag { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: 3px 8px; border-radius: 6px; }
        .kpi-card-premium.blue .kpi-tag { background: #eff6ff; color: #1d4ed8; }
        .kpi-card-premium.cyan .kpi-tag { background: #f0fdfa; color: #0d9488; }
        .kpi-card-premium.amber .kpi-tag { background: #fffbeb; color: #b45309; }
        .kpi-num { font-size: 34px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; line-height: 1.1; text-align: center; }
        .kpi-desc { font-size: 13px; color: #64748b; margin-top: 6px; font-weight: 500; text-align: center; }
        .kpi-footer-hint { margin-top: 12px; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 4px; }

        .alert-card-premium { width: 100%; box-sizing: border-box; background: #fff; border: 1px solid #fed7aa; border-left: 5px solid #dc2626; border-radius: 18px; padding: 20px 24px; box-shadow: 0 6px 20px rgba(220,38,38,.06); }
        .alert-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 14px; }
        .alert-badge { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; font-size: 12px; font-weight: 800; padding: 4px 12px; border-radius: 999px; }
        
        .table-premium { width: 100%; border-collapse: collapse; margin: 0; font-size: 12px; }
        .table-premium th { background: #f8fafc; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: .04em; padding: 10px 12px; border-bottom: 2px solid #e2e8f0; text-align: left; }
        .table-premium th.text-center, .table-premium td.text-center { text-align: center; }
        .table-premium th.text-right, .table-premium td.text-right { text-align: right; }
        .table-premium td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; color: #1e293b; vertical-align: middle; }
        .table-premium tbody tr:hover { background: #f8fafc; }

        @media (max-width: 900px) {
            .dashboard-kpis-grid { grid-template-columns: 1fr; }
            .erp-filter-card { flex-direction: column; align-items: stretch; }
            .erp-filter-form { flex-direction: column; align-items: stretch; }
            .erp-filter-field select, .erp-filter-field input, .btn-filter-apply { width: 100%; }
        }
    </style>';

    $body .= '<div class="dashboard-shell">';
    
    // Header y Filtros Unificados tipo Gráficos
    $formAction = $embeddedInErp ? '/' : '/reports/production-dashboard';
    $body .= '<div class="erp-filter-card">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">📊 PANEL DE CONTROL Y PRODUCCIÓN ERP</div>';
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . ' · Indicadores clave de rendimiento operativo</div>';
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

    $body .= '<button type="submit" class="btn-filter-apply">Aplicar Filtros</button>';
    $body .= '</form>';
    $body .= '</div>';

    $body .= '<script>
        (function () {
            var filterType = document.getElementById("filter_type_dash");
            var form = document.getElementById("erp-dashboard-filter-form");
            function syncFilterFields() {
                if (!filterType || !form) return;
                var mode = filterType.value === "range" ? "range" : "period";
                form.querySelectorAll("[data-filter-group]").forEach(function (field) {
                    var visible = field.getAttribute("data-filter-group") === mode;
                    field.classList.toggle("is-hidden", !visible);
                    field.querySelectorAll("input, select").forEach(function (input) {
                        if (input === filterType) return;
                        input.disabled = !visible;
                    });
                });
            }
            if (filterType && form) {
                filterType.addEventListener("change", syncFilterFields);
                syncFilterFields();
            }
        })();
    </script>';

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
    $body .= '<div class="kpi-desc">Unidades producidas (Corte y Sellado)</div>';
    $body .= '</div>';
    $body .= '<div class="kpi-footer-hint" style="justify-content:center; color:#0d9488;">Total acumulado del período</div>';
    $body .= '</div>';

    // KPI 2: Pendientes
    $body .= '<div class="kpi-card-premium blue">';
    $body .= '<div class="kpi-header-row"><span class="kpi-tag">Plan Abierto</span><span style="font-size:18px;">⏳</span></div>';
    $body .= '<div style="display:flex; flex-direction:column; align-items:center; text-align:center; margin:6px 0;">';
    $body .= '<div class="kpi-num">' . h($fmtInt($pendingUnits)) . '</div>';
    $body .= '<div class="kpi-desc">Unidades pendientes de fabricación</div>';
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
        $body .= '<div style="font-size:16px; font-weight:800; color:#9a3412;">Panel de Faltas: ' . $missingLunchCount . ' Operadores con Turno Iniciado sin Registro de Colación (Límite 14:00)</div>';
        $body .= '<div style="font-size:12.5px; color:#64748b; margin-top:2px;">Jornada del día ' . date('d/m/Y', strtotime($evalDate)) . ': Operarios que iniciaron turno en planta y pasadas las 14:00 hrs aún no registran horario de almuerzo.</div>';
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
    $criticalWasteOrders = $service->getCriticalWasteWorkOrders($start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), 5.0);
    $criticalCount = count($criticalWasteOrders);

    if ($criticalCount > 0) {
        $body .= '<div class="alert-card-premium">';

        $body .= '<div class="alert-header">';
        $body .= '<div style="display:flex; align-items:center; gap:12px;">';
        $body .= '<div style="width:42px; height:42px; border-radius:50%; background:#fee2e2; color:#dc2626; display:flex; align-items:center; justify-content:center; font-size:20px; font-weight:800; flex-shrink:0;">⚠️</div>';
        $body .= '<div>';
        $body .= '<div style="font-size:16px; font-weight:800; color:#991b1b;">Alerta de Calidad: ' . $criticalCount . ' Órdenes de Trabajo con Merma Superior al 5%</div>';
        $body .= '<div style="font-size:12.5px; color:#64748b; margin-top:2px;">Producciones registradas en el período activo con porcentaje de merma crítico que requieren supervisión técnica.</div>';
        $body .= '</div>';
        $body .= '</div>';
        $body .= '<div style="display:flex; align-items:center; gap:10px;">';
        $exportCriticalUrl = '/reports/critical-waste/excel?' . http_build_query($baseParams);
        $body .= '<a class="btn secondary" href="' . h($exportCriticalUrl) . '" style="background:#fff; border-color:#fca5a5; color:#991b1b; font-weight:700; font-size:12px; display:inline-flex; align-items:center; gap:6px;">📥 Descargar Excel (' . $criticalCount . ')</a>';
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

        $details = $service->getErpWasteDashboardDetails($start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));
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
        $body .= '<a class="btn secondary" href="' . h($wasteExcelHref) . '" style="font-weight:700; font-size:12.5px;">📥 Descargar Excel</a>';
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
        $body .= '<div class="erp-prod-muted" style="margin:-4px 0 12px">Desglose oficial clasificado por línea operativa: Impresión, Corte y Sellado, Embalaje y Pulpo Serigráfico.</div>';

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
            $body .= '<div class="erp-prod-muted" style="margin:-4px 0 8px">Basado en merma real registrada por tipo de defecto.</div>';
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
    $home = $service->getLegacyErpHomeDashboard($userId);
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
    echo '<div class="sub">Reporte descargado desde la ventana emergente del Panel ERP</div>';
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

    $kpis = $service->getErpOnlyProductionDashboardKpis($startStr, $endStr);
    $waste = is_array($kpis['waste'] ?? null) ? $kpis['waste'] : [];
    $details = $service->getErpWasteDashboardDetails($startStr, $endStr);

    $wasteUnitsTotal = array_key_exists('waste_units', $waste) && is_numeric($waste['waste_units']) ? (float)$waste['waste_units'] : 0.0;
    $wasteKgTotal = array_key_exists('waste_kg', $waste) && is_numeric($waste['waste_kg']) ? (float)$waste['waste_kg'] : 0.0;
    $wasteBaseTotal = array_key_exists('base_units', $waste) && is_numeric($waste['base_units']) ? (float)$waste['base_units'] : (float)($kpis['produced_units'] ?? 0.0);
    $wasteRequestedTotal = array_key_exists('requested_units', $waste) && is_numeric($waste['requested_units']) ? (float)$waste['requested_units'] : 0.0;
    $wastePercent = $wasteBaseTotal > 0 ? (($wasteUnitsTotal / $wasteBaseTotal) * 100.0) : 0.0;

    $byMachine = is_array($details['by_machine'] ?? null) ? $details['by_machine'] : [];
    $byType = is_array($details['by_type'] ?? null) ? $details['by_type'] : [];
    $top10 = is_array($details['top10'] ?? null) ? $details['top10'] : [];

    $filename = 'detalle-merma-dashboard-' . date('Ymd-His') . '.xlsx';
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

    $criticalWasteOrders = $service->getCriticalWasteWorkOrders($startStr, $endStr, 5.0);

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
                . '<div class="erp-prod-modal-note">Esta lectura cruza el volumen terminado frente a la carga abierta del mismo corte para mostrar cuánto del plan ya se convirtió en producto final.</div>'
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
                . '<div class="erp-prod-modal-note">El backlog muestra la carga que aún no se convierte en producto terminado y ayuda a priorizar programación, materiales y capacidad.</div>'
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
                . '<div class="erp-prod-modal-note">Este indicador permite ver cuánto de lo producido ya salió comercialmente y cuánto volumen sigue retenido o en espera de despacho.</div>'
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
                . '<div class="erp-prod-modal-note">Se muestran las bobinas semielaboradas más recientes para revisar rápidamente disponibilidad, peso y equivalencia aproximada antes de programar corte o sellado.</div>'
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
                . '<div class="erp-prod-modal-note">La merma se calcula sobre el peso procesado informado en producción. Esta ventana ayuda a validar el porcentaje y el peso real perdido en el corte consultado.</div>'
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
                . '<div class="erp-prod-modal-note">Los kilogramos procesados son la base para medir consumo, eficiencia y merma. Aquí puedes contrastar el peso ingresado al proceso frente al aprovechamiento neto.</div>'
                . $processedWorkOrdersHtml,
        ],
    ];

    $body = '<style>
        .erp-prod-shell{display:flex;flex-direction:column;gap:18px}
        .erp-prod-hero{background:linear-gradient(135deg,#0f172a 0%,#1e293b 55%,#2563eb 100%);color:#fff;border-radius:22px;padding:28px 30px;box-shadow:0 24px 60px rgba(15,23,42,.28)}
        .erp-prod-hero-top{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}
        .erp-prod-title{font-size:30px;font-weight:800;line-height:1.1;margin-bottom:8px;text-transform:uppercase}
        .erp-prod-subtitle{max-width:760px;color:rgba(255,255,255,.82);font-size:14px}
        .erp-prod-badges{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
        .erp-prod-badge{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:999px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);font-size:12px;font-weight:700;color:#e2e8f0}
        .erp-prod-hero-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .erp-prod-hero-actions .btn{box-shadow:none}
        .erp-prod-panel{background:#fff;border:1px solid #e5e7eb;border-radius:20px;box-shadow:0 10px 30px rgba(15,23,42,.07);overflow:hidden}
        .erp-prod-panel-header{padding:18px 22px 0 22px}
        .erp-prod-panel-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;padding:16px 22px 8px 22px}
        .erp-prod-panel-title{font-size:18px;font-weight:800;color:#0f172a}
        .erp-prod-panel-sub{font-size:13px;color:#64748b;margin-top:4px}
        .erp-prod-panel-body{padding:18px 22px 22px 22px}
        .erp-collapse-toggle{appearance:none;border:1px solid #e2e8f0;background:#f8fafc;color:#0f172a;border-radius:999px;padding:0 12px;height:34px;min-width:34px;cursor:pointer;font-size:12px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;gap:6px}
        .erp-collapse-toggle .chev{transition:transform .18s ease;font-size:14px;line-height:1}
        .erp-prod-panel.is-collapsed .erp-collapse-toggle .chev{transform:rotate(-90deg)}
        .erp-prod-panel.is-collapsed .erp-prod-panel-body{display:none}
        .erp-prod-panel.is-collapsed .erp-prod-panel-sub{display:none}
        .erp-prod-panel.is-collapsed{border-radius:18px}
        .erp-graph-panel{background:#fff;border:1px solid #e5e7eb;border-radius:20px;box-shadow:0 10px 30px rgba(15,23,42,.07);overflow:hidden}
        .erp-graph-panel-header{padding:18px 22px 0 22px}
        .erp-graph-panel-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;padding:16px 22px 8px 22px}
        .erp-graph-panel-title{font-size:18px;font-weight:800;color:#0f172a}
        .erp-graph-panel-sub{font-size:13px;color:#64748b;margin-top:4px}
        .erp-graph-panel-body{padding:18px 22px 22px 22px}
        .erp-graph-panel.is-collapsed .erp-graph-panel-body{display:none}
        .erp-graph-panel.is-collapsed .erp-graph-panel-sub{display:none}
        .erp-graph-panel.is-collapsed{border-radius:18px}
        .erp-graph-panel.is-collapsed .erp-collapse-toggle .chev{transform:rotate(-90deg)}
        .erp-prod-toolbar{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}
        .erp-prod-field{min-width:220px;display:flex;flex-direction:column;gap:6px}
        .erp-prod-field.is-hidden{display:none}
        .erp-prod-field label{font-size:12px;font-weight:700;color:#475569}
        .erp-prod-field select,.erp-prod-field input{height:42px;border:1px solid #cbd5e1;border-radius:12px;padding:0 12px;background:#fff;box-sizing:border-box}
        .erp-prod-panel-filter{display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap}
        .erp-prod-warehouse-row{cursor:pointer;transition:background-color .18s ease,box-shadow .18s ease}
        .erp-prod-warehouse-row:hover{background:#eff6ff}
        .erp-prod-warehouse-row:focus{outline:none;box-shadow:inset 0 0 0 2px #2563eb}
        .erp-prod-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
        .erp-prod-kpi{background:linear-gradient(180deg,#fff 0%,#f8fafc 100%);border:1px solid #e2e8f0;border-radius:18px;padding:18px 18px 16px 18px;box-shadow:0 10px 24px rgba(15,23,42,.06)}
        .erp-prod-kpi-button{width:100%;appearance:none;text-align:left;cursor:pointer;font:inherit;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
        .erp-prod-kpi-button:hover{transform:translateY(-2px);box-shadow:0 16px 28px rgba(15,23,42,.1);border-color:#bfdbfe}
        .erp-prod-kpi-label{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#64748b;margin-bottom:10px}
        .erp-prod-kpi-value{font-size:32px;font-weight:800;color:#0f172a;line-height:1}
        .erp-prod-kpi-sub{margin-top:10px;color:#475569;font-size:13px}
        .erp-prod-kpi-accent{display:inline-block;margin-top:12px;padding:6px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:700}
        .erp-prod-kpi-hint{margin-top:12px;font-size:12px;font-weight:700;color:#1d4ed8}
        .erp-prod-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(320px,.85fr);gap:18px}
        .erp-prod-callout{background:linear-gradient(180deg,#f8fafc 0%,#eef2ff 100%);border:1px solid #dbeafe;border-radius:18px;padding:16px}
        .erp-prod-callout-title{font-size:13px;font-weight:800;color:#334155;text-transform:uppercase;letter-spacing:.04em}
        .erp-prod-callout-value{font-size:34px;font-weight:800;color:#0f172a;margin-top:8px}
        .erp-prod-callout-sub{font-size:13px;color:#475569;margin-top:8px}
        .erp-prod-mini-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:14px}
        .erp-prod-mini{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px}
        .erp-prod-mini-label{font-size:12px;color:#64748b;font-weight:700}
        .erp-prod-mini-value{margin-top:8px;font-size:22px;font-weight:800;color:#0f172a}
        .erp-prod-table-wrap{overflow:auto}
        .erp-prod-table{width:100%;border-collapse:separate;border-spacing:0}
        .erp-prod-table th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;padding:12px 14px;text-align:left;border-bottom:1px solid #e2e8f0}
        .erp-prod-table td{padding:14px;border-bottom:1px solid #eef2f7;color:#0f172a;vertical-align:middle}
        .erp-prod-table tbody tr:hover{background:#f8fafc}
        .erp-prod-code{font-weight:800;color:#0f172a}
        .erp-prod-muted{color:#64748b;font-size:12px}
        .erp-prod-chip{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:700;font-size:12px}
        .erp-prod-chip-success{background:#dcfce7;color:#166534}
        .erp-prod-chip-warning{background:#fef3c7;color:#92400e}
        .erp-prod-chip-neutral{background:#e2e8f0;color:#334155}
        .erp-prod-occupancy{min-width:180px}
        .erp-prod-bar{height:10px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin-top:6px}
        .erp-prod-bar-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#2563eb 0%,#0ea5e9 100%)}
        .erp-prod-empty{padding:20px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#64748b;text-align:center}
        .erp-prod-modal[hidden]{display:none}
        .erp-prod-modal{position:fixed;inset:0;z-index:1200;display:flex;align-items:center;justify-content:center;padding:24px}
        .erp-prod-modal-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.62)}
        .erp-prod-modal-dialog{position:relative;z-index:1;width:min(920px,100%);max-height:90vh;overflow:auto;background:#fff;border-radius:22px;border:1px solid #e2e8f0;box-shadow:0 24px 80px rgba(15,23,42,.24);padding:22px}
        .erp-prod-modal-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}
        .erp-prod-modal-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .erp-prod-modal-title{font-size:22px;font-weight:800;color:#0f172a;line-height:1.1}
        .erp-prod-modal-sub{margin-top:6px;font-size:13px;color:#64748b}
        .erp-prod-modal-close{appearance:none;border:1px solid #cbd5e1;background:#fff;color:#0f172a;border-radius:999px;width:38px;height:38px;font-size:20px;cursor:pointer}
        .erp-prod-modal-section{margin-top:18px}
        .erp-prod-modal-section-title{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#64748b;margin-bottom:10px}
        .erp-prod-modal-note{margin-top:16px;padding:14px 16px;border-radius:14px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569;font-size:13px}
        @media (max-width: 1024px){.erp-prod-grid{grid-template-columns:1fr}.erp-prod-title{font-size:26px}}
        @media (max-width: 720px){.erp-prod-modal{padding:12px}.erp-prod-modal-dialog{padding:18px}.erp-prod-modal-title{font-size:20px}}
    </style>';

    $body .= '<div class="erp-prod-shell">';
    if (!$embeddedInErp) {
        $body .= '<div class="erp-prod-hero"><div class="erp-prod-hero-top"><div><div class="erp-prod-title">DASHBOARD DE PRODUCCIÓN</div><div class="erp-prod-subtitle">Vista ejecutiva de fabricación, semielaborados, despacho, ocupación de bodegas y merma para seguimiento diario del negocio.</div><div class="erp-prod-badges"><div class="erp-prod-badge">Filtro aplicado: ' . h($activeFilterLabel) . '</div><div class="erp-prod-badge">Modo: ' . h($defaultFilterType === 'range' ? 'Rango de fechas' : 'Período 26 al 25') . '</div><div class="erp-prod-badge">Ocupación máxima: ' . h(number_format($bestOccupancy, 2, '.', '')) . '%</div></div></div><div class="erp-prod-hero-actions"><a class="btn secondary" href="/">Volver al panel ERP</a></div></div></div>';
    }

    $body .= '<div class="erp-prod-panel"><div class="erp-prod-panel-head"><div><div class="erp-prod-panel-title">Filtros del dashboard</div><div class="erp-prod-panel-sub">Puedes consultar por período operativo del 26 al 25 o por un rango personalizado para revisar una semana o cualquier tramo específico.</div></div><button type="button" class="erp-collapse-toggle" data-collapse-toggle aria-label="Colapsar sección"><span class="chev">▾</span></button></div><div class="erp-prod-panel-body">';
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
        $body .= '<div class="erp-prod-modal-head"><div><div class="erp-prod-modal-title" id="' . h($titleId) . '">' . h($card['detail_title']) . '</div><div class="erp-prod-modal-sub">' . h($activeFilterLabel) . '</div></div><div class="erp-prod-modal-actions"><a class="btn secondary" href="' . h((string)($card['export_url'] ?? '#')) . '">Descargar Excel</a><button type="button" class="erp-prod-modal-close" aria-label="Cerrar" data-modal-close="1">&times;</button></div></div>';
        $body .= $card['detail_html'];
        $body .= '</div></div>';
    }

    $body .= '<div class="erp-prod-grid">';
    $body .= '<div class="erp-prod-panel"><div class="erp-prod-panel-head"><div><div class="erp-prod-panel-title">Semielaboradas pendientes</div><div class="erp-prod-panel-sub">Bobinas impresas aún disponibles para pasar a corte, con equivalencia estimada en bolsas.</div></div><button type="button" class="erp-collapse-toggle" data-collapse-toggle aria-label="Colapsar sección"><span class="chev">▾</span></button></div><div class="erp-prod-panel-body">';
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
    $body .= '<div class="erp-prod-panel"><div class="erp-prod-panel-head"><div><div class="erp-prod-panel-title">Merma y lectura ejecutiva</div><div class="erp-prod-panel-sub">Resumen de merma e indicadores ejecutivos listos para revisión de jefatura y planificación.</div></div><button type="button" class="erp-collapse-toggle" data-collapse-toggle aria-label="Colapsar sección"><span class="chev">▾</span></button></div><div class="erp-prod-panel-body">';
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
    $body .= '<div class="erp-prod-panel"><div class="erp-prod-panel-head"><div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;flex:1"><div><div class="erp-prod-panel-title">Nivel de ocupación de bodegas</div><div class="erp-prod-panel-sub">' . h($warehouseFilterSubtitle) . '</div></div><div class="row" style="flex-wrap:wrap;gap:10px;align-items:flex-end"><form method="get" action="' . ($embeddedInErp ? '/' : '/reports/production-dashboard') . '" class="erp-prod-panel-filter" style="margin:0"><div class="erp-prod-field" style="min-width:260px"><label for="warehouse_filter_occupancy">Ver bodega</label><select id="warehouse_filter_occupancy" name="warehouse_filter" onchange="this.form.submit()">' . $warehouseOptionsHtml . '</select></div>' . $warehouseFilterHiddenInputs . '</form><a class="btn secondary" href="' . h($occupancyExcelUrl) . '">Descargar Excel</a></div></div><button type="button" class="erp-collapse-toggle" data-collapse-toggle aria-label="Colapsar sección"><span class="chev">▾</span></button></div><div class="erp-prod-panel-body">';
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
        $body .= '<div class="erp-prod-modal-head"><div><div class="erp-prod-modal-title" id="' . h($titleId) . '">Detalle bodega · ' . h($modalTitle) . '</div><div class="erp-prod-modal-sub">Inventario ubicado actualmente en esta bodega.</div></div><div class="erp-prod-modal-actions"><a class="btn secondary" href="' . h($exportUrl) . '">Descargar Excel</a><button type="button" class="erp-prod-modal-close" aria-label="Cerrar" data-modal-close="1">&times;</button></div></div>';
        $body .= $summaryHtml;
        $body .= $compositionHtml;
        $body .= $rollHtml;
        $body .= $palletHtml;
        $body .= $boxHtml;
        $body .= '<div class="erp-prod-modal-note">Haz clic en cualquier código de bobina, pallet o caja para abrir su trazabilidad completa desde el módulo de inventario.</div>';
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
                var filterType = document.getElementById("filter_type");
                var form = document.getElementById("erp-dashboard-filter-form");

                function syncFilterFields() {
                    if (!filterType || !form) return;
                    var mode = filterType.value === "range" ? "range" : "period";
                    form.querySelectorAll("[data-filter-group]").forEach(function (field) {
                        var visible = field.getAttribute("data-filter-group") === mode;
                        field.classList.toggle("is-hidden", !visible);
                        field.querySelectorAll("input, select").forEach(function (input) {
                            if (input === filterType) return;
                            input.disabled = !visible;
                        });
                    });
                }

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

                if (filterType && form) {
                    filterType.addEventListener("change", syncFilterFields);
                    syncFilterFields();
                }

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
 * Renderiza el listado histórico de inventarios realizados (cabeceras).
 *
 * Desde esta pantalla se puede:
 * - Ver el detalle de una toma.
 * - Descargar el Excel del registro.
 *
 * ---
 *
 * Renders the historical list of performed inventory counts (headers).
 *
 * From this screen you can:
 * - View a count detail.
 * - Download the record Excel.
 */
function unibagRenderInventoryReportsPage(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $msg = trim((string)($_GET['msg'] ?? ''));
    $inventoryCounts = $service->listErpStockcounts(200);

    $body = '<div class="erp-prod-shell" style="max-width:1440px;margin:0 auto">';

    $body .= '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:16px">
        <div>
          <div style="font-size:22px;font-weight:800;color:#0f172a;letter-spacing:-.02em">Informe de Inventario · ERP</div>
          <div class="muted" style="font-size:13px;margin-top:2px">Histórico de recuentos físicos (stockcounts) registrados en la base de datos <code>unibag_unibag</code>.</div>
        </div>
        <div style="display:flex;gap:8px">
          <a class="btn secondary" href="/stock">Consultar stock actual</a>
          <a class="btn" href="/stock/inventory-counts">+ Nueva toma de inventario</a>
        </div>
      </div>';

    if ($msg !== '') {
        $body .= '<div class="ok" style="margin-bottom:14px;padding:12px 16px;font-weight:600;border-radius:10px">' . h($msg) . '</div>';
    }

    $body .= '<div class="card" style="border-radius:14px;padding:0;overflow:hidden;box-shadow:0 2px 10px rgba(15,23,42,.03)">
        <div style="padding:16px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
          <div style="font-weight:800;font-size:15px;color:#0f172a">Recuentos de inventario registrados</div>
          <div class="muted" style="font-size:12px">Total ' . count($inventoryCounts) . ' tomas</div>
        </div>
        <div class="table-wrap">
          <table style="width:100%;margin:0;border-collapse:collapse;font-size:13px">
            <thead>
              <tr style="background:#f1f5f9;border-bottom:2px solid #cbd5e1">
                <th style="padding:10px 14px;text-align:left">N° Recuento</th>
                <th style="padding:10px 14px;text-align:center">Fecha</th>
                <th style="padding:10px 14px;text-align:left">Bodega ERP</th>
                <th style="padding:10px 14px;text-align:left">Glosa / Observación</th>
                <th style="padding:10px 14px;text-align:center">Artículos</th>
                <th style="padding:10px 14px;text-align:right">Stock Sistema</th>
                <th style="padding:10px 14px;text-align:right">Conteo Físico</th>
                <th style="padding:10px 14px;text-align:right">Diferencia</th>
                <th style="padding:10px 14px;text-align:left">Realizado por</th>
                <th style="padding:10px 14px;text-align:center">Acciones</th>
              </tr>
            </thead>
            <tbody>';

    foreach ($inventoryCounts as $count) {
        $inventoryId = (int)($count['id'] ?? 0);
        $stcNum = trim((string)($count['stc_num'] ?? ('IR#' . $inventoryId)));
        $dt = !empty($count['stc_crtdat']) ? date('d.m.Y H:i', (int)$count['stc_crtdat']) : '-';
        $storehouseName = trim((string)($count['storehouse_name'] ?? 'Bodega ERP'));
        $annotation = trim((string)($count['stc_annotation'] ?? ''));
        $itemsCount = (int)($count['items_count'] ?? 0);
        $sysQty = (float)($count['total_system_qty'] ?? 0);
        $phyQty = (float)($count['total_physical_qty'] ?? 0);
        $diffQty = (float)($count['total_diff_qty'] ?? 0);
        $operator = trim((string)($count['operator_name'] ?? 'Sistema'));

        $diffColor = $diffQty === 0.0 ? '#64748b' : ($diffQty > 0 ? '#16a34a' : '#dc2626');

        $body .= '<tr style="border-bottom:1px solid #e2e8f0">
            <td style="padding:10px 14px;font-weight:800;color:#2563eb;white-space:nowrap">
              <a href="/reports/inventory/' . $inventoryId . '">' . h($stcNum) . '</a>
            </td>
            <td style="padding:10px 14px;text-align:center;color:#64748b;white-space:nowrap">' . h($dt) . '</td>
            <td style="padding:10px 14px;font-weight:600;color:#0f172a">' . h($storehouseName) . '</td>
            <td style="padding:10px 14px;color:#475569;max-width:240px">' . h($annotation !== '' ? $annotation : '-') . '</td>
            <td style="padding:10px 14px;text-align:center;font-weight:700">' . number_format($itemsCount, 0, ',', '.') . '</td>
            <td style="padding:10px 14px;text-align:right;font-weight:600">' . number_format($sysQty, 2, ',', '.') . '</td>
            <td style="padding:10px 14px;text-align:right;font-weight:700">' . number_format($phyQty, 2, ',', '.') . '</td>
            <td style="padding:10px 14px;text-align:right;font-weight:800;color:' . $diffColor . '">' . ($diffQty > 0 ? '+' : '') . number_format($diffQty, 2, ',', '.') . '</td>
            <td style="padding:10px 14px;color:#334155;white-space:nowrap">' . h($operator) . '</td>
            <td style="padding:10px 14px;text-align:center;white-space:nowrap">
              <div style="display:inline-flex;gap:6px">
                <a class="btn secondary" href="/reports/inventory/' . $inventoryId . '" style="padding:4px 10px;font-size:12px">Ver</a>
                <a class="btn secondary" href="/reports/inventory/' . $inventoryId . '/excel" style="padding:4px 10px;font-size:12px" title="Descargar Excel">Excel</a>
              </div>
            </td>
          </tr>';
    }

    if ($inventoryCounts === []) {
        $body .= '<tr><td colspan="10" style="padding:30px;text-align:center;color:#64748b">Aún no hay tomas de inventario registradas en el ERP.</td></tr>';
    }

    $body .= '</tbody></table></div></div></div>';

    render('Informe inventario · ERP', $body);
}

/**
 * Emite el Excel del detalle de una toma de inventario desde el ERP.
 */
function unibagOutputInventoryReportExcel(ReceptionService $service, int $inventoryCountId): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $inventoryCount = $service->getErpStockcount($inventoryCountId);
    if ($inventoryCount === null) {
        render('No encontrado', '<div class="card">Inventario no encontrado en el ERP.</div>');
        exit;
    }

    $items = $service->listErpStockcountItems($inventoryCountId);
    $stcNum = trim((string)($inventoryCount['stc_num'] ?? ('IR' . $inventoryCountId)));
    $storehouseName = trim((string)($inventoryCount['storehouse_name'] ?? 'Bodega'));

    $filename = 'inventario-erp-' . $stcNum . '.xlsx';
    ob_start();

    echo '<html><head><meta charset="UTF-8"><style>';
    echo 'body{font-family:Arial,sans-serif;color:#0f172a}';
    echo 'table{border-collapse:collapse;width:100%}';
    echo 'th,td{border:1px solid #cbd5e1;padding:6px 8px}';
    echo 'th{background:#0f172a;color:#fff;font-weight:700;text-align:center}';
    echo 'tr:nth-child(even){background:#f8fafc}';
    echo '.num-dec2{mso-number-format:"\#\,\#\#0\.00";text-align:right}';
    echo '.text-cell{mso-number-format:"\@"}';
    echo '</style></head><body>';
    echo '<table border="1">';
    echo '<tr><th colspan="7" style="font-size:14px;padding:10px;background:#1e293b;color:#fff">Inventario Realizado ' . h($stcNum) . ' - ' . h($storehouseName) . '</th></tr>';
    echo '<tr><td colspan="7"><strong>Fecha:</strong> ' . (!empty($inventoryCount['stc_crtdat']) ? date('d.m.Y H:i', (int)$inventoryCount['stc_crtdat']) : '-') . ' | <strong>Operador:</strong> ' . h((string)($inventoryCount['operator_name'] ?? '')) . ' | <strong>Glosa:</strong> ' . h((string)($inventoryCount['stc_annotation'] ?? '')) . '</td></tr>';
    echo '<tr><th>Código</th><th>Artículo / Descripción</th><th>Unidad</th><th>Stock Sistema</th><th>Conteo Físico</th><th>Diferencia</th><th>Observación</th></tr>';
    foreach ($items as $item) {
        $sysQty = (float)($item['item_amount_stock'] ?? 0);
        $phyQty = (float)($item['item_amount_count'] ?? 0);
        $diffQty = (float)($item['item_amount_book'] ?? 0);
        echo '<tr>';
        echo '<td class="text-cell">' . h((string)($item['item_number_prod'] ?? '')) . '</td>';
        echo '<td>' . h((string)($item['item_title'] ?? '')) . '</td>';
        echo '<td class="text-cell" style="text-align:center">' . h((string)($item['unit_name'] ?? 'UNID')) . '</td>';
        echo '<td class="num-dec2">' . number_format($sysQty, 2, '.', '') . '</td>';
        echo '<td class="num-dec2">' . number_format($phyQty, 2, '.', '') . '</td>';
        echo '<td class="num-dec2">' . number_format($diffQty, 2, '.', '') . '</td>';
        echo '<td>' . h((string)($item['item_comment'] ?? '')) . '</td>';
        echo '</tr>';
    }
    if ($items === []) {
        echo '<tr><td colspan="7" style="text-align:center;padding:10px">Sin líneas registradas</td></tr>';
    }
    echo '</table>';
    echo '</body></html>';
    $html = ob_get_clean();

    SimpleXlsx::streamHtml($filename, $html, 'Inventario');
}

/**
 * Renderiza el detalle (web) de un inventario realizado en el ERP.
 */
function unibagRenderInventoryReportDetailPage(ReceptionService $service, int $inventoryCountId): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $inventoryCount = $service->getErpStockcount($inventoryCountId);
    if ($inventoryCount === null) {
        render('No encontrado', '<div class="card" style="text-align:center"><div style="font-size:18px;font-weight:800;margin-bottom:10px">Inventario no encontrado en el ERP</div><a class="btn secondary" href="/reports/inventory">Volver al listado</a></div>');
        return;
    }

    $items = $service->listErpStockcountItems($inventoryCountId);
    $stcNum = trim((string)($inventoryCount['stc_num'] ?? ('IR#' . $inventoryCountId)));
    $storehouseName = trim((string)($inventoryCount['storehouse_name'] ?? 'Bodega'));
    $dt = !empty($inventoryCount['stc_crtdat']) ? date('d.m.Y H:i', (int)$inventoryCount['stc_crtdat']) : '-';
    $operator = trim((string)($inventoryCount['operator_name'] ?? 'Sistema'));
    $annotation = trim((string)($inventoryCount['stc_annotation'] ?? ''));

    $sysTotal = (float)($inventoryCount['total_system_qty'] ?? 0);
    $phyTotal = (float)($inventoryCount['total_physical_qty'] ?? 0);
    $diffTotal = (float)($inventoryCount['total_diff_qty'] ?? 0);

    $body = '<div class="erp-prod-shell" style="max-width:1440px;margin:0 auto">';

    $body .= '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:16px">
        <div>
          <div style="font-size:22px;font-weight:800;color:#0f172a">Detalle de Inventario Realizado · ' . h($stcNum) . '</div>
          <div class="muted" style="font-size:13px;margin-top:2px">Bodega ERP: <strong>' . h($storehouseName) . '</strong> · ' . h($annotation) . '</div>
        </div>
        <div style="display:flex;gap:8px">
          <a class="btn secondary" href="/reports/inventory">← Volver al listado</a>
          <a class="btn" href="/reports/inventory/' . $inventoryCountId . '/excel" style="display:inline-flex;align-items:center;gap:6px">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Descargar Excel
          </a>
        </div>
      </div>';

    $body .= '<div class="card" style="margin-bottom:16px;border-radius:14px;padding:18px 20px">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
          <div style="background:#f8fafc;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0">
            <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Fecha y hora</div>
            <div style="font-weight:800;font-size:15px;color:#0f172a;margin-top:2px">' . h($dt) . '</div>
          </div>
          <div style="background:#f8fafc;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0">
            <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Responsable</div>
            <div style="font-weight:800;font-size:15px;color:#0f172a;margin-top:2px">' . h($operator) . '</div>
          </div>
          <div style="background:#f8fafc;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0">
            <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Artículos contados</div>
            <div style="font-weight:800;font-size:18px;color:#0f172a;margin-top:2px">' . count($items) . '</div>
          </div>
          <div style="background:#f8fafc;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0">
            <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Stock Sistema</div>
            <div style="font-weight:800;font-size:18px;color:#2563eb;margin-top:2px">' . number_format($sysTotal, 2, ',', '.') . '</div>
          </div>
          <div style="background:#f8fafc;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0">
            <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Conteo Físico</div>
            <div style="font-weight:800;font-size:18px;color:#0f172a;margin-top:2px">' . number_format($phyTotal, 2, ',', '.') . '</div>
          </div>
          <div style="background:#f8fafc;padding:12px 14px;border-radius:10px;border:1px solid #e2e8f0">
            <div class="muted" style="font-size:11px;font-weight:700;text-transform:uppercase">Diferencia neta</div>
            <div style="font-weight:800;font-size:18px;color:' . ($diffTotal === 0.0 ? '#64748b' : ($diffTotal > 0 ? '#16a34a' : '#dc2626')) . ';margin-top:2px">'
              . ($diffTotal > 0 ? '+' : '') . number_format($diffTotal, 2, ',', '.') . '</div>
          </div>
        </div>
      </div>';

    $body .= '<div class="card" style="border-radius:14px;padding:0;overflow:hidden;box-shadow:0 2px 10px rgba(15,23,42,.03)">
        <div style="padding:16px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0">
          <div style="font-weight:800;font-size:15px;color:#0f172a">Líneas de recuento físico</div>
        </div>
        <div class="table-wrap">
          <table style="width:100%;margin:0;border-collapse:collapse;font-size:13px">
            <thead>
              <tr style="background:#f1f5f9;border-bottom:2px solid #cbd5e1">
                <th style="padding:10px 14px;text-align:left">Código</th>
                <th style="padding:10px 14px;text-align:left">Artículo / Descripción</th>
                <th style="padding:10px 14px;text-align:center">Unidad</th>
                <th style="padding:10px 14px;text-align:right">Stock Sistema</th>
                <th style="padding:10px 14px;text-align:right">Conteo Físico</th>
                <th style="padding:10px 14px;text-align:right">Diferencia</th>
                <th style="padding:10px 14px;text-align:left">Observación</th>
              </tr>
            </thead>
            <tbody>';

    foreach ($items as $item) {
        $sysQty = (float)($item['item_amount_stock'] ?? 0);
        $phyQty = (float)($item['item_amount_count'] ?? 0);
        $diffQty = (float)($item['item_amount_book'] ?? 0);
        $diffColor = $diffQty === 0.0 ? '#64748b' : ($diffQty > 0 ? '#16a34a' : '#dc2626');

        $body .= '<tr style="border-bottom:1px solid #e2e8f0">
            <td style="padding:10px 14px;font-weight:700;white-space:nowrap;color:#0f172a">' . h((string)$item['item_number_prod']) . '</td>
            <td style="padding:10px 14px;font-weight:600;color:#334155">' . h((string)$item['item_title']) . '</td>
            <td style="padding:10px 14px;text-align:center;color:#64748b">' . h((string)$item['unit_name']) . '</td>
            <td style="padding:10px 14px;text-align:right;font-weight:600">' . number_format($sysQty, 2, ',', '.') . '</td>
            <td style="padding:10px 14px;text-align:right;font-weight:700">' . number_format($phyQty, 2, ',', '.') . '</td>
            <td style="padding:10px 14px;text-align:right;font-weight:800;color:' . $diffColor . '">' . ($diffQty > 0 ? '+' : '') . number_format($diffQty, 2, ',', '.') . '</td>
            <td style="padding:10px 14px;color:#64748b">' . h((string)($item['item_comment'] !== '' ? $item['item_comment'] : '-')) . '</td>
          </tr>';
    }

    if ($items === []) {
        $body .= '<tr><td colspan="7" style="padding:30px;text-align:center;color:#64748b">No hay líneas en este inventario.</td></tr>';
    }

    $body .= '</tbody></table></div></div></div>';

    render('Detalle inventario ' . $stcNum, $body);
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
        .erp-graph-shell{display:flex;flex-direction:column;gap:20px;max-width:1440px;margin:0 auto}
        .erp-filter-card{background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;padding:18px 24px;box-shadow:0 4px 16px rgba(15,23,42,.04);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px}
        .erp-filter-title{font-size:20px;font-weight:800;color:#0f172a;letter-spacing:-.02em;text-transform:uppercase}
        .erp-filter-sub{font-size:13px;color:#64748b;font-weight:500;margin-top:2px}
        .erp-filter-form{display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap}
        .erp-filter-field{display:flex;flex-direction:column;gap:5px}
        .erp-filter-field.is-hidden{display:none}
        .erp-filter-field label{font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em}
        .erp-filter-field select,.erp-filter-field input{height:40px;border:1px solid #cbd5e1;border-radius:10px;padding:0 12px;background:#f8fafc;font-size:13px;font-weight:600;color:#1e293b;outline:none;transition:all .15s}
        .erp-filter-field select:focus,.erp-filter-field input:focus{border-color:#2563eb;background:#fff;box-shadow:0 0 0 3px rgba(37,99,235,.15)}
        .btn-filter-apply{height:40px;padding:0 20px;border-radius:10px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;font-weight:700;font-size:13px;border:none;cursor:pointer;box-shadow:0 4px 12px rgba(37,99,235,.25);transition:all .15s}
        .btn-filter-apply:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(37,99,235,.35)}

        .erp-kpi-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px}
        .erp-kpi-card{background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:16px;box-shadow:0 4px 16px rgba(15,23,42,.03);position:relative;overflow:hidden;transition:all .2s ease;display:flex;flex-direction:column;justify-content:space-between}
        .erp-kpi-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(15,23,42,.06)}
        .erp-kpi-card::before{content:"";position:absolute;top:0;left:0;right:0;height:4px}
        .erp-kpi-card.blue::before{background:linear-gradient(90deg,#2563eb,#60a5fa)}
        .erp-kpi-card.indigo::before{background:linear-gradient(90deg,#4f46e5,#818cf8)}
        .erp-kpi-card.cyan::before{background:linear-gradient(90deg,#0284c7,#38bdf8)}
        .erp-kpi-card.purple::before{background:linear-gradient(90deg,#7c3aed,#a78bfa)}
        .erp-kpi-card.rose::before{background:linear-gradient(90deg,#e11d48,#fb7185)}
        .erp-kpi-card.amber::before{background:linear-gradient(90deg,#d97706,#fbbf24)}
        .erp-kpi-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:20px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap}
        .erp-kpi-card.blue .erp-kpi-badge{background:#eff6ff;color:#1d4ed8}
        .erp-kpi-card.indigo .erp-kpi-badge{background:#eef2ff;color:#4338ca}
        .erp-kpi-card.cyan .erp-kpi-badge{background:#f0f9ff;color:#0284c7}
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
        .chart-pill.active{background:#ffffff;color:#0f172a;box-shadow:0 2px 6px rgba(0,0,0,.08)}

        @media (max-width: 1400px) {
            .erp-kpi-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
            .erp-grid-2,.erp-grid-2-even{grid-template-columns:1fr}
        }
        @media (max-width: 768px) {
            .erp-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
            .erp-filter-card{flex-direction:column;align-items:stretch}
            .erp-filter-form{flex-direction:column;align-items:stretch}
            .erp-filter-field select,.erp-filter-field input,.btn-filter-apply{width:100%}
        }
        @media (max-width: 480px) {
            .erp-kpi-grid{grid-template-columns:1fr}
        }
    </style>';

    $body .= '<script src="/js/chart.umd.min.js"></script>';
    $body .= '<script>if (typeof Chart === "undefined") { document.write(\'<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"><\\/script>\'); }</script>';

    $body .= '<div class="erp-graph-shell">';

    // Barra de Filtros
    $body .= '<div class="erp-filter-card">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">GRÁFICOS Y MÉTRICAS ERP</div>';
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . ' · Datos consolidados en tiempo real desde la base de datos</div>';
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
    $body .= '<button type="submit" class="btn-filter-apply">Aplicar Filtros</button>';
    $body .= $hiddenInputs;
    $body .= '</form>';
    $body .= '</div>';

    // Tarjetas KPI Separadas por Máquinas / Procesos + Merma
    $body .= '<div class="erp-kpi-grid">';

    // KPI 1: Corte y Sellado
    $body .= '<div class="erp-kpi-card blue">';
    $body .= '<div class="erp-kpi-badge">✂️ Corte y Sellado</div>';
    $body .= '<div class="erp-kpi-val">' . $fmtInt($unitsCorteSellado) . '</div>';
    $body .= '<div class="erp-kpi-sub">Bolsas cortadas y selladas</div>';
    $body .= '</div>';

    // KPI 2: Embalaje
    $body .= '<div class="erp-kpi-card indigo">';
    $body .= '<div class="erp-kpi-badge">📦 Embalaje</div>';
    $body .= '<div class="erp-kpi-val">' . $fmtInt($unitsEmbalaje) . '</div>';
    $body .= '<div class="erp-kpi-sub">Bolsas empacadas y finalizadas</div>';
    $body .= '</div>';

    // KPI 3: Flexografía
    $body .= '<div class="erp-kpi-card cyan">';
    $body .= '<div class="erp-kpi-badge">🖨️ Flexografía</div>';
    $body .= '<div class="erp-kpi-val">' . $fmtInt($unitsFlexo) . '</div>';
    $body .= '<div class="erp-kpi-sub">Impresión continua en flexo</div>';
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
    $body .= '<div class="erp-chart-subtitle" id="trendChartSubtitle">Unidades producidas día a día (Confección vs Impresión)</div>';
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
    $body .= '<div class="erp-chart-subtitle">Participación proporcional de cada línea de producción</div>';
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
    $body .= '<div class="erp-chart-subtitle">Volumen total de unidades producidas por equipo</div>';
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
    $body .= '<div class="erp-chart-subtitle">Kilos de merma generados por cada máquina en el período</div>';
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
                var sub = document.getElementById("trendChartSubtitle");

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

            // Manejador del cambio de tipo de filtro
            var filterType = document.getElementById("filter_type_graph");
            var form = document.getElementById("erp-graph-filter-form");
            function syncFilterFields() {
                if (!filterType || !form) return;
                var mode = filterType.value === "range" ? "range" : "period";
                form.querySelectorAll("[data-filter-group]").forEach(function (field) {
                    var visible = field.getAttribute("data-filter-group") === mode;
                    field.classList.toggle("is-hidden", !visible);
                    field.querySelectorAll("input, select").forEach(function (input) {
                        if (input === filterType) return;
                        input.disabled = !visible;
                    });
                });
            }
            if (filterType && form) {
                filterType.addEventListener("change", syncFilterFields);
                syncFilterFields();
            }
        })();
    </script>';

    $body .= '</div>';

    render('Gráficos', $body);
}

/**
 * Renderiza el informe de personal por máquina.
 *
 * Muestra las asignaciones de trabajadores a equipos/máquinas, agrupadas por equipo.
 * Soporta filtros por período, planta, tipo de equipo y equipo específico.
 *
 * Adaptado de: libs/modules/stats/workers/maquina.php (backup)
 */
function unibagRenderMachineStaffReportPage(ReceptionService $service): void
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

    $plantaId = isset($_GET['planta_id']) && is_numeric($_GET['planta_id']) ? (int)$_GET['planta_id'] : null;
    $equipoTypeId = isset($_GET['equipo_type_id']) && is_numeric($_GET['equipo_type_id']) ? (int)$_GET['equipo_type_id'] : null;
    $equipoId = isset($_GET['equipo_id']) && is_numeric($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : null;

    $report = $service->getMachineStaffReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $plantaId,
        $equipoTypeId,
        $equipoId
    );

    $plantas = $report['plantas'];
    $equipoTypes = $report['equipo_types'];
    $equipoList = $report['equipo_list'];
    $equipos = $report['equipos'];
    $assignments = $report['assignments'];
    $incidents = $report['incidents'];

    // Auto-set planta if not provided
    if ($plantaId === null && !empty($plantas)) {
        $plantaId = (int)$plantas[0]['id'];
    }

    $body = '<style>
        .erp-filter-card { width: 100%; box-sizing: border-box; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px 24px; box-shadow: 0 4px 16px rgba(15,23,42,.04); display: flex; flex-direction: column; gap: 16px; margin-bottom: 16px; }
        .erp-filter-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; width: 100%; }
        .erp-filter-title { font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; text-transform: uppercase; }
        .erp-filter-sub { font-size: 13px; color: #64748b; font-weight: 500; margin-top: 3px; }
        .erp-filter-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .erp-filter-form { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; width: 100%; padding-top: 16px; border-top: 1px solid #f1f5f9; }
        .erp-filter-field { display: flex; flex-direction: column; gap: 5px; }
        .erp-filter-field.is-hidden { display: none !important; }
        .erp-filter-field label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .05em; }
        .erp-filter-field select, .erp-filter-field input { height: 40px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0 12px; background: #f8fafc; font-size: 13px; font-weight: 600; color: #1e293b; outline: none; transition: all .15s ease; box-sizing: border-box; }
        .erp-filter-field select:focus, .erp-filter-field input:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
        .btn-filter-apply { height: 40px; padding: 0 20px; border-radius: 10px; background: linear-gradient(135deg,#2563eb,#1d4ed8); color: #fff; font-weight: 700; font-size: 13px; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,.25); transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; box-sizing: border-box; }
        .btn-filter-apply:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(37,99,235,.35); color: #fff; }
        .btn-filter-secondary { height: 40px; padding: 0 16px; border-radius: 10px; background: #ffffff; color: #334155; font-weight: 700; font-size: 13px; border: 1px solid #cbd5e1; cursor: pointer; transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; box-sizing: border-box; }
        .btn-filter-secondary:hover { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }
        @media (max-width: 900px) {
            .erp-filter-header { flex-direction: column; align-items: stretch; }
            .erp-filter-form { flex-direction: column; align-items: stretch; }
            .erp-filter-field select, .erp-filter-field input, .btn-filter-apply, .btn-filter-secondary { width: 100%; }
        }
    </style>';
    $body .= '<div class="trace-stack">';

    // Card Filtros Unificada tipo PANEL DE CONTROL Y PRODUCCIÓN ERP
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">👥 INFORME PERSONAL POR MÁQUINA</div>';
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . ' · ' . number_format($totalAssignments, 0, ',', '.') . ' asignaciones</div>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-actions">';
    if ($totalAssignments > 0) {
        $body .= '<a class="btn-filter-secondary" href="/reports/machine-staff/excel?' . h($exportParams) . '">📥 Exportar XLS</a>';
    }
    $body .= '<a class="btn-filter-secondary" href="/">← Volver al Dashboard</a>';
    $body .= '</div>';
    $body .= '</div>';

    // Filters Form
    $body .= '<form id="staff-filter-form" method="get" action="/reports/machine-staff" class="erp-filter-form">';
    $body .= '<div class="erp-filter-field">';
    $body .= '<label for="filter_type_staff">Tipo de filtro</label>';
    $body .= '<select id="filter_type_staff" name="filter_type">';
    $body .= '<option value="period"' . ($defaultFilterType === 'period' ? ' selected' : '') . '>Período (26 al 25)</option>';
    $body .= '<option value="range"' . ($defaultFilterType === 'range' ? ' selected' : '') . '>Rango personalizado</option>';
    $body .= '</select></div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'period' ? '' : ' is-hidden') . '" data-filter-group="period">';
    $body .= '<label for="period_staff">Mes del período</label>';
    $body .= '<input id="period_staff" type="month" name="period" value="' . h($periodYm) . '"' . ($defaultFilterType === 'period' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="start_date_staff">Fecha inicio</label>';
    $body .= '<input id="start_date_staff" type="date" name="start_date" value="' . h($rangeStartInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="end_date_staff">Fecha término</label>';
    $body .= '<input id="end_date_staff" type="date" name="end_date" value="' . h($rangeEndInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    // Planta filter
    $body .= '<div class="erp-filter-field" style="min-width:140px">';
    $body .= '<label for="planta_staff">Planta</label>';
    $body .= '<select id="planta_staff" name="planta_id">';
    foreach ($plantas as $planta) {
        $pid = (int)$planta['id'];
        $pname = trim((string)($planta['planta_name'] ?? $pid));
        $body .= '<option value="' . $pid . '"' . ($plantaId === $pid ? ' selected' : '') . '>' . h((string)$pname) . '</option>';
    }
    if (empty($plantas)) {
        $body .= '<option value="">Sin plantas</option>';
    }
    $body .= '</select></div>';

    // Tipo de equipo filter
    $body .= '<div class="erp-filter-field" style="min-width:160px">';
    $body .= '<label for="eqtype_staff">Tipo equipo</label>';
    $body .= '<select id="eqtype_staff" name="equipo_type_id">';
    $body .= '<option value="">Todos los tipos</option>';
    foreach ($equipoTypes as $eqType) {
        $etId = (int)$eqType['id'];
        $body .= '<option value="' . $etId . '"' . ($equipoTypeId === $etId ? ' selected' : '') . '>' . h((string)($eqType['type_ant_title'] ?? '')) . '</option>';
    }
    $body .= '</select></div>';

    // Equipo filter
    $body .= '<div class="erp-filter-field" style="min-width:160px">';
    $body .= '<label for="eq_staff">Equipo</label>';
    $body .= '<select id="eq_staff" name="equipo_id">';
    $body .= '<option value="">Todas las máquinas</option>';
    foreach ($equipoList as $eq) {
        $eqId = (int)$eq['id'];
        $body .= '<option value="' . $eqId . '"' . ($equipoId === $eqId ? ' selected' : '') . '>' . h((string)($eq['equipo_name'] ?? '')) . '</option>';
    }
    $body .= '</select></div>';

    $body .= '<div style="display:flex;gap:8px;align-items:flex-end">';
    $body .= '<button class="btn-filter-apply" type="submit">🔍 Aplicar Filtros</button>';
    $body .= '</div>';
    $body .= '</form>';
    $body .= '</div>';

    $body .= '<script>
        (function () {
            var filterType = document.getElementById("filter_type_staff");
            var form = document.getElementById("staff-filter-form");
            function syncFilterFields() {
                if (!filterType || !form) return;
                var mode = filterType.value === "range" ? "range" : "period";
                form.querySelectorAll("[data-filter-group]").forEach(function (field) {
                    var visible = field.getAttribute("data-filter-group") === mode;
                    field.classList.toggle("is-hidden", !visible);
                    field.querySelectorAll("input, select").forEach(function (input) {
                        if (input === filterType) return;
                        input.disabled = !visible;
                    });
                });
            }
            if (filterType && form) {
                filterType.addEventListener("change", syncFilterFields);
                syncFilterFields();
            }
        })();
    </script>';

    // Data tables grouped by equipment
    $dayNames = ['', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do'];
    if (empty($equipos)) {
        $body .= '<div class="card"><div class="erp-prod-empty">No se encontraron equipos para los filtros seleccionados.</div></div>';
    } else {
        $hasAnyData = false;
        foreach ($equipos as $equipo) {
            $eqId = (int)$equipo['id'];
            $eqName = trim((string)($equipo['equipo_name'] ?? ''));
            $data = $assignments[$eqId] ?? [];
            if (empty($data)) {
                continue;
            }
            $hasAnyData = true;
            $body .= '<div class="card" style="margin-top:12px">';
            $body .= '<div class="dashboard-subtitle" style="margin-bottom:8px">⚙️ ' . h($eqName) . ' <span class="erp-prod-muted" style="font-weight:400;font-size:12px">(' . count($data) . ' registros)</span></div>';
            $body .= '<div class="erp-prod-table-wrap"><table class="erp-prod-table table-compact"><thead><tr>';
            $body .= '<th>Fecha</th><th>Día</th><th>Entrada</th><th>Salida</th><th>Nombres</th><th>Apellidos</th><th>RUT</th><th>Cargo</th><th>Incidencia</th><th>Comentarios</th>';
            $body .= '</tr></thead><tbody>';
            foreach ($data as $row) {
                $assignStamp = (int)($row['assign_stamp'] ?? 0);
                $dateStr = $assignStamp > 0 ? date('d.m.Y', $assignStamp) : '';
                $dayOfWeek = $assignStamp > 0 ? (int)date('N', $assignStamp) : 0;
                $dayLabel = ($dayOfWeek >= 1 && $dayOfWeek <= 7) ? $dayNames[$dayOfWeek] : '';
                $entryHour = sprintf('%02s', (string)($row['ass_init_hour'] ?? '')) . ':' . sprintf('%02s', (string)($row['ass_init_min'] ?? ''));
                $exitHour = sprintf('%02s', (string)($row['ass_end_hour'] ?? '')) . ':' . sprintf('%02s', (string)($row['ass_end_min'] ?? ''));
                $firstName = trim((string)($row['wrk_firstname'] ?? ''));
                $lastName = trim((string)($row['wrk_lastname'] ?? ''));
                $rut = trim((string)($row['wrk_rut'] ?? ''));
                $cargo = trim((string)($row['cargo'] ?? ''));
                $comments = trim((string)($row['ass_comments'] ?? ''));

                // Check for incidents
                $workerId = (int)($row['assign_worker_id'] ?? 0);
                $incidentLabel = '';
                $incidentStyle = '';
                if ($workerId > 0 && isset($incidents[$workerId][$dateStr])) {
                    $inc = $incidents[$workerId][$dateStr];
                    $incidentLabel = trim((string)($inc['inc_name'] ?? ''));
                    $incColor = trim((string)($inc['inc_color'] ?? ''));
                    if ($incColor !== '') {
                        $incidentStyle = 'background-color:' . h($incColor) . ';color:#fff;text-align:center;border-radius:4px;padding:2px 6px';
                    }
                }

                $body .= '<tr>';
                $body .= '<td>' . h($dateStr) . '</td>';
                $body .= '<td style="text-align:center">' . h($dayLabel) . '</td>';
                $body .= '<td style="text-align:center">' . h($entryHour) . '</td>';
                $body .= '<td style="text-align:center">' . h($exitHour) . '</td>';
                $body .= '<td>' . h($firstName) . '</td>';
                $body .= '<td>' . h($lastName) . '</td>';
                $body .= '<td>' . h($rut) . '</td>';
                $body .= '<td>' . h($cargo) . '</td>';
                $body .= '<td' . ($incidentStyle !== '' ? ' style="' . $incidentStyle . '"' : '') . '>' . h($incidentLabel) . '</td>';
                $body .= '<td>' . h($comments) . '</td>';
                $body .= '</tr>';
            }
            $body .= '</tbody></table></div>';
            $body .= '</div>';
        }
        if (!$hasAnyData) {
            $body .= '<div class="card" style="margin-top:12px"><div class="erp-prod-empty">No hay asignaciones de personal para el período y filtros seleccionados.</div></div>';
        }
    }

    $body .= '</div>';
    render('Informe personal por máquina', $body);
}

/**
 * Exporta el informe de personal por máquina a Excel (XLS vía HTML).
 */
function unibagOutputMachineStaffExcel(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    $start = $filters['start'];
    $end = $filters['end'];
    $activeFilterLabel = (string)$filters['active_filter_label'];

    $plantaId = isset($_GET['planta_id']) && is_numeric($_GET['planta_id']) ? (int)$_GET['planta_id'] : null;
    $equipoTypeId = isset($_GET['equipo_type_id']) && is_numeric($_GET['equipo_type_id']) ? (int)$_GET['equipo_type_id'] : null;
    $equipoId = isset($_GET['equipo_id']) && is_numeric($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : null;

    $report = $service->getMachineStaffReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $plantaId,
        $equipoTypeId,
        $equipoId
    );

    $equipos = $report['equipos'];
    $assignments = $report['assignments'];
    $incidents = $report['incidents'];
    $dayNames = ['', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do'];

    $generatedAt = date('d/m/Y H:i');
    $filename = 'informe-personal-por-maquina-' . date('Ymd-His') . '.xlsx';
    ob_start();

    echo '<html><head><meta charset="UTF-8"><style>';
    echo 'body{font-family:Arial,sans-serif;color:#0f172a}';
    echo '.title{font-size:22px;font-weight:700;color:#0f172a}';
    echo '.sub{font-size:12px;color:#475569;margin-bottom:14px}';
    echo '.meta{margin:8px 0 18px 0;font-size:12px;color:#334155}';
    echo '.section{font-size:16px;font-weight:700;margin:22px 0 8px 0;color:#0f172a;border-bottom:2px solid #e2e8f0;padding-bottom:4px}';
    echo '.report{border-collapse:collapse;width:100%;margin-bottom:18px}';
    echo '.report td,.report th{border:1px solid #cbd5e1;padding:8px 10px}';
    echo '.report th{background:#0f172a;color:#fff;text-align:left;font-size:12px}';
    echo '.report tr:nth-child(even) td{background:#f8fafc}';
    echo '</style></head><body>';
    echo '<div class="title">Informe personal por máquina</div>';
    echo '<div class="sub">Unibag · Panel ERP</div>';
    echo '<div class="meta"><strong>Filtro aplicado:</strong> ' . h($activeFilterLabel) . '<br><strong>Generado:</strong> ' . h($generatedAt) . '</div>';

    foreach ($equipos as $equipo) {
        $eqId = (int)$equipo['id'];
        $eqName = trim((string)($equipo['equipo_name'] ?? ''));
        $data = $assignments[$eqId] ?? [];
        if (empty($data)) {
            continue;
        }

        echo '<div class="section">' . h($eqName) . ' (' . count($data) . ' registros)</div>';
        echo '<table class="report"><tr>';
        echo '<th>Fecha</th><th>Día</th><th>Entrada</th><th>Salida</th><th>Nombres</th><th>Apellidos</th><th>RUT</th><th>Cargo</th><th>Incidencia</th><th>Comentarios</th>';
        echo '</tr>';
        foreach ($data as $row) {
            $assignStamp = (int)($row['assign_stamp'] ?? 0);
            $dateStr = $assignStamp > 0 ? date('d.m.Y', $assignStamp) : '';
            $dayOfWeek = $assignStamp > 0 ? (int)date('N', $assignStamp) : 0;
            $dayLabel = ($dayOfWeek >= 1 && $dayOfWeek <= 7) ? $dayNames[$dayOfWeek] : '';
            $entryHour = sprintf('%02s', (string)($row['ass_init_hour'] ?? '')) . ':' . sprintf('%02s', (string)($row['ass_init_min'] ?? ''));
            $exitHour = sprintf('%02s', (string)($row['ass_end_hour'] ?? '')) . ':' . sprintf('%02s', (string)($row['ass_end_min'] ?? ''));
            $firstName = trim((string)($row['wrk_firstname'] ?? ''));
            $lastName = trim((string)($row['wrk_lastname'] ?? ''));
            $rut = trim((string)($row['wrk_rut'] ?? ''));
            $cargo = trim((string)($row['cargo'] ?? ''));
            $comments = trim((string)($row['ass_comments'] ?? ''));

            $workerId = (int)($row['assign_worker_id'] ?? 0);
            $incidentLabel = '';
            if ($workerId > 0 && isset($incidents[$workerId][$dateStr])) {
                $incidentLabel = trim((string)($incidents[$workerId][$dateStr]['inc_name'] ?? ''));
            }

            echo '<tr>';
            echo '<td>' . h($dateStr) . '</td>';
            echo '<td>' . h($dayLabel) . '</td>';
            echo '<td>' . h($entryHour) . '</td>';
            echo '<td>' . h($exitHour) . '</td>';
            echo '<td>' . h($firstName) . '</td>';
            echo '<td>' . h($lastName) . '</td>';
            echo '<td>' . h($rut) . '</td>';
            echo '<td>' . h($cargo) . '</td>';
            echo '<td>' . h($incidentLabel) . '</td>';
            echo '<td>' . h($comments) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    echo '</body></html>';
    $html = ob_get_clean();
    SimpleXlsx::streamHtml($filename, $html, 'Personal Maquina');
}

/**
 * Renderiza el informe de producción por máquina.
 *
 * Muestra las órdenes de trabajo producidas divididas por proceso/tipo de máquina
 * (Flexografía, Corte y Sellado, Serigrafía, Pulpo Serigráfico, Embalaje, Rebobinado),
 * integrando todas las máquinas de cada proceso sin requerir selección máquina a máquina,
 * con la totalidad de campos comerciales, técnicos, operativos, de tiempos y de mermas.
 *
 * Adaptado y unificado de: libs/modules/stats/prod/ (corteysellado, flexo, serigrafia, pulpo, embalaje).
 */
function unibagRenderMachineProductionReportPage(ReceptionService $service): void
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

    $activeProcess = isset($_GET['process']) ? strtolower(trim((string)$_GET['process'])) : 'sellado';
    if ($activeProcess === '' || $activeProcess === 'all') {
        $activeProcess = 'sellado';
    }
    $plantaId = isset($_GET['planta_id']) && is_numeric($_GET['planta_id']) ? (int)$_GET['planta_id'] : null;
    $equipoTypeId = isset($_GET['equipo_type_id']) && is_numeric($_GET['equipo_type_id']) ? (int)$_GET['equipo_type_id'] : null;
    $equipoId = isset($_GET['equipo_id']) && is_numeric($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;
    $canEdit = unibagCanUserPerformModifications();

    $report = $service->getMachineProductionReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $plantaId,
        $equipoTypeId,
        $equipoId,
        $search,
        $activeProcess
    );

    $plantas = $report['plantas'];
    $equipoTypes = $report['equipo_types'];
    $equipos = $report['equipos'];
    $processes = $report['processes'];
    $rows = $report['rows'];
    $summary = $report['summary'];

    $msg = trim((string)($_GET['msg'] ?? ''));
    $error = trim((string)($_GET['error'] ?? ''));
    $allMachines = $service->getAllActiveMachines();
    $allWorkers = $service->getAllActiveWorkers();

    // Auto-set planta if not provided
    if ($plantaId === null && !empty($plantas)) {
        $plantaId = (int)$plantas[0]['id'];
    }

    // Helper para generar URLs con parámetros
    $buildUrl = static function (array $overrides) use ($defaultFilterType, $periodYm, $rangeStartInput, $rangeEndInput, $plantaId, $equipoTypeId, $equipoId, $search, $activeProcess): string {
        $params = [
            'filter_type' => $defaultFilterType,
            'period' => $periodYm,
            'start_date' => $rangeStartInput,
            'end_date' => $rangeEndInput,
            'planta_id' => $plantaId,
            'process' => $activeProcess,
            'equipo_type_id' => $equipoTypeId,
            'equipo_id' => $equipoId,
            'q' => $search,
        ];
        foreach ($overrides as $k => $v) {
            if ($v === null || $v === '') {
                unset($params[$k]);
            } else {
                $params[$k] = $v;
            }
        }
        return '/reports/machine-production?' . http_build_query(array_filter($params, static fn($val) => $val !== null && $val !== ''));
    };

    $body = '';

    // Estilos para centrado armónico y vista panorámica limpia
    $body .= '<style>
        main { max-width: 98% !important; width: 98% !important; margin: 0 auto !important; padding: 16px 24px !important; }
        .report-shell { max-width: 1600px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 14px; }
        .report-tabs { display: flex; justify-content: center; align-items: center; gap: 8px; flex-wrap: wrap; margin: 4px 0 10px 0; }
        .report-tab-btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 700; color: #334155; text-decoration: none; transition: all .15s ease; box-shadow: 0 1px 2px rgba(0,0,0,.03); }
        .report-tab-btn:hover { background: #f8fafc; border-color: #94a3b8; }
        .report-tab-btn.active { background: #00A9A6; color: #fff; border-color: #00A9A6; box-shadow: 0 4px 12px rgba(0,169,166,.28); }
        .report-tab-badge { background: rgba(0,0,0,.08); padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; }
        .report-tab-btn.active .report-tab-badge { background: rgba(255,255,255,.25); color: #fff; }
        .dashboard-filters-grid { justify-content: center; margin: 0 auto; }
        .kpi-grid { justify-content: center; margin: 0 auto; }
        .erp-prod-table th { text-align: center; white-space: nowrap; font-size: 11px; vertical-align: middle; }
        .erp-prod-table td { vertical-align: middle; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .proc-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .proc-badge.flexo { background: #e0f2fe; color: #0369a1; }
        .proc-badge.sellado { background: #fef3c7; color: #b45309; }
        .proc-badge.seri { background: #f3e8ff; color: #7e22ce; }
        .proc-badge.pulpo { background: #fce7f3; color: #be185d; }
        .proc-badge.embalaje { background: #dcfce7; color: #15803d; }
        .proc-badge.rebo { background: #e2e8f0; color: #334155; }
        .proc-badge.otros { background: #f1f5f9; color: #475569; }
        .erp-filter-card { width: 100%; box-sizing: border-box; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px 24px; box-shadow: 0 4px 16px rgba(15,23,42,.04); display: flex; flex-direction: column; gap: 16px; margin-bottom: 6px; }
        .erp-filter-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; width: 100%; }
        .erp-filter-title { font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; text-transform: uppercase; }
        .erp-filter-sub { font-size: 13px; color: #64748b; font-weight: 500; margin-top: 3px; }
        .erp-filter-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .erp-filter-form { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; width: 100%; padding-top: 16px; border-top: 1px solid #f1f5f9; }
        .erp-filter-field { display: flex; flex-direction: column; gap: 5px; }
        .erp-filter-field.is-hidden { display: none !important; }
        .erp-filter-field label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .05em; }
        .erp-filter-field select, .erp-filter-field input { height: 40px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0 12px; background: #f8fafc; font-size: 13px; font-weight: 600; color: #1e293b; outline: none; transition: all .15s ease; box-sizing: border-box; }
        .erp-filter-field select:focus, .erp-filter-field input:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
        .btn-filter-apply { height: 40px; padding: 0 20px; border-radius: 10px; background: linear-gradient(135deg,#2563eb,#1d4ed8); color: #fff; font-weight: 700; font-size: 13px; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,.25); transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; box-sizing: border-box; }
        .btn-filter-apply:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(37,99,235,.35); color: #fff; }
        .btn-filter-secondary { height: 40px; padding: 0 16px; border-radius: 10px; background: #ffffff; color: #334155; font-weight: 700; font-size: 13px; border: 1px solid #cbd5e1; cursor: pointer; transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; box-sizing: border-box; }
        .btn-filter-secondary:hover { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }

        @media (max-width: 900px) {
            .erp-filter-header { flex-direction: column; align-items: stretch; }
            .erp-filter-form { flex-direction: column; align-items: stretch; }
            .erp-filter-field select, .erp-filter-field input, .btn-filter-apply, .btn-filter-secondary { width: 100%; }
        }
    </style>';

    $body .= '<div class="report-shell">';

    if ($msg !== '') {
        $body .= '<div style="padding:12px 18px;border-radius:10px;background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;font-size:14px;font-weight:700;display:flex;align-items:center;gap:10px;box-shadow:0 1px 3px rgba(0,0,0,.05)">✅ ' . h($msg) . '</div>';
    }
    if ($error !== '') {
        $body .= '<div style="padding:12px 18px;border-radius:10px;background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;font-size:14px;font-weight:700;display:flex;align-items:center;gap:10px;box-shadow:0 1px 3px rgba(0,0,0,.05)">⚠️ ' . h($error) . '</div>';
    }

    $subtitle = h($activeFilterLabel) . ' · ' . count($rows) . ' órdenes en total';
    if ($search !== null && $search !== '') {
        $subtitle .= ' · <span style="color:#2563eb;font-weight:600">Buscando: "' . h($search) . '"</span>';
        if (!empty($report['is_historical_search'])) {
            $subtitle .= ' <span class="badge" style="background:#fef3c7;color:#92400e;font-size:11px;margin-left:6px;padding:2px 8px;border-radius:9999px">📅 Coincidencia histórica (fuera del período seleccionado)</span>';
        }
    }

    // Excel export link
    $exportParams = http_build_query(array_filter([
        'filter_type' => $defaultFilterType,
        'period' => $periodYm,
        'start_date' => $rangeStartInput,
        'end_date' => $rangeEndInput,
        'planta_id' => $plantaId,
        'process' => $activeProcess,
        'equipo_type_id' => $equipoTypeId,
        'equipo_id' => $equipoId,
        'q' => $search,
    ], static fn($v) => $v !== null && $v !== '' && $v !== 0));

    // Card Filtros Unificada tipo PANEL DE CONTROL Y PRODUCCIÓN ERP
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">🏭 INFORME DE PRODUCCIÓN POR MÁQUINA</div>';
    $body .= '<div class="erp-filter-sub">' . $subtitle . '</div>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-actions">';
    if (!empty($rows)) {
        $body .= '<a class="btn-filter-secondary" href="/reports/machine-production/excel?' . h($exportParams) . '">📥 Exportar XLS (' . count($rows) . ' OTs)</a>';
    }
    $body .= '<a class="btn-filter-secondary" href="/">← Volver al Dashboard</a>';
    $body .= '</div>';
    $body .= '</div>';

    // TABS DE PROCESOS (Corte y Sellado, Flexografía, Serigrafía, Pulpo, Embalaje, Rebobinado)
    $body .= '<div class="report-tabs" style="margin:2px 0 0 0;justify-content:flex-start">';
    foreach ($processes as $pCode => $pData) {
        $isTabActive = ($pCode === $activeProcess);
        $tabUrl = $buildUrl(['process' => $pCode]);
        $tabTitle = (string)$pData['title'];
        $tabCount = (int)$pData['count'];
        $body .= '<a class="report-tab-btn' . ($isTabActive ? ' active' : '') . '" href="' . h($tabUrl) . '">';
        $body .= '<span>' . h($tabTitle) . '</span>';
        $body .= '<span class="report-tab-badge">' . number_format($tabCount, 0, ',', '.') . '</span>';
        $body .= '</a>';
    }
    $body .= '</div>';

    // Formulario de Filtros
    $body .= '<form id="prod-filter-form" method="get" action="/reports/machine-production" class="erp-filter-form">';
    $body .= '<input type="hidden" name="process" value="' . h($activeProcess) . '">';

    $body .= '<div class="erp-filter-field">';
    $body .= '<label for="filter_type_prod">Tipo de filtro</label>';
    $body .= '<select id="filter_type_prod" name="filter_type">';
    $body .= '<option value="period"' . ($defaultFilterType === 'period' ? ' selected' : '') . '>Período (26 al 25)</option>';
    $body .= '<option value="range"' . ($defaultFilterType === 'range' ? ' selected' : '') . '>Rango personalizado</option>';
    $body .= '</select>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'period' ? '' : ' is-hidden') . '" data-filter-group="period">';
    $body .= '<label for="period_prod">Mes del período</label>';
    $body .= '<input id="period_prod" type="month" name="period" value="' . h($periodYm) . '"' . ($defaultFilterType === 'period' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="start_date_prod">Fecha inicio</label>';
    $body .= '<input id="start_date_prod" type="date" name="start_date" value="' . h($rangeStartInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="end_date_prod">Fecha término</label>';
    $body .= '<input id="end_date_prod" type="date" name="end_date" value="' . h($rangeEndInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    // Planta filter
    $body .= '<div class="erp-filter-field" style="min-width:150px">';
    $body .= '<label for="planta_prod">Planta</label>';
    $body .= '<select id="planta_prod" name="planta_id">';
    foreach ($plantas as $planta) {
        $pid = (int)$planta['id'];
        $pname = trim((string)($planta['planta_name'] ?? $pid));
        $body .= '<option value="' . $pid . '"' . ($plantaId === $pid ? ' selected' : '') . '>' . h((string)$pname) . '</option>';
    }
    if (empty($plantas)) {
        $body .= '<option value="">Sin plantas</option>';
    }
    $body .= '</select></div>';

    // Search filter with clear link
    $body .= '<div class="erp-filter-field" style="flex:1;min-width:220px">';
    $body .= '<label for="q_prod">Buscar OT / CC / Máquina</label>';
    $body .= '<input id="q_prod" type="text" name="q" placeholder="Ej: 3837, OT 3809, 26-00808..." value="' . h((string)$search) . '">';
    $body .= '</div>';

    $body .= '<div style="display:flex;gap:8px;align-items:flex-end">';
    $body .= '<button type="submit" class="btn-filter-apply">🔍 Aplicar Filtros</button>';
    if ($search !== null && $search !== '') {
        $clearUrl = $buildUrl(['q' => null]);
        $body .= '<a class="btn-filter-secondary" href="' . h($clearUrl) . '" title="Quitar búsqueda">✕ Limpiar</a>';
    }
    $body .= '</div>';

    $body .= '</form>';
    $body .= '</div>';

    $body .= '<script>
        (function () {
            var filterType = document.getElementById("filter_type_prod");
            var form = document.getElementById("prod-filter-form");
            function syncFilterFields() {
                if (!filterType || !form) return;
                var mode = filterType.value === "range" ? "range" : "period";
                form.querySelectorAll("[data-filter-group]").forEach(function (field) {
                    var visible = field.getAttribute("data-filter-group") === mode;
                    field.classList.toggle("is-hidden", !visible);
                    field.querySelectorAll("input, select").forEach(function (input) {
                        if (input === filterType) return;
                        input.disabled = !visible;
                    });
                });
            }
            if (filterType && form) {
                filterType.addEventListener("change", syncFilterFields);
                syncFilterFields();
            }
        })();
    </script>';

    // KPIs Resumen
    $body .= '<div class="kpi-grid" style="grid-template-columns:repeat(6,minmax(0,1fr));margin-top:14px">';

    $body .= '<div class="kpi-card text-center">';
    $body .= '<div class="kpi-label">Total OTs</div>';
    $body .= '<div class="kpi-value">' . number_format((float)($summary['total_ots'] ?? 0), 0, ',', '.') . '</div>';
    $body .= '<div class="kpi-sub">En proceso seleccionado</div>';
    $body .= '</div>';

    $body .= '<div class="kpi-card text-center">';
    $body .= '<div class="kpi-label">Unid. Planificadas</div>';
    $body .= '<div class="kpi-value">' . number_format((float)($summary['total_requested_units'] ?? 0), 0, ',', '.') . '</div>';
    $body .= '<div class="kpi-sub">Saldo por producir en período</div>';
    $body .= '</div>';

    $body .= '<div class="kpi-card text-center">';
    $body .= '<div class="kpi-label">Unid. Producidas</div>';
    $body .= '<div class="kpi-value" style="color:#0f766e">' . number_format((float)($summary['total_produced_units'] ?? 0), 0, ',', '.') . '</div>';
    $body .= '<div class="kpi-sub">Total unidades logradas</div>';
    $body .= '</div>';

    $pendingUnits = (float)($summary['total_pending_units'] ?? max(0.0, (float)($summary['total_requested_units'] ?? 0) - (float)($summary['total_produced_units'] ?? 0)));
    $body .= '<div class="kpi-card text-center">';
    $body .= '<div class="kpi-label">Unidades Pendientes</div>';
    $body .= '<div class="kpi-value" style="color:#2563eb">' . number_format($pendingUnits, 0, ',', '.') . '</div>';
    $body .= '<div class="kpi-sub">Planificadas − Producidas</div>';
    $body .= '</div>';

    $wastePctStr = $summary['waste_percent'] !== null ? number_format((float)$summary['waste_percent'], 2, ',', '.') . '%' : '0,00%';
    $body .= '<div class="kpi-card text-center">';
    $body .= '<div class="kpi-label">Merma (Unidades / Kg)</div>';
    $body .= '<div class="kpi-value" style="color:#b91c1c">' . number_format((float)($summary['total_waste_units'] ?? 0), 0, ',', '.') . '</div>';
    $body .= '<div class="kpi-sub">' . h($wastePctStr) . ' · ' . number_format((float)($summary['total_waste_kg'] ?? 0), 2, ',', '.') . ' kg</div>';
    $body .= '</div>';

    $body .= '<div class="kpi-card text-center">';
    $body .= '<div class="kpi-label">Tiempo Total</div>';
    $body .= '<div class="kpi-value">' . number_format((float)($summary['total_hours'] ?? 0), 1, ',', '.') . ' h</div>';
    $body .= '<div class="kpi-sub">Horas de producción</div>';
    $body .= '</div>';

    $body .= '</div>'; // End kpi-grid
    $body .= '</div>'; // End card

    // Tabla de Detalle Completo
    $body .= '<div class="card">';
    $activeProcessTitle = $processes[$activeProcess]['title'] ?? 'General';
    $body .= '<div class="dashboard-head" style="margin-bottom:12px">';
    $body .= '<div class="dashboard-subtitle" style="margin:0">Detalle de Producción · ' . h($activeProcessTitle) . ' (' . count($rows) . ' órdenes registradas)</div>';
    $body .= '</div>';

    if (empty($rows)) {
        $body .= '<div class="erp-prod-empty text-center">No se encontraron registros de producción para los filtros seleccionados.</div>';
    } else {
        $body .= '<div class="erp-prod-table-wrap">';
        $body .= '<table class="erp-prod-table table-compact">';
        $body .= '<thead><tr>';
        $body .= '<th style="width:70px">OT</th>';
        $body .= '<th style="width:75px">CC</th>';
        $body .= '<th style="width:90px">Proceso</th>';
        $body .= '<th>Máquina</th>';
        $body .= '<th>Cliente</th>';
        $body .= '<th>Producto / Formato</th>';
        $body .= '<th>Sustrato / Tela</th>';
        $body .= '<th>Personal</th>';
        $body .= '<th>Fecha Proceso</th>';
        $body .= '<th>Duración</th>';
        $body .= '<th class="text-right">Planificadas</th>';
        $body .= '<th class="text-right">Producidas</th>';
        $body .= '<th class="text-right">Merma</th>';
        $body .= '<th class="text-right">% Merma</th>';
        $body .= '<th class="text-center">Estado</th>';
        $body .= '<th class="text-center" style="width:85px">Acción</th>';
        $body .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $otNum = trim((string)($row['ot_number'] ?? ''));
            $ccNum = trim((string)($row['cc_number'] ?? ''));
            $ccDate = trim((string)($row['cc_init_date'] ?? ''));
            $procCode = trim((string)($row['process_code'] ?? 'otros'));
            $procCat = trim((string)($row['process_category'] ?? 'General'));
            $machineName = trim((string)($row['machine_name'] ?? ''));
            $client = trim((string)($row['customer_name'] ?? ''));
            $itemCode = trim((string)($row['item_code'] ?? ''));
            $itemTitle = trim((string)($row['item_title'] ?? ''));
            $format = trim((string)($row['format_cm'] ?? ''));
            $bagType = trim((string)($row['bag_type'] ?? ''));
            
            // Sustrato / Tela / Gramaje / Color
            $fabType = trim((string)($row['fabric_type'] ?? ''));
            $fabColor = trim((string)($row['fabric_color'] ?? ''));
            $grammage = (float)($row['grammage'] ?? 0);
            $fabDesc = ($fabType !== '' ? $fabType : 'Tela') . ($fabColor !== '' ? ' · ' . $fabColor : '') . ($grammage > 0 ? ' (' . ((int)$grammage) . 'g)' : '');

            // Manilla / Colores
            $manillaColor = trim((string)($row['manilla_color'] ?? ''));
            $colorsFront = trim((string)($row['colors_front'] ?? ''));

            // Personal
            $operator = trim((string)($row['operator_name'] ?? ''));
            $opRut = trim((string)($row['operator_rut'] ?? ''));
            $supervisor = trim((string)($row['supervisor_name'] ?? ''));
            $helper = trim((string)($row['helper_name'] ?? ''));

            // Tiempos
            $startDt = trim((string)($row['started_at'] ?? ''));
            $endDt = trim((string)($row['ended_at'] ?? ''));
            $duration = trim((string)($row['duration_str'] ?? ''));
            $speed = (float)($row['speed_m_min'] ?? 0);

            // Cantidades
            $reqUnits = (float)($row['requested_units'] ?? 0);
            $prodUnits = (float)($row['produced_units'] ?? 0);
            $prodKg = (float)($row['produced_kg'] ?? 0);
            $wasteUnits = (float)($row['waste_units'] ?? 0);
            $wasteKg = (float)($row['waste_kg'] ?? 0);
            $wastePct = $row['waste_percent'];
            $statusLabel = trim((string)($row['status_label'] ?? ''));

            $statusBadgeStyle = 'padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;display:inline-block;';
            if ($statusLabel === 'Terminada') {
                $statusBadgeStyle .= 'background:#dcfce7;color:#166534';
            } elseif ($statusLabel === 'En Curso') {
                $statusBadgeStyle .= 'background:#e0f2fe;color:#075985';
            } else {
                $statusBadgeStyle .= 'background:#f1f5f9;color:#475569';
            }

            $wasteStyle = '';
            if ($wastePct !== null && $wastePct > 5.0) {
                $wasteStyle = 'color:#b91c1c;font-weight:700';
            }

            $wasteKgStr = ($wasteKg > 0) ? '<br><span class="erp-prod-muted" style="font-size:10px">' . number_format($wasteKg, 2, ',', '.') . ' kg</span>' : '';

            $body .= '<tr>';
            $body .= '<td class="erp-prod-code text-center">' . h($otNum) . '</td>';
            $body .= '<td class="text-center">' . h($ccNum) . ($ccDate !== '' ? '<br><span class="erp-prod-muted" style="font-size:10px">' . h($ccDate) . '</span>' : '') . '</td>';
            $body .= '<td class="text-center"><span class="proc-badge ' . h($procCode) . '">' . h($procCat) . '</span></td>';
            $body .= '<td class="text-center"><strong>' . h($machineName) . '</strong></td>';
            $body .= '<td>' . h($client) . '</td>';
            $body .= '<td><strong>' . h($itemTitle) . '</strong>' . ($itemCode !== '' ? ' <span class="erp-prod-muted" style="font-size:11px">[' . h($itemCode) . ']</span>' : '') . ($format !== '' ? '<br><span style="font-size:11px;color:#0f766e;font-weight:700">' . h($format) . '</span>' : '') . ($bagType !== '' ? ' <span class="erp-prod-muted" style="font-size:11px">(' . h($bagType) . ')</span>' : '') . '</td>';
            $body .= '<td style="font-size:11.5px">' . h($fabDesc) . ($manillaColor !== '' ? '<br><span class="erp-prod-muted" style="font-size:10.5px">Manilla: ' . h($manillaColor) . '</span>' : '') . ($colorsFront !== '' ? '<br><span class="erp-prod-muted" style="font-size:10.5px">Colores: ' . h($colorsFront) . '</span>' : '') . '</td>';
            $body .= '<td style="font-size:11.5px">👤 ' . h($operator) . ($supervisor !== '' ? '<br><span class="erp-prod-muted" style="font-size:10.5px">Sup: ' . h($supervisor) . '</span>' : '') . ($helper !== '' ? '<br><span class="erp-prod-muted" style="font-size:10.5px">Ayd: ' . h($helper) . '</span>' : '') . '</td>';
            $body .= '<td class="text-center" style="font-size:11px">' . h($startDt) . '<br>' . h($endDt) . '</td>';
            $totOrder = (float)($row['total_order_units'] ?? 0);
            $priorProd = (float)($row['prior_produced_units'] ?? 0);
            $planSub = '';
            if ($priorProd > 0 && $totOrder > 0) {
                $planSub = '<br><span class="erp-prod-muted" style="font-size:10px" title="Pedido total CC: ' . number_format($totOrder, 0, ',', '.') . ' | Producido antes: ' . number_format($priorProd, 0, ',', '.') . '">de ' . number_format($totOrder, 0, ',', '.') . ' (-' . number_format($priorProd, 0, ',', '.') . ' prev.)</span>';
            }
            $body .= '<td class="text-right"><strong>' . number_format($reqUnits, 0, ',', '.') . '</strong>' . $planSub . '</td>';
            $body .= '<td class="text-right" style="font-weight:700;color:#0f766e">' . number_format($prodUnits, 0, ',', '.') . '</td>';
            $body .= '<td class="text-right" style="' . $wasteStyle . '">' . number_format($wasteUnits, 0, ',', '.') . $wasteKgStr . '</td>';
            $body .= '<td class="text-right" style="' . $wasteStyle . '">' . ($wastePct !== null ? number_format($wastePct, 2, ',', '.') . '%' : '-') . '</td>';
            $body .= '<td class="text-center"><span style="' . $statusBadgeStyle . '">' . h($statusLabel) . '</span></td>';

            $prodRecordJson = json_encode([
                'ot_id' => (int)($row['ot_id'] ?? 0),
                'ot_number' => $otNum,
                'cc_number' => $ccNum,
                'customer_name' => $client,
                'item_title' => $itemTitle,
                'process_category' => $procCat,
                'machine_id' => (int)($row['machine_id'] ?? 0),
                'machine_name' => $machineName,
                'operator_id' => (int)($row['operator_id'] ?? 0),
                'operator_name' => $operator,
                'helper_id' => (int)($row['helper_id'] ?? 0),
                'helper_name' => $helper,
                'status' => (int)($row['wok_status'] ?? 2),
                'status_label' => $statusLabel,
                'started_at' => !empty($row['wok_crtdat']) ? date('Y-m-d\TH:i', (int)$row['wok_crtdat']) : '',
                'ended_at' => !empty($row['wok_enddat']) ? date('Y-m-d\TH:i', (int)$row['wok_enddat']) : '',
                'duration_str' => $duration,
                'requested_units' => $reqUnits,
                'produced_units' => $prodUnits,
                'produced_kg' => $prodKg,
                'produced_meters' => (float)($row['produced_meters'] ?? 0),
                'produced_meters_maquina' => (float)($row['produced_meters_maquina'] ?? 0),
                'waste_units' => $wasteUnits,
                'waste_kg' => $wasteKg,
                'waste_percent' => $wastePct,
                'waste_setup_kg' => (float)($row['waste_setup_kg'] ?? 0),
                'waste_setup_units' => (float)($row['waste_setup_units'] ?? 0),
                'waste_print_kg' => (float)($row['waste_print_kg'] ?? 0),
                'waste_print_units' => (float)($row['waste_print_units'] ?? 0),
                'waste_coil_kg' => (float)($row['waste_coil_kg'] ?? 0),
                'waste_coil_units' => (float)($row['waste_coil_units'] ?? 0),
                'waste_repair_kg' => (float)($row['waste_repair_kg'] ?? 0),
                'waste_repair_units' => (float)($row['waste_repair_units'] ?? 0),
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
 
            if ($canEdit) {
                $body .= '<td class="text-center"><button type="button" class="btn-edit-prod-trigger" data-prod="' . h((string)$prodRecordJson) . '" style="display:inline-flex;align-items:center;gap:4px;padding:4px 9px;font-size:11px;font-weight:700;border-radius:6px;background:#00A9A6;color:#fff;border:none;cursor:pointer;transition:all .15s ease;box-shadow:0 1px 2px rgba(0,0,0,.1)">✏️ Modificar</button></td>';
            } else {
                $body .= '<td class="text-center"><span class="erp-prod-muted" style="font-size:11.5px;color:#94a3b8" title="Modificaciones permitidas únicamente para HECTOR y JAVIER">🔒 Bloqueado</span></td>';
            }
            $body .= '</tr>';
        }
 
        $body .= '</tbody></table>';
        $body .= '</div>'; // End erp-prod-table-wrap
    }
 
    $body .= '</div>'; // End card
    $body .= '</div>'; // End report-shell
 
    if ($canEdit) {
        // MODAL PARA MODIFICAR REGISTRO DE PRODUCCIÓN
        $machinesOptionsHtml = implode('', array_map(static fn($m) => '<option value="' . (int)$m['id'] . '">' . h($m['equipo_name']) . '</option>', $allMachines));
        $workersOptionsHtml = implode('', array_map(static fn($w) => '<option value="' . (int)$w['id'] . '">' . h(trim($w['wrk_firstname'] . ' ' . $w['wrk_lastname'])) . ($w['wrk_rut'] ? ' (' . h($w['wrk_rut']) . ')' : '') . '</option>', $allWorkers));
        $helpersOptionsHtml = '<option value="0">Sin ayudante</option>' . implode('', array_map(static fn($w) => '<option value="' . (int)$w['id'] . '">' . h(trim($w['wrk_firstname'] . ' ' . $w['wrk_lastname'])) . '</option>', $allWorkers));
 
        $body .= '
        <!-- Modal para Modificar Registro de Producción -->
        <div id="modal-prod-edit" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.65);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:16px;">
        <div class="modal-prod-dialog" style="background:#fff;width:min(900px, 98%);max-height:92vh;display:flex;flex-direction:column;border-radius:16px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.3);border:1px solid #e2e8f0;overflow:hidden;">
            <!-- Modal Header -->
            <div style="background:#0f172a;color:#fff;padding:16px 22px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #334155;">
                <div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="font-size:18px;font-weight:800;color:#fff" id="modal-ot-title">Modificar Registro de Producción</span>
                        <span id="modal-badge-process" style="padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:#00A9A6;color:#fff">Proceso</span>
                    </div>
                    <div id="modal-sub-info" style="font-size:12.5px;color:#94a3b8;margin-top:4px">OT / CC / Cliente</div>
                </div>
                <button type="button" id="modal-prod-close-btn" style="background:rgba(255,255,255,.12);border:none;color:#fff;width:32px;height:32px;border-radius:999px;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;transition:background .15s" title="Cerrar">&times;</button>
            </div>

            <!-- Modal Form Scrollable Body -->
            <form id="form-prod-edit" method="post" action="/reports/machine-production/update" style="overflow-y:auto;padding:20px 24px;display:flex;flex-direction:column;gap:18px;margin:0">
                <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
                <input type="hidden" id="edit_ot_id" name="ot_id" value="">
                <input type="hidden" name="redirect_url" value="' . h($_SERVER['REQUEST_URI'] ?? '/reports/machine-production') . '">

                <!-- 1. Asignación Operativa & Estado -->
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 18px;">
                    <div style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#475569;margin-bottom:10px;display:flex;align-items:center;gap:6px">
                        <span>⚙️</span> Asignación Operativa & Estado de Orden
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;">
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px">Máquina de Producción</label>
                            <select id="edit_machine_id" name="machine_id" style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;background:#fff">
                                ' . $machinesOptionsHtml . '
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px">Operador Responsable</label>
                            <select id="edit_operator_id" name="operator_id" style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;background:#fff">
                                ' . $workersOptionsHtml . '
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px">Ayudante (Opcional)</label>
                            <select id="edit_helper_id" name="helper_id" style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;background:#fff">
                                ' . $helpersOptionsHtml . '
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px">Estado de la OT</label>
                            <select id="edit_wok_status" name="wok_status" style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;background:#fff">
                                <option value="2">Terminada</option>
                                <option value="1">En Curso</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 2. Tiempos Operativos -->
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 18px;">
                    <div style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#475569;margin-bottom:10px;display:flex;align-items:center;gap:6px">
                        <span>⏱️</span> Tiempos de Producción
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:12px;align-items:flex-end;">
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px">Fecha / Hora Inicio</label>
                            <input type="datetime-local" id="edit_started_at" name="started_at" style="width:100%;padding:7px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px">
                        </div>
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px">Fecha / Hora Término</label>
                            <input type="datetime-local" id="edit_ended_at" name="ended_at" style="width:100%;padding:7px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px">
                        </div>
                        <div style="padding-bottom:6px">
                            <span style="font-size:11.5px;font-weight:700;color:#64748b">Duración estimada:</span>
                            <span id="edit-duration-calc" style="font-size:13px;font-weight:800;color:#0f172a;margin-left:6px;background:#e2e8f0;padding:4px 8px;border-radius:4px">-</span>
                        </div>
                    </div>
                </div>

                <!-- 3. Producción Obtenida -->
                <div style="background:#f0fdfa;border:1px solid #ccfbf1;border-radius:12px;padding:14px 18px;">
                    <div style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#0f766e;margin-bottom:10px;display:flex;align-items:center;gap:6px">
                        <span>📦</span> Producción Lograda (Valores Reales)
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(190px, 1fr));gap:12px;">
                        <div>
                            <label style="display:block;font-size:12px;font-weight:800;color:#0f766e;margin-bottom:4px">Unidades Producidas *</label>
                            <input type="number" id="edit_produced_units" name="produced_units" min="0" step="1" required style="width:100%;padding:8px 10px;border:2px solid #00A9A6;border-radius:6px;font-size:15px;font-weight:800;color:#0f766e;background:#fff">
                        </div>
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px">Kilos Producidos (Kg)</label>
                            <input type="number" id="edit_produced_kg" name="produced_kg" min="0" step="0.01" style="width:100%;padding:7px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13.5px;background:#fff">
                        </div>
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px">Metros Lineales (m)</label>
                            <input type="number" id="edit_produced_meters" name="produced_meters" min="0" step="0.1" style="width:100%;padding:7px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13.5px;background:#fff">
                        </div>
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px">Metros Máquina</label>
                            <input type="number" id="edit_produced_meters_maquina" name="produced_meters_maquina" min="0" step="0.1" style="width:100%;padding:7px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13.5px;background:#fff">
                        </div>
                    </div>
                </div>

                <!-- 4. Mermas y Desperdicios -->
                <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:14px 18px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                        <div style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#c2410c;display:flex;align-items:center;gap:6px">
                            <span>⚠️</span> Mermas / Desperdicios
                        </div>
                        <div style="display:flex;align-items:center;gap:6px">
                            <span style="font-size:11.5px;color:#7c2d12;font-weight:700">% Merma resultante:</span>
                            <span id="edit-waste-pct-calc" style="font-size:12.5px;font-weight:800;padding:2px 8px;border-radius:4px;border:1px solid #cbd5e1;background:#f8fafc;color:#64748b">0,00%</span>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;margin-bottom:10px">
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px">Total Merma en Kilos (Kg)</label>
                            <input type="number" id="edit_waste_kg" name="waste_kg" min="0" step="0.01" style="width:100%;padding:7px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13.5px;background:#fff">
                        </div>
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px">Total Merma en Unidades</label>
                            <input type="number" id="edit_waste_units" name="waste_units" min="0" step="1" style="width:100%;padding:7px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13.5px;background:#fff">
                        </div>
                    </div>
                    
                    <!-- Desglose específico colapsable -->
                    <details style="margin-top:8px;font-size:12px">
                        <summary style="cursor:pointer;font-weight:700;color:#9a3412">▸ Ajustar desglose específico por tipo de merma (Setup, Impresión, Bobina, Reparación)</summary>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:10px;margin-top:10px;padding-top:10px;border-top:1px dashed #fed7aa">
                            <div>
                                <label style="display:block;font-size:11px;font-weight:600;color:#475569">Setup / Alistamiento (Kg)</label>
                                <input type="number" id="edit_waste_setup_kg" name="waste_setup_kg" min="0" step="0.01" style="width:100%;padding:5px 8px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                                <label style="display:block;font-size:10px;color:#64748b;margin-top:3px">Unidades:</label>
                                <input type="number" id="edit_waste_setup_units" name="waste_setup_units" min="0" step="1" style="width:100%;padding:4px 8px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                            </div>
                            <div>
                                <label style="display:block;font-size:11px;font-weight:600;color:#475569">Impresión (Kg)</label>
                                <input type="number" id="edit_waste_print_kg" name="waste_print_kg" min="0" step="0.01" style="width:100%;padding:5px 8px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                                <label style="display:block;font-size:10px;color:#64748b;margin-top:3px">Unidades:</label>
                                <input type="number" id="edit_waste_print_units" name="waste_print_units" min="0" step="1" style="width:100%;padding:4px 8px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                            </div>
                            <div>
                                <label style="display:block;font-size:11px;font-weight:600;color:#475569">Bobina / Otros (Kg)</label>
                                <input type="number" id="edit_waste_coil_kg" name="waste_coil_kg" min="0" step="0.01" style="width:100%;padding:5px 8px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                                <label style="display:block;font-size:10px;color:#64748b;margin-top:3px">Unidades:</label>
                                <input type="number" id="edit_waste_coil_units" name="waste_coil_units" min="0" step="1" style="width:100%;padding:4px 8px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                            </div>
                            <div>
                                <label style="display:block;font-size:11px;font-weight:600;color:#475569">Reparación (Kg)</label>
                                <input type="number" id="edit_waste_repair_kg" name="waste_repair_kg" min="0" step="0.01" style="width:100%;padding:5px 8px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                                <label style="display:block;font-size:10px;color:#64748b;margin-top:3px">Unidades:</label>
                                <input type="number" id="edit_waste_repair_units" name="waste_repair_units" min="0" step="1" style="width:100%;padding:4px 8px;border:1px solid #cbd5e1;border-radius:4px;font-size:12px">
                            </div>
                        </div>
                    </details>
                </div>

                <!-- 5. Motivo de Modificación / Auditoría -->
                <div>
                    <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px">Motivo de la corrección / Comentarios (Auditoría)</label>
                    <textarea id="edit_comments" name="comments" rows="2" placeholder="Describe brevemente el motivo del ajuste (ej: Corrección de tipeo en las unidades producidas reportadas por el operador)..." style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:12.5px;resize:vertical;font-family:inherit"></textarea>
                </div>

                <!-- Footer Acciones -->
                <div style="display:flex;justify-content:space-between;align-items:center;padding-top:12px;border-top:1px solid #e2e8f0;margin-top:4px">
                    <div id="edit-modal-status" style="display:none;font-size:13px;font-weight:700"></div>
                    <div style="display:flex;align-items:center;gap:10px;margin-left:auto">
                        <button type="button" id="modal-prod-cancel-btn" style="padding:8px 16px;border:1px solid #cbd5e1;background:#fff;color:#475569;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">Cancelar</button>
                        <button type="submit" id="modal-prod-save-btn" style="padding:9px 20px;border:none;background:#00A9A6;color:#fff;border-radius:8px;font-size:13.5px;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 4px 12px rgba(0,169,166,.3);transition:all .15s ease">💾 Guardar Modificación</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Script del Modal de Modificación de Producción -->
    <script>
    (function() {
        var modal = document.getElementById("modal-prod-edit");
        if (!modal) return;
        var form = document.getElementById("form-prod-edit");
        var closeBtn = document.getElementById("modal-prod-close-btn");
        var cancelBtn = document.getElementById("modal-prod-cancel-btn");
        var saveBtn = document.getElementById("modal-prod-save-btn");
        var statusDiv = document.getElementById("edit-modal-status");

        function closeModal() {
            modal.style.display = "none";
            if (statusDiv) { statusDiv.style.display = "none"; statusDiv.textContent = ""; }
        }

        if (closeBtn) closeBtn.addEventListener("click", closeModal);
        if (cancelBtn) cancelBtn.addEventListener("click", closeModal);
        modal.addEventListener("click", function(e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape" && modal.style.display !== "none") closeModal();
        });

        function recalcMetrics() {
            var startInput = document.getElementById("edit_started_at");
            var endInput = document.getElementById("edit_ended_at");
            var durationBadge = document.getElementById("edit-duration-calc");
            if (startInput && endInput && durationBadge) {
                var sVal = startInput.value ? new Date(startInput.value) : null;
                var eVal = endInput.value ? new Date(endInput.value) : null;
                if (sVal && eVal && eVal > sVal) {
                    var diffSecs = Math.floor((eVal - sVal) / 1000);
                    var hrs = Math.floor(diffSecs / 3600);
                    var mins = Math.floor((diffSecs % 3600) / 60);
                    durationBadge.textContent = (hrs < 10 ? "0" : "") + hrs + ":" + (mins < 10 ? "0" : "") + mins + " h";
                } else {
                    durationBadge.textContent = "-";
                }
            }

            var prodUnits = parseFloat(document.getElementById("edit_produced_units").value) || 0;
            var prodKg = parseFloat(document.getElementById("edit_produced_kg").value) || 0;
            var wasteUnits = parseFloat(document.getElementById("edit_waste_units").value) || 0;
            var wasteKg = parseFloat(document.getElementById("edit_waste_kg").value) || 0;
            var wasteBadge = document.getElementById("edit-waste-pct-calc");

            if (wasteBadge) {
                var pct = null;
                if (prodUnits > 0 && wasteUnits > 0) {
                    pct = ((wasteUnits / prodUnits) * 100);
                } else if (prodKg > 0 && wasteKg > 0) {
                    pct = ((wasteKg / prodKg) * 100);
                }
                if (pct !== null) {
                    var pctFixed = pct.toFixed(2) + "%";
                    wasteBadge.textContent = pctFixed;
                    if (pct > 5.0) {
                        wasteBadge.style.background = "#fef2f2";
                        wasteBadge.style.color = "#b91c1c";
                        wasteBadge.style.borderColor = "#fca5a5";
                    } else {
                        wasteBadge.style.background = "#ecfdf5";
                        wasteBadge.style.color = "#047857";
                        wasteBadge.style.borderColor = "#a7f3d0";
                    }
                } else {
                    wasteBadge.textContent = "0,00%";
                    wasteBadge.style.background = "#f8fafc";
                    wasteBadge.style.color = "#64748b";
                    wasteBadge.style.borderColor = "#cbd5e1";
                }
            }
        }

        ["edit_started_at", "edit_ended_at", "edit_produced_units", "edit_produced_kg", "edit_waste_units", "edit_waste_kg"].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener("input", recalcMetrics);
        });

        document.querySelectorAll(".btn-edit-prod-trigger").forEach(function(btn) {
            btn.addEventListener("click", function() {
                var raw = btn.getAttribute("data-prod");
                if (!raw) return;
                try {
                    var d = JSON.parse(raw);
                    document.getElementById("edit_ot_id").value = d.ot_id || "";
                    document.getElementById("modal-ot-title").textContent = "OT " + (d.ot_number || "-");
                    document.getElementById("modal-badge-process").textContent = d.process_category || "General";
                    document.getElementById("modal-sub-info").textContent = "CC: " + (d.cc_number || "-") + " · " + (d.customer_name || "Sin cliente") + " · " + (d.item_title || "");

                    var selMach = document.getElementById("edit_machine_id");
                    if (selMach) selMach.value = d.machine_id || "";
                    var selOp = document.getElementById("edit_operator_id");
                    if (selOp) selOp.value = d.operator_id || "";
                    var selAy = document.getElementById("edit_helper_id");
                    if (selAy) selAy.value = d.helper_id || "0";
                    var selSt = document.getElementById("edit_wok_status");
                    if (selSt) selSt.value = d.status || "2";

                    document.getElementById("edit_started_at").value = d.started_at || "";
                    document.getElementById("edit_ended_at").value = d.ended_at || "";

                    document.getElementById("edit_produced_units").value = d.produced_units || 0;
                    document.getElementById("edit_produced_kg").value = d.produced_kg || 0;
                    document.getElementById("edit_produced_meters").value = d.produced_meters || 0;
                    document.getElementById("edit_produced_meters_maquina").value = d.produced_meters_maquina || 0;

                    document.getElementById("edit_waste_kg").value = d.waste_kg || 0;
                    document.getElementById("edit_waste_units").value = d.waste_units || 0;

                    document.getElementById("edit_waste_setup_kg").value = d.waste_setup_kg || 0;
                    document.getElementById("edit_waste_setup_units").value = d.waste_setup_units || 0;
                    document.getElementById("edit_waste_print_kg").value = d.waste_print_kg || 0;
                    document.getElementById("edit_waste_print_units").value = d.waste_print_units || 0;
                    document.getElementById("edit_waste_coil_kg").value = d.waste_coil_kg || 0;
                    document.getElementById("edit_waste_coil_units").value = d.waste_coil_units || 0;
                    document.getElementById("edit_waste_repair_kg").value = d.waste_repair_kg || 0;
                    document.getElementById("edit_waste_repair_units").value = d.waste_repair_units || 0;

                    document.getElementById("edit_comments").value = "";

                    recalcMetrics();
                    modal.style.display = "flex";
                } catch(e) {
                    console.error("Error cargando registro:", e);
                }
            });
        });

        if (form) {
            form.addEventListener("submit", function(e) {
                e.preventDefault();
                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = "⏳ Guardando...";
                }
                if (statusDiv) {
                    statusDiv.style.display = "none";
                    statusDiv.textContent = "";
                }

                var formData = new FormData(form);
                fetch("/reports/machine-production/update", {
                    method: "POST",
                    headers: {
                        "X-Requested-With": "XMLHttpRequest",
                        "Accept": "application/json"
                    },
                    body: formData
                })
                .then(function(res) {
                    return res.json().then(function(json) { return { ok: res.ok, status: res.status, data: json }; });
                })
                .then(function(result) {
                    if (result.ok && result.data && result.data.ok) {
                        if (statusDiv) {
                            statusDiv.style.display = "block";
                            statusDiv.style.color = "#047857";
                            statusDiv.textContent = "✅ " + (result.data.message || "Guardado con éxito. Actualizando...");
                        }
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    } else {
                        if (saveBtn) {
                            saveBtn.disabled = false;
                            saveBtn.innerHTML = "💾 Guardar Modificación";
                        }
                        var err = (result.data && result.data.error) ? result.data.error : "Error al guardar los cambios.";
                        if (statusDiv) {
                            statusDiv.style.display = "block";
                            statusDiv.style.color = "#b91c1c";
                            statusDiv.textContent = "⚠️ " + err;
                        } else {
                            alert(err);
                        }
                    }
                })
                .catch(function(err) {
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = "💾 Guardar Modificación";
                    }
                    if (statusDiv) {
                        statusDiv.style.display = "block";
                        statusDiv.style.color = "#b91c1c";
                        statusDiv.textContent = "⚠️ Error de conexión al guardar.";
                    } else {
                        alert("Error de conexión al guardar.");
                    }
                });
            });
        }
        })();
        </script>';
    }

    render('Informe de Producción por Máquina', $body);
}

/**
 * Exporta el informe de producción por máquina a Excel (XLS vía HTML con todos los 65 campos del respaldo).
 */
function unibagOutputMachineProductionExcel(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    $start = $filters['start'];
    $end = $filters['end'];
    $activeFilterLabel = (string)$filters['active_filter_label'];

    $activeProcess = isset($_GET['process']) ? strtolower(trim((string)$_GET['process'])) : 'sellado';
    if ($activeProcess === '' || $activeProcess === 'all') {
        $activeProcess = 'sellado';
    }
    $plantaId = isset($_GET['planta_id']) && is_numeric($_GET['planta_id']) ? (int)$_GET['planta_id'] : null;
    $equipoTypeId = isset($_GET['equipo_type_id']) && is_numeric($_GET['equipo_type_id']) ? (int)$_GET['equipo_type_id'] : null;
    $equipoId = isset($_GET['equipo_id']) && is_numeric($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    $report = $service->getMachineProductionReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $plantaId,
        $equipoTypeId,
        $equipoId,
        $search,
        $activeProcess
    );

    $rows = $report['rows'];
    $summary = $report['summary'];
    $generatedAt = date('d/m/Y H:i');
    $procTitle = ($activeProcess !== 'all' && isset($report['processes'][$activeProcess]))
        ? preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower($report['processes'][$activeProcess]['title']))
        : 'general';
    $filename = 'informe-produccion-maquinas-' . $procTitle . '-' . date('Ymd-His') . '.xlsx';
    ob_start();

    echo '<html><head><meta charset="UTF-8"><style>';
    echo 'body{font-family:Arial,sans-serif;color:#0f172a;font-size:12px}';
    echo '.title{font-size:22px;font-weight:700;color:#0f172a}';
    echo '.sub{font-size:12px;color:#475569;margin-bottom:12px}';
    echo '.meta{margin:8px 0 16px 0;font-size:12px;color:#334155}';
    echo '.summary{border-collapse:collapse;margin-bottom:20px}';
    echo '.summary td,.summary th{border:1px solid #cbd5e1;padding:6px 10px;font-size:12px}';
    echo '.summary th{background:#f1f5f9;text-align:left}';
    echo '.report{border-collapse:collapse;width:100%;margin-bottom:18px}';
    echo '.report td,.report th{border:1px solid #cbd5e1;padding:6px 8px;font-size:11px}';
    echo '.report th{background:#0f172a;color:#fff;text-align:center;font-weight:700}';
    echo '.report tr:nth-child(even) td{background:#f8fafc}';
    echo '.num-int{mso-number-format:"\#\,\#\#0";text-align:right}';
    echo '.num-dec{mso-number-format:"\#\,\#\#0\.00";text-align:right}';
    echo '.num-dec1{mso-number-format:"\#\,\#\#0\.0";text-align:right}';
    echo '.text-cell{mso-number-format:"\@"}';
    echo '</style></head><body>';
    echo '<div class="title">Informe de Producción por Máquina</div>';
    echo '<div class="sub">Unibag · Panel ERP · ' . h($report['processes'][$activeProcess]['title'] ?? 'General') . '</div>';
    echo '<div class="meta"><strong>Filtro aplicado:</strong> ' . h($activeFilterLabel) . '<br><strong>Generado:</strong> ' . h($generatedAt) . '</div>';

    // Summary block
    echo '<table class="summary">';
    echo '<tr><th>Total OTs</th><td class="num-int">' . (int)$summary['total_ots'] . '</td>';
    echo '<th>Unid. Planificadas</th><td class="num-int">' . (int)round((float)$summary['total_requested_units']) . '</td>';
    echo '<th>Unid. Producidas</th><td class="num-int">' . (int)round((float)$summary['total_produced_units']) . '</td>';
    $pendingVal = (float)($summary['total_pending_units'] ?? max(0.0, (float)$summary['total_requested_units'] - (float)$summary['total_produced_units']));
    echo '<th>Unidades Pendientes</th><td class="num-int">' . (int)round($pendingVal) . '</td>';
    echo '<th>Total Merma</th><td class="num-int">' . (int)round((float)$summary['total_waste_units']) . ' (' . ($summary['waste_percent'] !== null ? number_format((float)$summary['waste_percent'], 2, ',', '.') . '%' : '0%') . ')</td>';
    echo '<th>Horas Producción</th><td class="num-dec1">' . round((float)$summary['total_hours'], 1) . ' h</td></tr>';
    echo '</table>';

    // Tabla Completa con todos los 65 campos del respaldo
    echo '<table class="report">';
    echo '<tr>';
    echo '<th>Fecha Ingreso CC</th>';                  /* 1 */
    echo '<th>Fecha de Proceso</th>';                  /* 2 */
    echo '<th>Número de CC</th>';                      /* 3 */
    echo '<th>Cantidad Planificada (Bolsas)</th>';      /* 4 */
    echo '<th>Número de OT</th>';                      /* 5 */
    echo '<th>Cliente</th>';                           /* 6 */
    echo '<th>Tipo Bolsa</th>';                        /* 7 */
    echo '<th>Formato Bolsa</th>';                     /* 8 */
    echo '<th>Código Producto</th>';                   /* 9 */
    echo '<th>Descripción Producto</th>';              /* 10 */
    echo '<th>Ancho Bolsa (cm)</th>';                  /* 11 */
    echo '<th>Alto Frente Bolsa (cm)</th>';            /* 12 */
    echo '<th>Alto Dorso Bolsa (cm)</th>';             /* 13 */
    echo '<th>Medida Fuelle Bolsa (cm)</th>';          /* 14 */
    echo '<th>Color Tela</th>';                        /* 15 */
    echo '<th>Código Color Tela</th>';                 /* 16 */
    echo '<th>Gramaje (g/m²)</th>';                    /* 17 */
    echo '<th>Tipo de Tela</th>';                      /* 18 */
    echo '<th>Color Manilla</th>';                     /* 19 */
    echo '<th>Código Color Manilla</th>';              /* 20 */
    echo '<th>Largo Manilla (cm)</th>';                /* 21 */
    echo '<th>Código de Barra</th>';                   /* 22 */
    echo '<th>Alarma / Dispositivo</th>';              /* 23 */
    echo '<th>Pie de Imprenta</th>';                   /* 24 */
    echo '<th>Proceso</th>';                           /* 25 */
    echo '<th>Nombre Máquina</th>';                    /* 26 */
    echo '<th>ID Máquina</th>';                        /* 27 */
    echo '<th>Tipo Máquina</th>';                      /* 28 */
    echo '<th>Nombre Supervisor</th>';                 /* 29 */
    echo '<th>RUT Supervisor</th>';                    /* 30 */
    echo '<th>Nombre Operador</th>';                   /* 31 */
    echo '<th>RUT Operador</th>';                      /* 32 */
    echo '<th>Nombre Ayudante</th>';                   /* 33 */
    echo '<th>RUT Ayudante</th>';                      /* 34 */
    echo '<th>Turno / Jornada</th>';                   /* 35 */
    echo '<th>Horario Asignado</th>';                  /* 36 */
    echo '<th>Fecha y Hora Inicio</th>';               /* 37 */
    echo '<th>Fecha y Hora Término</th>';              /* 38 */
    echo '<th>Total Horas Producción</th>';            /* 39 */
    echo '<th>Horas Alistamiento</th>';                /* 40 */
    echo '<th>Horas Paros</th>';                       /* 41 */
    echo '<th>Velocidad Máquina (m/min)</th>';         /* 42 */
    echo '<th>Unidades Planificadas</th>';              /* 43 */
    echo '<th>Total Producido (Unidades)</th>';        /* 44 */
    echo '<th>Metros Lineales Producidos</th>';        /* 45 */
    echo '<th>Kilos Producidos</th>';                  /* 46 */
    echo '<th>Total Merma (Unidades)</th>';            /* 47 */
    echo '<th>Total Merma (Kgs)</th>';                 /* 48 */
    echo '<th>% Merma</th>';                           /* 49 */
    echo '<th>Merma Alistamiento (Unidades)</th>';     /* 50 */
    echo '<th>Merma Alistamiento (Kgs)</th>';          /* 51 */
    echo '<th>Merma Impresión (Unidades)</th>';        /* 52 */
    echo '<th>Merma Impresión (Kgs)</th>';             /* 53 */
    echo '<th>Merma Bobina Defectuosa (Unid)</th>';    /* 54 */
    echo '<th>Merma Bobina Defectuosa (Kgs)</th>';     /* 55 */
    echo '<th>Bolsas a Reparar (Unidades)</th>';       /* 56 */
    echo '<th>Bolsas a Reparar (Kgs)</th>';            /* 57 */
    echo '<th>Colores Frente</th>';                    /* 58 */
    echo '<th>Colores Dorso</th>';                     /* 59 */
    echo '<th>Estado de Producción</th>';              /* 60 */
    echo '</tr>';

    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td align="center" class="text-cell">' . h((string)($row['cc_init_date'] ?? '')) . '</td>';
        echo '<td align="center" class="text-cell">' . h((string)($row['started_at'] ?? '')) . '</td>';
        echo '<td align="center" class="text-cell">' . h((string)($row['cc_number'] ?? '')) . '</td>';
        echo '<td class="num-int">' . (int)round((float)($row['requested_units'] ?? 0)) . '</td>';
        echo '<td align="center" class="text-cell">' . h((string)($row['ot_number'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['customer_name'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['bag_type'] ?? '')) . '</td>';
        echo '<td align="center" class="text-cell">' . h((string)($row['format_cm'] ?? '')) . '</td>';
        echo '<td class="text-cell">' . h((string)($row['item_code'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['item_title'] ?? '')) . '</td>';
        echo '<td class="num-dec1">' . ((float)($row['width'] ?? 0) > 0 ? round((float)$row['width'], 1) : 0) . '</td>';
        echo '<td class="num-dec1">' . ((float)($row['height'] ?? 0) > 0 ? round((float)$row['height'], 1) : 0) . '</td>';
        echo '<td class="num-dec1">' . ((float)($row['height'] ?? 0) > 0 ? round((float)$row['height'], 1) : 0) . '</td>';
        echo '<td class="num-dec1">' . ((float)($row['fuelle'] ?? 0) > 0 ? round((float)$row['fuelle'], 1) : 0) . '</td>';
        echo '<td>' . h((string)($row['fabric_color'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['fabric_color_code'] ?? '')) . '</td>';
        echo '<td class="num-int">' . (int)round((float)($row['grammage'] ?? 0)) . '</td>';
        echo '<td>' . h((string)($row['fabric_type'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['manilla_color'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['manilla_color_code'] ?? '')) . '</td>';
        echo '<td class="num-dec1">' . ((float)($row['manilla_length'] ?? 0) > 0 ? round((float)$row['manilla_length'], 1) : 0) . '</td>';
        echo '<td align="center">' . h((string)($row['barcode_number'] !== '' ? 'SI (' . $row['barcode_number'] . ')' : 'NO')) . '</td>';
        echo '<td align="center">' . h((string)($row['dispositivo'] !== '' ? 'SI (' . $row['dispositivo'] . ')' : 'NO')) . '</td>';
        echo '<td>' . h((string)($row['pie_imprenta'] ?? '')) . '</td>';
        echo '<td align="center">' . h((string)($row['process_category'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['machine_name'] ?? '')) . '</td>';
        echo '<td align="center" class="text-cell">' . h((string)($row['machine_id'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['machine_type_title'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['supervisor_name'] ?? '')) . '</td>';
        echo '<td align="center" class="text-cell">' . h((string)($row['supervisor_rut'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['operator_name'] ?? '')) . '</td>';
        echo '<td align="center" class="text-cell">' . h((string)($row['operator_rut'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['helper_name'] ?? '')) . '</td>';
        echo '<td align="center" class="text-cell">' . h((string)($row['helper_rut'] ?? '')) . '</td>';
        echo '<td align="center">' . h((string)($row['shift_name'] ?? '')) . '</td>';
        echo '<td align="center" class="text-cell">' . h((string)($row['shift_hours'] ?? '')) . '</td>';
        echo '<td align="center" class="text-cell">' . h((string)($row['started_at'] ?? '')) . '</td>';
        echo '<td align="center" class="text-cell">' . h((string)($row['ended_at'] ?? '')) . '</td>';
        echo '<td align="center" class="text-cell">' . h((string)($row['duration_str'] ?? '')) . '</td>';
        echo '<td class="num-dec">' . round((float)($row['setup_hours'] ?? 0), 2) . '</td>';
        echo '<td class="num-dec">' . round((float)($row['pause_hours'] ?? 0), 2) . '</td>';
        echo '<td class="num-dec1">' . round((float)($row['speed_m_min'] ?? 0), 1) . '</td>';
        echo '<td class="num-int">' . (int)round((float)($row['requested_units'] ?? 0)) . '</td>';
        echo '<td class="num-int">' . (int)round((float)($row['produced_units'] ?? 0)) . '</td>';
        echo '<td class="num-int">' . (int)round((float)($row['produced_meters'] ?? 0)) . '</td>';
        echo '<td class="num-dec">' . round((float)($row['produced_kg'] ?? 0), 2) . '</td>';
        echo '<td class="num-int">' . (int)round((float)($row['waste_units'] ?? 0)) . '</td>';
        echo '<td class="num-dec">' . round((float)($row['waste_kg'] ?? 0), 2) . '</td>';
        echo '<td align="right">' . ($row['waste_percent'] !== null ? number_format((float)$row['waste_percent'], 2, ',', '.') . '%' : '-') . '</td>';
        echo '<td class="num-int">' . (int)round((float)($row['waste_setup_units'] ?? 0)) . '</td>';
        echo '<td class="num-dec">' . round((float)($row['waste_setup_kg'] ?? 0), 2) . '</td>';
        echo '<td class="num-int">' . (int)round((float)($row['waste_print_units'] ?? 0)) . '</td>';
        echo '<td class="num-dec">' . round((float)($row['waste_print_kg'] ?? 0), 2) . '</td>';
        echo '<td class="num-int">' . (int)round((float)($row['waste_coil_units'] ?? 0)) . '</td>';
        echo '<td class="num-dec">' . round((float)($row['waste_coil_kg'] ?? 0), 2) . '</td>';
        echo '<td class="num-int">' . (int)round((float)($row['waste_repair_units'] ?? 0)) . '</td>';
        echo '<td class="num-dec">' . round((float)($row['waste_repair_kg'] ?? 0), 2) . '</td>';
        echo '<td>' . h((string)($row['colors_front'] ?? '')) . '</td>';
        echo '<td>' . h((string)($row['colors_back'] ?? '')) . '</td>';
        echo '<td align="center">' . h((string)($row['status_label'] ?? '')) . '</td>';
        echo '</tr>';
    }

    echo '</table>';
    echo '</body></html>';
    $html = ob_get_clean();

    SimpleXlsx::streamHtml($filename, $html, 'Produccion Maquinas');
}

/**
 * Router de reportes ERP (/reports/*) y dashboard.
 *
 * Mapea rutas a pantallas o exports:
 * - GET /                            dashboard ERP (según área)
 * - GET /reports/graphics            gráficos
 * - GET /reports/production-dashboard dashboard producción
 * - GET /reports/production-dashboard/excel export tarjeta métrica (metric=...)
 * - GET /reports/occupancy/excel     export ocupación (opcional warehouse_filter)
 * - GET /reports/occupancy/{code}/excel export ocupación por bodega
 * - GET /reports/inventory           listado inventarios
 * - GET /reports/inventory/{id}      detalle inventario
 * - GET /reports/inventory/{id}/excel export detalle inventario
 * - GET /reports/machine-staff       informe personal por máquina
 * - GET /reports/machine-staff/excel export informe personal por máquina
 * - GET /reports/machine-production  informe producción por máquina
 * - GET /reports/machine-production/excel export producción por máquina
 *
 * ---
 *
 * ERP reports (/reports/*) and dashboard router.
 *
 * Maps routes to screens or exports:
 * - GET /                            ERP dashboard (area-aware)
 * - GET /reports/graphics            charts
 * - GET /reports/production-dashboard production dashboard
 * - GET /reports/production-dashboard/excel metric card export (metric=...)
 * - GET /reports/occupancy/excel     occupancy export (optional warehouse_filter)
 * - GET /reports/occupancy/{code}/excel occupancy export for a single warehouse
 * - GET /reports/inventory           inventory counts list
/**
 * Renderiza el Informe de Colación.
 * Adaptado y corregido de: libs/modules/stats/detencion/colacion.php
 */
function unibagRenderColacionReportPage(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    $start = $filters['start'];
    $end = $filters['end'];
    $defaultFilterType = (string)$filters['filter_type'];
    $periodYm = (string)$filters['period'];
    $rangeStartInput = (string)$filters['start_date'];
    $rangeEndInput = (string)$filters['end_date'];
    $activeFilterLabel = (string)$filters['active_filter_label'];

    $plantaId = isset($_GET['planta_id']) && is_numeric($_GET['planta_id']) ? (int)$_GET['planta_id'] : null;
    $equipoTypeId = isset($_GET['equipo_type_id']) && is_numeric($_GET['equipo_type_id']) ? (int)$_GET['equipo_type_id'] : null;
    $equipoId = isset($_GET['equipo_id']) && is_numeric($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    $report = $service->getMachineBreaksReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $plantaId,
        $equipoTypeId,
        $equipoId,
        $search
    );

    $plantas = $report['plantas'];
    $rows = $report['rows'];
    $summary = $report['summary'];

    if ($plantaId === null && !empty($plantas)) {
        $plantaId = (int)$plantas[0]['id'];
    }

    $exportParams = http_build_query(array_filter([
        'filter_type' => $defaultFilterType,
        'period' => $periodYm,
        'start_date' => $rangeStartInput,
        'end_date' => $rangeEndInput,
        'planta_id' => $plantaId,
        'equipo_type_id' => $equipoTypeId,
        'equipo_id' => $equipoId,
        'q' => $search,
    ], static fn($v) => $v !== null && $v !== '' && $v !== 0));

    $body = '<style>
        main { max-width: 98% !important; width: 98% !important; margin: 0 auto !important; padding: 16px 24px !important; box-sizing: border-box !important; }
        .report-shell { max-width: 1600px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 18px; }
        
        .erp-filter-card { width: 100%; box-sizing: border-box; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px 24px; box-shadow: 0 4px 16px rgba(15,23,42,.04); display: flex; flex-direction: column; gap: 16px; margin-bottom: 6px; }
        .erp-filter-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; width: 100%; }
        .erp-filter-title { font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; text-transform: uppercase; }
        .erp-filter-sub { font-size: 13px; color: #64748b; font-weight: 500; margin-top: 3px; }
        .erp-filter-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .erp-filter-form { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; width: 100%; padding-top: 16px; border-top: 1px solid #f1f5f9; }
        .erp-filter-field { display: flex; flex-direction: column; gap: 5px; }
        .erp-filter-field.is-hidden { display: none !important; }
        .erp-filter-field label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .05em; }
        .erp-filter-field select, .erp-filter-field input { height: 40px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0 12px; background: #f8fafc; font-size: 13px; font-weight: 600; color: #1e293b; outline: none; transition: all .15s ease; box-sizing: border-box; }
        .erp-filter-field select:focus, .erp-filter-field input:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
        .btn-filter-apply { height: 40px; padding: 0 20px; border-radius: 10px; background: linear-gradient(135deg,#2563eb,#1d4ed8); color: #fff; font-weight: 700; font-size: 13px; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,.25); transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; box-sizing: border-box; }
        .btn-filter-apply:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(37,99,235,.35); color: #fff; }
        .btn-filter-secondary { height: 40px; padding: 0 16px; border-radius: 10px; background: #ffffff; color: #334155; font-weight: 700; font-size: 13px; border: 1px solid #cbd5e1; cursor: pointer; transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; box-sizing: border-box; }
        .btn-filter-secondary:hover { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }

        .erp-prod-table th { text-align: center; white-space: nowrap; font-size: 11px; vertical-align: middle; }
        .erp-prod-table td { vertical-align: middle; }

        @media (max-width: 900px) {
            .erp-filter-header { flex-direction: column; align-items: stretch; }
            .erp-filter-form { flex-direction: column; align-items: stretch; }
            .erp-filter-field select, .erp-filter-field input, .btn-filter-apply, .btn-filter-secondary { width: 100%; }
        }
    </style>';

    $body .= '<div class="report-shell">';

    // Card Filtros Unificada tipo PANEL DE CONTROL Y PRODUCCIÓN ERP
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">☕ INFORME DE COLACIÓN</div>';
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . ' · ' . count($rows) . ' registros encontrados</div>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-actions">';
    if (!empty($rows)) {
        $body .= '<a class="btn-filter-secondary" href="/reports/colacion/excel?' . h($exportParams) . '">📥 Exportar XLS (' . count($rows) . ')</a>';
    }
    $body .= '<a class="btn-filter-secondary" href="/">← Volver al Dashboard</a>';
    $body .= '</div>';
    $body .= '</div>';

    // Formulario de Filtros
    $body .= '<form id="colacion-filter-form" method="get" action="/reports/colacion" class="erp-filter-form">';
    $body .= '<div class="erp-filter-field">';
    $body .= '<label for="filter_type_col">Tipo de filtro</label>';
    $body .= '<select id="filter_type_col" name="filter_type">';
    $body .= '<option value="period"' . ($defaultFilterType === 'period' ? ' selected' : '') . '>Período (26 al 25)</option>';
    $body .= '<option value="range"' . ($defaultFilterType === 'range' ? ' selected' : '') . '>Rango personalizado</option>';
    $body .= '</select>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'period' ? '' : ' is-hidden') . '" data-filter-group="period">';
    $body .= '<label for="period_col">Mes del período</label>';
    $body .= '<input id="period_col" type="month" name="period" value="' . h($periodYm) . '"' . ($defaultFilterType === 'period' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="start_date_col">Fecha inicio</label>';
    $body .= '<input id="start_date_col" type="date" name="start_date" value="' . h($rangeStartInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="end_date_col">Fecha término</label>';
    $body .= '<input id="end_date_col" type="date" name="end_date" value="' . h($rangeEndInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field" style="min-width:150px">';
    $body .= '<label for="planta_col">Planta</label>';
    $body .= '<select id="planta_col" name="planta_id">';
    foreach ($plantas as $planta) {
        $pid = (int)$planta['id'];
        $pname = trim((string)($planta['planta_name'] ?? $pid));
        $body .= '<option value="' . $pid . '"' . ($plantaId === $pid ? ' selected' : '') . '>' . h((string)$pname) . '</option>';
    }
    $body .= '</select></div>';

    $body .= '<div class="erp-filter-field" style="flex:1;min-width:220px">';
    $body .= '<label for="q_col">Buscar Operario / Máquina / OT</label>';
    $body .= '<input id="q_col" type="text" name="q" placeholder="Ej: Hugo Ceballos, Selladora, 3607..." value="' . h((string)$search) . '">';
    $body .= '</div>';

    $body .= '<div style="display:flex;gap:8px;align-items:flex-end">';
    $body .= '<button class="btn-filter-apply" type="submit">🔍 Aplicar Filtros</button>';
    if ($search !== null && $search !== '') {
        $body .= '<a class="btn-filter-secondary" href="/reports/colacion" title="Limpiar">✕ Limpiar</a>';
    }
    $body .= '</div>';

    $body .= '</form>';
    $body .= '</div>';

    $body .= '<script>
        (function () {
            var filterType = document.getElementById("filter_type_col");
            var form = document.getElementById("colacion-filter-form");
            function syncFilterFields() {
                if (!filterType || !form) return;
                var mode = filterType.value === "range" ? "range" : "period";
                form.querySelectorAll("[data-filter-group]").forEach(function (field) {
                    var visible = field.getAttribute("data-filter-group") === mode;
                    field.classList.toggle("is-hidden", !visible);
                    field.querySelectorAll("input, select").forEach(function (input) {
                        if (input === filterType) return;
                        input.disabled = !visible;
                    });
                });
            }
            if (filterType && form) {
                filterType.addEventListener("change", syncFilterFields);
                syncFilterFields();
            }
        })();
    </script>';

    // KPIs Card
    $body .= '<div class="card" style="border-radius:18px;border:1px solid #e2e8f0;padding:20px 24px;box-shadow:0 4px 16px rgba(15,23,42,.03)">';
    $body .= '<div class="kpi-grid" style="grid-template-columns:repeat(5,minmax(0,1fr))">';
    $body .= '<div class="kpi-card"><div class="kpi-label">Total Registros</div><div class="kpi-value">' . number_format($summary['total_records'], 0, ',', '.') . '</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Terminadas</div><div class="kpi-value" style="color:#16a34a">' . number_format($summary['completed_records'], 0, ',', '.') . '</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">En Curso</div><div class="kpi-value" style="color:#0284c7">' . number_format($summary['in_progress_records'], 0, ',', '.') . '</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Total Horas</div><div class="kpi-value">' . number_format($summary['total_hours'], 1, ',', '.') . ' h</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Promedio Duración</div><div class="kpi-value">' . number_format($summary['avg_minutes'], 1, ',', '.') . ' min</div></div>';
    $body .= '</div></div>';

    // Tabla
    $body .= '<div class="card">';
    if (empty($rows)) {
        $body .= '<div class="erp-prod-empty text-center">No se encontraron registros de colación para los filtros seleccionados.</div>';
    } else {
        $body .= '<div class="erp-prod-table-wrap"><table class="erp-prod-table table-compact">';
        $body .= '<thead><tr>';
        $body .= '<th>Inicio Turno</th><th>Fin Turno</th><th>Turno</th><th>Máquina</th><th>Colaborador</th><th>OT / CC</th><th>Inicio Colación</th><th>Fin Colación</th><th class="text-right">Duración</th><th class="text-center">Estado</th><th>Observaciones</th>';
        $body .= '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $statusBadge = ($r['status'] === 'Terminado') 
                ? '<span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700">Terminado</span>'
                : '<span style="background:#e0f2fe;color:#075985;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700">En curso</span>';

            $body .= '<tr>';
            $body .= '<td class="text-center" style="font-size:11px">' . h($r['shift_start']) . '</td>';
            $body .= '<td class="text-center" style="font-size:11px">' . h($r['shift_end']) . '</td>';
            $body .= '<td class="text-center"><strong>' . h($r['shift_code']) . '</strong></td>';
            $body .= '<td class="text-center"><strong>' . h($r['machine_name']) . '</strong></td>';
            $body .= '<td><strong>' . h($r['worker_name']) . '</strong>' . ($r['worker_rut'] !== '' ? '<br><span class="erp-prod-muted" style="font-size:10.5px">' . h($r['worker_rut']) . '</span>' : '') . '</td>';
            $body .= '<td class="text-center">' . ($r['ot_number'] !== '' ? 'OT ' . h($r['ot_number']) : '-') . ($r['cc_number'] !== '' ? '<br><span class="erp-prod-muted" style="font-size:10px">CC ' . h($r['cc_number']) . '</span>' : '') . '</td>';
            $body .= '<td class="text-center" style="font-size:11px">' . h($r['break_start']) . '</td>';
            $body .= '<td class="text-center" style="font-size:11px">' . h($r['break_end']) . '</td>';
            $body .= '<td class="text-right" style="font-weight:700;color:#0f766e">' . h($r['duration_str']) . '</td>';
            $body .= '<td class="text-center">' . $statusBadge . '</td>';
            $body .= '<td style="font-size:11px">' . h($r['comments']) . '</td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div>';
    }
    $body .= '</div></div>';

    render('Informe de Colación', $body);
}

function unibagOutputColacionExcel(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    $start = $filters['start'];
    $end = $filters['end'];
    $activeFilterLabel = (string)$filters['active_filter_label'];

    $plantaId = isset($_GET['planta_id']) && is_numeric($_GET['planta_id']) ? (int)$_GET['planta_id'] : null;
    $equipoTypeId = isset($_GET['equipo_type_id']) && is_numeric($_GET['equipo_type_id']) ? (int)$_GET['equipo_type_id'] : null;
    $equipoId = isset($_GET['equipo_id']) && is_numeric($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    $report = $service->getMachineBreaksReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $plantaId,
        $equipoTypeId,
        $equipoId,
        $search
    );

    $rows = $report['rows'];
    $filename = 'informe-colacion-' . date('Ymd-His') . '.xlsx';
    ob_start();

    echo '<html><head><meta charset="UTF-8"><style>
        body{font-family:Arial,sans-serif;font-size:12px;color:#0f172a}
        table{border-collapse:collapse;width:100%}
        th,td{border:1px solid #cbd5e1;padding:6px 8px}
        th{background:#0f172a;color:#fff;font-weight:700;text-align:center}
        tr:nth-child(even){background:#f8fafc}
    </style></head><body>';
    echo '<h2>Informe de Colación</h2>';
    echo '<p>Filtro: ' . h($activeFilterLabel) . ' · Generado: ' . date('d/m/Y H:i') . '</p>';
    echo '<table>';
    echo '<tr><th>Inicio Turno</th><th>Fin Turno</th><th>Turno</th><th>Máquina</th><th>RUT</th><th>Colaborador</th><th>OT</th><th>CC</th><th>Cliente</th><th>Inicio Colación</th><th>Fin Colación</th><th>Duración Minutos</th><th>Duración</th><th>Estado</th><th>Observaciones</th></tr>';
    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td>' . h($r['shift_start']) . '</td>';
        echo '<td>' . h($r['shift_end']) . '</td>';
        echo '<td>' . h($r['shift_code']) . '</td>';
        echo '<td>' . h($r['machine_name']) . '</td>';
        echo '<td>' . h($r['worker_rut']) . '</td>';
        echo '<td>' . h($r['worker_name']) . '</td>';
        echo '<td>' . h($r['ot_number']) . '</td>';
        echo '<td>' . h($r['cc_number']) . '</td>';
        echo '<td>' . h($r['customer_name']) . '</td>';
        echo '<td>' . h($r['break_start']) . '</td>';
        echo '<td>' . h($r['break_end']) . '</td>';
        echo '<td>' . $r['duration_minutes'] . '</td>';
        echo '<td>' . h($r['duration_str']) . '</td>';
        echo '<td>' . h($r['status']) . '</td>';
        echo '<td>' . h($r['comments']) . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    $html = ob_get_clean();

    SimpleXlsx::streamHtml($filename, $html, 'Colacion');
}

/**
 * Renderiza el Informe de Detenciones.
 * Adaptado y corregido de: libs/modules/stats/detencion/detencion.php
 */
function unibagRenderDetencionReportPage(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    $start = $filters['start'];
    $end = $filters['end'];
    $defaultFilterType = (string)$filters['filter_type'];
    $periodYm = (string)$filters['period'];
    $rangeStartInput = (string)$filters['start_date'];
    $rangeEndInput = (string)$filters['end_date'];
    $activeFilterLabel = (string)$filters['active_filter_label'];

    $plantaId = isset($_GET['planta_id']) && is_numeric($_GET['planta_id']) ? (int)$_GET['planta_id'] : null;
    $equipoTypeId = isset($_GET['equipo_type_id']) && is_numeric($_GET['equipo_type_id']) ? (int)$_GET['equipo_type_id'] : null;
    $equipoId = isset($_GET['equipo_id']) && is_numeric($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : null;
    $pauseId = isset($_GET['pause_id']) && is_numeric($_GET['pause_id']) ? (int)$_GET['pause_id'] : null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    $report = $service->getMachineStopsReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $plantaId,
        $equipoTypeId,
        $equipoId,
        $pauseId,
        $search
    );

    $plantas = $report['plantas'];
    $pauseTypes = $report['pause_types'];
    $rows = $report['rows'];
    $summary = $report['summary'];

    if ($plantaId === null && !empty($plantas)) {
        $plantaId = (int)$plantas[0]['id'];
    }

    $exportParams = http_build_query(array_filter([
        'filter_type' => $defaultFilterType,
        'period' => $periodYm,
        'start_date' => $rangeStartInput,
        'end_date' => $rangeEndInput,
        'planta_id' => $plantaId,
        'equipo_type_id' => $equipoTypeId,
        'equipo_id' => $equipoId,
        'pause_id' => $pauseId,
        'q' => $search,
    ], static fn($v) => $v !== null && $v !== '' && $v !== 0));

    $body = '<style>
        main { max-width: 98% !important; width: 98% !important; margin: 0 auto !important; padding: 16px 24px !important; box-sizing: border-box !important; }
        .report-shell { max-width: 1600px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 18px; }
        
        .erp-filter-card { width: 100%; box-sizing: border-box; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px 24px; box-shadow: 0 4px 16px rgba(15,23,42,.04); display: flex; flex-direction: column; gap: 16px; margin-bottom: 6px; }
        .erp-filter-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; width: 100%; }
        .erp-filter-title { font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; text-transform: uppercase; }
        .erp-filter-sub { font-size: 13px; color: #64748b; font-weight: 500; margin-top: 3px; }
        .erp-filter-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .erp-filter-form { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; width: 100%; padding-top: 16px; border-top: 1px solid #f1f5f9; }
        .erp-filter-field { display: flex; flex-direction: column; gap: 5px; }
        .erp-filter-field.is-hidden { display: none !important; }
        .erp-filter-field label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .05em; }
        .erp-filter-field select, .erp-filter-field input { height: 40px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0 12px; background: #f8fafc; font-size: 13px; font-weight: 600; color: #1e293b; outline: none; transition: all .15s ease; box-sizing: border-box; }
        .erp-filter-field select:focus, .erp-filter-field input:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
        .btn-filter-apply { height: 40px; padding: 0 20px; border-radius: 10px; background: linear-gradient(135deg,#2563eb,#1d4ed8); color: #fff; font-weight: 700; font-size: 13px; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,.25); transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; box-sizing: border-box; }
        .btn-filter-apply:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(37,99,235,.35); color: #fff; }
        .btn-filter-secondary { height: 40px; padding: 0 16px; border-radius: 10px; background: #ffffff; color: #334155; font-weight: 700; font-size: 13px; border: 1px solid #cbd5e1; cursor: pointer; transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; box-sizing: border-box; }
        .btn-filter-secondary:hover { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }

        .erp-prod-table th { text-align: center; white-space: nowrap; font-size: 11px; vertical-align: middle; }
        .erp-prod-table td { vertical-align: middle; }

        @media (max-width: 900px) {
            .erp-filter-header { flex-direction: column; align-items: stretch; }
            .erp-filter-form { flex-direction: column; align-items: stretch; }
            .erp-filter-field select, .erp-filter-field input, .btn-filter-apply, .btn-filter-secondary { width: 100%; }
        }
    </style>';

    $body .= '<div class="report-shell">';

    // Card Filtros Unificada tipo PANEL DE CONTROL Y PRODUCCIÓN ERP
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">🛑 INFORME DE DETENCIONES</div>';
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . ' · ' . count($rows) . ' detenciones registradas</div>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-actions">';
    if (!empty($rows)) {
        $body .= '<a class="btn-filter-secondary" href="/reports/detencion/excel?' . h($exportParams) . '">📥 Exportar XLS (' . count($rows) . ')</a>';
    }
    $body .= '<a class="btn-filter-secondary" href="/">← Volver al Dashboard</a>';
    $body .= '</div>';
    $body .= '</div>';

    // Formulario de Filtros
    $body .= '<form id="detencion-filter-form" method="get" action="/reports/detencion" class="erp-filter-form">';
    $body .= '<div class="erp-filter-field">';
    $body .= '<label for="filter_type_det">Tipo de filtro</label>';
    $body .= '<select id="filter_type_det" name="filter_type">';
    $body .= '<option value="period"' . ($defaultFilterType === 'period' ? ' selected' : '') . '>Período (26 al 25)</option>';
    $body .= '<option value="range"' . ($defaultFilterType === 'range' ? ' selected' : '') . '>Rango personalizado</option>';
    $body .= '</select>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'period' ? '' : ' is-hidden') . '" data-filter-group="period">';
    $body .= '<label for="period_det">Mes del período</label>';
    $body .= '<input id="period_det" type="month" name="period" value="' . h($periodYm) . '"' . ($defaultFilterType === 'period' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="start_date_det">Fecha inicio</label>';
    $body .= '<input id="start_date_det" type="date" name="start_date" value="' . h($rangeStartInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="end_date_det">Fecha término</label>';
    $body .= '<input id="end_date_det" type="date" name="end_date" value="' . h($rangeEndInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field" style="min-width:140px">';
    $body .= '<label for="planta_det">Planta</label>';
    $body .= '<select id="planta_det" name="planta_id">';
    foreach ($plantas as $planta) {
        $pid = (int)$planta['id'];
        $pname = trim((string)($planta['planta_name'] ?? $pid));
        $body .= '<option value="' . $pid . '"' . ($plantaId === $pid ? ' selected' : '') . '>' . h((string)$pname) . '</option>';
    }
    $body .= '</select></div>';

    $body .= '<div class="erp-filter-field" style="min-width:170px">';
    $body .= '<label for="pause_det">Motivo Parada</label>';
    $body .= '<select id="pause_det" name="pause_id">';
    $body .= '<option value="">Todos los motivos</option>';
    foreach ($pauseTypes as $pt) {
        $ptid = (int)$pt['id'];
        $ptname = trim((string)$pt['pause_name']);
        $body .= '<option value="' . $ptid . '"' . ($pauseId === $ptid ? ' selected' : '') . '>' . h($ptname) . '</option>';
    }
    $body .= '</select></div>';

    $body .= '<div class="erp-filter-field" style="flex:1;min-width:220px">';
    $body .= '<label for="q_det">Buscar Operario / Máquina / Comentario</label>';
    $body .= '<input id="q_det" type="text" name="q" placeholder="Ej: Selladora, falta material, 3809..." value="' . h((string)$search) . '">';
    $body .= '</div>';

    $body .= '<div style="display:flex;gap:8px;align-items:flex-end">';
    $body .= '<button class="btn-filter-apply" type="submit">🔍 Aplicar Filtros</button>';
    if ($search !== null && $search !== '') {
        $body .= '<a class="btn-filter-secondary" href="/reports/detencion" title="Limpiar">✕ Limpiar</a>';
    }
    $body .= '</div>';

    $body .= '</form>';
    $body .= '</div>';

    $body .= '<script>
        (function () {
            var filterType = document.getElementById("filter_type_det");
            var form = document.getElementById("detencion-filter-form");
            function syncFilterFields() {
                if (!filterType || !form) return;
                var mode = filterType.value === "range" ? "range" : "period";
                form.querySelectorAll("[data-filter-group]").forEach(function (field) {
                    var visible = field.getAttribute("data-filter-group") === mode;
                    field.classList.toggle("is-hidden", !visible);
                    field.querySelectorAll("input, select").forEach(function (input) {
                        if (input === filterType) return;
                        input.disabled = !visible;
                    });
                });
            }
            if (filterType && form) {
                filterType.addEventListener("change", syncFilterFields);
                syncFilterFields();
            }
        })();
    </script>';

    // KPIs Card
    $body .= '<div class="card" style="border-radius:18px;border:1px solid #e2e8f0;padding:20px 24px;box-shadow:0 4px 16px rgba(15,23,42,.03)">';
    $body .= '<div class="kpi-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">';
    $body .= '<div class="kpi-card"><div class="kpi-label">Total Detenciones</div><div class="kpi-value">' . number_format($summary['total_stops'], 0, ',', '.') . '</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Horas Detenidas</div><div class="kpi-value" style="color:#b91c1c">' . number_format($summary['total_hours'], 1, ',', '.') . ' h</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Minutos Totales</div><div class="kpi-value">' . number_format($summary['total_minutes'], 0, ',', '.') . ' min</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Promedio por Parada</div><div class="kpi-value">' . number_format($summary['avg_minutes'], 1, ',', '.') . ' min</div></div>';
    $body .= '</div></div>';

    // Tabla
    $body .= '<div class="card">';
    if (empty($rows)) {
        $body .= '<div class="erp-prod-empty text-center">No se encontraron detenciones para los filtros seleccionados.</div>';
    } else {
        $body .= '<div class="erp-prod-table-wrap"><table class="erp-prod-table table-compact">';
        $body .= '<thead><tr>';
        $body .= '<th>Fecha</th><th>Hora Inicio</th><th>Hora Fin</th><th class="text-right">Duración</th><th>Motivo Detención</th><th>Código</th><th>Clasificación</th><th>Máquina</th><th>Proceso</th><th>Operario</th><th>OT / CC</th><th>Observaciones</th>';
        $body .= '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $body .= '<tr>';
            $body .= '<td class="text-center">' . h($r['date']) . '</td>';
            $body .= '<td class="text-center" style="font-size:11px">' . h($r['start_time']) . '</td>';
            $body .= '<td class="text-center" style="font-size:11px">' . h($r['end_time']) . '</td>';
            $body .= '<td class="text-right" style="font-weight:700;color:#b91c1c">' . h($r['duration_str']) . '</td>';
            $body .= '<td><strong style="color:#991b1b">' . h($r['stop_reason']) . '</strong></td>';
            $body .= '<td class="text-center">' . ($r['pause_code'] !== '' ? '<span class="badge" style="background:#fee2e2;color:#991b1b;font-size:11px;padding:2px 6px;border-radius:4px">' . h($r['pause_code']) . '</span>' : '-') . '</td>';
            $body .= '<td>' . h($r['classification']) . '</td>';
            $body .= '<td class="text-center"><strong>' . h($r['machine_name']) . '</strong></td>';
            $body .= '<td class="text-center">' . h($r['process_name']) . '</td>';
            $body .= '<td>' . h($r['worker_name']) . ($r['worker_rut'] !== '' ? '<br><span class="erp-prod-muted" style="font-size:10.5px">' . h($r['worker_rut']) . '</span>' : '') . '</td>';
            $body .= '<td class="text-center">' . ($r['ot_number'] !== '' ? 'OT ' . h($r['ot_number']) : '-') . ($r['cc_number'] !== '' ? '<br><span class="erp-prod-muted" style="font-size:10px">CC ' . h($r['cc_number']) . '</span>' : '') . '</td>';
            $body .= '<td style="font-size:11px">' . h($r['comments']) . '</td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div>';
    }
    $body .= '</div></div>';

    render('Informe de Detenciones', $body);
}

function unibagOutputDetencionExcel(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    $start = $filters['start'];
    $end = $filters['end'];
    $activeFilterLabel = (string)$filters['active_filter_label'];

    $plantaId = isset($_GET['planta_id']) && is_numeric($_GET['planta_id']) ? (int)$_GET['planta_id'] : null;
    $equipoTypeId = isset($_GET['equipo_type_id']) && is_numeric($_GET['equipo_type_id']) ? (int)$_GET['equipo_type_id'] : null;
    $equipoId = isset($_GET['equipo_id']) && is_numeric($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : null;
    $pauseId = isset($_GET['pause_id']) && is_numeric($_GET['pause_id']) ? (int)$_GET['pause_id'] : null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    $report = $service->getMachineStopsReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $plantaId,
        $equipoTypeId,
        $equipoId,
        $pauseId,
        $search
    );

    $rows = $report['rows'];
    $filename = 'informe-detenciones-' . date('Ymd-His') . '.xlsx';
    ob_start();

    echo '<html><head><meta charset="UTF-8"><style>
        body{font-family:Arial,sans-serif;font-size:12px;color:#0f172a}
        table{border-collapse:collapse;width:100%}
        th,td{border:1px solid #cbd5e1;padding:6px 8px}
        th{background:#0f172a;color:#fff;font-weight:700;text-align:center}
        tr:nth-child(even){background:#f8fafc}
    </style></head><body>';
    echo '<h2>Informe de Detenciones</h2>';
    echo '<p>Filtro: ' . h($activeFilterLabel) . ' · Generado: ' . date('d/m/Y H:i') . '</p>';
    echo '<table>';
    echo '<tr><th>Fecha</th><th>Hora Inicio</th><th>Hora Fin</th><th>Duración Minutos</th><th>Duración</th><th>Motivo Detención</th><th>Código Parada</th><th>Clasificación</th><th>Máquina</th><th>Proceso</th><th>Operario</th><th>RUT</th><th>OT</th><th>CC</th><th>Cliente</th><th>Observaciones</th></tr>';
    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td>' . h($r['date']) . '</td>';
        echo '<td>' . h($r['start_time']) . '</td>';
        echo '<td>' . h($r['end_time']) . '</td>';
        echo '<td>' . $r['duration_minutes'] . '</td>';
        echo '<td>' . h($r['duration_str']) . '</td>';
        echo '<td>' . h($r['stop_reason']) . '</td>';
        echo '<td>' . h($r['pause_code']) . '</td>';
        echo '<td>' . h($r['classification']) . '</td>';
        echo '<td>' . h($r['machine_name']) . '</td>';
        echo '<td>' . h($r['process_name']) . '</td>';
        echo '<td>' . h($r['worker_name']) . '</td>';
        echo '<td>' . h($r['worker_rut']) . '</td>';
        echo '<td>' . h($r['ot_number']) . '</td>';
        echo '<td>' . h($r['cc_number']) . '</td>';
        echo '<td>' . h($r['customer_name']) . '</td>';
        echo '<td>' . h($r['comments']) . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    $html = ob_get_clean();

    SimpleXlsx::streamHtml($filename, $html, 'Detenciones');
}

/**
 * Renderiza el Informe de Cambio de Configuración.
 * Adaptado de: libs/modules/stats/prod/cambioconfiguracion.php
 */
function unibagRenderCambioConfiguracionReportPage(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    $start = $filters['start'];
    $end = $filters['end'];
    $defaultFilterType = (string)$filters['filter_type'];
    $periodYm = (string)$filters['period'];
    $rangeStartInput = (string)$filters['start_date'];
    $rangeEndInput = (string)$filters['end_date'];
    $activeFilterLabel = (string)$filters['active_filter_label'];

    $plantaId = isset($_GET['planta_id']) && is_numeric($_GET['planta_id']) ? (int)$_GET['planta_id'] : null;
    $equipoTypeId = isset($_GET['equipo_type_id']) && is_numeric($_GET['equipo_type_id']) ? (int)$_GET['equipo_type_id'] : null;
    $equipoId = isset($_GET['equipo_id']) && is_numeric($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    $report = $service->getMachineSetupReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $plantaId,
        $equipoTypeId,
        $equipoId,
        $search
    );

    $plantas = $report['plantas'];
    $rows = $report['rows'];
    $summary = $report['summary'];

    if ($plantaId === null && !empty($plantas)) {
        $plantaId = (int)$plantas[0]['id'];
    }

    $exportParams = http_build_query(array_filter([
        'filter_type' => $defaultFilterType,
        'period' => $periodYm,
        'start_date' => $rangeStartInput,
        'end_date' => $rangeEndInput,
        'planta_id' => $plantaId,
        'equipo_type_id' => $equipoTypeId,
        'equipo_id' => $equipoId,
        'q' => $search,
    ], static fn($v) => $v !== null && $v !== '' && $v !== 0));

    $body = '<style>
        main { max-width: 98% !important; width: 98% !important; margin: 0 auto !important; padding: 16px 24px !important; box-sizing: border-box !important; }
        .report-shell { max-width: 1600px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 18px; }
        
        .erp-filter-card { width: 100%; box-sizing: border-box; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px 24px; box-shadow: 0 4px 16px rgba(15,23,42,.04); display: flex; flex-direction: column; gap: 16px; margin-bottom: 6px; }
        .erp-filter-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; width: 100%; }
        .erp-filter-title { font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; text-transform: uppercase; }
        .erp-filter-sub { font-size: 13px; color: #64748b; font-weight: 500; margin-top: 3px; }
        .erp-filter-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .erp-filter-form { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; width: 100%; padding-top: 16px; border-top: 1px solid #f1f5f9; }
        .erp-filter-field { display: flex; flex-direction: column; gap: 5px; }
        .erp-filter-field.is-hidden { display: none !important; }
        .erp-filter-field label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .05em; }
        .erp-filter-field select, .erp-filter-field input { height: 40px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0 12px; background: #f8fafc; font-size: 13px; font-weight: 600; color: #1e293b; outline: none; transition: all .15s ease; box-sizing: border-box; }
        .erp-filter-field select:focus, .erp-filter-field input:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
        .btn-filter-apply { height: 40px; padding: 0 20px; border-radius: 10px; background: linear-gradient(135deg,#2563eb,#1d4ed8); color: #fff; font-weight: 700; font-size: 13px; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,.25); transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; box-sizing: border-box; }
        .btn-filter-apply:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(37,99,235,.35); color: #fff; }
        .btn-filter-secondary { height: 40px; padding: 0 16px; border-radius: 10px; background: #ffffff; color: #334155; font-weight: 700; font-size: 13px; border: 1px solid #cbd5e1; cursor: pointer; transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; box-sizing: border-box; }
        .btn-filter-secondary:hover { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }

        .erp-prod-table th { text-align: center; white-space: nowrap; font-size: 11px; vertical-align: middle; }
        .erp-prod-table td { vertical-align: middle; }

        @media (max-width: 900px) {
            .erp-filter-header { flex-direction: column; align-items: stretch; }
            .erp-filter-form { flex-direction: column; align-items: stretch; }
            .erp-filter-field select, .erp-filter-field input, .btn-filter-apply, .btn-filter-secondary { width: 100%; }
        }
    </style>';

    $body .= '<div class="report-shell">';

    // Card Filtros Unificada tipo PANEL DE CONTROL Y PRODUCCIÓN ERP
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">⚙️ INFORME DE CAMBIO DE CONFIGURACIÓN (SETUP)</div>';
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . ' · ' . count($rows) . ' cambios registrados</div>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-actions">';
    if (!empty($rows)) {
        $body .= '<a class="btn-filter-secondary" href="/reports/cambio-configuracion/excel?' . h($exportParams) . '">📥 Exportar XLS (' . count($rows) . ')</a>';
    }
    $body .= '<a class="btn-filter-secondary" href="/">← Volver al Dashboard</a>';
    $body .= '</div>';
    $body .= '</div>';

    // Formulario de Filtros
    $body .= '<form id="setup-filter-form" method="get" action="/reports/cambio-configuracion" class="erp-filter-form">';
    $body .= '<div class="erp-filter-field">';
    $body .= '<label for="filter_type_setup">Tipo de filtro</label>';
    $body .= '<select id="filter_type_setup" name="filter_type">';
    $body .= '<option value="period"' . ($defaultFilterType === 'period' ? ' selected' : '') . '>Período (26 al 25)</option>';
    $body .= '<option value="range"' . ($defaultFilterType === 'range' ? ' selected' : '') . '>Rango personalizado</option>';
    $body .= '</select>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'period' ? '' : ' is-hidden') . '" data-filter-group="period">';
    $body .= '<label for="period_setup">Mes del período</label>';
    $body .= '<input id="period_setup" type="month" name="period" value="' . h($periodYm) . '"' . ($defaultFilterType === 'period' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="start_date_setup">Fecha inicio</label>';
    $body .= '<input id="start_date_setup" type="date" name="start_date" value="' . h($rangeStartInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="end_date_setup">Fecha término</label>';
    $body .= '<input id="end_date_setup" type="date" name="end_date" value="' . h($rangeEndInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field" style="min-width:140px">';
    $body .= '<label for="planta_setup">Planta</label>';
    $body .= '<select id="planta_setup" name="planta_id">';
    foreach ($plantas as $planta) {
        $pid = (int)$planta['id'];
        $pname = trim((string)($planta['planta_name'] ?? $pid));
        $body .= '<option value="' . $pid . '"' . ($plantaId === $pid ? ' selected' : '') . '>' . h((string)$pname) . '</option>';
    }
    $body .= '</select></div>';

    $body .= '<div class="erp-filter-field" style="flex:1;min-width:220px">';
    $body .= '<label for="q_setup">Buscar OT / CC / Máquina / Operario</label>';
    $body .= '<input id="q_setup" type="text" name="q" placeholder="Ej: 3607, Selladora, Hugo..." value="' . h((string)$search) . '">';
    $body .= '</div>';

    $body .= '<div style="display:flex;gap:8px;align-items:flex-end">';
    $body .= '<button class="btn-filter-apply" type="submit">🔍 Aplicar Filtros</button>';
    if ($search !== null && $search !== '') {
        $body .= '<a class="btn-filter-secondary" href="/reports/cambio-configuracion" title="Limpiar">✕ Limpiar</a>';
    }
    $body .= '</div>';

    $body .= '</form>';
    $body .= '</div>';

    $body .= '<script>
        (function () {
            var filterType = document.getElementById("filter_type_setup");
            var form = document.getElementById("setup-filter-form");
            function syncFilterFields() {
                if (!filterType || !form) return;
                var mode = filterType.value === "range" ? "range" : "period";
                form.querySelectorAll("[data-filter-group]").forEach(function (field) {
                    var visible = field.getAttribute("data-filter-group") === mode;
                    field.classList.toggle("is-hidden", !visible);
                    field.querySelectorAll("input, select").forEach(function (input) {
                        if (input === filterType) return;
                        input.disabled = !visible;
                    });
                });
            }
            if (filterType && form) {
                filterType.addEventListener("change", syncFilterFields);
                syncFilterFields();
            }
        })();
    </script>';

    // KPIs Card
    $body .= '<div class="card" style="border-radius:18px;border:1px solid #e2e8f0;padding:20px 24px;box-shadow:0 4px 16px rgba(15,23,42,.03)">';
    $body .= '<div class="kpi-grid" style="grid-template-columns:repeat(4,minmax(0,1fr))">';
    $body .= '<div class="kpi-card"><div class="kpi-label">Total Configuraciones</div><div class="kpi-value">' . number_format($summary['total_setups'], 0, ',', '.') . '</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Horas Totales Setup</div><div class="kpi-value" style="color:#0284c7">' . number_format($summary['total_hours'], 1, ',', '.') . ' h</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Minutos Totales</div><div class="kpi-value">' . number_format($summary['total_minutes'], 0, ',', '.') . ' min</div></div>';
    $body .= '<div class="kpi-card"><div class="kpi-label">Promedio Duración</div><div class="kpi-value">' . number_format($summary['avg_minutes'], 1, ',', '.') . ' min</div></div>';
    $body .= '</div></div>';

    // Tabla
    $body .= '<div class="card">';
    if (empty($rows)) {
        $body .= '<div class="erp-prod-empty text-center">No se encontraron cambios de configuración para los filtros seleccionados.</div>';
    } else {
        $body .= '<div class="erp-prod-table-wrap"><table class="erp-prod-table table-compact">';
        $body .= '<thead><tr>';
        $body .= '<th>OT</th><th>CC</th><th>Cliente</th><th>Máquina</th><th>Proceso</th><th>Operario</th><th>Medida Desde</th><th>Medida Hasta</th><th class="text-center">Aplica</th><th>Inicio Setup</th><th>Fin Setup</th><th class="text-right">Duración</th><th class="text-center">Estado</th><th>Observaciones</th>';
        $body .= '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $body .= '<tr>';
            $body .= '<td class="text-center erp-prod-code">' . h($r['ot_number']) . '</td>';
            $body .= '<td class="text-center">' . h($r['cc_number']) . '</td>';
            $body .= '<td>' . h($r['customer_name']) . '</td>';
            $body .= '<td class="text-center"><strong>' . h($r['machine_name']) . '</strong></td>';
            $body .= '<td class="text-center">' . h($r['process_name']) . '</td>';
            $body .= '<td>' . h($r['worker_name']) . ($r['worker_rut'] !== '' ? '<br><span class="erp-prod-muted" style="font-size:10.5px">' . h($r['worker_rut']) . '</span>' : '') . '</td>';
            $body .= '<td class="text-center" style="font-size:11.5px">' . h($r['format_from']) . '</td>';
            $body .= '<td class="text-center" style="font-size:11.5px;font-weight:700;color:#0f766e">' . h($r['format_to']) . '</td>';
            $body .= '<td class="text-center"><strong>' . h($r['aplica'] ?? 'NO') . '</strong></td>';
            $body .= '<td class="text-center" style="font-size:11px">' . h($r['setup_start']) . '</td>';
            $body .= '<td class="text-center" style="font-size:11px">' . h($r['setup_end']) . '</td>';
            $body .= '<td class="text-right" style="font-weight:700;color:#0f766e">' . h($r['duration_str']) . '</td>';
            $body .= '<td class="text-center"><span class="badge" style="background:#e0f2fe;color:#0369a1;padding:2px 8px;border-radius:4px;font-size:11px">' . h($r['status']) . '</span></td>';
            $body .= '<td style="font-size:11px">' . h($r['comments']) . '</td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div>';
    }
    $body .= '</div></div>';

    render('Informe de Cambio de Configuración', $body);
}

function unibagOutputCambioConfiguracionExcel(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    $start = $filters['start'];
    $end = $filters['end'];
    $activeFilterLabel = (string)$filters['active_filter_label'];

    $plantaId = isset($_GET['planta_id']) && is_numeric($_GET['planta_id']) ? (int)$_GET['planta_id'] : null;
    $equipoTypeId = isset($_GET['equipo_type_id']) && is_numeric($_GET['equipo_type_id']) ? (int)$_GET['equipo_type_id'] : null;
    $equipoId = isset($_GET['equipo_id']) && is_numeric($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    $report = $service->getMachineSetupReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $plantaId,
        $equipoTypeId,
        $equipoId,
        $search
    );

    $rows = $report['rows'];
    $filename = 'informe-cambio-configuracion-' . date('Ymd-His') . '.xlsx';
    ob_start();

    echo '<html><head><meta charset="UTF-8"><style>
        body{font-family:Arial,sans-serif;font-size:12px;color:#0f172a}
        table{border-collapse:collapse;width:100%}
        th,td{border:1px solid #cbd5e1;padding:6px 8px}
        th{background:#0f172a;color:#fff;font-weight:700;text-align:center}
        tr:nth-child(even){background:#f8fafc}
    </style></head><body>';
    echo '<h2>Informe de Cambio de Configuración</h2>';
    echo '<p>Filtro: ' . h($activeFilterLabel) . ' · Generado: ' . date('d/m/Y H:i') . '</p>';
    echo '<table>';
    echo '<tr><th>OT</th><th>CC</th><th>Cliente</th><th>Máquina</th><th>Proceso</th><th>Operario</th><th>RUT</th><th>Medida Desde</th><th>Medida Hasta</th><th>Aplica</th><th>Inicio Setup</th><th>Fin Setup</th><th>Duración Minutos</th><th>Duración</th><th>Estado</th><th>Observaciones</th></tr>';
    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td>' . h($r['ot_number']) . '</td>';
        echo '<td>' . h($r['cc_number']) . '</td>';
        echo '<td>' . h($r['customer_name']) . '</td>';
        echo '<td>' . h($r['machine_name']) . '</td>';
        echo '<td>' . h($r['process_name']) . '</td>';
        echo '<td>' . h($r['worker_name']) . '</td>';
        echo '<td>' . h($r['worker_rut']) . '</td>';
        echo '<td>' . h($r['format_from']) . '</td>';
        echo '<td>' . h($r['format_to']) . '</td>';
        echo '<td>' . h($r['aplica'] ?? 'NO') . '</td>';
        echo '<td>' . h($r['setup_start']) . '</td>';
        echo '<td>' . h($r['setup_end']) . '</td>';
        echo '<td>' . $r['duration_minutes'] . '</td>';
        echo '<td>' . h($r['duration_str']) . '</td>';
        echo '<td>' . h($r['status']) . '</td>';
        echo '<td>' . h($r['comments']) . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    $html = ob_get_clean();

    SimpleXlsx::streamHtml($filename, $html, 'Cambios Config');
}

/**
 * Renderiza la vista unificada de Eventos de Máquina:
 * Detenciones, Colaciones y Cambios de Configuración (Setup) en pestañas integradas con badges de conteo.
 */
function unibagRenderMachineEventsReportPage(ReceptionService $service): void
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

    $activeTab = strtolower(trim((string)($_GET['tab'] ?? 'detencion')));
    if (!in_array($activeTab, ['detencion', 'colacion', 'setup'], true)) {
        if ($activeTab === 'cambio-configuracion') {
            $activeTab = 'setup';
        } else {
            $activeTab = 'detencion';
        }
    }

    $plantaId = isset($_GET['planta_id']) && is_numeric($_GET['planta_id']) ? (int)$_GET['planta_id'] : null;
    $equipoTypeId = isset($_GET['equipo_type_id']) && is_numeric($_GET['equipo_type_id']) ? (int)$_GET['equipo_type_id'] : null;
    $equipoId = isset($_GET['equipo_id']) && is_numeric($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : null;
    $pauseId = isset($_GET['pause_id']) && is_numeric($_GET['pause_id']) ? (int)$_GET['pause_id'] : null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    $startStr = $start->format('Y-m-d H:i:s');
    $endStr = $end->format('Y-m-d H:i:s');

    // Cargar reporte según pestaña activa y obtener conteos para las pestañas
    if ($activeTab === 'detencion') {
        $detencionReport = $service->getMachineStopsReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, $pauseId, $search);
        $colacionReport = $service->getMachineBreaksReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, $search);
        $setupReport = $service->getMachineSetupReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, $search);
    } elseif ($activeTab === 'colacion') {
        $colacionReport = $service->getMachineBreaksReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, $search);
        $detencionReport = $service->getMachineStopsReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, null, $search);
        $setupReport = $service->getMachineSetupReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, $search);
    } else { // setup
        $setupReport = $service->getMachineSetupReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, $search);
        $detencionReport = $service->getMachineStopsReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, null, $search);
        $colacionReport = $service->getMachineBreaksReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, $search);
    }

    $detencionCount = (int)($detencionReport['summary']['total_stops'] ?? count($detencionReport['rows'] ?? []));
    $colacionCount = (int)($colacionReport['summary']['total_records'] ?? count($colacionReport['rows'] ?? []));
    $setupCount = (int)($setupReport['summary']['total_setups'] ?? count($setupReport['rows'] ?? []));

    $currentReport = match($activeTab) {
        'colacion' => $colacionReport,
        'setup' => $setupReport,
        default => $detencionReport,
    };
    $plantas = $currentReport['plantas'] ?? [];
    $pauseTypes = $detencionReport['pause_types'] ?? [];

    if ($plantaId === null && !empty($plantas)) {
        $plantaId = (int)$plantas[0]['id'];
    }

    // Helper URLs
    $buildUrl = static function (array $overrides) use ($defaultFilterType, $periodYm, $rangeStartInput, $rangeEndInput, $plantaId, $equipoTypeId, $equipoId, $pauseId, $search, $activeTab): string {
        $params = [
            'tab' => $activeTab,
            'filter_type' => $defaultFilterType,
            'period' => $periodYm,
            'start_date' => $rangeStartInput,
            'end_date' => $rangeEndInput,
            'planta_id' => $plantaId,
            'equipo_type_id' => $equipoTypeId,
            'equipo_id' => $equipoId,
            'pause_id' => $pauseId,
            'q' => $search,
        ];
        foreach ($overrides as $k => $v) {
            if ($v === null || $v === '') {
                unset($params[$k]);
            } else {
                $params[$k] = $v;
            }
        }
        return '/reports/machine-events?' . http_build_query($params);
    };

    $exportParams = http_build_query(array_filter([
        'tab' => $activeTab,
        'filter_type' => $defaultFilterType,
        'period' => $periodYm,
        'start_date' => $rangeStartInput,
        'end_date' => $rangeEndInput,
        'planta_id' => $plantaId,
        'equipo_type_id' => $equipoTypeId,
        'equipo_id' => $equipoId,
        'pause_id' => $pauseId,
        'q' => $search,
    ], static fn($v) => $v !== null && $v !== ''));

    $body = '<style>
        main { max-width: 98% !important; width: 98% !important; margin: 0 auto !important; padding: 16px 24px !important; box-sizing: border-box !important; }
        .report-shell { max-width: 1600px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 18px; }
        
        .erp-filter-card { width: 100%; box-sizing: border-box; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px 24px; box-shadow: 0 4px 16px rgba(15,23,42,.04); display: flex; flex-direction: column; gap: 16px; margin-bottom: 6px; }
        .erp-filter-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; width: 100%; }
        .erp-filter-title { font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; text-transform: uppercase; }
        .erp-filter-sub { font-size: 13px; color: #64748b; font-weight: 500; margin-top: 3px; }
        .erp-filter-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .erp-filter-form { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; width: 100%; padding-top: 16px; border-top: 1px solid #f1f5f9; }
        .erp-filter-field { display: flex; flex-direction: column; gap: 5px; }
        .erp-filter-field.is-hidden { display: none !important; }
        .erp-filter-field label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .05em; }
        .erp-filter-field select, .erp-filter-field input { height: 40px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0 12px; background: #f8fafc; font-size: 13px; font-weight: 600; color: #1e293b; outline: none; transition: all .15s ease; box-sizing: border-box; }
        .erp-filter-field select:focus, .erp-filter-field input:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
        .btn-filter-apply { height: 40px; padding: 0 20px; border-radius: 10px; background: linear-gradient(135deg,#2563eb,#1d4ed8); color: #fff; font-weight: 700; font-size: 13px; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,.25); transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; box-sizing: border-box; }
        .btn-filter-apply:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(37,99,235,.35); color: #fff; }
        .btn-filter-secondary { height: 40px; padding: 0 16px; border-radius: 10px; background: #ffffff; color: #334155; font-weight: 700; font-size: 13px; border: 1px solid #cbd5e1; cursor: pointer; transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; box-sizing: border-box; }
        .btn-filter-secondary:hover { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }

        .report-tabs { display: flex; justify-content: flex-start; align-items: center; gap: 8px; flex-wrap: wrap; }
        .report-tab-btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 700; color: #334155; text-decoration: none; transition: all .15s ease; box-shadow: 0 1px 2px rgba(0,0,0,.03); }
        .report-tab-btn:hover { background: #f8fafc; border-color: #94a3b8; }
        .report-tab-btn.active { background: #00A9A6; color: #fff; border-color: #00A9A6; box-shadow: 0 4px 12px rgba(0,169,166,.28); }
        .report-tab-badge { background: rgba(0,0,0,.08); padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; }
        .report-tab-btn.active .report-tab-badge { background: rgba(255,255,255,.25); color: #fff; }

        .erp-prod-table th { text-align: center; white-space: nowrap; font-size: 11px; vertical-align: middle; }
        .erp-prod-table td { vertical-align: middle; }

        @media (max-width: 900px) {
            .erp-filter-header { flex-direction: column; align-items: stretch; }
            .erp-filter-form { flex-direction: column; align-items: stretch; }
            .erp-filter-field select, .erp-filter-field input, .btn-filter-apply, .btn-filter-secondary { width: 100%; }
        }
    </style>';

    $body .= '<div class="report-shell">';

    // Card Filtros Unificada tipo PANEL DE CONTROL Y PRODUCCIÓN ERP
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">⚡ INFORME DE EVENTOS DE MÁQUINA</div>';
    
    $tabTitles = [
        'detencion' => 'Detenciones registradas',
        'colacion' => 'Colaciones registradas',
        'setup' => 'Cambios de configuración (Setup)',
    ];
    $activeTabCount = match($activeTab) {
        'colacion' => $colacionCount,
        'setup' => $setupCount,
        default => $detencionCount,
    };
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . ' · ' . number_format($activeTabCount, 0, ',', '.') . ' ' . ($tabTitles[$activeTab] ?? 'eventos') . '</div>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-actions">';
    $body .= '<a class="btn-filter-secondary" href="/reports/machine-events/excel?' . h($exportParams) . '">📥 Exportar XLS (' . number_format($activeTabCount, 0, ',', '.') . ')</a>';
    $body .= '<a class="btn-filter-secondary" href="/">← Volver al Dashboard</a>';
    $body .= '</div>';
    $body .= '</div>';

    // Tabs de navegación
    $body .= '<div class="report-tabs" style="margin:2px 0 0 0">';
    $tabsDef = [
        'detencion' => ['label' => 'Detención', 'count' => $detencionCount],
        'colacion' => ['label' => 'Colación', 'count' => $colacionCount],
        'setup' => ['label' => 'Cambio de Configuración', 'count' => $setupCount],
    ];
    foreach ($tabsDef as $tKey => $tData) {
        $isActive = ($activeTab === $tKey);
        $tabUrl = $buildUrl(['tab' => $tKey]);
        $body .= '<a class="report-tab-btn' . ($isActive ? ' active' : '') . '" href="' . h($tabUrl) . '">';
        $body .= '<span>' . h($tData['label']) . '</span>';
        $body .= '<span class="report-tab-badge">' . number_format($tData['count'], 0, ',', '.') . '</span>';
        $body .= '</a>';
    }
    $body .= '</div>';

    // Formulario de Filtros
    $body .= '<form id="events-filter-form" method="get" action="/reports/machine-events" class="erp-filter-form">';
    $body .= '<input type="hidden" name="tab" value="' . h($activeTab) . '">';
    
    $body .= '<div class="erp-filter-field">';
    $body .= '<label for="filter_type_evt">Tipo de filtro</label>';
    $body .= '<select id="filter_type_evt" name="filter_type">';
    $body .= '<option value="period"' . ($defaultFilterType === 'period' ? ' selected' : '') . '>Período (26 al 25)</option>';
    $body .= '<option value="range"' . ($defaultFilterType === 'range' ? ' selected' : '') . '>Rango personalizado</option>';
    $body .= '</select>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'period' ? '' : ' is-hidden') . '" data-filter-group="period">';
    $body .= '<label for="period_evt">Mes del período</label>';
    $body .= '<input id="period_evt" type="month" name="period" value="' . h($periodYm) . '"' . ($defaultFilterType === 'period' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="start_date_evt">Fecha inicio</label>';
    $body .= '<input id="start_date_evt" type="date" name="start_date" value="' . h($rangeStartInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="end_date_evt">Fecha término</label>';
    $body .= '<input id="end_date_evt" type="date" name="end_date" value="' . h($rangeEndInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field" style="min-width:140px">';
    $body .= '<label for="planta_evt">Planta</label>';
    $body .= '<select id="planta_evt" name="planta_id">';
    foreach ($plantas as $planta) {
        $pid = (int)$planta['id'];
        $pname = trim((string)($planta['planta_name'] ?? $pid));
        $body .= '<option value="' . $pid . '"' . ($plantaId === $pid ? ' selected' : '') . '>' . h((string)$pname) . '</option>';
    }
    $body .= '</select></div>';

    if ($activeTab === 'detencion') {
        $body .= '<div class="erp-filter-field" style="min-width:170px">';
        $body .= '<label for="pause_evt">Motivo Parada</label>';
        $body .= '<select id="pause_evt" name="pause_id">';
        $body .= '<option value="">Todos los motivos</option>';
        foreach ($pauseTypes as $pt) {
            $ptid = (int)$pt['id'];
            $ptname = trim((string)$pt['pause_name']);
            $body .= '<option value="' . $ptid . '"' . ($pauseId === $ptid ? ' selected' : '') . '>' . h($ptname) . '</option>';
        }
        $body .= '</select></div>';
    }

    $body .= '<div class="erp-filter-field" style="flex:1;min-width:220px">';
    $body .= '<label for="q_evt">Buscar Operario / Máquina / OT</label>';
    $body .= '<input id="q_evt" type="text" name="q" placeholder="Buscar..." value="' . h((string)$search) . '">';
    $body .= '</div>';

    $body .= '<div style="display:flex;gap:8px;align-items:flex-end">';
    $body .= '<button class="btn-filter-apply" type="submit">🔍 Aplicar Filtros</button>';
    if ($search !== null && $search !== '') {
        $body .= '<a class="btn-filter-secondary" href="/reports/machine-events?tab=' . h($activeTab) . '" title="Limpiar">✕ Limpiar</a>';
    }
    $body .= '</div>';

    $body .= '</form>';
    $body .= '</div>';

    $body .= '<script>
        (function () {
            var filterType = document.getElementById("filter_type_evt");
            var form = document.getElementById("events-filter-form");
            function syncFilterFields() {
                if (!filterType || !form) return;
                var mode = filterType.value === "range" ? "range" : "period";
                form.querySelectorAll("[data-filter-group]").forEach(function (field) {
                    var visible = field.getAttribute("data-filter-group") === mode;
                    field.classList.toggle("is-hidden", !visible);
                    field.querySelectorAll("input, select").forEach(function (input) {
                        if (input === filterType) return;
                        input.disabled = !visible;
                    });
                });
            }
            if (filterType && form) {
                filterType.addEventListener("change", syncFilterFields);
                syncFilterFields();
            }
        })();
    </script>';

    // KPIs según pestaña
    if ($activeTab === 'detencion') {
        $summary = $detencionReport['summary'];
        $body .= '<div class="kpi-grid" style="grid-template-columns:repeat(4,minmax(0,1fr));margin-top:14px">';
        $body .= '<div class="kpi-card"><div class="kpi-label">Total Detenciones</div><div class="kpi-value">' . number_format($summary['total_stops'], 0, ',', '.') . '</div></div>';
        $body .= '<div class="kpi-card"><div class="kpi-label">Horas Detenidas</div><div class="kpi-value" style="color:#b91c1c">' . number_format($summary['total_hours'], 1, ',', '.') . ' h</div></div>';
        $body .= '<div class="kpi-card"><div class="kpi-label">Minutos Totales</div><div class="kpi-value">' . number_format($summary['total_minutes'], 0, ',', '.') . ' min</div></div>';
        $body .= '<div class="kpi-card"><div class="kpi-label">Promedio por Parada</div><div class="kpi-value">' . number_format($summary['avg_minutes'], 1, ',', '.') . ' min</div></div>';
        $body .= '</div>';
    } elseif ($activeTab === 'colacion') {
        $summary = $colacionReport['summary'];
        $body .= '<div class="kpi-grid" style="grid-template-columns:repeat(5,minmax(0,1fr));margin-top:14px">';
        $body .= '<div class="kpi-card"><div class="kpi-label">Total Registros</div><div class="kpi-value">' . number_format($summary['total_records'], 0, ',', '.') . '</div></div>';
        $body .= '<div class="kpi-card"><div class="kpi-label">Terminadas</div><div class="kpi-value" style="color:#16a34a">' . number_format($summary['completed_records'], 0, ',', '.') . '</div></div>';
        $body .= '<div class="kpi-card"><div class="kpi-label">En Curso</div><div class="kpi-value" style="color:#0284c7">' . number_format($summary['in_progress_records'], 0, ',', '.') . '</div></div>';
        $body .= '<div class="kpi-card"><div class="kpi-label">Total Horas</div><div class="kpi-value">' . number_format($summary['total_hours'], 1, ',', '.') . ' h</div></div>';
        $body .= '<div class="kpi-card"><div class="kpi-label">Promedio Duración</div><div class="kpi-value">' . number_format($summary['avg_minutes'], 1, ',', '.') . ' min</div></div>';
        $body .= '</div>';
    } else { // setup
        $summary = $setupReport['summary'];
        $body .= '<div class="kpi-grid" style="grid-template-columns:repeat(4,minmax(0,1fr));margin-top:14px">';
        $body .= '<div class="kpi-card"><div class="kpi-label">Total Configuraciones</div><div class="kpi-value">' . number_format($summary['total_setups'], 0, ',', '.') . '</div></div>';
        $body .= '<div class="kpi-card"><div class="kpi-label">Horas Totales Setup</div><div class="kpi-value" style="color:#0284c7">' . number_format($summary['total_hours'], 1, ',', '.') . ' h</div></div>';
        $body .= '<div class="kpi-card"><div class="kpi-label">Minutos Totales</div><div class="kpi-value">' . number_format($summary['total_minutes'], 0, ',', '.') . ' min</div></div>';
        $body .= '<div class="kpi-card"><div class="kpi-label">Promedio Duración</div><div class="kpi-value">' . number_format($summary['avg_minutes'], 1, ',', '.') . ' min</div></div>';
        $body .= '</div>';
    }

    // Tabla de Datos
    $body .= '<div class="card">';
    if ($activeTab === 'detencion') {
        $rows = $detencionReport['rows'];
        if (empty($rows)) {
            $body .= '<div class="erp-prod-empty text-center">No se encontraron detenciones para los filtros seleccionados.</div>';
        } else {
            $body .= '<div class="erp-prod-table-wrap"><table class="erp-prod-table table-compact">';
            $body .= '<thead><tr>';
            $body .= '<th>Fecha</th><th>Hora Inicio</th><th>Hora Fin</th><th class="text-right">Duración</th><th>Motivo Detención</th><th>Código</th><th>Clasificación</th><th>Máquina</th><th>Proceso</th><th>Operario</th><th>OT / CC</th><th>Observaciones</th>';
            $body .= '</tr></thead><tbody>';
            foreach ($rows as $r) {
                $body .= '<tr>';
                $body .= '<td class="text-center">' . h($r['date']) . '</td>';
                $body .= '<td class="text-center" style="font-size:11px">' . h($r['start_time']) . '</td>';
                $body .= '<td class="text-center" style="font-size:11px">' . h($r['end_time']) . '</td>';
                $body .= '<td class="text-right" style="font-weight:700;color:#b91c1c">' . h($r['duration_str']) . '</td>';
                $body .= '<td><strong style="color:#991b1b">' . h($r['stop_reason']) . '</strong></td>';
                $body .= '<td class="text-center">' . ($r['pause_code'] !== '' ? '<span class="badge" style="background:#fee2e2;color:#991b1b;font-size:11px;padding:2px 6px;border-radius:4px">' . h($r['pause_code']) . '</span>' : '-') . '</td>';
                $body .= '<td>' . h($r['classification']) . '</td>';
                $body .= '<td class="text-center"><strong>' . h($r['machine_name']) . '</strong></td>';
                $body .= '<td class="text-center">' . h($r['process_name']) . '</td>';
                $body .= '<td>' . h($r['worker_name']) . ($r['worker_rut'] !== '' ? '<br><span class="erp-prod-muted" style="font-size:10.5px">' . h($r['worker_rut']) . '</span>' : '') . '</td>';
                $body .= '<td class="text-center">' . ($r['ot_number'] !== '' ? 'OT ' . h($r['ot_number']) : '-') . ($r['cc_number'] !== '' ? '<br><span class="erp-prod-muted" style="font-size:10px">CC ' . h($r['cc_number']) . '</span>' : '') . '</td>';
                $body .= '<td style="font-size:11px">' . h($r['comments']) . '</td>';
                $body .= '</tr>';
            }
            $body .= '</tbody></table></div>';
        }
    } elseif ($activeTab === 'colacion') {
        $rows = $colacionReport['rows'];
        if (empty($rows)) {
            $body .= '<div class="erp-prod-empty text-center">No se encontraron registros de colación para los filtros seleccionados.</div>';
        } else {
            $body .= '<div class="erp-prod-table-wrap"><table class="erp-prod-table table-compact">';
            $body .= '<thead><tr>';
            $body .= '<th>Inicio Turno</th><th>Fin Turno</th><th>Turno</th><th>Máquina</th><th>Colaborador</th><th>OT / CC</th><th>Inicio Colación</th><th>Fin Colación</th><th class="text-right">Duración</th><th class="text-center">Estado</th><th>Observaciones</th>';
            $body .= '</tr></thead><tbody>';
            foreach ($rows as $r) {
                $statusBadge = ($r['status'] === 'Terminado') 
                    ? '<span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700">Terminado</span>'
                    : '<span style="background:#e0f2fe;color:#075985;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700">En curso</span>';

                $body .= '<tr>';
                $body .= '<td class="text-center" style="font-size:11px">' . h($r['shift_start']) . '</td>';
                $body .= '<td class="text-center" style="font-size:11px">' . h($r['shift_end']) . '</td>';
                $body .= '<td class="text-center"><strong>' . h($r['shift_code']) . '</strong></td>';
                $body .= '<td class="text-center"><strong>' . h($r['machine_name']) . '</strong></td>';
                $body .= '<td><strong>' . h($r['worker_name']) . '</strong>' . ($r['worker_rut'] !== '' ? '<br><span class="erp-prod-muted" style="font-size:10.5px">' . h($r['worker_rut']) . '</span>' : '') . '</td>';
                $body .= '<td class="text-center">' . ($r['ot_number'] !== '' ? 'OT ' . h($r['ot_number']) : '-') . ($r['cc_number'] !== '' ? '<br><span class="erp-prod-muted" style="font-size:10px">CC ' . h($r['cc_number']) . '</span>' : '') . '</td>';
                $body .= '<td class="text-center" style="font-size:11px">' . h($r['break_start']) . '</td>';
                $body .= '<td class="text-center" style="font-size:11px">' . h($r['break_end']) . '</td>';
                $body .= '<td class="text-right" style="font-weight:700;color:#0f766e">' . h($r['duration_str']) . '</td>';
                $body .= '<td class="text-center">' . $statusBadge . '</td>';
                $body .= '<td style="font-size:11px">' . h($r['comments']) . '</td>';
                $body .= '</tr>';
            }
            $body .= '</tbody></table></div>';
        }
    } else { // setup
        $rows = $setupReport['rows'];
        if (empty($rows)) {
            $body .= '<div class="erp-prod-empty text-center">No se encontraron cambios de configuración para los filtros seleccionados.</div>';
        } else {
            $body .= '<div class="erp-prod-table-wrap"><table class="erp-prod-table table-compact">';
            $body .= '<thead><tr>';
            $body .= '<th>OT</th><th>CC</th><th>Cliente</th><th>Máquina</th><th>Proceso</th><th>Operario</th><th>Medida Desde</th><th>Medida Hasta</th><th class="text-center">Aplica</th><th>Inicio Setup</th><th>Fin Setup</th><th class="text-right">Duración</th><th class="text-center">Estado</th><th>Observaciones</th>';
            $body .= '</tr></thead><tbody>';
            foreach ($rows as $r) {
                $body .= '<tr>';
                $body .= '<td class="text-center erp-prod-code">' . h($r['ot_number']) . '</td>';
                $body .= '<td class="text-center">' . h($r['cc_number']) . '</td>';
                $body .= '<td>' . h($r['customer_name']) . '</td>';
                $body .= '<td class="text-center"><strong>' . h($r['machine_name']) . '</strong></td>';
                $body .= '<td class="text-center">' . h($r['process_name']) . '</td>';
                $body .= '<td>' . h($r['worker_name']) . ($r['worker_rut'] !== '' ? '<br><span class="erp-prod-muted" style="font-size:10.5px">' . h($r['worker_rut']) . '</span>' : '') . '</td>';
                $body .= '<td class="text-center" style="font-size:11.5px">' . h($r['format_from']) . '</td>';
                $body .= '<td class="text-center" style="font-size:11.5px;font-weight:700;color:#0f766e">' . h($r['format_to']) . '</td>';
                $body .= '<td class="text-center"><strong>' . h($r['aplica'] ?? 'NO') . '</strong></td>';
                $body .= '<td class="text-center" style="font-size:11px">' . h($r['setup_start']) . '</td>';
                $body .= '<td class="text-center" style="font-size:11px">' . h($r['setup_end']) . '</td>';
                $body .= '<td class="text-right" style="font-weight:700;color:#0f766e">' . h($r['duration_str']) . '</td>';
                $body .= '<td class="text-center"><span class="badge" style="background:#e0f2fe;color:#0369a1;padding:2px 8px;border-radius:4px;font-size:11px">' . h($r['status']) . '</span></td>';
                $body .= '<td style="font-size:11px">' . h($r['comments']) . '</td>';
                $body .= '</tr>';
            }
            $body .= '</tbody></table></div>';
        }
    }
    $body .= '</div></div>';

    render('Eventos de Máquina', $body);
}

/**
 * Exporta a Excel según la pestaña activa del informe unificado de Eventos de Máquina.
 */
function unibagOutputMachineEventsExcel(ReceptionService $service): void
{
    $tab = strtolower(trim((string)($_GET['tab'] ?? 'detencion')));
    if ($tab === 'colacion') {
        unibagOutputColacionExcel($service);
    } elseif ($tab === 'setup' || $tab === 'cambio-configuracion') {
        unibagOutputCambioConfiguracionExcel($service);
    } else {
        unibagOutputDetencionExcel($service);
    }
}

/**
 * Renderiza el Informe de Nivel de Servicio (OTIF / Cumplimiento de Entrega).
 * Adaptado de: libs/modules/stats/bodega/informe.despachos.php y libs/xls.stats.php
 */
function unibagRenderServiceLevelReportPage(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    $start = $filters['start'];
    $end = $filters['end'];
    $activeFilterLabel = (string)$filters['active_filter_label'];
    $defaultFilterType = (string)($filters['filter_type'] ?? 'period');
    $periodYm = (string)($filters['period'] ?? date('Y-m'));
    $rangeStartInput = (string)($filters['start_date'] ?? '');
    $rangeEndInput = (string)($filters['end_date'] ?? '');

    $plantaId = isset($_GET['planta_id']) && is_numeric($_GET['planta_id']) ? (int)$_GET['planta_id'] : null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;
    $delayStatus = isset($_GET['delay_status']) ? trim((string)$_GET['delay_status']) : 'all';
    if (!in_array($delayStatus, ['all', 'delayed', 'on_time'], true)) {
        $delayStatus = 'all';
    }
    $delayDays = isset($_GET['delay_days']) ? trim((string)$_GET['delay_days']) : '';

    $report = $service->getServiceLevelReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $plantaId,
        $search,
        $delayStatus,
        $delayDays
    );

    $plantas = $report['plantas'] ?? [];
    $rows = $report['rows'];
    $summary = $report['summary'];

    $hasCustomFilters = ($delayStatus !== 'all' || $delayDays !== '' || ($search !== null && $search !== ''));
    $canEdit = unibagCanUserPerformModifications();

    $exportParams = http_build_query(array_filter([
        'filter_type' => $defaultFilterType,
        'period' => $periodYm,
        'start_date' => $rangeStartInput,
        'end_date' => $rangeEndInput,
        'planta_id' => $plantaId,
        'delay_status' => ($delayStatus !== 'all' ? $delayStatus : null),
        'delay_days' => ($delayDays !== '' ? $delayDays : null),
        'q' => $search,
    ], static fn($v) => $v !== null && $v !== '' && $v !== 0));

    $body = '<style>
        main { max-width: 98% !important; width: 98% !important; margin: 0 auto !important; padding: 16px 24px !important; box-sizing: border-box !important; }
        .report-shell { max-width: 1600px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 18px; }
        
        .erp-filter-card { width: 100%; box-sizing: border-box; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px 24px; box-shadow: 0 4px 16px rgba(15,23,42,.04); display: flex; flex-direction: column; gap: 16px; }
        .erp-filter-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; width: 100%; }
        .erp-filter-title { font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; text-transform: uppercase; }
        .erp-filter-sub { font-size: 13px; color: #64748b; font-weight: 500; margin-top: 3px; }
        .erp-filter-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .erp-filter-form { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; width: 100%; padding-top: 16px; border-top: 1px solid #f1f5f9; }
        .erp-filter-field { display: flex; flex-direction: column; gap: 5px; }
        .erp-filter-field.is-hidden { display: none !important; }
        .erp-filter-field label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .05em; }
        .erp-filter-field select, .erp-filter-field input { height: 40px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0 12px; background: #f8fafc; font-size: 13px; font-weight: 600; color: #1e293b; outline: none; transition: all .15s ease; box-sizing: border-box; }
        .erp-filter-field select:focus, .erp-filter-field input:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
        .btn-filter-apply { height: 40px; padding: 0 20px; border-radius: 10px; background: linear-gradient(135deg,#2563eb,#1d4ed8); color: #fff; font-weight: 700; font-size: 13px; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,.25); transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; box-sizing: border-box; }
        .btn-filter-apply:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(37,99,235,.35); color: #fff; }
        .btn-filter-secondary { height: 40px; padding: 0 16px; border-radius: 10px; background: #ffffff; color: #334155; font-weight: 700; font-size: 13px; border: 1px solid #cbd5e1; cursor: pointer; transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; box-sizing: border-box; }
        .btn-filter-secondary:hover { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }

        .dashboard-kpis-grid { width: 100%; box-sizing: border-box; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
        .kpi-card-premium { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px 22px; position: relative; overflow: hidden; box-shadow: 0 4px 16px rgba(15,23,42,.03); transition: all .2s ease; text-decoration: none; display: flex; flex-direction: column; justify-content: space-between; min-height: 150px; box-sizing: border-box; }
        .kpi-card-premium:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(15,23,42,.06); }
        .kpi-card-premium::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
        .kpi-card-premium.blue::before { background: linear-gradient(90deg, #2563eb, #60a5fa); }
        .kpi-card-premium.cyan::before { background: linear-gradient(90deg, #00A9A6, #2dd4bf); }
        .kpi-card-premium.green::before { background: linear-gradient(90deg, #10b981, #34d399); }
        .kpi-card-premium.amber::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .kpi-card-premium.red::before { background: linear-gradient(90deg, #ef4444, #f87171); }
        .kpi-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; width: 100%; }
        .kpi-tag { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: 3px 8px; border-radius: 6px; }
        .kpi-card-premium.blue .kpi-tag { background: #eff6ff; color: #1d4ed8; }
        .kpi-card-premium.cyan .kpi-tag { background: #f0fdfa; color: #0d9488; }
        .kpi-card-premium.green .kpi-tag { background: #ecfdf5; color: #047857; }
        .kpi-card-premium.amber .kpi-tag { background: #fffbeb; color: #b45309; }
        .kpi-card-premium.red .kpi-tag { background: #fef2f2; color: #b91c1c; }
        .kpi-num { font-size: 32px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; line-height: 1.1; text-align: center; }
        .kpi-desc { font-size: 12.5px; color: #64748b; margin-top: 5px; font-weight: 500; text-align: center; }
        .kpi-footer-hint { margin-top: 10px; font-size: 11.5px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 4px; }

        .erp-prod-table th { text-align: center; white-space: nowrap; font-size: 11px; vertical-align: middle; }
        .erp-prod-table td { vertical-align: middle; }

        .btn-edit-sl { height: 28px; padding: 0 10px; border-radius: 8px; font-size: 11.5px; font-weight: 700; cursor: pointer; transition: all .15s ease; display: inline-flex; align-items: center; gap: 4px; border: 1px solid; white-space: nowrap; }
        .btn-edit-sl-default { background: #ffffff; color: #2563eb; border-color: #bfdbfe; }
        .btn-edit-sl-default:hover { background: #eff6ff; border-color: #2563eb; transform: translateY(-1px); box-shadow: 0 2px 6px rgba(37,99,235,.15); }
        .btn-edit-sl-active { background: #eff6ff; color: #1d4ed8; border-color: #60a5fa; box-shadow: 0 1px 4px rgba(37,99,235,.15); }
        .btn-edit-sl-active:hover { background: #dbeafe; }

        .sl-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; }
        .sl-modal-card { background: #ffffff; border-radius: 20px; max-width: 640px; width: 100%; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.2); border: 1px solid #e2e8f0; overflow: hidden; animation: slModalIn .2s ease-out; }
        @keyframes slModalIn { from { opacity: 0; transform: scale(0.96) translateY(8px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .sl-modal-header { padding: 18px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #fafbfc; }
        .sl-modal-close { background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer; padding: 4px 8px; line-height: 1; border-radius: 6px; }
        .sl-modal-close:hover { color: #0f172a; background: #e2e8f0; }
        .sl-modal-summary { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px 24px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; font-size: 11.5px; }
        .sl-summary-item { display: flex; flex-direction: column; gap: 2px; }
        .sl-summary-label { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
        .sl-summary-val { font-size: 12px; font-weight: 700; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sl-modal-footer { padding: 16px 24px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; align-items: center; gap: 10px; background: #fafbfc; }
        .btn-sl-primary { height: 40px; padding: 0 20px; border-radius: 10px; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; font-weight: 700; font-size: 13px; border: none; cursor: pointer; transition: all .15s; box-shadow: 0 4px 12px rgba(37,99,235,.25); }
        .btn-sl-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(37,99,235,.35); }
        .btn-sl-secondary { height: 40px; padding: 0 16px; border-radius: 10px; background: #fff; color: #475569; font-weight: 700; font-size: 13px; border: 1px solid #cbd5e1; cursor: pointer; transition: all .15s; }
        .btn-sl-secondary:hover { background: #f8fafc; color: #0f172a; }
        .btn-sl-danger { height: 40px; padding: 0 16px; border-radius: 10px; background: #fef2f2; color: #dc2626; font-weight: 700; font-size: 13px; border: 1px solid #fecaca; cursor: pointer; transition: all .15s; }
        .btn-sl-danger:hover { background: #fee2e2; }

        @media (max-width: 992px) {
            .dashboard-kpis-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .sl-modal-summary { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .dashboard-kpis-grid { grid-template-columns: 1fr; }
            .erp-filter-header { flex-direction: column; align-items: stretch; }
            .erp-filter-form { flex-direction: column; align-items: stretch; }
            .erp-filter-field select, .erp-filter-field input, .btn-filter-apply, .btn-filter-secondary { width: 100%; }
            .sl-modal-summary { grid-template-columns: 1fr; }
        }
    </style>';

    $body .= '<div class="report-shell">';

    // Card Filtros Unificada tipo PANEL DE CONTROL Y PRODUCCIÓN ERP
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">🚚 INFORME DE NIVEL DE SERVICIO (OTIF)</div>';
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . ' · ' . count($rows) . ' entregas registradas' . ($summary['has_delay_filter'] ? ' (filtradas de ' . $summary['total_dispatches'] . ' totales)' : '') . '</div>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-actions">';
    if (!empty($rows)) {
        $body .= '<a class="btn-filter-secondary" href="/reports/nivel-servicio/excel?' . h($exportParams) . '">📥 Exportar XLS (' . count($rows) . ')</a>';
    }
    $body .= '<a class="btn-filter-secondary" href="/">← Volver al Dashboard</a>';
    $body .= '</div>';
    $body .= '</div>';

    $body .= '<form id="sl-filter-form" method="get" action="/reports/nivel-servicio" class="erp-filter-form">';
    
    // Tipo de filtro
    $body .= '<div class="erp-filter-field">';
    $body .= '<label for="filter_type_sl">Tipo de filtro</label>';
    $body .= '<select id="filter_type_sl" name="filter_type">';
    $body .= '<option value="period"' . ($defaultFilterType === 'period' ? ' selected' : '') . '>Período (26 al 25)</option>';
    $body .= '<option value="range"' . ($defaultFilterType === 'range' ? ' selected' : '') . '>Rango personalizado</option>';
    $body .= '</select>';
    $body .= '</div>';

    // Mes del período
    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'period' ? '' : ' is-hidden') . '" data-filter-group="period">';
    $body .= '<label for="period_sl">Mes del período</label>';
    $body .= '<input id="period_sl" type="month" name="period" value="' . h($periodYm) . '"' . ($defaultFilterType === 'period' ? '' : ' disabled') . '>';
    $body .= '</div>';

    // Rango personalizado
    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="start_date_sl">Fecha inicio</label>';
    $body .= '<input id="start_date_sl" type="date" name="start_date" value="' . h($rangeStartInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="end_date_sl">Fecha término</label>';
    $body .= '<input id="end_date_sl" type="date" name="end_date" value="' . h($rangeEndInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    // Filtro por Estado de Atrasos
    $body .= '<div class="erp-filter-field" style="min-width:170px">';
    $body .= '<label for="delay_status_sl">Estado de Entrega</label>';
    $body .= '<select id="delay_status_sl" name="delay_status">';
    $body .= '<option value="all"' . ($delayStatus === 'all' ? ' selected' : '') . '>Todos los despachos</option>';
    $body .= '<option value="delayed"' . ($delayStatus === 'delayed' ? ' selected' : '') . '>⚠️ Solo con atraso</option>';
    $body .= '<option value="on_time"' . ($delayStatus === 'on_time' ? ' selected' : '') . '>✅ A tiempo (sin atraso)</option>';
    $body .= '</select>';
    $body .= '</div>';

    // Filtro por Cantidad de Días de Atraso
    $body .= '<div class="erp-filter-field" style="min-width:180px">';
    $body .= '<label for="delay_days_sl">Días de Atraso</label>';
    $body .= '<select id="delay_days_sl" name="delay_days">';
    $body .= '<option value=""' . ($delayDays === '' ? ' selected' : '') . '>Todos los días de atraso</option>';
    $body .= '<option value="1-3"' . ($delayDays === '1-3' ? ' selected' : '') . '>1 a 3 días de atraso</option>';
    $body .= '<option value="4-7"' . ($delayDays === '4-7' ? ' selected' : '') . '>4 a 7 días de atraso</option>';
    $body .= '<option value="8-14"' . ($delayDays === '8-14' ? ' selected' : '') . '>8 a 14 días de atraso</option>';
    $body .= '<option value="15+"' . ($delayDays === '15+' ? ' selected' : '') . '>15 o más días de atraso</option>';
    $body .= '<option value="min_3"' . ($delayDays === 'min_3' ? ' selected' : '') . '>≥ 3 días de atraso</option>';
    $body .= '<option value="min_7"' . ($delayDays === 'min_7' ? ' selected' : '') . '>≥ 7 días de atraso</option>';
    $body .= '<option value="min_14"' . ($delayDays === 'min_14' ? ' selected' : '') . '>≥ 14 días de atraso</option>';
    $body .= '</select>';
    $body .= '</div>';

    // Buscador
    $body .= '<div class="erp-filter-field" style="flex:1;min-width:220px">';
    $body .= '<label for="q_sl">Buscar Cliente / CC / Documento / Producto</label>';
    $body .= '<input id="q_sl" type="text" name="q" placeholder="Ej: SMU, Rendic, 26-00483, GV14469..." value="' . h((string)$search) . '">';
    $body .= '</div>';

    // Acciones de búsqueda
    $body .= '<div style="display:flex;gap:8px;align-items:flex-end">';
    $body .= '<button type="submit" class="btn-filter-apply">🔍 Aplicar Filtros</button>';
    if ($hasCustomFilters) {
        $body .= '<a class="btn-filter-secondary" href="/reports/nivel-servicio" title="Limpiar todos los filtros">✕ Limpiar</a>';
    }
    $body .= '</div>';

    $body .= '</form>';
    $body .= '</div>';

    $body .= '<script>
        (function () {
            var filterType = document.getElementById("filter_type_sl");
            var form = document.getElementById("sl-filter-form");
            var delayStatus = document.getElementById("delay_status_sl");
            var delayDays = document.getElementById("delay_days_sl");

            function syncFilterFields() {
                if (!filterType || !form) return;
                var mode = filterType.value === "range" ? "range" : "period";
                form.querySelectorAll("[data-filter-group]").forEach(function (field) {
                    var visible = field.getAttribute("data-filter-group") === mode;
                    field.classList.toggle("is-hidden", !visible);
                    field.querySelectorAll("input, select").forEach(function (input) {
                        if (input === filterType) return;
                        input.disabled = !visible;
                    });
                });
            }

            function syncDelayFields() {
                if (!delayStatus || !delayDays) return;
                if (delayStatus.value === "on_time") {
                    delayDays.disabled = true;
                    delayDays.style.opacity = "0.5";
                } else {
                    delayDays.disabled = false;
                    delayDays.style.opacity = "1";
                }
            }

            if (filterType && form) {
                filterType.addEventListener("change", syncFilterFields);
                syncFilterFields();
            }
            if (delayStatus && delayDays) {
                delayStatus.addEventListener("change", syncDelayFields);
                syncDelayFields();
            }
        })();
    </script>';

    // Mensaje de filtro activo si aplica
    if ($summary['has_delay_filter']) {
        $statusText = match($delayStatus) {
            'delayed' => 'Solo con atraso',
            'on_time' => 'A tiempo',
            default => 'Todos los estados'
        };
        $daysText = $delayDays !== '' ? ' · Días: ' . h($delayDays) : '';
        $body .= '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:14px;padding:12px 18px;color:#1e40af;font-size:13px;font-weight:600;display:flex;align-items:center;justify-content:space-between;gap:12px">';
        $body .= '<div>🎯 <strong>Filtro de atraso activo:</strong> ' . h($statusText) . $daysText . ' — Mostrando <strong>' . $summary['filtered_count'] . '</strong> de <strong>' . $summary['total_dispatches'] . '</strong> despachos registrados en el período.</div>';
        $body .= '<a href="/reports/nivel-servicio" style="color:#2563eb;text-decoration:none;font-weight:700;font-size:12px;white-space:nowrap">Mostrar todos</a>';
        $body .= '</div>';
    }

    // Tarjetas KPI Principales estilo PANEL DE CONTROL Y PRODUCCIÓN ERP
    $body .= '<div class="dashboard-kpis-grid">';
    
    // KPI 1: Nivel de Servicio OTIF
    $serviceLevelVal = (float)$summary['service_level_percent'];
    $slCardClass = ($serviceLevelVal >= 90.0) ? 'green' : (($serviceLevelVal >= 75.0) ? 'amber' : 'red');
    $slBadgeColor = ($serviceLevelVal >= 90.0) ? '#047857' : (($serviceLevelVal >= 75.0) ? '#b45309' : '#b91c1c');
    $body .= '<div class="kpi-card-premium ' . $slCardClass . '">';
    $body .= '<div class="kpi-header-row"><span class="kpi-tag">OTIF Cumplimiento</span><span style="font-size:18px;">🎯</span></div>';
    $body .= '<div style="display:flex;flex-direction:column;align-items:center;text-align:center;margin:4px 0">';
    $body .= '<div class="kpi-num" style="color:' . $slBadgeColor . '">' . number_format($serviceLevelVal, 1, ',', '.') . '%</div>';
    $body .= '<div class="kpi-desc">Nivel de servicio global período</div>';
    $body .= '</div>';
    $body .= '<div class="kpi-footer-hint" style="color:' . $slBadgeColor . '">Meta operativa: ≥ 90.0%</div>';
    $body .= '</div>';

    // KPI 2: Despachos a Tiempo
    $body .= '<div class="kpi-card-premium cyan">';
    $body .= '<div class="kpi-header-row"><span class="kpi-tag">A Tiempo</span><span style="font-size:18px;">✅</span></div>';
    $body .= '<div style="display:flex;flex-direction:column;align-items:center;text-align:center;margin:4px 0">';
    $body .= '<div class="kpi-num" style="color:#0d9488">' . number_format($summary['on_time_dispatches'], 0, ',', '.') . '</div>';
    $body .= '<div class="kpi-desc">Despachos cumplidos a fecha</div>';
    $body .= '</div>';
    $body .= '<div class="kpi-footer-hint" style="color:#0d9488">Sin retraso respecto a fecha pactada</div>';
    $body .= '</div>';

    // KPI 3: Despachos con Atraso
    $body .= '<div class="kpi-card-premium red">';
    $body .= '<div class="kpi-header-row"><span class="kpi-tag">Con Atraso</span><span style="font-size:18px;">⚠️</span></div>';
    $body .= '<div style="display:flex;flex-direction:column;align-items:center;text-align:center;margin:4px 0">';
    $body .= '<div class="kpi-num" style="color:#dc2626">' . number_format($summary['delayed_dispatches'], 0, ',', '.') . '</div>';
    $body .= '<div class="kpi-desc">Despachos fuera de plazo comprometido</div>';
    $body .= '</div>';
    $body .= '<div class="kpi-footer-hint" style="color:#b91c1c">' . ($summary['has_delay_filter'] ? 'Mostrando ' . $summary['filtered_count'] . ' filtrados' : 'Requieren gestión logística') . '</div>';
    $body .= '</div>';

    // KPI 4: Unidades Totales Despachadas
    $body .= '<div class="kpi-card-premium blue">';
    $body .= '<div class="kpi-header-row"><span class="kpi-tag">Volumen Despachado</span><span style="font-size:18px;">📦</span></div>';
    $body .= '<div style="display:flex;flex-direction:column;align-items:center;text-align:center;margin:4px 0">';
    $body .= '<div class="kpi-num" style="color:#1d4ed8">' . number_format($summary['total_dispatched_units'], 0, ',', '.') . '</div>';
    $body .= '<div class="kpi-desc">Unidades físicas despachadas</div>';
    $body .= '</div>';
    $body .= '<div class="kpi-footer-hint" style="color:#1d4ed8">Total entregado a clientes</div>';
    $body .= '</div>';

    $body .= '</div>'; // Fin dashboard-kpis-grid

    // Tabla de Resultados
    $body .= '<div class="card" style="border-radius:18px;border:1px solid #e2e8f0;box-shadow:0 4px 16px rgba(15,23,42,.03);overflow:hidden;padding:20px 24px">';
    if (empty($rows)) {
        $body .= '<div class="erp-prod-empty text-center" style="padding:40px 20px;color:#64748b;font-size:14px;font-weight:600">No se encontraron despachos para los filtros seleccionados.</div>';
    } else {
        $body .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">';
        $body .= '<div style="font-size:14px;font-weight:700;color:#0f172a">Listado detallado de despachos y cumplimiento (' . count($rows) . ' registros)</div>';
        $body .= '</div>';

        $body .= '<div class="erp-prod-table-wrap"><table class="erp-prod-table table-compact">';
        $body .= '<thead><tr>';
        $body .= '<th>Fecha Despacho</th><th>Doc. / Guía</th><th>Cliente</th><th>Canal</th><th>N° CC</th><th>Cód. Producto</th><th>Fecha Comprometida</th><th class="text-center">Cumplimiento</th><th class="text-center">Días Atraso</th><th class="text-right">Unid. Despachadas</th><th class="text-center">Pallets / Cajas</th><th>Transporte / Conductor</th><th>Observación</th><th class="text-center" style="width:115px">Acción</th>';
        $body .= '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $hasAdj = !empty($r['has_adjustment']);
            if ($hasAdj) {
                if ($r['is_on_time']) {
                    $badge = '<span style="background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:800;display:inline-flex;align-items:center;gap:4px" title="' . h($r['reason_category']) . ($r['reason_details'] !== '' ? ': ' . h($r['reason_details']) : '') . '">✓ A tiempo (Acuerdo)</span>';
                } else {
                    $badge = '<span style="background:#fffbeb;color:#b45309;border:1px solid #fde68a;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:800;display:inline-flex;align-items:center;gap:4px">⚠️ Con atraso (Reprog.)</span>';
                }
            } else {
                $badge = $r['is_on_time']
                    ? '<span style="background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:800;display:inline-flex;align-items:center;gap:4px">✓ A tiempo</span>'
                    : '<span style="background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:800;display:inline-flex;align-items:center;gap:4px">⚠️ Con atraso</span>';
            }

            if ($r['is_on_time']) {
                $delayBadge = '<span style="color:#64748b;font-size:11px;font-weight:600">' . ($hasAdj ? '0 d (Ajustado)' : '0 d') . '</span>';
            } else {
                $delayBadge = '<span style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:800">+' . (int)$r['delay_days'] . ' días</span>';
            }

            if ($hasAdj && !empty($r['adjusted_committed_date'])) {
                $committedDateHtml = '<strong style="color:#2563eb">' . h($r['adjusted_committed_date']) . '</strong><br><span style="font-size:10px;color:#94a3b8;text-decoration:line-through" title="Fecha original">' . h($r['original_committed_date']) . '</span>';
            } else {
                $committedDateHtml = h($r['committed_date']);
            }

            $driverInfo = h($r['transport_company']);
            if ($r['driver_name'] !== '') {
                $driverInfo .= ($driverInfo !== '' ? '<br>' : '') . '<span style="font-size:10.5px;color:#334155">' . h($r['driver_name']) . ($r['vehicle_plate'] !== '' ? ' (' . h($r['vehicle_plate']) . ')' : '') . '</span>';
            }

            $obsHtml = h($r['observation']);
            if ($hasAdj) {
                $adjNote = '<span style="background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;border-radius:6px;padding:2px 6px;font-size:10px;font-weight:700;display:inline-block;margin-bottom:3px">📋 ' . h($r['reason_category']) . '</span>';
                if ($r['reason_details'] !== '') {
                    $adjNote .= '<br><span style="font-size:10.5px;color:#334155">' . h($r['reason_details']) . '</span>';
                }
                $obsHtml = $adjNote . ($obsHtml !== '' ? '<br>' . $obsHtml : '');
            }

            if ($canEdit) {
                $actionBtnClass = $hasAdj ? 'btn-edit-sl-active' : 'btn-edit-sl-default';
                $actionBtnText = $hasAdj ? '✏️ Editado' : '✏️ Modificar';
                $actionBtnTitle = $hasAdj ? 'Registro con acuerdo/ajuste aplicado. Clic para editar o revertir.' : 'Modificar fecha o justificar entrega por acuerdo con cliente.';
                $actionHtml = '<button type="button" class="btn-edit-sl ' . $actionBtnClass . '" '
                    . 'data-despacho-id="' . (int)$r['despacho_id'] . '" '
                    . 'data-od-id="' . (int)($r['orders_delivery_id'] ?? 0) . '" '
                    . 'data-doc-number="' . h($r['doc_number']) . '" '
                    . 'data-customer-name="' . h($r['customer_name']) . '" '
                    . 'data-cc-number="' . h($r['cc_number']) . '" '
                    . 'data-product-code="' . h($r['product_code']) . '" '
                    . 'data-disp-date="' . h($r['date']) . '" '
                    . 'data-raw-disp-date="' . h($r['raw_date']) . '" '
                    . 'data-committed-date="' . h($r['original_committed_date'] !== 'Sin fecha' ? $r['original_committed_date'] : $r['committed_date']) . '" '
                    . 'data-raw-committed-date="' . h($r['raw_orig_committed_date'] ?: $r['raw_committed_date']) . '" '
                    . 'data-raw-adjusted-date="' . h($r['raw_adjusted_date'] ?? '') . '" '
                    . 'data-has-adjustment="' . ($hasAdj ? '1' : '0') . '" '
                    . 'data-is-justified="' . (!empty($r['is_justified']) ? '1' : '0') . '" '
                    . 'data-reason-category="' . h($r['reason_category'] ?? '') . '" '
                    . 'data-reason-details="' . h($r['reason_details'] ?? '') . '" '
                    . 'data-updated-by="' . h($r['updated_by'] ?? '') . '" '
                    . 'data-updated-at="' . h($r['updated_at'] ?? '') . '" '
                    . 'title="' . h($actionBtnTitle) . '" '
                    . 'onclick="openSlModal(this)">'
                    . $actionBtnText
                    . '</button>';
            } else {
                $actionHtml = '<span class="erp-prod-muted" style="font-size:11px;color:#94a3b8" title="Modificaciones permitidas únicamente para HECTOR y JAVIER">🔒 Bloqueado</span>';
            }

            $body .= '<tr>';
            $body .= '<td class="text-center">' . h($r['date']) . ($r['entry_time'] !== '' && $r['entry_time'] !== '00:00:00' ? '<br><span class="erp-prod-muted" style="font-size:10px">' . h($r['entry_time']) . '</span>' : '') . '</td>';
            $body .= '<td class="text-center"><strong>' . h($r['doc_number']) . '</strong><br><span class="erp-prod-muted" style="font-size:10px">' . h($r['doc_type']) . '</span></td>';
            $body .= '<td><strong>' . h($r['customer_name']) . '</strong></td>';
            $body .= '<td class="text-center" style="font-size:11px">' . h($r['sales_channel']) . '</td>';
            $body .= '<td class="text-center erp-prod-code">' . h($r['cc_number']) . '</td>';
            $body .= '<td class="text-center" style="font-size:11px">' . h($r['product_code']) . '</td>';
            $body .= '<td class="text-center" style="font-size:11px">' . $committedDateHtml . '</td>';
            $body .= '<td class="text-center">' . $badge . '</td>';
            $body .= '<td class="text-center">' . $delayBadge . '</td>';
            $body .= '<td class="text-right" style="font-weight:700;color:#0f766e">' . number_format($r['dispatched_units'], 0, ',', '.') . '</td>';
            $body .= '<td class="text-center" style="font-size:11px">' . $r['pallets'] . ' p / ' . $r['boxes'] . ' c</td>';
            $body .= '<td style="font-size:11px">' . $driverInfo . '</td>';
            $body .= '<td style="font-size:11px">' . $obsHtml . '</td>';
            $body .= '<td class="text-center">' . $actionHtml . '</td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div>';
    }
    $body .= '</div></div>';

    // Modal de Modificación / Justificación por Acuerdo con Cliente
    if ($canEdit) {
        $csrfVal = h($_SESSION['csrf'] ?? '');
        $body .= '
    <div id="sl-adjust-modal" class="sl-modal-overlay" style="display:none;" onclick="if(event.target===this)closeSlModal();">
        <div class="sl-modal-card">
            <div class="sl-modal-header">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="font-size:24px;">🚚</span>
                    <div>
                        <h3 style="margin:0;font-size:16px;font-weight:800;color:#0f172a;">Modificar Fecha / Justificar Entrega</h3>
                        <div style="font-size:12px;color:#64748b;margin-top:2px;">Nivel de Servicio (OTIF) · Gestión y Acuerdo con Cliente</div>
                    </div>
                </div>
                <button type="button" class="sl-modal-close" onclick="closeSlModal()">&times;</button>
            </div>

            <div class="sl-modal-summary">
                <div class="sl-summary-item">
                    <span class="sl-summary-label">Cliente</span>
                    <span class="sl-summary-val" id="sl_sum_customer">-</span>
                </div>
                <div class="sl-summary-item">
                    <span class="sl-summary-label">Doc. / Guía</span>
                    <span class="sl-summary-val" id="sl_sum_doc">-</span>
                </div>
                <div class="sl-summary-item">
                    <span class="sl-summary-label">N° CC</span>
                    <span class="sl-summary-val" id="sl_sum_cc">-</span>
                </div>
                <div class="sl-summary-item">
                    <span class="sl-summary-label">Fecha Despacho</span>
                    <span class="sl-summary-val" id="sl_sum_disp_date" style="color:#0f172a;font-weight:800;">-</span>
                </div>
                <div class="sl-summary-item">
                    <span class="sl-summary-label">Fecha Comprometida Orig.</span>
                    <span class="sl-summary-val" id="sl_sum_orig_date" style="color:#dc2626;">-</span>
                </div>
                <div class="sl-summary-item">
                    <span class="sl-summary-label">Cód. Producto</span>
                    <span class="sl-summary-val" id="sl_sum_prod">-</span>
                </div>
            </div>

            <form id="sl-adjust-form" onsubmit="submitSlAdjustment(event)">
                <input type="hidden" name="_csrf" value="' . $csrfVal . '">
                <input type="hidden" id="sl_input_despacho_id" name="despacho_id" value="">
                <input type="hidden" id="sl_input_orders_delivery_id" name="orders_delivery_id" value="">
                <input type="hidden" id="sl_input_doc_number" name="doc_number" value="">
                <input type="hidden" id="sl_input_cc_number" name="cc_number" value="">

                <div style="display:flex;flex-direction:column;gap:14px;padding:20px 24px;">
                    <div class="sl-form-group">
                        <label for="sl_input_adjusted_date" style="font-size:12px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">
                            📅 Nueva Fecha Acordada con Cliente (Fecha Comprometida Modificada):
                        </label>
                        <input type="date" id="sl_input_adjusted_date" name="adjusted_date" class="sl-input" style="width:100%;height:40px;border-radius:10px;border:1px solid #cbd5e1;padding:0 12px;font-size:13px;font-weight:600;box-sizing:border-box;">
                        <div style="font-size:11.5px;color:#64748b;margin-top:4px;">
                            Si la entrega física se realizó en o antes de esta fecha acordada, se contabilizará automáticamente como cumplida a tiempo.
                        </div>
                    </div>

                    <div class="sl-form-group">
                        <label for="sl_input_reason_category" style="font-size:12px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">
                            📌 Motivo / Causa de la Modificación o Postergación:
                        </label>
                        <select id="sl_input_reason_category" name="reason_category" class="sl-select" required style="width:100%;height:40px;border-radius:10px;border:1px solid #cbd5e1;padding:0 12px;font-size:13px;font-weight:600;background:#fff;box-sizing:border-box;">
                            <option value="Falta de pago / Retención de crédito">Falta de pago / Retención de crédito por cobranza</option>
                            <option value="Espera de confirmación del cliente">Espera de confirmación del cliente</option>
                            <option value="Acuerdo de postergación con cliente">Acuerdo de postergación con cliente</option>
                            <option value="Reprogramación solicitada por cliente">Reprogramación de fecha solicitada por cliente</option>
                            <option value="Retiro por transporte de cliente postergado">Retiro por transporte de cliente postergado</option>
                            <option value="Fuerza mayor / Caso fortuito">Fuerza mayor / Caso fortuito</option>
                            <option value="Otro acuerdo comercial">Otro acuerdo comercial (especificar en detalle)</option>
                        </select>
                    </div>

                    <div class="sl-form-group" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;">
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin:0;">
                            <input type="checkbox" id="sl_input_is_justified" name="is_justified" value="1" style="width:18px;height:18px;cursor:pointer;accent-color:#2563eb;">
                            <span style="font-size:13px;font-weight:700;color:#0f172a;">
                                Marcar entrega como justificada / Cumplida por acuerdo comercial
                            </span>
                        </label>
                        <div style="font-size:11.5px;color:#64748b;margin-left:28px;margin-top:3px;">
                            Al activar esta casilla, esta entrega se computará como "A tiempo" en el indicador de Nivel de Servicio (OTIF), ya que el atraso no es imputable a la operación de planta.
                        </div>
                    </div>

                    <div class="sl-form-group">
                        <label for="sl_input_reason_details" style="font-size:12px;font-weight:700;color:#334155;display:block;margin-bottom:6px;">
                            📝 Detalle / Observación del Acuerdo:
                        </label>
                        <textarea id="sl_input_reason_details" name="reason_details" rows="3" class="sl-textarea" placeholder="Indique antecedentes adicionales (ej: contacto del cliente, N° de correo de solicitud, acuerdo con área comercial...)" style="width:100%;border-radius:10px;border:1px solid #cbd5e1;padding:10px 12px;font-size:13px;box-sizing:border-box;resize:vertical;"></textarea>
                    </div>

                    <div id="sl_audit_info" style="display:none;background:#f1f5f9;border-radius:8px;padding:8px 12px;font-size:11.5px;color:#475569;">
                        ℹ️ <span id="sl_audit_text"></span>
                    </div>

                    <div id="sl_modal_error" style="display:none;background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:10px 14px;border-radius:10px;font-size:12.5px;font-weight:600;"></div>
                </div>

                <div class="sl-modal-footer">
                    <button type="button" class="btn-sl-secondary" onclick="closeSlModal()">Cancelar</button>
                    <button type="button" id="btn_sl_revert" class="btn-sl-danger" onclick="revertSlAdjustment()" style="display:none;">↩️ Restaurar Original</button>
                    <button type="submit" id="btn_sl_save" class="btn-sl-primary">💾 Guardar Modificación</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openSlModal(btn) {
            var modal = document.getElementById("sl-adjust-modal");
            var errBox = document.getElementById("sl_modal_error");
            if (errBox) errBox.style.display = "none";

            var d = btn.dataset;
            document.getElementById("sl_sum_customer").textContent = d.customerName || "-";
            document.getElementById("sl_sum_doc").textContent = d.docNumber || "-";
            document.getElementById("sl_sum_cc").textContent = d.ccNumber || "-";
            document.getElementById("sl_sum_disp_date").textContent = d.dispDate || "-";
            document.getElementById("sl_sum_orig_date").textContent = d.committedDate || "-";
            document.getElementById("sl_sum_prod").textContent = d.productCode || "-";

            document.getElementById("sl_input_despacho_id").value = d.despachoId || "";
            document.getElementById("sl_input_orders_delivery_id").value = d.odId || "";
            document.getElementById("sl_input_doc_number").value = d.docNumber || "";
            document.getElementById("sl_input_cc_number").value = d.ccNumber || "";

            var dateInput = document.getElementById("sl_input_adjusted_date");
            dateInput.value = d.rawAdjustedDate || d.rawCommittedDate || "";

            var isJustifiedCheck = document.getElementById("sl_input_is_justified");
            if (d.hasAdjustment === "1") {
                isJustifiedCheck.checked = (d.isJustified === "1");
            } else {
                isJustifiedCheck.checked = true;
            }

            var reasonSelect = document.getElementById("sl_input_reason_category");
            if (d.reasonCategory) {
                reasonSelect.value = d.reasonCategory;
            } else {
                reasonSelect.value = "Falta de pago / Retención de crédito";
            }

            document.getElementById("sl_input_reason_details").value = d.reasonDetails || "";

            var revertBtn = document.getElementById("btn_sl_revert");
            var auditBox = document.getElementById("sl_audit_info");
            var auditText = document.getElementById("sl_audit_text");
            if (d.hasAdjustment === "1") {
                revertBtn.style.display = "inline-flex";
                auditBox.style.display = "block";
                auditText.textContent = "Modificado previamente por " + (d.updatedBy || "usuario") + (d.updatedAt ? " el " + d.updatedAt : "");
            } else {
                revertBtn.style.display = "none";
                auditBox.style.display = "none";
            }

            modal.style.display = "flex";
        }

        function closeSlModal() {
            var modal = document.getElementById("sl-adjust-modal");
            if (modal) modal.style.display = "none";
        }

        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") closeSlModal();
        });

        function submitSlAdjustment(e) {
            e.preventDefault();
            var form = document.getElementById("sl-adjust-form");
            var saveBtn = document.getElementById("btn_sl_save");
            var errBox = document.getElementById("sl_modal_error");
            if (errBox) errBox.style.display = "none";

            saveBtn.disabled = true;
            saveBtn.textContent = "Guardando...";

            var fd = new FormData(form);

            fetch("/reports/nivel-servicio/update", {
                method: "POST",
                body: fd,
                headers: { "X-Requested-With": "XMLHttpRequest" }
            })
            .then(function(res) {
                return res.json().then(function(data) {
                    return { ok: res.ok, status: res.status, data: data };
                });
            })
            .then(function(resObj) {
                saveBtn.disabled = false;
                saveBtn.textContent = "💾 Guardar Modificación";
                if (resObj.ok && resObj.data && resObj.data.ok) {
                    closeSlModal();
                    window.location.reload();
                } else {
                    var errMsg = (resObj.data && (resObj.data.error || resObj.data.message)) || "Error al guardar el ajuste.";
                    if (errBox) {
                        errBox.textContent = errMsg;
                        errBox.style.display = "block";
                    } else {
                        alert(errMsg);
                    }
                }
            })
            .catch(function(err) {
                saveBtn.disabled = false;
                saveBtn.textContent = "💾 Guardar Modificación";
                if (errBox) {
                    errBox.textContent = "Error de conexión: " + err.message;
                    errBox.style.display = "block";
                } else {
                    alert("Error: " + err.message);
                }
            });
        }

        function revertSlAdjustment() {
            if (!confirm("¿Está seguro de restaurar este registro a su fecha y estado original del ERP?")) {
                return;
            }

            var form = document.getElementById("sl-adjust-form");
            var revertBtn = document.getElementById("btn_sl_revert");
            var errBox = document.getElementById("sl_modal_error");
            if (errBox) errBox.style.display = "none";

            revertBtn.disabled = true;
            revertBtn.textContent = "Restaurando...";

            var fd = new FormData(form);

            fetch("/reports/nivel-servicio/revert", {
                method: "POST",
                body: fd,
                headers: { "X-Requested-With": "XMLHttpRequest" }
            })
            .then(function(res) {
                return res.json().then(function(data) {
                    return { ok: res.ok, status: res.status, data: data };
                });
            })
            .then(function(resObj) {
                revertBtn.disabled = false;
                revertBtn.textContent = "↩️ Restaurar Original";
                if (resObj.ok && resObj.data && resObj.data.ok) {
                    closeSlModal();
                    window.location.reload();
                } else {
                    var errMsg = (resObj.data && (resObj.data.error || resObj.data.message)) || "Error al revertir el ajuste.";
                    if (errBox) {
                        errBox.textContent = errMsg;
                        errBox.style.display = "block";
                    } else {
                        alert(errMsg);
                    }
                }
            })
            .catch(function(err) {
                revertBtn.disabled = false;
                revertBtn.textContent = "↩️ Restaurar Original";
                if (errBox) {
                    errBox.textContent = "Error de conexión: " + err.message;
                    errBox.style.display = "block";
                } else {
                    alert("Error: " + err.message);
                }
            });
        }
    </script>
    ';
    }

    render('Informe de Nivel de Servicio · ERP', $body);
}

function unibagOutputServiceLevelExcel(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    $start = $filters['start'];
    $end = $filters['end'];
    $activeFilterLabel = (string)$filters['active_filter_label'];

    $plantaId = isset($_GET['planta_id']) && is_numeric($_GET['planta_id']) ? (int)$_GET['planta_id'] : null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;
    $delayStatus = isset($_GET['delay_status']) ? trim((string)$_GET['delay_status']) : 'all';
    $delayDays = isset($_GET['delay_days']) ? trim((string)$_GET['delay_days']) : '';

    $report = $service->getServiceLevelReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $plantaId,
        $search,
        $delayStatus,
        $delayDays
    );

    $rows = $report['rows'];
    $filename = 'informe-nivel-servicio-' . date('Ymd-His') . '.xlsx';
    ob_start();

    echo '<html><head><meta charset="UTF-8"><style>
        body{font-family:Arial,sans-serif;font-size:12px;color:#0f172a}
        table{border-collapse:collapse;width:100%}
        th,td{border:1px solid #cbd5e1;padding:6px 8px}
        th{background:#0f172a;color:#fff;font-weight:700;text-align:center}
        tr:nth-child(even){background:#f8fafc}
        .num-int{mso-number-format:"\#\,\#\#0";text-align:right}
        .text-cell{mso-number-format:"\@"}
    </style></head><body>';
    echo '<h2>Informe de Nivel de Servicio (OTIF)</h2>';
    $filterDesc = h($activeFilterLabel);
    if ($delayStatus !== 'all') {
        $filterDesc .= ' · Estado: ' . ($delayStatus === 'delayed' ? 'Solo con atraso' : 'A tiempo');
    }
    if ($delayDays !== '') {
        $filterDesc .= ' · Días atraso: ' . h($delayDays);
    }
    echo '<p>Filtro: ' . $filterDesc . ' · Total registros: ' . count($rows) . ' · Generado: ' . date('d/m/Y H:i') . '</p>';
    echo '<table>';
    echo '<tr><th>Fecha Despacho</th><th>Hora Entrada</th><th>Hora Salida</th><th>Tipo Documento</th><th>N° Documento</th><th>Cliente</th><th>Canal de Venta</th><th>N° CC</th><th>Cód. Producto</th><th>Fecha Original</th><th>Fecha Comprometida (Ajustada)</th><th>Estado Plazo</th><th>Días Atraso</th><th>Estado Acuerdo</th><th>Motivo Ajuste / Postergación</th><th>Detalle Acuerdo</th><th>Modificado Por</th><th>Unid. Despachadas</th><th>Pallets</th><th>Cajas</th><th>Transporte</th><th>Patente</th><th>Conductor</th><th>RUT</th><th>Sello</th><th>Observación</th></tr>';
    foreach ($rows as $r) {
        $hasAdj = !empty($r['has_adjustment']);
        $origDate = $r['original_committed_date'] !== 'Sin fecha' ? $r['original_committed_date'] : $r['committed_date'];
        $adjDate = ($hasAdj && !empty($r['adjusted_committed_date'])) ? $r['adjusted_committed_date'] : $r['committed_date'];
        $agreementStatus = $hasAdj ? (!empty($r['is_justified']) ? 'Justificado por acuerdo' : 'Fecha reprogramada') : 'Sin modificación';

        echo '<tr>';
        echo '<td>' . h($r['date']) . '</td>';
        echo '<td>' . h($r['entry_time']) . '</td>';
        echo '<td>' . h($r['exit_time']) . '</td>';
        echo '<td>' . h($r['doc_type']) . '</td>';
        echo '<td>' . h($r['doc_number']) . '</td>';
        echo '<td>' . h($r['customer_name']) . '</td>';
        echo '<td>' . h($r['sales_channel']) . '</td>';
        echo '<td>' . h($r['cc_number']) . '</td>';
        echo '<td>' . h($r['product_code']) . '</td>';
        echo '<td>' . h($origDate) . '</td>';
        echo '<td>' . h($adjDate) . '</td>';
        echo '<td>' . ($r['is_on_time'] ? 'A tiempo' : 'Con atraso') . '</td>';
        echo '<td class="num-int">' . (int)round((float)($r['delay_days'] ?? 0)) . '</td>';
        echo '<td>' . h($agreementStatus) . '</td>';
        echo '<td>' . h($r['reason_category'] ?? '') . '</td>';
        echo '<td>' . h($r['reason_details'] ?? '') . '</td>';
        echo '<td>' . h($r['updated_by'] ?? '') . '</td>';
        echo '<td class="num-int">' . (int)round((float)($r['dispatched_units'] ?? 0)) . '</td>';
        echo '<td class="num-int">' . (int)round((float)($r['pallets'] ?? 0)) . '</td>';
        echo '<td class="num-int">' . (int)round((float)($r['boxes'] ?? 0)) . '</td>';
        echo '<td>' . h($r['transport_company']) . '</td>';
        echo '<td>' . h($r['vehicle_plate']) . '</td>';
        echo '<td>' . h($r['driver_name']) . '</td>';
        echo '<td>' . h($r['driver_rut']) . '</td>';
        echo '<td>' . h($r['seal_number']) . '</td>';
        echo '<td>' . h($r['observation']) . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    $html = ob_get_clean();

    SimpleXlsx::streamHtml($filename, $html, 'Nivel Servicio');
}

/**
 * Renderiza el módulo e informe completo de mermas por operador.
 *
 * Incluye filtros unificados (período/rango, proceso, operador, búsqueda),
 * tarjetas KPI consolidadas, ranking interactivo por operador, tasas de merma
 * con semáforo visual de calidad, desglose por proceso y modal interactivo
 * de órdenes de trabajo (OTs) por trabajador.
 */
function unibagRenderOperatorWasteReportPage(ReceptionService $service): void
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

    $filterOperatorId = isset($_GET['operator_id']) && is_numeric($_GET['operator_id']) ? (int)$_GET['operator_id'] : null;
    $filterProcess = isset($_GET['process']) ? strtolower(trim((string)$_GET['process'])) : null;
    if ($filterProcess === '' || $filterProcess === 'all') {
        $filterProcess = null;
    }
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    $reportData = $service->getErpOperatorWasteReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $filterOperatorId,
        $filterProcess
    );

    $summary = $reportData['summary'];
    $operators = $reportData['operators'];
    $catalog = $reportData['catalog'];
    $processes = $reportData['processes'];

    // Apply search filter in-memory if provided
    if ($search !== null && $search !== '') {
        $searchLower = mb_strtolower($search, 'UTF-8');
        $operators = array_values(array_filter($operators, static function (array $op) use ($searchLower): bool {
            $name = mb_strtolower((string)($op['operator_name'] ?? ''), 'UTF-8');
            $rut = mb_strtolower((string)($op['operator_rut'] ?? ''), 'UTF-8');
            $proc = mb_strtolower((string)($op['main_process'] ?? ''), 'UTF-8');
            return str_contains($name, $searchLower) || str_contains($rut, $searchLower) || str_contains($proc, $searchLower);
        }));
    }

    $fmtUnits = static fn(float $val): string => number_format($val, 0, ',', '.');
    $fmtDec = static fn(float $val): string => number_format($val, 2, ',', '.');

    // Build URL helper
    $buildUrl = static function (array $overrides) use ($defaultFilterType, $periodYm, $rangeStartInput, $rangeEndInput, $filterProcess, $filterOperatorId, $search): string {
        $params = [
            'filter_type' => $defaultFilterType,
            'period' => $periodYm,
            'start_date' => $rangeStartInput,
            'end_date' => $rangeEndInput,
            'process' => $filterProcess,
            'operator_id' => $filterOperatorId,
            'q' => $search,
        ];
        foreach ($overrides as $k => $v) {
            if ($v === null || $v === '') {
                unset($params[$k]);
            } else {
                $params[$k] = $v;
            }
        }
        return '/reports/operator-waste?' . http_build_query(array_filter($params, static fn($v) => $v !== null && $v !== ''));
    };

    $excelUrl = '/reports/operator-waste/excel?' . http_build_query(array_filter([
        'filter_type' => $defaultFilterType,
        'period' => $periodYm,
        'start_date' => $rangeStartInput,
        'end_date' => $rangeEndInput,
        'process' => $filterProcess,
        'operator_id' => $filterOperatorId,
        'q' => $search,
    ], static fn($v) => $v !== null && $v !== ''));

    $body = '<style>
        main { max-width: 98% !important; width: 98% !important; margin: 0 auto !important; padding: 16px 24px !important; box-sizing: border-box !important; }
        .report-shell { max-width: 1600px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 18px; }
        
        .erp-filter-card { width: 100%; box-sizing: border-box; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px 24px; box-shadow: 0 4px 16px rgba(15,23,42,.04); display: flex; flex-direction: column; gap: 16px; }
        .erp-filter-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; width: 100%; }
        .erp-filter-title { font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; text-transform: uppercase; }
        .erp-filter-sub { font-size: 13px; color: #64748b; font-weight: 500; margin-top: 3px; }
        .erp-filter-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .erp-filter-form { display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; width: 100%; padding-top: 16px; border-top: 1px solid #f1f5f9; }
        .erp-filter-field { display: flex; flex-direction: column; gap: 5px; }
        .erp-filter-field.is-hidden { display: none !important; }
        .erp-filter-field label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .05em; }
        .erp-filter-field select, .erp-filter-field input { height: 40px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0 12px; background: #f8fafc; font-size: 13px; font-weight: 600; color: #1e293b; outline: none; transition: all .15s ease; box-sizing: border-box; }
        .erp-filter-field select:focus, .erp-filter-field input:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
        .btn-filter-apply { height: 40px; padding: 0 20px; border-radius: 10px; background: linear-gradient(135deg,#2563eb,#1d4ed8); color: #fff; font-weight: 700; font-size: 13px; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,.25); transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; box-sizing: border-box; }
        .btn-filter-apply:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(37,99,235,.35); color: #fff; }
        .btn-filter-secondary { height: 40px; padding: 0 16px; border-radius: 10px; background: #ffffff; color: #334155; font-weight: 700; font-size: 13px; border: 1px solid #cbd5e1; cursor: pointer; transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; white-space: nowrap; box-sizing: border-box; }
        .btn-filter-secondary:hover { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }

        .report-tabs { display: flex; justify-content: flex-start; align-items: center; gap: 8px; flex-wrap: wrap; }
        .report-tab-btn { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 700; color: #334155; text-decoration: none; transition: all .15s ease; box-shadow: 0 1px 2px rgba(0,0,0,.03); }
        .report-tab-btn:hover { background: #f8fafc; border-color: #94a3b8; }
        .report-tab-btn.active { background: #00A9A6; color: #fff; border-color: #00A9A6; box-shadow: 0 4px 12px rgba(0,169,166,.28); }
        .erp-prod-table th { text-align: center; white-space: nowrap; font-size: 11px; vertical-align: middle; }
        .erp-prod-table td { vertical-align: middle; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .rate-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-weight: 800; font-size: 11.5px; }
        .rate-badge.green { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .rate-badge.yellow { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .rate-badge.red { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        @media (max-width: 900px) {
            .erp-filter-header { flex-direction: column; align-items: stretch; }
            .erp-filter-form { flex-direction: column; align-items: stretch; }
            .erp-filter-field select, .erp-filter-field input, .btn-filter-apply, .btn-filter-secondary { width: 100%; }
        }
    </style>';

    $body .= '<div class="report-shell">';

    // Card Filtros Unificada tipo PANEL DE CONTROL Y PRODUCCIÓN ERP
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">👷 INFORME DE MERMA POR OPERADOR</div>';
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . ' · Desempeño, ranking y trazabilidad analítica de mermas por trabajador</div>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-actions">';
    $body .= '<a class="btn-filter-secondary" href="' . h($excelUrl) . '">📥 Exportar XLS</a>';
    $body .= '<a class="btn-filter-secondary" href="/">← Volver al Dashboard</a>';
    $body .= '</div>';
    $body .= '</div>';

    // Barra de Filtros Form
    $body .= '<form id="op-filter-form" method="get" action="/reports/operator-waste" class="erp-filter-form">';

    // Tipo de filtro
    $body .= '<div class="erp-filter-field">';
    $body .= '<label for="filter_type_op">Tipo de filtro</label>';
    $body .= '<select id="filter_type_op" name="filter_type">';
    $body .= '<option value="period"' . ($defaultFilterType === 'period' ? ' selected' : '') . '>Período (26 al 25)</option>';
    $body .= '<option value="range"' . ($defaultFilterType === 'range' ? ' selected' : '') . '>Rango personalizado</option>';
    $body .= '</select>';
    $body .= '</div>';

    // Periodo
    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'period' ? '' : ' is-hidden') . '" data-filter-group="period">';
    $body .= '<label for="period_op">Mes del período</label>';
    $body .= '<input id="period_op" type="month" name="period" value="' . h($periodYm) . '"' . ($defaultFilterType === 'period' ? '' : ' disabled') . '>';
    $body .= '</div>';

    // Rango
    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="start_date_op">Fecha inicio</label>';
    $body .= '<input id="start_date_op" type="date" name="start_date" value="' . h($rangeStartInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="end_date_op">Fecha término</label>';
    $body .= '<input id="end_date_op" type="date" name="end_date" value="' . h($rangeEndInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    // Proceso
    $body .= '<div class="erp-filter-field" style="min-width:160px">';
    $body .= '<label for="proc_filter_op">Proceso productivo</label>';
    $body .= '<select id="proc_filter_op" name="process">';
    $body .= '<option value="">Todos los procesos</option>';
    foreach ($processes as $pCode => $pTitle) {
        $body .= '<option value="' . h($pCode) . '"' . ($filterProcess === $pCode ? ' selected' : '') . '>' . h($pTitle) . '</option>';
    }
    $body .= '</select>';
    $body .= '</div>';

    // Operador Selector
    $body .= '<div class="erp-filter-field" style="flex:1;min-width:200px">';
    $body .= '<label for="operator_filter_op">Operador específico</label>';
    $body .= '<select id="operator_filter_op" name="operator_id">';
    $body .= '<option value="">Todos los operadores (' . count($catalog) . ' activos)</option>';
    foreach ($catalog as $catOp) {
        $cId = (int)$catOp['id'];
        $cName = (string)$catOp['name'];
        $cRut = (string)($catOp['rut'] ?? '');
        $label = $cRut !== '' ? "{$cName} ({$cRut})" : $cName;
        $body .= '<option value="' . $cId . '"' . ($filterOperatorId === $cId ? ' selected' : '') . '>' . h($label) . '</option>';
    }
    $body .= '</select>';
    $body .= '</div>';

    // Buscador rápido
    $body .= '<div class="erp-filter-field" style="min-width:180px">';
    $body .= '<label for="search_op">Buscar texto</label>';
    $body .= '<input id="search_op" type="text" name="q" value="' . h((string)$search) . '" placeholder="Nombre, RUT...">';
    $body .= '</div>';

    $hasOpCustomFilters = ($filterProcess !== null || $filterOperatorId !== null || ($search !== null && $search !== ''));

    $body .= '<div style="display:flex;gap:8px;align-items:flex-end">';
    $body .= '<button type="submit" class="btn-filter-apply">🔍 Aplicar Filtros</button>';
    if ($hasOpCustomFilters) {
        $body .= '<a class="btn-filter-secondary" href="/reports/operator-waste" title="Limpiar filtros">✕ Limpiar</a>';
    }
    $body .= '</div>';

    $body .= '</form>';
    $body .= '</div>';

    $body .= '<script>
        (function () {
            var filterType = document.getElementById("filter_type_op");
            var form = document.getElementById("op-filter-form");
            function syncFilterFields() {
                if (!filterType || !form) return;
                var mode = filterType.value === "range" ? "range" : "period";
                form.querySelectorAll("[data-filter-group]").forEach(function (field) {
                    var visible = field.getAttribute("data-filter-group") === mode;
                    field.classList.toggle("is-hidden", !visible);
                    field.querySelectorAll("input, select").forEach(function (input) {
                        if (input === filterType) return;
                        input.disabled = !visible;
                    });
                });
            }
            if (filterType && form) {
                filterType.addEventListener("change", syncFilterFields);
                syncFilterFields();
            }
        })();
    </script>';

    // KPI Cards estándar del ERP
    $totalOps = (int)($summary['total_operators'] ?? count($operators));
    $totWasteU = (float)($summary['total_waste_units'] ?? 0.0);
    $totWasteKg = (float)($summary['total_waste_kg'] ?? 0.0);
    $totProdU = (float)($summary['total_produced_units'] ?? 0.0);
    $globalPct = (float)($summary['global_waste_percent'] ?? 0.0);
    $topOp = (string)($summary['top_operator'] ?? 'N/D');

    $body .= '<div class="card" style="border-radius:18px;border:1px solid #e2e8f0;padding:20px 24px;box-shadow:0 4px 16px rgba(15,23,42,.03)">';
    $body .= '<div class="kpi-grid" style="grid-template-columns:repeat(5,minmax(0,1fr))">';

    $body .= '<div class="kpi-card">';
    $body .= '<div class="kpi-label">Operadores Evaluados</div>';
    $body .= '<div class="kpi-value">' . $totalOps . '</div>';
    $body .= '<div class="kpi-sub">Trabajadores en rango</div>';
    $body .= '</div>';

    $body .= '<div class="kpi-card">';
    $body .= '<div class="kpi-label">Merma Total Unidades</div>';
    $body .= '<div class="kpi-value" style="color:#dc2626">' . h($fmtUnits($totWasteU)) . '</div>';
    $body .= '<div class="kpi-sub">Unidades descartadas</div>';
    $body .= '</div>';

    $body .= '<div class="kpi-card">';
    $body .= '<div class="kpi-label">Merma Total Kilos</div>';
    $body .= '<div class="kpi-value">' . h($fmtDec($totWasteKg)) . ' <span style="font-size:14px;font-weight:600">kg</span></div>';
    $body .= '<div class="kpi-sub">Peso total reportado</div>';
    $body .= '</div>';

    $body .= '<div class="kpi-card">';
    $body .= '<div class="kpi-label">% Merma General Planta</div>';
    $body .= '<div class="kpi-value" style="color:#00A9A6">' . h($fmtDec($globalPct)) . '%</div>';
    $body .= '<div class="kpi-sub">Base prod: ' . h($fmtUnits($totProdU)) . ' unid</div>';
    $body .= '</div>';

    $body .= '<div class="kpi-card">';
    $body .= '<div class="kpi-label">Mayor Incidencia</div>';
    $body .= '<div class="kpi-value" style="font-size:15px;line-height:1.2;margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="' . h($topOp) . '">' . h($topOp) . '</div>';
    $body .= '<div class="kpi-sub">Mayor merma en kilos</div>';
    $body .= '</div>';

    $body .= '</div>';
    $body .= '</div>'; // Fin card 1

    // Card 2: Tabs de Procesos
    $body .= '<div class="card" style="padding:10px 14px">';
    $body .= '<div class="report-tabs" style="margin:0">';
    $body .= '<a class="report-tab-btn' . ($filterProcess === null ? ' active' : '') . '" href="' . h($buildUrl(['process' => null])) . '">Todos los Procesos</a>';
    $body .= '<a class="report-tab-btn' . ($filterProcess === 'impresion' ? ' active' : '') . '" href="' . h($buildUrl(['process' => 'impresion'])) . '">Impresión</a>';
    $body .= '<a class="report-tab-btn' . ($filterProcess === 'corte_sellado' ? ' active' : '') . '" href="' . h($buildUrl(['process' => 'corte_sellado'])) . '">Corte y Sellado</a>';
    $body .= '<a class="report-tab-btn' . ($filterProcess === 'embalaje' ? ' active' : '') . '" href="' . h($buildUrl(['process' => 'embalaje'])) . '">Embalaje</a>';
    $body .= '<a class="report-tab-btn' . ($filterProcess === 'pulpo' ? ' active' : '') . '" href="' . h($buildUrl(['process' => 'pulpo'])) . '">Pulpo Serigráfico</a>';
    $body .= '</div>';
    $body .= '</div>'; // Fin card 2

    // Card 3: Tabla Principal
    $body .= '<div class="card">';
    $body .= '<div class="erp-prod-table-wrap">';
    $body .= '<table class="erp-prod-table table-compact">';
    $body .= '<thead><tr>';
    $body .= '<th style="width:40px;text-align:center">#</th>';
    $body .= '<th style="text-align:left">Operador</th>';
    $body .= '<th style="text-align:center">RUT</th>';
    $body .= '<th style="text-align:center">Proceso Principal</th>';
    $body .= '<th style="text-align:center">OTs</th>';
    $body .= '<th style="text-align:right">Producción (unid)</th>';
    $body .= '<th style="text-align:right">Merma (unid)</th>';
    $body .= '<th style="text-align:right">Merma (kg)</th>';
    $body .= '<th style="text-align:center">% Merma</th>';
    $body .= '<th style="text-align:right">Participación</th>';
    $body .= '<th style="text-align:left">Mayor Causa</th>';
    $body .= '<th style="text-align:center;width:110px">Acción</th>';
    $body .= '</tr></thead><tbody>';

    if (empty($operators)) {
        $body .= '<tr><td colspan="12" class="text-center" style="padding:40px; color:#64748b; font-size:13px;">No se encontraron registros de merma para los filtros seleccionados.</td></tr>';
    } else {
        $rank = 0;
        foreach ($operators as $idx => $op) {
            $rank++;
            $opId = (int)$op['operator_id'];
            $opName = (string)$op['operator_name'];
            $opRut = (string)$op['operator_rut'];
            $mainProc = (string)$op['main_process'];
            $otCount = (int)$op['ot_count'];
            $prodU = (float)$op['produced_units'];
            $wUnits = (float)$op['waste_units'];
            $wKg = (float)$op['waste_kg'];
            $wPct = (float)$op['waste_percent'];
            $sharePct = (float)$op['share_percent'];
            $topDefect = (string)$op['top_defect_type'];
            $otsList = is_array($op['ots'] ?? null) ? $op['ots'] : [];

            $rateClass = $wPct > 5.0 ? 'red' : ($wPct > 2.0 ? 'yellow' : 'green');
            $opJsonData = htmlspecialchars(json_encode($otsList, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');

            $body .= '<tr>';
            $body .= '<td class="text-center" style="color:#64748b;font-weight:700">' . $rank . '</td>';
            $body .= '<td style="font-weight:700;color:#0f172a">' . h($opName) . '</td>';
            $body .= '<td class="text-center" style="font-family:monospace;color:#475569;font-size:11.5px">' . h($opRut ?: '—') . '</td>';
            $body .= '<td class="text-center"><span style="display:inline-block;padding:2px 8px;border-radius:4px;background:#f1f5f9;font-weight:600;font-size:11px;color:#334155">' . h($mainProc) . '</span></td>';
            $body .= '<td class="text-center" style="font-weight:700;color:#0284c7">' . $otCount . '</td>';
            $body .= '<td class="text-right" style="font-weight:600">' . ($prodU > 0 ? h($fmtUnits($prodU)) : '—') . '</td>';
            $body .= '<td class="text-right" style="font-weight:800;color:#dc2626">' . h($fmtUnits($wUnits)) . '</td>';
            $body .= '<td class="text-right" style="font-weight:700">' . h($fmtDec($wKg)) . ' kg</td>';
            $body .= '<td class="text-center"><span class="rate-badge ' . $rateClass . '">' . h($fmtDec($wPct)) . '%</span></td>';
            $body .= '<td class="text-right" style="font-weight:700;color:#0f172a">' . number_format($sharePct, 1, ',', '.') . '%</td>';
            $body .= '<td style="font-size:11.5px;color:#475569">' . h($topDefect) . '</td>';
            $body .= '<td class="text-center">';
            $body .= '<button type="button" class="btn secondary" style="padding:4px 10px;font-size:11.5px;font-weight:700" onclick="unibagOpenOperatorModal(\'' . h(addslashes($opName)) . '\', ' . $opJsonData . ')">Ver OTs (' . count($otsList) . ')</button>';
            $body .= '</td>';
            $body .= '</tr>';
        }
    }

    $body .= '</tbody></table>';
    $body .= '</div>';
    $body .= '</div>'; // Fin card 3

    // Modal de Detalle de OTs por Operador con estilos estándar del ERP
    $body .= '<div class="modal-backdrop" id="operator-ot-modal">';
    $body .= '<div class="modal-card" style="width:min(1100px,calc(100vw - 24px));max-height:85vh">';
    $body .= '<div class="modal-head">';
    $body .= '<div>';
    $body .= '<div class="modal-title" id="modal-op-title">Detalle de Merma por Operador</div>';
    $body .= '<div class="erp-prod-muted" id="modal-op-count" style="margin-top:2px">0 eventos de merma registrados</div>';
    $body .= '</div>';
    $body .= '<button type="button" class="btn secondary" style="padding:4px 10px;font-size:12px" onclick="unibagCloseOperatorModal()">✕</button>';
    $body .= '</div>';

    $body .= '<div class="modal-body">';
    $body .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:10px;flex-wrap:wrap">';
    $body .= '<div class="erp-prod-muted">Detalle por evento registrado en las órdenes de trabajo</div>';
    $body .= '<input type="text" id="modal-op-search" placeholder="Filtrar por OT, CC, máquina..." style="max-width:280px;height:34px;padding:4px 10px;font-size:12px" oninput="unibagFilterModalOts(this.value)">';
    $body .= '</div>';

    $body .= '<div class="erp-prod-table-wrap">';
    $body .= '<table class="erp-prod-table table-compact" id="modal-op-table">';
    $body .= '<thead><tr>';
    $body .= '<th style="text-align:center">N° OT</th>';
    $body .= '<th style="text-align:center">Centro Costo</th>';
    $body .= '<th style="text-align:left">Máquina</th>';
    $body .= '<th style="text-align:center">Proceso</th>';
    $body .= '<th style="text-align:left">Tipo Defecto</th>';
    $body .= '<th style="text-align:right">Merma (unid)</th>';
    $body .= '<th style="text-align:right">Merma (kg)</th>';
    $body .= '<th style="text-align:center">Fecha y Hora</th>';
    $body .= '</tr></thead>';
    $body .= '<tbody id="modal-op-tbody"></tbody>';
    $body .= '</table></div>';
    $body .= '</div>'; // Fin modal-body
    $body .= '</div>'; // Fin modal-card
    $body .= '</div>'; // Fin modal-backdrop

    $body .= '<script>
        var currentModalOts = [];
        function unibagOpenOperatorModal(operatorName, ots) {
            currentModalOts = ots || [];
            var titleEl = document.getElementById("modal-op-title");
            var countEl = document.getElementById("modal-op-count");
            if (titleEl) titleEl.innerText = "Detalle de Merma — " + operatorName;
            if (countEl) countEl.innerText = currentModalOts.length + " eventos de merma registrados";
            var searchInput = document.getElementById("modal-op-search");
            if (searchInput) searchInput.value = "";
            renderModalRows(currentModalOts);
            var modal = document.getElementById("operator-ot-modal");
            if (modal) {
                modal.classList.add("open");
                modal.style.display = "flex";
            }
        }
        function unibagCloseOperatorModal() {
            var modal = document.getElementById("operator-ot-modal");
            if (modal) {
                modal.classList.remove("open");
                modal.style.display = "none";
            }
        }
        function renderModalRows(list) {
            var tbody = document.getElementById("modal-op-tbody");
            if (!tbody) return;
            if (!list || list.length === 0) {
                tbody.innerHTML = "<tr><td colspan=\"8\" class=\"text-center\" style=\"padding:24px; color:#64748b;\">Sin registros de merma para este operador en el período.</td></tr>";
                return;
            }
            var html = "";
            for (var i = 0; i < list.length; i++) {
                var item = list[i];
                html += "<tr>";
                html += "<td class=\"text-center\" style=\"color:#2563eb; font-weight:800;\">" + (item.ot_number || "—") + "</td>";
                html += "<td class=\"text-center\" style=\"color:#475569;\">" + (item.cost_center || "—") + "</td>";
                html += "<td style=\"font-weight:600;\">" + (item.machine_label || "—") + "</td>";
                html += "<td class=\"text-center\"><span class=\"badge\" style=\"background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:11px;\">" + (item.process_title || "—") + "</span></td>";
                html += "<td style=\"font-weight:600; color:#b91c1c;\">" + (item.merma_title || "Merma") + "</td>";
                html += "<td class=\"text-right\" style=\"color:#dc2626; font-weight:800;\">" + Number(item.units || 0).toLocaleString("es-CL") + "</td>";
                html += "<td class=\"text-right\" style=\"font-weight:700;\">" + Number(item.kg || 0).toLocaleString("es-CL", {minimumFractionDigits:2, maximumFractionDigits:2}) + " kg</td>";
                html += "<td class=\"text-center\" style=\"color:#64748b; font-size:11px;\">" + (item.datetime || "—") + "</td>";
                html += "</tr>";
            }
            tbody.innerHTML = html;
        }
        function unibagFilterModalOts(q) {
            if (!q) {
                renderModalRows(currentModalOts);
                return;
            }
            q = q.toLowerCase();
            var filtered = currentModalOts.filter(function(it) {
                var ot = (it.ot_number || "").toLowerCase();
                var cc = (it.cost_center || "").toLowerCase();
                var m = (it.machine_label || "").toLowerCase();
                var t = (it.merma_title || "").toLowerCase();
                return ot.indexOf(q) !== -1 || cc.indexOf(q) !== -1 || m.indexOf(q) !== -1 || t.indexOf(q) !== -1;
            });
            renderModalRows(filtered);
        }
        var modalBackdrop = document.getElementById("operator-ot-modal");
        if (modalBackdrop) {
            modalBackdrop.addEventListener("click", function(e) {
                if (e.target === modalBackdrop) unibagCloseOperatorModal();
            });
        }
        window.addEventListener("keydown", function(e) {
            if (e.key === "Escape") unibagCloseOperatorModal();
        });
    </script>';

    $body .= '</div>'; // End report-shell

    render('Informe de Merma por Operador · ERP', $body);
}

/**
 * Exporta a Excel (.xlsx streaming) el informe consolidado y analítico de merma por operador.
 */
function unibagOutputOperatorWasteExcel(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $filters = unibagResolveProductionDashboardFilters();
    $start = $filters['start'];
    $end = $filters['end'];
    $activeFilterLabel = (string)$filters['active_filter_label'];

    $filterOperatorId = isset($_GET['operator_id']) && is_numeric($_GET['operator_id']) ? (int)$_GET['operator_id'] : null;
    $filterProcess = isset($_GET['process']) ? strtolower(trim((string)$_GET['process'])) : null;
    if ($filterProcess === '' || $filterProcess === 'all') {
        $filterProcess = null;
    }
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    $reportData = $service->getErpOperatorWasteReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $filterOperatorId,
        $filterProcess
    );

    $summary = $reportData['summary'];
    $operators = $reportData['operators'];

    if ($search !== null && $search !== '') {
        $searchLower = mb_strtolower($search, 'UTF-8');
        $operators = array_values(array_filter($operators, static function (array $op) use ($searchLower): bool {
            $name = mb_strtolower((string)($op['operator_name'] ?? ''), 'UTF-8');
            $rut = mb_strtolower((string)($op['operator_rut'] ?? ''), 'UTF-8');
            $proc = mb_strtolower((string)($op['main_process'] ?? ''), 'UTF-8');
            return str_contains($name, $searchLower) || str_contains($rut, $searchLower) || str_contains($proc, $searchLower);
        }));
    }

    $filename = 'informe-merma-operadores-' . date('Ymd-His') . '.xlsx';
    ob_start();

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Merma por Operador</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>';
    echo 'body{font-family:Calibri,Arial,sans-serif;font-size:11pt;color:#1e293b;background:#ffffff}';
    echo '.report-header{margin-bottom:16px}';
    echo '.report-title{font-size:16pt;font-weight:bold;color:#0f172a}';
    echo '.report-meta{font-size:10pt;color:#475569;margin-top:4px}';
    echo '.section-title{font-size:12pt;font-weight:bold;color:#0f172a;background:#f1f5f9;padding:6px 10px;border-left:4px solid #0284c7;margin:18px 0 8px 0}';
    echo 'table{border-collapse:collapse;width:100%;margin-bottom:18px}';
    echo 'th{background:#0f172a;color:#ffffff;font-weight:bold;font-size:10pt;padding:8px 10px;border:1px solid #94a3b8;vertical-align:middle}';
    echo 'td{font-size:10pt;padding:6px 10px;border:1px solid #cbd5e1;vertical-align:middle;color:#1e293b}';
    echo '.th-sub{background:#e2e8f0;color:#0f172a;font-weight:bold}';
    echo '.num-int{mso-number-format:"\#\,\#\#0";text-align:right}';
    echo '.num-dec{mso-number-format:"\#\,\#\#0\.00";text-align:right}';
    echo '.text-center{text-align:center}';
    echo '.text-right{text-align:right}';
    echo '.font-bold{font-weight:bold}';
    echo '.highlight-red{color:#dc2626;font-weight:bold}';
    echo '.row-even{background:#f8fafc}';
    echo '</style></head><body>';

    echo '<div class="report-header">';
    echo '<div class="report-title">Control de Calidad — Informe Consolidado de Merma por Operador</div>';
    echo '<div class="report-meta"><strong>Filtro aplicado:</strong> ' . h($activeFilterLabel) . ' | <strong>Generado el:</strong> ' . date('d/m/Y H:i') . '</div>';
    echo '</div>';

    // 1. Resumen
    echo '<div class="section-title">1. Indicadores Globales de Merma por Operador</div>';
    echo '<table>';
    echo '<tr><th class="th-sub" style="width:340px;text-align:left">Indicador</th><th class="th-sub" style="text-align:right;width:200px">Valor</th></tr>';
    echo '<tr><td>Total Operadores Evaluados</td><td class="num-int font-bold">' . count($operators) . '</td></tr>';
    echo '<tr class="row-even"><td>Total Merma Acumulada (unidades)</td><td class="num-int highlight-red">' . (int)round((float)($summary['total_waste_units'] ?? 0)) . '</td></tr>';
    echo '<tr><td>Total Merma Acumulada (kg)</td><td class="num-dec font-bold">' . round((float)($summary['total_waste_kg'] ?? 0), 2) . ' kg</td></tr>';
    echo '<tr class="row-even"><td>Total Base de Producción (unidades)</td><td class="num-int font-bold">' . (int)round((float)($summary['total_produced_units'] ?? 0)) . '</td></tr>';
    echo '<tr><td>% Merma Global de Planta</td><td class="text-right font-bold">' . number_format((float)($summary['global_waste_percent'] ?? 0), 2, ',', '.') . '%</td></tr>';
    echo '<tr class="row-even"><td>Mayor Incidencia Individual</td><td class="text-right font-bold highlight-red">' . h((string)($summary['top_operator'] ?? 'N/D')) . '</td></tr>';
    echo '</table>';

    // 2. Ranking de Operadores
    echo '<div class="section-title">2. Ranking y Desempeño Consolidado por Operador</div>';
    echo '<table>';
    echo '<thead><tr>';
    echo '<th>#</th><th>Operador</th><th>RUT</th><th>Proceso Principal</th><th style="text-align:center">OTs</th><th style="text-align:right">Base Producción</th><th style="text-align:right">Merma (unid)</th><th style="text-align:right">Merma (kg)</th><th style="text-align:right">% Merma</th><th style="text-align:right">% Participación</th><th>Mayor Causa</th>';
    echo '</tr></thead><tbody>';

    if (empty($operators)) {
        echo '<tr><td colspan="11" class="text-center">Sin registros de merma para los filtros indicados.</td></tr>';
    } else {
        $r = 0;
        foreach ($operators as $op) {
            $r++;
            $rowCls = ($r % 2 === 0) ? ' class="row-even"' : '';
            $prod = (float)($op['produced_units'] ?? 0);
            $wU = (float)($op['waste_units'] ?? 0);
            $wKg = (float)($op['waste_kg'] ?? 0);
            $rate = (float)($op['waste_percent'] ?? 0);
            $share = (float)($op['share_percent'] ?? 0);

            echo '<tr' . $rowCls . '>';
            echo '<td class="text-center">' . $r . '</td>';
            echo '<td class="font-bold">' . h((string)($op['operator_name'] ?? '')) . '</td>';
            echo '<td>' . h((string)($op['operator_rut'] ?? '')) . '</td>';
            echo '<td>' . h((string)($op['main_process'] ?? '')) . '</td>';
            echo '<td class="num-int">' . (int)($op['ot_count'] ?? 0) . '</td>';
            echo '<td class="num-int">' . ($prod > 0 ? (int)round($prod) : 'N/D') . '</td>';
            echo '<td class="num-int highlight-red">' . (int)round($wU) . '</td>';
            echo '<td class="num-dec">' . round($wKg, 2) . ' kg</td>';
            echo '<td class="text-right font-bold' . ($rate > 5.0 ? ' highlight-red' : '') . '">' . number_format($rate, 2, ',', '.') . '%</td>';
            echo '<td class="text-right">' . number_format($share, 1, ',', '.') . '%</td>';
            echo '<td>' . h((string)($op['top_defect_type'] ?? '')) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';

    // 3. Detalle de Eventos por Orden de Trabajo
    echo '<div class="section-title">3. Detalle Exhaustivo de Eventos de Merma por OT y Trabajador</div>';
    echo '<table>';
    echo '<thead><tr>';
    echo '<th>Operador</th><th>OT</th><th>Centro Costo</th><th>Máquina</th><th>Proceso</th><th>Tipo Defecto</th><th style="text-align:right">Merma (unid)</th><th style="text-align:right">Merma (kg)</th><th style="text-align:center">Fecha/Hora</th>';
    echo '</tr></thead><tbody>';

    $hasEvents = false;
    $idxEvent = 0;
    foreach ($operators as $op) {
        $opName = (string)($op['operator_name'] ?? '');
        $ots = is_array($op['ots'] ?? null) ? $op['ots'] : [];
        foreach ($ots as $item) {
            $hasEvents = true;
            $idxEvent++;
            $rowCls = ($idxEvent % 2 === 0) ? ' class="row-even"' : '';
            echo '<tr' . $rowCls . '>';
            echo '<td class="font-bold">' . h($opName) . '</td>';
            echo '<td class="font-bold">' . h((string)($item['ot_number'] ?? '')) . '</td>';
            echo '<td>' . h((string)($item['cost_center'] ?? '')) . '</td>';
            echo '<td>' . h((string)($item['machine_label'] ?? '')) . '</td>';
            echo '<td>' . h((string)($item['process_title'] ?? '')) . '</td>';
            echo '<td style="color:#b91c1c;">' . h((string)($item['merma_title'] ?? '')) . '</td>';
            echo '<td class="num-int highlight-red">' . (int)round((float)($item['units'] ?? 0)) . '</td>';
            echo '<td class="num-dec">' . round((float)($item['kg'] ?? 0), 2) . ' kg</td>';
            echo '<td class="text-center">' . h((string)($item['datetime'] ?? '')) . '</td>';
            echo '</tr>';
        }
    }

    if (!$hasEvents) {
        echo '<tr><td colspan="9" class="text-center">Sin eventos de merma registrados.</td></tr>';
    }

    echo '</tbody></table></body></html>';

    $html = ob_get_clean();
}

// =============================================================================
// RESUMEN DIARIO DE PLANTA (DÍA ANTERIOR) · VISTA EJECUTIVA Y DE CONTROL
// =============================================================================

function unibagRenderDailySummaryPage(ReceptionService $service): void
{
    $perms = sessionAreaPermissions();
    if (!userCanAccessArea('ERP', $perms) && !userCanAccessArea('PRODUCTION', $perms)) {
        redirectResponse(firstAllowedAreaHome($perms));
    }

    $date = trim((string)($_GET['date'] ?? ''));
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d', strtotime('-1 day'));
    }

    $summary = $service->getDailyPlantSummary($date);
    $kpis = $summary['kpis'];
    $yesterdayDate = date('Y-m-d', strtotime('-1 day'));
    $todayDate = date('Y-m-d');
    $excelUrl = '/reports/resumen-diario/excel?date=' . urlencode($date);
    $activeTab = trim((string)($_GET['tab'] ?? 'production'));
    if (!in_array($activeTab, ['production', 'dispatches', 'stops', 'critical', 'attendance'], true)) {
        $activeTab = 'production';
    }

    $fmtInt = static fn(float|int $n): string => number_format((float)$n, 0, ',', '.');
    $fmtDec = static fn(float|int $n, int $d = 2): string => number_format((float)$n, $d, ',', '.');
    $fmtMoney = static fn(float|int $n): string => '$ ' . number_format((float)$n, 0, ',', '.');

    // Estado del badge de merma
    $wastePercent = (float)$kpis['waste_percent'];
    $wasteTheme = $wastePercent > 5.0 ? 'red' : ($wastePercent > 3.0 ? 'amber' : 'emerald');

    $body = '<style>
        main { max-width: 1540px !important; width: 100% !important; margin: 0 auto !important; padding: 18px 24px !important; box-sizing: border-box !important; }
        .daily-shell { display: flex; flex-direction: column; gap: 20px; width: 100%; box-sizing: border-box; }
        
        .daily-head-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px 24px; box-shadow: 0 4px 16px rgba(15,23,42,.04); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .daily-head-left { display: flex; flex-direction: column; gap: 4px; }
        .daily-title-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .daily-head-title { font-size: 22px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; display: flex; align-items: center; gap: 8px; text-transform: uppercase; }
        .daily-date-badge { font-size: 12px; font-weight: 800; padding: 4px 10px; border-radius: 999px; text-transform: uppercase; letter-spacing: .04em; }
        .daily-date-badge.yesterday { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .daily-date-badge.today { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .daily-date-badge.other { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .daily-head-sub { font-size: 13.5px; color: #64748b; font-weight: 600; text-transform: uppercase; }

        .daily-controls { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .daily-date-input-wrap { display: flex; align-items: center; gap: 6px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 4px 10px; height: 38px; box-sizing: border-box; }
        .daily-date-input-wrap input[type="date"] { border: none; background: transparent; font-weight: 700; font-size: 13px; color: #0f172a; outline: none; padding: 0; width: 130px; }
        .btn-date-quick { height: 38px; padding: 0 12px; border-radius: 8px; font-size: 12.5px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 4px; border: 1px solid #cbd5e1; background: #fff; color: #475569; transition: all .15s ease; text-transform: uppercase; }
        .btn-date-quick:hover { background: #f1f5f9; color: #0f172a; border-color: #94a3b8; }
        .btn-date-quick.active { background: #00A9A6; color: #fff; border-color: #00A9A6; box-shadow: 0 2px 6px rgba(0,169,166,.3); }
        .btn-export-excel { height: 38px; padding: 0 14px; border-radius: 8px; font-size: 12.5px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; background: #059669; color: #fff; border: none; box-shadow: 0 2px 6px rgba(5,150,105,.25); transition: all .15s ease; text-transform: uppercase; }
        .btn-export-excel:hover { background: #047857; color: #fff; transform: translateY(-1px); }

        .daily-kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; width: 100%; box-sizing: border-box; }
        .kpi-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px 16px; box-shadow: 0 4px 14px rgba(15,23,42,.03); display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden; min-height: 125px; box-sizing: border-box; }
        .kpi-card::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 3.5px; }
        .kpi-card.teal::before { background: linear-gradient(90deg, #00A9A6, #2dd4bf); }
        .kpi-card.emerald::before { background: linear-gradient(90deg, #10b981, #34d399); }
        .kpi-card.amber::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .kpi-card.red::before { background: linear-gradient(90deg, #ef4444, #f87171); }
        .kpi-card.blue::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
        .kpi-card.indigo::before { background: linear-gradient(90deg, #6366f1, #818cf8); }
        .kpi-card.slate::before { background: linear-gradient(90deg, #64748b, #94a3b8); }

        .kpi-top { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
        .kpi-tag { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
        .kpi-icon { font-size: 18px; line-height: 1; }
        .kpi-val { font-size: 26px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; margin: 4px 0 2px; }
        .kpi-sub { font-size: 11.5px; color: #64748b; font-weight: 600; text-transform: uppercase; }

        .process-distribution-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; width: 100%; box-sizing: border-box; }
        .process-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; box-shadow: 0 2px 8px rgba(15,23,42,.02); display: flex; flex-direction: column; justify-content: space-between; gap: 8px; }
        .process-card-top { display: flex; align-items: center; justify-content: space-between; }
        .process-card-title { font-size: 13.5px; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 6px; text-transform: uppercase; }
        .process-card-count { font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 6px; background: #f1f5f9; color: #475569; }
        .process-card-metric { display: flex; align-items: baseline; justify-content: space-between; }
        .process-card-num { font-size: 20px; font-weight: 800; color: #0f172a; }
        .process-card-waste { font-size: 12px; font-weight: 700; color: #b91c1c; text-transform: uppercase; }

        .daily-tabs-nav { display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; padding-bottom: 2px; margin-top: 6px; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .daily-tab-link { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; font-size: 13.5px; font-weight: 800; text-decoration: none; border-radius: 8px 8px 0 0; color: #64748b; transition: all .15s ease; white-space: nowrap; text-transform: uppercase; }
        .daily-tab-link:hover { color: #0f172a; background: #f1f5f9; }
        .daily-tab-link.active { color: #00A9A6; border-bottom: 3px solid #00A9A6; background: #f0fdfa; margin-bottom: -2px; }
        .daily-tab-pill { font-size: 11px; font-weight: 800; padding: 2px 7px; border-radius: 999px; background: #e2e8f0; color: #334155; }
        .daily-tab-link.active .daily-tab-pill { background: #00A9A6; color: #fff; }

        .daily-content-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px 24px; box-shadow: 0 4px 16px rgba(15,23,42,.03); width: 100%; box-sizing: border-box; }
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 10px; border: 1px solid #e2e8f0; }
        .table-daily { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        .table-daily th { background: #f8fafc; color: #475569; font-weight: 800; text-transform: uppercase; font-size: 11px; letter-spacing: .04em; padding: 10px 12px; border-bottom: 2px solid #e2e8f0; text-align: left; white-space: nowrap; }
        .table-daily td { padding: 9px 12px; border-bottom: 1px solid #f1f5f9; color: #1e293b; vertical-align: middle; }
        .table-daily tbody tr:hover { background: #f8fafc; }
        .table-daily tbody tr:last-child td { border-bottom: none; }
        .table-daily tfoot th, .table-daily tfoot td { background: #f1f5f9; font-weight: 800; border-top: 2px solid #cbd5e1; padding: 10px 12px; }

        .badge-process { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 6px; font-weight: 800; font-size: 11.5px; text-transform: uppercase; }
        .badge-process.sellado { background: #eff6ff; color: #1d4ed8; }
        .badge-process.flexo { background: #f0fdfa; color: #0f766e; }
        .badge-process.seri { background: #fdf4ff; color: #a21caf; }
        .badge-process.pulpo { background: #fff7ed; color: #c2410c; }
        .badge-process.embalaje { background: #f8fafc; color: #334155; border: 1px solid #cbd5e1; }
        .badge-process.rebo { background: #faf5ff; color: #7e22ce; }

        .badge-rate { display: inline-block; padding: 3px 8px; border-radius: 999px; font-weight: 800; font-size: 11px; }
        .badge-rate.good { background: #ecfdf5; color: #047857; }
        .badge-rate.warn { background: #fffbeb; color: #b45309; }
        .badge-rate.bad { background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }

        .search-box-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 14px; }
        .search-input-wrap { display: flex; align-items: center; gap: 8px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 6px 12px; width: min(340px, 100%); }
        .search-input-wrap input { border: none; background: transparent; font-size: 13px; color: #0f172a; outline: none; width: 100%; }

        .alert-summary-banner { background: #fffbeb; border: 1px solid #fde68a; border-left: 5px solid #d97706; border-radius: 12px; padding: 14px 18px; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .alert-summary-title { font-size: 14px; font-weight: 800; color: #92400e; display: flex; align-items: center; gap: 8px; text-transform: uppercase; }

        @media (max-width: 1200px) {
            .daily-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 768px) {
            .daily-kpi-grid { grid-template-columns: 1fr; }
            .daily-head-card { flex-direction: column; align-items: stretch; }
            .daily-controls { flex-direction: column; align-items: stretch; }
            .daily-controls > * { width: 100%; }
        }
    </style>';

    $body .= '<div class="daily-shell">';

    // HEADER Y SELECTOR DE FECHAS
    $dateBadgeLabel = $summary['is_yesterday'] ? 'DÍA ANTERIOR (AYER)' : ($summary['is_today'] ? 'JORNADA EN CURSO (HOY)' : 'FECHA SELECCIONADA');
    $dateBadgeCls = $summary['is_yesterday'] ? 'yesterday' : ($summary['is_today'] ? 'today' : 'other');

    $body .= '<div class="daily-head-card">';
    $body .= '<div class="daily-head-left">';
    $body .= '<div class="daily-title-row">';
    $body .= '<div class="daily-head-title"><span>📋</span> RESUMEN OPERATIVO DE PLANTA</div>';
    $body .= '<span class="daily-date-badge ' . $dateBadgeCls . '">' . h($dateBadgeLabel) . '</span>';
    $body .= '</div>';
    $body .= '<div class="daily-head-sub">' . mb_strtoupper((string)$summary['formatted_date'], 'UTF-8') . ' · CONTROL CONSOLIDADO DE PRODUCCIÓN, DESPACHOS, INCIDENCIAS, PARADAS Y DOTACIÓN.</div>';
    $body .= '</div>';

    $body .= '<div class="daily-controls">';
    $body .= '<form method="get" action="/reports/resumen-diario" style="margin:0; display:flex; gap:6px; flex-wrap:wrap; align-items:center;">';
    $body .= '<input type="hidden" name="tab" value="' . h($activeTab) . '">';
    $body .= '<a class="btn-date-quick" href="/reports/resumen-diario?date=' . urlencode($summary['prev_date']) . '&tab=' . urlencode($activeTab) . '" title="Ir al día anterior">← ANTERIOR</a>';
    $body .= '<div class="daily-date-input-wrap">';
    $body .= '<span style="font-size:14px;">📅</span>';
    $body .= '<input type="date" name="date" value="' . h($date) . '" onchange="this.form.submit()">';
    $body .= '</div>';
    $body .= '<a class="btn-date-quick' . ($date === $yesterdayDate ? ' active' : '') . '" href="/reports/resumen-diario?date=' . urlencode($yesterdayDate) . '&tab=' . urlencode($activeTab) . '">AYER</a>';
    $body .= '<a class="btn-date-quick' . ($date === $todayDate ? ' active' : '') . '" href="/reports/resumen-diario?date=' . urlencode($todayDate) . '&tab=' . urlencode($activeTab) . '">HOY</a>';
    $body .= '<a class="btn-date-quick" href="/reports/resumen-diario?date=' . urlencode($summary['next_date']) . '&tab=' . urlencode($activeTab) . '" title="Ir al día siguiente">SIGUIENTE →</a>';
    $body .= '</form>';
    $body .= '<a class="btn-export-excel" href="' . h($excelUrl) . '"><span>📥</span> EXPORTAR EXCEL</a>';
    $body .= '</div>';
    $body .= '</div>';

    // GRID DE 8 KPIS PRINCIPALES (INCLUYENDO DESPACHOS Y MONTO EN DINERO)
    $totDispatchedUnits = (float)($kpis['total_dispatched_units'] ?? 0);
    $totDispatchedMoney = (float)($kpis['total_dispatched_money'] ?? 0);
    $totDispatchesCount = (int)($kpis['total_dispatches_count'] ?? 0);

    $body .= '<div class="daily-kpi-grid">';
    
    // KPI 1: Producción Total
    $body .= '<div class="kpi-card teal">';
    $body .= '<div class="kpi-top"><span class="kpi-tag">PRODUCCIÓN TOTAL</span><span class="kpi-icon">📦</span></div>';
    $body .= '<div class="kpi-val">' . $fmtInt($kpis['total_produced_units']) . '</div>';
    $body .= '<div class="kpi-sub">' . $kpis['active_machines_count'] . ' MÁQUINAS OPERARON</div>';
    $body .= '</div>';

    // KPI 2: Merma Total y %
    $body .= '<div class="kpi-card ' . $wasteTheme . '">';
    $body .= '<div class="kpi-top"><span class="kpi-tag">MERMA DE PLANTA</span><span class="kpi-icon">⚠️</span></div>';
    $body .= '<div class="kpi-val">' . $fmtDec($kpis['waste_percent']) . '%</div>';
    $body .= '<div class="kpi-sub">' . $fmtInt($kpis['total_waste_units']) . ' UNID (' . $fmtDec($kpis['total_waste_kg'], 1) . ' KG)</div>';
    $body .= '</div>';

    // KPI 3: Despachos Realizados (Unidades)
    $body .= '<div class="kpi-card blue">';
    $body .= '<div class="kpi-top"><span class="kpi-tag">DESPACHOS REALIZADOS</span><span class="kpi-icon">🚚</span></div>';
    $body .= '<div class="kpi-val">' . $fmtInt($totDispatchedUnits) . '</div>';
    $body .= '<div class="kpi-sub">' . $totDispatchesCount . ' DOCUMENTOS COMERCIALES</div>';
    $body .= '</div>';

    // KPI 4: Monto Total Despachado en Dinero (CLP)
    $body .= '<div class="kpi-card emerald">';
    $body .= '<div class="kpi-top"><span class="kpi-tag">MONTO DESPACHADO (CLP)</span><span class="kpi-icon">💰</span></div>';
    $body .= '<div class="kpi-val" style="color:#059669; font-size:23px;">' . $fmtMoney($totDispatchedMoney) . '</div>';
    $body .= '<div class="kpi-sub">VALORIZACIÓN SALIDAS DE BODEGA</div>';
    $body .= '</div>';

    // KPI 5: Máquinas Activas
    $body .= '<div class="kpi-card indigo">';
    $body .= '<div class="kpi-top"><span class="kpi-tag">MÁQUINAS ACTIVAS</span><span class="kpi-icon">🏭</span></div>';
    $body .= '<div class="kpi-val">' . $kpis['active_machines_count'] . '</div>';
    $body .= '<div class="kpi-sub">' . $kpis['total_ots_count'] . ' OTS PROCESADAS</div>';
    $body .= '</div>';

    // KPI 6: Dotación en Turno
    $body .= '<div class="kpi-card slate">';
    $body .= '<div class="kpi-top"><span class="kpi-tag">DOTACIÓN PLANTA</span><span class="kpi-icon">👷</span></div>';
    $body .= '<div class="kpi-val">' . $kpis['active_operators_count'] . '</div>';
    $body .= '<div class="kpi-sub">COLACIONES: ' . $kpis['lunch_compliance_rate'] . '% CUMPLIMIENTO</div>';
    $body .= '</div>';

    // KPI 7: Horas Efectivas de Producción
    $body .= '<div class="kpi-card teal">';
    $body .= '<div class="kpi-top"><span class="kpi-tag">HORAS OPERACIÓN</span><span class="kpi-icon">⏱️</span></div>';
    $body .= '<div class="kpi-val">' . $fmtDec($kpis['total_prod_hours'], 1) . ' <span style="font-size:15px; font-weight:600;">HRS</span></div>';
    $body .= '<div class="kpi-sub">TIEMPO EFECTIVO DE MÁQUINA</div>';
    $body .= '</div>';

    // KPI 8: Paradas y Cambios de Formato
    $body .= '<div class="kpi-card amber">';
    $body .= '<div class="kpi-top"><span class="kpi-tag">PARADAS & CAMBIOS</span><span class="kpi-icon">🛑</span></div>';
    $body .= '<div class="kpi-val">' . $fmtDec($kpis['total_stops_hours'] + $kpis['total_setups_hours'], 1) . ' <span style="font-size:15px; font-weight:600;">HRS</span></div>';
    $body .= '<div class="kpi-sub">' . count($summary['stops_rows']) . ' EVENTOS REGISTRADOS</div>';
    $body .= '</div>';

    $body .= '</div>';

    // DISTRIBUCIÓN POR LÍNEAS OPERATIVAS (MINI CARDS)
    if (!empty($summary['by_process'])) {
        $body .= '<div class="process-distribution-grid">';
        foreach ($summary['by_process'] as $p) {
            $pTot = $p['produced'] + $p['waste'];
            $pRate = $pTot > 0 ? round(($p['waste'] / $pTot) * 100, 2) : 0.0;
            $body .= '<div class="process-card">';
            $body .= '<div class="process-card-top">';
            $body .= '<div class="process-card-title"><span>' . h($p['icon']) . '</span> ' . mb_strtoupper((string)$p['title'], 'UTF-8') . '</div>';
            $body .= '<span class="process-card-count">' . $p['ots_count'] . ' OT' . ($p['ots_count'] !== 1 ? 'S' : '') . '</span>';
            $body .= '</div>';
            $body .= '<div class="process-card-metric">';
            $body .= '<div class="process-card-num">' . $fmtInt($p['produced']) . ' <span style="font-size:11.5px; font-weight:600; color:#64748b;">UNID</span></div>';
            $body .= '<div class="process-card-waste">' . ($p['waste'] > 0 ? ('MERMA: ' . $fmtDec($pRate) . '%') : '<span style="color:#059669">0% MERMA</span>') . '</div>';
            $body .= '</div>';
            $body .= '</div>';
        }
        $body .= '</div>';
    }

    // PESTAÑAS DE NAVEGACIÓN
    $tabUrl = static fn(string $t): string => '/reports/resumen-diario?date=' . urlencode($date) . '&tab=' . urlencode($t);
    $otsCount = count($summary['work_orders']);
    $dispRowsCount = count($summary['dispatches_rows'] ?? []);
    $stopsCount = count($summary['stops_rows']);
    $critCount = count($summary['critical_waste_orders']);
    $attCount = $kpis['active_operators_count'];

    $body .= '<div class="daily-tabs-nav">';
    $body .= '<a class="daily-tab-link' . ($activeTab === 'production' ? ' active' : '') . '" href="' . h($tabUrl('production')) . '"><span>🏭</span> PRODUCCIÓN POR MÁQUINA Y OT <span class="daily-tab-pill">' . $otsCount . '</span></a>';
    $body .= '<a class="daily-tab-link' . ($activeTab === 'dispatches' ? ' active' : '') . '" href="' . h($tabUrl('dispatches')) . '"><span>🚚</span> DESPACHOS REALIZADOS <span class="daily-tab-pill">' . $dispRowsCount . '</span></a>';
    $body .= '<a class="daily-tab-link' . ($activeTab === 'stops' ? ' active' : '') . '" href="' . h($tabUrl('stops')) . '"><span>🛑</span> PARADAS, NOVEDADES Y MONTAJES <span class="daily-tab-pill">' . $stopsCount . '</span></a>';
    $body .= '<a class="daily-tab-link' . ($activeTab === 'critical' ? ' active' : '') . '" href="' . h($tabUrl('critical')) . '"><span>⚠️</span> MERMAS CRÍTICAS (>5%) <span class="daily-tab-pill">' . $critCount . '</span></a>';
    $body .= '<a class="daily-tab-link' . ($activeTab === 'attendance' ? ' active' : '') . '" href="' . h($tabUrl('attendance')) . '"><span>👥</span> DOTACIÓN Y COLACIONES <span class="daily-tab-pill">' . $attCount . '</span></a>';
    $body .= '</div>';

    // CONTENIDO DE LA PESTAÑA SELECCIONADA
    $body .= '<div class="daily-content-card">';

    // -------------------------------------------------------------------------
    // TAB 1: PRODUCCIÓN POR MÁQUINA Y OT
    // -------------------------------------------------------------------------
    if ($activeTab === 'production') {
        $body .= '<div class="search-box-row">';
        $body .= '<div style="font-size:16px; font-weight:800; color:#0f172a; text-transform:uppercase;">DETALLE DE ÓRDENES DE TRABAJO EJECUTADAS EN LA JORNADA</div>';
        $body .= '<div class="search-input-wrap">';
        $body .= '<span>🔍</span>';
        $body .= '<input type="text" id="filter-production-table" placeholder="Buscar por OT, Cliente, Máquina u Operario..." onkeyup="filterDailyTable()">';
        $body .= '</div>';
        $body .= '</div>';

        if (empty($summary['work_orders'])) {
            $body .= '<div style="text-align:center; padding:48px 16px; color:#64748b; font-weight:700; text-transform:uppercase;">NO SE REGISTRARON ÓRDENES DE TRABAJO NI PRODUCCIÓN EFECTIVA PARA EL DÍA ' . h($date) . '.</div>';
        } else {
            $body .= '<div class="table-responsive">';
            $body .= '<table class="table-daily" id="daily-production-table">';
            $body .= '<thead><tr>';
            $body .= '<th>PROCESO</th>';
            $body .= '<th>MÁQUINA</th>';
            $body .= '<th class="text-center">N° OT</th>';
            $body .= '<th class="text-center">CC</th>';
            $body .= '<th>CLIENTE</th>';
            $body .= '<th>PRODUCTO / DISEÑO</th>';
            $body .= '<th>OPERADOR</th>';
            $body .= '<th style="text-align:right;">U. BUENAS</th>';
            $body .= '<th style="text-align:right;">U. MERMA</th>';
            $body .= '<th style="text-align:right;">KG MERMA</th>';
            $body .= '<th class="text-center">% MERMA</th>';
            $body .= '<th class="text-center">DURACIÓN</th>';
            $body .= '<th class="text-center">ESTADO</th>';
            $body .= '</tr></thead><tbody>';

            foreach ($summary['work_orders'] as $row) {
                $pCode = $row['process_code'];
                $wRate = (float)$row['waste_rate'];
                $rateCls = $wRate > 5.0 ? 'bad' : ($wRate > 3.0 ? 'warn' : 'good');
                $statusBadge = (int)$row['wok_status'] === 2 ? '<span style="color:#059669; font-weight:800; text-transform:uppercase;">FINALIZADA</span>' : '<span style="color:#2563eb; font-weight:800; text-transform:uppercase;">EN CURSO</span>';

                $body .= '<tr>';
                $body .= '<td><span class="badge-process ' . h($pCode) . '">' . h($row['process_name']) . '</span></td>';
                $body .= '<td style="font-weight:700; color:#0f172a;">' . h($row['machine_name']) . '</td>';
                $body .= '<td class="text-center" style="font-family:monospace; font-weight:800; color:#0f172a;">' . h($row['ot_number']) . '</td>';
                $body .= '<td class="text-center" style="font-family:monospace; color:#475569;">' . h((string)$row['cc_number']) . '</td>';
                $body .= '<td style="font-weight:600; color:#1e293b; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="' . h($row['customer_name']) . '">' . h($row['customer_name']) . '</td>';
                $body .= '<td style="color:#475569; max-width:240px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="' . h($row['product_name']) . '">' . h($row['product_name']) . '</td>';
                $body .= '<td style="font-weight:600; color:#334155;">' . h($row['operator_name']) . '</td>';
                $body .= '<td style="text-align:right; font-weight:800; color:#0f172a;">' . $fmtInt((float)$row['produced_units']) . '</td>';
                $body .= '<td style="text-align:right; font-weight:700; color:#b91c1c;">' . $fmtInt((float)$row['waste_units']) . '</td>';
                $body .= '<td style="text-align:right; font-weight:600;">' . $fmtDec((float)$row['waste_kg'], 2) . ' kg</td>';
                $body .= '<td class="text-center"><span class="badge-rate ' . $rateCls . '">' . $fmtDec($wRate) . '%</span></td>';
                $body .= '<td class="text-center" style="font-weight:600; color:#64748b;">' . $fmtDec((float)$row['duration_hours'], 1) . ' hrs</td>';
                $body .= '<td class="text-center">' . $statusBadge . '</td>';
                $body .= '</tr>';
            }

            $body .= '</tbody><tfoot><tr>';
            $body .= '<th colspan="7" style="text-align:right; text-transform:uppercase;">TOTALES GENERALES DE LA JORNADA:</th>';
            $body .= '<th style="text-align:right; font-size:13.5px; color:#0f172a;">' . $fmtInt($kpis['total_produced_units']) . '</th>';
            $body .= '<th style="text-align:right; font-size:13.5px; color:#b91c1c;">' . $fmtInt($kpis['total_waste_units']) . '</th>';
            $body .= '<th style="text-align:right; font-size:13.5px;">' . $fmtDec($kpis['total_waste_kg'], 2) . ' kg</th>';
            $body .= '<th class="text-center"><span class="badge-rate ' . ($wastePercent > 5 ? 'bad' : ($wastePercent > 3 ? 'warn' : 'good')) . '">' . $fmtDec($wastePercent) . '%</span></th>';
            $body .= '<th class="text-center">' . $fmtDec($kpis['total_prod_hours'], 1) . ' hrs</th>';
            $body .= '<th></th>';
            $body .= '</tr></tfoot>';
            $body .= '</table></div>';

            $body .= '<script>
                function filterDailyTable() {
                    var input = document.getElementById("filter-production-table");
                    var filter = input.value.toLowerCase();
                    var rows = document.querySelectorAll("#daily-production-table tbody tr");
                    rows.forEach(function(r) {
                        var text = r.textContent.toLowerCase();
                        r.style.display = text.indexOf(filter) > -1 ? "" : "none";
                    });
                }
            </script>';
        }
    }

    // -------------------------------------------------------------------------
    // TAB 2: DESPACHOS REALIZADOS Y VALORIZACIÓN EN DINERO (CLP)
    // -------------------------------------------------------------------------
    elseif ($activeTab === 'dispatches') {
        $body .= '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px 20px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">';
        $body .= '<div>';
        $body .= '<div style="font-size:16px; font-weight:800; color:#0f172a; text-transform:uppercase; letter-spacing:-.01em;">DETALLE DE DESPACHOS REALIZADOS Y VALORIZACIÓN EN DINERO (CLP)</div>';
        $body .= '<div style="font-size:13px; color:#64748b; margin-top:2px;">Consolidado de salidas comerciales, guías de despacho, facturas y montos valorizados en la jornada.</div>';
        $body .= '</div>';
        $body .= '<div style="display:flex; gap:14px; flex-wrap:wrap;">';
        $body .= '<div style="background:#fff; border:1px solid #cbd5e1; border-radius:10px; padding:8px 14px; text-align:right;">';
        $body .= '<div style="font-size:10.5px; font-weight:800; color:#64748b; text-transform:uppercase;">TOTAL VALORIZADO</div>';
        $body .= '<div style="font-size:17px; font-weight:800; color:#059669;">' . $fmtMoney($totDispatchedMoney) . '</div>';
        $body .= '</div>';
        $body .= '<div style="background:#fff; border:1px solid #cbd5e1; border-radius:10px; padding:8px 14px; text-align:right;">';
        $body .= '<div style="font-size:10.5px; font-weight:800; color:#64748b; text-transform:uppercase;">TOTAL DESPACHADO</div>';
        $body .= '<div style="font-size:17px; font-weight:800; color:#0f172a;">' . $fmtInt($totDispatchedUnits) . ' <span style="font-size:12px; color:#64748b;">UNID</span></div>';
        $body .= '</div>';
        $body .= '<div style="background:#fff; border:1px solid #cbd5e1; border-radius:10px; padding:8px 14px; text-align:right;">';
        $body .= '<div style="font-size:10.5px; font-weight:800; color:#64748b; text-transform:uppercase;">DOCUMENTOS</div>';
        $body .= '<div style="font-size:17px; font-weight:800; color:#2563eb;">' . $totDispatchesCount . ' <span style="font-size:12px; color:#64748b;">DESPACHOS</span></div>';
        $body .= '</div>';
        $body .= '</div>';
        $body .= '</div>';

        $body .= '<div class="search-box-row">';
        $body .= '<div style="font-size:14px; font-weight:800; color:#334155; text-transform:uppercase;">LISTADO DETALLADO DE DESPACHOS DEL DÍA</div>';
        $body .= '<div class="search-input-wrap">';
        $body .= '<span>🔍</span>';
        $body .= '<input type="text" id="filter-dispatches-table" placeholder="Buscar por Cliente, Documento, CC, Código o Chofer..." onkeyup="filterDispatchesTable()">';
        $body .= '</div>';
        $body .= '</div>';

        if (empty($summary['dispatches_rows'])) {
            $body .= '<div style="text-align:center; padding:48px 16px; color:#64748b; font-weight:700; text-transform:uppercase;">NO SE REGISTRARON DESPACHOS COMERCIALES PARA EL DÍA ' . h($date) . '.</div>';
        } else {
            $body .= '<div class="table-responsive">';
            $body .= '<table class="table-daily" id="daily-dispatches-table">';
            $body .= '<thead><tr>';
            $body .= '<th class="text-center">TIPO DOC</th>';
            $body .= '<th class="text-center">N° DOCUMENTO</th>';
            $body .= '<th>CLIENTE</th>';
            $body .= '<th class="text-center">CC / NOTA VENTA</th>';
            $body .= '<th>CÓDIGO</th>';
            $body .= '<th>PRODUCTO / DESCRIPCIÓN</th>';
            $body .= '<th style="text-align:right;">CANTIDAD (UNID)</th>';
            $body .= '<th style="text-align:right;">PRECIO UNIT. (CLP)</th>';
            $body .= '<th style="text-align:right;">MONTO TOTAL (CLP)</th>';
            $body .= '<th>TRANSPORTE / CHOFER</th>';
            $body .= '<th class="text-center">HORA SALIDA</th>';
            $body .= '</tr></thead><tbody>';

            foreach ($summary['dispatches_rows'] as $dr) {
                $docType = strtoupper(trim((string)($dr['tipo_documento'] ?? 'DESP')));
                $docBadgeColor = ($docType === 'FA' || str_contains($docType, 'FACT')) ? '#2563eb' : '#0d9488';
                $docBadgeBg = ($docType === 'FA' || str_contains($docType, 'FACT')) ? '#eff6ff' : '#f0fdfa';
                $uPrice = (float)($dr['unit_price'] ?? 0);
                $lMoney = (float)($dr['total_money'] ?? 0);

                $driverInfo = trim((string)($dr['driver_name'] ?? ''));
                $transInfo = trim((string)($dr['transport_company'] ?? ''));
                $plateInfo = trim((string)($dr['vehicle_plate'] ?? ''));
                $transText = $transInfo;
                if ($driverInfo !== '') {
                    $transText .= ($transText !== '' ? ' · ' : '') . $driverInfo;
                }
                if ($plateInfo !== '') {
                    $transText .= " ({$plateInfo})";
                }
                if ($transText === '') {
                    $transText = 'Transporte propio / N/D';
                }

                $body .= '<tr>';
                $body .= '<td class="text-center"><span style="display:inline-block; padding:3px 8px; border-radius:6px; font-weight:800; font-size:11px; background:' . $docBadgeBg . '; color:' . $docBadgeColor . '; border:1px solid ' . $docBadgeColor . '33;">' . h($docType) . '</span></td>';
                $body .= '<td class="text-center" style="font-family:monospace; font-weight:800; color:#0f172a;">' . h((string)$dr['doc_number']) . '</td>';
                $body .= '<td style="font-weight:700; color:#1e293b; max-width:210px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="' . h($dr['customer_name']) . '">' . h($dr['customer_name']) . '</td>';
                $body .= '<td class="text-center" style="font-family:monospace; font-weight:600; color:#475569;">' . h((string)($dr['cc_number'] ?: '—')) . '</td>';
                $body .= '<td style="font-family:monospace; font-weight:700; color:#0f766e;">' . h((string)($dr['item_number_prod'] ?: '—')) . '</td>';
                $body .= '<td style="color:#334155; max-width:230px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="' . h($dr['item_name']) . '">' . h($dr['item_name']) . '</td>';
                $body .= '<td style="text-align:right; font-weight:800; color:#0f172a;">' . $fmtInt((float)$dr['dispatched_units']) . '</td>';
                $body .= '<td style="text-align:right; font-weight:600; color:#64748b;">' . ($uPrice > 0 ? $fmtMoney($uPrice) : '—') . '</td>';
                $body .= '<td style="text-align:right; font-weight:800; color:#059669; font-size:13px;">' . ($lMoney > 0 ? $fmtMoney($lMoney) : '$ 0') . '</td>';
                $body .= '<td style="font-size:12px; color:#475569; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="' . h($transText) . '">' . h($transText) . '</td>';
                $body .= '<td class="text-center" style="font-family:monospace; font-weight:700; color:#475569;">' . h((string)$dr['departure_time']) . '</td>';
                $body .= '</tr>';
            }

            $body .= '</tbody><tfoot><tr>';
            $body .= '<th colspan="6" style="text-align:right; text-transform:uppercase;">TOTALES GENERALES DE DESPACHOS:</th>';
            $body .= '<th style="text-align:right; font-size:13.5px; color:#0f172a;">' . $fmtInt($totDispatchedUnits) . '</th>';
            $body .= '<th></th>';
            $body .= '<th style="text-align:right; font-size:14px; color:#059669; font-weight:800;">' . $fmtMoney($totDispatchedMoney) . '</th>';
            $body .= '<th colspan="2"></th>';
            $body .= '</tr></tfoot>';
            $body .= '</table></div>';

            $body .= '<script>
                function filterDispatchesTable() {
                    var input = document.getElementById("filter-dispatches-table");
                    var filter = input.value.toLowerCase();
                    var rows = document.querySelectorAll("#daily-dispatches-table tbody tr");
                    rows.forEach(function(r) {
                        var text = r.textContent.toLowerCase();
                        r.style.display = text.indexOf(filter) > -1 ? "" : "none";
                    });
                }
            </script>';
        }
    }

    // -------------------------------------------------------------------------
    // TAB 3: PARADAS, NOVEDADES Y MONTAJES
    // -------------------------------------------------------------------------
    elseif ($activeTab === 'stops') {
        $body .= '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">';
        $body .= '<div>';
        $body .= '<div style="font-size:16px; font-weight:800; color:#0f172a; text-transform:uppercase;">REGISTRO CRONOLÓGICO DE DETENCIONES, AJUSTES E INCIDENTES</div>';
        $body .= '<div style="font-size:13px; color:#64748b; margin-top:2px;">Detalle de todas las interrupciones operativas, fallas, montajes y pausas de máquina reportadas.</div>';
        $body .= '</div>';
        $body .= '</div>';

        // Pills de motivos
        if (!empty($summary['stops_by_category'])) {
            $body .= '<div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">';
            foreach ($summary['stops_by_category'] as $reason => $stat) {
                $body .= '<div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:6px 12px; font-size:12px;">';
                $body .= '<strong>' . mb_strtoupper((string)$reason, 'UTF-8') . ':</strong> ' . $stat['count'] . ' eventos · ' . $stat['minutes'] . ' min (' . round($stat['minutes']/60, 1) . ' hrs)';
                $body .= '</div>';
            }
            $body .= '</div>';
        }

        if (empty($summary['stops_rows'])) {
            $body .= '<div style="text-align:center; padding:48px 16px; color:#059669; font-weight:800; text-transform:uppercase;">✅ JORNADA CONTINUA: NO SE REPORTARON PARADAS, FALLAS NI DETENCIONES OPERATIVAS EN EL SISTEMA PARA ESTE DÍA.</div>';
        } else {
            $body .= '<div class="table-responsive">';
            $body .= '<table class="table-daily">';
            $body .= '<thead><tr>';
            $body .= '<th class="text-center">HORA INICIO</th>';
            $body .= '<th class="text-center">HORA FIN</th>';
            $body .= '<th class="text-center">DURACIÓN</th>';
            $body .= '<th>MÁQUINA</th>';
            $body .= '<th>OPERADOR</th>';
            $body .= '<th>MOTIVO / TIPO DE EVENTO</th>';
            $body .= '<th class="text-center">OT ASOCIADA</th>';
            $body .= '<th>OBSERVACIONES / COMENTARIOS REPORTADOS</th>';
            $body .= '</tr></thead><tbody>';

            foreach ($summary['stops_rows'] as $st) {
                $isColacion = stripos((string)$st['stop_reason'], 'colaci') !== false;
                $isApertura = $st['evt_type'] === 'apertura';
                $badgeStyle = $isColacion ? 'background:#ecfdf5; color:#047857; border:1px solid #a7f3d0;' : ($isApertura ? 'background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe;' : 'background:#fff1f2; color:#be123c; border:1px solid #fecdd3;');

                $body .= '<tr>';
                $body .= '<td class="text-center" style="font-family:monospace; font-weight:700; color:#0f172a;">' . h($st['start_time']) . '</td>';
                $body .= '<td class="text-center" style="font-family:monospace; color:#64748b;">' . h($st['end_time'] ?: 'En curso') . '</td>';
                $body .= '<td class="text-center" style="font-weight:800; color:#0f172a;">' . (int)$st['duration_minutes'] . ' min</td>';
                $body .= '<td style="font-weight:700; color:#0f172a;">' . h((string)$st['machine_name']) . '</td>';
                $body .= '<td style="font-weight:600; color:#334155;">' . h((string)$st['operator_name']) . '</td>';
                $body .= '<td><span style="display:inline-block; padding:3px 9px; border-radius:6px; font-weight:800; font-size:11.5px; text-transform:uppercase; ' . $badgeStyle . '">' . h((string)$st['stop_reason']) . '</span></td>';
                $body .= '<td class="text-center" style="font-family:monospace; font-weight:700;">' . h((string)$st['ot_number'] ?: '—') . '</td>';
                $body .= '<td style="color:#475569; font-size:12px;">' . h((string)$st['evt_comments'] ?: 'Sin observaciones') . '</td>';
                $body .= '</tr>';
            }

            $body .= '</tbody></table></div>';
        }
    }

    // -------------------------------------------------------------------------
    // TAB 4: MERMAS CRÍTICAS (>5%)
    // -------------------------------------------------------------------------
    elseif ($activeTab === 'critical') {
        if (empty($summary['critical_waste_orders'])) {
            $body .= '<div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:24px; text-align:center;">';
            $body .= '<div style="font-size:32px; margin-bottom:8px;">🌟</div>';
            $body .= '<div style="font-size:17px; font-weight:800; color:#15803d; text-transform:uppercase;">¡EXCELENTE CONTROL DE CALIDAD!</div>';
            $body .= '<div style="font-size:13.5px; color:#166534; margin-top:4px; text-transform:uppercase;">NINGUNA ORDEN DE TRABAJO SUPERÓ EL UMBRAL CRÍTICO DE MERMA DEL 5.0% DURANTE LA JORNADA DEL ' . h($date) . '.</div>';
            $body .= '</div>';
        } else {
            $body .= '<div class="alert-summary-banner">';
            $body .= '<div class="alert-summary-title">';
            $body .= '<span style="font-size:20px;">⚠️</span>';
            $body .= '<span>ALERTA DE CALIDAD: SE DETECTARON ' . count($summary['critical_waste_orders']) . ' ÓRDENES CON MERMA SUPERIOR AL UMBRAL CRÍTICO DEL 5.0%</span>';
            $body .= '</div>';
            $body .= '<div style="font-size:12.5px; color:#78350f; font-weight:600; text-transform:uppercase;">SE RECOMIENDA AUDITORÍA TÉCNICA Y REVISIÓN CON LOS OPERADORES RESPONSABLES.</div>';
            $body .= '</div>';

            $body .= '<div class="table-responsive">';
            $body .= '<table class="table-daily">';
            $body .= '<thead><tr>';
            $body .= '<th>PROCESO</th>';
            $body .= '<th>MÁQUINA</th>';
            $body .= '<th class="text-center">N° OT</th>';
            $body .= '<th class="text-center">CC</th>';
            $body .= '<th>CLIENTE</th>';
            $body .= '<th>PRODUCTO</th>';
            $body .= '<th>OPERADOR</th>';
            $body .= '<th style="text-align:right;">U. BUENAS</th>';
            $body .= '<th style="text-align:right;">U. MERMA</th>';
            $body .= '<th style="text-align:right;">KG MERMA</th>';
            $body .= '<th class="text-center">% MERMA</th>';
            $body .= '</tr></thead><tbody>';

            foreach ($summary['critical_waste_orders'] as $co) {
                $wRate = (float)$co['waste_rate'];
                $body .= '<tr>';
                $body .= '<td><span class="badge-process ' . h($co['process_code']) . '">' . h($co['process_name']) . '</span></td>';
                $body .= '<td style="font-weight:700;">' . h($co['machine_name']) . '</td>';
                $body .= '<td class="text-center" style="font-family:monospace; font-weight:800; color:#0f172a;">' . h($co['ot_number']) . '</td>';
                $body .= '<td class="text-center" style="font-family:monospace;">' . h((string)$co['cc_number']) . '</td>';
                $body .= '<td style="font-weight:700; color:#1e293b;">' . h($co['customer_name']) . '</td>';
                $body .= '<td style="color:#475569;">' . h($co['product_name']) . '</td>';
                $body .= '<td style="font-weight:600;">' . h($co['operator_name']) . '</td>';
                $body .= '<td style="text-align:right;">' . $fmtInt((float)$co['produced_units']) . '</td>';
                $body .= '<td style="text-align:right; font-weight:800; color:#dc2626;">' . $fmtInt((float)$co['waste_units']) . '</td>';
                $body .= '<td style="text-align:right; font-weight:600;">' . $fmtDec((float)$co['waste_kg'], 2) . ' kg</td>';
                $body .= '<td class="text-center"><span class="badge-rate bad">' . $fmtDec($wRate) . '%</span></td>';
                $body .= '</tr>';
            }

            $body .= '</tbody></table></div>';
        }
    }

    // -------------------------------------------------------------------------
    // TAB 5: DOTACIÓN, ASISTENCIA Y COLACIONES
    // -------------------------------------------------------------------------
    elseif ($activeTab === 'attendance') {
        $att = $summary['attendance'];
        $body .= '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">';
        $body .= '<div>';
        $body .= '<div style="font-size:16px; font-weight:800; color:#0f172a; text-transform:uppercase;">DOTACIÓN DE OPERARIOS Y CUMPLIMIENTO DE COLACIONES</div>';
        $body .= '<div style="font-size:13px; color:#64748b; margin-top:2px;">Control de asistencia en planta, turnos iniciados y horarios de almuerzo registrados.</div>';
        $body .= '</div>';
        $body .= '<div>';
        $body .= '<a class="btn secondary" href="/reports/colaciones?date=' . urlencode($date) . '" style="background:#00A9A6; color:#fff; border:none; font-weight:800; font-size:12.5px; padding:7px 14px; border-radius:8px; text-decoration:none; text-transform:uppercase;">GESTIONAR COLACIONES EN MÓDULO →</a>';
        $body .= '</div>';
        $body .= '</div>';

        if (empty($att['operators'])) {
            $body .= '<div style="text-align:center; padding:48px 16px; color:#64748b; font-weight:700; text-transform:uppercase;">NO SE REGISTRARON TURNOS NI ASIGNACIONES DE OPERARIOS PARA ESTE DÍA.</div>';
        } else {
            $body .= '<div class="table-responsive">';
            $body .= '<table class="table-daily">';
            $body .= '<thead><tr>';
            $body .= '<th>OPERADOR</th>';
            $body .= '<th>MÁQUINA ASIGNADA</th>';
            $body .= '<th class="text-center">INICIO TURNO</th>';
            $body .= '<th class="text-center">FIN TURNO</th>';
            $body .= '<th class="text-center">TURNO ALMUERZO</th>';
            $body .= '<th class="text-center">HORARIO COLACIÓN</th>';
            $body .= '<th class="text-center">DURACIÓN</th>';
            $body .= '<th class="text-center">ESTADO COLACIÓN</th>';
            $body .= '</tr></thead><tbody>';

            foreach ($att['operators'] as $op) {
                $hasLunch = !empty($op['has_lunch']) && !empty($op['lunch_start']);
                $lunchRange = $hasLunch ? ($op['lunch_start'] . ' a ' . ($op['lunch_end'] ?: 'En curso')) : 'Sin registro';
                $lunchStateBadge = $hasLunch 
                    ? '<span style="display:inline-block; padding:3px 9px; border-radius:999px; font-weight:800; font-size:11px; background:#ecfdf5; color:#047857; border:1px solid #a7f3d0;">✓ REGISTRADA</span>'
                    : '<span style="display:inline-block; padding:3px 9px; border-radius:999px; font-weight:800; font-size:11px; background:#fef2f2; color:#b91c1c; border:1px solid #fca5a5;">⚠️ PENDIENTE</span>';

                $body .= '<tr>';
                $body .= '<td style="font-weight:700; color:#0f172a;">' . h($op['operator_name']) . '</td>';
                $body .= '<td style="font-weight:600; color:#475569;">' . h($op['machine_name']) . '</td>';
                $body .= '<td class="text-center" style="font-family:monospace; font-weight:700;">' . h($op['shift_start']) . '</td>';
                $body .= '<td class="text-center" style="font-family:monospace; color:#64748b;">' . h($op['shift_end']) . '</td>';
                $body .= '<td class="text-center" style="font-size:12px; font-weight:600; color:#0f766e;">' . h($op['shift_lunch_window'] ?? '12:00 a 13:15') . '</td>';
                $body .= '<td class="text-center" style="font-family:monospace; font-weight:700; color:#0f172a;">' . h($lunchRange) . '</td>';
                $body .= '<td class="text-center" style="font-weight:700;">' . ((int)($op['duration_minutes'] ?? 0) > 0 ? ((int)$op['duration_minutes'] . ' min') : '—') . '</td>';
                $body .= '<td class="text-center">' . $lunchStateBadge . '</td>';
                $body .= '</tr>';
            }

            $body .= '</tbody></table></div>';
        }
    }

    $body .= '</div>'; // End daily-content-card
    $body .= '</div>'; // End daily-shell

    render('Resumen Diario de Planta · ' . $summary['formatted_date'], $body);
}

function unibagOutputDailySummaryExcel(ReceptionService $service): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $date = trim((string)($_GET['date'] ?? ''));
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d', strtotime('-1 day'));
    }

    $summary = $service->getDailyPlantSummary($date);
    $kpis = $summary['kpis'];
    $filename = 'Resumen_Planta_' . str_replace('-', '', $date);

    $fmtInt = static fn(float|int $n): string => number_format((float)$n, 0, ',', '.');
    $fmtDec = static fn(float|int $n, int $d = 2): string => number_format((float)$n, $d, ',', '.');
    $fmtMoney = static fn(float|int $n): string => '$ ' . number_format((float)$n, 0, ',', '.');

    $totDispUnits = (float)($kpis['total_dispatched_units'] ?? 0);
    $totDispMoney = (float)($kpis['total_dispatched_money'] ?? 0);
    $totDispCount = (int)($kpis['total_dispatches_count'] ?? 0);

    ob_start();
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
    echo '<style>
        body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; color: #111; }
        h1 { font-size: 16pt; color: #008A87; margin-bottom: 4px; text-transform: uppercase; }
        h2 { font-size: 13pt; color: #1e293b; margin-top: 18px; margin-bottom: 6px; text-transform: uppercase; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th { background-color: #008A87; color: #ffffff; font-weight: bold; border: 1px solid #cbd5e1; padding: 6px 10px; text-align: left; text-transform: uppercase; }
        td { border: 1px solid #e2e8f0; padding: 5px 8px; font-size: 10pt; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .bg-gray { background-color: #f8fafc; }
        .bg-total { background-color: #e2e8f0; font-weight: bold; }
    </style></head><body>';

    echo '<h1>UNIBAG · INFORME RESUMEN OPERATIVO DE PLANTA</h1>';
    echo '<p style="color:#64748b; font-weight:bold; text-transform:uppercase;">FECHA DE OPERACIÓN: ' . h(mb_strtoupper((string)$summary['formatted_date'], 'UTF-8')) . ' (' . h($date) . ')</p>';

    // KPIs Ejecutivos
    echo '<h2>1. INDICADORES CLAVE DE RENDIMIENTO (KPIS)</h2>';
    echo '<table>';
    echo '<tr><th>INDICADOR</th><th>VALOR</th><th>DETALLE</th></tr>';
    echo '<tr><td class="font-bold">TOTAL UNIDADES PRODUCIDAS (BUENAS)</td><td class="font-bold text-right">' . $fmtInt($kpis['total_produced_units']) . '</td><td>Total fabricado en planta</td></tr>';
    echo '<tr><td class="font-bold">TOTAL MERMA GENERADA</td><td class="font-bold text-right" style="color:#b91c1c;">' . $fmtInt($kpis['total_waste_units']) . ' UNID (' . $fmtDec($kpis['total_waste_kg'], 2) . ' KG)</td><td>Merma total del día</td></tr>';
    echo '<tr><td class="font-bold">% MERMA GLOBAL</td><td class="font-bold text-right">' . $fmtDec($kpis['waste_percent']) . '%</td><td>Sobre total producido + merma</td></tr>';
    echo '<tr><td class="font-bold">TOTAL DESPACHADO (UNIDADES)</td><td class="font-bold text-right">' . $fmtInt($totDispUnits) . '</td><td>' . $totDispCount . ' documentos de despacho</td></tr>';
    echo '<tr><td class="font-bold">MONTO TOTAL DESPACHADO (CLP)</td><td class="font-bold text-right" style="color:#059669;">' . $fmtMoney($totDispMoney) . '</td><td>Valorización comercial salidas de bodega</td></tr>';
    echo '<tr><td class="font-bold">MÁQUINAS OPERATIVAS</td><td class="text-right">' . $kpis['active_machines_count'] . '</td><td>Equipos con OTs procesadas</td></tr>';
    echo '<tr><td class="font-bold">DOTACIÓN DE OPERARIOS EN TURNO</td><td class="text-right">' . $kpis['active_operators_count'] . '</td><td>Personal activo en jornada</td></tr>';
    echo '<tr><td class="font-bold">HORAS DE PRODUCCIÓN EFECTIVA</td><td class="text-right">' . $fmtDec($kpis['total_prod_hours'], 1) . ' HRS</td><td>Horas máquina productivas</td></tr>';
    echo '<tr><td class="font-bold">HORAS DE PARADAS Y DETENCIONES</td><td class="text-right">' . $fmtDec($kpis['total_stops_hours'], 1) . ' HRS</td><td>Paradas operativas</td></tr>';
    echo '<tr><td class="font-bold">HORAS DE MONTAJE Y CAMBIOS DE FORMATO</td><td class="text-right">' . $fmtDec($kpis['total_setups_hours'], 1) . ' HRS</td><td>Aperturas y preparación</td></tr>';
    echo '<tr><td class="font-bold">CUMPLIMIENTO CONTROL DE COLACIONES</td><td class="text-right">' . $kpis['lunch_compliance_rate'] . '%</td><td>' . $kpis['lunch_missing_count'] . ' faltas / sin registro</td></tr>';
    echo '</table>';

    // Producción Detallada por OT
    echo '<h2>2. DETALLE DE PRODUCCIÓN POR MÁQUINA Y ORDEN DE TRABAJO (OT)</h2>';
    echo '<table>';
    echo '<thead><tr>';
    echo '<th>PROCESO</th><th>MÁQUINA</th><th>N° OT</th><th>CC</th><th>CLIENTE</th><th>PRODUCTO / DISEÑO</th><th>OPERADOR</th><th class="text-right">U. BUENAS</th><th class="text-right">U. MERMA</th><th class="text-right">KG MERMA</th><th class="text-center">% MERMA</th><th class="text-center">HORAS</th><th class="text-center">ESTADO</th>';
    echo '</tr></thead><tbody>';

    if (empty($summary['work_orders'])) {
        echo '<tr><td colspan="13" class="text-center">Sin producción registrada para este día.</td></tr>';
    } else {
        foreach ($summary['work_orders'] as $row) {
            echo '<tr>';
            echo '<td>' . h($row['process_name']) . '</td>';
            echo '<td class="font-bold">' . h($row['machine_name']) . '</td>';
            echo '<td class="text-center font-bold">' . h($row['ot_number']) . '</td>';
            echo '<td class="text-center">' . h((string)$row['cc_number']) . '</td>';
            echo '<td>' . h($row['customer_name']) . '</td>';
            echo '<td>' . h($row['product_name']) . '</td>';
            echo '<td>' . h($row['operator_name']) . '</td>';
            echo '<td class="text-right font-bold">' . $fmtInt((float)$row['produced_units']) . '</td>';
            echo '<td class="text-right" style="color:#b91c1c;">' . $fmtInt((float)$row['waste_units']) . '</td>';
            echo '<td class="text-right">' . $fmtDec((float)$row['waste_kg'], 2) . ' kg</td>';
            echo '<td class="text-center">' . $fmtDec((float)$row['waste_rate']) . '%</td>';
            echo '<td class="text-center">' . $fmtDec((float)$row['duration_hours'], 1) . '</td>';
            echo '<td class="text-center">' . ((int)$row['wok_status'] === 2 ? 'Finalizada' : 'En curso') . '</td>';
            echo '</tr>';
        }
        echo '<tr class="bg-total"><td colspan="7" class="text-right">TOTALES GENERALES DE PRODUCCIÓN:</td><td class="text-right">' . $fmtInt($kpis['total_produced_units']) . '</td><td class="text-right">' . $fmtInt($kpis['total_waste_units']) . '</td><td class="text-right">' . $fmtDec($kpis['total_waste_kg'], 2) . ' kg</td><td class="text-center">' . $fmtDec($kpis['waste_percent']) . '%</td><td class="text-center">' . $fmtDec($kpis['total_prod_hours'], 1) . ' hrs</td><td></td></tr>';
    }
    echo '</tbody></table>';

    // Despachos Realizados y Valorización en Dinero
    echo '<h2>3. DETALLE DE DESPACHOS REALIZADOS Y VALORIZACIÓN EN DINERO (CLP)</h2>';
    echo '<table>';
    echo '<thead><tr>';
    echo '<th class="text-center">TIPO DOC</th><th class="text-center">N° DOCUMENTO</th><th>CLIENTE</th><th class="text-center">CC / NOTA VENTA</th><th>CÓDIGO</th><th>PRODUCTO / DESCRIPCIÓN</th><th class="text-right">CANTIDAD (UNID)</th><th class="text-right">PRECIO UNIT. (CLP)</th><th class="text-right">MONTO TOTAL (CLP)</th><th>TRANSPORTE / CHOFER</th><th class="text-center">HORA SALIDA</th>';
    echo '</tr></thead><tbody>';

    if (empty($summary['dispatches_rows'])) {
        echo '<tr><td colspan="11" class="text-center">Sin despachos comerciales registrados para este día.</td></tr>';
    } else {
        foreach ($summary['dispatches_rows'] as $dr) {
            $uPrice = (float)($dr['unit_price'] ?? 0);
            $lMoney = (float)($dr['total_money'] ?? 0);

            $driverInfo = trim((string)($dr['driver_name'] ?? ''));
            $transInfo = trim((string)($dr['transport_company'] ?? ''));
            $plateInfo = trim((string)($dr['vehicle_plate'] ?? ''));
            $transText = $transInfo;
            if ($driverInfo !== '') {
                $transText .= ($transText !== '' ? ' · ' : '') . $driverInfo;
            }
            if ($plateInfo !== '') {
                $transText .= " ({$plateInfo})";
            }
            if ($transText === '') {
                $transText = 'Transporte propio / N/D';
            }

            echo '<tr>';
            echo '<td class="text-center font-bold">' . h(strtoupper((string)($dr['tipo_documento'] ?? 'DESP'))) . '</td>';
            echo '<td class="text-center font-bold">' . h((string)$dr['doc_number']) . '</td>';
            echo '<td>' . h((string)$dr['customer_name']) . '</td>';
            echo '<td class="text-center">' . h((string)($dr['cc_number'] ?: '—')) . '</td>';
            echo '<td>' . h((string)($dr['item_number_prod'] ?: '—')) . '</td>';
            echo '<td>' . h((string)$dr['item_name']) . '</td>';
            echo '<td class="text-right font-bold">' . $fmtInt((float)$dr['dispatched_units']) . '</td>';
            echo '<td class="text-right">' . ($uPrice > 0 ? $fmtMoney($uPrice) : '—') . '</td>';
            echo '<td class="text-right font-bold" style="color:#059669;">' . ($lMoney > 0 ? $fmtMoney($lMoney) : '$ 0') . '</td>';
            echo '<td>' . h($transText) . '</td>';
            echo '<td class="text-center">' . h((string)$dr['departure_time']) . '</td>';
            echo '</tr>';
        }
        echo '<tr class="bg-total"><td colspan="6" class="text-right">TOTALES GENERALES DE DESPACHOS:</td><td class="text-right">' . $fmtInt($totDispUnits) . '</td><td></td><td class="text-right" style="color:#059669;">' . $fmtMoney($totDispMoney) . '</td><td colspan="2"></td></tr>';
    }
    echo '</tbody></table>';

    // Detenciones e Incidentes
    echo '<h2>4. DETENCIONES, NOVEDADES Y CAMBIOS DE FORMATO</h2>';
    echo '<table>';
    echo '<thead><tr>';
    echo '<th>HORA INICIO</th><th>HORA FIN</th><th class="text-center">DURACIÓN (MIN)</th><th>MÁQUINA</th><th>OPERADOR</th><th>TIPO / MOTIVO</th><th>OT ASOCIADA</th><th>OBSERVACIONES / COMENTARIOS</th>';
    echo '</tr></thead><tbody>';

    if (empty($summary['stops_rows'])) {
        echo '<tr><td colspan="8" class="text-center">No se reportaron detenciones operativas en este día.</td></tr>';
    } else {
        foreach ($summary['stops_rows'] as $st) {
            echo '<tr>';
            echo '<td class="text-center">' . h($st['start_time']) . '</td>';
            echo '<td class="text-center">' . h($st['end_time'] ?: 'En curso') . '</td>';
            echo '<td class="text-center font-bold">' . (int)$st['duration_minutes'] . '</td>';
            echo '<td class="font-bold">' . h((string)$st['machine_name']) . '</td>';
            echo '<td>' . h((string)$st['operator_name']) . '</td>';
            echo '<td>' . h((string)$st['stop_reason']) . '</td>';
            echo '<td class="text-center">' . h((string)$st['ot_number'] ?: '—') . '</td>';
            echo '<td>' . h((string)$st['evt_comments'] ?: 'Sin observaciones') . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';

    // Asistencia y Colaciones
    echo '<h2>5. ASISTENCIA Y CONTROL DE COLACIONES</h2>';
    echo '<table>';
    echo '<thead><tr>';
    echo '<th>OPERADOR</th><th>MÁQUINA ASIGNADA</th><th class="text-center">INICIO TURNO</th><th class="text-center">FIN TURNO</th><th class="text-center">HORARIO COLACIÓN</th><th class="text-center">DURACIÓN</th><th class="text-center">ESTADO COLACIÓN</th>';
    echo '</tr></thead><tbody>';

    if (empty($summary['attendance']['operators'])) {
        echo '<tr><td colspan="7" class="text-center">Sin asistencia registrada para este día.</td></tr>';
    } else {
        foreach ($summary['attendance']['operators'] as $op) {
            $hasLunch = !empty($op['has_lunch']) && !empty($op['lunch_start']);
            $lunchRange = $hasLunch ? ($op['lunch_start'] . ' a ' . ($op['lunch_end'] ?: 'En curso')) : 'Sin registro';
            echo '<tr>';
            echo '<td class="font-bold">' . h($op['operator_name']) . '</td>';
            echo '<td>' . h($op['machine_name']) . '</td>';
            echo '<td class="text-center">' . h($op['shift_start']) . '</td>';
            echo '<td class="text-center">' . h($op['shift_end']) . '</td>';
            echo '<td class="text-center font-bold">' . h($lunchRange) . '</td>';
            echo '<td class="text-center">' . ((int)($op['duration_minutes'] ?? 0) > 0 ? ((int)$op['duration_minutes'] . ' min') : '—') . '</td>';
            echo '<td class="text-center">' . ($hasLunch ? 'REGISTRADA' : 'PENDIENTE') . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';

    echo '</body></html>';

    $html = ob_get_clean();
    SimpleXlsx::streamHtml($filename, $html, 'Resumen Diario Planta');
}

/**
 * ERP reports (/reports/*) and dashboard router.
 *
 * Maps routes to screens or exports:
 * - GET /                            ERP dashboard (area-aware)
 * - GET /reports/graphics            charts
 * - GET /reports/production-dashboard production dashboard
 * - GET /reports/production-dashboard/excel metric card export (metric=...)
 * - GET /reports/operator-waste      informe de merma por operador
 * - GET /reports/operator-waste/excel export informe de merma por operador
 * - GET /reports/occupancy/excel     occupancy export (optional warehouse_filter)
 * - GET /reports/occupancy/{code}/excel occupancy export for a single warehouse
 * - GET /reports/inventory           inventory counts list
 * - GET /reports/inventory/{id}      inventory detail
 * - GET /reports/inventory/{id}/excel inventory detail export
 * - GET /reports/machine-staff       machine staff report
 * - GET /reports/machine-staff/excel machine staff report export
 * - GET /reports/machine-production  machine production report
 * - GET /reports/machine-production/excel machine production report export
 * - GET /reports/colacion            informe de colación
 * - GET /reports/colacion/excel      export informe de colación
 * - GET /reports/detencion           informe de detenciones
 * - GET /reports/detencion/excel     export informe de detenciones
 * - GET /reports/cambio-configuracion informe cambio de configuración
 * - GET /reports/cambio-configuracion/excel export cambio de configuración
 * - GET /reports/nivel-servicio      informe nivel de servicio
 * - GET /reports/nivel-servicio/excel export nivel de servicio
 *
 * @return bool true si manejó la ruta; false si no corresponde a este módulo
 */
function handleErpReportRoutes(string $path, string $method, ReceptionService $service): bool
{
    if ($path === '/' && $method === 'GET') {
        unibagRenderErpDashboardPage($service);
        return true;
    }

    if (($path === '/reports/resumen-diario' || $path === '/dashboard/resumen' || $path === '/reports/daily-summary') && $method === 'GET') {
        unibagRenderDailySummaryPage($service);
        return true;
    }

    if (($path === '/reports/resumen-diario/excel' || $path === '/dashboard/resumen/excel' || $path === '/reports/daily-summary/excel') && $method === 'GET') {
        unibagOutputDailySummaryExcel($service);
        return true;
    }

    if ($path === '/reports/operator-waste' && $method === 'GET') {
        unibagRenderOperatorWasteReportPage($service);
        return true;
    }

    if ($path === '/reports/operator-waste/excel' && $method === 'GET') {
        unibagOutputOperatorWasteExcel($service);
        return true;
    }

    if ($path === '/reports/despachos' && $method === 'GET') {
        unibagRenderDispatchesReportPage($service);
        return true;
    }

    if ($path === '/reports/despachos/excel' && $method === 'GET') {
        unibagOutputDispatchesReportExcel($service);
        return true;
    }

    if ($path === '/reports/graphics' && $method === 'GET') {
        unibagRenderGraphicsPage($service);
        return true;
    }

    if ($path === '/reports/production-dashboard' && $method === 'GET') {
        unibagRenderErpOnlyProductionDashboardPage($service, false);
        return true;
    }

    if ($path === '/reports/production-dashboard/excel' && $method === 'GET') {
        unibagOutputProductionDashboardMetricExcel($service);
        return true;
    }

    if ($path === '/reports/dashboard-waste-detail/excel' && $method === 'GET') {
        unibagOutputDashboardWasteDetailExcel($service);
        return true;
    }

    if ($path === '/reports/critical-waste/excel' && $method === 'GET') {
        unibagOutputCriticalWasteExcel($service);
        return true;
    }

    if ($path === '/reports/occupancy/excel' && $method === 'GET') {
        unibagOutputWarehouseOccupancyExcel($service);
        return true;
    }

    if (preg_match('#^/reports/occupancy/(.+?)/excel$#', $path, $matches) === 1 && $method === 'GET') {
        $_GET['warehouse_filter'] = (string)$matches[1];
        unibagOutputWarehouseOccupancyExcel($service);
        return true;
    }

    if ($path === '/reports/machine-staff' && $method === 'GET') {
        $qs = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
        redirectResponse('/reports/machine-production' . $qs);
        return true;
    }

    if ($path === '/reports/machine-staff/excel' && $method === 'GET') {
        $qs = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
        redirectResponse('/reports/machine-production/excel' . $qs);
        return true;
    }

    if ($path === '/reports/machine-production' && $method === 'GET') {
        unibagRenderMachineProductionReportPage($service);
        return true;
    }

    if ($path === '/reports/machine-production/excel' && $method === 'GET') {
        unibagOutputMachineProductionExcel($service);
        return true;
    }

    // Actualización de registro de producción por máquina (Modal de edición)
    if ($path === '/reports/machine-production/update' && $method === 'POST') {
        if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'No tiene permisos suficientes para modificar producción.']);
            return true;
        }

        if (!unibagCanUserPerformModifications()) {
            http_response_code(403);
            $errPermMsg = 'Acceso denegado: únicamente los usuarios autorizados (HECTOR y JAVIER) pueden realizar modificaciones en producción.';
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                   || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => $errPermMsg]);
                return true;
            }
            $redirectUrl = trim((string)($_POST['redirect_url'] ?? '/reports/machine-production'));
            if ($redirectUrl === '' || !str_starts_with($redirectUrl, '/reports/machine-production')) {
                $redirectUrl = '/reports/machine-production';
            }
            $sep = str_contains($redirectUrl, '?') ? '&' : '?';
            redirectResponse($redirectUrl . $sep . 'error=' . rawurlencode($errPermMsg));
            return true;
        }

        requireCsrf();

        $otId = isset($_POST['ot_id']) ? (int)$_POST['ot_id'] : 0;
        $userName = trim((string)($_SESSION['user_name'] ?? $_SESSION['auth_username'] ?? $_SESSION['user_firstname'] ?? 'Usuario'));
        $result = $service->updateProductionRecord($otId, $_POST, $userName);

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            if ($result['ok'] !== true) {
                http_response_code(422);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return true;
        }

        $redirectUrl = trim((string)($_POST['redirect_url'] ?? '/reports/machine-production'));
        if ($redirectUrl === '' || !str_starts_with($redirectUrl, '/reports/machine-production')) {
            $redirectUrl = '/reports/machine-production';
        }
        $sep = str_contains($redirectUrl, '?') ? '&' : '?';
        if ($result['ok'] === true) {
            redirectResponse($redirectUrl . $sep . 'msg=' . rawurlencode((string)($result['message'] ?? 'Registro modificado con éxito.')));
        } else {
            redirectResponse($redirectUrl . $sep . 'error=' . rawurlencode((string)($result['error'] ?? 'Error al actualizar.')));
        }
        return true;
    }

    // Eventos de Máquina (Unificado: Detención, Colación, Cambio Configuración)
    if ($path === '/reports/machine-events' && $method === 'GET') {
        unibagRenderMachineEventsReportPage($service);
        return true;
    }

    if ($path === '/reports/machine-events/excel' && $method === 'GET') {
        unibagOutputMachineEventsExcel($service);
        return true;
    }

    // Colación (Redirige a pestaña unificada o renderiza)
    if ($path === '/reports/colacion' && $method === 'GET') {
        $query = $_GET;
        $query['tab'] = 'colacion';
        redirectResponse('/reports/machine-events?' . http_build_query($query));
        return true;
    }

    if ($path === '/reports/colacion/excel' && $method === 'GET') {
        unibagOutputColacionExcel($service);
        return true;
    }

    // Detención (Redirige a pestaña unificada o renderiza)
    if ($path === '/reports/detencion' && $method === 'GET') {
        $query = $_GET;
        $query['tab'] = 'detencion';
        redirectResponse('/reports/machine-events?' . http_build_query($query));
        return true;
    }

    if ($path === '/reports/detencion/excel' && $method === 'GET') {
        unibagOutputDetencionExcel($service);
        return true;
    }

    // Cambio de Configuración (Redirige a pestaña unificada o renderiza)
    if ($path === '/reports/cambio-configuracion' && $method === 'GET') {
        $query = $_GET;
        $query['tab'] = 'setup';
        redirectResponse('/reports/machine-events?' . http_build_query($query));
        return true;
    }

    if ($path === '/reports/cambio-configuracion/excel' && $method === 'GET') {
        unibagOutputCambioConfiguracionExcel($service);
        return true;
    }

    // Nivel de Servicio
    if ($path === '/reports/nivel-servicio' && $method === 'GET') {
        unibagRenderServiceLevelReportPage($service);
        return true;
    }

    if ($path === '/reports/nivel-servicio/excel' && $method === 'GET') {
        unibagOutputServiceLevelExcel($service);
        return true;
    }

    // Modificación de registro en Nivel de Servicio (OTIF) por acuerdo con cliente
    if ($path === '/reports/nivel-servicio/update' && $method === 'POST') {
        if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'No tiene permisos suficientes para modificar registros de nivel de servicio.']);
            return true;
        }

        if (!unibagCanUserPerformModifications()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Acceso denegado: únicamente los usuarios autorizados (HECTOR y JAVIER) pueden modificar nivel de servicio.']);
            return true;
        }

        requireCsrf();

        $userName = trim((string)($_SESSION['user_name'] ?? $_SESSION['auth_username'] ?? $_SESSION['user_firstname'] ?? 'Usuario'));
        $result = $service->saveServiceLevelAdjustment($_POST, $userName);

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            if ($result['ok'] !== true) {
                http_response_code(422);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return true;
        }

        $redirectUrl = trim((string)($_POST['redirect_url'] ?? '/reports/nivel-servicio'));
        $sep = str_contains($redirectUrl, '?') ? '&' : '?';
        if ($result['ok'] === true) {
            redirectResponse($redirectUrl . $sep . 'msg=' . rawurlencode((string)($result['message'] ?? 'Registro modificado con éxito.')));
        } else {
            redirectResponse($redirectUrl . $sep . 'error=' . rawurlencode((string)($result['error'] ?? 'Error al actualizar.')));
        }
        return true;
    }

    if ($path === '/reports/nivel-servicio/revert' && $method === 'POST') {
        if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'No tiene permisos suficientes.']);
            return true;
        }

        if (!unibagCanUserPerformModifications()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Acceso denegado: únicamente los usuarios autorizados (HECTOR y JAVIER) pueden revertir acuerdos de nivel de servicio.']);
            return true;
        }

        requireCsrf();

        $despachoId = isset($_POST['despacho_id']) ? (int)$_POST['despacho_id'] : 0;
        $userName = trim((string)($_SESSION['user_name'] ?? $_SESSION['auth_username'] ?? $_SESSION['user_firstname'] ?? 'Usuario'));
        $result = $service->revertServiceLevelAdjustment($despachoId, $userName);

        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            if ($result['ok'] !== true) {
                http_response_code(422);
            }
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return true;
        }

        $redirectUrl = trim((string)($_POST['redirect_url'] ?? '/reports/nivel-servicio'));
        $sep = str_contains($redirectUrl, '?') ? '&' : '?';
        redirectResponse($redirectUrl . $sep . 'msg=' . rawurlencode((string)($result['message'] ?? 'Ajuste revertido.')));
        return true;
    }

    if ($path === '/reports/inventory' && $method === 'GET') {
        unibagRenderInventoryReportsPage($service);
        return true;
    }

    if (preg_match('#^/reports/inventory/(\d+)/excel$#', $path, $matches) === 1 && $method === 'GET') {
        unibagOutputInventoryReportExcel($service, (int)$matches[1]);
        return true;
    }

    if (preg_match('#^/reports/inventory/(\d+)$#', $path, $matches) === 1 && $method === 'GET') {
        unibagRenderInventoryReportDetailPage($service, (int)$matches[1]);
        return true;
    }

    // Informe Mensual PPTX
    if ($path === '/reports/monthly-presentation' && $method === 'GET') {
        unibagRenderMonthlyPresentationPage($service);
        return true;
    }

    if ($path === '/reports/monthly-presentation/download' && $method === 'GET') {
        unibagDownloadMonthlyPresentation($service);
        return true;
    }

    return false;
}

/**
 * Renderiza la vista del Generador de Informe Mensual de Operaciones en PPTX.
 */
function unibagRenderMonthlyPresentationPage(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $monthKey = trim((string)($_GET['month'] ?? '2026-09'));
    if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
        $monthKey = '2026-09';
    }

    $presService = new MonthlyPresentationService($service, Db::erpPdo());
    $m = $presService->getMonthlyMetrics($monthKey);

    $monthNames = [
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
        '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
        '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
    ];
    $monthParts = explode('-', $monthKey);
    $monthLabel = ($monthNames[$monthParts[1]] ?? 'Mes') . ' ' . $monthParts[0];

    $downloadUrl = '/reports/monthly-presentation/download?month=' . urlencode($monthKey);

    $body = '<style>
        main { max-width: 98% !important; width: 98% !important; margin: 0 auto !important; padding: 16px 24px !important; box-sizing: border-box !important; }
        .report-shell { max-width: 1600px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 18px; }
        
        .erp-filter-card { width: 100%; box-sizing: border-box; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px 24px; box-shadow: 0 4px 16px rgba(15,23,42,.04); display: flex; flex-direction: column; gap: 16px; }
        .erp-filter-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; width: 100%; }
        .erp-filter-title { font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; text-transform: uppercase; }
        .erp-filter-sub { font-size: 13px; color: #64748b; font-weight: 500; margin-top: 3px; }
        .erp-filter-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        
        .btn-pptx-download { height: 44px; padding: 0 24px; border-radius: 10px; background: linear-gradient(135deg, #d97706, #b45309); color: #fff; font-weight: 800; font-size: 14px; border: none; cursor: pointer; box-shadow: 0 4px 14px rgba(217,119,6,.3); transition: all .15s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; white-space: nowrap; }
        .btn-pptx-download:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(217,119,6,.4); color: #fff; background: linear-gradient(135deg, #b45309, #92400e); }

        .dashboard-kpis-grid { width: 100%; box-sizing: border-box; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
        .kpi-card-premium { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px 22px; position: relative; overflow: hidden; box-shadow: 0 4px 16px rgba(15,23,42,.03); text-decoration: none; display: flex; flex-direction: column; justify-content: space-between; min-height: 140px; box-sizing: border-box; }
        .kpi-card-premium::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
        .kpi-card-premium.blue::before { background: linear-gradient(90deg, #2563eb, #60a5fa); }
        .kpi-card-premium.green::before { background: linear-gradient(90deg, #10b981, #34d399); }
        .kpi-card-premium.cyan::before { background: linear-gradient(90deg, #00A9A6, #2dd4bf); }
        .kpi-card-premium.amber::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .kpi-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; width: 100%; }
        .kpi-tag { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: 3px 8px; border-radius: 6px; }
        .kpi-card-premium.blue .kpi-tag { background: #eff6ff; color: #1d4ed8; }
        .kpi-card-premium.green .kpi-tag { background: #ecfdf5; color: #047857; }
        .kpi-card-premium.cyan .kpi-tag { background: #f0fdfa; color: #0d9488; }
        .kpi-card-premium.amber .kpi-tag { background: #fffbeb; color: #b45309; }
        .kpi-num { font-size: 30px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; line-height: 1.1; text-align: center; }
        .kpi-desc { font-size: 12.5px; color: #64748b; margin-top: 5px; font-weight: 500; text-align: center; }

        .slide-preview-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
        .slide-preview-table th { background: #0f172a; color: #ffffff; padding: 10px 14px; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; }
        .slide-preview-table td { padding: 10px 14px; border-bottom: 1px solid #e2e8f0; }
        .slide-preview-table tr:hover { background: #f8fafc; }
        .badge-dynamic { background: #dcfce7; color: #15803d; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; }
        .badge-static { background: #f1f5f9; color: #475569; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; }
    </style>';

    $body .= '<div class="report-shell">';

    // Card Header y Filtros
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">📊 INFORME MENSUAL DE OPERACIONES (PPTX)</div>';
    $body .= '<div class="erp-filter-sub">Generación automatizada de la presentación ejecutiva basada en la plantilla maestra de 41 láminas con datos calculados del ERP.</div>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-actions">';
    $body .= '<a class="btn-pptx-download" href="' . h($downloadUrl) . '">📥 Descargar Presentación PPTX (' . h($monthLabel) . ')</a>';
    $body .= '<a class="btn-filter-secondary" href="/reports/operator-waste">← Volver a Informes</a>';
    $body .= '</div>';
    $body .= '</div>';

    $body .= '<form method="get" action="/reports/monthly-presentation" style="display:flex;align-items:flex-end;gap:12px;margin-top:14px;border-top:1px solid #f1f5f9;padding-top:14px">';
    $body .= '<div style="display:flex;flex-direction:column;gap:5px">';
    $body .= '<label style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase">Mes de Operación</label>';
    $body .= '<input type="month" name="month" value="' . h($monthKey) . '" style="height:38px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;background:#f8fafc;font-weight:700">';
    $body .= '</div>';
    $body .= '<button type="submit" class="btn-filter-apply" style="height:38px;padding:0 16px;border-radius:8px">Consultar Período</button>';
    $body .= '</form>';
    $body .= '</div>';

    // KPIs del Mes Seleccionado
    $body .= '<div class="dashboard-kpis-grid">';
    
    // KPI 1: Producción
    $body .= '<div class="kpi-card-premium blue">';
    $body .= '<div class="kpi-header-row"><span class="kpi-tag">Producción Real</span><span style="font-size:12px;font-weight:700;color:#1d4ed8">' . number_format($m['prod_cumpl'], 1, ',', '.') . '% meta</span></div>';
    $body .= '<div class="kpi-num">' . number_format($m['prod_units'], 0, ',', '.') . ' <span style="font-size:14px;color:#64748b">Und</span></div>';
    $body .= '<div class="kpi-desc">Meta Planta: ' . number_format($m['prod_meta'], 0, ',', '.') . ' Und (' . h($monthLabel) . ')</div>';
    $body .= '</div>';

    // KPI 2: Nivel de Servicio
    $body .= '<div class="kpi-card-premium green">';
    $body .= '<div class="kpi-header-row"><span class="kpi-tag">Nivel Servicio (OTIF)</span><span style="font-size:12px;font-weight:700;color:#047857">' . $m['sl_total_cc'] . ' CC Totales</span></div>';
    $body .= '<div class="kpi-num">' . number_format($m['sl_percent_cc'], 1, ',', '.') . '%</div>';
    $body .= '<div class="kpi-desc">' . ($m['sl_total_cc'] - $m['sl_delayed_cc']) . ' pedidos a tiempo · ' . $m['sl_delayed_cc'] . ' con atraso</div>';
    $body .= '</div>';

    // KPI 3: Merma Global
    $body .= '<div class="kpi-card-premium cyan">';
    $body .= '<div class="kpi-header-row"><span class="kpi-tag">Merma Planta</span><span style="font-size:12px;font-weight:700;color:#0f766e">Meta: 1,60%</span></div>';
    $body .= '<div class="kpi-num">' . number_format($m['global_waste_percent'], 2, ',', '.') . '%</div>';
    $body .= '<div class="kpi-desc">' . number_format($m['total_waste_kg'], 1, ',', '.') . ' kg de merma registrada en el mes</div>';
    $body .= '</div>';

    // KPI 4: Láminas
    $body .= '<div class="kpi-card-premium amber">';
    $body .= '<div class="kpi-header-row"><span class="kpi-tag">Plantilla Oficial</span><span style="font-size:12px;font-weight:700;color:#b45309">100% Integrada</span></div>';
    $body .= '<div class="kpi-num">41 <span style="font-size:14px;color:#64748b">Láminas</span></div>';
    $body .= '<div class="kpi-desc">11 láminas dinámicas ERP + 30 conservadas del histórico</div>';
    $body .= '</div>';

    $body .= '</div>';

    // Tarjeta Detalle de Láminas
    $body .= '<div class="erp-filter-card">';
    $body .= '<div style="font-size:16px;font-weight:800;color:#0f172a;margin-bottom:8px">📑 ESTRUCTURA DEL INFORME MENSUAL GENERADO (' . h(strtoupper($monthLabel)) . ')</div>';
    $body .= '<div style="font-size:13px;color:#64748b;margin-bottom:14px">A continuación se detalla cómo el sistema inyecta la información del ERP en la presentación PowerPoint, manteniendo la trazabilidad histórica anterior:</div>';

    $body .= '<div style="overflow-x:auto;border:1px solid #e2e8f0;border-radius:12px">';
    $body .= '<table class="slide-preview-table">';
    $body .= '<thead><tr>';
    $body .= '<th>N° Lámina</th><th>Sección</th><th>Contenido Inyectado / Conservado</th><th>Tipo de Dato</th><th>Estado</th>';
    $body .= '</tr></thead><tbody>';

    $slidesInfo = [
        [1, 'Portada Principal', 'Título actualizado: OPERACIONES | ' . strtoupper($monthLabel), 'Dinámico (Texto)', 'badge-dynamic', 'Actualizado'],
        [2, 'Producción Planta', 'Tabla con columna ' . strtolower(substr($monthLabel, 0, 3)) . '-26 (' . number_format($m['prod_units'], 0, ',', '.') . ' Und) y recálculo acumulado Gestión 2026', 'Dinámico (Tabla ERP)', 'badge-dynamic', 'Inyectado'],
        [3, 'Producción en Curso', 'Unidades fabricadas en el ciclo y bolsas programadas en cola', 'Dinámico (ERP)', 'badge-dynamic', 'Inyectado'],
        [4, 'Resumen Dotación', 'Dotación de planta y registros de personal (conservado del histórico base)', 'Histórico / Fijo', 'badge-static', 'Conservado'],
        [5, 'Nivel de Servicio [Und.]', 'Total unidades pedidos (' . number_format($m['sl_total_units'], 0, ',', '.') . ' Und), atrasados y % de cumplimiento', 'Dinámico (Tabla ERP)', 'badge-dynamic', 'Inyectado'],
        [6, 'Nivel de Servicio [C.C.]', 'Total pedidos CC (' . $m['sl_total_cc'] . ' CC), atrasados (' . $m['sl_delayed_cc'] . ') y % cumplimiento (' . number_format($m['sl_percent_cc'], 1, ',', '.') . '%)', 'Dinámico (Tabla ERP)', 'badge-dynamic', 'Inyectado'],
        [7, 'Detalle de Pedidos Atrasados', 'Tabla detallada con OTs de ' . $monthLabel . ' con cliente, fecha requerida y días de retraso', 'Dinámico (Tabla ERP)', 'badge-dynamic', 'Inyectado'],
        [8, 'Mermas Flexografía', 'Kgs programados, procesados, kilos merma y % de merma en Flexos', 'Dinámico (Tabla ERP)', 'badge-dynamic', 'Inyectado'],
        [9, 'Mermas Serigrafía', 'Kgs programados, procesados, kilos merma y % de merma en Seris', 'Dinámico (Tabla ERP)', 'badge-dynamic', 'Inyectado'],
        [10, 'Mermas Corte y Sellado', 'Kgs programados, procesados, kilos merma cortadoras y % de merma', 'Dinámico (Tabla ERP)', 'badge-dynamic', 'Inyectado'],
        [11, 'Mermas Embalaje', 'Kgs programados, procesados, kilos merma embalaje y % de merma', 'Dinámico (Tabla ERP)', 'badge-dynamic', 'Inyectado'],
        [12, 'Total Merma Consolidada', 'Gráfico y porcentaje global consolidado (' . number_format($m['global_waste_percent'], 2, ',', '.') . '%) vs meta (1,60%)', 'Dinámico (ERP)', 'badge-dynamic', 'Inyectado'],
        [13, 'Recall Tottus', 'Seguimiento operativo puntual del proceso de recall', 'Histórico / Fijo', 'badge-static', 'Conservado'],
        ['14-19', 'Aseguramiento de Calidad', 'Certificaciones, pruebas de tracción, test de roce y puntos limpios', 'Histórico / Fotos', 'badge-static', 'Conservado'],
        ['20-23', 'Mantenimiento y Máquinas', 'Disponibilidad y utilización de Flexo, Seri y Selladoras', 'Histórico / Fijo', 'badge-static', 'Conservado'],
        ['24-25', 'Centros de Costos', 'Histórico de CCs procesados y rendimientos', 'Histórico / Fijo', 'badge-static', 'Conservado'],
        ['26-29', 'Seguridad y Salud (SSOO)', 'Días sin accidentes, estadísticas ACHS y prevención', 'Histórico / Fijo', 'badge-static', 'Conservado'],
        [31, 'Bodega Producto Terminado', 'Stock consolidado por cliente al cierre de ' . $monthLabel, 'Dinámico (ERP)', 'badge-dynamic', 'Inyectado'],
        ['32-34', 'Bodega Rotación Stock', 'Días de rotación y detalle de artículos sin movimiento', 'Histórico / Fijo', 'badge-static', 'Conservado'],
        ['35-41', 'Proyectos y Mejoras', 'Fotografías y avances de paneles solares, asfaltado y despeje de planta', 'Histórico / Fotos', 'badge-static', 'Conservado'],
    ];

    foreach ($slidesInfo as $s) {
        $body .= '<tr>';
        $body .= '<td style="font-weight:800;color:#0f172a">Lámina ' . h((string)$s[0]) . '</td>';
        $body .= '<td style="font-weight:700">' . h($s[1]) . '</td>';
        $body .= '<td>' . h($s[2]) . '</td>';
        $body .= '<td><span class="' . h($s[4]) . '">' . h($s[3]) . '</span></td>';
        $body .= '<td><strong style="color:' . ($s[5] === 'Inyectado' || $s[5] === 'Actualizado' ? '#16a34a' : '#64748b') . '">✓ ' . h($s[5]) . '</strong></td>';
        $body .= '</tr>';
    }

    $body .= '</tbody></table>';
    $body .= '</div>';

    // Botón final destacado
    $body .= '<div style="display:flex;justify-content:flex-end;margin-top:14px">';
    $body .= '<a class="btn-pptx-download" href="' . h($downloadUrl) . '">📥 Descargar Presentación PPTX (' . h($monthLabel) . ')</a>';
    $body .= '</div>';

    $body .= '</div>';
    $body .= '</div>';

    render('Informe Mensual PPT', $body);
    exit;
}

/**
 * Genera y descarga el archivo PPTX para el mes solicitado.
 */
function unibagDownloadMonthlyPresentation(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $monthKey = trim((string)($_GET['month'] ?? '2026-09'));
    if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
        $monthKey = '2026-09';
    }

    try {
        $presService = new MonthlyPresentationService($service, Db::erpPdo());
        $filePath = $presService->generatePresentation($monthKey);

        if (!file_exists($filePath)) {
            throw new RuntimeException("No se encontró el archivo generado en disco.");
        }

        $cleanFilename = 'KPIS_OPERACIONES_' . ($monthKey === '2026-09' ? 'SEPTIEMBRE_2026' : strtoupper(date('F_Y', strtotime($monthKey . '-01')))) . '.pptx';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
        header('Content-Disposition: attachment; filename="' . $cleanFilename . '"');
        header('Content-Length: ' . (string)filesize($filePath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($filePath);
        @unlink($filePath);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        render('Error al generar PPTX', '<div class="err" style="margin:20px;padding:15px;background:#fef2f2;border:1px solid #f87171;border-radius:10px;color:#991b1b"><strong>Error al generar presentación:</strong> ' . h($e->getMessage()) . '</div><div style="margin:20px"><a class="btn-filter-secondary" href="/reports/monthly-presentation">← Volver</a></div>');
        exit;
    }
}

// =============================================================================
// INFORME DE DESPACHOS POR PERÍODO O RANGO DE FECHAS
// =============================================================================

/**
 * Renderiza la vista del Informe de Despachos Comerciales por Período Operativo o Rango.
 */
function unibagRenderDispatchesReportPage(ReceptionService $service): void
{
    $perms = sessionAreaPermissions();
    if (!userCanAccessArea('ERP', $perms) && !userCanAccessArea('PRODUCTION', $perms)) {
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

    $clientId = isset($_GET['cliente_id']) && is_numeric($_GET['cliente_id']) && (int)$_GET['cliente_id'] > 0 ? (int)$_GET['cliente_id'] : null;
    $docType = isset($_GET['doc_type']) ? strtoupper(trim((string)$_GET['doc_type'])) : null;
    if ($docType !== 'FA' && $docType !== 'GV') {
        $docType = null;
    }
    $transportId = isset($_GET['transporte_id']) && is_numeric($_GET['transporte_id']) && (int)$_GET['transporte_id'] > 0 ? (int)$_GET['transporte_id'] : null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;
    if ($search === '') {
        $search = null;
    }

    $reportData = $service->getDispatchesPeriodReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $clientId,
        $docType,
        $transportId,
        $search
    );

    $summary = $reportData['summary'];
    $dispatches = $reportData['dispatches'];
    $clients = $reportData['clients'];
    $transports = $reportData['transports'];

    $fmtInt = static fn(float|int $v): string => number_format((float)$v, 0, ',', '.');
    $fmtMoney = static fn(float|int $v): string => '$ ' . number_format((float)$v, 0, ',', '.');

    $excelQueryParams = [
        'filter_type' => $defaultFilterType,
        'period' => $periodYm,
        'start_date' => $rangeStartInput,
        'end_date' => $rangeEndInput,
    ];
    if ($clientId !== null) {
        $excelQueryParams['cliente_id'] = $clientId;
    }
    if ($docType !== null) {
        $excelQueryParams['doc_type'] = $docType;
    }
    if ($transportId !== null) {
        $excelQueryParams['transporte_id'] = $transportId;
    }
    if ($search !== null) {
        $excelQueryParams['q'] = $search;
    }
    $excelUrl = '/reports/despachos/excel?' . http_build_query($excelQueryParams);

    $body = '<style>
        main { max-width: 1540px !important; width: 100% !important; margin: 0 auto !important; padding: 16px 24px !important; box-sizing: border-box !important; }
        .report-shell { max-width: 100%; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        
        .erp-filter-card { width: 100%; box-sizing: border-box; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px 24px; box-shadow: 0 4px 16px rgba(15,23,42,.04); display: flex; flex-direction: column; gap: 16px; }
        .erp-filter-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .erp-filter-title { font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; text-transform: uppercase; }
        .erp-filter-sub { font-size: 13px; color: #64748b; font-weight: 600; margin-top: 2px; text-transform: uppercase; }
        
        .erp-filter-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; align-items: flex-end; }
        .erp-filter-field { display: flex; flex-direction: column; gap: 5px; }
        .erp-filter-field.is-hidden { display: none !important; }
        .erp-filter-field label { font-size: 11px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: .05em; }
        .erp-filter-field select, .erp-filter-field input { height: 40px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 0 12px; background: #f8fafc; font-size: 13px; font-weight: 600; color: #1e293b; outline: none; transition: all .15s ease; box-sizing: border-box; width: 100%; }
        .erp-filter-field select:focus, .erp-filter-field input:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
        
        .erp-filter-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 4px; }
        .btn-filter-apply { height: 40px; padding: 0 20px; border-radius: 10px; background: linear-gradient(135deg,#2563eb,#1d4ed8); color: #fff; font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,.25); transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; justify-content: center; }
        .btn-filter-apply:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(37,99,235,.35); color: #fff; }
        .btn-filter-secondary { height: 40px; padding: 0 16px; border-radius: 10px; background: #f1f5f9; color: #475569; font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; border: 1px solid #cbd5e1; cursor: pointer; transition: all .15s ease; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
        .btn-filter-secondary:hover { background: #e2e8f0; color: #1e293b; }
        .btn-filter-excel { height: 40px; padding: 0 18px; border-radius: 10px; background: linear-gradient(135deg,#059669,#047857); color: #fff; font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(5,150,105,.25); transition: all .15s ease; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; justify-content: center; }
        .btn-filter-excel:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(5,150,105,.35); color: #fff; }

        .dashboard-kpis-grid { width: 100%; box-sizing: border-box; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
        .kpi-card-premium { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px 22px; position: relative; overflow: hidden; box-shadow: 0 4px 16px rgba(15,23,42,.03); display: flex; flex-direction: column; justify-content: space-between; min-height: 140px; box-sizing: border-box; }
        .kpi-card-premium::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
        .kpi-card-premium.c-blue::before { background: linear-gradient(90deg, #2563eb, #38bdf8); }
        .kpi-card-premium.c-green::before { background: linear-gradient(90deg, #059669, #34d399); }
        .kpi-card-premium.c-amber::before { background: linear-gradient(90deg, #d97706, #fbbf24); }
        .kpi-card-premium.c-purple::before { background: linear-gradient(90deg, #7c3aed, #a78bfa); }
        
        .kpi-label { font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
        .kpi-value { font-size: 28px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; margin: 8px 0 4px 0; font-feature-settings: "tnum"; }
        .kpi-value.text-green { color: #059669; }
        .kpi-sub { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; }

        .report-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 22px 24px; box-shadow: 0 4px 16px rgba(15,23,42,.04); display: flex; flex-direction: column; gap: 16px; box-sizing: border-box; }
        .report-card-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .report-card-title { font-size: 18px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: -.01em; display: flex; align-items: center; gap: 8px; }
        .badge-count { font-size: 12px; font-weight: 800; background: #e0f2fe; color: #0284c7; padding: 3px 10px; border-radius: 12px; text-transform: uppercase; }

        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 12px; border: 1px solid #e2e8f0; }
        .table-dispatches { width: 100%; border-collapse: collapse; text-align: left; font-size: 12.5px; }
        .table-dispatches thead th { background: #0f172a; color: #ffffff; font-weight: 800; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; padding: 12px 14px; border: none; white-space: nowrap; }
        .table-dispatches thead th.text-right { text-align: right; }
        .table-dispatches thead th.text-center { text-align: center; }
        .table-dispatches tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .12s ease; }
        .table-dispatches tbody tr:hover { background: #f8fafc; }
        .table-dispatches tbody td { padding: 10px 14px; vertical-align: middle; color: #334155; }
        .table-dispatches tbody td.text-right { text-align: right; }
        .table-dispatches tbody td.text-center { text-align: center; }
        .table-dispatches tfoot td { background: #f1f5f9; font-weight: 800; padding: 12px 14px; border-top: 2px solid #cbd5e1; text-transform: uppercase; font-size: 12.5px; }
        .table-dispatches tfoot td.text-right { text-align: right; }

        .badge-doc { display: inline-block; padding: 3px 8px; border-radius: 6px; font-weight: 800; font-size: 11px; text-transform: uppercase; }
        .badge-doc.fa { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .badge-doc.gv { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .badge-doc.other { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

        .client-name { font-weight: 700; color: #0f172a; font-size: 13px; }
        .client-sub { font-size: 11px; color: #64748b; font-weight: 500; font-family: monospace; }
        .doc-num { font-weight: 800; color: #1e293b; font-feature-settings: "tnum"; font-size: 13px; }
        .amount-highlight { font-weight: 800; color: #059669; font-feature-settings: "tnum"; }
        .units-highlight { font-weight: 800; color: #0f172a; font-feature-settings: "tnum"; }

        @media (max-width: 1024px) {
            .dashboard-kpis-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .dashboard-kpis-grid { grid-template-columns: 1fr; }
            .erp-filter-form { grid-template-columns: 1fr; }
        }
    </style>';

    $body .= '<div class="report-shell">';

    // HEADER Y FILTROS
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">🚚 INFORME DE DESPACHOS POR PERÍODO O RANGO DE FECHAS</div>';
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . ' · SEGUIMIENTO DE SALIDAS Y FACTURACIÓN COMERCIAL</div>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-actions">';
    $body .= '<a href="' . h($excelUrl) . '" class="btn-filter-excel">📥 EXPORTAR A EXCEL</a>';
    $body .= '<a href="/reports/despachos" class="btn-filter-secondary">LIMPIAR</a>';
    $body .= '</div>';
    $body .= '</div>';

    $body .= '<form id="erp-dispatches-filter-form" method="get" action="/reports/despachos" class="erp-filter-form">';
    
    // Tipo de Filtro
    $body .= '<div class="erp-filter-field">';
    $body .= '<label for="filter_type_disp">TIPO DE FILTRO</label>';
    $body .= '<select id="filter_type_disp" name="filter_type">';
    $body .= '<option value="period"' . ($defaultFilterType === 'period' ? ' selected' : '') . '>PERÍODO (26 AL 25)</option>';
    $body .= '<option value="range"' . ($defaultFilterType === 'range' ? ' selected' : '') . '>RANGO PERSONALIZADO</option>';
    $body .= '</select>';
    $body .= '</div>';

    // Período
    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'period' ? '' : ' is-hidden') . '" data-filter-group="period">';
    $body .= '<label for="period_disp">MES DEL PERÍODO</label>';
    $body .= '<input id="period_disp" type="month" name="period" value="' . h($periodYm) . '"' . ($defaultFilterType === 'period' ? '' : ' disabled') . '>';
    $body .= '</div>';

    // Rango Inicio
    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="start_date_disp">FECHA INICIO</label>';
    $body .= '<input id="start_date_disp" type="date" name="start_date" value="' . h($rangeStartInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    // Rango Término
    $body .= '<div class="erp-filter-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range">';
    $body .= '<label for="end_date_disp">FECHA TÉRMINO</label>';
    $body .= '<input id="end_date_disp" type="date" name="end_date" value="' . h($rangeEndInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '>';
    $body .= '</div>';

    // Cliente
    $body .= '<div class="erp-filter-field">';
    $body .= '<label for="cliente_id_disp">CLIENTE</label>';
    $body .= '<select id="cliente_id_disp" name="cliente_id">';
    $body .= '<option value="">TODOS LOS CLIENTES</option>';
    foreach ($clients as $cl) {
        $cId = (int)$cl['id'];
        $cName = (string)$cl['nombre'];
        $cRut = (string)($cl['rut'] ?? '');
        $selected = ($clientId !== null && $clientId === $cId) ? ' selected' : '';
        $label = $cRut !== '' ? ($cName . ' (' . $cRut . ')') : $cName;
        $body .= '<option value="' . $cId . '"' . $selected . '>' . h(strtoupper($label)) . '</option>';
    }
    $body .= '</select>';
    $body .= '</div>';

    // Tipo de Documento
    $body .= '<div class="erp-filter-field">';
    $body .= '<label for="doc_type_disp">TIPO DOCUMENTO</label>';
    $body .= '<select id="doc_type_disp" name="doc_type">';
    $body .= '<option value="">TODOS LOS DOCUMENTOS</option>';
    $body .= '<option value="FA"' . ($docType === 'FA' ? ' selected' : '') . '>FACTURA (FA)</option>';
    $body .= '<option value="GV"' . ($docType === 'GV' ? ' selected' : '') . '>GUÍA DE DESPACHO (GV)</option>';
    $body .= '</select>';
    $body .= '</div>';

    // Transporte
    $body .= '<div class="erp-filter-field">';
    $body .= '<label for="transporte_id_disp">TRANSPORTE</label>';
    $body .= '<select id="transporte_id_disp" name="transporte_id">';
    $body .= '<option value="">TODOS LOS TRANSPORTES</option>';
    foreach ($transports as $tr) {
        $tId = (int)$tr['id'];
        $tName = (string)$tr['nombre'];
        $selected = ($transportId !== null && $transportId === $tId) ? ' selected' : '';
        $body .= '<option value="' . $tId . '"' . $selected . '>' . h(strtoupper($tName)) . '</option>';
    }
    $body .= '</select>';
    $body .= '</div>';

    // Búsqueda de texto
    $body .= '<div class="erp-filter-field" style="grid-column: span 2;">';
    $body .= '<label for="q_disp">BÚSQUEDA RÁPIDA</label>';
    $body .= '<input id="q_disp" type="text" name="q" placeholder="N° DOC, CLIENTE, CC, PRODUCTO, CONDUCTOR..." value="' . h($search ?? '') . '">';
    $body .= '</div>';

    // Botón Submit
    $body .= '<div class="erp-filter-field" style="justify-content: flex-end;">';
    $body .= '<button type="submit" class="btn-filter-apply" style="width: 100%;">APLICAR FILTROS</button>';
    $body .= '</div>';

    $body .= '</form>';
    $body .= '</div>';

    // TARJETAS KPI
    $body .= '<div class="dashboard-kpis-grid">';
    
    // KPI 1: Unidades
    $body .= '<div class="kpi-card-premium c-blue">';
    $body .= '<div class="kpi-label">TOTAL UNIDADES DESPACHADAS</div>';
    $body .= '<div class="kpi-value">' . $fmtInt($summary['total_units']) . '</div>';
    $body .= '<div class="kpi-sub">PROMEDIO: ' . $fmtInt($summary['avg_units_per_dispatch']) . ' UNID / REGISTRO</div>';
    $body .= '</div>';

    // KPI 2: Monto Total CLP
    $body .= '<div class="kpi-card-premium c-green">';
    $body .= '<div class="kpi-label">MONTO TOTAL DESPACHADO (CLP)</div>';
    $body .= '<div class="kpi-value text-green">' . $fmtMoney($summary['total_amount']) . '</div>';
    $body .= '<div class="kpi-sub">PROMEDIO: ' . $fmtMoney($summary['avg_amount_per_dispatch']) . ' / REGISTRO</div>';
    $body .= '</div>';

    // KPI 3: Documentos
    $body .= '<div class="kpi-card-premium c-amber">';
    $body .= '<div class="kpi-label">TOTAL DOCUMENTOS EMITIDOS</div>';
    $body .= '<div class="kpi-value">' . $fmtInt($summary['total_dispatches']) . '</div>';
    $body .= '<div class="kpi-sub">FACTURAS (FA): ' . $fmtInt($summary['fa_count']) . ' · GUÍAS (GV): ' . $fmtInt($summary['gv_count']) . '</div>';
    $body .= '</div>';

    // KPI 4: Clientes
    $body .= '<div class="kpi-card-premium c-purple">';
    $body .= '<div class="kpi-label">CLIENTES ATENDIDOS</div>';
    $body .= '<div class="kpi-value">' . $fmtInt($summary['clients_count']) . '</div>';
    $body .= '<div class="kpi-sub">CLIENTES CON DESPACHOS EN ESTE PERÍODO</div>';
    $body .= '</div>';

    $body .= '</div>'; // End kpis-grid

    // TABLA DETALLADA DE DESPACHOS
    $body .= '<div class="report-card">';
    $body .= '<div class="report-card-head">';
    $body .= '<div class="report-card-title">';
    $body .= '<span>📋 DETALLE DE DESPACHOS REALIZADOS</span>';
    $body .= '<span class="badge-count">' . count($dispatches) . ' REGISTROS</span>';
    $body .= '</div>';
    $body .= '</div>';

    $body .= '<div class="table-responsive">';
    $body .= '<table class="table-dispatches">';
    $body .= '<thead><tr>';
    $body .= '<th>FECHA Y HORA</th>';
    $body .= '<th class="text-center">TIPO DOC</th>';
    $body .= '<th>N° DOC</th>';
    $body .= '<th>CLIENTE</th>';
    $body .= '<th>CC / NOTA VENTA</th>';
    $body .= '<th>CÓDIGO</th>';
    $body .= '<th>PRODUCTO / DETALLE</th>';
    $body .= '<th class="text-right">CANTIDAD (UNID)</th>';
    $body .= '<th class="text-right">PRECIO UNIT. (CLP)</th>';
    $body .= '<th class="text-right">TOTAL NETO (CLP)</th>';
    $body .= '<th>TRANSPORTE / CHOFER</th>';
    $body .= '<th class="text-center">ESTADO</th>';
    $body .= '</tr></thead>';

    $body .= '<tbody>';
    if (empty($dispatches)) {
        $body .= '<tr><td colspan="12" class="text-center" style="padding:48px 16px; color:#64748b; font-size:14px; font-weight:600;">NO SE ENCONTRARON DESPACHOS CON LOS FILTROS SELECCIONADOS.</td></tr>';
    } else {
        foreach ($dispatches as $d) {
            $tipoDoc = (string)($d['tipo_documento'] ?? '');
            $tipoDocClass = $tipoDoc === 'FA' ? 'fa' : ($tipoDoc === 'GV' ? 'gv' : 'other');
            $tipoDocText = $tipoDoc === 'FA' ? 'FACTURA' : ($tipoDoc === 'GV' ? 'GUÍA' : $tipoDoc);

            $units = (float)($d['salida'] ?? 0);
            $unitPrice = (float)($d['unit_price'] ?? 0);
            $totalAmount = (float)($d['total_amount'] ?? 0);

            $body .= '<tr>';
            $body .= '<td style="white-space:nowrap;font-weight:600;font-size:12px;color:#1e293b">' . h((string)($d['fecha_formateada'] ?? '—')) . '</td>';
            $body .= '<td class="text-center"><span class="badge-doc ' . $tipoDocClass . '">' . h($tipoDocText) . '</span></td>';
            $body .= '<td class="doc-num">' . h((string)($d['numero_documento'] ?? '—')) . '</td>';
            $body .= '<td><div class="client-name">' . h(strtoupper((string)($d['cliente_nombre'] ?? '—'))) . '</div>';
            if (!empty($d['cliente_rut'])) {
                $body .= '<div class="client-sub">' . h((string)$d['cliente_rut']) . '</div>';
            }
            $body .= '</td>';
            $ccDisplay = (string)($d['cost_center'] ?? ($d['order_number'] ?? '—'));
            $body .= '<td>' . h($ccDisplay !== '' ? $ccDisplay : '—') . '</td>';
            $body .= '<td style="font-family:monospace;font-weight:600;color:#0284c7">' . h((string)($d['item_codigo'] ?? '—')) . '</td>';
            $itemDesc = (string)($d['item_nombre'] ?? ($d['observacion'] ?? '—'));
            $body .= '<td style="max-width:280px;font-weight:600;color:#1e293b">' . h(strtoupper($itemDesc !== '' ? $itemDesc : '—')) . '</td>';
            $body .= '<td class="text-right units-highlight">' . $fmtInt($units) . '</td>';
            $body .= '<td class="text-right" style="color:#64748b;font-weight:600">' . ($unitPrice > 0 ? $fmtMoney($unitPrice) : '—') . '</td>';
            $body .= '<td class="text-right amount-highlight">' . $fmtMoney($totalAmount) . '</td>';
            $body .= '<td style="font-size:12px;">';
            $body .= '<div style="font-weight:700;color:#1e293b">' . h(strtoupper((string)($d['transporte_nombre'] ?? '—'))) . '</div>';
            if (!empty($d['chofer_nombre'])) {
                $body .= '<div style="font-size:11px;color:#64748b">' . h(strtoupper((string)$d['chofer_nombre'])) . (!empty($d['patente']) ? ' (' . h((string)$d['patente']) . ')' : '') . '</div>';
            }
            $body .= '</td>';
            $body .= '<td class="text-center"><span style="display:inline-block;padding:2px 8px;border-radius:4px;background:#f1f5f9;font-weight:700;font-size:11px;color:#475569;text-transform:uppercase">' . h(strtoupper((string)($d['estado_nombre'] ?? 'EMITIDO'))) . '</span></td>';
            $body .= '</tr>';
        }
    }
    $body .= '</tbody>';

    // TFOOT CON TOTALES
    $body .= '<tfoot>';
    $body .= '<tr>';
    $body .= '<td colspan="7" style="font-weight:800;color:#0f172a;text-align:right">TOTALES DEL PERÍODO:</td>';
    $body .= '<td class="text-right units-highlight" style="font-size:13.5px">' . $fmtInt($summary['total_units']) . '</td>';
    $body .= '<td></td>';
    $body .= '<td class="text-right amount-highlight" style="font-size:14px">' . $fmtMoney($summary['total_amount']) . '</td>';
    $body .= '<td colspan="2"></td>';
    $body .= '</tr>';
    $body .= '</tfoot>';

    $body .= '</table>';
    $body .= '</div>'; // End table-responsive
    $body .= '</div>'; // End report-card

    // SCRIPT PARA SINCRONIZAR FILTRO PERIODO VS RANGO
    $body .= '<script>
        (function () {
            var filterType = document.getElementById("filter_type_disp");
            var form = document.getElementById("erp-dispatches-filter-form");
            function syncFilterFields() {
                if (!filterType || !form) return;
                var mode = filterType.value === "range" ? "range" : "period";
                form.querySelectorAll("[data-filter-group]").forEach(function (field) {
                    var visible = field.getAttribute("data-filter-group") === mode;
                    field.classList.toggle("is-hidden", !visible);
                    field.querySelectorAll("input, select").forEach(function (input) {
                        if (input === filterType) return;
                        input.disabled = !visible;
                    });
                });
            }
            if (filterType && form) {
                filterType.addEventListener("change", syncFilterFields);
                syncFilterFields();
            }
        })();
    </script>';

    $body .= '</div>'; // End report-shell

    render('INFORME DE DESPACHOS POR PERÍODO · ERP', $body);
    exit;
}

/**
 * Exporta el informe de despachos comerciales a Excel (.xlsx streaming).
 */
function unibagOutputDispatchesReportExcel(ReceptionService $service): void
{
    $perms = sessionAreaPermissions();
    if (!userCanAccessArea('ERP', $perms) && !userCanAccessArea('PRODUCTION', $perms)) {
        redirectResponse(firstAllowedAreaHome($perms));
    }

    $filters = unibagResolveProductionDashboardFilters();
    /** @var DateTimeImmutable $start */
    $start = $filters['start'];
    /** @var DateTimeImmutable $end */
    $end = $filters['end'];
    $activeFilterLabel = (string)$filters['active_filter_label'];

    $clientId = isset($_GET['cliente_id']) && is_numeric($_GET['cliente_id']) && (int)$_GET['cliente_id'] > 0 ? (int)$_GET['cliente_id'] : null;
    $docType = isset($_GET['doc_type']) ? strtoupper(trim((string)$_GET['doc_type'])) : null;
    if ($docType !== 'FA' && $docType !== 'GV') {
        $docType = null;
    }
    $transportId = isset($_GET['transporte_id']) && is_numeric($_GET['transporte_id']) && (int)$_GET['transporte_id'] > 0 ? (int)$_GET['transporte_id'] : null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;
    if ($search === '') {
        $search = null;
    }

    $reportData = $service->getDispatchesPeriodReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $clientId,
        $docType,
        $transportId,
        $search
    );

    $summary = $reportData['summary'];
    $dispatches = $reportData['dispatches'];

    $filename = 'informe-despachos-' . date('Ymd-His') . '.xlsx';
    ob_start();

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
    echo '<style>';
    echo 'body{font-family:Calibri,Arial,sans-serif;font-size:11pt;color:#1e293b;background:#ffffff}';
    echo '.report-header{margin-bottom:16px}';
    echo '.report-title{font-size:16pt;font-weight:bold;color:#0f172a}';
    echo '.report-meta{font-size:10pt;color:#475569;margin-top:4px}';
    echo '.section-title{font-size:12pt;font-weight:bold;color:#0f172a;background:#f1f5f9;padding:6px 10px;border-left:4px solid #2563eb;margin:18px 0 8px 0}';
    echo 'table{border-collapse:collapse;width:100%;margin-bottom:18px}';
    echo 'th{background:#0f172a;color:#ffffff;font-weight:bold;font-size:10pt;padding:8px 10px;border:1px solid #94a3b8;vertical-align:middle;text-transform:uppercase}';
    echo 'td{font-size:10pt;padding:6px 10px;border:1px solid #cbd5e1;vertical-align:middle;color:#1e293b}';
    echo '.th-sub{background:#e2e8f0;color:#0f172a;font-weight:bold}';
    echo '.num-int{mso-number-format:"\#\,\#\#0";text-align:right}';
    echo '.num-dec{mso-number-format:"\#\,\#\#0\.00";text-align:right}';
    echo '.text-center{text-align:center}';
    echo '.text-right{text-align:right}';
    echo '.font-bold{font-weight:bold}';
    echo '.highlight-green{color:#059669;font-weight:bold}';
    echo '.row-even{background:#f8fafc}';
    echo '</style></head><body>';

    echo '<div class="report-header">';
    echo '<div class="report-title">INFORME DE DESPACHOS Y FACTURACIÓN COMERCIAL</div>';
    echo '<div class="report-meta"><strong>PERÍODO APLICADO:</strong> ' . h(strtoupper($activeFilterLabel)) . ' | <strong>GENERADO EL:</strong> ' . date('d/m/Y H:i') . '</div>';
    echo '</div>';

    // 1. Resumen
    echo '<div class="section-title">1. RESUMEN EJECUTIVO DE DESPACHOS</div>';
    echo '<table>';
    echo '<tr><th class="th-sub" style="width:340px;text-align:left">INDICADOR</th><th class="th-sub" style="text-align:right;width:200px">VALOR</th></tr>';
    echo '<tr><td>TOTAL UNIDADES DESPACHADAS</td><td class="num-int font-bold">' . (int)round((float)($summary['total_units'] ?? 0)) . '</td></tr>';
    echo '<tr class="row-even"><td>MONTO TOTAL FACTURADO (CLP)</td><td class="num-int highlight-green">' . (int)round((float)($summary['total_amount'] ?? 0)) . '</td></tr>';
    echo '<tr><td>TOTAL DOCUMENTOS EMITIDOS</td><td class="num-int font-bold">' . (int)($summary['total_dispatches'] ?? 0) . '</td></tr>';
    echo '<tr class="row-even"><td>TOTAL FACTURAS (FA)</td><td class="num-int">' . (int)($summary['fa_count'] ?? 0) . '</td></tr>';
    echo '<tr><td>TOTAL GUÍAS DE DESPACHO (GV)</td><td class="num-int">' . (int)($summary['gv_count'] ?? 0) . '</td></tr>';
    echo '<tr class="row-even"><td>CLIENTES ATENDIDOS</td><td class="num-int font-bold">' . (int)($summary['clients_count'] ?? 0) . '</td></tr>';
    echo '<tr><td>PROMEDIO UNIDADES / REGISTRO</td><td class="num-int">' . (int)round((float)($summary['avg_units_per_dispatch'] ?? 0)) . '</td></tr>';
    echo '<tr class="row-even"><td>PROMEDIO MONTO / REGISTRO (CLP)</td><td class="num-int">' . (int)round((float)($summary['avg_amount_per_dispatch'] ?? 0)) . '</td></tr>';
    echo '</table>';

    // 2. Detalle
    echo '<div class="section-title">2. DETALLE DE DESPACHOS REGISTRADOS</div>';
    echo '<table>';
    echo '<thead><tr>';
    echo '<th>FECHA Y HORA</th>';
    echo '<th>TIPO DOC</th>';
    echo '<th>N° DOC</th>';
    echo '<th>CLIENTE</th>';
    echo '<th>RUT CLIENTE</th>';
    echo '<th>CC / NOTA VENTA</th>';
    echo '<th>CÓDIGO ÍTEM</th>';
    echo '<th>PRODUCTO / DETALLE</th>';
    echo '<th style="text-align:right">CANTIDAD (UNID)</th>';
    echo '<th style="text-align:right">PRECIO UNIT. (CLP)</th>';
    echo '<th style="text-align:right">TOTAL NETO (CLP)</th>';
    echo '<th>TRANSPORTE</th>';
    echo '<th>CONDUCTOR</th>';
    echo '<th>PATENTE</th>';
    echo '<th>ESTADO</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    $rowIdx = 0;
    foreach ($dispatches as $d) {
        $rowIdx++;
        $clsEven = ($rowIdx % 2 === 0) ? ' class="row-even"' : '';
        $units = (float)($d['salida'] ?? 0);
        $unitPrice = (float)($d['unit_price'] ?? 0);
        $totalAmt = (float)($d['total_amount'] ?? 0);

        echo '<tr' . $clsEven . '>';
        echo '<td class="text-center">' . h((string)($d['fecha_formateada'] ?? '')) . '</td>';
        echo '<td class="text-center font-bold">' . h((string)($d['tipo_documento'] ?? '')) . '</td>';
        echo '<td class="font-bold">' . h((string)($d['numero_documento'] ?? '')) . '</td>';
        echo '<td>' . h(strtoupper((string)($d['cliente_nombre'] ?? ''))) . '</td>';
        echo '<td>' . h((string)($d['cliente_rut'] ?? '')) . '</td>';
        $ccDisplay = (string)($d['cost_center'] ?? ($d['order_number'] ?? ''));
        echo '<td>' . h($ccDisplay) . '</td>';
        echo '<td>' . h((string)($d['item_codigo'] ?? '')) . '</td>';
        $itemDesc = (string)($d['item_nombre'] ?? ($d['observacion'] ?? ''));
        echo '<td>' . h(strtoupper($itemDesc)) . '</td>';
        echo '<td class="num-int font-bold">' . (int)round($units) . '</td>';
        echo '<td class="num-int">' . (int)round($unitPrice) . '</td>';
        echo '<td class="num-int highlight-green">' . (int)round($totalAmt) . '</td>';
        echo '<td>' . h(strtoupper((string)($d['transporte_nombre'] ?? ''))) . '</td>';
        echo '<td>' . h(strtoupper((string)($d['chofer_nombre'] ?? ''))) . '</td>';
        echo '<td>' . h((string)($d['patente'] ?? '')) . '</td>';
        echo '<td class="text-center">' . h(strtoupper((string)($d['estado_nombre'] ?? 'EMITIDO'))) . '</td>';
        echo '</tr>';
    }

    // Totales fila
    echo '<tfoot>';
    echo '<tr>';
    echo '<td colspan="8" class="text-right font-bold">TOTALES GENERALES:</td>';
    echo '<td class="num-int font-bold">' . (int)round((float)($summary['total_units'] ?? 0)) . '</td>';
    echo '<td></td>';
    echo '<td class="num-int font-bold highlight-green">' . (int)round((float)($summary['total_amount'] ?? 0)) . '</td>';
    echo '<td colspan="4"></td>';
    echo '</tr>';
    echo '</tfoot>';

    echo '</tbody></table></body></html>';

    $html = ob_get_clean();
    SimpleXlsx::streamHtml($filename, $html, 'Despachos');
    exit;
}

