<?php

declare(strict_types=1);

/**
 * Cajas de bolsas con código de trazabilidad y saldo de unidades.
 *
 * Una caja guarda N bolsas de un artículo (SKU) del ERP, de un CC (o sin CC si es compra) y en una bodega del ERP.
 * Su saldo baja cuando se usan bolsas (Pulpo Serigráfico) o se despachan. El stock del ERP se mueve aparte, con
 * StockLedgerService; aquí solo vive el saldo físico de cada caja (tabla TRZ boxes + traceability_warehouse_entries).
 *
 * Estados: IN_WAREHOUSE (disponible en su bodega), IN_MACHINE (en una máquina, con saldo), CONSUMED (sin saldo),
 * DISPATCHED (despachada), VOID (anulada porque se corrigió o borró el embalaje que la creó).
 */
require_once __DIR__ . '/StockLedgerService.php';

final class BagBoxService
{
    public const SOURCE_PACKAGING = 'EMBALAJE';
    public const SOURCE_PURCHASE = 'COMPRA';
    public const SOURCE_LABEL = 'ETIQUETA';
    /** Etiqueta del Ingreso con Trazabilidad (pallet, bulto…) que se escaneó en un traspaso y pasó a manejarse como caja. */
    public const SOURCE_ENTRY = 'INGRESO';
    /** Parte sacada de una caja en un traspaso: código temporal, no se imprime ni se escanea; se consume en el Pulpo. */
    public const SOURCE_PARTIAL = 'SALDO';

    private PDO $trz;
    private PDO $erp;
    private bool $sqlite;
    private static bool $schemaReady = false;

