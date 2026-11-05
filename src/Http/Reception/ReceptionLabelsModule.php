<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Etiquetas Térmicas Barpos/Zebra y SVG (src/Http/Reception/ReceptionLabelsModule.php)
// =============================================================================

function saveReceptionLabelBatch(array $result, ReceptionService $service, PrintService $printer): array
{
    $ids = array_values(array_filter(array_map('intval', $result['ids'] ?? [$result['id'] ?? 0])));
    $token = bin2hex(random_bytes(16));
    $batch = ['ids' => $ids, 'created_at' => time(), 'sync_error' => $result['sync_error'] ?? '', 'print_error' => '', 'printed' => '0'];
    // A print failure must never turn a saved receipt into a second receipt.
    // Un fallo de impresion no debe provocar una segunda recepcion.
    try {
        if ($printer->isEnabled()) {
            $rolls = [];
            foreach ($ids as $id) {
                $roll = $service->getRoll($id);
                if (!is_array($roll)) {
                    throw new RuntimeException('No se pudo cargar una etiqueta del lote.');
                }
                $rolls[] = $roll;
            }
            $printResult = $printer->printRollLabels($rolls);
            $isClientQueued = !empty($printResult['client_queued']);
            $batch['client_queued'] = $isClientQueued ? '1' : '0';
            $batch['printed'] = ($printResult['ok'] ?? false) ? '1' : '0';
            $batch['print_error'] = (($printResult['ok'] ?? false) || $isClientQueued) ? '' : (string)($printResult['error'] ?? 'Error de impresion.');
        }
    } catch (Throwable $e) {
        $batch['print_error'] = $e->getMessage();
    }
    $_SESSION['reception_label_batches'][$token] = $batch;
    while (count($_SESSION['reception_label_batches']) > 20) {
        array_shift($_SESSION['reception_label_batches']);
    }
    return ['token' => $token, 'printed' => $batch['printed']];
}

function renderReceptionBatchNotice(): string
{
    $token = (string)($_GET['saved_batch'] ?? '');
    $batch = $_SESSION['reception_label_batches'][$token] ?? null;
    if (!is_array($batch)) {
        return '';
    }
    $count = count($batch['ids']);
    $body = '<div class="card" style="margin-bottom:14px"><strong>Recepcion guardada: ' . $count . ' codigo(s) y ' . $count . ' etiqueta(s).</strong> ';
    $body .= '<a class="btn" href="/reception/labels/' . h($token) . '?server_print=1">⚡ Imprimir lote en Térmica</a> <a class="btn secondary" target="_blank" href="/reception/labels/' . h($token) . '">👁️ Ver lote 10×7</a>';
    if ($batch['printed'] === '1' || ($batch['client_queued'] ?? '0') === '1') {
        $body .= '<p>Lote enviado a la impresora.</p>';
    }
    foreach (['sync_error', 'print_error'] as $field) {
        if (!empty($batch[$field])) {
            $body .= '<p class="err">' . h((string)$batch[$field]) . ' No vuelvas a ingresar estas unidades.</p>';
        }
    }
    if ($batch['printed'] !== '1' && empty($batch['browser_print_opened'])) {
        $_SESSION['reception_label_batches'][$token]['browser_print_opened'] = true;
        // Impresión térmica gestionada directamente por UnibagHardware sin iframes ocultos de 1x1px
    }
    return $body . '</div>';
}


function renderReceptionThermalLabel(array $data): void
{
    echo renderView('reception/thermal_label', ['data' => $data]);
    exit;
}


