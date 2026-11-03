<?php

declare(strict_types=1);

// =============================================================================
// Enrutador HTTP · Área de Recepción y Bodega Materia Prima (src/Http/Reception/ReceptionRouter.php)
// =============================================================================

require_once __DIR__ . '/ReceptionLabelsModule.php';
require_once __DIR__ . '/ReceptionClosureModule.php';
require_once __DIR__ . '/PurchaseOrdersModule.php';
require_once __DIR__ . '/ImportContainersModule.php';
require_once __DIR__ . '/RollGenerationModule.php';

function handleReceptionRouter(
    string $path,
    string $method,
    ReceptionService $service,
    ?ScaleService $scale = null,
    ?PrintService $printer = null,
    string $currentOperatorName = '',
    ?PDO $trzPdo = null
): bool {
    if ($printer === null) {
        $printer = new PrintService($_ENV);
    }
    if ($currentOperatorName === '') {
        $currentOperatorName = trim((string)($_SESSION['operator_name'] ?? $_SESSION['auth_display_name'] ?? 'Operador'));
        if ($currentOperatorName === '') {
            $currentOperatorName = 'Operador';
        }
    }

    if ($path === '/reception') {
        $targetQuery = $_GET;
        if (!isset($targetQuery['status'])) {
            $targetQuery['status'] = 'active';
        }
        if (!isset($targetQuery['supplier_type'])) {
            $targetQuery['supplier_type'] = 'NATIONAL';
        }
        redirectResponse('/purchase-orders?' . http_build_query($targetQuery), 302);
    }

    if (handleReceptionClosureRoutes($path, $method, $service, $currentOperatorName)) {
        return true;
    }

    if (handleReceptionLabelsRoutes($path, $method, $service, $printer, $trzPdo)) {
        return true;
    }

    if (handlePurchaseOrdersRoutes($path, $method, $service, $printer, $currentOperatorName)) {
        return true;
    }

    if (handleImportContainersRoutes($path, $method, $service, $printer, $currentOperatorName)) {
        return true;
    }

    if (handleRollGenerationRoutes($path, $method, $service, $printer, $currentOperatorName)) {
        return true;
    }

    return false;
}