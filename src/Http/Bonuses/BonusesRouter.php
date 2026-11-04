<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Enrutador y Módulos de Bonificaciones ERP (src/Http/Bonuses/BonusesRouter.php)
 * =============================================================================
 */

require_once __DIR__ . '/BonusConfigurationModule.php';
require_once __DIR__ . '/HelperBonusModule.php';
require_once __DIR__ . '/HelperBonusExportModule.php';
require_once __DIR__ . '/OpenActivityModule.php';
require_once __DIR__ . '/BonusConfigHeader.php';
require_once __DIR__ . '/MaintenanceBonusModule.php';
require_once __DIR__ . '/BonusPreviewComponent.php';

// =============================================================================
// Módulo HTTP · Bonificaciones ERP (src/Http/BonusesModule.php)
// =============================================================================

function handleBonusesRoutes(string $path, string $method, PDO $erpPdo, ?PDO $trzPdo = null, ?ReceptionService $service = null): bool
{
    if ($trzPdo === null) {
        $trzPdo = Db::trzPdo();
    }
    if ($service === null) {
        $service = new ReceptionService($trzPdo, $erpPdo);
    }

// =============================================================================
// ERP · Bonificaciones
//
// Pantalla principal: /bonificaciones?view=...
// - view=configuracion: mantención de tablas base (tramos Flexo, tarifas por unidad, etc.)
// - view=bonoflexo: previsualización y descarga de planilla (CSV/XLS) para el período 26–25
//
// Nota: el cálculo de período 26–25 y la consulta ERP para Flexo están encapsulados en
// ReceptionService (getBonusPeriodByMonthFinal / listErpFlexoProductionForBonusPeriod).
// =============================================================================
if (handleMaintenanceBonusRoutes($path, $method, $erpPdo, $trzPdo)) {
    exit;
}
if (str_starts_with($path, '/bonificaciones/config/') && $method === 'POST') {
    requireBonusConfigurationEditAccess();
}
if ($path === '/bonificaciones/config/helper-save' && $method === 'POST') {
    handleHelperBonusConfiguration($service, new BonusConfigurationService($trzPdo));
}
if ($path === '/bonificaciones/config/helper-recipients-save' && $method === 'POST') {
    handleHelperBonusRecipients($service, new BonusConfigurationService($trzPdo));
}
if ($path === '/bonificaciones/ayudante/evaluation-save' && $method === 'POST') {
    handleHelperBonusEvaluationSave($service);
}
if ($path === '/bonificaciones/ayudante/export-xls' && $method === 'GET') {
    handleHelperBonusExcelExport($service);
}
if (in_array($path, [
    '/bonificaciones/config/bonus-policy',
    '/bonificaciones/config/bonus-measure',
    '/bonificaciones/config/bonus-measure-disable',
], true)) {
    handleBonusConfigurationRoutes($path, $method, new BonusConfigurationService($trzPdo));
}

if ($path === '/bonificaciones' && $method === 'GET') {
    $body = bonusFilterStyles();
    $view = strtolower(trim((string)($_GET['view'] ?? '')));
    $viewLabel = 'Bonificaciones';
    if ($view === 'bonoflexo') { $viewLabel = 'Bonoflexo'; }
    elseif ($view === 'bonoseri') { $viewLabel = 'Bono seri'; }
    elseif ($view === 'bonocys') { $viewLabel = 'Bono CYS'; }
    elseif ($view === 'bonopulp') { $viewLabel = 'Bono pulp'; }
    elseif ($view === 'bonoayudante') { $viewLabel = 'Bono ayudante'; }
    elseif ($view === 'configuracion') { $viewLabel = 'Configuración'; }
    if ($view === 'configuracion') {
        // -----------------------------------------------------------------------------
        // Configuración
        //
        // - bonus_code: determina qué tabla/forma de cálculo se configura.
        // - Flexo usa tramos (bonus_brackets) + “Compartido” por operador (bonus_operator_factors).
        // - Serigrafía / CYS / otros usan tarifas por unidad (bonus_unit_rates).
        // -----------------------------------------------------------------------------
        $bonusCode = strtolower(trim((string)($_GET['bonus'] ?? 'bonoflexo')));
        $bonusCodes = $service->listBonusCodes();
        if (!in_array($bonusCode, $bonusCodes, true)) {
            $bonusCode = 'bonoflexo';
        }
        $bonusLabels = [
            'bonoflexo' => 'Bonoflexo',
            'bonoseri' => 'Bono seri',
            'bonocys' => 'Bono CYS',
            'bonopulp' => 'Bono pulp',
            'bonoayudante' => 'Bono ayudante',
            'bonomantencion' => 'Bono mantención',
        ];
        $selectedLabel = (string)($bonusLabels[$bonusCode] ?? $bonusCode);
        $canEditBonuses = canEditBonusConfiguration();
        $body .= '<div id="bonus-config-page"><style>
            #bonus-config-page .card,#bonus-config-page .legacy-sheet-card{border:1px solid #e2e8f0;border-radius:14px;background:#fff;box-shadow:0 4px 16px rgba(15,23,42,.04);overflow:hidden}
            #bonus-config-page .legacy-sheet-table{border-collapse:collapse;width:100%;font-size:13px}
            #bonus-config-page .legacy-sheet-table th{background:#f1f5f9;color:#334155;padding:12px 16px;font-weight:800;border-color:#e2e8f0}
            #bonus-config-page .legacy-sheet-table td{border-color:#e2e8f0;padding:10px 14px}
            #bonus-config-page .legacy-sheet-table input,#bonus-config-page .legacy-sheet-table select{border-radius:8px!important;padding:9px 12px!important}
            #bonus-config-page input:disabled,#bonus-config-page select:disabled{background:#f1f5f9;color:#64748b}
            #bonus-config-page button:disabled{opacity:.5;cursor:not-allowed}
            #bonus-config-page .bcfg-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}
            #bonus-config-page .bcfg-head h2{margin:0;font-size:20px;font-weight:900;color:#0f172a;letter-spacing:-.01em}
            #bonus-config-page .bcfg-head p{margin:4px 0 0;color:#64748b;font-size:13px}
            #bonus-config-page .bcfg-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;font-size:12px;font-weight:800}
            #bonus-config-page .bcfg-pill.on{background:#dcfce7;color:#166534}
            #bonus-config-page .bcfg-pill.off{background:#f1f5f9;color:#64748b}
            #bonus-config-page .bcfg-select{width:min(380px,100%);height:40px;padding:0 12px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;font-size:14px;font-weight:700;color:#0f172a}
            #bonus-config-page .bcfg-select:focus,#bonus-config-page .legacy-sheet-table input:focus,#bonus-config-page .legacy-sheet-table select:focus{outline:2px solid #5eead4;outline-offset:1px;border-color:#14b8a6!important}
            #bonus-config-page .bcfg-scroll{max-height:62vh;overflow:auto}
            #bonus-config-page .bcfg-scroll .legacy-label-cell{position:sticky;top:0;z-index:2}
            #bonus-config-page .bcfg-table td{padding:8px 14px!important;vertical-align:middle}
            #bonus-config-page .bcfg-table tbody tr:hover td.legacy-value-cell{background:#f0fdfa}
            #bonus-config-page .bcfg-num{text-align:right!important;font-variant-numeric:tabular-nums}
            #bonus-config-page .bcfg-actions{display:flex;gap:6px;justify-content:flex-end;align-items:center}
            #bonus-config-page .bcfg-actions form{margin:0}
            #bonus-config-page .bcfg-btn{padding:5px 12px!important;font-size:12px!important;min-width:0!important;border-radius:8px!important;line-height:1.4}
            #bonus-config-page .bcfg-btn.danger{background:#fff!important;color:#b91c1c!important;border:1px solid #fca5a5!important}
            #bonus-config-page .bcfg-btn.danger:hover{background:#fee2e2!important}
            #bonus-config-page .bcfg-row{display:grid;grid-template-columns:3fr 2fr;gap:18px;align-items:center;padding:10px 4px;border-bottom:1px solid #f1f5f9}
            #bonus-config-page .bcfg-row3{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(150px,1fr) minmax(150px,1fr);gap:18px;align-items:center;padding:10px 4px;border-bottom:1px solid #f1f5f9}
            #bonus-config-page .bcfg-row input,#bonus-config-page .bcfg-row3 input{width:100%;box-sizing:border-box}
            #bonus-config-page .bcfg-form4{margin:0;padding:12px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr)) 200px;gap:10px;align-items:end}
            #bonus-config-page .legacy-sheet-head{background:#f8fafc;color:#0f172a;border-bottom:1px solid #e2e8f0;border-left:4px solid #00A9A6;padding:13px 18px}
            #bonus-config-page .legacy-sheet-head-title{font-size:15px;font-weight:900}
            #bonus-config-page .legacy-sheet-head-arrow{border-color:#64748b}
            #bonus-config-page .legacy-sheet-card{background:#fff}
            #bonus-config-page .legacy-label-cell{background:#f8fafc;color:#475569;font-size:11px;text-transform:uppercase;letter-spacing:.05em;font-weight:800;border-left:0;border-right:0}
            #bonus-config-page .legacy-sheet-table td{border-left:0;border-right:0}
            #bonus-config-page .card{padding:16px 20px}
            #bonus-config-page .legacy-sheet-card:has(.bcfg-row),#bonus-config-page .legacy-sheet-card:has(.bcfg-row3){max-width:880px}
            #bonus-config-page .bcfg-split{display:grid;grid-template-columns:minmax(300px,380px) minmax(0,1fr);gap:16px;align-items:start}
            #bonus-config-page .bcfg-split .bcfg-form4{grid-template-columns:1fr;gap:12px;padding:16px}
            #bonus-config-page .bcfg-split .bcfg-form4 .muted{font-weight:800;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
            @media(max-width:900px){#bonus-config-page .bcfg-split{grid-template-columns:1fr}}
            @media(max-width:900px){#bonus-config-page .bcfg-form4{grid-template-columns:1fr 1fr}#bonus-config-page .bcfg-row,#bonus-config-page .bcfg-row3{grid-template-columns:1fr;gap:8px}}
        </style>';
        if (in_array($bonusCode, ['bonoseri','bonocys','bonoayudante'], true)) {
            $bonusConfigMonth = BonusConfigurationService::monthForDate(date('Y-m-d'), date('Y-m'));
            $requestedBonusMonth = trim((string)($_GET['month'] ?? $bonusConfigMonth));
            if (preg_match('/^(\d{4})-(\d{2})$/', $requestedBonusMonth, $bonusMonthParts)
                && checkdate((int)$bonusMonthParts[2], 1, (int)$bonusMonthParts[1])) {
                $bonusConfigMonth = $requestedBonusMonth;
            }
            $bonusConfig = new BonusConfigurationService($trzPdo);
        }

        // Tarifas por unidad (si aplican para el bono seleccionado).
        $unitRates = $service->getBonusUnitRates($bonusCode);

        // Tramos (Solo Flexo): Alta/edición/eliminación de rangos “Desde/Hasta/Monto”.
        $editId = (int)($_GET['edit'] ?? 0);
        $rows = $service->listBonusBrackets($bonusCode);
        $editRow = null;
        if ($editId > 0) {
            foreach ($rows as $r) {
                if ((int)($r['id'] ?? 0) === $editId) {
                    $editRow = $r;
                    break;
                }
            }
        }

        $flashMessage = null;
        $flashIsError = false;
        if (isset($_GET['saved']) && $_GET['saved'] === '1') {
            $flashMessage = 'Configuración guardada.';
        } elseif (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
            $flashMessage = 'Tramo eliminado.';
        } elseif (isset($_GET['error']) && trim((string)$_GET['error']) !== '') {
            $flashMessage = trim((string)$_GET['error']);
            $flashIsError = true;
        }

        if ($flashMessage !== null) {
            $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
        }

        $body .= bonusConfigHeader($bonusCode, $canEditBonuses, isset($bonusConfigMonth) ? (string)$bonusConfigMonth : null);

        if (in_array($bonusCode, ['bonoseri','bonocys'], true)) {
            $predefinedBonusMeasures = $bonusCode === 'bonocys' && $erpPdo instanceof PDO
                ? (new ProductionService($erpPdo))->listMedidas() : [];
            $body .= renderBonusProcessConfiguration($bonusConfig, $bonusCode, $bonusConfigMonth, $service->listErpWorkerNames(), $predefinedBonusMeasures);
        }
        if ($bonusCode === 'bonoayudante') {
            $body .= renderHelperBonusConfiguration($bonusConfigMonth, $service->listErpWorkerNames(),
                $service->getBonusHelperSettings($bonusConfigMonth),
                $service->listBonusHelperMonthlyRows($bonusConfigMonth),
                $bonusConfig->listHistory($bonusConfigMonth, 'bonoayudante'));
        }

        if ($bonusCode === 'bonoseri') {
            // Serigrafía: bono por unidad con 2 tiers (menor a 3000 / 3000 o más).
            $defaultRates = [
                'bolsas' => ['LT_3000' => 4.0, 'GTE_3000' => 3.0],
                'des_menor_25cm' => ['LT_3000' => 2.0, 'GTE_3000' => 1.0],
                'sacos' => ['LT_3000' => 8.0, 'GTE_3000' => 6.0],
            ];
            $effectiveRates = $unitRates !== [] ? $unitRates : $defaultRates;
            $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="3">Bono de producción x unid. (Serigrafía)</th></tr></thead><tbody>';
            $body .= '<tr><td class="legacy-label-cell" style="width:40%">Categoría</td><td class="legacy-label-cell" style="width:30%">Menor a 3000 unid</td><td class="legacy-label-cell" style="width:30%">3.000 unid o más</td></tr>';
            $body .= '<tr><td colspan="3" class="legacy-value-cell" style="padding:0">';
            $body .= '<form method="post" action="/bonificaciones/config/save-flexo-unit" style="margin:0;padding:12px;display:grid;grid-template-columns:1fr;gap:10px">';
            $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
            $body .= '<input type="hidden" name="bonus_code" value="bonoseri">';

            $catLabels = [
                'bolsas' => 'Bolsas',
                'des_menor_25cm' => 'Productos con des. menor a 25cm',
                'sacos' => 'Sacos',
            ];
            foreach ($catLabels as $catCode => $catLabel) {
                $lt = (float)($effectiveRates[$catCode]['LT_3000'] ?? (float)($defaultRates[$catCode]['LT_3000'] ?? 0.0));
                $gte = (float)($effectiveRates[$catCode]['GTE_3000'] ?? (float)($defaultRates[$catCode]['GTE_3000'] ?? 0.0));
                $body .= '<div class="bcfg-row3">';
                $body .= '<div><div class="muted" style="margin-bottom:4px">Categoría</div><div style="font-weight:800">' . h($catLabel) . '</div><input type="hidden" name="cat_codes[]" value="' . h($catCode) . '"></div>';
                $body .= '<div><div class="muted" style="margin-bottom:4px">CLP / unid.</div><input type="number" name="rate_lt_3000[' . h($catCode) . ']" min="0" step="0.01" required value="' . h(number_format($lt, 2, '.', '')) . '" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box"></div>';
                $body .= '<div><div class="muted" style="margin-bottom:4px">CLP / unid.</div><input type="number" name="rate_gte_3000[' . h($catCode) . ']" min="0" step="0.01" required value="' . h(number_format($gte, 2, '.', '')) . '" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box"></div>';
                $body .= '</div>';
            }

            $body .= '<div style="display:flex;justify-content:flex-end">';
            $body .= '<button class="btn" type="submit" style="min-width:220px">Guardar bono x unid.</button>';
            $body .= '</div>';
            $body .= '</form>';
            $body .= '</td></tr>';
            $body .= '</tbody></table></div>';
        }

        if ($bonusCode === 'bonocys') {
            $defaultRates = [
                'bolsas' => ['BASE' => 1.0],
                'sacos' => ['BASE' => 2.0],
                'bolsas_xxl' => ['BASE' => 1.5],
                'basurines_troqueles' => ['BASE' => 0.5],
                'embalaje' => ['BASE' => 0.5],
                'cambio_configuracion' => ['AMOUNT' => 5000.0],
            ];
            $effectiveRates = $unitRates !== [] ? $unitRates : $defaultRates;
            $catLabels = [
                'bolsas' => 'Bolsas',
                'sacos' => 'Sacos',
                'bolsas_xxl' => 'Bolsas XXL',
                'basurines_troqueles' => 'Basurines y troqueles',
                'embalaje' => 'Embalaje',
            ];

            $body .= '<div class="legacy-sheet-card" style="margin-bottom:12px"><table class="legacy-sheet-table"><thead><tr><th colspan="2">Bono por unidad (Corte y Sellado)</th></tr></thead><tbody>';
            $body .= '<tr><td class="legacy-label-cell" style="width:60%">Categoría</td><td class="legacy-label-cell" style="width:40%">Monto (CLP) por cada uno</td></tr>';
            $body .= '<tr><td colspan="2" class="legacy-value-cell" style="padding:0">';
            $body .= '<form method="post" action="/bonificaciones/config/save-cys" style="margin:0;padding:12px;display:grid;grid-template-columns:1fr;gap:10px">';
            $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
            $body .= '<input type="hidden" name="bonus_code" value="bonocys">';
            foreach ($catLabels as $catCode => $catLabel) {
                $rate = (float)($effectiveRates[$catCode]['BASE'] ?? (float)($defaultRates[$catCode]['BASE'] ?? 0.0));
                $body .= '<div class="bcfg-row">';
                $body .= '<div><div class="muted" style="margin-bottom:4px">Categoría</div><div style="font-weight:800">' . h($catLabel) . '</div><input type="hidden" name="cat_codes[]" value="' . h($catCode) . '"></div>';
                $body .= '<div><div class="muted" style="margin-bottom:4px">CLP / unid.</div><input type="number" name="rate_base[' . h($catCode) . ']" min="0" step="0.01" required value="' . h(number_format($rate, 2, '.', '')) . '" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box"></div>';
                $body .= '</div>';
            }
            $cfgAmount = (float)($effectiveRates['cambio_configuracion']['AMOUNT'] ?? (float)($defaultRates['cambio_configuracion']['AMOUNT'] ?? 0.0));
            $body .= '<div class="bcfg-row" style="border-top:1px solid #e5e7eb;margin-top:6px">';
            $body .= '<div><div class="muted" style="margin-bottom:4px">Cambio de configuración</div><div style="font-weight:800">Pago por cada cambio de configuración</div></div>';
            $body .= '<div><div class="muted" style="margin-bottom:4px">CLP</div><input type="number" name="config_change_amount" min="0" step="1" required value="' . h(number_format($cfgAmount, 0, '.', '')) . '" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box"></div>';
            $body .= '</div>';

            $body .= '<div style="display:flex;justify-content:flex-end">';
            $body .= '<button class="btn" type="submit" style="min-width:220px">Guardar bono CYS</button>';
            $body .= '</div>';
            $body .= '</form>';
            $body .= '</td></tr>';
            $body .= '</tbody></table></div>';
        }

        if ($bonusCode === 'bonoflexo') {
            $defaultMonthKey = date('Y-m');
            $today = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
            if ((int)$today->format('j') >= 26) {
                $defaultMonthKey = $today->modify('+1 month')->format('Y-m');
            }
            $flexoCfgMonthKey = trim((string)($_GET['month'] ?? $defaultMonthKey));
            if (!preg_match('/^\d{4}-\d{2}$/', $flexoCfgMonthKey)) {
                $flexoCfgMonthKey = $defaultMonthKey;
            }

            $factorMap = $service->listBonusOperatorFactors('bonoflexo', $flexoCfgMonthKey);
            $coachCfg = $service->getBonusCoachConfig('bonoflexo', $flexoCfgMonthKey);
            $coachName = trim((string)($coachCfg['coach_name'] ?? ''));
            $coachShare = (float)($coachCfg['share_percent'] ?? 0.0);
            if (abs($coachShare - 0.5) >= 0.0001) {
                $coachShare = 0.0;
            }
            $coachTrainees = [];
            foreach ((array)($coachCfg['trainees'] ?? []) as $t) {
                $t = trim((string)$t);
                if ($t !== '') {
                    $coachTrainees[$t] = true;
                }
            }
            $operators = $service->listErpWorkerNames();
            $operators = array_values(array_unique(array_merge($operators, array_keys($factorMap))));
            sort($operators, SORT_NATURAL | SORT_FLAG_CASE);

            $configuredCount = count($factorMap);
            $body .= '<div class="card" style="margin-bottom:12px">';
            $body .= '<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between">';
            $body .= '<div><div style="font-weight:900">Compartido por operador</div><div class="muted" style="margin-top:4px">Mes: <strong>' . h($flexoCfgMonthKey) . '</strong> · Configurados: <strong>' . h((string)$configuredCount) . '</strong> (valores distintos a 1.0)</div></div>';
            $body .= '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end">';
            $body .= '<form method="get" action="/bonificaciones" style="display:flex;gap:8px;align-items:end;margin:0">';
            $body .= '<input type="hidden" name="view" value="configuracion">';
            $body .= '<input type="hidden" name="bonus" value="bonoflexo">';
            $body .= '<div><div class="muted" style="margin-bottom:4px">Mes</div><input type="month" name="month" value="' . h($flexoCfgMonthKey) . '" style="width:170px;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box"></div>';
            $body .= '<button class="btn secondary" type="submit" style="min-width:140px">Cargar mes</button>';
            $body .= '</form>';
            $body .= '<button type="button" class="btn" data-wm-open="flexo-factors" style="min-width:220px">Configurar compartido</button>';
            $body .= '</div>';
            $body .= '</div>';
            $body .= '</div>';

            $body .= '<style>';
            $body .= '.wm-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,0.55);display:none;align-items:flex-start;justify-content:center;z-index:9998;padding:28px 16px;overflow-y:auto}';
            $body .= '.wm-modal-backdrop.is-open{display:flex}';
            $body .= '.wm-modal{width:100%;max-width:860px;background:#fff;border-radius:14px;border:1px solid rgba(15,23,42,0.08);box-shadow:0 24px 80px rgba(0,0,0,0.35);margin:auto 0;overflow:hidden}';
            $body .= '.wm-modal-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;border-bottom:1px solid #e5e7eb;background:linear-gradient(180deg,#f8fafc,#ffffff)}';
            $body .= '.wm-modal-title{font-size:18px;font-weight:900;margin:0;color:#0f172a}';
            $body .= '.wm-modal-close{background:#fff;border:1px solid #d1d5db;color:#111827;border-radius:10px;padding:8px 12px;cursor:pointer;font-weight:800}';
            $body .= '.wm-modal-close:hover{background:#f3f4f6}';
            $body .= '.wm-modal-body{padding:18px;background:#fff;max-height:calc(100vh - 160px);overflow:auto}';
            $body .= '.wm-modal .table-wrap{border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#fff}';
            $body .= '</style>';

            $body .= '<div class="wm-modal-backdrop" data-wm-modal="flexo-factors" style="display:none !important;position:fixed !important;inset:0 !important;z-index:9998 !important">';
            $body .= '<div class="wm-modal">';
            $body .= '<div class="wm-modal-head"><h2 class="wm-modal-title">Compartido por operador</h2><button class="wm-modal-close" type="button" data-wm-close="flexo-factors">Cerrar</button></div>';
            $body .= '<div class="wm-modal-body">';
            $body .= '<div class="muted" style="margin-bottom:12px">Selecciona 1.0 / 0.9 / 0.8 por operador. En la planilla, el bono se multiplica por este valor. La configuración se guarda por mes.</div>';
            $body .= '<form method="post" action="/bonificaciones/config/save-flexo-factors" style="margin:0">';
            $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
            $body .= '<input type="hidden" name="bonus_code" value="bonoflexo">';
            $body .= '<input type="hidden" name="month_key" value="' . h($flexoCfgMonthKey) . '">';
            $body .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">';
            $body .= '<div><div class="muted" style="margin-bottom:4px">Mes (26–25)</div><div style="font-weight:900;padding:8px 10px;border:1px solid #e5e7eb;border-radius:6px;background:#f8fafc">' . h($flexoCfgMonthKey) . '</div></div>';
            $body .= '<div><div class="muted" style="margin-bottom:4px">Mentor (50% adicional)</div>';
            $body .= '<div style="display:grid;grid-template-columns:1fr 160px;gap:10px">';
            $body .= '<select name="coach_name" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box">';
            $body .= '<option value="">Sin mentor</option>';
            foreach ($operators as $opName) {
                $opName = trim((string)$opName);
                if ($opName === '') {
                    continue;
                }
                $sel = $coachName !== '' && strcasecmp($coachName, $opName) === 0 ? ' selected' : '';
                $body .= '<option value="' . h($opName) . '"' . $sel . '>' . h($opName) . '</option>';
            }
            $body .= '</select>';
            $body .= '<select name="coach_share" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box">';
            $body .= '<option value="0"' . ($coachShare <= 0.0 ? ' selected' : '') . '>0%</option>';
            $body .= '<option value="0.5"' . (abs($coachShare - 0.5) < 0.0001 ? ' selected' : '') . '>50%</option>';
            $body .= '</select>';
            $body .= '</div></div>';
            $body .= '</div>';
            $body .= '<div class="table-wrap"><table class="legacy-sheet-table" style="margin:0"><thead><tr><th style="width:60%">Operador</th><th style="width:20%">Compartido</th><th style="width:20%">Mentoría</th></tr></thead><tbody>';
            if ($operators === []) {
                $body .= '<tr><td colspan="3" class="legacy-value-cell" style="text-align:center"><span class="muted">Sin operadores disponibles.</span></td></tr>';
            }
            foreach ($operators as $op) {
                $op = trim((string)$op);
                if ($op === '') {
                    continue;
                }
                $current = (float)($factorMap[$op] ?? 1.0);
                $body .= '<tr>';
                $body .= '<td class="legacy-value-cell" style="font-weight:800">' . h($op) . '<input type="hidden" name="operators[]" value="' . h($op) . '"></td>';
                $body .= '<td class="legacy-value-cell">';
                $body .= '<select name="factors[]" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box">';
                $opts = ['1.0' => '1.0', '0.9' => '0.9', '0.8' => '0.8'];
                foreach ($opts as $val => $lab) {
                    $selected = abs($current - (float)$val) < 0.0001 ? ' selected' : '';
                    $body .= '<option value="' . h((string)$val) . '"' . $selected . '>' . h($lab) . '</option>';
                }
                $body .= '</select>';
                $body .= '</td>';
                $checked = isset($coachTrainees[$op]) ? ' checked' : '';
                $disabled = $coachName !== '' && strcasecmp($coachName, $op) === 0 ? ' disabled' : '';
                $body .= '<td class="legacy-value-cell center"><input type="checkbox" name="trainees[]" value="' . h($op) . '"' . $checked . $disabled . '></td>';
                $body .= '</tr>';
            }
            $body .= '</tbody></table></div>';
            $body .= '<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px">';
            $body .= '<button class="btn secondary" type="button" data-wm-close="flexo-factors" style="min-width:140px">Cancelar</button>';
            $body .= '<button class="btn" type="submit" style="min-width:220px">Guardar</button>';
            $body .= '</div>';
            $body .= '</form>';
            $body .= '</div></div></div>';

            $body .= '<div class="bcfg-split"><div><div class="legacy-sheet-card"><table class="legacy-sheet-table"><thead><tr><th>' . ($editRow !== null ? 'Editar tramo' : 'Agregar tramo') . ' · ' . h($selectedLabel) . '</th></tr></thead><tbody>';
            $body .= '<tr><td class="legacy-value-cell" style="padding:0">';
            $body .= '<form method="post" action="/bonificaciones/config/save" class="bcfg-form4">';
            $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
            $body .= '<input type="hidden" name="bonus_code" value="' . h($bonusCode) . '">';
            $body .= '<input type="hidden" name="id" value="' . h($editRow !== null ? (string)($editRow['id'] ?? '0') : '0') . '">';
            $body .= '<div><div class="muted" style="margin-bottom:4px">Desde</div><input type="number" name="range_from" min="0" step="1" required value="' . h($editRow !== null ? (string)($editRow['range_from'] ?? '0') : '0') . '" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box"></div>';
            $body .= '<div><div class="muted" style="margin-bottom:4px">Hasta (vacío = sin tope)</div><input type="number" name="range_to" min="0" step="1" value="' . h($editRow !== null && ($editRow['range_to'] ?? null) !== null ? (string)$editRow['range_to'] : '') . '" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box"></div>';
            $body .= '<div><div class="muted" style="margin-bottom:4px">Monto CLP</div><input type="number" name="amount_clp" min="0" step="1" required value="' . h($editRow !== null ? (string)($editRow['amount_clp'] ?? '0') : '0') . '" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;box-sizing:border-box"></div>';
            $body .= '<div style="display:flex;gap:8px;justify-content:flex-end">';
            if ($editRow !== null) {
                $body .= '<a class="btn secondary" href="/bonificaciones?view=configuracion&bonus=' . h($bonusCode) . '" style="min-width:110px;text-align:center">Cancelar</a>';
                $body .= '<button class="btn" type="submit" style="min-width:110px">Actualizar</button>';
            } else {
                $body .= '<button class="btn" type="submit" style="min-width:110px">Agregar</button>';
            }
            $body .= '</div>';
            $body .= '</form>';
            $body .= '</td></tr>';
            $body .= '</tbody></table></div></div>';
            $body .= '<div class="legacy-sheet-card"><div class="table-wrap bcfg-scroll"><table class="legacy-sheet-table bcfg-table" style="margin:0"><thead><tr><th colspan="4">Tramos registrados (' . count($rows) . ')</th></tr></thead><tbody>';
            $body .= '<tr><td class="legacy-label-cell" style="width:22%">Desde (unid.)</td><td class="legacy-label-cell" style="width:22%">Hasta (unid.)</td><td class="legacy-label-cell bcfg-num" style="width:26%">Monto (CLP)</td><td class="legacy-label-cell" style="width:30%;text-align:right">Opciones</td></tr>';
            if ($rows === []) {
                $body .= '<tr><td colspan="4" class="legacy-value-cell" style="text-align:center;padding:26px"><span class="muted">Aún no hay tramos configurados. Agrega el primero con el formulario de arriba.</span></td></tr>';
            }
            foreach ($rows as $r) {
                $id = (int)($r['id'] ?? 0);
                $from = (int)($r['range_from'] ?? 0);
                $to = ($r['range_to'] ?? null) !== null ? (int)$r['range_to'] : null;
                $amount = (string)($r['amount_clp'] ?? '0');
                $body .= '<tr>';
                $body .= '<td class="legacy-value-cell" style="font-variant-numeric:tabular-nums">' . h(number_format($from, 0, ',', '.')) . '</td>';
                $body .= '<td class="legacy-value-cell" style="font-variant-numeric:tabular-nums">' . ($to !== null ? h(number_format($to, 0, ',', '.')) : '<span class="muted">Sin tope</span>') . '</td>';
                $body .= '<td class="legacy-value-cell bcfg-num"><strong>$ ' . h(number_format((float)$amount, 0, ',', '.')) . '</strong></td>';
                $body .= '<td class="legacy-value-cell"><div class="bcfg-actions">';
                $body .= '<a class="btn secondary bcfg-btn" href="/bonificaciones?view=configuracion&bonus=' . h($bonusCode) . '&edit=' . $id . '">Editar</a>';
                $body .= '<form method="post" action="/bonificaciones/config/delete" onsubmit="return confirm(\'¿Eliminar este tramo?\')">';
                $body .= '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
                $body .= '<input type="hidden" name="bonus_code" value="' . h($bonusCode) . '">';
                $body .= '<input type="hidden" name="id" value="' . $id . '">';
                $body .= '<button class="btn secondary bcfg-btn danger" type="submit">Eliminar</button>';
                $body .= '</form>';
                $body .= '</div></td>';
                $body .= '</tr>';
            }
            $body .= '</tbody></table></div></div></div>';
        }

        $body .= '<script>(function(){var sel=document.getElementById("bonus-config-select");if(!sel)return;sel.addEventListener("change",function(){var v=sel.value||"bonoflexo";window.location.href="/bonificaciones?view=configuracion&bonus="+encodeURIComponent(v);});})();</script>';
        if (!$canEditBonuses) {
            $body .= '<script>document.querySelectorAll("#bonus-config-page form[method=post]").forEach(function(form){Array.from(form.elements).forEach(function(field){field.disabled=true;});});</script>';
        }
        $body .= '<script>(function(){if(window.__wmBound)return;window.__wmBound=true;function openWm(id){var m=document.querySelector("[data-wm-modal=\\x27"+id+"\\x27]");if(m){m.classList.add("is-open");m.style.display="flex";document.body.style.overflow="hidden";}}function closeWm(id){var m=document.querySelector("[data-wm-modal=\\x27"+id+"\\x27]");if(m){m.classList.remove("is-open");m.style.display="none";document.body.style.overflow="";}}document.addEventListener("click",function(e){var t=e.target;var openr=t.closest&&t.closest("[data-wm-open]");if(openr){e.preventDefault();openWm(openr.getAttribute("data-wm-open"));return;}var closer=t.closest&&t.closest("[data-wm-close]");if(closer){e.preventDefault();closeWm(closer.getAttribute("data-wm-close"));return;}if(t.classList&&t.classList.contains("wm-modal-backdrop")&&t.getAttribute("data-wm-modal")){if(t===e.target){closeWm(t.getAttribute("data-wm-modal"));}}});})();</script>';
        $body .= '</div>';
    } elseif ($view === 'bonocys') {
        $defaultMonthKey = date('Y-m');
        $today = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
        if ((int)$today->format('j') >= 26) {
            $defaultMonthKey = $today->modify('+1 month')->format('Y-m');
        }
        $filterType = trim((string)($_GET['filter_type'] ?? 'period'));
        if ($filterType !== 'range') {
            $filterType = 'period';
        }
        $monthKey = trim((string)($_GET['month'] ?? $defaultMonthKey));
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            $monthKey = $defaultMonthKey;
        }
        $startDate = trim((string)($_GET['start_date'] ?? ''));
        $endDate = trim((string)($_GET['end_date'] ?? ''));
        if ($startDate === '' || $endDate === '') {
            $startDate = date('Y-m-d', strtotime('-6 days'));
            $endDate = date('Y-m-d');
        }
        $selectedOperator = trim((string)($_GET['operator'] ?? ''));
        $selectedCc = trim((string)($_GET['cc'] ?? ''));

        $flashMessage = null;
        $flashIsError = false;
        if (isset($_GET['error']) && trim((string)$_GET['error']) !== '') {
            $flashMessage = trim((string)$_GET['error']);
            $flashIsError = true;
        }

        if ($flashMessage !== null) {
            $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
        }

        $period = $service->resolveBonusFilterPeriod($filterType, $monthKey, $startDate, $endDate);
        $periodLabel = (string)($period['label'] ?? '');

        $operatorNames = [];
        $cysEquipotypeIds = $service->getErpEquipotypeIdsForBonusCode('bonocys');
        $cysEquipoIds = $cysEquipotypeIds === null ? $service->getErpEquipoIdsForBonusCode('bonocys') : null;
        if ($cysEquipotypeIds !== null || $cysEquipoIds !== null) {
            $operatorsResult = $service->listErpBonusOperatorNamesForPeriod($period, $cysEquipotypeIds, $cysEquipoIds);
            if (($operatorsResult['ok'] ?? false) === true) {
                $operatorNames = (array)($operatorsResult['names'] ?? []);
            }
        }

        $body .= '<div class="erp-filter-card">';
        $body .= '<div style="font-weight:900;font-size:16px;color:#1e293b;margin-bottom:6px">Planilla Corte y Sellado</div>';
        $body .= '<form method="get" action="/bonificaciones" class="erp-filter-form" id="bonus-filter-form-cys">';
        $body .= '<input type="hidden" name="view" value="bonocys">';
        $body .= '<div class="erp-filter-field"><label for="filter_type_cys">Modo de consulta</label><select name="filter_type" id="filter_type_cys" class="erp-filter-select">';
        $body .= '<option value="period"' . ($filterType === 'period' ? ' selected' : '') . '>Por Período (26 al 25)</option>';
        $body .= '<option value="range"' . ($filterType === 'range' ? ' selected' : '') . '>Por Rango (Inicio - Fin)</option>';
        $body .= '</select></div>';

        $body .= '<div class="erp-filter-field bonus-filter-period-box" id="period-box-cys"' . ($filterType === 'range' ? ' style="display:none"' : '') . '><label for="month_cys">Mes (Ciclo 26–25)</label><input type="month" name="month" id="month_cys" value="' . h($monthKey) . '"></div>';
        $body .= '<div class="erp-filter-field bonus-filter-range-box" id="range-box-start-cys"' . ($filterType !== 'range' ? ' style="display:none"' : '') . '><label for="start_date_cys">Fecha Inicio</label><input type="date" name="start_date" id="start_date_cys" value="' . h($startDate) . '"></div>';
        $body .= '<div class="erp-filter-field bonus-filter-range-box" id="range-box-end-cys"' . ($filterType !== 'range' ? ' style="display:none"' : '') . '><label for="end_date_cys">Fecha Final</label><input type="date" name="end_date" id="end_date_cys" value="' . h($endDate) . '"></div>';

        $body .= '<div class="erp-filter-field"><label for="operator_cys">Operador</label><select name="operator" id="operator_cys">';
        $body .= '<option value="">Todos</option>';
        foreach ($operatorNames as $operatorName) {
            $selected = $selectedOperator === $operatorName ? ' selected' : '';
            $body .= '<option value="' . h($operatorName) . '"' . $selected . '>' . h($operatorName) . '</option>';
        }
        $body .= '</select></div>';
        $body .= '<div class="erp-filter-field erp-filter-actions"><button class="btn btn-filter-apply" type="submit">Consultar</button></div>';
        $body .= '</form>';

        $body .= '<div class="erp-filter-badge" style="margin-top:14px"><strong>' . h($periodLabel) . '</strong></div>';

        $exportParams = ['filter_type' => $filterType];
        if ($filterType === 'range') {
            $exportParams['start_date'] = $startDate;
            $exportParams['end_date'] = $endDate;
        } else {
            $exportParams['month'] = $monthKey;
        }
        if ($selectedOperator !== '') {
            $exportParams['operator'] = $selectedOperator;
        }
        $exportXlsUrl = '/bonificaciones/cys/export-xls?' . http_build_query($exportParams);

        $body .= '<div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">';
        $body .= '<a class="btn-filter-excel" href="' . h($exportXlsUrl) . '" style="min-width:280px;justify-content:center">📊 Descargar planilla CYS (Excel)</a>';
        $body .= '</div>';
        $body .= '</div>';

        // Vista previa COMPLETA del período (antes solo 12 filas planas): resumen, ranking por operador y detalle.
        $preview = $service->listErpCysProductionForBonusPeriod(
            $period,
            $selectedOperator !== '' ? $selectedOperator : null
        );
        if (($preview['ok'] ?? false) === true) {
            $rowsPreview = (array)($preview['rows'] ?? []);
            $cysBonusConfig = new BonusConfigurationService($trzPdo);
            $rowsPreview = array_map(static fn(array $row): array => $cysBonusConfig->applyMeasureRule($row, $monthKey), $rowsPreview);
            // Tipo de producto: SAC si la descripción/ítem/tipo lo indica; si hay categoría de bono, se usa su nombre.
            $cysTypeOf = static function (array $r): string {
                $bagUp = strtoupper(trim((string)($r['bag_type'] ?? '')));
                $typeUp = $bagUp !== '' ? $bagUp : strtoupper(trim((string)($r['product_type'] ?? '')));
                $descUp = strtoupper(trim((string)($r['erp_desc'] ?? '')));
                $itemUp = strtoupper(trim((string)($r['item_title'] ?? '')));
                if (str_contains($typeUp, 'SAC') || str_contains($descUp, 'SAC') || str_contains($itemUp, 'SAC')) {
                    $typeUp = 'SAC';
                }
                if (isset($r['bonus_category'])) {
                    $typeUp = BonusConfigurationService::CATEGORIES[$r['bonus_category']] ?? $typeUp;
                }
                return $typeUp;
            };
            $body .= bonusPreviewStyles();
            $body .= bonusRenderPreviewPanel($rowsPreview, [
                'columns' => [
                    ['label' => 'Fecha', 'align' => 'c', 'value' => static fn(array $r): string => bonusPreviewDate((string)($r['event_date'] ?? ''))],
                    ['label' => 'N° CC', 'align' => 'c', 'value' => static fn(array $r): string => (string)($r['cost_center'] ?? '')],
                    ['label' => 'Cliente', 'value' => static fn(array $r): string => (string)($r['client_label'] ?? '')],
                    ['label' => 'Selladora', 'align' => 'c', 'value' => static fn(array $r): string => (string)($r['printer_no'] ?? '')],
                    ['label' => 'Tipo', 'align' => 'c', 'value' => $cysTypeOf],
                    ['label' => 'Medidas bonos', 'align' => 'c', 'value' => static fn(array $r): string => (isset($r['bonus_original_measure']) ? $r['bonus_original_measure'] . ' -> ' : '') . (string)($r['measure_cm'] ?? '')],
                    ['label' => 'Unidades', 'align' => 'r', 'strong' => true, 'value' => static fn(array $r): string => bonusPreviewInt((float)($r['produced_units'] ?? 0))],
                ],
                'units_key' => 'produced_units',
                'requested_key' => null,
                'units_label' => 'Producidas',
                'selected_operator' => $selectedOperator,
                'operator_url' => static fn(string $op): string => '/bonificaciones?' . http_build_query(array_merge($_GET, ['view' => 'bonocys', 'operator' => $op])),
                'clear_url' => '/bonificaciones?' . http_build_query(array_diff_key(array_merge($_GET, ['view' => 'bonocys']), ['operator' => 1])),
            ]);
        }
    } elseif ($view === 'bonoseri') {
        $defaultMonthKey = date('Y-m');
        $today = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
        if ((int)$today->format('j') >= 26) {
            $defaultMonthKey = $today->modify('+1 month')->format('Y-m');
        }
        $filterType = trim((string)($_GET['filter_type'] ?? 'period'));
        if ($filterType !== 'range') {
            $filterType = 'period';
        }
        $monthKey = trim((string)($_GET['month'] ?? $defaultMonthKey));
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            $monthKey = $defaultMonthKey;
        }
        $startDate = trim((string)($_GET['start_date'] ?? ''));
        $endDate = trim((string)($_GET['end_date'] ?? ''));
        if ($startDate === '' || $endDate === '') {
            $startDate = date('Y-m-d', strtotime('-6 days'));
            $endDate = date('Y-m-d');
        }
        $selectedOperator = trim((string)($_GET['operator'] ?? ''));
        $selectedCc = trim((string)($_GET['cc'] ?? ''));

        $flashMessage = null;
        $flashIsError = false;
        if (isset($_GET['error']) && trim((string)$_GET['error']) !== '') {
            $flashMessage = trim((string)$_GET['error']);
            $flashIsError = true;
        }

        if ($flashMessage !== null) {
            $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
        }

        $period = $service->resolveBonusFilterPeriod($filterType, $monthKey, $startDate, $endDate);
        $periodLabel = (string)($period['label'] ?? '');

        $operatorNames = [];
        $seriEquipotypeIds = $service->getErpEquipotypeIdsForBonusCode('bonoseri');
        $seriEquipoIds = $service->getErpEquipoIdsForBonusCode('bonoseri');
        if ($seriEquipotypeIds !== null || $seriEquipoIds !== null) {
            $operatorsResult = $service->listErpBonusOperatorNamesForPeriod($period, $seriEquipotypeIds, $seriEquipoIds);
            if (($operatorsResult['ok'] ?? false) === true) {
                $operatorNames = (array)($operatorsResult['names'] ?? []);
            }
        }

        $body .= '<div class="erp-filter-card">';
        $body .= '<div style="font-weight:900;font-size:16px;color:#1e293b;margin-bottom:6px">Planilla Serigrafía</div>';
        $body .= '<form method="get" action="/bonificaciones" class="erp-filter-form" id="bonus-filter-form-seri">';
        $body .= '<input type="hidden" name="view" value="bonoseri">';
        $body .= '<div class="erp-filter-field"><label for="filter_type_seri">Modo de consulta</label><select name="filter_type" id="filter_type_seri" class="erp-filter-select">';
        $body .= '<option value="period"' . ($filterType === 'period' ? ' selected' : '') . '>Por Período (26 al 25)</option>';
        $body .= '<option value="range"' . ($filterType === 'range' ? ' selected' : '') . '>Por Rango (Inicio - Fin)</option>';
        $body .= '</select></div>';

        $body .= '<div class="erp-filter-field bonus-filter-period-box" id="period-box-seri"' . ($filterType === 'range' ? ' style="display:none"' : '') . '><label for="month_seri">Mes (Ciclo 26–25)</label><input type="month" name="month" id="month_seri" value="' . h($monthKey) . '"></div>';
        $body .= '<div class="erp-filter-field bonus-filter-range-box" id="range-box-start-seri"' . ($filterType !== 'range' ? ' style="display:none"' : '') . '><label for="start_date_seri">Fecha Inicio</label><input type="date" name="start_date" id="start_date_seri" value="' . h($startDate) . '"></div>';
        $body .= '<div class="erp-filter-field bonus-filter-range-box" id="range-box-end-seri"' . ($filterType !== 'range' ? ' style="display:none"' : '') . '><label for="end_date_seri">Fecha Final</label><input type="date" name="end_date" id="end_date_seri" value="' . h($endDate) . '"></div>';

        $body .= '<div class="erp-filter-field"><label for="operator_seri">Operador</label><select name="operator" id="operator_seri">';
        $body .= '<option value="">Todos</option>';
        foreach ($operatorNames as $operatorName) {
            $selected = $selectedOperator === $operatorName ? ' selected' : '';
            $body .= '<option value="' . h($operatorName) . '"' . $selected . '>' . h($operatorName) . '</option>';
        }
        $body .= '</select></div>';
        $body .= '<div class="erp-filter-field erp-filter-actions"><button class="btn btn-filter-apply" type="submit">Consultar</button></div>';
        $body .= '</form>';

        $body .= '<div class="erp-filter-badge" style="margin-top:14px"><strong>' . h($periodLabel) . '</strong></div>';

        $exportParams = ['filter_type' => $filterType];
        if ($filterType === 'range') {
            $exportParams['start_date'] = $startDate;
            $exportParams['end_date'] = $endDate;
        } else {
            $exportParams['month'] = $monthKey;
        }
        if ($selectedOperator !== '') {
            $exportParams['operator'] = $selectedOperator;
        }
        $exportXlsUrl = '/bonificaciones/seri/export-xls?' . http_build_query($exportParams);

        $body .= '<div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">';
        $body .= '<a class="btn-filter-excel" href="' . h($exportXlsUrl) . '" style="min-width:260px;justify-content:center">📊 Descargar planilla Seri (Excel)</a>';
        $body .= '</div>';
        $body .= '</div>';

        // Vista previa COMPLETA del período (antes solo 12 filas planas): resumen, ranking por operador y detalle.
        $preview = $service->listErpSeriProductionForBonusPeriod(
            $period,
            $selectedOperator !== '' ? $selectedOperator : null
        );
        if (($preview['ok'] ?? false) === true) {
            $rowsPreview = (array)($preview['rows'] ?? []);
            // Tipo de producto: SAC (sacos) o las 3 primeras letras del tipo (BOL por defecto).
            $seriTypeOf = static function (array $r): string {
                $descUp = strtoupper(trim((string)($r['erp_desc'] ?? '')));
                $itemUp = strtoupper(trim((string)($r['item_title'] ?? '')));
                $typeUp = strtoupper(trim((string)($r['bag_type'] ?? '')));
                if ($typeUp === '') {
                    $typeUp = strtoupper(trim((string)($r['product_type'] ?? '')));
                }
                if (str_contains($descUp, 'SAC') || str_contains($itemUp, 'SAC') || str_starts_with($typeUp, 'SAC')) {
                    return 'SAC';
                }
                return strtoupper(substr($typeUp !== '' ? $typeUp : 'BOL', 0, 3));
            };
            $body .= bonusPreviewStyles();
            $body .= bonusRenderPreviewPanel($rowsPreview, [
                'columns' => [
                    ['label' => 'Fecha', 'align' => 'c', 'value' => static fn(array $r): string => bonusPreviewDate((string)($r['event_date'] ?? ''))],
                    ['label' => 'N° CC', 'align' => 'c', 'value' => static fn(array $r): string => (string)($r['cost_center'] ?? '')],
                    ['label' => 'N° OT', 'align' => 'c', 'value' => static fn(array $r): string => (string)($r['work_order_number'] ?? '')],
                    ['label' => 'Cliente', 'value' => static fn(array $r): string => (string)($r['client_label'] ?? '')],
                    ['label' => 'Tipo', 'align' => 'c', 'value' => $seriTypeOf],
                    ['label' => 'Solicitadas', 'align' => 'r', 'value' => static fn(array $r): string => bonusPreviewInt((float)($r['requested_units'] ?? 0))],
                    ['label' => 'Impresas', 'align' => 'r', 'strong' => true, 'value' => static fn(array $r): string => bonusPreviewInt((float)($r['produced_units'] ?? 0))],
                ],
                'units_key' => 'produced_units',
                'requested_key' => 'requested_units',
                'units_label' => 'Impresas',
                'selected_operator' => $selectedOperator,
                'operator_url' => static fn(string $op): string => '/bonificaciones?' . http_build_query(array_merge($_GET, ['view' => 'bonoseri', 'operator' => $op])),
                'clear_url' => '/bonificaciones?' . http_build_query(array_diff_key(array_merge($_GET, ['view' => 'bonoseri']), ['operator' => 1])),
            ]);
        }
    } elseif ($view === 'bonoflexo') {
        $defaultMonthKey = date('Y-m');
        $today = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
        if ((int)$today->format('j') >= 26) {
            $defaultMonthKey = $today->modify('+1 month')->format('Y-m');
        }
        $filterType = trim((string)($_GET['filter_type'] ?? 'period'));
        if ($filterType !== 'range') {
            $filterType = 'period';
        }
        $monthKey = trim((string)($_GET['month'] ?? $defaultMonthKey));
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            $monthKey = $defaultMonthKey;
        }
        $startDate = trim((string)($_GET['start_date'] ?? ''));
        $endDate = trim((string)($_GET['end_date'] ?? ''));
        if ($startDate === '' || $endDate === '') {
            $startDate = date('Y-m-d', strtotime('-6 days'));
            $endDate = date('Y-m-d');
        }
        $selectedOperator = trim((string)($_GET['operator'] ?? ''));
        $selectedCc = trim((string)($_GET['cc'] ?? ''));

        $flashMessage = null;
        $flashIsError = false;
        if (isset($_GET['error']) && trim((string)$_GET['error']) !== '') {
            $flashMessage = trim((string)$_GET['error']);
            $flashIsError = true;
        }

        if ($flashMessage !== null) {
            $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
        }

        $period = $service->resolveBonusFilterPeriod($filterType, $monthKey, $startDate, $endDate);
        $periodLabel = (string)($period['label'] ?? '');

        $operatorNames = [];
        $flexoEquipotypeIds = $service->getErpEquipotypeIdsForBonusCode('bonoflexo');
        $flexoEquipoIds = $flexoEquipotypeIds === null ? $service->getErpEquipoIdsForBonusCode('bonoflexo') : null;
        if ($flexoEquipotypeIds !== null || $flexoEquipoIds !== null) {
            $operatorsResult = $service->listErpBonusOperatorNamesForPeriod(
                $period,
                $flexoEquipotypeIds,
                $flexoEquipoIds
            );
            if (($operatorsResult['ok'] ?? false) === true) {
                $operatorNames = (array)($operatorsResult['names'] ?? []);
            }
        }

        $body .= '<div class="erp-filter-card">';
        $body .= '<div style="font-weight:900;font-size:16px;color:#1e293b;margin-bottom:6px">Planilla Flexo</div>';
        $body .= '<form method="get" action="/bonificaciones" class="erp-filter-form" id="bonus-filter-form-flexo">';
        $body .= '<input type="hidden" name="view" value="bonoflexo">';
        $body .= '<div class="erp-filter-field"><label for="filter_type_flexo">Modo de consulta</label><select name="filter_type" id="filter_type_flexo" class="erp-filter-select">';
        $body .= '<option value="period"' . ($filterType === 'period' ? ' selected' : '') . '>Por Período (26 al 25)</option>';
        $body .= '<option value="range"' . ($filterType === 'range' ? ' selected' : '') . '>Por Rango (Inicio - Fin)</option>';
        $body .= '</select></div>';

        $body .= '<div class="erp-filter-field bonus-filter-period-box" id="period-box-flexo"' . ($filterType === 'range' ? ' style="display:none"' : '') . '><label for="month_flexo">Mes (Ciclo 26–25)</label><input type="month" name="month" id="month_flexo" value="' . h($monthKey) . '"></div>';
        $body .= '<div class="erp-filter-field bonus-filter-range-box" id="range-box-start-flexo"' . ($filterType !== 'range' ? ' style="display:none"' : '') . '><label for="start_date_flexo">Fecha Inicio</label><input type="date" name="start_date" id="start_date_flexo" value="' . h($startDate) . '"></div>';
        $body .= '<div class="erp-filter-field bonus-filter-range-box" id="range-box-end-flexo"' . ($filterType !== 'range' ? ' style="display:none"' : '') . '><label for="end_date_flexo">Fecha Final</label><input type="date" name="end_date" id="end_date_flexo" value="' . h($endDate) . '"></div>';

        $body .= '<div class="erp-filter-field"><label for="operator_flexo">Operador</label><select name="operator" id="operator_flexo">';
        $body .= '<option value="">Todos</option>';
        foreach ($operatorNames as $operatorName) {
            $selected = $selectedOperator === $operatorName ? ' selected' : '';
            $body .= '<option value="' . h($operatorName) . '"' . $selected . '>' . h($operatorName) . '</option>';
        }
        $body .= '</select></div>';
        $body .= '<div class="erp-filter-field"><label for="cc_flexo">N° CC</label><input type="text" name="cc" id="cc_flexo" value="' . h((string)$selectedCc) . '" placeholder="Ej: 12345"></div>';
        $body .= '<div class="erp-filter-field erp-filter-actions"><button class="btn btn-filter-apply" type="submit">Consultar</button></div>';
        $body .= '</form>';

        $body .= '<div class="erp-filter-badge" style="margin-top:14px"><strong>' . h($periodLabel) . '</strong></div>';

        $exportParams = ['filter_type' => $filterType];
        if ($filterType === 'range') {
            $exportParams['start_date'] = $startDate;
            $exportParams['end_date'] = $endDate;
        } else {
            $exportParams['month'] = $monthKey;
        }
        if ($selectedOperator !== '') {
            $exportParams['operator'] = $selectedOperator;
        }
        if ($selectedCc !== '') {
            $exportParams['cc'] = $selectedCc;
        }
        $exportXlsUrl = '/bonificaciones/flexo/export-xls?' . http_build_query($exportParams);

        $body .= '<div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">';
        $body .= '<a class="btn-filter-excel" href="' . h($exportXlsUrl) . '" style="min-width:260px;justify-content:center">📊 Descargar planilla Flexo (Excel)</a>';
        $body .= '</div>';
        $body .= '</div>';

        // Vista previa COMPLETA del período (antes solo 12 filas planas): resumen, ranking por operador y detalle.
        $preview = $service->listErpFlexoProductionForBonusPeriod(
            $period,
            $selectedOperator !== '' ? $selectedOperator : null,
            $selectedCc !== '' ? $selectedCc : null
        );
        if (($preview['ok'] ?? false) !== true) {
            $errList = $preview['errors'] ?? [];
            $error = $errList !== [] ? trim((string)reset($errList)) : '';
            if ($error !== '') {
                $body .= '<div class="err" style="margin-top:12px">' . h($error) . '</div>';
            }
        } else {
            $rowsPreview = (array)($preview['rows'] ?? []);
            if ($selectedOperator !== '') {
                $rowsPreview = array_values(array_filter($rowsPreview, function ($row) use ($selectedOperator) {
                    $name = is_array($row) ? trim((string)($row['operator_name'] ?? '')) : '';
                    return $name === $selectedOperator;
                }));
            }
            if ($selectedCc !== '') {
                $rowsPreview = array_values(array_filter($rowsPreview, function ($row) use ($selectedCc) {
                    $cc = is_array($row) ? trim((string)($row['cost_center'] ?? '')) : '';
                    return $cc === $selectedCc;
                }));
            }
            $body .= bonusPreviewStyles();
            $body .= bonusRenderPreviewPanel($rowsPreview, [
                'columns' => [
                    ['label' => 'Fecha', 'align' => 'c', 'value' => static fn(array $r): string => bonusPreviewDate((string)($r['event_date'] ?? ''))],
                    ['label' => 'N° CC', 'align' => 'c', 'value' => static fn(array $r): string => (string)($r['cost_center'] ?? '')],
                    ['label' => 'N° OT', 'align' => 'c', 'value' => static fn(array $r): string => (string)($r['work_order_number'] ?? '')],
                    ['label' => 'Cliente', 'value' => static fn(array $r): string => (string)($r['client_label'] ?? '')],
                    ['label' => 'Impresora', 'align' => 'c', 'value' => static fn(array $r): string => (string)($r['printer_no'] ?? '')],
                    ['label' => 'Tipo', 'align' => 'c', 'value' => static fn(array $r): string => (string)($r['product_type'] ?? '')],
                    ['label' => 'Medida', 'align' => 'c', 'value' => static fn(array $r): string => (string)($r['measure_cm'] ?? '')],
                    ['label' => 'Solicitadas', 'align' => 'r', 'value' => static fn(array $r): string => bonusPreviewInt((float)($r['requested_units'] ?? 0))],
                    ['label' => 'Impresas', 'align' => 'r', 'strong' => true, 'value' => static fn(array $r): string => bonusPreviewInt((float)($r['produced_units'] ?? 0))],
                ],
                'units_key' => 'produced_units',
                'requested_key' => 'requested_units',
                'units_label' => 'Impresas',
                'selected_operator' => $selectedOperator,
                'operator_url' => static fn(string $op): string => '/bonificaciones?' . http_build_query(array_merge($_GET, ['view' => 'bonoflexo', 'operator' => $op])),
                'clear_url' => '/bonificaciones?' . http_build_query(array_diff_key(array_merge($_GET, ['view' => 'bonoflexo']), ['operator' => 1])),
            ]);
        }

        $body .= '</div>';
    } elseif ($view === 'bonoayudante') {
        $defaultHelperMonth = BonusConfigurationService::monthForDate(date('Y-m-d'), date('Y-m'));
        $monthKey = trim((string)($_GET['month'] ?? $defaultHelperMonth));
        if (!preg_match('/^(\d{4})-(\d{2})$/', $monthKey, $helperMonthParts)
            || !checkdate((int)$helperMonthParts[2], 1, (int)$helperMonthParts[1])) {
            $monthKey = $defaultHelperMonth;
        }
        $flashMessage = null;
        $flashIsError = false;
        if (isset($_GET['saved']) && $_GET['saved'] === '1') {
            $flashMessage = 'Guardado.';
        } elseif (isset($_GET['error']) && trim((string)$_GET['error']) !== '') {
            $flashMessage = trim((string)$_GET['error']);
            $flashIsError = true;
        }
        if ($flashMessage !== null) {
            $body .= '<div class="' . ($flashIsError ? 'err' : 'ok') . '" style="margin-bottom:12px">' . h($flashMessage) . '</div>';
        }

        $rows = $service->listBonusHelperMonthlyRows($monthKey);
        $body .= renderHelperBonusEvaluation($monthKey, $rows, $service->listErpWorkerNames(),
            $service->getBonusHelperSettings($monthKey));
    } else {
        $body .= '<div class="card" style="margin-bottom:12px">';
        $body .= '<div style="font-weight:900;margin-bottom:8px">' . h($viewLabel) . '</div>';
        $body .= '<div class="muted">Módulo en preparación. Definiremos aquí el cálculo de bonos según la producción realizada.</div>';
        $body .= '</div>';
    }
    render('Bonificaciones', $body);
    exit;
}

