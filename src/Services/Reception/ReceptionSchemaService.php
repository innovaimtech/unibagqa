<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Esquema y Migraciones TRZ (src/Services/Reception/ReceptionSchemaService.php)
// =============================================================================

trait ReceptionSchemaServiceTrait
{
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
        // Unidades originales de una caja a la que Despacho le dejó saldo (producción y etiquetas usan este valor).
        if ($this->tableExists('boxes') && !$this->columnExists('boxes', 'units_initial')) {
            $this->pdo->exec('ALTER TABLE boxes ADD COLUMN units_initial DECIMAL(12,3) NULL AFTER units_qty');
        }
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
        // Desactivar o eliminar restricciones foráneas obsoletas que bloquean los IDs de OC/Proveedor del ERP
        foreach (['fk_rolls_po_id', 'fk_rolls_pol_id', 'fk_rolls_supplier_id'] as $fkName) {
            try {
                @$this->pdo->exec("ALTER TABLE rolls DROP FOREIGN KEY `{$fkName}`");
            } catch (Throwable) {}
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
                proactividad_score DECIMAL(4,2) UNSIGNED NOT NULL DEFAULT 0,
                eficiencia_score DECIMAL(4,2) UNSIGNED NOT NULL DEFAULT 0,
                multitarea_score DECIMAL(4,2) UNSIGNED NOT NULL DEFAULT 0,
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
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return;
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
        // Rendimiento: information_schema es lento y esto se consulta en cada página. Una consulta directa a
        // la tabla (LIMIT 0) responde en microsegundos; solo se recuerdan las tablas que existen.
        static $known = [];
        $cacheKey = spl_object_id($this->pdo) . ':' . $table;
        if (isset($known[$cacheKey])) {
            return true;
        }
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) === 1) {
            try {
                $this->pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 0');
                return $known[$cacheKey] = true;
            } catch (Throwable) {
                return false;
            }
        }
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $stmt->execute([
            ':table_name' => $table,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }


}

#[AllowDynamicProperties]
class ReceptionSchemaService
{
    use ReceptionSchemaServiceTrait;

    private ?ReceptionService $bridge = null;

    public function __construct(private PDO $pdo, private PDO $erpPdo, ?ReceptionService $bridge = null)
    {
        $this->bridge = $bridge;
    }

    public function __call(string $name, array $arguments): mixed
    {
        $this->bridge ??= new ReceptionService($this->pdo, $this->erpPdo);
        return $this->bridge->$name(...$arguments);
    }
}
