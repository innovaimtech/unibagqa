<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Eventos Operativos · Apertura, Tirajes, Pausas, Mantención y Mermas
// (src/Services/Production/ProductionEventsService.php)
// =============================================================================

trait ProductionEventsServiceTrait
{
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
                       t4.pause_name, t5.mant_title AS repair_name, t5.mant_code AS repair_code, t6.ubim_title AS ubim_name
                FROM prod_worker_ot_events t1
                LEFT OUTER JOIN prod_medidas t2 ON t1.evt_medida_fromid = t2.id
                LEFT OUTER JOIN prod_medidas t3 ON t1.evt_medida_toid = t3.id
                LEFT OUTER JOIN prod_pause_types t4 ON t1.evt_pause_id = t4.id
                LEFT OUTER JOIN equipo_manttype t5 ON t1.evt_equipo_mantid = t5.id
                LEFT OUTER JOIN equipos_ubimants t6 ON t1.evt_ubim_id = t6.id
                WHERE (
                    t1.evt_prod_worker_otid = :ot_id
                    OR (t1.overrideembalaje_act = 1 AND t1.overrideembalaje_selladora_evtrefid = :ot_id2)
                )
                AND t1.evt_status > 0
                AND (
                    LOWER(t1.evt_type) NOT IN ('prod', 'production', 'prodsericolor')
                    OR t1.evt_comments IS NULL
                    OR (t1.evt_comments NOT LIKE '%Bobina Semielaborada%' AND t1.evt_comments NOT LIKE '%ROLL-SE-%')
                )
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

    /** CyS always captures both legacy measure IDs, including unchanged setups.
     *  CyS siempre registra ambos IDs de medida, incluso cuando no hay cambio. */
    public function validateCysSetupMeasuresForAgenda(int $agId, int $fromId, int $toId): void
    {
        $stmt = $this->erpPdo->prepare('SELECT eq.equipo_type_id, t.type_ant_title
            FROM prod_agenda ag JOIN equipo eq ON eq.id=ag.ag_equipo_id
            LEFT JOIN equipo_type t ON t.id=eq.equipo_type_id WHERE ag.id=?');
        $stmt->execute([$agId]);
        $machine = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$machine) throw new RuntimeException('No se encontro la maquina de la OT.');
        if (!$this->isCysSetupMachine($machine)) return;
        $this->validateCysMeasureIds($fromId, $toId);
    }

    private function isCysSetupMachine(array $machine): bool
    {
        $title = (string)($machine['type_ant_title'] ?? '');
        return (int)($machine['equipo_type_id'] ?? 0) === 8
            || stripos($title, 'sellad') !== false || stripos($title, 'corte') !== false;
    }

    private function validateCysMeasureIds(int $fromId, int $toId): void
    {
        if ($fromId <= 0 || $toId <= 0) {
            throw new RuntimeException('En Corte y Sellado debes seleccionar De Medida y A Medida.');
        }
        $stmt = $this->erpPdo->prepare('SELECT id FROM prod_medidas WHERE id IN (?,?) AND med_status>0');
        $stmt->execute([$fromId,$toId]);
        $ids = array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
        if (!in_array($fromId,$ids,true) || !in_array($toId,$ids,true)) {
            throw new RuntimeException('Selecciona medidas vigentes del catalogo ERP.');
        }
    }