    public function __construct(PDO $trz, PDO $erp)
    {
        $this->trz = $trz;
        $this->erp = $erp;
        $this->sqlite = $trz->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    private function one(PDO $pdo, string $sql, array $params = []): ?array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    private function lock(): string
    {
        return $this->sqlite ? '' : ' FOR UPDATE';
    }

    private function columnExists(string $table, string $column): bool
    {
        if ($this->sqlite) {
            foreach ($this->trz->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $col) {
                if (strcasecmp((string)$col['name'], $column) === 0) {
                    return true;
                }
            }
            return false;
        }
        $stmt = $this->trz->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
        $stmt->execute([$column]);
        return $stmt->fetch() !== false;
    }

    private function indexExists(string $table, string $index): bool
    {
        $stmt = $this->trz->prepare('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?');
        $stmt->execute([$index]);
        return $stmt->fetch() !== false;
    }

    private function tableExists(string $table): bool
    {
        if ($this->sqlite) {
            $stmt = $this->trz->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
            $stmt->execute([$table]);
            return $stmt->fetchColumn() !== false;
        }
        $stmt = $this->trz->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return $stmt->fetchColumn() !== false;
    }

    public function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        if (!$this->tableExists('boxes')) {
            throw new RuntimeException('Falta la tabla de cajas en trazabilidad (boxes). Abre una vez Recepción para crearla.');
        }
        $columns = [
            'units_initial' => 'DECIMAL(12,3) NULL',
            'erp_item_id' => 'INT UNSIGNED NULL',
            'erp_st_id' => 'INT UNSIGNED NULL',
            'erp_order_id' => 'INT UNSIGNED NULL',
            'source_kind' => 'VARCHAR(20) NULL',
            'source_ref' => 'VARCHAR(60) NULL',
            'current_worker_ot_id' => 'BIGINT NULL',
        ];
        foreach ($columns as $column => $definition) {
            if (!$this->columnExists('boxes', $column)) {
                $this->trz->exec('ALTER TABLE boxes ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
        // Ojo: en MySQL toda instrucción de estructura (CREATE INDEX, ALTER, CREATE TABLE) confirma de golpe la transacción
        // abierta, y esta función se llama dentro de transacciones (escaneo, traspaso, ingreso). Antes se ejecutaban
        // siempre y el commit posterior fallaba con «There is no active transaction». Ahora solo si de verdad falta algo.
        if (!$this->sqlite) {
            foreach (['idx_boxes_source' => '(source_kind, source_ref)', 'idx_boxes_erp_stock' => '(erp_item_id, erp_st_id, status)'] as $index => $cols) {
                if (!$this->indexExists('boxes', $index)) {
                    try {
                        $this->trz->exec('CREATE INDEX ' . $index . ' ON boxes ' . $cols);
                    } catch (Throwable) {
                        // creado en paralelo
                    }
                }
            }
            $col = $this->one($this->trz, "SHOW COLUMNS FROM boxes LIKE 'source_roll_id'");
            if ($col !== null && strtoupper((string)($col['Null'] ?? 'YES')) === 'NO') {
                @$this->trz->exec('ALTER TABLE boxes MODIFY source_roll_id BIGINT UNSIGNED NULL DEFAULT NULL');
            }
        }
        // Saldo original de la etiqueta trazable (Despacho también lo agrega; aquí por si aún no corrió).
        if ($this->tableExists('traceability_warehouse_entries') && !$this->columnExists('traceability_warehouse_entries', 'quantity_initial')) {
            $this->trz->exec('ALTER TABLE traceability_warehouse_entries ADD COLUMN quantity_initial DECIMAL(12,3) NULL');
        }
        $engine = $this->sqlite ? '' : ' ENGINE=InnoDB';
        $auto = $this->sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        // Diario de movimientos de unidades de una caja (uso en máquina, devolución, traspaso). Solo se inserta y se marca.
        if (!$this->tableExists('box_unit_moves')) $this->trz->exec('CREATE TABLE IF NOT EXISTS box_unit_moves (
            id ' . $auto . ',
            box_id BIGINT NOT NULL,
            kind VARCHAR(16) NOT NULL,
            qty DECIMAL(12,3) NOT NULL,
            units_before DECIMAL(12,3) NOT NULL,
            units_after DECIMAL(12,3) NOT NULL,
            erp_st_id INT NULL,
            erp_ledger_key VARCHAR(120) NULL,
            worker_ot_id BIGINT NULL,
            agenda_id BIGINT NULL,
            original_move_id BIGINT NULL,
            state VARCHAR(12) NOT NULL,
            operator_name VARCHAR(120) NULL,
            note TEXT NULL,
            error_message TEXT NULL,
            created_at BIGINT NOT NULL,
            done_at BIGINT NULL'
            . ($this->sqlite ? '' : ', INDEX idx_box_moves_box (box_id), INDEX idx_box_moves_ot (worker_ot_id, state), INDEX idx_box_moves_ag (agenda_id, state)')
            . ')' . $engine);
        self::$schemaReady = true;
    }

    /** Código único de caja: BX + 8 caracteres (se revisa contra cajas y etiquetas trazables). */
    public function newCode(): string
    {
        $hasEntries = $this->tableExists('traceability_warehouse_entries');
        do {
            $code = 'BX' . strtoupper(bin2hex(random_bytes(4)));
            $taken = $this->one($this->trz, 'SELECT id FROM boxes WHERE box_code = ?', [$code]) !== null
                || ($hasEntries && $this->one($this->trz, 'SELECT id FROM traceability_warehouse_entries WHERE traceability_code = ?', [$code]) !== null);
        } while ($taken);
        return $code;
    }

    /** Bodega interna (warehouses.id) que corresponde a una bodega del ERP, o null si no está registrada. */
    public function localWarehouseId(int $erpStId): ?int
    {
        if ($erpStId <= 0 || !$this->tableExists('warehouses') || !$this->columnExists('warehouses', 'erp_storehouse_id')) {
            return null;
        }
        $row = $this->one($this->trz, 'SELECT id FROM warehouses WHERE erp_storehouse_id = ? ORDER BY id LIMIT 1', [$erpStId]);
        return $row === null ? null : (int)$row['id'];
    }

    /**
     * Crea una caja con su código y su etiqueta trazable.
     *
     * @param array{item_id:int,st_id:int,order_id?:int,units:float,sku:string,order_num?:string,source_kind:string,source_ref?:string,operator?:string,description?:string} $d
     */
    public function create(array $d): array
    {
        $this->ensureSchema();
        $units = round((float)($d['units'] ?? 0), 3);
        if ((int)($d['item_id'] ?? 0) <= 0 || (int)($d['st_id'] ?? 0) <= 0 || $units <= 0) {
            throw new InvalidArgumentException('Caja incompleta: artículo, bodega y unidades son obligatorios.');
        }
        $code = $this->newCode();
        $localWh = $this->localWarehouseId((int)$d['st_id']);
        $operator = mb_substr(trim((string)($d['operator'] ?? '')) ?: 'Sistema', 0, 120);
        $sku = mb_substr(trim((string)($d['sku'] ?? '')) ?: ('ITEM-' . (int)$d['item_id']), 0, 80);
        $orderNum = trim((string)($d['order_num'] ?? ''));
        $this->trz->prepare('INSERT INTO boxes (box_code, work_order_id, source_roll_id, pallet_id, final_sku, units_qty, units_initial,
                destination_mode, customer_order_ref, warehouse_id, operator_name, status, erp_item_id, erp_st_id, erp_order_id, source_kind, source_ref)
                VALUES (?, NULL, NULL, NULL, ?, ?, ?, \'BODEGA\', ?, ?, ?, \'IN_WAREHOUSE\', ?, ?, ?, ?, ?)')
            ->execute([$code, $sku, $units, $units, $orderNum !== '' ? $orderNum : null, $localWh, $operator,
                (int)$d['item_id'], (int)$d['st_id'], (int)($d['order_id'] ?? 0) ?: null, (string)$d['source_kind'],
                mb_substr((string)($d['source_ref'] ?? ''), 0, 60) ?: null]);
        $id = (int)$this->trz->lastInsertId();
        if ($this->tableExists('traceability_warehouse_entries')) {
            $this->trz->prepare("INSERT INTO traceability_warehouse_entries (traceability_code, entity_type, entity_id, warehouse_id, warehouse_name,
                    item_id, item_code, item_description, quantity, quantity_initial, unit_name, box_count, order_ref, operator_name, comments, status)
                    VALUES (?, 'BOX', ?, ?, ?, ?, ?, ?, ?, ?, 'Unid.', 1, ?, ?, ?, 'IN_WAREHOUSE')")
                // warehouse_id de los códigos = id de bodega del ERP (igual que RollStockService); nunca el id local.
                ->execute([$code, $id, (int)$d['st_id'], $this->erpStoreName((int)$d['st_id']), (int)$d['item_id'], $sku,
                    mb_substr((string)($d['description'] ?? ''), 0, 250), $units, $units, $orderNum !== '' ? $orderNum : null, $operator,
                    'Caja de bolsas (' . (string)$d['source_kind'] . ')']);
        }
        $this->movement($id, 'RECEIPT', null, $localWh, ['box_code' => $code, 'units' => $units, 'source' => $d['source_kind'], 'ref' => $d['source_ref'] ?? '']);
        return $this->byId($id);
    }

    public function byId(int $id, bool $forUpdate = false): ?array
    {
        $this->ensureSchema();
        return $this->one($this->trz, 'SELECT * FROM boxes WHERE id = ?' . ($forUpdate ? $this->lock() : ''), [$id]);
    }

    /** Busca una caja por su código (o por el código de su etiqueta trazable). */
    public function find(string $code, bool $forUpdate = false): ?array
    {
        $this->ensureSchema();
        $code = strtoupper(preg_replace('/\s+/', '', trim($code)) ?? '');
        if ($code === '') {
            return null;
        }
        $box = $this->one($this->trz, 'SELECT * FROM boxes WHERE UPPER(box_code) = ?' . ($forUpdate ? $this->lock() : ''), [$code]);
        if ($box !== null || !$this->tableExists('traceability_warehouse_entries')) {
            return $box;
        }
        $entry = $this->one($this->trz, "SELECT entity_id FROM traceability_warehouse_entries
            WHERE entity_type = 'BOX' AND entity_id IS NOT NULL AND UPPER(traceability_code) = ? ORDER BY id DESC LIMIT 1", [$code]);
        return $entry === null ? null : $this->byId((int)$entry['entity_id'], $forUpdate);
    }

    /**
     * Etiqueta del Ingreso con Trazabilidad (de cualquier tipo: caja, pallet, bulto) de ese artículo y bodega del ERP,
     * convertida en caja para traspasarla y usarla en el Pulpo. Si ya es caja, devuelve esa caja. null si el código no es
     * una etiqueta de ese artículo con saldo en esa bodega.
     */
    public function adoptEntry(string $code, int $itemId, int $stId, string $operator = ''): ?array
    {
        $this->ensureSchema();
        $code = strtoupper(preg_replace('/\s+/', '', trim($code)) ?? '');
        if ($code === '' || !$this->tableExists('traceability_warehouse_entries')) {
            return null;
        }
        $box = $this->find($code, true);
        if ($box !== null) {
            return $box;
        }
        $entry = $this->one($this->trz, 'SELECT * FROM traceability_warehouse_entries WHERE UPPER(traceability_code) = ? ORDER BY id DESC LIMIT 1', [$code]);
        if ($entry === null) {
            return null;
        }
        $qty = round((float)($entry['quantity'] ?? 0), 3);
        $status = strtoupper((string)($entry['status'] ?? ''));
        if ((int)($entry['item_id'] ?? 0) !== $itemId) {
            throw new RuntimeException('La etiqueta ' . $code . ' es de ' . ($entry['item_code'] ?? '¿?') . ', no del producto pedido.');
        }
        if ((int)($entry['warehouse_id'] ?? 0) !== $stId) {
            throw new RuntimeException('La etiqueta ' . $code . ' no está en la bodega de origen (está en ' . ($entry['warehouse_name'] ?? '¿?') . ').');
        }
        if ($qty <= 0 || in_array($status, ['CONSUMED', 'VOID', 'DISPATCHED', 'CANCELLED'], true)) {
            throw new RuntimeException('La etiqueta ' . $code . ' no tiene saldo disponible.');
        }
        // CC de la etiqueta solo si el ERP tiene stock de ese artículo con esa CC en la bodega (si no, stock general).
        $orderId = 0;
        $orderRef = trim((string)($entry['order_ref'] ?? ''));
        if ($orderRef !== '') {
            $order = $this->one($this->erp, 'SELECT id FROM orders WHERE req_number = ? LIMIT 1', [$orderRef]);
            if ($order !== null && $this->one($this->erp, 'SELECT item_id FROM item_shops_storehouses WHERE item_id = ? AND st_id = ? AND iss_order_id = ? AND iss_inventory > 0 LIMIT 1',
                    [$itemId, $stId, (int)$order['id']]) !== null) {
                $orderId = (int)$order['id'];
            }
        }
        $this->trz->prepare('INSERT INTO boxes (box_code, work_order_id, source_roll_id, pallet_id, final_sku, units_qty, units_initial,
                destination_mode, customer_order_ref, warehouse_id, operator_name, status, erp_item_id, erp_st_id, erp_order_id, source_kind, source_ref)
                VALUES (?, NULL, NULL, NULL, ?, ?, ?, \'BODEGA\', ?, ?, ?, \'IN_WAREHOUSE\', ?, ?, ?, ?, ?)')
            ->execute([$code, mb_substr((string)$entry['item_code'], 0, 80), $qty, round((float)($entry['quantity_initial'] ?? $qty) ?: $qty, 3),
                $orderRef !== '' ? $orderRef : null, $this->localWarehouseId($stId), mb_substr($operator ?: 'Bodega', 0, 120),
                $itemId, $stId, $orderId ?: null, self::SOURCE_ENTRY, 'entry:' . (int)$entry['id']]);
        $id = (int)$this->trz->lastInsertId();
        $this->movement($id, 'ADJUSTMENT', null, null, ['adopted_entry' => (int)$entry['id'], 'code' => $code, 'units' => $qty, 'entity_type' => $entry['entity_type'] ?? '']);
        return $this->byId($id, true);
    }

    /**
     * Parte sacada de una caja en un traspaso: queda en la Bodega 3000 con un código temporal (no se imprime ni se escanea).
     * Idempotente por $ref: si ya existe, devuelve la misma.
     */
    public function createPartial(array $parent, float $units, string $ref, string $operator): array
    {
        $this->ensureSchema();
        $existing = $this->one($this->trz, 'SELECT * FROM boxes WHERE source_kind = ? AND source_ref = ? LIMIT 1', [self::SOURCE_PARTIAL, $ref]);
        if ($existing !== null) {
            return $existing;
        }
        $box = $this->create(['item_id' => (int)$parent['erp_item_id'], 'st_id' => 16, 'order_id' => (int)($parent['erp_order_id'] ?? 0),
            'units' => $units, 'sku' => (string)$parent['final_sku'], 'order_num' => (string)($parent['customer_order_ref'] ?? ''),
            'source_kind' => self::SOURCE_PARTIAL, 'source_ref' => $ref, 'operator' => $operator,
            'description' => 'Parte de la caja ' . $parent['box_code'] . ' (código temporal, no se escanea)']);
        if ($this->tableExists('traceability_warehouse_entries')) {
            $this->trz->prepare("UPDATE traceability_warehouse_entries SET comments = ? WHERE traceability_code = ?")
                ->execute(['Código temporal: ' . StockLedgerService::fmt($units) . ' bolsas sacadas de la caja ' . $parent['box_code'] . ' para el Pulpo Serigráfico', $box['box_code']]);
        }
        return $box;
    }

    /** Una caja es de bolsas con stock en el ERP si sabe su artículo y su bodega del ERP. */
    public static function isErpLinked(array $box): bool
    {
        return (int)($box['erp_item_id'] ?? 0) > 0 && (int)($box['erp_st_id'] ?? 0) > 0;
    }

    /** Actualiza saldo, estado, bodega y máquina de la caja, y su etiqueta trazable. */
    public function update(int $boxId, array $set): void
    {
        $allowed = ['units_qty', 'status', 'erp_st_id', 'warehouse_id', 'current_worker_ot_id'];
        $fields = array_intersect_key($set, array_flip($allowed));
        if ($fields === []) {
            return;
        }
        $sql = 'UPDATE boxes SET ' . implode(', ', array_map(static fn($f) => $f . ' = ?', array_keys($fields))) . ' WHERE id = ?';
        $this->trz->prepare($sql)->execute([...array_values($fields), $boxId]);
        if ($this->tableExists('traceability_warehouse_entries')) {
            $entry = [];
            if (array_key_exists('units_qty', $fields)) {
                $entry['quantity'] = $fields['units_qty'];
            }
            if (array_key_exists('status', $fields)) {
                $entry['status'] = in_array($fields['status'], ['IN_WAREHOUSE', 'IN_MACHINE'], true) ? 'IN_WAREHOUSE' : $fields['status'];
            }
            if (array_key_exists('erp_st_id', $fields)) {
                $entry['warehouse_id'] = (int)$fields['erp_st_id'];
                $entry['warehouse_name'] = $this->erpStoreName((int)$fields['erp_st_id']);
            }
            if ($entry !== []) {
                // La etiqueta de la caja: la creada con la caja (BOX + id) o la del Ingreso con Trazabilidad con el mismo código.
                $code = (string)($this->one($this->trz, 'SELECT box_code FROM boxes WHERE id = ?', [$boxId])['box_code'] ?? '');
                $this->trz->prepare('UPDATE traceability_warehouse_entries SET ' . implode(', ', array_map(static fn($f) => $f . ' = ?', array_keys($entry)))
                    . " WHERE (entity_type = 'BOX' AND entity_id = ?) OR traceability_code = ?")->execute([...array_values($entry), $boxId, $code]);
            }
        }
    }

    public function movement(int $boxId, string $type, ?int $fromWh, ?int $toWh, array $payload): void
    {
        if (!$this->tableExists('movements')) {
            return;
        }
        $this->trz->prepare('INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload) VALUES (\'BOX\', ?, ?, ?, ?, ?)')
            ->execute([$boxId, $type, $fromWh, $toWh, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    }

    /** Cajas creadas por un embalaje. */
    public function packagingBoxes(int $eventId): array
    {
        $this->ensureSchema();
        $stmt = $this->trz->prepare("SELECT * FROM boxes WHERE source_kind = ? AND source_ref = ? AND status != 'VOID' ORDER BY id");
        $stmt->execute([self::SOURCE_PACKAGING, 'evt:' . $eventId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Una caja ya se usó si tiene movimientos de unidades, se movió de bodega, se despachó o perdió saldo. */
    public function isUsed(array $box): bool
    {
        if ((string)$box['status'] !== 'IN_WAREHOUSE' || abs((float)$box['units_qty'] - (float)($box['units_initial'] ?? $box['units_qty'])) > 0.0005) {
            return true;
        }
        return $this->one($this->trz, "SELECT id FROM box_unit_moves WHERE box_id = ? AND state != 'CANCELLED' LIMIT 1", [(int)$box['id']]) !== null;
    }

    /**
     * Cajas que debe tener un embalaje según su detalle guardado: una por cada caja completa y otra por la caja final.
     * En un cierre mixto, cada CC del pallet tiene sus propias cajas. Las sobrantes y el showroom no van en caja.
     *
     * @return list<array{order_id:int,item_id:int,units:int}>
     */
    public function expectedPackagingBoxes(int $eventId): array
    {
        $detail = $this->one($this->erp, 'SELECT * FROM prod_worker_embalajes WHERE req_v2refid = ? ORDER BY id DESC LIMIT 1', [$eventId]);
        if ($detail === null) {
            return [];
        }
        $rows = [];
        if ((string)($detail['req_codigo_cierre'] ?? '') === '50') {
            $mixed = $this->one($this->erp, 'SELECT id FROM embalaje_mixto WHERE id_embalaje = ? LIMIT 1', [(int)$detail['id']]);
            if ($mixed !== null) {
                $stmt = $this->erp->prepare('SELECT req_number, id_item, total_cajas, bolsas_por_caja, bolsas_caja_final FROM detalle_mixto WHERE id_mixto = ? ORDER BY id');
                $stmt->execute([(int)$mixed['id']]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
                    $order = $this->one($this->erp, 'SELECT id FROM orders WHERE req_number = ? LIMIT 1', [trim((string)$m['req_number'])]);
                    $orderId = (int)($order['id'] ?? 0);
                    $itemId = (int)$m['id_item'];
                    if ($itemId <= 0 && $orderId > 0) {
                        $itemId = (int)($this->one($this->erp, 'SELECT item_id FROM orders_items WHERE req_id = ? LIMIT 1', [$orderId])['item_id'] ?? 0);
                    }
                    $rows[] = [$orderId, $itemId, (int)$m['total_cajas'], (int)$m['bolsas_por_caja'], (int)$m['bolsas_caja_final']];
                }
            }
        }
        if ($rows === []) {
            $orderId = (int)$detail['evt_reqid'];
            $itemId = (int)($this->one($this->erp, 'SELECT item_id FROM orders_items WHERE req_id = ? LIMIT 1', [$orderId])['item_id'] ?? 0);
            $rows[] = [$orderId, $itemId, (int)$detail['req_embalaje_cajas_completas_amt'], (int)$detail['req_embalaje_bolsas_por_caja_amt'], (int)$detail['req_embalaje_caja_final']];
        }
        $boxes = [];
        foreach ($rows as [$orderId, $itemId, $full, $perBox, $final]) {
            if ($orderId <= 0 || $itemId <= 0) {
                continue;
            }
            for ($i = 0; $i < $full && $perBox > 0; $i++) {
                $boxes[] = ['order_id' => $orderId, 'item_id' => $itemId, 'units' => $perBox];
            }
            if ($final > 0) {
                $boxes[] = ['order_id' => $orderId, 'item_id' => $itemId, 'units' => $final];
            }
        }
        return $boxes;
    }

    /**
     * Deja las cajas de un embalaje iguales a su detalle. Si el detalle no cambió, no hace nada. Si cambió y ninguna caja
     * se usó, anula las anteriores y crea las nuevas. Si alguna ya se usó, no toca nada y lo informa.
     *
     * @return array{created:int[],warning:string}
     */
    public function syncPackagingBoxes(int $eventId, int $warehouse, string $operator): array
    {
        $this->ensureSchema();
        $expected = $this->expectedPackagingBoxes($eventId);
        $current = $this->packagingBoxes($eventId);
        $sig = static fn(array $list): array => array_map(static fn($b) => ($b['order_id'] ?? $b['erp_order_id']) . ':' . ($b['item_id'] ?? $b['erp_item_id'])
            . ':' . (int)round((float)($b['units'] ?? $b['units_initial'] ?? $b['units_qty'])) . ':' . ($b['st'] ?? $b['erp_st_id']), $list);
        $want = $sig(array_map(static fn($b) => $b + ['st' => $warehouse], $expected));
        $have = $sig($current);
        sort($want);
        sort($have);
        if ($want === $have) {
            return ['created' => [], 'warning' => ''];
        }
        foreach ($current as $box) {
            if ($this->isUsed($box)) {
                return ['created' => [], 'warning' => 'Las cajas de este embalaje ya se usaron o movieron (caja ' . $box['box_code']
                    . '): no se cambiaron sus códigos. Avisa a bodega para corregir las etiquetas.'];
            }
        }
        $own = !$this->trz->inTransaction();
        if ($own) {
            $this->trz->beginTransaction();
        }
        try {
            foreach ($current as $box) {
                $this->update((int)$box['id'], ['status' => 'VOID', 'units_qty' => 0]);
                $this->movement((int)$box['id'], 'ADJUSTMENT', $box['warehouse_id'] !== null ? (int)$box['warehouse_id'] : null, null, ['void' => 'embalaje corregido', 'event_id' => $eventId]);
            }
            $created = [];
            foreach ($expected as $b) {
                $item = $this->one($this->erp, 'SELECT item_number_prod, item_title FROM item WHERE id = ?', [$b['item_id']]) ?? [];
                $order = $this->one($this->erp, 'SELECT req_number FROM orders WHERE id = ?', [$b['order_id']]) ?? [];
                $created[] = (int)$this->create(['item_id' => $b['item_id'], 'st_id' => $warehouse, 'order_id' => $b['order_id'], 'units' => $b['units'],
                    'sku' => (string)($item['item_number_prod'] ?? ''), 'description' => (string)($item['item_title'] ?? ''),
                    'order_num' => (string)($order['req_number'] ?? ''), 'source_kind' => self::SOURCE_PACKAGING, 'source_ref' => 'evt:' . $eventId,
                    'operator' => $operator])['id'];
            }
            if ($own) {
                $this->trz->commit();
            }
            return ['created' => $created, 'warning' => ''];
        } catch (Throwable $e) {
            if ($own && $this->trz->inTransaction()) {
                $this->trz->rollBack();
            }
            throw $e;
        }
    }

    /** Antes de borrar o anular un embalaje: anula sus cajas. Si alguna ya se usó, lo impide. */
    public function voidPackagingBoxes(int $eventId): void
    {
        $this->ensureSchema();
        $boxes = $this->packagingBoxes($eventId);
        foreach ($boxes as $box) {
            if ($this->isUsed($box)) {
                throw new RuntimeException('No se puede eliminar este embalaje: la caja ' . $box['box_code'] . ' ya se usó, movió o despachó.');
            }
        }
        foreach ($boxes as $box) {
            $this->update((int)$box['id'], ['status' => 'VOID', 'units_qty' => 0]);
            $this->movement((int)$box['id'], 'ADJUSTMENT', null, null, ['void' => 'embalaje eliminado', 'event_id' => $eventId]);
        }
    }

    /**
     * Cajas disponibles para pedir a producción, agrupadas por artículo y bodega del ERP (sin la bodega 3000).
     *
     * @return list<array{item_id:int,st_id:int,boxes:int,units:float}>
     */
    public function availableGroups(): array
    {
        $this->ensureSchema();
        return array_map(static fn($r) => ['item_id' => (int)$r['item_id'], 'st_id' => (int)$r['st_id'], 'boxes' => (int)$r['boxes'], 'units' => (float)$r['units']],
            $this->trz->query("SELECT erp_item_id AS item_id, erp_st_id AS st_id, COUNT(*) AS boxes, SUM(units_qty) AS units FROM boxes
                WHERE status = 'IN_WAREHOUSE' AND erp_item_id > 0 AND erp_st_id > 0 AND erp_st_id != 16 AND units_qty > 0
                GROUP BY erp_item_id, erp_st_id ORDER BY erp_item_id, erp_st_id")->fetchAll(PDO::FETCH_ASSOC));
    }

    public function availableUnits(int $itemId, int $stId): float
    {
        $this->ensureSchema();
        $row = $this->one($this->trz, "SELECT COALESCE(SUM(units_qty),0) AS u FROM boxes WHERE status = 'IN_WAREHOUSE' AND erp_item_id = ? AND erp_st_id = ? AND units_qty > 0", [$itemId, $stId]);
        return (float)($row['u'] ?? 0);
    }

    private function erpStoreName(int $stId): string
    {
        try {
            return (string)($this->one($this->erp, 'SELECT st_name FROM company_shops_storehouses WHERE id = ?', [$stId])['st_name'] ?? ('Bodega ' . $stId));
        } catch (Throwable) {
            return 'Bodega ' . $stId;
        }
    }
}
