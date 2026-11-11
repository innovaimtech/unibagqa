<?php

declare(strict_types=1);

require_once __DIR__ . '/WarehouseVisibility.php';
require_once __DIR__ . '/MonthlyPresentationService.php';

/**
 * Informe de stock consolidado de bodega (solo lectura del ERP).
 *
 * Reproduce el Excel «INFORME STOCK CONSOLIDADOS»:
 *  - stock por artículo, bodega y CC en las bodegas 100 y 200 (bobinas) y 400, 700, 1000 y 5000 (producto terminado);
 *  - participación de cada bodega de producto terminado en el total («% partic. bodega»);
 *  - ocupación de las bodegas de bobinas (bobinas en stock / capacidad);
 *  - stock consolidado por cliente de la bodega 1000 (retail);
 *  - rotación: lo que está en la bodega 5000 (productos inmovilizados) es «sin rotación», el resto «con movimiento»;
 *  - bolsas de algodón.
 * Las bodegas se reconocen por el código al inicio del nombre ERP («400 (PROD TERMINADOS REVENTA)» → 400).
 */
final class WarehouseStockReportService
{
    public const ROLL_CODES = [100, 200];
    public const GOODS_CODES = [400, 700, 1000, 5000];
    public const RETAIL_CODE = 1000;
    public const STILL_CODE = 5000;

    /** Capacidad de almacenamiento de bobinas cuando el ERP no la tiene (ubicaciones × columnas × altura del Excel). */
    public const DEFAULT_ROLL_CAPACITY = [100 => 1824, 200 => 384];

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

    /** Informe con caché corta (2 minutos); `$fresh` lo recalcula. */
    public function cachedReport(bool $fresh = false): array
    {
        if ($this->erp->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return $this->report();
        }
        $key = 'wh-stock-report-v1';
        if ($fresh) {
            ResultCache::forget($key);
        }
        return (array)ResultCache::remember($key, 120, fn() => $this->report());
    }

