<?php

declare(strict_types=1);

require_once __DIR__ . '/WarehouseVisibility.php';

/**
 * Final del turno · Bodega: movimientos de bodega de un día (solo lectura del ERP y de la TRZ).
 *
 *  1. Recepciones de proveedores
 *     a) Bobinas recibidas por OC o contenedor: TRZ `movements` RECEIPT de bobinas con OC/contenedor; quién la recibió
 *        sale del registro de recepción (payload.operator_name) y la bodega es la de destino del movimiento.
 *     b) Otras compras ingresadas en el ERP: kardex `tran_data` tipo `invoicesbuy`.
 *  2. Despachos: kardex `tran_data` tipo `ordersdelivery` (salidas por guía), agrupado por guía; si la guía se
 *     despachó con escaneo en Bodega → Despacho (TRZ `dispatch_pickings`), se agrega quién preparó y quién confirmó.
 *  3. Producto terminado desde Corte y Sellado / Embalaje: cierres de embalaje del día (`prod_worker_embalajes`),
 *     con unidades, bodega de destino, operador y supervisor que validó.
 *  4. Ingresos registrados por bodega: «Ingreso con Trazabilidad» del día (`traceability_warehouse_entries` con lote).
 *
 * Día = 00:00:00 a 23:59:59 según la fecha de cada registro.
 */
final class ShiftEndWarehouseService
{
    private const PROCESS = [8 => 'Corte y Sellado', 14 => 'Corte y Sellado', 15 => 'Embalaje'];

    public function __construct(private PDO $erp, private ?PDO $trz)
    {
    }

    public function cachedDay(string $date, bool $fresh = false): array
    {
        $date = ShiftEndProductionService::normalizeDate($date);
        $key = 'shift-end-wh-v1-' . $date;
        if ($fresh) {
            ResultCache::forget($key);
        }
        return (array)ResultCache::remember($key, $date === date('Y-m-d') ? 60 : 600, fn() => $this->day($date));
    }

