<?php

declare(strict_types=1);

require_once __DIR__ . '/MachineKind.php';

/**
 * Qué está haciendo cada máquina en este momento (solo lectura del ERP).
 *
 * Una máquina «tiene producción» cuando hay un turno abierto en ella (prod_worker_init.win_status 1, o 3 si el
 * operador la dejó en pausa para ir a otra) de las últimas MAX_SESSION_HOURS horas. Su estado sale del evento
 * abierto (sin fecha de término) de la OT abierta en ese turno:
 *   pausa o colación → «En pausa» · mantención → «En mantención» · producción → «Produciendo» ·
 *   apertura → «Preparando» · sin evento abierto → «OT abierta, sin producir» · sin OT → «Turno abierto, sin OT».
 */
final class MachineLiveStatusService
{
    /** Turnos abiertos más antiguos se consideran olvidados (no se muestran como actividad del momento). */
    public const MAX_SESSION_HOURS = 36;

    private const PROCESS_NAMES = [7 => 'Flexografía', 8 => 'Corte y Sellado', 11 => 'Serigrafía', 22 => 'Pulpo Serigráfico',
        12 => 'Rebobinado', 14 => 'Corte y Sellado', 15 => 'Embalaje'];

    /** Puestos que no son máquinas de producción. */
    private const NOT_MACHINES = ['SUPERVISOR', 'MANTENCION', 'MANTENCIÓN', 'BODEGA', 'CHOFER', 'ASEO'];

    public function __construct(private PDO $erp)
    {
    }

