<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Autocontrol de Calidad y Aprobaciones de Líder / Supervisor
// (src/Services/Production/QualityAutocontrolService.php)
// =============================================================================

trait QualityAutocontrolServiceTrait
{
    // =========================================================================
    // SECCIÓN 5: CALIDAD Y AUTOCONTROL (prod_worker_ot_autocontrol)
    // =========================================================================

    /**
     * Obtiene los puntos de autocontrol configurados para un tipo de máquina.
     *
     * @param int $equipoTypeId Tipo de máquina (equipo_type.id)
     * @return array<int, array<string, mixed>>
     */
    public function listAutocontrolPoints(int $equipoTypeId): array
    {
        // Rebobinadora (equipo_type_id = 12) no utiliza autocontrol
        if ($equipoTypeId === 12) {
            return [];
        }

        try {
            $sql = "SELECT id, ac_name, ac_desc, ac_order
                    FROM equipo_puntos_autocontrol
                    WHERE ac_status = 1
                      AND (ac_equipotype_id = :type_id OR ac_equipotype_id = 0)
                    ORDER BY ac_order ASC, ac_name ASC";
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute([':type_id' => $equipoTypeId]);
            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Obtiene las respuestas de autocontrol registradas para una OT.
     *
     * @param int $workerOtId ID de la OT (prod_worker_ot.id)
     * @return array<int, int> Mapa de [acid => res]
     */
    public function getAutocontrolResponses(int $workerOtId): array
    {
        if ($workerOtId <= 0) {
            return [];
        }

        try {
            $sql = "SELECT ctr_acid, ctr_res FROM prod_worker_ot_autocontrol
                    WHERE ctr_init_id = :ot_id AND ctr_type = 'worker'";
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute([':ot_id' => $workerOtId]);
            $res = [];
            foreach ($stmt->fetchAll() as $row) {
                $res[(int)$row['ctr_acid']] = (int)$row['ctr_res'];
            }
            return $res;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Guarda la confirmación de autocontrol del operario.
     *
     * @param int $workerOtId ID de la OT
     * @param array<int> $selectedAcids IDs de los puntos conformes
     * @param int $userId ID de usuario que confirma
     * @return array{ok: bool}
     */
    public function saveAutocontrol(int $workerOtId, array $selectedAcids, int $userId): array
    {
        if ($workerOtId <= 0) {
            return ['ok' => false];
        }

        $now = time();
        // Limpiar respuestas previas
        $del = $this->erpPdo->prepare(
            "DELETE FROM prod_worker_ot_autocontrol WHERE ctr_init_id = :ot_id AND ctr_type = 'worker'"
        );
        $del->execute([':ot_id' => $workerOtId]);

        // Insertar cada punto marcado
        $ins = $this->erpPdo->prepare(
            "INSERT INTO prod_worker_ot_autocontrol
             (ctr_init_id, ctr_type, ctr_acid, ctr_res, ctr_ctrusr, ctr_ctrdat)
             VALUES
             (:ot_id, 'worker', :acid, 1, :usr, :dat)"
        );

        foreach ($selectedAcids as $acid) {
            $acid = (int)$acid;
            if ($acid > 0) {
                $ins->execute([
                    ':ot_id' => $workerOtId,
                    ':acid' => $acid,
                    ':usr' => $userId,
                    ':dat' => $now,
                ]);
            }
        }

        return ['ok' => true];
    }

    /**
     * Retorna el estado del evento de Alistamiento (apertura) para la OT.
     *
     * @return array{has_apertura: bool, has_alis: bool, event: ?array<string, mixed>}
     */
    public function getWorkOrderAlistamientoStatus(int $workerOtId): array
    {
        if ($workerOtId <= 0) {
            return ['has_apertura' => false, 'has_alis' => false, 'event' => null];
        }

        try {
            $stmt = $this->erpPdo->prepare(
                "SELECT * FROM prod_worker_ot_events
                 WHERE evt_prod_worker_otid = :ot_id
                   AND evt_status > 0
                   AND evt_type = 'apertura'
                 ORDER BY evt_crtdat DESC"
            );
            $stmt->execute([':ot_id' => $workerOtId]);
            $rows = $stmt->fetchAll() ?: [];

            $hasApertura = false;
            $hasAlis = false;
            $openEvent = null;
            $latestEvent = null;

            foreach ($rows as $row) {
                $hasApertura = true;
                if ($latestEvent === null) {
                    $latestEvent = $row;
                }
                if ((int)($row['evt_enddat'] ?? 0) === 0) {
                    $openEvent = $row;
                } else {
                    $hasAlis = true;
                }
            }

            if ($openEvent !== null) {
                $hasAlis = false;
                $latestEvent = $openEvent;
            }

            return [
                'has_apertura' => $hasApertura,
                'has_alis' => $hasAlis,
                'event' => $latestEvent,
                'open_event' => $openEvent,
            ];
        } catch (Throwable) {
            return ['has_apertura' => false, 'has_alis' => false, 'event' => null];
        }
    }

    /**
     * Retorna el estado completo del flujo de Autocontrol y Aprobación de Partida por Supervisor.
     *
     * @return array{
     *   has_points: bool,
     *   points: array<array<string, mixed>>,
     *   goto_autocontrol: bool,
     *   worker_completed: bool,
     *   super_inputs: int,
     *   super_pending: bool,
     *   super_approved: bool,
     *   worker_responses: array<int, int>,
     *   super_responses: array<int, int>
     * }
     */
    public function getWorkOrderAutocontrolStatus(int $workerOtId, int $equipoTypeId): array
    {
        // Rebobinadora (equipo_type_id = 12) no utiliza autocontrol
        if ($equipoTypeId === 12 || $workerOtId <= 0) {
            return [
                'has_points' => false,
                'points' => [],
                'goto_autocontrol' => false,
                'worker_completed' => true,
                'super_inputs' => 0,
                'super_pending' => false,
                'super_approved' => true,
                'lider_inputs' => 0,
                'lider_pending' => false,
                'lider_approved' => false,
                'worker_responses' => [],
                'super_responses' => [],
                'lider_responses' => [],
            ];
        }

        $points = $this->listAutocontrolPoints($equipoTypeId);
        $hasPoints = count($points) > 0;

        if (!$hasPoints) {
            return [
                'has_points' => false,
                'points' => [],
                'goto_autocontrol' => false,
                'worker_completed' => true,
                'super_inputs' => 0,
                'super_pending' => false,
                'super_approved' => true,
                'lider_inputs' => 0,
                'lider_pending' => false,
                'lider_approved' => false,
                'worker_responses' => [],
                'super_responses' => [],
                'lider_responses' => [],
            ];
        }

        $workerResponses = [];
        try {
            $stmtW = $this->erpPdo->prepare(
                "SELECT ctr_acid, ctr_res FROM prod_worker_ot_autocontrol
                 WHERE ctr_init_id = :ot_id AND ctr_type = 'worker'"
            );
            $stmtW->execute([':ot_id' => $workerOtId]);
            foreach ($stmtW->fetchAll() as $r) {
                $workerResponses[(int)$r['ctr_acid']] = (int)$r['ctr_res'];
            }
        } catch (Throwable) {
            $workerResponses = [];
        }

        $workerCompleted = !empty($workerResponses);
        foreach ($points as $p) {
            if (empty($workerResponses[(int)$p['id']])) {
                $workerCompleted = false;
                break;
            }
        }

        $superRows = [];
        $liderRows = [];
        try {
            // Registros de supervisor
            $stmtS = $this->erpPdo->prepare(
                "SELECT ctr_acid, ctr_res, ctr_ctrusr, ctr_ctrdat FROM prod_worker_ot_autocontrol
                 WHERE ctr_init_id = :ot_id AND ctr_type = 'supervisor'"
            );
            $stmtS->execute([':ot_id' => $workerOtId]);
            $superRows = $stmtS->fetchAll() ?: [];

            // Registros de líder de área
            $stmtL = $this->erpPdo->prepare(
                "SELECT ctr_acid, ctr_res, ctr_ctrusr, ctr_ctrdat FROM prod_worker_ot_autocontrol
                 WHERE ctr_init_id = :ot_id AND ctr_type = 'lider'"
            );
            $stmtL->execute([':ot_id' => $workerOtId]);
            $liderRows = $stmtL->fetchAll() ?: [];
        } catch (Throwable) {
            $superRows = [];
            $liderRows = [];
        }

        // Evaluar estado del Líder de Área
        $liderInputs = count($liderRows);
        $liderResponses = [];
        $liderHasUnapproved = false;
        foreach ($liderRows as $lr) {
            $liderResponses[(int)$lr['ctr_acid']] = (int)$lr['ctr_res'];
            if ((int)$lr['ctr_res'] === 0) {
                $liderHasUnapproved = true;
            }
        }
        $liderPending = ($liderInputs > 0 && $liderHasUnapproved);
        $liderApproved = ($liderInputs > 0 && !$liderHasUnapproved);

        // Evaluar estado del Supervisor
        $superInputs = count($superRows);
        $superResponses = [];
        $superHasUnapproved = false;
        foreach ($superRows as $sr) {
            $superResponses[(int)$sr['ctr_acid']] = (int)$sr['ctr_res'];
            if ((int)$sr['ctr_res'] === 0) {
                $superHasUnapproved = true;
            }
        }
        $superPending = ($superInputs > 0 && $superHasUnapproved);
        $superApproved = ($superInputs > 0 && !$superHasUnapproved);

        // La OT requiere pantalla de autocontrol/aprobación mientras el supervisor no haya completado la aprobación
        $gotoAutocontrol = !$superApproved;

        return [
            'has_points' => true,
            'points' => $points,
            'goto_autocontrol' => $gotoAutocontrol,
            'worker_completed' => $workerCompleted,
            'super_inputs' => $superInputs,
            'super_pending' => $superPending,
            'super_approved' => $superApproved,
            'lider_inputs' => $liderInputs,
            'lider_pending' => $liderPending,
            'lider_approved' => $liderApproved,
            'worker_responses' => $workerResponses,
            'super_responses' => $superResponses,
            'lider_responses' => $liderResponses,
        ];
    }

    /**
     * El operario confirma sus puntos de autocontrol e informa al supervisor.
     */
    public function sendAutocontrolToSupervisor(int $workerOtId, array $selectedAcids, int $userId): array
    {
        if ($workerOtId <= 0) {
            return ['ok' => false, 'error' => 'OT inválida.'];
        }

        $now = time();
        try {
            // Guardar confirmaciones del operario
            $delW = $this->erpPdo->prepare("DELETE FROM prod_worker_ot_autocontrol WHERE ctr_init_id = :ot_id AND ctr_type = 'worker'");
            $delW->execute([':ot_id' => $workerOtId]);

            $insW = $this->erpPdo->prepare(
                "INSERT INTO prod_worker_ot_autocontrol (ctr_init_id, ctr_type, ctr_acid, ctr_res, ctr_ctrusr, ctr_ctrdat)
                 VALUES (:ot_id, 'worker', :acid, 1, :usr, :dat)"
            );
            foreach ($selectedAcids as $acid) {
                $acid = (int)$acid;
                if ($acid > 0) {
                    $insW->execute([':ot_id' => $workerOtId, ':acid' => $acid, ':usr' => $userId, ':dat' => $now]);
                }
            }

            // Preparar requerimiento de validación para el supervisor (ctr_res = 0)
            // Limpiar registros previos tanto de supervisor como de líder para evitar estados inconsistentes
            $delS = $this->erpPdo->prepare("DELETE FROM prod_worker_ot_autocontrol WHERE ctr_init_id = :ot_id AND ctr_type IN ('supervisor', 'lider')");
            $delS->execute([':ot_id' => $workerOtId]);

            $insS = $this->erpPdo->prepare(
                "INSERT INTO prod_worker_ot_autocontrol (ctr_init_id, ctr_type, ctr_acid, ctr_res, ctr_ctrusr)
                 VALUES (:ot_id, 'supervisor', :acid, 0, 0)"
            );
            foreach ($selectedAcids as $acid) {
                $acid = (int)$acid;
                if ($acid > 0) {
                    $insS->execute([':ot_id' => $workerOtId, ':acid' => $acid]);
                }
            }

            // Cerrar el evento de alistamiento (apertura) si estuviese abierto
            $updEvt = $this->erpPdo->prepare(
                "UPDATE prod_worker_ot_events
                 SET evt_enddat = :now
                 WHERE evt_prod_worker_otid = :ot_id AND evt_type = 'apertura' AND (evt_enddat IS NULL OR evt_enddat = 0)"
            );
            $updEvt->execute([':now' => $now, ':ot_id' => $workerOtId]);

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * El operario informa al Líder de Área (especialmente en Flexografía).
     */
    public function sendAutocontrolToLider(int $workerOtId, array $selectedAcids, int $userId): array
    {
        if ($workerOtId <= 0) {
            return ['ok' => false, 'error' => 'OT inválida.'];
        }

        $now = time();
        try {
            // Guardar confirmaciones del operario
            $delW = $this->erpPdo->prepare("DELETE FROM prod_worker_ot_autocontrol WHERE ctr_init_id = :ot_id AND ctr_type = 'worker'");
            $delW->execute([':ot_id' => $workerOtId]);

            $insW = $this->erpPdo->prepare(
                "INSERT INTO prod_worker_ot_autocontrol (ctr_init_id, ctr_type, ctr_acid, ctr_res, ctr_ctrusr, ctr_ctrdat)
                 VALUES (:ot_id, 'worker', :acid, 1, :usr, :dat)"
            );
            foreach ($selectedAcids as $acid) {
                $acid = (int)$acid;
                if ($acid > 0) {
                    $insW->execute([':ot_id' => $workerOtId, ':acid' => $acid, ':usr' => $userId, ':dat' => $now]);
                }
            }

            // Preparar requerimiento para el líder (ctr_res = 0)
            // Limpiar registros previos tanto de líder como de supervisor para evitar estados inconsistentes
            $delL = $this->erpPdo->prepare("DELETE FROM prod_worker_ot_autocontrol WHERE ctr_init_id = :ot_id AND ctr_type IN ('supervisor', 'lider')");
            $delL->execute([':ot_id' => $workerOtId]);

            $insL = $this->erpPdo->prepare(
                "INSERT INTO prod_worker_ot_autocontrol (ctr_init_id, ctr_type, ctr_acid, ctr_res, ctr_ctrusr)
                 VALUES (:ot_id, 'lider', :acid, 0, 0)"
            );
            foreach ($selectedAcids as $acid) {
                $acid = (int)$acid;
                if ($acid > 0) {
                    $insL->execute([':ot_id' => $workerOtId, ':acid' => $acid]);
                }
            }

            // Cerrar el evento de alistamiento (apertura) si estuviese abierto
            $updEvt = $this->erpPdo->prepare(
                "UPDATE prod_worker_ot_events
                 SET evt_enddat = :now
                 WHERE evt_prod_worker_otid = :ot_id AND evt_type = 'apertura' AND (evt_enddat IS NULL OR evt_enddat = 0)"
            );
            $updEvt->execute([':now' => $now, ':ot_id' => $workerOtId]);

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * El Líder de Área valida las confirmaciones con su usuario y contraseña.
     * Al aprobar la revisión, se actualizan los registros de 'lider' y SE HABILITA
     * automáticamente el requerimiento pendiente para que el SUPERVISOR apruebe la partida.
     */
    public function approveAutocontrolLider(int $workerOtId, array $selectedAcids, string $username, string $password): array
    {
        if ($workerOtId <= 0) {
            return ['ok' => false, 'error' => 'ID de OT inválido.'];
        }

        $username = trim($username);
        $password = trim($password);
        if ($username === '' || $password === '') {
            return ['ok' => false, 'error' => 'Debe ingresar usuario y contraseña del líder de área.'];
        }

        try {
            $sql = "SELECT t1.id, t1.user_type
                    FROM user t1
                    WHERE t1.user_login = :login
                      AND t1.user_pass = :pass
                      AND t1.user_status = 1
                      AND (
                          t1.user_type = 1
                          OR (
                              SELECT count(*)
                              FROM user_group t2
                              WHERE t2.user_id = t1.id
                                AND t2.group_id IN (1, 10, 11, 31)
                          ) > 0
                      )
                    LIMIT 1";

            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute([
                ':login' => $username,
                ':pass' => md5($password),
            ]);
            $liderUser = $stmt->fetch();

            if (!is_array($liderUser) || (int)$liderUser['id'] <= 0) {
                return ['ok' => false, 'error' => 'Usuario o contraseña no válido para aprobación de líder de área.'];
            }

            $liderId = (int)$liderUser['id'];
            $now = time();

            // 1. Actualizar registros de 'lider' a aprobado (ctr_res = 1)
            $updL = $this->erpPdo->prepare(
                "UPDATE prod_worker_ot_autocontrol
                 SET ctr_res = 1, ctr_ctrusr = :lider_id, ctr_ctrdat = :now
                 WHERE ctr_init_id = :ot_id AND ctr_type = 'lider'"
            );
            $updL->execute([
                ':lider_id' => $liderId,
                ':now' => $now,
                ':ot_id' => $workerOtId,
            ]);

            // 2. Limpiar registros de 'supervisor' previos para esta OT
            $delS = $this->erpPdo->prepare(
                "DELETE FROM prod_worker_ot_autocontrol WHERE ctr_init_id = :ot_id AND ctr_type = 'supervisor'"
            );
            $delS->execute([':ot_id' => $workerOtId]);

            // 3. Crear requerimientos pendientes para el SUPERVISOR (ctr_type = 'supervisor', ctr_res = 0)
            $stmtPts = $this->erpPdo->prepare(
                "SELECT ctr_acid FROM prod_worker_ot_autocontrol WHERE ctr_init_id = :ot_id AND ctr_type = 'lider'"
            );
            $stmtPts->execute([':ot_id' => $workerOtId]);
            $acidRows = $stmtPts->fetchAll(PDO::FETCH_COLUMN) ?: [];

            if (empty($acidRows) && !empty($selectedAcids)) {
                $acidRows = $selectedAcids;
            }

            $insS = $this->erpPdo->prepare(
                "INSERT INTO prod_worker_ot_autocontrol (ctr_init_id, ctr_type, ctr_acid, ctr_res, ctr_ctrusr)
                 VALUES (:ot_id, 'supervisor', :acid, 0, 0)"
            );
            foreach ($acidRows as $acid) {
                $acid = (int)$acid;
                if ($acid > 0) {
                    $insS->execute([':ot_id' => $workerOtId, ':acid' => $acid]);
                }
            }

            // NOTA: NO se cambia wok_status a 1 aquí, porque falta la aprobación del SUPERVISOR.
            return ['ok' => true, 'lider_id' => $liderId];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * El supervisor valida las confirmaciones con su usuario y contraseña, aprobando la partida.
     */
    public function approveAutocontrolPartida(int $workerOtId, array $selectedAcids, string $username, string $password): array
    {
        if ($workerOtId <= 0) {
            return ['ok' => false, 'error' => 'ID de OT inválido.'];
        }

        $username = trim($username);
        $password = trim($password);
        if ($username === '' || $password === '') {
            return ['ok' => false, 'error' => 'Debe ingresar usuario y contraseña del supervisor.'];
        }

        try {
            $sql = "SELECT t1.id, t1.user_type
                    FROM user t1
                    WHERE t1.user_login = :login
                      AND t1.user_pass = :pass
                      AND t1.user_status = 1
                      AND (
                          t1.user_type = 1
                          OR (
                              SELECT count(*)
                              FROM user_group t2
                              WHERE t2.user_id = t1.id
                                AND t2.group_id IN (1, 10, 11, 31)
                          ) > 0
                      )
                    LIMIT 1";

            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute([
                ':login' => $username,
                ':pass' => md5($password),
            ]);
            $superUser = $stmt->fetch();

            if (!is_array($superUser) || (int)$superUser['id'] <= 0) {
                return ['ok' => false, 'error' => 'Usuario o contraseña no válido para aprobación de supervisor.'];
            }

            $superId = (int)$superUser['id'];
            $now = time();

            // Actualizar a aprobado (ctr_res = 1) los registros de 'supervisor'
            $upd = $this->erpPdo->prepare(
                "UPDATE prod_worker_ot_autocontrol
                 SET ctr_res = 1, ctr_ctrusr = :super_id, ctr_ctrdat = :now
                 WHERE ctr_init_id = :ot_id AND ctr_type = 'supervisor'"
            );
            $upd->execute([
                ':super_id' => $superId,
                ':now' => $now,
                ':ot_id' => $workerOtId,
            ]);

            // Asegurar que siempre existan registros aprobados de tipo 'supervisor'
            $checkSup = $this->erpPdo->prepare(
                "SELECT COUNT(*) FROM prod_worker_ot_autocontrol WHERE ctr_init_id = :ot_id AND ctr_type = 'supervisor' AND ctr_res = 1"
            );
            $checkSup->execute([':ot_id' => $workerOtId]);
            $hasSup = (int)$checkSup->fetchColumn() > 0;

            if (!$hasSup && !empty($selectedAcids)) {
                $ins = $this->erpPdo->prepare(
                    "INSERT INTO prod_worker_ot_autocontrol (ctr_init_id, ctr_type, ctr_acid, ctr_res, ctr_ctrusr, ctr_ctrdat)
                     VALUES (:ot_id, 'supervisor', :acid, 1, :super_id, :now)"
                );
                foreach ($selectedAcids as $acid) {
                    $acid = (int)$acid;
                    if ($acid > 0) {
                        $ins->execute([
                            ':ot_id' => $workerOtId,
                            ':acid' => $acid,
                            ':super_id' => $superId,
                            ':now' => $now,
                        ]);
                    }
                }
            }

            // Asegurar que el evento de alistamiento quede cerrado
            $updEvt = $this->erpPdo->prepare(
                "UPDATE prod_worker_ot_events
                 SET evt_enddat = :now
                 WHERE evt_prod_worker_otid = :ot_id AND evt_type = 'apertura' AND (evt_enddat IS NULL OR evt_enddat = 0)"
            );
            $updEvt->execute([':now' => $now, ':ot_id' => $workerOtId]);

            // Habilitar estado de la OT a producción (1)
            $updOt = $this->erpPdo->prepare(
                "UPDATE prod_worker_ot SET wok_status = 1 WHERE id = :ot_id AND wok_status = 0"
            );
            $updOt->execute([':ot_id' => $workerOtId]);

            return ['ok' => true, 'supervisor_id' => $superId];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }


}

#[AllowDynamicProperties]
class QualityAutocontrolService
{
    use QualityAutocontrolServiceTrait;

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
