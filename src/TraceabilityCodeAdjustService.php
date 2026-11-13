<?php

declare(strict_types=1);

/**
 * Ajuste de códigos de trazabilidad por bodega (cajas, pallets y bultos con etiqueta).
 *
 *  - Juntar: pasa unidades de un código a otro del mismo artículo, bodega y CC (dos cajas con saldo → una).
 *  - Borrar: elimina códigos impresos de más que nunca se usaron (queda el registro en el historial).
 *
 * Solo cambia los saldos de las etiquetas en trazabilidad (traceability_warehouse_entries y boxes). El stock del ERP no
 * se toca: el Ingreso con Trazabilidad solo etiqueta stock que ya existe, y juntar dos cajas no cambia el total.
 * Las bobinas (ROLL) y los códigos temporales del Pulpo (SALDO) no se ajustan aquí.
 */
require_once __DIR__ . '/BagBoxService.php';

final class TraceabilityCodeAdjustService
{
    public const DELETE_REASONS = [
        'IMPRESA_DE_MAS' => 'Etiqueta impresa de más',
        'DUPLICADA' => 'Etiqueta duplicada (mismo bulto con dos códigos)',
        'ERROR_INGRESO' => 'Error en el ingreso',
        'OTRO' => 'Otro motivo',
    ];

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

