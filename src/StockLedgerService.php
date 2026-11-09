<?php

declare(strict_types=1);

/**
 * Motor único de movimientos de stock del ERP con kardex.
 *
 * Todo lo que el sistema consume, devuelve o traspasa pasa por aquí, para que cada cambio de
 * item_shops_storehouses deje su documento (stockchanges o storehousechanges), sus líneas y su
 * registro en tran_data, igual que los documentos del ERP.
 *
 * - Idempotente: cada movimiento lleva la marca [TRZ-LED:clave]. Repetir la misma clave no vuelve a mover stock.
 * - Las tablas del ERP son MyISAM (sin rollback). El orden de escritura permite retomar un corte a mitad:
 *   documento (estado 1) → línea → kardex (con el saldo previo) → stock → documento en estado 2.
 *   Al retomar, el saldo previo guardado en el kardex indica si el stock ya se movió.
 * - Un candado de MySQL (GET_LOCK) evita que dos pantallas muevan stock a la vez desde este sistema.
 * - Stock por CC: con order_id > 0 se mueve la fila iss_order_id = CC; con 0, la fila de stock general.
 */
final class StockLedgerService
{
    public const DEFAULT_COMPANY_ID = 20010;
    public const DEFAULT_SHOP_ID = 30010;
    public const ISSUE_OUT = 19;
    public const ISSUE_IN = 20;

    private PDO $erp;
    private bool $sqlite;
    private int $lockDepth = 0;

