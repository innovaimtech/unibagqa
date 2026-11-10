<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Sincronización con ERP · Bodegas, Planificación, Ítems y Proveedores
// (src/Services/Reception/ErpSyncService.php)
// =============================================================================

trait ErpSyncServiceTrait
{
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
            // Solo bodegas de la sucursal Unibag: las de otras sucursales creaban filas sueltas en TRZ («VENTAS»…).
            require_once dirname(__DIR__, 2) . '/WarehouseVisibility.php';
            $unibagIds = WarehouseVisibility::activeErpIds($this->erpPdo);
            foreach ($rows as $row) {
                $erpId = (int)$row['id'];
                if ($unibagIds !== null && $unibagIds !== [] && !isset($unibagIds[$erpId])) {
                    continue;
                }
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
            $stmt = $this->pdo->prepare('SELECT id, code, name FROM warehouses WHERE erp_storehouse_id = :erp_id LIMIT 1');
            $stmt->execute([':erp_id' => $erpStorehouseId]);
            $row = $stmt->fetch();
            if ($row !== false) {
                $id = (int)$row['id'];
                // Rendimiento: si nada cambió no se consulta ni se escribe (eran 3 consultas por bodega).
                if ((string)$row['name'] === $name && (int)$row['code'] === $code) {
                    return $id;
                }
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



    private function inferReceptionModeFromErpLine(array $line): string
    {
        $orderedWeight = (float)($line['ordered_weight_kg'] ?? $line['item_kgs'] ?? $line['sord_kgs_amount'] ?? 0);
        return $orderedWeight > 0 ? 'WEIGHT' : 'QUANTITY';
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
    public function getErpItem(int $erpItemId): ?array
    {
        if ($erpItemId <= 0 || $this->erpPdo === null) {
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
                    i.item_reg_length,
                    i.item_reg_kg
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
            'sku_code' => trim((string)($row['item_number_prod'] ?? ($row['item_number'] ?? ''))) !== '' ? (string)($row['item_number_prod'] ?: $row['item_number']) : ('ERPITEM-' . $erpItemId),
            'sku_description' => trim((string)($row['item_title'] ?? '')) !== '' ? (string)$row['item_title'] : ('Producto ERP ' . $erpItemId),
            'grams' => (float)($row['item_reg_gsm'] ?? 0),
            'width_mm' => (float)($row['item_reg_width'] ?? 0),
            'meters' => (float)($row['item_reg_length'] ?? 0),
            'weight_kg' => (float)($row['item_reg_kg'] ?? 0),
            'color' => '',
        ];

        return $this->erpItemsCache[$erpItemId];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getErpItemBySku(string $sku): ?array
    {
        $sku = trim($sku);
        if ($sku === '' || $this->erpPdo === null) {
            return null;
        }

        $stmt = $this->erpPdo->prepare(
            'SELECT i.id,
                    i.item_number,
                    i.item_number_prod,
                    i.item_title,
                    i.item_reg_gsm,
                    i.item_reg_width,
                    i.item_reg_length,
                    i.item_reg_kg
             FROM item i
             WHERE i.item_number_prod = :s OR i.item_number = :s
             LIMIT 1'
        );
        $stmt->execute([':s' => $sku]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $id = (int)$row['id'];
        return [
            'id' => $id,
            'sku_code' => trim((string)($row['item_number_prod'] ?? ($row['item_number'] ?? ''))),
            'sku_description' => trim((string)($row['item_title'] ?? '')),
            'grams' => (float)($row['item_reg_gsm'] ?? 0),
            'width_mm' => (float)($row['item_reg_width'] ?? 0),
            'meters' => (float)($row['item_reg_length'] ?? 0),
            'weight_kg' => (float)($row['item_reg_kg'] ?? 0),
            'color' => '',
        ];
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



    /**
     * @param array<string, mixed> $row
     */
    private function decorateRollWithErpContext(array &$row): void
    {
        $purchaseOrderId = (int)($row['purchase_order_id'] ?? 0);
        $purchaseOrderLineId = (int)($row['purchase_order_line_id'] ?? 0);
        $supplierId = (int)($row['supplier_id'] ?? 0);
        $importContainerId = (int)($row['import_container_id'] ?? 0);
        $importContainerItemId = (int)($row['import_container_item_id'] ?? 0);

        if ($purchaseOrderId <= 0 && $purchaseOrderLineId > 0) {
            $poLine = $this->getPurchaseOrderLine($purchaseOrderLineId);
            if ($poLine !== null) {
                $purchaseOrderId = (int)($poLine['purchase_order_id'] ?? 0);
                if ($supplierId <= 0) {
                    $supplierId = (int)($poLine['supplier_id'] ?? 0);
                }
            }
        }
        if ($importContainerId <= 0 && $importContainerItemId > 0) {
            $cLine = $this->getImportContainerLine($importContainerItemId);
            if ($cLine !== null) {
                $importContainerId = (int)($cLine['import_container_id'] ?? $cLine['container_id'] ?? 0);
                if ($purchaseOrderId <= 0) {
                    $purchaseOrderId = (int)($cLine['purchase_order_id'] ?? 0);
                }
                if ($supplierId <= 0) {
                    $supplierId = (int)($cLine['supplier_id'] ?? 0);
                }
            }
        }

        $containerMeta = $this->getErpImportContainerMeta($importContainerId);
        $row['po_code'] = $this->getErpPurchaseOrderCode($purchaseOrderId);
        $row['container_code'] = $containerMeta['code'];
        $row['arrival_date'] = $containerMeta['eta_plant'];
        if (empty($row['arrival_date']) && !empty($row['created_at'])) {
            $row['arrival_date'] = substr((string)$row['created_at'], 0, 10);
        }
        if ($supplierId <= 0 && $purchaseOrderId > 0) {
            $po = $this->getPurchaseOrder($purchaseOrderId);
            if ($po !== null && (int)($po['supplier_id'] ?? 0) > 0) {
                $supplierId = (int)$po['supplier_id'];
            }
        }
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


}

#[AllowDynamicProperties]
class ErpSyncService
{
    use ErpSyncServiceTrait;

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
