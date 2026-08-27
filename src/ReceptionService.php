<?php

declare(strict_types=1);

require_once __DIR__ . '/InventoryCountService.php';
require_once __DIR__ . '/RollReceptionService.php';

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

    private InventoryCountService $inventoryCountService;
    private RollReceptionService $rollReceptionService;

    public function __construct(private PDO $pdo, private PDO $erpPdo)
    {
        $this->inventoryCountService = new InventoryCountService($this->pdo);
        $this->rollReceptionService = new RollReceptionService($this->pdo);
        if (!self::$schemaEnsured) {
            if ($this->getAppSetting('reception_schema_version', '') !== self::RECEPTION_SCHEMA_VERSION) {
                $this->ensureReceptionSchema();
                $this->setAppSetting('reception_schema_version', self::RECEPTION_SCHEMA_VERSION);
            }
            if ($this->getAppSetting('production_warehouse_sync_version', '') !== self::PRODUCTION_WAREHOUSE_SYNC_VERSION) {
                $this->syncLegacyProductionRollWarehouses();
                $this->setAppSetting('production_warehouse_sync_version', self::PRODUCTION_WAREHOUSE_SYNC_VERSION);
            }
            self::$schemaEnsured = true;
        }
        $this->ensureProductionMachineCatalog();
        $this->ensureWasteSchema();
        $this->ensureBonusSchema();
    }

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

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS waste_operations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                shift_session_id BIGINT UNSIGNED NULL,
                operation_code VARCHAR(30) NOT NULL,
                operator_name VARCHAR(120) NOT NULL,
                comments VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_waste_ops_shift (shift_session_id),
                KEY idx_waste_ops_code (operation_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

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
                operator_name VARCHAR(120) NOT NULL,
                factor DECIMAL(4,2) NOT NULL DEFAULT 1.00,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_bonus_operator_factor (bonus_code, operator_name),
                KEY idx_bonus_operator_factor_bonus (bonus_code),
                KEY idx_bonus_operator_factor_operator (operator_name)
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

    private function syncWarehousesFromErp(): void
    {
        if ($this->erpWarehousesSynced) {
            return;
        }

        if ($this->shouldSkipWarehouseSync()) {
            $this->erpWarehousesSynced = true;
            return;
        }

        $stmt = $this->erpPdo->query(
            'SELECT id, st_name
             FROM company_shops_storehouses
             WHERE st_status = 1
             ORDER BY id ASC'
        );
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            $name = trim((string)($row['st_name'] ?? ''));
            $code = $this->parseWarehouseCodeFromName($name);
            if ($code === null) {
                continue;
            }
            $this->upsertTraceWarehouse($code, $name);
        }

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

    private function upsertTraceWarehouse(int $code, string $name): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM warehouses WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        if ($row !== false) {
            $id = (int)$row['id'];
            $update = $this->pdo->prepare('UPDATE warehouses SET name = :name WHERE id = :id');
            $update->execute([':name' => $name, ':id' => $id]);
            return $id;
        }

        $insert = $this->pdo->prepare('INSERT INTO warehouses (code, name) VALUES (:code, :name)');
        $insert->execute([':code' => $code, ':name' => $name]);
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
                $targetQty = isset($row['ag_amount']) ? (int)round((float)$row['ag_amount']) : null;
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

    private function summarizeReceptionLine(array $line): array
    {
        $mode = $this->normalizeReceptionMode((string)($line['reception_mode'] ?? $this->inferReceptionModeFromErpLine($line)));
        if ($mode === 'WEIGHT') {
            $ordered = round((float)($line['ordered_weight_kg'] ?? 0), 3);
            $received = round((float)($line['received_weight_kg'] ?? 0), 3);
            $unit = 'Kg';
        } else {
            $ordered = round((float)($line['ordered_rolls'] ?? 0), 3);
            $received = round((float)($line['received_qty'] ?? $line['received_rolls'] ?? 0), 3);
            $unit = 'Unid.';
        }

        $pending = max(0, round($ordered - $received, 3));
        $complete = $ordered > 0 && $received >= $ordered;
        $hasProgress = $received > 0;

        return [
            'mode' => $mode,
            'ordered_value' => $ordered,
            'received_value' => $received,
            'pending_value' => $pending,
            'unit_label' => $unit,
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
             WHERE s.supp_status = 1
               AND po.sord_type = 0
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
        $stmt = $this->erpPdo->prepare(
            'SELECT s.id,
                    s.supp_company AS name,
                    c.country_name AS country_name,
                    sci.id AS container_item_id,
                    sci.sord_id AS container_id,
                    sci.sord_amount AS ordered_rolls,
                    sci.sord_kgs_amount AS ordered_weight_kg
             FROM supplier_contenedor_items sci
             JOIN supplier_order_items soi ON soi.id = sci.sord_pos_id
             JOIN supplier_order so ON so.id = soi.sord_id
             JOIN supplier s ON s.id = so.sord_supplier_id
             LEFT JOIN country c ON c.id = s.supp_countryid
             WHERE s.supp_status = 1
             ORDER BY s.supp_company ASC, sci.id ASC'
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
            if ($summary['is_complete']) {
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

    public function listPurchaseOrders(?int $supplierId, ?string $search, ?string $status, ?string $supplierType = null, int $limit = 50): array
    {
        $where = ['po.sord_type = 0'];
        $params = [];

        if ($supplierId !== null && $supplierId > 0) {
            $where[] = 'po.sord_supplier_id = :supplier_id';
            $params[':supplier_id'] = $supplierId;
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
                   po.sord_crtdat,
                   s.supp_company AS supplier_name,
                   c.country_name AS supplier_country_name
            FROM supplier_order po
            JOIN supplier s ON s.id = po.sord_supplier_id
            LEFT JOIN country c ON c.id = s.supp_countryid
            $whereSql
            ORDER BY po.id DESC
            LIMIT :limit
        ";

        $stmt = $this->erpPdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $orderIds = array_column($rows, 'id');
        $statsByOrder = $this->getPurchaseOrderStatsByIds($orderIds);

        $statusFilter = $status !== null ? strtolower(trim($status)) : '';
        $supplierType = $supplierType !== null ? strtoupper(trim($supplierType)) : '';
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
            $derivedStatus = 'OPEN';
            if ($totalLines > 0 && $completedLines >= $totalLines) {
                $derivedStatus = 'COMPLETE';
            } elseif ($linesWithProgress > 0) {
                $derivedStatus = 'PARTIAL';
            }

            if (($statusFilter === '' || $statusFilter === 'active') && !in_array($derivedStatus, ['OPEN', 'PARTIAL'], true)) {
                continue;
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

        $status = 'OPEN';
        $stats = $this->getPurchaseOrderStatsByIds([$id])[$id] ?? null;
        if (is_array($stats)) {
            $totalLines = (int)$stats['total_lines'];
            $completedLines = (int)$stats['completed_lines'];
            $hasProgress = (int)$stats['lines_with_progress'] > 0;
            if ($totalLines > 0 && $completedLines >= $totalLines) {
                $status = 'COMPLETE';
            } elseif ($hasProgress) {
                $status = 'PARTIAL';
            }
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

    public function listImportContainers(?int $supplierId, ?string $search, ?string $status, int $limit = 50): array
    {
        $where = ['1=1'];
        $params = [];

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
             LIMIT :limit"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $containerIds = array_column($rows, 'id');
        $statsByContainer = $this->getImportContainerStatsByIds($containerIds);

        $statusFilter = $status !== null ? strtolower(trim($status)) : '';
        $result = [];
        foreach ($rows as $row) {
            $stats = $statsByContainer[(int)$row['id']] ?? [
                'total_lines' => 0,
                'completed_lines' => 0,
                'lines_with_progress' => 0,
            ];
            $totalLines = (int)$stats['total_lines'];
            $completedLines = (int)$stats['completed_lines'];
            $hasProgress = (int)$stats['lines_with_progress'] > 0;
            $derivedStatus = 'OPEN';
            if ($totalLines > 0 && $completedLines >= $totalLines) {
                $derivedStatus = 'COMPLETE';
            } elseif ($hasProgress) {
                $derivedStatus = 'PARTIAL';
            }

            if (($statusFilter === '' || $statusFilter === 'active') && !in_array($derivedStatus, ['OPEN', 'PARTIAL'], true)) {
                continue;
            }
            if ($statusFilter === 'complete' && $derivedStatus !== 'COMPLETE') {
                continue;
            }

            $result[] = [
                'id' => (int)$row['id'],
                'container_code' => trim((string)($row['container_code'] ?? '')),
                'vessel_name' => trim((string)($row['vessel_name'] ?? '')),
                'forwarder_name' => trim((string)($row['forwarder_name'] ?? '')),
                'bill_of_lading' => trim((string)($row['bill_of_lading'] ?? '')),
                'po_codes' => trim((string)($row['po_codes'] ?? '')),
                'created_at' => gmdate('Y-m-d H:i:s', (int)($row['sord_crtdat'] ?? 0)),
                'eta_port' => (int)($row['sord_eta_puerto'] ?? 0) > 0 ? gmdate('Y-m-d', (int)$row['sord_eta_puerto']) : '',
                'eta_plant' => (int)($row['sord_eta_puertounibag'] ?? 0) > 0 ? gmdate('Y-m-d', (int)$row['sord_eta_puertounibag']) : '',
                'status' => $derivedStatus,
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

        $status = 'OPEN';
        $stats = $this->getImportContainerStatsByIds([$id])[$id] ?? null;
        if (is_array($stats)) {
            $totalLines = (int)$stats['total_lines'];
            $completedLines = (int)$stats['completed_lines'];
            $hasProgress = (int)$stats['lines_with_progress'] > 0;
            if ($totalLines > 0 && $completedLines >= $totalLines) {
                $status = 'COMPLETE';
            } elseif ($hasProgress) {
                $status = 'PARTIAL';
            }
        }

        return [
            'id' => (int)$row['id'],
            'container_code' => trim((string)($row['container_code'] ?? '')),
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
        return $result;
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
        $stmt = $this->pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch();
        if ($row === false) {
            return $default;
        }

        $value = $row['setting_value'] ?? null;
        return $value === null ? $default : (string)$value;
    }

    private function setAppSetting(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO app_settings (setting_key, setting_value) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([
            ':key' => $key,
            ':value' => $value,
        ]);
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
                   CASE WHEN sync.erp_plan_timestamp IS NOT NULL THEN FROM_UNIXTIME(sync.erp_plan_timestamp) ELSE NULL END,
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
                    COALESCE(wo.target_qty, 0) AS target_qty,
                    COALESCE(
                        CASE WHEN sync.erp_plan_timestamp IS NOT NULL THEN FROM_UNIXTIME(sync.erp_plan_timestamp) ELSE NULL END,
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
                    CASE WHEN sync.erp_plan_timestamp IS NOT NULL THEN FROM_UNIXTIME(sync.erp_plan_timestamp) ELSE NULL END,
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

        $rows = $this->stockSummary();
        foreach ($rows as &$row) {
            $warehouseId = (int)($row['warehouse_id'] ?? 0);
            $cap = $capacities[$warehouseId] ?? ['capacity_units_total' => 0.0, 'capacity_pallets' => 0];
            $row['capacity_units_total'] = (float)$cap['capacity_units_total'];
            $row['capacity_pallets'] = (int)$cap['capacity_pallets'];
            $row['occupancy_percent'] = null;
            $palletsCount = (int)($row['pallets_count'] ?? 0);
            $stockUnits = (float)($row['stock_units_total'] ?? 0);
            $capPallets = (int)$row['capacity_pallets'];
            $capUnits = (float)$row['capacity_units_total'];
            if ($capPallets > 0) {
                if ($palletsCount > 0) {
                    $row['occupancy_percent'] = round(($palletsCount / $capPallets) * 100, 2);
                } elseif ($capUnits > 0 && $stockUnits > 0) {
                    $row['occupancy_percent'] = round(($stockUnits / $capUnits) * 100, 2);
                } else {
                    $row['occupancy_percent'] = 0.0;
                }
            } elseif ($capUnits > 0) {
                $row['occupancy_percent'] = round(($stockUnits / $capUnits) * 100, 2);
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
            'SELECT w.id, w.code, w.name,
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
        return [
            'id' => (int)$row['id'],
            'code' => (int)$row['code'],
            'name' => (string)$row['name'],
            'capacity_units_total' => (float)($row['capacity_units_total'] ?? 0),
            'capacity_pallets' => (int)($row['capacity_pallets'] ?? 0),
        ];
    }

    public function listWarehousesWithCapacities(): array
    {
        $summaryRows = $this->stockSummaryWithCapacities();
        $summaryByWarehouseId = [];
        foreach ($summaryRows as $row) {
            $warehouseId = (int)($row['warehouse_id'] ?? 0);
            if ($warehouseId > 0) {
                $summaryByWarehouseId[$warehouseId] = $row;
            }
        }

        $stmt = $this->pdo->prepare(
            'SELECT w.id, w.code, w.name,
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
            $summary = $summaryByWarehouseId[$id] ?? null;
            if ($summary !== null) {
                $result[] = [
                    'id' => $id,
                    'code' => (int)($summary['warehouse_code'] ?? $w['code'] ?? 0),
                    'name' => (string)($summary['warehouse_name'] ?? $w['name'] ?? ''),
                    'capacity_units_total' => (float)($summary['capacity_units_total'] ?? $w['capacity_units_total'] ?? 0),
                    'capacity_pallets' => (int)($summary['capacity_pallets'] ?? $w['capacity_pallets'] ?? 0),
                    'rolls_count' => (int)($summary['rolls_count'] ?? 0),
                    'boxes_count' => (int)($summary['boxes_count'] ?? 0),
                    'pallets_count' => (int)($summary['pallets_count'] ?? 0),
                    'stock_units_total' => (float)($summary['stock_units_total'] ?? 0),
                    'occupancy_percent' => isset($summary['occupancy_percent'])
                        ? (is_numeric($summary['occupancy_percent']) ? round((float)$summary['occupancy_percent'], 2) : null)
                        : null,
                ];
            } else {
                $capacityPallets = (int)($w['capacity_pallets'] ?? 0);
                $capacityUnits = (float)($w['capacity_units_total'] ?? 0);
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
                    'capacity_units_total' => $capacityUnits,
                    'capacity_pallets' => $capacityPallets,
                    'rolls_count' => 0,
                    'boxes_count' => 0,
                    'pallets_count' => 0,
                    'stock_units_total' => 0.0,
                    'occupancy_percent' => $occupancy === null ? null : round((float)$occupancy, 2),
                ];
            }
        }

        return $result;
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function createWarehouse(int $code, string $name, float $capacityUnitsTotal, int $capacityPallets): array
    {
        $errors = [];
        $code = max(0, $code);
        $name = trim($name);
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

        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare('INSERT INTO warehouses (code, name) VALUES (:code, :name)');
            $insert->execute([':code' => $code, ':name' => $name]);
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
    public function updateWarehouse(int $id, int $code, string $name, float $capacityUnitsTotal, int $capacityPallets): array
    {
        $errors = [];
        $code = max(0, $code);
        $name = trim($name);
        $capacityUnitsTotal = (int)round(max(0.0, $capacityUnitsTotal));
        $capacityPallets = max(0, $capacityPallets);

        $current = $this->pdo->prepare('SELECT id, code FROM warehouses WHERE id = :id LIMIT 1');
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
                'errors' => ['No se puede eliminar la bodega: tiene ' . implode(', ', $parts) . ' asociadas.'],
            ];
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
            "SELECT w.id AS warehouse_id, w.code AS warehouse_code, w.name AS warehouse_name,
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

    public function getBonusPeriodByMonthFinal(string $monthKey): array
    {
        $monthKey = trim($monthKey);
        if (!preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            $monthKey = date('Y-m');
        }

        $tz = new DateTimeZone(date_default_timezone_get());
        $monthStart = new DateTimeImmutable($monthKey . '-01 00:00:00', $tz);
        $previousMonth = $monthStart->modify('-1 month');
        $periodStart = $previousMonth->setDate((int)$previousMonth->format('Y'), (int)$previousMonth->format('m'), 26)->setTime(0, 0, 0);
        $periodEnd = $monthStart->setDate((int)$monthStart->format('Y'), (int)$monthStart->format('m'), 25)->setTime(23, 59, 59);

        return [
            'month_key' => $monthKey,
            'start_date' => $periodStart->format('Y-m-d'),
            'end_date' => $periodEnd->format('Y-m-d'),
            'start_ts' => $periodStart->getTimestamp(),
            'end_ts' => $periodEnd->getTimestamp(),
        ];
    }

    public function listErpFlexoProductionForBonusPeriod(string $monthKey, ?string $operatorName = null): array
    {
        $period = $this->getBonusPeriodByMonthFinal($monthKey);
        $startTs = (int)($period['start_ts'] ?? 0);
        $endTs = (int)($period['end_ts'] ?? 0);
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return ['ok' => false, 'errors' => ['Período inválido.'], 'period' => $period, 'rows' => []];
        }

        $operatorName = $operatorName !== null ? trim($operatorName) : '';

        $sql = <<<SQL
SELECT
    pa.ag_equipo_id AS printer_no,
    ph.prd_reqid AS cost_center,
    ph.prd_number AS work_order_number,
    DATE(FROM_UNIXTIME(e.evt_crtdat)) AS event_date,
    ph.prd_desc AS erp_desc,
    TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))) AS operator_name,
    MAX(pa.ag_amount) AS requested_units,
    SUM(e.evt_amount) AS produced_units,
    SUM(e.evt_amount_metros_lineales) AS produced_linear_meters,
    SUM(e.evt_amount_metros_maquina) AS produced_machine_meters
FROM prod_worker_ot_events e
INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
INNER JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
INNER JOIN workers w ON w.id = pwi.win_wrkid
WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
  AND pa.ag_equipotype_id = 1
  AND e.evt_type = 'PRODUCTION'
  AND (:operator_name = '' OR TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, ""))) = :operator_name_exact)
GROUP BY pa.ag_equipo_id, ph.prd_reqid, ph.prd_number, DATE(FROM_UNIXTIME(e.evt_crtdat)), operator_name, ph.prd_desc
ORDER BY event_date ASC, printer_no ASC, cost_center ASC, work_order_number ASC
SQL;

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute([
                ':start_ts' => $startTs,
                ':end_ts' => $endTs,
                ':operator_name' => $operatorName,
                ':operator_name_exact' => $operatorName,
            ]);
            $rawRows = $stmt->fetchAll();
        } catch (PDOException $e) {
            $sqlState = (string)($e->errorInfo[0] ?? $e->getCode() ?? '');
            $message = $e->getMessage();
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
                'client_label' => $this->parseClientLabelFromErpDesc($desc),
                'product_type' => $this->parseProductTypeFromErpDesc($desc),
                'measure_cm' => $this->parseMeasureCmFromErpDesc($desc),
                'helper_label' => '',
                'bonification_label' => '',
                'operator_name' => trim((string)($r['operator_name'] ?? '')),
                'requested_units' => (float)($r['requested_units'] ?? 0),
                'produced_units' => (float)($r['produced_units'] ?? 0),
                'produced_linear_meters' => (float)($r['produced_linear_meters'] ?? 0),
                'produced_machine_meters' => (float)($r['produced_machine_meters'] ?? 0),
                'erp_desc' => $desc,
            ];
        }

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
        if (preg_match('/\b(BOU|PRO|TRO|IND)\b/', $desc, $m) === 1) {
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
     * @return array<string,float>
     */
    public function listBonusOperatorFactors(string $bonusCode): array
    {
        $bonusCode = strtolower(trim($bonusCode));
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT operator_name, factor
             FROM bonus_operator_factors
             WHERE bonus_code = :bonus_code
             ORDER BY operator_name ASC'
        );
        $stmt->execute([':bonus_code' => $bonusCode]);
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
     * @param array<string,float|int|string> $factorsByOperator
     * @return array{ok:bool, errors?:string[]}
     */
    public function replaceBonusOperatorFactors(string $bonusCode, array $factorsByOperator): array
    {
        $errors = [];
        $bonusCode = strtolower(trim($bonusCode));
        if (!in_array($bonusCode, $this->listBonusCodes(), true)) {
            $errors[] = 'Bono inválido.';
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
            $del = $this->pdo->prepare('DELETE FROM bonus_operator_factors WHERE bonus_code = :bonus_code');
            $del->execute([':bonus_code' => $bonusCode]);

            if ($normalized !== []) {
                $ins = $this->pdo->prepare(
                    'INSERT INTO bonus_operator_factors (bonus_code, operator_name, factor)
                     VALUES (:bonus_code, :operator_name, :factor)'
                );
                foreach ($normalized as $operatorName => $factor) {
                    $ins->execute([
                        ':bonus_code' => $bonusCode,
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
}