    /** Crea la tabla del historial si falta. Se llama FUERA de transacciones (en MySQL un CREATE confirma la transacción). */
    public function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        (new BagBoxService($this->trz, $this->erp))->ensureSchema();
        if (!$this->tableExists('traceability_code_adjustments')) {
            $auto = $this->sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
            $this->trz->exec('CREATE TABLE IF NOT EXISTS traceability_code_adjustments (
                id ' . $auto . ',
                action VARCHAR(12) NOT NULL,
                from_code VARCHAR(60) NOT NULL,
                to_code VARCHAR(60) NULL,
                item_id INT NULL,
                item_code VARCHAR(80) NULL,
                warehouse_id INT NULL,
                warehouse_name VARCHAR(100) NULL,
                order_ref VARCHAR(80) NULL,
                qty DECIMAL(12,3) NOT NULL,
                from_before DECIMAL(12,3) NOT NULL,
                from_after DECIMAL(12,3) NOT NULL,
                to_before DECIMAL(12,3) NULL,
                to_after DECIMAL(12,3) NULL,
                reason VARCHAR(40) NULL,
                note VARCHAR(255) NULL,
                operator_name VARCHAR(120) NOT NULL,
                created_at BIGINT NOT NULL'
                . ($this->sqlite ? '' : ', INDEX idx_tca_created (created_at), INDEX idx_tca_from (from_code), INDEX idx_tca_to (to_code)')
                . ')' . ($this->sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'));
        }
        self::$schemaReady = true;
    }

    public static function normalizeCode(string $code): string
    {
        // La pistola con teclado en español escribe «'» en vez de «-».
        return strtoupper(str_replace("'", '-', preg_replace('/\s+/', '', trim($code)) ?? ''));
    }

    /**
     * Código con sus datos y si se puede ajustar. null si no existe.
     *
     * @return array<string,mixed>|null
     */
    public function find(string $code, bool $forUpdate = false): ?array
    {
        $this->ensureSchema();
        $code = self::normalizeCode($code);
        if ($code === '') {
            return null;
        }
        $lock = $forUpdate && !$this->sqlite ? ' FOR UPDATE' : '';
        $entry = $this->one('SELECT * FROM traceability_warehouse_entries WHERE UPPER(traceability_code) = ? ORDER BY id DESC LIMIT 1' . $lock, [$code]);
        if ($entry === null) {
            return null;
        }
        $box = null;
        if (strtoupper((string)$entry['entity_type']) === 'BOX' && (int)($entry['entity_id'] ?? 0) > 0) {
            $box = $this->one('SELECT * FROM boxes WHERE id = ?' . $lock, [(int)$entry['entity_id']]);
        }
        $box ??= $this->one('SELECT * FROM boxes WHERE UPPER(box_code) = ?' . $lock, [$code]);
        return $this->describe($entry, $box);
    }

    /** @return array<string,mixed> */
    private function describe(array $entry, ?array $box): array
    {
        $type = strtoupper((string)$entry['entity_type']);
        $status = strtoupper((string)($entry['status'] ?? ''));
        $boxStatus = $box !== null ? strtoupper((string)$box['status']) : null;
        $qty = round((float)($box['units_qty'] ?? $entry['quantity'] ?? 0), 3);
        $blocked = '';
        if ($type === 'ROLL') {
            $blocked = 'Es una bobina: sus kilos se ajustan en Recepción, no aquí.';
        } elseif ($box !== null && strtoupper((string)($box['source_kind'] ?? '')) === BagBoxService::SOURCE_PARTIAL) {
            $blocked = 'Es un código temporal del Pulpo Serigráfico (no tiene etiqueta).';
        } elseif ($status === 'VOID' || $boxStatus === 'VOID') {
            $blocked = 'El código está anulado.';
        } elseif ($boxStatus === 'IN_MACHINE') {
            $blocked = 'La caja está cargada en una máquina: primero devuélvela a bodega.';
        } elseif (in_array($status, ['DISPATCHED', 'CANCELLED'], true) || $boxStatus === 'DISPATCHED') {
            $blocked = 'El código ya fue despachado.';
        } elseif ($status === 'CONSUMED') {
            $blocked = 'El código ya no tiene saldo (consumido o juntado en otro código).';
        } elseif ($status !== 'IN_WAREHOUSE') {
            $blocked = 'El código no está en bodega (estado ' . $status . ').';
        }
        return [
            'entry_id' => (int)$entry['id'],
            'box_id' => $box !== null ? (int)$box['id'] : null,
            'entity_id' => (int)($entry['entity_id'] ?? 0),
            'code' => (string)$entry['traceability_code'],
            'type' => $type,
            'item_id' => (int)($entry['item_id'] ?? $box['erp_item_id'] ?? 0),
            'item_code' => (string)($entry['item_code'] ?? $box['final_sku'] ?? ''),
            'description' => (string)($entry['item_description'] ?? ''),
            'warehouse_id' => (int)($entry['warehouse_id'] ?? 0),
            'warehouse_name' => (string)($entry['warehouse_name'] ?? ''),
            'order_ref' => trim((string)($entry['order_ref'] ?? $box['customer_order_ref'] ?? '')),
            'erp_order_id' => (int)($box['erp_order_id'] ?? 0),
            'qty' => $qty,
            'qty_initial' => round((float)($entry['quantity_initial'] ?? $box['units_initial'] ?? $qty), 3),
            'unit' => (string)($entry['unit_name'] ?? 'Unid.'),
            'status' => $boxStatus === 'IN_MACHINE' ? 'IN_MACHINE' : $status,
            'source' => (string)($box['source_kind'] ?? ''),
            'created_at' => (string)($entry['created_at'] ?? ''),
            'comments' => (string)($entry['comments'] ?? ''),
            'blocked' => $blocked,
        ];
    }

    /** Motivo por el que dos códigos no se pueden juntar ('' si se puede). */
    public static function mergeProblem(array $from, array $to): string
    {
        if (strtoupper((string)$from['code']) === strtoupper((string)$to['code'])) {
            return 'Escaneaste dos veces el mismo código.';
        }
        foreach ([$from, $to] as $c) {
            if ($c['blocked'] !== '') {
                return $c['code'] . ': ' . $c['blocked'];
            }
        }
        if ($from['item_id'] !== $to['item_id']) {
            return 'Los códigos son de artículos distintos (' . $from['item_code'] . ' y ' . $to['item_code'] . ').';
        }
        if ($from['warehouse_id'] !== $to['warehouse_id']) {
            return 'Los códigos están en bodegas distintas (' . $from['warehouse_name'] . ' y ' . $to['warehouse_name'] . ').';
        }
        if (!self::sameOrder($from, $to)) {
            return 'Los códigos son de CC distintos (' . ($from['order_ref'] ?: 'sin CC') . ' y ' . ($to['order_ref'] ?: 'sin CC') . ').';
        }
        if ($from['qty'] <= 0) {
            return 'El código ' . $from['code'] . ' no tiene saldo para pasar.';
        }
        return '';
    }

    private static function sameOrder(array $a, array $b): bool
    {
        return strtoupper($a['order_ref']) === strtoupper($b['order_ref']);
    }

    /**
     * Pasa $qty unidades (todo el saldo si es null) de un código a otro.
     *
     * @return array{from:array<string,mixed>,to:array<string,mixed>,qty:float}
     */
    public function merge(string $fromCode, string $toCode, ?float $qty, string $operator, string $note = ''): array
    {
        $this->ensureSchema();
        $fromCode = self::normalizeCode($fromCode);
        $toCode = self::normalizeCode($toCode);
        if ($fromCode === '' || $toCode === '') {
            throw new InvalidArgumentException('Indica el código de origen y el código donde se juntan las unidades.');
        }
        if ($fromCode === $toCode) {
            throw new InvalidArgumentException('El código de origen y el de destino son el mismo.');
        }
        $this->trz->beginTransaction();
        try {
            // Se bloquean en orden alfabético para que dos ajustes cruzados no se esperen entre sí.
            $locked = [];
            foreach ([$fromCode, $toCode] as $c) {
                $locked[$c] = null;
            }
            $order = array_keys($locked);
            sort($order);
            foreach ($order as $c) {
                $locked[$c] = $this->find($c, true);
            }
            $from = $locked[$fromCode];
            $to = $locked[$toCode];
            if ($from === null) {
                throw new RuntimeException('No existe el código ' . $fromCode . '.');
            }
            if ($to === null) {
                throw new RuntimeException('No existe el código ' . $toCode . '.');
            }
            $problem = self::mergeProblem($from, $to);
            if ($problem !== '') {
                throw new RuntimeException($problem);
            }
            $move = $qty === null ? $from['qty'] : round($qty, 3);
            if ($move <= 0) {
                throw new InvalidArgumentException('La cantidad a pasar debe ser mayor que 0.');
            }
            if ($move > $from['qty'] + 0.0005) {
                throw new RuntimeException('El código ' . $from['code'] . ' tiene ' . self::fmt($from['qty']) . ' unidades: no se pueden pasar ' . self::fmt($move) . '.');
            }
            $fromAfter = round($from['qty'] - $move, 3);
            $toAfter = round($to['qty'] + $move, 3);
            $now = time();
            $label = $operator !== '' ? $operator : 'Bodega';

            // Origen: baja su saldo; si queda en 0, sale de la bodega como consumido (juntado en otro código).
            $this->setCode($from, $fromAfter, $fromAfter > 0 ? 'IN_WAREHOUSE' : 'CONSUMED', null,
                'Saldo juntado en ' . $to['code'] . ' (' . self::fmt($move) . ' unid.) · ' . date('d-m-Y H:i', $now) . ' · ' . $label);
            // Destino: suma el saldo y su etiqueta pasa a mostrar el total del bulto.
            $this->setCode($to, $toAfter, 'IN_WAREHOUSE', $toAfter,
                'Recibió ' . self::fmt($move) . ' unid. de ' . $from['code'] . ' · ' . date('d-m-Y H:i', $now) . ' · ' . $label);
            $this->boxMove($from, 'MERGE_OUT', $move, $from['qty'], $fromAfter, $label, 'Juntado en ' . $to['code'], $now);
            $this->boxMove($to, 'MERGE_IN', $move, $to['qty'], $toAfter, $label, 'Desde ' . $from['code'], $now);
            $this->log('MERGE', $from, $to, $move, $fromAfter, $toAfter, null, $note, $label, $now);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) {
                $this->trz->rollBack();
            }
            throw $e;
        }
        return ['from' => $this->find($fromCode) ?? $from, 'to' => $this->find($toCode) ?? $to, 'qty' => $move];
    }

