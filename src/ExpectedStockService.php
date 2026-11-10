<?php

declare(strict_types=1);

/**
 * Stock esperado de producto terminado.
 *
 * Maestro editable (TRZ) con los códigos a controlar: bodega (1000 ó 400), unidades por caja, cajas por pallet,
 * stock objetivo y stock mínimo. El informe cruza el maestro con el stock del ERP (solo lectura) y calcula
 * pallets, cajas, saldo y estado contra el objetivo.
 *
 * Además (solo lectura): reservado = vendido sin despachar en NV de venta (req_isfabricate = 0) de los últimos
 * SALES_MAX_AGE_DAYS días; en producción = lo que falta embalar de las CC de fabricación vivas (con movimiento en
 * PRODUCTION_IDLE_DAYS días o nuevas); proyectado = stock + en producción − reservado. Ventanas de detalle: reservedDetail() y productionDetail().
 */
final class ExpectedStockService
{
    public const WAREHOUSES = [1000, 400];
    private const SCHEMA_VERSION = 'expected-stock-2026-11-04-a';

    /** Lista inicial: [descripción, código]. */
    private const SEED = [
        ['CONSTRUMART BOU', 'BOL0247'], ['CONSTRUMART PROMO', 'BOL0962'], ['CRUZ VERDE', 'BOL0250'], ['EASY', 'BOL0251'],
        ['EASY XXL', 'BOL0997'], ['ESMAX', 'BOL0947'], ['GANGA XXL', 'BOL0635'], ['LA FAMA BOU', 'BOL0301'],
        ['LA FAMA PROMO', 'BOL0300'], ['IMPERIAL (KUTZ)', 'BOL0682'], ['OXXO (TIENDA)', 'BOL0983'],
        ['RENDIC GENERICO UNIBAG BOU', 'BOL0403'], ['RENDIC GENERICO UNIBAG PRO', 'BOL0402'], ['RENDIC GENERICO UNIBAG XXL', 'BOL0864'],
        ['RENDIC UNIMARC BOU', 'BOL0356'], ['RENDIC UNIMARC PRO', 'BOL0357'], ['RENDIC UNIMARC XXL', 'BOL0904'],
        ['SALCOBRAND BOU', 'BOL0243'], ['SALCOBRAND PRO', 'BOL0245'], ['SODIMAC 37X40X12', 'BOL0436'], ['SODIMAC 44X39X23', 'BOL0382'],
        ['SODIMAC 48X38X16', 'BOL0428'], ['TOTTUS 38X38', 'BOL0352'], ['TOTTUS XXL', 'BOL0499'], ['ALVI XXL', 'BOL1079'],
        ['ACUENTA PRO', 'BOL1068'], ['ACUENTA BOU', 'BOL1029'], ['WALMART BOU', 'BOL0363'], ['WALMART PRO', 'BOL0362'],
        ['WALMART PRO 45X40X12', 'BOL0953'], ['SALCOBRAND ALGODÓN 38X42', 'BOL0388'], ['MAICAO BOUTIQUE (FUCSIA)', 'BOL0967'],
        ['MAICAO BOUTIQUE (BLANCA)', 'BOL0968'], ['MASS PROMO 40X40X12', 'BOL0882'],
        ['PLA BOU 30X40X11 BLANCO', 'ONL0173'], ['PLA PROMO 45X40X12 BEIGE', 'ONL0136'], ['PP BOU 30X40X11 BLANCO', 'BOL0998'],
        ['PP BOU 30X40X11 NEGRO', 'ONL0172'], ['PP PROMO 45X40X12 BEIGE', 'ONL0103'], ['PP PROMO 45X40X12 BLANCO', 'ONL0020'],
        ['BOLSO COOLER MEDIANO', 'BOL0636'], ['PLA PROMO 45X40X12 BLANCO', 'ONL0175'],
    ];

    private const FIELD_LABELS = [
        'description' => 'Descripción', 'warehouse_code' => 'Bodega', 'units_per_box' => 'Unidades por caja',
        'boxes_per_pallet' => 'Cajas por pallet', 'target_units' => 'Stock objetivo', 'min_units' => 'Stock mínimo',
        'group_label' => 'Grupo', 'active' => 'Activo', 'alta' => 'Alta',
    ];

    private bool $ready = false;

    public function __construct(private ?PDO $erp, private PDO $trz)
    {
    }

