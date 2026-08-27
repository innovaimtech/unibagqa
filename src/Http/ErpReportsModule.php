<?php

declare(strict_types=1);

function unibagRenderErpDashboardPage(ReceptionService $service): void
{
    $currentArea = normalizeErpArea((string)($_SESSION['erp_area'] ?? 'ERP'));
    if ($currentArea === 'PRODUCTION') {
        redirectResponse('/production/shifts');
    }
    if ($currentArea === 'RECEPTION') {
        redirectResponse('/purchase-orders?status=active&supplier_type=NATIONAL');
    }
    unibagRenderProductionDashboardPage($service, true);
}

/**
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
            'filename' => 'dashboard-produccion-unidades-producidas.xls',
            'title' => 'Dashboard Producción - Unidades producidas por OT',
            'summary' => [
                ['label' => 'OTs con producción', 'value' => (string)count($rows)],
                ['label' => 'Unidades producidas', 'value' => number_format(array_sum(array_map(static fn(array $row): float => (float)($row['produced_units'] ?? 0), $rows)), 0, '.', '')],
                ['label' => 'Unidades pendientes', 'value' => number_format(array_sum(array_map(static fn(array $row): float => (float)($row['pending_units'] ?? 0), $rows)), 0, '.', '')],
            ],
            'columns' => ['OT', 'SKU final', 'Estado', 'Objetivo', 'Producidas', 'Pendientes', 'Avance %', 'Cajas'],
            'rows' => array_map(static function (array $row): array {
                return [
                    (string)($row['ot_code'] ?? ('OT #' . (int)($row['id'] ?? 0))),
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
            'filename' => 'dashboard-produccion-pendientes-por-ot.xls',
            'title' => 'Dashboard Producción - Pendientes por OT',
            'summary' => [
                ['label' => 'OTs con pendiente', 'value' => (string)count($rows)],
                ['label' => 'Pendiente total', 'value' => number_format(array_sum(array_map(static fn(array $row): float => (float)($row['pending_units'] ?? 0), $rows)), 0, '.', '')],
                ['label' => 'OTs activas', 'value' => (string)count(array_filter($rows, static fn(array $row): bool => in_array((string)($row['dashboard_status'] ?? ''), ['Con avance', 'En produccion', 'Pendiente', 'En corte'], true)))],
            ],
            'columns' => ['OT', 'SKU final', 'Estado', 'Objetivo', 'Producidas', 'Pendientes', 'Avance %'],
            'rows' => array_map(static function (array $row): array {
                return [
                    (string)($row['ot_code'] ?? ('OT #' . (int)($row['id'] ?? 0))),
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
            'filename' => 'dashboard-produccion-despachos-por-ot.xls',
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
            'filename' => 'dashboard-produccion-merma-por-ot.xls',
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
            'filename' => 'dashboard-produccion-kg-procesados-por-ot.xls',
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
            'filename' => 'dashboard-produccion-semielaboradas-por-ot.xls',
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

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $definition['filename'] . '"');
    header('Cache-Control: max-age=0');

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
    echo '</style></head><body>';
    echo '<div class="title">' . h((string)$definition['title']) . '</div>';
    echo '<div class="sub">Reporte descargado desde la ventana emergente del Panel ERP</div>';
    echo '<div class="meta"><strong>Filtro aplicado:</strong> ' . h((string)$definition['filter_label']) . '<br><strong>Generado:</strong> ' . h((string)$definition['generated_at']) . '</div>';
    echo '<table class="summary"><tr><th>Indicador</th><th>Valor</th></tr>';
    foreach ((array)$definition['summary'] as $summaryRow) {
        echo '<tr><td>' . h((string)($summaryRow['label'] ?? '')) . '</td><td>' . h((string)($summaryRow['value'] ?? '')) . '</td></tr>';
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
            echo '<td>' . h((string)$cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;
}

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

    $filename = 'informe-ocupacion-bodegas-' . (new DateTimeImmutable('now'))->format('Ymd-His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    echo '<html><head><meta charset="UTF-8"><style>';
    echo 'body{font-family:Arial,sans-serif;color:#0f172a}';
    echo '.title{font-size:22px;font-weight:700;color:#0f172a}';
    echo '.sub{font-size:12px;color:#475569;margin-bottom:14px}';
    echo '.meta{margin:8px 0 18px 0;font-size:12px;color:#334155}';
    echo '.summary,.report,.detail{border-collapse:collapse;width:100%;margin-bottom:18px}';
    echo '.summary td,.summary th,.report td,.report th,.detail td,.detail th{border:1px solid #cbd5e1;padding:8px 10px}';
    echo '.summary th,.report th,.detail th{background:#0f172a;color:#fff;text-align:left;font-size:12px}';
    echo '.report tr:nth-child(even) td,.detail tr:nth-child(even) td{background:#f8fafc}';
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
    echo '<tr><td>Unidades en stock (equivalentes)</td><td>' . h(number_format($totalStock, 0, '.', ',')) . '</td></tr>';
    echo '<tr><td>Rollos totales</td><td>' . h(number_format($totalRolls, 0, '.', ',')) . '</td></tr>';
    echo '<tr><td>Cajas totales</td><td>' . h(number_format($totalBoxes, 0, '.', ',')) . '</td></tr>';
    echo '<tr><td>Pallets totales</td><td>' . h(number_format($totalPallets, 0, '.', ',')) . '</td></tr>';
    echo '<tr><td>Peso total en rollos</td><td>' . h(number_format($totalWeightKg, 3, '.', ',')) . ' kg</td></tr>';
    echo '<tr><td>Ocupación promedio</td><td>' . ($avgOccupancy !== null ? h(number_format($avgOccupancy, 2, '.', '') . '%') : 'Sin capacidad configurada') . '</td></tr>';
    echo '<tr><td>Ocupación máxima</td><td>' . ($maxOccupancy !== null ? h(number_format($maxOccupancy, 2, '.', '') . '%') : 'Sin capacidad configurada') . '</td></tr>';
    echo '</table>';

    echo '<div class="section">Resumen por bodega</div>';
    echo '<table class="report"><tr><th>Código</th><th>Nombre</th><th>Stock (unid.)</th><th>Rollos</th><th>Cajas</th><th>Pallets</th><th>Peso rollos (kg)</th><th>Ocupación</th><th>Cap. pallets</th><th>Cap. unidades</th></tr>';
    foreach ($filteredWarehouses as $summaryRow) {
        $occValue = ($summaryRow['occupancy_percent'] ?? null) !== null ? number_format((float)$summaryRow['occupancy_percent'], 2, '.', '') . '%' : 'Sin capacidad';
        $capPallets = (int)($summaryRow['capacity_pallets'] ?? 0) > 0 ? (string)(int)$summaryRow['capacity_pallets'] : 'Sin configurar';
        $capUnits = (float)($summaryRow['capacity_units_total'] ?? 0) > 0 ? number_format((float)$summaryRow['capacity_units_total'], 0, '.', ',') : 'Sin configurar';
        echo '<tr>';
        echo '<td>' . h((string)($summaryRow['warehouse_code'] ?? '')) . '</td>';
        echo '<td>' . h((string)($summaryRow['warehouse_name'] ?? '')) . '</td>';
        echo '<td>' . h(number_format((float)($summaryRow['stock_units_total'] ?? 0), 0, '.', ',')) . '</td>';
        echo '<td>' . h(number_format((int)($summaryRow['rolls_count'] ?? 0), 0, '.', ',')) . '</td>';
        echo '<td>' . h(number_format((int)($summaryRow['boxes_count'] ?? 0), 0, '.', ',')) . '</td>';
        echo '<td>' . h(number_format((int)($summaryRow['pallets_count'] ?? 0), 0, '.', ',')) . '</td>';
        echo '<td>' . h(number_format((float)($summaryRow['total_weight_kg'] ?? 0), 3, '.', ',')) . '</td>';
        echo '<td>' . h($occValue) . '</td>';
        echo '<td>' . h($capPallets) . '</td>';
        echo '<td>' . h($capUnits) . '</td>';
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
        echo '<tr><td>Unidades en stock</td><td>' . h(number_format((float)($summaryRow['stock_units_total'] ?? 0), 0, '.', ',')) . '</td></tr>';
        echo '<tr><td>Ocupación</td><td>' . h($occValue) . '</td></tr>';
        echo '<tr><td>Rollos</td><td>' . h(number_format((int)($summaryRow['rolls_count'] ?? 0), 0, '.', ',')) . '</td></tr>';
        echo '<tr><td>Cajas</td><td>' . h(number_format((int)($summaryRow['boxes_count'] ?? 0), 0, '.', ',')) . '</td></tr>';
        echo '<tr><td>Pallets</td><td>' . h(number_format((int)($summaryRow['pallets_count'] ?? 0), 0, '.', ',')) . '</td></tr>';
        echo '<tr><td>Peso en rollos</td><td>' . h(number_format((float)($summaryRow['total_weight_kg'] ?? 0), 3, '.', ',')) . ' kg</td></tr>';
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
    exit;
}

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
            $tableHtml = '<div class="erp-prod-table-wrap"><table class="erp-prod-table"><thead><tr><th>OT</th><th>Estado</th><th>Objetivo</th><th>Producidas</th><th>Pendientes</th><th>Avance</th></tr></thead><tbody>';
            foreach ($previewRows as $row) {
                $tableHtml .= '<tr>';
                $tableHtml .= '<td><div class="erp-prod-code">' . h((string)($row['ot_code'] ?? ('OT #' . (int)($row['id'] ?? 0)))) . '</div><div class="erp-prod-muted">' . h((string)($row['sku_final'] ?? '-')) . '</div></td>';
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
            $tableHtml = '<div class="erp-prod-table-wrap"><table class="erp-prod-table"><thead><tr><th>OT</th><th>Estado</th><th>Objetivo</th><th>Producidas</th><th>Pendientes</th><th>Avance</th></tr></thead><tbody>';
            foreach ($previewRows as $row) {
                $tableHtml .= '<tr>';
                $tableHtml .= '<td><div class="erp-prod-code">' . h((string)($row['ot_code'] ?? ('OT #' . (int)($row['id'] ?? 0)))) . '</div><div class="erp-prod-muted">' . h((string)($row['sku_final'] ?? '-')) . '</div></td>';
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
        .erp-prod-title{font-size:30px;font-weight:800;line-height:1.1;margin-bottom:8px}
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
        $body .= '<div class="erp-prod-hero"><div class="erp-prod-hero-top"><div><div class="erp-prod-title">Dashboard de Producción</div><div class="erp-prod-subtitle">Vista ejecutiva de fabricación, semielaborados, despacho, ocupación de bodegas y merma para seguimiento diario del negocio.</div><div class="erp-prod-badges"><div class="erp-prod-badge">Filtro aplicado: ' . h($activeFilterLabel) . '</div><div class="erp-prod-badge">Modo: ' . h($defaultFilterType === 'range' ? 'Rango de fechas' : 'Período 26 al 25') . '</div><div class="erp-prod-badge">Ocupación máxima: ' . h(number_format($bestOccupancy, 2, '.', '')) . '%</div></div></div><div class="erp-prod-hero-actions"><a class="btn secondary" href="/">Volver al panel ERP</a></div></div></div>';
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

function unibagRenderInventoryReportsPage(ReceptionService $service): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $inventoryCounts = $service->listInventoryCounts(200);
    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Informe de inventario</div>
          <div class="muted">Histórico de inventarios realizados por bodega y acceso a su Excel.</div>
        </div>
        <a class="btn secondary" href="/">Volver al panel ERP</a>
      </div>';

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Inventarios realizados</div>';
    $body .= '<table><thead><tr><th>Fecha</th><th>Bodega</th><th>Nombre bodega</th><th>SKUs</th><th>Sistema</th><th>Fisico</th><th>Dif</th><th>Realizado por</th><th></th></tr></thead><tbody>';
    foreach ($inventoryCounts as $inventoryCount) {
        $inventoryId = (int)($inventoryCount['id'] ?? 0);
        $body .= '<tr>';
        $body .= '<td>' . h((string)($inventoryCount['created_at'] ?? '')) . '</td>';
        $body .= '<td>' . h((string)($inventoryCount['warehouse_code'] ?? '')) . '</td>';
        $body .= '<td>' . h((string)($inventoryCount['warehouse_name'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($inventoryCount['total_skus'] ?? '0')) . '</td>';
        $body .= '<td>' . h(number_format((float)($inventoryCount['total_system_qty'] ?? 0), 3, '.', '')) . '</td>';
        $body .= '<td>' . h(number_format((float)($inventoryCount['total_physical_qty'] ?? 0), 3, '.', '')) . '</td>';
        $body .= '<td>' . h(number_format((float)($inventoryCount['total_diff_qty'] ?? 0), 3, '.', '')) . '</td>';
        $body .= '<td>' . h((string)($inventoryCount['created_by'] ?? '-')) . '</td>';
        $body .= '<td><div class="row"><a class="btn secondary" href="/reports/inventory/' . $inventoryId . '">Ver</a><a class="btn secondary" href="/reports/inventory/' . $inventoryId . '/excel">Descargar Excel</a></div></td>';
        $body .= '</tr>';
    }
    if ($inventoryCounts === []) {
        $body .= '<tr><td colspan="9" class="muted">Aún no hay inventarios realizados.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    render('Informe inventario', $body);
}

function unibagOutputInventoryReportExcel(ReceptionService $service, int $inventoryCountId): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $inventoryCount = $service->getInventoryCount($inventoryCountId);
    if ($inventoryCount === null) {
        render('No encontrado', '<div class="card">Inventario no encontrado.</div>');
        exit;
    }
    $rows = $service->listInventoryCountItems($inventoryCountId);
    outputInventoryCountDetailExcel(
        'informe-inventario-bodega-' . (int)($inventoryCount['warehouse_code'] ?? 0) . '-registro-' . $inventoryCountId . '.xls',
        $rows,
        $inventoryCount
    );
}

function unibagRenderInventoryReportDetailPage(ReceptionService $service, int $inventoryCountId): void
{
    if (!userCanAccessArea('ERP', sessionAreaPermissions())) {
        redirectResponse(firstAllowedAreaHome(sessionAreaPermissions()));
    }

    $inventoryCount = $service->getInventoryCount($inventoryCountId);
    if ($inventoryCount === null) {
        render('No encontrado', '<div class="card">Inventario no encontrado.</div>');
        return;
    }
    $items = $service->listInventoryCountItems($inventoryCountId);

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Inventario realizado</div>
          <div class="muted">Bodega ' . h((string)($inventoryCount['warehouse_code'] ?? '')) . ' · ' . h((string)($inventoryCount['warehouse_name'] ?? '')) . '</div>
        </div>
        <div class="row">
          <a class="btn secondary" href="/reports/inventory">Volver al informe</a>
          <a class="btn secondary" href="/reports/inventory/' . $inventoryCountId . '/excel">Descargar Excel</a>
        </div>
      </div>';

    $body .= '<div class="card" style="margin-bottom:12px"><div class="row">'
        . '<div style="flex:1;min-width:180px"><div class="muted">Fecha</div><div style="font-weight:800">' . h((string)($inventoryCount['created_at'] ?? '')) . '</div></div>'
        . '<div style="flex:1;min-width:180px"><div class="muted">Realizado por</div><div style="font-weight:800">' . h((string)($inventoryCount['created_by'] ?? '-')) . '</div></div>'
        . '<div style="flex:1;min-width:180px"><div class="muted">SKUs</div><div style="font-weight:800">' . h((string)($inventoryCount['total_skus'] ?? '0')) . '</div></div>'
        . '<div style="flex:1;min-width:180px"><div class="muted">Sistema</div><div style="font-weight:800">' . h(number_format((float)($inventoryCount['total_system_qty'] ?? 0), 3, '.', '')) . '</div></div>'
        . '<div style="flex:1;min-width:180px"><div class="muted">Fisico</div><div style="font-weight:800">' . h(number_format((float)($inventoryCount['total_physical_qty'] ?? 0), 3, '.', '')) . '</div></div>'
        . '<div style="flex:1;min-width:180px"><div class="muted">Diferencia</div><div style="font-weight:800">' . h(number_format((float)($inventoryCount['total_diff_qty'] ?? 0), 3, '.', '')) . '</div></div>'
        . '</div></div>';

    $body .= '<div class="card"><div style="font-weight:800;margin-bottom:8px">Detalle del inventario</div>';
    $body .= '<table><thead><tr><th>Numero</th><th>Articulo</th><th>Familia</th><th>Cod. color</th><th>Alto</th><th>Gramos</th><th>Metros</th><th>Unidad</th><th>Bodega</th><th>Sistema</th><th>Fisico</th><th>Dif</th></tr></thead><tbody>';
    foreach ($items as $item) {
        $body .= '<tr>';
        $body .= '<td>' . h((string)($item['sku_code'] ?? '')) . '</td>';
        $body .= '<td>' . h((string)($item['article_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($item['family_color'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($item['color_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($item['height_mm'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($item['grams'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($item['meters'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($item['unit_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($inventoryCount['warehouse_code'] ?? '')) . ' (' . h((string)($inventoryCount['warehouse_name'] ?? '-')) . ')</td>';
        $body .= '<td>' . h(number_format((float)($item['system_qty'] ?? 0), 3, '.', '')) . '</td>';
        $body .= '<td>' . h(number_format((float)($item['physical_qty'] ?? 0), 3, '.', '')) . '</td>';
        $body .= '<td>' . h(number_format((float)($item['diff_qty'] ?? 0), 3, '.', '')) . '</td>';
        $body .= '</tr>';
    }
    if ($items === []) {
        $body .= '<tr><td colspan="12" class="muted">Este inventario no registró stock disponible.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    render('Detalle inventario', $body);
}

function unibagRenderGraphicsPage(ReceptionService $service): void
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
    $dashboardFilterParams = isset($filters['filter_params']) && is_array($filters['filter_params']) ? $filters['filter_params'] : [];

    $kpis = $service->getProductionDashboardKpis($start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));
    $producedUnits = (float)($kpis['produced_units'] ?? 0);
    $pendingUnits = (float)($kpis['pending_units'] ?? 0);
    $dispatchedUnits = (float)($kpis['dispatched_units'] ?? 0);
    $semiRollCount = (int)($kpis['semi_rolls']['count'] ?? 0);
    $wastePercent = (float)($kpis['waste']['percent'] ?? 0);
    $processedKg = (float)($kpis['waste']['processed_kg'] ?? 0);
    $wasteKg = (float)($kpis['waste']['waste_kg'] ?? 0);
    $warehouses = is_array($kpis['warehouses'] ?? null) ? $kpis['warehouses'] : [];

    $topWarehouse = ['occupancy_percent' => 0.0, 'warehouse_code' => null, 'warehouse_name' => null];
    foreach ($warehouses as $row) {
        if (($row['occupancy_percent'] ?? null) === null) {
            continue;
        }
        if ((float)$row['occupancy_percent'] >= (float)$topWarehouse['occupancy_percent']) {
            $topWarehouse = $row;
        }
    }

    $periods = [];
    $anchor = DateTimeImmutable::createFromFormat('Y-m-d', $end->format('Y-m-01')) ?: $end;
    for ($i = 5; $i >= 0; $i--) {
        $periodMonth = $anchor->modify('-' . $i . ' month');
        $pStart = $periodMonth->modify('-1 month')->setDate(
            (int)$periodMonth->modify('-1 month')->format('Y'),
            (int)$periodMonth->modify('-1 month')->format('m'),
            26
        )->setTime(0, 0, 0);
        $pEnd = $periodMonth->setDate(
            (int)$periodMonth->format('Y'),
            (int)$periodMonth->format('m'),
            25
        )->setTime(23, 59, 59);
        $periodKpis = $service->getProductionDashboardKpis($pStart->format('Y-m-d H:i:s'), $pEnd->format('Y-m-d H:i:s'));
        $periods[] = [
            'label' => $pStart->format('M/y'),
            'produced' => round((float)($periodKpis['produced_units'] ?? 0), 0),
            'pending' => round((float)($periodKpis['pending_units'] ?? 0), 0),
            'dispatched' => round((float)($periodKpis['dispatched_units'] ?? 0), 0),
            'waste_percent' => round((float)($periodKpis['waste']['percent'] ?? 0), 2),
        ];
    }

    $warehouseOccRows = [];
    foreach (array_slice($warehouses, 0, 10) as $row) {
        $warehouseOccRows[] = [
            'label' => trim((string)($row['warehouse_code'] ?? '')),
            'value' => round((float)($row['occupancy_percent'] ?? 0), 2),
        ];
    }

    $formAction = '/reports/graphics';
    $hiddenInputs = '';
    if ($defaultFilterType !== 'range') {
        $hiddenInputs .= '<input type="hidden" name="start_date" value="' . h($rangeStartInput) . '">';
        $hiddenInputs .= '<input type="hidden" name="end_date" value="' . h($rangeEndInput) . '">';
    }
    if ($defaultFilterType !== 'period') {
        $hiddenInputs .= '<input type="hidden" name="period" value="' . h($periodYm) . '">';
    }

    $kpiLabels = ['Unidades producidas', 'Unidades pendientes', 'Unidades despachadas', 'Semielaboradas (rollos)', 'Merma (%)', 'Kg procesados'];
    $barValues = [
        $producedUnits,
        $pendingUnits,
        $dispatchedUnits,
        $semiRollCount,
        $wastePercent,
        $processedKg,
    ];
    $doughnutLabels = ['Producidas', 'Pendientes', 'Despachadas', 'Semielaboradas'];
    $doughnutValues = [
        $producedUnits,
        $pendingUnits,
        $dispatchedUnits,
        $semiRollCount,
    ];
    $barPayload = ['labels' => $kpiLabels, 'values' => $barValues];
    $doughnutPayload = ['labels' => $doughnutLabels, 'values' => $doughnutValues];
    $trendLabels = [];
    $trendProduced = [];
    $trendPending = [];
    $trendDispatched = [];
    foreach ($periods as $p) {
        $trendLabels[] = $p['label'];
        $trendProduced[] = $p['produced'];
        $trendPending[] = $p['pending'];
        $trendDispatched[] = $p['dispatched'];
    }
    $trendPayload = [
        'labels' => $trendLabels,
        'produced' => $trendProduced,
        'pending' => $trendPending,
        'dispatched' => $trendDispatched,
        'warehouse_labels' => array_column($warehouseOccRows, 'label'),
        'warehouse_values' => array_column($warehouseOccRows, 'value'),
    ];
    $defaultChart = isset($_GET['chart']) && in_array((string)$_GET['chart'], ['compact', 'compare', 'executive'], true) ? (string)$_GET['chart'] : 'compact';

    $body = '<style>
        .erp-graph-shell{display:flex;flex-direction:column;gap:18px}
        .erp-graph-toolbar{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}
        .erp-graph-field{min-width:220px;display:flex;flex-direction:column;gap:6px}
        .erp-graph-field.is-hidden{display:none}
        .erp-graph-field label{font-size:12px;font-weight:700;color:#475569}
        .erp-graph-field select,.erp-graph-field input{height:42px;border:1px solid #cbd5e1;border-radius:12px;padding:0 12px;background:#fff;box-sizing:border-box}
        .erp-graph-canvas-wrap{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:18px;box-shadow:0 10px 30px rgba(15,23,42,.06);height:460px;position:relative}
        .erp-graph-canvas-wrap.is-tall{height:640px}
        .erp-graph-canvas-wrap.is-short{height:360px}
        .erp-graph-canvas{height:100%!important;width:100%!important}
        .erp-graph-compact-head{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:16px}
        .erp-graph-compact-kpi{border:1px solid #e2e8f0;border-radius:16px;padding:14px 16px;background:#f8fafc}
        .erp-graph-compact-label{font-size:11px;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:.08em}
        .erp-graph-compact-value{font-size:22px;font-weight:800;color:#0f172a;margin-top:4px}
        .erp-graph-compact-note{font-size:12px;color:#64748b;margin-top:2px}
        .erp-graph-grid-2{display:grid;grid-template-columns:1.1fr 0.9fr;gap:16px}
        .erp-graph-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
        .erp-graph-section-title{font-size:14px;font-weight:800;color:#0f172a;margin:0 0 10px 0}
        @media (max-width: 1180px) {
            .erp-graph-grid-2,.erp-graph-grid-3{grid-template-columns:1fr}
            .erp-graph-compact-head{grid-template-columns:repeat(2,minmax(0,1fr))}
        }
        @media (max-width: 640px) {
            .erp-graph-compact-head{grid-template-columns:1fr}
        }
    </style>';
    $body .= '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>';

    $body .= '<div class="erp-graph-shell">';

    $chartOptions = [
        'compact' => 'Forma 1 · Compacto KPI',
        'compare' => 'Forma 2 · Comparativo múltiple',
        'executive' => 'Forma 3 · Dashboard ejecutivo',
    ];
    $chartSelectHtml = '';
    foreach ($chartOptions as $value => $label) {
        $chartSelectHtml .= '<option value="' . h($value) . '"' . ($defaultChart === $value ? ' selected' : '') . '>' . h($label) . '</option>';
    }

    $hiddenExtraInputs = '';
    if (!isset($_GET['chart']) || !is_string($_GET['chart'])) {
        $hiddenExtraInputs .= '<input type="hidden" name="chart" value="' . h($defaultChart) . '">';
    }

    $body .= '<div class="erp-graph-panel"><div class="erp-graph-panel-head"><div style="flex:1"><div class="erp-graph-panel-title">Filtros y forma del gráfico</div><div class="erp-graph-panel-sub">' . h($activeFilterLabel) . ' · Selecciona una de las 3 formas para ver cuál se ve mejor. Puedes colapsar este panel para ganar espacio vertical.</div></div><button type="button" class="erp-collapse-toggle" data-collapse-toggle aria-label="Colapsar sección"><span class="chev">▾</span></button></div><div class="erp-graph-panel-body"><div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap"><div><div class="erp-graph-panel-title">Gráficos</div><div class="erp-graph-panel-sub">' . h($activeFilterLabel) . '</div></div><form id="erp-graph-filter-form" method="get" action="' . h($formAction) . '" class="erp-graph-toolbar"><div class="erp-graph-field"><label for="filter_type_graph">Tipo de filtro</label><select id="filter_type_graph" name="filter_type"><option value="period"' . ($defaultFilterType === 'period' ? ' selected' : '') . '>Período 26 al 25</option><option value="range"' . ($defaultFilterType === 'range' ? ' selected' : '') . '>Rango de fechas</option></select></div><div class="erp-graph-field"><label for="chart_type_graph">Forma del gráfico</label><select id="chart_type_graph" name="chart">' . $chartSelectHtml . '</select></div><div class="erp-graph-field' . ($defaultFilterType === 'period' ? '' : ' is-hidden') . '" data-filter-group="period"><label for="period_graph">Período</label><input id="period_graph" type="month" name="period" value="' . h($periodYm) . '"' . ($defaultFilterType === 'period' ? '' : ' disabled') . '></div><div class="erp-graph-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range"><label for="start_date_graph">Fecha inicio</label><input id="start_date_graph" type="date" name="start_date" value="' . h($rangeStartInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '></div><div class="erp-graph-field' . ($defaultFilterType === 'range' ? '' : ' is-hidden') . '" data-filter-group="range"><label for="end_date_graph">Fecha final</label><input id="end_date_graph" type="date" name="end_date" value="' . h($rangeEndInput) . '"' . ($defaultFilterType === 'range' ? '' : ' disabled') . '></div><button type="submit" class="btn primary">Aplicar</button>' . $hiddenInputs . $hiddenExtraInputs . '</form></div></div></div>';

    $wasteKgRounded = (float)number_format($wasteKg, 3, '.', '');
    $processedKgRounded = (float)number_format($processedKg, 3, '.', '');

    // FORMA 1 · COMPACTO KPI
    $body .= '<div class="erp-graph-panel" data-chart-wrap="compact" ' . ($defaultChart === 'compact' ? '' : 'hidden') . '><div class="erp-graph-panel-head"><div><div class="erp-graph-panel-title">Forma 1 · Compacto KPI</div><div class="erp-graph-panel-sub">KPIs rápidos arriba y una combinación tendencia + ocupación en el mismo eje.</div></div><button type="button" class="erp-collapse-toggle" data-collapse-toggle aria-label="Colapsar sección"><span class="chev">▾</span></button></div><div class="erp-graph-panel-body">';
    $body .= '<div class="erp-graph-compact-head">';
    $body .= '<div class="erp-graph-compact-kpi"><div class="erp-graph-compact-label">Producción vs despacho</div><div class="erp-graph-compact-value">' . h(number_format($producedUnits, 0, '.', '')) . ' / ' . h(number_format($dispatchedUnits, 0, '.', '')) . '</div><div class="erp-graph-compact-note">Producidas / Despachadas</div></div>';
    $body .= '<div class="erp-graph-compact-kpi"><div class="erp-graph-compact-label">Pendientes</div><div class="erp-graph-compact-value">' . h(number_format($pendingUnits, 0, '.', '')) . '</div><div class="erp-graph-compact-note">Backlog actual</div></div>';
    $body .= '<div class="erp-graph-compact-kpi"><div class="erp-graph-compact-label">Merma</div><div class="erp-graph-compact-value">' . h(number_format($wastePercent, 2, '.', '')) . '%</div><div class="erp-graph-compact-note">' . h(number_format($wasteKgRounded, 3, '.', '')) . ' kg sobre ' . h(number_format($processedKgRounded, 3, '.', '')) . ' kg procesados</div></div>';
    $body .= '</div>';
    $body .= '<div class="erp-graph-canvas-wrap is-tall"><canvas id="erpChartCompact" class="erp-graph-canvas"></canvas></div>';
    $body .= '</div></div>';

    // FORMA 2 · COMPARATIVO MULTIPLE
    $body .= '<div class="erp-graph-panel" data-chart-wrap="compare" ' . ($defaultChart === 'compare' ? '' : 'hidden') . '><div class="erp-graph-panel-head"><div><div class="erp-graph-panel-title">Forma 2 · Comparativo múltiple</div><div class="erp-graph-panel-sub">Múltiples vistas pequeñas del mismo período para comparar dimensiones.</div></div><button type="button" class="erp-collapse-toggle" data-collapse-toggle aria-label="Colapsar sección"><span class="chev">▾</span></button></div><div class="erp-graph-panel-body">';
    $body .= '<div class="erp-graph-grid-3" style="margin-bottom:16px"><div><div class="erp-graph-section-title">KPIs período</div><div class="erp-graph-canvas-wrap is-short"><canvas id="erpChartCompareBar" class="erp-graph-canvas"></canvas></div></div><div><div class="erp-graph-section-title">Composición</div><div class="erp-graph-canvas-wrap is-short"><canvas id="erpChartCompareDoughnut" class="erp-graph-canvas"></canvas></div></div><div><div class="erp-graph-section-title">Ocupación bodegas</div><div class="erp-graph-canvas-wrap is-short"><canvas id="erpChartCompareWarehouse" class="erp-graph-canvas"></canvas></div></div></div>';
    $body .= '<div class="erp-graph-canvas-wrap is-tall"><canvas id="erpChartCompareTrend" class="erp-graph-canvas"></canvas></div>';
    $body .= '</div></div>';

    // FORMA 3 · DASHBOARD EJECUTIVO
    $body .= '<div class="erp-graph-panel" data-chart-wrap="executive" ' . ($defaultChart === 'executive' ? '' : 'hidden') . '><div class="erp-graph-panel-head"><div><div class="erp-graph-panel-title">Forma 3 · Dashboard ejecutivo</div><div class="erp-graph-panel-sub">Layout en 2 columnas con profundidad ejecutiva y ocupación grande al final.</div></div><button type="button" class="erp-collapse-toggle" data-collapse-toggle aria-label="Colapsar sección"><span class="chev">▾</span></button></div><div class="erp-graph-panel-body">';
    $body .= '<div class="erp-graph-grid-2" style="margin-bottom:16px"><div><div class="erp-graph-section-title">Tendencia producción (últimos 6 períodos)</div><div class="erp-graph-canvas-wrap is-tall"><canvas id="erpChartExecTrend" class="erp-graph-canvas"></canvas></div></div><div><div class="erp-graph-section-title">Detalle merma vs procesado</div><div class="erp-graph-canvas-wrap is-short" style="margin-bottom:16px"><canvas id="erpChartExecWaste" class="erp-graph-canvas"></canvas></div><div class="erp-graph-section-title">Semielaboradas vs pendientes</div><div class="erp-graph-canvas-wrap is-short"><canvas id="erpChartExecStack" class="erp-graph-canvas"></canvas></div></div></div>';
    $body .= '<div class="erp-graph-section-title">Ocupación por bodega</div><div class="erp-graph-canvas-wrap is-tall"><canvas id="erpChartExecWarehouse" class="erp-graph-canvas"></canvas></div>';
    $body .= '</div></div>';

    $barPayloadJson = json_encode($barPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $doughnutPayloadJson = json_encode($doughnutPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $trendPayloadJson = json_encode($trendPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $wastePayloadJson = json_encode([
        'labels' => ['Kg procesados', 'Kg merma'],
        'values' => [$processedKgRounded, $wasteKgRounded],
        'percent' => round($wastePercent, 2),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stackPayloadJson = json_encode([
        'labels' => ['Producción actual'],
        'pending' => [round($pendingUnits, 0)],
        'semi' => [$semiRollCount],
        'produced' => [round($producedUnits, 0)],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $warehousePayloadJson = json_encode([
        'labels' => array_column($warehouseOccRows, 'label'),
        'values' => array_column($warehouseOccRows, 'value'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $body .= '<script>
        (function () {
            var payloadBar = ' . $barPayloadJson . ';
            var payloadDoughnut = ' . $doughnutPayloadJson . ';
            var payloadTrend = ' . $trendPayloadJson . ';
            var payloadWaste = ' . $wastePayloadJson . ';
            var payloadStack = ' . $stackPayloadJson . ';
            var payloadWarehouse = ' . $warehousePayloadJson . ';
            var palette = ["#2563eb", "#0ea5e9", "#10b981", "#f59e0b", "#ef4444", "#6366f1"];
            var paletteDoughnut = ["#2563eb", "#0ea5e9", "#10b981", "#f59e0b"];

            function buildBarChart(ctx, payload, title) {
                return new Chart(ctx, {
                    type: "bar",
                    data: { labels: payload.labels, datasets: [{ label: "Valor", data: payload.values, backgroundColor: palette, borderRadius: 10 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, title: { display: true, text: title || "KPIs del período (valores brutos)" } }, scales: { y: { beginAtZero: true } } }
                });
            }
            function buildHorizontalBarChart(ctx, payload, title) {
                return new Chart(ctx, {
                    indexAxis: "y",
                    type: "bar",
                    data: { labels: payload.labels, datasets: [{ label: "% de ocupación", data: payload.values, backgroundColor: "rgba(245,158,11,.85)", borderRadius: 8 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, title: { display: true, text: title || "Ocupación por bodega (%)" } }, scales: { x: { beginAtZero: true, max: 100, title: { display: true, text: "% ocupación" } } } }
                });
            }
            function buildDoughnutChart(ctx, payload, title) {
                return new Chart(ctx, {
                    type: "doughnut",
                    data: { labels: payload.labels, datasets: [{ data: payload.values, backgroundColor: paletteDoughnut, borderColor: "#fff", borderWidth: 2 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" }, title: { display: true, text: title || "Composición" } } }
                });
            }
            function buildTrendChart(ctx, payload, title) {
                return new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: payload.labels,
                        datasets: [
                            { label: "Producidas", data: payload.produced, borderColor: palette[0], backgroundColor: "rgba(37,99,235,.15)", fill: true, tension: 0.25 },
                            { label: "Pendientes", data: payload.pending, borderColor: palette[1], backgroundColor: "rgba(14,165,233,.15)", fill: true, tension: 0.25 },
                            { label: "Despachadas", data: payload.dispatched, borderColor: palette[2], backgroundColor: "rgba(16,185,129,.15)", fill: true, tension: 0.25 }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: "index", intersect: false },
                        plugins: { legend: { position: "bottom" }, title: { display: true, text: title || "Tendencia últimos períodos" } },
                        scales: { y: { beginAtZero: true, title: { display: true, text: "Unidades" } } }
                    }
                });
            }
            function buildCompactCombo(ctx, payloadTrend, payloadWarehouse) {
                return new Chart(ctx, {
                    type: "line",
                    data: {
                        labels: payloadTrend.labels,
                        datasets: [
                            { label: "Producidas", type: "line", data: payloadTrend.produced, borderColor: palette[0], backgroundColor: "rgba(37,99,235,.12)", fill: true, tension: 0.25, order: 2 },
                            { label: "Pendientes", type: "line", data: payloadTrend.pending, borderColor: palette[1], backgroundColor: "rgba(14,165,233,.12)", fill: true, tension: 0.25, order: 2 },
                            { label: "Despachadas", type: "line", data: payloadTrend.dispatched, borderColor: palette[2], backgroundColor: "rgba(16,185,129,.12)", fill: true, tension: 0.25, order: 2 },
                            { label: "% ocupación bodegas", type: "bar", data: payloadWarehouse.values, backgroundColor: "rgba(245,158,11,.35)", yAxisID: "y1", order: 1 }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: "index", intersect: false },
                        plugins: { legend: { position: "bottom" }, title: { display: true, text: "Forma 1 · Compacto KPI (tendencia + ocupación)" } },
                        scales: {
                            x: { labels: payloadWarehouse.labels.length > payloadTrend.labels.length ? payloadWarehouse.labels : payloadTrend.labels },
                            y: { beginAtZero: true, position: "left", title: { display: true, text: "Unidades" } },
                            y1: { beginAtZero: true, position: "right", max: 100, grid: { drawOnChartArea: false }, title: { display: true, text: "% ocupación bodegas" } }
                        }
                    }
                });
            }
            function buildWasteBar(ctx, payload) {
                return new Chart(ctx, {
                    type: "bar",
                    data: { labels: payload.labels, datasets: [{ label: "Kg", data: payload.values, backgroundColor: ["#2563eb", "#ef4444"], borderRadius: 10 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, title: { display: true, text: "Control de merma (" + payload.percent + "%)" } }, scales: { y: { beginAtZero: true, title: { display: true, text: "Kg" } } } }
                });
            }
            function buildStackedStatus(ctx, payload) {
                return new Chart(ctx, {
                    type: "bar",
                    data: {
                        labels: payload.labels,
                        datasets: [
                            { label: "Producidas", data: payload.produced, backgroundColor: palette[2], stack: "flow", borderRadius: 4 },
                            { label: "Pendientes", data: payload.pending, backgroundColor: palette[1], stack: "flow", borderRadius: 4 },
                            { label: "Semielaboradas", data: payload.semi, backgroundColor: palette[3], stack: "flow", borderRadius: 4 }
                        ]
                    },
                    options: {
                        indexAxis: "y",
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: "bottom" }, title: { display: true, text: "Flujo (apilado)" } },
                        scales: { x: { stacked: true, beginAtZero: true }, y: { stacked: true } }
                    }
                });
            }

            var charts = {};
            var compactCtx = document.getElementById("erpChartCompact");
            charts.compact = compactCtx ? buildCompactCombo(compactCtx, payloadTrend, payloadWarehouse) : null;

            var compareBarCtx = document.getElementById("erpChartCompareBar");
            var compareDoughnutCtx = document.getElementById("erpChartCompareDoughnut");
            var compareWarehouseCtx = document.getElementById("erpChartCompareWarehouse");
            var compareTrendCtx = document.getElementById("erpChartCompareTrend");
            charts.compare = {
                bar: compareBarCtx ? buildBarChart(compareBarCtx, payloadBar, "KPIs del período") : null,
                doughnut: compareDoughnutCtx ? buildDoughnutChart(compareDoughnutCtx, payloadDoughnut, "Composición producción") : null,
                warehouse: compareWarehouseCtx ? buildHorizontalBarChart(compareWarehouseCtx, payloadWarehouse, "Ocupación por bodega (%)") : null,
                trend: compareTrendCtx ? buildTrendChart(compareTrendCtx, payloadTrend, "Tendencia últimos 6 períodos operativos") : null,
                resize: function () {
                    try { if (this.bar && this.bar.resize) this.bar.resize(); } catch (e) {}
                    try { if (this.doughnut && this.doughnut.resize) this.doughnut.resize(); } catch (e) {}
                    try { if (this.warehouse && this.warehouse.resize) this.warehouse.resize(); } catch (e) {}
                    try { if (this.trend && this.trend.resize) this.trend.resize(); } catch (e) {}
                }
            };

            var execTrendCtx = document.getElementById("erpChartExecTrend");
            var execWasteCtx = document.getElementById("erpChartExecWaste");
            var execStackCtx = document.getElementById("erpChartExecStack");
            var execWarehouseCtx = document.getElementById("erpChartExecWarehouse");
            charts.executive = {
                trend: execTrendCtx ? buildTrendChart(execTrendCtx, payloadTrend, "Tendencia producción (líneas)") : null,
                waste: execWasteCtx ? buildWasteBar(execWasteCtx, payloadWaste) : null,
                stack: execStackCtx ? buildStackedStatus(execStackCtx, payloadStack) : null,
                warehouse: execWarehouseCtx ? buildHorizontalBarChart(execWarehouseCtx, payloadWarehouse, "Ocupación por bodega (%)") : null,
                resize: function () {
                    try { if (this.trend && this.trend.resize) this.trend.resize(); } catch (e) {}
                    try { if (this.waste && this.waste.resize) this.waste.resize(); } catch (e) {}
                    try { if (this.stack && this.stack.resize) this.stack.resize(); } catch (e) {}
                    try { if (this.warehouse && this.warehouse.resize) this.warehouse.resize(); } catch (e) {}
                }
            };

            function setChart(name) {
                if (!charts[name]) return;
                document.querySelectorAll("[data-chart-wrap]").forEach(function (wrap) {
                    wrap.hidden = wrap.getAttribute("data-chart-wrap") !== name;
                    if (!wrap.hidden) {
                        setTimeout(function () {
                            try {
                                if (charts[name] && charts[name].resize) charts[name].resize();
                            } catch (e) {}
                        }, 20);
                    }
                });
                var chartSelect = document.getElementById("chart_type_graph");
                if (chartSelect && chartSelect.value !== name) chartSelect.value = name;
            }

            function attachCollapsibleControls(root) {
                if (!root) root = document;
                root.querySelectorAll("[data-collapse-toggle]").forEach(function (btn) {
                    if (btn.getAttribute("data-collapse-bound") === "1") return;
                    btn.setAttribute("data-collapse-bound", "1");
                    btn.addEventListener("click", function () {
                        var panel = btn.closest(".erp-prod-panel, .erp-graph-panel");
                        if (!panel) return;
                        var willCollapse = !panel.classList.contains("is-collapsed");
                        panel.classList.toggle("is-collapsed");
                        if (willCollapse) return;
                        setTimeout(function () {
                            var wrap = panel.querySelector("[data-chart-wrap]");
                            if (wrap && !wrap.hidden) {
                                var name = wrap.getAttribute("data-chart-wrap");
                                try {
                                    if (charts[name] && charts[name].resize) charts[name].resize();
                                } catch (e) {}
                            } else {
                                try {
                                    Object.keys(charts).forEach(function (key) {
                                        if (charts[key] && charts[key].resize && typeof charts[key].resize === "function") charts[key].resize();
                                    });
                                } catch (e) {}
                            }
                        }, 20);
                    });
                    btn.addEventListener("keydown", function (event) {
                        if (!event) return;
                        if (event.key !== "Enter" && event.key !== " ") return;
                        event.preventDefault();
                        btn.click();
                    });
                });
            }

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

            attachCollapsibleControls(document);

            setChart("' . h($defaultChart) . '");
        })();
    </script>';

    $body .= '</div>';

    render('Gráficos', $body);
}

function handleErpReportRoutes(string $path, string $method, ReceptionService $service): bool
{
    if ($path === '/' && $method === 'GET') {
        unibagRenderErpDashboardPage($service);
        return true;
    }

    if ($path === '/reports/graphics' && $method === 'GET') {
        unibagRenderGraphicsPage($service);
        return true;
    }

    if ($path === '/reports/production-dashboard' && $method === 'GET') {
        unibagRenderProductionDashboardPage($service);
        return true;
    }

    if ($path === '/reports/production-dashboard/excel' && $method === 'GET') {
        unibagOutputProductionDashboardMetricExcel($service);
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

    return false;
}