if ($path === '/bonificaciones/flexo/export' && $method === 'GET') {
    $qs = (string)($_SERVER['QUERY_STRING'] ?? '');
    header('Location: /bonificaciones/flexo/export-xls' . ($qs !== '' ? ('?' . $qs) : ''));
    exit;
}

// -----------------------------------------------------------------------------
// Export Bono CYS (Excel .xls en HTML)
// -----------------------------------------------------------------------------
if ($path === '/bonificaciones/cys/export-xls' && $method === 'GET') {
    $defaultMonthKey = date('Y-m');
    $today = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
    if ((int)$today->format('j') >= 26) {
        $defaultMonthKey = $today->modify('+1 month')->format('Y-m');
    }
    $filterType = trim((string)($_GET['filter_type'] ?? 'period'));
    if ($filterType !== 'range') {
        $filterType = 'period';
    }
    $monthKey = trim((string)($_GET['month'] ?? $defaultMonthKey));
    if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
        $monthKey = $defaultMonthKey;
    }
    $startDate = trim((string)($_GET['start_date'] ?? ''));
    $endDate = trim((string)($_GET['end_date'] ?? ''));
    if ($startDate === '' || $endDate === '') {
        $startDate = date('Y-m-d', strtotime('-6 days'));
        $endDate = date('Y-m-d');
    }
    $operatorName = trim((string)($_GET['operator'] ?? ''));
    if ($operatorName === '') {
        $operatorName = null;
    }

    $period = $service->resolveBonusFilterPeriod($filterType, $monthKey, $startDate, $endDate);
    $result = $service->listErpCysProductionForBonusPeriod($period, $operatorName);
    if (($result['ok'] ?? false) !== true) {
        $errList = $result['errors'] ?? [];
        $error = $errList !== [] ? trim((string)reset($errList)) : '';
        $redirectParams = ['view' => 'bonocys', 'filter_type' => $filterType];
        if ($filterType === 'range') {
            $redirectParams['start_date'] = $startDate;
            $redirectParams['end_date'] = $endDate;
        } else {
            $redirectParams['month'] = $monthKey;
        }
        if (is_string($operatorName) && $operatorName !== '') {
            $redirectParams['operator'] = $operatorName;
        }
        $redirectParams['error'] = $error !== '' ? $error : 'No se pudo generar la planilla.';
        header('Location: /bonificaciones?' . http_build_query($redirectParams));
        exit;
    }

    $periodData = (array)($result['period'] ?? []);
    $start = (string)($periodData['start_date'] ?? '');
    $end = (string)($periodData['end_date'] ?? '');
    $filename = $filterType === 'range'
        ? ('planilla_cys_rango_' . $start . '_a_' . $end)
        : ('planilla_cys_' . $monthKey . '_26-25');
    if (is_string($operatorName) && $operatorName !== '') {
        $safeOperator = preg_replace('/[^A-Za-z0-9._-]+/', '_', $operatorName);
        $safeOperator = trim((string)$safeOperator, '_');
        if ($safeOperator !== '') {
            $filename .= '_operador_' . $safeOperator;
        }
    }
    $filename .= '.xlsx';

    $rows = (array)($result['rows'] ?? []);

    #region debug-point cys-export-snapshot
    $cysBonusConfig = new BonusConfigurationService($trzPdo);
    $rows = array_map(static fn(array $row): array => $cysBonusConfig->applyMeasureRule($row, $monthKey), $rows);
    $debugEnvPath = __DIR__ . '/../.dbg/cys-totals-mismatch.env';
    $debugUrl = '';
    if (is_file($debugEnvPath)) {
        $envRaw = (string)@file_get_contents($debugEnvPath);
        if ($envRaw !== '') {
            foreach (preg_split('/\r?\n/', $envRaw) ?: [] as $line) {
                $line = trim((string)$line);
                if ($line === '' || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                if ($k === 'DEBUG_SERVER_URL') {
                    $debugUrl = $v;
                    break;
                }
            }
        }
    }
    if ($debugUrl !== '') {
        $dbgPost = static function (string $url, array $payload): void {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if (!is_string($body)) {
                return;
            }
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $body,
                    'timeout' => 1,
                ],
            ]);
            @file_get_contents($url, false, $ctx);
        };

        $runId = trim((string)($_GET['debug_run'] ?? 'pre'));
        if ($runId === '') {
            $runId = 'pre';
        }

        $rowCount = count($rows);
        $sumProduced = 0.0;
        $keys = [];
        $dupes = [];
        $sample = [];

        foreach ($rows as $i => $r) {
            if (!is_array($r)) {
                continue;
            }
            $produced = (float)($r['produced_units'] ?? 0.0);
            $sumProduced += $produced;

            $k = (string)($r['event_date'] ?? '') . '|' . (string)($r['cost_center'] ?? '') . '|' . (string)($r['operator_name'] ?? '') . '|' . (string)$produced;
            if (isset($keys[$k])) {
                $dupes[$k] = (int)($dupes[$k] ?? 1) + 1;
            } else {
                $keys[$k] = true;
            }

            if (count($sample) < 12) {
                $sample[] = [
                    'event_date' => (string)($r['event_date'] ?? ''),
                    'cc' => (string)($r['cost_center'] ?? ''),
                    'operator' => (string)($r['operator_name'] ?? ''),
                    'product_type' => (string)($r['product_type'] ?? ''),
                    'measure' => (string)($r['measure_cm'] ?? ''),
                    'produced_units' => $produced,
                    'requested_units' => (float)($r['requested_units'] ?? 0.0),
                    'erp_desc' => (string)($r['erp_desc'] ?? ''),
                ];
            }
        }

        $dupeTop = [];
        if ($dupes !== []) {
            arsort($dupes);
            foreach ($dupes as $k => $cnt) {
                $dupeTop[] = ['key' => $k, 'count' => $cnt];
                if (count($dupeTop) >= 10) {
                    break;
                }
            }
        }

        $dbgPost($debugUrl, [
            'sessionId' => 'cys-totals-mismatch',
            'hypothesisId' => 'precheck',
            'runId' => $runId,
            'event' => 'cys_export_rows_snapshot',
            'ts' => time(),
            'monthKey' => $monthKey,
            'operatorFilter' => $operatorName,
            'rowCount' => $rowCount,
            'uniqueKeyCount' => count($keys),
            'dupeTop' => $dupeTop,
            'sumProducedUnits' => $sumProduced,
            'sample' => $sample,
        ]);
    }
    #endregion debug-point cys-export-snapshot

    $toDmy = static function (string $ymd): string {
        $ymd = trim($ymd);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) !== 1) {
            return $ymd;
        }
        try {
            return (new DateTimeImmutable($ymd))->format('d/m/Y');
        } catch (Throwable) {
            return $ymd;
        }
    };

    $operatorCodeFromName = static function (string $name): string {
        $raw = strtoupper(trim($name));
        if ($raw === '') {
            return 'SIN';
        }
        if (preg_match('/^[A-Z0-9]{2,5}$/', $raw) === 1) {
            return $raw;
        }
        $parts = preg_split('/\s+/', $raw) ?: [];
        $code = '';
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            $code .= mb_substr($p, 0, 1);
            if (mb_strlen($code) >= 2) {
                break;
            }
        }
        if ($code === '') {
            $code = mb_substr($raw, 0, 2);
        }
        return strtoupper($code);
    };

    $defaultRates = [
        'bolsas' => ['BASE' => 1.0],
        'sacos' => ['BASE' => 2.0],
        'bolsas_xxl' => ['BASE' => 1.5],
        'basurines_troqueles' => ['BASE' => 0.5],
        'embalaje' => ['BASE' => 0.5],
        'cambio_configuracion' => ['AMOUNT' => 5000.0],
    ];
    $effectiveRates = $service->getBonusUnitRates('bonocys');
    if (!is_array($effectiveRates) || $effectiveRates === []) {
        $effectiveRates = $defaultRates;
    }
    $normalizeCysProductType = static function (string $bagType, string $fallbackType, string $erpDesc, string $itemTitle): array {
        $raw = trim($bagType) !== '' ? trim($bagType) : trim($fallbackType);
        $rawUp = strtoupper($raw);
        $descUp = strtoupper(trim($erpDesc));
        $itemUp = strtoupper(trim($itemTitle));
        $label = $rawUp;
        $code = $rawUp;
        if (
            str_contains($rawUp, 'SAC')
            || str_contains($rawUp, 'SACO')
            || str_contains($rawUp, 'SACOS')
            || str_contains($descUp, 'SAC')
            || str_contains($descUp, 'SACO')
            || str_contains($descUp, 'SACOS')
            || str_contains($itemUp, 'SAC')
            || str_contains($itemUp, 'SACO')
            || str_contains($itemUp, 'SACOS')
        ) {
            $label = 'SAC';
            $code = 'SAC';
        } elseif ($rawUp === 'TRO' || str_contains($rawUp, 'TROQ')) {
            $label = 'TRO';
            $code = 'TRO';
        } elseif ($rawUp === 'BAS' || str_contains($rawUp, 'BASUR')) {
            $label = 'BAS';
            $code = 'BAS';
        }
        return ['label' => $label, 'code' => $code];
    };
    $resolveCysCategory = static function (string $erpDesc, string $productType, string $measureLabel): string {
        $typeUp = strtoupper(trim($productType));
        if ($typeUp === 'SAC') {
            return 'sacos';
        }
        if ($typeUp === 'BAS' || $typeUp === 'TRO') {
            return 'basurines_troqueles';
        }
        $m = strtoupper(trim($measureLabel));
        $m = preg_replace('/\s+/', '', $m) ?? '';
        if ($m === '44X39X23' || $m === '48X40X20' || $m === '44X40X23') {
            return 'bolsas_xxl';
        }
        return 'bolsas';
    };
    $resolveCysRate = static function (array $rates, string $category): float {
        if (isset($rates[$category]) && is_array($rates[$category])) {
            return (float)($rates[$category]['BASE'] ?? 0.0);
        }
        return 0.0;
    };
    $money = static function (float $amount): string {
        if ($amount <= 0) {
            return '$ -';
        }
        return '$' . number_format((float)round($amount, 0), 0, ',', '.');
    };
    $formatVariation = static function (?float $value): string {
        if ($value === null) {
            return '';
        }
        $label = number_format($value, 9, ',', '.');
        $label = rtrim($label, '0');
        $label = rtrim($label, ',');
        if ($label === '-0' || $label === '') {
            return '0';
        }
        return $label;
    };

    $grouped = [];
    if ($operatorName !== null) {
        $grouped[(string)$operatorName] = $rows;
    } else {
        foreach ($rows as $row) {
            $name = is_array($row) ? trim((string)($row['operator_name'] ?? '')) : '';
            if ($name === '') {
                $name = 'Sin operador';
            }
            if (!isset($grouped[$name])) {
                $grouped[$name] = [];
            }
            $grouped[$name][] = $row;
        }
        ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);
    }

    ob_start();

    echo '<html><head><meta charset="UTF-8"><style>';
    echo 'body{font-family:Arial,sans-serif;color:#0f172a}';
    echo '.title{font-size:22px;font-weight:800;color:#0f172a}';
    echo '.sub{font-size:12px;color:#475569;margin:6px 0 14px 0}';
    echo '.meta{margin:10px 0 16px 0;font-size:12px;color:#334155}';
    echo '.section{font-size:14px;font-weight:800;margin:20px 0 8px 0;color:#0f172a}';
    echo '.report{border-collapse:collapse;width:100%;font-size:12px}';
    echo '.report td,.report th{border:1px solid #cbd5e1;padding:6px 8px;vertical-align:middle}';
    echo '.report th{background:#0f172a;color:#fff;text-align:center;font-size:11px}';
    echo '.report tr:nth-child(even) td{background:#f8fafc}';
    echo '.right{text-align:right}';
    echo '.center{text-align:center}';
    echo '.th2{background:#145e5b;color:#fff;text-align:center;font-size:11px}';
    echo '.num-int{mso-number-format:"\#\,\#\#0";text-align:right}';
    echo '.text-cell{mso-number-format:"\@"}';
    echo '</style></head><body>';

    echo '<div class="title">Planilla Bonos Corte y Sellado</div>';
    echo '<div class="sub">Unibag ERP · Bonificaciones</div>';
    echo '<div class="meta"><strong>Período:</strong> ' . h($start !== '' && $end !== '' ? ($start . ' a ' . $end) : ('Mes ' . $monthKey)) . '<br><strong>Generado:</strong> ' . h((new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get())))->format('d/m/Y H:i:s')) . '</div>';

    $formatUnits = static function (float $value): string {
        if ($value <= 0) {
            return '';
        }
        return (string)(int)round($value);
    };
    $formatMultiplier = static function (float $value): string {
        if ($value <= 0) {
            return '';
        }
        $label = number_format($value, 3, ',', '.');
        $label = rtrim($label, '0');
        $label = rtrim($label, ',');
        return 'x' . $label;
    };
    $moneyRow = static function (float $amount): string {
        if ($amount <= 0) {
            return '$ -';
        }
        return '$ ' . number_format((float)round($amount, 0), 0, ',', '.');
    };

    // Identificar operadores de Corte y Sellado para consistencia entre Hoja 1 y Hoja 2
    $cysOperatorNamesMap = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $name = trim((string)($r['operator_name'] ?? ''));
        if ($name !== '') {
            $cysOperatorNamesMap[$name] = true;
        }
    }
    if ($operatorName !== null && $operatorName !== '') {
        $cysOperatorNamesMap[$operatorName] = true;
    }

    $cysCodesMap = $service->buildOperatorBonusCodesMap(array_keys($cysOperatorNamesMap));
    $operatorLabelFromName = static function (string $name) use (&$cysCodesMap, $service, &$cysOperatorNamesMap): string {
        $name = trim($name);
        if ($name === '') {
            return 'SIN';
        }
        if (!isset($cysCodesMap[$name])) {
            $cysOperatorNamesMap[$name] = true;
            $cysCodesMap = $service->buildOperatorBonusCodesMap(array_keys($cysOperatorNamesMap));
        }
        return $cysCodesMap[$name] ?? 'OP';
    };

    $operatorTotals = [];
    $grandUnits = 0.0;
    $grandAmount = 0.0;

    foreach ($grouped as $operatorLabel => $operatorRows) {
        $rowsByDate = [];
        foreach ((array)$operatorRows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $date = trim((string)($r['event_date'] ?? ''));
            if ($date === '') {
                $date = 'Sin fecha';
            }
            if (!isset($rowsByDate[$date])) {
                $rowsByDate[$date] = [];
            }
            $rowsByDate[$date][] = $r;
        }
        ksort($rowsByDate, SORT_NATURAL | SORT_FLAG_CASE);

        $opHeaderCode = $operatorLabelFromName((string)$operatorLabel);
        echo '<div class="section">Operador: ' . h((string)$operatorLabel) . ' [' . h($opHeaderCode) . ']</div>';

        $sumUnits = 0.0;
        $sumAmount = 0.0;

        echo '<table class="report"><thead>';
        echo '<tr class="op-header-row"><th colspan="13" style="background:#0f172a;color:#ffffff;font-size:13px;font-weight:900;text-align:left;padding:8px 10px;">OPERADOR: ' . h((string)$operatorLabel) . ' [' . h($opHeaderCode) . ']</th></tr>';
        echo '<tr>';
        echo '<th colspan="13" class="th2">Suma de CANTIDAD</th>';
        echo '</tr>';
        echo '<tr>';
        echo '<th>OPERADOR</th>';
        echo '<th>FECHA</th>';
        echo '<th>N° CC</th>';
        echo '<th>CLIENTE</th>';
        echo '<th>SELLADORA</th>';
        echo '<th>TIPO<br>PRODUCTO</th>';
        echo '<th>MEDIDAS</th>';
        echo '<th>MULT</th>';
        echo '<th>MONTO</th>';
        echo '<th>% VARIACIÓN</th>';
        echo '<th>BONIFICACIÓN</th>';
        echo '<th>UNIDADES</th>';
        echo '<th>Total general</th>';
        echo '</tr>';
        echo '</thead><tbody>';

        foreach ($rowsByDate as $dateYmd => $dayRows) {
            $dayLabel = $toDmy((string)$dateYmd);
            $dayUnits = 0.0;
            $dayAmount = 0.0;
            $firstRowOfDay = true;

            foreach ((array)$dayRows as $r) {
                $produced = (float)($r['produced_units'] ?? 0);
                $requested = (float)($r['requested_units'] ?? 0);
                $variation = null;
                if ($requested > 0) {
                    $variation = ($produced - $requested) / $requested;
                }
                $bagType = (string)($r['bag_type'] ?? '');
                $productType = (string)($r['product_type'] ?? '');
                $erpDesc = (string)($r['erp_desc'] ?? '');
                $itemTitle = (string)($r['item_title'] ?? '');
                $measureLabel = (string)($r['measure_cm'] ?? '');
                $typeDef = $normalizeCysProductType($bagType, $productType, $erpDesc, $itemTitle);
                $typeLabel = (string)($typeDef['label'] ?? '');
                $typeCode = (string)($typeDef['code'] ?? $typeLabel);
                $category = (string)($r['bonus_category'] ?? $resolveCysCategory($erpDesc, $typeCode, $measureLabel));
                $rate = $resolveCysRate($effectiveRates, $category);
                $productionFactor = $cysBonusConfig->factor('bonocys', (string)$operatorLabel, (string)($r['event_date'] ?? ''), $monthKey);
                $rowAmount = $produced > 0 ? ($produced * $rate * $productionFactor) : 0.0;
                $bonificacionLabel = 'PAGO (' . (int)round($productionFactor * 100) . '%)';

                $dayUnits += $produced > 0 ? $produced : 0.0;
                $dayAmount += $rowAmount;
                $sumUnits += $produced > 0 ? $produced : 0.0;
                $sumAmount += $rowAmount;
                $grandUnits += $produced > 0 ? $produced : 0.0;
                $grandAmount += $rowAmount;

                echo '<tr>';
                echo '<td>' . h($opHeaderCode) . '</td>';
                echo '<td class="center">' . h($firstRowOfDay ? $dayLabel : '') . '</td>';
                $firstRowOfDay = false;
                echo '<td class="center">' . h((string)($r['cost_center'] ?? '')) . '</td>';
                echo '<td>' . h((string)($r['client_label'] ?? '')) . '</td>';
                echo '<td class="center">' . h((string)($r['printer_no'] ?? '')) . '</td>';
                echo '<td class="center">' . h($typeLabel) . '</td>';
                echo '<td class="center">' . h(isset($r['bonus_original_measure']) ? $r['bonus_original_measure'] . ' -> ' . $measureLabel : $measureLabel) . '</td>';
                echo '<td class="center">' . h($formatMultiplier((float)$rate)) . '</td>';
                echo '<td class="right" style="font-weight:800">' . h($moneyRow((float)$rowAmount)) . '</td>';
                echo '<td class="center">' . h($formatVariation($variation)) . '</td>';
                echo '<td class="center">' . h($bonificacionLabel) . '</td>';
                echo '<td class="right num-int" style="font-weight:800">' . h($formatUnits($produced)) . '</td>';
                echo '<td class="right num-int" style="font-weight:800">' . h($formatUnits($produced)) . '</td>';
                echo '</tr>';
            }

            echo '<tr>';
            echo '<td colspan="8" style="font-weight:900">Total ' . h($dayLabel) . ' (' . h((string)$operatorLabel) . ')</td>';
            echo '<td class="right" style="font-weight:900">' . h($moneyRow((float)$dayAmount)) . '</td>';
            echo '<td colspan="2"></td>';
            echo '<td class="right num-int" style="font-weight:900">' . h($formatUnits($dayUnits)) . '</td>';
            echo '<td class="right num-int" style="font-weight:900">' . h($formatUnits($dayUnits)) . '</td>';
            echo '</tr>';
        }

        echo '<tr>';
        echo '<td colspan="8" style="font-weight:900">Total ' . h((string)$operatorLabel) . '</td>';
        echo '<td class="right" style="font-weight:900">' . h($moneyRow((float)$sumAmount)) . '</td>';
        echo '<td colspan="2"></td>';
        echo '<td class="right num-int" style="font-weight:900">' . h($formatUnits($sumUnits)) . '</td>';
        echo '<td class="right num-int" style="font-weight:900">' . h($formatUnits($sumUnits)) . '</td>';
        echo '</tr>';

        echo '</tbody></table>';

        $operatorTotals[(string)$operatorLabel] = $sumUnits;
    }

    echo '<div class="section">Totales por operador</div>';
    echo '<table class="report" style="max-width:620px"><thead><tr><th>Operador</th><th>UNIDADES</th></tr></thead><tbody>';
    foreach ($operatorTotals as $operatorLabel => $u) {
        $opCode = $operatorLabelFromName((string)$operatorLabel);
        echo '<tr><td><strong>[' . h($opCode) . ']</strong> ' . h((string)$operatorLabel) . '</td><td class="right num-int" style="font-weight:800">' . h($formatUnits((float)$u)) . '</td></tr>';
    }
    echo '<tr><td style="font-weight:900">TOTAL</td><td class="right num-int" style="font-weight:900">' . h($formatUnits($grandUnits)) . '</td></tr>';
    echo '</tbody></table>';

    $money = static function (float $amount): string {
        if ($amount <= 0) {
            return '$ -';
        }
        return '$ ' . number_format((float)round($amount, 0), 0, ',', '.');
    };

    $cfgAmount = (float)($effectiveRates['cambio_configuracion']['AMOUNT'] ?? (float)($defaultRates['cambio_configuracion']['AMOUNT'] ?? 0.0));
    $embRate = (float)($effectiveRates['embalaje']['BASE'] ?? (float)($defaultRates['embalaje']['BASE'] ?? 0.0));

    $opUnits = [];
    $opBase = [];
    $opSacos = [];
    $opXxl = [];
    $opBolsas = [];
    $opBasurines = [];
    $opExtraUnits = [];
    $opExtraPay = [];
    // Multiplicador de producción (120%): el 20% extra se paga sobre las unidades con la tarifa base de producción ($1 por defecto).
    $extraBaseRate = (float)($effectiveRates['bolsas']['BASE'] ?? (float)($defaultRates['bolsas']['BASE'] ?? 1.0));

    foreach ($rows as $r) {
        if (!is_array($r)) {
            continue;
        }
        $opName = trim((string)($r['operator_name'] ?? ''));
        if ($operatorName !== null && $opName !== (string)$operatorName) {
            continue;
        }
        $units = (float)($r['produced_units'] ?? 0);
        if ($units <= 0) {
            continue;
        }
        $opLabel = $operatorLabelFromName($opName);
        $opUnits[$opLabel] = (float)($opUnits[$opLabel] ?? 0.0) + $units;

        $erpDesc = (string)($r['erp_desc'] ?? '');
        $bagType = (string)($r['bag_type'] ?? '');
        $productType = (string)($r['product_type'] ?? '');
        $itemTitle = (string)($r['item_title'] ?? '');
        $measureLabel = (string)($r['measure_cm'] ?? '');
        $typeDef = $normalizeCysProductType($bagType, $productType, $erpDesc, $itemTitle);
        $typeCode = (string)($typeDef['code'] ?? (string)($typeDef['label'] ?? ''));
        $category = (string)($r['bonus_category'] ?? $resolveCysCategory($erpDesc, $typeCode, $measureLabel));
        $rate = $resolveCysRate($effectiveRates, $category);
        $amount = $units * $rate * $cysBonusConfig->factor('bonocys', $opName, (string)($r['event_date'] ?? ''), $monthKey);

        $opBase[$opLabel] = (float)($opBase[$opLabel] ?? 0.0) + $amount;
        $extraRate = $cysBonusConfig->productionBonusRate('bonocys', $opName, (string)($r['event_date'] ?? ''), $monthKey);
        if ($extraRate > 0) {
            $opExtraUnits[$opLabel] = (float)($opExtraUnits[$opLabel] ?? 0.0) + ($units * $extraRate);
            $opExtraPay[$opLabel] = (float)($opExtraPay[$opLabel] ?? 0.0) + BonusConfigurationService::extraProductionPay($units, $extraRate, $extraBaseRate);
        }
        if ($category === 'sacos') {
            $opSacos[$opLabel] = (float)($opSacos[$opLabel] ?? 0.0) + $amount;
        } elseif ($category === 'bolsas_xxl') {
            $opXxl[$opLabel] = (float)($opXxl[$opLabel] ?? 0.0) + $amount;
        } elseif ($category === 'bolsas') {
            $opBolsas[$opLabel] = (float)($opBolsas[$opLabel] ?? 0.0) + $amount;
        } elseif ($category === 'basurines_troqueles') {
            $opBasurines[$opLabel] = (float)($opBasurines[$opLabel] ?? 0.0) + $amount;
        }
    }

    $opCols = array_keys($opUnits);
    sort($opCols, SORT_NATURAL | SORT_FLAG_CASE);

    if ($opCols !== []) {
        $rowTotal = static function (array $map, array $cols): float {
            $sum = 0.0;
            foreach ($cols as $c) {
                $sum += (float)($map[$c] ?? 0.0);
            }
            return $sum;
        };

        $opChangeCounts = [];
        $changeRes = $service->listErpCysConfigChangeCountsForBonusPeriod($period, $operatorName);
        if (is_array($changeRes) && ($changeRes['ok'] ?? false) === true && is_array($changeRes['counts'] ?? null)) {
            foreach ((array)$changeRes['counts'] as $name => $cnt) {
                $name = trim((string)$name);
                if ($name === '') {
                    continue;
                }
                $label = $operatorLabelFromName($name);
                if (!in_array($label, $opCols, true)) {
                    continue;
                }
                $opChangeCounts[$label] = (float)($opChangeCounts[$label] ?? 0.0) + (float)$cnt;
            }
        }
        $opChangePay = [];
        $opTotalPay = [];
        foreach ($opCols as $c) {
            $cnt = (float)($opChangeCounts[$c] ?? 0.0);
            $opChangePay[$c] = $cnt > 0 ? ($cnt * $cfgAmount) : 0.0;
            $opTotalPay[$c] = (float)($opBase[$c] ?? 0.0) + (float)($opExtraPay[$c] ?? 0.0) + (float)($opChangePay[$c] ?? 0.0);
        }

        $opPackUnits = [];
        $packRes = $service->listErpCysPackagingUnitsForBonusPeriod($period, $operatorName);
        if (is_array($packRes) && ($packRes['ok'] ?? false) === true && is_array($packRes['units'] ?? null)) {
            foreach ((array)$packRes['units'] as $name => $u) {
                $name = trim((string)$name);
                if ($name === '') {
                    continue;
                }
                $label = $operatorLabelFromName($name);
                if (!in_array($label, $opCols, true)) {
                    continue;
                }
                $opPackUnits[$label] = (float)($opPackUnits[$label] ?? 0.0) + (float)$u;
            }
        }
        $opPackPay = [];
        $opTotalWithPack = [];
        foreach ($opCols as $c) {
            $u = (float)($opPackUnits[$c] ?? 0.0);
            $opPackPay[$c] = $u > 0 ? ($u * $embRate) : 0.0;
            $opTotalWithPack[$c] = (float)($opTotalPay[$c] ?? 0.0) + (float)($opPackPay[$c] ?? 0.0);
        }

        echo '<div class="section">Resumen</div>';
        echo '<table class="report"><thead><tr><th style="min-width:240px">Concepto</th>';
        foreach ($opCols as $c) {
            $fullNameForCode = '';
            foreach ($cysCodesMap as $fName => $code) {
                if ($code === $c) {
                    $fullNameForCode = $fName;
                    break;
                }
            }
            $titleAttr = $fullNameForCode !== '' ? ' title="' . h($fullNameForCode) . '"' : '';
            echo '<th' . $titleAttr . '>' . h((string)$c) . '</th>';
        }
        echo '<th>Total</th></tr></thead><tbody>';

        $rowsSummary = [
            ['label' => 'DESCUENTO', 'type' => 'money', 'map' => []],
            ['label' => 'SACOS (x2)', 'type' => 'money', 'map' => $opSacos],
            ['label' => 'BOLSAS XXL (x1,5)', 'type' => 'money', 'map' => $opXxl],
            ['label' => 'BOLSAS (x1)', 'type' => 'money', 'map' => $opBolsas],
            ['label' => 'BASURINES/TROQUELES (x0,5)', 'type' => 'money', 'map' => $opBasurines],
            ['label' => 'UNIDADES EXTRA (MULTIPLICADOR)', 'type' => 'count', 'map' => $opExtraUnits],
            ['label' => 'ADICIONAL +20% PRODUCCIÓN', 'type' => 'money', 'map' => $opExtraPay],
            ['label' => 'CAMBIOS DE CONFIGURACIÓN', 'type' => 'count', 'map' => $opChangeCounts],
            ['label' => 'VALOR CAMBIOS ($)', 'type' => 'fixed_money', 'value' => $cfgAmount],
            ['label' => 'A PAGAR POR CAMBIOS', 'type' => 'money', 'map' => $opChangePay],
            ['label' => 'PISO DE PRODUCCIÓN', 'type' => 'money', 'map' => []],
            ['label' => 'BONIFICACIÓN (BASE)', 'type' => 'money', 'map' => $opBase],
            ['label' => 'TOTAL A PAGAR', 'type' => 'money', 'map' => $opTotalPay],
        ];

        foreach ($rowsSummary as $rowDef) {
            $label = (string)($rowDef['label'] ?? '');
            $type = (string)($rowDef['type'] ?? 'money');
            echo '<tr><td style="font-weight:800">' . h($label) . '</td>';
            if ($type === 'fixed_money') {
                $val = (float)($rowDef['value'] ?? 0.0);
                foreach ($opCols as $c) {
                    echo '<td class="right">' . h($money($val)) . '</td>';
                }
                echo '<td class="right" style="font-weight:900">' . h($money($val)) . '</td></tr>';
                continue;
            }
            $map = (array)($rowDef['map'] ?? []);
            foreach ($opCols as $c) {
                $v = (float)($map[$c] ?? 0.0);
                if ($type === 'count') {
                    echo '<td class="right">' . h($v > 0 ? number_format((float)(int)$v, 0, ',', '.') : '0') . '</td>';
                } else {
                    echo '<td class="right">' . h($money($v)) . '</td>';
                }
            }
            $total = $rowTotal($map, $opCols);
            if ($type === 'count') {
                echo '<td class="right" style="font-weight:900">' . h(number_format((float)(int)$total, 0, ',', '.')) . '</td></tr>';
            } else {
                echo '<td class="right" style="font-weight:900">' . h($money($total)) . '</td></tr>';
            }
        }
        echo '</tbody></table>';

        echo '<div class="section">Embalaje</div>';
        echo '<table class="report"><thead><tr><th style="min-width:240px">Concepto</th>';
        foreach ($opCols as $c) {
            $fullNameForCode = '';
            foreach ($cysCodesMap as $fName => $code) {
                if ($code === $c) {
                    $fullNameForCode = $fName;
                    break;
                }
            }
            $titleAttr = $fullNameForCode !== '' ? ' title="' . h($fullNameForCode) . '"' : '';
            echo '<th' . $titleAttr . '>' . h((string)$c) . '</th>';
        }
        echo '<th>Total</th></tr></thead><tbody>';

        echo '<tr><td style="font-weight:800">PESOS POR BOLSA EMBALADA</td>';
        foreach ($opCols as $c) {
            echo '<td class="right">' . h(number_format($embRate, 1, ',', '.')) . '</td>';
        }
        echo '<td class="right">' . h(number_format($embRate, 1, ',', '.')) . '</td></tr>';

        echo '<tr><td style="font-weight:800">CANTIDAD EMBALADA</td>';
        foreach ($opCols as $c) {
            $uLabel = $formatUnits((float)($opPackUnits[$c] ?? 0.0));
            echo '<td class="right">' . h($uLabel !== '' ? $uLabel : '0') . '</td>';
        }
        $uTotalLabel = $formatUnits($rowTotal($opPackUnits, $opCols));
        echo '<td class="right" style="font-weight:900">' . h($uTotalLabel !== '' ? $uTotalLabel : '0') . '</td></tr>';

        echo '<tr><td style="font-weight:800">PESOS A PAGAR POR EMBALAJE</td>';
        foreach ($opCols as $c) {
            echo '<td class="right">' . h($money((float)($opPackPay[$c] ?? 0.0))) . '</td>';
        }
        echo '<td class="right" style="font-weight:900">' . h($money($rowTotal($opPackPay, $opCols))) . '</td></tr>';

        echo '<tr><td style="font-weight:800">TOTAL A PAGAR BOLSA Y EMBALAJE</td>';
        foreach ($opCols as $c) {
            echo '<td class="right">' . h($money((float)($opTotalWithPack[$c] ?? 0.0))) . '</td>';
        }
        echo '<td class="right" style="font-weight:900">' . h($money($rowTotal($opTotalWithPack, $opCols))) . '</td></tr>';

        echo '</tbody></table>';
    }

    echo '</body></html>';
    $htmlCys = (string)ob_get_clean();

    // =========================================================================
    // HOJA 2: Embalaje CyS (Solo operadores de Corte y Sellado)
    // =========================================================================
    $packRowsRes = $service->listErpCysPackagingRowsForBonusPeriod($period, $operatorName);
    $packAllRows = (array)($packRowsRes['rows'] ?? []);

    // Identificar el conjunto de operadores que trabajaron en Corte y Sellado
    $cysOperatorNamesMap = [];
    foreach ($rows as $r) {
        if (!is_array($r)) continue;
        $name = trim((string)($r['operator_name'] ?? ''));
        if ($name !== '') {
            $cysOperatorNamesMap[$name] = true;
        }
    }

    // Filtrar: EXCLUSIVAMENTE los operadores de Corte y Sellado, nadie más
    $packCysRows = [];
    foreach ($packAllRows as $pr) {
        if (!is_array($pr)) continue;
        $op = trim((string)($pr['operator_name'] ?? ''));
        if ($op !== '' && isset($cysOperatorNamesMap[$op])) {
            $packCysRows[] = $pr;
        }
    }

    // Agrupar filas de embalaje por operador de CyS
    $groupedPack = [];
    if ($operatorName !== null) {
        if (isset($cysOperatorNamesMap[$operatorName])) {
            $groupedPack[(string)$operatorName] = $packCysRows;
        }
    } else {
        foreach ($packCysRows as $pr) {
            $op = trim((string)($pr['operator_name'] ?? ''));
            if (!isset($groupedPack[$op])) {
                $groupedPack[$op] = [];
            }
            $groupedPack[$op][] = $pr;
        }
        ksort($groupedPack, SORT_NATURAL | SORT_FLAG_CASE);
    }

    ob_start();
    echo '<html><head><meta charset="UTF-8"><style>';
    echo 'body{font-family:Arial,sans-serif;color:#0f172a}';
    echo '.title{font-size:22px;font-weight:800;color:#0f172a}';
    echo '.sub{font-size:12px;color:#475569;margin:6px 0 14px 0}';
    echo '.meta{margin:10px 0 16px 0;font-size:12px;color:#334155}';
    echo '.section{font-size:14px;font-weight:800;margin:20px 0 8px 0;color:#0f172a}';
    echo '.report{border-collapse:collapse;width:100%;font-size:12px}';
    echo '.report td,.report th{border:1px solid #cbd5e1;padding:6px 8px;vertical-align:middle}';
    echo '.report th{background:#0f172a;color:#fff;text-align:center;font-size:11px}';
    echo '.report tr:nth-child(even) td{background:#f8fafc}';
    echo '.right{text-align:right}';
    echo '.center{text-align:center}';
    echo '.th2{background:#145e5b;color:#fff;text-align:center;font-size:11px}';
    echo '.num-int{mso-number-format:"\#\,\#\#0";text-align:right}';
    echo '.text-cell{mso-number-format:"\@"}';
    echo '</style></head><body>';

    echo '<div class="title">Planilla Embalaje · Operadores Corte y Sellado</div>';
    echo '<div class="sub">Unibag ERP · Bonificaciones</div>';
    echo '<div class="meta"><strong>Período:</strong> ' . h($start !== '' && $end !== '' ? ($start . ' a ' . $end) : ('Mes ' . $monthKey)) . '<br><strong>Personal incluido:</strong> Exclusivo operadores de Corte y Sellado<br><strong>Tarifa Embalaje:</strong> $' . number_format($embRate, 2, ',', '.') . ' / unid.<br><strong>Generado:</strong> ' . h((new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get())))->format('d/m/Y H:i:s')) . '</div>';

    $packOperatorTotals = [];
    $packGrandUnits = 0.0;
    $packGrandAmount = 0.0;

    if ($groupedPack === []) {
        echo '<div class="section">Sin registros de embalaje</div>';
        echo '<p style="color:#64748b;font-size:13px;padding:12px 0">No se registraron unidades embaladas por operadores de Corte y Sellado en este período.</p>';
    } else {
        foreach ($groupedPack as $operatorLabel => $operatorRows) {
            $rowsByDate = [];
            foreach ((array)$operatorRows as $r) {
                if (!is_array($r)) continue;
                $date = trim((string)($r['event_date'] ?? ''));
                if ($date === '') $date = 'Sin fecha';
                if (!isset($rowsByDate[$date])) {
                    $rowsByDate[$date] = [];
                }
                $rowsByDate[$date][] = $r;
            }
            ksort($rowsByDate, SORT_NATURAL | SORT_FLAG_CASE);

            $packHeaderCode = $operatorLabelFromName((string)$operatorLabel);
            echo '<div class="section">Operador: ' . h((string)$operatorLabel) . ' [' . h($packHeaderCode) . ']</div>';

            $sumUnits = 0.0;
            $sumAmount = 0.0;

            echo '<table class="report"><thead>';
            echo '<tr class="op-header-row"><th colspan="13" style="background:#0f172a;color:#ffffff;font-size:13px;font-weight:900;text-align:left;padding:8px 10px;">OPERADOR: ' . h((string)$operatorLabel) . ' [' . h($packHeaderCode) . ']</th></tr>';
            echo '<tr>';
            echo '<th colspan="13" class="th2">Suma de CANTIDAD EMBALADA</th>';
            echo '</tr>';
            echo '<tr>';
            echo '<th>OPERADOR</th>';
            echo '<th>FECHA</th>';
            echo '<th>N° CC</th>';
            echo '<th>CLIENTE</th>';
            echo '<th>SELLADORA / MÁQ.</th>';
            echo '<th>TIPO<br>PRODUCTO</th>';
            echo '<th>MEDIDAS</th>';
            echo '<th>MULT</th>';
            echo '<th>MONTO</th>';
            echo '<th>% VARIACIÓN</th>';
            echo '<th>BONIFICACIÓN</th>';
            echo '<th>UNIDADES</th>';
            echo '<th>Total general</th>';
            echo '</tr>';
            echo '</thead><tbody>';

            foreach ($rowsByDate as $dateYmd => $dayRows) {
                $dayLabel = $toDmy((string)$dateYmd);
                $dayUnits = 0.0;
                $dayAmount = 0.0;
                $firstRowOfDay = true;

                foreach ((array)$dayRows as $r) {
                    $produced = (float)($r['produced_units'] ?? 0);
                    $requested = (float)($r['requested_units'] ?? 0);
                    $variation = null;
                    if ($requested > 0) {
                        $variation = ($produced - $requested) / $requested;
                    }
                    $bagType = (string)($r['bag_type'] ?? '');
                    $productType = (string)($r['product_type'] ?? '');
                    $erpDesc = (string)($r['erp_desc'] ?? '');
                    $itemTitle = (string)($r['item_title'] ?? '');
                    $measureLabel = (string)($r['measure_cm'] ?? '');
                    $typeDef = $normalizeCysProductType($bagType, $productType, $erpDesc, $itemTitle);
                    $typeLabel = (string)($typeDef['label'] ?? '');
                    $rowAmount = $produced > 0 ? ($produced * $embRate) : 0.0;
                    $bonificacionLabel = 'PAGO';

                    $dayUnits += $produced > 0 ? $produced : 0.0;
                    $dayAmount += $rowAmount;
                    $sumUnits += $produced > 0 ? $produced : 0.0;
                    $sumAmount += $rowAmount;
                    $packGrandUnits += $produced > 0 ? $produced : 0.0;
                    $packGrandAmount += $rowAmount;

                    echo '<tr>';
                    echo '<td>' . h($packHeaderCode) . '</td>';
                    echo '<td class="center">' . h($firstRowOfDay ? $dayLabel : '') . '</td>';
                    $firstRowOfDay = false;
                    echo '<td class="center">' . h((string)($r['cost_center'] ?? '')) . '</td>';
                    echo '<td>' . h((string)($r['client_label'] ?? '')) . '</td>';
                    echo '<td class="center">' . h((string)($r['printer_no'] ?? '')) . '</td>';
                    echo '<td class="center">' . h($typeLabel) . '</td>';
                    echo '<td class="center">' . h($measureLabel) . '</td>';
                    echo '<td class="center">' . h($formatMultiplier((float)$embRate)) . '</td>';
                    echo '<td class="right" style="font-weight:800">' . h($moneyRow((float)$rowAmount)) . '</td>';
                    echo '<td class="center">' . h($formatVariation($variation)) . '</td>';
                    echo '<td class="center">' . h($bonificacionLabel) . '</td>';
                    echo '<td class="right num-int" style="font-weight:800">' . h($formatUnits($produced)) . '</td>';
                    echo '<td class="right num-int" style="font-weight:800">' . h($formatUnits($produced)) . '</td>';
                    echo '</tr>';
                }

                echo '<tr>';
                echo '<td colspan="8" style="font-weight:900">Total ' . h($dayLabel) . ' (' . h((string)$operatorLabel) . ')</td>';
                echo '<td class="right" style="font-weight:900">' . h($moneyRow((float)$dayAmount)) . '</td>';
                echo '<td colspan="2"></td>';
                echo '<td class="right num-int" style="font-weight:900">' . h($formatUnits($dayUnits)) . '</td>';
                echo '<td class="right num-int" style="font-weight:900">' . h($formatUnits($dayUnits)) . '</td>';
                echo '</tr>';
            }

            echo '<tr>';
            echo '<td colspan="8" style="font-weight:900">Total ' . h((string)$operatorLabel) . '</td>';
            echo '<td class="right" style="font-weight:900">' . h($moneyRow((float)$sumAmount)) . '</td>';
            echo '<td colspan="2"></td>';
            echo '<td class="right num-int" style="font-weight:900">' . h($formatUnits($sumUnits)) . '</td>';
            echo '<td class="right num-int" style="font-weight:900">' . h($formatUnits($sumUnits)) . '</td>';
            echo '</tr>';

            echo '</tbody></table>';

            $packOperatorTotals[(string)$operatorLabel] = [
                'units' => $sumUnits,
                'amount' => $sumAmount,
            ];
        }

        echo '<div class="section">Totales Embalaje por operador (Corte y Sellado)</div>';
        echo '<table class="report" style="max-width:720px"><thead><tr><th>Operador</th><th>UNIDADES EMBALADAS</th><th>MONTO A PAGAR</th></tr></thead><tbody>';
        foreach ($packOperatorTotals as $operatorLabel => $tot) {
            $u = (float)($tot['units'] ?? 0.0);
            $amt = (float)($tot['amount'] ?? 0.0);
            $packRowCode = $operatorLabelFromName((string)$operatorLabel);
            echo '<tr><td><strong>[' . h($packRowCode) . ']</strong> ' . h((string)$operatorLabel) . '</td><td class="right num-int" style="font-weight:800">' . h($formatUnits($u)) . '</td><td class="right" style="font-weight:800">' . h($moneyRow($amt)) . '</td></tr>';
        }
        echo '<tr><td style="font-weight:900">TOTAL</td><td class="right num-int" style="font-weight:900">' . h($formatUnits($packGrandUnits)) . '</td><td class="right" style="font-weight:900">' . h($moneyRow($packGrandAmount)) . '</td></tr>';
        echo '</tbody></table>';
    }

    echo '</body></html>';
    $htmlPackaging = (string)ob_get_clean();

    SimpleXlsx::streamHtmlSheets($filename, [
        ['title' => 'Corte y Sellado', 'html' => $htmlCys],
        ['title' => 'Embalaje CyS', 'html' => $htmlPackaging],
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// Export Bono Serigrafía (Excel .xls en HTML)
//
// Nota: en esta etapa se configura principalmente el formato para que calce con
// el look & feel del Excel de Flexo. Las reglas completas de cálculo se integran
// después (el usuario confirmará qué campos ERP usar y qué lógicas aplicar).
// -----------------------------------------------------------------------------
if ($path === '/bonificaciones/seri/export-xls' && $method === 'GET') {
    $defaultMonthKey = date('Y-m');
    $today = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
    if ((int)$today->format('j') >= 26) {
        $defaultMonthKey = $today->modify('+1 month')->format('Y-m');
    }
    $filterType = trim((string)($_GET['filter_type'] ?? 'period'));
    if ($filterType !== 'range') {
        $filterType = 'period';
    }
    $monthKey = trim((string)($_GET['month'] ?? $defaultMonthKey));
    if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
        $monthKey = $defaultMonthKey;
    }
    $startDate = trim((string)($_GET['start_date'] ?? ''));
    $endDate = trim((string)($_GET['end_date'] ?? ''));
    if ($startDate === '' || $endDate === '') {
        $startDate = date('Y-m-d', strtotime('-6 days'));
        $endDate = date('Y-m-d');
    }
    $operatorName = trim((string)($_GET['operator'] ?? ''));
    if ($operatorName === '') {
        $operatorName = null;
    }

    $period = $service->resolveBonusFilterPeriod($filterType, $monthKey, $startDate, $endDate);
    $result = $service->listErpSeriProductionForBonusPeriod($period, $operatorName);
    if (($result['ok'] ?? false) !== true) {
        $errList = $result['errors'] ?? [];
        $error = $errList !== [] ? trim((string)reset($errList)) : '';
        $redirectParams = ['view' => 'bonoseri', 'filter_type' => $filterType];
        if ($filterType === 'range') {
            $redirectParams['start_date'] = $startDate;
            $redirectParams['end_date'] = $endDate;
        } else {
            $redirectParams['month'] = $monthKey;
        }
        if (is_string($operatorName) && $operatorName !== '') {
            $redirectParams['operator'] = $operatorName;
        }
        $redirectParams['error'] = $error !== '' ? $error : 'No se pudo generar la planilla.';
        header('Location: /bonificaciones?' . http_build_query($redirectParams));
        exit;
    }

    $periodData = (array)($result['period'] ?? []);
    $start = (string)($periodData['start_date'] ?? '');
    $end = (string)($periodData['end_date'] ?? '');
    $filename = $filterType === 'range'
        ? ('planilla_seri_rango_' . $start . '_a_' . $end)
        : ('planilla_seri_' . $monthKey . '_26-25');
    if (is_string($operatorName) && $operatorName !== '') {
        $safeOperator = preg_replace('/[^A-Za-z0-9._-]+/', '_', $operatorName);
        $safeOperator = trim((string)$safeOperator, '_');
        if ($safeOperator !== '') {
            $filename .= '_operador_' . $safeOperator;
        }
    }
    $filename .= '.xlsx';

    $rows = (array)($result['rows'] ?? []);
    $grouped = [];
    if ($operatorName !== null) {
        $grouped[(string)$operatorName] = $rows;
    } else {
        foreach ($rows as $row) {
            $name = is_array($row) ? trim((string)($row['operator_name'] ?? '')) : '';
            if ($name === '') {
                $name = 'Sin operador';
            }
            if (!isset($grouped[$name])) {
                $grouped[$name] = [];
            }
            $grouped[$name][] = $row;
        }
        ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);
    }

    $toDmy = static function (string $ymd): string {
        $ymd = trim($ymd);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) !== 1) {
            return $ymd;
        }
        try {
            return (new DateTimeImmutable($ymd))->format('d/m/Y');
        } catch (Throwable) {
            return $ymd;
        }
    };
    $allEquipoParams = $service->listErpEquipoParams();

    $normalizeSeriProductType = static function (string $erpDesc, string $productType, string $itemTitle): string {
        $descUp = strtoupper(trim($erpDesc));
        $typeUp = strtoupper(trim($productType));
        $itemUp = strtoupper(trim($itemTitle));
        if (
            str_contains($descUp, 'SAC')
            || str_contains($descUp, 'SACO')
            || str_contains($descUp, 'SACOS')
            || str_contains($itemUp, 'SAC')
            || str_contains($itemUp, 'SACO')
            || str_contains($itemUp, 'SACOS')
            || $typeUp === 'SACO'
            || $typeUp === 'SACOS'
            || str_starts_with($typeUp, 'SAC')
        ) {
            return 'SAC';
        }
        if ($typeUp === '' || $typeUp === 'BOL') {
            if (str_contains($descUp, 'BOU') || str_contains($itemUp, 'BOU')) {
                return 'BOU';
            }
            if (str_contains($descUp, 'TRO') || str_contains($itemUp, 'TRO')) {
                return 'TRO';
            }
            if (str_contains($descUp, 'BAS') || str_contains($itemUp, 'BAS')) {
                return 'BAS';
            }
            if (str_contains($descUp, 'PRO') || str_contains($itemUp, 'PRO')) {
                return 'PRO';
            }
        }
        if ($typeUp === '') {
            $typeUp = 'BOL';
        }
        // Solo las 3 primeras letras
        return strtoupper(substr($typeUp, 0, 3));
    };

    $resolveSeriDesarrolloCm = static function (array $allParams, int $equipoId, float $ancho, float $fuelle, float $alto, int $calcFuelle): int {
        $paramMedida = $calcFuelle === 1 ? (int)round($ancho + $fuelle) : (int)round($ancho);
        if ($paramMedida <= 0) {
            $paramMedida = (int)round($ancho);
        }

        // 1. Buscar en equipo específico
        $corteM2 = null;
        if ($equipoId > 0) {
            foreach ($allParams as $ep) {
                if ((int)$ep['param_equipo_id'] === $equipoId && (int)$ep['param_medida'] >= $paramMedida) {
                    $corteM2 = (float)$ep['param_corte'];
                    break;
                }
            }
        }

        // 2. Si no encontró en ese equipo, buscar en cualquier equipo de serigrafía (equipo_type_id = 11)
        if ($corteM2 === null || $corteM2 <= 0.0) {
            foreach ($allParams as $ep) {
                if ((int)$ep['equipo_type_id'] === 11 && (int)$ep['param_medida'] >= $paramMedida) {
                    $corteM2 = (float)$ep['param_corte'];
                    break;
                }
            }
        }

        // 3. Fallback
        if ($corteM2 === null || $corteM2 <= 0.0) {
            $corteM2 = $alto > 0.0 ? ($alto / 100.0) : ($ancho / 100.0);
        }

        // Corte de bolsa * 100 redondeado a entero sin decimales
        return (int)round($corteM2 * 100.0);
    };

    $parseSeriDesarrollo = static function (array $row, array $allParams) use ($resolveSeriDesarrolloCm): string {
        $ancho = (float)($row['dim_width_cm'] ?? 0);
        $alto = (float)($row['dim_height_cm'] ?? 0);
        $fuelle = (float)($row['dim_fuelle_cm'] ?? 0);
        $calcFuelle = (int)($row['item_prodcalc_fuelle_act'] ?? 0);
        $equipoId = (int)($row['win_equipoid'] ?? ($row['printer_no'] ?? 0));

        if ($ancho <= 0.0 || $alto <= 0.0) {
            $measure = strtoupper(trim((string)($row['measure_cm'] ?? '')));
            if ($measure !== '' && preg_match('/^(\d+(?:\.\d+)?)(?:X(\d+(?:\.\d+)?))?(?:X(\d+(?:\.\d+)?))?/', $measure, $m)) {
                if ($ancho <= 0.0 && isset($m[1])) {
                    $ancho = (float)$m[1];
                }
                if ($alto <= 0.0 && isset($m[2])) {
                    $alto = (float)$m[2];
                }
                if ($fuelle <= 0.0 && isset($m[3])) {
                    $fuelle = (float)$m[3];
                }
            }
        }

        if ($ancho <= 0.0 && $alto <= 0.0) {
            return '';
        }

        $desarrolloInt = $resolveSeriDesarrolloCm($allParams, $equipoId, $ancho, $fuelle, $alto, $calcFuelle);
        return $desarrolloInt > 0 ? (string)$desarrolloInt : '';
    };

    ob_start();

    echo '<html><head><meta charset="UTF-8"><style>';
    echo 'body{font-family:Arial,sans-serif;color:#0f172a}';
    echo '.title{font-size:22px;font-weight:800;color:#0f172a}';
    echo '.sub{font-size:12px;color:#475569;margin:6px 0 14px 0}';
    echo '.meta{margin:10px 0 16px 0;font-size:12px;color:#334155}';
    echo '.section{font-size:14px;font-weight:800;margin:20px 0 8px 0;color:#0f172a}';
    echo '.report{border-collapse:collapse;width:100%;font-size:12px}';
    echo '.report td,.report th{border:1px solid #cbd5e1;padding:6px 8px;vertical-align:middle}';
    echo '.report th{background:#0f172a;color:#fff;text-align:center;font-size:11px}';
    echo '.report tr:nth-child(even) td{background:#f8fafc}';
    echo '.right{text-align:right}';
    echo '.center{text-align:center}';
    echo '.th2{background:#145e5b;color:#fff;text-align:center;font-size:11px}';
    echo '.num-int{mso-number-format:"\#\,\#\#0";text-align:right}';
    echo '.text-cell{mso-number-format:"\@"}';
    echo '</style></head><body>';

    echo '<div class="title">Planilla Bonos Serigrafía</div>';
    echo '<div class="sub">Unibag ERP · Bonificaciones</div>';
    echo '<div class="meta"><strong>Período:</strong> ' . h($start !== '' && $end !== '' ? ($start . ' a ' . $end) : ('Mes ' . $monthKey)) . '<br><strong>Generado:</strong> ' . h((new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get())))->format('d/m/Y H:i:s')) . '</div>';

    $defaultRates = [
        'bolsas' => ['LT_3000' => 4.0, 'GTE_3000' => 3.0],
        'des_menor_25cm' => ['LT_3000' => 2.0, 'GTE_3000' => 1.0],
        'sacos' => ['LT_3000' => 8.0, 'GTE_3000' => 6.0],
    ];
    $effectiveRates = $service->getBonusUnitRates('bonoseri');
    $seriBonusConfig = new BonusConfigurationService($trzPdo);
    if (!is_array($effectiveRates) || $effectiveRates === []) {
        $effectiveRates = $defaultRates;
    }
    // Pre-calcular producción total y cantidad solicitada por CC en todo el período consultado.
    // Si una CC se realizó en más de una vez (ej: 2 días), se toma el total acumulado producido
    // de la CC para determinar el tramo correspondiente (< 3000 vs >= 3000), evitando que
    // producciones parciales a medias cobren indebidamente la tarifa de menor a 3.000.
    $allPeriodSeriRows = $rows;
    if ($operatorName !== null) {
        $allPeriodResult = $service->listErpSeriProductionForBonusPeriod($period, null);
        if (($allPeriodResult['ok'] ?? false) === true && is_array($allPeriodResult['rows'] ?? null)) {
            $allPeriodSeriRows = (array)$allPeriodResult['rows'];
        }
    }
    $totalUnitsByCc = [];
    $maxReqByCc = [];
    foreach ($allPeriodSeriRows as $r) {
        if (!is_array($r)) {
            continue;
        }
        $ccKey = trim((string)($r['cost_center'] ?? ''));
        if ($ccKey === '') {
            $ccKey = trim((string)($r['work_order_number'] ?? ''));
        }
        if ($ccKey === '') {
            continue;
        }
        $totalUnitsByCc[$ccKey] = ($totalUnitsByCc[$ccKey] ?? 0.0) + (float)($r['produced_units'] ?? 0.0);
        $reqVal = (float)($r['requested_units'] ?? 0.0);
        if (!isset($maxReqByCc[$ccKey]) || $reqVal > $maxReqByCc[$ccKey]) {
            $maxReqByCc[$ccKey] = $reqVal;
        }
    }

    $resolveSeriRate = static function (
        array $rates,
        float $ccTotalUnits,
        float $ccRequested,
        float $desarrolloCm,
        string $erpDesc,
        string $normalizedType
    ): float {
        // Para aplicar la tarifa menor a 3.000 (< 3000), debe ser la producción completa de la CC
        // menor a 3000 y que la cantidad solicitada no sea >= 3000 (no una producción a medias).
        $isGte3000 = ($ccTotalUnits >= 3000.0) || ($ccRequested >= 3000.0);
        $tier = $isGte3000 ? 'GTE_3000' : 'LT_3000';

        $descUp = strtoupper(trim($erpDesc));
        $typeUp = strtoupper(trim($normalizedType));
        $category = 'bolsas';
        if ($typeUp === 'SAC' || preg_match('/\bSAC\b/', $descUp) === 1 || str_contains($descUp, 'SACO') || str_contains($descUp, 'SACOS')) {
            $category = 'sacos';
        } elseif ($desarrolloCm > 0 && $desarrolloCm < 25) {
            $category = 'des_menor_25cm';
        }
        $rate = 0.0;
        if (isset($rates[$category]) && is_array($rates[$category])) {
            $rate = (float)($rates[$category][$tier] ?? 0.0);
        }
        return $rate;
    };
    $money = static function (float $amount): string {
        if ($amount <= 0) {
            return '$ -';
        }
        return '$ ' . number_format($amount, 2, ',', '.');
    };

    $operatorTotals = [];
    $grandUnits = 0.0;
    $grandCounter = 0.0;
    $grandBonus = 0.0;

    foreach ($grouped as $operatorLabel => $operatorRows) {
        $sumUnits = 0.0;
        $sumCounter = 0.0;
        $sumBonus = 0.0;
        foreach ((array)$operatorRows as $r) {
            $u = (float)($r['produced_units'] ?? 0);
            $counter = (float)($r['produced_machine_meters'] ?? 0);
            if ($counter <= 0 && $u > 0) {
                $counter = round($u / 5.0, 0);
            }
            $ccKey = trim((string)($r['cost_center'] ?? ''));
            if ($ccKey === '') {
                $ccKey = trim((string)($r['work_order_number'] ?? ''));
            }
            $ccTotal = (float)($totalUnitsByCc[$ccKey] ?? $u);
            $ccReq = (float)($maxReqByCc[$ccKey] ?? (float)($r['requested_units'] ?? 0));

            $normalizedType = $normalizeSeriProductType((string)($r['erp_desc'] ?? ''), (string)($r['bag_type'] ?? (string)($r['product_type'] ?? '')), (string)($r['item_title'] ?? ''));
            $desarrollo = $parseSeriDesarrollo((array)$r, $allEquipoParams);
            $desVal = (float)($desarrollo !== '' ? str_replace(',', '.', $desarrollo) : '0');
            $rate = $resolveSeriRate($effectiveRates, $ccTotal, $ccReq, $desVal, (string)($r['erp_desc'] ?? ''), $normalizedType);
            $productionFactor = $seriBonusConfig->factor('bonoseri', (string)$operatorLabel, (string)($r['event_date'] ?? ''), $monthKey);
            $bonus = $u > 0 ? ($u * $rate * $productionFactor) : 0.0;
            if ($u > 0) {
                $sumUnits += $u;
            }
            if ($counter > 0) {
                $sumCounter += $counter;
            }
            if ($bonus > 0) {
                $sumBonus += $bonus;
            }
        }
        $operatorTotals[(string)$operatorLabel] = ['units' => $sumUnits, 'counter' => $sumCounter, 'bonus' => $sumBonus];
        $grandUnits += $sumUnits;
        $grandCounter += $sumCounter;
        $grandBonus += $sumBonus;
    }

    foreach ($grouped as $operatorLabel => $operatorRows) {
        $seriHeaderCode = $service->getOperatorBonusCode((string)$operatorLabel, array_keys($grouped));
        echo '<div class="section">Operador: ' . h((string)$operatorLabel) . ' [' . h($seriHeaderCode) . ']</div>';
        echo '<table class="report"><thead>';
        echo '<tr class="op-header-row"><th colspan="15" style="background:#0f172a;color:#ffffff;font-size:13px;font-weight:900;text-align:left;padding:8px 10px;">OPERADOR: ' . h((string)$operatorLabel) . ' [' . h($seriHeaderCode) . ']</th></tr>';
        echo '<tr>';
        echo '<th colspan="12"></th>';
        echo '<th colspan="2" class="th2">Valores · Operador</th>';
        echo '<th class="th2">Bono</th>';
        echo '</tr>';
        echo '<tr>';
        echo '<th>OPERADOR</th>';
        echo '<th>N° OT</th>';
        echo '<th>N°</th>';
        echo '<th>FECHA</th>';
        echo '<th>CLIENTE</th>';
        echo '<th>DESARROLLO<br>[cm]</th>';
        echo '<th>TIPO</th>';
        echo '<th>CANTIDAD DE<br>COLORES</th>';
        echo '<th>IMPRESIÓN POR<br>DESARROLLO</th>';
        echo '<th>% VARIACIÓN</th>';
        echo '<th>CANTIDAD<br>SOLICITADA EN CC</th>';
        echo '<th>BONIFICACIÓN</th>';
        echo '<th>Suma de UNID. IMPRESAS</th>';
        echo '<th>Suma de CANTIDAD PRODUCIDA (CONTADOR)</th>';
        echo '<th>BONO</th>';
        echo '</tr>';
        echo '</thead><tbody>';

        $sumUnits = 0.0;
        $sumCounter = 0.0;
        $sumBonus = 0.0;
        foreach ((array)$operatorRows as $r) {
            $units = (float)($r['produced_units'] ?? 0);
            $counter = (float)($r['produced_machine_meters'] ?? 0);
            if ($counter <= 0 && $units > 0) {
                $counter = round($units / 5.0, 0);
            }
            $sumUnits += $units > 0 ? $units : 0.0;
            $sumCounter += $counter > 0 ? $counter : 0.0;

            $unitsLabel = $units > 0 ? (string)(int)round($units) : '';
            $counterLabel = $counter > 0 ? (string)(int)round($counter) : '';
            $req = (float)($r['requested_units'] ?? 0);
            $reqLabel = $req > 0 ? (string)(int)round($req) : '';
            $dateLabel = $toDmy((string)($r['event_date'] ?? ''));
            $normalizedType = $normalizeSeriProductType((string)($r['erp_desc'] ?? ''), (string)($r['bag_type'] ?? (string)($r['product_type'] ?? '')), (string)($r['item_title'] ?? ''));
            $desarrollo = $parseSeriDesarrollo((array)$r, $allEquipoParams);
            $desarrolloVal = $desarrollo !== '' ? (float)str_replace(',', '.', $desarrollo) : 0.0;
            $bonificacionLabel = 'PAGO';

            $ccKey = trim((string)($r['cost_center'] ?? ''));
            if ($ccKey === '') {
                $ccKey = trim((string)($r['work_order_number'] ?? ''));
            }
            $ccTotal = (float)($totalUnitsByCc[$ccKey] ?? $units);
            $ccReq = (float)($maxReqByCc[$ccKey] ?? $req);

            $rate = $resolveSeriRate($effectiveRates, $ccTotal, $ccReq, $desarrolloVal, (string)($r['erp_desc'] ?? ''), $normalizedType);
            $productionFactor = $seriBonusConfig->factor('bonoseri', (string)$operatorLabel, (string)($r['event_date'] ?? ''), $monthKey);
            $bonusAmount = $bonificacionLabel === 'PAGO' ? ($units > 0 ? ($units * $rate * $productionFactor) : 0.0) : 0.0;
            $bonificacionLabel .= ' (' . (int)round($productionFactor * 100) . '%)';
            $sumBonus += $bonusAmount > 0 ? $bonusAmount : 0.0;

            echo '<tr>';
            echo '<td>' . h((string)$operatorLabel) . '</td>';
            echo '<td class="center">' . h((string)($r['work_order_number'] ?? '')) . '</td>';
            echo '<td class="center">' . h((string)($r['cost_center'] ?? '')) . '</td>';
            echo '<td class="center">' . h($dateLabel) . '</td>';
            echo '<td>' . h((string)($r['client_label'] ?? '')) . '</td>';
            echo '<td class="center">' . h($desarrollo) . '</td>';
            echo '<td class="center">' . h($normalizedType) . '</td>';
            echo '<td class="center"></td>';
            echo '<td class="center"></td>';
            echo '<td class="center"></td>';
            echo '<td class="num-int">' . h($reqLabel) . '</td>';
            echo '<td class="center">' . h($bonificacionLabel) . '</td>';
            echo '<td class="num-int">' . h($unitsLabel) . '</td>';
            echo '<td class="num-int">' . h($counterLabel) . '</td>';
            echo '<td class="right">' . h($money($bonusAmount)) . '</td>';
            echo '</tr>';
        }

        echo '<tr>';
        echo '<td colspan="12" style="font-weight:900">Total ' . h((string)$operatorLabel) . '</td>';
        echo '<td class="num-int" style="font-weight:900">' . ($sumUnits > 0 ? (string)(int)round($sumUnits) : '') . '</td>';
        echo '<td class="num-int" style="font-weight:900">' . ($sumCounter > 0 ? (string)(int)round($sumCounter) : '') . '</td>';
        echo '<td class="right" style="font-weight:900">' . h($money($sumBonus)) . '</td>';
        echo '</tr>';

        echo '</tbody></table>';
    }

    echo '<div class="section">Totales por operador</div>';
    echo '<table class="report" style="max-width:860px"><thead><tr><th>Operador</th><th>Suma de UNID. IMPRESAS</th><th>Suma de CANTIDAD PRODUCIDA (CONTADOR)</th><th>Bono</th></tr></thead><tbody>';
    foreach ($operatorTotals as $operatorLabel => $tot) {
        $u = (float)($tot['units'] ?? 0);
        $c = (float)($tot['counter'] ?? 0);
        $b = (float)($tot['bonus'] ?? 0);
        $seriRowCode = $service->getOperatorBonusCode((string)$operatorLabel, array_keys($operatorTotals));
        echo '<tr><td><strong>[' . h($seriRowCode) . ']</strong> ' . h((string)$operatorLabel) . '</td><td class="num-int" style="font-weight:800">' . ($u > 0 ? (string)(int)round($u) : '') . '</td><td class="num-int" style="font-weight:800">' . ($c > 0 ? (string)(int)round($c) : '') . '</td><td class="right" style="font-weight:800">' . h($money($b)) . '</td></tr>';
    }
    echo '<tr><td style="font-weight:900">TOTAL</td><td class="num-int" style="font-weight:900">' . ($grandUnits > 0 ? (string)(int)round($grandUnits) : '') . '</td><td class="num-int" style="font-weight:900">' . ($grandCounter > 0 ? (string)(int)round($grandCounter) : '') . '</td><td class="right" style="font-weight:900">' . h($money($grandBonus)) . '</td></tr>';
    echo '</tbody></table>';

    echo '</body></html>';
    $html = (string)ob_get_clean();
    SimpleXlsx::streamHtml($filename, $html, 'Bono Serigrafía');
    exit;
}

