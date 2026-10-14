<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Servicio de Producción · Planta, Máquinas, Operarios, OTs y Eventos
 * (src/ProductionService.php)
 * =============================================================================
 *
 * Este servicio recrea, moderniza y centraliza toda la lógica operativa que
 * residía en el sistema legacy (_BACKUP20260723: prodwrk.php y libs.prodwrk).
 *
 * Responsabilidades:
 * -----------------
 * 1. MÁQUINAS Y TURNOS:
 *    - Registro de apertura y cierre de turno en máquina (tabla `prod_worker_init`).
 *    - Pausa automática de máquinas previas al alternar de puesto (`win_status = 3`).
 *    - Asignaciones de turno planificadas (`turnos_config_assign`, `turnos`, `equipo`).
 *
 * 2. PROGRAMACIÓN Y ARRANQUE DE ÓRDENES DE TRABAJO (OTs):
 *    - Lectura de agenda programada por equipo y fecha (`prod_agenda`, `prod_header`, `orders`).
 *    - Inicio formal del trabajo por el operario (`prod_worker_ot` con `wok_status = 1`).
 *    - Recuperación del contexto completo técnico: medidas, clichés, colores, requerimiento.
 *
 * 3. BITÁCORA DE EVENTOS PRODUCTIVOS (`prod_worker_ot_events`):
 *    - Aperturas / Setup de máquina: cambio de medidas, clichés, rodillos (`evt_type = 'apertura'`).
 *    - Producción: registro de metros máquina, metros lineales, unidades y kilos (`evt_type = 'prod'`).
 *    - Pausas operativas: catalogadas por causas (`prod_pause_types`, `evt_type = 'pause'`).
 *    - Mantenciones mecánicas/eléctricas: catalogadas por fallas (`prod_repairtypes`, `evt_type = 'mantencion'`).
 *    - Consumos de insumos / bobinas: telas, tintas, adhesivos (`evt_type = 'materiales'`).
 *
 * 4. CALIDAD Y AUTOCONTROL (`prod_worker_ot_autocontrol`):
 *    - Lista de verificación por tipo de máquina (`equipo_puntos_autocontrol`).
 *    - Guardado de confirmación de puntos de control por el operario y supervisor.
 *
 * 5. CIERRE DE ORDEN DE TRABAJO:
 *    - Verificación estricta de credenciales de supervisor/administrador (rol 31 o tipo 1).
 *    - Cierre de la OT (`wok_status = 2`, `wok_enddat = timestamp`).
 *
 * 6. MONITOREO DE PLANTA:
 *    - Visualización en tiempo real de OTs en curso por equipo y operario.
 *    - Historial consolidado de órdenes terminadas con totales de producción y mermas.
 *
 * 7. PREPARADO PARA TRAZABILIDAD:
 *    - Métodos listos para recibir identificación de bobina hija/madre, pesaje de balanza y etiquetas.
 */
final class ProductionService
{
    /** @var PDO Conexión a la base de datos principal de producción (ERP / unibagqa) */
    private PDO $erpPdo;

    /** @var PDO|null Conexión opcional a la base de datos local de trazabilidad (TRZ) */
    private ?PDO $trzPdo;

    public function __construct(PDO $erpPdo, ?PDO $trzPdo = null)
    {
        $this->erpPdo = $erpPdo;
        $this->trzPdo = $trzPdo;
    }

    // =========================================================================
    // SECCIÓN 1: GESTIÓN DE MÁQUINAS Y APERTURA DE TURNO (prod_worker_init)
    // =========================================================================

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

    // =========================================================================
    // SECCIÓN 2: GESTIÓN DE AGENDA Y ARRANQUE DE OTs (prod_agenda / prod_worker_ot)
    // =========================================================================

    /**
     * Retorna la OT actualmente activa para el operario en la máquina abierta.
     *
     * @param int $workerId ID del operario
     * @param int $plantaId ID de planta
     * @return array<string, mixed>|null
     */
    public function getOpenWorkerOt(int $workerId, int $plantaId): ?array
    {
        $openInit = $this->getOpenWorkerInit($workerId, $plantaId);
        if ($openInit === null) {
            return null;
        }

        $sql = "SELECT t1.*, t4.prd_number, t4.prd_reqid, t3.ag_equipo_id, t3.ag_amount
                FROM prod_worker_ot t1
                INNER JOIN prod_worker_init t2 ON t1.wok_init_id = t2.id
                INNER JOIN prod_agenda t3 ON t1.wok_ag_id = t3.id
                INNER JOIN prod_header t4 ON t3.ag_prdid = t4.id
                WHERE t1.wok_init_id = :init_id
                  AND t1.wok_status = 1
                ORDER BY t1.id DESC
                LIMIT 1";

        $stmt = $this->erpPdo->prepare($sql);
        $stmt->execute([':init_id' => (int)$openInit['id']]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Retorna la OT activa filtrando por ID de agenda específica.
     *
     * @param int $workerId ID del operario
     * @param int $plantaId ID de planta
     * @param int $agId ID de la agenda (prod_agenda.id)
     * @return array<string, mixed>|null
     */
    public function getOpenWorkerOtByAgId(int $workerId, int $plantaId, int $agId): ?array
    {
        $whereWrk = $workerId > 0 ? " AND t2.win_wrkid = :wrk_id" : "";
        $wherePlanta = $plantaId > 0 ? " AND t2.win_plantaid = :planta_id" : "";
        $sql = "SELECT t1.*, t4.prd_number, t4.prd_reqid, t3.ag_equipo_id, t3.ag_amount
                FROM prod_worker_ot t1
                INNER JOIN prod_worker_init t2 ON t1.wok_init_id = t2.id
                INNER JOIN prod_agenda t3 ON t1.wok_ag_id = t3.id
                INNER JOIN prod_header t4 ON t3.ag_prdid = t4.id
                WHERE t1.wok_ag_id = :ag_id
                  AND t1.wok_status = 1
                  {$whereWrk}
                  {$wherePlanta}
                ORDER BY t1.id DESC
                LIMIT 1";

        $stmt = $this->erpPdo->prepare($sql);
        $params = [':ag_id' => $agId];
        if ($workerId > 0) {
            $params[':wrk_id'] = $workerId;
        }
        if ($plantaId > 0) {
            $params[':planta_id'] = $plantaId;
        }
        $stmt->execute($params);
        $row = $stmt->fetch();

        if (is_array($row)) {
            return $row;
        }

        // Fallback 1: Si win_plantaid fue 0 o difiere, buscar por operario y agenda
        if ($workerId > 0) {
            $sqlWrk = "SELECT t1.*, t4.prd_number, t4.prd_reqid, t3.ag_equipo_id, t3.ag_amount
                       FROM prod_worker_ot t1
                       INNER JOIN prod_worker_init t2 ON t1.wok_init_id = t2.id
                       INNER JOIN prod_agenda t3 ON t1.wok_ag_id = t3.id
                       INNER JOIN prod_header t4 ON t3.ag_prdid = t4.id
                       WHERE t1.wok_ag_id = :ag_id
                         AND t1.wok_status = 1
                         AND t2.win_wrkid = :wrk_id
                       ORDER BY t1.id DESC
                       LIMIT 1";
            $stmtWrk = $this->erpPdo->prepare($sqlWrk);
            $stmtWrk->execute([':ag_id' => $agId, ':wrk_id' => $workerId]);
            $rowWrk = $stmtWrk->fetch();
            if (is_array($rowWrk)) {
                return $rowWrk;
            }
        }

        // Fallback 2: Cualquier OT activa abierta para esta agenda (wok_status = 1)
        $sqlAg = "SELECT t1.*, t4.prd_number, t4.prd_reqid, t3.ag_equipo_id, t3.ag_amount
                  FROM prod_worker_ot t1
                  INNER JOIN prod_worker_init t2 ON t1.wok_init_id = t2.id
                  INNER JOIN prod_agenda t3 ON t1.wok_ag_id = t3.id
                  INNER JOIN prod_header t4 ON t3.ag_prdid = t4.id
                  WHERE t1.wok_ag_id = :ag_id
                    AND t1.wok_status = 1
                  ORDER BY t1.id DESC
                  LIMIT 1";
        $stmtAg = $this->erpPdo->prepare($sqlAg);
        $stmtAg->execute([':ag_id' => $agId]);
        $rowAg = $stmtAg->fetch();
        if (is_array($rowAg)) {
            return $rowAg;
        }

        // Fallback 3: Última OT registrada para esta agenda
        $sqlLast = "SELECT t1.*, t4.prd_number, t4.prd_reqid, t3.ag_equipo_id, t3.ag_amount
                    FROM prod_worker_ot t1
                    INNER JOIN prod_agenda t3 ON t1.wok_ag_id = t3.id
                    INNER JOIN prod_header t4 ON t3.ag_prdid = t4.id
                    WHERE t1.wok_ag_id = :ag_id
                    ORDER BY t1.id DESC
                    LIMIT 1";
        $stmtLast = $this->erpPdo->prepare($sqlLast);
        $stmtLast->execute([':ag_id' => $agId]);
        $rowLast = $stmtLast->fetch();

        return is_array($rowLast) ? $rowLast : null;
    }

    /**
     * Lista las órdenes de trabajo programadas en agenda (`prod_agenda`) para la máquina indicada.
     *
     * @param int $equipoId ID de máquina
     * @param int $plantaId ID de planta
     * @return array<int, array<string, mixed>>
     */
    public function listScheduledOrdersForMachine(int $equipoId, int $plantaId, ?string $dateFilter = null): array
    {
        // En agenda legacy existen planificaciones válidas con ag_plantaid = 0 o NULL.
        // Si filtramos solo por la planta exacta, la OT no aparece en la consola nueva aunque
        // ya esté correctamente asignada a la máquina en el ERP antiguo.
        $wherePlanta = $plantaId > 0 ? " AND (t0.ag_plantaid = :planta_id OR t0.ag_plantaid IS NULL OR t0.ag_plantaid = 0)" : "";
        $whereDate = "";
        $params = [':equipo_id' => $equipoId];
        if ($plantaId > 0) {
            $params[':planta_id'] = $plantaId;
        }

        if (!empty($dateFilter) && $dateFilter !== 'all') {
            $normalizedDate = trim($dateFilter);
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $normalizedDate, $mD)) {
                $day = (int)$mD[3];
                $month = (int)$mD[2];
                $year = (int)$mD[1];
                $normalizedDate = sprintf('%02d.%02d.%04d', $day, $month, $year);
            } elseif (preg_match('/^(\d{1,2})[\.\/-](\d{1,2})[\.\/-](\d{4})$/', $normalizedDate, $mD)) {
                $day = (int)$mD[1];
                $month = (int)$mD[2];
                $year = (int)$mD[3];
                $normalizedDate = sprintf('%02d.%02d.%04d', $day, $month, $year);
            } else {
                $day = (int)date('d');
                $month = (int)date('m');
                $year = (int)date('Y');
                $normalizedDate = date('d.m.Y');
            }

            $dateStart = mktime(0, 0, 0, $month, $day, $year);
            $dateEnd = mktime(23, 59, 59, $month, $day, $year);

            $whereDate = " AND (t0.ag_date = :target_date OR (t0.ag_date_stamp >= :date_start AND t0.ag_date_stamp <= :date_end))";
            $params[':target_date'] = $normalizedDate;
            $params[':date_start'] = $dateStart;
            $params[':date_end'] = $dateEnd;
        }

        $sql = "SELECT DISTINCT t0.id AS ag_id, t0.ag_date, t0.ag_amount, t0.ag_status, t0.ag_active,
                               t0.pulpo_tot_frente, t0.pulpo_tot_dorso, t0.pulpo_finished_bags,
                               t0.pulpo_surplus_frente, t0.pulpo_surplus_dorso,
                               t3x.id AS prdid, t3x.prd_number,
                               t1.id AS req_id, t1.req_number, t1.req_production_initdate, t1.req_status,
                               t1.req_operador_tela_width, t1.req_rebo_type, t1.req_rebo_cc_genrefid,
                               t1.req_infoaddprd_cliche_ubicacion, t1.req_infoaddprd_cliche_codigo,
                               t1.req_operador_mermaperc, t1.req_operador_bastidoramt, t1.req_solic_devprints_poltype,
                               t2.company_short, t3.shop_name, t4.cust_name,
                               t2x.item_number_prod, t2x.item_title, t2x.item_prodcalc_fuelle_act, t1x.item_amount,
                               t1x.fab_printtype, t1x.fab_type, t1x.fab_med_width, t1x.fab_med_height,
                               t1x.fab_med_fuelle, t1x.fab_design_imagehash, t1x.fab_design_name, t1x.item_img_hash,
                               t1x.fab_print_colordesc_1, t1x.fab_print_colordesc_2, t1x.fab_print_colordesc_3,
                               t1x.fab_print_colordesc_4, t1x.fab_print_colordesc_5, t1x.fab_print_colordesc_6,
                               teq.equipo_name, teqt.type_ant_title, teq.equipo_type_id, teq.equipo_prod_isprinter_flexo, teq.equipo_prod_isprinter_seri,
                               (SELECT COALESCE(SUM(t_ev.evt_amount), 0) FROM prod_worker_ot t_ot INNER JOIN prod_worker_ot_events t_ev ON t_ev.evt_prod_worker_otid = t_ot.id WHERE t_ot.wok_ag_id = t0.id AND t_ev.evt_type = 'prod' AND t_ev.evt_status > 0) AS total_produced
                FROM prod_agenda t0
                LEFT OUTER JOIN prod_header t3x ON t0.ag_prdid = t3x.id
                INNER JOIN orders t1 ON t0.ag_reqid = t1.id
                LEFT OUTER JOIN company_data t2 ON t1.req_company_id = t2.id
                LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id = t3.id
                LEFT OUTER JOIN customer t4 ON t1.req_cust_id = t4.id
                LEFT OUTER JOIN orders_items t1x ON t1.id = t1x.req_id
                LEFT OUTER JOIN item t2x ON t1x.item_id = t2x.id
                LEFT OUTER JOIN equipo teq ON t0.ag_equipo_id = teq.id
                LEFT OUTER JOIN equipo_type teqt ON teq.equipo_type_id = teqt.id
                WHERE t0.ag_equipo_id = :equipo_id
                  {$wherePlanta}
                  AND t0.ag_status = 1
                  {$whereDate}
                ORDER BY t0.ag_date ASC, t0.ag_order ASC, t0.id ASC
                LIMIT 50";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll();
            foreach ($orders as &$ord) {
                $isRebo = ((int)($ord['req_rebo_cc_genrefid'] ?? 0) > 0)
                    || ((int)($ord['equipo_type_id'] ?? 0) === 12)
                    || stripos((string)($ord['equipo_name'] ?? ''), 'REBO') !== false
                    || stripos((string)($ord['type_ant_title'] ?? ''), 'REBOBIN') !== false;
                if ($isRebo) {
                    $ord['rebo_distribucion'] = $this->getReboDistributionText((int)$ord['req_id'], (float)($ord['req_operador_tela_width'] ?? 0));
                }
            }
            unset($ord);
            $this->attachFlexoMetrics($orders, $equipoId);
            $this->attachSeriMetrics($orders);
            return $orders;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Lista las órdenes de trabajo activas en ERP que NO han entrado en la planificación de la máquina indicada
     * (sin agenda asignada a este equipo o sin agenda general), permitiendo al operario o supervisor incorporarlas.
     *
     * @param int $equipoId ID de máquina
     * @param int $plantaId ID de planta
     * @param string|null $search Término de búsqueda opcional (OT, CC, cliente, producto)
     * @param int $limit Límite de resultados
     * @return array<int, array<string, mixed>>
     */
    public function listUnscheduledOrdersForMachine(int $equipoId, int $plantaId, ?string $search = null, int $limit = 60): array
    {
        if ($equipoId <= 0) {
            return [];
        }

        try {
            $stmtEq = $this->erpPdo->prepare("SELECT id, equipo_name, equipo_type_id, equipo_prod_isprinter_flexo, equipo_prod_isprinter_seri FROM equipo WHERE id = :id LIMIT 1");
            $stmtEq->execute([':id' => $equipoId]);
            $eq = $stmtEq->fetch(PDO::FETCH_ASSOC);
            if (!$eq) {
                return [];
            }

            $eqTypeId = (int)($eq['equipo_type_id'] ?? 0);
            $isFlexo = ($eqTypeId === 7 || (int)($eq['equipo_prod_isprinter_flexo'] ?? 0) === 1);
            $isSeri = ($eqTypeId === 11 || (int)($eq['equipo_prod_isprinter_seri'] ?? 0) === 1);
            $isRebo = ($eqTypeId === 12);

            $whereType = "";
            if ($isFlexo) {
                $whereType = " AND (t1x.fab_printtype LIKE '%FLEX%' OR t1x.fab_printtype = 'FLX')";
            } elseif ($isSeri) {
                $whereType = " AND (t1x.fab_printtype LIKE '%SERI%' OR t1x.fab_printtype = 'SER')";
            } elseif ($isRebo) {
                $whereType = " AND (t1.req_rebo_type IS NOT NULL OR t1.req_rebo_cc_genrefid > 0 OR t1.req_operador_tela_width > 0)";
            }

            $whereSearch = "";
            $params = [':equipo_id' => $equipoId];
            if ($search !== null && trim($search) !== '') {
                $cleanSearch = '%' . trim($search) . '%';
                $whereSearch = " AND (t3x.prd_number LIKE :s1 OR t1.req_number LIKE :s2 OR t4.cust_name LIKE :s3 OR t2x.item_title LIKE :s4 OR t1x.fab_design_name LIKE :s5)";
                $params[':s1'] = $cleanSearch;
                $params[':s2'] = $cleanSearch;
                $params[':s3'] = $cleanSearch;
                $params[':s4'] = $cleanSearch;
                $params[':s5'] = $cleanSearch;
            }

            $sql = "SELECT DISTINCT t3x.id AS prdid, t3x.prd_number,
                                   t1.id AS req_id, t1.req_number, t1.req_production_initdate, t1.req_status,
                                   t1.req_operador_tela_width, t1.req_rebo_type, t1.req_rebo_cc_genrefid,
                                   t1.req_infoaddprd_cliche_ubicacion, t1.req_infoaddprd_cliche_codigo,
                                   t1.req_operador_mermaperc, t1.req_operador_bastidoramt, t1.req_solic_devprints_poltype,
                                   t2.company_short, t3.shop_name, t4.cust_name,
                                   t2x.item_number_prod, t2x.item_title, t2x.item_prodcalc_fuelle_act, t1x.item_amount,
                                   t1x.fab_printtype, t1x.fab_type, t1x.fab_med_width, t1x.fab_med_height,
                                   t1x.fab_med_fuelle, t1x.fab_design_imagehash, t1x.fab_design_name, t1x.item_img_hash,
                                   t1x.fab_print_colordesc_1, t1x.fab_print_colordesc_2, t1x.fab_print_colordesc_3,
                                   t1x.fab_print_colordesc_4, t1x.fab_print_colordesc_5, t1x.fab_print_colordesc_6,
                                   (SELECT COUNT(*) FROM prod_agenda pa_all WHERE pa_all.ag_prdid = t3x.id AND pa_all.ag_status = 1) AS total_agendas_activas
                    FROM prod_header t3x
                    INNER JOIN orders t1 ON t3x.prd_reqid = t1.id
                    LEFT OUTER JOIN company_data t2 ON t1.req_company_id = t2.id
                    LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id = t3.id
                    LEFT OUTER JOIN customer t4 ON t1.req_cust_id = t4.id
                    INNER JOIN orders_items t1x ON t1.id = t1x.req_id
                    LEFT OUTER JOIN item t2x ON t1x.item_id = t2x.id
                    WHERE t3x.prd_status >= 2
                      AND t1.req_status > 1
                      {$whereType}
                      {$whereSearch}
                      AND NOT EXISTS (
                        SELECT 1 FROM prod_agenda pa
                        WHERE pa.ag_prdid = t3x.id
                          AND pa.ag_equipo_id = :equipo_id
                          AND pa.ag_status = 1
                      )
                    ORDER BY t1.req_production_initdate DESC, t3x.id DESC
                    LIMIT {$limit}";

            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($orders as &$ord) {
                $ord['ag_id'] = 0;
                $ord['ag_amount'] = (float)($ord['item_amount'] ?? 0);
                $ord['ag_date'] = '-';
                $ord['ag_active'] = 0;
                $ord['equipo_name'] = (string)($eq['equipo_name'] ?? '');
                $ord['equipo_type_id'] = $eqTypeId;
                $ord['equipo_prod_isprinter_flexo'] = (int)($eq['equipo_prod_isprinter_flexo'] ?? 0);
                $ord['equipo_prod_isprinter_seri'] = (int)($eq['equipo_prod_isprinter_seri'] ?? 0);
                $ord['is_unscheduled'] = true;

                $isReboItem = ((int)($ord['req_rebo_cc_genrefid'] ?? 0) > 0)
                    || ($eqTypeId === 12)
                    || stripos((string)($eq['equipo_name'] ?? ''), 'REBO') !== false;
                if ($isReboItem) {
                    $ord['rebo_distribucion'] = $this->getReboDistributionText((int)$ord['req_id'], (float)($ord['req_operador_tela_width'] ?? 0));
                }
            }
            unset($ord);

            $this->attachFlexoMetrics($orders, $equipoId);
            $this->attachSeriMetrics($orders);

            return $orders;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Incorpora a la planificación e inicia formalmente una Orden de Trabajo que no estaba agendada en la máquina.
     * Crea el registro correspondiente en `prod_agenda` y arranca `prod_worker_ot`.
     *
     * @param int $prdId ID de prod_header
     * @param int $initId ID de sesión de máquina (prod_worker_init)
     * @param int $equipoId ID de máquina
     * @param int $plantaId ID de planta
    /**
     * Asegura o crea el registro en prod_agenda para una OT no planificada en una máquina.
     * Retorna el id de prod_agenda (ag_id) o 0 en caso de error.
     */
    public function ensureAgendaForUnscheduledOrder(int $prdId, int $equipoId, int $plantaId, ?float $amount = null): int
    {
        if ($prdId <= 0 || $equipoId <= 0) {
            return 0;
        }

        try {
            // Verificar si ya existe un registro de agenda activo para esta máquina y OT
            $stmtCheckAg = $this->erpPdo->prepare(
                "SELECT id FROM prod_agenda
                 WHERE ag_prdid = :prdid AND ag_equipo_id = :equipoid AND ag_status = 1
                 ORDER BY id DESC LIMIT 1"
            );
            $stmtCheckAg->execute([':prdid' => $prdId, ':equipoid' => $equipoId]);
            $existingAg = $stmtCheckAg->fetch(PDO::FETCH_ASSOC);
            if ($existingAg && (int)$existingAg['id'] > 0) {
                return (int)$existingAg['id'];
            }

            $stmtPrd = $this->erpPdo->prepare(
                "SELECT t3x.id AS prdid, t3x.prd_reqid, t1x.item_amount, t1.id AS req_id, teq.equipo_type_id
                 FROM prod_header t3x
                 INNER JOIN orders t1 ON t3x.prd_reqid = t1.id
                 LEFT JOIN orders_items t1x ON t1.id = t1x.req_id
                 LEFT JOIN equipo teq ON teq.id = :equipo_id
                 WHERE t3x.id = :prd_id
                 LIMIT 1"
            );
            $stmtPrd->execute([':prd_id' => $prdId, ':equipo_id' => $equipoId]);
            $prd = $stmtPrd->fetch(PDO::FETCH_ASSOC);
            if (!$prd) {
                return 0;
            }

            $reqId = (int)$prd['req_id'];
            $equipoTypeId = (int)($prd['equipo_type_id'] ?? 0);
            $itemAmount = $amount !== null && $amount > 0 ? $amount : (float)($prd['item_amount'] ?? 1000);
            $now = time();
            $today = date('d.m.Y');

            $stmtInsAg = $this->erpPdo->prepare(
                "INSERT INTO prod_agenda
                    (ag_date, ag_date_stamp, ag_equipo_id, ag_equipotype_id, ag_amount, ag_prdid, ag_reqid, ag_plantaid, ag_crtdat, ag_crtusr, ag_status, ag_active, ag_order)
                 VALUES
                    (:ag_date, :ag_date_stamp, :ag_equipo_id, :ag_equipotype_id, :ag_amount, :ag_prdid, :ag_reqid, :ag_plantaid, :ag_crtdat, 0, 1, 1, 99)"
            );
            $stmtInsAg->execute([
                ':ag_date' => $today,
                ':ag_date_stamp' => $now,
                ':ag_equipo_id' => $equipoId,
                ':ag_equipotype_id' => $equipoTypeId,
                ':ag_amount' => $itemAmount,
                ':ag_prdid' => $prdId,
                ':ag_reqid' => $reqId,
                ':ag_plantaid' => $plantaId > 0 ? $plantaId : 1,
                ':ag_crtdat' => $now,
            ]);

            return (int)$this->erpPdo->lastInsertId();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Incorpora una orden fuera de planificación a la agenda de la máquina y abre la sesión del operario.
     * 
     * @param int $prdId ID de prod_header
     * @param int $initId ID de prod_worker_init
     * @param int $equipoId ID de la máquina
     * @param int $plantaId ID de la planta
     * @param float|null $amount Cantidad a planificar (por defecto toma el saldo de la orden)
     * @return array{ok: bool, ag_id?: int, worker_ot_id?: int, error?: string}
     */
    public function startUnscheduledWorkOrder(int $prdId, int $initId, int $equipoId, int $plantaId, ?float $amount = null): array
    {
        if ($prdId <= 0 || $initId <= 0 || $equipoId <= 0) {
            return ['ok' => false, 'error' => 'Parámetros inválidos para iniciar orden fuera de planificación.'];
        }

        try {
            $agId = $this->ensureAgendaForUnscheduledOrder($prdId, $equipoId, $plantaId, $amount);
            if ($agId <= 0) {
                return ['ok' => false, 'error' => 'No se pudo generar o encontrar el registro de agenda para la orden.'];
            }

            $startRes = $this->startWorkerOt($agId, $initId);
            if (!$startRes['ok']) {
                return $startRes;
            }

            return [
                'ok' => true,
                'ag_id' => $agId,
                'worker_ot_id' => (int)($startRes['worker_ot_id'] ?? 0),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al incorporar e iniciar orden: ' . $e->getMessage()];
        }
    }

    /**
     * Genera y descarga un archivo Excel con la lista de productos que no entraron en la planificación.
     */
    public function outputUnscheduledOrdersExcel(array $orders, string $machineName = ''): void
    {
        $safeMachine = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $machineName) ?: 'Maquina';
        $filename = 'productos_sin_planificacion_' . strtolower($safeMachine) . '_' . date('Ymd_His') . '.xls';

        $html = '<table border="1">';
        $html .= '<thead><tr style="background:#0F766E;color:#FFFFFF;font-weight:bold">';
        $html .= '<th>N° O.T.</th>';
        $html .= '<th>N° C.C.</th>';
        $html .= '<th>Cliente</th>';
        $html .= '<th>Producto / Título</th>';
        $html .= '<th>Material</th>';
        $html .= '<th>Tipo Impresión</th>';
        $html .= '<th>Medidas (cm)</th>';
        $html .= '<th>Fuelle (cm)</th>';
        $html .= '<th>Cantidad Solicitada</th>';
        $html .= '<th>Fecha Requerida ERP</th>';
        $html .= '<th>Ubicación Cliché</th>';
        $html .= '<th>Código Cliché</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($orders as $ord) {
            $w = (int)($ord['fab_med_width'] ?? 0);
            $h = (int)($ord['fab_med_height'] ?? 0);
            $fuelle = (int)($ord['fab_med_fuelle'] ?? 0);
            $medidas = $w > 0 ? "{$w}x{$h}" : '-';
            $initDate = (int)($ord['req_production_initdate'] ?? 0);
            $dateStr = $initDate > 0 ? date('d.m.Y', $initDate) : '-';

            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars((string)($ord['prd_number'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string)($ord['req_number'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string)($ord['cust_name'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string)($ord['item_title'] ?? $ord['fab_design_name'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string)($ord['fab_type'] ?? 'TNT')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string)($ord['fab_printtype'] ?? '-')) . '</td>';
            $html .= '<td>' . htmlspecialchars($medidas) . '</td>';
            $html .= '<td>' . ($fuelle > 0 ? (int)$fuelle : '0') . '</td>';
            $html .= '<td>' . (float)($ord['item_amount'] ?? 0) . '</td>';
            $html .= '<td>' . htmlspecialchars($dateStr) . '</td>';
            $html .= '<td>' . htmlspecialchars((string)($ord['req_infoaddprd_cliche_ubicacion'] ?? '-')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string)($ord['req_infoaddprd_cliche_codigo'] ?? '-')) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        require_once __DIR__ . '/SimpleXlsx.php';
        SimpleXlsx::streamHtml($filename, $html, 'Sin Planificacion');
        exit;
    }

    /**
     * Obtiene el texto formateado de "Distribución solicitado" para una OT en Rebobinadora.
     */
    public function getReboDistributionText(int $reqId, float $telaWidth = 0): string
    {
        if ($reqId <= 0) {
            return $telaWidth > 0 ? ('Bobina ' . rtrim(rtrim(number_format($telaWidth, 1, ',', ''), '0'), ',') . ' cm') : '-';
        }

        try {
            $stmtVals = $this->erpPdo->prepare(
                "SELECT rollo_amt, rollo_dims, req_rebo_type
                 FROM orders_classify_rebo_values
                 WHERE req_id = :req_id AND req_rebo_type IN ('metro', 'rollo') AND rollo_amt > 0 AND rollo_dims > 0
                 ORDER BY id ASC"
            );
            $stmtVals->execute([':req_id' => $reqId]);
            $vals = $stmtVals->fetchAll();

            if (!empty($vals)) {
                $parts = [];
                foreach ($vals as $v) {
                    $amt = rtrim(rtrim(number_format((float)$v['rollo_amt'], 2, ',', ''), '0'), ',');
                    $dims = rtrim(rtrim(number_format((float)$v['rollo_dims'], 2, ',', ''), '0'), ',');
                    $parts[] = "{$amt} x {$dims} cm";
                }
                return implode(' · ', $parts);
            }

            $stmtSubs = $this->erpPdo->prepare(
                "SELECT subprod_amount, subprod_ancho_cm, subprod_destino
                 FROM orders_classify_subproducts
                 WHERE req_id = :req_id AND subprod_amount > 0 AND subprod_ancho_cm > 0
                 ORDER BY sub_pos ASC"
            );
            $stmtSubs->execute([':req_id' => $reqId]);
            $subs = $stmtSubs->fetchAll();

            if (!empty($subs)) {
                $parts = [];
                foreach ($subs as $s) {
                    $amt = rtrim(rtrim(number_format((float)$s['subprod_amount'], 2, ',', ''), '0'), ',');
                    $w = rtrim(rtrim(number_format((float)$s['subprod_ancho_cm'], 2, ',', ''), '0'), ',');
                    $parts[] = "{$amt} x {$w} cm";
                }
                return implode(' · ', $parts);
            }
        } catch (Throwable) {
            // fallback
        }

        if ($telaWidth > 0) {
            $tw = rtrim(rtrim(number_format($telaWidth, 1, ',', ''), '0'), ',');
            return "Bobina {$tw} cm";
        }

        return '-';
    }

    /**
     * Adjunta parámetros de flexografía (Rodillo Z, corte_m2, metros a imprimir) a una lista de órdenes.
     *
     * @param array<int, array<string, mixed>> $orders
     * @param int $fallbackEquipoId
     */
    public function attachFlexoMetrics(array &$orders, int $fallbackEquipoId = 0): void
    {
        if (empty($orders)) {
            return;
        }

        $cacheParams = [];

        foreach ($orders as &$ord) {
            $isFlexo = ((int)($ord['equipo_type_id'] ?? 0) === 7)
                || ((int)($ord['equipo_prod_isprinter_flexo'] ?? 0) === 1)
                || stripos((string)($ord['equipo_name'] ?? ''), 'flexo') !== false
                || stripos((string)($ord['type_ant_title'] ?? ''), 'flexo') !== false;

            if (!$isFlexo) {
                continue;
            }

            $eqId = (int)($ord['win_equipoid'] ?? $ord['ag_equipo_id'] ?? $fallbackEquipoId);
            $w = (int)($ord['fab_med_width'] ?? 0);
            $fuelle = (int)($ord['fab_med_fuelle'] ?? 0);
            $paramMedida = $w;
            if ((int)($ord['item_prodcalc_fuelle_act'] ?? 0) === 1) {
                $paramMedida += $fuelle;
            }

            $polType = (string)($ord['req_solic_devprints_poltype'] ?? '');
            $cacheKey = "{$eqId}_{$paramMedida}";

            if (!isset($cacheParams[$cacheKey])) {
                $stmt = $this->erpPdo->prepare("SELECT * FROM equipo_params WHERE param_equipo_id = :eq AND param_medida >= :med ORDER BY param_medida ASC LIMIT 1");
                $stmt->execute([':eq' => $eqId, ':med' => $paramMedida]);
                $cacheParams[$cacheKey] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            $eqParam = $cacheParams[$cacheKey];
            $corteZ = 0;
            $corteM2 = 0.0;

            if ($eqParam) {
                $corteZ = (int)($eqParam['param_z'] ?? 0);
                if ($polType === 'pol170') {
                    $corteM2 = (float)($eqParam['param_poly17'] ?? 0);
                } else {
                    $corteM2 = (float)(!empty($eqParam['param_poly28']) ? $eqParam['param_poly28'] : ($eqParam['param_corte'] ?? 0));
                }
            }

            if ($corteM2 <= 0.0) {
                $h = (float)($ord['fab_med_height'] ?? 0);
                $corteM2 = round(($h + (float)$fuelle) / 100, 4);
            }

            $cantBolsas = (float)($ord['ag_amount'] ?? 0);
            $mermaPerc = (float)($ord['req_operador_mermaperc'] ?? 0);

            $mlinProg = round($cantBolsas * $corteM2);
            $mlinAddi = round($mlinProg / 100 * $mermaPerc);
            $metrosAImprimir = round($mlinProg + $mlinAddi);

            $ord['flexo_z'] = $corteZ;
            $ord['flexo_corte_m2'] = $corteM2;
            $ord['flexo_metros'] = $metrosAImprimir;
        }
        unset($ord);
    }

    /**
     * Adjunta parámetros de serigrafía (contador impresora) a una lista de órdenes.
     *
     * @param array<int, array<string, mixed>> $orders
     */
    public function attachSeriMetrics(array &$orders): void
    {
        if (empty($orders)) {
            return;
        }

        foreach ($orders as &$ord) {
            $isSeri = ((int)($ord['equipo_prod_isprinter_seri'] ?? 0) === 1)
                || ((int)($ord['equipo_type_id'] ?? 0) === 11)
                || stripos((string)($ord['equipo_name'] ?? ''), 'seri') !== false
                || stripos((string)($ord['equipo_name'] ?? ''), 'pulpo') !== false
                || stripos((string)($ord['type_ant_title'] ?? ''), 'seri') !== false;

            if (!$isSeri) {
                continue;
            }

            $agAmount = (float)($ord['ag_amount'] ?? 0);
            $mermaPerc = (float)($ord['req_operador_mermaperc'] ?? 0);
            $meraUnits = round($agAmount / 100 * $mermaPerc);
            $realUnit = $agAmount + $meraUnits;
            $devPrints = max(1, (int)($ord['req_solic_devprints_cc'] ?? 1));
            $ord['seri_contador'] = $devPrints > 0 ? round($realUnit / $devPrints) : (int)$realUnit;
        }
        unset($ord);
    }

    /**
     * Inicia una OT para el operario (crea registro en `prod_worker_ot` con `wok_status = 1`).
     *
     * @param int $agId ID de la agenda programada
     * @param int $initId ID de la sesión de máquina
     * @return array{ok: bool, worker_ot_id?: int, error?: string}
     */
    public function startWorkerOt(int $agId, int $initId): array
    {
        if ($agId <= 0 || $initId <= 0) {
            return ['ok' => false, 'error' => 'Agenda e inicio de máquina son requeridos.'];
        }

        // Verificar si ya existe una OT abierta o recién creada para este init (evita duplicación por doble clic)
        $recentTs = time() - 10;
        $stmtCheck = $this->erpPdo->prepare(
            "SELECT id FROM prod_worker_ot
             WHERE wok_ag_id = :ag_id AND wok_init_id = :init_id AND (wok_status = 1 OR wok_crtdat >= :recent_ts)
             ORDER BY id DESC LIMIT 1"
        );
        $stmtCheck->execute([
            ':ag_id' => $agId,
            ':init_id' => $initId,
            ':recent_ts' => $recentTs,
        ]);
        $existing = $stmtCheck->fetch();
        if (is_array($existing) && (int)$existing['id'] > 0) {
            return ['ok' => true, 'worker_ot_id' => (int)$existing['id']];
        }

        $now = time();
        $stmtInsert = $this->erpPdo->prepare(
            "INSERT INTO prod_worker_ot
             (wok_ag_id, wok_init_id, wok_crtdat, wok_enddat, wok_status)
             VALUES
             (:ag_id, :init_id, :crtdat, 0, 1)"
        );
        $stmtInsert->execute([
            ':ag_id' => $agId,
            ':init_id' => $initId,
            ':crtdat' => $now,
        ]);

        return ['ok' => true, 'worker_ot_id' => (int)$this->erpPdo->lastInsertId()];
    }

    // =========================================================================
    // SECCIÓN 3: CONSOLA DE OPERACIÓN DE LA OT (editxid.php)
    // =========================================================================

    /**
     * Retorna los detalles técnicos, ficha y cliente de una OT desde prod_agenda.
     *
     * @param int $agId ID de la agenda (prod_agenda.id)
     * @return array<string, mixed>|null
     */
    public function getWorkOrderAgendaDetails(int $agId): ?array
    {
        if ($agId <= 0) {
            return null;
        }

        $sql = "SELECT DISTINCT t0.*,
                       t1.req_number, t1.req_production_initdate, t1.req_status, t2.company_short,
                       t3.shop_name, t4.cust_name, t1.req_hash, t2x.item_number_prod, t2x.item_title,
                       t1x.item_amount, t3x.prd_number, t1x.fab_printtype, t1x.fab_type, t3x.id AS prdid,
                       t1x.fab_med_width, t1x.fab_med_height, t1x.fab_med_fuelle, t1x.fab_print_width,
                       t1x.fab_print_height, v1.add_name AS fabric_color, v2.add_name AS manilla_color,
                       fab_print_colors_front_1, fab_print_colors_front_2, fab_print_colors_front_3, fab_print_colors_front_4, fab_print_colors_front_5,
                       fab_print_colors_front_6, fab_print_colors_front_7, fab_print_colors_front_8, fab_print_colors_front_9, fab_print_colors_front_10,
                       fab_print_colors_back_1, fab_print_colors_back_2, fab_print_colors_back_3, fab_print_colors_back_4, fab_print_colors_back_5,
                       fab_print_colors_back_6, fab_print_colors_back_7, fab_print_colors_back_8, fab_print_colors_back_9, fab_print_colors_back_10,
                       fab_print_colordesc_1, fab_print_colordesc_2, fab_print_colordesc_3, fab_print_colordesc_4, fab_print_colordesc_5,
                       fab_print_colordesc_6, fab_print_colordesc_7, fab_print_colordesc_8, fab_print_colordesc_9, fab_print_colordesc_10,
                       t0.ag_amount, t1.req_cliche_peli_solic_dat, t1.req_cliche_peli_recep_dat,
                       t1.req_prod_adjfile_0, t1.req_prod_adjfile_1, t1.req_prod_adjfile_2, t1.req_prod_adjfile_3, t1.req_prod_adjfile_4,
                       t1.req_prod_adjcomments_0, t1.req_prod_adjcomments_1, t1.req_prod_adjcomments_2, t1.req_prod_adjcomments_3, t1.req_prod_adjcomments_4,
                       t1x.fab_design_imagehash, t1.req_company_id, t1.req_shop_id, t1x.fab_mat_fabric_color, t1x.fab_mat_manilla_color,
                       t1.req_solic_devprints_cc, t1x.fab_design_name, t1x.fab_mat_gramms, t1x.item_compdesc,
                       t1.req_operador_bastidoramt, t1.req_operador_mermaperc, t1.req_operador_tela_width,
                       t1.req_solic_devprints_poltype, t1x.fab_manilla_length, t1.req_rebo_type,
                       t1.req_rebo_state, t1.req_rebo_rolloscc, t2x.item_prodcalc_fuelle_act, t1.req_rebo_cortescc,
                       t1x.item_sellprice_barcodenumber,
                       t1.req_infoaddprd_dado_manillas,
                       t1.req_infoaddprd_cabezal_act,
                       t1.req_infoaddprd_procedencia,
                       t1.req_infoaddprd_reversa_act,
                       t1.req_infoaddprd_cliche_ubicacion,
                       t1.req_infoaddprd_cliche_codigo,
                       t1.req_solic_supp_file_0, t1.req_solic_supp_file_1, t1.req_solic_supp_file_2,
                       t1.req_solic_supp_file_3, t1.req_solic_supp_file_4, t1.req_solic_supp_name_0,
                       t1.req_solic_supp_name_1, t1.req_solic_supp_name_2, t1.req_solic_supp_name_3,
                       t1.req_solic_supp_name_4, t1x.fab_mat_dispositivo, tz.descripcion AS dispositivo,
                       t1x.item_id, tz2.cat_id,
                       t1.req_embalaje_medidas_caja, t1.req_embalaje_cajas_por_pallet_amt,
                       t1.req_embalaje_bolsas_por_caja_amt,
                       t1.req_caja_impresa, t1.req_embalaje_cajas_completas_amt, t1.req_embalaje_caja_final,
                       t1.req_pie_imprenta, t1.req_despacho_desc, t1.req_desc_intern,
                       to1.req_number AS reqnum_mezcla_1, to2.req_number AS reqnum_mezcla_2,
                       to3.req_number AS reqnum_mezcla_3, t1.req_rebo_cc_genrefid,
                       teq.equipo_name, teq.equipo_prod_isprinter_flexo, teq.equipo_prod_isprinter_seri,
                       teq.equipo_type_id, ttype.type_ant_title, ttype.type_createstock_act
                FROM prod_agenda t0
                LEFT OUTER JOIN prod_header t3x ON t0.ag_prdid = t3x.id AND t3x.prd_status >= 2
                LEFT OUTER JOIN orders t1 ON t0.ag_reqid = t1.id
                LEFT OUTER JOIN company_data t2 ON t1.req_company_id = t2.id
                LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id = t3.id
                LEFT OUTER JOIN customer t4 ON t1.req_cust_id = t4.id
                LEFT OUTER JOIN orders_items t1x ON t1.id = t1x.req_id
                LEFT OUTER JOIN item t2x ON t1x.item_id = t2x.id
                LEFT OUTER JOIN tran_comments_vals v1 ON t1x.fab_mat_fabric_color = v1.id
                LEFT OUTER JOIN tran_comments_vals v2 ON t1x.fab_mat_manilla_color = v2.id
                LEFT OUTER JOIN parametros tz ON t1x.fab_mat_dispositivo = tz.codigo AND tz.tabla = 'DISPOSITIVO'
                LEFT OUTER JOIN item_productcats tz2 ON t1x.item_id = tz2.item_id
                LEFT OUTER JOIN productcats tz3 ON tz2.cat_id = tz3.id
                LEFT OUTER JOIN orders to1 ON t1.req_id_cc_m1 = to1.id
                LEFT OUTER JOIN orders to2 ON t1.req_id_cc_m2 = to2.id
                LEFT OUTER JOIN orders to3 ON t1.req_id_cc_m3 = to3.id
                LEFT OUTER JOIN equipo teq ON t0.ag_equipo_id = teq.id
                LEFT OUTER JOIN equipo_type ttype ON teq.equipo_type_id = ttype.id
                WHERE t0.id = :ag_id
                LIMIT 1";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute([':ag_id' => $agId]);
            $row = $stmt->fetch();
            if (is_array($row)) {
                $grams = (float)($row['fab_mat_gramms'] ?? 0);
                if ($grams <= 0) {
                    $text = (string)($row['item_compdesc'] ?? '') . ' ' . (string)($row['item_title'] ?? '');
                    if (preg_match('/(?:tela|gramaje|peso)?\s*(\d{2,3})\s*(?:grs?|g|gsm)\b/i', $text, $m)) {
                        $grams = (float)$m[1];
                    }
                }
                $row['ot_grams'] = $grams;
                $row['ot_grams_str'] = $grams > 0 ? ((int)$grams . ' gr') : '-';
                return $row;
            }
            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Retorna todo el contexto técnico, especificaciones y métricas para la consola del operario.
     *
     * @param int $agId ID de la agenda
     * @param int $workerId ID del operario
     * @param int $plantaId ID de planta
     * @return array<string, mixed>|null
     */
    public function getWorkOrderOperatingContext(int $agId, int $workerId, int $plantaId): ?array
    {
        $openInit = $this->getOpenWorkerInit($workerId, $plantaId);
        $workerOt = $this->getOpenWorkerOtByAgId($workerId, $plantaId, $agId);

        $agendaData = $this->getWorkOrderAgendaDetails($agId);

        if (($openInit === null || (int)($openInit['id'] ?? 0) === 0) && $workerOt !== null && !empty($workerOt['wok_init_id'])) {
            try {
                $stmtInit = $this->erpPdo->prepare(
                    "SELECT t1.*, t2.equipo_name, t2.equipo_prod_isprinter_flexo, t2.equipo_prod_isprinter_seri,
                            t2.equipo_type_id, t3.type_ant_title, t3.type_createstock_act, t3.type_ant_inpmedidas_act
                     FROM prod_worker_init t1
                     LEFT JOIN equipo t2 ON t1.win_equipoid = t2.id
                     LEFT JOIN equipo_type t3 ON t2.equipo_type_id = t3.id
                     WHERE t1.id = :init_id"
                );
                $stmtInit->execute([':init_id' => (int)$workerOt['wok_init_id']]);
                $initRow = $stmtInit->fetch();
                if (is_array($initRow)) {
                    $openInit = $initRow;
                }
            } catch (Throwable) {}
        }

        if ($openInit === null && is_array($agendaData)) {
            $openInit = [
                'id' => 0,
                'win_equipoid' => (int)($agendaData['ag_equipo_id'] ?? 0),
                'equipo_name' => (string)($agendaData['equipo_name'] ?? ''),
                'equipo_prod_isprinter_flexo' => (int)($agendaData['equipo_prod_isprinter_flexo'] ?? 0),
                'equipo_prod_isprinter_seri' => (int)($agendaData['equipo_prod_isprinter_seri'] ?? 0),
                'equipo_type_id' => (int)($agendaData['equipo_type_id'] ?? 0),
                'type_ant_title' => (string)($agendaData['type_ant_title'] ?? ''),
                'type_createstock_act' => (int)($agendaData['type_createstock_act'] ?? 0),
                'type_ant_inpmedidas_act' => (int)($agendaData['type_ant_inpmedidas_act'] ?? 0),
            ];
        }

        if (!is_array($agendaData)) {
            $sqlRelaxed = str_replace("AND t3x.prd_status >= 2", "", $sql);
            $stmt = $this->erpPdo->prepare($sqlRelaxed);
            $stmt->execute([':ag_id' => $agId]);
            $agendaData = $stmt->fetch();
            if (!is_array($agendaData)) {
                return null;
            }
        }

        // Obtener comentarios / características adicionales (tran_comments)
        $comvals = [];
        try {
            $stmtCom = $this->erpPdo->prepare(
                "SELECT t1.com_id, t2.add_name
                 FROM tran_comments_item_vals t1
                 INNER JOIN tran_comments_vals t2 ON t1.val_id = t2.id
                 WHERE t1.item_id = :item_id"
            );
            $stmtCom->execute([':item_id' => (int)($agendaData['item_id'] ?? 0)]);
            $comvals = $stmtCom->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        } catch (Throwable) {
            $comvals = [];
        }

        $transcomInfos = [
            'itemtype' => (string)($comvals[20] ?? ''),
            'ancho' => (string)($comvals[31] ?? (string)($agendaData['fab_med_width'] ?? '')),
            'alto_tiro' => (string)($comvals[39] ?? (string)($agendaData['fab_med_height'] ?? '')),
            'alto_retiro' => (string)($comvals[40] ?? ''),
            'doblez_superior' => (string)($comvals[41] ?? ''),
            'ancho_manillas' => (string)($comvals[49] ?? ''),
        ];

        // Parámetros de máquina (equipo_params)
        $paramMedida = (int)($agendaData['fab_med_width'] ?? 0);
        if (!empty($agendaData['item_prodcalc_fuelle_act'])) {
            $paramMedida += (int)($agendaData['fab_med_fuelle'] ?? 0);
        }

        $corteM2 = 0.0;
        $corteZ = 0;
        $equipoParams = null;
        if ($openInit !== null && !empty($openInit['win_equipoid'])) {
            try {
                $stmtEq = $this->erpPdo->prepare(
                    "SELECT * FROM equipo_params
                     WHERE param_equipo_id = :eq_id
                       AND param_medida >= :medida
                     ORDER BY param_medida ASC
                     LIMIT 1"
                );
                $stmtEq->execute([
                    ':eq_id' => (int)$openInit['win_equipoid'],
                    ':medida' => $paramMedida,
                ]);
                $equipoParams = $stmtEq->fetch(PDO::FETCH_ASSOC);
                if (is_array($equipoParams)) {
                    $polType = (string)($agendaData['req_solic_devprints_poltype'] ?? '');
                    if ($polType === 'pol284' && isset($equipoParams['param_poly28'])) {
                        $corteM2 = (float)$equipoParams['param_poly28'];
                        $corteZ = (int)($equipoParams['param_z'] ?? 0);
                    } elseif ($polType === 'pol170' && isset($equipoParams['param_poly17'])) {
                        $corteM2 = (float)$equipoParams['param_poly17'];
                        $corteZ = (int)($equipoParams['param_z'] ?? 0);
                    } else {
                        $corteM2 = (float)($equipoParams['param_corte'] ?? 0);
                        $corteZ = (int)($equipoParams['param_z'] ?? 0);
                    }
                }
            } catch (Throwable) {
                // Ignore missing params
            }
        }

        // Entregas programadas desde prod_amtplan
        $entregas = [];
        try {
            $stmtEnt = $this->erpPdo->prepare(
                "SELECT * FROM prod_amtplan
                 WHERE prodplan_prdid = :prdid
                 ORDER BY prodplan_date ASC"
            );
            $stmtEnt->execute([':prdid' => (int)($agendaData['prdid'] ?? 0)]);
            $entregas = $stmtEnt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            $entregas = [];
        }

        // Totales acumulados producidos para esta agenda (THIS_STATS) y de la OT completa (OT_STATS)
        $totProducedThis = 0.0;
        $totMetersThis = 0.0;
        $totKilosThis = 0.0;
        $totPulpoFrenteThis = 0.0;
        $totPulpoDorsoThis = 0.0;
        if ($workerOt !== null) {
            $stmtTot = $this->erpPdo->prepare(
                "SELECT SUM(evt_amount) AS tot_amt,
                        SUM(evt_amount_metros_lineales) AS tot_meters,
                        SUM(prod_bobina_kg) AS tot_kg,
                        SUM(evt_amount_pulpo_frente) AS tot_pulpo_frente,
                        SUM(evt_amount_pulpo_dorso) AS tot_pulpo_dorso
                 FROM prod_worker_ot_events
                 WHERE evt_prod_worker_otid = :ot_id
                   AND evt_type = 'prod'
                   AND evt_status > 0"
            );
            $stmtTot->execute([':ot_id' => (int)$workerOt['id']]);
            $tRow = $stmtTot->fetch();
            if (is_array($tRow)) {
                $totProducedThis = (float)($tRow['tot_amt'] ?? 0);
                $totMetersThis = (float)($tRow['tot_meters'] ?? 0);
                $totKilosThis = (float)($tRow['tot_kg'] ?? 0);
                $totPulpoFrenteThis = (float)($tRow['tot_pulpo_frente'] ?? 0);
                $totPulpoDorsoThis = (float)($tRow['tot_pulpo_dorso'] ?? 0);
            }
        }

        $totProducedAll = 0.0;
        $totPulpoFrenteAgenda = $totPulpoFrenteThis;
        $totPulpoDorsoAgenda = $totPulpoDorsoThis;
        try {
            $stmtAgTot = $this->erpPdo->prepare(
                "SELECT SUM(t3.evt_amount) AS tot_all,
                        SUM(t3.evt_amount_pulpo_frente) AS tot_frente,
                        SUM(t3.evt_amount_pulpo_dorso) AS tot_dorso
                 FROM prod_worker_ot t2
                 INNER JOIN prod_worker_ot_events t3 ON t3.evt_prod_worker_otid = t2.id
                 WHERE t2.wok_ag_id = :ag_id
                   AND t3.evt_type = 'prod'
                   AND t3.evt_status > 0"
            );
            $stmtAgTot->execute([':ag_id' => $agId]);
            $agRow = $stmtAgTot->fetch();
            if (is_array($agRow)) {
                $totProducedAll = (float)($agRow['tot_all'] ?? 0);
                $totPulpoFrenteAgenda = (float)($agRow['tot_frente'] ?? 0);
                $totPulpoDorsoAgenda = (float)($agRow['tot_dorso'] ?? 0);
            }
        } catch (Throwable) {
            $totProducedAll = $totProducedThis;
        }

        $surplusFrente = max(0.0, $totPulpoFrenteAgenda - $totPulpoDorsoAgenda);
        $surplusDorso = max(0.0, $totPulpoDorsoAgenda - $totPulpoFrenteAgenda);

        // Cálculos adicionales para Impresora Flexo
        $agAmount = (float)($agendaData['ag_amount'] ?? 0);
        $mermaPerc = (float)($agendaData['req_operador_mermaperc'] ?? 0);
        $telaWidth = (float)($agendaData['req_operador_tela_width'] ?? 0);
        $gramms = (float)($agendaData['fab_mat_gramms'] ?? 0);

        $mlinProg = round($agAmount * $corteM2);
        $mlinAddi = round(($mlinProg / 100) * $mermaPerc);
        $metrosAImprimir = round($mlinProg + $mlinAddi);
        $kgAImprimir = round(($metrosAImprimir * $telaWidth / 100 * $gramms) / 1000, 2);
        $contadorImpresora = $metrosAImprimir > 0 ? round($metrosAImprimir / 0.41) : 0;

        // Cálculos de colores y pasadas para Serigrafía / Pulpo
        $amtColorsFrente = 0;
        $amtColorsDorso = 0;
        $amtColorsSeri = 0;
        for ($c = 1; $c <= 10; $c++) {
            $frontCol = !empty($agendaData["fab_print_colors_front_{$c}"]);
            $backCol = !empty($agendaData["fab_print_colors_back_{$c}"]);
            if ($frontCol) $amtColorsFrente++;
            if ($backCol) $amtColorsDorso++;
            if ($frontCol || $backCol) $amtColorsSeri++;
        }

        $devPrints = max(1, (int)($agendaData['req_solic_devprints_cc'] ?? 1));
        $pasadasPrint = $devPrints > 0 ? round($agAmount / $devPrints) : 0;

        // Insumos confección (manillas, bobinas)
        $largoManillas = (int)($agendaData['fab_manilla_length'] ?? 0);
        $cantManillas = round(($largoManillas / 100) * $agAmount * 2 / 1200, 2);
        $cantBobinas = $metrosAImprimir > 0 ? round($metrosAImprimir / 1000, 2) : 0;

        // Anilox para flexografía (editxid.php 90-340)
        $itemAnilox = [];
        $detalleAnilox = [];
        $aniloxDescMap = [];
        $reqId = (int)($agendaData['ag_reqid'] ?? 0);
        $equipoId = (int)($openInit['win_equipoid'] ?? 0);
        if ($openInit !== null && !empty($openInit['equipo_prod_isprinter_flexo']) && $reqId > 0 && $equipoId > 0) {
            $aniloxContext = $this->getAniloxContext($agId, $reqId, $equipoId, (int)($_SESSION['user_id'] ?? 0));
            $itemAnilox = $aniloxContext['item_anilox'];
            $detalleAnilox = $aniloxContext['detalle_anilox'];
            $aniloxDescMap = $aniloxContext['anilox_desc_map'];
        }

        $mermatypes = $this->getMermaTypes();
        $repairtypes = $this->getRepairTypes();

        // Ayudante de apertura
        $aperturaAyudanteName = '';
        $aperturaComments = '';
        if ($workerOt !== null) {
            try {
                $stmtAp = $this->erpPdo->prepare(
                    "SELECT t1.evt_comments, CONCAT(t2.wrk_firstname, ' ', t2.wrk_lastname) AS ayudante_name
                     FROM prod_worker_ot_events t1
                     LEFT OUTER JOIN workers t2 ON t1.evt_idayudante = t2.id
                     WHERE t1.evt_prod_worker_otid = :ot_id
                       AND t1.evt_type = 'apertura'
                       AND t1.evt_status = 1
                     ORDER BY t1.id DESC
                     LIMIT 1"
                );
                $stmtAp->execute([':ot_id' => (int)$workerOt['id']]);
                $apRow = $stmtAp->fetch(PDO::FETCH_ASSOC);
                if (is_array($apRow)) {
                    $aperturaAyudanteName = (string)($apRow['ayudante_name'] ?? '');
                    $aperturaComments = (string)($apRow['evt_comments'] ?? '');
                }
            } catch (Throwable) {
                // Ignore
            }
        }

        // Lista de ayudantes disponibles
        $ayudantesList = [];
        try {
            $stmtAyu = $this->erpPdo->query(
                "SELECT id, CONCAT(wrk_firstname, ' ', wrk_lastname) AS nombre
                 FROM workers
                 WHERE wrk_status > 0
                 ORDER BY wrk_firstname ASC"
            );
            $ayudantesList = $stmtAyu->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            $ayudantesList = [];
        }

        $typeAntTitle = strtoupper((string)($openInit['type_ant_title'] ?? ''));
        $equipoName = strtoupper((string)($openInit['equipo_name'] ?? ''));

        $isFlexo = !empty($openInit['equipo_prod_isprinter_flexo']);
        $isSeri = !empty($openInit['equipo_prod_isprinter_seri']);
        $isSeriPulpo = $isSeri && (strpos($equipoName, 'PULPO') !== false);
        $isRebobinadora = ((int)($openInit['equipo_type_id'] ?? 0) === 12)
            || strpos($typeAntTitle, 'REBOBIN') !== false
            || strpos($equipoName, 'REBOB') !== false;
        $isSelladora = strpos($typeAntTitle, 'SELLADORA') !== false;
        $isEmbalaje = !empty($openInit['type_createstock_act']);

        // Carga de información completa de Rebobinadora (editxid.php 2370-2600 y 4381-4465)
        $reboOrder = [];
        $reboValues = [];
        $subproducts = [];
        $inputWeights = [];
        if ($reqId > 0) {
            try {
                $stmtReboO = $this->erpPdo->prepare("SELECT * FROM orders WHERE id = :req_id LIMIT 1");
                $stmtReboO->execute([':req_id' => $reqId]);
                $reboOrder = $stmtReboO->fetch(PDO::FETCH_ASSOC) ?: [];
                if (!empty($reboOrder) && is_array($agendaData)) {
                    foreach ($reboOrder as $rk => $rv) {
                        if (!isset($agendaData[$rk]) || $agendaData[$rk] === '' || $agendaData[$rk] === null) {
                            $agendaData[$rk] = $rv;
                        }
                    }
                }
            } catch (Throwable) {
                $reboOrder = [];
            }

            try {
                $stmtRV = $this->erpPdo->prepare(
                    "SELECT * FROM orders_classify_rebo_values WHERE req_id = :req_id ORDER BY id ASC"
                );
                $stmtRV->execute([':req_id' => $reqId]);
                $rawRV = $stmtRV->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rawRV as $rv) {
                    $reboValues[(string)$rv['req_rebo_type']][(int)$rv['id']] = $rv;
                }
            } catch (Throwable) {
                $reboValues = [];
            }

            try {
                $stmtSP = $this->erpPdo->prepare(
                    "SELECT * FROM orders_classify_subproducts WHERE req_id = :req_id ORDER BY sub_pos ASC"
                );
                $stmtSP->execute([':req_id' => $reqId]);
                $rawSP = $stmtSP->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rawSP as $sp) {
                    $subproducts[(int)$sp['sub_pos']] = $sp;
                }
            } catch (Throwable) {
                $subproducts = [];
            }

            // Si reboValues está vacío pero existen subproductos (corte de bolsa con manillas en sobrante, ej. OT 000866),
            // sintetizamos reboValues para que todas las OTs tengan la misma estructura y visualización.
            if (empty($reboValues['rollo']) && empty($reboValues['metro']) && !empty($subproducts)) {
                $fakeId = 1;
                foreach ($subproducts as $sp) {
                    $amt = (float)($sp['subprod_amount'] ?? 0);
                    $dims = (float)($sp['subprod_ancho_cm'] ?? 0);
                    if ($amt > 0 && $dims > 0) {
                        $reboValues['rollo'][$fakeId] = [
                            'id' => $fakeId,
                            'req_id' => $reqId,
                            'req_rebo_type' => 'rollo',
                            'rollo_amt' => $amt,
                            'rollo_dims' => $dims,
                        ];
                        $fakeId++;
                    }
                }
                $cantRollosMadre = (float)($reboOrder['req_rebo_rolloscc'] ?? 1);
                if ($cantRollosMadre <= 0) $cantRollosMadre = 1.0;
                $reboValues['rollo_amt'][1] = [
                    'id' => 1,
                    'req_id' => $reqId,
                    'req_rebo_type' => 'rollo_amt',
                    'rollo_amt' => $cantRollosMadre,
                ];
            }

            if (empty($reboOrder['req_rebo_rolloscc_opttype']) && !empty($reboOrder['req_rebo_type'])) {
                $reboOrder['req_rebo_rolloscc_opttype'] = 'Por rollo';
                $agendaData['req_rebo_rolloscc_opttype'] = 'Por rollo';
            }

            if ($workerOt !== null && !empty($workerOt['id'])) {
                try {
                    $stmtIW = $this->erpPdo->prepare(
                        "SELECT * FROM orders_classify_subproducts_inputweights
                         WHERE req_id = :req_id AND prod_worker_ot_id = :ot_id
                         ORDER BY subprod_rollo_idx ASC"
                    );
                    $stmtIW->execute([
                        ':req_id' => $reqId,
                        ':ot_id' => (int)$workerOt['id'],
                    ]);
                    $rawIW = $stmtIW->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    foreach ($rawIW as $iw) {
                        $inputWeights[(int)$iw['subprod_rollo_idx']] = $iw;
                    }
                } catch (Throwable) {
                    $inputWeights = [];
                }
            }
        }

        $mermatypes = $this->getMermaTypes();
        $repairtypes = $this->getRepairTypes();

        return [
            'agenda' => $agendaData,
            'machine_init' => $openInit,
            'worker_ot' => $workerOt,
            'transcom_infos' => $transcomInfos,
            'rebo_order' => $reboOrder,
            'rebo_values' => $reboValues,
            'subproducts' => $subproducts,
            'input_weights' => $inputWeights,
            'equipo_params' => $equipoParams,
            'corte_z' => $corteZ,
            'corte_m2' => $corteM2,
            'entregas' => $entregas,
            'metros_a_imprimir' => $metrosAImprimir,
            'kg_a_imprimir' => $kgAImprimir,
            'contador_impresora' => $contadorImpresora,
            'amt_colors_frente' => $amtColorsFrente,
            'amt_colors_dorso' => $amtColorsDorso,
            'amt_colors_seri' => $amtColorsSeri,
            'pasadas_print' => $pasadasPrint,
            'largo_manillas' => $largoManillas,
            'cant_manillas' => $cantManillas,
            'cant_bobinas' => $cantBobinas,
            'item_anilox' => $itemAnilox,
            'detalle_anilox' => $detalleAnilox,
            'anilox_desc_map' => $aniloxDescMap,
            'mermatypes' => $mermatypes,
            'repairtypes' => $repairtypes,
            'apertura_ayudante' => $aperturaAyudanteName,
            'apertura_comments' => $aperturaComments,
            'ayudantes_list' => $ayudantesList,
            'flags' => [
                'is_flexo' => $isFlexo,
                'is_seri' => $isSeri,
                'is_seri_pulpo' => $isSeriPulpo,
                'is_rebobinadora' => $isRebobinadora,
                'is_selladora' => $isSelladora,
                'is_embalaje' => $isEmbalaje,
            ],
            'stats' => [
                'total_produced_this' => $totProducedThis,
                'total_produced_all' => $totProducedAll,
                'total_meters' => $totMetersThis,
                'total_kilos' => $totKilosThis,
                'target_amount' => $agAmount,
                'order_amount' => (float)($agendaData['item_amount'] ?? $agAmount),
                'percent_completed_this' => $agAmount > 0
                    ? round(($totProducedThis / $agAmount) * 100, 2)
                    : 0,
                'percent_completed_all' => (float)($agendaData['item_amount'] ?? 0) > 0
                    ? round(($totProducedAll / (float)$agendaData['item_amount']) * 100, 2)
                    : 0,
                'saldo_total' => max(0.0, (float)($agendaData['item_amount'] ?? 0) - $totProducedAll),
                'total_pulpo_frente_agenda' => $totPulpoFrenteAgenda,
                'total_pulpo_dorso_agenda' => $totPulpoDorsoAgenda,
                'total_pulpo_frente_this' => $totPulpoFrenteThis,
                'total_pulpo_dorso_this' => $totPulpoDorsoThis,
                'surplus_frente' => $surplusFrente,
                'surplus_dorso' => $surplusDorso,
            ],
        ];
    }

    /**
     * Lista los eventos ocurridos dentro de una OT de operario (`prod_worker_ot_events`).
     *
     * @param int $workerOtId ID de la OT del operario (prod_worker_ot.id)
     * @return array<int, array<string, mixed>>
     */
    public function getWorkOrderEvents(int $workerOtId): array
    {
        if ($workerOtId <= 0) {
            return [];
        }

        $sql = "SELECT t1.*, t2.med_name AS from_med_name, t3.med_name AS to_med_name,
                       t4.pause_name, t5.repair_title AS repair_name
                FROM prod_worker_ot_events t1
                LEFT OUTER JOIN prod_medidas t2 ON t1.evt_medida_fromid = t2.id
                LEFT OUTER JOIN prod_medidas t3 ON t1.evt_medida_toid = t3.id
                LEFT OUTER JOIN prod_pause_types t4 ON t1.evt_pause_id = t4.id
                LEFT OUTER JOIN prod_repairtypes t5 ON t1.evt_equipo_mantid = t5.id
                WHERE (
                    t1.evt_prod_worker_otid = :ot_id
                    OR (t1.overrideembalaje_act = 1 AND t1.overrideembalaje_selladora_evtrefid = :ot_id2)
                )
                AND t1.evt_status > 0
                ORDER BY t1.evt_crtdat DESC, t1.id DESC";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute([':ot_id' => $workerOtId, ':ot_id2' => $workerOtId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    // =========================================================================
    // SECCIÓN 4: REGISTRO DE EVENTOS OPERATIVOS (prod_worker_ot_events)
    // =========================================================================

    /**
     * Registra o finaliza evento de Apertura / Preparación de máquina (`evt_type = 'apertura'`).
     */
    public function recordEventApertura(
        int $workerOtId,
        int $medidaFromId,
        int $medidaToId,
        string $comments,
        ?int $refId = null,
        bool $isEnd = false,
        int $idAyudante = 0
    ): array {
        if ($workerOtId <= 0 && $refId !== null && $refId > 0) {
            try {
                $stmtFindWot = $this->erpPdo->prepare("SELECT evt_prod_worker_otid FROM prod_worker_ot_events WHERE id = :id");
                $stmtFindWot->execute([':id' => $refId]);
                $foundWotId = (int)$stmtFindWot->fetchColumn();
                if ($foundWotId > 0) {
                    $workerOtId = $foundWotId;
                }
            } catch (Throwable) {}
        }

        if ($workerOtId <= 0 && ($refId === null || $refId <= 0)) {
            return ['ok' => false, 'error' => 'ID de OT inválido.'];
        }

        $now = time();
        if ($refId !== null && $refId > 0) {
            if ($isEnd) {
                $sql = "UPDATE prod_worker_ot_events
                        SET evt_comments = :comments,
                            evt_medida_fromid = :from_id,
                            evt_medida_toid = :to_id,
                            evt_idayudante = :ayudante,
                            evt_enddat = :now
                        WHERE id = :id";
                $stmt = $this->erpPdo->prepare($sql);
                $stmt->execute([
                    ':comments' => $comments,
                    ':from_id' => $medidaFromId,
                    ':to_id' => $medidaToId,
                    ':ayudante' => $idAyudante,
                    ':now' => $now,
                    ':id' => $refId,
                ]);
            } else {
                $sql = "UPDATE prod_worker_ot_events
                        SET evt_comments = :comments,
                            evt_medida_fromid = :from_id,
                            evt_medida_toid = :to_id,
                            evt_idayudante = :ayudante
                        WHERE id = :id";
                $stmt = $this->erpPdo->prepare($sql);
                $stmt->execute([
                    ':comments' => $comments,
                    ':from_id' => $medidaFromId,
                    ':to_id' => $medidaToId,
                    ':ayudante' => $idAyudante,
                    ':id' => $refId,
                ]);
            }
            return ['ok' => true, 'event_id' => $refId];
        }

        $sql = "INSERT INTO prod_worker_ot_events
                (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type,
                 evt_comments, evt_medida_fromid, evt_medida_toid, evt_idayudante)
                VALUES
                (:ot_id, 0, :crtdat, 0, 1, 'apertura', :comments, :from_id, :to_id, :ayudante)";

        $stmt = $this->erpPdo->prepare($sql);
        $stmt->execute([
            ':ot_id' => $workerOtId,
            ':crtdat' => $now,
            ':comments' => $comments,
            ':from_id' => $medidaFromId,
            ':to_id' => $medidaToId,
            ':ayudante' => $idAyudante,
        ]);

        return ['ok' => true, 'event_id' => (int)$this->erpPdo->lastInsertId()];
    }

    /**
     * Registra o finaliza evento de Producción (`evt_type = 'prod'`).
     */
    public function recordEventProduction(
        int $workerOtId,
        float $amount,
        float $amount2,
        float $bobinaKg,
        int $metrosMaquina,
        int $metrosLineales,
        string $metroType,
        string $comments,
        ?int $refId = null,
        bool $isEnd = false,
        int $overrideEmbalaje = 0,
        array $mermas = [],
        array $repairs = [],
        float $amountPulpoFrente = 0.0,
        float $amountPulpoDorso = 0.0
    ): array {
        if ($workerOtId <= 0) {
            return ['ok' => false, 'error' => 'ID de OT inválido.'];
        }

        $now = time();
        $eventId = 0;

        // Si no viene refId y se trata de un inicio de tiraje (amount = 0), comprobar si ya existe un evento 'prod' abierto
        if ($refId === null || $refId <= 0) {
            if ($amount <= 0.0) {
                $stmtActiveProd = $this->erpPdo->prepare("
                    SELECT id FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :ot_id 
                      AND evt_type = 'prod'
                      AND evt_status = 1
                    ORDER BY id DESC LIMIT 1
                ");
                $stmtActiveProd->execute([':ot_id' => $workerOtId]);
                $activeProdId = (int)$stmtActiveProd->fetchColumn();
                if ($activeProdId > 0) {
                    $refId = $activeProdId;
                }
            }
        }
        // Automatización de Bobinas Impresas: Si no vinieron kilos o metros, autocompletar con la suma de bobinas impresas producidas
        if ($workerOtId > 0 && ($bobinaKg <= 0.0 || $metrosLineales <= 0)) {
            $prSummary = $this->getPrintedRollsSummary($workerOtId);
            if (!empty($prSummary['rolls_count']) && $prSummary['rolls_count'] > 0) {
                if ($bobinaKg <= 0.0) {
                    $bobinaKg = (float)$prSummary['total_weight_kg'];
                }
                if ($metrosLineales <= 0) {
                    $metrosLineales = (int)round($prSummary['total_meters']);
                }
                if ($amount <= 0.0 && ($metroType === 'metros_lineales' || empty($metroType))) {
                    $amount = (float)$metrosLineales;
                }
            }
        }

        if ($refId !== null && $refId > 0) {
            $eventId = $refId;
            $endDat = $isEnd ? $now : null;
            $sql = "UPDATE prod_worker_ot_events
                    SET evt_amount = :amt, evt_amount2 = :amt2, prod_bobina_kg = :kg,
                        evt_comments = :comments, evt_amount_metros_maquina = :mmaq,
                        evt_amount_metros_lineales = :mlin, evt_metrotype = :mtype,
                        overrideembalaje_act = :overr,
                        evt_amount_pulpo_frente = :frente,
                        evt_amount_pulpo_dorso = :dorso"
                    . ($isEnd ? ", evt_enddat = :enddat, evt_status = 2" : "")
                    . " WHERE id = :id";
            $params = [
                ':amt' => $amount,
                ':amt2' => $amount2,
                ':kg' => $bobinaKg,
                ':comments' => $comments,
                ':mmaq' => $metrosMaquina,
                ':mlin' => $metrosLineales,
                ':mtype' => $metroType,
                ':overr' => $overrideEmbalaje,
                ':frente' => $amountPulpoFrente,
                ':dorso' => $amountPulpoDorso,
                ':id' => $refId,
            ];
            if ($isEnd) {
                $params[':enddat'] = $endDat;
            }
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $status = $isEnd ? 2 : 1;
            $endDat = $isEnd ? $now : 0;
            $sql = "INSERT INTO prod_worker_ot_events
                    (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments,
                     prod_bobina_kg, evt_amount_metros_maquina, evt_amount_metros_lineales, evt_metrotype, evt_amount2,
                     overrideembalaje_act, evt_amount_pulpo_frente, evt_amount_pulpo_dorso)
                    VALUES
                    (:ot_id, :amt, :crtdat, :enddat, :status, 'prod', :comments,
                     :kg, :mmaq, :mlin, :mtype, :amt2, :overr, :frente, :dorso)";

            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute([
                ':ot_id' => $workerOtId,
                ':amt' => $amount,
                ':crtdat' => $now,
                ':enddat' => $endDat,
                ':status' => $status,
                ':comments' => $comments,
                ':kg' => $bobinaKg,
                ':mmaq' => $metrosMaquina,
                ':mlin' => $metrosLineales,
                ':mtype' => $metroType,
                ':amt2' => $amount2,
                ':overr' => $overrideEmbalaje,
                ':frente' => $amountPulpoFrente,
                ':dorso' => $amountPulpoDorso,
            ]);
            $eventId = (int)$this->erpPdo->lastInsertId();
        }

        // Consolidación de mermas de desmontaje de bobinas:
        // Si el evento final no trae alguna merma de desmontaje previamente registrada, cargarla automáticamente
        if ($workerOtId > 0) {
            $dismount = $this->getRollDismountMermas($workerOtId);
            foreach ($dismount as $dtid => $dinfo) {
                if (empty($mermas[$dtid]) || ((float)($mermas[$dtid]['amount'] ?? 0) <= 0 && (float)($mermas[$dtid]['kgs'] ?? 0) <= 0 && (int)($mermas[$dtid]['mts'] ?? 0) <= 0)) {
                    $mermas[$dtid] = [
                        'amount' => (float)($dinfo['amount'] ?? 0),
                        'kgs' => (float)($dinfo['kgs'] ?? 0),
                        'mts' => (int)($dinfo['mts'] ?? 0),
                        'comments' => (string)($dinfo['comments'] ?? ''),
                    ];
                }
            }
        }

        if ($eventId > 0 && (!empty($mermas) || !empty($repairs))) {
            $this->saveEventDefectUnits($eventId, $mermas, $repairs);
        }

        // Desactivar registros intermedios de 'merma' de desmontaje para no duplicar en reportes
        if ($workerOtId > 0 && $eventId > 0) {
            try {
                $stCleanDefects = $this->erpPdo->prepare("
                    UPDATE prod_worker_ot_defectunits d
                    INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                    SET d.evt_status = 0
                    WHERE e.evt_prod_worker_otid = :ot_id
                      AND e.evt_type = 'merma'
                      AND e.id != :cur_eid
                ");
                $stCleanDefects->execute([':ot_id' => $workerOtId, ':cur_eid' => $eventId]);

                $stCleanEvents = $this->erpPdo->prepare("
                    UPDATE prod_worker_ot_events
                    SET evt_status = 2, evt_enddat = :now
                    WHERE evt_prod_worker_otid = :ot_id
                      AND evt_type = 'merma'
                      AND id != :cur_eid
                ");
            } catch (Throwable) {}
        }

        // Consolidar y cerrar eventos intermedios de bobina semielaborada para no duplicar kilos ni metros en reportes
        if ($workerOtId > 0 && $eventId > 0) {
            $prSummary = $this->getPrintedRollsSummary($workerOtId);
            if (!empty($prSummary['rolls_count']) && $prSummary['rolls_count'] > 0) {
                try {
                    $this->erpPdo->prepare("
                        UPDATE prod_worker_ot_events
                        SET evt_status = 2,
                            evt_enddat = COALESCE(evt_enddat, :now),
                            prod_bobina_kg = 0,
                            evt_amount = 0
                        WHERE evt_prod_worker_otid = :ot_id
                          AND evt_comments LIKE '%Bobina Semielaborada%'
                          AND id != :cur_eid
                    ")->execute([':now' => $now, ':ot_id' => $workerOtId, ':cur_eid' => $eventId]);
                } catch (Throwable) {}
            }
        }

        try {
            $stmtOt = $this->erpPdo->prepare("SELECT wok_ag_id FROM prod_worker_ot WHERE id = :id");
            $stmtOt->execute([':id' => $workerOtId]);
            $agId = (int)$stmtOt->fetchColumn();
            if ($agId > 0) {
                $this->syncPulpoAgendaStats($agId);
            }
        } catch (Throwable) {}

        return ['ok' => true, 'event_id' => $eventId];
    }

    /**
     * Registra evento de Pausa operativa (`evt_type = 'pause'`).
     */
    public function recordEventPause(
        int $workerOtId,
        int $pauseId,
        string $comments,
        ?int $refId = null,
        bool $isEnd = false
    ): array {
        if ($workerOtId <= 0) {
            return ['ok' => false, 'error' => 'ID de OT inválido.'];
        }

        $now = time();
        if ($refId !== null && $refId > 0) {
            $sql = "UPDATE prod_worker_ot_events
                    SET evt_comments = :comments, evt_pause_id = :pause_id"
                    . ($isEnd ? ", evt_enddat = :enddat" : "")
                    . " WHERE id = :id";
            $params = [
                ':comments' => $comments,
                ':pause_id' => $pauseId,
                ':id' => $refId,
            ];
            if ($isEnd) {
                $params[':enddat'] = $now;
            }
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            return ['ok' => true, 'event_id' => $refId];
        }

        $sql = "INSERT INTO prod_worker_ot_events
                (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments, evt_pause_id)
                VALUES
                (:ot_id, 0, :crtdat, 1, 'pause', :comments, :pause_id)";

        $stmt = $this->erpPdo->prepare($sql);
        $stmt->execute([
            ':ot_id' => $workerOtId,
            ':crtdat' => $now,
            ':comments' => $comments,
            ':pause_id' => $pauseId,
        ]);

        return ['ok' => true, 'event_id' => (int)$this->erpPdo->lastInsertId()];
    }

    /**
     * Finaliza cualquier evento abierto (`evt_enddat = time()`).
     */
    public function endEvent(int $eventId, int $workerOtId, float $amount = 0, string $comments = ''): array
    {
        if ($eventId <= 0) {
            return ['ok' => false, 'error' => 'ID de evento inválido.'];
        }
        $now = time();
        $sql = "UPDATE prod_worker_ot_events 
                SET evt_enddat = :enddat"
                . ($comments !== '' ? ", evt_comments = :comments" : "")
                . ($amount > 0 ? ", evt_amount = :amount" : "")
                . " WHERE id = :id";
        $params = [
            ':enddat' => $now,
            ':id' => $eventId,
        ];
        if ($comments !== '') {
            $params[':comments'] = $comments;
        }
        if ($amount > 0) {
            $params[':amount'] = $amount;
        }
        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Registra evento de Mantención o falla de máquina (`evt_type = 'mantencion'`).
     */
    public function recordEventMantencion(
        int $workerOtId,
        int $equipoMantId,
        int $ubimId,
        string $comments,
        ?int $refId = null,
        bool $isEnd = false
    ): array {
        if ($workerOtId <= 0) {
            return ['ok' => false, 'error' => 'ID de OT inválido.'];
        }

        $now = time();
        if ($refId !== null && $refId > 0) {
            $sql = "UPDATE prod_worker_ot_events
                    SET evt_comments = :comments, evt_equipo_mantid = :mant_id, evt_ubim_id = :ubim_id"
                    . ($isEnd ? ", evt_enddat = :enddat" : "")
                    . " WHERE id = :id";
            $params = [
                ':comments' => $comments,
                ':mant_id' => $equipoMantId,
                ':ubim_id' => $ubimId,
                ':id' => $refId,
            ];
            if ($isEnd) {
                $params[':enddat'] = $now;
            }
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            return ['ok' => true, 'event_id' => $refId];
        }

        $sql = "INSERT INTO prod_worker_ot_events
                (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type,
                 evt_comments, evt_equipo_mantid, evt_ubim_id)
                VALUES
                (:ot_id, 0, :crtdat, 1, 'mantencion', :comments, :mant_id, :ubim_id)";

        $stmt = $this->erpPdo->prepare($sql);
        $stmt->execute([
            ':ot_id' => $workerOtId,
            ':crtdat' => $now,
            ':comments' => $comments,
            ':mant_id' => $equipoMantId,
            ':ubim_id' => $ubimId,
        ]);

        return ['ok' => true, 'event_id' => (int)$this->erpPdo->lastInsertId()];
    }

    /**
     * Registra consumo de material o insumo (telas, bobinas, tintas).
     */
    public function recordMaterialConsumption(
        int $workerOtId,
        int $itemId,
        float $amount,
        string $comments = ''
    ): array {
        if ($workerOtId <= 0 || $itemId <= 0 || $amount <= 0) {
            return ['ok' => false, 'error' => 'Datos de consumo inválidos.'];
        }

        $now = time();
        $sql = "INSERT INTO prod_worker_ot_events
                (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments)
                VALUES
                (:ot_id, :amt, :crtdat, 1, 'materiales', :comments)";

        $stmt = $this->erpPdo->prepare($sql);
        $stmt->execute([
            ':ot_id' => $workerOtId,
            ':amt' => $amount,
            ':crtdat' => $now,
            ':comments' => "Consumo ítem #{$itemId}: " . trim($comments),
        ]);

        return ['ok' => true, 'event_id' => (int)$this->erpPdo->lastInsertId()];
    }

    // =========================================================================
    // SECCIÓN 4.1: TRAZABILIDAD Y CONSUMO DE BOBINAS / MATERIALES (TRZ + ERP)
    // =========================================================================

    /**
     * Lista las bobinas disponibles en stock (recepcionadas en bodegas) para ser consumidas en la OT.
     *
     * @param string|null $filterSku Filtro opcional por SKU o descripción
     * @param int $limit Límite de resultados
     * @return array<int, array<string, mixed>>
     */
    public function listAvailableRollsForProduction(
        ?string $filterSku = null,
        int $limit = 200,
        ?string $targetColor = null,
        int|float|null $targetWidth = null,
        ?string $targetMaterial = null,
        int|float|null $targetGrammage = null
    ): array {
        try {
            $rolls = [];

            // 1. Normalizar material objetivo de la OT (PLA vs PP / TNT vs Otro)
            $tMatUpper = mb_strtoupper(trim((string)$targetMaterial));
            $normTargetMat = '';
            if ($tMatUpper === 'PLA' || $tMatUpper === 'P.L.A' || str_contains($tMatUpper, 'PLA')) {
                $normTargetMat = 'PLA';
            } elseif ($tMatUpper === 'PP' || $tMatUpper === 'TNT' || $tMatUpper === 'TNT60' || str_contains($tMatUpper, 'POLIPROP') || str_contains($tMatUpper, 'NON WOVEN')) {
                $normTargetMat = 'PP';
            } elseif ($tMatUpper !== '') {
                $normTargetMat = $tMatUpper;
            }

            // 2. Extraer palabras clave de color objetivo de la OT (limpiando códigos Pantone y puntuación)
            $cleanCol = mb_strtolower(trim((string)$targetColor));
            $cleanCol = preg_replace('/\(pantone[^\)]*\)/i', '', $cleanCol);
            $cleanCol = preg_replace('/[^a-záéíóúñ\s]/u', ' ', $cleanCol);
            $rawWords = array_filter(array_map('trim', explode(' ', $cleanCol)), fn($w) => mb_strlen($w) >= 3);

            $colorBases = [
                'blanco', 'negro', 'azul', 'rojo', 'verde', 'amarillo', 'naranjo', 'naranja',
                'beige', 'crudo', 'natural', 'gris', 'morado', 'fucsia', 'celeste', 'turquesa',
                'transparente', 'cafe', 'café', 'cristal', 'dorado', 'plateado', 'rosa', 'rosado'
            ];
            $colorKeywords = [];
            foreach ($rawWords as $w) {
                if (in_array($w, $colorBases, true)) {
                    $colorKeywords[] = $w;
                }
            }
            if (in_array('crudo', $colorKeywords, true) || in_array('natural', $colorKeywords, true) || in_array('beige', $colorKeywords, true)) {
                $colorKeywords = array_unique(array_merge($colorKeywords, ['natural', 'crudo', 'beige']));
            }
            if (in_array('transparente', $colorKeywords, true) || in_array('cristal', $colorKeywords, true)) {
                $colorKeywords = array_unique(array_merge($colorKeywords, ['transparente', 'cristal']));
            }
            if (in_array('naranja', $colorKeywords, true) || in_array('naranjo', $colorKeywords, true)) {
                $colorKeywords = array_unique(array_merge($colorKeywords, ['naranja', 'naranjo']));
            }
            if (empty($colorKeywords) && !empty($rawWords)) {
                $colorKeywords = array_values($rawWords);
            }

            $targetWidthMm = (int)$targetWidth;
            if ($targetWidthMm > 0 && $targetWidthMm < 100) {
                $targetWidthMm *= 10; // Convertir cm a mm
            }

            // 3. CONSULTAR BOBINAS REALES DESDE LA BASE DE DATOS unibag_unibag (ERP)
            // Solo bobinas de Bodega 100 (MP PLA) y Bodega 200 (MP PP). La bodega 3000 es la que ya está en producción.
            $stWhere = "(css.id IN (5, 11) OR css.st_name LIKE '%100 (MP PLA)%' OR css.st_name LIKE '%200 (MP PP)%') AND css.id NOT IN (16, 26, 27) AND css.st_name NOT LIKE '%3000%'";
            $where = [
                "({$stWhere})",
                "iss.iss_inventory > 0",
                "(i.item_status > 0 OR i.item_status IS NULL)",
                "(i.item_number_prod LIKE 'TEL%' OR i.item_title LIKE 'PLA/%' OR i.item_title LIKE 'PP/%' OR i.item_title LIKE 'TNT/%')"
            ];
            $params = [];

            if ($filterSku !== null && trim($filterSku) !== '') {
                $where[] = "(i.item_number_prod LIKE :q OR i.item_title LIKE :q OR i.item_desc LIKE :q)";
                $params[':q'] = '%' . trim($filterSku) . '%';
            }

            $orderSql = "(CASE WHEN css.id = 5 THEN 1 WHEN css.id = 11 THEN 2 ELSE 3 END), iss.iss_inventory DESC";
            if ($normTargetMat === 'PLA') {
                $orderSql = "(CASE WHEN css.id = 5 OR i.item_title LIKE 'PLA/%' THEN 1 ELSE 2 END), iss.iss_inventory DESC";
            } elseif ($normTargetMat === 'PP') {
                $orderSql = "(CASE WHEN css.id = 11 OR i.item_title LIKE 'PP/%' OR i.item_title LIKE 'TNT/%' THEN 1 ELSE 2 END), iss.iss_inventory DESC";
            }

            $whereSql = implode(' AND ', $where);
            $sql = "SELECT iss.st_id, css.st_name AS warehouse_name,
                           i.id AS item_id, i.item_number, i.item_number_prod AS sku_code,
                           i.item_title AS sku_desc, i.item_desc,
                           i.item_reg_width AS width_cm,
                           iss.iss_inventory AS stock_qty,
                           iss.iss_inventory_reserved AS stock_reserved,
                           (iss.iss_inventory - iss.iss_inventory_reserved) AS available_qty
                    FROM item_shops_storehouses iss
                    JOIN company_shops_storehouses css ON iss.st_id = css.id
                    JOIN item i ON iss.item_id = i.id
                    WHERE {$whereSql}
                    ORDER BY {$orderSql}
                    LIMIT :lim";

            $stmt = $this->erpPdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $erpRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($erpRows as $er) {
                $stId = (int)$er['st_id'];
                $itemId = (int)$er['item_id'];
                $sku = trim((string)$er['sku_code']);
                $title = trim((string)$er['sku_desc']);
                $whName = (string)$er['warehouse_name'];
                $whCode = ($stId === 5 ? '100' : ($stId === 11 ? '200' : (string)$stId));

                // Parsear título para obtener material, color, ancho, gramaje, metros
                $parts = explode('/', $title);
                $titleMat = mb_strtoupper(trim($parts[0] ?? ''));
                $titleColor = trim($parts[1] ?? '');
                $colorClean = preg_replace('/\(pantone[^\)]*\)/i', '', $titleColor);
                $colorClean = trim($colorClean) ?: '-';

                $widthCm = (float)$er['width_cm'];
                $grammage = 80.0;
                $meters = 1100.0;
                if (preg_match('/\/(\d+(?:\.\d+)?)[xX](\d+(?:\.\d+)?)[xX](\d+)/i', $title, $mD)) {
                    if ($widthCm <= 0) $widthCm = (float)$mD[1];
                    $grammage = (float)$mD[2];
                    $meters = (float)$mD[3];
                }

                $widthMm = (int)round($widthCm * 10);
                $weightKg = round(($widthCm / 100) * $meters * ($grammage / 1000), 1);
                if ($weightKg <= 0) {
                    $weightKg = ($titleMat === 'PLA' ? 120.0 : 88.0);
                }

                // Detectar material de la bobina
                $rollMat = 'OTRO';
                if ($titleMat === 'PLA' || $stId === 5 || str_contains($whName, 'PLA')) {
                    $rollMat = 'PLA';
                } elseif ($titleMat === 'PP' || $titleMat === 'TNT' || $stId === 11 || str_contains($whName, 'PP')) {
                    $rollMat = 'PP';
                } elseif (str_starts_with($sku, 'TEL')) {
                    $rollMat = str_contains($title, 'PLA') ? 'PLA' : 'PP';
                }

                // 1. Filtro por Materialidad
                $matMatch = true;
                if ($normTargetMat === 'PLA') {
                    $matMatch = ($rollMat === 'PLA');
                } elseif ($normTargetMat === 'PP') {
                    $matMatch = ($rollMat === 'PP');
                } elseif ($normTargetMat !== '') {
                    $matMatch = str_contains(mb_strtolower($title), mb_strtolower($normTargetMat));
                }

                // 2. Filtro por Color
                $colorMatch = true;
                $rColorLower = mb_strtolower($colorClean);
                $rTitleLower = mb_strtolower($title);
                if (!empty($colorKeywords)) {
                    $colorMatch = false;
                    foreach ($colorKeywords as $kw) {
                        if (str_contains($rColorLower, $kw) || str_contains($rTitleLower, $kw)) {
                            $colorMatch = true;
                            break;
                        }
                    }
                }

                // 3. Filtro por Ancho de Bobina
                $widthMatch = true;
                if ($targetWidthMm > 0) {
                    $widthMatch = ($widthMm > 0 && abs($widthMm - $targetWidthMm) <= 80) || ($widthMm >= $targetWidthMm);
                }

                $isCompatible = ($matMatch && $colorMatch && $widthMatch);
                $score = 0;
                if ($isCompatible) $score += 100;
                if ($matMatch) $score += 40;
                if ($colorMatch) $score += 30;
                if ($widthMatch) $score += 20;

                $availQty = max(0.0, (float)$er['available_qty']);

                $rolls[] = [
                    'id' => "ERP_{$stId}_{$itemId}",
                    'raw_id' => "ERP_{$stId}_{$itemId}",
                    'roll_code' => $sku,
                    'roll_number' => $sku,
                    'item_id' => $itemId,
                    'st_id' => $stId,
                    'sku_code' => $sku,
                    'sku_desc' => $title,
                    'warehouse_code' => $whCode,
                    'warehouse_name' => $whName,
                    'stock_qty' => $availQty,
                    'current_weight_kg' => $weightKg,
                    'weight_kg' => $weightKg,
                    'microns' => (int)$grammage,
                    'width_mm' => $widthMm,
                    'width_cm' => $widthCm,
                    'color' => $colorClean,
                    'meters' => $meters,
                    'status' => 'DISPONIBLE',
                    'roll_material' => $rollMat,
                    'is_from_erp' => true,
                    'is_compatible' => $isCompatible,
                    'is_material_match' => $matMatch,
                    'is_color_match' => $colorMatch,
                    'is_width_match' => $widthMatch,
                    'compatibility_score' => $score,
                    'compatibility_label' => $isCompatible ? '⭐ COMPATIBLE' : '',
                ];
            }

            // 4. Incorporar bobinas físicas ya recibidas en TRZ (no demo, no asignadas)
            if ($this->trzPdo !== null) {
                try {
                    $sqlTrz = "SELECT r.id, r.roll_code, r.roll_code AS roll_number,
                                      r.weight_kg, r.weight_kg AS current_weight_kg,
                                      r.microns, r.width_mm, r.color, r.meters, r.status,
                                      r.purchase_order_id, r.purchase_order_line_id, r.created_at,
                                      s.code AS sku_code, s.description AS sku_desc,
                                      w.code AS warehouse_code, w.name AS warehouse_name
                               FROM rolls r
                               INNER JOIN skus s ON r.sku_id = s.id
                               INNER JOIN warehouses w ON r.warehouse_id = w.id
                               WHERE r.status IN ('RECEIVED', 'IN_PROCESS')
                                 AND r.current_work_order_id IS NULL
                                 AND r.roll_code NOT LIKE 'DEMO-%'
                                  AND (w.code IN ('100', '200') OR w.erp_storehouse_id IN (5, 11) OR w.name LIKE '%100%' OR w.name LIKE '%200%')
                                  AND w.code NOT IN ('3000') AND w.name NOT LIKE '%3000%'
                               ORDER BY r.id DESC LIMIT 50";
                    $stmtTrz = $this->trzPdo->query($sqlTrz);
                    $trzRolls = $stmtTrz ? ($stmtTrz->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

                    foreach ($trzRolls as $tr) {
                        $rColor = mb_strtolower(trim((string)($tr['color'] ?? '')));
                        $rDesc = mb_strtolower(trim((string)($tr['sku_desc'] ?? '')));
                        $rSku = mb_strtolower(trim((string)($tr['sku_code'] ?? '')));
                        $whCode = trim((string)($tr['warehouse_code'] ?? ''));
                        $whName = mb_strtoupper(trim((string)($tr['warehouse_name'] ?? '')));
                        $rWidth = (int)($tr['width_mm'] ?? 0);

                        $rollMat = 'OTRO';
                        if (str_starts_with($rSku, 'pp-') || str_starts_with($rDesc, 'pp ') || str_contains($rDesc, 'pp/') || str_starts_with($rSku, 'tel')) {
                            $rollMat = 'PP';
                        } elseif (str_starts_with($rSku, 'pla-') || str_starts_with($rDesc, 'pla ') || str_contains($rDesc, 'pla/')) {
                            $rollMat = 'PLA';
                        } elseif ($whCode === '100' || str_contains($whName, 'PLA')) {
                            $rollMat = 'PLA';
                        } elseif ($whCode === '200' || str_contains($whName, 'PP')) {
                            $rollMat = 'PP';
                        }
                        $tr['roll_material'] = $rollMat;

                        $matMatch = true;
                        if ($normTargetMat === 'PLA') {
                            $matMatch = ($rollMat === 'PLA');
                        } elseif ($normTargetMat === 'PP') {
                            $matMatch = ($rollMat === 'PP');
                        }

                        $colorMatch = true;
                        if (!empty($colorKeywords)) {
                            $colorMatch = false;
                            foreach ($colorKeywords as $kw) {
                                if (str_contains($rColor, $kw) || str_contains($rDesc, $kw) || str_contains($rSku, $kw)) {
                                    $colorMatch = true;
                                    break;
                                }
                            }
                        }

                        $widthMatch = true;
                        if ($targetWidthMm > 0) {
                            $widthMatch = ($rWidth > 0 && abs($rWidth - $targetWidthMm) <= 80) || ($rWidth >= $targetWidthMm);
                        }

                        $isCompatible = ($matMatch && $colorMatch && $widthMatch);
                        $score = 0;
                        if ($isCompatible) $score += 100;
                        if ($matMatch) $score += 40;
                        if ($colorMatch) $score += 30;
                        if ($widthMatch) $score += 20;

                        $tr['id'] = (int)$tr['id'];
                        $tr['raw_id'] = (string)$tr['id'];
                        $tr['stock_qty'] = 1.0;
                        $tr['is_from_erp'] = false;
                        $tr['is_compatible'] = $isCompatible;
                        $tr['is_material_match'] = $matMatch;
                        $tr['is_color_match'] = $colorMatch;
                        $tr['is_width_match'] = $widthMatch;
                        $tr['compatibility_score'] = $score;
                        $tr['compatibility_label'] = $isCompatible ? '⭐ COMPATIBLE' : '';

                        $rolls[] = $tr;
                    }
                } catch (Throwable) {}
            }

            // 5. Ordenar: primero las compatibles por score descendente y luego por stock disponible
            usort($rolls, static function ($a, $b) {
                $scoreDiff = ($b['compatibility_score'] ?? 0) - ($a['compatibility_score'] ?? 0);
                if ($scoreDiff !== 0) return $scoreDiff;
                return ($b['stock_qty'] ?? 0) <=> ($a['stock_qty'] ?? 0);
            });

            return $rolls;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Calcula la cuota máxima de bobinas/material permitida para la OT activa
     * y determina si se requiere autorización de supervisor para solicitar más.
     */
    public function getWorkOrderMaterialQuota(int $agId, int $workerOtId = 0): array
    {
        $default = [
            'planned_units' => 0,
            'planned_kg' => 0.0,
            'max_allowed_rolls' => 2,
            'assigned_rolls_count' => 0,
            'assigned_kg' => 0.0,
            'requires_supervisor' => false,
            'assigned_rolls' => [],
        ];

        try {
            $stmt = $this->erpPdo->prepare("
                SELECT t0.ag_amount, t1x.fab_mat_gramms, t1x.fab_med_width, t1x.fab_med_height,
                       t1.req_operador_mermaperc, t1.req_operador_tela_width,
                       t1x.fab_type, v1.add_name AS fabric_color
                FROM prod_agenda t0
                INNER JOIN orders t1 ON t0.ag_reqid = t1.id
                INNER JOIN orders_items t1x ON t1.id = t1x.req_id
                LEFT OUTER JOIN tran_comments_vals v1 ON t1x.fab_mat_fabric_color = v1.id
                WHERE t0.id = :agid
                LIMIT 1
            ");
            $stmt->execute([':agid' => $agId]);
            $ot = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ot) {
                return $default;
            }

            $amount = (float)($ot['ag_amount'] ?? 0);
            $gramms = (float)($ot['fab_mat_gramms'] ?? 70);
            $widthCm = (float)($ot['req_operador_tela_width'] ?: ($ot['fab_med_width'] ?? 35));
            $heightCm = (float)($ot['fab_med_height'] ?? 40);
            $mermaPerc = (float)($ot['req_operador_mermaperc'] ?? 5.0);

            $areaBolsaM2 = (2 * ($widthCm / 100) * ($heightCm / 100));
            $pesoUnitarioKg = ($areaBolsaM2 * $gramms) / 1000;
            if ($pesoUnitarioKg <= 0.005) {
                $pesoUnitarioKg = 0.035;
            }

            $plannedKg = round($amount * $pesoUnitarioKg * (1 + ($mermaPerc / 100)), 1);
            $maxAllowedRolls = max(2, (int)ceil($plannedKg / 60));

            $assignedRolls = [];
            $assignedKg = 0.0;
            if ($this->trzPdo !== null && $workerOtId > 0) {
                $stmtRolls = $this->trzPdo->prepare("
                    SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                    FROM rolls r
                    INNER JOIN skus s ON r.sku_id = s.id
                    LEFT JOIN warehouses w ON r.warehouse_id = w.id
                    WHERE (r.current_work_order_id = :ot_id OR (r.source_work_order_id = :ot_id2 AND r.status = 'CONSUMED'))
                    ORDER BY r.id ASC
                ");
                $stmtRolls->execute([':ot_id' => $workerOtId, ':ot_id2' => $workerOtId]);
                $assignedRolls = $stmtRolls->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($assignedRolls as &$ar) {
                    $assignedKg += (float)($ar['weight_kg'] ?? 0);
                    $ar['roll_number'] = $ar['roll_code'];
                    $ar['current_weight_kg'] = $ar['weight_kg'];
                    $ar['entry_weight_kg'] = (float)($ar['weight_kg'] ?? 0);

                    $arId = (int)$ar['id'];
                    $stmtM = $this->trzPdo->prepare("
                        SELECT type, payload, created_at FROM events
                        WHERE type IN ('ROLL_MOUNTED_ON_MACHINE', 'ROLL_UNMOUNTED_FROM_MACHINE')
                          AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.roll_id')) = :rid
                          AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.worker_ot_id')) = :otid
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmtM->execute([':rid' => (string)$arId, ':otid' => (string)$workerOtId]);
                    $lastMount = $stmtM->fetch(PDO::FETCH_ASSOC);
                    $ar['is_mounted'] = ($lastMount && $lastMount['type'] === 'ROLL_MOUNTED_ON_MACHINE');
                    $ar['mounted_at'] = $ar['is_mounted'] ? $lastMount['created_at'] : null;
                    if ($lastMount && !empty($lastMount['payload'])) {
                        $pData = json_decode((string)$lastMount['payload'], true);
                        if (!empty($pData['entry_weight_kg'])) {
                            $ar['entry_weight_kg'] = (float)$pData['entry_weight_kg'];
                        }
                    }

                    $stmtOut = $this->trzPdo->prepare("
                        SELECT type, payload, created_at FROM events
                        WHERE type IN ('ROLL_OUTPUT_COMPLETED', 'ROLL_CONSUMED')
                          AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.roll_id')) = :rid
                          AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.worker_ot_id')) = :otid
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmtOut->execute([':rid' => (string)$arId, ':otid' => (string)$workerOtId]);
                    $lastOut = $stmtOut->fetch(PDO::FETCH_ASSOC);
                    if ($lastOut && !empty($lastOut['payload'])) {
                        $outData = json_decode((string)$lastOut['payload'], true);
                        $ar['output_bobina_kg'] = (float)($outData['output_bobina_kg'] ?? $outData['output_binas_kg'] ?? 0);
                        $ar['output_binas_kg'] = $ar['output_bobina_kg'];
                        $ar['output_roll_id'] = (int)($outData['output_roll_id'] ?? 0);
                        $ar['output_roll_code'] = (string)($outData['output_roll_code'] ?? '');
                        $ar['output_meters'] = (float)($outData['output_meters'] ?? 0);
                        $ar['scrap_kg'] = (float)($outData['scrap_kg'] ?? 0);
                        $ar['leftover_kg'] = (float)($outData['leftover_kg'] ?? 0);
                        $ar['leftover_roll_code'] = (string)($outData['leftover_roll_code'] ?? '');
                        $ar['leftover_roll_id'] = (int)($outData['leftover_roll_id'] ?? 0);
                        if ($ar['leftover_roll_id'] <= 0 && $ar['leftover_roll_code'] !== '') {
                            $stFindSob = $this->trzPdo->prepare("SELECT id FROM rolls WHERE roll_code = :c LIMIT 1");
                            $stFindSob->execute([':c' => $ar['leftover_roll_code']]);
                            $ar['leftover_roll_id'] = (int)$stFindSob->fetchColumn();
                        }
                        $ar['mermas_detail'] = (array)($outData['mermas_detail'] ?? []);
                        $ar['output_at'] = $lastOut['created_at'];
                    }
                }
                unset($ar);
            }

            $assignedCount = count($assignedRolls);
            $requiresSupervisor = ($assignedCount >= $maxAllowedRolls) || ($plannedKg > 0 && $assignedKg >= ($plannedKg * 1.15));

            return [
                'planned_units' => (int)$amount,
                'planned_kg' => $plannedKg,
                'max_allowed_rolls' => $maxAllowedRolls,
                'assigned_rolls_count' => $assignedCount,
                'assigned_kg' => round($assignedKg, 1),
                'requires_supervisor' => $requiresSupervisor,
                'assigned_rolls' => $assignedRolls,
                'target_color' => (string)($ot['fabric_color'] ?? ''),
                'target_material' => (string)($ot['fab_type'] ?? ''),
                'target_width' => $widthCm,
                'target_grammage' => $gramms,
            ];
        } catch (Throwable) {
            return $default;
        }
    }

    /**
     * Solicita una o varias bobinas físicas desde bodega para una Orden de Trabajo.
     * Conecta directamente con la base de datos real unibag_unibag (ERP) e instancía
     * los bultos trazables en TRZ para su posterior montaje y consumo.
     */
    public function requestRollsForWorkOrder(
        int $workerOtId,
        array $rollIds,
        string $comments = '',
        string $operatorName = '',
        string $supervisorUser = '',
        string $supervisorPass = '',
        bool $requiresSupervisor = false,
        array $rollQuantities = []
    ): array {
        if ($workerOtId <= 0 || empty($rollIds)) {
            return ['ok' => false, 'error' => 'Debe seleccionar al menos una bobina para solicitar.'];
        }

        if ($this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Base de datos de trazabilidad no disponible.'];
        }

        // Si se supera la cuota, verificar supervisor una sola vez para el lote
        if ($requiresSupervisor) {
            if (!$this->validateSupervisor($supervisorUser, $supervisorPass)) {
                return ['ok' => false, 'error' => 'Se ha alcanzado el límite de bobinas planificadas para esta OT. Requiere autorización válida de Supervisor (usuario y contraseña incorrectos).'];
            }
        }

        try {
            $assignedCount = 0;
            $lastRollCode = '';
            $lastWeightKg = 0.0;
            $now = time();
            $productionStorehouseId = 16;
            $productionStorehouseName = '3000 INSUMOS EN PRODUCCION';

            try {
                $stmtProdWh = $this->erpPdo->query("
                    SELECT id, st_name
                    FROM company_shops_storehouses
                    WHERE id IN (16, 27) OR st_name LIKE '%3000%'
                    ORDER BY CASE WHEN id = 16 THEN 0 ELSE 1 END, id ASC
                    LIMIT 1
                ");
                $prodWh = $stmtProdWh ? $stmtProdWh->fetch(PDO::FETCH_ASSOC) : false;
                if ($prodWh) {
                    $productionStorehouseId = (int)($prodWh['id'] ?? 16);
                    $productionStorehouseName = trim((string)($prodWh['st_name'] ?? '')) ?: $productionStorehouseName;
                }
            } catch (Throwable) {}

            $stmtWh3000 = $this->trzPdo->prepare("
                INSERT INTO warehouses (code, name, erp_storehouse_id)
                VALUES (3000, :name, :erpid)
                ON DUPLICATE KEY UPDATE name = VALUES(name), erp_storehouse_id = VALUES(erp_storehouse_id)
            ");
            $stmtWh3000->execute([
                ':name' => $productionStorehouseName,
                ':erpid' => $productionStorehouseId,
            ]);
            $productionWarehouseId = (int)$this->trzPdo->lastInsertId();
            if ($productionWarehouseId <= 0) {
                $stW3000 = $this->trzPdo->prepare("SELECT id FROM warehouses WHERE code = 3000 LIMIT 1");
                $stW3000->execute();
                $productionWarehouseId = (int)$stW3000->fetchColumn();
            }

            foreach ($rollIds as $rawRollId) {
                $rawRollId = trim((string)$rawRollId);
                if ($rawRollId === '') continue;

                // CASO 1: Bobina seleccionada directamente desde el stock del ERP (unibag_unibag)
                if (preg_match('/^ERP_(\d+)_(\d+)$/', $rawRollId, $mErp)) {
                    $stId = (int)$mErp[1];
                    $itemId = (int)$mErp[2];

                    $stmtErpItem = $this->erpPdo->prepare("
                        SELECT i.*, iss.iss_inventory, css.st_name
                        FROM item i
                        JOIN item_shops_storehouses iss ON i.id = iss.item_id AND iss.st_id = :stid
                        JOIN company_shops_storehouses css ON iss.st_id = css.id
                        WHERE i.id = :itid
                        LIMIT 1
                    ");
                    $stmtErpItem->execute([':stid' => $stId, ':itid' => $itemId]);
                    $erpItem = $stmtErpItem->fetch(PDO::FETCH_ASSOC);
                    if (!$erpItem) continue;

                    $sku = trim((string)$erpItem['item_number_prod']) ?: ('ITEM' . $itemId);
                    $desc = trim((string)$erpItem['item_title']);
                    $whName = (string)$erpItem['st_name'];
                    $whCode = (int)($stId === 5 ? 100 : ($stId === 11 ? 200 : $stId));

                    $parts = explode('/', $desc);
                    $color = trim($parts[1] ?? '-');
                    $color = preg_replace('/\(pantone[^\)]*\)/i', '', $color);
                    $color = trim($color) ?: '-';

                    $widthCm = (float)$erpItem['item_reg_width'];
                    $gramms = 80.0;
                    $meters = 1100.0;
                    if (preg_match('/\/(\d+(?:\.\d+)?)[xX](\d+(?:\.\d+)?)[xX](\d+)/i', $desc, $mD)) {
                        if ($widthCm <= 0) $widthCm = (float)$mD[1];
                        $gramms = (float)$mD[2];
                        $meters = (float)$mD[3];
                    }
                    $widthMm = (int)round($widthCm * 10);
                    $weightKg = round(($widthCm / 100) * $meters * ($gramms / 1000), 1);
                    if ($weightKg <= 0) $weightKg = 88.0;

                    // Asegurar SKU en TRZ
                    $stmtSku = $this->trzPdo->prepare("
                        INSERT INTO skus (code, description, is_active)
                        VALUES (:code, :desc, 1)
                        ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = 1
                    ");
                    $stmtSku->execute([':code' => $sku, ':desc' => $desc]);
                    $skuId = (int)$this->trzPdo->lastInsertId();
                    if ($skuId <= 0) {
                        $stSk = $this->trzPdo->prepare("SELECT id FROM skus WHERE code = :code LIMIT 1");
                        $stSk->execute([':code' => $sku]);
                        $skuId = (int)$stSk->fetchColumn();
                    }

                    $reqQty = isset($rollQuantities[$rawRollId]) ? max(1, (int)$rollQuantities[$rawRollId]) : 1;
                    for ($q = 1; $q <= $reqQty; $q++) {
                        $rollCode = "BOB-{$sku}-" . date('ymd') . "-" . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
                        $insRoll = $this->trzPdo->prepare("
                            INSERT INTO rolls
                                (roll_code, sku_id, warehouse_id, weight_kg, received_qty, reception_mode, microns, width_mm, color, meters, status, current_work_order_id, process_stage, created_at)
                            VALUES
                                (:code, :sku_id, :wh_id, :weight_kg, 1, 'ERP_STOCK', :microns, :width_mm, :color, :meters, 'RECEIVED', NULL, 'PRODUCTION', NOW())
                        ");
                        $insRoll->execute([
                            ':code' => $rollCode,
                            ':sku_id' => $skuId,
                            ':wh_id' => $productionWarehouseId,
                            ':weight_kg' => $weightKg,
                            ':microns' => (int)$gramms,
                            ':width_mm' => $widthMm,
                            ':color' => $color,
                            ':meters' => $meters,
                        ]);
                        $rollId = (int)$this->trzPdo->lastInsertId();
                        $lastRollCode = $rollCode;
                        $lastWeightKg = $weightKg;

                        // Evento en TRZ
                        $stmtEvent = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
                        $stmtEvent->execute([
                            ':type' => 'ROLL_TRANSFERRED_TO_BODEGA_3000',
                            ':payload' => json_encode([
                                'roll_id' => $rollId,
                                'roll_code' => $rollCode,
                                'sku_code' => $sku,
                                'weight_kg' => $weightKg,
                                'requested_worker_ot_id' => $workerOtId,
                                'requested_by' => $operatorName,
                                'supervisor_auth' => $requiresSupervisor ? $supervisorUser : null,
                                'comments' => $comments,
                                'timestamp' => $now,
                                'from_warehouse_code' => $whCode,
                                'from_warehouse_name' => $whName,
                                'to_warehouse_code' => 3000,
                                'to_warehouse_name' => $productionStorehouseName,
                                'action' => 'REQUESTED_TO_BODEGA_3000',
                            ], JSON_UNESCAPED_UNICODE),
                        ]);

                        // Evento en prod_worker_ot_events (ERP remoto)
                        $erpComment = "Solicitud Bobina {$rollCode} [{$sku}] - {$desc} traspasada a Bodega 3000 desde {$whName} por {$operatorName}";
                        if ($requiresSupervisor && $supervisorUser !== '') {
                            $erpComment .= " [Aut. Supervisor: {$supervisorUser}]";
                        }
                        if (trim($comments) !== '') {
                            $erpComment .= " (" . trim($comments) . ")";
                        }

                        $stmtErp = $this->erpPdo->prepare("
                            INSERT INTO prod_worker_ot_events
                             (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                            VALUES
                             (:ot_id, :amt, :crtdat, :enddat, 2, 'materiales', :comments)
                        ");
                        $stmtErp->execute([
                            ':ot_id' => $workerOtId,
                            ':amt' => $weightKg,
                            ':crtdat' => $now,
                            ':enddat' => $now,
                            ':comments' => $erpComment,
                        ]);

                        // Reflejar el traspaso físico al stock de Bodega 3000 en ERP
                        try {
                            if ($stId !== $productionStorehouseId) {
                                $this->erpPdo->prepare("
                                    UPDATE item_shops_storehouses
                                    SET iss_inventory = GREATEST(0, iss_inventory - 1)
                                    WHERE item_id = :itid AND st_id = :stid
                                ")->execute([':itid' => $itemId, ':stid' => $stId]);
                            }

                            $upd3000 = $this->erpPdo->prepare("
                                UPDATE item_shops_storehouses
                                SET iss_inventory = iss_inventory + 1
                                WHERE item_id = :itid AND st_id = :stid
                            ");
                            $upd3000->execute([':itid' => $itemId, ':stid' => $productionStorehouseId]);
                            if ($upd3000->rowCount() === 0) {
                                $shopId = 1;
                                try {
                                    $stShop = $this->erpPdo->query("SELECT id FROM company_shops ORDER BY id ASC LIMIT 1");
                                    $shopId = (int)($stShop ? $stShop->fetchColumn() : 1);
                                    if ($shopId <= 0) {
                                        $shopId = 1;
                                    }
                                } catch (Throwable) {}

                                $this->erpPdo->prepare("
                                    INSERT INTO item_shops_storehouses (item_id, shop_id, st_id, iss_inventory)
                                    VALUES (:itid, :shopid, :stid, 1)
                                ")->execute([
                                    ':itid' => $itemId,
                                    ':shopid' => $shopId,
                                    ':stid' => $productionStorehouseId,
                                ]);
                            }
                        } catch (Throwable) {}

                        $assignedCount++;
                    }
                    continue;
                }

                // CASO 2: Bobina con ID numérico ya existente en TRZ
                $rollId = (int)$rawRollId;
                if ($rollId <= 0) continue;

                $stmt = $this->trzPdo->prepare(
                    "SELECT r.*, s.code AS sku_code, s.description AS sku_desc
                     FROM rolls r
                     INNER JOIN skus s ON r.sku_id = s.id
                     WHERE r.id = :id
                     LIMIT 1"
                );
                $stmt->execute([':id' => $rollId]);
                $roll = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$roll) continue;

                $rollCode = (string)$roll['roll_code'];
                $skuCode = (string)$roll['sku_code'];
                $weightKg = (float)$roll['weight_kg'];
                $lastRollCode = $rollCode;
                $lastWeightKg = $weightKg;

                $updateRoll = $this->trzPdo->prepare(
                    "UPDATE rolls
                     SET status = 'RECEIVED',
                         current_work_order_id = NULL,
                         warehouse_id = :wh_id,
                         process_stage = 'PRODUCTION'
                     WHERE id = :id"
                );
                $updateRoll->execute([
                    ':wh_id' => $productionWarehouseId,
                    ':id' => $rollId,
                ]);

                $stmtEvent = $this->trzPdo->prepare(
                    "INSERT INTO events (type, payload) VALUES (:type, :payload)"
                );
                $stmtEvent->execute([
                    ':type' => 'ROLL_TRANSFERRED_TO_BODEGA_3000',
                    ':payload' => json_encode([
                        'roll_id' => $rollId,
                        'roll_code' => $rollCode,
                        'sku_code' => $skuCode,
                        'weight_kg' => $weightKg,
                        'requested_worker_ot_id' => $workerOtId,
                        'requested_by' => $operatorName,
                        'supervisor_auth' => $requiresSupervisor ? $supervisorUser : null,
                        'comments' => $comments,
                        'timestamp' => $now,
                        'to_warehouse_code' => 3000,
                        'to_warehouse_name' => $productionStorehouseName,
                        'action' => 'REQUESTED_TO_BODEGA_3000',
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                $erpComment = "Solicitud Bobina {$rollCode} [{$skuCode}] traspasada a Bodega 3000 por {$operatorName}";
                if ($requiresSupervisor && $supervisorUser !== '') {
                    $erpComment .= " [Aut. Supervisor: {$supervisorUser}]";
                }
                if (trim($comments) !== '') {
                    $erpComment .= " (" . trim($comments) . ")";
                }

                $stmtErp = $this->erpPdo->prepare(
                    "INSERT INTO prod_worker_ot_events
                     (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                     VALUES
                     (:ot_id, :amt, :crtdat, :enddat, 2, 'materiales', :comments)"
                );
                $stmtErp->execute([
                    ':ot_id' => $workerOtId,
                    ':amt' => $weightKg,
                    ':crtdat' => $now,
                    ':enddat' => $now,
                    ':comments' => $erpComment,
                ]);

                $assignedCount++;
            }

            return [
                'ok' => true,
                'count' => $assignedCount,
                'roll_code' => $lastRollCode,
                'weight_kg' => $lastWeightKg,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al solicitar bobinas: ' . $e->getMessage()];
        }
    }

    /**
     * Compatibilidad: asigna una bobina individual a la OT
     */
    public function assignRollToWorkOrder(
        int $workerOtId,
        int $rollId,
        float $consumedWeight = 0.0,
        string $comments = '',
        string $operatorName = '',
        string $supervisorUser = '',
        string $supervisorPass = '',
        bool $requiresSupervisor = false
    ): array {
        return $this->requestRollsForWorkOrder(
            $workerOtId,
            [$rollId],
            $comments,
            $operatorName,
            $supervisorUser,
            $supervisorPass,
            $requiresSupervisor
        );
    }

    /**
     * Registra el montaje físico de una bobina en la máquina (desbobinador) solicitando el peso de ingreso.
     */
    public function mountRollOnMachine(int $workerOtId, int $rollId, string $operatorName, float $entryWeightKg = 0.0, string $comments = ''): array
    {
        if ($workerOtId <= 0 || $rollId <= 0) {
            return ['ok' => false, 'error' => 'Parámetros inválidos para montar bobina.'];
        }
        if ($this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Base de datos de trazabilidad no disponible.'];
        }

        try {
            $now = time();
            $stmt = $this->trzPdo->prepare("SELECT roll_code, weight_kg FROM rolls WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $rollId]);
            $roll = $stmt->fetch(PDO::FETCH_ASSOC);
            $rollCode = $roll['roll_code'] ?? "ID #{$rollId}";
            $currentKg = (float)($roll['weight_kg'] ?? 0);
            $actualEntryKg = $entryWeightKg > 0 ? $entryWeightKg : $currentKg;

            // Actualizar peso de la bobina en TRZ al peso de ingreso medido
            if ($entryWeightKg > 0) {
                $this->trzPdo->prepare("UPDATE rolls SET weight_kg = :w, status = 'IN_PROCESS' WHERE id = :id")
                    ->execute([':w' => $entryWeightKg, ':id' => $rollId]);
            } else {
                $this->trzPdo->prepare("UPDATE rolls SET status = 'IN_PROCESS' WHERE id = :id")
                    ->execute([':id' => $rollId]);
            }

            $stmtEvent = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
            $stmtEvent->execute([
                ':type' => 'ROLL_MOUNTED_ON_MACHINE',
                ':payload' => json_encode([
                    'roll_id' => $rollId,
                    'roll_code' => $rollCode,
                    'worker_ot_id' => $workerOtId,
                    'entry_weight_kg' => $actualEntryKg,
                    'operator' => $operatorName,
                    'comments' => $comments,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $comm = "Montaje de Bobina {$rollCode} en máquina (Peso ingreso: {$actualEntryKg} kg) por {$operatorName}";
            if ($comments !== '') {
                $comm .= " [{$comments}]";
            }
            $stmtErp = $this->erpPdo->prepare(
                "INSERT INTO prod_worker_ot_events
                 (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                 VALUES
                 (:ot_id, 0, :crtdat, :enddat, 2, 'materiales', :comments)"
            );
            $stmtErp->execute([
                ':ot_id' => $workerOtId,
                ':crtdat' => $now,
                ':enddat' => $now,
                ':comments' => $comm,
            ]);

            return ['ok' => true, 'roll_code' => $rollCode, 'entry_weight_kg' => $actualEntryKg];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al montar bobina: ' . $e->getMessage()];
        }
    }

    /**
     * Registra la salida de una bobina montada en máquina:
     * - Peso de salida en binas / bobinas producidas ($outputBinasKg)
     * - Mermas de salida generadas por la bobina ($scrapKg)
     * - Sobrante en la bobina ($leftoverKg): si es > 0, genera un nuevo código de bobina
     *   y la registra en Bodega 3000 con trazabilidad vinculada a la bobina madre.
     * - Cierra la bobina original como CONSUMED y registra los eventos en TRZ y ERP.
     */
    public function recordRollOutputAndLeftover(
        int $workerOtId,
        int $rollId,
        float $outputBobinaKg,
        float $scrapKg,
        float $leftoverKg,
        string $comments = '',
        string $operatorName = '',
        array $mermasDetail = [],
        bool $createOutputRoll = false,
        string $outputSkuCode = '',
        float $outputMeters = 0.0
    ): array {
        if ($rollId <= 0 || $workerOtId <= 0) {
            return ['ok' => false, 'error' => 'Parámetros inválidos para registrar salida de bobina.'];
        }
        if ($this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Base de datos de trazabilidad no disponible.'];
        }
        if ($createOutputRoll && $outputBobinaKg <= 0) {
            return ['ok' => false, 'error' => 'Debes registrar el peso de la nueva bobina saliente para generar su etiqueta.'];
        }

        // Si se informaron mermas por tipo pero scrapKg vino en 0, calcular total
        if ($scrapKg <= 0 && !empty($mermasDetail)) {
            $sumKgs = 0.0;
            foreach ($mermasDetail as $md) {
                $sumKgs += (float)($md['kgs'] ?? 0);
            }
            if ($sumKgs > 0) {
                $scrapKg = round($sumKgs, 2);
            }
        }

        try {
            $now = time();
            $stmt = $this->trzPdo->prepare("
                SELECT r.*, s.code AS sku_code, s.description AS sku_desc
                FROM rolls r
                LEFT JOIN skus s ON r.sku_id = s.id
                WHERE r.id = :id LIMIT 1
            ");
            $stmt->execute([':id' => $rollId]);
            $roll = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$roll) {
                return ['ok' => false, 'error' => 'Bobina no encontrada en trazabilidad.'];
            }

            $rollCode = (string)$roll['roll_code'];
            $skuId = (int)($roll['sku_id'] ?? 0);
            $skuCode = trim((string)($roll['sku_code'] ?? 'TEL'));
            $entryWeight = (float)($roll['weight_kg'] ?? 0);

            // Buscar peso de ingreso en el último evento de montaje si existe
            $stM = $this->trzPdo->prepare("
                SELECT payload FROM events
                WHERE type = 'ROLL_MOUNTED_ON_MACHINE'
                  AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.roll_id')) = :rid
                  AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.worker_ot_id')) = :otid
                ORDER BY id DESC LIMIT 1
            ");
            $stM->execute([':rid' => (string)$rollId, ':otid' => (string)$workerOtId]);
            $evMount = $stM->fetch(PDO::FETCH_ASSOC);
            if ($evMount && !empty($evMount['payload'])) {
                $pData = json_decode((string)$evMount['payload'], true);
                if (!empty($pData['entry_weight_kg'])) {
                    $entryWeight = (float)$pData['entry_weight_kg'];
                }
            }

            $leftoverRollCode = '';
            $newRollId = 0;
            $outputRollId = 0;
            $outputRollCode = '';
            $outputMeters = max(0.0, $outputMeters);

            if ($createOutputRoll && $outputBobinaKg > 0) {
                $resolvedOutputSku = trim($outputSkuCode) !== '' ? trim($outputSkuCode) : $skuCode;
                $outputRes = $this->createOutputRollFromWorkOrder(
                    $workerOtId,
                    $rollId,
                    $resolvedOutputSku,
                    $outputBobinaKg,
                    $outputMeters,
                    500,
                    $operatorName
                );
                if (($outputRes['ok'] ?? false) !== true) {
                    return ['ok' => false, 'error' => (string)($outputRes['error'] ?? 'No se pudo generar la bobina saliente.')];
                }
                $outputRollId = (int)($outputRes['child_roll_id'] ?? 0);
                $outputRollCode = (string)($outputRes['child_roll_code'] ?? '');
            }

            // 1. SI HAY SOBRANTE EN LA BOBINA -> GENERAR NUEVO CÓDIGO CON TRAZABILIDAD
            if ($leftoverKg > 0) {
                $dateSuffix = date('ymd');
                $randSuffix = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
                if (str_starts_with(strtoupper($skuCode), 'TEL')) {
                    $leftoverRollCode = "{$skuCode}-SOB-{$dateSuffix}-{$randSuffix}";
                } elseif (str_starts_with(strtoupper($rollCode), 'BOB-')) {
                    $leftoverRollCode = "{$rollCode}-SOB-{$randSuffix}";
                } else {
                    $leftoverRollCode = "BOB-{$skuCode}-SOB-{$dateSuffix}-{$randSuffix}";
                }

                // Resolver Bodega 3000
                $stWh = $this->trzPdo->query("SELECT id FROM warehouses WHERE code IN (3000, 16) OR name LIKE '%3000%' ORDER BY id ASC LIMIT 1");
                $whId = (int)($stWh ? $stWh->fetchColumn() : 0);
                if ($whId <= 0) {
                    $whId = (int)($roll['warehouse_id'] ?? 1);
                }

                // Insertar nueva bobina sobrante en TRZ disponible en Bodega 3000
                $insSob = $this->trzPdo->prepare("
                    INSERT INTO rolls
                        (roll_code, sku_id, warehouse_id, weight_kg, received_qty, reception_mode,
                         status, current_work_order_id, parent_roll_id, source_work_order_id,
                         process_stage, width_mm, microns, color, meters, created_at)
                    VALUES
                        (:code, :sku_id, :wh_id, :weight_kg, 1, 'SOBRANTE',
                         'RECEIVED', NULL, :parent_id, :src_ot,
                         'PRODUCTION', :width, :microns, :color, :meters, NOW())
                ");
                $insSob->execute([
                    ':code' => $leftoverRollCode,
                    ':sku_id' => $skuId,
                    ':wh_id' => $whId,
                    ':weight_kg' => $leftoverKg,
                    ':parent_id' => $rollId,
                    ':src_ot' => $workerOtId,
                    ':width' => (int)($roll['width_mm'] ?? 0),
                    ':microns' => (int)($roll['microns'] ?? 80),
                    ':color' => (string)($roll['color'] ?? ''),
                    ':meters' => round($leftoverKg / max(0.01, ($entryWeight > 0 ? ($entryWeight / max(1, (float)$roll['meters'])) : 0.08)), 1),
                ]);
                $newRollId = (int)$this->trzPdo->lastInsertId();

                // Registrar evento de sobrante en TRZ
                $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)")->execute([
                    ':type' => 'ROLL_LEFTOVER_CREATED',
                    ':payload' => json_encode([
                        'parent_roll_id' => $rollId,
                        'parent_roll_code' => $rollCode,
                        'new_roll_id' => $newRollId,
                        'new_roll_code' => $leftoverRollCode,
                        'leftover_kg' => $leftoverKg,
                        'warehouse' => 'Bodega 3000',
                        'worker_ot_id' => $workerOtId,
                        'operator' => $operatorName,
                        'timestamp' => $now,
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }

            // 2. CERRAR BOBINA MADRE EN TRZ (CONSUMIDA EN LA OT, PERO VINCULADA EN SOURCE PARA HISTORIAL)
            $this->trzPdo->prepare("
                UPDATE rolls
                SET status = 'CONSUMED',
                    source_work_order_id = :src_ot,
                    current_work_order_id = NULL
                WHERE id = :id
            ")->execute([':id' => $rollId, ':src_ot' => $workerOtId]);

            // Kilos consumidos reales de tela = ingreso - sobrante
            $netConsumedKg = max(0.0, round($entryWeight - $leftoverKg, 2));

            // Registrar evento de salida de bobina en TRZ
            $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)")->execute([
                ':type' => 'ROLL_OUTPUT_COMPLETED',
                ':payload' => json_encode([
                    'roll_id' => $rollId,
                    'roll_code' => $rollCode,
                    'entry_weight_kg' => $entryWeight,
                    'output_bobina_kg' => $outputBobinaKg,
                    'output_binas_kg' => $outputBobinaKg,
                    'output_roll_id' => $outputRollId,
                    'output_roll_code' => $outputRollCode,
                    'output_meters' => $outputMeters,
                    'scrap_kg' => $scrapKg,
                    'leftover_kg' => $leftoverKg,
                    'leftover_roll_id' => $newRollId,
                    'leftover_roll_code' => $leftoverRollCode,
                    'mermas_detail' => $mermasDetail,
                    'net_consumed_kg' => $netConsumedKg,
                    'worker_ot_id' => $workerOtId,
                    'operator' => $operatorName,
                    'comments' => $comments,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            // 3. REGISTRAR EVENTOS EN ERP (prod_worker_ot_events)
            $descMat = "Salida Bobina {$rollCode}: Ingreso {$entryWeight} kg -> Bobina: {$outputBobinaKg} kg, Mermas: {$scrapKg} kg";
            if ($leftoverKg > 0) {
                $descMat .= ", Sobrante: {$leftoverKg} kg (Código: {$leftoverRollCode})";
            } else {
                $descMat .= " (Consumida completa)";
            }
            if ($comments !== '') {
                $descMat .= " [{$comments}]";
            }

            $stmtErpMat = $this->erpPdo->prepare("
                INSERT INTO prod_worker_ot_events
                (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                VALUES
                (:ot_id, :amt, :crtdat, :enddat, 2, 'materiales', :comments)
            ");
            $stmtErpMat->execute([
                ':ot_id' => $workerOtId,
                ':amt' => $netConsumedKg,
                ':crtdat' => $now,
                ':enddat' => $now,
                ':comments' => $descMat,
            ]);

            // Si se informaron mermas de salida, registrarlas en el evento de merma de la OT
            $mermaEvtId = 0;
            if ($scrapKg > 0) {
                $stmtErpMerma = $this->erpPdo->prepare("
                    INSERT INTO prod_worker_ot_events
                    (evt_prod_worker_otid, evt_amount, evt_amount2, evt_type, evt_comments, evt_crtdat, evt_enddat, evt_status)
                    VALUES
                    (:ot_id, :amt, 0, 'merma', :comments, :crtdat, :enddat, 2)
                ");
                $stmtErpMerma->execute([
                    ':ot_id' => $workerOtId,
                    ':amt' => $scrapKg,
                    ':comments' => "Merma salida bobina {$rollCode}: {$scrapKg} kg" . ($comments !== '' ? " [{$comments}]" : ""),
                    ':crtdat' => $now,
                    ':enddat' => $now,
                ]);
                $mermaEvtId = (int)$this->erpPdo->lastInsertId();

                // Registrar desglose en prod_worker_ot_defectunits si se proporcionó
                if ($mermaEvtId > 0) {
                    $hasBreakdown = false;
                    if (!empty($mermasDetail)) {
                        foreach ($mermasDetail as $mtId => $mInfo) {
                            $mKgs = (float)($mInfo['kgs'] ?? 0);
                            $mComm = trim((string)($mInfo['comments'] ?? ''));
                            if ($mKgs > 0 || $mComm !== '') {
                                $hasBreakdown = true;
                                $stmtDefect = $this->erpPdo->prepare("
                                    INSERT INTO prod_worker_ot_defectunits
                                    (evt_amount, evt_crtdat, evt_status, evt_type, evt_comments, evt_merma_typeid, evt_refid, evt_kgstounits, evt_mtstounits)
                                    VALUES
                                    (0, :crtdat, 1, 'merma', :comm, :type_id, :ref_id, :kgs, 0)
                                ");
                                $stmtDefect->execute([
                                    ':crtdat' => $now,
                                    ':comm' => $mComm,
                                    ':type_id' => (int)$mtId,
                                    ':ref_id' => $mermaEvtId,
                                    ':kgs' => $mKgs,
                                ]);
                            }
                        }
                    }
                    if (!$hasBreakdown && $scrapKg > 0) {
                        $stmtDefect = $this->erpPdo->prepare("
                            INSERT INTO prod_worker_ot_defectunits
                            (evt_amount, evt_crtdat, evt_status, evt_type, evt_comments, evt_merma_typeid, evt_refid, evt_kgstounits, evt_mtstounits)
                            VALUES
                            (0, :crtdat, 1, 'merma', :comm, 6, :ref_id, :kgs, 0)
                        ");
                        $stmtDefect->execute([
                            ':crtdat' => $now,
                            ':comm' => "Merma salida bobina {$rollCode}",
                            ':ref_id' => $mermaEvtId,
                            ':kgs' => $scrapKg,
                        ]);
                    }
                }
            }

            return [
                'ok' => true,
                'roll_code' => $rollCode,
                'leftover_roll_id' => $newRollId,
                'leftover_roll_code' => $leftoverRollCode,
                'leftover_kg' => $leftoverKg,
                'output_roll_id' => $outputRollId,
                'output_roll_code' => $outputRollCode,
                'output_meters' => $outputMeters,
                'output_bobina_kg' => $outputBobinaKg,
                'output_binas_kg' => $outputBobinaKg,
                'scrap_kg' => $scrapKg,
                'mermas_detail' => $mermasDetail,
                'net_consumed_kg' => $netConsumedKg,
                'message' => "Salida de bobina {$rollCode} registrada exitosamente." . ($leftoverRollCode !== '' ? " Se generó sobrante {$leftoverRollCode} ({$leftoverKg} kg) con trazabilidad en Bodega 3000." : "")
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al registrar salida de bobina: ' . $e->getMessage()];
        }
    }

    /**
     * Registra el desmontaje de una bobina de la máquina.
     */
    public function unmountRollFromMachine(int $workerOtId, int $rollId, string $operatorName): array
    {
        if ($workerOtId <= 0 || $rollId <= 0 || $this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Parámetros inválidos.'];
        }
        try {
            $now = time();
            $stmtRoll = $this->trzPdo->prepare("
                SELECT roll_code, weight_kg, current_work_order_id
                FROM rolls
                WHERE id = :id
                LIMIT 1
            ");
            $stmtRoll->execute([':id' => $rollId]);
            $roll = $stmtRoll->fetch(PDO::FETCH_ASSOC);
            if (!$roll) {
                return ['ok' => false, 'error' => 'Bobina no encontrada.'];
            }

            $rollCode = trim((string)($roll['roll_code'] ?? ''));
            $rollWeightKg = (float)($roll['weight_kg'] ?? 0);
            $currentWorkOrderId = (int)($roll['current_work_order_id'] ?? 0);
            if ($currentWorkOrderId > 0 && $currentWorkOrderId !== $workerOtId) {
                return ['ok' => false, 'error' => 'La bobina pertenece a otra OT activa.'];
            }

            $stmtEvent = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
            $stmtEvent->execute([
                ':type' => 'ROLL_UNMOUNTED_FROM_MACHINE',
                ':payload' => json_encode([
                    'roll_id' => $rollId,
                    'roll_code' => $rollCode,
                    'weight_kg' => $rollWeightKg,
                    'worker_ot_id' => $workerOtId,
                    'operator' => $operatorName,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            if ($this->erpPdo !== null) {
                $entryWeightKg = $rollWeightKg;
                $stmtMount = $this->trzPdo->prepare("
                    SELECT payload
                    FROM events
                    WHERE type = 'ROLL_MOUNTED_ON_MACHINE'
                      AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.roll_id')) = :rid
                      AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.worker_ot_id')) = :otid
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $stmtMount->execute([
                    ':rid' => (string)$rollId,
                    ':otid' => (string)$workerOtId,
                ]);
                $mountEvent = $stmtMount->fetch(PDO::FETCH_ASSOC);
                if ($mountEvent && !empty($mountEvent['payload'])) {
                    $mountPayload = json_decode((string)$mountEvent['payload'], true);
                    if (!empty($mountPayload['entry_weight_kg'])) {
                        $entryWeightKg = (float)$mountPayload['entry_weight_kg'];
                    }
                }

                $evtComment = 'Desmontaje temporal de Bobina '
                    . ($rollCode !== '' ? $rollCode : ('#' . $rollId))
                    . ' desde máquina';
                if ($entryWeightKg > 0) {
                    $evtComment .= ' (Peso ingreso: ' . number_format($entryWeightKg, 2, '.', '') . ' kg)';
                }
                if (trim($operatorName) !== '') {
                    $evtComment .= ' por ' . trim($operatorName);
                }

                $stmtErp = $this->erpPdo->prepare("
                    INSERT INTO prod_worker_ot_events
                    (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                    VALUES
                    (:ot_id, 0, :crtdat, :enddat, 2, 'materiales', :comments)
                ");
                $stmtErp->execute([
                    ':ot_id' => $workerOtId,
                    ':crtdat' => $now,
                    ':enddat' => $now,
                    ':comments' => $evtComment,
                ]);
            }
            return ['ok' => true, 'roll_code' => $rollCode];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al desmontar bobina: ' . $e->getMessage()];
        }
    }

    /**
     * Desvincula una bobina no utilizada de la OT y la devuelve a bodega.
     */
    public function unassignRollFromWorkOrder(int $workerOtId, int $rollId, string $operatorName): array
    {
        if ($workerOtId <= 0 || $rollId <= 0 || $this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Parámetros inválidos.'];
        }
        try {
            $now = time();
            $stmtRoll = $this->trzPdo->prepare("
                SELECT r.roll_code, s.code AS sku_code
                FROM rolls r
                LEFT JOIN skus s ON r.sku_id = s.id
                WHERE r.id = :id AND r.current_work_order_id = :ot_id
                LIMIT 1
            ");
            $stmtRoll->execute([':id' => $rollId, ':ot_id' => $workerOtId]);
            $roll = $stmtRoll->fetch(PDO::FETCH_ASSOC);
            if (!$roll) {
                return ['ok' => false, 'error' => 'La bobina no está tomada por esta OT.'];
            }

            $stmt = $this->trzPdo->prepare("UPDATE rolls SET status = 'RECEIVED', current_work_order_id = NULL WHERE id = :id AND current_work_order_id = :ot_id");
            $stmt->execute([':id' => $rollId, ':ot_id' => $workerOtId]);
            if ($stmt->rowCount() > 0 && !empty($roll['sku_code'])) {
                $this->adjustBodega3000InventoryBySku((string)$roll['sku_code'], 1);
            }

            $stmtEvent = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
            $stmtEvent->execute([
                ':type' => 'ROLL_RETURNED_TO_WAREHOUSE',
                ':payload' => json_encode([
                    'roll_id' => $rollId,
                    'worker_ot_id' => $workerOtId,
                    'operator' => $operatorName,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al devolver bobina: ' . $e->getMessage()];
        }
    }

    /**
     * Registra el consumo real de una bobina y el eventual retazo que se devuelve a bodega,
     * garantizando la trazabilidad total del insumo.
     */
    public function recordRollConsumptionAndScrap(
        int $workerOtId,
        int $rollId,
        float $consumedKg,
        float $scrapKg,
        string $comments = '',
        string $operatorName = ''
    ): array {
        if ($rollId <= 0 || $workerOtId <= 0) {
            return ['ok' => false, 'error' => 'Parámetros inválidos.'];
        }

        try {
            $now = time();
            $stmt = $this->trzPdo->prepare("SELECT * FROM rolls WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $rollId]);
            $roll = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$roll) {
                return ['ok' => false, 'error' => 'Bobina no encontrada.'];
            }

            $originalWeight = (float)($roll['weight_kg'] ?? 0);
            $rollCode = (string)$roll['roll_code'];

            $newStatus = ($scrapKg > 0) ? 'RETURNED' : 'CONSUMED';
            $upd = $this->trzPdo->prepare("
                UPDATE rolls
                SET status = :st,
                    weight_kg = :scrap_kg
                WHERE id = :id
            ");
            $upd->execute([
                ':st' => $newStatus,
                ':scrap_kg' => $scrapKg,
                ':id' => $rollId,
            ]);

            // Registrar evento en TRZ
            $evStmt = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
            $evStmt->execute([
                ':type' => 'ROLL_CONSUMED',
                ':payload' => json_encode([
                    'roll_id' => $rollId,
                    'roll_code' => $rollCode,
                    'initial_kg' => $originalWeight,
                    'consumed_kg' => $consumedKg,
                    'scrap_kg' => $scrapKg,
                    'worker_ot_id' => $workerOtId,
                    'operator' => $operatorName,
                    'comments' => $comments,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            // Registrar evento en ERP
            $desc = "Consumo Bobina {$rollCode}: {$consumedKg} kg consumidos";
            if ($scrapKg > 0) {
                $desc .= " (Retazo devuelto a bodega: {$scrapKg} kg)";
            }
            if ($comments !== '') {
                $desc .= " - {$comments}";
            }
            $stmtErp = $this->erpPdo->prepare("
                INSERT INTO prod_worker_ot_events
                (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                VALUES
                (:ot_id, :amt, :crtdat, :enddat, 2, 'materiales', :comments)
            ");
            $stmtErp->execute([
                ':ot_id' => $workerOtId,
                ':amt' => $consumedKg,
                ':crtdat' => $now,
                ':enddat' => $now,
                ':comments' => $desc,
            ]);

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Genera una bobina hija (semielaborada) en TRZ a partir del proceso productivo en la OT,
     * vinculándola a la bobina madre y registrando el evento en el ERP remoto.
     */
    public function createOutputRollFromWorkOrder(
        int $workerOtId,
        ?int $parentRollId,
        string $skuCode,
        float $weightKg,
        float $meters,
        int $warehouseCode = 500,
        string $operatorName = ''
    ): array {
        if ($workerOtId <= 0 || $weightKg <= 0) {
            return ['ok' => false, 'error' => 'Peso y OT inválidos para generar bobina semielaborada.'];
        }

        if ($this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Base de datos de trazabilidad no disponible.'];
        }

        try {
            // Resolver o crear el SKU local
            $skuId = 1;
            $stmtSku = $this->trzPdo->prepare("SELECT id FROM skus WHERE code = :code LIMIT 1");
            $stmtSku->execute([':code' => trim($skuCode)]);
            $foundSku = $stmtSku->fetch();
            if ($foundSku) {
                $skuId = (int)$foundSku['id'];
            } else {
                $insSku = $this->trzPdo->prepare("INSERT INTO skus (code, description, is_active) VALUES (:c, :d, 1)");
                $insSku->execute([':c' => trim($skuCode), ':d' => trim($skuCode)]);
                $skuId = (int)$this->trzPdo->lastInsertId();
            }

            // Resolver ID de bodega
            $warehouseId = 1;
            $stmtWh = $this->trzPdo->prepare("SELECT id FROM warehouses WHERE code = :c LIMIT 1");
            $stmtWh->execute([':c' => $warehouseCode]);
            $foundWh = $stmtWh->fetch();
            if ($foundWh) {
                $warehouseId = (int)$foundWh['id'];
            }

            // Obtener especificaciones de la bobina madre si existen
            $parentWidth = null;
            $parentMicrons = null;
            $parentColor = null;
            if ($parentRollId !== null && $parentRollId > 0) {
                $stParent = $this->trzPdo->prepare("SELECT width_mm, microns, color FROM rolls WHERE id = :id LIMIT 1");
                $stParent->execute([':id' => $parentRollId]);
                $pRow = $stParent->fetch(PDO::FETCH_ASSOC);
                if ($pRow) {
                    $parentWidth = $pRow['width_mm'] ?? null;
                    $parentMicrons = $pRow['microns'] ?? null;
                    $parentColor = $pRow['color'] ?? null;
                }
            }

            // Calcular metros aproximados por peso si no vinieron informados
            if ($meters <= 0 && $weightKg > 0) {
                $wCm = ($parentWidth !== null && (float)$parentWidth > 0) ? ((float)$parentWidth / 10.0) : 80.0;
                $g = ($parentMicrons !== null && (float)$parentMicrons > 0) ? (float)$parentMicrons : 80.0;
                $meters = round(($weightKg * 100000.0) / ($wCm * $g));
                if ($meters <= 0) $meters = 1.0;
            }

            // Generar código único de bobina hija
            $stmtMax = $this->trzPdo->query("SELECT MAX(id) FROM rolls");
            $nextNum = ((int)$stmtMax->fetchColumn()) + 1;
            $childRollCode = sprintf('ROLL-SE-%05d', $nextNum);

            // Insertar bobina en TRZ
            $now = time();
            $insRoll = $this->trzPdo->prepare(
                "INSERT INTO rolls
                 (roll_code, sku_id, warehouse_id, weight_kg, received_qty, meters, status, parent_roll_id, source_work_order_id, process_stage, width_mm, microns, color, created_at)
                 VALUES
                 (:code, :sku, :wh, :weight, 1.000, :meters, 'RECEIVED', :parent, :wo_id, 'PRINTED', :w_mm, :mic, :col, NOW())"
            );
            $insRoll->execute([
                ':code' => $childRollCode,
                ':sku' => $skuId,
                ':wh' => $warehouseId,
                ':weight' => number_format($weightKg, 3, '.', ''),
                ':meters' => $meters > 0 ? $meters : null,
                ':parent' => $parentRollId > 0 ? $parentRollId : null,
                ':wo_id' => $workerOtId,
                ':w_mm' => $parentWidth,
                ':mic' => $parentMicrons,
                ':col' => $parentColor,
            ]);

            $childId = (int)$this->trzPdo->lastInsertId();

            // Evento en TRZ
            $stmtEvent = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
            $stmtEvent->execute([
                ':type' => 'CHILD_ROLL_CREATED',
                ':payload' => json_encode([
                    'child_roll_id' => $childId,
                    'child_roll_code' => $childRollCode,
                    'parent_roll_id' => $parentRollId,
                    'weight_kg' => $weightKg,
                    'meters' => $meters,
                    'worker_ot_id' => $workerOtId,
                    'operator' => $operatorName,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            // Evento en ERP remoto
            $stmtErp = $this->erpPdo->prepare(
                "INSERT INTO prod_worker_ot_events
                 (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_amount_metros_lineales, evt_metrotype, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                 VALUES
                 (:ot_id, :amt, :kg, :mlin, 'metros_lineales', :crtdat, :enddat, 2, 'prod', :comments)"
            );
            $stmtErp->execute([
                ':ot_id' => $workerOtId,
                ':amt' => $meters > 0 ? $meters : $weightKg,
                ':kg' => $weightKg,
                ':mlin' => $meters,
                ':crtdat' => $now,
                ':enddat' => $now,
                ':comments' => "Bobina Semielaborada {$childRollCode} ({$weightKg} kg, {$meters} m)",
            ]);

            return [
                'ok' => true,
                'child_roll_id' => $childId,
                'child_roll_code' => $childRollCode,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al crear bobina hija: ' . $e->getMessage()];
        }
    }

    /**
     * Genera cajas de producto terminado vinculadas a la OT activa en TRZ y ERP,
     * asociando la bobina de origen (source_roll_id) y opcionalmente un Pallet contenedor.
     */
    public function createOutputBoxesFromWorkOrder(
        int $workerOtId,
        string $skuFinal,
        int $boxQty,
        int $unitsPerBox,
        string $operatorName = '',
        ?int $sourceRollId = null,
        ?string $customerOrderRef = null,
        int $boxesPerPallet = 0,
        string $destinationMode = 'STOCK',
        int $warehouseId = 1
    ): array {
        if ($workerOtId <= 0 || $boxQty <= 0 || $unitsPerBox <= 0) {
            return ['ok' => false, 'error' => 'Datos de cajas inválidos.'];
        }

        if ($this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Base de datos de trazabilidad no disponible.'];
        }

        try {
            $createdBoxes = [];
            $createdBoxIds = [];
            $now = time();
            $totalUnits = $boxQty * $unitsPerBox;

            // Auto-detectar bobina de origen montada en la OT si no fue especificada
            if ($sourceRollId === null || $sourceRollId <= 0) {
                $stmtAct = $this->trzPdo->prepare("
                    SELECT id FROM rolls
                    WHERE current_work_order_id = :otid
                    ORDER BY id DESC LIMIT 1
                ");
                $stmtAct->execute([':otid' => $workerOtId]);
                $rId = (int)$stmtAct->fetchColumn();
                if ($rId > 0) {
                    $sourceRollId = $rId;
                } else {
                    $stmtAct2 = $this->trzPdo->prepare("
                        SELECT id FROM rolls
                        WHERE source_work_order_id = :otid
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmtAct2->execute([':otid' => $workerOtId]);
                    $rId2 = (int)$stmtAct2->fetchColumn();
                    if ($rId2 > 0) {
                        $sourceRollId = $rId2;
                    }
                }
            }

            // Crear Pallet si fue solicitado
            $palletId = null;
            $palletCode = null;
            if ($boxesPerPallet > 0) {
                $stmtMaxPal = $this->trzPdo->query("SELECT MAX(id) FROM pallets");
                $nextPalNum = ((int)$stmtMaxPal->fetchColumn()) + 1;
                $palletCode = sprintf('PAL-%06d', $nextPalNum);

                $insPal = $this->trzPdo->prepare("
                    INSERT INTO pallets
                    (pallet_code, work_order_id, source_roll_id, final_sku, destination_mode, customer_order_ref, warehouse_id, box_count, operator_name, status)
                    VALUES
                    (:code, :wo_id, :roll_id, :sku, :dest, :cust_ref, :wh, :box_count, :op, 'OPEN')
                ");
                $insPal->execute([
                    ':code' => $palletCode,
                    ':wo_id' => $workerOtId,
                    ':roll_id' => $sourceRollId > 0 ? $sourceRollId : null,
                    ':sku' => trim($skuFinal) !== '' ? trim($skuFinal) : 'PRODUCTO-TERMINADO',
                    ':dest' => $destinationMode,
                    ':cust_ref' => $customerOrderRef,
                    ':wh' => $warehouseId,
                    ':box_count' => $boxQty,
                    ':op' => $operatorName,
                ]);
                $palletId = (int)$this->trzPdo->lastInsertId();
            }

            $insBox = $this->trzPdo->prepare(
                "INSERT INTO boxes
                 (box_code, work_order_id, source_roll_id, pallet_id, final_sku, units_qty, destination_mode, customer_order_ref, warehouse_id, operator_name, status)
                 VALUES
                 (:code, :wo_id, :roll_id, :pallet_id, :sku, :units, :dest, :cust_ref, :wh, :op, 'CREATED')"
            );

            for ($i = 1; $i <= $boxQty; $i++) {
                $stmtMax = $this->trzPdo->query("SELECT MAX(id) FROM boxes");
                $nextNum = ((int)$stmtMax->fetchColumn()) + 1;
                $boxCode = sprintf('BOX-%06d', $nextNum);

                $insBox->execute([
                    ':code' => $boxCode,
                    ':wo_id' => $workerOtId,
                    ':roll_id' => $sourceRollId > 0 ? $sourceRollId : null,
                    ':pallet_id' => $palletId,
                    ':sku' => trim($skuFinal) !== '' ? trim($skuFinal) : 'PRODUCTO-TERMINADO',
                    ':units' => $unitsPerBox,
                    ':dest' => $destinationMode,
                    ':cust_ref' => $customerOrderRef,
                    ':wh' => $warehouseId,
                    ':op' => $operatorName,
                ]);

                $boxId = (int)$this->trzPdo->lastInsertId();
                $createdBoxes[] = $boxCode;
                $createdBoxIds[] = $boxId;
            }

            // Registrar evento de creación de cajas en TRZ
            $stmtEvt = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
            $stmtEvt->execute([
                ':type' => 'BOXES_CREATED',
                ':payload' => json_encode([
                    'worker_ot_id' => $workerOtId,
                    'source_roll_id' => $sourceRollId,
                    'pallet_id' => $palletId,
                    'pallet_code' => $palletCode,
                    'boxes' => $createdBoxes,
                    'box_count' => $boxQty,
                    'units_per_box' => $unitsPerBox,
                    'total_units' => $totalUnits,
                    'operator' => $operatorName,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            // Registrar evento de producción en ERP remoto
            $stmtErp = $this->erpPdo->prepare(
                "INSERT INTO prod_worker_ot_events
                 (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments)
                 VALUES
                 (:ot_id, :amt, :crtdat, 1, 'prod', :comments)"
            );
            $stmtErp->execute([
                ':ot_id' => $workerOtId,
                ':amt' => $totalUnits,
                ':crtdat' => $now,
                ':comments' => "{$boxQty} Cajas generadas (" . implode(', ', array_slice($createdBoxes, 0, 3)) . ($boxQty > 3 ? '...' : '') . ") - {$totalUnits} unidades totales" . ($palletCode ? " [Pallet: {$palletCode}]" : ''),
            ]);

            return [
                'ok' => true,
                'box_count' => $boxQty,
                'total_units' => $totalUnits,
                'boxes' => $createdBoxes,
                'box_ids' => $createdBoxIds,
                'pallet_id' => $palletId,
                'pallet_code' => $palletCode,
                'source_roll_id' => $sourceRollId,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al registrar cajas: ' . $e->getMessage()];
        }
    }

    /**
     * Construye el árbol completo de trazabilidad "Inicio a Fin" a partir de cualquier código:
     * Código de Bobina (ROLL-*), Código de Caja (BOX-*), Código de Pallet (PAL-*), Número de OT o Número de OC.
     */
    public function getTraceabilityTree(string $queryCode): ?array
    {
        $queryCode = trim($queryCode);
        if ($queryCode === '' || $this->trzPdo === null) {
            return null;
        }

        $result = [
            'query' => $queryCode,
            'warehouse_entry' => null,
            'purchase_order' => null,
            'supplier' => null,
            'roll' => null,
            'parent_roll' => null,
            'virgin_roll' => null,
            'child_rolls' => [],
            'pallet' => null,
            'pallets' => [],
            'box' => null,
            'boxes' => [],
            'work_order' => null,
            'events' => [],
            'movements' => [],
        ];

        // 0. ¿Es un código de ingreso de trazabilidad en bodega?
        $lookupRollId = 0;
        $lookupBoxId = 0;
        $lookupPalletId = 0;

        $stmtWh = $this->trzPdo->prepare("SELECT * FROM traceability_warehouse_entries WHERE traceability_code = :q OR id = :id LIMIT 1");
        $stmtWh->execute([':q' => $queryCode, ':id' => is_numeric($queryCode) ? (int)$queryCode : 0]);
        $whEntry = $stmtWh->fetch();
        if ($whEntry) {
            $result['warehouse_entry'] = $whEntry;
            try {
                $stmtMov = $this->trzPdo->prepare(
                    "SELECT * FROM movements WHERE JSON_EXTRACT(payload, '$.traceability_code') = :code OR (entity_type = :etype AND entity_id = :eid) ORDER BY id DESC"
                );
                $stmtMov->execute([':code' => $whEntry['traceability_code'], ':etype' => $whEntry['entity_type'], ':eid' => (int)($whEntry['entity_id'] ?? 0)]);
                $result['movements'] = $stmtMov->fetchAll();
            } catch (Throwable) {}

            if ($whEntry['entity_type'] === 'ROLL' && !empty($whEntry['entity_id'])) {
                $lookupRollId = (int)$whEntry['entity_id'];
            } elseif ($whEntry['entity_type'] === 'BOX' && !empty($whEntry['entity_id'])) {
                $lookupBoxId = (int)$whEntry['entity_id'];
            } elseif ($whEntry['entity_type'] === 'PALLET' && !empty($whEntry['entity_id'])) {
                $lookupPalletId = (int)$whEntry['entity_id'];
            }
        }

        // 1. ¿Es una bobina?
        $stmtRoll = $this->trzPdo->prepare(
            "SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
             FROM rolls r
             INNER JOIN skus s ON r.sku_id = s.id
             INNER JOIN warehouses w ON r.warehouse_id = w.id
             WHERE r.roll_code = :q OR r.id = :id OR (r.id = :lookup_rid AND :lookup_rid > 0)
             LIMIT 1"
        );
        $stmtRoll->execute([':q' => $queryCode, ':id' => is_numeric($queryCode) ? (int)$queryCode : 0, ':lookup_rid' => $lookupRollId]);
        $roll = $stmtRoll->fetch();

        if ($roll) {
            $result['roll'] = $roll;
            if ($result['warehouse_entry'] === null) {
                $stmtWhRoll = $this->trzPdo->prepare("SELECT * FROM traceability_warehouse_entries WHERE (entity_type = 'ROLL' AND entity_id = :eid) OR traceability_code = :code LIMIT 1");
                $stmtWhRoll->execute([':eid' => (int)$roll['id'], ':code' => (string)$roll['roll_code']]);
                $result['warehouse_entry'] = $stmtWhRoll->fetch() ?: null;
            }
            if (empty($result['movements'])) {
                try {
                    $stmtMov = $this->trzPdo->prepare("SELECT * FROM movements WHERE (entity_type = 'ROLL' AND entity_id = :eid) OR JSON_EXTRACT(payload, '$.traceability_code') = :code ORDER BY id DESC");
                    $stmtMov->execute([':eid' => (int)$roll['id'], ':code' => (string)$roll['roll_code']]);
                    $result['movements'] = $stmtMov->fetchAll();
                } catch (Throwable) {}
            }

            // Buscar bobina madre si esta bobina es hija
            $parentRollId = (int)($roll['parent_roll_id'] ?? 0);
            if ($parentRollId > 0) {
                $stmtParent = $this->trzPdo->prepare(
                    "SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                     FROM rolls r
                     INNER JOIN skus s ON r.sku_id = s.id
                     LEFT JOIN warehouses w ON r.warehouse_id = w.id
                     WHERE r.id = :pid LIMIT 1"
                );
                $stmtParent->execute([':pid' => $parentRollId]);
                $result['parent_roll'] = $stmtParent->fetch() ?: null;
            }

            // Buscar datos de la OC en ERP si tiene purchase_order_id (en esta bobina o en su madre)
            $poId = (int)($roll['purchase_order_id'] ?? ($result['parent_roll']['purchase_order_id'] ?? 0));
            if ($poId > 0) {
                $stmtPo = $this->erpPdo->prepare(
                    "SELECT po.*, s.supp_company AS supplier_name, s.supp_rut AS supplier_rut, c.country_name
                     FROM supplier_order po
                     LEFT OUTER JOIN supplier s ON po.sord_supplier_id = s.id
                     LEFT OUTER JOIN country c ON s.supp_countryid = c.id
                     WHERE po.id = :id
                     LIMIT 1"
                );
                $stmtPo->execute([':id' => $poId]);
                $poData = $stmtPo->fetch();
                if ($poData) {
                    $result['purchase_order'] = [
                        'id' => (int)$poData['id'],
                        'po_code' => (string)$poData['sord_number'],
                        'date' => (int)$poData['sord_crtdat'] > 0 ? date('d.m.Y', (int)$poData['sord_crtdat']) : '-',
                    ];
                    $result['supplier'] = [
                        'name' => (string)($poData['supplier_name'] ?? 'Proveedor'),
                        'rut' => (string)($poData['supplier_rut'] ?? '-'),
                        'country' => (string)($poData['country_name'] ?? 'Chile'),
                    ];
                }
            }

            // Buscar bobinas hijas
            $stmtChildren = $this->trzPdo->prepare(
                "SELECT r.*, s.code AS sku_code, s.description AS sku_desc
                 FROM rolls r
                 INNER JOIN skus s ON r.sku_id = s.id
                 WHERE r.parent_roll_id = :pid
                 ORDER BY r.id ASC"
            );
            $stmtChildren->execute([':pid' => (int)$roll['id']]);
            $result['child_rolls'] = $stmtChildren->fetchAll();

            // Buscar OT vinculada
            $woId = (int)($roll['current_work_order_id'] ?? $roll['source_work_order_id'] ?? 0);
            if ($woId > 0) {
                $stmtOt = $this->erpPdo->prepare(
                    "SELECT t1.*, t4.prd_number, t5.req_number, t7.equipo_name, t8.type_ant_title,
                            t9.wrk_firstname, t9.wrk_lastname, t3.ag_amount AS planned_qty,
                            (SELECT COALESCE(SUM(e.evt_amount), 0)
                               FROM prod_worker_ot_events e
                              WHERE e.evt_prod_worker_otid = t1.id
                                AND e.evt_type = 'prod'
                                AND e.evt_status > 0) AS total_produced_qty,
                            (SELECT COALESCE(SUM(e.prod_bobina_kg), 0)
                               FROM prod_worker_ot_events e
                              WHERE e.evt_prod_worker_otid = t1.id
                                AND e.evt_type = 'prod'
                                AND e.evt_status > 0) AS total_output_kg,
                            (SELECT COALESCE(SUM(e.evt_amount_metros_lineales), 0)
                               FROM prod_worker_ot_events e
                              WHERE e.evt_prod_worker_otid = t1.id
                                AND e.evt_type = 'prod'
                                AND e.evt_status > 0) AS total_output_meters,
                            (SELECT COALESCE(SUM(d.evt_amount), 0)
                               FROM prod_worker_ot_defectunits d
                               INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                              WHERE e.evt_prod_worker_otid = t1.id
                                AND d.evt_type = 'merma'
                                AND d.evt_status > 0) AS total_merma_units,
                            (SELECT COALESCE(SUM(d.evt_kgstounits), 0)
                               FROM prod_worker_ot_defectunits d
                               INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                              WHERE e.evt_prod_worker_otid = t1.id
                                AND d.evt_type = 'merma'
                                AND d.evt_status > 0) AS total_merma_kg
                     FROM prod_worker_ot t1
                     INNER JOIN prod_worker_init t2 ON t1.wok_init_id = t2.id
                     LEFT OUTER JOIN equipo t7 ON t2.win_equipoid = t7.id
                     LEFT OUTER JOIN equipo_type t8 ON t7.equipo_type_id = t8.id
                     LEFT OUTER JOIN workers t9 ON t2.win_wrkid = t9.id
                     INNER JOIN prod_agenda t3 ON t1.wok_ag_id = t3.id
                     INNER JOIN prod_header t4 ON t3.ag_prdid = t4.id
                     INNER JOIN orders t5 ON t3.ag_reqid = t5.id
                     WHERE t1.id = :id
                     LIMIT 1"
                );
                $stmtOt->execute([':id' => $woId]);
                $otData = $stmtOt->fetch();
                if ($otData) {
                    $result['work_order'] = $otData;
                    $stmtEvts = $this->erpPdo->prepare(
                        "SELECT * FROM prod_worker_ot_events WHERE evt_prod_worker_otid = :otid AND evt_status > 0 ORDER BY id ASC"
                    );
                    $stmtEvts->execute([':otid' => $woId]);
                    $result['events'] = $stmtEvts->fetchAll();
                }

                // Cajas vinculadas a la OT
                $stmtBoxes = $this->trzPdo->prepare(
                    "SELECT * FROM boxes WHERE work_order_id = :woid ORDER BY id ASC"
                );
                $stmtBoxes->execute([':woid' => $woId]);
                $result['boxes'] = $stmtBoxes->fetchAll();
            }

            // Cajas vinculadas directamente a esta bobina por source_roll_id
            $stmtDirectBoxes = $this->trzPdo->prepare(
                "SELECT * FROM boxes WHERE source_roll_id = :rid ORDER BY id ASC"
            );
            $stmtDirectBoxes->execute([':rid' => (int)$roll['id']]);
            $directBoxes = $stmtDirectBoxes->fetchAll();
            if (!empty($directBoxes)) {
                $existingBoxCodes = array_column($result['boxes'], 'box_code');
                foreach ($directBoxes as $db) {
                    if (!in_array($db['box_code'], $existingBoxCodes, true)) {
                        $result['boxes'][] = $db;
                    }
                }
            }

            // Pallets vinculados directamente a esta bobina
            $stmtDirectPallets = $this->trzPdo->prepare(
                "SELECT * FROM pallets WHERE source_roll_id = :rid ORDER BY id ASC"
            );
            $stmtDirectPallets->execute([':rid' => (int)$roll['id']]);
            $result['pallets'] = $stmtDirectPallets->fetchAll();

            return $result;
        }

        // 2. ¿Es una caja (BOX-...)?
        $stmtBox = $this->trzPdo->prepare("SELECT * FROM boxes WHERE box_code = :q OR id = :id OR (id = :lookup_bid AND :lookup_bid > 0) LIMIT 1");
        $stmtBox->execute([':q' => $queryCode, ':id' => is_numeric($queryCode) ? (int)$queryCode : 0, ':lookup_bid' => $lookupBoxId]);
        $box = $stmtBox->fetch();
        if ($box) {
            $result['box'] = $box;
            $result['boxes'] = [$box];

            if (!empty($box['pallet_id'])) {
                $stmtPal = $this->trzPdo->prepare("SELECT * FROM pallets WHERE id = :pid LIMIT 1");
                $stmtPal->execute([':pid' => (int)$box['pallet_id']]);
                $result['pallet'] = $stmtPal->fetch() ?: null;
            }

            if (!empty($box['source_roll_id'])) {
                $stmtR = $this->trzPdo->prepare("
                    SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                    FROM rolls r
                    INNER JOIN skus s ON r.sku_id = s.id
                    LEFT JOIN warehouses w ON r.warehouse_id = w.id
                    WHERE r.id = :rid LIMIT 1
                ");
                $stmtR->execute([':rid' => (int)$box['source_roll_id']]);
                $result['roll'] = $stmtR->fetch() ?: null;

                if (!empty($result['roll']['parent_roll_id'])) {
                    $stmtParent = $this->trzPdo->prepare("
                        SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                        FROM rolls r
                        INNER JOIN skus s ON r.sku_id = s.id
                        LEFT JOIN warehouses w ON r.warehouse_id = w.id
                        WHERE r.id = :pid LIMIT 1
                    ");
                    $stmtParent->execute([':pid' => (int)$result['roll']['parent_roll_id']]);
                    $result['parent_roll'] = $stmtParent->fetch() ?: null;

                    if (!empty($result['parent_roll']['parent_roll_id'])) {
                        $stmtV = $this->trzPdo->prepare("
                            SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                            FROM rolls r
                            INNER JOIN skus s ON r.sku_id = s.id
                            LEFT JOIN warehouses w ON r.warehouse_id = w.id
                            WHERE r.id = :vid LIMIT 1
                        ");
                        $stmtV->execute([':vid' => (int)$result['parent_roll']['parent_roll_id']]);
                        $result['virgin_roll'] = $stmtV->fetch() ?: null;
                    }
                }

                // Resolver datos de compra desde el bulto raíz
                $rootRoll = $result['virgin_roll'] ?? $result['parent_roll'] ?? $result['roll'];
                $poId = (int)($rootRoll['purchase_order_id'] ?? 0);
                if ($poId > 0 && empty($result['purchase_order'])) {
                    $stmtPo = $this->erpPdo->prepare("
                        SELECT po.*, s.supp_company AS supplier_name, s.supp_rut AS supplier_rut, c.country_name
                        FROM supplier_order po
                        LEFT OUTER JOIN supplier s ON po.sord_supplier_id = s.id
                        LEFT OUTER JOIN country c ON s.supp_countryid = c.id
                        WHERE po.id = :id
                        LIMIT 1
                    ");
                    $stmtPo->execute([':id' => $poId]);
                    $poData = $stmtPo->fetch();
                    if ($poData) {
                        $result['purchase_order'] = [
                            'id' => (int)$poData['id'],
                            'po_code' => (string)$poData['sord_number'],
                            'date' => (int)$poData['sord_crtdat'] > 0 ? date('d.m.Y', (int)$poData['sord_crtdat']) : '-',
                        ];
                        $result['supplier'] = [
                            'name' => (string)($poData['supplier_name'] ?? 'Proveedor'),
                            'rut' => (string)($poData['supplier_rut'] ?? '-'),
                            'country' => (string)($poData['country_name'] ?? 'Chile'),
                        ];
                    }
                }
            }

            $woId = (int)($box['work_order_id'] ?? 0);
            if ($woId > 0) {
                $stmtOt = $this->erpPdo->prepare("
                    SELECT t1.*, t4.prd_number, t5.req_number, t7.equipo_name, t8.type_ant_title,
                           t9.wrk_firstname, t9.wrk_lastname, t3.ag_amount AS planned_qty,
                           (SELECT COALESCE(SUM(e.evt_amount), 0)
                              FROM prod_worker_ot_events e
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND e.evt_type = 'prod'
                               AND e.evt_status > 0) AS total_produced_qty,
                           (SELECT COALESCE(SUM(e.prod_bobina_kg), 0)
                              FROM prod_worker_ot_events e
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND e.evt_type = 'prod'
                               AND e.evt_status > 0) AS total_output_kg,
                           (SELECT COALESCE(SUM(e.evt_amount_metros_lineales), 0)
                              FROM prod_worker_ot_events e
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND e.evt_type = 'prod'
                               AND e.evt_status > 0) AS total_output_meters,
                           (SELECT COALESCE(SUM(d.evt_amount), 0)
                              FROM prod_worker_ot_defectunits d
                              INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND d.evt_type = 'merma'
                               AND d.evt_status > 0) AS total_merma_units,
                           (SELECT COALESCE(SUM(d.evt_kgstounits), 0)
                              FROM prod_worker_ot_defectunits d
                              INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND d.evt_type = 'merma'
                               AND d.evt_status > 0) AS total_merma_kg
                    FROM prod_worker_ot t1
                    INNER JOIN prod_worker_init t2 ON t1.wok_init_id = t2.id
                    LEFT OUTER JOIN equipo t7 ON t2.win_equipoid = t7.id
                    LEFT OUTER JOIN equipo_type t8 ON t7.equipo_type_id = t8.id
                    LEFT OUTER JOIN workers t9 ON t2.win_wrkid = t9.id
                    INNER JOIN prod_agenda t3 ON t1.wok_ag_id = t3.id
                    INNER JOIN prod_header t4 ON t3.ag_prdid = t4.id
                    INNER JOIN orders t5 ON t3.ag_reqid = t5.id
                    WHERE t1.id = :id LIMIT 1
                ");
                $stmtOt->execute([':id' => $woId]);
                $result['work_order'] = $stmtOt->fetch() ?: null;

                $stmtEvts = $this->erpPdo->prepare("
                    SELECT * FROM prod_worker_ot_events WHERE evt_prod_worker_otid = :otid AND evt_status > 0 ORDER BY id ASC
                ");
                $stmtEvts->execute([':otid' => $woId]);
                $result['events'] = $stmtEvts->fetchAll() ?: [];
            }

            try {
                $stmtMov = $this->trzPdo->prepare("SELECT * FROM movements WHERE (entity_type = 'BOX' AND entity_id = :bid) OR JSON_EXTRACT(payload, '$.box_code') = :bcode ORDER BY id DESC");
                $stmtMov->execute([':bid' => (int)$box['id'], ':bcode' => (string)$box['box_code']]);
                $result['movements'] = $stmtMov->fetchAll() ?: [];
            } catch (Throwable) {}

            return $result;
        }

        // 2b. ¿Es un pallet (PAL-...)?
        $stmtPallet = $this->trzPdo->prepare("SELECT * FROM pallets WHERE pallet_code = :q OR id = :id OR (id = :lookup_pid AND :lookup_pid > 0) LIMIT 1");
        $stmtPallet->execute([':q' => $queryCode, ':id' => is_numeric($queryCode) ? (int)$queryCode : 0, ':lookup_pid' => $lookupPalletId]);
        $pallet = $stmtPallet->fetch();
        if ($pallet) {
            $result['pallet'] = $pallet;

            $stmtBoxes = $this->trzPdo->prepare("SELECT * FROM boxes WHERE pallet_id = :pid ORDER BY id ASC");
            $stmtBoxes->execute([':pid' => (int)$pallet['id']]);
            $result['boxes'] = $stmtBoxes->fetchAll() ?: [];

            if (!empty($pallet['source_roll_id'])) {
                $stmtR = $this->trzPdo->prepare("
                    SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                    FROM rolls r
                    INNER JOIN skus s ON r.sku_id = s.id
                    LEFT JOIN warehouses w ON r.warehouse_id = w.id
                    WHERE r.id = :rid LIMIT 1
                ");
                $stmtR->execute([':rid' => (int)$pallet['source_roll_id']]);
                $result['roll'] = $stmtR->fetch() ?: null;

                if (!empty($result['roll']['parent_roll_id'])) {
                    $stmtParent = $this->trzPdo->prepare("
                        SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                        FROM rolls r
                        INNER JOIN skus s ON r.sku_id = s.id
                        LEFT JOIN warehouses w ON r.warehouse_id = w.id
                        WHERE r.id = :pid LIMIT 1
                    ");
                    $stmtParent->execute([':pid' => (int)$result['roll']['parent_roll_id']]);
                    $result['parent_roll'] = $stmtParent->fetch() ?: null;

                    if (!empty($result['parent_roll']['parent_roll_id'])) {
                        $stmtV = $this->trzPdo->prepare("
                            SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                            FROM rolls r
                            INNER JOIN skus s ON r.sku_id = s.id
                            LEFT JOIN warehouses w ON r.warehouse_id = w.id
                            WHERE r.id = :vid LIMIT 1
                        ");
                        $stmtV->execute([':vid' => (int)$result['parent_roll']['parent_roll_id']]);
                        $result['virgin_roll'] = $stmtV->fetch() ?: null;
                    }
                }
            }

            $woId = (int)($pallet['work_order_id'] ?? 0);
            if ($woId > 0) {
                $stmtOt = $this->erpPdo->prepare("
                    SELECT t1.*, t4.prd_number, t5.req_number, t7.equipo_name, t8.type_ant_title,
                           t9.wrk_firstname, t9.wrk_lastname, t3.ag_amount AS planned_qty,
                           (SELECT COALESCE(SUM(e.evt_amount), 0)
                              FROM prod_worker_ot_events e
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND e.evt_type = 'prod'
                               AND e.evt_status > 0) AS total_produced_qty,
                           (SELECT COALESCE(SUM(e.prod_bobina_kg), 0)
                              FROM prod_worker_ot_events e
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND e.evt_type = 'prod'
                               AND e.evt_status > 0) AS total_output_kg,
                           (SELECT COALESCE(SUM(e.evt_amount_metros_lineales), 0)
                              FROM prod_worker_ot_events e
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND e.evt_type = 'prod'
                               AND e.evt_status > 0) AS total_output_meters,
                           (SELECT COALESCE(SUM(d.evt_amount), 0)
                              FROM prod_worker_ot_defectunits d
                              INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND d.evt_type = 'merma'
                               AND d.evt_status > 0) AS total_merma_units,
                           (SELECT COALESCE(SUM(d.evt_kgstounits), 0)
                              FROM prod_worker_ot_defectunits d
                              INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                             WHERE e.evt_prod_worker_otid = t1.id
                               AND d.evt_type = 'merma'
                               AND d.evt_status > 0) AS total_merma_kg
                    FROM prod_worker_ot t1
                    INNER JOIN prod_worker_init t2 ON t1.wok_init_id = t2.id
                    LEFT OUTER JOIN equipo t7 ON t2.win_equipoid = t7.id
                    LEFT OUTER JOIN equipo_type t8 ON t7.equipo_type_id = t8.id
                    LEFT OUTER JOIN workers t9 ON t2.win_wrkid = t9.id
                    INNER JOIN prod_agenda t3 ON t1.wok_ag_id = t3.id
                    INNER JOIN prod_header t4 ON t3.ag_prdid = t4.id
                    INNER JOIN orders t5 ON t3.ag_reqid = t5.id
                    WHERE t1.id = :id LIMIT 1
                ");
                $stmtOt->execute([':id' => $woId]);
                $result['work_order'] = $stmtOt->fetch() ?: null;

                $stmtEvts = $this->erpPdo->prepare("
                    SELECT * FROM prod_worker_ot_events WHERE evt_prod_worker_otid = :otid AND evt_status > 0 ORDER BY id ASC
                ");
                $stmtEvts->execute([':otid' => $woId]);
                $result['events'] = $stmtEvts->fetchAll() ?: [];
            }

            try {
                $stmtMov = $this->trzPdo->prepare("SELECT * FROM movements WHERE (entity_type = 'PALLET' AND entity_id = :pid) OR JSON_EXTRACT(payload, '$.pallet_code') = :pcode ORDER BY id DESC");
                $stmtMov->execute([':pid' => (int)$pallet['id'], ':pcode' => (string)$pallet['pallet_code']]);
                $result['movements'] = $stmtMov->fetchAll() ?: [];
            } catch (Throwable) {}

            return $result;
        }

        // 3. ¿Es una OT de producción (por prd_number o id)?
        $cleanOt = $queryCode;
        if (preg_match('#(?:OT|PRD)[\s\-]*(\d+)#i', $queryCode, $mOt)) {
            $cleanOt = $mOt[1];
        }
        $numOt = is_numeric($cleanOt) ? (int)$cleanOt : 0;

        $stmtOtSearch = $this->erpPdo->prepare(
            "SELECT t1.id AS worker_ot_id, t4.prd_number
             FROM prod_worker_ot t1
             INNER JOIN prod_agenda t3 ON t1.wok_ag_id = t3.id
             INNER JOIN prod_header t4 ON t3.ag_prdid = t4.id
             WHERE t4.prd_number = :p_prd OR t4.id = :p_hdr OR t3.id = :p_agd OR t1.id = :p_wot
             ORDER BY t1.id DESC LIMIT 1"
        );
        $stmtOtSearch->execute([
            ':p_prd' => $cleanOt,
            ':p_hdr' => $numOt,
            ':p_agd' => $numOt,
            ':p_wot' => $numOt,
        ]);
        $otRow = $stmtOtSearch->fetch();
        if ($otRow) {
            $woId = (int)$otRow['worker_ot_id'];
            // Buscar rollos vinculados a esta OT
            $stmtRollsInOt = $this->trzPdo->prepare(
                "SELECT roll_code FROM rolls WHERE current_work_order_id = :woid1 OR source_work_order_id = :woid2 LIMIT 1"
            );
            $stmtRollsInOt->execute([':woid1' => $woId, ':woid2' => $woId]);
            $firstRoll = $stmtRollsInOt->fetchColumn();
            if ($firstRoll) {
                return $this->getTraceabilityTree((string)$firstRoll);
            }

            // Si la OT aún no tiene bobinas vinculadas, armar los datos directos de la OT
            $stmtDirectOt = $this->erpPdo->prepare(
                "SELECT t1.*, t4.prd_number, t5.req_number, t7.equipo_name, t8.type_ant_title,
                        t9.wrk_firstname, t9.wrk_lastname
                 FROM prod_worker_ot t1
                 INNER JOIN prod_worker_init t2 ON t1.wok_init_id = t2.id
                 LEFT OUTER JOIN equipo t7 ON t2.win_equipoid = t7.id
                 LEFT OUTER JOIN equipo_type t8 ON t7.equipo_type_id = t8.id
                 LEFT OUTER JOIN workers t9 ON t2.win_wrkid = t9.id
                 INNER JOIN prod_agenda t3 ON t1.wok_ag_id = t3.id
                 INNER JOIN prod_header t4 ON t3.ag_prdid = t4.id
                 INNER JOIN orders t5 ON t3.ag_reqid = t5.id
                 WHERE t1.id = :wo_id
                 LIMIT 1"
            );
            $stmtDirectOt->execute([':wo_id' => $woId]);
            $dOt = $stmtDirectOt->fetch();
            if ($dOt) {
                $result['work_order'] = $dOt;
                $stmtEvts = $this->erpPdo->prepare(
                    "SELECT * FROM prod_worker_ot_events WHERE evt_prod_worker_otid = :otid AND evt_status > 0 ORDER BY id ASC"
                );
                $stmtEvts->execute([':otid' => $woId]);
                $result['events'] = $stmtEvts->fetchAll();

                $stmtBoxes = $this->trzPdo->prepare("SELECT * FROM boxes WHERE work_order_id = :woid ORDER BY id ASC");
                $stmtBoxes->execute([':woid' => $woId]);
                $result['boxes'] = $stmtBoxes->fetchAll();
                return $result;
            }
        }

        // 4. ¿Es una Orden de Compra (OC)?
        if (preg_match('#^OC[\d]+#i', $queryCode) === 1 || is_numeric($queryCode)) {
            $stmtPo = $this->erpPdo->prepare(
                "SELECT po.*, s.supp_company AS supplier_name, s.supp_rut AS supplier_rut, c.country_name
                 FROM supplier_order po
                 LEFT OUTER JOIN supplier s ON po.sord_supplier_id = s.id
                 LEFT OUTER JOIN country c ON s.supp_countryid = c.id
                 WHERE po.sord_number = :p_sord OR po.id = :p_sord_id
                 LIMIT 1"
            );
            $stmtPo->execute([
                ':p_sord' => $queryCode,
                ':p_sord_id' => is_numeric($queryCode) ? (int)$queryCode : 0,
            ]);
            $poData = $stmtPo->fetch();
            if ($poData) {
                $result['purchase_order'] = [
                    'id' => (int)$poData['id'],
                    'po_code' => (string)$poData['sord_number'],
                    'date' => (int)$poData['sord_crtdat'] > 0 ? date('d.m.Y', (int)$poData['sord_crtdat']) : '-',
                ];
                $result['supplier'] = [
                    'name' => (string)($poData['supplier_name'] ?? 'Proveedor'),
                    'rut' => (string)($poData['supplier_rut'] ?? '-'),
                    'country' => (string)($poData['country_name'] ?? 'Chile'),
                ];

                $stmtPoRolls = $this->trzPdo->prepare(
                    "SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
                     FROM rolls r
                     INNER JOIN skus s ON r.sku_id = s.id
                     INNER JOIN warehouses w ON r.warehouse_id = w.id
                     WHERE r.purchase_order_id = :po_id
                     ORDER BY r.id ASC LIMIT 1"
                );
                $stmtPoRolls->execute([':po_id' => (int)$poData['id']]);
                $rFound = $stmtPoRolls->fetch();
                if ($rFound) {
                    $result['roll'] = $rFound;
                }
                return $result;
            }
        }

        return null;
    }

    /**
     * Elimina un evento de la bitácora (soft delete con `evt_status = 0`).
     */
    public function deleteEvent(int $eventId, int $workerOtId): bool
    {
        if ($eventId <= 0 || $workerOtId <= 0) {
            return false;
        }

        $stmt = $this->erpPdo->prepare(
            "UPDATE prod_worker_ot_events SET evt_status = 0 WHERE id = :id AND evt_prod_worker_otid = :ot_id"
        );
        $ok = $stmt->execute([':id' => $eventId, ':ot_id' => $workerOtId]);
        if ($ok) {
            try {
                $stmtOt = $this->erpPdo->prepare("SELECT wok_ag_id FROM prod_worker_ot WHERE id = :id");
                $stmtOt->execute([':id' => $workerOtId]);
                $agId = (int)$stmtOt->fetchColumn();
                if ($agId > 0) {
                    $this->syncPulpoAgendaStats($agId);
                }
            } catch (Throwable) {}
        }
        return $ok;
    }

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
                'worker_responses' => [],
                'super_responses' => [],
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
                'worker_responses' => [],
                'super_responses' => [],
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
        try {
            $stmtS = $this->erpPdo->prepare(
                "SELECT ctr_acid, ctr_res, ctr_ctrusr, ctr_ctrdat FROM prod_worker_ot_autocontrol
                 WHERE ctr_init_id = :ot_id AND ctr_type IN ('supervisor', 'lider')"
            );
            $stmtS->execute([':ot_id' => $workerOtId]);
            $superRows = $stmtS->fetchAll() ?: [];
        } catch (Throwable) {
            $superRows = [];
        }

        $superInputs = count($superRows);
        $superResponses = [];
        $hasUnapproved = false;

        foreach ($superRows as $sr) {
            $superResponses[(int)$sr['ctr_acid']] = (int)$sr['ctr_res'];
            if ((int)$sr['ctr_res'] === 0) {
                $hasUnapproved = true;
            }
        }

        $superPending = ($superInputs > 0 && $hasUnapproved);
        $superApproved = ($superInputs > 0 && !$hasUnapproved);
        // Si no se han enviado registros al supervisor, o si hay pendientes sin aprobar
        $gotoAutocontrol = ($superInputs === 0 || $hasUnapproved);

        return [
            'has_points' => true,
            'points' => $points,
            'goto_autocontrol' => $gotoAutocontrol,
            'worker_completed' => $workerCompleted,
            'super_inputs' => $superInputs,
            'super_pending' => $superPending,
            'super_approved' => $superApproved,
            'worker_responses' => $workerResponses,
            'super_responses' => $superResponses,
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
            $delS = $this->erpPdo->prepare("DELETE FROM prod_worker_ot_autocontrol WHERE ctr_init_id = :ot_id AND ctr_type = 'supervisor'");
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
            $delL = $this->erpPdo->prepare("DELETE FROM prod_worker_ot_autocontrol WHERE ctr_init_id = :ot_id AND ctr_type = 'lider'");
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

            if ($upd->rowCount() === 0 && !empty($selectedAcids)) {
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

    /**
     * Lista los ayudantes activos en turno (`workers`).
     *
     * @return array<array<string, mixed>>
     */
    public function listActiveHelpers(): array
    {
        try {
            $sql = "SELECT id, concat(wrk_firstname, ' ', wrk_lastname) AS ayudantes
                    FROM workers
                    WHERE wrk_cargoid NOT IN (4, 10)
                      AND wrk_turno_state = 1
                      AND wrk_status > 0
                    ORDER BY wrk_firstname ASC";
            $stmt = $this->erpPdo->query($sql);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    // =========================================================================
    // SECCIÓN 6: CATÁLOGOS AUXILIARES (Pausas, Mantenciones, Medidas)
    // =========================================================================

    public function listPauseTypes(): array
    {
        try {
            $stmt = $this->erpPdo->query(
                "SELECT id, pause_name, pause_name AS type_name, pause_code FROM prod_pause_types WHERE pause_status > 0 ORDER BY id ASC"
            );
            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public function listRepairTypes(): array
    {
        try {
            $stmt = $this->erpPdo->query(
                "SELECT id, repair_name FROM prod_repairtypes WHERE repair_status > 0 ORDER BY repair_name ASC"
            );
            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public function listMedidas(): array
    {
        try {
            $stmt = $this->erpPdo->query(
                "SELECT id, med_name FROM prod_medidas WHERE med_status > 0 ORDER BY med_name ASC"
            );
            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    public function listBastidores(): array
    {
        try {
            $stmt = $this->erpPdo->query(
                "SELECT id, codigo, descripcion, valor1 FROM parametros WHERE tabla = 'MESH' ORDER BY codigo ASC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    // =========================================================================
    // SECCIÓN 7: CIERRE DE OT Y AUTORIZACIÓN DE SUPERVISOR
    // =========================================================================

    /**
     * Valida que el supervisor tenga credenciales activas y pertenezca al rol supervisor (grupo 31) o admin (user_type = 1).
     */
    public function validateSupervisor(string $username, string $password): bool
    {
        $username = trim($username);
        $password = trim($password);
        if ($username === '' || $password === '') {
            return false;
        }

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
                            AND t2.group_id IN (31)
                      ) > 0
                  )
                LIMIT 1";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute([
                ':login' => $username,
                ':pass' => md5($password),
            ]);
            $row = $stmt->fetch();
            return is_array($row) && (int)$row['id'] > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Sincroniza y guarda en `prod_agenda` el balance acumulado de Serigrafía Pulpo:
     * - Total pasadas Frente
     * - Total pasadas Dorso
     * - Bolsas terminadas completas
     * - Saldo de bolsas semi-terminadas pendientes (Frente o Dorso)
     */
    public function syncPulpoAgendaStats(int $agId): array
    {
        if ($agId <= 0) {
            return ['ok' => false, 'error' => 'ID de agenda inválido.'];
        }

        try {
            // Verificar o asegurar que las columnas existan en prod_agenda
            try {
                $chk = $this->erpPdo->query("SHOW COLUMNS FROM prod_agenda LIKE 'pulpo_surplus_frente'")->fetch();
                if (!$chk) {
                    $this->erpPdo->exec("ALTER TABLE prod_agenda ADD COLUMN pulpo_surplus_frente DECIMAL(30,2) DEFAULT 0");
                    $this->erpPdo->exec("ALTER TABLE prod_agenda ADD COLUMN pulpo_surplus_dorso DECIMAL(30,2) DEFAULT 0");
                    $this->erpPdo->exec("ALTER TABLE prod_agenda ADD COLUMN pulpo_tot_frente DECIMAL(30,2) DEFAULT 0");
                    $this->erpPdo->exec("ALTER TABLE prod_agenda ADD COLUMN pulpo_tot_dorso DECIMAL(30,2) DEFAULT 0");
                    $this->erpPdo->exec("ALTER TABLE prod_agenda ADD COLUMN pulpo_finished_bags DECIMAL(30,2) DEFAULT 0");
                }
            } catch (Throwable) {}

            $stmt = $this->erpPdo->prepare(
                "SELECT SUM(t3.evt_amount) AS tot_amt,
                        SUM(t3.evt_amount_pulpo_frente) AS tot_frente,
                        SUM(t3.evt_amount_pulpo_dorso) AS tot_dorso
                 FROM prod_worker_ot t2
                 INNER JOIN prod_worker_ot_events t3 ON t3.evt_prod_worker_otid = t2.id
                 WHERE t2.wok_ag_id = :ag_id
                   AND t3.evt_type = 'prod'
                   AND t3.evt_status > 0"
            );
            $stmt->execute([':ag_id' => $agId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $totFrente = (float)($row['tot_frente'] ?? 0);
            $totDorso = (float)($row['tot_dorso'] ?? 0);
            $totAmt = (float)($row['tot_amt'] ?? 0);

            // Verificar si lleva dorso
            $hasDorso = false;
            $stmtColors = $this->erpPdo->prepare("SELECT fab_print_colors_back_1, fab_print_colors_back_2, fab_print_colors_back_3, fab_print_colors_back_4, fab_print_colors_back_5 FROM orders_items WHERE req_id = (SELECT ag_reqid FROM prod_agenda WHERE id = :agid LIMIT 1)");
            $stmtColors->execute([':agid' => $agId]);
            $cRow = $stmtColors->fetch(PDO::FETCH_ASSOC);
            if (is_array($cRow)) {
                for ($ci = 1; $ci <= 5; $ci++) {
                    if (!empty($cRow["fab_print_colors_back_{$ci}"])) {
                        $hasDorso = true;
                        break;
                    }
                }
            }

            if ($hasDorso || $totDorso > 0) {
                $finished = min($totFrente, $totDorso);
                $surplusFrente = max(0.0, $totFrente - $totDorso);
                $surplusDorso = max(0.0, $totDorso - $totFrente);
            } else {
                $finished = $totFrente;
                $surplusFrente = 0.0;
                $surplusDorso = 0.0;
            }

            $stmtUpd = $this->erpPdo->prepare(
                "UPDATE prod_agenda
                 SET pulpo_tot_frente = :tf,
                     pulpo_tot_dorso = :td,
                     pulpo_finished_bags = :fin,
                     pulpo_surplus_frente = :sf,
                     pulpo_surplus_dorso = :sd
                 WHERE id = :ag_id"
            );
            $stmtUpd->execute([
                ':tf' => $totFrente,
                ':td' => $totDorso,
                ':fin' => $finished,
                ':sf' => $surplusFrente,
                ':sd' => $surplusDorso,
                ':ag_id' => $agId,
            ]);

            return [
                'ok' => true,
                'tot_frente' => $totFrente,
                'tot_dorso' => $totDorso,
                'finished' => $finished,
                'surplus_frente' => $surplusFrente,
                'surplus_dorso' => $surplusDorso,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Finaliza la OT tras validación positiva de supervisor (`wok_status = 2`, `wok_enddat = time()`).
     */
    public function closeWorkOrder(int $workerOtId, string $supervisorUser, string $supervisorPass): array
    {
        if ($workerOtId <= 0) {
            return ['ok' => false, 'error' => 'ID de OT inválido.'];
        }

        if (!$this->validateSupervisor($supervisorUser, $supervisorPass)) {
            return ['ok' => false, 'error' => 'Usuario o contraseña de supervisor errónea.'];
        }

        $now = time();
        $stmt = $this->erpPdo->prepare(
            "UPDATE prod_worker_ot
             SET wok_status = 2, wok_enddat = :enddat
             WHERE id = :id"
        );
        $stmt->execute([':enddat' => $now, ':id' => $workerOtId]);

        try {
            // Consolidar mermas de desmontaje en el evento de producción si aún no se vincularon
            $stmtLastProd = $this->erpPdo->prepare("
                SELECT id FROM prod_worker_ot_events
                WHERE evt_prod_worker_otid = :ot_id AND evt_type = 'prod'
                ORDER BY id DESC LIMIT 1
            ");
            $stmtLastProd->execute([':ot_id' => $workerOtId]);
            $lastProdId = (int)$stmtLastProd->fetchColumn();
            if ($lastProdId > 0) {
                $stmtCheckDef = $this->erpPdo->prepare("
                    SELECT COUNT(*) FROM prod_worker_ot_defectunits WHERE evt_refid = :eid AND evt_status > 0
                ");
                $stmtCheckDef->execute([':eid' => $lastProdId]);
                $defCount = (int)$stmtCheckDef->fetchColumn();
                if ($defCount === 0) {
                    $dismount = $this->getRollDismountMermas($workerOtId);
                    $mermasToSave = [];
                    foreach ($dismount as $tid => $dinfo) {
                        $mermasToSave[$tid] = [
                            'amount' => $dinfo['amount'] ?? 0,
                            'kgs' => $dinfo['kgs'] ?? 0,
                            'mts' => $dinfo['mts'] ?? 0,
                            'comments' => $dinfo['comments'] ?? '',
                        ];
                    }
                    if (!empty($mermasToSave)) {
                        $this->saveEventDefectUnits($lastProdId, $mermasToSave, []);
                        $this->erpPdo->prepare("
                            UPDATE prod_worker_ot_defectunits d
                            INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                            SET d.evt_status = 0
                            WHERE e.evt_prod_worker_otid = :ot_id AND e.evt_type = 'merma' AND e.id != :cur_eid
                        ")->execute([':ot_id' => $workerOtId, ':cur_eid' => $lastProdId]);
                    }
                }

                // Consolidar automáticamente peso y metros de las bobinas impresas si el evento tiene 0
                $prSummary = $this->getPrintedRollsSummary($workerOtId);
                if (!empty($prSummary['rolls_count']) && $prSummary['rolls_count'] > 0) {
                    $this->erpPdo->prepare("
                        UPDATE prod_worker_ot_events
                        SET prod_bobina_kg = CASE WHEN prod_bobina_kg <= 0 THEN :pkg ELSE prod_bobina_kg END,
                            evt_amount_metros_lineales = CASE WHEN evt_amount_metros_lineales <= 0 THEN :pmlin ELSE evt_amount_metros_lineales END,
                            evt_amount = CASE WHEN evt_amount <= 0 THEN :pamt ELSE evt_amount END
                        WHERE id = :eid
                    ")->execute([
                        ':pkg' => $prSummary['total_weight_kg'],
                        ':pmlin' => $prSummary['total_meters'],
                        ':pamt' => $prSummary['total_meters'],
                        ':eid' => $lastProdId,
                    ]);

                    $this->erpPdo->prepare("
                        UPDATE prod_worker_ot_events
                        SET evt_status = 2,
                            evt_enddat = COALESCE(evt_enddat, :now),
                            prod_bobina_kg = 0,
                            evt_amount = 0
                        WHERE evt_prod_worker_otid = :ot_id
                          AND evt_comments LIKE '%Bobina Semielaborada%'
                          AND id != :cur_eid
                    ")->execute([':now' => $now, ':ot_id' => $workerOtId, ':cur_eid' => $lastProdId]);
                }
            } else {
                // Si no existía evento previo de producción pero se emitieron bobinas impresas, crear evento final consolidado
                $prSummary = $this->getPrintedRollsSummary($workerOtId);
                if (!empty($prSummary['rolls_count']) && $prSummary['rolls_count'] > 0) {
                    $insEvt = $this->erpPdo->prepare("
                        INSERT INTO prod_worker_ot_events
                        (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_amount_metros_lineales, evt_metrotype, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                        VALUES
                        (:ot_id, :amt, :kg, :mlin, 'metros_lineales', :crtdat, :enddat, 2, 'prod', :comments)
                    ");
                    $insEvt->execute([
                        ':ot_id' => $workerOtId,
                        ':amt' => $prSummary['total_meters'],
                        ':kg' => $prSummary['total_weight_kg'],
                        ':mlin' => $prSummary['total_meters'],
                        ':crtdat' => $now,
                        ':enddat' => $now,
                        ':comments' => "Producción final consolidada ({$prSummary['rolls_count']} bobinas impresas)",
                    ]);
                    $lastProdId = (int)$this->erpPdo->lastInsertId();

                    $this->erpPdo->prepare("
                        UPDATE prod_worker_ot_events
                        SET evt_status = 2,
                            evt_enddat = COALESCE(evt_enddat, :now),
                            prod_bobina_kg = 0,
                            evt_amount = 0
                        WHERE evt_prod_worker_otid = :ot_id
                          AND evt_comments LIKE '%Bobina Semielaborada%'
                          AND id != :cur_eid
                    ")->execute([':now' => $now, ':ot_id' => $workerOtId, ':cur_eid' => $lastProdId]);
                }
            }
        } catch (Throwable) {}

        try {
            $stmtCloseEvt = $this->erpPdo->prepare(
                "UPDATE prod_worker_ot_events
                 SET evt_status = 2, evt_enddat = :enddat
                 WHERE evt_prod_worker_otid = :ot_id AND evt_status = 1"
            );
            $stmtCloseEvt->execute([':enddat' => $now, ':ot_id' => $workerOtId]);
        } catch (Throwable) {}

        try {
            $stmtOt = $this->erpPdo->prepare("SELECT wok_ag_id FROM prod_worker_ot WHERE id = :id");
            $stmtOt->execute([':id' => $workerOtId]);
            $agId = (int)$stmtOt->fetchColumn();
            if ($agId > 0) {
                $this->syncPulpoAgendaStats($agId);
            }
        } catch (Throwable) {}

        return ['ok' => true];
    }

    // =========================================================================
    // SECCIÓN 8: MONITOREO EN TIEMPO REAL E HISTORIAL DE PLANTA
    // =========================================================================

    /**
     * Lista todas las OTs actualmente en ejecución en la planta (`wok_status = 1`).
     */
    public function listActiveOrdersInCourse(int $plantaId, int $equipoId = 0, ?string $dateFilter = null): array
    {
        $wherePlanta = $plantaId > 0 ? " AND t2.win_plantaid = :planta_id" : "";
        $whereEquipo = $equipoId > 0 ? " AND t2.win_equipoid = :equipo_id" : "";

        $whereDate = "";
        $params = [];
        if ($plantaId > 0) {
            $params[':planta_id'] = $plantaId;
        }
        if ($equipoId > 0) {
            $params[':equipo_id'] = $equipoId;
        }

        if ($dateFilter !== null && $dateFilter !== '' && $dateFilter !== 'all') {
            $parsedTs = null;
            if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $dateFilter)) {
                $pParts = explode('.', $dateFilter);
                $parsedTs = mktime(0, 0, 0, (int)$pParts[1], (int)$pParts[0], (int)$pParts[2]);
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
                $pParts = explode('-', $dateFilter);
                $parsedTs = mktime(0, 0, 0, (int)$pParts[1], (int)$pParts[2], (int)$pParts[0]);
                $dateFilter = sprintf('%02d.%02d.%04d', (int)$pParts[2], (int)$pParts[1], (int)$pParts[0]);
            }
            if ($parsedTs !== null && $parsedTs > 0) {
                $dayStart = mktime(0, 0, 0, (int)date('n', $parsedTs), (int)date('j', $parsedTs), (int)date('Y', $parsedTs));
                $dayEnd = $dayStart + 86399;
                $whereDate = " AND (t0.ag_date = :target_date OR (t0.ag_date_stamp BETWEEN :date_start AND :date_end))";
                $params[':target_date'] = $dateFilter;
                $params[':date_start'] = $dayStart;
                $params[':date_end'] = $dayEnd;
            } else {
                $whereDate = " AND t0.ag_date = :target_date";
                $params[':target_date'] = $dateFilter;
            }
        }

        $sql = "SELECT t1.id AS worker_ot_id, t1.wok_crtdat, t1.wok_status,
                       t2.win_equipoid, t7.equipo_name, t8.type_ant_title, t7.equipo_type_id, t7.equipo_prod_isprinter_flexo, t7.equipo_prod_isprinter_seri,
                       t9.wrk_firstname, t9.wrk_lastname,
                       t0.id AS ag_id, t0.ag_amount, t0.ag_active, t0.ag_date, t0.ag_date_stamp,
                       t3x.prd_number,
                       t5.id AS req_id, t5.req_number, t4.cust_name, t2x.item_title, t2x.item_number_prod, t2x.item_prodcalc_fuelle_act,
                       t5.req_operador_tela_width, t5.req_rebo_type, t5.req_rebo_cc_genrefid,
                       t5.req_infoaddprd_cliche_ubicacion, t5.req_infoaddprd_cliche_codigo,
                       t5.req_operador_mermaperc, t5.req_operador_bastidoramt, t5.req_solic_devprints_poltype,
                       t1x.item_amount, t1x.fab_type, t1x.fab_med_width, t1x.fab_med_height, t1x.fab_med_fuelle,
                       t1x.fab_printtype, t1x.fab_design_imagehash, t1x.fab_design_name, t1x.item_img_hash,
                       t1x.fab_print_colordesc_1, t1x.fab_print_colordesc_2, t1x.fab_print_colordesc_3,
                       t1x.fab_print_colordesc_4, t1x.fab_print_colordesc_5, t1x.fab_print_colordesc_6,
                       (SELECT COALESCE(SUM(evt_amount), 0) FROM prod_worker_ot_events WHERE evt_prod_worker_otid = t1.id AND evt_type = 'prod' AND evt_status > 0) AS total_produced
                FROM prod_worker_ot t1
                INNER JOIN prod_worker_init t2 ON t1.wok_init_id = t2.id
                LEFT OUTER JOIN equipo t7 ON t2.win_equipoid = t7.id
                LEFT OUTER JOIN equipo_type t8 ON t7.equipo_type_id = t8.id
                LEFT OUTER JOIN workers t9 ON t2.win_wrkid = t9.id
                INNER JOIN prod_agenda t0 ON t1.wok_ag_id = t0.id
                INNER JOIN prod_header t3x ON t0.ag_prdid = t3x.id
                INNER JOIN orders t5 ON t0.ag_reqid = t5.id
                LEFT OUTER JOIN customer t4 ON t5.req_cust_id = t4.id
                LEFT OUTER JOIN orders_items t1x ON t5.id = t1x.req_id
                LEFT OUTER JOIN item t2x ON t1x.item_id = t2x.id
                WHERE t1.wok_status = 1
                  {$wherePlanta}
                  {$whereEquipo}
                  {$whereDate}
                ORDER BY t1.wok_crtdat DESC";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll();
            foreach ($orders as &$ord) {
                $isRebo = ((int)($ord['req_rebo_cc_genrefid'] ?? 0) > 0)
                    || ((int)($ord['equipo_type_id'] ?? 0) === 12)
                    || stripos((string)($ord['equipo_name'] ?? ''), 'REBO') !== false
                    || stripos((string)($ord['type_ant_title'] ?? ''), 'REBOBIN') !== false;
                if ($isRebo) {
                    $ord['rebo_distribucion'] = $this->getReboDistributionText((int)($ord['req_id'] ?? 0), (float)($ord['req_operador_tela_width'] ?? 0));
                }
            }
            unset($ord);
            $this->attachFlexoMetrics($orders, $equipoId);
            $this->attachSeriMetrics($orders);
            return $orders;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Lista el historial de OTs cerradas en la planta (`wok_status = 2`).
     */
    public function listHistoryOrders(int $plantaId, int $limit = 50): array
    {
        $wherePlanta = $plantaId > 0 ? " AND t2.win_plantaid = :planta_id" : "";
        $sql = "SELECT t1.id AS worker_ot_id, t1.wok_crtdat, t1.wok_enddat, t1.wok_status,
                       t2.win_equipoid, t7.equipo_name, t8.type_ant_title, t7.equipo_type_id,
                       t9.wrk_firstname, t9.wrk_lastname,
                       t0.id AS ag_id, t0.ag_amount,
                       t3x.prd_number,
                       t5.req_number, t4.cust_name, t2x.item_title, t2x.item_number_prod,
                       (SELECT SUM(evt_amount) FROM prod_worker_ot_events WHERE evt_prod_worker_otid = t1.id AND evt_type = 'prod' AND evt_status > 0) AS total_produced
                FROM prod_worker_ot t1
                INNER JOIN prod_worker_init t2 ON t1.wok_init_id = t2.id
                LEFT OUTER JOIN equipo t7 ON t2.win_equipoid = t7.id
                LEFT OUTER JOIN equipo_type t8 ON t7.equipo_type_id = t8.id
                LEFT OUTER JOIN workers t9 ON t2.win_wrkid = t9.id
                INNER JOIN prod_agenda t0 ON t1.wok_ag_id = t0.id
                INNER JOIN prod_header t3x ON t0.ag_prdid = t3x.id
                INNER JOIN orders t5 ON t0.ag_reqid = t5.id
                LEFT OUTER JOIN customer t4 ON t5.req_cust_id = t4.id
                LEFT OUTER JOIN orders_items t1x ON t5.id = t1x.req_id
                LEFT OUTER JOIN item t2x ON t1x.item_id = t2x.id
                WHERE t1.wok_status = 2
                  {$wherePlanta}
                ORDER BY t1.wok_enddat DESC, t1.id DESC
                LIMIT :lim";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            if ($plantaId > 0) {
                $stmt->bindValue(':planta_id', $plantaId, PDO::PARAM_INT);
            }
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Obtiene y auto-inicializa el contexto de Anilox para una OT flexográfica.
     * Replica exactamente editxid.php líneas 90-340.
     */
    public function getAniloxContext(int $agId, int $reqId, int $equipoId, int $userId = 0): array
    {
        $itemAnilox = [];
        $detalleAnilox = [];
        $aniloxDescMap = [];

        try {
            // 1. Catálogo de anilox para la máquina
            $stmtAni = $this->erpPdo->prepare(
                "SELECT i.id, i.item_number_prod, i.item_title
                 FROM item i
                 INNER JOIN item_productcats ip ON i.id = ip.item_id
                 INNER JOIN productcats p ON p.id = ip.cat_id AND p.cat_prefix = 'ANI'
                 INNER JOIN item_equipos_rel ier ON ier.item_id = i.id
                 WHERE i.item_status > 0 AND ier.equipo_id = :eq_id
                 ORDER BY i.item_number_prod ASC"
            );
            $stmtAni->execute([':eq_id' => $equipoId]);
            $itemAnilox = $stmtAni->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Fallback: Si no hay anilox asociados por máquina, cargar todos los anilox activos
            if (empty($itemAnilox)) {
                $stmtFallback = $this->erpPdo->query(
                    "SELECT i.id, i.item_number_prod, i.item_title
                     FROM item i
                     INNER JOIN item_productcats ip ON i.id = ip.item_id
                     INNER JOIN productcats p ON p.id = ip.cat_id AND p.cat_prefix = 'ANI'
                     WHERE i.item_status > 0
                     ORDER BY i.item_number_prod ASC"
                );
                $itemAnilox = $stmtFallback->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            // 2. Verificar si existe cabecera prod_anilox_ot
            $stmtAniOt = $this->erpPdo->prepare(
                "SELECT * FROM prod_anilox_ot
                 WHERE prod_anilox_agid = :ag_id AND prod_anilox_reqid = :req_id
                 LIMIT 1"
            );
            $stmtAniOt->execute([':ag_id' => $agId, ':req_id' => $reqId]);
            $aniOt = $stmtAniOt->fetch(PDO::FETCH_ASSOC);

            // Si no existe, auto-crear según editxid.php 272-330
            if (!is_array($aniOt) || empty($aniOt['id'])) {
                $stmtColors = $this->erpPdo->prepare(
                    "SELECT fab_print_colordesc_1 AS color1,
                            fab_print_colordesc_2 AS color2,
                            fab_print_colordesc_3 AS color3,
                            fab_print_colordesc_4 AS color4,
                            fab_print_colordesc_5 AS color5,
                            fab_print_colordesc_6 AS color6
                     FROM orders_items
                     WHERE req_id = :req_id
                     LIMIT 1"
                );
                $stmtColors->execute([':req_id' => $reqId]);
                $colorsRow = $stmtColors->fetch(PDO::FETCH_ASSOC) ?: [];

                $now = time();
                $stmtInsPao = $this->erpPdo->prepare(
                    "INSERT INTO prod_anilox_ot
                     (prod_anilox_agid, prod_anilox_reqid, prod_anilox_unidad,
                      prod_anilox_fecha_creacion, prod_anilox_user_creacion,
                      prod_anilox_fecha_actualiza, prod_anilox_user_actualiza)
                     VALUES
                     (:ag_id, :req_id, 0, :cdate, :cuser, :udate, :uuser)"
                );
                $stmtInsPao->execute([
                    ':ag_id' => $agId,
                    ':req_id' => $reqId,
                    ':cdate' => $now,
                    ':cuser' => $userId,
                    ':udate' => $now,
                    ':uuser' => $userId,
                ]);
                $stkId = (int)$this->erpPdo->lastInsertId();

                if ($stkId > 0) {
                    $stmtInsWorker = $this->erpPdo->prepare(
                        "INSERT INTO prod_anilox_ot_worker
                         (paow_unidad, paow_posicion, paow_color, paow_anilox, paow_ot_worker_id)
                         VALUES
                         (:u, :p, :color, 0, :wid)"
                    );
                    for ($x = 1; $x <= 6; $x++) {
                        $col = (string)($colorsRow['color' . $x] ?? '');
                        $stmtInsWorker->execute([
                            ':u' => $x,
                            ':p' => $x,
                            ':color' => $col,
                            ':wid' => $stkId,
                        ]);
                    }
                }

                // Reconsultar la cabecera
                $stmtAniOt->execute([':ag_id' => $agId, ':req_id' => $reqId]);
                $aniOt = $stmtAniOt->fetch(PDO::FETCH_ASSOC);
            }

            // 3. Consultar las filas de prod_anilox_ot_worker
            if (is_array($aniOt) && !empty($aniOt['id'])) {
                $stkId = (int)$aniOt['id'];
                $stmtWorkerAni = $this->erpPdo->prepare(
                    "SELECT * FROM prod_anilox_ot_worker
                     WHERE paow_ot_worker_id = :stk_id
                     ORDER BY paow_unidad ASC, paow_posicion ASC"
                );
                $stmtWorkerAni->execute([':stk_id' => $stkId]);
                $detalleAnilox = $stmtWorkerAni->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $aniMap = [];
                foreach ($itemAnilox as $ia) {
                    $aniMap[(int)$ia['id']] = $ia['item_number_prod'] . '-' . $ia['item_title'];
                }

                foreach ($detalleAnilox as $da) {
                    $pos = (int)($da['paow_posicion'] ?? $da['paow_unidad'] ?? 0);
                    $aid = (int)($da['paow_anilox'] ?? 0);
                    if ($aid > 0 && isset($aniMap[$aid])) {
                        $aniloxDescMap[$pos] = $aniMap[$aid];
                    }
                }
            }
        } catch (Throwable) {
            // Ignore errors
        }

        return [
            'item_anilox' => $itemAnilox,
            'detalle_anilox' => $detalleAnilox,
            'anilox_desc_map' => $aniloxDescMap,
        ];
    }

    /**
     * Obtiene los datos de una agenda de producción por su ID.
     */
    public function getAgendaById(int $agId): ?array
    {
        try {
            $stmt = $this->erpPdo->prepare("SELECT * FROM prod_agenda WHERE id = :ag_id LIMIT 1");
            $stmt->execute([':ag_id' => $agId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Guarda las asignaciones de anilox por unidad flexográfica (editxid.php 108-158).
     * Valida que no se seleccione el mismo anilox más de una vez en la misma CC.
     */
    public function saveAniloxAssignments(int $agId, int $reqId, array $aniloxMap, int $userId = 0): array
    {
        try {
            $stkId = 0;
            if ($reqId > 0) {
                $stmt = $this->erpPdo->prepare(
                    "SELECT id FROM prod_anilox_ot
                     WHERE prod_anilox_agid = :ag_id AND prod_anilox_reqid = :req_id
                     LIMIT 1"
                );
                $stmt->execute([':ag_id' => $agId, ':req_id' => $reqId]);
                $stkId = (int)$stmt->fetchColumn();
            }
            if ($stkId <= 0) {
                $stmt = $this->erpPdo->prepare(
                    "SELECT id FROM prod_anilox_ot
                     WHERE prod_anilox_agid = :ag_id
                     LIMIT 1"
                );
                $stmt->execute([':ag_id' => $agId]);
                $stkId = (int)$stmt->fetchColumn();
            }
            if ($stkId <= 0) {
                return ['ok' => false, 'error' => 'No se encontró el registro de anilox para la OT.'];
            }

            // Validar no duplicados entre unidades
            $used = [];
            foreach ($aniloxMap as $unidad => $aniloxId) {
                $aid = (int)$aniloxId;
                if ($aid > 0) {
                    if (in_array($aid, $used, true)) {
                        return ['ok' => false, 'error' => 'No se puede seleccionar mas de un Anilox en la misma CC'];
                    }
                    $used[] = $aid;
                }
            }

            $stmtUpd = $this->erpPdo->prepare(
                "UPDATE prod_anilox_ot_worker
                 SET paow_anilox = :aid
                 WHERE paow_ot_worker_id = :stk_id AND paow_unidad = :unidad"
            );
            foreach ($aniloxMap as $unidad => $aniloxId) {
                $stmtUpd->execute([
                    ':aid' => (int)$aniloxId,
                    ':stk_id' => $stkId,
                    ':unidad' => (int)$unidad,
                ]);
            }

            $now = time();
            $stmtPao = $this->erpPdo->prepare(
                "UPDATE prod_anilox_ot
                 SET prod_anilox_fecha_actualiza = :udate, prod_anilox_user_actualiza = :uuser
                 WHERE id = :id"
            );
            $stmtPao->execute([':udate' => $now, ':uuser' => $userId, ':id' => $stkId]);

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al guardar Anilox: ' . $e->getMessage()];
        }
    }

    /**
     * Mueve una posición de anilox hacia arriba (direction = 1) o abajo (direction = 2).
     * Replica exactamente editxid.php líneas 160-265.
     */
    public function moveAniloxUnit(int $agId, int $reqId, int $unidad, int $direction): array
    {
        try {
            $stkId = 0;
            if ($reqId > 0) {
                $stmt = $this->erpPdo->prepare(
                    "SELECT id FROM prod_anilox_ot
                     WHERE prod_anilox_agid = :ag_id AND prod_anilox_reqid = :req_id
                     LIMIT 1"
                );
                $stmt->execute([':ag_id' => $agId, ':req_id' => $reqId]);
                $stkId = (int)$stmt->fetchColumn();
            }
            if ($stkId <= 0) {
                $stmt = $this->erpPdo->prepare(
                    "SELECT id FROM prod_anilox_ot
                     WHERE prod_anilox_agid = :ag_id
                     LIMIT 1"
                );
                $stmt->execute([':ag_id' => $agId]);
                $stkId = (int)$stmt->fetchColumn();
            }
            if ($stkId <= 0) {
                return ['ok' => false, 'error' => 'Registro de anilox no encontrado.'];
            }

            $stmtWorkers = $this->erpPdo->prepare(
                "SELECT * FROM prod_anilox_ot_worker
                 WHERE paow_ot_worker_id = :stk_id
                 ORDER BY paow_unidad ASC"
            );
            $stmtWorkers->execute([':stk_id' => $stkId]);
            $rows = $stmtWorkers->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $byUnidad = [];
            foreach ($rows as $r) {
                $byUnidad[(int)$r['paow_unidad']] = $r;
            }

            if (!isset($byUnidad[$unidad])) {
                return ['ok' => false, 'error' => 'Unidad no encontrada.'];
            }

            $curr = $byUnidad[$unidad];

            if ($direction === 1) { // Mover arriba
                if ($unidad <= 1) {
                    return ['ok' => false, 'error' => 'No puede Mover, estas en la primera posición'];
                }
                $targetUnidad = $unidad - 1;
                if (!isset($byUnidad[$targetUnidad])) {
                    return ['ok' => false, 'error' => 'Unidad destino no existe.'];
                }
                $target = $byUnidad[$targetUnidad];

                // Intercambiar color y anilox
                $stmtUpd = $this->erpPdo->prepare(
                    "UPDATE prod_anilox_ot_worker
                     SET paow_color = :color, paow_anilox = :anilox
                     WHERE paow_ot_worker_id = :stk_id AND paow_unidad = :unidad"
                );
                $stmtUpd->execute([
                    ':color' => $target['paow_color'],
                    ':anilox' => (int)$target['paow_anilox'],
                    ':stk_id' => $stkId,
                    ':unidad' => $unidad,
                ]);
                $stmtUpd->execute([
                    ':color' => $curr['paow_color'],
                    ':anilox' => (int)$curr['paow_anilox'],
                    ':stk_id' => $stkId,
                    ':unidad' => $targetUnidad,
                ]);
            } elseif ($direction === 2) { // Mover abajo
                $maximo = count($byUnidad);
                if ($unidad >= $maximo) {
                    return ['ok' => false, 'error' => 'No puede Mover, estas en la última posición'];
                }
                $targetUnidad = $unidad + 1;
                if (!isset($byUnidad[$targetUnidad])) {
                    return ['ok' => false, 'error' => 'Unidad destino no existe.'];
                }
                $target = $byUnidad[$targetUnidad];

                $stmtUpd = $this->erpPdo->prepare(
                    "UPDATE prod_anilox_ot_worker
                     SET paow_color = :color, paow_anilox = :anilox
                     WHERE paow_ot_worker_id = :stk_id AND paow_unidad = :unidad"
                );
                $stmtUpd->execute([
                    ':color' => $target['paow_color'],
                    ':anilox' => (int)$target['paow_anilox'],
                    ':stk_id' => $stkId,
                    ':unidad' => $unidad,
                ]);
                $stmtUpd->execute([
                    ':color' => $curr['paow_color'],
                    ':anilox' => (int)$curr['paow_anilox'],
                    ':stk_id' => $stkId,
                    ':unidad' => $targetUnidad,
                ]);
            }

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al mover unidad de anilox: ' . $e->getMessage()];
        }
    }

    /**
     * Tipos de merma activos en el ERP.
     */
    public function getMermaTypes(): array
    {
        try {
            $stmt = $this->erpPdo->query("SELECT * FROM prod_mermatypes WHERE merma_status > 0 ORDER BY merma_title ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Tipos de reparaciones activos en el ERP.
     */
    public function getRepairTypes(): array
    {
        try {
            $stmt = $this->erpPdo->query("SELECT * FROM prod_repairtypes WHERE repair_status > 0 ORDER BY repair_title ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Obtiene mermas y reparaciones asociadas a un evento de producción (prod_worker_ot_defectunits).
     */
    public function getEventDefectUnits(int $eventId): array
    {
        if ($eventId <= 0) {
            return [];
        }
        try {
            $stmt = $this->erpPdo->prepare(
                "SELECT t1.*, COALESCE(t2.merma_title, t3.repair_title, '') AS causa_title
                 FROM prod_worker_ot_defectunits t1
                 LEFT JOIN prod_mermatypes t2 ON t1.evt_merma_typeid = t2.id
                 LEFT JOIN prod_repairtypes t3 ON t1.evt_repair_typeid = t3.id
                 WHERE t1.evt_refid = :ref_id AND t1.evt_status > 0
                 ORDER BY t1.id ASC"
            );
            $stmt->execute([':ref_id' => $eventId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Guarda mermas y reparaciones en prod_worker_ot_defectunits (editxid.php 850-940).
     */
    public function saveEventDefectUnits(int $eventId, array $mermas, array $repairs): void
    {
        if ($eventId <= 0) {
            return;
        }
        $now = time();

        // Mermas
        foreach ($mermas as $typeId => $mData) {
            $amt = (float)($mData['amount'] ?? 0);
            $kgs = (float)($mData['kgs'] ?? 0);
            $mts = (int)($mData['mts'] ?? 0);
            $comm = trim((string)($mData['comments'] ?? ''));

            $stmtCheck = $this->erpPdo->prepare(
                "SELECT id FROM prod_worker_ot_defectunits
                 WHERE evt_refid = :ref_id AND evt_type = 'merma' AND evt_merma_typeid = :type_id
                 LIMIT 1"
            );
            $stmtCheck->execute([':ref_id' => $eventId, ':type_id' => (int)$typeId]);
            $existingId = (int)$stmtCheck->fetchColumn();

            // Si es merma tipo 9 (Setup / Alistamiento), verificar si ya existe para cualquier evento de esta misma OT
            if ($existingId <= 0 && (int)$typeId === 9) {
                $stmtCheckAlis = $this->erpPdo->prepare(
                    "SELECT d.id FROM prod_worker_ot_defectunits d
                     INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                     WHERE e.evt_prod_worker_otid = (SELECT evt_prod_worker_otid FROM prod_worker_ot_events WHERE id = :ref_id LIMIT 1)
                       AND d.evt_type = 'merma' AND d.evt_merma_typeid = 9 AND d.evt_status > 0
                     ORDER BY d.id DESC LIMIT 1"
                );
                $stmtCheckAlis->execute([':ref_id' => $eventId]);
                $existingAlisId = (int)$stmtCheckAlis->fetchColumn();
                if ($existingAlisId > 0) {
                    $existingId = $existingAlisId;
                    $this->erpPdo->prepare("UPDATE prod_worker_ot_defectunits SET evt_refid = :eid WHERE id = :id")
                        ->execute([':eid' => $eventId, ':id' => $existingId]);
                }
            }

            if ($amt > 0 || $kgs > 0 || $mts > 0 || $comm !== '') {
                if ($existingId > 0) {
                    $stmtUpd = $this->erpPdo->prepare(
                        "UPDATE prod_worker_ot_defectunits
                         SET evt_amount = :amt, evt_kgstounits = :kgs, evt_mtstounits = :mts, evt_comments = :comm
                         WHERE id = :id"
                    );
                    $stmtUpd->execute([':amt' => $amt, ':kgs' => $kgs, ':mts' => $mts, ':comm' => $comm, ':id' => $existingId]);
                } else {
                    $stmtIns = $this->erpPdo->prepare(
                        "INSERT INTO prod_worker_ot_defectunits
                         (evt_amount, evt_crtdat, evt_status, evt_type, evt_comments, evt_merma_typeid, evt_refid, evt_kgstounits, evt_mtstounits)
                         VALUES
                         (:amt, :crtdat, 1, 'merma', :comm, :type_id, :ref_id, :kgs, :mts)"
                    );
                    $stmtIns->execute([
                        ':amt' => $amt,
                        ':crtdat' => $now,
                        ':comm' => $comm,
                        ':type_id' => (int)$typeId,
                        ':ref_id' => $eventId,
                        ':kgs' => $kgs,
                        ':mts' => $mts,
                    ]);
                }
            } else {
                if ($existingId > 0) {
                    $stmtDel = $this->erpPdo->prepare("DELETE FROM prod_worker_ot_defectunits WHERE id = :id");
                    $stmtDel->execute([':id' => $existingId]);
                }
            }
        }

        // Reparaciones
        foreach ($repairs as $typeId => $rData) {
            $amt = (float)($rData['amount'] ?? 0);
            $kgs = (float)($rData['kgs'] ?? 0);
            $comm = trim((string)($rData['comments'] ?? ''));

            $stmtCheck = $this->erpPdo->prepare(
                "SELECT id FROM prod_worker_ot_defectunits
                 WHERE evt_refid = :ref_id AND evt_type = 'repair' AND evt_repair_typeid = :type_id
                 LIMIT 1"
            );
            $stmtCheck->execute([':ref_id' => $eventId, ':type_id' => (int)$typeId]);
            $existingId = (int)$stmtCheck->fetchColumn();

            if ($amt > 0 || $kgs > 0 || $comm !== '') {
                if ($existingId > 0) {
                    $stmtUpd = $this->erpPdo->prepare(
                        "UPDATE prod_worker_ot_defectunits
                         SET evt_amount = :amt, evt_kgstounits = :kgs, evt_comments = :comm
                         WHERE id = :id"
                    );
                    $stmtUpd->execute([':amt' => $amt, ':kgs' => $kgs, ':comm' => $comm, ':id' => $existingId]);
                } else {
                    $stmtIns = $this->erpPdo->prepare(
                        "INSERT INTO prod_worker_ot_defectunits
                         (evt_amount, evt_crtdat, evt_status, evt_type, evt_comments, evt_repair_typeid, evt_refid, evt_kgstounits, evt_mtstounits)
                         VALUES
                         (:amt, :crtdat, 1, 'repair', :comm, :type_id, :ref_id, :kgs, 0)"
                    );
                    $stmtIns->execute([
                        ':amt' => $amt,
                        ':crtdat' => $now,
                        ':comm' => $comm,
                        ':type_id' => (int)$typeId,
                        ':ref_id' => $eventId,
                        ':kgs' => $kgs,
                    ]);
                }
            } else {
                if ($existingId > 0) {
                    $stmtDel = $this->erpPdo->prepare("DELETE FROM prod_worker_ot_defectunits WHERE id = :id");
                    $stmtDel->execute([':id' => $existingId]);
                }
            }
        }
    }

    // =========================================================================
    // SECCIÓN: PULPO SERIGRÁFICO V2 (Información de Fabricación)
    // =========================================================================

    public function getPulpoV2Data(int $agId, string $colorMode = 'Frente'): array
    {
        $colorMode = ($colorMode === 'Dorso') ? 'Dorso' : 'Frente';
        $agendaData = [];
        try {
            $stmt = $this->erpPdo->prepare("
                SELECT pulpo_temp_horno_frente, pulpo_temp_horno_dorso,
                       pulpo_maq_speed_frente, pulpo_maq_speed_dorso,
                       pulpo_horno_speed_frente, pulpo_horno_speed_dorso,
                       pulpo_medidas_camillas_frente, pulpo_medidas_camillas_dorso
                FROM prod_agenda WHERE id = :id LIMIT 1
            ");
            $stmt->execute([':id' => $agId]);
            $agendaData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {}

        $units = [];
        try {
            $stmt = $this->erpPdo->prepare("
                SELECT * FROM prod_agenda_v2_pulpo
                WHERE ag_id = :id AND val_colormode = :mode
                ORDER BY val_pos ASC
            ");
            $stmt->execute([':id' => $agId, ':mode' => $colorMode]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $units[(int)$r['val_pos']] = $r;
            }
        } catch (Throwable) {}

        return [
            'agenda' => $agendaData,
            'units' => $units,
            'colormode' => $colorMode,
        ];
    }

    public function savePulpoV2Data(int $agId, string $colorMode, array $header, array $units): bool
    {
        $colorMode = ($colorMode === 'Dorso') ? 'Dorso' : 'Frente';
        $pidx = strtolower($colorMode);

        try {
            $sql = "UPDATE prod_agenda
                    SET pulpo_temp_horno_{$pidx} = :temp,
                        pulpo_maq_speed_{$pidx} = :maq_speed,
                        pulpo_horno_speed_{$pidx} = :horno_speed,
                        pulpo_medidas_camillas_{$pidx} = :camillas
                    WHERE id = :id";
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute([
                ':temp' => (int)($header['temp_horno'] ?? 0),
                ':maq_speed' => (float)($header['maq_speed'] ?? 0),
                ':horno_speed' => (float)($header['horno_speed'] ?? 0),
                ':camillas' => trim((string)($header['medidas_camillas'] ?? '')),
                ':id' => $agId,
            ]);

            $del = $this->erpPdo->prepare("DELETE FROM prod_agenda_v2_pulpo WHERE ag_id = :id AND val_colormode = :mode");
            $del->execute([':id' => $agId, ':mode' => $colorMode]);

            $ins = $this->erpPdo->prepare("
                INSERT INTO prod_agenda_v2_pulpo
                    (ag_id, color_id, val_temp_secador, val_num_bastidor, val_mesh, val_total_tinta, val_pos, val_colormode)
                VALUES
                    (:ag_id, :color_id, :temp_sec, :bastidor, :mesh, :total_tinta, :pos, :mode)
            ");

            for ($px = 0; $px < 10; $px++) {
                $u = $units[$px] ?? null;
                if ($u === null) continue;
                $colorId = trim((string)($u['color_id'] ?? ''));
                if ($colorId === '') continue;

                $tempSec = (float)($u['val_temp_secador'] ?? 0);
                $bastidor = trim((string)($u['val_num_bastidor'] ?? ''));
                $mesh = (float)($u['val_mesh'] ?? 0);
                $totalTinta = (float)($u['val_total_tinta'] ?? 0);
                $cargaTinta = (float)($u['val_carga_tinta'] ?? 0);
                $sobranteTinta = (float)($u['val_sobrante_tinta'] ?? 0);
                $totalTinta = $totalTinta + $cargaTinta - $sobranteTinta;

                if ($colorId === '-1') {
                    $bastidor = '';
                    $mesh = 0;
                    $totalTinta = 0;
                } else {
                    $tempSec = 0;
                }

                $ins->execute([
                    ':ag_id' => $agId,
                    ':color_id' => $colorId,
                    ':temp_sec' => $tempSec,
                    ':bastidor' => $bastidor,
                    ':mesh' => $mesh,
                    ':total_tinta' => $totalTinta,
                    ':pos' => $px,
                    ':mode' => $colorMode,
                ]);
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    // =========================================================================
    // SECCIÓN: EVENTOS DE SIN PRODUCCIÓN / STANDBY DE MÁQUINA
    // =========================================================================

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

    /**
     * Guarda el registro de pesaje de bobinas entrantes y subproductos salientes para la Rebobinadora.
     * Replica con precisión la lógica legacy de editxid.php líneas 347-380 (reboexec = 'save').
     */
    public function saveReboInputWeights(int $reqId, int $workerOtId, array $postData): bool
    {
        if ($reqId <= 0 || $workerOtId <= 0) {
            return false;
        }
        try {
            $delStmt = $this->erpPdo->prepare(
                "DELETE FROM orders_classify_subproducts_inputweights
                 WHERE req_id = :req_id AND prod_worker_ot_id = :ot_id"
            );
            $delStmt->execute([':req_id' => $reqId, ':ot_id' => $workerOtId]);

            $insStmt = $this->erpPdo->prepare(
                "INSERT INTO orders_classify_subproducts_inputweights
                 (req_id, prod_worker_ot_id, subprod_rollo_idx, subprod_weight_enter,
                  subprod_salida_weight_0, subprod_salida_weight_1, subprod_salida_weight_2, subprod_salida_weight_3,
                  subprod_salida_weight_4, subprod_salida_weight_5, subprod_salida_weight_6, subprod_salida_weight_7,
                  subprod_salida_weight_8, subprod_salida_weight_9, subprod_salida_weight_10, subprod_salida_weight_11,
                  subprod_salida_weight_12, subprod_salida_weight_13, subprod_salida_weight_14, subprod_salida_weight_15,
                  subprod_salida_weight_16, subprod_salida_weight_17, subprod_salida_weight_18, subprod_salida_weight_19)
                 VALUES
                 (:req_id, :ot_id, :idx, :weight_enter,
                  :w0, :w1, :w2, :w3, :w4, :w5, :w6, :w7, :w8, :w9, :w10, :w11, :w12, :w13, :w14, :w15, :w16, :w17, :w18, :w19)"
            );

            foreach ($postData as $k => $val) {
                if (str_starts_with((string)$k, 'subprod_weight_enter_')) {
                    $idxStr = substr((string)$k, strlen('subprod_weight_enter_'));
                    $idx = (int)$idxStr;
                    $cleanedEnter = str_replace(',', '.', trim((string)$val));
                    $weightEnter = (float)$cleanedEnter;
                    if ($weightEnter > 0) {
                        $inps = [];
                        for ($z = 0; $z < 20; $z++) {
                            $rawZ = $postData["subprod_salida_weight_{$idx}_{$z}"] ?? 0;
                            $cleanedZ = str_replace(',', '.', trim((string)$rawZ));
                            $inps[$z] = (float)$cleanedZ;
                        }
                        $insStmt->execute([
                            ':req_id' => $reqId,
                            ':ot_id' => $workerOtId,
                            ':idx' => $idx,
                            ':weight_enter' => $weightEnter,
                            ':w0' => $inps[0], ':w1' => $inps[1], ':w2' => $inps[2], ':w3' => $inps[3],
                            ':w4' => $inps[4], ':w5' => $inps[5], ':w6' => $inps[6], ':w7' => $inps[7],
                            ':w8' => $inps[8], ':w9' => $inps[9], ':w10' => $inps[10], ':w11' => $inps[11],
                            ':w12' => $inps[12], ':w13' => $inps[13], ':w14' => $inps[14], ':w15' => $inps[15],
                            ':w16' => $inps[16], ':w17' => $inps[17], ':w18' => $inps[18], ':w19' => $inps[19],
                        ]);
                    }
                }
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Obtiene los materiales disponibles en bodega 3000 para consumir en la Rebobinadora.
     * Replica exactamente la consulta legacy de form.reboprod.php líneas 25-55.
     */
    public function getReboAvailableMaterials(array $agenda): array
    {
        $shopId = (int)($agenda['req_shop_id'] ?? 30010);
        $colorVals = array_filter([
            (int)($agenda['fab_mat_fabric_color'] ?? 0),
            (int)($agenda['fab_mat_manilla_color'] ?? 0),
        ]);
        $colorIn = !empty($colorVals) ? implode(',', $colorVals) : '';
        $fabType = trim((string)($agenda['fab_type'] ?? ''));

        $sql = "SELECT DISTINCT t1.*, t2.cat_id, t3.cat_title,
                       t4b.add_name AS colordesc,
                       t4.val_id AS colorid,
                       t6.add_name AS material,
                       t7.st_id,
                       SUM(t7.iss_inventory) AS iss_inventory
                FROM item t1
                INNER JOIN item_productcats t2            ON t1.id = t2.item_id
                INNER JOIN productcats t3                 ON t2.cat_id = t3.id
                INNER JOIN tran_comments_item_vals t4     ON t4.item_id = t1.id
                INNER JOIN tran_comments_vals t4b         ON t4.val_id = t4b.id
                INNER JOIN tran_comments_item_vals t5     ON t5.item_id = t1.id
                INNER JOIN tran_comments_vals t6          ON t5.val_id = t6.id
                INNER JOIN item_shops_storehouses t7      ON t1.id = t7.item_id
                INNER JOIN company_shops_storehouses t8   ON t7.st_id = t8.id
                WHERE
                    t1.item_status   > 0 AND
                    t1.item_released > 0 AND
                    t1.item_reg_kg   > 0 AND
                    t7.iss_inventory > 0 AND
                    t8.st_status     = 1 AND
                    t8.st_name LIKE '3000%' AND
                    t3.id IN (6, 27) AND
                    t4.com_id = 17 AND
                    t5.com_id = 18";

        if ($colorIn !== '') {
            $sql .= " AND t4.val_id IN ({$colorIn})";
        }
        if ($fabType !== '') {
            $sql .= " AND t6.add_name = :fab_type";
        }

        $sql .= " GROUP BY t1.id ORDER BY t3.cat_title, t1.item_title, t1.item_number_prod";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            if ($fabType !== '') {
                $stmt->execute([':fab_type' => $fabType]);
            } else {
                $stmt->execute();
            }
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (!empty($items)) {
                return $items;
            }

            // Fallback: Si no hay coincidencias estrictas, traer cualquier bobina de tela con stock positivo en Bodega 3000
            $sqlFb = "SELECT DISTINCT t1.*, t2.cat_id, t3.cat_title,
                             COALESCE((SELECT t4b.add_name FROM tran_comments_item_vals t4 INNER JOIN tran_comments_vals t4b ON t4.val_id = t4b.id WHERE t4.item_id = t1.id AND t4.com_id = 17 LIMIT 1), t1.item_title) AS colordesc,
                             COALESCE((SELECT t6.add_name FROM tran_comments_item_vals t5 INNER JOIN tran_comments_vals t6 ON t5.val_id = t6.id WHERE t5.item_id = t1.id AND t5.com_id = 18 LIMIT 1), 'TNT') AS material,
                             t7.st_id,
                             SUM(t7.iss_inventory) AS iss_inventory
                      FROM item t1
                      INNER JOIN item_productcats t2          ON t1.id = t2.item_id
                      INNER JOIN productcats t3               ON t2.cat_id = t3.id
                      INNER JOIN item_shops_storehouses t7    ON t1.id = t7.item_id
                      INNER JOIN company_shops_storehouses t8 ON t7.st_id = t8.id
                      WHERE
                          t1.item_status   > 0 AND
                          t1.item_released > 0 AND
                          t1.item_reg_kg   > 0 AND
                          t7.iss_inventory > 0 AND
                          t8.st_status     = 1 AND
                          t8.st_name LIKE '3000%' AND
                          t3.id IN (6, 27)
                      GROUP BY t1.id ORDER BY t3.cat_title, t1.item_title, t1.item_number_prod";
            $stmtFb = $this->erpPdo->query($sqlFb);
            return $stmtFb->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Determina si la orden de Rebobinado corresponde al Caso 2 (Solo Manillas) o al Caso 1 (Bolsas por cortar).
     */
    public function isReboSoloManillas(array $agenda, array $reboOrder = [], array $subproducts = []): bool
    {
        $opt = trim((string)($reboOrder['req_rebo_rolloscc_opt'] ?? ($agenda['req_rebo_rolloscc_opt'] ?? '')));
        if ($opt === '100cm_manillas') {
            return true;
        }

        if (!empty($subproducts)) {
            $hasNonManilla = false;
            $allEmpty = true;
            foreach ($subproducts as $sp) {
                $dest = strtolower(trim((string)($sp['subprod_destino'] ?? '')));
                $w = (float)($sp['subprod_ancho_cm'] ?? 0);
                if ($dest !== '') $allEmpty = false;
                if ($w > 15 || (!str_contains($dest, 'manilla') && $dest !== '')) {
                    $hasNonManilla = true;
                    break;
                }
            }
            if (!$hasNonManilla && !$allEmpty) {
                return true;
            }
        }

        $anchoManillas = (float)($agenda['req_infoaddprd_ancho_manillas'] ?? 0);
        if ($anchoManillas > 0 && empty($subproducts)) {
            return true;
        }

        return false;
    }

    /**
     * Escanea e ingresa una bobina para Rebobinado / Corte (Caso 1 o Caso 2).
     * Busca por código de producto (item_number_prod), código interno (item_number), ID en ERP o roll_code en TRZ.
     * Si la bobina no tiene stock en Bodega 3000, la transfiere/ingresa automáticamente desde Bodega 100/200.
     */
    public function scanAndPrepareReboRoll(string $code, array $agenda, int $userId): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['ok' => false, 'error' => 'Código de bobina vacío.'];
        }

        $shopId = (int)($agenda['req_shop_id'] ?? 20010);
        $stId3000 = 16; // 3000 INSUMOS EN PRODUCCION

        // 1. Buscar directamente en ERP item
        $stmt = $this->erpPdo->prepare("
            SELECT i.*,
                   COALESCE((SELECT SUM(iss_inventory) FROM item_shops_storehouses WHERE item_id = i.id AND st_id IN (16, 27)), 0) as stock_3000,
                   COALESCE((SELECT SUM(iss_inventory) FROM item_shops_storehouses WHERE item_id = i.id AND st_id IN (1, 10, 11)), 0) as stock_mp
            FROM item i
            WHERE i.item_number_prod = :c OR i.item_number = :c OR i.id = :c2
            LIMIT 1
        ");
        $stmt->execute([':c' => $code, ':c2' => is_numeric($code) ? (int)$code : 0]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Si no se encuentra, buscar en TRZ rolls por roll_code
        if (!$item) {
            try {
                $stmtTrz = $this->trzPdo->prepare("
                    SELECT * FROM rolls WHERE roll_code = :c OR id = :c2 LIMIT 1
                ");
                $stmtTrz->execute([':c' => $code, ':c2' => is_numeric($code) ? (int)$code : 0]);
                $trzRoll = $stmtTrz->fetch(PDO::FETCH_ASSOC);
                if ($trzRoll) {
                    $wCm = round(((float)($trzRoll['width_mm'] ?? 0)) / 10, 1);
                    $stmtF = $this->erpPdo->prepare("
                        SELECT i.*,
                               COALESCE((SELECT SUM(iss_inventory) FROM item_shops_storehouses WHERE item_id = i.id AND st_id IN (16, 27)), 0) as stock_3000,
                               COALESCE((SELECT SUM(iss_inventory) FROM item_shops_storehouses WHERE item_id = i.id AND st_id IN (1, 10, 11)), 0) as stock_mp
                        FROM item i
                        WHERE (i.item_reg_width = :w OR i.item_title LIKE :c) AND i.item_status > 0
                        LIMIT 1
                    ");
                    $stmtF->execute([':w' => $wCm, ':c' => '%' . ($trzRoll['color'] ?? '') . '%']);
                    $item = $stmtF->fetch(PDO::FETCH_ASSOC);
                    if ($item && !empty($trzRoll['weight_kg']) && (float)$trzRoll['weight_kg'] > 0) {
                        $item['item_reg_kg'] = (float)$trzRoll['weight_kg'];
                    }
                }
            } catch (Throwable) {}
        }

        // 3. Si aún no se encuentra, probar búsqueda LIKE
        if (!$item) {
            $stmtLike = $this->erpPdo->prepare("
                SELECT i.*,
                       COALESCE((SELECT SUM(iss_inventory) FROM item_shops_storehouses WHERE item_id = i.id AND st_id IN (16, 27)), 0) as stock_3000,
                       COALESCE((SELECT SUM(iss_inventory) FROM item_shops_storehouses WHERE item_id = i.id AND st_id IN (1, 10, 11)), 0) as stock_mp
                FROM item i
                WHERE (i.item_number_prod LIKE :c OR i.item_title LIKE :c) AND i.item_status > 0
                LIMIT 1
            ");
            $stmtLike->execute([':c' => "%{$code}%"]);
            $item = $stmtLike->fetch(PDO::FETCH_ASSOC);
        }

        if (!$item) {
            return ['ok' => false, 'error' => "No se encontró ninguna bobina con el código '{$code}'."];
        }

        $itemId = (int)$item['id'];
        $stock3000 = (float)($item['stock_3000'] ?? 0);

        // Si no tiene stock en Bodega 3000, ingresarla / transferirla a Bodega 3000 (1 bobina)
        if ($stock3000 <= 0.0001) {
            // Descontar de MP si hay stock
            $stockMp = (float)($item['stock_mp'] ?? 0);
            if ($stockMp > 0) {
                try {
                    $this->erpPdo->prepare("
                        UPDATE item_shops_storehouses
                        SET iss_inventory = GREATEST(0, iss_inventory - 1)
                        WHERE item_id = :itid AND st_id IN (10, 11, 1) AND iss_inventory > 0
                        LIMIT 1
                    ")->execute([':itid' => $itemId]);
                } catch (Throwable) {}
            }

            // Ingresar a Bodega 3000
            try {
                $upd = $this->erpPdo->prepare("
                    UPDATE item_shops_storehouses
                    SET iss_inventory = iss_inventory + 1
                    WHERE item_id = :itid AND st_id = :stid
                ");
                $upd->execute([':itid' => $itemId, ':stid' => $stId3000]);
                if ($upd->rowCount() === 0) {
                    $this->erpPdo->prepare("
                        INSERT INTO item_shops_storehouses (item_id, shop_id, st_id, iss_inventory)
                        VALUES (:itid, :shopid, :stid, 1)
                    ")->execute([':itid' => $itemId, ':shopid' => $shopId, ':stid' => $stId3000]);
                }
            } catch (Throwable) {}
        }

        return [
            'ok' => true,
            'item' => $item,
            'item_id' => $itemId,
            'code' => $code,
            'item_number_prod' => $item['item_number_prod'] ?? '',
            'title' => $item['item_title'] ?? '',
            'weight_kg' => (float)($item['item_reg_kg'] ?? 0),
            'roll_id' => (int)($trzRoll['id'] ?? 0),
            'roll_code' => (string)($trzRoll['roll_code'] ?? $item['item_number_prod'] ?? $code),
        ];
    }

    /**
     * Obtiene las bobinas específicamente solicitadas para esta orden de trabajo (Trazabilidad).
     * Solo retorna las bobinas que ingresaron por solicitud a esta OT o están asignadas a la misma.
     */
    public function getRequestedRollsForRebo(int $workerOtId, int $agId): array
    {
        $requested = [];

        // 1. Buscar en TRZ rolls asignadas a la orden de trabajo (current_work_order_id = $workerOtId)
        if ($this->trzPdo !== null && $workerOtId > 0) {
            try {
                $stmt = $this->trzPdo->prepare("
                    SELECT r.*, s.code AS sku_code, s.description AS sku_desc, s.name AS sku_name, w.name AS warehouse_name
                    FROM rolls r
                    LEFT JOIN skus s ON r.sku_id = s.id
                    LEFT JOIN warehouses w ON r.warehouse_id = w.id
                    WHERE r.current_work_order_id = :ot_id AND r.status != 'CONSUMED'
                    ORDER BY r.id DESC
                ");
                $stmt->execute([':ot_id' => $workerOtId]);
                $trzRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($trzRows as $r) {
                    $rId = (int)$r['id'];

                    // Buscar el item correspondiente en ERP
                    $erpItem = null;
                    if (!empty($r['roll_code'])) {
                        $stItem = $this->erpPdo->prepare("
                            SELECT * FROM item WHERE item_number_prod = :c OR item_number = :c LIMIT 1
                        ");
                        $stItem->execute([':c' => $r['roll_code']]);
                        $erpItem = $stItem->fetch(PDO::FETCH_ASSOC);
                    }
                    if (!$erpItem && !empty($r['sku_code'])) {
                        $stItem = $this->erpPdo->prepare("
                            SELECT * FROM item WHERE item_number_prod = :c OR item_number = :c LIMIT 1
                        ");
                        $stItem->execute([':c' => $r['sku_code']]);
                        $erpItem = $stItem->fetch(PDO::FETCH_ASSOC);
                    }

                    $requested[] = [
                        'source' => 'trz',
                        'roll_id' => $rId,
                        'roll_code' => $r['roll_code'],
                        'sku_code' => $r['sku_code'] ?? '',
                        'material' => $r['sku_name'] ?? ($erpItem['item_title'] ?? 'TNT'),
                        'colordesc' => $r['color'] ?? ($erpItem['colordesc'] ?? ''),
                        'width_cm' => round(((float)($r['width_mm'] ?? 0)) / 10, 1),
                        'weight_kg' => (float)($r['weight_kg'] ?? 0),
                        'meters' => (float)($r['meters'] ?? 0),
                        'erp_item_id' => (int)($erpItem['id'] ?? 0),
                        'status' => $r['status'],
                    ];
                }
            } catch (Throwable) {}
        }

        // 2. Buscar eventos de solicitud en ERP prod_worker_ot_events si no hubiese en TRZ
        if ($workerOtId > 0) {
            try {
                $stmtEvt = $this->erpPdo->prepare("
                    SELECT * FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :ot_id
                      AND evt_status = 1
                      AND evt_type = 'materiales'
                      AND evt_comments LIKE '%Solicitud Bobina%'
                    ORDER BY id DESC
                ");
                $stmtEvt->execute([':ot_id' => $workerOtId]);
                $evtRows = $stmtEvt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($evtRows as $ev) {
                    if (preg_match('/Solicitud Bobina\s+([A-Za-z0-9\-_]+)(?:\s+\[([A-Za-z0-9\-_]+)\])?/i', (string)$ev['evt_comments'], $mCode)) {
                        $rollCode = trim($mCode[1]);
                        $skuCode = trim($mCode[2] ?? '');

                        $already = false;
                        foreach ($requested as $rq) {
                            if ($rq['roll_code'] === $rollCode) { $already = true; break; }
                        }
                        if ($already) continue;

                        $stItem = $this->erpPdo->prepare("
                            SELECT * FROM item WHERE item_number_prod = :c OR item_number = :c LIMIT 1
                        ");
                        $stItem->execute([':c' => $skuCode !== '' ? $skuCode : $rollCode]);
                        $erpItem = $stItem->fetch(PDO::FETCH_ASSOC);

                        $requested[] = [
                            'source' => 'erp_event',
                            'roll_id' => 0,
                            'roll_code' => $rollCode,
                            'sku_code' => $skuCode,
                            'material' => $erpItem['item_title'] ?? 'TNT',
                            'colordesc' => $erpItem['item_desc'] ?? '',
                            'width_cm' => (float)($erpItem['item_reg_width'] ?? 0),
                            'weight_kg' => (float)$ev['evt_amount'],
                            'meters' => 0,
                            'erp_item_id' => (int)($erpItem['id'] ?? 0),
                            'status' => 'SOLICITADA',
                        ];
                    }
                }
            } catch (Throwable) {}
        }

        return $requested;
    }

    /**
     * Obtiene el listado de movimientos de producción finalizados en Rebobinado (form.reboprod.php líneas 700-880).
     */
    public function getReboFinishedMoves(int $agId): array
    {
        if ($agId <= 0) {
            return [];
        }
        try {
            $sql = "SELECT t1.*, t3.item_title, t3.item_number_prod, t2.item_amount,
                           t3.item_reg_kg, t2.item_reboprod_amt, t2.item_reboprod_unitkg
                    FROM stockchanges t1
                    INNER JOIN stockchanges_items t2 ON t1.id = t2.stk_id
                    INNER JOIN item t3 ON t2.item_id = t3.id
                    WHERE
                        t1.sth_fromrebo_agid = :agid AND
                        t1.stk_status        = 2 AND
                        t1.stk_negative      = 1 AND
                        t1.sth_fromrebo_agid_grphash != ''
                    ORDER BY t1.id ASC";
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute([':agid' => $agId]);
            $moveouts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($moveouts as &$mo) {
                $sqlIn = "SELECT t1.*, t3.item_title, t3.item_number_prod, t2.item_amount,
                                 t3.item_reg_kg, t2.item_reboprod_amt, t2.item_reboprod_unitkg
                          FROM stockchanges t1
                          INNER JOIN stockchanges_items t2 ON t1.id = t2.stk_id
                          INNER JOIN item t3 ON t2.item_id = t3.id
                          WHERE
                              t1.sth_fromrebo_agid = :agid AND
                              t1.stk_status        = 2 AND
                              t1.stk_negative      = 0 AND
                              t1.sth_fromrebo_agid_grphash = :grphash
                          ORDER BY t1.id DESC";
                $stmtIn = $this->erpPdo->prepare($sqlIn);
                $stmtIn->execute([
                    ':agid' => $agId,
                    ':grphash' => $mo['sth_fromrebo_agid_grphash'],
                ]);
                $mo['moveins'] = $stmtIn->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
            unset($mo);
            return $moveouts;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Anula movimientos de rebobinado previamente registrados (form.reboprod.php líneas 4-22).
     */
    public function deleteReboStockChanges(array $delsthids, int $userId): bool
    {
        if (empty($delsthids)) {
            return false;
        }
        $now = time();
        foreach ($delsthids as $delsthid) {
            $id = (int)$delsthid;
            if ($id <= 0) continue;
            try {
                // Obtener datos del movimiento
                $stmt = $this->erpPdo->prepare("SELECT * FROM stockchanges WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $id]);
                $head = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$head || (int)($head['stk_status'] ?? 0) !== 2) {
                    continue;
                }
                $isNegative = (int)($head['stk_negative'] ?? 0) === 1;

                // Obtener ítems y revertir stock
                $stmtItems = $this->erpPdo->prepare("SELECT * FROM stockchanges_items WHERE stk_id = :stk_id");
                $stmtItems->execute([':stk_id' => $id]);
                $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($items as $it) {
                    $itemId = (int)($it['item_id'] ?? 0);
                    $stId = (int)($it['item_st_id'] ?? 0);
                    $amt = (float)($it['item_amount'] ?? 0);
                    if ($itemId > 0 && $stId > 0 && $amt > 0) {
                        if ($isNegative) {
                            // Era salida (consumo) -> revertir sumando stock
                            $this->erpPdo->prepare("
                                UPDATE item_shops_storehouses
                                SET iss_inventory = iss_inventory + :amt
                                WHERE item_id = :itid AND st_id = :stid
                            ")->execute([':amt' => $amt, ':itid' => $itemId, ':stid' => $stId]);
                        } else {
                            // Era entrada (producido) -> revertir restando stock
                            $this->erpPdo->prepare("
                                UPDATE item_shops_storehouses
                                SET iss_inventory = GREATEST(0, iss_inventory - :amt)
                                WHERE item_id = :itid AND st_id = :stid
                            ")->execute([':amt' => $amt, ':itid' => $itemId, ':stid' => $stId]);
                        }
                    }
                }

                // Marcar como anulado
                $this->erpPdo->prepare("
                    UPDATE stockchanges
                    SET stk_status = 0, stk_upddat = :now, stk_updusr = :uid
                    WHERE id = :id
                ")->execute([':now' => $now, ':uid' => $userId, ':id' => $id]);

            } catch (Throwable) {}
        }
        return true;
    }

    /**
     * Ejecuta la finalización de Rebobinado / Corte (form.reboprod.php líneas 65-430 y 900-1000).
     *
     * REGLA DE NEGOCIO SOLICITADA POR EL USUARIO:
     * - Si es Corte de bobina (para hacer manillas o bolsas): SÍ CONSUME la bobina madre (salida stk_negative=1)
     *   y genera el alta de los nuevos subproductos cortados (entrada stk_negative=0).
     * - Si es Solo Rebobinado: NO CONSUME la bobina madre (la bobina mantiene su stock intacto).
     */
    public function executeReboFinalize(int $agId, array $postData, int $userId, int $companyId, int $workerOtId = 0, string $operatorName = ''): array
    {
        $selItemId = (int)($postData['selitemid1'] ?? 0);
        if ($selItemId <= 0 || $agId <= 0) {
            return ['ok' => false, 'error' => 'No se seleccionó una bobina válida.'];
        }

        // Obtener datos de la OT y de la bobina de origen
        $stmtAg = $this->erpPdo->prepare("
            SELECT pa.*, o.req_rebo_type, o.req_rebo_rolloscc, o.req_rebo_cortescc, o.req_shop_id,
                   o.req_planta_id, o.fab_type, o.req_number, o.id as order_id,
                   o.fab_mat_fabric_color, o.fab_mat_manilla_color
            FROM prod_agenda pa
            LEFT JOIN orders o ON pa.ag_reqid = o.id
            WHERE pa.id = :id LIMIT 1
        ");
        $stmtAg->execute([':id' => $agId]);
        $agenda = $stmtAg->fetch(PDO::FETCH_ASSOC) ?: [];
        $reqShopId = (int)($agenda['req_shop_id'] ?? 30010);
        $reboType = trim((string)($postData['req_rebo_type'] ?? ($agenda['req_rebo_type'] ?? 'Corte')));
        $orderId = (int)($agenda['order_id'] ?? $agenda['ag_reqid'] ?? 0);
        $orderNum = (string)($agenda['req_number'] ?? '');
        $plantaId = (int)($agenda['req_planta_id'] ?? 1);
        $equipoId = (int)($agenda['ag_equipoid'] ?? 0);

        // Obtener datos del ítem de origen
        $stmtItem = $this->erpPdo->prepare("
            SELECT i.*, iss.st_id, iss.iss_inventory
            FROM item i
            INNER JOIN item_shops_storehouses iss ON i.id = iss.item_id
            INNER JOIN company_shops_storehouses css ON iss.st_id = css.id
            WHERE i.id = :id AND css.st_name LIKE '3000%' AND iss.iss_inventory > 0
            LIMIT 1
        ");
        $stmtItem->execute([':id' => $selItemId]);
        $itemSel = $stmtItem->fetch(PDO::FETCH_ASSOC);
        if (!$itemSel) {
            // Intentar sin filtro de bodega si ya fue parcialmente consumida
            $stmtItem2 = $this->erpPdo->prepare("
                SELECT i.*, iss.st_id, iss.iss_inventory
                FROM item i
                LEFT JOIN item_shops_storehouses iss ON i.id = iss.item_id
                WHERE i.id = :id LIMIT 1
            ");
            $stmtItem2->execute([':id' => $selItemId]);
            $itemSel = $stmtItem2->fetch(PDO::FETCH_ASSOC);
        }

        if (!$itemSel) {
            return ['ok' => false, 'error' => 'No se encontró la bobina en inventario.'];
        }

        $now = time();
        $useKgAmt = (float)str_replace(',', '.', (string)($postData['useamt_1'] ?? $postData['useamt'] ?? 0));
        if ($useKgAmt <= 0) {
            $ingresoKg = (float)str_replace(',', '.', (string)($postData['ingresokgamt_1'] ?? $itemSel['item_reg_kg']));
            $sobranteKg = (float)str_replace(',', '.', (string)($postData['sobranteamt_1'] ?? 0));
            $useKgAmt = max(0.0, $ingresoKg - $sobranteKg);
        }

        $baseKg = (float)($itemSel['item_reg_kg'] ?? 1);
        if ($baseKg <= 0) $baseKg = 1.0;
        $origenUnits = round($useKgAmt / $baseKg, 10);
        $stkFixedSthId = (int)($itemSel['st_id'] ?? 16);
        $grpHash = md5((string)microtime(true));

        // Asegurar la existencia de prod_worker_ot para vincular el informe oficial de consumo
        if ($workerOtId <= 0) {
            $stmtOt = $this->erpPdo->prepare("
                SELECT id FROM prod_worker_ot WHERE wok_ag_id = :agid ORDER BY id DESC LIMIT 1
            ");
            $stmtOt->execute([':agid' => $agId]);
            $workerOtId = (int)($stmtOt->fetchColumn() ?: 0);
            if ($workerOtId <= 0) {
                try {
                    $this->erpPdo->prepare("
                        INSERT INTO prod_worker_ot (wok_ag_id, wok_init_id, wok_crtdat, wok_status, wok_idusuario)
                        VALUES (:agid, 0, :now, 1, :uid)
                    ")->execute([':agid' => $agId, ':now' => $now, ':uid' => $userId]);
                    $workerOtId = (int)$this->erpPdo->lastInsertId();
                } catch (Throwable) {}
            }
        }

        // -------------------------------------------------------------
        // CASO 1: REBOBINADO SIMPLE -> NO CONSUME LA BOBINA
        // -------------------------------------------------------------
        if ($reboType === 'Rebobinado') {
            // Registrar únicamente el evento de trabajo de rebobinado en la OT sin descontar la bobina madre
            $mermaKg = (float)str_replace(',', '.', (string)($postData['itemmermakg'] ?? 0));
            if ($workerOtId > 0) {
                try {
                    $this->erpPdo->prepare("
                        INSERT INTO prod_worker_ot_events
                        (evt_prod_worker_otid, evt_type, evt_amount, prod_bobina_kg, evt_amount2, evt_crtdat, evt_enddat, evt_status, evt_comments)
                        VALUES
                        (:ot_id, 'prod', 1, :kg, :waste, :now, :now, 1, :comments)
                    ")->execute([
                        ':ot_id' => $workerOtId,
                        ':kg' => $useKgAmt,
                        ':waste' => $mermaKg,
                        ':now' => $now,
                        ':comments' => "Rebobinado finalizado - Rollo {$itemSel['item_number_prod']} ({$useKgAmt} Kg, Merma: {$mermaKg} Kg) [Sin consumo de stock]",
                    ]);
                } catch (Throwable) {}
            }
            return ['ok' => true, 'rebo_type' => 'Rebobinado', 'consumed' => false];
        }

        // -------------------------------------------------------------
        // CASO 2: CORTE DE BOBINA (MANILLAS / BOLSAS) -> CONSUME LA BOBINA
        // -------------------------------------------------------------
        if ($origenUnits > 0) {
            // 1. Crear número de transacción para la salida
            $stkNumOut = $this->generateNextStockChangeNumber($companyId);
            $annotText = number_format($useKgAmt, 2, ',', '.') . ' Kilogramos';

            // Insertar cabecera de salida (stk_negative = 1, issue 19 = Uso material)
            // Vinculando sth_fromprodotid para el informe oficial de consumo (libs/modules/stats/prod/consumo.php)
            $stmtSC = $this->erpPdo->prepare("
                INSERT INTO stockchanges
                (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate, stk_negative,
                 stk_status, stk_crtdat, stk_crtusr, stk_fixedsthid, sth_fromrebo_agid, sth_fromrebo_agid_grphash,
                 sth_fromprodotid, sth_order_id, sth_order_num, sth_planta_id, sth_assign_wrkid, sth_assign_equipoid)
                VALUES
                (:num, :annot, 19, :compid, :shopid, :bdate, 1, 2, :crtdat, :crtusr, :sthid, :agid, :grphash,
                 :ot_id, :order_id, :order_num, :planta_id, :worker_id, :equipo_id)
            ");
            $stmtSC->execute([
                ':num' => $stkNumOut,
                ':annot' => $annotText,
                ':compid' => $companyId > 0 ? $companyId : 20010,
                ':shopid' => $reqShopId,
                ':bdate' => $now,
                ':crtdat' => $now,
                ':crtusr' => $userId,
                ':sthid' => $stkFixedSthId,
                ':agid' => $agId,
                ':grphash' => $grpHash,
                ':ot_id' => $workerOtId,
                ':order_id' => $orderId,
                ':order_num' => $orderNum,
                ':planta_id' => $plantaId,
                ':worker_id' => $userId,
                ':equipo_id' => $equipoId,
            ]);
            $stkIdOut = (int)$this->erpPdo->lastInsertId();

            // Insertar ítem de salida
            $this->erpPdo->prepare("
                INSERT INTO stockchanges_items
                (stk_id, item_id, item_pos, item_amount, item_type, item_st_id,
                 item_costprice_brutto, item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes,
                 item_charges_act, item_sellprice_brutto, item_reboprod_amt, item_reboprod_unitkg)
                VALUES
                (:stk_id, :itid, 0, :amt, 'item', :sthid, 0, 0, 0, 0, 0, 0, 1, :unitkg)
            ")->execute([
                ':stk_id' => $stkIdOut,
                ':itid' => $selItemId,
                ':amt' => $origenUnits,
                ':sthid' => $stkFixedSthId,
                ':unitkg' => $useKgAmt,
            ]);

            // Descontar inventario de la bobina madre en Bodega 3000
            try {
                $this->erpPdo->prepare("
                    UPDATE item_shops_storehouses
                    SET iss_inventory = GREATEST(0, iss_inventory - :amt)
                    WHERE item_id = :itid AND st_id = :stid
                ")->execute([':amt' => $origenUnits, ':itid' => $selItemId, ':stid' => $stkFixedSthId]);
            } catch (Throwable) {}

            // 2. Dar de alta los rollos de subproductos cortados
            $prodItems = [];
            foreach ($postData as $reqKey => $val) {
                if (str_starts_with($reqKey, 'stockplustotalkg_')) {
                    $idx = (int)substr($reqKey, strlen('stockplustotalkg_'));
                    $destItemId = (int)($postData["stockplusitemid_{$idx}"] ?? 0);
                    $destDims = round((float)str_replace(',', '.', (string)($postData["rollo_dims_{$idx}"] ?? 0)), 2);
                    $destUseKg = (float)str_replace(',', '.', (string)($postData["stockplustotalkg_{$idx}"] ?? 0));
                    $reboAmt = max(1, (int)($postData["reboamt_{$idx}"] ?? 1));
                    $destLargo = (float)($postData["stockpluslargo_{$idx}"] ?? 0);
                    $destinoLabel = trim((string)($postData["rebo_destino_{$idx}"] ?? ''));
                    $destType = $this->isReboHandleDestination($destinoLabel, $destDims, $agenda) ? 'handle' : 'bag';

                    if ($destDims <= 0 || $destUseKg <= 0) {
                        continue;
                    }

                    // Si no tiene destItemId, buscar si existe un ítem con ese ancho y color, o crearlo
                    if ($destItemId <= 0) {
                        $destItemId = $this->findOrCreateCutItem($itemSel, $destDims, $destLargo, $userId);
                    }

                    // Determinar peso base para cálculo de unidades
                    $destBaseKg = 0.0;
                    if ($destItemId > 0) {
                        $stmtDI = $this->erpPdo->prepare("SELECT item_reg_kg FROM item WHERE id = :id LIMIT 1");
                        $stmtDI->execute([':id' => $destItemId]);
                        $destBaseKg = (float)($stmtDI->fetchColumn() ?: 0);
                    }
                    if ($destBaseKg <= 0) {
                        $destBaseKg = round($destUseKg / $reboAmt, 2);
                    }

                    $destUnits = round($destUseKg / $destBaseKg, 10);
                    if ($destUnits <= 0) {
                        $destUnits = $reboAmt;
                    }

                    $prodItems[] = [
                        'dest_itemid' => $destItemId,
                        'dest_units' => $destUnits,
                        'item_reboprod_amt' => $reboAmt,
                        'item_reboprod_unitkg' => round($destUseKg / $reboAmt, 2),
                        'dest_dims' => $destDims,
                        'dest_largo' => $destLargo,
                        'destino_label' => $destinoLabel,
                        'dest_type' => $destType,
                    ];
                }
            }

            // Insertar cabecera e ítems de entrada (stk_negative = 0, issue 20 = Generado por producción)
            if (!empty($prodItems)) {
                $stkNumIn = $this->generateNextStockChangeNumber($companyId);
                $stmtSCIn = $this->erpPdo->prepare("
                    INSERT INTO stockchanges
                    (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate, stk_negative,
                     stk_status, stk_crtdat, stk_crtusr, stk_fixedsthid, sth_fromrebo_agid, sth_fromrebo_agid_grphash,
                     sth_fromprodotid, sth_order_id, sth_order_num, sth_planta_id, sth_assign_wrkid, sth_assign_equipoid)
                    VALUES
                    (:num, 'Generado por producción', 20, :compid, :shopid, :bdate, 0, 2, :crtdat, :crtusr, :sthid, :agid, :grphash,
                     :ot_id, :order_id, :order_num, :planta_id, :worker_id, :equipo_id)
                ");
                $stmtSCIn->execute([
                    ':num' => $stkNumIn,
                    ':compid' => $companyId > 0 ? $companyId : 20010,
                    ':shopid' => $reqShopId,
                    ':bdate' => $now,
                    ':crtdat' => $now,
                    ':crtusr' => $userId,
                    ':sthid' => $stkFixedSthId,
                    ':agid' => $agId,
                    ':grphash' => $grpHash,
                    ':ot_id' => $workerOtId,
                    ':order_id' => $orderId,
                    ':order_num' => $orderNum,
                    ':planta_id' => $plantaId,
                    ':worker_id' => $userId,
                    ':equipo_id' => $equipoId,
                ]);
                $stkIdIn = (int)$this->erpPdo->lastInsertId();

                $pos = 0;
                foreach ($prodItems as $pRow) {
                    $this->erpPdo->prepare("
                        INSERT INTO stockchanges_items
                        (stk_id, item_id, item_pos, item_amount, item_type, item_st_id,
                         item_costprice_brutto, item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes,
                         item_charges_act, item_sellprice_brutto, item_reboprod_amt, item_reboprod_unitkg)
                        VALUES
                        (:stk_id, :itid, :pos, :amt, 'item', :sthid, 0, 0, 0, 0, 0, 0, :reboamt, :unitkg)
                    ")->execute([
                        ':stk_id' => $stkIdIn,
                        ':itid' => $pRow['dest_itemid'],
                        ':pos' => $pos++,
                        ':amt' => $pRow['dest_units'],
                        ':sthid' => $stkFixedSthId,
                        ':reboamt' => $pRow['item_reboprod_amt'],
                        ':unitkg' => $pRow['item_reboprod_unitkg'],
                    ]);

                    // Incrementar inventario del subproducto en Bodega 3000
                    try {
                        $upd = $this->erpPdo->prepare("
                            UPDATE item_shops_storehouses
                            SET iss_inventory = iss_inventory + :amt
                            WHERE item_id = :itid AND st_id = :stid
                        ");
                        $upd->execute([':amt' => $pRow['dest_units'], ':itid' => $pRow['dest_itemid'], ':stid' => $stkFixedSthId]);
                        if ($upd->rowCount() === 0) {
                            $this->erpPdo->prepare("
                                INSERT INTO item_shops_storehouses
                                (item_id, shop_id, st_id, iss_inventory)
                                VALUES (:itid, :shopid, :stid, :amt)
                            ")->execute([':itid' => $pRow['dest_itemid'], ':shopid' => $reqShopId, ':stid' => $stkFixedSthId, ':amt' => $pRow['dest_units']]);
                        }
                    } catch (Throwable) {}
                }
            }

            // Registrar eventos en la OT para informe de consumo y trazabilidad
            if ($workerOtId > 0) {
                try {
                    $mermaKg = (float)str_replace(',', '.', (string)($postData['itemmermakg'] ?? 0));

                    // 1. Evento de consumo de materiales (para informe oficial de consumo libs/modules/stats/prod/consumo.php)
                    $this->erpPdo->prepare("
                        INSERT INTO prod_worker_ot_events
                        (evt_prod_worker_otid, evt_type, evt_amount, prod_bobina_kg, evt_crtdat, evt_enddat, evt_status, evt_comments)
                        VALUES
                        (:ot_id, 'materiales', :amt, :kg, :now, :now, 1, :comments)
                    ")->execute([
                        ':ot_id' => $workerOtId,
                        ':amt' => $useKgAmt,
                        ':kg' => $useKgAmt,
                        ':now' => $now,
                        ':comments' => "Consumo bobina rebobinado: {$itemSel['item_number_prod']} ({$useKgAmt} Kg)",
                    ]);

                    // 2. Evento de registro de corte/producción
                    $totalCuts = count($prodItems);
                    $this->erpPdo->prepare("
                        INSERT INTO prod_worker_ot_events
                        (evt_prod_worker_otid, evt_type, evt_amount, prod_bobina_kg, evt_amount2, evt_crtdat, evt_enddat, evt_status, evt_comments)
                        VALUES
                        (:ot_id, 'prod', :amt, :kg, :merma, :now, :now, 1, :comments)
                    ")->execute([
                        ':ot_id' => $workerOtId,
                        ':amt' => $totalCuts > 0 ? $totalCuts : 1,
                        ':kg' => $useKgAmt,
                        ':merma' => $mermaKg,
                        ':now' => $now,
                        ':comments' => "Corte de bobina finalizado - Origen: {$itemSel['item_number_prod']} ({$useKgAmt} Kg consumidos, Merma: {$mermaKg} Kg)",
                    ]);
                } catch (Throwable) {}
            }

            // Actualizar estado de la bobina en TRZ (Trazabilidad) y crear bobinas hijas
            $rollIdPost = (int)($postData['scanned_roll_id'] ?? $postData['roll_id'] ?? 0);
            $rollCodePost = trim((string)($postData['scanned_roll_code'] ?? $postData['roll_code'] ?? ($itemSel['item_number_prod'] ?? '')));
            $createdChildRolls = [];
            $createdBagRolls = [];
            $createdHandleRolls = [];
            $createdHandlePallet = null;
            if ($this->trzPdo !== null) {
                try {
                    if ($rollIdPost <= 0 && $rollCodePost !== '') {
                        $stmtFind = $this->trzPdo->prepare("SELECT id FROM rolls WHERE roll_code = :c LIMIT 1");
                        $stmtFind->execute([':c' => $rollCodePost]);
                        $rollIdPost = (int)($stmtFind->fetchColumn() ?: 0);
                    }

                    if ($rollIdPost > 0) {
                        $this->trzPdo->prepare("
                            UPDATE rolls
                            SET status = 'CONSUMED', weight_kg = GREATEST(0, weight_kg - :used)
                            WHERE id = :id
                        ")->execute([':used' => $useKgAmt, ':id' => $rollIdPost]);
                    } elseif ($rollCodePost !== '') {
                        $this->trzPdo->prepare("
                            UPDATE rolls
                            SET status = 'CONSUMED', weight_kg = GREATEST(0, weight_kg - :used)
                            WHERE roll_code = :c
                        ")->execute([':used' => $useKgAmt, ':c' => $rollCodePost]);
                    }

                    // Generar bobinas hijas cortadas (CUT) en TRZ
                    foreach ($prodItems as $pRow) {
                        $reboAmt = (int)($pRow['item_reboprod_amt'] ?? 1);
                        $unitKg = (float)($pRow['item_reboprod_unitkg'] ?? 0);
                        $widthMm = (int)round(($pRow['dest_dims'] ?? 0) * 10);
                        $meters = (float)($pRow['dest_largo'] ?? 0);
                        $destItemId = (int)($pRow['dest_itemid'] ?? 0);
                        $destType = (string)($pRow['dest_type'] ?? 'bag');
                        $destinoLabel = trim((string)($pRow['destino_label'] ?? ''));

                        $skuCode = ($destType === 'handle' ? 'CUT-HANDLE-' : 'CUT-') . $destItemId;
                        $skuId = 1;
                        try {
                            $stmtSku = $this->trzPdo->prepare("SELECT id FROM skus WHERE code = :c LIMIT 1");
                            $stmtSku->execute([':c' => $skuCode]);
                            $foundSku = $stmtSku->fetchColumn();
                            if ($foundSku) {
                                $skuId = (int)$foundSku;
                            } else {
                                $stmtInsSku = $this->trzPdo->prepare("INSERT INTO skus (code, description, is_active) VALUES (:c, :d, 1)");
                                $stmtInsSku->execute([
                                    ':c' => $skuCode,
                                    ':d' => $destType === 'handle'
                                        ? ("Manilla Cortada {$pRow['dest_dims']}cm")
                                        : ("Bobina Cortada {$pRow['dest_dims']}cm"),
                                ]);
                                $skuId = (int)$this->trzPdo->lastInsertId();
                            }
                        } catch (Throwable) {}

                        for ($k = 1; $k <= $reboAmt; $k++) {
                            try {
                                $stmtMax = $this->trzPdo->query("SELECT MAX(id) FROM rolls");
                                $nextNum = ((int)$stmtMax->fetchColumn()) + 1;
                                $childRollCode = $destType === 'handle'
                                    ? sprintf('ROLL-HDL-%05d', $nextNum)
                                    : sprintf('ROLL-CUT-%05d', $nextNum);

                                $insRoll = $this->trzPdo->prepare("
                                    INSERT INTO rolls
                                    (roll_code, sku_id, warehouse_id, weight_kg, received_qty, width_mm, meters, status, parent_roll_id, source_work_order_id, process_stage)
                                    VALUES
                                    (:code, :sku, 1, :weight, 1.000, :width, :meters, 'RECEIVED', :parent, :wo_id, 'CUT')
                                ");
                                $insRoll->execute([
                                    ':code' => $childRollCode,
                                    ':sku' => $skuId,
                                    ':weight' => number_format($unitKg, 3, '.', ''),
                                    ':width' => $widthMm > 0 ? $widthMm : null,
                                    ':meters' => $meters > 0 ? $meters : null,
                                    ':parent' => $rollIdPost > 0 ? $rollIdPost : null,
                                    ':wo_id' => $workerOtId > 0 ? $workerOtId : null,
                                ]);
                                $childRollId = (int)$this->trzPdo->lastInsertId();

                                $stmtEvt = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
                                $stmtEvt->execute([
                                    ':type' => 'CHILD_ROLL_CREATED',
                                    ':payload' => json_encode([
                                        'child_roll_id' => $childRollId,
                                        'child_roll_code' => $childRollCode,
                                        'parent_roll_id' => $rollIdPost,
                                        'weight_kg' => $unitKg,
                                        'width_mm' => $widthMm,
                                        'meters' => $meters,
                                        'source_work_order_id' => $workerOtId,
                                        'operator_id' => $userId,
                                        'timestamp' => $now,
                                    ], JSON_UNESCAPED_UNICODE),
                                ]);

                                $createdChildRolls[] = [
                                    'id' => $childRollId,
                                    'roll_code' => $childRollCode,
                                    'weight_kg' => $unitKg,
                                    'width_mm' => $widthMm,
                                    'meters' => $meters,
                                    'dest_type' => $destType,
                                    'destino_label' => $destinoLabel,
                                ];
                                if ($destType === 'handle') {
                                    $createdHandleRolls[] = [
                                        'id' => $childRollId,
                                        'roll_code' => $childRollCode,
                                        'weight_kg' => $unitKg,
                                        'width_mm' => $widthMm,
                                        'meters' => $meters,
                                        'destino_label' => $destinoLabel,
                                    ];
                                } else {
                                    $createdBagRolls[] = [
                                        'id' => $childRollId,
                                        'roll_code' => $childRollCode,
                                        'weight_kg' => $unitKg,
                                        'width_mm' => $widthMm,
                                        'meters' => $meters,
                                        'destino_label' => $destinoLabel,
                                    ];
                                }
                            } catch (Throwable) {}
                        }
                    }

                    if (!empty($createdHandleRolls)) {
                        $stmtMaxPal = $this->trzPdo->query("SELECT MAX(id) FROM pallets");
                        $nextPalNum = ((int)$stmtMaxPal->fetchColumn()) + 1;
                        $palletCode = sprintf('PAL-%06d', $nextPalNum);
                        $handleCount = count($createdHandleRolls);
                        $finalSku = 'MANILLAS';
                        if (!empty($createdHandleRolls[0]['width_mm'])) {
                            $finalSku .= ' ' . number_format(((float)$createdHandleRolls[0]['width_mm']) / 10, 1, ',', '.') . 'cm';
                        }
                        $insPal = $this->trzPdo->prepare("
                            INSERT INTO pallets
                            (pallet_code, work_order_id, source_roll_id, final_sku, destination_mode, customer_order_ref, warehouse_id, box_count, operator_name, status)
                            VALUES
                            (:code, :wo_id, :roll_id, :sku, :dest, :cust_ref, 1, :box_count, :op, 'OPEN')
                        ");
                        $insPal->execute([
                            ':code' => $palletCode,
                            ':wo_id' => $workerOtId > 0 ? $workerOtId : null,
                            ':roll_id' => $rollIdPost > 0 ? $rollIdPost : null,
                            ':sku' => $finalSku,
                            ':dest' => 'REBO_HANDLE_PALLET',
                            ':cust_ref' => $orderNum !== '' ? $orderNum : null,
                            ':box_count' => $handleCount,
                            ':op' => trim($operatorName) !== '' ? trim($operatorName) : ('Usuario ' . $userId),
                        ]);
                        $palletId = (int)$this->trzPdo->lastInsertId();

                        $stmtEvt = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
                        $stmtEvt->execute([
                            ':type' => 'REBO_HANDLE_PALLET_CREATED',
                            ':payload' => json_encode([
                                'pallet_id' => $palletId,
                                'pallet_code' => $palletCode,
                                'source_roll_id' => $rollIdPost,
                                'handle_roll_ids' => array_column($createdHandleRolls, 'id'),
                                'handle_roll_codes' => array_column($createdHandleRolls, 'roll_code'),
                                'handle_roll_count' => $handleCount,
                                'source_work_order_id' => $workerOtId,
                                'operator_id' => $userId,
                                'operator_name' => trim($operatorName) !== '' ? trim($operatorName) : null,
                                'timestamp' => $now,
                            ], JSON_UNESCAPED_UNICODE),
                        ]);

                        $createdHandlePallet = [
                            'id' => $palletId,
                            'code' => $palletCode,
                            'handle_count' => $handleCount,
                        ];
                    }
                } catch (Throwable) {}
            }
        }

        return [
            'ok' => true,
            'rebo_type' => 'Corte',
            'consumed' => true,
            'created_rolls' => $createdChildRolls,
            'created_bag_rolls' => $createdBagRolls,
            'created_handle_rolls' => $createdHandleRolls,
            'handle_pallet' => $createdHandlePallet,
        ];
    }

    private function isReboHandleDestination(string $destinoLabel, float $destDims, array $agenda): bool
    {
        $dest = mb_strtolower(trim($destinoLabel));
        if ($dest !== '') {
            if (str_contains($dest, 'manilla')) {
                return true;
            }
            if (str_contains($dest, 'bolsa')) {
                return false;
            }
        }

        $anchoManillas = (float)($agenda['req_infoaddprd_ancho_manillas'] ?? 0);
        if ($anchoManillas > 0 && $destDims > 0 && $destDims <= ($anchoManillas + 1.0)) {
            return true;
        }

        return $destDims > 0 && $destDims <= 15.0;
    }

    /**
     * Genera el siguiente número de transacción de stock (company_numcounter_stockchange).
     */
    private function generateNextStockChangeNumber(int $companyId): string
    {
        $compId = $companyId > 0 ? $companyId : 20010;
        try {
            $this->erpPdo->beginTransaction();
            $stmt = $this->erpPdo->prepare("
                SELECT company_numformat_stockchange, company_numcounter_stockchange
                FROM company_data WHERE id = :id FOR UPDATE
            ");
            $stmt->execute([':id' => $compId]);
            $cd = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $prefix = !empty($cd['company_numformat_stockchange']) ? trim((string)$cd['company_numformat_stockchange']) : 'IA';
            $nextCount = ((int)($cd['company_numcounter_stockchange'] ?? 33000)) + 1;

            $this->erpPdo->prepare("
                UPDATE company_data
                SET company_numcounter_stockchange = :c
                WHERE id = :id
            ")->execute([':c' => $nextCount, ':id' => $compId]);

            $this->erpPdo->commit();
            return $prefix . sprintf('%08d', $nextCount);
        } catch (Throwable) {
            if ($this->erpPdo->inTransaction()) {
                $this->erpPdo->rollBack();
            }
            return 'IA' . sprintf('%08d', time() % 100000000);
        }
    }

    /**
     * Busca un ítem existente o crea uno nuevo para un subproducto cortado (form.reboprod.php líneas 150-330).
     */
    private function findOrCreateCutItem(array $baseItem, float $destDims, float $destLargo, int $userId): int
    {
        $gsm = (float)($baseItem['item_reg_gsm'] ?? 80);
        $colorId = (int)($baseItem['colorid'] ?? 0);
        $width = round($destDims);

        // 1. Buscar si ya existe un ítem en catálogo con este ancho, gramaje y color
        try {
            $sql = "SELECT DISTINCT t1.id
                    FROM item t1
                    INNER JOIN item_productcats t2 ON t1.id = t2.item_id
                    INNER JOIN tran_comments_item_vals t4 ON t4.item_id = t1.id
                    WHERE
                        t1.item_status > 0 AND
                        t2.cat_id IN (6, 27) AND
                        t1.item_reg_gsm = :gsm AND
                        t1.item_reg_width = :w";
            if ($colorId > 0) {
                $sql .= " AND t4.com_id = 17 AND t4.val_id = {$colorId}";
            }
            $sql .= " ORDER BY t1.id DESC LIMIT 1";
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute([':gsm' => $gsm, ':w' => $width]);
            $existingId = (int)$stmt->fetchColumn();
            if ($existingId > 0) {
                return $existingId;
            }
        } catch (Throwable) {}

        // 2. Si no existe, crear un nuevo item clonando el baseItem
        try {
            $now = time();
            $stmtCols = $this->erpPdo->query("SHOW COLUMNS FROM item");
            $allCols = $stmtCols->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $colsToCopy = array_diff($allCols, ['id']);
            $colList = implode(', ', $colsToCopy);

            $this->erpPdo->prepare("
                INSERT INTO item ({$colList})
                SELECT {$colList} FROM item WHERE id = :bid
            ")->execute([':bid' => $baseItem['id']]);
            $newItemId = (int)$this->erpPdo->lastInsertId();

            if ($newItemId > 0) {
                // Generar nuevo código item_number_prod
                $isBte = str_contains((string)($baseItem['item_number_prod'] ?? ''), 'BTE');
                $catId = $isBte ? 27 : 6;
                $prefix = $isBte ? 'BTE' : 'TEL';

                $stmtCount = $this->erpPdo->prepare("
                    SELECT item_number_prod FROM item WHERE item_number_prod LIKE :p ORDER BY id DESC LIMIT 50
                ");
                $stmtCount->execute([':p' => "{$prefix}%"]);
                $existingProds = $stmtCount->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $maxNum = 1;
                foreach ($existingProds as $ep) {
                    $num = (int)str_replace($prefix, '', (string)$ep);
                    if ($num >= $maxNum) $maxNum = $num + 1;
                }
                $newProdNumber = $prefix . sprintf('%04d', $maxNum);

                // Calcular peso estimado según ancho, gsm y largo
                $unitKg = round(($destDims * $gsm * ($destLargo > 0 ? $destLargo : 1000)) / 100000, 2);
                if ($unitKg <= 0) $unitKg = round($destDims * 0.8, 2);

                $this->erpPdo->prepare("
                    UPDATE item
                    SET item_number_prod = :pnum,
                        item_reg_width = :w,
                        item_reg_length = :l,
                        item_reg_kg = :kg,
                        item_crtdat = :now,
                        item_crtusr = :uid
                    WHERE id = :id
                ")->execute([
                    ':pnum' => $newProdNumber,
                    ':w' => $destDims,
                    ':l' => $destLargo > 0 ? $destLargo : ($baseItem['item_reg_length'] ?? 1000),
                    ':kg' => $unitKg,
                    ':now' => $now,
                    ':uid' => $userId,
                    ':id' => $newItemId,
                ]);

                // Asignar categoría
                $this->erpPdo->prepare("
                    INSERT INTO item_productcats (item_id, cat_id) VALUES (:iid, :cid)
                ")->execute([':iid' => $newItemId, ':cid' => $catId]);

                // Copiar características de tela (color, material)
                $this->erpPdo->prepare("
                    INSERT INTO tran_comments_item_vals (item_id, com_id, val_id)
                    SELECT :nid, com_id, val_id FROM tran_comments_item_vals WHERE item_id = :bid
                ")->execute([':nid' => $newItemId, ':bid' => $baseItem['id']]);

                return $newItemId;
            }
        } catch (Throwable) {}

        return (int)$baseItem['id'];
    }

    /**
     * Cierra cualquier evento de apertura / alistamiento abierto para la OT.
     */
    public function closeOpenAperturaEvent(int $workerOtId): bool
    {
        if ($workerOtId <= 0) return false;
        try {
            $now = time();
            $stmt = $this->erpPdo->prepare("
                UPDATE prod_worker_ot_events
                SET evt_enddat = :now
                WHERE evt_prod_worker_otid = :ot_id
                  AND evt_type = 'apertura'
                  AND (evt_enddat = 0 OR evt_enddat IS NULL)
                  AND evt_status > 0
            ");
            return $stmt->execute([':now' => $now, ':ot_id' => $workerOtId]);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Guarda la merma de alistamiento (Ajuste / Aprobación / Setup - Tipo 9) registrada por el operario.
     */
    public function saveAlistamientoMerma(int $workerOtId, float $amount, float $kgs, float $mts, string $comments = ''): bool
    {
        if ($workerOtId <= 0) return false;
        if ($amount <= 0 && $kgs <= 0 && $mts <= 0) return true; // Nada que guardar

        try {
            // Buscar si ya existe CUALQUIER registro de merma tipo 9 para esta OT (independiente del evento)
            $stmtExisting = $this->erpPdo->prepare("
                SELECT d.id, d.evt_refid
                FROM prod_worker_ot_defectunits d
                INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                WHERE e.evt_prod_worker_otid = :ot_id
                  AND d.evt_merma_typeid = 9
                  AND d.evt_status > 0
                ORDER BY d.id DESC LIMIT 1
            ");
            $stmtExisting->execute([':ot_id' => $workerOtId]);
            $existingRow = $stmtExisting->fetch(PDO::FETCH_ASSOC);

            // Buscar evento de apertura o el evento activo más reciente de la OT
            $stmtEvt = $this->erpPdo->prepare("
                SELECT id FROM prod_worker_ot_events
                WHERE evt_prod_worker_otid = :ot_id
                  AND evt_type = 'apertura'
                ORDER BY evt_status DESC, evt_crtdat DESC LIMIT 1
            ");
            $stmtEvt->execute([':ot_id' => $workerOtId]);
            $refId = (int)$stmtEvt->fetchColumn();

            // Si no hay evento de apertura, buscar cualquier evento activo de la OT
            if ($refId <= 0) {
                $stmtEvt2 = $this->erpPdo->prepare("
                    SELECT id FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :ot_id AND evt_status > 0
                    ORDER BY evt_crtdat DESC LIMIT 1
                ");
                $stmtEvt2->execute([':ot_id' => $workerOtId]);
                $refId = (int)$stmtEvt2->fetchColumn();
            }

            $comm = trim($comments) ?: 'Merma de Alistamiento / Ajuste (Setup)';
            $now = time();

            if ($existingRow && (int)$existingRow['id'] > 0) {
                $targetRefId = $refId > 0 ? $refId : (int)$existingRow['evt_refid'];
                $upd = $this->erpPdo->prepare("
                    UPDATE prod_worker_ot_defectunits
                    SET evt_amount = :amt, evt_kgstounits = :kgs, evt_mtstounits = :mts, evt_comments = :comm, evt_refid = :ref_id
                    WHERE id = :id
                ");
                return $upd->execute([':amt' => $amount, ':kgs' => $kgs, ':mts' => $mts, ':comm' => $comm, ':ref_id' => $targetRefId, ':id' => (int)$existingRow['id']]);
            } else {
                $ins = $this->erpPdo->prepare("
                    INSERT INTO prod_worker_ot_defectunits
                    (evt_amount, evt_crtdat, evt_status, evt_type, evt_comments, evt_merma_typeid, evt_repair_typeid, evt_refid, evt_kgstounits, evt_mtstounits)
                    VALUES
                    (:amt, :crtdat, 1, 'merma', :comm, 9, 0, :ref_id, :kgs, :mts)
                ");
                return $ins->execute([
                    ':amt' => $amount,
                    ':crtdat' => $now,
                    ':comm' => $comm,
                    ':ref_id' => $refId,
                    ':kgs' => $kgs,
                    ':mts' => $mts,
                ]);
            }
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Retorna la merma de alistamiento registrada para la OT (Tipo 9: Ajuste / Aprobación).
     */
    public function getAlistamientoMerma(int $workerOtId): ?array
    {
        if ($workerOtId <= 0) return null;
        try {
            $stmt = $this->erpPdo->prepare("
                SELECT d.*
                FROM prod_worker_ot_defectunits d
                JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                WHERE e.evt_prod_worker_otid = :ot_id
                  AND d.evt_merma_typeid = 9
                  AND d.evt_status > 0
                ORDER BY d.id DESC LIMIT 1
            ");
            $stmt->execute([':ot_id' => $workerOtId]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                return [
                    'id' => (int)$r['id'],
                    'evt_refid' => (int)$r['evt_refid'],
                    'amount' => (float)$r['evt_amount'],
                    'kgs' => (float)$r['evt_kgstounits'],
                    'mts' => (int)$r['evt_mtstounits'],
                    'comments' => (string)$r['evt_comments'],
                    'evt_amount' => (float)$r['evt_amount'],
                    'evt_kgstounits' => (float)$r['evt_kgstounits'],
                    'evt_mtstounits' => (int)$r['evt_mtstounits'],
                    'evt_comments' => (string)$r['evt_comments'],
                ];
            }
            return null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Retorna las mermas registradas durante el desmontaje de bobinas para la OT,
     * agrupadas por tipo de merma (prod_mermatypes.id => ['kgs' => ..., 'amount' => ..., 'mts' => ..., 'comments' => ...]).
     */
    public function getRollDismountMermas(int $workerOtId): array
    {
        if ($workerOtId <= 0) {
            return [];
        }
        $result = [];
        try {
            $stmt = $this->erpPdo->prepare("
                SELECT d.evt_merma_typeid, 
                       SUM(d.evt_kgstounits) AS total_kgs, 
                       SUM(d.evt_amount) AS total_amount,
                       SUM(d.evt_mtstounits) AS total_mts,
                       GROUP_CONCAT(NULLIF(d.evt_comments, '') SEPARATOR '; ') AS comments,
                       COUNT(*) AS rolls_count
                FROM prod_worker_ot_defectunits d
                INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                WHERE e.evt_prod_worker_otid = :ot_id
                  AND e.evt_type = 'merma'
                  AND d.evt_status > 0
                GROUP BY d.evt_merma_typeid
            ");
            $stmt->execute([':ot_id' => $workerOtId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $typeId = (int)$r['evt_merma_typeid'];
                $result[$typeId] = [
                    'type_id' => $typeId,
                    'kgs' => (float)$r['total_kgs'],
                    'amount' => (float)$r['total_amount'],
                    'mts' => (int)$r['total_mts'],
                    'comments' => (string)($r['comments'] ?? ''),
                    'rolls_count' => (int)($r['rolls_count'] ?? 0),
                ];
            }
        } catch (Throwable) {}

        // Fallback: si por alguna razón no hay registros en ERP pero sí en TRZ events
        if (empty($result) && $this->trzPdo !== null) {
            try {
                $stmtTrz = $this->trzPdo->prepare("
                    SELECT payload FROM events 
                    WHERE type = 'ROLL_OUTPUT_COMPLETED' 
                    ORDER BY id ASC
                ");
                $stmtTrz->execute();
                while ($tr = $stmtTrz->fetch(PDO::FETCH_ASSOC)) {
                    $p = json_decode((string)($tr['payload'] ?? ''), true);
                    if (is_array($p) && (int)($p['worker_ot_id'] ?? 0) === $workerOtId && !empty($p['mermas_detail']) && is_array($p['mermas_detail'])) {
                        foreach ($p['mermas_detail'] as $mtId => $mInfo) {
                            $tId = (int)$mtId;
                            $k = (float)($mInfo['kgs'] ?? 0);
                            $c = trim((string)($mInfo['comments'] ?? ''));
                            if ($k > 0 || $c !== '') {
                                if (!isset($result[$tId])) {
                                    $result[$tId] = [
                                        'type_id' => $tId,
                                        'kgs' => $k,
                                        'amount' => 0.0,
                                        'mts' => 0,
                                        'comments' => $c,
                                        'rolls_count' => 1,
                                    ];
                                } else {
                                    $result[$tId]['kgs'] += $k;
                                    $result[$tId]['rolls_count'] += 1;
                                    if ($c !== '') {
                                        $result[$tId]['comments'] .= ($result[$tId]['comments'] !== '' ? '; ' : '') . $c;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Throwable) {}
        }

        return $result;
    }

    /**
     * Retorna el resumen consolidado de las bobinas impresas semielaboradas generadas en la OT.
     * Incluye la suma total de peso (kg), la suma de metros lineales, el conteo de bobinas y el detalle.
     */
    public function getPrintedRollsSummary(int $workerOtId): array
    {
        if ($workerOtId <= 0) {
            return [
                'total_weight_kg' => 0.0,
                'total_meters' => 0.0,
                'rolls_count' => 0,
                'rolls_list' => [],
            ];
        }

        $rollsList = [];
        $totalWeight = 0.0;
        $totalMeters = 0.0;

        // 1. Consultar en TRZ (tabla rolls)
        if ($this->trzPdo !== null) {
            try {
                $stmt = $this->trzPdo->prepare("
                    SELECT id, roll_code, weight_kg, meters, parent_roll_id, created_at
                    FROM rolls
                    WHERE source_work_order_id = :ot_id
                      AND (process_stage = 'PRINTED' OR roll_code LIKE 'ROLL-SE-%')
                    ORDER BY id ASC
                ");
                $stmt->execute([':ot_id' => $workerOtId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    $w = (float)($r['weight_kg'] ?? 0);
                    $m = (float)($r['meters'] ?? 0);
                    $totalWeight += $w;
                    $totalMeters += $m;
                    $rollsList[] = [
                        'id' => (int)$r['id'],
                        'roll_code' => (string)$r['roll_code'],
                        'weight_kg' => $w,
                        'meters' => $m,
                        'parent_roll_id' => $r['parent_roll_id'] !== null ? (int)$r['parent_roll_id'] : null,
                        'created_at' => (string)($r['created_at'] ?? ''),
                    ];
                }
            } catch (Throwable) {}
        }

        // 2. Si no hubo resultados en rolls, verificar en events (CHILD_ROLL_CREATED) de TRZ
        if (empty($rollsList) && $this->trzPdo !== null) {
            try {
                $stmtEvt = $this->trzPdo->prepare("
                    SELECT id, payload, created_at
                    FROM events
                    WHERE type = 'CHILD_ROLL_CREATED'
                    ORDER BY id ASC
                ");
                $stmtEvt->execute();
                while ($erow = $stmtEvt->fetch(PDO::FETCH_ASSOC)) {
                    $p = json_decode((string)($erow['payload'] ?? ''), true);
                    if (is_array($p) && (int)($p['worker_ot_id'] ?? 0) === $workerOtId) {
                        $w = (float)($p['weight_kg'] ?? 0);
                        $m = (float)($p['meters'] ?? 0);
                        $code = (string)($p['child_roll_code'] ?? ('ROLL-' . ($p['child_roll_id'] ?? $erow['id'])));
                        $totalWeight += $w;
                        $totalMeters += $m;
                        $rollsList[] = [
                            'id' => (int)($p['child_roll_id'] ?? $erow['id']),
                            'roll_code' => $code,
                            'weight_kg' => $w,
                            'meters' => $m,
                            'parent_roll_id' => !empty($p['parent_roll_id']) ? (int)$p['parent_roll_id'] : null,
                            'created_at' => (string)($erow['created_at'] ?? ''),
                        ];
                    }
                }
            } catch (Throwable) {}
        }

        // 3. Fallback en ERP: eventos que registran 'Bobina Semielaborada'
        if (empty($rollsList) && $this->erpPdo !== null) {
            try {
                $stmtErp = $this->erpPdo->prepare("
                    SELECT id, evt_amount, prod_bobina_kg, evt_amount_metros_lineales, evt_comments, evt_crtdat
                    FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :ot_id
                      AND evt_comments LIKE '%Bobina Semielaborada%'
                      AND evt_status > 0
                    ORDER BY id ASC
                ");
                $stmtErp->execute([':ot_id' => $workerOtId]);
                while ($erow = $stmtErp->fetch(PDO::FETCH_ASSOC)) {
                    $w = (float)($erow['prod_bobina_kg'] > 0 ? $erow['prod_bobina_kg'] : $erow['evt_amount']);
                    $m = (float)($erow['evt_amount_metros_lineales'] ?? 0);
                    if ($w > 0) {
                        $totalWeight += $w;
                        $totalMeters += $m;
                        $rollsList[] = [
                            'id' => (int)$erow['id'],
                            'roll_code' => 'BOB-SE-' . $erow['id'],
                            'weight_kg' => $w,
                            'meters' => $m,
                            'parent_roll_id' => null,
                            'created_at' => date('Y-m-d H:i:s', (int)$erow['evt_crtdat']),
                        ];
                    }
                }
            } catch (Throwable) {}
        }

        return [
            'total_weight_kg' => round($totalWeight, 3),
            'total_meters' => round($totalMeters, 2),
            'rolls_count' => count($rollsList),
            'rolls_list' => $rollsList,
        ];
    }

    /**
     * Determina si una máquina o agenda corresponde a Flexografía, Serigrafía o Pulpo Serigráfico.
     */
    public function isFlexoSeriPulpoMachine(?int $equipoId = null, ?array $agenda = null): bool
    {
        if ($agenda !== null) {
            $isFlexo = (int)($agenda['equipo_prod_isprinter_flexo'] ?? 0) === 1
                || (int)($agenda['equipo_type_id'] ?? 0) === 7
                || stripos((string)($agenda['type_ant_title'] ?? ''), 'flexo') !== false
                || stripos((string)($agenda['equipo_name'] ?? ''), 'flexo') !== false;
            $isSeri = (int)($agenda['equipo_prod_isprinter_seri'] ?? 0) === 1
                || in_array((int)($agenda['equipo_type_id'] ?? 0), [11, 22], true)
                || stripos((string)($agenda['type_ant_title'] ?? ''), 'seri') !== false
                || stripos((string)($agenda['equipo_name'] ?? ''), 'seri') !== false
                || stripos((string)($agenda['equipo_name'] ?? ''), 'pulpo') !== false;
            if ($isFlexo || $isSeri) {
                return true;
            }
        }

        if ($equipoId !== null && $equipoId > 0) {
            try {
                $stmt = $this->erpPdo->prepare("
                    SELECT e.id, e.equipo_name, e.equipo_type_id,
                           e.equipo_prod_isprinter_flexo, e.equipo_prod_isprinter_seri,
                           t.type_ant_title
                    FROM equipo e
                    LEFT JOIN equipo_type t ON e.equipo_type_id = t.id
                    WHERE e.id = :id
                    LIMIT 1
                ");
                $stmt->execute([':id' => $equipoId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    if ((int)$row['equipo_prod_isprinter_flexo'] === 1
                        || (int)$row['equipo_prod_isprinter_seri'] === 1
                        || in_array((int)$row['equipo_type_id'], [7, 11, 22], true)
                        || stripos((string)$row['equipo_name'], 'flexo') !== false
                        || stripos((string)$row['equipo_name'], 'seri') !== false
                        || stripos((string)$row['equipo_name'], 'pulpo') !== false
                        || stripos((string)($row['type_ant_title'] ?? ''), 'flexo') !== false
                        || stripos((string)($row['type_ant_title'] ?? ''), 'seri') !== false
                    ) {
                        return true;
                    }
                }
            } catch (Throwable) {}
        }

        return false;
    }

    /**
     * Lista todas las bobinas disponibles actualmente en Bodega 3000 (Insumos/MP en producción).
     * Incluye análisis de compatibilidad opcional con una OT si se pasa $agId.
     * Si la máquina corresponde a Flexografía, Serigrafía o Pulpo Serigráfico,
     * SOLO se incluyen códigos iniciados en TEL (telas vírgenes), excluyendo bolsas y bobinas ya impresas/procesadas.
     */
    public function listAvailableRollsInBodega3000(?int $agId = null, int $limit = 100, ?bool $unprintedOnly = null, ?int $equipoId = null, bool $compatibleOnly = false): array
    {
        $rolls = [];

        // Datos de la OT para compatibilidad si se proporciona
        $targetMat = '';
        $targetColor = '';
        $targetWidth = 0.0;
        $targetWidthMm = 0;
        $targetGrams = 0.0;
        $agenda = null;
        if ($agId !== null && $agId > 0) {
            $agenda = $this->getWorkOrderAgendaDetails($agId);
            if ($agenda !== null) {
                $targetMat = mb_strtoupper(trim((string)($agenda['fab_type'] ?? '')));
                $targetColor = mb_strtolower(trim((string)($agenda['fabric_color'] ?? $agenda['ag_color_tela'] ?? '')));
                $targetWidth = (float)($agenda['req_operador_tela_width'] ?? $agenda['fab_med_width'] ?? 0);
                $targetGrams = (float)($agenda['ot_grams'] ?? 0);
                $targetWidthMm = (int)round($targetWidth > 0 && $targetWidth < 100 ? $targetWidth * 10 : $targetWidth);
                if ($equipoId === null && !empty($agenda['ag_equipo_id'])) {
                    $equipoId = (int)$agenda['ag_equipo_id'];
                }
            }
        }

        $isFlexoSeriPulpo = $this->isFlexoSeriPulpoMachine($equipoId, $agenda);
        $isPrinter = $isFlexoSeriPulpo;
        if ($unprintedOnly === null) {
            $unprintedOnly = $isFlexoSeriPulpo;
        }
        $telOnly = $isFlexoSeriPulpo;

        // 1. Bobinas en ERP unibag_unibag en Bodega 3000 (st_id IN (16, 27) o LIKE '3000%') con stock positivo
        try {
            $whereErp = $telOnly
                ? "AND (i.item_number_prod LIKE 'TEL%' OR i.item_number_prod LIKE 'tel%')"
                : "AND (
                      i.item_number_prod LIKE 'TEL%'
                      OR i.item_number_prod LIKE 'BTE%'
                      OR i.item_number_prod LIKE 'BOB%'
                      OR i.item_title LIKE 'PLA/%'
                      OR i.item_title LIKE 'PP/%'
                      OR i.item_title LIKE 'TNT/%'
                      OR i.item_title LIKE '%BOBINA%'
                      OR i.item_title LIKE '%TELA%'
                  )";

            $sqlErp = "
                SELECT iss.iss_inventory, iss.st_id, css.st_name as warehouse_name,
                       i.id as item_id, i.item_number_prod as sku_code, i.item_title as sku_desc,
                       i.item_reg_width as width_cm, i.item_reg_kg as weight_kg, i.item_reg_length as meters
                FROM item_shops_storehouses iss
                JOIN item i ON i.id = iss.item_id
                JOIN company_shops_storehouses css ON css.id = iss.st_id
                WHERE (iss.st_id IN (16, 27) OR css.st_name LIKE '%3000%')
                  AND iss.iss_inventory > 0
                  {$whereErp}
                ORDER BY iss.iss_inventory DESC
                LIMIT :lim
            ";
            $stmtErp = $this->erpPdo->prepare($sqlErp);
            $stmtErp->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmtErp->execute();
            $erpRows = $stmtErp->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($erpRows as $row) {
                $desc = (string)$row['sku_desc'];
                $sku = (string)$row['sku_code'];

                // Si es Flexo, Seri o Pulpo: REGLA ESTRICTA -> Solo códigos iniciados en TEL
                if ($telOnly && !str_starts_with(strtoupper($sku), 'TEL')) {
                    continue;
                }

                // Detección de bobina impresa
                $isPrinted = (stripos($desc, 'IMPRES') !== false || stripos($sku, 'IMP') !== false);
                if ($unprintedOnly && $isPrinted) {
                    continue; // Para Flexo y Seri se excluyen bobinas ya impresas
                }

                // Parser de materialidad (PLA / PP / TNT)
                $rMat = 'PP';
                if (stripos($desc, 'PLA') !== false || stripos($sku, 'PLA') !== false) {
                    $rMat = 'PLA';
                }

                // Parser de color
                $parts = explode('/', $desc);
                $color = trim($parts[1] ?? '-');
                $color = trim((string)preg_replace('/\(pantone[^\)]*\)/i', '', $color)) ?: '-';

                $widthCm = (float)$row['width_cm'];
                $gramms = 80.0;
                $meters = (float)($row['meters'] ?: 1100);
                if (preg_match('/\/(\d+(?:\.\d+)?)[xX](\d+(?:\.\d+)?)[xX](\d+)/i', $desc, $mD)) {
                    if ($widthCm <= 0) $widthCm = (float)$mD[1];
                    $gramms = (float)$mD[2];
                    $meters = (float)$mD[3];
                } elseif (preg_match('/\/(\d{2,3})\/(?:(\d{2,3})GR|)/i', $desc, $mD2)) {
                    if ($widthCm <= 0) $widthCm = (float)$mD2[1];
                    if (!empty($mD2[2])) $gramms = (float)$mD2[2];
                }
                if ($widthCm > 0 && $widthCm < 20) {
                    $widthCm *= 10;
                }

                $weightKg = (float)$row['weight_kg'];
                if ($weightKg <= 0 && $widthCm > 0) {
                    $weightKg = round(($widthCm / 100) * $meters * ($gramms / 1000), 1);
                }
                if ($weightKg <= 0) {
                    $weightKg = ($rMat === 'PLA' ? 120.0 : 85.0);
                }

                // Compatibilidad con la OT
                $isComp = false;
                $matMatch = true;
                $colMatch = true;
                $gramMatch = true;
                $widthMatch = true;
                if ($targetMat !== '') {
                    $normTarget = str_contains($targetMat, 'PLA') ? 'PLA' : 'PP';
                    $matMatch = ($rMat === $normTarget);
                }
                if ($targetColor !== '') {
                    $colMatch = str_contains(mb_strtolower($color), $targetColor) || str_contains($targetColor, mb_strtolower($color));
                }
                if ($targetGrams > 0 && $gramms > 0) {
                    $gramMatch = (abs($gramms - $targetGrams) <= 5);
                }
                if ($targetWidthMm > 0) {
                    $rollWidthMm = (int)round($widthCm * 10);
                    $widthMatch = ($rollWidthMm > 0 && abs($rollWidthMm - $targetWidthMm) <= 80) || ($rollWidthMm >= $targetWidthMm);
                }
                $isComp = ($matMatch && $colMatch && $gramMatch && $widthMatch && (!$isPrinter || !$isPrinted));

                $rolls[] = [
                    'id' => "BOD3000_{$row['st_id']}_{$row['item_id']}",
                    'source' => 'ERP',
                    'erp_item_id' => (int)$row['item_id'],
                    'st_id' => (int)$row['st_id'],
                    'roll_code' => "BOB-{$sku}-B3000",
                    'sku_code' => $sku,
                    'sku_desc' => $desc,
                    'material' => $rMat,
                    'color' => $color,
                    'width_cm' => $widthCm,
                    'grammage' => $gramms,
                    'meters' => $meters,
                    'weight_kg' => $weightKg,
                    'stock_qty' => (float)$row['iss_inventory'],
                    'warehouse_name' => (string)$row['warehouse_name'],
                    'is_printed' => $isPrinted,
                    'is_compatible' => $isComp,
                    'is_material_match' => $matMatch,
                    'is_color_match' => $colMatch,
                    'is_gram_match' => $gramMatch,
                    'is_width_match' => $widthMatch,
                ];
            }
        } catch (Throwable) {}

        // 2. Bobinas registradas en TRZ en Bodega 3000 o en proceso activo de producción
        if ($this->trzPdo !== null) {
            try {
                $whereTrzTel = $telOnly
                    ? "AND (s.code LIKE 'TEL%' OR s.code LIKE 'tel%' OR r.roll_code LIKE 'TEL%' OR r.roll_code LIKE 'tel%')"
                    : "";

                $sqlTrz = "
                    SELECT r.*, s.code as sku_code, s.description as sku_desc, w.name as warehouse_name
                    FROM rolls r
                    LEFT JOIN skus s ON r.sku_id = s.id
                    LEFT JOIN warehouses w ON r.warehouse_id = w.id
                    WHERE (w.code IN (3000, 16, 27) OR w.name LIKE '%3000%')
                      AND r.status != 'CONSUMED'
                      AND r.current_work_order_id IS NULL
                      {$whereTrzTel}
                    ORDER BY r.id DESC
                    LIMIT :lim
                ";
                $stmtTrz = $this->trzPdo->prepare($sqlTrz);
                $stmtTrz->bindValue(':lim', $limit, PDO::PARAM_INT);
                $stmtTrz->execute();
                $trzRows = $stmtTrz->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($trzRows as $row) {
                    $rCode = (string)$row['roll_code'];
                    $rDesc = (string)($row['sku_desc'] ?? '');
                    $skuCode = (string)($row['sku_code'] ?? '');

                    // Si es Flexo, Seri o Pulpo: REGLA ESTRICTA -> Solo códigos iniciados en TEL
                    if ($telOnly) {
                        $codeUpper = strtoupper($skuCode);
                        $rollUpper = strtoupper($rCode);
                        if (!str_starts_with($codeUpper, 'TEL') && !str_starts_with($rollUpper, 'TEL')) {
                            continue;
                        }
                    }

                    // Detección de bobina impresa en TRZ (RP-, ROLL-SE-, process_stage=PRINTED, o con source_work_order_id)
                    $isPrinted = ($row['process_stage'] === 'PRINTED'
                        || str_starts_with($rCode, 'RP-')
                        || str_starts_with($rCode, 'ROLL-SE-')
                        || !empty($row['source_work_order_id'])
                        || stripos($rDesc, 'IMPRES') !== false);

                    if ($unprintedOnly && $isPrinted) {
                        continue; // Para Flexo y Seri se excluyen bobinas ya impresas
                    }

                    $color = (string)($row['color'] ?? '-');
                    $rMat = 'PP';
                    if (stripos((string)$row['sku_code'], 'PLA') !== false || stripos($rDesc, 'PLA') !== false) {
                        $rMat = 'PLA';
                    }

                    $matMatch = ($targetMat === '' || (str_contains($targetMat, 'PLA') ? ($rMat === 'PLA') : ($rMat === 'PP')));
                    $colMatch = ($targetColor === '' || str_contains(mb_strtolower($color), $targetColor) || str_contains($targetColor, mb_strtolower($color)));
                    $gramms = (float)($row['microns'] ?? 80);
                    $gramMatch = true;
                    $widthMatch = true;
                    if ($targetGrams > 0 && $gramms > 0) {
                        $gramMatch = (abs($gramms - $targetGrams) <= 5);
                    }
                    if ($targetWidthMm > 0) {
                        $rWidthMm = (int)($row['width_mm'] ?? 0);
                        $widthMatch = ($rWidthMm > 0 && abs($rWidthMm - $targetWidthMm) <= 80) || ($rWidthMm >= $targetWidthMm);
                    }
                    $isComp = ($matMatch && $colMatch && $gramMatch && $widthMatch && (!$isPrinter || !$isPrinted));

                    $rolls[] = [
                        'id' => (int)$row['id'],
                        'source' => 'TRZ',
                        'erp_item_id' => 0,
                        'st_id' => 16,
                        'roll_code' => $rCode,
                        'sku_code' => (string)($row['sku_code'] ?? 'S/C'),
                        'sku_desc' => $rDesc,
                        'material' => $rMat,
                        'color' => $color,
                        'width_cm' => round(((float)($row['width_mm'] ?? 0)) / 10, 1),
                        'grammage' => $gramms,
                        'meters' => (float)($row['meters'] ?? 0),
                        'weight_kg' => (float)($row['weight_kg'] ?? 0),
                        'stock_qty' => (float)($row['received_qty'] ?? 1),
                        'warehouse_name' => (string)($row['warehouse_name'] ?? 'Bodega 3000'),
                        'is_printed' => $isPrinted,
                        'is_compatible' => $isComp,
                        'is_material_match' => $matMatch,
                        'is_color_match' => $colMatch,
                        'is_gram_match' => $gramMatch,
                        'is_width_match' => $widthMatch,
                    ];
                }
            } catch (Throwable) {}
        }

        if ($compatibleOnly && $agId !== null && $agId > 0) {
            $rolls = array_values(array_filter(
                $rolls,
                static fn(array $roll): bool => !empty($roll['is_compatible'])
            ));
        }

        return $rolls;
    }

    private function adjustBodega3000InventoryBySku(string $skuCode, int $delta): bool
    {
        $skuCode = trim($skuCode);
        if ($skuCode === '' || $delta === 0) {
            return false;
        }

        try {
            $stmtItem = $this->erpPdo->prepare("
                SELECT id
                FROM item
                WHERE item_number_prod = :sku OR item_number = :sku
                LIMIT 1
            ");
            $stmtItem->execute([':sku' => $skuCode]);
            $itemId = (int)$stmtItem->fetchColumn();
            if ($itemId <= 0) {
                return false;
            }

            $stmtRow = $this->erpPdo->prepare("
                SELECT iss.st_id, COALESCE(iss.shop_id, 1) AS shop_id
                FROM item_shops_storehouses iss
                INNER JOIN company_shops_storehouses css ON css.id = iss.st_id
                WHERE iss.item_id = :item_id
                  AND (iss.st_id IN (16, 27) OR css.st_name LIKE '%3000%')
                ORDER BY iss.iss_inventory DESC, iss.st_id ASC
                LIMIT 1
            ");
            $stmtRow->execute([':item_id' => $itemId]);
            $row = $stmtRow->fetch(PDO::FETCH_ASSOC) ?: [];

            $stId = (int)($row['st_id'] ?? 16);
            $shopId = (int)($row['shop_id'] ?? 1);

            if ($delta < 0) {
                $qty = abs($delta);
                $stmtUpd = $this->erpPdo->prepare("
                    UPDATE item_shops_storehouses
                    SET iss_inventory = GREATEST(0, iss_inventory - :qty)
                    WHERE item_id = :item_id
                      AND st_id = :st_id
                      AND iss_inventory >= :qty
                ");
                $stmtUpd->execute([
                    ':qty' => $qty,
                    ':item_id' => $itemId,
                    ':st_id' => $stId,
                ]);
                return $stmtUpd->rowCount() > 0;
            }

            $stmtUpd = $this->erpPdo->prepare("
                UPDATE item_shops_storehouses
                SET iss_inventory = iss_inventory + :qty
                WHERE item_id = :item_id
                  AND st_id = :st_id
            ");
            $stmtUpd->execute([
                ':qty' => $delta,
                ':item_id' => $itemId,
                ':st_id' => $stId,
            ]);
            if ($stmtUpd->rowCount() > 0) {
                return true;
            }

            $stmtIns = $this->erpPdo->prepare("
                INSERT INTO item_shops_storehouses (item_id, shop_id, st_id, iss_inventory)
                VALUES (:item_id, :shop_id, :st_id, :qty)
            ");
            $stmtIns->execute([
                ':item_id' => $itemId,
                ':shop_id' => $shopId,
                ':st_id' => $stId,
                ':qty' => $delta,
            ]);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Asigna una bobina de Bodega 3000 a la máquina y operario para una OT.
     * Soporta tanto trazabilidad estándar como saltarse la trazabilidad si la bobina no tiene código.
     */
    public function assignBodega3000RollToMachine(
        int $workerOtId,
        string $rollIdentifier,
        float $weightKg,
        string $operatorName,
        int $machineId,
        string $comments = '',
        bool $skipTraceability = false,
        ?int $agId = null
    ): array {
        if ($workerOtId <= 0) {
            return ['ok' => false, 'error' => 'Orden de trabajo inválida.'];
        }

        $now = time();

        // 1. MODO: SALTARSEN LA TRAZABILIDAD (Bobina sin código QR o código de trazabilidad)
        if ($skipTraceability) {
            $rollCodeDisplay = trim($rollIdentifier) !== '' ? trim($rollIdentifier) : 'BOBINA-MANUAL-' . date('ymd-His');
            $wKg = $weightKg > 0 ? $weightKg : 80.0;
            $evtComment = "Carga de bobina sin código de trazabilidad: {$rollCodeDisplay} ({$wKg} kg) asignada a máquina por {$operatorName}";
            if (trim($comments) !== '') {
                $evtComment .= " [Detalle: " . trim($comments) . "]";
            }

            // Registrar en eventos de la OT (ERP)
            try {
                $stmtErp = $this->erpPdo->prepare("
                    INSERT INTO prod_worker_ot_events
                     (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                    VALUES
                     (:ot_id, :amt, :crtdat, :enddat, 2, 'materiales', :comments)
                ");
                $stmtErp->execute([
                    ':ot_id' => $workerOtId,
                    ':amt' => $wKg,
                    ':crtdat' => $now,
                    ':enddat' => $now,
                    ':comments' => $evtComment,
                ]);
            } catch (Throwable $e) {
                return ['ok' => false, 'error' => 'Error al registrar evento en ERP: ' . $e->getMessage()];
            }

            // Registrar auditoría en TRZ si está disponible
            if ($this->trzPdo !== null) {
                try {
                    $stmtEvtTrz = $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)");
                    $stmtEvtTrz->execute([
                        ':type' => 'ROLL_LOADED_WITHOUT_TRACEABILITY',
                        ':payload' => json_encode([
                            'worker_ot_id' => $workerOtId,
                            'roll_identifier' => $rollCodeDisplay,
                            'weight_kg' => $wKg,
                            'operator' => $operatorName,
                            'machine_id' => $machineId,
                            'comments' => $comments,
                            'timestamp' => $now,
                            'note' => 'Cargada con salto de trazabilidad autorizado',
                        ], JSON_UNESCAPED_UNICODE),
                    ]);
                } catch (Throwable) {}
            }

            return ['ok' => true, 'roll_code' => $rollCodeDisplay, 'message' => 'Bobina sin trazabilidad cargada exitosamente a la máquina y OT.'];
        }

        // 2. MODO: CON TRAZABILIDAD (Seleccionada desde Bodega 3000 o código QR)
        $agDetails = ($agId !== null && $agId > 0) ? $this->getWorkOrderAgendaDetails($agId) : null;
        $isFlexoSeriPulpo = $this->isFlexoSeriPulpoMachine($machineId, $agDetails);

        // Caso A: Selección desde ERP_stId_itemId o BOD3000_stId_itemId
        if (preg_match('/^(?:BOD3000|ERP)_(\d+)_(\d+)$/', $rollIdentifier, $m)) {
            $stId = (int)$m[1];
            $itemId = (int)$m[2];

            $stmtItem = $this->erpPdo->prepare("
                SELECT i.*, css.st_name, iss.iss_inventory
                FROM item i
                JOIN item_shops_storehouses iss ON i.id = iss.item_id AND iss.st_id = :stid
                JOIN company_shops_storehouses css ON css.id = iss.st_id
                WHERE i.id = :itid LIMIT 1
            ");
            $stmtItem->execute([':stid' => $stId, ':itid' => $itemId]);
            $item = $stmtItem->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                return ['ok' => false, 'error' => 'Material de Bodega 3000 no encontrado en ERP.'];
            }

            $sku = (string)$item['item_number_prod'];
            if ($isFlexoSeriPulpo && !str_starts_with(strtoupper($sku), 'TEL')) {
                return [
                    'ok' => false,
                    'error' => "En Flexografía, Serigrafía y Pulpo Serigráfico solo se pueden solicitar materiales de Bodega 3000 con códigos de tela (iniciados en TEL). El material {$sku} corresponde a bolsa o bobina ya hecha/procesada."
                ];
            }
            $desc = (string)$item['item_title'];
            $wKg = $weightKg > 0 ? $weightKg : (float)($item['item_reg_kg'] ?: 85.0);
            $rollCode = "BOB-{$sku}-" . date('ymd') . "-" . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

            $stmtConsume3000 = $this->erpPdo->prepare("
                UPDATE item_shops_storehouses
                SET iss_inventory = GREATEST(0, iss_inventory - 1)
                WHERE item_id = :item_id
                  AND st_id = :st_id
                  AND iss_inventory >= 1
            ");
            $stmtConsume3000->execute([
                ':item_id' => $itemId,
                ':st_id' => $stId,
            ]);
            if ($stmtConsume3000->rowCount() <= 0) {
                return ['ok' => false, 'error' => 'La bobina ya no tiene stock disponible en Bodega 3000.'];
            }

            // Si TRZ disponible, crear roll en TRZ en Bodega 3000 con estado IN_PROCESS
            if ($this->trzPdo !== null) {
                try {
                    // Asegurar SKU
                    $this->trzPdo->prepare("
                        INSERT INTO skus (code, description, is_active) VALUES (:c, :d, 1)
                        ON DUPLICATE KEY UPDATE description = VALUES(description)
                    ")->execute([':c' => $sku, ':d' => $desc]);
                    $skuId = (int)$this->trzPdo->lastInsertId();
                    if ($skuId <= 0) {
                        $stS = $this->trzPdo->prepare("SELECT id FROM skus WHERE code = :c LIMIT 1");
                        $stS->execute([':c' => $sku]);
                        $skuId = (int)$stS->fetchColumn();
                    }

                    // Asegurar Warehouse Bodega 3000
                    $this->trzPdo->prepare("
                        INSERT INTO warehouses (code, name, erp_storehouse_id)
                        VALUES (3000, '3000 INSUMOS EN PRODUCCION', :stid)
                        ON DUPLICATE KEY UPDATE name = VALUES(name)
                    ")->execute([':stid' => $stId]);
                    $whId = (int)$this->trzPdo->lastInsertId();
                    if ($whId <= 0) {
                        $stW = $this->trzPdo->prepare("SELECT id FROM warehouses WHERE code = 3000 LIMIT 1");
                        $stW->execute();
                        $whId = (int)$stW->fetchColumn();
                    }

                    // Insertar roll
                    $insRoll = $this->trzPdo->prepare("
                        INSERT INTO rolls
                            (roll_code, sku_id, warehouse_id, weight_kg, received_qty, reception_mode, status, current_work_order_id, process_stage, created_at)
                        VALUES
                            (:code, :sku_id, :wh_id, :weight_kg, 1, 'BODEGA_3000', 'IN_PROCESS', :ot_id, 'PRODUCTION', NOW())
                    ");
                    $insRoll->execute([
                        ':code' => $rollCode,
                        ':sku_id' => $skuId,
                        ':wh_id' => $whId,
                        ':weight_kg' => $wKg,
                        ':ot_id' => $workerOtId,
                    ]);
                    $newRollId = (int)$this->trzPdo->lastInsertId();

                    // Registrar evento en TRZ
                    $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)")->execute([
                        ':type' => 'ROLL_LOADED_TO_OT',
                        ':payload' => json_encode([
                            'roll_id' => $newRollId,
                            'roll_code' => $rollCode,
                            'sku_code' => $sku,
                            'weight_kg' => $wKg,
                            'worker_ot_id' => $workerOtId,
                            'operator' => $operatorName,
                            'machine_id' => $machineId,
                            'comments' => $comments,
                            'timestamp' => $now,
                            'warehouse' => 'Bodega 3000',
                        ], JSON_UNESCAPED_UNICODE),
                    ]);
                } catch (Throwable) {}
            }

            // Registrar en eventos de la OT (ERP)
            $evtComment = "Carga Bobina Bodega 3000: {$rollCode} [{$sku}] - {$desc} ({$wKg} kg) asignada a máquina por {$operatorName}";
            if (trim($comments) !== '') {
                $evtComment .= " (" . trim($comments) . ")";
            }

            $this->erpPdo->prepare("
                INSERT INTO prod_worker_ot_events
                 (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                VALUES
                 (:ot_id, :amt, :crtdat, :enddat, 2, 'materiales', :comments)
            ")->execute([
                ':ot_id' => $workerOtId,
                ':amt' => $wKg,
                ':crtdat' => $now,
                ':enddat' => $now,
                ':comments' => $evtComment,
            ]);

            return ['ok' => true, 'roll_code' => $rollCode, 'message' => "Bobina {$rollCode} asignada exitosamente a la OT y máquina."];
        }

        // Caso B: ID numérico de roll en TRZ
        $trzRollId = (int)$rollIdentifier;
        if ($trzRollId > 0 && $this->trzPdo !== null) {
            $stR = $this->trzPdo->prepare("SELECT r.*, s.code as sku_code, s.description as sku_desc FROM rolls r LEFT JOIN skus s ON r.sku_id = s.id WHERE r.id = :id LIMIT 1");
            $stR->execute([':id' => $trzRollId]);
            $roll = $stR->fetch(PDO::FETCH_ASSOC);
            if ($roll) {
                $existingOtId = (int)($roll['current_work_order_id'] ?? 0);
                if ($existingOtId > 0 && $existingOtId !== $workerOtId) {
                    return ['ok' => false, 'error' => 'La bobina ya fue tomada por otra OT y no está disponible en Bodega 3000.'];
                }

                $sku = (string)($roll['sku_code'] ?? 'S/C');
                $rCode = (string)$roll['roll_code'];
                if ($isFlexoSeriPulpo && !str_starts_with(strtoupper($sku), 'TEL') && !str_starts_with(strtoupper($rCode), 'TEL')) {
                    return [
                        'ok' => false,
                        'error' => "En Flexografía, Serigrafía y Pulpo Serigráfico solo se pueden solicitar materiales de Bodega 3000 con códigos de tela (iniciados en TEL). La bobina {$rCode} ({$sku}) corresponde a bolsa o bobina ya procesada."
                    ];
                }

                $isPrinted = ($roll['process_stage'] === 'PRINTED'
                    || str_starts_with((string)$roll['roll_code'], 'RP-')
                    || str_starts_with((string)$roll['roll_code'], 'ROLL-SE-')
                    || !empty($roll['source_work_order_id']));
                if ($isPrinted && $isFlexoSeriPulpo) {
                    return [
                        'ok' => false,
                        'error' => "La bobina {$roll['roll_code']} ya fue impresa previamente. Las máquinas de Flexografía y Serigrafía requieren bobinas vírgenes sin uso de impresión."
                    ];
                }

                $wKg = (float)$roll['weight_kg'];
                $rCode = (string)$roll['roll_code'];
                $sku = (string)($roll['sku_code'] ?? 'S/C');

                if ($sku !== '' && !$this->adjustBodega3000InventoryBySku($sku, -1)) {
                    return ['ok' => false, 'error' => 'No se pudo descontar la bobina del stock de Bodega 3000.'];
                }

                // Actualizar estado en TRZ
                $this->trzPdo->prepare("
                    UPDATE rolls
                    SET status = 'IN_PROCESS', current_work_order_id = :ot_id, process_stage = 'PRODUCTION'
                    WHERE id = :id
                ")->execute([':ot_id' => $workerOtId, ':id' => $trzRollId]);

                // Registrar evento en TRZ
                $this->trzPdo->prepare("INSERT INTO events (type, payload) VALUES (:type, :payload)")->execute([
                    ':type' => 'ROLL_LOADED_TO_OT',
                    ':payload' => json_encode([
                        'roll_id' => $trzRollId,
                        'roll_code' => $rCode,
                        'sku_code' => $sku,
                        'weight_kg' => $wKg,
                        'worker_ot_id' => $workerOtId,
                        'operator' => $operatorName,
                        'machine_id' => $machineId,
                        'comments' => $comments,
                        'timestamp' => $now,
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                // Registrar evento en ERP
                $evtComment = "Carga Bobina Trazable: {$rCode} [{$sku}] ({$wKg} kg) asignada a máquina por {$operatorName}";
                if (trim($comments) !== '') {
                    $evtComment .= " (" . trim($comments) . ")";
                }

                $this->erpPdo->prepare("
                    INSERT INTO prod_worker_ot_events
                     (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                    VALUES
                     (:ot_id, :amt, :crtdat, :enddat, 2, 'materiales', :comments)
                ")->execute([
                    ':ot_id' => $workerOtId,
                    ':amt' => $wKg,
                    ':crtdat' => $now,
                    ':enddat' => $now,
                    ':comments' => $evtComment,
                ]);

                return ['ok' => true, 'roll_code' => $rCode, 'message' => "Bobina {$rCode} asignada exitosamente a la máquina y OT."];
            }
        }

        return ['ok' => false, 'error' => 'Identificador de bobina no válido.'];
    }
}




