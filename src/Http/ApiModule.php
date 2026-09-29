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
    if ($path === '/api/scale/weight' && $method === 'GET') {
        // Endpoint de lectura de balanza: útil para formularios de recepción y flujos con peso.
        // ---
        // Scale read endpoint: useful for reception forms and weight-based flows.
        header('Content-Type: application/json; charset=utf-8');
        $result = $scale->readWeightKg();
        if ($result['ok'] !== true) {
            http_response_code(502);
        }
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
        $weight = isset($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : 0.0;
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
        $printed = false;
        $printError = null;
        if ($printer->isEnabled()) {
            // Impresión “best effort”: la creación de la bobina no depende de imprimir.
            // ---
            // Best-effort printing: roll creation does not depend on printing.
            $roll = $service->getRoll($rollId);
            if (is_array($roll)) {
                $printResult = $printer->printRollLabel($roll);
                $printed = ($printResult['ok'] ?? false) === true;
                $printError = $printed ? null : (string)($printResult['error'] ?? 'No se pudo imprimir.');
            } else {
                $printError = 'No se encontró la bobina para imprimir.';
            }
        }

        echo json_encode([
            'ok' => true,
            'id' => $rollId,
            'label_url' => '/rolls/' . $rollId . '/label?auto_print=1',
            'printed' => $printed,
            'print_error' => $printError,
        ], JSON_UNESCAPED_UNICODE);
        return true;
    }

    return false;
}
