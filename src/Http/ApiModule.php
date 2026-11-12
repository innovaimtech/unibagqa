<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · API (endpoints JSON)
//
// Este módulo agrupa rutas bajo /api para acciones “headless” que se consumen
// desde la UI (AJAX) u otros clientes internos.
//
// Convenciones:
// - Retorna true si la ruta fue manejada; false para que el router continúe.
// - Emite JSON directamente y setea HTTP status cuando corresponde.
// - Para POST se exige CSRF (requireCsrf()).
//
// ---
//
// HTTP Module · API (JSON endpoints)
//
// This module groups routes under /api for “headless” actions consumed by the UI
// (AJAX) or other internal clients.
//
// Conventions:
// - Returns true if the route was handled; false so the router can continue.
// - Outputs JSON directly and sets HTTP status when needed.
// - POST endpoints require CSRF (requireCsrf()).
// =============================================================================

/**
 * Router de endpoints /api.
 *
 * Endpoints actuales:
 * - GET  /api/scale/weight
 *   Lee el peso actual de la balanza (cuando está habilitada).
 *
 * - POST /api/receptions/receive
 *   Crea una bobina/rollo a partir de:
 *   - una línea de importación (import_container_item_id), o
 *   - una línea de orden de compra (purchase_order_line_id).
 *   Luego intenta imprimir etiqueta si la impresora está habilitada.
 *
 * ---
 *
 * /api endpoints router.
 *
 * Current endpoints:
 * - GET  /api/scale/weight
 *   Reads the current scale weight (when enabled).
 *
 * - POST /api/receptions/receive
 *   Creates a roll from:
 *   - an import container line (import_container_item_id), or
 *   - a purchase order line (purchase_order_line_id).
 *   Then attempts to print the label if the printer is enabled.
 *
 * @return bool true si manejó la ruta; false si no corresponde a este módulo
 */
