<?php
    $skuCode = trim((string)($data['sku_code'] ?? '-'));
    $skuDescription = trim((string)($data['sku_description'] ?? '-'));
    $arrivalDate = trim((string)($data['arrival_date'] ?? '-'));
    $warehouseCode = trim((string)($data['warehouse_code'] ?? '-'));
    $warehouseName = trim((string)($data['warehouse_name'] ?? ''));
    $weightStr = trim((string)($data['weight_str'] ?? '0.00 Kg'));
    $metersStr = trim((string)($data['meters_str'] ?? '-'));
    $specsStr = trim((string)($data['specs_str'] ?? '-'));
    $color = trim((string)($data['color'] ?? '-'));
    $poCode = trim((string)($data['po_code'] ?? '-'));
    $containerCode = trim((string)($data['container_code'] ?? '-'));
    $supplierName = trim((string)($data['supplier_name'] ?? '-'));
    $barcodeValue = trim((string)($data['barcode_value'] ?? ''));
    $stageBadge = trim((string)($data['stage_badge'] ?? 'MATERIA PRIMA'));
    $footerText = trim((string)($data['footer_text'] ?? 'CONTROL DE CALIDAD UNIBAG'));
    $isSample = !empty($data['is_sample']);
    $autoPrint = !empty($data['auto_print']);
    $downloadZplUrl = (string)($data['download_zpl_url'] ?? '');
    $rawZplUrl = (string)($data['raw_zpl_url'] ?? '');
    $serverPrintAllowed = !empty($data['server_print_allowed']);
    $serverPrintUrl = (string)($data['server_print_url'] ?? '');
    $serverPrintResult = $data['server_print_result'] ?? null;
    $printLanguage = strtolower(trim((string)($data['print_language'] ?? 'zpl')));
    if (!in_array($printLanguage, ['zpl', 'tspl', 'epl'], true)) {
        $printLanguage = 'zpl';
    }
    $commandLabel = strtoupper($printLanguage);
    $configuredPrinterName = trim((string)($data['printer_name'] ?? ''));
    $title = (string)($data['title'] ?? ('Etiqueta 10x7 - ' . $barcodeValue));

    // Código de barras vectorial Code 128 nítido y de 36px de alto optimizado para lectura instantánea con pistolas ópticas
    $barcodeSvg = code128Svg($barcodeValue, 36, 1.35);
    if ($barcodeSvg === '') {
        $barcodeSvg = code39Svg($barcodeValue, 36, 1.15, 3);
    }

    echo '<!doctype html><html lang="es"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($title) . '</title>';
    echo '<style id="page_rule">
      @page {
        size: 100mm 70mm;
        margin: 0 !important;
      }
    </style>';
    echo '<style>
      * {
        box-sizing: border-box;
      }
      html, body {
        margin: 0;
        padding: 0;
        background: #f3f4f6;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        color: #000;
        -webkit-font-smoothing: antialiased;
      }
      .no-print-toolbar {
        padding: 10px 16px;
        background: #fff;
        color: #000;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        font-size: 13px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.12);
        position: relative;
        z-index: 20;
        border-bottom: 1px solid #000;
      }
      .no-print-toolbar .actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
      }
      .btn-tb {
        border-radius: 6px;
        padding: 7px 14px;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid transparent;
        transition: all 0.15s ease;
      }
      .btn-tb-print {
        background: #000;
        color: #fff;
        border-color: #000;
        font-size: 14px;
        font-weight: 900;
        padding: 8px 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.16);
      }
      .btn-tb-print:hover {
        background: #111;
      }
      .btn-tb-sec {
        background: #fff;
        color: #000;
        border-color: #000;
      }
      .btn-tb-sec:hover {
        background: #f3f4f6;
      }
      .btn-height-preset {
        padding: 5px 9px;
        font-size: 11.5px;
        font-weight: 800;
        background: #fff;
        color: #111;
        border: 1px solid #000;
        border-radius: 4px;
        cursor: pointer;
      }
      .btn-height-preset:hover {
        background: #f3f4f6;
        color: #000;
      }
      .btn-height-preset.active {
        background: #000;
        color: #fff;
        border-color: #000;
        font-weight: 900;
      }
      .scale-badge {
        background: #fff;
        border: 1px solid #000;
        color: #111;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
      }
      .scale-badge strong {
        color: #000;
      }
      .print-guide-banner {
        background: #fff;
        border-bottom: 2px solid #000;
        padding: 10px 18px;
        font-size: 12.5px;
        color: #111;
        display: flex;
        flex-direction: column;
        gap: 4px;
      }
      .print-guide-banner .guide-steps {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        align-items: center;
      }
      .guide-step {
        display: inline-flex;
        align-items: center;
        gap: 6px;
      }
      .guide-num {
        background: #000;
        color: #fff;
        width: 19px;
        height: 19px;
        border-radius: 50%;
        font-size: 11px;
        font-weight: 900;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
      }
      .preview-stage {
        padding: 24px;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: calc(100vh - 140px);
        background: #f3f4f6;
      }
      .alert-box {
        margin: 10px 24px 0;
        padding: 10px 14px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
      }
      .alert-ok { background: #fff; border: 1px solid #000; color: #111; }
      .alert-err { background: #fff; border: 1px solid #000; color: #111; }

      /* Envoltorio de la tarjeta en pantalla */
      .label-rot-wrap {
        width: 100mm;
        height: 70mm;
        display: block;
        position: relative;
      }

      /* Contenedor exacto de la tarjeta de etiqueta: 100mm x 70mm */
      .label-card {
        background: #fff;
        border: 1.5pt solid #000;
        border-radius: 3px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        box-sizing: border-box;
        overflow: hidden;
        page-break-inside: avoid;
        page-break-after: avoid;
        break-inside: avoid;
        width: 100mm;
        height: 70mm;
        max-width: 100mm;
        max-height: 70mm;
        padding: 1.8mm 2.5mm;
      }

      /* ========================================================
         ELEMENTOS INTERNOS COMPACTOS (CABEN EN 54 MM)
         ======================================================== */
      .header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1.2pt solid #000;
        padding-bottom: 0.6mm;
        line-height: 1;
      }
      .brand-title {
        font-size: 12pt;
        font-weight: 900;
        letter-spacing: 0.4px;
      }
      .sub-title {
        font-size: 6pt;
        font-weight: 800;
        letter-spacing: 0.3px;
        text-transform: uppercase;
        margin-top: 1px;
      }
      .header-meta {
        text-align: right;
        line-height: 1.15;
      }
      .wh-badge {
        background: #000;
        color: #fff;
        font-weight: 900;
        font-size: 7.5pt;
        padding: 1px 5px;
        border-radius: 2px;
        display: inline-block;
        letter-spacing: 0.3px;
      }
      .date-str {
        font-size: 6.2pt;
        font-weight: 800;
        margin-top: 1px;
      }
      .sku-box {
        border-bottom: 1pt solid #000;
        padding-bottom: 0.6mm;
        margin-top: 0.4mm;
      }
      .sku-header {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
      }
      .sku-label {
        font-size: 7pt;
        font-weight: 800;
        text-transform: uppercase;
      }
      .sku-val {
        font-size: 14pt;
        font-weight: 900;
        letter-spacing: 0.5px;
      }
      .stage-tag {
        font-size: 6.5pt;
        font-weight: 800;
        border: 0.8pt solid #000;
        padding: 1px 4px;
        border-radius: 2px;
        text-transform: uppercase;
      }
      .sku-desc {
        font-size: 7.2pt;
        font-weight: 700;
        line-height: 1.15;
        margin-top: 1px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .grid-specs {
        display: grid;
        grid-template-columns: 1.35fr 1fr 1.25fr 0.95fr;
        gap: 1.5mm;
        border-bottom: 1pt solid #000;
        padding: 0.6mm 0;
        margin-top: 0.2mm;
      }
      .spec-col {
        border-right: 0.8pt solid #bbb;
        padding-right: 1mm;
      }
      .spec-col:last-child {
        border-right: none;
        padding-right: 0;
      }
      .spec-k {
        font-size: 5.5pt;
        font-weight: 800;
        text-transform: uppercase;
        color: #222;
        line-height: 1;
      }
      .spec-v {
        font-size: 8pt;
        font-weight: 900;
        line-height: 1.1;
        margin-top: 1px;
      }
      .spec-v-lg {
        font-size: 10.5pt;
        font-weight: 900;
      }
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
      .trace-item {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
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
      .barcode-svg-wrap svg {
        max-width: 100%;
        height: 100%;
        shape-rendering: crispEdges;
      }
      .code-text {
        font-family: "Consolas", "Courier New", monospace;
        font-size: 8.5pt;
        font-weight: 900;
        letter-spacing: 1.4px;
        line-height: 1.1;
        margin-top: 1px;
      }
      .footer-text {
        font-size: 5pt;
        font-weight: 800;
        color: #222;
        letter-spacing: 0.2px;
        margin-top: 1px;
      }

      /* ========================================================
         REGLAS DE IMPRESIÓN: ESTRICTAMENTE 1 SOLA ETIQUETA
         ======================================================== */
      @media print {
        @page {
          size: 100mm 70mm;
          margin: 0 !important;
        }
        html, body {
          background: #fff !important;
          margin: 0 !important;
          padding: 0 !important;
          width: 100mm !important;
          height: 70mm !important;
          max-width: 100mm !important;
          max-height: 70mm !important;
          overflow: hidden !important;
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
          page-break-inside: avoid !important;
          page-break-after: avoid !important;
          page-break-before: avoid !important;
          break-inside: avoid !important;
        }
        .no-print, .no-print-toolbar, .print-guide-banner, .alert-box {
          display: none !important;
        }
        .preview-stage {
          padding: 0 !important;
          margin: 0 !important;
          width: 100mm !important;
          height: 70mm !important;
          max-width: 100mm !important;
          max-height: 70mm !important;
          display: block !important;
          background: none !important;
          min-height: 0 !important;
          overflow: hidden !important;
        }
        .label-rot-wrap {
          width: 100mm !important;
          height: 70mm !important;
          max-width: 100mm !important;
          max-height: 70mm !important;
          margin: 0 !important;
          padding: 0 !important;
          overflow: hidden !important;
        }
        .label-card {
          width: 100mm !important;
          height: 70mm !important;
          max-width: 100mm !important;
          max-height: 70mm !important;
          box-sizing: border-box !important;
          border: 1.5pt solid #000 !important;
          box-shadow: none !important;
          padding: 1.8mm 2.5mm !important;
          margin: 0 !important;
          overflow: hidden !important;
          page-break-inside: avoid !important;
          page-break-after: avoid !important;
          break-inside: avoid !important;
          display: flex !important;
          flex-direction: column !important;
          justify-content: space-between !important;
        }
      }
    </style></head><body>';

    // Barra de herramientas superior
    echo '<div class="no-print-toolbar">';
    echo '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">';
    $headerPrinterDisplay = $configuredPrinterName !== '' ? $configuredPrinterName : 'Impresora Térmica';
    echo '<span style="font-weight:900;font-size:15px;color:#000">🖨️ ' . h($headerPrinterDisplay) . '</span>';
    echo '<div class="scale-badge">Formato: <strong>100 × 70 mm exactos</strong></div>';
    echo '</div>';

    echo '<div class="actions">';
    if ($serverPrintAllowed && $serverPrintUrl !== '') {
        echo '<a class="btn-tb btn-tb-print" href="' . h($serverPrintUrl) . '" title="Enviar etiqueta por comando térmico directo">⚡ Imprimir Térmica 10×7</a>';
        echo '<button type="button" class="btn-tb btn-tb-sec" onclick="window.print()">🖨️ Respaldo navegador</button>';
    } else {
        echo '<button type="button" class="btn-tb btn-tb-print" onclick="window.print()">🖨️ Imprimir Directo 10×7</button>';
    }

    echo '<div style="display:flex;align-items:center;gap:4px;background:#fff;padding:3px 8px;border-radius:6px;border:1px solid #000">';
    echo '<span style="font-size:11px;color:#111;font-weight:800">Modo:</span>';
    echo '<span style="font-size:11px;color:#000;font-weight:900">Etiqueta completa 100 × 70 mm</span>';
    echo '</div>';

    if ($downloadZplUrl !== '') {
        echo '<a class="btn-tb btn-tb-sec" href="' . h($downloadZplUrl) . '" download title="Descarga comando nativo 100x70mm para la impresora configurada">⬇️ ' . h($commandLabel) . ' 100x70</a>';
    }
    if ($rawZplUrl !== '') {
        echo '<a class="btn-tb btn-tb-sec" href="' . h($rawZplUrl) . '" target="_blank">📄 Ver ' . h($commandLabel) . '</a>';
    }
    echo '<button type="button" class="btn-tb btn-tb-sec" onclick="toggleGuide()">❓ Ayuda</button>';
    echo '</div>';
    echo '</div>';

    // Banner de Ayuda Directa
    echo '<div class="print-guide-banner" id="print_guide">';
    echo '<div class="guide-steps">';
    echo '<div style="font-weight:900;display:flex;align-items:center;gap:6px"><span>✅ CONFIGURACIÓN EXACTA PARA 1 SOLA ETIQUETA:</span></div>';
    echo '<div class="guide-step"><span class="guide-num">1</span> Esta vista imprime la etiqueta usando el tamaño real completo de <strong>100 × 70 mm</strong>.</div>';
    echo '<div class="guide-step"><span class="guide-num">2</span> <strong>En Chrome o Edge (Ctrl+P):</strong> usa <strong>Márgenes: Ninguno</strong> y <strong>Escala: 100%</strong>.</div>';
    echo '</div>';
    echo '</div>';

    if (is_array($serverPrintResult)) {
        if (($serverPrintResult['ok'] ?? false) === true) {
            echo '<div class="alert-box alert-ok">✅ Etiqueta enviada con éxito a la impresora ' . h($headerPrinterDisplay) . '.</div>';
        } elseif (!empty($serverPrintResult['client_queued'])) {
            echo '<div id="client-print-banner" class="alert-box alert-ok">⚡ Enviando etiqueta a tu impresora térmica (' . h($headerPrinterDisplay) . ')...</div>';
        } else {
            echo '<div class="alert-box alert-err">❌ Error al enviar a impresora: ' . h((string)($serverPrintResult['error'] ?? 'Error desconocido')) . '</div>';
        }
    } elseif (!$serverPrintAllowed) {
        echo '<div class="alert-box alert-err">⚠️ La impresión directa está deshabilitada (PRINT_MODE=none). Puedes usar el botón "Imprimir Navegador".</div>';
    }

    echo '<div class="preview-stage">';
    echo '<div class="label-rot-wrap" id="label_rot_wrap">';
    echo '<div class="label-card" id="label_card">';

    // 1. Encabezado UNIBAG + Bodega y Fecha
    echo '<div class="header-row">';
    echo '<div>';
    echo '<div class="brand-title">UNIBAG</div>';
    echo '<div class="sub-title">RECEPCIÓN MATERIA PRIMA</div>';
    echo '</div>';
    echo '<div class="header-meta">';
    echo '<div class="wh-badge">BOD: ' . h($warehouseCode) . '</div>';
    echo '<div class="date-str">FECHA: ' . h($arrivalDate) . '</div>';
    echo '</div>';
    echo '</div>';

    // 2. SKU y Descripción (1 línea para no ocupar altura)
    echo '<div class="sku-box">';
    echo '<div class="sku-header">';
    echo '<div><span class="sku-label">SKU: </span><span class="sku-val">' . h($skuCode) . '</span></div>';
    echo '<div class="stage-tag">' . h($stageBadge) . '</div>';
    echo '</div>';
    echo '<div class="sku-desc" title="' . h($skuDescription) . '">' . h($skuDescription) . '</div>';
    echo '</div>';

    // 3. Cuadrícula de Especificaciones Técnicas y Peso (4 columnas alineadas)
    echo '<div class="grid-specs">';
    echo '<div class="spec-col"><div class="spec-k">Peso Real</div><div class="spec-v spec-v-lg">' . h($weightStr) . '</div></div>';
    echo '<div class="spec-col"><div class="spec-k">Metros</div><div class="spec-v">' . h($metersStr) . '</div></div>';
    echo '<div class="spec-col"><div class="spec-k">Ancho / Gr</div><div class="spec-v">' . h($specsStr) . '</div></div>';
    echo '<div class="spec-col"><div class="spec-k">Color</div><div class="spec-v">' . h($color) . '</div></div>';
    echo '</div>';

    // 4. Datos de Compra y Proveedor (Trazabilidad en 1 fila)
    echo '<div class="trace-row">';
    echo '<div class="trace-item"><span>OC: </span><b>' . h($poCode) . '</b></div>';
    echo '<div class="trace-item"><span>CONT: </span><b>' . h($containerCode) . '</b></div>';
    echo '<div class="trace-item" style="max-width:40%"><span>PROV: </span><b>' . h($supplierName) . '</b></div>';
    echo '</div>';

    // 5. Código de Barras y Código de Bobina
    echo '<div class="barcode-section">';
    if ($barcodeSvg !== '') {
        echo '<div class="barcode-svg-wrap">' . $barcodeSvg . '</div>';
    }
    echo '<div class="code-text">' . h($barcodeValue) . '</div>';
    echo '<div class="footer-text">' . h($footerText) . '</div>';
    echo '</div>';

    echo '</div>'; // .label-card
    echo '</div>'; // .label-rot-wrap
    echo '</div>'; // .preview-stage

    echo '<script>
      (function(){
        var wrap = document.getElementById("label_rot_wrap");
        var card = document.getElementById("label_card");
        if (wrap) {
          wrap.style.width = "100mm";
          wrap.style.height = "70mm";
        }
        if (card) {
          card.style.width = "100mm";
          card.style.height = "70mm";
          card.style.maxWidth = "100mm";
          card.style.maxHeight = "70mm";
        }

        window.toggleGuide = function() {
          var el = document.getElementById("print_guide");
          if (el) el.style.display = (el.style.display === "none" || !el.style.display) ? "flex" : "none";
        };

        ' . ($autoPrint ? '
        function doPrint(){
          try { window.focus(); } catch(e) {}
          try { window.print(); } catch(e) {}
        }
        setTimeout(doPrint, 500);
        ' : '') . '
      })();
    </script>';
    echo '</body></html>';

