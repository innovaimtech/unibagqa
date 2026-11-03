<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Datos · Contenedores de Importación (src/Services/Reception/ImportContainerService.php)
// =============================================================================

trait ImportContainerServiceTrait
{
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
               AND (reception_mode IS NULL OR reception_mode != 'WAREHOUSE_STOCK')
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
               AND (reception_mode IS NULL OR reception_mode != 'WAREHOUSE_STOCK')
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
                 INNER JOIN supplier_contenedor_items sci ON sci.id = t3.item_contenedor_refid
                     AND sci.sord_id = ta.sth_supporder_contenedorid
                     AND sci.sord_pos_id = t3.item_contenedor_supporderposid
                 INNER JOIN supplier_order_items soi ON soi.id = sci.sord_pos_id AND soi.item_id = t3.item_id
                 WHERE ta.stk_status > 1
                   AND t3.item_contenedor_refid IN ($placeholders)
                 GROUP BY t3.item_contenedor_refid"
            );
            $erpStmt->execute($containerItemIds);
            foreach ($erpStmt->fetchAll() as $erpRow) {
                $cItemId = (int)$erpRow['container_item_id'];
                $erpAmount = (float)$erpRow['erp_amount'];
                if ($erpAmount > 0) {
                    $existingWeight = (float)($summary[$cItemId]['received_weight_kg'] ?? 0.0);
                    // Confirmed ERP receipt is authoritative, not the largest local value.
                    // La recepcion ERP confirmada prevalece sobre cantidades locales mayores.
                    $summary[$cItemId] = [
                        'received_rolls' => (int)round($erpAmount),
                        'received_qty' => $erpAmount,
                        'received_weight_kg' => $existingWeight,
                    ];
                }
            }

            // Archived does not mean received: only actual receipt records count.
            // Archivado no equivale a recibido: contar solo recepciones reales.
        } catch (\Throwable) {
        }

        return $summary;
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


    public function listSuppliersForImportContainers(?string $status = 'active'): array
    {
        $statusFilter = $status !== null ? strtolower(trim($status)) : '';
        $where = ['s.supp_status = 1', 'sc.sord_status > 0', 'so.sord_status > 0'];
        if ($statusFilter === 'active' || $statusFilter === '') {
            $where[] = 'sc.sord_status IN (2, 3)';
            $where[] = 'sc.sord_crtdat >= 1704067200';
        } elseif ($statusFilter === 'complete') {
            $where[] = '(sc.sord_status IN (3, 4) OR sc.sord_crtdat < 1704067200)';
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
            $where[] = '(sc.sord_status IN (3, 4) OR sc.sord_crtdat < 1704067200)';
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
            $where[] = '(sc.sord_status IN (3, 4) OR sc.sord_crtdat < 1704067200)';
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
                'ordered_rolls' => 0.0,
                'received_rolls' => 0.0,
                'pending_rolls' => 0.0,
                'ordered_weight_kg' => 0.0,
                'received_weight_kg' => 0.0,
                'pending_weight_kg' => 0.0,
                'is_complete' => false,
                'has_progress' => false,
            ];
            $totalLines = (int)$stats['total_lines'];
            $completedLines = (int)$stats['completed_lines'];
            $hasProgress = (bool)($stats['has_progress'] ?? ((int)$stats['lines_with_progress'] > 0));
            $erpStatus = (int)($row['sord_status'] ?? 0);
            $crtdat = (int)($row['sord_crtdat'] ?? 0);

            $derivedStatus = 'OPEN';
            if ($erpStatus === 4 || $crtdat < 1704067200 || (bool)($stats['is_complete'] ?? ($totalLines > 0 && $completedLines >= $totalLines))) {
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
                'ordered_rolls' => round((float)($stats['ordered_rolls'] ?? 0), 3),
                'received_rolls' => round((float)($stats['received_rolls'] ?? 0), 3),
                'pending_rolls' => round((float)($stats['pending_rolls'] ?? 0), 3),
                'ordered_weight_kg' => round((float)($stats['ordered_weight_kg'] ?? 0), 3),
                'received_weight_kg' => round((float)($stats['received_weight_kg'] ?? 0), 3),
                'pending_weight_kg' => round((float)($stats['pending_weight_kg'] ?? 0), 3),
                'erp_is_complete' => $erpStatus === 4,
                'reception_is_complete' => (bool)($stats['is_complete'] ?? false),
            ];
        }

        return $result;
    }

    /**
     * @param array<int, int|string> $containerIds
     * @return array<int, array{
     *   total_lines:int,
     *   completed_lines:int,
     *   lines_with_progress:int,
     *   ordered_rolls:float,
     *   received_rolls:float,
     *   pending_rolls:float,
     *   ordered_weight_kg:float,
     *   received_weight_kg:float,
     *   pending_weight_kg:float,
     *   is_complete:bool,
     *   has_progress:bool
     * }>
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
                    'ordered_rolls' => 0.0,
                    'received_rolls' => 0.0,
                    'pending_rolls' => 0.0,
                    'ordered_weight_kg' => 0.0,
                    'received_weight_kg' => 0.0,
                    'pending_weight_kg' => 0.0,
                    'is_complete' => false,
                    'has_progress' => false,
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
            $stats[$containerId]['ordered_rolls'] += (float)($summary['ordered_rolls'] ?? 0);
            $stats[$containerId]['received_rolls'] += (float)($summary['received_rolls'] ?? 0);
            $stats[$containerId]['pending_rolls'] += (float)($summary['pending_rolls'] ?? 0);
            $stats[$containerId]['ordered_weight_kg'] += (float)($summary['ordered_weight_kg'] ?? 0);
            $stats[$containerId]['received_weight_kg'] += (float)($summary['received_weight_kg'] ?? 0);
            $stats[$containerId]['pending_weight_kg'] += (float)($summary['pending_weight_kg'] ?? 0);
        }

        foreach ($stats as &$containerStats) {
            $containerStats['ordered_rolls'] = round((float)$containerStats['ordered_rolls'], 3);
            $containerStats['received_rolls'] = round((float)$containerStats['received_rolls'], 3);
            $containerStats['pending_rolls'] = round((float)$containerStats['pending_rolls'], 3);
            $containerStats['ordered_weight_kg'] = round((float)$containerStats['ordered_weight_kg'], 3);
            $containerStats['received_weight_kg'] = round((float)$containerStats['received_weight_kg'], 3);
            $containerStats['pending_weight_kg'] = round((float)$containerStats['pending_weight_kg'], 3);
            $containerStats['is_complete'] = (int)$containerStats['total_lines'] > 0
                && (int)$containerStats['completed_lines'] >= (int)$containerStats['total_lines'];
            $containerStats['has_progress'] = (int)$containerStats['lines_with_progress'] > 0
                || (float)$containerStats['received_rolls'] > 0
                || (float)$containerStats['received_weight_kg'] > 0;
        }
        unset($containerStats);

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
            $hasProgress = (bool)($stats['has_progress'] ?? ((int)$stats['lines_with_progress'] > 0));
            if ($erpStatus === 4 || $crtdat < 1704067200 || (bool)($stats['is_complete'] ?? ($totalLines > 0 && $completedLines >= $totalLines))) {
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
            'total_lines' => (int)($stats['total_lines'] ?? 0),
            'completed_lines' => (int)($stats['completed_lines'] ?? 0),
            'ordered_rolls' => round((float)($stats['ordered_rolls'] ?? 0), 3),
            'received_rolls' => round((float)($stats['received_rolls'] ?? 0), 3),
            'pending_rolls' => round((float)($stats['pending_rolls'] ?? 0), 3),
            'ordered_weight_kg' => round((float)($stats['ordered_weight_kg'] ?? 0), 3),
            'received_weight_kg' => round((float)($stats['received_weight_kg'] ?? 0), 3),
            'pending_weight_kg' => round((float)($stats['pending_weight_kg'] ?? 0), 3),
            'erp_is_complete' => $erpStatus === 4,
            'reception_is_complete' => (bool)($stats['is_complete'] ?? false),
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
        } elseif ($this->getReceptionWarehouseById($warehouseId) === null) {
            $errors['warehouse_id'] = 'La bodega seleccionada no pertenece al ERP unibag_unibag o no está disponible para recepción.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }

        $line = $this->getImportContainerLine($containerItemId);
        if ($line === null) {
            return ['ok' => false, 'errors' => ['import_container_item_id' => 'Línea de contenedor no existe.'], 'id' => null];
        }
        try {
            $this->assertImportContainerReceiptEditable((int)$line['import_container_id']);
        } catch (Throwable $e) {
            return ['ok' => false, 'errors' => ['erp' => $e->getMessage()], 'id' => null];
        }

        $purchaseOrderLine = $this->getPurchaseOrderLine((int)$line['id']);
        if ($purchaseOrderLine === null) {
            return ['ok' => false, 'errors' => ['purchase_order_line_id' => 'Línea de OC asociada no existe.'], 'id' => null];
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
            'import_container_id' => (int)($line['import_container_id'] ?? 0),
            'import_container_item_id' => (int)($line['import_container_item_id'] ?? 0),
            'supplier_id' => (int)$line['supplier_id'],
            'operator_name' => trim($operatorName),
        ];

        $result = $selectedMode === 'QUANTITY' ? $this->createReceptionUnits($input) : $this->createRoll($input);
        if ($result['ok'] !== true) {
            return $result;
        }

        $containerId = (int)($line['import_container_id'] ?? 0);
        if ($containerId > 0) {
            try {
                $this->refreshImportContainerStatus($containerId);
                $this->refreshPurchaseOrderStatus((int)$line['purchase_order_id']);
            } catch (Throwable $e) {
                // La recepción física ya existe; no solicitar un segundo ingreso.
                // Physical receipt is saved; retry ERP sync without receiving again.
                $result['sync_error'] = 'Recepción guardada en trazabilidad, pendiente de sincronizar con ERP: ' . $e->getMessage();
            }
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


    public function closeImportContainerReception(int $containerId, int $userId = 0, string $reason = '', ?string $notes = null): array
    {
        if ($containerId <= 0) {
            return ['ok' => false, 'error' => 'ID de contenedor inválido'];
        }
        $this->ensureReceptionClosureSchema();
        try {
            $this->syncImportContainerErpReceptionTransaction($containerId, $userId);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'No se puede cerrar sin sincronizar la recepción ERP: ' . $e->getMessage()];
        }

        if (!$this->updateImportContainerClosureAcrossErps($containerId, $userId)) {
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

        try {
            $lines = $this->listImportContainerLines($containerId);
            $poIds = array_unique(array_filter(array_map(static fn(array $l): int => (int)($l['purchase_order_id'] ?? 0), $lines), static fn(int $id): bool => $id > 0));
            foreach ($poIds as $poId) {
                $this->refreshPurchaseOrderStatus($poId);
            }
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
        return $this->updateImportContainerClosureAcrossErps($containerId, $userId);
    }

    public function retryImportContainerReceptionSync(int $containerId, int $userId = 0): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT id, purchase_order_id FROM rolls WHERE import_container_id = :id');
            $stmt->execute([':id' => $containerId]);
            $purchaseOrderIds = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $roll) {
                $this->syncRollWarehouseTraceabilityEntry((int)$roll['id']);
                $purchaseOrderIds[(int)$roll['purchase_order_id']] = true;
            }
            $this->syncImportContainerErpReceptionTransaction($containerId, $userId);
            $this->refreshImportContainerStatus($containerId);
            foreach (array_keys($purchaseOrderIds) as $purchaseOrderId) {
                if ($purchaseOrderId > 0) {
                    $this->refreshPurchaseOrderStatus($purchaseOrderId);
                }
            }
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function assertImportContainerReceiptEditable(int $containerId): void
    {
        foreach ($this->getErpSyncTargets() as $erpPdo) {
            $stmt = $erpPdo->prepare(
                'SELECT COUNT(*) FROM stockchanges s
                 WHERE s.sth_supporder_contenedorid = :container_id
                   AND (s.stk_annotation <> :annotation OR s.stk_annotation IS NULL
                        OR EXISTS (SELECT 1 FROM tran_data t WHERE t.tran_id = s.id
                                   AND t.tran_type IN ("stockchangeup", "stockchangedown"))
                        OR EXISTS (SELECT 1 FROM stockchanges_items i WHERE i.stk_id = s.id
                                   AND (COALESCE(i.item_costprice_netto, 0) <> 0
                                        OR COALESCE(i.item_cont_costadd, 0) <> 0
                                        OR COALESCE(i.item_cont_unitprc_amt, 0) <> 0
                                        OR COALESCE(i.item_cont_unitprc_kg, 0) <> 0)))'
            );
            $stmt->execute([':container_id' => $containerId, ':annotation' => 'Recepción sincronizada por sistema Unibag']);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new RuntimeException('El contenedor tiene una recepción ERP previa, contabilizada o costeada. Ajusta esa recepción en el ERP antes de agregar o borrar productos.');
            }
        }
    }

    public function refreshImportContainerStatus(int $containerId): void
    {
        if ($containerId <= 0) {
            return;
        }
        $stats = $this->getImportContainerStatsByIds([$containerId])[$containerId] ?? null;
        if (!is_array($stats) || (int)($stats['total_lines'] ?? 0) <= 0) {
            return;
        }
        $this->syncImportContainerErpReceptionTransaction($containerId);

        if ((bool)($stats['is_complete'] ?? false)) {
            $this->archiveImportContainer($containerId);
        } elseif ((bool)($stats['has_progress'] ?? false) || (float)($stats['received_rolls'] ?? 0) > 0) {
            $this->updateImportContainerReceivedAcrossErps($containerId);
        }
    }

    private function syncImportContainerErpReceptionTransaction(int $containerId, int $userId = 0): void
    {
        if ($containerId <= 0 || $this->erpPdo === null) {
            return;
        }

        $lines = $this->listImportContainerLines($containerId);
        // Conservar bodega de recepción y cantidades, no la bodega actual del material.
        // Preserve receipt warehouses and quantities even after subsequent transfers.
        $receiptStmt = $this->pdo->prepare(
            'SELECT r.import_container_item_id, w.erp_storehouse_id,
                    SUM(r.received_qty) AS received_qty,
                    SUM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(m.payload, "$.weight_kg")), r.weight_kg)) AS weight_kg
             FROM rolls r
             LEFT JOIN movements m ON m.entity_type = "ROLL" AND m.entity_id = r.id
                 AND m.id = (SELECT MIN(m2.id) FROM movements m2
                             WHERE m2.entity_type = "ROLL" AND m2.entity_id = r.id AND m2.movement_type = "RECEIPT")
             JOIN warehouses w ON w.id = COALESCE(m.to_warehouse_id, r.warehouse_id)
             WHERE r.import_container_id = :container_id
             GROUP BY r.import_container_item_id, w.erp_storehouse_id'
        );
        $receiptStmt->execute([':container_id' => $containerId]);
        $receiptsByLine = [];
        foreach ($receiptStmt->fetchAll(PDO::FETCH_ASSOC) as $receipt) {
            $receiptsByLine[(int)$receipt['import_container_item_id']][] = $receipt;
        }
        $items = [];

        foreach ($lines as $line) {
            $containerItemId = (int)($line['import_container_item_id'] ?? 0);
            foreach ($receiptsByLine[$containerItemId] ?? [] as $receipt) {
                if ((float)$receipt['received_qty'] <= 0) {
                    continue;
                }
                if ((int)$receipt['erp_storehouse_id'] <= 0) {
                    throw new RuntimeException('La bodega de recepción no tiene equivalencia ERP.');
                }
                $items[] = [
                    'container_item_id' => $containerItemId,
                    'purchase_order_line_id' => (int)($line['id'] ?? 0),
                    'erp_item_id' => (int)($line['erp_item_id'] ?? 0),
                    'received_rolls' => round((float)$receipt['received_qty'], 10),
                    'received_weight_kg' => round((float)$receipt['weight_kg'], 2),
                    'erp_storehouse_id' => (int)$receipt['erp_storehouse_id'],
                ];
            }
        }

        $annotation = 'Recepción sincronizada por sistema Unibag';
        $effectiveUserId = $userId > 0 ? $userId : 1;
        foreach ($this->getErpSyncTargets() as $targetPdo) {
            $this->syncImportContainerErpReceptionTransactionToPdo(
                $targetPdo,
                $containerId,
                $effectiveUserId,
                $annotation,
                $items
            );
        }
    }

    private function syncImportContainerErpReceptionTransactionToPdo(
        PDO $erpPdo,
        int $containerId,
        int $effectiveUserId,
        string $annotation,
        array $items
    ): void {
        $hasPreReception = $erpPdo->query("SHOW TABLES LIKE 'stockchanges_preconteo'")->fetchColumn();
        if ($hasPreReception !== false) {
            $this->syncImportContainerPreReception($erpPdo, $containerId, $effectiveUserId, $annotation, $items);
            return;
        }
        $header = $this->getImportContainerErpHeaderForReceptionSync($containerId, $erpPdo);
        if ($header === null) {
            throw new RuntimeException('El contenedor no existe en una de las bases ERP configuradas.');
        }

        $now = time();

        try {
            $erpPdo->beginTransaction();
            $lockHeader = $erpPdo->prepare('SELECT id FROM supplier_contenedor WHERE id = :id FOR UPDATE');
            $lockHeader->execute([':id' => $containerId]);

            $foreignReceipt = $erpPdo->prepare(
                'SELECT COUNT(*) FROM stockchanges
                 WHERE sth_supporder_contenedorid = :container_id
                   AND (stk_annotation IS NULL OR stk_annotation <> :annotation)'
            );
            $foreignReceipt->execute([':container_id' => $containerId, ':annotation' => $annotation]);
            if ((int)$foreignReceipt->fetchColumn() > 0) {
                throw new RuntimeException('Existe una recepción creada en el ERP antiguo. No se generará otra que duplique sus cantidades; revisa la conciliación del contenedor.');
            }

            $existingId = 0;
            $stmtExisting = $erpPdo->prepare(
                'SELECT id
                 FROM stockchanges
                 WHERE sth_supporder_contenedorid = :container_id
                   AND stk_annotation = :annotation
                 ORDER BY id DESC
                 LIMIT 1 FOR UPDATE'
            );
            $stmtExisting->execute([
                ':container_id' => $containerId,
                ':annotation' => $annotation,
            ]);
            $existingId = (int)($stmtExisting->fetchColumn() ?: 0);

            if ($existingId > 0) {
                $costStmt = $erpPdo->prepare(
                    'SELECT COUNT(*) FROM tran_data WHERE tran_id = :id
                     AND tran_type IN ("stockchangeup", "stockchangedown")'
                );
                $costStmt->execute([':id' => $existingId]);
                $pricedStmt = $erpPdo->prepare(
                    'SELECT COUNT(*) FROM stockchanges_items WHERE stk_id = :id
                     AND (COALESCE(item_costprice_netto, 0) <> 0 OR COALESCE(item_cont_costadd, 0) <> 0
                          OR COALESCE(item_cont_unitprc_amt, 0) <> 0 OR COALESCE(item_cont_unitprc_kg, 0) <> 0)'
                );
                $pricedStmt->execute([':id' => $existingId]);
                if ((int)$costStmt->fetchColumn() > 0 || (int)$pricedStmt->fetchColumn() > 0) {
                    $oldStmt = $erpPdo->prepare(
                        'SELECT item_contenedor_refid, item_st_id, item_amount, item_cont_kg_received
                         FROM stockchanges_items WHERE stk_id = :id'
                    );
                    $oldStmt->execute([':id' => $existingId]);
                    $oldValues = [];
                    foreach ($oldStmt->fetchAll(PDO::FETCH_ASSOC) as $oldItem) {
                        $oldValues[(int)$oldItem['item_contenedor_refid'] . ':' . (int)$oldItem['item_st_id']] = [
                            number_format((float)$oldItem['item_amount'], 3, '.', ''),
                            number_format((float)$oldItem['item_cont_kg_received'], 2, '.', ''),
                        ];
                    }
                    $newValues = [];
                    foreach ($items as $item) {
                        $newValues[(int)$item['container_item_id'] . ':' . (int)$item['erp_storehouse_id']] = [
                            number_format((float)$item['received_rolls'], 3, '.', ''),
                            number_format((float)$item['received_weight_kg'], 2, '.', ''),
                        ];
                    }
                    ksort($oldValues);
                    ksort($newValues);
                    if ($oldValues === $newValues) {
                        $erpPdo->commit();
                        return;
                    }
                    throw new RuntimeException('La recepción ERP ya fue contabilizada o costeada; no se reemplazará. Revisa el ajuste en el ERP antiguo.');
                }
            }

            if ($items === []) {
                if ($existingId > 0) {
                    $erpPdo->prepare('DELETE FROM stockchanges_items WHERE stk_id = :stk_id')
                        ->execute([':stk_id' => $existingId]);
                    $erpPdo->prepare(
                        'UPDATE stockchanges
                         SET stk_status = 1,
                             stk_upddat = :upddat,
                             stk_updusr = :updusr
                         WHERE id = :id'
                    )->execute([
                        ':upddat' => $now,
                        ':updusr' => $effectiveUserId,
                        ':id' => $existingId,
                    ]);
                }
                $erpPdo->commit();
                return;
            }

            if ($existingId > 0) {
                $erpPdo->prepare(
                    'UPDATE stockchanges
                     SET stk_issueid = 17,
                         stk_companyid = :company_id,
                         stk_shopid = :shop_id,
                         stk_bookdate = :bookdate,
                         stk_negative = 0,
                         stk_status = 2,
                         stk_upddat = :upddat,
                         stk_updusr = :updusr,
                         sth_supporder_contenedorid = :container_id
                     WHERE id = :id'
                )->execute([
                    ':company_id' => (int)$header['company_id'],
                    ':shop_id' => (int)$header['shop_id'],
                    ':bookdate' => $now,
                    ':upddat' => $now,
                    ':updusr' => $effectiveUserId,
                    ':container_id' => $containerId,
                    ':id' => $existingId,
                ]);

                $erpPdo->prepare('DELETE FROM stockchanges_items WHERE stk_id = :stk_id')
                    ->execute([':stk_id' => $existingId]);
            } else {
                $stockChangeNumber = $this->generateNextErpStockChangeNumber((int)$header['company_id'], $erpPdo);
                $erpPdo->prepare(
                    'INSERT INTO stockchanges
                     (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate,
                      stk_negative, stk_status, stk_crtdat, stk_crtusr, stk_isventainterna, stk_fixedsthid,
                      sth_supporder_contenedorid)
                     VALUES
                     (:stk_num, :annotation, 17, :company_id, :shop_id, :bookdate,
                      0, 2, :crtdat, :crtusr, 0, 0, :container_id)'
                )->execute([
                    ':stk_num' => $stockChangeNumber,
                    ':annotation' => $annotation,
                    ':company_id' => (int)$header['company_id'],
                    ':shop_id' => (int)$header['shop_id'],
                    ':bookdate' => $now,
                    ':crtdat' => $now,
                    ':crtusr' => $effectiveUserId,
                    ':container_id' => $containerId,
                ]);
                $existingId = (int)$erpPdo->lastInsertId();
            }

            $insertItem = $erpPdo->prepare(
                'INSERT INTO stockchanges_items
                 (stk_id, item_id, item_pos, item_amount, item_type, item_st_id,
                  item_contenedor_refid, item_contenedor_supporderposid, item_cont_kg_received)
                 VALUES
                 (:stk_id, :item_id, :item_pos, :item_amount, "item", :item_st_id,
                  :container_item_id, :purchase_order_line_id, :received_weight_kg)'
            );

            foreach ($items as $position => $item) {
                $storehouseId = (int)$item['erp_storehouse_id'];
                if ($storehouseId <= 0) {
                    throw new RuntimeException('Falta la bodega ERP para una línea recibida.');
                }
                $insertItem->execute([
                    ':stk_id' => $existingId,
                    ':item_id' => (int)$item['erp_item_id'],
                    ':item_pos' => $position,
                    ':item_amount' => number_format((float)$item['received_rolls'], 10, '.', ''),
                    ':item_st_id' => $storehouseId,
                    ':container_item_id' => (int)$item['container_item_id'],
                    ':purchase_order_line_id' => (int)$item['purchase_order_line_id'],
                    ':received_weight_kg' => number_format((float)$item['received_weight_kg'], 2, '.', ''),
                ]);
            }

            $erpPdo->prepare(
                'UPDATE supplier_contenedor SET sord_stock_pending_act = 1,
                 sord_upddat = :date, sord_updusr = :user WHERE id = :id'
            )->execute([':date' => $now, ':user' => $effectiveUserId, ':id' => $containerId]);
            $erpPdo->commit();
        } catch (Throwable $e) {
            if ($erpPdo->inTransaction()) {
                $erpPdo->rollBack();
            }
            throw $e;
        }
    }

    private function syncImportContainerPreReception(PDO $erpPdo, int $containerId, int $userId, string $annotation, array $items): void
    {
        $header = $this->getImportContainerErpHeaderForReceptionSync($containerId, $erpPdo);
        if ($header === null) {
            throw new RuntimeException('El contenedor no existe en ERP.');
        }
        if ((int)$header['company_id'] <= 0 || (int)$header['shop_id'] <= 0) {
            throw new RuntimeException('El contenedor debe tener empresa y sucursal validas en el ERP antes de enviar a precosteo.');
        }
        $erpPdo->beginTransaction();
        try {
            $lock = $erpPdo->prepare('SELECT id, sord_stock_pending_act FROM supplier_contenedor WHERE id = :id FOR UPDATE');
            $lock->execute([':id' => $containerId]);
            $lockedHeader = $lock->fetch(PDO::FETCH_ASSOC);
            if ((int)($lockedHeader['sord_stock_pending_act'] ?? 0) === 1) {
                throw new RuntimeException('El ERP ya habilito el ingreso posterior al costeo. No se reemplazara la recepcion ni se reiniciara el costeo.');
            }
            // Receipt belongs to pre-costing, never to booked stock.
            // La recepcion pertenece al precosteo, nunca al stock contabilizado.
            $final = $erpPdo->prepare('SELECT COUNT(*) FROM stockchanges WHERE sth_supporder_contenedorid = :id AND stk_status > 0');
            $final->execute([':id' => $containerId]);
            if ((int)$final->fetchColumn() > 0) {
                throw new RuntimeException('Hay un documento de ingreso final ERP activo. Conciliarlo antes de sincronizar el precosteo para evitar duplicar stock.');
            }
            $existing = $erpPdo->prepare('SELECT id, stk_annotation FROM stockchanges_preconteo WHERE sth_supporder_contenedorid = :id AND (stk_status > 0 OR stk_annotation = :annotation) FOR UPDATE');
            $existing->execute([':id' => $containerId, ':annotation' => $annotation]);
            $documents = $existing->fetchAll(PDO::FETCH_ASSOC);
            if (count($documents) > 1 || ($documents !== [] && $documents[0]['stk_annotation'] !== $annotation)) {
                throw new RuntimeException('Existe una recepcion nativa ERP. Conciliarla antes de agregar otra.');
            }
            $id = (int)($documents[0]['id'] ?? 0);
            $now = time();
            if ($id === 0 && $items !== []) {
                $insert = $erpPdo->prepare(
                    'INSERT INTO stockchanges_preconteo
                     (stk_num,stk_annotation,stk_issueid,stk_companyid,stk_shopid,stk_bookdate,stk_status,
                      stk_crtdat,stk_crtusr,sth_supporder_contenedorid,stk_cinumber,stk_vintnumber,
                      sth_order_num,sth_fromrebo_agid_grphash)
                     VALUES (:num,:annotation,17,:company,:shop,:date,2,:created,:user,:container,"","","","")'
                );
                $insert->execute([':num' => $this->generateNextErpStockChangeNumber((int)$header['company_id'], $erpPdo),
                    ':annotation' => $annotation, ':company' => (int)$header['company_id'], ':shop' => (int)$header['shop_id'],
                    ':date' => $now, ':created' => $now, ':user' => $userId, ':container' => $containerId]);
                $id = (int)$erpPdo->lastInsertId();
            }
            if ($id > 0) {
                $priced = $erpPdo->prepare('SELECT COUNT(*) FROM stockchanges_preconteo_items WHERE stk_id = :id AND COALESCE(item_costprice_netto,0) <> 0');
                $priced->execute([':id' => $id]);
                if ((int)$priced->fetchColumn() > 0) {
                    throw new RuntimeException('El precosteo ya tiene precios; no se reemplazara.');
                }
                $erpPdo->prepare('DELETE FROM stockchanges_preconteo_items WHERE stk_id = :id')->execute([':id' => $id]);
                $insertItem = $erpPdo->prepare(
                    'INSERT INTO stockchanges_preconteo_items
                     (stk_id,item_id,item_pos,item_amount,item_type,item_st_id,item_contenedor_refid,item_contenedor_supporderposid)
                     VALUES (:id,:item,:pos,:amount,"item",:warehouse,:line,:po)'
                );
                foreach ($items as $pos => $item) {
                    $insertItem->execute([':id' => $id, ':item' => $item['erp_item_id'], ':pos' => $pos,
                        ':amount' => $item['received_rolls'], ':warehouse' => $item['erp_storehouse_id'],
                        ':line' => $item['container_item_id'], ':po' => $item['purchase_order_line_id']]);
                }
                $erpPdo->prepare('UPDATE stockchanges_preconteo SET stk_status = :status,stk_upddat = :date,stk_updusr = :user WHERE id = :id')
                    ->execute([':status' => $items === [] ? 0 : 2, ':date' => $now, ':user' => $userId, ':id' => $id]);
            }
            // Only ERP costing can enable stock entry; receipt must not enable it.
            // Solo el costeo ERP habilita el ingreso; la recepcion no debe habilitarlo.
            $erpPdo->prepare('UPDATE supplier_contenedor SET sord_upddat = :date,sord_updusr = :user WHERE id = :id')
                ->execute([':date' => $now, ':user' => $userId, ':id' => $containerId]);
            $erpPdo->commit();
        } catch (Throwable $e) {
            if ($erpPdo->inTransaction()) {
                $erpPdo->rollBack();
            }
            throw $e;
        }
    }

    private function getImportContainerErpHeaderForReceptionSync(int $containerId, ?PDO $erpPdo = null): ?array
    {
        $erpPdo ??= $this->erpPdo;
        if ($containerId <= 0 || $erpPdo === null) {
            return null;
        }

        $stmt = $erpPdo->prepare(
            'SELECT id, sord_company_id, sord_shop_id
             FROM supplier_contenedor
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $containerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int)($row['id'] ?? 0),
            'company_id' => (int)($row['sord_company_id'] ?? 0),
            'shop_id' => (int)($row['sord_shop_id'] ?? 0),
        ];
    }

    /**
     * @param array<int, int|string> $containerItemIds
     * @return array<int, int>
     */
    private function getDominantErpStorehouseByImportContainerItemIds(array $containerItemIds): array
    {
        $containerItemIds = array_values(array_filter(array_map(static fn($id): int => (int)$id, $containerItemIds), static fn(int $id): bool => $id > 0));
        if ($containerItemIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($containerItemIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT r.import_container_item_id,
                    COALESCE(w.erp_storehouse_id, 0) AS erp_storehouse_id,
                    COALESCE(SUM(r.received_qty), 0) AS total_qty,
                    COUNT(*) AS total_rolls,
                    MAX(r.id) AS last_roll_id
             FROM rolls r
             INNER JOIN warehouses w ON w.id = r.warehouse_id
             WHERE r.import_container_item_id IN ($placeholders)
             GROUP BY r.import_container_item_id, w.erp_storehouse_id
             ORDER BY r.import_container_item_id ASC, total_qty DESC, total_rolls DESC, last_roll_id DESC"
        );
        $stmt->execute($containerItemIds);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $containerItemId = (int)($row['import_container_item_id'] ?? 0);
            if ($containerItemId <= 0 || isset($result[$containerItemId])) {
                continue;
            }
            $result[$containerItemId] = (int)($row['erp_storehouse_id'] ?? 0);
        }

        return $result;
    }

    private function generateNextErpStockChangeNumber(int $companyId, ?PDO $erpPdo = null): string
    {
        $erpPdo ??= $this->erpPdo;
        if ($erpPdo === null) {
            return 'IA' . sprintf('%08d', time() % 100000000);
        }
        $companyId = $companyId > 0 ? $companyId : 20010;
        $ownsTransaction = !$erpPdo->inTransaction();
        try {
            if ($ownsTransaction) {
                $erpPdo->beginTransaction();
            }
            $stmt = $erpPdo->prepare(
                'SELECT company_numformat_stockchange, company_numcounter_stockchange
                 FROM company_data
                 WHERE id = :id
                 FOR UPDATE'
            );
            $stmt->execute([':id' => $companyId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $prefix = trim((string)($row['company_numformat_stockchange'] ?? ''));
            if ($prefix === '') {
                $prefix = 'IA';
            }
            $nextCounter = ((int)($row['company_numcounter_stockchange'] ?? 33000)) + 1;

            $erpPdo->prepare(
                'UPDATE company_data
                 SET company_numcounter_stockchange = :counter
                 WHERE id = :id'
            )->execute([
                ':counter' => $nextCounter,
                ':id' => $companyId,
            ]);

            if ($ownsTransaction) {
                $erpPdo->commit();
            }
            return $prefix . sprintf('%08d', $nextCounter);
        } catch (Throwable) {
            if ($ownsTransaction && $erpPdo->inTransaction()) {
                $erpPdo->rollBack();
            }
            return 'IA' . sprintf('%08d', time() % 100000000);
        }
    }

    /**
     * @return list<PDO>
     */
    private function getErpSyncTargets(): array
    {
        if ($this->legacyErpConnectionError !== null) {
            throw new RuntimeException('No se pudo conectar al ERP antiguo: ' . $this->legacyErpConnectionError);
        }
        $targets = [$this->erpPdo];
        if ($this->legacyErpPdo instanceof PDO && $this->legacyErpPdo !== $this->erpPdo) {
            $targets[] = $this->legacyErpPdo;
        }
        return $targets;
    }

    private function updateImportContainerClosureAcrossErps(int $containerId, int $userId = 0): bool
    {
        $ok = true;
        $params = [
            ':upddat' => time(),
            ':updusr' => $userId > 0 ? $userId : 1,
            ':id' => $containerId,
        ];

        foreach ($this->getErpSyncTargets() as $erpPdo) {
            try {
                $hasPreReception = $erpPdo->query("SHOW TABLES LIKE 'stockchanges_preconteo'")->fetchColumn() !== false;
                $stmt = $erpPdo->prepare(
                    'UPDATE supplier_contenedor
                     SET sord_status = CASE
                             WHEN COALESCE(sord_stock_pending_act, 0) = 1 AND :pre_reception = 0 THEN sord_status
                             WHEN sord_status = 4 THEN 4
                             ELSE 3
                         END,
                         sord_upddat = :upddat,
                         sord_updusr = :updusr
                     WHERE id = :id'
                );
                $result = $stmt->execute($params + [':pre_reception' => $hasPreReception ? 1 : 0]);
                $ok = $ok && $result;
            } catch (Throwable) {
                $ok = false;
                continue;
            }
        }

        return $ok;
    }

    private function updateImportContainerReceivedAcrossErps(int $containerId, int $userId = 0): bool
    {
        $ok = true;
        $params = [
            ':upddat' => time(),
            ':updusr' => $userId > 0 ? $userId : 1,
            ':id' => $containerId,
        ];

        foreach ($this->getErpSyncTargets() as $erpPdo) {
            try {
                $stmt = $erpPdo->prepare(
                    'UPDATE supplier_contenedor
                     SET sord_upddat = :upddat,
                         sord_updusr = :updusr
                     WHERE id = :id AND sord_status < 3'
                );
                $result = $stmt->execute($params);
                $ok = $ok && $result;
            } catch (Throwable) {
                $ok = false;
                continue;
            }
        }

        return $ok;
    }

}

class ImportContainerService
{
    use ImportContainerServiceTrait;

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
