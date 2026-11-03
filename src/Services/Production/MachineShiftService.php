<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Datos · Sesiones de Máquinas, Turnos, Colaciones y Standby
// (src/Services/Production/MachineShiftService.php)
// =============================================================================

trait MachineShiftReceptionTrait
{
    public function listProductionMachinesWithSessions(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pm.id, pm.code, pm.name, pm.production_area, pm.erp_machine_id, pm.plant_label, pm.sort_order,
                    pmt.name AS machine_type_name, pmt.code AS machine_type_code, pmt.production_area AS machine_type_area,
                    pss.id AS active_session_id, pss.work_order_id AS active_work_order_id, pss.operator_name, pss.helper_name,
                    pss.shift_label, pss.process_stage, pss.comments, pss.started_at, pss.ended_at, pss.status AS session_status,
                    wo.ot_code AS active_work_order_code, wo.sku_final AS active_work_order_sku
             FROM production_machines pm
             INNER JOIN production_machine_types pmt ON pmt.id = pm.machine_type_id
             LEFT JOIN production_shift_sessions pss
               ON pss.machine_id = pm.id
              AND pss.status = "ACTIVE"
              AND pss.id = (
                    SELECT MAX(pss2.id)
                    FROM production_shift_sessions pss2
                    WHERE pss2.machine_id = pm.id
                      AND pss2.status = "ACTIVE"
               )
             LEFT JOIN work_orders wo ON wo.id = pss.work_order_id
             WHERE pm.is_active = 1
             ORDER BY pmt.display_order ASC, pm.sort_order ASC, pm.id ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listProductionMachineTypes(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, code, name, production_area, erp_machine_type_id, display_order
             FROM production_machine_types
             WHERE is_active = 1
             ORDER BY display_order ASC, name ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getProductionMachine(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pm.*, pmt.name AS machine_type_name, pmt.code AS machine_type_code, pmt.production_area AS machine_type_area
             FROM production_machines pm
             INNER JOIN production_machine_types pmt ON pmt.id = pm.machine_type_id
             WHERE pm.id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listProductionPersonnelNames(): array
    {
        $names = [];

        try {
            $stmt = $this->pdo->query(
                'SELECT DISTINCT display_name
                 FROM auth_users
                 WHERE is_active = 1
                   AND (can_operator = 1 OR can_production = 1)
                   AND TRIM(COALESCE(display_name, "")) <> ""
                 ORDER BY display_name ASC'
            );
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
                $name = trim((string)$value);
                if ($name !== '') {
                    $names[$name] = true;
                }
            }
        } catch (Throwable) {
            // Si auth_users aún no existe, seguimos con nombres históricos.
        }

        $stmt = $this->pdo->query(
            'SELECT DISTINCT operator_name AS person_name
             FROM production_shift_sessions
             WHERE TRIM(COALESCE(operator_name, "")) <> ""
             UNION
             SELECT DISTINCT helper_name AS person_name
             FROM production_shift_sessions
             WHERE TRIM(COALESCE(helper_name, "")) <> ""
             ORDER BY person_name ASC'
        );
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
            $name = trim((string)$value);
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        $result = array_values(array_keys($names));
        natcasesort($result);
        return array_values($result);
    }

    public function listErpWorkerNames(): array
    {
        if (!$this->erpTableExists('workers')) {
            return [];
        }

        $firstCol = null;
        if ($this->erpColumnExists('workers', 'wrk_firstname')) {
            $firstCol = 'wrk_firstname';
        } elseif ($this->erpColumnExists('workers', 'firstname')) {
            $firstCol = 'firstname';
        } elseif ($this->erpColumnExists('workers', 'first_name')) {
            $firstCol = 'first_name';
        }

        $lastCol = null;
        if ($this->erpColumnExists('workers', 'wrk_lastname')) {
            $lastCol = 'wrk_lastname';
        } elseif ($this->erpColumnExists('workers', 'lastname')) {
            $lastCol = 'lastname';
        } elseif ($this->erpColumnExists('workers', 'last_name')) {
            $lastCol = 'last_name';
        }

        $nameCol = null;
        if ($this->erpColumnExists('workers', 'name')) {
            $nameCol = 'name';
        }

        $sql = '';
        if ($firstCol !== null || $lastCol !== null) {
            $sql = 'SELECT DISTINCT TRIM(CONCAT(COALESCE('
                . ($firstCol !== null ? $firstCol : '""')
                . ', ""), " ", COALESCE('
                . ($lastCol !== null ? $lastCol : '""')
                . ', ""))) AS display_name
                FROM workers
                WHERE TRIM(CONCAT(COALESCE('
                . ($firstCol !== null ? $firstCol : '""')
                . ', ""), " ", COALESCE('
                . ($lastCol !== null ? $lastCol : '""')
                . ', ""))) <> ""
                ORDER BY display_name ASC';
        } elseif ($nameCol !== null) {
            $sql = 'SELECT DISTINCT TRIM(COALESCE(' . $nameCol . ', "")) AS display_name
                    FROM workers
                    WHERE TRIM(COALESCE(' . $nameCol . ', "")) <> ""
                    ORDER BY display_name ASC';
        } else {
            return [];
        }

        try {
            $stmt = $this->erpPdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $names = [];
            foreach (is_array($rows) ? $rows : [] as $v) {
                $name = trim((string)$v);
                if ($name !== '') {
                    $names[] = $name;
                }
            }
            $names = array_values(array_unique($names));
            sort($names, SORT_NATURAL | SORT_FLAG_CASE);
            return $names;
        } catch (Throwable) {
            return [];
        }
    }

    public function getActiveShiftSessionByOperator(string $operatorName): ?array
    {
        $operatorName = trim($operatorName);
        if ($operatorName === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT pss.*, pm.name AS machine_name, pm.code AS machine_code, pm.production_area AS machine_area,
                    pm.erp_machine_id, pmt.name AS machine_type_name, pmt.code AS machine_type_code,
                    pmt.erp_machine_type_id, wo.ot_code, wo.sku_final
             FROM production_shift_sessions pss
             INNER JOIN production_machines pm ON pm.id = pss.machine_id
             INNER JOIN production_machine_types pmt ON pmt.id = pm.machine_type_id
             LEFT JOIN work_orders wo ON wo.id = pss.work_order_id
             WHERE pss.operator_name = :operator_name
               AND pss.status = "ACTIVE"
             ORDER BY pss.id DESC
             LIMIT 1'
        );
        $stmt->execute([':operator_name' => $operatorName]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getActiveShiftSessionByWorkOrder(int $workOrderId): ?array
    {
        if ($workOrderId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT pss.*, pm.name AS machine_name, pm.code AS machine_code, pm.production_area AS machine_area,
                    pm.erp_machine_id, pmt.name AS machine_type_name, pmt.code AS machine_type_code,
                    pmt.erp_machine_type_id
             FROM production_shift_sessions pss
             INNER JOIN production_machines pm ON pm.id = pss.machine_id
             INNER JOIN production_machine_types pmt ON pmt.id = pm.machine_type_id
             WHERE pss.work_order_id = :work_order_id
               AND pss.status = "ACTIVE"
             ORDER BY pss.id DESC
             LIMIT 1'
        );
        $stmt->execute([':work_order_id' => $workOrderId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function startShiftSession(
        int $machineId,
        string $operatorName,
        ?string $helperName = null,
        ?string $shiftLabel = null,
        ?string $processStage = null,
        ?string $comments = null
    ): array {
        $operatorName = trim($operatorName);
        $helperName = trim((string)$helperName);
        $shiftLabel = trim((string)$shiftLabel);
        $comments = trim((string)$comments);
        $machine = $this->getProductionMachine($machineId);

        $errors = [];
        if ($machineId <= 0 || $machine === null) {
            $errors['machine_id'] = 'La máquina seleccionada no existe.';
        }
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }
        if ($machine !== null) {
            $currentMachineSession = $this->getActiveShiftSessionByMachine($machineId);
            if ($currentMachineSession !== null) {
                $errors['machine_id'] = 'La máquina ya tiene un turno activo.';
            }
            $currentOperatorSession = $this->getActiveShiftSessionByOperator($operatorName);
            if ($currentOperatorSession !== null) {
                $errors['operator_name'] = 'El operador ya tiene un turno activo en otra máquina.';
            }
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $resolvedStage = $this->normalizeMachineProcessStage(
            $processStage !== null && trim($processStage) !== '' ? $processStage : (string)($machine['production_area'] ?? '')
        );
        $resolvedShiftLabel = $shiftLabel !== '' ? $shiftLabel : 'Turno general';

        $stmt = $this->pdo->prepare(
            'INSERT INTO production_shift_sessions (
                machine_id, work_order_id, operator_name, helper_name, shift_label, process_stage, comments, started_at, ended_at, status
             ) VALUES (
                :machine_id, NULL, :operator_name, :helper_name, :shift_label, :process_stage, :comments, CURRENT_TIMESTAMP, NULL, "ACTIVE"
             )'
        );
        $stmt->execute([
            ':machine_id' => $machineId,
            ':operator_name' => $operatorName,
            ':helper_name' => $helperName !== '' ? $helperName : null,
            ':shift_label' => $resolvedShiftLabel,
            ':process_stage' => $resolvedStage,
            ':comments' => $comments !== '' ? $comments : null,
        ]);

        $sessionId = (int)$this->pdo->lastInsertId();
        $this->insertEvent('SHIFT_SESSION_STARTED', [
            'shift_session_id' => $sessionId,
            'machine_id' => $machineId,
            'machine_name' => (string)($machine['name'] ?? ''),
            'machine_type' => (string)($machine['machine_type_name'] ?? ''),
            'operator_name' => $operatorName,
            'helper_name' => $helperName !== '' ? $helperName : null,
            'shift_label' => $resolvedShiftLabel,
            'process_stage' => $resolvedStage,
            'comments' => $comments !== '' ? $comments : null,
        ]);

        return ['ok' => true, 'errors' => [], 'id' => $sessionId];
    }

    public function endShiftSession(int $sessionId, string $operatorName, ?string $comments = null): array
    {
        $operatorName = trim($operatorName);
        $comments = trim((string)$comments);
        $session = $this->getShiftSession($sessionId);
        $errors = [];
        if ($sessionId <= 0 || $session === null) {
            $errors['session_id'] = 'El turno no existe.';
        } elseif ((string)($session['status'] ?? '') !== 'ACTIVE') {
            $errors['session_id'] = 'El turno ya está cerrado.';
        } elseif ($operatorName !== '' && trim((string)($session['operator_name'] ?? '')) !== $operatorName) {
            $errors['operator_name'] = 'Solo el operador activo puede cerrar este turno.';
        }
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $existingComments = trim((string)($session['comments'] ?? ''));
        $resolvedComments = $existingComments;
        if ($comments !== '') {
            $resolvedComments = $existingComments !== ''
                ? $existingComments . "\n" . $comments
                : $comments;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE production_shift_sessions
             SET status = "CLOSED",
                 ended_at = CURRENT_TIMESTAMP,
                 comments = :comments
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $sessionId,
            ':comments' => $resolvedComments !== '' ? $resolvedComments : null,
        ]);

        $this->insertEvent('SHIFT_SESSION_ENDED', [
            'shift_session_id' => $sessionId,
            'machine_id' => (int)($session['machine_id'] ?? 0),
            'machine_name' => (string)($session['machine_name'] ?? ''),
            'operator_name' => (string)($session['operator_name'] ?? ''),
            'work_order_id' => (int)($session['work_order_id'] ?? 0) > 0 ? (int)$session['work_order_id'] : null,
            'comments' => $comments !== '' ? $comments : null,
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function updateShiftSessionHeader(int $sessionId, ?string $helperName = null, ?string $comments = null): array
    {
        $session = $this->getShiftSession($sessionId);
        if ($session === null) {
            return ['ok' => false, 'errors' => ['session_id' => 'El turno no existe.']];
        }
        if ((string)($session['status'] ?? '') !== 'ACTIVE') {
            return ['ok' => false, 'errors' => ['session_id' => 'Solo se puede editar un turno activo.']];
        }

        $helperName = trim((string)$helperName);
        $comments = trim((string)$comments);

        $stmt = $this->pdo->prepare(
            'UPDATE production_shift_sessions
             SET helper_name = :helper_name,
                 comments = :comments
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $sessionId,
            ':helper_name' => $helperName !== '' ? $helperName : null,
            ':comments' => $comments !== '' ? $comments : null,
        ]);

        $this->insertEvent('SHIFT_SESSION_HEADER_UPDATED', [
            'shift_session_id' => $sessionId,
            'machine_id' => (int)($session['machine_id'] ?? 0),
            'machine_name' => (string)($session['machine_name'] ?? ''),
            'work_order_id' => (int)($session['work_order_id'] ?? 0) > 0 ? (int)$session['work_order_id'] : null,
            'operator_name' => (string)($session['operator_name'] ?? ''),
            'helper_name' => $helperName !== '' ? $helperName : null,
            'comments' => $comments !== '' ? $comments : null,
        ]);

        return ['ok' => true, 'errors' => []];
    }

    public function assignActiveShiftSessionToWorkOrder(int $workOrderId, string $operatorName): void
    {
        $session = $this->getActiveShiftSessionByOperator($operatorName);
        if ($session === null) {
            return;
        }
        if ((int)($session['work_order_id'] ?? 0) === $workOrderId) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE production_shift_sessions
             SET work_order_id = :work_order_id
             WHERE id = :id'
        );
        $stmt->execute([
            ':work_order_id' => $workOrderId,
            ':id' => (int)$session['id'],
        ]);

        $this->insertEvent('SHIFT_SESSION_WORK_ORDER_ASSIGNED', [
            'shift_session_id' => (int)$session['id'],
            'machine_id' => (int)($session['machine_id'] ?? 0),
            'work_order_id' => $workOrderId,
            'operator_name' => trim((string)($session['operator_name'] ?? '')),
        ]);
    }

    public function releaseActiveShiftSessionFromWorkOrder(int $workOrderId, string $operatorName): void
    {
        $session = $this->getActiveShiftSessionByOperator($operatorName);
        if ($session === null || (int)($session['work_order_id'] ?? 0) !== $workOrderId) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE production_shift_sessions
             SET work_order_id = NULL
             WHERE id = :id'
        );
        $stmt->execute([':id' => (int)$session['id']]);

        $this->insertEvent('SHIFT_SESSION_WORK_ORDER_RELEASED', [
            'shift_session_id' => (int)$session['id'],
            'machine_id' => (int)($session['machine_id'] ?? 0),
            'work_order_id' => $workOrderId,
            'operator_name' => trim((string)($session['operator_name'] ?? '')),
        ]);
    }


    public function ensureOperatorLunchBreaksTable(): void
    {
        try {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS production_operator_lunch_breaks (
                  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  shift_date DATE NOT NULL,
                  init_id BIGINT NULL,
                  worker_id BIGINT NULL,
                  operator_name VARCHAR(150) NOT NULL,
                  machine_id INT UNSIGNED NULL,
                  machine_name VARCHAR(120) NULL,
                  start_time DATETIME NOT NULL,
                  end_time DATETIME NULL,
                  duration_minutes INT UNSIGNED NULL,
                  erp_event_id BIGINT NULL,
                  comments TEXT NULL,
                  created_by VARCHAR(100) NULL,
                  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (id),
                  KEY idx_lunch_date (shift_date),
                  KEY idx_lunch_worker (worker_id),
                  KEY idx_lunch_init (init_id),
                  KEY idx_lunch_machine (machine_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable) {
            // Ignorar si la tabla ya existe o faltan permisos de DDL
        }
    }

    /**
     * Obtiene el reporte consolidado de turnos y colaciones para una fecha específica.
     * Lista a TODOS los operarios que iniciaron turno (hayan ido o no a colación).
     *
     * @param string $date Formato 'YYYY-MM-DD'
     * @return array<string, mixed>
     */
    public function getOperatorLunchReport(string $date = ''): array
    {
        $this->ensureOperatorLunchBreaksTable();

        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        list($year, $month, $day) = explode('-', $date);

        // 1. Obtener todas las máquinas activas de producción (excluyendo embalaje, supervisión, mantención, bodega, chofer, aseo)
        $allMachines = [];
        try {
            $stmtEq = $this->erpPdo->query("
                SELECT id, equipo_name 
                FROM equipo 
                WHERE equipo_status = 1 
                  AND equipo_type_id NOT IN (15, 16, 17, 18, 19, 20)
                  AND equipo_name NOT LIKE '%EMBALAJE%'
                  AND equipo_name NOT LIKE '%SUPERVISOR%' 
                  AND equipo_name NOT LIKE '%MANTENCION%'
                  AND equipo_name NOT LIKE '%BODEGA%'
                  AND equipo_name NOT LIKE '%CHOFER%'
                  AND equipo_name NOT LIKE '%ASEO%'
                ORDER BY equipo_type_id ASC, equipo_name ASC
            ");
            foreach ($stmtEq->fetchAll(PDO::FETCH_ASSOC) as $eqRow) {
                $mName = trim((string)$eqRow['equipo_name']);
                if ($mName !== '') {
                    $allMachines[(int)$eqRow['id']] = $mName;
                }
            }
        } catch (Throwable) {}

        // 2. Obtener registros guardados localmente en TRZ
        $savedBreaks = [];
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM production_operator_lunch_breaks WHERE shift_date = :d");
            $stmt->execute([':d' => $date]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $sb) {
                if (!empty($sb['init_id']) && !empty($sb['worker_id'])) {
                    $savedBreaks['init_' . $sb['init_id'] . '_' . $sb['worker_id']] = $sb;
                }
                if (!empty($sb['worker_id'])) {
                    $savedBreaks['wrk_' . $sb['worker_id']] = $sb;
                }
                if (!empty($sb['operator_name'])) {
                    $savedBreaks['name_' . mb_strtolower(trim((string)$sb['operator_name']))] = $sb;
                }
            }
        } catch (Throwable) {}

        $dayStartTs = strtotime($date . ' 00:00:00');
        $dayEndTs = strtotime($date . ' 23:59:59');

        // Identificar operarios que operaron como ayudantes hoy en órdenes de trabajo
        $ayudanteWorkerIds = [];
        try {
            $stmtAyu = $this->erpPdo->prepare("
                SELECT DISTINCT evt_idayudante 
                FROM prod_worker_ot_events 
                WHERE evt_idayudante IS NOT NULL AND evt_idayudante > 0 
                  AND evt_crtdat BETWEEN :s AND :e
            ");
            $stmtAyu->execute([':s' => $dayStartTs, ':e' => $dayEndTs]);
            $ayudanteWorkerIds = array_map('intval', $stmtAyu->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Throwable) {}

        // 3. Consultar programación de turnos en ERP (turnos_config_assign) para enriquecer horarios planificados
        $scheduledMap = [];
        try {
            $sqlAssign = "
                SELECT a.assign_worker_id, a.ass_init_hour, a.ass_init_min, a.ass_end_hour, a.ass_end_min
                FROM turnos_config_assign a
                WHERE a.assign_year = :year AND a.assign_month = :month AND a.assign_day = :day
                  AND (a.confirm_falta_act IS NULL OR a.confirm_falta_act = 0)
            ";
            $stmtAssign = $this->erpPdo->prepare($sqlAssign);
            $stmtAssign->execute([':year' => (int)$year, ':month' => (int)$month, ':day' => (int)$day]);
            foreach ($stmtAssign->fetchAll(PDO::FETCH_ASSOC) as $ar) {
                $scheduledMap[(int)$ar['assign_worker_id']] = [
                    'start' => ($ar['ass_init_hour'] !== null) ? sprintf('%02d:%02d', (int)$ar['ass_init_hour'], (int)$ar['ass_init_min']) : '07:10',
                    'end' => ($ar['ass_end_hour'] !== null) ? sprintf('%02d:%02d', (int)$ar['ass_end_hour'], (int)$ar['ass_end_min']) : '15:40',
                ];
            }
        } catch (Throwable) {}

        $operators = [];

        // 4. Consultar turnos físicos iniciados en ERP (prod_worker_init)
        try {
            $sqlInit = "
                SELECT 
                    wi.id AS init_id,
                    wi.win_crtdat,
                    wi.win_enddat,
                    wi.win_status,
                    w.id AS worker_id,
                    TRIM(CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, ''))) AS operator_name,
                    eq.id AS machine_id,
                    COALESCE(eq.equipo_name, 'Sin Máquina') AS machine_name,
                    w.wrk_cargoid,
                    wt.type_name AS cargo_name,
                    ot.id AS ot_id,
                    ev.id AS colacion_evt_id,
                    ev.evt_crtdat AS colacion_inicio_ts,
                    ev.evt_enddat AS colacion_fin_ts,
                    ev.evt_status AS colacion_status
                FROM prod_worker_init wi
                INNER JOIN workers w ON wi.win_wrkid = w.id
                LEFT JOIN workers_types wt ON w.wrk_cargoid = wt.id
                INNER JOIN equipo eq ON wi.win_equipoid = eq.id
                LEFT JOIN prod_worker_ot ot ON ot.wok_init_id = wi.id
                LEFT JOIN prod_worker_ot_events ev ON ev.evt_prod_worker_otid = ot.id 
                     AND ev.evt_pause_id = 1 AND ev.evt_type = 'pause' AND ev.evt_status > 0
                     AND ev.evt_crtdat BETWEEN :start_ts AND :end_ts
                WHERE (
                    (wi.win_day = :day AND wi.win_month = :month AND wi.win_year = :year)
                    OR (wi.win_status = 1 AND wi.win_crtdat >= (:start_ts - 86400) AND wi.win_crtdat <= :end_ts)
                  )
                  AND eq.equipo_type_id NOT IN (15, 16, 17, 18, 19, 20)
                  AND eq.equipo_name NOT LIKE '%EMBALAJE%'
                  AND eq.equipo_name NOT LIKE '%SUPERVISOR%' 
                  AND eq.equipo_name NOT LIKE '%MANTENCION%'
                  AND eq.equipo_name NOT LIKE '%BODEGA%'
                  AND eq.equipo_name NOT LIKE '%CHOFER%'
                  AND eq.equipo_name NOT LIKE '%ASEO%'
                  AND (w.wrk_cargoid IS NULL OR w.wrk_cargoid NOT IN (3, 4, 6, 9, 10, 14))
                  AND (wt.type_name IS NULL OR (
                      wt.type_name NOT LIKE '%Supervisor%' 
                      AND wt.type_name NOT LIKE '%Mantenci%' 
                      AND wt.type_name NOT LIKE '%Mantenim%'
                      AND wt.type_name NOT LIKE '%Embalad%'
                      AND wt.type_name NOT LIKE '%Ayudante%'
                  ))
                  AND wi.win_wrkid NOT IN (27, 68)
                ORDER BY wi.id ASC, ev.id DESC
            ";
            $stmt = $this->erpPdo->prepare($sqlInit);
            $stmt->execute([
                ':day' => (int)$day,
                ':month' => (int)$month,
                ':year' => (int)$year,
                ':start_ts' => $dayStartTs,
                ':end_ts' => $dayEndTs
            ]);
            $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rawRows as $r) {
                $wId = (int)$r['worker_id'];
                $mId = (int)$r['machine_id'];
                $mName = trim((string)$r['machine_name']) ?: ($allMachines[$mId] ?? 'Sin Máquina');
                $opName = trim((string)$r['operator_name']) ?: ('Operario #' . $wId);

                $key = 'W_' . $wId;
                $shiftStart = !empty($scheduledMap[$wId]['start']) ? $scheduledMap[$wId]['start'] : (!empty($r['win_crtdat']) ? date('H:i', (int)$r['win_crtdat']) : '07:10');
                $shiftEnd = !empty($scheduledMap[$wId]['end']) ? $scheduledMap[$wId]['end'] : ((!empty($r['win_enddat']) && (int)$r['win_enddat'] > 0) ? date('H:i', (int)$r['win_enddat']) : 'En curso');
                $shiftStatus = (int)$r['win_status'] === 1 ? 'ACTIVO' : 'CERRADO';

                if (!isset($operators[$key])) {
                    $operators[$key] = [
                        'key' => $key,
                        'source' => 'ERP_INIT',
                        'init_id' => (int)$r['init_id'],
                        'worker_id' => $wId,
                        'operator_name' => $opName,
                        'machine_id' => $mId,
                        'machine_name' => $mName,
                        'cargo_name' => (string)($r['cargo_name'] ?? ''),
                        'shift_start' => $shiftStart,
                        'shift_end' => $shiftEnd,
                        'shift_status' => $shiftStatus,
                        'has_lunch' => false,
                        'lunch_event_id' => null,
                        'lunch_start' => '',
                        'lunch_end' => '',
                        'duration_minutes' => 0,
                        'lunch_status' => 'PENDIENTE',
                        'comments' => '',
                        'latest_ot_id' => (int)($r['ot_id'] ?? 0) > 0 ? (int)$r['ot_id'] : null,
                    ];
                } else {
                    $operators[$key]['init_id'] = (int)$r['init_id'];
                    $operators[$key]['shift_start'] = $shiftStart;
                    $operators[$key]['shift_end'] = $shiftEnd;
                    $operators[$key]['shift_status'] = $shiftStatus;
                    if ((int)($r['ot_id'] ?? 0) > 0 && empty($operators[$key]['latest_ot_id'])) {
                        $operators[$key]['latest_ot_id'] = (int)$r['ot_id'];
                    }
                }

                if (!empty($r['colacion_evt_id']) && !$operators[$key]['has_lunch']) {
                    $operators[$key]['has_lunch'] = true;
                    $operators[$key]['lunch_event_id'] = (int)$r['colacion_evt_id'];
                    $operators[$key]['lunch_start'] = date('H:i', (int)$r['colacion_inicio_ts']);
                    $operators[$key]['lunch_end'] = !empty($r['colacion_fin_ts']) ? date('H:i', (int)$r['colacion_fin_ts']) : '';
                    if (!empty($r['colacion_fin_ts']) && $r['colacion_fin_ts'] > $r['colacion_inicio_ts']) {
                        $operators[$key]['duration_minutes'] = (int)round(($r['colacion_fin_ts'] - $r['colacion_inicio_ts']) / 60);
                        $operators[$key]['lunch_status'] = 'COMPLETADA';
                    } else {
                        $operators[$key]['lunch_status'] = 'EN_CURSO';
                    }
                }
            }
        } catch (Throwable) {}

        // 4b. Vincular directamente TODOS los eventos de colación registrados en prod_worker_ot_events para esta fecha específica en máquinas de producción
        try {
            $sqlEvents = "
                SELECT 
                    ev.id AS colacion_evt_id,
                    ev.evt_prod_worker_otid AS ot_id,
                    ev.evt_crtdat AS colacion_inicio_ts,
                    ev.evt_enddat AS colacion_fin_ts,
                    ev.evt_status AS colacion_status,
                    ev.evt_comments AS colacion_comments,
                    wi.id AS init_id,
                    wi.win_wrkid AS worker_id,
                    wi.win_equipoid AS machine_id,
                    COALESCE(eq.equipo_name, 'Sin Máquina') AS machine_name,
                    TRIM(CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, ''))) AS operator_name,
                    wt.type_name AS cargo_name
                FROM prod_worker_ot_events ev
                INNER JOIN prod_worker_ot ot ON ev.evt_prod_worker_otid = ot.id
                INNER JOIN prod_worker_init wi ON ot.wok_init_id = wi.id
                LEFT JOIN workers w ON wi.win_wrkid = w.id
                LEFT JOIN workers_types wt ON w.wrk_cargoid = wt.id
                LEFT JOIN equipo eq ON wi.win_equipoid = eq.id
                WHERE ev.evt_type = 'pause'
                  AND ev.evt_pause_id = 1
                  AND ev.evt_status > 0
                  AND ev.evt_crtdat BETWEEN :start_ts AND :end_ts
                  AND eq.equipo_type_id NOT IN (15, 16, 17, 18, 19, 20)
                  AND eq.equipo_name NOT LIKE '%EMBALAJE%'
                  AND eq.equipo_name NOT LIKE '%SUPERVISOR%'
                  AND eq.equipo_name NOT LIKE '%MANTENCION%'
                  AND (w.wrk_cargoid IS NULL OR w.wrk_cargoid NOT IN (3, 4, 6, 9, 10, 14))
                  AND (wt.type_name IS NULL OR wt.type_name NOT LIKE '%Embalad%')
                ORDER BY ev.id ASC
            ";
            $stmtEvts = $this->erpPdo->prepare($sqlEvents);
            $stmtEvts->execute([':start_ts' => $dayStartTs, ':end_ts' => $dayEndTs]);
            foreach ($stmtEvts->fetchAll(PDO::FETCH_ASSOC) as $eRow) {
                $wId = (int)$eRow['worker_id'];
                $key = 'W_' . $wId;
                if (!isset($operators[$key])) {
                    $mId = (int)$eRow['machine_id'];
                    $mName = trim((string)$eRow['machine_name']) ?: ($allMachines[$mId] ?? 'Sin Máquina');
                    $opName = trim((string)$eRow['operator_name']) ?: ('Operario #' . $wId);
                    $operators[$key] = [
                        'key' => $key,
                        'source' => 'ERP_EVENT',
                        'init_id' => (int)$eRow['init_id'],
                        'worker_id' => $wId,
                        'operator_name' => $opName,
                        'machine_id' => $mId,
                        'machine_name' => $mName,
                        'cargo_name' => (string)($eRow['cargo_name'] ?? ''),
                        'shift_start' => !empty($scheduledMap[$wId]['start']) ? $scheduledMap[$wId]['start'] : '07:10',
                        'shift_end' => !empty($scheduledMap[$wId]['end']) ? $scheduledMap[$wId]['end'] : 'En curso',
                        'shift_status' => 'ACTIVO',
                        'has_lunch' => true,
                        'lunch_event_id' => (int)$eRow['colacion_evt_id'],
                        'lunch_start' => date('H:i', (int)$eRow['colacion_inicio_ts']),
                        'lunch_end' => !empty($eRow['colacion_fin_ts']) ? date('H:i', (int)$eRow['colacion_fin_ts']) : '',
                        'duration_minutes' => (!empty($eRow['colacion_fin_ts']) && $eRow['colacion_fin_ts'] > $eRow['colacion_inicio_ts'])
                            ? (int)round(($eRow['colacion_fin_ts'] - $eRow['colacion_inicio_ts']) / 60)
                            : 0,
                        'lunch_status' => !empty($eRow['colacion_fin_ts']) ? 'COMPLETADA' : 'EN_CURSO',
                        'comments' => (string)($eRow['colacion_comments'] ?? ''),
                        'latest_ot_id' => (int)$eRow['ot_id'],
                    ];
                } else {
                    $operators[$key]['has_lunch'] = true;
                    $operators[$key]['lunch_event_id'] = (int)$eRow['colacion_evt_id'];
                    $operators[$key]['lunch_start'] = date('H:i', (int)$eRow['colacion_inicio_ts']);
                    $operators[$key]['lunch_end'] = !empty($eRow['colacion_fin_ts']) ? date('H:i', (int)$eRow['colacion_fin_ts']) : '';
                    if (!empty($eRow['colacion_fin_ts']) && $eRow['colacion_fin_ts'] > $eRow['colacion_inicio_ts']) {
                        $operators[$key]['duration_minutes'] = (int)round(($eRow['colacion_fin_ts'] - $eRow['colacion_inicio_ts']) / 60);
                        $operators[$key]['lunch_status'] = 'COMPLETADA';
                    } else {
                        $operators[$key]['lunch_status'] = 'EN_CURSO';
                    }
                    if (!empty($eRow['colacion_comments'])) {
                        $operators[$key]['comments'] = (string)$eRow['colacion_comments'];
                    }
                    if ((int)$eRow['ot_id'] > 0) {
                        $operators[$key]['latest_ot_id'] = (int)$eRow['ot_id'];
                    }
                    if (empty($operators[$key]['init_id']) && (int)$eRow['init_id'] > 0) {
                        $operators[$key]['init_id'] = (int)$eRow['init_id'];
                    }
                }
            }
        } catch (Throwable) {}

        // 5. Consultar turnos locales en TRZ (production_shift_sessions)
        try {
            $stmt = $this->pdo->prepare("
                SELECT pss.*, pm.name AS machine_name
                FROM production_shift_sessions pss
                LEFT JOIN production_machines pm ON pm.id = pss.machine_id
                WHERE DATE(pss.started_at) = :d
                ORDER BY pss.id DESC
            ");
            $stmt->execute([':d' => $date]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $sRow) {
                $opName = trim((string)$sRow['operator_name']);
                if ($opName === '') continue;
                $mName = trim((string)$sRow['machine_name']) ?: 'Sin Máquina';
                if (stripos($mName, 'supervisor') !== false || stripos($mName, 'mantencion') !== false || stripos($mName, 'mantenimiento') !== false || stripos($mName, 'embalaje') !== false) {
                    continue;
                }
                $key = 'TRZ_' . $sRow['id'];
                $already = false;
                foreach ($operators as $op) {
                    if (strcasecmp($op['operator_name'], $opName) === 0) {
                        $already = true;
                        break;
                    }
                }
                if (!$already) {
                    $operators[$key] = [
                        'key' => $key,
                        'source' => 'TRZ',
                        'init_id' => 0,
                        'worker_id' => 0,
                        'operator_name' => $opName,
                        'machine_id' => (int)$sRow['machine_id'],
                        'machine_name' => $mName,
                        'cargo_name' => 'Operario Planta',
                        'shift_start' => date('H:i', strtotime($sRow['started_at'])),
                        'shift_end' => $sRow['ended_at'] ? date('H:i', strtotime($sRow['ended_at'])) : 'En curso',
                        'shift_status' => (string)$sRow['status'] === 'ACTIVE' ? 'ACTIVO' : 'CERRADO',
                        'has_lunch' => false,
                        'lunch_event_id' => null,
                        'lunch_start' => '',
                        'lunch_end' => '',
                        'duration_minutes' => 0,
                        'lunch_status' => 'PENDIENTE',
                        'comments' => '',
                        'latest_ot_id' => null,
                    ];
                }
            }
        } catch (Throwable) {}

        // Excluir de forma estricta: Embalaje, personal sin máquina iniciada, ayudantes, supervisores y mantención
        $operators = array_filter($operators, function ($op) use ($ayudanteWorkerIds) {
            $wId = (int)($op['worker_id'] ?? 0);
            if (in_array($wId, [27, 68], true) || in_array($wId, $ayudanteWorkerIds, true)) {
                return false;
            }
            $mId = (int)($op['machine_id'] ?? 0);
            $mName = mb_strtoupper((string)($op['machine_name'] ?? ''));
            if ($mId <= 0 || $mName === '' || $mName === 'SIN MÁQUINA' || $mName === 'SIN MAQUINA') {
                return false;
            }
            if (str_contains($mName, 'EMBALAJE')) {
                return false;
            }
            if (str_contains($mName, 'SUPERVISOR') || str_contains($mName, 'MANTENCION') || str_contains($mName, 'MANTENIMIENTO') || str_contains($mName, 'BODEGA') || str_contains($mName, 'CHOFER') || str_contains($mName, 'ASEO')) {
                return false;
            }
            if (empty($op['init_id']) && empty($op['latest_ot_id'])) {
                return false;
            }
            $cargo = mb_strtoupper((string)($op['cargo_name'] ?? ''));
            if (str_contains($cargo, 'AYUDANTE') || str_contains($cargo, 'EMBALAD')) {
                return false;
            }
            $n = mb_strtoupper((string)($op['operator_name'] ?? ''));
            if (str_contains($n, 'SUPERVISOR') || str_contains($n, 'TOTESAUT') || str_contains($n, 'PEROZO') || str_contains($n, 'PEREZ PALOMARES') || str_contains($n, 'NAVESNIK') || str_contains($n, 'HUAMÁN') || str_contains($n, 'HUAMAN') || str_contains($n, 'CORONADO')) {
                return false;
            }
            return true;
        });

        // 6. Evaluar fecha y hora límite (14:00)
        $isToday = ($date === date('Y-m-d'));
        $currentHi = (int)date('Hi');
        $isPastDeadline = (!$isToday && $date < date('Y-m-d')) || ($isToday && $currentHi >= 1400);

        // 7. Vincular colaciones guardadas / editadas
        foreach ($operators as $k => &$op) {
            $saved = null;
            if (!empty($op['init_id']) && !empty($op['worker_id'])) {
                $saved = $savedBreaks['init_' . $op['init_id'] . '_' . $op['worker_id']] ?? null;
            }
            if (!$saved && !empty($op['worker_id'])) {
                $saved = $savedBreaks['wrk_' . $op['worker_id']] ?? null;
            }
            if (!$saved && !empty($op['operator_name'])) {
                $saved = $savedBreaks['name_' . mb_strtolower(trim((string)$op['operator_name']))] ?? null;
            }

            if ($saved) {
                $op['has_lunch'] = true;
                $op['lunch_start'] = date('H:i', strtotime($saved['start_time']));
                $op['lunch_end'] = $saved['end_time'] ? date('H:i', strtotime($saved['end_time'])) : '';
                $op['duration_minutes'] = (int)($saved['duration_minutes'] ?? 0);
                $op['comments'] = (string)($saved['comments'] ?? '');
                $op['lunch_status'] = !empty($op['lunch_end']) ? 'COMPLETADA' : 'EN_CURSO';
                $op['trz_break_id'] = (int)$saved['id'];
            }
            if (!$op['has_lunch']) {
                $op['lunch_status'] = $isPastDeadline ? 'FALTA_CRITICA' : 'PENDIENTE';
            }
        }
        unset($op);

        // Ordenar: primero faltas críticas, luego pendientes, luego en curso y completadas
        uasort($operators, function ($a, $b) {
            $priority = [
                'FALTA_CRITICA' => 1,
                'PENDIENTE' => 2,
                'EN_CURSO' => 3,
                'COMPLETADA' => 4,
            ];
            $pA = $priority[$a['lunch_status']] ?? 5;
            $pB = $priority[$b['lunch_status']] ?? 5;
            if ($pA !== $pB) {
                return $pA <=> $pB;
            }
            $mCmp = strcasecmp((string)$a['machine_name'], (string)$b['machine_name']);
            if ($mCmp !== 0) {
                return $mCmp;
            }
            return strcasecmp((string)$a['operator_name'], (string)$b['operator_name']);
        });

        $total = count($operators);
        $registered = count(array_filter($operators, fn($o) => $o['has_lunch']));
        $missing = $total - $registered;
        $missingOperators = array_values(array_filter($operators, fn($o) => !$o['has_lunch']));

        // Construir lista exhaustiva de máquinas de producción (excluyendo embalaje, puestos de supervisión y mantención)
        $distinctMachines = [];
        foreach ($allMachines as $m) {
            $upper = mb_strtoupper($m);
            if (!str_contains($upper, 'SUPERVISOR') && !str_contains($upper, 'MANTENCION') && !str_contains($upper, 'MANTENIMIENTO') && !str_contains($upper, 'EMBALAJE') && !str_contains($upper, 'BODEGA') && !str_contains($upper, 'CHOFER') && !str_contains($upper, 'ASEO')) {
                $distinctMachines[$m] = $m;
            }
        }
        foreach ($operators as $o) {
            if (!empty($o['machine_name'])) {
                $upper = mb_strtoupper($o['machine_name']);
                if (!str_contains($upper, 'SUPERVISOR') && !str_contains($upper, 'MANTENCION') && !str_contains($upper, 'MANTENIMIENTO') && !str_contains($upper, 'EMBALAJE') && !str_contains($upper, 'BODEGA') && !str_contains($upper, 'CHOFER') && !str_contains($upper, 'ASEO')) {
                    $distinctMachines[$o['machine_name']] = $o['machine_name'];
                }
            }
        }
        $machinesList = array_values($distinctMachines);
        natcasesort($machinesList);
        $machinesList = array_values($machinesList);

        $completedCount = count(array_filter($operators, fn($o) => $o['lunch_status'] === 'COMPLETADA'));
        $inProgressCount = count(array_filter($operators, fn($o) => $o['lunch_status'] === 'EN_CURSO'));

        return [
            'date' => $date,
            'is_today' => $isToday,
            'is_past_deadline' => $isPastDeadline,
            'total_operators' => $total,
            'registered_count' => $registered,
            'completed_count' => $completedCount,
            'in_progress_count' => $inProgressCount,
            'missing_count' => $missing,
            'compliance_percent' => $total > 0 ? round(($registered / $total) * 100, 1) : 100.0,
            'operators' => array_values($operators),
            'missing_operators' => $missingOperators,
            'machines' => $machinesList,
            'all_machines' => $allMachines,
            'all_workers' => $this->getAllPlantWorkers(),
        ];
    }

    /**
     * Retorna la lista de todos los maquinistas de planta activos (excluyendo supervisores, mantención, embalaje y ayudantes)
     */
    public function getAllPlantWorkers(): array
    {
        try {
            $stmt = $this->erpPdo->query("
                SELECT w.id, 
                       TRIM(CONCAT(COALESCE(w.wrk_firstname,''), ' ', COALESCE(w.wrk_lastname,''))) AS name,
                       w.wrk_cargoid, wt.type_name AS cargo_name
                FROM workers w
                LEFT JOIN workers_types wt ON w.wrk_cargoid = wt.id
                WHERE w.wrk_status = 1
                  AND w.id NOT IN (27, 68)
                  AND (w.wrk_cargoid IS NULL OR w.wrk_cargoid NOT IN (3, 4, 6, 9, 10, 14))
                  AND (wt.type_name IS NULL OR (
                      wt.type_name NOT LIKE '%Supervisor%' 
                      AND wt.type_name NOT LIKE '%Mantenci%' 
                      AND wt.type_name NOT LIKE '%Mantenim%'
                      AND wt.type_name NOT LIKE '%Embalad%'
                      AND wt.type_name NOT LIKE '%Chequeo%'
                      AND wt.type_name NOT LIKE '%Ayudante%'
                      AND wt.type_name NOT LIKE '%Chofer%'
                      AND wt.type_name NOT LIKE '%Aseo%'
                      AND wt.type_name NOT LIKE '%Limpieza%'
                  ))
                ORDER BY w.wrk_firstname ASC, w.wrk_lastname ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Registra o actualiza el horario de colación de un operario individual.
     */
    public function saveOperatorLunchBreak(
        string $date,
        string $operatorName,
        string $startTime,
        string $endTime,
        int $workerId = 0,
        int $initId = 0,
        int $machineId = 0,
        string $machineName = '',
        ?string $comments = null,
        string $createdBy = ''
    ): array {
        $this->ensureOperatorLunchBreaksTable();

        $operatorName = trim($operatorName);
        $startTime = trim($startTime);
        $endTime = trim($endTime);
        if ($operatorName === '' || $startTime === '') {
            return ['ok' => false, 'error' => 'Operador y hora de inicio son requeridos.'];
        }

        $fullStart = "$date $startTime:00";
        $fullEnd = $endTime !== '' ? "$date $endTime:00" : null;
        $startTs = strtotime($fullStart);
        $endTs = $fullEnd ? strtotime($fullEnd) : null;
        $duration = ($endTs && $endTs > $startTs) ? (int)round(($endTs - $startTs) / 60) : 0;

        // 1. Sincronizar en ERP prod_worker_ot_events si el operario tiene OT o turno
        $erpEventId = null;
        try {
            $targetOtId = 0;
            if ($initId > 0) {
                $stmtOt = $this->erpPdo->prepare("SELECT id FROM prod_worker_ot WHERE wok_init_id = :init_id ORDER BY id DESC LIMIT 1");
                $stmtOt->execute([':init_id' => $initId]);
                $targetOtId = (int)$stmtOt->fetchColumn();
            }
            if ($targetOtId <= 0 && $workerId > 0) {
                list($y, $m, $d) = explode('-', $date);
                $stmtOt = $this->erpPdo->prepare("
                    SELECT ot.id 
                    FROM prod_worker_ot ot
                    INNER JOIN prod_worker_init wi ON ot.wok_init_id = wi.id
                    WHERE wi.win_wrkid = :wrkid AND wi.win_day = :d AND wi.win_month = :m AND wi.win_year = :y
                    ORDER BY ot.id DESC LIMIT 1
                ");
                $stmtOt->execute([':wrkid' => $workerId, ':d' => (int)$d, ':m' => (int)$m, ':y' => (int)$y]);
                $targetOtId = (int)$stmtOt->fetchColumn();
            }

            if ($targetOtId > 0) {
                $stmtEv = $this->erpPdo->prepare("
                    SELECT id FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :otid AND evt_pause_id = 1 AND evt_type = 'pause'
                    ORDER BY id DESC LIMIT 1
                ");
                $stmtEv->execute([':otid' => $targetOtId]);
                $existingEvtId = (int)$stmtEv->fetchColumn();

                if ($existingEvtId > 0) {
                    $upd = $this->erpPdo->prepare("
                        UPDATE prod_worker_ot_events
                        SET evt_crtdat = :start_ts, evt_enddat = :end_ts, evt_status = 1, evt_comments = :comments
                        WHERE id = :id
                    ");
                    $upd->execute([
                        ':start_ts' => $startTs,
                        ':end_ts' => $endTs ?: $startTs,
                        ':comments' => $comments ?: 'Colación registrada por supervisor',
                        ':id' => $existingEvtId,
                    ]);
                    $erpEventId = $existingEvtId;
                } else {
                    $ins = $this->erpPdo->prepare("
                        INSERT INTO prod_worker_ot_events
                            (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments, evt_pause_id)
                        VALUES
                            (:otid, 0, :start_ts, :end_ts, 1, 'pause', :comments, 1)
                    ");
                    $ins->execute([
                        ':otid' => $targetOtId,
                        ':start_ts' => $startTs,
                        ':end_ts' => $endTs ?: $startTs,
                        ':comments' => $comments ?: 'Colación registrada por supervisor',
                    ]);
                    $erpEventId = (int)$this->erpPdo->lastInsertId();
                }
            }
        } catch (Throwable) {}

        // 2. Guardar en base local TRZ production_operator_lunch_breaks
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM production_operator_lunch_breaks
                WHERE shift_date = :d AND (
                    (:wrkid > 0 AND worker_id = :wrkid)
                    OR (:init_id > 0 AND init_id = :init_id)
                    OR (:wrkid <= 0 AND :init_id <= 0 AND operator_name = :opname)
                )
                LIMIT 1
            ");
            $stmt->execute([
                ':d' => $date,
                ':init_id' => $initId,
                ':wrkid' => $workerId,
                ':opname' => $operatorName,
            ]);
            $existingBreakId = (int)$stmt->fetchColumn();

            if ($existingBreakId > 0) {
                $upd = $this->pdo->prepare("
                    UPDATE production_operator_lunch_breaks
                    SET start_time = :st, end_time = :et, duration_minutes = :dur,
                        comments = :comm,
                        machine_id = COALESCE(:mid, machine_id),
                        machine_name = COALESCE(:mname, machine_name),
                        worker_id = COALESCE(:wrkid, worker_id),
                        init_id = COALESCE(:initid, init_id),
                        erp_event_id = COALESCE(:erpid, erp_event_id),
                        created_by = :cby
                    WHERE id = :id
                ");
                $upd->execute([
                    ':st' => $fullStart,
                    ':et' => $fullEnd,
                    ':dur' => $duration,
                    ':comm' => $comments,
                    ':mid' => $machineId > 0 ? $machineId : null,
                    ':mname' => $machineName ?: null,
                    ':wrkid' => $workerId > 0 ? $workerId : null,
                    ':initid' => $initId > 0 ? $initId : null,
                    ':erpid' => $erpEventId,
                    ':cby' => $createdBy ?: null,
                    ':id' => $existingBreakId,
                ]);
                $savedId = $existingBreakId;
            } else {
                $ins = $this->pdo->prepare("
                    INSERT INTO production_operator_lunch_breaks
                        (shift_date, init_id, worker_id, operator_name, machine_id, machine_name, start_time, end_time, duration_minutes, erp_event_id, comments, created_by)
                    VALUES
                        (:d, :init_id, :wrkid, :opname, :mid, :mname, :st, :et, :dur, :erpid, :comm, :cby)
                ");
                $ins->execute([
                    ':d' => $date,
                    ':init_id' => $initId > 0 ? $initId : null,
                    ':wrkid' => $workerId > 0 ? $workerId : null,
                    ':opname' => $operatorName,
                    ':mid' => $machineId > 0 ? $machineId : null,
                    ':mname' => $machineName ?: null,
                    ':st' => $fullStart,
                    ':et' => $fullEnd,
                    ':dur' => $duration,
                    ':erpid' => $erpEventId,
                    ':comm' => $comments,
                    ':cby' => $createdBy ?: null,
                ]);
                $savedId = (int)$this->pdo->lastInsertId();
            }

            return ['ok' => true, 'id' => $savedId, 'erp_event_id' => $erpEventId];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Guarda colación de forma masiva para un conjunto de operarios seleccionados.
     *
     * @param array<int, array<string, mixed>> $items Lista de operarios
     */
    public function saveBulkOperatorLunchBreaks(
        array $items,
        string $date,
        string $startTime,
        string $endTime,
        ?string $comments = null,
        string $createdBy = ''
    ): array {
        $count = 0;
        $errors = [];
        foreach ($items as $item) {
            $opName = trim((string)($item['operator_name'] ?? ''));
            if ($opName === '') continue;
            $res = $this->saveOperatorLunchBreak(
                $date,
                $opName,
                $startTime,
                $endTime,
                (int)($item['worker_id'] ?? 0),
                (int)($item['init_id'] ?? 0),
                (int)($item['machine_id'] ?? 0),
                (string)($item['machine_name'] ?? ''),
                $comments,
                $createdBy
            );
            if ($res['ok']) {
                $count++;
            } else {
                $errors[] = $opName . ': ' . ($res['error'] ?? 'Error desconocido');
            }
        }
        return ['ok' => $count > 0, 'updated_count' => $count, 'errors' => $errors];
    }

    /**
     * Obtiene la colacion pendiente del operario, incluso si el turno cruza medianoche.
     */
    public function getActiveOperatorLunchBreak(int $workerId = 0, int $initId = 0, string $operatorName = ''): ?array
    {
        $this->ensureOperatorLunchBreaksTable();
        $where = [];
        $params = [];
        if ($workerId > 0) {
            $where[] = 'worker_id = :worker';
            $params[':worker'] = $workerId;
        }
        if ($initId > 0) {
            $where[] = ($workerId > 0 ? '(COALESCE(worker_id,0)=0 AND init_id=:init)' : 'init_id=:init');
            $params[':init'] = $initId;
        }
        if (trim($operatorName) !== '') {
            $where[] = ($workerId > 0 || $initId > 0)
                ? '(COALESCE(worker_id,0)=0 AND COALESCE(init_id,0)=0 AND operator_name=:name)'
                : 'operator_name=:name';
            $params[':name'] = trim($operatorName);
        }
        if ($where === []) return null;
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM production_operator_lunch_breaks
                WHERE end_time IS NULL AND (' . implode(' OR ', $where) . ') ORDER BY id DESC LIMIT 1');
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Inicia la colación de un operario usando la hora exacta actual del sistema (sin hora fija manual).
     */
    public function startOperatorLunchBreak(
        string $operatorName,
        int $workerId = 0,
        int $initId = 0,
        int $machineId = 0,
        string $machineName = '',
        ?string $comments = null
    ): array {
        $this->ensureOperatorLunchBreaksTable();
        $operatorName = trim($operatorName);
        if ($operatorName === '') {
            return ['ok' => false, 'error' => 'Nombre de operario requerido.'];
        }

        $existing = $this->getActiveOperatorLunchBreak($workerId, $initId, $operatorName);
        if ($existing !== null) {
            $st = date('H:i', strtotime((string)$existing['start_time']));
            return ['ok' => false, 'error' => "Ya tienes una colación en curso iniciada a las {$st}."];
        }

        $today = date('Y-m-d');
        $now = time();
        $startDt = date('Y-m-d H:i:s', $now);

        // 1. Abrir evento de pausa tipo 1 (Colación) en la OT activa del ERP si existe
        $erpEventId = null;
        try {
            $targetOtId = 0;
            if ($initId > 0) {
                $stmtOt = $this->erpPdo->prepare("SELECT id FROM prod_worker_ot WHERE wok_init_id = :init_id AND wok_status=1 ORDER BY id DESC LIMIT 1");
                $stmtOt->execute([':init_id' => $initId]);
                $targetOtId = (int)$stmtOt->fetchColumn();
            }
            if ($targetOtId <= 0 && $workerId > 0) {
                list($y, $m, $d) = explode('-', $today);
                $stmtOt = $this->erpPdo->prepare("
                    SELECT ot.id 
                    FROM prod_worker_ot ot
                    INNER JOIN prod_worker_init wi ON ot.wok_init_id = wi.id
                    WHERE wi.win_wrkid = :wrkid AND wi.win_day = :d AND wi.win_month = :m AND wi.win_year = :y AND ot.wok_status=1
                    ORDER BY ot.id DESC LIMIT 1
                ");
                $stmtOt->execute([':wrkid' => $workerId, ':d' => (int)$d, ':m' => (int)$m, ':y' => (int)$y]);
                $targetOtId = (int)$stmtOt->fetchColumn();
            }

            if ($targetOtId > 0) {
                $ins = $this->erpPdo->prepare("
                    INSERT INTO prod_worker_ot_events
                        (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments, evt_pause_id)
                    VALUES
                        (:otid, 0, :start_ts, 0, 1, 'pause', :comments, 1)
                ");
                $ins->execute([
                    ':otid' => $targetOtId,
                    ':start_ts' => $now,
                    ':comments' => 'Colación en curso iniciada a las ' . date('H:i', $now) . ($comments ? " ({$comments})" : ''),
                ]);
                $erpEventId = (int)$this->erpPdo->lastInsertId();
            }
        } catch (Throwable) {}

        // 2. Registrar en la base local de trazabilidad
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO production_operator_lunch_breaks
                    (shift_date, init_id, worker_id, operator_name, machine_id, machine_name, start_time, end_time, duration_minutes, erp_event_id, comments, created_by)
                VALUES
                    (:d, :init_id, :wrkid, :opname, :mid, :mname, :st, NULL, NULL, :erpid, :comm, :cby)
            ");
            $stmt->execute([
                ':d' => $today,
                ':init_id' => $initId > 0 ? $initId : null,
                ':wrkid' => $workerId > 0 ? $workerId : null,
                ':opname' => $operatorName,
                ':mid' => $machineId > 0 ? $machineId : null,
                ':mname' => $machineName ?: null,
                ':st' => $startDt,
                ':erpid' => $erpEventId,
                ':comm' => $comments ?: 'Colación iniciada en tiempo real',
                ':cby' => $operatorName,
            ]);
            $lunchId = (int)$this->pdo->lastInsertId();
            return ['ok' => true, 'id' => $lunchId, 'start_time' => date('H:i', $now)];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Finaliza la colación de un operario usando la hora exacta actual del sistema (sin hora fija manual).
     */
    public function endOperatorLunchBreak(
        int $lunchId = 0,
        string $operatorName = '',
        int $workerId = 0,
        int $initId = 0,
        ?string $comments = null
    ): array {
        $this->ensureOperatorLunchBreaksTable();
        $lunch = null;
        if ($lunchId > 0) {
            $stmt = $this->pdo->prepare("SELECT * FROM production_operator_lunch_breaks WHERE id = :id");
            $stmt->execute([':id' => $lunchId]);
            $lunch = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$lunch && $lunchId <= 0) {
            $lunch = $this->getActiveOperatorLunchBreak($workerId, $initId, $operatorName);
        }
        if (!$lunch) {
            return ['ok' => false, 'error' => 'No se encontró una colación activa en curso para finalizar.'];
        }
        $ownsLunch = (int)($lunch['worker_id'] ?? 0) > 0
            ? $workerId > 0 && (int)$lunch['worker_id'] === $workerId
            : ((int)($lunch['init_id'] ?? 0) > 0
                ? $initId > 0 && (int)$lunch['init_id'] === $initId
                : trim($operatorName) !== '' && trim((string)$lunch['operator_name']) === trim($operatorName));
        if (!$ownsLunch) {
            return ['ok'=>false, 'error'=>'La colacion no pertenece al operador conectado.'];
        }
        if (!empty($lunch['end_time'])) {
            return ['ok'=>true, 'duration'=>(int)$lunch['duration_minutes'],
                'start_time'=>date('H:i', strtotime((string)$lunch['start_time'])),
                'end_time'=>date('H:i', strtotime((string)$lunch['end_time']))];
        }

        $now = time();
        $endDt = date('Y-m-d H:i:s', $now);
        $startTs = strtotime((string)$lunch['start_time']);
        $duration = max(1, (int)round(($now - $startTs) / 60));

        // 1. Cerrar evento en el ERP si existe
        if (!empty($lunch['erp_event_id'])) {
            try {
                $upd = $this->erpPdo->prepare("
                    UPDATE prod_worker_ot_events
                    SET evt_enddat = :now, evt_status = 2, evt_amount = :dur,
                        evt_comments = :comments
                    WHERE id = :id AND evt_type='pause' AND evt_pause_id=1
                        AND evt_status>0 AND (evt_enddat=0 OR evt_enddat IS NULL)
                ");
                $upd->execute([
                    ':now' => $now,
                    ':dur' => $duration,
                    ':comments' => 'Colación finalizada (' . $duration . ' min de ' . date('H:i', $startTs) . ' a ' . date('H:i', $now) . ')' . ($comments ? " - {$comments}" : ''),
                    ':id' => (int)$lunch['erp_event_id'],
                ]);
                if ($upd->rowCount() === 0) {
                    // A retry after a local failure must preserve the ERP closing time.
                    // Un reintento tras un fallo local conserva la hora de cierre del ERP.
                    $stmtEnd = $this->erpPdo->prepare("SELECT evt_enddat FROM prod_worker_ot_events
                        WHERE id=? AND evt_type='pause' AND evt_pause_id=1 AND evt_status>0");
                    $stmtEnd->execute([(int)$lunch['erp_event_id']]);
                    $erpEnd = (int)$stmtEnd->fetchColumn();
                    if ($erpEnd <= 0) {
                        return ['ok'=>false, 'error'=>'No se pudo cerrar el evento de colacion en el ERP. Reintenta el termino.'];
                    }
                    $now = $erpEnd;
                    $endDt = date('Y-m-d H:i:s', $now);
                    $duration = max(1, (int)round(($now - $startTs) / 60));
                }
            } catch (Throwable $e) {
                error_log('Lunch ERP closure: ' . $e->getMessage());
                return ['ok'=>false, 'error'=>'No se pudo guardar el termino de colacion en el ERP. Reintenta cuando se restablezca la conexion.'];
            }
        }

        // 2. Actualizar registro en base local
        try {
            $stmt = $this->pdo->prepare("
                UPDATE production_operator_lunch_breaks
                SET end_time = :et, duration_minutes = :dur,
                    comments = CONCAT(COALESCE(comments, ''), :comm)
                WHERE id = :id AND end_time IS NULL
            ");
            $stmt->execute([
                ':et' => $endDt,
                ':dur' => $duration,
                ':comm' => $comments ? " | Fin: {$comments}" : '',
                ':id' => (int)$lunch['id'],
            ]);

            return [
                'ok' => true,
                'duration' => $duration,
                'start_time' => date('H:i', $startTs),
                'end_time' => date('H:i', $now),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Guarda colación de forma masiva para todos los operarios de una máquina en la fecha.
     */
    public function saveMachineLunchBreaks(
        string $date,
        string $machineName,
        string $startTime,
        string $endTime,
        ?string $comments = null,
        string $createdBy = ''
    ): array {
        $report = $this->getOperatorLunchReport($date);
        $machineName = trim($machineName);
        $targetOps = [];
        foreach ($report['operators'] as $op) {
            if (strcasecmp((string)$op['machine_name'], $machineName) === 0) {
                $targetOps[] = $op;
            }
        }

        if (empty($targetOps)) {
            return ['ok' => false, 'error' => "No se encontraron operarios en la máquina '{$machineName}' para el día {$date}."];
        }

        return $this->saveBulkOperatorLunchBreaks($targetOps, $date, $startTime, $endTime, $comments, $createdBy);
    }

    /**
     * Carga masiva de colaciones para un rango de fechas (Fecha Inicio a Fecha Fin),
     * ya sea por máquina o por lista de operarios seleccionados.
     *
     * REGLAS CRÍTICAS:
     * 1. Solo se cargará en días donde el operario haya INICIADO TURNO de trabajo efectivamente.
     *    Si no vino a trabajar o no tiene turno iniciado ese día, no se registra nada.
     * 2. Solo se cargará si NO tienen colación ya registrada para ese día (no sobreescribe colaciones previas).
     */
    public function saveBulkLunchDateRange(
        array $targetWorkerIds,
        ?string $targetMachineName,
        string $startDate,
        string $endDate,
        string $startTime,
        string $endTime,
        ?string $comments = null,
        string $currentUser = ''
    ): array {
        $this->ensureOperatorLunchBreaksTable();

        $startDate = trim($startDate);
        $endDate = trim($endDate);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $endDate = $startDate;
        }
        if ($startDate > $endDate) {
            $tmp = $startDate;
            $startDate = $endDate;
            $endDate = $tmp;
        }

        $startTime = trim($startTime) ?: '12:00';
        $endTime = trim($endTime) ?: '12:30';
        $targetMachineName = trim((string)$targetMachineName);
        if (strcasecmp($targetMachineName, 'Todas las Máquinas') === 0 || strcasecmp($targetMachineName, 'all') === 0) {
            $targetMachineName = '';
        }

        $startDt = new DateTime($startDate);
        $endDt = new DateTime($endDate);
        $endDt->modify('+1 day');
        $period = new DatePeriod($startDt, new DateInterval('P1D'), $endDt);

        // Consultar colaciones ya registradas en TRZ dentro del rango para no duplicar ni sobreescribir
        $existingBreaks = [];
        try {
            $stmtTrz = $this->pdo->prepare("
                SELECT shift_date, worker_id, operator_name 
                FROM production_operator_lunch_breaks 
                WHERE shift_date BETWEEN :s AND :e
            ");
            $stmtTrz->execute([':s' => $startDate, ':e' => $endDate]);
            foreach ($stmtTrz->fetchAll(PDO::FETCH_ASSOC) as $eb) {
                if (!empty($eb['worker_id'])) {
                    $existingBreaks[$eb['shift_date'] . '_w_' . $eb['worker_id']] = true;
                }
                if (!empty($eb['operator_name'])) {
                    $existingBreaks[$eb['shift_date'] . '_n_' . mb_strtolower(trim((string)$eb['operator_name']))] = true;
                }
            }
        } catch (Throwable) {}

        $targetWorkerIdsMap = [];
        foreach ($targetWorkerIds as $twid) {
            $id = (int)$twid;
            if ($id > 0) $targetWorkerIdsMap[$id] = true;
        }

        $updatedCount = 0;
        $daysProcessed = 0;
        $skippedAlreadyHad = 0;

        foreach ($period as $dt) {
            $currDate = $dt->format('Y-m-d');
            $daysProcessed++;
            $y = (int)$dt->format('Y');
            $m = (int)$dt->format('m');
            $d = (int)$dt->format('d');

            // 1. Identificar qué operarios iniciaron turno ese día en SELLADORA
            $workersOnSelladora = [];
            try {
                $stmtSell = $this->erpPdo->prepare("
                    SELECT wi.win_wrkid, eq.id AS eq_id, eq.equipo_name 
                    FROM prod_worker_init wi
                    LEFT JOIN equipo eq ON wi.win_equipoid = eq.id
                    WHERE wi.win_year = :y AND wi.win_month = :m AND wi.win_day = :d
                      AND eq.equipo_name LIKE '%SELLADORA%'
                ");
                $stmtSell->execute([':y' => $y, ':m' => $m, ':d' => $d]);
                foreach ($stmtSell->fetchAll(PDO::FETCH_ASSOC) as $sRow) {
                    $workersOnSelladora[(int)$sRow['win_wrkid']] = [
                        'machine_id' => (int)$sRow['eq_id'],
                        'machine_name' => trim((string)$sRow['equipo_name']),
                    ];
                }
            } catch (Throwable) {}

            $dayCandidates = [];

            // 2. Consultar turnos asignados en ERP para este día (turnos_config_assign)
            try {
                $stmtAssign = $this->erpPdo->prepare("
                    SELECT 
                        0 AS init_id,
                        a.assign_worker_id AS worker_id,
                        eq.id AS machine_id,
                        COALESCE(eq.equipo_name, 'Sin Máquina') AS machine_name,
                        TRIM(CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, ''))) AS operator_name,
                        w.wrk_cargoid,
                        wt.type_name AS cargo_name,
                        NULL AS ot_id,
                        NULL AS colacion_evt_id
                    FROM turnos_config_assign a
                    JOIN workers w ON a.assign_worker_id = w.id
                    LEFT JOIN workers_types wt ON w.wrk_cargoid = wt.id
                    LEFT JOIN equipo eq ON a.assign_equipoaid = eq.id
                    WHERE a.assign_year = :y AND a.assign_month = :m AND a.assign_day = :d
                      AND (a.confirm_falta_act IS NULL OR a.confirm_falta_act = 0)
                      AND (eq.equipo_name IS NULL OR (eq.equipo_name NOT LIKE '%SUPERVISOR%' AND eq.equipo_name NOT LIKE '%MANTENCION%'))
                      AND (w.wrk_cargoid IS NULL OR w.wrk_cargoid NOT IN (3, 4, 9, 10))
                      AND (wt.type_name IS NULL OR (wt.type_name NOT LIKE '%Supervisor%' AND wt.type_name NOT LIKE '%Mantenci%' AND wt.type_name NOT LIKE '%Mantenim%'))
                      AND (a.assign_worker_type IS NULL OR a.assign_worker_type NOT LIKE '%supervisor%')
                      AND (a.assign_worker_id IS NULL OR a.assign_worker_id NOT IN (27, 68))
                    ORDER BY a.id ASC
                ");
                $stmtAssign->execute([':y' => $y, ':m' => $m, ':d' => $d]);
                foreach ($stmtAssign->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $wId = (int)$row['worker_id'];
                    $mName = trim((string)$row['machine_name']);
                    if (isset($workersOnSelladora[$wId]) && stripos($mName, 'embalaje') !== false) {
                        $mName = $workersOnSelladora[$wId]['machine_name'];
                        $row['machine_name'] = $mName;
                        $row['machine_id'] = $workersOnSelladora[$wId]['machine_id'];
                    }
                    $dayCandidates[$wId] = $row;
                }
            } catch (Throwable) {}

            // 3. Consultar turnos físicos iniciados en ERP para este día (prod_worker_init)
            try {
                $sql = "
                    SELECT 
                        wi.id AS init_id,
                        wi.win_wrkid AS worker_id,
                        eq.id AS machine_id,
                        COALESCE(eq.equipo_name, 'Sin Máquina') AS machine_name,
                        TRIM(CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, ''))) AS operator_name,
                        w.wrk_cargoid,
                        wt.type_name AS cargo_name,
                        ot.id AS ot_id,
                        ev.id AS colacion_evt_id
                    FROM prod_worker_init wi
                    JOIN workers w ON wi.win_wrkid = w.id
                    LEFT JOIN workers_types wt ON w.wrk_cargoid = wt.id
                    LEFT JOIN equipo eq ON wi.win_equipoid = eq.id
                    LEFT JOIN prod_worker_ot ot ON ot.wok_init_id = wi.id
                    LEFT JOIN prod_worker_ot_events ev ON ev.evt_prod_worker_otid = ot.id 
                         AND ev.evt_pause_id = 1 AND ev.evt_type = 'pause' AND ev.evt_status > 0
                    WHERE wi.win_year = :y AND wi.win_month = :m AND wi.win_day = :d
                      AND (eq.equipo_name IS NULL OR (eq.equipo_name NOT LIKE '%SUPERVISOR%' AND eq.equipo_name NOT LIKE '%MANTENCION%'))
                      AND (w.wrk_cargoid IS NULL OR w.wrk_cargoid NOT IN (3, 4, 9, 10))
                      AND (wt.type_name IS NULL OR (wt.type_name NOT LIKE '%Supervisor%' AND wt.type_name NOT LIKE '%Mantenci%' AND wt.type_name NOT LIKE '%Mantenim%'))
                      AND (wi.win_wrkid IS NULL OR wi.win_wrkid NOT IN (27, 68))
                    ORDER BY wi.id DESC
                ";
                $stmt = $this->erpPdo->prepare($sql);
                $stmt->execute([':y' => $y, ':m' => $m, ':d' => $d]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $wId = (int)$row['worker_id'];
                    $mName = trim((string)$row['machine_name']);

                    // Regla Selladora vs Embalaje
                    if (isset($workersOnSelladora[$wId]) && stripos($mName, 'embalaje') !== false) {
                        $mName = $workersOnSelladora[$wId]['machine_name'];
                        $row['machine_name'] = $mName;
                        $row['machine_id'] = $workersOnSelladora[$wId]['machine_id'];
                    }

                    $dayCandidates[$wId] = $row;
                }
            } catch (Throwable) {}

            // 4. Consultar sesiones en TRZ para este día
            try {
                $stmtTrzSess = $this->pdo->prepare("
                    SELECT pss.*, pm.name AS machine_name
                    FROM production_shift_sessions pss
                    LEFT JOIN production_machines pm ON pm.id = pss.machine_id
                    WHERE DATE(pss.started_at) = :d
                ");
                $stmtTrzSess->execute([':d' => $currDate]);
                foreach ($stmtTrzSess->fetchAll(PDO::FETCH_ASSOC) as $sRow) {
                    $opName = trim((string)$sRow['operator_name']);
                    if ($opName === '') continue;
                    $mName = trim((string)$sRow['machine_name']);
                    if (stripos($mName, 'supervisor') !== false || stripos($mName, 'mantencion') !== false) {
                        continue;
                    }
                    $swId = (int)($sRow['worker_id'] ?? 0);
                    $trzKey = $swId > 0 ? $swId : ('trz_' . mb_strtolower($opName));
                    if (!isset($dayCandidates[$trzKey])) {
                        $dayCandidates[$trzKey] = [
                            'init_id' => 0,
                            'worker_id' => $swId,
                            'machine_id' => (int)($sRow['machine_id'] ?? 0),
                            'machine_name' => $mName,
                            'operator_name' => $opName,
                            'ot_id' => null,
                            'colacion_evt_id' => null,
                        ];
                    }
                }
            } catch (Throwable) {}

            // Filtrar y aplicar colación a los candidatos del día que iniciaron turno
            foreach ($dayCandidates as $cand) {
                $wId = (int)($cand['worker_id'] ?? 0);
                $opName = trim((string)$cand['operator_name']);
                $mName = trim((string)$cand['machine_name']);

                // Si se especificó lista de operarios, comprobar coincidencia
                if (!empty($targetWorkerIdsMap)) {
                    if ($wId <= 0 || !isset($targetWorkerIdsMap[$wId])) {
                        continue;
                    }
                }

                // Si se especificó máquina objetivo, comprobar coincidencia
                if ($targetMachineName !== '') {
                    if (stripos($mName, $targetMachineName) === false && stripos($targetMachineName, $mName) === false) {
                        continue;
                    }
                }

                // Si NO se especificaron operarios puntuales (ej. asignación a toda la máquina), no sobreescribir si ya tenía colación
                if (empty($targetWorkerIdsMap)) {
                    $alreadyTrz = ($wId > 0 && isset($existingBreaks[$currDate . '_w_' . $wId])) ||
                                  isset($existingBreaks[$currDate . '_n_' . mb_strtolower($opName)]);
                    $alreadyErp = !empty($cand['colacion_evt_id']);
                    if ($alreadyTrz || $alreadyErp) {
                        $skippedAlreadyHad++;
                        continue;
                    }
                }

                // Guardar colación
                $res = $this->saveOperatorLunchBreak(
                    $currDate,
                    $opName,
                    $startTime,
                    $endTime,
                    $wId,
                    (int)($cand['init_id'] ?? 0),
                    (int)($cand['machine_id'] ?? 0),
                    $mName,
                    $comments,
                    $currentUser
                );

                if ($res['ok']) {
                    $updatedCount++;
                    if ($wId > 0) {
                        $existingBreaks[$currDate . '_w_' . $wId] = true;
                    }
                    $existingBreaks[$currDate . '_n_' . mb_strtolower($opName)] = true;
                }
            }
        }

        return [
            'ok' => true,
            'updated_count' => $updatedCount,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days_processed' => $daysProcessed,
            'skipped_already_had' => $skippedAlreadyHad,
        ];
    }

}

trait MachineShiftProductionTrait
{
    /**
     * Obtiene la sesión activa de un operario en una máquina (`win_status = 1`).
     *
     * @param int $workerId ID del operario (workers.id)
     * @param int $plantaId ID de la planta donde opera
     * @return array<string, mixed>|null Datos de prod_worker_init + equipo o null si no tiene máquina abierta
     */
    public function getOpenWorkerInit(int $workerId, int $plantaId): ?array
    {
        if ($workerId <= 0) {
            return null;
        }

        $wherePlanta = $plantaId > 0 ? " AND t1.win_plantaid = :planta_id" : "";
        $sql = "SELECT t1.*, t7.equipo_name, t8.type_ant_title, t7.equipo_type_id,
                       t7.equipo_prod_serimulticolors_act, t8.type_createstock_act,
                       t8.type_ant_inpmedidas_act, t7.equipo_prod_isprinter_seri,
                       t7.equipo_prod_isprinter_flexo, t7.equipo_prod_divisor_perc,
                       t7.equipo_prod_printer_metrotype
                FROM prod_worker_init t1
                LEFT OUTER JOIN equipo t7 ON t1.win_equipoid = t7.id
                LEFT OUTER JOIN equipo_type t8 ON t7.equipo_type_id = t8.id
                WHERE t1.win_wrkid = :wrk_id
                  AND t1.win_status = 1
                  {$wherePlanta}
                ORDER BY t1.id DESC
                LIMIT 1";

        $stmt = $this->erpPdo->prepare($sql);
        $params = [':wrk_id' => $workerId];
        if ($plantaId > 0) {
            $params[':planta_id'] = $plantaId;
        }
        $stmt->execute($params);
        $row = $stmt->fetch();
        if (is_array($row)) {
            if (empty($row['equipo_name']) && (int)$row['win_equipoid'] === 53) {
                $row['equipo_name'] = 'GESTIÓN DE RESIDUOS';
                $row['type_ant_title'] = 'GESTIÓN RESIDUOS';
            }
            return $row;
        }

        return null;
    }

    /**
     * Lista las máquinas activas en la planta indicada, con su tipo y capacidades operativas.
     *
     * @param int $plantaId ID de la planta (0 para todas)
     * @return array<int, array<string, mixed>>
     */
    public function listPlantMachines(int $plantaId): array
    {
        $wherePlanta = $plantaId > 0 ? " AND (t1.equipo_planta_id = :planta_id OR t1.equipo_planta_id = 0)" : "";
        $sql = "SELECT t1.id, t1.equipo_name, t1.equipo_type_id, t1.equipo_prod_isprinter_flexo,
                       t1.equipo_prod_isprinter_seri, t1.equipo_prod_serimulticolors_act,
                       t2.type_ant_title, t2.type_createstock_act, t2.type_ant_inpmedidas_act
                FROM equipo t1
                LEFT OUTER JOIN equipo_type t2 ON t1.equipo_type_id = t2.id
                WHERE t1.equipo_status = 1
                  {$wherePlanta}
                  AND (t1.equipo_prod_dabl = 0 OR t1.equipo_prod_dabl IS NULL OR t1.id = 37 OR t1.equipo_type_id = 21)
                ORDER BY 
                  CASE WHEN t1.id = 37 OR t1.equipo_type_id = 21 THEN 1 ELSE 0 END ASC,
                  t2.type_ant_title, t1.equipo_name ASC";

        $stmt = $this->erpPdo->prepare($sql);
        if ($plantaId > 0) {
            $stmt->execute([':planta_id' => $plantaId]);
        } else {
            $stmt->execute();
        }
        $machines = $stmt->fetchAll();

        // Aseguramos la presencia de la máquina de Residuos
        $hasResiduos = false;
        foreach ($machines as $m) {
            if (stripos($m['equipo_name'], 'residuo') !== false || stripos($m['type_ant_title'] ?? '', 'residuo') !== false) {
                $hasResiduos = true;
                break;
            }
        }
        if (!$hasResiduos) {
            $machines[] = [
                'id' => 53,
                'equipo_name' => 'GESTIÓN DE RESIDUOS',
                'equipo_type_id' => 21,
                'equipo_prod_isprinter_flexo' => 0,
                'equipo_prod_isprinter_seri' => 0,
                'equipo_prod_serimulticolors_act' => 0,
                'type_ant_title' => 'GESTIÓN RESIDUOS',
                'type_createstock_act' => 0,
                'type_ant_inpmedidas_act' => 0,
                'is_waste' => 1,
            ];
        }

        return $machines;
    }

    /**
     * Lista las asignaciones de turno planificadas para el día y la planta seleccionada.
     *
     * @param int $day Día del mes
     * @param int $month Mes
     * @param int $year Año
     * @param int $plantaId ID de planta
     * @return array<int, array<string, mixed>>
     */
    public function listTurnoAssignments(int $day, int $month, int $year, int $plantaId): array
    {
        $wherePlanta = $plantaId > 0 ? " AND t1.assign_planta_id = :planta_id" : "";
        $sql = "SELECT DISTINCT t7.equipo_name, t7.id AS equipo_id, t3.turn_name, t8.type_ant_title,
                                t9.id AS worker_id, t9.wrk_firstname, t9.wrk_lastname, t9.wrk_rut,
                                t1.id AS assign_id
                FROM turnos_config_assign t1
                INNER JOIN turnos t3 ON t1.assign_turno_id = t3.id
                LEFT OUTER JOIN turnos_types t6 ON t1.assign_turno_type_id = t6.id
                LEFT OUTER JOIN equipo t7 ON t1.assign_equipoaid = t7.id
                LEFT OUTER JOIN equipo_type t8 ON t7.equipo_type_id = t8.id
                LEFT OUTER JOIN workers t9 ON t1.assign_worker_id = t9.id
                WHERE t1.assign_day = :day
                  AND t1.assign_month = :month
                  AND t1.assign_year = :year
                  {$wherePlanta}
                  AND t1.assign_worker_id > 0
                  AND t9.wrk_status > 0
                ORDER BY t3.turn_name, t7.equipo_name ASC";

        try {
            $params = [
                ':day' => $day,
                ':month' => $month,
                ':year' => $year,
            ];
            if ($plantaId > 0) {
                $params[':planta_id'] = $plantaId;
            }
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Inicia o reanuda sesión de trabajo en una máquina para el operario (win_status = 1).
     * Si el operario ya tenía otra máquina abierta, la pausa (win_status = 3).
     *
     * @param int $workerId ID del operario
     * @param int $plantaId ID de la planta
     * @param int $equipoId ID de la máquina a iniciar
     * @param int $assId ID de asignación opcional
     * @return array{ok: bool, init_id?: int, error?: string}
     */
    public function startWorkerMachineSession(int $workerId, int $plantaId, int $equipoId, int $assId = 0): array
    {
        if ($workerId <= 0 || $equipoId <= 0) {
            return ['ok' => false, 'error' => 'Operario y máquina son obligatorios.'];
        }

        $now = time();
        $day = (int)date('d', $now);
        $month = (int)date('m', $now);
        $year = (int)date('Y', $now);

        // 1. Verificar si ya tenía esa misma máquina pausada previamente (win_status = 3)
        $stmtCheck = $this->erpPdo->prepare(
            "SELECT id FROM prod_worker_init
             WHERE win_wrkid = :wrk_id
               AND win_plantaid = :planta_id
               AND win_equipoid = :equipo_id
               AND win_status = 3
             ORDER BY id DESC LIMIT 1"
        );
        $stmtCheck->execute([
            ':wrk_id' => $workerId,
            ':planta_id' => $plantaId,
            ':equipo_id' => $equipoId,
        ]);
        $paused = $stmtCheck->fetch();

        // 2. Pausar cualquier otra máquina que el operario tenga actualmente activa (win_status = 1)
        $stmtPauseOther = $this->erpPdo->prepare(
            "UPDATE prod_worker_init
             SET win_status = 3
             WHERE win_wrkid = :wrk_id
               AND win_plantaid = :planta_id
               AND win_status = 1"
        );
        $stmtPauseOther->execute([
            ':wrk_id' => $workerId,
            ':planta_id' => $plantaId,
        ]);

        // 3. Si estaba pausada, reactivarla. Si no, insertar nueva sesión en prod_worker_init.
        if (is_array($paused) && (int)$paused['id'] > 0) {
            $stmtUpdate = $this->erpPdo->prepare(
                "UPDATE prod_worker_init SET win_status = 1 WHERE id = :id"
            );
            $stmtUpdate->execute([':id' => (int)$paused['id']]);
            $initId = (int)$paused['id'];
        } else {
            $stmtInsert = $this->erpPdo->prepare(
                "INSERT INTO prod_worker_init
                 (win_crtdat, win_wrkid, win_status, win_plantaid, win_equipoid, win_ass_id, win_day, win_month, win_year)
                 VALUES
                 (:crtdat, :wrkid, 1, :plantaid, :equipoid, :assid, :day, :month, :year)"
            );
            $stmtInsert->execute([
                ':crtdat' => $now,
                ':wrkid' => $workerId,
                ':plantaid' => $plantaId,
                ':equipoid' => $equipoId,
                ':assid' => $assId,
                ':day' => $day,
                ':month' => $month,
                ':year' => $year,
            ]);
            $initId = (int)$this->erpPdo->lastInsertId();
        }

        return ['ok' => true, 'init_id' => $initId];
    }

    /**
     * Termina el turno en la máquina actual (win_status = 2).
     * Si el operario tenía alguna otra máquina pausada, reactiva la última pausada.
     *
     * @param int $workerId ID del operario
     * @param int $plantaId ID de la planta
     * @return array{ok: bool, error?: string}
     */
    public function endWorkerMachineSession(int $workerId, int $plantaId): array
    {
        $openInit = $this->getOpenWorkerInit($workerId, $plantaId);
        if ($openInit === null) {
            return ['ok' => false, 'error' => 'No hay turno activo para cerrar.'];
        }

        $now = time();
        $stmtClose = $this->erpPdo->prepare(
            "UPDATE prod_worker_init
             SET win_enddat = :enddat, win_status = 2
             WHERE id = :id"
        );
        $stmtClose->execute([
            ':enddat' => $now,
            ':id' => (int)$openInit['id'],
        ]);

        // Si tenía otra máquina pausada previa, reactivar la más reciente
        $stmtResume = $this->erpPdo->prepare(
            "UPDATE prod_worker_init
             SET win_status = 1
             WHERE win_wrkid = :wrkid
               AND win_status = 3
               AND win_plantaid = :plantaid
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmtResume->execute([
            ':wrkid' => $workerId,
            ':plantaid' => $plantaId,
        ]);

        return ['ok' => true];
    }


    public function ensureStandbySchema(): void
    {
        try {
            $this->trzPdo->exec("
                CREATE TABLE IF NOT EXISTS production_machine_standby_events (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    shift_date DATE NOT NULL,
                    init_id BIGINT UNSIGNED NULL,
                    worker_id BIGINT UNSIGNED NULL,
                    operator_name VARCHAR(150) NOT NULL,
                    machine_id INT NOT NULL,
                    machine_name VARCHAR(150) NOT NULL,
                    reason VARCHAR(150) NOT NULL,
                    start_time VARCHAR(10) NOT NULL,
                    end_time VARCHAR(10) NULL,
                    duration_minutes INT DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
                    comments TEXT NULL,
                    created_at BIGINT NOT NULL,
                    ended_at BIGINT NULL,
                    PRIMARY KEY (id),
                    KEY idx_standby_shift (shift_date, machine_id),
                    KEY idx_standby_init (init_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (Throwable) {}
    }

    public function startMachineStandby(
        int $initId,
        int $workerId,
        string $operatorName,
        int $machineId,
        string $machineName,
        string $reason,
        string $startTime,
        string $comments = ''
    ): array {
        $this->ensureStandbySchema();
        $today = date('Y-m-d');
        $now = time();
        try {
            $stmt = $this->trzPdo->prepare("
                INSERT INTO production_machine_standby_events
                    (shift_date, init_id, worker_id, operator_name, machine_id, machine_name, reason, start_time, is_active, comments, created_at)
                VALUES
                    (:shift_date, :init_id, :worker_id, :operator_name, :machine_id, :machine_name, :reason, :start_time, 1, :comments, :created_at)
            ");
            $stmt->execute([
                ':shift_date' => $today,
                ':init_id' => $initId > 0 ? $initId : null,
                ':worker_id' => $workerId > 0 ? $workerId : null,
                ':operator_name' => $operatorName,
                ':machine_id' => $machineId,
                ':machine_name' => $machineName,
                ':reason' => $reason,
                ':start_time' => $startTime ?: date('H:i'),
                ':comments' => $comments,
                ':created_at' => $now,
            ]);
            return ['ok' => true, 'id' => (int)$this->trzPdo->lastInsertId()];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function endMachineStandby(int $standbyId, string $endTime = ''): array
    {
        $this->ensureStandbySchema();
        $endTime = $endTime ?: date('H:i');
        $now = time();
        try {
            $stmt = $this->trzPdo->prepare("SELECT * FROM production_machine_standby_events WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $standbyId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return ['ok' => false, 'error' => 'Evento no encontrado'];

            $duration = 0;
            if (!empty($row['start_time'])) {
                $t1 = strtotime($row['shift_date'] . ' ' . $row['start_time']);
                $t2 = strtotime($row['shift_date'] . ' ' . $endTime);
                if ($t2 > $t1) {
                    $duration = (int)round(($t2 - $t1) / 60);
                }
            }

            $upd = $this->trzPdo->prepare("
                UPDATE production_machine_standby_events
                SET end_time = :end_time, duration_minutes = :dur, is_active = 0, ended_at = :ended_at
                WHERE id = :id
            ");
            $upd->execute([
                ':end_time' => $endTime,
                ':dur' => $duration,
                ':ended_at' => $now,
                ':id' => $standbyId,
            ]);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function getActiveMachineStandby(int $machineId, int $initId = 0): ?array
    {
        $this->ensureStandbySchema();
        $today = date('Y-m-d');
        try {
            $sql = "SELECT * FROM production_machine_standby_events
                    WHERE shift_date = :d AND machine_id = :mid AND is_active = 1
                    ORDER BY id DESC LIMIT 1";
            $stmt = $this->trzPdo->prepare($sql);
            $stmt->execute([':d' => $today, ':mid' => $machineId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (Throwable) {
            return null;
        }
    }

}

class MachineShiftService
{
    use MachineShiftProductionTrait;

    private ?ProductionService $bridge = null;

    public function __construct(private PDO $erpPdo, private ?PDO $trzPdo = null, ?ProductionService $bridge = null)
    {
        $this->bridge = $bridge;
    }

    public function __call(string $name, array $arguments): mixed
    {
        $this->bridge ??= new ProductionService($this->erpPdo, $this->trzPdo);
        return $this->bridge->$name(...$arguments);
    }
}
