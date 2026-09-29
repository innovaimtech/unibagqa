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

        return is_array($row) ? $row : null;
    }

    /**
     * Lista las órdenes de trabajo programadas en agenda (`prod_agenda`) para la máquina indicada.
     *
     * @param int $equipoId ID de máquina
     * @param int $plantaId ID de planta
     * @return array<int, array<string, mixed>>
     */
    public function listScheduledOrdersForMachine(int $equipoId, int $plantaId): array
    {
        $wherePlanta = $plantaId > 0 ? " AND t0.ag_plantaid = :planta_id" : "";
        $sql = "SELECT DISTINCT t0.id AS ag_id, t0.ag_date, t0.ag_amount, t0.ag_status,
                               t3x.id AS prdid, t3x.prd_number,
                               t1.id AS req_id, t1.req_number, t1.req_production_initdate, t1.req_status,
                               t2.company_short, t3.shop_name, t4.cust_name,
                               t2x.item_number_prod, t2x.item_title, t1x.item_amount,
                               t1x.fab_printtype, t1x.fab_type, t1x.fab_med_width, t1x.fab_med_height,
                               t1x.fab_med_fuelle
                FROM prod_agenda t0
                INNER JOIN prod_header t3x ON t0.ag_prdid = t3x.id AND t3x.prd_status >= 2
                INNER JOIN orders t1 ON t0.ag_reqid = t1.id
                LEFT OUTER JOIN company_data t2 ON t1.req_company_id = t2.id
                LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id = t3.id
                LEFT OUTER JOIN customer t4 ON t1.req_cust_id = t4.id
                LEFT OUTER JOIN orders_items t1x ON t1.id = t1x.req_id
                LEFT OUTER JOIN item t2x ON t1x.item_id = t2x.id
                WHERE t0.ag_equipo_id = :equipo_id
                  {$wherePlanta}
                  AND t0.ag_status = 1
                ORDER BY t0.ag_date ASC, t0.ag_order ASC, t0.id ASC
                LIMIT 50";

        try {
            $params = [':equipo_id' => $equipoId];
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

        // Verificar si ya existe una OT abierta para este init
        $stmtCheck = $this->erpPdo->prepare(
            "SELECT id FROM prod_worker_ot
             WHERE wok_ag_id = :ag_id AND wok_init_id = :init_id AND wok_status = 1
             LIMIT 1"
        );
        $stmtCheck->execute([
            ':ag_id' => $agId,
            ':init_id' => $initId,
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
                       t1.req_solic_devprints_cc, t1x.fab_design_name, t1x.fab_mat_gramms,
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
                       t1.req_pie_imprenta, t1.req_despacho_desc,
                       to1.req_number AS reqnum_mezcla_1, to2.req_number AS reqnum_mezcla_2,
                       to3.req_number AS reqnum_mezcla_3, t1.req_rebo_cc_genrefid,
                        teq.equipo_name, teq.equipo_prod_isprinter_flexo, teq.equipo_prod_isprinter_seri,
                        teq.equipo_type_id, ttype.type_ant_title, ttype.type_createstock_act
                FROM prod_agenda t0
                INNER JOIN prod_header t3x ON t0.ag_prdid = t3x.id AND t3x.prd_status >= 2
                INNER JOIN orders t1 ON t0.ag_reqid = t1.id
                LEFT OUTER JOIN company_data t2 ON t1.req_company_id = t2.id
                LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id = t3.id
                LEFT OUTER JOIN customer t4 ON t1.req_cust_id = t4.id
                INNER JOIN orders_items t1x ON t1.id = t1x.req_id
                INNER JOIN item t2x ON t1x.item_id = t2x.id
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

        $stmt = $this->erpPdo->prepare($sql);
        $stmt->execute([':ag_id' => $agId]);
        $agendaData = $stmt->fetch();

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
        if ($workerOt !== null) {
            $stmtTot = $this->erpPdo->prepare(
                "SELECT SUM(evt_amount) AS tot_amt,
                        SUM(evt_amount_metros_lineales) AS tot_meters,
                        SUM(prod_bobina_kg) AS tot_kg
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
            }
        }

        $totProducedAll = 0.0;
        try {
            $stmtAll = $this->erpPdo->prepare(
                "SELECT SUM(t3.evt_amount) AS tot_all
                 FROM prod_agenda t1
                 INNER JOIN prod_worker_ot t2 ON t2.wok_ag_id = t1.id
                 INNER JOIN prod_worker_ot_events t3 ON t3.evt_prod_worker_otid = t2.id
                 WHERE t1.ag_prdid = :prdid
                   AND t3.evt_type = 'prod'
                   AND t3.evt_status > 0"
            );
            $stmtAll->execute([':prdid' => (int)($agendaData['prdid'] ?? 0)]);
            $allRow = $stmtAll->fetch();
            if (is_array($allRow)) {
                $totProducedAll = (float)($allRow['tot_all'] ?? 0);
            }
        } catch (Throwable) {
            $totProducedAll = $totProducedThis;
        }

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

        $bastidorAmt = max(1, (int)($agendaData['req_operador_bastidoramt'] ?? 1));
        $pasadasPrint = $bastidorAmt > 0 ? round($agAmount / $bastidorAmt) : 0;

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
        $isRebobinadora = strpos($typeAntTitle, 'REBOBIN') !== false;
        $isSelladora = strpos($typeAntTitle, 'SELLADORA') !== false;
        $isEmbalaje = !empty($openInit['type_createstock_act']);

        return [
            'agenda' => $agendaData,
            'machine_init' => $openInit,
            'worker_ot' => $workerOt,
            'transcom_infos' => $transcomInfos,
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
        if ($workerOtId <= 0) {
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
        array $repairs = []
    ): array {
        if ($workerOtId <= 0) {
            return ['ok' => false, 'error' => 'ID de OT inválido.'];
        }

        $now = time();
        $eventId = 0;

        if ($refId !== null && $refId > 0) {
            $eventId = $refId;
            $endDat = $isEnd ? $now : null;
            $sql = "UPDATE prod_worker_ot_events
                    SET evt_amount = :amt, evt_amount2 = :amt2, prod_bobina_kg = :kg,
                        evt_comments = :comments, evt_amount_metros_maquina = :mmaq,
                        evt_amount_metros_lineales = :mlin, evt_metrotype = :mtype,
                        overrideembalaje_act = :overr"
                    . ($isEnd ? ", evt_enddat = :enddat" : "")
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
                ':id' => $refId,
            ];
            if ($isEnd) {
                $params[':enddat'] = $endDat;
            }
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
        } else {
            $sql = "INSERT INTO prod_worker_ot_events
                    (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments,
                     prod_bobina_kg, evt_amount_metros_maquina, evt_amount_metros_lineales, evt_metrotype, evt_amount2,
                     overrideembalaje_act)
                    VALUES
                    (:ot_id, :amt, :crtdat, 1, 'prod', :comments,
                     :kg, :mmaq, :mlin, :mtype, :amt2, :overr)";

            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute([
                ':ot_id' => $workerOtId,
                ':amt' => $amount,
                ':crtdat' => $now,
                ':comments' => $comments,
                ':kg' => $bobinaKg,
                ':mmaq' => $metrosMaquina,
                ':mlin' => $metrosLineales,
                ':mtype' => $metroType,
                ':amt2' => $amount2,
                ':overr' => $overrideEmbalaje,
            ]);
            $eventId = (int)$this->erpPdo->lastInsertId();
        }

        if ($eventId > 0 && (!empty($mermas) || !empty($repairs))) {
            $this->saveEventDefectUnits($eventId, $mermas, $repairs);
        }

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
    public function listAvailableRollsForProduction(?string $filterSku = null, int $limit = 60): array
    {
        if ($this->trzPdo === null) {
            return [];
        }

        try {
            $where = ["r.status IN ('RECEIVED', 'IN_PROCESS')"];
            $params = [];

            if ($filterSku !== null && trim($filterSku) !== '') {
                $where[] = "(s.code LIKE :q OR s.description LIKE :q OR r.roll_code LIKE :q)";
                $params[':q'] = '%' . trim($filterSku) . '%';
            }

            $whereSql = implode(' AND ', $where);
            $sql = "SELECT r.id, r.roll_code, r.weight_kg, r.microns, r.width_mm, r.color, r.meters, r.status,
                           r.purchase_order_id, r.purchase_order_line_id, r.created_at,
                           s.code AS sku_code, s.description AS sku_desc,
                           w.code AS warehouse_code, w.name AS warehouse_name
                    FROM rolls r
                    INNER JOIN skus s ON r.sku_id = s.id
                    INNER JOIN warehouses w ON r.warehouse_id = w.id
                    WHERE {$whereSql}
                    ORDER BY r.id DESC
                    LIMIT :lim";

            $stmt = $this->trzPdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Asigna y consume una bobina física recepcionada en una Orden de Trabajo activa,
     * sincronizando el estado en la base TRZ y registrando el evento de material en el ERP remoto.
     */
    public function assignRollToWorkOrder(
        int $workerOtId,
        int $rollId,
        float $consumedWeight,
        string $comments = '',
        string $operatorName = ''
    ): array {
        if ($workerOtId <= 0 || $rollId <= 0) {
            return ['ok' => false, 'error' => 'Parámetros de asignación inválidos.'];
        }

        if ($this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Base de datos de trazabilidad no disponible.'];
        }

        try {
            // 1. Obtener la bobina desde TRZ
            $stmt = $this->trzPdo->prepare(
                "SELECT r.*, s.code AS sku_code, s.description AS sku_desc
                 FROM rolls r
                 INNER JOIN skus s ON r.sku_id = s.id
                 WHERE r.id = :id
                 LIMIT 1"
            );
            $stmt->execute([':id' => $rollId]);
            $roll = $stmt->fetch();
            if (!$roll) {
                return ['ok' => false, 'error' => 'La bobina indicada no existe en el sistema.'];
            }

            $rollCode = (string)$roll['roll_code'];
            $skuCode = (string)$roll['sku_code'];
            $weightKg = $consumedWeight > 0 ? $consumedWeight : (float)$roll['weight_kg'];
            $now = time();

            // 2. Actualizar estado de la bobina en TRZ (pasa a EN PROCESO vinculada a la OT)
            $updateRoll = $this->trzPdo->prepare(
                "UPDATE rolls
                 SET status = 'IN_PROCESS',
                     current_work_order_id = :ot_id
                 WHERE id = :id"
            );
            $updateRoll->execute([
                ':ot_id' => $workerOtId,
                ':id' => $rollId,
            ]);

            // 3. Registrar movimiento y evento en TRZ
            $stmtEvent = $this->trzPdo->prepare(
                "INSERT INTO events (type, payload) VALUES (:type, :payload)"
            );
            $stmtEvent->execute([
                ':type' => 'ROLL_LOADED_TO_OT',
                ':payload' => json_encode([
                    'roll_id' => $rollId,
                    'roll_code' => $rollCode,
                    'sku_code' => $skuCode,
                    'weight_kg' => $weightKg,
                    'worker_ot_id' => $workerOtId,
                    'operator' => $operatorName,
                    'comments' => $comments,
                    'timestamp' => $now,
                ], JSON_UNESCAPED_UNICODE),
            ]);

            // 4. Registrar evento de materiales en el ERP remoto (prod_worker_ot_events)
            $erpComment = "Bobina {$rollCode} [{$skuCode}] - " . ($weightKg > 0 ? "{$weightKg} kg" : "");
            if (trim($comments) !== '') {
                $erpComment .= " (" . trim($comments) . ")";
            }

            $stmtErp = $this->erpPdo->prepare(
                "INSERT INTO prod_worker_ot_events
                 (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments)
                 VALUES
                 (:ot_id, :amt, :crtdat, 1, 'materiales', :comments)"
            );
            $stmtErp->execute([
                ':ot_id' => $workerOtId,
                ':amt' => $weightKg,
                ':crtdat' => $now,
                ':comments' => $erpComment,
            ]);

            return [
                'ok' => true,
                'roll_code' => $rollCode,
                'weight_kg' => $weightKg,
                'event_id' => (int)$this->erpPdo->lastInsertId(),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al asignar bobina: ' . $e->getMessage()];
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

            // Generar código único de bobina hija
            $stmtMax = $this->trzPdo->query("SELECT MAX(id) FROM rolls");
            $nextNum = ((int)$stmtMax->fetchColumn()) + 1;
            $childRollCode = sprintf('ROLL-SE-%05d', $nextNum);

            // Insertar bobina en TRZ
            $now = time();
            $insRoll = $this->trzPdo->prepare(
                "INSERT INTO rolls
                 (roll_code, sku_id, warehouse_id, weight_kg, received_qty, meters, status, parent_roll_id, source_work_order_id, process_stage)
                 VALUES
                 (:code, :sku, :wh, :weight, 1.000, :meters, 'RECEIVED', :parent, :wo_id, 'PRINTED')"
            );
            $insRoll->execute([
                ':code' => $childRollCode,
                ':sku' => $skuId,
                ':wh' => $warehouseId,
                ':weight' => number_format($weightKg, 3, '.', ''),
                ':meters' => $meters > 0 ? $meters : null,
                ':parent' => $parentRollId > 0 ? $parentRollId : null,
                ':wo_id' => $workerOtId,
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
                 (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_status, evt_type, evt_comments)
                 VALUES
                 (:ot_id, :amt, :crtdat, 1, 'prod', :comments)"
            );
            $stmtErp->execute([
                ':ot_id' => $workerOtId,
                ':amt' => $weightKg,
                ':crtdat' => $now,
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
     * Genera cajas de producto terminado vinculadas a la OT activa en TRZ y ERP.
     */
    public function createOutputBoxesFromWorkOrder(
        int $workerOtId,
        string $skuFinal,
        int $boxQty,
        int $unitsPerBox,
        string $operatorName = ''
    ): array {
        if ($workerOtId <= 0 || $boxQty <= 0 || $unitsPerBox <= 0) {
            return ['ok' => false, 'error' => 'Datos de cajas inválidos.'];
        }

        if ($this->trzPdo === null) {
            return ['ok' => false, 'error' => 'Base de datos de trazabilidad no disponible.'];
        }

        try {
            $createdBoxes = [];
            $now = time();
            $totalUnits = $boxQty * $unitsPerBox;

            $insBox = $this->trzPdo->prepare(
                "INSERT INTO boxes
                 (box_code, work_order_id, source_roll_id, final_sku, units_qty, destination_mode, operator_name, status)
                 VALUES
                 (:code, :wo_id, 0, :sku, :units, 'STOCK', :op, 'CREATED')"
            );

            for ($i = 1; $i <= $boxQty; $i++) {
                $stmtMax = $this->trzPdo->query("SELECT MAX(id) FROM boxes");
                $nextNum = ((int)$stmtMax->fetchColumn()) + 1;
                $boxCode = sprintf('BOX-%06d', $nextNum);

                $insBox->execute([
                    ':code' => $boxCode,
                    ':wo_id' => $workerOtId,
                    ':sku' => trim($skuFinal) !== '' ? trim($skuFinal) : 'PRODUCTO-TERMINADO',
                    ':units' => $unitsPerBox,
                    ':op' => $operatorName,
                ]);

                $createdBoxes[] = $boxCode;
            }

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
                ':comments' => "{$boxQty} Cajas generadas (" . implode(', ', array_slice($createdBoxes, 0, 3)) . ($boxQty > 3 ? '...' : '') . ") - {$totalUnits} unidades totales",
            ]);

            return [
                'ok' => true,
                'box_count' => $boxQty,
                'total_units' => $totalUnits,
                'boxes' => $createdBoxes,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al registrar cajas: ' . $e->getMessage()];
        }
    }

    /**
     * Construye el árbol completo de trazabilidad "Inicio a Fin" a partir de cualquier código:
     * Código de Bobina (ROLL-*), Código de Caja (BOX-*), Número de OT o Número de OC.
     */
    public function getTraceabilityTree(string $queryCode): ?array
    {
        $queryCode = trim($queryCode);
        if ($queryCode === '' || $this->trzPdo === null) {
            return null;
        }

        $result = [
            'query' => $queryCode,
            'purchase_order' => null,
            'supplier' => null,
            'roll' => null,
            'work_order' => null,
            'events' => [],
            'child_rolls' => [],
            'boxes' => [],
        ];

        // 1. ¿Es una bobina?
        $stmtRoll = $this->trzPdo->prepare(
            "SELECT r.*, s.code AS sku_code, s.description AS sku_desc, w.name AS warehouse_name
             FROM rolls r
             INNER JOIN skus s ON r.sku_id = s.id
             INNER JOIN warehouses w ON r.warehouse_id = w.id
             WHERE r.roll_code = :q OR r.id = :id
             LIMIT 1"
        );
        $stmtRoll->execute([':q' => $queryCode, ':id' => is_numeric($queryCode) ? (int)$queryCode : 0]);
        $roll = $stmtRoll->fetch();

        if ($roll) {
            $result['roll'] = $roll;

            // Buscar datos de la OC en ERP si tiene purchase_order_id
            $poId = (int)($roll['purchase_order_id'] ?? 0);
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
                "SELECT r.*, s.code AS sku_code FROM rolls r INNER JOIN skus s ON r.sku_id = s.id WHERE r.parent_roll_id = :pid"
            );
            $stmtChildren->execute([':pid' => (int)$roll['id']]);
            $result['child_rolls'] = $stmtChildren->fetchAll();

            // Buscar OT vinculada
            $woId = (int)($roll['current_work_order_id'] ?? $roll['source_work_order_id'] ?? 0);
            if ($woId > 0) {
                $stmtOt = $this->erpPdo->prepare(
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
                     WHERE t1.id = :id
                     LIMIT 1"
                );
                $stmtOt->execute([':id' => $woId]);
                $otData = $stmtOt->fetch();
                if ($otData) {
                    $result['work_order'] = $otData;
                    // Eventos de la OT
                    $stmtEvts = $this->erpPdo->prepare(
                        "SELECT * FROM prod_worker_ot_events WHERE evt_prod_worker_otid = :otid AND evt_status > 0 ORDER BY id ASC"
                    );
                    $stmtEvts->execute([':otid' => $woId]);
                    $result['events'] = $stmtEvts->fetchAll();
                }

                // Cajas vinculadas
                $stmtBoxes = $this->trzPdo->prepare(
                    "SELECT * FROM boxes WHERE work_order_id = :woid ORDER BY id ASC"
                );
                $stmtBoxes->execute([':woid' => $woId]);
                $result['boxes'] = $stmtBoxes->fetchAll();
            }

            return $result;
        }

        // 2. ¿Es una caja?
        $stmtBox = $this->trzPdo->prepare("SELECT * FROM boxes WHERE box_code = :q LIMIT 1");
        $stmtBox->execute([':q' => $queryCode]);
        $box = $stmtBox->fetch();
        if ($box) {
            $woId = (int)($box['work_order_id'] ?? 0);
            if ($woId > 0) {
                // Enlazar a través de la OT
                $stmtOt = $this->erpPdo->prepare(
                    "SELECT t4.prd_number FROM prod_worker_ot t1
                     INNER JOIN prod_agenda t3 ON t1.wok_ag_id = t3.id
                     INNER JOIN prod_header t4 ON t3.ag_prdid = t4.id
                     WHERE t1.id = :id LIMIT 1"
                );
                $stmtOt->execute([':id' => $woId]);
                $prdNumber = $stmtOt->fetchColumn();
                if ($prdNumber) {
                    return $this->getTraceabilityTree((string)$prdNumber);
                }
            }
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
        return $stmt->execute([':id' => $eventId, ':ot_id' => $workerOtId]);
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
        $points = $this->listAutocontrolPoints($equipoTypeId);
        $hasPoints = count($points) > 0;

        if (!$hasPoints || $workerOtId <= 0) {
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
                 WHERE ctr_init_id = :ot_id AND ctr_type = 'supervisor'"
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
                "SELECT id, type_name FROM prod_pause_types WHERE type_status > 0 ORDER BY type_name ASC"
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

        return ['ok' => true];
    }

    // =========================================================================
    // SECCIÓN 8: MONITOREO EN TIEMPO REAL E HISTORIAL DE PLANTA
    // =========================================================================

    /**
     * Lista todas las OTs actualmente en ejecución en la planta (`wok_status = 1`).
     */
    public function listActiveOrdersInCourse(int $plantaId): array
    {
        $wherePlanta = $plantaId > 0 ? " AND t2.win_plantaid = :planta_id" : "";
        $sql = "SELECT t1.id AS worker_ot_id, t1.wok_crtdat, t1.wok_status,
                       t2.win_equipoid, t7.equipo_name, t8.type_ant_title,
                       t9.wrk_firstname, t9.wrk_lastname,
                       t0.id AS ag_id, t0.ag_amount,
                       t3x.prd_number,
                       t5.req_number, t4.cust_name, t2x.item_title, t2x.item_number_prod
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
                ORDER BY t1.wok_crtdat DESC";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            if ($plantaId > 0) {
                $stmt->execute([':planta_id' => $plantaId]);
            } else {
                $stmt->execute();
            }
            return $stmt->fetchAll();
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
                       t2.win_equipoid, t7.equipo_name, t8.type_ant_title,
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
}