// -----------------------------------------------------------------------------
// Export Bonoflexo (Excel .xls en HTML)
//
// - Lee month=YYYY-MM como “mes final” del período 26–25.
// - Consulta producción ERP via ReceptionService::listErpFlexoProductionForBonusPeriod().
// - Calcula:
//   - Merma % = merma declarada / solicitadas * 100 (ERP: prod_worker_ot_defectunits evt_type='merma' cuando exista).
//   - Regla: si merma% > 5 => “NO HAY BONO” y bono = $ -.
//   - Tramo Flexo: ReceptionService::resolveBonusBracketAmount() según unidades impresas.
//   - Bono por OT: se paga una vez por OT (cap por OT). Si el OT aparece en varios días, se paga el “restante”.
//   - Compartido: factor por operador (bonus_operator_factors), se multiplica el bono base.
// - Al final agrega tabla “Totales por operador” (solo bonos efectivamente pagables).
// -----------------------------------------------------------------------------
if ($path === '/bonificaciones/flexo/export-xls' && $method === 'GET') {
    $defaultMonthKey = date('Y-m');
    $today = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
    if ((int)$today->format('j') >= 26) {
        $defaultMonthKey = $today->modify('+1 month')->format('Y-m');
    }
    $filterType = trim((string)($_GET['filter_type'] ?? 'period'));
    if ($filterType !== 'range') {
        $filterType = 'period';
    }
    $monthKey = trim((string)($_GET['month'] ?? $defaultMonthKey));
    if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
        $monthKey = $defaultMonthKey;
    }
    $startDate = trim((string)($_GET['start_date'] ?? ''));
    $endDate = trim((string)($_GET['end_date'] ?? ''));
    if ($startDate === '' || $endDate === '') {
        $startDate = date('Y-m-d', strtotime('-6 days'));
        $endDate = date('Y-m-d');
    }
    $operatorName = trim((string)($_GET['operator'] ?? ''));
    if ($operatorName === '') {
        $operatorName = null;
    }
    $costCenter = trim((string)($_GET['cc'] ?? ''));
    if ($costCenter === '') {
        $costCenter = null;
    }

    $period = $service->resolveBonusFilterPeriod($filterType, $monthKey, $startDate, $endDate);
    $result = $service->listErpFlexoProductionForBonusPeriod($period, $operatorName, $costCenter);
    if (($result['ok'] ?? false) !== true) {
        $errList = $result['errors'] ?? [];
        $error = $errList !== [] ? trim((string)reset($errList)) : '';
        $redirectParams = ['view' => 'bonoflexo', 'filter_type' => $filterType];
        if ($filterType === 'range') {
            $redirectParams['start_date'] = $startDate;
            $redirectParams['end_date'] = $endDate;
        } else {
            $redirectParams['month'] = $monthKey;
        }
        if (is_string($operatorName) && $operatorName !== '') {
            $redirectParams['operator'] = $operatorName;
        }
        if (is_string($costCenter) && $costCenter !== '') {
            $redirectParams['cc'] = $costCenter;
        }
        $redirectParams['error'] = $error !== '' ? $error : 'No se pudo generar la planilla.';
        header('Location: /bonificaciones?' . http_build_query($redirectParams));
        exit;
    }

    $periodData = (array)($result['period'] ?? []);
    $start = (string)($periodData['start_date'] ?? '');
    $end = (string)($periodData['end_date'] ?? '');
    $filename = $filterType === 'range'
        ? ('planilla_flexo_rango_' . $start . '_a_' . $end)
        : ('planilla_flexo_' . $monthKey . '_26-25');
    if (is_string($operatorName) && $operatorName !== '') {
        $safeOperator = preg_replace('/[^A-Za-z0-9._-]+/', '_', $operatorName);
        $safeOperator = trim((string)$safeOperator, '_');
        if ($safeOperator !== '') {
            $filename .= '_operador_' . $safeOperator;
        }
    }
    if (is_string($costCenter) && $costCenter !== '') {
        $safeCc = preg_replace('/[^A-Za-z0-9._-]+/', '_', $costCenter);
        $safeCc = trim((string)$safeCc, '_');
        if ($safeCc !== '') {
            $filename .= '_cc_' . $safeCc;
        }
    }
    $filename .= '.xlsx';

    $rows = (array)($result['rows'] ?? []);
    $brackets = $service->listBonusBrackets('bonoflexo');
    $factorMap = $service->listBonusOperatorFactors('bonoflexo', $monthKey);
    $coachCfg = $service->getBonusCoachConfig('bonoflexo', $monthKey);
    $coachName = trim((string)($coachCfg['coach_name'] ?? ''));
    $coachShare = (float)($coachCfg['share_percent'] ?? 0.0);
    if (abs($coachShare - 0.5) >= 0.0001) {
        $coachShare = 0.0;
    }
    $coachTrainees = [];
    foreach ((array)($coachCfg['trainees'] ?? []) as $t) {
        $t = trim((string)$t);
        if ($t !== '') {
            $coachTrainees[$t] = true;
        }
    }
    $grouped = [];
    if ($operatorName !== null) {
        $grouped[(string)$operatorName] = $rows;
    } else {
        foreach ($rows as $row) {
            $name = is_array($row) ? trim((string)($row['operator_name'] ?? '')) : '';
            if ($name === '') {
                $name = 'Sin operador';
            }
            if (!isset($grouped[$name])) {
                $grouped[$name] = [];
            }
            $grouped[$name][] = $row;
        }
        ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);
    }

    ob_start();

    echo '<html><head><meta charset="UTF-8"><style>';
    echo 'body{font-family:Arial,sans-serif;color:#0f172a}';
    echo '.title{font-size:22px;font-weight:800;color:#0f172a}';
    echo '.sub{font-size:12px;color:#475569;margin:6px 0 14px 0}';
    echo '.meta{margin:10px 0 16px 0;font-size:12px;color:#334155}';
    echo '.section{font-size:14px;font-weight:800;margin:20px 0 8px 0;color:#0f172a}';
    echo '.report{border-collapse:collapse;width:100%;font-size:12px}';
    echo '.report td,.report th{border:1px solid #cbd5e1;padding:6px 8px;vertical-align:middle}';
    echo '.report th{background:#0f172a;color:#fff;text-align:center;font-size:11px}';
    echo '.report tr:nth-child(even) td{background:#f8fafc}';
    echo '.right{text-align:right}';
    echo '.center{text-align:center}';
    echo '.num-int{mso-number-format:"\#\,\#\#0";text-align:right}';
    echo '.text-cell{mso-number-format:"\@"}';
    echo '</style></head><body>';
    echo '<div class="title">Planilla Bonos Flexografía</div>';
    echo '<div class="sub">Unibag ERP · Bonificaciones</div>';
    echo '<div class="meta"><strong>Período:</strong> ' . h($start !== '' && $end !== '' ? ($start . ' a ' . $end) : ('Mes ' . $monthKey)) . '<br><strong>Generado:</strong> ' . h((new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get())))->format('d/m/Y H:i:s')) . '</div>';

    $operatorBaseTotals = [];
    $operatorCoachTotals = [];
    $operatorTotals = [];
    foreach ($grouped as $operatorLabel => $operatorRows) {
        $sumClp = 0.0;
        $totalUnitsByOt = [];
        foreach ((array)$operatorRows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $ot = trim((string)($r['work_order_number'] ?? ''));
            $cc = trim((string)($r['cost_center'] ?? ''));
            $key = $ot . '|' . $cc;
            $totalUnitsByOt[$key] = (float)($totalUnitsByOt[$key] ?? 0.0) + (float)($r['produced_units'] ?? 0.0);
        }
        $maxBaseBonusByOt = [];
        foreach ($totalUnitsByOt as $key => $totalUnits) {
            $maxBaseBonusByOt[(string)$key] = (float)$service->resolveBonusBracketAmount($brackets, (float)$totalUnits);
        }
        $usedBaseBonusByOt = [];

        foreach ((array)$operatorRows as $r) {
            $units = (float)($r['produced_units'] ?? 0);
            // Para el bono de flexografía se toma exclusivamente la merma de impresión (dejando afuera alistamiento de bobina y reparación)
            $declaredWasteUnits = (float)($r['waste_print_units'] ?? 0.0);
            $declaredWasteKg    = (float)($r['waste_print_kg']    ?? 0.0);
            $grammageG  = (float)($r['grammage_g']         ?? 0.0);
            $dimWidth   = (float)($r['dim_width_cm']       ?? 0.0);
            $dimHeight  = (float)($r['dim_height_cm']      ?? 0.0);
            $dimFuelle  = (float)($r['dim_fuelle_cm']      ?? 0.0);
            $dimManilla = (float)($r['dim_manilla_length'] ?? 0.0);
            $itemWeight = (float)($r['item_weight']        ?? 0.0);

            // Calcular peso unitario teórico (igual que informe de producción)
            $unitWeightKg = 0.0;
            if ($itemWeight > 0) {
                $unitWeightKg = $itemWeight / 1000.0;
            } elseif ($dimWidth > 0 && $dimHeight > 0 && $grammageG > 0) {
                $areaM2       = (2.0 * ($dimWidth + $dimFuelle) * $dimHeight) / 10000.0;
                $bodyKg       = $areaM2 * ($grammageG / 1000.0);
                $manillaKg    = ($dimManilla > 0) ? (2.0 * ($dimManilla / 100.0) * 0.025 * ($grammageG / 1000.0)) : 0.0;
                $unitWeightKg = $bodyKg + $manillaKg;
            }

            // Convertir kg de merma a unidades si hay kg y peso unitario; si no, usar unidades declaradas
            if ($unitWeightKg > 0 && $declaredWasteKg > 0) {
                $effectiveWasteUnits = round($declaredWasteKg / $unitWeightKg);
            } else {
                $effectiveWasteUnits = $declaredWasteUnits;
            }

            // Denominador: si hubo producción, calcular porcentaje sobre unidades producidas (igual a informe producción).
            // Si no hubo producción, comparar contra solicitadas.
            $denom = $units > 0 ? $units : $requested;
            $mermaPercent = $denom > 0 ? (abs($effectiveWasteUnits) / $denom * 100.0) : 0.0;
            if ($mermaPercent > 5.0) {
                continue;
            }
            $factor = (float)($factorMap[(string)$operatorLabel] ?? 1.0);
            $ot = trim((string)($r['work_order_number'] ?? ''));
            $cc = trim((string)($r['cost_center'] ?? ''));
            $otKey = $ot . '|' . $cc;
            $maxBaseBonus = (float)($maxBaseBonusByOt[$otKey] ?? 0.0);
            $usedBase = (float)($usedBaseBonusByOt[$otKey] ?? 0.0);
            $rowBaseBonus = (float)$service->resolveBonusBracketAmount($brackets, $units);
            $allowedBase = max(0.0, $maxBaseBonus - $usedBase);
            $payBase = min($rowBaseBonus, $allowedBase);
            if ($payBase > 0) {
                $usedBaseBonusByOt[$otKey] = $usedBase + $payBase;
            }
            $bonusClp = $payBase > 0 ? round($payBase * $factor, 0) : 0.0;
            if ($bonusClp > 0) {
                $sumClp += $bonusClp;
            }

            $operatorRowName = trim((string)($r['operator_name'] ?? ''));
            $coachBonusClp = ($payBase > 0 && $coachName !== '' && $coachShare > 0 && isset($coachTrainees[$operatorRowName]))
                ? round($payBase * $coachShare, 0)
                : 0.0;
            if ($coachBonusClp > 0) {
                $operatorCoachTotals[$coachName] = (float)($operatorCoachTotals[$coachName] ?? 0.0) + $coachBonusClp;
                $operatorTotals[$coachName] = (float)($operatorTotals[$coachName] ?? 0.0) + $coachBonusClp;
            }
        }
        $operatorBaseTotals[(string)$operatorLabel] = (float)($operatorBaseTotals[(string)$operatorLabel] ?? 0.0) + $sumClp;
        $operatorTotals[(string)$operatorLabel] = (float)($operatorTotals[(string)$operatorLabel] ?? 0.0) + $sumClp;
    }
    ksort($operatorTotals, SORT_NATURAL | SORT_FLAG_CASE);
    $grandTotalClp = array_sum(array_map('floatval', $operatorTotals));

    foreach ($grouped as $operatorLabel => $operatorRows) {
        $totalUnitsByOt = [];
        foreach ((array)$operatorRows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $ot = trim((string)($r['work_order_number'] ?? ''));
            $cc = trim((string)($r['cost_center'] ?? ''));
            $key = $ot . '|' . $cc;
            $totalUnitsByOt[$key] = (float)($totalUnitsByOt[$key] ?? 0.0) + (float)($r['produced_units'] ?? 0.0);
        }
        $maxBaseBonusByOt = [];
        foreach ($totalUnitsByOt as $key => $totalUnits) {
            $maxBaseBonusByOt[(string)$key] = (float)$service->resolveBonusBracketAmount($brackets, (float)$totalUnits);
        }
        $usedBaseBonusByOt = [];

        $flexoHeaderCode = $service->getOperatorBonusCode((string)$operatorLabel, array_keys($grouped));
        echo '<div class="section">Operador: ' . h((string)$operatorLabel) . ' [' . h($flexoHeaderCode) . ']</div>';
        echo '<table class="report"><thead>';
        echo '<tr class="op-header-row"><th colspan="20" style="background:#0f172a;color:#ffffff;font-size:13px;font-weight:900;text-align:left;padding:8px 10px;">OPERADOR: ' . h((string)$operatorLabel) . ' [' . h($flexoHeaderCode) . ']</th></tr>';
        echo '<tr>';
        echo '<th>OPERADOR</th>';
        echo '<th>N°<br>IMPRESORA</th>';
        echo '<th>N°<br>CC</th>';
        echo '<th>N°<br>OT</th>';
        echo '<th>FECHA</th>';
        echo '<th>CLIENTE</th>';
        echo '<th>TIPO<br>PRODUCTO</th>';
        echo '<th>MEDIDA<br>[cm]</th>';
        echo '<th>APOYO</th>';
        echo '<th>BONIFICACIÓN</th>';
        echo '<th>Total UNID.<br>IMPRESAS</th>';
        echo '<th>%</th>';
        echo '<th>Compartido</th>';
        echo '<th>Bono</th>';
        echo '<th>Mentor</th>';
        echo '<th>Bono<br>Mentor</th>';
        echo '<th>OBSERVACIONES</th>';
        echo '<th>UNID<br>SOLICITADAS</th>';
        echo '<th>unid<br>impresas</th>';
        echo '<th>DIFERENCIA</th>';
        echo '</tr>';
        echo '</thead><tbody>';

        foreach ((array)$operatorRows as $r) {
            $units = (float)($r['produced_units'] ?? 0);
            $requested = (float)($r['requested_units'] ?? 0);
            $diff = $requested > 0 ? ($requested - $units) : 0;
            $unitsLabel = $units > 0 ? (string)(int)round($units) : '';
            $reqLabel = $requested > 0 ? (string)(int)round($requested) : '';
            $diffLabel = $reqLabel !== '' ? (string)(int)round($diff) : '';
            $factor = (float)($factorMap[(string)$operatorLabel] ?? 1.0);
            // Para el bono de flexografía se toma exclusivamente la merma de impresión (dejando afuera alistamiento de bobina y reparación)
            $declaredWasteUnits = (float)($r['waste_print_units'] ?? 0.0);
            $declaredWasteKg    = (float)($r['waste_print_kg']    ?? 0.0);
            $grammageG  = (float)($r['grammage_g']         ?? 0.0);
            $dimWidth   = (float)($r['dim_width_cm']       ?? 0.0);
            $dimHeight  = (float)($r['dim_height_cm']      ?? 0.0);
            $dimFuelle  = (float)($r['dim_fuelle_cm']      ?? 0.0);
            $dimManilla = (float)($r['dim_manilla_length'] ?? 0.0);
            $itemWeight = (float)($r['item_weight']        ?? 0.0);

            // Calcular peso unitario teórico (igual que informe de producción)
            $unitWeightKg = 0.0;
            if ($itemWeight > 0) {
                $unitWeightKg = $itemWeight / 1000.0;
            } elseif ($dimWidth > 0 && $dimHeight > 0 && $grammageG > 0) {
                $areaM2       = (2.0 * ($dimWidth + $dimFuelle) * $dimHeight) / 10000.0;
                $bodyKg       = $areaM2 * ($grammageG / 1000.0);
                $manillaKg    = ($dimManilla > 0) ? (2.0 * ($dimManilla / 100.0) * 0.025 * ($grammageG / 1000.0)) : 0.0;
                $unitWeightKg = $bodyKg + $manillaKg;
            }

            // Convertir kg de merma a unidades si hay kg y peso unitario; si no, usar unidades declaradas
            if ($unitWeightKg > 0 && $declaredWasteKg > 0) {
                $effectiveWasteUnits = round($declaredWasteKg / $unitWeightKg);
            } else {
                $effectiveWasteUnits = $declaredWasteUnits;
            }

            // Denominador: si hubo producción, calcular porcentaje sobre unidades producidas (igual a informe producción).
            // Si no hubo producción, comparar contra solicitadas.
            $denom = $units > 0 ? $units : $requested;
            $mermaPercent = $denom > 0 ? (abs($effectiveWasteUnits) / $denom * 100.0) : 0.0;
            $mermaLabel = number_format($mermaPercent, 2, ',', '.') . '%';
            $hasBonus = $mermaPercent <= 5.0;
            $bonificacionLabel = $hasBonus ? 'PAGO' : 'NO HAY BONO';
            $ot = trim((string)($r['work_order_number'] ?? ''));
            $cc = trim((string)($r['cost_center'] ?? ''));
            $otKey = $ot . '|' . $cc;
            $maxBaseBonus = (float)($maxBaseBonusByOt[$otKey] ?? 0.0);
            $usedBase = (float)($usedBaseBonusByOt[$otKey] ?? 0.0);
            $rowBaseBonus = (float)$service->resolveBonusBracketAmount($brackets, $units);
            $allowedBase = max(0.0, $maxBaseBonus - $usedBase);
            $payBase = $hasBonus ? min($rowBaseBonus, $allowedBase) : 0.0;
            if ($hasBonus && $payBase > 0) {
                $usedBaseBonusByOt[$otKey] = $usedBase + $payBase;
            }
            $bonusClp = $payBase > 0 ? round($payBase * $factor, 0) : 0.0;
            $bonusLabel = $bonusClp > 0 ? ('$ ' . number_format($bonusClp, 0, ',', '.')) : '$ -';
            $operatorRowName = trim((string)($r['operator_name'] ?? ''));
            $coachBonusClp = ($hasBonus && $payBase > 0 && $coachName !== '' && $coachShare > 0 && isset($coachTrainees[$operatorRowName]))
                ? round($payBase * $coachShare, 0)
                : 0.0;
            $coachBonusLabel = $coachBonusClp > 0 ? ('$ ' . number_format($coachBonusClp, 0, ',', '.')) : '$ -';

            echo '<tr>';
            echo '<td>' . h((string)$operatorLabel) . '</td>';
            echo '<td class="center">' . h((string)($r['printer_no'] ?? '')) . '</td>';
            echo '<td class="center">' . h((string)($r['cost_center'] ?? '')) . '</td>';
            echo '<td class="center">' . h((string)($r['work_order_number'] ?? '')) . '</td>';
            echo '<td class="center">' . h((string)($r['event_date'] ?? '')) . '</td>';
            echo '<td>' . h((string)($r['client_label'] ?? '')) . '</td>';
            echo '<td class="center">' . h((string)($r['product_type'] ?? '')) . '</td>';
            echo '<td class="center">' . h((string)($r['measure_cm'] ?? '')) . '</td>';
            echo '<td class="center">' . h((string)($r['helper_label'] ?? '')) . '</td>';
            echo '<td class="center">' . h($bonificacionLabel) . '</td>';
            echo '<td class="num-int">' . h($unitsLabel) . '</td>';
            echo '<td class="center">' . h($mermaLabel) . '</td>';
            echo '<td class="center">' . h(rtrim(rtrim(number_format($factor, 1, '.', ''), '0'), '.')) . '</td>';
            echo '<td class="right">' . h($bonusLabel) . '</td>';
            echo '<td class="center">' . h($coachName !== '' && isset($coachTrainees[$operatorRowName]) ? $coachName : '') . '</td>';
            echo '<td class="right">' . h($coachBonusLabel) . '</td>';
            echo '<td></td>';
            echo '<td class="num-int">' . h($reqLabel) . '</td>';
            echo '<td class="num-int">' . h($unitsLabel) . '</td>';
            echo '<td class="num-int">' . h($diffLabel) . '</td>';
            echo '</tr>';
        }
        $opBaseBonusTotal = (float)($operatorBaseTotals[(string)$operatorLabel] ?? 0.0);
        $opBaseBonusLabel = $opBaseBonusTotal > 0 ? ('$ ' . number_format($opBaseBonusTotal, 0, ',', '.')) : '$ -';
        echo '<tr><td colspan="13" style="font-weight:900">Total ' . h((string)$operatorLabel) . '</td><td class="right" style="font-weight:900">' . h($opBaseBonusLabel) . '</td><td colspan="6"></td></tr>';
        echo '</tbody></table>';
    }

    echo '<div class="section">Totales por operador</div>';
    $allTotalNames = array_values(array_unique(array_merge(array_keys($operatorBaseTotals), array_keys($operatorCoachTotals), array_keys($operatorTotals))));
    sort($allTotalNames, SORT_NATURAL | SORT_FLAG_CASE);
    echo '<table class="report" style="max-width:760px"><thead><tr><th>Operador</th><th>Bono operador</th><th>Bono mentor</th><th>Total</th></tr></thead><tbody>';
    $sumBaseAll = 0.0;
    $sumCoachAll = 0.0;
    foreach ($allTotalNames as $operatorLabel) {
        $baseClp = (float)($operatorBaseTotals[(string)$operatorLabel] ?? 0.0);
        $coachClp = (float)($operatorCoachTotals[(string)$operatorLabel] ?? 0.0);
        $totalClp = (float)($operatorTotals[(string)$operatorLabel] ?? 0.0);
        $sumBaseAll += $baseClp;
        $sumCoachAll += $coachClp;
        $baseLabel = $baseClp > 0 ? ('$ ' . number_format($baseClp, 0, ',', '.')) : '$ -';
        $coachLabel = $coachClp > 0 ? ('$ ' . number_format($coachClp, 0, ',', '.')) : '$ -';
        $totalLabel = $totalClp > 0 ? ('$ ' . number_format($totalClp, 0, ',', '.')) : '$ -';
        $flexoRowCode = $service->getOperatorBonusCode((string)$operatorLabel, $allTotalNames);
        echo '<tr><td><strong>[' . h($flexoRowCode) . ']</strong> ' . h((string)$operatorLabel) . '</td><td class="right">' . h($baseLabel) . '</td><td class="right">' . h($coachLabel) . '</td><td class="right" style="font-weight:800">' . h($totalLabel) . '</td></tr>';
    }
    $sumBaseLabel = $sumBaseAll > 0 ? ('$ ' . number_format($sumBaseAll, 0, ',', '.')) : '$ -';
    $sumCoachLabel = $sumCoachAll > 0 ? ('$ ' . number_format($sumCoachAll, 0, ',', '.')) : '$ -';
    $grandLabel = $grandTotalClp > 0 ? ('$ ' . number_format((float)$grandTotalClp, 0, ',', '.')) : '$ -';
    echo '<tr><td style="font-weight:900">TOTAL</td><td class="right" style="font-weight:900">' . h($sumBaseLabel) . '</td><td class="right" style="font-weight:900">' . h($sumCoachLabel) . '</td><td class="right" style="font-weight:900">' . h($grandLabel) . '</td></tr>';
    echo '</tbody></table>';

    echo '</body></html>';
    $html = (string)ob_get_clean();
    SimpleXlsx::streamHtml($filename, $html, 'Bono Flexo');
    exit;
}

