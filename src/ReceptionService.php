<?php

declare(strict_types=1);

final class ReceptionService
{
    private const RECEPTION_SCHEMA_VERSION = 'reception_v1';
    private static bool $schemaEnsured = false;
    private bool $erpWarehousesSynced = false;

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

    public function __construct(private PDO $pdo, private PDO $erpPdo)
    {
        if (!self::$schemaEnsured) {
            if ($this->getAppSetting('reception_schema_version', '') !== self::RECEPTION_SCHEMA_VERSION) {
                $this->ensureReceptionSchema();
                $this->setAppSetting('reception_schema_version', self::RECEPTION_SCHEMA_VERSION);
            }
            self::$schemaEnsured = true;
        }
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
        return 'QUANTITY';
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

        $row['reception_mode'] = 'QUANTITY';
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
        $line['reception_mode'] = 'QUANTITY';
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
        $mode = 'QUANTITY';
        $ordered = round((float)($line['ordered_rolls'] ?? 0), 3);
        $received = round((float)($line['received_qty'] ?? $line['received_rolls'] ?? 0), 3);
        $unit = 'Unid.';

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

        $selectedMode = 'QUANTITY';
        $line['reception_mode'] = 'QUANTITY';
        $summary = $this->summarizeReceptionLine($line);
        if ($weightKg <= 0) {
            return ['ok' => false, 'errors' => ['weight_kg' => 'Peso real (Kg) debe ser mayor a 0.'], 'id' => null];
        }
        if ($receivedQty <= 0) {
            return ['ok' => false, 'errors' => ['received_qty' => 'Cantidad recibida debe ser mayor a 0.'], 'id' => null];
        }
        if ($summary['is_complete']) {
            return ['ok' => false, 'errors' => ['purchase_order_line_id' => 'Esta línea ya está completa y no permite más recepciones.'], 'id' => null];
        }

        $input = [
            'sku_id' => (int)$line['sku_id'],
            'warehouse_id' => $warehouseId,
            'weight_kg' => $weightKg,
            'received_qty' => $receivedQty,
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

        $selectedMode = 'QUANTITY';
        $line['reception_mode'] = 'QUANTITY';
        $summary = $this->summarizeReceptionLine($line);
        if ($weightKg <= 0) {
            return ['ok' => false, 'errors' => ['weight_kg' => 'Peso real (Kg) debe ser mayor a 0.'], 'id' => null];
        }
        if ($receivedQty <= 0) {
            return ['ok' => false, 'errors' => ['received_qty' => 'Cantidad recibida debe ser mayor a 0.'], 'id' => null];
        }
        if ($summary['is_complete']) {
            return ['ok' => false, 'errors' => ['import_container_item_id' => 'Esta línea de contenedor ya está completa y no permite más recepciones.'], 'id' => null];
        }

        $input = [
            'sku_id' => (int)$line['sku_id'],
            'warehouse_id' => $warehouseId,
            'weight_kg' => $weightKg,
            'received_qty' => $receivedQty,
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
            'SELECT id, ot_code, sku_final, target_qty, status, created_at
             FROM work_orders
             WHERE status IN (' . implode(',', $placeholderNames) . ')
             ORDER BY CASE
                 WHEN status = "ACTIVE" THEN 0
                 WHEN status = "CUTTING" THEN 1
                 ELSE 2
             END, id DESC
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
            $row['operator_name'] = '';
            $row['operator_label'] = '-';
            $row['current_roll_code'] = '-';
            $row['current_chemical_label'] = '-';
            $row['finished_at'] = '';
            $row['box_qty'] = '';
            $row['status_label'] = match ((string)$row['status']) {
                'ACTIVE' => 'Producción',
                'CUTTING' => 'Corte',
                'CLOSED' => 'Fabricada',
                default => 'Pendiente',
            };

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
        $stmt = $this->pdo->prepare(
            'SELECT id, ot_code, sku_final, target_qty, status, created_at
             FROM work_orders
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
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
        $id = (int)$this->getAppSetting('active_work_order_id', '0');
        if ($id === null || $id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT id, ot_code, sku_final, target_qty, status, created_at FROM work_orders WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $wo = $stmt->fetch();
        return $wo === false ? null : $wo;
    }

    public function listWorkOrdersForTransfer(): array
    {
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

    public function listChemicals(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, code, name FROM chemicals WHERE is_active = 1 ORDER BY code ASC');
        $stmt->execute();
        return $stmt->fetchAll();
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

    public function listWorkOrderRollHistory(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.id, e.type, e.created_at, e.payload,
                    r.id AS roll_id, r.roll_code, r.weight_kg, w.code AS warehouse_code,
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

    public function startWorkOrder(int $workOrderId, string $operatorName): array
    {
        $operatorName = trim($operatorName);
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
        if ($this->getCurrentRollInWorkOrder($workOrderId) === null) {
            return ['ok' => false, 'errors' => ['roll' => 'Debes asignar y pesar una bobina antes de iniciar la OT.']];
        }
        if ($this->listChemicalInputsByWorkOrder($workOrderId, 1) === []) {
            return ['ok' => false, 'errors' => ['chemical' => 'Debes registrar al menos un químico de entrada antes de iniciar la OT.']];
        }

        $this->setActiveWorkOrder($workOrderId, $operatorName);
        $this->insertEvent('WORK_ORDER_STARTED', [
            'work_order_id' => $workOrderId,
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function getLastWorkOrderStart(int $workOrderId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, created_at,
                    JSON_UNQUOTE(JSON_EXTRACT(payload, "$.operator_name")) AS operator_name
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
        $stmt = $this->pdo->prepare(
            'SELECT e.created_at, e.payload
             FROM events e
             WHERE e.type = "MATERIAL_DELIVERED"
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(e.payload, "$.request_id")) AS UNSIGNED) = :request_id
             ORDER BY e.id DESC'
        );
        $stmt->execute([':request_id' => $requestId]);
        $rows = $stmt->fetchAll();
        $deliveries = [];
        foreach ($rows as $row) {
            $payload = json_decode((string)($row['payload'] ?? '{}'), true);
            if (!is_array($payload)) {
                $payload = [];
            }
            $deliveries[] = [
                'created_at' => (string)($row['created_at'] ?? ''),
                'roll_id' => (int)($payload['roll_id'] ?? 0),
                'roll_code' => (string)($payload['roll_code'] ?? ''),
                'operator_name' => (string)($payload['operator_name'] ?? ''),
                'request_type' => (string)($payload['request_type'] ?? 'ROLL'),
                'delivered_qty' => (float)($payload['delivered_qty'] ?? 0),
                'requested_unit' => (string)($payload['requested_unit'] ?? 'Unid.'),
                'delivered_item' => (string)($payload['delivered_item'] ?? ''),
                'delivery_note' => (string)($payload['delivery_note'] ?? ''),
            ];
        }
        return $deliveries;
    }

    public function listAvailableRollsForMaterialRequest(): array
    {
        $rolls = $this->listAvailableRollsForMaterialDelivery();
        $groups = [];
        foreach ($rolls as $roll) {
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
        $errors = [];
        $group = null;
        $chemical = null;

        if ($workOrderId <= 0 || $this->getWorkOrder($workOrderId) === null) {
            $errors['work_order_id'] = 'OT no existe.';
        }
        if (!in_array($requestType, ['ROLL', 'CHEMICAL', 'OTHER'], true)) {
            $errors['request_type'] = 'Tipo de solicitud inválido.';
        }
        if ($requestedQty <= 0) {
            $errors['requested_qty'] = 'Cantidad solicitada debe ser mayor a 0.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }

        if ($requestType === 'ROLL') {
            $group = $this->findMaterialGroupByKey($requestedGroupKey);
            if ($requestedGroupKey === '' || $group === null) {
                $errors['requested_group_key'] = 'Debes seleccionar un tipo de bobina disponible.';
            } elseif ($requestedQty > (float)($group['available_qty'] ?? 0)) {
                $errors['requested_qty'] = 'La cantidad solicitada supera las bobinas disponibles en bodega.';
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
            'INSERT INTO work_order_material_requests (work_order_id, request_type, requested_item, requested_qty, requested_unit, delivered_qty, request_notes, status, requested_by, requested_roll_id, requested_group_key, chemical_id)
             VALUES (:wo, :request_type, :item, :qty, :requested_unit, :delivered_qty, :notes, :status, :requested_by, NULL, :requested_group_key, :chemical_id)'
        );
        $stmt->execute([
            ':wo' => $workOrderId,
            ':request_type' => $requestType,
            ':item' => $requestedItem,
            ':qty' => number_format($requestedQty, 3, '.', ''),
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
        if (!in_array((string)$request['status'], ['PENDING', 'ACCEPTED', 'PARTIAL'], true)) {
            return ['ok' => false, 'errors' => ['request_id' => 'La solicitud ya fue atendida.']];
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
        $stmt = $this->pdo->prepare('SELECT * FROM work_order_material_requests WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $requestId]);
        $request = $stmt->fetch();
        if ($request === false) {
            return ['ok' => false, 'errors' => ['request_id' => 'Solicitud no existe.']];
        }
        if (!in_array((string)$request['status'], ['PENDING', 'ACCEPTED', 'PARTIAL'], true)) {
            return ['ok' => false, 'errors' => ['request_id' => 'La solicitud ya fue atendida.']];
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
        if (!in_array($stage, ['PRODUCTION', 'CUT'], true)) {
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
        if ($this->getCurrentRollInWorkOrder($workOrderId) !== null) {
            $errors['roll_active'] = 'Ya existe una bobina activa en esta OT. Debes cambiarla o finalizar la OT.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare('UPDATE rolls SET current_work_order_id = :wo, status = :status WHERE id = :id');
        $stmt->execute([
            ':wo' => $workOrderId,
            ':status' => 'IN_PROCESS',
            ':id' => $rollId,
        ]);

        $this->insertEvent('WORK_ORDER_ROLL_ATTACHED', [
            'work_order_id' => $workOrderId,
            'roll_id' => $rollId,
            'process_weight_kg' => round($processWeightKg, 3),
            'waste_kg' => round($wasteKg, 3),
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function changeRollInWorkOrder(int $workOrderId, int $nextRollId, float $currentFinalWeightKg, float $currentWasteKg, float $outputRollWeightKg, float $nextProcessWeightKg, float $nextWasteKg, string $operatorName): array
    {
        $errors = [];
        $operatorName = trim($operatorName);
        $workOrder = $this->getWorkOrder($workOrderId);
        $currentRoll = $this->getCurrentRollInWorkOrder($workOrderId);
        $nextRoll = $this->getRoll($nextRollId);

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

            $stmt = $this->pdo->prepare('UPDATE rolls SET current_work_order_id = :wo, status = :status WHERE id = :id');
            $stmt->execute([
                ':wo' => $workOrderId,
                ':status' => 'IN_PROCESS',
                ':id' => $nextRollId,
            ]);

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
        string $operatorName
    ): array
    {
        $errors = [];
        $operatorName = trim($operatorName);
        $workOrder = $this->getWorkOrder($workOrderId);
        $currentRoll = $this->getCurrentRollInWorkOrder($workOrderId);

        if ($workOrder === null) {
            $errors['work_order_id'] = 'OT no existe.';
        }
        if ($workOrder !== null && in_array((string)$workOrder['status'], ['CUTTING', 'CLOSED'], true)) {
            $errors['work_order_id'] = 'La producción ya fue cerrada para esta OT.';
        }
        if ($currentRoll === null) {
            $errors['current_roll'] = 'No hay bobina activa para finalizar la OT.';
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
        if ($boxQty <= 0) {
            $errors['box_qty'] = 'Cantidad de cajas debe ser mayor a 0.';
        }
        if ($outputRollWeightKg <= 0) {
            $errors['output_roll_weight_kg'] = 'Peso de la nueva bobina debe ser mayor a 0.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->pdo->beginTransaction();
        try {
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

            $outputRollId = $this->createOutputRollFromWorkOrder(
                $workOrder,
                $currentRoll,
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

            $this->insertEvent('WORK_ORDER_FINISHED', [
                'work_order_id' => $workOrderId,
                'roll_id' => (int)$currentRoll['id'],
                'final_roll_weight_kg' => round($finalRollWeightKg, 3),
                'final_chemical_weight_kg' => round($finalChemicalWeightKg, 3),
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
                'roll_id' => (int)$currentRoll['id'],
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
        $errors = [];

        if ($sourceRoll === null) {
            $errors['source_roll_id'] = 'Bobina de corte no existe.';
        } elseif ((string)($sourceRoll['process_stage'] ?? 'RAW') !== 'PRINTED') {
            $errors['source_roll_id'] = 'La bobina debe provenir de producción para pasar a corte.';
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
            $where[] = '(p.warehouse_id IS NULL OR COALESCE(p.status, "") <> "STORED")';
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

    public function movePalletToWarehouse(int $palletId, int $toWarehouseId, string $operatorName): array
    {
        $operatorName = trim($operatorName);
        $pallet = $this->getPallet($palletId);
        $errors = [];

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

        $rolls = [
            'total' => 0,
            'in_stock' => 0,
            'in_process' => 0,
            'ready_for_cut' => 0,
            'output' => 0,
        ];
        $stmt = $this->pdo->query(
            "SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN status IN ('RECEIVED','IN_PROCESS','BLOCKED') THEN 1 ELSE 0 END) AS in_stock_count,
                SUM(CASE WHEN status = 'IN_PROCESS' THEN 1 ELSE 0 END) AS in_process_count,
                SUM(CASE WHEN process_stage = 'PRINTED' AND status <> 'CONSUMED' THEN 1 ELSE 0 END) AS ready_cut_count,
                SUM(CASE WHEN parent_roll_id IS NOT NULL THEN 1 ELSE 0 END) AS output_count
             FROM rolls"
        );
        $row = $stmt->fetch() ?: [];
        $rolls['total'] = (int)($row['total_count'] ?? 0);
        $rolls['in_stock'] = (int)($row['in_stock_count'] ?? 0);
        $rolls['in_process'] = (int)($row['in_process_count'] ?? 0);
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

    public function stockSummary(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT w.id AS warehouse_id, w.code AS warehouse_code, w.name AS warehouse_name,
                    COALESCE(roll_stats.rolls_count, 0) AS rolls_count,
                    COALESCE(roll_stats.roll_units_total, 0) AS roll_units_total,
                    COALESCE(roll_stats.total_weight_kg, 0) AS total_weight_kg,
                    COALESCE(box_stats.boxes_count, 0) AS boxes_count,
                    COALESCE(box_stats.box_units_total, 0) AS box_units_total,
                    COALESCE(pallet_stats.pallets_count, 0) AS pallets_count,
                    COALESCE(roll_stats.roll_units_total, 0) + COALESCE(box_stats.box_units_total, 0) AS stock_units_total
             FROM warehouses w
             LEFT JOIN (
                SELECT warehouse_id,
                       COUNT(*) AS rolls_count,
                       COALESCE(SUM(received_qty), 0) AS roll_units_total,
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

        $stmt = $this->pdo->prepare('SELECT id, warehouse_id FROM rolls WHERE id = :id');
        $stmt->execute([':id' => $rollId]);
        $roll = $stmt->fetch();
        if ($roll === false) {
            return ['ok' => false, 'errors' => ['roll' => 'Bobina no existe.']];
        }

        $fromWarehouseId = (int)$roll['warehouse_id'];
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
            $stmt = $this->pdo->prepare('SELECT id FROM work_orders WHERE id = :id');
            $stmt->execute([':id' => $workOrderId]);
            if ($stmt->fetch() === false) {
                $errors['work_order_id'] = 'OT no existe.';
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->pdo->beginTransaction();
        try {
            $targetWarehouseId = $toWarehouseId > 0 ? $toWarehouseId : $fromWarehouseId;
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
                    wf.code AS from_warehouse_code, wt.code AS to_warehouse_code,
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
        $errors = $this->validateCreate($input);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }

        $rollCode = $this->generateRollCode();

        $microns = isset($input['microns']) && $input['microns'] !== '' && $input['microns'] !== null ? (int)$input['microns'] : null;
        $width = isset($input['width_mm']) && $input['width_mm'] !== '' && $input['width_mm'] !== null ? (int)$input['width_mm'] : null;
        $color = isset($input['color']) && trim((string)$input['color']) !== '' ? trim((string)$input['color']) : null;
        $meters = isset($input['meters']) && $input['meters'] !== '' && $input['meters'] !== null ? (float)$input['meters'] : null;
        $receivedQty = isset($input['received_qty']) ? (float)$input['received_qty'] : 1.0;
        $poId = isset($input['purchase_order_id']) ? (int)$input['purchase_order_id'] : null;
        $polId = isset($input['purchase_order_line_id']) ? (int)$input['purchase_order_line_id'] : null;
        $importContainerId = isset($input['import_container_id']) ? (int)$input['import_container_id'] : null;
        $importContainerItemId = isset($input['import_container_item_id']) ? (int)$input['import_container_item_id'] : null;
        $supplierId = isset($input['supplier_id']) ? (int)$input['supplier_id'] : null;
        $operatorName = trim((string)($input['operator_name'] ?? ''));
        $receptionMode = $this->normalizeReceptionMode((string)($input['reception_mode'] ?? 'QUANTITY'));

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO rolls (roll_code, sku_id, warehouse_id, weight_kg, received_qty, reception_mode, microns, width_mm, color, meters, status, purchase_order_id, purchase_order_line_id, import_container_id, import_container_item_id, supplier_id, current_work_order_id)
                 VALUES (:roll_code, :sku_id, :warehouse_id, :weight_kg, :received_qty, :reception_mode, :microns, :width_mm, :color, :meters, :status, :po_id, :pol_id, :import_container_id, :import_container_item_id, :supplier_id, :work_order_id)'
            );
            $stmt->execute([
                ':roll_code' => $rollCode,
                ':sku_id' => (int)$input['sku_id'],
                ':warehouse_id' => (int)$input['warehouse_id'],
                ':weight_kg' => (string)$input['weight_kg'],
                ':received_qty' => number_format($receivedQty, 3, '.', ''),
                ':reception_mode' => $receptionMode,
                ':microns' => $microns,
                ':width_mm' => $width,
                ':color' => $color,
                ':meters' => $meters,
                ':status' => 'RECEIVED',
                ':po_id' => $poId > 0 ? $poId : null,
                ':pol_id' => $polId > 0 ? $polId : null,
                ':import_container_id' => $importContainerId !== null && $importContainerId > 0 ? $importContainerId : null,
                ':import_container_item_id' => $importContainerItemId !== null && $importContainerItemId > 0 ? $importContainerItemId : null,
                ':supplier_id' => $supplierId > 0 ? $supplierId : null,
                ':work_order_id' => null,
            ]);

            $rollId = (int)$this->pdo->lastInsertId();

            $this->insertMovement($rollId, (int)$input['warehouse_id'], $input);
            $this->insertEvent('ROLL_RECEIVED', [
                'roll_id' => $rollId,
                'roll_code' => $rollCode,
                'warehouse_id' => (int)$input['warehouse_id'],
                'sku_id' => (int)$input['sku_id'],
                'purchase_order_id' => $poId,
                'purchase_order_line_id' => $polId,
                'import_container_id' => $importContainerId,
                'import_container_item_id' => $importContainerItemId,
                'reception_mode' => $receptionMode,
                'supplier_id' => $supplierId,
                'operator_name' => $operatorName,
            ]);

            $this->pdo->commit();
            return ['ok' => true, 'errors' => [], 'id' => $rollId];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function insertMovement(int $rollId, int $toWarehouseId, array $input): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload)
             VALUES (:entity_type, :entity_id, :movement_type, :from_warehouse_id, :to_warehouse_id, :payload)'
        );

        $payload = json_encode([
            'weight_kg' => (string)$input['weight_kg'],
            'received_qty' => isset($input['received_qty']) ? (float)$input['received_qty'] : 1.0,
            'reception_mode' => $this->normalizeReceptionMode((string)($input['reception_mode'] ?? 'QUANTITY')),
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

    private function validateCreate(array $input): array
    {
        $errors = [];

        $skuId = isset($input['sku_id']) ? (int)$input['sku_id'] : 0;
        if ($skuId <= 0) {
            $errors['sku_id'] = 'SKU es obligatorio.';
        }

        $warehouseId = isset($input['warehouse_id']) ? (int)$input['warehouse_id'] : 0;
        if ($warehouseId <= 0) {
            $errors['warehouse_id'] = 'Bodega es obligatoria.';
        } else {
            $stmt = $this->pdo->prepare('SELECT code FROM warehouses WHERE id = :id');
            $stmt->execute([':id' => $warehouseId]);
            $row = $stmt->fetch();
            if ($row === false) {
                $errors['warehouse_id'] = 'La bodega seleccionada no existe.';
            }
        }

        $mode = $this->normalizeReceptionMode((string)($input['reception_mode'] ?? 'QUANTITY'));
        $weight = isset($input['weight_kg']) ? (float)$input['weight_kg'] : 0.0;
        if ($mode === 'WEIGHT' && $weight <= 0) {
            $errors['weight_kg'] = 'Peso real (Kg) debe ser mayor a 0.';
        }
        if ($mode !== 'WEIGHT' && $weight < 0) {
            $errors['weight_kg'] = 'Peso real (Kg) no puede ser negativo.';
        }

        $receivedQty = isset($input['received_qty']) ? (float)$input['received_qty'] : 1.0;
        if ($mode === 'QUANTITY' && $receivedQty <= 0) {
            $errors['received_qty'] = 'Cantidad recibida debe ser mayor a 0.';
        }

        $operatorName = trim((string)($input['operator_name'] ?? ''));
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }

        if (isset($input['microns']) && $input['microns'] !== '') {
            $microns = (int)$input['microns'];
            if ($microns <= 0) {
                $errors['microns'] = 'Gramos debe ser mayor a 0.';
            }
        }

        if (isset($input['width_mm']) && $input['width_mm'] !== '') {
            $width = (int)$input['width_mm'];
            if ($width <= 0) {
                $errors['width_mm'] = 'Ancho (mm) debe ser mayor a 0.';
            }
        }

        if (isset($input['color']) && trim((string)$input['color']) !== '') {
            $color = trim((string)$input['color']);
            if ($color === '') {
                $errors['color'] = 'Color es inválido.';
            }
        }

        if (isset($input['meters']) && $input['meters'] !== '') {
            $meters = (float)$input['meters'];
            if ($meters <= 0) {
                $errors['meters'] = 'Metros lineales debe ser mayor a 0.';
            }
        }

        return $errors;
    }

    private function generateRollCode(): string
    {
        $date = gmdate('Ymd');
        $rand = bin2hex(random_bytes(3));
        return 'RB-' . $date . '-' . strtoupper($rand);
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
}
