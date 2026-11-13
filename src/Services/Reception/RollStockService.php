<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Datos · Movimientos de Stock y Trazabilidad de Bobinas (src/Services/Reception/RollStockService.php)
// =============================================================================

require_once dirname(__DIR__, 2) . '/StockLedgerService.php';
require_once dirname(__DIR__, 2) . '/BagBoxService.php';
trait RollStockServiceTrait
{
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
        $code = $this->normalizeScannedInventoryCode($code);
        if ($code === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $code) === 1) {
            $roll = $this->getRoll((int)$code);
            if ($roll !== null) {
                return $roll;
            }
        }

        // 1. Búsqueda directa por roll_code en rolls
        $stmt = $this->pdo->prepare('SELECT id FROM rolls WHERE UPPER(roll_code) = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        if ($row !== false && (int)$row['id'] > 0) {
            return $this->getRoll((int)$row['id']);
        }

        // 2. Búsqueda por código de trazabilidad rotulado en traceability_warehouse_entries
        $stmtTrz = $this->pdo->prepare('SELECT entity_id FROM traceability_warehouse_entries WHERE UPPER(traceability_code) = :code AND entity_type = "ROLL" LIMIT 1');
        $stmtTrz->execute([':code' => $code]);
        $trzRow = $stmtTrz->fetch();
        if ($trzRow !== false && (int)$trzRow['entity_id'] > 0) {
            return $this->getRoll((int)$trzRow['entity_id']);
        }

        // 3. Soporte para prefijo ROLL-{id} o R-{id}
        if (preg_match('/^(?:ROLL|ROL|R)[-_](\d+)$/i', $code, $m) === 1) {
            $roll = $this->getRoll((int)$m[1]);
            if ($roll !== null) {
                return $roll;
            }
        }

        return null;
    }

    public function ensureTransferableRollByCode(
        string $code,
        int $warehouseId,
        string $operatorName = '',
        string $skuCode = '',
        string $skuDescription = ''
    ): array {
        $code = $this->normalizeScannedInventoryCode($code);
        if ($code === '') {
            return ['ok' => false, 'errors' => ['code' => 'Debe indicar un código de bobina válido.']];
        }

        $existing = $this->getRollByScanCode($code);
        if ($existing !== null) {
            return ['ok' => true, 'roll_id' => (int)$existing['id'], 'roll' => $existing, 'created' => false];
        }

        $stmtWarehouse = $this->pdo->prepare('SELECT id FROM warehouses WHERE id = :id LIMIT 1');
        $stmtWarehouse->execute([':id' => $warehouseId]);
        if ($stmtWarehouse->fetch() === false) {
            $warehouseId = $this->findWarehouseIdByCode(100) ?? 1;
        }

        $skuCode = trim($skuCode) !== '' ? trim($skuCode) : 'LEGACY-ROLL';
        $skuDescription = trim($skuDescription) !== '' ? trim($skuDescription) : 'Bobina legacy registrada desde traspaso';

        $stmtSku = $this->pdo->prepare('SELECT id FROM skus WHERE code = :code LIMIT 1');
        $stmtSku->execute([':code' => $skuCode]);
        $skuId = (int)$stmtSku->fetchColumn();
        if ($skuId <= 0) {
            $stmtInsertSku = $this->pdo->prepare(
                'INSERT INTO skus (code, description, is_active, created_at) VALUES (:code, :description, 1, NOW())'
            );
            $stmtInsertSku->execute([
                ':code' => $skuCode,
                ':description' => $skuDescription,
            ]);
            $skuId = (int)$this->pdo->lastInsertId();
        }

        $stmtInsertRoll = $this->pdo->prepare(
            'INSERT INTO rolls (
                roll_code, sku_id, warehouse_id, weight_kg, received_qty, microns, width_mm, color, meters,
                reception_mode, status, process_stage, created_at
            ) VALUES (
                :roll_code, :sku_id, :warehouse_id, 0, 1, 0, 0, :color, 0,
                :reception_mode, :status, :process_stage, NOW()
            )'
        );
        $stmtInsertRoll->execute([
            ':roll_code' => $code,
            ':sku_id' => $skuId > 0 ? $skuId : 1,
            ':warehouse_id' => $warehouseId,
            ':color' => '-',
            ':reception_mode' => 'LEGACY_TRANSFER',
            ':status' => 'RECEIVED',
            ':process_stage' => 'RAW',
        ]);

        $rollId = (int)$this->pdo->lastInsertId();
        $this->insertEvent('ROLL_REGISTERED_FROM_SCAN', [
            'roll_id' => $rollId,
            'roll_code' => $code,
            'operator_name' => trim($operatorName),
            'warehouse_id' => $warehouseId,
            'sku_code' => $skuCode,
            'sku_description' => $skuDescription,
        ]);
        $this->syncRollWarehouseTraceabilityEntry($rollId, trim($operatorName));

        $roll = $this->getRoll($rollId);
        return ['ok' => $roll !== null, 'roll_id' => $rollId, 'roll' => $roll, 'created' => true];
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
            } elseif (!in_array($rollStatus, ['RECEIVED', 'IN_PROCESS'], true)) {
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

            $this->syncRollWarehouseTraceabilityEntry($rollId, $operatorName);
            $this->syncErpInventoryForRollTransfer($rollId, $fromWarehouseId, $targetWarehouseId);

            $this->pdo->commit();
            return ['ok' => true, 'errors' => []];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function transferRollTraceabilityOnly(int $rollId, int $toWarehouseId, string $operatorName = ''): array
    {
        $errors = [];
        $operatorName = trim($operatorName);

        $roll = $this->getRoll($rollId);
        if ($roll === null) {
            return ['ok' => false, 'errors' => ['roll' => 'Bobina no existe.']];
        }

        $fromWarehouseId = (int)$roll['warehouse_id'];
        if ($toWarehouseId <= 0) {
            $errors['warehouse_id'] = 'Bodega destino es obligatoria.';
        } elseif ($toWarehouseId === $fromWarehouseId) {
            $errors['warehouse_id'] = 'Bodega destino debe ser distinta a la actual.';
        } else {
            $stmt = $this->pdo->prepare('SELECT id FROM warehouses WHERE id = :id');
            $stmt->execute([':id' => $toWarehouseId]);
            if ($stmt->fetch() === false) {
                $errors['warehouse_id'] = 'Bodega destino no existe.';
            }
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }

        $rollStatus = strtoupper(trim((string)($roll['status'] ?? '')));
        $rollCurrentWorkOrderId = (int)($roll['current_work_order_id'] ?? 0);
        if ($rollCurrentWorkOrderId > 0) {
            $errors['roll'] = 'La bobina está asignada a una OT y no se puede mover solo en trazabilidad.';
        } elseif (!in_array($rollStatus, ['RECEIVED', 'IN_PROCESS'], true)) {
            $errors['roll'] = 'Solo se pueden mover en trazabilidad bobinas disponibles.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE rolls SET warehouse_id = :to, current_work_order_id = NULL, status = :status WHERE id = :id');
            $stmt->execute([
                ':to' => $toWarehouseId,
                ':status' => 'RECEIVED',
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
                ':to_warehouse_id' => $toWarehouseId,
                ':payload' => json_encode([
                    'operator_name' => $operatorName,
                    'traceability_only' => true,
                    'inventory_impact' => false,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $this->insertEvent('ROLL_TRACEABILITY_TRANSFERRED', [
                'roll_id' => $rollId,
                'roll_code' => (string)($roll['roll_code'] ?? ''),
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'operator_name' => $operatorName,
                'traceability_only' => true,
                'inventory_impact' => false,
            ]);

            $this->syncRollWarehouseTraceabilityEntry($rollId, $operatorName);

            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return ['ok' => true, 'errors' => []];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['ok' => false, 'errors' => ['transfer' => 'No se pudo registrar el traspaso solo de trazabilidad: ' . $e->getMessage()]];
        }
    }

    /**
     * Traspasa una bobina desde Bodega Central a la Planta de Producción (Bodega 3000).
     * Registra en la base de datos de trazabilidad (TRZ) y en ERP quién la solicitó,
     * quién la sacó, para qué máquina fue y en qué OT se ocupó, asignando o confirmando
     * su código único de trazabilidad.
     */
    public function dispatchWarehouseTransferToProduction(array $data, string $currentOperatorName): array
    {
        $quienSolicito = trim((string)($data['quien_la_solicito'] ?? ''));
        $quienSaco = trim((string)($data['quien_la_saco'] ?? $currentOperatorName));
        if ($quienSaco === '') {
            $quienSaco = $currentOperatorName ?: 'Bodeguero';
        }
        $machineId = (int)($data['machine_id'] ?? 0);
        $machineName = trim((string)($data['machine_name'] ?? ''));
        $otId = (int)($data['ot_id'] ?? 0);
        $otNumber = trim((string)($data['ot_number'] ?? ''));
        $clientName = trim((string)($data['client_name'] ?? ''));
        $comments = trim((string)($data['comments'] ?? ''));
        $weightKg = (float)str_replace(',', '.', (string)($data['weight_kg'] ?? 0));
        $fromWarehouseId = (int)($data['from_warehouse_id'] ?? 1);

        $rollId = (int)($data['roll_id'] ?? 0);
        $scanCode = trim((string)($data['scan_code'] ?? $data['code'] ?? ''));
        $sku = trim((string)($data['sku'] ?? ''));
        $skuDesc = trim((string)($data['sku_desc'] ?? ''));
        $color = trim((string)($data['color'] ?? ''));
        $widthMm = (int)($data['width_mm'] ?? 0);
        if ($widthMm <= 0 && !empty($data['width_cm'])) {
            $widthMm = (int)round((float)$data['width_cm'] * 10);
        }
        $meters = (float)($data['meters'] ?? 0);
        $material = trim((string)($data['material'] ?? 'TNT'));

        // 1. Resolver bobina si existe en TRZ
        $roll = null;
        if ($rollId > 0) {
            $roll = $this->getRoll($rollId);
        } elseif ($scanCode !== '') {
            $roll = $this->getRollByScanCode($scanCode);
            if ($roll !== null) {
                $rollId = (int)$roll['id'];
            }
        }

        // 2. Resolver Bodega 3000 en TRZ
        $toWarehouseId = $this->findWarehouseIdByCode(3000);
        if ($toWarehouseId === null || $toWarehouseId <= 0) {
            // La 3000 es la bodega 16 del ERP. Antes se creaba una «3000 (MP PRODUCCIÓN)» con la bodega 27, que se
            // mostraba como bodega aparte.
            $stW = $this->pdo->query("SELECT id FROM warehouses WHERE erp_storehouse_id = 16 OR code = 3000 ORDER BY (erp_storehouse_id = 16) DESC, id LIMIT 1");
            $toWarehouseId = $stW ? (int)$stW->fetchColumn() : 0;
            if ($toWarehouseId <= 0) {
                $this->pdo->prepare("INSERT INTO warehouses (code, name, erp_storehouse_id) VALUES (3000, '3000 INSUMOS EN PRODUCCION', 16)")->execute();
                $toWarehouseId = (int)$this->pdo->lastInsertId();
            }
        }

        $now = time();

        // 3. Si la bobina ya existe en TRZ
        if ($roll !== null && $rollId > 0) {
            $rollCode = (string)$roll['roll_code'];
            $fromWarehouseId = (int)($roll['warehouse_id'] ?? $fromWarehouseId);
            $sku = (string)($roll['sku_code'] ?? $sku);
            $skuDesc = (string)($roll['sku_description'] ?? $skuDesc);
            if ($weightKg <= 0) {
                $weightKg = (float)$roll['weight_kg'];
            }

            $this->pdo->prepare("
                UPDATE rolls
                SET warehouse_id = :to_wh,
                    status = 'IN_PROCESS',
                    process_stage = 'PRODUCTION',
                    current_work_order_id = :ot_id,
                    weight_kg = :w
                WHERE id = :id
            ")->execute([
                ':to_wh' => $toWarehouseId,
                ':ot_id' => $otId > 0 ? $otId : null,
                ':w' => $weightKg,
                ':id' => $rollId,
            ]);
        } else {
            // 4. Bobina nueva o de ERP: Asignar código de trazabilidad único
            $skuClean = strtoupper(preg_replace('/[^A-Z0-9]/', '', $sku ?: 'BOB'));
            $rollCode = "BOB-{$skuClean}-" . date('ymd') . "-" . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
            if ($weightKg <= 0) {
                $weightKg = 0.0;
            }

            // Asegurar SKU en TRZ
            $skuId = 0;
            if ($sku !== '') {
                $stS = $this->pdo->prepare("SELECT id FROM skus WHERE code = :c LIMIT 1");
                $stS->execute([':c' => $sku]);
                $skuId = (int)$stS->fetchColumn();
                if ($skuId <= 0) {
                    $this->pdo->prepare("INSERT INTO skus (code, description, is_active) VALUES (:c, :d, 1)")
                        ->execute([':c' => $sku, ':d' => $skuDesc ?: $sku]);
                    $skuId = (int)$this->pdo->lastInsertId();
                }
            }
            if ($skuId <= 0) {
                $skuId = (int)$this->pdo->query("SELECT id FROM skus ORDER BY id ASC LIMIT 1")->fetchColumn();
            }

            $insRoll = $this->pdo->prepare("
                INSERT INTO rolls
                    (roll_code, sku_id, warehouse_id, weight_kg, received_qty, reception_mode,
                     microns, width_mm, color, meters, status, current_work_order_id, process_stage, created_at)
                VALUES
                    (:code, :sku_id, :wh_id, :weight_kg, 1, 'TRASP_PROD',
                     80, :width_mm, :color, :meters, 'IN_PROCESS', :ot_id, 'PRODUCTION', NOW())
            ");
            $insRoll->execute([
                ':code' => $rollCode,
                ':sku_id' => $skuId,
                ':wh_id' => $toWarehouseId,
                ':weight_kg' => $weightKg,
                ':width_mm' => $widthMm,
                ':color' => $color ?: '-',
                ':meters' => $meters,
                ':ot_id' => $otId > 0 ? $otId : null,
            ]);
            $rollId = (int)$this->pdo->lastInsertId();
        }

        // 5. Registrar movimiento en TRZ
        try {
            $stmtMov = $this->pdo->prepare("
                INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload)
                VALUES ('ROLL', :entity_id, 'TRANSFER', :from_wh, :to_wh, :payload)
            ");
            $stmtMov->execute([
                ':entity_id' => $rollId,
                ':from_wh' => $fromWarehouseId > 0 ? $fromWarehouseId : 1,
                ':to_wh' => $toWarehouseId,
                ':payload' => json_encode([
                    'roll_code' => $rollCode,
                    'quien_la_solicito' => $quienSolicito,
                    'quien_la_saco' => $quienSaco,
                    'machine_id' => $machineId,
                    'machine_name' => $machineName,
                    'ot_id' => $otId,
                    'ot_number' => $otNumber,
                    'client_name' => $clientName,
                    'comments' => $comments,
                    'destination' => 'Bodega 3000 (Planta)',
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable) {}

        // 6. Registrar evento en TRZ events
        try {
            $stmtEvt = $this->pdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
            $stmtEvt->execute([
                ':type' => 'ROLL_TRANSFERRED_TO_PRODUCTION',
                ':payload' => json_encode([
                    'roll_id' => $rollId,
                    'roll_code' => $rollCode,
                    'sku' => $sku,
                    'sku_desc' => $skuDesc,
                    'weight_kg' => $weightKg,
                    'quien_la_solicito' => $quienSolicito,
                    'quien_la_saco' => $quienSaco,
                    'machine_id' => $machineId,
                    'machine_name' => $machineName,
                    'ot_id' => $otId,
                    'ot_number' => $otNumber,
                    'client_name' => $clientName,
                    'from_warehouse_id' => $fromWarehouseId,
                    'to_warehouse_id' => $toWarehouseId,
                    'to_warehouse_name' => 'Bodega 3000 (Producción)',
                    'comments' => $comments,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable) {}

        $this->syncErpInventoryForRollTransfer($rollId, $fromWarehouseId, $toWarehouseId, 'PRODUCTION_TRANSFER');

        // 7. Sincronizar en ERP (unibag_unibag)
        if ($this->erpPdo !== null) {
            try {
                $wotId = 0;
                if ($otNumber !== '') {
                    $stmtWot = $this->erpPdo->prepare("
                        SELECT pwo.id 
                        FROM prod_worker_ot pwo
                        JOIN prod_agenda pa ON pwo.wok_ag_id = pa.id
                        JOIN orders_classify_subproducts ocs ON pa.ag_order_id = ocs.id
                        WHERE (ocs.prd_number = :ot OR pa.id = :otid) AND pwo.wok_status = 1
                        ORDER BY pwo.id DESC LIMIT 1
                    ");
                    $stmtWot->execute([':ot' => $otNumber, ':otid' => $otId]);
                    $wotId = (int)$stmtWot->fetchColumn();
                }

                $evtComment = "Traspaso Bodega Central -> Planta (Bodega 3000): Bobina {$rollCode} ({$weightKg} kg)";
                if ($quienSolicito !== '') $evtComment .= " solicitada por {$quienSolicito}";
                if ($quienSaco !== '') $evtComment .= ", despachada por {$quienSaco}";
                if ($machineName !== '') $evtComment .= " para máquina {$machineName}";
                if ($otNumber !== '') $evtComment .= " (OT #{$otNumber})";
                if ($comments !== '') $evtComment .= " - {$comments}";

                if ($wotId > 0) {
                    $this->erpPdo->prepare("
                        INSERT INTO prod_worker_ot_events
                         (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments)
                        VALUES
                         (:ot_id, :amt, :crtdat, 1, 'materiales', :comments)
                    ")->execute([
                        ':ot_id' => $wotId,
                        ':amt' => $weightKg,
                        ':crtdat' => $now,
                        ':comments' => $evtComment,
                    ]);
                }
            } catch (Throwable) {}
        }

        return [
            'ok' => true,
            'roll_id' => $rollId,
            'roll_code' => $rollCode,
            'weight_kg' => $weightKg,
            'ot_number' => $otNumber,
            'machine_name' => $machineName,
            'quien_la_solicito' => $quienSolicito,
            'quien_la_saco' => $quienSaco,
            'message' => "Bobina {$rollCode} despachada a Bodega 3000 y asignada a la OT #{$otNumber} exitosamente.",
        ];
    }

    /**
     * Lista el historial reciente de traspasos a Bodega 3000 (Planta).
     */
    public function listProductionTransferHistory(int $limit = 50): array
    {
        $history = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT e.id, e.type, e.payload, e.created_at
                FROM events e
                WHERE e.type IN ('ROLL_TRANSFERRED_TO_PRODUCTION', 'ROLL_LOADED_TO_OT', 'ROLL_DISPATCHED_TO_PLANT')
                ORDER BY e.id DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $p = json_decode((string)$r['payload'], true) ?: [];
                $history[] = [
                    'id' => (int)$r['id'],
                    'created_at' => $r['created_at'],
                    'roll_id' => (int)($p['roll_id'] ?? 0),
                    'roll_code' => (string)($p['roll_code'] ?? ($p['roll_identifier'] ?? '-')),
                    'sku' => (string)($p['sku'] ?? ($p['sku_code'] ?? '-')),
                    'sku_desc' => (string)($p['sku_desc'] ?? ''),
                    'weight_kg' => (float)($p['weight_kg'] ?? 0),
                    'quien_la_solicito' => (string)($p['quien_la_solicito'] ?? ($p['operator'] ?? '-')),
                    'quien_la_saco' => (string)($p['quien_la_saco'] ?? '-'),
                    'machine_name' => (string)($p['machine_name'] ?? '-'),
                    'ot_number' => (string)($p['ot_number'] ?? ($p['worker_ot_id'] ?? '-')),
                    'client_name' => (string)($p['client_name'] ?? '-'),
                    'destination' => (string)($p['destination_warehouse'] ?? 'Bodega 3000'),
                    'comments' => (string)($p['comments'] ?? ''),
                ];
            }
        } catch (Throwable) {}
        return $history;
    }

    /**
     * Lista solicitudes de materiales y OTs activas/agendadas en planta.
     */
    public function listPendingMaterialRequestsFromProduction(int $limit = 30): array
    {
        $requests = [];
        if ($this->erpPdo !== null) {
            try {
                $equipoTable = $this->erpTableExists('equipo') ? 'equipo' : ($this->erpTableExists('equipos') ? 'equipos' : 'equipo');
                $stmt = $this->erpPdo->prepare("
                    SELECT pwe.id, pwe.evt_crtdat, pwe.evt_amount, pwe.evt_comments,
                           pwo.wok_ag_id, pa.ag_amount, pa.ag_equipoid,
                           eq.equipo_name, eq.id as equipo_id,
                           ocs.prd_number, ocs.req_number, c.cust_name, i.item_title, ocs.fab_type
                    FROM prod_worker_ot_events pwe
                    JOIN prod_worker_ot pwo ON pwe.evt_prod_worker_otid = pwo.id
                    JOIN prod_agenda pa ON pwo.wok_ag_id = pa.id
                    JOIN {$equipoTable} eq ON pa.ag_equipoid = eq.id
                    JOIN orders_classify_subproducts ocs ON pa.ag_order_id = ocs.id
                    JOIN orders o ON ocs.req_id = o.id
                    JOIN customers c ON o.req_cust_id = c.id
                    JOIN item i ON ocs.item_id = i.id
                    WHERE pwe.evt_type = 'materiales' AND pwe.evt_status = 1
                    ORDER BY pwe.id DESC
                    LIMIT :lim
                ");
                $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable) {}

            // Si no hay solicitudes explícitas de eventos, traer las OTs agendadas/activas para despacho rápido
            if (empty($requests)) {
                try {
                    $equipoTable = $this->erpTableExists('equipo') ? 'equipo' : 'equipo';
                    $stmtAg = $this->erpPdo->prepare("
                        SELECT pa.id AS wok_ag_id, pa.ag_amount, pa.ag_equipoid,
                               eq.equipo_name, eq.id AS equipo_id,
                               ocs.prd_number, ocs.req_number, c.cust_name, i.item_title, ocs.fab_type,
                               pa.ag_date
                        FROM prod_agenda pa
                        JOIN {$equipoTable} eq ON pa.ag_equipoid = eq.id
                        JOIN orders_classify_subproducts ocs ON pa.ag_order_id = ocs.id
                        JOIN orders o ON ocs.req_id = o.id
                        JOIN customers c ON o.req_cust_id = c.id
                        JOIN item i ON ocs.item_id = i.id
                        WHERE pa.ag_status IN (1, 2)
                        ORDER BY pa.id DESC
                        LIMIT :lim
                    ");
                    $stmtAg->bindValue(':lim', $limit, PDO::PARAM_INT);
                    $stmtAg->execute();
                    $agendaRows = $stmtAg->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    foreach ($agendaRows as $ag) {
                        $requests[] = [
                            'id' => 0,
                            'evt_crtdat' => time(),
                            'evt_amount' => 0,
                            'evt_comments' => 'Programada en planta - Pendiente de asignación de bobina',
                            'wok_ag_id' => (int)$ag['wok_ag_id'],
                            'ag_amount' => (float)$ag['ag_amount'],
                            'ag_equipoid' => (int)$ag['ag_equipoid'],
                            'equipo_name' => (string)$ag['equipo_name'],
                            'equipo_id' => (int)$ag['equipo_id'],
                            'prd_number' => (string)$ag['prd_number'],
                            'req_number' => (string)$ag['req_number'],
                            'cust_name' => (string)$ag['cust_name'],
                            'item_title' => (string)$ag['item_title'],
                            'fab_type' => (string)$ag['fab_type'],
                            'is_agenda' => true,
                        ];
                    }
                } catch (Throwable) {}
            }
        }
        return $requests;
    }

    /**
     * Lista equipos/máquinas operativas de planta.
     *
     * @return list<array{id: int, equipo_name: string}>
     */
    public function listProductionMachines(): array
    {
        if ($this->erpPdo !== null && $this->erpTableExists('equipo')) {
            try {
                $nameCol = $this->erpColumnExists('equipo', 'equipo_name') ? 'equipo_name' : 'name';
                $stmt = $this->erpPdo->query("
                    SELECT id, {$nameCol} AS equipo_name, equipo_type_id
                    FROM equipo
                    WHERE equipo_status > 0
                    ORDER BY {$nameCol} ASC
                ");
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                if (!empty($rows)) {
                    return $rows;
                }
            } catch (Throwable) {}
        }
        return [
            ['id' => 13, 'equipo_name' => 'FLEXO I.'],
            ['id' => 33, 'equipo_name' => 'FLEXO II.'],
            ['id' => 39, 'equipo_name' => 'FLEXO III.'],
            ['id' => 18, 'equipo_name' => 'SELLADORA I.'],
            ['id' => 20, 'equipo_name' => 'SELLADORA II.'],
            ['id' => 21, 'equipo_name' => 'SELLADORA III.'],
            ['id' => 22, 'equipo_name' => 'SELLADORA IV.'],
            ['id' => 23, 'equipo_name' => 'SELLADORA V.'],
            ['id' => 34, 'equipo_name' => 'SELLADORA VI.'],
            ['id' => 35, 'equipo_name' => 'SELLADORA VII'],
            ['id' => 14, 'equipo_name' => 'SERI I.'],
            ['id' => 17, 'equipo_name' => 'SERI II.'],
            ['id' => 38, 'equipo_name' => 'SERI III.'],
            ['id' => 36, 'equipo_name' => 'PULPO SERIGRAFICO'],
            ['id' => 15, 'equipo_name' => 'REBO I.'],
            ['id' => 32, 'equipo_name' => 'REBO II.'],
        ];
    }

    /**
     * Lista bobinas disponibles en bodega para traspaso a producción (Bodega 3000).
     */
    public function listAvailableRollsForTransfer(int $limit = 100): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT r.id, r.roll_code, r.weight_kg, r.status, r.warehouse_id,
                       w.code AS wh_code, w.name AS wh_name,
                       s.code AS sku_code, s.description AS sku_description
                FROM rolls r
                JOIN warehouses w ON r.warehouse_id = w.id
                JOIN skus s ON r.sku_id = s.id
                WHERE w.code NOT IN (3000, 16, 27)
                  AND (r.status = 'RECEIVED' OR r.status = 'IN_PROCESS')
                  AND (r.current_work_order_id IS NULL OR r.current_work_order_id = 0)
                ORDER BY r.id DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Lista todas las bobinas activas para el módulo de Traspaso Rápido Masivo entre bodegas.
     * Incluye bobinas madre, hijas cortadas (CUT), empalmes e impresas de todas las bodegas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAllRollsForWarehouseTransfer(?int $warehouseId = null, int $limit = 2000): array
    {
        try {
            $whereWh = '';
            if ($warehouseId !== null && $warehouseId > 0) {
                $whereWh = ' AND r.warehouse_id = :wh ';
            }
            $stmt = $this->pdo->prepare("
                SELECT r.id, r.roll_code, r.weight_kg, r.status, r.warehouse_id, r.process_stage,
                       r.parent_roll_id, r.meters, r.width_mm, r.created_at,
                       w.code AS wh_code, w.name AS wh_name,
                       s.code AS sku_code, s.description AS sku_description,
                       pr.roll_code AS parent_roll_code
                FROM rolls r
                JOIN warehouses w ON r.warehouse_id = w.id
                JOIN skus s ON r.sku_id = s.id
                LEFT JOIN rolls pr ON pr.id = r.parent_roll_id
                WHERE (r.status = 'RECEIVED' OR r.status = 'IN_PROCESS')
                  AND (r.current_work_order_id IS NULL OR r.current_work_order_id = 0)
                  {$whereWh}
                ORDER BY r.id DESC
                LIMIT :lim
            ");
            if ($warehouseId !== null && $warehouseId > 0) {
                $stmt->bindValue(':wh', $warehouseId, PDO::PARAM_INT);
            }
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Da de baja / descuenta una bobina que ya fue ocupada o consumida en producción.
     * Retira la bobina del inventario de bodega TRZ (traceability_warehouse_entries)
     * y marca su estado como CONSUMED, registrando la auditoría del operador (Javier).
     * Opcionalmente descuenta también el saldo contable en el ERP.
     */
    public function decommissionRoll(
        int $rollId,
        string $operatorName,
        string $reason = 'CONSUMED_IN_PRODUCTION',
        string $notes = '',
        bool $traceabilityOnly = true
    ): array {
        $roll = $this->getRoll($rollId);
        if ($roll === null) {
            return ['ok' => false, 'errors' => ['roll' => 'La bobina no existe en el sistema.']];
        }

        $rollStatus = strtoupper(trim((string)($roll['status'] ?? '')));
        if ($rollStatus === 'CONSUMED') {
            return ['ok' => false, 'errors' => ['roll' => 'La bobina ya está registrada como consumida.']];
        }

        $rollCode = trim((string)($roll['roll_code'] ?? ('#' . $rollId)));
        $fromWarehouseId = (int)($roll['warehouse_id'] ?? 0);
        $weightKg = (float)($roll['weight_kg'] ?? 0);
        $effectiveOperator = trim($operatorName) !== '' ? trim($operatorName) : 'Javier Perozo';

        $startedTx = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $startedTx = true;
        }
        try {
            // 1. Actualizar estado en rolls a CONSUMED
            $stmt = $this->pdo->prepare(
                'UPDATE rolls
                 SET status = :status, current_work_order_id = NULL
                 WHERE id = :id'
            );
            $stmt->execute([
                ':status' => 'CONSUMED',
                ':id' => $rollId,
            ]);

            // 2. Eliminar entrada en traceability_warehouse_entries (stock físico de bodega)
            $this->deleteRollWarehouseTraceabilityEntry($rollId, $rollCode);

            // 3. Registrar movimiento de ajuste/baja en movements
            $stmt = $this->pdo->prepare(
                'INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload)
                 VALUES (:entity_type, :entity_id, :movement_type, :from_warehouse_id, :to_warehouse_id, :payload)'
            );
            $stmt->execute([
                ':entity_type' => 'ROLL',
                ':entity_id' => $rollId,
                ':movement_type' => 'ADJUSTMENT',
                ':from_warehouse_id' => $fromWarehouseId > 0 ? $fromWarehouseId : null,
                ':to_warehouse_id' => null,
                ':payload' => json_encode([
                    'action' => 'DECOMMISSION',
                    'operator_name' => $effectiveOperator,
                    'reason' => $reason,
                    'notes' => $notes,
                    'roll_code' => $rollCode,
                    'weight_kg' => $weightKg,
                    'traceability_only' => $traceabilityOnly,
                    'decommissioned_at' => date('Y-m-d H:i:s'),
                ], JSON_UNESCAPED_UNICODE),
            ]);

            // 4. Registrar evento auditado en events
            $this->insertEvent('ROLL_DECOMMISSIONED', [
                'roll_id' => $rollId,
                'roll_code' => $rollCode,
                'operator_name' => $effectiveOperator,
                'reason' => $reason,
                'notes' => $notes,
                'weight_kg' => $weightKg,
                'from_warehouse_id' => $fromWarehouseId,
                'traceability_only' => $traceabilityOnly,
            ]);

            // 5. Descuento en ERP si no es solo trazabilidad
            if (!$traceabilityOnly && $fromWarehouseId > 0) {
                $this->syncErpInventoryForReceivedRoll($rollId, -1.0, 'CONSUMO_PRODUCCION');
            }

            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return ['ok' => true, 'errors' => []];
        } catch (Throwable $e) {
            if ($startedTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['ok' => false, 'errors' => ['decommission' => 'Error al dar de baja bobina ' . $rollCode . ': ' . $e->getMessage()]];
        }
    }

    /**
     * Da de baja en lote una lista de IDs de bobinas.
     */
    public function decommissionRollsBulk(
        array $rollIds,
        string $operatorName,
        string $reason = 'CONSUMED_IN_PRODUCTION',
        string $notes = '',
        bool $traceabilityOnly = true
    ): array {
        $successCount = 0;
        $failCount = 0;
        $totalWeight = 0.0;
        $errors = [];

        foreach ($rollIds as $rId) {
            $rId = (int)$rId;
            if ($rId <= 0) {
                continue;
            }
            $rInfo = $this->getRoll($rId);
            $rCode = (string)($rInfo['roll_code'] ?? "#{$rId}");
            $res = $this->decommissionRoll($rId, $operatorName, $reason, $notes, $traceabilityOnly);
            if ($res['ok'] ?? false) {
                $successCount++;
                $totalWeight += (float)($rInfo['weight_kg'] ?? 0);
            } else {
                $failCount++;
                $errStr = !empty($res['errors']) ? implode('; ', (array)$res['errors']) : 'Error desconocido';
                $errors[] = "{$rCode}: {$errStr}";
            }
        }

        return [
            'ok' => $successCount > 0,
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'total_weight' => round($totalWeight, 2),
            'errors' => $errors,
        ];
    }

    /**
     * Obtiene el historial de bobinas dadas de baja / consumidas por Javier.
     */
    public function listDecommissionedRollsHistory(int $limit = 60): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT r.id, r.roll_code, r.weight_kg, r.status, r.process_stage,
                       r.warehouse_id, w.code AS wh_code, w.name AS wh_name,
                       s.code AS sku_code, s.description AS sku_description,
                       pr.roll_code AS parent_roll_code,
                       m.id AS movement_id, m.created_at AS decommissioned_at,
                       m.payload AS movement_payload
                FROM movements m
                JOIN rolls r ON r.id = m.entity_id
                LEFT JOIN warehouses w ON w.id = r.warehouse_id
                LEFT JOIN skus s ON s.id = r.sku_id
                LEFT JOIN rolls pr ON pr.id = r.parent_roll_id
                WHERE m.entity_type = 'ROLL'
                  AND m.movement_type = 'ADJUSTMENT'
                  AND JSON_UNQUOTE(JSON_EXTRACT(m.payload, '$.action')) = 'DECOMMISSION'
                ORDER BY m.id DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Busca bobinas activas coincidentes por una lista de códigos (para escaneo/pegado masivo).
     */
    public function findRollsByCodes(array $codes): array
    {
        $normalized = [];
        foreach ($codes as $c) {
            $c = strtoupper(trim((string)$c));
            if ($c !== '') {
                $normalized[] = $c;
            }
        }
        $normalized = array_values(array_unique($normalized));
        if (empty($normalized)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($normalized), '?'));
        try {
            $stmt = $this->pdo->prepare("
                SELECT r.id, r.roll_code, r.weight_kg, r.status, r.warehouse_id, r.process_stage,
                       r.parent_roll_id, r.meters, r.width_mm, r.created_at,
                       w.code AS wh_code, w.name AS wh_name,
                       s.code AS sku_code, s.description AS sku_description,
                       pr.roll_code AS parent_roll_code
                FROM rolls r
                JOIN warehouses w ON r.warehouse_id = w.id
                JOIN skus s ON r.sku_id = s.id
                LEFT JOIN rolls pr ON pr.id = r.parent_roll_id
                WHERE UPPER(r.roll_code) IN ({$placeholders})
                ORDER BY r.id DESC
            ");
            $stmt->execute($normalized);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
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
        $sql = 'SELECT r.id, r.roll_code, r.weight_kg, r.received_qty, r.reception_mode,
                       r.microns, r.width_mm, r.meters, r.color, r.status, r.created_at,
                       r.import_container_id, r.import_container_item_id, r.purchase_order_id, r.purchase_order_line_id,
                       w.id AS warehouse_id, w.code AS warehouse_code, w.name AS warehouse_name,
                       s.code AS sku_code, s.description AS sku_description,
                       COALESCE(twe.traceability_code, r.roll_code) AS traceability_code,
                       JSON_UNQUOTE(JSON_EXTRACT(m.payload, "$.operator_name")) AS operator_name
                FROM rolls r
                JOIN warehouses w ON w.id = r.warehouse_id
                LEFT JOIN skus s ON s.id = r.sku_id
                LEFT JOIN traceability_warehouse_entries twe
                  ON twe.entity_type = "ROLL"
                 AND twe.entity_id = r.id
                 AND twe.id = (SELECT MIN(t2.id) FROM traceability_warehouse_entries t2 WHERE t2.entity_type = "ROLL" AND t2.entity_id = r.id)
                LEFT JOIN movements m
                  ON m.entity_type = "ROLL"
                 AND m.entity_id = r.id
                 AND m.movement_type = "RECEIPT"
                 AND m.id = (SELECT MIN(m2.id) FROM movements m2 WHERE m2.entity_type = "ROLL" AND m2.entity_id = r.id AND m2.movement_type = "RECEIPT")
                WHERE r.purchase_order_line_id = :pol';
        if ($importContainerItemId !== null && $importContainerItemId > 0) {
            $sql .= ' AND r.import_container_item_id = :container_item_id';
        }
        $sql .= ' ORDER BY r.id DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT :limit';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':pol', $purchaseOrderLineId, PDO::PARAM_INT);
        if ($importContainerItemId !== null && $importContainerItemId > 0) {
            $stmt->bindValue(':container_item_id', $importContainerItemId, PDO::PARAM_INT);
        }
        if ($limit > 0) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $containerCodes = [];
        foreach ($rows as &$row) {
            $cId = (int)($row['import_container_id'] ?? 0);
            if ($cId > 0) {
                if (!isset($containerCodes[$cId])) {
                    $containerCodes[$cId] = $this->getErpImportContainerCode($cId);
                }
                $row['container_code'] = $containerCodes[$cId];
            } else {
                $row['container_code'] = '';
            }
        }
        unset($row);
        return $rows;
    }

    /**
     * Lista todas las bobinas recibidas para un contenedor de importación completo (o una línea específica).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRollsByImportContainer(int $containerId, ?int $importContainerItemId = null, int $limit = 1000): array
    {
        $sql = 'SELECT r.id, r.roll_code, r.weight_kg, r.received_qty, r.reception_mode,
                       r.microns, r.width_mm, r.meters, r.color, r.status, r.created_at,
                       r.import_container_id, r.import_container_item_id, r.purchase_order_id, r.purchase_order_line_id,
                       w.id AS warehouse_id, w.code AS warehouse_code, w.name AS warehouse_name,
                       s.code AS sku_code, s.description AS sku_description,
                       COALESCE(twe.traceability_code, r.roll_code) AS traceability_code,
                       JSON_UNQUOTE(JSON_EXTRACT(m.payload, "$.operator_name")) AS operator_name
                FROM rolls r
                JOIN warehouses w ON w.id = r.warehouse_id
                LEFT JOIN skus s ON s.id = r.sku_id
                LEFT JOIN traceability_warehouse_entries twe
                  ON twe.entity_type = "ROLL"
                 AND twe.entity_id = r.id
                 AND twe.id = (SELECT MIN(t2.id) FROM traceability_warehouse_entries t2 WHERE t2.entity_type = "ROLL" AND t2.entity_id = r.id)
                LEFT JOIN movements m
                  ON m.entity_type = "ROLL"
                 AND m.entity_id = r.id
                 AND m.movement_type = "RECEIPT"
                 AND m.id = (SELECT MIN(m2.id) FROM movements m2 WHERE m2.entity_type = "ROLL" AND m2.entity_id = r.id AND m2.movement_type = "RECEIPT")
                WHERE r.import_container_id = :cid';
        if ($importContainerItemId !== null && $importContainerItemId > 0) {
            $sql .= ' AND r.import_container_item_id = :item_id';
        }
        $sql .= ' ORDER BY r.id DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT :limit';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':cid', $containerId, PDO::PARAM_INT);
        if ($importContainerItemId !== null && $importContainerItemId > 0) {
            $stmt->bindValue(':item_id', $importContainerItemId, PDO::PARAM_INT);
        }
        if ($limit > 0) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $containerCode = $this->getErpImportContainerCode($containerId);
        foreach ($rows as &$row) {
            $row['container_code'] = $containerCode;
        }
        unset($row);
        return $rows;
    }

    /**
     * Lista todas las bobinas recibidas para una orden de compra completa (o una línea específica).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRollsByPurchaseOrder(int $purchaseOrderId, ?int $purchaseOrderLineId = null, int $limit = 1000): array
    {
        $sql = 'SELECT r.id, r.roll_code, r.weight_kg, r.received_qty, r.reception_mode,
                       r.microns, r.width_mm, r.meters, r.color, r.status, r.created_at,
                       r.import_container_id, r.import_container_item_id, r.purchase_order_id, r.purchase_order_line_id,
                       w.id AS warehouse_id, w.code AS warehouse_code, w.name AS warehouse_name,
                       s.code AS sku_code, s.description AS sku_description,
                       COALESCE(twe.traceability_code, r.roll_code) AS traceability_code,
                       JSON_UNQUOTE(JSON_EXTRACT(m.payload, "$.operator_name")) AS operator_name
                FROM rolls r
                JOIN warehouses w ON w.id = r.warehouse_id
                LEFT JOIN skus s ON s.id = r.sku_id
                LEFT JOIN traceability_warehouse_entries twe
                  ON twe.entity_type = "ROLL"
                 AND twe.entity_id = r.id
                 AND twe.id = (SELECT MIN(t2.id) FROM traceability_warehouse_entries t2 WHERE t2.entity_type = "ROLL" AND t2.entity_id = r.id)
                LEFT JOIN movements m
                  ON m.entity_type = "ROLL"
                 AND m.entity_id = r.id
                 AND m.movement_type = "RECEIPT"
                 AND m.id = (SELECT MIN(m2.id) FROM movements m2 WHERE m2.entity_type = "ROLL" AND m2.entity_id = r.id AND m2.movement_type = "RECEIPT")
                WHERE r.purchase_order_id = :poid';
        if ($purchaseOrderLineId !== null && $purchaseOrderLineId > 0) {
            $sql .= ' AND r.purchase_order_line_id = :line_id';
        }
        $sql .= ' ORDER BY r.id DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT :limit';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':poid', $purchaseOrderId, PDO::PARAM_INT);
        if ($purchaseOrderLineId !== null && $purchaseOrderLineId > 0) {
            $stmt->bindValue(':line_id', $purchaseOrderLineId, PDO::PARAM_INT);
        }
        if ($limit > 0) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $containerCodes = [];
        foreach ($rows as &$row) {
            $cId = (int)($row['import_container_id'] ?? 0);
            if ($cId > 0) {
                if (!isset($containerCodes[$cId])) {
                    $containerCodes[$cId] = $this->getErpImportContainerCode($cId);
                }
                $row['container_code'] = $containerCodes[$cId];
            } else {
                $row['container_code'] = '';
            }
        }
        unset($row);
        return $rows;
    }

    /**
     * Lista todas las bobinas físicas almacenadas en una bodega para la vista de inventario con trazabilidad.
     *
     * @return array<int, array<string, mixed>>
     */
    /** Totales de bobinas de la bodega sin traer el detalle (mismo criterio que listRollsForWarehouseInventory). */
    public function summarizeRollsForWarehouseInventory(int $storehouseIdOrCode, int $limit = 2000): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS n, COALESCE(SUM(weight_kg), 0) AS kg, COALESCE(SUM(meters), 0) AS meters FROM (
                SELECT r.weight_kg, r.meters
                FROM rolls r
                JOIN warehouses w ON w.id = r.warehouse_id
                WHERE (w.id = :id1 OR w.code = :code1 OR w.erp_storehouse_id = :erpid1 OR (:erpid2 IN (16, 3000) AND w.id IN (18, 58)))
                  AND r.status IN ("RECEIVED", "IN_PROCESS", "BLOCKED")
                ORDER BY r.id DESC
                LIMIT ' . max(1, $limit) . '
            ) t');
        $stmt->execute([':id1' => $storehouseIdOrCode, ':code1' => $storehouseIdOrCode, ':erpid1' => $storehouseIdOrCode, ':erpid2' => $storehouseIdOrCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['count' => (int)($row['n'] ?? 0), 'weight_kg' => (float)($row['kg'] ?? 0), 'meters' => (float)($row['meters'] ?? 0)];
    }

    public function listRollsForWarehouseInventory(int $storehouseIdOrCode, int $limit = 2000): array
    {
        $sql = 'SELECT r.id, r.roll_code, r.weight_kg, r.received_qty, r.reception_mode,
                       r.microns, r.width_mm, r.meters, r.color, r.status, r.created_at,
                       r.import_container_id, r.import_container_item_id, r.purchase_order_id, r.purchase_order_line_id,
                       w.id AS warehouse_id, w.code AS warehouse_code, w.name AS warehouse_name,
                       s.code AS sku_code, s.description AS sku_description,
                       COALESCE(twe.traceability_code, r.roll_code) AS traceability_code,
                       JSON_UNQUOTE(JSON_EXTRACT(m.payload, "$.operator_name")) AS operator_name
                FROM rolls r
                JOIN warehouses w ON w.id = r.warehouse_id
                LEFT JOIN skus s ON s.id = r.sku_id
                LEFT JOIN traceability_warehouse_entries twe 
                  ON twe.entity_type = "ROLL" 
                 AND twe.entity_id = r.id
                 AND twe.id = (SELECT MIN(t2.id) FROM traceability_warehouse_entries t2 WHERE t2.entity_type = "ROLL" AND t2.entity_id = r.id)
                LEFT JOIN movements m 
                  ON m.entity_type = "ROLL" 
                 AND m.entity_id = r.id 
                 AND m.movement_type = "RECEIPT"
                 AND m.id = (SELECT MIN(m2.id) FROM movements m2 WHERE m2.entity_type = "ROLL" AND m2.entity_id = r.id AND m2.movement_type = "RECEIPT")
                WHERE (w.id = :id1 OR w.code = :code1 OR w.erp_storehouse_id = :erpid1 OR (:erpid1 IN (16, 3000) AND w.id IN (18, 58)))
                  AND r.status IN ("RECEIVED", "IN_PROCESS", "BLOCKED")
                ORDER BY r.id DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT :limit';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id1', $storehouseIdOrCode, PDO::PARAM_INT);
        $stmt->bindValue(':code1', $storehouseIdOrCode, PDO::PARAM_INT);
        $stmt->bindValue(':erpid1', $storehouseIdOrCode, PDO::PARAM_INT);
        if ($limit > 0) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->preloadErpImportContainerMeta(array_column($rows, 'import_container_id'));
        $containerCodes = [];
        foreach ($rows as &$row) {
            $cId = (int)($row['import_container_id'] ?? 0);
            if ($cId > 0) {
                if (!isset($containerCodes[$cId])) {
                    $containerCodes[$cId] = $this->getErpImportContainerCode($cId);
                }
                $row['container_code'] = $containerCodes[$cId];
            } else {
                $row['container_code'] = '';
            }
        }
        unset($row);
        return $rows;
    }

    public function deleteReceivedRoll(int $rollId): array
    {
        if ($rollId <= 0) {
            return ['ok' => false, 'error' => 'ID de bobina inválido.'];
        }

        $stmt = $this->pdo->prepare('SELECT * FROM rolls WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $rollId]);
        $roll = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$roll) {
            return ['ok' => false, 'error' => 'La bobina no existe o ya fue eliminada.'];
        }
        if (($roll['status'] ?? '') !== 'RECEIVED') {
            return ['ok' => false, 'error' => 'No se puede eliminar la bobina porque ya se encuentra en estado ' . ($roll['status'] ?? 'desconocido') . '.'];
        }

        $poId = (int)($roll['purchase_order_id'] ?? 0);
        $polId = (int)($roll['purchase_order_line_id'] ?? 0);
        $containerId = (int)($roll['import_container_id'] ?? 0);
        $rollCode = (string)($roll['roll_code'] ?? ('#' . $rollId));
        $weightKg = (float)($roll['weight_kg'] ?? 0);
        if ($containerId > 0) {
            try {
                $this->assertImportContainerReceiptEditable($containerId);
            } catch (Throwable $e) {
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        }

        $this->pdo->beginTransaction();
        try {
            $this->syncErpInventoryForReceivedRoll($rollId, -1.0, 'RECEPTION_DELETE');
            $this->deleteRollWarehouseTraceabilityEntry($rollId, $rollCode);

            $stmtMov = $this->pdo->prepare("DELETE FROM movements WHERE entity_type = 'ROLL' AND entity_id = :id");
            $stmtMov->execute([':id' => $rollId]);

            $stmtDel = $this->pdo->prepare("DELETE FROM rolls WHERE id = :id");
            $stmtDel->execute([':id' => $rollId]);

            $this->insertEvent('ROLL_RECEPTION_DELETED', [
                'roll_id' => $rollId,
                'roll_code' => $rollCode,
                'weight_kg' => $weightKg,
                'purchase_order_id' => $poId,
                'purchase_order_line_id' => $polId,
                'import_container_id' => $containerId,
            ]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['ok' => false, 'error' => 'Error al eliminar la bobina: ' . $e->getMessage()];
        }

        if ($poId > 0) {
            try {
                $this->refreshPurchaseOrderStatus($poId);
            } catch (Throwable) {}
        }
        if ($containerId > 0) {
            try {
                $this->refreshImportContainerStatus($containerId);
            } catch (Throwable) {}
        }

        return ['ok' => true, 'id' => $rollId, 'roll_code' => $rollCode, 'weight_kg' => $weightKg];
    }

    public function createRoll(array $input): array
    {
        $result = $this->rollReceptionService->createRoll($input);
        if (($result['ok'] ?? false) === true && (int)($result['id'] ?? 0) > 0) {
            try {
                $this->syncRollWarehouseTraceabilityEntry((int)$result['id']);
                $this->syncErpInventoryForReceivedRoll((int)$result['id'], 1.0, 'RECEPTION');
            } catch (Throwable $e) {
                $result['sync_error'] = 'Recepcion guardada; sincronizacion pendiente: ' . $e->getMessage();
            }
        }
        return $result;
    }

    private function createReceptionUnits(array $input): array
    {
        $result = $this->rollReceptionService->createUnits($input);
        if (($result['ok'] ?? false) === true) {
            foreach ($result['ids'] as $id) {
                try {
                    $this->syncRollWarehouseTraceabilityEntry((int)$id);
                    $this->syncErpInventoryForReceivedRoll((int)$id, 1.0, 'RECEPTION');
                } catch (Throwable $e) {
                    $result['sync_error'] = 'Recepcion guardada; sincronizacion pendiente: ' . $e->getMessage();
                }
            }
        }
        return $result;
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


    /**
     * Asegura la existencia de la tabla traceability_warehouse_entries en la BD TRZ.
     */
    public function ensureTraceabilityWarehouseEntriesTable(): void
    {
        static $done = false;
        if ($done) return;
        // Rendimiento: estas verificaciones (ALTER ... MODIFY, information_schema) se corrían en cada página.
        $schemaVersion = 'twe-v2';
        $gated = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' && class_exists('SchemaGate');
        if ($gated && SchemaGate::done('traceability-entries', $schemaVersion)) {
            $done = true;
            return;
        }

        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS traceability_warehouse_entries (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    traceability_code VARCHAR(60) NOT NULL,
                    batch_id VARCHAR(32) NULL,
                    entity_type ENUM('PALLET', 'BOX', 'ROLL', 'PRODUCT') NOT NULL DEFAULT 'PRODUCT',
                    entity_id BIGINT UNSIGNED NULL,
                    warehouse_id INT UNSIGNED NOT NULL,
                    warehouse_name VARCHAR(100) NOT NULL,
                    item_id INT UNSIGNED NULL,
                    item_code VARCHAR(60) NOT NULL,
                    item_description VARCHAR(255) NOT NULL,
                    quantity DECIMAL(12, 3) NOT NULL DEFAULT 1.000,
                    unit_name VARCHAR(30) NOT NULL DEFAULT 'UNID',
                    box_count INT UNSIGNED NULL,
                    weight_kg DECIMAL(10, 3) NULL,
                    width_mm INT UNSIGNED NULL,
                    microns INT UNSIGNED NULL,
                    meters DECIMAL(12, 2) NULL,
                    lot_number VARCHAR(80) NULL,
                    location_ref VARCHAR(80) NULL,
                    order_ref VARCHAR(80) NULL,
                    operator_name VARCHAR(120) NOT NULL,
                    comments TEXT NULL,
                    status VARCHAR(30) NOT NULL DEFAULT 'IN_WAREHOUSE',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_code (traceability_code),
                    INDEX idx_wh (warehouse_id),
                    INDEX idx_sku (item_code),
                    INDEX idx_created (created_at),
                    INDEX idx_label_batch (warehouse_id, batch_id, id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            // Asegurar columnas y enum por si la tabla ya existía previamente en la base de datos
            $colsToAdd = [
                'batch_id' => "ALTER TABLE traceability_warehouse_entries ADD COLUMN batch_id VARCHAR(32) NULL AFTER traceability_code",
                'entity_id' => "ALTER TABLE traceability_warehouse_entries ADD COLUMN entity_id BIGINT UNSIGNED NULL AFTER entity_type",
                'package_number' => "ALTER TABLE traceability_warehouse_entries ADD COLUMN package_number INT UNSIGNED NULL DEFAULT 1 AFTER box_count",
                'total_packages' => "ALTER TABLE traceability_warehouse_entries ADD COLUMN total_packages INT UNSIGNED NULL DEFAULT 1 AFTER package_number",
                'width_mm' => "ALTER TABLE traceability_warehouse_entries ADD COLUMN width_mm INT UNSIGNED NULL AFTER weight_kg",
                'microns' => "ALTER TABLE traceability_warehouse_entries ADD COLUMN microns INT UNSIGNED NULL AFTER width_mm",
                'meters' => "ALTER TABLE traceability_warehouse_entries ADD COLUMN meters DECIMAL(12, 2) NULL AFTER microns",
                'order_ref' => "ALTER TABLE traceability_warehouse_entries ADD COLUMN order_ref VARCHAR(80) NULL AFTER location_ref",
            ];
            foreach ($colsToAdd as $col => $sql) {
                if (!$this->columnExists('traceability_warehouse_entries', $col)) {
                    try { @$this->pdo->exec($sql); } catch (Throwable) {}
                }
            }
            try {
                $stmtIndex = $this->pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='traceability_warehouse_entries'
                    AND INDEX_NAME='idx_label_batch'");
                if ((int)$stmtIndex->fetchColumn() === 0) {
                    $this->pdo->exec('CREATE INDEX idx_label_batch ON traceability_warehouse_entries (warehouse_id,batch_id,id)');
                }
            } catch (Throwable) {}
            try {
                @$this->pdo->exec("ALTER TABLE traceability_warehouse_entries MODIFY COLUMN entity_type ENUM('PALLET', 'BOX', 'ROLL', 'PRODUCT') NOT NULL DEFAULT 'PRODUCT'");
            } catch (Throwable) {}

            // Asegurar que pallets y boxes permitan ingresos directos de bodega sin bobina origen
            @$this->pdo->exec("ALTER TABLE pallets MODIFY source_roll_id BIGINT UNSIGNED NULL DEFAULT NULL");
            @$this->pdo->exec("ALTER TABLE pallets MODIFY destination_mode VARCHAR(20) NOT NULL DEFAULT 'BODEGA'");
            @$this->pdo->exec("ALTER TABLE boxes MODIFY source_roll_id BIGINT UNSIGNED NULL DEFAULT NULL");
            @$this->pdo->exec("ALTER TABLE boxes MODIFY destination_mode VARCHAR(20) NOT NULL DEFAULT 'BODEGA'");
            // Sin este índice, listar las bobinas de una bodega recorría la tabla completa por cada bobina.
            try {
                $this->pdo->exec('CREATE INDEX idx_twe_entity ON traceability_warehouse_entries (entity_type, entity_id)');
            } catch (Throwable) {
                // El índice ya existe.
            }
            if ($gated) {
                SchemaGate::mark('traceability-entries', $schemaVersion);
            }
            $done = true;
        } catch (Throwable) {}
    }

    /**
     * Genera un código de trazabilidad corto (TRZ5C4687) que no exista aún. La fecha ya está en los datos del ingreso.
     */
    public function generateTraceabilityCode(string $type = 'PRODUCT'): string
    {
        $prefix = match (strtoupper(trim($type))) {
            'PALLET' => 'PL',
            'BOX' => 'BX',
            'ROLL' => 'RB',
            default => 'TRZ',
        };
        $this->ensureTraceabilityWarehouseEntriesTable();
        // Sin la fecha, un código puede repetirse con el tiempo: se revisa contra los ya usados.
        $exists = $this->pdo->prepare('SELECT 1 FROM traceability_warehouse_entries WHERE traceability_code = ? LIMIT 1');
        do {
            $code = $prefix . strtoupper(bin2hex(random_bytes(3)));
            $exists->execute([$code]);
            $taken = $exists->fetchColumn() !== false;
            $exists->closeCursor();
        } while ($taken);
        return $code;
    }

    /**
     * Resuelve el ID primario (PK) de la bodega en la base de datos de trazabilidad (unibag_trazabilidad.warehouses).
     * Mapea un ID de bodega de ERP (company_shops_storehouses.id) o código (100, 200, 3000) a warehouses.id,
     * garantizando que las foreign keys (como rolls.warehouse_id y movements.to_warehouse_id) no fallen.
     */
    public function resolveLocalWarehouseId(int $storehouseIdOrCode, string $fallbackName = ''): int
    {
        if ($storehouseIdOrCode <= 0 && trim($fallbackName) === '') {
            $stmt = $this->pdo->query('SELECT id FROM warehouses ORDER BY id ASC LIMIT 1');
            return (int)($stmt->fetchColumn() ?: 1);
        }

        // 1. Intentar resolver por erp_storehouse_id
        if ($storehouseIdOrCode > 0) {
            $stmt = $this->pdo->prepare('SELECT id FROM warehouses WHERE erp_storehouse_id = :erp_id LIMIT 1');
            $stmt->execute([':erp_id' => $storehouseIdOrCode]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }

            // 2. Intentar resolver por código directo (ej. 100, 200, 3000)
            $stmt = $this->pdo->prepare('SELECT id FROM warehouses WHERE code = :code LIMIT 1');
            $stmt->execute([':code' => $storehouseIdOrCode]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }

            // 3. Intentar resolver por coincidencia con PK directa si existe
            $stmt = $this->pdo->prepare('SELECT id FROM warehouses WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $storehouseIdOrCode]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }
        }

        // 4. Si viene un nombre de bodega (ej. "200 (MP PP)" o "Bodega #11"), buscar o parsear código
        if ($fallbackName !== '') {
            if (preg_match('/^\s*(\d{3,4})\b/', $fallbackName, $m)) {
                $parsedCode = (int)$m[1];
                $stmt = $this->pdo->prepare('SELECT id FROM warehouses WHERE code = :code LIMIT 1');
                $stmt->execute([':code' => $parsedCode]);
                $id = $stmt->fetchColumn();
                if ($id) {
                    return (int)$id;
                }
            }

            $stmt = $this->pdo->prepare('SELECT id FROM warehouses WHERE name = :name LIMIT 1');
            $stmt->execute([':name' => $fallbackName]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }
        }

        // 5. Si no existe aún y tenemos un código/erpId, intentar registrarla o mapearla
        if ($storehouseIdOrCode > 0) {
            $name = $fallbackName !== '' ? $fallbackName : ('Bodega #' . $storehouseIdOrCode);
            try {
                return $this->upsertTraceWarehouse($storehouseIdOrCode, $name, $storehouseIdOrCode);
            } catch (Throwable) {
                // Ignore fallback creation error
            }
        }

        $stmt = $this->pdo->query('SELECT id FROM warehouses ORDER BY id ASC LIMIT 1');
        return (int)($stmt->fetchColumn() ?: 1);
    }

    /**
     * Registra el ingreso a bodega de un producto/bulto con código de trazabilidad.
     * Impacta TRZ (pallets/boxes/rolls + movements + traceability_warehouse_entries).
     *
     * @param array<string, mixed> $data
     * @return array{ok: bool, error?: string, entry_id?: int, code?: string, codes?: list<string>, count?: int, entries?: list<array<string, mixed>>}
     */
    public function registerWarehouseTraceabilityEntry(array $data): array
    {
        $this->ensureTraceabilityWarehouseEntriesTable();
        $maxLabelsPerBatch = self::MAX_WAREHOUSE_TRACEABILITY_LABELS;

        $code = strtoupper(trim((string)($data['traceability_code'] ?? '')));
        $entityType = strtoupper(trim((string)($data['entity_type'] ?? 'PRODUCT')));
        if (!in_array($entityType, ['PALLET', 'BOX', 'ROLL', 'PRODUCT'], true)) {
            $entityType = 'PRODUCT';
        }

        if ($code === '') {
            $code = $this->generateTraceabilityCode($entityType);
        }

        $warehouseId = (int)($data['warehouse_id'] ?? ($data['storehouse_id'] ?? 0));
        $warehouseName = trim((string)($data['warehouse_name'] ?? ($data['storehouse_name'] ?? '')));
        $itemCode = trim((string)($data['item_code'] ?? ($data['sku'] ?? '')));
        $itemDesc = trim((string)($data['item_description'] ?? ($data['product_name'] ?? '')));
        $itemId = isset($data['item_id']) && (int)$data['item_id'] > 0 ? (int)$data['item_id'] : null;
        
        $packagingMode = strtoupper(trim((string)($data['packaging_mode'] ?? 'UNITARY')));
        $rawCount = (int)($data['package_count'] ?? 1);
        $rawQty = (float)($data['quantity'] ?? 1);

        if ($packagingMode === 'UNITARY') {
            // Modo unitario: 1 etiqueta por cada producto/bulto individual (ej. 10 bobinas = 10 etiquetas de 1 BOB)
            // Si el usuario puso package_count > 1, son esa cantidad de bultos unitarios
            // Si el usuario puso package_count = 1 pero quantity > 1, interpretamos que son N productos individuales de 1 unidad
            if ($rawCount > 1) {
                $packageCount = max(1, $rawCount);
                $qty = 1.0;
            } elseif ($rawQty > 1) {
                $packageCount = max(1, (int)round($rawQty));
                $qty = 1.0;
            } else {
                $packageCount = 1;
                $qty = max(0.001, $rawQty);
            }
        } else {
            // Modo agrupado: N bultos con M unidades cada uno (ej. 5 cajas de 500 bolsas c/u)
            $packageCount = max(1, $rawCount);
            $qty = max(0.001, $rawQty);
        }

        if ($packageCount > $maxLabelsPerBatch) {
            return ['ok' => false, 'error' => 'El maximo permitido es de ' . $maxLabelsPerBatch . ' etiquetas por vez. Divida el ingreso en tandas mas pequenas.'];
        }
        // Keep identifiers separate from descriptions; never truncate a SKU.
        // Separar identificadores y descripciones; nunca recortar un SKU.
        if (mb_strlen($itemCode, 'UTF-8') > 60) {
            return ['ok'=>false, 'error'=>'El codigo o SKU admite hasta 60 caracteres. La descripcion se ingresa por separado.'];
        }
        if (mb_strlen($itemDesc, 'UTF-8') > 255) {
            return ['ok'=>false, 'error'=>'La descripcion del producto admite hasta 255 caracteres.'];
        }

        $unitName = trim((string)($data['unit_name'] ?? 'UNID')) ?: 'UNID';
        $boxCount = isset($data['box_count']) ? (int)$data['box_count'] : null;
        // Para el ingreso de productos que ya están en el inventario, el peso no se toma en cuenta
        $weightKg = (isset($data['weight_kg']) && $data['weight_kg'] !== '') ? (float)$data['weight_kg'] : null;
        $widthMm = (isset($data['width_mm']) && $data['width_mm'] !== '') ? (int)$data['width_mm'] : null;
        $microns = (isset($data['microns']) && $data['microns'] !== '') ? (int)$data['microns'] : null;
        $meters = (isset($data['meters']) && $data['meters'] !== '') ? (float)$data['meters'] : null;

        // Rescatar automáticamente especificaciones (ancho, micras, metros) directamente del ERP si no fueron enviadas
        if ($widthMm === null || $microns === null || $meters === null) {
            $erpItemData = null;
            if ($itemId !== null && $itemId > 0) {
                $erpItemData = $this->getErpItem($itemId);
            }
            if ($erpItemData === null && $itemCode !== '') {
                $erpItemData = $this->getErpItemBySku($itemCode);
            }
            if ($erpItemData !== null) {
                if ($itemId === null && !empty($erpItemData['id'])) {
                    $itemId = (int)$erpItemData['id'];
                }
                if (($widthMm === null || $widthMm <= 0) && !empty($erpItemData['width_mm']) && (float)$erpItemData['width_mm'] > 0) {
                    $widthMm = (int)round((float)$erpItemData['width_mm']);
                }
                if (($microns === null || $microns <= 0) && !empty($erpItemData['grams']) && (float)$erpItemData['grams'] > 0) {
                    $microns = (int)round((float)$erpItemData['grams']);
                }
                if (($meters === null || $meters <= 0) && !empty($erpItemData['meters']) && (float)$erpItemData['meters'] > 0) {
                    $meters = (float)$erpItemData['meters'];
                }
            }
        }
        $lotNumber = trim((string)($data['lot_number'] ?? ''));
        $locationRef = trim((string)($data['location_ref'] ?? ''));
        $orderRef = trim((string)($data['order_ref'] ?? ''));
        $operatorName = trim((string)($data['operator_name'] ?? '')) ?: 'Operador Bodega';
        $comments = trim((string)($data['comments'] ?? ''));

        if ($warehouseId <= 0) {
            return ['ok' => false, 'error' => 'Debe seleccionar una bodega válida.'];
        }
        if ($itemCode === '') {
            return ['ok' => false, 'error' => 'Debe especificar el código o SKU del producto.'];
        }
        if ($qty <= 0) {
            return ['ok' => false, 'error' => 'La cantidad por bulto debe ser mayor a cero.'];
        }

        // Si no se pasó nombre de bodega, obtenerlo
        if ($warehouseName === '') {
            $whList = $this->listErpStorehouses();
            foreach ($whList as $w) {
                if ((int)$w['id'] === $warehouseId) {
                    $warehouseName = (string)$w['name'];
                    break;
                }
            }
            if ($warehouseName === '') {
                $warehouseName = 'Bodega #' . $warehouseId;
            }
        }

        // Resolver la PK de la bodega en la base de datos de trazabilidad (unibag_trazabilidad.warehouses)
        // para garantizar que las FKs no violen fk_rolls_warehouse_id ni fk_movements_to_wh
        $localWarehouseId = $this->resolveLocalWarehouseId($warehouseId, $warehouseName);

        // Cajas de bolsas: quedan ligadas al artículo, la bodega y el CC del ERP, para pedirlas, usarlas en el Pulpo y
        // despacharlas. Solo etiquetan stock que ya existe: el total de cajas no puede superar el stock del ERP.
        $boxErpOrderId = null;
        if ($entityType === 'BOX' && $itemId !== null && $warehouseId > 0) {
            if ($orderRef !== '') {
                $stmtOrd = $this->erpPdo->prepare('SELECT id FROM orders WHERE req_number = ? LIMIT 1');
                $stmtOrd->execute([$orderRef]);
                $boxErpOrderId = (int)$stmtOrd->fetchColumn() ?: null;
            }
            $stmtStock = $this->erpPdo->prepare('SELECT COALESCE(SUM(iss_inventory),0) FROM item_shops_storehouses WHERE item_id = ? AND st_id = ?'
                . ($boxErpOrderId !== null ? ' AND iss_order_id = ?' : ''));
            $stmtStock->execute($boxErpOrderId !== null ? [$itemId, $warehouseId, $boxErpOrderId] : [$itemId, $warehouseId]);
            $erpStock = (float)$stmtStock->fetchColumn();
            try {
                (new BagBoxService($this->pdo, $this->erpPdo))->ensureSchema();
                $stmtLabeled = $this->pdo->prepare("SELECT COALESCE(SUM(units_qty),0) FROM boxes WHERE erp_item_id = ? AND erp_st_id = ? AND status IN ('IN_WAREHOUSE','IN_MACHINE')"
                    . ($boxErpOrderId !== null ? ' AND erp_order_id = ?' : ''));
                $stmtLabeled->execute($boxErpOrderId !== null ? [$itemId, $warehouseId, $boxErpOrderId] : [$itemId, $warehouseId]);
                $labeled = (float)$stmtLabeled->fetchColumn();
            } catch (Throwable) {
                $labeled = 0.0;
            }
            $wanted = $packageCount * $qty;
            if ($labeled + $wanted > $erpStock + 0.0005) {
                return ['ok' => false, 'error' => 'El ERP tiene ' . number_format($erpStock, 0, ',', '.') . ' unidades de ' . $itemCode
                    . ($boxErpOrderId !== null ? ' (CC ' . $orderRef . ')' : '') . ' en esta bodega y ya hay ' . number_format($labeled, 0, ',', '.')
                    . ' en cajas con código: no alcanza para ' . number_format($wanted, 0, ',', '.') . ' más. Revisa el stock antes de etiquetar.'];
            }
        }

        // Para rollo, resolver o insertar sku_id en tabla skus una sola vez
        $skuId = null;
        if ($entityType === 'ROLL') {
            if ($itemCode !== '') {
                $stmtSku = $this->pdo->prepare('SELECT id FROM skus WHERE code = :c LIMIT 1');
                $stmtSku->execute([':c' => $itemCode]);
                $skuId = $stmtSku->fetchColumn();
                if (!$skuId) {
                    $stmtInsSku = $this->pdo->prepare('INSERT INTO skus (code, description, is_active, created_at) VALUES (:c, :d, 1, NOW())');
                    $stmtInsSku->execute([':c' => $itemCode, ':d' => $itemDesc !== '' ? $itemDesc : $itemCode]);
                    $skuId = (int)$this->pdo->lastInsertId();
                } else {
                    $skuId = (int)$skuId;
                }
            }
            if (!$skuId || $skuId <= 0) {
                $skuId = 1;
            }
        }

        $this->pdo->beginTransaction();
        try {
            $createdEntries = [];
            $batchId = bin2hex(random_bytes(16));

            for ($i = 0; $i < $packageCount; $i++) {
                if ($packageCount === 1 && $code !== '') {
                    $entryCode = $code;
                } else {
                    $entryCode = $this->generateTraceabilityCode($entityType);
                }

                $packageNumber = $i + 1;
                $entityId = null;

                // 1. Crear en tabla específica de entidad física si aplica
                if ($entityType === 'PALLET') {
                    $stmtPallet = $this->pdo->prepare('
                        INSERT INTO pallets (pallet_code, final_sku, warehouse_id, box_count, customer_order_ref, operator_name, destination_mode, status, created_at)
                        VALUES (:code, :sku, :wh, :boxes, :ord, :op, :dest, :st, NOW())
                    ');
                    $stmtPallet->execute([
                        ':code' => $entryCode,
                        ':sku' => $itemCode,
                        ':wh' => $localWarehouseId,
                        ':boxes' => $boxCount ?: (int)$qty,
                        ':ord' => $orderRef !== '' ? $orderRef : null,
                        ':op' => $operatorName,
                        ':dest' => 'BODEGA',
                        ':st' => 'RECEIVED',
                    ]);
                    $entityId = (int)$this->pdo->lastInsertId();
                } elseif ($entityType === 'BOX') {
                    $linkedBox = $itemId !== null && $warehouseId > 0;
                    if ($linkedBox) {
                        (new BagBoxService($this->pdo, $this->erpPdo))->ensureSchema();
                        $stmtBox = $this->pdo->prepare('
                            INSERT INTO boxes (box_code, final_sku, units_qty, units_initial, warehouse_id, customer_order_ref, operator_name, destination_mode, status,
                                erp_item_id, erp_st_id, erp_order_id, source_kind, source_ref, created_at)
                            VALUES (:code, :sku, :qty, :qty2, :wh, :ord, :op, :dest, :st, :item, :erpst, :erpord, :kind, :ref, NOW())
                        ');
                        $stmtBox->execute([
                            ':code' => $entryCode, ':sku' => $itemCode, ':qty' => $qty, ':qty2' => $qty, ':wh' => $localWarehouseId,
                            ':ord' => $orderRef !== '' ? $orderRef : null, ':op' => $operatorName, ':dest' => 'BODEGA', ':st' => 'IN_WAREHOUSE',
                            ':item' => $itemId, ':erpst' => $warehouseId, ':erpord' => $boxErpOrderId, ':kind' => BagBoxService::SOURCE_LABEL, ':ref' => $batchId,
                        ]);
                    } else {
                        $stmtBox = $this->pdo->prepare('
                            INSERT INTO boxes (box_code, final_sku, units_qty, warehouse_id, customer_order_ref, operator_name, destination_mode, status, created_at)
                            VALUES (:code, :sku, :qty, :wh, :ord, :op, :dest, :st, NOW())
                        ');
                        $stmtBox->execute([
                            ':code' => $entryCode,
                            ':sku' => $itemCode,
                            ':qty' => $qty,
                            ':wh' => $localWarehouseId,
                            ':ord' => $orderRef !== '' ? $orderRef : null,
                            ':op' => $operatorName,
                            ':dest' => 'BODEGA',
                            ':st' => 'RECEIVED',
                        ]);
                    }
                    $entityId = (int)$this->pdo->lastInsertId();
                } elseif ($entityType === 'ROLL') {
                    $stmtRoll = $this->pdo->prepare('
                        INSERT INTO rolls (roll_code, sku_id, warehouse_id, weight_kg, received_qty, width_mm, microns, meters, reception_mode, status, created_at)
                        VALUES (:code, :skuid, :wh, :wkg, :rqty, :wmm, :mic, :met, :mode, :st, NOW())
                    ');
                    $stmtRoll->execute([
                        ':code' => $entryCode,
                        ':skuid' => $skuId,
                        ':wh' => $localWarehouseId,
                        ':wkg' => $weightKg !== null ? $weightKg : 0.0,
                        ':rqty' => $qty,
                        ':wmm' => $widthMm,
                        ':mic' => $microns,
                        ':met' => $meters,
                        ':mode' => 'WAREHOUSE_STOCK',
                        ':st' => 'RECEIVED',
                    ]);
                    $entityId = (int)$this->pdo->lastInsertId();
                }

                // 2. Registrar movimiento de entrada (RECEIPT)
                $stmtMov = $this->pdo->prepare('
                    INSERT INTO movements (entity_type, entity_id, movement_type, to_warehouse_id, payload, created_at)
                    VALUES (:etype, :eid, :mtype, :towh, :payload, NOW())
                ');
                $movPayload = json_encode([
                    'traceability_code' => $entryCode,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'item_code' => $itemCode,
                    'item_description' => $itemDesc,
                    'quantity' => $qty,
                    'unit' => $unitName,
                    'package_number' => $packageNumber,
                    'total_packages' => $packageCount,
                    'batch_id' => $batchId,
                    'operator' => $operatorName,
                    'comments' => $comments,
                ], JSON_UNESCAPED_UNICODE);

                $stmtMov->execute([
                    ':etype' => $entityType,
                    ':eid' => $entityId ?: 0,
                    ':mtype' => 'RECEIPT',
                    ':towh' => $localWarehouseId,
                    ':payload' => $movPayload,
                ]);

                // 3. Registrar entrada detallada en traceability_warehouse_entries
                $stmtEntry = $this->pdo->prepare('
                    INSERT INTO traceability_warehouse_entries 
                    (traceability_code, batch_id, entity_type, entity_id, warehouse_id, warehouse_name, item_id, item_code, item_description,
                     quantity, unit_name, box_count, package_number, total_packages, weight_kg, width_mm, microns, meters, lot_number, location_ref, order_ref, 
                     operator_name, comments, status, created_at)
                    VALUES 
                    (:code, :batch, :etype, :eid, :wh, :whname, :itemid, :itemcode, :itemdesc,
                     :qty, :unit, :boxcnt, :pkgnum, :totpkg, :wkg, :wmm, :mic, :met, :lot, :loc, :ord,
                     :op, :comments, :st, NOW())
                ');
                $stmtEntry->execute([
                    ':code' => $entryCode,
                    ':batch' => $batchId,
                    ':etype' => $entityType,
                    ':eid' => $entityId,
                    ':wh' => $warehouseId,
                    ':whname' => $warehouseName,
                    ':itemid' => $itemId,
                    ':itemcode' => $itemCode,
                    ':itemdesc' => $itemDesc,
                    ':qty' => $qty,
                    ':unit' => $unitName,
                    ':boxcnt' => $boxCount,
                    ':pkgnum' => $packageNumber,
                    ':totpkg' => $packageCount,
                    ':wkg' => $weightKg,
                    ':wmm' => $widthMm,
                    ':mic' => $microns,
                    ':met' => $meters,
                    ':lot' => $lotNumber !== '' ? $lotNumber : null,
                    ':loc' => $locationRef !== '' ? $locationRef : null,
                    ':ord' => $orderRef !== '' ? $orderRef : null,
                    ':op' => $operatorName,
                    ':comments' => $comments !== '' ? $comments : null,
                    ':st' => 'IN_WAREHOUSE',
                ]);

                $entryId = (int)$this->pdo->lastInsertId();

                $createdEntries[] = [
                    'entry_id' => $entryId,
                    'batch_id' => $batchId,
                    'code' => $entryCode,
                    'traceability_code' => $entryCode,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'warehouse_name' => $warehouseName,
                    'item_code' => $itemCode,
                    'item_description' => $itemDesc,
                    'quantity' => $qty,
                    'unit_name' => $unitName,
                    'package_number' => $packageNumber,
                    'total_packages' => $packageCount,
                    'operator_name' => $operatorName,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }

            $this->pdo->commit();

            return [
                'ok' => true,
                'count' => count($createdEntries),
                'batch_id' => $batchId,
                'entry_id' => $createdEntries[0]['entry_id'] ?? 0,
                'code' => $createdEntries[0]['code'] ?? $code,
                'codes' => array_column($createdEntries, 'code'),
                'entries' => $createdEntries,
                'entity_type' => $entityType,
                'entity_id' => $createdEntries[0]['entity_id'] ?? null,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['ok' => false, 'error' => 'Error al guardar ingreso trazable: ' . $e->getMessage()];
        }
    }

    /**
     * Elimina un registro de ingreso trazable a bodega y sus entidades generadas (por error humano u operativo).
     *
     * @return array{ok: bool, error?: string, code?: string}
     */
    public function deleteWarehouseTraceabilityEntry(int $entryId): array
    {
        $this->ensureTraceabilityWarehouseEntriesTable();
        if ($entryId <= 0) {
            return ['ok' => false, 'error' => 'ID de registro inválido.'];
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM traceability_warehouse_entries WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $entryId]);
            $entry = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$entry) {
                return ['ok' => false, 'error' => 'El registro no existe o ya fue eliminado.'];
            }

            $code = (string)$entry['traceability_code'];
            $entityType = strtoupper((string)$entry['entity_type']);
            $entityId = (int)($entry['entity_id'] ?? 0);

            $this->pdo->beginTransaction();

            if ($entityType === 'PALLET' && $entityId > 0) {
                $stmtDel = $this->pdo->prepare('DELETE FROM pallets WHERE id = :id OR pallet_code = :c');
                $stmtDel->execute([':id' => $entityId, ':c' => $code]);
            } elseif ($entityType === 'BOX' && $entityId > 0) {
                $stmtDel = $this->pdo->prepare('DELETE FROM boxes WHERE id = :id OR box_code = :c');
                $stmtDel->execute([':id' => $entityId, ':c' => $code]);
            } elseif ($entityType === 'ROLL' && $entityId > 0) {
                $stmtDel = $this->pdo->prepare('DELETE FROM rolls WHERE id = :id OR roll_code = :c');
                $stmtDel->execute([':id' => $entityId, ':c' => $code]);
            }

            $stmtDelMov = $this->pdo->prepare('DELETE FROM movements WHERE payload LIKE :c');
            $stmtDelMov->execute([':c' => '%' . $code . '%']);

            $stmtDelEnt = $this->pdo->prepare('DELETE FROM traceability_warehouse_entries WHERE id = :id');
            $stmtDelEnt->execute([':id' => $entryId]);

            $this->pdo->commit();
            return ['ok' => true, 'code' => $code];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['ok' => false, 'error' => 'Error al eliminar ingreso trazable: ' . $e->getMessage()];
        }
    }

    /**
     * Obtiene el acumulado de cantidades ya registradas con código de trazabilidad por artículo en una bodega.
     *
     * @return array<string, float>
     */
    public function getWarehouseTraceabilityTaggedQuantities(int $warehouseId): array
    {
        $this->ensureTraceabilityWarehouseEntriesTable();
        if ($warehouseId <= 0) {
            return [];
        }

        try {
            $localWhId = $this->resolveLocalWarehouseId($warehouseId);
            if ($localWhId > 0 && $localWhId !== $warehouseId) {
                $whClause = '(warehouse_id = :wh OR warehouse_id = :local_wh)';
                $params = [':wh' => $warehouseId, ':local_wh' => $localWhId];
            } else {
                $whClause = 'warehouse_id = :wh';
                $params = [':wh' => $warehouseId];
            }

            $stmt = $this->pdo->prepare("
                SELECT item_code, COALESCE(item_id, 0) AS item_id, SUM(quantity) AS tagged_qty
                FROM traceability_warehouse_entries
                WHERE {$whClause} AND status NOT IN ('CANCELLED', 'DISPATCHED')
                GROUP BY item_code, item_id
            ");
            $stmt->execute($params);
            $map = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $code = strtoupper(trim((string)$r['item_code']));
                $id = (int)$r['item_id'];
                $qty = (float)$r['tagged_qty'];
                if ($id > 0) {
                    $map['id_' . $id] = ($map['id_' . $id] ?? 0.0) + $qty;
                }
                if ($code !== '') {
                    $map['code_' . $code] = ($map['code_' . $code] ?? 0.0) + $qty;
                }
            }

            return $map;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Obtiene registros de trazabilidad en bodega según una lista de códigos específicos.
     *
     * @param list<string> $codes
     * @return list<array<string, mixed>>
     */
    public function getWarehouseTraceabilityEntriesByCodes(array $codes): array
    {
        $this->ensureTraceabilityWarehouseEntriesTable();
        $cleanCodes = array_values(array_filter(array_map('strval', $codes)));
        if ($cleanCodes === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($cleanCodes), '?'));
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM traceability_warehouse_entries WHERE traceability_code IN ({$placeholders}) ORDER BY id ASC");
            $stmt->execute($cleanCodes);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /** Legacy batches require consecutive row IDs and package numbering.
     *  Lotes anteriores: solo agrupar IDs y numeracion consecutivos, sin adivinar por fecha. */
    private static function legacyLabelBatchStartSql(): string
    {
        return '(id - CASE WHEN package_number > 0 THEN package_number ELSE 1 END + 1)';
    }

    public function listWarehouseTraceabilityBatches(int $warehouseId, string $search = '', int $page = 1, int $pageSize = 25): array
    {
        $this->ensureTraceabilityWarehouseEntriesTable();
        if ($warehouseId <= 0) return [];
        $pageSize = max(1, min(50, $pageSize));
        $offset = (max(1, $page) - 1) * $pageSize;
        $batchStart = "CASE WHEN COALESCE(batch_id,'')='' THEN " . self::legacyLabelBatchStartSql() . " ELSE 0 END";
        $groupFields = implode(',', self::LABEL_BATCH_FIELDS);
        $params = [$warehouseId];
        $having = '';
        if (trim($search) !== '') {
            $having = ' HAVING MAX(CASE WHEN item_code LIKE ? OR item_description LIKE ?
                OR operator_name LIKE ? OR traceability_code LIKE ? OR order_ref LIKE ? THEN 1 ELSE 0 END)=1';
            $params = array_merge($params, array_fill(0, 5, '%' . trim($search) . '%'));
        }
        $stmt = $this->pdo->prepare("SELECT MIN(id) AS anchor_id, MAX(id) AS latest_id, $batchStart AS legacy_start,
            MAX(batch_id) AS batch_id, MIN(created_at) AS created_at, MAX(item_code) AS item_code,
            MAX(item_description) AS item_description, MAX(operator_name) AS operator_name,
            MAX(entity_type) AS entity_type, COUNT(*) AS label_count, MAX(total_packages) AS expected_count,
            MIN(package_number) AS first_number, MAX(package_number) AS last_number,
            MIN(traceability_code) AS first_code
            FROM traceability_warehouse_entries WHERE warehouse_id=?
            GROUP BY COALESCE(batch_id,''), $batchStart, $groupFields $having
            ORDER BY latest_id DESC LIMIT " . ($pageSize + 1) . " OFFSET " . $offset);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getWarehouseTraceabilityBatchEntries(int $warehouseId, int $anchorId): array
    {
        $this->ensureTraceabilityWarehouseEntriesTable();
        if ($warehouseId <= 0 || $anchorId <= 0) return [];
        $stmt = $this->pdo->prepare('SELECT * FROM traceability_warehouse_entries WHERE id=? AND warehouse_id=?');
        $stmt->execute([$anchorId, $warehouseId]);
        $anchor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$anchor) return [];
        $batchId = trim((string)($anchor['batch_id'] ?? ''));
        if ($batchId !== '') {
            $stmt = $this->pdo->prepare('SELECT * FROM traceability_warehouse_entries
                WHERE warehouse_id=? AND batch_id=? ORDER BY package_number,id');
            $stmt->execute([$warehouseId, $batchId]);
        } else {
            $start = (int)$anchor['id'] - max(1, (int)$anchor['package_number']) + 1;
            $where = ["COALESCE(batch_id,'')=''", self::legacyLabelBatchStartSql() . '=?'];
            $params = [$start];
            foreach (self::LABEL_BATCH_FIELDS as $field) {
                if ($anchor[$field] === null) {
                    $where[] = "$field IS NULL";
                } else {
                    $where[] = "$field=?";
                    $params[] = $anchor[$field];
                }
            }
            $stmt = $this->pdo->prepare('SELECT * FROM traceability_warehouse_entries WHERE '
                . implode(' AND ', $where) . ' ORDER BY package_number,id');
            foreach ($params as $index => $value) {
                $stmt->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Lista los ingresos recientes con código de trazabilidad realizados en bodega.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listWarehouseTraceabilityEntries(?int $warehouseId = null, ?string $search = null, int $limit = 50): array
    {
        $this->ensureTraceabilityWarehouseEntriesTable();

        $params = [];
        $where = [];

        if ($warehouseId !== null && $warehouseId > 0) {
            // warehouse_id guarda el id de bodega del ERP, pero algunos registros quedaron con el id local. El id local de
            // una bodega puede ser el id ERP de OTRA (local 1 = 100, ERP 1 = 400): por eso el id local solo cuenta si el
            // nombre guardado también es el de esta bodega; si no, los códigos de la 400 aparecían en la 100.
            $localWhId = $this->resolveLocalWarehouseId($warehouseId);
            $localName = '';
            if ($localWhId > 0 && $localWhId !== $warehouseId) {
                $stName = $this->pdo->prepare('SELECT name FROM warehouses WHERE id = :id');
                $stName->execute([':id' => $localWhId]);
                $localName = trim((string)($stName->fetchColumn() ?: ''));
            }
            if ($localName !== '') {
                $where[] = '(warehouse_id = :wh OR (warehouse_id = :local_wh AND warehouse_name = :local_name))';
                $params[':wh'] = $warehouseId;
                $params[':local_wh'] = $localWhId;
                $params[':local_name'] = $localName;
            } else {
                $where[] = 'warehouse_id = :wh';
                $params[':wh'] = $warehouseId;
            }
        }

        if ($search !== null && trim($search) !== '') {
            $where[] = '(traceability_code LIKE :q OR item_code LIKE :q OR item_description LIKE :q OR lot_number LIKE :q OR order_ref LIKE :q OR location_ref LIKE :q)';
            $params[':q'] = '%' . trim($search) . '%';
        }

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
        $effectiveLimit = max(1, min(10000, $limit));
        $sql = "SELECT * FROM traceability_warehouse_entries {$whereSql} ORDER BY id DESC LIMIT " . $effectiveLimit;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Elimina todos los registros de trazabilidad de un artículo específico en una bodega (para corrección en masa).
     *
     * @return array{ok: bool, deleted_count: int, error?: string}
     */
    public function deleteWarehouseTraceabilityEntriesByItem(int $warehouseId, string $itemCode): array
    {
        $this->ensureTraceabilityWarehouseEntriesTable();
        $itemCode = trim($itemCode);
        if ($warehouseId <= 0 || $itemCode === '') {
            return ['ok' => false, 'deleted_count' => 0, 'error' => 'Parámetros inválidos.'];
        }

        try {
            $stmt = $this->pdo->prepare('SELECT id FROM traceability_warehouse_entries WHERE warehouse_id = :wh AND UPPER(item_code) = :sku');
            $stmt->execute([':wh' => $warehouseId, ':sku' => strtoupper($itemCode)]);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($entries === []) {
                return ['ok' => true, 'deleted_count' => 0];
            }

            $count = 0;
            foreach ($entries as $e) {
                $delRes = $this->deleteWarehouseTraceabilityEntry((int)$e['id']);
                if (!empty($delRes['ok'])) {
                    $count++;
                }
            }

            return ['ok' => true, 'deleted_count' => $count];
        } catch (Throwable $e) {
            return ['ok' => false, 'deleted_count' => 0, 'error' => $e->getMessage()];
        }
    }

    private function syncLegacyRollWarehouseTraceabilityEntries(): void
    {
        $this->ensureTraceabilityWarehouseEntriesTable();

        try {
            $stmt = $this->pdo->query(
                "SELECT id
                 FROM rolls
                 WHERE status IN ('RECEIVED', 'IN_PROCESS', 'BLOCKED')"
            );
            $rollIds = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
            foreach ($rollIds as $rollId) {
                $this->syncRollWarehouseTraceabilityEntry((int)$rollId);
            }
        } catch (Throwable) {
            return;
        }
    }

    private function syncLegacyErpInventoryForReceivedRolls(): void
    {
        if ($this->erpPdo === null) {
            return;
        }

        try {
            $stmt = $this->pdo->query(
                "SELECT r.warehouse_id,
                        s.code AS sku_code,
                        SUM(r.received_qty) AS total_qty
                 FROM rolls r
                 INNER JOIN skus s ON s.id = r.sku_id
                 WHERE r.status = 'RECEIVED'
                   AND COALESCE(r.import_container_id, 0) = 0
                 GROUP BY r.warehouse_id, s.code"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $row) {
                $warehouseId = (int)($row['warehouse_id'] ?? 0);
                $skuCode = trim((string)($row['sku_code'] ?? ''));
                $targetQty = (float)($row['total_qty'] ?? 0);
                if ($warehouseId <= 0 || $skuCode === '' || $targetQty <= 0) {
                    continue;
                }

                $erpItem = $this->getErpItemBySku($skuCode);
                $erpStorehouse = $this->getErpStorehouseForLocalWarehouse($warehouseId);
                if ($erpItem === null || $erpStorehouse === null) {
                    continue;
                }

                $currentQty = $this->getCurrentErpInventoryQty((int)$erpItem['id'], (int)$erpStorehouse['id']);
                if ($currentQty + 0.0001 >= $targetQty) {
                    continue;
                }

                (new StockLedgerService($this->erpPdo))->post([
                    'key' => 'rolllegacy.' . substr(hash('sha256', $warehouseId . '|' . $skuCode . '|' . $targetQty . '|' . $currentQty), 0, 24),
                    'item_id' => (int)$erpItem['id'], 'shop_id' => (int)$erpStorehouse['shop_id'], 'st_id' => (int)$erpStorehouse['id'],
                    'delta' => $targetQty - $currentQty, 'allow_negative' => true,
                    'text' => 'Bobinas recibidas antes en trazabilidad que faltaban en el ERP: ' . $skuCode . '.',
                ]);
            }
        } catch (Throwable) {
            return;
        }
    }

    private function syncRollWarehouseTraceabilityEntry(int $rollId, string $operatorName = ''): void
    {
        if ($rollId <= 0) {
            return;
        }
        $this->ensureTraceabilityWarehouseEntriesTable();
        RollTraceabilityEntry::sync($this->pdo, $rollId, $operatorName);
    }

    private function deleteRollWarehouseTraceabilityEntry(int $rollId, string $rollCode = ''): void
    {
        if ($rollId <= 0 && $rollCode === '') {
            return;
        }

        $this->ensureTraceabilityWarehouseEntriesTable();
        $stmt = $this->pdo->prepare(
            "DELETE FROM traceability_warehouse_entries
             WHERE (entity_type = 'ROLL' AND entity_id = :entity_id)
                OR traceability_code = :traceability_code"
        );
        $stmt->execute([
            ':entity_id' => $rollId,
            ':traceability_code' => $rollCode,
        ]);
    }

    private function syncErpInventoryForReceivedRoll(int $rollId, float $direction, string $movementContext = 'RECEPTION'): void
    {
        if ($rollId <= 0 || $direction == 0.0 || $this->erpPdo === null) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.roll_code, r.received_qty, r.warehouse_id, r.import_container_id,
                    s.code AS sku_code, w.code AS warehouse_code
             FROM rolls r
             INNER JOIN skus s ON s.id = r.sku_id
             INNER JOIN warehouses w ON w.id = r.warehouse_id
             WHERE r.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $rollId]);
        $roll = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$roll) {
            return;
        }
        // Las importaciones se ingresan por recepción de contenedor y costeo ERP.
        // Imports enter ERP inventory only through the container costing workflow.
        if ((int)($roll['import_container_id'] ?? 0) > 0) {
            return;
        }

        $skuCode = trim((string)($roll['sku_code'] ?? ''));
        $receivedQty = (float)($roll['received_qty'] ?? 0);
        $warehouseId = (int)($roll['warehouse_id'] ?? 0);
        if ($skuCode === '' || $receivedQty <= 0 || $warehouseId <= 0) {
            return;
        }

        $erpItem = $this->getErpItemBySku($skuCode);
        $erpStorehouse = $this->getErpStorehouseForLocalWarehouse($warehouseId);
        if ($erpItem === null || $erpStorehouse === null) {
            return;
        }

        $movementLabel = strtoupper(trim($movementContext));
        $annotationBase = $direction > 0
            ? sprintf(
                'TRZ RECEPCION %s BOBINA %s SKU %s BOD %s',
                $movementLabel,
                trim((string)($roll['roll_code'] ?? ('#' . $rollId))),
                $skuCode,
                (string)($roll['warehouse_code'] ?? $warehouseId)
            )
            : sprintf(
                'TRZ REVERSO %s BOBINA %s SKU %s BOD %s',
                $movementLabel,
                trim((string)($roll['roll_code'] ?? ('#' . $rollId))),
                $skuCode,
                (string)($roll['warehouse_code'] ?? $warehouseId)
            );

        $wasBooked = $this->recordErpInventoryMovementAcrossTargets($annotationBase, [[
            'erp_item_id' => (int)$erpItem['id'],
            'shop_id' => (int)$erpStorehouse['shop_id'],
            'erp_storehouse_id' => (int)$erpStorehouse['id'],
            'qty' => $receivedQty * $direction,
        ]]);

        if ($wasBooked) {
            return;
        }

        // Si el registro principal falló, el respaldo también deja documento y kardex (antes solo tocaba el stock).
        foreach ($this->getErpSyncTargets() as $targetPdo) {
            (new StockLedgerService($targetPdo))->post([
                'key' => 'rollsync.' . substr(hash('sha256', $annotationBase . '|' . $rollId . '|' . $direction), 0, 24),
                'item_id' => (int)$erpItem['id'], 'shop_id' => (int)$erpStorehouse['shop_id'], 'st_id' => (int)$erpStorehouse['id'],
                'delta' => $receivedQty * $direction, 'allow_negative' => true, 'text' => $annotationBase,
            ]);
        }
    }

    private function syncErpInventoryForRollTransfer(int $rollId, int $fromWarehouseId, int $toWarehouseId, string $movementContext = 'TRANSFER'): void
    {
        if ($rollId <= 0 || $fromWarehouseId <= 0 || $toWarehouseId <= 0 || $fromWarehouseId === $toWarehouseId || $this->erpPdo === null) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT r.roll_code, r.received_qty, s.code AS sku_code,
                    wf.code AS from_warehouse_code, wt.code AS to_warehouse_code
             FROM rolls r
             INNER JOIN skus s ON s.id = r.sku_id
             INNER JOIN warehouses wf ON wf.id = :from_wh
             INNER JOIN warehouses wt ON wt.id = :to_wh
             WHERE r.id = :id
             LIMIT 1'
        );
        $stmt->execute([
            ':id' => $rollId,
            ':from_wh' => $fromWarehouseId,
            ':to_wh' => $toWarehouseId,
        ]);
        $roll = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$roll) {
            return;
        }

        $skuCode = trim((string)($roll['sku_code'] ?? ''));
        $receivedQty = (float)($roll['received_qty'] ?? 0);
        if ($skuCode === '' || $receivedQty <= 0) {
            return;
        }

        $erpItem = $this->getErpItemBySku($skuCode);
        $fromStorehouse = $this->getErpStorehouseForLocalWarehouse($fromWarehouseId);
        $toStorehouse = $this->getErpStorehouseForLocalWarehouse($toWarehouseId);
        if ($erpItem === null || $fromStorehouse === null || $toStorehouse === null) {
            return;
        }
        if ((int)$fromStorehouse['id'] === (int)$toStorehouse['id']) {
            return;
        }

        $itemId = (int)$erpItem['id'];
        $annotationBase = sprintf(
            'TRZ %s BOBINA %s SKU %s %s->%s',
            strtoupper(trim($movementContext)),
            trim((string)($roll['roll_code'] ?? ('#' . $rollId))),
            $skuCode,
            (string)($roll['from_warehouse_code'] ?? $fromWarehouseId),
            (string)($roll['to_warehouse_code'] ?? $toWarehouseId)
        );

        $wasBooked = $this->recordErpInventoryMovementAcrossTargets($annotationBase, [
            [
                'erp_item_id' => $itemId,
                'shop_id' => (int)$fromStorehouse['shop_id'],
                'erp_storehouse_id' => (int)$fromStorehouse['id'],
                'qty' => -1.0 * $receivedQty,
            ],
            [
                'erp_item_id' => $itemId,
                'shop_id' => (int)$toStorehouse['shop_id'],
                'erp_storehouse_id' => (int)$toStorehouse['id'],
                'qty' => $receivedQty,
            ],
        ]);
        if ($wasBooked) {
            return;
        }

        // Respaldo con documento de traspaso y kardex (antes solo tocaba el stock).
        foreach ($this->getErpSyncTargets() as $targetPdo) {
            (new StockLedgerService($targetPdo))->transfer([
                'key' => 'rolltr.' . substr(hash('sha256', $annotationBase . '|' . $rollId), 0, 24),
                'item_id' => $itemId, 'shop_id' => (int)$fromStorehouse['shop_id'], 'from_st' => (int)$fromStorehouse['id'],
                'to_st' => (int)$toStorehouse['id'], 'qty' => $receivedQty, 'allow_negative' => true, 'text' => $annotationBase,
            ]);
        }
    }

    /**
     * @param array<int, array<string, int|float>> $entries
     */
    private function recordErpInventoryMovementAcrossTargets(string $annotationBase, array $entries, int $issueId = 17, int $userId = 1): bool
    {
        $annotationBase = trim($annotationBase);
        if ($annotationBase === '' || $entries === []) {
            return false;
        }

        $targets = $this->getErpSyncTargets();
        if ($targets === []) {
            return false;
        }

        $bookedAny = false;
        $bookedAll = true;
        foreach ($targets as $targetPdo) {
            $targetBooked = false;
            try {
                if ($this->recordErpInventoryMovementToPdo($targetPdo, $annotationBase, $entries, $issueId, $userId)) {
                    $bookedAny = true;
                    $targetBooked = true;
                }
            } catch (Throwable) {
            }
            if (!$targetBooked) {
                $bookedAll = false;
            }
        }

        return $bookedAny && $bookedAll;
    }

    /**
     * @param array<int, array<string, int|float>> $entries
     */
    private function recordErpInventoryMovementToPdo(PDO $erpPdo, string $annotationBase, array $entries, int $issueId, int $userId): bool
    {
        $buckets = [];
        foreach ($entries as $entry) {
            $itemId = (int)($entry['erp_item_id'] ?? 0);
            $shopId = (int)($entry['shop_id'] ?? 0);
            $storehouseId = (int)($entry['erp_storehouse_id'] ?? 0);
            $qty = (float)($entry['qty'] ?? 0);
            if ($itemId <= 0 || $shopId <= 0 || $storehouseId <= 0 || abs($qty) < 0.0001) {
                continue;
            }

            $negative = $qty < 0 ? 1 : 0;
            $bucketKey = $negative . ':' . $shopId;
            if (!isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = [
                    'negative' => $negative,
                    'shop_id' => $shopId,
                    'entries' => [],
                ];
            }

            $buckets[$bucketKey]['entries'][] = [
                'erp_item_id' => $itemId,
                'shop_id' => $shopId,
                'erp_storehouse_id' => $storehouseId,
                'qty' => abs($qty),
            ];
        }

        if ($buckets === []) {
            return false;
        }

        $bookedAny = false;
        $now = time();
        foreach ($buckets as $bucket) {
            $negative = (int)$bucket['negative'];
            $shopId = (int)$bucket['shop_id'];
            $tranType = $negative > 0 ? 'stockchangedown' : 'stockchangeup';
            $annotation = $annotationBase . ($negative > 0 ? ' [SALIDA]' : ' [ENTRADA]');
            $companyId = $this->resolveErpCompanyId($erpPdo);

            try {
                $erpPdo->beginTransaction();

                $existingId = $this->findErpStockChangeIdByAnnotation($erpPdo, $annotation, $negative);
                if ($existingId > 0 && $this->stockChangeHasTranData($erpPdo, $existingId, $tranType)) {
                    $erpPdo->commit();
                    $bookedAny = true;
                    continue;
                }

                if ($existingId > 0) {
                    $erpPdo->prepare(
                        'UPDATE stockchanges
                         SET stk_issueid = :issueid,
                             stk_companyid = :company_id,
                             stk_shopid = :shop_id,
                             stk_bookdate = :bookdate,
                             stk_negative = :negative,
                             stk_status = 2,
                             stk_upddat = :upddat,
                             stk_updusr = :updusr
                         WHERE id = :id'
                    )->execute([
                        ':issueid' => $issueId,
                        ':company_id' => $companyId,
                        ':shop_id' => $shopId,
                        ':bookdate' => $now,
                        ':negative' => $negative,
                        ':upddat' => $now,
                        ':updusr' => $userId > 0 ? $userId : 1,
                        ':id' => $existingId,
                    ]);
                    $erpPdo->prepare('DELETE FROM stockchanges_items WHERE stk_id = :stk_id')
                        ->execute([':stk_id' => $existingId]);
                    $erpPdo->prepare('DELETE FROM tran_data WHERE tran_id = :tran_id AND tran_type = :tran_type')
                        ->execute([
                            ':tran_id' => $existingId,
                            ':tran_type' => $tranType,
                        ]);
                    $stockChangeId = $existingId;
                    $stockChangeNumber = $this->getErpStockChangeNumberById($erpPdo, $stockChangeId);
                } else {
                    $stockChangeNumber = $this->generateNextErpStockChangeNumber($companyId, $erpPdo);
                    $erpPdo->prepare(
                        'INSERT INTO stockchanges
                         (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate,
                          stk_negative, stk_status, stk_crtdat, stk_crtusr, stk_isventainterna, stk_fixedsthid)
                         VALUES
                         (:stk_num, :annotation, :issueid, :company_id, :shop_id, :bookdate,
                          :negative, 2, :crtdat, :crtusr, 0, 0)'
                    )->execute([
                        ':stk_num' => $stockChangeNumber,
                        ':annotation' => $annotation,
                        ':issueid' => $issueId,
                        ':company_id' => $companyId,
                        ':shop_id' => $shopId,
                        ':bookdate' => $now,
                        ':negative' => $negative,
                        ':crtdat' => $now,
                        ':crtusr' => $userId > 0 ? $userId : 1,
                    ]);
                    $stockChangeId = (int)$erpPdo->lastInsertId();
                }

                $insertItem = $erpPdo->prepare(
                    'INSERT INTO stockchanges_items
                     (stk_id, item_id, item_pos, item_amount, item_type, item_st_id)
                     VALUES
                     (:stk_id, :item_id, :item_pos, :item_amount, "item", :item_st_id)'
                );
                $insertTran = $erpPdo->prepare(
                    'INSERT INTO tran_data
                     (tran_id, tran_pos, tran_type, tran_number, tran_currstock, tran_amount, tran_company_id, tran_shop_id, tran_st_id, tran_supplier_id,
                      tran_cust_id, tran_issueid, item_id, item_parent_id, item_type, item_costprice_brutto, item_costprice_taxes_perc, item_costprice_netto,
                      item_costprice_taxes, item_costprice_avg_netto, item_sellprice_brutto, item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes,
                      item_charges_id, item_charges_used_id, tran_day, tran_month, tran_year, tran_crtusr, tran_crtdat, tran_order_id)
                     VALUES
                     (:tran_id, :tran_pos, :tran_type, :tran_number, :tran_currstock, :tran_amount, :tran_company_id, :tran_shop_id, :tran_st_id, 0,
                      0, :tran_issueid, :item_id, 0, "item", 0, 0, 0,
                      0, 0, 0, 0, 0, 0,
                      0, 0, :tran_day, :tran_month, :tran_year, :tran_crtusr, :tran_crtdat, 0)'
                );

                $currentStockByKey = [];
                foreach ($bucket['entries'] as $position => $entry) {
                    $itemId = (int)$entry['erp_item_id'];
                    $storehouseId = (int)$entry['erp_storehouse_id'];
                    $qty = (float)$entry['qty'];
                    $stockKey = $itemId . ':' . $storehouseId;
                    $currentStock = $currentStockByKey[$stockKey] ?? $this->getCurrentErpInventoryQty($itemId, $storehouseId, $erpPdo);

                    $insertItem->execute([
                        ':stk_id' => $stockChangeId,
                        ':item_id' => $itemId,
                        ':item_pos' => $position,
                        ':item_amount' => number_format($qty, 10, '.', ''),
                        ':item_st_id' => $storehouseId,
                    ]);

                    $insertTran->execute([
                        ':tran_id' => $stockChangeId,
                        ':tran_pos' => $position,
                        ':tran_type' => $tranType,
                        ':tran_number' => $stockChangeNumber,
                        ':tran_currstock' => number_format($currentStock, 10, '.', ''),
                        ':tran_amount' => number_format($qty, 10, '.', ''),
                        ':tran_company_id' => $companyId,
                        ':tran_shop_id' => $shopId,
                        ':tran_st_id' => $storehouseId,
                        ':tran_issueid' => $issueId,
                        ':item_id' => $itemId,
                        ':tran_day' => (int)date('d', $now),
                        ':tran_month' => (int)date('m', $now),
                        ':tran_year' => (int)date('Y', $now),
                        ':tran_crtusr' => $userId > 0 ? $userId : 1,
                        ':tran_crtdat' => $now,
                    ]);

                    $delta = $negative > 0 ? -1.0 * $qty : $qty;
                    $this->adjustErpInventoryRow($itemId, $shopId, $storehouseId, $delta, $erpPdo, true);
                    $currentStockByKey[$stockKey] = $currentStock + $delta;
                }

                $erpPdo->commit();
                $bookedAny = true;
            } catch (Throwable) {
                if ($erpPdo->inTransaction()) {
                    $erpPdo->rollBack();
                }
            }
        }

        return $bookedAny;
    }

    private function findErpStockChangeIdByAnnotation(PDO $erpPdo, string $annotation, int $negative): int
    {
        $stmt = $erpPdo->prepare(
            'SELECT id
             FROM stockchanges
             WHERE stk_annotation = :annotation
               AND stk_negative = :negative
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([
            ':annotation' => $annotation,
            ':negative' => $negative,
        ]);

        return (int)($stmt->fetchColumn() ?: 0);
    }

    private function stockChangeHasTranData(PDO $erpPdo, int $stockChangeId, string $tranType): bool
    {
        if ($stockChangeId <= 0) {
            return false;
        }

        $stmt = $erpPdo->prepare(
            'SELECT COUNT(*)
             FROM tran_data
             WHERE tran_id = :tran_id
               AND tran_type = :tran_type'
        );
        $stmt->execute([
            ':tran_id' => $stockChangeId,
            ':tran_type' => $tranType,
        ]);

        return (int)($stmt->fetchColumn() ?: 0) > 0;
    }

    private function getErpStockChangeNumberById(PDO $erpPdo, int $stockChangeId): string
    {
        if ($stockChangeId <= 0) {
            return '';
        }

        $stmt = $erpPdo->prepare(
            'SELECT stk_num
             FROM stockchanges
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $stockChangeId]);

        return trim((string)($stmt->fetchColumn() ?: ''));
    }

    private function resolveErpCompanyId(PDO $erpPdo): int
    {
        try {
            $preferred = $erpPdo->query('SELECT id FROM company_data WHERE id = 20010 LIMIT 1');
            $preferredId = $preferred ? (int)($preferred->fetchColumn() ?: 0) : 0;
            if ($preferredId > 0) {
                return $preferredId;
            }

            $fallback = $erpPdo->query('SELECT id FROM company_data ORDER BY id ASC LIMIT 1');
            $fallbackId = $fallback ? (int)($fallback->fetchColumn() ?: 0) : 0;
            if ($fallbackId > 0) {
                return $fallbackId;
            }
        } catch (Throwable) {
        }

        return 20010;
    }

    private function getErpStorehouseForLocalWarehouse(int $localWarehouseId): ?array
    {
        if ($localWarehouseId <= 0 || $this->erpPdo === null) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT erp_storehouse_id FROM warehouses WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $localWarehouseId]);
        $erpStorehouseId = (int)($stmt->fetchColumn() ?: 0);
        if ($erpStorehouseId <= 0) {
            return null;
        }

        $erpStmt = $this->erpPdo->prepare(
            'SELECT id, st_shop_id
             FROM company_shops_storehouses
             WHERE id = :id
             LIMIT 1'
        );
        $erpStmt->execute([':id' => $erpStorehouseId]);
        $row = $erpStmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int)($row['id'] ?? 0),
            'shop_id' => (int)($row['st_shop_id'] ?? 0),
        ];
    }

    private function getCurrentErpInventoryQty(int $erpItemId, int $erpStorehouseId, ?PDO $erpPdo = null): float
    {
        $erpPdo ??= $this->erpPdo;
        if ($erpItemId <= 0 || $erpStorehouseId <= 0 || $erpPdo === null) {
            return 0.0;
        }

        $stmt = $erpPdo->prepare(
            'SELECT COALESCE(SUM(iss_inventory), 0)
             FROM item_shops_storehouses
             WHERE item_id = :item_id
               AND st_id = :st_id'
        );
        $stmt->execute([
            ':item_id' => $erpItemId,
            ':st_id' => $erpStorehouseId,
        ]);

        return (float)($stmt->fetchColumn() ?: 0);
    }

    private function adjustErpInventoryRow(int $erpItemId, int $shopId, int $erpStorehouseId, float $deltaQty, ?PDO $erpPdo = null, bool $allowNegative = false): void
    {
        $erpPdo ??= $this->erpPdo;
        if ($erpItemId <= 0 || $shopId <= 0 || $erpStorehouseId <= 0 || abs($deltaQty) < 0.0001 || $erpPdo === null) {
            return;
        }

        $inventoryExpr = $allowNegative ? 'iss_inventory + :delta' : 'GREATEST(0, iss_inventory + :delta)';
        $update = $erpPdo->prepare(
            "UPDATE item_shops_storehouses
             SET iss_inventory = {$inventoryExpr}
             WHERE item_id = :item_id
               AND st_id = :st_id"
        );
        $update->execute([
            ':delta' => $deltaQty,
            ':item_id' => $erpItemId,
            ':st_id' => $erpStorehouseId,
        ]);

        if ($update->rowCount() > 0 || ($deltaQty < 0 && !$allowNegative)) {
            return;
        }

        $insert = $erpPdo->prepare(
            'INSERT INTO item_shops_storehouses (item_id, shop_id, st_id, iss_inventory)
             VALUES (:item_id, :shop_id, :st_id, :iss_inventory)'
        );
        $insert->execute([
            ':item_id' => $erpItemId,
            ':shop_id' => $shopId,
            ':st_id' => $erpStorehouseId,
            ':iss_inventory' => $deltaQty,
        ]);
    }

}

class RollStockService
{
    use RollStockServiceTrait;

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