    private function sqlite(): bool
    {
        return $this->trz->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    private function all(PDO $pdo, string $sql, array $params = []): array
    {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($code)) ?? '');
    }

    public function ensureSchema(): void
    {
        if ($this->ready) {
            return;
        }
        $gate = !$this->sqlite() && class_exists('SchemaGate');
        if ($gate && SchemaGate::done('expected-stock', self::SCHEMA_VERSION)) {
            $this->ready = true;
            return;
        }
        $pk = $this->sqlite() ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $engine = $this->sqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $this->trz->exec("CREATE TABLE IF NOT EXISTS expected_stock_master (
            id $pk,
            item_code VARCHAR(40) NOT NULL UNIQUE,
            description VARCHAR(200) NOT NULL,
            group_label VARCHAR(80) NULL,
            warehouse_code INT NULL,
            units_per_box INT NULL,
            boxes_per_pallet INT NULL,
            target_units INT NULL,
            min_units INT NULL,
            active TINYINT NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_by VARCHAR(120) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_by VARCHAR(120) NULL,
            updated_at TIMESTAMP NULL
        )$engine");
        $this->trz->exec("CREATE TABLE IF NOT EXISTS expected_stock_history (
            id $pk,
            item_code VARCHAR(40) NOT NULL,
            field VARCHAR(30) NOT NULL,
            old_value VARCHAR(255) NULL,
            new_value VARCHAR(255) NULL,
            actor VARCHAR(120) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )$engine");
        $this->seed();
        if ($gate) {
            SchemaGate::mark('expected-stock', self::SCHEMA_VERSION);
        }
        $this->ready = true;
    }

    /** Carga los códigos iniciales que aún no existan (no pisa nada del usuario). */
    private function seed(): void
    {
        $have = [];
        foreach ($this->all($this->trz, 'SELECT item_code FROM expected_stock_master') as $r) {
            $have[self::normalizeCode((string)$r['item_code'])] = true;
        }
        $order = (int)($this->all($this->trz, 'SELECT COALESCE(MAX(sort_order),0) AS m FROM expected_stock_master')[0]['m'] ?? 0);
        $ins = $this->trz->prepare('INSERT INTO expected_stock_master (item_code, description, sort_order, created_by) VALUES (?,?,?,?)');
        foreach (self::SEED as [$desc, $code]) {
            if (!isset($have[$code])) {
                $ins->execute([$code, $desc, ++$order, 'sistema']);
            }
        }
    }

    // ------------------------------------------------------------------ cálculo

    /**
     * Cálculo de una fila (función pura).
     *
     * @param array<string,mixed> $row  warehouse_code, units_per_box, boxes_per_pallet, target_units, min_units
     * @return array<string,mixed>
     */
    public static function compute(array $row, ?float $stock, bool $existsInErp): array
    {
        $upb = (int)($row['units_per_box'] ?? 0);
        $bpp = (int)($row['boxes_per_pallet'] ?? 0);
        $target = isset($row['target_units']) && $row['target_units'] !== '' && $row['target_units'] !== null ? (int)$row['target_units'] : null;
        $min = isset($row['min_units']) && $row['min_units'] !== '' && $row['min_units'] !== null ? (int)$row['min_units'] : null;
        $hasWarehouse = in_array((int)($row['warehouse_code'] ?? 0), self::WAREHOUSES, true);
        $perPallet = ($upb > 0 && $bpp > 0) ? $upb * $bpp : 0;
        $configured = $hasWarehouse && $perPallet > 0;

        $out = [
            'per_pallet' => $perPallet, 'pallets' => null, 'boxes' => null, 'loose' => null, 'saldo' => '',
            'diff' => null, 'diff_pallets' => null, 'configured' => $configured, 'status' => '',
        ];
        $qty = $stock === null ? 0 : (int)floor($stock + 1e-9);
        if ($configured) {
            $pallets = intdiv($qty, $perPallet);
            $rest = $qty - $pallets * $perPallet;
            $boxes = intdiv($rest, $upb);
            $out['pallets'] = $pallets;
            $out['boxes'] = $boxes;
            $out['loose'] = $rest - $boxes * $upb;
            $out['saldo'] = $pallets . ' pallets + ' . $boxes . ' cajas + ' . $out['loose'] . ' un';
        }
        if ($target !== null) {
            $out['diff'] = $qty - $target;
            if ($perPallet > 0) {
                $out['diff_pallets'] = round(($qty - $target) / $perPallet, 1);
            }
        }
        if (!$existsInErp) {
            $out['status'] = 'No existe en ERP';
        } elseif (!$configured) {
            $out['status'] = 'Falta configurar';
        } elseif ($min !== null && $qty < $min) {
            $out['status'] = 'Crítico';
        } elseif ($target !== null) {
            $out['status'] = $qty < $target ? 'Bajo' : 'OK';
        } elseif ($min !== null) {
            $out['status'] = 'OK';
        } else {
            $out['status'] = 'Sin objetivo';
        }
        return $out;
    }

    // ------------------------------------------------------------------ lectura

    public function master(bool $onlyActive = true): array
    {
        $this->ensureSchema();
        return $this->all($this->trz, 'SELECT * FROM expected_stock_master' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY sort_order, id');
    }

    /** @return array<int,array<int,int>> código de bodega → ids de bodega ERP */
    private function storehouseIds(): array
    {
        $map = [1000 => [], 400 => []];
        if ($this->erp === null) {
            return $map;
        }
        foreach ($this->all($this->erp, 'SELECT id, st_name FROM company_shops_storehouses WHERE st_status = 1') as $w) {
            $code = WarehouseVisibility::codeFromName((string)$w['st_name']);
            if ($code !== null && isset($map[$code])) {
                $map[$code][] = (int)$w['id'];
            }
        }
        return $map;
    }

    /**
     * Informe: maestro + stock ERP + cálculo. Una sola consulta al ERP para todos los códigos.
     *
     * @return array{rows:array<int,array<string,mixed>>,erp_ok:bool,error:string,generated_at:string}
     */
    public function report(): array
    {
        $master = $this->master(true);
        $erpOk = $this->erp !== null;
        $error = '';
        $stock = [];
        $items = [];
        if ($master !== [] && $this->erp !== null) {
            try {
                $wh = $this->storehouseIds();
                $allIds = array_merge($wh[1000], $wh[400]);
                $codes = array_values(array_unique(array_map(static fn($r) => self::normalizeCode((string)$r['item_code']), $master)));
                $ph = implode(',', array_fill(0, count($codes), '?'));
                $sql = "SELECT it.id AS item_id, it.item_number_prod, it.item_title, iss.st_id,
                               SUM(iss.iss_inventory) AS inv, SUM(iss.iss_inventory_reserved) AS res
                        FROM item it
                        LEFT JOIN item_shops_storehouses iss ON iss.item_id = it.id"
                    . ($allIds !== [] ? ' AND iss.st_id IN (' . implode(',', array_map('intval', $allIds)) . ')' : ' AND 1 = 0')
                    . " WHERE it.item_number_prod IN ($ph)
                        GROUP BY it.id, it.item_number_prod, it.item_title, iss.st_id";
                foreach ($this->all($this->erp, $sql, $codes) as $r) {
                    $code = self::normalizeCode((string)$r['item_number_prod']);
                    $items[$code] = ['id' => (int)$r['item_id'], 'title' => trim((string)$r['item_title'])];
                    if ($r['st_id'] !== null) {
                        $stock[$code][(int)$r['st_id']] = ['inv' => (float)$r['inv'], 'res' => (float)$r['res']];
                    }
                }
            } catch (Throwable $e) {
                $erpOk = false;
                $error = 'No se pudo leer el stock del ERP: ' . $e->getMessage();
                $items = [];
                $stock = [];
                $wh = [1000 => [], 400 => []];
            }
        } else {
            $wh = [1000 => [], 400 => []];
        }
        $sumFor = static function (array $byStore, array $ids): array {
            $inv = 0.0;
            $res = 0.0;
            foreach ($ids as $id) {
                $inv += (float)($byStore[$id]['inv'] ?? 0);
                $res += (float)($byStore[$id]['res'] ?? 0);
            }
            return [$inv, $res];
        };
        // NV sin despachar y producción pendiente (consultas agrupadas, con caché corta).
        $flow = ['reserved' => [], 'production' => []];
        $flowOk = false;
        $flowError = '';
        $itemIds = array_values(array_filter(array_map(static fn($i) => (int)$i['id'], $items)));
        if ($erpOk && $itemIds !== []) {
            try {
                $flow = $this->flowSummary($itemIds);
                $flowOk = true;
            } catch (Throwable $e) {
                $flowError = 'No se pudieron leer las NV y la producción del ERP: ' . $e->getMessage();
            }
        }
        $rows = [];
        foreach ($master as $m) {
            $code = self::normalizeCode((string)$m['item_code']);
            $wcode = (int)($m['warehouse_code'] ?? 0);
            $exists = isset($items[$code]);
            [$inv] = in_array($wcode, self::WAREHOUSES, true) ? $sumFor($stock[$code] ?? [], $wh[$wcode] ?? []) : [0.0, 0.0];
            $otherCode = $wcode === 1000 ? 400 : ($wcode === 400 ? 1000 : 0);
            [$otherInv] = $otherCode > 0 ? $sumFor($stock[$code] ?? [], $wh[$otherCode] ?? []) : [0.0];
            $calc = self::compute($m, $inv, $exists || !$erpOk);
            if (!$erpOk) {
                $calc['status'] = 'Sin conexión ERP';
            }
            $itemId = (int)($items[$code]['id'] ?? 0);
            $stockQty = (int)floor($inv + 1e-9);
            $res = $flow['reserved'][$itemId] ?? ['qty' => 0, 'orders' => 0, 'due' => 0];
            $prod = ($flow['production'][$itemId] ?? []) + ['qty' => 0, 'orders' => 0, 'print' => 0, 'seal' => 0];
            $reserved = ($flowOk && $exists) ? (int)round($res['qty']) : null;
            $inProd = ($flowOk && $exists) ? (int)round($prod['qty']) : null;
            $projected = ($reserved !== null && $inProd !== null) ? $stockQty + $inProd - $reserved : null;
            $projCalc = $projected !== null ? self::compute($m, max(0, $projected), $exists) : null;
            $rows[] = [
                'id' => (int)$m['id'], 'code' => (string)$m['item_code'], 'description' => (string)$m['description'],
                'group_label' => (string)($m['group_label'] ?? ''),
                'erp_title' => $items[$code]['title'] ?? '', 'warehouse_code' => $wcode > 0 ? $wcode : null,
                'units_per_box' => $m['units_per_box'] !== null ? (int)$m['units_per_box'] : null,
                'boxes_per_pallet' => $m['boxes_per_pallet'] !== null ? (int)$m['boxes_per_pallet'] : null,
                'target_units' => $m['target_units'] !== null ? (int)$m['target_units'] : null,
                'min_units' => $m['min_units'] !== null ? (int)$m['min_units'] : null,
                'stock' => $stockQty, 'other_stock' => (int)floor($otherInv + 1e-9),
                'reserved' => $reserved, 'reserved_orders' => (int)$res['orders'],
                'oldest_due' => (int)$res['due'] > 0 ? date('Y-m-d', (int)$res['due']) : '',
                'free' => $reserved !== null ? $stockQty - $reserved : null,
                'in_production' => $inProd, 'production_orders' => (int)$prod['orders'],
                'to_print' => $inProd !== null ? (int)round($prod['print']) : null, 'to_seal' => $inProd !== null ? (int)round($prod['seal']) : null,
                'projected' => $projected,
                'projected_diff' => ($projected !== null && $calc['diff'] !== null) ? $projected - (int)$m['target_units'] : null,
                'projected_status' => $projCalc === null ? '' : ($projected < 0 && in_array($projCalc['status'], ['OK', 'Bajo', 'Sin objetivo'], true) ? 'Crítico' : $projCalc['status']),
                'exists' => $exists,
            ] + $calc;
        }
        return ['rows' => $rows, 'erp_ok' => $erpOk, 'error' => $error, 'flow_ok' => $flowOk, 'flow_error' => $flowError,
            'generated_at' => date('Y-m-d H:i:s')];
    }

    // ------------------------------------------------------------------ NV y producción (solo lectura del ERP)

    private const FLOW_CACHE_TTL = 120;
    private const PROCESS_NAMES = [7 => 'Flexografía', 8 => 'Corte y Sellado', 11 => 'Serigrafía', 22 => 'Pulpo Serigráfico',
        12 => 'Rebobinado', 15 => 'Embalaje'];
    /** Procesos que cuentan en bolsas (flexo y rebobinado cuentan en metros). */
    private const UNIT_PROCESSES = [7, 8, 11, 22, 15];

    /** @var array<string,array<string,bool>> */
    private array $erpColumnCache = [];

    /** @return array<string,bool> columnas en minúscula; vacío si no se pudo consultar */
    private function erpColumns(string $table): array
    {
        if (isset($this->erpColumnCache[$table])) {
            return $this->erpColumnCache[$table];
        }
        $cols = [];
        try {
            if ($this->erp->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                foreach ($this->all($this->erp, 'PRAGMA table_info(' . preg_replace('/\W/', '', $table) . ')') as $r) {
                    $cols[strtolower((string)$r['name'])] = true;
                }
            } else {
                // Las columnas de una tabla se recuerdan un día entre solicitudes (antes: 5 a 6 consultas por carga).
                $cols = (array)ResultCache::remember('erp-table-columns-' . $table, 86400, function () use ($table): array {
                    $found = [];
                    foreach ($this->all($this->erp, 'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]) as $r) {
                        $found[strtolower((string)$r['COLUMN_NAME'])] = true;
                    }
                    return $found;
                });
            }
        } catch (Throwable) {
            $cols = [];
        }
        if ($cols !== []) {
            $this->erpColumnCache[$table] = $cols; // si no se pudo leer (o la tabla aún no existe), se reintenta la próxima vez
        }
        return $cols;
    }

    /** La columna existe (si no se pudo consultar el esquema, se asume que sí). */
    private function has(string $table, string $column): bool
    {
        $cols = $this->erpColumns($table);
        return $cols === [] || isset($cols[strtolower($column)]);
    }

    private function col(string $table, string $alias, string $column, string $fallback = '0'): string
    {
        return $this->has($table, $column) ? $alias . '.' . $column : $fallback;
    }

    private static function inList(array $ids): string
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($v) => $v > 0)));
        return $ids === [] ? '0' : implode(',', $ids);
    }

    /** Condiciones de línea abierta de una NV (regla del informe «Stock reservado» del sistema antiguo). */
    private function openLineWhere(): string
    {
        return ($this->has('orders_items', 'item_type') ? " AND oi.item_type = 'item'" : '')
            . ($this->has('orders_items', 'item_amount_shipped_stop') ? ' AND oi.item_amount_shipped_stop = 0' : '');
    }

    /** NV de venta: solo se cuentan las creadas en estos días (las más antiguas abiertas en el ERP son ventas ya entregadas sin cerrar). */
    public const SALES_MAX_AGE_DAYS = 90;
    /** CC de fabricación: cuenta si tuvo producción o embalaje en estos días… */
    public const PRODUCTION_IDLE_DAYS = 30;
    /** …o si aún no empieza y se creó hace menos de estos días. */
    public const PRODUCTION_NEW_DAYS = 120;
    /** CC con al menos esta fracción embalada se da por terminada (el saldo es merma). */
    public const PRODUCTION_DONE_RATIO = 0.95;

    private function hasFabricateFlag(): bool
    {
        $cols = $this->erpColumns('orders');
        return $cols !== [] && isset($cols['req_isfabricate']);
    }

    /**
     * NV de venta pendientes de despacho (una fila por NV y SKU con saldo).
     * Solo NV de venta (no las CC de fabricación, que nunca se despachan contra sí mismas), aprobadas o con despacho
     * parcial, sin cerrar. Despachado = lo mayor entre lo marcado en la NV y lo de sus guías no anuladas.
     *
     * @param bool $old false: NV de los últimos SALES_MAX_AGE_DAYS (se cuentan); true: las más antiguas (solo se informan)
     * @return array<int,array<string,mixed>>
     */
    private function pendingSales(array $itemIds, bool $old = false): array
    {
        $cutoff = strtotime(date('Y-m-d')) - self::SALES_MAX_AGE_DAYS * 86400;
        $sql = 'SELECT oi.item_id, o.id AS order_id, o.req_number, o.req_status, o.req_crtdat, o.req_cust_id,
                       ' . $this->col('orders', 'o', 'req_fec_despacho') . ' AS due_at,
                       ' . $this->col('orders', 'o', 'req_oc_number', "''") . ' AS oc_number,
                       ' . $this->col('orders', 'o', 'req_userid_seller') . ' AS seller_id,
                       ' . $this->col('orders', 'o', 'req_isreserva') . ' AS is_reserva,
                       SUM(oi.item_amount) AS ordered, SUM(oi.item_amount_shipped) AS shipped_nv
                FROM orders o
                JOIN orders_items oi ON oi.req_id = o.id
                WHERE oi.item_id IN (' . self::inList($itemIds) . ') AND o.req_status IN (2,3)'
            . ($this->hasFabricateFlag() ? ' AND o.req_isfabricate = 0' : '')
            . ($this->has('orders', 'req_order_shipped') ? ' AND o.req_order_shipped = 0' : '')
            . $this->openLineWhere()
            . ' AND oi.item_amount > oi.item_amount_shipped
                  AND o.req_crtdat ' . ($old ? '<' : '>=') . ' ' . $cutoff . '
                GROUP BY oi.item_id, o.id, o.req_number, o.req_status, o.req_crtdat, o.req_cust_id, due_at, oc_number, seller_id, is_reserva
                ORDER BY o.id';
        $lines = $this->all($this->erp, $sql);
        if ($lines === []) {
            return [];
        }
        $guides = [];
        $statusWhere = $this->has('orders_delivery', 'dlv_status') ? ' AND od.dlv_status <> 5' : '';
        $dateCol = $this->col('orders_delivery', 'od', 'dlv_delivery_date');
        foreach ($this->all($this->erp, "SELECT od.dlv_order_id, odi.item_id, SUM(odi.item_amount_shipped) AS qty, COUNT(DISTINCT od.id) AS n, MAX($dateCol) AS last_at
                FROM orders_delivery od JOIN orders_delivery_items odi ON odi.dlv_id = od.id
                WHERE od.dlv_order_id IN (" . self::inList(array_column($lines, 'order_id')) . ') AND odi.item_id IN (' . self::inList($itemIds) . ")$statusWhere
                GROUP BY od.dlv_order_id, odi.item_id") as $g) {
            $guides[(int)$g['dlv_order_id'] . ':' . (int)$g['item_id']] = ['qty' => (float)$g['qty'], 'n' => (int)$g['n'], 'last_at' => (int)$g['last_at']];
        }
        $out = [];
        foreach ($lines as $l) {
            $g = $guides[(int)$l['order_id'] . ':' . (int)$l['item_id']] ?? ['qty' => 0.0, 'n' => 0, 'last_at' => 0];
            $shipped = max((float)$l['shipped_nv'], $g['qty']);
            $pending = (float)$l['ordered'] - $shipped;
            if ($pending < 0.5) {
                continue;
            }
            $out[] = $l + ['shipped' => $shipped, 'pending' => $pending, 'guides' => $g['n'], 'last_guide_at' => $g['last_at']];
        }
        return $out;
    }

    /**
     * CC con unidades por fabricar del SKU: CC de fabricación (o con OT), aprobadas, con línea abierta.
     * Pendiente = pedido − lo ya embalado de la CC (repartido por línea si la CC tiene varios productos).
     * Cuenta solo si está viva: con producción o embalaje en los últimos PRODUCTION_IDLE_DAYS días, o sin empezar
     * y creada hace menos de PRODUCTION_NEW_DAYS. Las demás quedaron con saldo de merma o se dejaron de hacer.
     *
     * @param bool $stale false: las vivas (se cuentan); true: las con saldo sin movimiento (solo se informan)
     * @return array<int,array<string,mixed>> una fila por SKU y CC con pendiente > 0
     */
    private function productionLines(array $itemIds, bool $stale = false): array
    {
        $key = implode(',', $itemIds);
        $this->productionMemo[$key] ??= $this->productionCandidates($itemIds);
        return array_values(array_filter($this->productionMemo[$key], static fn($c) => $c['alive'] !== $stale));
    }

    /** @var array<string,array<int,array<string,mixed>>> */
    private array $productionMemo = [];

    /** Todas las CC con saldo, marcadas como vivas o no (con el motivo). */
    private function productionCandidates(array $itemIds): array
    {
        $fabricate = $this->hasFabricateFlag() ? ' OR o.req_isfabricate = 1' : '';
        $sql = 'SELECT oi.item_id, o.id AS order_id, o.req_number, o.req_status, o.req_crtdat, o.req_cust_id,
                       ' . $this->col('orders', 'o', 'req_fec_despacho') . ' AS due_at,
                       ' . $this->col('orders', 'o', 'req_userid_seller') . ' AS seller_id,
                       SUM(oi.item_amount) AS ordered, MAX(' . $this->col('orders_items', 'oi', 'fab_printtype', "''") . ') AS print_type
                FROM orders o
                JOIN orders_items oi ON oi.req_id = o.id
                WHERE oi.item_id IN (' . self::inList($itemIds) . ') AND o.req_status IN (2,3)' . $this->openLineWhere() . '
                  AND (o.id IN (SELECT ph.prd_reqid FROM prod_header ph WHERE ph.prd_status > 0)' . $fabricate . ')
                GROUP BY oi.item_id, o.id, o.req_number, o.req_status, o.req_crtdat, o.req_cust_id, due_at, seller_id
                ORDER BY o.id';
        $lines = $this->all($this->erp, $sql);
        if ($lines === []) {
            return [];
        }
        $orderIn = self::inList(array_column($lines, 'order_id'));
        // Total pedido de cada CC (todas sus líneas de producto), para repartir lo embalado.
        $orderTotal = [];
        foreach ($this->all($this->erp, 'SELECT oi.req_id, SUM(oi.item_amount) AS total, COUNT(DISTINCT oi.item_id) AS products
                FROM orders_items oi WHERE oi.req_id IN (' . $orderIn . ')' . $this->openLineWhere() . ' GROUP BY oi.req_id') as $r) {
            $orderTotal[(int)$r['req_id']] = ['total' => (float)$r['total'], 'products' => (int)$r['products']];
        }
        $packed = [];
        $pe = static fn(string $c): string => 'COALESCE(pe.' . $c . ',0)';
        $packExpr = $pe('req_embalaje_cajas_completas_amt') . ' * ' . $pe('req_embalaje_bolsas_por_caja_amt') . ' + ' . $pe('req_embalaje_caja_final')
            . ($this->has('prod_worker_embalajes', 'req_embalaje_bolsas_sobrantes_amt') ? ' + ' . $pe('req_embalaje_bolsas_sobrantes_amt') : '')
            . ($this->has('prod_worker_embalajes', 'req_embalaje_showroom') ? ' + ' . $pe('req_embalaje_showroom') : '');
        foreach ($this->all($this->erp, "SELECT pe.evt_reqid, SUM($packExpr) AS packed, MAX(pe.evt_crtdat) AS last_at
                FROM prod_worker_embalajes pe WHERE pe.evt_reqid IN ($orderIn) GROUP BY pe.evt_reqid") as $r) {
            $packed[(int)$r['evt_reqid']] = ['qty' => (float)$r['packed'], 'last_at' => (int)$r['last_at']];
        }
        $candidates = [];
        foreach ($lines as $l) {
            $oid = (int)$l['order_id'];
            $ordered = (float)$l['ordered'];
            $tot = $orderTotal[$oid] ?? ['total' => $ordered, 'products' => 1];
            $share = ($tot['products'] > 1 && $tot['total'] > 0) ? $ordered / $tot['total'] : 1.0;
            $packedQty = ($packed[$oid]['qty'] ?? 0.0) * $share;
            $pending = max(0.0, $ordered - $packedQty);
            if ($pending < 0.5) {
                continue;
            }
            $candidates[] = $l + ['ordered' => $ordered, 'packed' => round($packedQty), 'share' => $share,
                'packed_last_at' => (int)($packed[$oid]['last_at'] ?? 0), 'multi_product' => $tot['products'] > 1];
        }
        if ($candidates === []) {
            return [];
        }
        // Producción por etapa de cada CC (unidades; flexo y seri registran bolsas en evt_amount) y última actividad.
        $candIn = self::inList(array_column($candidates, 'order_id'));
        $stageQty = [];
        $lastProd = [];
        $commentFilter = $this->has('prod_worker_ot_events', 'evt_comments')
            ? " AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))" : '';
        foreach ($this->all($this->erp, "SELECT pa.ag_reqid, pa.ag_equipotype_id, pa.ag_equipo_id, SUM(e.evt_amount) AS qty, MAX(e.evt_crtdat) AS last_at
                FROM prod_agenda pa
                JOIN prod_worker_ot w ON w.wok_ag_id = pa.id
                JOIN prod_worker_ot_events e ON e.evt_prod_worker_otid = w.id
                WHERE pa.ag_reqid IN ($candIn) AND e.evt_type = 'prod' AND (e.evt_status IS NULL OR e.evt_status > 0)$commentFilter
                GROUP BY pa.ag_reqid, pa.ag_equipotype_id, pa.ag_equipo_id") as $r) {
            $oid = (int)$r['ag_reqid'];
            $stage = self::stageOf((int)$r['ag_equipotype_id'], (int)$r['ag_equipo_id']);
            $stageQty[$oid][$stage] = ($stageQty[$oid][$stage] ?? 0.0) + (float)$r['qty'];
            $lastProd[$oid] = max($lastProd[$oid] ?? 0, (int)$r['last_at']);
        }
        // Máquinas programadas por etapa (para saber si la CC lleva impresión).
        $hasStage = [];
        foreach ($this->all($this->erp, "SELECT pa.ag_reqid, pa.ag_equipotype_id, pa.ag_equipo_id FROM prod_agenda pa
                WHERE pa.ag_reqid IN ($candIn) AND pa.ag_status > 0 GROUP BY pa.ag_reqid, pa.ag_equipotype_id, pa.ag_equipo_id") as $r) {
            $hasStage[(int)$r['ag_reqid']][self::stageOf((int)$r['ag_equipotype_id'], (int)$r['ag_equipo_id'])] = true;
        }
        $today = strtotime(date('Y-m-d'));
        $out = [];
        foreach ($candidates as $c) {
            $oid = (int)$c['order_id'];
            $share = (float)$c['share'];
            $ordered = (float)$c['ordered'];
            $printed = ($stageQty[$oid]['print'] ?? 0.0) * $share;
            $sealed = max(($stageQty[$oid]['seal'] ?? 0.0) * $share, (float)$c['packed']);
            $printType = strtoupper(trim((string)($c['print_type'] ?? '')));
            $needsPrint = isset($hasStage[$oid]['print']) || $printed > 0
                || (!isset($hasStage[$oid]['seal']) && $sealed <= 0 && !in_array($printType, ['', 'SIN', 'SIN IMPRESION', 'SIN IMPRESIÓN', 'NO'], true));
            $toPrint = $needsPrint ? max(0.0, $ordered - $printed) : 0.0;
            $toSeal = max(0.0, ($needsPrint ? min($printed, $ordered) : $ordered) - $sealed);
            $pending = $toPrint + $toSeal;
            if ($pending < 0.5) {
                continue;
            }
            $last = max((int)$c['packed_last_at'], $lastProd[$oid] ?? 0);
            $reason = '';
            if ($sealed >= self::PRODUCTION_DONE_RATIO * $ordered) {
                $reason = 'Sellado/embalado ≥ ' . (int)round(self::PRODUCTION_DONE_RATIO * 100) . ' % (saldo de merma)';
            } elseif ($last > 0 && $last < $today - self::PRODUCTION_IDLE_DAYS * 86400) {
                $reason = 'Sin producción ni embalaje hace más de ' . self::PRODUCTION_IDLE_DAYS . ' días';
            } elseif ($last === 0 && (int)$c['req_crtdat'] < $today - self::PRODUCTION_NEW_DAYS * 86400) {
                $reason = 'Nunca empezó (creada hace más de ' . self::PRODUCTION_NEW_DAYS . ' días)';
            }
            $out[] = $c + ['printed' => round($printed), 'sealed' => round($sealed), 'needs_print' => $needsPrint,
                'to_print' => round($toPrint), 'to_seal' => round($toSeal), 'pending' => round($pending),
                'last_activity_at' => $last, 'alive' => $reason === '', 'excluded_reason' => $reason];
        }
        return $out;
    }

    /** Etapa del proceso: impresión (flexo, serigrafía plana), corte y sellado, embalaje u otra. */
    private static function stageOf(int $type, int $equipoId): string
    {
        if ($equipoId === 36 || $type === 22) {
            return 'other'; // el pulpo imprime bolsas ya hechas, después del corte y sellado
        }
        return match ($type) {
            7, 11 => 'print',
            8 => 'seal',
            15 => 'pack',
            default => 'other',
        };
    }

    /** @return array{reserved:array<int,array{qty:float,orders:int,due:int}>,production:array<int,array{qty:float,orders:int}>} */
    private function flowSummary(array $itemIds): array
    {
        sort($itemIds);
        $cacheFile = null;
        if ($this->erp->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $cacheFile = dirname(__DIR__) . '/storage/cache/expected-stock-flow-v3-' . substr(hash('sha256', implode(',', $itemIds)), 0, 16) . '.json';
            $cached = is_file($cacheFile) && filemtime($cacheFile) > time() - self::FLOW_CACHE_TTL ? json_decode((string)@file_get_contents($cacheFile), true) : null;
            if (is_array($cached) && isset($cached['reserved'], $cached['production'])) {
                return ['reserved' => self::intKeys($cached['reserved']), 'production' => self::intKeys($cached['production'])];
            }
        }
        $reserved = [];
        foreach ($this->pendingSales($itemIds) as $l) {
            $iid = (int)$l['item_id'];
            $reserved[$iid] ??= ['qty' => 0.0, 'orders' => [], 'due' => 0];
            $reserved[$iid]['qty'] += (float)$l['pending'];
            $reserved[$iid]['orders'][(int)$l['order_id']] = true;
            $due = (int)$l['due_at'];
            if ($due > 0 && ($reserved[$iid]['due'] === 0 || $due < $reserved[$iid]['due'])) {
                $reserved[$iid]['due'] = $due;
            }
        }
        foreach ($reserved as $iid => $r) {
            $reserved[$iid]['orders'] = count($r['orders']);
        }
        $production = [];
        foreach ($this->productionLines($itemIds) as $l) {
            $iid = (int)$l['item_id'];
            $production[$iid] ??= ['qty' => 0.0, 'orders' => 0, 'print' => 0.0, 'seal' => 0.0];
            $production[$iid]['qty'] += (float)$l['pending'];
            $production[$iid]['print'] += (float)$l['to_print'];
            $production[$iid]['seal'] += (float)$l['to_seal'];
            $production[$iid]['orders']++;
        }
        $data = ['reserved' => $reserved, 'production' => $production];
        if ($cacheFile !== null) {
            @file_put_contents($cacheFile, json_encode($data), LOCK_EX);
        }
        return $data;
    }

    private static function intKeys(array $a): array
    {
        $out = [];
        foreach ($a as $k => $v) {
            $out[(int)$k] = $v;
        }
        return $out;
    }

    /** @return array{id:int,code:string,title:string} */
    private function itemByCode(string $code): array
    {
        if ($this->erp === null) {
            throw new RuntimeException('Sin conexión con el ERP.');
        }
        $norm = self::normalizeCode($code);
        $r = $this->all($this->erp, 'SELECT id, item_number_prod, item_title FROM item WHERE item_number_prod = ? LIMIT 1', [$norm]);
        if ($r === []) {
            throw new RuntimeException('El código ' . $norm . ' no existe en el ERP.');
        }
        return ['id' => (int)$r[0]['id'], 'code' => $norm, 'title' => trim((string)$r[0]['item_title'])];
    }

    /** @return array<int,string> */
    private function customerNames(array $ids): array
    {
        $out = [];
        if (self::inList($ids) === '0') {
            return $out;
        }
        foreach ($this->all($this->erp, 'SELECT id, cust_name FROM customer WHERE id IN (' . self::inList($ids) . ')') as $r) {
            $out[(int)$r['id']] = trim((string)$r['cust_name']);
        }
        return $out;
    }

    /** @return array<int,string> */
    private function userNames(array $ids): array
    {
        $out = [];
        if (self::inList($ids) === '0') {
            return $out;
        }
        try {
            foreach ($this->all($this->erp, 'SELECT id, user_firstname, user_lastname FROM user WHERE id IN (' . self::inList($ids) . ')') as $r) {
                $out[(int)$r['id']] = trim((string)$r['user_firstname'] . ' ' . (string)$r['user_lastname']);
            }
        } catch (Throwable) {
            // sin nombres de vendedor
        }
        return $out;
    }

    /**
     * Stock de cada NV/CC en las bodegas (filas iss_order_id = NV).
     *
     * @return array<int,array{qty:int,places:string}>
     */
    private function stockByOrder(int $itemId, array $orderIds): array
    {
        $out = [];
        if (self::inList($orderIds) === '0') {
            return $out;
        }
        $names = [];
        foreach ($this->all($this->erp, 'SELECT id, st_name FROM company_shops_storehouses') as $w) {
            $names[(int)$w['id']] = trim((string)$w['st_name']);
        }
        foreach ($this->all($this->erp, 'SELECT iss.iss_order_id, iss.st_id, SUM(iss.iss_inventory) AS qty FROM item_shops_storehouses iss
                WHERE iss.item_id = ? AND iss.iss_order_id IN (' . self::inList($orderIds) . ') GROUP BY iss.iss_order_id, iss.st_id', [$itemId]) as $r) {
            $q = (int)floor((float)$r['qty'] + 1e-9);
            if ($q === 0) {
                continue;
            }
            $oid = (int)$r['iss_order_id'];
            $out[$oid] ??= ['qty' => 0, 'places' => []];
            $out[$oid]['qty'] += $q;
            $label = $names[(int)$r['st_id']] ?? ('Bodega ' . (int)$r['st_id']);
            $out[$oid]['places'][] = (preg_match('/^\s*(\d{3,4})/', $label, $mm) ? $mm[1] : $label) . ': ' . number_format($q, 0, ',', '.');
        }
        foreach ($out as $oid => $v) {
            $out[$oid]['places'] = implode(' · ', $v['places']);
        }
        return $out;
    }

    private static function day(int $ts): string
    {
        return $ts > 0 ? date('Y-m-d', $ts) : '';
    }

    /** Resumen del SKU para la cabecera de la ventana (misma fila del informe). */
    private function summaryFor(string $code): ?array
    {
        foreach ($this->report()['rows'] as $r) {
            if (self::normalizeCode((string)$r['code']) === self::normalizeCode($code)) {
                return $r;
            }
        }
        return null;
    }

    /**
     * NV pendientes de despacho de un SKU, con cliente, vendedor, fechas, guías y stock ya asignado.
     *
     * @return array{item:array,summary:?array,rows:array<int,array<string,mixed>>,totals:array<string,int>}
     */
    public function reservedDetail(string $code): array
    {
        return $this->cachedDetail('reserved', $code, fn(): array => $this->computeReservedDetail($code));
    }

    /**
     * Las ventanas de detalle tardaban 2,5 a 6,6 s (recorren eventos y guías del ERP). Se recuerdan lo mismo que
     * el resumen (FLOW_CACHE_TTL): abrir dos veces el mismo SKU ya no recalcula. En pruebas (sqlite) no hay caché.
     */
    private function cachedDetail(string $kind, string $code, callable $compute): array
    {
        if ($this->erp === null || $this->erp->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return $compute();
        }
        return (array)ResultCache::remember('expected-stock-' . $kind . '-' . strtoupper(trim($code)), self::FLOW_CACHE_TTL, $compute);
    }

    private function computeReservedDetail(string $code): array
    {
        $item = $this->itemByCode($code);
        $lines = $this->pendingSales([$item['id']]);
        $byOrder = [];
        foreach ($lines as $l) {
            $byOrder[(int)$l['order_id']] = $l;
        }
        $orderIds = array_keys($byOrder);
        $old = $this->pendingSales([$item['id']], true);
        $customers = $this->customerNames(array_merge(array_column($lines, 'req_cust_id'), array_column($old, 'req_cust_id')));
        $sellers = $this->userNames(array_column($lines, 'seller_id'));
        $stock = $this->stockByOrder($item['id'], $orderIds);
        $ots = [];
        if ($orderIds !== []) {
            foreach ($this->all($this->erp, 'SELECT ph.prd_reqid, ph.prd_number FROM prod_header ph WHERE ph.prd_status > 0 AND ph.prd_reqid IN (' . self::inList($orderIds) . ')') as $p) {
                $ots[(int)$p['prd_reqid']][] = (string)$p['prd_number'];
            }
        }
        $today = strtotime(date('Y-m-d'));
        $age = static fn(int $ts): ?int => $ts > 0 ? (int)floor(($today - strtotime(date('Y-m-d', $ts))) / 86400) : null;
        $rows = [];
        $tot = ['ordered' => 0, 'shipped' => 0, 'pending' => 0, 'stock' => 0, 'orders' => 0];
        foreach ($byOrder as $oid => $o) {
            $pending = (int)round((float)$o['pending']);
            $due = (int)$o['due_at'];
            $created = (int)$o['req_crtdat'];
            $rows[] = [
                'order_id' => $oid, 'nv' => (string)$o['req_number'], 'customer' => $customers[(int)$o['req_cust_id']] ?? '',
                'seller' => $sellers[(int)$o['seller_id']] ?? '', 'oc' => trim((string)$o['oc_number']),
                'created' => self::day($created), 'age_days' => $age($created),
                'due' => self::day($due), 'overdue' => $due > 0 && $due < $today,
                'status' => (int)$o['req_status'] === 3 ? 'Despacho parcial' : 'Aprobada', 'is_reserva' => (int)$o['is_reserva'] === 1,
                'ordered' => (int)round((float)$o['ordered']), 'shipped' => (int)round((float)$o['shipped']), 'pending' => $pending,
                'guides' => (int)$o['guides'], 'last_guide' => self::day((int)$o['last_guide_at']),
                'stock' => $stock[$oid]['qty'] ?? 0, 'stock_places' => $stock[$oid]['places'] ?? '',
                'ots' => implode(', ', $ots[$oid] ?? []),
            ];
            $tot['ordered'] += (int)round((float)$o['ordered']);
            $tot['shipped'] += (int)round((float)$o['shipped']);
            $tot['pending'] += $pending;
            $tot['stock'] += $stock[$oid]['qty'] ?? 0;
            $tot['orders']++;
        }
        usort($rows, static fn($a, $b) => [$a['due'] === '' ? '9' : $a['due'], $a['order_id']] <=> [$b['due'] === '' ? '9' : $b['due'], $b['order_id']]);
        // NV de venta antiguas que siguen abiertas en el ERP: no se cuentan (se informan para cerrarlas en el ERP).
        $oldRows = [];
        $oldTot = ['orders' => 0, 'pending' => 0];
        foreach ($old as $o) {
            $oldRows[] = ['order_id' => (int)$o['order_id'], 'nv' => (string)$o['req_number'], 'customer' => $customers[(int)$o['req_cust_id']] ?? '',
                'created' => self::day((int)$o['req_crtdat']), 'age_days' => $age((int)$o['req_crtdat']), 'ordered' => (int)round((float)$o['ordered']),
                'shipped' => (int)round((float)$o['shipped']), 'pending' => (int)round((float)$o['pending']), 'guides' => (int)$o['guides']];
            $oldTot['orders']++;
            $oldTot['pending'] += (int)round((float)$o['pending']);
        }
        usort($oldRows, static fn($a, $b) => $b['order_id'] <=> $a['order_id']);
        return ['item' => $item, 'summary' => $this->summaryFor($item['code']), 'rows' => $rows, 'totals' => $tot,
            'excluded' => $oldRows, 'excluded_totals' => $oldTot, 'max_age_days' => self::SALES_MAX_AGE_DAYS];
    }

    /**
     * CC y OT con unidades por fabricar de un SKU: pedido, embalado, pendiente, etapa actual y agendas por máquina.
     *
     * @return array{item:array,summary:?array,rows:array<int,array<string,mixed>>,totals:array<string,int>}
     */
    public function productionDetail(string $code): array
    {
        return $this->cachedDetail('production', $code, fn(): array => $this->computeProductionDetail($code));
    }

    private function computeProductionDetail(string $code): array
    {
        $item = $this->itemByCode($code);
        $lines = $this->productionLines([$item['id']]);
        $orderIds = array_map(static fn($l) => (int)$l['order_id'], $lines);
        $in = self::inList($orderIds);
        $customers = $this->customerNames(array_column($lines, 'req_cust_id'));
        $sellers = $this->userNames(array_column($lines, 'seller_id'));
        $stock = $this->stockByOrder($item['id'], $orderIds);
        $headers = [];
        $plans = [];
        $agendas = [];
        if ($orderIds !== []) {
            foreach ($this->all($this->erp, "SELECT ph.id, ph.prd_reqid, ph.prd_number, ph.prd_status FROM prod_header ph WHERE ph.prd_status > 0 AND ph.prd_reqid IN ($in) ORDER BY ph.id") as $h) {
                $headers[(int)$h['prd_reqid']][] = $h;
            }
            $headerIds = [];
            foreach ($headers as $list) {
                foreach ($list as $h) {
                    $headerIds[] = (int)$h['id'];
                }
            }
            if ($headerIds !== []) {
                try {
                    foreach ($this->all($this->erp, 'SELECT prodplan_prdid, prodplan_amt, prodplan_date FROM prod_amtplan WHERE prodplan_prdid IN (' . self::inList($headerIds) . ') ORDER BY prodplan_date, id') as $p) {
                        $plans[(int)$p['prodplan_prdid']][] = ['date' => self::day((int)$p['prodplan_date']), 'qty' => (int)round((float)$p['prodplan_amt'])];
                    }
                } catch (Throwable) {
                    $plans = [];
                }
            }
            $agRows = $this->all($this->erp, "SELECT pa.id, pa.ag_reqid, pa.ag_prdid, pa.ag_equipo_id, pa.ag_equipotype_id, pa.ag_date, pa.ag_amount, pa.ag_status,
                    e.equipo_name, e.equipo_type_id
                FROM prod_agenda pa LEFT JOIN equipo e ON e.id = pa.ag_equipo_id
                WHERE pa.ag_reqid IN ($in) AND pa.ag_status > 0 ORDER BY pa.id");
            $agIds = array_map(static fn($a) => (int)$a['id'], $agRows);
            $woks = [];
            $produced = [];
            if ($agIds !== []) {
                $wokRows = $this->all($this->erp, 'SELECT w.id, w.wok_ag_id, w.wok_status, w.wok_init_id, ' . $this->col('prod_worker_ot', 'w', 'wok_crtdat') . ' AS wok_crtdat, ' . $this->col('prod_worker_init', 'wi', 'win_status', '1') . ' AS win_status
                    FROM prod_worker_ot w LEFT JOIN prod_worker_init wi ON wi.id = w.wok_init_id WHERE w.wok_ag_id IN (' . self::inList($agIds) . ')');
                $initIds = array_map(static fn($w) => (int)$w['wok_init_id'], $wokRows);
                $operators = [];
                if (self::inList($initIds) !== '0') {
                    try {
                        foreach ($this->all($this->erp, 'SELECT wi.id, wk.wrk_firstname, wk.wrk_lastname FROM prod_worker_init wi LEFT JOIN workers wk ON wk.id = wi.win_wrkid WHERE wi.id IN (' . self::inList($initIds) . ')') as $o) {
                            $operators[(int)$o['id']] = trim((string)$o['wrk_firstname'] . ' ' . (string)$o['wrk_lastname']);
                        }
                    } catch (Throwable) {
                        $operators = [];
                    }
                }
                $wokToAg = [];
                foreach ($wokRows as $w) {
                    $ag = (int)$w['wok_ag_id'];
                    $wokToAg[(int)$w['id']] = $ag;
                    $woks[$ag] ??= ['open' => false, 'operator' => '', 'runs' => 0];
                    $woks[$ag]['runs']++;
                    // En curso solo si la OT sigue abierta, su turno también (1 activo, 3 en pausa) y se abrió hace menos de 2 días:
                    // hay OT que quedaron abiertas con el turno ya cerrado y no están en máquina.
                    if ((int)$w['wok_status'] === 1 && in_array((int)$w['win_status'], [1, 3], true)
                        && ((int)$w['wok_crtdat'] === 0 || (int)$w['wok_crtdat'] >= time() - 2 * 86400)) {
                        $woks[$ag]['open'] = true;
                        $woks[$ag]['operator'] = $operators[(int)$w['wok_init_id']] ?? '';
                    }
                }
                if ($wokToAg !== []) {
                    $commentFilter = $this->has('prod_worker_ot_events', 'evt_comments')
                        ? " AND (e.evt_comments IS NULL OR (e.evt_comments NOT LIKE '%Bobina Semielaborada%' AND e.evt_comments NOT LIKE '%ROLL-SE-%'))" : '';
                    foreach ($this->all($this->erp, "SELECT e.evt_prod_worker_otid, SUM(e.evt_amount) AS qty, MAX(e.evt_crtdat) AS last_at FROM prod_worker_ot_events e
                            WHERE e.evt_prod_worker_otid IN (" . self::inList(array_keys($wokToAg)) . ") AND e.evt_type = 'prod' AND (e.evt_status IS NULL OR e.evt_status > 0)$commentFilter
                            GROUP BY e.evt_prod_worker_otid") as $ev) {
                        $ag = $wokToAg[(int)$ev['evt_prod_worker_otid']] ?? 0;
                        $produced[$ag] ??= ['qty' => 0.0, 'last_at' => 0];
                        $produced[$ag]['qty'] += (float)$ev['qty'];
                        $produced[$ag]['last_at'] = max($produced[$ag]['last_at'], (int)$ev['last_at']);
                    }
                }
            }
            foreach ($agRows as $a) {
                $agId = (int)$a['id'];
                $type = (int)($a['equipo_type_id'] ?: $a['ag_equipotype_id']);
                if ((int)$a['ag_equipo_id'] === 36) {
                    $type = 22;
                }
                $isOpen = $woks[$agId]['open'] ?? false;
                $prodQty = (int)round($produced[$agId]['qty'] ?? 0);
                $agendas[(int)$a['ag_reqid']][] = [
                    'machine' => trim((string)($a['equipo_name'] ?? '')) ?: ('Equipo ' . (int)$a['ag_equipo_id']),
                    'process' => self::PROCESS_NAMES[$type] ?? 'Otro', 'type' => $type,
                    'unit' => in_array($type, self::UNIT_PROCESSES, true) ? 'u' : '',
                    'date' => trim((string)$a['ag_date']), 'planned' => (int)round((float)$a['ag_amount']), 'produced' => $prodQty,
                    'last_at' => (int)($produced[$agId]['last_at'] ?? 0),
                    'state' => $isOpen ? 'En curso' : ($prodQty > 0 ? 'Con producción' : 'Programada'),
                    'operator' => $woks[$agId]['operator'] ?? '',
                ];
            }
        }
        $today = strtotime(date('Y-m-d'));
        $rows = [];
        $tot = ['ordered' => 0, 'packed' => 0, 'printed' => 0, 'sealed' => 0, 'to_print' => 0, 'to_seal' => 0, 'pending' => 0, 'stock' => 0, 'orders' => 0];
        foreach ($lines as $l) {
            $oid = (int)$l['order_id'];
            $ag = $agendas[$oid] ?? [];
            $stage = ($headers[$oid] ?? []) !== [] ? 'OT creada, sin máquina programada' : 'Sin programar';
            $lastActivity = (int)$l['packed_last_at'];
            $open = array_values(array_filter($ag, static fn($a) => $a['state'] === 'En curso'));
            if ($open !== []) {
                $stage = implode(' · ', array_map(static fn($a) => $a['process'] . ' — ' . $a['machine'] . ($a['operator'] !== '' ? ' (' . $a['operator'] . ')' : ''), $open));
            } elseif ($ag !== []) {
                $withProd = array_values(array_filter($ag, static fn($a) => $a['produced'] > 0));
                if ($withProd !== []) {
                    usort($withProd, static fn($a, $b) => $b['last_at'] <=> $a['last_at']);
                    $stage = 'Último: ' . $withProd[0]['process'] . ' — ' . $withProd[0]['machine'];
                } else {
                    $stage = 'Programada: ' . implode(', ', array_unique(array_map(static fn($a) => $a['process'], $ag)));
                }
            }
            foreach ($ag as $a) {
                $lastActivity = max($lastActivity, $a['last_at']);
            }
            $hdrs = $headers[$oid] ?? [];
            $planList = [];
            foreach ($hdrs as $h) {
                foreach ($plans[(int)$h['id']] ?? [] as $p) {
                    $planList[] = $p;
                }
            }
            $due = (int)$l['due_at'];
            $nextPlan = '';
            foreach ($planList as $p) {
                if ($p['date'] !== '' && ($nextPlan === '' || $p['date'] < $nextPlan) && strtotime($p['date']) >= $today) {
                    $nextPlan = $p['date'];
                }
            }
            $ordered = (int)round((float)$l['ordered']);
            $packed = (int)$l['packed'];
            foreach ($ag as $i => $a) {
                unset($ag[$i]['last_at']);
                $ag[$i]['last'] = self::day($a['last_at']);
            }
            $rows[] = [
                'order_id' => $oid, 'cc' => (string)$l['req_number'], 'ot' => implode(', ', array_map(static fn($h) => (string)$h['prd_number'], $hdrs)),
                'customer' => $customers[(int)$l['req_cust_id']] ?? '', 'seller' => $sellers[(int)$l['seller_id']] ?? '',
                'created' => self::day((int)$l['req_crtdat']), 'due' => self::day($due), 'overdue' => $due > 0 && $due < $today,
                'next_plan' => $nextPlan, 'plans' => $planList,
                'ordered' => $ordered, 'packed' => $packed, 'pending' => (int)$l['pending'],
                'printed' => (int)$l['printed'], 'sealed' => (int)$l['sealed'], 'needs_print' => (bool)$l['needs_print'],
                'to_print' => (int)$l['to_print'], 'to_seal' => (int)$l['to_seal'],
                'progress' => $ordered > 0 ? min(100, (int)round((int)$l['sealed'] * 100 / $ordered)) : 0,
                'stage' => $stage, 'agendas' => $ag, 'multi_product' => (bool)$l['multi_product'],
                'last_activity' => self::day($lastActivity),
                'idle_days' => $lastActivity > 0 ? (int)floor(($today - strtotime(date('Y-m-d', $lastActivity))) / 86400) : null,
                'stock' => $stock[$oid]['qty'] ?? 0, 'stock_places' => $stock[$oid]['places'] ?? '',
                'status' => (int)$l['req_status'] === 3 ? 'Despacho parcial' : 'Aprobada',
            ];
            $tot['ordered'] += $ordered;
            $tot['packed'] += $packed;
            $tot['pending'] += (int)$l['pending'];
            foreach (['printed', 'sealed', 'to_print', 'to_seal'] as $k) {
                $tot[$k] += (int)$l[$k];
            }
            $tot['stock'] += $stock[$oid]['qty'] ?? 0;
            $tot['orders']++;
        }
        usort($rows, static fn($a, $b) => [$a['due'] === '' ? '9' : $a['due'], $a['order_id']] <=> [$b['due'] === '' ? '9' : $b['due'], $b['order_id']]);
        // CC con saldo pero sin movimiento: no se cuentan (merma de cierre o pedido que se dejó de hacer).
        $staleRows = [];
        $staleTot = ['orders' => 0, 'pending' => 0];
        $stale = $this->productionLines([$item['id']], true);
        $staleCustomers = $this->customerNames(array_column($stale, 'req_cust_id'));
        foreach ($stale as $l) {
            $staleRows[] = ['order_id' => (int)$l['order_id'], 'cc' => (string)$l['req_number'], 'customer' => $staleCustomers[(int)$l['req_cust_id']] ?? '',
                'created' => self::day((int)$l['req_crtdat']), 'ordered' => (int)round((float)$l['ordered']), 'packed' => (int)$l['packed'],
                'to_print' => (int)$l['to_print'], 'to_seal' => (int)$l['to_seal'],
                'pending' => (int)$l['pending'], 'last_activity' => self::day((int)$l['last_activity_at']), 'reason' => (string)$l['excluded_reason']];
            $staleTot['orders']++;
            $staleTot['pending'] += (int)$l['pending'];
        }
        usort($staleRows, static fn($a, $b) => $b['order_id'] <=> $a['order_id']);
        return ['item' => $item, 'summary' => $this->summaryFor($item['code']), 'rows' => $rows, 'totals' => $tot,
            'excluded' => $staleRows, 'excluded_totals' => $staleTot, 'idle_days' => self::PRODUCTION_IDLE_DAYS, 'new_days' => self::PRODUCTION_NEW_DAYS];
    }

    // ------------------------------------------------------------------ escritura

    private function actorName(array $actor): string
    {
        $n = trim((string)($actor['name'] ?? ''));
        if ($n === '') {
            throw new RuntimeException('No se pudo identificar al usuario.');
        }
        return mb_substr($n, 0, 120);
    }

    private function log(string $code, string $field, ?string $old, ?string $new, string $actor): void
    {
        $this->trz->prepare('INSERT INTO expected_stock_history (item_code, field, old_value, new_value, actor) VALUES (?,?,?,?,?)')
            ->execute([$code, $field, $old, $new, $actor]);
    }

    private static function intOrNull(mixed $v, string $label, ?array $allowed = null): ?int
    {
        $s = trim((string)$v);
        if ($s === '') {
            return null;
        }
        if (!preg_match('/^\d{1,9}$/', str_replace(['.', ' '], '', $s))) {
            throw new RuntimeException($label . ' debe ser un número entero positivo.');
        }
        $n = (int)str_replace(['.', ' '], '', $s);
        if ($allowed !== null && !in_array($n, $allowed, true)) {
            throw new RuntimeException($label . ' debe ser ' . implode(' o ', $allowed) . '.');
        }
        return $n;
    }

    /** Agrega un código al maestro. Si no se indica descripción se usa la del ERP. */
    public function addItem(string $code, string $description, array $actor): array
    {
        $this->ensureSchema();
        $who = $this->actorName($actor);
        $norm = self::normalizeCode($code);
        if ($norm === '' || strlen($norm) > 40) {
            throw new RuntimeException('Indica un código válido.');
        }
        if ($this->all($this->trz, 'SELECT id FROM expected_stock_master WHERE UPPER(item_code) = ?', [$norm]) !== []) {
            throw new RuntimeException('El código ' . $norm . ' ya está en el maestro.');
        }
        $erpTitle = '';
        $existsErp = null;
        if ($this->erp !== null) {
            try {
                $r = $this->all($this->erp, 'SELECT item_title FROM item WHERE item_number_prod = ? LIMIT 1', [$norm]);
                $existsErp = $r !== [];
                $erpTitle = $existsErp ? trim((string)$r[0]['item_title']) : '';
            } catch (Throwable) {
                $existsErp = null;
            }
        }
        $desc = trim($description) !== '' ? trim($description) : $erpTitle;
        if ($desc === '') {
            throw new RuntimeException('Indica una descripción (el código no se encontró en el ERP para tomarla de ahí).');
        }
        $order = (int)($this->all($this->trz, 'SELECT COALESCE(MAX(sort_order),0) AS m FROM expected_stock_master')[0]['m'] ?? 0);
        $this->trz->prepare('INSERT INTO expected_stock_master (item_code, description, sort_order, created_by) VALUES (?,?,?,?)')
            ->execute([$norm, mb_substr($desc, 0, 200), $order + 1, $who]);
        $this->log($norm, 'alta', null, mb_substr($desc, 0, 200), $who);
        return ['code' => $norm, 'exists_in_erp' => $existsErp];
    }

    /**
     * Pega una lista: una línea por producto, "código [tab/;/,] descripción" o "descripción [tab] código".
     *
     * @return array{added:int,skipped:array<int,string>}
     */
    public function importList(string $text, array $actor): array
    {
        $this->ensureSchema();
        $added = 0;
        $skipped = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\t+|;|\s{2,}/', $line) ?: [];
            $parts = array_values(array_filter(array_map('trim', $parts), static fn($p) => $p !== ''));
            $code = '';
            $desc = '';
            foreach ($parts as $p) {
                if (preg_match('/^[A-Za-z]{3}\d{3,6}$/', $p) && $code === '') {
                    $code = $p;
                } elseif ($desc === '') {
                    $desc = $p;
                }
            }
            if ($code === '') {
                $skipped[] = $line . ' (sin código)';
                continue;
            }
            try {
                $this->addItem($code, $desc, $actor);
                $added++;
            } catch (Throwable $e) {
                $skipped[] = $line . ' (' . $e->getMessage() . ')';
            }
        }
        return ['added' => $added, 'skipped' => $skipped];
    }

    /**
     * Guarda cambios del maestro. $rows: id → campos. Solo registra en el historial lo que cambió.
     *
     * @return int cantidad de campos modificados
     */
    public function saveRows(array $rows, array $actor): int
    {
        $this->ensureSchema();
        $who = $this->actorName($actor);
        $changed = 0;
        $this->trz->beginTransaction();
        try {
            foreach ($rows as $id => $in) {
                $id = (int)$id;
                if ($id <= 0 || !is_array($in)) {
                    continue;
                }
                $cur = $this->all($this->trz, 'SELECT * FROM expected_stock_master WHERE id = ?', [$id])[0] ?? null;
                if (!$cur) {
                    continue;
                }
                $new = [
                    'description' => trim((string)($in['description'] ?? $cur['description'])),
                    'group_label' => trim((string)($in['group_label'] ?? (string)$cur['group_label'])),
                    'warehouse_code' => self::intOrNull($in['warehouse_code'] ?? $cur['warehouse_code'], 'La bodega', self::WAREHOUSES),
                    'units_per_box' => self::intOrNull($in['units_per_box'] ?? $cur['units_per_box'], 'Las unidades por caja'),
                    'boxes_per_pallet' => self::intOrNull($in['boxes_per_pallet'] ?? $cur['boxes_per_pallet'], 'Las cajas por pallet'),
                    'target_units' => self::intOrNull($in['target_units'] ?? $cur['target_units'], 'El stock objetivo'),
                    'min_units' => self::intOrNull($in['min_units'] ?? $cur['min_units'], 'El stock mínimo'),
                    'active' => isset($in['active']) ? (empty($in['active']) ? 0 : 1) : (int)$cur['active'],
                ];
                if ($new['description'] === '') {
                    throw new RuntimeException('La descripción de ' . $cur['item_code'] . ' no puede quedar vacía.');
                }
                $sets = [];
                $params = [];
                foreach ($new as $field => $value) {
                    $old = $cur[$field];
                    $same = ($old === null && ($value === null || $value === '')) || (string)$old === (string)$value;
                    if ($same) {
                        continue;
                    }
                    $sets[] = "$field = ?";
                    $params[] = ($value === '' ? null : $value);
                    $this->log((string)$cur['item_code'], $field, $old === null ? null : (string)$old, $value === null ? null : (string)$value, $who);
                    $changed++;
                }
                if ($sets !== []) {
                    $params[] = $who;
                    $params[] = $id;
                    $this->trz->prepare('UPDATE expected_stock_master SET ' . implode(', ', $sets) . ', updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute($params);
                }
            }
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) {
                $this->trz->rollBack();
            }
            throw $e;
        }
        return $changed;
    }

    public function history(int $limit = 100): array
    {
        $this->ensureSchema();
        return $this->all($this->trz, 'SELECT * FROM expected_stock_history ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)));
    }

    public static function fieldLabel(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? $field;
    }
}