function handleReceptionLabelsRoutes(string $path, string $method, ReceptionService $service, PrintService $printer, ?PDO $trzPdo = null): bool
{
if (preg_match('#^/import-containers/(\\d+)/(?:no-weight-labels|print-labels)$#', $path, $m) === 1) {
    $containerId = (int)$m[1];
    if (!unibagIsUserJavier() && !unibagCanUserPerformModifications()) {
        http_response_code(403);
        render('Acceso Restringido', '<div class="card" style="padding:24px;border-left:5px solid #ef4444;background:#fff;border-radius:12px;max-width:640px;margin:30px auto"><h2 style="margin-top:0;color:#991b1b;display:flex;align-items:center;gap:8px"><span>⛔</span> Acceso Restringido</h2><p style="color:#475569;font-size:14px;line-height:1.5">La generación e impresión de etiquetas sin peso con código de trazabilidad para contenedores está reservada y autorizada exclusivamente para <strong>Javier Perozo</strong>.</p><div style="margin-top:16px"><a class="btn secondary" href="/import-containers/' . $containerId . '">Volver al Contenedor</a></div></div>');
        exit;
    }

    $container = $service->getImportContainer($containerId);
    if ($container === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe el contenedor.</div>');
        exit;
    }

    $cCode = trim((string)$container['container_code']);
    $cTitle = $cCode !== '' ? ('Contenedor ' . $cCode) : ('Cargamento #' . $containerId);
    $allLines = $service->listImportContainerLines($containerId);
    $filterLineId = isset($_REQUEST['line']) ? (int)$_REQUEST['line'] : 0;
    $labelWarehouses = $service->listWarehousesForReception();
    $labelWarehouseOptions = '<option value="">Seleccionar bodega</option>';
    foreach ($labelWarehouses as $labelWarehouse) {
        $labelWarehouseCode = (string)$labelWarehouse['code'];
        $labelWarehouseOptions .= '<option value="' . (int)$labelWarehouse['id'] . '">' . h($labelWarehouseCode . ' - ' . $labelWarehouse['name']) . '</option>';
    }
    $printerProfile = unibagNormalizePrinterProfile((string)($_REQUEST['printer_profile'] ?? 'default'));
    $activePrinter = $printer->withProfile($printerProfile);
    $configuredPrinterName = $activePrinter->getConfiguredPrinterName();
    $configuredPrintLang = strtoupper((string)($activePrinter->getConfiguredPrintLanguage() ?: 'EPL'));
    $hostHeader = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
    $isLocalHost = in_array($hostHeader, ['localhost', '127.0.0.1'], true);
    // En la VPS en la nube (ej. 149.50.140.21), el servidor Linux remoto no puede hacer cURL local
    // a una impresora USB física conectada a la PC del usuario.
    $isPrinterEnabled = $activePrinter->isEnabled();

    $trzPdo = $trzPdo instanceof PDO ? $trzPdo : (method_exists($service, 'getPdo') ? $service->getPdo() : Db::trzPdo());
    $service->ensureTraceabilityWarehouseEntriesTable();

    // Asegurar que no existan restricciones foráneas obsoletas que bloqueen los IDs de OC/Proveedor del ERP
    foreach (['fk_rolls_po_id', 'fk_rolls_pol_id', 'fk_rolls_supplier_id'] as $fkName) {
        try {
            @$trzPdo->exec("ALTER TABLE rolls DROP FOREIGN KEY `{$fkName}`");
        } catch (Throwable) {}
    }

    // Consultar bobinas ya registradas con trazabilidad para este contenedor
    $stmtExisting = $trzPdo->prepare("
        SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name, w.code AS warehouse_code
        FROM rolls r
        LEFT JOIN skus s ON s.id = r.sku_id
        LEFT JOIN warehouses w ON w.id = r.warehouse_id
        WHERE r.import_container_id = :cid
        ORDER BY r.id ASC
    ");
    $stmtExisting->execute([':cid' => $containerId]);
    $existingRolls = $stmtExisting->fetchAll(PDO::FETCH_ASSOC);

    $existingRollsByLine = [];
    foreach ($existingRolls as $er) {
        $lItemId = (int)($er['import_container_item_id'] ?? 0);
        $existingRollsByLine[$lItemId][] = $er;
    }

    // =========================================================================
    // VISTA: IMPRESIÓN POR LOTE / NAVEGADOR DE ETIQUETAS (FLEXO 100×70 MM)
    // =========================================================================
    $isBatchPrintView = str_ends_with($path, '/print-labels') || (isset($_GET['print_batch']) && (string)$_GET['print_batch'] === '1');
    if ($isBatchPrintView) {
        $filterRollIds = [];
        if (!empty($_GET['roll_ids'])) {
            $parts = explode(',', (string)$_GET['roll_ids']);
            foreach ($parts as $p) {
                $pid = (int)trim($p);
                if ($pid > 0) $filterRollIds[] = $pid;
            }
        }
        $filterLineId = isset($_GET['line_id']) ? (int)$_GET['line_id'] : 0;
        $printAll = isset($_GET['all']) && (string)$_GET['all'] === '1';

        $rollsToPrint = [];
        foreach ($existingRolls as $er) {
            $rId = (int)$er['id'];
            $lId = (int)($er['import_container_item_id'] ?? 0);
            if (!empty($filterRollIds)) {
                if (in_array($rId, $filterRollIds, true)) {
                    $rollsToPrint[] = $er;
                }
            } elseif ($filterLineId > 0) {
                if ($lId === $filterLineId) {
                    $rollsToPrint[] = $er;
                }
            } else {
                $rollsToPrint[] = $er;
            }
        }

        if (empty($rollsToPrint) && !empty($filterRollIds)) {
            $inR = implode(',', array_fill(0, count($filterRollIds), '?'));
            $stmtR = $trzPdo->prepare("
                SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name, w.code AS warehouse_code
                FROM rolls r
                LEFT JOIN skus s ON s.id = r.sku_id
                LEFT JOIN warehouses w ON w.id = r.warehouse_id
                WHERE r.id IN ($inR)
                ORDER BY r.id ASC
            ");
            $stmtR->execute($filterRollIds);
            $rollsToPrint = $stmtR->fetchAll(PDO::FETCH_ASSOC);
        }

        $linesById = [];
        foreach ($allLines as $aln) {
            $linesById[(int)$aln['import_container_item_id']] = $aln;
        }

        $autoPrint = isset($_GET['auto_print']) && (string)$_GET['auto_print'] === '1';
        $totalLabels = count($rollsToPrint);
        $rawDate = trim((string)($container['eta_plant'] ?? $container['created_at'] ?? date('Y-m-d')));
        $arrivalDate = formatLabelDate($rawDate);

        echo '<!doctype html><html lang="es"><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Imprimir Etiquetas de Trazabilidad 100x70mm - ' . h($cTitle) . '</title>';
        echo '<style id="page_rule">
          @page {
            size: 100mm 70mm;
            margin: 0 !important;
          }
        </style>';
        echo '<style>
          * { box-sizing: border-box; }
          html, body {
            margin: 0;
            padding: 0;
            background: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            color: #000;
            -webkit-font-smoothing: antialiased;
          }
          .no-print-toolbar {
            background: #0f172a;
            color: #fff;
            padding: 12px 24px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 15px rgba(0,0,0,0.25);
          }
          .btn-print {
            background: #00A9A6;
            color: #fff;
            font-weight: 900;
            font-size: 14px;
            border: none;
            border-radius: 8px;
            padding: 9px 22px;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,169,166,0.4);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.15s ease;
          }
          .btn-print:hover { background: #008f8c; }
          .btn-back {
            background: #334155;
            color: #fff;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
          }
          .btn-back:hover { background: #475569; }
          .labels-container {
            padding: 24px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
          }
          .label-card {
            background: #fff;
            border: 1.5pt solid #000;
            border-radius: 3px;
            width: 100mm;
            height: 70mm;
            max-width: 100mm;
            max-height: 70mm;
            padding: 1.8mm 2.5mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
          }
          .header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1.5pt solid #000;
            padding-bottom: 0.6mm;
          }
          .brand-title {
            font-size: 11pt;
            font-weight: 900;
            letter-spacing: 0.5px;
            line-height: 1;
          }
          .sub-title {
            font-size: 5pt;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin-top: 1px;
            color: #333;
          }
          .header-meta {
            text-align: right;
            display: flex;
            align-items: center;
            gap: 2mm;
          }
          .wh-badge {
            background: #000;
            color: #fff;
            font-size: 6.5pt;
            font-weight: 900;
            padding: 0.5mm 1.5mm;
            border-radius: 2px;
          }
          .date-str {
            font-size: 6pt;
            font-weight: 800;
          }
          .sku-box {
            border-bottom: 1pt solid #000;
            padding: 0.6mm 0;
          }
          .sku-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
          }
          .sku-label { font-size: 6.5pt; font-weight: 800; color: #444; }
          .sku-val { font-size: 11pt; font-weight: 900; letter-spacing: 0.3px; }
          .stage-tag {
            font-size: 5.5pt;
            font-weight: 900;
            background: #eee;
            border: 0.6pt solid #000;
            padding: 0.3mm 1mm;
            border-radius: 2px;
          }
          .sku-desc {
            font-size: 6pt;
            font-weight: 700;
            line-height: 1.15;
            margin-top: 0.4mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
          }
          .grid-specs {
            display: grid;
            grid-template-columns: 1.3fr 1fr 1fr 1fr;
            border-bottom: 1pt solid #000;
            padding: 0.6mm 0;
            margin-top: 0.2mm;
          }
          .spec-col { border-right: 0.8pt solid #bbb; padding-right: 1mm; }
          .spec-col:last-child { border-right: none; padding-right: 0; }
          .spec-k { font-size: 5.5pt; font-weight: 800; text-transform: uppercase; color: #222; line-height: 1; }
          .spec-v { font-size: 8pt; font-weight: 900; line-height: 1.1; margin-top: 1px; }
          .spec-v-lg { font-size: 10.5pt; font-weight: 900; }
          .trace-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 0.8pt solid #000;
            padding: 0.6mm 0;
            font-size: 6.5pt;
            font-weight: 700;
            line-height: 1.1;
          }
          .trace-item { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
          .barcode-section {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding-top: 0.4mm;
          }
          .barcode-svg-wrap {
            width: 95%;
            max-width: 90mm;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
          }
          .code-text {
            font-family: "Consolas", monospace;
            font-size: 8pt;
            font-weight: 900;
            letter-spacing: 1px;
            margin-top: 0.5mm;
          }
          .footer-text {
            font-size: 5pt;
            font-weight: 700;
            color: #444;
            letter-spacing: 0.3px;
            margin-top: 0.2mm;
          }

          @media print {
            body, html {
              margin: 0 !important;
              padding: 0 !important;
              background: #fff !important;
              -webkit-print-color-adjust: exact !important;
              print-color-adjust: exact !important;
            }
            .no-print, .no-print-toolbar {
              display: none !important;
            }
            .labels-container {
              padding: 0 !important;
              margin: 0 !important;
              display: block !important;
            }
            .label-card {
              width: 100mm !important;
              height: 70mm !important;
              max-width: 100mm !important;
              max-height: 70mm !important;
              margin: 0 !important;
              box-shadow: none !important;
              border: 1.5pt solid #000 !important;
              page-break-after: always !important;
              break-after: page !important;
              page-break-inside: avoid !important;
              break-inside: avoid !important;
            }
            .label-card:last-child {
              page-break-after: avoid !important;
              break-after: avoid !important;
            }
          }
        </style></head><body>';

        echo '<div class="no-print-toolbar">';
        echo '<div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">';
        echo '<a class="btn-back" href="/import-containers/' . $containerId . '/no-weight-labels">⬅️ Volver al Contenedor</a>';
        echo '<div>';
        echo '<div style="font-weight:900;font-size:15px;color:#fff">🖨️ Impresión de Etiquetas Térmicas (' . $totalLabels . ' bobina' . ($totalLabels === 1 ? '' : 's') . ')</div>';
        echo '<div style="font-size:12px;color:#94a3b8">Formato Flexo 100 × 70 mm · ' . h($cTitle) . '</div>';
        echo '</div>';
        echo '</div>';
        echo '<div style="display:flex;align-items:center;gap:10px">';
        echo '<button type="button" class="btn-print" onclick="window.print()">🖨️ Imprimir Todas (' . $totalLabels . ')</button>';
        echo '</div>';
        echo '</div>';

        if (empty($rollsToPrint)) {
            echo '<div style="text-align:center;padding:60px 20px;color:#64748b">';
            echo '<div style="font-size:36px;margin-bottom:8px">⚠️</div>';
            echo '<div style="font-weight:800;font-size:16px;color:#0f172a">No se encontraron bobinas registradas para imprimir</div>';
            echo '<div style="margin-top:12px"><a class="btn-back" href="/import-containers/' . $containerId . '/no-weight-labels">Volver a Generar Etiquetas</a></div>';
            echo '</div>';
        } else {
            echo '<div class="labels-container">';
            foreach ($rollsToPrint as $roll) {
                $whCode = trim((string)($roll['warehouse_code'] ?? '')) ?: '-';
                $skuCode = trim((string)($roll['sku_code'] ?? '-'));
                $skuDesc = trim((string)($roll['sku_desc'] ?? $roll['sku_description'] ?? '-'));
                $lData = $linesById[(int)($roll['import_container_item_id'] ?? 0)] ?? [];
                $poCode = trim((string)($lData['po_code'] ?? '-'));
                $suppName = trim((string)($lData['supplier_name'] ?? '-'));
                $metersVal = (float)($roll['meters'] ?? $lData['meters'] ?? 0);
                $metersStr = $metersVal > 0 ? (number_format($metersVal, 0, ',', '.') . ' m') : '-';
                $widthVal = (float)($roll['width_mm'] ?? $lData['width_mm'] ?? 0);
                $gramsVal = (float)($roll['microns'] ?? $lData['grams'] ?? $lData['microns'] ?? 0);
                $specsStr = '';
                if ($widthVal > 0) $specsStr .= round($widthVal) . ' mm';
                if ($gramsVal > 0) $specsStr .= ($specsStr !== '' ? ' · ' : '') . round($gramsVal) . ' g';
                if ($specsStr === '') $specsStr = '-';
                $colorVal = trim((string)($roll['color'] ?? $lData['color'] ?? '-')) ?: '-';
                $rollCode = (string)$roll['roll_code'];
                $barcodeSvg = code128Svg($rollCode);

                echo '<div class="label-card">';
                // 1. Cabecera
                echo '<div class="header-row">';
                echo '<div><div class="brand-title">UNIBAG</div><div class="sub-title">RECEPCIÓN MATERIA PRIMA</div></div>';
                echo '<div class="header-meta"><div class="wh-badge">BOD: ' . h($whCode) . '</div><div class="date-str">FECHA: ' . h($arrivalDate) . '</div></div>';
                echo '</div>';

                // 2. SKU y Descripción
                echo '<div class="sku-box">';
                echo '<div class="sku-header"><div><span class="sku-label">SKU: </span><span class="sku-val">' . h($skuCode) . '</span></div><div class="stage-tag">MATERIA PRIMA</div></div>';
                echo '<div class="sku-desc" title="' . h($skuDesc) . '">' . h($skuDesc) . '</div>';
                echo '</div>';

                // 3. Cuadrícula (PESO REAL: -)
                echo '<div class="grid-specs">';
                echo '<div class="spec-col"><div class="spec-k">Peso Real</div><div class="spec-v spec-v-lg">-</div></div>';
                echo '<div class="spec-col"><div class="spec-k">Metros</div><div class="spec-v">' . h($metersStr) . '</div></div>';
                echo '<div class="spec-col"><div class="spec-k">Ancho / Gr</div><div class="spec-v">' . h($specsStr) . '</div></div>';
                echo '<div class="spec-col"><div class="spec-k">Color</div><div class="spec-v">' . h($colorVal) . '</div></div>';
                echo '</div>';

                // 4. Trazabilidad
                echo '<div class="trace-row">';
                echo '<div class="trace-item"><span>OC: </span><b>' . h($poCode) . '</b></div>';
                echo '<div class="trace-item"><span>CONT: </span><b>' . h($cCode ?: ('#' . $containerId)) . '</b></div>';
                echo '<div class="trace-item" style="max-width:40%"><span>PROV: </span><b>' . h($suppName) . '</b></div>';
                echo '</div>';

                // 5. Código de barras con código corto
                echo '<div class="barcode-section">';
                if ($barcodeSvg !== '') {
                    echo '<div class="barcode-svg-wrap">' . $barcodeSvg . '</div>';
                }
                echo '<div class="code-text">' . h($rollCode) . '</div>';
                echo '<div class="footer-text">CONTENEDOR ' . h($cCode ?: ('#' . $containerId)) . ' · CONTROL DE CALIDAD UNIBAG</div>';
                echo '</div>';

                echo '</div>'; // .label-card
            }
            echo '</div>'; // .labels-container
        }

        if ($autoPrint && !empty($rollsToPrint)) {
            echo '<script>
              window.addEventListener("load", function() {
                setTimeout(function() {
                  try { window.focus(); } catch (e) {}
                  try { window.print(); } catch (e) {}
                }, 400);
              });
            </script>';
        }
        echo '</body></html>';
        exit;
    }

    $action = (string)($_POST['action'] ?? $_GET['action'] ?? '');

    // =========================================================================
    // ACCIÓN: GENERAR CÓDIGOS DE TRAZABILIDAD CORTOS E IMPRIMIR EN TÉRMICA
    // (SIN TOCAR STOCK DE BODEGA EN ERP NI RE-RECEPCIONAR CONTENEDOR)
    // =========================================================================
    if ($action === 'generate_and_print' || $action === 'print_labels') {
        if ($method === 'POST') {
            requireCsrf();
        }
        try {
            @ini_set('max_execution_time', '180');
            if (function_exists('set_time_limit')) {
                @set_time_limit(180);
            }

            $targetLineId = (int)($_REQUEST['target_line_id'] ?? 0);
            // Máximo seguro por petición: 100 bobinas por lote para evitar bloqueos
            $reqQty = max(1, min(100, (int)($_REQUEST['qty'] ?? 1)));
            $whId = (int)($_POST['warehouse_id'] ?? 0);
            $selectedWarehouse = null;
            foreach ($labelWarehouses as $labelWarehouse) {
                if ((int)$labelWarehouse['id'] === $whId) {
                    $selectedWarehouse = $labelWarehouse;
                    break;
                }
            }
            if ($selectedWarehouse === null) {
                throw new RuntimeException('Selecciona la bodega para los nuevos codigos de trazabilidad.');
            }
            $whCode = (string)$selectedWarehouse['code'];
            $userPrefix = strtoupper(trim((string)($_REQUEST['code_prefix'] ?? '')));
            if ($userPrefix === '') {
                $userPrefix = 'RB-' . $containerId . '-';
            } elseif (!str_ends_with($userPrefix, '-')) {
                $userPrefix .= '-';
            }

            $linesToProcess = [];
            if ($targetLineId > 0) {
                foreach ($allLines as $l) {
                    if ((int)$l['import_container_item_id'] === $targetLineId) {
                        $linesToProcess[] = $l;
                        break;
                    }
                }
            } else {
                $linesToProcess = $allLines;
            }

            // Calcular siguiente número correlativo para este contenedor y prefijo
            $stmtMax = $trzPdo->prepare("SELECT roll_code FROM rolls WHERE roll_code LIKE :pref ORDER BY id DESC LIMIT 100");
            $stmtMax->execute([':pref' => $userPrefix . '%']);
            $existingCodes = $stmtMax->fetchAll(PDO::FETCH_COLUMN);
            $maxSeq = 0;
            foreach ($existingCodes as $ec) {
                if (preg_match('/' . preg_quote($userPrefix, '/') . '(\d+)$/', (string)$ec, $mSeq)) {
                    $n = (int)$mSeq[1];
                    if ($n > $maxSeq) $maxSeq = $n;
                }
            }

            $createdRolls = [];
            $createdRollIds = [];
            $printErrors = [];
            $printedCount = 0;
            $consecutivePrintErrors = 0;
            $remainingGlobalBatch = ($targetLineId > 0) ? $reqQty : min(50, $reqQty);

            foreach ($linesToProcess as $ln) {
                $lineItemId = (int)($ln['import_container_item_id'] ?? 0);
                $alreadyCount = isset($existingRollsByLine[$lineItemId]) ? count($existingRollsByLine[$lineItemId]) : 0;
                $totalOrdered = max(1, (int)($ln['received_rolls'] ?? $ln['ordered_rolls'] ?? 1));

                // Si es por línea específica se usa reqQty; si es global se limita a un lote seguro
                if ($targetLineId > 0) {
                    $lineCopies = $reqQty;
                } else {
                    $pendingLine = max(0, $totalOrdered - $alreadyCount);
                    if ($pendingLine <= 0) {
                        continue;
                    }
                    $lineCopies = min($remainingGlobalBatch, min(20, $pendingLine));
                    $remainingGlobalBatch -= $lineCopies;
                }

                if ($lineCopies <= 0) {
                    continue;
                }

                $skuCode = trim((string)($ln['sku_code'] ?? '-'));
                $skuDesc = trim((string)($ln['sku_description'] ?? '-'));
                $poCode = trim((string)($ln['po_code'] ?? '-'));
                $suppName = trim((string)($ln['supplier_name'] ?? '-'));
                $metersVal = (float)($ln['meters'] ?? 0);
                $widthVal = (float)($ln['width_mm'] ?? 0);
                $gramsVal = (float)($ln['grams'] ?? $ln['microns'] ?? 0);
                $colorVal = trim((string)($ln['color'] ?? '')) ?: '-';
                $rawDate = trim((string)($container['eta_plant'] ?? $container['created_at'] ?? date('Y-m-d')));

                // Resolver o registrar sku_id en tabla skus de TRZ
                $skuId = 1;
                if ($skuCode !== '' && $skuCode !== '-') {
                    $stmtSku = $trzPdo->prepare('SELECT id FROM skus WHERE code = :c LIMIT 1');
                    $stmtSku->execute([':c' => $skuCode]);
                    $foundSkuId = (int)$stmtSku->fetchColumn();
                    if ($foundSkuId > 0) {
                        $skuId = $foundSkuId;
                    } else {
                        $stmtInsSku = $trzPdo->prepare('INSERT INTO skus (code, description, is_active, created_at) VALUES (:c, :d, 1, NOW())');
                        $stmtInsSku->execute([':c' => $skuCode, ':d' => $skuDesc !== '' ? $skuDesc : $skuCode]);
                        $skuId = (int)$trzPdo->lastInsertId();
                    }
                }

                for ($i = 1; $i <= $lineCopies; $i++) {
                    $maxSeq++;
                    // Código de trazabilidad corto y limpio: ej. RB-281-001
                    $rollCode = $userPrefix . str_pad((string)$maxSeq, 3, '0', STR_PAD_LEFT);

                    // 1. Insertar en tabla rolls de trazabilidad (status = RECEIVED, sin tocar inventario ERP)
                    $stmtRoll = $trzPdo->prepare('
                        INSERT INTO rolls (
                            roll_code, sku_id, warehouse_id, weight_kg, received_qty,
                            reception_mode, microns, width_mm, color, meters, status,
                            purchase_order_id, purchase_order_line_id, import_container_id, import_container_item_id,
                            supplier_id, process_stage, created_at
                        ) VALUES (
                            :code, :skuid, :whid, 0.0, 1,
                            "WAREHOUSE_STOCK", :microns, :width_mm, :color, :meters, "RECEIVED",
                            :po_id, :po_line_id, :container_id, :container_item_id,
                            :supplier_id, "RAW", NOW()
                        )
                    ');
                    $targetPoId = !empty($ln['purchase_order_id']) ? (int)$ln['purchase_order_id'] : null;
                    $targetPoLineId = !empty($ln['id']) ? (int)$ln['id'] : null;
                    $targetSupplierId = !empty($ln['supplier_id']) ? (int)$ln['supplier_id'] : null;

                    try {
                        $stmtRoll->execute([
                            ':code' => $rollCode,
                            ':skuid' => $skuId,
                            ':whid' => $whId,
                            ':microns' => $gramsVal > 0 ? (int)round($gramsVal) : null,
                            ':width_mm' => $widthVal > 0 ? (int)round($widthVal) : null,
                            ':color' => $colorVal !== '-' ? $colorVal : null,
                            ':meters' => $metersVal > 0 ? $metersVal : null,
                            ':po_id' => $targetPoId,
                            ':po_line_id' => $targetPoLineId,
                            ':container_id' => $containerId,
                            ':container_item_id' => $lineItemId,
                            ':supplier_id' => $targetSupplierId,
                        ]);
                    } catch (PDOException $eRoll) {
                        // Si falla por restricción de clave foránea residual (ej. fk_rolls_po_id, 1452), reintentar sin po_id/supplier_id
                        if ((int)$eRoll->getCode() === 23000 || str_contains($eRoll->getMessage(), '1452') || str_contains($eRoll->getMessage(), 'fk_rolls_po_id')) {
                            $stmtRollFallback = $trzPdo->prepare('
                                INSERT INTO rolls (
                                    roll_code, sku_id, warehouse_id, weight_kg, received_qty,
                                    reception_mode, microns, width_mm, color, meters, status,
                                    purchase_order_id, purchase_order_line_id, import_container_id, import_container_item_id,
                                    supplier_id, process_stage, created_at
                                ) VALUES (
                                    :code, :skuid, :whid, 0.0, 1,
                                    "WAREHOUSE_STOCK", :microns, :width_mm, :color, :meters, "RECEIVED",
                                    NULL, NULL, :container_id, :container_item_id,
                                    NULL, "RAW", NOW()
                                )
                            ');
                            $stmtRollFallback->execute([
                                ':code' => $rollCode,
                                ':skuid' => $skuId,
                                ':whid' => $whId,
                                ':microns' => $gramsVal > 0 ? (int)round($gramsVal) : null,
                                ':width_mm' => $widthVal > 0 ? (int)round($widthVal) : null,
                                ':color' => $colorVal !== '-' ? $colorVal : null,
                                ':meters' => $metersVal > 0 ? $metersVal : null,
                                ':container_id' => $containerId,
                                ':container_item_id' => $lineItemId,
                            ]);
                        } else {
                            throw $eRoll;
                        }
                    }
                    $rollId = (int)$trzPdo->lastInsertId();

                    // 2. Registrar movimiento en movements
                    $stmtMov = $trzPdo->prepare('
                        INSERT INTO movements (entity_type, entity_id, movement_type, to_warehouse_id, payload, created_at)
                        VALUES ("ROLL", :eid, "RECEIPT", :towh, :payload, NOW())
                    ');
                    $movPayload = json_encode([
                        'traceability_code' => $rollCode,
                        'entity_type' => 'ROLL',
                        'entity_id' => $rollId,
                        'item_code' => $skuCode,
                        'item_description' => $skuDesc,
                        'quantity' => 1.0,
                        'unit' => 'BOB',
                        'warehouse_code' => $whCode,
                        'container_code' => $cCode,
                        'container_id' => $containerId,
                        'package_number' => $maxSeq,
                        'total_packages' => $lineCopies,
                        'operator' => 'Javier Perozo',
                        'comments' => 'Trazabilidad de bobina asignada en bodega para contenedor ' . ($cCode ?: ('#' . $containerId)) . ' (sin duplicar stock ERP)',
                    ], JSON_UNESCAPED_UNICODE);
                    $stmtMov->execute([
                        ':eid' => $rollId,
                        ':towh' => $whId,
                        ':payload' => $movPayload,
                    ]);

                    // 3. Registrar entrada en traceability_warehouse_entries (tolerante a fallos)
                    try {
                        $stmtEntry = $trzPdo->prepare('
                            INSERT INTO traceability_warehouse_entries (
                                traceability_code, entity_type, entity_id, warehouse_id, warehouse_name,
                                item_code, item_description, quantity, unit_name, package_number, total_packages,
                                weight_kg, width_mm, microns, meters, order_ref, operator_name, comments, status, created_at
                            ) VALUES (
                                :code, "ROLL", :eid, :whid, :whname,
                                :sku, :desc, 1.0, "BOB", :pkgnum, :totpkg,
                                NULL, :wmm, :mic, :met, :ord, :op, :comm, "IN_WAREHOUSE", NOW()
                            )
                        ');
                        $stmtEntry->execute([
                            ':code' => $rollCode,
                            ':eid' => $rollId,
                            ':whid' => $whId,
                            ':whname' => (string)$selectedWarehouse['name'],
                            ':sku' => $skuCode,
                            ':desc' => $skuDesc,
                            ':pkgnum' => $maxSeq,
                            ':totpkg' => $lineCopies,
                            ':wmm' => $widthVal > 0 ? (int)round($widthVal) : null,
                            ':mic' => $gramsVal > 0 ? (int)round($gramsVal) : null,
                            ':met' => $metersVal > 0 ? $metersVal : null,
                            ':ord' => $poCode !== '-' ? ('OC: ' . $poCode) : ('CONT: ' . ($cCode ?: ('#' . $containerId))),
                            ':op' => 'Javier Perozo',
                            ':comm' => 'Contenedor ' . ($cCode ?: ('#' . $containerId)) . ' - Trazabilidad de bobina en bodega',
                        ]);
                    } catch (Throwable $eEntry) {
                        error_log('traceability_warehouse_entries insert notice: ' . $eEntry->getMessage());
                    }

                    // 4. Imprimir etiqueta térmica en formato Flexo 100x70 con el código de trazabilidad corto
                    $mockRoll = [
                        'id' => $rollId,
                        'roll_code' => $rollCode,
                        'sku_code' => $skuCode,
                        'sku_description' => $skuDesc,
                        'warehouse_code' => $whCode,
                        'weight_kg' => 0.0,
                        'no_weight' => true,
                        'weight_str' => '-',
                        'meters' => $metersVal,
                        'width_mm' => $widthVal,
                        'grams' => $gramsVal,
                        'microns' => $gramsVal,
                        'color' => $colorVal,
                        'po_code' => $poCode,
                        'container_code' => $cCode ?: ('#' . $containerId),
                        'supplier_name' => $suppName,
                        'arrival_date' => $rawDate,
                        'process_stage' => 'RAW',
                    ];

                    if ($isPrinterEnabled && $consecutivePrintErrors < 3) {
                        try {
                            $pRes = $activePrinter->printRollLabel($mockRoll);
                            if (($pRes['ok'] ?? false) === true || !empty($pRes['client_queued'])) {
                                $printedCount++;
                            } else {
                                $consecutivePrintErrors++;
                                $printErrors[] = $pRes['error'] ?? 'Error desconocido';
                            }
                        } catch (Throwable $pe) {
                            $consecutivePrintErrors++;
                            $printErrors[] = 'Impresora: ' . $pe->getMessage();
                        }
                    }

                    $createdRolls[] = $rollCode;
                    $createdRollIds[] = $rollId;
                }

                if ($targetLineId <= 0 && $remainingGlobalBatch <= 0) {
                    break;
                }
            }

            // Si la impresora térmica directa en servidor no está habilitada o no imprimió (ej. VPS remota),
            // abrir directamente la pantalla de lote de impresión del navegador para el lote recién creado
            if ($printedCount === 0 && !empty($createdRollIds)) {
                header('Location: /import-containers/' . $containerId . '/print-labels?roll_ids=' . implode(',', $createdRollIds) . '&auto_print=1');
                exit;
            }

            $redParams = ['created' => count($createdRolls), 'printed' => $printedCount];
            if (!empty($createdRolls)) {
                $redParams['code_range'] = $createdRolls[0] . (count($createdRolls) > 1 ? (' al ' . end($createdRolls)) : '');
                $redParams['new_roll_ids'] = implode(',', $createdRollIds);
            }
            if ($targetLineId > 0) {
                $redParams['line'] = $targetLineId;
            }
            if (!empty($printErrors)) {
                $redParams['print_err'] = implode('; ', array_slice(array_unique($printErrors), 0, 3));
            }
            header('Location: /import-containers/' . $containerId . '/no-weight-labels?' . http_build_query($redParams));
            exit;
        } catch (Throwable $e) {
            error_log('Error en generate_and_print no-weight-labels: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            header('Location: /import-containers/' . $containerId . '/no-weight-labels?print_err=' . urlencode('Error al generar: ' . $e->getMessage()));
            exit;
        }
    }

    // =========================================================================
    // ACCIÓN: OBTENER COMANDO NATIVO EPL/ZPL DE BOBINA PARA IMPRESIÓN CLIENTE
    // =========================================================================
    if ($action === 'raw_roll_command') {
        $rollId = (int)($_REQUEST['roll_id'] ?? 0);
        $roll = $service->getRoll($rollId);
        if ($roll === null) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Bobina no encontrada']);
            exit;
        }
        $roll['no_weight'] = true;
        $roll['weight_str'] = '-';
        $cmd = $activePrinter->buildRollCommand($roll);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'roll_id' => $rollId,
            'roll_code' => $roll['roll_code'],
            'printer' => $configuredPrinterName,
            'language' => strtolower($activePrinter->getConfiguredPrintLanguage()),
            'command' => $cmd
        ]);
        exit;
    }

    // =========================================================================
    // ACCIÓN: REIMPRIMIR ETIQUETA DE BOBINA YA EXISTENTE
    // =========================================================================
    if ($action === 'reprint_roll') {
        // Imprimir es una acción con efecto: solo se acepta por POST y con token CSRF.
        if ($method !== 'POST') {
            http_response_code(405);
            header('Location: /import-containers/' . $containerId . '/no-weight-labels');
            exit;
        }
        requireCsrf();
        $rollId = (int)($_REQUEST['roll_id'] ?? 0);
        $roll = $service->getRoll($rollId);
        if ($roll !== null) {
            $roll['no_weight'] = true;
            $roll['weight_str'] = '-';
            if ($isPrinterEnabled) {
                $pRes = $activePrinter->printRollLabel($roll);
                if (($pRes['ok'] ?? false) === true || !empty($pRes['client_queued'])) {
                    header('Location: /import-containers/' . $containerId . '/no-weight-labels?msg=' . urlencode('Etiqueta ' . $roll['roll_code'] . ' reimpresa exitosamente.'));
                    exit;
                }
                if (!empty($pRes['ambiguous'])) {
                    // Estado incierto: no se reintenta por el navegador (duplicaría la etiqueta).
                    header('Location: /import-containers/' . $containerId . '/no-weight-labels?msg=' . urlencode((string)($pRes['error'] ?? 'Estado de impresión incierto.')));
                    exit;
                }
            }
            // En VPS o si falló la impresora directa, abrir etiqueta en navegador con auto-print
            header('Location: /rolls/' . $rollId . '/label?no_weight=1&auto_print=1');
            exit;
        }
        header('Location: /import-containers/' . $containerId . '/no-weight-labels');
        exit;
    }

    // =========================================================================
    // ACCIÓN: REIMPRIMIR TODAS LAS BOBINAS REGISTRADAS DEL CONTENEDOR
    // =========================================================================
    if ($action === 'reprint_all') {
        if ($method === 'POST') {
            requireCsrf();
        }
        $targetLineId = (int)($_REQUEST['target_line_id'] ?? 0);
        if (!$isPrinterEnabled) {
            // En VPS o sin impresora directa en servidor: redirigir directamente al lote de impresión del navegador
            $redUrl = '/import-containers/' . $containerId . '/print-labels?' . ($targetLineId > 0 ? ('line_id=' . $targetLineId) : 'all=1') . '&auto_print=1';
            header('Location: ' . $redUrl);
            exit;
        }
        try {
            @ini_set('max_execution_time', '180');
            if (function_exists('set_time_limit')) {
                @set_time_limit(180);
            }
            $reprintList = [];
            foreach ($existingRolls as $er) {
                if ($targetLineId > 0 && (int)($er['import_container_item_id'] ?? 0) !== $targetLineId) {
                    continue;
                }
                $reprintList[] = $er;
            }

            $reprintedCount = 0;
            $printErrors = [];
            $consecutivePrintErrors = 0;
            foreach ($reprintList as $er) {
                if ($isPrinterEnabled && $consecutivePrintErrors < 3) {
                    $roll = $service->getRoll((int)$er['id']);
                    if ($roll !== null) {
                        $roll['no_weight'] = true;
                        $roll['weight_str'] = '-';
                        try {
                            $pRes = $activePrinter->printRollLabel($roll);
                            if (($pRes['ok'] ?? false) === true || !empty($pRes['client_queued'])) {
                                $reprintedCount++;
                            } else {
                                $consecutivePrintErrors++;
                                $printErrors[] = $pRes['error'] ?? 'Error';
                            }
                        } catch (Throwable $pe) {
                            $consecutivePrintErrors++;
                            $printErrors[] = 'Impresora: ' . $pe->getMessage();
                        }
                    }
                }
            }

            $redParams = ['msg' => 'Se reimprimieron ' . $reprintedCount . ' etiqueta(s) térmica(s).'];
            if (!empty($printErrors)) {
                $redParams['print_err'] = implode('; ', array_slice(array_unique($printErrors), 0, 3));
            }
            header('Location: /import-containers/' . $containerId . '/no-weight-labels?' . http_build_query($redParams));
            exit;
        } catch (Throwable $e) {
            header('Location: /import-containers/' . $containerId . '/no-weight-labels?print_err=' . urlencode('Error al reimprimir: ' . $e->getMessage()));
            exit;
        }
    }

    // =========================================================================
    // ACCIÓN: ELIMINAR / LIBERAR TRAZABILIDAD DE UNA BOBINA ESPECÍFICA
    // =========================================================================
    if ($action === 'delete_roll') {
        if ($method === 'POST') {
            requireCsrf();
        }
        try {
            $rollId = (int)($_REQUEST['roll_id'] ?? 0);
            $delRollCode = trim((string)($_REQUEST['roll_code'] ?? ''));
            if ($rollId > 0 || $delRollCode !== '') {
                $stmtDelRoll = $trzPdo->prepare('DELETE FROM rolls WHERE id = :id OR roll_code = :c');
                $stmtDelRoll->execute([':id' => $rollId, ':c' => $delRollCode]);
                try {
                    $stmtDelMov = $trzPdo->prepare('DELETE FROM movements WHERE entity_type = "ROLL" AND (entity_id = :id OR payload LIKE :c)');
                    $stmtDelMov->execute([':id' => $rollId, ':c' => '%' . $delRollCode . '%']);
                } catch (Throwable) {}
                try {
                    $stmtDelTrz = $trzPdo->prepare('DELETE FROM traceability_warehouse_entries WHERE entity_type = "ROLL" AND (entity_id = :id OR traceability_code = :c)');
                    $stmtDelTrz->execute([':id' => $rollId, ':c' => $delRollCode]);
                } catch (Throwable) {}
                header('Location: /import-containers/' . $containerId . '/no-weight-labels?msg=' . urlencode('Bobina ' . $delRollCode . ' eliminada de trazabilidad correctamente.'));
                exit;
            }
            header('Location: /import-containers/' . $containerId . '/no-weight-labels');
            exit;
        } catch (Throwable $e) {
            header('Location: /import-containers/' . $containerId . '/no-weight-labels?print_err=' . urlencode('Error al eliminar: ' . $e->getMessage()));
            exit;
        }
    }

    // =========================================================================
    // ACCIÓN: VER PANTALLA COMPLETA DE ETIQUETA EN FORMATO FLEXO (100x70 MM)
    // =========================================================================
    $previewLineId = isset($_GET['preview_line_id']) ? (int)$_GET['preview_line_id'] : ($filterLineId > 0 ? $filterLineId : (!empty($allLines) ? (int)$allLines[0]['import_container_item_id'] : 0));
    if ($previewLineId > 0 && isset($_GET['full_preview']) && (string)$_GET['full_preview'] === '1') {
        $targetLn = null;
        foreach ($allLines as $ln) {
            if ((int)$ln['import_container_item_id'] === $previewLineId) {
                $targetLn = $ln;
                break;
            }
        }
        if ($targetLn !== null) {
            $skuCode = trim((string)($targetLn['sku_code'] ?? '-'));
            $skuDesc = trim((string)($targetLn['sku_description'] ?? '-'));
            $poCode = trim((string)($targetLn['po_code'] ?? '-'));
            $suppName = trim((string)($targetLn['supplier_name'] ?? '-'));
            $metersVal = (float)($targetLn['meters'] ?? 0);
            $metersStr = $metersVal > 0 ? (number_format($metersVal, 0, ',', '.') . ' m') : '-';
            $widthVal = (float)($targetLn['width_mm'] ?? 0);
            $gramsVal = (float)($targetLn['grams'] ?? $targetLn['microns'] ?? 0);
            $specsStr = '';
            if ($widthVal > 0) $specsStr .= round($widthVal) . ' mm';
            if ($gramsVal > 0) $specsStr .= ($specsStr !== '' ? ' · ' : '') . round($gramsVal) . ' g';
            if ($specsStr === '') $specsStr = '-';
            $colorVal = trim((string)($targetLn['color'] ?? '')) ?: '-';
            $rawDate = trim((string)($container['eta_plant'] ?? $container['created_at'] ?? date('Y-m-d')));
            $arrivalDate = formatLabelDate($rawDate);
            $previewRoll = $existingRollsByLine[$previewLineId][0] ?? [];
            $whCode = trim((string)($previewRoll['warehouse_code'] ?? '')) ?: 'Por seleccionar';

            // Si hay bobinas registradas para esta línea mostrar el primer código, sino código modelo corto
            $sampleCode = !empty($existingRollsByLine[$previewLineId]) ? (string)$existingRollsByLine[$previewLineId][0]['roll_code'] : ('RB-' . $containerId . '-001');

            renderReceptionThermalLabel([
                'sku_code' => $skuCode,
                'sku_description' => $skuDesc,
                'arrival_date' => $arrivalDate,
                'warehouse_code' => $whCode,
                'warehouse_name' => 'Bodega ' . $whCode,
                'weight_str' => '-',
                'meters_str' => $metersStr,
                'specs_str' => $specsStr,
                'color' => $colorVal,
                'po_code' => $poCode,
                'container_code' => $cCode,
                'supplier_name' => $suppName,
                'barcode_value' => $sampleCode,
                'stage_badge' => 'MATERIA PRIMA',
                'footer_text' => 'CONTENEDOR ' . $cCode . ' · CONTROL DE CALIDAD UNIBAG',
                'is_sample' => false,
                'auto_print' => false,
                'print_language' => strtolower($configuredPrintLang),
                'printer_name' => $configuredPrinterName,
                'server_print_allowed' => $isPrinterEnabled,
                'server_print_url' => '',
                'server_print_result' => null,
                'download_zpl_url' => '',
                'title' => 'Etiqueta Flexo Sin Peso - ' . $sampleCode,
            ]);
            exit;
        }
    }

    // Parámetros y notificaciones de vista
    $createdCount = isset($_GET['created']) ? (int)$_GET['created'] : 0;
    $printedMsg = isset($_GET['printed']) ? (int)$_GET['printed'] : 0;
    $codeRange = trim((string)($_GET['code_range'] ?? ''));
    $genMsg = trim((string)($_GET['msg'] ?? ''));
    $printErr = trim((string)($_GET['print_err'] ?? ''));

    // Seleccionar línea para la vista previa
    $previewLine = null;
    if ($previewLineId > 0) {
        foreach ($allLines as $ln) {
            if ((int)$ln['import_container_item_id'] === $previewLineId) {
                $previewLine = $ln;
                break;
            }
        }
    }
    if ($previewLine === null && !empty($allLines)) {
        $previewLine = $allLines[0];
        $previewLineId = (int)$previewLine['import_container_item_id'];
    }

    $defaultShortPrefix = 'RB-' . $containerId . '-';

    echo '<!doctype html><html lang="es"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Trazabilidad e Impresión de Bobinas Sin Peso (Flexo 100×70) - ' . h($cTitle) . '</title>';
    echo '<style>
      * { box-sizing: border-box; }
      body {
        margin: 0;
        padding: 0;
        background: #f1f5f9;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        color: #0f172a;
        -webkit-font-smoothing: antialiased;
      }
      .toolbar {
        background: #0f172a;
        color: #fff;
        padding: 12px 24px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.25);
        position: sticky;
        top: 0;
        z-index: 50;
      }
      .btn {
        border-radius: 8px;
        padding: 8px 16px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid transparent;
        transition: all 0.15s ease;
      }
      .btn-primary { background: #00A9A6; color: #fff; border-color: #00A9A6; }
      .btn-primary:hover { background: #008f8c; }
      .btn-dark { background: #1e293b; color: #fff; border-color: #334155; }
      .btn-dark:hover { background: #334155; }
      .btn-success { background: #059669; color: #fff; border-color: #059669; }
      .btn-success:hover { background: #047857; }
      .btn-outline { background: #fff; color: #0f172a; border-color: #cbd5e1; }
      .btn-outline:hover { background: #f8fafc; }
      .btn-danger-sm { background: #fff; color: #dc2626; border-color: #fecaca; padding: 4px 8px; font-size: 11px; }
      .btn-danger-sm:hover { background: #fef2f2; border-color: #f87171; }
      .btn-reprint-sm { background: #f0fdf4; color: #166534; border-color: #bbf7d0; padding: 4px 9px; font-size: 11px; }
      .btn-reprint-sm:hover { background: #dcfce7; }
      .container-layout {
        max-width: 100%;
        margin: 20px auto;
        padding: 0 20px 40px 20px;
        display: flex;
        flex-direction: column;
        gap: 20px;
      }
      .alert {
        padding: 14px 20px;
        border-radius: 10px;
        font-size: 13.5px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
      }
      .alert-ok { background: #ecfdf5; border: 1.5px solid #a7f3d0; color: #065f46; }
      .alert-err { background: #fef2f2; border: 1.5px solid #fecaca; color: #991b1b; }
      .alert-info { background: #eff6ff; border: 1.5px solid #bfdbfe; color: #1e40af; }

      /* Tarjeta de Etiqueta Térmica 100x70 mm (Diseño idéntico a Flexo) */
      .thermal-preview-container {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 16px;
        background: #e2e8f0;
        border-radius: 8px;
        border: 1px dashed #94a3b8;
      }
      .label-card {
        background: #fff;
        border: 1.5pt solid #000;
        border-radius: 3px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-sizing: border-box;
        overflow: hidden;
        width: 100mm;
        height: 70mm;
        max-width: 100mm;
        max-height: 70mm;
        padding: 1.8mm 2.5mm;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        color: #000;
      }
      .header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1.2pt solid #000;
        padding-bottom: 0.6mm;
        line-height: 1;
      }
      .brand-title { font-size: 12pt; font-weight: 900; letter-spacing: 0.4px; }
      .sub-title { font-size: 6pt; font-weight: 800; letter-spacing: 0.3px; text-transform: uppercase; margin-top: 1px; }
      .header-meta { text-align: right; line-height: 1.15; }
      .wh-badge { background: #000; color: #fff; font-weight: 900; font-size: 7.5pt; padding: 1px 5px; border-radius: 2px; display: inline-block; letter-spacing: 0.3px; }
      .date-str { font-size: 6.2pt; font-weight: 800; margin-top: 1px; }
      .sku-box { border-bottom: 1pt solid #000; padding-bottom: 0.6mm; margin-top: 0.4mm; }
      .sku-header { display: flex; justify-content: space-between; align-items: baseline; }
      .sku-label { font-size: 7pt; font-weight: 800; text-transform: uppercase; }
      .sku-val { font-size: 14pt; font-weight: 900; letter-spacing: 0.5px; }
      .stage-tag { font-size: 6.5pt; font-weight: 800; border: 0.8pt solid #000; padding: 1px 4px; border-radius: 2px; text-transform: uppercase; }
      .sku-desc { font-size: 7.2pt; font-weight: 700; line-height: 1.15; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .grid-specs { display: grid; grid-template-columns: 1.35fr 1fr 1.25fr 0.95fr; gap: 1.5mm; border-bottom: 1pt solid #000; padding: 0.6mm 0; margin-top: 0.2mm; }
      .spec-col { border-right: 0.8pt solid #bbb; padding-right: 1mm; }
      .spec-col:last-child { border-right: none; padding-right: 0; }
      .spec-k { font-size: 5.5pt; font-weight: 800; text-transform: uppercase; color: #222; line-height: 1; }
      .spec-v { font-size: 8pt; font-weight: 900; line-height: 1.1; margin-top: 1px; }
      .spec-v-lg { font-size: 10.5pt; font-weight: 900; }
      .trace-row { display: flex; justify-content: space-between; align-items: center; border-bottom: 0.8pt solid #000; padding: 0.6mm 0; font-size: 6.5pt; font-weight: 700; line-height: 1.1; }
      .trace-item { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .barcode-section { text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; padding-top: 0.4mm; }
      .barcode-svg-wrap { width: 95%; max-width: 90mm; height: 36px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
      .barcode-svg-wrap svg { max-width: 100%; height: 100%; shape-rendering: crispEdges; }
      .code-text { font-family: "Consolas", "Courier New", monospace; font-size: 9.5pt; font-weight: 900; letter-spacing: 1.5px; line-height: 1.1; margin-top: 1px; }
      .footer-text { font-size: 5pt; font-weight: 800; color: #222; letter-spacing: 0.2px; margin-top: 1px; }

      .table-card {
        background: #fff;
        border-radius: 12px;
        border: 1px solid #cbd5e1;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        overflow: hidden;
      }
      .lines-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
      }
      .lines-table th {
        background: #f8fafc;
        padding: 10px 14px;
        text-align: left;
        font-weight: 700;
        color: #475569;
        border-bottom: 1px solid #e2e8f0;
      }
      .lines-table td {
        padding: 10px 14px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
      }
      .lines-table tr:hover td {
        background: #f8fafc;
      }
      .badge-code {
        font-family: "Consolas", monospace;
        font-weight: 800;
        background: #f0fdfa;
        color: #0f766e;
        border: 1px solid #99f6e4;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 12px;
      }
    </style></head><body>';

    // Toolbar superior
    echo '<div class="toolbar">';
    echo '<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">';
    echo '<a class="btn btn-dark" href="/import-containers/' . $containerId . '">⬅️ Volver al Contenedor</a>';
    echo '<div>';
    echo '<div style="font-weight:900;font-size:16px;color:#fff">📦 Trazabilidad de Bobinas en Bodega · Formato Flexo 100×70 mm</div>';
    echo '<div style="font-size:12px;color:#94a3b8">' . h($cTitle) . ' · Exclusivo: <strong>Javier Perozo</strong> · ' . count($existingRolls) . ' bobina(s) registradas</div>';
    echo '</div>';
    echo '</div>';

    $bridgeStatus = method_exists($activePrinter, 'checkBridgeStatus') ? $activePrinter->checkBridgeStatus() : ['online' => false];
    echo '<span id="bridge-status-badge" style="background:' . ($bridgeStatus['online'] ? '#059669' : '#b91c1c') . ';color:#fff;padding:4px 8px;border-radius:6px;font-size:11px;font-weight:800;display:inline-flex;align-items:center;gap:4px" title="' . ($bridgeStatus['online'] ? 'El script print_bridge.ps1 está escuchando en puerto 8767' : 'Inicia iniciar_impresora.bat en tu PC para conectar la impresora térmica local') . '">' . ($bridgeStatus['online'] ? '🟢 Puente Térmico Activo' : '🔴 Puente 8767 Desconectado') . '</span>';
    if (!empty($existingRolls)) {
        echo '<a class="btn btn-primary" href="/import-containers/' . $containerId . '/print-labels?all=1" target="_blank" title="Abrir todas las etiquetas para imprimir por lote o navegador">🖨️ Imprimir Todas (' . count($existingRolls) . ')</a>';
    }
    $headerPrinterName = $configuredPrinterName !== '' ? $configuredPrinterName : 'Impresora Térmica';
    echo '<div style="background:rgba(255,255,255,0.1);padding:6px 12px;border-radius:8px;font-size:12px;color:#e2e8f0;display:flex;align-items:center;gap:6px">';
    echo '<span>🖨️ Impresora Térmica:</span> <strong style="color:#38bdf8">' . h($headerPrinterName) . '</strong>';
    echo '<span style="background:#0284c7;color:#fff;padding:1px 6px;border-radius:4px;font-size:10px;font-weight:800">' . h($configuredPrintLang ?? 'EPL') . '</span>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="container-layout">';

    // Banner explicativo de trazabilidad pura sin duplicar stock
    echo '<div class="alert alert-info">';
    echo '<span style="font-size:22px">🛡️</span>';
    echo '<div>';
    echo '<div style="font-weight:800;font-size:14px">Asignación de Trazabilidad en Bodega (Sin Duplicar Stock ERP)</div>';
    echo '<div style="font-size:12.5px;margin-top:2px;color:#1e3a8a;line-height:1.4">Genera los códigos de trazabilidad cortos para las bobinas que ya se encuentran dentro de bodega y envía las etiquetas directamente a la impresora térmica. <strong>No re-recepciona el contenedor en ERP ni carga doble stock en las bodegas.</strong></div>';
    echo '</div>';
    echo '</div>';

    // Notificaciones de éxito o error
    if ($createdCount > 0) {
        echo '<div class="alert alert-ok"><span>✅</span> <div><strong>¡Trazabilidad Generada Exitosamente!</strong> Se crearon <strong>' . $createdCount . ' bobina(s)</strong> con códigos cortos (' . h($codeRange) . ') y se enviaron <strong>' . $printedMsg . ' etiqueta(s)</strong> a la impresora <u>' . h($headerPrinterName) . '</u>. El stock en ERP se mantuvo intacto sin duplicidades.</div></div>';
    } elseif ($genMsg !== '') {
        echo '<div class="alert alert-ok"><span>✅</span> <div>' . h($genMsg) . '</div></div>';
    }
    if ($printErr !== '') {
        $isConnErr = str_contains($printErr, '8767') || str_contains($printErr, 'Connection refused');
        echo '<div class="alert alert-err" style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">';
        echo '<div style="display:flex;align-items:flex-start;gap:10px">';
        echo '<span style="font-size:20px">⚠️</span>';
        echo '<div>';
        echo '<div style="font-weight:800">Aviso de impresión térmica:</div>';
        echo '<div style="font-size:12.5px;margin-top:2px">' . h($printErr) . '</div>';
        if ($isConnErr) {
            echo '<div style="font-size:12px;margin-top:6px;color:#991b1b">💡 <strong>Solución:</strong> Para imprimir directo en térmica sin ventana de diálogo, asegúrate de haber ejecutado <code>iniciar_proyecto.bat</code> y que la ventana de <em>Unibag Print Bridge (Puerto 8767)</em> permanezca abierta.</div>';
        }
        echo '</div>';
        echo '</div>';
        if (!empty($existingRolls)) {
            echo '<div style="align-self:center">';
            echo '<a class="btn btn-outline" style="background:#fff;border-color:#cbd5e1;padding:6px 12px;font-size:12px;font-weight:800" href="/import-containers/' . $containerId . '/print-labels?all=1" target="_blank">🖨️ Imprimir en Navegador</a>';
            echo '</div>';
        }
        echo '</div>';
    }

    // Disposición: Vista Previa Diseño Flexo + Panel de Líneas
    if (is_array($previewLine)) {
        $pvItemId = (int)$previewLine['import_container_item_id'];
        $pvSku = trim((string)($previewLine['sku_code'] ?? '-'));
        $pvDesc = trim((string)($previewLine['sku_description'] ?? '-'));
        $pvPo = trim((string)($previewLine['po_code'] ?? '-'));
        $pvSupp = trim((string)($previewLine['supplier_name'] ?? '-'));
        $pvRawDate = trim((string)($container['eta_plant'] ?? $container['created_at'] ?? date('Y-m-d')));
        $pvDate = formatLabelDate($pvRawDate);
        $pvMeters = (float)($previewLine['meters'] ?? 0);
        $pvMetersStr = $pvMeters > 0 ? (number_format($pvMeters, 0, ',', '.') . ' m') : '-';
        $pvWidth = (float)($previewLine['width_mm'] ?? 0);
        $pvGrams = (float)($previewLine['grams'] ?? $previewLine['microns'] ?? 0);
        $pvSpecs = '';
        if ($pvWidth > 0) $pvSpecs .= round($pvWidth) . ' mm';
        if ($pvGrams > 0) $pvSpecs .= ($pvSpecs !== '' ? ' · ' : '') . round($pvGrams) . ' g';
        if ($pvSpecs === '') $pvSpecs = '-';
        $pvColor = trim((string)($previewLine['color'] ?? '')) ?: '-';

        // Código de muestra para la vista previa
        $pvSampleCode = !empty($existingRollsByLine[$pvItemId]) ? (string)$existingRollsByLine[$pvItemId][0]['roll_code'] : ($defaultShortPrefix . '001');
        $pvWarehouseCode = trim((string)($existingRollsByLine[$pvItemId][0]['warehouse_code'] ?? '')) ?: 'Por seleccionar';

        $pvSvg = code128Svg($pvSampleCode);

        echo '<div style="display:grid;grid-template-columns:minmax(390px, 420px) 1fr;gap:20px;align-items:start">';

        // Columna Izquierda: Vista Previa Formato Flexo 100x70 mm con Código Corto
        echo '<div class="table-card" style="padding:20px">';
        echo '<div style="font-weight:900;font-size:15px;color:#0f172a;margin-bottom:4px;display:flex;align-items:center;justify-content:space-between">';
        echo '<span>👁️ Vista Previa Etiqueta Flexo</span>';
        echo '<span style="font-size:11px;background:#e0f2fe;color:#0369a1;padding:2px 8px;border-radius:4px;font-weight:800">100 × 70 mm</span>';
        echo '</div>';
        echo '<div style="font-size:12px;color:#64748b;margin-bottom:14px">Código de trazabilidad corto: <strong class="badge-code">' . h($pvSampleCode) . '</strong></div>';

        echo '<div class="thermal-preview-container">';
        echo '<div class="label-card">';

        // 1. Cabecera
        echo '<div class="header-row">';
        echo '<div><div class="brand-title">UNIBAG</div><div class="sub-title">RECEPCIÓN MATERIA PRIMA</div></div>';
        echo '<div class="header-meta"><div class="wh-badge">BOD: ' . h($pvWarehouseCode) . '</div><div class="date-str">FECHA: ' . h($pvDate) . '</div></div>';
        echo '</div>';

        // 2. SKU y Descripción
        echo '<div class="sku-box">';
        echo '<div class="sku-header"><div><span class="sku-label">SKU: </span><span class="sku-val">' . h($pvSku) . '</span></div><div class="stage-tag">MATERIA PRIMA</div></div>';
        echo '<div class="sku-desc" title="' . h($pvDesc) . '">' . h($pvDesc) . '</div>';
        echo '</div>';

        // 3. Cuadrícula (PESO REAL: -)
        echo '<div class="grid-specs">';
        echo '<div class="spec-col"><div class="spec-k">Peso Real</div><div class="spec-v spec-v-lg">-</div></div>';
        echo '<div class="spec-col"><div class="spec-k">Metros</div><div class="spec-v">' . h($pvMetersStr) . '</div></div>';
        echo '<div class="spec-col"><div class="spec-k">Ancho / Gr</div><div class="spec-v">' . h($pvSpecs) . '</div></div>';
        echo '<div class="spec-col"><div class="spec-k">Color</div><div class="spec-v">' . h($pvColor) . '</div></div>';
        echo '</div>';

        // 4. Trazabilidad con datos de contenedor y OC
        echo '<div class="trace-row">';
        echo '<div class="trace-item"><span>OC: </span><b>' . h($pvPo) . '</b></div>';
        echo '<div class="trace-item"><span>CONT: </span><b>' . h($cCode) . '</b></div>';
        echo '<div class="trace-item" style="max-width:40%"><span>PROV: </span><b>' . h($pvSupp) . '</b></div>';
        echo '</div>';

        // 5. Código de barras con el CÓDIGO CORTO de trazabilidad
        echo '<div class="barcode-section">';
        if ($pvSvg !== '') {
            echo '<div class="barcode-svg-wrap">' . $pvSvg . '</div>';
        }
        echo '<div class="code-text">' . h($pvSampleCode) . '</div>';
        echo '<div class="footer-text">CONTENEDOR ' . h($cCode) . ' · CONTROL DE CALIDAD UNIBAG</div>';
        echo '</div>';

        echo '</div>'; // .label-card
        echo '</div>'; // .thermal-preview-container

        echo '<div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">';
        echo '<a class="btn btn-outline" style="flex:1;justify-content:center" href="/import-containers/' . $containerId . '/no-weight-labels?preview_line_id=' . $pvItemId . '&full_preview=1" target="_blank" title="Ver pantalla completa de etiqueta Flexo">📄 Ver Pantalla Completa</a>';
        echo '</div>';
        echo '</div>';

        // Columna Derecha: Líneas del Contenedor con Generación de Trazabilidad
        echo '<div class="table-card">';
        echo '<div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">';
        echo '<div>';
        echo '<div style="font-weight:900;font-size:15px;color:#0f172a">📋 Artículos en el Contenedor (' . count($allLines) . ' líneas)</div>';
        echo '<div style="font-size:12px;color:#64748b">Genera trazabilidad con código corto (ej. <b class="badge-code">' . h($defaultShortPrefix) . '001</b>) e imprime directo en la térmica.</div>';
        echo '</div>';

        // Botón global: generar trazabilidad para bobinas pendientes del contenedor en lote seguro
        $totalRollsInContainer = 0;
        $totalRegisteredRolls = count($existingRolls);
        foreach ($allLines as $ln) {
            $totalRollsInContainer += max(1, (int)($ln['received_rolls'] ?? $ln['ordered_rolls'] ?? 1));
        }
        $pendingToRegister = max(0, $totalRollsInContainer - $totalRegisteredRolls);
        $batchToRegister = min(50, $pendingToRegister);

        echo '<div style="display:flex;gap:8px;align-items:center">';
        if ($totalRegisteredRolls > 0) {
            echo '<form method="post" action="/import-containers/' . $containerId . '/no-weight-labels" style="margin:0">';
            echo '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
            echo '<input type="hidden" name="action" value="reprint_all">';
            echo '<button type="submit" class="btn btn-outline" style="font-weight:800;font-size:12px;color:#0f766e;border-color:#99f6e4" title="Reimprimir todas las etiquetas registradas en este contenedor">';
            echo '🖨️ Reimprimir Todas (' . $totalRegisteredRolls . ')';
            echo '</button>';
            echo '</form>';
        }

        if ($pendingToRegister > 0) {
            echo '<form method="post" action="/import-containers/' . $containerId . '/no-weight-labels" style="margin:0" onsubmit="return confirm(\'¿Generar trazabilidad para un lote de ' . $batchToRegister . ' bobina(s) pendiente(s) e imprimirlas en la térmica?\')">';
            echo '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
            echo '<input type="hidden" name="action" value="generate_and_print">';
            echo '<input type="hidden" name="target_line_id" value="0">';
            echo '<input type="hidden" name="code_prefix" value="' . h($defaultShortPrefix) . '">';
            echo '<input type="hidden" name="qty" value="' . $batchToRegister . '">';
            echo '<select name="warehouse_id" required style="max-width:230px;height:32px" aria-label="Bodega de los nuevos codigos">' . $labelWarehouseOptions . '</select>';
            echo '<button type="submit" class="btn btn-success" style="font-weight:800;font-size:12px">';
            echo '⚡ Generar Lote Pendiente (' . $batchToRegister . ($pendingToRegister > $batchToRegister ? (' de ' . $pendingToRegister) : '') . ')';
            echo '</button>';
            echo '</form>';
        }
        echo '</div>';
        echo '</div>';

        echo '<div style="overflow-x:auto">';
        echo '<table class="lines-table"><thead><tr>';
        echo '<th>Artículo / SKU</th><th>OC / Proveedor</th><th>Especificación</th><th style="text-align:center">Trazabilidad</th><th style="width:310px;text-align:right">Acciones</th>';
        echo '</tr></thead><tbody>';
        foreach ($allLines as $ln) {
            $lnId = (int)$ln['import_container_item_id'];
            $lnSku = trim((string)($ln['sku_code'] ?? '-'));
            $lnDesc = trim((string)($ln['sku_description'] ?? '-'));
            $lnPo = trim((string)($ln['po_code'] ?? '-'));
            $lnSupp = trim((string)($ln['supplier_name'] ?? '-'));
            $lnMeters = (float)($ln['meters'] ?? 0);
            $lnWidth = (float)($ln['width_mm'] ?? 0);
            $lnMic = (float)($ln['grams'] ?? $ln['microns'] ?? 0);
            $lnSpecs = ($lnWidth > 0 ? (round($lnWidth) . 'mm') : '') . ($lnMic > 0 ? (' ' . round($lnMic) . 'g') : '') . ($lnMeters > 0 ? (' ' . round($lnMeters) . 'm') : '');
            if ($lnSpecs === '') $lnSpecs = '-';
            $lnColor = trim((string)($ln['color'] ?? '')) ?: '-';
            $lnTotalRolls = max(1, (int)($ln['received_rolls'] ?? $ln['ordered_rolls'] ?? 1));

            $lnRegisteredCount = isset($existingRollsByLine[$lnId]) ? count($existingRollsByLine[$lnId]) : 0;
            $lnPending = max(0, $lnTotalRolls - $lnRegisteredCount);
            $defaultQtyVal = ($lnPending > 0 && $lnPending <= 50) ? $lnPending : 1;
            $isCurPreview = ($lnId === $pvItemId);

            echo '<tr' . ($isCurPreview ? ' style="background:#f0fdfa"' : '') . '>';
            echo '<td>';
            echo '<div style="font-weight:800;color:#0f766e;font-size:13.5px">' . h($lnSku) . '</div>';
            echo '<div style="font-size:11.5px;color:#64748b;max-width:260px;line-height:1.2;margin-top:2px">' . h($lnDesc) . '</div>';
            echo '</td>';
            echo '<td>';
            echo '<div style="font-weight:700;font-size:12px">OC: ' . h($lnPo) . '</div>';
            echo '<div style="font-size:11px;color:#64748b">' . h($lnSupp) . '</div>';
            echo '</td>';
            echo '<td style="font-size:12px">';
            echo '<div>' . h($lnSpecs) . '</div>';
            echo '<div style="color:#64748b;font-size:11px">Color: ' . h($lnColor) . '</div>';
            echo '</td>';
            echo '<td style="text-align:center">';
            if ($lnRegisteredCount > 0) {
                echo '<span style="background:#ecfdf5;color:#065f46;font-weight:800;padding:3px 8px;border-radius:12px;font-size:11.5px;border:1px solid #a7f3d0">' . $lnRegisteredCount . ' / ' . $lnTotalRolls . ' bobinas</span>';
            } else {
                echo '<span style="background:#f8fafc;color:#64748b;font-weight:800;padding:3px 8px;border-radius:12px;font-size:11.5px;border:1px solid #e2e8f0">0 / ' . $lnTotalRolls . ' bobinas</span>';
            }
            echo '</td>';
            echo '<td style="text-align:right">';
            echo '<div style="display:flex;align-items:center;justify-content:flex-end;gap:6px">';

            // Formulario para generar nuevas bobinas con trazabilidad corta
            echo '<form method="post" action="/import-containers/' . $containerId . '/no-weight-labels" style="display:flex;align-items:center;gap:4px;margin:0">';
            echo '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
            echo '<input type="hidden" name="action" value="generate_and_print">';
            echo '<input type="hidden" name="target_line_id" value="' . $lnId . '">';
            echo '<input type="hidden" name="code_prefix" value="' . h($defaultShortPrefix) . '">';
            echo '<select name="warehouse_id" required style="max-width:200px;height:32px" aria-label="Bodega de los nuevos codigos">' . $labelWarehouseOptions . '</select>';
            echo '<input type="number" name="qty" min="1" max="100" value="' . $defaultQtyVal . '" style="width:58px;height:32px;text-align:center;font-weight:700;border:1px solid #cbd5e1;border-radius:6px;font-size:12px" title="Cantidad de bobinas a generar (máx 100 por lote)">';
            echo '<button type="submit" class="btn btn-primary" style="padding:6px 10px;font-size:12px;font-weight:800" title="Generar códigos cortos e imprimir en térmica">⚡ Generar</button>';
            echo '</form>';

            // Si ya tiene bobinas registradas, botón para reimprimir
            if ($lnRegisteredCount > 0) {
                echo '<a class="btn btn-reprint-sm" href="/import-containers/' . $containerId . '/print-labels?line_id=' . $lnId . '" target="_blank" title="Imprimir lote de esta línea en navegador">🖨️ Lote (' . $lnRegisteredCount . ')</a>';
                echo '<form method="post" action="/import-containers/' . $containerId . '/no-weight-labels" style="margin:0">';
                echo '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
                echo '<input type="hidden" name="action" value="reprint_all">';
                echo '<input type="hidden" name="target_line_id" value="' . $lnId . '">';
                echo '<button type="submit" class="btn btn-reprint-sm" title="Reimprimir etiquetas de esta línea en térmica">⚡ Térmica</button>';
                echo '</form>';
            }

            echo '<a class="btn btn-outline" href="/import-containers/' . $containerId . '/no-weight-labels?preview_line_id=' . $lnId . '" style="padding:6px 9px;font-size:12px" title="Ver diseño en vista previa">👁️</a>';
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div></div>';
        echo '</div>'; // grid layout

        // Sección Inferior: Listado de Bobinas con Trazabilidad Asignada
        if (!empty($existingRolls)) {
            echo '<div class="table-card" style="margin-top:10px">';
            echo '<div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">';
            echo '<div>';
            echo '<div style="font-weight:900;font-size:15px;color:#0f172a">🏷️ Bobinas con Trazabilidad Asignada (' . count($existingRolls) . ' bobinas en bodega)</div>';
            echo '</div>';
            echo '<div style="font-size:12px;color:#0f766e;font-weight:700">Prefijo utilizado: <span class="badge-code">' . h($defaultShortPrefix) . 'XXX</span></div>';
            echo '</div>';

            echo '<div style="overflow-x:auto;max-height:380px">';
            echo '<table class="lines-table"><thead><tr>';
            echo '<th>ID</th><th>Código Trazabilidad Corto</th><th>SKU / Producto</th><th>Bodega</th><th>Estado</th><th>Fecha Registro</th><th style="text-align:right">Acciones</th>';
            echo '</tr></thead><tbody>';
            foreach ($existingRolls as $rRow) {
                $rId = (int)$rRow['id'];
                $rCode = (string)$rRow['roll_code'];
                $rSku = (string)($rRow['sku_code'] ?? '-');
                $rDesc = (string)($rRow['sku_desc'] ?? '-');
                $rWh = (string)($rRow['warehouse_name'] ?? 'Bodega MP');
                $rDate = (string)$rRow['created_at'];

                echo '<tr>';
                echo '<td style="color:#64748b;font-weight:700;font-size:11px">#' . $rId . '</td>';
                echo '<td><strong class="badge-code">' . h($rCode) . '</strong></td>';
                echo '<td><div style="font-weight:800;color:#0f172a">' . h($rSku) . '</div><div style="font-size:11px;color:#64748b">' . h($rDesc) . '</div></td>';
                echo '<td style="font-size:12px">' . h($rWh) . '</td>';
                echo '<td><span style="background:#ecfdf5;color:#065f46;font-size:11px;font-weight:800;padding:2px 8px;border-radius:6px">RECEPCIONADO</span></td>';
                echo '<td style="font-size:11.5px;color:#64748b">' . h($rDate) . '</td>';
                echo '<td style="text-align:right">';
                echo '<div style="display:flex;align-items:center;justify-content:flex-end;gap:6px">';
                // Reimprimir directa a térmica
                echo '<form method="post" action="/import-containers/' . $containerId . '/no-weight-labels" style="margin:0">';
                echo '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
                echo '<input type="hidden" name="action" value="reprint_roll">';
                echo '<input type="hidden" name="roll_id" value="' . $rId . '">';
                echo '<button type="submit" class="btn btn-reprint-sm" title="Reimprimir etiqueta a la impresora térmica">⚡ Térmica</button>';
                echo '</form>';
                // Ver / Imprimir en navegador
                echo '<a class="btn btn-outline" style="padding:4px 8px;font-size:11px" href="/rolls/' . $rId . '/label?no_weight=1" target="_blank" title="Ver / Imprimir etiqueta en navegador">🖨️ Ver / Imprimir</a>';
                // Eliminar trazabilidad (si fue error)
                echo '<form method="post" action="/import-containers/' . $containerId . '/no-weight-labels" style="margin:0" onsubmit="return confirm(\'¿Eliminar la trazabilidad de la bobina ' . h($rCode) . '?\')">';
                echo '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
                echo '<input type="hidden" name="action" value="delete_roll">';
                echo '<input type="hidden" name="roll_id" value="' . $rId . '">';
                echo '<input type="hidden" name="roll_code" value="' . h($rCode) . '">';
                echo '<button type="submit" class="btn btn-danger-sm" title="Eliminar registro de trazabilidad">🗑️</button>';
                echo '</form>';
                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div></div>';
        }
    } else {
        echo '<div style="text-align:center;padding:50px 20px;background:#fff;border-radius:12px;border:1px solid #e2e8f0">';
        echo '<div style="font-size:42px;margin-bottom:12px">📦</div>';
        echo '<div style="font-size:17px;font-weight:900;color:#0f172a">Este cargamento no registra líneas de artículos</div>';
        echo '<div style="margin-top:14px"><a class="btn btn-primary" href="/import-containers/' . $containerId . '">Volver al Contenedor</a></div>';
        echo '</div>';
    }

    echo '</div>'; // .container-layout
    echo '<script>
    (function() {
      const badge = document.getElementById("bridge-status-badge");
      if (!badge) return;
      fetch("http://127.0.0.1:8767/health", { method: "GET", mode: "cors", cache: "no-store" })
        .then(r => r.json())
        .then(data => {
          if (data && (data.ok || data.status === "running")) {
            badge.style.background = "#059669";
            badge.innerHTML = "🟢 Puente Térmico Activo (PC Local)";
            badge.title = "Puente local conectado en puerto 8767. Listo para imprimir en impresora térmica.";
          }
        })
        .catch(() => {});
    })();
    </script>';
    echo '</body></html>';
    exit;
}

if (preg_match('#^/reception/labels/([a-f0-9]{32})$#', $path, $m) === 1 && $method === 'GET') {
    $batch = $_SESSION['reception_label_batches'][$m[1]] ?? null;
    if (!is_array($batch)) {
        http_response_code(404);
        render('Lote no encontrado', '<div class="card">El lote no esta disponible en esta sesion. Reimprime desde los productos recibidos.</div>');
        exit;
    }
    $rolls = [];
    foreach ($batch['ids'] as $id) {
        $roll = $service->getRoll((int)$id);
        if (!is_array($roll)) {
            http_response_code(409);
            render('Lote modificado', '<div class="card">Un producto del lote fue eliminado. Reimprime desde los productos recibidos.</div>');
            exit;
        }
        $rolls[] = $roll;
    }
    $batchPrinter = $printer->withProfile(unibagNormalizePrinterProfile((string)($_GET['printer_profile'] ?? 'default')));
    if (isset($_GET['raw_job']) && (string)$_GET['raw_job'] === '1') {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $cmd = $batchPrinter->buildRollsBatchCommand($rolls);
        } catch (InvalidArgumentException $e) {
            // Peso inválido en el lote: no se entrega ningún comando de impresión.
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode([
            'ok' => true,
            'print_job' => $batchPrinter->buildClientJob($cmd, null, 'LOTE-' . count($rolls) . '-ETIQ'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (isset($_GET['server_print']) && (string)$_GET['server_print'] === '1' && $batchPrinter->isEnabled()) {
        // Guardia anti-duplicado: un refresco de la página no reimprime el lote.
        if (unibagPrintGuard('batch:' . implode(',', array_map('intval', $batch['ids'])))) {
            $batchPrinter->printRollLabels($rolls);
        }
    }
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Etiquetas de recepcion</title><style>
        @page{size:100mm 70mm;margin:0}*{box-sizing:border-box}body{margin:0;font-family:Arial;color:#000;background:#eee}
        .toolbar{padding:12px}.label{background:white;width:100mm;height:70mm;padding:3mm;border:1px solid black;overflow:hidden;display:flex;flex-direction:column;justify-content:space-between;margin:8px auto}
        .sku{font-size:22pt;font-weight:900}.code{font-size:13pt;font-weight:bold}.desc{font-size:11pt}.value{font-size:22pt;font-weight:900}
        @media print{body{background:white}.toolbar{display:none}.label{margin:0;break-after:page;break-inside:avoid}.label:last-child{break-after:auto}}
        </style></head><body><div class="toolbar"><strong>' . count($rolls) . ' etiquetas</strong> <button onclick="window.print()">Imprimir lote</button></div>';
    foreach ($rolls as $roll) {
        $weight = (float)($roll['weight_kg'] ?? 0);
        $quantityMode = ($roll['reception_mode'] ?? '') === 'QUANTITY';
        $value = $quantityMode ? number_format((float)($roll['received_qty'] ?? 1), 0, ',', '.') . ' Unid.' : number_format($weight, 2, ',', '.') . ' Kg';
        $code = (string)$roll['roll_code'];
        echo '<section class="label"><div><strong>UNIBAG</strong> | ' . h(str_replace('-', '/', formatLabelDate((string)($roll['arrival_date'] ?? $roll['created_at'])))) . '</div>';
        echo '<div class="sku">' . h((string)($roll['sku_code'] ?? '')) . '</div><div class="desc">' . h((string)($roll['sku_description'] ?? '')) . '</div>';
        echo '<div class="value">' . h($value) . '</div><div class="desc">Bodega: ' . h((string)($roll['warehouse_code'] ?? '')) . ' ' . h((string)($roll['warehouse_name'] ?? '')) . '</div>';
        echo '<div class="desc">OC: ' . h((string)($roll['po_code'] ?? '-')) . ' | Contenedor: ' . h((string)($roll['container_code'] ?? '-')) . '</div>';
        echo '<div>' . code128Svg($code) . '<div class="code">' . h($code) . '</div></div></section>';
    }
    if (($_GET['auto_print'] ?? '') === '1') {
        echo '<script>window.addEventListener("load",function(){window.print();});</script>';
    }
    echo '</body></html>';
    exit;
}

if ($path === '/reception/test-label' && $method === 'GET') {
    $type = (string)($_GET['type'] ?? 'po'); // 'po' | 'import'
    $lineId = (int)($_GET['line'] ?? 0);
    $weightParam = isset($_GET['weight']) && (float)$_GET['weight'] > 0 ? (float)$_GET['weight'] : null;
    $whId = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;
    $printerProfile = unibagNormalizePrinterProfile((string)($_GET['printer_profile'] ?? 'default'));
    $activePrinter = $printer->withProfile($printerProfile);
    $printerProfileQuery = unibagPrinterProfileQuery($printerProfile);

    $line = null;
    if ($type === 'import' && $lineId > 0) {
        $line = $service->getImportContainerLine($lineId);
    } elseif ($lineId > 0) {
        $line = $service->getPurchaseOrderLine($lineId);
    }

    $skuCode = trim((string)($line['sku_code'] ?? 'TEL0044'));
    $skuDescription = trim((string)($line['sku_description'] ?? 'PP/BLANCO/W80/100X80X1100'));
    $poCode = trim((string)($line['po_code'] ?? 'OC0000003'));
    $containerCode = trim((string)($line['container_code'] ?? ($type === 'import' ? 'HLBU9654413' : '-')));
    $supplierName = trim((string)($line['supplier_name'] ?? 'Proveedor Unibag'));
    $grams = trim((string)($line['grams'] ?? $line['microns'] ?? '35'));
    $widthMm = (float)($line['width_mm'] ?? 450);
    $color = trim((string)($line['color'] ?? 'Blanco'));
    $metersVal = (float)($line['meters'] ?? 2200);
    $arrivalDate = formatLabelDate((string)($line['arrival_date'] ?? date('Y-m-d')));

    $warehouseCode = '100';
    $warehouseName = 'BODEGA 100';
    if ($whId > 0) {
        $whList = $service->listWarehousesForReception();
        foreach ($whList as $w) {
            if ((int)$w['id'] === $whId) {
                $warehouseCode = (string)$w['code'];
                $warehouseName = (string)$w['name'];
                break;
            }
        }
    }

    $weightKg = $weightParam ?? (round((float)($line['ordered_weight_kg'] ?? 0), 2) ?: 120.00);
    $weightStr = $weightKg > 0 ? number_format($weightKg, 2, ',', '.') . ' Kg' : '1 Unid.';
    $metersStr = $metersVal > 0 ? number_format($metersVal, 0, ',', '.') . ' m' : '-';

    $specsStr = '';
    if ($widthMm > 0) {
        $specsStr .= round($widthMm) . ' mm';
    }
    if ($grams !== '' && (float)$grams > 0) {
        $specsStr .= ($specsStr !== '' ? ' · ' : '') . round((float)$grams) . ' g';
    }
    if ($specsStr === '') {
        $specsStr = '-';
    }

    $sampleRollCode = 'MUESTRA-' . ($skuCode !== '' ? $skuCode : '10X7');
    $sampleRoll = [
        'id' => 0,
        'roll_code' => $sampleRollCode,
        'sku_code' => $skuCode,
        'sku_description' => $skuDescription,
        'warehouse_code' => $warehouseCode,
        'warehouse_name' => $warehouseName,
        'weight_kg' => $weightKg,
        'received_qty' => 1.0,
        'reception_mode' => ($line['reception_mode'] ?? 'WEIGHT'),
        'grams' => $grams,
        'width_mm' => $widthMm,
        'color' => $color,
        'meters' => $metersVal,
        'arrival_date' => $arrivalDate,
        'created_at' => date('Y-m-d H:i:s'),
        'po_code' => $poCode,
        'container_code' => $containerCode,
        'supplier_name' => $supplierName,
        'process_stage' => 'RAW',
        'is_sample' => true,
    ];
    $printLanguage = $activePrinter->getConfiguredPrintLanguage();
    $printExtension = in_array($printLanguage, ['tspl', 'epl', 'zpl'], true) ? $printLanguage : 'zpl';
    if (isset($_GET['zpl']) && (string)$_GET['zpl'] === '1') {
        header('Content-Type: text/plain; charset=utf-8');
        echo $activePrinter->getRollPrintCommand($sampleRoll);
        exit;
    }
    if (isset($_GET['download_zpl']) && (string)$_GET['download_zpl'] === '1') {
        $filename = 'etiqueta_muestra_10x7_' . $skuCode . '.' . $printExtension;
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $activePrinter->getRollPrintCommand($sampleRoll);
        exit;
    }

    $autoPrint = isset($_GET['auto_print']) && (string)$_GET['auto_print'] === '1';
    if (isset($_GET['raw_job']) && (string)$_GET['raw_job'] === '1') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'print_job' => $activePrinter->buildClientJob($activePrinter->getRollPrintCommand($sampleRoll), null, $sampleRollCode),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $serverPrint = isset($_GET['server_print']) && (string)($_GET['server_print']) === '1';
    $serverPrintResult = null;
    if (($serverPrint || $autoPrint) && $activePrinter->isEnabled()) {
        $serverPrintResult = $activePrinter->printRollLabel($sampleRoll);
        if (($serverPrintResult['ok'] ?? false) === true || !empty($serverPrintResult['client_queued'])) {
            $autoPrint = false;
        }
    }

renderReceptionThermalLabel([
    'sku_code' => $skuCode,
    'sku_description' => $skuDescription,
    'arrival_date' => $arrivalDate,
    'warehouse_code' => $warehouseCode,
    'warehouse_name' => $warehouseName,
    'weight_str' => $weightStr,
    'meters_str' => $metersStr,
    'specs_str' => $specsStr,
    'color' => $color,
    'po_code' => $poCode,
    'container_code' => $containerCode,
    'supplier_name' => $supplierName,
    'barcode_value' => $sampleRollCode,
    'stage_badge' => 'MUESTRA / TEST',
    'footer_text' => 'ETIQUETA DE MUESTRA SIN REGISTRO · CONTROL DE CALIDAD',
    'is_sample' => true,
    'auto_print' => $autoPrint,
    'print_language' => $printLanguage,
    'printer_name' => $activePrinter->getConfiguredPrinterName(),
    'server_print_allowed' => $activePrinter->isEnabled(),
    'server_print_url' => '?server_print=1&type=' . urlencode($type) . '&line=' . $lineId . $printerProfileQuery,
    'server_print_result' => $serverPrintResult,
    'download_zpl_url' => '?download_zpl=1&type=' . urlencode($type) . '&line=' . $lineId . $printerProfileQuery,
    'raw_zpl_url' => '?zpl=1&type=' . urlencode($type) . '&line=' . $lineId . $printerProfileQuery,
    'title' => 'Etiqueta de Prueba 10x7 - ' . $skuCode,
]);
exit;
}

if (preg_match('#^/rolls/(\d+)/label$#', $path, $m) === 1 && $method === 'GET') {
    $id = (int)$m[1];
    $printerProfile = unibagNormalizePrinterProfile((string)($_GET['printer_profile'] ?? 'default'));
    $activePrinter = $printer->withProfile($printerProfile);
    $printerProfileQuery = unibagPrinterProfileQuery($printerProfile);
    $roll = $service->getRoll($id);
    if ($roll === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la bobina.</div>');
        exit;
    }

    $noWeight = (isset($_GET['no_weight']) && (string)$_GET['no_weight'] === '1');
    if ($noWeight) {
        $roll['no_weight'] = true;
        $roll['weight_str'] = '-';
    }
    if (isset($_GET['raw_job']) && (string)$_GET['raw_job'] === '1') {
        header('Content-Type: application/json; charset=utf-8');
        $rawRollCode = trim((string)($roll['roll_code'] ?? ('#' . $id)));
        try {
            $rawRollCmd = $activePrinter->getRollPrintCommand($roll);
        } catch (InvalidArgumentException $e) {
            // Peso inválido: no se entrega ningún comando de impresión.
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode([
            'ok' => true,
            'print_job' => $activePrinter->buildClientJob($rawRollCmd, null, $rawRollCode),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($noWeight) {
        $roll['no_weight'] = true;
        $roll['weight_str'] = '-';
    }

    $printLanguage = $activePrinter->getConfiguredPrintLanguage();
    $printExtension = in_array($printLanguage, ['tspl', 'epl', 'zpl'], true) ? $printLanguage : 'zpl';
    if (isset($_GET['zpl']) && (string)$_GET['zpl'] === '1') {
        header('Content-Type: text/plain; charset=utf-8');
        echo $activePrinter->getRollPrintCommand($roll);
        exit;
    }
    if (isset($_GET['download_zpl']) && (string)$_GET['download_zpl'] === '1') {
        $filename = 'etiqueta_bobina_' . ($roll['roll_code'] ?: $id) . '.' . $printExtension;
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $activePrinter->getRollPrintCommand($roll);
        exit;
    }

    $autoPrint = isset($_GET['auto_print']) && (string)$_GET['auto_print'] === '1';
    $serverPrint = isset($_GET['server_print']) && (string)$_GET['server_print'] === '1';
    $serverPrintResult = null;
    if (($serverPrint || $autoPrint) && $activePrinter->isEnabled()) {
        if (!unibagPrintGuard('roll:' . (int)$id)) {
            // La misma etiqueta se envió hace pocos segundos (refresco de página o doble clic):
            // no se vuelve a imprimir.
            $serverPrintResult = ['ok' => true, 'duplicate' => true];
            $autoPrint = false;
        } else {
            $serverPrintResult = $activePrinter->printRollLabel($roll);
            if (($serverPrintResult['ok'] ?? false) === true || !empty($serverPrintResult['client_queued']) || !empty($serverPrintResult['ambiguous'])) {
                $autoPrint = false;
            }
        }
    }

    // Datos formateados para la etiqueta física de 10 x 7 cm (100mm x 70mm)
    $rollCode = trim((string)($roll['roll_code'] ?? ''));
    $barcodeValue = $rollCode !== '' ? $rollCode : (string)(int)$roll['id'];

    $skuCode = trim((string)($roll['sku_code'] ?? '-'));
    $skuDescription = trim((string)($roll['sku_description'] ?? '-'));
    $rawDate = trim((string)($roll['arrival_date'] ?? ''));
    if ($rawDate === '' || $rawDate === '-') {
        $rawDate = trim((string)($roll['created_at'] ?? ''));
    }
    $arrivalDate = formatLabelDate($rawDate !== '' ? $rawDate : date('Y-m-d'));

    $warehouseCode = trim((string)($roll['warehouse_code'] ?? '-'));
    $warehouseName = trim((string)($roll['warehouse_name'] ?? ''));

    $weightKg = round((float)($roll['weight_kg'] ?? 0), 2);
    $weightStr = $noWeight ? '-' : ($weightKg > 0 ? number_format($weightKg, 2, ',', '.') . ' Kg' : '1 Unid.');

    $metersVal = (float)($roll['meters'] ?? 0);
    $metersStr = $metersVal > 0 ? number_format($metersVal, 0, ',', '.') . ' m' : '-';

    $widthMm = (float)($roll['width_mm'] ?? 0);
    $gramsVal = (float)($roll['grams'] ?? $roll['microns'] ?? 0);
    $specsStr = '';
    if ($widthMm > 0) {
        $specsStr .= round($widthMm) . ' mm';
    }
    if ($gramsVal > 0) {
        $specsStr .= ($specsStr !== '' ? ' · ' : '') . round($gramsVal) . ' g';
    }
    if ($specsStr === '') {
        $specsStr = '-';
    }

    $color = trim((string)($roll['color'] ?? '')) ?: '-';
    $poCode = trim((string)($roll['po_code'] ?? '')) ?: '-';
    $containerCode = trim((string)($roll['container_code'] ?? '')) ?: '-';
    $supplierName = trim((string)($roll['supplier_name'] ?? '')) ?: '-';
    $stage = strtoupper(trim((string)($roll['process_stage'] ?? 'RAW')));
    $stageBadge = 'MATERIA PRIMA';
    if ($stage === 'PRINTED' || str_starts_with($barcodeValue, 'ROLL-SE-')) {
        $stageBadge = 'IMPRESA';
    } elseif (str_starts_with($barcodeValue, 'ROLL-EMP-')) {
        $stageBadge = 'EMPALMADA';
    } elseif (str_starts_with($barcodeValue, 'ROLL-BOL-')) {
        $stageBadge = 'CUERPO BOLSA';
    } elseif (str_starts_with($barcodeValue, 'ROLL-MAN-') || str_starts_with($barcodeValue, 'ROLL-HDL-')) {
        $stageBadge = 'MANILLAS';
    } elseif ($stage === 'CUT') {
        $stageBadge = 'CORTADA';
    }

    renderReceptionThermalLabel([
        'sku_code' => $skuCode,
        'sku_description' => $skuDescription,
        'arrival_date' => $arrivalDate,
        'warehouse_code' => $warehouseCode,
        'warehouse_name' => $warehouseName,
        'weight_str' => $weightStr,
        'meters_str' => $metersStr,
        'specs_str' => $specsStr,
        'color' => $color,
        'po_code' => $poCode,
        'container_code' => $containerCode,
        'supplier_name' => $supplierName,
        'barcode_value' => $barcodeValue,
        'stage_badge' => $stageBadge,
        'footer_text' => 'ID #' . (int)$id . ($warehouseName !== '' ? ' · ' . $warehouseName : '') . ' · CONTROL DE CALIDAD UNIBAG',
        'is_sample' => false,
        'auto_print' => $autoPrint,
        'print_language' => $printLanguage,
        'printer_name' => $activePrinter->getConfiguredPrinterName(),
        'server_print_allowed' => $activePrinter->isEnabled(),
        'server_print_url' => '/rolls/' . (int)$id . '/label?server_print=1' . ($noWeight ? '&no_weight=1' : '') . $printerProfileQuery,
        'server_print_result' => $serverPrintResult,
        'download_zpl_url' => '/rolls/' . (int)$id . '/label?download_zpl=1' . ($noWeight ? '&no_weight=1' : '') . $printerProfileQuery,
        'raw_zpl_url' => '/rolls/' . (int)$id . '/label?zpl=1' . ($noWeight ? '&no_weight=1' : '') . $printerProfileQuery,
        'title' => 'Etiqueta 10x7 - ' . $barcodeValue,
    ]);
    exit;
}

if (preg_match('#^/boxes/(\d+)/label$#', $path, $m) === 1 && $method === 'GET') {
    $id = (int)$m[1];
    $box = $service->getBox($id);
    if (isset($_GET['raw_job']) && (string)$_GET['raw_job'] === '1') {
        header('Content-Type: application/json; charset=utf-8');
        if ($box === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'No existe la caja.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $boxPrinter = $printer->withProfile(unibagNormalizePrinterProfile((string)($_GET['printer_profile'] ?? 'default')));
        echo json_encode([
            'ok' => true,
            'print_job' => $boxPrinter->buildClientJob($boxPrinter->buildBoxCommand($box), null, (string)($box['box_code'] ?? ('CAJA-' . $id))),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($box === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la caja.</div>');
        exit;
    }

    $autoPrint = isset($_GET['auto_print']) && (string)$_GET['auto_print'] === '1';
    $serverPrint = isset($_GET['server_print']) && (string)$_GET['server_print'] === '1';
    $serverPrintResult = null;
    if (($serverPrint || $autoPrint) && $printer->isEnabled()) {
        if (!unibagPrintGuard('box:' . (int)($box['id'] ?? 0))) {
            // La misma etiqueta se envió hace pocos segundos (refresco de página o doble clic):
            // no se vuelve a imprimir.
            $serverPrintResult = ['ok' => true, 'duplicate' => true];
            $autoPrint = false;
        } else {
            $serverPrintResult = $printer->printBoxLabel($box);
            if (($serverPrintResult['ok'] ?? false) === true || !empty($serverPrintResult['client_queued']) || !empty($serverPrintResult['ambiguous'])) {
                $autoPrint = false;
            }
        }
    }

    $barcodeValue = strtoupper(trim((string)$box['box_code']));
    $barcodeSvg = code128Svg($barcodeValue);
    $destinationLabel = (string)$box['destination_mode'] === 'CUSTOMER_ORDER'
        ? 'OC cliente: ' . (string)($box['customer_order_ref'] ?? '-')
        : 'Bodega: ' . (string)($box['warehouse_code'] ?? '-');

    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Etiqueta caja</title>';
    echo '<style>
      *{box-sizing:border-box}
      body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:0;background:#fff;color:#111}
      .wrap{padding:10px}
      .label{border:2px solid #111;border-radius:10px;padding:18px;max-width:700px}
      .brand{font-weight:900;letter-spacing:1px;font-size:22px}
      .kind{margin-top:6px;font-size:15px;font-weight:900}
      .product{margin-top:10px;font-size:28px;font-weight:900;line-height:1.15}
      .divider{height:2px;background:#111;margin:10px 0}
      .rows{display:grid;grid-template-columns:150px 1fr;gap:8px 12px}
      .k{font-size:13px;color:#111;font-weight:800}
      .v{font-size:18px;font-weight:900}
      .bottom{margin-top:12px;display:flex;flex-direction:column;align-items:center;justify-content:center}
      .barcode{margin:10px 0 6px}
      .idtxt{font-size:22px;font-weight:900;letter-spacing:.8px}
      .ok,.err{background:#fff;border:1px solid #111;color:#111;padding:10px;border-radius:10px;margin-bottom:10px}
      @media print{
        body{margin:0}
        .wrap{padding:0}
        .label{border:none}
      }
    </style></head><body><div class="wrap">';
    if (is_array($serverPrintResult)) {
        if (($serverPrintResult['ok'] ?? false) === true || !empty($serverPrintResult['client_queued'])) {
            echo '<div class="ok">Etiqueta enviada a la impresora.</div>';
        } else {
            echo '<div class="err">No se pudo imprimir: ' . h((string)($serverPrintResult['error'] ?? 'Error desconocido.')) . '</div>';
        }
    }
    echo '<div class="label">';
    echo '<div class="brand">UNIBAG</div>';
    echo '<div class="kind">CAJA TRAZABLE</div>';
    echo '<div class="product">' . h((string)$box['final_sku']) . '</div>';
    echo '<div class="divider"></div>';
    echo '<div class="rows">';
    echo '<div class="k">Caja</div><div class="v">' . h((string)$box['box_code']) . '</div>';
    echo '<div class="k">OT</div><div class="v">' . h((string)($box['ot_code'] ?? '-')) . '</div>';
    echo '<div class="k">Bobina origen</div><div class="v">' . h((string)$box['source_roll_code']) . '</div>';
    echo '<div class="k">Unidades</div><div class="v">' . h((string)$box['units_qty']) . '</div>';
    echo '<div class="k">Pallet</div><div class="v">' . h((string)($box['pallet_code'] ?? '-')) . '</div>';
    echo '<div class="k">Operador</div><div class="v">' . h((string)$box['operator_name']) . '</div>';
    echo '<div class="k">Destino</div><div class="v">' . h($destinationLabel) . '</div>';
    echo '</div>';
    echo '<div class="bottom">';
    if ($barcodeSvg !== '') {
        echo '<div class="barcode">' . $barcodeSvg . '</div>';
    }
    echo '<div class="idtxt">' . h($barcodeValue) . '</div>';
    echo '</div></div></div>';
    if ($autoPrint) {
        echo '<script>
          (function(){
            function doPrint(){
              try { window.focus(); } catch(e) {}
              try { window.print(); } catch(e) {}
            }
            function schedule(){
              try { setTimeout(doPrint, 800); } catch(e) {}
            }
            if (document.readyState === "complete") schedule();
            else window.addEventListener("load", schedule);
          })();
        </script>';
    }
    echo '</body></html>';
    exit;
}

if (preg_match('#^/pallets/(\d+)/label$#', $path, $m) === 1 && $method === 'GET') {
    $id = (int)$m[1];
    $pallet = $service->getPallet($id);
    if (isset($_GET['raw_job']) && (string)$_GET['raw_job'] === '1') {
        header('Content-Type: application/json; charset=utf-8');
        if ($pallet === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'No existe el pallet.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $pBoxes = $service->listBoxesByPallet($id);
        $palletPrinter = $printer->withProfile(unibagNormalizePrinterProfile((string)($_GET['printer_profile'] ?? 'default')));
        echo json_encode([
            'ok' => true,
            'print_job' => $palletPrinter->buildClientJob($palletPrinter->buildPalletCommand($pallet, $pBoxes), null, (string)($pallet['pallet_code'] ?? ('PALLET-' . $id))),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($pallet === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe el pallet.</div>');
        exit;
    }

    $palletBoxes = $service->listBoxesByPallet($id);
    $autoPrint = isset($_GET['auto_print']) && (string)$_GET['auto_print'] === '1';
    $serverPrint = isset($_GET['server_print']) && (string)$_GET['server_print'] === '1';
    $serverPrintResult = null;
    if (($serverPrint || $autoPrint) && $printer->isEnabled()) {
        if (!unibagPrintGuard('pallet:' . (int)($pallet['id'] ?? 0))) {
            // La misma etiqueta se envió hace pocos segundos (refresco de página o doble clic):
            // no se vuelve a imprimir.
            $serverPrintResult = ['ok' => true, 'duplicate' => true];
            $autoPrint = false;
        } else {
            $serverPrintResult = $printer->printPalletLabel($pallet, $palletBoxes);
            if (($serverPrintResult['ok'] ?? false) === true || !empty($serverPrintResult['client_queued']) || !empty($serverPrintResult['ambiguous'])) {
                $autoPrint = false;
            }
        }
    }

    $barcodeValue = strtoupper(trim((string)$pallet['pallet_code']));
    $barcodeSvg = code128Svg($barcodeValue);
    $destinationLabel = (string)$pallet['destination_mode'] === 'CUSTOMER_ORDER'
        ? 'OC cliente: ' . (string)($pallet['customer_order_ref'] ?? '-')
        : 'Bodega: ' . (string)($pallet['warehouse_code'] ?? '-');
    $boxCodes = array_map(
        static fn(array $box): string => (string)($box['box_code'] ?? ''),
        array_filter($palletBoxes, static fn(array $box): bool => trim((string)($box['box_code'] ?? '')) !== '')
    );

    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Etiqueta pallet</title>';
    echo '<style>
      *{box-sizing:border-box}
      body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:0;background:#fff;color:#111}
      .wrap{padding:10px}
      .label{border:2px solid #111;border-radius:10px;padding:18px;max-width:700px}
      .brand{font-weight:900;letter-spacing:1px;font-size:22px}
      .kind{margin-top:6px;font-size:15px;font-weight:900}
      .product{margin-top:10px;font-size:28px;font-weight:900;line-height:1.15}
      .divider{height:2px;background:#111;margin:10px 0}
      .rows{display:grid;grid-template-columns:150px 1fr;gap:8px 12px}
      .k{font-size:13px;color:#111;font-weight:800}
      .v{font-size:18px;font-weight:900}
      .bottom{margin-top:12px;display:flex;flex-direction:column;align-items:center;justify-content:center}
      .barcode{margin:10px 0 6px}
      .idtxt{font-size:22px;font-weight:900;letter-spacing:.8px}
      .ok,.err{background:#fff;border:1px solid #111;color:#111;padding:10px;border-radius:10px;margin-bottom:10px}
      @media print{
        body{margin:0}
        .wrap{padding:0}
        .label{border:none}
      }
    </style></head><body><div class="wrap">';
    if (is_array($serverPrintResult)) {
        if (($serverPrintResult['ok'] ?? false) === true || !empty($serverPrintResult['client_queued'])) {
            echo '<div class="ok">Etiqueta enviada a la impresora.</div>';
        } else {
            echo '<div class="err">No se pudo imprimir: ' . h((string)($serverPrintResult['error'] ?? 'Error desconocido.')) . '</div>';
        }
    }
    echo '<div class="label">';
    echo '<div class="brand">UNIBAG</div>';
    echo '<div class="kind">PALLET TRAZABLE</div>';
    echo '<div class="product">' . h((string)$pallet['final_sku']) . '</div>';
    echo '<div class="divider"></div>';
    echo '<div class="rows">';
    echo '<div class="k">Pallet</div><div class="v">' . h((string)$pallet['pallet_code']) . '</div>';
    echo '<div class="k">OT</div><div class="v">' . h((string)($pallet['ot_code'] ?? '-')) . '</div>';
    echo '<div class="k">Bobina origen</div><div class="v">' . h((string)$pallet['source_roll_code']) . '</div>';
    echo '<div class="k">Cajas</div><div class="v">' . h((string)$pallet['box_count']) . '</div>';
    echo '<div class="k">Operador</div><div class="v">' . h((string)$pallet['operator_name']) . '</div>';
    echo '<div class="k">Destino</div><div class="v">' . h($destinationLabel) . '</div>';
    echo '<div class="k">Códigos caja</div><div class="v">' . h($boxCodes === [] ? '-' : implode(', ', $boxCodes)) . '</div>';
    echo '</div>';
    echo '<div class="bottom">';
    if ($barcodeSvg !== '') {
        echo '<div class="barcode">' . $barcodeSvg . '</div>';
    }
    echo '<div class="idtxt">' . h($barcodeValue) . '</div>';
    echo '</div></div></div>';
    if ($autoPrint) {
        echo '<script>
          (function(){
            function doPrint(){
              try { window.focus(); } catch(e) {}
              try { window.print(); } catch(e) {}
            }
            function schedule(){
              try { setTimeout(doPrint, 800); } catch(e) {}
            }
            if (document.readyState === "complete") schedule();
            else window.addEventListener("load", schedule);
          })();
        </script>';
    }
    echo '</body></html>';
    exit;
}

if (preg_match('#^/work-orders/(\d+)/box-label$#', $path, $m) === 1 && $method === 'GET') {
    $id = (int)$m[1];
    $ot = $service->getWorkOrder($id);
    if (isset($_GET['raw_job']) && (string)$_GET['raw_job'] === '1') {
        header('Content-Type: application/json; charset=utf-8');
        if ($ot === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'No existe la OT.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $otFinish = $service->getLastWorkOrderFinish($id);
        $otPrinter = $printer->withProfile(unibagNormalizePrinterProfile((string)($_GET['printer_profile'] ?? 'default')));
        echo json_encode([
            'ok' => true,
            'print_job' => $otPrinter->buildClientJob($otPrinter->buildWorkOrderBoxCommand($ot, $otFinish), null, (string)($ot['ot_code'] ?? ('OT-' . $id))),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($ot === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la OT.</div>');
        exit;
    }

    $finish = $service->getLastWorkOrderFinish($id);
    $autoPrint = isset($_GET['auto_print']) && (string)$_GET['auto_print'] === '1';
    $serverPrint = isset($_GET['server_print']) && (string)$_GET['server_print'] === '1';
    $serverPrintResult = null;
    if (($serverPrint || $autoPrint) && $printer->isEnabled()) {
        if (!unibagPrintGuard('otbox:' . (int)($ot['id'] ?? 0))) {
            // La misma etiqueta se envió hace pocos segundos (refresco de página o doble clic):
            // no se vuelve a imprimir.
            $serverPrintResult = ['ok' => true, 'duplicate' => true];
            $autoPrint = false;
        } else {
            $serverPrintResult = $printer->printWorkOrderBoxLabel($ot, $finish);
            if (($serverPrintResult['ok'] ?? false) === true || !empty($serverPrintResult['client_queued']) || !empty($serverPrintResult['ambiguous'])) {
                $autoPrint = false;
            }
        }
    }
    $barcodeValue = strtoupper(trim((string)$ot['ot_code']));
    $barcodeSvg = code128Svg($barcodeValue);

    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Etiqueta cajas</title>';
    echo '<style>
      *{box-sizing:border-box}
      body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;margin:0;background:#fff;color:#111}
      .wrap{padding:10px}
      .label{border:2px solid #111;border-radius:10px;padding:18px;max-width:700px}
      .brand{font-weight:900;letter-spacing:1px;font-size:22px}
      .kind{margin-top:6px;font-size:15px;font-weight:900}
      .product{margin-top:10px;font-size:30px;font-weight:900;line-height:1.15}
      .divider{height:2px;background:#111;margin:10px 0}
      .rows{display:grid;grid-template-columns:150px 1fr;gap:8px 12px}
      .k{font-size:13px;color:#111;font-weight:800}
      .v{font-size:18px;font-weight:900}
      .bottom{margin-top:12px;display:flex;flex-direction:column;align-items:center;justify-content:center}
      .barcode{margin:10px 0 6px}
      .idtxt{font-size:22px;font-weight:900;letter-spacing:.8px}
      @media print{
        body{margin:0}
        .wrap{padding:0}
        .label{border:none}
      }
    </style></head><body><div class="wrap"><div class="label">';

    if (is_array($serverPrintResult)) {
        if (($serverPrintResult['ok'] ?? false) === true || !empty($serverPrintResult['client_queued'])) {
            echo '<div style="background:#fff;border:1px solid #111;color:#111;padding:10px;border-radius:10px;margin-bottom:10px">Etiqueta enviada a la impresora.</div>';
        } else {
            echo '<div style="background:#fff;border:1px solid #111;color:#111;padding:10px;border-radius:10px;margin-bottom:10px">No se pudo imprimir: ' . h((string)($serverPrintResult['error'] ?? 'Error desconocido.')) . '</div>';
        }
    }

    echo '<div class="brand">UNIBAG</div>';
    echo '<div class="kind">CAJA PRODUCTO TERMINADO</div>';
    echo '<div class="product">' . h((string)$ot['sku_final']) . '</div>';
    echo '<div class="divider"></div>';
    echo '<div class="rows">';
    echo '<div class="k">OT</div><div class="v">' . h((string)$ot['ot_code']) . '</div>';
    echo '<div class="k">Fecha cierre</div><div class="v">' . h((string)($finish['created_at'] ?? '-')) . '</div>';
    echo '<div class="k">Operador</div><div class="v">' . h((string)($finish['operator_name'] ?? '-')) . '</div>';
    echo '<div class="k">Cantidad cajas</div><div class="v">' . h((string)($finish['box_qty'] ?? '-')) . '</div>';
    echo '</div>';
    echo '<div class="bottom">';
    if ($barcodeSvg !== '') {
        echo '<div class="barcode">' . $barcodeSvg . '</div>';
    }
    echo '<div class="idtxt">' . h($barcodeValue) . '</div>';
    echo '</div>';
    echo '</div></div>';
    if ($autoPrint) {
        echo '<script>
          (function(){
            function doPrint(){
              try { window.focus(); } catch(e) {}
              try { window.print(); } catch(e) {}
            }
            function schedule(){
              try { setTimeout(doPrint, 800); } catch(e) {}
            }
            if (document.readyState === "complete") schedule();
            else window.addEventListener("load", schedule);
          })();
        </script>';
    }
    echo '</body></html>';
    exit;
}

    return false;
}
