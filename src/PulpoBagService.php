<?php

declare(strict_types=1);

require_once __DIR__ . '/MachineKind.php';
require_once __DIR__ . '/StockLedgerService.php';
require_once __DIR__ . '/BagBoxService.php';
require_once __DIR__ . '/MonthlyPresentationService.php';

/**
 * Bolsas que usa el Pulpo Serigráfico, por caja con código.
 *
 * 1. El operador escanea la caja (ya traspasada a la Bodega 3000 por Solicitudes MP): queda «en máquina».
 * 2. Cuando termina de usarla, o al cerrar, indica cuántas bolsas usó: la caja baja ese saldo y el inventario del SKU
 *    (con su CC) baja lo mismo en la Bodega 3000, con documento y kardex. Si quedan bolsas, la caja sigue en uso.
 * 3. Un consumo mal registrado se anula: vuelve el saldo a la caja y el stock, con su documento de reversa.
 *
 * Cada movimiento queda en el diario box_unit_moves (TRZ). El ERP es MyISAM: primero se anota el movimiento como
 * PENDIENTE, luego se registra el stock (idempotente por clave) y al final se marca HECHO; un corte se retoma solo.
 */
final class PulpoBagService
{
    private PDO $erp;
    private PDO $trz;
    private BagBoxService $boxes;
    private StockLedgerService $ledger;
    private bool $sqlite;

