<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Reportes Operativos, KPIs Dashboard, Nivel de Servicio y Resumen Planta
// (src/Services/Reception/ReceptionReportingService.php)
// =============================================================================

trait ReceptionReportingServiceTrait
{
    // =========================================================================
    // Informe personal por máquina (Maquinarias)
    // =========================================================================

    /**
     * Obtiene las plantas disponibles en el ERP.
     *
     * @return list<array{id: int, planta_name: string}>
     */
    public function getPlantasList(): array
    {
        $table = $this->erpTableExists('plantas') ? 'plantas' : ($this->erpTableExists('planta') ? 'planta' : null);
        if ($table === null) {
            return [];
        }
        try {
            $nameCol = $this->erpColumnExists($table, 'planta_name') ? 'planta_name' : ($this->erpColumnExists($table, 'name') ? 'name' : null);
            if ($nameCol === null) {
                $stmt = $this->erpPdo->query('SELECT id, id AS planta_name FROM ' . $table . ' WHERE id > 0 ORDER BY id');
            } else {
                $stmt = $this->erpPdo->query('SELECT id, ' . $nameCol . ' AS planta_name FROM ' . $table . ' ORDER BY ' . $nameCol);
            }
            return $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Obtiene los tipos de equipo activos en el ERP.
     *
     * @return list<array{id: int, type_ant_title: string}>
     */
    public function getEquipoTypesList(): array
    {
        if (!$this->erpTableExists('equipo_type')) {
            return [];
        }
        try {
            $titleCol = $this->erpColumnExists('equipo_type', 'type_ant_title') ? 'type_ant_title' : ($this->erpColumnExists('equipo_type', 'type_name') ? 'type_name' : null);
            if ($titleCol === null) {
                return [];
            }
            $statusCol = $this->erpColumnExists('equipo_type', 'type_ant_status') ? 'type_ant_status' : null;
            $where = $statusCol !== null ? ('WHERE ' . $statusCol . ' > 0') : '';
            $stmt = $this->erpPdo->query('SELECT id, ' . $titleCol . ' AS type_ant_title FROM equipo_type ' . $where . ' ORDER BY ' . $titleCol);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Obtiene equipos filtrados por planta y opcionalmente por tipo.
     *
     * @return list<array{id: int, equipo_name: string}>
     */
    public function getEquiposByPlantaAndType(int $plantaId, ?int $equipoTypeId = null): array
    {
        if (!$this->erpTableExists('equipo')) {
            return [];
        }
        $nameCol = $this->erpColumnExists('equipo', 'equipo_name') ? 'equipo_name' : ($this->erpColumnExists('equipo', 'name') ? 'name' : null);
        if ($nameCol === null) {
            return [];
        }
        try {
            $sql = 'SELECT id, ' . $nameCol . ' AS equipo_name FROM equipo WHERE equipo_status > 0 AND equipo_planta_id = :planta_id';
            $params = [':planta_id' => $plantaId];
            if ($equipoTypeId !== null && $equipoTypeId > 0) {
                $sql .= ' AND equipo_type_id = :type_id';
                $params[':type_id'] = $equipoTypeId;
            }
            $sql .= ' ORDER BY ' . $nameCol;
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Genera el informe de personal por máquina.
     *
     * Consulta turnos_config_assign para obtener asignaciones de trabajadores a equipos
     * en un rango de fechas, agrupado por equipo. Incluye incidencias de personal.
     *
     * Adaptado del backup: libs/modules/stats/workers/maquina.php
     *
     * @return array{
     *   equipos: list<array{id: int, equipo_name: string}>,
     *   assignments: array<int, list<array<string, mixed>>>,
     *   incidents: array<int, array<string, array<string, mixed>>>,
     *   plantas: list<array>,
     *   equipo_types: list<array>,
     *   equipo_list: list<array>
     * }
     */
    public function getMachineStaffReport(
        string $startAt,
        string $endAt,
        ?int $plantaId = null,
        ?int $equipoTypeId = null,
        ?int $equipoId = null
    ): array {
        $result = [
            'equipos' => [],
            'assignments' => [],
            'incidents' => [],
            'plantas' => $this->getPlantasList(),
            'equipo_types' => $this->getEquipoTypesList(),
            'equipo_list' => [],
        ];

        if (!$this->erpTableExists('equipo') || !$this->erpTableExists('turnos_config_assign')) {
            return $result;
        }

        // Determinar planta por defecto
        if ($plantaId === null || $plantaId <= 0) {
            if (!empty($result['plantas'])) {
                $plantaId = (int)$result['plantas'][0]['id'];
            } else {
                return $result;
            }
        }

        // Obtener equipos disponibles para los filtros
        $result['equipo_list'] = $this->getEquiposByPlantaAndType($plantaId, $equipoTypeId);

        // Convertir fechas a timestamps UNIX (el ERP usa timestamps UNIX)
        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            return $result;
        }
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return $result;
        }

        // Obtener equipos para el informe
        $equipoNameCol = $this->erpColumnExists('equipo', 'equipo_name') ? 'equipo_name' : ($this->erpColumnExists('equipo', 'name') ? 'name' : null);
        if ($equipoNameCol === null) {
            return $result;
        }

        $equipoSql = 'SELECT id, ' . $equipoNameCol . ' AS equipo_name FROM equipo WHERE equipo_status > 0 AND equipo_planta_id = :planta_id';
        $equipoParams = [':planta_id' => $plantaId];
        if ($equipoTypeId !== null && $equipoTypeId > 0) {
            $equipoSql .= ' AND equipo_type_id = :type_id';
            $equipoParams[':type_id'] = $equipoTypeId;
        }
        if ($equipoId !== null && $equipoId > 0) {
            $equipoSql .= ' AND id = :equipo_id';
            $equipoParams[':equipo_id'] = $equipoId;
        }
        $equipoSql .= ' ORDER BY equipo_name';

        try {
            $stmt = $this->erpPdo->prepare($equipoSql);
            $stmt->execute($equipoParams);
            $equipos = $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            return $result;
        }
        $result['equipos'] = $equipos;

        if (empty($equipos)) {
            return $result;
        }

        // Consultar asignaciones de personal por máquina
        $hasWorkersTable = $this->erpTableExists('workers');
        $hasWorkersTypes = $this->erpTableExists('workers_types');
        $hasTurnos = $this->erpTableExists('turnos');
        $hasTurnosTypes = $this->erpTableExists('turnos_types');

        $selectParts = [
            't1.*',
        ];
        $joinParts = [];

        if ($hasTurnos) {
            $selectParts[] = 't3.turn_name';
            $joinParts[] = 'LEFT JOIN turnos t3 ON t1.assign_turno_id = t3.id';
        }
        if ($hasTurnosTypes) {
            $selectParts[] = 't6.type_name_short AS turno_type_short';
            $selectParts[] = 't6.type_color';
            $joinParts[] = 'LEFT JOIN turnos_types t6 ON t1.assign_turno_type_id = t6.id';
        }
        if ($hasWorkersTable) {
            $selectParts[] = 't8.wrk_firstname';
            $selectParts[] = 't8.wrk_lastname';
            $selectParts[] = 't8.wrk_rut';
            $joinParts[] = 'LEFT JOIN workers t8 ON t1.assign_worker_id = t8.id';
            if ($hasWorkersTypes && $this->erpColumnExists('workers', 'wrk_cargoid')) {
                $selectParts[] = 't9.type_name AS cargo';
                $joinParts[] = 'LEFT JOIN workers_types t9 ON t8.wrk_cargoid = t9.id';
            }
        }

        $selectSql = implode(', ', $selectParts);
        $joinSql = implode(' ', $joinParts);

        // Verificar el nombre de la columna de equipo en la asignación
        $assignEquipoCol = 'assign_equipoaid';
        if (!$this->erpColumnExists('turnos_config_assign', 'assign_equipoaid')) {
            if ($this->erpColumnExists('turnos_config_assign', 'assign_equipo_id')) {
                $assignEquipoCol = 'assign_equipo_id';
            } else {
                return $result;
            }
        }

        $hasPlantaCol = $this->erpColumnExists('turnos_config_assign', 'assign_planta_id');

        $sql = 'SELECT ' . $selectSql . '
                FROM turnos_config_assign t1
                ' . $joinSql . '
                WHERE t1.assign_stamp BETWEEN :start_ts AND :end_ts';
        $queryParams = [':start_ts' => $startTs, ':end_ts' => $endTs];

        if ($hasPlantaCol) {
            $sql .= ' AND t1.assign_planta_id = :planta_id';
            $queryParams[':planta_id'] = $plantaId;
        }

        $sql .= ' ORDER BY t1.assign_stamp, t1.ass_init_hour, t1.ass_init_min, t1.ass_end_hour, t1.ass_end_min';

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($queryParams);
            $allAssignments = $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            return $result;
        }

        // Agrupar por equipo
        $assignments = [];
        foreach ($allAssignments as $row) {
            $eqId = (int)($row[$assignEquipoCol] ?? 0);
            if ($eqId > 0) {
                $assignments[$eqId][] = $row;
            }
        }
        $result['assignments'] = $assignments;

        // Consultar incidencias de personal
        if ($this->erpTableExists('turnos_config_incidencias') && $this->erpTableExists('incidencias')) {
            try {
                $stmt = $this->erpPdo->query(
                    'SELECT t1.id, t1.cfi_startdate, t1.cfi_enddate, t1.cfi_workerid,
                            t3.inc_name_short, t3.inc_name, t3.inc_color
                     FROM turnos_config_incidencias t1
                     INNER JOIN incidencias t3 ON t1.cfi_inc_id = t3.id
                     WHERE t1.cfi_status = 2 AND t3.inc_type = 0'
                );
                $allIncidents = $stmt->fetchAll() ?: [];
            } catch (Throwable) {
                $allIncidents = [];
            }

            $incidents = [];
            foreach ($allIncidents as $inc) {
                $cfiStart = (int)($inc['cfi_startdate'] ?? 0);
                $cfiEnd = (int)($inc['cfi_enddate'] ?? 0);
                for ($x = $cfiStart + 3600; $x <= $cfiEnd; $x += 86400) {
                    if ($x >= $startTs && $x <= $endTs) {
                        $workerId = (int)($inc['cfi_workerid'] ?? 0);
                        $dateKey = date('d.m.Y', $x);
                        $incidents[$workerId][$dateKey] = $inc;
                    }
                }
            }
            $result['incidents'] = $incidents;
        }

        return $result;
    }

    /**
     * Genera el informe de producción por máquina.
     *
     * Consulta órdenes de trabajo producidas en el rango de fechas con desglose
     * por tipo de equipo/proceso (Flexografía, Corte y Sellado, Serigrafía, Pulpo,
     * Embalaje, Rebobinado, etc.) y todas las máquinas correspondientes a cada proceso.
     *
     * Adaptado y unificado de libs/modules/stats/prod/ (corteysellado.php,
     * flexo.php, serigrafia.php, pulpo-seri.php, embalaje.php).
     *
     * @return array{
     *   plantas: list<array>,
     *   equipo_types: list<array>,
     *   equipos: list<array>,
     *   processes: array<string, array{title: string, count: int, produced: float}>,
     *   active_process: string,
     *   rows: list<array<string, mixed>>,
     *   summary: array<string, mixed>
     * }
     */
    public function getMachineProductionReport(
        string $startAt,
        string $endAt,
        ?int $plantaId = null,
        ?int $equipoTypeId = null,
        ?int $equipoId = null,
        ?string $search = null,
        ?string $process = null,
        ?int $page = null,
        int $perPage = 25
    ): array {
        $process = strtolower(trim((string)($process ?? 'sellado')));
        if ($process === '') {
            $process = 'sellado';
        }

        $result = [
            'plantas' => $this->getPlantasList(),
            'equipo_types' => $this->getEquipoTypesList(),
            'equipos' => [],
            'processes' => [
                'all' => ['title' => 'Todas las Máquinas', 'count' => 0, 'produced' => 0.0],
                'sellado' => ['title' => 'Corte y Sellado', 'count' => 0, 'produced' => 0.0],
                'flexo' => ['title' => 'Flexografía', 'count' => 0, 'produced' => 0.0],
                'seri' => ['title' => 'Serigrafía', 'count' => 0, 'produced' => 0.0],
                'pulpo' => ['title' => 'Pulpo Serigráfico', 'count' => 0, 'produced' => 0.0],
                'embalaje' => ['title' => 'Embalaje', 'count' => 0, 'produced' => 0.0],
                'rebo' => ['title' => 'Rebobinado', 'count' => 0, 'produced' => 0.0],
            ],
            'active_process' => $process,
            'is_historical_search' => false,
            'search_query' => $search,
            'rows' => [],
            'summary' => [
                'total_ots' => 0,
                'total_produced_units' => 0.0,
                'total_requested_units' => 0.0,
                'total_pending_units' => 0.0,
                'total_waste_units' => 0.0,
                'total_waste_kg' => 0.0,
                'waste_percent' => null,
                'total_hours' => 0.0,
            ],
        ];

        if (!$this->erpTableExists('prod_worker_ot') || !$this->erpTableExists('prod_agenda')) {
            return $result;
        }

        // Determinar planta por defecto si aplica
        if (($plantaId === null || $plantaId <= 0) && !empty($result['plantas'])) {
            $plantaId = (int)$result['plantas'][0]['id'];
        }

        $result['equipos'] = $plantaId !== null ? $this->getEquiposByPlantaAndType($plantaId, $equipoTypeId) : [];

        // Convertir fechas a timestamps UNIX
        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            return $result;
        }
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return $result;
        }

        // Helper para construir cláusulas de búsqueda inteligente con nombres únicos de parámetros
        // para prevenir errores SQLSTATE[HY093] por placeholders duplicados en PDO nativo.
        $buildSearchCondition = static function (string $raw, array &$params): string {
            $raw = trim($raw);
            if ($raw === '') {
                return '';
            }

            $clauses = [];
            $idx = 0;

            // 1. Búsqueda directa sobre campos de texto estándar
            $idx++;
            $clauses[] = "t4.prd_number LIKE :s_ot_raw_{$idx}";
            $params[":s_ot_raw_{$idx}"] = '%' . $raw . '%';

            $clauses[] = "t9.req_number LIKE :s_cc_raw_{$idx}";
            $params[":s_cc_raw_{$idx}"] = '%' . $raw . '%';

            $clauses[] = "t12.cust_name LIKE :s_cust_raw_{$idx}";
            $params[":s_cust_raw_{$idx}"] = '%' . $raw . '%';

            $clauses[] = "t11.item_title LIKE :s_item_raw_{$idx}";
            $params[":s_item_raw_{$idx}"] = '%' . $raw . '%';

            $clauses[] = "t11.item_number_prod LIKE :s_prod_raw_{$idx}";
            $params[":s_prod_raw_{$idx}"] = '%' . $raw . '%';

            // 2. Si el usuario prefijó OT (ej. "OT 3837", "OT-3837", "OT#3837", "OT: 3837")
            $otClean = trim((string)preg_replace('/^ot[:\s\-_#]*/i', '', $raw));
            if ($otClean !== '' && $otClean !== $raw) {
                $idx++;
                $clauses[] = "t4.prd_number LIKE :s_ot_cl_{$idx}";
                $params[":s_ot_cl_{$idx}"] = '%' . $otClean . '%';
            }

            // 3. Si el usuario prefijó CC (ej. "CC 26-00808-1", "CC-808", "CC 808")
            $ccClean = trim((string)preg_replace('/^cc[:\s\-_#]*/i', '', $raw));
            if ($ccClean !== '' && $ccClean !== $raw) {
                $idx++;
                $clauses[] = "t9.req_number LIKE :s_cc_cl_{$idx}";
                $params[":s_cc_cl_{$idx}"] = '%' . $ccClean . '%';
            }

            // 4. Búsqueda numérica exacta o zero-padded para OT (003837) y CC (00808)
            if (preg_match('/^\d+$/', $raw)) {
                $num = (int)$raw;
                $idx++;
                $clauses[] = "t4.prd_number = :s_padot_{$idx}";
                $params[":s_padot_{$idx}"] = sprintf('%06d', $num);

                $idx++;
                $clauses[] = "t9.req_number LIKE :s_padcc_{$idx}";
                $params[":s_padcc_{$idx}"] = '%' . sprintf('%05d', $num) . '%';
            }

            // 5. Si viene texto mixto como "OT 3837" o "CC 808", extraer también el número puro
            $cleanNum = trim((string)preg_replace('/[^0-9]/', '', $raw));
            if ($cleanNum !== '' && $cleanNum !== $raw && strlen($cleanNum) <= 6) {
                $num2 = (int)$cleanNum;
                $idx++;
                $clauses[] = "t4.prd_number = :s_padot2_{$idx}";
                $params[":s_padot2_{$idx}"] = sprintf('%06d', $num2);

                $idx++;
                $clauses[] = "t9.req_number LIKE :s_padcc2_{$idx}";
                $params[":s_padcc2_{$idx}"] = '%' . sprintf('%05d', $num2) . '%';
            }

            return '(' . implode(' OR ', array_unique($clauses)) . ')';
        };

        // Rendimiento: las subconsultas agrupadas (eventos, mermas, supervisor) recorrían TODO el historial de
        // la tabla en cada carga. Se acotan a las OT del período (enteros ya validados, seguros de incrustar).
        $periodOtIds = 'SELECT w0.id FROM prod_worker_ot w0 WHERE w0.wok_status > 0 AND w0.wok_crtdat BETWEEN '
            . (int)$startTs . ' AND ' . (int)$endTs;

        $baseWhereClauses = [
            't1.wok_status > 0',
            't1.wok_crtdat BETWEEN :start_ts AND :end_ts',
        ];
        $baseParams = [
            ':start_ts' => $startTs,
            ':end_ts' => $endTs,
        ];

        if ($plantaId !== null && $plantaId > 0 && $this->erpColumnExists('prod_header', 'prd_plantaid')) {
            $baseWhereClauses[] = 't4.prd_plantaid = :planta_id';
            $baseParams[':planta_id'] = $plantaId;
        }

        $search = trim((string)$search);
        if ($search !== '') {
            $searchCond = $buildSearchCondition($search, $baseParams);
            if ($searchCond !== '') {
                $baseWhereClauses[] = $searchCond;
            }
        }

        // Primero calculamos conteos y totales por proceso para los tabs acotados a la Fecha de Proceso
        $whereProcessCounts = implode(' AND ', $baseWhereClauses);
        $countsParams = $baseParams;

        $countsSql = "
            SELECT 
                CASE 
                    WHEN t7.equipo_type_id = 7 THEN 'flexo'
                    WHEN t7.equipo_type_id = 8 THEN 'sellado'
                    WHEN t7.equipo_type_id = 11 AND t7.id != 36 THEN 'seri'
                    WHEN t7.equipo_type_id = 22 OR t7.id = 36 THEN 'pulpo'
                    WHEN t7.equipo_type_id = 15 THEN 'embalaje'
                    WHEN t7.equipo_type_id = 12 THEN 'rebo'
                    ELSE 'otros'
                END AS process_code,
                COUNT(DISTINCT t1.id) AS ot_count,
                COALESCE(SUM(pe.produced_units), 0) AS total_prod
            FROM prod_worker_ot t1
            INNER JOIN prod_agenda t2 ON t1.wok_ag_id = t2.id
            INNER JOIN prod_worker_init t3 ON t1.wok_init_id = t3.id
            INNER JOIN prod_header t4 ON t2.ag_prdid = t4.id
            LEFT JOIN equipo t7 ON t3.win_equipoid = t7.id
            LEFT JOIN (
                SELECT evt_prod_worker_otid, 
                       SUM(CASE WHEN LOWER(evt_type) IN ('prod', 'production', 'prodsericolor')
                                 AND (evt_status IS NULL OR evt_status > 0)
                                 AND (evt_comments IS NULL OR (evt_comments NOT LIKE '%Bobina Semielaborada%' AND evt_comments NOT LIKE '%ROLL-SE-%'))
                                THEN evt_amount ELSE 0 END) AS produced_units
                FROM prod_worker_ot_events
                WHERE (evt_status IS NULL OR evt_status > 0)
                  AND evt_prod_worker_otid IN ({$periodOtIds})
                GROUP BY evt_prod_worker_otid
            ) pe ON pe.evt_prod_worker_otid = t1.id
            WHERE {$whereProcessCounts}
            GROUP BY process_code
        ";

        $totalAllCount = 0;
        $totalAllProduced = 0.0;
        try {
            $stmtCounts = $this->erpPdo->prepare($countsSql);
            $stmtCounts->execute($countsParams);
            $countRows = $stmtCounts->fetchAll() ?: [];
            foreach ($countRows as $cr) {
                $pcode = (string)($cr['process_code'] ?? '');
                $pCount = (int)($cr['ot_count'] ?? 0);
                $pProd = (float)($cr['total_prod'] ?? 0);
                $totalAllCount += $pCount;
                $totalAllProduced += $pProd;
                if (isset($result['processes'][$pcode])) {
                    $result['processes'][$pcode]['count'] = $pCount;
                    $result['processes'][$pcode]['produced'] = $pProd;
                }
            }
            if (isset($result['processes']['all'])) {
                $result['processes']['all']['count'] = $totalAllCount;
                $result['processes']['all']['produced'] = $totalAllProduced;
            }
        } catch (Throwable) {
            // fallback: continue
        }

        // Si se buscó una OT/CC/Cliente y no arrojó resultados en el período actual (ej. OT de un mes anterior),
        // fallback automático a búsqueda histórica en toda la base de datos para no obligar a adivinar el mes
        if ($totalAllCount === 0 && $search !== '') {
            $histWhereClauses = ['t1.wok_status > 0'];
            $histParams = [];
            if ($plantaId !== null && $plantaId > 0 && $this->erpColumnExists('prod_header', 'prd_plantaid')) {
                $histWhereClauses[] = 't4.prd_plantaid = :planta_id';
                $histParams[':planta_id'] = $plantaId;
            }
            $histSearchCond = $buildSearchCondition($search, $histParams);
            if ($histSearchCond !== '') {
                $histWhereClauses[] = $histSearchCond;
            }

            $histCountsSql = "
                SELECT 
                    CASE 
                        WHEN t7.equipo_type_id = 7 THEN 'flexo'
                        WHEN t7.equipo_type_id = 8 THEN 'sellado'
                        WHEN t7.equipo_type_id = 11 AND t7.id != 36 THEN 'seri'
                        WHEN t7.equipo_type_id = 22 OR t7.id = 36 THEN 'pulpo'
                        WHEN t7.equipo_type_id = 15 THEN 'embalaje'
                        WHEN t7.equipo_type_id = 12 THEN 'rebo'
                        ELSE 'otros'
                    END AS process_code,
                    COUNT(DISTINCT t1.id) AS ot_count,
                    COALESCE(SUM(pe.produced_units), 0) AS total_prod
                FROM prod_worker_ot t1
                INNER JOIN prod_agenda t2 ON t1.wok_ag_id = t2.id
                INNER JOIN prod_worker_init t3 ON t1.wok_init_id = t3.id
                INNER JOIN prod_header t4 ON t2.ag_prdid = t4.id
                LEFT JOIN equipo t7 ON t3.win_equipoid = t7.id
                LEFT JOIN orders t9 ON t2.ag_reqid = t9.id
                LEFT JOIN orders_items t10 ON t9.id = t10.req_id
                LEFT JOIN item t11 ON t10.item_id = t11.id
                LEFT JOIN customer t12 ON t9.req_cust_id = t12.id
                INNER JOIN (
                    SELECT evt_prod_worker_otid,
                           SUM(CASE WHEN LOWER(evt_type) IN ('prod', 'production', 'prodsericolor')
                                     AND (evt_status IS NULL OR evt_status > 0)
                                     AND (evt_comments IS NULL OR (evt_comments NOT LIKE '%Bobina Semielaborada%' AND evt_comments NOT LIKE '%ROLL-SE-%'))
                                    THEN evt_amount ELSE 0 END) AS produced_units,
                           SUM(CASE WHEN LOWER(evt_type) IN ('prod', 'production', 'prodsericolor')
                                     AND (evt_status IS NULL OR evt_status > 0)
                                     AND (evt_comments IS NULL OR (evt_comments NOT LIKE '%Bobina Semielaborada%' AND evt_comments NOT LIKE '%ROLL-SE-%'))
                                    THEN evt_amount_metros_lineales ELSE 0 END) AS produced_meters,
                           SUM(CASE WHEN LOWER(evt_type) IN ('prod', 'production', 'prodsericolor')
                                     AND (evt_status IS NULL OR evt_status > 0)
                                     AND (evt_comments IS NULL OR (evt_comments NOT LIKE '%Bobina Semielaborada%' AND evt_comments NOT LIKE '%ROLL-SE-%'))
                                    THEN prod_bobina_kg ELSE 0 END) AS produced_kg
                    FROM prod_worker_ot_events
                    WHERE (evt_status IS NULL OR evt_status > 0)
                    GROUP BY evt_prod_worker_otid
                    HAVING (produced_units > 0 OR produced_meters > 0 OR produced_kg > 0)
                ) pe ON pe.evt_prod_worker_otid = t1.id
                WHERE " . implode(' AND ', $histWhereClauses) . "
                GROUP BY process_code
            ";

            try {
                $stmtHist = $this->erpPdo->prepare($histCountsSql);
                $stmtHist->execute($histParams);
                $histRows = $stmtHist->fetchAll() ?: [];
                $histAllCount = 0;
                $histAllProduced = 0.0;
                foreach ($histRows as $hr) {
                    $pcode = (string)($hr['process_code'] ?? '');
                    $pCount = (int)($hr['ot_count'] ?? 0);
                    $pProd = (float)($hr['total_prod'] ?? 0);
                    $histAllCount += $pCount;
                    $histAllProduced += $pProd;
                    if (isset($result['processes'][$pcode])) {
                        $result['processes'][$pcode]['count'] = $pCount;
                        $result['processes'][$pcode]['produced'] = $pProd;
                    }
                }
                if ($histAllCount > 0) {
                    $result['is_historical_search'] = true;
                    $baseWhereClauses = $histWhereClauses;
                    $baseParams = $histParams;
                }
            } catch (Throwable) {
                // ignore
            }
        }

        // Filtros específicos para la consulta principal
        $whereClauses = $baseWhereClauses;
        $params = $baseParams;

        // Filtro por proceso (agrupa todas las máquinas de ese proceso)
        // NOTA: Si el usuario realiza una búsqueda específica por OT/CC ($search !== '') y está en el proceso por defecto 'sellado',
        // permitimos buscar en todas las máquinas para que Héctor y Javier puedan localizar OTs producidas en Flexo, Pulpo, Seri, etc.
        if ($process !== 'all' && ($search === '' || $process !== 'sellado')) {
            switch ($process) {
                case 'flexo':
                    $whereClauses[] = 't7.equipo_type_id = 7';
                    break;
                case 'sellado':
                    $whereClauses[] = 't7.equipo_type_id = 8';
                    break;
                case 'seri':
                    $whereClauses[] = 't7.equipo_type_id = 11 AND t7.id != 36';
                    break;
                case 'pulpo':
                    $whereClauses[] = '(t7.equipo_type_id = 22 OR t7.id = 36)';
                    break;
                case 'embalaje':
                    $whereClauses[] = 't7.equipo_type_id = 15';
                    break;
                case 'rebo':
                    $whereClauses[] = 't7.equipo_type_id = 12';
                    break;
            }
        } elseif ($equipoTypeId !== null && $equipoTypeId > 0) {
            $whereClauses[] = 't7.equipo_type_id = :equipo_type_id';
            $params[':equipo_type_id'] = $equipoTypeId;
        }

        if ($equipoId !== null && $equipoId > 0) {
            $whereClauses[] = 't7.id = :equipo_id';
            $params[':equipo_id'] = $equipoId;
        }

        $whereSql = implode(' AND ', $whereClauses);

        // En búsqueda histórica (fuera del período) no se acota; en el resto, solo las OT del período.
        $evtScope = $result['is_historical_search'] ? '' : " AND e.evt_prod_worker_otid IN ({$periodOtIds})";
        $ctrlScope = $result['is_historical_search'] ? '' : " AND ac.ctr_init_id IN ({$periodOtIds})";

        $sql = "SELECT
            t1.id AS ot_id,
            t1.wok_crtdat,
            t1.wok_enddat,
            t1.wok_status,
            t3.win_wrkid AS operator_id,
            pe.ayudante_id AS helper_id,
            t4.prd_number AS ot_number,
            t9.req_number AS cc_number,
            t9.req_production_initdate AS cc_init_date,
            t12.cust_name AS customer_name,
            t11.item_number_prod AS item_code,
            t11.item_title AS item_title,
            t11.item_weight AS item_weight,
            p1.cat_title AS bag_type,
            t10.fab_med_width,
            t10.fab_med_height,
            t10.fab_med_fuelle,
            t10.fab_mat_gramms,
            t10.fab_type,
            v1.add_name AS fabric_color,
            v1.add_name_eng AS fabric_color_code,
            v2.add_name AS manilla_color,
            v2.add_name_eng AS manilla_color_code,
            t10.fab_manilla_length,
            t10.fab_mat_dispositivo,
            t10.item_sellprice_barcodenumber,
            t9.req_pie_imprenta,
            t7.id AS machine_id,
            t7.equipo_name AS machine_name,
            t7.equipo_type_id AS machine_type_id,
            t8.type_ant_title AS machine_type_title,
            CASE 
                WHEN t7.equipo_type_id = 7 THEN 'flexo'
                WHEN t7.equipo_type_id = 8 THEN 'sellado'
                WHEN t7.equipo_type_id = 11 AND t7.id != 36 THEN 'seri'
                WHEN t7.equipo_type_id = 22 OR t7.id = 36 THEN 'pulpo'
                WHEN t7.equipo_type_id = 15 THEN 'embalaje'
                WHEN t7.equipo_type_id = 12 THEN 'rebo'
                ELSE 'otros'
            END AS process_code,
            CASE 
                WHEN t7.equipo_type_id = 7 THEN 'Flexografía'
                WHEN t7.equipo_type_id = 8 THEN 'Corte y Sellado'
                WHEN t7.equipo_type_id = 11 AND t7.id != 36 THEN 'Serigrafía'
                WHEN t7.equipo_type_id = 22 OR t7.id = 36 THEN 'Pulpo Serigráfico'
                WHEN t7.equipo_type_id = 15 THEN 'Embalaje'
                WHEN t7.equipo_type_id = 12 THEN 'Rebobinado'
                ELSE COALESCE(t8.type_ant_title, 'Otros')
            END AS process_category,
            w_op.wrk_firstname AS op_first,
            w_op.wrk_lastname AS op_last,
            w_op.wrk_rut AS op_rut,
            ctrl.supervisor_name,
            ctrl.supervisor_rut,
            w_ay.wrk_firstname AS ay_first,
            w_ay.wrk_lastname AS ay_last,
            w_ay.wrk_rut AS ay_rut,
            tt.type_name AS shift_name,
            tca.ass_init_hour,
            tca.ass_init_min,
            tca.ass_end_hour,
            tca.ass_end_min,
            t2.ag_prdid AS prd_id,
            t2.id AS ag_id,
            t2.ag_amount,
            t10.item_amount,
            COALESCE(t10.item_amount, t2.ag_amount, 0) AS requested_units,
            COALESCE(pe.produced_units, 0) AS produced_units,
            COALESCE(pe.produced_meters, 0) AS produced_meters,
            COALESCE(pe.produced_meters_maquina, 0) AS produced_meters_maquina,
            COALESCE(pe.produced_kg, 0) AS produced_kg,
            COALESCE(pe.speed_m_min, 0) AS speed_m_min,
            COALESCE(pe.setup_seconds, 0) AS setup_seconds,
            COALESCE(pe.pause_seconds, 0) AS pause_seconds,
            COALESCE(dw.waste_units_raw, 0) AS waste_units_raw,
            COALESCE(dw.waste_kg, 0) AS waste_kg,
            COALESCE(dw.waste_setup_units_raw, 0) AS waste_setup_units_raw,
            COALESCE(dw.waste_setup_kg, 0) AS waste_setup_kg,
            COALESCE(dw.waste_print_units_raw, 0) AS waste_print_units_raw,
            COALESCE(dw.waste_print_kg, 0) AS waste_print_kg,
            COALESCE(dw.waste_coil_units_raw, 0) AS waste_coil_units_raw,
            COALESCE(dw.waste_coil_kg, 0) AS waste_coil_kg,
            COALESCE(dw.waste_repair_units_raw, 0) AS waste_repair_units_raw,
            COALESCE(dw.waste_repair_kg, 0) AS waste_repair_kg,
            t10.fab_print_colors_front_1,
            t10.fab_print_colors_front_2,
            t10.fab_print_colors_front_3,
            t10.fab_print_colors_front_4,
            t10.fab_print_colors_front_5,
            t10.fab_print_colors_front_6,
            t10.fab_print_colors_back_1,
            t10.fab_print_colors_back_2,
            t10.fab_print_colors_back_3,
            t10.fab_print_colors_back_4,
            t10.fab_print_colors_back_5,
            t10.fab_print_colors_back_6,
            t10.fab_print_colordesc_1,
            t10.fab_print_colordesc_2,
            t10.fab_print_colordesc_3,
            t10.fab_print_colordesc_4
        FROM prod_worker_ot t1
        INNER JOIN prod_agenda t2 ON t1.wok_ag_id = t2.id
        INNER JOIN prod_worker_init t3 ON t1.wok_init_id = t3.id
        INNER JOIN prod_header t4 ON t2.ag_prdid = t4.id
        LEFT JOIN equipo t7 ON t3.win_equipoid = t7.id
        LEFT JOIN equipo_type t8 ON t7.equipo_type_id = t8.id
        LEFT JOIN orders t9 ON t2.ag_reqid = t9.id
        LEFT JOIN orders_items t10 ON t9.id = t10.req_id
        LEFT JOIN item t11 ON t10.item_id = t11.id
        LEFT JOIN customer t12 ON t9.req_cust_id = t12.id
        LEFT JOIN item_productcats ip1 ON ip1.item_id = t11.id
        LEFT JOIN productcats p1 ON ip1.cat_id = p1.id
        LEFT JOIN tran_comments_vals v1 ON t10.fab_mat_fabric_color = v1.id
        LEFT JOIN tran_comments_vals v2 ON t10.fab_mat_manilla_color = v2.id
        LEFT JOIN workers w_op ON t3.win_wrkid = w_op.id
        LEFT JOIN (
            SELECT
                ac.ctr_init_id,
                MAX(TRIM(CONCAT(COALESCE(u.user_firstname, ''), ' ', COALESCE(u.user_lastname, '')))) AS supervisor_name,
                MAX(u.user_rut) AS supervisor_rut
            FROM prod_worker_ot_autocontrol ac
            INNER JOIN user u ON u.id = ac.ctr_ctrusr
            INNER JOIN prod_worker_ot pw_sub ON pw_sub.id = ac.ctr_init_id
            WHERE ac.ctr_type = 'supervisor'
              AND pw_sub.wok_status > 0{$ctrlScope}
            GROUP BY ac.ctr_init_id
        ) ctrl ON ctrl.ctr_init_id = t1.id
        LEFT JOIN turnos_config_assign tca ON t3.win_ass_id = tca.id
        LEFT JOIN turnos_types tt ON tca.assign_turno_type_id = tt.id
        LEFT JOIN (
            SELECT 
                e.evt_prod_worker_otid,
                SUM(CASE WHEN LOWER(e.evt_type) IN ('prod', 'production', 'prodsericolor')
                          AND (e.evt_status IS NULL OR e.evt_status > 0)
                          AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                         THEN e.evt_amount ELSE 0 END) AS produced_units,
                SUM(CASE WHEN LOWER(e.evt_type) IN ('prod', 'production', 'prodsericolor')
                          AND (e.evt_status IS NULL OR e.evt_status > 0)
                          AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                         THEN e.evt_amount_metros_lineales ELSE 0 END) AS produced_meters,
                SUM(CASE WHEN LOWER(e.evt_type) IN ('prod', 'production', 'prodsericolor')
                          AND (e.evt_status IS NULL OR e.evt_status > 0)
                          AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                         THEN e.evt_amount_metros_maquina ELSE 0 END) AS produced_meters_maquina,
                SUM(CASE WHEN LOWER(e.evt_type) IN ('prod', 'production', 'prodsericolor')
                          AND (e.evt_status IS NULL OR e.evt_status > 0)
                          AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                         THEN e.prod_bobina_kg ELSE 0 END) AS produced_kg,
                AVG(CASE WHEN LOWER(e.evt_type) IN ('prod', 'production', 'prodsericolor')
                           AND (e.evt_status IS NULL OR e.evt_status > 0)
                           AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                           AND e.evt_amount_metros_lineales > 0 AND (e.evt_enddat - e.evt_crtdat) > 0
                    THEN (e.evt_amount_metros_lineales / ((e.evt_enddat - e.evt_crtdat) / 60.0))
                    ELSE NULL END) AS speed_m_min,
                SUM(CASE WHEN LOWER(e.evt_type) = 'setup' AND (e.evt_status IS NULL OR e.evt_status > 0) AND e.evt_enddat > e.evt_crtdat THEN (e.evt_enddat - e.evt_crtdat) ELSE 0 END) AS setup_seconds,
                SUM(CASE WHEN LOWER(e.evt_type) = 'pause' AND (e.evt_status IS NULL OR e.evt_status > 0) AND e.evt_enddat > e.evt_crtdat THEN (e.evt_enddat - e.evt_crtdat) ELSE 0 END) AS pause_seconds,
                MAX(CASE WHEN e.evt_idayudante > 0 THEN e.evt_idayudante ELSE NULL END) AS ayudante_id
            FROM prod_worker_ot_events e
            WHERE (e.evt_status IS NULL OR e.evt_status > 0){$evtScope}
            GROUP BY e.evt_prod_worker_otid
        ) pe ON pe.evt_prod_worker_otid = t1.id
        LEFT JOIN workers w_ay ON pe.ayudante_id = w_ay.id
        LEFT JOIN (
            SELECT 
                e.evt_prod_worker_otid AS wok_id,
                -- Total Merma Kgs (Suma de merma Kgs excluyendo repair)
                SUM(CASE WHEN LOWER(d.evt_type) = 'merma' THEN d.evt_kgstounits ELSE 0 END) AS waste_kg,
                SUM(CASE WHEN LOWER(d.evt_type) = 'merma' THEN d.evt_amount ELSE 0 END) AS waste_units_raw,

                -- Merma Setup / Proceso / Alistamiento (ID 6 y 9 de prod_mermatypes)
                SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid IN (6, 9) THEN d.evt_kgstounits ELSE 0 END) AS waste_setup_kg,
                SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid IN (6, 9) THEN d.evt_amount ELSE 0 END) AS waste_setup_units_raw,

                -- Merma Impresión (ID 7 de prod_mermatypes)
                SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid = 7 THEN d.evt_kgstounits ELSE 0 END) AS waste_print_kg,
                SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid = 7 THEN d.evt_amount ELSE 0 END) AS waste_print_units_raw,

                -- Merma Bobina / Otros (ID 8 y cualquier otro tipo de merma)
                SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid NOT IN (6, 7, 9) THEN d.evt_kgstounits ELSE 0 END) AS waste_coil_kg,
                SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid NOT IN (6, 7, 9) THEN d.evt_amount ELSE 0 END) AS waste_coil_units_raw,

                -- Reparación (evt_type = 'repair' o evt_repair_typeid > 0)
                SUM(CASE WHEN LOWER(d.evt_type) = 'repair' OR d.evt_repair_typeid > 0 THEN d.evt_kgstounits ELSE 0 END) AS waste_repair_kg,
                SUM(CASE WHEN LOWER(d.evt_type) = 'repair' OR d.evt_repair_typeid > 0 THEN d.evt_amount ELSE 0 END) AS waste_repair_units_raw
            FROM prod_worker_ot_defectunits d
            INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
            WHERE d.evt_status > 0{$evtScope}
            GROUP BY e.evt_prod_worker_otid
        ) dw ON dw.wok_id = t1.id
        WHERE {$whereSql}
        ORDER BY process_category, t7.equipo_name, t1.wok_crtdat DESC, t1.id DESC";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            $rawRows = $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return $result;
        }

        $productionEventsByOt = [];
        // Paginación: el resumen se calcula con todas las órdenes, pero el detalle de eventos
        // (consulta pesada) solo se carga para las órdenes de la página mostrada.
        $pageOffset = 0;
        $pageInfo = null;
        $eventSourceRows = $rawRows;
        if ($page !== null) {
            $perPage = max(5, min(200, $perPage));
            $totalRawRows = count($rawRows);
            $totalPages = max(1, (int)ceil($totalRawRows / $perPage));
            $page = max(1, min($page, $totalPages));
            $pageOffset = ($page - 1) * $perPage;
            $eventSourceRows = array_slice($rawRows, $pageOffset, $perPage);
            $pageInfo = ['page' => $page, 'per_page' => $perPage, 'total' => $totalRawRows, 'pages' => $totalPages];
        }
        $reportOtIds = array_values(array_filter(array_unique(array_map(
            static fn($row) => (int)($row['ot_id'] ?? 0),
            $eventSourceRows
        ))));
        if (!empty($reportOtIds)) {
            try {
                $eventPlaceholders = implode(',', array_fill(0, count($reportOtIds), '?'));
                $sqlEvents = "
                    SELECT
                        e.id AS event_id,
                        e.evt_prod_worker_otid AS ot_id,
                        e.evt_type,
                        e.evt_amount AS produced_units,
                        e.prod_bobina_kg AS produced_kg,
                        e.evt_amount_metros_lineales AS produced_meters,
                        e.evt_amount_metros_maquina AS produced_meters_maquina,
                        e.evt_crtdat,
                        e.evt_enddat,
                        e.evt_status,
                        e.evt_idayudante AS helper_id,
                        COALESCE(e.evt_comments, '') AS comments,
                        SUM(CASE WHEN LOWER(d.evt_type) = 'merma' THEN d.evt_amount ELSE 0 END) AS waste_units,
                        SUM(CASE WHEN LOWER(d.evt_type) = 'merma' THEN d.evt_kgstounits ELSE 0 END) AS waste_kg,
                        SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid IN (6, 9) THEN d.evt_amount ELSE 0 END) AS waste_setup_units,
                        SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid IN (6, 9) THEN d.evt_kgstounits ELSE 0 END) AS waste_setup_kg,
                        SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid = 7 THEN d.evt_amount ELSE 0 END) AS waste_print_units,
                        SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid = 7 THEN d.evt_kgstounits ELSE 0 END) AS waste_print_kg,
                        SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid NOT IN (6, 7, 9) THEN d.evt_amount ELSE 0 END) AS waste_coil_units,
                        SUM(CASE WHEN LOWER(d.evt_type) = 'merma' AND d.evt_merma_typeid NOT IN (6, 7, 9) THEN d.evt_kgstounits ELSE 0 END) AS waste_coil_kg,
                        SUM(CASE WHEN LOWER(d.evt_type) = 'repair' OR d.evt_repair_typeid > 0 THEN d.evt_amount ELSE 0 END) AS waste_repair_units,
                        SUM(CASE WHEN LOWER(d.evt_type) = 'repair' OR d.evt_repair_typeid > 0 THEN d.evt_kgstounits ELSE 0 END) AS waste_repair_kg
                    FROM prod_worker_ot_events e
                    LEFT JOIN prod_worker_ot_defectunits d
                        ON d.evt_refid = e.id
                       AND d.evt_status > 0
                    WHERE e.evt_prod_worker_otid IN ($eventPlaceholders)
                      AND e.evt_status > 0
                    GROUP BY
                        e.id, e.evt_prod_worker_otid, e.evt_type, e.evt_amount, e.prod_bobina_kg,
                        e.evt_amount_metros_lineales, e.evt_amount_metros_maquina,
                        e.evt_crtdat, e.evt_enddat, e.evt_status, e.evt_idayudante, e.evt_comments
                    ORDER BY e.evt_prod_worker_otid ASC, e.evt_crtdat DESC, e.id DESC
                ";
                $stEvents = $this->erpPdo->prepare($sqlEvents);
                $stEvents->execute($reportOtIds);
                foreach ($stEvents->fetchAll(PDO::FETCH_ASSOC) ?: [] as $eventRow) {
                    $eventOtId = (int)($eventRow['ot_id'] ?? 0);
                    if ($eventOtId <= 0) {
                        continue;
                    }
                    $eventId = (int)($eventRow['event_id'] ?? 0);
                    $eventStartTs = (int)($eventRow['evt_crtdat'] ?? 0);
                    $eventEndTs = (int)($eventRow['evt_enddat'] ?? 0);
                    $rawType = strtolower(trim((string)($eventRow['evt_type'] ?? '')));
                    $typeBadge = strtoupper($rawType !== '' ? $rawType : 'EVENTO');
                    $eventUnits = (float)($eventRow['produced_units'] ?? 0);
                    $eventKg = (float)($eventRow['produced_kg'] ?? 0);
                    $eventMeters = (float)($eventRow['produced_meters'] ?? 0);
                    $cmt = trim((string)($eventRow['comments'] ?? ''));

                    $valSummary = [];
                    if ($eventUnits > 0) $valSummary[] = number_format($eventUnits, 0, ',', '.') . ' un';
                    if ($eventKg > 0) $valSummary[] = number_format($eventKg, 2, ',', '.') . ' kg';
                    if ($eventMeters > 0) $valSummary[] = number_format($eventMeters, 1, ',', '.') . ' m';
                    $valSummaryStr = !empty($valSummary) ? implode(' / ', $valSummary) : ($eventUnits == 0 && $eventKg == 0 && $eventMeters == 0 ? '0 un' : '');

                    $label = "[{$typeBadge}] #{$eventId} | "
                        . ($eventStartTs > 0 ? date('d/m H:i', $eventStartTs) : 'sin fecha')
                        . ($valSummaryStr !== '' ? (' | ' . $valSummaryStr) : '')
                        . ($cmt !== '' ? (' | ' . mb_substr($cmt, 0, 45) . (mb_strlen($cmt) > 45 ? '...' : '')) : '');

                    $productionEventsByOt[$eventOtId][] = [
                        'event_id' => $eventId,
                        'event_type' => $rawType,
                        'event_label' => $label,
                        'produced_units' => $eventUnits,
                        'produced_kg' => $eventKg,
                        'produced_meters' => $eventMeters,
                        'produced_meters_maquina' => (float)($eventRow['produced_meters_maquina'] ?? 0),
                        'started_at' => $eventStartTs > 0 ? date('Y-m-d\TH:i', $eventStartTs) : '',
                        'ended_at' => $eventEndTs > 0 ? date('Y-m-d\TH:i', $eventEndTs) : '',
                        'helper_id' => (int)($eventRow['helper_id'] ?? 0),
                        'comments' => $cmt,
                        'waste_units' => (float)($eventRow['waste_units'] ?? 0),
                        'waste_kg' => (float)($eventRow['waste_kg'] ?? 0),
                        'waste_setup_units' => (float)($eventRow['waste_setup_units'] ?? 0),
                        'waste_setup_kg' => (float)($eventRow['waste_setup_kg'] ?? 0),
                        'waste_print_units' => (float)($eventRow['waste_print_units'] ?? 0),
                        'waste_print_kg' => (float)($eventRow['waste_print_kg'] ?? 0),
                        'waste_coil_units' => (float)($eventRow['waste_coil_units'] ?? 0),
                        'waste_coil_kg' => (float)($eventRow['waste_coil_kg'] ?? 0),
                        'waste_repair_units' => (float)($eventRow['waste_repair_units'] ?? 0),
                        'waste_repair_kg' => (float)($eventRow['waste_repair_kg'] ?? 0),
                    ];
                }
            } catch (Throwable) {
                $productionEventsByOt = [];
            }
        }

        $formattedRows = [];
        $totalProduced = 0.0;
        $totalProducedKg = 0.0;
        $totalRequested = 0.0;
        $requestedByOrder = [];
        $totalWaste = 0.0;
        $totalWasteKg = 0.0;
        $totalSeconds = 0;

        // Consulta cruzada: obtener producción acumulada en períodos/meses anteriores (antes de $startTs)
        // para cada OT (prd_id) y tipo de máquina (equipo_type_id).
        // De esta forma, si una orden comercial (CC) de 70.000 bolsas ya produjo 50.000 en meses anteriores,
        // no se carga el total de 70.000 sino el saldo parcial que falta por terminar (20.000) en el período.
        $priorProducedMap = [];
        $distinctPrdIds = array_values(array_filter(array_unique(array_map(
            static fn($r) => (int)($r['prd_id'] ?? 0),
            $rawRows
        ))));

        if (!empty($distinctPrdIds) && $startTs > 0) {
            $inPlaceholders = implode(',', array_fill(0, count($distinctPrdIds), '?'));
            $sqlPrior = "
                SELECT 
                    pa.ag_prdid,
                    eq.equipo_type_id,
                    SUM(e.evt_amount) AS prior_prod
                FROM prod_worker_ot wok
                JOIN prod_agenda pa ON wok.wok_ag_id = pa.id
                JOIN prod_worker_init win ON wok.wok_init_id = win.id
                JOIN equipo eq ON win.win_equipoid = eq.id
                JOIN prod_worker_ot_events e ON e.evt_prod_worker_otid = wok.id
                WHERE pa.ag_prdid IN ($inPlaceholders)
                  AND e.evt_type IN ('prod', 'production', 'prodsericolor')
                  AND (e.evt_status IS NULL OR e.evt_status > 0)
                  AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                  AND wok.wok_crtdat < ?
                GROUP BY pa.ag_prdid, eq.equipo_type_id
            ";
            $priorParams = array_values($distinctPrdIds);
            $priorParams[] = $startTs;

            try {
                $stmtPrior = $this->erpPdo->prepare($sqlPrior);
                $stmtPrior->execute($priorParams);
                foreach ($stmtPrior->fetchAll(PDO::FETCH_ASSOC) ?: [] as $prRow) {
                    $k = ((int)$prRow['ag_prdid']) . '_' . ((int)$prRow['equipo_type_id']);
                    $priorProducedMap[$k] = (float)$prRow['prior_prod'];
                }
            } catch (Throwable) {
                $priorProducedMap = [];
            }
        }

        foreach ($rawRows as $row) {
            $startTsRow = (int)($row['wok_crtdat'] ?? 0);
            $endTsRow = (int)($row['wok_enddat'] ?? 0);
            $durationSeconds = 0;
            $durationStr = '-';
            if ($startTsRow > 0 && $endTsRow > $startTsRow) {
                $durationSeconds = $endTsRow - $startTsRow;
                $hrs = (int)floor($durationSeconds / 3600);
                $mins = (int)floor(($durationSeconds % 3600) / 60);
                $durationStr = sprintf('%02d:%02d h', $hrs, $mins);
            }

            $status = (int)($row['wok_status'] ?? 0);
            $statusLabel = 'Abierta';
            if ($endTsRow > 0) {
                $statusLabel = 'Terminada';
            } elseif ($status > 0) {
                $statusLabel = 'En Curso';
            }

            $w = (float)($row['fab_med_width'] ?? 0);
            $h = (float)($row['fab_med_height'] ?? 0);
            $f = (float)($row['fab_med_fuelle'] ?? 0);
            $gramms = (float)($row['fab_mat_gramms'] ?? 0);
            $manLen = (float)($row['fab_manilla_length'] ?? 0);
            $itemWeight = (float)($row['item_weight'] ?? 0);

            $formatCm = '';
            if ($w > 0 && $h > 0) {
                $formatCm = ((int)$w) . 'x' . ((int)$h) . ($f > 0 ? ('x' . ((int)$f)) : '') . ' cm';
            }

            $prdId = (int)($row['prd_id'] ?? 0);
            $machineTypeId = (int)($row['machine_type_id'] ?? 0);
            $priorKey = $prdId . '_' . $machineTypeId;
            $priorProducedUnits = (float)($priorProducedMap[$priorKey] ?? 0.0);

            $rawRequested = (float)($row['requested_units'] ?? 0.0);
            $totalOrderUnits = (float)($row['item_amount'] ?? 0.0);
            if ($totalOrderUnits <= 0) {
                $totalOrderUnits = (float)($row['ag_amount'] ?? $rawRequested);
            }

            // Unidades planificadas para el período: saldo parcial que falta por terminar
            // (Total CC menos lo producido en meses o días anteriores en este proceso)
            if ($totalOrderUnits > 0) {
                $reqUnits = max(0.0, $totalOrderUnits - $priorProducedUnits);
            } else {
                $reqUnits = $rawRequested;
            }

            $prodUnits = (float)($row['produced_units'] ?? 0.0);
            $prodKg = (float)($row['produced_kg'] ?? 0.0);

            // Peso unitario teórico en Kg por bolsa
            $unitWeightKg = 0.0;
            if ($itemWeight > 0) {
                $unitWeightKg = $itemWeight / 1000.0;
            } elseif ($w > 0 && $h > 0 && $gramms > 0) {
                $areaM2 = (2.0 * ($w + $f) * $h) / 10000.0;
                $bodyKg = $areaM2 * ($gramms / 1000.0);
                $manillaKg = ($manLen > 0) ? (2.0 * ($manLen / 100.0) * 0.025 * ($gramms / 1000.0)) : 0.0;
                $unitWeightKg = $bodyKg + $manillaKg;
            } elseif ($prodUnits > 0 && $prodKg > 0) {
                $unitWeightKg = $prodKg / $prodUnits;
            }

            // Kilos de merma desglosados
            $wasteKg = (float)($row['waste_kg'] ?? 0.0);
            $wasteSetupKg = (float)($row['waste_setup_kg'] ?? 0.0);
            $wastePrintKg = (float)($row['waste_print_kg'] ?? 0.0);
            $wasteCoilKg = (float)($row['waste_coil_kg'] ?? 0.0);
            $wasteRepairKg = (float)($row['waste_repair_kg'] ?? 0.0);

            // Unidades de merma calculadas según peso unitario (estándar ERP / legacy)
            if ($unitWeightKg > 0) {
                $wasteSetupUnits = (float)round($wasteSetupKg / $unitWeightKg);
                $wastePrintUnits = (float)round($wastePrintKg / $unitWeightKg);
                $wasteCoilUnits = (float)round($wasteCoilKg / $unitWeightKg);
                $wasteRepairUnits = (float)round($wasteRepairKg / $unitWeightKg);
                $wasteUnits = $wasteSetupUnits + $wastePrintUnits + $wasteCoilUnits;
            } else {
                $wasteSetupUnits = (float)($row['waste_setup_units_raw'] ?? 0.0);
                $wastePrintUnits = (float)($row['waste_print_units_raw'] ?? 0.0);
                $wasteCoilUnits = (float)($row['waste_coil_units_raw'] ?? 0.0);
                $wasteRepairUnits = (float)($row['waste_repair_units_raw'] ?? 0.0);
                $wasteUnits = (float)($row['waste_units_raw'] ?? 0.0);
            }

            $wastePct = null;
            if ($prodUnits > 0 && $wasteUnits > 0) {
                $wastePct = round(($wasteUnits / $prodUnits) * 100.0, 2);
            } elseif ($prodKg > 0 && $wasteKg > 0) {
                $wastePct = round(($wasteKg / $prodKg) * 100.0, 2);
            }

            $totalProduced += $prodUnits;
            $totalProducedKg += $prodKg;
            
            // Las unidades solicitadas corresponden al saldo planificado del pedido (OT / CC). Para no multiplicar
            // las cantidades cuando una OT es trabajada en múltiples turnos o máquinas,
            // agrupamos el valor máximo solicitado por cada OT / CC y proceso único.
            $otKey = trim((string)($row['ot_number'] ?? ''));
            $ccKey = trim((string)($row['cc_number'] ?? ''));
            $itemKey = trim((string)($row['item_code'] ?? ''));
            $orderGroupKey = ($otKey !== '' ? $otKey : '') . '|' . ($ccKey !== '' ? $ccKey : '') . '|' . $itemKey . '|' . $machineTypeId;
            if ($orderGroupKey === '|||' . $machineTypeId) {
                $orderGroupKey = 'row_' . (int)($row['ot_id'] ?? 0);
            }
            if (!isset($requestedByOrder[$orderGroupKey]) || $reqUnits > $requestedByOrder[$orderGroupKey]) {
                $requestedByOrder[$orderGroupKey] = $reqUnits;
            }

            $totalWaste += $wasteUnits;
            $totalWasteKg += $wasteKg;
            $totalSeconds += $durationSeconds;

            // Operador, Supervisor y Ayudante
            $opFirst = trim((string)($row['op_first'] ?? ''));
            $opLast = trim((string)($row['op_last'] ?? ''));
            $operatorName = trim($opFirst . ' ' . $opLast);
            if ($operatorName === '') {
                $operatorName = 'Sin operador';
            }
            $operatorRut = trim((string)($row['op_rut'] ?? ''));

            $supName = trim((string)($row['supervisor_name'] ?? ''));
            $supRut = trim((string)($row['supervisor_rut'] ?? ''));

            $ayFirst = trim((string)($row['ay_first'] ?? ''));
            $ayLast = trim((string)($row['ay_last'] ?? ''));
            $ayudanteName = trim($ayFirst . ' ' . $ayLast);
            $ayudanteRut = trim((string)($row['ay_rut'] ?? ''));

            // Turno
            $shiftName = trim((string)($row['shift_name'] ?? ''));
            $shiftHours = '';
            if (isset($row['ass_init_hour']) && $row['ass_init_hour'] !== null && $row['ass_init_hour'] !== '') {
                $shiftHours = sprintf('%02d:%02d', (int)$row['ass_init_hour'], (int)($row['ass_init_min'] ?? 0)) .
                    ' - ' . sprintf('%02d:%02d', (int)($row['ass_end_hour'] ?? 0), (int)($row['ass_end_min'] ?? 0));
            }

            // Colores frente y dorso
            $colorsFront = [];
            for ($ci = 1; $ci <= 6; $ci++) {
                $cf = trim((string)($row['fab_print_colors_front_' . $ci] ?? ''));
                if ($cf !== '' && $cf !== '0') {
                    $colorsFront[] = $cf;
                }
            }
            $colorsBack = [];
            for ($ci = 1; $ci <= 6; $ci++) {
                $cb = trim((string)($row['fab_print_colors_back_' . $ci] ?? ''));
                if ($cb !== '' && $cb !== '0') {
                    $colorsBack[] = $cb;
                }
            }

            $currentOtId = (int)($row['ot_id'] ?? 0);
            $formattedRows[] = [
                'ot_id' => (int)($row['ot_id'] ?? 0),
                'ot_number' => trim((string)($row['ot_number'] ?? '')),
                'cc_number' => trim((string)($row['cc_number'] ?? '')),
                'cc_init_date' => !empty($row['cc_init_date']) ? date('d/m/Y', (int)$row['cc_init_date']) : '',
                'customer_name' => trim((string)($row['customer_name'] ?? 'Sin cliente')),
                'item_code' => trim((string)($row['item_code'] ?? '')),
                'item_title' => trim((string)($row['item_title'] ?? '')),
                'bag_type' => trim((string)($row['bag_type'] ?? 'Bolsas')),
                'format_cm' => $formatCm,
                'width' => $w,
                'height' => $h,
                'fuelle' => $f,
                'fabric_type' => trim((string)($row['fabric_type'] ?? '')),
                'fabric_color' => trim((string)($row['fabric_color'] ?? '')),
                'fabric_color_code' => trim((string)($row['fabric_color_code'] ?? '')),
                'grammage' => (float)($row['fab_mat_gramms'] ?? 0.0),
                'manilla_color' => trim((string)($row['manilla_color'] ?? '')),
                'manilla_color_code' => trim((string)($row['manilla_color_code'] ?? '')),
                'manilla_length' => (float)($row['fab_manilla_length'] ?? 0.0),
                'barcode_number' => trim((string)($row['item_sellprice_barcodenumber'] ?? '')),
                'dispositivo' => trim((string)($row['fab_mat_dispositivo'] ?? '')),
                'pie_imprenta' => trim((string)($row['req_pie_imprenta'] ?? '')),
                'process_code' => trim((string)($row['process_code'] ?? 'otros')),
                'process_category' => trim((string)($row['process_category'] ?? 'General')),
                'machine_id' => (int)($row['machine_id'] ?? 0),
                'machine_name' => trim((string)($row['machine_name'] ?? 'Sin asignar')),
                'machine_type_id' => (int)($row['machine_type_id'] ?? 0),
                'machine_type_title' => trim((string)($row['machine_type_title'] ?? 'General')),
                'operator_name' => $operatorName,
                'operator_rut' => $operatorRut,
                'supervisor_name' => $supName,
                'supervisor_rut' => $supRut,
                'helper_name' => $ayudanteName,
                'helper_rut' => $ayudanteRut,
                'shift_name' => $shiftName,
                'shift_hours' => $shiftHours,
                'started_at' => $startTsRow > 0 ? date('d/m/Y H:i', $startTsRow) : '-',
                'ended_at' => $endTsRow > 0 ? date('d/m/Y H:i', $endTsRow) : '-',
                'duration_str' => $durationStr,
                'duration_seconds' => $durationSeconds,
                'duration_hours' => round($durationSeconds / 3600, 2),
                'setup_hours' => round((float)($row['setup_seconds'] ?? 0) / 3600, 2),
                'pause_hours' => round((float)($row['pause_seconds'] ?? 0) / 3600, 2),
                'speed_m_min' => round((float)($row['speed_m_min'] ?? 0), 1),
                'status_label' => $statusLabel,
                'requested_units' => $reqUnits,
                'total_order_units' => $totalOrderUnits,
                'prior_produced_units' => $priorProducedUnits,
                'ag_amount' => (float)($row['ag_amount'] ?? 0.0),
                'produced_units' => $prodUnits,
                'produced_meters' => (float)($row['produced_meters'] ?? 0),
                'produced_kg' => $prodKg,
                'waste_units' => $wasteUnits,
                'waste_kg' => $wasteKg,
                'waste_percent' => $wastePct,
                'waste_setup_units' => $wasteSetupUnits,
                'waste_setup_kg' => $wasteSetupKg,
                'waste_print_units' => $wastePrintUnits,
                'waste_print_kg' => $wastePrintKg,
                'waste_coil_units' => $wasteCoilUnits,
                'waste_coil_kg' => $wasteCoilKg,
                'waste_repair_units' => $wasteRepairUnits,
                'waste_repair_kg' => $wasteRepairKg,
                'unit_weight_grs' => round($unitWeightKg * 1000.0, 2),
                'colors_front' => implode(', ', $colorsFront),
                'colors_back' => implode(', ', $colorsBack),
                'wok_status' => $status,
                'wok_crtdat' => $startTsRow,
                'wok_enddat' => $endTsRow,
                'operator_id' => (int)($row['operator_id'] ?? 0),
                'helper_id' => (int)($row['helper_id'] ?? 0),
                'production_events' => array_values($productionEventsByOt[$currentOtId] ?? []),
            ];
        }

        $totalRequested = (float)array_sum($requestedByOrder);
        $totalPending = max(0.0, $totalRequested - $totalProduced);

        $result['rows'] = $pageInfo !== null ? array_slice($formattedRows, $pageOffset, $pageInfo['per_page']) : $formattedRows;
        $result['pagination'] = $pageInfo;
        $result['summary'] = [
            'total_ots' => count($formattedRows),
            'total_produced_units' => $totalProduced,
            'total_requested_units' => $totalRequested,
            'total_pending_units' => $totalPending,
            'total_waste_units' => $totalWaste,
            'total_waste_kg' => $totalWasteKg,
            'waste_percent' => ($totalProduced > 0 && $totalWaste > 0)
                ? round(($totalWaste / $totalProduced) * 100.0, 2)
                : (($totalProducedKg > 0 && $totalWasteKg > 0) ? round(($totalWasteKg / $totalProducedKg) * 100.0, 2) : null),
            'total_hours' => round($totalSeconds / 3600, 1),
        ];

        return $result;
    }

    /**
     * Obtiene la lista de máquinas activas para selección en formularios de edición.
     *
     * @return list<array{id: int, equipo_name: string, equipo_type_id: int}>
     */
    public function getAllActiveMachines(): array
    {
        if (!$this->erpTableExists('equipo')) {
            return [];
        }
        try {
            $stmt = $this->erpPdo->query("SELECT id, equipo_name, equipo_type_id FROM equipo WHERE equipo_status > 0 ORDER BY equipo_name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Obtiene la lista de trabajadores/operadores activos para selección en formularios de edición.
     *
     * @return list<array{id: int, wrk_firstname: string, wrk_lastname: string, wrk_rut: string}>
     */
    public function getAllActiveWorkers(): array
    {
        if (!$this->erpTableExists('workers')) {
            return [];
        }
        try {
            $stmt = $this->erpPdo->query("SELECT id, wrk_firstname, wrk_lastname, wrk_rut FROM workers WHERE wrk_status > 0 ORDER BY wrk_firstname ASC, wrk_lastname ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Modifica de manera profesional y segura un registro de producción por máquina en la base de datos ERP.
     *
     * Permite corregir errores de tipeo o mal ingreso en unidades, kilos, metros, fechas, máquina,
     * operador y mermas con respaldo en una transacción ACID y registro de auditoría.
     *
     * @param int $otId ID de prod_worker_ot (ot_id)
     * @param array<string, mixed> $data Datos modificados desde el formulario modal
     * @param string|null $userName Nombre del usuario que realiza el ajuste para registro de auditoría
     * @return array{ok: bool, message?: string, error?: string}
     */
    public function updateProductionRecord(int $otId, array $data, ?string $userName = null): array
    {
        if ($otId <= 0) {
            return ['ok' => false, 'error' => 'Identificador de registro no válido.'];
        }

        try {
            // 1. Obtener registro base de prod_worker_ot y prod_worker_init
            $stmtBase = $this->erpPdo->prepare("
                SELECT t1.id, t1.wok_ag_id, t1.wok_init_id, t1.wok_status, t1.wok_crtdat, t1.wok_enddat,
                       t3.win_wrkid, t3.win_equipoid
                FROM prod_worker_ot t1
                INNER JOIN prod_worker_init t3 ON t1.wok_init_id = t3.id
                WHERE t1.id = :ot_id
            ");
            $stmtBase->execute([':ot_id' => $otId]);
            $baseRec = $stmtBase->fetch(PDO::FETCH_ASSOC);
            if (!$baseRec) {
                return ['ok' => false, 'error' => 'No se encontró el registro de producción especificado.'];
            }

            $initId = (int)$baseRec['wok_init_id'];

            // 2. Parsear parámetros y valores
            $targetEventId = isset($data['target_event_id']) ? max(0, (int)$data['target_event_id']) : 0;
            $producedUnits = isset($data['produced_units']) ? max(0.0, (float)$data['produced_units']) : 0.0;
            $producedKg = isset($data['produced_kg']) ? max(0.0, (float)$data['produced_kg']) : 0.0;
            $producedMeters = isset($data['produced_meters']) ? max(0.0, (float)$data['produced_meters']) : 0.0;
            $producedMetersMaquina = isset($data['produced_meters_maquina']) ? max(0.0, (float)$data['produced_meters_maquina']) : 0.0;

            $status = isset($data['wok_status']) ? (int)$data['wok_status'] : (int)$baseRec['wok_status'];
            if ($status < 1 || $status > 2) {
                $status = 2; // Por defecto Terminada
            }

            // Fechas y marcas de tiempo
            $startTs = (int)$baseRec['wok_crtdat'];
            if (!empty($data['started_at'])) {
                try {
                    $dtStart = new DateTimeImmutable((string)$data['started_at']);
                    $startTs = $dtStart->getTimestamp();
                } catch (Throwable) {}
            }

            $endTs = (int)$baseRec['wok_enddat'];
            if (!empty($data['ended_at'])) {
                try {
                    $dtEnd = new DateTimeImmutable((string)$data['ended_at']);
                    $endTs = $dtEnd->getTimestamp();
                } catch (Throwable) {}
            } elseif ($status === 1) {
                // En curso
                $endTs = 0;
            } elseif ($endTs <= 0) {
                $endTs = $startTs > 0 ? $startTs : time();
            }

            // Recursos asignados
            $machineId = isset($data['machine_id']) && (int)$data['machine_id'] > 0 ? (int)$data['machine_id'] : (int)$baseRec['win_equipoid'];
            $operatorId = isset($data['operator_id']) && (int)$data['operator_id'] > 0 ? (int)$data['operator_id'] : (int)$baseRec['win_wrkid'];
            $helperId = isset($data['helper_id']) && (int)$data['helper_id'] > 0 ? (int)$data['helper_id'] : 0;

            // Mermas y desperdicios
            $wasteTotalKg = isset($data['waste_kg']) ? max(0.0, (float)$data['waste_kg']) : 0.0;
            $wasteTotalUnits = isset($data['waste_units']) ? max(0.0, (float)$data['waste_units']) : 0.0;

            $wasteSetupKg = isset($data['waste_setup_kg']) && is_numeric($data['waste_setup_kg']) ? max(0.0, (float)$data['waste_setup_kg']) : null;
            $wasteSetupUnits = isset($data['waste_setup_units']) && is_numeric($data['waste_setup_units']) ? max(0.0, (float)$data['waste_setup_units']) : null;
            $wastePrintKg = isset($data['waste_print_kg']) && is_numeric($data['waste_print_kg']) ? max(0.0, (float)$data['waste_print_kg']) : null;
            $wastePrintUnits = isset($data['waste_print_units']) && is_numeric($data['waste_print_units']) ? max(0.0, (float)$data['waste_print_units']) : null;
            $wasteCoilKg = isset($data['waste_coil_kg']) && is_numeric($data['waste_coil_kg']) ? max(0.0, (float)$data['waste_coil_kg']) : null;
            $wasteCoilUnits = isset($data['waste_coil_units']) && is_numeric($data['waste_coil_units']) ? max(0.0, (float)$data['waste_coil_units']) : null;
            $wasteRepairKg = isset($data['waste_repair_kg']) && is_numeric($data['waste_repair_kg']) ? max(0.0, (float)$data['waste_repair_kg']) : null;
            $wasteRepairUnits = isset($data['waste_repair_units']) && is_numeric($data['waste_repair_units']) ? max(0.0, (float)$data['waste_repair_units']) : null;

            // Comentario / auditoría
            $userComment = trim((string)($data['comments'] ?? ''));
            $editor = trim((string)($userName ?? 'Usuario'));
            $auditStamp = '[' . date('Y-m-d H:i') . ' Modificado por ' . $editor . ($userComment !== '' ? ': ' . $userComment : '') . ']';

            // 3. Iniciar Transacción ACID
            if (!$this->erpPdo->inTransaction()) {
                $this->erpPdo->beginTransaction();
            }

            // Actualizar prod_worker_ot
            $stUpdOt = $this->erpPdo->prepare("
                UPDATE prod_worker_ot 
                SET wok_status = :status, wok_crtdat = :crtdat, wok_enddat = :enddat 
                WHERE id = :ot_id
            ");
            $stUpdOt->execute([
                ':status' => $status,
                ':crtdat' => $startTs,
                ':enddat' => $endTs,
                ':ot_id' => $otId,
            ]);

            // Actualizar prod_worker_init (máquina y operador)
            $stUpdInit = $this->erpPdo->prepare("
                UPDATE prod_worker_init 
                SET win_equipoid = :machine_id, win_wrkid = :operator_id 
                WHERE id = :init_id
            ");
            $stUpdInit->execute([
                ':machine_id' => $machineId,
                ':operator_id' => $operatorId,
                ':init_id' => $initId,
            ]);

            $eventStatus = $status === 1 ? 1 : 2;

            // Consolidar o buscar el evento específico seleccionado
            $evtRow = null;
            $duplicateProdEventIds = [];
            if ($targetEventId > 0) {
                $stDirect = $this->erpPdo->prepare("
                    SELECT id, evt_comments, evt_type
                    FROM prod_worker_ot_events
                    WHERE id = :target_id AND evt_prod_worker_otid = :ot_id AND evt_status > 0
                    LIMIT 1
                ");
                $stDirect->execute([':target_id' => $targetEventId, ':ot_id' => $otId]);
                $evtRow = $stDirect->fetch(PDO::FETCH_ASSOC);
                if (!$evtRow) {
                    throw new RuntimeException('El evento seleccionado ya no existe o no pertenece a esta OT.');
                }
            } else {
                $stEvt = $this->erpPdo->prepare("
                    SELECT id, evt_comments
                    FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :ot_id
                      AND LOWER(evt_type) IN ('prod', 'production', 'prodsericolor')
                      AND evt_status > 0
                      AND (evt_comments IS NULL OR (evt_comments NOT LIKE '%Bobina Semielaborada%' AND evt_comments NOT LIKE '%ROLL-SE-%'))
                    ORDER BY
                        CASE WHEN evt_enddat > 0 THEN evt_enddat ELSE evt_crtdat END DESC,
                        id DESC
                ");
                $stEvt->execute([':ot_id' => $otId]);
                $prodEventRows = $stEvt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $evtRow = $prodEventRows[0] ?? null;
                if (count($prodEventRows) > 1) {
                    $duplicateProdEventIds = array_values(array_filter(array_map(
                        static fn(array $row): int => (int)($row['id'] ?? 0),
                        array_slice($prodEventRows, 1)
                    )));
                }
            }

            if ($evtRow) {
                $evtId = (int)$evtRow['id'];
                $existingComments = trim((string)($evtRow['evt_comments'] ?? ''));
                $newComments = $existingComments !== '' ? ($existingComments . ' | ' . $auditStamp) : $auditStamp;

                $stUpdEvt = $this->erpPdo->prepare("
                    UPDATE prod_worker_ot_events 
                    SET evt_amount = :amount,
                        prod_bobina_kg = :kg,
                        evt_amount_metros_lineales = :metros_lineales,
                        evt_amount_metros_maquina = :metros_maquina,
                        evt_crtdat = :start_ts,
                        evt_enddat = :end_ts,
                        evt_status = :evt_status,
                        evt_idayudante = :helper_id,
                        evt_comments = :comments
                    WHERE id = :evt_id
                ");
                $stUpdEvt->execute([
                    ':amount' => $producedUnits,
                    ':kg' => $producedKg,
                    ':metros_lineales' => $producedMeters,
                    ':metros_maquina' => $producedMetersMaquina,
                    ':start_ts' => $startTs,
                    ':end_ts' => $endTs,
                    ':evt_status' => $eventStatus,
                    ':helper_id' => $helperId,
                    ':comments' => $newComments,
                    ':evt_id' => $evtId,
                ]);

                if (!empty($duplicateProdEventIds)) {
                    $dupPlaceholders = implode(',', array_fill(0, count($duplicateProdEventIds), '?'));

                    $stMoveDupDef = $this->erpPdo->prepare("
                        UPDATE prod_worker_ot_defectunits
                        SET evt_refid = ?
                        WHERE evt_refid IN ({$dupPlaceholders})
                    ");
                    $stMoveDupDef->execute(array_merge([$evtId], $duplicateProdEventIds));

                    $dupAuditNote = ' | [Evento consolidado automáticamente en #' . $evtId . ' por ' . $editor . ' ' . date('Y-m-d H:i') . ']';
                    $stDisableDupEvt = $this->erpPdo->prepare("
                        UPDATE prod_worker_ot_events
                        SET evt_status = 0,
                            evt_amount = 0,
                            evt_amount2 = 0,
                            prod_bobina_kg = 0,
                            evt_amount_metros_lineales = 0,
                            evt_amount_metros_maquina = 0,
                            evt_comments = CONCAT(COALESCE(evt_comments, ''), ?)
                        WHERE id IN ({$dupPlaceholders})
                    ");
                    $stDisableDupEvt->execute(array_merge([$dupAuditNote], $duplicateProdEventIds));
                }
            } else {
                // Crear evento prod si no existía
                $stInsEvt = $this->erpPdo->prepare("
                    INSERT INTO prod_worker_ot_events (
                        evt_prod_worker_otid, evt_amount, prod_bobina_kg,
                        evt_amount_metros_lineales, evt_amount_metros_maquina,
                        evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments, evt_idayudante
                    ) VALUES (
                        :ot_id, :amount, :kg,
                        :metros_lineales, :metros_maquina,
                        :start_ts, :end_ts, :evt_status, 'prod', :comments, :helper_id
                    )
                ");
                $stInsEvt->execute([
                    ':ot_id' => $otId,
                    ':amount' => $producedUnits,
                    ':kg' => $producedKg,
                    ':metros_lineales' => $producedMeters,
                    ':metros_maquina' => $producedMetersMaquina,
                    ':start_ts' => $startTs,
                    ':end_ts' => $endTs,
                    ':evt_status' => $eventStatus,
                    ':comments' => $auditStamp,
                    ':helper_id' => $helperId,
                ]);
                $evtId = (int)$this->erpPdo->lastInsertId();
            }

            // Gestionar defectos/merma en prod_worker_ot_defectunits vinculados a esta orden
            if ($evtId > 0) {
                $stDefSql = "
                    SELECT d.id, d.evt_refid, d.evt_type, d.evt_merma_typeid, d.evt_repair_typeid, d.evt_amount, d.evt_kgstounits 
                    FROM prod_worker_ot_defectunits d
                    INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                    WHERE e.evt_prod_worker_otid = :ot_id AND d.evt_status > 0";
                $stDefParams = [':ot_id' => $otId];
                if ($targetEventId > 0) {
                    $stDefSql .= " AND e.id = :event_id";
                    $stDefParams[':event_id'] = $targetEventId;
                }
                $stDef = $this->erpPdo->prepare($stDefSql);
                $stDef->execute($stDefParams);
                $defRows = $stDef->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $hasDetailedWaste = ($wasteSetupKg !== null || $wastePrintKg !== null || $wasteCoilKg !== null || $wasteRepairKg !== null);

                if ($hasDetailedWaste) {
                    $targets = [
                        'setup' => ['type' => 'merma', 'merma_typeid' => 6, 'kg' => $wasteSetupKg ?? 0.0, 'units' => $wasteSetupUnits ?? 0.0],
                        'print' => ['type' => 'merma', 'merma_typeid' => 7, 'kg' => $wastePrintKg ?? 0.0, 'units' => $wastePrintUnits ?? 0.0],
                        'coil' => ['type' => 'merma', 'merma_typeid' => 8, 'kg' => $wasteCoilKg ?? 0.0, 'units' => $wasteCoilUnits ?? 0.0],
                        'repair' => ['type' => 'repair', 'merma_typeid' => 0, 'repair_typeid' => 1, 'kg' => $wasteRepairKg ?? 0.0, 'units' => $wasteRepairUnits ?? 0.0],
                    ];

                    foreach ($targets as $k => $t) {
                        $found = null;
                        foreach ($defRows as $dr) {
                            if ($t['type'] === 'repair' && (strtolower((string)$dr['evt_type']) === 'repair' || (int)$dr['evt_repair_typeid'] > 0)) {
                                $found = $dr;
                                break;
                            }
                            if ($t['type'] === 'merma' && (int)$dr['evt_merma_typeid'] === $t['merma_typeid']) {
                                $found = $dr;
                                break;
                            }
                            if ($k === 'setup' && in_array((int)$dr['evt_merma_typeid'], [6, 9], true)) {
                                $found = $dr;
                                break;
                            }
                        }

                        if ($found) {
                            $stUpdDef = $this->erpPdo->prepare("
                                UPDATE prod_worker_ot_defectunits 
                                SET evt_refid = :ref_id, evt_kgstounits = :kg, evt_amount = :amt 
                                WHERE id = :def_id
                            ");
                            $stUpdDef->execute([
                                ':ref_id' => $evtId,
                                ':kg' => $t['kg'],
                                ':amt' => $t['units'],
                                ':def_id' => (int)$found['id'],
                            ]);
                        } elseif ($t['kg'] > 0 || $t['units'] > 0) {
                            $stInsDef = $this->erpPdo->prepare("
                                INSERT INTO prod_worker_ot_defectunits (
                                    evt_refid, evt_type, evt_merma_typeid, evt_repair_typeid,
                                    evt_amount, evt_kgstounits, evt_mtstounits, evt_status, evt_crtdat, evt_comments
                                ) VALUES (
                                    :refid, :type, :mtype, :rtype,
                                    :amt, :kg, 0, 1, :crtdat, 'Modificado via ERP Web'
                                )
                            ");
                            $stInsDef->execute([
                                ':refid' => $evtId,
                                ':type' => $t['type'],
                                ':mtype' => $t['merma_typeid'],
                                ':rtype' => $t['repair_typeid'] ?? 0,
                                ':amt' => $t['units'],
                                ':kg' => $t['kg'],
                                ':crtdat' => $endTs > 0 ? $endTs : time(),
                            ]);
                        }
                    }
                } else {
                    // Merma total directa: actualizar el registro existente garantizando que no se duplique
                    if (!empty($defRows)) {
                        $first = true;
                        foreach ($defRows as $dr) {
                            if ($first) {
                                $stUpdDef = $this->erpPdo->prepare("
                                    UPDATE prod_worker_ot_defectunits 
                                    SET evt_refid = :ref_id, evt_kgstounits = :kg, evt_amount = :amt 
                                    WHERE id = :def_id
                                ");
                                $stUpdDef->execute([
                                    ':ref_id' => $evtId,
                                    ':kg' => $wasteTotalKg,
                                    ':amt' => $wasteTotalUnits,
                                    ':def_id' => (int)$dr['id'],
                                ]);
                                $first = false;
                            } else {
                                $stDeact = $this->erpPdo->prepare("UPDATE prod_worker_ot_defectunits SET evt_status = 0 WHERE id = :def_id");
                                $stDeact->execute([':def_id' => (int)$dr['id']]);
                            }
                        }
                    } elseif ($wasteTotalKg > 0 || $wasteTotalUnits > 0) {
                        $stInsDef = $this->erpPdo->prepare("
                            INSERT INTO prod_worker_ot_defectunits (
                                evt_refid, evt_type, evt_merma_typeid, evt_repair_typeid,
                                evt_amount, evt_kgstounits, evt_mtstounits, evt_status, evt_crtdat, evt_comments
                            ) VALUES (
                                :refid, 'merma', 6, 0,
                                :amt, :kg, 0, 1, :crtdat, 'Ingreso corrección ERP Web'
                            )
                        ");
                        $stInsDef->execute([
                            ':refid' => $evtId,
                            ':amt' => $wasteTotalUnits,
                            ':kg' => $wasteTotalKg,
                            ':crtdat' => $endTs > 0 ? $endTs : time(),
                        ]);
                    }
                }
            }

            $this->erpPdo->commit();
            return ['ok' => true, 'message' => 'Producción actualizada correctamente en la base de datos.'];
        } catch (Throwable $e) {
            if ($this->erpPdo->inTransaction()) {
                $this->erpPdo->rollBack();
            }
            return ['ok' => false, 'error' => 'Error al actualizar producción: ' . $e->getMessage()];
        }
    }

    public function deleteProductionEvent(int $otId, int $eventId, ?string $userName = null): array
    {
        if ($otId <= 0 || $eventId <= 0) {
            return ['ok' => false, 'error' => 'Evento no válido.'];
        }

        try {
            $stmtBase = $this->erpPdo->prepare("
                SELECT id, evt_prod_worker_otid, evt_type, evt_comments
                FROM prod_worker_ot_events
                WHERE id = :event_id
                  AND evt_prod_worker_otid = :ot_id
                  AND evt_status > 0
                LIMIT 1
            ");
            $stmtBase->execute([
                ':event_id' => $eventId,
                ':ot_id' => $otId,
            ]);
            $eventRow = $stmtBase->fetch(PDO::FETCH_ASSOC);
            if (!$eventRow) {
                return ['ok' => false, 'error' => 'No se encontró el evento seleccionado o ya fue anulado.'];
            }

            $editor = trim((string)($userName ?? 'Usuario'));
            $auditStamp = ' | [Evento anulado por ' . $editor . ' ' . date('Y-m-d H:i') . ']';

            if (!$this->erpPdo->inTransaction()) {
                $this->erpPdo->beginTransaction();
            }

            $stDisableDef = $this->erpPdo->prepare("
                UPDATE prod_worker_ot_defectunits
                SET evt_status = 0
                WHERE evt_refid = :event_id
            ");
            $stDisableDef->execute([':event_id' => $eventId]);

            $stDisableEvt = $this->erpPdo->prepare("
                UPDATE prod_worker_ot_events
                SET evt_status = 0,
                    evt_amount = 0,
                    evt_amount2 = 0,
                    prod_bobina_kg = 0,
                    evt_amount_metros_lineales = 0,
                    evt_amount_metros_maquina = 0,
                    evt_comments = CONCAT(COALESCE(evt_comments, ''), :audit)
                WHERE id = :event_id
                  AND evt_prod_worker_otid = :ot_id
            ");
            $stDisableEvt->execute([
                ':audit' => $auditStamp,
                ':event_id' => $eventId,
                ':ot_id' => $otId,
            ]);

            // Sincronizar agenda si aplica para que las cantidades salgan de la planificación de inmediato
            $stAg = $this->erpPdo->prepare("SELECT wok_ag_id FROM prod_worker_ot WHERE id = :ot_id");
            $stAg->execute([':ot_id' => $otId]);
            $agId = (int)$stAg->fetchColumn();
            if ($agId > 0 && method_exists($this, 'syncPulpoAgendaStats')) {
                try {
                    $this->syncPulpoAgendaStats($agId);
                } catch (Throwable) {}
            }

            $this->erpPdo->commit();

            return ['ok' => true, 'message' => 'Evento #' . $eventId . ' (' . strtoupper((string)($eventRow['evt_type'] ?? 'EVENTO')) . ') anulado correctamente. Cantidades retiradas de la planificación.'];
        } catch (Throwable $e) {
            if ($this->erpPdo->inTransaction()) {
                $this->erpPdo->rollBack();
            }
            return ['ok' => false, 'error' => 'Error al anular el evento de producción: ' . $e->getMessage()];
        }
    }

    /**
     * Elimina un registro de producción (prod_worker_ot) y sus eventos, mermas y controles asociados.
     * Operación transaccional con auditoría, permitida a usuarios autorizados (Héctor y Javier).
     *
     * @param int $otId ID del registro en prod_worker_ot
     * @param string|null $userName Nombre del usuario para registro de auditoría
     * @return array{ok: bool, message?: string, error?: string}
     */
    public function deleteProductionRecord(int $otId, ?string $userName = null): array
    {
        if ($otId <= 0) {
            return ['ok' => false, 'error' => 'Identificador de registro no válido.'];
        }

        try {
            // 1. Obtener registro base de prod_worker_ot para validar existencia y obtener info de contexto
            $stmtBase = $this->erpPdo->prepare("
                SELECT t1.id, t1.wok_ag_id, t1.wok_init_id, t1.wok_status,
                       t2.ag_equipo_id, t2.ag_prdid,
                       h.prd_number, e.equipo_name,
                       CONCAT(w.wrk_firstname, ' ', w.wrk_lastname) AS worker_name
                FROM prod_worker_ot t1
                LEFT JOIN prod_agenda t2 ON t1.wok_ag_id = t2.id
                LEFT JOIN prod_header h ON t2.ag_prdid = h.id
                LEFT JOIN prod_worker_init wi ON t1.wok_init_id = wi.id
                LEFT JOIN workers w ON wi.win_wrkid = w.id
                LEFT JOIN equipo e ON t2.ag_equipo_id = e.id
                WHERE t1.id = :ot_id
            ");
            $stmtBase->execute([':ot_id' => $otId]);
            $baseRec = $stmtBase->fetch(PDO::FETCH_ASSOC);
            if (!$baseRec) {
                return ['ok' => false, 'error' => 'No se encontró el registro de producción especificado o ya fue eliminado.'];
            }

            $prdNumber = trim((string)($baseRec['prd_number'] ?? ''));
            $editor = trim((string)($userName ?? 'Usuario'));

            // 2. Transacción ACID
            if (!$this->erpPdo->inTransaction()) {
                $this->erpPdo->beginTransaction();
            }

            // 2.1 Obtener todos los IDs de eventos asociados a esta OT
            $stEvts = $this->erpPdo->prepare("SELECT id FROM prod_worker_ot_events WHERE evt_prod_worker_otid = :ot_id");
            $stEvts->execute([':ot_id' => $otId]);
            $evtIds = $stEvts->fetchAll(PDO::FETCH_COLUMN) ?: [];

            // 2.2 Eliminar mermas/defectos de esos eventos
            if (!empty($evtIds)) {
                $placeholders = implode(',', array_fill(0, count($evtIds), '?'));
                $stDelDef = $this->erpPdo->prepare("DELETE FROM prod_worker_ot_defectunits WHERE evt_refid IN ($placeholders)");
                $stDelDef->execute($evtIds);
            }

            // 2.3 Eliminar controles de calidad (autocontrol) asociados a esta OT
            if ($this->erpTableExists('prod_worker_ot_autocontrol')) {
                $stDelAc = $this->erpPdo->prepare("DELETE FROM prod_worker_ot_autocontrol WHERE ctr_init_id = :ot_id");
                $stDelAc->execute([':ot_id' => $otId]);
            }

            // 2.4 Eliminar embalajes si existieran
            if ($this->erpTableExists('prod_worker_embalajes')) {
                $stDelEmb = $this->erpPdo->prepare("DELETE FROM prod_worker_embalajes WHERE evt_prod_worker_otid = :ot_id");
                $stDelEmb->execute([':ot_id' => $otId]);
            }

            // 2.5 Eliminar registros de clasificación de subproductos si existieran
            if ($this->erpTableExists('orders_classify_subproducts_inputweights')) {
                $stDelClas = $this->erpPdo->prepare("DELETE FROM orders_classify_subproducts_inputweights WHERE prod_worker_ot_id = :ot_id");
                $stDelClas->execute([':ot_id' => $otId]);
            }

            // 2.6 Eliminar eventos de producción
            $stDelEvt = $this->erpPdo->prepare("DELETE FROM prod_worker_ot_events WHERE evt_prod_worker_otid = :ot_id");
            $stDelEvt->execute([':ot_id' => $otId]);

            // 2.7 Eliminar el registro maestro de la OT
            $stDelOt = $this->erpPdo->prepare("DELETE FROM prod_worker_ot WHERE id = :ot_id");
            $stDelOt->execute([':ot_id' => $otId]);

            $this->erpPdo->commit();

            // Log de auditoría
            error_log(sprintf('[PROD_DELETE] OT %d (N° %s) eliminada exitosamente por %s', $otId, $prdNumber, $editor));

            return [
                'ok' => true,
                'message' => sprintf('Registro de producción de la OT %s (ID %d) eliminado exitosamente.', $prdNumber !== '' ? $prdNumber : '#' . $otId, $otId),
            ];
        } catch (Throwable $e) {
            if ($this->erpPdo->inTransaction()) {
                $this->erpPdo->rollBack();
            }
            return ['ok' => false, 'error' => 'Error al eliminar el registro de producción: ' . $e->getMessage()];
        }
    }

    /**
     * Obtiene bobinas cargadas en máquinas delegando a ProductionService.
     *
     * @return list<array<string, mixed>>
     */
    public function getLoadedRollsAcrossMachines(?int $workerOtId = null): array
    {
        $prod = new ProductionService($this->erpPdo, $this->pdo);
        return $prod->getLoadedRollsAcrossMachines($workerOtId);
    }

    /**
     * Devuelve una bobina cargada en máquina directamente a Bodega 3000 delegando a ProductionService.
     *
     * @return array{ok: bool, roll_code?: string, sku_code?: string, message?: string, error?: string}
     */
    public function returnRollToBodega3000(int $rollId, string $operatorName = '', string $reason = ''): array
    {
        $prod = new ProductionService($this->erpPdo, $this->pdo);
        return $prod->returnRollToBodega3000($rollId, $operatorName, $reason);
    }

    /**
     * Informe de Colación: pausas operativas tipo colación (pause_id = 1 o código 2200).
     * Corrige el bug del legacy que forzaba worker_id = 19 y cruzaba con ag_crtusr.
     */
    public function getMachineBreaksReport(
        string $startAt,
        string $endAt,
        ?int $plantaId = null,
        ?int $equipoTypeId = null,
        ?int $equipoId = null,
        ?string $search = null
    ): array {
        $result = [
            'plantas' => $this->getPlantasList(),
            'equipo_types' => $this->getEquipoTypesList(),
            'equipos' => [],
            'rows' => [],
            'summary' => [
                'total_records' => 0,
                'completed_records' => 0,
                'in_progress_records' => 0,
                'total_minutes' => 0,
                'total_hours' => 0.0,
                'avg_minutes' => 0.0,
            ],
        ];

        if (!$this->erpTableExists('prod_worker_ot_events') || !$this->erpTableExists('prod_worker_ot')) {
            return $result;
        }

        if (($plantaId === null || $plantaId <= 0) && !empty($result['plantas'])) {
            $plantaId = (int)$result['plantas'][0]['id'];
        }
        $result['equipos'] = $plantaId !== null ? $this->getEquiposByPlantaAndType($plantaId, $equipoTypeId) : [];

        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = strtotime($startAt) ?: 0;
            $endTs = strtotime($endAt) ?: 0;
        }

        $where = [
            "pwoe.evt_type = 'pause'",
            "(pwoe.evt_pause_id = 1 OR pwoe.evt_pause_id = '1' OR LOWER(ppt.pause_name) LIKE '%colaci%')",
            "pwoe.evt_crtdat BETWEEN :start_ts AND :end_ts",
            "pwoe.evt_status > 0",
        ];
        $params = [
            ':start_ts' => $startTs,
            ':end_ts' => $endTs,
        ];

        if ($plantaId !== null && $plantaId > 0 && $this->erpColumnExists('prod_worker_init', 'win_plantaid')) {
            $where[] = "pwi.win_plantaid = :planta_id";
            $params[':planta_id'] = $plantaId;
        }

        if ($equipoTypeId !== null && $equipoTypeId > 0) {
            $where[] = "e.equipo_type_id = :equipo_type_id";
            $params[':equipo_type_id'] = $equipoTypeId;
        }

        if ($equipoId !== null && $equipoId > 0) {
            $where[] = "e.id = :equipo_id";
            $params[':equipo_id'] = $equipoId;
        }

        if ($search !== null && trim($search) !== '') {
            $s = '%' . trim($search) . '%';
            $where[] = "(h.prd_number LIKE :s1 OR o.req_number LIKE :s2 OR c.cust_name LIKE :s3 OR w.wrk_firstname LIKE :s4 OR w.wrk_lastname LIKE :s5 OR e.equipo_name LIKE :s6 OR w.wrk_rut LIKE :s7)";
            $params[':s1'] = $s;
            $params[':s2'] = $s;
            $params[':s3'] = $s;
            $params[':s4'] = $s;
            $params[':s5'] = $s;
            $params[':s6'] = $s;
            $params[':s7'] = $s;
        }

        $sql = "
            SELECT 
                pwoe.id AS event_id,
                pwot.id AS pwo_id,
                pwot.wok_crtdat AS shift_start,
                pwot.wok_enddat AS shift_end,
                pwoe.evt_crtdat AS break_start,
                pwoe.evt_enddat AS break_end,
                pwoe.evt_comments AS break_comments,
                e.id AS machine_id,
                e.equipo_name AS machine_name,
                et.id AS machine_type_id,
                et.type_ant_title AS process_name,
                w.id AS worker_id,
                w.wrk_rut,
                CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, '')) AS worker_name,
                h.prd_number AS ot_number,
                o.req_number AS cc_number,
                c.cust_name AS customer_name
            FROM prod_worker_ot_events pwoe
            LEFT JOIN prod_pause_types ppt ON ppt.id = pwoe.evt_pause_id
            INNER JOIN prod_worker_ot pwot ON pwot.id = pwoe.evt_prod_worker_otid
            INNER JOIN prod_worker_init pwi ON pwi.id = pwot.wok_init_id
            LEFT JOIN workers w ON w.id = pwi.win_wrkid
            LEFT JOIN equipo e ON e.id = pwi.win_equipoid
            LEFT JOIN equipo_type et ON et.id = e.equipo_type_id
            LEFT JOIN prod_agenda pa ON pa.id = pwot.wok_ag_id
            LEFT JOIN prod_header h ON h.id = pa.ag_prdid
            LEFT JOIN orders o ON o.id = pa.ag_reqid
            LEFT JOIN customer c ON c.id = o.req_cust_id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY pwoe.evt_crtdat DESC
        ";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            $rawRows = $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            $rawRows = [];
        }

        $totalMinutes = 0;
        $completedCount = 0;
        $inProgressCount = 0;
        $rows = [];

        // Carga batch de turnos para evitar N+1 queries y acelerar la respuesta
        $turnoCache = [];
        $workerIds = array_values(array_unique(array_filter(array_map(static fn($r) => (int)($r['worker_id'] ?? 0), $rawRows))));
        if (!empty($workerIds)) {
            $years = [];
            foreach ($rawRows as $r) {
                $bs = (int)($r['break_start'] ?? 0);
                if ($bs > 0) {
                    $years[] = (int)date('Y', $bs);
                }
            }
            $years = array_unique($years);
            if (empty($years)) {
                $years = [(int)date('Y')];
            }
            $minY = min($years);
            $maxY = max($years);

            $inPlaceholders = implode(',', array_fill(0, count($workerIds), '?'));
            $batchSql = "
                SELECT tca.assign_worker_id, tca.assign_year, tca.assign_month, tca.assign_day,
                       tt.type_name_short, tt.type_name
                FROM turnos_config_assign tca
                INNER JOIN turnos_types tt ON tt.id = tca.assign_turno_type_id
                WHERE tca.assign_worker_id IN ($inPlaceholders)
                  AND tca.assign_year BETWEEN ? AND ?
            ";
            try {
                $bStmt = $this->erpPdo->prepare($batchSql);
                $bParams = array_merge($workerIds, [$minY, $maxY]);
                $bStmt->execute($bParams);
                while ($tRow = $bStmt->fetch()) {
                    $w = (int)$tRow['assign_worker_id'];
                    $y = (int)$tRow['assign_year'];
                    $m = (int)$tRow['assign_month'];
                    $d = (int)$tRow['assign_day'];
                    $key = "{$w}_{$y}_{$m}_{$d}";
                    $code = trim((string)($tRow['type_name_short'] ?? $tRow['type_name'] ?? 'N/D'));
                    $turnoCache[$key] = $code;
                }
            } catch (Throwable) {
                // Silencioso si falla la tabla de turnos
            }
        }

        foreach ($rawRows as $row) {
            $bStart = (int)($row['break_start'] ?? 0);
            $bEnd = (int)($row['break_end'] ?? 0);
            $wId = (int)($row['worker_id'] ?? 0);

            $diffSeconds = ($bEnd > 0 && $bEnd >= $bStart) ? ($bEnd - $bStart) : 0;
            $durationMinutes = (int)round($diffSeconds / 60);
            $hours = (int)floor($durationMinutes / 60);
            $mins = $durationMinutes % 60;
            $durationStr = $bEnd > 0 ? sprintf('%02d:%02d h', $hours, $mins) : 'En curso';
            $statusLabel = $bEnd > 0 ? 'Terminado' : 'En curso';

            if ($bEnd > 0) {
                $completedCount++;
                $totalMinutes += $durationMinutes;
            } else {
                $inProgressCount++;
            }

            // Buscar código de turno real del trabajador para ese día desde el mapa batch
            $shiftCode = 'N/D';
            if ($wId > 0 && $bStart > 0) {
                $y = (int)date('Y', $bStart);
                $m = (int)date('m', $bStart);
                $d = (int)date('d', $bStart);
                $cacheKey = "{$wId}_{$y}_{$m}_{$d}";
                if (isset($turnoCache[$cacheKey])) {
                    $shiftCode = $turnoCache[$cacheKey];
                }
            }

            $sStart = (int)($row['shift_start'] ?? 0);
            $sEnd = (int)($row['shift_end'] ?? 0);

            $rows[] = [
                'event_id' => (int)$row['event_id'],
                'shift_start' => $sStart > 0 ? date('d/m/Y H:i', $sStart) : '-',
                'shift_end' => $sEnd > 0 ? date('d/m/Y H:i', $sEnd) : '-',
                'shift_code' => $shiftCode !== '' ? $shiftCode : 'General',
                'machine_name' => trim((string)($row['machine_name'] ?? 'Sin asignar')),
                'process_name' => trim((string)($row['process_name'] ?? 'General')),
                'worker_rut' => trim((string)($row['wrk_rut'] ?? '')),
                'worker_name' => trim((string)($row['worker_name'] ?? 'Sin asignar')),
                'ot_number' => trim((string)($row['ot_number'] ?? '')),
                'cc_number' => trim((string)($row['cc_number'] ?? '')),
                'customer_name' => trim((string)($row['customer_name'] ?? '')),
                'break_start' => $bStart > 0 ? date('d/m/Y H:i', $bStart) : '-',
                'break_end' => $bEnd > 0 ? date('d/m/Y H:i', $bEnd) : '-',
                'duration_minutes' => $durationMinutes,
                'duration_str' => $durationStr,
                'status' => $statusLabel,
                'comments' => trim((string)($row['break_comments'] ?? '')),
            ];
        }

        $result['rows'] = $rows;
        $totalRecords = count($rows);
        $result['summary'] = [
            'total_records' => $totalRecords,
            'completed_records' => $completedCount,
            'in_progress_records' => $inProgressCount,
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 1),
            'avg_minutes' => $completedCount > 0 ? round($totalMinutes / $completedCount, 1) : 0.0,
        ];

        return $result;
    }

    /**
     * Informe de Detenciones: paradas de máquina distintas de colación.
     * Muestra motivo de parada, clasificación, máquina, tiempos, operario y observaciones.
     */
    public function getMachineStopsReport(
        string $startAt,
        string $endAt,
        ?int $plantaId = null,
        ?int $equipoTypeId = null,
        ?int $equipoId = null,
        ?int $pauseId = null,
        ?string $search = null
    ): array {
        $result = [
            'plantas' => $this->getPlantasList(),
            'equipo_types' => $this->getEquipoTypesList(),
            'equipos' => [],
            'pause_types' => [],
            'rows' => [],
            'summary' => [
                'total_stops' => 0,
                'total_minutes' => 0,
                'total_hours' => 0.0,
                'avg_minutes' => 0.0,
                'by_reason' => [],
            ],
        ];

        if (!$this->erpTableExists('prod_worker_ot_events') || !$this->erpTableExists('prod_worker_ot')) {
            return $result;
        }

        if (($plantaId === null || $plantaId <= 0) && !empty($result['plantas'])) {
            $plantaId = (int)$result['plantas'][0]['id'];
        }
        $result['equipos'] = $plantaId !== null ? $this->getEquiposByPlantaAndType($plantaId, $equipoTypeId) : [];

        // Obtener lista de motivos de parada para filtro
        try {
            $result['pause_types'] = $this->erpPdo->query("
                SELECT id, pause_code, pause_name 
                FROM prod_pause_types 
                WHERE pause_status > 0 AND (id != 1 AND pause_code != '2200')
                ORDER BY pause_name ASC
            ")->fetchAll() ?: [];
        } catch (Throwable) {
            $result['pause_types'] = [];
        }

        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = strtotime($startAt) ?: 0;
            $endTs = strtotime($endAt) ?: 0;
        }

        $where = [
            "pwoe.evt_type = 'pause'",
            "(pwoe.evt_pause_id != 1 AND pwoe.evt_pause_id != '1' AND (ppt.pause_code IS NULL OR ppt.pause_code != '2200'))",
            "pwoe.evt_crtdat BETWEEN :start_ts AND :end_ts",
            "pwoe.evt_status > 0",
        ];
        $params = [
            ':start_ts' => $startTs,
            ':end_ts' => $endTs,
        ];

        if ($plantaId !== null && $plantaId > 0 && $this->erpColumnExists('prod_worker_init', 'win_plantaid')) {
            $where[] = "pwi.win_plantaid = :planta_id";
            $params[':planta_id'] = $plantaId;
        }

        if ($equipoTypeId !== null && $equipoTypeId > 0) {
            $where[] = "e.equipo_type_id = :equipo_type_id";
            $params[':equipo_type_id'] = $equipoTypeId;
        }

        if ($equipoId !== null && $equipoId > 0) {
            $where[] = "e.id = :equipo_id";
            $params[':equipo_id'] = $equipoId;
        }

        if ($pauseId !== null && $pauseId > 0) {
            $where[] = "pwoe.evt_pause_id = :pause_id";
            $params[':pause_id'] = $pauseId;
        }

        if ($search !== null && trim($search) !== '') {
            $s = '%' . trim($search) . '%';
            $where[] = "(ppt.pause_name LIKE :s1 OR pwoe.evt_comments LIKE :s2 OR e.equipo_name LIKE :s3 OR w.wrk_firstname LIKE :s4 OR w.wrk_lastname LIKE :s5 OR h.prd_number LIKE :s6 OR o.req_number LIKE :s7)";
            $params[':s1'] = $s;
            $params[':s2'] = $s;
            $params[':s3'] = $s;
            $params[':s4'] = $s;
            $params[':s5'] = $s;
            $params[':s6'] = $s;
            $params[':s7'] = $s;
        }

        $sql = "
            SELECT 
                pwoe.id AS event_id,
                pwot.id AS pwo_id,
                pwoe.evt_crtdat AS stop_start,
                pwoe.evt_enddat AS stop_end,
                pwoe.evt_comments AS stop_comments,
                pwoe.evt_pause_id AS pause_id,
                COALESCE(ppt.pause_code, '') AS pause_code,
                COALESCE(ppt.pause_name, 'Detención sin motivo') AS stop_reason,
                COALESCE(p.descripcion, '') AS stop_classification,
                e.id AS machine_id,
                e.equipo_name AS machine_name,
                et.id AS machine_type_id,
                et.type_ant_title AS process_name,
                w.id AS worker_id,
                w.wrk_rut,
                CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, '')) AS worker_name,
                h.prd_number AS ot_number,
                o.req_number AS cc_number,
                c.cust_name AS customer_name
            FROM prod_worker_ot_events pwoe
            LEFT JOIN prod_pause_types ppt ON ppt.id = pwoe.evt_pause_id
            LEFT JOIN parametros p ON (p.tabla = 'CLASIFICA' AND p.codigo = ppt.pause_clasifica)
            INNER JOIN prod_worker_ot pwot ON pwot.id = pwoe.evt_prod_worker_otid
            INNER JOIN prod_worker_init pwi ON pwi.id = pwot.wok_init_id
            LEFT JOIN workers w ON w.id = pwi.win_wrkid
            LEFT JOIN equipo e ON e.id = pwi.win_equipoid
            LEFT JOIN equipo_type et ON et.id = e.equipo_type_id
            LEFT JOIN prod_agenda pa ON pa.id = pwot.wok_ag_id
            LEFT JOIN prod_header h ON h.id = pa.ag_prdid
            LEFT JOIN orders o ON o.id = pa.ag_reqid
            LEFT JOIN customer c ON c.id = o.req_cust_id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY pwoe.evt_crtdat DESC
        ";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            $rawRows = $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            $rawRows = [];
        }

        $totalMinutes = 0;
        $reasonCounts = [];
        $rows = [];

        foreach ($rawRows as $row) {
            $sStart = (int)($row['stop_start'] ?? 0);
            $sEnd = (int)($row['stop_end'] ?? 0);
            $diffSeconds = ($sEnd > 0 && $sEnd >= $sStart) ? ($sEnd - $sStart) : 0;
            $durationMinutes = (int)round($diffSeconds / 60);
            $hours = (int)floor($durationMinutes / 60);
            $mins = $durationMinutes % 60;
            $durationStr = $sEnd > 0 ? sprintf('%02d:%02d h', $hours, $mins) : 'En curso';

            $totalMinutes += $durationMinutes;
            $reason = trim((string)$row['stop_reason']);
            if (!isset($reasonCounts[$reason])) {
                $reasonCounts[$reason] = ['count' => 0, 'minutes' => 0];
            }
            $reasonCounts[$reason]['count']++;
            $reasonCounts[$reason]['minutes'] += $durationMinutes;

            $rows[] = [
                'event_id' => (int)$row['event_id'],
                'worker_name' => trim((string)($row['worker_name'] ?? 'Sin asignar')),
                'worker_rut' => trim((string)($row['wrk_rut'] ?? '')),
                'process_name' => trim((string)($row['process_name'] ?? 'General')),
                'machine_id' => (int)($row['machine_id'] ?? 0),
                'machine_name' => trim((string)($row['machine_name'] ?? 'Sin asignar')),
                'pause_code' => trim((string)$row['pause_code']),
                'stop_reason' => $reason,
                'comments' => trim((string)($row['stop_comments'] ?? '')),
                'classification' => trim((string)$row['stop_classification']),
                'date' => $sStart > 0 ? date('d/m/Y', $sStart) : '-',
                'start_time' => $sStart > 0 ? date('H:i', $sStart) : '-',
                'end_date' => $sEnd > 0 ? date('d/m/Y', $sEnd) : '-',
                'end_time' => $sEnd > 0 ? date('H:i', $sEnd) : '-',
                'duration_minutes' => $durationMinutes,
                'duration_str' => $durationStr,
                'status' => $sEnd > 0 ? 'Terminada' : 'En curso',
                'ot_number' => trim((string)($row['ot_number'] ?? '')),
                'cc_number' => trim((string)($row['cc_number'] ?? '')),
                'customer_name' => trim((string)($row['customer_name'] ?? '')),
            ];
        }

        $result['rows'] = $rows;
        $totalStops = count($rows);
        $result['summary'] = [
            'total_stops' => $totalStops,
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 1),
            'avg_minutes' => $totalStops > 0 ? round($totalMinutes / $totalStops, 1) : 0.0,
            'by_reason' => $reasonCounts,
        ];

        return $result;
    }

    /**
     * Informe de Cambio de Configuración: eventos tipo 'apertura' (setup de máquinas / cambio de medida).
     */
    public function getMachineSetupReport(
        string $startAt,
        string $endAt,
        ?int $plantaId = null,
        ?int $equipoTypeId = null,
        ?int $equipoId = null,
        ?string $search = null
    ): array {
        $result = [
            'plantas' => $this->getPlantasList(),
            'equipo_types' => $this->getEquipoTypesList(),
            'equipos' => [],
            'rows' => [],
            'summary' => [
                'total_setups' => 0,
                'total_minutes' => 0,
                'total_hours' => 0.0,
                'avg_minutes' => 0.0,
            ],
        ];

        if (!$this->erpTableExists('prod_worker_ot_events') || !$this->erpTableExists('prod_worker_ot')) {
            return $result;
        }

        if (($plantaId === null || $plantaId <= 0) && !empty($result['plantas'])) {
            $plantaId = (int)$result['plantas'][0]['id'];
        }
        $result['equipos'] = $plantaId !== null ? $this->getEquiposByPlantaAndType($plantaId, $equipoTypeId) : [];

        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = strtotime($startAt) ?: 0;
            $endTs = strtotime($endAt) ?: 0;
        }

        $where = [
            "pwoe.evt_type = 'apertura'",
            "pwoe.evt_medida_fromid != pwoe.evt_medida_toid",
            "pwoe.evt_crtdat BETWEEN :start_ts AND :end_ts",
            "pwoe.evt_status > 0",
        ];
        $params = [
            ':start_ts' => $startTs,
            ':end_ts' => $endTs,
        ];

        if ($plantaId !== null && $plantaId > 0 && $this->erpColumnExists('prod_worker_init', 'win_plantaid')) {
            $where[] = "pwi.win_plantaid = :planta_id";
            $params[':planta_id'] = $plantaId;
        }

        if ($equipoTypeId !== null && $equipoTypeId > 0) {
            $where[] = "e.equipo_type_id = :equipo_type_id";
            $params[':equipo_type_id'] = $equipoTypeId;
        }

        if ($equipoId !== null && $equipoId > 0) {
            $where[] = "e.id = :equipo_id";
            $params[':equipo_id'] = $equipoId;
        }

        if ($search !== null && trim($search) !== '') {
            $s = '%' . trim($search) . '%';
            $where[] = "(h.prd_number LIKE :s1 OR o.req_number LIKE :s2 OR c.cust_name LIKE :s3 OR w.wrk_firstname LIKE :s4 OR w.wrk_lastname LIKE :s5 OR e.equipo_name LIKE :s6)";
            $params[':s1'] = $s;
            $params[':s2'] = $s;
            $params[':s3'] = $s;
            $params[':s4'] = $s;
            $params[':s5'] = $s;
            $params[':s6'] = $s;
        }

        $sql = "
            SELECT 
                pwoe.id AS event_id,
                pwot.id AS pwo_id,
                pwoe.evt_crtdat AS setup_start,
                pwoe.evt_enddat AS setup_end,
                pwoe.evt_comments AS setup_comments,
                pwoe.evt_medida_fromid,
                pwoe.evt_medida_toid,
                pm_from.med_name AS from_size,
                pm_from.med_tipoproducto AS from_tipo,
                pm_to.med_name AS to_size,
                pm_to.med_tipoproducto AS to_tipo,
                e.id AS machine_id,
                e.equipo_name AS machine_name,
                et.id AS machine_type_id,
                et.type_ant_title AS process_name,
                w.id AS worker_id,
                w.wrk_rut,
                CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, '')) AS worker_name,
                h.prd_number AS ot_number,
                o.req_number AS cc_number,
                c.cust_name AS customer_name
            FROM prod_worker_ot_events pwoe
            INNER JOIN prod_worker_ot pwot ON pwot.id = pwoe.evt_prod_worker_otid
            INNER JOIN prod_worker_init pwi ON pwi.id = pwot.wok_init_id
            LEFT JOIN workers w ON w.id = pwi.win_wrkid
            LEFT JOIN equipo e ON e.id = pwi.win_equipoid
            LEFT JOIN equipo_type et ON et.id = e.equipo_type_id
            LEFT JOIN prod_agenda pa ON pa.id = pwot.wok_ag_id
            LEFT JOIN prod_header h ON h.id = pa.ag_prdid
            LEFT JOIN orders o ON o.id = pa.ag_reqid
            LEFT JOIN customer c ON c.id = o.req_cust_id
            LEFT JOIN prod_medidas pm_from ON pm_from.id = pwoe.evt_medida_fromid
            LEFT JOIN prod_medidas pm_to ON pm_to.id = pwoe.evt_medida_toid
            WHERE " . implode(" AND ", $where) . "
            ORDER BY pwoe.evt_crtdat DESC
        ";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            $rawRows = $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            $rawRows = [];
        }

        $totalMinutes = 0;
        $rows = [];

        foreach ($rawRows as $row) {
            $sStart = (int)($row['setup_start'] ?? 0);
            $sEnd = (int)($row['setup_end'] ?? 0);
            $diffSeconds = ($sEnd > 0 && $sEnd >= $sStart) ? ($sEnd - $sStart) : 0;
            $durationMinutes = (int)round($diffSeconds / 60);
            $hours = (int)floor($durationMinutes / 60);
            $mins = $durationMinutes % 60;
            $durationStr = $sEnd > 0 ? sprintf('%02d:%02d h', $hours, $mins) : 'En curso';

            $totalMinutes += $durationMinutes;

            $formatFrom = trim((string)($row['from_size'] ?? ''));
            $formatTo = trim((string)($row['to_size'] ?? ''));

            $ft = (int)($row['from_tipo'] ?? 0);
            $tt = (int)($row['to_tipo'] ?? 0);
            $aplica = 'NO';
            if ($ft === 3 && $tt === 3) {
                $aplica = 'SI';
            } elseif ($ft > 0 && $tt > 0 && $ft !== $tt) {
                $aplica = 'SI';
            }

            $rows[] = [
                'event_id' => (int)$row['event_id'],
                'ot_number' => trim((string)($row['ot_number'] ?? '')),
                'cc_number' => trim((string)($row['cc_number'] ?? '')),
                'customer_name' => trim((string)($row['customer_name'] ?? '')),
                'machine_name' => trim((string)($row['machine_name'] ?? 'Sin asignar')),
                'process_name' => trim((string)($row['process_name'] ?? 'General')),
                'worker_name' => trim((string)($row['worker_name'] ?? 'Sin asignar')),
                'worker_rut' => trim((string)($row['wrk_rut'] ?? '')),
                'setup_start' => $sStart > 0 ? date('d/m/Y H:i', $sStart) : '-',
                'setup_end' => $sEnd > 0 ? date('d/m/Y H:i', $sEnd) : '-',
                'duration_minutes' => $durationMinutes,
                'duration_str' => $durationStr,
                'format_from' => $formatFrom !== '' ? $formatFrom : 'Sin especif.',
                'format_to' => $formatTo !== '' ? $formatTo : 'Sin especif.',
                'is_format_change' => ((int)$row['evt_medida_fromid'] !== (int)$row['evt_medida_toid']),
                'aplica' => $aplica,
                'status' => $sEnd > 0 ? 'Terminada' : 'En curso',
                'comments' => trim((string)($row['setup_comments'] ?? '')),
            ];
        }

        $result['rows'] = $rows;
        $totalSetups = count($rows);
        $result['summary'] = [
            'total_setups' => $totalSetups,
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 1),
            'avg_minutes' => $totalSetups > 0 ? round($totalMinutes / $totalSetups, 1) : 0.0,
        ];

        return $result;
    }

    public function ensureMachineStandbySchema(): void
    {
        try {
            $this->pdo->exec("
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

    /**
     * Informe de Eventos Sin Producción: eventos de parada/standby registrados en máquinas sin OT.
     */
    public function getMachineStandbyReport(
        string $startAt,
        string $endAt,
        ?int $plantaId = null,
        ?int $equipoTypeId = null,
        ?int $equipoId = null,
        ?string $reason = null,
        ?string $search = null
    ): array {
        $this->ensureMachineStandbySchema();

        $result = [
            'plantas' => $this->getPlantasList(),
            'equipo_types' => $this->getEquipoTypesList(),
            'equipos' => [],
            'reasons' => [
                'Falta de trabajo / Sin OT',
                'Mantenimiento / Reparación',
                'Falta de insumos / sustrato',
                'Falta de personal / operador',
                'Corte de energía / Servicios',
                'Ajuste o limpieza general',
                'Otros',
            ],
            'rows' => [],
            'summary' => [
                'total_records' => 0,
                'completed_records' => 0,
                'in_progress_records' => 0,
                'total_minutes' => 0,
                'total_hours' => 0.0,
                'avg_minutes' => 0.0,
            ],
        ];

        if ($plantaId !== null && $plantaId > 0) {
            $result['equipos'] = $this->getEquiposByPlantaAndType($plantaId, $equipoTypeId);
        } else {
            $result['equipos'] = [];
        }

        $startDate = substr($startAt, 0, 10);
        $endDate = substr($endAt, 0, 10);

        $where = ["shift_date BETWEEN :start_date AND :end_date"];
        $params = [
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ];

        if ($equipoId !== null && $equipoId > 0) {
            $where[] = "machine_id = :equipo_id";
            $params[':equipo_id'] = $equipoId;
        } elseif ($plantaId !== null && $plantaId > 0 && !empty($result['equipos'])) {
            $mIds = array_filter(array_map(static fn($e) => (int)($e['id'] ?? 0), $result['equipos']));
            if (!empty($mIds)) {
                $placeholders = [];
                foreach (array_values($mIds) as $idx => $mid) {
                    $key = ":pmid_{$idx}";
                    $placeholders[] = $key;
                    $params[$key] = $mid;
                }
                $where[] = "machine_id IN (" . implode(',', $placeholders) . ")";
            }
        }

        if ($reason !== null && trim($reason) !== '') {
            $where[] = "reason = :reason";
            $params[':reason'] = trim($reason);
        }

        if ($search !== null && trim($search) !== '') {
            $s = '%' . trim($search) . '%';
            $where[] = "(machine_name LIKE :s1 OR operator_name LIKE :s2 OR reason LIKE :s3 OR comments LIKE :s4)";
            $params[':s1'] = $s;
            $params[':s2'] = $s;
            $params[':s3'] = $s;
            $params[':s4'] = $s;
        }

        $sql = "
            SELECT *
            FROM production_machine_standby_events
            WHERE " . implode(" AND ", $where) . "
            ORDER BY shift_date DESC, id DESC
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            $rawRows = [];
        }

        $now = time();
        $totalMinutes = 0;
        $completedCount = 0;
        $inProgressCount = 0;
        $rows = [];

        foreach ($rawRows as $row) {
            $isActive = (int)($row['is_active'] ?? 0) === 1;
            $startTime = trim((string)($row['start_time'] ?? ''));
            $endTime = trim((string)($row['end_time'] ?? ''));
            $shiftDate = trim((string)($row['shift_date'] ?? ''));

            $durationMinutes = (int)($row['duration_minutes'] ?? 0);
            if ($isActive) {
                $startTs = 0;
                if ($shiftDate !== '' && $startTime !== '') {
                    $startTs = strtotime("{$shiftDate} {$startTime}") ?: 0;
                }
                if ($startTs <= 0 && !empty($row['created_at'])) {
                    $startTs = (int)$row['created_at'];
                }
                if ($startTs > 0 && $now > $startTs) {
                    $durationMinutes = (int)round(($now - $startTs) / 60);
                }
                $inProgressCount++;
            } else {
                $completedCount++;
            }

            $totalMinutes += $durationMinutes;
            $hours = (int)floor($durationMinutes / 60);
            $mins = $durationMinutes % 60;
            $durationStr = $hours > 0 ? sprintf('%02d:%02d h', $hours, $mins) : sprintf('%d min', $mins);

            $rows[] = [
                'id' => (int)$row['id'],
                'date' => $shiftDate !== '' ? date('d/m/Y', strtotime($shiftDate)) : '-',
                'shift_date' => $shiftDate,
                'start_time' => $startTime !== '' ? $startTime : '-',
                'end_time' => $endTime !== '' ? $endTime : ($isActive ? 'En curso' : '-'),
                'duration_minutes' => $durationMinutes,
                'duration_str' => $isActive ? ($durationStr . ' (en curso)') : $durationStr,
                'reason' => trim((string)($row['reason'] ?? 'Sin motivo')),
                'machine_id' => (int)($row['machine_id'] ?? 0),
                'machine_name' => trim((string)($row['machine_name'] ?? 'Máquina')),
                'operator_name' => trim((string)($row['operator_name'] ?? 'Operador')),
                'is_active' => $isActive,
                'status' => $isActive ? 'En curso' : 'Terminado',
                'comments' => trim((string)($row['comments'] ?? '')),
                'init_id' => (int)($row['init_id'] ?? 0),
                'worker_id' => (int)($row['worker_id'] ?? 0),
            ];
        }

        $totalRecords = count($rows);
        $result['rows'] = $rows;
        $result['summary'] = [
            'total_records' => $totalRecords,
            'completed_records' => $completedCount,
            'in_progress_records' => $inProgressCount,
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 1),
            'avg_minutes' => $totalRecords > 0 ? round($totalMinutes / $totalRecords, 1) : 0.0,
        ];

        return $result;
    }

    /**
     * Obtiene las órdenes de trabajo (OTs) cuya tasa de merma supere el umbral especificado (por defecto > 5.0%)
     * en el período indicado, para generar alertas proactivas en el Dashboard ERP.
     */
    public function getCriticalWasteWorkOrders(string $startAt, string $endAt, float $threshold = 5.0): array
    {
        if (!$this->erpTableExists('prod_worker_ot_defectunits') || !$this->erpTableExists('prod_header')) {
            return [];
        }

        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = strtotime($startAt) ?: 0;
            $endTs = strtotime($endAt) ?: 0;
        }
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return [];
        }

        try {
            $sqlDefects = "
                SELECT 
                    d.id AS defect_id,
                    d.evt_amount,
                    d.evt_kgstounits,
                    COALESCE(pa.ag_equipo_id, pwi.win_equipoid, 0) AS machine_id,
                    eq.equipo_name,
                    et.type_ant_title AS process_name,
                    ph.prd_number AS work_order_number,
                    COALESCE(ord.req_number, ph.prd_reqid) AS cost_center,
                    COALESCE(c.cust_name, 'Cliente no asignado') AS customer_name,
                    pa.ag_amount AS requested_units,
                    t11.item_weight,
                    t10.fab_med_width,
                    t10.fab_med_height,
                    t10.fab_med_fuelle,
                    t10.fab_mat_gramms,
                    t10.fab_manilla_length
                FROM prod_worker_ot_defectunits d
                INNER JOIN prod_worker_ot_events e ON e.id = d.evt_refid
                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
                LEFT JOIN orders ord ON ord.id = pa.ag_reqid
                LEFT JOIN customer c ON c.id = ord.req_cust_id
                LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
                LEFT JOIN equipo eq ON eq.id = COALESCE(pa.ag_equipo_id, pwi.win_equipoid)
                LEFT JOIN equipo_type et ON et.id = eq.equipo_type_id
                LEFT JOIN orders_items t10 ON t10.req_id = pa.ag_reqid
                LEFT JOIN item t11 ON t11.id = t10.item_id
                WHERE d.evt_crtdat BETWEEN :start_ts AND :end_ts
                  AND LOWER(d.evt_type) = 'merma'
                ORDER BY d.id ASC
            ";

            $stmt = $this->erpPdo->prepare($sqlDefects);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $defects = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $byOtMap = [];
            foreach ($defects as $r) {
                $itemWeight = (float)($r['item_weight'] ?? 0);
                $w = (float)($r['fab_med_width'] ?? 0);
                $h = (float)($r['fab_med_height'] ?? 0);
                $f = (float)($r['fab_med_fuelle'] ?? 0);
                $gramms = (float)($r['fab_mat_gramms'] ?? 0);
                $manLen = (float)($r['fab_manilla_length'] ?? 0);

                $unitWeightKg = 0.0;
                if ($itemWeight > 0) {
                    $unitWeightKg = $itemWeight / 1000.0;
                } elseif ($w > 0 && $h > 0 && $gramms > 0) {
                    $areaM2 = (2.0 * ($w + $f) * $h) / 10000.0;
                    $bodyKg = $areaM2 * ($gramms / 1000.0);
                    $manillaKg = ($manLen > 0) ? (2.0 * ($manLen / 100.0) * 0.025 * ($gramms / 1000.0)) : 0.0;
                    $unitWeightKg = $bodyKg + $manillaKg;
                }

                $kg = (float)($r['evt_kgstounits'] ?? 0);
                $rawUnits = (float)($r['evt_amount'] ?? 0);

                if ($unitWeightKg > 0 && $kg > 0) {
                    $units = (float)round($kg / $unitWeightKg);
                } else {
                    $units = $rawUnits;
                }

                $ot = trim((string)($r['work_order_number'] ?? ''));
                if ($ot === '') {
                    continue;
                }

                if (!isset($byOtMap[$ot])) {
                    $byOtMap[$ot] = [
                        'ot_number' => $ot,
                        'cost_center' => trim((string)($r['cost_center'] ?? '')),
                        'customer_name' => trim((string)($r['customer_name'] ?? '')),
                        'process_name' => trim((string)($r['process_name'] ?? 'General')),
                        'machine_name' => trim((string)($r['equipo_name'] ?? 'Máquina')),
                        'requested_units' => (float)($r['requested_units'] ?? 0.0),
                        'good_units' => 0,
                        'waste_units' => 0,
                        'total_units' => 0,
                        'waste_rate' => 0.0,
                    ];
                }
                $byOtMap[$ot]['waste_units'] += (int)round($units);
            }

            if (empty($byOtMap)) {
                return [];
            }

            $otNumbers = array_keys($byOtMap);
            $otPlaceholders = [];
            $otParams = [':start_ts' => $startTs, ':end_ts' => $endTs];
            foreach (array_values($otNumbers) as $idx => $otNum) {
                $ph = ':ot_num_' . $idx;
                $otPlaceholders[] = $ph;
                $otParams[$ph] = $otNum;
            }
            $stmtOtProd = $this->erpPdo->prepare("
                SELECT
                    ph.prd_number,
                    COALESCE(SUM(t.produced_units), 0) AS produced_units
                FROM (
                    SELECT
                        ph2.prd_number,
                        event_date,
                        MAX(sum_units) AS produced_units
                    FROM (
                        SELECT
                            ph3.prd_number,
                            DATE(FROM_UNIXTIME(e.evt_crtdat)) AS event_date,
                            LOWER(e.evt_type) AS evt_type,
                            SUM(e.evt_amount) AS sum_units
                        FROM prod_worker_ot_events e
                        INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                        INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                        INNER JOIN prod_header ph3 ON ph3.id = pa.ag_prdid
                        WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                          AND ph3.prd_number IN (" . implode(',', $otPlaceholders) . ")
                          AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                          AND (e.evt_status IS NULL OR e.evt_status > 0)
                          AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                        GROUP BY ph3.prd_number, DATE(FROM_UNIXTIME(e.evt_crtdat)), LOWER(e.evt_type)
                    ) x
                    INNER JOIN prod_header ph2 ON ph2.prd_number = x.prd_number
                    GROUP BY ph2.prd_number, event_date
                ) t
                INNER JOIN prod_header ph ON ph.prd_number = t.prd_number
                GROUP BY ph.prd_number
            ");
            $stmtOtProd->execute($otParams);
            $prodByOt = [];
            foreach ($stmtOtProd->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $prodByOt[$row['prd_number']] = (float)($row['produced_units'] ?? 0.0);
            }

            $critical = [];
            foreach ($byOtMap as $ot => $data) {
                $prod = (float)($prodByOt[$ot] ?? 0.0);
                $waste = (float)$data['waste_units'];
                $base = $prod > 0 ? $prod : (float)$data['requested_units'];
                $rate = $base > 0 ? round(($waste / $base) * 100.0, 2) : 0.0;
                
                $data['good_units'] = (int)max(0.0, $prod - $waste);
                $data['total_units'] = (int)$prod;
                $data['waste_rate'] = $rate;

                if ($rate > $threshold && $waste > 0) {
                    $critical[] = $data;
                }
            }

            usort($critical, static fn($a, $b) => $b['waste_rate'] <=> $a['waste_rate']);
            return $critical;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Informe de Nivel de Servicio / Despachos (OTIF):
     * Basado en despacho, detalle_despacho y orders_delivery.
     */
    public function getServiceLevelReport(
        string $startAt,
        string $endAt,
        ?int $plantaId = null,
        ?string $search = null,
        ?string $delayStatus = 'all',
        ?string $delayDays = null
    ): array {
        $result = [
            'plantas' => $this->getPlantasList(),
            'rows' => [],
            'summary' => [
                'total_dispatches' => 0,
                'on_time_dispatches' => 0,
                'delayed_dispatches' => 0,
                'service_level_percent' => 0.0,
                'total_dispatched_units' => 0.0,
                'filtered_count' => 0,
                'filtered_units' => 0.0,
                'filtered_on_time' => 0,
                'filtered_delayed' => 0,
                'filtered_service_level_percent' => 0.0,
                'has_delay_filter' => false,
            ],
        ];

        if (!$this->erpTableExists('despacho') || !$this->erpTableExists('detalle_despacho')) {
            return $result;
        }

        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = strtotime($startAt) ?: 0;
            $endTs = strtotime($endAt) ?: 0;
        }

        $where = [
            "d.fecha_ingreso BETWEEN :start_ts AND :end_ts",
        ];
        $params = [
            ':start_ts' => $startTs,
            ':end_ts' => $endTs,
        ];

        if ($search !== null && trim($search) !== '') {
            $s = '%' . trim($search) . '%';
            $where[] = "(c.cust_name LIKE :s1 OR c.cust_company LIKE :s2 OR o1.req_number LIKE :s3 OR d.numero_documento LIKE :s4 OR od1.dlv_docnum LIKE :s5 OR i.item_number_prod LIKE :s6)";
            $params[':s1'] = $s;
            $params[':s2'] = $s;
            $params[':s3'] = $s;
            $params[':s4'] = $s;
            $params[':s5'] = $s;
            $params[':s6'] = $s;
        }

        $sql = "
            SELECT 
                d.id AS despacho_id,
                d.mes,
                d.año,
                d.fecha_ingreso,
                d.hora_ingreso,
                d.hora_salida,
                d.numero_documento,
                d.tipo_documento,
                d.estado,
                d.observacion,
                d.sello,
                d.cantidad_pallet,
                d.cantidad_cajas,
                COALESCE(c.cust_company, c.cust_name, 'Cliente N/D') AS customer_name,
                p.descripcion AS sales_channel,
                o1.id AS order_id,
                o1.req_number AS cc_number,
                o1.req_crtdat AS order_date,
                od1.id AS orders_delivery_id,
                od1.dlv_delivery_date AS committed_date,
                od1.dlv_docnum AS guia_factura,
                i.item_number_prod AS product_code,
                dd.salida AS dispatched_qty,
                t1.trans_name AS transport_company,
                tv.transports_vh_patente AS vehicle_plate,
                CONCAT(COALESCE(tc.transports_chofer_nombre, ''), ' ', COALESCE(tc.transports_chofer_paterno, '')) AS driver_name,
                tc.transports_chofer_rut AS driver_rut
            FROM despacho d
            INNER JOIN detalle_despacho dd ON d.id = dd.id_despacho
            INNER JOIN customer c ON c.id = d.id_cliente
            LEFT OUTER JOIN transports_chofer tc ON tc.id = d.id_chofer
            LEFT OUTER JOIN transports_vehiculo tv ON tv.id = d.id_patente
            LEFT OUTER JOIN transports t1 ON t1.id = d.id_transporte
            LEFT OUTER JOIN parametros p ON p.tabla = 'CANAL' AND p.codigo = c.cust_canal
            LEFT OUTER JOIN item i ON i.id = dd.id_item
            LEFT OUTER JOIN orders_delivery od1 ON d.numero_documento = od1.id
            LEFT OUTER JOIN orders o1 ON o1.id = od1.dlv_order_id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY d.fecha_ingreso DESC
        ";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            $rawRows = $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            $rawRows = [];
        }

        // Cargar ajustes manuales registrados (acuerdos con clientes, falta de pago, etc.)
        $adjustments = [];
        try {
            $adjStmt = $this->pdo->query("SELECT * FROM service_level_adjustments");
            while ($adjRow = $adjStmt->fetch(PDO::FETCH_ASSOC)) {
                $adjustments[(int)$adjRow['despacho_id']] = $adjRow;
            }
        } catch (Throwable) {
            $adjustments = [];
        }

        $totalDispatches = 0;
        $onTimeCount = 0;
        $delayedCount = 0;
        $totalDisp = 0.0;
        $allRows = [];

        foreach ($rawRows as $row) {
            $despachoId = (int)$row['despacho_id'];
            $ordersDeliveryId = !empty($row['orders_delivery_id']) ? (int)$row['orders_delivery_id'] : null;
            $fIngreso = (int)($row['fecha_ingreso'] ?? 0);
            $committed = (int)($row['committed_date'] ?? 0);
            $dispUnits = (float)($row['dispatched_qty'] ?? 0);

            $hasAdj = isset($adjustments[$despachoId]);
            $adj = $hasAdj ? $adjustments[$despachoId] : null;

            $origCommitted = $committed;
            $isJustified = false;
            $reasonCategory = '';
            $reasonDetails = '';
            $updatedBy = '';
            $updatedAt = '';
            $adjCommittedTs = null;

            if ($hasAdj) {
                $isJustified = !empty($adj['is_justified']);
                $reasonCategory = trim((string)($adj['reason_category'] ?? ''));
                $reasonDetails = trim((string)($adj['reason_details'] ?? ''));
                $updatedBy = trim((string)($adj['updated_by'] ?? ''));
                $updatedAt = trim((string)($adj['updated_at'] ?? ''));
                if (!empty($adj['adjusted_committed_date'])) {
                    $adjCommittedTs = (int)$adj['adjusted_committed_date'];
                    $committed = $adjCommittedTs;
                }
            }

            $isOnTime = true;
            $rowDelayDays = 0;
            if ($isJustified) {
                $isOnTime = true;
                $rowDelayDays = 0;
            } elseif ($committed > 0 && $fIngreso > 0) {
                // Si la fecha de despacho fue posterior a la fecha comprometida (fin de día)
                if ($fIngreso > ($committed + 86399)) {
                    $isOnTime = false;
                    $rowDelayDays = (int)ceil(($fIngreso - $committed) / 86400);
                }
            }

            if ($isOnTime) {
                $onTimeCount++;
            } else {
                $delayedCount++;
            }

            $totalDispatches++;
            $totalDisp += $dispUnits;

            $statusLabel = $isOnTime 
                ? ($hasAdj && $isJustified ? 'A tiempo (Justificado)' : ($hasAdj ? 'A tiempo (Acuerdo)' : 'A tiempo'))
                : ($hasAdj ? "Atraso {$rowDelayDays}d (Reprog.)" : "Atraso {$rowDelayDays}d");

            $allRows[] = [
                'despacho_id' => $despachoId,
                'orders_delivery_id' => $ordersDeliveryId,
                'date' => $fIngreso > 0 ? date('d/m/Y', $fIngreso) : '-',
                'raw_date' => $fIngreso > 0 ? date('Y-m-d', $fIngreso) : '',
                'entry_time' => trim((string)($row['hora_ingreso'] ?? '')),
                'exit_time' => trim((string)($row['hora_salida'] ?? '')),
                'doc_type' => trim((string)($row['tipo_documento'] ?? 'Despacho')),
                'doc_number' => trim((string)($row['guia_factura'] ?? $row['numero_documento'] ?? '')),
                'customer_name' => trim((string)$row['customer_name']),
                'sales_channel' => trim((string)($row['sales_channel'] ?? 'General')),
                'cc_number' => trim((string)($row['cc_number'] ?? '')),
                'product_code' => trim((string)($row['product_code'] ?? '')),
                'committed_date' => $committed > 0 ? date('d/m/Y', $committed) : 'Sin fecha',
                'raw_committed_date' => $committed > 0 ? date('Y-m-d', $committed) : '',
                'original_committed_date' => $origCommitted > 0 ? date('d/m/Y', $origCommitted) : 'Sin fecha',
                'raw_orig_committed_date' => $origCommitted > 0 ? date('Y-m-d', $origCommitted) : '',
                'is_on_time' => $isOnTime,
                'delay_days' => $rowDelayDays,
                'status_label' => $statusLabel,
                'has_adjustment' => $hasAdj,
                'is_justified' => $isJustified,
                'reason_category' => $reasonCategory,
                'reason_details' => $reasonDetails,
                'adjusted_committed_date' => $adjCommittedTs ? date('d/m/Y', $adjCommittedTs) : null,
                'raw_adjusted_date' => $adjCommittedTs ? date('Y-m-d', $adjCommittedTs) : null,
                'updated_by' => $updatedBy,
                'updated_at' => $updatedAt,
                'dispatched_units' => $dispUnits,
                'pallets' => (int)($row['cantidad_pallet'] ?? 0),
                'boxes' => (int)($row['cantidad_cajas'] ?? 0),
                'transport_company' => trim((string)($row['transport_company'] ?? '')),
                'vehicle_plate' => trim((string)($row['vehicle_plate'] ?? '')),
                'driver_name' => trim((string)($row['driver_name'] ?? '')),
                'driver_rut' => trim((string)($row['driver_rut'] ?? '')),
                'seal_number' => trim((string)($row['sello'] ?? '')),
                'observation' => trim((string)($row['observacion'] ?? '')),
            ];
        }

        // Filtro por Estado de Atraso y Cantidad de Días de Atraso
        $normalizedDelayStatus = strtolower(trim((string)($delayStatus ?? 'all')));
        if ($normalizedDelayStatus === '') {
            $normalizedDelayStatus = 'all';
        }
        $normalizedDelayDays = trim((string)($delayDays ?? ''));

        $hasDelayFilter = ($normalizedDelayStatus !== 'all' || ($normalizedDelayDays !== '' && $normalizedDelayDays !== 'all'));

        $filteredRows = [];
        $filteredUnits = 0.0;
        $filteredOnTime = 0;
        $filteredDelayed = 0;

        foreach ($allRows as $r) {
            $isOnTime = (bool)$r['is_on_time'];
            $days = (int)$r['delay_days'];

            // Filtro por Estado de Entrega (a tiempo vs con atraso)
            if ($normalizedDelayStatus === 'on_time' && !$isOnTime) {
                continue;
            }
            if ($normalizedDelayStatus === 'delayed' && $isOnTime) {
                continue;
            }

            // Filtro por Cantidad de Días de Atraso
            if ($normalizedDelayDays !== '' && $normalizedDelayDays !== 'all') {
                if ($isOnTime) {
                    continue;
                }
                if ($normalizedDelayDays === '1-3' && !($days >= 1 && $days <= 3)) {
                    continue;
                }
                if ($normalizedDelayDays === '4-7' && !($days >= 4 && $days <= 7)) {
                    continue;
                }
                if ($normalizedDelayDays === '8-14' && !($days >= 8 && $days <= 14)) {
                    continue;
                }
                if ($normalizedDelayDays === '15+' && !($days >= 15)) {
                    continue;
                }
                if (str_starts_with($normalizedDelayDays, 'min_')) {
                    $minVal = (int)substr($normalizedDelayDays, 4);
                    if ($days < $minVal) {
                        continue;
                    }
                } elseif (is_numeric($normalizedDelayDays)) {
                    if ($days < (int)$normalizedDelayDays) {
                        continue;
                    }
                }
            }

            $filteredRows[] = $r;
            $filteredUnits += (float)$r['dispatched_units'];
            if ($isOnTime) {
                $filteredOnTime++;
            } else {
                $filteredDelayed++;
            }
        }

        $filteredCount = count($filteredRows);
        $result['rows'] = $filteredRows;
        $result['summary'] = [
            'total_dispatches' => $totalDispatches,
            'on_time_dispatches' => $onTimeCount,
            'delayed_dispatches' => $delayedCount,
            'service_level_percent' => $totalDispatches > 0 ? round(($onTimeCount / $totalDispatches) * 100.0, 2) : 100.0,
            'total_dispatched_units' => $totalDisp,
            'filtered_count' => $filteredCount,
            'filtered_units' => $filteredUnits,
            'filtered_on_time' => $filteredOnTime,
            'filtered_delayed' => $filteredDelayed,
            'filtered_service_level_percent' => $filteredCount > 0 ? round(($filteredOnTime / $filteredCount) * 100.0, 2) : 100.0,
            'has_delay_filter' => $hasDelayFilter,
        ];

        return $result;
    }

    /**
     * Guarda o actualiza un ajuste / justificación de fecha en el Informe de Nivel de Servicio.
     * Permite justificar entregas que por acuerdo con el cliente (falta de pago, espera de confirmación, etc.)
     * se reprogramaron o no deben imputarse como atraso de planta.
     *
     * @param array<string, mixed> $data
     * @return array{ok: bool, message?: string, error?: string, despacho_id?: int, is_justified?: bool, reason_category?: string, adjusted_date?: string}
     */
    public function saveServiceLevelAdjustment(array $data, string $userName = 'Usuario'): array
    {
        $despachoId = isset($data['despacho_id']) ? (int)$data['despacho_id'] : 0;
        if ($despachoId <= 0) {
            return ['ok' => false, 'error' => 'ID de despacho inválido.'];
        }

        $ordersDeliveryId = !empty($data['orders_delivery_id']) ? (int)$data['orders_delivery_id'] : null;
        $docNumber = trim((string)($data['doc_number'] ?? ''));
        $ccNumber = trim((string)($data['cc_number'] ?? ''));
        $adjustedDateStr = trim((string)($data['adjusted_date'] ?? ''));
        $isJustified = !empty($data['is_justified']) ? 1 : 0;
        $reasonCategory = trim((string)($data['reason_category'] ?? 'Acuerdo con cliente'));
        $reasonDetails = trim((string)($data['reason_details'] ?? ''));

        if ($reasonCategory === '') {
            return ['ok' => false, 'error' => 'Debe indicar el motivo o justificación de la modificación.'];
        }

        // Calcular timestamp ajustado si se ingresó fecha
        $adjustedTs = null;
        if ($adjustedDateStr !== '') {
            try {
                $tz = new DateTimeZone('America/Santiago');
                $adjustedTs = (new DateTimeImmutable($adjustedDateStr . ' 12:00:00', $tz))->getTimestamp();
            } catch (Throwable) {
                $adjustedTs = null;
            }
        }

        // Obtener fecha original previa si ya existía ajuste o desde ERP orders_delivery
        $origCommittedTs = null;
        try {
            $s = $this->pdo->prepare("SELECT * FROM service_level_adjustments WHERE despacho_id = :did LIMIT 1");
            $s->execute([':did' => $despachoId]);
            $existingAdj = $s->fetch(PDO::FETCH_ASSOC);
            if ($existingAdj && !empty($existingAdj['original_committed_date'])) {
                $origCommittedTs = (int)$existingAdj['original_committed_date'];
            }
        } catch (Throwable) {}

        if (!$origCommittedTs && $ordersDeliveryId) {
            try {
                $odStmt = $this->erpPdo->prepare("SELECT dlv_delivery_date FROM orders_delivery WHERE id = :odid LIMIT 1");
                $odStmt->execute([':odid' => $ordersDeliveryId]);
                $odRow = $odStmt->fetch(PDO::FETCH_ASSOC);
                if ($odRow && !empty($odRow['dlv_delivery_date'])) {
                    $origCommittedTs = (int)$odRow['dlv_delivery_date'];
                }
            } catch (Throwable) {}
        }

        // Upsert en service_level_adjustments
        try {
            $sql = "INSERT INTO service_level_adjustments 
                (despacho_id, orders_delivery_id, doc_number, cc_number, original_committed_date, adjusted_committed_date, is_justified, reason_category, reason_details, updated_by, created_at, updated_at)
                VALUES 
                (:despacho_id, :orders_delivery_id, :doc_number, :cc_number, :orig_date, :adj_date, :is_justified, :reason_category, :reason_details, :updated_by, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                orders_delivery_id = VALUES(orders_delivery_id),
                doc_number = VALUES(doc_number),
                cc_number = VALUES(cc_number),
                adjusted_committed_date = VALUES(adjusted_committed_date),
                is_justified = VALUES(is_justified),
                reason_category = VALUES(reason_category),
                reason_details = VALUES(reason_details),
                updated_by = VALUES(updated_by),
                updated_at = NOW()";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':despacho_id' => $despachoId,
                ':orders_delivery_id' => $ordersDeliveryId,
                ':doc_number' => $docNumber,
                ':cc_number' => $ccNumber,
                ':orig_date' => $origCommittedTs,
                ':adj_date' => $adjustedTs,
                ':is_justified' => $isJustified,
                ':reason_category' => $reasonCategory,
                ':reason_details' => $reasonDetails,
                ':updated_by' => $userName,
            ]);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al guardar ajuste: ' . $e->getMessage()];
        }

        // Sincronizar fecha en ERP orders_delivery si se especificó nueva fecha
        if ($ordersDeliveryId && $adjustedTs) {
            try {
                $erpUpd = $this->erpPdo->prepare("UPDATE orders_delivery SET dlv_delivery_date = :new_ts WHERE id = :odid");
                $erpUpd->execute([
                    ':new_ts' => $adjustedTs,
                    ':odid' => $ordersDeliveryId,
                ]);
            } catch (Throwable) {}
        }

        return [
            'ok' => true,
            'message' => 'Registro de entrega modificado y sincronizado con éxito.',
            'despacho_id' => $despachoId,
            'is_justified' => (bool)$isJustified,
            'reason_category' => $reasonCategory,
            'adjusted_date' => $adjustedDateStr,
        ];
    }

    /**
     * Revierte el ajuste de una entrega, restaurando la fecha y estado original.
     *
     * @return array{ok: bool, message?: string, error?: string}
     */
    public function revertServiceLevelAdjustment(int $despachoId, string $userName = 'Usuario'): array
    {
        if ($despachoId <= 0) {
            return ['ok' => false, 'error' => 'ID de despacho inválido.'];
        }

        try {
            $s = $this->pdo->prepare("SELECT * FROM service_level_adjustments WHERE despacho_id = :did LIMIT 1");
            $s->execute([':did' => $despachoId]);
            $adj = $s->fetch(PDO::FETCH_ASSOC);

            if ($adj) {
                // Restaurar fecha en ERP si teníamos la original
                if (!empty($adj['orders_delivery_id']) && !empty($adj['original_committed_date'])) {
                    try {
                        $erpUpd = $this->erpPdo->prepare("UPDATE orders_delivery SET dlv_delivery_date = :orig_ts WHERE id = :odid");
                        $erpUpd->execute([
                            ':orig_ts' => (int)$adj['original_committed_date'],
                            ':odid' => (int)$adj['orders_delivery_id'],
                        ]);
                    } catch (Throwable) {}
                }

                $del = $this->pdo->prepare("DELETE FROM service_level_adjustments WHERE despacho_id = :did");
                $del->execute([':did' => $despachoId]);
            }

            return ['ok' => true, 'message' => 'Ajuste revertido y valores originales restaurados.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Error al revertir: ' . $e->getMessage()];
        }
    }

    /**
     * Informe de Despachos Comerciales por Período o Rango de Fechas.
     * Con valorización en dinero ($ CLP) y filtros por Cliente, Tipo Doc y Transporte.
     *
     * @return array{
     *     summary: array<string, mixed>,
     *     rows: array<int, array<string, mixed>>,
     *     clients: array<int, array{id: int, name: string}>,
     *     transports: array<int, array{id: int, name: string}>
     * }
     */
    public function getDispatchesPeriodReport(
        string $startAt,
        string $endAt,
        ?int $clientId = null,
        ?string $docType = null,
        ?int $transportId = null,
        ?string $search = null
    ): array {
        $result = [
            'summary' => [
                'total_dispatches' => 0,
                'total_items_count' => 0,
                'total_dispatched_units' => 0.0,
                'total_dispatched_money' => 0.0,
                'total_clients_count' => 0,
                'average_money_per_dispatch' => 0.0,
                'average_units_per_dispatch' => 0.0,
                'facturas_count' => 0,
                'guias_count' => 0,
            ],
            'rows' => [],
            'clients' => [],
            'transports' => [],
        ];

        if (!$this->erpTableExists('despacho') || !$this->erpTableExists('detalle_despacho')) {
            return $result;
        }

        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = strtotime($startAt) ?: 0;
            $endTs = strtotime($endAt) ?: 0;
        }

        $where = ["d.fecha_ingreso BETWEEN :start_ts AND :end_ts"];
        $params = [
            ':start_ts' => $startTs,
            ':end_ts' => $endTs,
        ];

        if ($clientId !== null && $clientId > 0) {
            $where[] = "d.id_cliente = :cid";
            $params[':cid'] = $clientId;
        }

        if ($docType !== null && $docType !== '' && $docType !== 'all') {
            $where[] = "d.tipo_documento = :dtype";
            $params[':dtype'] = strtoupper(trim($docType));
        }

        if ($transportId !== null && $transportId > 0) {
            $where[] = "d.id_transporte = :tid";
            $params[':tid'] = $transportId;
        }

        if ($search !== null && trim($search) !== '') {
            $s = '%' . trim($search) . '%';
            $where[] = "(c.cust_name LIKE :s1 OR c.cust_company LIKE :s2 OR o.req_number LIKE :s3 OR d.numero_documento LIKE :s4 OR od.dlv_docnum LIKE :s5 OR i.item_number_prod LIKE :s6 OR i.item_title LIKE :s7 OR tc.transports_chofer_nombre LIKE :s8 OR tc.transports_chofer_paterno LIKE :s9)";
            $params[':s1'] = $s;
            $params[':s2'] = $s;
            $params[':s3'] = $s;
            $params[':s4'] = $s;
            $params[':s5'] = $s;
            $params[':s6'] = $s;
            $params[':s7'] = $s;
            $params[':s8'] = $s;
            $params[':s9'] = $s;
        }

        $sql = "
            SELECT 
                d.id AS despacho_id,
                d.fecha_ingreso,
                d.hora_ingreso,
                d.hora_salida,
                d.numero_documento,
                d.tipo_documento,
                d.id_cliente,
                d.id_transporte,
                d.estado,
                d.observacion,
                COALESCE(c.cust_company, c.cust_name, 'Cliente N/D') AS customer_name,
                o.req_number AS cc_number,
                od.dlv_docnum AS guia_factura,
                od.dlv_total_netto,
                od.dlv_total_brutto,
                i.item_number_prod,
                COALESCE(i.item_title, dd.descripcion, 'Producto N/D') AS item_name,
                dd.salida AS dispatched_units,
                dd.cantidad AS declared_units,
                oi.item_sellprice_netto,
                oi.item_sellprice_netto_dsc,
                oi.item_amount,
                i.item_sellprice_netto AS item_catalog_price,
                t1.trans_name AS transport_company,
                CONCAT(COALESCE(tc.transports_chofer_nombre, ''), ' ', COALESCE(tc.transports_chofer_paterno, '')) AS driver_name,
                tv.transports_vh_patente AS vehicle_plate
            FROM despacho d
            INNER JOIN detalle_despacho dd ON d.id = dd.id_despacho
            LEFT JOIN customer c ON c.id = d.id_cliente
            LEFT JOIN transports_chofer tc ON tc.id = d.id_chofer
            LEFT JOIN transports_vehiculo tv ON tv.id = d.id_patente
            LEFT JOIN transports t1 ON t1.id = d.id_transporte
            LEFT JOIN item i ON i.id = dd.id_item
            LEFT JOIN orders_delivery od ON d.numero_documento = od.id
            LEFT JOIN orders o ON o.id = od.dlv_order_id
            LEFT JOIN orders_items oi ON oi.req_id = o.id AND oi.item_id = dd.id_item
            WHERE " . implode(" AND ", $where) . "
            ORDER BY d.fecha_ingreso DESC, d.id DESC, dd.id ASC
        ";

        $rows = [];
        $totalUnits = 0.0;
        $totalMoney = 0.0;
        $distinctDespachos = [];
        $distinctClients = [];
        $usedDeliveryTotals = [];
        $facturasCount = 0;
        $guiasCount = 0;

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute($params);
            $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rawRows as $dr) {
                $units = (float)($dr['dispatched_units'] ?? $dr['declared_units'] ?? 0);
                if ($units <= 0 && (float)($dr['declared_units'] ?? 0) > 0) {
                    $units = (float)$dr['declared_units'];
                }

                $unitPrice = 0.0;
                if (!empty($dr['item_sellprice_netto']) && (float)$dr['item_sellprice_netto'] > 0) {
                    $unitPrice = (float)$dr['item_sellprice_netto'];
                } elseif (!empty($dr['item_amount']) && (float)$dr['item_amount'] > 0 && !empty($dr['item_sellprice_netto_dsc'])) {
                    $unitPrice = (float)$dr['item_sellprice_netto_dsc'] / (float)$dr['item_amount'];
                } elseif (!empty($dr['item_catalog_price']) && (float)$dr['item_catalog_price'] > 0) {
                    $unitPrice = (float)$dr['item_catalog_price'];
                }

                $lineMoney = round($units * $unitPrice, 2);
                if ($lineMoney <= 0 && !empty($dr['dlv_total_netto']) && (float)$dr['dlv_total_netto'] > 0) {
                    $dlvId = (string)($dr['numero_documento'] ?? '');
                    if (!isset($usedDeliveryTotals[$dlvId])) {
                        $lineMoney = (float)$dr['dlv_total_netto'];
                        $usedDeliveryTotals[$dlvId] = true;
                        if ($units > 0 && $unitPrice <= 0) {
                            $unitPrice = round($lineMoney / $units, 2);
                        }
                    }
                }

                $docNum = trim((string)($dr['guia_factura'] ?? ''));
                if ($docNum === '' || $docNum === '0') {
                    $docNum = trim((string)($dr['numero_documento'] ?? ''));
                }

                $fDate = !empty($dr['fecha_ingreso']) ? date('d/m/Y', (int)$dr['fecha_ingreso']) : '—';
                $fTime = !empty($dr['hora_salida']) && (string)$dr['hora_salida'] !== '00:00:00' ? substr((string)$dr['hora_salida'], 0, 5) : (!empty($dr['hora_ingreso']) ? substr((string)$dr['hora_ingreso'], 0, 5) : '—');
                $dType = strtoupper(trim((string)($dr['tipo_documento'] ?? 'DESP')));

                $dr['formatted_date'] = $fDate;
                $dr['departure_time'] = $fTime;
                $dr['fecha_formateada'] = ($fDate !== '—' ? $fDate : '') . ($fTime !== '—' ? ' ' . $fTime : '');
                $dr['doc_number'] = $docNum;
                $dr['numero_documento'] = $docNum;
                $dr['doc_type_clean'] = $dType;
                $dr['tipo_documento'] = $dType;
                $dr['dispatched_units'] = $units;
                $dr['salida'] = $units;
                $dr['unit_price'] = $unitPrice;
                $dr['total_money'] = $lineMoney;
                $dr['total_amount'] = $lineMoney;
                $dr['cliente_nombre'] = $dr['customer_name'] ?? '';
                $dr['cliente_rut'] = $dr['customer_rut'] ?? '';
                $dr['cost_center'] = $dr['cc_number'] ?? '';
                $dr['order_number'] = $dr['cc_number'] ?? '';
                $dr['item_codigo'] = $dr['item_number_prod'] ?? '';
                $dr['item_nombre'] = $dr['item_name'] ?? '';
                $dr['observacion'] = $dr['observacion'] ?? '';
                $dr['transporte_nombre'] = $dr['transport_company'] ?? '';
                $dr['chofer_nombre'] = $dr['driver_name'] ?? '';
                $dr['patente'] = $dr['vehicle_plate'] ?? '';
                $dr['estado_nombre'] = ($dr['estado'] == 1 || $dr['estado'] == '1' || $dr['estado'] === 'EMITIDO') ? 'EMITIDO' : (string)($dr['estado'] ?? 'EMITIDO');

                $rows[] = $dr;
                $totalUnits += $units;
                $totalMoney += $lineMoney;
                $distinctDespachos[(int)$dr['despacho_id']] = true;
                if (!empty($dr['id_cliente'])) {
                    $distinctClients[(int)$dr['id_cliente']] = true;
                }

                if ($dType === 'FA' || str_contains($dType, 'FACT')) {
                    $facturasCount++;
                } else {
                    $guiasCount++;
                }
            }
        } catch (Throwable) {}

        // Catálogos para filtros
        $clientsList = [];
        $transportsList = [];
        try {
            $stmtC = $this->erpPdo->query("
                SELECT DISTINCT c.id, COALESCE(c.cust_company, c.cust_name) AS name, COALESCE(c.cust_company, c.cust_name) AS nombre, c.cust_rut AS rut
                FROM customer c
                INNER JOIN despacho d ON d.id_cliente = c.id
                WHERE d.fecha_ingreso >= UNIX_TIMESTAMP('2026-01-01')
                ORDER BY name ASC
            ");
            $clientsList = $stmtC->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {}

        try {
            $stmtT = $this->erpPdo->query("SELECT id, trans_name AS name, trans_name AS nombre FROM transports ORDER BY trans_name ASC");
            $transportsList = $stmtT->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {}

        $totDespachos = count($distinctDespachos);
        $result['summary'] = [
            'total_dispatches' => $totDespachos,
            'total_items_count' => count($rows),
            'total_units' => $totalUnits,
            'total_dispatched_units' => $totalUnits,
            'total_amount' => $totalMoney,
            'total_dispatched_money' => $totalMoney,
            'clients_count' => count($distinctClients),
            'total_clients_count' => count($distinctClients),
            'average_money_per_dispatch' => $totDespachos > 0 ? round($totalMoney / $totDespachos, 2) : 0.0,
            'avg_money_per_dispatch' => $totDespachos > 0 ? round($totalMoney / $totDespachos, 2) : 0.0,
            'avg_amount_per_dispatch' => $totDespachos > 0 ? round($totalMoney / $totDespachos, 2) : 0.0,
            'average_units_per_dispatch' => $totDespachos > 0 ? round($totalUnits / $totDespachos, 1) : 0.0,
            'avg_units_per_dispatch' => $totDespachos > 0 ? round($totalUnits / $totDespachos, 1) : 0.0,
            'facturas_count' => $facturasCount,
            'fa_count' => $facturasCount,
            'guias_count' => $guiasCount,
            'gv_count' => $guiasCount,
        ];
        $result['rows'] = $rows;
        $result['dispatches'] = $rows;
        $result['clients'] = $clientsList;
        $result['transports'] = $transportsList;

        return $result;
    }

    public function getErpDashboardSummary(): array
    {
        $workOrders = [
            'open' => 0,
            'active' => 0,
            'cutting' => 0,
            'closed' => 0,
            'completed' => 0,
        ];
        $stmt = $this->pdo->query(
            "SELECT
                SUM(CASE WHEN status = 'OPEN' THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN status = 'CUTTING' THEN 1 ELSE 0 END) AS cutting_count,
                SUM(CASE WHEN status = 'CLOSED' THEN 1 ELSE 0 END) AS closed_count
             FROM work_orders"
        );
        $row = $stmt->fetch() ?: [];
        $workOrders['open'] = (int)($row['open_count'] ?? 0);
        $workOrders['active'] = (int)($row['active_count'] ?? 0);
        $workOrders['cutting'] = (int)($row['cutting_count'] ?? 0);
        $workOrders['closed'] = (int)($row['closed_count'] ?? 0);
        $workOrders['completed'] = $workOrders['closed'];

        $rolls = [
            'total' => 0,
            'in_stock' => 0,
            'in_process' => 0,
            'ready_for_cut' => 0,
            'output' => 0,
            'blocked' => 0,
        ];
        $stmt = $this->pdo->query(
            "SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN status IN ('RECEIVED','IN_PROCESS','BLOCKED') THEN 1 ELSE 0 END) AS in_stock_count,
                SUM(CASE WHEN status = 'IN_PROCESS' THEN 1 ELSE 0 END) AS in_process_count,
                SUM(CASE WHEN status = 'BLOCKED' THEN 1 ELSE 0 END) AS blocked_count,
                SUM(CASE WHEN process_stage = 'PRINTED' AND status <> 'CONSUMED' THEN 1 ELSE 0 END) AS ready_cut_count,
                SUM(CASE WHEN parent_roll_id IS NOT NULL THEN 1 ELSE 0 END) AS output_count
             FROM rolls"
        );
        $row = $stmt->fetch() ?: [];
        $rolls['total'] = (int)($row['total_count'] ?? 0);
        $rolls['in_stock'] = (int)($row['in_stock_count'] ?? 0);
        $rolls['in_process'] = (int)($row['in_process_count'] ?? 0);
        $rolls['blocked'] = (int)($row['blocked_count'] ?? 0);
        $rolls['ready_for_cut'] = (int)($row['ready_cut_count'] ?? 0);
        $rolls['output'] = (int)($row['output_count'] ?? 0);

        $packaging = [
            'boxes' => 0,
            'pallets' => 0,
            'units' => 0.0,
        ];
        $stmt = $this->pdo->query('SELECT COUNT(*) AS box_count, COALESCE(SUM(units_qty), 0) AS units_total FROM boxes');
        $row = $stmt->fetch() ?: [];
        $packaging['boxes'] = (int)($row['box_count'] ?? 0);
        $packaging['units'] = (float)($row['units_total'] ?? 0);
        $stmt = $this->pdo->query('SELECT COUNT(*) AS pallet_count FROM pallets');
        $packaging['pallets'] = (int)$stmt->fetchColumn();

        $reception = [
            'purchase_orders_pending' => 0,
            'containers_pending' => 0,
        ];
        $stmt = $this->erpPdo->query('SELECT id FROM supplier_order WHERE sord_type = 0');
        $purchaseOrderIds = array_map(static fn($value): int => (int)$value, $stmt->fetchAll(PDO::FETCH_COLUMN));
        $purchaseOrderStats = $this->getPurchaseOrderStatsByIds($purchaseOrderIds);
        foreach ($purchaseOrderStats as $stats) {
            if ((int)($stats['total_lines'] ?? 0) > (int)($stats['completed_lines'] ?? 0)) {
                $reception['purchase_orders_pending']++;
            }
        }

        $stmt = $this->erpPdo->query('SELECT id FROM supplier_contenedor');
        $containerIds = array_map(static fn($value): int => (int)$value, $stmt->fetchAll(PDO::FETCH_COLUMN));
        $containerStats = $this->getImportContainerStatsByIds($containerIds);
        foreach ($containerStats as $stats) {
            if ((int)($stats['total_lines'] ?? 0) > (int)($stats['completed_lines'] ?? 0)) {
                $reception['containers_pending']++;
            }
        }

        return [
            'work_orders' => $workOrders,
            'rolls' => $rolls,
            'packaging' => $packaging,
            'reception' => $reception,
        ];
    }

    public function getLegacyErpHomeDashboard(int $userId): array
    {
        $dashboardUrl = '';
        $userPic = '';
        $salesByYear = [];

        if ($this->erpTableExists('user')) {
            try {
                $stmt = $this->erpPdo->prepare('SELECT user_pic, user_dashboard FROM user WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $userId]);
                $row = $stmt->fetch();
                if (is_array($row)) {
                    $userPic = (string)($row['user_pic'] ?? '');
                    $dashboardUrl = (string)($row['user_dashboard'] ?? '');
                }
            } catch (Throwable) {
                $userPic = '';
                $dashboardUrl = '';
            }
        }

        if ($this->erpTableExists('invoices_sell')) {
            try {
                $stmt = $this->erpPdo->query(
                    "SELECT
                        DATE_FORMAT(DATE_ADD('1970-01-01', INTERVAL invc_date SECOND), '%Y') AS year,
                        ROUND(SUM(invc_total_netto), 0) AS total
                     FROM invoices_sell
                     GROUP BY DATE_FORMAT(DATE_ADD('1970-01-01', INTERVAL invc_date SECOND), '%Y')
                     ORDER BY year ASC"
                );
                $salesByYear = $stmt->fetchAll();
            } catch (Throwable) {
                $salesByYear = [];
            }
        }

        return [
            'user_pic' => $userPic,
            'dashboard_url' => $dashboardUrl,
            'sales_by_year' => $salesByYear,
        ];
    }

    private function erpTableExists(string $table): bool
    {
        if (isset($this->erpTableExistsCache[$table])) {
            return $this->erpTableExistsCache[$table];
        }
        try {
            // Rendimiento: el ERP está en un servidor remoto (~35 ms por consulta). En vez de una consulta
            // por cada tabla consultada, se carga UNA sola vez la lista completa de tablas de la base
            // y se responde desde memoria. Si la carga falla, se cae a la consulta individual de siempre.
            static $allTables = [];
            $dbKey = spl_object_id($this->erpPdo);
            if (!isset($allTables[$dbKey])) {
                $allTables[$dbKey] = [];
                try {
                    $names = $this->erpPdo
                        ->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')
                        ->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($names as $name) {
                        $allTables[$dbKey][strtolower((string)$name)] = true;
                    }
                } catch (Throwable) {
                    $allTables[$dbKey] = [];
                }
            }
            if ($allTables[$dbKey] !== []) {
                $exists = isset($allTables[$dbKey][strtolower($table)]);
                $this->erpTableExistsCache[$table] = $exists;
                return $exists;
            }
            $stmt = $this->erpPdo->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
            );
            $stmt->execute([
                ':table_name' => $table,
            ]);
            $exists = (int)$stmt->fetchColumn() > 0;
            $this->erpTableExistsCache[$table] = $exists;
            return $exists;
        } catch (Throwable) {
            $this->erpTableExistsCache[$table] = false;
            return false;
        }
    }

    private function erpColumnExists(string $table, string $column): bool
    {
        if (isset($this->erpColumnExistsCache[$table]) && array_key_exists($column, $this->erpColumnExistsCache[$table])) {
            return (bool)$this->erpColumnExistsCache[$table][$column];
        }
        try {
            $stmt = $this->erpPdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
            );
            $stmt->execute([
                ':table_name' => $table,
                ':column_name' => $column,
            ]);
            $exists = (int)$stmt->fetchColumn() > 0;
            if (!isset($this->erpColumnExistsCache[$table])) {
                $this->erpColumnExistsCache[$table] = [];
            }
            $this->erpColumnExistsCache[$table][$column] = $exists;
            return $exists;
        } catch (Throwable) {
            if (!isset($this->erpColumnExistsCache[$table])) {
                $this->erpColumnExistsCache[$table] = [];
            }
            $this->erpColumnExistsCache[$table][$column] = false;
            return false;
        }
    }

    /**
     * Unidades que pasaron por Corte y Sellado en el rango (misma regla que la tarjeta "Corte y Sellado" del panel).
     * Consulta liviana para proyecciones: no calcula merma ni plan abierto.
     */
    public function getCorteSelladoProducedUnits(string $startAt, string $endAt): float
    {
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            return 0.0;
        }
        if ($startTs <= 0 || $endTs < $startTs || !$this->erpTableExists('prod_worker_ot_events') || !$this->erpTableExists('prod_worker_ot') || !$this->erpTableExists('prod_agenda')) {
            return 0.0;
        }
        $stmt = $this->erpPdo->prepare(
            "SELECT COALESCE(SUM(e.evt_amount), 0) AS produced_units
             FROM prod_worker_ot_events e
             INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
             INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
             INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
             WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
               AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
               AND (e.evt_status IS NULL OR e.evt_status > 0)
               AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
               AND eq.equipo_type_id = 8"
        );
        $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
        return (float)($stmt->fetchColumn() ?: 0);
    }

    public function getErpOnlyProductionDashboardKpis(string $startAt, string $endAt): array
    {
        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = 0;
            $endTs = 0;
        }
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return [
                'produced_units' => 0.0,
                'pending_units' => 0.0,
                'dispatched_units' => null,
                'waste' => ['percent' => null, 'waste_kg' => null, 'processed_kg' => null],
                'semi_rolls' => ['count' => null, 'rows' => []],
                'work_orders' => [],
                'warehouses' => [],
            ];
        }

        if (!$this->erpTableExists('prod_worker_ot_events') || !$this->erpTableExists('prod_worker_ot') || !$this->erpTableExists('prod_agenda') || !$this->erpTableExists('prod_header')) {
            return [
                'produced_units' => 0.0,
                'pending_units' => 0.0,
                'dispatched_units' => null,
                'waste' => ['percent' => null, 'waste_kg' => null, 'processed_kg' => null],
                'semi_rolls' => ['count' => null, 'rows' => []],
                'work_orders' => [],
                'warehouses' => [],
            ];
        }

        $producedUnits = 0.0;
        try {
            $stmt = $this->erpPdo->prepare(
                "SELECT COALESCE(SUM(e.evt_amount), 0) AS produced_units
                 FROM prod_worker_ot_events e
                 INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                 INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                 INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                 WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                   AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                   AND (e.evt_status IS NULL OR e.evt_status > 0)
                   AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                   AND eq.equipo_type_id = 8"
            );
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $producedUnits = (float)($stmt->fetchColumn() ?: 0);
        } catch (Throwable) {
            $producedUnits = 0.0;
        }

        $wastePercent = null;
        $wasteUnits = null;
        $wasteRequestedUnits = null;
        $wasteBaseUnits = null;
        $wasteDeclaredUnits = null;
        $wasteDeclaredBaseUnits = null;
        $wasteDeclaredPercent = null;
        $wasteSource = null;

        $wasteKg = null;

        if ($this->erpTableExists('prod_worker_ot_defectunits')) {
            $wasteKg = 0.0;
            $wasteUnits = 0.0;
            try {
                $stmt = $this->erpPdo->prepare(
                    'SELECT 
                        d.evt_type,
                        d.evt_amount,
                        d.evt_kgstounits,
                        t11.item_weight,
                        t10.fab_med_width,
                        t10.fab_med_height,
                        t10.fab_med_fuelle,
                        t10.fab_mat_gramms,
                        t10.fab_manilla_length
                     FROM prod_worker_ot_defectunits d
                     INNER JOIN prod_worker_ot_events e ON e.id = d.evt_refid
                     INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                     INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                     LEFT JOIN orders_items t10 ON t10.req_id = pa.ag_reqid
                     LEFT JOIN item t11 ON t11.id = t10.item_id
                     WHERE d.evt_crtdat BETWEEN :start_ts AND :end_ts
                       AND LOWER(d.evt_type) = "merma"'
                );
                $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $itemWeight = (float)($r['item_weight'] ?? 0);
                    $w = (float)($r['fab_med_width'] ?? 0);
                    $h = (float)($r['fab_med_height'] ?? 0);
                    $f = (float)($r['fab_med_fuelle'] ?? 0);
                    $gramms = (float)($r['fab_mat_gramms'] ?? 0);
                    $manLen = (float)($r['fab_manilla_length'] ?? 0);

                    $unitWeightKg = 0.0;
                    if ($itemWeight > 0) {
                        $unitWeightKg = $itemWeight / 1000.0;
                    } elseif ($w > 0 && $h > 0 && $gramms > 0) {
                        $areaM2 = (2.0 * ($w + $f) * $h) / 10000.0;
                        $bodyKg = $areaM2 * ($gramms / 1000.0);
                        $manillaKg = ($manLen > 0) ? (2.0 * ($manLen / 100.0) * 0.025 * ($gramms / 1000.0)) : 0.0;
                        $unitWeightKg = $bodyKg + $manillaKg;
                    }

                    $kg = (float)($r['evt_kgstounits'] ?? 0);
                    $rawUnits = (float)($r['evt_amount'] ?? 0);

                    if ($unitWeightKg > 0 && $kg > 0) {
                        $units = (float)round($kg / $unitWeightKg);
                    } else {
                        $units = $rawUnits;
                    }

                    $wasteKg += $kg;
                    $wasteUnits += $units;
                }
            } catch (Throwable) {
                $wasteUnits = null;
                $wasteKg = null;
            }

            try {
                $stmt = $this->erpPdo->prepare(
                    "SELECT COALESCE(SUM(t.requested_units), 0) AS requested_units
                     FROM (
                        SELECT ph.prd_number AS work_order_number, MAX(pa.ag_amount) AS requested_units
                        FROM prod_worker_ot_events e
                        INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                        INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                        INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
                        WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                          AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                          AND (e.evt_status IS NULL OR e.evt_status > 0)
                          AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                        GROUP BY ph.prd_number
                     ) t"
                );
                $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
                $wasteRequestedUnits = (float)($stmt->fetchColumn() ?: 0.0);
            } catch (Throwable) {
                $wasteRequestedUnits = null;
            }

            if (is_numeric($wasteUnits)) {
                $wasteBaseUnits = $producedUnits;
                if ($wasteBaseUnits > 0) {
                    $wastePercent = ($wasteUnits / $wasteBaseUnits) * 100.0;
                } else {
                    $wastePercent = 0.0;
                }
                $wasteSource = 'prod_worker_ot_defectunits';
            }
        }

        $sqlLegacy = <<<SQL
SELECT
    ph.prd_number AS work_order_number,
    ph.prd_reqid AS cost_center,
    MIN(DATE(FROM_UNIXTIME(e.evt_crtdat))) AS first_date,
    MAX(DATE(FROM_UNIXTIME(e.evt_crtdat))) AS last_date,
    ph.prd_desc AS erp_desc,
    MAX(TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, "")))) AS operator_name,
    MAX(eq.equipo_name) AS machine_name,
    MAX(pa.ag_amount) AS requested_units,
    COALESCE(SUM(e.evt_amount), 0) AS produced_units
FROM prod_worker_ot_events e
INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
LEFT JOIN workers w ON w.id = pwi.win_wrkid
WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
  AND LOWER(e.evt_type) IN ("production","prod","prodsericolor")
  AND (e.evt_status IS NULL OR e.evt_status > 0)
  AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
  AND eq.equipo_type_id = 8
GROUP BY ph.prd_number, ph.prd_reqid, ph.prd_desc
HAVING produced_units > 0
ORDER BY produced_units DESC, work_order_number ASC
LIMIT 40
SQL;

        $sqlExtended = <<<SQL
SELECT
    ph.prd_number AS work_order_number,
    COALESCE(o.req_number, ph.prd_reqid) AS cost_center,
    MIN(DATE(FROM_UNIXTIME(e.evt_crtdat))) AS first_date,
    MAX(DATE(FROM_UNIXTIME(e.evt_crtdat))) AS last_date,
    ph.prd_desc AS erp_desc,
    MAX(TRIM(CONCAT(COALESCE(w.wrk_firstname, ""), " ", COALESCE(w.wrk_lastname, "")))) AS operator_name,
    MAX(eq.equipo_name) AS machine_name,
    MAX(COALESCE(oi.item_amount, pa.ag_amount)) AS requested_units,
    COALESCE(SUM(e.evt_amount), 0) AS produced_units,
    MAX(c.cust_name) AS client_label,
    MAX(UPPER(cat.cat_prefix)) AS product_type,
    MAX(
        CASE
            WHEN oi.fab_med_width IS NULL OR oi.fab_med_height IS NULL THEN ''
            WHEN oi.fab_med_fuelle IS NULL OR oi.fab_med_fuelle = 0 THEN CONCAT(CAST(oi.fab_med_width AS UNSIGNED), 'X', CAST(oi.fab_med_height AS UNSIGNED))
            ELSE CONCAT(CAST(oi.fab_med_width AS UNSIGNED), 'X', CAST(oi.fab_med_height AS UNSIGNED), 'X', CAST(oi.fab_med_fuelle AS UNSIGNED))
        END
    ) AS measure_cm
FROM prod_worker_ot_events e
INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
LEFT JOIN workers w ON w.id = pwi.win_wrkid
LEFT JOIN orders o ON o.id = pa.ag_reqid
LEFT JOIN customer c ON c.id = o.req_cust_id
LEFT JOIN (
    SELECT
        req_id,
        MAX(item_amount) AS item_amount,
        MAX(fab_med_width) AS fab_med_width,
        MAX(fab_med_height) AS fab_med_height,
        MAX(fab_med_fuelle) AS fab_med_fuelle,
        MAX(item_id) AS item_id
    FROM orders_items
    GROUP BY req_id
) oi ON oi.req_id = o.id
LEFT JOIN item it ON it.id = oi.item_id
LEFT JOIN (
    SELECT ip.item_id, MIN(pc.cat_prefix) AS cat_prefix
    FROM item_productcats ip
    INNER JOIN productcats pc ON pc.id = ip.cat_id
    GROUP BY ip.item_id
) cat ON cat.item_id = it.id
WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
  AND LOWER(e.evt_type) IN ("production","prod","prodsericolor")
  AND (e.evt_status IS NULL OR e.evt_status > 0)
  AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
  AND eq.equipo_type_id = 8
GROUP BY ph.prd_number, cost_center, ph.prd_desc
HAVING produced_units > 0
ORDER BY produced_units DESC, work_order_number ASC
LIMIT 40
SQL;

        $rawRows = [];
        $queryMode = 'extended';
        try {
            $stmt = $this->erpPdo->prepare($sqlExtended);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $rawRows = $stmt->fetchAll();
        } catch (Throwable) {
            $queryMode = 'legacy';
            try {
                $stmt = $this->erpPdo->prepare($sqlLegacy);
                $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
                $rawRows = $stmt->fetchAll();
            } catch (Throwable) {
                $rawRows = [];
            }
        }

        $rows = [];
        $pendingUnits = 0.0;
        try {
            $pendingStmt = $this->erpPdo->prepare(
                "SELECT COALESCE(SUM(GREATEST(t.requested_units - t.produced_units, 0)), 0) AS pending_units
                 FROM (
                    SELECT
                        ph.prd_number,
                        MAX(pa.ag_amount) AS requested_units,
                        SUM(e.evt_amount) AS produced_units
                    FROM prod_worker_ot_events e
                    INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                    INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                    INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
                    INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                    WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                      AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                      AND (e.evt_status IS NULL OR e.evt_status > 0)
                      AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                      AND eq.equipo_type_id = 8
                    GROUP BY ph.prd_number
                 ) t"
            );
            $pendingStmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $pendingUnits = (float)($pendingStmt->fetchColumn() ?: 0.0);
        } catch (Throwable) {
            $pendingUnits = 0.0;
        }

        foreach ($rawRows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $desc = trim((string)($r['erp_desc'] ?? ''));
            $requested = (float)($r['requested_units'] ?? 0.0);
            $produced = (float)($r['produced_units'] ?? 0.0);

            $rows[] = [
                'work_order_number' => (string)($r['work_order_number'] ?? ''),
                'cost_center' => (string)($r['cost_center'] ?? ''),
                'first_date' => (string)($r['first_date'] ?? ''),
                'last_date' => (string)($r['last_date'] ?? ''),
                'operator_name' => trim((string)($r['operator_name'] ?? '')),
                'machine_name' => trim((string)($r['machine_name'] ?? '')),
                'client_label' => trim((string)($r['client_label'] ?? '')) !== '' ? (string)$r['client_label'] : $this->parseClientLabelFromErpDesc($desc),
                'product_type' => trim((string)($r['product_type'] ?? '')) !== '' ? (string)$r['product_type'] : $this->parseProductTypeFromErpDesc($desc),
                'measure_cm' => trim((string)($r['measure_cm'] ?? '')) !== '' ? (string)$r['measure_cm'] : $this->parseMeasureCmFromErpDesc($desc),
                'requested_units' => $requested,
                'produced_units' => $produced,
                'erp_desc' => $desc,
                'source' => $queryMode,
            ];
        }

        return [
            'produced_units' => $producedUnits,
            'pending_units' => $pendingUnits,
            'dispatched_units' => null,
            'waste' => [
                'percent' => $wastePercent,
                'waste_units' => $wasteUnits,
                'waste_kg' => $wasteKg,
                'base_units' => $wasteBaseUnits,
                'requested_units' => $wasteRequestedUnits,
                'declared_waste_units' => null,
                'declared_base_units' => null,
                'source' => $wasteSource,
                'processed_kg' => null,
            ],
            'semi_rolls' => ['count' => null, 'rows' => []],
            'work_orders' => $rows,
            'warehouses' => [],
        ];
    }

    public function getErpWasteDashboardDetails(string $startAt, string $endAt): array
    {
        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = 0;
            $endTs = 0;
        }
        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return [
                'by_machine' => [],
                'by_type' => [],
                'by_production_type' => [],
                'top10' => [],
                'sources' => ['by_machine' => null, 'by_type' => null, 'by_production_type' => null, 'top10' => null],
            ];
        }

        $equipoNameCol = null;
        if ($this->erpTableExists('equipo')) {
            if ($this->erpColumnExists('equipo', 'equipo_name')) {
                $equipoNameCol = 'equipo_name';
            } elseif ($this->erpColumnExists('equipo', 'name')) {
                $equipoNameCol = 'name';
            }
        }

        $mermaTitleCol = null;
        if ($this->erpTableExists('prod_mermatypes')) {
            if ($this->erpColumnExists('prod_mermatypes', 'merma_title')) {
                $mermaTitleCol = 'merma_title';
            }
        }

        $classifyMachine = static function (int $machineId, int $typeId, string $machineName): array {
            if ($typeId === 22 || $machineId === 36 || stripos($machineName, 'pulpo') !== false) {
                return ['code' => 'pulpo', 'title' => 'Pulpo Serigráfico', 'icon' => '🐙'];
            }
            if ($typeId === 8 || $typeId === 14 || stripos($machineName, 'sellad') !== false) {
                return ['code' => 'corte_sellado', 'title' => 'Corte y Sellado', 'icon' => '✂️'];
            }
            if ($typeId === 15 || stripos($machineName, 'embalaje') !== false) {
                return ['code' => 'embalaje', 'title' => 'Embalaje', 'icon' => '📦'];
            }
            if ($typeId === 7 || $typeId === 11 || stripos($machineName, 'flexo') !== false || stripos($machineName, 'seri') !== false || stripos($machineName, 'impresora') !== false) {
                return ['code' => 'impresion', 'title' => 'Impresión', 'icon' => '🖨️'];
            }
            return ['code' => 'otros', 'title' => 'Otros Procesos', 'icon' => '⚙️'];
        };

        $byMachine = [];
        $byType = [];
        $byProductionType = [];
        $top10 = [];
        $sources = ['by_machine' => null, 'by_type' => null, 'by_production_type' => null, 'top10' => null];

        if ($this->erpTableExists('prod_worker_ot_defectunits')) {
            $machineNameCol = $equipoNameCol !== null ? 'eq.' . $equipoNameCol : '""';
            $typeTitleCol = $mermaTitleCol !== null ? 'mt.' . $mermaTitleCol : '""';

            try {
                $sqlDefects = "
                    SELECT 
                        d.id AS defect_id,
                        d.evt_type,
                        d.evt_merma_typeid,
                        {$typeTitleCol} AS merma_title,
                        d.evt_amount,
                        d.evt_kgstounits,
                        COALESCE(pa.ag_equipo_id, pwi.win_equipoid, 0) AS machine_id,
                        {$machineNameCol} AS equipo_name,
                        COALESCE(eq.equipo_type_id, 0) AS equipo_type_id,
                        ph.prd_number AS work_order_number,
                        ph.prd_reqid AS cost_center,
                        pa.ag_amount AS requested_units,
                        TRIM(CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, ''))) AS operator_name,
                        t11.item_weight,
                        t10.fab_med_width,
                        t10.fab_med_height,
                        t10.fab_med_fuelle,
                        t10.fab_mat_gramms,
                        t10.fab_manilla_length
                    FROM prod_worker_ot_defectunits d
                    INNER JOIN prod_worker_ot_events e ON e.id = d.evt_refid
                    INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                    INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                    INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
                    LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
                    LEFT JOIN workers w ON w.id = pwi.win_wrkid
                    LEFT JOIN orders_items t10 ON t10.req_id = pa.ag_reqid
                    LEFT JOIN item t11 ON t11.id = t10.item_id
                    " . ($equipoNameCol !== null ? 'LEFT JOIN equipo eq ON eq.id = COALESCE(pa.ag_equipo_id, pwi.win_equipoid)' : '') . "
                    " . ($mermaTitleCol !== null ? 'LEFT JOIN prod_mermatypes mt ON mt.id = d.evt_merma_typeid' : '') . "
                    WHERE d.evt_crtdat BETWEEN :start_ts AND :end_ts
                      AND LOWER(d.evt_type) = 'merma'
                    ORDER BY d.id ASC
                ";
                $stmt = $this->erpPdo->prepare($sqlDefects);
                $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
                $defects = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $byMachineMap = [];
                $byTypeMap = [];
                $byOtMap = [];

                $byProdTypeMap = [
                    'impresion' => [
                        'code' => 'impresion',
                        'title' => 'Impresión',
                        'icon' => '🖨️',
                        'waste_units' => 0.0,
                        'waste_kg' => 0.0,
                        'produced_units' => 0.0,
                        'waste_percent' => null,
                        'share_percent' => 0.0,
                        'machines_count' => 0,
                    ],
                    'corte_sellado' => [
                        'code' => 'corte_sellado',
                        'title' => 'Corte y Sellado',
                        'icon' => '✂️',
                        'waste_units' => 0.0,
                        'waste_kg' => 0.0,
                        'produced_units' => 0.0,
                        'waste_percent' => null,
                        'share_percent' => 0.0,
                        'machines_count' => 0,
                    ],
                    'embalaje' => [
                        'code' => 'embalaje',
                        'title' => 'Embalaje',
                        'icon' => '📦',
                        'waste_units' => 0.0,
                        'waste_kg' => 0.0,
                        'produced_units' => 0.0,
                        'waste_percent' => null,
                        'share_percent' => 0.0,
                        'machines_count' => 0,
                    ],
                    'pulpo' => [
                        'code' => 'pulpo',
                        'title' => 'Pulpo Serigráfico',
                        'icon' => '🐙',
                        'waste_units' => 0.0,
                        'waste_kg' => 0.0,
                        'produced_units' => 0.0,
                        'waste_percent' => null,
                        'share_percent' => 0.0,
                        'machines_count' => 0,
                    ],
                ];

                $totalWasteUnitsAll = 0.0;

                foreach ($defects as $r) {
                    $itemWeight = (float)($r['item_weight'] ?? 0);
                    $w = (float)($r['fab_med_width'] ?? 0);
                    $h = (float)($r['fab_med_height'] ?? 0);
                    $f = (float)($r['fab_med_fuelle'] ?? 0);
                    $gramms = (float)($r['fab_mat_gramms'] ?? 0);
                    $manLen = (float)($r['fab_manilla_length'] ?? 0);

                    $unitWeightKg = 0.0;
                    if ($itemWeight > 0) {
                        $unitWeightKg = $itemWeight / 1000.0;
                    } elseif ($w > 0 && $h > 0 && $gramms > 0) {
                        $areaM2 = (2.0 * ($w + $f) * $h) / 10000.0;
                        $bodyKg = $areaM2 * ($gramms / 1000.0);
                        $manillaKg = ($manLen > 0) ? (2.0 * ($manLen / 100.0) * 0.025 * ($gramms / 1000.0)) : 0.0;
                        $unitWeightKg = $bodyKg + $manillaKg;
                    }

                    $kg = (float)($r['evt_kgstounits'] ?? 0);
                    $rawUnits = (float)($r['evt_amount'] ?? 0);

                    if ($unitWeightKg > 0 && $kg > 0) {
                        $units = (float)round($kg / $unitWeightKg);
                    } else {
                        $units = $rawUnits;
                    }

                    $totalWasteUnitsAll += $units;

                    // Classify machine & production type
                    $mId = (int)($r['machine_id'] ?? 0);
                    $typeId = (int)($r['equipo_type_id'] ?? 0);
                    $mName = trim((string)($r['equipo_name'] ?? ''));
                    $pClass = $classifyMachine($mId, $typeId, $mName);
                    $pCode = $pClass['code'];

                    // Production Type Accumulator
                    if (!isset($byProdTypeMap[$pCode])) {
                        $byProdTypeMap[$pCode] = [
                            'code' => $pCode,
                            'title' => $pClass['title'],
                            'icon' => $pClass['icon'],
                            'waste_units' => 0.0,
                            'waste_kg' => 0.0,
                            'produced_units' => 0.0,
                            'waste_percent' => null,
                            'share_percent' => 0.0,
                            'machines_count' => 0,
                        ];
                    }
                    $byProdTypeMap[$pCode]['waste_units'] += $units;
                    $byProdTypeMap[$pCode]['waste_kg'] += $kg;

                    // Machine
                    $mLabel = $mId > 0 ? ($mName !== '' ? "Equipo {$mId} · {$mName}" : "Equipo {$mId}") : 'Sin máquina';
                    if (!isset($byMachineMap[$mId])) {
                        $byMachineMap[$mId] = [
                            'machine_id' => $mId,
                            'machine_name' => $mName,
                            'machine_label' => $mLabel,
                            'production_type_code' => $pCode,
                            'production_type_title' => $pClass['title'],
                            'production_type_icon' => $pClass['icon'],
                            'waste_units' => 0.0,
                            'waste_kg' => 0.0,
                            'requested_units' => 0.0,
                        ];
                    }
                    $byMachineMap[$mId]['waste_units'] += $units;
                    $byMachineMap[$mId]['waste_kg'] += $kg;

                    // Type
                    $tId = (int)($r['evt_merma_typeid'] ?? 0);
                    $tTitle = trim((string)($r['merma_title'] ?? ''));
                    $tLabel = $tTitle !== '' ? $tTitle : ($tId > 0 ? "Tipo {$tId}" : 'Sin tipo');
                    if (!isset($byTypeMap[$tId])) {
                        $byTypeMap[$tId] = [
                            'type_id' => $tId,
                            'type_label' => $tLabel,
                            'waste_units' => 0.0,
                            'waste_kg' => 0.0,
                        ];
                    }
                    $byTypeMap[$tId]['waste_units'] += $units;
                    $byTypeMap[$tId]['waste_kg'] += $kg;

                    // OT
                    $ot = trim((string)($r['work_order_number'] ?? ''));
                    if ($ot !== '') {
                        if (!isset($byOtMap[$ot])) {
                            $byOtMap[$ot] = [
                                'work_order_number' => $ot,
                                'cost_center' => trim((string)($r['cost_center'] ?? '')),
                                'operator_name' => trim((string)($r['operator_name'] ?? '')) ?: 'N/D',
                                'machine_label' => $mLabel,
                                'production_type_title' => $pClass['title'],
                                'requested_units' => (float)($r['requested_units'] ?? 0.0),
                                'produced_units' => 0.0,
                                'waste_units' => 0.0,
                                'waste_kg' => 0.0,
                                'waste_percent' => null,
                            ];
                        }
                        $byOtMap[$ot]['waste_units'] += $units;
                        $byOtMap[$ot]['waste_kg'] += $kg;
                    }
                }

                // Fetch produced units per machine in period
                if ($byMachineMap !== []) {
                    $mIds = array_keys($byMachineMap);
                    $mPlaceholders = [];
                    $mParams = [':start_ts' => $startTs, ':end_ts' => $endTs];
                    foreach (array_values($mIds) as $idx => $id) {
                        $ph = ':m_id_' . $idx;
                        $mPlaceholders[] = $ph;
                        $mParams[$ph] = $id;
                    }
                    $stmtProd = $this->erpPdo->prepare("
                        SELECT
                            t.machine_id,
                            COALESCE(SUM(t.produced_units), 0) AS produced_units
                        FROM (
                            SELECT
                                machine_id,
                                work_order_number,
                                event_date,
                                MAX(sum_units) AS produced_units
                            FROM (
                                SELECT
                                    COALESCE(pa.ag_equipo_id, 0) AS machine_id,
                                    ph.prd_number AS work_order_number,
                                    DATE(FROM_UNIXTIME(e.evt_crtdat)) AS event_date,
                                    LOWER(e.evt_type) AS evt_type,
                                    SUM(e.evt_amount) AS sum_units
                                FROM prod_worker_ot_events e
                                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                                INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
                                WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                                  AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                                  AND (e.evt_status IS NULL OR e.evt_status > 0)
                                  AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                                  AND pa.ag_equipo_id IN (" . implode(',', $mPlaceholders) . ")
                                GROUP BY pa.ag_equipo_id, ph.prd_number, DATE(FROM_UNIXTIME(e.evt_crtdat)), LOWER(e.evt_type)
                            ) x
                            GROUP BY machine_id, work_order_number, event_date
                        ) t
                        GROUP BY t.machine_id
                    ");
                    $stmtProd->execute($mParams);
                    foreach ($stmtProd->fetchAll(PDO::FETCH_ASSOC) as $mp) {
                        $mid = (int)($mp['machine_id'] ?? 0);
                        if (isset($byMachineMap[$mid])) {
                            $prodVal = (float)($mp['produced_units'] ?? 0.0);
                            $byMachineMap[$mid]['requested_units'] = $prodVal;
                            $mCode = $byMachineMap[$mid]['production_type_code'];
                            if (isset($byProdTypeMap[$mCode])) {
                                $byProdTypeMap[$mCode]['produced_units'] += $prodVal;
                            }
                        }
                    }
                }

                // Compute counts and rates for production types
                foreach ($byMachineMap as $mInfo) {
                    $mCode = $mInfo['production_type_code'];
                    if (isset($byProdTypeMap[$mCode])) {
                        $byProdTypeMap[$mCode]['machines_count']++;
                    }
                }
                foreach ($byProdTypeMap as $k => $pt) {
                    $pProd = (float)$pt['produced_units'];
                    $pWaste = (float)$pt['waste_units'];
                    $byProdTypeMap[$k]['waste_percent'] = $pProd > 0 ? round(($pWaste / $pProd) * 100.0, 2) : null;
                    $byProdTypeMap[$k]['share_percent'] = $totalWasteUnitsAll > 0 ? round(($pWaste / $totalWasteUnitsAll) * 100.0, 1) : 0.0;
                }

                $byMachine = array_values($byMachineMap);
                usort($byMachine, fn($a, $b) => ($b['waste_units'] <=> $a['waste_units']) ?: ($b['waste_kg'] <=> $a['waste_kg']));

                $byType = array_values($byTypeMap);
                usort($byType, fn($a, $b) => ($b['waste_units'] <=> $a['waste_units']) ?: ($b['waste_kg'] <=> $a['waste_kg']));

                uasort($byOtMap, fn($a, $b) => ($b['waste_units'] <=> $a['waste_units']) ?: ($b['waste_kg'] <=> $a['waste_kg']));
                $top10 = array_slice(array_values($byOtMap), 0, 10);

                if ($top10 !== []) {
                    $otNumbers = array_column($top10, 'work_order_number');
                    $otPlaceholders = [];
                    $otParams = [':start_ts' => $startTs, ':end_ts' => $endTs];
                    foreach (array_values($otNumbers) as $idx => $otNum) {
                        $ph = ':ot_num_' . $idx;
                        $otPlaceholders[] = $ph;
                        $otParams[$ph] = $otNum;
                    }
                    $stmtOtProd = $this->erpPdo->prepare("
                        SELECT
                            ph.prd_number,
                            COALESCE(SUM(t.produced_units), 0) AS produced_units
                        FROM (
                            SELECT
                                ph2.prd_number,
                                event_date,
                                MAX(sum_units) AS produced_units
                            FROM (
                                SELECT
                                    ph3.prd_number,
                                    DATE(FROM_UNIXTIME(e.evt_crtdat)) AS event_date,
                                    LOWER(e.evt_type) AS evt_type,
                                    SUM(e.evt_amount) AS sum_units
                                FROM prod_worker_ot_events e
                                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                                INNER JOIN prod_header ph3 ON ph3.id = pa.ag_prdid
                                WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                                  AND ph3.prd_number IN (" . implode(',', $otPlaceholders) . ")
                                  AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                                  AND (e.evt_status IS NULL OR e.evt_status > 0)
                                  AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                                GROUP BY ph3.prd_number, DATE(FROM_UNIXTIME(e.evt_crtdat)), LOWER(e.evt_type)
                            ) x
                            INNER JOIN prod_header ph2 ON ph2.prd_number = x.prd_number
                            GROUP BY ph2.prd_number, event_date
                        ) t
                        INNER JOIN prod_header ph ON ph.prd_number = t.prd_number
                        GROUP BY ph.prd_number
                    ");
                    $stmtOtProd->execute($otParams);
                    $prodByOt = [];
                    foreach ($stmtOtProd->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $prodByOt[$row['prd_number']] = (float)($row['produced_units'] ?? 0.0);
                    }

                    foreach ($top10 as &$otItem) {
                        $otNum = $otItem['work_order_number'];
                        $prod = (float)($prodByOt[$otNum] ?? 0.0);
                        $otItem['produced_units'] = $prod;
                        $otItem['waste_percent'] = $prod > 0 
                            ? round(($otItem['waste_units'] / $prod) * 100.0, 2)
                            : ($otItem['requested_units'] > 0 ? round(($otItem['waste_units'] / $otItem['requested_units']) * 100.0, 2) : 0.0);
                    }
                    unset($otItem);
                }

                $sources = [
                    'by_machine' => 'prod_worker_ot_defectunits',
                    'by_type' => 'prod_worker_ot_defectunits',
                    'by_production_type' => 'prod_worker_ot_defectunits',
                    'top10' => 'prod_worker_ot_defectunits',
                ];
            } catch (Throwable) {
                $byMachine = [];
                $byType = [];
                $byProdTypeMap = [];
                $top10 = [];
                $sources = ['by_machine' => null, 'by_type' => null, 'by_production_type' => null, 'top10' => null];
            }
        }

        return [
            'by_machine' => $byMachine,
            'by_type' => $byType,
            'by_production_type' => array_values($byProdTypeMap),
            'top10' => $top10,
            'sources' => $sources,
        ];
    }

    /**
     * Obtiene el informe consolidado y analítico de mermas por operador.
     * Permite filtrar por un operador específico o por tipo de producción.
     *
     * @param string $startAt Fecha inicio 'Y-m-d H:i:s'
     * @param string $endAt   Fecha término 'Y-m-d H:i:s'
     * @param int|null $filterOperatorId ID opcional del operador
     * @param string|null $filterProcess Código opcional de proceso ('impresion', 'corte_sellado', 'embalaje', 'pulpo')
     * @return array Resumen, listado de operadores, catálogo y métricas globales
     */
    public function getErpOperatorWasteReport(string $startAt, string $endAt, ?int $filterOperatorId = null, ?string $filterProcess = null): array
    {
        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = 0;
            $endTs = 0;
        }

        $emptyResponse = [
            'summary' => [
                'total_operators' => 0,
                'total_waste_units' => 0.0,
                'total_waste_kg' => 0.0,
                'total_produced_units' => 0.0,
                'global_waste_percent' => 0.0,
                'top_operator' => null,
            ],
            'operators' => [],
            'catalog' => [],
            'processes' => [
                'impresion' => 'Impresión',
                'corte_sellado' => 'Corte y Sellado',
                'embalaje' => 'Embalaje',
                'pulpo' => 'Pulpo Serigráfico',
            ],
        ];

        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
            return $emptyResponse;
        }

        $classifyMachine = static function (int $machineId, int $typeId, string $machineName): array {
            if ($typeId === 22 || $machineId === 36 || stripos($machineName, 'pulpo') !== false) {
                return ['code' => 'pulpo', 'title' => 'Pulpo Serigráfico', 'icon' => '🐙'];
            }
            if ($typeId === 8 || $typeId === 14 || stripos($machineName, 'sellad') !== false) {
                return ['code' => 'corte_sellado', 'title' => 'Corte y Sellado', 'icon' => '✂️'];
            }
            if ($typeId === 15 || stripos($machineName, 'embalaje') !== false) {
                return ['code' => 'embalaje', 'title' => 'Embalaje', 'icon' => '📦'];
            }
            if ($typeId === 7 || $typeId === 11 || stripos($machineName, 'flexo') !== false || stripos($machineName, 'seri') !== false || stripos($machineName, 'impresora') !== false) {
                return ['code' => 'impresion', 'title' => 'Impresión', 'icon' => '🖨️'];
            }
            return ['code' => 'otros', 'title' => 'Otros Procesos', 'icon' => '⚙️'];
        };

        try {
            $equipoNameCol = $this->erpColumnExists('equipo', 'equipo_name') ? 'eq.equipo_name' : ($this->erpColumnExists('equipo', 'name') ? 'eq.name' : '""');
            $mermaTitleCol = $this->erpColumnExists('prod_mermatypes', 'merma_title') ? 'mt.merma_title' : '""';

            $sqlDefects = "
                SELECT 
                    d.id AS defect_id,
                    d.evt_type,
                    d.evt_merma_typeid,
                    {$mermaTitleCol} AS merma_title,
                    d.evt_amount,
                    d.evt_kgstounits,
                    d.evt_crtdat,
                    COALESCE(pa.ag_equipo_id, pwi.win_equipoid, 0) AS machine_id,
                    {$equipoNameCol} AS equipo_name,
                    COALESCE(eq.equipo_type_id, 0) AS equipo_type_id,
                    ph.prd_number AS work_order_number,
                    ph.prd_reqid AS cost_center,
                    COALESCE(w.id, pwi.win_wrkid, 0) AS operator_id,
                    TRIM(CONCAT(COALESCE(w.wrk_firstname, ''), ' ', COALESCE(w.wrk_lastname, ''))) AS operator_name,
                    COALESCE(w.wrk_rut, '') AS operator_rut,
                    t11.item_weight,
                    t10.fab_med_width,
                    t10.fab_med_height,
                    t10.fab_med_fuelle,
                    t10.fab_mat_gramms,
                    t10.fab_manilla_length
                FROM prod_worker_ot_defectunits d
                INNER JOIN prod_worker_ot_events e ON e.id = d.evt_refid
                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                INNER JOIN prod_header ph ON ph.id = pa.ag_prdid
                LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
                LEFT JOIN workers w ON w.id = pwi.win_wrkid
                LEFT JOIN orders_items t10 ON t10.req_id = pa.ag_reqid
                LEFT JOIN item t11 ON t11.id = t10.item_id
                LEFT JOIN equipo eq ON eq.id = COALESCE(pa.ag_equipo_id, pwi.win_equipoid)
                LEFT JOIN prod_mermatypes mt ON mt.id = d.evt_merma_typeid
                WHERE d.evt_crtdat BETWEEN :start_ts AND :end_ts
                  AND LOWER(d.evt_type) = 'merma'
                ORDER BY d.evt_crtdat DESC
            ";
            $stmt = $this->erpPdo->prepare($sqlDefects);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $rawDefects = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Produced units per worker in the same period
            $sqlProd = "
                SELECT 
                    pwi.win_wrkid AS operator_id,
                    COALESCE(SUM(e.evt_amount), 0) AS produced_units
                FROM prod_worker_ot_events e
                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                INNER JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
                WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                  AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                  AND (e.evt_status IS NULL OR e.evt_status > 0)
                  AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                  AND pwi.win_wrkid > 0
                GROUP BY pwi.win_wrkid
            ";
            $stmtProd = $this->erpPdo->prepare($sqlProd);
            $stmtProd->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $prodMap = $stmtProd->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

            $operatorsMap = [];
            $totalPlantWasteUnits = 0.0;
            $totalPlantWasteKg = 0.0;

            foreach ($rawDefects as $r) {
                $itemWeight = (float)($r['item_weight'] ?? 0);
                $w = (float)($r['fab_med_width'] ?? 0);
                $h = (float)($r['fab_med_height'] ?? 0);
                $f = (float)($r['fab_med_fuelle'] ?? 0);
                $gramms = (float)($r['fab_mat_gramms'] ?? 0);
                $manLen = (float)($r['fab_manilla_length'] ?? 0);

                $unitWeightKg = 0.0;
                if ($itemWeight > 0) {
                    $unitWeightKg = $itemWeight / 1000.0;
                } elseif ($w > 0 && $h > 0 && $gramms > 0) {
                    $areaM2 = (2.0 * ($w + $f) * $h) / 10000.0;
                    $bodyKg = $areaM2 * ($gramms / 1000.0);
                    $manillaKg = ($manLen > 0) ? (2.0 * ($manLen / 100.0) * 0.025 * ($gramms / 1000.0)) : 0.0;
                    $unitWeightKg = $bodyKg + $manillaKg;
                }

                $kg = (float)($r['evt_kgstounits'] ?? 0);
                $rawUnits = (float)($r['evt_amount'] ?? 0);
                $units = ($unitWeightKg > 0 && $kg > 0) ? (float)round($kg / $unitWeightKg) : $rawUnits;

                $mId = (int)($r['machine_id'] ?? 0);
                $typeId = (int)($r['equipo_type_id'] ?? 0);
                $mName = trim((string)($r['equipo_name'] ?? ''));
                $pClass = $classifyMachine($mId, $typeId, $mName);
                $pCode = $pClass['code'];

                // Filter by process if active
                if ($filterProcess !== null && $filterProcess !== '' && $pCode !== $filterProcess) {
                    continue;
                }

                $opId = (int)($r['operator_id'] ?? 0);
                // Filter by operator if active
                if ($filterOperatorId !== null && $filterOperatorId > 0 && $opId !== $filterOperatorId) {
                    continue;
                }

                $totalPlantWasteUnits += $units;
                $totalPlantWasteKg += $kg;

                $opName = trim((string)($r['operator_name'] ?? ''));
                if ($opName === '') {
                    $opName = $opId > 0 ? "Operador #{$opId}" : 'Sin Operador Asignado';
                }
                $opRut = trim((string)($r['operator_rut'] ?? ''));

                if (!isset($operatorsMap[$opId])) {
                    $operatorsMap[$opId] = [
                        'operator_id' => $opId,
                        'operator_name' => $opName,
                        'operator_rut' => $opRut,
                        'produced_units' => (float)($prodMap[$opId] ?? 0.0),
                        'waste_units' => 0.0,
                        'waste_kg' => 0.0,
                        'waste_percent' => 0.0,
                        'share_percent' => 0.0,
                        'ot_count' => 0,
                        'defect_count' => 0,
                        'main_process' => $pClass['title'],
                        'main_process_code' => $pCode,
                        'process_weights' => [],
                        'defect_types_map' => [],
                        'top_defect_type' => 'N/D',
                        'ots_map' => [],
                        'ots' => [],
                    ];
                }

                $operatorsMap[$opId]['waste_units'] += $units;
                $operatorsMap[$opId]['waste_kg'] += $kg;
                $operatorsMap[$opId]['defect_count']++;

                // Track processes
                if (!isset($operatorsMap[$opId]['process_weights'][$pClass['title']])) {
                    $operatorsMap[$opId]['process_weights'][$pClass['title']] = 0.0;
                }
                $operatorsMap[$opId]['process_weights'][$pClass['title']] += ($kg > 0 ? $kg : $units);

                // Track defect types
                $dTitle = trim((string)($r['merma_title'] ?? '')) ?: 'Merma General';
                if (!isset($operatorsMap[$opId]['defect_types_map'][$dTitle])) {
                    $operatorsMap[$opId]['defect_types_map'][$dTitle] = 0.0;
                }
                $operatorsMap[$opId]['defect_types_map'][$dTitle] += ($kg > 0 ? $kg : $units);

                // Track OTs
                $otNumber = trim((string)($r['work_order_number'] ?? ''));
                if ($otNumber !== '') {
                    $operatorsMap[$opId]['ots_map'][$otNumber] = true;
                }

                $mLabel = $mId > 0 ? ($mName !== '' ? "Equipo {$mId} · {$mName}" : "Equipo {$mId}") : 'Sin máquina';
                $operatorsMap[$opId]['ots'][] = [
                    'defect_id' => (int)$r['defect_id'],
                    'ot_number' => $otNumber ?: 'N/D',
                    'cost_center' => trim((string)($r['cost_center'] ?? '')),
                    'machine_label' => $mLabel,
                    'process_title' => $pClass['title'],
                    'process_code' => $pCode,
                    'merma_title' => $dTitle,
                    'units' => $units,
                    'kg' => $kg,
                    'datetime' => date('d/m/Y H:i', (int)$r['evt_crtdat']),
                ];
            }

            // Post-process operators (main process, top defect, % rates)
            $totalProducedAll = 0.0;
            foreach ($operatorsMap as $opId => &$opData) {
                $opData['ot_count'] = count($opData['ots_map']);
                unset($opData['ots_map']);

                // Find main process
                if (!empty($opData['process_weights'])) {
                    arsort($opData['process_weights']);
                    $opData['main_process'] = (string)array_key_first($opData['process_weights']);
                }
                unset($opData['process_weights']);

                // Find top defect
                if (!empty($opData['defect_types_map'])) {
                    arsort($opData['defect_types_map']);
                    $opData['top_defect_type'] = (string)array_key_first($opData['defect_types_map']);
                }
                unset($opData['defect_types_map']);

                $prodU = (float)$opData['produced_units'];
                $totalProducedAll += $prodU;
                $wUnits = (float)$opData['waste_units'];

                $opData['waste_percent'] = $prodU > 0 ? round(($wUnits / $prodU) * 100.0, 2) : ($wUnits > 0 ? 100.0 : 0.0);
                $opData['share_percent'] = $totalPlantWasteUnits > 0 ? round(($wUnits / $totalPlantWasteUnits) * 100.0, 1) : 0.0;
            }
            unset($opData);

            $operatorsList = array_values($operatorsMap);
            usort($operatorsList, static fn($a, $b) => ($b['waste_kg'] <=> $a['waste_kg']) ?: ($b['waste_units'] <=> $a['waste_units']));

            // Catalog of workers for filter
            $stmtCat = $this->erpPdo->query("
                SELECT id, TRIM(CONCAT(COALESCE(wrk_firstname, ''), ' ', COALESCE(wrk_lastname, ''))) AS name, COALESCE(wrk_rut, '') AS rut
                FROM workers
                WHERE wrk_status = 1
                ORDER BY wrk_firstname, wrk_lastname
            ");
            $catalog = $stmtCat->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $globalWastePct = $totalProducedAll > 0 ? round(($totalPlantWasteUnits / $totalProducedAll) * 100.0, 2) : 0.0;
            $topOp = !empty($operatorsList) ? $operatorsList[0]['operator_name'] . ' (' . number_format($operatorsList[0]['waste_kg'], 1, ',', '.') . ' kg)' : 'N/D';

            return [
                'summary' => [
                    'total_operators' => count($operatorsList),
                    'total_waste_units' => $totalPlantWasteUnits,
                    'total_waste_kg' => $totalPlantWasteKg,
                    'total_produced_units' => $totalProducedAll,
                    'global_waste_percent' => $globalWastePct,
                    'top_operator' => $topOp,
                ],
                'operators' => $operatorsList,
                'catalog' => $catalog,
                'processes' => [
                    'impresion' => 'Impresión',
                    'corte_sellado' => 'Corte y Sellado',
                    'embalaje' => 'Embalaje',
                    'pulpo' => 'Pulpo Serigráfico',
                ],
            ];
        } catch (Throwable) {
            return $emptyResponse;
        }
    }

    public function getProductionDashboardKpis(string $startAt, string $endAt): array
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(units_qty), 0) AS produced_units FROM boxes WHERE created_at BETWEEN :start AND :end');
        $stmt->execute([':start' => $startAt, ':end' => $endAt]);
        $producedUnits = (float)($stmt->fetchColumn() ?: 0);

        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(units_qty), 0) AS dispatched_units
             FROM boxes
             WHERE destination_mode = "CUSTOMER_ORDER"
               AND created_at BETWEEN :start AND :end'
        );
        $stmt->execute([':start' => $startAt, ':end' => $endAt]);
        $dispatchedUnits = (float)($stmt->fetchColumn() ?: 0);

        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(COALESCE(wo.target_qty, 0)), 0) AS pending_units
             FROM work_orders wo
             LEFT JOIN erp_work_order_sync sync ON sync.work_order_id = wo.id
             WHERE wo.status IN ("OPEN","ACTIVE","CUTTING")
               AND COALESCE(
                   CASE WHEN sync.erp_plan_timestamp IS NOT NULL AND sync.erp_plan_timestamp > 0 THEN FROM_UNIXTIME(sync.erp_plan_timestamp) ELSE NULL END,
                   wo.created_at
               ) BETWEEN :start AND :end'
        );
        $stmt->execute([':start' => $startAt, ':end' => $endAt]);
        $pendingUnits = (float)($stmt->fetchColumn() ?: 0);

        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.roll_code, r.meters, r.weight_kg, r.created_at,
                    wo.id AS work_order_id, wo.ot_code, wo.target_qty,
                    COALESCE(roll_totals.total_rolls, 0) AS total_rolls
             FROM rolls r
             LEFT JOIN work_orders wo ON wo.id = r.source_work_order_id
             LEFT JOIN (
                SELECT source_work_order_id, COUNT(*) AS total_rolls
                FROM rolls
                WHERE source_work_order_id IS NOT NULL
                GROUP BY source_work_order_id
             ) roll_totals ON roll_totals.source_work_order_id = r.source_work_order_id
             WHERE r.process_stage = "PRINTED"
               AND r.status <> "CONSUMED"
             ORDER BY r.id DESC
             LIMIT 40'
        );
        $stmt->execute();
        $semiRows = $stmt->fetchAll();
        foreach ($semiRows as &$row) {
            $targetQty = (float)($row['target_qty'] ?? 0);
            $totalRolls = (int)($row['total_rolls'] ?? 0);
            $row['estimated_units'] = $targetQty > 0 && $totalRolls > 0 ? round($targetQty / $totalRolls, 3) : null;
        }
        unset($row);

        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(weight_kg), 0) AS waste_kg FROM production_wastes WHERE created_at BETWEEN :start AND :end');
        $stmt->execute([':start' => $startAt, ':end' => $endAt]);
        $wasteKg = (float)($stmt->fetchColumn() ?: 0);

        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.process_weight_kg")) AS DECIMAL(12,3))), 0) AS processed_kg
             FROM events
             WHERE type = "WORK_ORDER_ROLL_ATTACHED"
               AND created_at BETWEEN :start AND :end'
        );
        $stmt->execute([':start' => $startAt, ':end' => $endAt]);
        $processedKg = (float)($stmt->fetchColumn() ?: 0);

        $stmt = $this->pdo->prepare(
            'SELECT wo.id,
                    wo.ot_code,
                    wo.sku_final,
                    wo.status,
                    wo.created_at,
                    sync.erp_machine_label AS machine_name,
                    COALESCE(wo.target_qty, 0) AS target_qty,
                    COALESCE(
                        CASE WHEN sync.erp_plan_timestamp IS NOT NULL AND sync.erp_plan_timestamp > 0 THEN FROM_UNIXTIME(sync.erp_plan_timestamp) ELSE NULL END,
                        wo.created_at
                    ) AS planned_at,
                    COALESCE(box_stats.produced_units, 0) AS produced_units,
                    COALESCE(box_stats.dispatched_units, 0) AS dispatched_units,
                    COALESCE(box_stats.boxes_count, 0) AS boxes_count,
                    box_stats.last_box_at,
                    COALESCE(waste_stats.waste_kg, 0) AS waste_kg,
                    COALESCE(waste_stats.waste_records, 0) AS waste_records,
                    COALESCE(process_stats.processed_kg, 0) AS processed_kg,
                    COALESCE(process_stats.attached_events, 0) AS attached_events,
                    COALESCE(semi_stats.semi_rolls_count, 0) AS semi_rolls_count,
                    COALESCE(semi_stats.semi_weight_kg, 0) AS semi_weight_kg,
                    COALESCE(semi_stats.semi_meters, 0) AS semi_meters
             FROM work_orders wo
             LEFT JOIN erp_work_order_sync sync ON sync.work_order_id = wo.id
             LEFT JOIN (
                SELECT work_order_id,
                       COALESCE(SUM(units_qty), 0) AS produced_units,
                       COALESCE(SUM(CASE WHEN destination_mode = "CUSTOMER_ORDER" THEN units_qty ELSE 0 END), 0) AS dispatched_units,
                       COUNT(*) AS boxes_count,
                       MAX(created_at) AS last_box_at
                FROM boxes
                WHERE created_at BETWEEN :box_start AND :box_end
                GROUP BY work_order_id
             ) box_stats ON box_stats.work_order_id = wo.id
             LEFT JOIN (
                SELECT work_order_id,
                       COALESCE(SUM(weight_kg), 0) AS waste_kg,
                       COUNT(*) AS waste_records
                FROM production_wastes
                WHERE created_at BETWEEN :waste_start AND :waste_end
                GROUP BY work_order_id
             ) waste_stats ON waste_stats.work_order_id = wo.id
             LEFT JOIN (
                SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED) AS work_order_id,
                       COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.process_weight_kg")) AS DECIMAL(12,3))), 0) AS processed_kg,
                       COUNT(*) AS attached_events
                FROM events
                WHERE type = "WORK_ORDER_ROLL_ATTACHED"
                  AND created_at BETWEEN :process_start AND :process_end
                GROUP BY CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, "$.work_order_id")) AS UNSIGNED)
             ) process_stats ON process_stats.work_order_id = wo.id
             LEFT JOIN (
                SELECT source_work_order_id AS work_order_id,
                       COUNT(*) AS semi_rolls_count,
                       COALESCE(SUM(weight_kg), 0) AS semi_weight_kg,
                       COALESCE(SUM(meters), 0) AS semi_meters
                FROM rolls
                WHERE process_stage = "PRINTED"
                  AND status <> "CONSUMED"
                  AND source_work_order_id IS NOT NULL
                GROUP BY source_work_order_id
             ) semi_stats ON semi_stats.work_order_id = wo.id
             WHERE (
                COALESCE(
                    CASE WHEN sync.erp_plan_timestamp IS NOT NULL AND sync.erp_plan_timestamp > 0 THEN FROM_UNIXTIME(sync.erp_plan_timestamp) ELSE NULL END,
                    wo.created_at
                ) BETWEEN :plan_start AND :plan_end
                OR box_stats.work_order_id IS NOT NULL
                OR waste_stats.work_order_id IS NOT NULL
                OR process_stats.work_order_id IS NOT NULL
                OR semi_stats.work_order_id IS NOT NULL
             )
             ORDER BY
                CASE wo.status
                    WHEN "ACTIVE" THEN 0
                    WHEN "OPEN" THEN 1
                    WHEN "CUTTING" THEN 2
                    WHEN "CLOSED" THEN 3
                    ELSE 4
                END,
                COALESCE(box_stats.produced_units, 0) DESC,
                wo.id DESC
             LIMIT 80'
        );
        $stmt->execute([
            ':box_start' => $startAt,
            ':box_end' => $endAt,
            ':waste_start' => $startAt,
            ':waste_end' => $endAt,
            ':process_start' => $startAt,
            ':process_end' => $endAt,
            ':plan_start' => $startAt,
            ':plan_end' => $endAt,
        ]);
        $workOrderRows = $stmt->fetchAll();
        foreach ($workOrderRows as &$workOrderRow) {
            $targetQty = (float)($workOrderRow['target_qty'] ?? 0);
            $producedQty = (float)($workOrderRow['produced_units'] ?? 0);
            $processedQty = (float)($workOrderRow['processed_kg'] ?? 0);
            $wasteQty = (float)($workOrderRow['waste_kg'] ?? 0);
            $pendingQty = max(0.0, $targetQty - $producedQty);
            $progressPercent = $targetQty > 0 ? round(min(100, ($producedQty / $targetQty) * 100), 2) : 0.0;
            $dispatchCoveragePercent = $producedQty > 0 ? round(min(100, (((float)($workOrderRow['dispatched_units'] ?? 0)) / $producedQty) * 100), 2) : 0.0;
            $wastePercentByOt = $processedQty > 0 ? round(($wasteQty / $processedQty) * 100, 2) : 0.0;
            $status = (string)($workOrderRow['status'] ?? '');
            if ($status === 'CLOSED' || ($targetQty > 0 && $pendingQty <= 0 && $producedQty > 0)) {
                $dashboardStatus = 'Terminada';
            } elseif ($producedQty > 0 || $processedQty > 0 || $wasteQty > 0) {
                $dashboardStatus = 'Con avance';
            } elseif ($status === 'ACTIVE') {
                $dashboardStatus = 'En produccion';
            } elseif ($status === 'CUTTING') {
                $dashboardStatus = 'En corte';
            } else {
                $dashboardStatus = 'Pendiente';
            }

            $workOrderRow['pending_units'] = round($pendingQty, 3);
            $workOrderRow['progress_percent'] = $progressPercent;
            $workOrderRow['dispatch_coverage_percent'] = $dispatchCoveragePercent;
            $workOrderRow['waste_percent'] = $wastePercentByOt;
            $workOrderRow['dashboard_status'] = $dashboardStatus;
        }
        unset($workOrderRow);

        $wastePercent = $processedKg > 0 ? round(($wasteKg / $processedKg) * 100, 2) : 0.0;

        return [
            'produced_units' => round($producedUnits, 3),
            'pending_units' => round($pendingUnits, 3),
            'dispatched_units' => round($dispatchedUnits, 3),
            'work_orders' => $workOrderRows,
            'semi_rolls' => [
                'count' => count($semiRows),
                'rows' => $semiRows,
            ],
            'warehouses' => $this->stockSummaryWithCapacities(),
            'waste' => [
                'waste_kg' => round($wasteKg, 3),
                'processed_kg' => round($processedKg, 3),
                'percent' => $wastePercent,
            ],
        ];
    }

    public function stockSummaryWithCapacities(?int $onlyErpStorehouseId = null): array
    {
        $capacities = [];
        $stmt = $this->pdo->prepare('SELECT warehouse_id, capacity_units_total, capacity_pallets FROM warehouse_capacities');
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            $capacities[(int)$row['warehouse_id']] = [
                'capacity_units_total' => (float)($row['capacity_units_total'] ?? 0),
                'capacity_pallets' => (int)($row['capacity_pallets'] ?? 0),
            ];
        }

        // Consultar descripciones, clasificaciones y capacidades del maestro ERP
        $erpStorehouses = [];
        if ($this->erpPdo !== null) {
            try {
                $stmtErp = $this->erpPdo->query('SELECT id, st_desc, st_clasificacion, st_capacidad FROM company_shops_storehouses WHERE st_status = 1');
                foreach ($stmtErp->fetchAll(PDO::FETCH_ASSOC) as $erow) {
                    $erpStorehouses[(int)$erow['id']] = [
                        'capacidad_erp' => (int)($erow['st_capacidad'] ?? 0),
                        'clasificacion' => trim((string)($erow['st_clasificacion'] ?? '')),
                        'desc' => trim((string)($erow['st_desc'] ?? '')),
                    ];
                }
            } catch (Throwable) {}
        }

        // Consultar el stock real y desglose por categorías (Pallets, Bobinas, Cajas, Unidades) en el ERP
        $erpStockByStorehouse = [];
        if ($this->erpPdo !== null) {
            try {
                $stmtStock = $this->erpPdo->query(
                    "SELECT 
                        iss.st_id,
                        SUM(CASE 
                            WHEN (u.unit_name IN ('PALLET', 'PAL') OR it.item_title LIKE '%PALLET%' OR it.item_number_prod LIKE 'PAL%')
                            THEN iss.iss_inventory ELSE 0 END) AS qty_pallets,
                        SUM(CASE 
                            WHEN (u.unit_name IN ('BOB', 'ROL') OR it.item_number_prod LIKE 'TEL%' OR it.item_title LIKE 'BOBINA%' OR it.item_title LIKE '% BOBINA%')
                                 AND NOT (it.item_title LIKE '%CUCHILLA%')
                                 AND NOT (u.unit_name IN ('PALLET', 'PAL') OR it.item_title LIKE '%PALLET%' OR it.item_number_prod LIKE 'PAL%')
                            THEN iss.iss_inventory ELSE 0 END) AS qty_bobinas,
                        SUM(CASE 
                            WHEN (u.unit_name IN ('CAJA', 'CAJ', 'caj', 'CAJ.600') OR it.item_title LIKE 'CAJA %' OR it.item_title LIKE '% CAJA %' OR it.item_title LIKE 'CAJAS %')
                                 AND NOT (it.item_title LIKE 'BOLSA %' OR it.item_title LIKE 'BOL %')
                                 AND NOT (u.unit_name IN ('PALLET', 'PAL') OR it.item_title LIKE '%PALLET%' OR it.item_number_prod LIKE 'PAL%')
                                 AND NOT (u.unit_name IN ('BOB', 'ROL') OR it.item_number_prod LIKE 'TEL%' OR it.item_title LIKE 'BOBINA%' OR it.item_title LIKE '% BOBINA%')
                            THEN iss.iss_inventory ELSE 0 END) AS qty_cajas,
                        SUM(CASE 
                            WHEN NOT (
                                (u.unit_name IN ('PALLET', 'PAL') OR it.item_title LIKE '%PALLET%' OR it.item_number_prod LIKE 'PAL%')
                                OR ((u.unit_name IN ('BOB', 'ROL') OR it.item_number_prod LIKE 'TEL%' OR it.item_title LIKE 'BOBINA%' OR it.item_title LIKE '% BOBINA%') AND NOT (it.item_title LIKE '%CUCHILLA%'))
                                OR ((u.unit_name IN ('CAJA', 'CAJ', 'caj', 'CAJ.600') OR it.item_title LIKE 'CAJA %' OR it.item_title LIKE '% CAJA %' OR it.item_title LIKE 'CAJAS %') AND NOT (it.item_title LIKE 'BOLSA %' OR it.item_title LIKE 'BOL %'))
                            )
                            THEN iss.iss_inventory ELSE 0 END) AS qty_unidades,
                        SUM(iss.iss_inventory) AS stock_pos,
                        SUM(CASE WHEN iss.iss_inventory > 0 THEN (iss.iss_inventory - iss.iss_inventory_reserved) ELSE 0 END) AS stock_disp,
                        COUNT(DISTINCT it.id) AS items_count
                    FROM item_shops_storehouses iss
                    JOIN item it ON it.id = iss.item_id AND it.item_status = 1
                    LEFT JOIN item_units u ON u.id = it.item_unit
                    WHERE iss.iss_inventory > 0" . ($onlyErpStorehouseId !== null ? ' AND iss.st_id = ' . (int)$onlyErpStorehouseId : '') . "
                    GROUP BY iss.st_id"
                );
                foreach ($stmtStock->fetchAll(PDO::FETCH_ASSOC) as $srow) {
                    $erpStockByStorehouse[(int)$srow['st_id']] = [
                        'qty_pallets' => (float)($srow['qty_pallets'] ?? 0),
                        'qty_bobinas' => (float)($srow['qty_bobinas'] ?? 0),
                        'qty_cajas' => (float)($srow['qty_cajas'] ?? 0),
                        'qty_unidades' => (float)($srow['qty_unidades'] ?? 0),
                        'stock_units' => (float)($srow['stock_pos'] ?? 0),
                        'available_units' => (float)($srow['stock_disp'] ?? 0),
                        'items_count' => (int)($srow['items_count'] ?? 0),
                    ];
                }
            } catch (Throwable) {}
        }

        $rows = $this->stockSummary();
        foreach ($rows as &$row) {
            $warehouseId = (int)($row['warehouse_id'] ?? 0);
            $erpId = (int)($row['erp_storehouse_id'] ?? 0);

            $cap = $capacities[$warehouseId] ?? ['capacity_units_total' => 0.0, 'capacity_pallets' => 0];
            $capUnits = (float)$cap['capacity_units_total'];
            $capPallets = (int)$cap['capacity_pallets'];
            $capErp = isset($erpStorehouses[$erpId]) ? (float)$erpStorehouses[$erpId]['capacidad_erp'] : 0.0;

            // Si no se configuró capacidad explícita en TRZ, considerar la capacidad del maestro ERP
            if ($capUnits <= 0 && $capErp >= 1000) {
                $capUnits = $capErp;
            }
            if ($capPallets <= 0 && $capErp > 0 && $capErp < 1000) {
                $capPallets = (int)$capErp;
            }

            $row['capacity_units_total'] = $capUnits;
            $row['capacity_pallets'] = $capPallets;
            $row['capacidad_erp'] = $capErp;

            // Obtener stock real y desglose de inventario ERP
            $erpStockInfo = $erpStockByStorehouse[$erpId] ?? null;
            $erpUnits = $erpStockInfo !== null ? (float)$erpStockInfo['stock_units'] : 0.0;
            $erpAvailable = $erpStockInfo !== null ? (float)$erpStockInfo['available_units'] : 0.0;
            $erpItemsCount = $erpStockInfo !== null ? (int)$erpStockInfo['items_count'] : 0;
            $trzUnits = (float)($row['stock_units_total'] ?? 0);

            $row['stock_units_erp'] = $erpUnits;
            $row['stock_available_erp'] = $erpAvailable;
            $row['erp_items_count'] = $erpItemsCount;
            $row['stock_units_trz'] = $trzUnits;

            // Cantidades obtenidas directamente de la base de datos ERP (Pallets, Bobinas, Cajas, Unidades)
            if ($erpStockInfo !== null) {
                $row['pallets_count'] = (float)$erpStockInfo['qty_pallets'];
                $row['rolls_count'] = (float)$erpStockInfo['qty_bobinas'];
                $row['boxes_count'] = (float)$erpStockInfo['qty_cajas'];
                $row['units_other_count'] = (float)$erpStockInfo['qty_unidades'];
                $row['stock_units_total'] = $erpUnits;
            } else {
                $row['units_other_count'] = 0.0;
            }

            $row['occupancy_percent'] = null;
            $palletsCount = (float)($row['pallets_count'] ?? 0);
            $stockUnits = (float)$row['stock_units_total'];

            // Calcular ocupación:
            if ($capPallets > 0 && $palletsCount > 0) {
                $row['occupancy_percent'] = round(($palletsCount / $capPallets) * 100, 2);
            } elseif ($capUnits > 0 && $stockUnits > 0) {
                $row['occupancy_percent'] = round(($stockUnits / $capUnits) * 100, 2);
            } elseif ($capPallets > 0 && $palletsCount == 0 && $stockUnits == 0.0) {
                $row['occupancy_percent'] = 0.0;
            } elseif ($capUnits > 0 && $stockUnits == 0.0) {
                $row['occupancy_percent'] = 0.0;
            }
        }
        unset($row);

        return $rows;
    }

    public function listErpDashboardAlerts(int $limit = 6): array
    {
        $summary = $this->getErpDashboardSummary();
        $alerts = [];

        if ((int)$summary['rolls']['ready_for_cut'] > 0) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Bobinas esperando corte',
                'detail' => (string)$summary['rolls']['ready_for_cut'] . ' bobinas impresas siguen pendientes de corte.',
                'link' => '/cut',
            ];
        }
        if ((int)$summary['work_orders']['cutting'] > 0) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'OT pendientes de cierre',
                'detail' => (string)$summary['work_orders']['cutting'] . ' OT ya terminaron impresión y siguen abiertas por corte.',
                'link' => '/work-orders?view=active',
            ];
        }
        if ((int)$summary['reception']['purchase_orders_pending'] > 0) {
            $alerts[] = [
                'level' => 'info',
                'title' => 'Recepciones nacionales pendientes',
                'detail' => (string)$summary['reception']['purchase_orders_pending'] . ' OCs aún tienen líneas por recepcionar.',
                'link' => '/purchase-orders?status=active&supplier_type=NATIONAL',
            ];
        }
        if ((int)$summary['reception']['containers_pending'] > 0) {
            $alerts[] = [
                'level' => 'info',
                'title' => 'Importaciones pendientes',
                'detail' => (string)$summary['reception']['containers_pending'] . ' contenedores siguen con recepción incompleta.',
                'link' => '/import-containers?status=active',
            ];
        }

        $activeWorkOrder = $this->getActiveWorkOrder();
        if ($activeWorkOrder !== null) {
            $alerts[] = [
                'level' => 'success',
                'title' => 'OT activa en planta',
                'detail' => (string)($activeWorkOrder['ot_code'] ?? 'OT') . ' está marcada como activa para operación.',
                'link' => '/work-orders/' . (int)$activeWorkOrder['id'] . '/start',
            ];
        }

        if ($alerts === []) {
            $alerts[] = [
                'level' => 'success',
                'title' => 'Sin alertas críticas',
                'detail' => 'No hay pendientes operativos relevantes en este momento.',
                'link' => '/',
            ];
        }

        return array_slice($alerts, 0, $limit);
    }

    public function listDashboardRecentTraceability(int $limit = 8): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.roll_code, r.process_stage, r.status, r.created_at,
                    pr.roll_code AS parent_roll_code,
                    wo.id AS work_order_id, wo.ot_code,
                    COALESCE(box_stats.box_count, 0) AS box_count,
                    COALESCE(pallet_stats.pallet_count, 0) AS pallet_count
             FROM rolls r
             LEFT JOIN rolls pr ON pr.id = r.parent_roll_id
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
             WHERE r.parent_roll_id IS NOT NULL
             ORDER BY r.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listRecentOperationalEvents(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, type, created_at, payload
             FROM events
             ORDER BY id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $payload = json_decode((string)($row['payload'] ?? '{}'), true);
            $payload = is_array($payload) ? $payload : [];
            $row['payload_data'] = $payload;
        }
        unset($row);
        return $rows;
    }



    /**
     * Analytics and dataset generator for ERP graphics dashboard (/reports/graphics).
     *
     * Connects directly to unibag_unibag (ERP DB) to aggregate:
     * - Macro KPIs: Confection, Printing, Total Units, Active Machines, Waste (kg and %).
     * - Monthly Trend: Past 6 months of historical production.
     * - Period Evolution: Day-by-day (or weekly) production curve within the selected date range.
     * - Process Distribution: Confection vs Printing breakdown.
     * - Machine Ranking: Top producing machines.
     * - Machine Waste: Defect kg per machine.
     * - Warehouse Occupancy: Current capacity and utilization.
     */
    public function getErpGraphicsAnalytics(string $startAt, string $endAt): array
    {
        $startTs = 0;
        $endTs = 0;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            $startTs = (new DateTimeImmutable($startAt, $tz))->getTimestamp();
            $endTs = (new DateTimeImmutable($endAt, $tz))->getTimestamp();
        } catch (Throwable) {
            $startTs = 0;
            $endTs = 0;
        }

        $emptyResponse = [
            'kpis' => [
                'units_corte_sellado' => 0.0,
                'units_embalaje' => 0.0,
                'units_flexo' => 0.0,
                'units_seri' => 0.0,
                'units_pulpo' => 0.0,
                'units_confeccion' => 0.0,
                'units_impresion' => 0.0,
                'total_units' => 0.0,
                'total_events' => 0,
                'active_machines' => 0,
                'waste_kg' => 0.0,
                'waste_units' => 0.0,
                'waste_percent' => 0.0,
            ],
            'monthly_trend' => [],
            'period_evolution' => [],
            'process_distribution' => [],
            'machine_ranking' => [],
            'waste_by_machine' => [],
            'warehouses' => [],
        ];

        if ($startTs <= 0 || $endTs <= 0 || $endTs < $startTs || !$this->erpPdo) {
            return $emptyResponse;
        }

        try {
            // 1. KPIs Generales de Producción
            $kpiSql = "
                SELECT 
                    COALESCE(SUM(CASE WHEN eq.equipo_type_id = 8 THEN e.evt_amount ELSE 0 END), 0) AS units_corte_sellado,
                    COALESCE(SUM(CASE WHEN eq.equipo_type_id = 15 THEN e.evt_amount ELSE 0 END), 0) AS units_embalaje,
                    COALESCE(SUM(CASE WHEN eq.equipo_type_id = 7 THEN e.evt_amount ELSE 0 END), 0) AS units_flexo,
                    COALESCE(SUM(CASE WHEN (eq.equipo_type_id = 11 AND eq.id != 36 AND LOWER(eq.equipo_name) NOT LIKE '%pulpo%') THEN e.evt_amount ELSE 0 END), 0) AS units_seri,
                    COALESCE(SUM(CASE WHEN (eq.equipo_type_id = 22 OR eq.id = 36 OR LOWER(eq.equipo_name) LIKE '%pulpo%') THEN e.evt_amount ELSE 0 END), 0) AS units_pulpo,
                    COALESCE(SUM(CASE WHEN eq.equipo_type_id IN (8, 15) THEN e.evt_amount ELSE 0 END), 0) AS units_confeccion,
                    COALESCE(SUM(CASE WHEN eq.equipo_type_id IN (7, 11, 22) OR eq.id = 36 OR LOWER(eq.equipo_name) LIKE '%pulpo%' THEN e.evt_amount ELSE 0 END), 0) AS units_impresion,
                    COALESCE(SUM(e.evt_amount), 0) AS total_units,
                    COUNT(DISTINCT e.id) AS total_events,
                    COUNT(DISTINCT eq.id) AS active_machines
                FROM prod_worker_ot_events e
                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                  AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                  AND (e.evt_status IS NULL OR e.evt_status > 0)
                  AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
            ";
            $stmt = $this->erpPdo->prepare($kpiSql);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $prodKpis = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // Merma KPIs
            $wasteSql = "
                SELECT 
                    COUNT(d.id) AS waste_events,
                    COALESCE(SUM(d.evt_amount), 0) AS waste_units,
                    COALESCE(ROUND(SUM(d.evt_kgstounits), 2), 0) AS waste_kg
                FROM prod_worker_ot_defectunits d
                WHERE d.evt_crtdat BETWEEN :start_ts AND :end_ts
                  AND LOWER(d.evt_type) = 'merma'
            ";
            $stmt = $this->erpPdo->prepare($wasteSql);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $wasteKpis = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // Merma % oficial del ERP Dashboard
            $erpOnlyKpis = $this->getErpOnlyProductionDashboardKpis($startAt, $endAt);
            $wastePercent = $erpOnlyKpis['waste']['percent'] ?? 0.0;
            if ($wastePercent === null || !is_numeric($wastePercent)) {
                $wastePercent = 0.0;
            }

            // 2. Tendencia Histórica Mensual (Últimos 6 meses)
            $sixMonthsAgoTs = strtotime('-6 months', $endTs);
            $monthlySql = "
                SELECT 
                    FROM_UNIXTIME(e.evt_crtdat, '%Y-%m') AS ym,
                    COALESCE(SUM(CASE WHEN eq.equipo_type_id IN (8, 15) THEN e.evt_amount ELSE 0 END), 0) AS confeccion,
                    COALESCE(SUM(CASE WHEN eq.equipo_type_id IN (7, 11, 22) THEN e.evt_amount ELSE 0 END), 0) AS impresion,
                    COALESCE(SUM(e.evt_amount), 0) AS total
                FROM prod_worker_ot_events e
                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                WHERE e.evt_crtdat BETWEEN :six_ts AND :end_ts
                  AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                  AND (e.evt_status IS NULL OR e.evt_status > 0)
                  AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                GROUP BY ym
                ORDER BY ym ASC
            ";
            $stmt = $this->erpPdo->prepare($monthlySql);
            $stmt->execute([':six_ts' => $sixMonthsAgoTs, ':end_ts' => $endTs]);
            $monthlyRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $monthlyTrend = [];
            foreach ($monthlyRows as $mr) {
                $time = strtotime($mr['ym'] . '-01');
                $monthlyTrend[] = [
                    'ym' => $mr['ym'],
                    'label' => $time ? date('M/y', $time) : $mr['ym'],
                    'confeccion' => round((float)$mr['confeccion'], 0),
                    'impresion' => round((float)$mr['impresion'], 0),
                    'total' => round((float)$mr['total'], 0),
                ];
            }

            // 3. Evolución en el Período
            $diffDays = ($endTs - $startTs) / 86400;
            if ($diffDays <= 65) {
                $periodSql = "
                    SELECT 
                        FROM_UNIXTIME(e.evt_crtdat, '%Y-%m-%d') AS date_key,
                        FROM_UNIXTIME(e.evt_crtdat, '%d/%m') AS date_label,
                        COALESCE(SUM(CASE WHEN eq.equipo_type_id IN (8, 15) THEN e.evt_amount ELSE 0 END), 0) AS confeccion,
                        COALESCE(SUM(CASE WHEN eq.equipo_type_id IN (7, 11, 22) THEN e.evt_amount ELSE 0 END), 0) AS impresion,
                        COALESCE(SUM(e.evt_amount), 0) AS total
                    FROM prod_worker_ot_events e
                    INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                    INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                    INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                    WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                      AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                      AND (e.evt_status IS NULL OR e.evt_status > 0)
                      AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                    GROUP BY date_key, date_label
                    ORDER BY date_key ASC
                ";
            } else {
                $periodSql = "
                    SELECT 
                        FROM_UNIXTIME(e.evt_crtdat, '%Y-%u') AS date_key,
                        CONCAT('Sem ', FROM_UNIXTIME(e.evt_crtdat, '%u')) AS date_label,
                        COALESCE(SUM(CASE WHEN eq.equipo_type_id IN (8, 15) THEN e.evt_amount ELSE 0 END), 0) AS confeccion,
                        COALESCE(SUM(CASE WHEN eq.equipo_type_id IN (7, 11, 22) THEN e.evt_amount ELSE 0 END), 0) AS impresion,
                        COALESCE(SUM(e.evt_amount), 0) AS total
                    FROM prod_worker_ot_events e
                    INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                    INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                    INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                    WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                      AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                      AND (e.evt_status IS NULL OR e.evt_status > 0)
                      AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                    GROUP BY date_key, date_label
                    ORDER BY date_key ASC
                ";
            }
            $stmt = $this->erpPdo->prepare($periodSql);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $periodEvolution = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $pe) {
                $periodEvolution[] = [
                    'key' => $pe['date_key'],
                    'label' => $pe['date_label'],
                    'confeccion' => round((float)$pe['confeccion'], 0),
                    'impresion' => round((float)$pe['impresion'], 0),
                    'total' => round((float)$pe['total'], 0),
                ];
            }

            // 4. Distribución por Proceso
            $processSql = "
                SELECT 
                    CASE 
                        WHEN eq.equipo_type_id = 22 OR eq.id = 36 OR LOWER(eq.equipo_name) LIKE '%pulpo%' THEN 'Pulpo Serigráfico'
                        WHEN eq.equipo_type_id = 11 THEN 'Serigrafía'
                        WHEN eq.equipo_type_id = 7 THEN 'Flexografía'
                        WHEN eq.equipo_type_id = 8 THEN 'Selladoras'
                        WHEN eq.equipo_type_id = 15 THEN 'Embalaje'
                        ELSE COALESCE(NULLIF(TRIM(eqt.type_ant_title), ''), 'Otro')
                    END AS process_name,
                    SUM(e.evt_amount) AS units
                FROM prod_worker_ot_events e
                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                LEFT JOIN equipo_type eqt ON eqt.id = eq.equipo_type_id
                WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                  AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                  AND (e.evt_status IS NULL OR e.evt_status > 0)
                  AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                GROUP BY process_name
                ORDER BY units DESC
            ";
            $stmt = $this->erpPdo->prepare($processSql);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $processDistribution = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $pr) {
                $processDistribution[] = [
                    'process_name' => $pr['process_name'],
                    'units' => round((float)$pr['units'], 0),
                ];
            }

            // 5. Ranking de Máquinas
            $machinesSql = "
                SELECT 
                    COALESCE(NULLIF(TRIM(eq.equipo_name), ''), 'Sin nombre') AS machine_name,
                    CASE 
                        WHEN eq.equipo_type_id = 22 OR eq.id = 36 OR LOWER(eq.equipo_name) LIKE '%pulpo%' THEN 'Pulpo Serigráfico'
                        WHEN eq.equipo_type_id = 11 THEN 'Serigrafía'
                        WHEN eq.equipo_type_id = 7 THEN 'Flexografía'
                        WHEN eq.equipo_type_id = 8 THEN 'Selladoras'
                        WHEN eq.equipo_type_id = 15 THEN 'Embalaje'
                        ELSE 'Otro'
                    END AS process_name,
                    SUM(e.evt_amount) AS units
                FROM prod_worker_ot_events e
                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                WHERE e.evt_crtdat BETWEEN :start_ts AND :end_ts
                  AND LOWER(e.evt_type) IN ('production','prod','prodsericolor')
                  AND (e.evt_status IS NULL OR e.evt_status > 0)
                  AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))
                GROUP BY machine_name, process_name
                ORDER BY units DESC
                LIMIT 12
            ";
            $stmt = $this->erpPdo->prepare($machinesSql);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $machineRanking = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $m) {
                $machineRanking[] = [
                    'machine_name' => $m['machine_name'],
                    'process_name' => $m['process_name'],
                    'units' => round((float)$m['units'], 0),
                ];
            }

            // 6. Merma por Máquina
            $wasteMachineSql = "
                SELECT 
                    COALESCE(NULLIF(TRIM(eq.equipo_name), ''), 'Sin máquina') AS machine_name,
                    COUNT(d.id) AS defect_events,
                    COALESCE(SUM(d.evt_amount), 0) AS defect_units,
                    COALESCE(ROUND(SUM(d.evt_kgstounits), 2), 0) AS defect_kg
                FROM prod_worker_ot_defectunits d
                INNER JOIN prod_worker_ot_events e ON e.id = d.evt_refid
                INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
                INNER JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
                INNER JOIN equipo eq ON eq.id = pa.ag_equipo_id
                WHERE d.evt_crtdat BETWEEN :start_ts AND :end_ts
                  AND LOWER(d.evt_type) = 'merma'
                GROUP BY machine_name
                ORDER BY defect_kg DESC
                LIMIT 10
            ";
            $stmt = $this->erpPdo->prepare($wasteMachineSql);
            $stmt->execute([':start_ts' => $startTs, ':end_ts' => $endTs]);
            $wasteByMachine = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $wm) {
                $wasteByMachine[] = [
                    'machine_name' => $wm['machine_name'],
                    'defect_events' => (int)$wm['defect_events'],
                    'defect_units' => round((float)$wm['defect_units'], 0),
                    'defect_kg' => (float)$wm['defect_kg'],
                ];
            }

            return [
                'kpis' => [
                    'units_corte_sellado' => round((float)($prodKpis['units_corte_sellado'] ?? 0), 0),
                    'units_embalaje' => round((float)($prodKpis['units_embalaje'] ?? 0), 0),
                    'units_flexo' => round((float)($prodKpis['units_flexo'] ?? 0), 0),
                    'units_seri' => round((float)($prodKpis['units_seri'] ?? 0), 0),
                    'units_pulpo' => round((float)($prodKpis['units_pulpo'] ?? 0), 0),
                    'units_confeccion' => round((float)($prodKpis['units_confeccion'] ?? 0), 0),
                    'units_impresion' => round((float)($prodKpis['units_impresion'] ?? 0), 0),
                    'total_units' => round((float)($prodKpis['total_units'] ?? 0), 0),
                    'total_events' => (int)($prodKpis['total_events'] ?? 0),
                    'active_machines' => (int)($prodKpis['active_machines'] ?? 0),
                    'waste_kg' => (float)($wasteKpis['waste_kg'] ?? 0),
                    'waste_units' => round((float)($wasteKpis['waste_units'] ?? 0), 0),
                    'waste_percent' => round((float)$wastePercent, 2),
                ],
                'monthly_trend' => $monthlyTrend,
                'period_evolution' => $periodEvolution,
                'process_distribution' => $processDistribution,
                'machine_ranking' => $machineRanking,
                'waste_by_machine' => $wasteByMachine,
            ];
        } catch (Throwable $e) {
            return $emptyResponse;
        }
    }

    // =========================================================================
    // SECCIÓN: CONTROL Y GESTIÓN DE HORARIOS DE COLACIÓN DE OPERARIOS
    // =========================================================================


    /**
     * Resumen completo de planta para un día específico (por defecto, ayer).
     * Incluye: producción total, mermas por proceso y máquina, eventos y paradas operativas,
     * mermas críticas (>5%), y control de asistencia/colaciones.
     *
     * @param string $date Fecha YYYY-MM-DD
     * @return array<string, mixed>
     */
    public function getDailyPlantSummary(string $date = ''): array
    {
        $date = trim($date);
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d', strtotime('-1 day'));
        }

        $prevDate = date('Y-m-d', strtotime("$date -1 day"));
        $nextDate = date('Y-m-d', strtotime("$date +1 day"));
        $isYesterday = ($date === date('Y-m-d', strtotime('-1 day')));
        $isToday = ($date === date('Y-m-d'));

        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $ts = strtotime($date);
        $dayOfWeek = $dias[(int)date('w', $ts)];
        $dayNum = date('d', $ts);
        $monthName = $meses[(int)date('n', $ts)];
        $yearNum = date('Y', $ts);
        $formattedDate = "$dayOfWeek, $dayNum de $monthName de $yearNum";

        $startTs = strtotime("$date 00:00:00");
        $endTs = strtotime("$date 23:59:59");

        // 1. Consultar OTs de producción del día
        $prodRows = [];
        try {
            $sqlProd = "
                SELECT 
                    t1.id AS ot_id,
                    t1.wok_status,
                    t1.wok_crtdat,
                    t1.wok_enddat,
                    t4.prd_number AS ot_number,
                    t2.ag_reqid AS cc_number,
                    COALESCE(t12.cust_name, 'Cliente no asignado') AS customer_name,
                    COALESCE(t11.item_title, 'Sin descripción') AS product_name,
                    COALESCE(t7.equipo_name, 'Sin Máquina') AS machine_name,
                    t7.id AS machine_id,
                    t7.equipo_type_id,
                    CASE 
                        WHEN t7.equipo_type_id = 7 THEN 'Flexografía'
                        WHEN t7.equipo_type_id = 8 THEN 'Corte y Sellado'
                        WHEN t7.equipo_type_id = 11 AND t7.id != 36 THEN 'Serigrafía'
                        WHEN t7.equipo_type_id = 22 OR t7.id = 36 THEN 'Pulpo Serigráfico'
                        WHEN t7.equipo_type_id = 15 THEN 'Embalaje'
                        WHEN t7.equipo_type_id = 12 THEN 'Rebobinado'
                        ELSE 'Otros'
                    END AS process_name,
                    CASE 
                        WHEN t7.equipo_type_id = 7 THEN 'flexo'
                        WHEN t7.equipo_type_id = 8 THEN 'sellado'
                        WHEN t7.equipo_type_id = 11 AND t7.id != 36 THEN 'seri'
                        WHEN t7.equipo_type_id = 22 OR t7.id = 36 THEN 'pulpo'
                        WHEN t7.equipo_type_id = 15 THEN 'embalaje'
                        WHEN t7.equipo_type_id = 12 THEN 'rebo'
                        ELSE 'otros'
                    END AS process_code,
                    TRIM(CONCAT(COALESCE(w.wrk_firstname,''), ' ', COALESCE(w.wrk_lastname,''))) AS operator_name,
                    COALESCE(pe.produced_units, 0) AS produced_units,
                    COALESCE(dw.waste_units, 0) AS waste_units,
                    COALESCE(dw.waste_kg, 0) AS waste_kg,
                    COALESCE(pe.produced_meters, 0) AS produced_meters,
                    CASE 
                        WHEN t1.wok_enddat > t1.wok_crtdat THEN ROUND((t1.wok_enddat - t1.wok_crtdat)/3600, 2)
                        ELSE 0 
                    END AS duration_hours
                FROM prod_worker_ot t1
                INNER JOIN prod_agenda t2 ON t1.wok_ag_id = t2.id
                INNER JOIN prod_worker_init t3 ON t1.wok_init_id = t3.id
                INNER JOIN prod_header t4 ON t2.ag_prdid = t4.id
                LEFT JOIN equipo t7 ON t3.win_equipoid = t7.id
                LEFT JOIN workers w ON t3.win_wrkid = w.id
                LEFT JOIN orders t9 ON t2.ag_reqid = t9.id
                LEFT JOIN orders_items t10 ON t9.id = t10.req_id
                LEFT JOIN item t11 ON t10.item_id = t11.id
                LEFT JOIN customer t12 ON t9.req_cust_id = t12.id
                LEFT JOIN (
                    SELECT evt_prod_worker_otid,
                           SUM(CASE WHEN LOWER(evt_type) IN ('prod', 'production', 'prodsericolor')
                                     AND (evt_status IS NULL OR evt_status > 0)
                                     AND (evt_comments IS NULL OR (evt_comments NOT LIKE '%Bobina Semielaborada%' AND evt_comments NOT LIKE '%ROLL-SE-%'))
                                    THEN evt_amount ELSE 0 END) AS produced_units,
                           SUM(CASE WHEN LOWER(evt_type) IN ('prod', 'production', 'prodsericolor')
                                     AND (evt_status IS NULL OR evt_status > 0)
                                     AND (evt_comments IS NULL OR (evt_comments NOT LIKE '%Bobina Semielaborada%' AND evt_comments NOT LIKE '%ROLL-SE-%'))
                                    THEN COALESCE(evt_amount_metros_lineales, 0) ELSE 0 END) AS produced_meters
                    FROM prod_worker_ot_events
                    WHERE (evt_status IS NULL OR evt_status > 0)
                    GROUP BY evt_prod_worker_otid
                ) pe ON pe.evt_prod_worker_otid = t1.id
                LEFT JOIN (
                    SELECT e.evt_prod_worker_otid AS wok_id,
                           SUM(CASE WHEN LOWER(d.evt_type) = 'merma' THEN d.evt_amount ELSE 0 END) AS waste_units,
                           SUM(CASE WHEN LOWER(d.evt_type) = 'merma' THEN d.evt_kgstounits ELSE 0 END) AS waste_kg
                    FROM prod_worker_ot_defectunits d
                    INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                    WHERE d.evt_status > 0
                    GROUP BY e.evt_prod_worker_otid
                ) dw ON dw.wok_id = t1.id
                WHERE t1.wok_status > 0 AND t1.wok_crtdat BETWEEN :s AND :e
                ORDER BY process_name ASC, t7.equipo_name ASC, t1.id ASC
            ";
            $stmt = $this->erpPdo->prepare($sqlProd);
            $stmt->execute([':s' => $startTs, ':e' => $endTs]);
            $prodRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {}

        // 2. Consultar eventos, paradas, montajes y pausas
        $stopsRows = [];
        try {
            $sqlStops = "
                SELECT 
                    e.id,
                    e.evt_type,
                    e.evt_pause_id,
                    COALESCE(ppt.pause_name, CASE WHEN e.evt_type = 'apertura' THEN 'Cambio de Formato / Montaje' ELSE 'Parada Operativa' END) AS stop_reason,
                    e.evt_crtdat,
                    e.evt_enddat,
                    CASE WHEN e.evt_enddat > e.evt_crtdat THEN ROUND((e.evt_enddat - e.evt_crtdat)/60) ELSE 0 END AS duration_minutes,
                    e.evt_comments,
                    eq.equipo_name AS machine_name,
                    TRIM(CONCAT(COALESCE(w.wrk_firstname,''), ' ', COALESCE(w.wrk_lastname,''))) AS operator_name,
                    h.prd_number AS ot_number
                FROM prod_worker_ot_events e
                LEFT JOIN prod_worker_ot ot ON e.evt_prod_worker_otid = ot.id
                LEFT JOIN prod_agenda ag ON ot.wok_ag_id = ag.id
                LEFT JOIN prod_header h ON ag.ag_prdid = h.id
                LEFT JOIN prod_worker_init wi ON ot.wok_init_id = wi.id
                LEFT JOIN workers w ON wi.win_wrkid = w.id
                LEFT JOIN equipo eq ON ag.ag_equipo_id = eq.id
                LEFT JOIN prod_pause_types ppt ON e.evt_pause_id = ppt.id
                WHERE e.evt_crtdat BETWEEN :s AND :e
                  AND (e.evt_type IN ('pause', 'apertura') OR e.evt_pause_id > 0)
                ORDER BY e.evt_crtdat ASC
            ";
            $stmtStops = $this->erpPdo->prepare($sqlStops);
            $stmtStops->execute([':s' => $startTs, ':e' => $endTs]);
            $stopsRows = $stmtStops->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {}

        // 3. Consultar asistencia y control de colaciones
        $lunchReport = $this->getOperatorLunchReport($date);

        // 4. Consultar despachos comerciales y su valorización en dinero
        $dispatchRows = [];
        $totalDispatchedUnits = 0.0;
        $totalDispatchedMoney = 0.0;
        $distinctDespachoIds = [];
        $usedDeliveryTotals = [];

        try {
            $sqlDisp = "
                SELECT 
                    d.id AS despacho_id,
                    d.numero_documento,
                    d.tipo_documento,
                    d.hora_ingreso,
                    d.hora_salida,
                    d.estado,
                    d.observacion,
                    COALESCE(c.cust_company, c.cust_name, 'Cliente N/D') AS customer_name,
                    o.req_number AS cc_number,
                    od.dlv_docnum AS guia_factura,
                    od.dlv_total_netto,
                    od.dlv_total_brutto,
                    i.item_number_prod,
                    COALESCE(i.item_title, dd.descripcion, 'Producto N/D') AS item_name,
                    dd.salida AS dispatched_units,
                    dd.cantidad AS declared_units,
                    oi.item_sellprice_netto,
                    oi.item_sellprice_netto_dsc,
                    oi.item_amount,
                    i.item_sellprice_netto AS item_catalog_price,
                    t1.trans_name AS transport_company,
                    CONCAT(COALESCE(tc.transports_chofer_nombre, ''), ' ', COALESCE(tc.transports_chofer_paterno, '')) AS driver_name,
                    tv.transports_vh_patente AS vehicle_plate
                FROM despacho d
                INNER JOIN detalle_despacho dd ON d.id = dd.id_despacho
                LEFT JOIN customer c ON c.id = d.id_cliente
                LEFT JOIN transports_chofer tc ON tc.id = d.id_chofer
                LEFT JOIN transports_vehiculo tv ON tv.id = d.id_patente
                LEFT JOIN transports t1 ON t1.id = d.id_transporte
                LEFT JOIN item i ON i.id = dd.id_item
                LEFT JOIN orders_delivery od ON d.numero_documento = od.id
                LEFT JOIN orders o ON o.id = od.dlv_order_id
                LEFT JOIN orders_items oi ON oi.req_id = o.id AND oi.item_id = dd.id_item
                WHERE d.fecha_ingreso BETWEEN :s AND :e
                ORDER BY d.id DESC, dd.id ASC
            ";
            $stmtDisp = $this->erpPdo->prepare($sqlDisp);
            $stmtDisp->execute([':s' => $startTs, ':e' => $endTs]);
            $rawDisp = $stmtDisp->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rawDisp as $dr) {
                $units = (float)($dr['dispatched_units'] ?? $dr['declared_units'] ?? 0);
                if ($units <= 0 && (float)($dr['declared_units'] ?? 0) > 0) {
                    $units = (float)$dr['declared_units'];
                }

                $unitPrice = 0.0;
                if (!empty($dr['item_sellprice_netto']) && (float)$dr['item_sellprice_netto'] > 0) {
                    $unitPrice = (float)$dr['item_sellprice_netto'];
                } elseif (!empty($dr['item_amount']) && (float)$dr['item_amount'] > 0 && !empty($dr['item_sellprice_netto_dsc'])) {
                    $unitPrice = (float)$dr['item_sellprice_netto_dsc'] / (float)$dr['item_amount'];
                } elseif (!empty($dr['item_catalog_price']) && (float)$dr['item_catalog_price'] > 0) {
                    $unitPrice = (float)$dr['item_catalog_price'];
                }

                $lineMoney = round($units * $unitPrice, 2);
                if ($lineMoney <= 0 && !empty($dr['dlv_total_netto']) && (float)$dr['dlv_total_netto'] > 0) {
                    $dlvId = (string)($dr['numero_documento'] ?? '');
                    if (!isset($usedDeliveryTotals[$dlvId])) {
                        $lineMoney = (float)$dr['dlv_total_netto'];
                        $usedDeliveryTotals[$dlvId] = true;
                        if ($units > 0 && $unitPrice <= 0) {
                            $unitPrice = round($lineMoney / $units, 2);
                        }
                    }
                }

                $docNum = trim((string)($dr['guia_factura'] ?? ''));
                if ($docNum === '' || $docNum === '0') {
                    $docNum = trim((string)($dr['numero_documento'] ?? ''));
                }

                $dr['doc_number'] = $docNum;
                $dr['dispatched_units'] = $units;
                $dr['unit_price'] = $unitPrice;
                $dr['total_money'] = $lineMoney;
                $dr['departure_time'] = !empty($dr['hora_salida']) && (string)$dr['hora_salida'] !== '00:00:00' ? substr((string)$dr['hora_salida'], 0, 5) : (!empty($dr['hora_ingreso']) ? substr((string)$dr['hora_ingreso'], 0, 5) : '—');

                $dispatchRows[] = $dr;
                $totalDispatchedUnits += $units;
                $totalDispatchedMoney += $lineMoney;
                $distinctDespachoIds[(int)$dr['despacho_id']] = true;
            }
        } catch (Throwable) {}

        // 5. Procesar y estructurar datos
        $totalGoodUnits = 0.0;
        $totalWasteUnits = 0.0;
        $totalWasteKg = 0.0;
        $totalMeters = 0.0;
        $totalProdHours = 0.0;

        $byProcess = [
            'Corte y Sellado' => ['title' => 'Corte y Sellado', 'icon' => '✂️', 'code' => 'sellado', 'produced' => 0.0, 'waste' => 0.0, 'waste_kg' => 0.0, 'ots_count' => 0],
            'Flexografía' => ['title' => 'Flexografía', 'icon' => '🎨', 'code' => 'flexo', 'produced' => 0.0, 'waste' => 0.0, 'waste_kg' => 0.0, 'ots_count' => 0],
            'Serigrafía' => ['title' => 'Serigrafía', 'icon' => '🖌️', 'code' => 'seri', 'produced' => 0.0, 'waste' => 0.0, 'waste_kg' => 0.0, 'ots_count' => 0],
            'Pulpo Serigráfico' => ['title' => 'Pulpo Serigráfico', 'icon' => '🐙', 'code' => 'pulpo', 'produced' => 0.0, 'waste' => 0.0, 'waste_kg' => 0.0, 'ots_count' => 0],
            'Embalaje' => ['title' => 'Embalaje', 'icon' => '📦', 'code' => 'embalaje', 'produced' => 0.0, 'waste' => 0.0, 'waste_kg' => 0.0, 'ots_count' => 0],
            'Rebobinado' => ['title' => 'Rebobinado', 'icon' => '🔄', 'code' => 'rebo', 'produced' => 0.0, 'waste' => 0.0, 'waste_kg' => 0.0, 'ots_count' => 0],
        ];

        $byMachine = [];
        $criticalWasteOrders = [];
        $activeMachines = [];
        $activeOperators = [];

        foreach ($prodRows as &$r) {
            $g = (float)$r['produced_units'];
            $w = (float)$r['waste_units'];
            $wKg = (float)$r['waste_kg'];
            $m = (float)$r['produced_meters'];
            $hrs = (float)$r['duration_hours'];
            $tot = $g + $w;
            $wRate = $tot > 0 ? round(($w / $tot) * 100, 2) : 0.0;
            $r['waste_rate'] = $wRate;

            $totalGoodUnits += $g;
            $totalWasteUnits += $w;
            $totalWasteKg += $wKg;
            $totalMeters += $m;
            $totalProdHours += $hrs;

            $mName = trim((string)$r['machine_name']) ?: 'Sin Máquina';
            $pName = trim((string)$r['process_name']) ?: 'Otros';
            $opName = trim((string)$r['operator_name']);

            $activeMachines[$mName] = true;
            if ($opName !== '') {
                $activeOperators[$opName] = true;
            }

            // Agrupación por Proceso
            if (!isset($byProcess[$pName])) {
                $byProcess[$pName] = ['title' => $pName, 'icon' => '⚙️', 'code' => $r['process_code'], 'produced' => 0.0, 'waste' => 0.0, 'waste_kg' => 0.0, 'ots_count' => 0];
            }
            $byProcess[$pName]['produced'] += $g;
            $byProcess[$pName]['waste'] += $w;
            $byProcess[$pName]['waste_kg'] += $wKg;
            $byProcess[$pName]['ots_count']++;

            // Agrupación por Máquina
            if (!isset($byMachine[$mName])) {
                $byMachine[$mName] = [
                    'machine_name' => $mName,
                    'process_name' => $pName,
                    'process_code' => $r['process_code'],
                    'ots_count' => 0,
                    'produced_units' => 0.0,
                    'waste_units' => 0.0,
                    'waste_kg' => 0.0,
                    'duration_hours' => 0.0,
                    'operators' => [],
                    'orders' => [],
                ];
            }
            $byMachine[$mName]['ots_count']++;
            $byMachine[$mName]['produced_units'] += $g;
            $byMachine[$mName]['waste_units'] += $w;
            $byMachine[$mName]['waste_kg'] += $wKg;
            $byMachine[$mName]['duration_hours'] += $hrs;
            if ($opName !== '' && !in_array($opName, $byMachine[$mName]['operators'], true)) {
                $byMachine[$mName]['operators'][] = $opName;
            }
            $byMachine[$mName]['orders'][] = $r;

            // Merma Crítica (>5%)
            if ($wRate >= 5.0 && $w > 0) {
                $criticalWasteOrders[] = $r;
            }
        }
        unset($r);

        // Calcular porcentaje global de merma
        $totOverall = $totalGoodUnits + $totalWasteUnits;
        $overallWastePercent = $totOverall > 0 ? round(($totalWasteUnits / $totOverall) * 100, 2) : 0.0;

        // Calcular métricas de eventos / paradas
        $totalStopsMinutes = 0;
        $totalSetupsMinutes = 0;
        $stopsByCategory = [];

        foreach ($stopsRows as &$st) {
            $mins = (int)$st['duration_minutes'];
            $st['start_time'] = !empty($st['evt_crtdat']) ? date('H:i', (int)$st['evt_crtdat']) : '';
            $st['end_time'] = !empty($st['evt_enddat']) ? date('H:i', (int)$st['evt_enddat']) : '';

            if ($st['evt_type'] === 'apertura') {
                $totalSetupsMinutes += $mins;
            } else {
                $totalStopsMinutes += $mins;
            }

            $reason = trim((string)$st['stop_reason']);
            if (!isset($stopsByCategory[$reason])) {
                $stopsByCategory[$reason] = ['count' => 0, 'minutes' => 0];
            }
            $stopsByCategory[$reason]['count']++;
            $stopsByCategory[$reason]['minutes'] += $mins;
        }
        unset($st);

        uasort($stopsByCategory, static fn($a, $b) => $b['minutes'] <=> $a['minutes']);

        // Calcular cumplimiento de colaciones
        $lunchTotalOps = (int)($lunchReport['total_operators'] ?? 0);
        $lunchMissingOps = (int)($lunchReport['missing_count'] ?? 0);
        $lunchCompletedOps = max(0, $lunchTotalOps - $lunchMissingOps);
        $lunchComplianceRate = $lunchTotalOps > 0 ? round(($lunchCompletedOps / $lunchTotalOps) * 100, 1) : 100.0;

        return [
            'date' => $date,
            'formatted_date' => $formattedDate,
            'is_yesterday' => $isYesterday,
            'is_today' => $isToday,
            'prev_date' => $prevDate,
            'next_date' => $nextDate,
            'kpis' => [
                'total_produced_units' => $totalGoodUnits,
                'total_waste_units' => $totalWasteUnits,
                'total_waste_kg' => $totalWasteKg,
                'waste_percent' => $overallWastePercent,
                'total_meters' => $totalMeters,
                'total_prod_hours' => round($totalProdHours, 1),
                'total_stops_hours' => round($totalStopsMinutes / 60, 1),
                'total_stops_minutes' => $totalStopsMinutes,
                'total_setups_hours' => round($totalSetupsMinutes / 60, 1),
                'total_setups_minutes' => $totalSetupsMinutes,
                'active_machines_count' => count($activeMachines),
                'active_operators_count' => max(count($activeOperators), $lunchTotalOps),
                'total_ots_count' => count($prodRows),
                'critical_waste_count' => count($criticalWasteOrders),
                'lunch_compliance_rate' => $lunchComplianceRate,
                'lunch_missing_count' => $lunchMissingOps,
                'total_dispatched_units' => $totalDispatchedUnits,
                'total_dispatched_money' => $totalDispatchedMoney,
                'total_dispatches_count' => count($distinctDespachoIds),
            ],
            'by_process' => array_values(array_filter($byProcess, static fn($p) => $p['produced'] > 0 || $p['waste'] > 0 || $p['ots_count'] > 0)),
            'by_machine' => array_values($byMachine),
            'work_orders' => $prodRows,
            'stops_rows' => $stopsRows,
            'stops_by_category' => $stopsByCategory,
            'critical_waste_orders' => $criticalWasteOrders,
            'attendance' => $lunchReport,
            'dispatches_rows' => $dispatchRows,
            'total_dispatched_money' => $totalDispatchedMoney,
            'total_dispatched_units' => $totalDispatchedUnits,
            'total_dispatches_count' => count($distinctDespachoIds),
        ];
    }

    // =========================================================================
    // SECCIÓN 12: INGRESO DE PRODUCTOS EN BODEGA CON CÓDIGO DE TRAZABILIDAD
    // =========================================================================


}

#[AllowDynamicProperties]
class ReceptionReportingService
{
    use ReceptionReportingServiceTrait;

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
