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
    private const RECEPTION_SCHEMA_VERSION = 'reception_v10';
    private const PRODUCTION_WAREHOUSE_SYNC_VERSION = 'production_warehouse_sync_v1';
    private static bool $schemaEnsured = false;
    private bool $erpWarehousesSynced = false;
    private bool $erpProductionPlanSynced = false;

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
            self::$schemaEnsured = true;
        }
    }

    /**
     * Asegura que exista el esquema base de TRZ antes de aplicar migraciones incrementales.
     *
     * Este proyecto mantiene un esquema “bootstrap” en database/schema.sql para
     * instalaciones nuevas o ambientes sin trazabilidad inicializada.
     *
     * ---
     *
     * Ensures the TRZ base schema exists before running incremental migrations.
     *
     * This project keeps a “bootstrap” schema in database/schema.sql for fresh
     * installs or environments where traceability schema is not initialized.
     */
    private function ensureTrzBaseSchema(): void
    {
        if ($this->tableExists('rolls') && $this->tableExists('warehouses') && $this->tableExists('skus')) {
            return;
        }

        $schemaPath = __DIR__ . '/../database/schema.sql';
        if (!is_file($schemaPath)) {
            return;
        }

        $sql = (string)file_get_contents($schemaPath);
        $sql = trim($sql);
        if ($sql === '') {
            return;
        }

        $statements = preg_split('/;\\s*(?:\\r?\\n|$)/', $sql) ?: [];
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            try {
                $this->pdo->exec($statement);
            } catch (Throwable) {
                continue;
            }
        }
    }

    /**
     * Asegura la tabla app_settings en TRZ.
     *
     * Esta tabla se utiliza como “key-value store” para:
     * - versionar migraciones livianas (schema_version)
     * - guardar flags/configuración de la app
     *
     * ---
     *
     * Ensures the app_settings table in TRZ.
     *
     * This table acts as a key-value store for:
     * - lightweight migration versioning (schema_version)
     * - application flags/configuration
     */
    private function ensureAppSettingsSchema(): void
    {
        if ($this->tableExists('app_settings')) {
            return;
        }
        try {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS app_settings (
                    setting_key VARCHAR(190) NOT NULL,
                    setting_value TEXT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (setting_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable) {
            return;
        }
    }

    /**
     * Asegura/migra el esquema de “recepción” en la BD TRZ.
     *
     * Qué hace típicamente:
     * - Agrega columnas faltantes en rolls para vínculo con compras/importaciones y trazabilidad.
     * - Crea tablas auxiliares (solicitudes de materiales, merma, capacidades, pallets/cajas, etc.).
     * - Aplica defaults de configuración inicial en app_settings cuando corresponde.
     *
     * ---
     *
     * Ensures/migrates the “reception” schema in the TRZ database.
     *
     * Typical actions:
     * - Add missing columns to rolls for purchase/import linking and traceability.
     * - Create auxiliary tables (material requests, waste, capacities, pallets/boxes, etc.).
     * - Apply initial configuration defaults in app_settings when applicable.
     */
    private function ensureReceptionSchema(): void
    {
        if (!$this->columnExists('rolls', 'purchase_order_id')) {
            $this->pdo->exec("ALTER TABLE rolls ADD COLUMN purchase_order_id BIGINT UNSIGNED NULL AFTER status");
        }
        if (!$this->columnExists('rolls', 'purchase_order_line_id')) {
            $this->pdo->exec("ALTER TABLE rolls ADD COLUMN purchase_order_line_id BIGINT UNSIGNED NULL AFTER purchase_order_id");
        }
        if (!$this->columnExists('rolls', 'import_container_id')) {
            $this->pdo->exec("ALTER TABLE rolls ADD COLUMN import_container_id BIGINT UNSIGNED NULL AFTER purchase_order_line_id");
        }
        if (!$this->columnExists('rolls', 'import_container_item_id')) {
            $this->pdo->exec("ALTER TABLE rolls ADD COLUMN import_container_item_id BIGINT UNSIGNED NULL AFTER import_container_id");
        }
        if (!$this->columnExists('rolls', 'supplier_id')) {
            $this->pdo->exec("ALTER TABLE rolls ADD COLUMN supplier_id BIGINT UNSIGNED NULL AFTER import_container_item_id");
        }
        if (!$this->columnExists('rolls', 'current_work_order_id')) {
            $this->pdo->exec("ALTER TABLE rolls ADD COLUMN current_work_order_id BIGINT UNSIGNED NULL AFTER supplier_id");
        }
        if (!$this->columnExists('rolls', 'received_qty')) {
            $this->pdo->exec("ALTER TABLE rolls ADD COLUMN received_qty DECIMAL(12,3) NOT NULL DEFAULT 1.000 AFTER weight_kg");
        }
        if (!$this->columnExists('rolls', 'reception_mode')) {
            $this->pdo->exec("ALTER TABLE rolls ADD COLUMN reception_mode VARCHAR(20) NOT NULL DEFAULT 'QUANTITY' AFTER received_qty");
        }
        if (!$this->columnExists('rolls', 'parent_roll_id')) {
            $this->pdo->exec("ALTER TABLE rolls ADD COLUMN parent_roll_id BIGINT UNSIGNED NULL AFTER current_work_order_id");
        }
        if (!$this->columnExists('rolls', 'source_work_order_id')) {
            $this->pdo->exec("ALTER TABLE rolls ADD COLUMN source_work_order_id BIGINT UNSIGNED NULL AFTER parent_roll_id");
        }
        if (!$this->columnExists('rolls', 'process_stage')) {
            $this->pdo->exec("ALTER TABLE rolls ADD COLUMN process_stage VARCHAR(20) NOT NULL DEFAULT 'RAW' AFTER source_work_order_id");
        }

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS work_order_material_requests (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                work_order_id BIGINT UNSIGNED NOT NULL,
                request_type VARCHAR(20) NOT NULL DEFAULT 'ROLL',
                requested_item VARCHAR(120) NOT NULL,
                requested_qty DECIMAL(12,3) NULL,
                requested_unit VARCHAR(20) NOT NULL DEFAULT 'Unid.',
                request_notes VARCHAR(255) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
                requested_by VARCHAR(120) NOT NULL,
                chemical_id INT UNSIGNED NULL,
                accepted_by VARCHAR(120) NULL,
                accepted_at TIMESTAMP NULL DEFAULT NULL,
                delivered_roll_id BIGINT UNSIGNED NULL,
                delivered_by VARCHAR(120) NULL,
                delivered_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_material_requests_wo (work_order_id),
                KEY idx_material_requests_status (status),
                CONSTRAINT fk_material_requests_wo FOREIGN KEY (work_order_id) REFERENCES work_orders(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        if (!$this->columnExists('work_order_material_requests', 'requested_roll_id')) {
            $this->pdo->exec("ALTER TABLE work_order_material_requests ADD COLUMN requested_roll_id BIGINT UNSIGNED NULL AFTER requested_by");
        }
        if (!$this->columnExists('work_order_material_requests', 'requested_group_key')) {
            $this->pdo->exec("ALTER TABLE work_order_material_requests ADD COLUMN requested_group_key VARCHAR(190) NULL AFTER requested_roll_id");
        }
        if (!$this->columnExists('work_order_material_requests', 'delivered_qty')) {
            $this->pdo->exec("ALTER TABLE work_order_material_requests ADD COLUMN delivered_qty DECIMAL(12,3) NOT NULL DEFAULT 0.000 AFTER requested_qty");
        }
        if (!$this->columnExists('work_order_material_requests', 'request_type')) {
            $this->pdo->exec("ALTER TABLE work_order_material_requests ADD COLUMN request_type VARCHAR(20) NOT NULL DEFAULT 'ROLL' AFTER work_order_id");
        }
        if (!$this->columnExists('work_order_material_requests', 'requested_unit')) {
            $this->pdo->exec("ALTER TABLE work_order_material_requests ADD COLUMN requested_unit VARCHAR(20) NOT NULL DEFAULT 'Unid.' AFTER requested_qty");
        }
        if (!$this->columnExists('work_order_material_requests', 'chemical_id')) {
            $this->pdo->exec("ALTER TABLE work_order_material_requests ADD COLUMN chemical_id INT UNSIGNED NULL AFTER requested_group_key");
        }
        if (!$this->columnExists('work_order_material_requests', 'accepted_by')) {
            $this->pdo->exec("ALTER TABLE work_order_material_requests ADD COLUMN accepted_by VARCHAR(120) NULL AFTER requested_group_key");
        }
        if (!$this->columnExists('work_order_material_requests', 'accepted_at')) {
            $this->pdo->exec("ALTER TABLE work_order_material_requests ADD COLUMN accepted_at TIMESTAMP NULL DEFAULT NULL AFTER accepted_by");
        }
        if (!$this->columnExists('work_order_material_requests', 'requested_meters')) {
            $this->pdo->exec("ALTER TABLE work_order_material_requests ADD COLUMN requested_meters DECIMAL(12,3) NULL AFTER requested_qty");
        }
        if (!$this->columnExists('work_order_material_requests', 'estimated_roll_qty')) {
            $this->pdo->exec("ALTER TABLE work_order_material_requests ADD COLUMN estimated_roll_qty DECIMAL(12,3) NULL AFTER requested_meters");
        }

        if ($this->tableExists('app_settings') && $this->getAppSetting('roll_request_meter_buffer_percent') === null) {
            $this->setAppSetting('roll_request_meter_buffer_percent', '5');
        }

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS production_wastes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                work_order_id BIGINT UNSIGNED NOT NULL,
                roll_id BIGINT UNSIGNED NULL,
                waste_stage VARCHAR(20) NOT NULL DEFAULT 'PRODUCTION',
                reason VARCHAR(120) NOT NULL,
                weight_kg DECIMAL(10,3) NOT NULL,
                operator_name VARCHAR(120) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_production_wastes_wo (work_order_id),
                KEY idx_production_wastes_roll (roll_id),
                CONSTRAINT fk_production_wastes_wo FOREIGN KEY (work_order_id) REFERENCES work_orders(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS warehouse_capacities (
                warehouse_id INT UNSIGNED NOT NULL,
                capacity_units_total DECIMAL(14,3) NOT NULL DEFAULT 0.000,
                capacity_pallets INT UNSIGNED NOT NULL DEFAULT 0,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (warehouse_id),
                CONSTRAINT fk_warehouse_capacities_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        if (!$this->columnExists('warehouse_capacities', 'capacity_units_total')) {
            $this->pdo->exec("ALTER TABLE warehouse_capacities ADD COLUMN capacity_units_total DECIMAL(14,3) NOT NULL DEFAULT 0.000 AFTER warehouse_id");
        }
        if (!$this->columnExists('warehouse_capacities', 'capacity_pallets')) {
            $this->pdo->exec("ALTER TABLE warehouse_capacities ADD COLUMN capacity_pallets INT UNSIGNED NOT NULL DEFAULT 0 AFTER capacity_units_total");
        }
        if (!$this->columnExists('warehouses', 'erp_storehouse_id')) {
            $this->pdo->exec("ALTER TABLE warehouses ADD COLUMN erp_storehouse_id INT UNSIGNED NULL DEFAULT NULL AFTER id, ADD INDEX idx_warehouses_erp_storehouse_id (erp_storehouse_id)");
        }

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS pallets (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                pallet_code VARCHAR(40) NOT NULL,
                work_order_id BIGINT UNSIGNED NULL,
                source_roll_id BIGINT UNSIGNED NOT NULL,
                final_sku VARCHAR(80) NOT NULL,
                destination_mode VARCHAR(20) NOT NULL,
                customer_order_ref VARCHAR(80) NULL,
                warehouse_id INT UNSIGNED NULL,
                box_count INT UNSIGNED NOT NULL DEFAULT 0,
                operator_name VARCHAR(120) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'CREATED',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_pallets_code (pallet_code),
                KEY idx_pallets_wo (work_order_id),
                KEY idx_pallets_roll (source_roll_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS boxes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                box_code VARCHAR(40) NOT NULL,
                work_order_id BIGINT UNSIGNED NULL,
                source_roll_id BIGINT UNSIGNED NOT NULL,
                pallet_id BIGINT UNSIGNED NULL,
                final_sku VARCHAR(80) NOT NULL,
                units_qty DECIMAL(12,3) NOT NULL,
                destination_mode VARCHAR(20) NOT NULL,
                customer_order_ref VARCHAR(80) NULL,
                warehouse_id INT UNSIGNED NULL,
                operator_name VARCHAR(120) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'CREATED',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_boxes_code (box_code),
                KEY idx_boxes_wo (work_order_id),
                KEY idx_boxes_roll (source_roll_id),
                KEY idx_boxes_pallet (pallet_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS maquila_orders (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                pallet_id BIGINT UNSIGNED NOT NULL,
                work_order_id BIGINT UNSIGNED NULL,
                source_roll_id BIGINT UNSIGNED NULL,
                workshop_name VARCHAR(160) NOT NULL,
                outgoing_weight_kg DECIMAL(12,3) NOT NULL,
                outgoing_box_count INT UNSIGNED NOT NULL DEFAULT 0,
                outgoing_units_qty DECIMAL(12,3) NOT NULL DEFAULT 0.000,
                outgoing_warehouse_id INT UNSIGNED NOT NULL,
                external_warehouse_id INT UNSIGNED NOT NULL,
                return_warehouse_id INT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
                notes VARCHAR(255) NULL,
                operator_name VARCHAR(120) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                closed_at TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_maquila_orders_pallet_status (pallet_id, status),
                KEY idx_maquila_orders_work_order (work_order_id),
                KEY idx_maquila_orders_status (status),
                CONSTRAINT fk_maquila_orders_pallet FOREIGN KEY (pallet_id) REFERENCES pallets(id) ON DELETE CASCADE,
                CONSTRAINT fk_maquila_orders_wo FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS maquila_order_returns (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                maquila_order_id BIGINT UNSIGNED NOT NULL,
                return_weight_kg DECIMAL(12,3) NOT NULL DEFAULT 0.000,
                returned_box_count INT UNSIGNED NOT NULL DEFAULT 0,
                returned_units_qty DECIMAL(12,3) NOT NULL DEFAULT 0.000,
                waste_weight_kg DECIMAL(12,3) NOT NULL DEFAULT 0.000,
                notes VARCHAR(255) NULL,
                operator_name VARCHAR(120) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_maquila_returns_order (maquila_order_id),
                CONSTRAINT fk_maquila_returns_order FOREIGN KEY (maquila_order_id) REFERENCES maquila_orders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS inventory_counts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                warehouse_id INT UNSIGNED NULL,
                warehouse_code INT UNSIGNED NOT NULL,
                warehouse_name VARCHAR(160) NOT NULL,
                total_skus INT UNSIGNED NOT NULL DEFAULT 0,
                total_available_qty DECIMAL(14,3) NOT NULL DEFAULT 0.000,
                total_system_qty DECIMAL(14,3) NOT NULL DEFAULT 0.000,
                total_physical_qty DECIMAL(14,3) NOT NULL DEFAULT 0.000,
                total_diff_qty DECIMAL(14,3) NOT NULL DEFAULT 0.000,
                created_by VARCHAR(120) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_inventory_counts_warehouse (warehouse_code),
                KEY idx_inventory_counts_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS inventory_count_items (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                inventory_count_id BIGINT UNSIGNED NOT NULL,
                sku_code VARCHAR(120) NOT NULL,
                sku_description VARCHAR(255) NOT NULL DEFAULT '',
                article_code VARCHAR(20) NOT NULL DEFAULT '',
                family_color VARCHAR(80) NOT NULL DEFAULT '',
                color_code VARCHAR(80) NOT NULL DEFAULT '',
                height_mm DECIMAL(12,3) NULL,
                grams DECIMAL(12,3) NULL,
                meters DECIMAL(12,3) NULL,
                unit_code VARCHAR(20) NOT NULL DEFAULT 'BOB',
                system_qty DECIMAL(14,3) NOT NULL DEFAULT 0.000,
                physical_qty DECIMAL(14,3) NOT NULL DEFAULT 0.000,
                diff_qty DECIMAL(14,3) NOT NULL DEFAULT 0.000,
                available_qty DECIMAL(14,3) NOT NULL DEFAULT 0.000,
                PRIMARY KEY (id),
                KEY idx_inventory_count_items_count (inventory_count_id),
                KEY idx_inventory_count_items_sku (sku_code),
                CONSTRAINT fk_inventory_count_items_count FOREIGN KEY (inventory_count_id) REFERENCES inventory_counts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        if (!$this->columnExists('inventory_counts', 'total_system_qty')) {
            $this->pdo->exec("ALTER TABLE inventory_counts ADD COLUMN total_system_qty DECIMAL(14,3) NOT NULL DEFAULT 0.000 AFTER total_available_qty");
        }
        if (!$this->columnExists('inventory_counts', 'total_physical_qty')) {
            $this->pdo->exec("ALTER TABLE inventory_counts ADD COLUMN total_physical_qty DECIMAL(14,3) NOT NULL DEFAULT 0.000 AFTER total_system_qty");
        }
        if (!$this->columnExists('inventory_counts', 'total_diff_qty')) {
            $this->pdo->exec("ALTER TABLE inventory_counts ADD COLUMN total_diff_qty DECIMAL(14,3) NOT NULL DEFAULT 0.000 AFTER total_physical_qty");
        }
        if (!$this->columnExists('inventory_count_items', 'article_code')) {
            $this->pdo->exec("ALTER TABLE inventory_count_items ADD COLUMN article_code VARCHAR(20) NOT NULL DEFAULT '' AFTER sku_description");
        }
        if (!$this->columnExists('inventory_count_items', 'family_color')) {
            $this->pdo->exec("ALTER TABLE inventory_count_items ADD COLUMN family_color VARCHAR(80) NOT NULL DEFAULT '' AFTER article_code");
        }
        if (!$this->columnExists('inventory_count_items', 'color_code')) {
            $this->pdo->exec("ALTER TABLE inventory_count_items ADD COLUMN color_code VARCHAR(80) NOT NULL DEFAULT '' AFTER family_color");
        }
        if (!$this->columnExists('inventory_count_items', 'height_mm')) {
            $this->pdo->exec("ALTER TABLE inventory_count_items ADD COLUMN height_mm DECIMAL(12,3) NULL AFTER color_code");
        }
        if (!$this->columnExists('inventory_count_items', 'grams')) {
            $this->pdo->exec("ALTER TABLE inventory_count_items ADD COLUMN grams DECIMAL(12,3) NULL AFTER height_mm");
        }
        if (!$this->columnExists('inventory_count_items', 'meters')) {
            $this->pdo->exec("ALTER TABLE inventory_count_items ADD COLUMN meters DECIMAL(12,3) NULL AFTER grams");
        }
        if (!$this->columnExists('inventory_count_items', 'unit_code')) {
            $this->pdo->exec("ALTER TABLE inventory_count_items ADD COLUMN unit_code VARCHAR(20) NOT NULL DEFAULT 'BOB' AFTER meters");
        }
        if (!$this->columnExists('inventory_count_items', 'system_qty')) {
            $this->pdo->exec("ALTER TABLE inventory_count_items ADD COLUMN system_qty DECIMAL(14,3) NOT NULL DEFAULT 0.000 AFTER unit_code");
        }
        if (!$this->columnExists('inventory_count_items', 'physical_qty')) {
            $this->pdo->exec("ALTER TABLE inventory_count_items ADD COLUMN physical_qty DECIMAL(14,3) NOT NULL DEFAULT 0.000 AFTER system_qty");
        }
        if (!$this->columnExists('inventory_count_items', 'diff_qty')) {
            $this->pdo->exec("ALTER TABLE inventory_count_items ADD COLUMN diff_qty DECIMAL(14,3) NOT NULL DEFAULT 0.000 AFTER physical_qty");
        }

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS cliches (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(60) NOT NULL,
                description VARCHAR(180) NOT NULL,
                location_code VARCHAR(60) NOT NULL,
                location_detail VARCHAR(180) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'AVAILABLE',
                current_work_order_id BIGINT UNSIGNED NULL,
                current_operator_name VARCHAR(120) NULL,
                current_assigned_at TIMESTAMP NULL DEFAULT NULL,
                notes VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_cliches_code (code),
                KEY idx_cliches_status (status),
                KEY idx_cliches_location (location_code),
                KEY idx_cliches_work_order (current_work_order_id),
                CONSTRAINT fk_cliches_work_order FOREIGN KEY (current_work_order_id) REFERENCES work_orders(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS cliche_usage_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                cliche_id BIGINT UNSIGNED NOT NULL,
                work_order_id BIGINT UNSIGNED NULL,
                action_type VARCHAR(20) NOT NULL,
                from_location_code VARCHAR(60) NULL,
                to_location_code VARCHAR(60) NULL,
                operator_name VARCHAR(120) NOT NULL,
                notes VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_cliche_logs_cliche (cliche_id),
                KEY idx_cliche_logs_work_order (work_order_id),
                KEY idx_cliche_logs_action (action_type),
                CONSTRAINT fk_cliche_logs_cliche FOREIGN KEY (cliche_id) REFERENCES cliches(id) ON DELETE CASCADE,
                CONSTRAINT fk_cliche_logs_work_order FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS erp_work_order_sync (
                work_order_id BIGINT UNSIGNED NOT NULL,
                erp_prod_header_id BIGINT UNSIGNED NOT NULL,
                erp_agenda_id BIGINT UNSIGNED NOT NULL,
                erp_worker_ot_id BIGINT UNSIGNED NULL,
                erp_worker_init_id BIGINT UNSIGNED NULL,
                erp_worker_id BIGINT UNSIGNED NULL,
                erp_worker_name VARCHAR(160) NULL,
                erp_user_id BIGINT UNSIGNED NULL,
                erp_user_login VARCHAR(120) NULL,
                erp_prod_number VARCHAR(80) NOT NULL,
                erp_req_id VARCHAR(80) NULL,
                erp_plan_desc VARCHAR(255) NULL,
                erp_plan_date VARCHAR(40) NULL,
                erp_plan_timestamp BIGINT NULL,
                erp_machine_id BIGINT NULL,
                erp_machine_label VARCHAR(120) NULL,
                erp_machine_type_id BIGINT NULL,
                erp_planta_id BIGINT NULL,
                erp_target_qty DECIMAL(12,3) NULL,
                erp_required_meters DECIMAL(12,3) NULL,
                erp_required_meters_source VARCHAR(120) NULL,
                erp_header_status VARCHAR(40) NULL,
                erp_agenda_status VARCHAR(40) NULL,
                erp_agenda_active TINYINT(1) NOT NULL DEFAULT 0,
                erp_worker_status VARCHAR(40) NULL,
                last_synced_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (work_order_id),
                UNIQUE KEY uq_erp_work_order_sync_agenda (erp_agenda_id),
                UNIQUE KEY uq_erp_work_order_sync_header_agenda (erp_prod_header_id, erp_agenda_id),
                KEY idx_erp_work_order_sync_prod_number (erp_prod_number),
                KEY idx_erp_work_order_sync_plan_ts (erp_plan_timestamp),
                CONSTRAINT fk_erp_work_order_sync_wo FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        if (!$this->columnExists('erp_work_order_sync', 'erp_required_meters')) {
            $this->pdo->exec("ALTER TABLE erp_work_order_sync ADD COLUMN erp_required_meters DECIMAL(12,3) NULL AFTER erp_target_qty");
        }
        if (!$this->columnExists('erp_work_order_sync', 'erp_required_meters_source')) {
            $this->pdo->exec("ALTER TABLE erp_work_order_sync ADD COLUMN erp_required_meters_source VARCHAR(120) NULL AFTER erp_required_meters");
        }

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS production_machine_types (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(40) NOT NULL,
                name VARCHAR(120) NOT NULL,
                production_area VARCHAR(30) NOT NULL DEFAULT 'PRODUCTION',
                erp_machine_type_id BIGINT NULL,
                display_order INT UNSIGNED NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_production_machine_types_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS production_machines (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                machine_type_id INT UNSIGNED NOT NULL,
                code VARCHAR(40) NOT NULL,
                name VARCHAR(120) NOT NULL,
                production_area VARCHAR(30) NOT NULL DEFAULT 'PRODUCTION',
                erp_machine_id BIGINT NULL,
                plant_label VARCHAR(120) NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_production_machines_code (code),
                KEY idx_production_machines_type (machine_type_id),
                KEY idx_production_machines_erp (erp_machine_id),
                CONSTRAINT fk_production_machines_type FOREIGN KEY (machine_type_id) REFERENCES production_machine_types(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS production_shift_sessions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                machine_id INT UNSIGNED NOT NULL,
                work_order_id BIGINT UNSIGNED NULL,
                operator_name VARCHAR(120) NOT NULL,
                helper_name VARCHAR(120) NULL,
                shift_label VARCHAR(60) NULL,
                process_stage VARCHAR(30) NOT NULL DEFAULT 'PRODUCTION',
                comments TEXT NULL,
                started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ended_at TIMESTAMP NULL DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_shift_sessions_machine_status (machine_id, status),
                KEY idx_shift_sessions_operator_status (operator_name, status),
                KEY idx_shift_sessions_work_order (work_order_id),
                CONSTRAINT fk_shift_sessions_machine FOREIGN KEY (machine_id) REFERENCES production_machines(id),
                CONSTRAINT fk_shift_sessions_work_order FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS production_anilox_catalog (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(40) NOT NULL,
                name VARCHAR(120) NOT NULL,
                bcm VARCHAR(40) NULL,
                lpi VARCHAR(40) NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_production_anilox_catalog_code (code),
                KEY idx_production_anilox_catalog_active_sort (is_active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS work_order_anilox_assignments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                work_order_id BIGINT UNSIGNED NOT NULL,
                unit_no TINYINT UNSIGNED NOT NULL,
                color_name VARCHAR(120) NOT NULL DEFAULT '',
                anilox_id INT UNSIGNED NULL,
                updated_by VARCHAR(120) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_work_order_anilox_unit (work_order_id, unit_no),
                KEY idx_work_order_anilox_work_order (work_order_id),
                KEY idx_work_order_anilox_catalog (anilox_id),
                CONSTRAINT fk_work_order_anilox_work_order FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE,
                CONSTRAINT fk_work_order_anilox_catalog FOREIGN KEY (anilox_id) REFERENCES production_anilox_catalog(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "INSERT IGNORE INTO production_anilox_catalog (code, name, bcm, lpi, sort_order, is_active) VALUES
                ('ANI0001', 'ANILOX 100', '2.2', '100', 10, 1),
                ('ANI0002', 'ANILOX 120', '2.5', '120', 20, 1),
                ('ANI0003', 'ANILOX 300', '3.0', '300', 30, 1),
                ('ANI0004', 'ANILOX 360', '3.3', '360', 40, 1),
                ('ANI0005', 'ANILOX 400', '3.6', '400', 50, 1),
                ('ANI0006', 'ANILOX 500', '4.0', '500', 60, 1),
                ('ANI0007', 'ANILOX 550', '4.3', '550', 70, 1),
                ('ANI0008', 'ANILOX 650', '4.8', '650', 80, 1),
                ('ANI0009', 'ANILOX 700', '5.0', '700', 90, 1),
                ('ANI0010', 'ANILOX 800', '5.4', '800', 100, 1),
                ('ANI0011', 'ANILOX 1000', '6.0', '1000', 110, 1),
                ('ANI0012', 'ANILOX 1200', '6.8', '1200', 120, 1)"
        );

        $this->pdo->exec(
            "INSERT IGNORE INTO warehouses (code, name) VALUES
                (100, 'Bodega 100 - Recepción MP'),
                (200, 'Bodega 200 - Recepción MP'),
                (500, 'Bodega 500 - Producción intermedia'),
                (600, 'Bodega 600 - Corte y conversión'),
                (700, 'Bodega 700 - Canal Tradicional'),
                (900, 'Bodega 900 - Tintas'),
                (1000, 'Bodega 1000 - Retail')"
        );
    }

    /**
     * Asegura/migra el esquema de gestión de residuos (mermas) en TRZ.
     *
     * Crea/ajusta:
     * - waste_inventory_entries: entradas de merma a inventario (con trazabilidad de origen).
     * - waste_operations: operaciones agregadas de residuos (retiros/compactadora/etc.).
     *
     * También agrega columnas nuevas de forma compatible (ALTER TABLE) cuando se detectan
     * despliegues con versiones anteriores.
     *
     * ---
     *
     * Ensures/migrates the waste management schema in TRZ.
     *
     * Creates/updates:
     * - waste_inventory_entries: waste inventory entries (with origin traceability).
     * - waste_operations: aggregated waste operations (withdrawals/compactor/etc.).
     *
     * It also adds new columns in a backward-compatible way (ALTER TABLE) when older
     * deployments are detected.
     */
    private function ensureWasteSchema(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS waste_inventory_entries (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                shift_session_id BIGINT UNSIGNED NULL,
                material_code VARCHAR(20) NOT NULL,
                weight_kg DECIMAL(10,3) NOT NULL,
                operator_name VARCHAR(120) NOT NULL,
                supplier_operator_name VARCHAR(120) NULL,
                supplier_machine_code VARCHAR(60) NULL,
                supplier_machine_name VARCHAR(160) NULL,
                comments VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_waste_inventory_shift (shift_session_id),
                KEY idx_waste_inventory_material (material_code),
                KEY idx_waste_inventory_supplier (supplier_operator_name),
                KEY idx_waste_inventory_supplier_machine (supplier_machine_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        if (!$this->columnExists('waste_inventory_entries', 'supplier_operator_name')) {
            $this->pdo->exec("ALTER TABLE waste_inventory_entries ADD COLUMN supplier_operator_name VARCHAR(120) NULL AFTER operator_name");
            $this->pdo->exec("ALTER TABLE waste_inventory_entries ADD INDEX idx_waste_inventory_supplier (supplier_operator_name)");
        }
        if (!$this->columnExists('waste_inventory_entries', 'supplier_machine_code')) {
            $this->pdo->exec("ALTER TABLE waste_inventory_entries ADD COLUMN supplier_machine_code VARCHAR(60) NULL AFTER supplier_operator_name");
            $this->pdo->exec("ALTER TABLE waste_inventory_entries ADD INDEX idx_waste_inventory_supplier_machine (supplier_machine_code)");
        }
        if (!$this->columnExists('waste_inventory_entries', 'supplier_machine_name')) {
            $this->pdo->exec("ALTER TABLE waste_inventory_entries ADD COLUMN supplier_machine_name VARCHAR(160) NULL AFTER supplier_machine_code");
        }
        if (!$this->columnExists('waste_inventory_entries', 'withdrawn_at')) {
            $this->pdo->exec("ALTER TABLE waste_inventory_entries ADD COLUMN withdrawn_at TIMESTAMP NULL AFTER created_at");
            $this->pdo->exec("ALTER TABLE waste_inventory_entries ADD COLUMN withdrawn_by_operator VARCHAR(120) NULL AFTER withdrawn_at");
            $this->pdo->exec("ALTER TABLE waste_inventory_entries ADD COLUMN withdrawal_operation_id BIGINT UNSIGNED NULL AFTER withdrawn_by_operator");
            $this->pdo->exec("ALTER TABLE waste_inventory_entries ADD INDEX idx_waste_inventory_withdrawn (withdrawn_at)");
            $this->pdo->exec("ALTER TABLE waste_inventory_entries ADD INDEX idx_waste_inventory_withdrawal_op (withdrawal_operation_id)");
        }
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS waste_operations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                shift_session_id BIGINT UNSIGNED NULL,
                operation_code VARCHAR(30) NOT NULL,
                material_code VARCHAR(20) NULL,
                weight_kg DECIMAL(10,3) NULL,
                operator_name VARCHAR(120) NOT NULL,
                supplier_operator_name VARCHAR(120) NULL,
                supplier_machine_code VARCHAR(60) NULL,
                supplier_machine_name VARCHAR(160) NULL,
                solicitante VARCHAR(120) NULL,
                area VARCHAR(100) NULL,
                motivo VARCHAR(160) NULL,
                entry_kg DECIMAL(10,3) NULL,
                exit_kg DECIMAL(10,3) NULL,
                pallet_count INT UNSIGNED NULL,
                comments VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_waste_ops_shift (shift_session_id),
                KEY idx_waste_ops_code (operation_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        if (!$this->columnExists('waste_operations', 'material_code')) {
            $this->pdo->exec("ALTER TABLE waste_operations ADD COLUMN material_code VARCHAR(20) NULL AFTER operation_code");
            $this->pdo->exec("ALTER TABLE waste_operations ADD COLUMN weight_kg DECIMAL(10,3) NULL AFTER material_code");
            $this->pdo->exec("ALTER TABLE waste_operations ADD COLUMN supplier_operator_name VARCHAR(120) NULL AFTER operator_name");
            $this->pdo->exec("ALTER TABLE waste_operations ADD COLUMN supplier_machine_code VARCHAR(60) NULL AFTER supplier_operator_name");
            $this->pdo->exec("ALTER TABLE waste_operations ADD COLUMN supplier_machine_name VARCHAR(160) NULL AFTER supplier_machine_code");
        }
        if (!$this->columnExists('waste_operations', 'solicitante')) {
            $this->pdo->exec("ALTER TABLE waste_operations ADD COLUMN solicitante VARCHAR(120) NULL AFTER supplier_machine_name");
            $this->pdo->exec("ALTER TABLE waste_operations ADD COLUMN area VARCHAR(100) NULL AFTER solicitante");
            $this->pdo->exec("ALTER TABLE waste_operations ADD COLUMN motivo VARCHAR(160) NULL AFTER area");
        }
        if (!$this->columnExists('waste_operations', 'entry_kg')) {
            $this->pdo->exec("ALTER TABLE waste_operations ADD COLUMN entry_kg DECIMAL(10,3) NULL AFTER motivo");
            $this->pdo->exec("ALTER TABLE waste_operations ADD COLUMN exit_kg DECIMAL(10,3) NULL AFTER entry_kg");
            $this->pdo->exec("ALTER TABLE waste_operations ADD COLUMN pallet_count INT UNSIGNED NULL AFTER exit_kg");
        }
    }

    /**
     * Asegura/migra el esquema de configuración de bonificaciones en TRZ.
     *
     * Tablas principales:
     * - bonus_brackets: tramos por rango (cuando el bono se calcula por tramos).
     * - bonus_unit_rates: tarifas por unidad según categoría/nivel (para bonos por unidad).
     * - bonus_operator_factors: factores por operador (ajustes multiplicativos).
     * - bonus_helper_roster / bonus_helper_monthly: roster y datos mensuales para bonos de ayudante.
     *
     * ---
     *
     * Ensures/migrates the bonuses configuration schema in TRZ.
     *
     * Main tables:
     * - bonus_brackets: range brackets (when the bonus is computed by ranges).
     * - bonus_unit_rates: per-unit rates by category/tier (for unit-based bonuses).
     * - bonus_operator_factors: operator factors (multiplicative adjustments).
     * - bonus_helper_roster / bonus_helper_monthly: roster and monthly data for helper bonuses.
     */
    private function ensureBonusSchema(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS bonus_brackets (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                bonus_code VARCHAR(40) NOT NULL,
                range_from INT UNSIGNED NOT NULL,
                range_to INT UNSIGNED NULL,
                amount_clp DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_bonus_code (bonus_code),
                KEY idx_bonus_range (bonus_code, range_from, range_to)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS bonus_unit_rates (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                bonus_code VARCHAR(40) NOT NULL,
                category_code VARCHAR(60) NOT NULL,
                tier_code VARCHAR(20) NOT NULL,
                rate_clp DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_bonus_unit (bonus_code, category_code, tier_code),
                KEY idx_bonus_unit_bonus (bonus_code),
                KEY idx_bonus_unit_category (bonus_code, category_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS bonus_operator_factors (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                bonus_code VARCHAR(40) NOT NULL,
                month_key CHAR(7) NOT NULL DEFAULT '',
                operator_name VARCHAR(120) NOT NULL,
                factor DECIMAL(4,2) NOT NULL DEFAULT 1.00,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_bonus_operator_factor (bonus_code, month_key, operator_name),
                KEY idx_bonus_operator_factor_bonus (bonus_code, month_key),
                KEY idx_bonus_operator_factor_operator (operator_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        try {
            $col = $this->pdo->query("SHOW COLUMNS FROM bonus_operator_factors LIKE 'month_key'")->fetchAll();
            if ($col === [] || $col === false) {
                $this->pdo->exec("ALTER TABLE bonus_operator_factors ADD COLUMN month_key CHAR(7) NOT NULL DEFAULT '' AFTER bonus_code");
            }
        } catch (Throwable) {
        }

        try {
            $this->pdo->exec("ALTER TABLE bonus_operator_factors DROP INDEX uniq_bonus_operator_factor");
        } catch (Throwable) {
        }
        try {
            $this->pdo->exec("ALTER TABLE bonus_operator_factors ADD UNIQUE KEY uniq_bonus_operator_factor (bonus_code, month_key, operator_name)");
        } catch (Throwable) {
        }
        try {
            $this->pdo->exec("ALTER TABLE bonus_operator_factors DROP INDEX idx_bonus_operator_factor_bonus");
        } catch (Throwable) {
        }
        try {
            $this->pdo->exec("ALTER TABLE bonus_operator_factors ADD KEY idx_bonus_operator_factor_bonus (bonus_code, month_key)");
        } catch (Throwable) {
        }

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS bonus_operator_coach_configs (
                bonus_code VARCHAR(40) NOT NULL,
                month_key CHAR(7) NOT NULL,
                coach_name VARCHAR(120) NOT NULL,
                share_percent DECIMAL(5,2) NOT NULL DEFAULT 0.50,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (bonus_code, month_key),
                KEY idx_bonus_coach_month (bonus_code, month_key),
                KEY idx_bonus_coach_name (coach_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS bonus_operator_coach_trainees (
                bonus_code VARCHAR(40) NOT NULL,
                month_key CHAR(7) NOT NULL,
                trainee_name VARCHAR(120) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (bonus_code, month_key, trainee_name),
                KEY idx_bonus_coach_trainee (trainee_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS bonus_helper_roster (
                operator_name VARCHAR(120) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (operator_name),
                KEY idx_bonus_helper_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS bonus_helper_monthly (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                month_key CHAR(7) NOT NULL,
                operator_name VARCHAR(120) NOT NULL,
                proactividad_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
                eficiencia_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
                multitarea_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
                matrix_proactividad_clp DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                matrix_eficiencia_clp DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                matrix_multitarea_clp DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                fixed_clp DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                additional_clp DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                observations VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_bonus_helper_month (month_key, operator_name),
                KEY idx_bonus_helper_month (month_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Sincroniza bodegas “legacy” para bobinas que están en producción.
     *
     * Objetivo:
     * - En versiones anteriores algunas bobinas podían quedar con warehouse_id distinto
     *   a la bodega “Producción”, aun estando IN_PROCESS o asignadas a una OT.
     * - Este método reubica esas bobinas en la bodega de producción (código 3000) y
     *   registra movimientos y eventos automáticamente.
     *
     * ---
     *
     * Syncs legacy warehouse assignment for rolls that are in production.
     *
     * Goal:
     * - In older versions, some rolls could remain in a non-production warehouse while
     *   being IN_PROCESS or attached to a work order.
     * - This method moves them into the production warehouse (code 3000) and
     *   automatically records movements and events.
     */
    private function syncLegacyProductionRollWarehouses(): void
    {
        $this->syncWarehousesFromErp();
        $productionWarehouseId = $this->findWarehouseIdByCode(3000);
        if ($productionWarehouseId === null) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, warehouse_id, current_work_order_id
             FROM rolls
             WHERE (status = :status OR current_work_order_id IS NOT NULL)
               AND warehouse_id <> :production_warehouse_id'
        );
        $stmt->execute([
            ':status' => 'IN_PROCESS',
            ':production_warehouse_id' => $productionWarehouseId,
        ]);
        $rows = $stmt->fetchAll();
        if ($rows === []) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare('UPDATE rolls SET warehouse_id = :warehouse_id WHERE id = :id');
            $insertMovement = $this->pdo->prepare(
                'INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload)
                 VALUES (:entity_type, :entity_id, :movement_type, :from_warehouse_id, :to_warehouse_id, :payload)'
            );

            foreach ($rows as $row) {
                $rollId = (int)($row['id'] ?? 0);
                $fromWarehouseId = (int)($row['warehouse_id'] ?? 0);
                $workOrderId = (int)($row['current_work_order_id'] ?? 0);
                if ($rollId <= 0 || $fromWarehouseId <= 0 || $fromWarehouseId === $productionWarehouseId) {
                    continue;
                }

                $update->execute([
                    ':warehouse_id' => $productionWarehouseId,
                    ':id' => $rollId,
                ]);

                $payload = json_encode([
                    'operator_name' => 'Sistema',
                    'work_order_id' => $workOrderId > 0 ? $workOrderId : null,
                    'auto_sync' => true,
                    'reason' => 'SYNC_PRODUCTION_WAREHOUSE',
                ], JSON_UNESCAPED_UNICODE);

                $insertMovement->execute([
                    ':entity_type' => 'ROLL',
                    ':entity_id' => $rollId,
                    ':movement_type' => 'TRANSFER',
                    ':from_warehouse_id' => $fromWarehouseId,
                    ':to_warehouse_id' => $productionWarehouseId,
                    ':payload' => $payload,
                ]);

                $this->insertEvent('ROLL_TRANSFERRED', [
                    'roll_id' => $rollId,
                    'from_warehouse_id' => $fromWarehouseId,
                    'to_warehouse_id' => $productionWarehouseId,
                    'operator_name' => 'Sistema',
                    'work_order_id' => $workOrderId > 0 ? $workOrderId : null,
                    'auto_sync' => true,
                    'reason' => 'SYNC_PRODUCTION_WAREHOUSE',
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Asegura el catálogo local de máquinas y tipos de máquina para Producción.
     *
     * Se usa para:
     * - Pantallas de turnos (shift sessions).
     * - Asignación de OTs a máquinas.
     * - Clasificar áreas de producción (PRINTING/REWINDING/SEALING/PACKAGING/RESIDUOS).
     *
     * Inserta/actualiza:
     * - production_machine_types
     * - production_machines
     *
     * ---
     *
     * Ensures the local machine and machine-type catalog used by Production.
     *
     * Used by:
     * - Shift sessions screens.
     * - Work order assignment to machines.
     * - Production area classification (PRINTING/REWINDING/SEALING/PACKAGING/RESIDUOS).
     *
     * Inserts/updates:
     * - production_machine_types
     * - production_machines
     */
    private function ensureProductionMachineCatalog(): void
    {
        $machineTypes = [
            ['code' => 'EMBALAJE', 'name' => 'EMBALAJE', 'production_area' => 'PACKAGING', 'erp_machine_type_id' => null, 'display_order' => 10],
            ['code' => 'FLEXOGRAFIA', 'name' => 'IMPRESORA FLEXOGRAFIA', 'production_area' => 'PRINTING', 'erp_machine_type_id' => 1, 'display_order' => 20],
            ['code' => 'SERIGRAFIA', 'name' => 'IMPRESORA SERIGRAFIA', 'production_area' => 'PRINTING', 'erp_machine_type_id' => 2, 'display_order' => 30],
            ['code' => 'REBOBINADO', 'name' => 'REBOBINADORA', 'production_area' => 'REWINDING', 'erp_machine_type_id' => 3, 'display_order' => 40],
            ['code' => 'SELLADO', 'name' => 'SELLADORAS', 'production_area' => 'SEALING', 'erp_machine_type_id' => 4, 'display_order' => 50],
            ['code' => 'GESTION_RESIDUOS', 'name' => 'gestion residuo', 'production_area' => 'RESIDUOS', 'erp_machine_type_id' => null, 'display_order' => 60],
        ];

        $typeStmt = $this->pdo->prepare(
            'INSERT INTO production_machine_types (code, name, production_area, erp_machine_type_id, display_order, is_active)
             VALUES (:code, :name, :production_area, :erp_machine_type_id, :display_order, 1)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                production_area = VALUES(production_area),
                erp_machine_type_id = VALUES(erp_machine_type_id),
                display_order = VALUES(display_order),
                is_active = 1'
        );
        foreach ($machineTypes as $machineType) {
            $typeStmt->execute([
                ':code' => $machineType['code'],
                ':name' => $machineType['name'],
                ':production_area' => $machineType['production_area'],
                ':erp_machine_type_id' => $machineType['erp_machine_type_id'],
                ':display_order' => $machineType['display_order'],
            ]);
        }

        $typeMap = [];
        $typeAreaMap = [];
        $typeRows = $this->pdo->query('SELECT id, code FROM production_machine_types')->fetchAll();
        foreach ($machineTypes as $machineType) {
            $typeAreaMap[(string)$machineType['code']] = (string)$machineType['production_area'];
        }
        foreach ($typeRows as $typeRow) {
            $typeMap[(string)$typeRow['code']] = (int)$typeRow['id'];
        }

        $machines = [
            ['type' => 'EMBALAJE', 'code' => 'EMB-01', 'name' => 'EMBALAJE', 'erp_machine_id' => 201, 'sort_order' => 10],
            ['type' => 'FLEXOGRAFIA', 'code' => 'FLEXO-01', 'name' => 'FLEXO I.', 'erp_machine_id' => 101, 'sort_order' => 20],
            ['type' => 'FLEXOGRAFIA', 'code' => 'FLEXO-02', 'name' => 'FLEXO II.', 'erp_machine_id' => 102, 'sort_order' => 21],
            ['type' => 'SERIGRAFIA', 'code' => 'SERI-PULPO', 'name' => 'PULPO SERIGRAFICO', 'erp_machine_id' => 111, 'sort_order' => 30],
            ['type' => 'SERIGRAFIA', 'code' => 'SERI-01', 'name' => 'SERI I.', 'erp_machine_id' => 112, 'sort_order' => 31],
            ['type' => 'SERIGRAFIA', 'code' => 'SERI-02', 'name' => 'SERI II.', 'erp_machine_id' => 113, 'sort_order' => 32],
            ['type' => 'SERIGRAFIA', 'code' => 'SERI-03', 'name' => 'SERI III.', 'erp_machine_id' => 114, 'sort_order' => 33],
            ['type' => 'REBOBINADO', 'code' => 'REBO-02', 'name' => 'REBO II.', 'erp_machine_id' => 121, 'sort_order' => 40],
            ['type' => 'SELLADO', 'code' => 'SELLA-01', 'name' => 'SELLADORA I.', 'erp_machine_id' => 131, 'sort_order' => 50],
            ['type' => 'SELLADO', 'code' => 'SELLA-02', 'name' => 'SELLADORA II.', 'erp_machine_id' => 132, 'sort_order' => 51],
            ['type' => 'SELLADO', 'code' => 'SELLA-04', 'name' => 'SELLADORA IV.', 'erp_machine_id' => 134, 'sort_order' => 52],
            ['type' => 'SELLADO', 'code' => 'SELLA-05', 'name' => 'SELLADORA V.', 'erp_machine_id' => 135, 'sort_order' => 53],
            ['type' => 'SELLADO', 'code' => 'SELLA-06', 'name' => 'SELLADORA VI.', 'erp_machine_id' => 136, 'sort_order' => 54],
            ['type' => 'GESTION_RESIDUOS', 'code' => 'GESTION-01', 'name' => 'gestion 1', 'erp_machine_id' => null, 'sort_order' => 60],
        ];

        $machineStmt = $this->pdo->prepare(
            'INSERT INTO production_machines (machine_type_id, code, name, production_area, erp_machine_id, plant_label, sort_order, is_active)
             VALUES (:machine_type_id, :code, :name, :production_area, :erp_machine_id, :plant_label, :sort_order, 1)
             ON DUPLICATE KEY UPDATE
                machine_type_id = VALUES(machine_type_id),
                name = VALUES(name),
                production_area = VALUES(production_area),
                erp_machine_id = VALUES(erp_machine_id),
                plant_label = VALUES(plant_label),
                sort_order = VALUES(sort_order),
                is_active = 1'
        );
        foreach ($machines as $machine) {
            $machineTypeId = (int)($typeMap[$machine['type']] ?? 0);
            if ($machineTypeId <= 0) {
                continue;
            }
            $productionArea = $typeAreaMap[$machine['type']] ?? 'PRODUCTION';
            $machineStmt->execute([
                ':machine_type_id' => $machineTypeId,
                ':code' => $machine['code'],
                ':name' => $machine['name'],
                ':production_area' => $productionArea,
                ':erp_machine_id' => $machine['erp_machine_id'],
                ':plant_label' => 'SANTIAGO CM',
                ':sort_order' => $machine['sort_order'],
            ]);
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $stmt->execute([
            ':table_name' => $table,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function syncWarehousesFromErp(bool $force = false): void
    {
        if ($this->erpWarehousesSynced && !$force) {
            return;
        }

        if (!$force && $this->shouldSkipWarehouseSync()) {
            $this->erpWarehousesSynced = true;
            return;
        }

        try {
            $stmt = $this->erpPdo->query(
                'SELECT id, st_name, st_desc, st_shop_id, st_status
                 FROM company_shops_storehouses
                 WHERE st_status = 1
                 ORDER BY id ASC'
            );
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $erpId = (int)$row['id'];
                $name = trim((string)($row['st_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $code = $this->parseWarehouseCodeFromName($name);
                if ($code === null) {
                    $code = $erpId;
                }
                $this->upsertTraceWarehouse($code, $name, $erpId);
            }
        } catch (Throwable $e) {
            // Fallback to static list if ERP query fails
            foreach ([
                100 => '100 (MP PLA)',
                110 => '110 (MP PLA DESCALIBRADO)',
                120 => '120 (MP PLA EMPALMADO)',
                150 => '150 (BODEGA RESERVA MATERIALES)',
                200 => '200 (MP PP)',
                300 => '300 (PROD. TERMINADO FABRICACION INTERNA)',
                400 => '400 (PROD TERMINADOS REVENTA)',
                500 => '500 (PRODUCCION - BODEGA)',
                510 => '510 (RESIDUOS)',
                600 => '600 (REPUESTOS)',
                700 => '700 (BODEGA CANAL TRADICIONAL)',
                800 => '800 (EPP Y ROPAS)',
                900 => '900 (TINTAS FLEXOGRAFIA)',
                910 => '910 (TINTAS SERIGRAFIA)',
                920 => '920 (TINTAS PULPO SERIGRAFIA)',
                1000 => '1000 (BODEGA RETAIL A y B)',
                2000 => '2000 TALLERES EXTERNOS',
                3000 => '3000 INSUMOS EN PRODUCCION',
                3100 => '3100 INSUMOS-LIMPIEZA',
                3200 => '3200 INSUMOS DISPONIBLES (MP)',
                4000 => '4000 (BOBINAS USADAS)',
                5000 => '5000 (PRODUCTOS INMOVILIZADOS)',
                6000 => '6000 Facturacion de servicios No productivos',
            ] as $code => $name) {
                $this->upsertTraceWarehouse($code, $name);
            }
        }

        $this->setAppSetting('erp_warehouses_synced_at', (string)time());
        $this->erpWarehousesSynced = true;
    }

    private function shouldSkipWarehouseSync(): bool
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM warehouses');
        $warehouseCount = (int)$stmt->fetchColumn();
        if ($warehouseCount <= 0) {
            return false;
        }

        $lastSyncedAt = (int)$this->getAppSetting('erp_warehouses_synced_at', '0');
        return $lastSyncedAt > 0 && $lastSyncedAt >= (time() - 900);
    }

    private function parseWarehouseCodeFromName(string $name): ?int
    {
        if (preg_match('/^\s*(\d{3,4})\b/', $name, $matches) !== 1) {
            return null;
        }

        return (int)$matches[1];
    }

    private function upsertTraceWarehouse(int $code, string $name, ?int $erpStorehouseId = null): int
    {
        if ($erpStorehouseId !== null && $erpStorehouseId > 0) {
            $stmt = $this->pdo->prepare('SELECT id, code FROM warehouses WHERE erp_storehouse_id = :erp_id LIMIT 1');
            $stmt->execute([':erp_id' => $erpStorehouseId]);
            $row = $stmt->fetch();
            if ($row !== false) {
                $id = (int)$row['id'];
                $chk = $this->pdo->prepare('SELECT id FROM warehouses WHERE code = :code AND id != :id LIMIT 1');
                $chk->execute([':code' => $code, ':id' => $id]);
                if ($chk->fetch() !== false) {
                    $code = (int)$row['code'] > 0 ? (int)$row['code'] : $erpStorehouseId;
                }
                $update = $this->pdo->prepare('UPDATE warehouses SET code = :code, name = :name WHERE id = :id');
                $update->execute([':code' => $code, ':name' => $name, ':id' => $id]);
                return $id;
            }
        }

        $stmt = $this->pdo->prepare('SELECT id, erp_storehouse_id FROM warehouses WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        if ($row !== false) {
            $existingErpId = $row['erp_storehouse_id'] !== null ? (int)$row['erp_storehouse_id'] : null;
            if ($existingErpId === null || $existingErpId === $erpStorehouseId) {
                $id = (int)$row['id'];
                $update = $this->pdo->prepare('UPDATE warehouses SET name = :name, erp_storehouse_id = COALESCE(erp_storehouse_id, :erp_id) WHERE id = :id');
                $update->execute([':name' => $name, ':erp_id' => $erpStorehouseId, ':id' => $id]);
                return $id;
            }
            // Code already belongs to a different ERP storehouse; use distinct code
            $code = $erpStorehouseId !== null && $erpStorehouseId > 0 ? $erpStorehouseId : ($code * 10 + 1);
            $chk2 = $this->pdo->prepare('SELECT id FROM warehouses WHERE code = :code LIMIT 1');
            $chk2->execute([':code' => $code]);
            if ($chk2->fetch() !== false) {
                $code = 10000 + (int)$erpStorehouseId;
            }
        }

        $insert = $this->pdo->prepare('INSERT INTO warehouses (code, name, erp_storehouse_id) VALUES (:code, :name, :erp_id)');
        $insert->execute([':code' => $code, ':name' => $name, ':erp_id' => $erpStorehouseId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function syncErpProductionPlan(bool $force = false): array
    {
        return $this->syncWorkOrdersFromErpProductionPlan($force);
    }

    private function syncWorkOrdersFromErpProductionPlan(bool $force = false): array
    {
        if ($this->erpProductionPlanSynced && !$force) {
            return ['ok' => true, 'processed' => 0, 'synced' => 0];
        }

        if (!$force && $this->shouldSkipProductionPlanSync()) {
            $this->erpProductionPlanSynced = true;
            return ['ok' => true, 'processed' => 0, 'synced' => 0];
        }

        $sql = <<<SQL
SELECT
    ph.id AS erp_prod_header_id,
    ph.prd_number,
    ph.prd_reqid,
    ph.prd_desc,
    ph.prd_status,
    ph.prd_plantaid,
    pa.id AS erp_agenda_id,
    pa.ag_date,
    pa.ag_date_stamp,
    pa.ag_equipo_id,
    pa.ag_equipotype_id,
    pa.ag_amount,
    pa.ag_reqid,
    pa.ag_plantaid,
    pa.ag_status,
    pa.ag_active,
    pwo.id AS erp_worker_ot_id,
    pwo.wok_init_id,
    pwo.wok_status,
    pwo.wok_crtdat,
    pwo.wok_enddat,
    pwi.id AS erp_worker_init_id,
    pwi.win_wrkid,
    w.id AS erp_worker_id,
    w.wrk_firstname,
    w.wrk_lastname,
    w.wrk_status,
    u.id AS erp_user_id,
    u.user_login
FROM prod_agenda pa
INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
LEFT JOIN (
    SELECT pwo1.*
    FROM prod_worker_ot pwo1
    INNER JOIN (
        SELECT wok_ag_id, MAX(id) AS max_id
        FROM prod_worker_ot
        GROUP BY wok_ag_id
    ) latest_pwo ON latest_pwo.max_id = pwo1.id
) pwo ON pwo.wok_ag_id = pa.id
LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
LEFT JOIN workers w ON w.id = pwi.win_wrkid
LEFT JOIN user u ON u.id = w.wrk_uid
ORDER BY pa.id DESC
LIMIT 250
SQL;

        try {
            $rows = $this->erpPdo->query($sql)->fetchAll();
        } catch (PDOException $e) {
            $sqlState = (string)($e->errorInfo[0] ?? $e->getCode() ?? '');
            $message = $e->getMessage();
            if ($sqlState === '42S02' || str_contains($message, 'Base table or view not found')) {
                return [
                    'ok' => false,
                    'processed' => 0,
                    'synced' => 0,
                    'warning' => 'erp_production_tables_missing',
                ];
            }

            throw $e;
        }
        $processed = 0;
        $synced = 0;

        $this->pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $processed++;
                $planDate = $this->resolveErpPlanDate(
                    $row['ag_date'] ?? null,
                    $row['ag_date_stamp'] ?? null
                );
                $otCode = $this->buildErpWorkOrderCode($row);
                $skuFinal = $this->buildErpWorkOrderSku($row);
                $rawAmount = isset($row['ag_amount']) ? (float)$row['ag_amount'] : null;
                $targetQty = $rawAmount !== null ? (int)round(max(0.0, $rawAmount)) : null;
                $machineId = isset($row['ag_equipo_id']) ? (int)$row['ag_equipo_id'] : null;
                $machineTypeId = isset($row['ag_equipotype_id']) ? (int)$row['ag_equipotype_id'] : null;
                $machineLabel = $this->buildErpMachineLabel($machineId, $machineTypeId);
                $workerName = $this->buildErpWorkerName($row);
                $workOrder = $this->findExistingWorkOrderForErpAgenda((int)$row['erp_agenda_id'], $otCode);
                $workOrderId = $workOrder['id'] ?? null;
                $existingStatus = $workOrder['status'] ?? null;
                $status = $this->mapErpPlanToWorkOrderStatus($existingStatus, $row);

                if ($workOrderId === null) {
                    $insert = $this->pdo->prepare(
                        'INSERT INTO work_orders (ot_code, sku_final, target_qty, status)
                         VALUES (:ot_code, :sku_final, :target_qty, :status)'
                    );
                    $insert->execute([
                        ':ot_code' => $otCode,
                        ':sku_final' => $skuFinal,
                        ':target_qty' => $targetQty,
                        ':status' => $status,
                    ]);
                    $workOrderId = (int)$this->pdo->lastInsertId();
                    $synced++;
                } else {
                    $update = $this->pdo->prepare(
                        'UPDATE work_orders
                         SET ot_code = :ot_code,
                             sku_final = :sku_final,
                             target_qty = :target_qty,
                             status = :status
                         WHERE id = :id'
                    );
                    $update->execute([
                        ':id' => $workOrderId,
                        ':ot_code' => $otCode,
                        ':sku_final' => $skuFinal,
                        ':target_qty' => $targetQty,
                        ':status' => $status,
                    ]);
                }

                $sync = $this->pdo->prepare(
                    'INSERT INTO erp_work_order_sync (
                        work_order_id,
                        erp_prod_header_id,
                        erp_agenda_id,
                        erp_worker_ot_id,
                        erp_worker_init_id,
                        erp_worker_id,
                        erp_worker_name,
                        erp_user_id,
                        erp_user_login,
                        erp_prod_number,
                        erp_req_id,
                        erp_plan_desc,
                        erp_plan_date,
                        erp_plan_timestamp,
                        erp_machine_id,
                        erp_machine_label,
                        erp_machine_type_id,
                        erp_planta_id,
                        erp_target_qty,
                        erp_required_meters,
                        erp_required_meters_source,
                        erp_header_status,
                        erp_agenda_status,
                        erp_agenda_active,
                        erp_worker_status
                    ) VALUES (
                        :work_order_id,
                        :erp_prod_header_id,
                        :erp_agenda_id,
                        :erp_worker_ot_id,
                        :erp_worker_init_id,
                        :erp_worker_id,
                        :erp_worker_name,
                        :erp_user_id,
                        :erp_user_login,
                        :erp_prod_number,
                        :erp_req_id,
                        :erp_plan_desc,
                        :erp_plan_date,
                        :erp_plan_timestamp,
                        :erp_machine_id,
                        :erp_machine_label,
                        :erp_machine_type_id,
                        :erp_planta_id,
                        :erp_target_qty,
                        :erp_required_meters,
                        :erp_required_meters_source,
                        :erp_header_status,
                        :erp_agenda_status,
                        :erp_agenda_active,
                        :erp_worker_status
                    )
                    ON DUPLICATE KEY UPDATE
                        work_order_id = VALUES(work_order_id),
                        erp_worker_ot_id = VALUES(erp_worker_ot_id),
                        erp_worker_init_id = VALUES(erp_worker_init_id),
                        erp_worker_id = VALUES(erp_worker_id),
                        erp_worker_name = VALUES(erp_worker_name),
                        erp_user_id = VALUES(erp_user_id),
                        erp_user_login = VALUES(erp_user_login),
                        erp_prod_number = VALUES(erp_prod_number),
                        erp_req_id = VALUES(erp_req_id),
                        erp_plan_desc = VALUES(erp_plan_desc),
                        erp_plan_date = VALUES(erp_plan_date),
                        erp_plan_timestamp = VALUES(erp_plan_timestamp),
                        erp_machine_id = VALUES(erp_machine_id),
                        erp_machine_label = VALUES(erp_machine_label),
                        erp_machine_type_id = VALUES(erp_machine_type_id),
                        erp_planta_id = VALUES(erp_planta_id),
                        erp_target_qty = VALUES(erp_target_qty),
                        erp_required_meters = VALUES(erp_required_meters),
                        erp_required_meters_source = VALUES(erp_required_meters_source),
                        erp_header_status = VALUES(erp_header_status),
                        erp_agenda_status = VALUES(erp_agenda_status),
                        erp_agenda_active = VALUES(erp_agenda_active),
                        erp_worker_status = VALUES(erp_worker_status)'
                );
                $sync->execute([
                    ':work_order_id' => $workOrderId,
                    ':erp_prod_header_id' => (int)$row['erp_prod_header_id'],
                    ':erp_agenda_id' => (int)$row['erp_agenda_id'],
                    ':erp_worker_ot_id' => isset($row['erp_worker_ot_id']) ? (int)$row['erp_worker_ot_id'] : null,
                    ':erp_worker_init_id' => isset($row['erp_worker_init_id']) ? (int)$row['erp_worker_init_id'] : null,
                    ':erp_worker_id' => isset($row['erp_worker_id']) ? (int)$row['erp_worker_id'] : null,
                    ':erp_worker_name' => $workerName !== '' ? $workerName : null,
                    ':erp_user_id' => isset($row['erp_user_id']) ? (int)$row['erp_user_id'] : null,
                    ':erp_user_login' => trim((string)($row['user_login'] ?? '')) !== '' ? trim((string)$row['user_login']) : null,
                    ':erp_prod_number' => $otCode,
                    ':erp_req_id' => trim((string)($row['prd_reqid'] ?? $row['ag_reqid'] ?? '')) !== ''
                        ? trim((string)($row['prd_reqid'] ?? $row['ag_reqid']))
                        : null,
                    ':erp_plan_desc' => trim((string)($row['prd_desc'] ?? '')) !== '' ? trim((string)$row['prd_desc']) : null,
                    ':erp_plan_date' => $planDate['label'] !== '' ? $planDate['label'] : null,
                    ':erp_plan_timestamp' => $planDate['timestamp'],
                    ':erp_machine_id' => $machineId,
                    ':erp_machine_label' => $machineLabel !== '' ? $machineLabel : null,
                    ':erp_machine_type_id' => $machineTypeId,
                    ':erp_planta_id' => isset($row['ag_plantaid']) && (int)$row['ag_plantaid'] > 0
                        ? (int)$row['ag_plantaid']
                        : (isset($row['prd_plantaid']) ? (int)$row['prd_plantaid'] : null),
                    ':erp_target_qty' => isset($row['ag_amount']) ? (float)$row['ag_amount'] : null,
                    ':erp_required_meters' => null,
                    ':erp_required_meters_source' => null,
                    ':erp_header_status' => trim((string)($row['prd_status'] ?? '')) !== '' ? trim((string)$row['prd_status']) : null,
                    ':erp_agenda_status' => trim((string)($row['ag_status'] ?? '')) !== '' ? trim((string)$row['ag_status']) : null,
                    ':erp_agenda_active' => (int)($row['ag_active'] ?? 0),
                    ':erp_worker_status' => trim((string)($row['wok_status'] ?? '')) !== '' ? trim((string)$row['wok_status']) : null,
                ]);
            }

            $this->setAppSetting('erp_production_plan_synced_at', (string)time());
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->erpProductionPlanSynced = true;
        return ['ok' => true, 'processed' => $processed, 'synced' => $synced];
    }

    private function shouldSkipProductionPlanSync(): bool
    {
        $tableExists = $this->pdo->query("SHOW TABLES LIKE 'erp_work_order_sync'")->fetchColumn();
        if ($tableExists === false) {
            return false;
        }

        $rowCount = (int)$this->pdo->query('SELECT COUNT(*) FROM erp_work_order_sync')->fetchColumn();
        if ($rowCount <= 0) {
            return false;
        }

        $lastSyncedAt = (int)$this->getAppSetting('erp_production_plan_synced_at', '0');
        return $lastSyncedAt > 0 && $lastSyncedAt >= (time() - 300);
    }

    /**
     * @return array{id:int,status:string}|null
     */
    private function findExistingWorkOrderForErpAgenda(int $agendaId, string $otCode): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT wo.id, wo.status
             FROM erp_work_order_sync sync
             INNER JOIN work_orders wo ON wo.id = sync.work_order_id
             WHERE sync.erp_agenda_id = :agenda_id
             LIMIT 1'
        );
        $stmt->execute([':agenda_id' => $agendaId]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return [
                'id' => (int)$row['id'],
                'status' => (string)$row['status'],
            ];
        }

        $fallback = $this->pdo->prepare('SELECT id, status FROM work_orders WHERE ot_code = :ot_code LIMIT 1');
        $fallback->execute([':ot_code' => $otCode]);
        $row = $fallback->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int)$row['id'],
            'status' => (string)$row['status'],
        ];
    }

    /**
     * @return array{label:string,timestamp:?int}
     */
    private function resolveErpPlanDate(mixed $agDate, mixed $agDateStamp): array
    {
        foreach ([$agDateStamp, $agDate] as $candidate) {
            if (is_numeric($candidate)) {
                $timestamp = (int)$candidate;
                if ($timestamp > 0) {
                    return [
                        'label' => date('Y-m-d H:i', $timestamp),
                        'timestamp' => $timestamp,
                    ];
                }
            }
        }

        $raw = trim((string)($agDate ?? ''));
        if ($raw !== '') {
            return ['label' => $raw, 'timestamp' => null];
        }

        return ['label' => '', 'timestamp' => null];
    }

    private function buildErpWorkOrderCode(array $row): string
    {
        $number = trim((string)($row['prd_number'] ?? ''));
        if ($number !== '') {
            return $number;
        }

        return 'ERP-PRD-' . (int)($row['erp_prod_header_id'] ?? 0) . '-AG-' . (int)($row['erp_agenda_id'] ?? 0);
    }

    private function buildErpWorkOrderSku(array $row): string
    {
        $description = trim((string)($row['prd_desc'] ?? ''));
        if ($description !== '') {
            return $description;
        }

        $requestId = trim((string)($row['prd_reqid'] ?? $row['ag_reqid'] ?? ''));
        if ($requestId !== '') {
            return 'Req ERP ' . $requestId;
        }

        return 'Producción ERP';
    }

    private function buildErpMachineLabel(?int $machineId, ?int $machineTypeId): string
    {
        $parts = [];
        if ($machineId !== null && $machineId > 0) {
            $parts[] = 'Equipo ' . $machineId;
        }
        if ($machineTypeId !== null && $machineTypeId > 0) {
            $parts[] = 'Tipo ' . $machineTypeId;
        }

        return implode(' - ', $parts);
    }

    private function normalizeMachineProcessStage(string $value): string
    {
        $value = strtoupper(trim($value));
        return match ($value) {
            'PRINTING', 'PRINT', 'PRODUCCION', 'PRODUCTION' => 'PRODUCTION',
            'REWIND', 'REWINDING', 'REBOBINADO', 'REBOBINADORA' => 'REWINDING',
            'PACKAGING', 'EMBALAJE' => 'PACKAGING',
            'SEALING', 'SELLADO', 'SELLADORA', 'SELLADORAS' => 'SEALING',
            'CUT', 'CORTE', 'CUTTING' => 'CUTTING',
            'RESIDUOS', 'WASTE', 'GESTION_RESIDUOS', 'GESTION RESIDUOS', 'RESIDUO' => 'RESIDUOS',
            default => 'PRODUCTION',
        };
    }

    private function getShiftSession(int $sessionId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pss.*, pm.name AS machine_name, pm.code AS machine_code, pmt.name AS machine_type_name
             FROM production_shift_sessions pss
             INNER JOIN production_machines pm ON pm.id = pss.machine_id
             INNER JOIN production_machine_types pmt ON pmt.id = pm.machine_type_id
             WHERE pss.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $sessionId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getActiveShiftSessionByMachine(int $machineId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pss.*, pm.name AS machine_name, pm.code AS machine_code, pmt.name AS machine_type_name
             FROM production_shift_sessions pss
             INNER JOIN production_machines pm ON pm.id = pss.machine_id
             INNER JOIN production_machine_types pmt ON pmt.id = pm.machine_type_id
             WHERE pss.machine_id = :machine_id
               AND pss.status = "ACTIVE"
             ORDER BY pss.id DESC
             LIMIT 1'
        );
        $stmt->execute([':machine_id' => $machineId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    private function buildErpWorkerName(array $row): string
    {
        return trim(
            trim((string)($row['wrk_firstname'] ?? ''))
            . ' '
            . trim((string)($row['wrk_lastname'] ?? ''))
        );
    }

    private function mapErpPlanToWorkOrderStatus(?string $existingStatus, array $row): string
    {
        $existingStatus = strtoupper(trim((string)$existingStatus));
        if (in_array($existingStatus, ['CUTTING', 'CLOSED'], true)) {
            return $existingStatus;
        }
        if ($existingStatus === 'ACTIVE') {
            return 'ACTIVE';
        }

        $workerStatus = strtoupper(trim((string)($row['wok_status'] ?? '')));
        $headerStatus = strtoupper(trim((string)($row['prd_status'] ?? '')));
        $agendaStatus = strtoupper(trim((string)($row['ag_status'] ?? '')));
        $agendaActive = (int)($row['ag_active'] ?? 0) === 1;
        $workerOpen = isset($row['erp_worker_ot_id'])
            && (int)$row['erp_worker_ot_id'] > 0
            && !$this->hasErpProcessEnded($row['wok_enddat'] ?? null);
        $workerEnded = isset($row['erp_worker_ot_id'])
            && (int)$row['erp_worker_ot_id'] > 0
            && $this->hasErpProcessEnded($row['wok_enddat'] ?? null);

        if ($agendaActive || $workerOpen || in_array($workerStatus, ['ACTIVE', 'STARTED', 'RUNNING', 'IN_PROGRESS', '1'], true)) {
            return 'ACTIVE';
        }

        if (
            $workerEnded
            || in_array($workerStatus, ['2', '3', 'COMPLETE', 'COMPLETED', 'FINISHED', 'DONE', 'CLOSED'], true)
            || in_array($headerStatus, ['2', '3', 'COMPLETE', 'COMPLETED', 'FINISHED', 'DONE', 'CLOSED'], true)
            || in_array($agendaStatus, ['2', '3', 'COMPLETE', 'COMPLETED', 'FINISHED', 'DONE', 'CLOSED'], true)
        ) {
            return 'CLOSED';
        }

        return 'OPEN';
    }

    private function hasErpProcessEnded(mixed $value): bool
    {
        $raw = trim((string)$value);
        return $raw !== '' && $raw !== '0' && $raw !== '0000-00-00 00:00:00';
    }

    /**
     * @return array<int, array{received_rolls:int,received_qty:float,received_weight_kg:float}>
     */
    private function getReceivedSummaryByPurchaseOrderLineIds(array $lineIds): array
    {
        $lineIds = array_values(array_filter(array_map(static fn(mixed $id): int => (int)$id, $lineIds), static fn(int $id): bool => $id > 0));
        if ($lineIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($lineIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT purchase_order_line_id,
                    COUNT(*) AS received_rolls,
                    COALESCE(SUM(received_qty), 0) AS received_qty,
                    COALESCE(SUM(weight_kg), 0) AS received_weight_kg
             FROM rolls
             WHERE purchase_order_line_id IN ($placeholders)
             GROUP BY purchase_order_line_id"
        );
        $stmt->execute($lineIds);

        $summary = [];
        foreach ($stmt->fetchAll() as $row) {
            $summary[(int)$row['purchase_order_line_id']] = [
                'received_rolls' => (int)$row['received_rolls'],
                'received_qty' => (float)$row['received_qty'],
                'received_weight_kg' => (float)$row['received_weight_kg'],
            ];
        }

        try {
            $erpStmt = $this->erpPdo->prepare(
                "SELECT id, COALESCE(item_amount_shipped, 0) AS shipped_amount
                 FROM supplier_order_items
                 WHERE id IN ($placeholders)"
            );
            $erpStmt->execute($lineIds);
            foreach ($erpStmt->fetchAll() as $erpRow) {
                $lineId = (int)$erpRow['id'];
                $shipped = (float)$erpRow['shipped_amount'];
                if ($shipped > 0) {
                    if (!isset($summary[$lineId])) {
                        $summary[$lineId] = [
                            'received_rolls' => (int)round($shipped),
                            'received_qty' => $shipped,
                            'received_weight_kg' => 0.0,
                        ];
                    } else {
                        $summary[$lineId]['received_rolls'] = max($summary[$lineId]['received_rolls'], (int)round($shipped));
                        $summary[$lineId]['received_qty'] = max($summary[$lineId]['received_qty'], $shipped);
                    }
                }
            }
        } catch (\Throwable) {
        }

        return $summary;
    }

    /**
     * @param array<int, int|string> $lineIds
     * @return array<int, string>
     */
    private function getSavedReceptionModesByPurchaseOrderLineIds(array $lineIds): array
    {
        $lineIds = array_values(array_filter(array_map(static fn($id): int => (int)$id, $lineIds), static fn(int $id): bool => $id > 0));
        if ($lineIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($lineIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT purchase_order_line_id, reception_mode
             FROM rolls
             WHERE purchase_order_line_id IN ($placeholders)
             ORDER BY id DESC"
        );
        $stmt->execute($lineIds);

        $modes = [];
        foreach ($stmt->fetchAll() as $row) {
            $lineId = (int)($row['purchase_order_line_id'] ?? 0);
            if ($lineId <= 0 || isset($modes[$lineId])) {
                continue;
            }
            $modes[$lineId] = $this->normalizeReceptionMode((string)($row['reception_mode'] ?? 'QUANTITY'));
        }

        return $modes;
    }

    /**
     * @param array<int, int|string> $containerItemIds
     * @return array<int, string>
     */
    private function getSavedReceptionModesByImportContainerItemIds(array $containerItemIds): array
    {
        $containerItemIds = array_values(array_filter(array_map(static fn($id): int => (int)$id, $containerItemIds), static fn(int $id): bool => $id > 0));
        if ($containerItemIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($containerItemIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT import_container_item_id, reception_mode
             FROM rolls
             WHERE import_container_item_id IN ($placeholders)
             ORDER BY id DESC"
        );
        $stmt->execute($containerItemIds);

        $modes = [];
        foreach ($stmt->fetchAll() as $row) {
            $containerItemId = (int)($row['import_container_item_id'] ?? 0);
            if ($containerItemId <= 0 || isset($modes[$containerItemId])) {
                continue;
            }
            $modes[$containerItemId] = $this->normalizeReceptionMode((string)($row['reception_mode'] ?? 'QUANTITY'));
        }

        return $modes;
    }

    /**
     * @param array<int, int|string> $containerItemIds
     * @return array<int, array{received_rolls:int,received_qty:float,received_weight_kg:float}>
     */
    private function getReceivedSummaryByImportContainerItemIds(array $containerItemIds): array
    {
        $containerItemIds = array_values(array_filter(array_map(static fn ($id): int => (int)$id, $containerItemIds), static fn (int $id): bool => $id > 0));
        if ($containerItemIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($containerItemIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT import_container_item_id,
                    COUNT(*) AS received_rolls,
                    COALESCE(SUM(received_qty), 0) AS received_qty,
                    COALESCE(SUM(weight_kg), 0) AS received_weight_kg
             FROM rolls
             WHERE import_container_item_id IN ($placeholders)
             GROUP BY import_container_item_id"
        );
        $stmt->execute($containerItemIds);

        $summary = [];
        foreach ($stmt->fetchAll() as $row) {
            $summary[(int)$row['import_container_item_id']] = [
                'received_rolls' => (int)$row['received_rolls'],
                'received_qty' => (float)$row['received_qty'],
                'received_weight_kg' => (float)$row['received_weight_kg'],
            ];
        }

        try {
            $erpStmt = $this->erpPdo->prepare(
                "SELECT t3.item_contenedor_refid AS container_item_id,
                        COALESCE(SUM(t3.item_amount), 0) AS erp_amount
                 FROM stockchanges ta
                 INNER JOIN stockchanges_items t3 ON t3.stk_id = ta.id
                 WHERE ta.stk_status > 1
                   AND t3.item_contenedor_refid IN ($placeholders)
                 GROUP BY t3.item_contenedor_refid"
            );
            $erpStmt->execute($containerItemIds);
            foreach ($erpStmt->fetchAll() as $erpRow) {
                $cItemId = (int)$erpRow['container_item_id'];
                $erpAmount = (float)$erpRow['erp_amount'];
                if ($erpAmount > 0) {
                    if (!isset($summary[$cItemId])) {
                        $summary[$cItemId] = [
                            'received_rolls' => (int)round($erpAmount),
                            'received_qty' => $erpAmount,
                            'received_weight_kg' => 0.0,
                        ];
                    } else {
                        $summary[$cItemId]['received_rolls'] = max($summary[$cItemId]['received_rolls'], (int)round($erpAmount));
                        $summary[$cItemId]['received_qty'] = max($summary[$cItemId]['received_qty'], $erpAmount);
                    }
                }
            }
        } catch (\Throwable) {
        }

        return $summary;
    }

    private function inferReceptionModeFromErpLine(array $line): string
    {
        $orderedWeight = (float)($line['ordered_weight_kg'] ?? $line['item_kgs'] ?? $line['sord_kgs_amount'] ?? 0);
        return $orderedWeight > 0 ? 'WEIGHT' : 'QUANTITY';
    }

    /**
     * @param array<string, mixed> $line
     * @return array<string, mixed>
     */
    private function normalizeErpPurchaseOrderLine(array $line): array
    {
        $lineId = (int)($line['id'] ?? 0);
        $erpItemId = (int)($line['erp_item_id'] ?? $line['item_id'] ?? 0);
        $sku = $this->getErpItem($erpItemId);
        $fallbackSkuCode = $erpItemId > 0 ? ('ERPITEM-' . $erpItemId) : ('ERP-LINE-' . $lineId);
        $fallbackDescription = trim((string)($line['line_description'] ?? '')) !== ''
            ? trim((string)$line['line_description'])
            : ('Producto ERP ' . ($erpItemId > 0 ? $erpItemId : $lineId));
        $localSkuId = $this->ensureTraceSkuFromErpItem($erpItemId, $fallbackSkuCode, $fallbackDescription);

        $row = [
            'id' => $lineId,
            'purchase_order_id' => (int)($line['purchase_order_id'] ?? 0),
            'supplier_id' => (int)($line['supplier_id'] ?? 0),
            'sku_id' => $localSkuId,
            'erp_item_id' => $erpItemId,
            'ordered_rolls' => (float)($line['ordered_rolls'] ?? 0),
            'ordered_weight_kg' => (float)($line['ordered_weight_kg'] ?? 0),
            'grams' => isset($line['grams']) ? (float)$line['grams'] : (float)($sku['grams'] ?? 0),
            'width_mm' => isset($line['width_mm']) ? (float)$line['width_mm'] : (float)($sku['width_mm'] ?? 0),
            'color' => (string)($line['color'] ?? ($sku['color'] ?? '')),
            'meters' => isset($line['meters']) ? (float)$line['meters'] : (float)($sku['meters'] ?? 0),
            'created_at' => (string)($line['created_at'] ?? ''),
            'sku_code' => (string)($line['sku_code'] ?? ($sku['sku_code'] ?? $fallbackSkuCode)),
            'sku_description' => (string)($line['sku_description'] ?? ($sku['sku_description'] ?? $fallbackDescription)),
            'received_rolls' => (int)($line['received_rolls'] ?? 0),
            'received_qty' => (float)($line['received_qty'] ?? 0),
            'received_weight_kg' => (float)($line['received_weight_kg'] ?? 0),
            'po_code' => (string)($line['po_code'] ?? ''),
            'po_status' => (string)($line['po_status'] ?? ''),
            'supplier_name' => (string)($line['supplier_name'] ?? ''),
            'supplier_country_name' => trim((string)($line['supplier_country_name'] ?? '')),
        ];

        $row['reception_mode'] = $this->inferReceptionModeFromErpLine($line);
        $row['supplier_type'] = $this->classifySupplierType((string)$row['supplier_country_name']);
        return $row;
    }

    private function getSavedReceptionMode(int $purchaseOrderLineId, ?int $importContainerItemId = null): ?string
    {
        if ($purchaseOrderLineId <= 0) {
            return null;
        }

        $sql = 'SELECT reception_mode
                FROM rolls
                WHERE purchase_order_line_id = :pol';
        if ($importContainerItemId !== null && $importContainerItemId > 0) {
            $sql .= ' AND import_container_item_id = :container_item_id';
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':pol', $purchaseOrderLineId, PDO::PARAM_INT);
        if ($importContainerItemId !== null && $importContainerItemId > 0) {
            $stmt->bindValue(':container_item_id', $importContainerItemId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $row = $stmt->fetch();

        if ($row === false || trim((string)($row['reception_mode'] ?? '')) === '') {
            return null;
        }

        return $this->normalizeReceptionMode((string)$row['reception_mode']);
    }

    /**
     * @param array<string, mixed> $line
     * @return array<string, mixed>
     */
    private function applySavedReceptionMode(array $line): array
    {
        $savedMode = $this->getSavedReceptionMode(
            (int)($line['id'] ?? 0),
            isset($line['import_container_item_id']) ? (int)$line['import_container_item_id'] : null
        );
        $line['reception_mode'] = $savedMode ?? $this->inferReceptionModeFromErpLine($line);
        return $line;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getErpItem(int $erpItemId): ?array
    {
        if ($erpItemId <= 0) {
            return null;
        }
        if (isset($this->erpItemsCache[$erpItemId])) {
            return $this->erpItemsCache[$erpItemId];
        }

        $stmt = $this->erpPdo->prepare(
            'SELECT i.id,
                    i.item_number,
                    i.item_number_prod,
                    i.item_title,
                    i.item_reg_gsm,
                    i.item_reg_width,
                    i.item_reg_length
             FROM item i
             WHERE i.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $erpItemId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $this->erpItemsCache[$erpItemId] = [
            'id' => (int)$row['id'],
            'sku_code' => trim((string)($row['item_number'] ?? '')) !== '' ? (string)$row['item_number'] : ('ERPITEM-' . $erpItemId),
            'sku_description' => trim((string)($row['item_title'] ?? '')) !== '' ? (string)$row['item_title'] : ('Producto ERP ' . $erpItemId),
            'grams' => (float)($row['item_reg_gsm'] ?? 0),
            'width_mm' => (float)($row['item_reg_width'] ?? 0),
            'meters' => (float)($row['item_reg_length'] ?? 0),
            'color' => '',
        ];

        return $this->erpItemsCache[$erpItemId];
    }

    private function ensureTraceSkuFromErpItem(int $erpItemId, string $fallbackCode = '', string $fallbackDescription = ''): int
    {
        $item = $this->getErpItem($erpItemId);
        $skuCode = $item !== null
            ? (string)$item['sku_code']
            : ($fallbackCode !== '' ? $fallbackCode : ('ERPITEM-' . max(1, $erpItemId)));
        $description = $item !== null
            ? (string)$item['sku_description']
            : ($fallbackDescription !== '' ? $fallbackDescription : $skuCode);
        $description = $this->normalizeLocalSkuDescription($description);

        $stmt = $this->pdo->prepare('SELECT id FROM skus WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $skuCode]);
        $row = $stmt->fetch();
        if ($row !== false) {
            $id = (int)$row['id'];
            $update = $this->pdo->prepare('UPDATE skus SET description = :description, is_active = 1 WHERE id = :id');
            $update->execute([
                ':description' => $description,
                ':id' => $id,
            ]);
            return $id;
        }

        $insert = $this->pdo->prepare('INSERT INTO skus (code, description, is_active) VALUES (:code, :description, 1)');
        $insert->execute([
            ':code' => $skuCode,
            ':description' => $description,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function normalizeLocalSkuDescription(string $description): string
    {
        $description = trim(preg_replace('/\s+/', ' ', $description) ?? '');
        if ($description === '') {
            return 'Producto ERP';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($description, 0, 255);
        }

        return substr($description, 0, 255);
    }

    private function getErpSupplierName(int $supplierId): string
    {
        $meta = $this->getErpSupplierMeta($supplierId);
        return $meta['name'];
    }

    /**
     * @return array{name:string,country_name:string,supplier_type:string}
     */
    private function getErpSupplierMeta(int $supplierId): array
    {
        if ($supplierId <= 0) {
            return [
                'name' => '',
                'country_name' => '',
                'supplier_type' => 'NATIONAL',
            ];
        }
        if (isset($this->erpSupplierMetaCache[$supplierId])) {
            return $this->erpSupplierMetaCache[$supplierId];
        }

        $stmt = $this->erpPdo->prepare(
            'SELECT s.supp_company, c.country_name
             FROM supplier s
             LEFT JOIN country c ON c.id = s.supp_countryid
             WHERE s.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $supplierId]);
        $row = $stmt->fetch();
        $name = $row === false ? '' : (string)$row['supp_company'];
        $countryName = $row === false ? '' : trim((string)($row['country_name'] ?? ''));
        $supplierType = $this->classifySupplierType($countryName);

        $this->erpSuppliersCache[$supplierId] = $name;
        $this->erpSupplierMetaCache[$supplierId] = [
            'name' => $name,
            'country_name' => $countryName,
            'supplier_type' => $supplierType,
        ];

        return $this->erpSupplierMetaCache[$supplierId];
    }

    private function normalizeCountryName(string $countryName): string
    {
        $countryName = trim($countryName);
        if ($countryName === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $countryName);
            if (is_string($normalized) && $normalized !== '') {
                $countryName = $normalized;
            }
        }

        $upper = function_exists('mb_strtoupper')
            ? mb_strtoupper($countryName, 'UTF-8')
            : strtoupper($countryName);

        return trim(preg_replace('/\s+/', ' ', $upper) ?? $upper);
    }

    private function classifySupplierType(string $countryName): string
    {
        return $this->normalizeCountryName($countryName) === 'CHILE' ? 'NATIONAL' : 'IMPORT';
    }

    private function getErpPurchaseOrderCode(int $purchaseOrderId): string
    {
        if ($purchaseOrderId <= 0) {
            return '';
        }
        if (isset($this->erpPurchaseOrdersCache[$purchaseOrderId])) {
            return $this->erpPurchaseOrdersCache[$purchaseOrderId];
        }

        $stmt = $this->erpPdo->prepare('SELECT sord_number FROM supplier_order WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $purchaseOrderId]);
        $row = $stmt->fetch();
        $this->erpPurchaseOrdersCache[$purchaseOrderId] = $row === false ? '' : (string)$row['sord_number'];
        return $this->erpPurchaseOrdersCache[$purchaseOrderId];
    }

    /**
     * @return array{code:string,eta_plant:string}
     */
    private function getErpImportContainerMeta(int $containerId): array
    {
        if ($containerId <= 0) {
            return [
                'code' => '',
                'eta_plant' => '',
            ];
        }
        if (isset($this->erpImportContainersCache[$containerId])) {
            return $this->erpImportContainersCache[$containerId];
        }

        $stmt = $this->erpPdo->prepare(
            'SELECT sord_contenedor, sord_eta_puertounibag
             FROM supplier_contenedor
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $containerId]);
        $row = $stmt->fetch();
        $this->erpImportContainersCache[$containerId] = [
            'code' => $row === false ? '' : trim((string)($row['sord_contenedor'] ?? '')),
            'eta_plant' => $row !== false && (int)($row['sord_eta_puertounibag'] ?? 0) > 0
                ? gmdate('Y-m-d', (int)$row['sord_eta_puertounibag'])
                : '',
        ];

        return $this->erpImportContainersCache[$containerId];
    }

    private function getErpImportContainerCode(int $containerId): string
    {
        return $this->getErpImportContainerMeta($containerId)['code'];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function decorateRollWithErpContext(array &$row): void
    {
        $purchaseOrderId = (int)($row['purchase_order_id'] ?? 0);
        $supplierId = (int)($row['supplier_id'] ?? 0);
        $importContainerId = (int)($row['import_container_id'] ?? 0);
        $containerMeta = $this->getErpImportContainerMeta($importContainerId);
        $row['po_code'] = $this->getErpPurchaseOrderCode($purchaseOrderId);
        $row['container_code'] = $containerMeta['code'];
        $row['arrival_date'] = $containerMeta['eta_plant'];
        $meta = $this->getErpSupplierMeta($supplierId);
        $row['supplier_name'] = $meta['name'];
        $row['supplier_country_name'] = $meta['country_name'];
        $row['supplier_type'] = $meta['supplier_type'];
    }

    private function normalizeReceptionMode(?string $mode): string
    {
        return strtoupper(trim((string)$mode)) === 'WEIGHT' ? 'WEIGHT' : 'QUANTITY';
    }

    public function summarizeReceptionLine(array $line): array
    {
        $mode = $this->normalizeReceptionMode((string)($line['reception_mode'] ?? $this->inferReceptionModeFromErpLine($line)));
        $orderedWeight = round((float)($line['ordered_weight_kg'] ?? 0), 3);
        $receivedWeight = round((float)($line['received_weight_kg'] ?? 0), 3);
        $pendingWeight = max(0, round($orderedWeight - $receivedWeight, 3));

        $orderedRolls = round((float)($line['ordered_rolls'] ?? 0), 3);
        $receivedRolls = round((float)($line['received_qty'] ?? $line['received_rolls'] ?? 0), 3);
        $pendingRolls = max(0, round($orderedRolls - $receivedRolls, 3));

        if ($mode === 'WEIGHT') {
            $ordered = $orderedWeight;
            $received = $receivedWeight;
            $pending = $pendingWeight;
            $unit = 'Kg';
        } else {
            $ordered = $orderedRolls;
            $received = $receivedRolls;
            $pending = $pendingRolls;
            $unit = 'Unid.';
        }

        $complete = ($ordered > 0 && $received >= $ordered)
            || ($orderedRolls > 0 && $receivedRolls >= $orderedRolls)
            || ($orderedWeight > 0 && $receivedWeight >= $orderedWeight);
        $hasProgress = $received > 0 || $receivedRolls > 0 || $receivedWeight > 0;

        return [
            'mode' => $mode,
            'ordered_value' => $ordered,
            'received_value' => $received,
            'pending_value' => $pending,
            'unit_label' => $unit,
            'ordered_weight_kg' => $orderedWeight,
            'received_weight_kg' => $receivedWeight,
            'pending_weight_kg' => $pendingWeight,
            'ordered_rolls' => $orderedRolls,
            'received_rolls' => $receivedRolls,
            'pending_rolls' => $pendingRolls,
            'is_complete' => $complete,
            'has_progress' => $hasProgress,
        ];
    }

    public function listSuppliers(): array
    {
        $stmt = $this->erpPdo->prepare(
            'SELECT s.id,
                    s.supp_company AS name,
                    c.country_name AS country_name
             FROM supplier s
             LEFT JOIN country c ON c.id = s.supp_countryid
             WHERE supp_status = 1
             ORDER BY s.supp_company ASC'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['country_name'] = trim((string)($row['country_name'] ?? ''));
            $row['supplier_type'] = $this->classifySupplierType((string)$row['country_name']);
        }
        unset($row);
        return $rows;
    }

    public function listSuppliersForPurchaseOrders(?string $status = 'active', ?string $supplierType = null): array
    {
        $supplierType = $supplierType !== null ? strtoupper(trim($supplierType)) : '';
        $statusFilter = $status !== null ? strtolower(trim($status)) : '';
        $where = ['s.supp_status = 1', 'po.sord_type = 0', 'po.sord_status > 0', 'po.sord_crtdat >= 1704067200'];
        if ($statusFilter === 'active' || $statusFilter === '') {
            $where[] = 'po.sord_status IN (1, 2, 3) AND COALESCE(po.sord_order_shipped, 0) = 0';
        } elseif ($statusFilter === 'complete') {
            $where[] = '(po.sord_status = 4 OR COALESCE(po.sord_order_shipped, 0) = 1)';
        }
        $whereSql = implode(' AND ', $where);

        $stmt = $this->erpPdo->query(
            "SELECT s.id,
                    s.supp_company AS name,
                    c.country_name AS country_name,
                    soi.id AS line_id,
                    soi.item_amount AS ordered_rolls,
                    soi.item_kgs AS ordered_weight_kg
             FROM supplier_order po
             JOIN supplier s ON s.id = po.sord_supplier_id
             LEFT JOIN country c ON c.id = s.supp_countryid
             JOIN supplier_order_items soi ON soi.sord_id = po.id
             WHERE $whereSql
             ORDER BY s.supp_company ASC, soi.id ASC"
        );
        $rows = $stmt->fetchAll();
        $receivedByLine = $this->getReceivedSummaryByPurchaseOrderLineIds(array_column($rows, 'line_id'));
        $savedModesByLine = $this->getSavedReceptionModesByPurchaseOrderLineIds(array_column($rows, 'line_id'));
        $result = [];
        foreach ($rows as $row) {
            $countryName = trim((string)($row['country_name'] ?? ''));
            $derivedSupplierType = $this->classifySupplierType($countryName);
            if ($supplierType !== '' && $supplierType !== 'ALL' && $derivedSupplierType !== $supplierType) {
                continue;
            }

            $supplierId = (int)$row['id'];
            if (!isset($result[$supplierId])) {
                $result[$supplierId] = [
                    'id' => $supplierId,
                    'name' => (string)$row['name'],
                    'country_name' => $countryName,
                    'supplier_type' => $derivedSupplierType,
                    '_total_lines' => 0,
                    '_completed_lines' => 0,
                ];
            }

            $lineId = (int)($row['line_id'] ?? 0);
            $received = $receivedByLine[$lineId] ?? [
                'received_rolls' => 0,
                'received_qty' => 0.0,
                'received_weight_kg' => 0.0,
            ];
            $line = [
                'ordered_rolls' => (float)($row['ordered_rolls'] ?? 0),
                'ordered_weight_kg' => (float)($row['ordered_weight_kg'] ?? 0),
                'received_rolls' => (int)$received['received_rolls'],
                'received_qty' => (float)$received['received_qty'],
                'received_weight_kg' => (float)$received['received_weight_kg'],
                'reception_mode' => $savedModesByLine[$lineId] ?? $this->inferReceptionModeFromErpLine($row),
            ];
            $summary = $this->summarizeReceptionLine($line);
            $result[$supplierId]['_total_lines']++;
            if ($summary['is_complete']) {
                $result[$supplierId]['_completed_lines']++;
            }
        }

        $filtered = [];
        foreach ($result as $row) {
            $totalLines = (int)$row['_total_lines'];
            $completedLines = (int)$row['_completed_lines'];
            $hasActiveOrders = $totalLines > 0 && $completedLines < $totalLines;
            $hasCompleteOrders = $totalLines > 0 && $completedLines >= $totalLines;
            if (($statusFilter === '' || $statusFilter === 'active') && !$hasActiveOrders) {
                continue;
            }
            if ($statusFilter === 'complete' && !$hasCompleteOrders) {
                continue;
            }
            unset($row['_total_lines'], $row['_completed_lines']);
            $filtered[] = $row;
        }

        return $filtered;
    }

    public function listSuppliersForImportContainers(?string $status = 'active'): array
    {
        $statusFilter = $status !== null ? strtolower(trim($status)) : '';
        $where = ['s.supp_status = 1', 'sc.sord_status > 0', 'so.sord_status > 0'];
        if ($statusFilter === 'active' || $statusFilter === '') {
            $where[] = 'sc.sord_status IN (2, 3)';
            $where[] = 'sc.sord_crtdat >= 1704067200';
        } elseif ($statusFilter === 'complete') {
            $where[] = '(sc.sord_status = 4 OR sc.sord_crtdat < 1704067200)';
        }
        $whereSql = implode(' AND ', $where);

        $stmt = $this->erpPdo->prepare(
            "SELECT s.id,
                    s.supp_company AS name,
                    c.country_name AS country_name,
                    sci.id AS container_item_id,
                    sci.sord_id AS container_id,
                    sc.sord_status AS container_status,
                    sc.sord_crtdat AS container_crtdat,
                    sci.sord_amount AS ordered_rolls,
                    sci.sord_kgs_amount AS ordered_weight_kg
             FROM supplier_contenedor_items sci
             JOIN supplier_contenedor sc ON sc.id = sci.sord_id
             JOIN supplier_order_items soi ON soi.id = sci.sord_pos_id
             JOIN supplier_order so ON so.id = soi.sord_id
             JOIN supplier s ON s.id = so.sord_supplier_id
             LEFT JOIN country c ON c.id = s.supp_countryid
             WHERE $whereSql
             ORDER BY s.supp_company ASC, sci.id ASC"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $receivedByItem = $this->getReceivedSummaryByImportContainerItemIds(array_column($rows, 'container_item_id'));
        $savedModesByItem = $this->getSavedReceptionModesByImportContainerItemIds(array_column($rows, 'container_item_id'));
        $result = [];
        foreach ($rows as $row) {
            $countryName = trim((string)($row['country_name'] ?? ''));
            $derivedSupplierType = $this->classifySupplierType($countryName);
            if ($derivedSupplierType !== 'IMPORT') {
                continue;
            }

            $supplierId = (int)$row['id'];
            if (!isset($result[$supplierId])) {
                $result[$supplierId] = [
                    'id' => $supplierId,
                    'name' => (string)$row['name'],
                    'country_name' => $countryName,
                    'supplier_type' => $derivedSupplierType,
                    '_total_lines' => 0,
                    '_completed_lines' => 0,
                ];
            }

            $containerItemId = (int)($row['container_item_id'] ?? 0);
            $received = $receivedByItem[$containerItemId] ?? [
                'received_rolls' => 0,
                'received_qty' => 0.0,
                'received_weight_kg' => 0.0,
            ];
            $line = [
                'ordered_rolls' => (float)($row['ordered_rolls'] ?? 0),
                'ordered_weight_kg' => (float)($row['ordered_weight_kg'] ?? 0),
                'received_rolls' => (int)$received['received_rolls'],
                'received_qty' => (float)$received['received_qty'],
                'received_weight_kg' => (float)$received['received_weight_kg'],
                'reception_mode' => $savedModesByItem[$containerItemId] ?? $this->inferReceptionModeFromErpLine($row),
            ];
            $summary = $this->summarizeReceptionLine($line);
            $result[$supplierId]['_total_lines']++;
            $crtdat = (int)($row['container_crtdat'] ?? 0);
            $isComplete = ((int)($row['container_status'] ?? 0) === 4) || ($crtdat < 1704067200) || $summary['is_complete'];
            if ($isComplete) {
                $result[$supplierId]['_completed_lines']++;
            }
        }

        $filtered = [];
        foreach ($result as $row) {
            $totalLines = (int)$row['_total_lines'];
            $completedLines = (int)$row['_completed_lines'];
            $hasActiveContainers = $totalLines > 0 && $completedLines < $totalLines;
            $hasCompleteContainers = $totalLines > 0 && $completedLines >= $totalLines;
            if (($statusFilter === '' || $statusFilter === 'active') && !$hasActiveContainers) {
                continue;
            }
            if ($statusFilter === 'complete' && !$hasCompleteContainers) {
                continue;
            }
            unset($row['_total_lines'], $row['_completed_lines']);
            $filtered[] = $row;
        }

        return $filtered;
    }

    public function countPurchaseOrders(
        ?int $supplierId = null,
        ?string $search = null,
        ?string $status = 'active',
        ?string $supplierType = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): int {
        $statusFilter = $status !== null ? strtolower(trim($status)) : '';
        $supplierType = $supplierType !== null ? strtoupper(trim($supplierType)) : '';

        $where = ['po.sord_type = 0', 'po.sord_status > 0', 'po.sord_crtdat >= 1704067200'];
        $params = [];

        if ($statusFilter === 'active' || $statusFilter === '') {
            $where[] = 'po.sord_status IN (1, 2, 3) AND COALESCE(po.sord_order_shipped, 0) = 0';
        } elseif ($statusFilter === 'complete') {
            $where[] = '(po.sord_status = 4 OR COALESCE(po.sord_order_shipped, 0) = 1)';
        }

        if ($supplierType === 'NATIONAL') {
            $where[] = "(c.country_name = 'Chile' OR c.country_name IS NULL OR c.country_name = '')";
        } elseif ($supplierType === 'IMPORT') {
            $where[] = "(c.country_name != 'Chile' AND c.country_name IS NOT NULL AND c.country_name != '')";
        }

        $where[] = 'EXISTS (SELECT 1 FROM supplier_order_items soi WHERE soi.sord_id = po.id)';

        if ($supplierId !== null && $supplierId > 0) {
            $where[] = 'po.sord_supplier_id = :supplier_id';
            $params[':supplier_id'] = $supplierId;
        }

        if ($dateFrom !== null && trim($dateFrom) !== '') {
            $tsFrom = strtotime(trim($dateFrom) . ' 00:00:00');
            if ($tsFrom !== false) {
                $where[] = 'po.sord_crtdat >= :date_from';
                $params[':date_from'] = max(1704067200, $tsFrom);
            }
        }

        if ($dateTo !== null && trim($dateTo) !== '') {
            $tsTo = strtotime(trim($dateTo) . ' 23:59:59');
            if ($tsTo !== false) {
                $where[] = 'po.sord_crtdat <= :date_to';
                $params[':date_to'] = $tsTo;
            }
        }

        $search = $search !== null ? trim($search) : null;
        if ($search !== null && $search !== '') {
            $where[] = 'po.sord_number LIKE :q';
            $params[':q'] = '%' . $search . '%';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT COUNT(*)
                FROM supplier_order po
                JOIN supplier s ON s.id = po.sord_supplier_id
                LEFT JOIN country c ON c.id = s.supp_countryid
                $whereSql";

        $stmt = $this->erpPdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function listPurchaseOrders(
        ?int $supplierId = null,
        ?string $search = null,
        ?string $status = 'active',
        ?string $supplierType = null,
        int $limit = 20,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $offset = 0
    ): array {
        $statusFilter = $status !== null ? strtolower(trim($status)) : '';
        $supplierType = $supplierType !== null ? strtoupper(trim($supplierType)) : '';

        $where = ['po.sord_type = 0', 'po.sord_status > 0', 'po.sord_crtdat >= 1704067200'];
        $params = [];

        if ($statusFilter === 'active' || $statusFilter === '') {
            $where[] = 'po.sord_status IN (1, 2, 3) AND COALESCE(po.sord_order_shipped, 0) = 0';
        } elseif ($statusFilter === 'complete') {
            $where[] = '(po.sord_status = 4 OR COALESCE(po.sord_order_shipped, 0) = 1)';
        }

        if ($supplierType === 'NATIONAL') {
            $where[] = "(c.country_name = 'Chile' OR c.country_name IS NULL OR c.country_name = '')";
        } elseif ($supplierType === 'IMPORT') {
            $where[] = "(c.country_name != 'Chile' AND c.country_name IS NOT NULL AND c.country_name != '')";
        }

        $where[] = 'EXISTS (SELECT 1 FROM supplier_order_items soi WHERE soi.sord_id = po.id)';

        if ($supplierId !== null && $supplierId > 0) {
            $where[] = 'po.sord_supplier_id = :supplier_id';
            $params[':supplier_id'] = $supplierId;
        }

        if ($dateFrom !== null && trim($dateFrom) !== '') {
            $tsFrom = strtotime(trim($dateFrom) . ' 00:00:00');
            if ($tsFrom !== false) {
                $where[] = 'po.sord_crtdat >= :date_from';
                $params[':date_from'] = max(1704067200, $tsFrom);
            }
        }

        if ($dateTo !== null && trim($dateTo) !== '') {
            $tsTo = strtotime(trim($dateTo) . ' 23:59:59');
            if ($tsTo !== false) {
                $where[] = 'po.sord_crtdat <= :date_to';
                $params[':date_to'] = $tsTo;
            }
        }

        $search = $search !== null ? trim($search) : null;
        if ($search !== null && $search !== '') {
            $where[] = 'po.sord_number LIKE :q';
            $params[':q'] = '%' . $search . '%';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $sql = "
            SELECT po.id,
                   po.sord_number AS po_code,
                   po.sord_supplier_id AS supplier_id,
                   po.sord_status,
                   po.sord_order_shipped,
                   po.sord_crtdat,
                   s.supp_company AS supplier_name,
                   c.country_name AS supplier_country_name
            FROM supplier_order po
            JOIN supplier s ON s.id = po.sord_supplier_id
            LEFT JOIN country c ON c.id = s.supp_countryid
            $whereSql
            ORDER BY po.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->erpPdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $orderIds = array_column($rows, 'id');
        $statsByOrder = $this->getPurchaseOrderStatsByIds($orderIds);

        $result = [];
        foreach ($rows as $row) {
            $derivedSupplierType = $this->classifySupplierType((string)($row['supplier_country_name'] ?? ''));
            if ($supplierType !== '' && $supplierType !== 'ALL' && $derivedSupplierType !== $supplierType) {
                continue;
            }

            $stats = $statsByOrder[(int)$row['id']] ?? [
                'total_lines' => 0,
                'completed_lines' => 0,
                'lines_with_progress' => 0,
            ];
            $totalLines = (int)$stats['total_lines'];
            $completedLines = (int)$stats['completed_lines'];
            $linesWithProgress = (int)$stats['lines_with_progress'];
            $erpStatus = (int)($row['sord_status'] ?? 0);
            $erpShipped = (int)($row['sord_order_shipped'] ?? 0);

            $derivedStatus = 'OPEN';
            if ($erpStatus === 4 || $erpShipped === 1 || ($totalLines > 0 && $completedLines >= $totalLines)) {
                $derivedStatus = 'COMPLETE';
            } elseif ($linesWithProgress > 0) {
                $derivedStatus = 'PARTIAL';
            }

            if (($statusFilter === '' || $statusFilter === 'active')) {
                if ($totalLines <= 0 || !in_array($derivedStatus, ['OPEN', 'PARTIAL'], true)) {
                    continue;
                }
            }
            if ($statusFilter === 'complete' && $derivedStatus !== 'COMPLETE') {
                continue;
            }

            $result[] = [
                'id' => (int)$row['id'],
                'po_code' => (string)$row['po_code'],
                'status' => $derivedStatus,
                'created_at' => gmdate('Y-m-d H:i:s', (int)$row['sord_crtdat']),
                'supplier_name' => (string)$row['supplier_name'],
                'supplier_country_name' => trim((string)($row['supplier_country_name'] ?? '')),
                'supplier_type' => $derivedSupplierType,
                'total_lines' => $totalLines,
                'completed_lines' => $completedLines,
            ];
        }

        return $result;
    }

    /**
     * @param array<int, int|string> $purchaseOrderIds
     * @return array<int, array{total_lines:int,completed_lines:int,lines_with_progress:int}>
     */
    private function getPurchaseOrderStatsByIds(array $purchaseOrderIds): array
    {
        $purchaseOrderIds = array_values(array_filter(array_map(static fn($id): int => (int)$id, $purchaseOrderIds), static fn(int $id): bool => $id > 0));
        if ($purchaseOrderIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($purchaseOrderIds), '?'));
        $stmt = $this->erpPdo->prepare(
            "SELECT soi.id,
                    soi.sord_id AS purchase_order_id,
                    soi.item_amount AS ordered_rolls,
                    soi.item_kgs AS ordered_weight_kg
             FROM supplier_order_items soi
             WHERE soi.sord_id IN ($placeholders)"
        );
        $stmt->execute($purchaseOrderIds);
        $lines = $stmt->fetchAll();
        if ($lines === []) {
            return [];
        }

        $receivedByLine = $this->getReceivedSummaryByPurchaseOrderLineIds(array_column($lines, 'id'));
        $savedModesByLine = $this->getSavedReceptionModesByPurchaseOrderLineIds(array_column($lines, 'id'));
        $stats = [];
        foreach ($lines as $row) {
            $purchaseOrderId = (int)($row['purchase_order_id'] ?? 0);
            if (!isset($stats[$purchaseOrderId])) {
                $stats[$purchaseOrderId] = [
                    'total_lines' => 0,
                    'completed_lines' => 0,
                    'lines_with_progress' => 0,
                ];
            }

            $lineId = (int)($row['id'] ?? 0);
            $received = $receivedByLine[$lineId] ?? [
                'received_rolls' => 0,
                'received_qty' => 0.0,
                'received_weight_kg' => 0.0,
            ];
            $line = [
                'ordered_rolls' => (float)($row['ordered_rolls'] ?? 0),
                'ordered_weight_kg' => (float)($row['ordered_weight_kg'] ?? 0),
                'received_rolls' => (int)$received['received_rolls'],
                'received_qty' => (float)$received['received_qty'],
                'received_weight_kg' => (float)$received['received_weight_kg'],
                'reception_mode' => $savedModesByLine[$lineId] ?? $this->inferReceptionModeFromErpLine($row),
            ];
            $summary = $this->summarizeReceptionLine($line);
            $stats[$purchaseOrderId]['total_lines']++;
            if ($summary['is_complete']) {
                $stats[$purchaseOrderId]['completed_lines']++;
            }
            if ($summary['has_progress']) {
                $stats[$purchaseOrderId]['lines_with_progress']++;
            }
        }

        return $stats;
    }

    public function getPurchaseOrder(int $id): ?array
    {
        $stmt = $this->erpPdo->prepare(
            'SELECT po.id,
                    po.sord_number AS po_code,
                    po.sord_supplier_id AS supplier_id,
                    po.sord_status,
                    po.sord_order_shipped,
                    po.sord_crtdat,
                    s.supp_company AS supplier_name,
                    c.country_name AS supplier_country_name
             FROM supplier_order po
             JOIN supplier s ON s.id = po.sord_supplier_id
             LEFT JOIN country c ON c.id = s.supp_countryid
             WHERE po.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $erpStatus = (int)($row['sord_status'] ?? 0);
        $erpShipped = (int)($row['sord_order_shipped'] ?? 0);
        $status = 'OPEN';
        $stats = $this->getPurchaseOrderStatsByIds([$id])[$id] ?? null;
        if (is_array($stats)) {
            $totalLines = (int)$stats['total_lines'];
            $completedLines = (int)$stats['completed_lines'];
            $hasProgress = (int)$stats['lines_with_progress'] > 0;
            if ($erpStatus === 4 || $erpShipped === 1 || ($totalLines > 0 && $completedLines >= $totalLines)) {
                $status = 'COMPLETE';
            } elseif ($hasProgress) {
                $status = 'PARTIAL';
            }
        } elseif ($erpStatus === 4 || $erpShipped === 1) {
            $status = 'COMPLETE';
        }

        return [
            'id' => (int)$row['id'],
            'po_code' => (string)$row['po_code'],
            'status' => $status,
            'created_at' => gmdate('Y-m-d H:i:s', (int)$row['sord_crtdat']),
            'supplier_id' => (int)$row['supplier_id'],
            'supplier_name' => (string)$row['supplier_name'],
            'supplier_country_name' => trim((string)($row['supplier_country_name'] ?? '')),
            'supplier_type' => $this->classifySupplierType((string)($row['supplier_country_name'] ?? '')),
        ];
    }

    public function countImportContainers(
        ?int $supplierId = null,
        ?string $search = null,
        ?string $status = 'active',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $poCode = null,
        ?string $bl = null,
        ?string $containerCode = null,
        ?string $productType = null
    ): int {
        $statusFilter = $status !== null ? strtolower(trim($status)) : '';
        $where = ['sc.sord_status > 0', 'EXISTS (SELECT 1 FROM supplier_contenedor_items sci WHERE sci.sord_id = sc.id)'];
        $params = [];

        if ($statusFilter === 'active' || $statusFilter === '') {
            $where[] = 'sc.sord_status IN (2, 3)';
            $where[] = 'sc.sord_crtdat >= 1704067200';
        } elseif ($statusFilter === 'complete') {
            $where[] = '(sc.sord_status = 4 OR sc.sord_crtdat < 1704067200)';
        }

        if ($supplierId !== null && $supplierId > 0) {
            $where[] = 'EXISTS (
                SELECT 1
                FROM supplier_contenedor_items sci
                JOIN supplier_order_items soi ON soi.id = sci.sord_pos_id
                JOIN supplier_order so ON so.id = soi.sord_id
                WHERE sci.sord_id = sc.id
                  AND so.sord_supplier_id = :supplier_id
            )';
            $params[':supplier_id'] = $supplierId;
        }

        if ($dateFrom !== null && trim($dateFrom) !== '') {
            $tsFrom = strtotime(trim($dateFrom) . ' 00:00:00');
            if ($tsFrom !== false) {
                $where[] = 'sc.sord_crtdat >= :date_from';
                $params[':date_from'] = ($statusFilter === 'active' || $statusFilter === '') ? max(1704067200, $tsFrom) : $tsFrom;
            }
        }

        if ($dateTo !== null && trim($dateTo) !== '') {
            $tsTo = strtotime(trim($dateTo) . ' 23:59:59');
            if ($tsTo !== false) {
                $where[] = 'sc.sord_crtdat <= :date_to';
                $params[':date_to'] = $tsTo;
            }
        }

        if ($poCode !== null && trim($poCode) !== '') {
            $where[] = '(sc.sord_ocs LIKE :po_code OR EXISTS (
                SELECT 1
                FROM supplier_contenedor_items sci_po
                JOIN supplier_order_items soi_po ON soi_po.id = sci_po.sord_pos_id
                JOIN supplier_order so_po ON so_po.id = soi_po.sord_id
                WHERE sci_po.sord_id = sc.id
                  AND so_po.sord_number LIKE :po_code_sub
            ))';
            $params[':po_code'] = '%' . trim($poCode) . '%';
            $params[':po_code_sub'] = '%' . trim($poCode) . '%';
        }

        if ($bl !== null && trim($bl) !== '') {
            $where[] = 'sc.sord_billoflanding LIKE :bl';
            $params[':bl'] = '%' . trim($bl) . '%';
        }

        if ($containerCode !== null && trim($containerCode) !== '') {
            $where[] = 'sc.sord_contenedor LIKE :container_code';
            $params[':container_code'] = '%' . trim($containerCode) . '%';
        }

        if ($productType !== null && trim($productType) !== '') {
            $where[] = 'EXISTS (
                SELECT 1
                FROM supplier_contenedor_items sci_pt
                JOIN supplier_order_items soi_pt ON soi_pt.id = sci_pt.sord_pos_id
                LEFT JOIN item i_pt ON i_pt.id = soi_pt.item_id
                WHERE sci_pt.sord_id = sc.id
                  AND (
                      i_pt.item_number LIKE :product_type_num
                      OR i_pt.item_title LIKE :product_type_title
                      OR soi_pt.item_desc LIKE :product_type_desc
                  )
            )';
            $ptLike = '%' . trim($productType) . '%';
            $params[':product_type_num'] = $ptLike;
            $params[':product_type_title'] = $ptLike;
            $params[':product_type_desc'] = $ptLike;
        }

        $search = $search !== null ? trim($search) : null;
        if ($search !== null && $search !== '') {
            $where[] = '(sc.sord_contenedor LIKE :q_container
                OR sc.sord_billoflanding LIKE :q_bl
                OR sc.sord_buque LIKE :q_vessel
                OR sc.sord_forward LIKE :q_forwarder
                OR sc.sord_ocs LIKE :q_po)';
            $searchLike = '%' . $search . '%';
            $params[':q_container'] = $searchLike;
            $params[':q_bl'] = $searchLike;
            $params[':q_vessel'] = $searchLike;
            $params[':q_forwarder'] = $searchLike;
            $params[':q_po'] = $searchLike;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM supplier_contenedor sc $whereSql";
        $stmt = $this->erpPdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public function listImportContainers(
        ?int $supplierId = null,
        ?string $search = null,
        ?string $status = 'active',
        int $limit = 20,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $poCode = null,
        ?string $bl = null,
        ?string $containerCode = null,
        ?string $productType = null,
        int $offset = 0
    ): array {
        $statusFilter = $status !== null ? strtolower(trim($status)) : '';
        $where = ['sc.sord_status > 0', 'EXISTS (SELECT 1 FROM supplier_contenedor_items sci WHERE sci.sord_id = sc.id)'];
        $params = [];

        if ($statusFilter === 'active' || $statusFilter === '') {
            $where[] = 'sc.sord_status IN (2, 3)';
            $where[] = 'sc.sord_crtdat >= 1704067200';
        } elseif ($statusFilter === 'complete') {
            $where[] = '(sc.sord_status = 4 OR sc.sord_crtdat < 1704067200)';
        }

        if ($supplierId !== null && $supplierId > 0) {
            $where[] = 'EXISTS (
                SELECT 1
                FROM supplier_contenedor_items sci
                JOIN supplier_order_items soi ON soi.id = sci.sord_pos_id
                JOIN supplier_order so ON so.id = soi.sord_id
                WHERE sci.sord_id = sc.id
                  AND so.sord_supplier_id = :supplier_id
            )';
            $params[':supplier_id'] = $supplierId;
        }

        if ($dateFrom !== null && trim($dateFrom) !== '') {
            $tsFrom = strtotime(trim($dateFrom) . ' 00:00:00');
            if ($tsFrom !== false) {
                $where[] = 'sc.sord_crtdat >= :date_from';
                $params[':date_from'] = ($statusFilter === 'active' || $statusFilter === '') ? max(1704067200, $tsFrom) : $tsFrom;
            }
        }

        if ($dateTo !== null && trim($dateTo) !== '') {
            $tsTo = strtotime(trim($dateTo) . ' 23:59:59');
            if ($tsTo !== false) {
                $where[] = 'sc.sord_crtdat <= :date_to';
                $params[':date_to'] = $tsTo;
            }
        }

        if ($poCode !== null && trim($poCode) !== '') {
            $where[] = '(sc.sord_ocs LIKE :po_code OR EXISTS (
                SELECT 1
                FROM supplier_contenedor_items sci_po
                JOIN supplier_order_items soi_po ON soi_po.id = sci_po.sord_pos_id
                JOIN supplier_order so_po ON so_po.id = soi_po.sord_id
                WHERE sci_po.sord_id = sc.id
                  AND so_po.sord_number LIKE :po_code_sub
            ))';
            $params[':po_code'] = '%' . trim($poCode) . '%';
            $params[':po_code_sub'] = '%' . trim($poCode) . '%';
        }

        if ($bl !== null && trim($bl) !== '') {
            $where[] = 'sc.sord_billoflanding LIKE :bl';
            $params[':bl'] = '%' . trim($bl) . '%';
        }

        if ($containerCode !== null && trim($containerCode) !== '') {
            $where[] = 'sc.sord_contenedor LIKE :container_code';
            $params[':container_code'] = '%' . trim($containerCode) . '%';
        }

        if ($productType !== null && trim($productType) !== '') {
            $where[] = 'EXISTS (
                SELECT 1
                FROM supplier_contenedor_items sci_pt
                JOIN supplier_order_items soi_pt ON soi_pt.id = sci_pt.sord_pos_id
                LEFT JOIN item i_pt ON i_pt.id = soi_pt.item_id
                WHERE sci_pt.sord_id = sc.id
                  AND (
                      i_pt.item_number LIKE :product_type_num
                      OR i_pt.item_title LIKE :product_type_title
                      OR soi_pt.item_desc LIKE :product_type_desc
                  )
            )';
            $ptLike = '%' . trim($productType) . '%';
            $params[':product_type_num'] = $ptLike;
            $params[':product_type_title'] = $ptLike;
            $params[':product_type_desc'] = $ptLike;
        }

        $search = $search !== null ? trim($search) : null;
        if ($search !== null && $search !== '') {
            $where[] = '(sc.sord_contenedor LIKE :q_container
                OR sc.sord_billoflanding LIKE :q_bl
                OR sc.sord_buque LIKE :q_vessel
                OR sc.sord_forward LIKE :q_forwarder
                OR sc.sord_ocs LIKE :q_po)';
            $searchLike = '%' . $search . '%';
            $params[':q_container'] = $searchLike;
            $params[':q_bl'] = $searchLike;
            $params[':q_vessel'] = $searchLike;
            $params[':q_forwarder'] = $searchLike;
            $params[':q_po'] = $searchLike;
        }

        $stmt = $this->erpPdo->prepare(
            "SELECT sc.id,
                    sc.sord_contenedor AS container_code,
                    sc.sord_status,
                    sc.sord_buque AS vessel_name,
                    sc.sord_forward AS forwarder_name,
                    sc.sord_billoflanding AS bill_of_lading,
                    sc.sord_ocs AS po_codes,
                    sc.sord_crtdat,
                    sc.sord_eta_puerto,
                    sc.sord_eta_puertounibag
             FROM supplier_contenedor sc
             WHERE " . implode(' AND ', $where) . "
             ORDER BY sc.id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $containerIds = array_column($rows, 'id');
        $statsByContainer = $this->getImportContainerStatsByIds($containerIds);

        $result = [];
        foreach ($rows as $row) {
            $containerId = (int)$row['id'];
            $stats = $statsByContainer[$containerId] ?? [
                'total_lines' => 0,
                'completed_lines' => 0,
                'lines_with_progress' => 0,
            ];
            $totalLines = (int)$stats['total_lines'];
            $completedLines = (int)$stats['completed_lines'];
            $hasProgress = (int)$stats['lines_with_progress'] > 0;
            $erpStatus = (int)($row['sord_status'] ?? 0);
            $crtdat = (int)($row['sord_crtdat'] ?? 0);

            $derivedStatus = 'OPEN';
            if ($erpStatus === 4 || $crtdat < 1704067200 || ($totalLines > 0 && $completedLines >= $totalLines)) {
                $derivedStatus = 'COMPLETE';
            } elseif ($hasProgress) {
                $derivedStatus = 'PARTIAL';
            }

            if (($statusFilter === '' || $statusFilter === 'active')) {
                if ($totalLines <= 0 || !in_array($derivedStatus, ['OPEN', 'PARTIAL'], true)) {
                    continue;
                }
            }
            if ($statusFilter === 'complete' && $derivedStatus !== 'COMPLETE') {
                continue;
            }

            $result[] = [
                'id' => $containerId,
                'container_code' => trim((string)($row['container_code'] ?? '')),
                'vessel_name' => trim((string)($row['vessel_name'] ?? '')),
                'forwarder_name' => trim((string)($row['forwarder_name'] ?? '')),
                'bill_of_lading' => trim((string)($row['bill_of_lading'] ?? '')),
                'po_codes' => trim((string)($row['po_codes'] ?? '')),
                'created_at' => gmdate('Y-m-d H:i:s', (int)($row['sord_crtdat'] ?? 0)),
                'eta_port' => (int)($row['sord_eta_puerto'] ?? 0) > 0 ? gmdate('Y-m-d', (int)$row['sord_eta_puerto']) : '',
                'eta_plant' => (int)($row['sord_eta_puertounibag'] ?? 0) > 0 ? gmdate('Y-m-d', (int)$row['sord_eta_puertounibag']) : '',
                'status' => $derivedStatus,
                'sord_status' => $erpStatus,
                'total_lines' => $totalLines,
                'completed_lines' => $completedLines,
            ];
        }

        return $result;
    }

    /**
     * @param array<int, int|string> $containerIds
     * @return array<int, array{total_lines:int,completed_lines:int,lines_with_progress:int}>
     */
    private function getImportContainerStatsByIds(array $containerIds): array
    {
        $containerIds = array_values(array_filter(array_map(static fn($id): int => (int)$id, $containerIds), static fn(int $id): bool => $id > 0));
        if ($containerIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($containerIds), '?'));
        $stmt = $this->erpPdo->prepare(
            "SELECT sci.id AS container_item_id,
                    sci.sord_id AS container_id,
                    sci.sord_amount AS ordered_rolls,
                    sci.sord_kgs_amount AS ordered_weight_kg
             FROM supplier_contenedor_items sci
             WHERE sci.sord_id IN ($placeholders)"
        );
        $stmt->execute($containerIds);
        $lines = $stmt->fetchAll();
        if ($lines === []) {
            return [];
        }

        $receivedByItem = $this->getReceivedSummaryByImportContainerItemIds(array_column($lines, 'container_item_id'));
        $savedModesByItem = $this->getSavedReceptionModesByImportContainerItemIds(array_column($lines, 'container_item_id'));
        $stats = [];
        foreach ($lines as $row) {
            $containerId = (int)($row['container_id'] ?? 0);
            if (!isset($stats[$containerId])) {
                $stats[$containerId] = [
                    'total_lines' => 0,
                    'completed_lines' => 0,
                    'lines_with_progress' => 0,
                ];
            }

            $containerItemId = (int)($row['container_item_id'] ?? 0);
            $received = $receivedByItem[$containerItemId] ?? [
                'received_rolls' => 0,
                'received_qty' => 0.0,
                'received_weight_kg' => 0.0,
            ];
            $line = [
                'ordered_rolls' => (float)($row['ordered_rolls'] ?? 0),
                'ordered_weight_kg' => (float)($row['ordered_weight_kg'] ?? 0),
                'received_rolls' => (int)$received['received_rolls'],
                'received_qty' => (float)$received['received_qty'],
                'received_weight_kg' => (float)$received['received_weight_kg'],
                'reception_mode' => $savedModesByItem[$containerItemId] ?? $this->inferReceptionModeFromErpLine($row),
            ];
            $summary = $this->summarizeReceptionLine($line);
            $stats[$containerId]['total_lines']++;
            if ($summary['is_complete']) {
                $stats[$containerId]['completed_lines']++;
            }
            if ($summary['has_progress']) {
                $stats[$containerId]['lines_with_progress']++;
            }
        }

        return $stats;
    }

    public function getImportContainer(int $id): ?array
    {
        $stmt = $this->erpPdo->prepare(
            "SELECT sc.id,
                    sc.sord_contenedor AS container_code,
                    sc.sord_status,
                    sc.sord_desc AS description,
                    sc.sord_buque AS vessel_name,
                    sc.sord_forward AS forwarder_name,
                    sc.sord_incoterm AS incoterm,
                    sc.sord_billoflanding AS bill_of_lading,
                    sc.sord_ocs AS po_codes,
                    sc.sord_crtdat,
                    sc.sord_eta_puerto,
                    sc.sord_eta_puertounibag
             FROM supplier_contenedor sc
             WHERE sc.id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $erpStatus = (int)($row['sord_status'] ?? 0);
        $crtdat = (int)($row['sord_crtdat'] ?? 0);
        $status = 'OPEN';
        $stats = $this->getImportContainerStatsByIds([$id])[$id] ?? null;
        if (is_array($stats)) {
            $totalLines = (int)$stats['total_lines'];
            $completedLines = (int)$stats['completed_lines'];
            $hasProgress = (int)$stats['lines_with_progress'] > 0;
            if ($erpStatus === 4 || $crtdat < 1704067200 || ($totalLines > 0 && $completedLines >= $totalLines)) {
                $status = 'COMPLETE';
            } elseif ($hasProgress) {
                $status = 'PARTIAL';
            }
        } elseif ($erpStatus === 4 || $crtdat < 1704067200) {
            $status = 'COMPLETE';
        }

        return [
            'id' => (int)$row['id'],
            'container_code' => trim((string)($row['container_code'] ?? '')),
            'sord_status' => $erpStatus,
            'description' => trim((string)($row['description'] ?? '')),
            'vessel_name' => trim((string)($row['vessel_name'] ?? '')),
            'forwarder_name' => trim((string)($row['forwarder_name'] ?? '')),
            'incoterm' => trim((string)($row['incoterm'] ?? '')),
            'bill_of_lading' => trim((string)($row['bill_of_lading'] ?? '')),
            'po_codes' => trim((string)($row['po_codes'] ?? '')),
            'created_at' => gmdate('Y-m-d H:i:s', (int)($row['sord_crtdat'] ?? 0)),
            'eta_port' => (int)($row['sord_eta_puerto'] ?? 0) > 0 ? gmdate('Y-m-d', (int)$row['sord_eta_puerto']) : '',
            'eta_plant' => (int)($row['sord_eta_puertounibag'] ?? 0) > 0 ? gmdate('Y-m-d', (int)$row['sord_eta_puertounibag']) : '',
            'status' => $status,
        ];
    }

    public function listImportContainerLines(int $containerId): array
    {
        $stmt = $this->erpPdo->prepare(
            "SELECT sci.id AS container_item_id,
                    sci.sord_id AS container_id,
                    sci.sord_amount AS container_ordered_rolls,
                    sci.sord_kgs_amount AS container_ordered_weight_kg,
                    sc.sord_contenedor AS container_code,
                    soi.id,
                    soi.sord_id AS purchase_order_id,
                    so.sord_supplier_id AS supplier_id,
                    soi.item_id AS erp_item_id,
                    soi.item_desc AS line_description,
                    so.sord_number AS po_code,
                    so.sord_crtdat,
                    s.supp_company AS supplier_name,
                    c.country_name AS supplier_country_name
             FROM supplier_contenedor_items sci
             JOIN supplier_contenedor sc ON sc.id = sci.sord_id
             JOIN supplier_order_items soi ON soi.id = sci.sord_pos_id
             JOIN supplier_order so ON so.id = soi.sord_id
             JOIN supplier s ON s.id = so.sord_supplier_id
             LEFT JOIN country c ON c.id = s.supp_countryid
             WHERE sci.sord_id = :id
             ORDER BY sci.id ASC"
        );
        $stmt->execute([':id' => $containerId]);
        $rows = $stmt->fetchAll();
        $summaryByItem = $this->getReceivedSummaryByImportContainerItemIds(array_column($rows, 'container_item_id'));
        $savedModesByItem = $this->getSavedReceptionModesByImportContainerItemIds(array_column($rows, 'container_item_id'));

        $normalized = [];
        foreach ($rows as $row) {
            $containerItemId = (int)$row['container_item_id'];
            $received = $summaryByItem[$containerItemId] ?? [
                'received_rolls' => 0,
                'received_qty' => 0.0,
                'received_weight_kg' => 0.0,
            ];
            $normalizedLine = $this->normalizeErpPurchaseOrderLine(array_merge($row, $received, [
                'ordered_rolls' => (float)($row['container_ordered_rolls'] ?? 0),
                'ordered_weight_kg' => (float)($row['container_ordered_weight_kg'] ?? 0),
                'created_at' => gmdate('Y-m-d H:i:s', (int)($row['sord_crtdat'] ?? 0)),
            ]));
            $normalizedLine['import_container_id'] = (int)$row['container_id'];
            $normalizedLine['import_container_item_id'] = $containerItemId;
            $normalizedLine['container_code'] = trim((string)($row['container_code'] ?? ''));
            if (isset($savedModesByItem[$containerItemId])) {
                $normalizedLine['reception_mode'] = $savedModesByItem[$containerItemId];
            }
            $normalized[] = $normalizedLine;
        }

        return $normalized;
    }

    public function listImportContainerLinesForContainers(array $containerIds): array
    {
        if ($containerIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($containerIds), '?'));
        $stmt = $this->erpPdo->prepare(
            "SELECT sci.id AS container_item_id,
                    sci.sord_id AS container_id,
                    sci.sord_amount AS container_ordered_rolls,
                    sci.sord_kgs_amount AS container_ordered_weight_kg,
                    sc.sord_contenedor AS container_code,
                    soi.id,
                    soi.sord_id AS purchase_order_id,
                    so.sord_supplier_id AS supplier_id,
                    soi.item_id AS erp_item_id,
                    soi.item_desc AS line_description,
                    so.sord_number AS po_code,
                    so.sord_crtdat,
                    s.supp_company AS supplier_name,
                    c.country_name AS supplier_country_name,
                    i.item_number AS sku_code,
                    i.item_title AS sku_title,
                    i.item_reg_gsm AS grams,
                    i.item_reg_width AS width_mm,
                    '' AS color,
                    i.item_reg_length AS meters
             FROM supplier_contenedor_items sci
             JOIN supplier_contenedor sc ON sc.id = sci.sord_id
             JOIN supplier_order_items soi ON soi.id = sci.sord_pos_id
             JOIN supplier_order so ON so.id = soi.sord_id
             JOIN supplier s ON s.id = so.sord_supplier_id
             LEFT JOIN country c ON c.id = s.supp_countryid
             LEFT JOIN item i ON i.id = soi.item_id
             WHERE sci.sord_id IN ($in)
             ORDER BY sci.sord_id DESC, sci.id ASC"
        );
        $stmt->execute($containerIds);
        $rows = $stmt->fetchAll();
        if ($rows === []) {
            return [];
        }
        $summaryByItem = $this->getReceivedSummaryByImportContainerItemIds(array_column($rows, 'container_item_id'));
        $savedModesByItem = $this->getSavedReceptionModesByImportContainerItemIds(array_column($rows, 'container_item_id'));

        $normalized = [];
        foreach ($rows as $row) {
            $containerItemId = (int)$row['container_item_id'];
            $received = $summaryByItem[$containerItemId] ?? [
                'received_rolls' => 0,
                'received_qty' => 0.0,
                'received_weight_kg' => 0.0,
            ];
            $lineId = (int)$row['id'];
            $erpItemId = (int)($row['erp_item_id'] ?? 0);
            $skuCode = trim((string)($row['sku_code'] ?? ''));
            if ($skuCode === '') {
                $skuCode = $erpItemId > 0 ? ('ERPITEM-' . $erpItemId) : ('ERP-LINE-' . $lineId);
            }
            $skuDesc = trim((string)($row['sku_title'] ?? ''));
            if ($skuDesc === '') {
                $skuDesc = trim((string)($row['line_description'] ?? ''));
            }
            $mode = $savedModesByItem[$containerItemId] ?? ($row['container_ordered_weight_kg'] > 0 ? 'WEIGHT' : 'QUANTITY');

            $normalized[] = [
                'id' => $lineId,
                'import_container_id' => (int)$row['container_id'],
                'import_container_item_id' => $containerItemId,
                'container_code' => trim((string)($row['container_code'] ?? '')),
                'purchase_order_id' => (int)($row['purchase_order_id'] ?? 0),
                'supplier_id' => (int)($row['supplier_id'] ?? 0),
                'erp_item_id' => $erpItemId,
                'ordered_rolls' => (float)($row['container_ordered_rolls'] ?? 0),
                'ordered_weight_kg' => (float)($row['container_ordered_weight_kg'] ?? 0),
                'grams' => (float)($row['grams'] ?? 0),
                'width_mm' => (float)($row['width_mm'] ?? 0),
                'color' => (string)($row['color'] ?? ''),
                'meters' => (float)($row['meters'] ?? 0),
                'created_at' => gmdate('Y-m-d H:i:s', (int)($row['sord_crtdat'] ?? 0)),
                'sku_code' => $skuCode,
                'sku_description' => $skuDesc,
                'received_rolls' => (int)$received['received_rolls'],
                'received_qty' => (float)$received['received_qty'],
                'received_weight_kg' => (float)$received['received_weight_kg'],
                'po_code' => (string)($row['po_code'] ?? ''),
                'supplier_name' => (string)($row['supplier_name'] ?? ''),
                'supplier_country_name' => trim((string)($row['supplier_country_name'] ?? '')),
                'reception_mode' => $mode,
            ];
        }

        return $normalized;
    }

    public function getImportContainerLine(int $containerItemId): ?array
    {
        $stmt = $this->erpPdo->prepare(
            "SELECT sci.id AS container_item_id,
                    sci.sord_id AS container_id,
                    sci.sord_amount AS container_ordered_rolls,
                    sci.sord_kgs_amount AS container_ordered_weight_kg,
                    sc.sord_contenedor AS container_code,
                    soi.id,
                    soi.sord_id AS purchase_order_id,
                    so.sord_supplier_id AS supplier_id,
                    soi.item_id AS erp_item_id,
                    soi.item_desc AS line_description,
                    so.sord_number AS po_code,
                    so.sord_crtdat,
                    s.supp_company AS supplier_name,
                    c.country_name AS supplier_country_name
             FROM supplier_contenedor_items sci
             JOIN supplier_contenedor sc ON sc.id = sci.sord_id
             JOIN supplier_order_items soi ON soi.id = sci.sord_pos_id
             JOIN supplier_order so ON so.id = soi.sord_id
             JOIN supplier s ON s.id = so.sord_supplier_id
             LEFT JOIN country c ON c.id = s.supp_countryid
             WHERE sci.id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $containerItemId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $received = $this->getReceivedSummaryByImportContainerItemIds([$containerItemId])[$containerItemId] ?? [
            'received_rolls' => 0,
            'received_qty' => 0.0,
            'received_weight_kg' => 0.0,
        ];
        $normalized = $this->normalizeErpPurchaseOrderLine(array_merge($row, $received, [
            'ordered_rolls' => (float)($row['container_ordered_rolls'] ?? 0),
            'ordered_weight_kg' => (float)($row['container_ordered_weight_kg'] ?? 0),
            'created_at' => gmdate('Y-m-d H:i:s', (int)($row['sord_crtdat'] ?? 0)),
        ]));
        $normalized['import_container_id'] = (int)$row['container_id'];
        $normalized['import_container_item_id'] = $containerItemId;
        $normalized['container_code'] = trim((string)($row['container_code'] ?? ''));
        return $this->applySavedReceptionMode($normalized);
    }

    public function listPurchaseOrderLines(int $purchaseOrderId): array
    {
        $stmt = $this->erpPdo->prepare(
            "SELECT soi.id,
                    soi.sord_id AS purchase_order_id,
                    so.sord_supplier_id AS supplier_id,
                    soi.item_id AS erp_item_id,
                    soi.item_amount AS ordered_rolls,
                    soi.item_kgs AS ordered_weight_kg,
                    soi.item_desc AS line_description,
                    so.sord_number AS po_code,
                    so.sord_crtdat,
                    s.supp_company AS supplier_name,
                    c.country_name AS supplier_country_name
             FROM supplier_order_items soi
             JOIN supplier_order so ON so.id = soi.sord_id
             JOIN supplier s ON s.id = so.sord_supplier_id
             LEFT JOIN country c ON c.id = s.supp_countryid
             WHERE soi.sord_id = :po
             ORDER BY soi.id ASC"
        );
        $stmt->execute([':po' => $purchaseOrderId]);
        $rows = $stmt->fetchAll();
        $summaryByLine = $this->getReceivedSummaryByPurchaseOrderLineIds(array_column($rows, 'id'));
        $savedModesByLine = $this->getSavedReceptionModesByPurchaseOrderLineIds(array_column($rows, 'id'));
        $normalized = [];
        foreach ($rows as $row) {
            $lineId = (int)$row['id'];
            $received = $summaryByLine[$lineId] ?? [
                'received_rolls' => 0,
                'received_qty' => 0.0,
                'received_weight_kg' => 0.0,
            ];
            $line = $this->normalizeErpPurchaseOrderLine(array_merge($row, $received, [
                'created_at' => gmdate('Y-m-d H:i:s', (int)($row['sord_crtdat'] ?? 0)),
            ]));
            if (isset($savedModesByLine[$lineId])) {
                $line['reception_mode'] = $savedModesByLine[$lineId];
            }
            $normalized[] = $line;
        }

        return $normalized;
    }

    public function listPurchaseOrderLinesForOrders(array $purchaseOrderIds): array
    {
        if ($purchaseOrderIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($purchaseOrderIds), '?'));
        $stmt = $this->erpPdo->prepare(
            "SELECT soi.id,
                    soi.sord_id AS purchase_order_id,
                    so.sord_supplier_id AS supplier_id,
                    soi.item_id AS erp_item_id,
                    soi.item_amount AS ordered_rolls,
                    soi.item_kgs AS ordered_weight_kg,
                    soi.item_desc AS line_description,
                    so.sord_number AS po_code,
                    so.sord_crtdat,
                    s.supp_company AS supplier_name,
                    c.country_name AS supplier_country_name,
                    i.item_number AS sku_code,
                    i.item_title AS sku_title,
                    i.item_reg_gsm AS grams,
                    i.item_reg_width AS width_mm,
                    '' AS color,
                    i.item_reg_length AS meters
             FROM supplier_order_items soi
             JOIN supplier_order so ON so.id = soi.sord_id
             JOIN supplier s ON s.id = so.sord_supplier_id
             LEFT JOIN country c ON c.id = s.supp_countryid
             LEFT JOIN item i ON i.id = soi.item_id
             WHERE soi.sord_id IN ($in)
             ORDER BY soi.sord_id DESC, soi.id ASC"
        );
        $stmt->execute($purchaseOrderIds);
        $rows = $stmt->fetchAll();
        if ($rows === []) {
            return [];
        }
        $summaryByLine = $this->getReceivedSummaryByPurchaseOrderLineIds(array_column($rows, 'id'));
        $savedModesByLine = $this->getSavedReceptionModesByPurchaseOrderLineIds(array_column($rows, 'id'));
        $normalized = [];
        foreach ($rows as $row) {
            $lineId = (int)$row['id'];
            $received = $summaryByLine[$lineId] ?? [
                'received_rolls' => 0,
                'received_qty' => 0.0,
                'received_weight_kg' => 0.0,
            ];
            $erpItemId = (int)($row['erp_item_id'] ?? 0);
            $skuCode = trim((string)($row['sku_code'] ?? ''));
            if ($skuCode === '') {
                $skuCode = $erpItemId > 0 ? ('ERPITEM-' . $erpItemId) : ('ERP-LINE-' . $lineId);
            }
            $skuDesc = trim((string)($row['sku_title'] ?? ''));
            if ($skuDesc === '') {
                $skuDesc = trim((string)($row['line_description'] ?? ''));
            }
            $mode = $savedModesByLine[$lineId] ?? ($row['ordered_weight_kg'] > 0 ? 'WEIGHT' : 'QUANTITY');

            $normalized[] = [
                'id' => $lineId,
                'purchase_order_id' => (int)($row['purchase_order_id'] ?? 0),
                'supplier_id' => (int)($row['supplier_id'] ?? 0),
                'erp_item_id' => $erpItemId,
                'ordered_rolls' => (float)($row['ordered_rolls'] ?? 0),
                'ordered_weight_kg' => (float)($row['ordered_weight_kg'] ?? 0),
                'grams' => (float)($row['grams'] ?? 0),
                'width_mm' => (float)($row['width_mm'] ?? 0),
                'color' => (string)($row['color'] ?? ''),
                'meters' => (float)($row['meters'] ?? 0),
                'created_at' => gmdate('Y-m-d H:i:s', (int)($row['sord_crtdat'] ?? 0)),
                'sku_code' => $skuCode,
                'sku_description' => $skuDesc,
                'received_rolls' => (int)$received['received_rolls'],
                'received_qty' => (float)$received['received_qty'],
                'received_weight_kg' => (float)$received['received_weight_kg'],
                'po_code' => (string)($row['po_code'] ?? ''),
                'supplier_name' => (string)($row['supplier_name'] ?? ''),
                'supplier_country_name' => trim((string)($row['supplier_country_name'] ?? '')),
                'reception_mode' => $mode,
            ];
        }

        return $normalized;
    }

    public function getPurchaseOrderLine(int $id): ?array
    {
        $stmt = $this->erpPdo->prepare(
            "SELECT soi.id,
                    soi.sord_id AS purchase_order_id,
                    so.sord_supplier_id AS supplier_id,
                    soi.item_id AS erp_item_id,
                    soi.item_amount AS ordered_rolls,
                    soi.item_kgs AS ordered_weight_kg,
                    soi.item_desc AS line_description,
                    so.sord_number AS po_code,
                    so.sord_crtdat,
                    s.supp_company AS supplier_name,
                    c.country_name AS supplier_country_name
             FROM supplier_order_items soi
             JOIN supplier_order so ON so.id = soi.sord_id
             JOIN supplier s ON s.id = so.sord_supplier_id
             LEFT JOIN country c ON c.id = s.supp_countryid
             WHERE soi.id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $received = $this->getReceivedSummaryByPurchaseOrderLineIds([(int)$row['id']]);
        $normalized = $this->applySavedReceptionMode($this->normalizeErpPurchaseOrderLine(array_merge($row, $received[(int)$row['id']] ?? [
            'received_rolls' => 0,
            'received_qty' => 0.0,
            'received_weight_kg' => 0.0,
        ], [
            'created_at' => gmdate('Y-m-d H:i:s', (int)($row['sord_crtdat'] ?? 0)),
        ])));

        $purchaseOrder = $this->getPurchaseOrder((int)$normalized['purchase_order_id']);
        $normalized['po_status'] = (string)($purchaseOrder['status'] ?? 'OPEN');
        return $normalized;
    }

    public function createRollFromPurchaseOrderLine(
        int $purchaseOrderLineId,
        int $warehouseId,
        float $weightKg,
        string $operatorName = '',
        float $receivedQty = 1.0,
        ?string $receptionMode = null,
        ?int $importContainerId = null,
        ?int $importContainerItemId = null
    ): array
    {
        $errors = [];
        if ($purchaseOrderLineId <= 0) {
            $errors['purchase_order_line_id'] = 'Línea de OC es obligatoria.';
        }
        if ($warehouseId <= 0) {
            $errors['warehouse_id'] = 'Bodega es obligatoria.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }

        $line = $this->getPurchaseOrderLine($purchaseOrderLineId);
        if ($line === null) {
            return ['ok' => false, 'errors' => ['purchase_order_line_id' => 'Línea de OC no existe.'], 'id' => null];
        }

        if (($line['po_status'] ?? null) === 'COMPLETE') {
            return ['ok' => false, 'errors' => ['purchase_order_line_id' => 'Esta recepción ya está finalizada y no permite agregar más.'], 'id' => null];
        }

        $selectedMode = $this->normalizeReceptionMode((string)($receptionMode ?? ($line['reception_mode'] ?? 'QUANTITY')));
        $line['reception_mode'] = $selectedMode;
        $summary = $this->summarizeReceptionLine($line);
        if ($weightKg <= 0) {
            return ['ok' => false, 'errors' => ['weight_kg' => 'Peso real (Kg) debe ser mayor a 0.'], 'id' => null];
        }
        if ($selectedMode === 'QUANTITY' && $receivedQty <= 0) {
            return ['ok' => false, 'errors' => ['received_qty' => 'Cantidad recibida debe ser mayor a 0.'], 'id' => null];
        }
        if ($summary['is_complete']) {
            return ['ok' => false, 'errors' => ['purchase_order_line_id' => 'Esta línea ya está completa y no permite más recepciones.'], 'id' => null];
        }
        if ($selectedMode === 'WEIGHT' && $weightKg > ((float)$summary['pending_value'] + 0.0001)) {
            return ['ok' => false, 'errors' => ['weight_kg' => 'El peso recibido supera lo pendiente por recepcionar en esta línea.'], 'id' => null];
        }
        if ($selectedMode === 'QUANTITY' && $receivedQty > ((float)$summary['pending_value'] + 0.0001)) {
            return ['ok' => false, 'errors' => ['received_qty' => 'La cantidad recibida supera lo pendiente por recepcionar en esta línea.'], 'id' => null];
        }

        $input = [
            'sku_id' => (int)$line['sku_id'],
            'warehouse_id' => $warehouseId,
            'weight_kg' => $weightKg,
            'received_qty' => $selectedMode === 'WEIGHT' ? 1.0 : $receivedQty,
            'reception_mode' => $selectedMode,
            'microns' => $line['grams'],
            'width_mm' => $line['width_mm'],
            'color' => $line['color'],
            'meters' => $line['meters'],
            'purchase_order_id' => (int)$line['purchase_order_id'],
            'purchase_order_line_id' => (int)$line['id'],
            'import_container_id' => $importContainerId !== null && $importContainerId > 0 ? $importContainerId : null,
            'import_container_item_id' => $importContainerItemId !== null && $importContainerItemId > 0 ? $importContainerItemId : null,
            'supplier_id' => (int)$line['supplier_id'],
            'operator_name' => trim($operatorName),
        ];

        $result = $this->createRoll($input);
        if ($result['ok'] !== true) {
            return $result;
        }

        $this->refreshPurchaseOrderStatus((int)$line['purchase_order_id']);
        return $result;
    }

    public function createRollFromImportContainerLine(
        int $containerItemId,
        int $warehouseId,
        float $weightKg,
        string $operatorName = '',
        float $receivedQty = 1.0,
        ?string $receptionMode = null
    ): array
    {
        $errors = [];
        if ($containerItemId <= 0) {
            $errors['import_container_item_id'] = 'Línea de contenedor es obligatoria.';
        }
        if ($warehouseId <= 0) {
            $errors['warehouse_id'] = 'Bodega es obligatoria.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }

        $line = $this->getImportContainerLine($containerItemId);
        if ($line === null) {
            return ['ok' => false, 'errors' => ['import_container_item_id' => 'Línea de contenedor no existe.'], 'id' => null];
        }

        $purchaseOrderLine = $this->getPurchaseOrderLine((int)$line['id']);
        if ($purchaseOrderLine === null) {
            return ['ok' => false, 'errors' => ['purchase_order_line_id' => 'Línea de OC asociada no existe.'], 'id' => null];
        }

        if (($purchaseOrderLine['po_status'] ?? null) === 'COMPLETE') {
            return ['ok' => false, 'errors' => ['purchase_order_line_id' => 'Esta recepción ya está finalizada y no permite agregar más.'], 'id' => null];
        }

        $selectedMode = $this->normalizeReceptionMode((string)($receptionMode ?? ($line['reception_mode'] ?? 'QUANTITY')));
        $line['reception_mode'] = $selectedMode;
        $summary = $this->summarizeReceptionLine($line);
        if ($weightKg <= 0) {
            return ['ok' => false, 'errors' => ['weight_kg' => 'Peso real (Kg) debe ser mayor a 0.'], 'id' => null];
        }
        if ($selectedMode === 'QUANTITY' && $receivedQty <= 0) {
            return ['ok' => false, 'errors' => ['received_qty' => 'Cantidad recibida debe ser mayor a 0.'], 'id' => null];
        }
        if ($summary['is_complete']) {
            return ['ok' => false, 'errors' => ['import_container_item_id' => 'Esta línea de contenedor ya está completa y no permite más recepciones.'], 'id' => null];
        }
        if ($selectedMode === 'WEIGHT' && $weightKg > ((float)$summary['pending_value'] + 0.0001)) {
            return ['ok' => false, 'errors' => ['weight_kg' => 'El peso recibido supera lo pendiente por recepcionar en esta línea.'], 'id' => null];
        }
        if ($selectedMode === 'QUANTITY' && $receivedQty > ((float)$summary['pending_value'] + 0.0001)) {
            return ['ok' => false, 'errors' => ['received_qty' => 'La cantidad recibida supera lo pendiente por recepcionar en esta línea.'], 'id' => null];
        }

        $input = [
            'sku_id' => (int)$line['sku_id'],
            'warehouse_id' => $warehouseId,
            'weight_kg' => $weightKg,
            'received_qty' => $selectedMode === 'WEIGHT' ? 1.0 : $receivedQty,
            'reception_mode' => $selectedMode,
            'microns' => $line['grams'],
            'width_mm' => $line['width_mm'],
            'color' => $line['color'],
            'meters' => $line['meters'],
            'purchase_order_id' => (int)$line['purchase_order_id'],
            'purchase_order_line_id' => (int)$line['id'],
            'import_container_id' => (int)($line['import_container_id'] ?? 0),
            'import_container_item_id' => (int)($line['import_container_item_id'] ?? 0),
            'supplier_id' => (int)$line['supplier_id'],
            'operator_name' => trim($operatorName),
        ];

        $result = $this->createRoll($input);
        if ($result['ok'] !== true) {
            return $result;
        }

        $this->refreshPurchaseOrderStatus((int)$line['purchase_order_id']);
        $containerId = (int)($line['import_container_id'] ?? 0);
        if ($containerId > 0) {
            $this->refreshImportContainerStatus($containerId);
        }
        return $result;
    }

    public function ensureReceptionClosureSchema(): void
    {
        try {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS reception_closures (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    entity_type ENUM('PURCHASE_ORDER', 'IMPORT_CONTAINER') NOT NULL,
                    entity_id INT UNSIGNED NOT NULL,
                    closed_by_user_id INT UNSIGNED NOT NULL DEFAULT 0,
                    reason VARCHAR(150) NOT NULL,
                    notes TEXT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_entity (entity_type, entity_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
            );
        } catch (Throwable) {
        }
    }

    public function closePurchaseOrderReception(int $purchaseOrderId, int $userId = 0, string $reason = '', ?string $notes = null): array
    {
        if ($purchaseOrderId <= 0) {
            return ['ok' => false, 'error' => 'ID de orden de compra inválido'];
        }
        $this->ensureReceptionClosureSchema();

        $stmt = $this->erpPdo->prepare(
            'UPDATE supplier_order
             SET sord_status = 4,
                 sord_order_shipped = 1,
                 sord_upddat = :upddat,
                 sord_updusr = :updusr
             WHERE id = :id'
        );
        $ok = $stmt->execute([
            ':upddat' => time(),
            ':updusr' => $userId > 0 ? $userId : 1,
            ':id' => $purchaseOrderId,
        ]);

        if (!$ok) {
            return ['ok' => false, 'error' => 'No se pudo actualizar la orden de compra en el ERP'];
        }

        try {
            $stmtIns = $this->pdo->prepare(
                'INSERT INTO reception_closures (entity_type, entity_id, closed_by_user_id, reason, notes)
                 VALUES ("PURCHASE_ORDER", :entity_id, :user_id, :reason, :notes)'
            );
            $stmtIns->execute([
                ':entity_id' => $purchaseOrderId,
                ':user_id' => $userId,
                ':reason' => $reason !== '' ? $reason : 'Falla de proveedor (Reembolso acordado)',
                ':notes' => $notes !== '' ? $notes : null,
            ]);
        } catch (Throwable) {
        }

        return ['ok' => true];
    }

    public function closeImportContainerReception(int $containerId, int $userId = 0, string $reason = '', ?string $notes = null): array
    {
        if ($containerId <= 0) {
            return ['ok' => false, 'error' => 'ID de contenedor inválido'];
        }
        $this->ensureReceptionClosureSchema();

        $stmt = $this->erpPdo->prepare(
            'UPDATE supplier_contenedor
             SET sord_status = 4,
                 sord_upddat = :upddat,
                 sord_updusr = :updusr
             WHERE id = :id'
        );
        $ok = $stmt->execute([
            ':upddat' => time(),
            ':updusr' => $userId > 0 ? $userId : 1,
            ':id' => $containerId,
        ]);

        if (!$ok) {
            return ['ok' => false, 'error' => 'No se pudo actualizar el contenedor en el ERP'];
        }

        try {
            $stmtIns = $this->pdo->prepare(
                'INSERT INTO reception_closures (entity_type, entity_id, closed_by_user_id, reason, notes)
                 VALUES ("IMPORT_CONTAINER", :entity_id, :user_id, :reason, :notes)'
            );
            $stmtIns->execute([
                ':entity_id' => $containerId,
                ':user_id' => $userId,
                ':reason' => $reason !== '' ? $reason : 'Falla de proveedor (Reembolso acordado)',
                ':notes' => $notes !== '' ? $notes : null,
            ]);
        } catch (Throwable) {
        }

        return ['ok' => true];
    }

    public function getReceptionClosure(string $entityType, int $entityId): ?array
    {
        $this->ensureReceptionClosureSchema();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, entity_type, entity_id, closed_by_user_id, reason, notes, created_at
                 FROM reception_closures
                 WHERE entity_type = :entity_type AND entity_id = :entity_id
                 ORDER BY id DESC
                 LIMIT 1'
            );
            $stmt->execute([
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
            ]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    public function archiveImportContainer(int $containerId, int $userId = 0): bool
    {
        if ($containerId <= 0) {
            return false;
        }
        $stmt = $this->erpPdo->prepare(
            'UPDATE supplier_contenedor
             SET sord_status = 4,
                 sord_upddat = :upddat,
                 sord_updusr = :updusr
             WHERE id = :id'
        );
        return $stmt->execute([
            ':upddat' => time(),
            ':updusr' => $userId > 0 ? $userId : 1,
            ':id' => $containerId,
        ]);
    }

    public function refreshImportContainerStatus(int $containerId): void
    {
        if ($containerId <= 0) {
            return;
        }
        $lines = $this->listImportContainerLines($containerId);
        if ($lines === []) {
            return;
        }
        $allComplete = true;
        foreach ($lines as $line) {
            $summary = $this->summarizeReceptionLine($line);
            if (!$summary['is_complete']) {
                $allComplete = false;
                break;
            }
        }
        if ($allComplete) {
            $this->archiveImportContainer($containerId);
        }
    }

    public function refreshPurchaseOrderStatus(int $purchaseOrderId): void
    {
        $lines = $this->listPurchaseOrderLines($purchaseOrderId);
        if ($lines === []) {
            return;
        }

        $hasProgress = false;
        $allComplete = true;

        foreach ($lines as $line) {
            $summary = $this->summarizeReceptionLine($line);
            $shippedValue = $summary['mode'] === 'WEIGHT'
                ? round((float)$summary['received_value'], 2)
                : round((float)$summary['received_value'], 2);
            $updateLine = $this->erpPdo->prepare(
                'UPDATE supplier_order_items
                 SET item_amount_shipped = :shipped
                 WHERE id = :id'
            );
            $updateLine->execute([
                ':shipped' => number_format($shippedValue, 2, '.', ''),
                ':id' => (int)$line['id'],
            ]);

            if ($summary['has_progress']) {
                $hasProgress = true;
            }
            if (!$summary['is_complete']) {
                $allComplete = false;
            }
        }

        $updateOrder = $this->erpPdo->prepare(
            'UPDATE supplier_order
             SET sord_order_shipped = :is_complete
             WHERE id = :id'
        );
        $updateOrder->execute([
            ':is_complete' => $allComplete ? 1 : 0,
            ':id' => $purchaseOrderId,
        ]);
        if ($allComplete) {
            $updateStatus = $this->erpPdo->prepare(
                'UPDATE supplier_order
                 SET sord_status = 4,
                     sord_upddat = :upddat
                 WHERE id = :id'
            );
            $updateStatus->execute([
                ':upddat' => time(),
                ':id' => $purchaseOrderId,
            ]);
        }
    }

    public function listWorkOrders(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, ot_code, sku_final, target_qty, status, created_at
             FROM work_orders
             ORDER BY id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listWorkOrdersByView(string $view, int $limit = 50): array
    {
        $this->syncWorkOrdersFromErpProductionPlan();
        $view = strtolower(trim($view));
        $statuses = match ($view) {
            'active' => ['ACTIVE', 'CUTTING'],
            'closed' => ['CLOSED'],
            default => ['OPEN'],
        };
        $placeholderNames = [];
        foreach (array_keys($statuses) as $index) {
            $placeholderNames[] = ':status' . $index;
        }

        $stmt = $this->pdo->prepare(
            'SELECT wo.id, wo.ot_code, wo.sku_final, wo.target_qty, wo.status, wo.created_at,
                    sync.erp_prod_header_id, sync.erp_agenda_id, sync.erp_prod_number, sync.erp_req_id,
                    sync.erp_plan_desc, sync.erp_plan_date, sync.erp_plan_timestamp,
                    sync.erp_machine_id, sync.erp_machine_label, sync.erp_machine_type_id,
                    sync.erp_worker_name, sync.erp_target_qty, sync.erp_required_meters,
                    sync.erp_required_meters_source, sync.erp_header_status, sync.erp_agenda_status
             FROM work_orders wo
             LEFT JOIN erp_work_order_sync sync ON sync.work_order_id = wo.id
             WHERE wo.status IN (' . implode(',', $placeholderNames) . ')
             ORDER BY CASE
                 WHEN wo.status = "ACTIVE" THEN 0
                 WHEN wo.status = "CUTTING" THEN 1
                 ELSE 2
             END,
             COALESCE(sync.erp_plan_timestamp, UNIX_TIMESTAMP(wo.created_at)) DESC,
             wo.id DESC
             LIMIT :limit'
        );
        foreach ($statuses as $index => $status) {
            $stmt->bindValue(':status' . $index, $status);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $workOrderId = (int)$row['id'];
            $activeShiftSession = $this->getActiveShiftSessionByWorkOrder($workOrderId);
            $row['operator_name'] = '';
            $row['operator_label'] = '-';
            $row['current_roll_code'] = '-';
            $row['current_chemical_label'] = '-';
            $row['finished_at'] = '';
            $row['box_qty'] = '';
            $row['erp_plan_label'] = trim((string)($row['erp_plan_date'] ?? ''));
            $row['erp_machine_label'] = trim((string)($row['erp_machine_label'] ?? ''));
            $row['erp_reference_label'] = trim((string)($row['erp_req_id'] ?? ''));
            $row['active_machine_label'] = trim((string)($activeShiftSession['machine_name'] ?? ''));
            $row['active_machine_type_label'] = trim((string)($activeShiftSession['machine_type_name'] ?? ''));
            $row['active_shift_label'] = trim((string)($activeShiftSession['shift_label'] ?? ''));
            $row['status_label'] = match ((string)$row['status']) {
                'ACTIVE' => 'Producción',
                'CUTTING' => 'Corte',
                'CLOSED' => 'Fabricada',
                default => 'Pendiente',
            };

            if ($row['active_machine_label'] !== '') {
                $row['erp_machine_label'] = $row['active_machine_type_label'] !== ''
                    ? $row['active_machine_type_label'] . ' - ' . $row['active_machine_label']
                    : $row['active_machine_label'];
            }

            if ((string)$row['status'] === 'ACTIVE') {
                $lastStart = $this->getLastWorkOrderStart($workOrderId);
                $currentRoll = $this->getCurrentRollInWorkOrder($workOrderId);
                $chemicalInputs = $this->listChemicalInputsByWorkOrder($workOrderId, 1);

                $row['operator_name'] = (string)($lastStart['operator_name'] ?? '');
                $row['operator_label'] = $row['operator_name'] !== '' ? $row['operator_name'] : '-';
                $row['current_roll_code'] = $currentRoll !== null
                    ? ((string)$currentRoll['roll_code'] . ' (' . (string)$currentRoll['weight_kg'] . ' Kg)')
                    : '-';

                if ($chemicalInputs !== []) {
                    $latestChemical = $chemicalInputs[0];
                    $row['current_chemical_label'] = (string)$latestChemical['chemical_code']
                        . ' (' . (string)$latestChemical['weight_kg'] . ' Kg)';
                }
                if (trim((string)($activeShiftSession['operator_name'] ?? '')) !== '') {
                    $row['operator_name'] = trim((string)$activeShiftSession['operator_name']);
                    $row['operator_label'] = $row['operator_name'];
                }
            } elseif ((string)$row['status'] === 'OPEN') {
                $row['operator_name'] = trim((string)($row['erp_worker_name'] ?? ''));
                $row['operator_label'] = $row['operator_name'] !== '' ? $row['operator_name'] : '-';
            } elseif ((string)$row['status'] === 'CUTTING') {
                $lastFinish = $this->getLastWorkOrderFinish($workOrderId);
                $row['operator_name'] = (string)($lastFinish['operator_name'] ?? '');
                $row['operator_label'] = $row['operator_name'] !== '' ? $row['operator_name'] : '-';
                $row['finished_at'] = (string)($lastFinish['created_at'] ?? '');
                $row['box_qty'] = (string)($lastFinish['box_qty'] ?? '');
                $row['current_chemical_label'] = 'Pendiente de corte';
                $outputRollId = (int)($lastFinish['output_roll_id'] ?? 0);
                if ($outputRollId > 0) {
                    $outputRoll = $this->getRoll($outputRollId);
                    if ($outputRoll !== null) {
                        $row['current_roll_code'] = (string)$outputRoll['roll_code'] . ' (' . (string)$outputRoll['weight_kg'] . ' Kg)';
                    }
                }

                $finishApproval = $this->getLastWorkOrderFinishApproval($workOrderId);
                $sealingSetupApproval = $this->getLastWorkOrderSealingSetupApproval($workOrderId);
                $sealingFinish = $this->getLastWorkOrderSealingFinish($workOrderId);
                $packagingSetupApproval = $this->getLastWorkOrderPackagingSetupApproval($workOrderId);
                $packagingFinish = $this->getLastWorkOrderPackagingFinish($workOrderId);
                $openSealingProduction = $this->getOpenWorkOrderSealingProductionEvent($workOrderId);
                $openPackagingProduction = $this->getOpenWorkOrderPackagingProductionEvent($workOrderId);

                $finishApprovalTs = strtotime((string)($finishApproval['created_at'] ?? ''));
                $sealingSetupTs = strtotime((string)($sealingSetupApproval['created_at'] ?? ''));
                $sealingFinishTs = strtotime((string)($sealingFinish['created_at'] ?? ''));
                $packagingSetupTs = strtotime((string)($packagingSetupApproval['created_at'] ?? ''));
                $packagingFinishTs = strtotime((string)($packagingFinish['created_at'] ?? ''));
                $openSealingStartedTs = strtotime((string)($openSealingProduction['started_at'] ?? ''));
                $openPackagingStartedTs = strtotime((string)($openPackagingProduction['started_at'] ?? ''));

                $flexoApproved = $finishApproval !== null;
                $sealingSetupValid = $flexoApproved
                    && $sealingSetupApproval !== null
                    && ($finishApprovalTs === false || $sealingSetupTs === false || $sealingSetupTs >= $finishApprovalTs);

                $sealingFinished = false;
                if ($flexoApproved && $sealingFinish !== null) {
                    if ($sealingSetupValid) {
                        $sealingFinished = ($sealingFinishTs === false || $sealingSetupTs === false)
                            ? true
                            : ($sealingFinishTs >= $sealingSetupTs);
                    } elseif ($finishApprovalTs === false || $sealingFinishTs === false) {
                        $sealingFinished = true;
                    } else {
                        $sealingFinished = $sealingFinishTs >= $finishApprovalTs;
                    }
                }

                $sealingStarted = $flexoApproved && (
                    $sealingSetupValid
                    || $sealingFinished
                    || ($openSealingProduction !== null && ($finishApprovalTs === false || $openSealingStartedTs === false || $openSealingStartedTs >= $finishApprovalTs))
                );

                $packagingSetupValid = $sealingFinished
                    && $packagingSetupApproval !== null
                    && ($sealingFinishTs === false || $packagingSetupTs === false || $packagingSetupTs >= $sealingFinishTs);
                $packagingFinished = $sealingFinished
                    && $packagingFinish !== null
                    && (
                        $packagingSetupValid
                            ? ($packagingFinishTs === false || $packagingSetupTs === false || $packagingFinishTs >= $packagingSetupTs)
                            : ($sealingFinishTs === false || $packagingFinishTs === false || $packagingFinishTs >= $sealingFinishTs)
                    );
                $packagingStarted = $sealingFinished && (
                    $packagingSetupValid
                    || $packagingFinished
                    || ($openPackagingProduction !== null && ($sealingFinishTs === false || $openPackagingStartedTs === false || $openPackagingStartedTs >= $sealingFinishTs))
                );

                if ($packagingStarted) {
                    $row['status_label'] = 'Embalaje';
                    $row['current_chemical_label'] = $packagingFinished ? 'Embalaje terminado' : 'En proceso de Embalaje';
                } elseif ($sealingFinished) {
                    $row['status_label'] = 'Embalaje';
                    $row['current_chemical_label'] = 'Lista para Embalaje';
                } elseif ($sealingStarted || $finishApproval !== null) {
                    $row['status_label'] = 'Selladora';
                    $row['current_chemical_label'] = $sealingSetupValid ? 'En proceso de Selladora' : 'Lista para Selladora';
                }
            } elseif ((string)$row['status'] === 'CLOSED') {
                $lastCut = $this->getLastCutCompletion($workOrderId);
                $lastFinish = $this->getLastWorkOrderFinish($workOrderId);
                $row['operator_name'] = (string)($lastCut['operator_name'] ?? $lastFinish['operator_name'] ?? '');
                $row['operator_label'] = $row['operator_name'] !== '' ? $row['operator_name'] : '-';
                $row['finished_at'] = (string)($lastCut['created_at'] ?? $lastFinish['created_at'] ?? '');
                $row['box_qty'] = (string)($lastCut['box_qty'] ?? $lastFinish['box_qty'] ?? '');
            }
        }
        unset($row);

        return $rows;
    }

    public function getWorkOrder(int $id): ?array
    {
        $this->syncWorkOrdersFromErpProductionPlan();
        $stmt = $this->pdo->prepare(
            'SELECT wo.id, wo.ot_code, wo.sku_final, wo.target_qty, wo.status, wo.created_at,
                    sync.erp_prod_header_id, sync.erp_agenda_id, sync.erp_worker_ot_id,
                    sync.erp_worker_init_id, sync.erp_worker_id, sync.erp_worker_name,
                    sync.erp_user_id, sync.erp_user_login, sync.erp_prod_number, sync.erp_req_id,
                    sync.erp_plan_desc, sync.erp_plan_date, sync.erp_plan_timestamp,
                    sync.erp_machine_id, sync.erp_machine_label, sync.erp_machine_type_id,
                    sync.erp_planta_id, sync.erp_target_qty, sync.erp_required_meters,
                    sync.erp_required_meters_source, sync.erp_header_status,
                    sync.erp_agenda_status, sync.erp_agenda_active, sync.erp_worker_status
             FROM work_orders wo
             LEFT JOIN erp_work_order_sync sync ON sync.work_order_id = wo.id
             WHERE wo.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row !== false) {
            $activeShiftSession = $this->getActiveShiftSessionByWorkOrder((int)$row['id']);
            if ($activeShiftSession !== null) {
                $row['shift_session_id'] = (int)$activeShiftSession['id'];
                $row['shift_machine_name'] = (string)($activeShiftSession['machine_name'] ?? '');
                $row['shift_machine_code'] = (string)($activeShiftSession['machine_code'] ?? '');
                $row['shift_machine_type_name'] = (string)($activeShiftSession['machine_type_name'] ?? '');
                $row['shift_operator_name'] = (string)($activeShiftSession['operator_name'] ?? '');
                $row['shift_helper_name'] = (string)($activeShiftSession['helper_name'] ?? '');
                $row['shift_label'] = (string)($activeShiftSession['shift_label'] ?? '');
                $row['shift_process_stage'] = (string)($activeShiftSession['process_stage'] ?? '');
                $row['shift_comments'] = (string)($activeShiftSession['comments'] ?? '');
                if (trim((string)($row['shift_machine_name'] ?? '')) !== '') {
                    $row['erp_machine_label'] = trim((string)($row['shift_machine_type_name'] ?? '')) !== ''
                        ? trim((string)$row['shift_machine_type_name']) . ' - ' . trim((string)$row['shift_machine_name'])
                        : trim((string)$row['shift_machine_name']);
                }
            }
        }
        return $row === false ? null : $row;
    }

    public function listWorkOrdersForClicheAssignment(): array
    {
        $this->syncWorkOrdersFromErpProductionPlan();
        $stmt = $this->pdo->prepare(
            "SELECT id, ot_code, sku_final, status, created_at
             FROM work_orders
             WHERE status IN ('OPEN', 'ACTIVE', 'CUTTING')
             ORDER BY CASE
                 WHEN status = 'ACTIVE' THEN 0
                 WHEN status = 'CUTTING' THEN 1
                 ELSE 2
             END, id DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listCliches(?string $search = null, ?string $location = null, ?string $status = null): array
    {
        $params = [];
        $where = [];
        $search = trim((string)$search);
        $location = trim((string)$location);
        $status = strtoupper(trim((string)$status));

        if ($search !== '') {
            $where[] = '(c.code LIKE :search OR c.description LIKE :search OR wo.ot_code LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        if ($location !== '') {
            $where[] = '(c.location_code LIKE :location OR c.location_detail LIKE :location)';
            $params[':location'] = '%' . $location . '%';
        }
        if ($status !== '' && $status !== 'ALL') {
            $where[] = 'c.status = :status';
            $params[':status'] = $status;
        }

        $sql = 'SELECT c.*,
                       wo.ot_code, wo.sku_final
                FROM cliches c
                LEFT JOIN work_orders wo ON wo.id = c.current_work_order_id';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY CASE
                    WHEN c.status = "IN_USE" THEN 0
                    WHEN c.status = "AVAILABLE" THEN 1
                    WHEN c.status = "MAINTENANCE" THEN 2
                    ELSE 3
                  END, c.code ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getCliche(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, wo.ot_code, wo.sku_final
             FROM cliches c
             LEFT JOIN work_orders wo ON wo.id = c.current_work_order_id
             WHERE c.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createCliche(
        string $code,
        string $description,
        string $locationCode,
        string $locationDetail,
        string $notes,
        string $operatorName
    ): array {
        $code = strtoupper(trim($code));
        $description = trim($description);
        $locationCode = strtoupper(trim($locationCode));
        $locationDetail = trim($locationDetail);
        $notes = trim($notes);
        $operatorName = trim($operatorName);
        $errors = [];

        if ($code === '') {
            $errors['code'] = 'Código de clisé es obligatorio.';
        }
        if ($description === '') {
            $errors['description'] = 'Descripción del clisé es obligatoria.';
        }
        if ($locationCode === '') {
            $errors['location_code'] = 'Ubicación física es obligatoria.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO cliches (
                code, description, location_code, location_detail, status, notes
             ) VALUES (
                :code, :description, :location_code, :location_detail, :status, :notes
             )'
        );
        try {
            $stmt->execute([
                ':code' => $code,
                ':description' => $description,
                ':location_code' => $locationCode,
                ':location_detail' => $locationDetail !== '' ? $locationDetail : null,
                ':status' => 'AVAILABLE',
                ':notes' => $notes !== '' ? $notes : null,
            ]);
            $clicheId = (int)$this->pdo->lastInsertId();

            $log = $this->pdo->prepare(
                'INSERT INTO cliche_usage_logs (
                    cliche_id, work_order_id, action_type, from_location_code, to_location_code, operator_name, notes
                 ) VALUES (
                    :cliche_id, NULL, :action_type, NULL, :to_location_code, :operator_name, :notes
                 )'
            );
            $log->execute([
                ':cliche_id' => $clicheId,
                ':action_type' => 'CREATED',
                ':to_location_code' => $locationCode,
                ':operator_name' => $operatorName,
                ':notes' => $notes !== '' ? $notes : null,
            ]);

            $this->insertEvent('CLICHE_CREATED', [
                'cliche_id' => $clicheId,
                'cliche_code' => $code,
                'location_code' => $locationCode,
                'operator_name' => $operatorName,
            ]);

            return ['ok' => true, 'errors' => [], 'cliche_id' => $clicheId];
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'uq_cliches_code')) {
                return ['ok' => false, 'errors' => ['code' => 'Ese código de clisé ya existe.']];
            }
            throw $e;
        }
    }

    public function assignClicheToWorkOrder(int $clicheId, int $workOrderId, string $notes, string $operatorName): array
    {
        $notes = trim($notes);
        $operatorName = trim($operatorName);
        $cliche = $this->getCliche($clicheId);
        $workOrder = $this->getWorkOrder($workOrderId);
        $errors = [];

        if ($cliche === null) {
            $errors['cliche_id'] = 'El clisé no existe.';
        }
        if ($workOrder === null) {
            $errors['work_order_id'] = 'La OT no existe.';
        } elseif (!in_array((string)($workOrder['status'] ?? ''), ['OPEN', 'ACTIVE', 'CUTTING'], true)) {
            $errors['work_order_id'] = 'La OT no está disponible para asignar clisés.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($cliche !== null) {
            $clicheStatus = strtoupper(trim((string)($cliche['status'] ?? '')));
            $currentWorkOrderId = (int)($cliche['current_work_order_id'] ?? 0);
            if ($clicheStatus === 'IN_USE' && $currentWorkOrderId > 0 && $currentWorkOrderId !== $workOrderId) {
                $errors['cliche_id'] = 'El clisé ya está en uso en la OT ' . ((string)($cliche['ot_code'] ?? '') !== '' ? (string)$cliche['ot_code'] : ('#' . $currentWorkOrderId)) . '.';
            } elseif ($clicheStatus === 'IN_USE' && $currentWorkOrderId === $workOrderId) {
                $errors['cliche_id'] = 'El clisé ya está asignado a esta OT.';
            } elseif (!in_array($clicheStatus, ['AVAILABLE'], true)) {
                $errors['cliche_id'] = 'Solo se pueden asignar clisés disponibles.';
            }
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $fromLocationCode = (string)($cliche['location_code'] ?? '');
        $toLocationCode = 'OT ' . (string)$workOrder['ot_code'];
        $stmt = $this->pdo->prepare(
            'UPDATE cliches
             SET status = :status,
                 current_work_order_id = :current_work_order_id,
                 current_operator_name = :current_operator_name,
                 current_assigned_at = CURRENT_TIMESTAMP,
                 location_code = :location_code,
                 location_detail = :location_detail
             WHERE id = :id'
        );
        $stmt->execute([
            ':status' => 'IN_USE',
            ':current_work_order_id' => $workOrderId,
            ':current_operator_name' => $operatorName,
            ':location_code' => 'EN_USO',
            ':location_detail' => $toLocationCode,
            ':id' => $clicheId,
        ]);

        $log = $this->pdo->prepare(
            'INSERT INTO cliche_usage_logs (
                cliche_id, work_order_id, action_type, from_location_code, to_location_code, operator_name, notes
             ) VALUES (
                :cliche_id, :work_order_id, :action_type, :from_location_code, :to_location_code, :operator_name, :notes
             )'
        );
        $log->execute([
            ':cliche_id' => $clicheId,
            ':work_order_id' => $workOrderId,
            ':action_type' => 'ASSIGNED',
            ':from_location_code' => $fromLocationCode !== '' ? $fromLocationCode : null,
            ':to_location_code' => $toLocationCode,
            ':operator_name' => $operatorName,
            ':notes' => $notes !== '' ? $notes : null,
        ]);

        $this->insertEvent('CLICHE_ASSIGNED', [
            'cliche_id' => $clicheId,
            'cliche_code' => (string)$cliche['code'],
            'work_order_id' => $workOrderId,
            'ot_code' => (string)$workOrder['ot_code'],
            'operator_name' => $operatorName,
            'notes' => $notes,
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function returnCliche(int $clicheId, string $locationCode, string $locationDetail, string $notes, string $operatorName): array
    {
        $locationCode = strtoupper(trim($locationCode));
        $locationDetail = trim($locationDetail);
        $notes = trim($notes);
        $operatorName = trim($operatorName);
        $cliche = $this->getCliche($clicheId);
        $errors = [];

        if ($cliche === null) {
            $errors['cliche_id'] = 'El clisé no existe.';
        }
        if ($locationCode === '') {
            $errors['location_code'] = 'Debes indicar la ubicación de retorno.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($cliche !== null && strtoupper(trim((string)($cliche['status'] ?? ''))) !== 'IN_USE') {
            $errors['cliche_id'] = 'Solo se pueden devolver clisés que estén en uso.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $workOrderId = (int)($cliche['current_work_order_id'] ?? 0);
        $fromLocationCode = trim((string)($cliche['location_detail'] ?? $cliche['location_code'] ?? ''));
        $stmt = $this->pdo->prepare(
            'UPDATE cliches
             SET status = :status,
                 current_work_order_id = NULL,
                 current_operator_name = NULL,
                 current_assigned_at = NULL,
                 location_code = :location_code,
                 location_detail = :location_detail
             WHERE id = :id'
        );
        $stmt->execute([
            ':status' => 'AVAILABLE',
            ':location_code' => $locationCode,
            ':location_detail' => $locationDetail !== '' ? $locationDetail : null,
            ':id' => $clicheId,
        ]);

        $log = $this->pdo->prepare(
            'INSERT INTO cliche_usage_logs (
                cliche_id, work_order_id, action_type, from_location_code, to_location_code, operator_name, notes
             ) VALUES (
                :cliche_id, :work_order_id, :action_type, :from_location_code, :to_location_code, :operator_name, :notes
             )'
        );
        $log->execute([
            ':cliche_id' => $clicheId,
            ':work_order_id' => $workOrderId > 0 ? $workOrderId : null,
            ':action_type' => 'RETURNED',
            ':from_location_code' => $fromLocationCode !== '' ? $fromLocationCode : null,
            ':to_location_code' => $locationCode,
            ':operator_name' => $operatorName,
            ':notes' => $notes !== '' ? $notes : null,
        ]);

        $this->insertEvent('CLICHE_RETURNED', [
            'cliche_id' => $clicheId,
            'cliche_code' => (string)$cliche['code'],
            'work_order_id' => $workOrderId > 0 ? $workOrderId : null,
            'operator_name' => $operatorName,
            'location_code' => $locationCode,
            'notes' => $notes,
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function listClicheUsageLogsByCliche(int $clicheId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT log.*, wo.ot_code
             FROM cliche_usage_logs log
             LEFT JOIN work_orders wo ON wo.id = log.work_order_id
             WHERE log.cliche_id = :cliche_id
             ORDER BY log.id DESC'
        );
        $stmt->execute([':cliche_id' => $clicheId]);
        return $stmt->fetchAll();
    }

    public function listClicheUsageLogsByWorkOrder(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT log.*, c.code AS cliche_code, c.description AS cliche_description
             FROM cliche_usage_logs log
             INNER JOIN cliches c ON c.id = log.cliche_id
             WHERE log.work_order_id = :work_order_id
             ORDER BY log.id DESC'
        );
        $stmt->execute([':work_order_id' => $workOrderId]);
        return $stmt->fetchAll();
    }

    public function listClichesAssignedToWorkOrder(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM cliches
             WHERE current_work_order_id = :work_order_id
               AND status = "IN_USE"
             ORDER BY code ASC'
        );
        $stmt->execute([':work_order_id' => $workOrderId]);
        return $stmt->fetchAll();
    }

    public function createWorkOrder(string $otCode, string $skuFinal, ?int $targetQty): array
    {
        $otCode = trim($otCode);
        $skuFinal = trim($skuFinal);

        $errors = [];
        if ($otCode === '') {
            $errors['ot_code'] = 'Código de OT es obligatorio.';
        }
        if ($skuFinal === '') {
            $errors['sku_final'] = 'SKU final es obligatorio.';
        }
        if ($targetQty !== null && $targetQty <= 0) {
            $errors['target_qty'] = 'Cantidad objetivo debe ser mayor a 0.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO work_orders (ot_code, sku_final, target_qty, status)
             VALUES (:ot_code, :sku_final, :target_qty, :status)'
        );
        try {
            $stmt->execute([
                ':ot_code' => $otCode,
                ':sku_final' => $skuFinal,
                ':target_qty' => $targetQty,
                ':status' => 'OPEN',
            ]);
            $this->insertEvent('WORK_ORDER_CREATED', ['ot_code' => $otCode]);
            return ['ok' => true, 'errors' => []];
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'uq_work_orders_ot_code')) {
                return ['ok' => false, 'errors' => ['ot_code' => 'Esta OT ya existe.']];
            }
            throw $e;
        }
    }

    public function getActiveWorkOrder(): ?array
    {
        $this->syncWorkOrdersFromErpProductionPlan();
        $id = (int)$this->getAppSetting('active_work_order_id', '0');
        if ($id === null || $id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT wo.id, wo.ot_code, wo.sku_final, wo.target_qty, wo.status, wo.created_at,
                    sync.erp_plan_date, sync.erp_machine_label, sync.erp_worker_name
             FROM work_orders wo
             LEFT JOIN erp_work_order_sync sync ON sync.work_order_id = wo.id
             WHERE wo.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $wo = $stmt->fetch();
        return $wo === false ? null : $wo;
    }

    public function listWorkOrdersForTransfer(): array
    {
        $this->syncWorkOrdersFromErpProductionPlan();
        $stmt = $this->pdo->prepare(
            "SELECT id, ot_code, sku_final, target_qty, status, created_at
             FROM work_orders
             WHERE status IN ('ACTIVE', 'OPEN')
             ORDER BY CASE WHEN status = 'ACTIVE' THEN 0 ELSE 1 END, id DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function setActiveWorkOrder(int $id, string $operatorName = ''): void
    {
        $operatorName = trim($operatorName);
        $this->pdo->beginTransaction();
        try {
            $this->setAppSetting('active_work_order_id', (string)$id);

            $stmt = $this->pdo->prepare("UPDATE work_orders SET status = CASE WHEN id = :id THEN 'ACTIVE' WHEN status = 'ACTIVE' THEN 'OPEN' ELSE status END");
            $stmt->execute([':id' => $id]);

            $this->insertEvent('WORK_ORDER_ACTIVATED', [
                'work_order_id' => $id,
                'operator_name' => $operatorName,
            ]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function getAppSetting(string $key, ?string $default = null): ?string
    {
        try {
            $stmt = $this->pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :key LIMIT 1');
            $stmt->execute([':key' => $key]);
            $row = $stmt->fetch();
        } catch (Throwable) {
            return $default;
        }
        if ($row === false) {
            return $default;
        }

        $value = $row['setting_value'] ?? null;
        return $value === null ? $default : (string)$value;
    }

    private function setAppSetting(string $key, string $value): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO app_settings (setting_key, setting_value) VALUES (:key, :value)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            $stmt->execute([
                ':key' => $key,
                ':value' => $value,
            ]);
        } catch (Throwable) {
            return;
        }
    }

    public function getRollRequestLinearPlanningConfig(): array
    {
        $bufferPercent = $this->tableExists('app_settings')
            ? (float)($this->getAppSetting('roll_request_meter_buffer_percent', '5') ?? '5')
            : 5.0;
        if ($bufferPercent < 0) {
            $bufferPercent = 0;
        }

        return [
            'buffer_percent' => round($bufferPercent, 3),
        ];
    }

    public function getRollRequestPlanningForWorkOrder(int $workOrderId): array
    {
        $workOrder = $this->getWorkOrder($workOrderId);
        $config = $this->getRollRequestLinearPlanningConfig();
        if ($workOrder === null) {
            return [
                'work_order_id' => $workOrderId,
                'required_meters' => 0.0,
                'suggested_roll_qty' => 0,
                'suggested_group_key' => '',
                'suggested_group_label' => '',
                'hint' => [],
                'config' => $config,
            ];
        }

        $hint = $this->parseWorkOrderRollHint($workOrder);
        $bestGroup = null;
        $bestScore = null;
        foreach ($this->listAvailableRollsForMaterialRequest() as $group) {
            $score = $this->scoreMaterialGroupForWorkOrder($group, $hint);
            if ($bestGroup === null || $score > $bestScore) {
                $bestGroup = $group;
                $bestScore = $score;
            }
        }

        $requiredMeters = round((float)($hint['required_meters'] ?? 0), 3);
        $suggestedRollQty = 0;
        if (is_array($bestGroup)) {
            $suggestedRollQty = $this->estimateRollQuantityByMeters($requiredMeters, (float)($bestGroup['meters'] ?? 0), $config);
        }

        return [
            'work_order_id' => $workOrderId,
            'required_meters' => $requiredMeters,
            'suggested_roll_qty' => $suggestedRollQty,
            'suggested_group_key' => is_array($bestGroup) ? (string)($bestGroup['group_key'] ?? '') : '',
            'suggested_group_label' => is_array($bestGroup) ? $this->materialGroupLabel($bestGroup) : '',
            'suggested_group' => $bestGroup,
            'hint' => $hint,
            'config' => $config,
        ];
    }

    public function listChemicals(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, code, name FROM chemicals WHERE is_active = 1 ORDER BY code ASC');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listAniloxCatalog(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, code, name, bcm, lpi,
                    CONCAT(code, " - ", name, CASE
                        WHEN COALESCE(lpi, "") <> "" THEN CONCAT(" / ", lpi)
                        ELSE ""
                    END) AS display_label
             FROM production_anilox_catalog
             WHERE is_active = 1
             ORDER BY sort_order ASC, code ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getWorkOrderAniloxAssignments(int $workOrderId): array
    {
        if ($workOrderId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT wa.id, wa.work_order_id, wa.unit_no, wa.color_name, wa.anilox_id, wa.updated_by, wa.updated_at,
                    ac.code AS anilox_code, ac.name AS anilox_name,
                    CONCAT(ac.code, " - ", ac.name, CASE
                        WHEN COALESCE(ac.lpi, "") <> "" THEN CONCAT(" / ", ac.lpi)
                        ELSE ""
                    END) AS anilox_label
             FROM work_order_anilox_assignments wa
             LEFT JOIN production_anilox_catalog ac ON ac.id = wa.anilox_id
             WHERE wa.work_order_id = :work_order_id
             ORDER BY wa.unit_no ASC'
        );
        $stmt->execute([':work_order_id' => $workOrderId]);
        return $stmt->fetchAll();
    }

    /**
     * @param array<int,array{unit_no:int,color_name:string,anilox_id:int|null}> $slots
     */
    public function saveWorkOrderAniloxAssignments(int $workOrderId, array $slots, string $operatorName): array
    {
        $errors = [];
        $operatorName = trim($operatorName);

        if ($workOrderId <= 0 || $this->getWorkOrder($workOrderId) === null) {
            $errors['work_order_id'] = 'La OT no existe.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'El operador es obligatorio.';
        }
        if ($slots === []) {
            $errors['slots'] = 'Debes enviar al menos una unidad.';
        }

        $normalizedSlots = [];
        $seenUnits = [];
        foreach ($slots as $index => $slot) {
            $unitNo = isset($slot['unit_no']) ? (int)$slot['unit_no'] : ($index + 1);
            $colorName = trim((string)($slot['color_name'] ?? ''));
            $aniloxId = isset($slot['anilox_id']) && (int)$slot['anilox_id'] > 0 ? (int)$slot['anilox_id'] : null;

            if ($unitNo < 1 || $unitNo > 6) {
                $errors['unit_' . $index] = 'Las unidades de anilox deben estar entre 1 y 6.';
                continue;
            }
            if (isset($seenUnits[$unitNo])) {
                $errors['unit_dup_' . $unitNo] = 'La unidad ' . $unitNo . ' está repetida.';
                continue;
            }
            $seenUnits[$unitNo] = true;

            if (mb_strlen($colorName) > 120) {
                $errors['color_' . $unitNo] = 'El color de la unidad ' . $unitNo . ' supera el largo permitido.';
            }
            if ($aniloxId !== null) {
                $stmt = $this->pdo->prepare('SELECT id FROM production_anilox_catalog WHERE id = :id AND is_active = 1');
                $stmt->execute([':id' => $aniloxId]);
                if ($stmt->fetch() === false) {
                    $errors['anilox_' . $unitNo] = 'El anilox seleccionado en la unidad ' . $unitNo . ' no existe.';
                }
            }

            $normalizedSlots[] = [
                'unit_no' => $unitNo,
                'color_name' => $colorName,
                'anilox_id' => $aniloxId,
            ];
        }

        if (count($normalizedSlots) !== 6) {
            $errors['slots_count'] = 'La configuración debe tener exactamente 6 unidades.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        usort(
            $normalizedSlots,
            static fn(array $left, array $right): int => (int)$left['unit_no'] <=> (int)$right['unit_no']
        );

        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare('DELETE FROM work_order_anilox_assignments WHERE work_order_id = :work_order_id');
            $delete->execute([':work_order_id' => $workOrderId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO work_order_anilox_assignments (
                    work_order_id, unit_no, color_name, anilox_id, updated_by
                 ) VALUES (
                    :work_order_id, :unit_no, :color_name, :anilox_id, :updated_by
                 )'
            );

            foreach ($normalizedSlots as $slot) {
                $insert->execute([
                    ':work_order_id' => $workOrderId,
                    ':unit_no' => (int)$slot['unit_no'],
                    ':color_name' => (string)$slot['color_name'],
                    ':anilox_id' => $slot['anilox_id'],
                    ':updated_by' => $operatorName,
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->insertEvent('WORK_ORDER_ANILOX_UPDATED', [
            'work_order_id' => $workOrderId,
            'operator_name' => $operatorName,
            'slots' => array_map(
                static fn(array $slot): array => [
                    'unit_no' => (int)$slot['unit_no'],
                    'color_name' => (string)$slot['color_name'],
                    'anilox_id' => $slot['anilox_id'],
                ],
                $normalizedSlots
            ),
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function listRecentChemicalWeighings(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cw.id, cw.initial_weight_kg, cw.return_weight_kg, cw.net_consumption_kg, cw.created_at,
                    wo.ot_code, wo.sku_final,
                    c.code AS chemical_code, c.name AS chemical_name
             FROM chemical_weighings cw
             JOIN work_orders wo ON wo.id = cw.work_order_id
             JOIN chemicals c ON c.id = cw.chemical_id
             ORDER BY cw.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createChemicalWeighing(array $input): array
    {
        $errors = [];

        $workOrderId = isset($input['work_order_id']) ? (int)$input['work_order_id'] : 0;
        if ($workOrderId <= 0) {
            $errors['work_order_id'] = 'OT es obligatoria.';
        }

        $chemicalId = isset($input['chemical_id']) ? (int)$input['chemical_id'] : 0;
        if ($chemicalId <= 0) {
            $errors['chemical_id'] = 'Químico es obligatorio.';
        }

        $initial = isset($input['initial_weight_kg']) ? (float)$input['initial_weight_kg'] : 0.0;
        $return = isset($input['return_weight_kg']) ? (float)$input['return_weight_kg'] : 0.0;
        if ($initial <= 0) {
            $errors['initial_weight_kg'] = 'Peso inicial debe ser mayor a 0.';
        }
        if ($return <= 0) {
            $errors['return_weight_kg'] = 'Peso retorno debe ser mayor a 0.';
        }
        if ($initial > 0 && $return > 0 && $return > $initial) {
            $errors['return_weight_kg'] = 'Peso retorno no puede ser mayor al peso inicial.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $net = round($initial - $return, 3);
        if ($net < 0) {
            return ['ok' => false, 'errors' => ['net' => 'Consumo neto inválido.']];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO chemical_weighings (work_order_id, chemical_id, initial_weight_kg, return_weight_kg, net_consumption_kg)
             VALUES (:wo, :chem, :initial, :return, :net)'
        );
        $stmt->execute([
            ':wo' => $workOrderId,
            ':chem' => $chemicalId,
            ':initial' => $initial,
            ':return' => $return,
            ':net' => $net,
        ]);

        $this->insertEvent('CHEMICAL_WEIGHING_CREATED', [
            'work_order_id' => $workOrderId,
            'chemical_id' => $chemicalId,
            'net_consumption_kg' => $net,
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function createChemicalInput(int $workOrderId, int $chemicalId, float $weightKg, string $operatorName): array
    {
        $errors = [];
        $operatorName = trim($operatorName);

        if ($workOrderId <= 0 || $this->getWorkOrder($workOrderId) === null) {
            $errors['work_order_id'] = 'OT no existe.';
        }
        if ($chemicalId <= 0) {
            $errors['chemical_id'] = 'Químico es obligatorio.';
        } else {
            $stmt = $this->pdo->prepare('SELECT id FROM chemicals WHERE id = :id AND is_active = 1');
            $stmt->execute([':id' => $chemicalId]);
            if ($stmt->fetch() === false) {
                $errors['chemical_id'] = 'Químico no existe o está inactivo.';
            }
        }
        if ($weightKg <= 0) {
            $errors['weight_kg'] = 'Peso de entrada debe ser mayor a 0.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->insertEvent('CHEMICAL_INPUT_RECORDED', [
            'work_order_id' => $workOrderId,
            'chemical_id' => $chemicalId,
            'weight_kg' => round($weightKg, 3),
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function listChemicalInputsByWorkOrder(int $workOrderId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.id, e.created_at,
                    JSON_UNQUOTE(JSON_EXTRACT(e.payload, "$.weight_kg")) AS weight_kg,
                    JSON_UNQUOTE(JSON_EXTRACT(e.payload, "$.operator_name")) AS operator_name,
                    c.code AS chemical_code,
                    c.name AS chemical_name
             FROM events e
             JOIN chemicals c ON c.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(e.payload, "$.chemical_id")) AS UNSIGNED)
             WHERE e.type = "CHEMICAL_INPUT_RECORDED"
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(e.payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY e.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':wo', $workOrderId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listRollsByWorkOrder(int $workOrderId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT r.id, r.roll_code, r.weight_kg, r.status, r.created_at,
                    w.code AS warehouse_code,
                    s.code AS sku_code, s.description AS sku_description
             FROM events e
             JOIN rolls r ON r.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(e.payload, "$.roll_id")) AS UNSIGNED)
             JOIN warehouses w ON w.id = r.warehouse_id
             JOIN skus s ON s.id = r.sku_id
             WHERE e.type IN ("WORK_ORDER_ROLL_ATTACHED","WORK_ORDER_ROLL_RELEASED","WORK_ORDER_FINISHED")
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(e.payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY r.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':wo', $workOrderId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getCurrentRollInWorkOrder(int $workOrderId): ?array
    {
        $events = $this->listWorkOrderRollEvents($workOrderId);
        $currentRollId = null;
        foreach ($events as $event) {
            $rollId = (int)($event['roll_id'] ?? 0);
            if ($rollId <= 0) {
                continue;
            }
            if ((string)$event['type'] === 'WORK_ORDER_ROLL_ATTACHED') {
                $currentRollId = $rollId;
            } elseif ((string)$event['type'] === 'WORK_ORDER_ROLL_RELEASED' && $currentRollId === $rollId) {
                $currentRollId = null;
            }
        }

        return $currentRollId !== null ? $this->getRoll($currentRollId) : null;
    }

    public function listActiveRollsInWorkOrder(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, w.code AS warehouse_code, w.name AS warehouse_name,
                    s.code AS sku_code, s.description AS sku_description
             FROM rolls r
             JOIN warehouses w ON w.id = r.warehouse_id
             JOIN skus s ON s.id = r.sku_id
             WHERE r.current_work_order_id = :wo
               AND r.status = "IN_PROCESS"
             ORDER BY r.id DESC'
        );
        $stmt->execute([':wo' => $workOrderId]);
        return $stmt->fetchAll();
    }

    public function listWorkOrderRollHistory(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.id, e.type, e.created_at, e.payload,
                    r.id AS roll_id, r.roll_code, r.weight_kg, r.meters, w.code AS warehouse_code,
                    s.code AS sku_code, s.description AS sku_description,
                    r.purchase_order_id, r.supplier_id
             FROM events e
             JOIN rolls r ON r.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(e.payload, "$.roll_id")) AS UNSIGNED)
             JOIN warehouses w ON w.id = r.warehouse_id
             JOIN skus s ON s.id = r.sku_id
             WHERE e.type IN ("WORK_ORDER_ROLL_ATTACHED","WORK_ORDER_ROLL_RELEASED")
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(e.payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY e.id DESC'
        );
        $stmt->execute([':wo' => $workOrderId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $payload = json_decode((string)($row['payload'] ?? '{}'), true);
            $row['payload_data'] = is_array($payload) ? $payload : [];
            $this->decorateRollWithErpContext($row);
        }
        unset($row);
        return $rows;
    }

    public function listWorkOrderProcessEvents(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, type, created_at, payload
             FROM events
             WHERE type IN ("WORK_ORDER_PAUSE_STARTED","WORK_ORDER_PAUSE_ENDED","WORK_ORDER_MAINTENANCE_STARTED","WORK_ORDER_MAINTENANCE_ENDED")
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY id ASC'
        );
        $stmt->execute([':wo' => $workOrderId]);
        $rows = $stmt->fetchAll();

        $items = [];
        $itemIndexByStartEvent = [];
        foreach ($rows as $row) {
            $payload = json_decode((string)($row['payload'] ?? '{}'), true);
            $payload = is_array($payload) ? $payload : [];
            $eventType = strtoupper(trim((string)($row['type'] ?? '')));
            $eventKey = strtoupper(trim((string)($payload['event_key'] ?? '')));
            if ($eventKey === '') {
                $eventKey = str_contains($eventType, 'MAINTENANCE') ? 'MAINTENANCE' : 'PAUSE';
            }
            if (str_ends_with($eventType, '_STARTED')) {
                $items[] = [
                    'start_event_id' => (int)($row['id'] ?? 0),
                    'event_key' => $eventKey,
                    'event_label' => $eventKey === 'MAINTENANCE' ? 'Mantención' : 'Pausa',
                    'started_at' => trim((string)($payload['started_at'] ?? '')) !== '' ? (string)$payload['started_at'] : (string)($row['created_at'] ?? ''),
                    'ended_at' => null,
                    'comments' => trim((string)($payload['comments'] ?? '')),
                    'status' => 'OPEN',
                ];
                $itemIndexByStartEvent[(int)($row['id'] ?? 0)] = count($items) - 1;
                continue;
            }

            $startEventId = (int)($payload['start_event_id'] ?? 0);
            if ($startEventId <= 0 || !isset($itemIndexByStartEvent[$startEventId])) {
                continue;
            }
            $itemIndex = $itemIndexByStartEvent[$startEventId];
            $items[$itemIndex]['ended_at'] = trim((string)($payload['ended_at'] ?? '')) !== '' ? (string)$payload['ended_at'] : (string)($row['created_at'] ?? '');
            $items[$itemIndex]['status'] = 'CLOSED';
        }

        return array_reverse($items);
    }

    public function listWorkOrderSealingSetupEvents(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, type, created_at, payload
             FROM events
             WHERE type IN ("WORK_ORDER_SEALING_SETUP_STARTED","WORK_ORDER_SEALING_SETUP_ENDED")
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY id ASC'
        );
        $stmt->execute([':wo' => $workOrderId]);
        $rows = $stmt->fetchAll();

        $items = [];
        $itemIndexByStartEvent = [];
        foreach ($rows as $row) {
            $payload = json_decode((string)($row['payload'] ?? '{}'), true);
            $payload = is_array($payload) ? $payload : [];
            $eventType = strtoupper(trim((string)($row['type'] ?? '')));
            if ($eventType === 'WORK_ORDER_SEALING_SETUP_STARTED') {
                $items[] = [
                    'start_event_id' => (int)($row['id'] ?? 0),
                    'event_key' => 'SEALING_SETUP',
                    'event_label' => 'Alistamiento',
                    'started_at' => trim((string)($payload['started_at'] ?? '')) !== '' ? (string)$payload['started_at'] : (string)($row['created_at'] ?? ''),
                    'ended_at' => null,
                    'comments' => trim((string)($payload['comments'] ?? '')),
                    'detail' => trim((string)($payload['detail'] ?? '')),
                    'status' => 'OPEN',
                ];
                $itemIndexByStartEvent[(int)($row['id'] ?? 0)] = count($items) - 1;
                continue;
            }

            $startEventId = (int)($payload['start_event_id'] ?? 0);
            if ($startEventId <= 0 || !isset($itemIndexByStartEvent[$startEventId])) {
                continue;
            }
            $itemIndex = $itemIndexByStartEvent[$startEventId];
            $items[$itemIndex]['ended_at'] = trim((string)($payload['ended_at'] ?? '')) !== '' ? (string)$payload['ended_at'] : (string)($row['created_at'] ?? '');
            $items[$itemIndex]['status'] = 'CLOSED';
        }

        return array_reverse($items);
    }

    public function listWorkOrderPackagingSetupEvents(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, type, created_at, payload
             FROM events
             WHERE type IN ("WORK_ORDER_PACKAGING_SETUP_STARTED","WORK_ORDER_PACKAGING_SETUP_ENDED")
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY id ASC'
        );
        $stmt->execute([':wo' => $workOrderId]);
        $rows = $stmt->fetchAll();

        $items = [];
        $itemIndexByStartEvent = [];
        foreach ($rows as $row) {
            $payload = json_decode((string)($row['payload'] ?? '{}'), true);
            $payload = is_array($payload) ? $payload : [];
            $eventType = strtoupper(trim((string)($row['type'] ?? '')));
            if ($eventType === 'WORK_ORDER_PACKAGING_SETUP_STARTED') {
                $items[] = [
                    'start_event_id' => (int)($row['id'] ?? 0),
                    'event_key' => 'PACKAGING_SETUP',
                    'event_label' => 'Alistamiento',
                    'started_at' => trim((string)($payload['started_at'] ?? '')) !== '' ? (string)$payload['started_at'] : (string)($row['created_at'] ?? ''),
                    'ended_at' => null,
                    'comments' => trim((string)($payload['comments'] ?? '')),
                    'detail' => trim((string)($payload['detail'] ?? '')),
                    'status' => 'OPEN',
                ];
                $itemIndexByStartEvent[(int)($row['id'] ?? 0)] = count($items) - 1;
                continue;
            }

            $startEventId = (int)($payload['start_event_id'] ?? 0);
            if ($startEventId <= 0 || !isset($itemIndexByStartEvent[$startEventId])) {
                continue;
            }
            $itemIndex = $itemIndexByStartEvent[$startEventId];
            $items[$itemIndex]['ended_at'] = trim((string)($payload['ended_at'] ?? '')) !== '' ? (string)$payload['ended_at'] : (string)($row['created_at'] ?? '');
            $items[$itemIndex]['status'] = 'CLOSED';
        }

        return array_reverse($items);
    }

    public function startWorkOrderProcessEvent(int $workOrderId, string $eventKey, string $comments, string $operatorName): array
    {
        $eventKey = strtoupper(trim($eventKey));
        if (!in_array($eventKey, ['PAUSE', 'MAINTENANCE'], true)) {
            throw new RuntimeException('El evento solicitado no es válido.');
        }
        if (trim($comments) === '') {
            throw new RuntimeException('Debes ingresar un comentario para registrar este evento.');
        }
        foreach ($this->listWorkOrderProcessEvents($workOrderId) as $existingEvent) {
            if ((string)($existingEvent['status'] ?? '') !== 'OPEN') {
                continue;
            }
            if (strtoupper((string)($existingEvent['event_key'] ?? '')) !== $eventKey) {
                continue;
            }
            throw new RuntimeException($eventKey === 'PAUSE'
                ? 'Ya existe una pausa en curso para esta OT.'
                : 'Ya existe una mantención en curso para esta OT.');
        }

        $startedAt = date('Y-m-d H:i:s');
        $this->insertEvent(
            $eventKey === 'PAUSE' ? 'WORK_ORDER_PAUSE_STARTED' : 'WORK_ORDER_MAINTENANCE_STARTED',
            [
                'work_order_id' => $workOrderId,
                'event_key' => $eventKey,
                'started_at' => $startedAt,
                'comments' => trim($comments),
                'operator_name' => trim($operatorName),
            ]
        );

        return [
            'ok' => true,
            'event_key' => $eventKey,
            'started_at' => $startedAt,
        ];
    }

    public function finishWorkOrderProcessEvent(int $workOrderId, int $startEventId, string $operatorName): array
    {
        $targetEvent = null;
        foreach ($this->listWorkOrderProcessEvents($workOrderId) as $processEvent) {
            if ((int)($processEvent['start_event_id'] ?? 0) !== $startEventId) {
                continue;
            }
            $targetEvent = $processEvent;
            break;
        }
        if (!is_array($targetEvent)) {
            throw new RuntimeException('No se encontró el evento a terminar.');
        }
        if ((string)($targetEvent['status'] ?? '') !== 'OPEN') {
            throw new RuntimeException('Este evento ya fue terminado.');
        }

        $eventKey = strtoupper(trim((string)($targetEvent['event_key'] ?? '')));
        $endedAt = date('Y-m-d H:i:s');
        $this->insertEvent(
            $eventKey === 'PAUSE' ? 'WORK_ORDER_PAUSE_ENDED' : 'WORK_ORDER_MAINTENANCE_ENDED',
            [
                'work_order_id' => $workOrderId,
                'event_key' => $eventKey,
                'start_event_id' => $startEventId,
                'started_at' => (string)($targetEvent['started_at'] ?? ''),
                'ended_at' => $endedAt,
                'operator_name' => trim($operatorName),
            ]
        );

        return [
            'ok' => true,
            'event_key' => $eventKey,
            'ended_at' => $endedAt,
        ];
    }

    public function startWorkOrderPackagingSetupEvent(int $workOrderId, string $comments, string $operatorName, string $detail = ''): array
    {
        $workOrder = $this->getWorkOrder($workOrderId);
        if ($workOrderId <= 0 || $workOrder === null) {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La OT no existe.']];
        }
        foreach ($this->listWorkOrderPackagingSetupEvents($workOrderId) as $existingEvent) {
            if ((string)($existingEvent['status'] ?? '') === 'OPEN') {
                return ['ok' => false, 'errors' => ['event' => 'Ya existe un alistamiento de Embalaje en curso para esta OT.']];
            }
        }

        $startedAt = date('Y-m-d H:i:s');
        $this->insertEvent('WORK_ORDER_PACKAGING_SETUP_STARTED', [
            'work_order_id' => $workOrderId,
            'started_at' => $startedAt,
            'comments' => trim($comments),
            'detail' => trim($detail),
            'operator_name' => trim($operatorName),
        ]);

        return [
            'ok' => true,
            'started_at' => $startedAt,
        ];
    }

    public function finishWorkOrderPackagingSetupEvent(int $workOrderId, int $startEventId, string $operatorName): array
    {
        $targetEvent = null;
        foreach ($this->listWorkOrderPackagingSetupEvents($workOrderId) as $setupEvent) {
            if ((int)($setupEvent['start_event_id'] ?? 0) !== $startEventId) {
                continue;
            }
            $targetEvent = $setupEvent;
            break;
        }
        if (!is_array($targetEvent)) {
            throw new RuntimeException('No fue posible encontrar el evento de alistamiento indicado.');
        }
        if ((string)($targetEvent['status'] ?? '') !== 'OPEN') {
            throw new RuntimeException('Este evento de alistamiento ya fue terminado.');
        }

        $endedAt = date('Y-m-d H:i:s');
        $this->insertEvent('WORK_ORDER_PACKAGING_SETUP_ENDED', [
            'work_order_id' => $workOrderId,
            'start_event_id' => $startEventId,
            'started_at' => (string)($targetEvent['started_at'] ?? ''),
            'ended_at' => $endedAt,
            'operator_name' => trim($operatorName),
        ]);

        return [
            'ok' => true,
            'ended_at' => $endedAt,
        ];
    }

    public function listWorkOrderPackagingProductionEvents(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, type, created_at, payload
             FROM events
             WHERE type IN ("WORK_ORDER_PACKAGING_PRODUCTION_STARTED","WORK_ORDER_PACKAGING_PRODUCTION_FINISHED")
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY id ASC'
        );
        $stmt->execute([':wo' => $workOrderId]);
        $rows = $stmt->fetchAll();

        $items = [];
        $itemIndexByStartEvent = [];
        foreach ($rows as $row) {
            $payload = json_decode((string)($row['payload'] ?? '{}'), true);
            $payload = is_array($payload) ? $payload : [];
            $eventType = strtoupper(trim((string)($row['type'] ?? '')));
            if ($eventType === 'WORK_ORDER_PACKAGING_PRODUCTION_STARTED') {
                $items[] = [
                    'start_event_id' => (int)($row['id'] ?? 0),
                    'event_key' => 'PACKAGING_PRODUCTION',
                    'event_label' => 'Producción',
                    'started_at' => trim((string)($payload['started_at'] ?? '')) !== '' ? (string)$payload['started_at'] : (string)($row['created_at'] ?? ''),
                    'ended_at' => null,
                    'comments' => trim((string)($payload['comments'] ?? '')),
                    'detail' => trim((string)($payload['detail'] ?? '')),
                    'status' => 'OPEN',
                    'produced_units' => null,
                    'waste_kg' => null,
                ];
                $itemIndexByStartEvent[(int)($row['id'] ?? 0)] = count($items) - 1;
                continue;
            }

            $startEventId = (int)($payload['start_event_id'] ?? 0);
            if ($startEventId <= 0 || !isset($itemIndexByStartEvent[$startEventId])) {
                continue;
            }
            $itemIndex = $itemIndexByStartEvent[$startEventId];
            $items[$itemIndex]['ended_at'] = trim((string)($payload['ended_at'] ?? '')) !== '' ? (string)$payload['ended_at'] : (string)($row['created_at'] ?? '');
            $items[$itemIndex]['status'] = 'CLOSED';
            $items[$itemIndex]['produced_units'] = isset($payload['produced_units']) ? (float)$payload['produced_units'] : null;
            $items[$itemIndex]['waste_kg'] = isset($payload['waste_kg']) ? (float)$payload['waste_kg'] : null;
            if (trim((string)($payload['comments'] ?? '')) !== '') {
                $items[$itemIndex]['detail'] = trim((string)$payload['comments']);
            }
        }

        return array_reverse($items);
    }

    public function getOpenWorkOrderPackagingProductionEvent(int $workOrderId): ?array
    {
        foreach ($this->listWorkOrderPackagingProductionEvents($workOrderId) as $productionEvent) {
            if ((string)($productionEvent['status'] ?? '') === 'OPEN') {
                return $productionEvent;
            }
        }

        return null;
    }

    public function getLastWorkOrderPackagingFinish(int $workOrderId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, created_at,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.started_at")) AS started_at,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.ended_at")) AS ended_at,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.comments")) AS comments,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.produced_units")) AS produced_units,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.waste_kg")) AS waste_kg,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.package_measure")) AS package_measure
             FROM events
             WHERE type = "WORK_ORDER_PACKAGING_PRODUCTION_FINISHED"
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([':wo' => $workOrderId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function startWorkOrderPackagingProductionEvent(int $workOrderId, string $operatorName, string $comments = ''): array
    {
        $operatorName = trim($operatorName);
        $comments = trim($comments);
        $workOrder = $this->getWorkOrder($workOrderId);
        if ($workOrderId <= 0 || $workOrder === null) {
            return ['ok' => false, 'errors' => ['work_order_id' => 'OT no existe.']];
        }
        if ((string)$workOrder['status'] === 'CLOSED') {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La OT ya está cerrada.']];
        }
        if ($this->getLastWorkOrderPackagingSetupApproval($workOrderId) === null) {
            return ['ok' => false, 'errors' => ['setup' => 'Debes validar el alistamiento de Embalaje antes de iniciar la producción.']];
        }
        if ($this->getOpenWorkOrderPackagingProductionEvent($workOrderId) !== null) {
            return ['ok' => false, 'errors' => ['event' => 'Ya existe una producción de Embalaje en curso para esta OT.']];
        }
        $lastFinish = $this->getLastWorkOrderPackagingFinish($workOrderId);
        if ($lastFinish !== null) {
            $setupApproval = $this->getLastWorkOrderPackagingSetupApproval($workOrderId);
            $finishTs = strtotime((string)($lastFinish['created_at'] ?? ''));
            $setupTs = strtotime((string)($setupApproval['created_at'] ?? ''));
            $finishAfterSetup = $setupApproval === null || $finishTs === false || $setupTs === false ? true : ($finishTs >= $setupTs);
            if ($finishAfterSetup) {
                return ['ok' => false, 'errors' => ['event' => 'La producción de Embalaje ya fue cerrada para esta OT.']];
            }
        }
        if ($operatorName === '') {
            return ['ok' => false, 'errors' => ['operator_name' => 'Operador es obligatorio.']];
        }
        if ($comments === '') {
            return ['ok' => false, 'errors' => ['comments' => 'Debes ingresar un comentario para iniciar la producción de Embalaje.']];
        }

        $startedAt = date('Y-m-d H:i:s');
        $this->insertEvent('WORK_ORDER_PACKAGING_PRODUCTION_STARTED', [
            'work_order_id' => $workOrderId,
            'started_at' => $startedAt,
            'comments' => $comments,
            'operator_name' => $operatorName,
            'detail' => 'Producción de Embalaje iniciada.',
        ]);

        return [
            'ok' => true,
            'started_at' => $startedAt,
        ];
    }

    public function finishWorkOrderPackagingProduction(
        int $workOrderId,
        float $producedUnits,
        string $comments,
        array $wasteWeights,
        array $wasteComments,
        array $packagingData,
        string $operatorName
    ): array {
        $workOrder = $this->getWorkOrder($workOrderId);
        $openProductionEvent = $this->getOpenWorkOrderPackagingProductionEvent($workOrderId);
        $comments = trim($comments);
        $operatorName = trim($operatorName);
        $errors = [];

        if ($workOrder === null) {
            $errors['work_order_id'] = 'OT no existe.';
        } elseif ((string)$workOrder['status'] === 'CLOSED') {
            $errors['work_order_id'] = 'La OT ya está cerrada.';
        }
        if ($openProductionEvent === null) {
            $errors['event'] = 'No hay una producción activa de Embalaje para finalizar.';
        }
        if ($producedUnits < 0) {
            $errors['produced_units'] = 'La producción no puede ser negativa.';
        }

        $wasteItems = [];
        $wasteTotal = 0.0;
        foreach (['setup', 'printing', 'other', 'repair'] as $wasteKey) {
            $weight = isset($wasteWeights[$wasteKey]) ? round((float)$wasteWeights[$wasteKey], 3) : 0.0;
            if ($weight < 0) {
                $errors['waste_' . $wasteKey] = 'Las mermas no pueden ser negativas.';
                continue;
            }
            $comment = trim((string)($wasteComments[$wasteKey] ?? ''));
            $wasteItems[$wasteKey] = [
                'weight_kg' => $weight,
                'comments' => $comment,
            ];
            $wasteTotal += $weight;
        }

        $numericPackagingKeys = [
            'units_per_box',
            'boxes_per_pallet',
            'complete_pallets',
            'incomplete_pallet_boxes',
            'total_complete_boxes',
            'final_box_units',
            'leftover_bags',
            'showroom_bags',
        ];
        $normalizedPackagingData = [
            'package_measure' => trim((string)($packagingData['package_measure'] ?? '')),
        ];
        foreach ($numericPackagingKeys as $key) {
            $value = round((float)($packagingData[$key] ?? 0), 3);
            if ($value < 0) {
                $errors['packaging_' . $key] = 'Los valores de embalaje no pueden ser negativos.';
                continue;
            }
            $normalizedPackagingData[$key] = $value;
        }

        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $endedAt = date('Y-m-d H:i:s');
        $this->insertEvent('WORK_ORDER_PACKAGING_PRODUCTION_FINISHED', [
            'work_order_id' => $workOrderId,
            'start_event_id' => (int)($openProductionEvent['start_event_id'] ?? 0),
            'started_at' => (string)($openProductionEvent['started_at'] ?? ''),
            'ended_at' => $endedAt,
            'produced_units' => round($producedUnits, 3),
            'comments' => $comments,
            'waste_kg' => round($wasteTotal, 3),
            'waste_items' => $wasteItems,
            'package_measure' => (string)$normalizedPackagingData['package_measure'],
            'packaging_data' => $normalizedPackagingData,
            'operator_name' => $operatorName,
        ]);

        return [
            'ok' => true,
            'ended_at' => $endedAt,
            'waste_kg' => round($wasteTotal, 3),
            'produced_units' => round($producedUnits, 3),
        ];
    }

    public function closePackagingWorkOrder(
        int $workOrderId,
        int $warehouseId,
        string $closureClassification,
        string $supervisorUsername,
        string $supervisorDisplayName,
        string $supervisorObservation,
        float $totalUnits,
        array $packagingData,
        string $operatorName
    ): array {
        $workOrder = $this->getWorkOrder($workOrderId);
        $lastPackagingFinish = $this->getLastWorkOrderPackagingFinish($workOrderId);
        $operatorName = trim($operatorName);
        $closureClassification = trim($closureClassification);
        $supervisorUsername = trim($supervisorUsername);
        $supervisorDisplayName = trim($supervisorDisplayName);
        $supervisorObservation = trim($supervisorObservation);
        $errors = [];

        if ($workOrder === null) {
            $errors['work_order_id'] = 'OT no existe.';
        } elseif ((string)$workOrder['status'] === 'CLOSED') {
            $errors['work_order_id'] = 'La OT ya está cerrada.';
        }
        if ($lastPackagingFinish === null) {
            $errors['finish'] = 'Debes terminar la producción de Embalaje antes de cerrar la OT.';
        }
        if ($warehouseId <= 0) {
            $errors['warehouse_id'] = 'Debes seleccionar la bodega destino.';
        }
        if ($closureClassification === '') {
            $errors['closure_classification'] = 'Debes seleccionar la clasificación de cierre.';
        }
        if ($supervisorUsername === '') {
            $errors['supervisor_username'] = 'Usuario supervisor es obligatorio.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($totalUnits < 0) {
            $errors['total_units'] = 'El total no puede ser negativo.';
        }

        $warehouse = null;
        if ($warehouseId > 0) {
            $stmt = $this->pdo->prepare('SELECT id, code, name FROM warehouses WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $warehouseId]);
            $warehouse = $stmt->fetch();
            if (!is_array($warehouse)) {
                $errors['warehouse_id'] = 'La bodega destino no existe.';
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $normalizedPackagingData = [];
        foreach ([
            'package_measure',
            'units_per_box',
            'boxes_per_pallet',
            'complete_pallets',
            'incomplete_pallet_boxes',
            'total_complete_boxes',
            'final_box_units',
            'leftover_bags',
            'showroom_bags',
        ] as $key) {
            $normalizedPackagingData[$key] = $packagingData[$key] ?? null;
        }

        $pallets = $this->listPalletsByWorkOrder($workOrderId);
        $closedAt = date('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE work_orders SET status = :status WHERE id = :id');
            $stmt->execute([
                ':status' => 'CLOSED',
                ':id' => $workOrderId,
            ]);

            if (is_array($warehouse)) {
                $stmt = $this->pdo->prepare('UPDATE boxes SET warehouse_id = :warehouse_id, status = :status WHERE work_order_id = :work_order_id');
                $stmt->execute([
                    ':warehouse_id' => (int)$warehouse['id'],
                    ':status' => 'STORED',
                    ':work_order_id' => $workOrderId,
                ]);

                $updatePallet = $this->pdo->prepare('UPDATE pallets SET warehouse_id = :warehouse_id, status = :status WHERE id = :id');
                $insertMovement = $this->pdo->prepare(
                    'INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload)
                     VALUES (:entity_type, :entity_id, :movement_type, :from_warehouse_id, :to_warehouse_id, :payload)'
                );
                foreach ($pallets as $pallet) {
                    $palletId = (int)($pallet['id'] ?? 0);
                    if ($palletId <= 0) {
                        continue;
                    }
                    $fromWarehouseId = (int)($pallet['warehouse_id'] ?? 0);
                    $updatePallet->execute([
                        ':warehouse_id' => (int)$warehouse['id'],
                        ':status' => 'STORED',
                        ':id' => $palletId,
                    ]);
                    if ($fromWarehouseId !== (int)$warehouse['id']) {
                        $insertMovement->execute([
                            ':entity_type' => 'PALLET',
                            ':entity_id' => $palletId,
                            ':movement_type' => 'TRANSFER',
                            ':from_warehouse_id' => $fromWarehouseId > 0 ? $fromWarehouseId : null,
                            ':to_warehouse_id' => (int)$warehouse['id'],
                            ':payload' => json_encode([
                                'operator_name' => $operatorName,
                                'work_order_id' => $workOrderId,
                                'reason' => 'PACKAGING_CLOSE',
                            ], JSON_UNESCAPED_UNICODE),
                        ]);
                    }
                }
            }

            $this->insertEvent('WORK_ORDER_PACKAGING_CLOSED', [
                'work_order_id' => $workOrderId,
                'closed_at' => $closedAt,
                'warehouse_id' => (int)($warehouse['id'] ?? 0),
                'warehouse_code' => (string)($warehouse['code'] ?? ''),
                'warehouse_name' => (string)($warehouse['name'] ?? ''),
                'closure_classification' => $closureClassification,
                'supervisor_username' => $supervisorUsername,
                'supervisor_display_name' => $supervisorDisplayName,
                'supervisor_observation' => $supervisorObservation,
                'total_units' => round($totalUnits, 3),
                'packaging_data' => $normalizedPackagingData,
                'operator_name' => $operatorName,
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return [
            'ok' => true,
            'closed_at' => $closedAt,
            'warehouse_code' => (string)($warehouse['code'] ?? ''),
        ];
    }

    public function startWorkOrderSealingSetupEvent(int $workOrderId, string $comments, string $operatorName, string $detail = ''): array
    {
        $workOrder = $this->getWorkOrder($workOrderId);
        if ($workOrderId <= 0 || $workOrder === null) {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La OT no existe.']];
        }
        foreach ($this->listWorkOrderSealingSetupEvents($workOrderId) as $existingEvent) {
            if ((string)($existingEvent['status'] ?? '') === 'OPEN') {
                return ['ok' => false, 'errors' => ['event' => 'Ya existe un alistamiento de Selladora en curso para esta OT.']];
            }
        }

        $startedAt = date('Y-m-d H:i:s');
        $this->insertEvent('WORK_ORDER_SEALING_SETUP_STARTED', [
            'work_order_id' => $workOrderId,
            'started_at' => $startedAt,
            'comments' => trim($comments),
            'detail' => trim($detail),
            'operator_name' => trim($operatorName),
        ]);

        return [
            'ok' => true,
            'started_at' => $startedAt,
        ];
    }

    public function finishWorkOrderSealingSetupEvent(int $workOrderId, int $startEventId, string $operatorName): array
    {
        $targetEvent = null;
        foreach ($this->listWorkOrderSealingSetupEvents($workOrderId) as $setupEvent) {
            if ((int)($setupEvent['start_event_id'] ?? 0) !== $startEventId) {
                continue;
            }
            $targetEvent = $setupEvent;
            break;
        }
        if (!is_array($targetEvent)) {
            throw new RuntimeException('No fue posible encontrar el evento de alistamiento indicado.');
        }
        if ((string)($targetEvent['status'] ?? '') !== 'OPEN') {
            throw new RuntimeException('Este evento de alistamiento ya fue terminado.');
        }

        $endedAt = date('Y-m-d H:i:s');
        $this->insertEvent('WORK_ORDER_SEALING_SETUP_ENDED', [
            'work_order_id' => $workOrderId,
            'start_event_id' => $startEventId,
            'started_at' => (string)($targetEvent['started_at'] ?? ''),
            'ended_at' => $endedAt,
            'operator_name' => trim($operatorName),
        ]);

        return [
            'ok' => true,
            'ended_at' => $endedAt,
        ];
    }

    public function listWorkOrderSealingProductionEvents(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, type, created_at, payload
             FROM events
             WHERE type IN ("WORK_ORDER_SEALING_PRODUCTION_STARTED","WORK_ORDER_SEALING_PRODUCTION_FINISHED")
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY id ASC'
        );
        $stmt->execute([':wo' => $workOrderId]);
        $rows = $stmt->fetchAll();

        $items = [];
        $itemIndexByStartEvent = [];
        foreach ($rows as $row) {
            $payload = json_decode((string)($row['payload'] ?? '{}'), true);
            $payload = is_array($payload) ? $payload : [];
            $eventType = strtoupper(trim((string)($row['type'] ?? '')));
            if ($eventType === 'WORK_ORDER_SEALING_PRODUCTION_STARTED') {
                $items[] = [
                    'start_event_id' => (int)($row['id'] ?? 0),
                    'event_key' => 'SEALING_PRODUCTION',
                    'event_label' => 'Producción',
                    'started_at' => trim((string)($payload['started_at'] ?? '')) !== '' ? (string)$payload['started_at'] : (string)($row['created_at'] ?? ''),
                    'ended_at' => null,
                    'comments' => trim((string)($payload['comments'] ?? '')),
                    'detail' => trim((string)($payload['detail'] ?? '')),
                    'status' => 'OPEN',
                    'principal_counter' => null,
                    'receiver_counter' => null,
                    'waste_kg' => null,
                ];
                $itemIndexByStartEvent[(int)($row['id'] ?? 0)] = count($items) - 1;
                continue;
            }

            $startEventId = (int)($payload['start_event_id'] ?? 0);
            if ($startEventId <= 0 || !isset($itemIndexByStartEvent[$startEventId])) {
                continue;
            }
            $itemIndex = $itemIndexByStartEvent[$startEventId];
            $items[$itemIndex]['ended_at'] = trim((string)($payload['ended_at'] ?? '')) !== '' ? (string)$payload['ended_at'] : (string)($row['created_at'] ?? '');
            $items[$itemIndex]['status'] = 'CLOSED';
            $items[$itemIndex]['principal_counter'] = isset($payload['principal_counter']) ? (int)$payload['principal_counter'] : null;
            $items[$itemIndex]['receiver_counter'] = isset($payload['receiver_counter']) ? (int)$payload['receiver_counter'] : null;
            $items[$itemIndex]['waste_kg'] = isset($payload['waste_kg']) ? (float)$payload['waste_kg'] : null;
            if (trim((string)($payload['comments'] ?? '')) !== '') {
                $items[$itemIndex]['detail'] = trim((string)$payload['comments']);
            }
        }

        return array_reverse($items);
    }

    public function getOpenWorkOrderSealingProductionEvent(int $workOrderId): ?array
    {
        foreach ($this->listWorkOrderSealingProductionEvents($workOrderId) as $productionEvent) {
            if ((string)($productionEvent['status'] ?? '') === 'OPEN') {
                return $productionEvent;
            }
        }

        return null;
    }

    public function getLastWorkOrderSealingFinish(int $workOrderId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, created_at,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.started_at")) AS started_at,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.ended_at")) AS ended_at,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.comments")) AS comments,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.principal_counter")) AS principal_counter,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.receiver_counter")) AS receiver_counter,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.waste_kg")) AS waste_kg
             FROM events
             WHERE type = "WORK_ORDER_SEALING_PRODUCTION_FINISHED"
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([':wo' => $workOrderId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function startWorkOrderSealingProductionEvent(int $workOrderId, string $operatorName, string $comments = ''): array
    {
        $operatorName = trim($operatorName);
        $comments = trim($comments);
        $workOrder = $this->getWorkOrder($workOrderId);
        if ($workOrderId <= 0 || $workOrder === null) {
            return ['ok' => false, 'errors' => ['work_order_id' => 'OT no existe.']];
        }
        if ((string)$workOrder['status'] === 'CLOSED') {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La OT ya está cerrada.']];
        }
        if ($this->getLastWorkOrderSealingSetupApproval($workOrderId) === null) {
            return ['ok' => false, 'errors' => ['setup' => 'Debes validar el alistamiento de Selladora antes de iniciar la producción.']];
        }
        if ($this->getOpenWorkOrderSealingProductionEvent($workOrderId) !== null) {
            return ['ok' => false, 'errors' => ['event' => 'Ya existe una producción de Selladora en curso para esta OT.']];
        }
        $lastFinish = $this->getLastWorkOrderSealingFinish($workOrderId);
        if ($lastFinish !== null) {
            $setupApproval = $this->getLastWorkOrderSealingSetupApproval($workOrderId);
            $finishTs = strtotime((string)($lastFinish['created_at'] ?? ''));
            $setupTs = strtotime((string)($setupApproval['created_at'] ?? ''));
            $finishAfterSetup = $setupApproval === null || $finishTs === false || $setupTs === false ? true : ($finishTs >= $setupTs);
            if ($finishAfterSetup) {
                return ['ok' => false, 'errors' => ['event' => 'La producción de Selladora ya fue cerrada para esta OT.']];
            }
        }
        if ($operatorName === '') {
            return ['ok' => false, 'errors' => ['operator_name' => 'Operador es obligatorio.']];
        }
        if ($comments === '') {
            return ['ok' => false, 'errors' => ['comments' => 'Debes ingresar un comentario para iniciar la producción de Selladora.']];
        }

        $startedAt = date('Y-m-d H:i:s');
        $this->insertEvent('WORK_ORDER_SEALING_PRODUCTION_STARTED', [
            'work_order_id' => $workOrderId,
            'started_at' => $startedAt,
            'comments' => $comments,
            'operator_name' => $operatorName,
            'detail' => 'Producción de Selladora iniciada.',
        ]);

        return [
            'ok' => true,
            'started_at' => $startedAt,
        ];
    }

    public function finishWorkOrderSealingProduction(
        int $workOrderId,
        int $principalCounter,
        int $receiverCounter,
        string $comments,
        array $wasteWeights,
        array $wasteComments,
        string $operatorName
    ): array {
        $workOrder = $this->getWorkOrder($workOrderId);
        $openProductionEvent = $this->getOpenWorkOrderSealingProductionEvent($workOrderId);
        $comments = trim($comments);
        $operatorName = trim($operatorName);
        $errors = [];

        if ($workOrder === null) {
            $errors['work_order_id'] = 'OT no existe.';
        } elseif ((string)$workOrder['status'] === 'CLOSED') {
            $errors['work_order_id'] = 'La OT ya está cerrada.';
        }
        if ($openProductionEvent === null) {
            $errors['event'] = 'No hay una producción activa de Selladora para finalizar.';
        }
        if ($principalCounter < 0) {
            $errors['principal_counter'] = 'El contador tablero principal no puede ser negativo.';
        }
        if ($receiverCounter < 0) {
            $errors['receiver_counter'] = 'El contador módulo recibidor no puede ser negativo.';
        }

        $wasteItems = [];
        $wasteTotal = 0.0;
        foreach (['setup', 'printing', 'other', 'repair'] as $wasteKey) {
            $weight = isset($wasteWeights[$wasteKey]) ? round((float)$wasteWeights[$wasteKey], 3) : 0.0;
            if ($weight < 0) {
                $errors['waste_' . $wasteKey] = 'Las mermas no pueden ser negativas.';
                continue;
            }
            $comment = trim((string)($wasteComments[$wasteKey] ?? ''));
            $wasteItems[$wasteKey] = [
                'weight_kg' => $weight,
                'comments' => $comment,
            ];
            $wasteTotal += $weight;
        }

        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $endedAt = date('Y-m-d H:i:s');
        $this->insertEvent('WORK_ORDER_SEALING_PRODUCTION_FINISHED', [
            'work_order_id' => $workOrderId,
            'start_event_id' => (int)($openProductionEvent['start_event_id'] ?? 0),
            'started_at' => (string)($openProductionEvent['started_at'] ?? ''),
            'ended_at' => $endedAt,
            'principal_counter' => $principalCounter,
            'receiver_counter' => $receiverCounter,
            'comments' => $comments,
            'waste_kg' => round($wasteTotal, 3),
            'waste_items' => $wasteItems,
            'operator_name' => $operatorName,
        ]);

        return [
            'ok' => true,
            'ended_at' => $endedAt,
            'waste_kg' => round($wasteTotal, 3),
        ];
    }

    public function listOutputRollsByWorkOrder(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.roll_code, r.parent_roll_id, r.source_work_order_id, r.weight_kg, r.status, r.process_stage, r.created_at,
                    pr.roll_code AS parent_roll_code,
                    s.code AS sku_code, s.description AS sku_description,
                    COALESCE(box_stats.box_count, 0) AS box_count,
                    COALESCE(pallet_stats.pallet_count, 0) AS pallet_count
             FROM rolls r
             JOIN skus s ON s.id = r.sku_id
             LEFT JOIN rolls pr ON pr.id = r.parent_roll_id
             LEFT JOIN (
                SELECT source_roll_id, COUNT(*) AS box_count
                FROM boxes
                GROUP BY source_roll_id
             ) box_stats ON box_stats.source_roll_id = r.id
             LEFT JOIN (
                SELECT source_roll_id, COUNT(*) AS pallet_count
                FROM pallets
                GROUP BY source_roll_id
             ) pallet_stats ON pallet_stats.source_roll_id = r.id
             WHERE r.source_work_order_id = :wo
               AND r.process_stage IN ("PRINTED", "CUT")
             ORDER BY r.id DESC'
        );
        $stmt->execute([':wo' => $workOrderId]);
        return $stmt->fetchAll();
    }

    public function listChildRollsByParentRoll(int $parentRollId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.roll_code, r.weight_kg, r.status, r.process_stage, r.created_at,
                    wo.ot_code,
                    COALESCE(box_stats.box_count, 0) AS box_count,
                    COALESCE(pallet_stats.pallet_count, 0) AS pallet_count
             FROM rolls r
             LEFT JOIN work_orders wo ON wo.id = r.source_work_order_id
             LEFT JOIN (
                SELECT source_roll_id, COUNT(*) AS box_count
                FROM boxes
                GROUP BY source_roll_id
             ) box_stats ON box_stats.source_roll_id = r.id
             LEFT JOIN (
                SELECT source_roll_id, COUNT(*) AS pallet_count
                FROM pallets
                GROUP BY source_roll_id
             ) pallet_stats ON pallet_stats.source_roll_id = r.id
             WHERE r.parent_roll_id = :parent_roll_id
             ORDER BY r.id DESC'
        );
        $stmt->execute([':parent_roll_id' => $parentRollId]);
        return $stmt->fetchAll();
    }

    public function startWorkOrder(int $workOrderId, string $operatorName, string $comments = ''): array
    {
        $operatorName = trim($operatorName);
        $comments = trim($comments);
        $workOrder = $this->getWorkOrder($workOrderId);
        if ($workOrderId <= 0 || $workOrder === null) {
            return ['ok' => false, 'errors' => ['work_order_id' => 'OT no existe.']];
        }
        if ((string)$workOrder['status'] === 'CLOSED') {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La OT ya está cerrada.']];
        }
        if ((string)$workOrder['status'] === 'CUTTING') {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La OT ya terminó impresión y está pendiente de corte.']];
        }
        if ($this->getLastWorkOrderStart($workOrderId) !== null) {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La producción ya fue iniciada para esta OT.']];
        }
        if ($this->getCurrentRollInWorkOrder($workOrderId) === null) {
            return ['ok' => false, 'errors' => ['roll' => 'Debes asignar y pesar una bobina antes de iniciar la OT.']];
        }
        if ($this->listChemicalInputsByWorkOrder($workOrderId, 1) === []) {
            return ['ok' => false, 'errors' => ['chemical' => 'Debes registrar al menos un químico de entrada antes de iniciar la OT.']];
        }

        $this->setActiveWorkOrder($workOrderId, $operatorName);
        $this->assignActiveShiftSessionToWorkOrder($workOrderId, $operatorName);
        $this->insertEvent('WORK_ORDER_STARTED', [
            'work_order_id' => $workOrderId,
            'operator_name' => $operatorName,
            'comments' => $comments,
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function startWorkOrderProductionEvent(int $workOrderId, string $operatorName, string $comments = ''): array
    {
        $operatorName = trim($operatorName);
        $comments = trim($comments);
        $workOrder = $this->getWorkOrder($workOrderId);
        if ($workOrderId <= 0 || $workOrder === null) {
            return ['ok' => false, 'errors' => ['work_order_id' => 'OT no existe.']];
        }
        if ((string)$workOrder['status'] === 'CLOSED') {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La OT ya está cerrada.']];
        }
        if ((string)$workOrder['status'] === 'CUTTING') {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La producción ya fue cerrada para esta OT.']];
        }
        if ($this->getLastWorkOrderStart($workOrderId) !== null) {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La producción ya fue iniciada para esta OT.']];
        }
        if ($operatorName === '') {
            return ['ok' => false, 'errors' => ['operator_name' => 'Operador es obligatorio.']];
        }
        if ($comments === '') {
            return ['ok' => false, 'errors' => ['comments' => 'Debes ingresar un comentario para iniciar la producción.']];
        }

        $this->setActiveWorkOrder($workOrderId, $operatorName);
        $this->assignActiveShiftSessionToWorkOrder($workOrderId, $operatorName);
        $this->insertEvent('WORK_ORDER_STARTED', [
            'work_order_id' => $workOrderId,
            'operator_name' => $operatorName,
            'comments' => $comments,
            'source' => 'SETUP',
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function getLastWorkOrderStart(int $workOrderId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, created_at,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.operator_name")) AS operator_name,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.comments")) AS comments
             FROM events
             WHERE type = "WORK_ORDER_STARTED"
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([':wo' => $workOrderId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function approveWorkOrderSetup(int $workOrderId, string $role, string $approvedUsername, string $approvedDisplayName): array
    {
        $workOrder = $this->getWorkOrder($workOrderId);
        if ($workOrderId <= 0 || $workOrder === null) {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La OT no existe.']];
        }

        $role = strtoupper(trim($role)) === 'LEADER' ? 'LEADER' : 'SUPERVISOR';
        $approvedUsername = trim($approvedUsername);
        $approvedDisplayName = trim($approvedDisplayName);
        if ($approvedUsername === '') {
            return ['ok' => false, 'errors' => ['approval_username' => 'Usuario aprobador inválido.']];
        }

        $this->insertEvent('WORK_ORDER_SETUP_APPROVED', [
            'work_order_id' => $workOrderId,
            'role' => $role,
            'approved_username' => $approvedUsername,
            'approved_display_name' => $approvedDisplayName !== '' ? $approvedDisplayName : $approvedUsername,
            'detail' => 'La máquina quedó configurada para producción.',
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function approveWorkOrderSealingSetup(int $workOrderId, string $role, string $approvedUsername, string $approvedDisplayName): array
    {
        $workOrder = $this->getWorkOrder($workOrderId);
        if ($workOrderId <= 0 || $workOrder === null) {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La OT no existe.']];
        }

        $role = strtoupper(trim($role)) === 'LEADER' ? 'LEADER' : 'SUPERVISOR';
        $approvedUsername = trim($approvedUsername);
        $approvedDisplayName = trim($approvedDisplayName);
        if ($approvedUsername === '') {
            return ['ok' => false, 'errors' => ['approval_username' => 'Usuario aprobador inválido.']];
        }

        $this->insertEvent('WORK_ORDER_SEALING_SETUP_APPROVED', [
            'work_order_id' => $workOrderId,
            'role' => $role,
            'approved_username' => $approvedUsername,
            'approved_display_name' => $approvedDisplayName !== '' ? $approvedDisplayName : $approvedUsername,
            'detail' => 'La selladora quedó configurada para producción.',
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function approveWorkOrderPackagingSetup(int $workOrderId, string $role, string $approvedUsername, string $approvedDisplayName): array
    {
        $workOrder = $this->getWorkOrder($workOrderId);
        if ($workOrderId <= 0 || $workOrder === null) {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La OT no existe.']];
        }

        $role = strtoupper(trim($role)) === 'LEADER' ? 'LEADER' : 'SUPERVISOR';
        $approvedUsername = trim($approvedUsername);
        $approvedDisplayName = trim($approvedDisplayName);
        if ($approvedUsername === '') {
            return ['ok' => false, 'errors' => ['approval_username' => 'Usuario aprobador inválido.']];
        }

        $this->insertEvent('WORK_ORDER_PACKAGING_SETUP_APPROVED', [
            'work_order_id' => $workOrderId,
            'role' => $role,
            'approved_username' => $approvedUsername,
            'approved_display_name' => $approvedDisplayName !== '' ? $approvedDisplayName : $approvedUsername,
            'detail' => 'El embalaje quedó configurado para producción.',
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function getLastWorkOrderSetupApproval(int $workOrderId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, created_at,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.role")) AS role,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.approved_username")) AS approved_username,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.approved_display_name")) AS approved_display_name
             FROM events
             WHERE type = "WORK_ORDER_SETUP_APPROVED"
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([':wo' => $workOrderId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getLastWorkOrderSealingSetupApproval(int $workOrderId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, created_at,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.role")) AS role,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.approved_username")) AS approved_username,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.approved_display_name")) AS approved_display_name
             FROM events
             WHERE type = "WORK_ORDER_SEALING_SETUP_APPROVED"
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([':wo' => $workOrderId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getLastWorkOrderPackagingSetupApproval(int $workOrderId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, created_at,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.role")) AS role,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.approved_username")) AS approved_username,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.approved_display_name")) AS approved_display_name
             FROM events
             WHERE type = "WORK_ORDER_PACKAGING_SETUP_APPROVED"
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([':wo' => $workOrderId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getLastWorkOrderFinish(int $workOrderId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, created_at,
                    CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.roll_id")) AS UNSIGNED) AS roll_id,
                    CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.output_roll_id")) AS UNSIGNED) AS output_roll_id,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.operator_name")) AS operator_name,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.final_roll_weight_kg")) AS final_roll_weight_kg,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.output_roll_weight_kg")) AS output_roll_weight_kg,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.final_chemical_weight_kg")) AS final_chemical_weight_kg,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.production_meters")) AS production_meters,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.comments")) AS comments,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.box_qty")) AS box_qty,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.waste_kg")) AS waste_kg
             FROM events
             WHERE type = "WORK_ORDER_FINISHED"
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([':wo' => $workOrderId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function approveWorkOrderFinish(int $workOrderId, string $role, string $approvedUsername, string $approvedDisplayName): array
    {
        $workOrder = $this->getWorkOrder($workOrderId);
        if ($workOrderId <= 0 || $workOrder === null) {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La OT no existe.']];
        }

        $lastFinish = $this->getLastWorkOrderFinish($workOrderId);
        if ($lastFinish === null) {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La OT aun no registra cierre de flexografia.']];
        }

        $role = strtoupper(trim($role)) === 'LEADER' ? 'LEADER' : 'SUPERVISOR';
        $approvedUsername = trim($approvedUsername);
        $approvedDisplayName = trim($approvedDisplayName);
        if ($approvedUsername === '') {
            return ['ok' => false, 'errors' => ['approval_username' => 'Usuario aprobador invalido.']];
        }

        $this->insertEvent('WORK_ORDER_FINISH_APPROVED', [
            'work_order_id' => $workOrderId,
            'role' => $role,
            'approved_username' => $approvedUsername,
            'approved_display_name' => $approvedDisplayName !== '' ? $approvedDisplayName : $approvedUsername,
            'detail' => 'El cierre de flexografia fue validado por supervisor.',
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function getLastWorkOrderFinishApproval(int $workOrderId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, created_at,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.role")) AS role,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.approved_username")) AS approved_username,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.approved_display_name")) AS approved_display_name
             FROM events
             WHERE type = "WORK_ORDER_FINISH_APPROVED"
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([':wo' => $workOrderId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $lastFinish = $this->getLastWorkOrderFinish($workOrderId);
        if ($lastFinish === null) {
            return null;
        }

        $approvalId = (int)($row['id'] ?? 0);
        $finishId = (int)($lastFinish['id'] ?? 0);
        if ($approvalId > 0 && $finishId > 0 && $approvalId < $finishId) {
            return null;
        }

        $approvalTs = strtotime((string)($row['created_at'] ?? ''));
        $finishTs = strtotime((string)($lastFinish['created_at'] ?? ''));
        if ($approvalTs !== false && $finishTs !== false && $approvalTs < $finishTs) {
            return null;
        }

        return $row;
    }

    public function getLastCutCompletion(int $workOrderId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, created_at,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.operator_name")) AS operator_name,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.box_qty")) AS box_qty,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.pallet_qty")) AS pallet_qty,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.units_total")) AS units_total
             FROM events
             WHERE type = "CUT_COMPLETED"
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([':wo' => $workOrderId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listMaterialRequestsByWorkOrder(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT mr.*,
                    wo.ot_code, wo.sku_final,
                    r.roll_code AS delivered_roll_code, r.sku_id AS delivered_roll_sku_id,
                    s.code AS delivered_roll_sku_code, s.description AS delivered_roll_sku_description
             FROM work_order_material_requests mr
             JOIN work_orders wo ON wo.id = mr.work_order_id
             LEFT JOIN rolls r ON r.id = mr.delivered_roll_id
             LEFT JOIN skus s ON s.id = r.sku_id
             WHERE mr.work_order_id = :wo
             ORDER BY mr.id DESC'
        );
        $stmt->execute([':wo' => $workOrderId]);
        return $stmt->fetchAll();
    }

    public function listAllMaterialRequests(int $limit = 200): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT mr.*,
                    wo.ot_code, wo.sku_final, wo.status AS work_order_status,
                    r.roll_code AS delivered_roll_code,
                    s.code AS delivered_roll_sku_code, s.description AS delivered_roll_sku_description
             FROM work_order_material_requests mr
             JOIN work_orders wo ON wo.id = mr.work_order_id
             LEFT JOIN rolls r ON r.id = mr.delivered_roll_id
             LEFT JOIN skus s ON s.id = r.sku_id
             ORDER BY FIELD(mr.status, "PENDING", "ACCEPTED", "PARTIAL", "DELIVERED"), mr.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getMaterialRequest(int $requestId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT mr.*,
                    wo.ot_code, wo.sku_final, wo.status AS work_order_status
             FROM work_order_material_requests mr
             JOIN work_orders wo ON wo.id = mr.work_order_id
             WHERE mr.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $requestId]);
        $request = $stmt->fetch();
        return $request === false ? null : $request;
    }

    public function acceptMaterialRequest(int $requestId, string $operatorName): array
    {
        $operatorName = trim($operatorName);
        if ($operatorName === '') {
            return ['ok' => false, 'errors' => ['operator_name' => 'Operador es obligatorio.']];
        }

        $request = $this->getMaterialRequest($requestId);
        if ($request === null) {
            return ['ok' => false, 'errors' => ['request_id' => 'Solicitud no existe.']];
        }
        if ((string)$request['status'] !== 'PENDING') {
            return ['ok' => false, 'errors' => ['status' => 'La solicitud ya fue tomada por bodega o ya tiene entregas.']];
        }
        if (!in_array((string)($request['work_order_status'] ?? ''), ['OPEN', 'ACTIVE', 'CUTTING'], true)) {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La OT ya no está disponible para atender esta solicitud.']];
        }

        $stmt = $this->pdo->prepare(
            'UPDATE work_order_material_requests
             SET status = :status,
                 accepted_by = :accepted_by,
                 accepted_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            ':status' => 'ACCEPTED',
            ':accepted_by' => $operatorName,
            ':id' => $requestId,
        ]);

        $this->insertEvent('MATERIAL_REQUEST_ACCEPTED', [
            'work_order_id' => (int)$request['work_order_id'],
            'request_id' => $requestId,
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function listMaterialDeliveriesByRequest(int $requestId): array
    {
        if ($requestId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT e.id, e.type, e.created_at, e.payload
             FROM events e
             WHERE e.type IN ("MATERIAL_DELIVERED", "MATERIAL_DELIVERY_REVERTED")
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(e.payload, "$.request_id")) AS UNSIGNED) = :request_id
             ORDER BY e.id ASC'
        );
        $stmt->execute([':request_id' => $requestId]);
        $rows = $stmt->fetchAll();
        $deliveryStacks = [];
        foreach ($rows as $row) {
            $payload = json_decode((string)($row['payload'] ?? '{}'), true);
            if (!is_array($payload)) {
                $payload = [];
            }
            $rollId = (int)($payload['roll_id'] ?? 0);
            if ($rollId <= 0) {
                continue;
            }
            $eventType = strtoupper(trim((string)($row['type'] ?? '')));
            if ($eventType === 'MATERIAL_DELIVERED') {
                if (!isset($deliveryStacks[$rollId])) {
                    $deliveryStacks[$rollId] = [];
                }
                $deliveryStacks[$rollId][] = [
                    'created_at' => (string)($row['created_at'] ?? ''),
                    'roll_id' => $rollId,
                    'roll_code' => (string)($payload['roll_code'] ?? ''),
                    'operator_name' => (string)($payload['operator_name'] ?? ''),
                    'request_type' => (string)($payload['request_type'] ?? 'ROLL'),
                    'delivered_qty' => (float)($payload['delivered_qty'] ?? 0),
                    'requested_unit' => (string)($payload['requested_unit'] ?? 'Unid.'),
                    'delivered_item' => (string)($payload['delivered_item'] ?? ''),
                    'delivery_note' => (string)($payload['delivery_note'] ?? ''),
                ];
                continue;
            }

            if (isset($deliveryStacks[$rollId]) && $deliveryStacks[$rollId] !== []) {
                array_pop($deliveryStacks[$rollId]);
                if ($deliveryStacks[$rollId] === []) {
                    unset($deliveryStacks[$rollId]);
                }
            }
        }

        $deliveries = [];
        foreach ($deliveryStacks as $stack) {
            foreach ($stack as $delivery) {
                $deliveries[] = $delivery;
            }
        }

        usort(
            $deliveries,
            static fn(array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''))
        );

        return $deliveries;
    }

    public function listAvailableRollsForMaterialRequest(): array
    {
        $rolls = $this->listAvailableRollsForMaterialDelivery();
        $groups = [];
        foreach ($rolls as $roll) {
            if (!$this->isRequestableRollMaterial($roll)) {
                continue;
            }
            $groupKey = $this->materialGroupKeyFromRoll($roll);
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'group_key' => $groupKey,
                    'sku_description' => (string)($roll['sku_description'] ?? ''),
                    'grams' => $roll['grams'] ?? null,
                    'width_mm' => $roll['width_mm'] ?? null,
                    'color' => $roll['color'] ?? null,
                    'meters' => $roll['meters'] ?? null,
                    'available_qty' => 0,
                ];
            }
            $groups[$groupKey]['available_qty']++;
        }

        usort($groups, static function (array $a, array $b): int {
            return [$a['sku_description'], (string)($a['width_mm'] ?? ''), (string)($a['grams'] ?? ''), (string)($a['color'] ?? '')]
                <=> [$b['sku_description'], (string)($b['width_mm'] ?? ''), (string)($b['grams'] ?? ''), (string)($b['color'] ?? '')];
        });

        return array_values($groups);
    }

    public function listAvailableRollsForMaterialDelivery(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.roll_code, r.weight_kg, r.received_qty, r.microns AS grams, r.width_mm, r.color, r.meters, r.process_stage,
                    w.code AS warehouse_code, w.name AS warehouse_name,
                    s.code AS sku_code, s.description AS sku_description
             FROM rolls r
             JOIN warehouses w ON w.id = r.warehouse_id
             JOIN skus s ON s.id = r.sku_id
             WHERE r.current_work_order_id IS NULL
               AND r.status = "RECEIVED"
               AND r.process_stage IN ("RAW","PRINTED")
             ORDER BY s.description ASC, r.id ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createMaterialRequest(
        int $workOrderId,
        string $requestType,
        string $requestedGroupKey,
        ?int $chemicalId,
        string $requestedItemText,
        float $requestedQty,
        float $requestedMeters,
        string $requestedUnit,
        string $notes,
        string $operatorName
    ): array
    {
        $requestType = strtoupper(trim($requestType));
        $requestedGroupKey = trim($requestedGroupKey);
        $requestedItemText = trim($requestedItemText);
        $requestedUnit = trim($requestedUnit);
        $notes = trim($notes);
        $operatorName = trim($operatorName);
        $requestedMeters = round($requestedMeters, 3);
        $errors = [];
        $group = null;
        $chemical = null;

        if ($workOrderId <= 0 || $this->getWorkOrder($workOrderId) === null) {
            $errors['work_order_id'] = 'OT no existe.';
        }
        if (!in_array($requestType, ['ROLL', 'CHEMICAL', 'OTHER'], true)) {
            $errors['request_type'] = 'Tipo de solicitud inválido.';
        }
        if ($requestType !== 'ROLL' && $requestedQty <= 0) {
            $errors['requested_qty'] = 'Cantidad solicitada debe ser mayor a 0.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }

        if ($requestType === 'ROLL') {
            $group = $this->findMaterialGroupByKey($requestedGroupKey);
            if ($requestedGroupKey === '' || $group === null) {
                $errors['requested_group_key'] = 'Debes seleccionar un tipo de bobina disponible.';
            } else {
                if ($requestedMeters < 0) {
                    $errors['requested_meters'] = 'Los metros lineales no pueden ser negativos.';
                }
                $estimatedQty = $this->estimateRollQuantityByMeters(
                    $requestedMeters,
                    (float)($group['meters'] ?? 0),
                    $this->getRollRequestLinearPlanningConfig()
                );
                if ($requestedMeters > 0 && $estimatedQty > 0) {
                    $requestedQty = (float)$estimatedQty;
                }
                if ($requestedQty <= 0) {
                    $errors['requested_qty'] = 'Debes indicar metros lineales o una cantidad válida de bobinas.';
                } elseif ($requestedQty > (float)($group['available_qty'] ?? 0)) {
                    $errors['requested_qty'] = 'La cantidad solicitada supera las bobinas disponibles en bodega.';
                }
            }
            $requestedUnit = 'Unid.';
        } elseif ($requestType === 'CHEMICAL') {
            if (($chemicalId ?? 0) <= 0) {
                $errors['chemical_id'] = 'Debes seleccionar un químico.';
            } else {
                $stmt = $this->pdo->prepare('SELECT id, code, name FROM chemicals WHERE id = :id AND is_active = 1 LIMIT 1');
                $stmt->execute([':id' => (int)$chemicalId]);
                $chemical = $stmt->fetch();
                if ($chemical === false) {
                    $errors['chemical_id'] = 'Químico no existe o está inactivo.';
                }
            }
            if ($requestedUnit === '') {
                $requestedUnit = 'Kg';
            }
        } elseif ($requestType === 'OTHER') {
            if ($requestedItemText === '') {
                $errors['requested_item'] = 'Debes indicar el material o insumo solicitado.';
            }
            if ($requestedUnit === '') {
                $requestedUnit = 'Unid.';
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $requestedItem = match ($requestType) {
            'ROLL' => $this->materialGroupLabel($group),
            'CHEMICAL' => 'Químico: ' . trim((string)$chemical['code'] . ' - ' . (string)$chemical['name']),
            default => $requestedItemText,
        };

        $stmt = $this->pdo->prepare(
            'INSERT INTO work_order_material_requests (work_order_id, request_type, requested_item, requested_qty, requested_meters, estimated_roll_qty, requested_unit, delivered_qty, request_notes, status, requested_by, requested_roll_id, requested_group_key, chemical_id)
             VALUES (:wo, :request_type, :item, :qty, :requested_meters, :estimated_roll_qty, :requested_unit, :delivered_qty, :notes, :status, :requested_by, NULL, :requested_group_key, :chemical_id)'
        );
        $stmt->execute([
            ':wo' => $workOrderId,
            ':request_type' => $requestType,
            ':item' => $requestedItem,
            ':qty' => number_format($requestedQty, 3, '.', ''),
            ':requested_meters' => $requestType === 'ROLL' && $requestedMeters > 0 ? number_format($requestedMeters, 3, '.', '') : null,
            ':estimated_roll_qty' => $requestType === 'ROLL' && $requestedQty > 0 ? number_format($requestedQty, 3, '.', '') : null,
            ':requested_unit' => $requestedUnit,
            ':delivered_qty' => number_format(0, 3, '.', ''),
            ':notes' => $notes !== '' ? $notes : null,
            ':status' => 'PENDING',
            ':requested_by' => $operatorName,
            ':requested_group_key' => $requestType === 'ROLL' ? $requestedGroupKey : null,
            ':chemical_id' => $requestType === 'CHEMICAL' ? (int)$chemicalId : null,
        ]);

        $requestId = (int)$this->pdo->lastInsertId();
        $this->insertEvent('MATERIAL_REQUESTED', [
            'work_order_id' => $workOrderId,
            'request_id' => $requestId,
            'request_type' => $requestType,
            'requested_group_key' => $requestedGroupKey,
            'requested_item' => $requestedItem,
            'requested_qty' => round($requestedQty, 3),
            'requested_meters' => $requestType === 'ROLL' ? $requestedMeters : 0,
            'requested_unit' => $requestedUnit,
            'request_notes' => $notes,
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'errors' => [], 'request_id' => $requestId];
    }

    public function deliverGenericMaterialRequest(int $requestId, float $deliveredQty, string $deliveryNote, string $operatorName): array
    {
        $operatorName = trim($operatorName);
        $deliveryNote = trim($deliveryNote);
        $request = $this->getMaterialRequest($requestId);
        if ($request === null) {
            return ['ok' => false, 'errors' => ['request_id' => 'Solicitud no existe.']];
        }
        if ((string)($request['request_type'] ?? 'ROLL') === 'ROLL') {
            return ['ok' => false, 'errors' => ['request_type' => 'Esta solicitud debe atenderse con bobinas escaneadas.']];
        }
        if (!in_array((string)$request['status'], ['ACCEPTED', 'PARTIAL'], true)) {
            if ((string)$request['status'] === 'PENDING') {
                return ['ok' => false, 'errors' => ['request_id' => 'La solicitud debe ser tomada por bodega antes de registrar la entrega.']];
            }
            return ['ok' => false, 'errors' => ['request_id' => 'La solicitud ya fue atendida.']];
        }
        if (!in_array((string)($request['work_order_status'] ?? ''), ['OPEN', 'ACTIVE', 'CUTTING'], true)) {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La OT ya no está disponible para recibir esta entrega.']];
        }
        if ($deliveredQty <= 0) {
            return ['ok' => false, 'errors' => ['delivered_qty' => 'La cantidad entregada debe ser mayor a 0.']];
        }
        if ($operatorName === '') {
            return ['ok' => false, 'errors' => ['operator_name' => 'Operador es obligatorio.']];
        }

        $requestedQty = (float)($request['requested_qty'] ?? 0);
        $currentDeliveredQty = (float)($request['delivered_qty'] ?? 0);
        $nextDeliveredQty = $currentDeliveredQty + $deliveredQty;
        if ($nextDeliveredQty > $requestedQty) {
            return ['ok' => false, 'errors' => ['delivered_qty' => 'La entrega supera la cantidad solicitada para esta OT.']];
        }

        $nextStatus = $nextDeliveredQty >= $requestedQty ? 'DELIVERED' : 'PARTIAL';
        $stmt = $this->pdo->prepare(
            'UPDATE work_order_material_requests
             SET status = :status,
                 delivered_qty = :delivered_qty,
                 delivered_by = :delivered_by,
                 delivered_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            ':status' => $nextStatus,
            ':delivered_qty' => number_format($nextDeliveredQty, 3, '.', ''),
            ':delivered_by' => $operatorName,
            ':id' => $requestId,
        ]);

        $this->insertEvent('MATERIAL_DELIVERED', [
            'work_order_id' => (int)$request['work_order_id'],
            'request_id' => $requestId,
            'request_type' => (string)$request['request_type'],
            'delivered_item' => (string)$request['requested_item'],
            'delivered_qty' => round($deliveredQty, 3),
            'requested_unit' => (string)($request['requested_unit'] ?? 'Unid.'),
            'delivery_note' => $deliveryNote,
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function deliverMaterialRequest(int $requestId, ?int $rollId, string $operatorName): array
    {
        $operatorName = trim($operatorName);
        $request = $this->getMaterialRequest($requestId);
        if ($request === null) {
            return ['ok' => false, 'errors' => ['request_id' => 'Solicitud no existe.']];
        }
        if (!in_array((string)$request['status'], ['ACCEPTED', 'PARTIAL'], true)) {
            if ((string)$request['status'] === 'PENDING') {
                return ['ok' => false, 'errors' => ['request_id' => 'La solicitud debe ser tomada por bodega antes de entregar bobinas.']];
            }
            return ['ok' => false, 'errors' => ['request_id' => 'La solicitud ya fue atendida.']];
        }
        if (!in_array((string)($request['work_order_status'] ?? ''), ['OPEN', 'ACTIVE', 'CUTTING'], true)) {
            return ['ok' => false, 'errors' => ['work_order_id' => 'La OT ya no está disponible para recibir bobinas.']];
        }

        $resolvedRollId = $rollId !== null && $rollId > 0
            ? $rollId
            : $this->findFirstAvailableRollIdByMaterialGroup((string)($request['requested_group_key'] ?? ''));
        $roll = $this->getRoll($resolvedRollId);
        if ($roll === null) {
            return ['ok' => false, 'errors' => ['roll_id' => 'Bobina no existe.']];
        }
        if (($request['requested_group_key'] ?? '') !== '' && $this->materialGroupKeyFromRoll($roll) !== (string)$request['requested_group_key']) {
            return ['ok' => false, 'errors' => ['roll_id' => 'La bobina entregada no coincide con el tipo solicitado.']];
        }
        if ($operatorName === '') {
            return ['ok' => false, 'errors' => ['operator_name' => 'Operador es obligatorio.']];
        }

        $workOrderId = (int)$request['work_order_id'];
        $transfer = $this->transferRoll((int)$roll['id'], 0, $operatorName, $workOrderId);
        if (($transfer['ok'] ?? false) !== true) {
            return $transfer;
        }

        $requestedQty = (float)($request['requested_qty'] ?? 0);
        $deliveredQty = (float)($request['delivered_qty'] ?? 0) + 1.0;
        $nextStatus = $deliveredQty >= $requestedQty ? 'DELIVERED' : 'PARTIAL';

        $stmt = $this->pdo->prepare(
            'UPDATE work_order_material_requests
             SET status = :status,
                 delivered_roll_id = :roll_id,
                 delivered_qty = :delivered_qty,
                 delivered_by = :delivered_by,
                 delivered_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            ':status' => $nextStatus,
            ':roll_id' => (int)$roll['id'],
            ':delivered_qty' => number_format($deliveredQty, 3, '.', ''),
            ':delivered_by' => $operatorName,
            ':id' => $requestId,
        ]);

        $this->insertEvent('MATERIAL_DELIVERED', [
            'work_order_id' => $workOrderId,
            'request_id' => $requestId,
            'request_type' => 'ROLL',
            'roll_id' => (int)$roll['id'],
            'roll_code' => (string)$roll['roll_code'],
            'delivered_qty' => 1,
            'requested_unit' => (string)($request['requested_unit'] ?? 'Unid.'),
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function attachRequestedRollToWorkOrder(int $requestId, int $rollId, float $processWeightKg, string $operatorName): array
    {
        $errors = [];
        $operatorName = trim($operatorName);
        $request = $this->getMaterialRequest($requestId);
        $roll = $this->getRoll($rollId);
        $productionWarehouseId = $this->findWarehouseIdByCode(3000);

        if ($request === null) {
            $errors['request_id'] = 'Solicitud no existe.';
        } elseif ((string)($request['request_type'] ?? 'ROLL') !== 'ROLL') {
            $errors['request_type'] = 'La solicitud no corresponde a una bobina.';
        } elseif (!in_array((string)($request['status'] ?? ''), ['PENDING', 'ACCEPTED', 'PARTIAL', 'DELIVERED'], true)) {
            $errors['request_status'] = 'La solicitud ya fue atendida.';
        } elseif (!in_array((string)($request['work_order_status'] ?? ''), ['OPEN', 'ACTIVE', 'CUTTING'], true)) {
            $errors['work_order_id'] = 'La OT no está disponible para ingresar bobinas.';
        }
        if ($roll === null) {
            $errors['roll_id'] = 'Bobina no existe.';
        }
        if ($processWeightKg <= 0) {
            $errors['process_weight_kg'] = 'Peso de entrada debe ser mayor a 0.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($productionWarehouseId === null) {
            $errors['warehouse_id'] = 'No existe la bodega 3000 de producción.';
        }

        $workOrderId = (int)($request['work_order_id'] ?? 0);
        if ($request !== null && $roll !== null && ($request['requested_group_key'] ?? '') !== '' && $this->materialGroupKeyFromRoll($roll) !== (string)$request['requested_group_key']) {
            $errors['roll_id'] = 'La bobina seleccionada no coincide con el tipo solicitado.';
        }
        $isDeliveredForRequest = $request !== null && $roll !== null
            ? $this->isRollDeliveredForMaterialRequest($requestId, (int)($roll['id'] ?? 0))
            : false;
        $requestAlreadyDeliveredRollId = (int)($request['delivered_roll_id'] ?? 0);
        $isAlreadyDeliveredForRequest = $request !== null
            && $roll !== null
            && $requestAlreadyDeliveredRollId > 0
            && $requestAlreadyDeliveredRollId === (int)($roll['id'] ?? 0);
        if ($roll !== null) {
            $rollStatus = strtoupper(trim((string)($roll['status'] ?? '')));
            $rollCurrentWorkOrderId = (int)($roll['current_work_order_id'] ?? 0);
            $isAttachableDeliveredRoll = ($isAlreadyDeliveredForRequest || $isDeliveredForRequest)
                && $rollStatus === 'IN_PROCESS'
                && $rollCurrentWorkOrderId === $workOrderId;
            if (!in_array($rollStatus, ['RECEIVED'], true) && !$isAttachableDeliveredRoll) {
                $errors['roll_status'] = 'Solo se pueden ingresar bobinas disponibles.';
            } elseif ($rollCurrentWorkOrderId > 0 && $rollCurrentWorkOrderId !== $workOrderId) {
                $errors['roll_work_order'] = 'La bobina ya está asignada a otra OT.';
            } elseif ($rollCurrentWorkOrderId === $workOrderId && !$isAlreadyDeliveredForRequest && !$isDeliveredForRequest) {
                $errors['roll_work_order'] = 'La bobina ya está asignada a esta OT.';
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $fromWarehouseId = (int)($roll['warehouse_id'] ?? 0);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE rolls SET warehouse_id = :warehouse_id, current_work_order_id = :wo, status = :status WHERE id = :id');
            $stmt->execute([
                ':warehouse_id' => $productionWarehouseId,
                ':wo' => $workOrderId,
                ':status' => 'IN_PROCESS',
                ':id' => $rollId,
            ]);

            if ($fromWarehouseId > 0 && $fromWarehouseId !== $productionWarehouseId) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload)
                     VALUES (:entity_type, :entity_id, :movement_type, :from_warehouse_id, :to_warehouse_id, :payload)'
                );
                $stmt->execute([
                    ':entity_type' => 'ROLL',
                    ':entity_id' => $rollId,
                    ':movement_type' => 'TRANSFER',
                    ':from_warehouse_id' => $fromWarehouseId,
                    ':to_warehouse_id' => $productionWarehouseId,
                    ':payload' => json_encode([
                        'operator_name' => $operatorName,
                        'work_order_id' => $workOrderId,
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                $this->insertEvent('ROLL_TRANSFERRED', [
                    'roll_id' => $rollId,
                    'from_warehouse_id' => $fromWarehouseId,
                    'to_warehouse_id' => $productionWarehouseId,
                    'operator_name' => $operatorName,
                    'work_order_id' => $workOrderId,
                ]);
            }

            if (!$isAlreadyDeliveredForRequest && !$isDeliveredForRequest) {
                $requestedQty = (float)($request['requested_qty'] ?? 0);
                $deliveredQty = (float)($request['delivered_qty'] ?? 0) + 1.0;
                $nextStatus = $deliveredQty >= $requestedQty ? 'DELIVERED' : 'PARTIAL';
                $stmt = $this->pdo->prepare(
                    'UPDATE work_order_material_requests
                     SET status = :status,
                         delivered_roll_id = :roll_id,
                         delivered_qty = :delivered_qty,
                         delivered_by = :delivered_by,
                         delivered_at = CURRENT_TIMESTAMP
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':status' => $nextStatus,
                    ':roll_id' => $rollId,
                    ':delivered_qty' => number_format($deliveredQty, 3, '.', ''),
                    ':delivered_by' => $operatorName,
                    ':id' => $requestId,
                ]);

                $this->insertEvent('MATERIAL_DELIVERED', [
                    'work_order_id' => $workOrderId,
                    'request_id' => $requestId,
                    'request_type' => 'ROLL',
                    'roll_id' => $rollId,
                    'roll_code' => (string)$roll['roll_code'],
                    'delivered_qty' => 1,
                    'requested_unit' => (string)($request['requested_unit'] ?? 'Unid.'),
                    'operator_name' => $operatorName,
                ]);
            }

            $this->insertEvent('WORK_ORDER_ROLL_ATTACHED', [
                'work_order_id' => $workOrderId,
                'request_id' => $requestId,
                'roll_id' => $rollId,
                'process_weight_kg' => round($processWeightKg, 3),
                'waste_kg' => 0,
                'operator_name' => $operatorName,
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['ok' => true, 'errors' => []];
    }

    public function releaseCurrentRollFromWorkOrder(int $workOrderId, float $finalWeightKg, float $wasteKg, string $operatorName, array $wasteDetails = [], int $rollId = 0, int $requestId = 0, ?float $finalMeters = null): array
    {
        $errors = [];
        $operatorName = trim($operatorName);
        $workOrder = $this->getWorkOrder($workOrderId);
        $currentRoll = $rollId > 0 ? $this->getRoll($rollId) : $this->getCurrentRollInWorkOrder($workOrderId);
        $finalMeters = $finalMeters !== null ? round($finalMeters, 3) : null;
        $normalizedWasteDetails = [];
        foreach ($wasteDetails as $wasteKey => $wasteDetail) {
            if (!is_array($wasteDetail)) {
                continue;
            }
            $weightKg = (float)($wasteDetail['weight_kg'] ?? 0);
            $label = trim((string)($wasteDetail['label'] ?? ''));
            $comment = trim((string)($wasteDetail['comment'] ?? ''));
            if ($weightKg < 0) {
                $errors['waste_detail_' . (string)$wasteKey] = 'Los kilos de merma no pueden ser negativos.';
                continue;
            }
            $normalizedWasteDetails[] = [
                'key' => (string)$wasteKey,
                'label' => $label !== '' ? $label : (string)$wasteKey,
                'comment' => $comment,
                'weight_kg' => round($weightKg, 3),
            ];
        }
        if ($normalizedWasteDetails !== []) {
            $wasteKg = 0.0;
            foreach ($normalizedWasteDetails as $wasteDetail) {
                $wasteKg += (float)($wasteDetail['weight_kg'] ?? 0);
            }
            $wasteKg = round($wasteKg, 3);
        }

        if ($workOrder === null) {
            $errors['work_order_id'] = 'OT no existe.';
        } elseif ((string)$workOrder['status'] === 'CLOSED') {
            $errors['work_order_id'] = 'La OT está cerrada.';
        }
        if ($currentRoll !== null && (int)($currentRoll['current_work_order_id'] ?? 0) !== $workOrderId) {
            $errors['current_roll'] = 'La bobina seleccionada no está activa en esta OT.';
        }
        if ($currentRoll === null) {
            $errors['current_roll'] = 'No hay una bobina activa para registrar salida.';
        }
        if ($finalWeightKg < 0) {
            $errors['final_weight_kg'] = 'Peso de salida no puede ser negativo.';
        }
        if ($wasteKg < 0) {
            $errors['waste_kg'] = 'Merma no puede ser negativa.';
        }
        $currentMeters = $currentRoll !== null ? (float)($currentRoll['meters'] ?? 0) : 0.0;
        if ($finalMeters !== null && $finalMeters < 0) {
            $errors['final_meters'] = 'Los metros finales no pueden ser negativos.';
        } elseif ($finalMeters !== null && $currentMeters > 0 && $finalMeters > $currentMeters) {
            $errors['final_meters'] = 'Los metros finales no pueden superar los metros actuales de la bobina.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE rolls SET weight_kg = :weight_kg, meters = :meters, current_work_order_id = NULL, status = :status WHERE id = :id');
            $stmt->execute([
                ':weight_kg' => round($finalWeightKg, 3),
                ':meters' => $finalMeters !== null ? number_format($finalMeters, 3, '.', '') : ($currentRoll['meters'] ?? null),
                ':status' => $finalWeightKg > 0 ? 'RECEIVED' : 'CONSUMED',
                ':id' => (int)$currentRoll['id'],
            ]);

            $this->insertEvent('WORK_ORDER_ROLL_RELEASED', [
                'work_order_id' => $workOrderId,
                'request_id' => $requestId > 0 ? $requestId : null,
                'roll_id' => (int)$currentRoll['id'],
                'final_weight_kg' => round($finalWeightKg, 3),
                'initial_meters' => $currentMeters > 0 ? round($currentMeters, 3) : null,
                'final_meters' => $finalMeters,
                'used_meters' => $finalMeters !== null && $currentMeters > 0 ? round(max(0, $currentMeters - $finalMeters), 3) : null,
                'waste_kg' => round($wasteKg, 3),
                'waste_details' => $normalizedWasteDetails,
                'reason' => 'MANUAL_RELEASE',
                'operator_name' => $operatorName,
            ]);
            foreach ($normalizedWasteDetails as $wasteDetail) {
                $detailWeightKg = (float)($wasteDetail['weight_kg'] ?? 0);
                if ($detailWeightKg <= 0) {
                    continue;
                }
                $wasteResult = $this->createProductionWaste(
                    $workOrderId,
                    (int)$currentRoll['id'],
                    'PRODUCTION',
                    trim((string)($wasteDetail['comment'] ?? '')) !== ''
                        ? (string)$wasteDetail['comment']
                        : (string)($wasteDetail['label'] ?? 'Merma producción'),
                    $detailWeightKg,
                    $operatorName
                );
                if (($wasteResult['ok'] ?? false) !== true) {
                    throw new RuntimeException('No se pudo registrar el detalle de merma de salida.');
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['ok' => true, 'errors' => []];
    }

    public function removeCurrentRequestedRollFromWorkOrder(int $workOrderId, int $requestId, string $operatorName, int $rollId = 0): array
    {
        $errors = [];
        $operatorName = trim($operatorName);
        $workOrder = $this->getWorkOrder($workOrderId);
        $request = $this->getMaterialRequest($requestId);
        $currentRoll = $rollId > 0 ? $this->getRoll($rollId) : $this->getCurrentRollInWorkOrder($workOrderId);

        if ($workOrder === null) {
            $errors['work_order_id'] = 'OT no existe.';
        } elseif ((string)$workOrder['status'] === 'CLOSED') {
            $errors['work_order_id'] = 'La OT está cerrada.';
        }
        if ($request === null) {
            $errors['request_id'] = 'Solicitud no existe.';
        } elseif ((int)($request['work_order_id'] ?? 0) !== $workOrderId) {
            $errors['request_id'] = 'La solicitud no corresponde a esta OT.';
        } elseif ((string)($request['request_type'] ?? 'ROLL') !== 'ROLL') {
            $errors['request_type'] = 'La solicitud no corresponde a una bobina.';
        }
        if ($currentRoll === null) {
            $errors['current_roll'] = 'No hay una bobina activa para eliminar.';
        } elseif ((int)($currentRoll['current_work_order_id'] ?? 0) !== $workOrderId || strtoupper(trim((string)($currentRoll['status'] ?? ''))) !== 'IN_PROCESS') {
            $errors['current_roll'] = 'La bobina seleccionada ya no está activa en esta OT.';
        }
        if ($request !== null && $currentRoll !== null && !$this->isRollDeliveredForMaterialRequest($requestId, (int)($currentRoll['id'] ?? 0))) {
            $errors['request_roll'] = 'La solicitud no coincide con la bobina seleccionada.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $nextDeliveredQty = max(0.0, (float)($request['delivered_qty'] ?? 0) - 1.0);
        $requestedQty = (float)($request['requested_qty'] ?? 0);
        $nextStatus = $nextDeliveredQty <= 0
            ? 'ACCEPTED'
            : ($requestedQty > 0 && $nextDeliveredQty >= $requestedQty ? 'DELIVERED' : 'PARTIAL');
        $remainingDeliveredRollId = 0;
        foreach ($this->listMaterialDeliveriesByRequest($requestId) as $delivery) {
            $deliveryRollId = (int)($delivery['roll_id'] ?? 0);
            if ($deliveryRollId > 0 && $deliveryRollId !== (int)($currentRoll['id'] ?? 0)) {
                $remainingDeliveredRollId = $deliveryRollId;
                break;
            }
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE rolls
                 SET current_work_order_id = NULL,
                     status = :status
                 WHERE id = :id'
            );
            $stmt->execute([
                ':status' => 'RECEIVED',
                ':id' => (int)$currentRoll['id'],
            ]);

            $stmt = $this->pdo->prepare(
                'UPDATE work_order_material_requests
                 SET status = :status,
                     delivered_roll_id = :delivered_roll_id,
                     delivered_qty = :delivered_qty,
                     delivered_by = :delivered_by,
                     delivered_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $stmt->execute([
                ':status' => $nextStatus,
                ':delivered_roll_id' => $remainingDeliveredRollId > 0 ? $remainingDeliveredRollId : null,
                ':delivered_qty' => number_format($nextDeliveredQty, 3, '.', ''),
                ':delivered_by' => $operatorName,
                ':id' => $requestId,
            ]);

            $this->insertEvent('WORK_ORDER_ROLL_RELEASED', [
                'work_order_id' => $workOrderId,
                'request_id' => $requestId,
                'roll_id' => (int)$currentRoll['id'],
                'final_weight_kg' => round((float)($currentRoll['weight_kg'] ?? 0), 3),
                'waste_kg' => 0,
                'reason' => 'ENTRY_REMOVED',
                'operator_name' => $operatorName,
            ]);

            $this->insertEvent('MATERIAL_DELIVERY_REVERTED', [
                'work_order_id' => $workOrderId,
                'request_id' => $requestId,
                'request_type' => 'ROLL',
                'roll_id' => (int)$currentRoll['id'],
                'roll_code' => (string)($currentRoll['roll_code'] ?? ''),
                'delivered_qty' => 1,
                'requested_unit' => (string)($request['requested_unit'] ?? 'Unid.'),
                'operator_name' => $operatorName,
                'reason' => 'ENTRY_REMOVED',
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['ok' => true, 'errors' => []];
    }

    private function parseWorkOrderRollHint(array $workOrder): array
    {
        $sheetText = trim((string)($workOrder['erp_plan_desc'] ?? '') . ' ' . (string)($workOrder['sku_final'] ?? ''));
        $widthMm = 0.0;
        $heightMm = 0.0;
        $gussetMm = 0.0;
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*[xX]\s*(\d+(?:[.,]\d+)?)(?:\s*[xX]\s*(\d+(?:[.,]\d+)?))?/', $sheetText, $dimensionMatch) === 1) {
            $widthMm = round(((float)str_replace(',', '.', (string)$dimensionMatch[1])) * 10, 3);
            $heightMm = round(((float)str_replace(',', '.', (string)$dimensionMatch[2])) * 10, 3);
            $gussetMm = trim((string)($dimensionMatch[3] ?? '')) !== ''
                ? round(((float)str_replace(',', '.', (string)$dimensionMatch[3])) * 10, 3)
                : 0.0;
        }

        $requiredMeters = round((float)($workOrder['erp_required_meters'] ?? 0), 3);
        if ($requiredMeters < 0) {
            $requiredMeters = 0.0;
        }
        $requiredMetersSource = trim((string)($workOrder['erp_required_meters_source'] ?? ''));
        if ($requiredMeters > 0 && $requiredMetersSource === '') {
            $requiredMetersSource = 'ERP';
        }

        $material = '';
        foreach (['PLA', 'BOPP', 'PEBD', 'PEAD', 'PP', 'PE'] as $materialKeyword) {
            if (stripos($sheetText, $materialKeyword) !== false) {
                $material = $materialKeyword;
                break;
            }
        }

        $color = '';
        foreach (['NATURAL', 'BLANCO', 'BEIGE', 'AZUL', 'ROJO', 'VERDE', 'NEGRO', 'TRANSPARENTE', 'AMARILLO', 'ROSADO'] as $colorKeyword) {
            if (stripos($sheetText, $colorKeyword) !== false) {
                $color = $colorKeyword;
                break;
            }
        }

        return [
            'sheet_text' => $sheetText,
            'width_mm' => $widthMm,
            'height_mm' => $heightMm,
            'gusset_mm' => $gussetMm,
            'required_meters' => $requiredMeters,
            'required_meters_source' => $requiredMetersSource,
            'material' => $material,
            'color' => $color,
        ];
    }

    private function scoreMaterialGroupForWorkOrder(array $group, array $hint): int
    {
        $score = 0;
        $groupDescription = strtoupper(trim((string)($group['sku_description'] ?? '')));
        $groupColor = strtoupper(trim((string)($group['color'] ?? '')));
        $groupWidth = (float)($group['width_mm'] ?? 0);

        if ($groupWidth > 0 && (float)($hint['width_mm'] ?? 0) > 0) {
            $difference = abs($groupWidth - (float)$hint['width_mm']);
            if ($difference <= 10) {
                $score += 60;
            } elseif ($difference <= 30) {
                $score += 45;
            } elseif ($difference <= 60) {
                $score += 25;
            }
        }

        $material = strtoupper(trim((string)($hint['material'] ?? '')));
        if ($material !== '' && str_contains($groupDescription, $material)) {
            $score += 30;
        }

        $color = strtoupper(trim((string)($hint['color'] ?? '')));
        if ($color !== '') {
            if ($groupColor === $color) {
                $score += 15;
            } elseif ($groupColor === '' && $color === 'NATURAL') {
                $score += 10;
            }
        }

        if ((float)($group['available_qty'] ?? 0) > 0) {
            $score += 5;
        }

        if ((float)($group['meters'] ?? 0) > 0) {
            $score += 3;
        }

        return $score;
    }

    private function estimateRollQuantityByMeters(float $requiredMeters, float $rollMeters, array $config): int
    {
        if ($requiredMeters <= 0 || $rollMeters <= 0) {
            return 0;
        }

        $bufferFactor = 1 + (((float)($config['buffer_percent'] ?? 0)) / 100);
        return max(1, (int)ceil(($requiredMeters * $bufferFactor) / $rollMeters));
    }

    private function materialGroupKeyFromRoll(array $roll): string
    {
        return implode('|', [
            (string)($roll['sku_code'] ?? ''),
            (string)($roll['sku_description'] ?? ''),
            (string)($roll['grams'] ?? ''),
            (string)($roll['width_mm'] ?? ''),
            trim((string)($roll['color'] ?? '')),
            (string)($roll['meters'] ?? ''),
            (string)($roll['process_stage'] ?? ''),
        ]);
    }

    private function isRollDeliveredForMaterialRequest(int $requestId, int $rollId): bool
    {
        if ($requestId <= 0 || $rollId <= 0) {
            return false;
        }
        foreach ($this->listMaterialDeliveriesByRequest($requestId) as $delivery) {
            if ((int)($delivery['roll_id'] ?? 0) === $rollId) {
                return true;
            }
        }

        return false;
    }

    private function isRequestableRollMaterial(array $roll): bool
    {
        $warehouseCode = (int)($roll['warehouse_code'] ?? 0);
        return in_array($warehouseCode, [100, 200], true);
    }

    private function materialGroupLabel(array $group): string
    {
        $parts = [];
        $product = trim((string)($group['sku_description'] ?? 'Bobina'));
        if ($product !== '') {
            $parts[] = $product;
        }

        $spec = [];
        if (($group['grams'] ?? '') !== '') { $spec[] = 'Gramos ' . (string)$group['grams']; }
        if (($group['width_mm'] ?? '') !== '') { $spec[] = 'Ancho ' . (string)$group['width_mm'] . ' mm'; }
        if (trim((string)($group['color'] ?? '')) !== '') { $spec[] = 'Color ' . trim((string)$group['color']); }
        if (($group['meters'] ?? '') !== '') { $spec[] = 'ML ' . (string)$group['meters']; }
        if ($spec !== []) {
            $parts[] = implode(' · ', $spec);
        }
        return implode(' | ', $parts);
    }

    private function findMaterialGroupByKey(string $groupKey): ?array
    {
        foreach ($this->listAvailableRollsForMaterialRequest() as $group) {
            if ((string)($group['group_key'] ?? '') === $groupKey) {
                return $group;
            }
        }
        return null;
    }

    private function findFirstAvailableRollIdByMaterialGroup(string $groupKey): int
    {
        foreach ($this->listAvailableRollsForMaterialDelivery() as $roll) {
            if (!$this->isRequestableRollMaterial($roll)) {
                continue;
            }
            if ($this->materialGroupKeyFromRoll($roll) === $groupKey) {
                return (int)$roll['id'];
            }
        }
        return 0;
    }

    public function listProductionWastesByWorkOrder(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pw.*, r.roll_code
             FROM production_wastes pw
             LEFT JOIN rolls r ON r.id = pw.roll_id
             WHERE pw.work_order_id = :wo
             ORDER BY pw.id DESC'
        );
        $stmt->execute([':wo' => $workOrderId]);
        return $stmt->fetchAll();
    }

    public function createProductionWaste(int $workOrderId, ?int $rollId, string $stage, string $reason, float $weightKg, string $operatorName): array
    {
        $stage = strtoupper(trim($stage));
        $reason = trim($reason);
        $operatorName = trim($operatorName);
        $errors = [];

        if ($workOrderId <= 0 || $this->getWorkOrder($workOrderId) === null) {
            $errors['work_order_id'] = 'OT no existe.';
        }
        if (!in_array($stage, ['PRODUCTION', 'CUT', 'SEALING'], true)) {
            $errors['waste_stage'] = 'Etapa de merma inválida.';
        }
        if ($reason === '') {
            $errors['reason'] = 'Motivo de merma es obligatorio.';
        }
        if ($weightKg <= 0) {
            $errors['weight_kg'] = 'Peso de merma debe ser mayor a 0.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO production_wastes (work_order_id, roll_id, waste_stage, reason, weight_kg, operator_name)
             VALUES (:wo, :roll, :stage, :reason, :weight, :operator)'
        );
        $stmt->execute([
            ':wo' => $workOrderId,
            ':roll' => $rollId !== null && $rollId > 0 ? $rollId : null,
            ':stage' => $stage,
            ':reason' => $reason,
            ':weight' => number_format($weightKg, 3, '.', ''),
            ':operator' => $operatorName,
        ]);

        $wasteId = (int)$this->pdo->lastInsertId();
        $this->insertEvent('PRODUCTION_WASTE_RECORDED', [
            'work_order_id' => $workOrderId,
            'waste_id' => $wasteId,
            'roll_id' => $rollId,
            'waste_stage' => $stage,
            'reason' => $reason,
            'weight_kg' => round($weightKg, 3),
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'errors' => [], 'waste_id' => $wasteId];
    }

    public function isWorkOrderInSealingStage(int $workOrderId): bool
    {
        $workOrder = $this->getWorkOrder($workOrderId);
        if ($workOrder === null) {
            return false;
        }
        if (strtoupper(trim((string)($workOrder['status'] ?? ''))) === 'CLOSED') {
            return false;
        }

        $finishApproval = $this->getLastWorkOrderFinishApproval($workOrderId);
        if ($finishApproval === null) {
            return false;
        }

        $sealingFinish = $this->getLastWorkOrderSealingFinish($workOrderId);
        if ($sealingFinish === null) {
            return true;
        }

        $sealingSetup = $this->getLastWorkOrderSealingSetupApproval($workOrderId);
        if ($sealingSetup === null) {
            return false;
        }

        $finishTs = strtotime((string)($sealingFinish['created_at'] ?? ''));
        $setupTs = strtotime((string)($sealingSetup['created_at'] ?? ''));
        if ($finishTs === false || $setupTs === false) {
            return false;
        }

        return $finishTs < $setupTs;
    }

    private function listWorkOrderRollEvents(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, type,
                    CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.roll_id")) AS UNSIGNED) AS roll_id
             FROM events
             WHERE type IN ("WORK_ORDER_ROLL_ATTACHED","WORK_ORDER_ROLL_RELEASED")
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY id ASC'
        );
        $stmt->execute([':wo' => $workOrderId]);
        return $stmt->fetchAll();
    }

    public function attachRollToWorkOrder(int $workOrderId, int $rollId, float $processWeightKg, float $wasteKg, string $operatorName): array
    {
        $errors = [];
        $operatorName = trim($operatorName);
        $workOrder = $this->getWorkOrder($workOrderId);
        $roll = $this->getRoll($rollId);
        $productionWarehouseId = $this->findWarehouseIdByCode(3000);

        if ($workOrder === null) {
            $errors['work_order_id'] = 'OT no existe.';
        } elseif (in_array((string)$workOrder['status'], ['CLOSED', 'CUTTING'], true)) {
            $errors['work_order_id'] = 'La OT está cerrada.';
        }
        if ($roll === null) {
            $errors['roll_id'] = 'Bobina no existe.';
        }
        if ($processWeightKg <= 0) {
            $errors['process_weight_kg'] = 'Peso de proceso debe ser mayor a 0.';
        }
        if ($wasteKg < 0) {
            $errors['waste_kg'] = 'Merma no puede ser negativa.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($productionWarehouseId === null) {
            $errors['warehouse_id'] = 'No existe la bodega 3000 de producción.';
        }
        if ($this->getCurrentRollInWorkOrder($workOrderId) !== null) {
            $errors['roll_active'] = 'Ya existe una bobina activa en esta OT. Debes cambiarla o finalizar la OT.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $fromWarehouseId = (int)($roll['warehouse_id'] ?? 0);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE rolls SET warehouse_id = :warehouse_id, current_work_order_id = :wo, status = :status WHERE id = :id');
            $stmt->execute([
                ':warehouse_id' => $productionWarehouseId,
                ':wo' => $workOrderId,
                ':status' => 'IN_PROCESS',
                ':id' => $rollId,
            ]);

            if ($fromWarehouseId > 0 && $fromWarehouseId !== $productionWarehouseId) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload)
                     VALUES (:entity_type, :entity_id, :movement_type, :from_warehouse_id, :to_warehouse_id, :payload)'
                );
                $stmt->execute([
                    ':entity_type' => 'ROLL',
                    ':entity_id' => $rollId,
                    ':movement_type' => 'TRANSFER',
                    ':from_warehouse_id' => $fromWarehouseId,
                    ':to_warehouse_id' => $productionWarehouseId,
                    ':payload' => json_encode([
                        'operator_name' => $operatorName,
                        'work_order_id' => $workOrderId,
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                $this->insertEvent('ROLL_TRANSFERRED', [
                    'roll_id' => $rollId,
                    'from_warehouse_id' => $fromWarehouseId,
                    'to_warehouse_id' => $productionWarehouseId,
                    'operator_name' => $operatorName,
                    'work_order_id' => $workOrderId,
                ]);
            }

            $this->insertEvent('WORK_ORDER_ROLL_ATTACHED', [
                'work_order_id' => $workOrderId,
                'roll_id' => $rollId,
                'process_weight_kg' => round($processWeightKg, 3),
                'waste_kg' => round($wasteKg, 3),
                'operator_name' => $operatorName,
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['ok' => true, 'errors' => []];
    }

    public function changeRollInWorkOrder(int $workOrderId, int $nextRollId, float $currentFinalWeightKg, float $currentWasteKg, float $outputRollWeightKg, float $nextProcessWeightKg, float $nextWasteKg, string $operatorName): array
    {
        $errors = [];
        $operatorName = trim($operatorName);
        $workOrder = $this->getWorkOrder($workOrderId);
        $currentRoll = $this->getCurrentRollInWorkOrder($workOrderId);
        $nextRoll = $this->getRoll($nextRollId);
        $productionWarehouseId = $this->findWarehouseIdByCode(3000);

        if ($workOrder === null) {
            $errors['work_order_id'] = 'OT no existe.';
        } elseif (in_array((string)$workOrder['status'], ['CLOSED', 'CUTTING'], true)) {
            $errors['work_order_id'] = 'La OT está cerrada.';
        } elseif ($this->getLastWorkOrderStart($workOrderId) === null) {
            $errors['work_order_started'] = 'Debes iniciar la OT antes de hacer cambio de bobina.';
        }
        if ($currentRoll === null) {
            $errors['current_roll'] = 'No hay una bobina activa para cambiar.';
        }
        if ($nextRoll === null) {
            $errors['next_roll'] = 'La nueva bobina no existe.';
        }
        if ($currentRoll !== null && $nextRoll !== null && (int)$currentRoll['id'] === (int)$nextRoll['id']) {
            $errors['next_roll'] = 'La nueva bobina debe ser distinta a la actual.';
        }
        if ($currentFinalWeightKg < 0) {
            $errors['current_final_weight_kg'] = 'Peso final de la bobina actual no puede ser negativo.';
        }
        if ($currentWasteKg < 0) {
            $errors['current_waste_kg'] = 'Merma de la bobina actual no puede ser negativa.';
        }
        if ($outputRollWeightKg <= 0) {
            $errors['output_roll_weight_kg'] = 'Peso de la bobina salida debe ser mayor a 0.';
        }
        if ($nextProcessWeightKg <= 0) {
            $errors['next_process_weight_kg'] = 'Peso inicial de la nueva bobina debe ser mayor a 0.';
        }
        if ($nextWasteKg < 0) {
            $errors['next_waste_kg'] = 'Merma de la nueva bobina no puede ser negativa.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($productionWarehouseId === null) {
            $errors['warehouse_id'] = 'No existe la bodega 3000 de producción.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE rolls SET weight_kg = :weight_kg, current_work_order_id = NULL, status = :status WHERE id = :id');
            $stmt->execute([
                ':weight_kg' => round($currentFinalWeightKg, 3),
                ':status' => $currentFinalWeightKg > 0 ? 'RECEIVED' : 'CONSUMED',
                ':id' => (int)$currentRoll['id'],
            ]);

            $this->insertEvent('WORK_ORDER_ROLL_RELEASED', [
                'work_order_id' => $workOrderId,
                'roll_id' => (int)$currentRoll['id'],
                'final_weight_kg' => round($currentFinalWeightKg, 3),
                'waste_kg' => round($currentWasteKg, 3),
                'reason' => 'CHANGE',
                'operator_name' => $operatorName,
            ]);

            $outputRollId = $this->createOutputRollFromWorkOrder(
                $workOrder,
                $currentRoll,
                $outputRollWeightKg,
                $operatorName
            );

            $fromWarehouseId = (int)($nextRoll['warehouse_id'] ?? 0);
            $stmt = $this->pdo->prepare('UPDATE rolls SET warehouse_id = :warehouse_id, current_work_order_id = :wo, status = :status WHERE id = :id');
            $stmt->execute([
                ':warehouse_id' => $productionWarehouseId,
                ':wo' => $workOrderId,
                ':status' => 'IN_PROCESS',
                ':id' => $nextRollId,
            ]);

            if ($fromWarehouseId > 0 && $fromWarehouseId !== $productionWarehouseId) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload)
                     VALUES (:entity_type, :entity_id, :movement_type, :from_warehouse_id, :to_warehouse_id, :payload)'
                );
                $stmt->execute([
                    ':entity_type' => 'ROLL',
                    ':entity_id' => $nextRollId,
                    ':movement_type' => 'TRANSFER',
                    ':from_warehouse_id' => $fromWarehouseId,
                    ':to_warehouse_id' => $productionWarehouseId,
                    ':payload' => json_encode([
                        'operator_name' => $operatorName,
                        'work_order_id' => $workOrderId,
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                $this->insertEvent('ROLL_TRANSFERRED', [
                    'roll_id' => $nextRollId,
                    'from_warehouse_id' => $fromWarehouseId,
                    'to_warehouse_id' => $productionWarehouseId,
                    'operator_name' => $operatorName,
                    'work_order_id' => $workOrderId,
                ]);
            }

            $this->insertEvent('WORK_ORDER_ROLL_ATTACHED', [
                'work_order_id' => $workOrderId,
                'roll_id' => $nextRollId,
                'process_weight_kg' => round($nextProcessWeightKg, 3),
                'waste_kg' => round($nextWasteKg, 3),
                'operator_name' => $operatorName,
            ]);

            $this->pdo->commit();
            return [
                'ok' => true,
                'errors' => [],
                'output_roll_id' => $outputRollId,
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function finishWorkOrder(
        int $workOrderId,
        float $finalRollWeightKg,
        float $finalChemicalWeightKg,
        float $wasteKg,
        int $boxQty,
        float $outputRollWeightKg,
        string $operatorName,
        float $productionMeters = 0,
        string $comments = ''
    ): array
    {
        $errors = [];
        $operatorName = trim($operatorName);
        $comments = trim($comments);
        $workOrder = $this->getWorkOrder($workOrderId);
        $currentRoll = $this->getCurrentRollInWorkOrder($workOrderId);
        $sourceRoll = $currentRoll;
        if ($sourceRoll === null) {
            foreach ($this->listWorkOrderRollHistory($workOrderId) as $historyRow) {
                $historyRollId = (int)($historyRow['roll_id'] ?? 0);
                if ($historyRollId <= 0) {
                    continue;
                }
                $historyRoll = $this->getRoll($historyRollId);
                if ($historyRoll !== null) {
                    $sourceRoll = $historyRoll;
                    break;
                }
            }
        }

        if ($workOrder === null) {
            $errors['work_order_id'] = 'OT no existe.';
        }
        if ($workOrder !== null && in_array((string)$workOrder['status'], ['CUTTING', 'CLOSED'], true)) {
            $errors['work_order_id'] = 'La producción ya fue cerrada para esta OT.';
        }
        if ($sourceRoll === null) {
            $errors['roll_id'] = 'No hay bobinas registradas para cerrar la producción.';
        }
        if ($finalRollWeightKg < 0) {
            $errors['final_roll_weight_kg'] = 'Peso final de la bobina no puede ser negativo.';
        }
        if ($finalChemicalWeightKg < 0) {
            $errors['final_chemical_weight_kg'] = 'Peso final de los químicos no puede ser negativo.';
        }
        if ($wasteKg < 0) {
            $errors['waste_kg'] = 'Merma no puede ser negativa.';
        }
        if ($boxQty < 0) {
            $errors['box_qty'] = 'Cantidad de cajas no puede ser negativa.';
        }
        if ($outputRollWeightKg <= 0) {
            $errors['output_roll_weight_kg'] = 'Peso de la nueva bobina debe ser mayor a 0.';
        }
        if ($productionMeters < 0) {
            $errors['production_meters'] = 'Los metros producidos no pueden ser negativos.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->pdo->beginTransaction();
        try {
            if ($currentRoll !== null) {
                $stmt = $this->pdo->prepare('UPDATE rolls SET weight_kg = :weight_kg, current_work_order_id = NULL, status = :status WHERE id = :id');
                $stmt->execute([
                    ':weight_kg' => round($finalRollWeightKg, 3),
                    ':status' => $finalRollWeightKg > 0 ? 'RECEIVED' : 'CONSUMED',
                    ':id' => (int)$currentRoll['id'],
                ]);

                $this->insertEvent('WORK_ORDER_ROLL_RELEASED', [
                    'work_order_id' => $workOrderId,
                    'roll_id' => (int)$currentRoll['id'],
                    'final_weight_kg' => round($finalRollWeightKg, 3),
                    'waste_kg' => round($wasteKg, 3),
                    'reason' => 'FINISH',
                    'operator_name' => $operatorName,
                ]);
            }

            $outputRollId = $this->createOutputRollFromWorkOrder(
                $workOrder,
                $sourceRoll,
                $outputRollWeightKg,
                $operatorName
            );

            $stmt = $this->pdo->prepare('UPDATE work_orders SET status = :status WHERE id = :id');
            $stmt->execute([
                ':status' => 'CUTTING',
                ':id' => $workOrderId,
            ]);
            if ((int)$this->getAppSetting('active_work_order_id', '0') === $workOrderId) {
                $this->setAppSetting('active_work_order_id', '0');
            }
            $this->releaseActiveShiftSessionFromWorkOrder($workOrderId, $operatorName);

            $this->insertEvent('WORK_ORDER_FINISHED', [
                'work_order_id' => $workOrderId,
                'roll_id' => (int)($sourceRoll['id'] ?? 0),
                'final_roll_weight_kg' => round($finalRollWeightKg, 3),
                'final_chemical_weight_kg' => round($finalChemicalWeightKg, 3),
                'production_meters' => round($productionMeters, 3),
                'comments' => $comments,
                'box_qty' => $boxQty,
                'output_roll_id' => $outputRollId,
                'output_roll_weight_kg' => round($outputRollWeightKg, 3),
                'waste_kg' => round($wasteKg, 3),
                'operator_name' => $operatorName,
            ]);

            $this->pdo->commit();
            return [
                'ok' => true,
                'errors' => [],
                'roll_id' => (int)($sourceRoll['id'] ?? 0),
                'output_roll_id' => $outputRollId,
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function listProducedRollsReadyForCut(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.roll_code, r.weight_kg, r.process_stage, r.created_at,
                    w.code AS warehouse_code, w.name AS warehouse_name,
                    s.code AS sku_code, s.description AS sku_description,
                    wo.ot_code, wo.sku_final
             FROM rolls r
             JOIN warehouses w ON w.id = r.warehouse_id
             JOIN skus s ON s.id = r.sku_id
             LEFT JOIN work_orders wo ON wo.id = r.source_work_order_id
             WHERE r.process_stage = "PRINTED"
               AND r.status <> "CONSUMED"
             ORDER BY r.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function processCutRoll(
        int $sourceRollId,
        int $unitsTotal,
        int $boxQty,
        int $boxesPerPallet,
        string $destinationMode,
        ?string $customerOrderRef,
        ?int $warehouseId,
        string $operatorName
    ): array {
        $destinationMode = strtoupper(trim($destinationMode));
        $customerOrderRef = trim((string)$customerOrderRef);
        $operatorName = trim($operatorName);
        $sourceRoll = $this->getRoll($sourceRollId);
        $workOrderId = $sourceRoll !== null ? (int)($sourceRoll['source_work_order_id'] ?? 0) : 0;
        $errors = [];

        if ($sourceRoll === null) {
            $errors['source_roll_id'] = 'Bobina de corte no existe.';
        } elseif ((string)($sourceRoll['process_stage'] ?? 'RAW') !== 'PRINTED') {
            $errors['source_roll_id'] = 'La bobina debe provenir de producción para pasar a corte.';
        }
        if ($workOrderId > 0 && $this->getLastWorkOrderFinishApproval($workOrderId) === null) {
            $errors['finish_approval'] = 'Debes validar el cierre de flexografia con supervisor antes de pasar a la siguiente maquina.';
        }
        if ($unitsTotal <= 0) {
            $errors['units_total'] = 'Unidades totales debe ser mayor a 0.';
        }
        if ($boxQty <= 0) {
            $errors['box_qty'] = 'Cantidad de cajas debe ser mayor a 0.';
        }
        if ($boxesPerPallet <= 0) {
            $boxesPerPallet = $boxQty;
        }
        if (!in_array($destinationMode, ['STOCK', 'CUSTOMER_ORDER'], true)) {
            $errors['destination_mode'] = 'Destino de corte inválido.';
        }
        if ($destinationMode === 'CUSTOMER_ORDER' && $customerOrderRef === '') {
            $errors['customer_order_ref'] = 'Debes indicar la orden de compra del cliente.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $workOrderId = isset($sourceRoll['source_work_order_id']) ? (int)$sourceRoll['source_work_order_id'] : null;
        $finalSku = (string)($sourceRoll['work_order_sku_final'] ?? $sourceRoll['sku_description'] ?? '');
        $palletCount = (int)ceil($boxQty / max(1, $boxesPerPallet));
        $unitsBase = intdiv($unitsTotal, $boxQty);
        $unitsRemainder = $unitsTotal % $boxQty;
        $initialWarehouseId = $destinationMode === 'CUSTOMER_ORDER' && $warehouseId !== null && $warehouseId > 0
            ? $warehouseId
            : null;

        $this->pdo->beginTransaction();
        try {
            $palletIds = [];
            for ($i = 0; $i < $palletCount; $i++) {
                $palletCode = $this->generatePalletCode();
                $stmt = $this->pdo->prepare(
                    'INSERT INTO pallets (pallet_code, work_order_id, source_roll_id, final_sku, destination_mode, customer_order_ref, warehouse_id, box_count, operator_name, status)
                     VALUES (:pallet_code, :work_order_id, :source_roll_id, :final_sku, :destination_mode, :customer_order_ref, :warehouse_id, 0, :operator_name, :status)'
                );
                $stmt->execute([
                    ':pallet_code' => $palletCode,
                    ':work_order_id' => $workOrderId,
                    ':source_roll_id' => $sourceRollId,
                    ':final_sku' => $finalSku,
                    ':destination_mode' => $destinationMode,
                    ':customer_order_ref' => $customerOrderRef !== '' ? $customerOrderRef : null,
                    ':warehouse_id' => $initialWarehouseId,
                    ':operator_name' => $operatorName,
                    ':status' => 'CREATED',
                ]);
                $palletIds[] = (int)$this->pdo->lastInsertId();
            }

            $createdBoxes = [];
            $palletBoxCount = array_fill_keys($palletIds, 0);
            for ($i = 1; $i <= $boxQty; $i++) {
                $boxCode = $this->generateBoxCode();
                $palletIndex = (int)floor(($i - 1) / max(1, $boxesPerPallet));
                $palletId = $palletIds[min($palletIndex, max(0, count($palletIds) - 1))] ?? null;
                $unitsQty = $unitsBase + ($i <= $unitsRemainder ? 1 : 0);

                $stmt = $this->pdo->prepare(
                    'INSERT INTO boxes (box_code, work_order_id, source_roll_id, pallet_id, final_sku, units_qty, destination_mode, customer_order_ref, warehouse_id, operator_name, status)
                     VALUES (:box_code, :work_order_id, :source_roll_id, :pallet_id, :final_sku, :units_qty, :destination_mode, :customer_order_ref, :warehouse_id, :operator_name, :status)'
                );
                $stmt->execute([
                    ':box_code' => $boxCode,
                    ':work_order_id' => $workOrderId,
                    ':source_roll_id' => $sourceRollId,
                    ':pallet_id' => $palletId,
                    ':final_sku' => $finalSku,
                    ':units_qty' => number_format((float)$unitsQty, 3, '.', ''),
                    ':destination_mode' => $destinationMode,
                    ':customer_order_ref' => $customerOrderRef !== '' ? $customerOrderRef : null,
                    ':warehouse_id' => $initialWarehouseId,
                    ':operator_name' => $operatorName,
                    ':status' => 'CREATED',
                ]);
                $boxId = (int)$this->pdo->lastInsertId();
                $createdBoxes[] = ['id' => $boxId, 'code' => $boxCode, 'pallet_id' => $palletId, 'units_qty' => $unitsQty];
                if ($palletId !== null) {
                    $palletBoxCount[$palletId] = ($palletBoxCount[$palletId] ?? 0) + 1;
                }
            }

            foreach ($palletBoxCount as $palletId => $count) {
                $stmt = $this->pdo->prepare('UPDATE pallets SET box_count = :count WHERE id = :id');
                $stmt->execute([':count' => $count, ':id' => $palletId]);
            }

            $stmt = $this->pdo->prepare('UPDATE rolls SET status = :status, weight_kg = 0, process_stage = :stage WHERE id = :id');
            $stmt->execute([
                ':status' => 'CONSUMED',
                ':stage' => 'CUT',
                ':id' => $sourceRollId,
            ]);

            $this->insertEvent('CUT_COMPLETED', [
                'work_order_id' => $workOrderId,
                'roll_id' => $sourceRollId,
                'units_total' => $unitsTotal,
                'box_qty' => $boxQty,
                'pallet_qty' => $palletCount,
                'destination_mode' => $destinationMode,
                'customer_order_ref' => $customerOrderRef,
                'warehouse_id' => $warehouseId,
                'operator_name' => $operatorName,
            ]);

            if ($workOrderId !== null && $workOrderId > 0 && !$this->hasPendingCutRollsForWorkOrder($workOrderId)) {
                $stmt = $this->pdo->prepare('UPDATE work_orders SET status = :status WHERE id = :id');
                $stmt->execute([
                    ':status' => 'CLOSED',
                    ':id' => $workOrderId,
                ]);
            }

            $this->pdo->commit();
            return [
                'ok' => true,
                'errors' => [],
                'boxes' => $createdBoxes,
                'pallet_ids' => $palletIds,
            ];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function hasPendingCutRollsForWorkOrder(int $workOrderId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM rolls
             WHERE source_work_order_id = :wo
               AND process_stage = "PRINTED"
               AND status <> "CONSUMED"'
        );
        $stmt->execute([':wo' => $workOrderId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function listBoxesByWorkOrder(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT b.*, p.pallet_code, r.roll_code AS source_roll_code
             FROM boxes b
             LEFT JOIN pallets p ON p.id = b.pallet_id
             LEFT JOIN rolls r ON r.id = b.source_roll_id
             WHERE b.work_order_id = :wo
             ORDER BY b.id DESC'
        );
        $stmt->execute([':wo' => $workOrderId]);
        return $stmt->fetchAll();
    }

    public function listPalletsByWorkOrder(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, r.roll_code AS source_roll_code
             FROM pallets p
             LEFT JOIN rolls r ON r.id = p.source_roll_id
             WHERE p.work_order_id = :wo
             ORDER BY p.id DESC'
        );
        $stmt->execute([':wo' => $workOrderId]);
        return $stmt->fetchAll();
    }

    public function listBoxesBySourceRoll(int $sourceRollId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT b.*, p.pallet_code
             FROM boxes b
             LEFT JOIN pallets p ON p.id = b.pallet_id
             WHERE b.source_roll_id = :roll
             ORDER BY b.id DESC'
        );
        $stmt->execute([':roll' => $sourceRollId]);
        return $stmt->fetchAll();
    }

    public function listPalletsBySourceRoll(int $sourceRollId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM pallets
             WHERE source_roll_id = :roll
             ORDER BY id DESC'
        );
        $stmt->execute([':roll' => $sourceRollId]);
        return $stmt->fetchAll();
    }

    public function getBox(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT b.*, p.pallet_code, r.roll_code AS source_roll_code, wo.ot_code,
                    w.code AS warehouse_code, w.name AS warehouse_name
             FROM boxes b
             LEFT JOIN pallets p ON p.id = b.pallet_id
             LEFT JOIN rolls r ON r.id = b.source_roll_id
             LEFT JOIN work_orders wo ON wo.id = b.work_order_id
             LEFT JOIN warehouses w ON w.id = b.warehouse_id
             WHERE b.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getPallet(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, r.roll_code AS source_roll_code, wo.ot_code,
                    w.code AS warehouse_code, w.name AS warehouse_name
             FROM pallets p
             LEFT JOIN rolls r ON r.id = p.source_roll_id
             LEFT JOIN work_orders wo ON wo.id = p.work_order_id
             LEFT JOIN warehouses w ON w.id = p.warehouse_id
             WHERE p.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listPallets(?string $search = null, ?int $warehouseId = null, bool $onlyPendingAssignment = false): array
    {
        $this->syncWarehousesFromErp();
        $where = [];
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $where[] = '(p.pallet_code LIKE :q
                OR COALESCE(wo.ot_code, "") LIKE :q
                OR COALESCE(r.roll_code, "") LIKE :q
                OR COALESCE(p.final_sku, "") LIKE :q
                OR COALESCE(p.customer_order_ref, "") LIKE :q)';
            $params[':q'] = '%' . trim($search) . '%';
        }

        if ($warehouseId !== null && $warehouseId > 0) {
            $where[] = 'p.warehouse_id = :warehouse_id';
            $params[':warehouse_id'] = $warehouseId;
        }
        if ($onlyPendingAssignment) {
            $where[] = '(p.warehouse_id IS NULL OR COALESCE(p.status, "") NOT IN ("STORED","IN_MAQUILA"))';
        }

        $sql = 'SELECT p.*, r.roll_code AS source_roll_code, wo.ot_code,
                       w.code AS warehouse_code, w.name AS warehouse_name,
                       COALESCE(box_stats.units_total, 0) AS units_total
                FROM pallets p
                LEFT JOIN rolls r ON r.id = p.source_roll_id
                LEFT JOIN work_orders wo ON wo.id = p.work_order_id
                LEFT JOIN warehouses w ON w.id = p.warehouse_id
                LEFT JOIN (
                    SELECT pallet_id, COALESCE(SUM(units_qty), 0) AS units_total
                    FROM boxes
                    WHERE pallet_id IS NOT NULL
                    GROUP BY pallet_id
                ) box_stats ON box_stats.pallet_id = p.id';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function listBoxesByPallet(int $palletId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT b.*, w.code AS warehouse_code, w.name AS warehouse_name
             FROM boxes b
             LEFT JOIN warehouses w ON w.id = b.warehouse_id
             WHERE b.pallet_id = :pallet
             ORDER BY b.id ASC'
        );
        $stmt->execute([':pallet' => $palletId]);
        return $stmt->fetchAll();
    }

    private function getPalletBoxStats(int $palletId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS box_count, COALESCE(SUM(units_qty), 0) AS units_total
             FROM boxes
             WHERE pallet_id = :pallet'
        );
        $stmt->execute([':pallet' => $palletId]);
        $row = $stmt->fetch();

        return [
            'box_count' => (int)($row['box_count'] ?? 0),
            'units_total' => (float)($row['units_total'] ?? 0),
        ];
    }

    public function listPalletsAvailableForMaquila(int $limit = 200): array
    {
        $this->syncWarehousesFromErp();
        $stmt = $this->pdo->prepare(
            'SELECT p.*, r.roll_code AS source_roll_code, wo.ot_code,
                    w.code AS warehouse_code, w.name AS warehouse_name,
                    COALESCE(box_stats.units_total, 0) AS units_total,
                    active_order.id AS active_maquila_order_id
             FROM pallets p
             LEFT JOIN rolls r ON r.id = p.source_roll_id
             LEFT JOIN work_orders wo ON wo.id = p.work_order_id
             LEFT JOIN warehouses w ON w.id = p.warehouse_id
             LEFT JOIN (
                SELECT pallet_id, COALESCE(SUM(units_qty), 0) AS units_total
                FROM boxes
                WHERE pallet_id IS NOT NULL
                GROUP BY pallet_id
             ) box_stats ON box_stats.pallet_id = p.id
             LEFT JOIN maquila_orders active_order
               ON active_order.pallet_id = p.id
              AND active_order.status IN ("OPEN","PARTIAL")
             WHERE active_order.id IS NULL
               AND p.warehouse_id IS NOT NULL
               AND COALESCE(w.code, 0) <> 2000
             ORDER BY p.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listMaquilaOrders(?string $status = null): array
    {
        $this->syncWarehousesFromErp();
        $params = [];
        $where = [];
        $normalizedStatus = strtoupper(trim((string)$status));
        if ($normalizedStatus !== '' && $normalizedStatus !== 'ALL') {
            $where[] = 'mo.status = :status';
            $params[':status'] = $normalizedStatus;
        }

        $sql = 'SELECT mo.*,
                       p.pallet_code, p.final_sku, p.box_count AS pallet_box_count,
                       wo.ot_code,
                       r.roll_code AS source_roll_code,
                       ow.code AS outgoing_warehouse_code, ow.name AS outgoing_warehouse_name,
                       ew.code AS external_warehouse_code, ew.name AS external_warehouse_name,
                       rw.code AS return_warehouse_code, rw.name AS return_warehouse_name,
                       COALESCE(ret.returned_weight_kg, 0) AS returned_weight_kg,
                       COALESCE(ret.returned_box_count, 0) AS returned_box_count,
                       COALESCE(ret.returned_units_qty, 0) AS returned_units_qty,
                       COALESCE(ret.waste_weight_kg, 0) AS waste_weight_kg
                FROM maquila_orders mo
                INNER JOIN pallets p ON p.id = mo.pallet_id
                LEFT JOIN work_orders wo ON wo.id = mo.work_order_id
                LEFT JOIN rolls r ON r.id = mo.source_roll_id
                LEFT JOIN warehouses ow ON ow.id = mo.outgoing_warehouse_id
                LEFT JOIN warehouses ew ON ew.id = mo.external_warehouse_id
                LEFT JOIN warehouses rw ON rw.id = mo.return_warehouse_id
                LEFT JOIN (
                    SELECT maquila_order_id,
                           COALESCE(SUM(return_weight_kg), 0) AS returned_weight_kg,
                           COALESCE(SUM(returned_box_count), 0) AS returned_box_count,
                           COALESCE(SUM(returned_units_qty), 0) AS returned_units_qty,
                           COALESCE(SUM(waste_weight_kg), 0) AS waste_weight_kg
                    FROM maquila_order_returns
                    GROUP BY maquila_order_id
                ) ret ON ret.maquila_order_id = mo.id';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY FIELD(mo.status, "OPEN", "PARTIAL", "RETURNED", "CANCELLED"), mo.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getMaquilaOrder(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT mo.*,
                    p.pallet_code, p.final_sku, p.box_count AS pallet_box_count,
                    wo.ot_code,
                    r.roll_code AS source_roll_code,
                    ow.code AS outgoing_warehouse_code, ow.name AS outgoing_warehouse_name,
                    ew.code AS external_warehouse_code, ew.name AS external_warehouse_name,
                    rw.code AS return_warehouse_code, rw.name AS return_warehouse_name,
                    COALESCE(ret.returned_weight_kg, 0) AS returned_weight_kg,
                    COALESCE(ret.returned_box_count, 0) AS returned_box_count,
                    COALESCE(ret.returned_units_qty, 0) AS returned_units_qty,
                    COALESCE(ret.waste_weight_kg, 0) AS waste_weight_kg
             FROM maquila_orders mo
             INNER JOIN pallets p ON p.id = mo.pallet_id
             LEFT JOIN work_orders wo ON wo.id = mo.work_order_id
             LEFT JOIN rolls r ON r.id = mo.source_roll_id
             LEFT JOIN warehouses ow ON ow.id = mo.outgoing_warehouse_id
             LEFT JOIN warehouses ew ON ew.id = mo.external_warehouse_id
             LEFT JOIN warehouses rw ON rw.id = mo.return_warehouse_id
             LEFT JOIN (
                SELECT maquila_order_id,
                       COALESCE(SUM(return_weight_kg), 0) AS returned_weight_kg,
                       COALESCE(SUM(returned_box_count), 0) AS returned_box_count,
                       COALESCE(SUM(returned_units_qty), 0) AS returned_units_qty,
                       COALESCE(SUM(waste_weight_kg), 0) AS waste_weight_kg
                FROM maquila_order_returns
                GROUP BY maquila_order_id
             ) ret ON ret.maquila_order_id = mo.id
             WHERE mo.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listMaquilaOrdersByPallet(int $palletId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT mo.*,
                    COALESCE(ret.returned_weight_kg, 0) AS returned_weight_kg,
                    COALESCE(ret.returned_box_count, 0) AS returned_box_count,
                    COALESCE(ret.returned_units_qty, 0) AS returned_units_qty,
                    COALESCE(ret.waste_weight_kg, 0) AS waste_weight_kg
             FROM maquila_orders mo
             LEFT JOIN (
                SELECT maquila_order_id,
                       COALESCE(SUM(return_weight_kg), 0) AS returned_weight_kg,
                       COALESCE(SUM(returned_box_count), 0) AS returned_box_count,
                       COALESCE(SUM(returned_units_qty), 0) AS returned_units_qty,
                       COALESCE(SUM(waste_weight_kg), 0) AS waste_weight_kg
                FROM maquila_order_returns
                GROUP BY maquila_order_id
             ) ret ON ret.maquila_order_id = mo.id
             WHERE mo.pallet_id = :pallet_id
             ORDER BY mo.id DESC'
        );
        $stmt->execute([':pallet_id' => $palletId]);
        return $stmt->fetchAll();
    }

    public function getOpenMaquilaOrderByPallet(int $palletId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, status
             FROM maquila_orders
             WHERE pallet_id = :pallet_id
               AND status IN ("OPEN","PARTIAL")
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([':pallet_id' => $palletId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listMaquilaReturns(int $orderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM maquila_order_returns
             WHERE maquila_order_id = :order_id
             ORDER BY id DESC'
        );
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll();
    }

    public function createMaquilaOrder(int $palletId, string $workshopName, float $outgoingWeightKg, string $notes, string $operatorName): array
    {
        $workshopName = trim($workshopName);
        $notes = trim($notes);
        $operatorName = trim($operatorName);
        $errors = [];

        $pallet = $this->getPallet($palletId);
        if ($pallet === null) {
            $errors['pallet_id'] = 'El pallet seleccionado no existe.';
        }
        if ($workshopName === '') {
            $errors['workshop_name'] = 'Debes indicar el taller externo.';
        }
        if ($outgoingWeightKg <= 0) {
            $errors['outgoing_weight_kg'] = 'El peso de salida debe ser mayor a 0.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }

        $activeOrder = $palletId > 0 ? $this->getOpenMaquilaOrderByPallet($palletId) : null;
        if ($activeOrder !== null) {
            $errors['pallet_id'] = 'El pallet ya tiene una orden activa de maquila.';
        }

        $this->syncWarehousesFromErp();
        $externalWarehouseId = $this->findWarehouseIdByCode(2000);
        $returnWarehouseId = $this->findWarehouseIdByCode(400);
        if ($externalWarehouseId === null) {
            $errors['external_warehouse'] = 'No existe la bodega 2000 para talleres externos.';
        }
        if ($returnWarehouseId === null) {
            $errors['return_warehouse'] = 'No existe la bodega 400 para retorno de maquila.';
        }

        if ($pallet !== null) {
            $currentWarehouseId = (int)($pallet['warehouse_id'] ?? 0);
            $currentWarehouseCode = (int)($pallet['warehouse_code'] ?? 0);
            if ($currentWarehouseId <= 0) {
                $errors['pallet_id'] = 'El pallet debe estar asignado a una bodega interna antes de salir a maquila.';
            } elseif ($currentWarehouseCode === 2000) {
                $errors['pallet_id'] = 'El pallet ya está en talleres externos.';
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $currentWarehouseId = (int)$pallet['warehouse_id'];
        $boxStats = $this->getPalletBoxStats($palletId);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO maquila_orders (
                    pallet_id, work_order_id, source_roll_id, workshop_name,
                    outgoing_weight_kg, outgoing_box_count, outgoing_units_qty,
                    outgoing_warehouse_id, external_warehouse_id, return_warehouse_id,
                    status, notes, operator_name
                 ) VALUES (
                    :pallet_id, :work_order_id, :source_roll_id, :workshop_name,
                    :outgoing_weight_kg, :outgoing_box_count, :outgoing_units_qty,
                    :outgoing_warehouse_id, :external_warehouse_id, :return_warehouse_id,
                    :status, :notes, :operator_name
                 )'
            );
            $stmt->execute([
                ':pallet_id' => $palletId,
                ':work_order_id' => (int)($pallet['work_order_id'] ?? 0) > 0 ? (int)$pallet['work_order_id'] : null,
                ':source_roll_id' => (int)($pallet['source_roll_id'] ?? 0) > 0 ? (int)$pallet['source_roll_id'] : null,
                ':workshop_name' => $workshopName,
                ':outgoing_weight_kg' => number_format($outgoingWeightKg, 3, '.', ''),
                ':outgoing_box_count' => (int)$boxStats['box_count'],
                ':outgoing_units_qty' => number_format((float)$boxStats['units_total'], 3, '.', ''),
                ':outgoing_warehouse_id' => $currentWarehouseId,
                ':external_warehouse_id' => $externalWarehouseId,
                ':return_warehouse_id' => $returnWarehouseId,
                ':status' => 'OPEN',
                ':notes' => $notes !== '' ? $notes : null,
                ':operator_name' => $operatorName,
            ]);
            $orderId = (int)$this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare('UPDATE pallets SET warehouse_id = :warehouse_id, status = :status WHERE id = :id');
            $stmt->execute([
                ':warehouse_id' => $externalWarehouseId,
                ':status' => 'IN_MAQUILA',
                ':id' => $palletId,
            ]);

            $stmt = $this->pdo->prepare('UPDATE boxes SET warehouse_id = :warehouse_id, status = :status WHERE pallet_id = :pallet_id');
            $stmt->execute([
                ':warehouse_id' => $externalWarehouseId,
                ':status' => 'IN_MAQUILA',
                ':pallet_id' => $palletId,
            ]);

            $stmt = $this->pdo->prepare(
                'INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload)
                 VALUES (:entity_type, :entity_id, :movement_type, :from_warehouse_id, :to_warehouse_id, :payload)'
            );
            $stmt->execute([
                ':entity_type' => 'PALLET',
                ':entity_id' => $palletId,
                ':movement_type' => 'TRANSFER',
                ':from_warehouse_id' => $currentWarehouseId,
                ':to_warehouse_id' => $externalWarehouseId,
                ':payload' => json_encode([
                    'operator_name' => $operatorName,
                    'movement_context' => 'MAQUILA_OUT',
                    'maquila_order_id' => $orderId,
                    'workshop_name' => $workshopName,
                    'outgoing_weight_kg' => round($outgoingWeightKg, 3),
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $this->insertEvent('MAQUILA_SENT', [
                'maquila_order_id' => $orderId,
                'pallet_id' => $palletId,
                'pallet_code' => (string)($pallet['pallet_code'] ?? ''),
                'work_order_id' => (int)($pallet['work_order_id'] ?? 0) > 0 ? (int)$pallet['work_order_id'] : null,
                'workshop_name' => $workshopName,
                'outgoing_weight_kg' => round($outgoingWeightKg, 3),
                'outgoing_box_count' => (int)$boxStats['box_count'],
                'outgoing_units_qty' => round((float)$boxStats['units_total'], 3),
                'from_warehouse_id' => $currentWarehouseId,
                'to_warehouse_id' => $externalWarehouseId,
                'operator_name' => $operatorName,
                'notes' => $notes,
            ]);

            $this->pdo->commit();
            return ['ok' => true, 'errors' => [], 'maquila_order_id' => $orderId];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function registerMaquilaReturn(
        int $orderId,
        float $returnWeightKg,
        int $returnedBoxCount,
        float $returnedUnitsQty,
        float $wasteWeightKg,
        string $notes,
        string $operatorName
    ): array {
        $notes = trim($notes);
        $operatorName = trim($operatorName);
        $errors = [];

        $order = $this->getMaquilaOrder($orderId);
        if ($order === null) {
            $errors['maquila_order_id'] = 'La orden de maquila no existe.';
        }
        if ($returnWeightKg <= 0 && $wasteWeightKg <= 0) {
            $errors['return_weight_kg'] = 'Debes registrar retorno, merma o ambos.';
        }
        if ($returnedBoxCount < 0) {
            $errors['returned_box_count'] = 'La cantidad de cajas retornadas no puede ser negativa.';
        }
        if ($returnedUnitsQty < 0) {
            $errors['returned_units_qty'] = 'Las unidades retornadas no pueden ser negativas.';
        }
        if ($wasteWeightKg < 0) {
            $errors['waste_weight_kg'] = 'La merma no puede ser negativa.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }

        if ($order !== null && !in_array((string)($order['status'] ?? ''), ['OPEN', 'PARTIAL'], true)) {
            $errors['maquila_order_id'] = 'La orden de maquila ya fue cerrada.';
        }

        $currentReturnedWeight = (float)($order['returned_weight_kg'] ?? 0);
        $currentWasteWeight = (float)($order['waste_weight_kg'] ?? 0);
        $outgoingWeight = (float)($order['outgoing_weight_kg'] ?? 0);
        $remainingWeight = max(0, $outgoingWeight - $currentReturnedWeight - $currentWasteWeight);
        if ($order !== null && ($returnWeightKg + $wasteWeightKg) > ($remainingWeight + 0.0001)) {
            $errors['return_weight_kg'] = 'El retorno más la merma supera el peso pendiente por conciliar.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $nextReturnedWeight = $currentReturnedWeight + $returnWeightKg;
        $nextWasteWeight = $currentWasteWeight + $wasteWeightKg;
        $isFullyReturned = ($nextReturnedWeight + $nextWasteWeight) >= ($outgoingWeight - 0.0001);
        $nextStatus = $isFullyReturned ? 'RETURNED' : 'PARTIAL';

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO maquila_order_returns (
                    maquila_order_id, return_weight_kg, returned_box_count, returned_units_qty,
                    waste_weight_kg, notes, operator_name
                 ) VALUES (
                    :maquila_order_id, :return_weight_kg, :returned_box_count, :returned_units_qty,
                    :waste_weight_kg, :notes, :operator_name
                 )'
            );
            $stmt->execute([
                ':maquila_order_id' => $orderId,
                ':return_weight_kg' => number_format($returnWeightKg, 3, '.', ''),
                ':returned_box_count' => $returnedBoxCount,
                ':returned_units_qty' => number_format($returnedUnitsQty, 3, '.', ''),
                ':waste_weight_kg' => number_format($wasteWeightKg, 3, '.', ''),
                ':notes' => $notes !== '' ? $notes : null,
                ':operator_name' => $operatorName,
            ]);

            $stmt = $this->pdo->prepare(
                'UPDATE maquila_orders
                 SET status = :status,
                     closed_at = :closed_at
                 WHERE id = :id'
            );
            $stmt->execute([
                ':status' => $nextStatus,
                ':closed_at' => $nextStatus === 'RETURNED' ? date('Y-m-d H:i:s') : null,
                ':id' => $orderId,
            ]);

            if ($nextStatus === 'RETURNED') {
                $returnWarehouseId = (int)$order['return_warehouse_id'];
                $externalWarehouseId = (int)$order['external_warehouse_id'];
                $palletId = (int)$order['pallet_id'];

                $stmt = $this->pdo->prepare('UPDATE pallets SET warehouse_id = :warehouse_id, status = :status WHERE id = :id');
                $stmt->execute([
                    ':warehouse_id' => $returnWarehouseId,
                    ':status' => 'MAQUILA_RETURNED',
                    ':id' => $palletId,
                ]);

                $stmt = $this->pdo->prepare('UPDATE boxes SET warehouse_id = :warehouse_id, status = :status WHERE pallet_id = :pallet_id');
                $stmt->execute([
                    ':warehouse_id' => $returnWarehouseId,
                    ':status' => 'MAQUILA_RETURNED',
                    ':pallet_id' => $palletId,
                ]);

                $stmt = $this->pdo->prepare(
                    'INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload)
                     VALUES (:entity_type, :entity_id, :movement_type, :from_warehouse_id, :to_warehouse_id, :payload)'
                );
                $stmt->execute([
                    ':entity_type' => 'PALLET',
                    ':entity_id' => $palletId,
                    ':movement_type' => 'TRANSFER',
                    ':from_warehouse_id' => $externalWarehouseId,
                    ':to_warehouse_id' => $returnWarehouseId,
                    ':payload' => json_encode([
                        'operator_name' => $operatorName,
                        'movement_context' => 'MAQUILA_RETURN',
                        'maquila_order_id' => $orderId,
                        'return_weight_kg' => round($returnWeightKg, 3),
                        'waste_weight_kg' => round($wasteWeightKg, 3),
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }

            $this->insertEvent('MAQUILA_RETURN_RECORDED', [
                'maquila_order_id' => $orderId,
                'pallet_id' => (int)$order['pallet_id'],
                'pallet_code' => (string)($order['pallet_code'] ?? ''),
                'work_order_id' => (int)($order['work_order_id'] ?? 0) > 0 ? (int)$order['work_order_id'] : null,
                'workshop_name' => (string)($order['workshop_name'] ?? ''),
                'return_weight_kg' => round($returnWeightKg, 3),
                'returned_box_count' => $returnedBoxCount,
                'returned_units_qty' => round($returnedUnitsQty, 3),
                'waste_weight_kg' => round($wasteWeightKg, 3),
                'pending_weight_kg' => max(0, round($outgoingWeight - $nextReturnedWeight - $nextWasteWeight, 3)),
                'status' => $nextStatus,
                'operator_name' => $operatorName,
                'notes' => $notes,
            ]);

            $this->pdo->commit();
            return ['ok' => true, 'errors' => []];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function movePalletToWarehouse(int $palletId, int $toWarehouseId, string $operatorName): array
    {
        $operatorName = trim($operatorName);
        $pallet = $this->getPallet($palletId);
        $errors = [];
        $activeMaquilaOrder = $this->getOpenMaquilaOrderByPallet($palletId);

        if ($pallet === null) {
            $errors['pallet_id'] = 'El pallet no existe.';
        }
        if ($toWarehouseId <= 0) {
            $errors['warehouse_id'] = 'Debes seleccionar la bodega destino.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }

        $warehouse = null;
        if ($toWarehouseId > 0) {
            $stmt = $this->pdo->prepare('SELECT id, code, name FROM warehouses WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $toWarehouseId]);
            $warehouse = $stmt->fetch();
            if ($warehouse === false) {
                $errors['warehouse_id'] = 'La bodega destino no existe.';
            }
        }

        if ($pallet !== null && $toWarehouseId > 0 && (int)($pallet['warehouse_id'] ?? 0) === $toWarehouseId) {
            $errors['warehouse_id'] = 'El pallet ya está en esa bodega.';
        }
        if ($activeMaquilaOrder !== null) {
            $errors['pallet_id'] = 'El pallet está con una orden activa de maquila y no se puede mover manualmente.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $fromWarehouseId = isset($pallet['warehouse_id']) ? (int)$pallet['warehouse_id'] : 0;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE pallets SET warehouse_id = :warehouse_id, status = :status WHERE id = :id');
            $stmt->execute([
                ':warehouse_id' => $toWarehouseId,
                ':status' => 'STORED',
                ':id' => $palletId,
            ]);

            $stmt = $this->pdo->prepare('UPDATE boxes SET warehouse_id = :warehouse_id WHERE pallet_id = :pallet_id');
            $stmt->execute([
                ':warehouse_id' => $toWarehouseId,
                ':pallet_id' => $palletId,
            ]);

            $stmt = $this->pdo->prepare(
                'INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload)
                 VALUES (:entity_type, :entity_id, :movement_type, :from_warehouse_id, :to_warehouse_id, :payload)'
            );
            $stmt->execute([
                ':entity_type' => 'PALLET',
                ':entity_id' => $palletId,
                ':movement_type' => 'TRANSFER',
                ':from_warehouse_id' => $fromWarehouseId > 0 ? $fromWarehouseId : null,
                ':to_warehouse_id' => $toWarehouseId,
                ':payload' => json_encode([
                    'operator_name' => $operatorName,
                    'box_count' => (int)($pallet['box_count'] ?? 0),
                    'pallet_code' => (string)($pallet['pallet_code'] ?? ''),
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $this->insertEvent('PALLET_TRANSFERRED', [
                'pallet_id' => $palletId,
                'pallet_code' => (string)($pallet['pallet_code'] ?? ''),
                'from_warehouse_id' => $fromWarehouseId > 0 ? $fromWarehouseId : null,
                'to_warehouse_id' => $toWarehouseId,
                'to_warehouse_code' => (string)($warehouse['code'] ?? ''),
                'to_warehouse_name' => (string)($warehouse['name'] ?? ''),
                'operator_name' => $operatorName,
            ]);

            $this->pdo->commit();
            return ['ok' => true, 'errors' => []];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function listWarehousesForReception(): array
    {
        $this->syncWarehousesFromErp();
        $stmt = $this->pdo->prepare('SELECT id, code, name FROM warehouses ORDER BY code ASC');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listSkus(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, code, description FROM skus WHERE is_active = 1 ORDER BY code ASC');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listAllSkus(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, code, description, is_active, created_at FROM skus ORDER BY code ASC');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createSku(string $code, string $description): array
    {
        $code = trim($code);
        $description = trim($description);

        $errors = [];
        if ($code === '') {
            $errors['code'] = 'Código SKU es obligatorio.';
        }
        if ($description === '') {
            $errors['description'] = 'Descripción SKU es obligatoria.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare('INSERT INTO skus (code, description, is_active) VALUES (:code, :description, 1)');
        try {
            $stmt->execute([':code' => $code, ':description' => $description]);
            $this->insertEvent('SKU_CREATED', ['code' => $code]);
            return ['ok' => true, 'errors' => []];
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'uq_skus_code')) {
                return ['ok' => false, 'errors' => ['code' => 'Este SKU ya existe.']];
            }
            throw $e;
        }
    }

    public function toggleSku(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE skus SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $this->insertEvent('SKU_TOGGLED', ['sku_id' => $id]);
    }

    public function listRecentRolls(int $limit = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.roll_code, r.weight_kg, r.received_qty, r.microns AS grams, r.width_mm, r.color, r.meters, r.status, r.created_at,
                    r.parent_roll_id, r.source_work_order_id, r.process_stage,
                    w.code AS warehouse_code, w.name AS warehouse_name,
                    s.code AS sku_code, s.description AS sku_description
             FROM rolls r
             JOIN warehouses w ON w.id = r.warehouse_id
             JOIN skus s ON s.id = r.sku_id
             ORDER BY r.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // =========================================================================
    // Informe personal por máquina (Maquinarias)
    // =========================================================================

    /**
     * Obtiene las plantas disponibles en el ERP.
     *
     * @return list<array{id: int, planta_name: string}>
     */
    public function getPlantasList(): array
    {
        $table = $this->erpTableExists('plantas') ? 'plantas' : ($this->erpTableExists('planta') ? 'planta' : null);
        if ($table === null) {
            return [];
        }
        try {
            $nameCol = $this->erpColumnExists($table, 'planta_name') ? 'planta_name' : ($this->erpColumnExists($table, 'name') ? 'name' : null);
            if ($nameCol === null) {
                $stmt = $this->erpPdo->query('SELECT id, id AS planta_name FROM ' . $table . ' WHERE id > 0 ORDER BY id');
            } else {
                $stmt = $this->erpPdo->query('SELECT id, ' . $nameCol . ' AS planta_name FROM ' . $table . ' ORDER BY ' . $nameCol);
            }
            return $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Obtiene los tipos de equipo activos en el ERP.
     *
     * @return list<array{id: int, type_ant_title: string}>
     */
    public function getEquipoTypesList(): array
    {
        if (!$this->erpTableExists('equipo_type')) {
            return [];
        }
        try {
            $titleCol = $this->erpColumnExists('equipo_type', 'type_ant_title') ? 'type_ant_title' : ($this->erpColumnExists('equipo_type', 'type_name') ? 'type_name' : null);
            if ($titleCol === null) {
                return [];
            }
            $statusCol = $this->erpColumnExists('equipo_type', 'type_ant_status') ? 'type_ant_status' : null;
            $where = $statusCol !== null ? ('WHERE ' . $statusCol . ' > 0') : '';
            $stmt = $this->erpPdo->query('SELECT id, ' . $titleCol . ' AS type_ant_title FROM equipo_type ' . $where . ' ORDER BY ' . $titleCol);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Obtiene equipos filtrados por planta y opcionalmente por tipo.
     *
     * @return list<array{id: int, equipo_name: string}>
     */
    public function getEquiposByPlantaAndType(int $plantaId, ?int $equipoTypeId = null): array
    {
        if (!$this->erpTableExists('equipo')) {
            return [];
        }
        $nameCol = $this->erpColumnExists('equipo', 'equipo_name') ? 'equipo_name' : ($this->erpColumnExists('equipo', 'name') ? 'name' : null);
        if ($nameCol === null) {
            return [];
        }
        try {
            $sql = 'SELECT id, ' . $nameCol . ' AS equipo_name FROM equipo WHERE equipo_status > 0 AND equipo_planta_id = :planta_id';
            $params = [':planta_id' => $plantaId];
            if ($equipoTypeId !== null && $equipoTypeId > 0) {
                $sql .= ' AND equipo_type_id = :type_id';
                $params[':type_id'] = $equipoTypeId;
            }
            $sql .= ' ORDER BY ' . $nameCol;
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Genera el informe de personal por máquina.
     *
     * Consulta turnos_config_assign para obtener asignaciones de trabajadores a equipos
     * en un rango de fechas, agrupado por equipo. Incluye incidencias de personal.
     *
     * Adaptado del backup: libs/modules/stats/workers/maquina.php
     *
     * @return array{
     *   equipos: list<array{id: int, equipo_name: string}>,
     *   assignments: array<int, list<array<string, mixed>>>,
     *   incidents: array<int, array<string, array<string, mixed>>>,
     *   plantas: list<array>,
     *   equipo_types: list<array>,
     *   equipo_list: list<array>
     * }
     */
    public function getMachineStaffReport(
        string $startAt,
        string $endAt,
        ?int $plantaId = null,
        ?int $equipoTypeId = null,
        ?int $equipoId = null
    ): array {
        $result = [
            'equipos' => [],
            'assignments' => [],
            'incidents' => [],
            'plantas' => $this->getPlantasList(),
            'equipo_types' => $this->getEquipoTypesList(),
            'equipo_list' => [],
        ];

        if (!$this->erpTableExists('equipo') || !$this->erpTableExists('turnos_config_assign')) {
            return $result;
        }

        // Determinar planta por defecto
        if ($plantaId === null || $plantaId <= 0) {
            if (!empty($result['plantas'])) {
                $plantaId = (int)$result['plantas'][0]['id'];
            } else {
                return $result;
            }
        }

        // Obtener equipos disponibles para los filtros
        $result['equipo_list'] = $this->getEquiposByPlantaAndType($plantaId, $equipoTypeId);

        // Convertir fechas a timestamps UNIX (el ERP usa timestamps UNIX)
        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            return $result;
        }
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return $result;
        }

        // Obtener equipos para el informe
        $equipoNameCol = $this->erpColumnExists('equipo', 'equipo_name') ? 'equipo_name' : ($this->erpColumnExists('equipo', 'name') ? 'name' : null);
        if ($equipoNameCol === null) {
            return $result;
        }

        $equipoSql = 'SELECT id, ' . $equipoNameCol . ' AS equipo_name FROM equipo WHERE equipo_status > 0 AND equipo_planta_id = :planta_id';
        $equipoParams = [':planta_id' => $plantaId];
        if ($equipoTypeId !== null && $equipoTypeId > 0) {
            $equipoSql .= ' AND equipo_type_id = :type_id';
            $equipoParams[':type_id'] = $equipoTypeId;
        }
        if ($equipoId !== null && $equipoId > 0) {
            $equipoSql .= ' AND id = :equipo_id';
            $equipoParams[':equipo_id'] = $equipoId;
        }
        $equipoSql .= ' ORDER BY equipo_name';

        try {
            $stmt = $this->erpPdo->prepare($equipoSql);
            $stmt->execute($equipoParams);
            $equipos = $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            return $result;
        }
        $result['equipos'] = $equipos;

        if (empty($equipos)) {
            return $result;
        }

        // Consultar asignaciones de personal por máquina
        $hasWorkersTable = $this->erpTableExists('workers');
        $hasWorkersTypes = $this->erpTableExists('workers_types');
        $hasTurnos = $this->erpTableExists('turnos');
        $hasTurnosTypes = $this->erpTableExists('turnos_types');

        $selectParts = [
            't1.*',
        ];
        $joinParts = [];

        if ($hasTurnos) {
            $selectParts[] = 't3.turn_name';
            $joinParts[] = 'LEFT JOIN turnos t3 ON t1.assign_turno_id = t3.id';
        }
        if ($hasTurnosTypes) {
            $selectParts[] = 't6.type_name_short AS turno_type_short';
            $selectParts[] = 't6.type_color';
            $joinParts[] = 'LEFT JOIN turnos_types t6 ON t1.assign_turno_type_id = t6.id';
        }
        if ($hasWorkersTable) {
            $selectParts[] = 't8.wrk_firstname';
            $selectParts[] = 't8.wrk_lastname';
            $selectParts[] = 't8.wrk_rut';
            $joinParts[] = 'LEFT JOIN workers t8 ON t1.assign_worker_id = t8.id';
            if ($hasWorkersTypes && $this->erpColumnExists('workers', 'wrk_cargoid')) {
                $selectParts[] = 't9.type_name AS cargo';
                $joinParts[] = 'LEFT JOIN workers_types t9 ON t8.wrk_cargoid = t9.id';
            }
        }

        $selectSql = implode(', ', $selectParts);
        $joinSql = implode(' ', $joinParts);

        // Verificar el nombre de la columna de equipo en la asignación
        $assignEquipoCol = 'assign_equipoaid';
        if (!$this->erpColumnExists('turnos_config_assign', 'assign_equipoaid')) {
            if ($this->erpColumnExists('turnos_config_assign', 'assign_equipo_id')) {
                $assignEquipoCol = 'assign_equipo_id';
            } else {
                return $result;
            }
        }

        $hasPlantaCol = $this->erpColumnExists('turnos_config_assign', 'assign_planta_id');

        $sql = 'SELECT ' . $selectSql . '
                FROM turnos_config_assign t1
                ' . $joinSql . '
                WHERE t1.assign_stamp BETWEEN :start_ts AND :end_ts';
        $queryParams = [':start_ts' => $startTs, ':end_ts' => $endTs];

        if ($hasPlantaCol) {
            $sql .= ' AND t1.assign_planta_id = :planta_id';
            $queryParams[':planta_id'] = $plantaId;
        }

        $sql .= ' ORDER BY t1.assign_stamp, t1.ass_init_hour, t1.ass_init_min, t1.ass_end_hour, t1.ass_end_min';

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($queryParams);
            $allAssignments = $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            return $result;
        }

        // Agrupar por equipo
        $assignments = [];
        foreach ($allAssignments as $row) {
            $eqId = (int)($row[$assignEquipoCol] ?? 0);
            if ($eqId > 0) {
                $assignments[$eqId][] = $row;
            }
        }
        $result['assignments'] = $assignments;

        // Consultar incidencias de personal
        if ($this->erpTableExists('turnos_config_incidencias') && $this->erpTableExists('incidencias')) {
            try {
                $stmt = $this->erpPdo->query(
                    'SELECT t1.id, t1.cfi_startdate, t1.cfi_enddate, t1.cfi_workerid,
                            t3.inc_name_short, t3.inc_name, t3.inc_color
                     FROM turnos_config_incidencias t1
                     INNER JOIN incidencias t3 ON t1.cfi_inc_id = t3.id
                     WHERE t1.cfi_status = 2 AND t3.inc_type = 0'
                );
                $allIncidents = $stmt->fetchAll() ?: [];
            } catch (Throwable) {
                $allIncidents = [];
            }

            $incidents = [];
            foreach ($allIncidents as $inc) {
                $cfiStart = (int)($inc['cfi_startdate'] ?? 0);
                $cfiEnd = (int)($inc['cfi_enddate'] ?? 0);
                for ($x = $cfiStart + 3600; $x <= $cfiEnd; $x += 86400) {
                    if ($x >= $startTs && $x <= $endTs) {
                        $workerId = (int)($inc['cfi_workerid'] ?? 0);
                        $dateKey = date('d.m.Y', $x);
                        $incidents[$workerId][$dateKey] = $inc;
                    }
                }
            }
            $result['incidents'] = $incidents;
        }

        return $result;
    }

    /**
     * Genera el informe de producción por máquina.
     *
     * Consulta órdenes de trabajo producidas en el rango de fechas con desglose
     * por tipo de equipo/proceso (Flexografía, Corte y Sellado, Serigrafía, Pulpo,
     * Embalaje, Rebobinado, etc.) y todas las máquinas correspondientes a cada proceso.
     *
     * Adaptado y unificado de libs/modules/stats/prod/ (corteysellado.php,
     * flexo.php, serigrafia.php, pulpo-seri.php, embalaje.php).
     *
     * @return array{
     *   plantas: list<array>,
     *   equipo_types: list<array>,
     *   equipos: list<array>,
     *   processes: array<string, array{title: string, count: int, produced: float}>,
     *   active_process: string,
     *   rows: list<array<string, mixed>>,
     *   summary: array<string, mixed>
     * }
     */
    public function getMachineProductionReport(
        string $startAt,
        string $endAt,
        ?int $plantaId = null,
        ?int $equipoTypeId = null,
        ?int $equipoId = null,
        ?string $search = null,
        ?string $process = null
    ): array {
        $process = strtolower(trim((string)($process ?? 'sellado')));
        if ($process === '' || $process === 'all') {
            $process = 'sellado';
        }

        $result = [
            'plantas' => $this->getPlantasList(),
            'equipo_types' => $this->getEquipoTypesList(),
            'equipos' => [],
            'processes' => [
                'sellado' => ['title' => 'Corte y Sellado', 'count' => 0, 'produced' => 0.0],
                'flexo' => ['title' => 'Flexografía', 'count' => 0, 'produced' => 0.0],
                'seri' => ['title' => 'Serigrafía', 'count' => 0, 'produced' => 0.0],
                'pulpo' => ['title' => 'Pulpo Serigráfico', 'count' => 0, 'produced' => 0.0],
                'embalaje' => ['title' => 'Embalaje', 'count' => 0, 'produced' => 0.0],
                'rebo' => ['title' => 'Rebobinado', 'count' => 0, 'produced' => 0.0],
            ],
            'active_process' => $process,
            'is_historical_search' => false,
            'search_query' => $search,
            'rows' => [],
            'summary' => [
                'total_ots' => 0,
                'total_produced_units' => 0.0,
                'total_requested_units' => 0.0,
                'total_pending_units' => 0.0,
                'total_waste_units' => 0.0,
                'total_waste_kg' => 0.0,
                'waste_percent' => null,
                'total_hours' => 0.0,
            ],
        ];

        if (!$this->erpTableExists('prod_worker_ot') || !$this->erpTableExists('prod_agenda')) {
            return $result;
        }

        // Determinar planta por defecto si aplica
        if (($plantaId === null || $plantaId <= 0) && !empty($result['plantas'])) {
            $plantaId = (int)$result['plantas'][0]['id'];
        }

        $result['equipos'] = $plantaId !== null ? $this->getEquiposByPlantaAndType($plantaId, $equipoTypeId) : [];

        // Convertir fechas a timestamps UNIX
        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            return $result;
        }
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return $result;
        }

        // Helper para construir cláusulas de búsqueda inteligente con nombres únicos de parámetros
        // para prevenir errores SQLSTATE[HY093] por placeholders duplicados en PDO nativo.
        $buildSearchCondition = static function (string $raw, array &$params): string {
            $raw = trim($raw);
            if ($raw === '') {
                return '';
            }

            $clauses = [];
            $idx = 0;

            // 1. Búsqueda directa sobre campos de texto estándar
            $idx++;
            $clauses[] = "t4.prd_number LIKE :s_ot_raw_{$idx}";
            $params[":s_ot_raw_{$idx}"] = '%' . $raw . '%';

            $clauses[] = "t9.req_number LIKE :s_cc_raw_{$idx}";
            $params[":s_cc_raw_{$idx}"] = '%' . $raw . '%';

            $clauses[] = "t12.cust_name LIKE :s_cust_raw_{$idx}";
            $params[":s_cust_raw_{$idx}"] = '%' . $raw . '%';

            $clauses[] = "t11.item_title LIKE :s_item_raw_{$idx}";
            $params[":s_item_raw_{$idx}"] = '%' . $raw . '%';

            $clauses[] = "t11.item_number_prod LIKE :s_prod_raw_{$idx}";
            $params[":s_prod_raw_{$idx}"] = '%' . $raw . '%';

            // 2. Si el usuario prefijó OT (ej. "OT 3837", "OT-3837", "OT#3837", "OT: 3837")
            $otClean = trim((string)preg_replace('/^ot[:\s\-_#]*/i', '', $raw));
            if ($otClean !== '' && $otClean !== $raw) {
                $idx++;
                $clauses[] = "t4.prd_number LIKE :s_ot_cl_{$idx}";
                $params[":s_ot_cl_{$idx}"] = '%' . $otClean . '%';
            }

            // 3. Si el usuario prefijó CC (ej. "CC 26-00808-1", "CC-808", "CC 808")
            $ccClean = trim((string)preg_replace('/^cc[:\s\-_#]*/i', '', $raw));
            if ($ccClean !== '' && $ccClean !== $raw) {
                $idx++;
                $clauses[] = "t9.req_number LIKE :s_cc_cl_{$idx}";
                $params[":s_cc_cl_{$idx}"] = '%' . $ccClean . '%';
            }

            // 4. Búsqueda numérica exacta o zero-padded para OT (003837) y CC (00808)
            if (preg_match('/^\d+$/', $raw)) {
                $num = (int)$raw;
                $idx++;
                $clauses[] = "t4.prd_number = :s_padot_{$idx}";
                $params[":s_padot_{$idx}"] = sprintf('%06d', $num);

                $idx++;
                $clauses[] = "t9.req_number LIKE :s_padcc_{$idx}";
                $params[":s_padcc_{$idx}"] = '%' . sprintf('%05d', $num) . '%';
            }

            // 5. Si viene texto mixto como "OT 3837" o "CC 808", extraer también el número puro
            $cleanNum = trim((string)preg_replace('/[^0-9]/', '', $raw));
            if ($cleanNum !== '' && $cleanNum !== $raw && strlen($cleanNum) <= 6) {
                $num2 = (int)$cleanNum;
                $idx++;
                $clauses[] = "t4.prd_number = :s_padot2_{$idx}";
                $params[":s_padot2_{$idx}"] = sprintf('%06d', $num2);

                $idx++;
                $clauses[] = "t9.req_number LIKE :s_padcc2_{$idx}";
                $params[":s_padcc2_{$idx}"] = '%' . sprintf('%05d', $num2) . '%';
            }

            return '(' . implode(' OR ', array_unique($clauses)) . ')';
        };

        $baseWhereClauses = [
            't1.wok_status > 0',
            't1.wok_crtdat BETWEEN :start_ts AND :end_ts',
        ];
        $baseParams = [
            ':start_ts' => $startTs,
            ':end_ts' => $endTs,
        ];

        if ($plantaId !== null && $plantaId > 0 && $this->erpColumnExists('prod_header', 'prd_plantaid')) {
            $baseWhereClauses[] = 't4.prd_plantaid = :planta_id';
            $baseParams[':planta_id'] = $plantaId;
        }

        $search = trim((string)$search);
        if ($search !== '') {
            $searchCond = $buildSearchCondition($search, $baseParams);
            if ($searchCond !== '') {
                $baseWhereClauses[] = $searchCond;
            }
        }

        // Primero calculamos conteos y totales por proceso para los tabs acotados a la Fecha de Proceso
        $whereProcessCounts = implode(' AND ', $baseWhereClauses);
        $countsParams = $baseParams;

        $countsSql = "
            SELECT 
                CASE 
                    WHEN t7.equipo_type_id = 7 THEN 'flexo'
                    WHEN t7.equipo_type_id = 8 THEN 'sellado'
                    WHEN t7.equipo_type_id = 11 AND t7.id != 36 THEN 'seri'
                    WHEN t7.equipo_type_id = 22 OR t7.id = 36 THEN 'pulpo'
                    WHEN t7.equipo_type_id = 15 THEN 'embalaje'
                    WHEN t7.equipo_type_id = 12 THEN 'rebo'
                    ELSE 'otros'
                END AS process_code,
                COUNT(DISTINCT t1.id) AS ot_count,
                COALESCE(SUM(pe.produced_units), 0) AS total_prod
            FROM prod_worker_ot t1
            INNER JOIN prod_agenda t2 ON t1.wok_ag_id = t2.id
            INNER JOIN prod_worker_init t3 ON t1.wok_init_id = t3.id
            INNER JOIN prod_header t4 ON t2.ag_prdid = t4.id
            LEFT JOIN equipo t7 ON t3.win_equipoid = t7.id
            LEFT JOIN (
                SELECT evt_prod_worker_otid, 
                       SUM(CASE WHEN LOWER(evt_type) IN ('prod', 'production', 'prodsericolor') THEN evt_amount ELSE 0 END) AS produced_units
                FROM prod_worker_ot_events
                GROUP BY evt_prod_worker_otid
            ) pe ON pe.evt_prod_worker_otid = t1.id
            WHERE {$whereProcessCounts}
            GROUP BY process_code
        ";

        $totalAllCount = 0;
        $totalAllProduced = 0.0;
        try {
            $stmtCounts = $this->erpPdo->prepare($countsSql);
            $stmtCounts->execute($countsParams);
            $countRows = $stmtCounts->fetchAll() ?: [];
            foreach ($countRows as $cr) {
                $pcode = (string)($cr['process_code'] ?? '');
                $pCount = (int)($cr['ot_count'] ?? 0);
                $pProd = (float)($cr['total_prod'] ?? 0);
                $totalAllCount += $pCount;
                $totalAllProduced += $pProd;
                if (isset($result['processes'][$pcode])) {
                    $result['processes'][$pcode]['count'] = $pCount;
                    $result['processes'][$pcode]['produced'] = $pProd;
                }
            }
            if (isset($result['processes']['all'])) {
                $result['processes']['all']['count'] = $totalAllCount;
                $result['processes']['all']['produced'] = $totalAllProduced;
            }
        } catch (Throwable) {
            // fallback: continue
        }

        // Si se buscó una OT/CC/Cliente y no arrojó resultados en el período actual (ej. OT de un mes anterior),
        // fallback automático a búsqueda histórica en toda la base de datos para no obligar a adivinar el mes
        if ($totalAllCount === 0 && $search !== '') {
            $histWhereClauses = ['t1.wok_status > 0'];
            $histParams = [];
            if ($plantaId !== null && $plantaId > 0 && $this->erpColumnExists('prod_header', 'prd_plantaid')) {
                $histWhereClauses[] = 't4.prd_plantaid = :planta_id';
                $histParams[':planta_id'] = $plantaId;
            }
            $histSearchCond = $buildSearchCondition($search, $histParams);
            if ($histSearchCond !== '') {
                $histWhereClauses[] = $histSearchCond;
            }

            $histCountsSql = "
                SELECT 
                    CASE 
                        WHEN t7.equipo_type_id = 7 THEN 'flexo'
                        WHEN t7.equipo_type_id = 8 THEN 'sellado'
                        WHEN t7.equipo_type_id = 11 AND t7.id != 36 THEN 'seri'
                        WHEN t7.equipo_type_id = 22 OR t7.id = 36 THEN 'pulpo'
                        WHEN t7.equipo_type_id = 15 THEN 'embalaje'
                        WHEN t7.equipo_type_id = 12 THEN 'rebo'
                        ELSE 'otros'
                    END AS process_code,
                    COUNT(DISTINCT t1.id) AS ot_count,
                    COALESCE(SUM(pe.produced_units), 0) AS total_prod
                FROM prod_worker_ot t1
                INNER JOIN prod_agenda t2 ON t1.wok_ag_id = t2.id
                INNER JOIN prod_worker_init t3 ON t1.wok_init_id = t3.id
                INNER JOIN prod_header t4 ON t2.ag_prdid = t4.id
                LEFT JOIN equipo t7 ON t3.win_equipoid = t7.id
                LEFT JOIN orders t9 ON t2.ag_reqid = t9.id
                LEFT JOIN orders_items t10 ON t9.id = t10.req_id
                LEFT JOIN item t11 ON t10.item_id = t11.id
                LEFT JOIN customer t12 ON t9.req_cust_id = t12.id
                INNER JOIN (
                    SELECT evt_prod_worker_otid, 
                           SUM(CASE WHEN LOWER(evt_type) IN ('prod', 'production', 'prodsericolor') OR evt_status > 0 THEN evt_amount ELSE 0 END) AS produced_units,
                           SUM(evt_amount_metros_lineales) AS produced_meters,
                           SUM(prod_bobina_kg) AS produced_kg
                    FROM prod_worker_ot_events
                    GROUP BY evt_prod_worker_otid
                    HAVING (
                        SUM(CASE WHEN LOWER(evt_type) IN ('prod', 'production', 'prodsericolor') OR evt_status > 0 THEN evt_amount ELSE 0 END) > 0
                        OR SUM(evt_amount_metros_lineales) > 0
                        OR SUM(prod_bobina_kg) > 0
                    )
                ) pe ON pe.evt_prod_worker_otid = t1.id
                WHERE " . implode(' AND ', $histWhereClauses) . "
                GROUP BY process_code
            ";

            try {
                $stmtHist = $this->erpPdo->prepare($histCountsSql);
                $stmtHist->execute($histParams);
                $histRows = $stmtHist->fetchAll() ?: [];
                $histAllCount = 0;
                $histAllProduced = 0.0;
                foreach ($histRows as $hr) {
                    $pcode = (string)($hr['process_code'] ?? '');
                    $pCount = (int)($hr['ot_count'] ?? 0);
                    $pProd = (float)($hr['total_prod'] ?? 0);
                    $histAllCount += $pCount;
                    $histAllProduced += $pProd;
                    if (isset($result['processes'][$pcode])) {
                        $result['processes'][$pcode]['count'] = $pCount;
                        $result['processes'][$pcode]['produced'] = $pProd;
                    }
                }
                if ($histAllCount > 0) {
                    $result['is_historical_search'] = true;
                    $baseWhereClauses = $histWhereClauses;
                    $baseParams = $histParams;
                }
            } catch (Throwable) {
                // ignore
            }
        }

        // Filtros específicos para la consulta principal
        $whereClauses = $baseWhereClauses;
        $params = $baseParams;

        // Filtro por proceso (agrupa todas las máquinas de ese proceso)
        if ($process !== 'all') {
            switch ($process) {
                case 'flexo':
                    $whereClauses[] = 't7.equipo_type_id = 7';
                    break;
                case 'sellado':
                    $whereClauses[] = 't7.equipo_type_id = 8';
                    break;
                case 'seri':
                    $whereClauses[] = 't7.equipo_type_id = 11 AND t7.id != 36';
                    break;
                case 'pulpo':
                    $whereClauses[] = '(t7.equipo_type_id = 22 OR t7.id = 36)';
                    break;
                case 'embalaje':
                    $whereClauses[] = 't7.equipo_type_id = 15';
                    break;
                case 'rebo':
                    $whereClauses[] = 't7.equipo_type_id = 12';
                    break;
            }
        } elseif ($equipoTypeId !== null && $equipoTypeId > 0) {
            $whereClauses[] = 't7.equipo_type_id = :equipo_type_id';
            $params[':equipo_type_id'] = $equipoTypeId;
        }

        if ($equipoId !== null && $equipoId > 0) {
            $whereClauses[] = 't7.id = :equipo_id';
            $params[':equipo_id'] = $equipoId;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $sql = "SELECT 
            t1.id AS ot_id,
            t1.wok_crtdat,
            t1.wok_enddat,
            t1.wok_status,
            t3.win_wrkid AS operator_id,
            pe.ayudante_id AS helper_id,
            t4.prd_number AS ot_number,
            t9.req_number AS cc_number,
            t9.req_production_initdate AS cc_init_date,
            t12.cust_name AS customer_name,
            t11.item_number_prod AS item_code,
            t11.item_title AS item_title,
            t11.item_weight AS item_weight,
            p1.cat_title AS bag_type,
            t10.fab_med_width,
            t10.fab_med_height,
            t10.fab_med_fuelle,
            t10.fab_mat_gramms,
            t10.fab_type,
            v1.add_name AS fabric_color,
            v1.add_name_eng AS fabric_color_code,
            v2.add_name AS manilla_color,
            v2.add_name_eng AS manilla_color_code,
            t10.fab_manilla_length,
            t10.fab_mat_dispositivo,
            t10.item_sellprice_barcodenumber,
            t9.req_pie_imprenta,
            t7.id AS machine_id,
            t7.equipo_name AS machine_name,
            t7.equipo_type_id AS machine_type_id,
            t8.type_ant_title AS machine_type_title,
            CASE 
                WHEN t7.equipo_type_id = 7 THEN 'flexo'
                WHEN t7.equipo_type_id = 8 THEN 'sellado'
                WHEN t7.equipo_type_id = 11 AND t7.id != 36 THEN 'seri'
                WHEN t7.equipo_type_id = 22 OR t7.id = 36 THEN 'pulpo'
                WHEN t7.equipo_type_id = 15 THEN 'embalaje'
                WHEN t7.equipo_type_id = 12 THEN 'rebo'
                ELSE 'otros'
            END AS process_code,
            CASE 
                WHEN t7.equipo_type_id = 7 THEN 'Flexografía'
                WHEN t7.equipo_type_id = 8 THEN 'Corte y Sellado'
                WHEN t7.equipo_type_id = 11 AND t7.id != 36 THEN 'Serigrafía'
                WHEN t7.equipo_type_id = 22 OR t7.id = 36 THEN 'Pulpo Serigráfico'
                WHEN t7.equipo_type_id = 15 THEN 'Embalaje'
                WHEN t7.equipo_type_id = 12 THEN 'Rebobinado'
                ELSE COALESCE(t8.type_ant_title, 'Otros')
            END AS process_category,
            w_op.wrk_firstname AS op_first,
            w_op.wrk_lastname AS op_last,
            w_op.wrk_rut AS op_rut,
            ctrl.supervisor_name,
            ctrl.supervisor_rut,
            w_ay.wrk_firstname AS ay_first,
            w_ay.wrk_lastname AS ay_last,
            w_ay.wrk_rut AS ay_rut,
            tt.type_name AS shift_name,
            tca.ass_init_hour,
            tca.ass_init_min,
            tca.ass_end_hour,
            tca.ass_end_min,
            t2.ag_prdid AS prd_id,
            t2.id AS ag_id,
            t2.ag_amount,
            t10.item_amount,
            COALESCE(t10.item_amount, t2.ag_amount, 0) AS requested_units,
            COALESCE(pe.produced_units, 0) AS produced_units,
            COALESCE(pe.produced_meters, 0) AS produced_meters,
            COALESCE(pe.produced_meters_maquina, 0) AS produced_meters_maquina,
            COALESCE(pe.produced_kg, 0) AS produced_kg,
            COALESCE(pe.speed_m_min, 0) AS speed_m_min,
            COALESCE(pe.setup_seconds, 0) AS setup_seconds,
            COALESCE(pe.pause_seconds, 0) AS pause_seconds,
            COALESCE(dw.waste_units_raw, 0) AS waste_units_raw,
            COALESCE(dw.waste_kg, 0) AS waste_kg,
            COALESCE(dw.waste_setup_units_raw, 0) AS waste_setup_units_raw,
            COALESCE(dw.waste_setup_kg, 0) AS waste_setup_kg,
            COALESCE(dw.waste_print_units_raw, 0) AS waste_print_units_raw,
            COALESCE(dw.waste_print_kg, 0) AS waste_print_kg,
            COALESCE(dw.waste_coil_units_raw, 0) AS waste_coil_units_raw,
            COALESCE(dw.waste_coil_kg, 0) AS waste_coil_kg,
            COALESCE(dw.waste_repair_units_raw, 0) AS waste_repair_units_raw,
            COALESCE(dw.waste_repair_kg, 0) AS waste_repair_kg,
            t10.fab_print_colors_front_1,
            t10.fab_print_colors_front_2,
            t10.fab_print_colors_front_3,
            t10.fab_print_colors_front_4,
            t10.fab_print_colors_front_5,
            t10.fab_print_colors_front_6,
            t10.fab_print_colors_back_1,
            t10.fab_print_colors_back_2,
            t10.fab_print_colors_back_3,
            t10.fab_print_colors_back_4,
            t10.fab_print_colors_back_5,
            t10.fab_print_colors_back_6,
            t10.fab_print_colordesc_1,
            t10.fab_print_colordesc_2,
            t10.fab_print_colordesc_3,
            t10.fab_print_colordesc_4
        FROM prod_worker_ot t1
        INNER JOIN prod_agenda t2 ON t1.wok_ag_id = t2.id
        INNER JOIN prod_worker_init t3 ON t1.wok_init_id = t3.id
        INNER JOIN prod_header t4 ON t2.ag_prdid = t4.id
        LEFT JOIN equipo t7 ON t3.win_equipoid = t7.id
        LEFT JOIN equipo_type t8 ON t7.equipo_type_id = t8.id
        LEFT JOIN orders t9 ON t2.ag_reqid = t9.id
        LEFT JOIN orders_items t10 ON t9.id = t10.req_id
        LEFT JOIN item t11 ON t10.item_id = t11.id
        LEFT JOIN customer t12 ON t9.req_cust_id = t12.id
        LEFT JOIN item_productcats ip1 ON ip1.item_id = t11.id
        LEFT JOIN productcats p1 ON ip1.cat_id = p1.id
        LEFT JOIN tran_comments_vals v1 ON t10.fab_mat_fabric_color = v1.id
        LEFT JOIN tran_comments_vals v2 ON t10.fab_mat_manilla_color = v2.id
        LEFT JOIN workers w_op ON t3.win_wrkid = w_op.id
        LEFT JOIN (
            SELECT
                ac.ctr_init_id,
                MAX(TRIM(CONCAT(COALESCE(u.user_firstname, ''), ' ', COALESCE(u.user_lastname, '')))) AS supervisor_name,
                MAX(u.user_rut) AS supervisor_rut
            FROM prod_worker_ot_autocontrol ac
            INNER JOIN user u ON u.id = ac.ctr_ctrusr
            INNER JOIN prod_worker_ot pw_sub ON pw_sub.id = ac.ctr_init_id
            WHERE ac.ctr_type = 'supervisor'
              AND pw_sub.wok_status > 0
            GROUP BY ac.ctr_init_id
        ) ctrl ON ctrl.ctr_init_id = t1.id
        LEFT JOIN turnos_config_assign tca ON t3.win_ass_id = tca.id
        LEFT JOIN turnos_types tt ON tca.assign_turno_type_id = tt.id
        LEFT JOIN (
            SELECT 
                e.evt_prod_worker_otid,
                SUM(CASE WHEN LOWER(e.evt_type) IN ('prod', 'production', 'prodsericolor') THEN e.evt_amount ELSE 0 END) AS produced_units,
                SUM(e.evt_amount_metros_lineales) AS produced_meters,
                SUM(e.evt_amount_metros_maquina) AS produced_meters_maquina,
                SUM(e.prod_bobina_kg) AS produced_kg,
                AVG(CASE WHEN e.evt_amount_metros_lineales > 0 AND (e.evt_enddat - e.evt_crtdat) > 0 
                    THEN (e.evt_amount_metros_lineales / ((e.evt_enddat - e.evt_crtdat) / 60.0)) 
                    ELSE NULL END) AS speed_m_min,
                SUM(CASE WHEN LOWER(e.evt_type) = 'setup' AND e.evt_enddat > e.evt_crtdat THEN (e.evt_enddat - e.evt_crtdat) ELSE 0 END) AS setup_seconds,
                SUM(CASE WHEN LOWER(e.evt_type) = 'pause' AND e.evt_enddat > e.evt_crtdat THEN (e.evt_enddat - e.evt_crtdat) ELSE 0 END) AS pause_seconds,
                MAX(CASE WHEN e.evt_idayudante > 0 THEN e.evt_idayudante ELSE NULL END) AS ayudante_id
            FROM prod_worker_ot_events e
            GROUP BY e.evt_prod_worker_otid
        ) pe ON pe.evt_prod_worker_otid = t1.id
        LEFT JOIN workers w_ay ON pe.ayudante_id = w_ay.id
        LEFT JOIN (
            SELECT 
                e.evt_prod_worker_otid AS wok_id,
                -- Total Merma Kgs (Suma de merma Kgs excluyendo repair)
                SUM(CASE WHEN LOWER(d.evt_type) = 'merma' THEN d.evt_kgstounits ELSE 0 END) AS waste_kg,
                SUM(CASE WHEN LOWER(d.evt_type) = 'merma' THEN d.evt_amount ELSE 0 END) AS waste_units_raw,

                -- Merma Setup / Proceso / Alistamiento (ID 6 y 9 de prod_mermatypes)
                SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid IN (6, 9) THEN d.evt_kgstounits ELSE 0 END) AS waste_setup_kg,
                SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid IN (6, 9) THEN d.evt_amount ELSE 0 END) AS waste_setup_units_raw,

                -- Merma Impresión (ID 7 de prod_mermatypes)
                SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid = 7 THEN d.evt_kgstounits ELSE 0 END) AS waste_print_kg,
                SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid = 7 THEN d.evt_amount ELSE 0 END) AS waste_print_units_raw,

                -- Merma Bobina / Otros (ID 8 y cualquier otro tipo de merma)
                SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid NOT IN (6, 7, 9) THEN d.evt_kgstounits ELSE 0 END) AS waste_coil_kg,
                SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid NOT IN (6, 7, 9) THEN d.evt_amount ELSE 0 END) AS waste_coil_units_raw,

                -- Reparación (evt_type = 'repair' o evt_repair_typeid > 0)
                SUM(CASE WHEN LOWER(d.evt_type) = 'repair' OR d.evt_repair_typeid > 0 THEN d.evt_kgstounits ELSE 0 END) AS waste_repair_kg,
                SUM(CASE WHEN LOWER(d.evt_type) = 'repair' OR d.evt_repair_typeid > 0 THEN d.evt_amount ELSE 0 END) AS waste_repair_units_raw
            FROM prod_worker_ot_defectunits d
            INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
            WHERE d.evt_status > 0
            GROUP BY e.evt_prod_worker_otid
        ) dw ON dw.wok_id = t1.id
        WHERE {$whereSql}
        ORDER BY process_category, t7.equipo_name, t1.wok_crtdat DESC, t1.id DESC";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            $rawRows = $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return $result;
        }

        $formattedRows = [];
        $totalProduced = 0.0;
        $totalProducedKg = 0.0;
        $totalRequested = 0.0;
        $requestedByOrder = [];
        $totalWaste = 0.0;
        $totalWasteKg = 0.0;
        $totalSeconds = 0;

        // Consulta cruzada: obtener producción acumulada en períodos/meses anteriores (antes de $startTs)
        // para cada OT (prd_id) y tipo de máquina (equipo_type_id).
        // De esta forma, si una orden comercial (CC) de 70.000 bolsas ya produjo 50.000 en meses anteriores,
        // no se carga el total de 70.000 sino el saldo parcial que falta por terminar (20.000) en el período.
        $priorProducedMap = [];
        $distinctPrdIds = array_values(array_filter(array_unique(array_map(
            static fn($r) => (int)($r['prd_id'] ?? 0),
            $rawRows
        ))));

        if (!empty($distinctPrdIds) && $startTs > 0) {
            $inPlaceholders = implode(',', array_fill(0, count($distinctPrdIds), '?'));
            $sqlPrior = "
                SELECT 
                    pa.ag_prdid,
                    eq.equipo_type_id,
                    SUM(e.evt_amount) AS prior_prod
                FROM prod_worker_ot wok
                JOIN prod_agenda pa ON wok.wok_ag_id = pa.id
                JOIN prod_worker_init win ON wok.wok_init_id = win.id
                JOIN equipo eq ON win.win_equipoid = eq.id
                JOIN prod_worker_ot_events e ON e.evt_prod_worker_otid = wok.id
                WHERE pa.ag_prdid IN ($inPlaceholders)
                  AND e.evt_type IN ('prod', 'production', 'prodsericolor')
                  AND wok.wok_crtdat < ?
                GROUP BY pa.ag_prdid, eq.equipo_type_id
            ";
            $priorParams = array_values($distinctPrdIds);
            $priorParams[] = $startTs;

            try {
                $stmtPrior = $this->erpPdo->prepare($sqlPrior);
                $stmtPrior->execute($priorParams);
                foreach ($stmtPrior->fetchAll(PDO::FETCH_ASSOC) ?: [] as $prRow) {
                    $k = ((int)$prRow['ag_prdid']) . '_' . ((int)$prRow['equipo_type_id']);
                    $priorProducedMap[$k] = (float)$prRow['prior_prod'];
                }
            } catch (Throwable) {
                $priorProducedMap = [];
            }
        }

        foreach ($rawRows as $row) {
            $startTsRow = (int)($row['wok_crtdat'] ?? 0);
            $endTsRow = (int)($row['wok_enddat'] ?? 0);
            $durationSeconds = 0;
            $durationStr = '-';
            if ($startTsRow > 0 && $endTsRow > $startTsRow) {
                $durationSeconds = $endTsRow - $startTsRow;
                $hrs = (int)floor($durationSeconds / 3600);
                $mins = (int)floor(($durationSeconds % 3600) / 60);
                $durationStr = sprintf('%02d:%02d h', $hrs, $mins);
            }

            $status = (int)($row['wok_status'] ?? 0);
            $statusLabel = 'Abierta';
            if ($endTsRow > 0) {
                $statusLabel = 'Terminada';
            } elseif ($status > 0) {
                $statusLabel = 'En Curso';
            }

            $w = (float)($row['fab_med_width'] ?? 0);
            $h = (float)($row['fab_med_height'] ?? 0);
            $f = (float)($row['fab_med_fuelle'] ?? 0);
            $gramms = (float)($row['fab_mat_gramms'] ?? 0);
            $manLen = (float)($row['fab_manilla_length'] ?? 0);
            $itemWeight = (float)($row['item_weight'] ?? 0);

            $formatCm = '';
            if ($w > 0 && $h > 0) {
                $formatCm = ((int)$w) . 'x' . ((int)$h) . ($f > 0 ? ('x' . ((int)$f)) : '') . ' cm';
            }

            $prdId = (int)($row['prd_id'] ?? 0);
            $machineTypeId = (int)($row['machine_type_id'] ?? 0);
            $priorKey = $prdId . '_' . $machineTypeId;
            $priorProducedUnits = (float)($priorProducedMap[$priorKey] ?? 0.0);

            $rawRequested = (float)($row['requested_units'] ?? 0.0);
            $totalOrderUnits = (float)($row['item_amount'] ?? 0.0);
            if ($totalOrderUnits <= 0) {
                $totalOrderUnits = (float)($row['ag_amount'] ?? $rawRequested);
            }

            // Unidades planificadas para el período: saldo parcial que falta por terminar
            // (Total CC menos lo producido en meses o días anteriores en este proceso)
            if ($totalOrderUnits > 0) {
                $reqUnits = max(0.0, $totalOrderUnits - $priorProducedUnits);
            } else {
                $reqUnits = $rawRequested;
            }

            $prodUnits = (float)($row['produced_units'] ?? 0.0);
            $prodKg = (float)($row['produced_kg'] ?? 0.0);

            // Peso unitario teórico en Kg por bolsa
            $unitWeightKg = 0.0;
            if ($itemWeight > 0) {
                $unitWeightKg = $itemWeight / 1000.0;
            } elseif ($w > 0 && $h > 0 && $gramms > 0) {
                $areaM2 = (2.0 * ($w + $f) * $h) / 10000.0;
                $bodyKg = $areaM2 * ($gramms / 1000.0);
                $manillaKg = ($manLen > 0) ? (2.0 * ($manLen / 100.0) * 0.025 * ($gramms / 1000.0)) : 0.0;
                $unitWeightKg = $bodyKg + $manillaKg;
            } elseif ($prodUnits > 0 && $prodKg > 0) {
                $unitWeightKg = $prodKg / $prodUnits;
            }

            // Kilos de merma desglosados
            $wasteKg = (float)($row['waste_kg'] ?? 0.0);
            $wasteSetupKg = (float)($row['waste_setup_kg'] ?? 0.0);
            $wastePrintKg = (float)($row['waste_print_kg'] ?? 0.0);
            $wasteCoilKg = (float)($row['waste_coil_kg'] ?? 0.0);
            $wasteRepairKg = (float)($row['waste_repair_kg'] ?? 0.0);

            // Unidades de merma calculadas según peso unitario (estándar ERP / legacy)
            if ($unitWeightKg > 0) {
                $wasteSetupUnits = (float)round($wasteSetupKg / $unitWeightKg);
                $wastePrintUnits = (float)round($wastePrintKg / $unitWeightKg);
                $wasteCoilUnits = (float)round($wasteCoilKg / $unitWeightKg);
                $wasteRepairUnits = (float)round($wasteRepairKg / $unitWeightKg);
                $wasteUnits = $wasteSetupUnits + $wastePrintUnits + $wasteCoilUnits;
            } else {
                $wasteSetupUnits = (float)($row['waste_setup_units_raw'] ?? 0.0);
                $wastePrintUnits = (float)($row['waste_print_units_raw'] ?? 0.0);
                $wasteCoilUnits = (float)($row['waste_coil_units_raw'] ?? 0.0);
                $wasteRepairUnits = (float)($row['waste_repair_units_raw'] ?? 0.0);
                $wasteUnits = (float)($row['waste_units_raw'] ?? 0.0);
            }

            $wastePct = null;
            if ($prodUnits > 0 && $wasteUnits > 0) {
                $wastePct = round(($wasteUnits / $prodUnits) * 100.0, 2);
            } elseif ($prodKg > 0 && $wasteKg > 0) {
                $wastePct = round(($wasteKg / $prodKg) * 100.0, 2);
            }

            $totalProduced += $prodUnits;
            $totalProducedKg += $prodKg;
            
            // Las unidades solicitadas corresponden al saldo planificado del pedido (OT / CC). Para no multiplicar
            // las cantidades cuando una OT es trabajada en múltiples turnos o máquinas,
            // agrupamos el valor máximo solicitado por cada OT / CC y proceso único.
            $otKey = trim((string)($row['ot_number'] ?? ''));
            $ccKey = trim((string)($row['cc_number'] ?? ''));
            $itemKey = trim((string)($row['item_code'] ?? ''));
            $orderGroupKey = ($otKey !== '' ? $otKey : '') . '|' . ($ccKey !== '' ? $ccKey : '') . '|' . $itemKey . '|' . $machineTypeId;
            if ($orderGroupKey === '|||' . $machineTypeId) {
                $orderGroupKey = 'row_' . (int)($row['ot_id'] ?? 0);
            }
            if (!isset($requestedByOrder[$orderGroupKey]) || $reqUnits > $requestedByOrder[$orderGroupKey]) {
                $requestedByOrder[$orderGroupKey] = $reqUnits;
            }

            $totalWaste += $wasteUnits;
            $totalWasteKg += $wasteKg;
            $totalSeconds += $durationSeconds;

            // Operador, Supervisor y Ayudante
            $opFirst = trim((string)($row['op_first'] ?? ''));
            $opLast = trim((string)($row['op_last'] ?? ''));
            $operatorName = trim($opFirst . ' ' . $opLast);
            if ($operatorName === '') {
                $operatorName = 'Sin operador';
            }
            $operatorRut = trim((string)($row['op_rut'] ?? ''));

            $supName = trim((string)($row['supervisor_name'] ?? ''));
            $supRut = trim((string)($row['supervisor_rut'] ?? ''));

            $ayFirst = trim((string)($row['ay_first'] ?? ''));
            $ayLast = trim((string)($row['ay_last'] ?? ''));
            $ayudanteName = trim($ayFirst . ' ' . $ayLast);
            $ayudanteRut = trim((string)($row['ay_rut'] ?? ''));

            // Turno
            $shiftName = trim((string)($row['shift_name'] ?? ''));
            $shiftHours = '';
            if (isset($row['ass_init_hour']) && $row['ass_init_hour'] !== null && $row['ass_init_hour'] !== '') {
                $shiftHours = sprintf('%02d:%02d', (int)$row['ass_init_hour'], (int)($row['ass_init_min'] ?? 0)) .
                    ' - ' . sprintf('%02d:%02d', (int)($row['ass_end_hour'] ?? 0), (int)($row['ass_end_min'] ?? 0));
            }

            // Colores frente y dorso
            $colorsFront = [];
            for ($ci = 1; $ci <= 6; $ci++) {
                $cf = trim((string)($row['fab_print_colors_front_' . $ci] ?? ''));
                if ($cf !== '' && $cf !== '0') {
                    $colorsFront[] = $cf;
                }
            }
            $colorsBack = [];
            for ($ci = 1; $ci <= 6; $ci++) {
                $cb = trim((string)($row['fab_print_colors_back_' . $ci] ?? ''));
                if ($cb !== '' && $cb !== '0') {
                    $colorsBack[] = $cb;
                }
            }

            $formattedRows[] = [
                'ot_id' => (int)($row['ot_id'] ?? 0),
                'ot_number' => trim((string)($row['ot_number'] ?? '')),
                'cc_number' => trim((string)($row['cc_number'] ?? '')),
                'cc_init_date' => !empty($row['cc_init_date']) ? date('d/m/Y', (int)$row['cc_init_date']) : '',
                'customer_name' => trim((string)($row['customer_name'] ?? 'Sin cliente')),
                'item_code' => trim((string)($row['item_code'] ?? '')),
                'item_title' => trim((string)($row['item_title'] ?? '')),
                'bag_type' => trim((string)($row['bag_type'] ?? 'Bolsas')),
                'format_cm' => $formatCm,
                'width' => $w,
                'height' => $h,
                'fuelle' => $f,
                'fabric_type' => trim((string)($row['fabric_type'] ?? '')),
                'fabric_color' => trim((string)($row['fabric_color'] ?? '')),
                'fabric_color_code' => trim((string)($row['fabric_color_code'] ?? '')),
                'grammage' => (float)($row['fab_mat_gramms'] ?? 0.0),
                'manilla_color' => trim((string)($row['manilla_color'] ?? '')),
                'manilla_color_code' => trim((string)($row['manilla_color_code'] ?? '')),
                'manilla_length' => (float)($row['fab_manilla_length'] ?? 0.0),
                'barcode_number' => trim((string)($row['item_sellprice_barcodenumber'] ?? '')),
                'dispositivo' => trim((string)($row['fab_mat_dispositivo'] ?? '')),
                'pie_imprenta' => trim((string)($row['req_pie_imprenta'] ?? '')),
                'process_code' => trim((string)($row['process_code'] ?? 'otros')),
                'process_category' => trim((string)($row['process_category'] ?? 'General')),
                'machine_id' => (int)($row['machine_id'] ?? 0),
                'machine_name' => trim((string)($row['machine_name'] ?? 'Sin asignar')),
                'machine_type_id' => (int)($row['machine_type_id'] ?? 0),
                'machine_type_title' => trim((string)($row['machine_type_title'] ?? 'General')),
                'operator_name' => $operatorName,
                'operator_rut' => $operatorRut,
                'supervisor_name' => $supName,
                'supervisor_rut' => $supRut,
                'helper_name' => $ayudanteName,
                'helper_rut' => $ayudanteRut,
                'shift_name' => $shiftName,
                'shift_hours' => $shiftHours,
                'started_at' => $startTsRow > 0 ? date('d/m/Y H:i', $startTsRow) : '-',
                'ended_at' => $endTsRow > 0 ? date('d/m/Y H:i', $endTsRow) : '-',
                'duration_str' => $durationStr,
                'duration_seconds' => $durationSeconds,
                'duration_hours' => round($durationSeconds / 3600, 2),
                'setup_hours' => round((float)($row['setup_seconds'] ?? 0) / 3600, 2),
                'pause_hours' => round((float)($row['pause_seconds'] ?? 0) / 3600, 2),
                'speed_m_min' => round((float)($row['speed_m_min'] ?? 0), 1),
                'status_label' => $statusLabel,
                'requested_units' => $reqUnits,
                'total_order_units' => $totalOrderUnits,
                'prior_produced_units' => $priorProducedUnits,
                'ag_amount' => (float)($row['ag_amount'] ?? 0.0),
                'produced_units' => $prodUnits,
                'produced_meters' => (float)($row['produced_meters'] ?? 0),
                'produced_kg' => $prodKg,
                'waste_units' => $wasteUnits,
                'waste_kg' => $wasteKg,
                'waste_percent' => $wastePct,
                'waste_setup_units' => $wasteSetupUnits,
                'waste_setup_kg' => $wasteSetupKg,
                'waste_print_units' => $wastePrintUnits,
                'waste_print_kg' => $wastePrintKg,
                'waste_coil_units' => $wasteCoilUnits,
                'waste_coil_kg' => $wasteCoilKg,
                'waste_repair_units' => $wasteRepairUnits,
                'waste_repair_kg' => $wasteRepairKg,
                'unit_weight_grs' => round($unitWeightKg * 1000.0, 2),
                'colors_front' => implode(', ', $colorsFront),
                'colors_back' => implode(', ', $colorsBack),
                'wok_status' => $status,
                'wok_crtdat' => $startTsRow,
                'wok_enddat' => $endTsRow,
                'operator_id' => (int)($row['operator_id'] ?? 0),
                'helper_id' => (int)($row['helper_id'] ?? 0),
            ];
        }

        $totalRequested = (float)array_sum($requestedByOrder);
        $totalPending = max(0.0, $totalRequested - $totalProduced);

        $result['rows'] = $formattedRows;
        $result['summary'] = [
            'total_ots' => count($formattedRows),
            'total_produced_units' => $totalProduced,
            'total_requested_units' => $totalRequested,
            'total_pending_units' => $totalPending,
            'total_waste_units' => $totalWaste,
            'total_waste_kg' => $totalWasteKg,
            'waste_percent' => ($totalProduced > 0 && $totalWaste > 0)
                ? round(($totalWaste / $totalProduced) * 100.0, 2)
                : (($totalProducedKg > 0 && $totalWasteKg > 0) ? round(($totalWasteKg / $totalProducedKg) * 100.0, 2) : null),
            'total_hours' => round($totalSeconds / 3600, 1),
        ];

        return $result;
    }

    /**
     * Obtiene la lista de máquinas activas para selección en formularios de edición.
     *
     * @return list<array{id: int, equipo_name: string, equipo_type_id: int}>
     */
    public function getAllActiveMachines(): array
    {
        if (!$this->erpTableExists('equipo')) {
            return [];
        }
        try {
            $stmt = $this->erpPdo->query("SELECT id, equipo_name, equipo_type_id FROM equipo WHERE equipo_status > 0 ORDER BY equipo_name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Obtiene la lista de trabajadores/operadores activos para selección en formularios de edición.
     *
     * @return list<array{id: int, wrk_firstname: string, wrk_lastname: string, wrk_rut: string}>
     */
    public function getAllActiveWorkers(): array
    {
        if (!$this->erpTableExists('workers')) {
            return [];
        }
        try {
            $stmt = $this->erpPdo->query("SELECT id, wrk_firstname, wrk_lastname, wrk_rut FROM workers WHERE wrk_status > 0 ORDER BY wrk_firstname ASC, wrk_lastname ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Modifica de manera profesional y segura un registro de producción por máquina en la base de datos ERP.
     *
     * Permite corregir errores de tipeo o mal ingreso en unidades, kilos, metros, fechas, máquina,
     * operador y mermas con respaldo en una transacción ACID y registro de auditoría.
     *
     * @param int $otId ID de prod_worker_ot (ot_id)
     * @param array<string, mixed> $data Datos modificados desde el formulario modal
     * @param string|null $userName Nombre del usuario que realiza el ajuste para registro de auditoría
     * @return array{ok: bool, message?: string, error?: string}
     */
    public function updateProductionRecord(int $otId, array $data, ?string $userName = null): array
    {
        if ($otId <= 0) {
            return ['ok' => false, 'error' => 'Identificador de registro no válido.'];
        }

        try {
            // 1. Obtener registro base de prod_worker_ot y prod_worker_init
            $stmtBase = $this->erpPdo->prepare("
                SELECT t1.id, t1.wok_ag_id, t1.wok_init_id, t1.wok_status, t1.wok_crtdat, t1.wok_enddat,
                       t3.win_wrkid, t3.win_equipoid
                FROM prod_worker_ot t1
                INNER JOIN prod_worker_init t3 ON t1.wok_init_id = t3.id
                WHERE t1.id = :ot_id
            ");
            $stmtBase->execute([':ot_id' => $otId]);
            $baseRec = $stmtBase->fetch(PDO::FETCH_ASSOC);
            if (!$baseRec) {
                return ['ok' => false, 'error' => 'No se encontró el registro de producción especificado.'];
            }

            $initId = (int)$baseRec['wok_init_id'];

            // 2. Parsear parámetros y valores
            $producedUnits = isset($data['produced_units']) ? max(0.0, (float)$data['produced_units']) : 0.0;
            $producedKg = isset($data['produced_kg']) ? max(0.0, (float)$data['produced_kg']) : 0.0;
            $producedMeters = isset($data['produced_meters']) ? max(0.0, (float)$data['produced_meters']) : 0.0;
            $producedMetersMaquina = isset($data['produced_meters_maquina']) ? max(0.0, (float)$data['produced_meters_maquina']) : 0.0;

            $status = isset($data['wok_status']) ? (int)$data['wok_status'] : (int)$baseRec['wok_status'];
            if ($status < 1 || $status > 2) {
                $status = 2; // Por defecto Terminada
            }

            // Fechas y marcas de tiempo
            $startTs = (int)$baseRec['wok_crtdat'];
            if (!empty($data['started_at'])) {
                try {
                    $dtStart = new DateTimeImmutable((string)$data['started_at']);
                    $startTs = $dtStart->getTimestamp();
                } catch (Throwable) {}
            }

            $endTs = (int)$baseRec['wok_enddat'];
            if (!empty($data['ended_at'])) {
                try {
                    $dtEnd = new DateTimeImmutable((string)$data['ended_at']);
                    $endTs = $dtEnd->getTimestamp();
                } catch (Throwable) {}
            } elseif ($status === 1) {
                // En curso
                $endTs = 0;
            } elseif ($endTs <= 0) {
                $endTs = $startTs > 0 ? $startTs : time();
            }

            // Recursos asignados
            $machineId = isset($data['machine_id']) && (int)$data['machine_id'] > 0 ? (int)$data['machine_id'] : (int)$baseRec['win_equipoid'];
            $operatorId = isset($data['operator_id']) && (int)$data['operator_id'] > 0 ? (int)$data['operator_id'] : (int)$baseRec['win_wrkid'];
            $helperId = isset($data['helper_id']) && (int)$data['helper_id'] > 0 ? (int)$data['helper_id'] : 0;

            // Mermas y desperdicios
            $wasteTotalKg = isset($data['waste_kg']) ? max(0.0, (float)$data['waste_kg']) : 0.0;
            $wasteTotalUnits = isset($data['waste_units']) ? max(0.0, (float)$data['waste_units']) : 0.0;

            $wasteSetupKg = isset($data['waste_setup_kg']) && is_numeric($data['waste_setup_kg']) ? max(0.0, (float)$data['waste_setup_kg']) : null;
            $wasteSetupUnits = isset($data['waste_setup_units']) && is_numeric($data['waste_setup_units']) ? max(0.0, (float)$data['waste_setup_units']) : null;
            $wastePrintKg = isset($data['waste_print_kg']) && is_numeric($data['waste_print_kg']) ? max(0.0, (float)$data['waste_print_kg']) : null;
            $wastePrintUnits = isset($data['waste_print_units']) && is_numeric($data['waste_print_units']) ? max(0.0, (float)$data['waste_print_units']) : null;
            $wasteCoilKg = isset($data['waste_coil_kg']) && is_numeric($data['waste_coil_kg']) ? max(0.0, (float)$data['waste_coil_kg']) : null;
            $wasteCoilUnits = isset($data['waste_coil_units']) && is_numeric($data['waste_coil_units']) ? max(0.0, (float)$data['waste_coil_units']) : null;
            $wasteRepairKg = isset($data['waste_repair_kg']) && is_numeric($data['waste_repair_kg']) ? max(0.0, (float)$data['waste_repair_kg']) : null;
            $wasteRepairUnits = isset($data['waste_repair_units']) && is_numeric($data['waste_repair_units']) ? max(0.0, (float)$data['waste_repair_units']) : null;

            // Comentario / auditoría
            $userComment = trim((string)($data['comments'] ?? ''));
            $editor = trim((string)($userName ?? 'Usuario'));
            $auditStamp = '[' . date('Y-m-d H:i') . ' Modificado por ' . $editor . ($userComment !== '' ? ': ' . $userComment : '') . ']';

            // 3. Iniciar Transacción ACID
            if (!$this->erpPdo->inTransaction()) {
                $this->erpPdo->beginTransaction();
            }

            // Actualizar prod_worker_ot
            $stUpdOt = $this->erpPdo->prepare("
                UPDATE prod_worker_ot 
                SET wok_status = :status, wok_crtdat = :crtdat, wok_enddat = :enddat 
                WHERE id = :ot_id
            ");
            $stUpdOt->execute([
                ':status' => $status,
                ':crtdat' => $startTs,
                ':enddat' => $endTs,
                ':ot_id' => $otId,
            ]);

            // Actualizar prod_worker_init (máquina y operador)
            $stUpdInit = $this->erpPdo->prepare("
                UPDATE prod_worker_init 
                SET win_equipoid = :machine_id, win_wrkid = :operator_id 
                WHERE id = :init_id
            ");
            $stUpdInit->execute([
                ':machine_id' => $machineId,
                ':operator_id' => $operatorId,
                ':init_id' => $initId,
            ]);

            // Buscar evento de producción principal (estrictamente prod, nunca pause/colacion)
            $stEvt = $this->erpPdo->prepare("
                SELECT id, evt_comments FROM prod_worker_ot_events 
                WHERE evt_prod_worker_otid = :ot_id 
                  AND LOWER(evt_type) IN ('prod', 'production', 'prodsericolor')
                ORDER BY id DESC LIMIT 1
            ");
            $stEvt->execute([':ot_id' => $otId]);
            $evtRow = $stEvt->fetch(PDO::FETCH_ASSOC);

            if (!$evtRow) {
                // Fallback: cualquier evento que NO sea pausa ni colación
                $stEvtFallback = $this->erpPdo->prepare("
                    SELECT id, evt_comments FROM prod_worker_ot_events 
                    WHERE evt_prod_worker_otid = :ot_id 
                      AND LOWER(evt_type) NOT IN ('pause', 'colacion', 'mantencion')
                    ORDER BY id DESC LIMIT 1
                ");
                $stEvtFallback->execute([':ot_id' => $otId]);
                $evtRow = $stEvtFallback->fetch(PDO::FETCH_ASSOC);
            }

            if ($evtRow) {
                $evtId = (int)$evtRow['id'];
                $existingComments = trim((string)($evtRow['evt_comments'] ?? ''));
                $newComments = $existingComments !== '' ? ($existingComments . ' | ' . $auditStamp) : $auditStamp;

                $stUpdEvt = $this->erpPdo->prepare("
                    UPDATE prod_worker_ot_events 
                    SET evt_amount = :amount,
                        prod_bobina_kg = :kg,
                        evt_amount_metros_lineales = :metros_lineales,
                        evt_amount_metros_maquina = :metros_maquina,
                        evt_crtdat = :start_ts,
                        evt_enddat = :end_ts,
                        evt_idayudante = :helper_id,
                        evt_comments = :comments
                    WHERE id = :evt_id
                ");
                $stUpdEvt->execute([
                    ':amount' => $producedUnits,
                    ':kg' => $producedKg,
                    ':metros_lineales' => $producedMeters,
                    ':metros_maquina' => $producedMetersMaquina,
                    ':start_ts' => $startTs,
                    ':end_ts' => $endTs,
                    ':helper_id' => $helperId,
                    ':comments' => $newComments,
                    ':evt_id' => $evtId,
                ]);
            } else {
                // Crear evento prod si no existía
                $stInsEvt = $this->erpPdo->prepare("
                    INSERT INTO prod_worker_ot_events (
                        evt_prod_worker_otid, evt_amount, prod_bobina_kg,
                        evt_amount_metros_lineales, evt_amount_metros_maquina,
                        evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments, evt_idayudante
                    ) VALUES (
                        :ot_id, :amount, :kg,
                        :metros_lineales, :metros_maquina,
                        :start_ts, :end_ts, 1, 'prod', :comments, :helper_id
                    )
                ");
                $stInsEvt->execute([
                    ':ot_id' => $otId,
                    ':amount' => $producedUnits,
                    ':kg' => $producedKg,
                    ':metros_lineales' => $producedMeters,
                    ':metros_maquina' => $producedMetersMaquina,
                    ':start_ts' => $startTs,
                    ':end_ts' => $endTs,
                    ':comments' => $auditStamp,
                    ':helper_id' => $helperId,
                ]);
                $evtId = (int)$this->erpPdo->lastInsertId();
            }

            // Gestionar defectos/merma en prod_worker_ot_defectunits vinculados a esta orden
            if ($evtId > 0) {
                $stDef = $this->erpPdo->prepare("
                    SELECT d.id, d.evt_refid, d.evt_type, d.evt_merma_typeid, d.evt_repair_typeid, d.evt_amount, d.evt_kgstounits 
                    FROM prod_worker_ot_defectunits d
                    INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                    WHERE e.evt_prod_worker_otid = :ot_id AND d.evt_status > 0
                ");
                $stDef->execute([':ot_id' => $otId]);
                $defRows = $stDef->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $hasDetailedWaste = ($wasteSetupKg !== null || $wastePrintKg !== null || $wasteCoilKg !== null || $wasteRepairKg !== null);

                if ($hasDetailedWaste) {
                    $targets = [
                        'setup' => ['type' => 'merma', 'merma_typeid' => 6, 'kg' => $wasteSetupKg ?? 0.0, 'units' => $wasteSetupUnits ?? 0.0],
                        'print' => ['type' => 'merma', 'merma_typeid' => 7, 'kg' => $wastePrintKg ?? 0.0, 'units' => $wastePrintUnits ?? 0.0],
                        'coil' => ['type' => 'merma', 'merma_typeid' => 8, 'kg' => $wasteCoilKg ?? 0.0, 'units' => $wasteCoilUnits ?? 0.0],
                        'repair' => ['type' => 'repair', 'merma_typeid' => 0, 'repair_typeid' => 1, 'kg' => $wasteRepairKg ?? 0.0, 'units' => $wasteRepairUnits ?? 0.0],
                    ];

                    foreach ($targets as $k => $t) {
                        $found = null;
                        foreach ($defRows as $dr) {
                            if ($t['type'] === 'repair' && (strtolower((string)$dr['evt_type']) === 'repair' || (int)$dr['evt_repair_typeid'] > 0)) {
                                $found = $dr;
                                break;
                            }
                            if ($t['type'] === 'merma' && (int)$dr['evt_merma_typeid'] === $t['merma_typeid']) {
                                $found = $dr;
                                break;
                            }
                            if ($k === 'setup' && in_array((int)$dr['evt_merma_typeid'], [6, 9], true)) {
                                $found = $dr;
                                break;
                            }
                        }

                        if ($found) {
                            $stUpdDef = $this->erpPdo->prepare("
                                UPDATE prod_worker_ot_defectunits 
                                SET evt_refid = :ref_id, evt_kgstounits = :kg, evt_amount = :amt 
                                WHERE id = :def_id
                            ");
                            $stUpdDef->execute([
                                ':ref_id' => $evtId,
                                ':kg' => $t['kg'],
                                ':amt' => $t['units'],
                                ':def_id' => (int)$found['id'],
                            ]);
                        } elseif ($t['kg'] > 0 || $t['units'] > 0) {
                            $stInsDef = $this->erpPdo->prepare("
                                INSERT INTO prod_worker_ot_defectunits (
                                    evt_refid, evt_type, evt_merma_typeid, evt_repair_typeid,
                                    evt_amount, evt_kgstounits, evt_mtstounits, evt_status, evt_crtdat, evt_comments
                                ) VALUES (
                                    :refid, :type, :mtype, :rtype,
                                    :amt, :kg, 0, 1, :crtdat, 'Modificado via ERP Web'
                                )
                            ");
                            $stInsDef->execute([
                                ':refid' => $evtId,
                                ':type' => $t['type'],
                                ':mtype' => $t['merma_typeid'],
                                ':rtype' => $t['repair_typeid'] ?? 0,
                                ':amt' => $t['units'],
                                ':kg' => $t['kg'],
                                ':crtdat' => $endTs > 0 ? $endTs : time(),
                            ]);
                        }
                    }
                } else {
                    // Merma total directa: actualizar el registro existente garantizando que no se duplique
                    if (!empty($defRows)) {
                        $first = true;
                        foreach ($defRows as $dr) {
                            if ($first) {
                                $stUpdDef = $this->erpPdo->prepare("
                                    UPDATE prod_worker_ot_defectunits 
                                    SET evt_refid = :ref_id, evt_kgstounits = :kg, evt_amount = :amt 
                                    WHERE id = :def_id
                                ");
                                $stUpdDef->execute([
                                    ':ref_id' => $evtId,
                                    ':kg' => $wasteTotalKg,
                                    ':amt' => $wasteTotalUnits,
                                    ':def_id' => (int)$dr['id'],
                                ]);
                                $first = false;
                            } else {
                                $stDeact = $this->erpPdo->prepare("UPDATE prod_worker_ot_defectunits SET evt_status = 0 WHERE id = :def_id");
                                $stDeact->execute([':def_id' => (int)$dr['id']]);
                            }
                        }
                    } elseif ($wasteTotalKg > 0 || $wasteTotalUnits > 0) {
                        $stInsDef = $this->erpPdo->prepare("
                            INSERT INTO prod_worker_ot_defectunits (
                                evt_refid, evt_type, evt_merma_typeid, evt_repair_typeid,
                                evt_amount, evt_kgstounits, evt_mtstounits, evt_status, evt_crtdat, evt_comments
                            ) VALUES (
                                :refid, 'merma', 6, 0,
                                :amt, :kg, 0, 1, :crtdat, 'Ingreso corrección ERP Web'
                            )
                        ");
                        $stInsDef->execute([
                            ':refid' => $evtId,
                            ':amt' => $wasteTotalUnits,
                            ':kg' => $wasteTotalKg,
                            ':crtdat' => $endTs > 0 ? $endTs : time(),
                        ]);
                    }
                }
            }

            $this->erpPdo->commit();
            return ['ok' => true, 'message' => 'Producción actualizada correctamente en la base de datos.'];
        } catch (Throwable $e) {
            if ($this->erpPdo->inTransaction()) {
                $this->erpPdo->rollBack();
            }
            return ['ok' => false, 'error' => 'Error al actualizar producción: ' . $e->getMessage()];
        }
    }

    /**
     * Informe de Colación: pausas operativas tipo colación (pause_id = 1 o código 2200).
     * Corrige el bug del legacy que forzaba worker_id = 19 y cruzaba con ag_crtusr.
     */
    public function getMachineBreaksReport(
        string $startAt,
        string $endAt,
        ?int $plantaId = null,
        ?int $equipoTypeId = null,
        ?int $equipoId = null,
        ?string $search = null
    ): array {
        $result = [
            'plantas' => $this->getPlantasList(),
            'equipo_types' => $this->getEquipoTypesList(),
            'equipos' => [],
            'rows' => [],
            'summary' => [
                'total_records' => 0,
                'completed_records' => 0,
                'in_progress_records' => 0,
                'total_minutes' => 0,
                'total_hours' => 0.0,
                'avg_minutes' => 0.0,
            ],
        ];

        if (!$this->erpTableExists('prod_worker_ot_events') || !$this->erpTableExists('prod_worker_ot')) {
            return $result;
        }

        if (($plantaId === null || $plantaId <= 0) && !empty($result['plantas'])) {
            $plantaId = (int)$result['plantas'][0]['id'];
        }
        $result['equipos'] = $plantaId !== null ? $this->getEquiposByPlantaAndType($plantaId, $equipoTypeId) : [];

        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = strtotime($startAt) ?: 0;
            $endTs = strtotime($endAt) ?: 0;
        }

        $where = [
            "pwoe.evt_type = 'pause'",
            "(pwoe.evt_pause_id = 1 OR pwoe.evt_pause_id = '1' OR LOWER(ppt.pause_name) LIKE '%colaci%')",
            "pwoe.evt_crtdat BETWEEN :start_ts AND :end_ts",
            "pwoe.evt_status > 0",
        ];
        $params = [
            ':start_ts' => $startTs,
            ':end_ts' => $endTs,
        ];

        if ($plantaId !== null && $plantaId > 0 && $this->erpColumnExists('prod_worker_init', 'win_plantaid')) {
            $where[] = "pwi.win_plantaid = :planta_id";
            $params[':planta_id'] = $plantaId;
        }

        if ($equipoTypeId !== null && $equipoTypeId > 0) {
            $where[] = "e.equipo_type_id = :equipo_type_id";
            $params[':equipo_type_id'] = $equipoTypeId;
        }

        if ($equipoId !== null && $equipoId > 0) {
            $where[] = "e.id = :equipo_id";
            $params[':equipo_id'] = $equipoId;
        }

        if ($search !== null && trim($search) !== '') {
            $s = '%' . trim($search) . '%';
            $where[] = "(h.prd_number LIKE :s1 OR o.req_number LIKE :s2 OR c.cust_name LIKE :s3 OR w.wrk_firstname LIKE :s4 OR w.wrk_lastname LIKE :s5 OR e.equipo_name LIKE :s6 OR w.wrk_rut LIKE :s7)";
            $params[':s1'] = $s;
            $params[':s2'] = $s;
            $params[':s3'] = $s;
            $params[':s4'] = $s;
            $params[':s5'] = $s;
            $params[':s6'] = $s;
            $params[':s7'] = $s;
        }

        $sql = "
            SELECT 
                pwoe.id AS event_id,
                pwot.id AS pwo_id,
                pwot.wok_crtdat AS shift_start,
                pwot.wok_enddat AS shift_end,
                pwoe.evt_crtdat AS break_start,
                pwoe.evt_enddat AS break_end,
                pwoe.evt_comments AS break_comments,
                e.id AS machine_id,
                e.equipo_name AS machine_name,
                et.id AS machine_type_id,
                et.type_ant_title AS process_name,
                w.id AS worker_id,
                w.wrk_rut,
                CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, '')) AS worker_name,
                h.prd_number AS ot_number,
                o.req_number AS cc_number,
                c.cust_name AS customer_name
            FROM prod_worker_ot_events pwoe
            LEFT JOIN prod_pause_types ppt ON ppt.id = pwoe.evt_pause_id
            INNER JOIN prod_worker_ot pwot ON pwot.id = pwoe.evt_prod_worker_otid
            INNER JOIN prod_worker_init pwi ON pwi.id = pwot.wok_init_id
            LEFT JOIN workers w ON w.id = pwi.win_wrkid
            LEFT JOIN equipo e ON e.id = pwi.win_equipoid
            LEFT JOIN equipo_type et ON et.id = e.equipo_type_id
            LEFT JOIN prod_agenda pa ON pa.id = pwot.wok_ag_id
            LEFT JOIN prod_header h ON h.id = pa.ag_prdid
            LEFT JOIN orders o ON o.id = pa.ag_reqid
            LEFT JOIN customer c ON c.id = o.req_cust_id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY pwoe.evt_crtdat DESC
        ";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            $rawRows = $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            $rawRows = [];
        }

        $totalMinutes = 0;
        $completedCount = 0;
        $inProgressCount = 0;
        $rows = [];

        // Carga batch de turnos para evitar N+1 queries y acelerar la respuesta
        $turnoCache = [];
        $workerIds = array_values(array_unique(array_filter(array_map(static fn($r) => (int)($r['worker_id'] ?? 0), $rawRows))));
        if (!empty($workerIds)) {
            $years = [];
            foreach ($rawRows as $r) {
                $bs = (int)($r['break_start'] ?? 0);
                if ($bs > 0) {
                    $years[] = (int)date('Y', $bs);
                }
            }
            $years = array_unique($years);
            if (empty($years)) {
                $years = [(int)date('Y')];
            }
            $minY = min($years);
            $maxY = max($years);

            $inPlaceholders = implode(',', array_fill(0, count($workerIds), '?'));
            $batchSql = "
                SELECT tca.assign_worker_id, tca.assign_year, tca.assign_month, tca.assign_day,
                       tt.type_name_short, tt.type_name
                FROM turnos_config_assign tca
                INNER JOIN turnos_types tt ON tt.id = tca.assign_turno_type_id
                WHERE tca.assign_worker_id IN ($inPlaceholders)
                  AND tca.assign_year BETWEEN ? AND ?
            ";
            try {
                $bStmt = $this->erpPdo->prepare($batchSql);
                $bParams = array_merge($workerIds, [$minY, $maxY]);
                $bStmt->execute($bParams);
                while ($tRow = $bStmt->fetch()) {
                    $w = (int)$tRow['assign_worker_id'];
                    $y = (int)$tRow['assign_year'];
                    $m = (int)$tRow['assign_month'];
                    $d = (int)$tRow['assign_day'];
                    $key = "{$w}_{$y}_{$m}_{$d}";
                    $code = trim((string)($tRow['type_name_short'] ?? $tRow['type_name'] ?? 'N/D'));
                    $turnoCache[$key] = $code;
                }
            } catch (Throwable) {
                // Silencioso si falla la tabla de turnos
            }
        }

        foreach ($rawRows as $row) {
            $bStart = (int)($row['break_start'] ?? 0);
            $bEnd = (int)($row['break_end'] ?? 0);
            $wId = (int)($row['worker_id'] ?? 0);

            $diffSeconds = ($bEnd > 0 && $bEnd >= $bStart) ? ($bEnd - $bStart) : 0;
            $durationMinutes = (int)round($diffSeconds / 60);
            $hours = (int)floor($durationMinutes / 60);
            $mins = $durationMinutes % 60;
            $durationStr = $bEnd > 0 ? sprintf('%02d:%02d h', $hours, $mins) : 'En curso';
            $statusLabel = $bEnd > 0 ? 'Terminado' : 'En curso';

            if ($bEnd > 0) {
                $completedCount++;
                $totalMinutes += $durationMinutes;
            } else {
                $inProgressCount++;
            }

            // Buscar código de turno real del trabajador para ese día desde el mapa batch
            $shiftCode = 'N/D';
            if ($wId > 0 && $bStart > 0) {
                $y = (int)date('Y', $bStart);
                $m = (int)date('m', $bStart);
                $d = (int)date('d', $bStart);
                $cacheKey = "{$wId}_{$y}_{$m}_{$d}";
                if (isset($turnoCache[$cacheKey])) {
                    $shiftCode = $turnoCache[$cacheKey];
                }
            }

            $sStart = (int)($row['shift_start'] ?? 0);
            $sEnd = (int)($row['shift_end'] ?? 0);

            $rows[] = [
                'event_id' => (int)$row['event_id'],
                'shift_start' => $sStart > 0 ? date('d/m/Y H:i', $sStart) : '-',
                'shift_end' => $sEnd > 0 ? date('d/m/Y H:i', $sEnd) : '-',
                'shift_code' => $shiftCode !== '' ? $shiftCode : 'General',
                'machine_name' => trim((string)($row['machine_name'] ?? 'Sin asignar')),
                'process_name' => trim((string)($row['process_name'] ?? 'General')),
                'worker_rut' => trim((string)($row['wrk_rut'] ?? '')),
                'worker_name' => trim((string)($row['worker_name'] ?? 'Sin asignar')),
                'ot_number' => trim((string)($row['ot_number'] ?? '')),
                'cc_number' => trim((string)($row['cc_number'] ?? '')),
                'customer_name' => trim((string)($row['customer_name'] ?? '')),
                'break_start' => $bStart > 0 ? date('d/m/Y H:i', $bStart) : '-',
                'break_end' => $bEnd > 0 ? date('d/m/Y H:i', $bEnd) : '-',
                'duration_minutes' => $durationMinutes,
                'duration_str' => $durationStr,
                'status' => $statusLabel,
                'comments' => trim((string)($row['break_comments'] ?? '')),
            ];
        }

        $result['rows'] = $rows;
        $totalRecords = count($rows);
        $result['summary'] = [
            'total_records' => $totalRecords,
            'completed_records' => $completedCount,
            'in_progress_records' => $inProgressCount,
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 1),
            'avg_minutes' => $completedCount > 0 ? round($totalMinutes / $completedCount, 1) : 0.0,
        ];

        return $result;
    }

    /**
     * Informe de Detenciones: paradas de máquina distintas de colación.
     * Muestra motivo de parada, clasificación, máquina, tiempos, operario y observaciones.
     */
    public function getMachineStopsReport(
        string $startAt,
        string $endAt,
        ?int $plantaId = null,
        ?int $equipoTypeId = null,
        ?int $equipoId = null,
        ?int $pauseId = null,
        ?string $search = null
    ): array {
        $result = [
            'plantas' => $this->getPlantasList(),
            'equipo_types' => $this->getEquipoTypesList(),
            'equipos' => [],
            'pause_types' => [],
            'rows' => [],
            'summary' => [
                'total_stops' => 0,
                'total_minutes' => 0,
                'total_hours' => 0.0,
                'avg_minutes' => 0.0,
                'by_reason' => [],
            ],
        ];

        if (!$this->erpTableExists('prod_worker_ot_events') || !$this->erpTableExists('prod_worker_ot')) {
            return $result;
        }

        if (($plantaId === null || $plantaId <= 0) && !empty($result['plantas'])) {
            $plantaId = (int)$result['plantas'][0]['id'];
        }
        $result['equipos'] = $plantaId !== null ? $this->getEquiposByPlantaAndType($plantaId, $equipoTypeId) : [];

        // Obtener lista de motivos de parada para filtro
        try {
            $result['pause_types'] = $this->erpPdo->query("
                SELECT id, pause_code, pause_name 
                FROM prod_pause_types 
                WHERE pause_status > 0 AND (id != 1 AND pause_code != '2200')
                ORDER BY pause_name ASC
            ")->fetchAll() ?: [];
        } catch (Throwable) {
            $result['pause_types'] = [];
        }

        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = strtotime($startAt) ?: 0;
            $endTs = strtotime($endAt) ?: 0;
        }

        $where = [
            "pwoe.evt_type = 'pause'",
            "(pwoe.evt_pause_id != 1 AND pwoe.evt_pause_id != '1' AND (ppt.pause_code IS NULL OR ppt.pause_code != '2200'))",
            "pwoe.evt_crtdat BETWEEN :start_ts AND :end_ts",
            "pwoe.evt_status > 0",
        ];
        $params = [
            ':start_ts' => $startTs,
            ':end_ts' => $endTs,
        ];

        if ($plantaId !== null && $plantaId > 0 && $this->erpColumnExists('prod_worker_init', 'win_plantaid')) {
            $where[] = "pwi.win_plantaid = :planta_id";
            $params[':planta_id'] = $plantaId;
        }

        if ($equipoTypeId !== null && $equipoTypeId > 0) {
            $where[] = "e.equipo_type_id = :equipo_type_id";
            $params[':equipo_type_id'] = $equipoTypeId;
        }

        if ($equipoId !== null && $equipoId > 0) {
            $where[] = "e.id = :equipo_id";
            $params[':equipo_id'] = $equipoId;
        }

        if ($pauseId !== null && $pauseId > 0) {
            $where[] = "pwoe.evt_pause_id = :pause_id";
            $params[':pause_id'] = $pauseId;
        }

        if ($search !== null && trim($search) !== '') {
            $s = '%' . trim($search) . '%';
            $where[] = "(ppt.pause_name LIKE :s1 OR pwoe.evt_comments LIKE :s2 OR e.equipo_name LIKE :s3 OR w.wrk_firstname LIKE :s4 OR w.wrk_lastname LIKE :s5 OR h.prd_number LIKE :s6 OR o.req_number LIKE :s7)";
            $params[':s1'] = $s;
            $params[':s2'] = $s;
            $params[':s3'] = $s;
            $params[':s4'] = $s;
            $params[':s5'] = $s;
            $params[':s6'] = $s;
            $params[':s7'] = $s;
        }

        $sql = "
            SELECT 
                pwoe.id AS event_id,
                pwot.id AS pwo_id,
                pwoe.evt_crtdat AS stop_start,
                pwoe.evt_enddat AS stop_end,
                pwoe.evt_comments AS stop_comments,
                pwoe.evt_pause_id AS pause_id,
                COALESCE(ppt.pause_code, '') AS pause_code,
                COALESCE(ppt.pause_name, 'Detención sin motivo') AS stop_reason,
                COALESCE(p.descripcion, '') AS stop_classification,
                e.id AS machine_id,
                e.equipo_name AS machine_name,
                et.id AS machine_type_id,
                et.type_ant_title AS process_name,
                w.id AS worker_id,
                w.wrk_rut,
                CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, '')) AS worker_name,
                h.prd_number AS ot_number,
                o.req_number AS cc_number,
                c.cust_name AS customer_name
            FROM prod_worker_ot_events pwoe
            LEFT JOIN prod_pause_types ppt ON ppt.id = pwoe.evt_pause_id
            LEFT JOIN parametros p ON (p.tabla = 'CLASIFICA' AND p.codigo = ppt.pause_clasifica)
            INNER JOIN prod_worker_ot pwot ON pwot.id = pwoe.evt_prod_worker_otid
            INNER JOIN prod_worker_init pwi ON pwi.id = pwot.wok_init_id
            LEFT JOIN workers w ON w.id = pwi.win_wrkid
            LEFT JOIN equipo e ON e.id = pwi.win_equipoid
            LEFT JOIN equipo_type et ON et.id = e.equipo_type_id
            LEFT JOIN prod_agenda pa ON pa.id = pwot.wok_ag_id
            LEFT JOIN prod_header h ON h.id = pa.ag_prdid
            LEFT JOIN orders o ON o.id = pa.ag_reqid
            LEFT JOIN customer c ON c.id = o.req_cust_id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY pwoe.evt_crtdat DESC
        ";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            $rawRows = $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            $rawRows = [];
        }

        $totalMinutes = 0;
        $reasonCounts = [];
        $rows = [];

        foreach ($rawRows as $row) {
            $sStart = (int)($row['stop_start'] ?? 0);
            $sEnd = (int)($row['stop_end'] ?? 0);
            $diffSeconds = ($sEnd > 0 && $sEnd >= $sStart) ? ($sEnd - $sStart) : 0;
            $durationMinutes = (int)round($diffSeconds / 60);
            $hours = (int)floor($durationMinutes / 60);
            $mins = $durationMinutes % 60;
            $durationStr = $sEnd > 0 ? sprintf('%02d:%02d h', $hours, $mins) : 'En curso';

            $totalMinutes += $durationMinutes;
            $reason = trim((string)$row['stop_reason']);
            if (!isset($reasonCounts[$reason])) {
                $reasonCounts[$reason] = ['count' => 0, 'minutes' => 0];
            }
            $reasonCounts[$reason]['count']++;
            $reasonCounts[$reason]['minutes'] += $durationMinutes;

            $rows[] = [
                'event_id' => (int)$row['event_id'],
                'worker_name' => trim((string)($row['worker_name'] ?? 'Sin asignar')),
                'worker_rut' => trim((string)($row['wrk_rut'] ?? '')),
                'process_name' => trim((string)($row['process_name'] ?? 'General')),
                'machine_id' => (int)($row['machine_id'] ?? 0),
                'machine_name' => trim((string)($row['machine_name'] ?? 'Sin asignar')),
                'pause_code' => trim((string)$row['pause_code']),
                'stop_reason' => $reason,
                'comments' => trim((string)($row['stop_comments'] ?? '')),
                'classification' => trim((string)$row['stop_classification']),
                'date' => $sStart > 0 ? date('d/m/Y', $sStart) : '-',
                'start_time' => $sStart > 0 ? date('H:i', $sStart) : '-',
                'end_date' => $sEnd > 0 ? date('d/m/Y', $sEnd) : '-',
                'end_time' => $sEnd > 0 ? date('H:i', $sEnd) : '-',
                'duration_minutes' => $durationMinutes,
                'duration_str' => $durationStr,
                'status' => $sEnd > 0 ? 'Terminada' : 'En curso',
                'ot_number' => trim((string)($row['ot_number'] ?? '')),
                'cc_number' => trim((string)($row['cc_number'] ?? '')),
                'customer_name' => trim((string)($row['customer_name'] ?? '')),
            ];
        }

        $result['rows'] = $rows;
        $totalStops = count($rows);
        $result['summary'] = [
            'total_stops' => $totalStops,
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 1),
            'avg_minutes' => $totalStops > 0 ? round($totalMinutes / $totalStops, 1) : 0.0,
            'by_reason' => $reasonCounts,
        ];

        return $result;
    }

    /**
     * Informe de Cambio de Configuración: eventos tipo 'apertura' (setup de máquinas / cambio de medida).
     */
    public function getMachineSetupReport(
        string $startAt,
        string $endAt,
        ?int $plantaId = null,
        ?int $equipoTypeId = null,
        ?int $equipoId = null,
        ?string $search = null
    ): array {
        $result = [
            'plantas' => $this->getPlantasList(),
            'equipo_types' => $this->getEquipoTypesList(),
            'equipos' => [],
            'rows' => [],
            'summary' => [
                'total_setups' => 0,
                'total_minutes' => 0,
                'total_hours' => 0.0,
                'avg_minutes' => 0.0,
            ],
        ];

        if (!$this->erpTableExists('prod_worker_ot_events') || !$this->erpTableExists('prod_worker_ot')) {
            return $result;
        }

        if (($plantaId === null || $plantaId <= 0) && !empty($result['plantas'])) {
            $plantaId = (int)$result['plantas'][0]['id'];
        }
        $result['equipos'] = $plantaId !== null ? $this->getEquiposByPlantaAndType($plantaId, $equipoTypeId) : [];

        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = strtotime($startAt) ?: 0;
            $endTs = strtotime($endAt) ?: 0;
        }

        $where = [
            "pwoe.evt_type = 'apertura'",
            "pwoe.evt_medida_fromid != pwoe.evt_medida_toid",
            "pwoe.evt_crtdat BETWEEN :start_ts AND :end_ts",
            "pwoe.evt_status > 0",
        ];
        $params = [
            ':start_ts' => $startTs,
            ':end_ts' => $endTs,
        ];

        if ($plantaId !== null && $plantaId > 0 && $this->erpColumnExists('prod_worker_init', 'win_plantaid')) {
            $where[] = "pwi.win_plantaid = :planta_id";
            $params[':planta_id'] = $plantaId;
        }

        if ($equipoTypeId !== null && $equipoTypeId > 0) {
            $where[] = "e.equipo_type_id = :equipo_type_id";
            $params[':equipo_type_id'] = $equipoTypeId;
        }

        if ($equipoId !== null && $equipoId > 0) {
            $where[] = "e.id = :equipo_id";
            $params[':equipo_id'] = $equipoId;
        }

        if ($search !== null && trim($search) !== '') {
            $s = '%' . trim($search) . '%';
            $where[] = "(h.prd_number LIKE :s1 OR o.req_number LIKE :s2 OR c.cust_name LIKE :s3 OR w.wrk_firstname LIKE :s4 OR w.wrk_lastname LIKE :s5 OR e.equipo_name LIKE :s6)";
            $params[':s1'] = $s;
            $params[':s2'] = $s;
            $params[':s3'] = $s;
            $params[':s4'] = $s;
            $params[':s5'] = $s;
            $params[':s6'] = $s;
        }

        $sql = "
            SELECT 
                pwoe.id AS event_id,
                pwot.id AS pwo_id,
                pwoe.evt_crtdat AS setup_start,
                pwoe.evt_enddat AS setup_end,
                pwoe.evt_comments AS setup_comments,
                pwoe.evt_medida_fromid,
                pwoe.evt_medida_toid,
                pm_from.med_name AS from_size,
                pm_from.med_tipoproducto AS from_tipo,
                pm_to.med_name AS to_size,
                pm_to.med_tipoproducto AS to_tipo,
                e.id AS machine_id,
                e.equipo_name AS machine_name,
                et.id AS machine_type_id,
                et.type_ant_title AS process_name,
                w.id AS worker_id,
                w.wrk_rut,
                CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, '')) AS worker_name,
                h.prd_number AS ot_number,
                o.req_number AS cc_number,
                c.cust_name AS customer_name
            FROM prod_worker_ot_events pwoe
            INNER JOIN prod_worker_ot pwot ON pwot.id = pwoe.evt_prod_worker_otid
            INNER JOIN prod_worker_init pwi ON pwi.id = pwot.wok_init_id
            LEFT JOIN workers w ON w.id = pwi.win_wrkid
            LEFT JOIN equipo e ON e.id = pwi.win_equipoid
            LEFT JOIN equipo_type et ON et.id = e.equipo_type_id
            LEFT JOIN prod_agenda pa ON pa.id = pwot.wok_ag_id
            LEFT JOIN prod_header h ON h.id = pa.ag_prdid
            LEFT JOIN orders o ON o.id = pa.ag_reqid
            LEFT JOIN customer c ON c.id = o.req_cust_id
            LEFT JOIN prod_medidas pm_from ON pm_from.id = pwoe.evt_medida_fromid
            LEFT JOIN prod_medidas pm_to ON pm_to.id = pwoe.evt_medida_toid
            WHERE " . implode(" AND ", $where) . "
            ORDER BY pwoe.evt_crtdat DESC
        ";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            $rawRows = $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            $rawRows = [];
        }

        $totalMinutes = 0;
        $rows = [];

        foreach ($rawRows as $row) {
            $sStart = (int)($row['setup_start'] ?? 0);
            $sEnd = (int)($row['setup_end'] ?? 0);
            $diffSeconds = ($sEnd > 0 && $sEnd >= $sStart) ? ($sEnd - $sStart) : 0;
            $durationMinutes = (int)round($diffSeconds / 60);
            $hours = (int)floor($durationMinutes / 60);
            $mins = $durationMinutes % 60;
            $durationStr = $sEnd > 0 ? sprintf('%02d:%02d h', $hours, $mins) : 'En curso';

            $totalMinutes += $durationMinutes;

            $formatFrom = trim((string)($row['from_size'] ?? ''));
            $formatTo = trim((string)($row['to_size'] ?? ''));

            $ft = (int)($row['from_tipo'] ?? 0);
            $tt = (int)($row['to_tipo'] ?? 0);
            $aplica = 'NO';
            if ($ft === 3 && $tt === 3) {
                $aplica = 'SI';
            } elseif ($ft > 0 && $tt > 0 && $ft !== $tt) {
                $aplica = 'SI';
            }

            $rows[] = [
                'event_id' => (int)$row['event_id'],
                'ot_number' => trim((string)($row['ot_number'] ?? '')),
                'cc_number' => trim((string)($row['cc_number'] ?? '')),
                'customer_name' => trim((string)($row['customer_name'] ?? '')),
                'machine_name' => trim((string)($row['machine_name'] ?? 'Sin asignar')),
                'process_name' => trim((string)($row['process_name'] ?? 'General')),
                'worker_name' => trim((string)($row['worker_name'] ?? 'Sin asignar')),
                'worker_rut' => trim((string)($row['wrk_rut'] ?? '')),
                'setup_start' => $sStart > 0 ? date('d/m/Y H:i', $sStart) : '-',
                'setup_end' => $sEnd > 0 ? date('d/m/Y H:i', $sEnd) : '-',
                'duration_minutes' => $durationMinutes,
                'duration_str' => $durationStr,
                'format_from' => $formatFrom !== '' ? $formatFrom : 'Sin especif.',
                'format_to' => $formatTo !== '' ? $formatTo : 'Sin especif.',
                'is_format_change' => ((int)$row['evt_medida_fromid'] !== (int)$row['evt_medida_toid']),
                'aplica' => $aplica,
                'status' => $sEnd > 0 ? 'Terminada' : 'En curso',
                'comments' => trim((string)($row['setup_comments'] ?? '')),
            ];
        }

        $result['rows'] = $rows;
        $totalSetups = count($rows);
        $result['summary'] = [
            'total_setups' => $totalSetups,
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 1),
            'avg_minutes' => $totalSetups > 0 ? round($totalMinutes / $totalSetups, 1) : 0.0,
        ];

        return $result;
    }

    /**
     * Obtiene las órdenes de trabajo (OTs) cuya tasa de merma supere el umbral especificado (por defecto > 5.0%)
     * en el período indicado, para generar alertas proactivas en el Dashboard ERP.
     */
    public function getCriticalWasteWorkOrders(string $startAt, string $endAt, float $threshold = 5.0): array
    {
        if (!$this->erpTableExists('prod_worker_ot_defectunits') || !$this->erpTableExists('prod_header')) {
            return [];
        }

        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = strtotime($startAt) ?: 0;
            $endTs = strtotime($endAt) ?: 0;
        }
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return [];
        }

        try {
            $sqlDefects = "
                SELECT 
                    d.id AS defect_id,
                    d.evt_amount,
                    d.evt_kgstounits,
                    COALESCE(pa.ag_equipo_id, pwi.win_equipoid, 0) AS machine_id,
                    eq.equipo_name,
                    et.type_ant_title AS process_name,
                    ph.prd_number AS work_order_number,
                    COALESCE(ord.req_number, ph.prd_reqid) AS cost_center,
                    COALESCE(c.cust_name, 'Cliente no asignado') AS customer_name,
                    pa.ag_amount AS requested_units,
                    t11.item_weight,
                    t10.fab_med_width,
                    t10.fab_med_height,
                    t10.fab_med_fuelle,
                    t10.fab_mat_gramms,
                    t10.fab_manilla_length
                FROM prod_worker_ot_defectunits d
                INNER JOIN prod_worker_ot_events e ON e.id = d.evt_refid
                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
                LEFT JOIN orders ord ON ord.id = pa.ag_reqid
                LEFT JOIN customer c ON c.id = ord.req_cust_id
                LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
                LEFT JOIN equipo eq ON eq.id = COALESCE(pa.ag_equipo_id, pwi.win_equipoid)
                LEFT JOIN equipo_type et ON et.id = eq.equipo_type_id
                LEFT JOIN orders_items t10 ON t10.req_id = pa.ag_reqid
                LEFT JOIN item t11 ON t11.id = t10.item_id
                WHERE d.evt_crtdat BETWEEN :start_ts AND :end_ts
                  AND LOWER(d.evt_type) = 'merma'
                ORDER BY d.id ASC
            ";

            $stmt = $this->erpPdo->prepare($sqlDefects);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $defects = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $byOtMap = [];
            foreach ($defects as $r) {
                $itemWeight = (float)($r['item_weight'] ?? 0);
                $w = (float)($r['fab_med_width'] ?? 0);
                $h = (float)($r['fab_med_height'] ?? 0);
                $f = (float)($r['fab_med_fuelle'] ?? 0);
                $gramms = (float)($r['fab_mat_gramms'] ?? 0);
                $manLen = (float)($r['fab_manilla_length'] ?? 0);

                $unitWeightKg = 0.0;
                if ($itemWeight > 0) {
                    $unitWeightKg = $itemWeight / 1000.0;
                } elseif ($w > 0 && $h > 0 && $gramms > 0) {
                    $areaM2 = (2.0 * ($w + $f) * $h) / 10000.0;
                    $bodyKg = $areaM2 * ($gramms / 1000.0);
                    $manillaKg = ($manLen > 0) ? (2.0 * ($manLen / 100.0) * 0.025 * ($gramms / 1000.0)) : 0.0;
                    $unitWeightKg = $bodyKg + $manillaKg;
                }

                $kg = (float)($r['evt_kgstounits'] ?? 0);
                $rawUnits = (float)($r['evt_amount'] ?? 0);

                if ($unitWeightKg > 0 && $kg > 0) {
                    $units = (float)round($kg / $unitWeightKg);
                } else {
                    $units = $rawUnits;
                }

                $ot = trim((string)($r['work_order_number'] ?? ''));
                if ($ot === '') {
                    continue;
                }

                if (!isset($byOtMap[$ot])) {
                    $byOtMap[$ot] = [
                        'ot_number' => $ot,
                        'cost_center' => trim((string)($r['cost_center'] ?? '')),
                        'customer_name' => trim((string)($r['customer_name'] ?? '')),
                        'process_name' => trim((string)($r['process_name'] ?? 'General')),
                        'machine_name' => trim((string)($r['equipo_name'] ?? 'Máquina')),
                        'requested_units' => (float)($r['requested_units'] ?? 0.0),
                        'good_units' => 0,
                        'waste_units' => 0,
                        'total_units' => 0,
                        'waste_rate' => 0.0,
                    ];
                }
                $byOtMap[$ot]['waste_units'] += (int)round($units);
            }

            if (empty($byOtMap)) {
                return [];
            }

            $otNumbers = array_keys($byOtMap);
            $otPlaceholders = [];
            $otParams = [':start_ts' => $startTs, ':end_ts' => $endTs];
            foreach (array_values($otNumbers) as $idx => $otNum) {
                $ph = ':ot_num_' . $idx;
                $otPlaceholders[] = $ph;
                $otParams[$ph] = $otNum;
            }
            $stmtOtProd = $this->erpPdo->prepare("
                SELECT
                    ph.prd_number,
                    COALESCE(SUM(t.produced_units), 0) AS produced_units
                FROM (
                    SELECT
                        ph2.prd_number,
                        event_date,
                        MAX(sum_units) AS produced_units
                    FROM (
                        SELECT
                            ph3.prd_number,
                            DATE(FROM_UNIXTIME(e.evt_crtdat)) AS event_date,
                            LOWER(e.evt_type) AS evt_type,
                            SUM(e.evt_amount) AS sum_units
                        FROM prod_worker_ot_events e
                        INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                        INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                        INNER JOIN prod_header ph3 ON ph3.id = pa.ag_prdid
                        WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                          AND ph3.prd_number IN (" . implode(',', $otPlaceholders) . ")
                          AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                        GROUP BY ph3.prd_number, DATE(FROM_UNIXTIME(e.evt_crtdat)), LOWER(e.evt_type)
                    ) x
                    INNER JOIN prod_header ph2 ON ph2.prd_number = x.prd_number
                    GROUP BY ph2.prd_number, event_date
                ) t
                INNER JOIN prod_header ph ON ph.prd_number = t.prd_number
                GROUP BY ph.prd_number
            ");
            $stmtOtProd->execute($otParams);
            $prodByOt = [];
            foreach ($stmtOtProd->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $prodByOt[$row['prd_number']] = (float)($row['produced_units'] ?? 0.0);
            }

            $critical = [];
            foreach ($byOtMap as $ot => $data) {
                $prod = (float)($prodByOt[$ot] ?? 0.0);
                $waste = (float)$data['waste_units'];
                $base = $prod > 0 ? $prod : (float)$data['requested_units'];
                $rate = $base > 0 ? round(($waste / $base) * 100.0, 2) : 0.0;
                
                $data['good_units'] = (int)max(0.0, $prod - $waste);
                $data['total_units'] = (int)$prod;
                $data['waste_rate'] = $rate;

                if ($rate > $threshold && $waste > 0) {
                    $critical[] = $data;
                }
            }

            usort($critical, static fn($a, $b) => $b['waste_rate'] <=> $a['waste_rate']);
            return $critical;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Informe de Nivel de Servicio / Despachos (OTIF):
     * Basado en despacho, detalle_despacho y orders_delivery.
     */
    public function getServiceLevelReport(
        string $startAt,
        string $endAt,
        ?int $plantaId = null,
        ?string $search = null,
        ?string $delayStatus = 'all',
        ?string $delayDays = null
    ): array {
        $result = [
            'plantas' => $this->getPlantasList(),
            'rows' => [],
            'summary' => [
                'total_dispatches' => 0,
                'on_time_dispatches' => 0,
                'delayed_dispatches' => 0,
                'service_level_percent' => 0.0,
                'total_dispatched_units' => 0.0,
                'filtered_count' => 0,
                'filtered_units' => 0.0,
                'filtered_on_time' => 0,
                'filtered_delayed' => 0,
                'filtered_service_level_percent' => 0.0,
                'has_delay_filter' => false,
            ],
        ];

        if (!$this->erpTableExists('despacho') || !$this->erpTableExists('detalle_despacho')) {
            return $result;
        }

        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = strtotime($startAt) ?: 0;
            $endTs = strtotime($endAt) ?: 0;
        }

        $where = [
            "d.fecha_ingreso BETWEEN :start_ts AND :end_ts",
        ];
        $params = [
            ':start_ts' => $startTs,
            ':end_ts' => $endTs,
        ];

        if ($search !== null && trim($search) !== '') {
            $s = '%' . trim($search) . '%';
            $where[] = "(c.cust_name LIKE :s1 OR c.cust_company LIKE :s2 OR o1.req_number LIKE :s3 OR d.numero_documento LIKE :s4 OR od1.dlv_docnum LIKE :s5 OR i.item_number_prod LIKE :s6)";
            $params[':s1'] = $s;
            $params[':s2'] = $s;
            $params[':s3'] = $s;
            $params[':s4'] = $s;
            $params[':s5'] = $s;
            $params[':s6'] = $s;
        }

        $sql = "
            SELECT 
                d.id AS despacho_id,
                d.mes,
                d.año,
                d.fecha_ingreso,
                d.hora_ingreso,
                d.hora_salida,
                d.numero_documento,
                d.tipo_documento,
                d.estado,
                d.observacion,
                d.sello,
                d.cantidad_pallet,
                d.cantidad_cajas,
                COALESCE(c.cust_company, c.cust_name, 'Cliente N/D') AS customer_name,
                p.descripcion AS sales_channel,
                o1.id AS order_id,
                o1.req_number AS cc_number,
                o1.req_crtdat AS order_date,
                od1.id AS orders_delivery_id,
                od1.dlv_delivery_date AS committed_date,
                od1.dlv_docnum AS guia_factura,
                i.item_number_prod AS product_code,
                dd.salida AS dispatched_qty,
                t1.trans_name AS transport_company,
                tv.transports_vh_patente AS vehicle_plate,
                CONCAT(COALESCE(tc.transports_chofer_nombre, ''), ' ', COALESCE(tc.transports_chofer_paterno, '')) AS driver_name,
                tc.transports_chofer_rut AS driver_rut
            FROM despacho d
            INNER JOIN detalle_despacho dd ON d.id = dd.id_despacho
            INNER JOIN customer c ON c.id = d.id_cliente
            LEFT OUTER JOIN transports_chofer tc ON tc.id = d.id_chofer
            LEFT OUTER JOIN transports_vehiculo tv ON tv.id = d.id_patente
            LEFT OUTER JOIN transports t1 ON t1.id = d.id_transporte
            LEFT OUTER JOIN parametros p ON p.tabla = 'CANAL' AND p.codigo = c.cust_canal
            LEFT OUTER JOIN item i ON i.id = dd.id_item
            LEFT OUTER JOIN orders_delivery od1 ON d.numero_documento = od1.id
            LEFT OUTER JOIN orders o1 ON o1.id = od1.dlv_order_id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY d.fecha_ingreso DESC
        ";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            $rawRows = $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            $rawRows = [];
        }

        // Cargar ajustes manuales registrados (acuerdos con clientes, falta de pago, etc.)
        $adjustments = [];
        try {
            $adjStmt = $this->pdo->query("SELECT * FROM service_level_adjustments");
            while ($adjRow = $adjStmt->fetch(PDO::FETCH_ASSOC)) {
                $adjustments[(int)$adjRow['despacho_id']] = $adjRow;
            }
        } catch (Throwable) {
            $adjustments = [];
        }

        $totalDispatches = 0;
        $onTimeCount = 0;
        $delayedCount = 0;
        $totalDisp = 0.0;
        $allRows = [];

        foreach ($rawRows as $row) {
            $despachoId = (int)$row['despacho_id'];
            $ordersDeliveryId = !empty($row['orders_delivery_id']) ? (int)$row['orders_delivery_id'] : null;
            $fIngreso = (int)($row['fecha_ingreso'] ?? 0);
            $committed = (int)($row['committed_date'] ?? 0);
            $dispUnits = (float)($row['dispatched_qty'] ?? 0);

            $hasAdj = isset($adjustments[$despachoId]);
            $adj = $hasAdj ? $adjustments[$despachoId] : null;

            $origCommitted = $committed;
            $isJustified = false;
            $reasonCategory = '';
            $reasonDetails = '';
            $updatedBy = '';
            $updatedAt = '';
            $adjCommittedTs = null;

            if ($hasAdj) {
                $isJustified = !empty($adj['is_justified']);
                $reasonCategory = trim((string)($adj['reason_category'] ?? ''));
                $reasonDetails = trim((string)($adj['reason_details'] ?? ''));
                $updatedBy = trim((string)($adj['updated_by'] ?? ''));
                $updatedAt = trim((string)($adj['updated_at'] ?? ''));
                if (!empty($adj['adjusted_committed_date'])) {
                    $adjCommittedTs = (int)$adj['adjusted_committed_date'];
                    $committed = $adjCommittedTs;
                }
            }

            $isOnTime = true;
            $rowDelayDays = 0;
            if ($isJustified) {
                $isOnTime = true;
                $rowDelayDays = 0;
            } elseif ($committed > 0 && $fIngreso > 0) {
                // Si la fecha de despacho fue posterior a la fecha comprometida (fin de día)
                if ($fIngreso > ($committed + 86399)) {
                    $isOnTime = false;
                    $rowDelayDays = (int)ceil(($fIngreso - $committed) / 86400);
                }
            }

            if ($isOnTime) {
                $onTimeCount++;
            } else {
                $delayedCount++;
            }

            $totalDispatches++;
            $totalDisp += $dispUnits;

            $statusLabel = $isOnTime 
                ? ($hasAdj && $isJustified ? 'A tiempo (Justificado)' : ($hasAdj ? 'A tiempo (Acuerdo)' : 'A tiempo'))
                : ($hasAdj ? "Atraso {$rowDelayDays}d (Reprog.)" : "Atraso {$rowDelayDays}d");

            $allRows[] = [
                'despacho_id' => $despachoId,
                'orders_delivery_id' => $ordersDeliveryId,
                'date' => $fIngreso > 0 ? date('d/m/Y', $fIngreso) : '-',
                'raw_date' => $fIngreso > 0 ? date('Y-m-d', $fIngreso) : '',
                'entry_time' => trim((string)($row['hora_ingreso'] ?? '')),
                'exit_time' => trim((string)($row['hora_salida'] ?? '')),
                'doc_type' => trim((string)($row['tipo_documento'] ?? 'Despacho')),
                'doc_number' => trim((string)($row['guia_factura'] ?? $row['numero_documento'] ?? '')),
                'customer_name' => trim((string)$row['customer_name']),
                'sales_channel' => trim((string)($row['sales_channel'] ?? 'General')),
                'cc_number' => trim((string)($row['cc_number'] ?? '')),
                'product_code' => trim((string)($row['product_code'] ?? '')),
                'committed_date' => $committed > 0 ? date('d/m/Y', $committed) : 'Sin fecha',
                'raw_committed_date' => $committed > 0 ? date('Y-m-d', $committed) : '',
                'original_committed_date' => $origCommitted > 0 ? date('d/m/Y', $origCommitted) : 'Sin fecha',
                'raw_orig_committed_date' => $origCommitted > 0 ? date('Y-m-d', $origCommitted) : '',
                'is_on_time' => $isOnTime,
                'delay_days' => $rowDelayDays,
                'status_label' => $statusLabel,
                'has_adjustment' => $hasAdj,
                'is_justified' => $isJustified,
                'reason_category' => $reasonCategory,
                'reason_details' => $reasonDetails,
                'adjusted_committed_date' => $adjCommittedTs ? date('d/m/Y', $adjCommittedTs) : null,
                'raw_adjusted_date' => $adjCommittedTs ? date('Y-m-d', $adjCommittedTs) : null,
                'updated_by' => $updatedBy,
                'updated_at' => $updatedAt,
                'dispatched_units' => $dispUnits,
                'pallets' => (int)($row['cantidad_pallet'] ?? 0),
                'boxes' => (int)($row['cantidad_cajas'] ?? 0),
                'transport_company' => trim((string)($row['transport_company'] ?? '')),
                'vehicle_plate' => trim((string)($row['vehicle_plate'] ?? '')),
                'driver_name' => trim((string)($row['driver_name'] ?? '')),
                'driver_rut' => trim((string)($row['driver_rut'] ?? '')),
                'seal_number' => trim((string)($row['sello'] ?? '')),
                'observation' => trim((string)($row['observacion'] ?? '')),
            ];
        }

        // Filtro por Estado de Atraso y Cantidad de Días de Atraso
        $normalizedDelayStatus = strtolower(trim((string)($delayStatus ?? 'all')));
        if ($normalizedDelayStatus === '') {
            $normalizedDelayStatus = 'all';
        }
        $normalizedDelayDays = trim((string)($delayDays ?? ''));

        $hasDelayFilter = ($normalizedDelayStatus !== 'all' || ($normalizedDelayDays !== '' && $normalizedDelayDays !== 'all'));

        $filteredRows = [];
        $filteredUnits = 0.0;
        $filteredOnTime = 0;
        $filteredDelayed = 0;

        foreach ($allRows as $r) {
            $isOnTime = (bool)$r['is_on_time'];
            $days = (int)$r['delay_days'];

            // Filtro por Estado de Entrega (a tiempo vs con atraso)
            if ($normalizedDelayStatus === 'on_time' && !$isOnTime) {
                continue;
            }
            if ($normalizedDelayStatus === 'delayed' && $isOnTime) {
                continue;
            }

            // Filtro por Cantidad de Días de Atraso
            if ($normalizedDelayDays !== '' && $normalizedDelayDays !== 'all') {
                if ($isOnTime) {
                    continue;
                }
                if ($normalizedDelayDays === '1-3' && !($days >= 1 && $days <= 3)) {
                    continue;
                }
                if ($normalizedDelayDays === '4-7' && !($days >= 4 && $days <= 7)) {
                    continue;
                }
                if ($normalizedDelayDays === '8-14' && !($days >= 8 && $days <= 14)) {
                    continue;
                }
                if ($normalizedDelayDays === '15+' && !($days >= 15)) {
                    continue;
                }
                if (str_starts_with($normalizedDelayDays, 'min_')) {
                    $minVal = (int)substr($normalizedDelayDays, 4);
                    if ($days < $minVal) {
                        continue;
                    }
                } elseif (is_numeric($normalizedDelayDays)) {
                    if ($days < (int)$normalizedDelayDays) {
                        continue;
                    }
                }
            }

            $filteredRows[] = $r;
            $filteredUnits += (float)$r['dispatched_units'];
            if ($isOnTime) {
                $filteredOnTime++;
            } else {
                $filteredDelayed++;
            }
        }

        $filteredCount = count($filteredRows);
        $result['rows'] = $filteredRows;
        $result['summary'] = [
            'total_dispatches' => $totalDispatches,
            'on_time_dispatches' => $onTimeCount,
            'delayed_dispatches' => $delayedCount,
            'service_level_percent' => $totalDispatches > 0 ? round(($onTimeCount / $totalDispatches) * 100.0, 2) : 100.0,
            'total_dispatched_units' => $totalDisp,
            'filtered_count' => $filteredCount,
            'filtered_units' => $filteredUnits,
            'filtered_on_time' => $filteredOnTime,
            'filtered_delayed' => $filteredDelayed,
            'filtered_service_level_percent' => $filteredCount > 0 ? round(($filteredOnTime / $filteredCount) * 100.0, 2) : 100.0,
            'has_delay_filter' => $hasDelayFilter,
        ];

        return $result;
    }

    /**
     * Guarda o actualiza un ajuste / justificación de fecha en el Informe de Nivel de Servicio.
     * Permite justificar entregas que por acuerdo con el cliente (falta de pago, espera de confirmación, etc.)
     * se reprogramaron o no deben imputarse como atraso de planta.
     *
     * @param array<string, mixed> $data
     * @return array{ok: bool, message?: string, error?: string, despacho_id?: int, is_justified?: bool, reason_category?: string, adjusted_date?: string}
     */
    public function saveServiceLevelAdjustment(array $data, string $userName = 'Usuario'): array
    {
        $despachoId = isset($data['despacho_id']) ? (int)$data['despacho_id'] : 0;
        if ($despachoId <= 0) {
            return ['ok' => false, 'error' => 'ID de despacho inválido.'];
        }

        $ordersDeliveryId = !empty($data['orders_delivery_id']) ? (int)$data['orders_delivery_id'] : null;
        $docNumber = trim((string)($data['doc_number'] ?? ''));
        $ccNumber = trim((string)($data['cc_number'] ?? ''));
        $adjustedDateStr = trim((string)($data['adjusted_date'] ?? ''));
        $isJustified = !empty($data['is_justified']) ? 1 : 0;
        $reasonCategory = trim((string)($data['reason_category'] ?? 'Acuerdo con cliente'));
        $reasonDetails = trim((string)($data['reason_details'] ?? ''));

        if ($reasonCategory === '') {
            return ['ok' => false, 'error' => 'Debe indicar el motivo o justificación de la modificación.'];
        }

        // Calcular timestamp ajustado si se ingresó fecha
        $adjustedTs = null;
        if ($adjustedDateStr !== '') {
            try {
                $tz = new DateTimeZone('America/Santiago');
                $adjustedTs = (new DateTimeImmutable($adjustedDateStr . ' 12:00:00', $tz))->getTimestamp();
            } catch (Throwable) {
                $adjustedTs = null;
            }
        }

        // Obtener fecha original previa si ya existía ajuste o desde ERP orders_delivery
        $origCommittedTs = null;
        try {
            $s = $this->pdo->prepare("SELECT * FROM service_level_adjustments WHERE despacho_id = :did LIMIT 1");
            $s->execute([':did' => $despachoId]);
            $existingAdj = $s->fetch(PDO::FETCH_ASSOC);
            if ($existingAdj && !empty($existingAdj['original_committed_date'])) {
                $origCommittedTs = (int)$existingAdj['original_committed_date'];
            }
        } catch (Throwable) {}

        if (!$origCommittedTs && $ordersDeliveryId) {
            try {
                $odStmt = $this->erpPdo->prepare("SELECT dlv_delivery_date FROM orders_delivery WHERE id = :odid LIMIT 1");
                $odStmt->execute([':odid' => $ordersDeliveryId]);
                $odRow = $odStmt->fetch(PDO::FETCH_ASSOC);
                if ($odRow && !empty($odRow['dlv_delivery_date'])) {
                    $origCommittedTs = (int)$odRow['dlv_delivery_date'];
                }
            } catch (Throwable) {}
        }

        // Upsert en service_level_adjustments
        try {
            $sql = "INSERT INTO service_level_adjustments 
                (despacho_id, orders_delivery_id, doc_number, cc_number, original_committed_date, adjusted_committed_date, is_justified, reason_category, reason_details, updated_by, created_at, updated_at)
                VALUES 
                (:despacho_id, :orders_delivery_id, :doc_number, :cc_number, :orig_date, :adj_date, :is_justified, :reason_category, :reason_details, :updated_by, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                orders_delivery_id = VALUES(orders_delivery_id),
                doc_number = VALUES(doc_number),
                cc_number = VALUES(cc_number),
                adjusted_committed_date = VALUES(adjusted_committed_date),
                is_justified = VALUES(is_justified),
                reason_category = VALUES(reason_category),
                reason_details = VALUES(reason_details),
                updated_by = VALUES(updated_by),
                updated_at = NOW()";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':despacho_id' => $despachoId,
                ':orders_delivery_id' => $ordersDeliveryId,
                ':doc_number' => $docNumber,
                ':cc_number' => $ccNumber,
                ':orig_date' => $origCommittedTs,
                ':adj_date' => $adjustedTs,
                ':is_justified' => $isJustified,
                ':reason_category' => $reasonCategory,
                ':reason_details' => $reasonDetails,
                ':updated_by' => $userName,
            ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al guardar ajuste: ' . $e->getMessage()];
        }

        // Sincronizar fecha en ERP orders_delivery si se especificó nueva fecha
        if ($ordersDeliveryId && $adjustedTs) {
            try {
                $erpUpd = $this->erpPdo->prepare("UPDATE orders_delivery SET dlv_delivery_date = :new_ts WHERE id = :odid");
                $erpUpd->execute([
                    ':new_ts' => $adjustedTs,
                    ':odid' => $ordersDeliveryId,
                ]);
            } catch (Throwable) {}
        }

        return [
            'ok' => true,
            'message' => 'Registro de entrega modificado y sincronizado con éxito.',
            'despacho_id' => $despachoId,
            'is_justified' => (bool)$isJustified,
            'reason_category' => $reasonCategory,
            'adjusted_date' => $adjustedDateStr,
        ];
    }

    /**
     * Revierte el ajuste de una entrega, restaurando la fecha y estado original.
     *
     * @return array{ok: bool, message?: string, error?: string}
     */
    public function revertServiceLevelAdjustment(int $despachoId, string $userName = 'Usuario'): array
    {
        if ($despachoId <= 0) {
            return ['ok' => false, 'error' => 'ID de despacho inválido.'];
        }

        try {
            $s = $this->pdo->prepare("SELECT * FROM service_level_adjustments WHERE despacho_id = :did LIMIT 1");
            $s->execute([':did' => $despachoId]);
            $adj = $s->fetch(PDO::FETCH_ASSOC);

            if ($adj) {
                // Restaurar fecha en ERP si teníamos la original
                if (!empty($adj['orders_delivery_id']) && !empty($adj['original_committed_date'])) {
                    try {
                        $erpUpd = $this->erpPdo->prepare("UPDATE orders_delivery SET dlv_delivery_date = :orig_ts WHERE id = :odid");
                        $erpUpd->execute([
                            ':orig_ts' => (int)$adj['original_committed_date'],
                            ':odid' => (int)$adj['orders_delivery_id'],
                        ]);
                    } catch (Throwable) {}
                }

                $del = $this->pdo->prepare("DELETE FROM service_level_adjustments WHERE despacho_id = :did");
                $del->execute([':did' => $despachoId]);
            }

            return ['ok' => true, 'message' => 'Ajuste revertido y valores originales restaurados.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al revertir: ' . $e->getMessage()];
        }
    }

    /**
     * Informe de Despachos Comerciales por Período o Rango de Fechas.
     * Con valorización en dinero ($ CLP) y filtros por Cliente, Tipo Doc y Transporte.
     *
     * @return array{
     *     summary: array<string, mixed>,
     *     rows: array<int, array<string, mixed>>,
     *     clients: array<int, array{id: int, name: string}>,
     *     transports: array<int, array{id: int, name: string}>
     * }
     */
    public function getDispatchesPeriodReport(
        string $startAt,
        string $endAt,
        ?int $clientId = null,
        ?string $docType = null,
        ?int $transportId = null,
        ?string $search = null
    ): array {
        $result = [
            'summary' => [
                'total_dispatches' => 0,
                'total_items_count' => 0,
                'total_dispatched_units' => 0.0,
                'total_dispatched_money' => 0.0,
                'total_clients_count' => 0,
                'average_money_per_dispatch' => 0.0,
                'average_units_per_dispatch' => 0.0,
                'facturas_count' => 0,
                'guias_count' => 0,
            ],
            'rows' => [],
            'clients' => [],
            'transports' => [],
        ];

        if (!$this->erpTableExists('despacho') || !$this->erpTableExists('detalle_despacho')) {
            return $result;
        }

        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = strtotime($startAt) ?: 0;
            $endTs = strtotime($endAt) ?: 0;
        }

        $where = ["d.fecha_ingreso BETWEEN :start_ts AND :end_ts"];
        $params = [
            ':start_ts' => $startTs,
            ':end_ts' => $endTs,
        ];

        if ($clientId !== null && $clientId > 0) {
            $where[] = "d.id_cliente = :cid";
            $params[':cid'] = $clientId;
        }

        if ($docType !== null && $docType !== '' && $docType !== 'all') {
            $where[] = "d.tipo_documento = :dtype";
            $params[':dtype'] = strtoupper(trim($docType));
        }

        if ($transportId !== null && $transportId > 0) {
            $where[] = "d.id_transporte = :tid";
            $params[':tid'] = $transportId;
        }

        if ($search !== null && trim($search) !== '') {
            $s = '%' . trim($search) . '%';
            $where[] = "(c.cust_name LIKE :s1 OR c.cust_company LIKE :s2 OR o.req_number LIKE :s3 OR d.numero_documento LIKE :s4 OR od.dlv_docnum LIKE :s5 OR i.item_number_prod LIKE :s6 OR i.item_title LIKE :s7 OR tc.transports_chofer_nombre LIKE :s8 OR tc.transports_chofer_paterno LIKE :s9)";
            $params[':s1'] = $s;
            $params[':s2'] = $s;
            $params[':s3'] = $s;
            $params[':s4'] = $s;
            $params[':s5'] = $s;
            $params[':s6'] = $s;
            $params[':s7'] = $s;
            $params[':s8'] = $s;
            $params[':s9'] = $s;
        }

        $sql = "
            SELECT 
                d.id AS despacho_id,
                d.fecha_ingreso,
                d.hora_ingreso,
                d.hora_salida,
                d.numero_documento,
                d.tipo_documento,
                d.id_cliente,
                d.id_transporte,
                d.estado,
                d.observacion,
                COALESCE(c.cust_company, c.cust_name, 'Cliente N/D') AS customer_name,
                o.req_number AS cc_number,
                od.dlv_docnum AS guia_factura,
                od.dlv_total_netto,
                od.dlv_total_brutto,
                i.item_number_prod,
                COALESCE(i.item_title, dd.descripcion, 'Producto N/D') AS item_name,
                dd.salida AS dispatched_units,
                dd.cantidad AS declared_units,
                oi.item_sellprice_netto,
                oi.item_sellprice_netto_dsc,
                oi.item_amount,
                i.item_sellprice_netto AS item_catalog_price,
                t1.trans_name AS transport_company,
                CONCAT(COALESCE(tc.transports_chofer_nombre, ''), ' ', COALESCE(tc.transports_chofer_paterno, '')) AS driver_name,
                tv.transports_vh_patente AS vehicle_plate
            FROM despacho d
            INNER JOIN detalle_despacho dd ON d.id = dd.id_despacho
            LEFT JOIN customer c ON c.id = d.id_cliente
            LEFT JOIN transports_chofer tc ON tc.id = d.id_chofer
            LEFT JOIN transports_vehiculo tv ON tv.id = d.id_patente
            LEFT JOIN transports t1 ON t1.id = d.id_transporte
            LEFT JOIN item i ON i.id = dd.id_item
            LEFT JOIN orders_delivery od ON d.numero_documento = od.id
            LEFT JOIN orders o ON o.id = od.dlv_order_id
            LEFT JOIN orders_items oi ON oi.req_id = o.id AND oi.item_id = dd.id_item
            WHERE " . implode(" AND ", $where) . "
            ORDER BY d.fecha_ingreso DESC, d.id DESC, dd.id ASC
        ";

        $rows = [];
        $totalUnits = 0.0;
        $totalMoney = 0.0;
        $distinctDespachos = [];
        $distinctClients = [];
        $usedDeliveryTotals = [];
        $facturasCount = 0;
        $guiasCount = 0;

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rawRows as $dr) {
                $units = (float)($dr['dispatched_units'] ?? $dr['declared_units'] ?? 0);
                if ($units <= 0 && (float)($dr['declared_units'] ?? 0) > 0) {
                    $units = (float)$dr['declared_units'];
                }

                $unitPrice = 0.0;
                if (!empty($dr['item_sellprice_netto']) && (float)$dr['item_sellprice_netto'] > 0) {
                    $unitPrice = (float)$dr['item_sellprice_netto'];
                } elseif (!empty($dr['item_amount']) && (float)$dr['item_amount'] > 0 && !empty($dr['item_sellprice_netto_dsc'])) {
                    $unitPrice = (float)$dr['item_sellprice_netto_dsc'] / (float)$dr['item_amount'];
                } elseif (!empty($dr['item_catalog_price']) && (float)$dr['item_catalog_price'] > 0) {
                    $unitPrice = (float)$dr['item_catalog_price'];
                }

                $lineMoney = round($units * $unitPrice, 2);
                if ($lineMoney <= 0 && !empty($dr['dlv_total_netto']) && (float)$dr['dlv_total_netto'] > 0) {
                    $dlvId = (string)($dr['numero_documento'] ?? '');
                    if (!isset($usedDeliveryTotals[$dlvId])) {
                        $lineMoney = (float)$dr['dlv_total_netto'];
                        $usedDeliveryTotals[$dlvId] = true;
                        if ($units > 0 && $unitPrice <= 0) {
                            $unitPrice = round($lineMoney / $units, 2);
                        }
                    }
                }

                $docNum = trim((string)($dr['guia_factura'] ?? ''));
                if ($docNum === '' || $docNum === '0') {
                    $docNum = trim((string)($dr['numero_documento'] ?? ''));
                }

                $fDate = !empty($dr['fecha_ingreso']) ? date('d/m/Y', (int)$dr['fecha_ingreso']) : '—';
                $fTime = !empty($dr['hora_salida']) && (string)$dr['hora_salida'] !== '00:00:00' ? substr((string)$dr['hora_salida'], 0, 5) : (!empty($dr['hora_ingreso']) ? substr((string)$dr['hora_ingreso'], 0, 5) : '—');
                $dType = strtoupper(trim((string)($dr['tipo_documento'] ?? 'DESP')));

                $dr['formatted_date'] = $fDate;
                $dr['departure_time'] = $fTime;
                $dr['fecha_formateada'] = ($fDate !== '—' ? $fDate : '') . ($fTime !== '—' ? ' ' . $fTime : '');
                $dr['doc_number'] = $docNum;
                $dr['numero_documento'] = $docNum;
                $dr['doc_type_clean'] = $dType;
                $dr['tipo_documento'] = $dType;
                $dr['dispatched_units'] = $units;
                $dr['salida'] = $units;
                $dr['unit_price'] = $unitPrice;
                $dr['total_money'] = $lineMoney;
                $dr['total_amount'] = $lineMoney;
                $dr['cliente_nombre'] = $dr['customer_name'] ?? '';
                $dr['cliente_rut'] = $dr['customer_rut'] ?? '';
                $dr['cost_center'] = $dr['cc_number'] ?? '';
                $dr['order_number'] = $dr['cc_number'] ?? '';
                $dr['item_codigo'] = $dr['item_number_prod'] ?? '';
                $dr['item_nombre'] = $dr['item_name'] ?? '';
                $dr['observacion'] = $dr['observacion'] ?? '';
                $dr['transporte_nombre'] = $dr['transport_company'] ?? '';
                $dr['chofer_nombre'] = $dr['driver_name'] ?? '';
                $dr['patente'] = $dr['vehicle_plate'] ?? '';
                $dr['estado_nombre'] = ($dr['estado'] == 1 || $dr['estado'] == '1' || $dr['estado'] === 'EMITIDO') ? 'EMITIDO' : (string)($dr['estado'] ?? 'EMITIDO');

                $rows[] = $dr;
                $totalUnits += $units;
                $totalMoney += $lineMoney;
                $distinctDespachos[(int)$dr['despacho_id']] = true;
                if (!empty($dr['id_cliente'])) {
                    $distinctClients[(int)$dr['id_cliente']] = true;
                }

                if ($dType === 'FA' || str_contains($dType, 'FACT')) {
                    $facturasCount++;
                } else {
                    $guiasCount++;
                }
            }
        } catch (Throwable) {}

        // Catálogos para filtros
        $clientsList = [];
        $transportsList = [];
        try {
            $stmtC = $this->erpPdo->query("
                SELECT DISTINCT c.id, COALESCE(c.cust_company, c.cust_name) AS name, COALESCE(c.cust_company, c.cust_name) AS nombre, c.cust_rut AS rut
                FROM customer c
                INNER JOIN despacho d ON d.id_cliente = c.id
                WHERE d.fecha_ingreso >= UNIX_TIMESTAMP('2026-01-01')
                ORDER BY name ASC
            ");
            $clientsList = $stmtC->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {}

        try {
            $stmtT = $this->erpPdo->query("SELECT id, trans_name AS name, trans_name AS nombre FROM transports ORDER BY trans_name ASC");
            $transportsList = $stmtT->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {}

        $totDespachos = count($distinctDespachos);
        $result['summary'] = [
            'total_dispatches' => $totDespachos,
            'total_items_count' => count($rows),
            'total_units' => $totalUnits,
            'total_dispatched_units' => $totalUnits,
            'total_amount' => $totalMoney,
            'total_dispatched_money' => $totalMoney,
            'clients_count' => count($distinctClients),
            'total_clients_count' => count($distinctClients),
            'average_money_per_dispatch' => $totDespachos > 0 ? round($totalMoney / $totDespachos, 2) : 0.0,
            'avg_money_per_dispatch' => $totDespachos > 0 ? round($totalMoney / $totDespachos, 2) : 0.0,
            'avg_amount_per_dispatch' => $totDespachos > 0 ? round($totalMoney / $totDespachos, 2) : 0.0,
            'average_units_per_dispatch' => $totDespachos > 0 ? round($totalUnits / $totDespachos, 1) : 0.0,
            'avg_units_per_dispatch' => $totDespachos > 0 ? round($totalUnits / $totDespachos, 1) : 0.0,
            'facturas_count' => $facturasCount,
            'fa_count' => $facturasCount,
            'guias_count' => $guiasCount,
            'gv_count' => $guiasCount,
        ];
        $result['rows'] = $rows;
        $result['dispatches'] = $rows;
        $result['clients'] = $clientsList;
        $result['transports'] = $transportsList;

        return $result;
    }

    public function getErpDashboardSummary(): array
    {
        $workOrders = [
            'open' => 0,
            'active' => 0,
            'cutting' => 0,
            'closed' => 0,
            'completed' => 0,
        ];
        $stmt = $this->pdo->query(
            "SELECT
                SUM(CASE WHEN status = 'OPEN' THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN status = 'CUTTING' THEN 1 ELSE 0 END) AS cutting_count,
                SUM(CASE WHEN status = 'CLOSED' THEN 1 ELSE 0 END) AS closed_count
             FROM work_orders"
        );
        $row = $stmt->fetch() ?: [];
        $workOrders['open'] = (int)($row['open_count'] ?? 0);
        $workOrders['active'] = (int)($row['active_count'] ?? 0);
        $workOrders['cutting'] = (int)($row['cutting_count'] ?? 0);
        $workOrders['closed'] = (int)($row['closed_count'] ?? 0);
        $workOrders['completed'] = $workOrders['closed'];

        $rolls = [
            'total' => 0,
            'in_stock' => 0,
            'in_process' => 0,
            'ready_for_cut' => 0,
            'output' => 0,
            'blocked' => 0,
        ];
        $stmt = $this->pdo->query(
            "SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN status IN ('RECEIVED','IN_PROCESS','BLOCKED') THEN 1 ELSE 0 END) AS in_stock_count,
                SUM(CASE WHEN status = 'IN_PROCESS' THEN 1 ELSE 0 END) AS in_process_count,
                SUM(CASE WHEN status = 'BLOCKED' THEN 1 ELSE 0 END) AS blocked_count,
                SUM(CASE WHEN process_stage = 'PRINTED' AND status <> 'CONSUMED' THEN 1 ELSE 0 END) AS ready_cut_count,
                SUM(CASE WHEN parent_roll_id IS NOT NULL THEN 1 ELSE 0 END) AS output_count
             FROM rolls"
        );
        $row = $stmt->fetch() ?: [];
        $rolls['total'] = (int)($row['total_count'] ?? 0);
        $rolls['in_stock'] = (int)($row['in_stock_count'] ?? 0);
        $rolls['in_process'] = (int)($row['in_process_count'] ?? 0);
        $rolls['blocked'] = (int)($row['blocked_count'] ?? 0);
        $rolls['ready_for_cut'] = (int)($row['ready_cut_count'] ?? 0);
        $rolls['output'] = (int)($row['output_count'] ?? 0);

        $packaging = [
            'boxes' => 0,
            'pallets' => 0,
            'units' => 0.0,
        ];
        $stmt = $this->pdo->query('SELECT COUNT(*) AS box_count, COALESCE(SUM(units_qty), 0) AS units_total FROM boxes');
        $row = $stmt->fetch() ?: [];
        $packaging['boxes'] = (int)($row['box_count'] ?? 0);
        $packaging['units'] = (float)($row['units_total'] ?? 0);
        $stmt = $this->pdo->query('SELECT COUNT(*) AS pallet_count FROM pallets');
        $packaging['pallets'] = (int)$stmt->fetchColumn();

        $reception = [
            'purchase_orders_pending' => 0,
            'containers_pending' => 0,
        ];
        $stmt = $this->erpPdo->query('SELECT id FROM supplier_order WHERE sord_type = 0');
        $purchaseOrderIds = array_map(static fn($value): int => (int)$value, $stmt->fetchAll(PDO::FETCH_COLUMN));
        $purchaseOrderStats = $this->getPurchaseOrderStatsByIds($purchaseOrderIds);
        foreach ($purchaseOrderStats as $stats) {
            if ((int)($stats['total_lines'] ?? 0) > (int)($stats['completed_lines'] ?? 0)) {
                $reception['purchase_orders_pending']++;
            }
        }

        $stmt = $this->erpPdo->query('SELECT id FROM supplier_contenedor');
        $containerIds = array_map(static fn($value): int => (int)$value, $stmt->fetchAll(PDO::FETCH_COLUMN));
        $containerStats = $this->getImportContainerStatsByIds($containerIds);
        foreach ($containerStats as $stats) {
            if ((int)($stats['total_lines'] ?? 0) > (int)($stats['completed_lines'] ?? 0)) {
                $reception['containers_pending']++;
            }
        }

        return [
            'work_orders' => $workOrders,
            'rolls' => $rolls,
            'packaging' => $packaging,
            'reception' => $reception,
        ];
    }

    public function getLegacyErpHomeDashboard(int $userId): array
    {
        $dashboardUrl = '';
        $userPic = '';
        $salesByYear = [];

        if ($this->erpTableExists('user')) {
            try {
                $stmt = $this->erpPdo->prepare('SELECT user_pic, user_dashboard FROM user WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $userId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $userPic = (string)($row['user_pic'] ?? '');
                    $dashboardUrl = (string)($row['user_dashboard'] ?? '');
                }
            } catch (Throwable) {
                $userPic = '';
                $dashboardUrl = '';
            }
        }

        if ($this->erpTableExists('invoices_sell')) {
            try {
                $stmt = $this->erpPdo->query(
                    "SELECT
                        DATE_FORMAT(DATE_ADD('1970-01-01', INTERVAL invc_date SECOND), '%Y') AS year,
                        ROUND(SUM(invc_total_netto), 0) AS total
                     FROM invoices_sell
                     GROUP BY DATE_FORMAT(DATE_ADD('1970-01-01', INTERVAL invc_date SECOND), '%Y')
                     ORDER BY year ASC"
                );
                $salesByYear = $stmt->fetchAll();
            } catch (Throwable) {
                $salesByYear = [];
            }
        }

        return [
            'user_pic' => $userPic,
            'dashboard_url' => $dashboardUrl,
            'sales_by_year' => $salesByYear,
        ];
    }

    private function erpTableExists(string $table): bool
    {
        if (isset($this->erpTableExistsCache[$table])) {
            return $this->erpTableExistsCache[$table];
        }
        try {
            $stmt = $this->erpPdo->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
            );
            $stmt->execute([
                ':table_name' => $table,
            ]);
            $exists = (int)$stmt->fetchColumn() > 0;
            $this->erpTableExistsCache[$table] = $exists;
            return $exists;
        } catch (Throwable) {
            $this->erpTableExistsCache[$table] = false;
            return false;
        }
    }

    private function erpColumnExists(string $table, string $column): bool
    {
        if (isset($this->erpColumnExistsCache[$table]) && array_key_exists($column, $this->erpColumnExistsCache[$table])) {
            return (bool)$this->erpColumnExistsCache[$table][$column];
        }
        try {
            $stmt = $this->erpPdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
            );
            $stmt->execute([
                ':table_name' => $table,
                ':column_name' => $column,
            ]);
            $exists = (int)$stmt->fetchColumn() > 0;
            if (!isset($this->erpColumnExistsCache[$table])) {
                $this->erpColumnExistsCache[$table] = [];
            }
            $this->erpColumnExistsCache[$table][$column] = $exists;
            return $exists;
        } catch (Throwable) {
            if (!isset($this->erpColumnExistsCache[$table])) {
                $this->erpColumnExistsCache[$table] = [];
            }
            $this->erpColumnExistsCache[$table][$column] = false;
            return false;
        }
    }

    public function getErpOnlyProductionDashboardKpis(string $startAt, string $endAt): array
    {
        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = 0;
            $endTs = 0;
        }
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return [
                'produced_units' => 0.0,
                'pending_units' => 0.0,
                'dispatched_units' => null,
                'waste' => ['percent' => null, 'waste_kg' => null, 'processed_kg' => null],
                'semi_rolls' => ['count' => null, 'rows' => []],
                'work_orders' => [],
                'warehouses' => [],
            ];
        }

        if (!$this->erpTableExists('prod_worker_ot_events') || !$this->erpTableExists('prod_worker_ot') || !$this->erpTableExists('prod_agenda') || !$this->erpTableExists('prod_header')) {
            return [
                'produced_units' => 0.0,
                'pending_units' => 0.0,
                'dispatched_units' => null,
                'waste' => ['percent' => null, 'waste_kg' => null, 'processed_kg' => null],
                'semi_rolls' => ['count' => null, 'rows' => []],
                'work_orders' => [],
                'warehouses' => [],
            ];
        }

        $producedUnits = 0.0;
        try {
            $stmt = $this->erpPdo->prepare(
                "SELECT COALESCE(SUM(e.evt_amount), 0) AS produced_units
                 FROM prod_worker_ot_events e
                 INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                 INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                 INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                 WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                   AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                   AND eq.equipo_type_id = 8"
            );
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $producedUnits = (float)($stmt->fetchColumn() ?: 0);
        } catch (Throwable) {
            $producedUnits = 0.0;
        }

        $wastePercent = null;
        $wasteUnits = null;
        $wasteRequestedUnits = null;
        $wasteBaseUnits = null;
        $wasteDeclaredUnits = null;
        $wasteDeclaredBaseUnits = null;
        $wasteDeclaredPercent = null;
        $wasteSource = null;

        $wasteKg = null;

        if ($this->erpTableExists('prod_worker_ot_defectunits')) {
            $wasteKg = 0.0;
            $wasteUnits = 0.0;
            try {
                $stmt = $this->erpPdo->prepare(
                    'SELECT 
                        d.evt_type,
                        d.evt_amount,
                        d.evt_kgstounits,
                        t11.item_weight,
                        t10.fab_med_width,
                        t10.fab_med_height,
                        t10.fab_med_fuelle,
                        t10.fab_mat_gramms,
                        t10.fab_manilla_length
                     FROM prod_worker_ot_defectunits d
                     INNER JOIN prod_worker_ot_events e ON e.id = d.evt_refid
                     INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                     INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                     LEFT JOIN orders_items t10 ON t10.req_id = pa.ag_reqid
                     LEFT JOIN item t11 ON t11.id = t10.item_id
                     WHERE d.evt_crtdat BETWEEN :start_ts AND :end_ts
                       AND LOWER(d.evt_type) = "merma"'
                );
                $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $itemWeight = (float)($r['item_weight'] ?? 0);
                    $w = (float)($r['fab_med_width'] ?? 0);
                    $h = (float)($r['fab_med_height'] ?? 0);
                    $f = (float)($r['fab_med_fuelle'] ?? 0);
                    $gramms = (float)($r['fab_mat_gramms'] ?? 0);
                    $manLen = (float)($r['fab_manilla_length'] ?? 0);

                    $unitWeightKg = 0.0;
                    if ($itemWeight > 0) {
                        $unitWeightKg = $itemWeight / 1000.0;
                    } elseif ($w > 0 && $h > 0 && $gramms > 0) {
                        $areaM2 = (2.0 * ($w + $f) * $h) / 10000.0;
                        $bodyKg = $areaM2 * ($gramms / 1000.0);
                        $manillaKg = ($manLen > 0) ? (2.0 * ($manLen / 100.0) * 0.025 * ($gramms / 1000.0)) : 0.0;
                        $unitWeightKg = $bodyKg + $manillaKg;
                    }

                    $kg = (float)($r['evt_kgstounits'] ?? 0);
                    $rawUnits = (float)($r['evt_amount'] ?? 0);

                    if ($unitWeightKg > 0 && $kg > 0) {
                        $units = (float)round($kg / $unitWeightKg);
                    } else {
                        $units = $rawUnits;
                    }

                    $wasteKg += $kg;
                    $wasteUnits += $units;
                }
            } catch (Throwable) {
                $wasteUnits = null;
                $wasteKg = null;
            }

            try {
                $stmt = $this->erpPdo->prepare(
                    'SELECT COALESCE(SUM(t.requested_units), 0) AS requested_units
                     FROM (
                        SELECT ph.prd_number AS work_order_number, MAX(pa.ag_amount) AS requested_units
                        FROM prod_worker_ot_events e
                        INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                        INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                        INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
                        WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                          AND LOWER(e.evt_type) IN ("production","prod","prodsericolor")
                        GROUP BY ph.prd_number
                     ) t'
                );
                $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
                $wasteRequestedUnits = (float)($stmt->fetchColumn() ?: 0.0);
            } catch (Throwable) {
                $wasteRequestedUnits = null;
            }

            if (is_numeric($wasteUnits)) {
                $wasteBaseUnits = $producedUnits;
                if ($wasteBaseUnits > 0) {
                    $wastePercent = ($wasteUnits / $wasteBaseUnits) * 100.0;
                } else {
                    $wastePercent = 0.0;
                }
                $wasteSource = 'prod_worker_ot_defectunits';
            }
        }

        $sqlLegacy = <<<SQL
SELECT
    ph.prd_number AS work_order_number,
    ph.prd_reqid AS cost_center,
    MIN(DATE(FROM_UNIXTIME(e.evt_crtdat))) AS first_date,
    MAX(DATE(FROM_UNIXTIME(e.evt_crtdat))) AS last_date,
    ph.prd_desc AS erp_desc,
    MAX(TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, "")))) AS operator_name,
    MAX(eq.equipo_name) AS machine_name,
    MAX(pa.ag_amount) AS requested_units,
    COALESCE(SUM(e.evt_amount), 0) AS produced_units
FROM prod_worker_ot_events e
INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
LEFT JOIN workers w ON w.id = pwi.win_wrkid
WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
  AND LOWER(e.evt_type) IN ("production","prod","prodsericolor")
  AND eq.equipo_type_id = 8
GROUP BY ph.prd_number, ph.prd_reqid, ph.prd_desc
HAVING produced_units > 0
ORDER BY produced_units DESC, work_order_number ASC
LIMIT 40
SQL;

        $sqlExtended = <<<SQL
SELECT
    ph.prd_number AS work_order_number,
    COALESCE(o.req_number, ph.prd_reqid) AS cost_center,
    MIN(DATE(FROM_UNIXTIME(e.evt_crtdat))) AS first_date,
    MAX(DATE(FROM_UNIXTIME(e.evt_crtdat))) AS last_date,
    ph.prd_desc AS erp_desc,
    MAX(TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, "")))) AS operator_name,
    MAX(eq.equipo_name) AS machine_name,
    MAX(COALESCE(oi.item_amount, pa.ag_amount)) AS requested_units,
    COALESCE(SUM(e.evt_amount), 0) AS produced_units,
    MAX(c.cust_name) AS client_label,
    MAX(UPPER(cat.cat_prefix)) AS product_type,
    MAX(
        CASE
            WHEN oi.fab_med_width IS NULL OR oi.fab_med_height IS NULL THEN ''
            WHEN oi.fab_med_fuelle IS NULL OR oi.fab_med_fuelle = 0 THEN CONCAT(CAST(oi.fab_med_width AS UNSIGNED), 'X', CAST(oi.fab_med_height AS UNSIGNED))
            ELSE CONCAT(CAST(oi.fab_med_width AS UNSIGNED), 'X', CAST(oi.fab_med_height AS UNSIGNED), 'X', CAST(oi.fab_med_fuelle AS UNSIGNED))
        END
    ) AS measure_cm
FROM prod_worker_ot_events e
INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
LEFT JOIN workers w ON w.id = pwi.win_wrkid
LEFT JOIN orders o ON o.id = pa.ag_reqid
LEFT JOIN customer c ON c.id = o.req_cust_id
LEFT JOIN (
    SELECT
        req_id,
        MAX(item_amount) AS item_amount,
        MAX(fab_med_width) AS fab_med_width,
        MAX(fab_med_height) AS fab_med_height,
        MAX(fab_med_fuelle) AS fab_med_fuelle,
        MAX(item_id) AS item_id
    FROM orders_items
    GROUP BY req_id
) oi ON oi.req_id = o.id
LEFT JOIN item it ON it.id = oi.item_id
LEFT JOIN (
    SELECT ip.item_id, MIN(pc.cat_prefix) AS cat_prefix
    FROM item_productcats ip
    INNER JOIN productcats pc ON pc.id = ip.cat_id
    GROUP BY ip.item_id
) cat ON cat.item_id = it.id
WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
  AND LOWER(e.evt_type) IN ("production","prod","prodsericolor")
  AND eq.equipo_type_id = 8
GROUP BY ph.prd_number, cost_center, ph.prd_desc
HAVING produced_units > 0
ORDER BY produced_units DESC, work_order_number ASC
LIMIT 40
SQL;

        $rawRows = [];
        $queryMode = 'extended';
        try {
            $stmt = $this->erpPdo->prepare($sqlExtended);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $rawRows = $stmt->fetchAll();
        } catch (Throwable) {
            $queryMode = 'legacy';
            try {
                $stmt = $this->erpPdo->prepare($sqlLegacy);
                $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
                $rawRows = $stmt->fetchAll();
            } catch (Throwable) {
                $rawRows = [];
            }
        }

        $rows = [];
        $pendingUnits = 0.0;
        try {
            $pendingStmt = $this->erpPdo->prepare(
                "SELECT COALESCE(SUM(GREATEST(t.requested_units - t.produced_units, 0)), 0) AS pending_units
                 FROM (
                    SELECT
                        ph.prd_number,
                        MAX(pa.ag_amount) AS requested_units,
                        SUM(e.evt_amount) AS produced_units
                    FROM prod_worker_ot_events e
                    INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                    INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                    INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
                    INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                    WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                      AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                      AND eq.equipo_type_id = 8
                    GROUP BY ph.prd_number
                 ) t"
            );
            $pendingStmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $pendingUnits = (float)($pendingStmt->fetchColumn() ?: 0.0);
        } catch (Throwable) {
            $pendingUnits = 0.0;
        }

        foreach ($rawRows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $desc = trim((string)($r['erp_desc'] ?? ''));
            $requested = (float)($r['requested_units'] ?? 0.0);
            $produced = (float)($r['produced_units'] ?? 0.0);

            $rows[] = [
                'work_order_number' => (string)($r['work_order_number'] ?? ''),
                'cost_center' => (string)($r['cost_center'] ?? ''),
                'first_date' => (string)($r['first_date'] ?? ''),
                'last_date' => (string)($r['last_date'] ?? ''),
                'operator_name' => trim((string)($r['operator_name'] ?? '')),
                'machine_name' => trim((string)($r['machine_name'] ?? '')),
                'client_label' => trim((string)($r['client_label'] ?? '')) !== '' ? (string)$r['client_label'] : $this->parseClientLabelFromErpDesc($desc),
                'product_type' => trim((string)($r['product_type'] ?? '')) !== '' ? (string)$r['product_type'] : $this->parseProductTypeFromErpDesc($desc),
                'measure_cm' => trim((string)($r['measure_cm'] ?? '')) !== '' ? (string)$r['measure_cm'] : $this->parseMeasureCmFromErpDesc($desc),
                'requested_units' => $requested,
                'produced_units' => $produced,
                'erp_desc' => $desc,
                'source' => $queryMode,
            ];
        }

        return [
            'produced_units' => $producedUnits,
            'pending_units' => $pendingUnits,
            'dispatched_units' => null,
            'waste' => [
                'percent' => $wastePercent,
                'waste_units' => $wasteUnits,
                'waste_kg' => $wasteKg,
                'base_units' => $wasteBaseUnits,
                'requested_units' => $wasteRequestedUnits,
                'declared_waste_units' => null,
                'declared_base_units' => null,
                'source' => $wasteSource,
                'processed_kg' => null,
            ],
            'semi_rolls' => ['count' => null, 'rows' => []],
            'work_orders' => $rows,
            'warehouses' => [],
        ];
    }

    public function getErpWasteDashboardDetails(string $startAt, string $endAt): array
    {
        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = 0;
            $endTs = 0;
        }
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return [
                'by_machine' => [],
                'by_type' => [],
                'by_production_type' => [],
                'top10' => [],
                'sources' => ['by_machine' => null, 'by_type' => null, 'by_production_type' => null, 'top10' => null],
            ];
        }

        $equipoNameCol = null;
        if ($this->erpTableExists('equipo')) {
            if ($this->erpColumnExists('equipo', 'equipo_name')) {
                $equipoNameCol = 'equipo_name';
            } elseif ($this->erpColumnExists('equipo', 'name')) {
                $equipoNameCol = 'name';
            }
        }

        $mermaTitleCol = null;
        if ($this->erpTableExists('prod_mermatypes')) {
            if ($this->erpColumnExists('prod_mermatypes', 'merma_title')) {
                $mermaTitleCol = 'merma_title';
            }
        }

        $classifyMachine = static function (int $machineId, int $typeId, string $machineName): array {
            if ($typeId === 22 || $machineId === 36 || stripos($machineName, 'pulpo') !== false) {
                return ['code' => 'pulpo', 'title' => 'Pulpo Serigráfico', 'icon' => '🐙'];
            }
            if ($typeId === 8 || $typeId === 14 || stripos($machineName, 'sellad') !== false) {
                return ['code' => 'corte_sellado', 'title' => 'Corte y Sellado', 'icon' => '✂️'];
            }
            if ($typeId === 15 || stripos($machineName, 'embalaje') !== false) {
                return ['code' => 'embalaje', 'title' => 'Embalaje', 'icon' => '📦'];
            }
            if ($typeId === 7 || $typeId === 11 || stripos($machineName, 'flexo') !== false || stripos($machineName, 'seri') !== false || stripos($machineName, 'impresora') !== false) {
                return ['code' => 'impresion', 'title' => 'Impresión', 'icon' => '🖨️'];
            }
            return ['code' => 'otros', 'title' => 'Otros Procesos', 'icon' => '⚙️'];
        };

        $byMachine = [];
        $byType = [];
        $byProductionType = [];
        $top10 = [];
        $sources = ['by_machine' => null, 'by_type' => null, 'by_production_type' => null, 'top10' => null];

        if ($this->erpTableExists('prod_worker_ot_defectunits')) {
            $machineNameCol = $equipoNameCol !== null ? 'eq.' . $equipoNameCol : '""';
            $typeTitleCol = $mermaTitleCol !== null ? 'mt.' . $mermaTitleCol : '""';

            try {
                $sqlDefects = "
                    SELECT 
                        d.id AS defect_id,
                        d.evt_type,
                        d.evt_merma_typeid,
                        {$typeTitleCol} AS merma_title,
                        d.evt_amount,
                        d.evt_kgstounits,
                        COALESCE(pa.ag_equipo_id, pwi.win_equipoid, 0) AS machine_id,
                        {$machineNameCol} AS equipo_name,
                        COALESCE(eq.equipo_type_id, 0) AS equipo_type_id,
                        ph.prd_number AS work_order_number,
                        ph.prd_reqid AS cost_center,
                        pa.ag_amount AS requested_units,
                        TRIM(CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, ''))) AS operator_name,
                        t11.item_weight,
                        t10.fab_med_width,
                        t10.fab_med_height,
                        t10.fab_med_fuelle,
                        t10.fab_mat_gramms,
                        t10.fab_manilla_length
                    FROM prod_worker_ot_defectunits d
                    INNER JOIN prod_worker_ot_events e ON e.id = d.evt_refid
                    INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                    INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                    INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
                    LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
                    LEFT JOIN workers w ON w.id = pwi.win_wrkid
                    LEFT JOIN orders_items t10 ON t10.req_id = pa.ag_reqid
                    LEFT JOIN item t11 ON t11.id = t10.item_id
                    " . ($equipoNameCol !== null ? 'LEFT JOIN equipo eq ON eq.id = COALESCE(pa.ag_equipo_id, pwi.win_equipoid)' : '') . "
                    " . ($mermaTitleCol !== null ? 'LEFT JOIN prod_mermatypes mt ON mt.id = d.evt_merma_typeid' : '') . "
                    WHERE d.evt_crtdat BETWEEN :start_ts AND :end_ts
                      AND LOWER(d.evt_type) = 'merma'
                    ORDER BY d.id ASC
                ";
                $stmt = $this->erpPdo->prepare($sqlDefects);
                $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
                $defects = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $byMachineMap = [];
                $byTypeMap = [];
                $byOtMap = [];

                $byProdTypeMap = [
                    'impresion' => [
                        'code' => 'impresion',
                        'title' => 'Impresión',
                        'icon' => '🖨️',
                        'waste_units' => 0.0,
                        'waste_kg' => 0.0,
                        'produced_units' => 0.0,
                        'waste_percent' => null,
                        'share_percent' => 0.0,
                        'machines_count' => 0,
                    ],
                    'corte_sellado' => [
                        'code' => 'corte_sellado',
                        'title' => 'Corte y Sellado',
                        'icon' => '✂️',
                        'waste_units' => 0.0,
                        'waste_kg' => 0.0,
                        'produced_units' => 0.0,
                        'waste_percent' => null,
                        'share_percent' => 0.0,
                        'machines_count' => 0,
                    ],
                    'embalaje' => [
                        'code' => 'embalaje',
                        'title' => 'Embalaje',
                        'icon' => '📦',
                        'waste_units' => 0.0,
                        'waste_kg' => 0.0,
                        'produced_units' => 0.0,
                        'waste_percent' => null,
                        'share_percent' => 0.0,
                        'machines_count' => 0,
                    ],
                    'pulpo' => [
                        'code' => 'pulpo',
                        'title' => 'Pulpo Serigráfico',
                        'icon' => '🐙',
                        'waste_units' => 0.0,
                        'waste_kg' => 0.0,
                        'produced_units' => 0.0,
                        'waste_percent' => null,
                        'share_percent' => 0.0,
                        'machines_count' => 0,
                    ],
                ];

                $totalWasteUnitsAll = 0.0;

                foreach ($defects as $r) {
                    $itemWeight = (float)($r['item_weight'] ?? 0);
                    $w = (float)($r['fab_med_width'] ?? 0);
                    $h = (float)($r['fab_med_height'] ?? 0);
                    $f = (float)($r['fab_med_fuelle'] ?? 0);
                    $gramms = (float)($r['fab_mat_gramms'] ?? 0);
                    $manLen = (float)($r['fab_manilla_length'] ?? 0);

                    $unitWeightKg = 0.0;
                    if ($itemWeight > 0) {
                        $unitWeightKg = $itemWeight / 1000.0;
                    } elseif ($w > 0 && $h > 0 && $gramms > 0) {
                        $areaM2 = (2.0 * ($w + $f) * $h) / 10000.0;
                        $bodyKg = $areaM2 * ($gramms / 1000.0);
                        $manillaKg = ($manLen > 0) ? (2.0 * ($manLen / 100.0) * 0.025 * ($gramms / 1000.0)) : 0.0;
                        $unitWeightKg = $bodyKg + $manillaKg;
                    }

                    $kg = (float)($r['evt_kgstounits'] ?? 0);
                    $rawUnits = (float)($r['evt_amount'] ?? 0);

                    if ($unitWeightKg > 0 && $kg > 0) {
                        $units = (float)round($kg / $unitWeightKg);
                    } else {
                        $units = $rawUnits;
                    }

                    $totalWasteUnitsAll += $units;

                    // Classify machine & production type
                    $mId = (int)($r['machine_id'] ?? 0);
                    $typeId = (int)($r['equipo_type_id'] ?? 0);
                    $mName = trim((string)($r['equipo_name'] ?? ''));
                    $pClass = $classifyMachine($mId, $typeId, $mName);
                    $pCode = $pClass['code'];

                    // Production Type Accumulator
                    if (!isset($byProdTypeMap[$pCode])) {
                        $byProdTypeMap[$pCode] = [
                            'code' => $pCode,
                            'title' => $pClass['title'],
                            'icon' => $pClass['icon'],
                            'waste_units' => 0.0,
                            'waste_kg' => 0.0,
                            'produced_units' => 0.0,
                            'waste_percent' => null,
                            'share_percent' => 0.0,
                            'machines_count' => 0,
                        ];
                    }
                    $byProdTypeMap[$pCode]['waste_units'] += $units;
                    $byProdTypeMap[$pCode]['waste_kg'] += $kg;

                    // Machine
                    $mLabel = $mId > 0 ? ($mName !== '' ? "Equipo {$mId} · {$mName}" : "Equipo {$mId}") : 'Sin máquina';
                    if (!isset($byMachineMap[$mId])) {
                        $byMachineMap[$mId] = [
                            'machine_id' => $mId,
                            'machine_name' => $mName,
                            'machine_label' => $mLabel,
                            'production_type_code' => $pCode,
                            'production_type_title' => $pClass['title'],
                            'production_type_icon' => $pClass['icon'],
                            'waste_units' => 0.0,
                            'waste_kg' => 0.0,
                            'requested_units' => 0.0,
                        ];
                    }
                    $byMachineMap[$mId]['waste_units'] += $units;
                    $byMachineMap[$mId]['waste_kg'] += $kg;

                    // Type
                    $tId = (int)($r['evt_merma_typeid'] ?? 0);
                    $tTitle = trim((string)($r['merma_title'] ?? ''));
                    $tLabel = $tTitle !== '' ? $tTitle : ($tId > 0 ? "Tipo {$tId}" : 'Sin tipo');
                    if (!isset($byTypeMap[$tId])) {
                        $byTypeMap[$tId] = [
                            'type_id' => $tId,
                            'type_label' => $tLabel,
                            'waste_units' => 0.0,
                            'waste_kg' => 0.0,
                        ];
                    }
                    $byTypeMap[$tId]['waste_units'] += $units;
                    $byTypeMap[$tId]['waste_kg'] += $kg;

                    // OT
                    $ot = trim((string)($r['work_order_number'] ?? ''));
                    if ($ot !== '') {
                        if (!isset($byOtMap[$ot])) {
                            $byOtMap[$ot] = [
                                'work_order_number' => $ot,
                                'cost_center' => trim((string)($r['cost_center'] ?? '')),
                                'operator_name' => trim((string)($r['operator_name'] ?? '')) ?: 'N/D',
                                'machine_label' => $mLabel,
                                'production_type_title' => $pClass['title'],
                                'requested_units' => (float)($r['requested_units'] ?? 0.0),
                                'produced_units' => 0.0,
                                'waste_units' => 0.0,
                                'waste_kg' => 0.0,
                                'waste_percent' => null,
                            ];
                        }
                        $byOtMap[$ot]['waste_units'] += $units;
                        $byOtMap[$ot]['waste_kg'] += $kg;
                    }
                }

                // Fetch produced units per machine in period
                if ($byMachineMap !== []) {
                    $mIds = array_keys($byMachineMap);
                    $mPlaceholders = [];
                    $mParams = [':start_ts' => $startTs, ':end_ts' => $endTs];
                    foreach (array_values($mIds) as $idx => $id) {
                        $ph = ':m_id_' . $idx;
                        $mPlaceholders[] = $ph;
                        $mParams[$ph] = $id;
                    }
                    $stmtProd = $this->erpPdo->prepare("
                        SELECT
                            t.machine_id,
                            COALESCE(SUM(t.produced_units), 0) AS produced_units
                        FROM (
                            SELECT
                                machine_id,
                                work_order_number,
                                event_date,
                                MAX(sum_units) AS produced_units
                            FROM (
                                SELECT
                                    COALESCE(pa.ag_equipo_id, 0) AS machine_id,
                                    ph.prd_number AS work_order_number,
                                    DATE(FROM_UNIXTIME(e.evt_crtdat)) AS event_date,
                                    LOWER(e.evt_type) AS evt_type,
                                    SUM(e.evt_amount) AS sum_units
                                FROM prod_worker_ot_events e
                                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                                INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
                                WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                                  AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                                  AND pa.ag_equipo_id IN (" . implode(',', $mPlaceholders) . ")
                                GROUP BY pa.ag_equipo_id, ph.prd_number, DATE(FROM_UNIXTIME(e.evt_crtdat)), LOWER(e.evt_type)
                            ) x
                            GROUP BY machine_id, work_order_number, event_date
                        ) t
                        GROUP BY t.machine_id
                    ");
                    $stmtProd->execute($mParams);
                    foreach ($stmtProd->fetchAll(PDO::FETCH_ASSOC) as $mp) {
                        $mid = (int)($mp['machine_id'] ?? 0);
                        if (isset($byMachineMap[$mid])) {
                            $prodVal = (float)($mp['produced_units'] ?? 0.0);
                            $byMachineMap[$mid]['requested_units'] = $prodVal;
                            $mCode = $byMachineMap[$mid]['production_type_code'];
                            if (isset($byProdTypeMap[$mCode])) {
                                $byProdTypeMap[$mCode]['produced_units'] += $prodVal;
                            }
                        }
                    }
                }

                // Compute counts and rates for production types
                foreach ($byMachineMap as $mInfo) {
                    $mCode = $mInfo['production_type_code'];
                    if (isset($byProdTypeMap[$mCode])) {
                        $byProdTypeMap[$mCode]['machines_count']++;
                    }
                }
                foreach ($byProdTypeMap as $k => $pt) {
                    $pProd = (float)$pt['produced_units'];
                    $pWaste = (float)$pt['waste_units'];
                    $byProdTypeMap[$k]['waste_percent'] = $pProd > 0 ? round(($pWaste / $pProd) * 100.0, 2) : null;
                    $byProdTypeMap[$k]['share_percent'] = $totalWasteUnitsAll > 0 ? round(($pWaste / $totalWasteUnitsAll) * 100.0, 1) : 0.0;
                }

                $byMachine = array_values($byMachineMap);
                usort($byMachine, fn($a, $b) => ($b['waste_units'] <=> $a['waste_units']) ?: ($b['waste_kg'] <=> $a['waste_kg']));

                $byType = array_values($byTypeMap);
                usort($byType, fn($a, $b) => ($b['waste_units'] <=> $a['waste_units']) ?: ($b['waste_kg'] <=> $a['waste_kg']));

                uasort($byOtMap, fn($a, $b) => ($b['waste_units'] <=> $a['waste_units']) ?: ($b['waste_kg'] <=> $a['waste_kg']));
                $top10 = array_slice(array_values($byOtMap), 0, 10);

                if ($top10 !== []) {
                    $otNumbers = array_column($top10, 'work_order_number');
                    $otPlaceholders = [];
                    $otParams = [':start_ts' => $startTs, ':end_ts' => $endTs];
                    foreach (array_values($otNumbers) as $idx => $otNum) {
                        $ph = ':ot_num_' . $idx;
                        $otPlaceholders[] = $ph;
                        $otParams[$ph] = $otNum;
                    }
                    $stmtOtProd = $this->erpPdo->prepare("
                        SELECT
                            ph.prd_number,
                            COALESCE(SUM(t.produced_units), 0) AS produced_units
                        FROM (
                            SELECT
                                ph2.prd_number,
                                event_date,
                                MAX(sum_units) AS produced_units
                            FROM (
                                SELECT
                                    ph3.prd_number,
                                    DATE(FROM_UNIXTIME(e.evt_crtdat)) AS event_date,
                                    LOWER(e.evt_type) AS evt_type,
                                    SUM(e.evt_amount) AS sum_units
                                FROM prod_worker_ot_events e
                                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                                INNER JOIN prod_header ph3 ON ph3.id = pa.ag_prdid
                                WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                                  AND ph3.prd_number IN (" . implode(',', $otPlaceholders) . ")
                                  AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                                GROUP BY ph3.prd_number, DATE(FROM_UNIXTIME(e.evt_crtdat)), LOWER(e.evt_type)
                            ) x
                            INNER JOIN prod_header ph2 ON ph2.prd_number = x.prd_number
                            GROUP BY ph2.prd_number, event_date
                        ) t
                        INNER JOIN prod_header ph ON ph.prd_number = t.prd_number
                        GROUP BY ph.prd_number
                    ");
                    $stmtOtProd->execute($otParams);
                    $prodByOt = [];
                    foreach ($stmtOtProd->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $prodByOt[$row['prd_number']] = (float)($row['produced_units'] ?? 0.0);
                    }

                    foreach ($top10 as &$otItem) {
                        $otNum = $otItem['work_order_number'];
                        $prod = (float)($prodByOt[$otNum] ?? 0.0);
                        $otItem['produced_units'] = $prod;
                        $otItem['waste_percent'] = $prod > 0 
                            ? round(($otItem['waste_units'] / $prod) * 100.0, 2)
                            : ($otItem['requested_units'] > 0 ? round(($otItem['waste_units'] / $otItem['requested_units']) * 100.0, 2) : 0.0);
                    }
                    unset($otItem);
                }

                $sources = [
                    'by_machine' => 'prod_worker_ot_defectunits',
                    'by_type' => 'prod_worker_ot_defectunits',
                    'by_production_type' => 'prod_worker_ot_defectunits',
                    'top10' => 'prod_worker_ot_defectunits',
                ];
            } catch (Throwable) {
                $byMachine = [];
                $byType = [];
                $byProdTypeMap = [];
                $top10 = [];
                $sources = ['by_machine' => null, 'by_type' => null, 'by_production_type' => null, 'top10' => null];
            }
        }

        return [
            'by_machine' => $byMachine,
            'by_type' => $byType,
            'by_production_type' => array_values($byProdTypeMap),
            'top10' => $top10,
            'sources' => $sources,
        ];
    }

    /**
     * Obtiene el informe consolidado y analítico de mermas por operador.
     * Permite filtrar por un operador específico o por tipo de producción.
     *
     * @param string $startAt Fecha inicio 'Y-m-d H:i:s'
     * @param string $endAt   Fecha término 'Y-m-d H:i:s'
     * @param int|null $filterOperatorId ID opcional del operador
     * @param string|null $filterProcess Código opcional de proceso ('impresion', 'corte_sellado', 'embalaje', 'pulpo')
     * @return array Resumen, listado de operadores, catálogo y métricas globales
     */
    public function getErpOperatorWasteReport(string $startAt, string $endAt, ?int $filterOperatorId = null, ?string $filterProcess = null): array
    {
        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = 0;
            $endTs = 0;
        }

        $emptyResponse = [
            'summary' => [
                'total_operators' => 0,
                'total_waste_units' => 0.0,
                'total_waste_kg' => 0.0,
                'total_produced_units' => 0.0,
                'global_waste_percent' => 0.0,
                'top_operator' => null,
            ],
            'operators' => [],
            'catalog' => [],
            'processes' => [
                'impresion' => 'Impresión',
                'corte_sellado' => 'Corte y Sellado',
                'embalaje' => 'Embalaje',
                'pulpo' => 'Pulpo Serigráfico',
            ],
        ];

        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return $emptyResponse;
        }

        $classifyMachine = static function (int $machineId, int $typeId, string $machineName): array {
            if ($typeId === 22 || $machineId === 36 || stripos($machineName, 'pulpo') !== false) {
                return ['code' => 'pulpo', 'title' => 'Pulpo Serigráfico', 'icon' => '🐙'];
            }
            if ($typeId === 8 || $typeId === 14 || stripos($machineName, 'sellad') !== false) {
                return ['code' => 'corte_sellado', 'title' => 'Corte y Sellado', 'icon' => '✂️'];
            }
            if ($typeId === 15 || stripos($machineName, 'embalaje') !== false) {
                return ['code' => 'embalaje', 'title' => 'Embalaje', 'icon' => '📦'];
            }
            if ($typeId === 7 || $typeId === 11 || stripos($machineName, 'flexo') !== false || stripos($machineName, 'seri') !== false || stripos($machineName, 'impresora') !== false) {
                return ['code' => 'impresion', 'title' => 'Impresión', 'icon' => '🖨️'];
            }
            return ['code' => 'otros', 'title' => 'Otros Procesos', 'icon' => '⚙️'];
        };

        try {
            $equipoNameCol = $this->erpColumnExists('equipo', 'equipo_name') ? 'eq.equipo_name' : ($this->erpColumnExists('equipo', 'name') ? 'eq.name' : '""');
            $mermaTitleCol = $this->erpColumnExists('prod_mermatypes', 'merma_title') ? 'mt.merma_title' : '""';

            $sqlDefects = "
                SELECT 
                    d.id AS defect_id,
                    d.evt_type,
                    d.evt_merma_typeid,
                    {$mermaTitleCol} AS merma_title,
                    d.evt_amount,
                    d.evt_kgstounits,
                    d.evt_crtdat,
                    COALESCE(pa.ag_equipo_id, pwi.win_equipoid, 0) AS machine_id,
                    {$equipoNameCol} AS equipo_name,
                    COALESCE(eq.equipo_type_id, 0) AS equipo_type_id,
                    ph.prd_number AS work_order_number,
                    ph.prd_reqid AS cost_center,
                    COALESCE(w.id, pwi.win_wrkid, 0) AS operator_id,
                    TRIM(CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, ''))) AS operator_name,
                    COALESCE(w.wrk_rut, '') AS operator_rut,
                    t11.item_weight,
                    t10.fab_med_width,
                    t10.fab_med_height,
                    t10.fab_med_fuelle,
                    t10.fab_mat_gramms,
                    t10.fab_manilla_length
                FROM prod_worker_ot_defectunits d
                INNER JOIN prod_worker_ot_events e ON e.id = d.evt_refid
                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
                LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
                LEFT JOIN workers w ON w.id = pwi.win_wrkid
                LEFT JOIN orders_items t10 ON t10.req_id = pa.ag_reqid
                LEFT JOIN item t11 ON t11.id = t10.item_id
                LEFT JOIN equipo eq ON eq.id = COALESCE(pa.ag_equipo_id, pwi.win_equipoid)
                LEFT JOIN prod_mermatypes mt ON mt.id = d.evt_merma_typeid
                WHERE d.evt_crtdat BETWEEN :start_ts AND :end_ts
                  AND LOWER(d.evt_type) = 'merma'
                ORDER BY d.evt_crtdat DESC
            ";
            $stmt = $this->erpPdo->prepare($sqlDefects);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $rawDefects = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Produced units per worker in the same period
            $sqlProd = "
                SELECT 
                    pwi.win_wrkid AS operator_id,
                    COALESCE(SUM(e.evt_amount), 0) AS produced_units
                FROM prod_worker_ot_events e
                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                INNER JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
                WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                  AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                  AND pwi.win_wrkid > 0
                GROUP BY pwi.win_wrkid
            ";
            $stmtProd = $this->erpPdo->prepare($sqlProd);
            $stmtProd->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $prodMap = $stmtProd->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

            $operatorsMap = [];
            $totalPlantWasteUnits = 0.0;
            $totalPlantWasteKg = 0.0;

            foreach ($rawDefects as $r) {
                $itemWeight = (float)($r['item_weight'] ?? 0);
                $w = (float)($r['fab_med_width'] ?? 0);
                $h = (float)($r['fab_med_height'] ?? 0);
                $f = (float)($r['fab_med_fuelle'] ?? 0);
                $gramms = (float)($r['fab_mat_gramms'] ?? 0);
                $manLen = (float)($r['fab_manilla_length'] ?? 0);

                $unitWeightKg = 0.0;
                if ($itemWeight > 0) {
                    $unitWeightKg = $itemWeight / 1000.0;
                } elseif ($w > 0 && $h > 0 && $gramms > 0) {
                    $areaM2 = (2.0 * ($w + $f) * $h) / 10000.0;
                    $bodyKg = $areaM2 * ($gramms / 1000.0);
                    $manillaKg = ($manLen > 0) ? (2.0 * ($manLen / 100.0) * 0.025 * ($gramms / 1000.0)) : 0.0;
                    $unitWeightKg = $bodyKg + $manillaKg;
                }

                $kg = (float)($r['evt_kgstounits'] ?? 0);
                $rawUnits = (float)($r['evt_amount'] ?? 0);
                $units = ($unitWeightKg > 0 && $kg > 0) ? (float)round($kg / $unitWeightKg) : $rawUnits;

                $mId = (int)($r['machine_id'] ?? 0);
                $typeId = (int)($r['equipo_type_id'] ?? 0);
                $mName = trim((string)($r['equipo_name'] ?? ''));
                $pClass = $classifyMachine($mId, $typeId, $mName);
                $pCode = $pClass['code'];

                // Filter by process if active
                if ($filterProcess !== null && $filterProcess !== '' && $pCode !== $filterProcess) {
                    continue;
                }

                $opId = (int)($r['operator_id'] ?? 0);
                // Filter by operator if active
                if ($filterOperatorId !== null && $filterOperatorId > 0 && $opId !== $filterOperatorId) {
                    continue;
                }

                $totalPlantWasteUnits += $units;
                $totalPlantWasteKg += $kg;

                $opName = trim((string)($r['operator_name'] ?? ''));
                if ($opName === '') {
                    $opName = $opId > 0 ? "Operador #{$opId}" : 'Sin Operador Asignado';
                }
                $opRut = trim((string)($r['operator_rut'] ?? ''));

                if (!isset($operatorsMap[$opId])) {
                    $operatorsMap[$opId] = [
                        'operator_id' => $opId,
                        'operator_name' => $opName,
                        'operator_rut' => $opRut,
                        'produced_units' => (float)($prodMap[$opId] ?? 0.0),
                        'waste_units' => 0.0,
                        'waste_kg' => 0.0,
                        'waste_percent' => 0.0,
                        'share_percent' => 0.0,
                        'ot_count' => 0,
                        'defect_count' => 0,
                        'main_process' => $pClass['title'],
                        'main_process_code' => $pCode,
                        'process_weights' => [],
                        'defect_types_map' => [],
                        'top_defect_type' => 'N/D',
                        'ots_map' => [],
                        'ots' => [],
                    ];
                }

                $operatorsMap[$opId]['waste_units'] += $units;
                $operatorsMap[$opId]['waste_kg'] += $kg;
                $operatorsMap[$opId]['defect_count']++;

                // Track processes
                if (!isset($operatorsMap[$opId]['process_weights'][$pClass['title']])) {
                    $operatorsMap[$opId]['process_weights'][$pClass['title']] = 0.0;
                }
                $operatorsMap[$opId]['process_weights'][$pClass['title']] += ($kg > 0 ? $kg : $units);

                // Track defect types
                $dTitle = trim((string)($r['merma_title'] ?? '')) ?: 'Merma General';
                if (!isset($operatorsMap[$opId]['defect_types_map'][$dTitle])) {
                    $operatorsMap[$opId]['defect_types_map'][$dTitle] = 0.0;
                }
                $operatorsMap[$opId]['defect_types_map'][$dTitle] += ($kg > 0 ? $kg : $units);

                // Track OTs
                $otNumber = trim((string)($r['work_order_number'] ?? ''));
                if ($otNumber !== '') {
                    $operatorsMap[$opId]['ots_map'][$otNumber] = true;
                }

                $mLabel = $mId > 0 ? ($mName !== '' ? "Equipo {$mId} · {$mName}" : "Equipo {$mId}") : 'Sin máquina';
                $operatorsMap[$opId]['ots'][] = [
                    'defect_id' => (int)$r['defect_id'],
                    'ot_number' => $otNumber ?: 'N/D',
                    'cost_center' => trim((string)($r['cost_center'] ?? '')),
                    'machine_label' => $mLabel,
                    'process_title' => $pClass['title'],
                    'process_code' => $pCode,
                    'merma_title' => $dTitle,
                    'units' => $units,
                    'kg' => $kg,
                    'datetime' => date('d/m/Y H:i', (int)$r['evt_crtdat']),
                ];
            }

            // Post-process operators (main process, top defect, % rates)
            $totalProducedAll = 0.0;
            foreach ($operatorsMap as $opId => &$opData) {
                $opData['ot_count'] = count($opData['ots_map']);
                unset($opData['ots_map']);

                // Find main process
                if (!empty($opData['process_weights'])) {
                    arsort($opData['process_weights']);
                    $opData['main_process'] = (string)array_key_first($opData['process_weights']);
                }
                unset($opData['process_weights']);

                // Find top defect
                if (!empty($opData['defect_types_map'])) {
                    arsort($opData['defect_types_map']);
                    $opData['top_defect_type'] = (string)array_key_first($opData['defect_types_map']);
                }
                unset($opData['defect_types_map']);

                $prodU = (float)$opData['produced_units'];
                $totalProducedAll += $prodU;
                $wUnits = (float)$opData['waste_units'];

                $opData['waste_percent'] = $prodU > 0 ? round(($wUnits / $prodU) * 100.0, 2) : ($wUnits > 0 ? 100.0 : 0.0);
                $opData['share_percent'] = $totalPlantWasteUnits > 0 ? round(($wUnits / $totalPlantWasteUnits) * 100.0, 1) : 0.0;
            }
            unset($opData);

            $operatorsList = array_values($operatorsMap);
            usort($operatorsList, static fn($a, $b) => ($b['waste_kg'] <=> $a['waste_kg']) ?: ($b['waste_units'] <=> $a['waste_units']));

            // Catalog of workers for filter
            $stmtCat = $this->erpPdo->query("
                SELECT id, TRIM(CONCAT(COALESCE(wrk_firstname, ''), ' ', COALESCE(wrk_lastname, ''))) AS name, COALESCE(wrk_rut, '') AS rut
                FROM workers
                WHERE wrk_status = 1
                ORDER BY wrk_firstname, wrk_lastname
            ");
            $catalog = $stmtCat->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $globalWastePct = $totalProducedAll > 0 ? round(($totalPlantWasteUnits / $totalProducedAll) * 100.0, 2) : 0.0;
            $topOp = !empty($operatorsList) ? $operatorsList[0]['operator_name'] . ' (' . number_format($operatorsList[0]['waste_kg'], 1, ',', '.') . ' kg)' : 'N/D';

            return [
                'summary' => [
                    'total_operators' => count($operatorsList),
                    'total_waste_units' => $totalPlantWasteUnits,
                    'total_waste_kg' => $totalPlantWasteKg,
                    'total_produced_units' => $totalProducedAll,
                    'global_waste_percent' => $globalWastePct,
                    'top_operator' => $topOp,
                ],
                'operators' => $operatorsList,
                'catalog' => $catalog,
                'processes' => [
                    'impresion' => 'Impresión',
                    'corte_sellado' => 'Corte y Sellado',
                    'embalaje' => 'Embalaje',
                    'pulpo' => 'Pulpo Serigráfico',
                ],
            ];
        } catch (Throwable) {
            return $emptyResponse;
        }
    }

    public function getProductionDashboardKpis(string $startAt, string $endAt): array
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(units_qty), 0) AS produced_units FROM boxes WHERE created_at BETWEEN :start AND :end');
        $stmt->execute([':start' => $startAt, ':end' => $endAt]);
        $producedUnits = (float)($stmt->fetchColumn() ?: 0);

        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(units_qty), 0) AS dispatched_units
             FROM boxes
             WHERE destination_mode = "CUSTOMER_ORDER"
               AND created_at BETWEEN :start AND :end'
        );
        $stmt->execute([':start' => $startAt, ':end' => $endAt]);
        $dispatchedUnits = (float)($stmt->fetchColumn() ?: 0);

        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(COALESCE(wo.target_qty, 0)), 0) AS pending_units
             FROM work_orders wo
             LEFT JOIN erp_work_order_sync sync ON sync.work_order_id = wo.id
             WHERE wo.status IN ("OPEN","ACTIVE","CUTTING")
               AND COALESCE(
                   CASE WHEN sync.erp_plan_timestamp IS NOT NULL AND sync.erp_plan_timestamp > 0 THEN FROM_UNIXTIME(sync.erp_plan_timestamp) ELSE NULL END,
                   wo.created_at
               ) BETWEEN :start AND :end'
        );
        $stmt->execute([':start' => $startAt, ':end' => $endAt]);
        $pendingUnits = (float)($stmt->fetchColumn() ?: 0);

        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.roll_code, r.meters, r.weight_kg, r.created_at,
                    wo.id AS work_order_id, wo.ot_code, wo.target_qty,
                    COALESCE(roll_totals.total_rolls, 0) AS total_rolls
             FROM rolls r
             LEFT JOIN work_orders wo ON wo.id = r.source_work_order_id
             LEFT JOIN (
                SELECT source_work_order_id, COUNT(*) AS total_rolls
                FROM rolls
                WHERE source_work_order_id IS NOT NULL
                GROUP BY source_work_order_id
             ) roll_totals ON roll_totals.source_work_order_id = r.source_work_order_id
             WHERE r.process_stage = "PRINTED"
               AND r.status <> "CONSUMED"
             ORDER BY r.id DESC
             LIMIT 40'
        );
        $stmt->execute();
        $semiRows = $stmt->fetchAll();
        foreach ($semiRows as &$row) {
            $targetQty = (float)($row['target_qty'] ?? 0);
            $totalRolls = (int)($row['total_rolls'] ?? 0);
            $row['estimated_units'] = $targetQty > 0 && $totalRolls > 0 ? round($targetQty / $totalRolls, 3) : null;
        }
        unset($row);

        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(weight_kg), 0) AS waste_kg FROM production_wastes WHERE created_at BETWEEN :start AND :end');
        $stmt->execute([':start' => $startAt, ':end' => $endAt]);
        $wasteKg = (float)($stmt->fetchColumn() ?: 0);

        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.process_weight_kg")) AS DECIMAL(12,3))), 0) AS processed_kg
             FROM events
             WHERE type = "WORK_ORDER_ROLL_ATTACHED"
               AND created_at BETWEEN :start AND :end'
        );
        $stmt->execute([':start' => $startAt, ':end' => $endAt]);
        $processedKg = (float)($stmt->fetchColumn() ?: 0);

        $stmt = $this->pdo->prepare(
            'SELECT wo.id,
                    wo.ot_code,
                    wo.sku_final,
                    wo.status,
                    wo.created_at,
                    sync.erp_machine_label AS machine_name,
                    COALESCE(wo.target_qty, 0) AS target_qty,
                    COALESCE(
                        CASE WHEN sync.erp_plan_timestamp IS NOT NULL AND sync.erp_plan_timestamp > 0 THEN FROM_UNIXTIME(sync.erp_plan_timestamp) ELSE NULL END,
                        wo.created_at
                    ) AS planned_at,
                    COALESCE(box_stats.produced_units, 0) AS produced_units,
                    COALESCE(box_stats.dispatched_units, 0) AS dispatched_units,
                    COALESCE(box_stats.boxes_count, 0) AS boxes_count,
                    box_stats.last_box_at,
                    COALESCE(waste_stats.waste_kg, 0) AS waste_kg,
                    COALESCE(waste_stats.waste_records, 0) AS waste_records,
                    COALESCE(process_stats.processed_kg, 0) AS processed_kg,
                    COALESCE(process_stats.attached_events, 0) AS attached_events,
                    COALESCE(semi_stats.semi_rolls_count, 0) AS semi_rolls_count,
                    COALESCE(semi_stats.semi_weight_kg, 0) AS semi_weight_kg,
                    COALESCE(semi_stats.semi_meters, 0) AS semi_meters
             FROM work_orders wo
             LEFT JOIN erp_work_order_sync sync ON sync.work_order_id = wo.id
             LEFT JOIN (
                SELECT work_order_id,
                       COALESCE(SUM(units_qty), 0) AS produced_units,
                       COALESCE(SUM(CASE WHEN destination_mode = "CUSTOMER_ORDER" THEN units_qty ELSE 0 END), 0) AS dispatched_units,
                       COUNT(*) AS boxes_count,
                       MAX(created_at) AS last_box_at
                FROM boxes
                WHERE created_at BETWEEN :box_start AND :box_end
                GROUP BY work_order_id
             ) box_stats ON box_stats.work_order_id = wo.id
             LEFT JOIN (
                SELECT work_order_id,
                       COALESCE(SUM(weight_kg), 0) AS waste_kg,
                       COUNT(*) AS waste_records
                FROM production_wastes
                WHERE created_at BETWEEN :waste_start AND :waste_end
                GROUP BY work_order_id
             ) waste_stats ON waste_stats.work_order_id = wo.id
             LEFT JOIN (
                SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) AS work_order_id,
                       COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.process_weight_kg")) AS DECIMAL(12,3))), 0) AS processed_kg,
                       COUNT(*) AS attached_events
                FROM events
                WHERE type = "WORK_ORDER_ROLL_ATTACHED"
                  AND created_at BETWEEN :process_start AND :process_end
                GROUP BY CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED)
             ) process_stats ON process_stats.work_order_id = wo.id
             LEFT JOIN (
                SELECT source_work_order_id AS work_order_id,
                       COUNT(*) AS semi_rolls_count,
                       COALESCE(SUM(weight_kg), 0) AS semi_weight_kg,
                       COALESCE(SUM(meters), 0) AS semi_meters
                FROM rolls
                WHERE process_stage = "PRINTED"
                  AND status <> "CONSUMED"
                  AND source_work_order_id IS NOT NULL
                GROUP BY source_work_order_id
             ) semi_stats ON semi_stats.work_order_id = wo.id
             WHERE (
                COALESCE(
                    CASE WHEN sync.erp_plan_timestamp IS NOT NULL AND sync.erp_plan_timestamp > 0 THEN FROM_UNIXTIME(sync.erp_plan_timestamp) ELSE NULL END,
                    wo.created_at
                ) BETWEEN :plan_start AND :plan_end
                OR box_stats.work_order_id IS NOT NULL
                OR waste_stats.work_order_id IS NOT NULL
                OR process_stats.work_order_id IS NOT NULL
                OR semi_stats.work_order_id IS NOT NULL
             )
             ORDER BY
                CASE wo.status
                    WHEN "ACTIVE" THEN 0
                    WHEN "OPEN" THEN 1
                    WHEN "CUTTING" THEN 2
                    WHEN "CLOSED" THEN 3
                    ELSE 4
                END,
                COALESCE(box_stats.produced_units, 0) DESC,
                wo.id DESC
             LIMIT 80'
        );
        $stmt->execute([
            ':box_start' => $startAt,
            ':box_end' => $endAt,
            ':waste_start' => $startAt,
            ':waste_end' => $endAt,
            ':process_start' => $startAt,
            ':process_end' => $endAt,
            ':plan_start' => $startAt,
            ':plan_end' => $endAt,
        ]);
        $workOrderRows = $stmt->fetchAll();
        foreach ($workOrderRows as &$workOrderRow) {
            $targetQty = (float)($workOrderRow['target_qty'] ?? 0);
            $producedQty = (float)($workOrderRow['produced_units'] ?? 0);
            $processedQty = (float)($workOrderRow['processed_kg'] ?? 0);
            $wasteQty = (float)($workOrderRow['waste_kg'] ?? 0);
            $pendingQty = max(0.0, $targetQty - $producedQty);
            $progressPercent = $targetQty > 0 ? round(min(100, ($producedQty / $targetQty) * 100), 2) : 0.0;
            $dispatchCoveragePercent = $producedQty > 0 ? round(min(100, (((float)($workOrderRow['dispatched_units'] ?? 0)) / $producedQty) * 100), 2) : 0.0;
            $wastePercentByOt = $processedQty > 0 ? round(($wasteQty / $processedQty) * 100, 2) : 0.0;
            $status = (string)($workOrderRow['status'] ?? '');
            if ($status === 'CLOSED' || ($targetQty > 0 && $pendingQty <= 0 && $producedQty > 0)) {
                $dashboardStatus = 'Terminada';
            } elseif ($producedQty > 0 || $processedQty > 0 || $wasteQty > 0) {
                $dashboardStatus = 'Con avance';
            } elseif ($status === 'ACTIVE') {
                $dashboardStatus = 'En produccion';
            } elseif ($status === 'CUTTING') {
                $dashboardStatus = 'En corte';
            } else {
                $dashboardStatus = 'Pendiente';
            }

            $workOrderRow['pending_units'] = round($pendingQty, 3);
            $workOrderRow['progress_percent'] = $progressPercent;
            $workOrderRow['dispatch_coverage_percent'] = $dispatchCoveragePercent;
            $workOrderRow['waste_percent'] = $wastePercentByOt;
            $workOrderRow['dashboard_status'] = $dashboardStatus;
        }
        unset($workOrderRow);

        $wastePercent = $processedKg > 0 ? round(($wasteKg / $processedKg) * 100, 2) : 0.0;

        return [
            'produced_units' => round($producedUnits, 3),
            'pending_units' => round($pendingUnits, 3),
            'dispatched_units' => round($dispatchedUnits, 3),
            'work_orders' => $workOrderRows,
            'semi_rolls' => [
                'count' => count($semiRows),
                'rows' => $semiRows,
            ],
            'warehouses' => $this->stockSummaryWithCapacities(),
            'waste' => [
                'waste_kg' => round($wasteKg, 3),
                'processed_kg' => round($processedKg, 3),
                'percent' => $wastePercent,
            ],
        ];
    }

    public function stockSummaryWithCapacities(): array
    {
        $capacities = [];
        $stmt = $this->pdo->prepare('SELECT warehouse_id, capacity_units_total, capacity_pallets FROM warehouse_capacities');
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            $capacities[(int)$row['warehouse_id']] = [
                'capacity_units_total' => (float)($row['capacity_units_total'] ?? 0),
                'capacity_pallets' => (int)($row['capacity_pallets'] ?? 0),
            ];
        }

        // Consultar descripciones, clasificaciones y capacidades del maestro ERP
        $erpStorehouses = [];
        if ($this->erpPdo !== null) {
            try {
                $stmtErp = $this->erpPdo->query('SELECT id, st_desc, st_clasificacion, st_capacidad FROM company_shops_storehouses WHERE st_status = 1');
                foreach ($stmtErp->fetchAll(PDO::FETCH_ASSOC) as $erow) {
                    $erpStorehouses[(int)$erow['id']] = [
                        'capacidad_erp' => (int)($erow['st_capacidad'] ?? 0),
                        'clasificacion' => trim((string)($erow['st_clasificacion'] ?? '')),
                        'desc' => trim((string)($erow['st_desc'] ?? '')),
                    ];
                }
            } catch (Throwable) {}
        }

        // Consultar el stock real y desglose por categorías (Pallets, Bobinas, Cajas, Unidades) en el ERP
        $erpStockByStorehouse = [];
        if ($this->erpPdo !== null) {
            try {
                $stmtStock = $this->erpPdo->query(
                    "SELECT 
                        iss.st_id,
                        SUM(CASE 
                            WHEN (u.unit_name IN ('PALLET', 'PAL') OR it.item_title LIKE '%PALLET%' OR it.item_number_prod LIKE 'PAL%')
                            THEN iss.iss_inventory ELSE 0 END) AS qty_pallets,
                        SUM(CASE 
                            WHEN (u.unit_name IN ('BOB', 'ROL') OR it.item_number_prod LIKE 'TEL%' OR it.item_title LIKE 'BOBINA%' OR it.item_title LIKE '% BOBINA%')
                                 AND NOT (it.item_title LIKE '%CUCHILLA%')
                                 AND NOT (u.unit_name IN ('PALLET', 'PAL') OR it.item_title LIKE '%PALLET%' OR it.item_number_prod LIKE 'PAL%')
                            THEN iss.iss_inventory ELSE 0 END) AS qty_bobinas,
                        SUM(CASE 
                            WHEN (u.unit_name IN ('CAJA', 'CAJ', 'caj', 'CAJ.600') OR it.item_title LIKE 'CAJA %' OR it.item_title LIKE '% CAJA %' OR it.item_title LIKE 'CAJAS %')
                                 AND NOT (it.item_title LIKE 'BOLSA %' OR it.item_title LIKE 'BOL %')
                                 AND NOT (u.unit_name IN ('PALLET', 'PAL') OR it.item_title LIKE '%PALLET%' OR it.item_number_prod LIKE 'PAL%')
                                 AND NOT (u.unit_name IN ('BOB', 'ROL') OR it.item_number_prod LIKE 'TEL%' OR it.item_title LIKE 'BOBINA%' OR it.item_title LIKE '% BOBINA%')
                            THEN iss.iss_inventory ELSE 0 END) AS qty_cajas,
                        SUM(CASE 
                            WHEN NOT (
                                (u.unit_name IN ('PALLET', 'PAL') OR it.item_title LIKE '%PALLET%' OR it.item_number_prod LIKE 'PAL%')
                                OR ((u.unit_name IN ('BOB', 'ROL') OR it.item_number_prod LIKE 'TEL%' OR it.item_title LIKE 'BOBINA%' OR it.item_title LIKE '% BOBINA%') AND NOT (it.item_title LIKE '%CUCHILLA%'))
                                OR ((u.unit_name IN ('CAJA', 'CAJ', 'caj', 'CAJ.600') OR it.item_title LIKE 'CAJA %' OR it.item_title LIKE '% CAJA %' OR it.item_title LIKE 'CAJAS %') AND NOT (it.item_title LIKE 'BOLSA %' OR it.item_title LIKE 'BOL %'))
                            )
                            THEN iss.iss_inventory ELSE 0 END) AS qty_unidades,
                        SUM(iss.iss_inventory) AS stock_pos,
                        SUM(CASE WHEN iss.iss_inventory > 0 THEN (iss.iss_inventory - iss.iss_inventory_reserved) ELSE 0 END) AS stock_disp,
                        COUNT(DISTINCT it.id) AS items_count
                    FROM item_shops_storehouses iss
                    JOIN item it ON it.id = iss.item_id AND it.item_status = 1
                    LEFT JOIN item_units u ON u.id = it.item_unit
                    WHERE iss.iss_inventory > 0
                    GROUP BY iss.st_id"
                );
                foreach ($stmtStock->fetchAll(PDO::FETCH_ASSOC) as $srow) {
                    $erpStockByStorehouse[(int)$srow['st_id']] = [
                        'qty_pallets' => (float)($srow['qty_pallets'] ?? 0),
                        'qty_bobinas' => (float)($srow['qty_bobinas'] ?? 0),
                        'qty_cajas' => (float)($srow['qty_cajas'] ?? 0),
                        'qty_unidades' => (float)($srow['qty_unidades'] ?? 0),
                        'stock_units' => (float)($srow['stock_pos'] ?? 0),
                        'available_units' => (float)($srow['stock_disp'] ?? 0),
                        'items_count' => (int)($srow['items_count'] ?? 0),
                    ];
                }
            } catch (Throwable) {}
        }

        $rows = $this->stockSummary();
        foreach ($rows as &$row) {
            $warehouseId = (int)($row['warehouse_id'] ?? 0);
            $erpId = (int)($row['erp_storehouse_id'] ?? 0);

            $cap = $capacities[$warehouseId] ?? ['capacity_units_total' => 0.0, 'capacity_pallets' => 0];
            $capUnits = (float)$cap['capacity_units_total'];
            $capPallets = (int)$cap['capacity_pallets'];
            $capErp = isset($erpStorehouses[$erpId]) ? (float)$erpStorehouses[$erpId]['capacidad_erp'] : 0.0;

            // Si no se configuró capacidad explícita en TRZ, considerar la capacidad del maestro ERP
            if ($capUnits <= 0 && $capErp >= 1000) {
                $capUnits = $capErp;
            }
            if ($capPallets <= 0 && $capErp > 0 && $capErp < 1000) {
                $capPallets = (int)$capErp;
            }

            $row['capacity_units_total'] = $capUnits;
            $row['capacity_pallets'] = $capPallets;
            $row['capacidad_erp'] = $capErp;

            // Obtener stock real y desglose de inventario ERP
            $erpStockInfo = $erpStockByStorehouse[$erpId] ?? null;
            $erpUnits = $erpStockInfo !== null ? (float)$erpStockInfo['stock_units'] : 0.0;
            $erpAvailable = $erpStockInfo !== null ? (float)$erpStockInfo['available_units'] : 0.0;
            $erpItemsCount = $erpStockInfo !== null ? (int)$erpStockInfo['items_count'] : 0;
            $trzUnits = (float)($row['stock_units_total'] ?? 0);

            $row['stock_units_erp'] = $erpUnits;
            $row['stock_available_erp'] = $erpAvailable;
            $row['erp_items_count'] = $erpItemsCount;
            $row['stock_units_trz'] = $trzUnits;

            // Cantidades obtenidas directamente de la base de datos ERP (Pallets, Bobinas, Cajas, Unidades)
            if ($erpStockInfo !== null) {
                $row['pallets_count'] = (float)$erpStockInfo['qty_pallets'];
                $row['rolls_count'] = (float)$erpStockInfo['qty_bobinas'];
                $row['boxes_count'] = (float)$erpStockInfo['qty_cajas'];
                $row['units_other_count'] = (float)$erpStockInfo['qty_unidades'];
                $row['stock_units_total'] = $erpUnits;
            } else {
                $row['units_other_count'] = 0.0;
            }

            $row['occupancy_percent'] = null;
            $palletsCount = (float)($row['pallets_count'] ?? 0);
            $stockUnits = (float)$row['stock_units_total'];

            // Calcular ocupación:
            if ($capPallets > 0 && $palletsCount > 0) {
                $row['occupancy_percent'] = round(($palletsCount / $capPallets) * 100, 2);
            } elseif ($capUnits > 0 && $stockUnits > 0) {
                $row['occupancy_percent'] = round(($stockUnits / $capUnits) * 100, 2);
            } elseif ($capPallets > 0 && $palletsCount == 0 && $stockUnits == 0.0) {
                $row['occupancy_percent'] = 0.0;
            } elseif ($capUnits > 0 && $stockUnits == 0.0) {
                $row['occupancy_percent'] = 0.0;
            }
        }
        unset($row);

        return $rows;
    }

    public function listErpDashboardAlerts(int $limit = 6): array
    {
        $summary = $this->getErpDashboardSummary();
        $alerts = [];

        if ((int)$summary['rolls']['ready_for_cut'] > 0) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Bobinas esperando corte',
                'detail' => (string)$summary['rolls']['ready_for_cut'] . ' bobinas impresas siguen pendientes de corte.',
                'link' => '/cut',
            ];
        }
        if ((int)$summary['work_orders']['cutting'] > 0) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'OT pendientes de cierre',
                'detail' => (string)$summary['work_orders']['cutting'] . ' OT ya terminaron impresión y siguen abiertas por corte.',
                'link' => '/work-orders?view=active',
            ];
        }
        if ((int)$summary['reception']['purchase_orders_pending'] > 0) {
            $alerts[] = [
                'level' => 'info',
                'title' => 'Recepciones nacionales pendientes',
                'detail' => (string)$summary['reception']['purchase_orders_pending'] . ' OCs aún tienen líneas por recepcionar.',
                'link' => '/purchase-orders?status=active&supplier_type=NATIONAL',
            ];
        }
        if ((int)$summary['reception']['containers_pending'] > 0) {
            $alerts[] = [
                'level' => 'info',
                'title' => 'Importaciones pendientes',
                'detail' => (string)$summary['reception']['containers_pending'] . ' contenedores siguen con recepción incompleta.',
                'link' => '/import-containers?status=active',
            ];
        }

        $activeWorkOrder = $this->getActiveWorkOrder();
        if ($activeWorkOrder !== null) {
            $alerts[] = [
                'level' => 'success',
                'title' => 'OT activa en planta',
                'detail' => (string)($activeWorkOrder['ot_code'] ?? 'OT') . ' está marcada como activa para operación.',
                'link' => '/work-orders/' . (int)$activeWorkOrder['id'] . '/start',
            ];
        }

        if ($alerts === []) {
            $alerts[] = [
                'level' => 'success',
                'title' => 'Sin alertas críticas',
                'detail' => 'No hay pendientes operativos relevantes en este momento.',
                'link' => '/',
            ];
        }

        return array_slice($alerts, 0, $limit);
    }

    public function listDashboardRecentTraceability(int $limit = 8): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.roll_code, r.process_stage, r.status, r.created_at,
                    pr.roll_code AS parent_roll_code,
                    wo.id AS work_order_id, wo.ot_code,
                    COALESCE(box_stats.box_count, 0) AS box_count,
                    COALESCE(pallet_stats.pallet_count, 0) AS pallet_count
             FROM rolls r
             LEFT JOIN rolls pr ON pr.id = r.parent_roll_id
             LEFT JOIN work_orders wo ON wo.id = r.source_work_order_id
             LEFT JOIN (
                SELECT source_roll_id, COUNT(*) AS box_count
                FROM boxes
                GROUP BY source_roll_id
             ) box_stats ON box_stats.source_roll_id = r.id
             LEFT JOIN (
                SELECT source_roll_id, COUNT(*) AS pallet_count
                FROM pallets
                GROUP BY source_roll_id
             ) pallet_stats ON pallet_stats.source_roll_id = r.id
             WHERE r.parent_roll_id IS NOT NULL
             ORDER BY r.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listRecentOperationalEvents(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, type, created_at, payload
             FROM events
             ORDER BY id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $payload = json_decode((string)($row['payload'] ?? '{}'), true);
            $payload = is_array($payload) ? $payload : [];
            $row['payload_data'] = $payload;
        }
        unset($row);
        return $rows;
    }

    public function listWarehouses(): array
    {
        $this->syncWarehousesFromErp();
        $stmt = $this->pdo->prepare('SELECT id, code, name FROM warehouses ORDER BY code ASC');
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listWarehousesForCut(): array
    {
        $all = $this->listWarehouses();
        $allowedCodes = [700, 1000];
        return array_values(array_filter(
            $all,
            static fn(array $warehouse): bool => in_array((int)($warehouse['code'] ?? 0), $allowedCodes, true)
        ));
    }

    public function getWarehouseById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT w.id, w.code, w.name, w.erp_storehouse_id,
                    COALESCE(wc.capacity_units_total, 0) AS capacity_units_total,
                    COALESCE(wc.capacity_pallets, 0) AS capacity_pallets
             FROM warehouses w
             LEFT JOIN warehouse_capacities wc ON wc.warehouse_id = w.id
             WHERE w.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $erpId = (int)($row['erp_storehouse_id'] ?? 0);
        $desc = '';
        $clasificacion = '';
        $capacidadErp = 0;
        $crtdat = null;
        $flags = [
            'reserva' => 0,
            'repuestos' => 0,
            'unibagreserva' => 0,
            'flexo' => 0,
            'seri' => 0,
            'selladora' => 0,
        ];
        if ($erpId > 0) {
            try {
                $sErp = $this->erpPdo->prepare(
                    'SELECT st_desc, st_clasificacion, st_capacidad, st_crtdat,
                            st_reserva, st_repuestos_act, st_unibagreserva_act, st_unibagflexo_act, st_unibagseri_act, st_unibagsellador_act
                     FROM company_shops_storehouses WHERE id = :id LIMIT 1'
                );
                $sErp->execute([':id' => $erpId]);
                $eRow = $sErp->fetch(PDO::FETCH_ASSOC);
                if ($eRow) {
                    $desc = trim((string)($eRow['st_desc'] ?? ''));
                    $clasificacion = trim((string)($eRow['st_clasificacion'] ?? ''));
                    $capacidadErp = (int)($eRow['st_capacidad'] ?? 0);
                    $crtdat = !empty($eRow['st_crtdat']) ? (int)$eRow['st_crtdat'] : null;
                    $flags['reserva'] = (int)($eRow['st_reserva'] ?? 0);
                    $flags['repuestos'] = (int)($eRow['st_repuestos_act'] ?? 0);
                    $flags['unibagreserva'] = (int)($eRow['st_unibagreserva_act'] ?? 0);
                    $flags['flexo'] = (int)($eRow['st_unibagflexo_act'] ?? 0);
                    $flags['seri'] = (int)($eRow['st_unibagseri_act'] ?? 0);
                    $flags['selladora'] = (int)($eRow['st_unibagsellador_act'] ?? 0);
                }
            } catch (Throwable) {}
        }

        $stockUnitsErp = 0.0;
        $itemsCountErp = 0;
        $qtyPallets = 0.0;
        $qtyBobinas = 0.0;
        $qtyCajas = 0.0;
        $qtyUnidades = 0.0;
        if ($erpId > 0 && $this->erpPdo !== null) {
            try {
                $sStock = $this->erpPdo->prepare(
                    "SELECT 
                        SUM(CASE 
                            WHEN (u.unit_name IN ('PALLET', 'PAL') OR it.item_title LIKE '%PALLET%' OR it.item_number_prod LIKE 'PAL%')
                            THEN iss.iss_inventory ELSE 0 END) AS qty_pallets,
                        SUM(CASE 
                            WHEN (u.unit_name IN ('BOB', 'ROL') OR it.item_number_prod LIKE 'TEL%' OR it.item_title LIKE 'BOBINA%' OR it.item_title LIKE '% BOBINA%')
                                 AND NOT (it.item_title LIKE '%CUCHILLA%')
                                 AND NOT (u.unit_name IN ('PALLET', 'PAL') OR it.item_title LIKE '%PALLET%' OR it.item_number_prod LIKE 'PAL%')
                            THEN iss.iss_inventory ELSE 0 END) AS qty_bobinas,
                        SUM(CASE 
                            WHEN (u.unit_name IN ('CAJA', 'CAJ', 'caj', 'CAJ.600') OR it.item_title LIKE 'CAJA %' OR it.item_title LIKE '% CAJA %' OR it.item_title LIKE 'CAJAS %')
                                 AND NOT (it.item_title LIKE 'BOLSA %' OR it.item_title LIKE 'BOL %')
                                 AND NOT (u.unit_name IN ('PALLET', 'PAL') OR it.item_title LIKE '%PALLET%' OR it.item_number_prod LIKE 'PAL%')
                                 AND NOT (u.unit_name IN ('BOB', 'ROL') OR it.item_number_prod LIKE 'TEL%' OR it.item_title LIKE 'BOBINA%' OR it.item_title LIKE '% BOBINA%')
                            THEN iss.iss_inventory ELSE 0 END) AS qty_cajas,
                        SUM(CASE 
                            WHEN NOT (
                                (u.unit_name IN ('PALLET', 'PAL') OR it.item_title LIKE '%PALLET%' OR it.item_number_prod LIKE 'PAL%')
                                OR ((u.unit_name IN ('BOB', 'ROL') OR it.item_number_prod LIKE 'TEL%' OR it.item_title LIKE 'BOBINA%' OR it.item_title LIKE '% BOBINA%') AND NOT (it.item_title LIKE '%CUCHILLA%'))
                                OR ((u.unit_name IN ('CAJA', 'CAJ', 'caj', 'CAJ.600') OR it.item_title LIKE 'CAJA %' OR it.item_title LIKE '% CAJA %' OR it.item_title LIKE 'CAJAS %') AND NOT (it.item_title LIKE 'BOLSA %' OR it.item_title LIKE 'BOL %'))
                            )
                            THEN iss.iss_inventory ELSE 0 END) AS qty_unidades,
                        SUM(iss.iss_inventory) AS stock_pos,
                        COUNT(DISTINCT it.id) AS items_count
                    FROM item_shops_storehouses iss
                    JOIN item it ON it.id = iss.item_id AND it.item_status = 1
                    LEFT JOIN item_units u ON u.id = it.item_unit
                    WHERE iss.st_id = :st_id AND iss.iss_inventory > 0"
                );
                $sStock->execute([':st_id' => $erpId]);
                $stRow = $sStock->fetch(PDO::FETCH_ASSOC);
                if ($stRow) {
                    $stockUnitsErp = (float)($stRow['stock_pos'] ?? 0);
                    $itemsCountErp = (int)($stRow['items_count'] ?? 0);
                    $qtyPallets = (float)($stRow['qty_pallets'] ?? 0);
                    $qtyBobinas = (float)($stRow['qty_bobinas'] ?? 0);
                    $qtyCajas = (float)($stRow['qty_cajas'] ?? 0);
                    $qtyUnidades = (float)($stRow['qty_unidades'] ?? 0);
                }
            } catch (Throwable) {}
        }

        return [
            'id' => (int)$row['id'],
            'code' => (int)$row['code'],
            'name' => (string)$row['name'],
            'erp_storehouse_id' => $erpId,
            'description' => $desc,
            'clasificacion' => $clasificacion,
            'capacidad_erp' => $capacidadErp,
            'crtdat' => $crtdat,
            'flags' => $flags,
            'capacity_units_total' => (float)($row['capacity_units_total'] ?? 0),
            'capacity_pallets' => (int)($row['capacity_pallets'] ?? 0),
            'stock_units_erp' => $stockUnitsErp,
            'items_count_erp' => $itemsCountErp,
            'qty_pallets' => $qtyPallets,
            'qty_bobinas' => $qtyBobinas,
            'qty_cajas' => $qtyCajas,
            'qty_unidades' => $qtyUnidades,
        ];
    }

    public function listWarehousesWithCapacities(): array
    {
        $this->syncWarehousesFromErp(true);

        $summaryRows = $this->stockSummaryWithCapacities();
        $summaryByWarehouseId = [];
        foreach ($summaryRows as $row) {
            $warehouseId = (int)($row['warehouse_id'] ?? 0);
            if ($warehouseId > 0) {
                $summaryByWarehouseId[$warehouseId] = $row;
            }
        }

        $erpDescs = [];
        try {
            $stmtErp = $this->erpPdo->query('SELECT id, st_desc, st_clasificacion, st_capacidad, st_crtdat FROM company_shops_storehouses WHERE st_status = 1');
            foreach ($stmtErp->fetchAll(PDO::FETCH_ASSOC) as $erow) {
                $erpDescs[(int)$erow['id']] = [
                    'desc' => trim((string)($erow['st_desc'] ?? '')),
                    'clasificacion' => trim((string)($erow['st_clasificacion'] ?? '')),
                    'capacidad_erp' => (int)($erow['st_capacidad'] ?? 0),
                    'crtdat' => !empty($erow['st_crtdat']) ? (int)$erow['st_crtdat'] : null,
                ];
            }
        } catch (Throwable) {}

        $stmt = $this->pdo->prepare(
            'SELECT w.id, w.code, w.name, w.erp_storehouse_id,
                    COALESCE(wc.capacity_units_total, 0) AS capacity_units_total,
                    COALESCE(wc.capacity_pallets, 0) AS capacity_pallets
             FROM warehouses w
             LEFT JOIN warehouse_capacities wc ON wc.warehouse_id = w.id
             ORDER BY w.code ASC'
        );
        $stmt->execute();
        $warehouses = $stmt->fetchAll();

        $result = [];
        foreach ($warehouses as $w) {
            $id = (int)($w['id'] ?? 0);
            $erpId = (int)($w['erp_storehouse_id'] ?? 0);
            $erpData = $erpDescs[$erpId] ?? [];
            $desc = (string)($erpData['desc'] ?? '');
            $clasificacion = (string)($erpData['clasificacion'] ?? '');
            $capacidadErp = (int)($erpData['capacidad_erp'] ?? 0);
            $crtdat = $erpData['crtdat'] ?? null;

            $summary = $summaryByWarehouseId[$id] ?? null;
            if ($summary !== null) {
                $result[] = [
                    'id' => $id,
                    'code' => (int)($summary['warehouse_code'] ?? $w['code'] ?? 0),
                    'name' => (string)($summary['warehouse_name'] ?? $w['name'] ?? ''),
                    'erp_storehouse_id' => $erpId,
                    'description' => $desc,
                    'clasificacion' => $clasificacion,
                    'capacidad_erp' => $capacidadErp,
                    'crtdat' => $crtdat,
                    'capacity_units_total' => (float)($summary['capacity_units_total'] ?? $w['capacity_units_total'] ?? 0),
                    'capacity_pallets' => (int)($summary['capacity_pallets'] ?? $w['capacity_pallets'] ?? 0),
                    'rolls_count' => (float)($summary['rolls_count'] ?? 0),
                    'boxes_count' => (float)($summary['boxes_count'] ?? 0),
                    'pallets_count' => (float)($summary['pallets_count'] ?? 0),
                    'units_other_count' => (float)($summary['units_other_count'] ?? 0),
                    'stock_units_total' => (float)($summary['stock_units_total'] ?? 0),
                    'stock_units_erp' => (float)($summary['stock_units_erp'] ?? 0),
                    'stock_units_trz' => (float)($summary['stock_units_trz'] ?? 0),
                    'erp_items_count' => (int)($summary['erp_items_count'] ?? 0),
                    'occupancy_percent' => isset($summary['occupancy_percent'])
                        ? (is_numeric($summary['occupancy_percent']) ? round((float)$summary['occupancy_percent'], 2) : null)
                        : null,
                ];
            } else {
                $capacityPallets = (int)($w['capacity_pallets'] ?? 0);
                $capacityUnits = (float)($w['capacity_units_total'] ?? 0);
                if ($capacityUnits <= 0 && $capacidadErp >= 1000) {
                    $capacityUnits = (float)$capacidadErp;
                }
                if ($capacityPallets <= 0 && $capacidadErp > 0 && $capacidadErp < 1000) {
                    $capacityPallets = $capacidadErp;
                }
                $occupancy = null;
                if ($capacityPallets > 0) {
                    $occupancy = 0.0;
                } elseif ($capacityUnits > 0) {
                    $occupancy = 0.0;
                }
                $result[] = [
                    'id' => $id,
                    'code' => (int)($w['code'] ?? 0),
                    'name' => (string)($w['name'] ?? ''),
                    'erp_storehouse_id' => $erpId,
                    'description' => $desc,
                    'clasificacion' => $clasificacion,
                    'capacidad_erp' => $capacidadErp,
                    'crtdat' => $crtdat,
                    'capacity_units_total' => $capacityUnits,
                    'capacity_pallets' => $capacityPallets,
                    'rolls_count' => 0.0,
                    'boxes_count' => 0.0,
                    'pallets_count' => 0.0,
                    'units_other_count' => 0.0,
                    'stock_units_total' => 0.0,
                    'stock_units_erp' => 0.0,
                    'stock_units_trz' => 0.0,
                    'erp_items_count' => 0,
                    'occupancy_percent' => $occupancy === null ? null : round((float)$occupancy, 2),
                ];
            }
        }

        return $result;
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function createWarehouse(int $code, string $name, float $capacityUnitsTotal, int $capacityPallets, string $desc = '', array $extraErp = []): array
    {
        $errors = [];
        $code = max(0, $code);
        $name = trim($name);
        $desc = trim($desc);
        $capacityUnitsTotal = (int)round(max(0.0, $capacityUnitsTotal));
        $capacityPallets = max(0, $capacityPallets);

        if ($code <= 0) {
            $errors[] = 'El código de bodega debe ser mayor a 0.';
        }
        if ($name === '') {
            $errors[] = 'El nombre de la bodega es obligatorio.';
        }

        $check = $this->pdo->prepare('SELECT id FROM warehouses WHERE code = :code LIMIT 1');
        $check->execute([':code' => $code]);
        if ($check->fetch() !== false) {
            $errors[] = 'Ya existe una bodega con el código ' . $code . '.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $clasificacion = trim((string)($extraErp['clasificacion'] ?? ''));
        $capacidadErp = (int)($extraErp['capacidad_erp'] ?? 0);
        $reserva = !empty($extraErp['reserva']) ? 1 : 0;
        $repuestos = !empty($extraErp['repuestos']) ? 1 : 0;
        $unibagreserva = !empty($extraErp['unibagreserva']) ? 1 : 0;
        $flexo = !empty($extraErp['flexo']) ? 1 : 0;
        $seri = !empty($extraErp['seri']) ? 1 : 0;
        $selladora = !empty($extraErp['selladora']) ? 1 : 0;

        $erpStorehouseId = null;
        try {
            $currtme = time();
            $userId = (int)($_SESSION['auth_user_id'] ?? $_SESSION['user_id'] ?? 1);
            $erpInsert = $this->erpPdo->prepare(
                'INSERT INTO company_shops_storehouses (
                    st_name, st_desc, st_shop_id, st_status, st_crtdat, st_crtusr,
                    st_clasificacion, st_capacidad, st_reserva, st_repuestos_act,
                    st_unibagreserva_act, st_unibagflexo_act, st_unibagseri_act, st_unibagsellador_act
                 ) VALUES (
                    :name, :desc, :shop_id, 1, :crtdat, :crtusr,
                    :clasif, :cap_erp, :reserva, :repuestos,
                    :ub_reserva, :ub_flexo, :ub_seri, :ub_sella
                 )'
            );
            $erpInsert->execute([
                ':name' => $name,
                ':desc' => $desc,
                ':shop_id' => 30010,
                ':crtdat' => $currtme,
                ':crtusr' => $userId,
                ':clasif' => $clasificacion,
                ':cap_erp' => $capacidadErp,
                ':reserva' => $reserva,
                ':repuestos' => $repuestos,
                ':ub_reserva' => $unibagreserva,
                ':ub_flexo' => $flexo,
                ':ub_seri' => $seri,
                ':ub_sella' => $selladora,
            ]);
            $erpStorehouseId = (int)$this->erpPdo->lastInsertId();
        } catch (Throwable) {}

        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare('INSERT INTO warehouses (code, name, erp_storehouse_id) VALUES (:code, :name, :erp_id)');
            $insert->execute([':code' => $code, ':name' => $name, ':erp_id' => $erpStorehouseId]);
            $id = (int)$this->pdo->lastInsertId();

            $cap = $this->pdo->prepare(
                'INSERT INTO warehouse_capacities (warehouse_id, capacity_units_total, capacity_pallets)
                 VALUES (:id, :units, :pallets)
                 ON DUPLICATE KEY UPDATE capacity_units_total = VALUES(capacity_units_total), capacity_pallets = VALUES(capacity_pallets)'
            );
            $cap->execute([
                ':id' => $id,
                ':units' => $capacityUnitsTotal,
                ':pallets' => $capacityPallets,
            ]);

            $this->pdo->commit();
            return ['ok' => true, 'id' => $id];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'errors' => ['No se pudo crear la bodega: ' . $e->getMessage()]];
        }
    }

    /**
     * @return array{ok:bool, errors?:string[]}
     */
    public function updateWarehouse(int $id, int $code, string $name, float $capacityUnitsTotal, int $capacityPallets, string $desc = '', array $extraErp = []): array
    {
        $errors = [];
        $code = max(0, $code);
        $name = trim($name);
        $desc = trim($desc);
        $capacityUnitsTotal = (int)round(max(0.0, $capacityUnitsTotal));
        $capacityPallets = max(0, $capacityPallets);

        $current = $this->pdo->prepare('SELECT id, code, erp_storehouse_id FROM warehouses WHERE id = :id LIMIT 1');
        $current->execute([':id' => $id]);
        $currentRow = $current->fetch();
        if ($currentRow === false) {
            return ['ok' => false, 'errors' => ['La bodega seleccionada no existe.']];
        }

        if ($code <= 0) {
            $errors[] = 'El código de bodega debe ser mayor a 0.';
        }
        if ($name === '') {
            $errors[] = 'El nombre de la bodega es obligatorio.';
        }

        if ($code !== (int)$currentRow['code']) {
            $check = $this->pdo->prepare('SELECT id FROM warehouses WHERE code = :code AND id <> :id LIMIT 1');
            $check->execute([':code' => $code, ':id' => $id]);
            if ($check->fetch() !== false) {
                $errors[] = 'Ya existe otra bodega con el código ' . $code . '.';
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $erpStorehouseId = (int)($currentRow['erp_storehouse_id'] ?? 0);
        if ($erpStorehouseId > 0) {
            try {
                $currtme = time();
                $userId = (int)($_SESSION['auth_user_id'] ?? $_SESSION['user_id'] ?? 1);
                $clasificacion = trim((string)($extraErp['clasificacion'] ?? ''));
                $capacidadErp = (int)($extraErp['capacidad_erp'] ?? 0);
                $reserva = !empty($extraErp['reserva']) ? 1 : 0;
                $repuestos = !empty($extraErp['repuestos']) ? 1 : 0;
                $unibagreserva = !empty($extraErp['unibagreserva']) ? 1 : 0;
                $flexo = !empty($extraErp['flexo']) ? 1 : 0;
                $seri = !empty($extraErp['seri']) ? 1 : 0;
                $selladora = !empty($extraErp['selladora']) ? 1 : 0;

                $erpUpd = $this->erpPdo->prepare(
                    'UPDATE company_shops_storehouses
                     SET st_name = :name, st_desc = :desc, st_upddat = :upddat, st_updusr = :updusr,
                         st_clasificacion = :clasif, st_capacidad = :cap_erp, st_reserva = :reserva,
                         st_repuestos_act = :repuestos, st_unibagreserva_act = :ub_reserva,
                         st_unibagflexo_act = :ub_flexo, st_unibagseri_act = :ub_seri,
                         st_unibagsellador_act = :ub_sella
                     WHERE id = :id'
                );
                $erpUpd->execute([
                    ':name' => $name,
                    ':desc' => $desc,
                    ':upddat' => $currtme,
                    ':updusr' => $userId,
                    ':clasif' => $clasificacion,
                    ':cap_erp' => $capacidadErp,
                    ':reserva' => $reserva,
                    ':repuestos' => $repuestos,
                    ':ub_reserva' => $unibagreserva,
                    ':ub_flexo' => $flexo,
                    ':ub_seri' => $seri,
                    ':ub_sella' => $selladora,
                    ':id' => $erpStorehouseId,
                ]);
            } catch (Throwable) {}
        }

        $this->pdo->beginTransaction();
        try {
            $update = $this->pdo->prepare('UPDATE warehouses SET code = :code, name = :name WHERE id = :id');
            $update->execute([':code' => $code, ':name' => $name, ':id' => $id]);

            $cap = $this->pdo->prepare(
                'INSERT INTO warehouse_capacities (warehouse_id, capacity_units_total, capacity_pallets)
                 VALUES (:id, :units, :pallets)
                 ON DUPLICATE KEY UPDATE capacity_units_total = VALUES(capacity_units_total), capacity_pallets = VALUES(capacity_pallets)'
            );
            $cap->execute([
                ':id' => $id,
                ':units' => $capacityUnitsTotal,
                ':pallets' => $capacityPallets,
            ]);

            $this->pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'errors' => ['No se pudo actualizar la bodega: ' . $e->getMessage()]];
        }
    }

    /**
     * @return array{ok:bool, errors?:string[]}
     */
    public function deleteWarehouse(int $id): array
    {
        $rollsStmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM rolls WHERE warehouse_id = :id');
        $rollsStmt->execute([':id' => $id]);
        $rollsCount = (int)($rollsStmt->fetch()['c'] ?? 0);

        $palletsStmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM pallets WHERE warehouse_id = :id');
        $palletsStmt->execute([':id' => $id]);
        $palletsCount = (int)($palletsStmt->fetch()['c'] ?? 0);

        $boxesStmt = $this->pdo->prepare('SELECT COUNT(*) AS c FROM boxes WHERE warehouse_id = :id');
        $boxesStmt->execute([':id' => $id]);
        $boxesCount = (int)($boxesStmt->fetch()['c'] ?? 0);

        if ($rollsCount > 0 || $palletsCount > 0 || $boxesCount > 0) {
            $parts = [];
            if ($rollsCount > 0) $parts[] = $rollsCount . ' bobina(s)';
            if ($palletsCount > 0) $parts[] = $palletsCount . ' pallet(s)';
            if ($boxesCount > 0) $parts[] = $boxesCount . ' caja(s)';
            return [
                'ok' => false,
                'errors' => ['No se puede eliminar la bodega: tiene ' . implode(', ', $parts) . ' asociadas en trazabilidad.'],
            ];
        }

        $current = $this->pdo->prepare('SELECT id, erp_storehouse_id FROM warehouses WHERE id = :id LIMIT 1');
        $current->execute([':id' => $id]);
        $currentRow = $current->fetch();
        $erpStorehouseId = (int)($currentRow['erp_storehouse_id'] ?? 0);

        if ($erpStorehouseId > 0) {
            try {
                $currtme = time();
                $userId = (int)($_SESSION['auth_user_id'] ?? $_SESSION['user_id'] ?? 1);
                $erpDel = $this->erpPdo->prepare(
                    'UPDATE company_shops_storehouses
                     SET st_status = 0, st_upddat = :upddat, st_updusr = :updusr
                     WHERE id = :id'
                );
                $erpDel->execute([
                    ':upddat' => $currtme,
                    ':updusr' => $userId,
                    ':id' => $erpStorehouseId,
                ]);
            } catch (Throwable) {}
        }

        try {
            $delete = $this->pdo->prepare('DELETE FROM warehouses WHERE id = :id');
            $delete->execute([':id' => $id]);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'errors' => ['No se pudo eliminar la bodega: ' . $e->getMessage()]];
        }
    }

    public function listProductionMachinesWithSessions(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pm.id, pm.code, pm.name, pm.production_area, pm.erp_machine_id, pm.plant_label, pm.sort_order,
                    pmt.name AS machine_type_name, pmt.code AS machine_type_code, pmt.production_area AS machine_type_area,
                    pss.id AS active_session_id, pss.work_order_id AS active_work_order_id, pss.operator_name, pss.helper_name,
                    pss.shift_label, pss.process_stage, pss.comments, pss.started_at, pss.ended_at, pss.status AS session_status,
                    wo.ot_code AS active_work_order_code, wo.sku_final AS active_work_order_sku
             FROM production_machines pm
             INNER JOIN production_machine_types pmt ON pmt.id = pm.machine_type_id
             LEFT JOIN production_shift_sessions pss
               ON pss.machine_id = pm.id
              AND pss.status = "ACTIVE"
              AND pss.id = (
                    SELECT MAX(pss2.id)
                    FROM production_shift_sessions pss2
                    WHERE pss2.machine_id = pm.id
                      AND pss2.status = "ACTIVE"
               )
             LEFT JOIN work_orders wo ON wo.id = pss.work_order_id
             WHERE pm.is_active = 1
             ORDER BY pmt.display_order ASC, pm.sort_order ASC, pm.id ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listProductionMachineTypes(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, code, name, production_area, erp_machine_type_id, display_order
             FROM production_machine_types
             WHERE is_active = 1
             ORDER BY display_order ASC, name ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getProductionMachine(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pm.*, pmt.name AS machine_type_name, pmt.code AS machine_type_code, pmt.production_area AS machine_type_area
             FROM production_machines pm
             INNER JOIN production_machine_types pmt ON pmt.id = pm.machine_type_id
             WHERE pm.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listProductionPersonnelNames(): array
    {
        $names = [];

        try {
            $stmt = $this->pdo->query(
                'SELECT DISTINCT display_name
                 FROM auth_users
                 WHERE is_active = 1
                   AND (can_operator = 1 OR can_production = 1)
                   AND TRIM(COALESCE(display_name, "")) <> ""
                 ORDER BY display_name ASC'
            );
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
                $name = trim((string)$value);
                if ($name !== '') {
                    $names[$name] = true;
                }
            }
        } catch (Throwable) {
            // Si auth_users aún no existe, seguimos con nombres históricos.
        }

        $stmt = $this->pdo->query(
            'SELECT DISTINCT operator_name AS person_name
             FROM production_shift_sessions
             WHERE TRIM(COALESCE(operator_name, "")) <> ""
             UNION
             SELECT DISTINCT helper_name AS person_name
             FROM production_shift_sessions
             WHERE TRIM(COALESCE(helper_name, "")) <> ""
             ORDER BY person_name ASC'
        );
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
            $name = trim((string)$value);
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        $result = array_values(array_keys($names));
        natcasesort($result);
        return array_values($result);
    }

    public function listErpWorkerNames(): array
    {
        if (!$this->erpTableExists('workers')) {
            return [];
        }

        $firstCol = null;
        if ($this->erpColumnExists('workers', 'wrk_firstname')) {
            $firstCol = 'wrk_firstname';
        } elseif ($this->erpColumnExists('workers', 'firstname')) {
            $firstCol = 'firstname';
        } elseif ($this->erpColumnExists('workers', 'first_name')) {
            $firstCol = 'first_name';
        }

        $lastCol = null;
        if ($this->erpColumnExists('workers', 'wrk_lastname')) {
            $lastCol = 'wrk_lastname';
        } elseif ($this->erpColumnExists('workers', 'lastname')) {
            $lastCol = 'lastname';
        } elseif ($this->erpColumnExists('workers', 'last_name')) {
            $lastCol = 'last_name';
        }

        $nameCol = null;
        if ($this->erpColumnExists('workers', 'name')) {
            $nameCol = 'name';
        }

        $sql = '';
        if ($firstCol !== null || $lastCol !== null) {
            $sql = 'SELECT DISTINCT TRIM(CONCAT(COALESCE('
                . ($firstCol !== null ? $firstCol : '""')
                . ', ""), " ", COALESCE('
                . ($lastCol !== null ? $lastCol : '""')
                . ', ""))) AS display_name
                FROM workers
                WHERE TRIM(CONCAT(COALESCE('
                . ($firstCol !== null ? $firstCol : '""')
                . ', ""), " ", COALESCE('
                . ($lastCol !== null ? $lastCol : '""')
                . ', ""))) <> ""
                ORDER BY display_name ASC';
        } elseif ($nameCol !== null) {
            $sql = 'SELECT DISTINCT TRIM(COALESCE(' . $nameCol . ', "")) AS display_name
                    FROM workers
                    WHERE TRIM(COALESCE(' . $nameCol . ', "")) <> ""
                    ORDER BY display_name ASC';
        } else {
            return [];
        }

        try {
            $stmt = $this->erpPdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $names = [];
            foreach (is_array($rows) ? $rows : [] as $v) {
                $name = trim((string)$v);
                if ($name !== '') {
                    $names[] = $name;
                }
            }
            $names = array_values(array_unique($names));
            sort($names, SORT_NATURAL | SORT_FLAG_CASE);
            return $names;
        } catch (Throwable) {
            return [];
        }
    }

    public function getActiveShiftSessionByOperator(string $operatorName): ?array
    {
        $operatorName = trim($operatorName);
        if ($operatorName === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT pss.*, pm.name AS machine_name, pm.code AS machine_code, pm.production_area AS machine_area,
                    pm.erp_machine_id, pmt.name AS machine_type_name, pmt.code AS machine_type_code,
                    pmt.erp_machine_type_id, wo.ot_code, wo.sku_final
             FROM production_shift_sessions pss
             INNER JOIN production_machines pm ON pm.id = pss.machine_id
             INNER JOIN production_machine_types pmt ON pmt.id = pm.machine_type_id
             LEFT JOIN work_orders wo ON wo.id = pss.work_order_id
             WHERE pss.operator_name = :operator_name
               AND pss.status = "ACTIVE"
             ORDER BY pss.id DESC
             LIMIT 1'
        );
        $stmt->execute([':operator_name' => $operatorName]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getActiveShiftSessionByWorkOrder(int $workOrderId): ?array
    {
        if ($workOrderId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT pss.*, pm.name AS machine_name, pm.code AS machine_code, pm.production_area AS machine_area,
                    pm.erp_machine_id, pmt.name AS machine_type_name, pmt.code AS machine_type_code,
                    pmt.erp_machine_type_id
             FROM production_shift_sessions pss
             INNER JOIN production_machines pm ON pm.id = pss.machine_id
             INNER JOIN production_machine_types pmt ON pmt.id = pm.machine_type_id
             WHERE pss.work_order_id = :work_order_id
               AND pss.status = "ACTIVE"
             ORDER BY pss.id DESC
             LIMIT 1'
        );
        $stmt->execute([':work_order_id' => $workOrderId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function startShiftSession(
        int $machineId,
        string $operatorName,
        ?string $helperName = null,
        ?string $shiftLabel = null,
        ?string $processStage = null,
        ?string $comments = null
    ): array {
        $operatorName = trim($operatorName);
        $helperName = trim((string)$helperName);
        $shiftLabel = trim((string)$shiftLabel);
        $comments = trim((string)$comments);
        $machine = $this->getProductionMachine($machineId);

        $errors = [];
        if ($machineId <= 0 || $machine === null) {
            $errors['machine_id'] = 'La máquina seleccionada no existe.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($machine !== null) {
            $currentMachineSession = $this->getActiveShiftSessionByMachine($machineId);
            if ($currentMachineSession !== null) {
                $errors['machine_id'] = 'La máquina ya tiene un turno activo.';
            }
            $currentOperatorSession = $this->getActiveShiftSessionByOperator($operatorName);
            if ($currentOperatorSession !== null) {
                $errors['operator_name'] = 'El operador ya tiene un turno activo en otra máquina.';
            }
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $resolvedStage = $this->normalizeMachineProcessStage(
            $processStage !== null && trim($processStage) !== '' ? $processStage : (string)($machine['production_area'] ?? '')
        );
        $resolvedShiftLabel = $shiftLabel !== '' ? $shiftLabel : 'Turno general';

        $stmt = $this->pdo->prepare(
            'INSERT INTO production_shift_sessions (
                machine_id, work_order_id, operator_name, helper_name, shift_label, process_stage, comments, started_at, ended_at, status
             ) VALUES (
                :machine_id, NULL, :operator_name, :helper_name, :shift_label, :process_stage, :comments, CURRENT_TIMESTAMP, NULL, "ACTIVE"
             )'
        );
        $stmt->execute([
            ':machine_id' => $machineId,
            ':operator_name' => $operatorName,
            ':helper_name' => $helperName !== '' ? $helperName : null,
            ':shift_label' => $resolvedShiftLabel,
            ':process_stage' => $resolvedStage,
            ':comments' => $comments !== '' ? $comments : null,
        ]);

        $sessionId = (int)$this->pdo->lastInsertId();
        $this->insertEvent('SHIFT_SESSION_STARTED', [
            'shift_session_id' => $sessionId,
            'machine_id' => $machineId,
            'machine_name' => (string)($machine['name'] ?? ''),
            'machine_type' => (string)($machine['machine_type_name'] ?? ''),
            'operator_name' => $operatorName,
            'helper_name' => $helperName !== '' ? $helperName : null,
            'shift_label' => $resolvedShiftLabel,
            'process_stage' => $resolvedStage,
            'comments' => $comments !== '' ? $comments : null,
        ]);

        return ['ok' => true, 'errors' => [], 'id' => $sessionId];
    }

    public function endShiftSession(int $sessionId, string $operatorName, ?string $comments = null): array
    {
        $operatorName = trim($operatorName);
        $comments = trim((string)$comments);
        $session = $this->getShiftSession($sessionId);
        $errors = [];
        if ($sessionId <= 0 || $session === null) {
            $errors['session_id'] = 'El turno no existe.';
        } elseif ((string)($session['status'] ?? '') !== 'ACTIVE') {
            $errors['session_id'] = 'El turno ya está cerrado.';
        } elseif ($operatorName !== '' && trim((string)($session['operator_name'] ?? '')) !== $operatorName) {
            $errors['operator_name'] = 'Solo el operador activo puede cerrar este turno.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $existingComments = trim((string)($session['comments'] ?? ''));
        $resolvedComments = $existingComments;
        if ($comments !== '') {
            $resolvedComments = $existingComments !== ''
                ? $existingComments . "\n" . $comments
                : $comments;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE production_shift_sessions
             SET status = "CLOSED",
                 ended_at = CURRENT_TIMESTAMP,
                 comments = :comments
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $sessionId,
            ':comments' => $resolvedComments !== '' ? $resolvedComments : null,
        ]);

        $this->insertEvent('SHIFT_SESSION_ENDED', [
            'shift_session_id' => $sessionId,
            'machine_id' => (int)($session['machine_id'] ?? 0),
            'machine_name' => (string)($session['machine_name'] ?? ''),
            'operator_name' => (string)($session['operator_name'] ?? ''),
            'work_order_id' => (int)($session['work_order_id'] ?? 0) > 0 ? (int)$session['work_order_id'] : null,
            'comments' => $comments !== '' ? $comments : null,
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function updateShiftSessionHeader(int $sessionId, ?string $helperName = null, ?string $comments = null): array
    {
        $session = $this->getShiftSession($sessionId);
        if ($session === null) {
            return ['ok' => false, 'errors' => ['session_id' => 'El turno no existe.']];
        }
        if ((string)($session['status'] ?? '') !== 'ACTIVE') {
            return ['ok' => false, 'errors' => ['session_id' => 'Solo se puede editar un turno activo.']];
        }

        $helperName = trim((string)$helperName);
        $comments = trim((string)$comments);

        $stmt = $this->pdo->prepare(
            'UPDATE production_shift_sessions
             SET helper_name = :helper_name,
                 comments = :comments
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $sessionId,
            ':helper_name' => $helperName !== '' ? $helperName : null,
            ':comments' => $comments !== '' ? $comments : null,
        ]);

        $this->insertEvent('SHIFT_SESSION_HEADER_UPDATED', [
            'shift_session_id' => $sessionId,
            'machine_id' => (int)($session['machine_id'] ?? 0),
            'machine_name' => (string)($session['machine_name'] ?? ''),
            'work_order_id' => (int)($session['work_order_id'] ?? 0) > 0 ? (int)$session['work_order_id'] : null,
            'operator_name' => (string)($session['operator_name'] ?? ''),
            'helper_name' => $helperName !== '' ? $helperName : null,
            'comments' => $comments !== '' ? $comments : null,
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function assignActiveShiftSessionToWorkOrder(int $workOrderId, string $operatorName): void
    {
        $session = $this->getActiveShiftSessionByOperator($operatorName);
        if ($session === null) {
            return;
        }
        if ((int)($session['work_order_id'] ?? 0) === $workOrderId) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE production_shift_sessions
             SET work_order_id = :work_order_id
             WHERE id = :id'
        );
        $stmt->execute([
            ':work_order_id' => $workOrderId,
            ':id' => (int)$session['id'],
        ]);

        $this->insertEvent('SHIFT_SESSION_WORK_ORDER_ASSIGNED', [
            'shift_session_id' => (int)$session['id'],
            'machine_id' => (int)($session['machine_id'] ?? 0),
            'work_order_id' => $workOrderId,
            'operator_name' => trim((string)($session['operator_name'] ?? '')),
        ]);
    }

    public function releaseActiveShiftSessionFromWorkOrder(int $workOrderId, string $operatorName): void
    {
        $session = $this->getActiveShiftSessionByOperator($operatorName);
        if ($session === null || (int)($session['work_order_id'] ?? 0) !== $workOrderId) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE production_shift_sessions
             SET work_order_id = NULL
             WHERE id = :id'
        );
        $stmt->execute([':id' => (int)$session['id']]);

        $this->insertEvent('SHIFT_SESSION_WORK_ORDER_RELEASED', [
            'shift_session_id' => (int)$session['id'],
            'machine_id' => (int)($session['machine_id'] ?? 0),
            'work_order_id' => $workOrderId,
            'operator_name' => trim((string)($session['operator_name'] ?? '')),
        ]);
    }

    public function stockSummary(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT w.id AS warehouse_id, w.code AS warehouse_code, w.name AS warehouse_name, w.erp_storehouse_id,
                    COALESCE(roll_stats.rolls_count, 0) AS rolls_count,
                    COALESCE(roll_stats.roll_units_total, 0) AS roll_units_total,
                    COALESCE(roll_stats.available_rolls_count, 0) AS available_rolls_count,
                    COALESCE(roll_stats.available_roll_units_total, 0) AS available_roll_units_total,
                    COALESCE(roll_stats.unavailable_rolls_count, 0) AS unavailable_rolls_count,
                    COALESCE(roll_stats.unavailable_roll_units_total, 0) AS unavailable_roll_units_total,
                    COALESCE(roll_stats.total_weight_kg, 0) AS total_weight_kg,
                    COALESCE(box_stats.boxes_count, 0) AS boxes_count,
                    COALESCE(box_stats.box_units_total, 0) AS box_units_total,
                    COALESCE(pallet_stats.pallets_count, 0) AS pallets_count,
                    COALESCE(roll_stats.available_roll_units_total, 0) + COALESCE(box_stats.box_units_total, 0) AS stock_units_total
             FROM warehouses w
             LEFT JOIN (
                SELECT warehouse_id,
                       COUNT(*) AS rolls_count,
                       COALESCE(SUM(received_qty), 0) AS roll_units_total,
                       SUM(CASE WHEN status = 'RECEIVED' THEN 1 ELSE 0 END) AS available_rolls_count,
                       COALESCE(SUM(CASE WHEN status = 'RECEIVED' THEN received_qty ELSE 0 END), 0) AS available_roll_units_total,
                       SUM(CASE WHEN status <> 'RECEIVED' THEN 1 ELSE 0 END) AS unavailable_rolls_count,
                       COALESCE(SUM(CASE WHEN status <> 'RECEIVED' THEN received_qty ELSE 0 END), 0) AS unavailable_roll_units_total,
                       COALESCE(SUM(weight_kg), 0) AS total_weight_kg
                FROM rolls
                WHERE status IN ('RECEIVED','IN_PROCESS','BLOCKED')
                GROUP BY warehouse_id
             ) roll_stats ON roll_stats.warehouse_id = w.id
             LEFT JOIN (
                SELECT b.warehouse_id,
                       COUNT(*) AS boxes_count,
                       COALESCE(SUM(b.units_qty), 0) AS box_units_total
                FROM boxes b
                LEFT JOIN pallets p ON p.id = b.pallet_id
                WHERE b.warehouse_id IS NOT NULL
                  AND (b.pallet_id IS NULL OR COALESCE(p.status, '') = 'STORED')
                GROUP BY b.warehouse_id
             ) box_stats ON box_stats.warehouse_id = w.id
             LEFT JOIN (
                SELECT warehouse_id,
                       COUNT(*) AS pallets_count
                FROM pallets
                WHERE warehouse_id IS NOT NULL
                  AND COALESCE(status, '') = 'STORED'
                GROUP BY warehouse_id
             ) pallet_stats ON pallet_stats.warehouse_id = w.id
             ORDER BY w.code ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function inventoryAvailableSkuRowsByWarehouseCode(int $warehouseCode): array
    {
        return $this->inventoryCountService->inventoryAvailableSkuRowsByWarehouseCode($warehouseCode);
    }

    public function inventoryCountDraftRowsByWarehouseCode(int $warehouseCode): array
    {
        return $this->inventoryCountService->inventoryCountDraftRowsByWarehouseCode($warehouseCode);
    }

    public function createInventoryCount(int $warehouseCode, string $warehouseName, string $createdBy, array $items): array
    {
        return $this->inventoryCountService->createInventoryCount($warehouseCode, $warehouseName, $createdBy, $items);
    }

    public function listInventoryCounts(int $limit = 100): array
    {
        return $this->inventoryCountService->listInventoryCounts($limit);
    }

    public function getInventoryCount(int $inventoryCountId): ?array
    {
        return $this->inventoryCountService->getInventoryCount($inventoryCountId);
    }

    public function listInventoryCountItems(int $inventoryCountId): array
    {
        return $this->inventoryCountService->listInventoryCountItems($inventoryCountId);
    }

    public function listErpStorehouses(bool $onlyWithStock = false): array
    {
        return $this->inventoryCountService->listErpStorehouses($onlyWithStock);
    }

    public function getErpStockByStorehouse(int $storehouseId, ?string $search = null, bool $onlyWithStock = false): array
    {
        return $this->inventoryCountService->getErpStockByStorehouse($storehouseId, $search, $onlyWithStock);
    }

    public function getErpInventoryCountDraft(int $storehouseId): array
    {
        return $this->inventoryCountService->getErpInventoryCountDraft($storehouseId);
    }

    public function createErpInventoryCount(int $storehouseId, string $storehouseName, string $annotation, string $operatorName, array $items): array
    {
        return $this->inventoryCountService->createErpInventoryCount($storehouseId, $storehouseName, $annotation, $operatorName, $items);
    }

    public function listErpStockcounts(int $limit = 100): array
    {
        return $this->inventoryCountService->listErpStockcounts($limit);
    }

    public function getErpStockcount(int $id): ?array
    {
        return $this->inventoryCountService->getErpStockcount($id);
    }

    public function listErpStockcountItems(int $id): array
    {
        return $this->inventoryCountService->listErpStockcountItems($id);
    }

    public function listRollsByWarehouseCode(int $warehouseCode, int $limit = 200): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.roll_code, r.weight_kg, r.received_qty, r.microns AS grams, r.width_mm, r.color, r.meters, r.status, r.created_at,
                    w.code AS warehouse_code, w.name AS warehouse_name,
                    s.code AS sku_code, s.description AS sku_description,
                    wo.ot_code AS work_order_code,
                    JSON_UNQUOTE(JSON_EXTRACT(m.payload, "$.operator_name")) AS received_by
             FROM rolls r
             JOIN warehouses w ON w.id = r.warehouse_id
             JOIN skus s ON s.id = r.sku_id
             LEFT JOIN work_orders wo ON wo.id = r.current_work_order_id
             LEFT JOIN movements m
               ON m.entity_type = "ROLL"
              AND m.entity_id = r.id
              AND m.movement_type = "RECEIPT"
              AND m.id = (
                    SELECT MIN(m2.id)
                    FROM movements m2
                    WHERE m2.entity_type = "ROLL"
                      AND m2.entity_id = r.id
                      AND m2.movement_type = "RECEIPT"
              )
             WHERE w.code = :code
             ORDER BY r.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':code', $warehouseCode, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listPalletsByWarehouseCode(int $warehouseCode, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.pallet_code, p.final_sku, p.box_count, p.destination_mode, p.customer_order_ref, p.status, p.created_at,
                    p.operator_name, w.code AS warehouse_code, w.name AS warehouse_name,
                    r.roll_code AS source_roll_code, wo.ot_code,
                    COALESCE(box_stats.units_total, 0) AS units_total
             FROM pallets p
             JOIN warehouses w ON w.id = p.warehouse_id
             LEFT JOIN rolls r ON r.id = p.source_roll_id
             LEFT JOIN work_orders wo ON wo.id = p.work_order_id
             LEFT JOIN (
                SELECT pallet_id, COALESCE(SUM(units_qty), 0) AS units_total
                FROM boxes
                WHERE pallet_id IS NOT NULL
                GROUP BY pallet_id
             ) box_stats ON box_stats.pallet_id = p.id
             WHERE w.code = :code
               AND COALESCE(p.status, "") = "STORED"
             ORDER BY p.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':code', $warehouseCode, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listBoxesByWarehouseCode(int $warehouseCode, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT b.id, b.box_code, b.final_sku, b.units_qty, b.destination_mode, b.customer_order_ref, b.status, b.created_at,
                    b.operator_name, w.code AS warehouse_code, w.name AS warehouse_name,
                    r.roll_code AS source_roll_code, p.pallet_code, wo.ot_code
             FROM boxes b
             JOIN warehouses w ON w.id = b.warehouse_id
             LEFT JOIN rolls r ON r.id = b.source_roll_id
             LEFT JOIN pallets p ON p.id = b.pallet_id
             LEFT JOIN work_orders wo ON wo.id = b.work_order_id
             WHERE w.code = :code
               AND (b.pallet_id IS NULL OR COALESCE(p.status, "") = "STORED")
             ORDER BY b.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':code', $warehouseCode, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getRollByScanCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $code) === 1) {
            return $this->getRoll((int)$code);
        }

        $stmt = $this->pdo->prepare('SELECT id FROM rolls WHERE roll_code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return $this->getRoll((int)$row['id']);
    }

    public function transferRoll(int $rollId, int $toWarehouseId, string $operatorName = '', ?int $workOrderId = null): array
    {
        $errors = [];
        $operatorName = trim($operatorName);

        $roll = $this->getRoll($rollId);
        if ($roll === null) {
            return ['ok' => false, 'errors' => ['roll' => 'Bobina no existe.']];
        }

        $fromWarehouseId = (int)$roll['warehouse_id'];
        $targetWorkOrder = null;
        if ($toWarehouseId <= 0 && !($workOrderId !== null && $workOrderId > 0)) {
            $errors['warehouse_id'] = 'Bodega destino es obligatoria.';
        } elseif ($toWarehouseId > 0 && $toWarehouseId === $fromWarehouseId && !($workOrderId !== null && $workOrderId > 0)) {
            $errors['warehouse_id'] = 'Bodega destino debe ser distinta a la actual.';
        } elseif ($toWarehouseId > 0) {
            $stmt = $this->pdo->prepare('SELECT id FROM warehouses WHERE id = :id');
            $stmt->execute([':id' => $toWarehouseId]);
            if ($stmt->fetch() === false) {
                $errors['warehouse_id'] = 'Bodega destino no existe.';
            }
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($workOrderId !== null && $workOrderId > 0) {
            $targetWorkOrder = $this->getWorkOrder($workOrderId);
            if ($targetWorkOrder === null) {
                $errors['work_order_id'] = 'OT no existe.';
            } elseif (!in_array((string)($targetWorkOrder['status'] ?? ''), ['OPEN', 'ACTIVE', 'CUTTING'], true)) {
                $errors['work_order_id'] = 'La OT destino no está disponible para recibir bobinas.';
            }
        }

        $rollStatus = strtoupper(trim((string)($roll['status'] ?? '')));
        $rollCurrentWorkOrderId = (int)($roll['current_work_order_id'] ?? 0);
        if ($workOrderId !== null && $workOrderId > 0) {
            if (!in_array($rollStatus, ['RECEIVED'], true)) {
                $errors['roll'] = 'Solo se pueden transferir a OT bobinas disponibles.';
            } elseif ($rollCurrentWorkOrderId > 0 && $rollCurrentWorkOrderId !== $workOrderId) {
                $errors['roll'] = 'La bobina ya está asignada a otra OT.';
            } elseif ($rollCurrentWorkOrderId === $workOrderId) {
                $errors['roll'] = 'La bobina ya está asignada a esta OT.';
            } elseif (!in_array((string)($roll['process_stage'] ?? 'RAW'), ['RAW', 'PRINTED'], true)) {
                $errors['roll'] = 'La etapa actual de la bobina no permite ingresarla a una OT.';
            }
        } else {
            if ($rollCurrentWorkOrderId > 0) {
                $errors['roll'] = 'La bobina está asignada a una OT y no se puede trasladar a bodega.';
            } elseif (!in_array($rollStatus, ['RECEIVED'], true)) {
                $errors['roll'] = 'Solo se pueden trasladar a bodega bobinas disponibles.';
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->pdo->beginTransaction();
        try {
            $targetWarehouseId = $toWarehouseId > 0 ? $toWarehouseId : $fromWarehouseId;
            if ($workOrderId !== null && $workOrderId > 0) {
                $productionWarehouseId = $this->findWarehouseIdByCode(3000);
                if ($productionWarehouseId === null) {
                    throw new RuntimeException('No existe la bodega 3000 de producción.');
                }
                $targetWarehouseId = $productionWarehouseId;
            }
            $stmt = $this->pdo->prepare('UPDATE rolls SET warehouse_id = :to, current_work_order_id = :wo, status = :status WHERE id = :id');
            $stmt->execute([
                ':to' => $targetWarehouseId,
                ':wo' => $workOrderId !== null && $workOrderId > 0 ? $workOrderId : null,
                ':status' => $workOrderId !== null && $workOrderId > 0 ? 'IN_PROCESS' : 'RECEIVED',
                ':id' => $rollId,
            ]);

            $stmt = $this->pdo->prepare(
                'INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload)
                 VALUES (:entity_type, :entity_id, :movement_type, :from_warehouse_id, :to_warehouse_id, :payload)'
            );
            $stmt->execute([
                ':entity_type' => 'ROLL',
                ':entity_id' => $rollId,
                ':movement_type' => 'TRANSFER',
                ':from_warehouse_id' => $fromWarehouseId,
                ':to_warehouse_id' => $targetWarehouseId,
                ':payload' => json_encode([
                    'operator_name' => $operatorName,
                    'work_order_id' => $workOrderId !== null && $workOrderId > 0 ? $workOrderId : null,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $this->insertEvent('ROLL_TRANSFERRED', [
                'roll_id' => $rollId,
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $targetWarehouseId,
                'operator_name' => $operatorName,
                'work_order_id' => $workOrderId !== null && $workOrderId > 0 ? $workOrderId : null,
            ]);

            $this->pdo->commit();
            return ['ok' => true, 'errors' => []];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getRoll(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.roll_code, r.warehouse_id, r.weight_kg, r.received_qty, r.reception_mode, r.microns AS grams, r.width_mm, r.color, r.meters, r.status, r.created_at,
                    r.purchase_order_id, r.purchase_order_line_id, r.import_container_id, r.import_container_item_id, r.supplier_id, r.current_work_order_id,
                    r.parent_roll_id, r.source_work_order_id, r.process_stage, pr.roll_code AS parent_roll_code,
                    w.code AS warehouse_code, w.name AS warehouse_name,
                    s.code AS sku_code, s.description AS sku_description,
                    wo.ot_code AS work_order_code, wo.sku_final AS work_order_sku_final
             FROM rolls r
             JOIN warehouses w ON w.id = r.warehouse_id
             JOIN skus s ON s.id = r.sku_id
             LEFT JOIN work_orders wo ON wo.id = r.current_work_order_id
             LEFT JOIN rolls pr ON pr.id = r.parent_roll_id
             WHERE r.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $this->decorateRollWithErpContext($row);
        return $row;
    }

    public function listRollTraceability(int $rollId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.id, m.movement_type, m.created_at,
                    wf.code AS from_warehouse_code, wf.name AS from_warehouse_name,
                    wt.code AS to_warehouse_code, wt.name AS to_warehouse_name,
                    m.payload
             FROM movements m
             LEFT JOIN warehouses wf ON wf.id = m.from_warehouse_id
             LEFT JOIN warehouses wt ON wt.id = m.to_warehouse_id
             WHERE m.entity_type = :entity_type AND m.entity_id = :entity_id
             ORDER BY m.id ASC'
        );
        $stmt->execute([
            ':entity_type' => 'ROLL',
            ':entity_id' => $rollId,
        ]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $payload = json_decode((string)($row['payload'] ?? '{}'), true);
            $row['payload_data'] = is_array($payload) ? $payload : [];
        }
        unset($row);
        return $rows;
    }

    public function listRollOperationalTraceability(int $rollId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, type, created_at, payload
             FROM events
             WHERE type IN ("WORK_ORDER_ROLL_ATTACHED","WORK_ORDER_ROLL_RELEASED","WORK_ORDER_FINISHED","OUTPUT_ROLL_CREATED","CUT_COMPLETED")
               AND (
                    CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.roll_id")) AS UNSIGNED) = :roll_id
                    OR CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.output_roll_id")) AS UNSIGNED) = :output_roll_id
               )
             ORDER BY id ASC'
        );
        $stmt->execute([
            ':roll_id' => $rollId,
            ':output_roll_id' => $rollId,
        ]);
        $rows = $stmt->fetchAll();

        $workOrders = [];
        foreach ($rows as &$row) {
            $payload = json_decode((string)($row['payload'] ?? '{}'), true);
            $payload = is_array($payload) ? $payload : [];
            $workOrderId = (int)($payload['work_order_id'] ?? 0);
            $workOrderLabel = '-';
            if ($workOrderId > 0) {
                if (!isset($workOrders[$workOrderId])) {
                    $workOrders[$workOrderId] = $this->getWorkOrder($workOrderId);
                }
                $workOrderLabel = (string)($workOrders[$workOrderId]['ot_code'] ?? ('OT #' . $workOrderId));
            }

            $row['payload_data'] = $payload;
            $row['work_order_label'] = $workOrderLabel;
        }
        unset($row);

        return $rows;
    }

    public function listWorkOrderTraceability(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, type, created_at, payload
             FROM events
             WHERE type IN ("WORK_ORDER_ACTIVATED","WORK_ORDER_STARTED","CHEMICAL_INPUT_RECORDED","WORK_ORDER_ROLL_ATTACHED","WORK_ORDER_ROLL_RELEASED","WORK_ORDER_FINISHED","MATERIAL_REQUESTED","MATERIAL_DELIVERED","PRODUCTION_WASTE_RECORDED","OUTPUT_ROLL_CREATED","CUT_COMPLETED")
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) = :wo
             ORDER BY id ASC'
        );
        $stmt->execute([':wo' => $workOrderId]);
        $rows = $stmt->fetchAll();

        $rolls = [];
        $chemicals = [];

        foreach ($rows as &$row) {
            $payload = json_decode((string)($row['payload'] ?? '{}'), true);
            $payload = is_array($payload) ? $payload : [];
            $type = (string)($row['type'] ?? '');
            $detail = '-';
            $typeLabel = 'Evento';
            $operatorName = trim((string)($payload['operator_name'] ?? ''));

            if (isset($payload['roll_id']) && (int)$payload['roll_id'] > 0) {
                $rollId = (int)$payload['roll_id'];
                if (!isset($rolls[$rollId])) {
                    $rolls[$rollId] = $this->getRoll($rollId);
                }
            }
            if (isset($payload['chemical_id']) && (int)$payload['chemical_id'] > 0) {
                $chemicalId = (int)$payload['chemical_id'];
                if (!isset($chemicals[$chemicalId])) {
                    $stmtChemical = $this->pdo->prepare('SELECT id, code, name FROM chemicals WHERE id = :id LIMIT 1');
                    $stmtChemical->execute([':id' => $chemicalId]);
                    $chemicals[$chemicalId] = $stmtChemical->fetch() ?: null;
                }
            }

            if ($type === 'WORK_ORDER_ACTIVATED') {
                $typeLabel = 'OT activada';
                $detail = 'OT activada para operación.';
            } elseif ($type === 'WORK_ORDER_STARTED') {
                $typeLabel = 'Producción iniciada';
                $detail = 'Producción iniciada por ' . (string)($payload['operator_name'] ?? '-');
            } elseif ($type === 'CHEMICAL_INPUT_RECORDED') {
                $typeLabel = 'Registro químico';
                $chemical = $chemicals[(int)($payload['chemical_id'] ?? 0)] ?? null;
                $detail = 'Químico '
                    . (string)($chemical['code'] ?? ('#' . (int)($payload['chemical_id'] ?? 0)))
                    . ' · Peso '
                    . (string)($payload['weight_kg'] ?? '0')
                    . ' Kg · Operador '
                    . (string)($payload['operator_name'] ?? '-');
            } elseif ($type === 'WORK_ORDER_ROLL_ATTACHED') {
                $typeLabel = 'Ingreso de bobina';
                $roll = $rolls[(int)($payload['roll_id'] ?? 0)] ?? null;
                $detail = 'Ingreso bobina '
                    . (string)($roll['roll_code'] ?? ('#' . (int)($payload['roll_id'] ?? 0)))
                    . ' · Peso '
                    . (string)($payload['process_weight_kg'] ?? '0')
                    . ' Kg · Merma '
                    . (string)($payload['waste_kg'] ?? '0')
                    . ' Kg';
            } elseif ($type === 'WORK_ORDER_ROLL_RELEASED') {
                $typeLabel = 'Salida de bobina';
                $roll = $rolls[(int)($payload['roll_id'] ?? 0)] ?? null;
                $detail = 'Salida bobina '
                    . (string)($roll['roll_code'] ?? ('#' . (int)($payload['roll_id'] ?? 0)))
                    . ' · Peso final '
                    . (string)($payload['final_weight_kg'] ?? '0')
                    . ' Kg · Motivo '
                    . (string)($payload['reason'] ?? '-');
            } elseif ($type === 'WORK_ORDER_FINISHED') {
                $typeLabel = 'OT finalizada';
                $detail = 'Cierre OT · Peso bobina '
                    . (string)($payload['final_roll_weight_kg'] ?? '0')
                    . ' Kg · Químicos '
                    . (string)($payload['final_chemical_weight_kg'] ?? '0')
                    . ' Kg · Cajas '
                    . (string)($payload['box_qty'] ?? '0');
            } elseif ($type === 'MATERIAL_REQUESTED') {
                $typeLabel = 'Solicitud a bodega';
                $detail = 'Material '
                    . (string)($payload['requested_item'] ?? '-')
                    . ' · Cantidad '
                    . (string)($payload['requested_qty'] ?? '0')
                    . ' · Nota '
                    . (string)($payload['request_notes'] ?? '-');
            } elseif ($type === 'MATERIAL_DELIVERED') {
                $typeLabel = 'Material entregado';
                $detail = 'Bobina '
                    . (string)($payload['roll_code'] ?? ('#' . (int)($payload['roll_id'] ?? 0)))
                    . ' entregada a línea.';
            } elseif ($type === 'PRODUCTION_WASTE_RECORDED') {
                $typeLabel = 'Merma registrada';
                $detail = 'Etapa '
                    . (string)($payload['waste_stage'] ?? '-')
                    . ' · Motivo '
                    . (string)($payload['reason'] ?? '-')
                    . ' · Peso '
                    . (string)($payload['weight_kg'] ?? '0')
                    . ' Kg';
            } elseif ($type === 'OUTPUT_ROLL_CREATED') {
                $typeLabel = 'Nueva bobina salida';
                $detail = 'Bobina '
                    . (string)($payload['output_roll_code'] ?? ('#' . (int)($payload['output_roll_id'] ?? 0)))
                    . ' · Peso '
                    . (string)($payload['output_roll_weight_kg'] ?? '0')
                    . ' Kg';
            } elseif ($type === 'CUT_COMPLETED') {
                $typeLabel = 'Corte finalizado';
                $detail = 'Unidades '
                    . (string)($payload['units_total'] ?? '0')
                    . ' · Cajas '
                    . (string)($payload['box_qty'] ?? '0')
                    . ' · Pallets '
                    . (string)($payload['pallet_qty'] ?? '0');
            }

            $row['payload_data'] = $payload;
            $row['type_label'] = $typeLabel;
            $row['operator_name'] = $operatorName !== '' ? $operatorName : '-';
            $row['detail'] = $detail;
        }
        unset($row);

        return $rows;
    }

    public function listRollsByPurchaseOrderLine(int $purchaseOrderLineId, int $limit = 10, ?int $importContainerItemId = null): array
    {
        $sql = 'SELECT r.id, r.roll_code, r.weight_kg, r.received_qty, r.reception_mode, r.created_at, r.import_container_id, r.import_container_item_id, w.code AS warehouse_code
                FROM rolls r
                JOIN warehouses w ON w.id = r.warehouse_id
                WHERE r.purchase_order_line_id = :pol';
        if ($importContainerItemId !== null && $importContainerItemId > 0) {
            $sql .= ' AND r.import_container_item_id = :container_item_id';
        }
        $sql .= ' ORDER BY r.id DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':pol', $purchaseOrderLineId, PDO::PARAM_INT);
        if ($importContainerItemId !== null && $importContainerItemId > 0) {
            $stmt->bindValue(':container_item_id', $importContainerItemId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['container_code'] = $this->getErpImportContainerCode((int)($row['import_container_id'] ?? 0));
        }
        unset($row);
        return $rows;
    }

    public function createRoll(array $input): array
    {
        return $this->rollReceptionService->createRoll($input);
    }

    private function insertEvent(string $type, array $payload): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO events (type, payload) VALUES (:type, :payload)');
        $stmt->execute([
            ':type' => $type,
            ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function findWarehouseIdByCode(int $warehouseCode): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM warehouses WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $warehouseCode]);
        $row = $stmt->fetch();
        return $row === false ? null : (int)$row['id'];
    }

    private function findOrCreateSkuId(string $skuCode): int
    {
        $skuCode = trim($skuCode);
        $stmt = $this->pdo->prepare('SELECT id FROM skus WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $skuCode]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return (int)$row['id'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO skus (code, description, is_active) VALUES (:code, :description, 1)');
        $stmt->execute([
            ':code' => $skuCode,
            ':description' => $skuCode,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    private function createOutputRollFromWorkOrder(array $workOrder, array $sourceRoll, float $weightKg, string $operatorName): int
    {
        $skuCode = trim((string)($workOrder['sku_final'] ?? ''));
        $skuId = $this->findOrCreateSkuId($skuCode);
        $warehouseId = $this->findWarehouseIdByCode(500) ?? (int)$sourceRoll['warehouse_id'];
        $rollCode = $this->generateProcessRollCode();
        $receivedQty = isset($sourceRoll['received_qty']) ? (float)$sourceRoll['received_qty'] : 1.0;
        $sourceRollId = (int)($sourceRoll['id'] ?? 0);
        $workOrderId = (int)($workOrder['id'] ?? 0);

        $stmt = $this->pdo->prepare(
            'INSERT INTO rolls (roll_code, sku_id, warehouse_id, weight_kg, received_qty, microns, width_mm, color, meters, status, current_work_order_id, parent_roll_id, source_work_order_id, process_stage)
             VALUES (:roll_code, :sku_id, :warehouse_id, :weight_kg, :received_qty, :microns, :width_mm, :color, :meters, :status, NULL, :parent_roll_id, :source_work_order_id, :process_stage)'
        );
        $stmt->execute([
            ':roll_code' => $rollCode,
            ':sku_id' => $skuId,
            ':warehouse_id' => $warehouseId,
            ':weight_kg' => number_format($weightKg, 3, '.', ''),
            ':received_qty' => number_format($receivedQty, 3, '.', ''),
            ':microns' => isset($sourceRoll['grams']) && $sourceRoll['grams'] !== '' ? (int)$sourceRoll['grams'] : null,
            ':width_mm' => isset($sourceRoll['width_mm']) && $sourceRoll['width_mm'] !== '' ? (int)$sourceRoll['width_mm'] : null,
            ':color' => trim((string)($sourceRoll['color'] ?? '')) !== '' ? trim((string)$sourceRoll['color']) : null,
            ':meters' => isset($sourceRoll['meters']) && $sourceRoll['meters'] !== '' ? (float)$sourceRoll['meters'] : null,
            ':status' => 'RECEIVED',
            ':parent_roll_id' => $sourceRollId > 0 ? $sourceRollId : null,
            ':source_work_order_id' => $workOrderId > 0 ? $workOrderId : null,
            ':process_stage' => 'PRINTED',
        ]);

        $outputRollId = (int)$this->pdo->lastInsertId();
        $this->insertMovement($outputRollId, $warehouseId, [
            'weight_kg' => $weightKg,
            'received_qty' => $receivedQty,
            'reception_mode' => 'QUANTITY',
            'microns' => $sourceRoll['grams'] ?? null,
            'width_mm' => $sourceRoll['width_mm'] ?? null,
            'color' => $sourceRoll['color'] ?? null,
            'meters' => $sourceRoll['meters'] ?? null,
            'operator_name' => $operatorName,
        ]);

        $this->insertEvent('OUTPUT_ROLL_CREATED', [
            'work_order_id' => $workOrderId,
            'roll_id' => $sourceRollId,
            'output_roll_id' => $outputRollId,
            'output_roll_code' => $rollCode,
            'output_roll_weight_kg' => round($weightKg, 3),
            'operator_name' => $operatorName,
        ]);

        return $outputRollId;
    }

    private function insertMovement(int $rollId, int $toWarehouseId, array $input): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload)
             VALUES (:entity_type, :entity_id, :movement_type, :from_warehouse_id, :to_warehouse_id, :payload)'
        );

        $payload = json_encode([
            'weight_kg' => isset($input['weight_kg']) ? (string)$input['weight_kg'] : '0',
            'received_qty' => isset($input['received_qty']) ? (float)$input['received_qty'] : 1.0,
            'reception_mode' => strtoupper(trim((string)($input['reception_mode'] ?? 'QUANTITY'))) === 'WEIGHT' ? 'WEIGHT' : 'QUANTITY',
            'microns' => isset($input['microns']) && $input['microns'] !== '' ? (int)$input['microns'] : null,
            'width_mm' => isset($input['width_mm']) && $input['width_mm'] !== '' ? (int)$input['width_mm'] : null,
            'color' => isset($input['color']) && trim((string)$input['color']) !== '' ? trim((string)$input['color']) : null,
            'meters' => isset($input['meters']) && $input['meters'] !== '' ? (float)$input['meters'] : null,
            'purchase_order_id' => isset($input['purchase_order_id']) ? (int)$input['purchase_order_id'] : null,
            'purchase_order_line_id' => isset($input['purchase_order_line_id']) ? (int)$input['purchase_order_line_id'] : null,
            'import_container_id' => isset($input['import_container_id']) ? (int)$input['import_container_id'] : null,
            'import_container_item_id' => isset($input['import_container_item_id']) ? (int)$input['import_container_item_id'] : null,
            'operator_name' => trim((string)($input['operator_name'] ?? '')),
        ], JSON_UNESCAPED_UNICODE);

        $stmt->execute([
            ':entity_type' => 'ROLL',
            ':entity_id' => $rollId,
            ':movement_type' => 'RECEIPT',
            ':from_warehouse_id' => null,
            ':to_warehouse_id' => $toWarehouseId,
            ':payload' => $payload,
        ]);
    }

    private function generateProcessRollCode(): string
    {
        $date = gmdate('Ymd');
        $rand = bin2hex(random_bytes(3));
        return 'RP-' . $date . '-' . strtoupper($rand);
    }

    private function generateBoxCode(): string
    {
        $date = gmdate('Ymd');
        $rand = bin2hex(random_bytes(3));
        return 'BX-' . $date . '-' . strtoupper($rand);
    }

    private function generatePalletCode(): string
    {
        $date = gmdate('Ymd');
        $rand = bin2hex(random_bytes(3));
        return 'PL-' . $date . '-' . strtoupper($rand);
    }

    public function listWasteInventoryTotals(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT UPPER(TRIM(material_code)) AS material_code,
                    COALESCE(SUM(weight_kg), 0) AS total_kg
             FROM waste_inventory_entries
             GROUP BY UPPER(TRIM(material_code))'
        );
        $stmt->execute();
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[(string)$row['material_code']] = (float)($row['total_kg'] ?? 0);
        }
        $defaults = [
            'PP' => 0.0,
            'PLA' => 0.0,
            'FILM' => 0.0,
        ];
        foreach ($defaults as $code => $zero) {
            if (!isset($rows[$code])) {
                $rows[$code] = $zero;
            }
        }
        return $rows;
    }

    public function listWastePendingInventoryTotals(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT UPPER(TRIM(material_code)) AS material_code,
                    COALESCE(SUM(weight_kg), 0) AS total_kg
             FROM waste_inventory_entries
             WHERE withdrawn_at IS NULL
             GROUP BY UPPER(TRIM(material_code))'
        );
        $stmt->execute();
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[(string)$row['material_code']] = (float)($row['total_kg'] ?? 0);
        }
        $defaults = [
            'PP' => 0.0,
            'PLA' => 0.0,
            'FILM' => 0.0,
        ];
        foreach ($defaults as $code => $zero) {
            if (!isset($rows[$code])) {
                $rows[$code] = $zero;
            }
        }
        return $rows;
    }

    public function listPendingWasteWithdrawalsOfDay(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, shift_session_id, material_code, weight_kg, operator_name, supplier_operator_name, supplier_machine_code, supplier_machine_name, created_at
             FROM waste_inventory_entries
             WHERE withdrawn_at IS NULL
               AND DATE(created_at) = CURDATE()
             ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function recordWasteInventoryEntry(
        string $materialCode,
        float $weightKg,
        string $operatorName,
        ?int $shiftSessionId = null,
        ?string $comments = null,
        ?string $supplierOperatorName = null,
        ?string $supplierMachineCode = null,
        ?string $supplierMachineName = null
    ): array {
        $materialCode = strtoupper(trim($materialCode));
        $weightKg = round(max(0.0, $weightKg), 3);
        $operatorName = trim($operatorName);
        $supplierOperatorName = trim((string)$supplierOperatorName);
        if ($supplierOperatorName === '') {
            $supplierOperatorName = null;
        }
        $supplierMachineCode = trim((string)$supplierMachineCode);
        if ($supplierMachineCode === '') {
            $supplierMachineCode = null;
        }
        $supplierMachineName = trim((string)$supplierMachineName);
        if ($supplierMachineName === '') {
            $supplierMachineName = null;
        }
        $comments = trim((string)$comments);

        $errors = [];
        if (!in_array($materialCode, ['PP', 'PLA', 'FILM'], true)) {
            $errors[] = 'Material inválido. Usa PP, PLA o FILM.';
        }
        if ($weightKg <= 0) {
            $errors[] = 'Debes indicar un peso en kg mayor a 0.';
        }
        if ($operatorName === '') {
            $errors[] = 'El operador es obligatorio.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO waste_inventory_entries (shift_session_id, material_code, weight_kg, operator_name, supplier_operator_name, supplier_machine_code, supplier_machine_name, comments)
             VALUES (:shift_session_id, :material_code, :weight_kg, :operator_name, :supplier_operator_name, :supplier_machine_code, :supplier_machine_name, :comments)'
        );
        $stmt->execute([
            ':shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            ':material_code' => $materialCode,
            ':weight_kg' => $weightKg,
            ':operator_name' => $operatorName,
            ':supplier_operator_name' => $supplierOperatorName,
            ':supplier_machine_code' => $supplierMachineCode,
            ':supplier_machine_name' => $supplierMachineName,
            ':comments' => $comments !== '' ? $comments : null,
        ]);
        $id = (int)$this->pdo->lastInsertId();

        $this->insertEvent('WASTE_INVENTORY_ENTRY_CREATED', [
            'waste_inventory_entry_id' => $id,
            'shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            'material_code' => $materialCode,
            'weight_kg' => $weightKg,
            'operator_name' => $operatorName,
            'supplier_operator_name' => $supplierOperatorName,
            'supplier_machine_code' => $supplierMachineCode,
            'supplier_machine_name' => $supplierMachineName,
            'comments' => $comments !== '' ? $comments : null,
        ]);

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    public function listWasteInventoryRecentEntries(int $limit = 25): array
    {
        $limit = max(1, $limit);
        $stmt = $this->pdo->prepare(
            'SELECT id, shift_session_id, material_code, weight_kg, operator_name, supplier_operator_name, supplier_machine_code, supplier_machine_name, comments, created_at
             FROM waste_inventory_entries
             ORDER BY id DESC
             LIMIT ' . $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listWasteInventoryBySupplierTotals(): array
    {
        $rows = $this->pdo->query(
            'SELECT
                TRIM(COALESCE(supplier_operator_name, "")) AS supplier_operator_name,
                UPPER(TRIM(material_code)) AS material_code,
                COALESCE(SUM(weight_kg), 0) AS total_kg
             FROM waste_inventory_entries
             GROUP BY TRIM(COALESCE(supplier_operator_name, "")), UPPER(TRIM(material_code))
             ORDER BY supplier_operator_name ASC, material_code ASC'
        )->fetchAll();

        $suppliers = [];
        foreach ($rows as $row) {
            $supplier = trim((string)($row['supplier_operator_name'] ?? ''));
            if ($supplier === '') {
                $supplier = 'Sin asignar';
            }
            if (!isset($suppliers[$supplier])) {
                $suppliers[$supplier] = [
                    'supplier_operator_name' => $supplier,
                    'totals' => ['PP' => 0.0, 'PLA' => 0.0, 'FILM' => 0.0],
                    'total_kg' => 0.0,
                ];
            }
            $material = strtoupper(trim((string)($row['material_code'] ?? '')));
            $kg = (float)($row['total_kg'] ?? 0.0);
            if (in_array($material, ['PP', 'PLA', 'FILM'], true)) {
                $suppliers[$supplier]['totals'][$material] = round(($suppliers[$supplier]['totals'][$material] ?? 0.0) + $kg, 3);
            }
            $suppliers[$supplier]['total_kg'] = round(($suppliers[$supplier]['total_kg'] ?? 0.0) + $kg, 3);
        }
        return array_values($suppliers);
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function recordWasteOperation(
        string $operationCode,
        string $operatorName,
        ?int $shiftSessionId = null,
        ?string $comments = null
    ): array {
        $operationCode = strtoupper(trim($operationCode));
        $operatorName = trim($operatorName);
        $comments = trim((string)$comments);

        $allowed = [
            'MOLINO',
            'COMPACTADORA',
            'RETIRO',
            'CRECION_MERMA',
            'CREACION_MERMA',
            'RESPEL',
            'PAUSA_MOLINO',
            'MANTENCION_MOLINO',
            'PAUSA_MOLINO_INICIO',
            'PAUSA_MOLINO_FIN',
            'MANTENCION_MOLINO_INICIO',
            'MANTENCION_MOLINO_FIN',
            'PAUSA_COMPACTADORA',
            'MANTENCION_COMPACTADORA',
            'PAUSA_COMPACTADORA_INICIO',
            'PAUSA_COMPACTADORA_FIN',
            'MANTENCION_COMPACTADORA_INICIO',
            'MANTENCION_COMPACTADORA_FIN',
        ];
        $aliases = [
            'MOLINO' => 'MOLINO',
            'COMPACTADORA' => 'COMPACTADORA',
            'RETIRO' => 'RETIRO',
            'CRECION_MERMA' => 'CREACION_MERMA',
            'CREACION MERMA' => 'CREACION_MERMA',
            'CREACION_MERMA' => 'CREACION_MERMA',
            'RESPEL' => 'RESPEL',
            'PAUSA_MOLINO' => 'PAUSA_MOLINO',
            'MANTENCION_MOLINO' => 'MANTENCION_MOLINO',
            'PAUSA_MOLINO_INICIO' => 'PAUSA_MOLINO_INICIO',
            'PAUSA_MOLINO_FIN' => 'PAUSA_MOLINO_FIN',
            'MANTENCION_MOLINO_INICIO' => 'MANTENCION_MOLINO_INICIO',
            'MANTENCION_MOLINO_FIN' => 'MANTENCION_MOLINO_FIN',
            'PAUSA_COMPACTADORA' => 'PAUSA_COMPACTADORA',
            'MANTENCION_COMPACTADORA' => 'MANTENCION_COMPACTADORA',
            'PAUSA_COMPACTADORA_INICIO' => 'PAUSA_COMPACTADORA_INICIO',
            'PAUSA_COMPACTADORA_FIN' => 'PAUSA_COMPACTADORA_FIN',
            'MANTENCION_COMPACTADORA_INICIO' => 'MANTENCION_COMPACTADORA_INICIO',
            'MANTENCION_COMPACTADORA_FIN' => 'MANTENCION_COMPACTADORA_FIN',
        ];
        $normalized = $aliases[$operationCode] ?? null;
        $errors = [];
        if ($normalized === null || !in_array($normalized, $allowed, true)) {
            $errors[] = 'Operación inválida de gestión de residuos.';
        }
        if ($operatorName === '') {
            $errors[] = 'El operador es obligatorio.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO waste_operations (shift_session_id, operation_code, operator_name, comments)
             VALUES (:shift_session_id, :operation_code, :operator_name, :comments)'
        );
        $stmt->execute([
            ':shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            ':operation_code' => $normalized,
            ':operator_name' => $operatorName,
            ':comments' => $comments !== '' ? $comments : null,
        ]);
        $id = (int)$this->pdo->lastInsertId();

        $this->insertEvent('WASTE_OPERATION_CREATED', [
            'waste_operation_id' => $id,
            'shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            'operation_code' => $normalized,
            'operator_name' => $operatorName,
            'comments' => $comments !== '' ? $comments : null,
        ]);

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    public function listRecentWasteOperations(int $limit = 10): array
    {
        $limit = max(1, $limit);
        $stmt = $this->pdo->prepare(
            'SELECT id, operation_code, material_code, weight_kg, operator_name, supplier_operator_name, supplier_machine_code, supplier_machine_name, comments, created_at, shift_session_id
             FROM waste_operations
             ORDER BY id DESC
             LIMIT ' . $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function markWasteEntriesWithdrawn(array $entryIds, int $withdrawalOperationId, string $operatorName): int
    {
        if ($entryIds === []) {
            return 0;
        }
        $ids = array_values(array_map('intval', $entryIds));
        $idPlaceholders = [];
        foreach (array_keys($ids) as $i) {
            $idPlaceholders[] = ':id' . $i;
        }
        $placeholders = implode(',', $idPlaceholders);
        $stmt = $this->pdo->prepare(
            'UPDATE waste_inventory_entries
             SET withdrawn_at = CURRENT_TIMESTAMP,
                 withdrawn_by_operator = :op,
                 withdrawal_operation_id = :opId
             WHERE id IN (' . $placeholders . ') AND withdrawn_at IS NULL'
        );
        $stmt->bindValue(':op', trim($operatorName));
        $stmt->bindValue(':opId', $withdrawalOperationId, \PDO::PARAM_INT);
        foreach ($ids as $i => $id) {
            $stmt->bindValue(':id' . $i, $id, \PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int)$stmt->rowCount();
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int, affected?:int}
     */
    public function recordWasteWithdrawalOperation(
        array $entryIds,
        string $operatorName,
        ?int $shiftSessionId = null,
        ?string $materialCode = null,
        ?float $weightKg = null,
        ?string $supplierOperatorName = null,
        ?string $supplierMachineCode = null,
        ?string $supplierMachineName = null,
        ?string $comments = null
    ): array {
        $operatorName = trim($operatorName);
        $errors = [];
        if ($operatorName === '') {
            $errors[] = 'El operador es obligatorio para el retiro.';
        }
        if ($entryIds === []) {
            $errors[] = 'No hay sacos seleccionados para retirar.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        $ids = array_values(array_unique(array_map('intval', $entryIds)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT id, material_code, weight_kg, supplier_operator_name, supplier_machine_code, supplier_machine_name
             FROM waste_inventory_entries
             WHERE id IN (' . $placeholders . ') AND withdrawn_at IS NULL
             ORDER BY id ASC'
        );
        foreach ($ids as $i => $id) {
            $stmt->bindValue($i + 1, $id, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $entries = $stmt->fetchAll();
        if ($entries === []) {
            return ['ok' => false, 'errors' => ['Los sacos seleccionados ya fueron retirados o no existen.']];
        }

        $mat = $materialCode !== null ? strtoupper(trim((string)$materialCode)) : null;
        $w = $weightKg !== null ? (float)$weightKg : null;
        $supOp = $supplierOperatorName !== null ? trim((string)$supplierOperatorName) : null;
        $supCode = $supplierMachineCode !== null ? trim((string)$supplierMachineCode) : null;
        $supName = $supplierMachineName !== null ? trim((string)$supplierMachineName) : null;
        if ($mat === null && count($entries) === 1) {
            $mat = strtoupper(trim((string)($entries[0]['material_code'] ?? '')));
        }
        if ($w === null && count($entries) === 1) {
            $w = (float)($entries[0]['weight_kg'] ?? 0);
        }
        if ($w === null) {
            $sum = 0.0;
            foreach ($entries as $e) {
                $sum += (float)($e['weight_kg'] ?? 0);
            }
            $w = round($sum, 3);
        }
        if ($supOp === null && count($entries) === 1) {
            $supOp = trim((string)($entries[0]['supplier_operator_name'] ?? ''));
        }
        if ($supCode === null && count($entries) === 1) {
            $supCode = trim((string)($entries[0]['supplier_machine_code'] ?? ''));
        }
        if ($supName === null && count($entries) === 1) {
            $supName = trim((string)($entries[0]['supplier_machine_name'] ?? ''));
        }

        $this->pdo->beginTransaction();
        try {
            $opStmt = $this->pdo->prepare(
                'INSERT INTO waste_operations (shift_session_id, operation_code, material_code, weight_kg, operator_name, supplier_operator_name, supplier_machine_code, supplier_machine_name, comments)
                 VALUES (:shift_session_id, :operation_code, :material_code, :weight_kg, :operator_name, :supplier_operator_name, :supplier_machine_code, :supplier_machine_name, :comments)'
            );
            $opStmt->execute([
                ':shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
                ':operation_code' => 'RETIRO',
                ':material_code' => ($mat !== null && $mat !== '') ? $mat : null,
                ':weight_kg' => $w,
                ':operator_name' => $operatorName,
                ':supplier_operator_name' => ($supOp !== null && $supOp !== '') ? $supOp : null,
                ':supplier_machine_code' => ($supCode !== null && $supCode !== '') ? $supCode : null,
                ':supplier_machine_name' => ($supName !== null && $supName !== '') ? $supName : null,
                ':comments' => $comments !== null && trim((string)$comments) !== '' ? trim((string)$comments) : null,
            ]);
            $opId = (int)$this->pdo->lastInsertId();

            $affected = $this->markWasteEntriesWithdrawn($ids, $opId, $operatorName);

            $this->insertEvent('WASTE_WITHDRAWAL_CREATED', [
                'waste_operation_id' => $opId,
                'entry_ids' => $ids,
                'material_code' => $mat,
                'weight_kg' => $w,
                'operator_name' => $operatorName,
                'supplier_operator_name' => $supOp,
            ]);

            $this->pdo->commit();
            return ['ok' => true, 'id' => $opId, 'affected' => $affected, 'errors' => []];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['ok' => false, 'errors' => ['No se pudo registrar el retiro: ' . $e->getMessage()]];
        }
    }

    public function listWasteCreationMaterialOptions(): array
    {
        return [
            ['code' => 'PP', 'label' => 'PP'],
            ['code' => 'PLA', 'label' => 'PLA'],
            ['code' => 'FILM', 'label' => 'FILM'],
            ['code' => 'PAPEL', 'label' => 'PAPEL'],
            ['code' => 'CARTON', 'label' => 'CARTÓN'],
            ['code' => 'BOLSA', 'label' => 'BOLSA'],
            ['code' => 'OTRO', 'label' => 'OTRO'],
        ];
    }

    public function listWasteCreationAreaOptions(): array
    {
        return [
            ['code' => 'IMPRESION', 'label' => 'Impresión'],
            ['code' => 'SELLADO', 'label' => 'Sellado'],
            ['code' => 'REBOBINADO', 'label' => 'Rebobinado'],
            ['code' => 'EMBALAJE', 'label' => 'Embalaje'],
            ['code' => 'SERIGRAFIA', 'label' => 'Serigrafía'],
            ['code' => 'PULPO', 'label' => 'Pulpo'],
            ['code' => 'BODEGA', 'label' => 'Bodega'],
            ['code' => 'CALIDAD', 'label' => 'Calidad'],
            ['code' => 'MANTENCION', 'label' => 'Mantención'],
            ['code' => 'OTRO', 'label' => 'Otro'],
        ];
    }

    public function listWasteCreationMotivoOptions(): array
    {
        return [
            ['code' => 'CAMBIO_ORDEN', 'label' => 'Cambio de orden'],
            ['code' => 'ERROR_IMPRESION', 'label' => 'Error de impresión'],
            ['code' => 'ERROR_SELLADO', 'label' => 'Error de sellado'],
            ['code' => 'MATERIAL_DEFECTUOSO', 'label' => 'Material defectuoso'],
            ['code' => 'AJUSTE_APROBACION', 'label' => 'Ajuste / Aprobación'],
            ['code' => 'SCRAP_PRODUCCION', 'label' => 'Scrap de producción'],
            ['code' => 'MUESTRAS', 'label' => 'Muestras'],
            ['code' => 'OBSOLETO', 'label' => 'Obsoleto / Vencido'],
            ['code' => 'DEVOLUCION', 'label' => 'Devolución cliente'],
            ['code' => 'LIMPIEZA', 'label' => 'Limpieza'],
            ['code' => 'REPARACION', 'label' => 'Reparación'],
            ['code' => 'OTRO', 'label' => 'Otro motivo'],
        ];
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function recordWasteCreationOperation(
        string $materialCode,
        float $weightKg,
        string $solicitante,
        string $area,
        string $motivo,
        string $operatorName,
        ?int $shiftSessionId = null
    ): array {
        $errors = [];
        $materialCode = strtoupper(trim($materialCode));
        $weightKg = (float)$weightKg;
        $solicitante = trim($solicitante);
        $area = trim($area);
        $motivo = trim($motivo);
        $operatorName = trim($operatorName);

        $validMaterials = [];
        foreach ($this->listWasteCreationMaterialOptions() as $m) {
            $validMaterials[] = $m['code'];
        }
        $validAreas = [];
        foreach ($this->listWasteCreationAreaOptions() as $a) {
            $validAreas[] = $a['code'];
        }
        $validMotivos = [];
        foreach ($this->listWasteCreationMotivoOptions() as $m) {
            $validMotivos[] = $m['code'];
        }

        if ($materialCode === '' || !in_array($materialCode, $validMaterials, true)) {
            $errors[] = 'Materialidad inválida.';
        }
        if ($weightKg <= 0) {
            $errors[] = 'El peso debe ser mayor a 0.';
        }
        if ($solicitante === '') {
            $errors[] = 'El solicitante es obligatorio.';
        }
        if ($area === '' || !in_array($area, $validAreas, true)) {
            $errors[] = 'Área inválida.';
        }
        if ($motivo === '' || !in_array($motivo, $validMotivos, true)) {
            $errors[] = 'Motivo inválido.';
        }
        if ($operatorName === '') {
            $errors[] = 'El operador es obligatorio.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO waste_operations
                (shift_session_id, operation_code, material_code, weight_kg, operator_name,
                 solicitante, area, motivo, created_at)
            VALUES
                (:shift_session_id, "CREACION_MERMA", :material_code, :weight_kg, :operator_name,
                 :solicitante, :area, :motivo, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            ':material_code' => $materialCode,
            ':weight_kg' => $weightKg,
            ':operator_name' => $operatorName,
            ':solicitante' => $solicitante,
            ':area' => $area,
            ':motivo' => $motivo,
        ]);
        $id = (int)$this->pdo->lastInsertId();

        $this->insertEvent('WASTE_CREATION_RECORDED', [
            'waste_operation_id' => $id,
            'shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            'material_code' => $materialCode,
            'weight_kg' => $weightKg,
            'solicitante' => $solicitante,
            'area' => $area,
            'motivo' => $motivo,
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    /**
     * @return array{id:int,material_code:string,entry_kg:string,operator_name:string,shift_session_id:int|null}|null
     */
    public function getActiveMolinoOperation(string $operatorName, ?int $shiftSessionId = null): ?array
    {
        $sql = 'SELECT id, material_code, entry_kg, operator_name, shift_session_id
                FROM waste_operations
                WHERE operation_code = "MOLINO" AND exit_kg IS NULL AND entry_kg IS NOT NULL ';
        $params = [];
        if ($shiftSessionId > 0) {
            $sql .= ' AND shift_session_id = :shift_session_id';
            $params[':shift_session_id'] = $shiftSessionId;
        } else {
            $sql .= ' AND operator_name = :operator_name AND DATE(created_at) = CURDATE()';
            $params[':operator_name'] = trim($operatorName);
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function recordMolinoEntry(string $materialCode, float $entryKg, string $operatorName, ?int $shiftSessionId = null): array
    {
        $errors = [];
        $materialCode = strtoupper(trim($materialCode));
        $entryKg = (float)$entryKg;
        $operatorName = trim($operatorName);

        if ($materialCode !== 'PLA') {
            $errors[] = 'El molino solo procesa materialidad PLA.';
        }
        if ($entryKg <= 0) {
            $errors[] = 'El peso de ingreso debe ser mayor a 0.';
        }
        $stockPla = (float)($this->listWastePendingInventoryTotals()['PLA'] ?? 0.0);
        if ($entryKg > $stockPla + 0.0001) {
            $errors[] = 'El peso de ingreso supera el stock PLA disponible en bodega transitoria (' . number_format($stockPla, 3, '.', '') . ' kg).';
        }
        if ($operatorName === '') {
            $errors[] = 'El operador es obligatorio.';
        }
        if ($this->getActiveMolinoOperation($operatorName, $shiftSessionId) !== null) {
            $errors[] = 'Ya existe una operación de molino abierta; debe finalizarla antes de iniciar una nueva.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO waste_operations
                (shift_session_id, operation_code, material_code, entry_kg, operator_name, created_at)
            VALUES
                (:shift_session_id, "MOLINO", :material_code, :entry_kg, :operator_name, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            ':material_code' => $materialCode,
            ':entry_kg' => $entryKg,
            ':operator_name' => $operatorName,
        ]);
        $id = (int)$this->pdo->lastInsertId();

        $this->insertEvent('WASTE_MOLINO_ENTRY_RECORDED', [
            'waste_operation_id' => $id,
            'shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            'material_code' => $materialCode,
            'entry_kg' => $entryKg,
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function finalizeMolinoOperation(int $opId, float $exitKg, int $palletCount, string $operatorName): array
    {
        $errors = [];
        $opId = (int)$opId;
        $exitKg = (float)$exitKg;
        $palletCount = (int)$palletCount;
        $operatorName = trim($operatorName);

        if ($opId <= 0) {
            $errors[] = 'Operación de molino inválida.';
        }
        if ($exitKg <= 0) {
            $errors[] = 'El peso de salida debe ser mayor a 0.';
        }
        if ($palletCount < 0) {
            $errors[] = 'La cantidad de palet no puede ser negativa.';
        }
        if ($operatorName === '') {
            $errors[] = 'El operador es obligatorio.';
        }
        $row = null;
        if ($opId > 0) {
            $stmt = $this->pdo->prepare('SELECT id, operation_code, material_code, entry_kg, exit_kg, operator_name FROM waste_operations WHERE id = :id');
            $stmt->execute([':id' => $opId]);
            $row = $stmt->fetch();
            if ($row === false) {
                $errors[] = 'No se encontró la operación de molino.';
            } elseif (($row['operation_code'] ?? '') !== 'MOLINO') {
                $errors[] = 'La operación seleccionada no corresponde a molino.';
            } elseif (($row['exit_kg'] ?? null) !== null) {
                $errors[] = 'Esta operación de molino ya fue finalizada.';
            }
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare('
            UPDATE waste_operations
            SET exit_kg = :exit_kg, pallet_count = :pallet_count
            WHERE id = :id
        ');
        $stmt->execute([
            ':exit_kg' => $exitKg,
            ':pallet_count' => $palletCount > 0 ? $palletCount : null,
            ':id' => $opId,
        ]);

        $this->insertEvent('WASTE_MOLINO_FINALIZED', [
            'waste_operation_id' => $opId,
            'material_code' => (string)($row['material_code'] ?? ''),
            'entry_kg' => (float)($row['entry_kg'] ?? 0.0),
            'exit_kg' => $exitKg,
            'pallet_count' => $palletCount,
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'id' => $opId, 'errors' => []];
    }

    /**
     * Devuelve filas para la tabla de registro producción Molino, estilo legacy-setup-event-table.
     *
     * @return list<array{event:string,start_at:string,end_at:string,duration_label:string,quantity_label:string,comments:string,option_badge_type:string,option_label:string,option_href?:string,option_form_action?:string,option_form_params?:array<string,mixed>}>
     */
    public function listMolinoProductionEvents(string $operatorName, ?int $shiftSessionId = null): array
    {
        $operatorName = trim($operatorName);
        if ($operatorName === '' && !($shiftSessionId > 0)) {
            return [];
        }

        $params = [];
        $where = ' WHERE operation_code = "MOLINO" AND exit_kg IS NULL AND entry_kg IS NOT NULL ';
        if ($shiftSessionId > 0) {
            $where .= ' AND shift_session_id = :shift_session_id';
            $params[':shift_session_id'] = $shiftSessionId;
        } else {
            $where .= ' AND operator_name = :operator_name AND DATE(created_at) = CURDATE()';
            $params[':operator_name'] = $operatorName;
        }

        $stmt = $this->pdo->prepare('
            SELECT id, material_code, entry_kg, operator_name, created_at
            FROM waste_operations
            ' . $where . '
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute($params);
        $molinoOp = $stmt->fetch();
        if ($molinoOp === false) {
            return [];
        }

        $startAt = (string)($molinoOp['created_at'] ?? '');
        $entryVal = (float)($molinoOp['entry_kg'] ?? 0.0);
        $operatorLabel = trim((string)($molinoOp['operator_name'] ?? $operatorName));

        $rows = [];
        $rows[] = [
            'event' => 'Ingreso',
            'start_at' => $startAt !== '' ? $startAt : '-',
            'end_at' => '-',
            'duration_label' => '0h 0m',
            'quantity_label' => number_format($entryVal, 3, '.', '') . ' kg',
            'comments' => 'Ingreso Molino registrado · Operador: ' . ($operatorLabel !== '' ? $operatorLabel : $operatorName),
            'option_badge_type' => 'configured',
            'option_label' => 'Registrado',
        ];

        $rows[] = [
            'event' => 'Producción',
            'start_at' => $startAt !== '' ? $startAt : '-',
            'end_at' => '-',
            'duration_label' => '-',
            'quantity_label' => 'Entrada ' . number_format($entryVal, 3, '.', '') . ' kg',
            'comments' => 'Operador: ' . ($operatorLabel !== '' ? $operatorLabel : $operatorName) . ' · Producción en curso.',
            'option_badge_type' => 'finish-production',
            'option_label' => 'Terminar producción',
            'option_trigger' => 'molino-finalize',
        ];

        $eventsParams = [
            ':started_at' => $startAt,
        ];
        $eventsWhere = ' WHERE created_at >= :started_at AND operation_code IN ("PAUSA_MOLINO_INICIO","PAUSA_MOLINO_FIN","MANTENCION_MOLINO_INICIO","MANTENCION_MOLINO_FIN","PAUSA_MOLINO","MANTENCION_MOLINO") ';
        if ($shiftSessionId > 0) {
            $eventsWhere .= ' AND shift_session_id = :shift_session_id';
            $eventsParams[':shift_session_id'] = $shiftSessionId;
        } else {
            $eventsWhere .= ' AND operator_name = :operator_name AND DATE(created_at) = CURDATE()';
            $eventsParams[':operator_name'] = $operatorName;
        }
        $evtStmt = $this->pdo->prepare('SELECT operation_code, operator_name, created_at, comments FROM waste_operations' . $eventsWhere . ' ORDER BY id ASC');
        $evtStmt->execute($eventsParams);
        $events = $evtStmt->fetchAll();
        $pauseStack = [];
        $maintenanceStack = [];

        foreach ($events as $ev) {
            $code = strtoupper(trim((string)($ev['operation_code'] ?? '')));
            $evtAt = (string)($ev['created_at'] ?? '');
            $comments = trim((string)($ev['comments'] ?? ''));
            $opLabel = trim((string)($ev['operator_name'] ?? $operatorName));

            if ($code === 'PAUSA_MOLINO_INICIO' || $code === 'PAUSA_MOLINO') {
                $pauseStack[] = [
                    'start_at' => $evtAt,
                    'comments' => $comments !== '' ? $comments : ('Operador: ' . ($opLabel !== '' ? $opLabel : $operatorName)),
                ];
                continue;
            }
            if ($code === 'MANTENCION_MOLINO_INICIO' || $code === 'MANTENCION_MOLINO') {
                $maintenanceStack[] = [
                    'start_at' => $evtAt,
                    'comments' => $comments !== '' ? $comments : ('Operador: ' . ($opLabel !== '' ? $opLabel : $operatorName)),
                ];
                continue;
            }

            if ($code === 'PAUSA_MOLINO_FIN') {
                $startRow = array_pop($pauseStack);
                if ($startRow !== null) {
                    $rows[] = [
                        'event' => 'Pausa',
                        'start_at' => $startRow['start_at'] !== '' ? $startRow['start_at'] : '-',
                        'end_at' => $evtAt !== '' ? $evtAt : '-',
                        'duration_label' => $this->formatSimpleElapsedLabel($startRow['start_at'], $evtAt),
                        'quantity_label' => '-',
                        'comments' => (string)$startRow['comments'],
                        'option_badge_type' => 'configured',
                        'option_label' => 'Terminado',
                    ];
                }
                continue;
            }
            if ($code === 'MANTENCION_MOLINO_FIN') {
                $startRow = array_pop($maintenanceStack);
                if ($startRow !== null) {
                    $rows[] = [
                        'event' => 'Mantención',
                        'start_at' => $startRow['start_at'] !== '' ? $startRow['start_at'] : '-',
                        'end_at' => $evtAt !== '' ? $evtAt : '-',
                        'duration_label' => $this->formatSimpleElapsedLabel($startRow['start_at'], $evtAt),
                        'quantity_label' => '-',
                        'comments' => (string)$startRow['comments'],
                        'option_badge_type' => 'configured',
                        'option_label' => 'Terminado',
                    ];
                }
                continue;
            }
        }

        foreach ($pauseStack as $openPause) {
            $rows[] = [
                'event' => 'Pausa',
                'start_at' => (string)($openPause['start_at'] ?? '-'),
                'end_at' => '-',
                'duration_label' => $this->formatSimpleElapsedLabel((string)($openPause['start_at'] ?? ''), null),
                'quantity_label' => '-',
                'comments' => (string)($openPause['comments'] ?? '-'),
                'option_badge_type' => 'finish-event',
                'option_label' => 'Terminar',
                'option_form_action' => '/waste/operations',
                'option_form_params' => ['operation_code' => 'PAUSA_MOLINO_FIN', 'comments' => ''],
            ];
        }
        foreach ($maintenanceStack as $openMaint) {
            $rows[] = [
                'event' => 'Mantención',
                'start_at' => (string)($openMaint['start_at'] ?? '-'),
                'end_at' => '-',
                'duration_label' => $this->formatSimpleElapsedLabel((string)($openMaint['start_at'] ?? ''), null),
                'quantity_label' => '-',
                'comments' => (string)($openMaint['comments'] ?? '-'),
                'option_badge_type' => 'finish-event',
                'option_label' => 'Terminar',
                'option_form_action' => '/waste/operations',
                'option_form_params' => ['operation_code' => 'MANTENCION_MOLINO_FIN', 'comments' => ''],
            ];
        }

        return $rows;
    }

    /**
     * @return array{id:int,material_code:string,entry_kg:string,operator_name:string,shift_session_id:int|null}|null
     */
    public function getActiveCompactadoraOperation(string $operatorName, ?int $shiftSessionId = null): ?array
    {
        $sql = 'SELECT id, material_code, entry_kg, operator_name, shift_session_id
                FROM waste_operations
                WHERE operation_code = "COMPACTADORA" AND exit_kg IS NULL AND entry_kg IS NOT NULL ';
        $params = [];
        if ($shiftSessionId > 0) {
            $sql .= ' AND shift_session_id = :shift_session_id';
            $params[':shift_session_id'] = $shiftSessionId;
        } else {
            $sql .= ' AND operator_name = :operator_name AND DATE(created_at) = CURDATE()';
            $params[':operator_name'] = trim($operatorName);
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function recordCompactadoraEntry(string $materialCode, float $entryKg, string $operatorName, ?int $shiftSessionId = null): array
    {
        $errors = [];
        $materialCode = strtoupper(trim($materialCode));
        $entryKg = (float)$entryKg;
        $operatorName = trim($operatorName);

        if ($materialCode === '') {
            $errors[] = 'La materialidad es obligatoria.';
        }
        if ($entryKg <= 0) {
            $errors[] = 'El peso de ingreso debe ser mayor a 0.';
        }
        $stock = (float)($this->listWastePendingInventoryTotals()[$materialCode] ?? 0.0);
        if ($materialCode !== '' && $entryKg > $stock + 0.0001) {
            $errors[] = 'El peso de ingreso supera el stock disponible (' . number_format($stock, 3, '.', '') . ' kg).';
        }
        if ($operatorName === '') {
            $errors[] = 'El operador es obligatorio.';
        }
        if ($this->getActiveCompactadoraOperation($operatorName, $shiftSessionId) !== null) {
            $errors[] = 'Ya existe una compactadora en proceso; debe finalizarla antes de iniciar una nueva.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO waste_operations
                (shift_session_id, operation_code, material_code, entry_kg, operator_name, created_at)
            VALUES
                (:shift_session_id, "COMPACTADORA", :material_code, :entry_kg, :operator_name, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            ':material_code' => $materialCode,
            ':entry_kg' => $entryKg,
            ':operator_name' => $operatorName,
        ]);
        $id = (int)$this->pdo->lastInsertId();

        $this->insertEvent('WASTE_COMPACTADORA_ENTRY_RECORDED', [
            'waste_operation_id' => $id,
            'shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            'material_code' => $materialCode,
            'entry_kg' => $entryKg,
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function finalizeCompactadoraOperation(int $opId, float $exitKg, string $operatorName): array
    {
        $errors = [];
        $opId = (int)$opId;
        $exitKg = (float)$exitKg;
        $operatorName = trim($operatorName);

        if ($opId <= 0) {
            $errors[] = 'Operación de compactadora inválida.';
        }
        if ($exitKg <= 0) {
            $errors[] = 'El peso de salida debe ser mayor a 0.';
        }
        if ($operatorName === '') {
            $errors[] = 'El operador es obligatorio.';
        }
        $row = null;
        if ($opId > 0) {
            $stmt = $this->pdo->prepare('SELECT id, operation_code, material_code, entry_kg, exit_kg, operator_name FROM waste_operations WHERE id = :id');
            $stmt->execute([':id' => $opId]);
            $row = $stmt->fetch();
            if ($row === false) {
                $errors[] = 'No se encontró la operación de compactadora.';
            } elseif (($row['operation_code'] ?? '') !== 'COMPACTADORA') {
                $errors[] = 'La operación seleccionada no corresponde a compactadora.';
            } elseif (($row['exit_kg'] ?? null) !== null) {
                $errors[] = 'Esta operación de compactadora ya fue finalizada.';
            }
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare('
            UPDATE waste_operations
            SET exit_kg = :exit_kg, pallet_count = NULL
            WHERE id = :id
        ');
        $stmt->execute([
            ':exit_kg' => $exitKg,
            ':id' => $opId,
        ]);

        $this->insertEvent('WASTE_COMPACTADORA_FINALIZED', [
            'waste_operation_id' => $opId,
            'material_code' => (string)($row['material_code'] ?? ''),
            'entry_kg' => (float)($row['entry_kg'] ?? 0.0),
            'exit_kg' => $exitKg,
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'id' => $opId, 'errors' => []];
    }

    /**
     * @return list<array{event:string,start_at:string,end_at:string,duration_label:string,quantity_label:string,comments:string,option_badge_type:string,option_label:string,option_href?:string,option_form_action?:string,option_form_params?:array<string,mixed>}>
     */
    public function listCompactadoraProductionEvents(string $operatorName, ?int $shiftSessionId = null): array
    {
        $operatorName = trim($operatorName);
        if ($operatorName === '' && !($shiftSessionId > 0)) {
            return [];
        }

        $params = [];
        $where = ' WHERE operation_code = "COMPACTADORA" AND exit_kg IS NULL AND entry_kg IS NOT NULL ';
        if ($shiftSessionId > 0) {
            $where .= ' AND shift_session_id = :shift_session_id';
            $params[':shift_session_id'] = $shiftSessionId;
        } else {
            $where .= ' AND operator_name = :operator_name AND DATE(created_at) = CURDATE()';
            $params[':operator_name'] = $operatorName;
        }

        $stmt = $this->pdo->prepare('
            SELECT id, material_code, entry_kg, operator_name, created_at
            FROM waste_operations
            ' . $where . '
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute($params);
        $op = $stmt->fetch();
        if ($op === false) {
            return [];
        }

        $startAt = (string)($op['created_at'] ?? '');
        $entryVal = (float)($op['entry_kg'] ?? 0.0);
        $materialCode = strtoupper(trim((string)($op['material_code'] ?? '')));
        $operatorLabel = trim((string)($op['operator_name'] ?? $operatorName));

        $rows = [];
        $rows[] = [
            'event' => 'Ingreso',
            'start_at' => $startAt !== '' ? $startAt : '-',
            'end_at' => '-',
            'duration_label' => '0h 0m',
            'quantity_label' => $materialCode !== '' ? ($materialCode . ' · ' . number_format($entryVal, 3, '.', '') . ' kg') : (number_format($entryVal, 3, '.', '') . ' kg'),
            'comments' => 'Ingreso Compactadora registrado · Operador: ' . ($operatorLabel !== '' ? $operatorLabel : $operatorName),
            'option_badge_type' => 'configured',
            'option_label' => 'Registrado',
        ];

        $rows[] = [
            'event' => 'Producción',
            'start_at' => $startAt !== '' ? $startAt : '-',
            'end_at' => '-',
            'duration_label' => '-',
            'quantity_label' => ($materialCode !== '' ? ($materialCode . ' · ') : '') . 'Entrada ' . number_format($entryVal, 3, '.', '') . ' kg',
            'comments' => 'Operador: ' . ($operatorLabel !== '' ? $operatorLabel : $operatorName) . ' · Producción en curso.',
            'option_badge_type' => 'finish-production',
            'option_label' => 'Terminar producción',
            'option_trigger' => 'compactadora-finalize',
        ];

        $eventsParams = [
            ':started_at' => $startAt,
        ];
        $eventsWhere = ' WHERE created_at >= :started_at AND operation_code IN ("PAUSA_COMPACTADORA_INICIO","PAUSA_COMPACTADORA_FIN","MANTENCION_COMPACTADORA_INICIO","MANTENCION_COMPACTADORA_FIN","PAUSA_COMPACTADORA","MANTENCION_COMPACTADORA") ';
        if ($shiftSessionId > 0) {
            $eventsWhere .= ' AND shift_session_id = :shift_session_id';
            $eventsParams[':shift_session_id'] = $shiftSessionId;
        } else {
            $eventsWhere .= ' AND operator_name = :operator_name AND DATE(created_at) = CURDATE()';
            $eventsParams[':operator_name'] = $operatorName;
        }
        $evtStmt = $this->pdo->prepare('SELECT operation_code, operator_name, created_at, comments FROM waste_operations' . $eventsWhere . ' ORDER BY id ASC');
        $evtStmt->execute($eventsParams);
        $events = $evtStmt->fetchAll();

        $pauseStack = [];
        $maintenanceStack = [];
        foreach ($events as $ev) {
            $code = strtoupper(trim((string)($ev['operation_code'] ?? '')));
            $evtAt = (string)($ev['created_at'] ?? '');
            $comments = trim((string)($ev['comments'] ?? ''));
            $opLabel = trim((string)($ev['operator_name'] ?? $operatorName));

            if ($code === 'PAUSA_COMPACTADORA_INICIO' || $code === 'PAUSA_COMPACTADORA') {
                $pauseStack[] = [
                    'start_at' => $evtAt,
                    'comments' => $comments !== '' ? $comments : ('Operador: ' . ($opLabel !== '' ? $opLabel : $operatorName)),
                ];
                continue;
            }
            if ($code === 'MANTENCION_COMPACTADORA_INICIO' || $code === 'MANTENCION_COMPACTADORA') {
                $maintenanceStack[] = [
                    'start_at' => $evtAt,
                    'comments' => $comments !== '' ? $comments : ('Operador: ' . ($opLabel !== '' ? $opLabel : $operatorName)),
                ];
                continue;
            }
            if ($code === 'PAUSA_COMPACTADORA_FIN') {
                $startRow = array_pop($pauseStack);
                if ($startRow !== null) {
                    $rows[] = [
                        'event' => 'Pausa',
                        'start_at' => $startRow['start_at'] !== '' ? $startRow['start_at'] : '-',
                        'end_at' => $evtAt !== '' ? $evtAt : '-',
                        'duration_label' => $this->formatSimpleElapsedLabel($startRow['start_at'], $evtAt),
                        'quantity_label' => '-',
                        'comments' => (string)$startRow['comments'],
                        'option_badge_type' => 'configured',
                        'option_label' => 'Terminado',
                    ];
                }
                continue;
            }
            if ($code === 'MANTENCION_COMPACTADORA_FIN') {
                $startRow = array_pop($maintenanceStack);
                if ($startRow !== null) {
                    $rows[] = [
                        'event' => 'Mantención',
                        'start_at' => $startRow['start_at'] !== '' ? $startRow['start_at'] : '-',
                        'end_at' => $evtAt !== '' ? $evtAt : '-',
                        'duration_label' => $this->formatSimpleElapsedLabel($startRow['start_at'], $evtAt),
                        'quantity_label' => '-',
                        'comments' => (string)$startRow['comments'],
                        'option_badge_type' => 'configured',
                        'option_label' => 'Terminado',
                    ];
                }
                continue;
            }
        }

        foreach ($pauseStack as $openPause) {
            $rows[] = [
                'event' => 'Pausa',
                'start_at' => (string)($openPause['start_at'] ?? '-'),
                'end_at' => '-',
                'duration_label' => $this->formatSimpleElapsedLabel((string)($openPause['start_at'] ?? ''), null),
                'quantity_label' => '-',
                'comments' => (string)($openPause['comments'] ?? '-'),
                'option_badge_type' => 'finish-event',
                'option_label' => 'Terminar',
                'option_form_action' => '/waste/operations',
                'option_form_params' => ['operation_code' => 'PAUSA_COMPACTADORA_FIN', 'comments' => ''],
            ];
        }
        foreach ($maintenanceStack as $openMaint) {
            $rows[] = [
                'event' => 'Mantención',
                'start_at' => (string)($openMaint['start_at'] ?? '-'),
                'end_at' => '-',
                'duration_label' => $this->formatSimpleElapsedLabel((string)($openMaint['start_at'] ?? ''), null),
                'quantity_label' => '-',
                'comments' => (string)($openMaint['comments'] ?? '-'),
                'option_badge_type' => 'finish-event',
                'option_label' => 'Terminar',
                'option_form_action' => '/waste/operations',
                'option_form_params' => ['operation_code' => 'MANTENCION_COMPACTADORA_FIN', 'comments' => ''],
            ];
        }

        return $rows;
    }

    private function formatSimpleElapsedLabel(?string $startedAt, ?string $endedAt = null): string
    {
        $startedAt = trim((string)$startedAt);
        $endedAt = $endedAt !== null ? trim((string)$endedAt) : '';
        $startedTs = $startedAt !== '' ? strtotime($startedAt) : false;
        if ($startedTs === false) {
            return '0h 0m';
        }
        $endedTs = $endedAt !== '' ? strtotime($endedAt) : time();
        if ($endedTs === false || $endedTs < $startedTs) {
            $endedTs = $startedTs;
        }
        $diffSeconds = max(0, $endedTs - $startedTs);
        $hours = intdiv($diffSeconds, 3600);
        $minutes = intdiv($diffSeconds % 3600, 60);
        return $hours . 'h ' . $minutes . 'm';
    }

    /**
     * @return list<string>
     */
    public function listBonusCodes(): array
    {
        return [
            'bonoflexo',
            'bonoseri',
            'bonocys',
            'bonopulp',
            'bonoayudante',
        ];
    }

    public function listActiveBonusHelpers(): array
    {
        $stmt = $this->pdo->query(
            'SELECT operator_name
             FROM bonus_helper_roster
             WHERE is_active = 1
             ORDER BY operator_name ASC'
        );
        $rows = $stmt->fetchAll();
        if ($rows === false || $rows === []) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $name = trim((string)($r['operator_name'] ?? ''));
            if ($name !== '') {
                $out[] = $name;
            }
        }
        return $out;
    }

    public function saveActiveBonusHelpers(array $operatorNames): array
    {
        $names = [];
        foreach ($operatorNames as $n) {
            $n = trim((string)$n);
            if ($n === '' || mb_strlen($n) > 120) {
                continue;
            }
            $names[] = $n;
        }
        $names = array_values(array_unique($names));

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('UPDATE bonus_helper_roster SET is_active = 0');
            if ($names !== []) {
                $upsert = $this->pdo->prepare(
                    'INSERT INTO bonus_helper_roster (operator_name, is_active)
                     VALUES (:operator_name, 1)
                     ON DUPLICATE KEY UPDATE is_active = 1'
                );
                foreach ($names as $name) {
                    $upsert->execute([':operator_name' => $name]);
                }
            }
            $this->pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'errors' => ['No se pudo guardar ayudantes.']];
        }
    }

    public function listBonusHelperMonthlyRows(string $monthKey): array
    {
        $monthKey = trim($monthKey);
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            return [];
        }
        $helpers = $this->listActiveBonusHelpers();
        if ($helpers === []) {
            return [];
        }

        $in = [];
        $params = [':month_key' => $monthKey];
        foreach ($helpers as $i => $name) {
            $k = ':op' . $i;
            $in[] = $k;
            $params[$k] = $name;
        }
        $sql = 'SELECT operator_name, proactividad_score, eficiencia_score, multitarea_score,
                       matrix_proactividad_clp, matrix_eficiencia_clp, matrix_multitarea_clp,
                       fixed_clp, additional_clp, observations
                FROM bonus_helper_monthly
                WHERE month_key = :month_key AND operator_name IN (' . implode(',', $in) . ')
                ORDER BY operator_name ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $map = [];
        foreach ($rows as $r) {
            $name = trim((string)($r['operator_name'] ?? ''));
            if ($name !== '') {
                $map[$name] = $r;
            }
        }

        $out = [];
        foreach ($helpers as $name) {
            $r = $map[$name] ?? null;
            $out[] = [
                'operator_name' => $name,
                'proactividad_score' => (int)($r['proactividad_score'] ?? 0),
                'eficiencia_score' => (int)($r['eficiencia_score'] ?? 0),
                'multitarea_score' => (int)($r['multitarea_score'] ?? 0),
                'matrix_proactividad_clp' => (float)($r['matrix_proactividad_clp'] ?? 0.0),
                'matrix_eficiencia_clp' => (float)($r['matrix_eficiencia_clp'] ?? 0.0),
                'matrix_multitarea_clp' => (float)($r['matrix_multitarea_clp'] ?? 0.0),
                'fixed_clp' => (float)($r['fixed_clp'] ?? 0.0),
                'additional_clp' => (float)($r['additional_clp'] ?? 0.0),
                'observations' => (string)($r['observations'] ?? ''),
            ];
        }
        return $out;
    }

    public function saveBonusHelperMonthlyRows(string $monthKey, array $rows): array
    {
        $monthKey = trim($monthKey);
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            return ['ok' => false, 'errors' => ['Mes inválido.']];
        }
        $helpers = $this->listActiveBonusHelpers();
        if ($helpers === []) {
            return ['ok' => false, 'errors' => ['No hay ayudantes seleccionados.']];
        }
        $helperSet = array_fill_keys($helpers, true);

        $this->pdo->beginTransaction();
        try {
            $upsert = $this->pdo->prepare(
                'INSERT INTO bonus_helper_monthly (
                    month_key, operator_name,
                    proactividad_score, eficiencia_score, multitarea_score,
                    matrix_proactividad_clp, matrix_eficiencia_clp, matrix_multitarea_clp,
                    fixed_clp, additional_clp, observations
                 ) VALUES (
                    :month_key, :operator_name,
                    :proactividad_score, :eficiencia_score, :multitarea_score,
                    :matrix_proactividad_clp, :matrix_eficiencia_clp, :matrix_multitarea_clp,
                    :fixed_clp, :additional_clp, :observations
                 )
                 ON DUPLICATE KEY UPDATE
                    proactividad_score = VALUES(proactividad_score),
                    eficiencia_score = VALUES(eficiencia_score),
                    multitarea_score = VALUES(multitarea_score),
                    matrix_proactividad_clp = VALUES(matrix_proactividad_clp),
                    matrix_eficiencia_clp = VALUES(matrix_eficiencia_clp),
                    matrix_multitarea_clp = VALUES(matrix_multitarea_clp),
                    fixed_clp = VALUES(fixed_clp),
                    additional_clp = VALUES(additional_clp),
                    observations = VALUES(observations)'
            );

            foreach ($rows as $r) {
                $name = trim((string)($r['operator_name'] ?? ''));
                if ($name === '' || !isset($helperSet[$name])) {
                    continue;
                }
                $p = max(0, min(10, (int)($r['proactividad_score'] ?? 0)));
                $e = max(0, min(10, (int)($r['eficiencia_score'] ?? 0)));
                $m = max(0, min(10, (int)($r['multitarea_score'] ?? 0)));
                $mp = max(0.0, (float)($r['matrix_proactividad_clp'] ?? 0.0));
                $me = max(0.0, (float)($r['matrix_eficiencia_clp'] ?? 0.0));
                $mm = max(0.0, (float)($r['matrix_multitarea_clp'] ?? 0.0));
                $fixed = max(0.0, (float)($r['fixed_clp'] ?? 0.0));
                $add = max(0.0, (float)($r['additional_clp'] ?? 0.0));
                $obs = trim((string)($r['observations'] ?? ''));
                if (mb_strlen($obs) > 255) {
                    $obs = mb_substr($obs, 0, 255);
                }

                $upsert->execute([
                    ':month_key' => $monthKey,
                    ':operator_name' => $name,
                    ':proactividad_score' => $p,
                    ':eficiencia_score' => $e,
                    ':multitarea_score' => $m,
                    ':matrix_proactividad_clp' => $mp,
                    ':matrix_eficiencia_clp' => $me,
                    ':matrix_multitarea_clp' => $mm,
                    ':fixed_clp' => $fixed,
                    ':additional_clp' => $add,
                    ':observations' => $obs !== '' ? $obs : null,
                ]);
            }

            $this->pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'errors' => ['No se pudo guardar bonificación.']];
        }
    }

    /**
     * Calcula el período estándar de bonificaciones (26 de un mes al 25 del mes siguiente).
     *
     * $monthKey se interpreta como “mes final” del período en formato YYYY-MM.
     * Ejemplo: monthKey=2026-08 => período 2026-07-26 00:00:00 a 2026-08-25 23:59:59.
    /**
     * Resuelve el período o rango de tiempo para bonificaciones:
     * - Si $filterType === 'range' y se proveen $startDate y $endDate válidos (Y-m-d),
     *   calcula el rango personalizado con sus timestamps exactos.
     * - Si es 'period' (o no viene rango), calcula el período 26–25 del mes indicado.
     *
     * @return array{filter_type:string,month_key:string,start_date:string,end_date:string,start_ts:int,end_ts:int,label:string}
     */
    public function resolveBonusFilterPeriod(
        string $filterType = 'period',
        ?string $monthKey = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $tz = new DateTimeZone(date_default_timezone_get());
        $filterType = strtolower(trim($filterType));
        if ($filterType !== 'range') {
            $filterType = 'period';
        }

        if ($filterType === 'range' && $startDate !== null && $endDate !== null) {
            $startDate = trim($startDate);
            $endDate = trim($endDate);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                $startObj = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startDate . ' 00:00:00', $tz);
                $endObj = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endDate . ' 23:59:59', $tz);
                if ($startObj instanceof DateTimeImmutable && $endObj instanceof DateTimeImmutable && $endObj >= $startObj) {
                    $derivedMonth = $endObj->format('Y-m');
                    return [
                        'filter_type' => 'range',
                        'month_key' => ($monthKey && preg_match('/^\d{4}-\d{2}$/', $monthKey)) ? $monthKey : $derivedMonth,
                        'start_date' => $startObj->format('Y-m-d'),
                        'end_date' => $endObj->format('Y-m-d'),
                        'start_ts' => $startObj->getTimestamp(),
                        'end_ts' => $endObj->getTimestamp(),
                        'label' => 'Rango: ' . $startObj->format('d/m/Y') . ' a ' . $endObj->format('d/m/Y'),
                    ];
                }
            }
        }

        // Modo Período (26 al 25)
        $monthKey = trim((string)$monthKey);
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            $today = new DateTimeImmutable('now', $tz);
            $monthKey = ((int)$today->format('j') >= 26)
                ? $today->modify('+1 month')->format('Y-m')
                : $today->format('Y-m');
        }

        $monthStart = new DateTimeImmutable($monthKey . '-01 00:00:00', $tz);
        $previousMonth = $monthStart->modify('-1 month');
        $periodStart = $previousMonth->setDate((int)$previousMonth->format('Y'), (int)$previousMonth->format('m'), 26)->setTime(0, 0, 0);
        $periodEnd = $monthStart->setDate((int)$monthStart->format('Y'), (int)$monthStart->format('m'), 25)->setTime(23, 59, 59);

        return [
            'filter_type' => 'period',
            'month_key' => $monthKey,
            'start_date' => $periodStart->format('Y-m-d'),
            'end_date' => $periodEnd->format('Y-m-d'),
            'start_ts' => $periodStart->getTimestamp(),
            'end_ts' => $periodEnd->getTimestamp(),
            'label' => 'Período 26–25: ' . $periodStart->format('d/m/Y') . ' a ' . $periodEnd->format('d/m/Y'),
        ];
    }

    /**
     * Retorna fechas (Y-m-d) y timestamps (epoch) para usar en consultas ERP/DB local.
     * Soporta tanto string con mes ($monthKey) como array ya resuelto por resolveBonusFilterPeriod.
     *
     * @return array{month_key:string,start_date:string,end_date:string,start_ts:int,end_ts:int,filter_type?:string,label?:string}
     */
    public function getBonusPeriodByMonthFinal(string|array $monthKeyOrPeriod): array
    {
        if (is_array($monthKeyOrPeriod) && isset($monthKeyOrPeriod['start_ts'], $monthKeyOrPeriod['end_ts'])) {
            return $monthKeyOrPeriod;
        }
        return $this->resolveBonusFilterPeriod('period', (string)$monthKeyOrPeriod);
    }

    /**
     * Lista producción de Flexografía desde el ERP para el período 26–25 o rango personalizado.
     *
     * @return array{ok:bool, errors:string[], period:array{month_key:string,start_date:string,end_date:string,start_ts:int,end_ts:int}, rows:list<array<string,mixed>>}
     */
    public function listErpFlexoProductionForBonusPeriod(string|array $monthKey, ?string $operatorName = null, ?string $costCenter = null): array
    {
        $equipotypeIds = $this->resolveErpEquipotypeIdsForBonus('bonoflexo');
        $equipoIds = $equipotypeIds === null ? $this->resolveErpEquipoIdsForBonus('bonoflexo') : null;
        return $this->listErpProductionForBonusPeriod(
            $monthKey,
            $operatorName,
            $costCenter,
            $equipotypeIds,
            $equipoIds,
            null,
            false,
            null
        );
    }

    public function listErpFlexoProductionPreviewForBonusPeriod(string|array $monthKey, ?string $operatorName = null, ?string $costCenter = null, int $limit = 12): array
    {
        $limit = max(1, min(200, $limit));
        $equipotypeIds = $this->resolveErpEquipotypeIdsForBonus('bonoflexo');
        $equipoIds = $equipotypeIds === null ? $this->resolveErpEquipoIdsForBonus('bonoflexo') : null;
        return $this->listErpProductionForBonusPeriod(
            $monthKey,
            $operatorName,
            $costCenter,
            $equipotypeIds,
            $equipoIds,
            $limit,
            false,
            null
        );
    }

    /**
     * Lista producción de Serigrafía desde el ERP para el período 26–25 o rango personalizado.
     *
     * @return array{ok:bool, errors:string[], period:array{month_key:string,start_date:string,end_date:string,start_ts:int,end_ts:int}, rows:list<array<string,mixed>>}
     */
    public function listErpSeriProductionForBonusPeriod(string|array $monthKey, ?string $operatorName = null): array
    {
        $equipotypeIds = $this->resolveErpEquipotypeIdsForBonus('bonoseri');
        $equipoIds = $this->resolveErpEquipoIdsForBonus('bonoseri');
        return $this->listErpProductionForBonusPeriod(
            $monthKey,
            $operatorName,
            null,
            $equipotypeIds,
            $equipoIds,
            null,
            true,
            ['%PULPO%']
        );
    }

    public function listErpSeriProductionPreviewForBonusPeriod(string|array $monthKey, ?string $operatorName = null, int $limit = 12): array
    {
        $limit = max(1, min(200, $limit));
        $equipotypeIds = $this->resolveErpEquipotypeIdsForBonus('bonoseri');
        $equipoIds = $this->resolveErpEquipoIdsForBonus('bonoseri');
        return $this->listErpProductionForBonusPeriod(
            $monthKey,
            $operatorName,
            null,
            $equipotypeIds,
            $equipoIds,
            $limit,
            true,
            ['%PULPO%']
        );
    }

    /**
     * @return list<array{param_equipo_id:int,param_medida:int,param_corte:float,equipo_type_id:int}>
     */
    public function listErpEquipoParams(): array
    {
        if ($this->erpPdo === null) {
            return [];
        }
        try {
            $stmt = $this->erpPdo->query(
                'SELECT ep.param_equipo_id, ep.param_medida, ep.param_corte, eq.equipo_type_id
                 FROM equipo_params ep
                 LEFT JOIN equipo eq ON eq.id = ep.param_equipo_id
                 ORDER BY ep.param_equipo_id ASC, ep.param_medida ASC'
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                return [];
            }
            $out = [];
            foreach ($rows as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $out[] = [
                    'param_equipo_id' => (int)($r['param_equipo_id'] ?? 0),
                    'param_medida' => (int)($r['param_medida'] ?? 0),
                    'param_corte' => (float)($r['param_corte'] ?? 0.0),
                    'equipo_type_id' => (int)($r['equipo_type_id'] ?? 0),
                ];
            }
            return $out;
        } catch (Throwable) {
            return [];
        }
    }


    public function listErpCysProductionForBonusPeriod(string|array $monthKey, ?string $operatorName = null): array
    {
        $equipotypeIds = $this->resolveErpEquipotypeIdsForBonus('bonocys');
        $equipoIds = $equipotypeIds === null ? $this->resolveErpEquipoIdsForBonus('bonocys') : null;
        return $this->listErpProductionForBonusPeriod(
            $monthKey,
            $operatorName,
            null,
            $equipotypeIds,
            $equipoIds,
            null,
            false,
            null
        );
    }

    public function listErpCysProductionPreviewForBonusPeriod(string|array $monthKey, ?string $operatorName = null, int $limit = 12): array
    {
        $limit = max(1, min(200, $limit));
        $equipotypeIds = $this->resolveErpEquipotypeIdsForBonus('bonocys');
        $equipoIds = $equipotypeIds === null ? $this->resolveErpEquipoIdsForBonus('bonocys') : null;
        return $this->listErpProductionForBonusPeriod(
            $monthKey,
            $operatorName,
            null,
            $equipotypeIds,
            $equipoIds,
            $limit,
            false,
            null
        );
    }

    public function listErpCysConfigChangeCountsForBonusPeriod(string|array $monthKey, ?string $operatorName = null): array
    {
        $period = $this->getBonusPeriodByMonthFinal($monthKey);
        $startTs = (int)($period['start_ts'] ?? 0);
        $endTs = (int)($period['end_ts'] ?? 0);
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return ['ok' => false, 'errors' => ['Período inválido.'], 'period' => $period, 'counts' => []];
        }

        if (
            !$this->erpTableExists('prod_worker_ot_events')
            || !$this->erpTableExists('prod_worker_ot')
            || !$this->erpTableExists('prod_agenda')
            || !$this->erpTableExists('prod_worker_init')
            || !$this->erpTableExists('workers')
        ) {
            return ['ok' => true, 'errors' => [], 'period' => $period, 'counts' => []];
        }
        if (
            !$this->erpColumnExists('prod_worker_ot_events', 'evt_prod_worker_otid')
            || !$this->erpColumnExists('prod_worker_ot_events', 'evt_type')
            || !$this->erpColumnExists('prod_worker_ot_events', 'evt_crtdat')
            || !$this->erpColumnExists('prod_worker_ot_events', 'evt_medida_fromid')
            || !$this->erpColumnExists('prod_worker_ot_events', 'evt_medida_toid')
        ) {
            return ['ok' => true, 'errors' => [], 'period' => $period, 'counts' => []];
        }

        $equipotypeIds = $this->resolveErpEquipotypeIdsForBonus('bonocys');
        $equipoIds = $equipotypeIds === null ? $this->resolveErpEquipoIdsForBonus('bonocys') : null;

        $equipotypeFilterSql = '';
        $equipotypeParams = [];
        if (is_array($equipotypeIds) && $equipotypeIds !== []) {
            $equipotypePlaceholders = [];
            foreach (array_values(array_unique(array_map('intval', $equipotypeIds))) as $idx => $id) {
                if ($id <= 0) {
                    continue;
                }
                $ph = ':equipotype_id_' . $idx;
                $equipotypePlaceholders[] = $ph;
                $equipotypeParams[$ph] = $id;
            }
            if ($equipotypePlaceholders !== []) {
                $equipotypeFilterSql = ' AND pa.ag_equipotype_id IN (' . implode(',', $equipotypePlaceholders) . ')';
            }
        }

        $equipoFilterSql = '';
        $equipoParams = [];
        if (is_array($equipoIds) && $equipoIds !== []) {
            $equipoPlaceholders = [];
            foreach (array_values(array_unique(array_map('intval', $equipoIds))) as $idx => $id) {
                if ($id <= 0) {
                    continue;
                }
                $ph = ':equipo_id_' . $idx;
                $equipoPlaceholders[] = $ph;
                $equipoParams[$ph] = $id;
            }
            if ($equipoPlaceholders !== []) {
                $equipoFilterSql = ' AND pa.ag_equipo_id IN (' . implode(',', $equipoPlaceholders) . ')';
            }
        }

        $operatorName = $operatorName !== null ? trim($operatorName) : '';
        $canApplyRule = $this->erpTableExists('prod_medidas')
            && $this->erpTableExists('parametros')
            && $this->erpColumnExists('prod_medidas', 'id')
            && $this->erpColumnExists('prod_medidas', 'med_tipoproducto')
            && $this->erpColumnExists('parametros', 'tabla')
            && $this->erpColumnExists('parametros', 'codigo')
            && $this->erpColumnExists('parametros', 'valor1')
            && $this->erpColumnExists('parametros', 'valor2');
        $countExpr = $this->erpColumnExists('prod_worker_ot_events', 'id') ? 'COUNT(DISTINCT e.id)' : 'COUNT(*)';
        $aplicaJoinSql = '';
        if ($canApplyRule) {
            $countExpr = <<<SQL
SUM(
    CASE
        WHEN COALESCE(p_from.valor2, 0) = 1 AND COALESCE(p_to.valor2, 0) = 1 THEN 1
        WHEN (COALESCE(p_from.valor2, 0) = 0 OR COALESCE(p_to.valor2, 0) = 0) AND COALESCE(p_from.valor1, '') <> COALESCE(p_to.valor1, '') THEN 1
        ELSE 0
    END
)
SQL;
            $aplicaJoinSql = <<<SQL
LEFT JOIN prod_medidas m_from ON m_from.id = e.evt_medida_fromid
LEFT JOIN prod_medidas m_to ON m_to.id = e.evt_medida_toid
LEFT JOIN parametros p_from ON p_from.tabla = 'TIPOPRODUCTO' AND p_from.codigo = CAST(m_from.med_tipoproducto AS CHAR)
LEFT JOIN parametros p_to ON p_to.tabla = 'TIPOPRODUCTO' AND p_to.codigo = CAST(m_to.med_tipoproducto AS CHAR)
SQL;
        }
        $operatorExpr = 'TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, "")))';

        $sql = <<<SQL
SELECT
    {$operatorExpr} AS operator_name,
    {$countExpr} AS cnt
FROM prod_worker_ot_events e
INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
LEFT JOIN workers w ON w.id = pwi.win_wrkid
{$aplicaJoinSql}
WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
  AND LOWER(e.evt_type) = 'apertura'
  AND e.evt_medida_fromid IS NOT NULL
  AND e.evt_medida_toid IS NOT NULL
  AND e.evt_medida_fromid <> e.evt_medida_toid
{$equipotypeFilterSql}
{$equipoFilterSql}
  AND (:operator_name = '' OR {$operatorExpr} = :operator_name_exact)
GROUP BY {$operatorExpr}
ORDER BY operator_name ASC
SQL;

        $counts = [];
        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute(array_merge([
                ':start_ts' => $startTs,
                ':end_ts' => $endTs,
                ':operator_name' => $operatorName,
                ':operator_name_exact' => $operatorName,
            ], $equipotypeParams, $equipoParams));
            foreach ($stmt->fetchAll() as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $name = trim((string)($r['operator_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $counts[$name] = (int)($r['cnt'] ?? 0);
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'errors' => ['No se pudo consultar cambios de configuración.'], 'period' => $period, 'counts' => []];
        }

        return ['ok' => true, 'errors' => [], 'period' => $period, 'counts' => $counts];
    }

    public function listErpCysPackagingUnitsForBonusPeriod(string|array $monthKey, ?string $operatorName = null): array
    {
        $rowsRes = $this->listErpCysPackagingRowsForBonusPeriod($monthKey, $operatorName);
        if (($rowsRes['ok'] ?? false) !== true) {
            return ['ok' => false, 'errors' => $rowsRes['errors'] ?? ['No se pudo consultar embalaje.'], 'period' => $rowsRes['period'] ?? [], 'units' => []];
        }

        $units = [];
        foreach ((array)($rowsRes['rows'] ?? []) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $name = trim((string)($r['operator_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $u = (float)($r['produced_units'] ?? 0.0);
            if ($u <= 0) {
                continue;
            }
            $units[$name] = (float)($units[$name] ?? 0.0) + $u;
        }

        return ['ok' => true, 'errors' => [], 'period' => $rowsRes['period'] ?? [], 'units' => $units, 'rows' => $rowsRes['rows'] ?? []];
    }

    /**
     * Lista filas detalladas de producción de Embalaje desde el ERP para el período de bono.
     *
     * @return array{ok:bool, errors:string[], period:array<string,mixed>, rows:list<array<string,mixed>>}
     */
    public function listErpCysPackagingRowsForBonusPeriod(string|array $monthKey, ?string $operatorName = null): array
    {
        $period = $this->getBonusPeriodByMonthFinal($monthKey);
        $startTs = (int)($period['start_ts'] ?? 0);
        $endTs = (int)($period['end_ts'] ?? 0);
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return ['ok' => false, 'errors' => ['Período inválido.'], 'period' => $period, 'rows' => []];
        }

        $equipotypeIds = $this->resolveErpPackagingEquipotypeIds();
        if ($equipotypeIds === null || $equipotypeIds === []) {
            return ['ok' => true, 'errors' => [], 'period' => $period, 'rows' => []];
        }

        $result = $this->listErpProductionForBonusPeriod($monthKey, $operatorName, null, $equipotypeIds, null, null, false, null);
        if (($result['ok'] ?? false) !== true) {
            return ['ok' => false, 'errors' => $result['errors'] ?? ['No se pudo consultar embalaje.'], 'period' => $period, 'rows' => []];
        }

        return ['ok' => true, 'errors' => [], 'period' => $period, 'rows' => (array)($result['rows'] ?? [])];
    }

    private function resolveErpPackagingEquipotypeIds(): ?array
    {
        if (array_key_exists('packaging', $this->erpEquipotypeIdsByFeatureCache)) {
            return $this->erpEquipotypeIdsByFeatureCache['packaging'];
        }

        $ids = [];
        try {
            if ($this->erpTableExists('equipo_type')) {
                $titleCol = null;
                if ($this->erpColumnExists('equipo_type', 'type_ant_title')) {
                    $titleCol = 'type_ant_title';
                } elseif ($this->erpColumnExists('equipo_type', 'title')) {
                    $titleCol = 'title';
                }
                if ($titleCol !== null) {
                    $sql = 'SELECT id FROM equipo_type WHERE UPPER(' . $titleCol . ') LIKE :p1 OR UPPER(' . $titleCol . ') LIKE :p2 OR UPPER(' . $titleCol . ') LIKE :p3 ORDER BY id';
                    $stmt = $this->erpPdo->prepare($sql);
                    $stmt->execute([
                        ':p1' => '%EMBAL%',
                        ':p2' => '%EMBALA%',
                        ':p3' => '%PACK%',
                    ]);
                    foreach ($stmt->fetchAll() as $r) {
                        if (!is_array($r)) {
                            continue;
                        }
                        $id = (int)($r['id'] ?? 0);
                        if ($id > 0) {
                            $ids[] = $id;
                        }
                    }
                }
            }
        } catch (Throwable) {
            $ids = [];
        }

        $ids = array_values(array_unique(array_values(array_filter($ids, static fn ($v) => (int)$v > 0))));
        sort($ids);
        if ($ids === []) {
            try {
                if ($this->erpTableExists('equipo_type')) {
                    $stmt = $this->erpPdo->prepare('SELECT COUNT(*) FROM equipo_type WHERE id = 15');
                    $stmt->execute();
                    $exists = (int)($stmt->fetchColumn() ?: 0) > 0;
                    if ($exists) {
                        $ids = [15];
                    }
                }
            } catch (Throwable) {
                $ids = [];
            }
        }

        $this->erpEquipotypeIdsByFeatureCache['packaging'] = $ids !== [] ? $ids : null;
        return $this->erpEquipotypeIdsByFeatureCache['packaging'];
    }


    public function getErpEquipotypeIdsForBonusCode(string $bonusCode): ?array
    {
        return $this->resolveErpEquipotypeIdsForBonus($bonusCode);
    }

    public function getErpEquipoIdsForBonusCode(string $bonusCode): ?array
    {
        return $this->resolveErpEquipoIdsForBonus($bonusCode);
    }

    private function resolveErpEquipoIdsForBonus(string $bonusCode): ?array
    {
        $bonusCode = strtolower(trim($bonusCode));
        if ($bonusCode === '') {
            return null;
        }
        if (array_key_exists($bonusCode, $this->erpEquipoIdsByBonusCache)) {
            return $this->erpEquipoIdsByBonusCache[$bonusCode];
        }
        if (!$this->erpTableExists('equipo')) {
            $this->erpEquipoIdsByBonusCache[$bonusCode] = null;
            return null;
        }

        $patterns = [];
        if ($bonusCode === 'bonoflexo') {
            $patterns = ['%FLEXO%'];
        } elseif ($bonusCode === 'bonoseri') {
            $patterns = ['%SERIG%','%SERI%'];
        } elseif ($bonusCode === 'bonocys') {
            $patterns = ['%SELLAD%'];
        } else {
            $this->erpEquipoIdsByBonusCache[$bonusCode] = null;
            return null;
        }

        $ids = [];
        try {
            $hasEquipoType = $this->erpTableExists('equipo_type');
            $equipoNameCol = null;
            if ($this->erpColumnExists('equipo', 'equipo_name')) {
                $equipoNameCol = 'equipo_name';
            } elseif ($this->erpColumnExists('equipo', 'name')) {
                $equipoNameCol = 'name';
            }
            $equipoTypeTitleCol = null;
            if ($hasEquipoType) {
                if ($this->erpColumnExists('equipo_type', 'type_ant_title')) {
                    $equipoTypeTitleCol = 'type_ant_title';
                } elseif ($this->erpColumnExists('equipo_type', 'title')) {
                    $equipoTypeTitleCol = 'title';
                }
            }

            $whereParts = [];
            $params = [];
            foreach ($patterns as $idx => $p) {
                if ($equipoNameCol !== null) {
                    $ph = ':p' . $idx . '_e';
                    $whereParts[] = 'UPPER(e.' . $equipoNameCol . ') LIKE ' . $ph;
                    $params[$ph] = $p;
                }
                if ($equipoTypeTitleCol !== null) {
                    $ph = ':p' . $idx . '_t';
                    $whereParts[] = 'UPPER(et.' . $equipoTypeTitleCol . ') LIKE ' . $ph;
                    $params[$ph] = $p;
                }
            }
            $where = $whereParts !== [] ? ('(' . implode(' OR ', $whereParts) . ')') : '1=0';

            $sql = 'SELECT e.id FROM equipo e';
            if ($hasEquipoType) {
                $sql .= ' LEFT JOIN equipo_type et ON et.id = e.equipo_type_id';
            }
            $sql .= ' WHERE ' . $where;
            if ($bonusCode === 'bonoseri') {
                $excludeParts = [];
                if ($equipoNameCol !== null) {
                    $excludeParts[] = 'UPPER(e.' . $equipoNameCol . ') LIKE :exclude_pulpo_e';
                    $params[':exclude_pulpo_e'] = '%PULPO%';
                }
                if ($equipoTypeTitleCol !== null) {
                    $excludeParts[] = 'UPPER(et.' . $equipoTypeTitleCol . ') LIKE :exclude_pulpo_t';
                    $params[':exclude_pulpo_t'] = '%PULPO%';
                }
                if ($excludeParts !== []) {
                    $sql .= ' AND NOT (' . implode(' OR ', $excludeParts) . ')';
                }
            }
            $sql .= ' ORDER BY e.id';

            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $id = (int)($r['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        } catch (Throwable) {
            $ids = [];
        }

        $ids = array_values(array_unique(array_values(array_filter($ids, static fn ($v) => (int)$v > 0))));
        sort($ids);
        $this->erpEquipoIdsByBonusCache[$bonusCode] = $ids !== [] ? $ids : null;
        return $this->erpEquipoIdsByBonusCache[$bonusCode];
    }

    private function resolveErpEquipotypeIdsForBonus(string $bonusCode): ?array
    {
        $bonusCode = strtolower(trim($bonusCode));
        if ($bonusCode === '') {
            return null;
        }
        if (array_key_exists($bonusCode, $this->erpEquipotypeIdsByBonusCache)) {
            return $this->erpEquipotypeIdsByBonusCache[$bonusCode];
        }

        $ids = [];
        try {
            if (!$this->erpTableExists('equipo_type')) {
                $this->erpEquipotypeIdsByBonusCache[$bonusCode] = null;
                return null;
            }

            $titleCol = null;
            if ($this->erpColumnExists('equipo_type', 'type_ant_title')) {
                $titleCol = 'type_ant_title';
            } elseif ($this->erpColumnExists('equipo_type', 'title')) {
                $titleCol = 'title';
            }

            $hasFlexoAct = $this->erpColumnExists('equipo_type', 'type_ant_flexo_act');
            $hasSeriAct = $this->erpColumnExists('equipo_type', 'type_ant_seri_act');

            $whereParts = [];
            $params = [];
            if ($bonusCode === 'bonoflexo') {
                if ($titleCol !== null) {
                    $whereParts[] = 'UPPER(' . $titleCol . ') LIKE :title_like_1';
                    $whereParts[] = 'UPPER(' . $titleCol . ') LIKE :title_like_2';
                    $params[':title_like_1'] = '%FLEXO%';
                    $params[':title_like_2'] = '%FLEXOG%';
                } elseif ($hasFlexoAct) {
                    $whereParts[] = 'type_ant_flexo_act = 1';
                }
            } elseif ($bonusCode === 'bonoseri') {
                if ($titleCol !== null) {
                    $whereParts[] = 'UPPER(' . $titleCol . ') LIKE :title_like_1';
                    $whereParts[] = 'UPPER(' . $titleCol . ') LIKE :title_like_2';
                    $params[':title_like_1'] = '%SERIG%';
                    $params[':title_like_2'] = '%SERI%';
                } elseif ($hasSeriAct) {
                    $whereParts[] = 'type_ant_seri_act = 1';
                }
            } elseif ($bonusCode === 'bonocys') {
                if ($titleCol !== null) {
                    $whereParts[] = 'UPPER(' . $titleCol . ') LIKE :title_like_1';
                    $params[':title_like_1'] = '%SELLAD%';
                }
            } else {
                $this->erpEquipotypeIdsByBonusCache[$bonusCode] = null;
                return null;
            }

            if ($whereParts === []) {
                $this->erpEquipotypeIdsByBonusCache[$bonusCode] = null;
                return null;
            }

            $sql = 'SELECT id FROM equipo_type WHERE (' . implode(' OR ', $whereParts) . ')';
            if ($bonusCode === 'bonoseri' && $titleCol !== null) {
                $sql .= ' AND UPPER(' . $titleCol . ') NOT LIKE :exclude_pulpo_1';
                $params[':exclude_pulpo_1'] = '%PULPO%';
            }
            $sql .= ' ORDER BY id';
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $id = (int)($r['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        } catch (Throwable) {
            $ids = [];
        }

        $ids = array_values(array_unique(array_values(array_filter($ids, static fn ($v) => (int)$v > 0))));
        sort($ids);
        $this->erpEquipotypeIdsByBonusCache[$bonusCode] = $ids !== [] ? $ids : null;
        return $this->erpEquipotypeIdsByBonusCache[$bonusCode];
    }

    public function listErpBonusOperatorNamesForPeriod(string|array $monthKey, ?array $equipotypeIds, ?array $equipoIds = null): array
    {
        $period = $this->getBonusPeriodByMonthFinal($monthKey);
        $startTs = (int)($period['start_ts'] ?? 0);
        $endTs = (int)($period['end_ts'] ?? 0);
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return ['ok' => false, 'errors' => ['Período inválido.'], 'period' => $period, 'names' => []];
        }

        $equipotypeIds = is_array($equipotypeIds) ? array_values(array_filter(array_map('intval', $equipotypeIds), static fn ($v) => $v > 0)) : null;
        $equipotypeIds = $equipotypeIds !== null ? array_values(array_unique($equipotypeIds)) : null;
        $equipotypeFilterSql = '';
        $equipotypeParams = [];
        if ($equipotypeIds !== null && $equipotypeIds !== []) {
            $equipotypePlaceholders = [];
            foreach ($equipotypeIds as $idx => $id) {
                $ph = ':equipotype_id_' . $idx;
                $equipotypePlaceholders[] = $ph;
                $equipotypeParams[$ph] = $id;
            }
            $equipotypeFilterSql = ' AND pa.ag_equipotype_id IN (' . implode(',', $equipotypePlaceholders) . ')';
        }

        $equipoIds = is_array($equipoIds) ? array_values(array_filter(array_map('intval', $equipoIds), static fn ($v) => $v > 0)) : null;
        $equipoIds = $equipoIds !== null ? array_values(array_unique($equipoIds)) : null;
        $equipoFilterSql = '';
        $equipoParams = [];
        if ($equipoIds !== null && $equipoIds !== []) {
            $equipoPlaceholders = [];
            foreach ($equipoIds as $idx => $id) {
                $ph = ':equipo_id_' . $idx;
                $equipoPlaceholders[] = $ph;
                $equipoParams[$ph] = $id;
            }
            $equipoFilterSql = ' AND pa.ag_equipo_id IN (' . implode(',', $equipoPlaceholders) . ')';
        }

        #region debug-point bonus-query-empty-operators
        $debugEnvPath = __DIR__ . '/../.dbg/bonus-query-empty.env';
        $debugUrl = '';
        if (is_file($debugEnvPath)) {
            $envRaw = (string)@file_get_contents($debugEnvPath);
            if ($envRaw !== '') {
                foreach (preg_split('/\r?\n/', $envRaw) ?: [] as $line) {
                    $line = trim((string)$line);
                    if ($line === '' || !str_contains($line, '=')) {
                        continue;
                    }
                    [$k, $v] = array_map('trim', explode('=', $line, 2));
                    if ($k === 'DEBUG_SERVER_URL') {
                        $debugUrl = $v;
                        break;
                    }
                }
            }
        }
        $dbgPost = static function (string $url, array $payload): void {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if (!is_string($body)) {
                return;
            }
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $body,
                    'timeout' => 2,
                ],
            ]);
            @file_get_contents($url, false, $ctx);
        };
        if ($debugUrl !== '') {
            $dbName = '';
            try {
                $dbName = (string)($this->erpPdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
            } catch (Throwable) {
                $dbName = '';
            }
            $dbgPost($debugUrl, [
                'ts' => date('c'),
                'sessionId' => 'bonus-query-empty',
                'runId' => 'pre',
                'event' => 'bonus_operators_query_start',
                'monthKey' => $monthKey,
                'db' => $dbName,
                'startTs' => $startTs,
                'endTs' => $endTs,
                'equipotypeIds' => $equipotypeIds,
            ]);
        }
        #endregion debug-point bonus-query-empty-operators

        $sql = <<<SQL
SELECT DISTINCT
    TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))) AS operator_name
FROM prod_worker_ot_events e
INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
LEFT JOIN workers w ON w.id = pwi.win_wrkid
WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
{$equipotypeFilterSql}
{$equipoFilterSql}
  AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
ORDER BY operator_name ASC
SQL;

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute(array_merge([
                ':start_ts' => $startTs,
                ':end_ts' => $endTs,
            ], $equipotypeParams, $equipoParams));
            $names = [];
            foreach ($stmt->fetchAll() as $r) {
                $name = is_array($r) ? trim((string)($r['operator_name'] ?? '')) : '';
                if ($name !== '') {
                    $names[] = $name;
                }
            }
            $names = array_values(array_unique($names));
            sort($names, SORT_NATURAL | SORT_FLAG_CASE);
            #region debug-point bonus-query-empty-operators
            if ($debugUrl !== '') {
                $dbgPost($debugUrl, [
                    'ts' => date('c'),
                    'sessionId' => 'bonus-query-empty',
                    'runId' => 'pre',
                    'event' => 'bonus_operators_query_done',
                    'monthKey' => $monthKey,
                    'startTs' => $startTs,
                    'endTs' => $endTs,
                    'equipotypeIds' => $equipotypeIds,
                    'namesCount' => count($names),
                    'namesSample' => array_slice($names, 0, 12),
                ]);
            }
            #endregion debug-point bonus-query-empty-operators

            return ['ok' => true, 'errors' => [], 'period' => $period, 'names' => $names];
        } catch (PDOException $e) {
            $sqlState = (string)($e->errorInfo[0] ?? $e->getCode() ?? '');
            if ($sqlState === '42S02' || str_contains($e->getMessage(), 'Base table or view not found')) {
                #region debug-point bonus-query-empty-operators
                if ($debugUrl !== '') {
                    $dbgPost($debugUrl, [
                        'ts' => date('c'),
                        'sessionId' => 'bonus-query-empty',
                        'runId' => 'pre',
                        'event' => 'bonus_operators_query_error',
                        'monthKey' => $monthKey,
                        'sqlState' => $sqlState,
                        'error' => $e->getMessage(),
                    ]);
                }
                #endregion debug-point bonus-query-empty-operators
                return ['ok' => false, 'errors' => ['No se encuentran las tablas de producción del ERP (prod_*).'], 'period' => $period, 'names' => []];
            }
            #region debug-point bonus-query-empty-operators
            if ($debugUrl !== '') {
                $dbgPost($debugUrl, [
                    'ts' => date('c'),
                    'sessionId' => 'bonus-query-empty',
                    'runId' => 'pre',
                    'event' => 'bonus_operators_query_error',
                    'monthKey' => $monthKey,
                    'sqlState' => $sqlState,
                    'error' => $e->getMessage(),
                ]);
            }
            #endregion debug-point bonus-query-empty-operators
            return ['ok' => false, 'errors' => ['No se pudo consultar operadores del ERP.'], 'period' => $period, 'names' => []];
        }
    }

    /**
     * Implementación genérica de consulta ERP para producción en el período 26–25.
     *
     * @param int|null $equipotypeId Si viene, filtra por prod_agenda.ag_equipotype_id
     * @return array{ok:bool, errors:string[], period:array{month_key:string,start_date:string,end_date:string,start_ts:int,end_ts:int}, rows:list<array<string,mixed>>}
     */
    private function listErpProductionForBonusPeriod(string|array $monthKey, ?string $operatorName, ?string $costCenter, ?array $equipotypeIds, ?array $equipoIds, ?int $limit, bool $filterEquipoOnWorker = false, ?array $excludeWorkerEquipoNameLike = null): array
    {
        $period = $this->getBonusPeriodByMonthFinal($monthKey);
        $startTs = (int)($period['start_ts'] ?? 0);
        $endTs = (int)($period['end_ts'] ?? 0);
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return ['ok' => false, 'errors' => ['Período inválido.'], 'period' => $period, 'rows' => []];
        }

        $operatorName = $operatorName !== null ? trim($operatorName) : '';
        $costCenter = $costCenter !== null ? trim($costCenter) : '';
        $equipotypeIds = is_array($equipotypeIds) ? array_values(array_filter(array_map('intval', $equipotypeIds), static fn ($v) => $v > 0)) : null;
        $equipotypeIds = $equipotypeIds !== null ? array_values(array_unique($equipotypeIds)) : null;
        $equipoIds = is_array($equipoIds) ? array_values(array_filter(array_map('intval', $equipoIds), static fn ($v) => $v > 0)) : null;
        $equipoIds = $equipoIds !== null ? array_values(array_unique($equipoIds)) : null;
        $limit = $limit !== null ? max(1, min(20000, (int)$limit)) : null;
        $limitSql = $limit !== null ? (' LIMIT ' . $limit) : '';

        $equipotypeFilterSql = '';
        $equipotypeParams = [];
        if ($equipotypeIds !== null && $equipotypeIds !== []) {
            $equipotypePlaceholders = [];
            foreach ($equipotypeIds as $idx => $id) {
                $ph = ':equipotype_id_' . $idx;
                $equipotypePlaceholders[] = $ph;
                $equipotypeParams[$ph] = $id;
            }
            $equipotypeFilterSql = $filterEquipoOnWorker
                ? (' AND eq.equipo_type_id IN (' . implode(',', $equipotypePlaceholders) . ')')
                : (' AND pa.ag_equipotype_id IN (' . implode(',', $equipotypePlaceholders) . ')');
        }

        $equipoFilterSql = '';
        $equipoParams = [];
        if ($equipoIds !== null && $equipoIds !== []) {
            $equipoPlaceholders = [];
            foreach ($equipoIds as $idx => $id) {
                $ph = ':equipo_id_' . $idx;
                $equipoPlaceholders[] = $ph;
                $equipoParams[$ph] = $id;
            }
            $equipoFilterSql = $filterEquipoOnWorker
                ? (' AND pwi.win_equipoid IN (' . implode(',', $equipoPlaceholders) . ')')
                : (' AND pa.ag_equipo_id IN (' . implode(',', $equipoPlaceholders) . ')');
        }

        $agendaEquipoJoinSql = '';
        $agendaEquipoExcludeSql = '';
        $agendaEquipoExcludeParams = [];
        $excludeWorkerEquipoNameLike = is_array($excludeWorkerEquipoNameLike)
            ? array_values(array_filter(array_map('strval', $excludeWorkerEquipoNameLike), static fn ($v) => trim($v) !== ''))
            : null;
        if ($excludeWorkerEquipoNameLike !== null && $excludeWorkerEquipoNameLike !== [] && $this->erpTableExists('equipo')) {
            $agendaEquipoNameCol = null;
            if ($this->erpColumnExists('equipo', 'equipo_name')) {
                $agendaEquipoNameCol = 'equipo_name';
            } elseif ($this->erpColumnExists('equipo', 'name')) {
                $agendaEquipoNameCol = 'name';
            }
            if ($agendaEquipoNameCol !== null) {
                $agendaEquipoJoinSql = 'LEFT JOIN equipo ag_eq ON ag_eq.id = pwi.win_equipoid';
                $excludeParts = [];
                foreach ($excludeWorkerEquipoNameLike as $idx => $pat) {
                    $ph = ':agenda_equipo_excl_' . $idx;
                    $excludeParts[] = 'UPPER(ag_eq.' . $agendaEquipoNameCol . ') LIKE ' . $ph;
                    $agendaEquipoExcludeParams[$ph] = strtoupper($pat);
                }
                if ($excludeParts !== []) {
                    $agendaEquipoExcludeSql = ' AND NOT (' . implode(' OR ', $excludeParts) . ')';
                }
            }
        }

        $defectJoinSql = '';
        $defectSelectSqlLegacy = '0 AS declared_waste_units, 0 AS declared_waste_kg, 0 AS waste_print_units, 0 AS waste_print_kg';
        $defectSelectSqlExtended = '0 AS declared_waste_units, 0 AS declared_waste_kg, 0 AS waste_print_units, 0 AS waste_print_kg';
        if ($this->erpTableExists('prod_worker_ot_defectunits')) {
            $mTypeJoin = $this->erpTableExists('prod_mermatypes') ? 'LEFT JOIN prod_mermatypes mt ON mt.id = d.evt_merma_typeid' : '';
            $defectSelectSqlLegacy = 'SUM(COALESCE(dw.waste_units, 0)) AS declared_waste_units, SUM(COALESCE(dw.waste_kg, 0)) AS declared_waste_kg, SUM(COALESCE(dw.waste_print_units, 0)) AS waste_print_units, SUM(COALESCE(dw.waste_print_kg, 0)) AS waste_print_kg';
            $defectSelectSqlExtended = 'SUM(COALESCE(dw.waste_units, 0)) AS declared_waste_units, SUM(COALESCE(dw.waste_kg, 0)) AS declared_waste_kg, SUM(COALESCE(dw.waste_print_units, 0)) AS waste_print_units, SUM(COALESCE(dw.waste_print_kg, 0)) AS waste_print_kg';
            $defectJoinSql = <<<SQL
LEFT JOIN (
    SELECT 
        e2.evt_prod_worker_otid AS wok_id,
        SUM(CASE WHEN LOWER(d.evt_type) = 'merma' THEN d.evt_kgstounits ELSE 0 END) AS waste_kg,
        SUM(CASE WHEN LOWER(d.evt_type) = 'merma' THEN d.evt_amount ELSE 0 END) AS waste_units,
        SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND (d.evt_merma_typeid = 7 OR LOWER(COALESCE(mt.merma_title, '')) LIKE '%impresi%') THEN d.evt_kgstounits ELSE 0 END) AS waste_print_kg,
        SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND (d.evt_merma_typeid = 7 OR LOWER(COALESCE(mt.merma_title, '')) LIKE '%impresi%') THEN d.evt_amount ELSE 0 END) AS waste_print_units
    FROM prod_worker_ot_defectunits d
    INNER JOIN prod_worker_ot_events e2 ON d.evt_refid = e2.id
    {$mTypeJoin}
    WHERE d.evt_status > 0
    GROUP BY e2.evt_prod_worker_otid
) dw ON dw.wok_id = pwo.id
SQL;
        }

        $bagTypeSelectSqlLegacy = "'' AS bag_type";
        $bagTypeSelectSqlExtended = "'' AS bag_type";
        $bagTypeJoinSql = '';
        if (
            $this->erpTableExists('tran_comments_item_vals')
            && $this->erpTableExists('tran_comments')
            && $this->erpTableExists('tran_comments_vals')
            && $this->erpColumnExists('tran_comments_item_vals', 'item_id')
            && $this->erpColumnExists('tran_comments_item_vals', 'com_id')
            && $this->erpColumnExists('tran_comments_item_vals', 'val_id')
            && $this->erpColumnExists('tran_comments', 'id')
            && $this->erpColumnExists('tran_comments_vals', 'id')
            && $this->erpColumnExists('tran_comments_vals', 'add_name')
        ) {
            $bagTypeSelectSqlExtended = 'MAX(tb.bag_type) AS bag_type';
            $bagTypeJoinSql = <<<SQL
LEFT JOIN (
    SELECT
        tciv.item_id,
        MAX(TRIM(tcv.add_name)) AS bag_type
    FROM tran_comments_item_vals tciv
    INNER JOIN tran_comments tc ON tc.id = tciv.com_id AND tc.id = 20
    INNER JOIN tran_comments_vals tcv ON tcv.id = tciv.val_id
    GROUP BY tciv.item_id
) tb ON tb.item_id = it.id
SQL;
        }

        #region debug-point bonus-query-empty-main
        $debugEnvPath = __DIR__ . '/../.dbg/bonus-query-empty.env';
        $debugUrl = '';
        if (is_file($debugEnvPath)) {
            $envRaw = (string)@file_get_contents($debugEnvPath);
            if ($envRaw !== '') {
                foreach (preg_split('/\r?\n/', $envRaw) ?: [] as $line) {
                    $line = trim((string)$line);
                    if ($line === '' || !str_contains($line, '=')) {
                        continue;
                    }
                    [$k, $v] = array_map('trim', explode('=', $line, 2));
                    if ($k === 'DEBUG_SERVER_URL') {
                        $debugUrl = $v;
                        break;
                    }
                }
            }
        }
        $dbgPost = static function (string $url, array $payload): void {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if (!is_string($body)) {
                return;
            }
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $body,
                    'timeout' => 2,
                ],
            ]);
            @file_get_contents($url, false, $ctx);
        };
        if ($debugUrl !== '') {
            $dbName = '';
            try {
                $dbName = (string)($this->erpPdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
            } catch (Throwable) {
                $dbName = '';
            }
            $dbgPost($debugUrl, [
                'ts' => date('c'),
                'sessionId' => 'bonus-query-empty',
                'runId' => 'pre',
                'event' => 'bonus_rows_query_start',
                'monthKey' => $monthKey,
                'db' => $dbName,
                'startTs' => $startTs,
                'endTs' => $endTs,
                'equipotypeIds' => $equipotypeIds,
                'operatorName' => $operatorName,
                'costCenter' => $costCenter,
                'limit' => $limit,
            ]);
        }
        #endregion debug-point bonus-query-empty-main

        $sqlLegacy = <<<SQL
SELECT
    pa.ag_equipo_id AS printer_no,
    ph.prd_reqid AS cost_center,
    ph.prd_number AS work_order_number,
    e.event_date AS event_date,
    ph.prd_desc AS erp_desc,
    '' AS item_title,
    {$bagTypeSelectSqlLegacy},
    TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))) AS operator_name,
    MAX(pa.ag_amount) AS requested_units,
    MAX(pa.ag_amount) AS requested_units_pa,
    0 AS requested_units_item,
    MAX(e.produced_units) AS produced_units,
    MAX(e.produced_linear_meters) AS produced_linear_meters,
    MAX(e.produced_machine_meters) AS produced_machine_meters,
    0 AS item_prodcalc_fuelle_act,
    MAX(COALESCE(pwi.win_equipoid, pa.ag_equipo_id, 0)) AS win_equipoid,
    {$defectSelectSqlLegacy}
FROM (
    SELECT
        x.evt_prod_worker_otid,
        x.event_date,
        MAX(x.sum_units) AS produced_units,
        MAX(x.sum_linear) AS produced_linear_meters,
        MAX(x.sum_machine) AS produced_machine_meters
    FROM (
        SELECT
            evt_prod_worker_otid,
            DATE(FROM_UNIXTIME(evt_crtdat)) AS event_date,
            LOWER(evt_type) AS evt_type,
            SUM(evt_amount) AS sum_units,
            SUM(evt_amount_metros_lineales) AS sum_linear,
            SUM(evt_amount_metros_maquina) AS sum_machine
        FROM prod_worker_ot_events
        WHERE evt_crtdat BETWEEN :start_ts AND :end_ts
          AND LOWER(evt_type) IN ('production','prod','prodsericolor')
        GROUP BY evt_prod_worker_otid, DATE(FROM_UNIXTIME(evt_crtdat)), LOWER(evt_type)
    ) x
    GROUP BY x.evt_prod_worker_otid, x.event_date
) e
INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
{$agendaEquipoJoinSql}
LEFT JOIN workers w ON w.id = pwi.win_wrkid
{$defectJoinSql}
WHERE 1=1
{$equipotypeFilterSql}
{$equipoFilterSql}
{$agendaEquipoExcludeSql}
  AND (:operator_name = '' OR TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))) = :operator_name_exact)
  AND (:cost_center = '' OR ph.prd_reqid = :cost_center_exact)
GROUP BY
    pa.ag_equipo_id,
    ph.prd_reqid,
    ph.prd_number,
    e.event_date,
    TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))),
    ph.prd_desc
ORDER BY event_date ASC, printer_no ASC, cost_center ASC, work_order_number ASC{$limitSql}
SQL;

        $sqlExtended = <<<SQL
SELECT
    pa.ag_equipo_id AS printer_no,
    COALESCE(o.req_number, ph.prd_reqid) AS cost_center,
    ph.prd_number AS work_order_number,
    e.event_date AS event_date,
    ph.prd_desc AS erp_desc,
    TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))) AS operator_name,
    MAX(CASE WHEN pa.ag_amount IS NOT NULL AND pa.ag_amount > 0 THEN pa.ag_amount ELSE oi.item_amount END) AS requested_units,
    MAX(pa.ag_amount) AS requested_units_pa,
    MAX(oi.item_amount) AS requested_units_item,
    MAX(e.produced_units) AS produced_units,
    MAX(e.produced_linear_meters) AS produced_linear_meters,
    MAX(e.produced_machine_meters) AS produced_machine_meters,
    {$defectSelectSqlExtended},
    MAX(c.cust_name) AS client_label,
    MAX(UPPER(cat.cat_prefix)) AS product_type,
    MAX(it.item_title) AS item_title,
    {$bagTypeSelectSqlExtended},
    MAX(
        CASE
            WHEN oi.fab_med_width IS NULL OR oi.fab_med_height IS NULL THEN ''
            WHEN oi.fab_med_fuelle IS NULL OR oi.fab_med_fuelle = 0 THEN CONCAT(CAST(oi.fab_med_width AS UNSIGNED), 'X', CAST(oi.fab_med_height AS UNSIGNED))
            ELSE CONCAT(CAST(oi.fab_med_width AS UNSIGNED), 'X', CAST(oi.fab_med_height AS UNSIGNED), 'X', CAST(oi.fab_med_fuelle AS UNSIGNED))
        END
    ) AS measure_cm,
    MAX(oi.fab_mat_gramms)      AS grammage_g,
    MAX(oi.fab_med_width)       AS dim_width_cm,
    MAX(oi.fab_med_height)      AS dim_height_cm,
    MAX(oi.fab_med_fuelle)      AS dim_fuelle_cm,
    MAX(oi.fab_manilla_length)  AS dim_manilla_length,
    MAX(it.item_weight)         AS item_weight,
    MAX(it.item_prodcalc_fuelle_act) AS item_prodcalc_fuelle_act,
    MAX(COALESCE(pwi.win_equipoid, pa.ag_equipo_id, 0)) AS win_equipoid
FROM (
    SELECT
        x.evt_prod_worker_otid,
        x.event_date,
        MAX(x.sum_units) AS produced_units,
        MAX(x.sum_linear) AS produced_linear_meters,
        MAX(x.sum_machine) AS produced_machine_meters
    FROM (
        SELECT
            evt_prod_worker_otid,
            DATE(FROM_UNIXTIME(evt_crtdat)) AS event_date,
            LOWER(evt_type) AS evt_type,
            SUM(evt_amount) AS sum_units,
            SUM(evt_amount_metros_lineales) AS sum_linear,
            SUM(evt_amount_metros_maquina) AS sum_machine
        FROM prod_worker_ot_events
        WHERE evt_crtdat BETWEEN :start_ts AND :end_ts
          AND LOWER(evt_type) IN ('production','prod','prodsericolor')
        GROUP BY evt_prod_worker_otid, DATE(FROM_UNIXTIME(evt_crtdat)), LOWER(evt_type)
    ) x
    GROUP BY x.evt_prod_worker_otid, x.event_date
) e
INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
{$agendaEquipoJoinSql}
LEFT JOIN workers w ON w.id = pwi.win_wrkid
{$defectJoinSql}
LEFT JOIN equipo eq ON eq.id = pwi.win_equipoid
LEFT JOIN orders o ON o.id = pa.ag_reqid
LEFT JOIN customer c ON c.id = o.req_cust_id
LEFT JOIN (
    SELECT
        req_id,
        MAX(item_amount)        AS item_amount,
        MAX(fab_med_width)      AS fab_med_width,
        MAX(fab_med_height)     AS fab_med_height,
        MAX(fab_med_fuelle)     AS fab_med_fuelle,
        MAX(item_id)            AS item_id,
        MAX(fab_mat_gramms)     AS fab_mat_gramms,
        MAX(fab_manilla_length) AS fab_manilla_length
    FROM orders_items
    GROUP BY req_id
) oi ON oi.req_id = o.id
LEFT JOIN item it ON it.id = oi.item_id
{$bagTypeJoinSql}
LEFT JOIN (
    SELECT ip.item_id, MIN(pc.cat_prefix) AS cat_prefix
    FROM item_productcats ip
    INNER JOIN productcats pc ON pc.id = ip.cat_id
    GROUP BY ip.item_id
) cat ON cat.item_id = it.id
WHERE 1=1
{$equipotypeFilterSql}
{$equipoFilterSql}
{$agendaEquipoExcludeSql}
  AND (:operator_name = '' OR TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))) = :operator_name_exact)
  AND (:cost_center = '' OR COALESCE(o.req_number, ph.prd_reqid) = :cost_center_exact)
GROUP BY
    pa.ag_equipo_id,
    COALESCE(o.req_number, ph.prd_reqid),
    ph.prd_number,
    e.event_date,
    TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))),
    ph.prd_desc
ORDER BY event_date ASC, printer_no ASC, cost_center ASC, work_order_number ASC{$limitSql}
SQL;

        $queryMode = 'extended';
        $queryFallbackReason = '';
        try {
            $params = [
                ':start_ts' => $startTs,
                ':end_ts' => $endTs,
                ':operator_name' => $operatorName,
                ':operator_name_exact' => $operatorName,
                ':cost_center' => $costCenter,
                ':cost_center_exact' => $costCenter,
            ];
            $params = array_merge($params, $equipotypeParams, $equipoParams, $agendaEquipoExcludeParams);

            try {
                $stmt = $this->erpPdo->prepare($sqlExtended);
                $stmt->execute($params);
                $rawRows = $stmt->fetchAll();
            } catch (PDOException $eExtended) {
                $sqlState = (string)($eExtended->errorInfo[0] ?? $eExtended->getCode() ?? '');
                $message = $eExtended->getMessage();
                if ($sqlState === '42S02' || str_contains($message, 'Base table or view not found')) {
                    $queryMode = 'legacy';
                    $queryFallbackReason = $sqlState !== '' ? ('SQLSTATE ' . $sqlState) : 'missing table';
                    $stmt = $this->erpPdo->prepare($sqlLegacy);
                    $stmt->execute($params);
                    $rawRows = $stmt->fetchAll();
                } else {
                    throw $eExtended;
                }
            }
        } catch (PDOException $e) {
            $sqlState = (string)($e->errorInfo[0] ?? $e->getCode() ?? '');
            $message = $e->getMessage();
            #region debug-point bonus-query-empty-main
            if ($debugUrl !== '') {
                $dbgPost($debugUrl, [
                    'ts' => date('c'),
                    'sessionId' => 'bonus-query-empty',
                    'runId' => 'pre',
                    'event' => 'bonus_rows_query_error',
                    'monthKey' => $monthKey,
                    'sqlState' => $sqlState,
                    'error' => $message,
                    'queryMode' => $queryMode,
                    'fallbackReason' => $queryFallbackReason,
                ]);
            }
            #endregion debug-point bonus-query-empty-main
            if ($sqlState === '42S02' || str_contains($message, 'Base table or view not found')) {
                return ['ok' => false, 'errors' => ['No se encuentran las tablas de producción del ERP (prod_*).'], 'period' => $period, 'rows' => []];
            }
            if ($sqlState !== '') {
                return ['ok' => false, 'errors' => ['No se pudo consultar producción ERP (SQLSTATE ' . $sqlState . ').'], 'period' => $period, 'rows' => []];
            }
            return ['ok' => false, 'errors' => ['No se pudo consultar producción ERP.'], 'period' => $period, 'rows' => []];
        }

        $rows = [];
        foreach ($rawRows as $r) {
            $desc = trim((string)($r['erp_desc'] ?? ''));
            $rows[] = [
                'printer_no' => (string)($r['printer_no'] ?? ''),
                'cost_center' => (string)($r['cost_center'] ?? ''),
                'work_order_number' => (string)($r['work_order_number'] ?? ''),
                'event_date' => (string)($r['event_date'] ?? ''),
                'client_label' => trim((string)($r['client_label'] ?? '')) !== '' ? (string)$r['client_label'] : $this->parseClientLabelFromErpDesc($desc),
                'product_type' => trim((string)($r['product_type'] ?? '')) !== '' ? (string)$r['product_type'] : $this->parseProductTypeFromErpDesc($desc),
                'item_title' => trim((string)($r['item_title'] ?? '')),
                'bag_type' => trim((string)($r['bag_type'] ?? '')),
                'measure_cm' => trim((string)($r['measure_cm'] ?? '')) !== '' ? (string)$r['measure_cm'] : $this->parseMeasureCmFromErpDesc($desc),
                'helper_label' => '',
                'bonification_label' => '',
                'operator_name' => trim((string)($r['operator_name'] ?? '')),
                'requested_units' => (float)($r['requested_units'] ?? 0),
                'requested_units_pa' => (float)($r['requested_units_pa'] ?? 0),
                'requested_units_item' => (float)($r['requested_units_item'] ?? 0),
                'produced_units' => (float)($r['produced_units'] ?? 0),
                'produced_linear_meters' => (float)($r['produced_linear_meters'] ?? 0),
                'produced_machine_meters' => (float)($r['produced_machine_meters'] ?? 0),
                'declared_waste_units'  => (float)($r['declared_waste_units'] ?? 0),
                'declared_waste_kg'    => (float)($r['declared_waste_kg']    ?? 0),
                'waste_print_units'    => (float)($r['waste_print_units']    ?? 0),
                'waste_print_kg'       => (float)($r['waste_print_kg']       ?? 0),
                'grammage_g'           => (float)($r['grammage_g']           ?? 0),
                'dim_width_cm'         => (float)($r['dim_width_cm']         ?? 0),
                'dim_height_cm'        => (float)($r['dim_height_cm']        ?? 0),
                'dim_fuelle_cm'        => (float)($r['dim_fuelle_cm']        ?? 0),
                'dim_manilla_length'   => (float)($r['dim_manilla_length']   ?? 0),
                'item_weight'          => (float)($r['item_weight']          ?? 0),
                'item_prodcalc_fuelle_act' => (int)($r['item_prodcalc_fuelle_act'] ?? 0),
                'win_equipoid'         => (int)($r['win_equipoid']         ?? 0),
                'erp_desc' => $desc,
            ];
        }

        #region debug-point bonus-query-empty-main
        if ($debugUrl !== '') {
            $evtTypeCounts = [];
            try {
                $stmt = $this->erpPdo->prepare(
                    'SELECT evt_type, COUNT(*) AS cnt
                     FROM prod_worker_ot_events
                     WHERE evt_crtdat BETWEEN :start_ts AND :end_ts
                     GROUP BY evt_type
                     ORDER BY cnt DESC
                     LIMIT 10'
                );
                $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
                foreach ($stmt->fetchAll() as $r) {
                    if (!is_array($r)) {
                        continue;
                    }
                    $evtTypeCounts[(string)($r['evt_type'] ?? '')] = (int)($r['cnt'] ?? 0);
                }
            } catch (Throwable) {
                $evtTypeCounts = [];
            }

            $sample = [];
            foreach ($rows as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $sample[] = [
                    'event_date' => (string)($r['event_date'] ?? ''),
                    'cc' => (string)($r['cost_center'] ?? ''),
                    'ot' => (string)($r['work_order_number'] ?? ''),
                    'operator' => (string)($r['operator_name'] ?? ''),
                    'produced_units' => (float)($r['produced_units'] ?? 0.0),
                    'requested_units' => (float)($r['requested_units'] ?? 0.0),
                ];
                if (count($sample) >= 5) {
                    break;
                }
            }

            $dbgPost($debugUrl, [
                'ts' => date('c'),
                'sessionId' => 'bonus-query-empty',
                'runId' => 'pre',
                'event' => 'bonus_rows_query_done',
                'monthKey' => $monthKey,
                'startTs' => $startTs,
                'endTs' => $endTs,
                'equipotypeIds' => $equipotypeIds,
                'operatorName' => $operatorName,
                'limit' => $limit,
                'queryMode' => $queryMode,
                'fallbackReason' => $queryFallbackReason,
                'rawRowCount' => is_array($rawRows) ? count($rawRows) : null,
                'rowCount' => count($rows),
                'evtTypeCounts' => $evtTypeCounts,
                'sample' => $sample,
            ]);
        }
        #endregion debug-point bonus-query-empty-main

        #region debug-point flexo-bonus-empty-query
        $debugEnvPath = __DIR__ . '/../.dbg/flexo-bonus-empty.env';
        $debugUrl = '';
        if (is_file($debugEnvPath)) {
            $envRaw = (string)@file_get_contents($debugEnvPath);
            if ($envRaw !== '') {
                foreach (preg_split('/\r?\n/', $envRaw) ?: [] as $line) {
                    $line = trim((string)$line);
                    if ($line === '' || !str_contains($line, '=')) {
                        continue;
                    }
                    [$k, $v] = array_map('trim', explode('=', $line, 2));
                    if ($k === 'DEBUG_SERVER_URL') {
                        $debugUrl = $v;
                        break;
                    }
                }
            }
        }
        if ($debugUrl !== '') {
            $dbgPost = static function (string $url, array $payload): void {
                $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
                if (!is_string($body)) {
                    return;
                }
                $ctx = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/json\r\n",
                        'content' => $body,
                        'timeout' => 1,
                    ],
                ]);
                @file_get_contents($url, false, $ctx);
            };

            $sample = [];
            foreach ($rows as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $sample[] = [
                    'event_date' => (string)($r['event_date'] ?? ''),
                    'cc' => (string)($r['cost_center'] ?? ''),
                    'ot' => (string)($r['work_order_number'] ?? ''),
                    'operator' => (string)($r['operator_name'] ?? ''),
                    'produced_units' => (float)($r['produced_units'] ?? 0.0),
                    'requested_units' => (float)($r['requested_units'] ?? 0.0),
                ];
                if (count($sample) >= 5) {
                    break;
                }
            }

            $dbName = '';
            try {
                $dbName = (string)($this->erpPdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
            } catch (Throwable) {
                $dbName = '';
            }

            $dbgPost($debugUrl, [
                'sessionId' => 'flexo-bonus-empty',
                'hypothesisId' => 'probe',
                'runId' => trim((string)($_GET['debug_run'] ?? 'pre')) !== '' ? trim((string)($_GET['debug_run'] ?? 'pre')) : 'pre',
                'event' => 'erp_bonus_query_snapshot',
                'ts' => time(),
                'bonus' => 'flexo',
                'db' => $dbName,
                'monthKey' => $monthKey,
                'startTs' => $startTs,
                'endTs' => $endTs,
                'equipotypeIds' => $equipotypeIds ?? null,
                'operatorFilter' => $operatorName,
                'queryMode' => $queryMode,
                'fallbackReason' => $queryFallbackReason,
                'rowCount' => count($rows),
                'sample' => $sample,
            ]);
        }
        #endregion debug-point flexo-bonus-empty-query

        return ['ok' => true, 'errors' => [], 'period' => $period, 'rows' => $rows];
    }

    private function parseClientLabelFromErpDesc(string $desc): string
    {
        $desc = trim($desc);
        if ($desc === '') {
            return '';
        }
        $pos = mb_strpos($desc, '(');
        if ($pos !== false && $pos > 0) {
            return trim(mb_substr($desc, 0, $pos));
        }
        return $desc;
    }

    private function parseProductTypeFromErpDesc(string $desc): string
    {
        $desc = strtoupper($desc);
        if (preg_match('/\b(BOU|PRO|TRO|IND|SAC|VAR|OTRO)\b/', $desc, $m) === 1) {
            return (string)$m[1];
        }
        return '';
    }

    private function parseMeasureCmFromErpDesc(string $desc): string
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*[xX]\s*(\d+(?:[.,]\d+)?)(?:\s*[xX]\s*(\d+(?:[.,]\d+)?))?/', $desc, $m) !== 1) {
            return '';
        }
        $a = rtrim(rtrim(str_replace(',', '.', (string)$m[1]), '0'), '.');
        $b = rtrim(rtrim(str_replace(',', '.', (string)$m[2]), '0'), '.');
        $c = trim((string)($m[3] ?? ''));
        if ($c !== '') {
            $c = rtrim(rtrim(str_replace(',', '.', $c), '0'), '.');
            return strtoupper($a . 'X' . $b . 'X' . $c);
        }
        return strtoupper($a . 'X' . $b);
    }

    /**
     * Obtiene la tabla de tramos (Desde/Hasta/Monto) configurada para un bono.
     *
     * En Bonoflexo, estos tramos determinan el bono base según “Total UNID. IMPRESAS”.
     *
     * @return list<array{id:int,bonus_code:string,range_from:int,range_to:int|null,amount_clp:string}>
     */
    public function listBonusBrackets(string $bonusCode): array
    {
        $bonusCode = strtolower(trim($bonusCode));
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT id, bonus_code, range_from, range_to, amount_clp
             FROM bonus_brackets
             WHERE bonus_code = :bonus_code
             ORDER BY range_from ASC, range_to ASC, id ASC'
        );
        $stmt->execute([':bonus_code' => $bonusCode]);
        $rows = $stmt->fetchAll();
        return $rows !== false ? $rows : [];
    }

    /**
     * Obtiene el factor “Compartido” por operador.
     *
     * - Solo se guardan factores distintos de 1.0.
     * - Si un operador no está en el mapa, se asume 1.0.
     *
     * @return array<string,float>
     */
    public function listBonusOperatorFactors(string $bonusCode, string $monthKey = ''): array
    {
        $bonusCode = strtolower(trim($bonusCode));
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            return [];
        }
        $monthKey = trim($monthKey);
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            $monthKey = '';
        }
        $stmt = $this->pdo->prepare(
            'SELECT operator_name, factor
             FROM bonus_operator_factors
             WHERE bonus_code = :bonus_code AND month_key = :month_key
             ORDER BY operator_name ASC'
        );
        $stmt->execute([':bonus_code' => $bonusCode, ':month_key' => $monthKey]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows !== false ? $rows : [] as $row) {
            $name = trim((string)($row['operator_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[$name] = (float)($row['factor'] ?? 1.0);
        }
        return $out;
    }

    /**
     * Reemplaza completamente los factores “Compartido” por operador para un bono.
     *
     * - Valida que factor ∈ {1.0, 0.9, 0.8}.
     * - Omite (no persiste) los operadores con 1.0 para mantener la tabla liviana.
     *
     * @param array<string,float|int|string> $factorsByOperator
     * @return array{ok:bool, errors?:string[]}
     */
    public function replaceBonusOperatorFactors(string $bonusCode, string $monthKey, array $factorsByOperator): array
    {
        $errors = [];
        $bonusCode = strtolower(trim($bonusCode));
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            $errors[] = 'Bono inválido.';
        }
        $monthKey = trim($monthKey);
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            $errors[] = 'Mes inválido.';
        }

        $normalized = [];
        foreach ($factorsByOperator as $operatorName => $factorRaw) {
            $operatorName = trim((string)$operatorName);
            if ($operatorName === '') {
                continue;
            }
            if (mb_strlen($operatorName) > 120) {
                $errors[] = 'Operador inválido.';
                break;
            }
            $factor = (float)$factorRaw;
            $allowed = [1.0, 0.9, 0.8];
            $ok = false;
            foreach ($allowed as $a) {
                if (abs($factor - $a) < 0.0001) {
                    $factor = $a;
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                $errors[] = 'Factor inválido para ' . $operatorName . '.';
                break;
            }
            if (abs($factor - 1.0) < 0.0001) {
                continue;
            }
            $normalized[$operatorName] = $factor;
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM bonus_operator_factors WHERE bonus_code = :bonus_code AND month_key = :month_key');
            $del->execute([':bonus_code' => $bonusCode, ':month_key' => $monthKey]);

            if ($normalized !== []) {
                $ins = $this->pdo->prepare(
                    'INSERT INTO bonus_operator_factors (bonus_code, month_key, operator_name, factor)
                     VALUES (:bonus_code, :month_key, :operator_name, :factor)'
                );
                foreach ($normalized as $operatorName => $factor) {
                    $ins->execute([
                        ':bonus_code' => $bonusCode,
                        ':month_key' => $monthKey,
                        ':operator_name' => $operatorName,
                        ':factor' => $factor,
                    ]);
                }
            }

            $this->pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'errors' => ['No se pudo guardar la configuración.']];
        }
    }

    public function getBonusCoachConfig(string $bonusCode, string $monthKey): array
    {
        $bonusCode = strtolower(trim($bonusCode));
        $monthKey = trim($monthKey);
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            return [];
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            return [];
        }

        $cfg = [];
        try {
            $stmt = $this->pdo->prepare(
                'SELECT coach_name, share_percent
                 FROM bonus_operator_coach_configs
                 WHERE bonus_code = :bonus_code AND month_key = :month_key
                 LIMIT 1'
            );
            $stmt->execute([':bonus_code' => $bonusCode, ':month_key' => $monthKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $cfg = [
                    'coach_name' => trim((string)($row['coach_name'] ?? '')),
                    'share_percent' => (float)($row['share_percent'] ?? 0.0),
                ];
            }
        } catch (Throwable) {
            $cfg = [];
        }

        $trainees = [];
        try {
            $stmt = $this->pdo->prepare(
                'SELECT trainee_name
                 FROM bonus_operator_coach_trainees
                 WHERE bonus_code = :bonus_code AND month_key = :month_key
                 ORDER BY trainee_name ASC'
            );
            $stmt->execute([':bonus_code' => $bonusCode, ':month_key' => $monthKey]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $v) {
                $name = trim((string)$v);
                if ($name !== '') {
                    $trainees[] = $name;
                }
            }
        } catch (Throwable) {
            $trainees = [];
        }

        $cfg['trainees'] = $trainees;
        return $cfg;
    }

    public function replaceBonusCoachConfig(string $bonusCode, string $monthKey, string $coachName, float $sharePercent, array $trainees): array
    {
        $errors = [];
        $bonusCode = strtolower(trim($bonusCode));
        $monthKey = trim($monthKey);
        $coachName = trim($coachName);

        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            $errors[] = 'Bono inválido.';
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            $errors[] = 'Mes inválido.';
        }

        $allowed = [0.0, 0.5];
        $ok = false;
        foreach ($allowed as $a) {
            if (abs($sharePercent - $a) < 0.0001) {
                $sharePercent = $a;
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            $errors[] = 'Porcentaje inválido.';
        }

        $traineeNames = [];
        foreach ($trainees as $t) {
            $t = trim((string)$t);
            if ($t !== '') {
                $traineeNames[$t] = true;
            }
        }
        $traineeList = array_keys($traineeNames);
        sort($traineeList, SORT_NATURAL | SORT_FLAG_CASE);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->pdo->beginTransaction();
        try {
            $delT = $this->pdo->prepare('DELETE FROM bonus_operator_coach_trainees WHERE bonus_code = :bonus_code AND month_key = :month_key');
            $delT->execute([':bonus_code' => $bonusCode, ':month_key' => $monthKey]);

            $delC = $this->pdo->prepare('DELETE FROM bonus_operator_coach_configs WHERE bonus_code = :bonus_code AND month_key = :month_key');
            $delC->execute([':bonus_code' => $bonusCode, ':month_key' => $monthKey]);

            if ($coachName !== '' && $sharePercent > 0 && $traineeList !== []) {
                $insC = $this->pdo->prepare(
                    'INSERT INTO bonus_operator_coach_configs (bonus_code, month_key, coach_name, share_percent)
                     VALUES (:bonus_code, :month_key, :coach_name, :share_percent)'
                );
                $insC->execute([
                    ':bonus_code' => $bonusCode,
                    ':month_key' => $monthKey,
                    ':coach_name' => $coachName,
                    ':share_percent' => $sharePercent,
                ]);

                $insT = $this->pdo->prepare(
                    'INSERT INTO bonus_operator_coach_trainees (bonus_code, month_key, trainee_name)
                     VALUES (:bonus_code, :month_key, :trainee_name)'
                );
                foreach ($traineeList as $t) {
                    $insT->execute([
                        ':bonus_code' => $bonusCode,
                        ':month_key' => $monthKey,
                        ':trainee_name' => $t,
                    ]);
                }
            }

            $this->pdo->commit();
            return ['ok' => true];
        } catch (Throwable) {
            $this->pdo->rollBack();
            return ['ok' => false, 'errors' => ['No se pudo guardar la configuración.']];
        }
    }

    /**
     * Resuelve el monto del bono base (sin “Compartido”) buscando el tramo correspondiente.
     *
     * - $units: normalmente corresponde a “Total UNID. IMPRESAS” (evt_amount sumado).
     * - Se redondea a entero para calzar la tabla de tramos.
     * - Los tramos se asumen ordenados por range_from ascendente.
     */
    public function resolveBonusBracketAmount(array $brackets, float $units): float
    {
        $u = (int)round($units);
        foreach ($brackets as $b) {
            if (!is_array($b)) {
                continue;
            }
            $from = (int)($b['range_from'] ?? 0);
            $to = ($b['range_to'] ?? null) !== null ? (int)$b['range_to'] : null;
            if ($u < $from) {
                break;
            }
            if ($to !== null && $u > $to) {
                continue;
            }
            return (float)($b['amount_clp'] ?? 0);
        }
        return 0.0;
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function saveBonusBracket(string $bonusCode, ?int $id, int $rangeFrom, ?int $rangeTo, float $amountClp): array
    {
        $errors = [];
        $bonusCode = strtolower(trim($bonusCode));
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            $errors[] = 'Bono inválido.';
        }
        if ($rangeFrom < 0) {
            $errors[] = 'Desde (unidades) debe ser mayor o igual a 0.';
        }
        if ($rangeTo !== null && $rangeTo < $rangeFrom) {
            $errors[] = 'Hasta (unidades) debe ser mayor o igual a Desde.';
        }
        if ($amountClp < 0) {
            $errors[] = 'El monto no puede ser negativo.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $id = $id !== null ? (int)$id : null;
        if ($id !== null && $id > 0) {
            $stmt = $this->pdo->prepare(
                'UPDATE bonus_brackets
                 SET range_from = :range_from,
                     range_to = :range_to,
                     amount_clp = :amount_clp
                 WHERE id = :id AND bonus_code = :bonus_code'
            );
            $stmt->execute([
                ':range_from' => $rangeFrom,
                ':range_to' => $rangeTo,
                ':amount_clp' => $amountClp,
                ':id' => $id,
                ':bonus_code' => $bonusCode,
            ]);
            return ['ok' => true, 'id' => $id, 'errors' => []];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO bonus_brackets (bonus_code, range_from, range_to, amount_clp)
             VALUES (:bonus_code, :range_from, :range_to, :amount_clp)'
        );
        $stmt->execute([
            ':bonus_code' => $bonusCode,
            ':range_from' => $rangeFrom,
            ':range_to' => $rangeTo,
            ':amount_clp' => $amountClp,
        ]);
        $newId = (int)$this->pdo->lastInsertId();
        return ['ok' => true, 'id' => $newId, 'errors' => []];
    }

    public function deleteBonusBracket(string $bonusCode, int $id): array
    {
        $errors = [];
        $bonusCode = strtolower(trim($bonusCode));
        $id = (int)$id;
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            $errors[] = 'Bono inválido.';
        }
        if ($id <= 0) {
            $errors[] = 'Registro inválido.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        $stmt = $this->pdo->prepare('DELETE FROM bonus_brackets WHERE id = :id AND bonus_code = :bonus_code');
        $stmt->execute([':id' => $id, ':bonus_code' => $bonusCode]);
        return ['ok' => true];
    }

    /**
     * Reemplaza completamente la tabla de tramos de un bono.
     *
     * En Bonoflexo se usa como acción administrativa para cargar una tabla base
     * (o para dejar configurado el set de tramos completo por defecto).
     *
     * @param list<array{range_from:int,range_to:int|null,amount_clp:float}> $brackets
     * @return array{ok:bool, errors?:string[]}
     */
    public function replaceBonusBrackets(string $bonusCode, array $brackets): array
    {
        $errors = [];
        $bonusCode = strtolower(trim($bonusCode));
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            $errors[] = 'Bono inválido.';
        }
        foreach ($brackets as $idx => $b) {
            $from = (int)($b['range_from'] ?? -1);
            $to = ($b['range_to'] ?? null) !== null ? (int)$b['range_to'] : null;
            $amount = (float)($b['amount_clp'] ?? -1);
            if ($from < 0) {
                $errors[] = 'Tramo inválido (Desde) en fila ' . ($idx + 1) . '.';
                break;
            }
            if ($to !== null && $to < $from) {
                $errors[] = 'Tramo inválido (Hasta) en fila ' . ($idx + 1) . '.';
                break;
            }
            if ($amount < 0) {
                $errors[] = 'Monto inválido en fila ' . ($idx + 1) . '.';
                break;
            }
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM bonus_brackets WHERE bonus_code = :bonus_code');
            $del->execute([':bonus_code' => $bonusCode]);

            $ins = $this->pdo->prepare(
                'INSERT INTO bonus_brackets (bonus_code, range_from, range_to, amount_clp)
                 VALUES (:bonus_code, :range_from, :range_to, :amount_clp)'
            );
            foreach ($brackets as $b) {
                $ins->execute([
                    ':bonus_code' => $bonusCode,
                    ':range_from' => (int)$b['range_from'],
                    ':range_to' => ($b['range_to'] ?? null) !== null ? (int)$b['range_to'] : null,
                    ':amount_clp' => (float)$b['amount_clp'],
                ]);
            }

            $this->pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'errors' => ['No se pudo guardar la configuración.']];
        }
    }

    /**
     * @return array<string, array<string, float>>
     */
    public function getBonusUnitRates(string $bonusCode): array
    {
        $bonusCode = strtolower(trim($bonusCode));
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT category_code, tier_code, rate_clp
             FROM bonus_unit_rates
             WHERE bonus_code = :bonus_code
             ORDER BY category_code ASC, tier_code ASC'
        );
        $stmt->execute([':bonus_code' => $bonusCode]);
        $rows = $stmt->fetchAll();
        if ($rows === false || $rows === []) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $cat = strtolower(trim((string)($r['category_code'] ?? '')));
            $tier = strtoupper(trim((string)($r['tier_code'] ?? '')));
            if ($cat === '' || $tier === '') {
                continue;
            }
            if (!isset($out[$cat])) {
                $out[$cat] = [];
            }
            $out[$cat][$tier] = (float)($r['rate_clp'] ?? 0.0);
        }
        return $out;
    }

    /**
     * @param array<string, array<string, float>> $rates
     * @return array{ok:bool, errors?:string[]}
     */
    public function replaceBonusUnitRates(string $bonusCode, array $rates): array
    {
        $errors = [];
        $bonusCode = strtolower(trim($bonusCode));
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            $errors[] = 'Bono inválido.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $allowedTiers = ['LT_3000', 'GTE_3000', 'BASE', 'AMOUNT'];
        foreach ($rates as $category => $tiers) {
            $category = strtolower(trim((string)$category));
            if ($category === '') {
                $errors[] = 'Categoría inválida.';
                break;
            }
            foreach ($tiers as $tierCode => $rate) {
                $tierCode = strtoupper(trim((string)$tierCode));
                if (!in_array($tierCode, $allowedTiers, true)) {
                    $errors[] = 'Tier inválido.';
                    break 2;
                }
                $rate = (float)$rate;
                if ($rate < 0) {
                    $errors[] = 'La tarifa no puede ser negativa.';
                    break 2;
                }
            }
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM bonus_unit_rates WHERE bonus_code = :bonus_code');
            $del->execute([':bonus_code' => $bonusCode]);

            $ins = $this->pdo->prepare(
                'INSERT INTO bonus_unit_rates (bonus_code, category_code, tier_code, rate_clp)
                 VALUES (:bonus_code, :category_code, :tier_code, :rate_clp)'
            );
            foreach ($rates as $category => $tiers) {
                $category = strtolower(trim((string)$category));
                foreach ($tiers as $tierCode => $rate) {
                    $tierCode = strtoupper(trim((string)$tierCode));
                    $ins->execute([
                        ':bonus_code' => $bonusCode,
                        ':category_code' => $category,
                        ':tier_code' => $tierCode,
                        ':rate_clp' => (float)$rate,
                    ]);
                }
            }

            $this->pdo->commit();
            return ['ok' => true];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'errors' => ['No se pudo guardar la configuración.']];
        }
    }

    /**
     * Analytics and dataset generator for ERP graphics dashboard (/reports/graphics).
     *
     * Connects directly to unibag_unibag (ERP DB) to aggregate:
     * - Macro KPIs: Confection, Printing, Total Units, Active Machines, Waste (kg and %).
     * - Monthly Trend: Past 6 months of historical production.
     * - Period Evolution: Day-by-day (or weekly) production curve within the selected date range.
     * - Process Distribution: Confection vs Printing breakdown.
     * - Machine Ranking: Top producing machines.
     * - Machine Waste: Defect kg per machine.
     * - Warehouse Occupancy: Current capacity and utilization.
     */
    public function getErpGraphicsAnalytics(string $startAt, string $endAt): array
    {
        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = 0;
            $endTs = 0;
        }

        $emptyResponse = [
            'kpis' => [
                'units_corte_sellado' => 0.0,
                'units_embalaje' => 0.0,
                'units_flexo' => 0.0,
                'units_seri' => 0.0,
                'units_pulpo' => 0.0,
                'units_confeccion' => 0.0,
                'units_impresion' => 0.0,
                'total_units' => 0.0,
                'total_events' => 0,
                'active_machines' => 0,
                'waste_kg' => 0.0,
                'waste_units' => 0.0,
                'waste_percent' => 0.0,
            ],
            'monthly_trend' => [],
            'period_evolution' => [],
            'process_distribution' => [],
            'machine_ranking' => [],
            'waste_by_machine' => [],
            'warehouses' => [],
        ];

        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs || !$this->erpPdo) {
            return $emptyResponse;
        }

        try {
            // 1. KPIs Generales de Producción
            $kpiSql = "
                SELECT 
                    COALESCE(SUM(CASE WHEN eq.equipo_type_id = 8 THEN e.evt_amount ELSE 0 END), 0) AS units_corte_sellado,
                    COALESCE(SUM(CASE WHEN eq.equipo_type_id = 15 THEN e.evt_amount ELSE 0 END), 0) AS units_embalaje,
                    COALESCE(SUM(CASE WHEN eq.equipo_type_id = 7 THEN e.evt_amount ELSE 0 END), 0) AS units_flexo,
                    COALESCE(SUM(CASE WHEN (eq.equipo_type_id = 11 AND eq.id != 36 AND LOWER(eq.equipo_name) NOT LIKE '%pulpo%') THEN e.evt_amount ELSE 0 END), 0) AS units_seri,
                    COALESCE(SUM(CASE WHEN (eq.equipo_type_id = 22 OR eq.id = 36 OR LOWER(eq.equipo_name) LIKE '%pulpo%') THEN e.evt_amount ELSE 0 END), 0) AS units_pulpo,
                    COALESCE(SUM(CASE WHEN eq.equipo_type_id IN (8, 15) THEN e.evt_amount ELSE 0 END), 0) AS units_confeccion,
                    COALESCE(SUM(CASE WHEN eq.equipo_type_id IN (7, 11, 22) OR eq.id = 36 OR LOWER(eq.equipo_name) LIKE '%pulpo%' THEN e.evt_amount ELSE 0 END), 0) AS units_impresion,
                    COALESCE(SUM(e.evt_amount), 0) AS total_units,
                    COUNT(DISTINCT e.id) AS total_events,
                    COUNT(DISTINCT eq.id) AS active_machines
                FROM prod_worker_ot_events e
                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                  AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
            ";
            $stmt = $this->erpPdo->prepare($kpiSql);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $prodKpis = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // Merma KPIs
            $wasteSql = "
                SELECT 
                    COUNT(d.id) AS waste_events,
                    COALESCE(SUM(d.evt_amount), 0) AS waste_units,
                    COALESCE(ROUND(SUM(d.evt_kgstounits), 2), 0) AS waste_kg
                FROM prod_worker_ot_defectunits d
                WHERE d.evt_crtdat BETWEEN :start_ts AND :end_ts
                  AND LOWER(d.evt_type) = 'merma'
            ";
            $stmt = $this->erpPdo->prepare($wasteSql);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $wasteKpis = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // Merma % oficial del ERP Dashboard
            $erpOnlyKpis = $this->getErpOnlyProductionDashboardKpis($startAt, $endAt);
            $wastePercent = $erpOnlyKpis['waste']['percent'] ?? 0.0;
            if ($wastePercent === null || !is_numeric($wastePercent)) {
                $wastePercent = 0.0;
            }

            // 2. Tendencia Histórica Mensual (Últimos 6 meses)
            $sixMonthsAgoTs = strtotime('-6 months', $endTs);
            $monthlySql = "
                SELECT 
                    FROM_UNIXTIME(e.evt_crtdat, '%Y-%m') AS ym,
                    COALESCE(SUM(CASE WHEN eq.equipo_type_id IN (8, 15) THEN e.evt_amount ELSE 0 END), 0) AS confeccion,
                    COALESCE(SUM(CASE WHEN eq.equipo_type_id IN (7, 11, 22) THEN e.evt_amount ELSE 0 END), 0) AS impresion,
                    COALESCE(SUM(e.evt_amount), 0) AS total
                FROM prod_worker_ot_events e
                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                WHERE e.evt_crtdat BETWEEN :six_ts AND :end_ts
                  AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                GROUP BY ym
                ORDER BY ym ASC
            ";
            $stmt = $this->erpPdo->prepare($monthlySql);
            $stmt->execute([':six_ts' => $sixMonthsAgoTs, ':end_ts' => $endTs]);
            $monthlyRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $monthlyTrend = [];
            foreach ($monthlyRows as $mr) {
                $time = strtotime($mr['ym'] . '-01');
                $monthlyTrend[] = [
                    'ym' => $mr['ym'],
                    'label' => $time ? date('M/y', $time) : $mr['ym'],
                    'confeccion' => round((float)$mr['confeccion'], 0),
                    'impresion' => round((float)$mr['impresion'], 0),
                    'total' => round((float)$mr['total'], 0),
                ];
            }

            // 3. Evolución en el Período
            $diffDays = ($endTs - $startTs) / 86400;
            if ($diffDays <= 65) {
                $periodSql = "
                    SELECT 
                        FROM_UNIXTIME(e.evt_crtdat, '%Y-%m-%d') AS date_key,
                        FROM_UNIXTIME(e.evt_crtdat, '%d/%m') AS date_label,
                        COALESCE(SUM(CASE WHEN eq.equipo_type_id IN (8, 15) THEN e.evt_amount ELSE 0 END), 0) AS confeccion,
                        COALESCE(SUM(CASE WHEN eq.equipo_type_id IN (7, 11, 22) THEN e.evt_amount ELSE 0 END), 0) AS impresion,
                        COALESCE(SUM(e.evt_amount), 0) AS total
                    FROM prod_worker_ot_events e
                    INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                    INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                    INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                    WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                      AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                    GROUP BY date_key, date_label
                    ORDER BY date_key ASC
                ";
            } else {
                $periodSql = "
                    SELECT 
                        FROM_UNIXTIME(e.evt_crtdat, '%Y-%u') AS date_key,
                        CONCAT('Sem ', FROM_UNIXTIME(e.evt_crtdat, '%u')) AS date_label,
                        COALESCE(SUM(CASE WHEN eq.equipo_type_id IN (8, 15) THEN e.evt_amount ELSE 0 END), 0) AS confeccion,
                        COALESCE(SUM(CASE WHEN eq.equipo_type_id IN (7, 11, 22) THEN e.evt_amount ELSE 0 END), 0) AS impresion,
                        COALESCE(SUM(e.evt_amount), 0) AS total
                    FROM prod_worker_ot_events e
                    INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                    INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                    INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                    WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                      AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                    GROUP BY date_key, date_label
                    ORDER BY date_key ASC
                ";
            }
            $stmt = $this->erpPdo->prepare($periodSql);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $periodEvolution = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $pe) {
                $periodEvolution[] = [
                    'key' => $pe['date_key'],
                    'label' => $pe['date_label'],
                    'confeccion' => round((float)$pe['confeccion'], 0),
                    'impresion' => round((float)$pe['impresion'], 0),
                    'total' => round((float)$pe['total'], 0),
                ];
            }

            // 4. Distribución por Proceso
            $processSql = "
                SELECT 
                    CASE 
                        WHEN eq.equipo_type_id = 22 OR eq.id = 36 OR LOWER(eq.equipo_name) LIKE '%pulpo%' THEN 'Pulpo Serigráfico'
                        WHEN eq.equipo_type_id = 11 THEN 'Serigrafía'
                        WHEN eq.equipo_type_id = 7 THEN 'Flexografía'
                        WHEN eq.equipo_type_id = 8 THEN 'Selladoras'
                        WHEN eq.equipo_type_id = 15 THEN 'Embalaje'
                        ELSE COALESCE(NULLIF(TRIM(eqt.type_ant_title), ''), 'Otro')
                    END AS process_name,
                    SUM(e.evt_amount) AS units
                FROM prod_worker_ot_events e
                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                LEFT JOIN equipo_type eqt ON eqt.id = eq.equipo_type_id
                WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                  AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                GROUP BY process_name
                ORDER BY units DESC
            ";
            $stmt = $this->erpPdo->prepare($processSql);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $processDistribution = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $pr) {
                $processDistribution[] = [
                    'process_name' => $pr['process_name'],
                    'units' => round((float)$pr['units'], 0),
                ];
            }

            // 5. Ranking de Máquinas
            $machinesSql = "
                SELECT 
                    COALESCE(NULLIF(TRIM(eq.equipo_name), ''), 'Sin nombre') AS machine_name,
                    CASE 
                        WHEN eq.equipo_type_id = 22 OR eq.id = 36 OR LOWER(eq.equipo_name) LIKE '%pulpo%' THEN 'Pulpo Serigráfico'
                        WHEN eq.equipo_type_id = 11 THEN 'Serigrafía'
                        WHEN eq.equipo_type_id = 7 THEN 'Flexografía'
                        WHEN eq.equipo_type_id = 8 THEN 'Selladoras'
                        WHEN eq.equipo_type_id = 15 THEN 'Embalaje'
                        ELSE 'Otro'
                    END AS process_name,
                    SUM(e.evt_amount) AS units
                FROM prod_worker_ot_events e
                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                  AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                GROUP BY machine_name, process_name
                ORDER BY units DESC
                LIMIT 12
            ";
            $stmt = $this->erpPdo->prepare($machinesSql);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $machineRanking = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $m) {
                $machineRanking[] = [
                    'machine_name' => $m['machine_name'],
                    'process_name' => $m['process_name'],
                    'units' => round((float)$m['units'], 0),
                ];
            }

            // 6. Merma por Máquina
            $wasteMachineSql = "
                SELECT 
                    COALESCE(NULLIF(TRIM(eq.equipo_name), ''), 'Sin máquina') AS machine_name,
                    COUNT(d.id) AS defect_events,
                    COALESCE(SUM(d.evt_amount), 0) AS defect_units,
                    COALESCE(ROUND(SUM(d.evt_kgstounits), 2), 0) AS defect_kg
                FROM prod_worker_ot_defectunits d
                INNER JOIN prod_worker_ot_events e ON e.id = d.evt_refid
                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                WHERE d.evt_crtdat BETWEEN :start_ts AND :end_ts
                  AND LOWER(d.evt_type) = 'merma'
                GROUP BY machine_name
                ORDER BY defect_kg DESC
                LIMIT 10
            ";
            $stmt = $this->erpPdo->prepare($wasteMachineSql);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $wasteByMachine = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $wm) {
                $wasteByMachine[] = [
                    'machine_name' => $wm['machine_name'],
                    'defect_events' => (int)$wm['defect_events'],
                    'defect_units' => round((float)$wm['defect_units'], 0),
                    'defect_kg' => (float)$wm['defect_kg'],
                ];
            }

            return [
                'kpis' => [
                    'units_corte_sellado' => round((float)($prodKpis['units_corte_sellado'] ?? 0), 0),
                    'units_embalaje' => round((float)($prodKpis['units_embalaje'] ?? 0), 0),
                    'units_flexo' => round((float)($prodKpis['units_flexo'] ?? 0), 0),
                    'units_seri' => round((float)($prodKpis['units_seri'] ?? 0), 0),
                    'units_pulpo' => round((float)($prodKpis['units_pulpo'] ?? 0), 0),
                    'units_confeccion' => round((float)($prodKpis['units_confeccion'] ?? 0), 0),
                    'units_impresion' => round((float)($prodKpis['units_impresion'] ?? 0), 0),
                    'total_units' => round((float)($prodKpis['total_units'] ?? 0), 0),
                    'total_events' => (int)($prodKpis['total_events'] ?? 0),
                    'active_machines' => (int)($prodKpis['active_machines'] ?? 0),
                    'waste_kg' => (float)($wasteKpis['waste_kg'] ?? 0),
                    'waste_units' => round((float)($wasteKpis['waste_units'] ?? 0), 0),
                    'waste_percent' => round((float)$wastePercent, 2),
                ],
                'monthly_trend' => $monthlyTrend,
                'period_evolution' => $periodEvolution,
                'process_distribution' => $processDistribution,
                'machine_ranking' => $machineRanking,
                'waste_by_machine' => $wasteByMachine,
            ];
        } catch (Throwable $e) {
            return $emptyResponse;
        }
    }

    // =========================================================================
    // SECCIÓN: CONTROL Y GESTIÓN DE HORARIOS DE COLACIÓN DE OPERARIOS
    // =========================================================================

    public function ensureOperatorLunchBreaksTable(): void
    {
        try {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS production_operator_lunch_breaks (
                  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  shift_date DATE NOT NULL,
                  init_id BIGINT NULL,
                  worker_id BIGINT NULL,
                  operator_name VARCHAR(150) NOT NULL,
                  machine_id INT UNSIGNED NULL,
                  machine_name VARCHAR(120) NULL,
                  start_time DATETIME NOT NULL,
                  end_time DATETIME NULL,
                  duration_minutes INT UNSIGNED NULL,
                  erp_event_id BIGINT NULL,
                  comments TEXT NULL,
                  created_by VARCHAR(100) NULL,
                  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (id),
                  KEY idx_lunch_date (shift_date),
                  KEY idx_lunch_worker (worker_id),
                  KEY idx_lunch_init (init_id),
                  KEY idx_lunch_machine (machine_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable) {
            // Ignorar si la tabla ya existe o faltan permisos de DDL
        }
    }

    /**
     * Obtiene el reporte consolidado de turnos y colaciones para una fecha específica.
     * Lista a TODOS los operarios que iniciaron turno (hayan ido o no a colación).
     *
     * @param string $date Formato 'YYYY-MM-DD'
     * @return array<string, mixed>
     */
    public function getOperatorLunchReport(string $date = ''): array
    {
        $this->ensureOperatorLunchBreaksTable();

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        list($year, $month, $day) = explode('-', $date);

        // 1. Obtener todas las máquinas activas de la planta excluyendo supervisión y mantención
        $allMachines = [];
        try {
            $stmtEq = $this->erpPdo->query("
                SELECT id, equipo_name 
                FROM equipo 
                WHERE equipo_status = 1 
                  AND equipo_name NOT LIKE '%SUPERVISOR%' 
                  AND equipo_name NOT LIKE '%MANTENCION%'
                ORDER BY equipo_type_id ASC, equipo_name ASC
            ");
            foreach ($stmtEq->fetchAll(PDO::FETCH_ASSOC) as $eqRow) {
                $mName = trim((string)$eqRow['equipo_name']);
                if ($mName !== '') {
                    $allMachines[(int)$eqRow['id']] = $mName;
                }
            }
        } catch (Throwable) {}

        // 2. Obtener registros guardados localmente en TRZ
        $savedBreaks = [];
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM production_operator_lunch_breaks WHERE shift_date = :d");
            $stmt->execute([':d' => $date]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $sb) {
                if (!empty($sb['init_id']) && !empty($sb['worker_id'])) {
                    $savedBreaks['init_' . $sb['init_id'] . '_' . $sb['worker_id']] = $sb;
                }
                if (!empty($sb['worker_id'])) {
                    $savedBreaks['wrk_' . $sb['worker_id']] = $sb;
                }
                if (!empty($sb['operator_name'])) {
                    $savedBreaks['name_' . mb_strtolower(trim((string)$sb['operator_name']))] = $sb;
                }
            }
        } catch (Throwable) {}

        $operators = [];

        // Identificar qué operarios están asignados o iniciaron turno en SELLADORA para esta fecha
        // Regla: Los que estén en selladora y embalen se cuentan solo en selladora, no en embalaje
        $workersOnSelladora = [];
        try {
            $stmtSell = $this->erpPdo->prepare("
                SELECT a.assign_worker_id, eq.id AS eq_id, eq.equipo_name 
                FROM turnos_config_assign a
                LEFT JOIN equipo eq ON a.assign_equipoaid = eq.id
                WHERE a.assign_year = :year AND a.assign_month = :month AND a.assign_day = :day
                  AND eq.equipo_name LIKE '%SELLADORA%'
            ");
            $stmtSell->execute([':year' => (int)$year, ':month' => (int)$month, ':day' => (int)$day]);
            foreach ($stmtSell->fetchAll(PDO::FETCH_ASSOC) as $sRow) {
                $workersOnSelladora[(int)$sRow['assign_worker_id']] = [
                    'machine_id' => (int)$sRow['eq_id'],
                    'machine_name' => trim((string)$sRow['equipo_name']),
                ];
            }

            $stmtInitsSell = $this->erpPdo->prepare("
                SELECT wi.win_wrkid, eq.id AS eq_id, eq.equipo_name 
                FROM prod_worker_init wi
                LEFT JOIN equipo eq ON wi.win_equipoid = eq.id
                WHERE wi.win_year = :year AND wi.win_month = :month AND wi.win_day = :day
                  AND eq.equipo_name LIKE '%SELLADORA%'
            ");
            $stmtInitsSell->execute([':year' => (int)$year, ':month' => (int)$month, ':day' => (int)$day]);
            foreach ($stmtInitsSell->fetchAll(PDO::FETCH_ASSOC) as $sRow) {
                $workersOnSelladora[(int)$sRow['win_wrkid']] = [
                    'machine_id' => (int)$sRow['eq_id'],
                    'machine_name' => trim((string)$sRow['equipo_name']),
                ];
            }
        } catch (Throwable) {}

        // 3. Consultar programación de turnos en ERP (turnos_config_assign) - base de operarios programados
        try {
            $sqlAssign = "
                SELECT 
                    a.id AS assign_id,
                    a.assign_worker_id AS worker_id,
                    a.assign_equipoaid AS machine_id,
                    eq.equipo_name AS machine_name,
                    TRIM(CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, ''))) AS operator_name,
                    w.wrk_cargoid,
                    wt.type_name AS cargo_name,
                    a.ass_init_hour,
                    a.ass_init_min,
                    a.ass_end_hour,
                    a.ass_end_min
                FROM turnos_config_assign a
                LEFT JOIN equipo eq ON a.assign_equipoaid = eq.id
                LEFT JOIN workers w ON a.assign_worker_id = w.id
                LEFT JOIN workers_types wt ON w.wrk_cargoid = wt.id
                WHERE a.assign_year = :year AND a.assign_month = :month AND a.assign_day = :day
                  AND (a.confirm_falta_act IS NULL OR a.confirm_falta_act = 0)
                  AND (eq.equipo_name IS NULL OR (eq.equipo_name NOT LIKE '%SUPERVISOR%' AND eq.equipo_name NOT LIKE '%MANTENCION%'))
                  AND (w.wrk_cargoid IS NULL OR w.wrk_cargoid NOT IN (3, 4, 9, 10))
                  AND (wt.type_name IS NULL OR (wt.type_name NOT LIKE '%Supervisor%' AND wt.type_name NOT LIKE '%Mantenci%' AND wt.type_name NOT LIKE '%Mantenim%'))
                  AND (a.assign_worker_type IS NULL OR a.assign_worker_type NOT LIKE '%supervisor%')
                  AND (a.assign_worker_id IS NULL OR a.assign_worker_id NOT IN (27, 68))
                ORDER BY eq.equipo_name, w.wrk_firstname
            ";
            $stmt = $this->erpPdo->prepare($sqlAssign);
            $stmt->execute([':year' => (int)$year, ':month' => (int)$month, ':day' => (int)$day]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $wId = (int)$r['worker_id'];
                $mId = (int)$r['machine_id'];
                $mName = trim((string)$r['machine_name']) ?: ($allMachines[$mId] ?? 'Sin Máquina');
                $opName = trim((string)$r['operator_name']) ?: ('Operario #' . $wId);

                // Regla: Los que estén en selladora y embalen se cuentan solo en selladora, no en embalaje
                if (isset($workersOnSelladora[$wId]) && stripos($mName, 'embalaje') !== false) {
                    continue;
                }

                $startH = $r['ass_init_hour'] !== null ? sprintf('%02d:%02d', (int)$r['ass_init_hour'], (int)$r['ass_init_min']) : '07:10';
                $endH = $r['ass_end_hour'] !== null ? sprintf('%02d:%02d', (int)$r['ass_end_hour'], (int)$r['ass_end_min']) : '15:40';

                $key = 'W_' . $wId;
                if (!isset($operators[$key])) {
                    $operators[$key] = [
                        'key' => $key,
                        'source' => 'ERP_ASSIGN',
                        'init_id' => 0,
                        'worker_id' => $wId,
                        'operator_name' => $opName,
                        'machine_id' => $mId,
                        'machine_name' => $mName,
                        'shift_start' => $startH,
                        'shift_end' => $endH,
                        'shift_status' => 'PROGRAMADO',
                        'has_lunch' => false,
                        'lunch_event_id' => null,
                        'lunch_start' => '',
                        'lunch_end' => '',
                        'duration_minutes' => 0,
                        'lunch_status' => 'PENDIENTE',
                        'comments' => '',
                        'latest_ot_id' => null,
                    ];
                }
            }
        } catch (Throwable) {}

        // 4. Consultar turnos físicos iniciados en ERP (prod_worker_init)
        try {
            $sqlInit = "
                SELECT 
                    wi.id AS init_id,
                    wi.win_crtdat,
                    wi.win_enddat,
                    wi.win_status,
                    w.id AS worker_id,
                    TRIM(CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, ''))) AS operator_name,
                    eq.id AS machine_id,
                    COALESCE(eq.equipo_name, 'Sin Máquina') AS machine_name,
                    w.wrk_cargoid,
                    wt.type_name AS cargo_name,
                    ot.id AS ot_id,
                    ev.id AS colacion_evt_id,
                    ev.evt_crtdat AS colacion_inicio_ts,
                    ev.evt_enddat AS colacion_fin_ts,
                    ev.evt_status AS colacion_status
                FROM prod_worker_init wi
                LEFT JOIN workers w ON wi.win_wrkid = w.id
                LEFT JOIN workers_types wt ON w.wrk_cargoid = wt.id
                LEFT JOIN equipo eq ON wi.win_equipoid = eq.id
                LEFT JOIN prod_worker_ot ot ON ot.wok_init_id = wi.id
                LEFT JOIN prod_worker_ot_events ev ON ev.evt_prod_worker_otid = ot.id 
                     AND ev.evt_pause_id = 1 AND ev.evt_type = 'pause' AND ev.evt_status > 0
                     AND ev.evt_crtdat BETWEEN :start_ts AND :end_ts
                WHERE (
                    (wi.win_day = :day AND wi.win_month = :month AND wi.win_year = :year)
                    OR (wi.win_status = 1 AND wi.win_crtdat >= (:start_ts - 86400) AND wi.win_crtdat <= :end_ts)
                    OR (wi.win_status = 1 AND EXISTS (
                        SELECT 1 FROM prod_worker_ot ot_act 
                        JOIN prod_worker_ot_events ev_act ON ev_act.evt_prod_worker_otid = ot_act.id 
                        WHERE ot_act.wok_init_id = wi.id AND ev_act.evt_crtdat BETWEEN :start_ts AND :end_ts
                    ))
                  )
                  AND (eq.equipo_name IS NULL OR (eq.equipo_name NOT LIKE '%SUPERVISOR%' AND eq.equipo_name NOT LIKE '%MANTENCION%'))
                  AND (w.wrk_cargoid IS NULL OR w.wrk_cargoid NOT IN (3, 4, 9, 10))
                  AND (wt.type_name IS NULL OR (wt.type_name NOT LIKE '%Supervisor%' AND wt.type_name NOT LIKE '%Mantenci%' AND wt.type_name NOT LIKE '%Mantenim%'))
                  AND (wi.win_wrkid IS NULL OR wi.win_wrkid NOT IN (27, 68))
                ORDER BY wi.id ASC, ev.id DESC
            ";
            $dayStartTs = strtotime($date . ' 00:00:00');
            $dayEndTs = strtotime($date . ' 23:59:59');
            $stmt = $this->erpPdo->prepare($sqlInit);
            $stmt->execute([
                ':day' => (int)$day,
                ':month' => (int)$month,
                ':year' => (int)$year,
                ':start_ts' => $dayStartTs,
                ':end_ts' => $dayEndTs
            ]);
            $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rawRows as $r) {
                $wId = (int)$r['worker_id'];
                $mId = (int)$r['machine_id'];
                $mName = trim((string)$r['machine_name']) ?: ($allMachines[$mId] ?? 'Sin Máquina');
                $opName = trim((string)$r['operator_name']) ?: ('Operario #' . $wId);

                $isSelladora = (stripos($mName, 'selladora') !== false) || isset($workersOnSelladora[$wId]);
                $isEmbalaje = (stripos($mName, 'embalaje') !== false);

                // Regla: Los que estén en selladora y embalen se cuentan solo en selladora, no en embalaje
                if ($isSelladora && $isEmbalaje) {
                    if (isset($workersOnSelladora[$wId])) {
                        $mId = (int)$workersOnSelladora[$wId]['machine_id'];
                        $mName = (string)$workersOnSelladora[$wId]['machine_name'];
                    }
                }

                $key = 'W_' . $wId;
                $shiftStart = !empty($r['win_crtdat']) ? date('H:i', (int)$r['win_crtdat']) : '07:10';
                $shiftEnd = (!empty($r['win_enddat']) && (int)$r['win_enddat'] > 0) ? date('H:i', (int)$r['win_enddat']) : 'En curso';
                $shiftStatus = (int)$r['win_status'] === 1 ? 'ACTIVO' : 'CERRADO';

                if (!isset($operators[$key])) {
                    $operators[$key] = [
                        'key' => $key,
                        'source' => 'ERP_INIT',
                        'init_id' => (int)$r['init_id'],
                        'worker_id' => $wId,
                        'operator_name' => $opName,
                        'machine_id' => $mId,
                        'machine_name' => $mName,
                        'shift_start' => $shiftStart,
                        'shift_end' => $shiftEnd,
                        'shift_status' => $shiftStatus,
                        'has_lunch' => false,
                        'lunch_event_id' => null,
                        'lunch_start' => '',
                        'lunch_end' => '',
                        'duration_minutes' => 0,
                        'lunch_status' => 'PENDIENTE',
                        'comments' => '',
                        'latest_ot_id' => (int)($r['ot_id'] ?? 0) > 0 ? (int)$r['ot_id'] : null,
                    ];
                } else {
                    // Actualizar datos con el turno real iniciado
                    $operators[$key]['init_id'] = (int)$r['init_id'];
                    $operators[$key]['shift_start'] = $shiftStart;
                    $operators[$key]['shift_end'] = $shiftEnd;
                    $operators[$key]['shift_status'] = $shiftStatus;
                    if ($isSelladora && stripos($operators[$key]['machine_name'], 'embalaje') !== false) {
                        $operators[$key]['machine_name'] = $mName;
                        $operators[$key]['machine_id'] = $mId;
                    }
                    if ((int)($r['ot_id'] ?? 0) > 0 && empty($operators[$key]['latest_ot_id'])) {
                        $operators[$key]['latest_ot_id'] = (int)$r['ot_id'];
                    }
                }

                if (!empty($r['colacion_evt_id']) && !$operators[$key]['has_lunch']) {
                    $operators[$key]['has_lunch'] = true;
                    $operators[$key]['lunch_event_id'] = (int)$r['colacion_evt_id'];
                    $operators[$key]['lunch_start'] = date('H:i', (int)$r['colacion_inicio_ts']);
                    $operators[$key]['lunch_end'] = !empty($r['colacion_fin_ts']) ? date('H:i', (int)$r['colacion_fin_ts']) : '';
                    if (!empty($r['colacion_fin_ts']) && $r['colacion_fin_ts'] > $r['colacion_inicio_ts']) {
                        $operators[$key]['duration_minutes'] = (int)round(($r['colacion_fin_ts'] - $r['colacion_inicio_ts']) / 60);
                        $operators[$key]['lunch_status'] = 'COMPLETADA';
                    } else {
                        $operators[$key]['lunch_status'] = 'EN_CURSO';
                    }
                }
            }
        } catch (Throwable) {}

        // 4b. Vincular directamente TODOS los eventos de colación registrados en prod_worker_ot_events para esta fecha específica
        // Esto garantiza que cualquier operario que haya marcado colación en la máquina aparezca con su horario,
        // incluso si su turno en prod_worker_init viene abierto desde el día anterior o turno noche.
        try {
            $dayStartTs = strtotime($date . ' 00:00:00');
            $dayEndTs = strtotime($date . ' 23:59:59');
            $sqlEvents = "
                SELECT 
                    ev.id AS colacion_evt_id,
                    ev.evt_prod_worker_otid AS ot_id,
                    ev.evt_crtdat AS colacion_inicio_ts,
                    ev.evt_enddat AS colacion_fin_ts,
                    ev.evt_status AS colacion_status,
                    ev.evt_comments AS colacion_comments,
                    wi.id AS init_id,
                    wi.win_wrkid AS worker_id,
                    wi.win_equipoid AS machine_id,
                    COALESCE(eq.equipo_name, 'Sin Máquina') AS machine_name,
                    TRIM(CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, ''))) AS operator_name
                FROM prod_worker_ot_events ev
                INNER JOIN prod_worker_ot ot ON ev.evt_prod_worker_otid = ot.id
                INNER JOIN prod_worker_init wi ON ot.wok_init_id = wi.id
                LEFT JOIN workers w ON wi.win_wrkid = w.id
                LEFT JOIN equipo eq ON wi.win_equipoid = eq.id
                WHERE ev.evt_type = 'pause'
                  AND ev.evt_pause_id = 1
                  AND ev.evt_status > 0
                  AND ev.evt_crtdat BETWEEN :start_ts AND :end_ts
                ORDER BY ev.id ASC
            ";
            $stmtEvts = $this->erpPdo->prepare($sqlEvents);
            $stmtEvts->execute([':start_ts' => $dayStartTs, ':end_ts' => $dayEndTs]);
            foreach ($stmtEvts->fetchAll(PDO::FETCH_ASSOC) as $eRow) {
                $wId = (int)$eRow['worker_id'];
                $key = 'W_' . $wId;
                if (!isset($operators[$key])) {
                    $mId = (int)$eRow['machine_id'];
                    $mName = trim((string)$eRow['machine_name']) ?: ($allMachines[$mId] ?? 'Sin Máquina');
                    $opName = trim((string)$eRow['operator_name']) ?: ('Operario #' . $wId);
                    $operators[$key] = [
                        'key' => $key,
                        'source' => 'ERP_EVENT',
                        'init_id' => (int)$eRow['init_id'],
                        'worker_id' => $wId,
                        'operator_name' => $opName,
                        'machine_id' => $mId,
                        'machine_name' => $mName,
                        'shift_start' => '07:10',
                        'shift_end' => 'En curso',
                        'shift_status' => 'ACTIVO',
                        'has_lunch' => true,
                        'lunch_event_id' => (int)$eRow['colacion_evt_id'],
                        'lunch_start' => date('H:i', (int)$eRow['colacion_inicio_ts']),
                        'lunch_end' => !empty($eRow['colacion_fin_ts']) ? date('H:i', (int)$eRow['colacion_fin_ts']) : '',
                        'duration_minutes' => (!empty($eRow['colacion_fin_ts']) && $eRow['colacion_fin_ts'] > $eRow['colacion_inicio_ts'])
                            ? (int)round(($eRow['colacion_fin_ts'] - $eRow['colacion_inicio_ts']) / 60)
                            : 0,
                        'lunch_status' => !empty($eRow['colacion_fin_ts']) ? 'COMPLETADA' : 'EN_CURSO',
                        'comments' => (string)($eRow['colacion_comments'] ?? ''),
                        'latest_ot_id' => (int)$eRow['ot_id'],
                    ];
                } else {
                    $operators[$key]['has_lunch'] = true;
                    $operators[$key]['lunch_event_id'] = (int)$eRow['colacion_evt_id'];
                    $operators[$key]['lunch_start'] = date('H:i', (int)$eRow['colacion_inicio_ts']);
                    $operators[$key]['lunch_end'] = !empty($eRow['colacion_fin_ts']) ? date('H:i', (int)$eRow['colacion_fin_ts']) : '';
                    if (!empty($eRow['colacion_fin_ts']) && $eRow['colacion_fin_ts'] > $eRow['colacion_inicio_ts']) {
                        $operators[$key]['duration_minutes'] = (int)round(($eRow['colacion_fin_ts'] - $eRow['colacion_inicio_ts']) / 60);
                        $operators[$key]['lunch_status'] = 'COMPLETADA';
                    } else {
                        $operators[$key]['lunch_status'] = 'EN_CURSO';
                    }
                    if (!empty($eRow['colacion_comments'])) {
                        $operators[$key]['comments'] = (string)$eRow['colacion_comments'];
                    }
                    if ((int)$eRow['ot_id'] > 0) {
                        $operators[$key]['latest_ot_id'] = (int)$eRow['ot_id'];
                    }
                    if (empty($operators[$key]['init_id']) && (int)$eRow['init_id'] > 0) {
                        $operators[$key]['init_id'] = (int)$eRow['init_id'];
                    }
                }
            }
        } catch (Throwable) {}

        // 4. Consultar turnos locales en TRZ (production_shift_sessions)
        try {
            $stmt = $this->pdo->prepare("
                SELECT pss.*, pm.name AS machine_name
                FROM production_shift_sessions pss
                LEFT JOIN production_machines pm ON pm.id = pss.machine_id
                WHERE DATE(pss.started_at) = :d
                ORDER BY pss.id DESC
            ");
            $stmt->execute([':d' => $date]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $sRow) {
                $opName = trim((string)$sRow['operator_name']);
                if ($opName === '') continue;
                $mName = trim((string)$sRow['machine_name']) ?: 'Sin Máquina';
                if (stripos($mName, 'supervisor') !== false || stripos($mName, 'mantencion') !== false || stripos($mName, 'mantenimiento') !== false) {
                    continue;
                }
                $key = 'TRZ_' . $sRow['id'];
                $already = false;
                foreach ($operators as $op) {
                    if (strcasecmp($op['operator_name'], $opName) === 0) {
                        $already = true;
                        break;
                    }
                }
                if (!$already) {
                    $operators[$key] = [
                        'key' => $key,
                        'source' => 'TRZ',
                        'init_id' => 0,
                        'worker_id' => 0,
                        'operator_name' => $opName,
                        'machine_id' => (int)$sRow['machine_id'],
                        'machine_name' => $mName,
                        'shift_start' => date('H:i', strtotime($sRow['started_at'])),
                        'shift_end' => $sRow['ended_at'] ? date('H:i', strtotime($sRow['ended_at'])) : 'En curso',
                        'shift_status' => (string)$sRow['status'] === 'ACTIVE' ? 'ACTIVO' : 'CERRADO',
                        'has_lunch' => false,
                        'lunch_event_id' => null,
                        'lunch_start' => '',
                        'lunch_end' => '',
                        'duration_minutes' => 0,
                        'lunch_status' => 'PENDIENTE',
                        'comments' => '',
                        'latest_ot_id' => null,
                    ];
                }
            }
        } catch (Throwable) {}

        // Regla: Los que estén en selladora y embalen se cuentan solo en selladora, no en embalaje
        $workerHasSelladoraRow = [];
        foreach ($operators as $op) {
            if (!empty($op['worker_id']) && stripos((string)$op['machine_name'], 'selladora') !== false) {
                $workerHasSelladoraRow[$op['worker_id']] = true;
            }
        }
        $operators = array_filter($operators, function ($op) use ($workerHasSelladoraRow) {
            if (!empty($op['worker_id']) && isset($workerHasSelladoraRow[$op['worker_id']]) && stripos((string)$op['machine_name'], 'embalaje') !== false) {
                return false;
            }
            return true;
        });

        // Excluir de forma estricta cualquier registro que corresponda a Supervisores, Mantención o personal fuera del área
        $operators = array_filter($operators, function ($op) {
            $wId = (int)($op['worker_id'] ?? 0);
            if (in_array($wId, [27, 68], true)) {
                return false;
            }
            $m = mb_strtoupper((string)($op['machine_name'] ?? ''));
            if (str_contains($m, 'SUPERVISOR') || str_contains($m, 'MANTENCION') || str_contains($m, 'MANTENIMIENTO')) {
                return false;
            }
            $n = mb_strtoupper((string)($op['operator_name'] ?? ''));
            if (str_contains($n, 'SUPERVISOR') || str_contains($n, 'TOTESAUT') || str_contains($n, 'PEROZO') || str_contains($n, 'PEREZ PALOMARES') || str_contains($n, 'NAVESNIK') || str_contains($n, 'HUAMÁN') || str_contains($n, 'HUAMAN') || str_contains($n, 'CORONADO')) {
                return false;
            }
            return true;
        });

        // 6. Evaluar fecha y hora límite (14:00)
        $isToday = ($date === date('Y-m-d'));
        $currentHi = (int)date('Hi');
        $isPastDeadline = (!$isToday && $date < date('Y-m-d')) || ($isToday && $currentHi >= 1400);

        // 7. Vincular colaciones guardadas / editadas
        foreach ($operators as $k => &$op) {
            $saved = null;
            if (!empty($op['init_id']) && !empty($op['worker_id'])) {
                $saved = $savedBreaks['init_' . $op['init_id'] . '_' . $op['worker_id']] ?? null;
            }
            if (!$saved && !empty($op['worker_id'])) {
                $saved = $savedBreaks['wrk_' . $op['worker_id']] ?? null;
            }
            if (!$saved && !empty($op['operator_name'])) {
                $saved = $savedBreaks['name_' . mb_strtolower(trim((string)$op['operator_name']))] ?? null;
            }

            if ($saved) {
                $op['has_lunch'] = true;
                $op['lunch_start'] = date('H:i', strtotime($saved['start_time']));
                $op['lunch_end'] = $saved['end_time'] ? date('H:i', strtotime($saved['end_time'])) : '';
                $op['duration_minutes'] = (int)($saved['duration_minutes'] ?? 0);
                $op['comments'] = (string)($saved['comments'] ?? '');
                $op['lunch_status'] = !empty($op['lunch_end']) ? 'COMPLETADA' : 'EN_CURSO';
                $op['trz_break_id'] = (int)$saved['id'];
            }
            if (!$op['has_lunch']) {
                $op['lunch_status'] = $isPastDeadline ? 'FALTA_CRITICA' : 'PENDIENTE';
            }
        }
        unset($op);

        // Ordenar: primero faltas críticas, luego pendientes, luego en curso y completadas
        uasort($operators, function ($a, $b) {
            $priority = [
                'FALTA_CRITICA' => 1,
                'PENDIENTE' => 2,
                'EN_CURSO' => 3,
                'COMPLETADA' => 4,
            ];
            $pA = $priority[$a['lunch_status']] ?? 5;
            $pB = $priority[$b['lunch_status']] ?? 5;
            if ($pA !== $pB) {
                return $pA <=> $pB;
            }
            $mCmp = strcasecmp((string)$a['machine_name'], (string)$b['machine_name']);
            if ($mCmp !== 0) {
                return $mCmp;
            }
            return strcasecmp((string)$a['operator_name'], (string)$b['operator_name']);
        });

        $total = count($operators);
        $registered = count(array_filter($operators, fn($o) => $o['has_lunch']));
        $missing = $total - $registered;
        $missingOperators = array_values(array_filter($operators, fn($o) => !$o['has_lunch']));

        // Construir lista exhaustiva de máquinas de producción (excluyendo puestos de supervisión y mantención)
        $distinctMachines = [];
        foreach ($allMachines as $m) {
            $upper = mb_strtoupper($m);
            if (!str_contains($upper, 'SUPERVISOR') && !str_contains($upper, 'MANTENCION') && !str_contains($upper, 'MANTENIMIENTO')) {
                $distinctMachines[$m] = $m;
            }
        }
        foreach ($operators as $o) {
            if (!empty($o['machine_name'])) {
                $upper = mb_strtoupper($o['machine_name']);
                if (!str_contains($upper, 'SUPERVISOR') && !str_contains($upper, 'MANTENCION') && !str_contains($upper, 'MANTENIMIENTO')) {
                    $distinctMachines[$o['machine_name']] = $o['machine_name'];
                }
            }
        }
        $machinesList = array_values($distinctMachines);
        natcasesort($machinesList);
        $machinesList = array_values($machinesList);

        return [
            'date' => $date,
            'is_today' => $isToday,
            'is_past_deadline' => $isPastDeadline,
            'total_operators' => $total,
            'registered_count' => $registered,
            'missing_count' => $missing,
            'compliance_percent' => $total > 0 ? round(($registered / $total) * 100, 1) : 100.0,
            'operators' => array_values($operators),
            'missing_operators' => $missingOperators,
            'machines' => $machinesList,
            'all_machines' => $allMachines,
            'all_workers' => $this->getAllPlantWorkers(),
        ];
    }

    /**
     * Retorna la lista de todos los operarios de planta activos (excluyendo supervisores y mantención)
     */
    public function getAllPlantWorkers(): array
    {
        try {
            $stmt = $this->erpPdo->query("
                SELECT w.id, 
                       TRIM(CONCAT(COALESCE(w.wrk_firstname,''), ' ', COALESCE(w.wrk_lastname,''))) AS name,
                       w.wrk_cargoid, wt.type_name AS cargo_name
                FROM workers w
                LEFT JOIN workers_types wt ON w.wrk_cargoid = wt.id
                WHERE w.wrk_status = 1
                  AND w.id NOT IN (27, 68)
                  AND (w.wrk_cargoid IS NULL OR w.wrk_cargoid NOT IN (3, 4, 9, 10))
                  AND (wt.type_name IS NULL OR (wt.type_name NOT LIKE '%Supervisor%' AND wt.type_name NOT LIKE '%Mantenci%' AND wt.type_name NOT LIKE '%Mantenim%'))
                ORDER BY w.wrk_firstname ASC, w.wrk_lastname ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Registra o actualiza el horario de colación de un operario individual.
     */
    public function saveOperatorLunchBreak(
        string $date,
        string $operatorName,
        string $startTime,
        string $endTime,
        int $workerId = 0,
        int $initId = 0,
        int $machineId = 0,
        string $machineName = '',
        ?string $comments = null,
        string $createdBy = ''
    ): array {
        $this->ensureOperatorLunchBreaksTable();

        $operatorName = trim($operatorName);
        $startTime = trim($startTime);
        $endTime = trim($endTime);
        if ($operatorName === '' || $startTime === '') {
            return ['ok' => false, 'error' => 'Operador y hora de inicio son requeridos.'];
        }

        $fullStart = "$date $startTime:00";
        $fullEnd = $endTime !== '' ? "$date $endTime:00" : null;
        $startTs = strtotime($fullStart);
        $endTs = $fullEnd ? strtotime($fullEnd) : null;
        $duration = ($endTs && $endTs > $startTs) ? (int)round(($endTs - $startTs) / 60) : 0;

        // 1. Sincronizar en ERP prod_worker_ot_events si el operario tiene OT o turno
        $erpEventId = null;
        try {
            $targetOtId = 0;
            if ($initId > 0) {
                $stmtOt = $this->erpPdo->prepare("SELECT id FROM prod_worker_ot WHERE wok_init_id = :init_id ORDER BY id DESC LIMIT 1");
                $stmtOt->execute([':init_id' => $initId]);
                $targetOtId = (int)$stmtOt->fetchColumn();
            }
            if ($targetOtId <= 0 && $workerId > 0) {
                list($y, $m, $d) = explode('-', $date);
                $stmtOt = $this->erpPdo->prepare("
                    SELECT ot.id 
                    FROM prod_worker_ot ot
                    INNER JOIN prod_worker_init wi ON ot.wok_init_id = wi.id
                    WHERE wi.win_wrkid = :wrkid AND wi.win_day = :d AND wi.win_month = :m AND wi.win_year = :y
                    ORDER BY ot.id DESC LIMIT 1
                ");
                $stmtOt->execute([':wrkid' => $workerId, ':d' => (int)$d, ':m' => (int)$m, ':y' => (int)$y]);
                $targetOtId = (int)$stmtOt->fetchColumn();
            }

            if ($targetOtId > 0) {
                $stmtEv = $this->erpPdo->prepare("
                    SELECT id FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :otid AND evt_pause_id = 1 AND evt_type = 'pause'
                    ORDER BY id DESC LIMIT 1
                ");
                $stmtEv->execute([':otid' => $targetOtId]);
                $existingEvtId = (int)$stmtEv->fetchColumn();

                if ($existingEvtId > 0) {
                    $upd = $this->erpPdo->prepare("
                        UPDATE prod_worker_ot_events
                        SET evt_crtdat = :start_ts, evt_enddat = :end_ts, evt_status = 1, evt_comments = :comments
                        WHERE id = :id
                    ");
                    $upd->execute([
                        ':start_ts' => $startTs,
                        ':end_ts' => $endTs ?: $startTs,
                        ':comments' => $comments ?: 'Colación registrada por supervisor',
                        ':id' => $existingEvtId,
                    ]);
                    $erpEventId = $existingEvtId;
                } else {
                    $ins = $this->erpPdo->prepare("
                        INSERT INTO prod_worker_ot_events
                            (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments, evt_pause_id)
                        VALUES
                            (:otid, 0, :start_ts, :end_ts, 1, 'pause', :comments, 1)
                    ");
                    $ins->execute([
                        ':otid' => $targetOtId,
                        ':start_ts' => $startTs,
                        ':end_ts' => $endTs ?: $startTs,
                        ':comments' => $comments ?: 'Colación registrada por supervisor',
                    ]);
                    $erpEventId = (int)$this->erpPdo->lastInsertId();
                }
            }
        } catch (Throwable) {}

        // 2. Guardar en base local TRZ production_operator_lunch_breaks
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM production_operator_lunch_breaks
                WHERE shift_date = :d AND (
                    (:wrkid > 0 AND worker_id = :wrkid)
                    OR (:init_id > 0 AND init_id = :init_id)
                    OR (:wrkid <= 0 AND :init_id <= 0 AND operator_name = :opname)
                )
                LIMIT 1
            ");
            $stmt->execute([
                ':d' => $date,
                ':init_id' => $initId,
                ':wrkid' => $workerId,
                ':opname' => $operatorName,
            ]);
            $existingBreakId = (int)$stmt->fetchColumn();

            if ($existingBreakId > 0) {
                $upd = $this->pdo->prepare("
                    UPDATE production_operator_lunch_breaks
                    SET start_time = :st, end_time = :et, duration_minutes = :dur,
                        comments = :comm,
                        machine_id = COALESCE(:mid, machine_id),
                        machine_name = COALESCE(:mname, machine_name),
                        worker_id = COALESCE(:wrkid, worker_id),
                        init_id = COALESCE(:initid, init_id),
                        erp_event_id = COALESCE(:erpid, erp_event_id),
                        created_by = :cby
                    WHERE id = :id
                ");
                $upd->execute([
                    ':st' => $fullStart,
                    ':et' => $fullEnd,
                    ':dur' => $duration,
                    ':comm' => $comments,
                    ':mid' => $machineId > 0 ? $machineId : null,
                    ':mname' => $machineName ?: null,
                    ':wrkid' => $workerId > 0 ? $workerId : null,
                    ':initid' => $initId > 0 ? $initId : null,
                    ':erpid' => $erpEventId,
                    ':cby' => $createdBy ?: null,
                    ':id' => $existingBreakId,
                ]);
                $savedId = $existingBreakId;
            } else {
                $ins = $this->pdo->prepare("
                    INSERT INTO production_operator_lunch_breaks
                        (shift_date, init_id, worker_id, operator_name, machine_id, machine_name, start_time, end_time, duration_minutes, erp_event_id, comments, created_by)
                    VALUES
                        (:d, :init_id, :wrkid, :opname, :mid, :mname, :st, :et, :dur, :erpid, :comm, :cby)
                ");
                $ins->execute([
                    ':d' => $date,
                    ':init_id' => $initId > 0 ? $initId : null,
                    ':wrkid' => $workerId > 0 ? $workerId : null,
                    ':opname' => $operatorName,
                    ':mid' => $machineId > 0 ? $machineId : null,
                    ':mname' => $machineName ?: null,
                    ':st' => $fullStart,
                    ':et' => $fullEnd,
                    ':dur' => $duration,
                    ':erpid' => $erpEventId,
                    ':comm' => $comments,
                    ':cby' => $createdBy ?: null,
                ]);
                $savedId = (int)$this->pdo->lastInsertId();
            }

            return ['ok' => true, 'id' => $savedId, 'erp_event_id' => $erpEventId];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Guarda colación de forma masiva para un conjunto de operarios seleccionados.
     *
     * @param array<int, array<string, mixed>> $items Lista de operarios
     */
    public function saveBulkOperatorLunchBreaks(
        array $items,
        string $date,
        string $startTime,
        string $endTime,
        ?string $comments = null,
        string $createdBy = ''
    ): array {
        $count = 0;
        $errors = [];
        foreach ($items as $item) {
            $opName = trim((string)($item['operator_name'] ?? ''));
            if ($opName === '') continue;
            $res = $this->saveOperatorLunchBreak(
                $date,
                $opName,
                $startTime,
                $endTime,
                (int)($item['worker_id'] ?? 0),
                (int)($item['init_id'] ?? 0),
                (int)($item['machine_id'] ?? 0),
                (string)($item['machine_name'] ?? ''),
                $comments,
                $createdBy
            );
            if ($res['ok']) {
                $count++;
            } else {
                $errors[] = $opName . ': ' . ($res['error'] ?? 'Error desconocido');
            }
        }
        return ['ok' => $count > 0, 'updated_count' => $count, 'errors' => $errors];
    }

    /**
     * Guarda colación de forma masiva para todos los operarios de una máquina en la fecha.
     */
    public function saveMachineLunchBreaks(
        string $date,
        string $machineName,
        string $startTime,
        string $endTime,
        ?string $comments = null,
        string $createdBy = ''
    ): array {
        $report = $this->getOperatorLunchReport($date);
        $machineName = trim($machineName);
        $targetOps = [];
        foreach ($report['operators'] as $op) {
            if (strcasecmp((string)$op['machine_name'], $machineName) === 0) {
                $targetOps[] = $op;
            }
        }

        if (empty($targetOps)) {
            return ['ok' => false, 'error' => "No se encontraron operarios en la máquina '{$machineName}' para el día {$date}."];
        }

        return $this->saveBulkOperatorLunchBreaks($targetOps, $date, $startTime, $endTime, $comments, $createdBy);
    }

    /**
     * Carga masiva de colaciones para un rango de fechas (Fecha Inicio a Fecha Fin),
     * ya sea por máquina o por lista de operarios seleccionados.
     *
     * REGLAS CRÍTICAS:
     * 1. Solo se cargará en días donde el operario haya INICIADO TURNO de trabajo efectivamente.
     *    Si no vino a trabajar o no tiene turno iniciado ese día, no se registra nada.
     * 2. Solo se cargará si NO tienen colación ya registrada para ese día (no sobreescribe colaciones previas).
     */
    public function saveBulkLunchDateRange(
        array $targetWorkerIds,
        ?string $targetMachineName,
        string $startDate,
        string $endDate,
        string $startTime,
        string $endTime,
        ?string $comments = null,
        string $currentUser = ''
    ): array {
        $this->ensureOperatorLunchBreaksTable();

        $startDate = trim($startDate);
        $endDate = trim($endDate);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endDate = $startDate;
        }
        if ($startDate > $endDate) {
            $tmp = $startDate;
            $startDate = $endDate;
            $endDate = $tmp;
        }

        $startTime = trim($startTime) ?: '12:00';
        $endTime = trim($endTime) ?: '12:30';
        $targetMachineName = trim((string)$targetMachineName);
        if (strcasecmp($targetMachineName, 'Todas las Máquinas') === 0 || strcasecmp($targetMachineName, 'all') === 0) {
            $targetMachineName = '';
        }

        $startDt = new DateTime($startDate);
        $endDt = new DateTime($endDate);
        $endDt->modify('+1 day');
        $period = new DatePeriod($startDt, new DateInterval('P1D'), $endDt);

        // Consultar colaciones ya registradas en TRZ dentro del rango para no duplicar ni sobreescribir
        $existingBreaks = [];
        try {
            $stmtTrz = $this->pdo->prepare("
                SELECT shift_date, worker_id, operator_name 
                FROM production_operator_lunch_breaks 
                WHERE shift_date BETWEEN :s AND :e
            ");
            $stmtTrz->execute([':s' => $startDate, ':e' => $endDate]);
            foreach ($stmtTrz->fetchAll(PDO::FETCH_ASSOC) as $eb) {
                if (!empty($eb['worker_id'])) {
                    $existingBreaks[$eb['shift_date'] . '_w_' . $eb['worker_id']] = true;
                }
                if (!empty($eb['operator_name'])) {
                    $existingBreaks[$eb['shift_date'] . '_n_' . mb_strtolower(trim((string)$eb['operator_name']))] = true;
                }
            }
        } catch (Throwable) {}

        $targetWorkerIdsMap = [];
        foreach ($targetWorkerIds as $twid) {
            $id = (int)$twid;
            if ($id > 0) $targetWorkerIdsMap[$id] = true;
        }

        $updatedCount = 0;
        $daysProcessed = 0;
        $skippedAlreadyHad = 0;

        foreach ($period as $dt) {
            $currDate = $dt->format('Y-m-d');
            $daysProcessed++;
            $y = (int)$dt->format('Y');
            $m = (int)$dt->format('m');
            $d = (int)$dt->format('d');

            // 1. Identificar qué operarios iniciaron turno ese día en SELLADORA
            $workersOnSelladora = [];
            try {
                $stmtSell = $this->erpPdo->prepare("
                    SELECT wi.win_wrkid, eq.id AS eq_id, eq.equipo_name 
                    FROM prod_worker_init wi
                    LEFT JOIN equipo eq ON wi.win_equipoid = eq.id
                    WHERE wi.win_year = :y AND wi.win_month = :m AND wi.win_day = :d
                      AND eq.equipo_name LIKE '%SELLADORA%'
                ");
                $stmtSell->execute([':y' => $y, ':m' => $m, ':d' => $d]);
                foreach ($stmtSell->fetchAll(PDO::FETCH_ASSOC) as $sRow) {
                    $workersOnSelladora[(int)$sRow['win_wrkid']] = [
                        'machine_id' => (int)$sRow['eq_id'],
                        'machine_name' => trim((string)$sRow['equipo_name']),
                    ];
                }
            } catch (Throwable) {}

            $dayCandidates = [];

            // 2. Consultar turnos asignados en ERP para este día (turnos_config_assign)
            try {
                $stmtAssign = $this->erpPdo->prepare("
                    SELECT 
                        0 AS init_id,
                        a.assign_worker_id AS worker_id,
                        eq.id AS machine_id,
                        COALESCE(eq.equipo_name, 'Sin Máquina') AS machine_name,
                        TRIM(CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, ''))) AS operator_name,
                        w.wrk_cargoid,
                        wt.type_name AS cargo_name,
                        NULL AS ot_id,
                        NULL AS colacion_evt_id
                    FROM turnos_config_assign a
                    JOIN workers w ON a.assign_worker_id = w.id
                    LEFT JOIN workers_types wt ON w.wrk_cargoid = wt.id
                    LEFT JOIN equipo eq ON a.assign_equipoaid = eq.id
                    WHERE a.assign_year = :y AND a.assign_month = :m AND a.assign_day = :d
                      AND (a.confirm_falta_act IS NULL OR a.confirm_falta_act = 0)
                      AND (eq.equipo_name IS NULL OR (eq.equipo_name NOT LIKE '%SUPERVISOR%' AND eq.equipo_name NOT LIKE '%MANTENCION%'))
                      AND (w.wrk_cargoid IS NULL OR w.wrk_cargoid NOT IN (3, 4, 9, 10))
                      AND (wt.type_name IS NULL OR (wt.type_name NOT LIKE '%Supervisor%' AND wt.type_name NOT LIKE '%Mantenci%' AND wt.type_name NOT LIKE '%Mantenim%'))
                      AND (a.assign_worker_type IS NULL OR a.assign_worker_type NOT LIKE '%supervisor%')
                      AND (a.assign_worker_id IS NULL OR a.assign_worker_id NOT IN (27, 68))
                    ORDER BY a.id ASC
                ");
                $stmtAssign->execute([':y' => $y, ':m' => $m, ':d' => $d]);
                foreach ($stmtAssign->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $wId = (int)$row['worker_id'];
                    $mName = trim((string)$row['machine_name']);
                    if (isset($workersOnSelladora[$wId]) && stripos($mName, 'embalaje') !== false) {
                        $mName = $workersOnSelladora[$wId]['machine_name'];
                        $row['machine_name'] = $mName;
                        $row['machine_id'] = $workersOnSelladora[$wId]['machine_id'];
                    }
                    $dayCandidates[$wId] = $row;
                }
            } catch (Throwable) {}

            // 3. Consultar turnos físicos iniciados en ERP para este día (prod_worker_init)
            try {
                $sql = "
                    SELECT 
                        wi.id AS init_id,
                        wi.win_wrkid AS worker_id,
                        eq.id AS machine_id,
                        COALESCE(eq.equipo_name, 'Sin Máquina') AS machine_name,
                        TRIM(CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, ''))) AS operator_name,
                        w.wrk_cargoid,
                        wt.type_name AS cargo_name,
                        ot.id AS ot_id,
                        ev.id AS colacion_evt_id
                    FROM prod_worker_init wi
                    JOIN workers w ON wi.win_wrkid = w.id
                    LEFT JOIN workers_types wt ON w.wrk_cargoid = wt.id
                    LEFT JOIN equipo eq ON wi.win_equipoid = eq.id
                    LEFT JOIN prod_worker_ot ot ON ot.wok_init_id = wi.id
                    LEFT JOIN prod_worker_ot_events ev ON ev.evt_prod_worker_otid = ot.id 
                         AND ev.evt_pause_id = 1 AND ev.evt_type = 'pause' AND ev.evt_status > 0
                    WHERE wi.win_year = :y AND wi.win_month = :m AND wi.win_day = :d
                      AND (eq.equipo_name IS NULL OR (eq.equipo_name NOT LIKE '%SUPERVISOR%' AND eq.equipo_name NOT LIKE '%MANTENCION%'))
                      AND (w.wrk_cargoid IS NULL OR w.wrk_cargoid NOT IN (3, 4, 9, 10))
                      AND (wt.type_name IS NULL OR (wt.type_name NOT LIKE '%Supervisor%' AND wt.type_name NOT LIKE '%Mantenci%' AND wt.type_name NOT LIKE '%Mantenim%'))
                      AND (wi.win_wrkid IS NULL OR wi.win_wrkid NOT IN (27, 68))
                    ORDER BY wi.id DESC
                ";
                $stmt = $this->erpPdo->prepare($sql);
                $stmt->execute([':y' => $y, ':m' => $m, ':d' => $d]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $wId = (int)$row['worker_id'];
                    $mName = trim((string)$row['machine_name']);

                    // Regla Selladora vs Embalaje
                    if (isset($workersOnSelladora[$wId]) && stripos($mName, 'embalaje') !== false) {
                        $mName = $workersOnSelladora[$wId]['machine_name'];
                        $row['machine_name'] = $mName;
                        $row['machine_id'] = $workersOnSelladora[$wId]['machine_id'];
                    }

                    $dayCandidates[$wId] = $row;
                }
            } catch (Throwable) {}

            // 4. Consultar sesiones en TRZ para este día
            try {
                $stmtTrzSess = $this->pdo->prepare("
                    SELECT pss.*, pm.name AS machine_name
                    FROM production_shift_sessions pss
                    LEFT JOIN production_machines pm ON pm.id = pss.machine_id
                    WHERE DATE(pss.started_at) = :d
                ");
                $stmtTrzSess->execute([':d' => $currDate]);
                foreach ($stmtTrzSess->fetchAll(PDO::FETCH_ASSOC) as $sRow) {
                    $opName = trim((string)$sRow['operator_name']);
                    if ($opName === '') continue;
                    $mName = trim((string)$sRow['machine_name']);
                    if (stripos($mName, 'supervisor') !== false || stripos($mName, 'mantencion') !== false) {
                        continue;
                    }
                    $swId = (int)($sRow['worker_id'] ?? 0);
                    $trzKey = $swId > 0 ? $swId : ('trz_' . mb_strtolower($opName));
                    if (!isset($dayCandidates[$trzKey])) {
                        $dayCandidates[$trzKey] = [
                            'init_id' => 0,
                            'worker_id' => $swId,
                            'machine_id' => (int)($sRow['machine_id'] ?? 0),
                            'machine_name' => $mName,
                            'operator_name' => $opName,
                            'ot_id' => null,
                            'colacion_evt_id' => null,
                        ];
                    }
                }
            } catch (Throwable) {}

            // Filtrar y aplicar colación a los candidatos del día que iniciaron turno
            foreach ($dayCandidates as $cand) {
                $wId = (int)($cand['worker_id'] ?? 0);
                $opName = trim((string)$cand['operator_name']);
                $mName = trim((string)$cand['machine_name']);

                // Si se especificó lista de operarios, comprobar coincidencia
                if (!empty($targetWorkerIdsMap)) {
                    if ($wId <= 0 || !isset($targetWorkerIdsMap[$wId])) {
                        continue;
                    }
                }

                // Si se especificó máquina objetivo, comprobar coincidencia
                if ($targetMachineName !== '') {
                    if (stripos($mName, $targetMachineName) === false && stripos($targetMachineName, $mName) === false) {
                        continue;
                    }
                }

                // Si NO se especificaron operarios puntuales (ej. asignación a toda la máquina), no sobreescribir si ya tenía colación
                if (empty($targetWorkerIdsMap)) {
                    $alreadyTrz = ($wId > 0 && isset($existingBreaks[$currDate . '_w_' . $wId])) ||
                                  isset($existingBreaks[$currDate . '_n_' . mb_strtolower($opName)]);
                    $alreadyErp = !empty($cand['colacion_evt_id']);
                    if ($alreadyTrz || $alreadyErp) {
                        $skippedAlreadyHad++;
                        continue;
                    }
                }

                // Guardar colación
                $res = $this->saveOperatorLunchBreak(
                    $currDate,
                    $opName,
                    $startTime,
                    $endTime,
                    $wId,
                    (int)($cand['init_id'] ?? 0),
                    (int)($cand['machine_id'] ?? 0),
                    $mName,
                    $comments,
                    $currentUser
                );

                if ($res['ok']) {
                    $updatedCount++;
                    if ($wId > 0) {
                        $existingBreaks[$currDate . '_w_' . $wId] = true;
                    }
                    $existingBreaks[$currDate . '_n_' . mb_strtolower($opName)] = true;
                }
            }
        }

        return [
            'ok' => true,
            'updated_count' => $updatedCount,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days_processed' => $daysProcessed,
            'skipped_already_had' => $skippedAlreadyHad,
        ];
    }

    /**
     * Resumen completo de planta para un día específico (por defecto, ayer).
     * Incluye: producción total, mermas por proceso y máquina, eventos y paradas operativas,
     * mermas críticas (>5%), y control de asistencia/colaciones.
     *
     * @param string $date Fecha YYYY-MM-DD
     * @return array<string, mixed>
     */
    public function getDailyPlantSummary(string $date = ''): array
    {
        $date = trim($date);
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d', strtotime('-1 day'));
        }

        $prevDate = date('Y-m-d', strtotime("$date -1 day"));
        $nextDate = date('Y-m-d', strtotime("$date +1 day"));
        $isYesterday = ($date === date('Y-m-d', strtotime('-1 day')));
        $isToday = ($date === date('Y-m-d'));

        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $ts = strtotime($date);
        $dayOfWeek = $dias[(int)date('w', $ts)];
        $dayNum = date('d', $ts);
        $monthName = $meses[(int)date('n', $ts)];
        $yearNum = date('Y', $ts);
        $formattedDate = "$dayOfWeek, $dayNum de $monthName de $yearNum";

        $startTs = strtotime("$date 00:00:00");
        $endTs = strtotime("$date 23:59:59");

        // 1. Consultar OTs de producción del día
        $prodRows = [];
        try {
            $sqlProd = "
                SELECT 
                    t1.id AS ot_id,
                    t1.wok_status,
                    t1.wok_crtdat,
                    t1.wok_enddat,
                    t4.prd_number AS ot_number,
                    t2.ag_reqid AS cc_number,
                    COALESCE(t12.cust_name, 'Cliente no asignado') AS customer_name,
                    COALESCE(t11.item_title, 'Sin descripción') AS product_name,
                    COALESCE(t7.equipo_name, 'Sin Máquina') AS machine_name,
                    t7.id AS machine_id,
                    t7.equipo_type_id,
                    CASE 
                        WHEN t7.equipo_type_id = 7 THEN 'Flexografía'
                        WHEN t7.equipo_type_id = 8 THEN 'Corte y Sellado'
                        WHEN t7.equipo_type_id = 11 AND t7.id != 36 THEN 'Serigrafía'
                        WHEN t7.equipo_type_id = 22 OR t7.id = 36 THEN 'Pulpo Serigráfico'
                        WHEN t7.equipo_type_id = 15 THEN 'Embalaje'
                        WHEN t7.equipo_type_id = 12 THEN 'Rebobinado'
                        ELSE 'Otros'
                    END AS process_name,
                    CASE 
                        WHEN t7.equipo_type_id = 7 THEN 'flexo'
                        WHEN t7.equipo_type_id = 8 THEN 'sellado'
                        WHEN t7.equipo_type_id = 11 AND t7.id != 36 THEN 'seri'
                        WHEN t7.equipo_type_id = 22 OR t7.id = 36 THEN 'pulpo'
                        WHEN t7.equipo_type_id = 15 THEN 'embalaje'
                        WHEN t7.equipo_type_id = 12 THEN 'rebo'
                        ELSE 'otros'
                    END AS process_code,
                    TRIM(CONCAT(COALESCE(w.wrk_firstname,''), ' ', COALESCE(w.wrk_lastname,''))) AS operator_name,
                    COALESCE(pe.produced_units, 0) AS produced_units,
                    COALESCE(dw.waste_units, 0) AS waste_units,
                    COALESCE(dw.waste_kg, 0) AS waste_kg,
                    COALESCE(pe.produced_meters, 0) AS produced_meters,
                    CASE 
                        WHEN t1.wok_enddat > t1.wok_crtdat THEN ROUND((t1.wok_enddat - t1.wok_crtdat)/3600, 2)
                        ELSE 0 
                    END AS duration_hours
                FROM prod_worker_ot t1
                INNER JOIN prod_agenda t2 ON t1.wok_ag_id = t2.id
                INNER JOIN prod_worker_init t3 ON t1.wok_init_id = t3.id
                INNER JOIN prod_header t4 ON t2.ag_prdid = t4.id
                LEFT JOIN equipo t7 ON t3.win_equipoid = t7.id
                LEFT JOIN workers w ON t3.win_wrkid = w.id
                LEFT JOIN orders t9 ON t2.ag_reqid = t9.id
                LEFT JOIN orders_items t10 ON t9.id = t10.req_id
                LEFT JOIN item t11 ON t10.item_id = t11.id
                LEFT JOIN customer t12 ON t9.req_cust_id = t12.id
                LEFT JOIN (
                    SELECT evt_prod_worker_otid,
                           SUM(CASE WHEN LOWER(evt_type) IN ('prod', 'production', 'prodsericolor') OR evt_status > 0 THEN evt_amount ELSE 0 END) AS produced_units,
                           SUM(COALESCE(evt_amount_metros_lineales, 0)) AS produced_meters
                    FROM prod_worker_ot_events
                    GROUP BY evt_prod_worker_otid
                ) pe ON pe.evt_prod_worker_otid = t1.id
                LEFT JOIN (
                    SELECT e.evt_prod_worker_otid AS wok_id,
                           SUM(CASE WHEN LOWER(d.evt_type) = 'merma' THEN d.evt_amount ELSE 0 END) AS waste_units,
                           SUM(CASE WHEN LOWER(d.evt_type) = 'merma' THEN d.evt_kgstounits ELSE 0 END) AS waste_kg
                    FROM prod_worker_ot_defectunits d
                    INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                    WHERE d.evt_status > 0
                    GROUP BY e.evt_prod_worker_otid
                ) dw ON dw.wok_id = t1.id
                WHERE t1.wok_status > 0 AND t1.wok_crtdat BETWEEN :s AND :e
                ORDER BY process_name ASC, t7.equipo_name ASC, t1.id ASC
            ";
            $stmt = $this->erpPdo->prepare($sqlProd);
            $stmt->execute([':s' => $startTs, ':e' => $endTs]);
            $prodRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {}

        // 2. Consultar eventos, paradas, montajes y pausas
        $stopsRows = [];
        try {
            $sqlStops = "
                SELECT 
                    e.id,
                    e.evt_type,
                    e.evt_pause_id,
                    COALESCE(ppt.pause_name, CASE WHEN e.evt_type = 'apertura' THEN 'Cambio de Formato / Montaje' ELSE 'Parada Operativa' END) AS stop_reason,
                    e.evt_crtdat,
                    e.evt_enddat,
                    CASE WHEN e.evt_enddat > e.evt_crtdat THEN ROUND((e.evt_enddat - e.evt_crtdat)/60) ELSE 0 END AS duration_minutes,
                    e.evt_comments,
                    eq.equipo_name AS machine_name,
                    TRIM(CONCAT(COALESCE(w.wrk_firstname,''), ' ', COALESCE(w.wrk_lastname,''))) AS operator_name,
                    h.prd_number AS ot_number
                FROM prod_worker_ot_events e
                LEFT JOIN prod_worker_ot ot ON e.evt_prod_worker_otid = ot.id
                LEFT JOIN prod_agenda ag ON ot.wok_ag_id = ag.id
                LEFT JOIN prod_header h ON ag.ag_prdid = h.id
                LEFT JOIN prod_worker_init wi ON ot.wok_init_id = wi.id
                LEFT JOIN workers w ON wi.win_wrkid = w.id
                LEFT JOIN equipo eq ON ag.ag_equipo_id = eq.id
                LEFT JOIN prod_pause_types ppt ON e.evt_pause_id = ppt.id
                WHERE e.evt_crtdat BETWEEN :s AND :e
                  AND (e.evt_type IN ('pause', 'apertura') OR e.evt_pause_id > 0)
                ORDER BY e.evt_crtdat ASC
            ";
            $stmtStops = $this->erpPdo->prepare($sqlStops);
            $stmtStops->execute([':s' => $startTs, ':e' => $endTs]);
            $stopsRows = $stmtStops->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {}

        // 3. Consultar asistencia y control de colaciones
        $lunchReport = $this->getOperatorLunchReport($date);

        // 4. Consultar despachos comerciales y su valorización en dinero
        $dispatchRows = [];
        $totalDispatchedUnits = 0.0;
        $totalDispatchedMoney = 0.0;
        $distinctDespachoIds = [];
        $usedDeliveryTotals = [];

        try {
            $sqlDisp = "
                SELECT 
                    d.id AS despacho_id,
                    d.numero_documento,
                    d.tipo_documento,
                    d.hora_ingreso,
                    d.hora_salida,
                    d.estado,
                    d.observacion,
                    COALESCE(c.cust_company, c.cust_name, 'Cliente N/D') AS customer_name,
                    o.req_number AS cc_number,
                    od.dlv_docnum AS guia_factura,
                    od.dlv_total_netto,
                    od.dlv_total_brutto,
                    i.item_number_prod,
                    COALESCE(i.item_title, dd.descripcion, 'Producto N/D') AS item_name,
                    dd.salida AS dispatched_units,
                    dd.cantidad AS declared_units,
                    oi.item_sellprice_netto,
                    oi.item_sellprice_netto_dsc,
                    oi.item_amount,
                    i.item_sellprice_netto AS item_catalog_price,
                    t1.trans_name AS transport_company,
                    CONCAT(COALESCE(tc.transports_chofer_nombre, ''), ' ', COALESCE(tc.transports_chofer_paterno, '')) AS driver_name,
                    tv.transports_vh_patente AS vehicle_plate
                FROM despacho d
                INNER JOIN detalle_despacho dd ON d.id = dd.id_despacho
                LEFT JOIN customer c ON c.id = d.id_cliente
                LEFT JOIN transports_chofer tc ON tc.id = d.id_chofer
                LEFT JOIN transports_vehiculo tv ON tv.id = d.id_patente
                LEFT JOIN transports t1 ON t1.id = d.id_transporte
                LEFT JOIN item i ON i.id = dd.id_item
                LEFT JOIN orders_delivery od ON d.numero_documento = od.id
                LEFT JOIN orders o ON o.id = od.dlv_order_id
                LEFT JOIN orders_items oi ON oi.req_id = o.id AND oi.item_id = dd.id_item
                WHERE d.fecha_ingreso BETWEEN :s AND :e
                ORDER BY d.id DESC, dd.id ASC
            ";
            $stmtDisp = $this->erpPdo->prepare($sqlDisp);
            $stmtDisp->execute([':s' => $startTs, ':e' => $endTs]);
            $rawDisp = $stmtDisp->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rawDisp as $dr) {
                $units = (float)($dr['dispatched_units'] ?? $dr['declared_units'] ?? 0);
                if ($units <= 0 && (float)($dr['declared_units'] ?? 0) > 0) {
                    $units = (float)$dr['declared_units'];
                }

                $unitPrice = 0.0;
                if (!empty($dr['item_sellprice_netto']) && (float)$dr['item_sellprice_netto'] > 0) {
                    $unitPrice = (float)$dr['item_sellprice_netto'];
                } elseif (!empty($dr['item_amount']) && (float)$dr['item_amount'] > 0 && !empty($dr['item_sellprice_netto_dsc'])) {
                    $unitPrice = (float)$dr['item_sellprice_netto_dsc'] / (float)$dr['item_amount'];
                } elseif (!empty($dr['item_catalog_price']) && (float)$dr['item_catalog_price'] > 0) {
                    $unitPrice = (float)$dr['item_catalog_price'];
                }

                $lineMoney = round($units * $unitPrice, 2);
                if ($lineMoney <= 0 && !empty($dr['dlv_total_netto']) && (float)$dr['dlv_total_netto'] > 0) {
                    $dlvId = (string)($dr['numero_documento'] ?? '');
                    if (!isset($usedDeliveryTotals[$dlvId])) {
                        $lineMoney = (float)$dr['dlv_total_netto'];
                        $usedDeliveryTotals[$dlvId] = true;
                        if ($units > 0 && $unitPrice <= 0) {
                            $unitPrice = round($lineMoney / $units, 2);
                        }
                    }
                }

                $docNum = trim((string)($dr['guia_factura'] ?? ''));
                if ($docNum === '' || $docNum === '0') {
                    $docNum = trim((string)($dr['numero_documento'] ?? ''));
                }

                $dr['doc_number'] = $docNum;
                $dr['dispatched_units'] = $units;
                $dr['unit_price'] = $unitPrice;
                $dr['total_money'] = $lineMoney;
                $dr['departure_time'] = !empty($dr['hora_salida']) && (string)$dr['hora_salida'] !== '00:00:00' ? substr((string)$dr['hora_salida'], 0, 5) : (!empty($dr['hora_ingreso']) ? substr((string)$dr['hora_ingreso'], 0, 5) : '—');

                $dispatchRows[] = $dr;
                $totalDispatchedUnits += $units;
                $totalDispatchedMoney += $lineMoney;
                $distinctDespachoIds[(int)$dr['despacho_id']] = true;
            }
        } catch (Throwable) {}

        // 5. Procesar y estructurar datos
        $totalGoodUnits = 0.0;
        $totalWasteUnits = 0.0;
        $totalWasteKg = 0.0;
        $totalMeters = 0.0;
        $totalProdHours = 0.0;

        $byProcess = [
            'Corte y Sellado' => ['title' => 'Corte y Sellado', 'icon' => '✂️', 'code' => 'sellado', 'produced' => 0.0, 'waste' => 0.0, 'waste_kg' => 0.0, 'ots_count' => 0],
            'Flexografía' => ['title' => 'Flexografía', 'icon' => '🎨', 'code' => 'flexo', 'produced' => 0.0, 'waste' => 0.0, 'waste_kg' => 0.0, 'ots_count' => 0],
            'Serigrafía' => ['title' => 'Serigrafía', 'icon' => '🖌️', 'code' => 'seri', 'produced' => 0.0, 'waste' => 0.0, 'waste_kg' => 0.0, 'ots_count' => 0],
            'Pulpo Serigráfico' => ['title' => 'Pulpo Serigráfico', 'icon' => '🐙', 'code' => 'pulpo', 'produced' => 0.0, 'waste' => 0.0, 'waste_kg' => 0.0, 'ots_count' => 0],
            'Embalaje' => ['title' => 'Embalaje', 'icon' => '📦', 'code' => 'embalaje', 'produced' => 0.0, 'waste' => 0.0, 'waste_kg' => 0.0, 'ots_count' => 0],
            'Rebobinado' => ['title' => 'Rebobinado', 'icon' => '🔄', 'code' => 'rebo', 'produced' => 0.0, 'waste' => 0.0, 'waste_kg' => 0.0, 'ots_count' => 0],
        ];

        $byMachine = [];
        $criticalWasteOrders = [];
        $activeMachines = [];
        $activeOperators = [];

        foreach ($prodRows as &$r) {
            $g = (float)$r['produced_units'];
            $w = (float)$r['waste_units'];
            $wKg = (float)$r['waste_kg'];
            $m = (float)$r['produced_meters'];
            $hrs = (float)$r['duration_hours'];
            $tot = $g + $w;
            $wRate = $tot > 0 ? round(($w / $tot) * 100, 2) : 0.0;
            $r['waste_rate'] = $wRate;

            $totalGoodUnits += $g;
            $totalWasteUnits += $w;
            $totalWasteKg += $wKg;
            $totalMeters += $m;
            $totalProdHours += $hrs;

            $mName = trim((string)$r['machine_name']) ?: 'Sin Máquina';
            $pName = trim((string)$r['process_name']) ?: 'Otros';
            $opName = trim((string)$r['operator_name']);

            $activeMachines[$mName] = true;
            if ($opName !== '') {
                $activeOperators[$opName] = true;
            }

            // Agrupación por Proceso
            if (!isset($byProcess[$pName])) {
                $byProcess[$pName] = ['title' => $pName, 'icon' => '⚙️', 'code' => $r['process_code'], 'produced' => 0.0, 'waste' => 0.0, 'waste_kg' => 0.0, 'ots_count' => 0];
            }
            $byProcess[$pName]['produced'] += $g;
            $byProcess[$pName]['waste'] += $w;
            $byProcess[$pName]['waste_kg'] += $wKg;
            $byProcess[$pName]['ots_count']++;

            // Agrupación por Máquina
            if (!isset($byMachine[$mName])) {
                $byMachine[$mName] = [
                    'machine_name' => $mName,
                    'process_name' => $pName,
                    'process_code' => $r['process_code'],
                    'ots_count' => 0,
                    'produced_units' => 0.0,
                    'waste_units' => 0.0,
                    'waste_kg' => 0.0,
                    'duration_hours' => 0.0,
                    'operators' => [],
                    'orders' => [],
                ];
            }
            $byMachine[$mName]['ots_count']++;
            $byMachine[$mName]['produced_units'] += $g;
            $byMachine[$mName]['waste_units'] += $w;
            $byMachine[$mName]['waste_kg'] += $wKg;
            $byMachine[$mName]['duration_hours'] += $hrs;
            if ($opName !== '' && !in_array($opName, $byMachine[$mName]['operators'], true)) {
                $byMachine[$mName]['operators'][] = $opName;
            }
            $byMachine[$mName]['orders'][] = $r;

            // Merma Crítica (>5%)
            if ($wRate >= 5.0 && $w > 0) {
                $criticalWasteOrders[] = $r;
            }
        }
        unset($r);

        // Calcular porcentaje global de merma
        $totOverall = $totalGoodUnits + $totalWasteUnits;
        $overallWastePercent = $totOverall > 0 ? round(($totalWasteUnits / $totOverall) * 100, 2) : 0.0;

        // Calcular métricas de eventos / paradas
        $totalStopsMinutes = 0;
        $totalSetupsMinutes = 0;
        $stopsByCategory = [];

        foreach ($stopsRows as &$st) {
            $mins = (int)$st['duration_minutes'];
            $st['start_time'] = !empty($st['evt_crtdat']) ? date('H:i', (int)$st['evt_crtdat']) : '';
            $st['end_time'] = !empty($st['evt_enddat']) ? date('H:i', (int)$st['evt_enddat']) : '';

            if ($st['evt_type'] === 'apertura') {
                $totalSetupsMinutes += $mins;
            } else {
                $totalStopsMinutes += $mins;
            }

            $reason = trim((string)$st['stop_reason']);
            if (!isset($stopsByCategory[$reason])) {
                $stopsByCategory[$reason] = ['count' => 0, 'minutes' => 0];
            }
            $stopsByCategory[$reason]['count']++;
            $stopsByCategory[$reason]['minutes'] += $mins;
        }
        unset($st);

        uasort($stopsByCategory, static fn($a, $b) => $b['minutes'] <=> $a['minutes']);

        // Calcular cumplimiento de colaciones
        $lunchTotalOps = (int)($lunchReport['total_operators'] ?? 0);
        $lunchMissingOps = (int)($lunchReport['missing_count'] ?? 0);
        $lunchCompletedOps = max(0, $lunchTotalOps - $lunchMissingOps);
        $lunchComplianceRate = $lunchTotalOps > 0 ? round(($lunchCompletedOps / $lunchTotalOps) * 100, 1) : 100.0;

        return [
            'date' => $date,
            'formatted_date' => $formattedDate,
            'is_yesterday' => $isYesterday,
            'is_today' => $isToday,
            'prev_date' => $prevDate,
            'next_date' => $nextDate,
            'kpis' => [
                'total_produced_units' => $totalGoodUnits,
                'total_waste_units' => $totalWasteUnits,
                'total_waste_kg' => $totalWasteKg,
                'waste_percent' => $overallWastePercent,
                'total_meters' => $totalMeters,
                'total_prod_hours' => round($totalProdHours, 1),
                'total_stops_hours' => round($totalStopsMinutes / 60, 1),
                'total_stops_minutes' => $totalStopsMinutes,
                'total_setups_hours' => round($totalSetupsMinutes / 60, 1),
                'total_setups_minutes' => $totalSetupsMinutes,
                'active_machines_count' => count($activeMachines),
                'active_operators_count' => max(count($activeOperators), $lunchTotalOps),
                'total_ots_count' => count($prodRows),
                'critical_waste_count' => count($criticalWasteOrders),
                'lunch_compliance_rate' => $lunchComplianceRate,
                'lunch_missing_count' => $lunchMissingOps,
                'total_dispatched_units' => $totalDispatchedUnits,
                'total_dispatched_money' => $totalDispatchedMoney,
                'total_dispatches_count' => count($distinctDespachoIds),
            ],
            'by_process' => array_values(array_filter($byProcess, static fn($p) => $p['produced'] > 0 || $p['waste'] > 0 || $p['ots_count'] > 0)),
            'by_machine' => array_values($byMachine),
            'work_orders' => $prodRows,
            'stops_rows' => $stopsRows,
            'stops_by_category' => $stopsByCategory,
            'critical_waste_orders' => $criticalWasteOrders,
            'attendance' => $lunchReport,
            'dispatches_rows' => $dispatchRows,
            'total_dispatched_money' => $totalDispatchedMoney,
            'total_dispatched_units' => $totalDispatchedUnits,
            'total_dispatches_count' => count($distinctDespachoIds),
        ];
    }
}