    /** @return array<int,array<string,mixed>> */
    private function rows(string $sql, array $params = []): array
    {
        $st = $this->erp->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function in(array $ids): string
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($v) => $v > 0)));
        return $ids === [] ? '0' : implode(',', $ids);
    }

    /**
     * @return array{machines:array<int,array<string,mixed>>,counts:array<string,int>,generated_at:string}
     */
    public function snapshot(?int $now = null): array
    {
        $now ??= time();
        $since = $now - self::MAX_SESSION_HOURS * 3600;
        $sessions = $this->rows('SELECT wi.id AS init_id, wi.win_equipoid AS machine_id, wi.win_status, wi.win_crtdat, wi.win_wrkid,
                e.equipo_name, e.equipo_type_id, w.wrk_firstname, w.wrk_lastname
            FROM prod_worker_init wi
            LEFT JOIN equipo e ON e.id = wi.win_equipoid
            LEFT JOIN workers w ON w.id = wi.win_wrkid
            WHERE wi.win_status IN (1,3) AND wi.win_crtdat >= ?
            ORDER BY wi.id DESC', [$since]);
        $sessions = array_values(array_filter($sessions, static function (array $s): bool {
            $name = strtoupper((string)($s['equipo_name'] ?? ''));
            foreach (self::NOT_MACHINES as $word) {
                if (str_contains($name, $word)) {
                    return false;
                }
            }
            return (int)$s['machine_id'] > 0;
        }));
        if ($sessions === []) {
            return ['machines' => [], 'counts' => ['total' => 0, 'producing' => 0, 'paused' => 0, 'other' => 0], 'generated_at' => date('Y-m-d H:i:s', $now)];
        }
        // OT abiertas de esos turnos (la más reciente de cada turno).
        $ots = [];
        foreach ($this->rows('SELECT o.id, o.wok_init_id, o.wok_ag_id, o.wok_crtdat FROM prod_worker_ot o
                WHERE o.wok_status = 1 AND o.wok_init_id IN (' . self::in(array_column($sessions, 'init_id')) . ') ORDER BY o.id DESC') as $o) {
            $ots[(int)$o['wok_init_id']] ??= $o;
        }
        $otIds = array_map(static fn($o) => (int)$o['id'], $ots);
        $agIds = array_map(static fn($o) => (int)$o['wok_ag_id'], $ots);
        // Eventos abiertos (sin término) de esas OT.
        $open = [];
        foreach ($this->rows("SELECT ev.id, ev.evt_prod_worker_otid, ev.evt_type, ev.evt_crtdat, ev.evt_pause_id, p.pause_name
                FROM prod_worker_ot_events ev LEFT JOIN prod_pause_types p ON p.id = ev.evt_pause_id
                WHERE ev.evt_prod_worker_otid IN (" . self::in($otIds) . ") AND ev.evt_status > 0
                  AND (ev.evt_enddat IS NULL OR ev.evt_enddat = 0) ORDER BY ev.id DESC") as $e) {
            $open[(int)$e['evt_prod_worker_otid']][] = $e;
        }
        // Agenda: CC, cantidad programada y producto (primera línea de la CC).
        $agendas = [];
        foreach ($this->rows('SELECT a.id, a.ag_reqid, a.ag_amount, a.ag_equipotype_id, ord.req_number,
                    (SELECT oi.item_id FROM orders_items oi WHERE oi.req_id = a.ag_reqid ORDER BY oi.item_pos LIMIT 1) AS item_id
                FROM prod_agenda a LEFT JOIN orders ord ON ord.id = a.ag_reqid WHERE a.id IN (' . self::in($agIds) . ')') as $a) {
            $agendas[(int)$a['id']] = $a;
        }
        $items = [];
        foreach ($this->rows('SELECT id, item_number_prod, item_title FROM item WHERE id IN (' . self::in(array_column($agendas, 'item_id')) . ')') as $it) {
            $items[(int)$it['id']] = $it;
        }
        // Producido en la agenda (todas sus OT), sin la bobina semielaborada.
        $produced = [];
        foreach ($this->rows("SELECT o.wok_ag_id, SUM(ev.evt_amount) AS qty FROM prod_worker_ot o
                JOIN prod_worker_ot_events ev ON ev.evt_prod_worker_otid = o.id
                WHERE o.wok_ag_id IN (" . self::in($agIds) . ") AND ev.evt_type IN ('prod','prodsericolor') AND ev.evt_status > 0
                  AND (ev.evt_comments IS NULL OR (ev.evt_comments NOT LIKE '%Bobina Semielaborada%' AND ev.evt_comments NOT LIKE '%ROLL-SE-%'))
                GROUP BY o.wok_ag_id") as $p) {
            $produced[(int)$p['wok_ag_id']] = (float)$p['qty'];
        }

        // Una fila por máquina: el turno activo (1) manda sobre el pausado (3); entre iguales, el más reciente.
        $byMachine = [];
        foreach ($sessions as $s) {
            $mid = (int)$s['machine_id'];
            $cur = $byMachine[$mid] ?? null;
            if ($cur === null || ((int)$cur['win_status'] !== 1 && (int)$s['win_status'] === 1)) {
                $byMachine[$mid] = $s;
            }
        }
        $machines = [];
        $counts = ['total' => 0, 'producing' => 0, 'paused' => 0, 'other' => 0];
        foreach ($byMachine as $mid => $s) {
            $ot = $ots[(int)$s['init_id']] ?? null;
            $events = $ot !== null ? ($open[(int)$ot['id']] ?? []) : [];
            [$state, $key, $detail, $since] = self::state($ot, $events, (int)$s['win_status']);
            $ag = $ot !== null ? ($agendas[(int)$ot['wok_ag_id']] ?? null) : null;
            $item = $ag !== null ? ($items[(int)$ag['item_id']] ?? null) : null;
            $type = (int)($s['equipo_type_id'] ?: ($ag['ag_equipotype_id'] ?? 0));
            $isPulpo = MachineKind::isPulpo($mid, (string)$s['equipo_name'], $type);
            $machines[] = [
                'machine_id' => $mid,
                'machine_name' => trim((string)$s['equipo_name']) ?: ('Máquina ' . $mid),
                'process' => $isPulpo ? 'Pulpo Serigráfico' : (self::PROCESS_NAMES[$type] ?? 'Otro'),
                'state' => $state, 'state_key' => $key, 'state_detail' => $detail,
                'state_since' => $since > 0 ? date('Y-m-d H:i', $since) : '',
                'minutes' => $since > 0 ? max(0, intdiv($now - $since, 60)) : null,
                'operator' => trim((string)$s['wrk_firstname'] . ' ' . (string)$s['wrk_lastname']) ?: ('Operario #' . (int)$s['win_wrkid']),
                'shift_since' => date('Y-m-d H:i', (int)$s['win_crtdat']),
                'worker_ot_id' => $ot !== null ? (int)$ot['id'] : null,
                'cc' => (string)($ag['req_number'] ?? ''),
                'order_id' => (int)($ag['ag_reqid'] ?? 0),
                'sku' => (string)($item['item_number_prod'] ?? ''),
                'product' => (string)($item['item_title'] ?? ''),
                'planned' => $ag !== null ? (int)round((float)$ag['ag_amount']) : null,
                'produced' => $ag !== null ? (int)round($produced[(int)$ag['id']] ?? 0) : null,
            ];
            $counts['total']++;
            $counts[$key === 'producing' ? 'producing' : ($key === 'paused' ? 'paused' : 'other')]++;
        }
        $order = ['producing' => 0, 'setup' => 1, 'paused' => 2, 'maintenance' => 3, 'idle' => 4, 'no_ot' => 5, 'away' => 6];
        usort($machines, static fn($a, $b) => [$order[$a['state_key']] ?? 9, $a['machine_name']] <=> [$order[$b['state_key']] ?? 9, $b['machine_name']]);
        return ['machines' => $machines, 'counts' => $counts, 'generated_at' => date('Y-m-d H:i:s', $now)];
    }

    /**
     * @param array<int,array<string,mixed>> $events eventos abiertos de la OT, del más nuevo al más antiguo
     * @return array{0:string,1:string,2:string,3:int} estado, clave, detalle, desde (timestamp)
     */
    public static function state(?array $ot, array $events, int $winStatus): array
    {
        if ($winStatus === 3) {
            return ['Turno en pausa', 'away', 'El operador está trabajando en otra máquina', 0];
        }
        if ($ot === null) {
            return ['Turno abierto, sin OT', 'no_ot', '', 0];
        }
        $pick = static function (array $types) use ($events): ?array {
            foreach ($events as $e) {
                if (in_array(strtolower((string)$e['evt_type']), $types, true)) {
                    return $e;
                }
            }
            return null;
        };
        // Una pausa o mantención abierta manda sobre la producción abierta (la máquina está detenida).
        if (($e = $pick(['pause'])) !== null) {
            $name = trim((string)($e['pause_name'] ?? ''));
            return ['En pausa', 'paused', $name !== '' ? $name : 'Pausa', (int)$e['evt_crtdat']];
        }
        if (($e = $pick(['mantencion'])) !== null) {
            return ['En mantención', 'maintenance', '', (int)$e['evt_crtdat']];
        }
        if (($e = $pick(['prod', 'prodsericolor'])) !== null) {
            return ['Produciendo', 'producing', '', (int)$e['evt_crtdat']];
        }
        if (($e = $pick(['apertura'])) !== null) {
            return ['Preparando', 'setup', 'Puesta a punto / apertura', (int)$e['evt_crtdat']];
        }
        return ['OT abierta, sin producir', 'idle', '', (int)($ot['wok_crtdat'] ?? 0)];
    }
}