    public function __construct(PDO $erp, PDO $trz)
    {
        $this->erp = $erp;
        $this->trz = $trz;
        $this->boxes = new BagBoxService($trz, $erp);
        $this->ledger = new StockLedgerService($erp);
        $this->sqlite = $trz->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    private function one(PDO $pdo, string $sql, array $params = []): ?array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Caras que imprime la OT (frente y/o dorso), con los 10 colores. Un color con descripción pero sin marcar cuenta como frente.
     *
     * @return array{front:bool,back:bool}
     */
    public static function printSides(PDO $erp, int $agId): array
    {
        $cols = [];
        for ($i = 1; $i <= 10; $i++) {
            $cols[] = "oi.fab_print_colors_front_$i, oi.fab_print_colors_back_$i, oi.fab_print_colordesc_$i";
        }
        $stmt = $erp->prepare('SELECT ' . implode(', ', $cols) . ' FROM prod_agenda ag JOIN orders_items oi ON oi.req_id = ag.ag_reqid WHERE ag.id = ? LIMIT 1');
        $stmt->execute([$agId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $front = $back = false;
        for ($i = 1; $i <= 10; $i++) {
            $f = (int)($row["fab_print_colors_front_$i"] ?? 0) > 0;
            $b = (int)($row["fab_print_colors_back_$i"] ?? 0) > 0;
            $front = $front || $f || (!$f && !$b && trim((string)($row["fab_print_colordesc_$i"] ?? '')) !== '');
            $back = $back || $b;
        }
        return ['front' => $front || !$back, 'back' => $back];
    }

    /** Bolsas terminadas acumuladas: con frente y dorso, las que tienen ambas caras; con una sola cara, las pasadas de esa cara. */
    public static function finishedBags(float $frente, float $dorso, bool $hasFront, bool $hasBack): float
    {
        if ($hasFront && $hasBack) {
            return min($frente, $dorso);
        }
        return $hasBack ? $dorso : $frente;
    }

    /** OT abierta en un Pulpo Serigráfico. */
    public function context(int $otId): array
    {
        $row = $this->one($this->erp, 'SELECT ot.id AS ot_id, ot.wok_status, ot.wok_ag_id AS agenda_id, ag.ag_reqid AS order_id,
                wi.win_equipoid, eq.equipo_name, eq.equipo_type_id, eq.equipo_prod_isprinter_seri
            FROM prod_worker_ot ot JOIN prod_worker_init wi ON wi.id = ot.wok_init_id
            JOIN prod_agenda ag ON ag.id = ot.wok_ag_id LEFT JOIN equipo eq ON eq.id = wi.win_equipoid WHERE ot.id = ?', [$otId]);
        if ($row === null || (int)$row['wok_status'] !== 1) {
            throw new RuntimeException('La OT debe estar abierta.');
        }
        if (!MachineKind::isPulpo((int)$row['win_equipoid'], (string)$row['equipo_name'], (int)$row['equipo_type_id'], (int)$row['equipo_prod_isprinter_seri'] === 1)) {
            throw new RuntimeException('Las cajas de bolsas se usan en el Pulpo Serigráfico.');
        }
        return $row;
    }

    /** Escanear una caja en la máquina: debe estar en la Bodega 3000 y con bolsas. */
    public function mount(int $otId, string $code, string $operator): array
    {
        $ctx = $this->context($otId);
        $this->boxes->ensureSchema();
        $this->trz->beginTransaction();
        try {
            $box = $this->boxes->find($code, true);
            if ($box === null) {
                throw new RuntimeException('El código ' . trim($code) . ' no es una caja de bolsas registrada.');
            }
            if (!BagBoxService::isErpLinked($box)) {
                throw new RuntimeException('La caja ' . $box['box_code'] . ' no tiene su artículo y bodega del ERP: pide a bodega que la etiquete con el ingreso trazable.');
            }
            if ((string)$box['status'] === 'IN_MACHINE' && (int)$box['current_worker_ot_id'] === $otId) {
                $this->trz->commit();
                return $box + ['already' => true];
            }
            if ((string)$box['status'] === 'IN_MACHINE') {
                throw new RuntimeException('La caja ' . $box['box_code'] . ' está en uso en otra OT. Primero bájala de esa máquina.');
            }
            if ((string)$box['status'] !== 'IN_WAREHOUSE' || (float)$box['units_qty'] <= 0) {
                throw new RuntimeException('La caja ' . $box['box_code'] . ' no tiene bolsas disponibles (estado ' . $box['status'] . ').');
            }
            if ((int)$box['erp_st_id'] !== 16) {
                throw new RuntimeException('La caja ' . $box['box_code'] . ' sigue en la bodega ' . $box['erp_st_id']
                    . '. Pídela por Solicitudes MP para que bodega la traspase a 3000 Insumos en Producción.');
            }
            $this->boxes->update((int)$box['id'], ['status' => 'IN_MACHINE', 'current_worker_ot_id' => $otId]);
            $this->boxes->movement((int)$box['id'], 'ADJUSTMENT', null, null, ['mounted_on_ot' => $otId, 'operator' => $operator, 'machine' => $ctx['equipo_name']]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) {
                $this->trz->rollBack();
            }
            throw $e;
        }
        return $this->boxes->byId((int)$box['id']) + ['already' => false];
    }

    /** Bajar la caja de la máquina sin usar más bolsas: queda disponible en la Bodega 3000 con su saldo. */
    public function unmount(int $otId, int $boxId, string $operator): void
    {
        $this->context($otId);
        $this->trz->beginTransaction();
        try {
            $box = $this->boxes->byId($boxId, true);
            if ($box === null || (string)$box['status'] !== 'IN_MACHINE' || (int)$box['current_worker_ot_id'] !== $otId) {
                throw new RuntimeException('La caja no está en esta máquina.');
            }
            $this->boxes->update($boxId, ['status' => (float)$box['units_qty'] > 0 ? 'IN_WAREHOUSE' : 'CONSUMED', 'current_worker_ot_id' => null]);
            $this->boxes->movement($boxId, 'ADJUSTMENT', null, null, ['unmounted_from_ot' => $otId, 'operator' => $operator]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) {
                $this->trz->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Registrar las bolsas usadas de una caja en máquina. $opKey (32 hex) viene del formulario: repetir el envío no descuenta dos veces.
     *
     * @return array{move_id:int,units_after:float,warning:string}
     */
    public function consume(int $otId, int $boxId, float $qty, string $operator, string $opKey): array
    {
        $ctx = $this->context($otId);
        if (!preg_match('/^[a-f0-9]{32}$/D', $opKey)) {
            throw new RuntimeException('Confirmación inválida; recarga la pantalla.');
        }
        $qty = round($qty, 3);
        if (!is_finite($qty) || $qty <= 0 || floor($qty) !== $qty) {
            throw new RuntimeException('Indica una cantidad entera de bolsas mayor a cero.');
        }
        $this->boxes->ensureSchema();
        $this->trz->beginTransaction();
        try {
            $existing = $this->one($this->trz, "SELECT * FROM box_unit_moves WHERE erp_ledger_key = ?", ['bag.' . $opKey]);
            if ($existing !== null) {
                $this->trz->commit();
                return $this->finish((int)$existing['id']);
            }
            $box = $this->boxes->byId($boxId, true);
            if ($box === null || (string)$box['status'] !== 'IN_MACHINE' || (int)$box['current_worker_ot_id'] !== $otId) {
                throw new RuntimeException('La caja no está en esta máquina. Escanéala primero.');
            }
            if ($qty > (float)$box['units_qty'] + 0.0005) {
                throw new RuntimeException('La caja ' . $box['box_code'] . ' tiene ' . StockLedgerService::fmt((float)$box['units_qty']) . ' bolsas; no se pueden usar ' . StockLedgerService::fmt($qty) . '.');
            }
            $pending = $this->one($this->trz, "SELECT id FROM box_unit_moves WHERE box_id = ? AND state = 'PENDING' LIMIT 1", [$boxId]);
            if ($pending !== null) {
                throw new RuntimeException('Esta caja tiene un movimiento pendiente. Usa «Reintentar pendientes» antes de registrar otro.');
            }
            $after = round((float)$box['units_qty'] - $qty, 3);
            $this->trz->prepare("INSERT INTO box_unit_moves (box_id, kind, qty, units_before, units_after, erp_st_id, erp_ledger_key, worker_ot_id, agenda_id,
                    state, operator_name, created_at) VALUES (?, 'CONSUME', ?, ?, ?, 16, ?, ?, ?, 'PENDING', ?, ?)")
                ->execute([$boxId, $qty, (float)$box['units_qty'], $after, 'bag.' . $opKey, $otId, (int)$ctx['agenda_id'], mb_substr($operator, 0, 120), time()]);
            $moveId = (int)$this->trz->lastInsertId();
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) {
                $this->trz->rollBack();
            }
            throw $e;
        }
        return $this->finish($moveId);
    }

    /** Anular un consumo: vuelve el saldo a la caja y el stock a la Bodega 3000, con su documento de reversa. */
    public function reverse(int $otId, int $moveId, string $operator, string $reason): array
    {
        $this->context($otId);
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new RuntimeException('Indica el motivo de la anulación.');
        }
        $this->boxes->ensureSchema();
        $this->trz->beginTransaction();
        try {
            $move = $this->one($this->trz, 'SELECT * FROM box_unit_moves WHERE id = ?' . ($this->sqlite ? '' : ' FOR UPDATE'), [$moveId]);
            if ($move === null || (string)$move['kind'] !== 'CONSUME' || (int)$move['worker_ot_id'] !== $otId) {
                throw new RuntimeException('El consumo no pertenece a esta OT.');
            }
            $existing = $this->one($this->trz, "SELECT id FROM box_unit_moves WHERE original_move_id = ? AND kind = 'RETURN'", [$moveId]);
            if ($existing !== null) {
                $this->trz->commit();
                return $this->finish((int)$existing['id']);
            }
            if ((string)$move['state'] !== 'DONE') {
                throw new RuntimeException('Solo se anula un consumo terminado.');
            }
            $box = $this->boxes->byId((int)$move['box_id'], true);
            if ($box === null || in_array((string)$box['status'], ['DISPATCHED', 'VOID'], true) || (int)$box['erp_st_id'] !== 16) {
                throw new RuntimeException('La caja ya no está en la Bodega 3000; no se puede devolver el consumo.');
            }
            $before = (float)$box['units_qty'];
            $this->trz->prepare("INSERT INTO box_unit_moves (box_id, kind, qty, units_before, units_after, erp_st_id, erp_ledger_key, worker_ot_id, agenda_id,
                    original_move_id, state, operator_name, note, created_at) VALUES (?, 'RETURN', ?, ?, ?, 16, ?, ?, ?, ?, 'PENDING', ?, ?, ?)")
                ->execute([(int)$move['box_id'], (float)$move['qty'], $before, round($before + (float)$move['qty'], 3), (string)$move['erp_ledger_key'] . '.R',
                    $otId, (int)$move['agenda_id'], $moveId, mb_substr($operator, 0, 120), $reason, time()]);
            $returnId = (int)$this->trz->lastInsertId();
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) {
                $this->trz->rollBack();
            }
            throw $e;
        }
        return $this->finish($returnId);
    }

    /** Registra el stock del movimiento (idempotente) y aplica el saldo a la caja. */
    private function finish(int $moveId): array
    {
        $move = $this->one($this->trz, 'SELECT * FROM box_unit_moves WHERE id = ?', [$moveId]);
        if ($move === null) {
            throw new RuntimeException('Movimiento de caja no encontrado.');
        }
        $box = $this->boxes->byId((int)$move['box_id']);
        if ((string)$move['state'] === 'DONE') {
            return ['move_id' => $moveId, 'units_after' => (float)$box['units_qty'], 'warning' => ''];
        }
        $warning = '';
        try {
            if ((string)$move['kind'] === 'CONSUME') {
                $posted = $this->ledger->post(['key' => (string)$move['erp_ledger_key'], 'item_id' => (int)$box['erp_item_id'], 'st_id' => 16,
                    'delta' => -(float)$move['qty'], 'order_id' => (int)($box['erp_order_id'] ?? 0), 'allow_negative' => true,
                    'text' => 'Bolsas usadas en Pulpo Serigráfico, caja ' . $box['box_code'] . ', OT ' . $move['worker_ot_id']
                        . ((string)$move['operator_name'] !== '' ? ', ' . $move['operator_name'] : '') . '.']);
                if ($posted['stock_after'] < -0.0005) {
                    $warning = 'El stock de ' . $box['final_sku'] . ' en Bodega 3000 quedó negativo (' . StockLedgerService::fmt($posted['stock_after'])
                        . '): avisa a bodega para revisar el traspaso de la caja.';
                }
            } else {
                $original = $this->one($this->trz, 'SELECT erp_ledger_key FROM box_unit_moves WHERE id = ?', [(int)$move['original_move_id']]);
                $this->ledger->reverse((string)$original['erp_ledger_key'], 'consumo de bolsas anulado: ' . (string)$move['note']);
            }
        } catch (Throwable $e) {
            $this->trz->prepare('UPDATE box_unit_moves SET error_message = ? WHERE id = ?')->execute([mb_substr($e->getMessage(), 0, 1000), $moveId]);
            throw new RuntimeException('El movimiento quedó pendiente; usa «Reintentar pendientes». ' . $e->getMessage(), 0, $e);
        }
        $this->trz->beginTransaction();
        try {
            $box = $this->boxes->byId((int)$move['box_id'], true);
            $current = (float)$box['units_qty'];
            if (abs($current - (float)$move['units_before']) > 0.0005 && abs($current - (float)$move['units_after']) > 0.0005) {
                throw new RuntimeException('El saldo de la caja ' . $box['box_code'] . ' cambió durante el movimiento. Requiere revisión.');
            }
            $after = (float)$move['units_after'];
            $status = (string)$box['status'];
            if ((string)$move['kind'] === 'CONSUME' && $after <= 0.0005) {
                $status = 'CONSUMED';
            } elseif ((string)$move['kind'] === 'RETURN' && $status === 'CONSUMED') {
                $status = 'IN_WAREHOUSE';
            }
            $this->boxes->update((int)$box['id'], ['units_qty' => $after, 'status' => $status,
                'current_worker_ot_id' => $status === 'IN_MACHINE' ? (int)$box['current_worker_ot_id'] : null]);
            $this->boxes->movement((int)$box['id'], 'ADJUSTMENT', null, null, ['kind' => $move['kind'], 'qty' => (float)$move['qty'], 'units_after' => $after,
                'ot' => (int)$move['worker_ot_id'], 'ledger_key' => $move['erp_ledger_key']]);
            if ((string)$move['kind'] === 'RETURN') {
                $this->trz->prepare("UPDATE box_unit_moves SET state = 'REVERSED' WHERE id = ?")->execute([(int)$move['original_move_id']]);
            }
            $this->trz->prepare("UPDATE box_unit_moves SET state = 'DONE', error_message = NULL, done_at = ? WHERE id = ?")->execute([time(), $moveId]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) {
                $this->trz->rollBack();
            }
            throw $e;
        }
        // Bitácora de la OT (se ve en el historial de materiales).
        try {
            $text = ((string)$move['kind'] === 'CONSUME' ? 'Bolsas usadas: ' : 'Anulación bolsas usadas: ') . StockLedgerService::fmt((float)$move['qty'])
                . ' de la caja ' . $box['box_code'] . ' (' . $box['final_sku'] . ($box['customer_order_ref'] ? ', CC ' . $box['customer_order_ref'] : '')
                . '). Quedan ' . StockLedgerService::fmt((float)$move['units_after']) . ' en la caja. [TRZ-BAG:' . $moveId . ']';
            $now = time();
            $this->erp->prepare("INSERT INTO prod_worker_ot_events (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                VALUES (?, ?, ?, ?, 2, 'materiales', ?)")->execute([(int)$move['worker_ot_id'], (string)$move['kind'] === 'CONSUME' ? (float)$move['qty'] : 0, $now, $now, $text]);
        } catch (Throwable $e) {
            error_log('Bitácora de bolsas usadas (movimiento ' . $moveId . '): ' . $e->getMessage());
        }
        return ['move_id' => $moveId, 'units_after' => (float)$move['units_after'], 'warning' => $warning];
    }

    /** Retoma los movimientos que quedaron pendientes en esta OT. */
    public function resumePending(int $otId): int
    {
        $this->boxes->ensureSchema();
        $stmt = $this->trz->prepare("SELECT id FROM box_unit_moves WHERE worker_ot_id = ? AND state = 'PENDING' ORDER BY id");
        $stmt->execute([$otId]);
        $done = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $this->finish((int)$id);
            $done++;
        }
        return $done;
    }

    /** Partes de caja con código temporal (de un traspaso con saldo) que esperan en la 3000 para cargarse sin escanear. */
    public function waitingPartials(): array
    {
        $this->boxes->ensureSchema();
        $stmt = $this->trz->prepare("SELECT * FROM boxes WHERE status = 'IN_WAREHOUSE' AND erp_st_id = 16 AND source_kind = ? AND units_qty > 0 ORDER BY id");
        $stmt->execute([BagBoxService::SOURCE_PARTIAL]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function mountedBoxes(int $otId): array
    {
        $this->boxes->ensureSchema();
        $stmt = $this->trz->prepare("SELECT * FROM boxes WHERE status = 'IN_MACHINE' AND current_worker_ot_id = ? ORDER BY id");
        $stmt->execute([$otId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function moves(int $agendaId): array
    {
        $this->boxes->ensureSchema();
        $stmt = $this->trz->prepare('SELECT m.*, b.box_code, b.final_sku, b.customer_order_ref FROM box_unit_moves m JOIN boxes b ON b.id = m.box_id
            WHERE m.agenda_id = ? ORDER BY m.id DESC LIMIT 200');
        $stmt->execute([$agendaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Bolsas usadas (netas de anulaciones) en todas las OT de la agenda. */
    public function consumedForAgenda(int $agendaId): float
    {
        $this->boxes->ensureSchema();
        $row = $this->one($this->trz, "SELECT COALESCE(SUM(qty),0) AS q FROM box_unit_moves WHERE agenda_id = ? AND kind = 'CONSUME' AND state = 'DONE'", [$agendaId]);
        return (float)($row['q'] ?? 0);
    }

    /**
     * Bolsas que la producción necesitó: las que recibieron frente o dorso (la mayor de las dos) más la merma registrada.
     *
     * Con pasadas de frente/dorso se usan solo esas: el sistema antiguo guardaba en evt_amount las pasadas de cada
     * evento (frente + dorso = la misma bolsa dos veces) y el nuevo las bolsas terminadas. Sin pasadas, evt_amount.
     * La merma del Pulpo casi siempre se anota solo en kg (evt_amount 0): esos kg se pasan a bolsas con el peso de la
     * bolsa de la CC (artículo o fórmula de la ficha). Si no hay peso, esos kg quedan en merma_kg_sin_convertir.
     *
     * @return array{printed:float,merma:float,needed:float,merma_kg_convertidos:float,merma_kg_sin_convertir:float}
     */
    public function neededForAgenda(int $agendaId): array
    {
        $row = $this->one($this->erp, "SELECT COALESCE(SUM(e.evt_amount_pulpo_frente),0) AS f, COALESCE(SUM(e.evt_amount_pulpo_dorso),0) AS d,
                COALESCE(SUM(e.evt_amount),0) AS a
            FROM prod_worker_ot ot JOIN prod_worker_ot_events e ON e.evt_prod_worker_otid = ot.id
            WHERE ot.wok_ag_id = ? AND e.evt_type = 'prod' AND e.evt_status > 0", [$agendaId]) ?? [];
        $f = (float)($row['f'] ?? 0);
        $d = (float)($row['d'] ?? 0);
        $printed = ($f > 0 || $d > 0) ? max($f, $d) : (float)($row['a'] ?? 0);
        $merma = $this->one($this->erp, "SELECT COALESCE(SUM(d.evt_amount),0) AS m,
                COALESCE(SUM(CASE WHEN d.evt_amount > 0 THEN 0 ELSE d.evt_kgstounits END),0) AS kg
            FROM prod_worker_ot_defectunits d
            JOIN prod_worker_ot_events e ON e.id = d.evt_refid JOIN prod_worker_ot ot ON ot.id = e.evt_prod_worker_otid
            WHERE ot.wok_ag_id = ? AND d.evt_status > 0 AND d.evt_type = 'merma'", [$agendaId]);
        $m = (float)($merma['m'] ?? 0);
        $kgOnly = (float)($merma['kg'] ?? 0);
        $kgConverted = 0.0;
        if ($kgOnly > 0) {
            $weight = $this->bagWeightKg($agendaId);
            if ($weight > 0) {
                $m += round($kgOnly / $weight);
                $kgConverted = $kgOnly;
                $kgOnly = 0.0;
            }
        }
        return ['printed' => $printed, 'merma' => $m, 'needed' => $printed + $m,
            'merma_kg_convertidos' => $kgConverted, 'merma_kg_sin_convertir' => $kgOnly];
    }

    /** Peso de una bolsa de la CC de la agenda, en kg (0 si no se puede calcular). */
    private function bagWeightKg(int $agendaId): float
    {
        try {
            $r = $this->one($this->erp, 'SELECT it.item_weight, oi.fab_med_width, oi.fab_med_height, oi.fab_med_fuelle, oi.fab_mat_gramms, oi.fab_manilla_length
                FROM prod_agenda ag JOIN orders_items oi ON oi.req_id = ag.ag_reqid LEFT JOIN item it ON it.id = oi.item_id
                WHERE ag.id = ? ORDER BY oi.item_pos LIMIT 1', [$agendaId]);
        } catch (Throwable) {
            return 0.0;
        }
        return $r === null ? 0.0 : MonthlyPresentationService::unitWeightKg($r);
    }

    /**
     * Antes de terminar la OT del Pulpo: no puede haber movimientos pendientes, y las bolsas usadas deben cuadrar con
     * las impresas más la merma. Si no cuadran, solo se puede cerrar confirmando la diferencia con un motivo (el cierre
     * ya pide la clave del supervisor). Las cajas que siguen en la máquina se bajan a la Bodega 3000 con su saldo.
     */
    public function assertReadyToClose(int $otId, bool $confirmDifference, string $reason, string $operator): void
    {
        $ctx = $this->context($otId);
        $this->boxes->ensureSchema();
        if ($this->one($this->trz, "SELECT id FROM box_unit_moves WHERE worker_ot_id = ? AND state = 'PENDING' LIMIT 1", [$otId]) !== null) {
            throw new RuntimeException('Hay movimientos de bolsas pendientes. Usa «Reintentar pendientes» en Materiales antes de terminar la OT.');
        }
        $need = $this->neededForAgenda((int)$ctx['agenda_id']);
        $used = $this->consumedForAgenda((int)$ctx['agenda_id']);
        $diff = round($used - $need['needed'], 3);
        if (abs($diff) >= 1 && $need['needed'] + $used > 0) {
            $text = $diff < 0
                ? 'Faltan registrar ' . StockLedgerService::fmt(-$diff) . ' bolsas usadas: se imprimieron ' . StockLedgerService::fmt($need['printed'])
                    . ' y hubo ' . StockLedgerService::fmt($need['merma']) . ' de merma, pero se registraron ' . StockLedgerService::fmt($used) . ' bolsas usadas de las cajas.'
                : 'Se registraron ' . StockLedgerService::fmt($diff) . ' bolsas usadas de más: se imprimieron ' . StockLedgerService::fmt($need['printed'])
                    . ' y hubo ' . StockLedgerService::fmt($need['merma']) . ' de merma, pero se registraron ' . StockLedgerService::fmt($used) . '.';
            if (!$confirmDifference || mb_strlen(trim($reason)) < 10) {
                throw new RuntimeException($text . ' Corrige las bolsas usadas en Materiales, o marca «Confirmo la diferencia de bolsas» y escribe el motivo (mínimo 10 caracteres).');
            }
            $now = time();
            $this->erp->prepare("INSERT INTO prod_worker_ot_events (evt_prod_worker_otid, evt_amount, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                VALUES (?, 0, ?, ?, 2, 'materiales', ?)")->execute([$otId, $now, $now, 'Diferencia de bolsas confirmada al cerrar: ' . $text . ' Motivo: ' . trim($reason) . ' (' . $operator . ')']);
        }
        foreach ($this->mountedBoxes($otId) as $box) {
            $this->unmount($otId, (int)$box['id'], $operator);
        }
    }
}
