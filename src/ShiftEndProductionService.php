<?php

declare(strict_types=1);

require_once __DIR__ . '/MachineKind.php';

/**
 * Final del turno · Producción: todo lo que se registró como producido en un día, por máquina (solo lectura del ERP).
 *
 * Día = registros de producción (`prod_worker_ot_events` tipo prod/prodsericolor) y de merma
 * (`prod_worker_ot_defectunits` tipo merma) cuya fecha de registro cae entre las 00:00 y las 23:59:59.
 * La máquina es la del turno del operador (`prod_worker_init.win_equipoid`); si falta, la de la agenda.
 * Los registros de bobina semielaborada (ROLL-SE) se muestran en el detalle pero no suman unidades, igual que el panel.
 */
final class ShiftEndProductionService
{
    private const PROCESS = [7 => 'Flexografía', 11 => 'Serigrafía', 22 => 'Pulpo Serigráfico', 8 => 'Corte y Sellado',
        14 => 'Corte y Sellado', 12 => 'Rebobinado', 15 => 'Embalaje'];
    private const PROCESS_ORDER = ['Flexografía', 'Serigrafía', 'Pulpo Serigráfico', 'Corte y Sellado', 'Rebobinado', 'Embalaje', 'Otro'];

    public function __construct(private PDO $erp)
    {
    }

    /** Caché corta: el día en curso cambia; los días pasados casi no. */
    public function cachedDay(string $date, bool $fresh = false): array
    {
        $date = self::normalizeDate($date);
        $key = 'shift-end-prod-v1-' . $date;
        if ($fresh) {
            ResultCache::forget($key);
        }
        $ttl = $date === date('Y-m-d') ? 60 : 600;
        return (array)ResultCache::remember($key, $ttl, fn() => $this->day($date));
    }