if ($path === '/bonificaciones/config/seed-flexo' && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $brackets = [
        ['range_from' => 0, 'range_to' => 9999, 'amount_clp' => 5000.0],
        ['range_from' => 10000, 'range_to' => 19999, 'amount_clp' => 10000.0],
        ['range_from' => 20000, 'range_to' => 29999, 'amount_clp' => 15000.0],
        ['range_from' => 30000, 'range_to' => 39999, 'amount_clp' => 20000.0],
        ['range_from' => 40000, 'range_to' => 49999, 'amount_clp' => 25000.0],
        ['range_from' => 50000, 'range_to' => 59999, 'amount_clp' => 30000.0],
        ['range_from' => 60000, 'range_to' => 69999, 'amount_clp' => 35000.0],
        ['range_from' => 70000, 'range_to' => 79999, 'amount_clp' => 40000.0],
        ['range_from' => 80000, 'range_to' => 89999, 'amount_clp' => 45000.0],
        ['range_from' => 90000, 'range_to' => 99999, 'amount_clp' => 50000.0],
        ['range_from' => 100000, 'range_to' => 109999, 'amount_clp' => 55000.0],
        ['range_from' => 110000, 'range_to' => 119999, 'amount_clp' => 60000.0],
        ['range_from' => 120000, 'range_to' => 129999, 'amount_clp' => 65000.0],
        ['range_from' => 130000, 'range_to' => 139999, 'amount_clp' => 70000.0],
        ['range_from' => 140000, 'range_to' => 149999, 'amount_clp' => 75000.0],
        ['range_from' => 150000, 'range_to' => 159999, 'amount_clp' => 80000.0],
        ['range_from' => 160000, 'range_to' => 169999, 'amount_clp' => 85000.0],
        ['range_from' => 170000, 'range_to' => 179999, 'amount_clp' => 90000.0],
        ['range_from' => 180000, 'range_to' => 189999, 'amount_clp' => 95000.0],
        ['range_from' => 190000, 'range_to' => 199999, 'amount_clp' => 100000.0],
        ['range_from' => 200000, 'range_to' => 209999, 'amount_clp' => 105000.0],
        ['range_from' => 210000, 'range_to' => 219999, 'amount_clp' => 110000.0],
        ['range_from' => 220000, 'range_to' => 229999, 'amount_clp' => 115000.0],
        ['range_from' => 230000, 'range_to' => 239999, 'amount_clp' => 120000.0],
        ['range_from' => 240000, 'range_to' => 249999, 'amount_clp' => 125000.0],
        ['range_from' => 250000, 'range_to' => 259999, 'amount_clp' => 130000.0],
        ['range_from' => 260000, 'range_to' => 269999, 'amount_clp' => 135000.0],
        ['range_from' => 270000, 'range_to' => 279999, 'amount_clp' => 140000.0],
        ['range_from' => 280000, 'range_to' => 289999, 'amount_clp' => 145000.0],
        ['range_from' => 290000, 'range_to' => 299999, 'amount_clp' => 150000.0],
        ['range_from' => 300000, 'range_to' => 309999, 'amount_clp' => 155000.0],
        ['range_from' => 310000, 'range_to' => 319999, 'amount_clp' => 160000.0],
        ['range_from' => 320000, 'range_to' => 329999, 'amount_clp' => 165000.0],
        ['range_from' => 330000, 'range_to' => 339999, 'amount_clp' => 170000.0],
        ['range_from' => 340000, 'range_to' => 349999, 'amount_clp' => 175000.0],
        ['range_from' => 350000, 'range_to' => 359999, 'amount_clp' => 180000.0],
        ['range_from' => 360000, 'range_to' => 369999, 'amount_clp' => 185000.0],
        ['range_from' => 370000, 'range_to' => 379999, 'amount_clp' => 190000.0],
        ['range_from' => 380000, 'range_to' => 389999, 'amount_clp' => 195000.0],
        ['range_from' => 390000, 'range_to' => 399999, 'amount_clp' => 200000.0],
        ['range_from' => 400000, 'range_to' => 409999, 'amount_clp' => 205000.0],
        ['range_from' => 410000, 'range_to' => 419999, 'amount_clp' => 210000.0],
        ['range_from' => 420000, 'range_to' => 429999, 'amount_clp' => 215000.0],
        ['range_from' => 430000, 'range_to' => 439999, 'amount_clp' => 220000.0],
        ['range_from' => 440000, 'range_to' => 449999, 'amount_clp' => 225000.0],
        ['range_from' => 450000, 'range_to' => 459999, 'amount_clp' => 230000.0],
        ['range_from' => 460000, 'range_to' => 469999, 'amount_clp' => 235000.0],
        ['range_from' => 470000, 'range_to' => 479999, 'amount_clp' => 240000.0],
        ['range_from' => 480000, 'range_to' => 489999, 'amount_clp' => 245000.0],
        ['range_from' => 490000, 'range_to' => 499999, 'amount_clp' => 250000.0],
    ];
    $result = $service->replaceBonusBrackets('bonoflexo', $brackets);
    if (($result['ok'] ?? false) === true) {
        header('Location: /bonificaciones?view=configuracion&bonus=bonoflexo&saved=1');
        exit;
    }
    $errList = $result['errors'] ?? [];
    $error = $errList !== [] ? trim((string)reset($errList)) : '';
    header('Location: /bonificaciones?view=configuracion&bonus=bonoflexo&error=' . rawurlencode($error !== '' ? $error : 'No se pudo cargar la tabla.'));
    exit;
}

