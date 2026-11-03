<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Datos · Gestión de Residuos, Molino y Compactadora (src/Services/Production/WasteService.php)
// =============================================================================

trait WasteServiceTrait
{
    public function listWasteInventoryTotals(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT UPPER(TRIM(material_code)) AS material_code,
                    COALESCE(SUM(weight_kg), 0) AS total_kg
             FROM waste_inventory_entries
             GROUP BY UPPER(TRIM(material_code))'
        );
        $stmt->execute();
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[(string)$row['material_code']] = (float)($row['total_kg'] ?? 0);
        }
        $defaults = [
            'PP' => 0.0,
            'PLA' => 0.0,
            'FILM' => 0.0,
        ];
        foreach ($defaults as $code => $zero) {
            if (!isset($rows[$code])) {
                $rows[$code] = $zero;
            }
        }
        return $rows;
    }

    public function listWastePendingInventoryTotals(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT UPPER(TRIM(material_code)) AS material_code,
                    COALESCE(SUM(weight_kg), 0) AS total_kg
             FROM waste_inventory_entries
             WHERE withdrawn_at IS NULL
             GROUP BY UPPER(TRIM(material_code))'
        );
        $stmt->execute();
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[(string)$row['material_code']] = (float)($row['total_kg'] ?? 0);
        }
        $defaults = [
            'PP' => 0.0,
            'PLA' => 0.0,
            'FILM' => 0.0,
        ];
        foreach ($defaults as $code => $zero) {
            if (!isset($rows[$code])) {
                $rows[$code] = $zero;
            }
        }
        return $rows;
    }

    public function listPendingWasteWithdrawalsOfDay(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, shift_session_id, material_code, weight_kg, operator_name, supplier_operator_name, supplier_machine_code, supplier_machine_name, created_at
             FROM waste_inventory_entries
             WHERE withdrawn_at IS NULL
               AND DATE(created_at) = CURDATE()
             ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function recordWasteInventoryEntry(
        string $materialCode,
        float $weightKg,
        string $operatorName,
        ?int $shiftSessionId = null,
        ?string $comments = null,
        ?string $supplierOperatorName = null,
        ?string $supplierMachineCode = null,
        ?string $supplierMachineName = null
    ): array {
        $materialCode = strtoupper(trim($materialCode));
        $weightKg = round(max(0.0, $weightKg), 3);
        $operatorName = trim($operatorName);
        $supplierOperatorName = trim((string)$supplierOperatorName);
        if ($supplierOperatorName === '') {
            $supplierOperatorName = null;
        }
        $supplierMachineCode = trim((string)$supplierMachineCode);
        if ($supplierMachineCode === '') {
            $supplierMachineCode = null;
        }
        $supplierMachineName = trim((string)$supplierMachineName);
        if ($supplierMachineName === '') {
            $supplierMachineName = null;
        }
        $comments = trim((string)$comments);

        $errors = [];
        if (!in_array($materialCode, ['PP', 'PLA', 'FILM'], true)) {
            $errors[] = 'Material inválido. Usa PP, PLA o FILM.';
        }
        if ($weightKg <= 0) {
            $errors[] = 'Debes indicar un peso en kg mayor a 0.';
        }
        if ($operatorName === '') {
            $errors[] = 'El operador es obligatorio.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO waste_inventory_entries (shift_session_id, material_code, weight_kg, operator_name, supplier_operator_name, supplier_machine_code, supplier_machine_name, comments)
             VALUES (:shift_session_id, :material_code, :weight_kg, :operator_name, :supplier_operator_name, :supplier_machine_code, :supplier_machine_name, :comments)'
        );
        $stmt->execute([
            ':shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            ':material_code' => $materialCode,
            ':weight_kg' => $weightKg,
            ':operator_name' => $operatorName,
            ':supplier_operator_name' => $supplierOperatorName,
            ':supplier_machine_code' => $supplierMachineCode,
            ':supplier_machine_name' => $supplierMachineName,
            ':comments' => $comments !== '' ? $comments : null,
        ]);
        $id = (int)$this->pdo->lastInsertId();

        $this->insertEvent('WASTE_INVENTORY_ENTRY_CREATED', [
            'waste_inventory_entry_id' => $id,
            'shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            'material_code' => $materialCode,
            'weight_kg' => $weightKg,
            'operator_name' => $operatorName,
            'supplier_operator_name' => $supplierOperatorName,
            'supplier_machine_code' => $supplierMachineCode,
            'supplier_machine_name' => $supplierMachineName,
            'comments' => $comments !== '' ? $comments : null,
        ]);

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    public function listWasteInventoryRecentEntries(int $limit = 25): array
    {
        $limit = max(1, $limit);
        $stmt = $this->pdo->prepare(
            'SELECT id, shift_session_id, material_code, weight_kg, operator_name, supplier_operator_name, supplier_machine_code, supplier_machine_name, comments, created_at
             FROM waste_inventory_entries
             ORDER BY id DESC
             LIMIT ' . $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listWasteInventoryBySupplierTotals(): array
    {
        $rows = $this->pdo->query(
            'SELECT
                TRIM(COALESCE(supplier_operator_name, "")) AS supplier_operator_name,
                UPPER(TRIM(material_code)) AS material_code,
                COALESCE(SUM(weight_kg), 0) AS total_kg
             FROM waste_inventory_entries
             GROUP BY TRIM(COALESCE(supplier_operator_name, "")), UPPER(TRIM(material_code))
             ORDER BY supplier_operator_name ASC, material_code ASC'
        )->fetchAll();

        $suppliers = [];
        foreach ($rows as $row) {
            $supplier = trim((string)($row['supplier_operator_name'] ?? ''));
            if ($supplier === '') {
                $supplier = 'Sin asignar';
            }
            if (!isset($suppliers[$supplier])) {
                $suppliers[$supplier] = [
                    'supplier_operator_name' => $supplier,
                    'totals' => ['PP' => 0.0, 'PLA' => 0.0, 'FILM' => 0.0],
                    'total_kg' => 0.0,
                ];
            }
            $material = strtoupper(trim((string)($row['material_code'] ?? '')));
            $kg = (float)($row['total_kg'] ?? 0.0);
            if (in_array($material, ['PP', 'PLA', 'FILM'], true)) {
                $suppliers[$supplier]['totals'][$material] = round(($suppliers[$supplier]['totals'][$material] ?? 0.0) + $kg, 3);
            }
            $suppliers[$supplier]['total_kg'] = round(($suppliers[$supplier]['total_kg'] ?? 0.0) + $kg, 3);
        }
        return array_values($suppliers);
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function recordWasteOperation(
        string $operationCode,
        string $operatorName,
        ?int $shiftSessionId = null,
        ?string $comments = null
    ): array {
        $operationCode = strtoupper(trim($operationCode));
        $operatorName = trim($operatorName);
        $comments = trim((string)$comments);

        $allowed = [
            'MOLINO',
            'COMPACTADORA',
            'RETIRO',
            'CRECION_MERMA',
            'CREACION_MERMA',
            'RESPEL',
            'PAUSA_MOLINO',
            'MANTENCION_MOLINO',
            'PAUSA_MOLINO_INICIO',
            'PAUSA_MOLINO_FIN',
            'MANTENCION_MOLINO_INICIO',
            'MANTENCION_MOLINO_FIN',
            'PAUSA_COMPACTADORA',
            'MANTENCION_COMPACTADORA',
            'PAUSA_COMPACTADORA_INICIO',
            'PAUSA_COMPACTADORA_FIN',
            'MANTENCION_COMPACTADORA_INICIO',
            'MANTENCION_COMPACTADORA_FIN',
        ];
        $aliases = [
            'MOLINO' => 'MOLINO',
            'COMPACTADORA' => 'COMPACTADORA',
            'RETIRO' => 'RETIRO',
            'CRECION_MERMA' => 'CREACION_MERMA',
            'CREACION MERMA' => 'CREACION_MERMA',
            'CREACION_MERMA' => 'CREACION_MERMA',
            'RESPEL' => 'RESPEL',
            'PAUSA_MOLINO' => 'PAUSA_MOLINO',
            'MANTENCION_MOLINO' => 'MANTENCION_MOLINO',
            'PAUSA_MOLINO_INICIO' => 'PAUSA_MOLINO_INICIO',
            'PAUSA_MOLINO_FIN' => 'PAUSA_MOLINO_FIN',
            'MANTENCION_MOLINO_INICIO' => 'MANTENCION_MOLINO_INICIO',
            'MANTENCION_MOLINO_FIN' => 'MANTENCION_MOLINO_FIN',
            'PAUSA_COMPACTADORA' => 'PAUSA_COMPACTADORA',
            'MANTENCION_COMPACTADORA' => 'MANTENCION_COMPACTADORA',
            'PAUSA_COMPACTADORA_INICIO' => 'PAUSA_COMPACTADORA_INICIO',
            'PAUSA_COMPACTADORA_FIN' => 'PAUSA_COMPACTADORA_FIN',
            'MANTENCION_COMPACTADORA_INICIO' => 'MANTENCION_COMPACTADORA_INICIO',
            'MANTENCION_COMPACTADORA_FIN' => 'MANTENCION_COMPACTADORA_FIN',
        ];
        $normalized = $aliases[$operationCode] ?? null;
        $errors = [];
        if ($normalized === null || !in_array($normalized, $allowed, true)) {
            $errors[] = 'Operación inválida de gestión de residuos.';
        }
        if ($operatorName === '') {
            $errors[] = 'El operador es obligatorio.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO waste_operations (shift_session_id, operation_code, operator_name, comments)
             VALUES (:shift_session_id, :operation_code, :operator_name, :comments)'
        );
        $stmt->execute([
            ':shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            ':operation_code' => $normalized,
            ':operator_name' => $operatorName,
            ':comments' => $comments !== '' ? $comments : null,
        ]);
        $id = (int)$this->pdo->lastInsertId();

        $this->insertEvent('WASTE_OPERATION_CREATED', [
            'waste_operation_id' => $id,
            'shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            'operation_code' => $normalized,
            'operator_name' => $operatorName,
            'comments' => $comments !== '' ? $comments : null,
        ]);

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    public function listRecentWasteOperations(int $limit = 10): array
    {
        $limit = max(1, $limit);
        $stmt = $this->pdo->prepare(
            'SELECT id, operation_code, material_code, weight_kg, operator_name, supplier_operator_name, supplier_machine_code, supplier_machine_name, comments, created_at, shift_session_id
             FROM waste_operations
             ORDER BY id DESC
             LIMIT ' . $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function markWasteEntriesWithdrawn(array $entryIds, int $withdrawalOperationId, string $operatorName): int
    {
        if ($entryIds === []) {
            return 0;
        }
        $ids = array_values(array_map('intval', $entryIds));
        $idPlaceholders = [];
        foreach (array_keys($ids) as $i) {
            $idPlaceholders[] = ':id' . $i;
        }
        $placeholders = implode(',', $idPlaceholders);
        $stmt = $this->pdo->prepare(
            'UPDATE waste_inventory_entries
             SET withdrawn_at = CURRENT_TIMESTAMP,
                 withdrawn_by_operator = :op,
                 withdrawal_operation_id = :opId
             WHERE id IN (' . $placeholders . ') AND withdrawn_at IS NULL'
        );
        $stmt->bindValue(':op', trim($operatorName));
        $stmt->bindValue(':opId', $withdrawalOperationId, \PDO::PARAM_INT);
        foreach ($ids as $i => $id) {
            $stmt->bindValue(':id' . $i, $id, \PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int)$stmt->rowCount();
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int, affected?:int}
     */
    public function recordWasteWithdrawalOperation(
        array $entryIds,
        string $operatorName,
        ?int $shiftSessionId = null,
        ?string $materialCode = null,
        ?float $weightKg = null,
        ?string $supplierOperatorName = null,
        ?string $supplierMachineCode = null,
        ?string $supplierMachineName = null,
        ?string $comments = null
    ): array {
        $operatorName = trim($operatorName);
        $errors = [];
        if ($operatorName === '') {
            $errors[] = 'El operador es obligatorio para el retiro.';
        }
        if ($entryIds === []) {
            $errors[] = 'No hay sacos seleccionados para retirar.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        $ids = array_values(array_unique(array_map('intval', $entryIds)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT id, material_code, weight_kg, supplier_operator_name, supplier_machine_code, supplier_machine_name
             FROM waste_inventory_entries
             WHERE id IN (' . $placeholders . ') AND withdrawn_at IS NULL
             ORDER BY id ASC'
        );
        foreach ($ids as $i => $id) {
            $stmt->bindValue($i + 1, $id, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $entries = $stmt->fetchAll();
        if ($entries === []) {
            return ['ok' => false, 'errors' => ['Los sacos seleccionados ya fueron retirados o no existen.']];
        }

        $mat = $materialCode !== null ? strtoupper(trim((string)$materialCode)) : null;
        $w = $weightKg !== null ? (float)$weightKg : null;
        $supOp = $supplierOperatorName !== null ? trim((string)$supplierOperatorName) : null;
        $supCode = $supplierMachineCode !== null ? trim((string)$supplierMachineCode) : null;
        $supName = $supplierMachineName !== null ? trim((string)$supplierMachineName) : null;
        if ($mat === null && count($entries) === 1) {
            $mat = strtoupper(trim((string)($entries[0]['material_code'] ?? '')));
        }
        if ($w === null && count($entries) === 1) {
            $w = (float)($entries[0]['weight_kg'] ?? 0);
        }
        if ($w === null) {
            $sum = 0.0;
            foreach ($entries as $e) {
                $sum += (float)($e['weight_kg'] ?? 0);
            }
            $w = round($sum, 3);
        }
        if ($supOp === null && count($entries) === 1) {
            $supOp = trim((string)($entries[0]['supplier_operator_name'] ?? ''));
        }
        if ($supCode === null && count($entries) === 1) {
            $supCode = trim((string)($entries[0]['supplier_machine_code'] ?? ''));
        }
        if ($supName === null && count($entries) === 1) {
            $supName = trim((string)($entries[0]['supplier_machine_name'] ?? ''));
        }

        $this->pdo->beginTransaction();
        try {
            $opStmt = $this->pdo->prepare(
                'INSERT INTO waste_operations (shift_session_id, operation_code, material_code, weight_kg, operator_name, supplier_operator_name, supplier_machine_code, supplier_machine_name, comments)
                 VALUES (:shift_session_id, :operation_code, :material_code, :weight_kg, :operator_name, :supplier_operator_name, :supplier_machine_code, :supplier_machine_name, :comments)'
            );
            $opStmt->execute([
                ':shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
                ':operation_code' => 'RETIRO',
                ':material_code' => ($mat !== null && $mat !== '') ? $mat : null,
                ':weight_kg' => $w,
                ':operator_name' => $operatorName,
                ':supplier_operator_name' => ($supOp !== null && $supOp !== '') ? $supOp : null,
                ':supplier_machine_code' => ($supCode !== null && $supCode !== '') ? $supCode : null,
                ':supplier_machine_name' => ($supName !== null && $supName !== '') ? $supName : null,
                ':comments' => $comments !== null && trim((string)$comments) !== '' ? trim((string)$comments) : null,
            ]);
            $opId = (int)$this->pdo->lastInsertId();

            $affected = $this->markWasteEntriesWithdrawn($ids, $opId, $operatorName);

            $this->insertEvent('WASTE_WITHDRAWAL_CREATED', [
                'waste_operation_id' => $opId,
                'entry_ids' => $ids,
                'material_code' => $mat,
                'weight_kg' => $w,
                'operator_name' => $operatorName,
                'supplier_operator_name' => $supOp,
            ]);

            $this->pdo->commit();
            return ['ok' => true, 'id' => $opId, 'affected' => $affected, 'errors' => []];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['ok' => false, 'errors' => ['No se pudo registrar el retiro: ' . $e->getMessage()]];
        }
    }

    public function listWasteCreationMaterialOptions(): array
    {
        return [
            ['code' => 'PP', 'label' => 'PP'],
            ['code' => 'PLA', 'label' => 'PLA'],
            ['code' => 'FILM', 'label' => 'FILM'],
            ['code' => 'PAPEL', 'label' => 'PAPEL'],
            ['code' => 'CARTON', 'label' => 'CARTÓN'],
            ['code' => 'BOLSA', 'label' => 'BOLSA'],
            ['code' => 'OTRO', 'label' => 'OTRO'],
        ];
    }

    public function listWasteCreationAreaOptions(): array
    {
        return [
            ['code' => 'IMPRESION', 'label' => 'Impresión'],
            ['code' => 'SELLADO', 'label' => 'Sellado'],
            ['code' => 'REBOBINADO', 'label' => 'Rebobinado'],
            ['code' => 'EMBALAJE', 'label' => 'Embalaje'],
            ['code' => 'SERIGRAFIA', 'label' => 'Serigrafía'],
            ['code' => 'PULPO', 'label' => 'Pulpo'],
            ['code' => 'BODEGA', 'label' => 'Bodega'],
            ['code' => 'CALIDAD', 'label' => 'Calidad'],
            ['code' => 'MANTENCION', 'label' => 'Mantención'],
            ['code' => 'OTRO', 'label' => 'Otro'],
        ];
    }

    public function listWasteCreationMotivoOptions(): array
    {
        return [
            ['code' => 'CAMBIO_ORDEN', 'label' => 'Cambio de orden'],
            ['code' => 'ERROR_IMPRESION', 'label' => 'Error de impresión'],
            ['code' => 'ERROR_SELLADO', 'label' => 'Error de sellado'],
            ['code' => 'MATERIAL_DEFECTUOSO', 'label' => 'Material defectuoso'],
            ['code' => 'AJUSTE_APROBACION', 'label' => 'Ajuste / Aprobación'],
            ['code' => 'SCRAP_PRODUCCION', 'label' => 'Scrap de producción'],
            ['code' => 'MUESTRAS', 'label' => 'Muestras'],
            ['code' => 'OBSOLETO', 'label' => 'Obsoleto / Vencido'],
            ['code' => 'DEVOLUCION', 'label' => 'Devolución cliente'],
            ['code' => 'LIMPIEZA', 'label' => 'Limpieza'],
            ['code' => 'REPARACION', 'label' => 'Reparación'],
            ['code' => 'OTRO', 'label' => 'Otro motivo'],
        ];
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function recordWasteCreationOperation(
        string $materialCode,
        float $weightKg,
        string $solicitante,
        string $area,
        string $motivo,
        string $operatorName,
        ?int $shiftSessionId = null
    ): array {
        $errors = [];
        $materialCode = strtoupper(trim($materialCode));
        $weightKg = (float)$weightKg;
        $solicitante = trim($solicitante);
        $area = trim($area);
        $motivo = trim($motivo);
        $operatorName = trim($operatorName);

        $validMaterials = [];
        foreach ($this->listWasteCreationMaterialOptions() as $m) {
            $validMaterials[] = $m['code'];
        }
        $validAreas = [];
        foreach ($this->listWasteCreationAreaOptions() as $a) {
            $validAreas[] = $a['code'];
        }
        $validMotivos = [];
        foreach ($this->listWasteCreationMotivoOptions() as $m) {
            $validMotivos[] = $m['code'];
        }

        if ($materialCode === '' || !in_array($materialCode, $validMaterials, true)) {
            $errors[] = 'Materialidad inválida.';
        }
        if ($weightKg <= 0) {
            $errors[] = 'El peso debe ser mayor a 0.';
        }
        if ($solicitante === '') {
            $errors[] = 'El solicitante es obligatorio.';
        }
        if ($area === '' || !in_array($area, $validAreas, true)) {
            $errors[] = 'Área inválida.';
        }
        if ($motivo === '' || !in_array($motivo, $validMotivos, true)) {
            $errors[] = 'Motivo inválido.';
        }
        if ($operatorName === '') {
            $errors[] = 'El operador es obligatorio.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO waste_operations
                (shift_session_id, operation_code, material_code, weight_kg, operator_name,
                 solicitante, area, motivo, created_at)
            VALUES
                (:shift_session_id, "CREACION_MERMA", :material_code, :weight_kg, :operator_name,
                 :solicitante, :area, :motivo, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            ':material_code' => $materialCode,
            ':weight_kg' => $weightKg,
            ':operator_name' => $operatorName,
            ':solicitante' => $solicitante,
            ':area' => $area,
            ':motivo' => $motivo,
        ]);
        $id = (int)$this->pdo->lastInsertId();

        $this->insertEvent('WASTE_CREATION_RECORDED', [
            'waste_operation_id' => $id,
            'shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            'material_code' => $materialCode,
            'weight_kg' => $weightKg,
            'solicitante' => $solicitante,
            'area' => $area,
            'motivo' => $motivo,
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    /**
     * @return array{id:int,material_code:string,entry_kg:string,operator_name:string,shift_session_id:int|null}|null
     */
    public function getActiveMolinoOperation(string $operatorName, ?int $shiftSessionId = null): ?array
    {
        $sql = 'SELECT id, material_code, entry_kg, operator_name, shift_session_id
                FROM waste_operations
                WHERE operation_code = "MOLINO" AND exit_kg IS NULL AND entry_kg IS NOT NULL ';
        $params = [];
        if ($shiftSessionId > 0) {
            $sql .= ' AND shift_session_id = :shift_session_id';
            $params[':shift_session_id'] = $shiftSessionId;
        } else {
            $sql .= ' AND operator_name = :operator_name AND DATE(created_at) = CURDATE()';
            $params[':operator_name'] = trim($operatorName);
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function recordMolinoEntry(string $materialCode, float $entryKg, string $operatorName, ?int $shiftSessionId = null): array
    {
        $errors = [];
        $materialCode = strtoupper(trim($materialCode));
        $entryKg = (float)$entryKg;
        $operatorName = trim($operatorName);

        if ($materialCode !== 'PLA') {
            $errors[] = 'El molino solo procesa materialidad PLA.';
        }
        if ($entryKg <= 0) {
            $errors[] = 'El peso de ingreso debe ser mayor a 0.';
        }
        $stockPla = (float)($this->listWastePendingInventoryTotals()['PLA'] ?? 0.0);
        if ($entryKg > $stockPla + 0.0001) {
            $errors[] = 'El peso de ingreso supera el stock PLA disponible en bodega transitoria (' . number_format($stockPla, 3, '.', '') . ' kg).';
        }
        if ($operatorName === '') {
            $errors[] = 'El operador es obligatorio.';
        }
        if ($this->getActiveMolinoOperation($operatorName, $shiftSessionId) !== null) {
            $errors[] = 'Ya existe una operación de molino abierta; debe finalizarla antes de iniciar una nueva.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO waste_operations
                (shift_session_id, operation_code, material_code, entry_kg, operator_name, created_at)
            VALUES
                (:shift_session_id, "MOLINO", :material_code, :entry_kg, :operator_name, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            ':material_code' => $materialCode,
            ':entry_kg' => $entryKg,
            ':operator_name' => $operatorName,
        ]);
        $id = (int)$this->pdo->lastInsertId();

        $this->insertEvent('WASTE_MOLINO_ENTRY_RECORDED', [
            'waste_operation_id' => $id,
            'shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            'material_code' => $materialCode,
            'entry_kg' => $entryKg,
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function finalizeMolinoOperation(int $opId, float $exitKg, int $palletCount, string $operatorName): array
    {
        $errors = [];
        $opId = (int)$opId;
        $exitKg = (float)$exitKg;
        $palletCount = (int)$palletCount;
        $operatorName = trim($operatorName);

        if ($opId <= 0) {
            $errors[] = 'Operación de molino inválida.';
        }
        if ($exitKg <= 0) {
            $errors[] = 'El peso de salida debe ser mayor a 0.';
        }
        if ($palletCount < 0) {
            $errors[] = 'La cantidad de palet no puede ser negativa.';
        }
        if ($operatorName === '') {
            $errors[] = 'El operador es obligatorio.';
        }
        $row = null;
        if ($opId > 0) {
            $stmt = $this->pdo->prepare('SELECT id, operation_code, material_code, entry_kg, exit_kg, operator_name FROM waste_operations WHERE id = :id');
            $stmt->execute([':id' => $opId]);
            $row = $stmt->fetch();
            if ($row === false) {
                $errors[] = 'No se encontró la operación de molino.';
            } elseif (($row['operation_code'] ?? '') !== 'MOLINO') {
                $errors[] = 'La operación seleccionada no corresponde a molino.';
            } elseif (($row['exit_kg'] ?? null) !== null) {
                $errors[] = 'Esta operación de molino ya fue finalizada.';
            }
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare('
            UPDATE waste_operations
            SET exit_kg = :exit_kg, pallet_count = :pallet_count
            WHERE id = :id
        ');
        $stmt->execute([
            ':exit_kg' => $exitKg,
            ':pallet_count' => $palletCount > 0 ? $palletCount : null,
            ':id' => $opId,
        ]);

        $this->insertEvent('WASTE_MOLINO_FINALIZED', [
            'waste_operation_id' => $opId,
            'material_code' => (string)($row['material_code'] ?? ''),
            'entry_kg' => (float)($row['entry_kg'] ?? 0.0),
            'exit_kg' => $exitKg,
            'pallet_count' => $palletCount,
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'id' => $opId, 'errors' => []];
    }

    /**
     * Devuelve filas para la tabla de registro producción Molino, estilo legacy-setup-event-table.
     *
     * @return list<array{event:string,start_at:string,end_at:string,duration_label:string,quantity_label:string,comments:string,option_badge_type:string,option_label:string,option_href?:string,option_form_action?:string,option_form_params?:array<string,mixed>}>
     */
    public function listMolinoProductionEvents(string $operatorName, ?int $shiftSessionId = null): array
    {
        $operatorName = trim($operatorName);
        if ($operatorName === '' && !($shiftSessionId > 0)) {
            return [];
        }

        $params = [];
        $where = ' WHERE operation_code = "MOLINO" AND exit_kg IS NULL AND entry_kg IS NOT NULL ';
        if ($shiftSessionId > 0) {
            $where .= ' AND shift_session_id = :shift_session_id';
            $params[':shift_session_id'] = $shiftSessionId;
        } else {
            $where .= ' AND operator_name = :operator_name AND DATE(created_at) = CURDATE()';
            $params[':operator_name'] = $operatorName;
        }

        $stmt = $this->pdo->prepare('
            SELECT id, material_code, entry_kg, operator_name, created_at
            FROM waste_operations
            ' . $where . '
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute($params);
        $molinoOp = $stmt->fetch();
        if ($molinoOp === false) {
            return [];
        }

        $startAt = (string)($molinoOp['created_at'] ?? '');
        $entryVal = (float)($molinoOp['entry_kg'] ?? 0.0);
        $operatorLabel = trim((string)($molinoOp['operator_name'] ?? $operatorName));

        $rows = [];
        $rows[] = [
            'event' => 'Ingreso',
            'start_at' => $startAt !== '' ? $startAt : '-',
            'end_at' => '-',
            'duration_label' => '0h 0m',
            'quantity_label' => number_format($entryVal, 3, '.', '') . ' kg',
            'comments' => 'Ingreso Molino registrado · Operador: ' . ($operatorLabel !== '' ? $operatorLabel : $operatorName),
            'option_badge_type' => 'configured',
            'option_label' => 'Registrado',
        ];

        $rows[] = [
            'event' => 'Producción',
            'start_at' => $startAt !== '' ? $startAt : '-',
            'end_at' => '-',
            'duration_label' => '-',
            'quantity_label' => 'Entrada ' . number_format($entryVal, 3, '.', '') . ' kg',
            'comments' => 'Operador: ' . ($operatorLabel !== '' ? $operatorLabel : $operatorName) . ' · Producción en curso.',
            'option_badge_type' => 'finish-production',
            'option_label' => 'Terminar producción',
            'option_trigger' => 'molino-finalize',
        ];

        $eventsParams = [
            ':started_at' => $startAt,
        ];
        $eventsWhere = ' WHERE created_at >= :started_at AND operation_code IN ("PAUSA_MOLINO_INICIO","PAUSA_MOLINO_FIN","MANTENCION_MOLINO_INICIO","MANTENCION_MOLINO_FIN","PAUSA_MOLINO","MANTENCION_MOLINO") ';
        if ($shiftSessionId > 0) {
            $eventsWhere .= ' AND shift_session_id = :shift_session_id';
            $eventsParams[':shift_session_id'] = $shiftSessionId;
        } else {
            $eventsWhere .= ' AND operator_name = :operator_name AND DATE(created_at) = CURDATE()';
            $eventsParams[':operator_name'] = $operatorName;
        }
        $evtStmt = $this->pdo->prepare('SELECT operation_code, operator_name, created_at, comments FROM waste_operations' . $eventsWhere . ' ORDER BY id ASC');
        $evtStmt->execute($eventsParams);
        $events = $evtStmt->fetchAll();
        $pauseStack = [];
        $maintenanceStack = [];

        foreach ($events as $ev) {
            $code = strtoupper(trim((string)($ev['operation_code'] ?? '')));
            $evtAt = (string)($ev['created_at'] ?? '');
            $comments = trim((string)($ev['comments'] ?? ''));
            $opLabel = trim((string)($ev['operator_name'] ?? $operatorName));

            if ($code === 'PAUSA_MOLINO_INICIO' || $code === 'PAUSA_MOLINO') {
                $pauseStack[] = [
                    'start_at' => $evtAt,
                    'comments' => $comments !== '' ? $comments : ('Operador: ' . ($opLabel !== '' ? $opLabel : $operatorName)),
                ];
                continue;
            }
            if ($code === 'MANTENCION_MOLINO_INICIO' || $code === 'MANTENCION_MOLINO') {
                $maintenanceStack[] = [
                    'start_at' => $evtAt,
                    'comments' => $comments !== '' ? $comments : ('Operador: ' . ($opLabel !== '' ? $opLabel : $operatorName)),
                ];
                continue;
            }

            if ($code === 'PAUSA_MOLINO_FIN') {
                $startRow = array_pop($pauseStack);
                if ($startRow !== null) {
                    $rows[] = [
                        'event' => 'Pausa',
                        'start_at' => $startRow['start_at'] !== '' ? $startRow['start_at'] : '-',
                        'end_at' => $evtAt !== '' ? $evtAt : '-',
                        'duration_label' => $this->formatSimpleElapsedLabel($startRow['start_at'], $evtAt),
                        'quantity_label' => '-',
                        'comments' => (string)$startRow['comments'],
                        'option_badge_type' => 'configured',
                        'option_label' => 'Terminado',
                    ];
                }
                continue;
            }
            if ($code === 'MANTENCION_MOLINO_FIN') {
                $startRow = array_pop($maintenanceStack);
                if ($startRow !== null) {
                    $rows[] = [
                        'event' => 'Mantención',
                        'start_at' => $startRow['start_at'] !== '' ? $startRow['start_at'] : '-',
                        'end_at' => $evtAt !== '' ? $evtAt : '-',
                        'duration_label' => $this->formatSimpleElapsedLabel($startRow['start_at'], $evtAt),
                        'quantity_label' => '-',
                        'comments' => (string)$startRow['comments'],
                        'option_badge_type' => 'configured',
                        'option_label' => 'Terminado',
                    ];
                }
                continue;
            }
        }

        foreach ($pauseStack as $openPause) {
            $rows[] = [
                'event' => 'Pausa',
                'start_at' => (string)($openPause['start_at'] ?? '-'),
                'end_at' => '-',
                'duration_label' => $this->formatSimpleElapsedLabel((string)($openPause['start_at'] ?? ''), null),
                'quantity_label' => '-',
                'comments' => (string)($openPause['comments'] ?? '-'),
                'option_badge_type' => 'finish-event',
                'option_label' => 'Terminar',
                'option_form_action' => '/waste/operations',
                'option_form_params' => ['operation_code' => 'PAUSA_MOLINO_FIN', 'comments' => ''],
            ];
        }
        foreach ($maintenanceStack as $openMaint) {
            $rows[] = [
                'event' => 'Mantención',
                'start_at' => (string)($openMaint['start_at'] ?? '-'),
                'end_at' => '-',
                'duration_label' => $this->formatSimpleElapsedLabel((string)($openMaint['start_at'] ?? ''), null),
                'quantity_label' => '-',
                'comments' => (string)($openMaint['comments'] ?? '-'),
                'option_badge_type' => 'finish-event',
                'option_label' => 'Terminar',
                'option_form_action' => '/waste/operations',
                'option_form_params' => ['operation_code' => 'MANTENCION_MOLINO_FIN', 'comments' => ''],
            ];
        }

        return $rows;
    }

    /**
     * @return array{id:int,material_code:string,entry_kg:string,operator_name:string,shift_session_id:int|null}|null
     */
    public function getActiveCompactadoraOperation(string $operatorName, ?int $shiftSessionId = null): ?array
    {
        $sql = 'SELECT id, material_code, entry_kg, operator_name, shift_session_id
                FROM waste_operations
                WHERE operation_code = "COMPACTADORA" AND exit_kg IS NULL AND entry_kg IS NOT NULL ';
        $params = [];
        if ($shiftSessionId > 0) {
            $sql .= ' AND shift_session_id = :shift_session_id';
            $params[':shift_session_id'] = $shiftSessionId;
        } else {
            $sql .= ' AND operator_name = :operator_name AND DATE(created_at) = CURDATE()';
            $params[':operator_name'] = trim($operatorName);
        }
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function recordCompactadoraEntry(string $materialCode, float $entryKg, string $operatorName, ?int $shiftSessionId = null): array
    {
        $errors = [];
        $materialCode = strtoupper(trim($materialCode));
        $entryKg = (float)$entryKg;
        $operatorName = trim($operatorName);

        if ($materialCode === '') {
            $errors[] = 'La materialidad es obligatoria.';
        }
        if ($entryKg <= 0) {
            $errors[] = 'El peso de ingreso debe ser mayor a 0.';
        }
        $stock = (float)($this->listWastePendingInventoryTotals()[$materialCode] ?? 0.0);
        if ($materialCode !== '' && $entryKg > $stock + 0.0001) {
            $errors[] = 'El peso de ingreso supera el stock disponible (' . number_format($stock, 3, '.', '') . ' kg).';
        }
        if ($operatorName === '') {
            $errors[] = 'El operador es obligatorio.';
        }
        if ($this->getActiveCompactadoraOperation($operatorName, $shiftSessionId) !== null) {
            $errors[] = 'Ya existe una compactadora en proceso; debe finalizarla antes de iniciar una nueva.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO waste_operations
                (shift_session_id, operation_code, material_code, entry_kg, operator_name, created_at)
            VALUES
                (:shift_session_id, "COMPACTADORA", :material_code, :entry_kg, :operator_name, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([
            ':shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            ':material_code' => $materialCode,
            ':entry_kg' => $entryKg,
            ':operator_name' => $operatorName,
        ]);
        $id = (int)$this->pdo->lastInsertId();

        $this->insertEvent('WASTE_COMPACTADORA_ENTRY_RECORDED', [
            'waste_operation_id' => $id,
            'shift_session_id' => $shiftSessionId > 0 ? $shiftSessionId : null,
            'material_code' => $materialCode,
            'entry_kg' => $entryKg,
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    /**
     * @return array{ok:bool, errors?:string[], id?:int}
     */
    public function finalizeCompactadoraOperation(int $opId, float $exitKg, string $operatorName): array
    {
        $errors = [];
        $opId = (int)$opId;
        $exitKg = (float)$exitKg;
        $operatorName = trim($operatorName);

        if ($opId <= 0) {
            $errors[] = 'Operación de compactadora inválida.';
        }
        if ($exitKg <= 0) {
            $errors[] = 'El peso de salida debe ser mayor a 0.';
        }
        if ($operatorName === '') {
            $errors[] = 'El operador es obligatorio.';
        }
        $row = null;
        if ($opId > 0) {
            $stmt = $this->pdo->prepare('SELECT id, operation_code, material_code, entry_kg, exit_kg, operator_name FROM waste_operations WHERE id = :id');
            $stmt->execute([':id' => $opId]);
            $row = $stmt->fetch();
            if ($row === false) {
                $errors[] = 'No se encontró la operación de compactadora.';
            } elseif (($row['operation_code'] ?? '') !== 'COMPACTADORA') {
                $errors[] = 'La operación seleccionada no corresponde a compactadora.';
            } elseif (($row['exit_kg'] ?? null) !== null) {
                $errors[] = 'Esta operación de compactadora ya fue finalizada.';
            }
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $stmt = $this->pdo->prepare('
            UPDATE waste_operations
            SET exit_kg = :exit_kg, pallet_count = NULL
            WHERE id = :id
        ');
        $stmt->execute([
            ':exit_kg' => $exitKg,
            ':id' => $opId,
        ]);

        $this->insertEvent('WASTE_COMPACTADORA_FINALIZED', [
            'waste_operation_id' => $opId,
            'material_code' => (string)($row['material_code'] ?? ''),
            'entry_kg' => (float)($row['entry_kg'] ?? 0.0),
            'exit_kg' => $exitKg,
            'operator_name' => $operatorName,
        ]);

        return ['ok' => true, 'id' => $opId, 'errors' => []];
    }

    /**
     * @return list<array{event:string,start_at:string,end_at:string,duration_label:string,quantity_label:string,comments:string,option_badge_type:string,option_label:string,option_href?:string,option_form_action?:string,option_form_params?:array<string,mixed>}>
     */
    public function listCompactadoraProductionEvents(string $operatorName, ?int $shiftSessionId = null): array
    {
        $operatorName = trim($operatorName);
        if ($operatorName === '' && !($shiftSessionId > 0)) {
            return [];
        }

        $params = [];
        $where = ' WHERE operation_code = "COMPACTADORA" AND exit_kg IS NULL AND entry_kg IS NOT NULL ';
        if ($shiftSessionId > 0) {
            $where .= ' AND shift_session_id = :shift_session_id';
            $params[':shift_session_id'] = $shiftSessionId;
        } else {
            $where .= ' AND operator_name = :operator_name AND DATE(created_at) = CURDATE()';
            $params[':operator_name'] = $operatorName;
        }

        $stmt = $this->pdo->prepare('
            SELECT id, material_code, entry_kg, operator_name, created_at
            FROM waste_operations
            ' . $where . '
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute($params);
        $op = $stmt->fetch();
        if ($op === false) {
            return [];
        }

        $startAt = (string)($op['created_at'] ?? '');
        $entryVal = (float)($op['entry_kg'] ?? 0.0);
        $materialCode = strtoupper(trim((string)($op['material_code'] ?? '')));
        $operatorLabel = trim((string)($op['operator_name'] ?? $operatorName));

        $rows = [];
        $rows[] = [
            'event' => 'Ingreso',
            'start_at' => $startAt !== '' ? $startAt : '-',
            'end_at' => '-',
            'duration_label' => '0h 0m',
            'quantity_label' => $materialCode !== '' ? ($materialCode . ' · ' . number_format($entryVal, 3, '.', '') . ' kg') : (number_format($entryVal, 3, '.', '') . ' kg'),
            'comments' => 'Ingreso Compactadora registrado · Operador: ' . ($operatorLabel !== '' ? $operatorLabel : $operatorName),
            'option_badge_type' => 'configured',
            'option_label' => 'Registrado',
        ];

        $rows[] = [
            'event' => 'Producción',
            'start_at' => $startAt !== '' ? $startAt : '-',
            'end_at' => '-',
            'duration_label' => '-',
            'quantity_label' => ($materialCode !== '' ? ($materialCode . ' · ') : '') . 'Entrada ' . number_format($entryVal, 3, '.', '') . ' kg',
            'comments' => 'Operador: ' . ($operatorLabel !== '' ? $operatorLabel : $operatorName) . ' · Producción en curso.',
            'option_badge_type' => 'finish-production',
            'option_label' => 'Terminar producción',
            'option_trigger' => 'compactadora-finalize',
        ];

        $eventsParams = [
            ':started_at' => $startAt,
        ];
        $eventsWhere = ' WHERE created_at >= :started_at AND operation_code IN ("PAUSA_COMPACTADORA_INICIO","PAUSA_COMPACTADORA_FIN","MANTENCION_COMPACTADORA_INICIO","MANTENCION_COMPACTADORA_FIN","PAUSA_COMPACTADORA","MANTENCION_COMPACTADORA") ';
        if ($shiftSessionId > 0) {
            $eventsWhere .= ' AND shift_session_id = :shift_session_id';
            $eventsParams[':shift_session_id'] = $shiftSessionId;
        } else {
            $eventsWhere .= ' AND operator_name = :operator_name AND DATE(created_at) = CURDATE()';
            $eventsParams[':operator_name'] = $operatorName;
        }
        $evtStmt = $this->pdo->prepare('SELECT operation_code, operator_name, created_at, comments FROM waste_operations' . $eventsWhere . ' ORDER BY id ASC');
        $evtStmt->execute($eventsParams);
        $events = $evtStmt->fetchAll();

        $pauseStack = [];
        $maintenanceStack = [];
        foreach ($events as $ev) {
            $code = strtoupper(trim((string)($ev['operation_code'] ?? '')));
            $evtAt = (string)($ev['created_at'] ?? '');
            $comments = trim((string)($ev['comments'] ?? ''));
            $opLabel = trim((string)($ev['operator_name'] ?? $operatorName));

            if ($code === 'PAUSA_COMPACTADORA_INICIO' || $code === 'PAUSA_COMPACTADORA') {
                $pauseStack[] = [
                    'start_at' => $evtAt,
                    'comments' => $comments !== '' ? $comments : ('Operador: ' . ($opLabel !== '' ? $opLabel : $operatorName)),
                ];
                continue;
            }
            if ($code === 'MANTENCION_COMPACTADORA_INICIO' || $code === 'MANTENCION_COMPACTADORA') {
                $maintenanceStack[] = [
                    'start_at' => $evtAt,
                    'comments' => $comments !== '' ? $comments : ('Operador: ' . ($opLabel !== '' ? $opLabel : $operatorName)),
                ];
                continue;
            }
            if ($code === 'PAUSA_COMPACTADORA_FIN') {
                $startRow = array_pop($pauseStack);
                if ($startRow !== null) {
                    $rows[] = [
                        'event' => 'Pausa',
                        'start_at' => $startRow['start_at'] !== '' ? $startRow['start_at'] : '-',
                        'end_at' => $evtAt !== '' ? $evtAt : '-',
                        'duration_label' => $this->formatSimpleElapsedLabel($startRow['start_at'], $evtAt),
                        'quantity_label' => '-',
                        'comments' => (string)$startRow['comments'],
                        'option_badge_type' => 'configured',
                        'option_label' => 'Terminado',
                    ];
                }
                continue;
            }
            if ($code === 'MANTENCION_COMPACTADORA_FIN') {
                $startRow = array_pop($maintenanceStack);
                if ($startRow !== null) {
                    $rows[] = [
                        'event' => 'Mantención',
                        'start_at' => $startRow['start_at'] !== '' ? $startRow['start_at'] : '-',
                        'end_at' => $evtAt !== '' ? $evtAt : '-',
                        'duration_label' => $this->formatSimpleElapsedLabel($startRow['start_at'], $evtAt),
                        'quantity_label' => '-',
                        'comments' => (string)$startRow['comments'],
                        'option_badge_type' => 'configured',
                        'option_label' => 'Terminado',
                    ];
                }
                continue;
            }
        }

        foreach ($pauseStack as $openPause) {
            $rows[] = [
                'event' => 'Pausa',
                'start_at' => (string)($openPause['start_at'] ?? '-'),
                'end_at' => '-',
                'duration_label' => $this->formatSimpleElapsedLabel((string)($openPause['start_at'] ?? ''), null),
                'quantity_label' => '-',
                'comments' => (string)($openPause['comments'] ?? '-'),
                'option_badge_type' => 'finish-event',
                'option_label' => 'Terminar',
                'option_form_action' => '/waste/operations',
                'option_form_params' => ['operation_code' => 'PAUSA_COMPACTADORA_FIN', 'comments' => ''],
            ];
        }
        foreach ($maintenanceStack as $openMaint) {
            $rows[] = [
                'event' => 'Mantención',
                'start_at' => (string)($openMaint['start_at'] ?? '-'),
                'end_at' => '-',
                'duration_label' => $this->formatSimpleElapsedLabel((string)($openMaint['start_at'] ?? ''), null),
                'quantity_label' => '-',
                'comments' => (string)($openMaint['comments'] ?? '-'),
                'option_badge_type' => 'finish-event',
                'option_label' => 'Terminar',
                'option_form_action' => '/waste/operations',
                'option_form_params' => ['operation_code' => 'MANTENCION_COMPACTADORA_FIN', 'comments' => ''],
            ];
        }

        return $rows;
    }

    private function formatSimpleElapsedLabel(?string $startedAt, ?string $endedAt = null): string
    {
        $startedAt = trim((string)$startedAt);
        $endedAt = $endedAt !== null ? trim((string)$endedAt) : '';
        $startedTs = $startedAt !== '' ? strtotime($startedAt) : false;
        if ($startedTs === false) {
            return '0h 0m';
        }
        $endedTs = $endedAt !== '' ? strtotime($endedAt) : time();
        if ($endedTs === false || $endedTs < $startedTs) {
            $endedTs = $startedTs;
        }
        $diffSeconds = max(0, $endedTs - $startedTs);
        $hours = intdiv($diffSeconds, 3600);
        $minutes = intdiv($diffSeconds % 3600, 60);
        return $hours . 'h ' . $minutes . 'm';
    }

}

class WasteService
{
    use WasteServiceTrait;

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
