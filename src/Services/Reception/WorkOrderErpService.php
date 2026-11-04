<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Órdenes de Trabajo ERP, Químicos, Setups, Aprobaciones y Solicitudes
// (src/Services/Reception/WorkOrderErpService.php)
// =============================================================================

trait WorkOrderErpServiceTrait
{









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

            $erpReqId = (int)($row['erp_req_id'] ?? 0);
            $otCode = trim((string)($row['ot_code'] ?? ''));

            if ($this->erpPdo !== null && ($erpReqId > 0 || $otCode !== '')) {
                try {
                    $sqlErp = "SELECT o.id AS order_id, o.req_number, o.req_cust_id, c.cust_name,
                                      o.req_cliche_peli_solic_dat, o.req_cliche_peli_recep_dat,
                                      o.req_prod_adjfile_0, o.req_prod_adjfile_1, o.req_prod_adjfile_2, o.req_prod_adjfile_3, o.req_prod_adjfile_4,
                                      o.req_prod_adjcomments_0, o.req_prod_adjcomments_1, o.req_prod_adjcomments_2, o.req_prod_adjcomments_3, o.req_prod_adjcomments_4,
                                      oi.fab_type, oi.fab_printtype, oi.fab_med_width, oi.fab_med_height, oi.fab_med_fuelle,
                                      oi.fab_print_width, oi.fab_print_height, oi.fab_design_imagehash, oi.fab_design_name,
                                      oi.fab_print_colordesc_1, oi.fab_print_colordesc_2, oi.fab_print_colordesc_3, oi.fab_print_colordesc_4, oi.fab_print_colordesc_5, oi.fab_print_colordesc_6
                               FROM orders o
                               LEFT JOIN customer c ON c.id = o.req_cust_id
                               LEFT JOIN orders_items oi ON oi.req_id = o.id
                               WHERE " . ($erpReqId > 0 ? "o.id = :req_id" : "o.req_number = :ot_code") . "
                               LIMIT 1";
                    $stmtErp = $this->erpPdo->prepare($sqlErp);
                    $params = $erpReqId > 0 ? [':req_id' => $erpReqId] : [':ot_code' => $otCode];
                    $stmtErp->execute($params);
                    $erpData = $stmtErp->fetch();
                    if (is_array($erpData)) {
                        $row['erp_customer_name'] = (string)($erpData['cust_name'] ?? '');
                        $row['cust_name'] = (string)($erpData['cust_name'] ?? '');
                        $row['fab_med_width'] = (float)($erpData['fab_med_width'] ?? 0);
                        $row['fab_med_height'] = (float)($erpData['fab_med_height'] ?? 0);
                        $row['fab_med_fuelle'] = (float)($erpData['fab_med_fuelle'] ?? 0);
                        $row['fab_print_width'] = (float)($erpData['fab_print_width'] ?? 0);
                        $row['fab_print_height'] = (float)($erpData['fab_print_height'] ?? 0);
                        $row['fab_type'] = (string)($erpData['fab_type'] ?? '');
                        $row['fab_printtype'] = (string)($erpData['fab_printtype'] ?? '');
                        $row['fab_design_imagehash'] = (string)($erpData['fab_design_imagehash'] ?? '');
                        $row['fab_design_name'] = (string)($erpData['fab_design_name'] ?? '');
                        $row['req_cliche_peli_solic_dat'] = $erpData['req_cliche_peli_solic_dat'];
                        $row['req_cliche_peli_recep_dat'] = $erpData['req_cliche_peli_recep_dat'];
                        for ($x = 0; $x < 5; $x++) {
                            $row["req_prod_adjfile_{$x}"] = (string)($erpData["req_prod_adjfile_{$x}"] ?? '');
                            $row["req_prod_adjcomments_{$x}"] = (string)($erpData["req_prod_adjcomments_{$x}"] ?? '');
                        }
                        for ($c = 1; $c <= 6; $c++) {
                            $row["fab_print_colordesc_{$c}"] = (string)($erpData["fab_print_colordesc_{$c}"] ?? '');
                        }
                    }
                } catch (Throwable) {}
            }
        }
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

    /**
     * OT disponibles para traspaso.
     *
     * $refreshFromErp = false lee solo el caché local (rápido). Con true (valor por defecto, comportamiento histórico)
     * puede ejecutar la sincronización con el ERP, que es lenta cuando el caché de 5 minutos venció.
     */
    public function listWorkOrdersForTransfer(bool $refreshFromErp = true): array
    {
        if ($refreshFromErp) {
            $this->syncWorkOrdersFromErpProductionPlan();
        }
        $stmt = $this->pdo->prepare(
            "SELECT id, ot_code, sku_final, target_qty, status, created_at
             FROM work_orders
             WHERE status IN ('ACTIVE', 'OPEN')
             ORDER BY CASE WHEN status = 'ACTIVE' THEN 0 ELSE 1 END, id DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** ¿Venció el caché local del plan ERP? Consulta barata: no toca el ERP. */
    public function isProductionPlanSyncStale(): bool
    {
        $last = (int)($this->getAppSetting('erp_production_plan_synced_at', '0') ?? '0');
        return $last <= 0 || $last < (time() - 300);
    }

    /** Refresca el plan de producción desde el ERP (respeta el caché de 5 minutos). */
    public function refreshProductionPlanFromErp(): void
    {
        $this->syncWorkOrdersFromErpProductionPlan();
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
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return;
        }
    }

    /** @var array<int,array<string,?string>> Lectura única de app_settings por conexión y solicitud. */
    private static array $appSettingsCache = [];

    private function getAppSetting(string $key, ?string $default = null): ?string
    {
        $cacheId = spl_object_id($this->pdo);
        if (!isset(self::$appSettingsCache[$cacheId])) {
            try {
                self::$appSettingsCache[$cacheId] = [];
                foreach ($this->pdo->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll(PDO::FETCH_NUM) as $r) {
                    self::$appSettingsCache[$cacheId][(string)$r[0]] = $r[1] === null ? null : (string)$r[1];
                }
            } catch (Throwable) {
                unset(self::$appSettingsCache[$cacheId]);
            }
        }
        if (isset(self::$appSettingsCache[$cacheId])) {
            if (!array_key_exists($key, self::$appSettingsCache[$cacheId])) {
                return $default;
            }
            $cached = self::$appSettingsCache[$cacheId][$key];
            return $cached === null ? $default : $cached;
        }
        try {
            $stmt = $this->pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :key LIMIT 1');
            $stmt->execute([':key' => $key]);
            $row = $stmt->fetch();
        } catch (Throwable) {
            return $default;
        }
        if ($row === false) {
            return $default;
        }

        $value = $row['setting_value'] ?? null;
        return $value === null ? $default : (string)$value;
    }

    private function setAppSetting(string $key, string $value): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO app_settings (setting_key, setting_value) VALUES (:key, :value)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            $stmt->execute([
                ':key' => $key,
                ':value' => $value,
            ]);
            self::$appSettingsCache[spl_object_id($this->pdo)][$key] = $value;
        } catch (Throwable) {
            return;
        }
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

    /**
     * Retorna artículos existentes en una bodega ERP para ser solicitados como tintas/insumos.
     *
     * @return array<int, array{item_id:int, code:string, name:string, unit_name:string, available_qty:float, request_label:string}>
     */
    public function listWarehouseItemsByWarehouseCode(int $warehouseCode, bool $onlyWithStock = true): array
    {
        $warehouseCode = max(0, $warehouseCode);
        if ($warehouseCode <= 0) {
            return [];
        }

        $this->syncWarehousesFromErp();

        $stmt = $this->pdo->prepare(
            'SELECT id, code, name, erp_storehouse_id
             FROM warehouses
             WHERE code = :code
             LIMIT 1'
        );
        $stmt->execute([':code' => $warehouseCode]);
        $warehouse = $stmt->fetch(PDO::FETCH_ASSOC);

        $erpStorehouseId = (int)($warehouse['erp_storehouse_id'] ?? 0);
        if ($erpStorehouseId <= 0) {
            $erpStorehouseId = $warehouseCode;
        }

        $items = $this->getErpStockByStorehouse($erpStorehouseId, null, $onlyWithStock);
        $results = [];
        foreach ($items as $item) {
            $code = trim((string)($item['item_number_prod'] ?? ''));
            $name = trim((string)($item['item_title'] ?? ''));
            $unitName = trim((string)($item['unit_name'] ?? 'UNID'));
            $availableQty = (float)($item['available_qty'] ?? 0);

            if ($code === '' && $name === '') {
                continue;
            }

            $results[] = [
                'item_id' => (int)($item['item_id'] ?? 0),
                'code' => $code,
                'name' => $name,
                'unit_name' => $unitName,
                'available_qty' => $availableQty,
                'request_label' => trim(($code !== '' ? $code . ' - ' : '') . $name),
            ];
        }

        return $results;
    }

    /**
     * Retorna artículos de una bodega ERP específica, opcionalmente filtrados por prefijo de código.
     *
     * @return array<int, array{item_id:int, code:string, name:string, unit_name:string, available_qty:float, request_label:string}>
     */
    public function listWarehouseItemsByErpStorehouseId(int $erpStorehouseId, bool $onlyWithStock = true, string $codePrefix = ''): array
    {
        $erpStorehouseId = max(0, $erpStorehouseId);
        if ($erpStorehouseId <= 0) {
            return [];
        }

        $prefix = strtoupper(trim($codePrefix));
        $items = $this->getErpStockByStorehouse($erpStorehouseId, null, $onlyWithStock);
        $results = [];
        foreach ($items as $item) {
            $code = trim((string)($item['item_number_prod'] ?? ''));
            $name = trim((string)($item['item_title'] ?? ''));
            $unitName = trim((string)($item['unit_name'] ?? 'UNID'));
            $availableQty = (float)($item['available_qty'] ?? 0);

            if ($code === '' && $name === '') {
                continue;
            }
            if ($prefix !== '' && strpos(strtoupper($code), $prefix) !== 0) {
                continue;
            }

            $results[] = [
                'item_id' => (int)($item['item_id'] ?? 0),
                'code' => $code,
                'name' => $name,
                'unit_name' => $unitName,
                'available_qty' => $availableQty,
                'request_label' => trim(($code !== '' ? $code . ' - ' : '') . $name),
            ];
        }

        return $results;
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
                AND (w.code IN ("100", "200") OR w.erp_storehouse_id IN (5, 11) OR w.name LIKE "%100%" OR w.name LIKE "%200%")
                AND w.code NOT IN ("3000") AND w.name NOT LIKE "%3000%"
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
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['ok' => false, 'errors' => ['transfer' => 'No se pudo registrar el traspaso entre bodegas: ' . $e->getMessage()]];
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


}

#[AllowDynamicProperties]
class WorkOrderErpService
{
    use WorkOrderErpServiceTrait;

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