    /** @return array<int,array<string,mixed>> */
    private function rows(PDO $pdo, string $sql, array $params = []): array
    {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function name(?string $first, ?string $last): string
    {
        return trim(trim((string)$first) . ' ' . trim((string)$last));
    }

    private static function digits(string $v): string
    {
        return ltrim((string)preg_replace('/\D/', '', $v), '0');
    }

    /** @return array<int,string> nombres de bodegas ERP por id */
    private function storehouseNames(array $ids): array
    {
        $ids = array_values(array_filter(array_unique(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach ($this->rows($this->erp, 'SELECT id, st_name FROM company_shops_storehouses WHERE id IN (' . implode(',', $ids) . ')') as $r) {
            $out[(int)$r['id']] = trim((string)$r['st_name']);
        }
        return $out;
    }

    public function day(string $date): array
    {
        $date = ShiftEndProductionService::normalizeDate($date);
        $from = $date . ' 00:00:00';
        $to = $date . ' 23:59:59';
        $start = (new DateTimeImmutable($from))->getTimestamp();
        $end = (new DateTimeImmutable($to))->getTimestamp();
        $warnings = [];

        // Kardex del día (compras y guías), solo bodegas de la sucursal Unibag.
        $kardex = $this->rows($this->erp, "SELECT t.tran_type, t.tran_id, t.tran_number, t.tran_amount, t.tran_st_id, t.tran_crtdat, t.tran_order_id,
                t.item_id, COALESCE(NULLIF(i.item_title, ''), t.item_title) AS item_title, i.item_number_prod,
                st.st_name, st.st_shop_id, u.user_firstname, u.user_lastname, o.req_number, c.cust_name
            FROM tran_data t
            LEFT JOIN item i ON i.id = t.item_id
            LEFT JOIN company_shops_storehouses st ON st.id = t.tran_st_id
            LEFT JOIN `user` u ON u.id = t.tran_crtusr
            LEFT JOIN orders o ON o.id = t.tran_order_id
            LEFT JOIN customer c ON c.id = o.req_cust_id
            WHERE t.tran_crtdat BETWEEN ? AND ? AND t.tran_type IN ('invoicesbuy', 'ordersdelivery')
            ORDER BY t.tran_crtdat, t.tran_id, t.tran_pos", [$start, $end]);
        $shop = WarehouseVisibility::shopId();
        $kardex = array_values(array_filter($kardex, static fn($r) => (int)$r['st_shop_id'] === 0 || (int)$r['st_shop_id'] === $shop));

        // 1a. Bobinas recibidas por OC o contenedor (TRZ).
        $rolls = [];
        if ($this->trz !== null) {
            try {
                $rolls = $this->rows($this->trz, "SELECT m.created_at, m.payload, r.roll_code, r.weight_kg, r.received_qty, r.purchase_order_id,
                        r.import_container_id, r.supplier_id, w.code AS wh_code, w.name AS wh_name, s.code AS sku, s.description AS sku_desc
                    FROM movements m
                    JOIN rolls r ON r.id = m.entity_id
                    LEFT JOIN warehouses w ON w.id = m.to_warehouse_id
                    LEFT JOIN skus s ON s.id = r.sku_id
                    WHERE m.entity_type = 'ROLL' AND m.movement_type = 'RECEIPT' AND m.created_at BETWEEN ? AND ?
                      AND (COALESCE(r.purchase_order_id, 0) > 0 OR COALESCE(r.import_container_id, 0) > 0)
                    ORDER BY m.created_at, m.id", [$from, $to]);
            } catch (Throwable $e) {
                $warnings[] = 'No se pudieron leer las bobinas recibidas (trazabilidad).';
            }
        }
        $poIds = array_filter(array_unique(array_map('intval', array_column($rolls, 'purchase_order_id'))));
        $contIds = array_filter(array_unique(array_map('intval', array_column($rolls, 'import_container_id'))));
        $suppIds = array_filter(array_unique(array_map('intval', array_column($rolls, 'supplier_id'))));
        $po = $cont = $supp = [];
        if ($poIds !== []) {
            foreach ($this->rows($this->erp, 'SELECT po.id, po.sord_number, po.sord_supplier_id, s.supp_company FROM supplier_order po
                    LEFT JOIN supplier s ON s.id = po.sord_supplier_id WHERE po.id IN (' . implode(',', $poIds) . ')') as $r) {
                $po[(int)$r['id']] = $r;
            }
        }
        if ($contIds !== []) {
            foreach ($this->rows($this->erp, 'SELECT id, sord_contenedor FROM supplier_contenedor WHERE id IN (' . implode(',', $contIds) . ')') as $r) {
                $cont[(int)$r['id']] = trim((string)$r['sord_contenedor']);
            }
        }
        if ($suppIds !== []) {
            foreach ($this->rows($this->erp, 'SELECT id, supp_company FROM supplier WHERE id IN (' . implode(',', $suppIds) . ')') as $r) {
                $supp[(int)$r['id']] = trim((string)$r['supp_company']);
            }
        }
        $receptions = [];
        foreach ($rolls as $r) {
            $payload = json_decode((string)$r['payload'], true) ?: [];
            $poRow = $po[(int)$r['purchase_order_id']] ?? null;
            $doc = $poRow !== null ? 'OC ' . trim((string)$poRow['sord_number'])
                : ((int)$r['import_container_id'] > 0 ? 'Contenedor ' . ($cont[(int)$r['import_container_id']] ?? ('#' . (int)$r['import_container_id'])) : '');
            $supplier = trim((string)($poRow['supp_company'] ?? '')) ?: ($supp[(int)$r['supplier_id']] ?? '');
            $who = trim((string)($payload['operator_name'] ?? '')) ?: 'Sin registro';
            $wh = trim((string)$r['wh_name']) ?: ('Bodega ' . (string)$r['wh_code']);
            $key = $doc . '|' . $r['sku'] . '|' . $wh . '|' . $who;
            $receptions[$key] ??= ['document' => $doc, 'supplier' => $supplier, 'sku' => (string)$r['sku'], 'description' => (string)$r['sku_desc'],
                'warehouse' => $wh, 'received_by' => $who, 'rolls' => 0, 'kg' => 0.0, 'first' => substr((string)$r['created_at'], 11, 5),
                'last' => '', 'codes' => []];
            $receptions[$key]['rolls']++;
            $receptions[$key]['kg'] += (float)$r['weight_kg'];
            $receptions[$key]['last'] = substr((string)$r['created_at'], 11, 5);
            $receptions[$key]['codes'][] = (string)$r['roll_code'];
        }
        $receptions = array_values($receptions);

        // 1b. Otras compras en el ERP.
        $purchases = [];
        foreach ($kardex as $r) {
            if ($r['tran_type'] !== 'invoicesbuy') {
                continue;
            }
            $purchases[] = ['time' => date('H:i', (int)$r['tran_crtdat']), 'document' => trim((string)$r['tran_number']),
                'sku' => (string)$r['item_number_prod'], 'description' => (string)$r['item_title'], 'qty' => (float)$r['tran_amount'],
                'warehouse' => trim((string)$r['st_name']) ?: '—', 'user' => self::name($r['user_firstname'], $r['user_lastname']) ?: 'Sin usuario'];
        }

        // 2. Despachos por guía (kardex) + confirmación en bodega (TRZ).
        $pickings = [];
        if ($this->trz !== null) {
            try {
                $pickings = $this->rows($this->trz, "SELECT p.id, p.code, p.dispatch_type, p.document_type, p.guide_number, p.guide_snapshot, p.created_by,
                        p.confirmed_by, p.confirmed_at,
                        (SELECT COUNT(*) FROM dispatch_picking_items i WHERE i.picking_id = p.id AND i.status = 'DISPATCHED') AS scanned
                    FROM dispatch_pickings p WHERE p.status = 'DISPATCHED' AND p.confirmed_at BETWEEN ? AND ? ORDER BY p.confirmed_at", [$from, $to]);
            } catch (Throwable $e) {
                $warnings[] = 'No se pudieron leer los despachos escaneados en bodega (trazabilidad).';
            }
        }
        $pickByNumber = [];
        foreach ($pickings as $i => $p) {
            $snap = json_decode((string)$p['guide_snapshot'], true) ?: [];
            $pickings[$i]['snap'] = $snap;
            foreach ([(string)($snap['number'] ?? ''), (string)($snap['docnum'] ?? ''), (string)$p['guide_number']] as $n) {
                if (self::digits($n) !== '') {
                    $pickByNumber[self::digits($n)] = $i;
                }
            }
        }
        $dispatches = [];
        $usedPickings = [];
        foreach ($kardex as $r) {
            if ($r['tran_type'] !== 'ordersdelivery') {
                continue;
            }
            $num = trim((string)$r['tran_number']);
            $key = $num !== '' ? $num : ('#' . (int)$r['tran_id']);
            if (!isset($dispatches[$key])) {
                $pi = $pickByNumber[self::digits($num)] ?? null;
                if ($pi !== null) {
                    $usedPickings[$pi] = true;
                }
                $dispatches[$key] = ['time' => date('H:i', (int)$r['tran_crtdat']), 'guide' => $key,
                    'customer' => trim((string)$r['cust_name']) ?: (string)($pi !== null ? ($pickings[$pi]['snap']['customer'] ?? '') : ''),
                    'cc' => [], 'items' => [], 'units' => 0.0, 'warehouses' => [],
                    'issued_by' => self::name($r['user_firstname'], $r['user_lastname']) ?: 'Sin usuario',
                    'picked_by' => $pi !== null ? (string)$pickings[$pi]['created_by'] : '',
                    'confirmed_by' => $pi !== null ? (string)$pickings[$pi]['confirmed_by'] : '',
                    'picking' => $pi !== null ? (string)$pickings[$pi]['code'] : ''];
            }
            $d = &$dispatches[$key];
            if (trim((string)$r['req_number']) !== '') {
                $d['cc'][trim((string)$r['req_number'])] = true;
            }
            $d['warehouses'][trim((string)$r['st_name']) ?: '—'] = true;
            $d['items'][] = ['sku' => (string)$r['item_number_prod'], 'description' => (string)$r['item_title'], 'qty' => (float)$r['tran_amount'],
                'warehouse' => trim((string)$r['st_name']) ?: '—', 'cc' => trim((string)$r['req_number'])];
            $d['units'] += (float)$r['tran_amount'];
            unset($d);
        }
        foreach ($dispatches as &$d) {
            $d['cc'] = array_keys($d['cc']);
            $d['warehouses'] = array_keys($d['warehouses']);
        }
        unset($d);
        $dispatches = array_values($dispatches);
        // Despachos confirmados en bodega cuya guía no aparece en el kardex del día (p. ej. guía emitida otro día o factura).
        $unmatched = [];
        foreach ($pickings as $i => $p) {
            if (isset($usedPickings[$i])) {
                continue;
            }
            $snap = $p['snap'];
            $unmatched[] = ['time' => substr((string)$p['confirmed_at'], 11, 5), 'picking' => (string)$p['code'],
                'document' => ($p['document_type'] === 'INVOICE' ? 'Factura ' : 'Guía ') . (string)$p['guide_number'],
                'customer' => (string)($snap['customer'] ?? ''), 'type' => $p['dispatch_type'] === 'MAQUILA' ? 'Maquila' : 'Venta',
                'units' => array_sum(array_map(static fn($it) => (float)($it['qty'] ?? 0), (array)($snap['items'] ?? []))),
                'scanned' => (int)$p['scanned'], 'picked_by' => (string)$p['created_by'], 'confirmed_by' => (string)$p['confirmed_by']];
        }

        // 3. Producto terminado desde Corte y Sellado / Embalaje.
        $packing = $this->rows($this->erp, "SELECT pe.id, pe.evt_crtdat, pe.evt_reqid, pe.req_id_bodega, pe.req_id_supervisor, pe.req_codigo_cierre,
                pe.req_embalaje_cajas_completas_amt AS boxes, pe.req_embalaje_bolsas_por_caja_amt AS per_box, pe.req_embalaje_caja_final AS last_box,
                pe.req_embalaje_bolsas_sobrantes_amt AS loose, pe.req_embalaje_showroom AS showroom,
                e.equipo_name, e.equipo_type_id, w.wrk_firstname, w.wrk_lastname, us.user_firstname, us.user_lastname,
                h.prd_number, o.req_number, c.cust_name
            FROM prod_worker_embalajes pe
            LEFT JOIN prod_worker_ot ot ON ot.id = pe.evt_prod_worker_otid
            LEFT JOIN prod_worker_init wi ON wi.id = ot.wok_init_id
            LEFT JOIN prod_agenda a ON a.id = ot.wok_ag_id
            LEFT JOIN equipo e ON e.id = COALESCE(NULLIF(wi.win_equipoid, 0), a.ag_equipo_id)
            LEFT JOIN workers w ON w.id = wi.win_wrkid
            LEFT JOIN `user` us ON us.id = pe.req_id_supervisor
            LEFT JOIN prod_header h ON h.id = a.ag_prdid
            LEFT JOIN orders o ON o.id = pe.evt_reqid
            LEFT JOIN customer c ON c.id = o.req_cust_id
            WHERE pe.evt_crtdat BETWEEN ? AND ?
            ORDER BY pe.evt_crtdat, pe.id", [$start, $end]);
        $whNames = $this->storehouseNames(array_column($packing, 'req_id_bodega'));
        $items = [];
        $reqIds = array_values(array_filter(array_unique(array_map('intval', array_column($packing, 'evt_reqid')))));
        if ($reqIds !== []) {
            foreach ($this->rows($this->erp, 'SELECT oi.req_id, i.item_number_prod, i.item_title FROM orders_items oi LEFT JOIN item i ON i.id = oi.item_id
                    WHERE oi.req_id IN (' . implode(',', $reqIds) . ') ORDER BY oi.req_id, oi.item_pos') as $r) {
                $items[(int)$r['req_id']] ??= $r;
            }
        }
        $finished = [];
        foreach ($packing as $r) {
            $units = (int)$r['boxes'] * (int)$r['per_box'] + (int)$r['last_box'] + (int)$r['loose'] + (int)$r['showroom'];
            $it = $items[(int)$r['evt_reqid']] ?? ['item_number_prod' => '', 'item_title' => ''];
            $type = (int)$r['equipo_type_id'];
            $finished[] = ['time' => date('H:i', (int)$r['evt_crtdat']),
                'process' => self::PROCESS[$type] ?? 'Otro', 'machine' => trim((string)$r['equipo_name']) ?: '—',
                'ot' => trim((string)$r['prd_number']), 'cc' => trim((string)$r['req_number']), 'customer' => trim((string)$r['cust_name']),
                'sku' => (string)$it['item_number_prod'], 'description' => (string)$it['item_title'],
                'boxes' => (int)$r['boxes'] + ((int)$r['last_box'] > 0 ? 1 : 0), 'units' => $units,
                'warehouse' => (int)$r['req_id_bodega'] > 0 ? ($whNames[(int)$r['req_id_bodega']] ?? ('Bodega #' . (int)$r['req_id_bodega'])) : 'Sin bodega',
                'operator' => self::name($r['wrk_firstname'], $r['wrk_lastname']) ?: '—',
                'supervisor' => self::name($r['user_firstname'], $r['user_lastname']) ?: '—',
                'mixed' => trim((string)$r['req_codigo_cierre']) === '50'];
        }

        // 4. Ingresos registrados por bodega (Ingreso con Trazabilidad).
        $entries = [];
        if ($this->trz !== null) {
            try {
                foreach ($this->rows($this->trz, "SELECT batch_id, MIN(created_at) AS created_at, entity_type, warehouse_name, item_code, item_description,
                        SUM(quantity) AS qty, MAX(unit_name) AS unit_name, COUNT(*) AS labels, MAX(order_ref) AS order_ref, operator_name
                    FROM traceability_warehouse_entries
                    WHERE created_at BETWEEN ? AND ? AND batch_id IS NOT NULL AND batch_id <> ''
                    GROUP BY batch_id, entity_type, warehouse_name, item_code, item_description, operator_name
                    ORDER BY MIN(created_at)", [$from, $to]) as $r) {
                    $entries[] = ['time' => substr((string)$r['created_at'], 11, 5), 'type' => ['PALLET' => 'Pallet', 'BOX' => 'Caja', 'ROLL' => 'Bobina'][$r['entity_type']] ?? 'Producto',
                        'sku' => (string)$r['item_code'], 'description' => (string)$r['item_description'], 'qty' => (float)$r['qty'],
                        'unit' => (string)$r['unit_name'], 'labels' => (int)$r['labels'], 'warehouse' => (string)$r['warehouse_name'],
                        'reference' => (string)$r['order_ref'], 'received_by' => trim((string)$r['operator_name']) ?: 'Sin registro'];
                }
            } catch (Throwable $e) {
                $warnings[] = 'No se pudieron leer los ingresos registrados por bodega (trazabilidad).';
            }
        }
        if ($this->trz === null) {
            $warnings[] = 'Sin conexión a trazabilidad: faltan bobinas recibidas, confirmaciones de despacho e ingresos de bodega.';
        }

        // Resumen por bodega: entradas y salidas del día.
        $byWh = [];
        $add = static function (string $wh, string $field, float $v) use (&$byWh): void {
            $byWh[$wh] ??= ['warehouse' => $wh, 'rolls_in' => 0, 'kg_in' => 0.0, 'purchases_in' => 0.0, 'production_in' => 0.0, 'entries_in' => 0.0, 'dispatched' => 0.0];
            $byWh[$wh][$field] += $v;
        };
        foreach ($receptions as $r) {
            $add($r['warehouse'], 'rolls_in', $r['rolls']);
            $add($r['warehouse'], 'kg_in', $r['kg']);
        }
        foreach ($purchases as $r) {
            $add($r['warehouse'], 'purchases_in', $r['qty']);
        }
        foreach ($finished as $r) {
            $add($r['warehouse'], 'production_in', $r['units']);
        }
        foreach ($entries as $r) {
            $add($r['warehouse'], 'entries_in', $r['qty']);
        }
        foreach ($dispatches as $d) {
            foreach ($d['items'] as $it) {
                $add($it['warehouse'], 'dispatched', $it['qty']);
            }
        }
        ksort($byWh, SORT_NATURAL);

        return [
            'date' => $date, 'generated_at' => date('Y-m-d H:i:s'), 'warnings' => $warnings,
            'receptions' => $receptions, 'purchases' => $purchases, 'dispatches' => $dispatches, 'unmatched_dispatches' => $unmatched,
            'finished' => $finished, 'entries' => $entries, 'by_warehouse' => array_values($byWh),
            'totals' => [
                'rolls' => array_sum(array_column($receptions, 'rolls')), 'rolls_kg' => array_sum(array_column($receptions, 'kg')),
                'purchase_lines' => count($purchases), 'guides' => count($dispatches) + count($unmatched),
                'dispatched_units' => array_sum(array_column($dispatches, 'units')) + array_sum(array_column($unmatched, 'units')),
                'finished_closures' => count($finished), 'finished_units' => array_sum(array_column($finished, 'units')),
                'entries' => count($entries),
            ],
        ];
    }
}