    public static function normalizeDate(string $date): string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', trim($date));
        return $d !== false && $d->format('Y-m-d') === trim($date) ? $d->format('Y-m-d') : date('Y-m-d');
    }

    /** @return array<int,array<string,mixed>> */
    private function rows(string $sql, array $params = []): array
    {
        $st = $this->erp->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function isSemiRoll(?string $comments): bool
    {
        $c = (string)$comments;
        return str_contains($c, 'Bobina Semielaborada') || str_contains($c, 'ROLL-SE-');
    }

    /**
     * @return array{date:string,generated_at:string,machines:array<int,array<string,mixed>>,processes:array<int,array<string,mixed>>,totals:array<string,mixed>}
     */
    public function day(string $date): array
    {
        $date = self::normalizeDate($date);
        $start = (new DateTimeImmutable($date . ' 00:00:00'))->getTimestamp();
        $end = (new DateTimeImmutable($date . ' 23:59:59'))->getTimestamp();

        $joins = 'INNER JOIN prod_worker_ot o ON o.id = ev.evt_prod_worker_otid
            LEFT JOIN prod_worker_init wi ON wi.id = o.wok_init_id
            LEFT JOIN workers w ON w.id = wi.win_wrkid
            LEFT JOIN prod_agenda a ON a.id = o.wok_ag_id
            LEFT JOIN prod_header h ON h.id = a.ag_prdid
            LEFT JOIN orders ord ON ord.id = a.ag_reqid';
        $cols = 'ev.evt_prod_worker_otid AS worker_ot_id, o.wok_ag_id AS ag_id,
            COALESCE(NULLIF(wi.win_equipoid, 0), a.ag_equipo_id) AS machine_id,
            w.wrk_firstname, w.wrk_lastname, wi.win_wrkid,
            a.ag_reqid, a.ag_amount, h.prd_number, ord.req_number';

        $prod = $this->rows("SELECT ev.id, ev.evt_type, ev.evt_amount, ev.evt_crtdat, ev.evt_enddat, ev.evt_comments,
                ev.evt_amount_metros_lineales AS meters, ev.prod_bobina_kg AS kg, $cols
            FROM prod_worker_ot_events ev $joins
            WHERE ev.evt_crtdat BETWEEN ? AND ? AND LOWER(ev.evt_type) IN ('prod','production','prodsericolor')
              AND (ev.evt_status IS NULL OR ev.evt_status > 0)
            ORDER BY ev.evt_crtdat, ev.id", [$start, $end]);

        $waste = $this->rows("SELECT d.id, d.evt_amount, d.evt_kgstounits, d.evt_crtdat, d.evt_comments,
                COALESCE(NULLIF(mt.merma_title, ''), 'Sin tipo') AS waste_type, $cols
            FROM prod_worker_ot_defectunits d
            INNER JOIN prod_worker_ot_events ev ON ev.id = d.evt_refid
            $joins
            LEFT JOIN prod_mermatypes mt ON mt.id = d.evt_merma_typeid
            WHERE d.evt_crtdat BETWEEN ? AND ? AND LOWER(d.evt_type) = 'merma' AND (d.evt_status IS NULL OR d.evt_status > 0)
            ORDER BY d.evt_crtdat, d.id", [$start, $end]);

        // Máquinas y productos (primera línea de cada CC).
        $machineIds = array_unique(array_map('intval', array_merge(array_column($prod, 'machine_id'), array_column($waste, 'machine_id'))));
        $machinesInfo = [];
        if ($machineIds !== []) {
            foreach ($this->rows('SELECT id, equipo_name, equipo_type_id FROM equipo WHERE id IN (' . implode(',', $machineIds) . ')') as $m) {
                $machinesInfo[(int)$m['id']] = $m;
            }
        }
        $reqIds = array_values(array_filter(array_unique(array_map('intval', array_merge(array_column($prod, 'ag_reqid'), array_column($waste, 'ag_reqid'))))));
        $products = [];
        if ($reqIds !== []) {
            foreach ($this->rows('SELECT oi.req_id, oi.item_pos, i.item_number_prod, i.item_title FROM orders_items oi
                    LEFT JOIN item i ON i.id = oi.item_id WHERE oi.req_id IN (' . implode(',', $reqIds) . ') ORDER BY oi.req_id, oi.item_pos') as $p) {
                $products[(int)$p['req_id']] ??= ['sku' => (string)$p['item_number_prod'], 'title' => (string)$p['item_title']];
            }
        }

        $machines = [];
        $base = function (array $r) use (&$machines, $machinesInfo): int {
            $mid = (int)$r['machine_id'];
            if (!isset($machines[$mid])) {
                $info = $machinesInfo[$mid] ?? ['equipo_name' => '', 'equipo_type_id' => 0];
                $type = (int)$info['equipo_type_id'];
                $machines[$mid] = [
                    'machine_id' => $mid,
                    'machine_name' => trim((string)$info['equipo_name']) ?: ($mid > 0 ? 'Máquina ' . $mid : 'Sin máquina'),
                    'process' => MachineKind::isPulpo($mid, (string)$info['equipo_name'], $type) ? 'Pulpo Serigráfico' : (self::PROCESS[$type] ?? 'Otro'),
                    'units' => 0.0, 'meters' => 0.0, 'kg' => 0.0, 'records' => 0, 'semi_records' => 0, 'open_records' => 0,
                    'waste_units' => 0.0, 'waste_kg' => 0.0, 'waste_records' => 0,
                    'operators' => [], 'ots' => [], 'first' => null, 'last' => null,
                    'production' => [], 'waste' => [],
                ];
            }
            return $mid;
        };
        $operator = static fn(array $r): string => trim((string)$r['wrk_firstname'] . ' ' . (string)$r['wrk_lastname'])
            ?: ((int)$r['win_wrkid'] > 0 ? 'Operario #' . (int)$r['win_wrkid'] : 'Sin operador');
        $otKey = function (int $mid, array $r) use (&$machines, $products): string {
            $key = (string)(int)$r['worker_ot_id'];
            if (!isset($machines[$mid]['ots'][$key])) {
                $p = $products[(int)$r['ag_reqid']] ?? ['sku' => '', 'title' => ''];
                $machines[$mid]['ots'][$key] = [
                    'worker_ot_id' => (int)$r['worker_ot_id'],
                    'ot' => trim((string)$r['prd_number']),
                    'cc' => trim((string)$r['req_number']),
                    'sku' => $p['sku'], 'product' => $p['title'],
                    'planned' => $r['ag_amount'] !== null ? (float)$r['ag_amount'] : null,
                    'units' => 0.0, 'waste_units' => 0.0, 'waste_kg' => 0.0,
                ];
            }
            return $key;
        };

        foreach ($prod as $r) {
            $mid = $base($r);
            $m = &$machines[$mid];
            $op = $operator($r);
            $m['operators'][$op] = true;
            $k = $otKey($mid, $r);
            $semi = self::isSemiRoll($r['evt_comments']);
            $amount = (float)$r['evt_amount'];
            $open = (int)$r['evt_enddat'] <= 0;
            $m['records']++;
            if ($semi) {
                $m['semi_records']++;
            } else {
                $m['units'] += $amount;
                $m['ots'][$k]['units'] += $amount;
            }
            if ($open) {
                $m['open_records']++;
            }
            $m['meters'] += (float)$r['meters'];
            $m['kg'] += (float)$r['kg'];
            $ts = (int)$r['evt_crtdat'];
            $m['first'] = $m['first'] === null ? $ts : min($m['first'], $ts);
            $m['last'] = max((int)$m['last'], (int)$r['evt_enddat'] > 0 ? (int)$r['evt_enddat'] : $ts);
            $m['production'][] = [
                'start' => date('H:i', $ts),
                'end' => $open ? null : date((int)$r['evt_enddat'] > $end ? 'd/m H:i' : 'H:i', (int)$r['evt_enddat']),
                'operator' => $op,
                'ot' => $m['ots'][$k]['ot'], 'cc' => $m['ots'][$k]['cc'], 'sku' => $m['ots'][$k]['sku'],
                'units' => $amount, 'meters' => (float)$r['meters'], 'kg' => (float)$r['kg'],
                'semi' => $semi, 'open' => $open, 'comments' => trim((string)$r['evt_comments']),
            ];
            unset($m);
        }
        foreach ($waste as $r) {
            $mid = $base($r);
            $m = &$machines[$mid];
            $op = $operator($r);
            $m['operators'][$op] = true;
            $k = $otKey($mid, $r);
            $m['waste_units'] += (float)$r['evt_amount'];
            $m['waste_kg'] += (float)$r['evt_kgstounits'];
            $m['waste_records']++;
            $m['ots'][$k]['waste_units'] += (float)$r['evt_amount'];
            $m['ots'][$k]['waste_kg'] += (float)$r['evt_kgstounits'];
            $m['waste'][] = [
                'time' => date('H:i', (int)$r['evt_crtdat']), 'operator' => $op,
                'ot' => $m['ots'][$k]['ot'], 'cc' => $m['ots'][$k]['cc'], 'type' => (string)$r['waste_type'],
                'units' => (float)$r['evt_amount'], 'kg' => (float)$r['evt_kgstounits'], 'comments' => trim((string)$r['evt_comments']),
            ];
            unset($m);
        }

        $processes = [];
        $totals = ['machines' => 0, 'units' => 0.0, 'waste_kg' => 0.0, 'records' => 0, 'open_records' => 0];
        foreach ($machines as &$m) {
            $m['operators'] = array_keys($m['operators']);
            $m['ots'] = array_values($m['ots']);
            $m['first'] = $m['first'] !== null ? date('H:i', $m['first']) : null;
            $m['last'] = $m['last'] ? date('H:i', $m['last']) : null;
            $p = $m['process'];
            $processes[$p] ??= ['process' => $p, 'machines' => 0, 'units' => 0.0, 'waste_kg' => 0.0];
            $processes[$p]['machines']++;
            $processes[$p]['units'] += $m['units'];
            $processes[$p]['waste_kg'] += $m['waste_kg'];
            $totals['machines']++;
            $totals['units'] += $m['units'];
            $totals['waste_kg'] += $m['waste_kg'];
            $totals['records'] += $m['records'];
            $totals['open_records'] += $m['open_records'];
        }
        unset($m);
        $rank = array_flip(self::PROCESS_ORDER);
        $machines = array_values($machines);
        usort($machines, static fn($a, $b) => [$rank[$a['process']] ?? 99, $a['machine_name']] <=> [$rank[$b['process']] ?? 99, $b['machine_name']]);
        $processes = array_values($processes);
        usort($processes, static fn($a, $b) => ($rank[$a['process']] ?? 99) <=> ($rank[$b['process']] ?? 99));

        return ['date' => $date, 'generated_at' => date('Y-m-d H:i:s'), 'machines' => $machines, 'processes' => $processes, 'totals' => $totals];
    }
}