    public function getCysSetupMeasuresStatus(int $workerOtId): array
    {
        $stmt = $this->erpPdo->prepare('SELECT ot.wok_ag_id, eq.equipo_type_id, t.type_ant_title
            FROM prod_worker_ot ot JOIN prod_worker_init wi ON wi.id=ot.wok_init_id
            JOIN equipo eq ON eq.id=wi.win_equipoid LEFT JOIN equipo_type t ON t.id=eq.equipo_type_id
            WHERE ot.id=?');
        $stmt->execute([$workerOtId]);
        $machine = $stmt->fetch(PDO::FETCH_ASSOC);
        $required = $machine && $this->isCysSetupMachine($machine);
        if (!$required) return ['required'=>false,'complete'=>true,'event'=>null];
        $stmt = $this->erpPdo->prepare("SELECT ev.*, m1.med_name AS from_med_name, m2.med_name AS to_med_name
            FROM prod_worker_ot_events ev
            LEFT JOIN prod_medidas m1 ON m1.id=ev.evt_medida_fromid
            LEFT JOIN prod_medidas m2 ON m2.id=ev.evt_medida_toid
            WHERE ev.evt_prod_worker_otid=? AND ev.evt_type='apertura' AND ev.evt_status>0
            ORDER BY ev.evt_crtdat DESC,ev.id DESC LIMIT 1");
        $stmt->execute([$workerOtId]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        // Historical measures can be inactive; retain valid historical references.
        // Las medidas historicas pueden estar inactivas; conservar sus referencias validas.
        $complete = $event && (int)$event['evt_medida_fromid']>0 && (int)$event['evt_medida_toid']>0
            && $event['from_med_name'] !== null && $event['to_med_name'] !== null;
        return ['required'=>true,'complete'=>(bool)$complete,'event'=>$event];
    }

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

        $measureStatus = $this->getCysSetupMeasuresStatus($workerOtId);
        if ($measureStatus['required']) {
            try {
                $this->validateCysMeasureIds($medidaFromId,$medidaToId);
                if ($refId !== null && $refId > 0) {
                    $event = $this->getEventById($refId,$workerOtId);
                    if (!$event || $event['evt_type'] !== 'apertura') {
                        throw new RuntimeException('El alistamiento no pertenece a esta OT.');
                    }
                } elseif ($measureStatus['event'] !== null) {
                    // Retry initial capture without inserting another setup event.
                    // Reintentar captura inicial sin crear otro evento de alistamiento.
                    $existing = $measureStatus['event'];
                    if ($measureStatus['complete']) {
                        if ((int)$existing['evt_medida_fromid'] !== $medidaFromId
                            || (int)$existing['evt_medida_toid'] !== $medidaToId) {
                            throw new RuntimeException('La OT ya tiene medidas. Actualiza su alistamiento existente.');
                        }
                        return ['ok'=>true,'event_id'=>(int)$existing['id']];
                    }
                    $refId = (int)$existing['id'];
                }
            } catch (RuntimeException $e) {
                return ['ok'=>false,'error'=>$e->getMessage()];
            }
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

    /** Recupera el detalle de embalaje del evento, sin crear stock ni etiquetas. */
    public function getPackagingEventDetail(int $eventId, int $workerOtId): array
    {
        // El evento decide a qué OT pertenece: el embalaje de una selladora pasa a la OT de Embalaje al cerrarse.
        $stmt = $this->erpPdo->prepare('SELECT pe.* FROM prod_worker_embalajes pe
            JOIN prod_worker_ot_events e ON e.id = pe.req_v2refid
            WHERE pe.req_v2refid = :event
              AND (e.evt_prod_worker_otid = :ot OR (e.overrideembalaje_act = 1 AND e.overrideembalaje_selladora_evtrefid = :linked_ot))
            ORDER BY pe.id DESC LIMIT 1');
        $stmt->execute([':event' => $eventId, ':ot' => $workerOtId, ':linked_ot' => $workerOtId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** Evento de la OT, o embalaje enlazado a ella (como en getWorkOrderEvents). */
    public function getEventById(int $eventId, int $workerOtId): ?array
    {
        $stmt = $this->erpPdo->prepare('SELECT * FROM prod_worker_ot_events WHERE id = :event AND evt_status > 0
            AND (evt_prod_worker_otid = :ot OR (overrideembalaje_act = 1 AND overrideembalaje_selladora_evtrefid = :linked_ot))');
        $stmt->execute([':event' => $eventId, ':ot' => $workerOtId, ':linked_ot' => $workerOtId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }



    /** Registra o finaliza un evento de produccion o embalaje. */
    /** Match the phase and owning OT, never the first unrelated production.
     *  Buscar por proceso y OT, nunca tomar la primera produccion ajena. */
    public static function findOpenProductionPhase(array $events, int $workerOtId, int $packaging): ?array
    {
        foreach ($events as $event) {
            if (in_array((string)$event['evt_type'], ['prod', 'prodsericolor'], true)
                && (int)$event['evt_prod_worker_otid'] === $workerOtId
                && (int)$event['evt_status'] === 1 && (int)($event['evt_enddat'] ?? 0) === 0
                && (int)($event['overrideembalaje_act'] ?? 0) === $packaging) {
                return $event;
            }
        }
        return null;
    }

    public function assertNoOpenProductionPhases(int $workerOtId): void
    {
        $isCys = $this->getCysSetupMeasuresStatus($workerOtId)['required'];
        $stmt = $this->erpPdo->prepare("SELECT COUNT(*) FROM prod_worker_ot_events
            WHERE (evt_prod_worker_otid=:ot OR (overrideembalaje_act=1
                AND overrideembalaje_selladora_evtrefid=:linked_ot))
            AND (:is_cys=1 OR overrideembalaje_act=1)
            AND evt_type IN ('prod','prodsericolor') AND evt_status=1 AND evt_enddat=0");
        $stmt->execute([':ot'=>$workerOtId, ':linked_ot'=>$workerOtId, ':is_cys'=>$isCys ? 1 : 0]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException('Finaliza las producciones en curso, incluido Embalaje, antes de terminar la OT.');
        }
    }

    private function ensureProductionPhaseSchema(): void
    {
        if ($this->trzPdo === null) {
            throw new RuntimeException('No hay conexion de trazabilidad para registrar responsables.');
        }
        $this->trzPdo->exec("CREATE TABLE IF NOT EXISTS production_phase_responsibility (
            event_id BIGINT NOT NULL PRIMARY KEY,
            worker_ot_id BIGINT NOT NULL,
            packaging INTEGER NOT NULL,
            receiver_worker_id BIGINT NOT NULL DEFAULT 0,
            receiver_name VARCHAR(200) NOT NULL DEFAULT '',
            started_by_worker_id BIGINT NOT NULL DEFAULT 0,
            started_by_name VARCHAR(200) NOT NULL DEFAULT '',
            ended_by_worker_id BIGINT NOT NULL DEFAULT 0,
            ended_by_name VARCHAR(200) NOT NULL DEFAULT ''
        )" . ($this->trzPdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : ''));
    }

    public function getProductionPhaseResponsibilities(int $workerOtId): array
    {
        if ($this->trzPdo === null) return [];
        try {
            $stmt = $this->trzPdo->prepare('SELECT * FROM production_phase_responsibility WHERE worker_ot_id=?');
            $stmt->execute([$workerOtId]);
            $result = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $result[(int)$row['event_id']] = $row;
            }
            return $result;
        } catch (PDOException $e) {
            // Old installations have no phase records until the first explicit capture.
            // Instalaciones anteriores no tienen registros hasta la primera captura.
            if ((string)$e->getCode() === '42S02'
                || ((string)$e->getCode() === 'HY000' && strpos($e->getMessage(), 'no such table') !== false)) return [];
            throw $e;
        }
    }

    public function listProductionReceivers(): array
    {
        return $this->erpPdo->query('SELECT id, wrk_firstname, wrk_lastname FROM workers
            WHERE wrk_status>0 ORDER BY wrk_firstname, wrk_lastname')->fetchAll(PDO::FETCH_ASSOC);
    }

    private function validateProductionPhaseActor(array $actor, int $workerOtId, ?int $refId): array
    {
        if ((int)($actor['worker_id'] ?? 0) <= 0 || trim((string)($actor['name'] ?? '')) === '') {
            throw new RuntimeException('No se pudo identificar al operador conectado.');
        }
        $existing = ($refId !== null && $refId > 0)
            ? ($this->getProductionPhaseResponsibilities($workerOtId)[$refId] ?? null)
            : null;
        $receiverId = (int)($actor['receiver_worker_id'] ?? 0);

        if ($receiverId > 0) {
            $stmt = $this->erpPdo->prepare('SELECT wrk_firstname, wrk_lastname FROM workers WHERE id=? AND wrk_status>0');
            $stmt->execute([$receiverId]);
            $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($receiver) {
                $actor['receiver_worker_id'] = $receiverId;
                $actor['receiver_name'] = trim($receiver['wrk_firstname'] . ' ' . $receiver['wrk_lastname']);
            } else {
                $actor['receiver_worker_id'] = 0;
                $actor['receiver_name'] = '';
            }
        } elseif ($existing !== null && !empty($existing['receiver_worker_id'])) {
            $actor['receiver_worker_id'] = (int)$existing['receiver_worker_id'];
            $actor['receiver_name'] = (string)($existing['receiver_name'] ?? '');
        } else {
            $actor['receiver_worker_id'] = 0;
            $actor['receiver_name'] = '';
        }
        $this->ensureProductionPhaseSchema();
        return $actor;
    }

    private function saveProductionPhaseActor(int $eventId, int $workerOtId, int $packaging, array $actor, bool $isEnd): void
    {
        $existing = $this->getProductionPhaseResponsibilities($workerOtId)[$eventId] ?? null;
        $receiverId = (int)($actor['receiver_worker_id'] ?? 0);
        $receiverName = (string)($actor['receiver_name'] ?? '');

        if ($existing === null) {
            $stmt = $this->trzPdo->prepare('INSERT INTO production_phase_responsibility
                (event_id, worker_ot_id, packaging, receiver_worker_id, receiver_name,
                 started_by_worker_id, started_by_name, ended_by_worker_id, ended_by_name)
                VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $eventId,
                $workerOtId,
                $packaging,
                $receiverId,
                $receiverName,
                !empty($actor['starting']) ? (int)$actor['worker_id'] : 0,
                !empty($actor['starting']) ? (string)$actor['name'] : 'No registrado',
                $isEnd ? (int)$actor['worker_id'] : 0,
                $isEnd ? (string)$actor['name'] : ''
            ]);
        } else {
            if ($isEnd) {
                if ($receiverId > 0 && $receiverName !== '') {
                    $stmt = $this->trzPdo->prepare('UPDATE production_phase_responsibility
                        SET ended_by_worker_id=?, ended_by_name=?, receiver_worker_id=?, receiver_name=?
                        WHERE event_id=?');
                    $stmt->execute([(int)$actor['worker_id'], (string)$actor['name'], $receiverId, $receiverName, $eventId]);
                } else {
                    $stmt = $this->trzPdo->prepare('UPDATE production_phase_responsibility
                        SET ended_by_worker_id=?, ended_by_name=?
                        WHERE event_id=?');
                    $stmt->execute([(int)$actor['worker_id'], (string)$actor['name'], $eventId]);
                }
            } elseif ($receiverId > 0 && $receiverName !== '') {
                $stmt = $this->trzPdo->prepare('UPDATE production_phase_responsibility
                    SET receiver_worker_id=?, receiver_name=?
                    WHERE event_id=?');
                $stmt->execute([$receiverId, $receiverName, $eventId]);
            }
        }
    }

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
        float $amountPulpoDorso = 0.0,
        array $cysPackingDetail = [],
        array $phaseActor = []
    ): array {
        if ($workerOtId <= 0) {
            return ['ok' => false, 'error' => 'ID de OT inválido.'];
        }

        if ($cysPackingDetail !== [] || !in_array($overrideEmbalaje, [0, 1], true)) {
            return ['ok'=>false, 'error'=>'Las cantidades de embalaje se registran solo en su cierre separado.'];
        }
        if ($refId !== null && $refId > 0) {
            $event = $this->getEventById($refId, $workerOtId);
            if (!$event || !in_array((string)$event['evt_type'], ['prod', 'prodsericolor'], true)
                || (int)$event['overrideembalaje_act'] !== $overrideEmbalaje) {
                return ['ok'=>false, 'error'=>'El evento no corresponde a este proceso y OT.'];
            }
        }

        if ($overrideEmbalaje === 0 && ($refId === null || $refId <= 0)) {
            $measureStatus = $this->getCysSetupMeasuresStatus($workerOtId);
            if ($measureStatus['required'] && !$measureStatus['complete']) {
                return ['ok'=>false,'error'=>'Antes de iniciar produccion, registra De Medida y A Medida en Corte y Sellado.'];
            }
        }

        $now = time();
        $eventId = 0;

        // Si no viene refId y se trata de un inicio de tiraje (amount = 0), comprobar si ya existe un evento 'prod' abierto
        if ($refId === null || $refId <= 0) {
            if (!$isEnd && $amount <= 0.0) {
                $stmtActiveProd = $this->erpPdo->prepare("
                    SELECT id FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :ot_id 
                      AND evt_type = 'prod'
                      AND evt_status = 1 AND evt_enddat = 0 AND overrideembalaje_act = :packaging
                    ORDER BY id DESC LIMIT 1
                ");
                $stmtActiveProd->execute([':ot_id' => $workerOtId, ':packaging' => $overrideEmbalaje]);
                $activeProdId = (int)$stmtActiveProd->fetchColumn();
                if ($activeProdId > 0) {
                    // Repeated starts must not reset counters or the original start time.
                    // Repetir inicio no borra contadores ni modifica la hora original.
                    return ['ok'=>true, 'event_id'=>$activeProdId];
                }
            }
        }
        if ($phaseActor !== []) {
            try {
                if ($isEnd && ($refId === null || $refId <= 0)) {
                    throw new RuntimeException('Inicia este proceso antes de finalizar su produccion.');
                }
                $phaseActor = $this->validateProductionPhaseActor($phaseActor, $workerOtId, $refId);
                $phaseActor['starting'] = $refId === null || $refId <= 0;
            } catch (Throwable $e) {
                return ['ok'=>false, 'error'=>$e->getMessage()];
            }
        }
        // Automatización de Bobinas Impresas: Si no vinieron kilos o metros, autocompletar con la suma de bobinas impresas producidas
        if ($overrideEmbalaje !== 1 && $workerOtId > 0 && ($bobinaKg <= 0.0 || $metrosLineales <= 0)) {
            $prSummary = $this->getPrintedRollsSummary($workerOtId);
            if (!empty($prSummary['rolls_count']) && $prSummary['rolls_count'] > 0) {
                if ($bobinaKg <= 0.0) {
                    $bobinaKg = (float)$prSummary['total_weight_kg'];
                }
                if ($metrosLineales <= 0) {
                    $metrosLineales = (int)round($prSummary['total_meters']);
                }
            }
        }
        if (($metroType === 'metros_lineales' || $metroType === '') && $amount <= 0.0 && $metrosLineales > 0) {
            try {
                $stmtAg = $this->erpPdo->prepare("SELECT wok_ag_id FROM prod_worker_ot WHERE id = :worker_ot_id LIMIT 1");
                $stmtAg->execute([':worker_ot_id' => $workerOtId]);
                $agId = (int)$stmtAg->fetchColumn();
                if ($agId > 0) {
                    $agendaData = $this->getWorkOrderAgendaDetails($agId);
                    if (is_array($agendaData)) {
                        $paramMedida = (int)($agendaData['fab_med_width'] ?? 0);
                        if (!empty($agendaData['item_prodcalc_fuelle_act'])) {
                            $paramMedida += (int)($agendaData['fab_med_fuelle'] ?? 0);
                        }
                        $corteM2 = 0.0;
                        $stmtEq = $this->erpPdo->prepare(
                            "SELECT * FROM equipo_params
                             WHERE param_equipo_id = :eq_id
                               AND param_medida >= :medida
                             ORDER BY param_medida ASC
                             LIMIT 1"
                        );
                        $stmtEq->execute([
                            ':eq_id' => (int)($agendaData['ag_equipo_id'] ?? 0),
                            ':medida' => $paramMedida,
                        ]);
                        $equipoParams = $stmtEq->fetch(PDO::FETCH_ASSOC) ?: null;
                        if (is_array($equipoParams)) {
                            $polType = (string)($agendaData['req_solic_devprints_poltype'] ?? '');
                            if ($polType === 'pol284' && isset($equipoParams['param_poly28'])) {
                                $corteM2 = (float)$equipoParams['param_poly28'];
                            } elseif ($polType === 'pol170' && isset($equipoParams['param_poly17'])) {
                                $corteM2 = (float)$equipoParams['param_poly17'];
                            } else {
                                $corteM2 = (float)($equipoParams['param_corte'] ?? 0);
                            }
                        }
                        if ($corteM2 > 0.0) {
                            $amount = (float)round($metrosLineales / $corteM2);
                        }
                    }
                }
            } catch (Throwable) {
            }
        }

        if ($refId !== null && $refId > 0) {
            $eventId = $refId;
            $endDat = $isEnd ? ((int)($event['evt_enddat'] ?? 0) > 0 ? (int)$event['evt_enddat'] : $now) : null;
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

        if ($phaseActor !== []) {
            $this->saveProductionPhaseActor($eventId, $workerOtId, $overrideEmbalaje, $phaseActor, $isEnd);
        }

        // Consolidación de mermas de desmontaje de bobinas:
        // Si el evento final no trae alguna merma de desmontaje previamente registrada, cargarla automáticamente
        if ($overrideEmbalaje !== 1 && $workerOtId > 0) {
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
        if ($overrideEmbalaje !== 1 && $workerOtId > 0 && $eventId > 0) {
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
        if ($overrideEmbalaje !== 1 && $workerOtId > 0 && $eventId > 0) {
            $prSummary = $this->getPrintedRollsSummary($workerOtId);
            if (!empty($prSummary['rolls_count']) && $prSummary['rolls_count'] > 0) {
                try {
                    $this->erpPdo->prepare("
                        UPDATE prod_worker_ot_events
                        SET evt_status = 2,
                            evt_enddat = COALESCE(evt_enddat, :now),
                            prod_bobina_kg = 0,
                            evt_amount = 0,
                            evt_amount_metros_lineales = 0,
                            evt_amount_metros_maquina = 0
                        WHERE evt_prod_worker_otid = :ot_id
                          AND (evt_comments LIKE '%Bobina Semielaborada%' OR evt_comments LIKE '%ROLL-SE-%')
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



    /**
     * Elimina un evento de producción con reversión de consumos y auditoría.
     * Operación restringida exclusivamente a usuarios autorizados (Héctor y Javier).
     */
    public function deleteEvent(int $eventId, int $workerOtId, ?string $userName = null): array
    {
        if ($eventId <= 0 || $workerOtId <= 0) {
            return ['ok' => false, 'error' => 'Identificadores de evento u orden no válidos.'];
        }

        $editor = trim((string)($userName ?? 'Javier / Héctor'));

        try {
            $stBase = $this->erpPdo->prepare("
                SELECT id, evt_prod_worker_otid, evt_type, evt_amount, evt_comments
                FROM prod_worker_ot_events
                WHERE id = :eventId AND (evt_prod_worker_otid = :otId OR overrideembalaje_selladora_evtrefid = :otId2)
                LIMIT 1
            ");
            $stBase->execute([
                ':eventId' => $eventId,
                ':otId' => $workerOtId,
                ':otId2' => $workerOtId,
            ]);
            $evRow = $stBase->fetch(PDO::FETCH_ASSOC);
            if (!$evRow) {
                return ['ok' => false, 'error' => 'No se encontró el registro de producción especificado o ya fue eliminado.'];
            }

            $ownsTransaction = false;

            // Sin esto, la producción desaparece y el stock de ese embalaje queda en bodega. Si falla, no se elimina.
            $this->reversePackagingEventStock($eventId);

            if (!$this->erpPdo->inTransaction()) {
                $this->erpPdo->beginTransaction();
                $ownsTransaction = true;
            }

            // 1. Revertir consumos de insumos en Bodega 3000 si fue un cierre de embalaje
            $refTag = "[EMB_SUPPLY_CONSUMPTION:evt_{$eventId}]";
            $stPrev = $this->erpPdo->prepare("
                SELECT id, evt_amount, evt_comments
                FROM prod_worker_ot_events
                WHERE evt_type = 'materiales' AND evt_comments LIKE :ref_tag
            ");
            $stPrev->execute([':ref_tag' => '%' . $refTag . '%']);
            $prevSupplyEvents = $stPrev->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($prevSupplyEvents)) {
                foreach ($prevSupplyEvents as $pe) {
                    // Con kardex: se revierte su documento; antes del kardex: se devuelve como se descontó.
                    $this->revertPackagingSupplyEvent($pe);
                }
                $this->erpPdo->prepare("
                    DELETE FROM prod_worker_ot_events
                    WHERE evt_type = 'materiales' AND evt_comments LIKE :ref_tag
                ")->execute([':ref_tag' => '%' . $refTag . '%']);
            }

            // 2. Eliminar unidades defectuosas / mermas asociadas
            $stDelDef = $this->erpPdo->prepare("DELETE FROM prod_worker_ot_defectunits WHERE evt_refid = :event_id");
            $stDelDef->execute([':event_id' => $eventId]);

            // 3. Eliminar el evento físicamente de la bitácora
            $stDelEvt = $this->erpPdo->prepare("DELETE FROM prod_worker_ot_events WHERE id = :event_id");
            $stDelEvt->execute([':event_id' => $eventId]);

            // 4. Sincronizar estadísticas de la agenda
            $stAg = $this->erpPdo->prepare("SELECT wok_ag_id FROM prod_worker_ot WHERE id = :ot_id");
            $stAg->execute([':ot_id' => $workerOtId]);
            $agId = (int)$stAg->fetchColumn();
            if ($agId > 0 && method_exists($this, 'syncPulpoAgendaStats')) {
                try {
                    $this->syncPulpoAgendaStats($agId);
                } catch (Throwable) {}
            }

            if ($ownsTransaction && $this->erpPdo->inTransaction()) {
                $this->erpPdo->commit();
            }

            error_log(sprintf('[PROD_DELETE] Evento de producción %d (OT %d) eliminado exitosamente por %s', $eventId, $workerOtId, $editor));

            return [
                'ok' => true,
                'message' => sprintf('Registro de producción #%d eliminado exitosamente por %s.', $eventId, $editor),
            ];
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->erpPdo->inTransaction()) {
                $this->erpPdo->rollBack();
            }
            return ['ok' => false, 'error' => 'Error al eliminar el registro de producción: ' . $e->getMessage()];
        }
    }

    /**
     * Elimina un registro de producción completo (prod_worker_ot) y sus eventos asociados.
     * Operación restringida exclusivamente a usuarios autorizados (Héctor y Javier).
     */
    public function deleteProductionRecord(int $workerOtId, ?string $userName = null): array
    {
        if ($workerOtId <= 0) {
            return ['ok' => false, 'error' => 'Identificador de registro no válido.'];
        }

        $editor = trim((string)($userName ?? 'Javier / Héctor'));

        try {
            $stmtBase = $this->erpPdo->prepare("
                SELECT t1.id, t1.wok_ag_id, h.prd_number
                FROM prod_worker_ot t1
                LEFT JOIN prod_agenda t2 ON t1.wok_ag_id = t2.id
                LEFT JOIN prod_header h ON t2.ag_prdid = h.id
                WHERE t1.id = :ot_id
            ");
            $stmtBase->execute([':ot_id' => $workerOtId]);
            $baseRec = $stmtBase->fetch(PDO::FETCH_ASSOC);
            if (!$baseRec) {
                return ['ok' => false, 'error' => 'No se encontró el registro de producción especificado o ya fue eliminado.'];
            }

            $prdNumber = trim((string)($baseRec['prd_number'] ?? ''));

            $ownsTransaction = false;
            if (!$this->erpPdo->inTransaction()) {
                $this->erpPdo->beginTransaction();
                $ownsTransaction = true;
            }

            // Obtener todos los IDs de eventos asociados
            $stEvts = $this->erpPdo->prepare("SELECT id FROM prod_worker_ot_events WHERE evt_prod_worker_otid = :ot_id");
            $stEvts->execute([':ot_id' => $workerOtId]);
            $evtIds = $stEvts->fetchAll(PDO::FETCH_COLUMN) ?: [];

            if (!empty($evtIds)) {
                $placeholders = implode(',', array_fill(0, count($evtIds), '?'));
                $stDelDef = $this->erpPdo->prepare("DELETE FROM prod_worker_ot_defectunits WHERE evt_refid IN ($placeholders)");
                $stDelDef->execute($evtIds);
            }

            // Eliminar controles de calidad asociados
            $this->erpPdo->prepare("DELETE FROM prod_worker_ot_autocontrol WHERE ctr_init_id = :ot_id")->execute([':ot_id' => $workerOtId]);

            // Eliminar eventos de producción
            $this->erpPdo->prepare("DELETE FROM prod_worker_ot_events WHERE evt_prod_worker_otid = :ot_id")->execute([':ot_id' => $workerOtId]);

            // Eliminar el registro maestro de la OT
            $this->erpPdo->prepare("DELETE FROM prod_worker_ot WHERE id = :ot_id")->execute([':ot_id' => $workerOtId]);

            if ($ownsTransaction && $this->erpPdo->inTransaction()) {
                $this->erpPdo->commit();
            }

            error_log(sprintf('[PROD_DELETE] OT %d (N° %s) eliminada exitosamente por %s', $workerOtId, $prdNumber, $editor));

            return [
                'ok' => true,
                'message' => sprintf('Registro de producción de la OT %s (ID %d) eliminado exitosamente por %s.', $prdNumber ?: '#' . $workerOtId, $workerOtId, $editor),
            ];
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->erpPdo->inTransaction()) {
                $this->erpPdo->rollBack();
            }
            return ['ok' => false, 'error' => 'Error al eliminar el registro de producción: ' . $e->getMessage()];
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

    /**
     * Kg de tinta registrados (y descontados del inventario) por color de la OT, desde Consumo de tinta.
     * La etiqueta del color es «Color N: descripción»; se suma por N.
     *
     * @return array<int,float>
     */
    public function pulpoInkByColor(int $agId): array
    {
        if ($agId <= 0 || $this->trzPdo === null) {
            return [];
        }
        try {
            $stmt = $this->trzPdo->prepare("SELECT color_label, SUM(consumed_qty) AS kg FROM work_order_machine_ink_loads
                WHERE ag_id = ? AND machine_process = 'SERIGRAFIA' GROUP BY color_label");
            $stmt->execute([$agId]);
            $byColor = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (preg_match('/^Color\s+(\d+)\s*:/i', (string)$row['color_label'], $m) === 1) {
                    $byColor[(int)$m[1]] = ($byColor[(int)$m[1]] ?? 0.0) + (float)$row['kg'];
                }
            }
            return $byColor;
        } catch (Throwable) {
            return [];
        }
    }

    public function savePulpoV2Data(int $agId, string $colorMode, array $header, array $units): bool
    {
        // La tinta de cada color sale de Consumo de tinta (lo que se descontó del inventario), no de lo que se escribe aquí:
        // antes se sumaba «carga − sobrante» al total en cada guardado y un reenvío la contaba dos veces.
        $inkByColor = $this->pulpoInkByColor($agId);
        $inkAssigned = [];
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
                $colorIdx = (int)$colorId;
                $totalTinta = 0.0;
                if ($colorIdx > 0 && !isset($inkAssigned[$colorIdx])) {
                    $totalTinta = round((float)($inkByColor[$colorIdx] ?? 0), 3);
                    $inkAssigned[$colorIdx] = true; // un color en dos unidades no se cuenta dos veces
                }

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


}

#[AllowDynamicProperties]
class ProductionEventsService
{
    use ProductionEventsServiceTrait;

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
