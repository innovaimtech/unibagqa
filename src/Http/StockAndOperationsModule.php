<?php

declare(strict_types=1);

require_once __DIR__ . '/StockRequestsView.php';

// =============================================================================
// Módulo HTTP · Solicitudes de Material, Picking Móvil, Traspasos, Corte, Cajas, Pallets y Maquila
// =============================================================================

function handleStockAndOperationsRoutes(string $path, string $method, ReceptionService $service, PrintService $printer, string $currentOperatorName): bool
{
if ($path === '/stock/material-requests' && $method === 'GET') {
    $statusView = strtolower(trim((string)($_GET['status_view'] ?? 'all')));
    $allRequests = $service->listAllMaterialRequests(300);
    $statusCounters = [
        'all' => count($allRequests),
        'open' => 0,
        'pending' => 0,
        'delivered' => 0,
    ];
    foreach ($allRequests as $request) {
        $statusValue = strtoupper(trim((string)($request['status'] ?? '')));
        if (in_array($statusValue, ['PENDING', 'ACCEPTED', 'PARTIAL'], true)) {
            $statusCounters['open']++;
        }
        if ($statusValue === 'PENDING') {
            $statusCounters['pending']++;
        }
        if ($statusValue === 'DELIVERED') {
            $statusCounters['delivered']++;
        }
    }
    $requests = array_values(array_filter($allRequests, static function (array $request) use ($statusView): bool {
        $statusValue = strtoupper(trim((string)($request['status'] ?? '')));
        return match ($statusView) {
            'open' => in_array($statusValue, ['PENDING', 'ACCEPTED', 'PARTIAL'], true),
            'pending' => $statusValue === 'PENDING',
            'delivered' => $statusValue === 'DELIVERED',
            default => true,
        };
    }));

    $statusTabs = [
        'all' => 'Todas',
        'open' => 'En curso',
        'pending' => 'Sin tomar',
        'delivered' => 'Entregadas',
    ];
    $body = mrStyles() . '<div class="mr"><div class="mr-card mr-pad">
        <div class="mr-top"><div><h1>Solicitudes de materiales</h1></div>
        <div class="mr-links"><a class="mr-link" href="/mobile/picking">📱 Vista móvil</a><a class="mr-link" href="/stock">← Inventario</a></div></div>
        <div class="mr-tabs">';
    foreach ($statusTabs as $tabKey => $tabLabel) {
        $body .= '<a class="mr-tab' . ($statusView === $tabKey ? ' active' : '') . '" href="' . h(withQuery('/stock/material-requests', ['status_view' => $tabKey])) . '"><b>' . (int)($statusCounters[$tabKey] ?? 0) . '</b><span>' . h($tabLabel) . '</span></a>';
    }
    $body .= '</div></div>';

    // Pedidos que hacen los operadores desde su módulo (production_supply_requests): se atienden en /stock/material-supply.
    $operatorRows = [];
    try {
        require_once __DIR__ . '/../MaterialSupplyService.php';
        $operatorRows = array_values(array_filter(
            (new MaterialSupplyService(Db::erpPdo(), Db::trzPdo()))->requests(),
            static fn(array $r): bool => (int)($r['source_storehouse_id'] ?? 0) !== 16
                && in_array((string)($r['status'] ?? ''), ['PENDING', 'ACCEPTED', 'DISPATCHED'], true)
        ));
    } catch (Throwable $e) {
        error_log('Solicitudes de operadores: ' . $e->getMessage());
    }
    if ($operatorRows !== []) {
        $opLabels = ['PENDING' => 'Sin tomar', 'ACCEPTED' => 'En preparación', 'DISPATCHED' => 'Por recibir'];
        $body .= '<div class="mr-card tbl"><div class="mr-search"><b>Pedidos de operadores a Bodega (' . count($operatorRows) . ')</b></div>
            <table class="mr-table mr-op"><thead><tr><th>Material solicitado</th><th>Máquina / OT</th><th>Cantidad</th><th>Estado</th><th>Fecha</th><th></th></tr></thead><tbody>';
        foreach ($operatorRows as $r) {
            $ctx = json_decode((string)($r['context'] ?? ''), true) ?: [];
            $actor = json_decode((string)($r['requested_by'] ?? ''), true) ?: [];
            $machine = (string)($ctx['machine_name'] ?? ('Máquina #' . (int)($ctx['win_equipoid'] ?? 0)));
            $ot = (string)($ctx['planned_ot_number'] ?? '');
            $unit = (string)($r['unit'] ?? 'Unid.');
            $st = (string)$r['status'];
            $body .= '<tr><td class="main"><div class="mr-item">' . h((string)($r['description'] ?? $r['sku'] ?? '')) . '</div><div class="mr-muted">' . h((string)($r['sku'] ?? '')) . ' · Solicita: ' . h((string)($actor['name'] ?? '-')) . '</div></td>';
            $body .= '<td data-label="Máquina / OT"><b>' . h($machine) . '</b>' . ($ot !== '' ? '<div class="mr-muted">OT ' . h($ot) . '</div>' : '') . '</td>';
            $body .= '<td data-label="Cantidad">' . mrProgress((float)($r['requested_qty'] ?? 0), (float)($r['delivered_qty'] ?? 0), $unit) . '</td>';
            $body .= '<td data-label="Estado"><span class="mr-badge ' . ($st === 'PENDING' ? 'pend' : ($st === 'ACCEPTED' ? 'acc' : 'part')) . '">' . h($opLabels[$st] ?? $st) . '</span></td>';
            $body .= '<td data-label="Fecha">' . h(mrDate((string)($r['created_at'] ?? ''))) . '</td>';
            $body .= '<td class="act"><a class="mr-btn" href="/stock/material-supply?open=' . h((string)$r['request_key']) . '">' . ($st === 'DISPATCHED' ? 'Ver' : 'Atender') . '</a></td></tr>';
        }
        $body .= '</tbody></table></div>';
    }

    $body .= '<div class="mr-card tbl"><div class="mr-search"><input type="search" id="mr-search" placeholder="Buscar por material, OT o quién solicita…" aria-label="Buscar solicitudes"><small id="mr-count">' . count($requests) . ' solicitudes</small></div>
        <table class="mr-table"><thead><tr><th>Material solicitado</th><th>OT</th><th>Avance</th><th>Estado</th><th>Fecha</th><th>Última entrega</th><th></th></tr></thead><tbody>';
    foreach ($requests as $request) {
        $lastDelivered = trim((string)($request['delivered_roll_code'] ?? ''));
        $requestOperational = workOrderCanReceiveMaterials((string)($request['work_order_status'] ?? ''));
        $unit = (string)($request['requested_unit'] ?? 'Unid.');
        $meters = (float)($request['requested_meters'] ?? 0) > 0
            ? rtrim(rtrim(number_format((float)$request['requested_meters'], 3, '.', ''), '0'), '.') . ' ML'
            : '';
        $search = mb_strtolower((string)$request['requested_item'] . ' ' . (string)$request['ot_code'] . ' ' . (string)$request['requested_by'], 'UTF-8');
        $body .= '<tr data-s="' . h($search) . '">';
        $body .= '<td class="main"><div class="mr-item">' . h((string)$request['requested_item']) . '</div><div class="mr-muted">Solicita: ' . h((string)$request['requested_by']) . ($meters !== '' ? ' · ' . h($meters) : '') . '</div></td>';
        $body .= '<td data-label="OT"><a class="mr-ot" href="/work-orders/' . (int)$request['work_order_id'] . '/start">' . h((string)$request['ot_code']) . '</a></td>';
        $body .= '<td data-label="Avance">' . mrProgress((float)($request['requested_qty'] ?? 0), (float)($request['delivered_qty'] ?? 0), $unit) . '</td>';
        $body .= '<td data-label="Estado"><div>' . mrBadge((string)$request['status']) . ($requestOperational || strtoupper((string)$request['status']) === 'DELIVERED' ? '' : '<div class="mr-warn">OT no operativa</div>') . '</div></td>';
        $body .= '<td data-label="Fecha">' . h(mrDate((string)$request['created_at'])) . '</td>';
        $body .= '<td data-label="Última entrega">' . ($lastDelivered !== '' ? '<div><b>' . h($lastDelivered) . '</b><div class="mr-muted">' . h((string)($request['delivered_by'] ?? '-')) . '</div></div>' : '<span class="mr-muted">-</span>') . '</td>';
        $body .= '<td class="act"><a class="mr-btn' . ($requestOperational ? '' : ' sec') . '" href="/stock/material-requests/' . (int)$request['id'] . '">' . ($requestOperational ? 'Atender' : 'Ver') . '</a></td>';
        $body .= '</tr>';
    }
    $body .= '</tbody></table>';
    if ($requests === []) {
        $body .= '<div class="mr-empty">No hay solicitudes en este estado.</div>';
    }
    $body .= '</div></div><script>(function(){var i=document.getElementById("mr-search"),c=document.getElementById("mr-count");if(!i)return;i.addEventListener("input",function(){var q=i.value.trim().toLowerCase(),n=0;document.querySelectorAll(".mr-table:not(.mr-op) tbody tr").forEach(function(r){var ok=!q||(r.dataset.s||"").indexOf(q)>-1;r.style.display=ok?"":"none";if(ok)n++;});c.textContent=n+" solicitudes";});})();</script>';

    render('Solicitudes', $body);
    exit;
}

if ($path === '/mobile/picking' && $method === 'GET') {
    $requests = array_values(array_filter(
        $service->listAllMaterialRequests(200),
        static fn(array $request): bool => in_array((string)($request['status'] ?? ''), ['PENDING', 'ACCEPTED', 'PARTIAL'], true)
            && workOrderCanReceiveMaterials((string)($request['work_order_status'] ?? ''))
    ));

    $body = mrStyles() . '<div class="mr">' . mrPageHeader('Picking móvil', count($requests) . ' por atender', '/stock/material-requests', 'Vista escritorio');
    foreach ($requests as $request) {
        $unit = (string)($request['requested_unit'] ?? 'Unid.');
        $body .= '<div class="mr-card mr-pad"><div class="mr-top" style="flex-wrap:nowrap"><div style="min-width:0"><div class="mr-item" style="font-size:16px">' . h((string)$request['requested_item']) . '</div>
            <div class="mr-muted">OT <b>' . h((string)$request['ot_code']) . '</b> · ' . h((string)$request['requested_by']) . '</div></div>' . mrBadge((string)$request['status']) . '</div>
          <div style="margin:14px 0">' . mrProgress((float)($request['requested_qty'] ?? 0), (float)($request['delivered_qty'] ?? 0), $unit) . '</div>
          <div class="mr-muted" style="margin:0 0 12px">' . h(materialRequestTypeLabel((string)($request['request_type'] ?? 'ROLL'))) . ' · ' . h(mrDate((string)$request['created_at'])) . '</div>
          <a class="mr-btn big block" href="/mobile/picking/' . (int)$request['id'] . '">Abrir solicitud</a></div>';
    }
    if ($requests === []) {
        $body .= '<div class="mr-card"><div class="mr-empty">No hay solicitudes abiertas para picking en este momento.</div></div>';
    }
    $body .= '</div>';

    render('Picking móvil', $body);
    exit;
}

if (preg_match('#^/stock/material-requests/(\d+)/accept$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $requestId = (int)$m[1];
    $returnMode = (string)($_POST['return_mode'] ?? '') === 'mobile' ? 'mobile' : 'default';
    $result = $service->acceptMaterialRequest($requestId, $currentOperatorName);
    $query = (($result['ok'] ?? false) === true)
        ? '?ok=' . rawurlencode('Solicitud aceptada por bodega.')
        : '?error=' . rawurlencode((string)reset($result['errors']));
    $redirectBase = $returnMode === 'mobile'
        ? '/mobile/picking/' . $requestId
        : '/stock/material-requests/' . $requestId;
    header('Location: ' . $redirectBase . $query);
    exit;
}

if (preg_match('#^/stock/material-requests/(\d+)/scan$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $requestId = (int)$m[1];
    $returnMode = (string)($_POST['return_mode'] ?? '') === 'mobile' ? 'mobile' : 'default';
    $redirectBase = $returnMode === 'mobile'
        ? '/mobile/picking/' . $requestId
        : '/stock/material-requests/' . $requestId;
    $rawInputs = [];
    if (isset($_POST['scan_codes']) && is_array($_POST['scan_codes'])) {
        $rawInputs = array_merge($rawInputs, $_POST['scan_codes']);
    }
    if (isset($_POST['scan_code']) && is_string($_POST['scan_code']) && trim($_POST['scan_code']) !== '') {
        $rawInputs[] = $_POST['scan_code'];
    }
    $codes = [];
    foreach ($rawInputs as $rawItem) {
        $clean = str_replace(["'", '`', '´', '’', '‘', '‛'], '-', (string)$rawItem);
        $clean = preg_replace('/([A-Za-z0-9])((?:RB|CJ|BT|TRZ)-\d)/i', '$1 $2', $clean) ?? $clean;
        foreach (preg_split('/[\s,;]+/', trim($clean)) ?: [] as $part) {
            $part = trim($part);
            if ($part !== '' && !in_array($part, $codes, true)) {
                $codes[] = $part;
            }
        }
    }
    if ($codes === []) {
        header('Location: ' . $redirectBase . '?error=' . rawurlencode('Debes escanear o escribir al menos un código de trazabilidad en las casillas.'));
        exit;
    }

    $reqData = $service->getMaterialRequest($requestId);
    $otLabel = trim((string)($reqData['ot_code'] ?? ''));
    $deliveredCodes = [];
    $errors = [];

    foreach ($codes as $code) {
        $roll = $service->getRollByScanCode($code);
        if ($roll === null) {
            $errors[] = $code . ': No se encontró una bobina con ese código.';
            continue;
        }
        $result = $service->deliverMaterialRequest($requestId, (int)$roll['id'], $currentOperatorName);
        if (($result['ok'] ?? false) === true) {
            $deliveredCodes[] = trim((string)($roll['roll_code'] ?? ('#' . $roll['id'])));
        } else {
            $errMsg = !empty($result['errors']) ? (string)reset($result['errors']) : 'No se pudo entregar la bobina.';
            $errors[] = $code . ': ' . $errMsg;
        }
    }

    $params = [];
    if ($deliveredCodes !== []) {
        $count = count($deliveredCodes);
        $listStr = implode(', ', $deliveredCodes);
        $params['ok'] = $count === 1
            ? ('✅ Bobina ' . $listStr . ($otLabel !== '' ? (' entregada a OT ' . $otLabel) : '') . ' y trasladada exitosamente a 3000 Insumos en Producción.')
            : ('✅ ' . $count . ' bobinas (' . $listStr . ')' . ($otLabel !== '' ? (' entregadas a OT ' . $otLabel) : '') . ' y trasladadas exitosamente a 3000 Insumos en Producción.');
    }
    if ($errors !== []) {
        $params['error'] = implode(' | ', $errors);
    }
    header('Location: ' . $redirectBase . ($params !== [] ? ('?' . http_build_query($params)) : ''));
    exit;
}

if (preg_match('#^/stock/material-requests/(\d+)/deliver-manual$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $requestId = (int)$m[1];
    $returnMode = (string)($_POST['return_mode'] ?? '') === 'mobile' ? 'mobile' : 'default';
    $result = $service->deliverGenericMaterialRequest(
        $requestId,
        (float)($_POST['delivered_qty'] ?? 0),
        (string)($_POST['delivery_note'] ?? ''),
        $currentOperatorName
    );
    $query = (($result['ok'] ?? false) === true)
        ? '?ok=' . rawurlencode('Material entregado correctamente.')
        : '?error=' . rawurlencode((string)reset($result['errors']));
    $redirectBase = $returnMode === 'mobile'
        ? '/mobile/picking/' . $requestId
        : '/stock/material-requests/' . $requestId;
    header('Location: ' . $redirectBase . $query);
    exit;
}

if (preg_match('#^/mobile/picking/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $requestId = (int)$m[1];
    $request = $service->getMaterialRequest($requestId);
    if ($request === null) {
        render('No encontrado', '<div class="card">No existe la solicitud.</div>');
        exit;
    }

    $deliveries = $service->listMaterialDeliveriesByRequest($requestId);
    $flashOk = trim((string)($_GET['ok'] ?? ''));
    $flashError = trim((string)($_GET['error'] ?? ''));
    $requestedQty = (float)($request['requested_qty'] ?? 0);
    $deliveredQty = (float)($request['delivered_qty'] ?? 0);
    $pendingQty = max(0, $requestedQty - $deliveredQty);
    $requestOperational = workOrderCanReceiveMaterials((string)($request['work_order_status'] ?? ''));

    $body = mrStyles() . '<div class="mr">' . mrPageHeader('Picking móvil', '','/mobile/picking', '← Volver')
        . mrAlerts($flashOk, $flashError, $requestOperational)
        . mrDetailTop($request, $requestOperational, $requestedQty, $deliveredQty, $pendingQty);
    if ($requestOperational && (string)$request['status'] === 'PENDING') {
        $body .= mrAcceptCard($requestId, true);
    }

    if ($requestOperational && $pendingQty > 0 && in_array((string)$request['status'], ['ACCEPTED', 'PARTIAL'], true) && (string)($request['request_type'] ?? 'ROLL') === 'ROLL') {
        $body .= mrScanCards($requestId, true, $pendingQty);

        $body .= '<script>
          (function () {
            var form = document.querySelector(\'form[action="/stock/material-requests/' . $requestId . '/scan"]\');
            var input = document.getElementById("request_scan_code");
            var startButton = document.getElementById("camera_scan_start");
            var stopButton = document.getElementById("camera_scan_stop");
            var captureButton = document.getElementById("camera_scan_capture");
            var fileInput = document.getElementById("camera_scan_file");
            var video = document.getElementById("camera_scan_video");
            var statusBox = document.getElementById("camera_scan_status");
            if (!form || !input || !startButton || !stopButton || !captureButton || !fileInput || !video || !statusBox) {
              return;
            }

            var stream = null;
            var detector = null;
            var scanTimer = null;
            var isRunning = false;
            var isSubmitting = false;

            function setStatus(message, isError) {
              statusBox.textContent = message;
              statusBox.style.color = isError ? "#b42318" : "#475467";
            }

            function cleanupCamera() {
              if (scanTimer !== null) {
                window.clearTimeout(scanTimer);
                scanTimer = null;
              }
              if (stream) {
                stream.getTracks().forEach(function (track) { track.stop(); });
                stream = null;
              }
              video.srcObject = null;
              isRunning = false;
              startButton.disabled = false;
              stopButton.disabled = true;
            }

            function insecureContextMessage() {
              if (window.isSecureContext) {
                return "";
              }
              if (location.protocol === "http:" && location.hostname !== "localhost" && location.hostname !== "127.0.0.1") {
                return "La cámara en vivo está bloqueada porque abriste el sistema por HTTP en red local. En celular/tablet esto normalmente requiere HTTPS.";
              }
              return "";
            }

            async function buildDetector() {
              if (!("BarcodeDetector" in window)) {
                return null;
              }
              try {
                var supported = typeof BarcodeDetector.getSupportedFormats === "function"
                  ? await BarcodeDetector.getSupportedFormats()
                  : [];
                var preferred = ["code_39", "code_128", "ean_13", "ean_8", "upc_a", "upc_e"];
                var formats = preferred.filter(function (item) { return supported.indexOf(item) !== -1; });
                return formats.length > 0 ? new BarcodeDetector({ formats: formats }) : new BarcodeDetector();
              } catch (error) {
                return new BarcodeDetector();
              }
            }

            async function scanLoop() {
              if (!isRunning || !detector || isSubmitting) {
                return;
              }
              try {
                var codes = await detector.detect(video);
                if (codes && codes.length > 0) {
                  var rawValue = (codes[0].rawValue || "").trim();
                  if (rawValue !== "") {
                    if (typeof window.mrAddScannedCode === "function") {
                      window.mrAddScannedCode(rawValue);
                      setStatus("✓ Código agregado a casilla: " + rawValue + ". Puedes escanear otro o pulsar el botón de carga.", false);
                      scanTimer = window.setTimeout(scanLoop, 1200);
                      return;
                    }
                    input.value = rawValue;
                    isSubmitting = true;
                    setStatus("Código detectado. Ingresando bobina...", false);
                    cleanupCamera();
                    form.submit();
                    return;
                  }
                }
              } catch (error) {
                setStatus("No se pudo leer el código todavía. Mantén la cámara enfocando la etiqueta.", false);
              }
              scanTimer = window.setTimeout(scanLoop, 250);
            }

            async function detectFromFile(file) {
              detector = await buildDetector();
              if (!detector) {
                setStatus("Este navegador no soporta leer códigos desde cámara o foto. Usa Chrome o Edge actualizado.", true);
                return;
              }
              try {
                var bitmap = await createImageBitmap(file);
                var codes = await detector.detect(bitmap);
                if (bitmap && typeof bitmap.close === "function") {
                  bitmap.close();
                }
                if (codes && codes.length > 0) {
                  var rawValue = (codes[0].rawValue || "").trim();
                  if (rawValue !== "") {
                    if (typeof window.mrAddScannedCode === "function") {
                      window.mrAddScannedCode(rawValue);
                      setStatus("✓ Código detectado desde la foto y agregado a casilla: " + rawValue, false);
                      return;
                    }
                    input.value = rawValue;
                    isSubmitting = true;
                    setStatus("Código detectado desde la foto. Ingresando bobina...", false);
                    form.submit();
                    return;
                  }
                }
                setStatus("No se pudo leer el código desde la foto. Acércate más a la etiqueta o mejora la luz.", true);
              } catch (error) {
                setStatus("No se pudo procesar la foto del código. Intenta nuevamente.", true);
              }
            }

            async function startCamera() {
              if (isRunning) {
                return;
              }
              if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                var reason = insecureContextMessage();
                setStatus(reason !== "" ? reason + " Usa \"Tomar foto\" o el campo manual." : "Este navegador no permite abrir la cámara. Usa \"Tomar foto\", el campo manual o un lector externo.", true);
                return;
              }
              detector = await buildDetector();
              if (!detector) {
                setStatus("Este navegador no soporta detección de código con cámara. Usa Chrome o Edge en el dispositivo, o prueba con \"Tomar foto\".", true);
                return;
              }

              try {
                stream = await navigator.mediaDevices.getUserMedia({
                  video: {
                    facingMode: { ideal: "environment" },
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                  },
                  audio: false
                });
                video.srcObject = stream;
                await video.play();
                isRunning = true;
                startButton.disabled = true;
                stopButton.disabled = false;
                setStatus("Cámara activa. Apunta al código de barras de la bobina.", false);
                scanLoop();
              } catch (error) {
                cleanupCamera();
                var contextMessage = insecureContextMessage();
                setStatus(contextMessage !== "" ? contextMessage + " También puedes usar \"Tomar foto\"." : "No se pudo abrir la cámara. Revisa permisos del navegador y usa HTTPS o localhost.", true);
              }
            }

            startButton.addEventListener("click", function () {
              startCamera();
            });

            stopButton.addEventListener("click", function () {
              cleanupCamera();
              setStatus("Cámara detenida.", false);
            });

            captureButton.addEventListener("click", function () {
              fileInput.click();
            });

            fileInput.addEventListener("change", function () {
              if (!fileInput.files || !fileInput.files[0] || isSubmitting) {
                return;
              }
              setStatus("Procesando foto del código...", false);
              detectFromFile(fileInput.files[0]);
              fileInput.value = "";
            });

            window.addEventListener("beforeunload", cleanupCamera);
          })();
        </script>';
    }

    if ($requestOperational && $pendingQty > 0 && in_array((string)$request['status'], ['ACCEPTED', 'PARTIAL'], true) && (string)($request['request_type'] ?? 'ROLL') !== 'ROLL') {
        $body .= mrManualCard($requestId, (string)($request['requested_unit'] ?? 'Unid.'), $pendingQty, true);
    }

    $body .= mrDeliveries($deliveries) . '</div>';

    render('Picking móvil', $body);
    exit;
}

if (preg_match('#^/stock/material-requests/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $requestId = (int)$m[1];
    $request = $service->getMaterialRequest($requestId);
    if ($request === null) {
        render('No encontrado', '<div class="card">No existe la solicitud.</div>');
        exit;
    }

    $deliveries = $service->listMaterialDeliveriesByRequest($requestId);
    $flashOk = trim((string)($_GET['ok'] ?? ''));
    $flashError = trim((string)($_GET['error'] ?? ''));
    $requestedQty = (float)($request['requested_qty'] ?? 0);
    $deliveredQty = (float)($request['delivered_qty'] ?? 0);
    $pendingQty = max(0, $requestedQty - $deliveredQty);
    $requestOperational = workOrderCanReceiveMaterials((string)($request['work_order_status'] ?? ''));

    $body = mrStyles() . '<div class="mr">' . mrPageHeader('Atención de solicitud', '','/stock/material-requests', '← Volver a solicitudes')
        . mrAlerts($flashOk, $flashError, $requestOperational)
        . mrDetailTop($request, $requestOperational, $requestedQty, $deliveredQty, $pendingQty);
    if ($requestOperational && (string)$request['status'] === 'PENDING') {
        $body .= mrAcceptCard($requestId, false);
    }

    if ($requestOperational && $pendingQty > 0 && in_array((string)$request['status'], ['ACCEPTED', 'PARTIAL'], true) && (string)($request['request_type'] ?? 'ROLL') === 'ROLL') {
        $body .= mrScanCards($requestId, false, $pendingQty);

        $body .= '<script>
          (function () {
            var form = document.querySelector(\'form[action="/stock/material-requests/' . $requestId . '/scan"]\');
            var input = document.getElementById("request_scan_code");
            var startButton = document.getElementById("camera_scan_start");
            var stopButton = document.getElementById("camera_scan_stop");
            var captureButton = document.getElementById("camera_scan_capture");
            var fileInput = document.getElementById("camera_scan_file");
            var video = document.getElementById("camera_scan_video");
            var statusBox = document.getElementById("camera_scan_status");
            if (!form || !input || !startButton || !stopButton || !captureButton || !fileInput || !video || !statusBox) {
              return;
            }

            var stream = null;
            var detector = null;
            var scanTimer = null;
            var isRunning = false;
            var isSubmitting = false;

            function setStatus(message, isError) {
              statusBox.textContent = message;
              statusBox.style.color = isError ? "#b42318" : "#475467";
            }

            function cleanupCamera() {
              if (scanTimer !== null) {
                window.clearTimeout(scanTimer);
                scanTimer = null;
              }
              if (stream) {
                stream.getTracks().forEach(function (track) { track.stop(); });
                stream = null;
              }
              video.srcObject = null;
              isRunning = false;
              startButton.disabled = false;
              stopButton.disabled = true;
            }

            function insecureContextMessage() {
              if (window.isSecureContext) {
                return "";
              }
              if (location.protocol === "http:" && location.hostname !== "localhost" && location.hostname !== "127.0.0.1") {
                return "La cámara en vivo está bloqueada porque abriste el sistema por HTTP en red local. En celular/tablet esto normalmente requiere HTTPS.";
              }
              return "";
            }

            async function buildDetector() {
              if (!("BarcodeDetector" in window)) {
                return null;
              }
              try {
                var supported = typeof BarcodeDetector.getSupportedFormats === "function"
                  ? await BarcodeDetector.getSupportedFormats()
                  : [];
                var preferred = ["code_39", "code_128", "ean_13", "ean_8", "upc_a", "upc_e"];
                var formats = preferred.filter(function (item) { return supported.indexOf(item) !== -1; });
                return formats.length > 0 ? new BarcodeDetector({ formats: formats }) : new BarcodeDetector();
              } catch (error) {
                return new BarcodeDetector();
              }
            }

            async function scanLoop() {
              if (!isRunning || !detector || isSubmitting) {
                return;
              }
              try {
                var codes = await detector.detect(video);
                if (codes && codes.length > 0) {
                  var rawValue = (codes[0].rawValue || "").trim();
                  if (rawValue !== "") {
                    if (typeof window.mrAddScannedCode === "function") {
                      window.mrAddScannedCode(rawValue);
                      setStatus("✓ Código agregado a casilla: " + rawValue + ". Puedes escanear otro o pulsar el botón de carga.", false);
                      scanTimer = window.setTimeout(scanLoop, 1200);
                      return;
                    }
                    input.value = rawValue;
                    isSubmitting = true;
                    setStatus("Código detectado. Ingresando bobina...", false);
                    cleanupCamera();
                    form.submit();
                    return;
                  }
                }
              } catch (error) {
                setStatus("No se pudo leer el código todavía. Mantén la cámara enfocando la etiqueta.", false);
              }
              scanTimer = window.setTimeout(scanLoop, 250);
            }

            async function detectFromFile(file) {
              detector = await buildDetector();
              if (!detector) {
                setStatus("Este navegador no soporta leer códigos desde cámara o foto. Usa Chrome o Edge actualizado.", true);
                return;
              }
              try {
                var bitmap = await createImageBitmap(file);
                var codes = await detector.detect(bitmap);
                if (bitmap && typeof bitmap.close === "function") {
                  bitmap.close();
                }
                if (codes && codes.length > 0) {
                  var rawValue = (codes[0].rawValue || "").trim();
                  if (rawValue !== "") {
                    if (typeof window.mrAddScannedCode === "function") {
                      window.mrAddScannedCode(rawValue);
                      setStatus("✓ Código detectado desde la foto y agregado a casilla: " + rawValue, false);
                      return;
                    }
                    input.value = rawValue;
                    isSubmitting = true;
                    setStatus("Código detectado desde la foto. Ingresando bobina...", false);
                    form.submit();
                    return;
                  }
                }
                setStatus("No se pudo leer el código desde la foto. Acércate más a la etiqueta o mejora la luz.", true);
              } catch (error) {
                setStatus("No se pudo procesar la foto del código. Intenta nuevamente.", true);
              }
            }

            async function startCamera() {
              if (isRunning) {
                return;
              }
              if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                var reason = insecureContextMessage();
                setStatus(reason !== "" ? reason + " Usa \"Tomar foto\" o el campo manual." : "Este navegador no permite abrir la cámara. Usa \"Tomar foto\", el campo manual o un lector externo.", true);
                return;
              }
              detector = await buildDetector();
              if (!detector) {
                setStatus("Este navegador no soporta detección de código con cámara. Usa Chrome o Edge en el dispositivo, o prueba con \"Tomar foto\".", true);
                return;
              }

              try {
                stream = await navigator.mediaDevices.getUserMedia({
                  video: {
                    facingMode: { ideal: "environment" },
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                  },
                  audio: false
                });
                video.srcObject = stream;
                await video.play();
                isRunning = true;
                startButton.disabled = true;
                stopButton.disabled = false;
                setStatus("Cámara activa. Apunta al código de barras de la bobina.", false);
                scanLoop();
              } catch (error) {
                cleanupCamera();
                var contextMessage = insecureContextMessage();
                setStatus(contextMessage !== "" ? contextMessage + " También puedes usar \"Tomar foto\"." : "No se pudo abrir la cámara. Revisa permisos del navegador y usa HTTPS o localhost.", true);
              }
            }

            startButton.addEventListener("click", function () {
              startCamera();
            });

            stopButton.addEventListener("click", function () {
              cleanupCamera();
              setStatus("Cámara detenida.", false);
            });

            captureButton.addEventListener("click", function () {
              fileInput.click();
            });

            fileInput.addEventListener("change", function () {
              if (!fileInput.files || !fileInput.files[0] || isSubmitting) {
                return;
              }
              setStatus("Procesando foto del código...", false);
              detectFromFile(fileInput.files[0]);
              fileInput.value = "";
            });

            window.addEventListener("beforeunload", cleanupCamera);
          })();
        </script>';
    }

    if ($requestOperational && $pendingQty > 0 && in_array((string)$request['status'], ['ACCEPTED', 'PARTIAL'], true) && (string)($request['request_type'] ?? 'ROLL') !== 'ROLL') {
        $body .= mrManualCard($requestId, (string)($request['requested_unit'] ?? 'Unid.'), $pendingQty, false);
    }

    $body .= mrDeliveries($deliveries) . '</div>';

    render('Atención solicitud', $body);
    exit;
}

if (str_starts_with($path, '/admin/skus')) {
    header('Location: /');
    exit;
}

if ($path === '/cut' && $method === 'GET') {
    $scanCode = trim((string)($_GET['code'] ?? ''));
    $cutRoll = $scanCode !== '' ? $service->getRollByScanCode($scanCode) : null;
    $cutRolls = $service->listProducedRollsReadyForCut();
    $warehouses = array_values(array_filter($service->listWarehouses(), static fn(array $warehouse): bool => in_array((int)$warehouse['code'], [700, 1000], true)));
    $cutMessage = trim((string)($_GET['msg'] ?? ''));
    $cutError = trim((string)($_GET['error'] ?? ''));
    if ($cutRoll !== null) {
        $cutStage = strtoupper(trim((string)($cutRoll['process_stage'] ?? 'RAW')));
        $cutStatus = strtoupper(trim((string)($cutRoll['status'] ?? '')));
        if ($cutStage !== 'PRINTED' || $cutStatus === 'CONSUMED') {
            $cutError = 'La bobina escaneada no está disponible para corte.';
            $cutRoll = null;
        }
    }

    $body = '<div class="ub-filter-card" style="margin-bottom:14px">
        <div class="ub-filter-header" style="margin-bottom:14px">
          <div>
            <h1 class="ub-filter-title">Proceso de corte</h1>
          </div>
          <a class="ub-btn-secondary" href="/pallets">Ver pallets terminados</a>
        </div>
        <form method="get" action="/cut" class="erp-filter-form" style="padding-top:12px;border-top:1px solid #e2e8f0">
          <div class="ub-field" style="flex:1;min-width:260px">
            <label>Bobina salida producción</label>
            <input name="code" type="text" value="' . h($scanCode) . '" placeholder="Escanear código de bobina">
          </div>
          <div><button class="ub-btn-primary" type="submit">Buscar</button></div>
        </form>
      </div>';
    if ($cutMessage !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($cutMessage) . '</div>';
    }
    if ($cutError !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($cutError) . '</div>';
    }

    if ($cutRoll !== null) {
        $body .= '<div class="card" style="margin-bottom:12px">
            <div style="font-weight:800;margin-bottom:8px">Bobina lista para corte</div>
            <div class="row">
              <div style="flex:1;min-width:180px"><div class="muted">Código</div><div style="font-weight:800">' . h((string)$cutRoll['roll_code']) . '</div></div>
              <div style="flex:1;min-width:180px"><div class="muted">Especificación</div><div style="font-weight:800">' . h((string)$cutRoll['sku_description']) . '</div></div>
              <div style="flex:1;min-width:180px"><div class="muted">Peso</div><div style="font-weight:800">' . h((string)$cutRoll['weight_kg']) . ' Kg</div></div>
              <div style="flex:1;min-width:180px"><div class="muted">Etapa</div><div style="font-weight:800">' . h((string)($cutRoll['process_stage'] ?? '-')) . '</div></div>
            </div>
          </div>';

        $body .= '<div class="card">
            <form method="post" action="/cut/process">
              <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
              <input type="hidden" name="source_roll_id" value="' . (int)$cutRoll['id'] . '">
              <div class="row">
                <div style="flex:1;min-width:220px">
                  <label>Destino</label>
                  <select name="destination_mode" id="cut_destination_mode">
                    <option value="STOCK">Almacenar en stock</option>
                    <option value="CUSTOMER_ORDER">Orden de compra cliente</option>
                  </select>
                </div>
                <div style="flex:1;min-width:220px" id="cut_customer_wrap" style="display:none">
                  <label>OC cliente</label>
                  <input name="customer_order_ref" type="text" placeholder="OC cliente">
                </div>
              </div>
              <div class="row" style="margin-top:10px">
                <div style="flex:1;min-width:220px">
                  <label>Asignación de bodega</label>
                  <input type="text" value="La realiza Bodega al recibir el pallet terminado" readonly>
                </div>
                <div style="flex:1;min-width:180px">
                  <label>Unidades totales</label>
                  <input name="units_total" type="number" step="1" min="1" required>
                </div>
                <div style="flex:1;min-width:180px">
                  <label>Cantidad de cajas</label>
                  <input name="box_qty" type="number" step="1" min="1" required>
                </div>
                <div style="flex:1;min-width:180px">
                  <label>Cajas por pallet</label>
                  <input name="boxes_per_pallet" type="number" step="1" min="1" value="10" required>
                </div>
              </div>
              <div style="margin-top:12px"><button class="btn" type="submit">Procesar corte</button></div>
            </form>
            <script>
              (function(){
                var mode = document.getElementById("cut_destination_mode");
                var customerWrap = document.getElementById("cut_customer_wrap");
                if (!mode) return;
                function syncCutMode(){
                  var isCustomer = mode.value === "CUSTOMER_ORDER";
                  if (customerWrap) customerWrap.style.display = isCustomer ? "" : "none";
                }
                mode.addEventListener("change", syncCutMode);
                syncCutMode();
              })();
            </script>
          </div>';
    }

    $body .= '<div class="card" style="margin-top:12px">
        <div style="font-weight:800;margin-bottom:8px">Bobinas disponibles para corte</div>
        <table><thead><tr><th>Código</th><th>OT</th><th>SKU final</th><th>Peso</th><th></th></tr></thead><tbody>';
    foreach ($cutRolls as $roll) {
        $body .= '<tr>';
        $body .= '<td>' . h((string)$roll['roll_code']) . '</td>';
        $body .= '<td>' . h((string)($roll['ot_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($roll['sku_final'] ?? $roll['sku_description'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)$roll['weight_kg']) . ' Kg</td>';
        $body .= '<td><a class="btn secondary" href="/cut?code=' . urlencode((string)$roll['roll_code']) . '">Usar</a></td>';
        $body .= '</tr>';
    }
    if ($cutRolls === []) {
        $body .= '<tr><td colspan="5" class="muted">Sin bobinas pendientes de corte.</td></tr>';
    }
    $body .= '</tbody></table></div>';

    render('Corte', $body);
    exit;
}

if ($path === '/cut/process' && $method === 'POST') {
    denyErpProductionWriteAccess();
    requireCsrf();
    $result = $service->processCutRoll(
        (int)($_POST['source_roll_id'] ?? 0),
        (int)($_POST['units_total'] ?? 0),
        (int)($_POST['box_qty'] ?? 0),
        (int)($_POST['boxes_per_pallet'] ?? 0),
        (string)($_POST['destination_mode'] ?? 'STOCK'),
        isset($_POST['customer_order_ref']) ? (string)$_POST['customer_order_ref'] : null,
        isset($_POST['warehouse_id']) && (int)$_POST['warehouse_id'] > 0 ? (int)$_POST['warehouse_id'] : null,
        $currentOperatorName
    );
    $sourceRoll = $service->getRoll((int)($_POST['source_roll_id'] ?? 0));
    $workOrderId = $sourceRoll !== null ? (int)($sourceRoll['source_work_order_id'] ?? 0) : 0;
    $redirectCode = $sourceRoll !== null ? urlencode((string)$sourceRoll['roll_code']) : '';
    $redirectBase = $workOrderId > 0
        ? '/work-orders/' . $workOrderId . '/start'
        : '/cut' . ($redirectCode !== '' ? '?code=' . $redirectCode : '');
    $separator = str_contains($redirectBase, '?') ? '&' : '?';
    if (($result['ok'] ?? false) !== true) {
        $errorText = 'No se pudo procesar el corte.';
        if (isset($result['errors']) && is_array($result['errors']) && $result['errors'] !== []) {
            $errorText = implode(' | ', array_map(static fn($value): string => (string)$value, array_values($result['errors'])));
        }
        $errorKey = $workOrderId > 0 ? 'cut_error' : 'error';
        header('Location: ' . $redirectBase . $separator . $errorKey . '=' . urlencode($errorText));
        exit;
    }

    $printedBoxes = 0;
    $printedPallets = 0;
    $printErrors = [];
    if ($printer->isEnabled()) {
        foreach ((array)($result['boxes'] ?? []) as $createdBox) {
            $boxId = (int)($createdBox['id'] ?? 0);
            if ($boxId <= 0) {
                continue;
            }
            $box = $service->getBox($boxId);
            if (!is_array($box)) {
                $printErrors[] = 'No se encontró la caja #' . $boxId . ' para imprimir.';
                continue;
            }
            $printResult = $printer->printBoxLabel($box);
            if (($printResult['ok'] ?? false) === true || !empty($printResult['client_queued'])) {
                $printedBoxes++;
            } else {
                $printErrors[] = (string)($printResult['error'] ?? ('No se pudo imprimir la caja ' . ($box['box_code'] ?? ('#' . $boxId))));
            }
        }

        foreach ((array)($result['pallet_ids'] ?? []) as $palletId) {
            $pallet = $service->getPallet((int)$palletId);
            if (!is_array($pallet)) {
                $printErrors[] = 'No se encontró el pallet #' . (int)$palletId . ' para imprimir.';
                continue;
            }
            $palletBoxes = $service->listBoxesByPallet((int)$palletId);
            $printResult = $printer->printPalletLabel($pallet, $palletBoxes);
            if (($printResult['ok'] ?? false) === true || !empty($printResult['client_queued'])) {
                $printedPallets++;
            } else {
                $printErrors[] = (string)($printResult['error'] ?? ('No se pudo imprimir el pallet ' . ($pallet['pallet_code'] ?? ('#' . (int)$palletId))));
            }
        }
    }

    $message = 'Corte procesado: '
        . count((array)($result['boxes'] ?? []))
        . ' cajas y '
        . count((array)($result['pallet_ids'] ?? []))
        . ' pallets.';
    if ($printer->isEnabled()) {
        $message .= ' Impresión de etiquetas: '
            . $printedBoxes
            . ' cajas y '
            . $printedPallets
            . ' pallets.';
    }

    if ($workOrderId > 0) {
        header('Location: ' . $redirectBase . $separator . 'cut_completed=1' . ($printErrors !== [] ? '&cut_error=' . urlencode(implode(' | ', $printErrors)) : ''));
        exit;
    }
    header('Location: ' . $redirectBase . $separator . 'msg=' . urlencode($message) . ($printErrors !== [] ? '&error=' . urlencode(implode(' | ', $printErrors)) : ''));
    exit;
}

if (preg_match('#^/boxes/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $box = $service->getBox((int)$m[1]);
    if ($box === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe la caja.</div>');
        exit;
    }
    $boxSourceRoll = $service->getRoll((int)$box['source_roll_id']);
    $boxNavParams = inventoryNavigationContext() ? inventoryNavigationParams() : [];
    $boxBackUrl = inventoryNavigationContext() ? inventoryReturnUrl() : '/cut';

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Caja ' . h((string)$box['box_code']) . '</div>
        <div class="row"><a class="btn secondary" href="' . h($boxBackUrl) . '">' . h(inventoryNavigationContext() ? 'Volver a inventario' : 'Volver') . '</a><a class="btn secondary" href="' . h(withQuery('/rolls/' . (int)$box['source_roll_id'], $boxNavParams)) . '">Bobina origen</a><a class="btn" href="/boxes/' . (int)$box['id'] . '/label?auto_print=1" target="_blank" rel="noopener">Etiqueta</a><a class="btn secondary" href="/boxes/' . (int)$box['id'] . '/label?server_print=1" target="_blank" rel="noopener">Imprimir etiqueta</a></div>
      </div>';
    $body .= '<div class="card"><div class="row">
        <div style="flex:1;min-width:180px"><div class="muted">OT</div><div style="font-weight:800">' . h((string)($box['ot_code'] ?? '-')) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Bobina origen</div><div style="font-weight:800">' . h((string)$box['source_roll_code']) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Unidades</div><div style="font-weight:800">' . h((string)$box['units_qty']) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Pallet</div><div style="font-weight:800">' . h((string)($box['pallet_code'] ?? '-')) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Bodega actual</div><div style="font-weight:800">' . h(((string)($box['warehouse_code'] ?? '') !== '' ? ((string)$box['warehouse_code'] . ' - ' . (string)($box['warehouse_name'] ?? '')) : '-')) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Estado</div><div style="font-weight:800">' . h(boxStatusLabel((string)($box['status'] ?? ''))) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Operador</div><div style="font-weight:800">' . h((string)$box['operator_name']) . '</div></div>
      </div></div>';
    $body .= '<div class="card" style="margin-top:12px"><div style="font-weight:800;margin-bottom:8px">Cadena de trazabilidad</div><div class="row">';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">OT</div><div style="font-weight:700">';
    if ((int)($box['work_order_id'] ?? 0) > 0) {
        $boxOtLabel = (string)($box['ot_code'] ?? ('OT #' . (int)$box['work_order_id']));
        $body .= canAccessWorkOrderTraceability()
            ? '<a href="' . h(withQuery('/work-orders/' . (int)$box['work_order_id'] . '/traceability', $boxNavParams)) . '">' . h($boxOtLabel) . '</a>'
            : h($boxOtLabel);
    } else {
        $body .= '-';
    }
    $body .= '</div></div>';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">Bobina salida</div><div style="font-weight:700"><a href="' . h(withQuery('/rolls/' . (int)$box['source_roll_id'], $boxNavParams)) . '">' . h((string)$box['source_roll_code']) . '</a></div></div>';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">Bobina entrada</div><div style="font-weight:700">';
    if (is_array($boxSourceRoll) && (int)($boxSourceRoll['parent_roll_id'] ?? 0) > 0) {
        $body .= '<a href="' . h(withQuery('/rolls/' . (int)$boxSourceRoll['parent_roll_id'], $boxNavParams)) . '">' . h((string)($boxSourceRoll['parent_roll_code'] ?? ('#' . (int)$boxSourceRoll['parent_roll_id']))) . '</a>';
    } else {
        $body .= '-';
    }
    $body .= '</div></div>';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">Pallet</div><div style="font-weight:700">';
    if ((int)($box['pallet_id'] ?? 0) > 0) {
        $body .= '<a href="' . h(withQuery('/pallets/' . (int)$box['pallet_id'], $boxNavParams)) . '">' . h((string)($box['pallet_code'] ?? ('#' . (int)$box['pallet_id']))) . '</a>';
    } else {
        $body .= '-';
    }
    $body .= '</div></div>';
    $body .= '</div></div>';
    render('Caja', $body);
    exit;
}

if ($path === '/pallets' && $method === 'GET') {
    $isWarehousePalletArea = isWarehousePalletAssignmentArea();
    $palletSearch = trim((string)($_GET['q'] ?? ''));
    $palletWarehouseId = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;
    $palletWarehouses = $service->listWarehouses();
    $pallets = $service->listPallets($palletSearch, $palletWarehouseId > 0 ? $palletWarehouseId : null, true);
    $palletMessage = trim((string)($_GET['msg'] ?? ''));
    $palletError = trim((string)($_GET['error'] ?? ''));
    $returnQuery = [];
    if ($palletSearch !== '') {
        $returnQuery['q'] = $palletSearch;
    }
    if ($palletWarehouseId > 0) {
        $returnQuery['warehouse_id'] = (string)$palletWarehouseId;
    }
    $returnTo = '/pallets' . ($returnQuery !== [] ? '?' . http_build_query($returnQuery) : '');

    $body = '<div class="ub-filter-card" style="margin-bottom:14px">
        <div class="ub-filter-header">
          <div>
            <h1 class="ub-filter-title">Asignación de pallets terminados</h1>
          </div>
          <a class="ub-btn-secondary" href="' . ($isWarehousePalletArea ? '/stock' : '/cut') . '">← Volver</a>
        </div></div>';
    if ($palletMessage !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($palletMessage) . '</div>';
    }
    if ($palletError !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($palletError) . '</div>';
    }

    if (!$isWarehousePalletArea) {
        $body .= '<div class="card" style="margin-bottom:12px"><div class="muted">La asignación de pallets a bodega se realiza desde el área de Bodega/Inventario.</div></div>';
    }

    $body .= '<div class="ub-filter-card" style="margin-bottom:14px">
        <form method="get" action="/pallets" class="erp-filter-form">
          <div class="ub-field" style="flex:2;min-width:240px">
            <label>Buscar</label>
            <input type="text" name="q" value="' . h($palletSearch) . '" placeholder="Código pallet, OT, bobina, SKU u OC cliente">
          </div>
          <div class="ub-field" style="flex:1;min-width:220px">
            <label>Bodega actual</label>
            <select name="warehouse_id">
              <option value="">Todas</option>';
    foreach ($palletWarehouses as $warehouse) {
        $selected = (int)$warehouse['id'] === $palletWarehouseId ? ' selected' : '';
        $body .= '<option value="' . (int)$warehouse['id'] . '"' . $selected . '>' . h((string)$warehouse['code']) . ' - ' . h((string)$warehouse['name']) . '</option>';
    }
    $body .= '</select>
          </div>
          <div style="display:flex;gap:8px;align-items:flex-end">
            <button class="ub-btn-primary" type="submit">Filtrar</button>
            <a class="ub-btn-secondary" href="/pallets">✕ Limpiar</a>
          </div>
        </form>
      </div>';

    $body .= '<div class="ub-table-card" style="padding:14px 16px"><div style="font-weight:800;margin-bottom:10px">Pallets pendientes de asignación</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Pallet</th><th>OT</th><th>Bobina origen</th><th>Cajas</th><th>Unidades</th><th>Bodega actual</th><th>Estado</th><th>Destino</th><th>Asignación</th><th></th></tr></thead><tbody>';
    foreach ($pallets as $palletRow) {
        $warehouseLabel = trim((string)($palletRow['warehouse_code'] ?? ''));
        if ($warehouseLabel === '') {
            $warehouseLabel = 'Sin bodega';
        } elseif ((string)($palletRow['warehouse_name'] ?? '') !== '') {
            $warehouseLabel .= ' - ' . (string)$palletRow['warehouse_name'];
        }
        $body .= '<tr>';
        $body .= '<td>' . h((string)$palletRow['pallet_code']) . '</td>';
        $body .= '<td>' . h((string)($palletRow['ot_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($palletRow['source_roll_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)($palletRow['box_count'] ?? '0')) . '</td>';
        $body .= '<td>' . h((string)($palletRow['units_total'] ?? '0')) . '</td>';
        $body .= '<td>' . h($warehouseLabel) . '</td>';
        $body .= '<td>' . h(palletStatusLabel((string)($palletRow['status'] ?? ''))) . '</td>';
        $body .= '<td>' . h((string)($palletRow['destination_mode'] ?? '-')) . '</td>';
        if ($isWarehousePalletArea) {
            $body .= '<td><form method="post" action="/pallets/' . (int)$palletRow['id'] . '/move" style="margin:0"><input type="hidden" name="_csrf" value="' . h(csrfToken()) . '"><input type="hidden" name="return_to" value="' . h($returnTo) . '"><div class="row nowrap" style="gap:6px;align-items:end">';
            $body .= '<select name="warehouse_id" style="min-width:180px"><option value="">Asignar a...</option>';
            foreach ($palletWarehouses as $warehouse) {
                $disabled = (int)$warehouse['id'] === (int)($palletRow['warehouse_id'] ?? 0) ? ' disabled' : '';
                $body .= '<option value="' . (int)$warehouse['id'] . '"' . $disabled . '>' . h((string)$warehouse['code']) . ' - ' . h((string)$warehouse['name']) . '</option>';
            }
            $body .= '</select><button class="btn secondary" type="submit">Asignar</button></div></form></td>';
        } else {
            $body .= '<td><span class="muted">Solo Bodega</span></td>';
        }
        $body .= '<td><a class="btn secondary" href="/pallets/' . (int)$palletRow['id'] . '">Ver</a></td>';
        $body .= '</tr>';
    }
    if ($pallets === []) {
        $body .= '<tr><td colspan="10" class="muted">No hay pallets para los filtros seleccionados.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    render('Pallets', $body);
    exit;
}

if (preg_match('#^/pallets/(\d+)/move$#', $path, $m) === 1 && $method === 'POST') {
    if (!isWarehousePalletAssignmentArea()) {
        http_response_code(403);
        render('Acceso denegado', '<div class="card">La asignación de pallets a bodega solo puede realizarla el área de Bodega.</div>');
        exit;
    }
    requireCsrf();
    $palletId = (int)$m[1];
    $returnTo = trim((string)($_POST['return_to'] ?? ''));
    if ($returnTo === '' || !str_starts_with($returnTo, '/')) {
        $returnTo = '/pallets/' . $palletId;
    }
    $result = $service->movePalletToWarehouse(
        $palletId,
        (int)($_POST['warehouse_id'] ?? 0),
        $currentOperatorName
    );
    $separator = str_contains($returnTo, '?') ? '&' : '?';
    if (($result['ok'] ?? false) !== true) {
        $errorText = 'No se pudo mover el pallet.';
        if (isset($result['errors']) && is_array($result['errors']) && $result['errors'] !== []) {
            $errorText = implode(' | ', array_map(static fn($value): string => (string)$value, array_values($result['errors'])));
        }
        header('Location: ' . $returnTo . $separator . 'error=' . urlencode($errorText));
        exit;
    }
    header('Location: ' . $returnTo . $separator . 'msg=' . urlencode('Pallet movido correctamente a la bodega seleccionada.'));
    exit;
}

if (preg_match('#^/pallets/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $isWarehousePalletArea = isWarehousePalletAssignmentArea();
    $pallet = $service->getPallet((int)$m[1]);
    if ($pallet === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">No existe el pallet.</div>');
        exit;
    }
    $palletSourceRoll = $service->getRoll((int)$pallet['source_roll_id']);
    $palletBoxes = $service->listBoxesByPallet((int)$pallet['id']);
    $activeMaquilaOrder = $service->getOpenMaquilaOrderByPallet((int)$pallet['id']);
    $palletMaquilaOrders = $service->listMaquilaOrdersByPallet((int)$pallet['id']);
    $palletWarehouses = $service->listWarehouses();
    $palletMessage = trim((string)($_GET['msg'] ?? ''));
    $palletError = trim((string)($_GET['error'] ?? ''));
    $palletNavParams = inventoryNavigationContext() ? inventoryNavigationParams() : [];
    $palletBackUrl = inventoryNavigationContext() ? inventoryReturnUrl() : '/pallets';
    $currentWarehouseLabel = trim((string)($pallet['warehouse_code'] ?? ''));
    if ($currentWarehouseLabel === '') {
        $currentWarehouseLabel = 'Sin bodega';
    } elseif ((string)($pallet['warehouse_name'] ?? '') !== '') {
        $currentWarehouseLabel .= ' - ' . (string)$pallet['warehouse_name'];
    }

    $maquilaAction = '';
    if (currentSessionArea() === 'RECEPTION') {
        $maquilaAction = $activeMaquilaOrder !== null
            ? '<a class="btn secondary" href="/maquila/' . (int)$activeMaquilaOrder['id'] . '">Ver maquila</a>'
            : '<a class="btn secondary" href="/maquila?pallet_id=' . (int)$pallet['id'] . '">Enviar a maquila</a>';
    }

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div style="font-size:18px;font-weight:700">Pallet ' . h((string)$pallet['pallet_code']) . '</div>
        <div class="row"><a class="btn secondary" href="' . h($palletBackUrl) . '">' . h(inventoryNavigationContext() ? 'Volver a inventario' : 'Volver') . '</a>' . $maquilaAction . '<a class="btn secondary" href="' . h(withQuery('/rolls/' . (int)$pallet['source_roll_id'], $palletNavParams)) . '">Bobina origen</a><a class="btn" href="/pallets/' . (int)$pallet['id'] . '/label?auto_print=1" target="_blank" rel="noopener">Etiqueta</a><a class="btn secondary" href="/pallets/' . (int)$pallet['id'] . '/label?server_print=1" target="_blank" rel="noopener">Imprimir etiqueta</a></div>
      </div>';
    if ($palletMessage !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($palletMessage) . '</div>';
    }
    if ($palletError !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($palletError) . '</div>';
    }
    $body .= '<div class="card"><div class="row">
        <div style="flex:1;min-width:180px"><div class="muted">OT</div><div style="font-weight:800">' . h((string)($pallet['ot_code'] ?? '-')) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Bobina origen</div><div style="font-weight:800">' . h((string)$pallet['source_roll_code']) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Cajas</div><div style="font-weight:800">' . h((string)$pallet['box_count']) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Destino</div><div style="font-weight:800">' . h((string)$pallet['destination_mode']) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Bodega actual</div><div style="font-weight:800">' . h($currentWarehouseLabel) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Estado</div><div style="font-weight:800">' . h(palletStatusLabel((string)($pallet['status'] ?? ''))) . '</div></div>
        <div style="flex:1;min-width:180px"><div class="muted">Operador</div><div style="font-weight:800">' . h((string)$pallet['operator_name']) . '</div></div>
      </div></div>';
    if ($isWarehousePalletArea) {
        $body .= '<div class="card" style="margin-top:12px"><div style="font-weight:800;margin-bottom:8px">Asignar pallet a bodega</div>
            <form method="post" action="/pallets/' . (int)$pallet['id'] . '/move">
              <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
              <input type="hidden" name="return_to" value="/pallets/' . (int)$pallet['id'] . '">
              <div class="row" style="align-items:end">
                <div style="flex:1;min-width:260px">
                  <label>Bodega destino</label>
                  <select name="warehouse_id" required>
                    <option value="">Seleccionar</option>';
        foreach ($palletWarehouses as $warehouse) {
            $disabled = (int)$warehouse['id'] === (int)($pallet['warehouse_id'] ?? 0) ? ' disabled' : '';
            $body .= '<option value="' . (int)$warehouse['id'] . '"' . $disabled . '>' . h((string)$warehouse['code']) . ' - ' . h((string)$warehouse['name']) . '</option>';
        }
        $body .= '</select>
                </div>
                <div style="min-width:220px">
                  <button class="btn" type="submit">Asignar a bodega</button>
                </div>
              </div>
            </form>
          </div>';
    } else {
        $body .= '<div class="card" style="margin-top:12px"><div class="muted">La asignación de este pallet a bodega debe realizarla el área de Bodega desde Inventario.</div></div>';
    }
    if ($palletMaquilaOrders !== []) {
        $body .= '<div class="card" style="margin-top:12px"><div style="font-weight:800;margin-bottom:8px">Historial de maquila</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Orden</th><th>Taller</th><th>Salida</th><th>Retorno</th><th>Merma</th><th>Estado</th><th></th></tr></thead><tbody>';
        foreach ($palletMaquilaOrders as $palletMaquilaOrder) {
            $body .= '<tr>';
            $body .= '<td>#' . (int)$palletMaquilaOrder['id'] . '</td>';
            $body .= '<td>' . h((string)$palletMaquilaOrder['workshop_name']) . '</td>';
            $body .= '<td>' . h(formatReceptionValue((float)($palletMaquilaOrder['outgoing_weight_kg'] ?? 0), 'Kg')) . ' Kg</td>';
            $body .= '<td>' . h(formatReceptionValue((float)($palletMaquilaOrder['returned_weight_kg'] ?? 0), 'Kg')) . ' Kg</td>';
            $body .= '<td>' . h(formatReceptionValue((float)($palletMaquilaOrder['waste_weight_kg'] ?? 0), 'Kg')) . ' Kg</td>';
            $body .= '<td>' . h(maquilaStatusLabel((string)$palletMaquilaOrder['status'])) . '</td>';
            $body .= '<td><a class="btn secondary" href="/maquila/' . (int)$palletMaquilaOrder['id'] . '">Ver orden</a></td>';
            $body .= '</tr>';
        }
        $body .= '</tbody></table></div></div>';
    }
    $body .= '<div class="card" style="margin-top:12px"><div style="font-weight:800;margin-bottom:8px">Cadena de trazabilidad</div><div class="row">';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">OT</div><div style="font-weight:700">';
    if ((int)($pallet['work_order_id'] ?? 0) > 0) {
        $palletOtLabel = (string)($pallet['ot_code'] ?? ('OT #' . (int)$pallet['work_order_id']));
        $body .= canAccessWorkOrderTraceability()
            ? '<a href="' . h(withQuery('/work-orders/' . (int)$pallet['work_order_id'] . '/traceability', $palletNavParams)) . '">' . h($palletOtLabel) . '</a>'
            : h($palletOtLabel);
    } else {
        $body .= '-';
    }
    $body .= '</div></div>';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">Bobina salida</div><div style="font-weight:700"><a href="' . h(withQuery('/rolls/' . (int)$pallet['source_roll_id'], $palletNavParams)) . '">' . h((string)$pallet['source_roll_code']) . '</a></div></div>';
    $body .= '<div style="flex:1;min-width:180px"><div class="muted">Bobina entrada</div><div style="font-weight:700">';
    if (is_array($palletSourceRoll) && (int)($palletSourceRoll['parent_roll_id'] ?? 0) > 0) {
        $body .= '<a href="' . h(withQuery('/rolls/' . (int)$palletSourceRoll['parent_roll_id'], $palletNavParams)) . '">' . h((string)($palletSourceRoll['parent_roll_code'] ?? ('#' . (int)$palletSourceRoll['parent_roll_id']))) . '</a>';
    } else {
        $body .= '-';
    }
    $body .= '</div></div>';
    $body .= '</div></div>';
    $body .= '<div class="card" style="margin-top:12px"><div style="font-weight:800;margin-bottom:8px">Cajas contenidas</div><div class="table-wrap"><table class="trace-table"><thead><tr><th>Caja</th><th>Unidades</th><th>Bodega</th><th>Estado</th><th></th></tr></thead><tbody>';
    foreach ($palletBoxes as $palletBox) {
        $warehouseLabel = trim((string)($palletBox['warehouse_code'] ?? ''));
        if ($warehouseLabel === '') {
            $warehouseLabel = '-';
        } elseif ((string)($palletBox['warehouse_name'] ?? '') !== '') {
            $warehouseLabel .= ' - ' . (string)$palletBox['warehouse_name'];
        }
        $body .= '<tr>';
        $body .= '<td><a href="' . h(withQuery('/boxes/' . (int)$palletBox['id'], $palletNavParams)) . '">' . h((string)$palletBox['box_code']) . '</a></td>';
        $body .= '<td>' . h((string)($palletBox['units_qty'] ?? '-')) . '</td>';
        $body .= '<td>' . h($warehouseLabel) . '</td>';
        $body .= '<td>' . h(boxStatusLabel((string)($palletBox['status'] ?? ''))) . '</td>';
        $body .= '<td><a class="btn secondary" href="' . h(withQuery('/boxes/' . (int)$palletBox['id'], $palletNavParams)) . '">Ver caja</a></td>';
        $body .= '</tr>';
    }
    if ($palletBoxes === []) {
        $body .= '<tr><td colspan="5" class="muted">Este pallet aún no tiene cajas asociadas.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    render('Pallet', $body);
    exit;
}

if ($path === '/maquila' && $method === 'GET') {
    $statusFilter = strtoupper(trim((string)($_GET['status'] ?? 'OPEN')));
    if (!in_array($statusFilter, ['ALL', 'OPEN', 'PARTIAL', 'RETURNED'], true)) {
        $statusFilter = 'OPEN';
    }
    $selectedPalletId = isset($_GET['pallet_id']) ? (int)$_GET['pallet_id'] : 0;
    $maquilaMessage = trim((string)($_GET['msg'] ?? ''));
    $maquilaError = trim((string)($_GET['error'] ?? ''));
    $availablePallets = $service->listPalletsAvailableForMaquila(250);
    $maquilaOrders = $service->listMaquilaOrders($statusFilter === 'ALL' ? null : $statusFilter);

    $body = '<div class="ub-filter-card" style="margin-bottom:14px">
        <div class="ub-filter-header">
          <div>
            <h1 class="ub-filter-title">Maquila y talleres externos</h1>
          </div>
          <a class="ub-btn-secondary" href="/stock">← Volver a inventario</a>
        </div>
      </div>';
    if ($maquilaMessage !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($maquilaMessage) . '</div>';
    }
    if ($maquilaError !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($maquilaError) . '</div>';
    }

    $body .= '<div class="card" style="margin-bottom:12px">
      <div style="font-weight:800;margin-bottom:8px">Nueva salida a maquila</div>
      <form method="post" action="/maquila">
        <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
        <div class="row">
          <div style="flex:2;min-width:280px">
            <label>Pallet / lote</label>
            <select name="pallet_id" required>
              <option value="">Seleccionar</option>';
    foreach ($availablePallets as $availablePallet) {
        $selected = (int)$availablePallet['id'] === $selectedPalletId ? ' selected' : '';
        $warehouseLabel = trim((string)($availablePallet['warehouse_code'] ?? ''));
        if ($warehouseLabel !== '' && (string)($availablePallet['warehouse_name'] ?? '') !== '') {
            $warehouseLabel .= ' - ' . (string)$availablePallet['warehouse_name'];
        }
        $body .= '<option value="' . (int)$availablePallet['id'] . '"' . $selected . '>' . h((string)$availablePallet['pallet_code']) . ' | OT ' . h((string)($availablePallet['ot_code'] ?? '-')) . ' | ' . h((string)$warehouseLabel) . '</option>';
    }
    $body .= '</select>
          </div>
          <div style="flex:1;min-width:220px">
            <label>Taller externo</label>
            <input type="text" name="workshop_name" value="' . h((string)($_GET['workshop_name'] ?? '')) . '" placeholder="Ej: Taller Externo Norte" required>
          </div>
          <div style="flex:1;min-width:180px">
            <label>Peso salida (Kg)</label>
            <input type="number" step="0.001" min="0.001" name="outgoing_weight_kg" value="' . h((string)($_GET['outgoing_weight_kg'] ?? '')) . '" required>
          </div>
        </div>
        <div style="margin-top:10px">
          <label>Observación</label>
          <input type="text" name="notes" value="' . h((string)($_GET['notes'] ?? '')) . '" placeholder="Detalle de envío, guía o comentario">
        </div>
        <div style="margin-top:12px">
          <button class="btn" type="submit">Registrar salida</button>
        </div>
      </form>
    </div>';

    if ($availablePallets === []) {
        $body .= '<div class="card" style="margin-bottom:12px"><div class="muted">No hay pallets internos disponibles para enviar a maquila.</div></div>';
    }

    $body .= '<div class="card">
      <div class="row" style="justify-content:space-between;align-items:end;margin-bottom:8px">
        <div style="font-weight:800">Órdenes de maquila</div>
        <form method="get" action="/maquila">
          <div class="row nowrap">
            <div style="min-width:200px">
              <label>Estado</label>
              <select name="status">
                <option value="OPEN"' . ($statusFilter === 'OPEN' ? ' selected' : '') . '>En maquila</option>
                <option value="PARTIAL"' . ($statusFilter === 'PARTIAL' ? ' selected' : '') . '>Retorno parcial</option>
                <option value="RETURNED"' . ($statusFilter === 'RETURNED' ? ' selected' : '') . '>Retornadas</option>
                <option value="ALL"' . ($statusFilter === 'ALL' ? ' selected' : '') . '>Todas</option>
              </select>
            </div>
            <div style="min-width:160px;align-self:end">
              <button class="btn secondary" type="submit">Ver</button>
            </div>
          </div>
        </form>
      </div>
      <div class="table-wrap"><table class="trace-table"><thead><tr><th>Orden</th><th>Pallet</th><th>OT</th><th>Taller</th><th>Salida</th><th>Retorno</th><th>Merma</th><th>Pendiente</th><th>Estado</th><th></th></tr></thead><tbody>';
    foreach ($maquilaOrders as $maquilaOrder) {
        $pendingWeightKg = max(0, round((float)($maquilaOrder['outgoing_weight_kg'] ?? 0) - (float)($maquilaOrder['returned_weight_kg'] ?? 0) - (float)($maquilaOrder['waste_weight_kg'] ?? 0), 3));
        $body .= '<tr>';
        $body .= '<td>#' . (int)$maquilaOrder['id'] . '</td>';
        $body .= '<td>' . h((string)$maquilaOrder['pallet_code']) . '</td>';
        $body .= '<td>' . h((string)($maquilaOrder['ot_code'] ?? '-')) . '</td>';
        $body .= '<td>' . h((string)$maquilaOrder['workshop_name']) . '</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($maquilaOrder['outgoing_weight_kg'] ?? 0), 'Kg')) . ' Kg</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($maquilaOrder['returned_weight_kg'] ?? 0), 'Kg')) . ' Kg</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($maquilaOrder['waste_weight_kg'] ?? 0), 'Kg')) . ' Kg</td>';
        $body .= '<td>' . h(formatReceptionValue($pendingWeightKg, 'Kg')) . ' Kg</td>';
        $body .= '<td>' . h(maquilaStatusLabel((string)$maquilaOrder['status'])) . '</td>';
        $body .= '<td><a class="btn secondary" href="/maquila/' . (int)$maquilaOrder['id'] . '">Ver</a></td>';
        $body .= '</tr>';
    }
    if ($maquilaOrders === []) {
        $body .= '<tr><td colspan="10" class="muted">No hay órdenes de maquila para el filtro seleccionado.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    render('Maquila', $body);
    exit;
}

if ($path === '/maquila' && $method === 'POST') {
    requireCsrf();
    $palletId = (int)($_POST['pallet_id'] ?? 0);
    $workshopName = trim((string)($_POST['workshop_name'] ?? ''));
    $outgoingWeightKg = (float)($_POST['outgoing_weight_kg'] ?? 0);
    $notes = trim((string)($_POST['notes'] ?? ''));
    $result = $service->createMaquilaOrder($palletId, $workshopName, $outgoingWeightKg, $notes, $currentOperatorName);
    if (($result['ok'] ?? false) === true) {
        header('Location: /maquila/' . (int)$result['maquila_order_id'] . '?msg=' . urlencode('Salida a maquila registrada correctamente.'));
        exit;
    }

    $query = http_build_query([
        'error' => (string)reset($result['errors']),
        'pallet_id' => $palletId > 0 ? (string)$palletId : '',
        'workshop_name' => $workshopName,
        'outgoing_weight_kg' => $outgoingWeightKg > 0 ? (string)$outgoingWeightKg : '',
        'notes' => $notes,
    ]);
    header('Location: /maquila' . ($query !== '' ? '?' . $query : ''));
    exit;
}

if (preg_match('#^/maquila/(\d+)$#', $path, $m) === 1 && $method === 'GET') {
    $maquilaOrderId = (int)$m[1];
    $maquilaOrder = $service->getMaquilaOrder($maquilaOrderId);
    if ($maquilaOrder === null) {
        http_response_code(404);
        render('No encontrado', '<div class="card">La orden de maquila no existe.</div>');
        exit;
    }

    $maquilaReturns = $service->listMaquilaReturns($maquilaOrderId);
    $maquilaMessage = trim((string)($_GET['msg'] ?? ''));
    $maquilaError = trim((string)($_GET['error'] ?? ''));
    $pendingWeightKg = max(0, round((float)($maquilaOrder['outgoing_weight_kg'] ?? 0) - (float)($maquilaOrder['returned_weight_kg'] ?? 0) - (float)($maquilaOrder['waste_weight_kg'] ?? 0), 3));

    $body = '<div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
        <div>
          <div style="font-size:18px;font-weight:700">Orden de maquila #' . (int)$maquilaOrder['id'] . '</div>
          
        </div>
        <div class="row"><a class="btn secondary" href="/maquila">Volver</a><a class="btn secondary" href="/pallets/' . (int)$maquilaOrder['pallet_id'] . '">Ver pallet</a></div>
      </div>';
    if ($maquilaMessage !== '') {
        $body .= '<div class="ok" style="margin-bottom:12px">' . h($maquilaMessage) . '</div>';
    }
    if ($maquilaError !== '') {
        $body .= '<div class="err" style="margin-bottom:12px">' . h($maquilaError) . '</div>';
    }

    $body .= '<div class="card" style="margin-bottom:12px"><div class="row">
      <div style="flex:1;min-width:180px"><div class="muted">Pallet</div><div style="font-weight:800">' . h((string)$maquilaOrder['pallet_code']) . '</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">OT</div><div style="font-weight:800">' . h((string)($maquilaOrder['ot_code'] ?? '-')) . '</div></div>
      <div style="flex:1;min-width:220px"><div class="muted">Taller</div><div style="font-weight:800">' . h((string)$maquilaOrder['workshop_name']) . '</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Estado</div><div style="font-weight:800">' . h(maquilaStatusLabel((string)$maquilaOrder['status'])) . '</div></div>
    </div>
    <div class="row" style="margin-top:10px">
      <div style="flex:1;min-width:180px"><div class="muted">Salida</div><div style="font-weight:800">' . h(formatReceptionValue((float)($maquilaOrder['outgoing_weight_kg'] ?? 0), 'Kg')) . ' Kg</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Retorno acumulado</div><div style="font-weight:800">' . h(formatReceptionValue((float)($maquilaOrder['returned_weight_kg'] ?? 0), 'Kg')) . ' Kg</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Merma acumulada</div><div style="font-weight:800">' . h(formatReceptionValue((float)($maquilaOrder['waste_weight_kg'] ?? 0), 'Kg')) . ' Kg</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Pendiente</div><div style="font-weight:800">' . h(formatReceptionValue($pendingWeightKg, 'Kg')) . ' Kg</div></div>
    </div>
    <div class="row" style="margin-top:10px">
      <div style="flex:1;min-width:180px"><div class="muted">Bodega salida</div><div style="font-weight:800">' . h((string)$maquilaOrder['outgoing_warehouse_code']) . ' - ' . h((string)$maquilaOrder['outgoing_warehouse_name']) . '</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Bodega externa</div><div style="font-weight:800">' . h((string)$maquilaOrder['external_warehouse_code']) . ' - ' . h((string)$maquilaOrder['external_warehouse_name']) . '</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Bodega retorno</div><div style="font-weight:800">' . h((string)$maquilaOrder['return_warehouse_code']) . ' - ' . h((string)$maquilaOrder['return_warehouse_name']) . '</div></div>
      <div style="flex:1;min-width:180px"><div class="muted">Operador salida</div><div style="font-weight:800">' . h((string)$maquilaOrder['operator_name']) . '</div></div>
    </div></div>';

    if (in_array((string)$maquilaOrder['status'], ['OPEN', 'PARTIAL'], true)) {
        $body .= '<div class="card" style="margin-bottom:12px">
          <div style="font-weight:800;margin-bottom:8px">Registrar retorno</div>
          <form method="post" action="/maquila/' . $maquilaOrderId . '/return">
            <input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">
            <div class="row">
              <div style="flex:1;min-width:180px">
                <label>Peso retorno (Kg)</label>
                <input type="number" step="0.001" min="0" max="' . h((string)$pendingWeightKg) . '" name="return_weight_kg" value="' . h((string)$pendingWeightKg) . '">
              </div>
              <div style="flex:1;min-width:180px">
                <label>Cajas retornadas</label>
                <input type="number" step="1" min="0" name="returned_box_count" value="0">
              </div>
              <div style="flex:1;min-width:180px">
                <label>Unidades retornadas</label>
                <input type="number" step="0.001" min="0" name="returned_units_qty" value="0">
              </div>
              <div style="flex:1;min-width:180px">
                <label>Merma (Kg)</label>
                <input type="number" step="0.001" min="0" name="waste_weight_kg" value="0">
              </div>
            </div>
            <div style="margin-top:10px">
              <label>Observación retorno</label>
              <input type="text" name="notes" value="" placeholder="Detalle de retorno parcial, total o diferencia detectada">
            </div>
            <div style="margin-top:12px">
              <button class="btn" type="submit">Registrar retorno</button>
            </div>
          </form>
        </div>';
    }

    $body .= '<div class="card">
      <div style="font-weight:800;margin-bottom:8px">Historial de retornos</div>
      <div class="table-wrap"><table class="trace-table"><thead><tr><th>Fecha</th><th>Peso retorno</th><th>Cajas</th><th>Unidades</th><th>Merma</th><th>Operador</th><th>Observación</th></tr></thead><tbody>';
    foreach ($maquilaReturns as $maquilaReturn) {
        $body .= '<tr>';
        $body .= '<td>' . h((string)$maquilaReturn['created_at']) . '</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($maquilaReturn['return_weight_kg'] ?? 0), 'Kg')) . ' Kg</td>';
        $body .= '<td>' . h((string)($maquilaReturn['returned_box_count'] ?? '0')) . '</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($maquilaReturn['returned_units_qty'] ?? 0), 'Unid.')) . '</td>';
        $body .= '<td>' . h(formatReceptionValue((float)($maquilaReturn['waste_weight_kg'] ?? 0), 'Kg')) . ' Kg</td>';
        $body .= '<td>' . h((string)$maquilaReturn['operator_name']) . '</td>';
        $body .= '<td>' . h((string)($maquilaReturn['notes'] ?? '-')) . '</td>';
        $body .= '</tr>';
    }
    if ($maquilaReturns === []) {
        $body .= '<tr><td colspan="7" class="muted">Todavía no se registran retornos para esta orden.</td></tr>';
    }
    $body .= '</tbody></table></div></div>';
    render('Maquila', $body);
    exit;
}

if (preg_match('#^/maquila/(\d+)/return$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $maquilaOrderId = (int)$m[1];
    $result = $service->registerMaquilaReturn(
        $maquilaOrderId,
        (float)($_POST['return_weight_kg'] ?? 0),
        (int)($_POST['returned_box_count'] ?? 0),
        (float)($_POST['returned_units_qty'] ?? 0),
        (float)($_POST['waste_weight_kg'] ?? 0),
        (string)($_POST['notes'] ?? ''),
        $currentOperatorName
    );
    $query = (($result['ok'] ?? false) === true)
        ? '?msg=' . urlencode('Retorno de maquila registrado correctamente.')
        : '?error=' . urlencode((string)reset($result['errors']));
    header('Location: /maquila/' . $maquilaOrderId . $query);
    exit;
}

    return false;
}
