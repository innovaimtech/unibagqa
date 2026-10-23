<?php

declare(strict_types=1);

/**
 * Internal handle-roll stock movements, excluded from raw-material reports.
 * Movimientos internos de manillas, separados del consumo de materia prima.
 */
final class HandleConsumptionService
{
    private PDO $erp;
    private PDO $trz;
    private bool $schemaReady = false;
    private ?bool $transactionalErp = null;

    public function __construct(PDO $erp, PDO $trz)
    {
        $this->erp = $erp;
        $this->trz = $trz;
    }

    private function lock(PDO $pdo): string
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    }

    private function target(): string
    {
        if ($this->erp->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') return 'sqlite-test';
        $row = $this->erp->query('SELECT DATABASE() AS db, @@hostname AS host, @@port AS port')->fetch(PDO::FETCH_ASSOC);
        return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
    }

    private function erpIsTransactional(): bool
    {
        if ($this->transactionalErp !== null) return $this->transactionalErp;
        if ($this->erp->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') return $this->transactionalErp = true;
        $tables = $this->erp->query("SHOW TABLE STATUS WHERE Name IN
            ('company_data','stockchanges','stockchanges_items','item_shops_storehouses','tran_data','prod_worker_ot_events')")
            ->fetchAll(PDO::FETCH_ASSOC);
        if (count($tables) !== 6) throw new RuntimeException('No se pudieron comprobar las tablas de stock del ERP.');
        foreach ($tables as $table) if (strcasecmp((string)$table['Engine'], 'InnoDB') !== 0) return $this->transactionalErp = false;
        return $this->transactionalErp = true;
    }

    private function lockErpTables(): void
    {
        $this->erp->exec('LOCK TABLES company_data WRITE, stockchanges WRITE, stockchanges_items WRITE,
            item_shops_storehouses WRITE, tran_data WRITE, prod_worker_ot_events WRITE, item READ, prod_worker_ot READ');
    }

    public function ensureSchema(): void
    {
        if ($this->schemaReady) return;
        // Only TRZ owns the journal. No ERP schema changes.
        // El diario reside solo en TRZ. No se cambia el esquema ERP.
        $this->trz->exec('CREATE TABLE IF NOT EXISTS cys_handle_operations (
            operation_key VARCHAR(32) PRIMARY KEY,
            roll_id BIGINT NOT NULL,
            erp_worker_ot_id BIGINT NOT NULL,
            erp_target VARCHAR(64) NOT NULL,
            action VARCHAR(16) NOT NULL,
            original_key VARCHAR(32) NULL,
            state VARCHAR(16) NOT NULL,
            payload TEXT NOT NULL,
            erp_stockchange_id BIGINT NULL,
            error_message TEXT NULL,
            created_at BIGINT NOT NULL,
            completed_at BIGINT NULL'
            . ($this->trz->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' :
                ', INDEX idx_handle_roll_state (roll_id, state),
                   INDEX idx_handle_ot_target (erp_worker_ot_id, erp_target),
                   INDEX idx_handle_original (original_key)')
            . ')' . ($this->trz->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' ENGINE=InnoDB'));
        if ($this->trz->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $tables = $this->trz->query("SHOW TABLE STATUS WHERE Name IN
                ('rolls','traceability_warehouse_entries','movements','events','cys_handle_operations')")->fetchAll(PDO::FETCH_ASSOC);
            if (count($tables) !== 5) throw new RuntimeException('No se pudieron verificar las tablas de trazabilidad.');
            foreach ($tables as $table) {
                if (strcasecmp((string)$table['Engine'], 'InnoDB') !== 0) {
                    throw new RuntimeException('Las tablas TRZ deben ser transaccionales para consumir manillas con seguridad.');
                }
            }
        }
        $this->schemaReady = true;
    }

    private function one(PDO $pdo, string $sql, array $params): ?array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function operatorContext(int $otId, int $workerId, int $plantId): array
    {
        $row = $this->one($this->erp, 'SELECT ot.id AS ot_id, ot.wok_status, ag.id AS ag_id,
                ag.ag_reqid AS order_id, o.req_number, o.req_company_id, o.req_shop_id,
                wi.win_wrkid, wi.win_plantaid, wi.win_equipoid, eq.equipo_type_id,
                (SELECT i.item_number_prod FROM orders_items oi JOIN item i ON i.id = oi.item_id
                 WHERE oi.req_id = o.id ORDER BY oi.item_pos LIMIT 1) AS product_sku
            FROM prod_worker_ot ot JOIN prod_worker_init wi ON wi.id = ot.wok_init_id
            JOIN prod_agenda ag ON ag.id = ot.wok_ag_id JOIN orders o ON o.id = ag.ag_reqid
            JOIN equipo eq ON eq.id = wi.win_equipoid WHERE ot.id = ?', [$otId]);
        if (!$row || $workerId <= 0 || (int)$row['win_wrkid'] !== $workerId
            || ($plantId > 0 && (int)$row['win_plantaid'] !== $plantId)
            || (int)$row['equipo_type_id'] !== 8 || (int)$row['wok_status'] !== 1) {
            throw new RuntimeException('La OT debe estar abierta en corte y sellado y pertenecer al operador conectado.');
        }
        return $row;
    }

    private function traceRoll(string $code, bool $forUpdate = false): array
    {
        $roll = $this->one($this->trz, 'SELECT r.*, s.code AS sku_code, s.description,
                w.erp_storehouse_id, w.code AS warehouse_code, w.name AS warehouse_name
            FROM rolls r JOIN skus s ON s.id = r.sku_id JOIN warehouses w ON w.id = r.warehouse_id
            WHERE r.roll_code = ?' . ($forUpdate ? $this->lock($this->trz) : ''), [$code]);
        if (!$roll || !preg_match('/^ROLL-(MAN|HDL)-[0-9]+$/i', $code)
            || !preg_match('/^CUT-MAN-([0-9]+)(?:-|$)/', (string)$roll['sku_code'], $match)) {
            throw new RuntimeException('Escanea un codigo de rollo de manillas con trazabilidad de corte.');
        }
        $entry = $this->one($this->trz, 'SELECT * FROM traceability_warehouse_entries
            WHERE entity_type = \'ROLL\' AND entity_id = ? AND traceability_code = ?
            ORDER BY id DESC LIMIT 1' . ($forUpdate ? $this->lock($this->trz) : ''), [(int)$roll['id'], $code]);
        if (!$entry || (int)$entry['item_id'] !== (int)$match[1]
            || (int)$entry['warehouse_id'] !== (int)$roll['warehouse_id']) {
            throw new RuntimeException('El codigo no tiene una relacion valida con su articulo y bodega. Requiere conciliacion.');
        }
        $roll['entry'] = $entry;
        return $roll;
    }

    private function pendingForRoll(int $rollId): ?array
    {
        return $this->one($this->trz, 'SELECT * FROM cys_handle_operations
            WHERE roll_id = ? AND state = \'PENDING\' LIMIT 1', [$rollId]);
    }

    public function scan(int $otId, int $workerId, int $plantId, string $code): array
    {
        $ctx = $this->operatorContext($otId, $workerId, $plantId);
        $this->ensureSchema();
        $roll = $this->traceRoll(trim($code));
        $this->validateAvailable($roll);
        if ($this->pendingForRoll((int)$roll['id'])) {
            throw new RuntimeException('Este codigo tiene una operacion pendiente. Reintenta desde el historial de manillas.');
        }
        $roll['product_warning'] = !$this->matchesProduct($roll, (string)$ctx['product_sku']);
        unset($roll['entry']);
        return $roll;
    }

    private function matchesProduct(array $roll, string $sku): bool
    {
        return $sku !== '' && str_ends_with((string)$roll['sku_code'], '-' . $sku);
    }

    private function validateAvailable(array $roll): void
    {
        $entry = $roll['entry'];
        if ((string)$roll['status'] !== 'RECEIVED' || !empty($roll['current_work_order_id'])
            || (int)$roll['erp_storehouse_id'] !== 16 || (float)$roll['weight_kg'] <= 0
            || (float)$roll['received_qty'] <= 0 || (string)$entry['status'] !== 'IN_WAREHOUSE') {
            throw new RuntimeException('Las manillas deben estar disponibles en Bodega 3000, sin asignacion a otra OT.');
        }
        if (abs((float)$entry['weight_kg'] - (float)$roll['weight_kg']) > 0.001
            || abs((float)$entry['quantity'] - (float)$roll['received_qty']) > 0.001) {
            throw new RuntimeException('El saldo del codigo y el saldo de bodega no coinciden. Requiere conciliacion.');
        }
    }

    /** Pure, independently testable balance calculation. / Calculo aislado de saldos. */
    public static function balances(float $kg, float $qty, ?float $meters, float $leftover): array
    {
        if (!is_finite($leftover) || !is_finite($kg) || !is_finite($qty)
            || $kg <= 0 || $qty <= 0 || $leftover < 0 || $leftover >= $kg) {
            throw new RuntimeException('El sobrante debe ser menor al peso disponible y no puede ser negativo.');
        }
        $leftover = round($leftover, 3);
        $used = round($kg - $leftover, 3);
        $leftQty = $leftover === 0.0 ? 0.0 : round($qty * $leftover / $kg, 3);
        $usedQty = round($qty - $leftQty, 3);
        if ($used <= 0 || $usedQty <= 0 || ($leftover > 0 && $leftQty <= 0)) {
            throw new RuntimeException('El consumo es demasiado pequeno para la precision del inventario.');
        }
        return [
            'weight_kg' => $leftover, 'received_qty' => $leftQty,
            'meters' => $meters === null ? null : round($meters * $leftover / $kg, 2),
            'used_kg' => $used, 'used_qty' => $usedQty,
            'status' => $leftover > 0 ? 'RECEIVED' : 'CONSUMED',
            'entry_status' => $leftover > 0 ? 'IN_WAREHOUSE' : 'CONSUMED',
        ];
    }

    private function snapshot(array $roll): array
    {
        return [
            'weight_kg' => (float)$roll['weight_kg'], 'received_qty' => (float)$roll['received_qty'],
            'meters' => $roll['meters'] === null ? null : (float)$roll['meters'],
            'status' => (string)$roll['status'], 'entry_status' => (string)$roll['entry']['status'],
            'warehouse_id' => (int)$roll['warehouse_id'],
            'current_work_order_id' => $roll['current_work_order_id'],
        ];
    }

    private function assertSnapshot(array $roll, array $expected): void
    {
        $actual = $this->snapshot($roll);
        foreach ($actual as $field => $value) {
            if ($value != ($expected[$field] ?? null)) {
                throw new RuntimeException('El codigo cambio despues de esta operacion. No se alterara el saldo; requiere conciliacion.');
            }
        }
        if ((string)$roll['entry']['status'] !== (string)$expected['entry_status']
            || abs((float)$roll['entry']['weight_kg'] - (float)$expected['weight_kg']) > 0.001
            || abs((float)$roll['entry']['quantity'] - (float)$expected['received_qty']) > 0.001) {
            throw new RuntimeException('La entrada de bodega cambio. Requiere conciliacion antes de continuar.');
        }
    }

    private function operation(string $key, bool $locked = false): ?array
    {
        return $this->one($this->trz, 'SELECT * FROM cys_handle_operations WHERE operation_key = ?'
            . ($locked ? $this->lock($this->trz) : ''), [$key]);
    }

    public function consume(int $otId, int $workerId, int $plantId, int $userId, string $operator,
        string $key, string $code, float $leftover, bool $acceptOtherProduct, array $scannedBalance = []): array
    {
        $ctx = $this->operatorContext($otId, $workerId, $plantId);
        $this->ensureSchema();
        $this->validateKey($key);
        $this->trz->beginTransaction();
        try {
            $roll = $this->traceRoll(trim($code), true);
            $existing = $this->operation($key, true);
            if ($existing) {
                $payload = json_decode($existing['payload'], true, 512, JSON_THROW_ON_ERROR);
                if ((int)$existing['erp_worker_ot_id'] !== $otId || (int)$existing['roll_id'] !== (int)$roll['id']
                    || $existing['action'] !== 'CONSUME'
                    || abs((float)$payload['after']['weight_kg'] - $leftover) > 0.0001) {
                    throw new RuntimeException('La confirmacion ya corresponde a otro consumo.');
                }
                $this->trz->commit();
                return $this->resume($otId, $workerId, $plantId, $key);
            }
            $this->validateAvailable($roll);
            if ($scannedBalance !== [] && (abs((float)$scannedBalance['weight_kg'] - (float)$roll['weight_kg']) > 0.0001
                || abs((float)$scannedBalance['received_qty'] - (float)$roll['received_qty']) > 0.0001)) {
                throw new RuntimeException('El saldo cambio desde el escaneo. Escanea nuevamente antes de consumir.');
            }
            if ($this->pendingForRoll((int)$roll['id'])) throw new RuntimeException('Hay una operacion pendiente para este codigo.');
            if (!$this->matchesProduct($roll, (string)$ctx['product_sku']) && !$acceptOtherProduct) {
                throw new RuntimeException('Confirma expresamente el uso de manillas preparadas para otro producto.');
            }
            $before = $this->snapshot($roll);
            $after = array_replace($before, self::balances($before['weight_kg'], $before['received_qty'], $before['meters'], $leftover));
            $payload = [
                'code' => $roll['roll_code'], 'entry_id' => (int)$roll['entry']['id'],
                'item_id' => (int)$roll['entry']['item_id'], 'erp_storehouse_id' => 16,
                'before' => $before, 'after' => $after, 'context' => $ctx,
                'user_id' => $userId, 'worker_id' => $workerId, 'operator' => $operator,
                'other_product_accepted' => $acceptOtherProduct,
            ];
            $this->insertOperation($key, (int)$roll['id'], $otId, 'CONSUME', null, $payload);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            throw $e;
        }
        return $this->resume($otId, $workerId, $plantId, $key);
    }

    private function validateKey(string $key): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $key)) throw new RuntimeException('Confirmacion de manillas invalida.');
    }

    private function insertOperation(string $key, int $rollId, int $otId, string $action, ?string $original, array $payload): void
    {
        $this->trz->prepare('INSERT INTO cys_handle_operations
            (operation_key, roll_id, erp_worker_ot_id, erp_target, action, original_key, state, payload, created_at)
            VALUES (?, ?, ?, ?, ?, ?, \'PENDING\', ?, ?)')->execute([
                $key, $rollId, $otId, $this->target(), $action, $original,
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), time(),
            ]);
    }

    public function reverse(int $otId, int $workerId, int $plantId, int $userId, string $operator,
        string $key, string $original, string $reason, string $supervisor = ''): array
    {
        $ctx = $this->operatorContext($otId, $workerId, $plantId);
        $this->ensureSchema();
        $this->validateKey($key);
        if (trim($reason) === '') throw new RuntimeException('Indica el motivo de la devolucion.');
        $source = $this->operation($original);
        if (!$source || (int)$source['erp_worker_ot_id'] !== $otId || $source['action'] !== 'CONSUME') {
            throw new RuntimeException('El consumo original no pertenece a esta OT.');
        }
        $sourcePayload = json_decode($source['payload'], true, 512, JSON_THROW_ON_ERROR);
        $this->trz->beginTransaction();
        try {
            $roll = $this->traceRoll($sourcePayload['code'], true);
            $existing = $this->operation($key, true);
            if ($existing) {
                if ($existing['original_key'] !== $original || $existing['action'] !== 'REVERSE'
                    || (int)$existing['erp_worker_ot_id'] !== $otId) throw new RuntimeException('Confirmacion ya utilizada.');
                $this->trz->commit();
                return $this->resume($otId, $workerId, $plantId, $key);
            }
            $source = $this->operation($original, true);
            if ($source['state'] !== 'DONE' || $source['erp_target'] !== $this->target()) {
                throw new RuntimeException('Solo se puede devolver un consumo confirmado en el mismo ERP.');
            }
            if ($this->pendingForRoll((int)$roll['id'])) throw new RuntimeException('Primero resuelve la operacion pendiente del codigo.');
            $this->assertSnapshot($roll, $sourcePayload['after']);
            $duplicate = $this->one($this->trz, 'SELECT operation_key FROM cys_handle_operations
                WHERE original_key = ? AND state != \'CANCELLED\' LIMIT 1', [$original]);
            if ($duplicate) throw new RuntimeException('Este consumo ya tiene una devolucion.');
            $payload = $sourcePayload;
            $payload['before'] = $sourcePayload['after'];
            $payload['after'] = $sourcePayload['before'];
            $payload['after']['used_qty'] = $sourcePayload['after']['used_qty'];
            $payload['after']['used_kg'] = $sourcePayload['after']['used_kg'];
            $payload['context'] = $ctx;
            $payload['user_id'] = $userId;
            $payload['operator'] = $operator;
            $payload['reason'] = trim($reason);
            $payload['supervisor'] = $supervisor;
            $this->insertOperation($key, (int)$roll['id'], $otId, 'REVERSE', $original, $payload);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            throw $e;
        }
        return $this->resume($otId, $workerId, $plantId, $key);
    }

    public function history(int $otId): array
    {
        $this->ensureSchema();
        $stmt = $this->trz->prepare('SELECT * FROM cys_handle_operations WHERE erp_worker_ot_id = ?
            AND erp_target = ? ORDER BY CASE WHEN state = \'PENDING\' THEN 0 ELSE 1 END,
            created_at DESC, operation_key DESC LIMIT 100');
        $stmt->execute([$otId, $this->target()]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) $row['detail'] = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);
        unset($row);
        return $rows;
    }

    public function assertNoPendingForWorkOrder(int $otId): void
    {
        if ($this->trz->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $exists = $this->trz->query("SELECT name FROM sqlite_master WHERE type='table' AND name='cys_handle_operations'")->fetchColumn();
        } else {
            $exists = $this->trz->query("SHOW TABLES LIKE 'cys_handle_operations'")->fetchColumn();
        }
        if (!$exists) return;
        $stmt = $this->trz->prepare('SELECT COUNT(*) FROM cys_handle_operations
            WHERE erp_worker_ot_id = ? AND erp_target = ? AND state = \'PENDING\'');
        $stmt->execute([$otId, $this->target()]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException('Resuelve las operaciones pendientes de Manillas en Materiales antes de terminar la OT.');
        }
    }

    public function cancelPending(int $otId, int $workerId, int $plantId, string $key, string $reason, string $supervisor = ''): void
    {
        $this->operatorContext($otId, $workerId, $plantId);
        $this->ensureSchema();
        if (trim($reason) === '') throw new RuntimeException('Indica el motivo de cancelacion.');
        $op = $this->operation($key);
        if (!$op || (int)$op['erp_worker_ot_id'] !== $otId || $op['erp_target'] !== $this->target()) {
            throw new RuntimeException('Operacion no disponible en esta OT y ERP.');
        }
        $payload = json_decode($op['payload'], true, 512, JSON_THROW_ON_ERROR);
        $tablesLocked = false;
        $this->trz->beginTransaction();
        try {
            $this->traceRoll($payload['code'], true);
            $op = $this->operation($key, true);
            if ($op['state'] !== 'PENDING') throw new RuntimeException('La operacion no esta pendiente.');
            if ($this->erpIsTransactional()) $this->erp->beginTransaction();
            else {
                $this->lockErpTables();
                $tablesLocked = true;
            }
            $this->one($this->erp, 'SELECT id FROM company_data WHERE id = ?' . $this->lock($this->erp),
                [$payload['context']['req_company_id']]);
            $booked = $this->one($this->erp, 'SELECT id FROM stockchanges WHERE stk_annotation LIKE ?',
                ['%[TRZ-MAN:' . $key . ']%']);
            if ($booked) throw new RuntimeException('ERP ya registro el movimiento. Debes reintentar y luego devolver, no cancelar.');
            $payload['cancellation'] = ['worker_id' => $workerId, 'supervisor' => $supervisor,
                'reason' => trim($reason), 'timestamp' => time()];
            $this->trz->prepare('UPDATE cys_handle_operations SET state = \'CANCELLED\', error_message = ?, completed_at = ?, payload = ?
                WHERE operation_key = ?')->execute([trim($reason), time(),
                    json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $key]);
            if (!$tablesLocked) $this->erp->commit();
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->erp->inTransaction()) $this->erp->rollBack();
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            throw $e;
        } finally {
            if ($tablesLocked) $this->erp->exec('UNLOCK TABLES');
        }
    }

    public function resume(int $otId, int $workerId, int $plantId, string $key): array
    {
        $this->operatorContext($otId, $workerId, $plantId);
        $this->ensureSchema();
        $op = $this->operation($key);
        if (!$op || (int)$op['erp_worker_ot_id'] !== $otId || $op['erp_target'] !== $this->target()) {
            throw new RuntimeException('La operacion pendiente pertenece a otra OT o a otro ERP.');
        }
        if ($op['state'] === 'DONE') return $op;
        if ($op['state'] !== 'PENDING') throw new RuntimeException('Esta operacion ya fue cancelada.');
        $payload = json_decode($op['payload'], true, 512, JSON_THROW_ON_ERROR);
        $this->trz->beginTransaction();
        try {
            // Lock the roll until both commits finish; journal survives a lost commit reply.
            // Se bloquea el rollo; el diario permite recuperar una respuesta de commit perdida.
            $roll = $this->traceRoll($payload['code'], true);
            $op = $this->operation($key, true);
            if ($op['state'] === 'DONE') {
                $this->trz->commit();
                return $op;
            }
            if ($op['state'] !== 'PENDING') throw new RuntimeException('Operacion cancelada.');
            $this->assertSnapshot($roll, $payload['before']);
            $documentId = $this->applyErp($op, $payload);
            $after = $payload['after'];
            $this->trz->prepare('UPDATE rolls SET weight_kg = ?, received_qty = ?, meters = ?, status = ? WHERE id = ?')
                ->execute([$after['weight_kg'], $after['received_qty'], $after['meters'], $after['status'], (int)$roll['id']]);
            $this->trz->prepare('UPDATE traceability_warehouse_entries
                SET weight_kg = ?, quantity = ?, meters = ?, status = ? WHERE id = ?')->execute([
                    $after['weight_kg'], $after['received_qty'], $after['meters'], $after['entry_status'], $payload['entry_id'],
                ]);
            $eventPayload = json_encode(['operation_key' => $key, 'roll_id' => (int)$roll['id'],
                'erp_worker_ot_id' => $otId, 'erp_stockchange_id' => $documentId, 'detail' => $payload],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $this->trz->prepare('INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload)
                VALUES (\'ROLL\', ?, \'ADJUSTMENT\', ?, ?, ?)')->execute([
                    (int)$roll['id'], $roll['warehouse_id'], $roll['warehouse_id'], $eventPayload,
                ]);
            $this->trz->prepare('INSERT INTO events (type, payload) VALUES (?, ?)')
                ->execute([$op['action'] === 'CONSUME' ? 'HANDLE_CONSUMED' : 'HANDLE_CONSUMPTION_REVERSED', $eventPayload]);
            if ($op['action'] === 'REVERSE') {
                $this->trz->prepare('UPDATE cys_handle_operations SET state = \'REVERSED\' WHERE operation_key = ?')
                    ->execute([$op['original_key']]);
            }
            $this->trz->prepare('UPDATE cys_handle_operations SET state = \'DONE\', erp_stockchange_id = ?,
                error_message = NULL, completed_at = ? WHERE operation_key = ?')->execute([$documentId, time(), $key]);
            $this->trz->commit();
            return $this->operation($key);
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            $this->trz->prepare('UPDATE cys_handle_operations SET error_message = ? WHERE operation_key = ? AND state = \'PENDING\'')
                ->execute([mb_substr($e->getMessage(), 0, 1000), $key]);
            throw new RuntimeException('Operacion pendiente; no registres otro consumo. Usa Reintentar. ' . $e->getMessage(), 0, $e);
        }
    }

    private function applyErp(array $op, array $payload): int
    {
        if (!$this->erpIsTransactional()) return $this->applyLockedErp($op, $payload);
        $this->erp->beginTransaction();
        try {
            $ctx = $payload['context'];
            $ot = $this->one($this->erp, 'SELECT wok_status FROM prod_worker_ot WHERE id = ?'
                . $this->lock($this->erp), [$op['erp_worker_ot_id']]);
            if (!$ot || (int)$ot['wok_status'] !== 1) throw new RuntimeException('La OT ya no esta abierta.');
            $company = $this->one($this->erp, 'SELECT company_numformat_stockchange, company_numcounter_stockchange
                FROM company_data WHERE id = ?' . $this->lock($this->erp), [(int)$ctx['req_company_id']]);
            if (!$company || (int)$ctx['req_shop_id'] <= 0) throw new RuntimeException('La empresa o sucursal ERP no es valida.');
            $marker = '[TRZ-MAN:' . $op['operation_key'] . ']';
            $existing = $this->one($this->erp, 'SELECT id, stk_status, stk_negative, sth_fromprodotid,
                stk_companyid, stk_shopid FROM stockchanges WHERE stk_annotation LIKE ?'
                . $this->lock($this->erp), ['%' . $marker . '%']);
            if ($existing) {
                $item = $this->one($this->erp, 'SELECT item_id, item_amount, item_st_id FROM stockchanges_items
                    WHERE stk_id = ? AND item_pos = 0', [(int)$existing['id']]);
                $ledger = $this->one($this->erp, 'SELECT tran_amount, item_id, tran_st_id, tran_shop_id
                    FROM tran_data WHERE tran_id = ? AND tran_pos = 0 AND tran_type = ?', [
                        (int)$existing['id'], $op['action'] === 'CONSUME' ? 'stockchangedown' : 'stockchangeup',
                    ]);
                if ((int)$existing['stk_status'] !== 2 || (int)$existing['sth_fromprodotid'] !== 0
                    || (int)$existing['stk_negative'] !== ($op['action'] === 'CONSUME' ? 1 : 0)
                    || (int)$existing['stk_companyid'] !== (int)$ctx['req_company_id']
                    || (int)$existing['stk_shopid'] !== (int)$ctx['req_shop_id']
                    || !$item || (int)$item['item_id'] !== (int)$payload['item_id']
                    || (int)$item['item_st_id'] !== 16
                    || abs((float)$item['item_amount'] - (float)$payload['after']['used_qty']) > 0.00001
                    || !$ledger || (int)$ledger['item_id'] !== (int)$payload['item_id']
                    || (int)$ledger['tran_st_id'] !== 16 || (int)$ledger['tran_shop_id'] !== (int)$ctx['req_shop_id']
                    || abs((float)$ledger['tran_amount'] - (float)$payload['after']['used_qty']) > 0.00001) {
                    throw new RuntimeException('El documento ERP fue alterado; requiere conciliacion.');
                }
                $this->erp->commit();
                return (int)$existing['id'];
            }
            $item = $this->one($this->erp, 'SELECT id, item_title FROM item WHERE id = ?', [$payload['item_id']]);
            if (!$item) throw new RuntimeException('El articulo de manillas no existe en este ERP.');
            $stock = $this->one($this->erp, 'SELECT iss_inventory, iss_inventory_reserved FROM item_shops_storehouses
                WHERE item_id = ? AND shop_id = ? AND st_id = 16' . $this->lock($this->erp),
                [$payload['item_id'], $ctx['req_shop_id']]);
            $qty = (float)$payload['after']['used_qty'];
            $negative = $op['action'] === 'CONSUME' ? 1 : 0;
            if (!$stock || ($negative && (float)$stock['iss_inventory'] - (float)$stock['iss_inventory_reserved'] + 0.000001 < $qty)) {
                throw new RuntimeException('No hay suficiente stock disponible de manillas en Bodega 3000 del ERP.');
            }
            $now = time();
            $counter = (int)$company['company_numcounter_stockchange'] + 1;
            $number = (trim((string)$company['company_numformat_stockchange']) ?: 'IA') . sprintf('%08d', $counter);
            $this->erp->prepare('UPDATE company_data SET company_numcounter_stockchange = ? WHERE id = ?')
                ->execute([$counter, $ctx['req_company_id']]);
            $summary = ($negative ? 'Manillas utilizadas: ' : 'Manillas devueltas: ') . $payload['code']
                . '. Consumo: ' . number_format((float)$payload['after']['used_kg'], 3, ',', '.') . ' kg'
                . '. Saldo: ' . number_format((float)$payload['after']['weight_kg'], 3, ',', '.') . ' kg.';
            if (!$negative) $summary .= ' Motivo: ' . ($payload['reason'] ?? '');
            $this->erp->prepare('INSERT INTO stockchanges
                (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate, stk_negative,
                 stk_status, stk_crtdat, stk_crtusr, stk_fixedsthid, sth_fromprodotid, sth_fromrebo_agid,
                 sth_order_id, sth_order_num, sth_assign_equipoid, sth_assign_wrkid, sth_planta_id,
                 stk_cinumber, stk_vintnumber, sth_fromrebo_agid_grphash)
                VALUES (?, ?, ?, ?, ?, ?, ?, 2, ?, ?, 16, 0, 0, ?, ?, ?, ?, ?, \'\', \'\', \'\')')->execute([
                    $number, $summary . ' ' . $marker, $negative ? 19 : 20,
                    $ctx['req_company_id'], $ctx['req_shop_id'], $now, $negative, $now,
                    $payload['user_id'], $ctx['order_id'], $ctx['req_number'],
                    $ctx['win_equipoid'], $ctx['win_wrkid'], $ctx['win_plantaid'],
                ]);
            $documentId = (int)$this->erp->lastInsertId();
            $this->erp->prepare('INSERT INTO stockchanges_items
                (stk_id, item_id, item_pos, item_amount, item_type, item_st_id, item_reboprod_amt, item_reboprod_unitkg)
                VALUES (?, ?, 0, ?, \'item\', 16, ?, ?)')->execute([
                    $documentId, $payload['item_id'], $qty, $qty,
                    round((float)$payload['after']['used_kg'] / $qty, 2),
                ]);
            $newStock = round((float)$stock['iss_inventory'] + ($negative ? -$qty : $qty), 10);
            $this->erp->prepare('UPDATE item_shops_storehouses SET iss_inventory = ?
                WHERE item_id = ? AND shop_id = ? AND st_id = 16')->execute([
                    $newStock, $payload['item_id'], $ctx['req_shop_id'],
                ]);
            // The stock ledger is updated once in the same ERP transaction.
            // La bitacora de stock se actualiza una vez en la misma transaccion ERP.
            $this->erp->prepare('INSERT INTO tran_data
                (tran_id, tran_pos, tran_type, tran_number, tran_currstock, tran_amount,
                 tran_company_id, tran_shop_id, tran_st_id, tran_issueid, item_id, item_title, item_type,
                 tran_day, tran_month, tran_year, tran_crtusr, tran_crtdat, tran_order_id)
                VALUES (?, 0, ?, ?, ?, ?, ?, ?, 16, ?, ?, ?, \'item\', ?, ?, ?, ?, ?, ?)')->execute([
                    $documentId, $negative ? 'stockchangedown' : 'stockchangeup',
                    $number, $stock['iss_inventory'], $qty, $ctx['req_company_id'],
                    $ctx['req_shop_id'], $negative ? 19 : 20, $payload['item_id'], $item['item_title'],
                    (int)date('j', $now), (int)date('n', $now), (int)date('Y', $now),
                    $payload['user_id'], $now, $ctx['order_id'],
                ]);
            $this->erp->prepare('INSERT INTO prod_worker_ot_events
                (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                VALUES (?, 0, ?, ?, 2, \'materiales\', ?)')->execute([
                    $op['erp_worker_ot_id'], $now, $now, $summary . ' ' . $marker,
                ]);
            $this->erp->commit();
            return $documentId;
        } catch (Throwable $e) {
            if ($this->erp->inTransaction()) $this->erp->rollBack();
            throw $e;
        }
    }

    private function stockLedgerFingerprint(int $itemId, int $shopId): string
    {
        $stmt = $this->erp->prepare('SELECT tran_id, tran_pos, tran_type, tran_amount, tran_crtdat
            FROM tran_data WHERE item_id = ? AND tran_shop_id = ? AND tran_st_id = 16
            ORDER BY tran_crtdat DESC, tran_id DESC, tran_pos DESC, tran_type DESC LIMIT 1');
        $stmt->execute([$itemId, $shopId]);
        return hash('sha256', json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: [], JSON_THROW_ON_ERROR));
    }

    /**
     * MyISAM cannot roll back: persist the stock checkpoint before the first debit.
     * MyISAM no revierte: persistir el saldo previo antes del primer descuento.
     */
    private function applyLockedErp(array $op, array $payload): int
    {
        $this->lockErpTables();
        try {
            $ctx = $payload['context'];
            $ot = $this->one($this->erp, 'SELECT wok_status FROM prod_worker_ot WHERE id = ?', [$op['erp_worker_ot_id']]);
            if (!$ot || (int)$ot['wok_status'] !== 1) throw new RuntimeException('La OT ya no esta abierta.');
            $company = $this->one($this->erp, 'SELECT company_numformat_stockchange, company_numcounter_stockchange
                FROM company_data WHERE id = ?', [$ctx['req_company_id']]);
            $item = $this->one($this->erp, 'SELECT id, item_title FROM item WHERE id = ?', [$payload['item_id']]);
            $stock = $this->one($this->erp, 'SELECT iss_inventory, iss_inventory_reserved FROM item_shops_storehouses
                WHERE item_id = ? AND shop_id = ? AND st_id = 16', [$payload['item_id'], $ctx['req_shop_id']]);
            if (!$company || !$item || !$stock || (int)$ctx['req_shop_id'] <= 0) {
                throw new RuntimeException('No se pudo resolver articulo, stock, empresa o sucursal ERP.');
            }
            $marker = '[TRZ-MAN:' . $op['operation_key'] . ']';
            $qty = (float)$payload['after']['used_qty'];
            $negative = $op['action'] === 'CONSUME' ? 1 : 0;
            $type = $negative ? 'stockchangedown' : 'stockchangeup';
            $summary = ($negative ? 'Manillas utilizadas: ' : 'Manillas devueltas: ') . $payload['code']
                . '. ' . ($negative ? 'Consumo: ' : 'Devuelto: ')
                . number_format((float)$payload['after']['used_kg'], 3, ',', '.') . ' kg. Saldo: '
                . number_format((float)$payload['after']['weight_kg'], 3, ',', '.') . ' kg.';
            if (!$negative) $summary .= ' Motivo: ' . ($payload['reason'] ?? '');
            $document = $this->one($this->erp, 'SELECT * FROM stockchanges WHERE stk_annotation LIKE ?', ['%' . $marker . '%']);
            if (!$document) {
                if ($negative && (float)$stock['iss_inventory'] - (float)$stock['iss_inventory_reserved'] + 0.000001 < $qty) {
                    throw new RuntimeException('No hay suficiente stock disponible de manillas en Bodega 3000.');
                }
                $anchor = [
                    'before' => (float)$stock['iss_inventory'],
                    'after' => round((float)$stock['iss_inventory'] + ($negative ? -$qty : $qty), 10),
                    'ledger' => $this->stockLedgerFingerprint((int)$payload['item_id'], (int)$ctx['req_shop_id']),
                ];
                $counter = (int)$company['company_numcounter_stockchange'] + 1;
                $number = (trim((string)$company['company_numformat_stockchange']) ?: 'IA') . sprintf('%08d', $counter);
                $this->erp->prepare('UPDATE company_data SET company_numcounter_stockchange = ? WHERE id = ?')
                    ->execute([$counter, $ctx['req_company_id']]);
                $now = time();
                $annotation = $summary . ' ' . $marker . ' [TRZ-MAN-STOCK:'
                    . base64_encode(json_encode($anchor, JSON_THROW_ON_ERROR)) . ']';
                $this->erp->prepare('INSERT INTO stockchanges
                    (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate, stk_negative,
                     stk_status, stk_crtdat, stk_crtusr, stk_fixedsthid, sth_fromprodotid, sth_fromrebo_agid,
                     sth_order_id, sth_order_num, sth_assign_equipoid, sth_assign_wrkid, sth_planta_id,
                     stk_cinumber, stk_vintnumber, sth_fromrebo_agid_grphash)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, 16, 0, 0, ?, ?, ?, ?, ?, \'\', \'\', \'\')')->execute([
                        $number, $annotation, $negative ? 19 : 20, $ctx['req_company_id'], $ctx['req_shop_id'],
                        $now, $negative, $now, $payload['user_id'], $ctx['order_id'], $ctx['req_number'],
                        $ctx['win_equipoid'], $ctx['win_wrkid'], $ctx['win_plantaid'],
                    ]);
                $document = $this->one($this->erp, 'SELECT * FROM stockchanges WHERE id = ?', [(int)$this->erp->lastInsertId()]);
            }
            if (!in_array((int)$document['stk_status'], [1, 2], true)
                || (int)$document['stk_negative'] !== $negative || (int)$document['sth_fromprodotid'] !== 0
                || (int)$document['sth_fromrebo_agid'] !== 0 || (int)$document['stk_shopid'] !== (int)$ctx['req_shop_id']
                || (int)$document['stk_companyid'] !== (int)$ctx['req_company_id']
                || !preg_match('/\\[TRZ-MAN-STOCK:([A-Za-z0-9+\\/=]+)\\]/', $document['stk_annotation'], $match)) {
                throw new RuntimeException('Documento ERP no reconocible. Requiere conciliacion.');
            }
            $anchor = json_decode(base64_decode($match[1], true), true, 512, JSON_THROW_ON_ERROR);
            if (abs((float)$anchor['after'] - ((float)$anchor['before'] + ($negative ? -$qty : $qty))) > 0.000001) {
                throw new RuntimeException('El punto de recuperacion no coincide con el consumo.');
            }
            $id = (int)$document['id'];
            $existingItem = $this->one($this->erp, 'SELECT * FROM stockchanges_items WHERE stk_id = ? AND item_pos = 0', [$id]);
            $ledger = $this->one($this->erp, 'SELECT * FROM tran_data WHERE tran_id = ? AND tran_pos = 0 AND tran_type = ?', [$id, $type]);
            if (!$existingItem && (int)$document['stk_status'] === 1 && !$ledger) {
                $this->erp->prepare('INSERT INTO stockchanges_items
                    (stk_id, item_id, item_pos, item_amount, item_type, item_st_id, item_reboprod_amt, item_reboprod_unitkg)
                    VALUES (?, ?, 0, ?, \'item\', 16, ?, ?)')->execute([
                        $id, $payload['item_id'], $qty, $qty, round((float)$payload['after']['used_kg'] / $qty, 2),
                    ]);
            } elseif (!$existingItem || (int)$existingItem['item_id'] !== (int)$payload['item_id']
                || (int)$existingItem['item_st_id'] !== 16 || abs((float)$existingItem['item_amount'] - $qty) > 0.000001) {
                throw new RuntimeException('El item del documento fue alterado. Requiere conciliacion.');
            }
            if (!$ledger) {
                if ((int)$document['stk_status'] !== 1
                    || $anchor['ledger'] !== $this->stockLedgerFingerprint((int)$payload['item_id'], (int)$ctx['req_shop_id'])) {
                    throw new RuntimeException('Hubo otros movimientos desde la interrupcion. El saldo requiere conciliacion manual.');
                }
                $current = (float)$stock['iss_inventory'];
                if (abs($current - (float)$anchor['before']) < 0.000001) {
                    $this->erp->prepare('UPDATE item_shops_storehouses SET iss_inventory = ?
                        WHERE item_id = ? AND shop_id = ? AND st_id = 16')->execute([
                            $anchor['after'], $payload['item_id'], $ctx['req_shop_id'],
                        ]);
                } elseif (abs($current - (float)$anchor['after']) >= 0.000001) {
                    throw new RuntimeException('No se puede determinar el saldo despues de la interrupcion. Requiere conciliacion manual.');
                }
                $now = (int)$document['stk_crtdat'];
                $this->erp->prepare('INSERT INTO tran_data
                    (tran_id, tran_pos, tran_type, tran_number, tran_currstock, tran_amount,
                     tran_company_id, tran_shop_id, tran_st_id, tran_issueid, item_id, item_title, item_type,
                     tran_day, tran_month, tran_year, tran_crtusr, tran_crtdat, tran_order_id)
                    VALUES (?, 0, ?, ?, ?, ?, ?, ?, 16, ?, ?, ?, \'item\', ?, ?, ?, ?, ?, ?)')->execute([
                        $id, $type, $document['stk_num'], $anchor['before'], $qty,
                        $ctx['req_company_id'], $ctx['req_shop_id'], $negative ? 19 : 20, $payload['item_id'],
                        $item['item_title'], (int)date('j', $now), (int)date('n', $now), (int)date('Y', $now),
                        $payload['user_id'], $now, $ctx['order_id'],
                    ]);
            } elseif ((int)$ledger['item_id'] !== (int)$payload['item_id'] || (int)$ledger['tran_st_id'] !== 16
                || (int)$ledger['tran_shop_id'] !== (int)$ctx['req_shop_id'] || abs((float)$ledger['tran_amount'] - $qty) > 0.000001) {
                throw new RuntimeException('La bitacora ERP fue alterada. Requiere conciliacion.');
            }
            $event = $this->one($this->erp, 'SELECT id FROM prod_worker_ot_events WHERE evt_prod_worker_otid = ? AND evt_comments LIKE ?',
                [$op['erp_worker_ot_id'], '%' . $marker . '%']);
            if (!$event) {
                $now = time();
                $this->erp->prepare('INSERT INTO prod_worker_ot_events
                    (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                    VALUES (?, 0, ?, ?, 2, \'materiales\', ?)')->execute([$op['erp_worker_ot_id'], $now, $now, $summary . ' ' . $marker]);
            }
            $this->erp->prepare('UPDATE stockchanges SET stk_status = 2 WHERE id = ?')->execute([$id]);
            return $id;
        } finally {
            $this->erp->exec('UNLOCK TABLES');
        }
    }
}
