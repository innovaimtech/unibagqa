<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Datos · Órdenes de Compra Nacionales (src/Services/Reception/PurchaseOrderService.php)
// =============================================================================

trait PurchaseOrderServiceTrait
{
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
                "SELECT t3.item_contenedor_supporderposid AS line_id,
                        COALESCE(SUM(t3.item_amount), 0) AS erp_amount,
                        COALESCE(SUM(t3.item_cont_kg_received), 0) AS erp_kg
                 FROM stockchanges ta
                 INNER JOIN stockchanges_items t3 ON t3.stk_id = ta.id
                 WHERE ta.stk_status > 1
                   AND t3.item_contenedor_supporderposid IN ($placeholders)
                 GROUP BY t3.item_contenedor_supporderposid"
            );
            $erpStmt->execute($lineIds);
            foreach ($erpStmt->fetchAll() as $erpRow) {
                $lineId = (int)$erpRow['line_id'];
                $erpAmount = (float)$erpRow['erp_amount'];
                $erpKg = (float)$erpRow['erp_kg'];
                if ($erpAmount > 0 || $erpKg > 0) {
                    if (!isset($summary[$lineId])) {
                        $summary[$lineId] = [
                            'received_rolls' => (int)round($erpAmount),
                            'received_qty' => $erpAmount,
                            'received_weight_kg' => $erpKg,
                        ];
                    } else {
                        $summary[$lineId]['received_rolls'] = max($summary[$lineId]['received_rolls'], (int)round($erpAmount));
                        $summary[$lineId]['received_qty'] = max($summary[$lineId]['received_qty'], $erpAmount);
                        $summary[$lineId]['received_weight_kg'] = max($summary[$lineId]['received_weight_kg'], $erpKg);
                    }
                }
            }
        } catch (\Throwable) {
        }

        try {
            $shippedStmt = $this->erpPdo->prepare(
                "SELECT id, COALESCE(item_amount_shipped, 0) AS shipped_amount
                 FROM supplier_order_items
                 WHERE id IN ($placeholders)"
            );
            $shippedStmt->execute($lineIds);
            foreach ($shippedStmt->fetchAll() as $erpRow) {
                $lineId = (int)$erpRow['id'];
                $shipped = (float)$erpRow['shipped_amount'];
                if ($shipped > 0 && !isset($summary[$lineId])) {
                    $summary[$lineId] = [
                        'received_rolls' => (int)round($shipped),
                        'received_qty' => $shipped,
                        'received_weight_kg' => 0.0,
                    ];
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

        $row['reception_mode'] = $this->inferReceptionModeFromErpLine($row + $line);
        $row['supplier_type'] = $this->classifySupplierType((string)$row['supplier_country_name']);
        return $row;
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
        } elseif ($this->getReceptionWarehouseById($warehouseId) === null) {
            $errors['warehouse_id'] = 'La bodega seleccionada no pertenece al ERP unibag_unibag o no está disponible para recepción.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }

        $line = $this->getPurchaseOrderLine($purchaseOrderLineId);
        if ($line === null) {
            return ['ok' => false, 'errors' => ['purchase_order_line_id' => 'Línea de OC no existe.'], 'id' => null];
        }

        $selectedMode = $this->normalizeReceptionMode((string)($receptionMode ?? ($line['reception_mode'] ?? 'QUANTITY')));
        $line['reception_mode'] = $selectedMode;
        // El peso debe ser un número finito (descarta NAN/INF enviados por el cliente).
        if ($selectedMode === 'WEIGHT' && !is_finite($weightKg)) {
            return ['ok' => false, 'errors' => ['weight_kg' => 'Peso real (Kg) inválido.'], 'id' => null];
        }
        if ($selectedMode === 'WEIGHT' && $weightKg <= 0) {
            return ['ok' => false, 'errors' => ['weight_kg' => 'Peso real (Kg) debe ser mayor a 0.'], 'id' => null];
        }
        if ($selectedMode === 'WEIGHT' && $weightKg > ReceptionService::MAX_ROLL_WEIGHT_KG) {
            return ['ok' => false, 'errors' => ['weight_kg' => 'Peso real (Kg) fuera de rango (máximo ' . (int)ReceptionService::MAX_ROLL_WEIGHT_KG . ' kg). Verifica la lectura de la balanza.'], 'id' => null];
        }
        if ($selectedMode === 'WEIGHT' && $weightKg < 15) {
            return ['ok' => false, 'errors' => ['weight_kg' => 'Peso real (Kg) debe ser al menos 15 para registrar e imprimir la etiqueta.'], 'id' => null];
        }
        if ($selectedMode === 'QUANTITY' && $receivedQty <= 0) {
            return ['ok' => false, 'errors' => ['received_qty' => 'Cantidad recibida debe ser mayor a 0.'], 'id' => null];
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

        $result = $selectedMode === 'QUANTITY' ? $this->createReceptionUnits($input) : $this->createRoll($input);
        if ($result['ok'] !== true) {
            return $result;
        }

        $this->refreshPurchaseOrderStatus((int)$line['purchase_order_id']);
        return $result;
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
            $orderedRolls = (float)($line['ordered_rolls'] ?? 0);
            if ($orderedRolls > 0) {
                $shippedValue = (float)($summary['received_rolls'] > 0 ? $summary['received_rolls'] : ($summary['received_qty'] ?? 0));
            } else {
                $shippedValue = (float)($summary['received_value'] ?? 0);
            }

            foreach ($this->getErpSyncTargets() as $erpPdo) {
                try {
                    $updateLine = $erpPdo->prepare(
                        'UPDATE supplier_order_items
                         SET item_amount_shipped = :shipped
                         WHERE id = :id'
                    );
                    $updateLine->execute([
                        ':shipped' => number_format($shippedValue, 2, '.', ''),
                        ':id' => (int)$line['id'],
                    ]);
                } catch (Throwable) {
                }
            }

            if ($summary['has_progress']) {
                $hasProgress = true;
            }
            if (!$summary['is_complete']) {
                $allComplete = false;
            }
        }

        $now = time();
        foreach ($this->getErpSyncTargets() as $erpPdo) {
            try {
                if ($allComplete) {
                    $erpPdo->prepare(
                        'UPDATE supplier_order
                         SET sord_order_shipped = 1,
                             sord_status = 4,
                             sord_upddat = :upddat
                         WHERE id = :id'
                    )->execute([
                        ':upddat' => $now,
                        ':id' => $purchaseOrderId,
                    ]);
                } elseif ($hasProgress) {
                    $erpPdo->prepare(
                        'UPDATE supplier_order
                         SET sord_order_shipped = 0,
                             sord_status = CASE WHEN sord_status < 3 THEN 3 ELSE sord_status END,
                             sord_upddat = :upddat
                         WHERE id = :id'
                    )->execute([
                        ':upddat' => $now,
                        ':id' => $purchaseOrderId,
                    ]);
                }
            } catch (Throwable) {
            }
        }
    }

}

class PurchaseOrderService
{
    use PurchaseOrderServiceTrait;

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
