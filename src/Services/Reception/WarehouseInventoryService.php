<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Bodegas, Cajas, Pallets, Maquila y Toma de Inventario
// (src/Services/Reception/WarehouseInventoryService.php)
// =============================================================================

trait WarehouseInventoryServiceTrait
{
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

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return ['ok' => true, 'errors' => []];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['ok' => false, 'errors' => ['transfer' => 'No se pudo registrar el traspaso entre bodegas: ' . $e->getMessage()]];
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
        $stmt = $this->pdo->prepare(
            'SELECT w.id, w.code, w.name
             FROM warehouses w
             INNER JOIN (
                SELECT erp_storehouse_id, MIN(id) AS keep_id
                FROM warehouses
                WHERE erp_storehouse_id IS NOT NULL
                GROUP BY erp_storehouse_id
             ) valid ON valid.keep_id = w.id
             ORDER BY w.code ASC, w.name ASC'
        );
        $stmt->execute();
        return $this->onlyErpWarehouses($stmt->fetchAll());
    }

    /** Quita de la vista las bodegas de TRZ que no existen en el ERP (no borra datos). */
    private function onlyErpWarehouses(array $rows): array
    {
        require_once dirname(__DIR__, 2) . '/WarehouseVisibility.php';
        return WarehouseVisibility::filterTrz($rows, $this->erpPdo ?? null);
    }

    private function getReceptionWarehouseById(int $warehouseId): ?array
    {
        if ($warehouseId <= 0) {
            return null;
        }

        $this->syncWarehousesFromErp();
        $stmt = $this->pdo->prepare(
            'SELECT id, code, name, erp_storehouse_id
             FROM warehouses
             WHERE id = :id
               AND erp_storehouse_id IS NOT NULL
             LIMIT 1'
        );
        $stmt->execute([':id' => $warehouseId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
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



    public function listWarehouses(bool $refreshFromErp = true): array
    {
        if ($refreshFromErp) {
            $this->syncWarehousesFromErp();
        }
        $stmt = $this->pdo->prepare('SELECT id, code, name, erp_storehouse_id FROM warehouses ORDER BY code ASC');
        $stmt->execute();
        return $this->onlyErpWarehouses($stmt->fetchAll());
    }

    /**
     * Normaliza códigos escaneados desde pistolas/lectores que a veces
     * transforman el guion en apóstrofe según layout o simbología.
     */
    private function normalizeScannedInventoryCode(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }

        $code = str_replace(["\r", "\n", "\t", ' '], '', $code);
        $code = str_replace(["'", '`', '´', '’', '‘', '‛'], '-', $code);

        return strtoupper($code);
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
        $warehouses = $this->onlyErpWarehouses($stmt->fetchAll());

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

    public function createErpInventoryCount(int $storehouseId, string $storehouseName, string $annotation, string $operatorName, array $items, string $request = ''): array
    {
        return $this->inventoryCountService->createErpInventoryCount($storehouseId, $storehouseName, $annotation, $operatorName, $items, $request);
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


}

#[AllowDynamicProperties]
class WarehouseInventoryService
{
    use WarehouseInventoryServiceTrait;

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
