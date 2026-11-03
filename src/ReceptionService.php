<?php

declare(strict_types=1);

// =============================================================================
// Servicio principal · Recepción / Producción / Inventario / Bonificaciones
//
// Esta clase es el “núcleo” de lógica de negocio del proyecto. Agrupa:
// - Operaciones de recepción de bobinas (integración con compras/importaciones del ERP).
// - Inventario por bodega (rolls/pallets/boxes) y toma de inventario.
// - KPIs y dashboard de producción (métricas, merma, semielaborados).
// - Reportes/consultas para bonificaciones (Flexo/Seri/CYS).
// - Aseguramiento y migraciones livianas de esquema (tablas/columnas) en TRZ.
//
// Arquitectura:
// - $pdo     => conexión TRZ (base de datos del sistema / app).
// - $erpPdo  => conexión ERP (producción / planificación / soporte).
//
// Importante:
// - El proyecto no usa framework; el router crea esta clase y la inyecta en módulos HTTP.
// - Se implementan “ensure*Schema” para mantener compatibilidad en despliegues donde la BD
//   puede venir con versiones distintas.
//
// ---
//
// Main Service · Reception / Production / Inventory / Bonuses
//
// This class is the core business-logic layer of the project. It groups:
// - Roll reception operations (integration with ERP purchases/imports).
// - Warehouse inventory (rolls/pallets/boxes) and inventory count flows.
// - Production KPIs and dashboard (metrics, waste, semi-finished).
// - Reports/queries for bonuses (Flexo/Seri/CYS).
// - Lightweight schema assurance/migrations (tables/columns) in TRZ.
//
// Architecture:
// - $pdo     => TRZ connection (application/system database).
// - $erpPdo  => ERP connection (production/planning/support database).
//
// Important:
// - This project does not use a framework; the router instantiates this class and injects it into HTTP modules.
// - Several “ensure*Schema” methods exist to keep compatibility across deployments with different DB versions.
// =============================================================================

require_once __DIR__ . '/InventoryCountService.php';
require_once __DIR__ . '/RollReceptionService.php';
require_once __DIR__ . '/BonusConfigurationService.php';

require_once __DIR__ . '/Services/Reception/PurchaseOrderService.php';
require_once __DIR__ . '/Services/Reception/ImportContainerService.php';
require_once __DIR__ . '/Services/Reception/ToolingService.php';
require_once __DIR__ . '/Services/Reception/RollStockService.php';
require_once __DIR__ . '/Services/Production/WasteService.php';
require_once __DIR__ . '/Services/Production/MachineShiftService.php';
require_once __DIR__ . '/Services/Reception/ReceptionSchemaService.php';
require_once __DIR__ . '/Services/Reception/ErpSyncService.php';
require_once __DIR__ . '/Services/Reception/WorkOrderErpService.php';
require_once __DIR__ . '/Services/Reception/WarehouseInventoryService.php';
require_once __DIR__ . '/Services/Reception/ReceptionReportingService.php';
require_once __DIR__ . '/Services/Reception/BonusCalculationService.php';

/**
 * Servicio principal de acceso a datos y operaciones del dominio.
 *
 * Nota: la clase mantiene caches en memoria (por request) para disminuir roundtrips
 * hacia el ERP en pantallas que requieren muchas resoluciones de nombres/IDs.
 *
 * ---
 *
 * Main domain service for data access and operations.
 *
 * Note: the class keeps in-memory caches (per request) to reduce roundtrips
 * to ERP on screens that require many name/ID resolutions.
 */
final class ReceptionService
{
    use PurchaseOrderServiceTrait;
    use ImportContainerServiceTrait;
    use ToolingServiceTrait;
    use RollStockServiceTrait;
    use WasteServiceTrait;
    use MachineShiftReceptionTrait;

    use ReceptionSchemaServiceTrait;
    use ErpSyncServiceTrait;
    use WorkOrderErpServiceTrait;
    use WarehouseInventoryServiceTrait;
    use ReceptionReportingServiceTrait;
    use BonusCalculationServiceTrait;
    public const MAX_WAREHOUSE_TRACEABILITY_LABELS = 50;
    /** Peso mínimo (kg) para registrar una bobina por peso e imprimir su etiqueta. */
    public const MIN_ROLL_WEIGHT_KG = 15.0;
    /** Peso máximo (kg) aceptado en una recepción: protege contra lecturas o datos absurdos. */
    public const MAX_ROLL_WEIGHT_KG = 5000.0;
    private const LABEL_BATCH_FIELDS = [
        'warehouse_id', 'item_code', 'item_description', 'entity_type', 'operator_name',
        'unit_name', 'total_packages', 'quantity', 'lot_number', 'location_ref', 'order_ref',
    ];
    private const RECEPTION_SCHEMA_VERSION = 'reception_v10';
    private const PRODUCTION_WAREHOUSE_SYNC_VERSION = 'production_warehouse_sync_v1';
    private const ROLL_WAREHOUSE_TRACEABILITY_SYNC_VERSION = 'roll_warehouse_traceability_sync_v1';
    private const ROLL_ERP_STOCK_SYNC_VERSION = 'roll_erp_stock_sync_v1';
    private static bool $schemaEnsured = false;
    private bool $erpWarehousesSynced = false;
    private bool $erpProductionPlanSynced = false;
    private bool $helperScoreSchemaReady = false;
    private ?PDO $legacyErpPdo = null;
    private ?string $legacyErpConnectionError = null;

