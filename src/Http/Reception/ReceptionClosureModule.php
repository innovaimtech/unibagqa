<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Cierre Formal de Recepción y Auditoría (src/Http/Reception/ReceptionClosureModule.php)
// =============================================================================

function handleReceptionClosureRoutes(string $path, string $method, ReceptionService $service, string $currentOperatorName): bool
{
if (preg_match('#^/import-containers/(\\d+)/(?:close|archive)$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $containerId = (int)$m[1];
    $reason = trim((string)($_POST['reason'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $userId = (int)($_SESSION['auth_user_id'] ?? $_SESSION['user_id'] ?? 1);
    $res = $service->closeImportContainerReception($containerId, $userId, $reason, $notes);
    if ($res['ok'] === true) {
        header('Location: /import-containers/' . $containerId . '?closed=1');
        exit;
    }
    http_response_code(400);
    render('Error', '<div class="err">' . h($res['error'] ?? 'Error al cerrar el cargamento') . '</div><div style="margin-top:12px"><a class="btn secondary" href="/import-containers/' . $containerId . '">Volver</a></div>');
    exit;
}

if (preg_match('#^/purchase-orders/(\\d+)/(?:close|archive)$#', $path, $m) === 1 && $method === 'POST') {
    requireCsrf();
    $purchaseOrderId = (int)$m[1];
    $reason = trim((string)($_POST['reason'] ?? ''));
    $notes = trim((string)($_POST['notes'] ?? ''));
    $userId = (int)($_SESSION['auth_user_id'] ?? $_SESSION['user_id'] ?? 1);
    $res = $service->closePurchaseOrderReception($purchaseOrderId, $userId, $reason, $notes);
    if ($res['ok'] === true) {
        header('Location: /purchase-orders/' . $purchaseOrderId . '?closed=1');
        exit;
    }
    http_response_code(400);
    render('Error', '<div class="err">' . h($res['error'] ?? 'Error al cerrar la recepción') . '</div><div style="margin-top:12px"><a class="btn secondary" href="/purchase-orders/' . $purchaseOrderId . '">Volver</a></div>');
    exit;
}

    return false;
}