    /**
     * Borra varios códigos impresos de más (etiqueta y caja). Todo o nada: si uno no se puede borrar, no se borra
     * ninguno. Solo códigos sin uso (sin movimientos y con el saldo con que se imprimieron). Queda en el historial.
     *
     * @param list<string> $codes
     * @return list<array<string,mixed>> los códigos borrados (como estaban antes de borrarlos)
     */
    public function deleteMany(array $codes, string $reason, string $operator, string $note = ''): array
    {
        $this->ensureSchema();
        $list = [];
        foreach ($codes as $code) {
            $code = self::normalizeCode((string)$code);
            if ($code !== '') {
                $list[$code] = true;
            }
        }
        $list = array_keys($list);
        if ($list === []) {
            throw new InvalidArgumentException('No hay códigos para borrar.');
        }
        if (count($list) > 300) {
            throw new InvalidArgumentException('Máximo 300 códigos por vez.');
        }
        if (!isset(self::DELETE_REASONS[$reason])) {
            throw new InvalidArgumentException('Elige el motivo.');
        }
        $note = trim($note);
        if ($reason === 'OTRO' && $note === '') {
            throw new InvalidArgumentException('Escribe el motivo.');
        }
        sort($list);
        $this->trz->beginTransaction();
        try {
            $found = [];
            $errors = [];
            foreach ($list as $code) {
                $c = $this->find($code, true);
                $problem = $c === null ? 'no existe' : $this->deleteProblem($c);
                if ($problem !== '') {
                    $errors[] = ($c['code'] ?? $code) . ': ' . $problem;
                } else {
                    $found[] = $c;
                }
            }
            if ($errors !== []) {
                throw new RuntimeException('No se borró ningún código. ' . implode(' · ', $errors));
            }
            $now = time();
            $label = $operator !== '' ? $operator : 'Bodega';
            foreach ($found as $c) {
                $this->log('DELETE', $c, null, $c['qty'], 0.0, null, $reason, $note, $label, $now);
                $this->trz->prepare('DELETE FROM traceability_warehouse_entries WHERE id = ?')->execute([$c['entry_id']]);
                if ($c['box_id'] !== null) {
                    $this->trz->prepare('DELETE FROM boxes WHERE id = ?')->execute([$c['box_id']]);
                    if ($this->tableExists('movements')) {
                        $this->trz->prepare("DELETE FROM movements WHERE entity_type = 'BOX' AND entity_id = ?")->execute([$c['box_id']]);
                    }
                } elseif ($c['type'] === 'PALLET' && $c['entity_id'] > 0 && $this->tableExists('pallets')) {
                    $this->trz->prepare('DELETE FROM pallets WHERE id = ?')->execute([$c['entity_id']]);
                }
            }
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) {
                $this->trz->rollBack();
            }
            throw $e;
        }
        return $found;
    }

    /** Por qué un código no se puede borrar ('' si se puede): bloqueado, usado o con el saldo cambiado. */
    public function deleteProblem(array $c): string
    {
        if ($c['blocked'] !== '') {
            return $c['blocked'];
        }
        if (abs((float)$c['qty'] - (float)$c['qty_initial']) > 0.0005) {
            return 'su saldo ya cambió (' . self::fmt((float)$c['qty']) . ' de ' . self::fmt((float)$c['qty_initial']) . '): ya se usó.';
        }
        if ($c['box_id'] !== null) {
            $used = $this->one("SELECT id FROM box_unit_moves WHERE box_id = ? AND state != 'CANCELLED' LIMIT 1", [$c['box_id']]);
            if ($used !== null) {
                return 'la caja ya tiene movimientos (uso, traspaso o ajuste).';
            }
        }
        return '';
    }

    /**
     * Últimos ajustes (los más nuevos primero).
     *
     * @return list<array<string,mixed>>
     */
    public function history(int $limit = 40, string $code = '', string $action = ''): array
    {
        $this->ensureSchema();
        $code = self::normalizeCode($code);
        $where = [];
        $params = [];
        if ($code !== '') {
            $where[] = '(from_code = ? OR to_code = ?)';
            array_push($params, $code, $code);
        }
        if ($action !== '') {
            $where[] = 'action = ?';
            $params[] = $action;
        }
        $stmt = $this->trz->prepare('SELECT * FROM traceability_code_adjustments' . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)));
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Deja saldo y estado del código en la etiqueta y en su caja, y agrega una nota al comentario. */
    private function setCode(array $c, float $qty, string $status, ?float $newInitial, string $comment): void
    {
        $entrySet = ['quantity = ?', 'status = ?', 'comments = ?'];
        $params = [$qty, $status, trim(trim((string)$c['comments']) . "\n" . $comment)];
        if ($newInitial !== null) {
            // La etiqueta impresa muestra la cantidad del bulto (quantity_initial): al reimprimir sale el total nuevo.
            $entrySet[] = 'quantity_initial = ?';
            $params[] = $newInitial;
        }
        $params[] = $c['entry_id'];
        $this->trz->prepare('UPDATE traceability_warehouse_entries SET ' . implode(', ', $entrySet) . ' WHERE id = ?')->execute($params);
        if ($c['box_id'] !== null) {
            $boxSet = ['units_qty = ?', 'status = ?'];
            $boxParams = [$qty, $status];
            if ($newInitial !== null) {
                $boxSet[] = 'units_initial = ?';
                $boxParams[] = $newInitial;
            }
            $boxParams[] = $c['box_id'];
            $this->trz->prepare('UPDATE boxes SET ' . implode(', ', $boxSet) . ' WHERE id = ?')->execute($boxParams);
            if ($this->tableExists('movements')) {
                $this->trz->prepare("INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload) VALUES ('BOX', ?, 'ADJUSTMENT', NULL, NULL, ?)")
                    ->execute([$c['box_id'], json_encode(['code' => $c['code'], 'units' => $qty, 'status' => $status, 'note' => $comment], JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    /**
     * Anota el ajuste en el diario de la caja. Así la caja cuenta como «usada» y un embalaje corregido no la reemplaza.
     * Se anota como DONE y sin OT: el Pulpo solo lee movimientos pendientes o de consumo.
     */
    private function boxMove(array $c, string $kind, float $qty, float $before, float $after, string $operator, string $note, int $now): void
    {
        if ($c['box_id'] === null) {
            return;
        }
        $this->trz->prepare("INSERT INTO box_unit_moves (box_id, kind, qty, units_before, units_after, erp_st_id, state, operator_name, note, created_at, done_at)
                VALUES (?, ?, ?, ?, ?, ?, 'DONE', ?, ?, ?, ?)")
            ->execute([$c['box_id'], $kind, $qty, $before, $after, $c['warehouse_id'] ?: null, mb_substr($operator, 0, 120), mb_substr($note, 0, 500), $now, $now]);
    }

    private function log(string $action, array $from, ?array $to, float $qty, float $fromAfter, ?float $toAfter, ?string $reason, string $note, string $operator, int $now): void
    {
        $this->trz->prepare('INSERT INTO traceability_code_adjustments (action, from_code, to_code, item_id, item_code, warehouse_id, warehouse_name,
                order_ref, qty, from_before, from_after, to_before, to_after, reason, note, operator_name, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$action, $from['code'], $to['code'] ?? null, $from['item_id'] ?: null, mb_substr($from['item_code'], 0, 80),
                $from['warehouse_id'] ?: null, mb_substr($from['warehouse_name'], 0, 100), $from['order_ref'] !== '' ? $from['order_ref'] : null,
                $qty, $from['qty'], $fromAfter, $to['qty'] ?? null, $toAfter, $reason, mb_substr(trim($note), 0, 255) ?: null,
                mb_substr($operator, 0, 120), $now]);
    }

    public static function fmt(float $v): string
    {
        return number_format($v, abs($v - round($v)) < 0.0005 ? 0 : 2, ',', '.');
    }

    private function one(string $sql, array $params = []): ?array
    {
        $stmt = $this->trz->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
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
}
