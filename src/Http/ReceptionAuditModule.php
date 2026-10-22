<?php
declare(strict_types=1);

/**
 * Módulo de Auditoría de Recepción por Contenedor
 * UNIBAG SpA - Sistema de Gestión de Operaciones, Metrología & ERP
 *
 * Enfoque: Auditoría pericial por unidad de contenedor (no por acumulación global de productos),
 * diferenciando pesajes digitales capturados en balanza TRZ de registros administrativos históricos.
 */

require_once __DIR__ . '/../SimpleXlsx.php';

if (!function_exists('h')) {
    function h(string $v): string {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('receptionModeLabel')) {
    function receptionModeLabel(string $mode): string {
        return strtoupper(trim($mode)) === 'WEIGHT' ? 'Por peso' : 'Por unidades';
    }
}

if (!function_exists('buildReceptionSpec')) {
    function buildReceptionSpec(array $line): string {
        $spec = [];
        $grams = $line['grams'] ?? $line['microns'] ?? null;
        if ($grams !== null && $grams !== '') { $spec[] = 'Gramos ' . (int)$grams; }
        if (($line['width_mm'] ?? null) !== null) { $spec[] = 'Ancho ' . (int)$line['width_mm']; }
        if (($line['color'] ?? '') !== '') { $spec[] = 'Color ' . htmlspecialchars((string)$line['color'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
        if (($line['meters'] ?? null) !== null) { $spec[] = 'ML ' . htmlspecialchars((string)$line['meters'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
        return $spec === [] ? '-' : implode(' · ', $spec);
    }
}

/**
 * Renderiza el listado principal de Auditoría de Recepción por Contenedor
 */
function handleReceptionAuditList(ReceptionService $service): void
{
    $statusFilter = isset($_GET['status']) ? trim((string)$_GET['status']) : 'all';
    if (!in_array($statusFilter, ['all', 'active', 'complete'], true)) {
        $statusFilter = 'all';
    }
    $dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
    $dateTo = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';
    $poCode = isset($_GET['po_code']) ? trim((string)$_GET['po_code']) : '';
    $containerCode = isset($_GET['container_code']) ? trim((string)$_GET['container_code']) : '';
    $supplierQuery = isset($_GET['supplier']) ? trim((string)$_GET['supplier']) : '';
    $viewFilter = isset($_GET['view_filter']) ? trim((string)$_GET['view_filter']) : 'all'; 
    // view_filter: all, scale_only, critical_only, warning_only, conforming_only, erp_pending, active_only, admin_only
    $isExport = isset($_GET['export']) && $_GET['export'] === 'excel';

    $perPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [15, 25, 50, 100, 200], true)
        ? (int)$_GET['per_page']
        : 25;
    $page = max(1, (int)($_GET['page'] ?? 1));

    // 1. Obtener contenedores según filtros base
    $containers = [];
    if ($statusFilter === 'active') {
        $containers = $service->listImportContainers(
            null, null, 'active', 500,
            $dateFrom !== '' ? $dateFrom : null,
            $dateTo !== '' ? $dateTo : null,
            $poCode !== '' ? $poCode : null,
            null,
            $containerCode !== '' ? $containerCode : null,
            null, 0
        );
    } elseif ($statusFilter === 'complete') {
        $containers = $service->listImportContainers(
            null, null, 'complete', 500,
            $dateFrom !== '' ? $dateFrom : null,
            $dateTo !== '' ? $dateTo : null,
            $poCode !== '' ? $poCode : null,
            null,
            $containerCode !== '' ? $containerCode : null,
            null, 0
        );
    } else {
        $act = $service->listImportContainers(
            null, null, 'active', 500,
            $dateFrom !== '' ? $dateFrom : null,
            $dateTo !== '' ? $dateTo : null,
            $poCode !== '' ? $poCode : null,
            null,
            $containerCode !== '' ? $containerCode : null,
            null, 0
        );
        $cmp = $service->listImportContainers(
            null, null, 'complete', 500,
            $dateFrom !== '' ? $dateFrom : null,
            $dateTo !== '' ? $dateTo : null,
            $poCode !== '' ? $poCode : null,
            null,
            $containerCode !== '' ? $containerCode : null,
            null, 0
        );
        $byId = [];
        foreach (array_merge($act, $cmp) as $c) {
            $byId[(int)$c['id']] = $c;
        }
        $containers = array_values($byId);
    }

    usort($containers, static fn(array $a, array $b): int => ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0)));

    $cIds = array_column($containers, 'id');
    $suppData = [];
    $scaleData = [];
    if ($cIds !== []) {
        $cIn = implode(',', array_map('intval', $cIds));
        try {
            $suppStmt = Db::erpPdo()->query("
                SELECT sci.sord_id AS container_id,
                       GROUP_CONCAT(DISTINCT s.supp_company SEPARATOR ' / ') AS supplier_names,
                       GROUP_CONCAT(DISTINCT c.country_name SEPARATOR ' / ') AS country_names
                FROM supplier_contenedor_items sci
                JOIN supplier_order_items soi ON soi.id = sci.sord_pos_id
                JOIN supplier_order so ON so.id = soi.sord_id
                JOIN supplier s ON s.id = so.sord_supplier_id
                LEFT JOIN country c ON c.id = s.supp_countryid
                WHERE sci.sord_id IN ($cIn)
                GROUP BY sci.sord_id
            ");
            if ($suppStmt) {
                foreach ($suppStmt->fetchAll(PDO::FETCH_ASSOC) as $sRow) {
                    $suppData[(int)$sRow['container_id']] = $sRow;
                }
            }
        } catch (\Throwable) {}

        try {
            $scaleStmt = Db::pdo()->query("
                SELECT import_container_id,
                       COUNT(*) AS rolls_weighed,
                       COALESCE(SUM(weight_kg), 0) AS scale_weight_kg,
                       MIN(created_at) AS first_weigh,
                       MAX(created_at) AS last_weigh
                FROM rolls
                WHERE import_container_id IN ($cIn)
                GROUP BY import_container_id
            ");
            if ($scaleStmt) {
                foreach ($scaleStmt->fetchAll(PDO::FETCH_ASSOC) as $scRow) {
                    $scaleData[(int)$scRow['import_container_id']] = $scRow;
                }
            }
        } catch (\Throwable) {}
    }

    // 2. Procesar auditoría individual de cada contenedor
    $allContainerRows = [];
    $summary = [
        'total_containers' => 0,
        'active_containers' => 0,
        'complete_containers' => 0,
        'containers_with_scale' => 0,
        'containers_admin_erp' => 0,
        'ordered_weight_kg_scale' => 0.0,
        'received_weight_kg_scale' => 0.0,
        'scale_rolls_weighed' => 0,
        'scale_weight_kg_total' => 0.0,
        'critical_containers' => 0,
        'warning_containers' => 0,
        'conforming_containers' => 0,
        'erp_closed' => 0,
        'erp_pending' => 0,
    ];

    foreach ($containers as $c) {
        $cId = (int)$c['id'];
        $suppInfo = $suppData[$cId] ?? ['supplier_names' => '-', 'country_names' => '-'];
        $scaleInfo = $scaleData[$cId] ?? ['rolls_weighed' => 0, 'scale_weight_kg' => 0.0, 'first_weigh' => null, 'last_weigh' => null];

        $suppNames = trim((string)($suppInfo['supplier_names'] ?? '-'));
        $countryNames = trim((string)($suppInfo['country_names'] ?? '-'));
        if ($supplierQuery !== '' && stripos($suppNames, $supplierQuery) === false && stripos($countryNames, $supplierQuery) === false) {
            continue;
        }

        $ordRolls = round((float)($c['ordered_rolls'] ?? 0), 3);
        $erpRecRolls = round((float)($c['received_rolls'] ?? 0), 3);
        $ordWeight = round((float)($c['ordered_weight_kg'] ?? 0), 3);
        $erpRecWeight = round((float)($c['received_weight_kg'] ?? 0), 3);
        $erpComplete = (bool)($c['erp_is_complete'] ?? false);

        $scaleRollsWeighed = (int)($scaleInfo['rolls_weighed'] ?? 0);
        $scaleWeightKg = round((float)($scaleInfo['scale_weight_kg'] ?? 0), 3);
        $hasScale = ($scaleRollsWeighed > 0);

        if ($hasScale) {
            $physicalRolls = (float)$scaleRollsWeighed;
            $physicalWeight = $scaleWeightKg;
            $qtyDiff = round($physicalRolls - $ordRolls, 3);
            $weightDiff = round($physicalWeight - $ordWeight, 3);
            $weightDiffPct = $ordWeight > 0.0001 ? round(($weightDiff / $ordWeight) * 100, 2) : 0.0;

            $absWeightDiffPct = abs($weightDiffPct);
            $absQtyDiff = abs($qtyDiff);

            // Tolerancias Metrológicas:
            // ≤ 1.5%: Conforme (Tolerancia estricta)
            // 1.5% - 3.0%: Observado (Tolerancia comercial aceptable)
            // > 3.0% o discrepancia en bultos: Crítico
            $isCritical = ($absQtyDiff >= 1.0 || $absWeightDiffPct >= 3.0);
            $isWarning = (!$isCritical && $absWeightDiffPct >= 1.5);
            $isConforming = (!$isCritical && !$isWarning);
            $verdictType = $isCritical ? 'CRITICAL' : ($isWarning ? 'WARNING' : 'CONFORMING');
        } else {
            // Contenedor histórico sin pesajes digitales en balanza TRZ
            $physicalRolls = $erpRecRolls;
            $physicalWeight = $erpRecWeight;
            $qtyDiff = round($physicalRolls - $ordRolls, 3);
            $weightDiff = 0.0;
            $weightDiffPct = 0.0;
            $isCritical = false;
            $isWarning = false;
            $isConforming = false;
            $verdictType = 'ADMIN_ERP';
        }

        $c['supplier_names'] = $suppNames;
        $c['country_names'] = $countryNames;
        $c['has_scale'] = $hasScale;
        $c['scale_rolls_weighed'] = $scaleRollsWeighed;
        $c['scale_weight_kg'] = $scaleWeightKg;
        $c['physical_rolls'] = $physicalRolls;
        $c['physical_weight'] = $physicalWeight;
        $c['first_weigh'] = $scaleInfo['first_weigh'] ?? null;
        $c['last_weigh'] = $scaleInfo['last_weigh'] ?? null;
        $c['qty_diff'] = $qtyDiff;
        $c['weight_diff'] = $weightDiff;
        $c['weight_diff_pct'] = $weightDiffPct;
        $c['is_critical'] = $isCritical;
        $c['is_warning'] = $isWarning;
        $c['is_conforming'] = $isConforming;
        $c['verdict_type'] = $verdictType;

        $allContainerRows[] = $c;

        // Métricas de resumen calculadas estrictamente por contenedor
        $summary['total_containers']++;
        if ((string)($c['status'] ?? '') === 'COMPLETE') {
            $summary['complete_containers']++;
        } else {
            $summary['active_containers']++;
        }

        if ($hasScale) {
            $summary['containers_with_scale']++;
            $summary['ordered_weight_kg_scale'] += $ordWeight;
            $summary['received_weight_kg_scale'] += $scaleWeightKg;
            $summary['scale_rolls_weighed'] += $scaleRollsWeighed;
            $summary['scale_weight_kg_total'] += $scaleWeightKg;

            if ($isCritical) {
                $summary['critical_containers']++;
            } elseif ($isWarning) {
                $summary['warning_containers']++;
            } else {
                $summary['conforming_containers']++;
            }
        } else {
            $summary['containers_admin_erp']++;
        }

        if ($erpComplete) {
            $summary['erp_closed']++;
        } else {
            $summary['erp_pending']++;
        }
    }

    // 3. Aplicar Filtro de Vista Rápida
    $filteredRows = [];
    foreach ($allContainerRows as $row) {
        if ($viewFilter === 'scale_only' && !$row['has_scale']) continue;
        if ($viewFilter === 'critical_only' && !$row['is_critical']) continue;
        if ($viewFilter === 'warning_only' && !$row['is_warning']) continue;
        if ($viewFilter === 'conforming_only' && !$row['is_conforming']) continue;
        if ($viewFilter === 'erp_pending' && (bool)($row['erp_is_complete'] ?? false)) continue;
        if ($viewFilter === 'active_only' && (string)($row['status'] ?? '') === 'COMPLETE') continue;
        if ($viewFilter === 'admin_only' && $row['has_scale']) continue;
        $filteredRows[] = $row;
    }

    // 4. Indicadores Ejecutivos Calculados por Contenedor
    $conformancePct = $summary['containers_with_scale'] > 0
        ? round(($summary['conforming_containers'] / $summary['containers_with_scale']) * 100, 1)
        : 100.0;

    $erpClosePct = $summary['total_containers'] > 0
        ? round(($summary['erp_closed'] / $summary['total_containers']) * 100, 1)
        : 0.0;

    // 5. Exportación a Excel Profesional
    if ($isExport) {
        $filename = 'auditoria_recepcion_por_contenedor_' . date('Ymd_His') . '.xlsx';
        $exportHtml = '<!doctype html><html><head><meta charset="utf-8"></head><body>';
        $exportHtml .= '<h2>INFORME OFICIAL DE AUDITORÍA DE RECEPCIÓN POR CONTENEDOR - UNIBAG SPA</h2>';
        $exportHtml .= '<p><strong>Fecha Emisión:</strong> ' . date('d/m/Y H:i') . ' | <strong>Total Contenedores Evaluados:</strong> ' . count($filteredRows) . '</p>';
        $exportHtml .= '<p><strong>Contenedores con Balanza Digital TRZ:</strong> ' . $summary['containers_with_scale'] . ' | <strong>Tasa de Conformidad:</strong> ' . $conformancePct . '% | <strong>Desviaciones Críticas:</strong> ' . $summary['critical_containers'] . '</p>';
        $exportHtml .= '<table border="1"><thead><tr style="background:#0f172a;color:#ffffff;font-weight:bold;text-align:center">';
        $exportHtml .= '<th>ID</th><th>Contenedor</th><th>Bill of Lading</th><th>Buque</th><th>OCs</th><th>Proveedor</th><th>País</th><th>Estado Operativo</th>';
        $exportHtml .= '<th>Bultos Doc</th><th>Bultos Físicos</th><th>Dif Bultos</th>';
        $exportHtml .= '<th>Kilos Doc (kg)</th><th>Kilos Balanza (kg)</th><th>Dif Kilos (kg)</th><th>Desviación Balanza (%)</th>';
        $exportHtml .= '<th>Bobinas Báscula TRZ</th><th>Dictamen Auditoría</th><th>Cierre ERP</th><th>ETA Planta</th><th>Fecha Registro</th>';
        $exportHtml .= '</tr></thead><tbody>';

        foreach ($filteredRows as $row) {
            $dictLabel = match($row['verdict_type']) {
                'CRITICAL' => 'DESVIACIÓN CRÍTICA',
                'WARNING' => 'EN OBSERVACIÓN (TOLERANCIA)',
                'CONFORMING' => 'CONFORME (≤ 1.5%)',
                default => 'REGISTRO HISTÓRICO ERP',
            };
            $erpLabel = (bool)($row['erp_is_complete'] ?? false) ? 'CERRADO ERP' : 'PENDIENTE ERP';
            $exportHtml .= '<tr>';
            $exportHtml .= '<td>' . (int)$row['id'] . '</td>';
            $exportHtml .= '<td>' . h((string)($row['container_code'] ?: ('#' . $row['id']))) . '</td>';
            $exportHtml .= '<td>' . h((string)($row['bill_of_lading'] ?: '-')) . '</td>';
            $exportHtml .= '<td>' . h((string)($row['vessel_name'] ?: '-')) . '</td>';
            $exportHtml .= '<td>' . h((string)($row['po_codes'] ?: '-')) . '</td>';
            $exportHtml .= '<td>' . h((string)$row['supplier_names']) . '</td>';
            $exportHtml .= '<td>' . h((string)$row['country_names']) . '</td>';
            $exportHtml .= '<td>' . h((string)$row['status']) . '</td>';
            $exportHtml .= '<td style="text-align:right">' . number_format((float)($row['ordered_rolls'] ?? 0), 0, ',', '.') . '</td>';
            $exportHtml .= '<td style="text-align:right">' . number_format((float)($row['physical_rolls'] ?? 0), 0, ',', '.') . '</td>';
            $exportHtml .= '<td style="text-align:right">' . number_format((float)($row['qty_diff'] ?? 0), 0, ',', '.') . '</td>';
            $exportHtml .= '<td style="text-align:right">' . number_format((float)($row['ordered_weight_kg'] ?? 0), 2, ',', '.') . '</td>';
            $exportHtml .= '<td style="text-align:right">' . ($row['has_scale'] ? number_format((float)($row['scale_weight_kg'] ?? 0), 2, ',', '.') : 'Sin Balanza') . '</td>';
            $exportHtml .= '<td style="text-align:right">' . ($row['has_scale'] ? number_format((float)($row['weight_diff'] ?? 0), 2, ',', '.') : '-') . '</td>';
            $exportHtml .= '<td style="text-align:right">' . ($row['has_scale'] ? (number_format((float)($row['weight_diff_pct'] ?? 0), 2, ',', '.') . '%') : '-') . '</td>';
            $exportHtml .= '<td style="text-align:right">' . (int)($row['scale_rolls_weighed'] ?? 0) . '</td>';
            $exportHtml .= '<td style="text-align:center">' . $dictLabel . '</td>';
            $exportHtml .= '<td style="text-align:center">' . $erpLabel . '</td>';
            $exportHtml .= '<td>' . h((string)($row['eta_plant'] ?: '-')) . '</td>';
            $exportHtml .= '<td>' . h((string)($row['created_at'] ?: '-')) . '</td>';
            $exportHtml .= '</tr>';
        }
        $exportHtml .= '</tbody></table></body></html>';

        SimpleXlsx::streamHtml($filename, $exportHtml, 'Auditoria_Contenedores');
        exit;
    }

    // 6. Paginación de Filas
    $totalFiltered = count($filteredRows);
    $totalPages = max(1, (int)ceil($totalFiltered / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    $pagedRows = array_slice($filteredRows, $offset, $perPage);

    $buildQueryWithout = function(array $excludeKeys, array $newParams = []): string {
        $p = $_GET;
        foreach ($excludeKeys as $k) unset($p[$k]);
        foreach ($newParams as $k => $v) $p[$k] = $v;
        return '/reports/reception-audit?' . http_build_query($p);
    };

    $exportQuery = $_GET;
    $exportQuery['export'] = 'excel';
    $excelUrl = '/reports/reception-audit?' . http_build_query($exportQuery);
    $clearUrl = '/reports/reception-audit';

    // 7. Renderizado HTML Ejecutivo
    $body = '<div style="display:flex;flex-direction:column;gap:20px">';
    $body .= renderReceptionNavTabs('reception_audit');

    $body .= '<style>
      .rec-audit-kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(215px,1fr)); gap:14px; }
      .rec-audit-kpi-card { background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:18px 20px; box-shadow:0 4px 16px rgba(15,23,42,.04); position:relative; overflow:hidden; }
      .rec-audit-kpi-card::before { content:""; position:absolute; top:0; left:0; right:0; height:4px; }
      .rec-audit-kpi-card.slate::before { background:#0f172a; }
      .rec-audit-kpi-card.teal::before { background:#0f766e; }
      .rec-audit-kpi-card.emerald::before { background:#059669; }
      .rec-audit-kpi-card.red::before { background:#dc2626; }
      .rec-audit-kpi-card.indigo::before { background:#4f46e5; }
      .rec-audit-kpi-title { font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#64748b; font-weight:800; display:flex; justify-content:space-between; align-items:center; }
      .rec-audit-kpi-val { font-size:26px; font-weight:900; color:#0f172a; margin-top:8px; line-height:1.2; }
      .rec-audit-kpi-sub { font-size:12px; color:#64748b; margin-top:6px; display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
      .rec-badge-verdict { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; padding:4px 10px; border-radius:999px; }
      .rec-verdict-ok { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
      .rec-verdict-warn { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }
      .rec-verdict-crit { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
      .rec-verdict-erp { background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; }
      .rec-code-pill { font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace; font-size:12.5px; font-weight:800; color:#0f172a; background:#f1f5f9; padding:3px 9px; border-radius:6px; border:1px solid #e2e8f0; display:inline-block; }
      .rec-supp-box { max-width:230px; }
      .rec-supp-title { font-weight:700; color:#0f172a; font-size:13px; line-height:1.3; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
      .rec-supp-country { font-size:11px; color:#64748b; font-weight:600; display:inline-flex; align-items:center; gap:4px; margin-top:2px; }
      .rec-filter-pill { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:700; padding:6px 14px; border-radius:20px; text-decoration:none; transition:all .15s ease; border:1px solid transparent; }
      .rec-filter-pill.active { background:#0f172a; color:#ffffff; }
      .rec-filter-pill:not(.active) { background:#f1f5f9; color:#475569; border-color:#e2e8f0; }
      .rec-filter-pill:not(.active):hover { background:#e2e8f0; color:#0f172a; }
      .rec-pagination-bar { display:flex; justify-content:space-between; align-items:center; padding:14px 20px; background:#f8fafc; border-top:1px solid #e2e8f0; flex-wrap:wrap; gap:12px; }
      .rec-page-btn { display:inline-flex; align-items:center; gap:4px; padding:6px 12px; font-size:12px; font-weight:700; color:#0f172a; background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; text-decoration:none; transition:background .15s; }
      .rec-page-btn:hover:not(.disabled) { background:#f1f5f9; border-color:#94a3b8; }
      .rec-page-btn.disabled { opacity:0.5; pointer-events:none; }
      .rec-page-btn.active { background:#0f172a; color:#ffffff; border-color:#0f172a; }
    </style>';

    // Header Ejecutivo Principal
    $body .= '<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:22px 24px;box-shadow:0 6px 20px rgba(15,23,42,.04)">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
        <div>
          <div style="display:flex;align-items:center;gap:8px">
            <span style="background:#0f172a;color:#ffffff;font-size:11px;font-weight:800;padding:3px 8px;border-radius:6px;letter-spacing:.06em">AUDITORÍA POR CONTENEDOR</span>
            <span style="font-size:13px;color:#64748b;font-weight:600">Control Metrológico & Cierre de Embarques</span>
          </div>
          <h1 style="margin:6px 0 4px 0;font-size:24px;font-weight:900;color:#0f172a">Informe Ejecutivo de Auditoría de Recepción</h1>
          <div style="font-size:13px;color:#64748b;max-width:880px;line-height:1.5">
            Evaluación independiente por unidad de contenedor. Compara el pesaje real capturado en báscula electrónica TRZ contra el manifiesto documental (BL/Invoice), diferenciando ingresos históricos administrativos en ERP.
          </div>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <a href="' . h($excelUrl) . '" class="btn" style="background:#059669;color:#ffffff;border:none;display:inline-flex;align-items:center;gap:8px;font-weight:700;padding:9px 16px;border-radius:10px">
            <svg style="width:16px;height:16px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Exportar a Excel
          </a>
        </div>
      </div>';

    // Barra de Vistas Rápidas
    $body .= '<div style="margin-top:16px;padding-top:14px;border-top:1px solid #f1f5f9;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <span style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;margin-right:4px">Vistas Rápidas:</span>
      <a href="' . h($buildQueryWithout(['view_filter', 'page'], ['view_filter' => 'all'])) . '" class="rec-filter-pill' . ($viewFilter === 'all' ? ' active' : '') . '">
        Todos (' . count($allContainerRows) . ')
      </a>
      <a href="' . h($buildQueryWithout(['view_filter', 'page'], ['view_filter' => 'scale_only'])) . '" class="rec-filter-pill' . ($viewFilter === 'scale_only' ? ' active' : '') . '">
        ⚖️ Con Balanza TRZ (' . $summary['containers_with_scale'] . ')
      </a>
      <a href="' . h($buildQueryWithout(['view_filter', 'page'], ['view_filter' => 'critical_only'])) . '" class="rec-filter-pill' . ($viewFilter === 'critical_only' ? ' active' : '') . '">
        🚨 Desviación Crítica (' . $summary['critical_containers'] . ')
      </a>
      <a href="' . h($buildQueryWithout(['view_filter', 'page'], ['view_filter' => 'erp_pending'])) . '" class="rec-filter-pill' . ($viewFilter === 'erp_pending' ? ' active' : '') . '">
        ⏳ Pendientes ERP (' . $summary['erp_pending'] . ')
      </a>
      <a href="' . h($buildQueryWithout(['view_filter', 'page'], ['view_filter' => 'active_only'])) . '" class="rec-filter-pill' . ($viewFilter === 'active_only' ? ' active' : '') . '">
        📦 En Proceso / Activos (' . $summary['active_containers'] . ')
      </a>
      <a href="' . h($buildQueryWithout(['view_filter', 'page'], ['view_filter' => 'admin_only'])) . '" class="rec-filter-pill' . ($viewFilter === 'admin_only' ? ' active' : '') . '">
        📋 Históricos ERP (' . $summary['containers_admin_erp'] . ')
      </a>
    </div>';

    // Formulario de Filtros
    $body .= '<form method="get" action="/reports/reception-audit" style="margin-top:14px;padding-top:14px;border-top:1px solid #f1f5f9">
        <input type="hidden" name="view_filter" value="' . h($viewFilter) . '">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;align-items:flex-end">
          <div class="rec-field">
            <label style="font-size:11px;font-weight:800;text-transform:uppercase;color:#475569">Estado Cargamento</label>
            <select name="status" style="width:100%;height:38px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font-size:13px">
              <option value="all"' . ($statusFilter === 'all' ? ' selected' : '') . '>Todos los estados</option>
              <option value="active"' . ($statusFilter === 'active' ? ' selected' : '') . '>Solo activos / en proceso</option>
              <option value="complete"' . ($statusFilter === 'complete' ? ' selected' : '') . '>Solo finalizados</option>
            </select>
          </div>
          <div class="rec-field">
            <label style="font-size:11px;font-weight:800;text-transform:uppercase;color:#475569">Fecha Desde</label>
            <input type="date" name="date_from" value="' . h($dateFrom) . '" style="width:100%;height:38px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font-size:13px">
          </div>
          <div class="rec-field">
            <label style="font-size:11px;font-weight:800;text-transform:uppercase;color:#475569">Fecha Hasta</label>
            <input type="date" name="date_to" value="' . h($dateTo) . '" style="width:100%;height:38px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font-size:13px">
          </div>
          <div class="rec-field">
            <label style="font-size:11px;font-weight:800;text-transform:uppercase;color:#475569">Contenedor / BL</label>
            <input type="text" name="container_code" value="' . h($containerCode) . '" placeholder="Ej. FFAU7684870" style="width:100%;height:38px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font-size:13px">
          </div>
          <div class="rec-field">
            <label style="font-size:11px;font-weight:800;text-transform:uppercase;color:#475569">Orden de Compra</label>
            <input type="text" name="po_code" value="' . h($poCode) . '" placeholder="Ej. OC0003963" style="width:100%;height:38px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font-size:13px">
          </div>
          <div class="rec-field">
            <label style="font-size:11px;font-weight:800;text-transform:uppercase;color:#475569">Proveedor / País</label>
            <input type="text" name="supplier" value="' . h($supplierQuery) . '" placeholder="Buscar proveedor..." style="width:100%;height:38px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font-size:13px">
          </div>
          <div style="display:flex;gap:8px;align-items:center;height:38px">
            <button class="btn" type="submit" style="background:#0f172a;color:#ffffff;height:38px;padding:0 16px;border-radius:8px;font-weight:700">Filtrar</button>
            <a href="' . h($clearUrl) . '" class="btn secondary" style="height:38px;line-height:36px;padding:0 12px;border-radius:8px;font-size:13px">Limpiar</a>
          </div>
        </div>
      </form>
    </div>';

    // 8. Panel de 5 Tarjetas KPI Ejecutivas por Contenedor
    $critColor = $summary['critical_containers'] > 0 ? '#dc2626' : '#059669';
    $body .= '<div class="rec-audit-kpi-grid">
      <!-- KPI 1: Total Contenedores -->
      <div class="rec-audit-kpi-card slate">
        <div class="rec-audit-kpi-title">
          <span>Contenedores Evaluados</span>
          <span style="font-size:16px">🚢</span>
        </div>
        <div class="rec-audit-kpi-val">' . number_format($summary['total_containers'], 0, ',', '.') . '</div>
        <div class="rec-audit-kpi-sub">
          <span style="font-weight:700;color:#0f172a">' . $summary['active_containers'] . '</span> en proceso &bull; 
          <span style="font-weight:700;color:#0f172a">' . $summary['complete_containers'] . '</span> finalizados
        </div>
      </div>

      <!-- KPI 2: Auditoría Báscula TRZ -->
      <div class="rec-audit-kpi-card teal">
        <div class="rec-audit-kpi-title">
          <span>Auditoría Balanza TRZ</span>
          <span style="font-size:16px">⚖️</span>
        </div>
        <div class="rec-audit-kpi-val" style="color:#0f766e">
          ' . $summary['containers_with_scale'] . ' <span style="font-size:15px;color:#64748b;font-weight:700">cont.</span>
        </div>
        <div class="rec-audit-kpi-sub">
          <strong>' . number_format($summary['scale_rolls_weighed'], 0, ',', '.') . '</strong> bobinas pesadas con código único
        </div>
      </div>

      <!-- KPI 3: Conformidad Metrológica -->
      <div class="rec-audit-kpi-card emerald">
        <div class="rec-audit-kpi-title">
          <span>Conformidad Metrológica</span>
          <span style="font-size:16px">🎯</span>
        </div>
        <div class="rec-audit-kpi-val" style="color:#059669">' . number_format($conformancePct, 1, ',', '.') . '%</div>
        <div class="rec-audit-kpi-sub">
          <span style="color:#059669;font-weight:700">' . $summary['conforming_containers'] . ' conformes (≤1.5%)</span> &bull; 
          <span style="color:#d97706;font-weight:700">' . $summary['warning_containers'] . ' observados</span>
        </div>
      </div>

      <!-- KPI 4: Desviación Crítica -->
      <div class="rec-audit-kpi-card ' . ($summary['critical_containers'] > 0 ? 'red' : 'emerald') . '">
        <div class="rec-audit-kpi-title">
          <span>Desviación Crítica / Alertas</span>
          <span style="font-size:16px">' . ($summary['critical_containers'] > 0 ? '⚠️' : '✅') . '</span>
        </div>
        <div class="rec-audit-kpi-val" style="color:' . $critColor . '">
          ' . $summary['critical_containers'] . ' <span style="font-size:15px;font-weight:700;color:#64748b">cont.</span>
        </div>
        <div class="rec-audit-kpi-sub">
          ' . ($summary['critical_containers'] > 0 ? '<span style="color:#dc2626;font-weight:700">Requiere reserva o reclamo comercial</span>' : '<span style="color:#059669;font-weight:700">Sin alertas críticas vigentes</span>') . '
        </div>
      </div>

      <!-- KPI 5: Cierre ERP -->
      <div class="rec-audit-kpi-card indigo">
        <div class="rec-audit-kpi-title">
          <span>Cierre Contable ERP</span>
          <span style="font-size:16px">📋</span>
        </div>
        <div class="rec-audit-kpi-val" style="color:#4f46e5">' . number_format($erpClosePct, 1, ',', '.') . '%</div>
        <div class="rec-audit-kpi-sub">
          <span style="color:#059669;font-weight:700">' . $summary['erp_closed'] . '</span> cerrados en ERP &bull; 
          <span style="color:#dc2626;font-weight:700">' . $summary['erp_pending'] . '</span> pendientes
        </div>
      </div>
    </div>';

    // 9. Banner Explicativo de Enfoque por Contenedor
    $body .= '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid #0f766e;border-radius:10px;padding:12px 18px;display:flex;align-items:center;gap:12px">
      <div style="font-size:20px;color:#0f766e">💡</div>
      <div style="font-size:12.5px;color:#334155;line-height:1.4">
        <strong>Criterio de Auditoría Unitaria por Contenedor:</strong> Cada cargamento se evalúa como una unidad logística independiente contra su propio BL y manifiesto documental. Los registros históricos administrativos del ERP se presentan de forma segregada de las recepciones pesadas con balanza digital TRZ para asegurar precisión absoluta y evitar distorsiones.
      </div>
    </div>';

    // 10. Matriz de Auditoría por Contenedor
    $startItem = $totalFiltered > 0 ? ($offset + 1) : 0;
    $endItem = min($offset + $perPage, $totalFiltered);

    $body .= '<div class="rec-table-card" style="box-shadow:0 8px 24px rgba(15,23,42,.05);border-radius:16px;border:1px solid #e2e8f0;overflow:hidden">
      <div style="padding:16px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
        <div>
          <div style="font-weight:900;color:#0f172a;font-size:15px">Matriz de Auditoría por Contenedor</div>
          <div style="font-size:12px;color:#64748b;margin-top:2px">
            Mostrando <strong>' . $startItem . '-' . $endItem . '</strong> de <strong>' . $totalFiltered . '</strong> contenedores evaluados
          </div>
        </div>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
          <div style="font-size:12px;color:#64748b">
            Tolerancia: <strong style="color:#065f46">&plusmn;1.5% Conforme</strong> &bull; <strong style="color:#b45309">1.5% a 3.0% Observado</strong> &bull; <strong style="color:#b91c1c">&gt; 3.0% Crítico</strong>
          </div>
          <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:#64748b">
            <span>Por pág:</span>
            <select onchange="location.href=\'' . h($buildQueryWithout(['per_page', 'page'])) . '&per_page=\' + this.value" style="padding:2px 6px;border-radius:4px;border:1px solid #cbd5e1;font-size:12px">
              <option value="15"' . ($perPage === 15 ? ' selected' : '') . '>15</option>
              <option value="25"' . ($perPage === 25 ? ' selected' : '') . '>25</option>
              <option value="50"' . ($perPage === 50 ? ' selected' : '') . '>50</option>
              <option value="100"' . ($perPage === 100 ? ' selected' : '') . '>100</option>
            </select>
          </div>
        </div>
      </div>
      <div class="rec-table-wrap">
        <table class="rec-table" style="width:100%;border-collapse:collapse">
          <thead>
            <tr style="background:#0f172a;color:#ffffff;font-size:11px;text-transform:uppercase;letter-spacing:.05em">
              <th style="padding:12px 14px">Contenedor & Embarque</th>
              <th style="padding:12px 14px">Proveedor & Origen</th>
              <th style="padding:12px 14px">Fechas</th>
              <th style="padding:12px 14px;text-align:right">Bultos / Bobinas</th>
              <th style="padding:12px 14px;text-align:right">Kilos Balanza vs Doc</th>
              <th style="padding:12px 14px;text-align:right">Desviación Peso</th>
              <th style="padding:12px 14px;text-align:center">Captura TRZ</th>
              <th style="padding:12px 14px;text-align:center">Dictamen Auditoría</th>
              <th style="padding:12px 14px;text-align:center">Cierre ERP</th>
              <th style="padding:12px 14px;text-align:right">Acciones</th>
            </tr>
          </thead>
          <tbody>';

    foreach ($pagedRows as $c) {
        $containerId = (int)$c['id'];
        $reportUrl = '/reports/reception-audit/container/' . $containerId;
        $opUrl = '/import-containers/' . $containerId;
        $cCode = trim((string)($c['container_code'] ?: ('Cargamento #' . $containerId)));
        $bl = trim((string)($c['bill_of_lading'] ?: '-'));
        $vessel = trim((string)($c['vessel_name'] ?: '-'));
        $poCodes = trim((string)($c['po_codes'] ?: '-'));

        $ordRolls = (float)($c['ordered_rolls'] ?? 0);
        $physicalRolls = (float)($c['physical_rolls'] ?? 0);
        $qtyDiff = (float)($c['qty_diff'] ?? 0);

        $ordKg = (float)($c['ordered_weight_kg'] ?? 0);
        $physicalKg = (float)($c['physical_weight'] ?? 0);
        $weightDiff = (float)($c['weight_diff'] ?? 0);
        $weightDiffPct = (float)($c['weight_diff_pct'] ?? 0);

        $hasScale = (bool)($c['has_scale'] ?? false);
        $isCritical = (bool)($c['is_critical'] ?? false);
        $isWarning = (bool)($c['is_warning'] ?? false);

        $rowBg = $isCritical ? '#fef2f2' : ($isWarning ? '#fffbeb' : '#ffffff');
        $diffTextColor = $isCritical ? '#b91c1c' : ($isWarning ? '#b45309' : '#047857');

        $body .= '<tr style="background:' . $rowBg . ';border-bottom:1px solid #f1f5f9;transition:background .15s ease">';
        
        // 1. Contenedor & Embarque
        $body .= '<td style="padding:12px 14px;vertical-align:middle">
          <a href="' . h($reportUrl) . '" class="rec-code-pill" style="text-decoration:none;color:#0f172a;border-color:#cbd5e1" title="Ver informe pericial de auditoría">' . h($cCode) . '</a>
          <div style="font-size:11px;color:#475569;margin-top:4px">
            <strong>BL:</strong> ' . h($bl) . ($vessel !== '-' ? ' &bull; <em>' . h($vessel) . '</em>' : '') . '
          </div>
          <div style="font-size:11px;color:#64748b;margin-top:2px">
            <strong>OCs:</strong> ' . h($poCodes) . '
          </div>
        </td>';

        // 2. Proveedor & Origen
        $body .= '<td style="padding:12px 14px;vertical-align:middle">
          <div class="rec-supp-box">
            <div class="rec-supp-title" title="' . h((string)$c['supplier_names']) . '">' . h((string)$c['supplier_names']) . '</div>
            <div class="rec-supp-country">
              <svg style="width:12px;height:12px;color:#64748b" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              ' . h((string)$c['country_names']) . '
            </div>
          </div>
        </td>';

        // 3. Fechas
        $etaDate = trim((string)($c['eta_plant'] ?: ($c['eta_port'] ?: '')));
        $regDate = substr(trim((string)($c['created_at'] ?? '')), 0, 10);
        $body .= '<td style="padding:12px 14px;vertical-align:middle;font-size:12px;color:#475569">
          <div><span style="color:#94a3b8;font-size:11px">ETA:</span> <strong>' . h($etaDate ?: '-') . '</strong></div>
          <div style="margin-top:2px;font-size:11px;color:#64748b"><span style="color:#94a3b8">Reg:</span> ' . h($regDate ?: '-') . '</div>
        </td>';

        // 4. Bultos / Bobinas
        $body .= '<td style="padding:12px 14px;vertical-align:middle;text-align:right">';
        if ($hasScale) {
            $body .= '<div style="font-size:13px;font-weight:800;color:#0f172a">' . number_format($physicalRolls, 0, ',', '.') . ' <span style="font-size:11px;font-weight:600;color:#64748b">/ ' . number_format($ordRolls, 0, ',', '.') . ' bobinas</span></div>';
            if (abs($qtyDiff) < 0.001) {
                $body .= '<div style="font-size:11px;margin-top:3px;font-weight:700;color:#059669">Exacto (0 dif)</div>';
            } else {
                $body .= '<div style="font-size:11px;margin-top:3px;font-weight:700;color:#b91c1c">' . ($qtyDiff >= 0 ? '+' : '') . number_format($qtyDiff, 0, ',', '.') . ' bobinas</div>';
            }
        } else {
            $body .= '<div style="font-size:13px;font-weight:800;color:#475569">' . number_format($physicalRolls, 0, ',', '.') . ' <span style="font-size:11px;color:#64748b">unid.</span></div>';
            $body .= '<div style="font-size:10.5px;margin-top:2px;color:#94a3b8">Histórico ERP</div>';
        }
        $body .= '</td>';

        // 5. Kilos Balanza vs Doc
        $body .= '<td style="padding:12px 14px;vertical-align:middle;text-align:right">';
        if ($hasScale) {
            $body .= '<div style="font-size:13px;font-weight:800;color:#0f766e">' . number_format($physicalKg, 1, ',', '.') . ' <span style="font-size:11px;color:#64748b">kg</span></div>
                      <div style="font-size:11px;color:#64748b;margin-top:2px">Doc: ' . number_format($ordKg, 1, ',', '.') . ' kg</div>';
        } else {
            $body .= '<div style="font-size:13px;font-weight:700;color:#475569">' . number_format($ordKg, 1, ',', '.') . ' <span style="font-size:11px;color:#64748b">kg</span></div>
                      <div style="font-size:10.5px;color:#94a3b8;margin-top:2px">Sin báscula digital</div>';
        }
        $body .= '</td>';

        // 6. Desviación Peso
        $body .= '<td style="padding:12px 14px;vertical-align:middle;text-align:right">';
        if ($hasScale && $ordKg > 0) {
            $body .= '<div style="font-size:13px;font-weight:800;color:' . $diffTextColor . '">' . ($weightDiff >= 0 ? '+' : '') . number_format($weightDiff, 1, ',', '.') . ' kg</div>
                      <div style="font-size:11px;font-weight:700;color:' . $diffTextColor . ';margin-top:2px">' . ($weightDiff >= 0 ? '+' : '') . number_format($weightDiffPct, 2, ',', '.') . '%</div>';
        } elseif (!$hasScale) {
            $body .= '<div style="font-size:11.5px;color:#64748b;font-weight:600">No aplica</div>
                      <div style="font-size:10px;color:#94a3b8">Histórico ERP</div>';
        } else {
            $body .= '<div style="font-size:12px;color:#64748b">-</div>';
        }
        $body .= '</td>';

        // 7. Captura TRZ
        $body .= '<td style="padding:12px 14px;vertical-align:middle;text-align:center">';
        if ($hasScale) {
            $body .= '<span style="background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;font-size:11px;font-weight:800;padding:3px 9px;border-radius:6px;display:inline-block">
              ⚖️ ' . (int)$c['scale_rolls_weighed'] . ' bobinas
            </span>';
        } else {
            $body .= '<span style="background:#f1f5f9;color:#64748b;font-size:10.5px;font-weight:700;padding:3px 8px;border-radius:6px;display:inline-block">
              Admin ERP
            </span>';
        }
        $body .= '</td>';

        // 8. Dictamen Auditoría
        $body .= '<td style="padding:12px 14px;vertical-align:middle;text-align:center">';
        switch ($c['verdict_type']) {
            case 'CRITICAL':
                $body .= '<span class="rec-badge-verdict rec-verdict-crit" title="Desviación fuera de tolerancia comercial">🚨 CRÍTICO</span>';
                break;
            case 'WARNING':
                $body .= '<span class="rec-badge-verdict rec-verdict-warn" title="Variación entre 1.5% y 3.0%">⚠️ OBSERVADO</span>';
                break;
            case 'CONFORMING':
                $body .= '<span class="rec-badge-verdict rec-verdict-ok" title="Tolerancia estricta ≤ 1.5%">✅ CONFORME</span>';
                break;
            default:
                $body .= '<span class="rec-badge-verdict rec-verdict-erp" title="Ingreso administrativo previo a balanza digital">📋 HISTÓRICO ERP</span>';
                break;
        }
        $body .= '</td>';

        // 9. Cierre ERP
        $body .= '<td style="padding:12px 14px;vertical-align:middle;text-align:center">';
        if ((bool)($c['erp_is_complete'] ?? false)) {
            $body .= '<span style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;font-size:11px;font-weight:700;padding:3px 8px;border-radius:6px;display:inline-block">Cerrado ERP</span>';
        } else {
            $body .= '<span style="background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;font-size:11px;font-weight:700;padding:3px 8px;border-radius:6px;display:inline-block">Pendiente</span>';
        }
        $body .= '</td>';

        // 10. Acciones
        $body .= '<td style="padding:12px 14px;vertical-align:middle;text-align:right;white-space:nowrap">
          <div style="display:inline-flex;gap:6px;align-items:center">
            <a href="' . h($reportUrl) . '" class="btn" style="background:#0f172a;color:#ffffff;padding:6px 12px;font-size:12px;border-radius:8px;font-weight:700;display:inline-flex;align-items:center;gap:4px">
              Dictamen 🔍
            </a>
            <a href="' . h($opUrl) . '" class="btn secondary" style="padding:6px 9px;font-size:12px;border-radius:8px" title="Ver recepción operativa">📦</a>
          </div>
        </td>';

        $body .= '</tr>';
    }

    if ($filteredRows === []) {
        $body .= '<tr><td colspan="10" style="padding:48px 20px;text-align:center;color:#64748b">
          <div style="font-size:32px;margin-bottom:8px">🔍</div>
          <div style="font-size:16px;font-weight:800;color:#0f172a">No se encontraron contenedores para los filtros aplicados</div>
          <div style="font-size:13px;margin-top:4px">Prueba seleccionando "Todos" o ajustando los filtros de búsqueda.</div>
          <div style="margin-top:14px"><a href="' . h($clearUrl) . '" class="btn secondary" style="padding:6px 14px;font-size:13px">Restablecer filtros</a></div>
        </td></tr>';
    }

    $body .= '</tbody></table></div>';

    // Barra de Paginación
    if ($totalPages > 1) {
        $body .= '<div class="rec-pagination-bar">';
        $body .= '<div style="font-size:12.5px;color:#64748b">Página <strong>' . $page . '</strong> de <strong>' . $totalPages . '</strong> (' . $totalFiltered . ' contenedores)</div>';
        $body .= '<div style="display:flex;gap:6px;align-items:center">';
        
        // Anterior
        $prevPageUrl = $page > 1 ? $buildQueryWithout(['page'], ['page' => $page - 1]) : '#';
        $body .= '<a href="' . h($prevPageUrl) . '" class="rec-page-btn' . ($page <= 1 ? ' disabled' : '') . '">&laquo; Anterior</a>';
        
        // Botones de Páginas Principales
        $startP = max(1, $page - 2);
        $endP = min($totalPages, $page + 2);
        if ($startP > 1) {
            $body .= '<a href="' . h($buildQueryWithout(['page'], ['page' => 1])) . '" class="rec-page-btn">1</a>';
            if ($startP > 2) $body .= '<span style="color:#94a3b8;padding:0 2px">&hellip;</span>';
        }
        for ($p = $startP; $p <= $endP; $p++) {
            $pUrl = $buildQueryWithout(['page'], ['page' => $p]);
            $body .= '<a href="' . h($pUrl) . '" class="rec-page-btn' . ($p === $page ? ' active' : '') . '">' . $p . '</a>';
        }
        if ($endP < $totalPages) {
            if ($endP < $totalPages - 1) $body .= '<span style="color:#94a3b8;padding:0 2px">&hellip;</span>';
            $body .= '<a href="' . h($buildQueryWithout(['page'], ['page' => $totalPages])) . '" class="rec-page-btn">' . $totalPages . '</a>';
        }

        // Siguiente
        $nextPageUrl = $page < $totalPages ? $buildQueryWithout(['page'], ['page' => $page + 1]) : '#';
        $body .= '<a href="' . h($nextPageUrl) . '" class="rec-page-btn' . ($page >= $totalPages ? ' disabled' : '') . '">Siguiente &raquo;</a>';
        
        $body .= '</div></div>';
    }

    $body .= '</div>'; // Fin rec-table-card
    $body .= '</div>'; // Fin flex-col

    render('Auditoría de Recepción · Contenedores', $body);
}

/**
 * Renderiza el dictamen pericial detallado de auditoría de un contenedor individual
 */
function handleReceptionAuditContainerDetail(ReceptionService $service, int $containerId): void
{
    $container = $service->getImportContainer($containerId);
    if ($container === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe el contenedor solicitado.</div>');
        return;
    }

    $isExport = isset($_GET['export']) && $_GET['export'] === 'excel';
    $lines = $service->listImportContainerLines($containerId);

    // Bobinas capturadas en balanza electrónica TRZ
    $rolls = [];
    try {
        $rStmt = Db::pdo()->prepare("
            SELECT r.id, r.roll_code, r.weight_kg, r.received_qty, r.width_mm, r.microns, r.meters, r.status, r.created_at, r.purchase_order_line_id
            FROM rolls r
            WHERE r.import_container_id = :cid
            ORDER BY r.id ASC
        ");
        $rStmt->execute([':cid' => $containerId]);
        $rolls = $rStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable) {}

    // Proveedor y País
    $suppNames = '-';
    $countryNames = '-';
    try {
        $spStmt = Db::erpPdo()->prepare("
            SELECT GROUP_CONCAT(DISTINCT s.supp_company SEPARATOR ' / ') AS supplier_names,
                   GROUP_CONCAT(DISTINCT c.country_name SEPARATOR ' / ') AS country_names
                FROM supplier_contenedor_items sci
                JOIN supplier_order_items soi ON soi.id = sci.sord_pos_id
                JOIN supplier_order so ON so.id = soi.sord_id
                JOIN supplier s ON s.id = so.sord_supplier_id
                LEFT JOIN country c ON c.id = s.supp_countryid
                WHERE sci.sord_id = :cid
        ");
        $spStmt->execute([':cid' => $containerId]);
        $spRow = $spStmt->fetch(PDO::FETCH_ASSOC);
        if ($spRow) {
            $suppNames = trim((string)($spRow['supplier_names'] ?: '-'));
            $countryNames = trim((string)($spRow['country_names'] ?: '-'));
        }
    } catch (\Throwable) {}

    $ordRolls = round((float)($container['ordered_rolls'] ?? 0), 3);
    $erpRecRolls = round((float)($container['received_rolls'] ?? 0), 3);
    $ordWeight = round((float)($container['ordered_weight_kg'] ?? 0), 3);
    $erpRecWeight = round((float)($container['received_weight_kg'] ?? 0), 3);
    $erpComplete = (bool)($container['erp_is_complete'] ?? false);

    $scaleRollsCount = count($rolls);
    $scaleWeights = array_map(static fn(array $r): float => (float)($r['weight_kg'] ?? 0.0), $rolls);
    $scaleTotalKg = $scaleRollsCount > 0 ? (float)array_sum($scaleWeights) : 0.0;
    $hasScale = ($scaleRollsCount > 0);
    $absWeightDiffPct = 0.0;
    $absQtyDiff = 0.0;

    if ($hasScale) {
        $physicalRolls = (float)$scaleRollsCount;
        $physicalWeight = $scaleTotalKg;
        $qtyDiff = round($physicalRolls - $ordRolls, 3);
        $weightDiff = round($physicalWeight - $ordWeight, 3);
        $weightDiffPct = $ordWeight > 0.0001 ? round(($weightDiff / $ordWeight) * 100, 2) : 0.0;

        $absWeightDiffPct = abs($weightDiffPct);
        $absQtyDiff = abs($qtyDiff);

        $isCritical = ($absQtyDiff >= 1.0 || $absWeightDiffPct >= 3.0);
        $isWarning = (!$isCritical && $absWeightDiffPct >= 1.5);
        $isConforming = (!$isCritical && !$isWarning);
    } else {
        $physicalRolls = $erpRecRolls;
        $physicalWeight = $erpRecWeight;
        $qtyDiff = round($physicalRolls - $ordRolls, 3);
        $weightDiff = 0.0;
        $weightDiffPct = 0.0;
        $isCritical = false;
        $isWarning = false;
        $isConforming = false;
    }

    // Estadísticas metrológicas del lote pesado
    $scaleAvgKg = $scaleRollsCount > 0 ? round($scaleTotalKg / $scaleRollsCount, 2) : 0.0;
    $scaleMinKg = $scaleRollsCount > 0 ? (float)min($scaleWeights) : 0.0;
    $scaleMaxKg = $scaleRollsCount > 0 ? (float)max($scaleWeights) : 0.0;
    $variance = 0.0;
    foreach ($scaleWeights as $w) {
        $variance += pow(($w - $scaleAvgKg), 2);
    }
    $scaleStdDev = $scaleRollsCount > 1 ? round(sqrt($variance / ($scaleRollsCount - 1)), 2) : 0.0;

    // Resumen por líneas de producto
    foreach ($lines as &$line) {
        $lineSummary = function_exists('receptionLineSummary') ? receptionLineSummary($line) : $service->summarizeReceptionLine($line);
        $lineOrdWeight = round((float)($lineSummary['ordered_weight_kg'] ?? 0), 3);
        $lineRecWeight = round((float)($lineSummary['received_weight_kg'] ?? 0), 3);
        $lineQtyDiff = round((float)($lineSummary['received_rolls'] ?? 0) - (float)($lineSummary['ordered_rolls'] ?? 0), 3);
        $lineWeightDiff = round($lineRecWeight - $lineOrdWeight, 3);
        $lineWeightDiffPct = $lineOrdWeight > 0.0001 ? round(($lineWeightDiff / $lineOrdWeight) * 100, 2) : 0.0;

        $line['qty_diff'] = $lineQtyDiff;
        $line['weight_diff'] = $lineWeightDiff;
        $line['weight_diff_pct'] = $lineWeightDiffPct;
        $line['is_complete'] = (bool)($lineSummary['is_complete'] ?? false);
        $line['summary'] = $lineSummary;
    }
    unset($line);

    $containerCode = trim((string)($container['container_code'] ?: ('#' . $containerId)));
    $bl = trim((string)($container['bill_of_lading'] ?: '-'));
    $vessel = trim((string)($container['vessel_name'] ?: '-'));
    $forwarder = trim((string)($container['forwarder_name'] ?: '-'));
    $poCodes = trim((string)($container['po_codes'] ?: '-'));

    // Exportación a Excel del detalle individual
    if ($isExport) {
        $filename = 'dictamen_auditoria_contenedor_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $containerCode) . '_' . date('Ymd_His') . '.xlsx';
        $exportHtml = '<!doctype html><html><head><meta charset="utf-8"></head><body>';
        $exportHtml .= '<h2>DICTAMEN OFICIAL DE AUDITORÍA DE RECEPCIÓN - CONTENEDOR ' . h($containerCode) . '</h2>';
        $exportHtml .= '<p><strong>Bill of Lading:</strong> ' . h($bl) . ' | <strong>Buque:</strong> ' . h($vessel) . ' | <strong>Proveedor:</strong> ' . h($suppNames) . ' (' . h($countryNames) . ') | <strong>OCs:</strong> ' . h($poCodes) . '</p>';
        $exportHtml .= '<p><strong>Balance Kilos:</strong> Balanza ' . number_format($physicalWeight, 2, ',', '.') . ' kg vs Factura ' . number_format($ordWeight, 2, ',', '.') . ' kg (Dif: ' . ($weightDiff >= 0 ? '+' : '') . number_format($weightDiff, 2, ',', '.') . ' kg / ' . number_format($weightDiffPct, 2, ',', '.') . '%)</p>';

        $exportHtml .= '<h3>1. DESGLOSE POR PRODUCTO / SKU</h3>';
        $exportHtml .= '<table border="1"><thead><tr style="background:#0f172a;color:#ffffff;font-weight:bold">';
        $exportHtml .= '<th>OC</th><th>SKU</th><th>Descripción</th><th>Modo</th><th>Especificación</th><th>Bultos Doc</th><th>Bultos Físicos</th><th>Dif Bultos</th><th>Kilos Doc (kg)</th><th>Kilos Físicos (kg)</th><th>Dif Kilos (kg)</th><th>Desviación (%)</th><th>Estado</th>';
        $exportHtml .= '</tr></thead><tbody>';
        foreach ($lines as $ln) {
            $sm = $ln['summary'];
            $exportHtml .= '<tr>';
            $exportHtml .= '<td>' . h((string)($ln['po_code'] ?? '-')) . '</td>';
            $exportHtml .= '<td>' . h((string)($ln['sku_code'] ?? '-')) . '</td>';
            $exportHtml .= '<td>' . h((string)($ln['sku_description'] ?? '-')) . '</td>';
            $exportHtml .= '<td>' . h(receptionModeLabel((string)($ln['reception_mode'] ?? 'WEIGHT'))) . '</td>';
            $exportHtml .= '<td>' . buildReceptionSpec($ln) . '</td>';
            $exportHtml .= '<td style="text-align:right">' . number_format((float)($sm['ordered_rolls'] ?? 0), 0, ',', '.') . '</td>';
            $exportHtml .= '<td style="text-align:right">' . number_format((float)($sm['received_rolls'] ?? 0), 0, ',', '.') . '</td>';
            $exportHtml .= '<td style="text-align:right">' . number_format((float)($ln['qty_diff'] ?? 0), 0, ',', '.') . '</td>';
            $exportHtml .= '<td style="text-align:right">' . number_format((float)($sm['ordered_weight_kg'] ?? 0), 2, ',', '.') . '</td>';
            $exportHtml .= '<td style="text-align:right">' . number_format((float)($sm['received_weight_kg'] ?? 0), 2, ',', '.') . '</td>';
            $exportHtml .= '<td style="text-align:right">' . number_format((float)($ln['weight_diff'] ?? 0), 2, ',', '.') . '</td>';
            $exportHtml .= '<td style="text-align:right">' . number_format((float)($ln['weight_diff_pct'] ?? 0), 2, ',', '.') . '%</td>';
            $exportHtml .= '<td>' . ((bool)($ln['is_complete'] ?? false) ? 'Completa' : 'Pendiente') . '</td>';
            $exportHtml .= '</tr>';
        }
        $exportHtml .= '</tbody></table>';

        if ($rolls !== []) {
            $exportHtml .= '<h3>2. REGISTRO UNITARIO DE BOBINAS EN BÁSCULA TRZ (' . count($rolls) . ' BOBINAS)</h3>';
            $exportHtml .= '<table border="1"><thead><tr style="background:#1e3a8a;color:#ffffff;font-weight:bold">';
            $exportHtml .= '<th>N°</th><th>Código Bobina</th><th>Peso Balanza (kg)</th><th>Ancho (mm)</th><th>Micras/Gramos</th><th>Metros</th><th>Fecha/Hora Pesaje</th><th>Estado</th>';
            $exportHtml .= '</tr></thead><tbody>';
            $idx = 1;
            foreach ($rolls as $r) {
                $exportHtml .= '<tr>';
                $exportHtml .= '<td>' . $idx++ . '</td>';
                $exportHtml .= '<td>' . h((string)$r['roll_code']) . '</td>';
                $exportHtml .= '<td style="text-align:right">' . number_format((float)$r['weight_kg'], 2, ',', '.') . '</td>';
                $exportHtml .= '<td style="text-align:right">' . (int)($r['width_mm'] ?? 0) . '</td>';
                $exportHtml .= '<td style="text-align:right">' . (int)($r['microns'] ?? 0) . '</td>';
                $exportHtml .= '<td style="text-align:right">' . number_format((float)($r['meters'] ?? 0), 2, ',', '.') . '</td>';
                $exportHtml .= '<td>' . h((string)$r['created_at']) . '</td>';
                $exportHtml .= '<td>' . h((string)$r['status']) . '</td>';
                $exportHtml .= '</tr>';
            }
            $exportHtml .= '</tbody></table>';
        }

        $exportHtml .= '</body></html>';
        SimpleXlsx::streamHtml($filename, $exportHtml, 'Detalle_Contenedor');
        exit;
    }

    // Generar dictamen y justificación pericial
    $diffColor = $isCritical ? '#b91c1c' : ($isWarning ? '#b45309' : '#047857');
    $verdictClass = $isCritical ? 'crit' : ($isWarning ? 'warn' : ($hasScale ? 'ok' : 'erp'));

    if ($hasScale) {
        if ($isCritical) {
            $verdictTitle = 'DICTAMEN: RECEPCIÓN CON DESVIACIÓN CRÍTICA';
            $reasons = [];
            if ($absWeightDiffPct >= 3.0) {
                $reasons[] = 'desviación de peso en báscula de ' . ($weightDiff >= 0 ? '+' : '') . number_format($weightDiffPct, 2, ',', '.') . '% excede el umbral comercial estándar del 3.0%';
            }
            if ($absQtyDiff >= 1.0) {
                $reasons[] = 'discrepancia física en cantidad de bobinas (' . ($qtyDiff >= 0 ? '+' : '') . number_format($qtyDiff, 0, ',', '.') . ' bobinas)';
            }
            $verdictExplain = 'Se han determinado no conformidades metrológicas: ' . implode(' y ', $reasons) . '. Se recomienda formalizar carta de reserva y reclamo formal con naviera o proveedor.';
        } elseif ($isWarning) {
            $verdictTitle = 'DICTAMEN: RECEPCIÓN EN TOLERANCIA COMERCIAL OBSERVADA';
            $verdictExplain = 'El cargamento presenta una variación de peso en balanza de ' . ($weightDiff >= 0 ? '+' : '') . number_format($weightDiffPct, 2, ',', '.') . '%, lo cual se encuentra dentro del rango de tolerancia comercial ampliado (1.5% a 3.0%). La cantidad de bobinas es conforme.';
        } else {
            $verdictTitle = 'DICTAMEN: RECEPCIÓN CONFORME EN TOLERANCIA ESTRICTA';
            $verdictExplain = 'El cargamento cumple a cabalidad los estándares metrológicos y de control de calidad. Desviación neta de ' . ($weightDiff >= 0 ? '+' : '') . number_format($weightDiffPct, 2, ',', '.') . '% dentro de la tolerancia estricta (≤ 1.5%) y bobinas 100% conformes.';
        }
    } else {
        $verdictTitle = 'DICTAMEN: REGISTRO HISTÓRICO ADMINISTRATIVO ERP';
        $verdictExplain = 'Este cargamento fue ingresado de forma administrativa en el ERP previo a la captura de pesajes unitarios en báscula digital TRZ. Cantidades documentadas y bultos registrados según constancia contable.';
    }

    $exportQuery = $_GET;
    $exportQuery['export'] = 'excel';
    $excelUrl = '/reports/reception-audit/container/' . $containerId . '?' . http_build_query($exportQuery);

    $body = '<div style="display:flex;flex-direction:column;gap:20px">';
    $body .= renderReceptionNavTabs('reception_audit');

    $body .= '<style>
      @media print {
        body { background:#ffffff !important; font-size:10pt !important; color:#000000 !important; }
        .app-sidebar, .app-header, .rec-nav-bar, .btn, .no-print, .rec-page-filter-box { display:none !important; }
        .rec-audit-card, .rec-table-card { box-shadow:none !important; border:1px solid #cbd5e1 !important; }
        .print-only { display:block !important; }
        .page-break { page-break-before:always; }
      }
      .print-only { display:none; }
      .rec-container-kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:14px; }
      .rec-verdict-banner { border-radius:14px; padding:20px 24px; display:flex; align-items:flex-start; gap:16px; box-shadow:0 6px 20px rgba(15,23,42,.04); }
      .rec-verdict-banner.ok { background:#ecfdf5; border:1px solid #a7f3d0; }
      .rec-verdict-banner.warn { background:#fffbeb; border:1px solid #fde68a; }
      .rec-verdict-banner.crit { background:#fef2f2; border:1px solid #fecaca; }
      .rec-verdict-banner.erp { background:#f8fafc; border:1px solid #cbd5e1; }
      .rec-stat-pill { display:inline-flex; align-items:center; gap:6px; background:#f1f5f9; padding:4px 10px; border-radius:8px; font-size:12px; font-weight:700; color:#334155; }
    </style>';

    // Membrete formal para impresión
    $body .= '<div class="print-only" style="padding-bottom:14px;border-bottom:2px solid #0f172a;margin-bottom:16px">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <div>
          <h2 style="margin:0;font-size:20px;font-weight:900;color:#0f172a">UNIBAG SPA &bull; CONTROL DE CALIDAD Y RECEPCIONES</h2>
          <div style="font-size:12px;color:#475569">Dictamen Oficial de Auditoría y Metrología de Recepción por Contenedor</div>
        </div>
        <div style="text-align:right;font-size:11px;color:#475569">
          <div><strong>Certificado N°:</strong> AUD-CONT-' . $containerId . '-' . date('Y') . '</div>
          <div><strong>Fecha Emisión:</strong> ' . date('d/m/Y H:i') . '</div>
        </div>
      </div>
    </div>';

    // Cabecera interactiva
    $body .= '<div class="rec-audit-card" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:22px 24px;box-shadow:0 6px 20px rgba(15,23,42,.04)">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
        <div>
          <div style="display:flex;align-items:center;gap:8px">
            <a href="/reports/reception-audit" style="color:#64748b;text-decoration:none;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:4px">
              &larr; Volver a Matriz de Auditoría
            </a>
            <span style="color:#cbd5e1">&bull;</span>
            <span style="font-family:monospace;font-size:12px;font-weight:800;background:#f1f5f9;color:#0f172a;padding:2px 8px;border-radius:6px">ID #' . $containerId . '</span>
            <span style="font-size:12px;color:#64748b">Estado: ' . renderReceptionStatusBadge((string)$container['status']) . '</span>
          </div>
          <h1 style="margin:8px 0 4px 0;font-size:28px;font-weight:900;color:#0f172a">Contenedor ' . h($containerCode) . '</h1>
          <div style="font-size:14px;color:#475569;font-weight:600">
            ' . h($suppNames) . ' &bull; <em>' . h($countryNames) . '</em>
          </div>
        </div>
        <div class="no-print" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <a href="' . h($excelUrl) . '" class="btn" style="background:#059669;color:#ffffff;border:none;display:inline-flex;align-items:center;gap:6px;font-weight:700;padding:8px 14px;border-radius:9px;font-size:13px">
            <svg style="width:15px;height:15px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Excel
          </a>
          <button onclick="window.print()" class="btn" style="background:#0f172a;color:#ffffff;display:inline-flex;align-items:center;gap:6px;font-weight:700;padding:8px 14px;border-radius:9px;font-size:13px">
            <svg style="width:15px;height:15px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Imprimir Dictamen
          </button>
          <a href="/import-containers/' . $containerId . '" class="btn secondary" style="padding:8px 14px;border-radius:9px;font-size:13px;font-weight:700">
            Abrir Operativa 📦
          </a>
        </div>
      </div>
    </div>';

    // Banner de Dictamen Pericial Oficial
    $body .= '<div class="rec-verdict-banner ' . $verdictClass . '">
      <div style="font-size:32px;line-height:1">' . ($isCritical ? '🚨' : ($isWarning ? '⚠️' : ($hasScale ? '✅' : '📋'))) . '</div>
      <div style="flex:1">
        <div style="font-size:16px;font-weight:900;color:' . $diffColor . '">' . $verdictTitle . '</div>
        <div style="font-size:13px;color:#334155;margin-top:5px;line-height:1.5">' . h($verdictExplain) . '</div>
      </div>
      <div style="text-align:right">
        <span style="font-size:12px;font-weight:800;color:' . ($erpComplete ? '#065f46' : '#c2410c') . ';background:#ffffff;padding:5px 12px;border-radius:8px;border:1px solid rgba(0,0,0,.08);display:inline-block">
          ' . ($erpComplete ? '✓ Cierre Contable ERP Completo' : '⏳ Cierre ERP Pendiente') . '
        </span>
      </div>
    </div>';

    // Ficha de Identificación del Cargamento
    $body .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 18px">
        <div style="font-size:11px;text-transform:uppercase;color:#64748b;font-weight:800">Bill of Lading (BL)</div>
        <div style="font-size:16px;font-weight:800;color:#0f172a;margin-top:4px">' . h($bl) . '</div>
      </div>
      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 18px">
        <div style="font-size:11px;text-transform:uppercase;color:#64748b;font-weight:800">Buque & Embarcador</div>
        <div style="font-size:16px;font-weight:800;color:#0f172a;margin-top:4px">' . h($vessel) . '</div>
        <div style="font-size:11px;color:#64748b;margin-top:2px">' . h($forwarder) . '</div>
      </div>
      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 18px">
        <div style="font-size:11px;text-transform:uppercase;color:#64748b;font-weight:800">Órdenes de Compra (OCs)</div>
        <div style="font-size:16px;font-weight:800;color:#0f172a;margin-top:4px">' . h($poCodes) . '</div>
      </div>
      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 18px">
        <div style="font-size:11px;text-transform:uppercase;color:#64748b;font-weight:800">ETA / Arribo Planta</div>
        <div style="font-size:16px;font-weight:800;color:#0f172a;margin-top:4px">' . h((string)($container['eta_plant'] ?: ($container['eta_port'] ?: '-'))) . '</div>
        <div style="font-size:11px;color:#64748b;margin-top:2px">Reg: ' . h(substr((string)($container['created_at'] ?? ''), 0, 10)) . '</div>
      </div>
    </div>';

    // 4 KPI Cards Metrológicos del Contenedor
    $body .= '<div class="rec-container-kpi-grid">
      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:16px 20px">
        <div style="font-size:11px;text-transform:uppercase;color:#64748b;font-weight:800">Kilos Facturados (Doc)</div>
        <div style="font-size:26px;font-weight:900;color:#0f172a;margin-top:6px">' . number_format($ordWeight, 1, ',', '.') . ' <span style="font-size:14px;color:#64748b">kg</span></div>
        <div style="font-size:12px;color:#64748b;margin-top:4px">Bultos esperados: <strong>' . number_format($ordRolls, 0, ',', '.') . ' ' . ($hasScale ? 'bobinas' : 'unid.') . '</strong></div>
      </div>

      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:16px 20px">
        <div style="font-size:11px;text-transform:uppercase;color:#64748b;font-weight:800">Kilos Balanza (Físico)</div>';
    if ($hasScale) {
        $body .= '<div style="font-size:26px;font-weight:900;color:#0f766e;margin-top:6px">' . number_format($physicalWeight, 1, ',', '.') . ' <span style="font-size:14px;color:#64748b">kg</span></div>
                  <div style="font-size:12px;color:#64748b;margin-top:4px">Báscula TRZ: <strong>' . number_format($physicalRolls, 0, ',', '.') . ' bobinas pesadas</strong></div>';
    } else {
        $body .= '<div style="font-size:26px;font-weight:900;color:#475569;margin-top:6px">' . number_format($ordWeight, 1, ',', '.') . ' <span style="font-size:14px;color:#64748b">kg</span></div>
                  <div style="font-size:12px;color:#94a3b8;margin-top:4px">Sin pesaje digital en báscula</div>';
    }
    $body .= '</div>

      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:16px 20px">
        <div style="font-size:11px;text-transform:uppercase;color:#64748b;font-weight:800">Desviación Balanza</div>';
    if ($hasScale && $ordWeight > 0) {
        $body .= '<div style="font-size:26px;font-weight:900;color:' . $diffColor . ';margin-top:6px">' . ($weightDiff >= 0 ? '+' : '') . number_format($weightDiff, 1, ',', '.') . ' <span style="font-size:14px;color:#64748b">kg</span></div>
                  <div style="font-size:12px;color:' . $diffColor . ';margin-top:4px;font-weight:700">Variación: ' . ($weightDiff >= 0 ? '+' : '') . number_format($weightDiffPct, 2, ',', '.') . '%</div>';
    } else {
        $body .= '<div style="font-size:22px;font-weight:800;color:#64748b;margin-top:8px">No Aplica</div>
                  <div style="font-size:12px;color:#94a3b8;margin-top:4px">Registro previo a báscula TRZ</div>';
    }
    $body .= '</div>

      <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:16px 20px">
        <div style="font-size:11px;text-transform:uppercase;color:#64748b;font-weight:800">Bobinas en Báscula TRZ</div>
        <div style="font-size:26px;font-weight:900;color:#0f172a;margin-top:6px">' . number_format($scaleRollsCount, 0, ',', '.') . ' <span style="font-size:14px;color:#64748b">bobinas</span></div>';
    if ($scaleRollsCount > 0) {
        $body .= '<div style="font-size:12px;color:#64748b;margin-top:4px">Promedio: <strong>' . number_format($scaleAvgKg, 1, ',', '.') . ' kg/bobina</strong></div>';
    } else {
        $body .= '<div style="font-size:12px;color:#94a3b8;margin-top:4px">Ingreso administrativo ERP</div>';
    }
    $body .= '</div>
    </div>';

    // Tabla 1: Desglose por Ítem / SKU
    $body .= '<div class="rec-table-card" style="box-shadow:0 6px 20px rgba(15,23,42,.04);border-radius:16px;border:1px solid #e2e8f0;overflow:hidden">
      <div style="padding:16px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap">
        <div>
          <div style="font-size:16px;font-weight:900;color:#0f172a">Detalle por Ítem / SKU de la Orden</div>
          <div style="font-size:12px;color:#64748b;margin-top:2px">Cruce de cantidades documentadas vs recepción registrada por línea de cargamento</div>
        </div>
        <div style="font-size:12px;color:#64748b">' . count($lines) . ' líneas registradas</div>
      </div>
      <div class="rec-table-wrap">
        <table class="rec-table" style="width:100%;border-collapse:collapse">
          <thead>
            <tr style="background:#0f172a;color:#ffffff;font-size:11px;text-transform:uppercase">
              <th style="padding:12px 14px">OC</th>
              <th style="padding:12px 14px">SKU & Descripción</th>
              <th style="padding:12px 14px">Modo</th>
              <th style="padding:12px 14px">Especificación</th>
              <th style="padding:12px 14px;text-align:right">Bobinas (Doc / Físico)</th>
              <th style="padding:12px 14px;text-align:right">Kilos Balanza vs Doc</th>
              <th style="padding:12px 14px;text-align:right">Desviación Línea</th>
              <th style="padding:12px 14px;text-align:center">Estado Línea</th>
            </tr>
          </thead>
          <tbody>';

    foreach ($lines as $line) {
        $sm = $line['summary'];
        $lQtyDiff = (float)($line['qty_diff'] ?? 0);
        $lWDiff = (float)($line['weight_diff'] ?? 0);
        $lWPct = (float)($line['weight_diff_pct'] ?? 0);
        $isLineComplete = (bool)($line['is_complete'] ?? false);
        $lineColor = (abs($lQtyDiff) >= 1.0 || abs($lWPct) >= 3.0 || !$isLineComplete) ? '#b91c1c' : '#047857';

        $body .= '<tr style="border-bottom:1px solid #f1f5f9">';
        $body .= '<td style="padding:12px 14px;font-weight:700;color:#0f172a">' . h((string)($line['po_code'] ?? '-')) . '</td>';
        $body .= '<td style="padding:12px 14px">
          <div style="font-weight:800;color:#0f172a">' . h((string)($line['sku_code'] ?? '-')) . '</div>
          <div style="font-size:12px;color:#64748b;margin-top:2px">' . h((string)($line['sku_description'] ?? '-')) . '</div>
        </td>';
        $body .= '<td style="padding:12px 14px;font-size:12px;color:#475569">' . h(receptionModeLabel((string)($line['reception_mode'] ?? 'WEIGHT'))) . '</td>';
        $body .= '<td style="padding:12px 14px;font-size:12px;color:#475569">' . buildReceptionSpec($line) . '</td>';
        $body .= '<td style="padding:12px 14px;text-align:right">
          <div style="font-weight:800;color:#0f172a">' . number_format((float)($sm['received_rolls'] ?? 0), 0, ',', '.') . ' <span style="font-size:11px;color:#64748b">/ ' . number_format((float)($sm['ordered_rolls'] ?? 0), 0, ',', '.') . '</span></div>
          <div style="font-size:11px;font-weight:700;color:' . (abs($lQtyDiff) >= 1.0 ? '#b91c1c' : '#065f46') . ';margin-top:2px">
            ' . ($lQtyDiff >= 0 ? '+' : '') . number_format($lQtyDiff, 0, ',', '.') . ' unid.
          </div>
        </td>';
        $body .= '<td style="padding:12px 14px;text-align:right">
          <div style="font-weight:800;color:#0f172a">' . number_format((float)($sm['received_weight_kg'] ?? 0), 1, ',', '.') . ' <span style="font-size:11px;color:#64748b">kg</span></div>
          <div style="font-size:11px;color:#64748b;margin-top:2px">Doc: ' . number_format((float)($sm['ordered_weight_kg'] ?? 0), 1, ',', '.') . ' kg</div>
        </td>';
        $body .= '<td style="padding:12px 14px;text-align:right">
          <div style="font-weight:800;color:' . $lineColor . '">' . ($lWDiff >= 0 ? '+' : '') . number_format($lWDiff, 1, ',', '.') . ' kg</div>
          <div style="font-size:11px;font-weight:700;color:' . $lineColor . ';margin-top:2px">' . ($lWDiff >= 0 ? '+' : '') . number_format($lWPct, 2, ',', '.') . '%</div>
        </td>';
        $body .= '<td style="padding:12px 14px;text-align:center">';
        if ($isLineComplete) {
            $body .= '<span style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;font-size:11px;font-weight:700;padding:3px 8px;border-radius:6px;display:inline-block">Completa</span>';
        } else {
            $body .= '<span style="background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;font-size:11px;font-weight:700;padding:3px 8px;border-radius:6px;display:inline-block">Pendiente</span>';
        }
        $body .= '</td>';
        $body .= '</tr>';
    }

    if ($lines === []) {
        $body .= '<tr><td colspan="8" style="padding:24px;text-align:center;color:#64748b">Sin líneas asignadas para este contenedor.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';

    // Tabla 2: Registro Trazable Unitario de Bobinas en Balanza Electrónica
    if ($rolls !== []) {
        $body .= '<div class="rec-table-card" style="box-shadow:0 6px 20px rgba(15,23,42,.04);border-radius:16px;border:1px solid #e2e8f0;overflow:hidden">
          <div style="padding:16px 20px;background:#0f172a;color:#ffffff;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
            <div>
              <div style="font-size:16px;font-weight:900">Registro Unitario de Bobinas en Báscula TRZ (' . count($rolls) . ' bobinas)</div>
              <div style="font-size:12px;color:#94a3b8;margin-top:2px">Detalle individual de cada pesaje capturado por balanza electrónica para este contenedor</div>
            </div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
              <span class="rec-stat-pill" style="background:#1e293b;color:#38bdf8">Total Balanza: ' . number_format($scaleTotalKg, 1, ',', '.') . ' kg</span>
              <span class="rec-stat-pill" style="background:#1e293b;color:#facc15">Promedio: ' . number_format($scaleAvgKg, 1, ',', '.') . ' kg</span>
              <span class="rec-stat-pill" style="background:#1e293b;color:#a7f3d0">Mín: ' . number_format($scaleMinKg, 1, ',', '.') . ' kg</span>
              <span class="rec-stat-pill" style="background:#1e293b;color:#f472b6">Máx: ' . number_format($scaleMaxKg, 1, ',', '.') . ' kg</span>
              <span class="rec-stat-pill" style="background:#1e293b;color:#c084fc">Desv. Est: &plusmn;' . number_format($scaleStdDev, 2, ',', '.') . ' kg</span>
            </div>
          </div>
          
          <!-- Buscador interactivo de bobinas -->
          <div class="no-print" style="padding:10px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:10px">
            <div style="position:relative;flex:1;max-width:380px">
              <input type="text" id="roll-filter-input" placeholder="🔍 Filtrar bobina por código o peso..." onkeyup="filterRollsTable()" style="width:100%;height:34px;border:1px solid #cbd5e1;border-radius:6px;padding:0 10px;font-size:12.5px">
            </div>
            <div id="roll-filter-count" style="font-size:12px;color:#64748b">Mostrando ' . count($rolls) . ' bobinas</div>
          </div>

          <div class="rec-table-wrap" style="max-height:450px;overflow-y:auto">
            <table class="rec-table" id="rolls-audit-table" style="width:100%;border-collapse:collapse">
              <thead>
                <tr style="background:#1e293b;color:#ffffff;font-size:11px;text-transform:uppercase;position:sticky;top:0;z-index:2">
                  <th style="padding:10px 14px">#</th>
                  <th style="padding:10px 14px">Código de Bobina</th>
                  <th style="padding:10px 14px;text-align:right">Peso Báscula (kg)</th>
                  <th style="padding:10px 14px;text-align:right">Ancho (mm)</th>
                  <th style="padding:10px 14px;text-align:right">Micras / Gramos</th>
                  <th style="padding:10px 14px;text-align:right">Metros</th>
                  <th style="padding:10px 14px">Fecha / Hora Pesaje</th>
                  <th style="padding:10px 14px;text-align:center">Estado Trazable</th>
                </tr>
              </thead>
              <tbody>';

        $rIdx = 1;
        foreach ($rolls as $r) {
            $body .= '<tr class="roll-row" style="border-bottom:1px solid #f1f5f9;font-size:12px">';
            $body .= '<td style="padding:9px 14px;color:#64748b">' . $rIdx++ . '</td>';
            $body .= '<td style="padding:9px 14px"><span class="rec-code-pill roll-code-text">' . h((string)$r['roll_code']) . '</span></td>';
            $body .= '<td style="padding:9px 14px;text-align:right;font-weight:800;color:#0f172a"><span class="roll-weight-text">' . number_format((float)$r['weight_kg'], 2, ',', '.') . '</span> kg</td>';
            $body .= '<td style="padding:9px 14px;text-align:right;color:#475569">' . (int)($r['width_mm'] ?? 0) . '</td>';
            $body .= '<td style="padding:9px 14px;text-align:right;color:#475569">' . (int)($r['microns'] ?? 0) . '</td>';
            $body .= '<td style="padding:9px 14px;text-align:right;color:#475569">' . number_format((float)($r['meters'] ?? 0), 0, ',', '.') . ' m</td>';
            $body .= '<td style="padding:9px 14px;color:#64748b">' . h((string)$r['created_at']) . '</td>';
            $body .= '<td style="padding:9px 14px;text-align:center"><span style="background:#f1f5f9;color:#334155;font-size:10px;font-weight:800;padding:2px 7px;border-radius:4px">' . h((string)$r['status']) . '</span></td>';
            $body .= '</tr>';
        }

        $body .= '</tbody></table></div></div>';

        $body .= '<script>
        function filterRollsTable() {
            var q = (document.getElementById("roll-filter-input").value || "").toLowerCase().trim();
            var rows = document.querySelectorAll("#rolls-audit-table tbody tr.roll-row");
            var visible = 0;
            rows.forEach(function(row) {
                var code = (row.querySelector(".roll-code-text") ? row.querySelector(".roll-code-text").innerText : "").toLowerCase();
                var weight = (row.querySelector(".roll-weight-text") ? row.querySelector(".roll-weight-text").innerText : "").toLowerCase();
                if (q === "" || code.indexOf(q) !== -1 || weight.indexOf(q) !== -1) {
                    row.style.display = "";
                    visible++;
                } else {
                    row.style.display = "none";
                }
            });
            var cntEl = document.getElementById("roll-filter-count");
            if (cntEl) {
                cntEl.innerText = "Mostrando " + visible + " de " + rows.length + " bobinas";
            }
        }
        </script>';
    } else {
        $body .= '<div style="background:#f8fafc;border:1px dashed #cbd5e1;border-radius:14px;padding:26px;text-align:center;color:#64748b">
          <div style="font-size:28px;margin-bottom:6px">⚖️</div>
          <div style="font-weight:700;color:#0f172a;font-size:15px">Sin registros individuales de báscula electrónica para este cargamento</div>
          <div style="font-size:13px;max-width:620px;margin:6px auto 0 auto;line-height:1.4">
            Este contenedor fue procesado de forma administrativa en el ERP. La trazabilidad unitaria y auditoría metrológica por bobina opera automáticamente en todos los cargamentos pesados con la balanza integrada.
          </div>
        </div>';
    }

    // Sección de Firmas y Validación Formal para Informe Físico / Print
    $body .= '<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:24px;margin-top:10px">
      <div style="font-size:12px;font-weight:800;text-transform:uppercase;color:#64748b;letter-spacing:.05em;margin-bottom:20px">
        VALIDACIÓN Y CONFORMIDAD DE AUDITORÍA
      </div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;text-align:center">
        <div style="border-top:1.5px solid #0f172a;padding-top:8px">
          <div style="font-weight:800;font-size:13px;color:#0f172a">Auditor de Calidad / Metrología</div>
          <div style="font-size:11px;color:#64748b;margin-top:2px">Firma y Timbre</div>
        </div>
        <div style="border-top:1.5px solid #0f172a;padding-top:8px">
          <div style="font-weight:800;font-size:13px;color:#0f172a">Encargado de Bodega Recepción</div>
          <div style="font-size:11px;color:#64748b;margin-top:2px">Firma y Timbre</div>
        </div>
        <div style="border-top:1.5px solid #0f172a;padding-top:8px">
          <div style="font-weight:800;font-size:13px;color:#0f172a">Jefatura de Operaciones / Planta</div>
          <div style="font-size:11px;color:#64748b;margin-top:2px">Visto Bueno Final</div>
        </div>
      </div>
    </div>';

    $body .= '</div>';
    render('Dictamen de Auditoría · ' . $containerCode, $body);
}