    /**
     * @return array<string,mixed>
     */
    public function report(?int $now = null): array
    {
        $now ??= time();
        // Bodegas: id ERP por código.
        $stores = [];
        foreach ($this->rows('SELECT id, st_name, st_desc, st_capacidad FROM company_shops_storehouses WHERE st_status = 1 AND '
            . WarehouseVisibility::shopSql($this->erp) . ' ORDER BY id') as $s) {
            $code = WarehouseVisibility::codeFromName((string)$s['st_name']);
            if ($code === null || !in_array($code, array_merge(self::ROLL_CODES, self::GOODS_CODES), true) || isset($stores[$code])) {
                continue;
            }
            $stores[$code] = ['id' => (int)$s['id'], 'name' => trim((string)$s['st_name']), 'capacity' => (int)($s['st_capacidad'] ?? 0)];
        }
        $codeOf = [];
        foreach ($stores as $code => $s) {
            $codeOf[$s['id']] = $code;
        }

        // Stock por artículo, bodega y CC.
        $raw = $codeOf === [] ? [] : $this->rows('SELECT iss.item_id, iss.st_id, iss.iss_order_id, SUM(iss.iss_inventory) AS qty,
                it.item_number_prod, it.item_title, it.item_reg_kg, COALESCE(u.unit_name, \'UNID\') AS unit_name
            FROM item_shops_storehouses iss
            JOIN item it ON it.id = iss.item_id
            LEFT JOIN item_units u ON u.id = it.item_unit
            WHERE iss.st_id IN (' . self::in(array_keys($codeOf)) . ')
            GROUP BY iss.item_id, iss.st_id, iss.iss_order_id, it.item_number_prod, it.item_title, it.item_reg_kg, u.unit_name
            HAVING SUM(iss.iss_inventory) > 0');

        // CC y cliente: el de la CC si el stock está asignado a una; si no, el cliente que más ha pedido el artículo.
        $orders = [];
        foreach ($this->rows('SELECT id, req_number, req_cust_id FROM orders WHERE id IN (' . self::in(array_column($raw, 'iss_order_id')) . ')') as $o) {
            $orders[(int)$o['id']] = $o;
        }
        $freeItems = [];
        foreach ($raw as $r) {
            if ((int)$r['iss_order_id'] <= 0 || !isset($orders[(int)$r['iss_order_id']])) {
                $freeItems[(int)$r['item_id']] = true;
            }
        }
        $mainCustomer = [];
        $best = [];
        foreach (array_chunk(array_keys($freeItems), 500) as $chunk) {
            foreach ($this->rows('SELECT oi.item_id, o.req_cust_id, COUNT(*) AS n FROM orders_items oi JOIN orders o ON o.id = oi.req_id
                    WHERE oi.item_id IN (' . self::in($chunk) . ') AND o.req_cust_id > 0 GROUP BY oi.item_id, o.req_cust_id') as $c) {
                $item = (int)$c['item_id'];
                if (!isset($best[$item]) || (int)$c['n'] > $best[$item] || ((int)$c['n'] === $best[$item] && (int)$c['req_cust_id'] < $mainCustomer[$item])) {
                    $best[$item] = (int)$c['n'];
                    $mainCustomer[$item] = (int)$c['req_cust_id'];
                }
            }
        }
        $custIds = array_merge(array_values($mainCustomer), array_map(static fn($o) => (int)$o['req_cust_id'], $orders));
        $customers = [];
        foreach ($this->rows('SELECT id, cust_name FROM customer WHERE id IN (' . self::in($custIds) . ')') as $c) {
            $customers[(int)$c['id']] = MonthlyPresentationService::shortCustomerName((string)$c['cust_name']);
        }

        $detail = [];
        $byStore = [];
        foreach ($stores as $code => $s) {
            $byStore[$code] = ['code' => $code, 'name' => $s['name'], 'kind' => in_array($code, self::ROLL_CODES, true) ? 'rolls' : 'goods',
                'units' => 0.0, 'kg' => 0.0, 'items' => [], 'capacity' => null, 'occupancy' => null, 'share' => null];
        }
        $retailByCustomer = [];
        $stillByCustomer = [];
        $cotton = [];
        foreach ($raw as $r) {
            $code = $codeOf[(int)$r['st_id']] ?? null;
            if ($code === null) {
                continue;
            }
            $qty = (float)$r['qty'];
            $oid = (int)$r['iss_order_id'];
            $order = $orders[$oid] ?? null;
            $custId = $order !== null ? (int)$order['req_cust_id'] : ($mainCustomer[(int)$r['item_id']] ?? 0);
            $customer = $customers[$custId] ?? 'Sin cliente';
            $kg = $qty * (float)$r['item_reg_kg'];
            $title = trim((string)$r['item_title']);
            $detail[] = [
                'sku' => trim((string)$r['item_number_prod']),
                'title' => $title,
                'unit' => trim((string)$r['unit_name']),
                'warehouse' => $code,
                'warehouse_name' => $stores[$code]['name'],
                'qty' => round($qty, 2),
                'kg' => $byStore[$code]['kind'] === 'rolls' ? round($kg, 1) : null,
                'cc' => $order !== null ? trim((string)$order['req_number']) : '',
                'customer' => $byStore[$code]['kind'] === 'goods' ? $customer : '',
            ];
            $byStore[$code]['units'] += $qty;
            $byStore[$code]['kg'] += $kg;
            $byStore[$code]['items'][(int)$r['item_id']] = true;
            if ($code === self::RETAIL_CODE) {
                $retailByCustomer[$customer] = ($retailByCustomer[$customer] ?? 0.0) + $qty;
            }
            if ($code === self::STILL_CODE) {
                $stillByCustomer[$customer] = ($stillByCustomer[$customer] ?? 0.0) + $qty;
            }
            if ($byStore[$code]['kind'] === 'goods' && preg_match('/ALGOD[OÓ]N|\/ALG\//iu', $title) === 1) {
                $key = $r['item_number_prod'] . '|' . $code;
                $cotton[$key] ??= ['sku' => trim((string)$r['item_number_prod']), 'title' => $title, 'unit' => trim((string)$r['unit_name']),
                    'warehouse' => $code, 'qty' => 0.0];
                $cotton[$key]['qty'] += $qty;
            }
        }

        $goodsTotal = 0.0;
        foreach ($byStore as $code => $s) {
            if ($s['kind'] === 'goods') {
                $goodsTotal += $s['units'];
            }
        }
        foreach ($byStore as $code => &$s) {
            $s['item_count'] = count($s['items']);
            unset($s['items']);
            $s['units'] = round($s['units']);
            $s['kg'] = $s['kind'] === 'rolls' ? round($s['kg']) : null;
            if ($s['kind'] === 'goods') {
                $s['share'] = $goodsTotal > 0 ? round($s['units'] / $goodsTotal * 100, 1) : 0.0;
            } else {
                $cap = (int)$stores[$code]['capacity'] > 0 ? (int)$stores[$code]['capacity'] : (self::DEFAULT_ROLL_CAPACITY[$code] ?? 0);
                $s['capacity'] = $cap > 0 ? $cap : null;
                $s['occupancy'] = $cap > 0 ? round($s['units'] / $cap * 100, 1) : null;
            }
        }
        unset($s);
        $order = array_flip(array_merge(self::ROLL_CODES, self::GOODS_CODES));
        uksort($byStore, static fn($a, $b) => $order[$a] <=> $order[$b]);

        $still = (float)($byStore[self::STILL_CODE]['units'] ?? 0);
        arsort($retailByCustomer);
        arsort($stillByCustomer);
        usort($detail, static fn($a, $b) => [$a['warehouse'], $a['sku'], $a['cc']] <=> [$b['warehouse'], $b['sku'], $b['cc']]);
        $cotton = array_values($cotton);
        usort($cotton, static fn($a, $b) => [$a['sku'], $a['warehouse']] <=> [$b['sku'], $b['warehouse']]);
        $round = static fn(array $m): array => array_map(static fn($v) => round($v), $m);

        return [
            'generated_at' => date('Y-m-d H:i', $now),
            'warehouses' => array_values($byStore),
            'missing' => array_values(array_diff(array_merge(self::ROLL_CODES, self::GOODS_CODES), array_keys($stores))),
            'goods_total' => round($goodsTotal),
            'rotation' => ['moved' => round($goodsTotal - $still), 'still' => round($still),
                'still_pct' => $goodsTotal > 0 ? round($still / $goodsTotal * 100, 1) : 0.0],
            'retail_by_customer' => $round($retailByCustomer),
            'still_by_customer' => $round($stillByCustomer),
            'cotton' => array_map(static fn($c) => ['qty' => round($c['qty'])] + $c, $cotton),
            'cotton_total' => round(array_sum(array_column($cotton, 'qty'))),
            'rows' => $detail,
        ];
    }
}
