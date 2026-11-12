<?php

declare(strict_types=1);

class MonthlyPresentationService
{
    private ReceptionService $receptionService;
    private PDO $erpPdo;
    /** @var (Closure(): MaintenanceBonusService)|null Crea el cálculo de disponibilidad (necesita la TRZ); solo al usarlo. */
    private ?Closure $maintenanceFactory;

    public function __construct(ReceptionService $receptionService, PDO $erpPdo, ?Closure $maintenanceFactory = null)
    {
        $this->receptionService = $receptionService;
        $this->erpPdo = $erpPdo;
        $this->maintenanceFactory = $maintenanceFactory;
    }

    /** Tipo de máquina del ERP → lámina de disponibilidad de mantención. */
    private const MAINTENANCE_GROUPS = [7 => 'flexo', 11 => 'seri', 8 => 'sellado'];

    /**
     * Disponibilidad de mantención del período del informe (mismo cálculo del bono de mantención):
     * disponible = jornada − colación − preventiva; % real = (disponible − averías) ÷ disponible;
     * utilización = tiempo en producción ÷ tiempo disponible. Las máquinas sin registros van aparte («sin programación»).
     *
     * @param int $endExclusive fin del período (exclusivo)
     */
    public function computeMaintenance(string $monthKey, int $start, int $endExclusive): array
    {
        if ($this->maintenanceFactory === null) {
            throw new RuntimeException('cálculo de mantención no disponible');
        }
        $service = ($this->maintenanceFactory)();
        $period = ['start' => $start, 'end' => $endExclusive];
        $report = $service->report($monthKey, $period);
        $production = $service->productionSeconds($period);
        $groups = ['flexo' => [], 'seri' => [], 'sellado' => []];
        $inactive = ['flexo' => [], 'seri' => [], 'sellado' => []];
        $preventive = [];
        foreach ($report['rows'] as $row) {
            $group = self::MAINTENANCE_GROUPS[(int)$row['type_id']] ?? null;
            if ($group === null || str_contains(strtoupper((string)$row['name']), 'PULPO')) {
                continue;
            }
            $name = rtrim(trim((string)$row['name']), '.');
            if (in_array('Sin registros de actividad', (array)$row['warnings'], true)) {
                $inactive[$group][] = $name;
                continue;
            }
            $available = (float)$row['available'];
            $prod = (float)($production[(int)$row['id']] ?? 0);
            $groups[$group][] = [
                'name' => $name,
                'total' => (float)$row['total'], 'available' => $available, 'preventive' => (float)$row['preventive'],
                'breakdown' => (float)$row['breakdown'], 'setup' => (float)$row['setup'], 'external' => (float)$row['external'],
                'gross' => (float)$row['gross'], 'maintenance' => (float)$row['maintenance'],
                'real' => $row['real'] === null ? null : (float)$row['real'], 'target' => (float)$row['target'],
                'meets' => $row['real'] !== null && (float)$row['real'] >= (float)$row['target'],
                'production' => $prod, 'utilization' => $available > 0 ? min(100.0, $prod / $available * 100) : 0.0,
            ];
            $preventive[(string)round((float)$row['preventive'] / 3600, 2)][] = $name;
        }
        return [
            'days' => (int)$report['calendar']['days'],
            'daily' => (int)MaintenanceBonusService::DAILY_SECONDS,
            'lunch' => (int)$report['calendar']['days'] * MaintenanceBonusService::DAILY_LUNCH_SECONDS,
            'groups' => $groups,
            'inactive' => $inactive,
            'preventive_hours' => $preventive,
        ];
    }

    /** Ruta del historial mensual de KPIs (cifras ya publicadas + meses calculados). */
    private function historyPath(): string
    {
        return __DIR__ . '/../data/kpi_historial_2026.json';
    }

