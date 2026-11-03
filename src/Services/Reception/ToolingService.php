<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Datos · Mantenedor de Clichés y Anilox (src/Services/Reception/ToolingService.php)
// =============================================================================

trait ToolingServiceTrait
{
    public function listWorkOrdersForClicheAssignment(): array
    {
        $this->syncWorkOrdersFromErpProductionPlan();
        $stmt = $this->pdo->prepare(
            "SELECT id, ot_code, sku_final, status, created_at
             FROM work_orders
             WHERE status IN ('OPEN', 'ACTIVE', 'CUTTING')
             ORDER BY CASE
                 WHEN status = 'ACTIVE' THEN 0
                 WHEN status = 'CUTTING' THEN 1
                 ELSE 2
             END, id DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listCliches(?string $search = null, ?string $location = null, ?string $status = null): array
    {
        $params = [];
        $where = [];
        $search = trim((string)$search);
        $location = trim((string)$location);
        $status = strtoupper(trim((string)$status));

        if ($search !== '') {
            $where[] = '(c.code LIKE :search OR c.description LIKE :search OR wo.ot_code LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        if ($location !== '') {
            $where[] = '(c.location_code LIKE :location OR c.location_detail LIKE :location)';
            $params[':location'] = '%' . $location . '%';
        }
        if ($status !== '' && $status !== 'ALL') {
            $where[] = 'c.status = :status';
            $params[':status'] = $status;
        }

        $sql = 'SELECT c.*,
                       wo.ot_code, wo.sku_final
                FROM cliches c
                LEFT JOIN work_orders wo ON wo.id = c.current_work_order_id';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY CASE
                    WHEN c.status = "IN_USE" THEN 0
                    WHEN c.status = "AVAILABLE" THEN 1
                    WHEN c.status = "MAINTENANCE" THEN 2
                    ELSE 3
                  END, c.code ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getCliche(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, wo.ot_code, wo.sku_final
             FROM cliches c
             LEFT JOIN work_orders wo ON wo.id = c.current_work_order_id
             WHERE c.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createCliche(
        string $code,
        string $description,
        string $locationCode,
        string $locationDetail,
        string $notes,
        string $operatorName
    ): array {
        $code = strtoupper(trim($code));
        $description = trim($description);
        $locationCode = strtoupper(trim($locationCode));
        $locationDetail = trim($locationDetail);
        $notes = trim($notes);
        $operatorName = trim($operatorName);
        $errors = [];

        if ($code === '') {
            $errors['code'] = 'Código de clisé es obligatorio.';
        }
        if ($description === '') {
            $errors['description'] = 'Descripción del clisé es obligatoria.';
        }
        if ($locationCode === '') {
            $errors['location_code'] = 'Ubicación física es obligatoria.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO cliches (
                code, description, location_code, location_detail, status, notes
             ) VALUES (
                :code, :description, :location_code, :location_detail, :status, :notes
             )'
        );
        try {
            $stmt->execute([
                ':code' => $code,
                ':description' => $description,
                ':location_code' => $locationCode,
                ':location_detail' => $locationDetail !== '' ? $locationDetail : null,
                ':status' => 'AVAILABLE',
                ':notes' => $notes !== '' ? $notes : null,
            ]);
            $clicheId = (int)$this->pdo->lastInsertId();

            $log = $this->pdo->prepare(
                'INSERT INTO cliche_usage_logs (
                    cliche_id, work_order_id, action_type, from_location_code, to_location_code, operator_name, notes
                 ) VALUES (
                    :cliche_id, NULL, :action_type, NULL, :to_location_code, :operator_name, :notes
                 )'
            );
            $log->execute([
                ':cliche_id' => $clicheId,
                ':action_type' => 'CREATED',
                ':to_location_code' => $locationCode,
                ':operator_name' => $operatorName,
                ':notes' => $notes !== '' ? $notes : null,
            ]);

            $this->insertEvent('CLICHE_CREATED', [
                'cliche_id' => $clicheId,
                'cliche_code' => $code,
                'location_code' => $locationCode,
                'operator_name' => $operatorName,
            ]);

            return ['ok' => true, 'errors' => [], 'cliche_id' => $clicheId];
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'uq_cliches_code')) {
                return ['ok' => false, 'errors' => ['code' => 'Ese código de clisé ya existe.']];
            }
            throw $e;
        }
    }

