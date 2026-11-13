<?php

declare(strict_types=1);

/**
 * Traspasos de bodega por código de trazabilidad (cajas, pallets, bultos y bobinas).
 *
 * Cada código sale de SU bodega y llega a una sola bodega de destino. Se mueve todo: la etiqueta y su caja o bobina en
 * trazabilidad, el stock del ERP y su kardex (documento de traspaso storehousechanges + sthdown/sthup, con
 * StockLedgerService::transfer). En el ERP los códigos se agrupan por artículo, bodega de origen y CC: un documento
 * por grupo, por la suma.
 *
 * Destino 3000 (Insumos de Producción): el traspaso queda «Por aceptar» y los códigos en tránsito (bloqueados, siguen en
 * su bodega). Nada se mueve hasta que un supervisor (grupo 31 o administrador del ERP) lo acepta con su usuario y clave.
 * Se guarda quién envió, quién aceptó o rechazó y cuándo.
 *
 * Las tablas del ERP son MyISAM: primero se revisa todo, luego se mueve el ERP (idempotente por clave) y al final la
 * trazabilidad. Si algo falla a mitad, el traspaso queda en ERROR y «Reintentar» retoma por las mismas claves.
 */
require_once __DIR__ . '/StockLedgerService.php';
require_once __DIR__ . '/BagBoxService.php';

final class StockTransferService
{
    public const PRODUCTION_ST = 16;
    public const SHOP_ID = 30010;

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