    public function __construct(PDO $erp)
    {
        $this->erp = $erp;
        $this->sqlite = $erp->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    public static function marker(string $key): string
    {
        return '[TRZ-LED:' . $key . ']';
    }

    private static function assertKey(string $key): void
    {
        if (!preg_match('/^[A-Za-z0-9_.:\-]{3,120}$/D', $key)) {
            throw new InvalidArgumentException('Clave de movimiento de stock no válida: ' . $key);
        }
    }

    private function one(string $sql, array $params = []): ?array
    {
        $stmt = $this->erp->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    private function withLock(callable $fn)
    {
        if ($this->sqlite || $this->lockDepth > 0) {
            $this->lockDepth++;
            try {
                return $fn();
            } finally {
                $this->lockDepth--;
            }
        }
        $got = (int)$this->erp->query("SELECT GET_LOCK('unibag_stock_ledger', 15)")->fetchColumn();
        if ($got !== 1) {
            throw new RuntimeException('El stock está ocupado por otro movimiento. Intenta de nuevo en unos segundos.');
        }
        $this->lockDepth++;
        try {
            return $fn();
        } finally {
            $this->lockDepth--;
            $this->erp->query("SELECT RELEASE_LOCK('unibag_stock_ledger')")->fetchColumn();
        }
    }

    /** Documento de ajuste ya registrado con esta clave (o null). */
    public function findPosted(string $key): ?array
    {
        self::assertKey($key);
        return $this->one('SELECT * FROM stockchanges WHERE stk_annotation LIKE ? ORDER BY id LIMIT 1', ['%' . self::marker($key) . '%']);
    }

    public function isPosted(string $key): bool
    {
        $doc = $this->findPosted($key);
        return $doc !== null && (int)$doc['stk_status'] === 2;
    }

    /**
     * Movimiento de stock (entrada o salida) con documento y kardex.
     *
     * @param array{key:string,item_id:int,st_id:int,delta:float,order_id?:int,company_id?:int,shop_id?:int,
     *              text?:string,user_id?:int,allow_negative?:bool,order_num?:string,equipo_id?:int,worker_id?:int} $m
     * @return array{id:int,number:string,already:bool,stock_before:float,stock_after:float}
     */
    public function post(array $m): array
    {
        $key = (string)($m['key'] ?? '');
        self::assertKey($key);
        $itemId = (int)($m['item_id'] ?? 0);
        $stId = (int)($m['st_id'] ?? 0);
        $delta = round((float)($m['delta'] ?? 0), 6);
        if ($itemId <= 0 || $stId <= 0 || !is_finite($delta) || abs($delta) < 0.000001) {
            throw new InvalidArgumentException('Movimiento de stock incompleto (artículo, bodega o cantidad).');
        }
        return $this->withLock(function () use ($m, $key, $itemId, $stId, $delta): array {
            $ctx = $this->context($m, $stId, $itemId);
            $marker = self::marker($key);
            $doc = $this->findPosted($key);
            if ($doc !== null) {
                $this->assertSameMovement($doc, $itemId, $stId, $delta, $ctx);
                if ((int)$doc['stk_status'] === 2) {
                    $row = $this->stockRow($itemId, $ctx['shop_id'], $stId, $ctx['order_id']);
                    return ['id' => (int)$doc['id'], 'number' => (string)$doc['stk_num'], 'already' => true,
                        'stock_before' => (float)$row - $delta, 'stock_after' => (float)$row];
                }
                return $this->finish((int)$doc['id'], $itemId, $stId, $delta, $ctx, $m);
            }
            $item = $this->one('SELECT id FROM item WHERE id = ?', [$itemId]);
            if ($item === null) {
                throw new RuntimeException('El artículo ' . $itemId . ' no existe en el ERP.');
            }
            $current = $this->stockRow($itemId, $ctx['shop_id'], $stId, $ctx['order_id']);
            if ($delta < 0 && empty($m['allow_negative']) && ($current ?? 0.0) + $delta < -0.000001) {
                throw new RuntimeException('No hay stock suficiente de ' . $this->itemLabel($itemId) . ' en ' . $this->storeLabel($stId)
                    . ($ctx['order_id'] > 0 ? ' (CC ' . $ctx['order_num'] . ')' : '') . ': hay '
                    . self::fmt((float)($current ?? 0)) . ' y se necesitan ' . self::fmt(-$delta) . '.');
            }
            $number = $this->nextNumber('stockchange', $ctx['company_id']);
            $now = time();
            $text = trim((string)($m['text'] ?? 'Movimiento de producción'));
            $this->erp->prepare('INSERT INTO stockchanges
                    (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate, stk_negative, stk_status,
                     stk_crtdat, stk_crtusr, stk_upddat, stk_updusr, stk_fixedsthid, sth_fromprodotid, sth_fromrebo_agid,
                     sth_order_id, sth_order_num, stk_cinumber, stk_vintnumber, sth_fromrebo_agid_grphash)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, 0, 0, ?, ?, \'\', \'\', \'\')')
                ->execute([$number, mb_substr($text, 0, 900) . ' ' . $marker, $delta < 0 ? self::ISSUE_OUT : self::ISSUE_IN,
                    $ctx['company_id'], $ctx['shop_id'], $now, $delta < 0 ? 1 : 0, $now, $ctx['user_id'], $now, $ctx['user_id'],
                    $stId, $ctx['order_id'], $ctx['order_num']]);
            $docId = (int)$this->erp->lastInsertId();
            return $this->finish($docId, $itemId, $stId, $delta, $ctx, $m);
        });
    }

    /** Completa un documento en estado 1: línea, kardex, stock y estado 2 (cada paso solo si falta). */
    private function finish(int $docId, int $itemId, int $stId, float $delta, array $ctx, array $m): array
    {
        $doc = $this->one('SELECT * FROM stockchanges WHERE id = ?', [$docId]);
        $qty = abs($delta);
        $line = $this->one('SELECT * FROM stockchanges_items WHERE stk_id = ? AND item_pos = 0', [$docId]);
        if ($line === null) {
            $this->erp->prepare("INSERT INTO stockchanges_items (stk_id, item_id, item_pos, item_amount, item_type, item_st_id)
                VALUES (?, ?, 0, ?, 'item', ?)")->execute([$docId, $itemId, $qty, $stId]);
        }
        $type = $delta < 0 ? 'stockchangedown' : 'stockchangeup';
        $current = $this->stockRow($itemId, $ctx['shop_id'], $stId, $ctx['order_id']);
        $tran = $this->one('SELECT tran_currstock FROM tran_data WHERE tran_id = ? AND tran_pos = 0 AND tran_type = ?', [$docId, $type]);
        if ($tran === null) {
            $before = (float)($current ?? 0);
            $created = (int)$doc['stk_crtdat'];
            $this->erp->prepare("INSERT INTO tran_data
                    (tran_id, tran_pos, tran_type, tran_number, tran_currstock, tran_amount, tran_company_id, tran_shop_id, tran_st_id,
                     tran_issueid, item_id, item_title, item_type, tran_day, tran_month, tran_year, tran_crtusr, tran_crtdat, tran_order_id)
                    VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'item', ?, ?, ?, ?, ?, ?)")
                ->execute([$docId, $type, (string)$doc['stk_num'], $before, $qty, $ctx['company_id'], $ctx['shop_id'], $stId,
                    $delta < 0 ? self::ISSUE_OUT : self::ISSUE_IN, $itemId, $this->itemTitle($itemId),
                    (int)date('j', $created), (int)date('n', $created), (int)date('Y', $created), $ctx['user_id'], $created, $ctx['order_id']]);
            $this->applyStock($itemId, $ctx['shop_id'], $stId, $ctx['order_id'], $delta, $current);
        } else {
            $before = (float)$tran['tran_currstock'];
            $now = (float)($current ?? 0);
            if (abs($now - $before) < 0.000001) {
                $this->applyStock($itemId, $ctx['shop_id'], $stId, $ctx['order_id'], $delta, $current);
            } elseif (abs($now - ($before + $delta)) >= 0.000001) {
                throw new RuntimeException('El documento ' . $doc['stk_num'] . ' quedó a medias y el stock cambió desde entonces. Requiere revisión manual.');
            }
        }
        $this->erp->prepare('UPDATE stockchanges SET stk_status = 2 WHERE id = ?')->execute([$docId]);
        return ['id' => $docId, 'number' => (string)$doc['stk_num'], 'already' => false,
            'stock_before' => $before, 'stock_after' => round($before + $delta, 6)];
    }

    private function applyStock(int $itemId, int $shopId, int $stId, int $orderId, float $delta, ?float $current): void
    {
        if ($current === null) {
            $this->erp->prepare('INSERT INTO item_shops_storehouses (item_id, shop_id, st_id, iss_order_amount, iss_inventory_min, iss_inventory, iss_inventory_reserved, iss_order_id)
                VALUES (?, ?, ?, 0, 0, ?, 0, ?)')->execute([$itemId, $shopId, $stId, $delta, $orderId]);
            return;
        }
        $this->erp->prepare('UPDATE item_shops_storehouses SET iss_inventory = iss_inventory + ?
            WHERE item_id = ? AND shop_id = ? AND st_id = ? AND iss_order_id = ?')->execute([$delta, $itemId, $shopId, $stId, $orderId]);
    }

    private function assertSameMovement(array $doc, int $itemId, int $stId, float $delta, array $ctx): void
    {
        $line = $this->one('SELECT item_id, item_amount, item_st_id FROM stockchanges_items WHERE stk_id = ? AND item_pos = 0', [(int)$doc['id']]);
        $sameDoc = (int)$doc['stk_negative'] === ($delta < 0 ? 1 : 0) && (int)$doc['stk_fixedsthid'] === $stId
            && (int)$doc['sth_order_id'] === $ctx['order_id'];
        $sameLine = $line === null || ((int)$line['item_id'] === $itemId && (int)$line['item_st_id'] === $stId
            && abs((float)$line['item_amount'] - abs($delta)) < 0.000001);
        if (!$sameDoc || !$sameLine) {
            throw new RuntimeException('La clave ' . $doc['stk_num'] . ' ya se usó para otro movimiento de stock.');
        }
    }

    /**
     * Deshace un movimiento registrado con la clave dada (documento contrario con kardex). Repetir no revierte dos veces.
     * Devuelve null si la clave nunca se registró.
     */
    public function reverse(string $key, string $reason = '', int $userId = 0): ?array
    {
        $doc = $this->findPosted($key);
        if ($doc === null) {
            return null;
        }
        $line = $this->one('SELECT item_id, item_amount, item_st_id FROM stockchanges_items WHERE stk_id = ? AND item_pos = 0', [(int)$doc['id']]);
        if ($line === null) {
            throw new RuntimeException('El documento ' . $doc['stk_num'] . ' no tiene líneas; no se puede revertir.');
        }
        if ((int)$doc['stk_status'] !== 2) {
            // Quedó a medias: primero se completa, para que la reversa devuelva lo que realmente se movió.
            $this->post(['key' => $key, 'item_id' => (int)$line['item_id'], 'st_id' => (int)$line['item_st_id'],
                'delta' => ((int)$doc['stk_negative'] === 1 ? -1 : 1) * (float)$line['item_amount'],
                'order_id' => (int)$doc['sth_order_id'], 'company_id' => (int)$doc['stk_companyid'], 'shop_id' => (int)$doc['stk_shopid'],
                'allow_negative' => true]);
        }
        $delta = ((int)$doc['stk_negative'] === 1 ? 1 : -1) * (float)$line['item_amount'];
        return $this->post(['key' => $key . '.R', 'item_id' => (int)$line['item_id'], 'st_id' => (int)$line['item_st_id'],
            'delta' => $delta, 'order_id' => (int)$doc['sth_order_id'], 'company_id' => (int)$doc['stk_companyid'],
            'shop_id' => (int)$doc['stk_shopid'], 'user_id' => $userId, 'allow_negative' => true,
            'text' => 'Reversa de ' . $doc['stk_num'] . ($reason !== '' ? ': ' . $reason : '') . '.']);
    }

    /** Suma neta (con signo) movida por las claves que empiezan con el prefijo, sin contar las reversas por separado. */
    public function netByPrefix(string $prefix): float
    {
        self::assertKey($prefix . 'x');
        $stmt = $this->erp->prepare('SELECT s.stk_negative, si.item_amount FROM stockchanges s
            JOIN stockchanges_items si ON si.stk_id = s.id AND si.item_pos = 0
            WHERE s.stk_status = 2 AND s.stk_annotation LIKE ?');
        $stmt->execute(['%[TRZ-LED:' . $prefix . '%']);
        $net = 0.0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $net += ((int)$row['stk_negative'] === 1 ? -1 : 1) * (float)$row['item_amount'];
        }
        return round($net, 6);
    }

    /** Claves (sin reversa) registradas con el prefijo y que aún no tienen su reversa. */
    public function openKeys(string $prefix): array
    {
        self::assertKey($prefix . 'x');
        $stmt = $this->erp->prepare('SELECT stk_annotation FROM stockchanges WHERE stk_annotation LIKE ?');
        $stmt->execute(['%[TRZ-LED:' . $prefix . '%']);
        $keys = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $annotation) {
            if (preg_match('/\[TRZ-LED:([A-Za-z0-9_.:\-]+)\]/', (string)$annotation, $mm) === 1) {
                $keys[$mm[1]] = true;
            }
        }
        $open = [];
        foreach (array_keys($keys) as $k) {
            if (!str_ends_with($k, '.R') && !isset($keys[$k . '.R'])) {
                $open[] = $k;
            }
        }
        sort($open);
        return $open;
    }

    /**
     * Traspaso entre bodegas con documento de traspaso y kardex (sthdown / sthup). Idempotente por clave.
     *
     * @param array{key:string,item_id:int,from_st:int,to_st:int,qty:float,order_id?:int,company_id?:int,shop_id?:int,text?:string,user_id?:int,allow_negative?:bool} $t
     */
    public function transfer(array $t): array
    {
        $key = (string)($t['key'] ?? '');
        self::assertKey($key);
        $itemId = (int)($t['item_id'] ?? 0);
        $from = (int)($t['from_st'] ?? 0);
        $to = (int)($t['to_st'] ?? 0);
        $qty = round((float)($t['qty'] ?? 0), 6);
        if ($itemId <= 0 || $from <= 0 || $to <= 0 || $from === $to || $qty <= 0) {
            throw new InvalidArgumentException('Traspaso incompleto (artículo, bodegas o cantidad).');
        }
        return $this->withLock(function () use ($t, $key, $itemId, $from, $to, $qty): array {
            $ctx = $this->context($t, $from, $itemId);
            $marker = self::marker($key);
            $doc = $this->one('SELECT * FROM storehousechanges WHERE strc_desc LIKE ? ORDER BY id LIMIT 1', ['%' . $marker . '%']);
            if ($doc === null) {
                $current = $this->stockRow($itemId, $ctx['shop_id'], $from, $ctx['order_id']);
                if (empty($t['allow_negative']) && ($current ?? 0.0) - $qty < -0.000001) {
                    throw new RuntimeException('No hay stock suficiente de ' . $this->itemLabel($itemId) . ' en ' . $this->storeLabel($from)
                        . ($ctx['order_id'] > 0 ? ' (CC ' . $ctx['order_num'] . ')' : '') . ' para traspasar ' . self::fmt($qty) . '.');
                }
                $number = $this->nextNumber('storehouse', $ctx['company_id']);
                $now = time();
                $text = trim((string)($t['text'] ?? 'Traspaso de producción'));
                $this->erp->prepare('INSERT INTO storehousechanges
                    (strc_company_id,strc_shop_id,strc_company_dest_id,strc_shop_dest_id,strc_status,strc_date,strc_desc,strc_number,strc_crtusr,strc_crtdat,
                     strc_order_id,strc_order_id_dest,strc_order_num,strc_order_num_dest,strc_shopsent,strc_shopreceived,strc_custid,strc_rel_reqnum)
                    VALUES (?,?,?,?,1,?,?,?,?,?,?,?,?,?,0,0,0,?)')->execute([
                        $ctx['company_id'], $ctx['shop_id'], $ctx['company_id'], $ctx['shop_id'], $now, mb_substr($text, 0, 900) . ' ' . $marker,
                        $number, $ctx['user_id'], $now, $ctx['order_id'], $ctx['order_id'], $ctx['order_num'], $ctx['order_num'], $ctx['order_num']]);
                $doc = $this->one('SELECT * FROM storehousechanges WHERE id = ?', [(int)$this->erp->lastInsertId()]);
            } elseif ((int)$doc['strc_status'] === 2) {
                return ['id' => (int)$doc['id'], 'number' => (string)$doc['strc_number'], 'already' => true];
            }
            $docId = (int)$doc['id'];
            $line = $this->one('SELECT item_id, item_amount, item_st_id, item_st_dest_id FROM storehousechanges_items WHERE strc_id = ? AND item_pos = 0', [$docId]);
            if ($line === null) {
                $this->erp->prepare("INSERT INTO storehousechanges_items(strc_id,item_id,item_pos,item_amount,item_type,item_st_id,item_st_dest_id)
                    VALUES (?,?,0,?,'item',?,?)")->execute([$docId, $itemId, $qty, $from, $to]);
            } elseif ((int)$line['item_id'] !== $itemId || (int)$line['item_st_id'] !== $from || (int)$line['item_st_dest_id'] !== $to
                || abs((float)$line['item_amount'] - $qty) > 0.000001) {
                throw new RuntimeException('La clave del traspaso ' . $doc['strc_number'] . ' ya se usó para otro movimiento.');
            }
            $created = (int)$doc['strc_crtdat'];
            foreach ([[$from, 'sthdown', 0, -$qty], [$to, 'sthup', 1, $qty]] as [$st, $type, $pos, $d]) {
                $current = $this->stockRow($itemId, $ctx['shop_id'], $st, $ctx['order_id']);
                $tran = $this->one('SELECT tran_currstock FROM tran_data WHERE tran_id = ? AND tran_pos = ? AND tran_type = ?', [$docId, $pos, $type]);
                if ($tran === null) {
                    $this->erp->prepare("INSERT INTO tran_data(tran_id,tran_pos,tran_type,tran_number,tran_currstock,tran_amount,
                        tran_company_id,tran_shop_id,tran_st_id,item_id,item_title,item_type,tran_day,tran_month,tran_year,tran_crtusr,tran_crtdat,tran_order_id)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,'item',?,?,?,?,?,?)")->execute([
                            $docId, $pos, $type, (string)$doc['strc_number'], (float)($current ?? 0), $qty, $ctx['company_id'], $ctx['shop_id'], $st,
                            $itemId, $this->itemTitle($itemId), (int)date('j', $created), (int)date('n', $created), (int)date('Y', $created),
                            $ctx['user_id'], $created, $ctx['order_id']]);
                    $this->applyStock($itemId, $ctx['shop_id'], $st, $ctx['order_id'], $d, $current);
                } else {
                    $before = (float)$tran['tran_currstock'];
                    $now = (float)($current ?? 0);
                    if (abs($now - $before) < 0.000001) {
                        $this->applyStock($itemId, $ctx['shop_id'], $st, $ctx['order_id'], $d, $current);
                    } elseif (abs($now - ($before + $d)) >= 0.000001) {
                        throw new RuntimeException('El traspaso ' . $doc['strc_number'] . ' quedó a medias y el stock cambió desde entonces. Requiere revisión manual.');
                    }
                }
            }
            $this->erp->prepare('UPDATE storehousechanges SET strc_status = 2 WHERE id = ?')->execute([$docId]);
            return ['id' => $docId, 'number' => (string)$doc['strc_number'], 'already' => false];
        });
    }

    /** Stock actual de la fila exacta (null si la fila no existe). */
    public function stockRow(int $itemId, int $shopId, int $stId, int $orderId): ?float
    {
        $row = $this->one('SELECT iss_inventory FROM item_shops_storehouses WHERE item_id = ? AND shop_id = ? AND st_id = ? AND iss_order_id = ?',
            [$itemId, $shopId, $stId, $orderId]);
        return $row === null ? null : (float)$row['iss_inventory'];
    }

    /** Empresa, sucursal y CC del movimiento: desde el pedido si hay CC; si no, los valores dados o los por defecto. */
    private function context(array $m, int $stId, int $itemId): array
    {
        $orderId = (int)($m['order_id'] ?? 0);
        $companyId = (int)($m['company_id'] ?? 0);
        $shopId = (int)($m['shop_id'] ?? 0);
        $orderNum = (string)($m['order_num'] ?? '');
        if ($orderId > 0) {
            $order = $this->one('SELECT req_number, req_company_id, req_shop_id FROM orders WHERE id = ?', [$orderId]);
            if ($order === null) {
                throw new RuntimeException('No se encontró el CC ' . $orderId . ' en el ERP.');
            }
            $companyId = $companyId > 0 ? $companyId : (int)$order['req_company_id'];
            $shopId = $shopId > 0 ? $shopId : (int)$order['req_shop_id'];
            $orderNum = (string)$order['req_number'];
        } elseif ($shopId <= 0) {
            // Sin CC: la fila de stock general que ya tenga el artículo en esa bodega (de preferencia la sucursal principal).
            $row = $this->one('SELECT shop_id FROM item_shops_storehouses WHERE item_id = ? AND st_id = ? AND iss_order_id = 0
                ORDER BY CASE WHEN shop_id = ? THEN 0 ELSE 1 END, shop_id LIMIT 1', [$itemId, $stId, self::DEFAULT_SHOP_ID]);
            $shopId = (int)($row['shop_id'] ?? 0);
        }
        return [
            'order_id' => $orderId,
            'order_num' => $orderNum,
            'company_id' => $companyId > 0 ? $companyId : self::DEFAULT_COMPANY_ID,
            'shop_id' => $shopId > 0 ? $shopId : self::DEFAULT_SHOP_ID,
            'user_id' => (int)($m['user_id'] ?? 0) ?: (int)($_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? 0),
        ];
    }

    /** Siguiente número de documento (IA0000000 / TR0000000), con 7 dígitos como el ERP. */
    private function nextNumber(string $kind, int $companyId): string
    {
        $format = 'company_numformat_' . $kind;
        $counterCol = 'company_numcounter_' . $kind;
        $this->erp->prepare("UPDATE company_data SET $counterCol = $counterCol + 1 WHERE id = ?")->execute([$companyId]);
        $row = $this->one("SELECT $format AS f, $counterCol AS c FROM company_data WHERE id = ?", [$companyId]);
        if ($row === null) {
            throw new RuntimeException('No existe el contador de documentos de la empresa ' . $companyId . '.');
        }
        $prefix = trim((string)$row['f']) ?: ($kind === 'stockchange' ? 'IA' : 'TR');
        return $prefix . sprintf('%07d', (int)$row['c']);
    }

    private function itemTitle(int $itemId): string
    {
        $row = $this->one('SELECT item_title FROM item WHERE id = ?', [$itemId]);
        return (string)($row['item_title'] ?? '');
    }

    private function itemLabel(int $itemId): string
    {
        try {
            $row = $this->one('SELECT item_number_prod, item_title FROM item WHERE id = ?', [$itemId]);
        } catch (Throwable) {
            $row = $this->one('SELECT item_title FROM item WHERE id = ?', [$itemId]);
        }
        $sku = trim((string)($row['item_number_prod'] ?? ''));
        return $sku !== '' ? $sku : trim((string)($row['item_title'] ?? ('artículo ' . $itemId)));
    }

    private function storeLabel(int $stId): string
    {
        $row = $this->one('SELECT st_name FROM company_shops_storehouses WHERE id = ?', [$stId]);
        return 'la bodega ' . trim((string)($row['st_name'] ?? $stId));
    }

    public static function fmt(float $n): string
    {
        return number_format($n, abs($n - round($n)) < 0.0005 ? 0 : 3, ',', '.');
    }
}
