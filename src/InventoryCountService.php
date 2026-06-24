<?php

declare(strict_types=1);

final class InventoryCountService
{
    public function __construct(private PDO $pdo)
    {
    }

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

    public function createInventoryCount(int $warehouseCode, string $warehouseName, string $createdBy, array $items): array
    {
        $warehouseId = $this->findWarehouseIdByCode($warehouseCode);
        if ($warehouseId === null) {
            return ['ok' => false, 'errors' => ['warehouse' => 'La bodega seleccionada no existe.'], 'inventory_count_id' => null];
        }

        $createdBy = trim($createdBy);
        if ($createdBy === '') {
            $createdBy = 'Operador Demo';
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

    private function findWarehouseIdByCode(int $warehouseCode): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM warehouses WHERE code = :code LIMIT 1');
        $stmt->execute([':code' => $warehouseCode]);
        $warehouseId = $stmt->fetchColumn();

        return $warehouseId === false ? null : (int)$warehouseId;
    }

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
}