    /** Tablas del módulo. Fuera de transacciones (en MySQL un CREATE confirma la transacción abierta). */
    public function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        (new BagBoxService($this->trz, $this->erp))->ensureSchema();
        $auto = $this->sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $engine = $this->sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        if (!$this->tableExists('stock_transfers')) {
            $this->trz->exec('CREATE TABLE IF NOT EXISTS stock_transfers (
                id ' . $auto . ',
                to_st_id INT NOT NULL,
                to_name VARCHAR(100) NOT NULL,
                status VARCHAR(12) NOT NULL,
                note VARCHAR(255) NULL,
                sent_by_user_id INT NULL,
                sent_by_name VARCHAR(120) NOT NULL,
                sent_at BIGINT NOT NULL,
                accepted_by_user_id INT NULL,
                accepted_by_name VARCHAR(120) NULL,
                accepted_at BIGINT NULL,
                reject_reason VARCHAR(255) NULL,
                error_message TEXT NULL'
                . ($this->sqlite ? '' : ', INDEX idx_st_status (status), INDEX idx_st_sent (sent_at)') . ')' . $engine);
        }
        if (!$this->tableExists('stock_transfer_items')) {
            $this->trz->exec('CREATE TABLE IF NOT EXISTS stock_transfer_items (
                id ' . $auto . ',
                transfer_id BIGINT NOT NULL,
                code VARCHAR(60) NOT NULL,
                kind VARCHAR(10) NOT NULL,
                entry_id BIGINT NULL,
                entity_id BIGINT NULL,
                item_id INT NOT NULL,
                item_code VARCHAR(80) NULL,
                description VARCHAR(255) NULL,
                from_st_id INT NOT NULL,
                from_name VARCHAR(100) NULL,
                order_id INT NOT NULL DEFAULT 0,
                order_ref VARCHAR(80) NULL,
                qty DECIMAL(12,3) NOT NULL,
                group_no INT NULL,
                erp_doc VARCHAR(30) NULL,
                status VARCHAR(12) NOT NULL'
                . ($this->sqlite ? '' : ', INDEX idx_sti_transfer (transfer_id), INDEX idx_sti_code (code, status)') . ')' . $engine);
        }
        self::$schemaReady = true;
    }

    public static function normalizeCode(string $code): string
    {
        // La pistola con teclado en español escribe «'» en vez de «-».
        return strtoupper(str_replace(["'", '`', '´', '’', '‘'], '-', preg_replace('/\s+/', '', trim($code)) ?? ''));
    }

    /** @return list<string> códigos únicos, en el orden en que llegaron (máximo 300) */
    public static function parseCodes(array|string $raw): array
    {
        $parts = is_array($raw) ? $raw : [$raw];
        $out = [];
        foreach ($parts as $part) {
            foreach (preg_split('/[\s,;]+/', (string)$part) ?: [] as $c) {
                $c = self::normalizeCode($c);
                if ($c !== '' && count($out) < 300) {
                    $out[$c] = true;
                }
            }
        }
        return array_keys($out);
    }

    /** Bodegas activas del ERP de la sucursal (destinos posibles). @return array<int,string> id => nombre */
    public function storehouses(): array
    {
        $stmt = $this->erp->prepare('SELECT id, st_name FROM company_shops_storehouses WHERE st_status = 1 AND st_shop_id = ? ORDER BY st_name');
        $stmt->execute([self::SHOP_ID]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int)$r['id']] = (string)$r['st_name'];
        }
        return $out;
    }

    /**
     * Revisa los códigos: datos de cada uno y por qué no se puede traspasar ('' si se puede).
     *
     * @param list<string> $codes
     * @return list<array<string,mixed>>
     */
    public function inspect(array $codes, int $toSt = 0): array
    {
        $this->ensureSchema();
        $rows = [];
        foreach ($codes as $code) {
            $rows[] = $this->resolve(self::normalizeCode($code), $toSt);
        }
        return $rows;
    }

    /** Datos de un código para traspasarlo (con 'problem' = '' si se puede). */
    private function resolve(string $code, int $toSt, bool $forUpdate = false): array
    {
        $lock = $forUpdate && !$this->sqlite ? ' FOR UPDATE' : '';
        $row = ['code' => $code, 'kind' => '', 'entry_id' => null, 'entity_id' => null, 'item_id' => 0, 'item_code' => '', 'description' => '',
            'from_st_id' => 0, 'from_name' => '', 'order_id' => 0, 'order_ref' => '', 'qty' => 0.0, 'problem' => ''];
        $entry = $this->one('SELECT * FROM traceability_warehouse_entries WHERE UPPER(traceability_code) = ? ORDER BY id DESC LIMIT 1' . $lock, [$code]);
        $roll = $this->tableExists('rolls') ? $this->one('SELECT * FROM rolls WHERE UPPER(roll_code) = ? LIMIT 1' . $lock, [$code]) : null;
        if ($entry === null && $roll === null) {
            return ['problem' => 'No existe.'] + $row;
        }
        $type = strtoupper((string)($entry['entity_type'] ?? ($roll !== null ? 'ROLL' : '')));
        $row['entry_id'] = $entry !== null ? (int)$entry['id'] : null;
        $row['item_code'] = (string)($entry['item_code'] ?? '');
        $row['description'] = (string)($entry['item_description'] ?? '');
        $row['order_ref'] = trim((string)($entry['order_ref'] ?? ''));
        $status = strtoupper((string)($entry['status'] ?? 'IN_WAREHOUSE'));

        if ($type === 'ROLL' || ($roll !== null && $entry === null)) {
            $row['kind'] = 'ROLL';
            if ($roll === null && (int)($entry['entity_id'] ?? 0) > 0 && $this->tableExists('rolls')) {
                $roll = $this->one('SELECT * FROM rolls WHERE id = ?' . $lock, [(int)$entry['entity_id']]);
            }
            if ($roll === null) {
                return ['problem' => 'Bobina sin registro en trazabilidad.'] + $row;
            }
            $row['entity_id'] = (int)$roll['id'];
            $sku = (string)($this->one('SELECT code FROM skus WHERE id = ?', [(int)$roll['sku_id']])['code'] ?? $row['item_code']);
            $row['item_code'] = $sku;
            $item = $sku !== '' ? $this->oneErp('SELECT id, item_title FROM item WHERE item_number_prod = ? OR item_number = ? LIMIT 1', [$sku, $sku]) : null;
            $row['item_id'] = (int)($item['id'] ?? 0);
            $row['description'] = $row['description'] !== '' ? $row['description'] : (string)($item['item_title'] ?? '');
            $row['from_st_id'] = (int)($this->one('SELECT erp_storehouse_id FROM warehouses WHERE id = ?', [(int)$roll['warehouse_id']])['erp_storehouse_id'] ?? 0)
                ?: (int)($entry['warehouse_id'] ?? 0);
            $row['qty'] = max(1.0, round((float)($roll['received_qty'] ?? 1), 3));
            $row['order_ref'] = '';
            $rollStatus = strtoupper((string)($roll['status'] ?? ''));
            if ((int)($roll['current_work_order_id'] ?? 0) > 0) {
                $row['problem'] = 'Bobina asignada a una OT.';
            } elseif ($rollStatus !== 'RECEIVED') {
                $row['problem'] = 'Bobina no disponible en bodega (' . $rollStatus . ').';
            }
        } else {
            $box = null;
            if ($type === 'BOX') {
                $box = (int)($entry['entity_id'] ?? 0) > 0 ? $this->one('SELECT * FROM boxes WHERE id = ?' . $lock, [(int)$entry['entity_id']]) : null;
                $box ??= $this->one('SELECT * FROM boxes WHERE UPPER(box_code) = ?' . $lock, [$code]);
            }
            $row['kind'] = $box !== null ? 'BOX' : ($type !== '' ? $type : 'PRODUCT');
            $row['entity_id'] = $box !== null ? (int)$box['id'] : ((int)($entry['entity_id'] ?? 0) ?: null);
            $row['item_id'] = (int)($box['erp_item_id'] ?? $entry['item_id'] ?? 0);
            $row['from_st_id'] = (int)($box['erp_st_id'] ?? $entry['warehouse_id'] ?? 0);
            $row['qty'] = round((float)($box['units_qty'] ?? $entry['quantity'] ?? 0), 3);
            if ($box !== null) {
                $row['order_id'] = (int)($box['erp_order_id'] ?? 0);
                $row['order_ref'] = trim((string)($box['customer_order_ref'] ?? $row['order_ref']));
                $boxStatus = strtoupper((string)$box['status']);
                if (strtoupper((string)($box['source_kind'] ?? '')) === BagBoxService::SOURCE_PARTIAL) {
                    $row['problem'] = 'Código temporal del Pulpo.';
                } elseif ($boxStatus === 'IN_MACHINE') {
                    $row['problem'] = 'Caja cargada en una máquina.';
                } elseif ($boxStatus !== 'IN_WAREHOUSE') {
                    $row['problem'] = 'Caja sin saldo en bodega (' . $boxStatus . ').';
                }
            } elseif ($row['order_ref'] !== '' && $row['item_id'] > 0 && $row['from_st_id'] > 0) {
                // CC de la etiqueta solo si el ERP tiene stock de ese CC en la bodega (si no, stock general).
                $order = $this->oneErp('SELECT id FROM orders WHERE req_number = ? LIMIT 1', [$row['order_ref']]);
                if ($order !== null && $this->oneErp('SELECT item_id FROM item_shops_storehouses WHERE item_id = ? AND st_id = ? AND iss_order_id = ? AND iss_inventory > 0 LIMIT 1',
                        [$row['item_id'], $row['from_st_id'], (int)$order['id']]) !== null) {
                    $row['order_id'] = (int)$order['id'];
                }
            }
            if ($row['problem'] === '' && $status !== 'IN_WAREHOUSE') {
                $row['problem'] = 'Sin saldo en bodega (' . $status . ').';
            }
        }
        $row['from_name'] = $this->storeName($row['from_st_id']);
        if ($row['problem'] === '') {
            if ($row['item_id'] <= 0) {
                $row['problem'] = 'El artículo no existe en el ERP.';
            } elseif ($row['from_st_id'] <= 0) {
                $row['problem'] = 'No se sabe en qué bodega está.';
            } elseif ($row['qty'] <= 0) {
                $row['problem'] = 'Sin saldo.';
            } elseif ($toSt > 0 && $row['from_st_id'] === $toSt) {
                $row['problem'] = 'Ya está en ' . $this->storeName($toSt) . '.';
            } elseif (($pending = $this->pendingTransferOf($code)) !== null) {
                $row['problem'] = 'Está en el traspaso N° ' . $pending . ' por aceptar.';
            }
        }
        return $row;
    }

    /** N° del traspaso por aceptar (o con error) que tiene el código, o null. */
    private function pendingTransferOf(string $code): ?int
    {
        $r = $this->one("SELECT t.id FROM stock_transfer_items i JOIN stock_transfers t ON t.id = i.transfer_id
            WHERE i.code = ? AND t.status IN ('PENDING','ERROR') LIMIT 1", [$code]);
        return $r === null ? null : (int)$r['id'];
    }

    /**
     * Registra un traspaso. A la 3000 queda por aceptar; a otra bodega se ejecuta al tiro.
     *
     * @param array{user_id?:int,name?:string} $actor
     * @return array{id:int,status:string}
     */
    public function create(array $codes, int $toSt, array $actor, string $note = ''): array
    {
        $this->ensureSchema();
        $codes = self::parseCodes($codes);
        if ($codes === []) {
            throw new InvalidArgumentException('No hay códigos para traspasar.');
        }
        $stores = $this->storehouses();
        if (!isset($stores[$toSt])) {
            throw new InvalidArgumentException('Elige la bodega de destino.');
        }
        $name = trim((string)($actor['name'] ?? '')) ?: 'Bodega';
        $this->trz->beginTransaction();
        try {
            $rows = [];
            $errors = [];
            sort($codes);
            foreach ($codes as $c) {
                $r = $this->resolve($c, $toSt, true);
                if ($r['problem'] !== '') {
                    $errors[] = $c . ': ' . $r['problem'];
                }
                $rows[] = $r;
            }
            if ($errors !== []) {
                throw new RuntimeException('No se registró el traspaso. ' . implode(' · ', $errors));
            }
            $pending = $toSt === self::PRODUCTION_ST;
            $this->trz->prepare('INSERT INTO stock_transfers (to_st_id, to_name, status, note, sent_by_user_id, sent_by_name, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$toSt, $stores[$toSt], $pending ? 'PENDING' : 'SENDING', mb_substr(trim($note), 0, 255) ?: null,
                    (int)($actor['user_id'] ?? 0) ?: null, mb_substr($name, 0, 120), time()]);
            $id = (int)$this->trz->lastInsertId();
            $ins = $this->trz->prepare('INSERT INTO stock_transfer_items (transfer_id, code, kind, entry_id, entity_id, item_id, item_code, description,
                from_st_id, from_name, order_id, order_ref, qty, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($rows as $r) {
                $ins->execute([$id, $r['code'], $r['kind'], $r['entry_id'], $r['entity_id'], $r['item_id'], mb_substr($r['item_code'], 0, 80),
                    mb_substr($r['description'], 0, 255), $r['from_st_id'], mb_substr($r['from_name'], 0, 100), $r['order_id'],
                    $r['order_ref'] !== '' ? mb_substr($r['order_ref'], 0, 80) : null, $r['qty'], 'PENDING']);
            }
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) {
                $this->trz->rollBack();
            }
            throw $e;
        }
        if (!$pending) {
            $this->execute($id, (int)($actor['user_id'] ?? 0));
        }
        return ['id' => $id, 'status' => (string)$this->transfer($id)['status']];
    }

    /**
     * Supervisor del ERP (grupo 31 o administrador), igual que el cierre de embalaje. null si no es válido.
     *
     * @return array{user_id:int,name:string}|null
     */
    public function validateSupervisor(string $login, string $password): ?array
    {
        $login = trim($login);
        $password = trim($password);
        if ($login === '' || $password === '') {
            return null;
        }
        $stmt = $this->erp->prepare('SELECT t1.id, t1.user_firstname, t1.user_lastname, t1.user_login FROM user t1
            WHERE t1.user_login = ? AND t1.user_pass = ? AND t1.user_status = 1
              AND (t1.user_type = 1 OR (SELECT COUNT(*) FROM user_group t2 WHERE t2.user_id = t1.id AND t2.group_id IN (31)) > 0) LIMIT 1');
        $stmt->execute([$login, md5($password)]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            return null;
        }
        $name = trim((string)$u['user_firstname'] . ' ' . (string)$u['user_lastname']);
        return ['user_id' => (int)$u['id'], 'name' => $name !== '' ? $name : (string)$u['user_login']];
    }

    /** Acepta un traspaso por aceptar (destino 3000) y lo ejecuta. */
    public function accept(int $id, string $login, string $password): array
    {
        $this->ensureSchema();
        $sup = $this->validateSupervisor($login, $password);
        if ($sup === null) {
            throw new RuntimeException('Usuario o clave de supervisor no válidos.');
        }
        $t = $this->transfer($id);
        if ($t === null || $t['status'] !== 'PENDING') {
            throw new RuntimeException('El traspaso N° ' . $id . ' no está por aceptar.');
        }
        $this->trz->prepare('UPDATE stock_transfers SET accepted_by_user_id = ?, accepted_by_name = ?, accepted_at = ? WHERE id = ? AND status = ?')
            ->execute([$sup['user_id'], mb_substr($sup['name'], 0, 120), time(), $id, 'PENDING']);
        $this->execute($id, $sup['user_id']);
        return $this->transfer($id);
    }

    /** Rechaza un traspaso por aceptar: los códigos quedan libres en su bodega (no se movió nada). */
    public function reject(int $id, string $reason, string $login, string $password): array
    {
        $this->ensureSchema();
        $sup = $this->validateSupervisor($login, $password);
        if ($sup === null) {
            throw new RuntimeException('Usuario o clave de supervisor no válidos.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Escribe el motivo del rechazo.');
        }
        $t = $this->transfer($id);
        if ($t === null || $t['status'] !== 'PENDING') {
            throw new RuntimeException('El traspaso N° ' . $id . ' no está por aceptar.');
        }
        $this->trz->prepare("UPDATE stock_transfers SET status = 'REJECTED', accepted_by_user_id = ?, accepted_by_name = ?, accepted_at = ?, reject_reason = ? WHERE id = ?")
            ->execute([$sup['user_id'], mb_substr($sup['name'], 0, 120), time(), mb_substr(trim($reason), 0, 255), $id]);
        $this->trz->prepare("UPDATE stock_transfer_items SET status = 'REJECTED' WHERE transfer_id = ?")->execute([$id]);
        return $this->transfer($id);
    }

    /** Vuelve a ejecutar un traspaso que quedó con error (retoma por las mismas claves: no duplica). */
    public function retry(int $id, int $userId = 0): array
    {
        $t = $this->transfer($id);
        if ($t === null || $t['status'] !== 'ERROR') {
            throw new RuntimeException('El traspaso N° ' . $id . ' no tiene un error que reintentar.');
        }
        $this->execute($id, $userId);
        return $this->transfer($id);
    }

    /** Mueve ERP (stock + kardex) y trazabilidad. Queda DONE, o ERROR con el mensaje si algo falla. */
    private function execute(int $id, int $userId): void
    {
        $t = $this->transfer($id);
        $items = $this->items($id);
        $toSt = (int)$t['to_st_id'];
        try {
            // 1. Revisar de nuevo cada código (lo ya movido en un intento anterior se salta).
            $groups = [];
            foreach ($items as $it) {
                if ((string)$it['status'] === 'DONE') {
                    continue;
                }
                $now = $this->resolve((string)$it['code'], 0);
                $alreadyThere = $now['from_st_id'] === $toSt && $it['erp_doc'] !== null;
                if (!$alreadyThere) {
                    $problem = $now['problem'];
                    if (str_starts_with($problem, 'Está en el traspaso N° ' . $id . ' ')) {
                        $problem = '';
                    }
                    if ($problem !== '') {
                        throw new RuntimeException($it['code'] . ': ' . $problem);
                    }
                    if ($now['from_st_id'] !== (int)$it['from_st_id'] || $now['item_id'] !== (int)$it['item_id']
                        || abs($now['qty'] - (float)$it['qty']) > 0.0005 || $now['order_id'] !== (int)$it['order_id']) {
                        throw new RuntimeException($it['code'] . ': cambió desde que se cargó (bodega, saldo o CC). Rechaza y vuelve a cargarlo.');
                    }
                }
                $gk = (int)$it['item_id'] . ':' . (int)$it['from_st_id'] . ':' . (int)$it['order_id'];
                $groups[$gk] ??= ['item_id' => (int)$it['item_id'], 'from' => (int)$it['from_st_id'], 'order_id' => (int)$it['order_id'], 'qty' => 0.0, 'items' => []];
                $groups[$gk]['qty'] += (float)$it['qty'];
                $groups[$gk]['items'][] = $it;
            }
            ksort($groups);
            // 2. Stock suficiente en cada origen antes de mover nada (los grupos ya traspasados no se vuelven a revisar).
            $ledger = new StockLedgerService($this->erp);
            foreach ($groups as $gk => $g) {
                if ($g['items'][0]['erp_doc'] !== null) {
                    continue;
                }
                $have = (float)($this->oneErp('SELECT COALESCE(SUM(iss_inventory),0) AS s FROM item_shops_storehouses WHERE item_id = ? AND st_id = ? AND iss_order_id = ?',
                    [$g['item_id'], $g['from'], $g['order_id']])['s'] ?? 0);
                if ($have + 0.000001 < $g['qty']) {
                    throw new RuntimeException('El ERP tiene ' . StockLedgerService::fmt($have) . ' de ' . $g['items'][0]['item_code'] . ' en '
                        . $g['items'][0]['from_name'] . ($g['order_id'] > 0 ? ' (CC ' . $g['items'][0]['order_ref'] . ')' : '')
                        . ' y el traspaso necesita ' . StockLedgerService::fmt($g['qty']) . '.');
                }
            }
            // 3. ERP: un documento de traspaso por grupo (idempotente por clave).
            $n = 0;
            foreach ($groups as $g) {
                $n++;
                $codes = implode(', ', array_column($g['items'], 'code'));
                $res = $ledger->transfer(['key' => 'trsp.' . $id . '.' . $n, 'item_id' => $g['item_id'], 'from_st' => $g['from'], 'to_st' => $toSt,
                    'qty' => round($g['qty'], 3), 'order_id' => $g['order_id'], 'user_id' => $userId,
                    'text' => 'Traspaso N° ' . $id . ' a ' . $t['to_name'] . '. Envió ' . $t['sent_by_name']
                        . ($t['accepted_by_name'] ? '; aceptó ' . $t['accepted_by_name'] : '') . '. Códigos: ' . mb_substr($codes, 0, 600) . '.']);
                $ids = array_map(static fn($i) => (int)$i['id'], $g['items']);
                $this->trz->prepare('UPDATE stock_transfer_items SET group_no = ?, erp_doc = ? WHERE id IN (' . implode(',', $ids) . ')')
                    ->execute([$n, (string)$res['number']]);
            }
            // 4. Trazabilidad: cada código a la bodega de destino.
            $this->trz->beginTransaction();
            try {
                foreach ($this->items($id) as $it) {
                    if ((string)$it['status'] !== 'DONE') {
                        $this->moveCode($it, $toSt, (string)$t['to_name'], $id);
                    }
                }
                $this->trz->prepare("UPDATE stock_transfers SET status = 'DONE', error_message = NULL WHERE id = ?")->execute([$id]);
                $this->trz->commit();
            } catch (Throwable $e) {
                if ($this->trz->inTransaction()) {
                    $this->trz->rollBack();
                }
                throw $e;
            }
        } catch (Throwable $e) {
            $this->trz->prepare("UPDATE stock_transfers SET status = 'ERROR', error_message = ? WHERE id = ?")->execute([mb_substr($e->getMessage(), 0, 2000), $id]);
            throw $e;
        }
    }

    /** Deja el código en la bodega de destino (etiqueta + caja o bobina) y anota el movimiento. */
    private function moveCode(array $it, int $toSt, string $toName, int $transferId): void
    {
        $bags = new BagBoxService($this->trz, $this->erp);
        $localTo = $bags->localWarehouseId($toSt);
        $payload = ['transfer_id' => $transferId, 'erp_transfer' => $it['erp_doc'], 'code' => $it['code'], 'from_st' => (int)$it['from_st_id'], 'to_st' => $toSt];
        if ($it['kind'] === 'BOX' && (int)$it['entity_id'] > 0) {
            $box = $bags->byId((int)$it['entity_id']);
            $bags->update((int)$it['entity_id'], ['erp_st_id' => $toSt, 'warehouse_id' => $localTo]);
            $bags->movement((int)$it['entity_id'], 'TRANSFER', $box !== null && $box['warehouse_id'] !== null ? (int)$box['warehouse_id'] : null, $localTo, $payload);
        } elseif ($it['kind'] === 'ROLL' && (int)$it['entity_id'] > 0) {
            if ($localTo === null) {
                throw new RuntimeException('La bodega ' . $toName . ' no está configurada en trazabilidad para bobinas.');
            }
            $fromLocal = $this->one('SELECT warehouse_id FROM rolls WHERE id = ?', [(int)$it['entity_id']]);
            $this->trz->prepare('UPDATE rolls SET warehouse_id = ? WHERE id = ?')->execute([$localTo, (int)$it['entity_id']]);
            if ($this->tableExists('movements')) {
                $this->trz->prepare("INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload) VALUES ('ROLL', ?, 'TRANSFER', ?, ?, ?)")
                    ->execute([(int)$it['entity_id'], $fromLocal['warehouse_id'] ?? null, $localTo, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            }
        }
        if ($it['entry_id'] !== null) {
            $this->trz->prepare('UPDATE traceability_warehouse_entries SET warehouse_id = ?, warehouse_name = ? WHERE id = ?')
                ->execute([$toSt, mb_substr($toName, 0, 100), (int)$it['entry_id']]);
        }
        $this->trz->prepare("UPDATE stock_transfer_items SET status = 'DONE' WHERE id = ?")->execute([(int)$it['id']]);
    }

    public function transfer(int $id): ?array
    {
        $this->ensureSchema();
        return $this->one('SELECT * FROM stock_transfers WHERE id = ?', [$id]);
    }

    /** @return list<array<string,mixed>> */
    public function items(int $id): array
    {
        $stmt = $this->trz->prepare('SELECT * FROM stock_transfer_items WHERE transfer_id = ? ORDER BY id');
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> traspasos por aceptar, los más antiguos primero, con sus códigos */
    public function pending(): array
    {
        $this->ensureSchema();
        $out = [];
        foreach ($this->trz->query("SELECT * FROM stock_transfers WHERE status = 'PENDING' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $t['items'] = $this->items((int)$t['id']);
            $out[] = $t;
        }
        return $out;
    }

    public function pendingCount(): int
    {
        $this->ensureSchema();
        return (int)$this->trz->query("SELECT COUNT(*) FROM stock_transfers WHERE status = 'PENDING'")->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    public function history(int $limit = 60): array
    {
        $this->ensureSchema();
        return $this->trz->query('SELECT t.*, (SELECT COUNT(*) FROM stock_transfer_items i WHERE i.transfer_id = t.id) AS codes,
                (SELECT SUM(qty) FROM stock_transfer_items i WHERE i.transfer_id = t.id) AS units
            FROM stock_transfers t ORDER BY t.id DESC LIMIT ' . max(1, min(500, $limit)))->fetchAll(PDO::FETCH_ASSOC);
    }

    private function storeName(int $stId): string
    {
        if ($stId <= 0) {
            return '';
        }
        static $cache = [];
        if (!isset($cache[$stId])) {
            try {
                $cache[$stId] = (string)($this->oneErp('SELECT st_name FROM company_shops_storehouses WHERE id = ?', [$stId])['st_name'] ?? ('Bodega ' . $stId));
            } catch (Throwable) {
                $cache[$stId] = 'Bodega ' . $stId;
            }
        }
        return $cache[$stId];
    }

    private function one(string $sql, array $params = []): ?array
    {
        $stmt = $this->trz->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    private function oneErp(string $sql, array $params = []): ?array
    {
        $stmt = $this->erp->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        $k = spl_object_id($this->trz) . ':' . $table;
        if (isset($cache[$k])) {
            return $cache[$k];
        }
        if ($this->sqlite) {
            $stmt = $this->trz->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
        } else {
            $stmt = $this->trz->prepare('SHOW TABLES LIKE ?');
        }
        $stmt->execute([$table]);
        $exists = $stmt->fetchColumn() !== false;
        if ($exists) {
            $cache[$k] = true;
        }
        return $exists;
    }
}
