<?php

declare(strict_types=1);

function handleApiRoutes(
    string $path,
    string $method,
    ReceptionService $service,
    ScaleService $scale,
    PrintService $printer,
    string $currentOperatorName
): bool {
    if ($path === '/api/scale/weight' && $method === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        $result = $scale->readWeightKg();
        if ($result['ok'] !== true) {
            http_response_code(502);
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        return true;
    }

    if ($path === '/api/receptions/receive' && $method === 'POST') {
        requireCsrf();
        header('Content-Type: application/json; charset=utf-8');

        $lineId = isset($_POST['purchase_order_line_id']) ? (int)$_POST['purchase_order_line_id'] : 0;
        $containerItemId = isset($_POST['import_container_item_id']) ? (int)$_POST['import_container_item_id'] : 0;
        $warehouseId = isset($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : 0;
        $weight = isset($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : 0.0;
        $receivedQty = isset($_POST['received_qty']) ? (float)$_POST['received_qty'] : 1.0;
        $receptionMode = isset($_POST['reception_mode']) ? (string)$_POST['reception_mode'] : 'QUANTITY';

        if ($containerItemId > 0) {
            $result = $service->createRollFromImportContainerLine($containerItemId, $warehouseId, $weight, $currentOperatorName, $receivedQty, $receptionMode);
        } else {
            $result = $service->createRollFromPurchaseOrderLine($lineId, $warehouseId, $weight, $currentOperatorName, $receivedQty, $receptionMode);
        }

        if ($result['ok'] !== true) {
            http_response_code(422);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            return true;
        }

        $rollId = (int)$result['id'];
        $printed = false;
        $printError = null;
        if ($printer->isEnabled()) {
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
