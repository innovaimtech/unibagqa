<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Módulo HTTP · Informes de Despacho, Nivel de Servicio (OTIF), Resumen Diario y PPTX
 * (src/Http/Reports/ServiceAndDispatchReportsModule.php)
 * =============================================================================
 */

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
        .btn-edit-sl { height: 28px; padding: 0 10px; border-radius: 8px; font-size: 11.5px; font-weight: 700; cursor: pointer; transition: all .15s ease; display: inline-flex; align-items: center; gap: 4px; border: 1px solid; white-space: nowrap; }
        .btn-edit-sl-default { background: #ffffff; color: #0F766E; border-color: #99F6E4; }
        .btn-edit-sl-default:hover { background: #F0FDFA; border-color: #00A9A6; transform: translateY(-1px); box-shadow: 0 2px 6px rgba(0,169,166,.15); }
        .btn-edit-sl-active { background: #F0FDFA; color: #0F766E; border-color: #2DD4BF; box-shadow: 0 1px 4px rgba(0,169,166,.15); }
        .btn-edit-sl-active:hover { background: #CCFBF1; }
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
        .btn-sl-primary { height: 40px; padding: 0 20px; border-radius: 10px; background: linear-gradient(135deg, #00A9A6, #0D9488); color: #fff; font-weight: 700; font-size: 13px; border: 1px solid #0D9488; cursor: pointer; transition: all .15s; box-shadow: 0 3px 10px rgba(0,169,166,.22); }
        .btn-sl-primary:hover { transform: translateY(-1px); box-shadow: 0 5px 14px rgba(0,169,166,.30); }
        .btn-sl-secondary { height: 40px; padding: 0 16px; border-radius: 10px; background: #fff; color: #1e293b; font-weight: 700; font-size: 13px; border: 1.5px solid #cbd5e1; cursor: pointer; transition: all .15s; }
        .btn-sl-secondary:hover { background: #f8fafc; color: #0f172a; }
        .btn-sl-danger { height: 40px; padding: 0 16px; border-radius: 10px; background: #fef2f2; color: #dc2626; font-weight: 700; font-size: 13px; border: 1px solid #fecaca; cursor: pointer; transition: all .15s; }
        .btn-sl-danger:hover { background: #fee2e2; }
        @media (max-width: 992px) { .sl-modal-summary { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px) { .sl-modal-summary { grid-template-columns: 1fr; } }
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
        $body .= '<a class="btn-filter-excel" href="/reports/nivel-servicio/excel?' . h($exportParams) . '">📥 Exportar Excel (' . count($rows) . ')</a>';
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
            var delayStatus = document.getElementById("delay_status_sl");
            var delayDays = document.getElementById("delay_days_sl");
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
        $body .= '<div style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:14px;padding:12px 18px;color:#0f766e;font-size:13px;font-weight:600;display:flex;align-items:center;justify-content:space-between;gap:12px">';
        $body .= '<div>🎯 <strong>Filtro de atraso activo:</strong> ' . h($statusText) . $daysText . ' — Mostrando <strong>' . $summary['filtered_count'] . '</strong> de <strong>' . $summary['total_dispatches'] . '</strong> despachos registrados en el período.</div>';
        $body .= '<a href="/reports/nivel-servicio" style="color:#0f766e;text-decoration:underline;font-weight:800;font-size:12px;white-space:nowrap">Mostrar todos</a>';
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
                $committedDateHtml = '<strong style="color:#0F766E">' . h($r['adjusted_committed_date']) . '</strong><br><span style="font-size:10px;color:#94a3b8;text-decoration:line-through" title="Fecha original">' . h($r['original_committed_date']) . '</span>';
            } else {
                $committedDateHtml = h($r['committed_date']);
            }

            $driverInfo = h($r['transport_company']);
            if ($r['driver_name'] !== '') {
                $driverInfo .= ($driverInfo !== '' ? '<br>' : '') . '<span style="font-size:10.5px;color:#334155">' . h($r['driver_name']) . ($r['vehicle_plate'] !== '' ? ' (' . h($r['vehicle_plate']) . ')' : '') . '</span>';
            }

            $obsHtml = h($r['observation']);
            if ($hasAdj) {
                $adjNote = '<span style="background:#f0fdfa;color:#0f766e;border:1px solid #99f6e4;border-radius:6px;padding:2px 6px;font-size:10px;font-weight:700;display:inline-block;margin-bottom:3px">📋 ' . h($r['reason_category']) . '</span>';
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
                            <input type="checkbox" id="sl_input_is_justified" name="is_justified" value="1" style="width:18px;height:18px;cursor:pointer;accent-color:#00A9A6;">
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

/** Servicio del informe mensual; la disponibilidad de mantención (necesita la TRZ) se crea solo al calcularla. */
function unibagMonthlyPresentationService(ReceptionService $service): MonthlyPresentationService
{
    return new MonthlyPresentationService($service, Db::erpPdo(), static fn(): MaintenanceBonusService =>
        new MaintenanceBonusService(Db::erpPdo(), Db::trzPdo(), new BonusConfigurationService(Db::trzPdo())));
}

/**
 * Renderiza la vista del Generador de Informe Mensual de Operaciones en PPTX.
 */
/**
 * Mes del informe mensual: el que llega por ?month=YYYY-MM o, si no llega (o es inválido), el mes en curso.
 * Es el mes calendario: del día 1 al último día del mes (28 o 29 en febrero, 30 o 31 en el resto).
 */
function unibagMonthlyPresentationMonthKey(): string
{
    $monthKey = trim((string)($_GET['month'] ?? ''));
    if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthKey) === 1) {
        return $monthKey;
    }
    return date('Y-m');
}

/**
 * Rango de fechas del informe (?modo=rango&desde=YYYY-MM-DD&hasta=YYYY-MM-DD) o null si se pide el mes operativo.
 *
 * @return array{from:string,to:string}|null
 */
function unibagMonthlyPresentationRange(): ?array
{
    if (($_GET['modo'] ?? '') !== 'rango') {
        return null;
    }
    return MonthlyPresentationService::normalizeRange((string)($_GET['desde'] ?? ''), (string)($_GET['hasta'] ?? ''));
}

function unibagRenderMonthlyPresentationPage(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $monthKey = unibagMonthlyPresentationMonthKey();
    $range = unibagMonthlyPresentationRange();
    $rangeAsked = ($_GET['modo'] ?? '') === 'rango';

    $presService = unibagMonthlyPresentationService($service);
    $m = $presService->getMonthlyMetrics($monthKey, $range['from'] ?? null, $range['to'] ?? null);
    $monthKey = (string)$m['month_key'];

    $monthNames = [
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
        '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
        '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
    ];
    $monthParts = explode('-', $monthKey);
    $monthLabel = $range !== null
        ? 'Del ' . date('d/m/Y', (int)strtotime($range['from'])) . ' al ' . date('d/m/Y', (int)strtotime($range['to']))
        : ($monthNames[$monthParts[1]] ?? 'Mes') . ' ' . $monthParts[0];

    $periodQuery = http_build_query($range !== null
        ? ['modo' => 'rango', 'desde' => $range['from'], 'hasta' => $range['to']]
        : ['month' => $monthKey]);
    $downloadUrl = '/reports/monthly-presentation/download?' . $periodQuery;
    $previewUrl = '/reports/monthly-presentation/preview?' . $periodQuery;
    $rangeFrom = $range['from'] ?? (string)($_GET['desde'] ?? date('Y-m-01'));
    $rangeTo = $range['to'] ?? (string)($_GET['hasta'] ?? date('Y-m-d'));

    $body = '<div class="report-shell">';

    // Card Header y Filtros
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">📊 INFORME MENSUAL DE OPERACIONES (PPTX)</div>';
    $body .= '<div class="erp-filter-sub"></div>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-actions">';
    $body .= '<button type="button" class="btn-filter-secondary" data-ppt-slide="1">👁 Vista previa</button>';
    $body .= '<a class="btn-pptx-download" href="' . h($downloadUrl) . '">📥 Descargar Presentación PPTX (' . h($monthLabel) . ')</a>';
    $body .= '<a class="btn-filter-secondary" href="/reports/operator-waste">← Volver a Informes</a>';
    $body .= '</div>';
    $body .= '</div>';

    $lbl = 'font-size:11px;font-weight:700;color:#475569;text-transform:uppercase';
    $inp = 'height:38px;width:auto;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;background:#f8fafc;font-weight:700';
    $isRange = $rangeAsked;
    $body .= '<form method="get" action="/reports/monthly-presentation" id="mp-filter" style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;margin-top:14px;border-top:1px solid #f1f5f9;padding-top:14px">';
    $body .= '<div style="display:flex;flex-direction:column;gap:5px"><label style="' . $lbl . '" for="mp-modo">Tipo de período</label>'
        . '<select name="modo" id="mp-modo" style="' . $inp . '"><option value="mes"' . ($isRange ? '' : ' selected') . '>Mes (del 1 al último día)</option>'
        . '<option value="rango"' . ($isRange ? ' selected' : '') . '>Rango de fechas</option></select></div>';
    $body .= '<div data-mp="mes" style="display:' . ($isRange ? 'none' : 'flex') . ';flex-direction:column;gap:5px"><label style="' . $lbl . '">Mes de operación</label>'
        . '<input type="month" name="month" value="' . h($monthKey) . '" style="' . $inp . '"' . ($isRange ? ' disabled' : '') . '></div>';
    $body .= '<div data-mp="rango" style="display:' . ($isRange ? 'flex' : 'none') . ';gap:12px;align-items:flex-end">'
        . '<div style="display:flex;flex-direction:column;gap:5px"><label style="' . $lbl . '">Desde</label><input type="date" name="desde" value="' . h($rangeFrom) . '" style="' . $inp . '"' . ($isRange ? '' : ' disabled') . ' required></div>'
        . '<div style="display:flex;flex-direction:column;gap:5px"><label style="' . $lbl . '">Hasta</label><input type="date" name="hasta" value="' . h($rangeTo) . '" style="' . $inp . '"' . ($isRange ? '' : ' disabled') . ' required></div></div>';
    $body .= '<button type="submit" class="btn-filter-apply" style="height:38px;padding:0 16px;border-radius:8px">Consultar período</button>';
    $body .= '</form>';
    $body .= '<script>(function(){var s=document.getElementById("mp-modo");if(!s)return;s.addEventListener("change",function(){document.querySelectorAll("#mp-filter [data-mp]").forEach(function(b){var on=b.getAttribute("data-mp")===s.value;b.style.display=on?"flex":"none";b.querySelectorAll("input").forEach(function(i){i.disabled=!on;});});});})();</script>';
    if ($rangeAsked && $range === null) {
        $body .= '<div style="margin:10px 0;padding:10px 14px;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b;font-size:13px">El rango no es válido (la fecha «desde» debe ser anterior o igual a «hasta» y el rango no puede superar 1 año). Se muestra el mes operativo.</div>';
    }
    $body .= '</div>';

    // Advertencias: datos faltantes o que no se pueden automatizar (antes se rellenaban con cifras de septiembre).
    $pptWarnings = (array)($m['warnings'] ?? []);
    if (($m['origin'] ?? '') !== 'plantilla') {
        $pptWarnings[] = 'Nivel de servicio: se calcula con las guías de despacho del ERP y no por CC como en el informe publicado de septiembre. Revise esas 3 diapositivas antes de presentar.';
    }
    foreach ($pptWarnings as $pptWarning) {
        $body .= '<div style="margin:10px 0;padding:10px 14px;background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;color:#92400e;font-size:13px">⚠️ ' . h((string)$pptWarning) . '</div>';
    }
    $body .= '<div style="margin:6px 0 10px;font-size:12px;color:#64748b">Período calculado: ' . h((string)($m['period_label'] ?? ''))
        . ($range !== null
            ? '. En los gráficos el rango ocupa la última columna («' . h((string)$m['column_label']) . '»); los meses anteriores al rango salen del historial. La capacidad se ajusta a los días del rango.'
            : ' (mes calendario, del día 1 al último día del mes). Los meses publicados hasta septiembre 2026 se mantienen con su ciclo 26 al 25.') . '</div>';

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
    $body .= '<div class="kpi-num">18 <span style="font-size:14px;color:#64748b">Láminas</span></div>';
    $body .= '</div>';

    $body .= '</div>';

    // Tarjeta Detalle de Láminas
    $body .= '<div class="erp-filter-card">';
    $body .= '<div style="font-size:16px;font-weight:800;color:#0f172a;margin-bottom:8px">📑 ESTRUCTURA DEL INFORME MENSUAL GENERADO (' . h(strtoupper($monthLabel)) . ')</div>';

    $body .= '<div style="overflow-x:auto;border:1px solid #e2e8f0;border-radius:12px">';
    $body .= '<table class="slide-preview-table">';
    $body .= '<thead><tr>';
    $body .= '<th>N° Lámina</th><th>Sección</th><th>Contenido Inyectado / Conservado</th><th>Tipo de Dato</th><th>Estado</th>';
    $body .= '</tr></thead><tbody>';

    $proc = (array)($m['procesos'] ?? []);
    $kg = static fn(string $p, string $f): string => number_format((float)($proc[$p][$f] ?? 0), 0, ',', '.');
    $fg = (array)($m['finished_goods'] ?? []);
    $proj = (array)($m['projection'] ?? []);
    $calc = ($m['origin'] ?? '') === 'plantilla' ? 'Publicado (historial)' : 'Calculado (ERP)';
    $slidesInfo = [
        [1, 'Portada', 'OPERACIONES · ' . strtoupper($monthLabel), 'Texto', 'badge-dynamic', 'Actualizado'],
        [2, 'Producción – resumen global', number_format($m['prod_units'], 0, ',', '.') . ' Und y gráfico producción vs. capacidad', $calc, 'badge-dynamic', 'Inyectado'],
        [3, 'Producción – proyección de cierre', 'Mes en curso desde el ' . date('d/m', (int)strtotime((string)($proj['start'] ?? 'today'))) . ': ' . number_format((float)($proj['made'] ?? 0), 0, ',', '.') . ' Und fabricadas · cierre proyectado ' . number_format((float)($proj['projected'] ?? 0), 0, ',', '.') . ' Und', 'Calculado (ERP)', 'badge-dynamic', 'Inyectado'],
        [4, 'Nivel de servicio [unidades]', number_format($m['sl_total_units'], 0, ',', '.') . ' Und, ' . number_format($m['sl_percent_units'], 1, ',', '.') . '%', $calc, 'badge-dynamic', 'Inyectado'],
        [5, 'Nivel de servicio [C.C.]', $m['sl_total_cc'] . ' pedidos, ' . $m['sl_delayed_cc'] . ' atrasados', $calc, 'badge-dynamic', 'Inyectado'],
        [6, 'Pedidos atrasados', 'Hasta 10 pedidos con mayor atraso', 'Calculado (ERP)', 'badge-dynamic', 'Inyectado'],
        [7, 'Mermas – Flexografía', $kg('flexo', 'prog') . ' kg programados · ' . $kg('flexo', 'waste') . ' kg merma', $calc, 'badge-dynamic', 'Inyectado'],
        [8, 'Mermas – Serigrafía', $kg('seri', 'prog') . ' kg programados · ' . $kg('seri', 'waste') . ' kg merma', $calc, 'badge-dynamic', 'Inyectado'],
        [9, 'Mermas – Corte y Sellado', $kg('cys', 'prog') . ' kg programados · ' . $kg('cys', 'waste') . ' kg merma', $calc, 'badge-dynamic', 'Inyectado'],
        [10, 'Mermas – Embalaje', $kg('emb', 'prog') . ' kg programados · ' . $kg('emb', 'waste') . ' kg merma', $calc, 'badge-dynamic', 'Inyectado'],
        [11, 'Total de merma', number_format($m['global_waste_percent'], 2, ',', '.') . '% vs. meta 1,60%', $calc, 'badge-dynamic', 'Inyectado'],
        [12, 'Bodega (portada de sección)', 'Bodega de materias primas y producto terminado', 'Texto', 'badge-static', 'Conservado'],
        [13, 'Bodega – stock por cliente', number_format((float)($fg['total'] ?? 0), 0, ',', '.') . ' Und en bodegas 400, 1000 y 700', 'Calculado (ERP)', 'badge-dynamic', 'Inyectado'],
        [14, 'Bodega – rotación de stock', number_format((float)($fg['still'] ?? 0), 0, ',', '.') . ' Und sin salidas en 90 días', 'Calculado (ERP)', 'badge-dynamic', 'Inyectado'],
        [15, 'Bodega – artículos sin rotación', 'Cliente con más stock detenido vs. otros', 'Calculado (ERP)', 'badge-dynamic', 'Inyectado'],
        [16, 'Bodega – bobinas PP y PLA', 'Bolsas proyectadas (Libro1) al último día de cada mes, 13 meses', 'Calculado (ERP)', 'badge-dynamic', 'Inyectado'],
        [17, 'Bodega – bobinas PLA beige y blanco', 'Bolsas proyectadas (Libro1) al último día de cada mes, 13 meses', 'Calculado (ERP)', 'badge-dynamic', 'Inyectado'],
        [18, 'Cierre', 'Fecha de emisión', 'Texto', 'badge-dynamic', 'Actualizado'],
    ];

    foreach ($slidesInfo as $s) {
        $body .= '<tr class="ppt-row" data-ppt-slide="' . (int)$s[0] . '" title="Ver vista previa de la lámina ' . (int)$s[0] . '">';
        $body .= '<td style="font-weight:800;color:#0f172a">👁 Lámina ' . h((string)$s[0]) . '</td>';
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

    // Ventana de vista previa: al apretar una lámina (o «Vista previa») se muestra la diapositiva generada.
    $body .= '<style>.ppt-row{cursor:pointer}.ppt-row:hover td{background:#f0fdfa}'
        . '#ppt-modal{position:fixed;inset:0;z-index:9999;display:none;background:rgba(15,23,42,.72);padding:18px}'
        . '#ppt-modal.on{display:flex;flex-direction:column}'
        . '#ppt-modal .ppt-top{display:flex;justify-content:space-between;align-items:center;gap:10px;color:#fff;font-weight:800;padding:0 4px 10px}'
        . '#ppt-modal .ppt-top button{height:36px;padding:0 14px;border:0;border-radius:8px;background:#fff;color:#0f172a;font-weight:800;cursor:pointer}'
        . '#ppt-modal .ppt-box{position:relative;flex:1;min-height:0;border-radius:12px;overflow:hidden;background:#334155}'
        . '#ppt-modal iframe{width:100%;height:100%;border:0;display:block}'
        . '#ppt-modal .ppt-wait{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#e2e8f0;font-weight:700;font-size:15px}</style>';
    $body .= '<div id="ppt-modal" role="dialog" aria-modal="true" aria-label="Vista previa de la presentación"><div class="ppt-top"><span>Vista previa · ' . h($monthLabel) . '</span>'
        . '<span><a href="' . h($downloadUrl) . '" style="color:#fff;margin-right:12px">📥 Descargar PPTX</a><button type="button" id="ppt-close">✕ Cerrar</button></span></div>'
        . '<div class="ppt-box"><div class="ppt-wait" id="ppt-wait">Generando la presentación… (puede tardar unos segundos)</div><iframe id="ppt-frame" title="Vista previa"></iframe></div></div>';
    $body .= '<script>(function(){var url=' . json_encode($previewUrl) . ',m=document.getElementById("ppt-modal"),f=document.getElementById("ppt-frame"),w=document.getElementById("ppt-wait"),loaded=false;'
        . 'f.addEventListener("load",function(){if(f.getAttribute("src")){loaded=true;w.style.display="none";}});'
        . 'function open(n){m.classList.add("on");document.body.style.overflow="hidden";if(!f.getAttribute("src")){w.style.display="flex";f.setAttribute("src",url+"#s"+n);}'
        . 'else if(loaded){try{f.contentWindow.location.hash="s"+n;}catch(e){f.setAttribute("src",url+"#s"+n);}}}'
        . 'function close(){m.classList.remove("on");document.body.style.overflow="";}'
        . 'document.querySelectorAll("[data-ppt-slide]").forEach(function(el){el.addEventListener("click",function(){open(el.getAttribute("data-ppt-slide"));});});'
        . 'document.getElementById("ppt-close").addEventListener("click",close);m.addEventListener("click",function(e){if(e.target===m)close();});'
        . 'document.addEventListener("keydown",function(e){if(e.key==="Escape"&&m.classList.contains("on"))close();});})();</script>';

    render('Informe Mensual PPT', $body);
    exit;
}

/**
 * Vista previa de la presentación (se abre dentro de la ventana de la pantalla del informe).
 */
function unibagPreviewMonthlyPresentation(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $range = unibagMonthlyPresentationRange();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        echo (unibagMonthlyPresentationService($service))
            ->generatePreviewHtml(unibagMonthlyPresentationMonthKey(), $range['from'] ?? null, $range['to'] ?? null);
    } catch (Throwable $e) {
        http_response_code(500);
        echo '<!doctype html><meta charset="utf-8"><body style="font-family:Arial,sans-serif;padding:24px;color:#991b1b">'
            . '<b>No se pudo generar la vista previa.</b><pre style="white-space:pre-wrap;color:#475569">' . h($e->getMessage()) . '</pre></body>';
    }
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

    $monthKey = unibagMonthlyPresentationMonthKey();
    $range = unibagMonthlyPresentationRange();

    try {
        $presService = unibagMonthlyPresentationService($service);
        $filePath = $presService->generatePresentation($monthKey, $range['from'] ?? null, $range['to'] ?? null);

        if (!file_exists($filePath)) {
            throw new RuntimeException("No se encontró el archivo generado en disco.");
        }

        $cleanFilename = $range !== null
            ? 'KPIS_OPERACIONES_' . str_replace('-', '', $range['from']) . '_AL_' . str_replace('-', '', $range['to']) . '.pptx'
            : 'KPIS_OPERACIONES_' . strtoupper(date('F_Y', strtotime($monthKey . '-01'))) . '.pptx';

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
        .kpi-label { font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
        .kpi-value { font-size: 28px; font-weight: 800; color: #0f172a; letter-spacing: -.02em; margin: 8px 0 4px 0; font-feature-settings: "tnum"; }
        .kpi-value.text-green { color: #059669; }
        .kpi-sub { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; }
        .report-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 22px 24px; box-shadow: 0 4px 16px rgba(15,23,42,.04); display: flex; flex-direction: column; gap: 16px; box-sizing: border-box; }
        .report-card-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .report-card-title { font-size: 18px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: -.01em; display: flex; align-items: center; gap: 8px; }
        .badge-count { font-size: 12px; font-weight: 800; background: #f0fdfa; color: #0f766e; border: 1px solid #99f6e4; padding: 3px 10px; border-radius: 12px; text-transform: uppercase; }
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 12px; border: 1px solid #e2e8f0; }
        .table-dispatches { width: 100%; border-collapse: collapse; text-align: left; font-size: 12.5px; }
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
        .badge-doc.fa { background: #f0fdfa; color: #0f766e; border: 1px solid #99f6e4; }
        .badge-doc.gv { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .badge-doc.other { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .client-name { font-weight: 700; color: #0f172a; font-size: 13px; }
        .client-sub { font-size: 11px; color: #64748b; font-weight: 500; font-family: monospace; }
        .doc-num { font-weight: 800; color: #1e293b; font-feature-settings: "tnum"; font-size: 13px; }
        .amount-highlight { font-weight: 800; color: #059669; font-feature-settings: "tnum"; }
        .units-highlight { font-weight: 800; color: #0f172a; font-feature-settings: "tnum"; }
    </style>';

    $body .= '<div class="report-shell">';

    // HEADER Y FILTROS
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">🚚 INFORME DE DESPACHOS POR PERÍODO O RANGO DE FECHAS</div>';
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . '</div>';
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

