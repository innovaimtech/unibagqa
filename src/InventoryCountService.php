<?php

declare(strict_types=1);

require_once __DIR__ . '/ErpInventoryCountPostingService.php';

// =============================================================================
// Servicio · Toma de inventario
//
// Este servicio encapsula la lógica relacionada a:
// - Generar “borradores” de filas para una toma de inventario por bodega.
// - Persistir la toma (cabecera + ítems) en tablas TRZ:
//   - inventory_counts
//   - inventory_count_items
// - Consultar tomas históricas para reportes (list/get).
//
// Importante:
// - La “toma” se enfoca en cantidades (qty) y diferencias (diff) por SKU y atributos.
// - Se opera dentro de una transacción para asegurar consistencia entre cabecera e ítems.
//
// ---
//
// Service · Inventory Count
//
// This service encapsulates logic to:
// - Generate “draft rows” for a warehouse inventory count.
// - Persist the count (header + items) into TRZ tables:
//   - inventory_counts
//   - inventory_count_items
// - Query historical counts for reporting (list/get).
//
// Important:
// - The count focuses on quantities (qty) and differences (diff) per SKU and attributes.
// - It uses a transaction to keep header and items consistent.
// =============================================================================

/**
 * Servicio de inventario: toma física y consulta de registros.
 *
 * ---
 *
 * Inventory service: physical count and historical queries.
 */
final class InventoryCountService
{
    public function __construct(private PDO $pdo, private ?PDO $erpPdo = null)
    {
    }

    public function getErpPdo(): ?PDO
    {
        return $this->erpPdo;
    }