function handleApiRoutes(
    string $path,
    string $method,
    ReceptionService $service,
    ScaleService $scale,
    PrintService $printer,
    string $currentOperatorName
): bool {
    // Impresión en una impresora de red (IP:9100) desde el servidor web: el navegador no puede abrir conexiones TCP,
    // el servidor sí. La IP es la que eligió ESTE computador (cookie unibag_print_ip), validada como IP de red local.
    if ($path === '/api/print/network' && $method === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        $token = (string)($_POST['_csrf'] ?? '');
        $sessionToken = (string)($_SESSION['csrf'] ?? '');
        $cookieToken = (string)($_COOKIE[csrfCookieName()] ?? '');
        if ($token === '' || (!hash_equals($sessionToken, $token) && ($cookieToken === '' || !hash_equals($cookieToken, $token)))) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Sesión vencida: recarga la página (F5) y vuelve a imprimir.'], JSON_UNESCAPED_UNICODE);
            return true;
        }
        if (empty($_SESSION['auth_user_id']) && empty($_SESSION['user_id']) && empty($_SESSION['operator_name'])) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Inicia sesión para imprimir.'], JSON_UNESCAPED_UNICODE);
            return true;
        }
        $target = PrintService::parseNetworkPrinter((string)($_POST['printer_ip'] ?? '')) ?? PrintService::clientNetworkPrinter();
        if ($target === null) {
            echo json_encode(['ok' => false, 'error' => 'IP de impresora no válida (solo red local, puerto 9100).'], JSON_UNESCAPED_UNICODE);
            return true;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (isset($_POST['check'])) {
            $fp = @stream_socket_client('tcp://' . $target['host'] . ':' . $target['port'], $errno, $errstr, 2.5);
            $ok = is_resource($fp);
            if ($ok) {
                fclose($fp);
            }
            echo json_encode(['ok' => $ok, 'printer' => $target['host'] . ':' . $target['port'],
                'error' => $ok ? null : 'El servidor no llega a ' . $target['host'] . ':' . $target['port'] . '.'], JSON_UNESCAPED_UNICODE);
            return true;
        }
        $content = (string)($_POST['content'] ?? '');
        if ($content === '' || strlen($content) > 5_000_000) {
            echo json_encode(['ok' => false, 'error' => 'Trabajo de impresión vacío o demasiado grande.'], JSON_UNESCAPED_UNICODE);
            return true;
        }
        $language = strtolower((string)($_POST['language'] ?? 'epl'));
        // ZPL viaja en UTF-8; EPL y TSPL en un byte por carácter (Latin-1), igual que el puente y el navegador.
        if ($language !== 'zpl') {
            $converted = @mb_convert_encoding($content, 'ISO-8859-1', 'UTF-8');
            if (is_string($converted)) {
                $content = $converted;
            }
        }
        $copies = max(1, min(50, (int)($_POST['copies'] ?? 1)));
        echo json_encode($printer->sendNetworkRaw($target, $content, $copies), JSON_UNESCAPED_UNICODE);
        return true;
    }

    if ($path === '/api/scale/weight' && $method === 'GET') {
        // Endpoint de lectura de balanza: entrega JSON de estado (ok=true con peso o ok=false con detalle)
        header('Content-Type: application/json; charset=utf-8');
        $result = $scale->readWeightKg();
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        return true;
    }

    if ($path === '/api/receptions/receive' && $method === 'POST') {
        // Endpoint de recepción: crea bobina y retorna JSON para que la UI pueda:
        // - abrir/mostrar etiqueta
        // - confirmar si se imprimió automáticamente o mostrar el error
        // ---
        // Reception endpoint: creates a roll and returns JSON so the UI can:
        // - open/display the label
        // - confirm auto-print success or show the error
        requireCsrf();
        header('Content-Type: application/json; charset=utf-8');

        $lineId = isset($_POST['purchase_order_line_id']) ? (int)$_POST['purchase_order_line_id'] : 0;
        $containerItemId = isset($_POST['import_container_item_id']) ? (int)$_POST['import_container_item_id'] : 0;
        $warehouseId = isset($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : 0;
        // Se acepta coma o punto decimal ("15,5" o "15.5"). Antes "15,5" se leía como 15.
        $weight = isset($_POST['weight_kg']) ? (float)str_replace(',', '.', trim((string)$_POST['weight_kg'])) : 0.0;
        $receivedQty = isset($_POST['received_qty']) ? (float)$_POST['received_qty'] : 1.0;
        $receptionMode = isset($_POST['reception_mode']) ? (string)$_POST['reception_mode'] : 'QUANTITY';

        if ($containerItemId > 0) {
            // Recepción desde importación (contenedor).
            // ---
            // Receive from import container line.
            $result = $service->createRollFromImportContainerLine($containerItemId, $warehouseId, $weight, $currentOperatorName, $receivedQty, $receptionMode);
        } else {
            // Recepción desde orden de compra.
            // ---
            // Receive from purchase order line.
            $result = $service->createRollFromPurchaseOrderLine($lineId, $warehouseId, $weight, $currentOperatorName, $receivedQty, $receptionMode);
        }

        if ($result['ok'] !== true) {
            // Error de validación/regla de negocio: 422 para que el front lo trate como fallo esperado.
            // ---
            // Validation/business-rule error: 422 so the frontend treats it as an expected failure.
            http_response_code(422);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return true;
        }

        $rollId = (int)$result['id'];
        $batch = saveReceptionLabelBatch($result, $service, $printer);
        $savedBatch = $_SESSION['reception_label_batches'][$batch['token']];
        $roll = $service->getRoll($rollId);
        $rollCode = is_array($roll) ? (string)($roll['roll_code'] ?? '') : '';
        $labelUrl = count($savedBatch['ids']) > 1
            ? '/reception/labels/' . $batch['token']
            : '/rolls/' . $rollId . '/label';

        $clientQueued = ($savedBatch['client_queued'] ?? '0') === '1';
        $inlinePrintJob = ($batch['printed'] !== '1' && $clientQueued)
            ? PrintService::popLastClientJob()
            : null;

        echo json_encode([
            'ok' => true,
            'id' => $rollId,
            'ids' => $savedBatch['ids'],
            'roll_code' => $rollCode !== '' ? $rollCode : ('#' . $rollId),
            'label_url' => $labelUrl,
            'print_url' => $labelUrl . '?auto_print=1',
            'server_print_url' => '/rolls/' . $rollId . '/label?server_print=1',
            'printed' => $batch['printed'] === '1',
            'client_print' => $inlinePrintJob !== null,
            'print_job' => $inlinePrintJob,
            'print_error' => $inlinePrintJob !== null ? '' : $savedBatch['print_error'],
            'sync_error' => $result['sync_error'] ?? null,
        ], JSON_UNESCAPED_UNICODE);
        return true;
    }

    if (preg_match('#^/api/rolls/(\d+)/delete$#', $path, $m) === 1 && $method === 'POST') {
        requireCsrf();
        header('Content-Type: application/json; charset=utf-8');
        $rollId = (int)$m[1];
        $result = $service->deleteReceivedRoll($rollId);
        if (($result['ok'] ?? false) !== true) {
            http_response_code(422);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        return true;
    }

    return false;
}
