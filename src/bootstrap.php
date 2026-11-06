<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Bootstrap & Autoloader Central · Unibag ERP & Trazabilidad (src/bootstrap.php)
 * =============================================================================
 *
 * Responsabilidades:
 * 1. Cargar vendor/autoload.php automáticamente si Composer está instalado.
 * 2. Registrar un autoloader nativo compatible con PSR-4 / mapa de directorios
 *    (src/, src/Services/Reception/, src/Services/Production/) para que las
 *    clases de dominio se resuelvan sin cadenas manuales de require_once.
 * 3. Cargar ordenadamente los módulos HTTP funcionales de la capa src/Http/.
 */

$unibagVendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($unibagVendorAutoload)) {
    require_once $unibagVendorAutoload;
}

spl_autoload_register(static function (string $class): void {
    $relativeClass = ltrim($class, '\\');
    if (str_starts_with($relativeClass, 'Unibag\\')) {
        $relativeClass = substr($relativeClass, strlen('Unibag\\'));
    }

    $psrPath = __DIR__ . '/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($psrPath)) {
        require_once $psrPath;
        return;
    }

    $shortName = basename(str_replace('\\', '/', $relativeClass));
    $candidateDirs = [
        __DIR__,
        __DIR__ . '/Services/Reception',
        __DIR__ . '/Services/Production',
    ];
    foreach ($candidateDirs as $dir) {
        $candidate = $dir . '/' . $shortName . '.php';
        if (is_file($candidate)) {
            require_once $candidate;
            return;
        }
    }
});

// -----------------------------------------------------------------------------
// Núcleo Base & Servicios Principales
// -----------------------------------------------------------------------------
$unibagCoreFiles = [
    __DIR__ . '/Env.php',
    __DIR__ . '/RequestProfiler.php',
    __DIR__ . '/SchemaGate.php',
    __DIR__ . '/Db.php',
    __DIR__ . '/SimpleXlsx.php',
    __DIR__ . '/ScaleService.php',
    __DIR__ . '/PrintService.php',
    __DIR__ . '/ReceptionService.php',
    __DIR__ . '/ProductionService.php',
    __DIR__ . '/BonusConfigurationService.php',
    __DIR__ . '/MonthlyPresentationService.php',
    __DIR__ . '/HandleConsumptionService.php',
    __DIR__ . '/TraceabilityService.php',
    __DIR__ . '/FabricationDossierService.php',
    __DIR__ . '/WarehouseVisibility.php',
    __DIR__ . '/MailService.php',
    __DIR__ . '/TraceableRollLookup.php',
    __DIR__ . '/PackagingBillingMail.php',
    __DIR__ . '/TraceabilityDocxReport.php',
];

foreach ($unibagCoreFiles as $coreFile) {
    require_once $coreFile;
}

// -----------------------------------------------------------------------------
// Módulos HTTP Organizados por Dominio Funcional
// -----------------------------------------------------------------------------
$unibagHttpModules = [
    // Helpers de Kernel, Layout, Permisos, Visor de Diseños y Autenticación
    __DIR__ . '/Http/KernelHelpers.php',
    __DIR__ . '/Http/AuthModule.php',
    __DIR__ . '/Http/ApiModule.php',

    // Bonificaciones y Configuración Mensual (src/Http/Bonuses/)
    __DIR__ . '/Http/Bonuses/BonusesRouter.php',

    // Dashboard, Informes Ejecutivos, Colaciones y Auditoría (src/Http/Reports/)
    __DIR__ . '/Http/Reports/ReportsRouter.php',

    // Bodegas, Inventario, Stock y Abastecimiento
    __DIR__ . '/Http/WarehousesModule.php',
    __DIR__ . '/Http/InventoryModule.php',
    __DIR__ . '/Http/MaterialSupplyModule.php',
    __DIR__ . '/Http/DispatchModule.php',
    __DIR__ . '/Http/WarehouseBonusModule.php',
    __DIR__ . '/Http/ExpectedStockModule.php',
    __DIR__ . '/Http/StockAndOperationsModule.php',

    // Recepción, Producción, OTs, Trazabilidad, Mermas y Tintas
    __DIR__ . '/Http/Reception/ReceptionRouter.php',
    __DIR__ . '/Http/ProductionModule.php',
    __DIR__ . '/Http/Production/ProductionRouter.php',
    __DIR__ . '/Http/WorkOrders/WorkOrderErpModule.php',
    __DIR__ . '/Http/TraceabilityModule.php',
    __DIR__ . '/Http/Waste/WasteManagementModule.php',
    __DIR__ . '/Http/Chemicals/InksWeighingModule.php',
];

foreach ($unibagHttpModules as $httpModule) {
    if (is_file($httpModule)) {
        require_once $httpModule;
    }
}