    /**
     * Lee el historial mensual. Estructura: ['meses' => ['2026-01' => [...], ...]].
     *
     * @return array<string, array<string, mixed>>
     */
    private function loadHistory(): array
    {
        $path = $this->historyPath();
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded['meses'] ?? null) ? $decoded['meses'] : [];
    }

    /**
     * Kg de bobinas guardados por mes (al último día del mes; los publicados hasta sep-26, al 25): ['2026-10' => ['pp' => .., ...]].
     *
     * @return array<string, array<string, float>>
     */
    private function loadRollHistory(): array
    {
        $path = $this->historyPath();
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded['bobinas_kg'] ?? null) ? $decoded['bobinas_kg'] : [];
    }

    /** Guarda el historial conservando el comentario y el orden cronológico. */
    private function saveHistory(array $months, ?array $rolls = null): void
    {
        ksort($months);
        $path = $this->historyPath();
        $current = is_file($path) ? json_decode((string)file_get_contents($path), true) : [];
        $payload = ['_comentario' => (string)($current['_comentario'] ?? ''), 'meses' => $months];
        $rolls ??= is_array($current['bobinas_kg'] ?? null) ? $current['bobinas_kg'] : null;
        if ($rolls !== null) {
            ksort($rolls);
            $payload['bobinas_kg'] = $rolls;
        }
        @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Calcula con datos EN VIVO del ERP los indicadores de un MES CALENDARIO (del 1 al último día: 28, 29, 30 o 31).
     * Se usa el mismo período para producción, nivel de servicio y merma. Hasta septiembre 2026 el informe se publicó
     * con el ciclo 26→25: esos meses publicados quedan como están en el historial.
     *
     * @return array<string, mixed>
     */
    private function computeLiveCycle(string $monthKey, ?array $range = null): array
    {
        // Rango (desde/hasta) o mes calendario: el mismo cálculo que los bonos en modo «range».
        [$from, $to] = $range !== null ? [$range['from'], $range['to']] : self::monthBounds($monthKey);
        $period = $this->receptionService->resolveBonusFilterPeriod('range', $monthKey, $from, $to);
        $startDate = (string)$period['start_date'] . ' 00:00:00';
        $endDate = (string)$period['end_date'] . ' 23:59:59';

        // 1. Producción real: lo que pasó por CORTE Y SELLADO.
        $cysRes = $this->receptionService->listErpCysProductionForBonusPeriod($period);
        $prodUnits = 0.0;
        foreach ((array)($cysRes['rows'] ?? []) as $r) {
            $prodUnits += (float)($r['produced_units'] ?? 0);
        }

        // 2. Nivel de servicio. Cada fila trae 'is_on_time' y 'dispatched_units' (no existe 'is_delayed':
        //    antes se buscaba ese campo, nunca se encontraba y todo mes mostraba 708.572 atrasadas).
        $slReport = $this->receptionService->getServiceLevelReport($startDate, $endDate, null, null, 'all', '');
        $slSummary = (array)($slReport['summary'] ?? []);
        $slTotalUnits = 0.0;
        $slDelayedUnits = 0.0;
        $lateRows = [];
        foreach ((array)($slReport['rows'] ?? []) as $r) {
            $u = (float)($r['dispatched_units'] ?? 0);
            $slTotalUnits += $u;
            if (empty($r['is_on_time'])) {
                $slDelayedUnits += $u;
                $lateRows[] = $r;
            }
        }
        // Un pedido atrasado por CC (el informe trae una fila por producto): unidades sumadas y el mayor atraso.
        $byCc = [];
        foreach ($lateRows as $r) {
            $cc = trim((string)($r['cc_number'] ?? '')) !== '' ? (string)$r['cc_number'] : trim((string)($r['doc_type'] ?? '') . ' ' . (string)($r['doc_number'] ?? ''));
            if (!isset($byCc[$cc])) {
                $byCc[$cc] = [
                    'cc_number' => $cc,
                    'ot_number' => '-', // el informe de nivel de servicio no trae el N° de OT
                    'customer_name' => (string)($r['customer_name'] ?? '-'),
                    'product_type' => (string)($r['product_code'] ?? '-'),
                    'units' => 0.0,
                    'required_date' => (string)($r['committed_date'] ?? '-'),
                    'delay_days' => 0,
                ];
            }
            $byCc[$cc]['units'] += (float)($r['dispatched_units'] ?? 0);
            $byCc[$cc]['delay_days'] = max($byCc[$cc]['delay_days'], (int)($r['delay_days'] ?? 0));
        }
        $delayedOrders = array_values($byCc);
        usort($delayedOrders, static fn(array $a, array $b): int => [$b['delay_days'], $b['units']] <=> [$a['delay_days'], $a['units']]);
        $delayedOrders = array_slice($delayedOrders, 0, 12);

        // 3. Merma global del período.
        $wasteReport = $this->receptionService->getErpOperatorWasteReport($startDate, $endDate);
        $wasteSummary = (array)($wasteReport['summary'] ?? []);

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'period_label' => $range !== null ? (string)($period['label'] ?? '') : 'Mes: ' . date('d/m/Y', strtotime($from)) . ' a ' . date('d/m/Y', strtotime($to)),
            'prod_units' => $prodUnits,
            'sl_total_units' => $slTotalUnits,
            'sl_delayed_units' => $slDelayedUnits,
            // C.C. distintas (antes se contaban filas: una por producto despachado).
            'sl_total_cc' => (int)($slSummary['total_cc'] ?? $slSummary['total_dispatches'] ?? 0),
            'sl_delayed_cc' => (int)($slSummary['delayed_cc'] ?? $slSummary['delayed_dispatches'] ?? 0),
            'sl_percent_cc' => (float)($slSummary['service_level_cc_percent'] ?? $slSummary['service_level_percent'] ?? 0),
            'sl_without_agreed_cc' => (int)($slSummary['without_agreed_cc'] ?? 0),
            'delayed_orders' => $delayedOrders,
            'waste_pct' => (float)($wasteSummary['global_waste_percent'] ?? 0),
            'total_waste_kg' => (float)($wasteSummary['total_waste_kg'] ?? 0),
        ];
    }

    /** Tipo de máquina del ERP (equipo_type_id) → proceso del informe. */
    private const PROCESS_TYPES = [7 => 'flexo', 11 => 'seri', 22 => 'seri', 8 => 'cys', 14 => 'cys', 15 => 'emb'];

    /** Bodegas ERP de producto terminado (400, 1000, 700) y de bobinas (PLA = 100, PP = 200). */
    private const FINISHED_GOODS_STOREHOUSES = [1, 14, 10];
    private const ROLL_STOREHOUSES = ['pla' => 5, 'pp' => 11];

    /** Tipos de movimiento del kardex (tran_data) que suman o restan stock. */
    private const KARDEX_IN = ['stockchangeup', 'sthup', 'invoicesbuy', 'invoicesnotessell'];
    private const KARDEX_OUT = ['stockchangedown', 'sthdown', 'ordersdelivery', 'shipment'];

    /** Bodega ERP 5000 (productos inmovilizados): es el stock «sin rotación» de la lámina de bodega. */
    private const IMMOBILIZED_STOREHOUSE = 18;

    /** Artículos del recall Tottus (se muestran aparte dentro de la bodega 5000). */
    private const RECALL_TOTTUS_CODES = ['BOL0352'];

    /** Días sin salidas para considerar un artículo «sin rotación». */
    private const NO_ROTATION_DAYS = 90;

    /** Filtro de eventos de producción válidos (mismo criterio que getCorteSelladoProducedUnits). */
    private const PROD_EVENT_FILTER = "(ev.evt_status IS NULL OR ev.evt_status > 0)
        AND LOWER(ev.evt_type) IN ('production','prod','prodsericolor')
        AND (ev.evt_comments IS NULL OR (ev.evt_comments NOT LIKE '%Bobina Semielaborada%' AND ev.evt_comments NOT LIKE '%ROLL-SE-%'))";

    /**
     * Peso de una bolsa en kg: el del artículo o, si no tiene, la fórmula de la ficha
     * (mismo cálculo que getErpOperatorWasteReport).
     */
    public static function unitWeightKg(array $r): float
    {
        $itemWeight = (float)($r['item_weight'] ?? 0);
        if ($itemWeight > 0) {
            return $itemWeight / 1000.0;
        }
        $w = (float)($r['fab_med_width'] ?? 0);
        $h = (float)($r['fab_med_height'] ?? 0);
        $f = (float)($r['fab_med_fuelle'] ?? 0);
        $g = (float)($r['fab_mat_gramms'] ?? 0);
        $man = (float)($r['fab_manilla_length'] ?? 0);
        if ($w <= 0 || $h <= 0 || $g <= 0) {
            return 0.0;
        }
        $body = (2.0 * ($w + $f) * $h) / 10000.0 * ($g / 1000.0);
        $handle = $man > 0 ? 2.0 * ($man / 100.0) * 0.025 * ($g / 1000.0) : 0.0;
        return $body + $handle;
    }

    /**
     * Datos de la primera línea de cada CC (peso de la bolsa y cantidad pedida).
     *
     * @param int[] $reqIds
     * @return array<int, array{weight: float, ordered: float}>
     */
    private function orderLines(array $reqIds): array
    {
        $out = [];
        $reqIds = array_values(array_unique(array_filter(array_map('intval', $reqIds))));
        foreach (array_chunk($reqIds, 500) as $chunk) {
            $sql = 'SELECT oi.req_id, oi.item_amount, it.item_weight, oi.fab_med_width, oi.fab_med_height, oi.fab_med_fuelle,
                           oi.fab_mat_gramms, oi.fab_manilla_length
                    FROM orders_items oi LEFT JOIN item it ON it.id = oi.item_id
                    WHERE oi.req_id IN (' . implode(',', $chunk) . ')
                    ORDER BY oi.req_id, oi.item_pos';
            foreach ($this->erpPdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $id = (int)$r['req_id'];
                if (!isset($out[$id])) {
                    $out[$id] = ['weight' => self::unitWeightKg($r), 'ordered' => (float)($r['item_amount'] ?? 0)];
                }
            }
        }
        return $out;
    }

    /**
     * Balance de masa por proceso (Flexo, Seri, Corte y Sellado, Embalaje) del período, en kg.
     *
     * - Procesados: unidades producidas en el período × peso de la bolsa.
     * - Programados: cantidad pedida de la CC repartida según la parte de su producción (en ese proceso)
     *   que cayó en el período. Así una CC que se hace en dos meses no se cuenta entera dos veces.
     * - Merma: kg registrados en máquina; si el registro viene solo en unidades, unidades × peso.
     *
     * @return array<string, array{prog: float, proc: float, waste: float}>
     */
    public function computeProcessBalance(int $startTs, int $endTs): array
    {
        $out = [];
        foreach (array_unique(array_values(self::PROCESS_TYPES)) as $p) {
            $out[$p] = ['prog' => 0.0, 'proc' => 0.0, 'waste' => 0.0];
        }
        $types = implode(',', array_keys(self::PROCESS_TYPES));
        $filter = self::PROD_EVENT_FILTER;

        // Producción por CC y tipo de máquina: en el período y en total (para repartir lo pedido).
        $sql = "SELECT eq.equipo_type_id AS tp, a.ag_reqid AS req,
                       SUM(CASE WHEN ev.evt_crtdat BETWEEN :s1 AND :e1 THEN ev.evt_amount ELSE 0 END) AS pu,
                       SUM(ev.evt_amount) AS tot
                FROM prod_agenda a
                INNER JOIN prod_worker_ot o ON o.wok_ag_id = a.id
                INNER JOIN prod_worker_ot_events ev ON ev.evt_prod_worker_otid = o.id
                INNER JOIN equipo eq ON eq.id = a.ag_equipo_id
                WHERE a.ag_reqid IN (
                        SELECT a2.ag_reqid FROM prod_worker_ot_events e2
                        INNER JOIN prod_worker_ot o2 ON o2.id = e2.evt_prod_worker_otid
                        INNER JOIN prod_agenda a2 ON a2.id = o2.wok_ag_id
                        WHERE e2.evt_crtdat BETWEEN :s2 AND :e2)
                  AND eq.equipo_type_id IN ($types)
                  AND $filter
                GROUP BY eq.equipo_type_id, a.ag_reqid
                HAVING pu > 0";
        $stmt = $this->erpPdo->prepare($sql);
        $stmt->execute([':s1' => $startTs, ':e1' => $endTs, ':s2' => $startTs, ':e2' => $endTs]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Merma por CC y tipo de máquina.
        $sqlWaste = "SELECT eq.equipo_type_id AS tp, a.ag_reqid AS req, SUM(d.evt_kgstounits) AS kg, SUM(d.evt_amount) AS units,
                            SUM(CASE WHEN COALESCE(d.evt_kgstounits, 0) > 0 THEN 0 ELSE d.evt_amount END) AS units_without_kg
                     FROM prod_worker_ot_defectunits d
                     INNER JOIN prod_worker_ot_events ev ON ev.id = d.evt_refid
                     INNER JOIN prod_worker_ot o ON o.id = ev.evt_prod_worker_otid
                     INNER JOIN prod_agenda a ON a.id = o.wok_ag_id
                     INNER JOIN equipo eq ON eq.id = a.ag_equipo_id
                     WHERE d.evt_crtdat BETWEEN :s AND :e AND LOWER(d.evt_type) = 'merma' AND eq.equipo_type_id IN ($types)
                     GROUP BY eq.equipo_type_id, a.ag_reqid";
        $stmt = $this->erpPdo->prepare($sqlWaste);
        $stmt->execute([':s' => $startTs, ':e' => $endTs]);
        $wasteRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $lines = $this->orderLines(array_merge(array_column($rows, 'req'), array_column($wasteRows, 'req')));
        foreach ($rows as $r) {
            $p = self::PROCESS_TYPES[(int)$r['tp']] ?? null;
            if ($p === null) {
                continue;
            }
            $line = $lines[(int)$r['req']] ?? ['weight' => 0.0, 'ordered' => 0.0];
            $produced = (float)$r['pu'];
            $share = $produced / max((float)$r['tot'], 1.0);
            $out[$p]['proc'] += $produced * $line['weight'];
            $out[$p]['prog'] += $line['ordered'] * $share * $line['weight'];
        }
        foreach ($wasteRows as $r) {
            $p = self::PROCESS_TYPES[(int)$r['tp']] ?? null;
            if ($p === null) {
                continue;
            }
            $weight = $lines[(int)$r['req']]['weight'] ?? 0.0;
            $out[$p]['waste'] += (float)$r['kg'] + (float)$r['units_without_kg'] * $weight;
        }
        foreach ($out as $p => $v) {
            $out[$p] = ['prog' => round($v['prog'], 1), 'proc' => round($v['proc'], 1), 'waste' => round($v['waste'], 2)];
        }
        return $out;
    }

    /**
     * Stock por artículo y bodega a una fecha: stock actual menos los movimientos del kardex posteriores.
     *
     * @param int[] $storehouses
     * @return array<string, float> clave "item_id:st_id"
     */
    private function stockAt(array $storehouses, int $atTs, ?string $titlePrefixSql = null): array
    {
        $in = implode(',', array_map('intval', $storehouses));
        $titleCond = $titlePrefixSql !== null ? " AND ($titlePrefixSql)" : '';
        $stock = [];
        $sql = "SELECT s.item_id, s.st_id, SUM(s.iss_inventory) AS qty FROM item_shops_storehouses s
                INNER JOIN item i ON i.id = s.item_id
                WHERE s.st_id IN ($in)$titleCond GROUP BY s.item_id, s.st_id";
        foreach ($this->erpPdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $stock[$r['item_id'] . ':' . $r['st_id']] = (float)$r['qty'];
        }
        if ($atTs < time()) {
            $types = "'" . implode("','", array_merge(self::KARDEX_IN, self::KARDEX_OUT)) . "'";
            $stmt = $this->erpPdo->prepare("SELECT t.item_id, t.tran_st_id, t.tran_type, SUM(t.tran_amount) AS qty FROM tran_data t
                    INNER JOIN item i ON i.id = t.item_id
                    WHERE t.tran_crtdat > :at AND t.tran_st_id IN ($in) AND t.tran_type IN ($types)$titleCond
                    GROUP BY t.item_id, t.tran_st_id, t.tran_type");
            $stmt->execute([':at' => $atTs]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $key = $r['item_id'] . ':' . $r['tran_st_id'];
                $sign = in_array($r['tran_type'], self::KARDEX_IN, true) ? -1.0 : 1.0; // se deshace el movimiento
                $stock[$key] = ($stock[$key] ?? 0.0) + $sign * (float)$r['qty'];
            }
        }
        return $stock;
    }

    /** Una bobina PP/PLA no pesa más que esto; un peso mayor en el ERP es un error de digitación (p. ej. 11440 por 114,40). */
    private const ROLL_MAX_KG = 500.0;

    /**
     * Versión del nivel de servicio guardado en el historial. 2 = fecha pactada del pedido vs fecha de la factura,
     * solo despachos confirmados y C.C. distintas; 3 = los días hábiles corren desde la aprobación del pedido.
     * Si cambia, los meses calculados se recalculan.
     */
    private const SERVICE_LEVEL_VERSION = 3;

    /** Versión del cálculo de bobinas guardado en el historial; si cambia, los meses calculados se recalculan. */
    private const ROLL_CALC_VERSION = 3;

    /**
     * Metros de tela por bolsa de los formatos con más rotación (Libro1 «Stock bobinas»):
     * promocionales 36x40x12, 40x40x12, 45x40x12, 58x40x12 y boutique 25x30x7, 30x40x12, 37x40x12, 38x38x18, 48x38x16.
     */
    public const BAG_FORMATS_M = [0.36, 0.40, 0.46, 0.58, 0.33, 0.43, 0.51, 0.58, 0.65];

    /**
     * Bolsas que se podrían fabricar con una bobina (mismo cálculo del Libro1): largo (m) ÷ metros por bolsa,
     * promediado entre los 9 formatos. Las bobinas angostas (manillas, < 50 cm) no cuentan; las de 50 a 99 cm
     * cuentan en proporción a su ancho (en el Libro1 las de 51 cm se contaban como media bobina).
     *
     * @param array<string, mixed> $item item_title, item_reg_width, item_reg_length
     */
    public static function rollBags(array $item): float
    {
        $width = (float)($item['item_reg_width'] ?? 0);
        $length = (float)($item['item_reg_length'] ?? 0);
        // Las medidas del título (ANCHOxGRAMAJExLARGO) mandan: en algunas fichas el ancho está mal (13 por 130).
        if (preg_match('~/(\d+(?:[.,]\d+)?)X(\d+(?:[.,]\d+)?)X(\d+(?:[.,]\d+)?)\s*$~i', (string)($item['item_title'] ?? ''), $m)) {
            $width = (float)str_replace(',', '.', $m[1]);
            $length = (float)str_replace(',', '.', $m[3]);
        }
        if ($length <= 0 || $width < 50) {
            return 0.0;
        }
        $perMeter = array_sum(array_map(static fn(float $f): float => 1 / $f, self::BAG_FORMATS_M)) / count(self::BAG_FORMATS_M);
        return $length * $perMeter * min(1.0, $width / 100);
    }

    /**
     * Kg de una bobina: el peso del artículo (item_reg_kg). Si falta o es imposible (> 500 kg), se usa
     * ancho (cm) × gramaje (g/m²) × largo (m) / 100.000; si la ficha tampoco sirve, el peso dividido por 100.
     *
     * @param array<string, mixed> $item
     */
    public static function rollWeightKg(array $item): float
    {
        $kg = (float)($item['item_reg_kg'] ?? 0);
        $formula = (float)($item['item_reg_width'] ?? 0) * (float)($item['item_reg_gsm'] ?? 0) * (float)($item['item_reg_length'] ?? 0) / 100000.0;
        if ($kg > 0 && $kg <= self::ROLL_MAX_KG) {
            return $kg;
        }
        if ($formula > 0 && $formula <= self::ROLL_MAX_KG) {
            return $formula;
        }
        return $kg > self::ROLL_MAX_KG && $kg / 100.0 <= self::ROLL_MAX_KG ? $kg / 100.0 : 0.0;
    }

    /**
     * Kg y bolsas proyectadas (rollBags) de bobinas PP y PLA (y PLA beige / blanco) al cierre de cada mes indicado.
     *
     * @param array<string, int> $cutoffs month_key => timestamp de cierre
     * @return array<string, array<string, float>> pp, pla, pla_beige, pla_blanco (kg) y las mismas con sufijo _bolsas
     */
    public function computeRollStock(array $cutoffs): array
    {
        $titles = "i.item_title LIKE 'PLA/%' OR i.item_title LIKE 'PP/%'";
        $info = [];
        $sql = 'SELECT i.id, i.item_title, i.item_reg_kg, i.item_reg_width, i.item_reg_gsm, i.item_reg_length FROM item i WHERE ' . $titles;
        foreach ($this->erpPdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $info[(int)$r['id']] = ['title' => strtoupper((string)$r['item_title']), 'kg' => self::rollWeightKg($r), 'bags' => self::rollBags($r)];
        }
        $out = [];
        foreach ($cutoffs as $monthKey => $ts) {
            $row = ['pp' => 0.0, 'pla' => 0.0, 'pla_beige' => 0.0, 'pla_blanco' => 0.0,
                'pp_bolsas' => 0.0, 'pla_bolsas' => 0.0, 'pla_beige_bolsas' => 0.0, 'pla_blanco_bolsas' => 0.0];
            foreach ($this->stockAt(array_values(self::ROLL_STOREHOUSES), $ts, $titles) as $key => $qty) {
                [$itemId, $stId] = array_map('intval', explode(':', $key));
                if ($qty <= 0 || !isset($info[$itemId])) {
                    continue;
                }
                $kg = $qty * $info[$itemId]['kg'];
                $bags = $qty * $info[$itemId]['bags'];
                $title = $info[$itemId]['title'];
                $keys = [];
                if ($stId === self::ROLL_STOREHOUSES['pp'] && str_starts_with($title, 'PP/')) {
                    $keys = ['pp'];
                } elseif ($stId === self::ROLL_STOREHOUSES['pla'] && str_starts_with($title, 'PLA/')) {
                    $keys = ['pla'];
                    if (str_contains($title, '/BEIGE/')) {
                        $keys[] = 'pla_beige';
                    } elseif (str_contains($title, '/BLANCO/')) {
                        $keys[] = 'pla_blanco';
                    }
                }
                foreach ($keys as $k) {
                    $row[$k] += $kg;
                    $row[$k . '_bolsas'] += $bags;
                }
            }
            $out[$monthKey] = array_map(static fn($v) => round($v), $row);
        }
        return $out;
    }

    /**
     * Producto terminado al cierre: stock por cliente y rotación (artículos sin salidas en 90 días).
     * El cliente de un artículo es el que más CC/NV lo ha pedido.
     *
     * @return array<string, mixed>
     */
    public function computeFinishedGoods(int $atTs): array
    {
        $stock = [];
        foreach ($this->stockAt(self::FINISHED_GOODS_STOREHOUSES, $atTs) as $key => $qty) {
            $itemId = (int)explode(':', $key)[0];
            $stock[$itemId] = ($stock[$itemId] ?? 0.0) + $qty;
        }
        $stock = array_filter($stock, static fn($q) => $q > 0);
        $immobilized = $this->computeImmobilized($atTs);
        if ($stock === []) {
            return ['by_customer' => [], 'total' => 0, 'moved' => 0, 'still' => 0, 'still_by_customer' => [], 'immobilized' => $immobilized];
        }

        // Cliente principal de cada artículo.
        $customerOf = [];
        $counts = [];
        foreach (array_chunk(array_keys($stock), 500) as $chunk) {
            $sql = 'SELECT oi.item_id, o.req_cust_id, COUNT(*) AS n FROM orders_items oi INNER JOIN orders o ON o.id = oi.req_id
                    WHERE oi.item_id IN (' . implode(',', $chunk) . ') AND o.req_cust_id > 0 GROUP BY oi.item_id, o.req_cust_id';
            foreach ($this->erpPdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $item = (int)$r['item_id'];
                if (!isset($counts[$item]) || (int)$r['n'] > $counts[$item]) {
                    $counts[$item] = (int)$r['n'];
                    $customerOf[$item] = (int)$r['req_cust_id'];
                }
            }
        }
        $names = [];
        $ids = array_values(array_unique($customerOf));
        if ($ids !== []) {
            foreach ($this->erpPdo->query('SELECT id, cust_name FROM customer WHERE id IN (' . implode(',', $ids) . ')')->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $names[(int)$r['id']] = self::shortCustomerName((string)$r['cust_name']);
            }
        }

        // Artículos con al menos una salida en los 90 días previos al cierre.
        $in = implode(',', self::FINISHED_GOODS_STOREHOUSES);
        $outTypes = "'" . implode("','", self::KARDEX_OUT) . "'";
        $stmt = $this->erpPdo->prepare("SELECT DISTINCT item_id FROM tran_data
                WHERE tran_crtdat BETWEEN :from AND :to AND tran_st_id IN ($in) AND tran_type IN ($outTypes)");
        $stmt->execute([':from' => $atTs - self::NO_ROTATION_DAYS * 86400, ':to' => $atTs]);
        $moved = array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []), true);

        $byCustomer = [];
        $stillByCustomer = [];
        $total = 0.0;
        $movedUnits = 0.0;
        foreach ($stock as $itemId => $qty) {
            $name = $names[$customerOf[$itemId] ?? 0] ?? 'Sin cliente';
            $byCustomer[$name] = ($byCustomer[$name] ?? 0.0) + $qty;
            $total += $qty;
            if (isset($moved[$itemId])) {
                $movedUnits += $qty;
            } else {
                $stillByCustomer[$name] = ($stillByCustomer[$name] ?? 0.0) + $qty;
            }
        }
        arsort($byCustomer);
        arsort($stillByCustomer);
        return [
            'by_customer' => array_map(static fn($v) => round($v), $byCustomer),
            'total' => round($total),
            'moved' => round($movedUnits),
            'still' => round($total - $movedUnits),
            'still_by_customer' => array_map(static fn($v) => round($v), $stillByCustomer),
            'immobilized' => $immobilized,
        ];
    }

    /**
     * Stock consolidado por cliente al día de hoy (día de la descarga), para compararlo con el del cierre del mes.
     *
     * @return array{date: string, by_customer: array<string, float>, total: float}|null null si no se pudo calcular
     */
    public function stockToday(): ?array
    {
        try {
            $fg = $this->computeFinishedGoods(time());
        } catch (Throwable) {
            return null;
        }
        return ['date' => date('d-m-Y'), 'by_customer' => (array)($fg['by_customer'] ?? []), 'total' => (float)($fg['total'] ?? 0)];
    }

    /**
     * Bodega 5000 (productos inmovilizados) al cierre: total, recall Tottus (BOL0352) y el resto.
     * Es el «stock sin rotación» de la presentación (así se publicó en septiembre: recall 104.692 al 30-09).
     *
     * @return array{total: float, recall: float, others: float}
     */
    public function computeImmobilized(int $atTs): array
    {
        $byItem = [];
        foreach ($this->stockAt([self::IMMOBILIZED_STOREHOUSE], $atTs) as $key => $qty) {
            $itemId = (int)explode(':', $key)[0];
            $byItem[$itemId] = ($byItem[$itemId] ?? 0.0) + $qty;
        }
        $byItem = array_filter($byItem, static fn($q) => $q > 0);
        $recallIds = [];
        if ($byItem !== []) {
            $codes = implode(',', array_map(fn(string $c): string => (string)$this->erpPdo->quote($c), self::RECALL_TOTTUS_CODES));
            $recallIds = array_map('intval', $this->erpPdo->query("SELECT id FROM item WHERE item_number_prod IN ($codes)")->fetchAll(PDO::FETCH_COLUMN) ?: []);
        }
        $total = array_sum($byItem);
        $recall = 0.0;
        foreach ($recallIds as $id) {
            $recall += $byItem[$id] ?? 0.0;
        }
        return ['total' => round($total), 'recall' => round($recall), 'others' => round($total - $recall)];
    }

    /** Nombre corto del cliente para los gráficos: sin razón social (S.A., SpA, Ltda.). */
    public static function shortCustomerName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        $short = preg_replace('/[\s,]+(S\.?\s?A\.?|SpA|S\.?P\.?A\.?|Ltda\.?|Limitada|E\.?I\.?R\.?L\.?)(\s.*)?$/iu', '', $name) ?? $name;
        $short = preg_replace('/\s+(Chile|Comercial)$/iu', '', trim($short)) ?? $short;
        $short = preg_replace('/^(Hipermercados|Supermercados|Cadena Comercial|Comercial|Sociedad Comercial)\s+/iu', '', trim($short)) ?? $short;
        return $short !== '' ? trim(mb_substr($short, 0, 28)) : $name;
    }

    /**
     * Primer y último día (Y-m-d) del mes calendario 'YYYY-MM': 28 o 29 en febrero, 30 o 31 en el resto.
     *
     * @return array{0:string,1:string}
     */
    public static function monthBounds(string $monthKey): array
    {
        $first = new DateTimeImmutable($monthKey . '-01');
        return [$first->format('Y-m-d'), $first->format('Y-m-t')];
    }

    /** Timestamp del cierre de un mes 'YYYY-MM' (último día del mes, 23:59:59). */
    private static function monthEndTs(string $monthKey): int
    {
        return (int)strtotime(self::monthBounds($monthKey)[1] . ' 23:59:59');
    }

    /**
     * Ciclo de producción en curso que contiene `$today`: del 26 del mes anterior al 25 (si hoy es 26 o después,
     * del 26 de este mes al 25 del siguiente). Es el ciclo de la proyección de cierre (diapositiva 3), igual que el
     * tablero del ERP; el resto del informe usa el mes calendario.
     *
     * @return array{0:DateTimeImmutable,1:DateTimeImmutable}
     */
    public static function currentCycle(DateTimeImmutable $today): array
    {
        $first = $today->modify('first day of this month')->setTime(0, 0, 0);
        $base = (int)$today->format('j') >= 26 ? $first : $first->modify('-1 month');
        $start = $base->setDate((int)$base->format('Y'), (int)$base->format('n'), 26);
        $endMonth = $base->modify('+1 month');
        $end = $endMonth->setDate((int)$endMonth->format('Y'), (int)$endMonth->format('n'), 25)->setTime(23, 59, 59);
        return [$start, $end];
    }

    /**
     * Proyección de cierre (diapositiva 3) del CICLO EN CURSO (26 al 25), sea cual sea el mes del informe.
     * «Fabricado» = Corte y Sellado desde el 26 hasta hoy; «cierre proyectado» = promedio por día hábil cumplido
     * × días hábiles del ciclo (mismo cálculo que la tarjeta del panel).
     *
     * @return array<string, mixed>
     */
    private function closingProjection(): array
    {
        if (!function_exists('unibagBuildClosingProjection')) {
            require_once __DIR__ . '/Http/Reports/DashboardAndGraphicsModule.php';
        }
        $zone = new DateTimeZone('America/Santiago');
        [$start, $end] = self::currentCycle(new DateTimeImmutable('today', $zone));
        $from = $start->format('Y-m-d H:i:s');
        $to = $end->format('Y-m-d H:i:s');
        // Misma consulta y misma caché que la tarjeta del panel.
        $kpis = (array)unibagDashboardCached('kpis', $from, $to, fn() => $this->receptionService->getErpOnlyProductionDashboardKpis($from, $to));
        $made = (float)($kpis['produced_units'] ?? 0.0);
        $p = unibagBuildClosingProjection($this->receptionService, $start, $end);
        $projected = $p['projected'] === null ? $made : max((float)$p['projected'], $made);
        return [
            'made' => $made,
            'queue' => max(0.0, $projected - $made),
            'projected' => $projected,
            'closed' => false,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'today' => (new DateTimeImmutable('now', $zone))->format('d/m/Y'),
            'elapsed_days' => (int)$p['elapsed_days'],
            'total_days' => (int)$p['total_days'],
            'remaining_days' => (int)$p['remaining_days'],
            'daily_avg' => (float)$p['daily_avg'],
        ];
    }

    /**
     * Obtiene todos los indicadores del mes y el historial (enero a ese mes) para los gráficos.
     *
     * Reglas (automatización):
     * - El mes pedido se calcula SIEMPRE en vivo con el ERP.
     * - Los meses anteriores salen del historial (data/kpi_historial_2026.json): cifras ya publicadas.
     * - Si falta un mes anterior, se calcula en vivo y se guarda en el historial (origen 'calculado'),
     *   así el informe del mes siguiente lo encuentra solo.
     * - No hay valores de respaldo inventados: si no hay datos, se informa en 'warnings'.
     *
     * @param string $monthKey Formato 'YYYY-MM' (mes calendario: del 1 al último día)
     * @return array<string, mixed>
     */
    public function getMonthlyMetrics(string $monthKey = '', ?string $from = null, ?string $to = null): array
    {
        $range = self::normalizeRange($from, $to);
        if ($range !== null) {
            // Rango libre: el «mes» del informe es el de la fecha final.
            $monthKey = substr($range['to'], 0, 7);
        }
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthKey)) {
            $monthKey = date('Y-m');
        }
        // La página y la descarga piden lo mismo: se calcula una vez. Mes cerrado: 6 h; mes en curso: 5 min.
        // Si se corrige data/kpi_historial_2026.json a mano, el caché se descarta (va en la clave la fecha del archivo).
        $endTs = $range !== null ? (int)strtotime($range['to'] . ' 23:59:59') : self::monthEndTs($monthKey);
        $closed = $endTs < time();
        $suffix = $range !== null ? '-r' . $range['from'] . '_' . $range['to'] : '';
        $historyStamp = (string)@filemtime($this->historyPath());
        $cacheKey = 'monthly-mes-v' . self::ROLL_CALC_VERSION . '.' . self::SERVICE_LEVEL_VERSION . '.b2-' . $monthKey . $suffix . '-' . $historyStamp;
        $cached = ResultCache::get($cacheKey, $closed ? 21600 : 300, $found);
        if ($found && is_array($cached)) {
            $cached['issued_at'] = date('d-m-Y');
            // La proyección es del ciclo en curso (cambia día a día): no se toma del caché del mes.
            $cached['projection'] = $this->closingProjection();
            // El stock «al día de la descarga» es del día: si lo guardado es de otro día, se recalcula.
            if (($cached['stock_today']['date'] ?? '') !== date('d-m-Y')) {
                $cached['stock_today'] = $this->stockToday();
            }
            return $cached;
        }
        $metrics = $this->computeMonthlyMetrics($monthKey, $range);
        // La clave se arma con la fecha del historial DESPUÉS del cálculo (el cálculo puede guardar meses nuevos).
        clearstatcache();
        ResultCache::put('monthly-mes-v' . self::ROLL_CALC_VERSION . '.' . self::SERVICE_LEVEL_VERSION . '.b2-' . $monthKey . $suffix . '-' . (string)@filemtime($this->historyPath()), $metrics);
        return $metrics;
    }

    /**
     * Rango válido (Y-m-d, desde ≤ hasta, máximo 366 días) o null si no se pidió o no sirve.
     *
     * @return array{from:string,to:string}|null
     */
    public static function normalizeRange(?string $from, ?string $to): ?array
    {
        $from = trim((string)$from);
        $to = trim((string)$to);
        $ok = static fn(string $d): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1
            && ($o = DateTimeImmutable::createFromFormat('!Y-m-d', $d)) !== false && $o->format('Y-m-d') === $d;
        if (!$ok($from) || !$ok($to) || $from > $to) {
            return null;
        }
        $days = (int)(new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days;
        return $days <= 366 ? ['from' => $from, 'to' => $to] : null;
    }

    /** «01/10/2026 al 31/10/2026» y la etiqueta corta para la columna de los gráficos («01/10–31/10»). */
    private static function rangeLabels(array $range): array
    {
        $f = new DateTimeImmutable($range['from']);
        $t = new DateTimeImmutable($range['to']);
        $sameYear = $f->format('Y') === $t->format('Y');
        return [
            'title' => 'del ' . $f->format('d/m/Y') . ' al ' . $t->format('d/m/Y'),
            'column' => $f->format('d/m') . '–' . $t->format('d/m') . ($sameYear ? '' : '/' . $t->format('y')),
        ];
    }

    /** @return array<string, mixed> */
    private function computeMonthlyMetrics(string $monthKey, ?array $range = null): array
    {
        clearstatcache();
        $year = (int)substr($monthKey, 0, 4);
        $monthNum = (int)substr($monthKey, 5, 2);
        $warnings = [];
        $capacityDefault = (float)(Env::get('PPTX_CAPACIDAD_MENSUAL_UNIDADES', '1345000') ?? 1345000);

        $stored = $this->loadHistory();
        $storedChanged = false;
        $live = $this->computeLiveCycle($monthKey, $range);
        $liveStartTs = (int)strtotime($live['start_date']);
        $liveEndTs = (int)strtotime($live['end_date']);
        $monthClosed = $liveEndTs < time();

        $history = [];
        for ($m = 1; $m <= $monthNum; $m++) {
            $key = sprintf('%04d-%02d', $year, $m);
            $isPublished = ($stored[$key]['origen'] ?? '') === 'plantilla';
            // Meses calculados con el ciclo antiguo 26→25 (sin 'periodo' = 'mes') o con el nivel de servicio anterior
            // (comparaba contra la fecha de la factura): se recalculan.
            if (isset($stored[$key]) && !$isPublished && (($stored[$key]['periodo'] ?? '') !== 'mes'
                || (int)($stored[$key]['nivel_servicio'] ?? 1) < self::SERVICE_LEVEL_VERSION)) {
                unset($stored[$key]);
                $storedChanged = true;
            }
            if ($range !== null) {
                // Con rango: los meses que terminaron antes del inicio del rango salen del historial como siempre;
                // los que se cruzan con el rango no se muestran (el rango ocupa su lugar en los gráficos, al final).
                if ($key === $monthKey) {
                    $labels = self::rangeLabels($range);
                    $history[] = [
                        'origen' => 'rango',
                        'month_key' => $key,
                        'label' => $labels['column'],
                        'prod_units' => $live['prod_units'],
                        'prod_cap' => round((float)($stored[$key]['prod_cap'] ?? $capacityDefault) * (($liveEndTs - $liveStartTs + 1) / 86400) / 30.4, 0),
                        'sl_total_units' => $live['sl_total_units'],
                        'sl_delayed_units' => $live['sl_delayed_units'],
                        'sl_total_cc' => $live['sl_total_cc'],
                        'sl_delayed_cc' => $live['sl_delayed_cc'],
                        'waste_pct' => $live['waste_pct'],
                        'procesos' => $this->computeProcessBalance($liveStartTs, $liveEndTs),
                    ];
                    continue;
                }
                if (self::monthEndTs($key) >= $liveStartTs) {
                    continue;
                }
            }
            if ($key === $monthKey && !$isPublished) {
                $entry = [
                    'origen' => 'calculado',
                    'periodo' => 'mes',
                    'nivel_servicio' => self::SERVICE_LEVEL_VERSION,
                    'prod_units' => $live['prod_units'],
                    'prod_cap' => (float)($stored[$key]['prod_cap'] ?? $capacityDefault),
                    'sl_total_units' => $live['sl_total_units'],
                    'sl_delayed_units' => $live['sl_delayed_units'],
                    'sl_total_cc' => $live['sl_total_cc'],
                    'sl_delayed_cc' => $live['sl_delayed_cc'],
                    'waste_pct' => $live['waste_pct'],
                    'procesos' => $this->computeProcessBalance($liveStartTs, $liveEndTs),
                ];
                if ($monthClosed) {
                    // Mes terminado: queda guardado para los informes siguientes.
                    $stored[$key] = $entry;
                    $storedChanged = true;
                }
            } elseif (isset($stored[$key])) {
                $entry = $stored[$key];
                if (!isset($entry['procesos'])) {
                    // Meses publicados sin kg por proceso: se calculan con el ciclo con que se publicaron (26→25).
                    $p = $this->receptionService->resolveBonusFilterPeriod('period', $key);
                    $entry['procesos'] = $this->computeProcessBalance(
                        (int)strtotime($p['start_date'] . ' 00:00:00'),
                        (int)strtotime($p['end_date'] . ' 23:59:59')
                    );
                    $stored[$key]['procesos'] = $entry['procesos'];
                    $storedChanged = true;
                }
            } else {
                $past = $this->computeLiveCycle($key);
                $entry = [
                    'origen' => 'calculado',
                    'periodo' => 'mes',
                    'nivel_servicio' => self::SERVICE_LEVEL_VERSION,
                    'prod_units' => $past['prod_units'],
                    'prod_cap' => $capacityDefault,
                    'sl_total_units' => $past['sl_total_units'],
                    'sl_delayed_units' => $past['sl_delayed_units'],
                    'sl_total_cc' => $past['sl_total_cc'],
                    'sl_delayed_cc' => $past['sl_delayed_cc'],
                    'waste_pct' => $past['waste_pct'],
                    'procesos' => $this->computeProcessBalance((int)strtotime($past['start_date']), (int)strtotime($past['end_date'])),
                ];
                $stored[$key] = $entry;
                $storedChanged = true;
            }
            $entry['month_key'] = $key;
            $history[] = $entry;
        }

        // Bobinas: 13 cierres (último día de cada mes) hasta el mes del informe. Los meses cerrados se guardan; el en curso
        // usa el stock de hoy. Los publicados en la PPT (origen 'publicado', sep-25 a sep-26, al 25) quedan como están;
        // los calculados con el corte antiguo del 25 (sin 'corte') o con un cálculo anterior se recalculan al último día del mes.
        $rollHistory = $this->loadRollHistory();
        $rollsChanged = false;
        $cutoffs = [];
        for ($i = 12; $i >= 0; $i--) {
            $k = date('Y-m', (int)strtotime($monthKey . '-01 -' . $i . ' months'));
            $cutoffs[$k] = $k === $monthKey ? min($liveEndTs, time()) : self::monthEndTs($k);
        }
        // Se recalculan también los guardados con un cálculo de kg anterior (sin 'calculo' = ROLL_CALC_VERSION).
        $missing = array_filter($cutoffs, static fn($ts, $k) => !isset($rollHistory[$k]) || $k === $monthKey
            || (($rollHistory[$k]['origen'] ?? '') !== 'publicado'
                && (!isset($rollHistory[$k]['corte']) || (int)($rollHistory[$k]['calculo'] ?? 1) < self::ROLL_CALC_VERSION)), ARRAY_FILTER_USE_BOTH);
        $computed = [];
        try {
            $computed = $this->computeRollStock($missing);
        } catch (Throwable $e) {
            $warnings[] = 'No se pudo calcular el stock de bobinas: ' . $e->getMessage();
        }
        $rolls = [];
        foreach ($cutoffs as $k => $ts) {
            $row = $computed[$k] ?? $rollHistory[$k] ?? null;
            if (isset($computed[$k]) && $ts < time() && ($k !== $monthKey || ($monthClosed && $range === null))) {
                $rollHistory[$k] = $computed[$k] + ['corte' => date('Y-m-d', $ts), 'calculo' => self::ROLL_CALC_VERSION];
                $rollsChanged = true;
            }
            if ($row !== null) {
                $rolls[] = ['month_key' => $k] + $row;
            }
        }

        if ($storedChanged || $rollsChanged) {
            $this->saveHistory($stored, $rollsChanged ? $rollHistory : null);
        }

        $finished = ['by_customer' => [], 'total' => 0, 'moved' => 0, 'still' => 0, 'still_by_customer' => []];
        try {
            $finished = $this->computeFinishedGoods(min($liveEndTs, time()));
        } catch (Throwable $e) {
            $warnings[] = 'No se pudo calcular el stock de producto terminado: ' . $e->getMessage();
        }
        $currentEntry = end($history) ?: [];
        $projection = $this->closingProjection();
        $stockToday = $this->stockToday();
        $maintenance = null;
        try {
            $maintenance = $this->computeMaintenance($monthKey, $liveStartTs, min($liveEndTs, time()) + 1);
        } catch (Throwable $e) {
            $warnings[] = 'No se pudo calcular la disponibilidad de mantención: ' . $e->getMessage();
        }

        // Cifras del mes: las del historial si el mes ya fue publicado; si no, las calculadas recién.
        $cur = $currentEntry;
        $prodUnits = (float)($cur['prod_units'] ?? 0);
        $prodMeta = 1000000.0;
        if ($prodUnits <= 0) {
            $warnings[] = 'El ERP no devolvió producción de Corte y Sellado para el período ' . $live['period_label'] . '.';
        }
        if ((int)($cur['sl_total_cc'] ?? 0) <= 0) {
            $warnings[] = 'El ERP no devolvió despachos para el período ' . $live['period_label'] . '.';
        }
        $slTotalUnits = (float)($cur['sl_total_units'] ?? 0);
        $slDelayedUnits = (float)($cur['sl_delayed_units'] ?? 0);
        $slTotalCc = (int)($cur['sl_total_cc'] ?? 0);
        $slDelayedCc = (int)($cur['sl_delayed_cc'] ?? 0);
        $slPercentUnits = $slTotalUnits > 0 ? max(0.0, min(100.0, ($slTotalUnits - $slDelayedUnits) / $slTotalUnits * 100.0)) : 0.0;
        $slPercentCc = $slTotalCc > 0 ? max(0.0, min(100.0, ($slTotalCc - $slDelayedCc) / $slTotalCc * 100.0)) : 0.0;
        if (count($rolls) < 13) {
            $warnings[] = 'Bobinas: solo hay ' . count($rolls) . ' de 13 meses con stock calculado.';
        }

        $labels = $range !== null ? self::rangeLabels($range) : null;
        return [
            'month_key' => $monthKey,
            'range' => $range,
            'period_title' => $labels['title'] ?? '',
            'column_label' => $labels['column'] ?? '',
            'start_date' => $live['start_date'],
            'end_date' => $live['end_date'],
            'period_label' => $live['period_label'],
            'month_closed' => $monthClosed,
            'issued_at' => date('d-m-Y'),
            'stock_date' => date('d-m-Y', min($liveEndTs, time())),
            'origin' => (string)($cur['origen'] ?? 'calculado'),
            'prod_units' => $prodUnits,
            'prod_meta' => $prodMeta,
            'prod_cumpl' => $prodMeta > 0 ? ($prodUnits / $prodMeta * 100.0) : 0.0,
            'prod_cap' => (float)($cur['prod_cap'] ?? $capacityDefault),
            'sl_total_units' => $slTotalUnits,
            'sl_delayed_units' => $slDelayedUnits,
            'sl_percent_units' => $slPercentUnits,
            'sl_total_cc' => $slTotalCc,
            'sl_delayed_cc' => $slDelayedCc,
            'sl_percent_cc' => $slPercentCc,
            'delayed_orders' => $live['delayed_orders'],
            'global_waste_percent' => (float)($cur['waste_pct'] ?? $live['waste_pct']),
            'total_waste_kg' => $live['total_waste_kg'],
            'procesos' => (array)($cur['procesos'] ?? []),
            'projection' => $projection,
            'finished_goods' => $finished,
            'stock_today' => $stockToday,
            'maintenance' => $maintenance,
            'rolls' => $rolls,
            'history' => $history,
            'warnings' => $warnings,
        ];
    }

    /**
     * Genera la presentación PowerPoint en disco y retorna la ruta completa al archivo generado.
     */
    public function generatePresentation(string $monthKey = '', ?string $from = null, ?string $to = null): string
    {
        $metrics = $this->getMonthlyMetrics($monthKey, $from, $to);
        $monthKey = (string)$metrics['month_key'];
        
        $templatePath = __DIR__ . '/../data/plantilla_informe_mensual.pptx';
        if (!file_exists($templatePath)) {
            throw new RuntimeException("Plantilla maestra PPTX no encontrada en {$templatePath}");
        }

        $tmpJson = tempnam(sys_get_temp_dir(), 'kpis_') . '.json';
        file_put_contents($tmpJson, json_encode($metrics, JSON_UNESCAPED_UNICODE));

        $outputFilename = 'KPIS_OPERACIONES_' . strtoupper(date('F_Y', strtotime($monthKey . '-01'))) . '.pptx';
        $targetDataPath = __DIR__ . '/../data/' . $outputFilename;
        $uniqueOutput = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kpis_pres_' . $monthKey . '_' . uniqid() . '.pptx';

        $scriptPath = __DIR__ . '/../scripts/generate_monthly_pptx.py';
        $pythonBin = $this->resolvePythonBinary();

        $cmd = sprintf(
            '%s "%s" --template="%s" --month="%s" --data="%s" --output="%s" 2>&1',
            escapeshellcmd($pythonBin),
            $scriptPath,
            $templatePath,
            $monthKey,
            $tmpJson,
            $uniqueOutput
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);
        $actualOutput = $uniqueOutput;
        if (!file_exists($actualOutput)) {
            $altOutput = preg_replace('/\.pptx$/i', '_SOLO_MODIFICADAS.pptx', $uniqueOutput);
            if (file_exists($altOutput)) {
                $actualOutput = $altOutput;
            }
        }

        if ($returnCode !== 0 || !file_exists($actualOutput)) {
            $err = implode("\n", $output);
            if (str_contains($err, 'ModuleNotFoundError') || str_contains($err, 'No module named')) {
                $err .= "\n[Tip]: En la VPS ejecute: 'cd /var/www/innovaimtech/unibagqa && ./venv/bin/pip install -r requirements.txt' o 'pip3 install -r requirements.txt'";
            }
            throw new RuntimeException("Error al ejecutar generador Python: " . $err);
        }

        // Intentar actualizar la copia en data/ si no está abierta por otro programa (solo el informe del mes, no un rango).
        if (empty($metrics['range'])) {
            @copy($actualOutput, $targetDataPath);
        }

        return $actualOutput;
    }

    /**
     * Vista previa en HTML de la presentación (todas las láminas), dibujada desde el PPTX generado
     * con scripts/pptx_preview.py. Se guarda 10 minutos para no regenerar al pasar de lámina.
     */
    public function generatePreviewHtml(string $monthKey = '', ?string $from = null, ?string $to = null): string
    {
        $range = self::normalizeRange($from, $to);
        $key = 'pptx-preview-' . md5(json_encode([$monthKey, $range, (string)@filemtime($this->historyPath())]));
        return (string)ResultCache::remember($key, 600, function () use ($monthKey, $range): string {
            $pptx = $this->generatePresentation($monthKey, $range['from'] ?? null, $range['to'] ?? null);
            $html = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kpis_preview_' . uniqid() . '.html';
            $cmd = sprintf('%s "%s" "%s" "%s" 2>&1', escapeshellcmd($this->resolvePythonBinary()),
                __DIR__ . '/../scripts/pptx_preview.py', $pptx, $html);
            $output = [];
            $rc = 0;
            exec($cmd, $output, $rc);
            @unlink($pptx);
            if ($rc !== 0 || !is_file($html)) {
                throw new RuntimeException('No se pudo armar la vista previa: ' . implode("\n", $output));
            }
            $out = (string)file_get_contents($html);
            @unlink($html);
            return $out;
        });
    }

    /**
     * Resuelve el binario de Python a ejecutar.
     * Prioriza variable de entorno PYTHON_BIN, venv local del proyecto y finalmente python3/python del sistema.
     */
    private function resolvePythonBinary(): string
    {
        // 1. Variable de entorno personalizada
        $envBinary = getenv('PYTHON_BIN') ?: getenv('PYTHON_PATH') ?: ($_ENV['PYTHON_BIN'] ?? ($_ENV['PYTHON_PATH'] ?? null));
        if (!empty($envBinary) && (is_file((string)$envBinary) || $this->commandExists((string)$envBinary))) {
            return (string)$envBinary;
        }

        // 2. Entorno virtual (venv o .venv) en la raíz del proyecto
        $projectRoot = dirname(__DIR__);
        $isWindows = DIRECTORY_SEPARATOR === '\\';

        $venvCandidates = $isWindows
            ? [
                $projectRoot . '/venv/Scripts/python.exe',
                $projectRoot . '/.venv/Scripts/python.exe',
            ]
            : [
                $projectRoot . '/venv/bin/python3',
                $projectRoot . '/venv/bin/python',
                $projectRoot . '/.venv/bin/python3',
                $projectRoot . '/.venv/bin/python',
            ];

        foreach ($venvCandidates as $cand) {
            if (file_exists($cand) && (is_executable($cand) || $isWindows)) {
                return $cand;
            }
        }

        // 3. Binarios del sistema
        if ($isWindows) {
            return 'python';
        }

        if ($this->commandExists('python3')) {
            return 'python3';
        }

        return 'python';
    }

    private function commandExists(string $cmd): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $where = @shell_exec('where ' . escapeshellarg($cmd) . ' 2>NUL');
            return !empty(trim((string)$where));
        }

        $which = @shell_exec('which ' . escapeshellarg($cmd) . ' 2>/dev/null');
        return !empty(trim((string)$which));
    }
}