    /** @var array<int, array<string, mixed>> */
    private array $erpItemsCache = [];

    /** @var array<int, string> */
    private array $erpSuppliersCache = [];

    /** @var array<int, array{name:string,country_name:string,supplier_type:string}> */
    private array $erpSupplierMetaCache = [];

    /** @var array<int, string> */
    private array $erpPurchaseOrdersCache = [];

    /** @var array<int, array{code:string,eta_plant:string}> */
    private array $erpImportContainersCache = [];

    /** @var array<string, list<int>|null> */
    private array $erpEquipotypeIdsByBonusCache = [];

    /** @var array<string, list<int>|null> */
    private array $erpEquipoIdsByBonusCache = [];

    private array $erpEquipotypeIdsByFeatureCache = [];

    /** @var array<string, array{fname: string, lname: string}>|null */
    private ?array $workersByNormNameCache = null;

    /** @var array<string, array<string, bool>> */
    private array $erpColumnExistsCache = [];

    /** @var array<string, bool> */
    private array $erpTableExistsCache = [];

    private InventoryCountService $inventoryCountService;
    private RollReceptionService $rollReceptionService;

    /**
     * Constructor.
     *
     * Inicializa servicios internos y asegura el esquema mínimo requerido:
     * - Migra/asegura tablas/columnas de recepción e inventario en TRZ.
     * - Sincroniza compatibilidad para datos legacy (ej. bodegas en producción).
     * - Asegura catálogos/esquemas auxiliares (máquinas, merma, bonos).
     *
     * ---
     *
     * Constructor.
     *
     * Initializes internal services and ensures the minimum required schema:
     * - Migrates/ensures reception and inventory tables/columns in TRZ.
     * - Syncs legacy compatibility (e.g., production roll warehouses).
     * - Ensures auxiliary catalogs/schemas (machines, waste, bonuses).
     */
    public function __construct(private PDO $pdo, private PDO $erpPdo)
    {
        if (class_exists('Db') && method_exists('Db', 'legacyErpPdoOrNull')) {
            try {
                $legacyPdo = Db::legacyErpPdoOrNull();
                if ($legacyPdo instanceof PDO) {
                    $this->legacyErpPdo = $legacyPdo;
                }
            } catch (Throwable $e) {
                $this->legacyErpPdo = null;
                $this->legacyErpConnectionError = $e->getMessage();
            }
        }
        $this->inventoryCountService = new InventoryCountService($this->pdo, $this->erpPdo);
        $this->rollReceptionService = new RollReceptionService($this->pdo);
        $this->ensureTrzBaseSchema();
        $this->ensureAppSettingsSchema();
        if (!self::$schemaEnsured) {
            if ($this->getAppSetting('reception_schema_version', '') !== self::RECEPTION_SCHEMA_VERSION) {
                $this->ensureReceptionSchema();
                $this->setAppSetting('reception_schema_version', self::RECEPTION_SCHEMA_VERSION);
            }
            if ($this->getAppSetting('production_warehouse_sync_version', '') !== self::PRODUCTION_WAREHOUSE_SYNC_VERSION) {
                $this->syncLegacyProductionRollWarehouses();
                $this->setAppSetting('production_warehouse_sync_version', self::PRODUCTION_WAREHOUSE_SYNC_VERSION);
            }
            if ($this->getAppSetting('production_machine_catalog_version', '') !== '1.0.0') {
                $this->ensureProductionMachineCatalog();
                $this->setAppSetting('production_machine_catalog_version', '1.0.0');
            }
            if ($this->getAppSetting('waste_schema_version', '') !== '1.0.0') {
                $this->ensureWasteSchema();
                $this->setAppSetting('waste_schema_version', '1.0.0');
            }
            if ($this->getAppSetting('bonus_schema_version', '') !== '1.0.0') {
                $this->ensureBonusSchema();
                $this->setAppSetting('bonus_schema_version', '1.0.0');
            }
            if ($this->getAppSetting('roll_warehouse_traceability_sync_version', '') !== self::ROLL_WAREHOUSE_TRACEABILITY_SYNC_VERSION) {
                $this->syncLegacyRollWarehouseTraceabilityEntries();
                $this->setAppSetting('roll_warehouse_traceability_sync_version', self::ROLL_WAREHOUSE_TRACEABILITY_SYNC_VERSION);
            }
            if ($this->getAppSetting('roll_erp_stock_sync_version', '') !== self::ROLL_ERP_STOCK_SYNC_VERSION) {
                $this->syncLegacyErpInventoryForReceivedRolls();
                $this->setAppSetting('roll_erp_stock_sync_version', self::ROLL_ERP_STOCK_SYNC_VERSION);
            }
            self::$schemaEnsured = true;
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getErpPdo(): PDO
    {
        return $this->erpPdo;
    }

}