    public function assignClicheToWorkOrder(int $clicheId, int $workOrderId, string $notes, string $operatorName): array
    {
        $notes = trim($notes);
        $operatorName = trim($operatorName);
        $cliche = $this->getCliche($clicheId);
        $workOrder = $this->getWorkOrder($workOrderId);
        $errors = [];

        if ($cliche === null) {
            $errors['cliche_id'] = 'El clisé no existe.';
        }
        if ($workOrder === null) {
            $errors['work_order_id'] = 'La OT no existe.';
        } elseif (!in_array((string)($workOrder['status'] ?? ''), ['OPEN', 'ACTIVE', 'CUTTING'], true)) {
            $errors['work_order_id'] = 'La OT no está disponible para asignar clisés.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($cliche !== null) {
            $clicheStatus = strtoupper(trim((string)($cliche['status'] ?? '')));
            $currentWorkOrderId = (int)($cliche['current_work_order_id'] ?? 0);
            if ($clicheStatus === 'IN_USE' && $currentWorkOrderId > 0 && $currentWorkOrderId !== $workOrderId) {
                $errors['cliche_id'] = 'El clisé ya está en uso en la OT ' . ((string)($cliche['ot_code'] ?? '') !== '' ? (string)$cliche['ot_code'] : ('#' . $currentWorkOrderId)) . '.';
            } elseif ($clicheStatus === 'IN_USE' && $currentWorkOrderId === $workOrderId) {
                $errors['cliche_id'] = 'El clisé ya está asignado a esta OT.';
            } elseif (!in_array($clicheStatus, ['AVAILABLE'], true)) {
                $errors['cliche_id'] = 'Solo se pueden asignar clisés disponibles.';
            }
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $fromLocationCode = (string)($cliche['location_code'] ?? '');
        $toLocationCode = 'OT ' . (string)$workOrder['ot_code'];
        $stmt = $this->pdo->prepare(
            'UPDATE cliches
             SET status = :status,
                 current_work_order_id = :current_work_order_id,
                 current_operator_name = :current_operator_name,
                 current_assigned_at = CURRENT_TIMESTAMP,
                 location_code = :location_code,
                 location_detail = :location_detail
             WHERE id = :id'
        );
        $stmt->execute([
            ':status' => 'IN_USE',
            ':current_work_order_id' => $workOrderId,
            ':current_operator_name' => $operatorName,
            ':location_code' => 'EN_USO',
            ':location_detail' => $toLocationCode,
            ':id' => $clicheId,
        ]);

        $log = $this->pdo->prepare(
            'INSERT INTO cliche_usage_logs (
                cliche_id, work_order_id, action_type, from_location_code, to_location_code, operator_name, notes
             ) VALUES (
                :cliche_id, :work_order_id, :action_type, :from_location_code, :to_location_code, :operator_name, :notes
             )'
        );
        $log->execute([
            ':cliche_id' => $clicheId,
            ':work_order_id' => $workOrderId,
            ':action_type' => 'ASSIGNED',
            ':from_location_code' => $fromLocationCode !== '' ? $fromLocationCode : null,
            ':to_location_code' => $toLocationCode,
            ':operator_name' => $operatorName,
            ':notes' => $notes !== '' ? $notes : null,
        ]);

        $this->insertEvent('CLICHE_ASSIGNED', [
            'cliche_id' => $clicheId,
            'cliche_code' => (string)$cliche['code'],
            'work_order_id' => $workOrderId,
            'ot_code' => (string)$workOrder['ot_code'],
            'operator_name' => $operatorName,
            'notes' => $notes,
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function returnCliche(int $clicheId, string $locationCode, string $locationDetail, string $notes, string $operatorName): array
    {
        $locationCode = strtoupper(trim($locationCode));
        $locationDetail = trim($locationDetail);
        $notes = trim($notes);
        $operatorName = trim($operatorName);
        $cliche = $this->getCliche($clicheId);
        $errors = [];

        if ($cliche === null) {
            $errors['cliche_id'] = 'El clisé no existe.';
        }
        if ($locationCode === '') {
            $errors['location_code'] = 'Debes indicar la ubicación de retorno.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($cliche !== null && strtoupper(trim((string)($cliche['status'] ?? ''))) !== 'IN_USE') {
            $errors['cliche_id'] = 'Solo se pueden devolver clisés que estén en uso.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $workOrderId = (int)($cliche['current_work_order_id'] ?? 0);
        $fromLocationCode = trim((string)($cliche['location_detail'] ?? $cliche['location_code'] ?? ''));
        $stmt = $this->pdo->prepare(
            'UPDATE cliches
             SET status = :status,
                 current_work_order_id = NULL,
                 current_operator_name = NULL,
                 current_assigned_at = NULL,
                 location_code = :location_code,
                 location_detail = :location_detail
             WHERE id = :id'
        );
        $stmt->execute([
            ':status' => 'AVAILABLE',
            ':location_code' => $locationCode,
            ':location_detail' => $locationDetail !== '' ? $locationDetail : null,
            ':id' => $clicheId,
        ]);

        $log = $this->pdo->prepare(
            'INSERT INTO cliche_usage_logs (
                cliche_id, work_order_id, action_type, from_location_code, to_location_code, operator_name, notes
             ) VALUES (
                :cliche_id, :work_order_id, :action_type, :from_location_code, :to_location_code, :operator_name, :notes
             )'
        );
        $log->execute([
            ':cliche_id' => $clicheId,
            ':work_order_id' => $workOrderId > 0 ? $workOrderId : null,
            ':action_type' => 'RETURNED',
            ':from_location_code' => $fromLocationCode !== '' ? $fromLocationCode : null,
            ':to_location_code' => $locationCode,
            ':operator_name' => $operatorName,
            ':notes' => $notes !== '' ? $notes : null,
        ]);

        $this->insertEvent('CLICHE_RETURNED', [
            'cliche_id' => $clicheId,
            'cliche_code' => (string)$cliche['code'],
            'work_order_id' => $workOrderId > 0 ? $workOrderId : null,
            'operator_name' => $operatorName,
            'location_code' => $locationCode,
            'notes' => $notes,
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function listClicheUsageLogsByCliche(int $clicheId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT log.*, wo.ot_code
             FROM cliche_usage_logs log
             LEFT JOIN work_orders wo ON wo.id = log.work_order_id
             WHERE log.cliche_id = :cliche_id
             ORDER BY log.id DESC'
        );
        $stmt->execute([':cliche_id' => $clicheId]);
        return $stmt->fetchAll();
    }

    public function listClicheUsageLogsByWorkOrder(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT log.*, c.code AS cliche_code, c.description AS cliche_description
             FROM cliche_usage_logs log
             INNER JOIN cliches c ON c.id = log.cliche_id
             WHERE log.work_order_id = :work_order_id
             ORDER BY log.id DESC'
        );
        $stmt->execute([':work_order_id' => $workOrderId]);
        return $stmt->fetchAll();
    }

    public function listClichesAssignedToWorkOrder(int $workOrderId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM cliches
             WHERE current_work_order_id = :work_order_id
               AND status = "IN_USE"
             ORDER BY code ASC'
        );
        $stmt->execute([':work_order_id' => $workOrderId]);
        return $stmt->fetchAll();
    }


    public function listAniloxCatalog(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, code, name, bcm, lpi,
                    CONCAT(code, " - ", name, CASE
                        WHEN COALESCE(lpi, "") <> "" THEN CONCAT(" / ", lpi)
                        ELSE ""
                    END) AS display_label
             FROM production_anilox_catalog
             WHERE is_active = 1
             ORDER BY sort_order ASC, code ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getWorkOrderAniloxAssignments(int $workOrderId): array
    {
        if ($workOrderId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT wa.id, wa.work_order_id, wa.unit_no, wa.color_name, wa.anilox_id, wa.updated_by, wa.updated_at,
                    ac.code AS anilox_code, ac.name AS anilox_name,
                    CONCAT(ac.code, " - ", ac.name, CASE
                        WHEN COALESCE(ac.lpi, "") <> "" THEN CONCAT(" / ", ac.lpi)
                        ELSE ""
                    END) AS anilox_label
             FROM work_order_anilox_assignments wa
             LEFT JOIN production_anilox_catalog ac ON ac.id = wa.anilox_id
             WHERE wa.work_order_id = :work_order_id
             ORDER BY wa.unit_no ASC'
        );
        $stmt->execute([':work_order_id' => $workOrderId]);
        return $stmt->fetchAll();
    }

    /**
     * @param array<int,array{unit_no:int,color_name:string,anilox_id:int|null}> $slots
     */
    public function saveWorkOrderAniloxAssignments(int $workOrderId, array $slots, string $operatorName): array
    {
        $errors = [];
        $operatorName = trim($operatorName);

        if ($workOrderId <= 0 || $this->getWorkOrder($workOrderId) === null) {
            $errors['work_order_id'] = 'La OT no existe.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'El operador es obligatorio.';
        }
        if ($slots === []) {
            $errors['slots'] = 'Debes enviar al menos una unidad.';
        }

        $normalizedSlots = [];
        $seenUnits = [];
        foreach ($slots as $index => $slot) {
            $unitNo = isset($slot['unit_no']) ? (int)$slot['unit_no'] : ($index + 1);
            $colorName = trim((string)($slot['color_name'] ?? ''));
            $aniloxId = isset($slot['anilox_id']) && (int)$slot['anilox_id'] > 0 ? (int)$slot['anilox_id'] : null;

            if ($unitNo < 1 || $unitNo > 6) {
                $errors['unit_' . $index] = 'Las unidades de anilox deben estar entre 1 y 6.';
                continue;
            }
            if (isset($seenUnits[$unitNo])) {
                $errors['unit_dup_' . $unitNo] = 'La unidad ' . $unitNo . ' está repetida.';
                continue;
            }
            $seenUnits[$unitNo] = true;

            if (mb_strlen($colorName) > 120) {
                $errors['color_' . $unitNo] = 'El color de la unidad ' . $unitNo . ' supera el largo permitido.';
            }
            if ($aniloxId !== null) {
                $stmt = $this->pdo->prepare('SELECT id FROM production_anilox_catalog WHERE id = :id AND is_active = 1');
                $stmt->execute([':id' => $aniloxId]);
                if ($stmt->fetch() === false) {
                    $errors['anilox_' . $unitNo] = 'El anilox seleccionado en la unidad ' . $unitNo . ' no existe.';
                }
            }

            $normalizedSlots[] = [
                'unit_no' => $unitNo,
                'color_name' => $colorName,
                'anilox_id' => $aniloxId,
            ];
        }

        if (count($normalizedSlots) !== 6) {
            $errors['slots_count'] = 'La configuración debe tener exactamente 6 unidades.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        usort(
            $normalizedSlots,
            static fn(array $left, array $right): int => (int)$left['unit_no'] <=> (int)$right['unit_no']
        );

        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare('DELETE FROM work_order_anilox_assignments WHERE work_order_id = :work_order_id');
            $delete->execute([':work_order_id' => $workOrderId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO work_order_anilox_assignments (
                    work_order_id, unit_no, color_name, anilox_id, updated_by
                 ) VALUES (
                    :work_order_id, :unit_no, :color_name, :anilox_id, :updated_by
                 )'
            );

            foreach ($normalizedSlots as $slot) {
                $insert->execute([
                    ':work_order_id' => $workOrderId,
                    ':unit_no' => (int)$slot['unit_no'],
                    ':color_name' => (string)$slot['color_name'],
                    ':anilox_id' => $slot['anilox_id'],
                    ':updated_by' => $operatorName,
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->insertEvent('WORK_ORDER_ANILOX_UPDATED', [
            'work_order_id' => $workOrderId,
            'operator_name' => $operatorName,
            'slots' => array_map(
                static fn(array $slot): array => [
                    'unit_no' => (int)$slot['unit_no'],
                    'color_name' => (string)$slot['color_name'],
                    'anilox_id' => $slot['anilox_id'],
                ],
                $normalizedSlots
            ),
        ]);

        return ['ok' => true, 'errors' => []];
    }

}

class ToolingService
{
    use ToolingServiceTrait;

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