if ($path === '/bonificaciones/config/save-flexo-unit' && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $bonusCode = strtolower(trim((string)($_POST['bonus_code'] ?? 'bonoflexo')));
    $catCodes = $_POST['cat_codes'] ?? [];
    $rateLt = $_POST['rate_lt_3000'] ?? [];
    $rateGte = $_POST['rate_gte_3000'] ?? [];

    $rates = [];
    if (is_array($catCodes)) {
        foreach ($catCodes as $cat) {
            $cat = strtolower(trim((string)$cat));
            if ($cat === '') {
                continue;
            }
            $ltVal = isset($rateLt[$cat]) ? (float)$rateLt[$cat] : 0.0;
            $gteVal = isset($rateGte[$cat]) ? (float)$rateGte[$cat] : 0.0;
            $rates[$cat] = ['LT_3000' => $ltVal, 'GTE_3000' => $gteVal];
        }
    }

    $result = $service->replaceBonusUnitRates($bonusCode, $rates);
    if (($result['ok'] ?? false) === true) {
        header('Location: /bonificaciones?view=configuracion&bonus=' . rawurlencode($bonusCode) . '&saved=1');
        exit;
    }
    $errList = $result['errors'] ?? [];
    $error = $errList !== [] ? trim((string)reset($errList)) : '';
    header('Location: /bonificaciones?view=configuracion&bonus=' . rawurlencode($bonusCode) . '&error=' . rawurlencode($error !== '' ? $error : 'No se pudo guardar la configuración.'));
    exit;
}

