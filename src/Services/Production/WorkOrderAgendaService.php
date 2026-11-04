<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Agenda, Programación, Contexto Técnico e Historial de OTs
// (src/Services/Production/WorkOrderAgendaService.php)
// =============================================================================

trait WorkOrderAgendaServiceTrait
{
    // =========================================================================
    // SECCIÓN 1: GESTIÓN DE MÁQUINAS Y APERTURA DE TURNO (prod_worker_init)
    // =========================================================================


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

    /** Apertura operativa exacta, sin recurrir a sesiones u ordenes historicas.
     * Exact operational opening, without historical session or job fallbacks. */
    public function getOpenWorkerOtForInit(int $initId, int $agId): ?array
    {
        if ($initId<=0 || $agId<=0) return null;
        $stmt=$this->erpPdo->prepare("SELECT o.* FROM prod_worker_ot o
            JOIN prod_worker_init i ON i.id=o.wok_init_id
            WHERE o.wok_init_id=:init_id AND o.wok_ag_id=:ag_id
                AND o.wok_status=1 AND i.win_status=1
            ORDER BY o.id DESC LIMIT 1");
        $stmt->execute([':init_id'=>$initId, ':ag_id'=>$agId]);
        return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
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

        $orderBy = !empty($whereDate)
            ? "ORDER BY t0.ag_active DESC, t0.ag_order ASC, t0.id ASC"
            : "ORDER BY t0.ag_active DESC, t0.ag_date_stamp DESC, t0.ag_order ASC, t0.id DESC";

        $sql = "SELECT DISTINCT t0.id AS ag_id, t0.ag_date, t0.ag_date_stamp, t0.ag_amount, t0.ag_status, t0.ag_active,
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
                               (SELECT cv.add_name FROM tran_comments_vals cv WHERE cv.id = t1x.fab_mat_fabric_color LIMIT 1) AS fabric_color,
                               t1x.fab_med_fuelle, t1x.fab_design_imagehash, t1x.fab_design_name, t1x.item_img_hash,
                               t1x.fab_print_colordesc_1, t1x.fab_print_colordesc_2, t1x.fab_print_colordesc_3,
                               t1x.fab_print_colordesc_4, t1x.fab_print_colordesc_5, t1x.fab_print_colordesc_6,
                               teq.equipo_name, teqt.type_ant_title, teq.equipo_type_id, teq.equipo_prod_isprinter_flexo, teq.equipo_prod_isprinter_seri
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
                {$orderBy}
                LIMIT 50";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll();

            // Optimización de rendimiento: calcular total_produced en un solo query por lotes
            $agIds = array_filter(array_map('intval', array_column($orders, 'ag_id')));
            if (!empty($agIds)) {
                $placeholders = implode(',', $agIds);
                $stmtProd = $this->erpPdo->query("
                    SELECT t_ot.wok_ag_id, COALESCE(SUM(t_ev.evt_amount), 0) AS produced
                    FROM prod_worker_ot t_ot
                    INNER JOIN prod_worker_ot_events t_ev ON t_ev.evt_prod_worker_otid = t_ot.id
                    WHERE t_ot.wok_ag_id IN ($placeholders)
                      AND t_ev.evt_type = 'prod'
                      AND t_ev.evt_status > 0
                      AND (t_ev.evt_comments IS NULL OR (t_ev.evt_comments NOT LIKE '%Bobina Semielaborada%' AND t_ev.evt_comments NOT LIKE '%ROLL-SE-%'))
                    GROUP BY t_ot.wok_ag_id
                ");
                $prodMap = $stmtProd ? $stmtProd->fetchAll(PDO::FETCH_KEY_PAIR) : [];
                foreach ($orders as &$ord) {
                    $ord['total_produced'] = (float)($prodMap[(int)$ord['ag_id']] ?? 0);
                }
                unset($ord);
            } else {
                foreach ($orders as &$ord) {
                    $ord['total_produced'] = 0.0;
                }
                unset($ord);
            }

            $hasFlexo = false;
            $hasSeri = false;
            foreach ($orders as &$ord) {
                $isRebo = ((int)($ord['req_rebo_cc_genrefid'] ?? 0) > 0)
                    || ((int)($ord['equipo_type_id'] ?? 0) === 12)
                    || stripos((string)($ord['equipo_name'] ?? ''), 'REBO') !== false
                    || stripos((string)($ord['type_ant_title'] ?? ''), 'REBOBIN') !== false;
                if ($isRebo) {
                    $ord['rebo_distribucion'] = $this->getReboDistributionText((int)$ord['req_id'], (float)($ord['req_operador_tela_width'] ?? 0));
                }
                if (!$hasFlexo && (((int)($ord['equipo_type_id'] ?? 0) === 7) || ((int)($ord['equipo_prod_isprinter_flexo'] ?? 0) === 1))) {
                    $hasFlexo = true;
                }
                if (!$hasSeri && (((int)($ord['equipo_type_id'] ?? 0) === 11) || ((int)($ord['equipo_prod_isprinter_seri'] ?? 0) === 1))) {
                    $hasSeri = true;
                }
            }
            unset($ord);

            if ($hasFlexo) {
                $this->attachFlexoMetrics($orders, $equipoId);
            }
            if ($hasSeri) {
                $this->attachSeriMetrics($orders);
            }
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
                                   (SELECT cv.add_name FROM tran_comments_vals cv WHERE cv.id = t1x.fab_mat_fabric_color LIMIT 1) AS fabric_color,
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
     * @param int $prdId ID de prod_header
     * @param int $equipoId ID de máquina
     * @param int $plantaId ID de planta
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

        require_once dirname(__DIR__, 2) . '/SimpleXlsx.php';
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

        $agIds = [];
        $reqIds = [];
        foreach ($orders as $ord) {
            $isFlexo = ((int)($ord['equipo_type_id'] ?? 0) === 7)
                || ((int)($ord['equipo_prod_isprinter_flexo'] ?? 0) === 1)
                || stripos((string)($ord['equipo_name'] ?? ''), 'flexo') !== false
                || stripos((string)($ord['type_ant_title'] ?? ''), 'flexo') !== false;
            if ($isFlexo) {
                if (!empty($ord['ag_id'])) {
                    $agIds[] = (int)$ord['ag_id'];
                }
                if (!empty($ord['req_id'])) {
                    $reqIds[] = (int)$ord['req_id'];
                }
            }
        }
        $agIds = array_values(array_unique(array_filter($agIds)));
        $reqIds = array_values(array_unique(array_filter($reqIds)));

        $prodAgMap = [];
        if (!empty($agIds)) {
            try {
                $agPlaceholders = implode(',', $agIds);
                $stmt = $this->erpPdo->query("
                    SELECT t_ot.wok_ag_id,
                           COALESCE(SUM(t_ev.evt_amount), 0) AS units,
                           COALESCE(SUM(t_ev.evt_amount_metros_lineales), 0) AS meters
                    FROM prod_worker_ot t_ot
                    INNER JOIN prod_worker_ot_events t_ev ON t_ev.evt_prod_worker_otid = t_ot.id
                    WHERE t_ot.wok_ag_id IN ($agPlaceholders)
                      AND t_ev.evt_type = 'prod'
                      AND t_ev.evt_status > 0
                      AND (t_ev.evt_comments IS NULL OR (t_ev.evt_comments NOT LIKE '%Bobina Semielaborada%' AND t_ev.evt_comments NOT LIKE '%ROLL-SE-%'))
                    GROUP BY t_ot.wok_ag_id
                ");
                if ($stmt) {
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $prodAgMap[(int)$r['wok_ag_id']] = [
                            'units' => (float)$r['units'],
                            'meters' => (float)$r['meters'],
                        ];
                    }
                }
            } catch (Throwable) {}
        }

        $prodCcMap = [];
        if (!empty($reqIds)) {
            try {
                $reqPlaceholders = implode(',', $reqIds);
                $stmt = $this->erpPdo->query("
                    SELECT pa.ag_reqid,
                           COALESCE(SUM(t_ev.evt_amount), 0) AS units,
                           COALESCE(SUM(t_ev.evt_amount_metros_lineales), 0) AS meters
                    FROM prod_agenda pa
                    INNER JOIN prod_worker_ot t_ot ON t_ot.wok_ag_id = pa.id
                    INNER JOIN prod_worker_ot_events t_ev ON t_ev.evt_prod_worker_otid = t_ot.id
                    LEFT JOIN equipo eq ON pa.ag_equipo_id = eq.id
                    WHERE pa.ag_reqid IN ($reqPlaceholders)
                      AND t_ev.evt_type = 'prod'
                      AND t_ev.evt_status > 0
                      AND (eq.equipo_type_id = 7 OR eq.equipo_prod_isprinter_flexo = 1 OR eq.equipo_name LIKE '%flexo%' OR eq.equipo_name LIKE '%FLEXO%')
                      AND (t_ev.evt_comments IS NULL OR (t_ev.evt_comments NOT LIKE '%Bobina Semielaborada%' AND t_ev.evt_comments NOT LIKE '%ROLL-SE-%'))
                    GROUP BY pa.ag_reqid
                ");
                if ($stmt) {
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $prodCcMap[(int)$r['ag_reqid']] = [
                            'units' => (float)$r['units'],
                            'meters' => (float)$r['meters'],
                        ];
                    }
                }
            } catch (Throwable) {}
        }

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

            $agId = (int)($ord['ag_id'] ?? 0);
            $reqId = (int)($ord['req_id'] ?? 0);

            $agUnits = (float)($prodAgMap[$agId]['units'] ?? ($ord['total_produced'] ?? 0));
            $agMeters = (float)($prodAgMap[$agId]['meters'] ?? 0);
            if ($agMeters <= 0 && $agUnits > 0 && $corteM2 > 0) {
                $agMeters = round($agUnits * $corteM2);
            }
            if ($agUnits <= 0 && $agMeters > 0 && $corteM2 > 0) {
                $agUnits = round($agMeters / $corteM2);
            }

            $ccUnits = (float)($prodCcMap[$reqId]['units'] ?? $agUnits);
            $ccMeters = (float)($prodCcMap[$reqId]['meters'] ?? $agMeters);
            if ($ccMeters <= 0 && $ccUnits > 0 && $corteM2 > 0) {
                $ccMeters = round($ccUnits * $corteM2);
            }
            if ($ccUnits <= 0 && $ccMeters > 0 && $corteM2 > 0) {
                $ccUnits = round($ccMeters / $corteM2);
            }

            $ccUnits = max($ccUnits, $agUnits);
            $ccMeters = max($ccMeters, $agMeters);

            $ord['flexo_z'] = $corteZ;
            $ord['flexo_corte_m2'] = $corteM2;
            $ord['flexo_metros'] = $metrosAImprimir;
            $ord['total_produced'] = $agUnits;
            $ord['total_produced_meters'] = $agMeters;
            $ord['total_cc_units'] = $ccUnits;
            $ord['total_cc_meters'] = $ccMeters;
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
        $locks=[];
        try {
            if ($this->trzPdo!==null) {
                $locks=(new MachineRollContinuityService($this->erpPdo,$this->trzPdo))->lockMachineForInit($initId);
            }
            return $this->startWorkerOtUnlocked($agId,$initId);
        } catch (Throwable $e) {
            error_log('Work order opening: '.$e->getMessage());
            return ['ok'=>false,'error'=>'No se pudo abrir la OT. Vuelve a intentar cuando termine la operacion de la maquina.'];
        } finally {
            $this->unlockRollState($locks);
        }
    }

    private function startWorkerOtUnlocked(int $agId, int $initId): array
    {
        if ($agId <= 0 || $initId <= 0) {
            return ['ok' => false, 'error' => 'Agenda e inicio de máquina son requeridos.'];
        }

        // Reutilizar solo una OT abierta; una OT cerrada nunca sustituye la nueva apertura.
        // Only reuse an open job; a recently closed job must not replace a new opening.
        $stmtCheck = $this->erpPdo->prepare(
            "SELECT id FROM prod_worker_ot
             WHERE wok_ag_id = :ag_id AND wok_init_id = :init_id AND wok_status = 1
             ORDER BY id DESC LIMIT 1"
        );
        $stmtCheck->execute([
            ':ag_id' => $agId,
            ':init_id' => $initId,
        ]);
        $existing = $stmtCheck->fetch();
        if (is_array($existing) && (int)$existing['id'] > 0) {
            return $this->confirmWorkerOtRollContinuity((int)$existing['id']);
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

        return $this->confirmWorkerOtRollContinuity((int)$this->erpPdo->lastInsertId());
    }

    private function confirmWorkerOtRollContinuity(int $workerOtId): array
    {
        try {
            return ['ok'=>true,'worker_ot_id'=>$workerOtId,
                'continued_roll_ids'=>$this->continueMachineRollsForWorkOrder($workerOtId)];
        } catch (Throwable $e) {
            error_log('Work order roll continuity: '.$e->getMessage());
            return ['ok'=>false,'worker_ot_id'=>$workerOtId,
                'error'=>'La OT esta abierta, pero no se pudo confirmar la bobina cargada. Reintenta la apertura sin volver a cargar ni consumir material. '
                    .($e instanceof PDOException?'Solicita revision al administrador.':$e->getMessage())];
        }
    }

    public function continueMachineRollsForWorkOrder(int $workerOtId): array
    {
        if ($this->trzPdo===null) return [];
        return (new MachineRollContinuityService($this->erpPdo,$this->trzPdo))->continueForWorkOrder($workerOtId,
            fn(int $id,bool $create): int=>$this->resolveTraceabilityWorkOrderId($id,null,$create));
    }

    private function lockRollState(array $rollIds, int $workerOtId = 0): array
    {
        if ($this->trzPdo===null) return [];
        $continuity=new MachineRollContinuityService($this->erpPdo,$this->trzPdo);
        $locks=$continuity->lockRolls($rollIds);
        try {
            if ($workerOtId>0) {
                foreach (array_unique(array_map('intval',$rollIds)) as $rollId) {
                    if ($rollId>0) $continuity->assertRollJob($rollId,$workerOtId);
                }
            }
            return $locks;
        } catch (Throwable $e) {
            $continuity->unlock($locks);
            throw $e;
        }
    }

    private function unlockRollState(array $locks): void
    {
        if ($this->trzPdo!==null) (new MachineRollContinuityService($this->erpPdo,$this->trzPdo))->unlock($locks);
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

    private function resolveTraceabilityWorkOrderId(int $workerOtId, ?int $agId = null, bool $createIfMissing = false): int
    {
        if ($this->trzPdo === null || $workerOtId <= 0) {
            return 0;
        }

        // Resolver siempre desde la OT operativa: la ficha puede omitir el numero de produccion.
        // Always resolve from the worker job; the detailed agenda may omit the production number.
        $agendaData = null;
        if ($agendaData === null && $this->erpPdo !== null) {
            try {
                $stmt = $this->erpPdo->prepare("
                    SELECT pwo.id AS worker_ot_id, pa.id AS ag_id, pa.ag_amount, ph.prd_number, o.req_number,
                           i.item_number_prod, i.item_title
                    FROM prod_worker_ot pwo
                    LEFT JOIN prod_agenda pa ON pwo.wok_ag_id = pa.id
                    LEFT JOIN prod_header ph ON pa.ag_prdid = ph.id
                    LEFT JOIN orders o ON pa.ag_reqid = o.id
                    LEFT JOIN orders_items oi ON o.id = oi.req_id
                    LEFT JOIN item i ON oi.item_id = i.id
                    WHERE pwo.id = :worker_ot_id
                    LIMIT 1
                ");
                $stmt->execute([':worker_ot_id' => $workerOtId]);
                $agendaData = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Throwable) {
                $agendaData = null;
            }
        }

        $otCodeCandidates = [];
        foreach ([
            (string)($agendaData['prd_number'] ?? ''),
            (string)($agendaData['req_number'] ?? ''),
            'WOT-' . $workerOtId,
        ] as $candidate) {
            $candidate = strtoupper(trim($candidate));
            if ($candidate === '') {
                continue;
            }
            $candidate = substr($candidate, 0, 40);
            if (!in_array($candidate, $otCodeCandidates, true)) {
                $otCodeCandidates[] = $candidate;
            }
        }

        if ($otCodeCandidates === []) {
            return 0;
        }

        $skuFinal = trim((string)($agendaData['item_number_prod'] ?? $agendaData['item_title'] ?? 'OT-' . $workerOtId));
        if ($skuFinal === '') {
            $skuFinal = 'OT-' . $workerOtId;
        }
        $targetQty = null;
        if (isset($agendaData['ag_amount']) && (float)$agendaData['ag_amount'] > 0) {
            $targetQty = (int)round((float)$agendaData['ag_amount']);
        }

        try {
            foreach ($otCodeCandidates as $otCode) {
                $stmtFind = $this->trzPdo->prepare("SELECT id FROM work_orders WHERE ot_code = :ot_code LIMIT 1");
                $stmtFind->execute([':ot_code' => $otCode]);
                $existingId = (int)$stmtFind->fetchColumn();
                if ($existingId > 0) {
                    // Las consultas no deben reactivar OTs historicas.
                    // Read-only resolution must not reactivate historical jobs.
                    if (!$createIfMissing) {
                        return $existingId;
                    }
                    $stmtUpdate = $this->trzPdo->prepare("
                        UPDATE work_orders
                        SET sku_final = :sku_final,
                            target_qty = :target_qty,
                            status = 'ACTIVE'
                        WHERE id = :id
                    ");
                    $stmtUpdate->execute([
                        ':id' => $existingId,
                        ':sku_final' => substr($skuFinal, 0, 80),
                        ':target_qty' => $targetQty,
                    ]);
                    return $existingId;
                }
            }

            if (!$createIfMissing) {
                return 0;
            }

            $primaryOtCode = $otCodeCandidates[0];
            $stmtInsert = $this->trzPdo->prepare("
                INSERT INTO work_orders (ot_code, sku_final, target_qty, status)
                VALUES (:ot_code, :sku_final, :target_qty, 'ACTIVE')
            ");
            $stmtInsert->execute([
                ':ot_code' => $primaryOtCode,
                ':sku_final' => substr($skuFinal, 0, 80),
                ':target_qty' => $targetQty,
            ]);
            return (int)$this->trzPdo->lastInsertId();
        } catch (Throwable) {
            return 0;
        }
    }

    private function resolveBodega3000WarehouseId(): int
    {
        if ($this->trzPdo === null) {
            return 0;
        }

        try {
            $this->trzPdo->prepare("
                INSERT INTO warehouses (code, name, erp_storehouse_id)
                VALUES (3000, '3000 INSUMOS EN PRODUCCION', 3000)
                ON DUPLICATE KEY UPDATE name = VALUES(name)
            ")->execute();
            $stmt = $this->trzPdo->prepare("SELECT id FROM warehouses WHERE code IN (3000, 16, 27) ORDER BY FIELD(code, 3000, 16, 27), id ASC LIMIT 1");
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function resolveBodega3000InsumosWarehouseId(): int
    {
        if ($this->trzPdo === null) {
            return 0;
        }

        try {
            $this->trzPdo->prepare("
                INSERT INTO warehouses (code, name, erp_storehouse_id)
                VALUES (3000, '3000 INSUMOS EN PRODUCCION', 16)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    erp_storehouse_id = VALUES(erp_storehouse_id)
            ")->execute();

            $stmt = $this->trzPdo->prepare("
                SELECT id
                FROM warehouses
                WHERE erp_storehouse_id = 16
                   OR UPPER(name) LIKE '%INSUMOS%'
                   OR (code = 3000 AND UPPER(name) LIKE '%3000%')
                ORDER BY
                    CASE
                        WHEN erp_storehouse_id = 16 THEN 0
                        WHEN UPPER(name) LIKE '%INSUMOS%' THEN 1
                        WHEN code = 3000 THEN 2
                        ELSE 3
                    END,
                    id ASC
                LIMIT 1
            ");
            $stmt->execute();

            return (int)$stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
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
        $workerOt = $openInit !== null
            ? $this->getOpenWorkerOtForInit((int)$openInit['id'], $agId)
            : $this->getOpenWorkerOtByAgId($workerId, $plantaId, $agId);

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
            return null;
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
                $targetEqId = (int)$openInit['win_equipoid'];
                $isPrintMachine = !empty($openInit['equipo_prod_isprinter_flexo']) || !empty($openInit['equipo_prod_isprinter_seri']) || in_array((int)($openInit['equipo_type_id'] ?? 0), [7, 11], true);
                if (!$isPrintMachine && $this->erpPdo !== null) {
                    $reqId = (int)($agendaData['ag_reqid'] ?? 0);
                    if ($reqId > 0) {
                        try {
                            $stmtPrn = $this->erpPdo->prepare("
                                SELECT t3.win_equipoid
                                FROM prod_worker_ot t1
                                INNER JOIN prod_agenda t2 ON t1.wok_ag_id = t2.id
                                INNER JOIN prod_worker_init t3 ON t1.wok_init_id = t3.id
                                INNER JOIN equipo t7 ON t3.win_equipoid = t7.id
                                WHERE t2.ag_reqid = :reqid AND t7.equipo_type_id IN (7, 11)
                                ORDER BY t1.id DESC LIMIT 1
                            ");
                            $stmtPrn->execute([':reqid' => $reqId]);
                            $foundPrn = (int)$stmtPrn->fetchColumn();
                            if ($foundPrn > 0) {
                                $targetEqId = $foundPrn;
                            } else {
                                $stmtPrnAg = $this->erpPdo->prepare("
                                    SELECT pa.ag_equipo_id
                                    FROM prod_agenda pa
                                    INNER JOIN equipo eq ON pa.ag_equipo_id = eq.id
                                    WHERE pa.ag_reqid = :reqid AND eq.equipo_type_id IN (7, 11)
                                    ORDER BY pa.id DESC LIMIT 1
                                ");
                                $stmtPrnAg->execute([':reqid' => $reqId]);
                                $foundPrnAg = (int)$stmtPrnAg->fetchColumn();
                                if ($foundPrnAg > 0) {
                                    $targetEqId = $foundPrnAg;
                                }
                            }
                        } catch (Throwable) {}
                    }
                    if ($targetEqId <= 0 || $targetEqId === (int)$openInit['win_equipoid']) {
                        $targetEqId = (($agendaData['fab_printtype'] ?? '') === 'SERI') ? 14 : 33;
                    }
                }

                $stmtEq = $this->erpPdo->prepare(
                    "SELECT * FROM equipo_params
                     WHERE param_equipo_id = :eq_id
                       AND param_medida >= :medida
                     ORDER BY param_medida ASC
                     LIMIT 1"
                );
                $stmtEq->execute([
                    ':eq_id' => $targetEqId,
                    ':medida' => $paramMedida,
                ]);
                $equipoParams = $stmtEq->fetch(PDO::FETCH_ASSOC);
                if (!$equipoParams && $targetEqId !== 33 && $targetEqId !== 13) {
                    $stmtEq->execute([
                        ':eq_id' => 33,
                        ':medida' => $paramMedida,
                    ]);
                    $equipoParams = $stmtEq->fetch(PDO::FETCH_ASSOC);
                }

                if (is_array($equipoParams)) {
                    $polType = (string)($agendaData['req_solic_devprints_poltype'] ?? '');
                    $isSeriPrint = (($agendaData['fab_printtype'] ?? '') === 'SERI') || (int)($equipoParams['equipo_prod_isprinter_seri'] ?? 0) === 1;
                    if ($isSeriPrint) {
                        $corteM2 = (float)($equipoParams['param_corte'] ?? 0);
                    } elseif ($polType === 'pol284' && !empty($equipoParams['param_poly28']) && (float)$equipoParams['param_poly28'] > 0) {
                        $corteM2 = (float)$equipoParams['param_poly28'];
                    } elseif ($polType === 'pol170' && !empty($equipoParams['param_poly17']) && (float)$equipoParams['param_poly17'] > 0) {
                        $corteM2 = (float)$equipoParams['param_poly17'];
                    } else {
                        $corteM2 = (float)(!empty($equipoParams['param_poly28']) ? $equipoParams['param_poly28'] : ($equipoParams['param_corte'] ?? 0));
                    }
                    $corteZ = (int)($equipoParams['param_z'] ?? 0);
                }
            } catch (Throwable) {
                // Ignore missing params
            }
        }
        if ($corteM2 <= 0.0) {
            $corteM2 = round(((float)($agendaData['fab_med_height'] ?? 0) + (float)($agendaData['fab_med_fuelle'] ?? 0)) / 100, 4);
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
        $statsPackaging = !empty($openInit['type_createstock_act']) ? 1 : 0;
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
                   AND overrideembalaje_act = :phase
                   AND evt_status > 0
                   AND (evt_comments IS NULL OR (evt_comments NOT LIKE '%Bobina Semielaborada%' AND evt_comments NOT LIKE '%ROLL-SE-%'))"
            );
            $stmtTot->execute([':ot_id' => (int)$workerOt['id'], ':phase' => $statsPackaging]);
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
        $totMetersAll = 0.0;
        $totPulpoFrenteAgenda = $totPulpoFrenteThis;
        $totPulpoDorsoAgenda = $totPulpoDorsoThis;
        try {
            $stmtAgTot = $this->erpPdo->prepare(
                "SELECT SUM(t3.evt_amount) AS tot_all,
                        SUM(t3.evt_amount_metros_lineales) AS tot_meters,
                        SUM(t3.evt_amount_pulpo_frente) AS tot_frente,
                        SUM(t3.evt_amount_pulpo_dorso) AS tot_dorso
                 FROM prod_worker_ot t2
                 INNER JOIN prod_worker_ot_events t3 ON t3.evt_prod_worker_otid = t2.id
                 WHERE t2.wok_ag_id = :ag_id
                   AND t3.evt_type = 'prod'
                   AND t3.overrideembalaje_act = :phase
                   AND t3.evt_status > 0
                   AND (t3.evt_comments IS NULL OR (t3.evt_comments NOT LIKE '%Bobina Semielaborada%' AND t3.evt_comments NOT LIKE '%ROLL-SE-%'))"
            );
            $stmtAgTot->execute([':ag_id' => $agId, ':phase' => $statsPackaging]);
            $agRow = $stmtAgTot->fetch();
            if (is_array($agRow)) {
                $totProducedAll = (float)($agRow['tot_all'] ?? 0);
                $totMetersAll = (float)($agRow['tot_meters'] ?? 0);
                $totPulpoFrenteAgenda = (float)($agRow['tot_frente'] ?? 0);
                $totPulpoDorsoAgenda = (float)($agRow['tot_dorso'] ?? 0);
            }
        } catch (Throwable) {
            $totProducedAll = $totProducedThis;
            $totMetersAll = $totMetersThis;
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

        $targetAmountThis = $agAmount;
        $orderAmountDisplay = (float)($agendaData['item_amount'] ?? $agAmount);
        $displayProducedThis = $totProducedThis;
        $displayProducedAll = $totProducedAll;

        $orderBagAmount = (float)($agendaData['item_amount'] ?? $agAmount);
        $orderMlinProg = round($orderBagAmount * $corteM2);
        $orderMlinAddi = round(($orderMlinProg / 100) * $mermaPerc);
        $orderMetersTarget = round($orderMlinProg + $orderMlinAddi);
        if ($orderMetersTarget <= 0) {
            $orderMetersTarget = $metrosAImprimir;
        }

        $saldoTotal = max(0.0, $orderAmountDisplay - $displayProducedAll);
        $percentCompletedThis = $targetAmountThis > 0
            ? round(($displayProducedThis / $targetAmountThis) * 100, 2)
            : 0;
        $percentCompletedAll = $orderAmountDisplay > 0
            ? round(($displayProducedAll / $orderAmountDisplay) * 100, 2)
            : 0;

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
                'total_produced_this' => $displayProducedThis,
                'total_produced_all' => $displayProducedAll,
                'total_meters' => $totMetersThis,
                'total_kilos' => $totKilosThis,
                'target_amount' => $targetAmountThis,
                'order_amount' => $orderAmountDisplay,
                'percent_completed_this' => $percentCompletedThis,
                'percent_completed_all' => $percentCompletedAll,
                'saldo_total' => $saldoTotal,
                'total_pulpo_frente_agenda' => $totPulpoFrenteAgenda,
                'total_pulpo_dorso_agenda' => $totPulpoDorsoAgenda,
                'total_pulpo_frente_this' => $totPulpoFrenteThis,
                'total_pulpo_dorso_this' => $totPulpoDorsoThis,
                'surplus_frente' => $surplusFrente,
                'surplus_dorso' => $surplusDorso,
                'total_meters_all' => $totMetersAll,
            ],
        ];
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

    /**
     * Motivos de mantención: tabla `equipo_manttype` del ERP (la misma del sistema anterior),
     * solo los activos y ordenados por nombre.
     *
     * @return array<int,array{id:int,mant_title:string,mant_code:string}>
     */
    public function listMaintenanceTypes(): array
    {
        try {
            return $this->erpPdo->query(
                "SELECT id, mant_title, mant_code FROM equipo_manttype WHERE mant_status > 0 ORDER BY mant_title ASC"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('listMaintenanceTypes: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Ubicaciones/partes de la máquina donde se hace la mantención (`equipos_ubimants`),
     * filtradas por el tipo de máquina como en el sistema anterior.
     *
     * @return array<int,array{id:int,ubim_title:string}>
     */
    public function listMaintenanceLocations(int $equipoTypeId): array
    {
        if ($equipoTypeId <= 0) {
            return [];
        }
        try {
            $stmt = $this->erpPdo->prepare(
                "SELECT t1.id, t1.ubim_title FROM equipos_ubimants t1
                 INNER JOIN equipos_ubimants_types t2 ON t1.id = t2.ubimid
                 WHERE t1.ubim_status > 0 AND t2.typeid = :type
                 GROUP BY t1.id, t1.ubim_title ORDER BY t1.ubim_title ASC"
            );
            $stmt->execute([':type' => $equipoTypeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('listMaintenanceLocations: ' . $e->getMessage());
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
                       (SELECT cv.add_name FROM tran_comments_vals cv WHERE cv.id = t1x.fab_mat_fabric_color LIMIT 1) AS fabric_color,
                       t1x.fab_printtype, t1x.fab_design_imagehash, t1x.fab_design_name, t1x.item_img_hash,
                       t1x.fab_print_colordesc_1, t1x.fab_print_colordesc_2, t1x.fab_print_colordesc_3,
                       t1x.fab_print_colordesc_4, t1x.fab_print_colordesc_5, t1x.fab_print_colordesc_6,
                       (SELECT COALESCE(SUM(evt_amount), 0) FROM prod_worker_ot_events WHERE evt_prod_worker_otid = t1.id AND evt_type = 'prod' AND evt_status > 0 AND (evt_comments IS NULL OR (evt_comments NOT LIKE '%Bobina Semielaborada%' AND evt_comments NOT LIKE '%ROLL-SE-%'))) AS total_produced
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
                       (SELECT SUM(evt_amount) FROM prod_worker_ot_events WHERE evt_prod_worker_otid = t1.id AND evt_type = 'prod' AND evt_status > 0 AND (evt_comments IS NULL OR (evt_comments NOT LIKE '%Bobina Semielaborada%' AND evt_comments NOT LIKE '%ROLL-SE-%'))) AS total_produced
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

    public function attachWorkOrderFabricationDetails(array $orders): array
    {
        $requestIds = array_values(array_unique(array_filter(array_map('intval', array_column($orders, 'req_id')))));
        if ($requestIds === []) return $orders;
        // One batch lookup uses the same product attributes as the machine panel.
        // Una consulta por lote utiliza los mismos atributos del panel de maquina.
        $stmt = $this->erpPdo->prepare("
            SELECT oi.req_id, i.item_number_prod, oi.fab_mat_gramms, oi.fab_manilla_length,
                   oi.fab_design_imagehash, oi.fab_design_name,
                   o.req_embalaje_medidas_caja, o.req_embalaje_bolsas_por_caja_amt,
                   fabric.add_name AS fabric_color, handle.add_name AS manilla_color,
                   (SELECT v.add_name FROM tran_comments_item_vals c JOIN tran_comments_vals v ON v.id = c.val_id WHERE c.item_id = i.id AND c.com_id = 20 ORDER BY c.val_id LIMIT 1) AS bag_type,
                   (SELECT v.add_name FROM tran_comments_item_vals c JOIN tran_comments_vals v ON v.id = c.val_id WHERE c.item_id = i.id AND c.com_id = 31 ORDER BY c.val_id LIMIT 1) AS ancho,
                   (SELECT v.add_name FROM tran_comments_item_vals c JOIN tran_comments_vals v ON v.id = c.val_id WHERE c.item_id = i.id AND c.com_id = 39 ORDER BY c.val_id LIMIT 1) AS alto_tiro,
                   (SELECT v.add_name FROM tran_comments_item_vals c JOIN tran_comments_vals v ON v.id = c.val_id WHERE c.item_id = i.id AND c.com_id = 40 ORDER BY c.val_id LIMIT 1) AS alto_retiro,
                   (SELECT v.add_name FROM tran_comments_item_vals c JOIN tran_comments_vals v ON v.id = c.val_id WHERE c.item_id = i.id AND c.com_id = 41 ORDER BY c.val_id LIMIT 1) AS doblez_superior,
                   (SELECT v.add_name FROM tran_comments_item_vals c JOIN tran_comments_vals v ON v.id = c.val_id WHERE c.item_id = i.id AND c.com_id = 49 ORDER BY c.val_id LIMIT 1) AS ancho_manillas
            FROM orders_items oi
            JOIN orders o ON o.id = oi.req_id
            JOIN item i ON i.id = oi.item_id
            LEFT JOIN tran_comments_vals fabric ON fabric.id = oi.fab_mat_fabric_color
            LEFT JOIN tran_comments_vals handle ON handle.id = oi.fab_mat_manilla_color
            WHERE oi.req_id IN (" . implode(',', array_fill(0, count($requestIds), '?')) . ")
            ORDER BY oi.req_id, oi.item_pos
        ");
        $stmt->execute($requestIds);
        $details = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $detail) {
            $key = (int)$detail['req_id'] . '|' . (string)$detail['item_number_prod'];
            if (!isset($details[$key])) $details[$key] = $detail;
        }
        foreach ($orders as &$order) {
            $key = (int)($order['req_id'] ?? 0) . '|' . (string)($order['item_number_prod'] ?? '');
            $order = array_replace($order, $details[$key] ?? []);
        }
        unset($order);
        return $orders;
    }

    public function listOperatorEventHistory(int $workerId, int $plantaId, int $limit = 100): array
    {
        if ($workerId <= 0) {
            return [];
        }
        // Plant zero means no plant selected; worker isolation remains mandatory.
        // Planta cero significa sin seleccion; el filtro personal es obligatorio.
        $wherePlant = $plantaId > 0 ? 'AND wi.win_plantaid = :plant' : '';
        $stmt = $this->erpPdo->prepare("
            SELECT e.*, a.id AS ag_id, p.prd_number, o.req_number, c.cust_name,
                   eq.equipo_name, i.item_number_prod, i.item_title,
                   COALESCE(pt.pause_name, mt.mant_title, '') AS cause_title
            FROM prod_worker_ot_events e
            JOIN prod_worker_ot ot ON ot.id = e.evt_prod_worker_otid
            JOIN prod_worker_init wi ON wi.id = ot.wok_init_id
            JOIN prod_agenda a ON a.id = ot.wok_ag_id
            JOIN prod_header p ON p.id = a.ag_prdid
            JOIN orders o ON o.id = a.ag_reqid
            LEFT JOIN customer c ON c.id = o.req_cust_id
            LEFT JOIN equipo eq ON eq.id = wi.win_equipoid
            LEFT JOIN item i ON i.id = (
                SELECT oi.item_id FROM orders_items oi
                WHERE oi.req_id = o.id ORDER BY oi.item_pos, oi.item_id LIMIT 1
            )
            LEFT JOIN prod_pause_types pt ON pt.id = e.evt_pause_id
            LEFT JOIN equipo_manttype mt ON mt.id = e.evt_equipo_mantid
            WHERE wi.win_wrkid = :worker {$wherePlant}
              AND e.evt_status > 0 AND ot.wok_status > 0
            ORDER BY e.evt_crtdat DESC, e.id DESC LIMIT :lim
        ");
        $stmt->bindValue(':worker', $workerId, PDO::PARAM_INT);
        if ($plantaId > 0) {
            $stmt->bindValue(':plant', $plantaId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':lim', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows !== []) {
            $ids = array_map('intval', array_column($rows, 'id'));
            $defects = $this->erpPdo->query("
                SELECT d.*, COALESCE(m.merma_title, r.repair_title, 'No conformidad') AS cause_title
                FROM prod_worker_ot_defectunits d
                LEFT JOIN prod_mermatypes m ON m.id = d.evt_merma_typeid
                LEFT JOIN prod_repairtypes r ON r.id = d.evt_repair_typeid
                WHERE d.evt_status > 0 AND d.evt_refid IN (" . implode(',', $ids) . ")
                ORDER BY d.id
            ")->fetchAll(PDO::FETCH_ASSOC);
            $byEvent = [];
            foreach ($defects as $defect) {
                $byEvent[(int)$defect['evt_refid']][] = $defect;
            }
            foreach ($rows as &$row) {
                $row['defects'] = $byEvent[(int)$row['id']] ?? [];
            }
            unset($row);
        }
        return $rows;
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


}

#[AllowDynamicProperties]
class WorkOrderAgendaService
{
    use WorkOrderAgendaServiceTrait;

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
