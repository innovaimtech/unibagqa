<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Módulo HTTP · Informes Operativos de Máquina (Producción, Paradas, Setup, Colaciones)
 * (src/Http/Reports/MachineReportsModule.php)
 * =============================================================================
 */

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
    $totalAssignments = count($assignments);
    $exportParams = http_build_query(array_filter([
        'filter_type' => $defaultFilterType,
        'period' => $periodYm,
        'start_date' => $rangeStartInput,
        'end_date' => $rangeEndInput,
        'planta_id' => $plantaId,
        'equipo_type_id' => $equipoTypeId,
        'equipo_id' => $equipoId,
    ], static fn($value): bool => $value !== null && $value !== '' && $value !== 0));

    // Auto-set planta if not provided
    if ($plantaId === null && !empty($plantas)) {
        $plantaId = (int)$plantas[0]['id'];
    }

    $body = '<div class="trace-stack">';

    // Card Filtros Unificada tipo PANEL DE CONTROL Y PRODUCCIÓN ERP
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">👥 INFORME PERSONAL POR MÁQUINA</div>';
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . ' · ' . number_format($totalAssignments, 0, ',', '.') . ' asignaciones</div>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-actions">';
    if ($totalAssignments > 0) {
        $body .= '<a class="btn-filter-excel" href="/reports/machine-staff/excel?' . h($exportParams) . '">📥 Exportar Excel</a>';
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
    $body .= '<div class="report-shell">';

    if ($msg !== '') {
        $body .= '<div style="padding:12px 18px;border-radius:10px;background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;font-size:14px;font-weight:700;display:flex;align-items:center;gap:10px;box-shadow:0 1px 3px rgba(0,0,0,.05)">✅ ' . h($msg) . '</div>';
    }
    if ($error !== '') {
        $body .= '<div style="padding:12px 18px;border-radius:10px;background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;font-size:14px;font-weight:700;display:flex;align-items:center;gap:10px;box-shadow:0 1px 3px rgba(0,0,0,.05)">⚠️ ' . h($error) . '</div>';
    }

    $subtitle = h($activeFilterLabel) . ' · ' . count($rows) . ' órdenes en total';
    if ($search !== null && $search !== '') {
        $subtitle .= ' · <span style="color:#0F766E;font-weight:700">Buscando: "' . h($search) . '"</span>';
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
    $body .= '<button type="button" id="btn-open-loaded-rolls-modal" class="ub-btn-primary" title="Ver bobinas cargadas en máquinas y devolver a Bodega 3000">📦 Bobinas en Máquinas <span id="badge-loaded-rolls-count" style="background:rgba(255,255,255,0.25);padding:1px 8px;border-radius:999px;font-size:11px">...</span></button>';
    if (!empty($rows)) {
        $body .= '<a class="btn-filter-excel" href="/reports/machine-production/excel?' . h($exportParams) . '">📥 Exportar Excel (' . count($rows) . ' OTs)</a>';
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
            $body .= '<td class="text-center" style="font-weight:700;color:#334155">' . h($duration) . '</td>';
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
                'production_events' => $row['production_events'] ?? [],
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
 
            if ($canEdit) {
                $body .= '<td class="text-center" style="white-space:nowrap"><div style="display:inline-flex;align-items:center;gap:6px"><button type="button" class="btn-ot-rolls-trigger" data-otid="' . (int)($row['ot_id'] ?? 0) . '" data-otnum="' . h($otNum) . '" data-machine="' . h($machineName) . '" style="display:inline-flex;align-items:center;gap:4px;padding:4px 9px;font-size:11px;font-weight:700;border-radius:6px;background:#0d9488;color:#fff;border:none;cursor:pointer;transition:all .15s ease;box-shadow:0 1px 2px rgba(0,0,0,.1)" title="Ver bobinas cargadas en esta OT y devolver a Bodega 3000">📦 Bobinas</button><button type="button" class="btn-edit-prod-trigger" data-prod="' . h((string)$prodRecordJson) . '" style="display:inline-flex;align-items:center;gap:4px;padding:4px 9px;font-size:11px;font-weight:700;border-radius:6px;background:#00A9A6;color:#fff;border:none;cursor:pointer;transition:all .15s ease;box-shadow:0 1px 2px rgba(0,0,0,.1)">✏️ Modificar</button><button type="button" class="btn-delete-prod-trigger" data-otid="' . (int)($row['ot_id'] ?? 0) . '" data-otnum="' . h($otNum) . '" data-machine="' . h($machineName) . '" data-worker="' . h($operator) . '" data-date="' . h($startDt) . '" style="display:inline-flex;align-items:center;gap:4px;padding:4px 9px;font-size:11px;font-weight:700;border-radius:6px;background:#ef4444;color:#fff;border:none;cursor:pointer;transition:all .15s ease;box-shadow:0 1px 2px rgba(0,0,0,.1)" title="Eliminar registro de producción duplicado">🗑️ Borrar</button></div></td>';
            } else {
                $body .= '<td class="text-center" style="white-space:nowrap"><div style="display:inline-flex;align-items:center;gap:6px"><button type="button" class="btn-ot-rolls-trigger" data-otid="' . (int)($row['ot_id'] ?? 0) . '" data-otnum="' . h($otNum) . '" data-machine="' . h($machineName) . '" style="display:inline-flex;align-items:center;gap:4px;padding:4px 9px;font-size:11px;font-weight:700;border-radius:6px;background:#0d9488;color:#fff;border:none;cursor:pointer;transition:all .15s ease;box-shadow:0 1px 2px rgba(0,0,0,.1)" title="Ver bobinas cargadas en esta OT y devolver a Bodega 3000">📦 Bobinas</button><span class="erp-prod-muted" style="font-size:11.5px;color:#94a3b8" title="Modificaciones y eliminaciones permitidas únicamente para HECTOR y JAVIER">🔒 Bloqueado</span></div></td>';
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
                <input type="hidden" id="edit_target_event_id" name="target_event_id" value="">
                <input type="hidden" name="redirect_url" value="' . h($_SERVER['REQUEST_URI'] ?? '/reports/machine-production') . '">

                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:14px 18px;">
                    <div style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#1d4ed8;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between">
                        <span style="display:flex;align-items:center;gap:6px"><span>🎯</span> Bitácora Completa de Eventos Registrados en esta OT</span>
                        <span id="events-count-badge" style="background:#dbeafe;color:#1e40af;font-size:11px;padding:2px 8px;border-radius:999px;font-weight:800">0 eventos</span>
                    </div>
                    <div style="display:grid;grid-template-columns:minmax(260px,1fr) auto;gap:12px;align-items:end;margin-bottom:12px">
                        <div>
                            <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:4px">Selecciona el evento a editar o anular</label>
                            <select id="edit_event_selector" style="width:100%;padding:8px 10px;border:1px solid #93c5fd;border-radius:6px;font-size:13px;background:#fff">
                                <option value="">Usar resumen consolidado de la OT</option>
                            </select>
                            <div id="edit-event-help" style="margin-top:6px;font-size:11px;color:#475569">Si hubo duplicidad o un evento erróneo, puedes seleccionarlo arriba o en la tabla inferior para anularlo o corregirlo.</div>
                        </div>
                        <button type="button" id="btn-delete-selected-event" style="padding:9px 14px;border:none;background:#dc2626;color:#fff;border-radius:8px;font-size:12px;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 4px 12px rgba(220,38,38,.22)">🗑️ Anular evento seleccionado</button>
                    </div>

                    <!-- Tabla Interactiva de Auditoría de Eventos -->
                    <div style="max-height:220px;overflow-y:auto;border:1px solid #cbd5e1;border-radius:8px;background:#fff">
                        <table style="width:100%;border-collapse:collapse;font-size:12px;text-align:left">
                            <thead style="position:sticky;top:0;background:#f1f5f9;border-bottom:1px solid #cbd5e1;font-size:11px;font-weight:800;color:#475569;text-transform:uppercase">
                                <tr>
                                    <th style="padding:6px 10px">ID</th>
                                    <th style="padding:6px 10px">Tipo</th>
                                    <th style="padding:6px 10px">Hora</th>
                                    <th style="padding:6px 10px">Producción / Medida</th>
                                    <th style="padding:6px 10px">Detalle / Auditoría</th>
                                    <th style="padding:6px 10px;text-align:center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="events-audit-tbody">
                                <tr><td colspan="6" style="padding:10px;text-align:center;color:#64748b">Cargando eventos...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Bobinas Asignadas / Cargadas en esta OT -->
                <div id="ot-rolls-section" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:14px 18px;">
                    <div style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#166534;margin-bottom:8px;display:flex;align-items:center;justify-content:space-between">
                        <span style="display:flex;align-items:center;gap:6px"><span>📦</span> Bobinas Cargadas / Asignadas en esta OT</span>
                        <span id="ot-rolls-count-badge" style="background:#dcfce7;color:#15803d;font-size:11px;padding:2px 8px;border-radius:999px;font-weight:800">0 bobinas</span>
                    </div>
                    <div id="ot-rolls-container">
                        <div style="font-size:12px;color:#64748b;font-style:italic">Cargando bobinas asociadas...</div>
                    </div>
                </div>

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

    <!-- Modal para Eliminar Registro de Producción Duplicado -->
    <div id="modal-prod-delete" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.65);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:16px;">
        <div class="modal-prod-dialog" style="background:#fff;width:min(520px, 98%);display:flex;flex-direction:column;border-radius:16px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.3);border:1px solid #fee2e2;overflow:hidden;">
            <div style="background:#b91c1c;color:#fff;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="font-size:20px">🗑️</span>
                    <div>
                        <div style="font-size:16px;font-weight:800;color:#fff">Eliminar Registro de Producción</div>
                        <div style="font-size:11.5px;color:#fecaca">Autorizado únicamente para Héctor y Javier</div>
                    </div>
                </div>
                <button type="button" id="btn-close-delete-modal-x" style="background:transparent;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1">×</button>
            </div>
            
            <form id="form-delete-prod" method="post" action="/reports/machine-production/delete">
                <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
                <input type="hidden" name="ot_id" id="delete_ot_id" value="">
                <input type="hidden" name="event_id" id="delete_event_id" value="">
                <input type="hidden" name="redirect_url" value="' . h($_SERVER['REQUEST_URI'] ?? '/reports/machine-production') . '">
                
                <div style="padding:20px 24px;">
                    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 14px;margin-bottom:16px;">
                        <p id="delete-question-title" style="margin:0;font-size:13px;line-height:1.5;color:#991b1b;font-weight:600">
                            ¿Estás seguro de eliminar este registro de producción?
                        </p>
                        <p id="delete-question-desc" style="margin:6px 0 0;font-size:12px;color:#b91c1c;line-height:1.4">
                            Esta acción eliminará de forma permanente el turno de la OT, sus eventos y mermas registradas. Úsalo para corregir registros u órdenes duplicadas.
                        </p>
                    </div>

                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 16px;font-size:13px;display:flex;flex-direction:column;gap:8px;">
                        <div><strong style="color:#64748b">OT / Número:</strong> <span id="del-info-ot" style="font-weight:700;color:#0f172a">-</span></div>
                        <div><strong style="color:#64748b">Máquina:</strong> <span id="del-info-machine" style="font-weight:600;color:#0f172a">-</span></div>
                        <div><strong style="color:#64748b">Operador:</strong> <span id="del-info-worker" style="font-weight:600;color:#0f172a">-</span></div>
                        <div><strong style="color:#64748b">Fecha / Hora:</strong> <span id="del-info-date" style="font-weight:600;color:#0f172a">-</span></div>
                        <div id="del-info-event-wrap" style="display:none"><strong style="color:#64748b">Evento:</strong> <span id="del-info-event" style="font-weight:600;color:#0f172a">-</span></div>
                    </div>

                    <div id="delete-status-msg" style="display:none;margin-top:14px;font-size:12.5px;font-weight:700;padding:8px 12px;border-radius:6px;text-align:center"></div>
                </div>

                <div style="background:#f8fafc;padding:12px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" id="btn-cancel-delete" style="padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;background:#e2e8f0;color:#334155;border:none;cursor:pointer">Cancelar</button>
                    <button type="submit" id="btn-confirm-delete" style="padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;background:#ef4444;color:#fff;border:none;cursor:pointer;box-shadow:0 2px 4px rgba(239,68,68,0.2)">🗑️ Sí, Eliminar Registro</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script del Modal de Modificación y Eliminación de Producción -->
    <script>
    (function() {
        var modal = document.getElementById("modal-prod-edit");
        if (!modal) return;
        var form = document.getElementById("form-prod-edit");
        var closeBtn = document.getElementById("modal-prod-close-btn");
        var cancelBtn = document.getElementById("modal-prod-cancel-btn");
        var saveBtn = document.getElementById("modal-prod-save-btn");
        var statusDiv = document.getElementById("edit-modal-status");
        var eventSelector = document.getElementById("edit_event_selector");
        var deleteSelectedEventBtn = document.getElementById("btn-delete-selected-event");
        var activeRecord = null;
        var eventIndexMap = {};

        function fillProductionFormValues(data) {
            document.getElementById("edit_started_at").value = data.started_at || "";
            document.getElementById("edit_ended_at").value = data.ended_at || "";
            document.getElementById("edit_produced_units").value = data.produced_units || 0;
            document.getElementById("edit_produced_kg").value = data.produced_kg || 0;
            document.getElementById("edit_produced_meters").value = data.produced_meters || 0;
            document.getElementById("edit_produced_meters_maquina").value = data.produced_meters_maquina || 0;
            document.getElementById("edit_waste_kg").value = data.waste_kg || 0;
            document.getElementById("edit_waste_units").value = data.waste_units || 0;
            document.getElementById("edit_waste_setup_kg").value = data.waste_setup_kg || 0;
            document.getElementById("edit_waste_setup_units").value = data.waste_setup_units || 0;
            document.getElementById("edit_waste_print_kg").value = data.waste_print_kg || 0;
            document.getElementById("edit_waste_print_units").value = data.waste_print_units || 0;
            document.getElementById("edit_waste_coil_kg").value = data.waste_coil_kg || 0;
            document.getElementById("edit_waste_coil_units").value = data.waste_coil_units || 0;
            document.getElementById("edit_waste_repair_kg").value = data.waste_repair_kg || 0;
            document.getElementById("edit_waste_repair_units").value = data.waste_repair_units || 0;
            if (data.helper_id !== undefined) {
                var selAy = document.getElementById("edit_helper_id");
                if (selAy) selAy.value = data.helper_id || "0";
            }
            recalcMetrics();
        }

        function applySelectedEvent() {
            var hiddenTarget = document.getElementById("edit_target_event_id");
            var help = document.getElementById("edit-event-help");
            var selectedEventId = eventSelector ? (eventSelector.value || "") : "";
            if (hiddenTarget) hiddenTarget.value = selectedEventId;
            if (selectedEventId && eventIndexMap[selectedEventId]) {
                fillProductionFormValues(eventIndexMap[selectedEventId]);
                if (help) help.textContent = "Estás modificando solo el evento seleccionado, no el consolidado completo de la OT.";
            } else if (activeRecord) {
                fillProductionFormValues(activeRecord);
                if (help) help.textContent = "Estás usando el resumen consolidado de la OT. Si hubo duplicidad, selecciona un evento específico arriba.";
            }
        }

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
                    activeRecord = d;
                    eventIndexMap = {};
                    var auditTbody = document.getElementById("events-audit-tbody");
                    var countBadge = document.getElementById("events-count-badge");
                    if (eventSelector) {
                        eventSelector.innerHTML = "<option value=\"\">Usar resumen consolidado de la OT</option>";
                        var prodEvents = Array.isArray(d.production_events) ? d.production_events : [];
                        if (countBadge) countBadge.textContent = prodEvents.length + " evento" + (prodEvents.length === 1 ? "" : "s");
                        if (auditTbody) auditTbody.innerHTML = "";

                        if (prodEvents.length === 0) {
                            if (auditTbody) {
                                auditTbody.innerHTML = "<tr><td colspan=\"6\" style=\"padding:12px;text-align:center;color:#64748b\">No se registran eventos individuales desglosados en esta sesión.</td></tr>";
                            }
                        } else {
                            prodEvents.forEach(function(ev) {
                                var opt = document.createElement("option");
                                opt.value = ev.event_id || "";
                                opt.textContent = ev.event_label || ("Evento #" + (ev.event_id || ""));
                                eventSelector.appendChild(opt);
                                eventIndexMap[String(ev.event_id || "")] = ev;

                                if (auditTbody) {
                                    var tr = document.createElement("tr");
                                    tr.style.borderBottom = "1px solid #f1f5f9";
                                    tr.style.transition = "background .15s";

                                    var typeBg = "#e2e8f0"; var typeColor = "#334155";
                                    var evType = (ev.event_type || "prod").toLowerCase();
                                    if (evType === "prod" || evType === "production" || evType === "prodsericolor") {
                                        typeBg = "#dcfce7"; typeColor = "#15803d";
                                    } else if (evType === "materiales") {
                                        typeBg = "#e0f2fe"; typeColor = "#0369a1";
                                    } else if (evType === "apertura") {
                                        typeBg = "#fef3c7"; typeColor = "#b45309";
                                    } else if (evType === "pause") {
                                        typeBg = "#ffedd5"; typeColor = "#c2410c";
                                    } else if (evType === "mantencion") {
                                        typeBg = "#f3e8ff"; typeColor = "#7e22ce";
                                    }

                                    var cmtHtml = (ev.comments || "-");
                                    if (cmtHtml.indexOf("Bobina Semielaborada") !== -1) {
                                        cmtHtml = "<span style=\"display:inline-block;padding:1px 6px;border-radius:4px;font-size:10px;font-weight:800;background:#ffedd5;color:#c2410c;margin-right:4px\">SEMIELABORADA</span> " + cmtHtml;
                                    }

                                    var measures = [];
                                    if (ev.produced_units > 0) measures.push(ev.produced_units.toLocaleString("es-CL") + " un");
                                    if (ev.produced_kg > 0) measures.push(ev.produced_kg.toLocaleString("es-CL") + " kg");
                                    if (ev.produced_meters > 0) measures.push(ev.produced_meters.toLocaleString("es-CL") + " m");
                                    var measStr = measures.length > 0 ? measures.join(" / ") : "-";

                                    tr.innerHTML = "<td style=\"padding:6px 10px;font-weight:700;color:#0f172a\">#" + (ev.event_id || "") + "</td>"
                                        + "<td style=\"padding:6px 10px\"><span style=\"display:inline-block;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:800;background:" + typeBg + ";color:" + typeColor + "\">" + (evType.toUpperCase()) + "</span></td>"
                                        + "<td style=\"padding:6px 10px;white-space:nowrap;color:#64748b\">" + (ev.started_at ? ev.started_at.replace("T", " ") : "-") + "</td>"
                                        + "<td style=\"padding:6px 10px;font-weight:700;color:#0f172a\">" + measStr + "</td>"
                                        + "<td style=\"padding:6px 10px;color:#334155;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap\" title=\"" + (ev.comments || "") + "\">" + cmtHtml + "</td>"
                                        + "<td style=\"padding:6px 10px;text-align:center;white-space:nowrap\">"
                                        + "<button type=\"button\" class=\"btn-load-event-in-form\" data-evid=\"" + ev.event_id + "\" style=\"padding:3px 8px;font-size:11px;font-weight:700;background:#00A9A6;color:#fff;border:none;border-radius:4px;cursor:pointer;margin-right:4px\" title=\"Cargar valores en el formulario\">✏️ Cargar</button>"
                                        + "<button type=\"button\" class=\"btn-del-event-direct\" data-evid=\"" + ev.event_id + "\" style=\"padding:3px 8px;font-size:11px;font-weight:700;background:#ef4444;color:#fff;border:none;border-radius:4px;cursor:pointer\" title=\"Anular este evento\">🗑️</button>"
                                        + "</td>";
                                    auditTbody.appendChild(tr);
                                }
                            });

                            if (auditTbody) {
                                auditTbody.querySelectorAll(".btn-load-event-in-form").forEach(function(bLoad) {
                                    bLoad.addEventListener("click", function() {
                                        var eId = bLoad.getAttribute("data-evid");
                                        if (eventSelector && eId) {
                                            eventSelector.value = eId;
                                            applySelectedEvent();
                                            var targetInp = document.getElementById("edit_produced_units");
                                            if (targetInp) targetInp.focus();
                                        }
                                    });
                                });
                                auditTbody.querySelectorAll(".btn-del-event-direct").forEach(function(bDel) {
                                    bDel.addEventListener("click", function() {
                                        var eId = bDel.getAttribute("data-evid");
                                        if (eId && eventIndexMap[eId]) {
                                            var eObj = eventIndexMap[eId];
                                            openDeleteModal({
                                                scope: "event",
                                                otId: activeRecord.ot_id || "",
                                                otNum: activeRecord.ot_number || "-",
                                                machine: activeRecord.machine_name || "-",
                                                worker: activeRecord.operator_name || "-",
                                                dateStr: eObj.started_at ? eObj.started_at.replace("T", " ") : "-",
                                                eventId: eId,
                                                eventLabel: eObj.event_label || ("Evento #" + eId)
                                            });
                                        }
                                    });
                                });
                            }
                        }
                        eventSelector.value = "";
                    }
                    document.getElementById("edit_target_event_id").value = "";
                    fillProductionFormValues(d);
                    document.getElementById("edit_comments").value = "";
                    modal.style.display = "flex";
                } catch(e) {
                    console.error("Error cargando registro:", e);
                }
            });
        });

        if (eventSelector) {
            eventSelector.addEventListener("change", applySelectedEvent);
        }

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

        // Lógica de eliminación de registros duplicados
        var delModal = document.getElementById("modal-prod-delete");
        var delForm = document.getElementById("form-delete-prod");
        var delCloseX = document.getElementById("btn-close-delete-modal-x");
        var delCancelBtn = document.getElementById("btn-cancel-delete");
        var delConfirmBtn = document.getElementById("btn-confirm-delete");
        var delStatusDiv = document.getElementById("delete-status-msg");
        var deleteEventIdInput = document.getElementById("delete_event_id");
        var deleteQuestionTitle = document.getElementById("delete-question-title");
        var deleteQuestionDesc = document.getElementById("delete-question-desc");
        var deleteInfoEventWrap = document.getElementById("del-info-event-wrap");
        var deleteInfoEvent = document.getElementById("del-info-event");

        function openDeleteModal(config) {
            document.getElementById("delete_ot_id").value = config.otId || "";
            if (deleteEventIdInput) deleteEventIdInput.value = config.eventId || "";
            document.getElementById("del-info-ot").textContent = "OT " + (config.otNum || "-") + " (ID: #" + (config.otId || "-") + ")";
            document.getElementById("del-info-machine").textContent = config.machine || "-";
            document.getElementById("del-info-worker").textContent = config.worker || "-";
            document.getElementById("del-info-date").textContent = config.dateStr || "-";
            if (config.scope === "event") {
                if (deleteQuestionTitle) deleteQuestionTitle.textContent = "¿Estás seguro de anular este evento de producción?";
                if (deleteQuestionDesc) deleteQuestionDesc.textContent = "Esta acción desactiva solo el evento seleccionado y sus mermas asociadas, sin borrar toda la OT del informe.";
                if (deleteInfoEventWrap) deleteInfoEventWrap.style.display = "block";
                if (deleteInfoEvent) deleteInfoEvent.textContent = config.eventLabel || ("#" + (config.eventId || "-"));
                if (delForm) delForm.action = "/reports/machine-production/delete-event";
                if (delConfirmBtn) delConfirmBtn.innerHTML = "🗑️ Sí, Anular Evento";
            } else {
                if (deleteQuestionTitle) deleteQuestionTitle.textContent = "¿Estás seguro de eliminar este registro de producción?";
                if (deleteQuestionDesc) deleteQuestionDesc.textContent = "Esta acción eliminará de forma permanente el turno de la OT, sus eventos y mermas registradas. Úsalo para corregir registros u órdenes duplicadas.";
                if (deleteInfoEventWrap) deleteInfoEventWrap.style.display = "none";
                if (deleteInfoEvent) deleteInfoEvent.textContent = "-";
                if (delForm) delForm.action = "/reports/machine-production/delete";
                if (delConfirmBtn) delConfirmBtn.innerHTML = "🗑️ Sí, Eliminar Registro";
            }
            if (delModal) delModal.style.display = "flex";
        }

        function closeDelModal() {
            if (delModal) delModal.style.display = "none";
            if (delStatusDiv) { delStatusDiv.style.display = "none"; delStatusDiv.textContent = ""; }
            if (delConfirmBtn) { delConfirmBtn.disabled = false; delConfirmBtn.innerHTML = "🗑️ Sí, Eliminar Registro"; }
            if (deleteEventIdInput) deleteEventIdInput.value = "";
            if (delForm) delForm.action = "/reports/machine-production/delete";
        }

        if (delCloseX) delCloseX.addEventListener("click", closeDelModal);
        if (delCancelBtn) delCancelBtn.addEventListener("click", closeDelModal);
        if (delModal) {
            delModal.addEventListener("click", function(e) {
                if (e.target === delModal) closeDelModal();
            });
        }
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape" && delModal && delModal.style.display !== "none") closeDelModal();
        });

        document.querySelectorAll(".btn-delete-prod-trigger").forEach(function(btn) {
            btn.addEventListener("click", function() {
                openDeleteModal({
                    scope: "ot",
                    otId: btn.getAttribute("data-otid") || "",
                    otNum: btn.getAttribute("data-otnum") || "-",
                    machine: btn.getAttribute("data-machine") || "-",
                    worker: btn.getAttribute("data-worker") || "-",
                    dateStr: btn.getAttribute("data-date") || "-"
                });
            });
        });

        if (deleteSelectedEventBtn) {
            deleteSelectedEventBtn.addEventListener("click", function() {
                if (!activeRecord) {
                    return;
                }
                var selectedEventId = eventSelector ? (eventSelector.value || "") : "";
                if (!selectedEventId || !eventIndexMap[selectedEventId]) {
                    if (statusDiv) {
                        statusDiv.style.display = "block";
                        statusDiv.style.color = "#b91c1c";
                        statusDiv.textContent = "⚠️ Primero selecciona el evento específico que quieres anular.";
                    }
                    return;
                }
                var selectedEvent = eventIndexMap[selectedEventId];
                openDeleteModal({
                    scope: "event",
                    otId: activeRecord.ot_id || "",
                    otNum: activeRecord.ot_number || "-",
                    machine: activeRecord.machine_name || "-",
                    worker: activeRecord.operator_name || "-",
                    dateStr: selectedEvent.started_at || "-",
                    eventId: selectedEventId,
                    eventLabel: selectedEvent.event_label || ("Evento #" + selectedEventId)
                });
            });
        }

        if (delForm) {
            delForm.addEventListener("submit", function(e) {
                e.preventDefault();
                if (delConfirmBtn) {
                    delConfirmBtn.disabled = true;
                    delConfirmBtn.innerHTML = "⏳ Eliminando...";
                }
                if (delStatusDiv) {
                    delStatusDiv.style.display = "none";
                    delStatusDiv.textContent = "";
                }

                var formData = new FormData(delForm);
                fetch("/reports/machine-production/delete", {
                    method: "POST",
                    headers: {
                        "X-Requested-With": "XMLHttpRequest",
                        "Accept": "application/json"
                    },
                    body: formData
                })
                .then(function(res) {
                    return res.json().then(function(json) { return { ok: res.ok, data: json }; });
                })
                .then(function(result) {
                    if (result.ok && result.data && result.data.ok) {
                        if (delStatusDiv) {
                            delStatusDiv.style.display = "block";
                            delStatusDiv.style.background = "#dcfce7";
                            delStatusDiv.style.color = "#15803d";
                            delStatusDiv.textContent = "✅ " + (result.data.message || "Registro eliminado con éxito. Actualizando...");
                        }
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    } else {
                        if (delConfirmBtn) {
                            delConfirmBtn.disabled = false;
                            delConfirmBtn.innerHTML = "🗑️ Sí, Eliminar Registro";
                        }
                        var err = (result.data && result.data.error) ? result.data.error : "Error al eliminar registro.";
                        if (delStatusDiv) {
                            delStatusDiv.style.display = "block";
                            delStatusDiv.style.background = "#fee2e2";
                            delStatusDiv.style.color = "#b91c1c";
                            delStatusDiv.textContent = "⚠️ " + err;
                        } else {
                            alert(err);
                        }
                    }
                })
                .catch(function(err) {
                    if (delConfirmBtn) {
                        delConfirmBtn.disabled = false;
                        delConfirmBtn.innerHTML = "🗑️ Sí, Eliminar Registro";
                    }
                    if (delStatusDiv) {
                        delStatusDiv.style.display = "block";
                        delStatusDiv.style.background = "#fee2e2";
                        delStatusDiv.style.color = "#b91c1c";
                        delStatusDiv.textContent = "⚠️ Error de conexión al eliminar.";
                    } else {
                        alert("Error de conexión al eliminar.");
                    }
                });
            });
        }
        })();
        </script>';
    }

    // MODAL Y CONTROL DE BOBINAS CARGADAS EN MÁQUINAS (Retorno a Bodega 3000)
    $csrfVal = csrfToken();
    $body .= '
    <!-- Modal para Control y Devolución de Bobinas en Máquinas -->
    <div id="modal-loaded-rolls" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.65);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:16px;">
        <div style="background:#fff;width:min(1150px, 98%);max-height:92vh;display:flex;flex-direction:column;border-radius:16px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.3);border:1px solid #cbd5e1;overflow:hidden;">
            <!-- Header -->
            <div style="background:#0f766e;color:#fff;padding:16px 24px;display:flex;justify-content:space-between;align-items:center;">
                <div style="display:flex;align-items:center;gap:12px">
                    <span style="font-size:24px">📦</span>
                    <div>
                        <div style="font-size:18px;font-weight:800;color:#fff;display:flex;align-items:center;gap:10px">
                            <span>Control de Bobinas Cargadas en Máquinas</span>
                            <span id="modal-loaded-rolls-badge" style="background:rgba(255,255,255,0.25);padding:2px 10px;border-radius:999px;font-size:12px;font-weight:800">...</span>
                        </div>
                    </div>
                </div>
                <button type="button" id="btn-close-loaded-rolls-x" style="background:rgba(255,255,255,0.15);border:none;color:#fff;width:32px;height:32px;border-radius:999px;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;transition:background .15s" title="Cerrar">&times;</button>
            </div>

            <!-- Filter bar inside modal -->
            <div style="background:#f8fafc;padding:12px 24px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
                <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:240px">
                    <span style="font-size:14px">🔍</span>
                    <input type="text" id="filter-loaded-rolls-search" placeholder="Filtrar por máquina, OT, código de bobina o SKU..." style="width:100%;max-width:440px;padding:7px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;outline:none">
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <button type="button" id="btn-refresh-loaded-rolls" style="background:#fff;border:1px solid #cbd5e1;padding:6px 14px;border-radius:8px;font-size:12px;font-weight:700;color:#334155;cursor:pointer;display:inline-flex;align-items:center;gap:6px">🔄 Actualizar Lista</button>
                </div>
            </div>

            <!-- Notification Toast inside modal -->
            <div id="loaded-rolls-toast" style="display:none;padding:12px 24px;font-size:13px;font-weight:700;border-bottom:1px solid #e2e8f0"></div>

            <!-- Table Container -->
            <div style="overflow-y:auto;flex:1;padding:0;background:#fff">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;text-align:left">
                    <thead style="position:sticky;top:0;background:#f1f5f9;border-bottom:2px solid #cbd5e1;font-size:11px;font-weight:800;color:#475569;text-transform:uppercase;z-index:2">
                        <tr>
                            <th style="padding:10px 14px">Máquina</th>
                            <th style="padding:10px 14px">OT / CC</th>
                            <th style="padding:10px 14px">Bobina</th>
                            <th style="padding:10px 14px">SKU / Insumo</th>
                            <th style="padding:10px 14px;text-align:right">Peso (Kg)</th>
                            <th style="padding:10px 14px;text-align:center">Montada</th>
                            <th style="padding:10px 14px">Operador</th>
                            <th style="padding:10px 14px;text-align:center">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="loaded-rolls-tbody">
                        <tr><td colspan="8" style="padding:28px;text-align:center;color:#64748b">Cargando bobinas en máquina...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div style="background:#f8fafc;padding:12px 24px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
                <div id="loaded-rolls-summary-text" style="font-size:12px;color:#64748b">Cargando...</div>
                <button type="button" id="btn-close-loaded-rolls-footer" style="padding:7px 18px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:8px;font-size:12.5px;font-weight:700;cursor:pointer">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Script para Gestión de Bobinas Cargadas y Devolución a Bodega 3000 -->
    <script>
    (function() {
        var csrfToken = ' . json_encode($csrfVal) . ';
        var loadedRollsModal = document.getElementById("modal-loaded-rolls");
        var rollsTbody = document.getElementById("loaded-rolls-tbody");
        var rollsBadge = document.getElementById("badge-loaded-rolls-count");
        var modalBadge = document.getElementById("modal-loaded-rolls-badge");
        var summaryText = document.getElementById("loaded-rolls-summary-text");
        var searchInput = document.getElementById("filter-loaded-rolls-search");
        var toast = document.getElementById("loaded-rolls-toast");
        var currentRollsData = [];
        var currentFilteredOtId = null;

        function showToast(msg, isError) {
            if (!toast) return;
            toast.style.display = "block";
            toast.style.background = isError ? "#fee2e2" : "#dcfce7";
            toast.style.color = isError ? "#991b1b" : "#166534";
            toast.style.borderBottom = isError ? "1px solid #fca5a5" : "1px solid #86efac";
            toast.innerHTML = (isError ? "⚠️ " : "✅ ") + msg;
            setTimeout(function() {
                toast.style.display = "none";
            }, 6000);
        }

        function escapeHtml(text) {
            if (!text) return "";
            return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/\'/g, "&#039;");
        }

        function renderRollsTable(rolls) {
            if (!rollsTbody) return;
            rollsTbody.innerHTML = "";
            if (!rolls || rolls.length === 0) {
                rollsTbody.innerHTML = \'<tr><td colspan="8" style="padding:36px 16px;text-align:center;color:#64748b;font-size:13px">\' +
                    \'<div style="font-size:32px;margin-bottom:8px">🎉</div>\' +
                    \'<strong>No hay bobinas cargadas en máquinas</strong>\' +
                    \'<div style="font-size:11.5px;color:#94a3b8;margin-top:4px">Todas las bobinas se encuentran en bodega o fueron consumidas debidamente.</div>\' +
                    \'</td></tr>\';
                if (summaryText) summaryText.textContent = "0 bobinas encontradas";
                return;
            }

            var filterVal = searchInput ? searchInput.value.toLowerCase().trim() : "";
            var visibleCount = 0;

            rolls.forEach(function(r) {
                var searchHaystack = (
                    (r.machine_name || "") + " " +
                    (r.process_name || "") + " " +
                    (r.ot_number || "") + " " +
                    (r.cc_number || "") + " " +
                    (r.roll_code || "") + " " +
                    (r.sku_code || "") + " " +
                    (r.sku_description || "") + " " +
                    (r.operator_name || "")
                ).toLowerCase();

                if (filterVal !== "" && searchHaystack.indexOf(filterVal) === -1) {
                    return;
                }
                visibleCount++;

                var tr = document.createElement("tr");
                tr.id = "row-roll-" + r.roll_id;
                tr.style.borderBottom = "1px solid #e2e8f0";
                tr.style.transition = "background .15s";

                var mountBadge = r.is_mounted
                    ? \'<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;background:#dcfce7;color:#15803d"><span style="width:6px;height:6px;border-radius:999px;background:#15803d"></span> Montada</span>\'
                    : \'<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;background:#fef3c7;color:#b45309"><span style="width:6px;height:6px;border-radius:999px;background:#b45309"></span> Tomada</span>\';

                var otLabel = r.ot_number ? (\'<strong>OT \' + escapeHtml(r.ot_number) + \'</strong>\') : \'<span style="color:#94a3b8">-</span>\';
                if (r.cc_number) otLabel += \'<div style="font-size:11px;color:#64748b">CC: \' + escapeHtml(r.cc_number) + \'</div>\';

                var machineLabel = r.machine_name ? (\'<strong>\' + escapeHtml(r.machine_name) + \'</strong>\') : \'<span style="color:#94a3b8">-</span>\';
                if (r.process_name) machineLabel += \'<div style="font-size:11px;color:#0d9488">\' + escapeHtml(r.process_name) + \'</div>\';

                var rollLabel = \'<strong style="font-family:monospace;color:#0f766e;font-size:12px">\' + escapeHtml(r.roll_code) + \'</strong>\' +
                    \'<div style="font-size:10.5px;color:#64748b">ID: #\' + r.roll_id + \'</div>\';

                var skuLabel = \'<strong style="color:#1e293b">\' + escapeHtml(r.sku_code) + \'</strong>\' +
                    \'<div style="font-size:11px;color:#64748b;max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="\' + escapeHtml(r.sku_description) + \'">\' + escapeHtml(r.sku_description) + \'</div>\';

                var weightStr = (Number(r.weight_kg) || 0).toFixed(2) + \' kg\';
                var opLabel = r.operator_name ? escapeHtml(r.operator_name) : \'<span style="color:#94a3b8">-</span>\';

                tr.innerHTML =
                    \'<td style="padding:10px 14px;vertical-align:middle">\' + machineLabel + \'</td>\' +
                    \'<td style="padding:10px 14px;vertical-align:middle">\' + otLabel + \'</td>\' +
                    \'<td style="padding:10px 14px;vertical-align:middle">\' + rollLabel + \'</td>\' +
                    \'<td style="padding:10px 14px;vertical-align:middle">\' + skuLabel + \'</td>\' +
                    \'<td style="padding:10px 14px;vertical-align:middle;text-align:right;font-weight:700;font-variant-numeric:tabular-nums">\' + weightStr + \'</td>\' +
                    \'<td style="padding:10px 14px;vertical-align:middle;text-align:center">\' + mountBadge + \'</td>\' +
                    \'<td style="padding:10px 14px;vertical-align:middle;font-size:12px">\' + opLabel + \'</td>\' +
                    \'<td style="padding:10px 14px;vertical-align:middle;text-align:center;white-space:nowrap">\' +
                        \'<button type="button" class="btn-return-roll-action" data-roll-id="\' + r.roll_id + \'" data-roll-code="\' + escapeHtml(r.roll_code) + \'" style="padding:5px 12px;border:none;border-radius:6px;background:#0f766e;color:#fff;font-size:11.5px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:4px;box-shadow:0 1px 2px rgba(0,0,0,.1);transition:all .15s ease">↩️ Devolver a Bodega 3000</button>\' +
                    \'</td>\';

                rollsTbody.appendChild(tr);
            });

            if (visibleCount === 0 && rolls.length > 0) {
                rollsTbody.innerHTML = \'<tr><td colspan="8" style="padding:24px;text-align:center;color:#64748b">\' +
                    \'No hay bobinas que coincidan con el filtro "<em>\' + escapeHtml(filterVal) + \'</em>".\' +
                    \'</td></tr>\';
            }

            if (summaryText) {
                summaryText.textContent = visibleCount + " bobinas visibles (de " + rolls.length + " en total)";
            }

            rollsTbody.querySelectorAll(".btn-return-roll-action").forEach(function(btn) {
                btn.addEventListener("click", function() {
                    var rId = btn.getAttribute("data-roll-id");
                    var rCode = btn.getAttribute("data-roll-code");
                    executeReturnRoll(rId, rCode, btn);
                });
            });
        }

        function fetchLoadedRolls(workerOtId, onDone) {
            var url = "/reports/machine-production/loaded-rolls";
            if (workerOtId) {
                url += "?worker_ot_id=" + encodeURIComponent(workerOtId);
            }
            fetch(url, {
                headers: { "Accept": "application/json" }
            })
            .then(function(res) { return res.json(); })
            .then(function(json) {
                if (json && json.ok) {
                    currentRollsData = json.rolls || [];
                    if (!workerOtId && rollsBadge) {
                        rollsBadge.textContent = currentRollsData.length;
                        rollsBadge.style.background = currentRollsData.length > 0 ? "#f59e0b" : "rgba(255,255,255,0.25)";
                        rollsBadge.style.color = currentRollsData.length > 0 ? "#fff" : "inherit";
                    }
                    if (modalBadge) {
                        modalBadge.textContent = currentRollsData.length + " en máquina";
                    }
                    renderRollsTable(currentRollsData);
                    if (onDone) onDone(currentRollsData);
                } else {
                    if (rollsTbody) rollsTbody.innerHTML = \'<tr><td colspan="8" style="padding:20px;text-align:center;color:#b91c1c">Error al consultar bobinas.</td></tr>\';
                }
            })
            .catch(function(err) {
                console.error("Error fetching rolls:", err);
                if (rollsTbody) rollsTbody.innerHTML = \'<tr><td colspan="8" style="padding:20px;text-align:center;color:#b91c1c">Error de conexión al cargar bobinas.</td></tr>\';
            });
        }

        function executeReturnRoll(rollId, rollCode, btnElement) {
            if (!confirm("¿Confirmas devolver la bobina " + rollCode + " a Bodega 3000?\\n\\nSe desmontará de la máquina si está montada, se devolverá el stock al ERP y quedará en estado RECEIVED en Bodega 3000.")) {
                return;
            }

            var origText = btnElement ? btnElement.innerHTML : "";
            if (btnElement) {
                btnElement.disabled = true;
                btnElement.innerHTML = "⏳ Devolviendo...";
            }

            var fd = new FormData();
            fd.append("_csrf", csrfToken);
            fd.append("roll_id", rollId);
            fd.append("reason", "Devolución a Bodega 3000 desde Informe de Producción por Máquina");

            fetch("/reports/machine-production/return-roll", {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "Accept": "application/json"
                },
                body: fd
            })
            .then(function(res) { return res.json(); })
            .then(function(result) {
                if (result && result.ok) {
                    showToast(result.message || ("Bobina " + rollCode + " devuelta con éxito a Bodega 3000."), false);
                    var row = document.getElementById("row-roll-" + rollId);
                    if (row) {
                        row.style.background = "#dcfce7";
                        setTimeout(function() {
                            row.remove();
                        }, 400);
                    }
                    setTimeout(function() {
                        fetchLoadedRolls(currentFilteredOtId);
                    }, 500);
                } else {
                    if (btnElement) {
                        btnElement.disabled = false;
                        btnElement.innerHTML = origText;
                    }
                    var err = (result && result.error) ? result.error : "Error al devolver la bobina.";
                    showToast(err, true);
                    alert("⚠️ " + err);
                }
            })
            .catch(function(err) {
                console.error("Error returning roll:", err);
                if (btnElement) {
                    btnElement.disabled = false;
                    btnElement.innerHTML = origText;
                }
                showToast("Error de conexión al procesar la devolución.", true);
                alert("⚠️ Error de conexión al procesar la devolución.");
            });
        }

        function openLoadedRollsModal(workerOtId) {
            currentFilteredOtId = workerOtId || null;
            if (searchInput) searchInput.value = "";
            if (loadedRollsModal) loadedRollsModal.style.display = "flex";
            if (rollsTbody) rollsTbody.innerHTML = \'<tr><td colspan="8" style="padding:24px;text-align:center;color:#64748b">Cargando bobinas...</td></tr>\';
            fetchLoadedRolls(currentFilteredOtId);
        }

        function closeLoadedRollsModal() {
            if (loadedRollsModal) loadedRollsModal.style.display = "none";
            currentFilteredOtId = null;
        }

        var openBtn = document.getElementById("btn-open-loaded-rolls-modal");
        if (openBtn) openBtn.addEventListener("click", function() { openLoadedRollsModal(null); });

        var closeX = document.getElementById("btn-close-loaded-rolls-x");
        if (closeX) closeX.addEventListener("click", closeLoadedRollsModal);

        var closeFooter = document.getElementById("btn-close-loaded-rolls-footer");
        if (closeFooter) closeFooter.addEventListener("click", closeLoadedRollsModal);

        var refreshBtn = document.getElementById("btn-refresh-loaded-rolls");
        if (refreshBtn) refreshBtn.addEventListener("click", function() { fetchLoadedRolls(currentFilteredOtId); });

        if (searchInput) {
            // Debounce: se espera 250 ms tras la última tecla antes de volver a pintar la tabla.
            var rollsSearchTimer = null;
            searchInput.addEventListener("input", function() {
                clearTimeout(rollsSearchTimer);
                rollsSearchTimer = setTimeout(function() {
                    renderRollsTable(currentRollsData);
                }, 250);
            });
        }

        document.querySelectorAll(".btn-ot-rolls-trigger").forEach(function(b) {
            b.addEventListener("click", function() {
                var otId = b.getAttribute("data-otid");
                openLoadedRollsModal(otId);
            });
        });

        // Carga de bobinas dinámicas dentro del modal de edición de OT
        function loadOtRollsIntoEditModal(otId) {
            var container = document.getElementById("ot-rolls-container");
            var badge = document.getElementById("ot-rolls-count-badge");
            if (!container) return;
            container.innerHTML = \'<div style="font-size:12px;color:#64748b;font-style:italic">Cargando bobinas asociadas...</div>\';
            if (badge) badge.textContent = "...";

            fetch("/reports/machine-production/loaded-rolls?worker_ot_id=" + encodeURIComponent(otId), {
                headers: { "Accept": "application/json" }
            })
            .then(function(res) { return res.json(); })
            .then(function(json) {
                var rolls = (json && json.ok) ? (json.rolls || []) : [];
                if (badge) badge.textContent = rolls.length + " bobinas";
                if (rolls.length === 0) {
                    container.innerHTML = \'<div style="font-size:12px;color:#64748b;font-style:italic">Esta OT no tiene bobinas cargadas o montadas actualmente en la máquina.</div>\';
                    return;
                }
                var h = \'<table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:4px">\' +
                    \'<thead><tr style="border-bottom:1px solid #bbf7d0;color:#166534;font-size:11px;font-weight:700;text-transform:uppercase">\' +
                    \'<th style="padding:4px 8px;text-align:left">Código</th>\' +
                    \'<th style="padding:4px 8px;text-align:left">Insumo / SKU</th>\' +
                    \'<th style="padding:4px 8px;text-align:right">Peso (Kg)</th>\' +
                    \'<th style="padding:4px 8px;text-align:center">Estado</th>\' +
                    \'<th style="padding:4px 8px;text-align:center">Acción</th>\' +
                    \'</tr></thead><tbody>\';
                rolls.forEach(function(r) {
                    var mBadge = r.is_mounted
                        ? \'<span style="background:#dcfce7;color:#15803d;padding:2px 6px;border-radius:4px;font-weight:700">🟢 Montada</span>\'
                        : \'<span style="background:#fef3c7;color:#b45309;padding:2px 6px;border-radius:4px;font-weight:700">🟡 Tomada</span>\';
                    h += \'<tr id="edit-modal-roll-\' + r.roll_id + \'" style="border-bottom:1px solid #e2e8f0">\' +
                        \'<td style="padding:6px 8px;font-family:monospace;font-weight:700;color:#0f766e">\' + escapeHtml(r.roll_code) + \'</td>\' +
                        \'<td style="padding:6px 8px"><strong>\' + escapeHtml(r.sku_code) + \'</strong> <span style="font-size:11px;color:#64748b">\' + escapeHtml(r.sku_description) + \'</span></td>\' +
                        \'<td style="padding:6px 8px;text-align:right;font-weight:700">\' + (Number(r.weight_kg) || 0).toFixed(2) + \' kg</td>\' +
                        \'<td style="padding:6px 8px;text-align:center">\' + mBadge + \'</td>\' +
                        \'<td style="padding:6px 8px;text-align:center">\' +
                            \'<button type="button" class="btn-return-roll-from-edit" data-roll-id="\' + r.roll_id + \'" data-roll-code="\' + escapeHtml(r.roll_code) + \'" style="padding:4px 9px;border:none;border-radius:6px;background:#0f766e;color:#fff;font-size:11px;font-weight:700;cursor:pointer">↩️ Devolver a Bodega 3000</button>\' +
                        \'</td></tr>\';
                });
                h += \'</tbody></table>\';
                container.innerHTML = h;

                container.querySelectorAll(".btn-return-roll-from-edit").forEach(function(b) {
                    b.addEventListener("click", function() {
                        var rId = b.getAttribute("data-roll-id");
                        var rCode = b.getAttribute("data-roll-code");
                        executeReturnRoll(rId, rCode, b);
                        setTimeout(function() {
                            loadOtRollsIntoEditModal(otId);
                        }, 600);
                    });
                });
            })
            .catch(function(e) {
                container.innerHTML = \'<div style="font-size:12px;color:#b91c1c">Error cargando bobinas de la OT.</div>\';
            });
        }

        document.querySelectorAll(".btn-edit-prod-trigger").forEach(function(btn) {
            btn.addEventListener("click", function() {
                try {
                    var d = JSON.parse(btn.getAttribute("data-prod"));
                    if (d && d.ot_id) {
                        loadOtRollsIntoEditModal(d.ot_id);
                    }
                } catch(e) {}
            });
        });

        // Carga inicial asíncrona del contador de bobinas en máquina
        fetchLoadedRolls(null);
    })();
    </script>';

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

    $body = '<div class="report-shell">';

    // Card Filtros Unificada tipo PANEL DE CONTROL Y PRODUCCIÓN ERP
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">☕ INFORME DE COLACIÓN</div>';
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . ' · ' . count($rows) . ' registros encontrados</div>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-actions">';
    if (!empty($rows)) {
        $body .= '<a class="btn-filter-excel" href="/reports/colacion/excel?' . h($exportParams) . '">📥 Exportar Excel (' . count($rows) . ')</a>';
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
 * Exporta a Excel el informe de eventos de máquina sin producción.
 */
function unibagOutputMachineStandbyExcel(ReceptionService $service): void
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
    $reason = isset($_GET['reason']) ? trim((string)$_GET['reason']) : null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    $report = $service->getMachineStandbyReport(
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $plantaId,
        $equipoTypeId,
        $equipoId,
        $reason,
        $search
    );

    $rows = $report['rows'];
    $filename = 'informe-sin-produccion-' . date('Ymd-His') . '.xlsx';
    ob_start();

    echo '<html><head><meta charset="UTF-8"><style>
        body{font-family:Arial,sans-serif;font-size:12px;color:#0f172a}
        table{border-collapse:collapse;width:100%}
        th,td{border:1px solid #cbd5e1;padding:6px 8px}
        th{background:#0f172a;color:#fff;font-weight:700;text-align:center}
        tr:nth-child(even){background:#f8fafc}
    </style></head><body>';
    echo '<h2>Informe de Eventos Sin Producción</h2>';
    echo '<p>Filtro: ' . h($activeFilterLabel) . ' · Generado: ' . date('d/m/Y H:i') . '</p>';
    echo '<table>';
    echo '<tr><th>Fecha</th><th>Hora Inicio</th><th>Hora Fin</th><th>Duración Minutos</th><th>Duración</th><th>Motivo Sin Producción</th><th>Máquina</th><th>Operario</th><th>Estado</th><th>Observaciones</th></tr>';
    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td align="center">' . h($r['date']) . '</td>';
        echo '<td align="center">' . h($r['start_time']) . '</td>';
        echo '<td align="center">' . h($r['end_time']) . '</td>';
        echo '<td align="right">' . $r['duration_minutes'] . '</td>';
        echo '<td align="right">' . h($r['duration_str']) . '</td>';
        echo '<td>' . h($r['reason']) . '</td>';
        echo '<td>' . h($r['machine_name']) . '</td>';
        echo '<td>' . h($r['operator_name']) . '</td>';
        echo '<td align="center">' . h($r['status']) . '</td>';
        echo '<td>' . h($r['comments']) . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    $html = ob_get_clean();

    SimpleXlsx::streamHtml($filename, $html, 'SinProduccion');
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

    $body = '<div class="report-shell">';

    // Card Filtros Unificada tipo PANEL DE CONTROL Y PRODUCCIÓN ERP
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">🛑 INFORME DE DETENCIONES</div>';
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . ' · ' . count($rows) . ' detenciones registradas</div>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-actions">';
    if (!empty($rows)) {
        $body .= '<a class="btn-filter-excel" href="/reports/detencion/excel?' . h($exportParams) . '">📥 Exportar Excel (' . count($rows) . ')</a>';
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

    $body = '<div class="report-shell">';

    // Card Filtros Unificada tipo PANEL DE CONTROL Y PRODUCCIÓN ERP
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">⚙️ INFORME DE CAMBIO DE CONFIGURACIÓN (SETUP)</div>';
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . ' · ' . count($rows) . ' cambios registrados</div>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-actions">';
    if (!empty($rows)) {
        $body .= '<a class="btn-filter-excel" href="/reports/cambio-configuracion/excel?' . h($exportParams) . '">📥 Exportar Excel (' . count($rows) . ')</a>';
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
    if (!in_array($activeTab, ['detencion', 'sin-produccion', 'standby', 'colacion', 'setup'], true)) {
        if ($activeTab === 'cambio-configuracion') {
            $activeTab = 'setup';
        } else {
            $activeTab = 'detencion';
        }
    }
    if ($activeTab === 'standby') {
        $activeTab = 'sin-produccion';
    }

    $plantaId = isset($_GET['planta_id']) && is_numeric($_GET['planta_id']) ? (int)$_GET['planta_id'] : null;
    $equipoTypeId = isset($_GET['equipo_type_id']) && is_numeric($_GET['equipo_type_id']) ? (int)$_GET['equipo_type_id'] : null;
    $equipoId = isset($_GET['equipo_id']) && is_numeric($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : null;
    $pauseId = isset($_GET['pause_id']) && is_numeric($_GET['pause_id']) ? (int)$_GET['pause_id'] : null;
    $reason = isset($_GET['reason']) ? trim((string)$_GET['reason']) : null;
    $search = isset($_GET['q']) ? trim((string)$_GET['q']) : null;

    $startStr = $start->format('Y-m-d H:i:s');
    $endStr = $end->format('Y-m-d H:i:s');

    // Cargar reporte según pestaña activa y obtener conteos para las pestañas
    if ($activeTab === 'sin-produccion') {
        $standbyReport = $service->getMachineStandbyReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, $reason, $search);
        $detencionReport = $service->getMachineStopsReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, null, $search);
        $colacionReport = $service->getMachineBreaksReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, $search);
        $setupReport = $service->getMachineSetupReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, $search);
    } elseif ($activeTab === 'detencion') {
        $detencionReport = $service->getMachineStopsReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, $pauseId, $search);
        $standbyReport = $service->getMachineStandbyReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, null, $search);
        $colacionReport = $service->getMachineBreaksReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, $search);
        $setupReport = $service->getMachineSetupReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, $search);
    } elseif ($activeTab === 'colacion') {
        $colacionReport = $service->getMachineBreaksReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, $search);
        $standbyReport = $service->getMachineStandbyReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, null, $search);
        $detencionReport = $service->getMachineStopsReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, null, $search);
        $setupReport = $service->getMachineSetupReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, $search);
    } else { // setup
        $setupReport = $service->getMachineSetupReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, $search);
        $standbyReport = $service->getMachineStandbyReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, null, $search);
        $detencionReport = $service->getMachineStopsReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, null, $search);
        $colacionReport = $service->getMachineBreaksReport($startStr, $endStr, $plantaId, $equipoTypeId, $equipoId, $search);
    }

    $detencionCount = (int)($detencionReport['summary']['total_stops'] ?? count($detencionReport['rows'] ?? []));
    $standbyCount = (int)($standbyReport['summary']['total_records'] ?? count($standbyReport['rows'] ?? []));
    $colacionCount = (int)($colacionReport['summary']['total_records'] ?? count($colacionReport['rows'] ?? []));
    $setupCount = (int)($setupReport['summary']['total_setups'] ?? count($setupReport['rows'] ?? []));

    $currentReport = match($activeTab) {
        'sin-produccion' => $standbyReport,
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
    $buildUrl = static function (array $overrides) use ($defaultFilterType, $periodYm, $rangeStartInput, $rangeEndInput, $plantaId, $equipoTypeId, $equipoId, $pauseId, $reason, $search, $activeTab): string {
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
            'reason' => $reason,
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
        'reason' => $reason,
        'q' => $search,
    ], static fn($v) => $v !== null && $v !== ''));

    $body = '<div class="report-shell">';

    // Card Filtros Unificada tipo PANEL DE CONTROL Y PRODUCCIÓN ERP
    $body .= '<div class="erp-filter-card">';
    $body .= '<div class="erp-filter-header">';
    $body .= '<div>';
    $body .= '<div class="erp-filter-title">⚡ INFORME DE EVENTOS DE MÁQUINA</div>';
    
    $tabTitles = [
        'detencion' => 'Detenciones registradas',
        'sin-produccion' => 'Eventos de máquina sin producción',
        'colacion' => 'Colaciones registradas',
        'setup' => 'Cambios de configuración (Setup)',
    ];
    $activeTabCount = match($activeTab) {
        'sin-produccion' => $standbyCount,
        'colacion' => $colacionCount,
        'setup' => $setupCount,
        default => $detencionCount,
    };
    $body .= '<div class="erp-filter-sub">' . h($activeFilterLabel) . ' · ' . number_format($activeTabCount, 0, ',', '.') . ' ' . ($tabTitles[$activeTab] ?? 'eventos') . '</div>';
    $body .= '</div>';
    $body .= '<div class="erp-filter-actions">';
    $body .= '<a class="btn-filter-excel" href="/reports/machine-events/excel?' . h($exportParams) . '">📥 Exportar Excel (' . number_format($activeTabCount, 0, ',', '.') . ')</a>';
    $body .= '<a class="btn-filter-secondary" href="/">← Volver al Dashboard</a>';
    $body .= '</div>';
    $body .= '</div>';

    // Tabs de navegación
    $body .= '<div class="report-tabs" style="margin:2px 0 0 0">';
    $tabsDef = [
        'detencion' => ['label' => 'Detención', 'count' => $detencionCount],
        'sin-produccion' => ['label' => 'Sin Producción', 'count' => $standbyCount],
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
    } elseif ($activeTab === 'sin-produccion') {
        $body .= '<div class="erp-filter-field" style="min-width:180px">';
        $body .= '<label for="reason_evt">Motivo Sin Producción</label>';
        $body .= '<select id="reason_evt" name="reason">';
        $body .= '<option value="">Todos los motivos</option>';
        $reasons = $standbyReport['reasons'] ?? [];
        foreach ($reasons as $rs) {
            $body .= '<option value="' . h($rs) . '"' . ($reason === $rs ? ' selected' : '') . '>' . h($rs) . '</option>';
        }
        $body .= '</select></div>';
    }

    $body .= '<div class="erp-filter-field" style="flex:1;min-width:220px">';
    $body .= '<label for="q_evt">Buscar Operario / Máquina / OT</label>';
    $body .= '<input id="q_evt" type="text" name="q" placeholder="Buscar..." value="' . h((string)$search) . '">';
    $body .= '</div>';

    $body .= '<div style="display:flex;gap:8px;align-items:flex-end">';
    $body .= '<button class="btn-filter-apply" type="submit">🔍 Aplicar Filtros</button>';
    if (($search !== null && $search !== '') || ($reason !== null && $reason !== '')) {
        $body .= '<a class="btn-filter-secondary" href="/reports/machine-events?tab=' . h($activeTab) . '" title="Limpiar">✕ Limpiar</a>';
    }
    $body .= '</div>';

    $body .= '</form>';
    $body .= '</div>';


    // KPIs según pestaña
    if ($activeTab === 'detencion') {
        $summary = $detencionReport['summary'];
        $body .= '<div class="kpi-grid" style="grid-template-columns:repeat(4,minmax(0,1fr));margin-top:14px">';
        $body .= '<div class="kpi-card"><div class="kpi-label">Total Detenciones</div><div class="kpi-value">' . number_format($summary['total_stops'], 0, ',', '.') . '</div></div>';
        $body .= '<div class="kpi-card"><div class="kpi-label">Horas Detenidas</div><div class="kpi-value" style="color:#b91c1c">' . number_format($summary['total_hours'], 1, ',', '.') . ' h</div></div>';
        $body .= '<div class="kpi-card"><div class="kpi-label">Minutos Totales</div><div class="kpi-value">' . number_format($summary['total_minutes'], 0, ',', '.') . ' min</div></div>';
        $body .= '<div class="kpi-card"><div class="kpi-label">Promedio por Parada</div><div class="kpi-value">' . number_format($summary['avg_minutes'], 1, ',', '.') . ' min</div></div>';
        $body .= '</div>';
    } elseif ($activeTab === 'sin-produccion') {
        $summary = $standbyReport['summary'];
        $body .= '<div class="kpi-grid" style="grid-template-columns:repeat(5,minmax(0,1fr));margin-top:14px">';
        $body .= '<div class="kpi-card"><div class="kpi-label">Total Registros</div><div class="kpi-value">' . number_format($summary['total_records'], 0, ',', '.') . '</div></div>';
        $body .= '<div class="kpi-card"><div class="kpi-label">Terminadas</div><div class="kpi-value" style="color:#16a34a">' . number_format($summary['completed_records'], 0, ',', '.') . '</div></div>';
        $body .= '<div class="kpi-card"><div class="kpi-label">En Curso</div><div class="kpi-value" style="color:#d97706">' . number_format($summary['in_progress_records'], 0, ',', '.') . '</div></div>';
        $body .= '<div class="kpi-card"><div class="kpi-label">Total Horas Detenido</div><div class="kpi-value" style="color:#b91c1c">' . number_format($summary['total_hours'], 1, ',', '.') . ' h</div></div>';
        $body .= '<div class="kpi-card"><div class="kpi-label">Promedio Duración</div><div class="kpi-value">' . number_format($summary['avg_minutes'], 1, ',', '.') . ' min</div></div>';
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
    } elseif ($activeTab === 'sin-produccion') {
        $rows = $standbyReport['rows'];
        if (empty($rows)) {
            $body .= '<div class="erp-prod-empty text-center">No se encontraron eventos de máquina sin producción para los filtros seleccionados.</div>';
        } else {
            $body .= '<div class="erp-prod-table-wrap"><table class="erp-prod-table table-compact">';
            $body .= '<thead><tr>';
            $body .= '<th>Fecha</th><th>Hora Inicio</th><th>Hora Fin</th><th class="text-right">Duración</th><th>Motivo Sin Producción</th><th>Máquina</th><th>Operario</th><th class="text-center">Estado</th><th>Observaciones</th>';
            $body .= '</tr></thead><tbody>';
            foreach ($rows as $r) {
                $statusBadge = $r['is_active']
                    ? '<span style="background:#fef3c7;color:#b45309;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:800;display:inline-flex;align-items:center;gap:4px"><span style="width:7px;height:7px;border-radius:50%;background:#d97706;display:inline-block"></span> En curso</span>'
                    : '<span style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700">Terminado</span>';

                $durColor = $r['is_active'] ? '#b45309' : '#0f766e';

                $body .= '<tr>';
                $body .= '<td class="text-center">' . h($r['date']) . '</td>';
                $body .= '<td class="text-center" style="font-size:11.5px;font-weight:600">' . h($r['start_time']) . '</td>';
                $body .= '<td class="text-center" style="font-size:11.5px;font-weight:600">' . h($r['end_time']) . '</td>';
                $body .= '<td class="text-right" style="font-weight:700;color:' . $durColor . '">' . h($r['duration_str']) . '</td>';
                $body .= '<td><strong style="color:#0f172a">' . h($r['reason']) . '</strong></td>';
                $body .= '<td class="text-center"><strong>' . h($r['machine_name']) . '</strong></td>';
                $body .= '<td>' . h($r['operator_name']) . '</td>';
                $body .= '<td class="text-center">' . $statusBadge . '</td>';
                $body .= '<td style="font-size:11.5px;color:#334155">' . (!empty($r['comments']) ? h($r['comments']) : '<span style="color:#94a3b8">-</span>') . '</td>';
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
    if ($tab === 'sin-produccion' || $tab === 'standby') {
        unibagOutputMachineStandbyExcel($service);
    } elseif ($tab === 'colacion') {
        unibagOutputColacionExcel($service);
    } elseif ($tab === 'setup' || $tab === 'cambio-configuracion') {
        unibagOutputCambioConfiguracionExcel($service);
    } else {
        unibagOutputDetencionExcel($service);
    }
}