if ($path === '/bonificaciones/config/save-cys' && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $bonusCode = strtolower(trim((string)($_POST['bonus_code'] ?? 'bonocys')));
    $catCodes = $_POST['cat_codes'] ?? [];
    $rateBase = $_POST['rate_base'] ?? [];
    $configChangeAmount = (float)($_POST['config_change_amount'] ?? 0);

    $rates = [];
    if (is_array($catCodes)) {
        foreach ($catCodes as $cat) {
            $cat = strtolower(trim((string)$cat));
            if ($cat === '') {
                continue;
            }
            $baseVal = isset($rateBase[$cat]) ? (float)$rateBase[$cat] : 0.0;
            $rates[$cat] = ['BASE' => $baseVal];
        }
    }
    $rates['cambio_configuracion'] = ['AMOUNT' => $configChangeAmount];

    $result = $service->replaceBonusUnitRates($bonusCode, $rates);
    if (($result['ok'] ?? false) === true) {
        header('Location: /bonificaciones?view=configuracion&bonus=' . rawurlencode($bonusCode) . '&saved=1');
        exit;
    }
    $errList = $result['errors'] ?? [];
    $error = $errList !== [] ? trim((string)reset($errList)) : '';
    header('Location: /bonificaciones?view=configuracion&bonus=' . rawurlencode($bonusCode) . '&error=' . rawurlencode($error !== '' ? $error : 'No se pudo guardar la configuración.'));
    exit;
}

