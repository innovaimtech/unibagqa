<?php

declare(strict_types=1);

/**
 * Stock esperado de producto terminado.
 *
 * Maestro editable (TRZ) con los códigos a controlar: bodega (1000 ó 400), unidades por caja, cajas por pallet,
 * stock objetivo y stock mínimo. El informe cruza el maestro con el stock del ERP (solo lectura) y calcula
 * pallets, cajas, saldo y estado contra el objetivo.
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
                    $items[$code] = ['title' => trim((string)$r['item_title'])];
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
        $rows = [];
        foreach ($master as $m) {
            $code = self::normalizeCode((string)$m['item_code']);
            $wcode = (int)($m['warehouse_code'] ?? 0);
            $exists = isset($items[$code]);
            [$inv, $res] = in_array($wcode, self::WAREHOUSES, true) ? $sumFor($stock[$code] ?? [], $wh[$wcode] ?? []) : [0.0, 0.0];
            $otherCode = $wcode === 1000 ? 400 : ($wcode === 400 ? 1000 : 0);
            [$otherInv] = $otherCode > 0 ? $sumFor($stock[$code] ?? [], $wh[$otherCode] ?? []) : [0.0];
            $calc = self::compute($m, $inv, $exists || !$erpOk);
            if (!$erpOk) {
                $calc['status'] = 'Sin conexión ERP';
            }
            $rows[] = [
                'id' => (int)$m['id'], 'code' => (string)$m['item_code'], 'description' => (string)$m['description'],
                'erp_title' => $items[$code]['title'] ?? '', 'warehouse_code' => $wcode > 0 ? $wcode : null,
                'units_per_box' => $m['units_per_box'] !== null ? (int)$m['units_per_box'] : null,
                'boxes_per_pallet' => $m['boxes_per_pallet'] !== null ? (int)$m['boxes_per_pallet'] : null,
                'target_units' => $m['target_units'] !== null ? (int)$m['target_units'] : null,
                'min_units' => $m['min_units'] !== null ? (int)$m['min_units'] : null,
                'stock' => (int)floor($inv + 1e-9), 'reserved' => (int)floor($res + 1e-9),
                'available' => (int)floor($inv - $res + 1e-9), 'other_stock' => (int)floor($otherInv + 1e-9),
                'exists' => $exists,
            ] + $calc;
        }
        return ['rows' => $rows, 'erp_ok' => $erpOk, 'error' => $error, 'generated_at' => date('Y-m-d H:i:s')];
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
