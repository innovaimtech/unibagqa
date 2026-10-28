<?php

declare(strict_types=1);

/**
 * Service for end-to-end order traceability and bottleneck tracking.
 * Connects commercial orders, cliché procurement, pre-press checklists,
 * machine scheduling (OTs & agendas), production execution, and final dispatch.
 */
class TraceabilityService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Compute current stage, blocker/bottleneck ("¿Qué está esperando?"),
     * and progression percentages for a given order record.
     */
    public function computeStageAndBlocker(array $row): array
    {
        $printType = strtoupper(trim((string)($row['print_types'] ?? $row['fab_printtype'] ?? '')));
        $needsCliche = !in_array($printType, ['', 'SIN', 'SIN IMPRESION', 'SIN IMPRESIÓN'], true);

        $totalAmount = (float)($row['total_order_amount'] ?? $row['item_amount'] ?? 0);
        $produced = (float)($row['produced_units'] ?? 0);
        $shipped = (float)($row['shipped_units'] ?? 0);
        $reqStatus = (int)($row['req_status'] ?? 2);

        $clicheSolicDat = (int)($row['req_cliche_peli_solic_dat'] ?? 0);
        $clicheRecepDat = (int)($row['req_cliche_peli_recep_dat'] ?? 0);
        $clicheRepeatAct = (int)($row['req_cliche_peli_solic_repeat_act'] ?? 0);
        $checklistTerm = (int)($row['req_prod_checklist_term'] ?? 0);

        $hasOt = !empty($row['prod_header_id']) || !empty($row['ot_number']);
        $agendaCount = (int)($row['agenda_count'] ?? 0);
        $nextAgendaDate = trim((string)($row['next_agenda_date'] ?? ''));

        $stageKey = '';
        $stageLabel = '';
        $stageBadgeClass = '';
        $waitingFor = '';
        $isBlocked = false;

        if ($reqStatus === 1) {
            $stageKey = 'borrador';
            $stageLabel = 'Borrador Comercial';
            $stageBadgeClass = 'badge-secondary';
            $waitingFor = 'Esperando confirmación comercial de la Nota de Venta.';
            $isBlocked = true;
        } elseif ($needsCliche && $clicheRepeatAct === 0 && $clicheSolicDat === 0) {
            $stageKey = 'clises_espera';
            $stageLabel = 'Clisés / Fotopolímeros';
            $stageBadgeClass = 'badge-danger';
            $waitingFor = 'Esperando solicitud de compra de clisé al proveedor.';
            $isBlocked = true;
        } elseif ($needsCliche && $clicheRepeatAct === 0 && $clicheSolicDat > 0 && $clicheRecepDat === 0) {
            $stageKey = 'clises_espera';
            $stageLabel = 'Clisés / Fotopolímeros';
            $stageBadgeClass = 'badge-warning';
            $solicDate = date('d/m/Y', $clicheSolicDat);
            $waitingFor = "Esperando recepción de clisés desde proveedor (Solicitado el: {$solicDate}).";
            $isBlocked = true;
        } elseif ($checklistTerm === 0) {
            $stageKey = 'preparacion_espera';
            $stageLabel = 'Pre-Prensa';
            $stageBadgeClass = 'badge-warning';
            $waitingFor = 'Esperando validación de checklist técnico de Pre-prensa / Preparación.';
            $isBlocked = true;
        } elseif (!$hasOt) {
            $stageKey = 'planificacion_espera';
            $stageLabel = 'OT Pendiente';
            $stageBadgeClass = 'badge-info';
            $waitingFor = 'Esperando generación de Orden de Trabajo (OT) para planta.';
            $isBlocked = true;
        } elseif ($agendaCount === 0) {
            $stageKey = 'planificacion_espera';
            $stageLabel = 'Planificación Máquinas';
            $stageBadgeClass = 'badge-info';
            $waitingFor = 'Esperando programación de máquinas (Sin agenda asignada).';
            $isBlocked = true;
        } elseif ($produced <= 0) {
            $stageKey = 'produccion';
            $stageLabel = 'En Espera Turno';
            $stageBadgeClass = 'badge-primary';
            $dateTxt = $nextAgendaDate !== '' ? " para el {$nextAgendaDate}" : '';
            $waitingFor = "Esperando inicio de fabricación en máquina (Agendado{$dateTxt}).";
        } elseif ($produced < ($totalAmount * 0.95)) {
            $stageKey = 'produccion';
            $stageLabel = 'En Producción';
            $stageBadgeClass = 'badge-primary';
            $pct = $totalAmount > 0 ? round(($produced / $totalAmount) * 100, 1) : 0;
            $waitingFor = "En proceso de fabricación en planta ({$pct}% completado: " . number_format($produced, 0, ',', '.') . " / " . number_format($totalAmount, 0, ',', '.') . " un.).";
        } elseif ($shipped < ($totalAmount * 0.95) && $reqStatus !== 4) {
            $stageKey = 'despacho_pendiente';
            $stageLabel = 'Pendiente Despacho';
            $stageBadgeClass = 'badge-amber';
            $saldo = max(0, $totalAmount - $shipped);
            $waitingFor = "Producción lista (" . number_format($produced, 0, ',', '.') . " un.). Esperando emisión de guía y despacho al cliente (Saldo por despachar: " . number_format($saldo, 0, ',', '.') . " un.).";
            $isBlocked = true;
        } else {
            $stageKey = 'entregado';
            $stageLabel = 'Entregado / Cerrado';
            $stageBadgeClass = 'badge-success';
            $waitingFor = 'Pedido completado y entregado al cliente.';
        }

        $prodPct = $totalAmount > 0 ? min(100.0, round(($produced / $totalAmount) * 100, 1)) : 0.0;
        $shipPct = $totalAmount > 0 ? min(100.0, round(($shipped / $totalAmount) * 100, 1)) : 0.0;
        $balance = max(0.0, $totalAmount - $shipped);

        return [
            'stage_key' => $stageKey,
            'stage_label' => $stageLabel,
            'stage_badge_class' => $stageBadgeClass,
            'waiting_for' => $waitingFor,
            'is_blocked' => $isBlocked,
            'prod_pct' => $prodPct,
            'ship_pct' => $shipPct,
            'balance_to_ship' => $balance,
            'needs_cliche' => $needsCliche,
        ];
    }

    /**
     * Get aggregate statistics for KPI cards.
     */
    public function getStats(array $filters = []): array
    {
        try {
            $totalActive = (int)$this->pdo->query("SELECT COUNT(*) FROM orders WHERE req_status IN (2, 3)")->fetchColumn();
            
            $clisesEspera = (int)$this->pdo->query("
                SELECT COUNT(*) FROM orders 
                WHERE req_status IN (2, 3) 
                  AND req_cliche_peli_solic_repeat_act = 0 
                  AND (req_cliche_peli_solic_dat = 0 OR (req_cliche_peli_solic_dat > 0 AND req_cliche_peli_recep_dat = 0))
            ")->fetchColumn();

            $preparacionEspera = (int)$this->pdo->query("
                SELECT COUNT(*) FROM orders 
                WHERE req_status IN (2, 3) AND req_prod_checklist_term = 0
            ")->fetchColumn();

            $planificacionEspera = (int)$this->pdo->query("
                SELECT COUNT(*) FROM orders o 
                LEFT JOIN prod_header ph ON ph.prd_reqid = o.id 
                WHERE o.req_status IN (2, 3) 
                  AND (ph.id IS NULL OR NOT EXISTS (SELECT 1 FROM prod_agenda pa WHERE pa.ag_prdid = ph.id))
            ")->fetchColumn();

            $produccion = (int)$this->pdo->query("
                SELECT COUNT(DISTINCT o.id) FROM orders o 
                INNER JOIN prod_header ph ON ph.prd_reqid = o.id 
                INNER JOIN prod_agenda pa ON pa.ag_prdid = ph.id 
                WHERE o.req_status IN (2, 3)
            ")->fetchColumn();

            $despachoPendiente = (int)$this->pdo->query("
                SELECT COUNT(*) FROM orders WHERE req_status = 3
            ")->fetchColumn();

            return [
                'total_active' => $totalActive,
                'clises_espera' => $clisesEspera,
                'preparacion_espera' => $preparacionEspera,
                'planificacion_espera' => $planificacionEspera,
                'produccion' => $produccion,
                'despacho_pendiente' => $despachoPendiente,
            ];
        } catch (Throwable $e) {
            error_log("TraceabilityService::getStats error: " . $e->getMessage());
            return [
                'total_active' => 0,
                'clises_espera' => 0,
                'preparacion_espera' => 0,
                'planificacion_espera' => 0,
                'produccion' => 0,
                'despacho_pendiente' => 0,
            ];
        }
    }

    /**
     * Search orders with filters and pagination.
     * Uses optimized two-phase execution (late row lookup) for high performance.
     */
    public function searchOrders(array $filters, int $page = 1, int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $params = [];
        $where = [];

        // Base status filter
        $stageFilter = trim((string)($filters['stage'] ?? 'all'));
        if ($stageFilter === 'entregado') {
            $where[] = "o.req_status = 4";
        } else {
            $hasSearchTerm = !empty($filters['q']) || !empty($filters['oc']) || !empty($filters['cc']) || !empty($filters['ot']);
            if ($hasSearchTerm) {
                $where[] = "(o.req_status IN (2, 3) OR (o.req_status = 4 AND o.req_crtdat > UNIX_TIMESTAMP(NOW() - INTERVAL 180 DAY)))";
            } else {
                $where[] = "o.req_status IN (2, 3)";
            }
        }

        // Global search query
        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $wild = '%' . $q . '%';
            $where[] = "(
                o.req_oc_number LIKE :q_oc
                OR o.req_number LIKE :q_nv
                OR o.req_infoaddprd_cliche_codigo LIKE :q_cliche
                OR o.req_solic_supp_seudonimo LIKE :q_seud
                OR EXISTS (SELECT 1 FROM customer c WHERE c.id = o.req_cust_id AND (c.cust_name LIKE :q_cust OR c.cust_rut LIKE :q_rut))
                OR EXISTS (SELECT 1 FROM prod_header ph WHERE ph.prd_reqid = o.id AND ph.prd_number LIKE :q_ot)
                OR EXISTS (
                    SELECT 1 FROM orders_items oi 
                    LEFT JOIN item i ON oi.item_id = i.id 
                    WHERE oi.req_id = o.id 
                      AND (i.item_title LIKE :q_title OR i.item_number_prod LIKE :q_sku OR oi.fab_printtype LIKE :q_ptype)
                )
            )";
            $params[':q_oc'] = $wild;
            $params[':q_nv'] = $wild;
            $params[':q_cliche'] = $wild;
            $params[':q_seud'] = $wild;
            $params[':q_cust'] = $wild;
            $params[':q_rut'] = $wild;
            $params[':q_ot'] = $wild;
            $params[':q_title'] = $wild;
            $params[':q_sku'] = $wild;
            $params[':q_ptype'] = $wild;
        }

        // Specific field filters
        if (!empty($filters['oc'])) {
            $where[] = "o.req_oc_number LIKE :spec_oc";
            $params[':spec_oc'] = '%' . trim((string)$filters['oc']) . '%';
        }

        if (!empty($filters['cc'])) {
            $where[] = "o.req_number LIKE :spec_cc";
            $params[':spec_cc'] = '%' . trim((string)$filters['cc']) . '%';
        }

        if (!empty($filters['ot'])) {
            $where[] = "EXISTS (SELECT 1 FROM prod_header ph WHERE ph.prd_reqid = o.id AND ph.prd_number LIKE :spec_ot)";
            $params[':spec_ot'] = '%' . trim((string)$filters['ot']) . '%';
        }

        if (!empty($filters['customer'])) {
            $where[] = "EXISTS (SELECT 1 FROM customer c WHERE c.id = o.req_cust_id AND (c.cust_name LIKE :spec_cust OR c.cust_rut LIKE :spec_cust2))";
            $params[':spec_cust'] = '%' . trim((string)$filters['customer']) . '%';
            $params[':spec_cust2'] = '%' . trim((string)$filters['customer']) . '%';
        }

        if (!empty($filters['print_type'])) {
            $pType = trim((string)$filters['print_type']);
            $where[] = "EXISTS (SELECT 1 FROM orders_items oi WHERE oi.req_id = o.id AND (oi.fab_printtype = :spec_print OR oi.fab_printtype LIKE :spec_print_like))";
            $params[':spec_print'] = $pType;
            $params[':spec_print_like'] = '%' . $pType . '%';
        }

        if (!empty($filters['date_from'])) {
            $tFrom = strtotime((string)$filters['date_from'] . ' 00:00:00');
            if ($tFrom !== false) {
                $where[] = "o.req_crtdat >= :t_from";
                $params[':t_from'] = $tFrom;
            }
        }

        if (!empty($filters['date_to'])) {
            $tTo = strtotime((string)$filters['date_to'] . ' 23:59:59');
            if ($tTo !== false) {
                $where[] = "o.req_crtdat <= :t_to";
                $params[':t_to'] = $tTo;
            }
        }

        // Filter by stage
        if ($stageFilter === 'clises_espera') {
            $where[] = "EXISTS (SELECT 1 FROM orders_items oi WHERE oi.req_id = o.id AND oi.fab_printtype NOT IN ('', 'SIN', 'SIN IMPRESION', 'SIN IMPRESIÓN'))
                        AND o.req_cliche_peli_solic_repeat_act = 0
                        AND (o.req_cliche_peli_solic_dat = 0 OR (o.req_cliche_peli_solic_dat > 0 AND o.req_cliche_peli_recep_dat = 0))";
        } elseif ($stageFilter === 'preparacion_espera') {
            $where[] = "o.req_prod_checklist_term = 0";
        } elseif ($stageFilter === 'planificacion_espera') {
            $where[] = "NOT EXISTS (SELECT 1 FROM prod_header ph INNER JOIN prod_agenda pa ON pa.ag_prdid = ph.id WHERE ph.prd_reqid = o.id)";
        } elseif ($stageFilter === 'produccion') {
            $where[] = "EXISTS (SELECT 1 FROM prod_header ph INNER JOIN prod_agenda pa ON pa.ag_prdid = ph.id WHERE ph.prd_reqid = o.id)";
        } elseif ($stageFilter === 'despacho_pendiente') {
            $where[] = "o.req_status = 3";
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Phase 1: Fast count
        $countSql = "SELECT COUNT(*) FROM orders o {$whereClause}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();

        if ($totalRows === 0) {
            return [
                'items' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => 0,
            ];
        }

        // Phase 1b: Fetch ONLY the 20 order IDs for the page
        $idSql = "SELECT o.id FROM orders o {$whereClause} ORDER BY o.id DESC LIMIT {$perPage} OFFSET {$offset}";
        $idStmt = $this->pdo->prepare($idSql);
        $idStmt->execute($params);
        $ids = $idStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($ids)) {
            return [
                'items' => [],
                'total' => $totalRows,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int)ceil($totalRows / $perPage),
            ];
        }

        // Phase 2: Fetch detailed columns ONLY for the selected page order IDs
        $idIn = implode(',', array_map('intval', $ids));
        $dataSql = "
            SELECT 
                o.id, 
                o.req_number, 
                o.req_oc_number, 
                o.req_status, 
                o.req_crtdat, 
                o.req_fec_despacho, 
                o.req_despacho_desc,
                c.cust_name, 
                c.cust_rut,
                o.req_cliche_peli_solic_dat, 
                o.req_cliche_peli_recep_dat, 
                o.req_cliche_peli_solic_repeat_act,
                o.req_infoaddprd_cliche_codigo, 
                o.req_infoaddprd_cliche_ubicacion, 
                o.req_solic_supp_seudonimo,
                o.req_prod_checklist_term, 
                o.req_design_assign_uid,
                (SELECT SUM(oi.item_amount) FROM orders_items oi WHERE oi.req_id = o.id) as total_order_amount,
                (SELECT GROUP_CONCAT(DISTINCT oi.fab_printtype SEPARATOR ', ') FROM orders_items oi WHERE oi.req_id = o.id) as print_types,
                (SELECT GROUP_CONCAT(DISTINCT i.item_title SEPARATOR ' | ') FROM orders_items oi JOIN item i ON oi.item_id = i.id WHERE oi.req_id = o.id) as item_titles,
                (SELECT GROUP_CONCAT(DISTINCT i.item_number_prod SEPARATOR ', ') FROM orders_items oi JOIN item i ON oi.item_id = i.id WHERE oi.req_id = o.id) as item_skus,
                (SELECT MAX(oi.fab_design_imagehash) FROM orders_items oi WHERE oi.req_id = o.id) as fab_design_imagehash,
                ph.id as prod_header_id, 
                ph.prd_number as ot_number, 
                ph.prd_status,
                (SELECT COUNT(*) FROM prod_agenda pa WHERE pa.ag_prdid = ph.id) as agenda_count,
                (SELECT MIN(pa.ag_date) FROM prod_agenda pa WHERE pa.ag_prdid = ph.id AND pa.ag_status = 1) as next_agenda_date,
                (SELECT COALESCE(SUM(evt.evt_amount), 0) 
                 FROM prod_worker_ot_events evt 
                 JOIN prod_worker_ot pwo ON evt.evt_prod_worker_otid = pwo.id
                 JOIN prod_agenda pa ON pwo.wok_ag_id = pa.id
                 LEFT JOIN equipo e ON pa.ag_equipo_id = e.id
                 WHERE pa.ag_prdid = ph.id AND evt.evt_type = 'prod' 
                   AND (pa.ag_equipotype_id = 15 OR e.equipo_name = 'EMBALAJE' OR pa.ag_equipo_id = 25)) as produced_units,
                (SELECT COALESCE(SUM(odi.item_amount_shipped), 0)
                 FROM orders_delivery od
                 JOIN orders_delivery_items odi ON odi.dlv_id = od.id
                 WHERE od.dlv_order_id = o.id) as shipped_units
            FROM orders o
            LEFT JOIN customer c ON o.req_cust_id = c.id
            LEFT JOIN prod_header ph ON ph.prd_reqid = o.id
            WHERE o.id IN ({$idIn})
            GROUP BY o.id
            ORDER BY o.id DESC
        ";

        $stmt = $this->pdo->query($dataSql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($rows as $row) {
            $analysis = $this->computeStageAndBlocker($row);
            $items[] = array_merge($row, $analysis);
        }

        return [
            'items' => $items,
            'total' => $totalRows,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($totalRows / $perPage),
        ];
    }

    /**
     * Get complete 360° traceability detail for a specific order by ID.
     */
    public function getOrderDetail(int $orderId): ?array
    {
        $sql = "
            SELECT 
                o.*,
                c.cust_name, 
                c.cust_rut, 
                c.cust_street, 
                c.cust_phone, 
                c.cust_email,
                sup.supp_company as cliche_supplier_name,
                u_des.user_firstname as designer_firstname, 
                u_des.user_lastname as designer_lastname,
                ph.id as prod_header_id,
                ph.prd_number as ot_number,
                ph.prd_status,
                ph.prd_crtdat as ot_crtdat
            FROM orders o
            LEFT JOIN customer c ON o.req_cust_id = c.id
            LEFT JOIN supplier sup ON o.req_solic_supp_id = sup.id
            LEFT JOIN user u_des ON o.req_design_assign_uid = u_des.id
            LEFT JOIN prod_header ph ON ph.prd_reqid = o.id
            WHERE o.id = :order_id
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return null;
        }

        // Fetch Order Items
        $items = $this->getOrderItems($orderId);

        // Fetch Machine Agendas
        $agendas = $this->getOrderAgendas($orderId);

        // Fetch Production Events Log & Waste
        $events = $this->getOrderProductionEvents($orderId);

        // Fetch Deliveries & Dispatches
        $deliveries = $this->getOrderDeliveries($orderId);

        // Sum quantities
        $totalAmount = 0.0;
        $printTypes = [];
        foreach ($items as $it) {
            $totalAmount += (float)$it['item_amount'];
            if (!empty($it['fab_printtype'])) {
                $printTypes[] = $it['fab_printtype'];
            }
        }
        $order['total_order_amount'] = $totalAmount;
        $order['print_types'] = implode(', ', array_unique($printTypes));

        $producedUnits = 0.0;
        $producedMeters = 0.0;
        $consumedKg = 0.0;
        $producedFlexo = 0.0;
        $producedSellado = 0.0;
        $producedEmbalaje = 0.0;

        foreach ($events as $ev) {
            if ($ev['evt_type'] === 'prod') {
                $amt = (float)$ev['evt_amount'];
                $meters = (float)$ev['evt_amount_metros_lineales'];
                $kg = (float)$ev['prod_bobina_kg'];

                $eqType = (int)($ev['ag_equipotype_id'] ?? 0);
                $eqName = strtoupper(trim((string)($ev['equipo_name'] ?? '')));
                $eqId = (int)($ev['ag_equipo_id'] ?? 0);

                $isEmbalaje = ($eqType === 15 || $eqName === 'EMBALAJE' || $eqId === 25);
                $isFlexo = ($eqType === 7 || str_contains($eqName, 'FLEXO'));
                $isSellado = ($eqType === 8 || str_contains($eqName, 'SELLA'));

                if ($isEmbalaje) {
                    $producedEmbalaje += $amt;
                } elseif ($isSellado) {
                    $producedSellado += $amt;
                } elseif ($isFlexo) {
                    $producedFlexo += $amt;
                }

                $producedMeters += $meters;
                $consumedKg += $kg;
            }
        }

        // REQUERIMIENTO UNIBAG: El total fabricado para bolsas terminadas es SOLO lo que se embaló
        $order['produced_units'] = $producedEmbalaje;
        $order['produced_flexo'] = $producedFlexo;
        $order['produced_sellado'] = $producedSellado;
        $order['produced_embalaje'] = $producedEmbalaje;
        $order['produced_meters'] = $producedMeters;
        $order['consumed_kg'] = $consumedKg;

        $shippedUnits = 0.0;
        foreach ($deliveries as $dlv) {
            $shippedUnits += (float)$dlv['total_shipped'];
        }
        $order['shipped_units'] = $shippedUnits;
        $order['agenda_count'] = count($agendas);
        $order['next_agenda_date'] = !empty($agendas) ? $agendas[0]['ag_date'] : '';

        // Compute stage and blocker
        $stageAnalysis = $this->computeStageAndBlocker($order);

        // Compile attached files
        $attachments = [];
        for ($i = 0; $i <= 4; $i++) {
            $field = "req_prod_adjfile_{$i}";
            if (!empty($order[$field])) {
                $attachments[] = [
                    'index' => $i,
                    'filename' => $order[$field],
                    'ext' => strtolower(pathinfo($order[$field], PATHINFO_EXTENSION)),
                ];
            }
        }

        // Build Milestones Timeline
        $timeline = $this->buildMilestonesTimeline($order, $stageAnalysis, $items, $agendas, $events, $deliveries);

        return [
            'order' => $order,
            'analysis' => $stageAnalysis,
            'items' => $items,
            'agendas' => $agendas,
            'events' => $events,
            'deliveries' => $deliveries,
            'attachments' => $attachments,
            'timeline' => $timeline,
        ];
    }

    /**
     * Find order ID by customer purchase order number.
     */
    public function findOrderIdByOc(string $ocNumber): ?int
    {
        $clean = trim($ocNumber);
        if ($clean === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT id FROM orders WHERE req_oc_number = :oc ORDER BY id DESC LIMIT 1");
        $stmt->execute([':oc' => $clean]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            $stmt = $this->pdo->prepare("SELECT id FROM orders WHERE req_oc_number LIKE :oc ORDER BY id DESC LIMIT 1");
            $stmt->execute([':oc' => '%' . $clean . '%']);
            $id = $stmt->fetchColumn();
        }

        return $id ? (int)$id : null;
    }

    /**
     * Find order ID by Work Order (OT) number.
     */
    public function findOrderIdByOt(string $otNumber): ?int
    {
        $clean = trim($otNumber);
        if ($clean === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT prd_reqid FROM prod_header WHERE prd_number = :ot OR prd_number LIKE :ot_like ORDER BY id DESC LIMIT 1");
        $stmt->execute([':ot' => $clean, ':ot_like' => '%' . $clean . '%']);
        $id = $stmt->fetchColumn();

        return $id ? (int)$id : null;
    }

    /**
     * Find order ID by Nota de Venta (CC) number.
     */
    public function findOrderIdByNv(string $nvNumber): ?int
    {
        $clean = trim($nvNumber);
        if ($clean === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT id FROM orders WHERE req_number = :nv OR req_number LIKE :nv_like ORDER BY id DESC LIMIT 1");
        $stmt->execute([':nv' => $clean, ':nv_like' => '%' . $clean . '%']);
        $id = $stmt->fetchColumn();

        return $id ? (int)$id : null;
    }

    /**
     * Get items associated with an order.
     */
    public function getOrderItems(int $orderId): array
    {
        $sql = "
            SELECT 
                oi.*,
                i.item_title,
                i.item_number_prod,
                i.item_desc
            FROM orders_items oi
            LEFT JOIN item i ON oi.item_id = i.id
            WHERE oi.req_id = :order_id
            ORDER BY oi.item_pos ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get machine agendas for an order's OT.
     */
    public function getOrderAgendas(int $orderId): array
    {
        $sql = "
            SELECT 
                pa.*,
                e.equipo_name,
                e.equipo_desc,
                e.equipo_code
            FROM prod_agenda pa
            INNER JOIN prod_header ph ON pa.ag_prdid = ph.id
            LEFT JOIN equipo e ON pa.ag_equipo_id = e.id
            WHERE ph.prd_reqid = :order_id
            ORDER BY pa.id ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get production events log and machine execution with full operator and duration details.
     */
    public function getOrderProductionEvents(int $orderId): array
    {
        $sql = "
            SELECT 
                evt.id,
                evt.evt_type,
                evt.evt_amount,
                evt.evt_amount_metros_lineales,
                evt.prod_bobina_kg,
                evt.evt_crtdat,
                evt.evt_enddat,
                evt.evt_status,
                evt.evt_comments,
                e.id as ag_equipo_id,
                e.equipo_name,
                e.equipo_code,
                e.equipo_desc,
                pa.id as agenda_id,
                pa.ag_date,
                pa.ag_equipotype_id,
                pa.ag_amount as planned_amount,
                COALESCE(NULLIF(TRIM(CONCAT(w.wrk_firstname, ' ', w.wrk_lastname)), ''), TRIM(CONCAT(u.user_firstname, ' ', u.user_lastname)), 'No asignado') as operator_name,
                COALESCE(TRIM(CONCAT(w_ay.wrk_firstname, ' ', w_ay.wrk_lastname)), '') as helper_name
            FROM prod_worker_ot_events evt
            INNER JOIN prod_worker_ot pwo ON evt.evt_prod_worker_otid = pwo.id
            INNER JOIN prod_agenda pa ON pwo.wok_ag_id = pa.id
            INNER JOIN prod_header ph ON pa.ag_prdid = ph.id
            LEFT JOIN equipo e ON pa.ag_equipo_id = e.id
            LEFT JOIN prod_worker_init pwi ON pwo.wok_init_id = pwi.id
            LEFT JOIN workers w ON pwi.win_wrkid = w.id
            LEFT JOIN user u ON (pwo.wok_idusuario > 0 AND pwo.wok_idusuario = u.id)
            LEFT JOIN workers w_ay ON evt.evt_idayudante = w_ay.id
            WHERE ph.prd_reqid = :order_id
            ORDER BY evt.id DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $crtdat = (int)$r['evt_crtdat'];
            $enddat = (int)$r['evt_enddat'];
            $durSec = ($enddat > $crtdat && $enddat > 0) ? ($enddat - $crtdat) : 0;
            $hrs = floor($durSec / 3600);
            $mins = floor(($durSec % 3600) / 60);

            $r['duration_sec'] = $durSec;
            $r['duration_text'] = ($enddat > 0 && $durSec > 0) ? ($hrs > 0 ? "{$hrs}h {$mins}m" : "{$mins} min") : ($enddat > 0 ? "Menos de 1 min" : "En curso");
            $r['start_fmt'] = $crtdat > 0 ? date('d/m/Y H:i', $crtdat) : '-';
            $r['end_fmt'] = $enddat > 0 ? date('d/m/Y H:i', $enddat) : 'En proceso';
        }
        unset($r);

        return $rows;
    }

    /**
     * Get delivery records and dispatch guides for an order, including all dispatched items.
     */
    public function getOrderDeliveries(int $orderId): array
    {
        $sql = "
            SELECT 
                od.id,
                od.dlv_num,
                od.dlv_docnum,
                od.dlv_delivery_date,
                od.dlv_status,
                od.dlv_oc_number,
                od.dlv_annotation,
                od.dlv_direccion_destino,
                od.dlv_total_netto,
                od.dlv_total_brutto,
                c.cust_name,
                c.cust_rut,
                COALESCE(SUM(odi.item_amount_shipped), 0) as total_shipped
            FROM orders_delivery od
            LEFT JOIN orders_delivery_items odi ON odi.dlv_id = od.id
            LEFT JOIN customer c ON od.dlv_cust_id = c.id
            WHERE od.dlv_order_id = :order_id
            GROUP BY od.id
            ORDER BY od.id DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($deliveries as &$dlv) {
            $dlv['items'] = $this->getDeliveryItems((int)$dlv['id']);
            $dlv['date_fmt'] = (int)$dlv['dlv_delivery_date'] > 0 ? date('d/m/Y', (int)$dlv['dlv_delivery_date']) : '-';
        }
        unset($dlv);

        return $deliveries;
    }

    /**
     * Get all items shipped in a specific delivery guide.
     */
    public function getDeliveryItems(int $deliveryId): array
    {
        $sql = "
            SELECT 
                odi.dlv_id,
                odi.item_id,
                odi.item_pos,
                odi.item_amount_shipped,
                odi.item_sellprice_netto,
                odi.item_sellprice_brutto,
                odi.item_desc,
                COALESCE(NULLIF(i.item_title, ''), odi.item_desc, 'Bolsa') as item_title,
                i.item_number_prod,
                o.req_number as order_nv,
                o.req_oc_number as order_oc
            FROM orders_delivery_items odi
            LEFT JOIN item i ON odi.item_id = i.id
            LEFT JOIN orders_delivery od ON odi.dlv_id = od.id
            LEFT JOIN orders o ON od.dlv_order_id = o.id
            WHERE odi.dlv_id = :dlv_id
            ORDER BY odi.item_pos ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':dlv_id' => $deliveryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get full detailed header and items for a delivery guide.
     */
    public function getDeliveryDetail(int $deliveryId): ?array
    {
        $sql = "
            SELECT 
                od.*,
                c.cust_name,
                c.cust_rut,
                c.cust_street,
                c.cust_phone,
                u.user_firstname as creator_firstname,
                u.user_lastname as creator_lastname
            FROM orders_delivery od
            LEFT JOIN customer c ON od.dlv_cust_id = c.id
            LEFT JOIN user u ON od.dlv_crtusr = u.id
            WHERE od.id = :dlv_id
            LIMIT 1
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':dlv_id' => $deliveryId]);
        $dlv = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$dlv) {
            return null;
        }

        $dlv['items'] = $this->getDeliveryItems($deliveryId);
        $dlv['date_fmt'] = (int)$dlv['dlv_delivery_date'] > 0 ? date('d/m/Y', (int)$dlv['dlv_delivery_date']) : '-';
        return $dlv;
    }

    /**
     * Construct the 6-milestone lifecycle timeline with individual statuses.
     */
    private function buildMilestonesTimeline(
        array $order,
        array $analysis,
        array $items,
        array $agendas,
        array $events,
        array $deliveries
    ): array {
        $printType = strtoupper(trim((string)($order['print_types'] ?? '')));
        $needsCliche = $analysis['needs_cliche'];

        // Milestone 1: Comercial
        $m1Status = 'completed';
        $m1Date = (int)($order['req_crtdat'] ?? 0) > 0 ? date('d/m/Y', (int)$order['req_crtdat']) : '-';
        $m1Desc = "Nota de Venta: {$order['req_number']}" . (!empty($order['req_oc_number']) ? " | OC: {$order['req_oc_number']}" : '');

        // Milestone 2: Clisés / Fotopolímeros
        $m2Status = 'pending';
        $m2Date = '-';
        $m2Desc = '';
        if (!$needsCliche) {
            $m2Status = 'skipped';
            $m2Desc = 'No requiere impresión ni clisé (Sin impresión)';
        } elseif ((int)$order['req_cliche_peli_solic_repeat_act'] === 1) {
            $m2Status = 'completed';
            $m2Desc = 'Clisé existente en bodega (Repetición)';
            if (!empty($order['req_infoaddprd_cliche_codigo'])) {
                $m2Desc .= " [Cód: {$order['req_infoaddprd_cliche_codigo']}]";
            }
        } elseif ((int)$order['req_cliche_peli_recep_dat'] > 0) {
            $m2Status = 'completed';
            $m2Date = date('d/m/Y', (int)$order['req_cliche_peli_recep_dat']);
            $m2Desc = "Clisé recibido en planta el {$m2Date}";
        } elseif ((int)$order['req_cliche_peli_solic_dat'] > 0) {
            $m2Status = 'in_progress';
            $m2Date = date('d/m/Y', (int)$order['req_cliche_peli_solic_dat']);
            $m2Desc = "Solicitado a proveedor el {$m2Date}. Pendiente de recepción física.";
        } else {
            $m2Status = 'blocked';
            $m2Desc = 'Pendiente solicitud de compra de clisé a proveedor.';
        }

        // Milestone 3: Pre-Prensa y Preparación
        $m3Status = 'pending';
        $m3Date = '-';
        $m3Desc = '';
        if ((int)$order['req_prod_checklist_term'] === 1) {
            $m3Status = 'completed';
            $m3Desc = 'Checklist técnico de Pre-prensa aprobado y validado.';
        } else {
            if ($m2Status === 'completed' || $m2Status === 'skipped') {
                $m3Status = 'in_progress';
                $m3Desc = 'En revisión técnica de pre-prensa y validación de diseño.';
            } else {
                $m3Status = 'pending';
                $m3Desc = 'A la espera de resolución de clisés para completar pre-prensa.';
            }
        }

        // Milestone 4: OT y Planificación
        $m4Status = 'pending';
        $m4Date = '-';
        $m4Desc = '';
        if (!empty($order['ot_number'])) {
            if (!empty($agendas)) {
                $m4Status = 'completed';
                $machines = array_unique(array_filter(array_column($agendas, 'equipo_name')));
                $m4Desc = "OT: {$order['ot_number']} | Programado en: " . implode(', ', $machines);
            } else {
                $m4Status = 'in_progress';
                $m4Desc = "OT: {$order['ot_number']} emitida | Pendiente agendar máquina.";
            }
        } else {
            $m4Status = 'pending';
            $m4Desc = 'Pendiente generación de Orden de Trabajo (OT).';
        }

        // Milestone 5: Fabricación en Planta
        $m5Status = 'pending';
        $m5Date = '-';
        $m5Desc = '';
        $totalAmt = (float)$order['total_order_amount'];
        $produced = (float)$order['produced_units'];
        if ($produced >= ($totalAmt * 0.95) && $totalAmt > 0) {
            $m5Status = 'completed';
            $m5Desc = "Producción 100% terminada (" . number_format($produced, 0, ',', '.') . " un.).";
        } elseif ($produced > 0) {
            $m5Status = 'in_progress';
            $pct = $totalAmt > 0 ? round(($produced / $totalAmt) * 100, 1) : 0;
            $m5Desc = "En proceso en máquinas: " . number_format($produced, 0, ',', '.') . " un. fabricadas ({$pct}%).";
        } else {
            if ($m4Status === 'completed') {
                $m5Status = 'in_progress';
                $m5Desc = 'Agendado en máquina. En cola para inicio de tirada.';
            } else {
                $m5Status = 'pending';
                $m5Desc = 'Pendiente de inicio de fabricación.';
            }
        }

        // Milestone 6: Despacho y Entrega
        $m6Status = 'pending';
        $m6Date = '-';
        $m6Desc = '';
        $shipped = (float)$order['shipped_units'];
        if ($shipped >= ($totalAmt * 0.95) && $totalAmt > 0) {
            $m6Status = 'completed';
            $m6Desc = "Entregado en su totalidad (" . number_format($shipped, 0, ',', '.') . " un.).";
        } elseif ($shipped > 0) {
            $m6Status = 'in_progress';
            $saldo = max(0, $totalAmt - $shipped);
            $m6Desc = "Despacho parcial realizado (" . number_format($shipped, 0, ',', '.') . " un.). Saldo pendiente: " . number_format($saldo, 0, ',', '.') . " un.";
        } else {
            if ($m5Status === 'completed') {
                $m6Status = 'blocked';
                $m6Desc = 'Producción lista en bodega. Pendiente emisión de guía de despacho.';
            } else {
                $m6Status = 'pending';
                $m6Desc = 'Pendiente de despacho al cliente.';
            }
        }

        return [
            [
                'step' => 1,
                'name' => 'Comercial & Pedido',
                'status' => $m1Status,
                'date' => $m1Date,
                'desc' => $m1Desc,
                'icon' => '🛒',
            ],
            [
                'step' => 2,
                'name' => 'Clisés & Matrices',
                'status' => $m2Status,
                'date' => $m2Date,
                'desc' => $m2Desc,
                'icon' => '🎨',
            ],
            [
                'step' => 3,
                'name' => 'Pre-Prensa Técnica',
                'status' => $m3Status,
                'date' => $m3Date,
                'desc' => $m3Desc,
                'icon' => '📋',
            ],
            [
                'step' => 4,
                'name' => 'Planificación & OTs',
                'status' => $m4Status,
                'date' => $m4Date,
                'desc' => $m4Desc,
                'icon' => '📅',
            ],
            [
                'step' => 5,
                'name' => 'Fabricación en Planta',
                'status' => $m5Status,
                'date' => $m5Date,
                'desc' => $m5Desc,
                'icon' => '⚙️',
            ],
            [
                'step' => 6,
                'name' => 'Despacho & Facturación',
                'status' => $m6Status,
                'date' => $m6Date,
                'desc' => $m6Desc,
                'icon' => '🚚',
            ],
        ];
    }
}