// -----------------------------------------------------------------------------
// Configuración Bonoflexo · Guardar “Compartido” (factor) por operador
//
// - Se recibe desde el modal como dos arrays paralelos:
//   operators[] + factors[].
// - Los factores permitidos se validan en ReceptionService::replaceBonusOperatorFactors():
//   {1.0, 0.9, 0.8}. El 1.0 no se persiste (se asume por defecto).
// -----------------------------------------------------------------------------
if ($path === '/bonificaciones/config/save-flexo-factors' && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $bonusCode = strtolower(trim((string)($_POST['bonus_code'] ?? 'bonoflexo')));
    $monthKey = trim((string)($_POST['month_key'] ?? date('Y-m')));
    if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
        $monthKey = date('Y-m');
    }
    $operators = $_POST['operators'] ?? [];
    $factors = $_POST['factors'] ?? [];
    $coachName = trim((string)($_POST['coach_name'] ?? ''));
    $coachShare = (float)($_POST['coach_share'] ?? 0.0);
    $trainees = $_POST['trainees'] ?? [];
    $byOp = [];
    if (is_array($operators) && is_array($factors)) {
        foreach ($operators as $i => $op) {
            if (!array_key_exists($i, $factors)) {
                continue;
            }
            $name = trim((string)$op);
            if ($name === '') {
                continue;
            }
            $byOp[$name] = $factors[$i];
        }
    }
    $result = $service->replaceBonusOperatorFactors($bonusCode, $monthKey, $byOp);
    $resultCoach = $service->replaceBonusCoachConfig($bonusCode, $monthKey, $coachName, $coachShare, is_array($trainees) ? $trainees : []);
    if (($result['ok'] ?? false) === true && ($resultCoach['ok'] ?? false) === true) {
        header('Location: /bonificaciones?view=configuracion&bonus=' . rawurlencode($bonusCode) . '&month=' . rawurlencode($monthKey) . '&saved=1');
        exit;
    }
    $errList = array_merge((array)($result['errors'] ?? []), (array)($resultCoach['errors'] ?? []));
    $error = $errList !== [] ? trim((string)reset($errList)) : '';
    header('Location: /bonificaciones?view=configuracion&bonus=' . rawurlencode($bonusCode) . '&month=' . rawurlencode($monthKey) . '&error=' . rawurlencode($error !== '' ? $error : 'No se pudo guardar la configuración.'));
    exit;
}

