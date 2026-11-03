<?php

declare(strict_types=1);

/**
 * Ficha técnica completa de lo fabricado en UNA máquina de una OT (Trazabilidad > Fabricación en Planta).
 *
 * Reúne: medidas programadas, corte y rodillo, clichés, colores y anilox por unidad, tintas usadas,
 * producción real, mermas, aprobaciones (líder / supervisor) y alistamiento.
 *
 * Fuentes (todas ya usadas en otras pantallas del sistema):
 *  - ERP: prod_agenda, prod_header, orders, orders_items, item, equipo, equipo_params, prod_anilox_ot,
 *         prod_anilox_ot_worker, prod_worker_ot(+_events, _defectunits, _autocontrol), user, workers.
 *  - TRZ (opcional, si hay conexión): cliches / cliche_usage_logs, flexo_anilox_ink_configs, events.
 *
 * Cada bloque se consulta por separado: si falla uno (tabla distinta, sin permisos), el resto se muestra igual
 * y el bloque que falló queda marcado en "avisos".
 */
final class FabricationDossierService
{
    public function __construct(private PDO $erp, private ?PDO $trz = null)
    {
    }

    /** @return array<string,mixed>|null */
    public function forEvent(int $eventId): ?array
    {
        $stmt = $this->erp->prepare(
            'SELECT evt.id AS event_id, pwo.id AS worker_ot_id, pwo.wok_ag_id AS ag_id
             FROM prod_worker_ot_events evt
             INNER JOIN prod_worker_ot pwo ON pwo.id = evt.evt_prod_worker_otid
             WHERE evt.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $eventId]);
        $ref = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($ref) || (int)$ref['ag_id'] <= 0) {
            return null;
        }
        return $this->forAgenda((int)$ref['ag_id']);
    }

    /** @return array<string,mixed>|null */
    public function forAgenda(int $agId): ?array
    {
        $warnings = [];
        $plan = $this->safe($warnings, 'programación', fn() => $this->plan($agId));
        if (!is_array($plan)) {
            return null;
        }

        $isFlexo = !empty($plan['is_flexo']);
        $cut = $isFlexo ? $this->safe($warnings, 'corte y rodillo', fn() => $this->cutAndRoller($plan)) : null;

        $colors = [];
        for ($i = 1; $i <= 6; $i++) {
            $desc = trim((string)($plan['color_' . $i] ?? ''));
            if ($desc !== '') {
                $colors[$i] = $desc;
            }
        }

        $anilox = $isFlexo ? ($this->safe($warnings, 'anilox', fn() => $this->anilox($agId, (int)($plan['req_id'] ?? 0))) ?? []) : [];
        $inks = $this->trz !== null ? ($this->safe($warnings, 'tintas', fn() => $this->inks($agId)) ?? []) : [];
        $units = $this->mergeUnits($colors, $anilox, $inks);

        return [
            'ag_id' => $agId,
            'plan' => [
                'machine' => (string)($plan['equipo_name'] ?? ''),
                'process' => (string)($plan['type_ant_title'] ?? ''),
                'ot' => (string)($plan['prd_number'] ?? ''),
                'customer' => (string)($plan['cust_name'] ?? ''),
                'design' => (string)($plan['fab_design_name'] ?? ''),
                'product' => trim((string)($plan['item_number_prod'] ?? '') . ' ' . (string)($plan['item_title'] ?? '')),
                'planned_units' => (float)($plan['ag_amount'] ?? 0),
                'waste_percent_planned' => (float)($plan['req_operador_mermaperc'] ?? 0),
                'date' => (string)($plan['ag_date'] ?? ''),
                'width' => (float)($plan['fab_med_width'] ?? 0),
                'height' => (float)($plan['fab_med_height'] ?? 0),
                'gusset' => (float)($plan['fab_med_fuelle'] ?? 0),
                'print_width' => (float)($plan['fab_print_width'] ?? 0),
                'print_height' => (float)($plan['fab_print_height'] ?? 0),
                'handle_length' => (float)($plan['fab_manilla_length'] ?? 0),
                'grams' => (float)($plan['fab_mat_gramms'] ?? 0),
                'fabric_color' => (string)($plan['fabric_color'] ?? ''),
            ],
            'cut' => $cut,
            'cliche' => [
                'code' => trim((string)($plan['req_infoaddprd_cliche_codigo'] ?? '')),
                'location' => trim((string)($plan['req_infoaddprd_cliche_ubicacion'] ?? '')),
                'requested_at' => $this->fmtDate((int)($plan['req_cliche_peli_solic_dat'] ?? 0)),
                'received_at' => $this->fmtDate((int)($plan['req_cliche_peli_recep_dat'] ?? 0)),
                'assigned' => $this->trz !== null ? ($this->safe($warnings, 'clichés asignados', fn() => $this->clichesAssigned((string)($plan['prd_number'] ?? ''))) ?? []) : [],
            ],
            'units' => $units,
            'production' => $this->safe($warnings, 'producción', fn() => $this->production($agId)),
            'wastes' => $this->safe($warnings, 'mermas', fn() => $this->wastes($agId)) ?? [],
            'setup' => $this->safe($warnings, 'alistamiento', fn() => $this->setup($agId)) ?? [],
            'approvals' => $this->safe($warnings, 'aprobaciones', fn() => $this->approvals($agId, (string)($plan['prd_number'] ?? ''))) ?? [],
            'warnings' => $warnings,
        ];
    }

    private function safe(array &$warnings, string $label, callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            error_log('Ficha de fabricación (' . $label . '): ' . $e->getMessage());
            $warnings[] = $label;
            return null;
        }
    }

    private function fmtDate(int $ts): string
    {
        return $ts > 0 ? date('d/m/Y', $ts) : '';
    }

    private function fmtDateTime(int $ts): string
    {
        return $ts > 0 ? date('d/m/Y H:i', $ts) : '';
    }

    /** @return array<string,mixed>|null */
    private function plan(int $agId): ?array
    {
        $stmt = $this->erp->prepare(
            "SELECT t0.id AS ag_id, t0.ag_amount, t0.ag_date, t0.ag_equipo_id, t0.ag_reqid AS req_id,
                    teq.equipo_name, teq.equipo_prod_isprinter_flexo, teq.equipo_prod_isprinter_seri, teq.equipo_type_id,
                    ttype.type_ant_title,
                    t3x.prd_number, t4.cust_name, t1.req_number,
                    t1.req_operador_mermaperc, t1.req_solic_devprints_poltype,
                    t1.req_infoaddprd_cliche_ubicacion, t1.req_infoaddprd_cliche_codigo,
                    t1.req_cliche_peli_solic_dat, t1.req_cliche_peli_recep_dat,
                    t1x.fab_med_width, t1x.fab_med_height, t1x.fab_med_fuelle, t1x.fab_print_width, t1x.fab_print_height,
                    t1x.fab_manilla_length, t1x.fab_design_name, t1x.fab_mat_gramms,
                    t1x.fab_print_colordesc_1 AS color_1, t1x.fab_print_colordesc_2 AS color_2, t1x.fab_print_colordesc_3 AS color_3,
                    t1x.fab_print_colordesc_4 AS color_4, t1x.fab_print_colordesc_5 AS color_5, t1x.fab_print_colordesc_6 AS color_6,
                    t2x.item_number_prod, t2x.item_title, t2x.item_prodcalc_fuelle_act,
                    v1.add_name AS fabric_color
             FROM prod_agenda t0
             LEFT OUTER JOIN prod_header t3x ON t0.ag_prdid = t3x.id
             LEFT OUTER JOIN orders t1 ON t0.ag_reqid = t1.id
             LEFT OUTER JOIN customer t4 ON t1.req_cust_id = t4.id
             LEFT OUTER JOIN orders_items t1x ON t1.id = t1x.req_id
             LEFT OUTER JOIN item t2x ON t1x.item_id = t2x.id
             LEFT OUTER JOIN tran_comments_vals v1 ON t1x.fab_mat_fabric_color = v1.id
             LEFT OUTER JOIN equipo teq ON t0.ag_equipo_id = teq.id
             LEFT OUTER JOIN equipo_type ttype ON teq.equipo_type_id = ttype.id
             WHERE t0.id = :ag LIMIT 1"
        );
        $stmt->execute([':ag' => $agId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $row['is_flexo'] = ((int)($row['equipo_type_id'] ?? 0) === 7)
            || ((int)($row['equipo_prod_isprinter_flexo'] ?? 0) === 1)
            || stripos((string)($row['equipo_name'] ?? ''), 'flexo') !== false
            || stripos((string)($row['type_ant_title'] ?? ''), 'flexo') !== false;
        return $row;
    }

    /**
     * Rodillo (Z), corte por bolsa y metros a imprimir: misma regla que usa el panel de flexografía
     * (WorkOrderAgendaService::attachFlexoMetrics).
     *
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private function cutAndRoller(array $plan): array
    {
        $paramMedida = (int)($plan['fab_med_width'] ?? 0);
        if ((int)($plan['item_prodcalc_fuelle_act'] ?? 0) === 1) {
            $paramMedida += (int)($plan['fab_med_fuelle'] ?? 0);
        }
        $stmt = $this->erp->prepare('SELECT * FROM equipo_params WHERE param_equipo_id = :eq AND param_medida >= :med ORDER BY param_medida ASC LIMIT 1');
        $stmt->execute([':eq' => (int)($plan['ag_equipo_id'] ?? 0), ':med' => $paramMedida]);
        $param = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $z = 0;
        $cutM = 0.0;
        if (is_array($param)) {
            $z = (int)($param['param_z'] ?? 0);
            $cutM = (string)($plan['req_solic_devprints_poltype'] ?? '') === 'pol170'
                ? (float)($param['param_poly17'] ?? 0)
                : (float)(!empty($param['param_poly28']) ? $param['param_poly28'] : ($param['param_corte'] ?? 0));
        }
        if ($cutM <= 0.0) {
            $cutM = round(((float)($plan['fab_med_height'] ?? 0) + (float)($plan['fab_med_fuelle'] ?? 0)) / 100, 4);
        }
        $units = (float)($plan['ag_amount'] ?? 0);
        $mlinProg = round($units * $cutM);
        $mlinExtra = round($mlinProg / 100 * (float)($plan['req_operador_mermaperc'] ?? 0));

        return [
            'roller_z' => $z,
            'cut_m' => $cutM,
            'linear_meters_planned' => $mlinProg,
            'linear_meters_extra' => $mlinExtra,
            'linear_meters_to_print' => $mlinProg + $mlinExtra,
            'polymer_type' => (string)($plan['req_solic_devprints_poltype'] ?? ''),
            'measure_used' => $paramMedida,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function anilox(int $agId, int $reqId): array
    {
        $stmt = $this->erp->prepare(
            'SELECT w.paow_unidad, w.paow_posicion, w.paow_color, w.paow_anilox,
                    i.item_number_prod AS anilox_code, i.item_title AS anilox_title
             FROM prod_anilox_ot ot
             INNER JOIN prod_anilox_ot_worker w ON w.paow_ot_worker_id = ot.id
             LEFT JOIN item i ON i.id = w.paow_anilox
             WHERE ot.prod_anilox_agid = :ag AND ot.prod_anilox_reqid = :req
             ORDER BY w.paow_unidad ASC, w.paow_posicion ASC'
        );
        $stmt->execute([':ag' => $agId, ':req' => $reqId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    private function inks(int $agId): array
    {
        $stmt = $this->trz->prepare(
            'SELECT unidad, posicion, color_label, anilox_id, ink_code, ink_name, loaded_kg, leftover_kg, consumed_kg, is_mixed, mixture_notes
             FROM flexo_anilox_ink_configs WHERE ag_id = :ag ORDER BY unidad ASC, posicion ASC'
        );
        $stmt->execute([':ag' => $agId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Une, por unidad de impresión: color programado, anilox usado y tinta consumida.
     *
     * @param array<int,string> $colors
     * @param array<int,array<string,mixed>> $anilox
     * @param array<int,array<string,mixed>> $inks
     * @return array<int,array<string,mixed>>
     */
    private function mergeUnits(array $colors, array $anilox, array $inks): array
    {
        $units = [];
        foreach ($colors as $n => $desc) {
            $units[$n] = ['unit' => $n, 'color_planned' => $desc, 'color_used' => '', 'anilox' => '', 'ink' => '', 'consumed_kg' => null, 'mixed' => false, 'notes' => ''];
        }
        foreach ($anilox as $row) {
            $n = (int)($row['paow_posicion'] ?? 0) > 0 ? (int)$row['paow_posicion'] : (int)($row['paow_unidad'] ?? 0);
            if ($n <= 0) {
                continue;
            }
            $units[$n] ??= ['unit' => $n, 'color_planned' => '', 'color_used' => '', 'anilox' => '', 'ink' => '', 'consumed_kg' => null, 'mixed' => false, 'notes' => ''];
            $units[$n]['color_used'] = trim((string)($row['paow_color'] ?? ''));
            $code = trim((string)($row['anilox_code'] ?? ''));
            $title = trim((string)($row['anilox_title'] ?? ''));
            $units[$n]['anilox'] = (int)($row['paow_anilox'] ?? 0) > 0 ? trim($code . ($title !== '' ? ' - ' . $title : '')) : '';
        }
        foreach ($inks as $row) {
            $n = (int)($row['posicion'] ?? 0) > 0 ? (int)$row['posicion'] : (int)($row['unidad'] ?? 0);
            if ($n <= 0) {
                continue;
            }
            $units[$n] ??= ['unit' => $n, 'color_planned' => '', 'color_used' => '', 'anilox' => '', 'ink' => '', 'consumed_kg' => null, 'mixed' => false, 'notes' => ''];
            $ink = trim((string)($row['ink_code'] ?? '') . ' ' . (string)($row['ink_name'] ?? ''));
            $units[$n]['ink'] = $ink;
            $units[$n]['consumed_kg'] = (float)($row['consumed_kg'] ?? 0);
            $units[$n]['mixed'] = !empty($row['is_mixed']);
            $units[$n]['notes'] = trim((string)($row['mixture_notes'] ?? ''));
        }
        ksort($units);
        return array_values($units);
    }

    /** @return array<int,array<string,mixed>> */
    private function clichesAssigned(string $otCode): array
    {
        if ($otCode === '') {
            return [];
        }
        $stmt = $this->trz->prepare(
            'SELECT DISTINCT c.code, c.description
             FROM cliche_usage_logs log
             INNER JOIN cliches c ON c.id = log.cliche_id
             INNER JOIN work_orders wo ON wo.id = log.work_order_id
             WHERE wo.ot_code = :ot
             ORDER BY c.code ASC'
        );
        $stmt->execute([':ot' => $otCode]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string,mixed> */
    private function production(int $agId): array
    {
        $stmt = $this->erp->prepare(
            "SELECT COUNT(*) AS turns,
                    COALESCE(SUM(evt.evt_amount), 0) AS units,
                    COALESCE(SUM(evt.evt_amount_metros_lineales), 0) AS meters,
                    COALESCE(SUM(evt.prod_bobina_kg), 0) AS kg,
                    MIN(evt.evt_crtdat) AS first_at,
                    MAX(CASE WHEN evt.evt_enddat > 0 THEN evt.evt_enddat ELSE evt.evt_crtdat END) AS last_at
             FROM prod_worker_ot_events evt
             INNER JOIN prod_worker_ot pwo ON pwo.id = evt.evt_prod_worker_otid
             WHERE pwo.wok_ag_id = :ag AND LOWER(evt.evt_type) IN ('prod','production','prodsericolor')
               AND (evt.evt_status IS NULL OR evt.evt_status > 0)
               AND (evt.evt_comments IS NULL OR (evt.evt_comments NOT LIKE '%Bobina Semielaborada%' AND evt.evt_comments NOT LIKE '%ROLL-SE-%'))"
        );
        $stmt->execute([':ag' => $agId]);
        $tot = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $this->erp->prepare(
            "SELECT COALESCE(NULLIF(TRIM(CONCAT(w.wrk_firstname, ' ', w.wrk_lastname)), ''), 'No asignado') AS operator,
                    COALESCE(SUM(evt.evt_amount), 0) AS units, COUNT(*) AS turns
             FROM prod_worker_ot_events evt
             INNER JOIN prod_worker_ot pwo ON pwo.id = evt.evt_prod_worker_otid
             LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
             LEFT JOIN workers w ON w.id = pwi.win_wrkid
             WHERE pwo.wok_ag_id = :ag AND LOWER(evt.evt_type) IN ('prod','production','prodsericolor')
               AND (evt.evt_status IS NULL OR evt.evt_status > 0)
             GROUP BY operator ORDER BY units DESC"
        );
        $stmt->execute([':ag' => $agId]);

        return [
            'turns' => (int)($tot['turns'] ?? 0),
            'units' => (float)($tot['units'] ?? 0),
            'meters' => (float)($tot['meters'] ?? 0),
            'kg' => (float)($tot['kg'] ?? 0),
            'first_at' => $this->fmtDateTime((int)($tot['first_at'] ?? 0)),
            'last_at' => $this->fmtDateTime((int)($tot['last_at'] ?? 0)),
            'operators' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function wastes(int $agId): array
    {
        $stmt = $this->erp->prepare(
            "SELECT COALESCE(NULLIF(mt.merma_title, ''), 'Sin tipo') AS title,
                    COALESCE(SUM(d.evt_amount), 0) AS units, COALESCE(SUM(d.evt_kgstounits), 0) AS kg
             FROM prod_worker_ot_defectunits d
             INNER JOIN prod_worker_ot_events e ON e.id = d.evt_refid
             INNER JOIN prod_worker_ot pwo ON pwo.id = e.evt_prod_worker_otid
             LEFT JOIN prod_mermatypes mt ON mt.id = d.evt_merma_typeid
             WHERE pwo.wok_ag_id = :ag AND LOWER(d.evt_type) = 'merma'
             GROUP BY d.evt_merma_typeid, title ORDER BY units DESC"
        );
        $stmt->execute([':ag' => $agId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    private function setup(int $agId): array
    {
        $stmt = $this->erp->prepare(
            "SELECT evt.evt_crtdat, evt.evt_enddat, evt.evt_comments,
                    COALESCE(NULLIF(TRIM(CONCAT(w.wrk_firstname, ' ', w.wrk_lastname)), ''), '') AS operator,
                    COALESCE(TRIM(CONCAT(wa.wrk_firstname, ' ', wa.wrk_lastname)), '') AS helper
             FROM prod_worker_ot_events evt
             INNER JOIN prod_worker_ot pwo ON pwo.id = evt.evt_prod_worker_otid
             LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
             LEFT JOIN workers w ON w.id = pwi.win_wrkid
             LEFT JOIN workers wa ON wa.id = evt.evt_idayudante
             WHERE pwo.wok_ag_id = :ag AND evt.evt_type = 'apertura'
             ORDER BY evt.evt_crtdat ASC"
        );
        $stmt->execute([':ag' => $agId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $start = (int)($r['evt_crtdat'] ?? 0);
            $end = (int)($r['evt_enddat'] ?? 0);
            $out[] = [
                'start' => $this->fmtDateTime($start),
                'end' => $end > 0 ? $this->fmtDateTime($end) : 'En proceso',
                'minutes' => ($end > $start && $start > 0) ? (int)round(($end - $start) / 60) : null,
                'operator' => (string)($r['operator'] ?? ''),
                'helper' => trim((string)($r['helper'] ?? '')),
                'comments' => trim((string)($r['evt_comments'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * Quién aprobó la producción: líder de área y supervisor (autocontrol del ERP) y,
     * si existe, la aprobación del alistamiento registrada en TRZ.
     *
     * @return array<int,array<string,mixed>>
     */
    private function approvals(int $agId, string $otCode): array
    {
        $out = [];
        $stmt = $this->erp->prepare(
            "SELECT a.ctr_type, MAX(a.ctr_ctrdat) AS at_ts, a.ctr_ctrusr,
                    TRIM(CONCAT(COALESCE(u.user_firstname, ''), ' ', COALESCE(u.user_lastname, ''))) AS name, u.user_login
             FROM prod_worker_ot_autocontrol a
             INNER JOIN prod_worker_ot pwo ON pwo.id = a.ctr_init_id
             LEFT JOIN user u ON u.id = a.ctr_ctrusr
             WHERE pwo.wok_ag_id = :ag AND a.ctr_res = 1 AND a.ctr_ctrusr > 0 AND a.ctr_type IN ('lider', 'supervisor')
             GROUP BY a.ctr_type, a.ctr_ctrusr, name, u.user_login
             ORDER BY a.ctr_type ASC"
        );
        $stmt->execute([':ag' => $agId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $name = trim((string)($r['name'] ?? ''));
            $out[] = [
                'role' => (string)$r['ctr_type'] === 'lider' ? 'Líder de área' : 'Supervisor',
                'name' => $name !== '' ? $name : (string)($r['user_login'] ?? ''),
                'at' => $this->fmtDateTime((int)($r['at_ts'] ?? 0)),
                'source' => 'Autocontrol ERP',
            ];
        }

        if ($this->trz !== null && $otCode !== '') {
            try {
                $stmt = $this->trz->prepare(
                    'SELECT e.created_at,
                            JSON_UNQUOTE(JSON_EXTRACT(e.payload, "$.role")) AS role,
                            JSON_UNQUOTE(JSON_EXTRACT(e.payload, "$.approved_display_name")) AS name
                     FROM events e
                     INNER JOIN work_orders wo ON wo.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(e.payload, "$.work_order_id")) AS UNSIGNED)
                     WHERE e.type IN ("WORK_ORDER_SETUP_APPROVED", "WORK_ORDER_SEALING_SETUP_APPROVED", "WORK_ORDER_PACKAGING_SETUP_APPROVED")
                       AND wo.ot_code = :ot
                     ORDER BY e.id ASC'
                );
                $stmt->execute([':ot' => $otCode]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $out[] = [
                        'role' => ((string)($r['role'] ?? '') === 'LEADER' ? 'Líder' : 'Supervisor') . ' (alistamiento)',
                        'name' => (string)($r['name'] ?? ''),
                        'at' => (string)($r['created_at'] ?? ''),
                        'source' => 'Alistamiento TRZ',
                    ];
                }
            } catch (Throwable $e) {
                error_log('Ficha de fabricación (aprobaciones TRZ): ' . $e->getMessage());
            }
        }
        return $out;
    }
}