    /**
     * Retorna filas agregadas por SKU con cantidad disponible en una bodega.
     *
     * Se suman dos fuentes:
     * - Bobinas recibidas (rolls) en estado RECEIVED.
     * - Cajas (boxes) almacenadas en bodega, incluyendo pallets STORED o cajas sin pallet.
     *
     * ---
     *
     * Returns rows aggregated by SKU with available quantity in a warehouse.
     *
     * Two sources are combined:
     * - Received rolls (rolls) in RECEIVED status.
     * - Stored boxes (boxes) in the warehouse, including STORED pallets or boxes without a pallet.
     */
    public function inventoryAvailableSkuRowsByWarehouseCode(int $warehouseCode): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sku_code,
                    MAX(sku_description) AS sku_description,
                    ROUND(SUM(available_qty), 3) AS available_qty
             FROM (
                SELECT s.code AS sku_code,
                       COALESCE(NULLIF(TRIM(s.description), ""), s.code) AS sku_description,
                       COALESCE(SUM(r.received_qty), 0) AS available_qty
                FROM rolls r
                JOIN warehouses w ON w.id = r.warehouse_id
                JOIN skus s ON s.id = r.sku_id
                WHERE w.code = :roll_code
                  AND r.status = "RECEIVED"
                GROUP BY s.code, s.description

                UNION ALL

                SELECT b.final_sku AS sku_code,
                       b.final_sku AS sku_description,
                       COALESCE(SUM(b.units_qty), 0) AS available_qty
                FROM boxes b
                JOIN warehouses w ON w.id = b.warehouse_id
                LEFT JOIN pallets p ON p.id = b.pallet_id
                WHERE w.code = :box_code
                  AND b.warehouse_id IS NOT NULL
                  AND (b.pallet_id IS NULL OR COALESCE(p.status, "") = "STORED")
                GROUP BY b.final_sku
             ) inventory_rows
             WHERE TRIM(COALESCE(sku_code, "")) <> ""
             GROUP BY sku_code
             HAVING SUM(available_qty) > 0
             ORDER BY sku_code ASC'
        );
        $stmt->execute([
            ':roll_code' => $warehouseCode,
            ':box_code' => $warehouseCode,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Retorna filas “borrador” para tomar inventario en una bodega.
     *
     * Se construye un set de filas agrupadas por SKU y atributos (color/alto/gramos/metros),
     * con su cantidad de sistema (system_qty). El frontend usa estas filas como planilla
     * editable para ingresar physical_qty y calcular diff_qty.
     *
     * ---
     *
     * Returns “draft” rows for running an inventory count for a warehouse.
     *
     * It builds rows grouped by SKU and attributes (color/height/grams/meters),
     * including the system quantity (system_qty). The frontend uses these rows
     * as an editable sheet to input physical_qty and compute diff_qty.
     */
    public function inventoryCountDraftRowsByWarehouseCode(int $warehouseCode): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.code AS sku_code,
                    COALESCE(NULLIF(TRIM(s.description), ""), s.code) AS sku_description,
                    COALESCE(NULLIF(TRIM(r.color), ""), "") AS family_color,
                    COALESCE(NULLIF(TRIM(r.color), ""), "") AS color_code,
                    r.width_mm AS height_mm,
                    r.microns AS grams,
                    r.meters,
                    ROUND(COALESCE(SUM(r.received_qty), 0), 3) AS system_qty
             FROM rolls r
             JOIN warehouses w ON w.id = r.warehouse_id
             JOIN skus s ON s.id = r.sku_id
             WHERE w.code = :code
               AND r.status = "RECEIVED"
             GROUP BY s.code, s.description, r.color, r.width_mm, r.microns, r.meters
             HAVING SUM(r.received_qty) > 0
             ORDER BY s.code ASC, r.color ASC, r.width_mm ASC, r.microns ASC, r.meters ASC'
        );
        $stmt->execute([':code' => $warehouseCode]);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $skuCode = trim((string)($row['sku_code'] ?? ''));
            if ($skuCode === '') {
                continue;
            }
            $skuDescription = trim((string)($row['sku_description'] ?? $skuCode));
            $familyColor = trim((string)($row['family_color'] ?? ''));
            $colorCode = trim((string)($row['color_code'] ?? ''));
            $systemQty = round((float)($row['system_qty'] ?? 0), 3);
            $rows[] = [
                'sku_code' => $skuCode,
                'sku_description' => $skuDescription,
                'article_code' => $this->deriveInventoryArticleCode($skuCode, $skuDescription),
                'family_color' => $familyColor,
                'color_code' => $colorCode,
                'height_mm' => isset($row['height_mm']) && $row['height_mm'] !== null ? round((float)$row['height_mm'], 3) : null,
                'grams' => isset($row['grams']) && $row['grams'] !== null ? round((float)$row['grams'], 3) : null,
                'meters' => isset($row['meters']) && $row['meters'] !== null ? round((float)$row['meters'], 3) : null,
                'unit_code' => 'BOB',
                'system_qty' => $systemQty,
                'physical_qty' => $systemQty,
                'diff_qty' => 0.0,
            ];
        }

        return $rows;
    }

    /**
     * Crea una toma de inventario (cabecera + ítems).
     *
     * Validaciones/normalizaciones:
     * - Verifica que la bodega exista por código.
     * - Normaliza createdBy y warehouseName.
     * - Normaliza ítems: qty redondeadas a 3 decimales, y descarta ítems vacíos.
     * - Calcula totales (system/physical/diff) para la cabecera.
     *
     * ---
     *
     * Creates an inventory count (header + items).
     *
     * Validations/normalizations:
     * - Ensures the warehouse exists by code.
     * - Normalizes createdBy and warehouseName.
     * - Normalizes items: quantities rounded to 3 decimals, and drops empty items.
     * - Computes totals (system/physical/diff) for the header.
     *
     * @return array{ok:bool,errors:array,inventory_count_id:int|null}
     */
    public function createInventoryCount(int $warehouseCode, string $warehouseName, string $createdBy, array $items): array
    {
        $warehouseId = $this->findWarehouseIdByCode($warehouseCode);
        if ($warehouseId === null) {
            return ['ok' => false, 'errors' => ['warehouse' => 'La bodega seleccionada no existe.'], 'inventory_count_id' => null];
        }

        $createdBy = trim($createdBy);
        if ($createdBy === '') {
            $createdBy = 'Operador';
        }

        $normalizedItems = [];
        $totalSystemQty = 0.0;
        $totalPhysicalQty = 0.0;
        $totalDiffQty = 0.0;
        foreach ($items as $item) {
            $skuCode = trim((string)($item['sku_code'] ?? ''));
            if ($skuCode === '') {
                continue;
            }
            $skuDescription = trim((string)($item['sku_description'] ?? ''));
            $articleCode = trim((string)($item['article_code'] ?? $this->deriveInventoryArticleCode($skuCode, $skuDescription)));
            $familyColor = trim((string)($item['family_color'] ?? ''));
            $colorCode = trim((string)($item['color_code'] ?? $familyColor));
            $heightMm = isset($item['height_mm']) && $item['height_mm'] !== '' && $item['height_mm'] !== null ? round((float)$item['height_mm'], 3) : null;
            $grams = isset($item['grams']) && $item['grams'] !== '' && $item['grams'] !== null ? round((float)$item['grams'], 3) : null;
            $meters = isset($item['meters']) && $item['meters'] !== '' && $item['meters'] !== null ? round((float)$item['meters'], 3) : null;
            $unitCode = trim((string)($item['unit_code'] ?? 'BOB'));
            if ($unitCode === '') {
                $unitCode = 'BOB';
            }
            $systemQty = round((float)($item['system_qty'] ?? $item['available_qty'] ?? 0), 3);
            $physicalQty = round((float)($item['physical_qty'] ?? $systemQty), 3);
            $diffQty = round($physicalQty - $systemQty, 3);
            if ($systemQty <= 0 && $physicalQty <= 0) {
                continue;
            }
            $normalizedItems[] = [
                'sku_code' => $skuCode,
                'sku_description' => $skuDescription,
                'article_code' => $articleCode,
                'family_color' => $familyColor,
                'color_code' => $colorCode,
                'height_mm' => $heightMm,
                'grams' => $grams,
                'meters' => $meters,
                'unit_code' => $unitCode,
                'system_qty' => $systemQty,
                'physical_qty' => $physicalQty,
                'diff_qty' => $diffQty,
                'available_qty' => $systemQty,
            ];
            $totalSystemQty += $systemQty;
            $totalPhysicalQty += $physicalQty;
            $totalDiffQty += $diffQty;
        }

        $warehouseName = trim($warehouseName);
        if ($warehouseName === '') {
            $warehouseName = 'Sin nombre';
        }

        try {
            $this->pdo->beginTransaction();

            $insertCount = $this->pdo->prepare(
                'INSERT INTO inventory_counts (
                    warehouse_id, warehouse_code, warehouse_name,
                    total_skus, total_available_qty, total_system_qty, total_physical_qty, total_diff_qty, created_by
                 ) VALUES (
                    :warehouse_id, :warehouse_code, :warehouse_name,
                    :total_skus, :total_available_qty, :total_system_qty, :total_physical_qty, :total_diff_qty, :created_by
                 )'
            );
            $insertCount->execute([
                ':warehouse_id' => $warehouseId,
                ':warehouse_code' => $warehouseCode,
                ':warehouse_name' => $warehouseName,
                ':total_skus' => count($normalizedItems),
                ':total_available_qty' => number_format($totalSystemQty, 3, '.', ''),
                ':total_system_qty' => number_format($totalSystemQty, 3, '.', ''),
                ':total_physical_qty' => number_format($totalPhysicalQty, 3, '.', ''),
                ':total_diff_qty' => number_format($totalDiffQty, 3, '.', ''),
                ':created_by' => $createdBy,
            ]);
            $inventoryCountId = (int)$this->pdo->lastInsertId();

            if ($normalizedItems !== []) {
                $insertItem = $this->pdo->prepare(
                    'INSERT INTO inventory_count_items (
                        inventory_count_id, sku_code, sku_description, article_code, family_color, color_code,
                        height_mm, grams, meters, unit_code, system_qty, physical_qty, diff_qty, available_qty
                     ) VALUES (
                        :inventory_count_id, :sku_code, :sku_description, :article_code, :family_color, :color_code,
                        :height_mm, :grams, :meters, :unit_code, :system_qty, :physical_qty, :diff_qty, :available_qty
                     )'
                );
                foreach ($normalizedItems as $item) {
                    $insertItem->execute([
                        ':inventory_count_id' => $inventoryCountId,
                        ':sku_code' => $item['sku_code'],
                        ':sku_description' => $item['sku_description'],
                        ':article_code' => $item['article_code'],
                        ':family_color' => $item['family_color'],
                        ':color_code' => $item['color_code'],
                        ':height_mm' => $item['height_mm'] !== null ? number_format((float)$item['height_mm'], 3, '.', '') : null,
                        ':grams' => $item['grams'] !== null ? number_format((float)$item['grams'], 3, '.', '') : null,
                        ':meters' => $item['meters'] !== null ? number_format((float)$item['meters'], 3, '.', '') : null,
                        ':unit_code' => $item['unit_code'],
                        ':system_qty' => number_format((float)$item['system_qty'], 3, '.', ''),
                        ':physical_qty' => number_format((float)$item['physical_qty'], 3, '.', ''),
                        ':diff_qty' => number_format((float)$item['diff_qty'], 3, '.', ''),
                        ':available_qty' => number_format((float)$item['available_qty'], 3, '.', ''),
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return ['ok' => true, 'errors' => [], 'inventory_count_id' => $inventoryCountId];
    }

    /**
     * Lista tomas de inventario (cabecera), ordenadas de más nueva a más antigua.
     *
     * ---
     *
     * Lists inventory counts (header), ordered newest to oldest.
     */
    public function listInventoryCounts(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, warehouse_id, warehouse_code, warehouse_name, total_skus, total_available_qty, total_system_qty, total_physical_qty, total_diff_qty, created_by, created_at
             FROM inventory_counts
             ORDER BY id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Obtiene una toma de inventario por ID (solo cabecera).
     *
     * ---
     *
     * Gets an inventory count by ID (header only).
     */
    public function getInventoryCount(int $inventoryCountId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, warehouse_id, warehouse_code, warehouse_name, total_skus, total_available_qty, total_system_qty, total_physical_qty, total_diff_qty, created_by, created_at
             FROM inventory_counts
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $inventoryCountId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Lista los ítems (líneas) de una toma de inventario.
     *
     * ---
     *
     * Lists the items (lines) of an inventory count.
     */
    public function listInventoryCountItems(int $inventoryCountId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sku_code, sku_description, article_code, family_color, color_code, height_mm, grams, meters, unit_code, system_qty, physical_qty, diff_qty, available_qty
             FROM inventory_count_items
             WHERE inventory_count_id = :inventory_count_id
             ORDER BY sku_code ASC, family_color ASC, height_mm ASC, grams ASC, meters ASC, id ASC'
        );
        $stmt->execute([':inventory_count_id' => $inventoryCountId]);

        return $stmt->fetchAll();
    }

    /**
     * Busca el ID interno de una bodega a partir de su código (numérico).
     *
     * ---
     *
     * Finds the internal warehouse ID from its (numeric) code.
     */
    private function findWarehouseIdByCode(int $warehouseCode): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM warehouses WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $warehouseCode]);
        $warehouseId = $stmt->fetchColumn();

        return $warehouseId === false ? null : (int)$warehouseId;
    }

    /**
     * Intenta inferir un “código de artículo” para inventario a partir de SKU y descripción.
     *
     * Regla actual (simple):
     * - Si contiene PLA => PLA
     * - Si contiene PPT o “PP” => PPT
     * - Si no, vacío
     *
     * ---
     *
     * Attempts to derive an “article code” for inventory from SKU and description.
     *
     * Current (simple) rule:
     * - Contains PLA => PLA
     * - Contains PPT or “PP” => PPT
     * - Otherwise, empty
     */
    private function deriveInventoryArticleCode(string $skuCode, string $skuDescription): string
    {
        $subject = strtoupper(trim($skuCode . ' ' . $skuDescription));
        if ($subject === '') {
            return '';
        }
        if (str_contains($subject, 'PLA')) {
            return 'PLA';
        }
        if (str_contains($subject, 'PPT')) {
            return 'PPT';
        }
        if (preg_match('/\bPP\b/', $subject) === 1 || str_contains($subject, 'POLIPROP')) {
            return 'PPT';
        }

        return '';
    }

    /**
     * Retorna las bodegas activas de ERP (company_shops_storehouses).
     * Si $onlyWithStock es true, filtra solo aquellas bodegas que tengan inventario registrado (> 0).
     */
    public function listErpStorehouses(bool $onlyWithStock = false): array
    {
        if ($this->erpPdo === null) {
            return [];
        }

        $sql = 'SELECT id, st_name, st_desc, st_capacidad, st_clasificacion
                FROM company_shops_storehouses s
                WHERE st_status = 1';
        if ($onlyWithStock) {
            $sql .= ' AND EXISTS (
                SELECT 1 FROM item_shops_storehouses iss
                WHERE iss.st_id = s.id AND iss.iss_inventory > 0
            )';
        }
        $sql .= ' ORDER BY st_name ASC';

        $stmt = $this->erpPdo->query($sql);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'id' => (int)$row['id'],
                'name' => trim((string)($row['st_name'] ?? '')),
                'desc' => trim((string)($row['st_desc'] ?? '')),
                'capacidad' => (int)($row['st_capacidad'] ?? 0),
                'clasificacion' => trim((string)($row['st_clasificacion'] ?? '')),
            ];
        }
        return $rows;
    }

    /**
     * Retorna el inventario de artículos por bodega directamente desde el ERP (item_shops_storehouses + item).
     *
     * @return array<int, array{item_id:int, st_id:int, item_number_prod:string, item_title:string, unit_name:string, iss_inventory:float, iss_inventory_reserved:float, iss_inventory_min:float, available_qty:float}>
     */
    public function getErpStockByStorehouse(int $storehouseId, ?string $search = null, bool $onlyWithStock = false): array
    {
        if ($this->erpPdo === null || $storehouseId <= 0) {
            return [];
        }

        $sql = 'SELECT iss.item_id, iss.st_id, SUM(iss.iss_inventory) AS iss_inventory,
                       SUM(iss.iss_inventory_reserved) AS iss_inventory_reserved, MAX(iss.iss_inventory_min) AS iss_inventory_min,
                       it.item_number_prod, it.item_title,
                       it.item_reg_width, it.item_reg_gsm, it.item_reg_length, it.item_reg_kg,
                       COALESCE(u.unit_name, "UNID") AS unit_name,
                       SUM(iss.iss_inventory - iss.iss_inventory_reserved) AS available_qty
                FROM item_shops_storehouses iss
                JOIN item it ON it.id = iss.item_id
                LEFT JOIN item_units u ON u.id = it.item_unit
                WHERE iss.st_id = :st_id
                  AND it.item_status = 1';

        $params = [':st_id' => $storehouseId];

        $search = trim((string)$search);
        if ($search !== '') {
            $sql .= ' AND (it.item_number_prod LIKE :search1 OR it.item_title LIKE :search2)';
            $params[':search1'] = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
        }

        $sql .= ' GROUP BY iss.item_id, iss.st_id, it.item_number_prod, it.item_title,
                    it.item_reg_width, it.item_reg_gsm, it.item_reg_length, it.item_reg_kg, u.unit_name';
        if ($onlyWithStock) {
            $sql .= ' HAVING SUM(iss.iss_inventory) > 0';
        }
        $sql .= ' ORDER BY it.item_number_prod ASC';

        $stmt = $this->erpPdo->prepare($sql);
        $stmt->execute($params);

        $results = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $inv = (float)($r['iss_inventory'] ?? 0);
            $res = (float)($r['iss_inventory_reserved'] ?? 0);
            $min = (float)($r['iss_inventory_min'] ?? 0);
            $results[] = [
                'item_id' => (int)$r['item_id'],
                'st_id' => (int)$r['st_id'],
                'item_number_prod' => trim((string)$r['item_number_prod']),
                'item_title' => trim((string)$r['item_title']),
                'unit_name' => trim((string)$r['unit_name']),
                'iss_inventory' => $inv,
                'inventory_exact' => ErpInventoryCountPostingService::quantity((string)$r['iss_inventory']),
                'iss_inventory_reserved' => $res,
                'iss_inventory_min' => $min,
                'available_qty' => $inv - $res,
                'item_reg_width' => (float)($r['item_reg_width'] ?? 0),
                'item_reg_gsm' => (float)($r['item_reg_gsm'] ?? 0),
                'item_reg_length' => (float)($r['item_reg_length'] ?? 0),
                'item_reg_kg' => (float)($r['item_reg_kg'] ?? 0),
            ];
        }

        return $results;
    }

    /**
     * Retorna planilla borrador para toma de inventario desde el ERP,
     * únicamente con los artículos que tienen stock disponible (> 0).
     */
    public function getErpInventoryCountDraft(int $storehouseId): array
    {
        // Solo artículos con stock > 0
        $items = $this->getErpStockByStorehouse($storehouseId, null, true);

        $draft = [];
        foreach ($items as $it) {
            $sysQty = (string)$it['inventory_exact'];
            $dispQty = (float)($it['available_qty'] ?? 0);
            if ($sysQty <= 0 && $dispQty <= 0) {
                continue;
            }
            $draft[] = [
                'item_id' => (int)$it['item_id'],
                'item_number_prod' => trim((string)$it['item_number_prod']),
                'item_title' => trim((string)$it['item_title']),
                'unit_name' => trim((string)($it['unit_name'] ?? 'UNID')),
                'system_qty' => $sysQty,
                'physical_qty' => $sysQty,
                'diff_qty' => 0.0,
                'comment' => '',
            ];
        }

        return $draft;
    }

    /**
     * Guarda una toma de inventario (recuento) en las tablas de ERP:
     * stockcounts, stockcounts_lists, stockcounts_lists_items, stockcounts_storehouses.
     */
    public function createErpInventoryCount(int $storehouseId, string $storehouseName, string $annotation, string $operatorName, array $items, string $request = ''): array
    {
        if ($this->erpPdo === null) {
            return ['ok' => false, 'errors' => ['No hay conexión a la base de datos del ERP.']];
        }

        $userId = (int)($_SESSION['auth_user_id'] ?? $_SESSION['user_id'] ?? 0);
        return (new ErpInventoryCountPostingService($this->erpPdo, $this->pdo))
            ->create($storehouseId, $annotation, $operatorName, $items, $userId, $request);
    }

    /**
     * Lista los recuentos históricos de stockcounts en ERP.
     */
    public function listErpStockcounts(int $limit = 100): array
    {
        if ($this->erpPdo === null) return [];

        $stmt = $this->erpPdo->query(
            'SELECT sc.id, sc.stc_num, sc.stc_annotation, sc.stc_bookdate, sc.stc_crtdat, sc.stc_status,
                    st.id AS storehouse_id, st.st_name AS storehouse_name,
                    COALESCE(u.user_login, u.user_firstname, "Sistema") AS operator_name,
                    COALESCE(sli.items_count, 0) AS items_count,
                    COALESCE(sli.total_system_qty, 0) AS total_system_qty,
                    COALESCE(sli.total_physical_qty, 0) AS total_physical_qty,
                    COALESCE(sli.total_diff_qty, 0) AS total_diff_qty
             FROM stockcounts sc
             LEFT JOIN stockcounts_storehouses scs ON scs.stc_id = sc.id
             LEFT JOIN company_shops_storehouses st ON st.id = scs.sth_id
             LEFT JOIN user u ON u.id = sc.stc_crtusr
             LEFT JOIN (
                SELECT stc_id, COUNT(*) AS items_count,
                       SUM(item_amount_stock) AS total_system_qty,
                       SUM(item_amount_count) AS total_physical_qty,
                       SUM(item_amount_book) AS total_diff_qty
                FROM stockcounts_lists_items
                GROUP BY stc_id
             ) sli ON sli.stc_id = sc.id
             ORDER BY sc.id DESC
             LIMIT ' . (int)$limit
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene la cabecera de un recuento ERP por ID.
     */
    public function getErpStockcount(int $id): ?array
    {
        if ($this->erpPdo === null) return null;

        $stmt = $this->erpPdo->prepare(
            'SELECT sc.id, sc.stc_num, sc.stc_annotation, sc.stc_bookdate, sc.stc_crtdat, sc.stc_status,
                    st.id AS storehouse_id, st.st_name AS storehouse_name,
                    COALESCE(u.user_login, u.user_firstname, "Sistema") AS operator_name,
                    COALESCE(sli.items_count, 0) AS items_count,
                    COALESCE(sli.total_system_qty, 0) AS total_system_qty,
                    COALESCE(sli.total_physical_qty, 0) AS total_physical_qty,
                    COALESCE(sli.total_diff_qty, 0) AS total_diff_qty
             FROM stockcounts sc
             LEFT JOIN stockcounts_storehouses scs ON scs.stc_id = sc.id
             LEFT JOIN company_shops_storehouses st ON st.id = scs.sth_id
             LEFT JOIN user u ON u.id = sc.stc_crtusr
             LEFT JOIN (
                SELECT stc_id, COUNT(*) AS items_count,
                       SUM(item_amount_stock) AS total_system_qty,
                       SUM(item_amount_count) AS total_physical_qty,
                       SUM(item_amount_book) AS total_diff_qty
                FROM stockcounts_lists_items
                GROUP BY stc_id
             ) sli ON sli.stc_id = sc.id
             WHERE sc.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Lista los ítems de un recuento ERP por ID.
     */
    public function listErpStockcountItems(int $id): array
    {
        if ($this->erpPdo === null) return [];

        $stmt = $this->erpPdo->prepare(
            'SELECT sli.item_id, sli.item_pos, sli.item_amount_stock, sli.item_amount_count, sli.item_amount_book, sli.item_comment,
                    it.item_number_prod, it.item_title, COALESCE(u.unit_name, "UNID") AS unit_name
             FROM stockcounts_lists_items sli
             JOIN item it ON it.id = sli.item_id
             LEFT JOIN item_units u ON u.id = it.item_unit
             WHERE sli.stc_id = :id
             ORDER BY sli.item_pos ASC'
        );
        $stmt->execute([':id' => $id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