if ($path === '/bonificaciones/ayudante/helpers-save' && $method === 'POST') {
    requireBonusConfigurationEditAccess();
    requireCsrf();
    $monthKey = trim((string)($_POST['month'] ?? date('Y-m')));
    // Old forms must not change the global roster behind monthly configuration.
    // Los formularios antiguos ya no alteran el listado global entre meses.
    header('Location: /bonificaciones?view=configuracion&bonus=bonoayudante&month=' . rawurlencode($monthKey));
    exit;
}

if ($path === '/bonificaciones/ayudante/month-save' && $method === 'POST') {
    requireBonusConfigurationEditAccess();
    requireCsrf();
    $monthKey = trim((string)($_POST['month'] ?? date('Y-m')));
    $operators = $_POST['operators'] ?? [];
    $pScore = $_POST['p_score'] ?? [];
    $eScore = $_POST['e_score'] ?? [];
    $mScore = $_POST['m_score'] ?? [];
    $additional = $_POST['additional'] ?? [];
    $obs = $_POST['obs'] ?? [];

    $rows = [];
    $validForm = is_array($operators) && $operators !== [];
    foreach ([$pScore,$eScore,$mScore,$additional,$obs] as $values) {
        if (!is_array($values) || !is_array($operators) || array_keys($values) !== array_keys($operators)) $validForm = false;
    }
    if ($validForm) {
        foreach ($operators as $i => $op) {
            $rows[] = [
                'operator_name' => (string)$op,
                'proactividad_score' => $pScore[$i],
                'eficiencia_score' => $eScore[$i],
                'multitarea_score' => $mScore[$i],
                'additional_clp' => $additional[$i],
                'observations' => (string)$obs[$i],
            ];
        }
    }

    $result = $validForm ? $service->saveBonusHelperMonthlyRows($monthKey, $rows, bonusConfigurationActor())
        : ['ok'=>false,'errors'=>['Formulario incompleto. Recarga el mes antes de guardar.']];
    if (($result['ok'] ?? false) === true) {
        header('Location: /bonificaciones?view=bonoayudante&month=' . rawurlencode($monthKey) . '&saved=1');
        exit;
    }
    $errList = $result['errors'] ?? [];
    $error = $errList !== [] ? trim((string)reset($errList)) : '';
    header('Location: /bonificaciones?view=bonoayudante&month=' . rawurlencode($monthKey) . '&error=' . rawurlencode($error !== '' ? $error : 'No se pudo guardar.'));
    exit;
}

if ($path === '/bonificaciones/config/save' && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $bonusCode = strtolower(trim((string)($_POST['bonus_code'] ?? '')));
    $id = (int)($_POST['id'] ?? 0);
    $rangeFrom = (int)($_POST['range_from'] ?? 0);
    $rangeToRaw = trim((string)($_POST['range_to'] ?? ''));
    $rangeTo = $rangeToRaw === '' ? null : (int)$rangeToRaw;
    $amountClp = (float)($_POST['amount_clp'] ?? 0);
    $result = $service->saveBonusBracket($bonusCode, $id > 0 ? $id : null, $rangeFrom, $rangeTo, $amountClp);
    if (($result['ok'] ?? false) === true) {
        header('Location: /bonificaciones?view=configuracion&bonus=' . rawurlencode($bonusCode) . '&saved=1');
        exit;
    }
    $errList = $result['errors'] ?? [];
    $error = $errList !== [] ? trim((string)reset($errList)) : '';
    header('Location: /bonificaciones?view=configuracion&bonus=' . rawurlencode($bonusCode) . '&error=' . rawurlencode($error !== '' ? $error : 'No se pudo guardar la configuración.'));
    exit;
}

if ($path === '/bonificaciones/config/delete' && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $bonusCode = strtolower(trim((string)($_POST['bonus_code'] ?? '')));
    $id = (int)($_POST['id'] ?? 0);
    $result = $service->deleteBonusBracket($bonusCode, $id);
    if (($result['ok'] ?? false) === true) {
        header('Location: /bonificaciones?view=configuracion&bonus=' . rawurlencode($bonusCode) . '&deleted=1');
        exit;
    }
    $errList = $result['errors'] ?? [];
    $error = $errList !== [] ? trim((string)reset($errList)) : '';
    header('Location: /bonificaciones?view=configuracion&bonus=' . rawurlencode($bonusCode) . '&error=' . rawurlencode($error !== '' ? $error : 'No se pudo eliminar el tramo.'));
    exit;
}

    return false;
}
