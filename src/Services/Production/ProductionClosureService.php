<?php

declare(strict_types=1);

// =============================================================================
// Servicio de Datos · Cierre de Producción, Validación de Supervisor y Balance CC
// (src/Services/Production/ProductionClosureService.php)
// =============================================================================

require_once dirname(__DIR__, 2) . '/MachineKind.php';
require_once dirname(__DIR__, 2) . '/StockLedgerService.php';
require_once dirname(__DIR__, 2) . '/BagBoxService.php';
require_once dirname(__DIR__, 2) . '/PulpoBagService.php';
trait ProductionClosureServiceTrait
{
    /**
     * Valida que el supervisor tenga credenciales activas y pertenezca al rol supervisor (grupo 31) o admin (user_type = 1).
     */
    public function validateSupervisor(string $username, string $password): bool
    {
        $username = trim($username);
        $password = trim($password);
        if ($username === '' || $password === '') {
            return false;
        }

        $sql = "SELECT t1.id, t1.user_type
                FROM user t1
                WHERE t1.user_login = :login
                  AND t1.user_pass = :pass
                  AND t1.user_status = 1
                  AND (
                      t1.user_type = 1
                      OR (
                          SELECT count(*)
                          FROM user_group t2
                          WHERE t2.user_id = t1.id
                            AND t2.group_id IN (31)
                      ) > 0
                  )
                LIMIT 1";

        try {
            $stmt = $this->erpPdo->prepare($sql);
            $stmt->execute([
                ':login' => $username,
                ':pass' => md5($password),
            ]);
            $row = $stmt->fetch();
            return is_array($row) && (int)$row['id'] > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Sincroniza y guarda en `prod_agenda` el balance acumulado de Serigrafía Pulpo:
     * - Total pasadas Frente
     * - Total pasadas Dorso
     * - Bolsas terminadas completas
     * - Saldo de bolsas semi-terminadas pendientes (Frente o Dorso)
     */
    public function syncPulpoAgendaStats(int $agId): array
    {
        if ($agId <= 0) {
            return ['ok' => false, 'error' => 'ID de agenda inválido.'];
        }

        try {
            // Verificar o asegurar que las columnas existan en prod_agenda
            try {
                $chk = $this->erpPdo->query("SHOW COLUMNS FROM prod_agenda LIKE 'pulpo_surplus_frente'")->fetch();
                if (!$chk) {
                    $this->erpPdo->exec("ALTER TABLE prod_agenda ADD COLUMN pulpo_surplus_frente DECIMAL(30,2) DEFAULT 0");
                    $this->erpPdo->exec("ALTER TABLE prod_agenda ADD COLUMN pulpo_surplus_dorso DECIMAL(30,2) DEFAULT 0");
                    $this->erpPdo->exec("ALTER TABLE prod_agenda ADD COLUMN pulpo_tot_frente DECIMAL(30,2) DEFAULT 0");
                    $this->erpPdo->exec("ALTER TABLE prod_agenda ADD COLUMN pulpo_tot_dorso DECIMAL(30,2) DEFAULT 0");
                    $this->erpPdo->exec("ALTER TABLE prod_agenda ADD COLUMN pulpo_finished_bags DECIMAL(30,2) DEFAULT 0");
                }
            } catch (Throwable) {}

            $stmt = $this->erpPdo->prepare(
                "SELECT SUM(t3.evt_amount) AS tot_amt,
                        SUM(t3.evt_amount_pulpo_frente) AS tot_frente,
                        SUM(t3.evt_amount_pulpo_dorso) AS tot_dorso
                 FROM prod_worker_ot t2
                 INNER JOIN prod_worker_ot_events t3 ON t3.evt_prod_worker_otid = t2.id
                 WHERE t2.wok_ag_id = :ag_id
                   AND t3.evt_type = 'prod'
                   AND t3.evt_status > 0"
            );
            $stmt->execute([':ag_id' => $agId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $totFrente = (float)($row['tot_frente'] ?? 0);
            $totDorso = (float)($row['tot_dorso'] ?? 0);
            $totAmt = (float)($row['tot_amt'] ?? 0);

            // Caras que imprime la OT (10 colores). Antes se miraban solo 5 colores de dorso y una OT de solo dorso daba 0 terminadas.
            $sides = PulpoBagService::printSides($this->erpPdo, $agId);
            $finished = PulpoBagService::finishedBags($totFrente, $totDorso, $sides['front'], $sides['back'] || $totDorso > 0);
            $surplusFrente = max(0.0, $totFrente - $finished);
            $surplusDorso = max(0.0, $totDorso - $finished);
            if (!$sides['front']) {
                $surplusFrente = 0.0;
            }
            if (!$sides['back'] && $totDorso <= 0) {
                $surplusDorso = 0.0;
            }

            $stmtUpd = $this->erpPdo->prepare(
                "UPDATE prod_agenda
                 SET pulpo_tot_frente = :tf,
                     pulpo_tot_dorso = :td,
                     pulpo_finished_bags = :fin,
                     pulpo_surplus_frente = :sf,
                     pulpo_surplus_dorso = :sd
                 WHERE id = :ag_id"
            );
            $stmtUpd->execute([
                ':tf' => $totFrente,
                ':td' => $totDorso,
                ':fin' => $finished,
                ':sf' => $surplusFrente,
                ':sd' => $surplusDorso,
                ':ag_id' => $agId,
            ]);

            return [
                'ok' => true,
                'tot_frente' => $totFrente,
                'tot_dorso' => $totDorso,
                'finished' => $finished,
                'surplus_frente' => $surplusFrente,
                'surplus_dorso' => $surplusDorso,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Determina si una orden de trabajo (prod_worker_ot) ha iniciado producción real.
     * Retorna false si la OT fue abierta por error y aún no registra eventos ni cantidades producidas,
     * permitiendo al operador cerrarla directamente sin autorización de supervisor.
     */
    public function hasWorkOrderProductionStarted(int $workerOtId): bool
    {
        if ($workerOtId <= 0) {
            return false;
        }

        try {
            // 1. Verificar si existen eventos de producción cerrados o con producción registrada
            $stmtProd = $this->erpPdo->prepare("
                SELECT COUNT(*)
                FROM prod_worker_ot_events
                WHERE (evt_prod_worker_otid = :ot_id OR (overrideembalaje_act = 1 AND overrideembalaje_selladora_evtrefid = :linked_ot))
                  AND evt_type IN ('prod', 'prodsericolor')
                  AND evt_status > 0
                  AND (
                      evt_status = 2
                      OR evt_amount > 0
                      OR prod_bobina_kg > 0
                      OR evt_amount_metros_lineales > 0
                      OR evt_amount_metros_maquina > 0
                  )
            ");
            $stmtProd->execute([':ot_id' => $workerOtId, ':linked_ot' => $workerOtId]);
            if ((int)$stmtProd->fetchColumn() > 0) {
                return true;
            }

            // 2. Verificar si existen bobinas semielaboradas / impresas emitidas
            $prSummary = $this->getPrintedRollsSummary($workerOtId);
            if (!empty($prSummary['rolls_count']) && (int)$prSummary['rolls_count'] > 0) {
                return true;
            }

            // 3. Verificar si existen mermas registradas
            $stmtMermas = $this->erpPdo->prepare("
                SELECT COUNT(*)
                FROM prod_worker_ot_defectunits d
                INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                WHERE e.evt_prod_worker_otid = :ot_id
                  AND d.evt_status > 0
                  AND (d.evt_amount > 0 OR d.evt_kgstounits > 0 OR d.evt_mtstounits > 0)
            ");
            $stmtMermas->execute([':ot_id' => $workerOtId]);
            if ((int)$stmtMermas->fetchColumn() > 0) {
                return true;
            }

            // 4. Verificar si existe algún evento de producción abierto (evt_status = 1)
            $stmtOpenProd = $this->erpPdo->prepare("
                SELECT COUNT(*)
                FROM prod_worker_ot_events
                WHERE evt_prod_worker_otid = :ot_id
                  AND evt_type IN ('prod', 'prodsericolor')
                  AND evt_status = 1
            ");
            $stmtOpenProd->execute([':ot_id' => $workerOtId]);
            if ((int)$stmtOpenProd->fetchColumn() > 0) {
                return true;
            }
        } catch (Throwable) {
            return true;
        }

        return false;
    }

    /**
     * Finaliza la OT. Si ya se inició producción, requiere validación positiva de supervisor (`wok_status = 2`, `wok_enddat = time()`).
     * Si no se ha iniciado producción (p. ej. apertura por error), permite el término directo sin supervisor.
     */
    public function closeWorkOrder(int $workerOtId, string $supervisorUser = '', string $supervisorPass = '',
        bool $confirmBagDifference = false, string $bagDifferenceReason = ''): array
    {
        if ($workerOtId <= 0) {
            return ['ok' => false, 'error' => 'ID de OT inválido.'];
        }

        $prodStarted = $this->hasWorkOrderProductionStarted($workerOtId);

        if ($prodStarted) {
            if (!$this->validateSupervisor($supervisorUser, $supervisorPass)) {
                return ['ok' => false, 'error' => 'Usuario o contraseña de supervisor errónea.'];
            }

            try {
                $this->assertNoOpenProductionPhases($workerOtId);
            } catch (Throwable $e) {
                return ['ok'=>false, 'error'=>$e->getMessage()];
            }

            if ($this->trzPdo !== null) {
                try {
                    require_once dirname(__DIR__, 2) . '/HandleConsumptionService.php';
                    (new HandleConsumptionService($this->erpPdo, $this->trzPdo))->assertNoPendingForWorkOrder($workerOtId);
                    require_once dirname(__DIR__, 2) . '/MaterialSupplyService.php';
                    (new MaterialSupplyService($this->erpPdo, $this->trzPdo))->assertNoOutstandingReceipts($workerOtId);
                    // Pulpo: las bolsas usadas de las cajas deben cuadrar con las impresas más la merma.
                    $machine = $this->erpPdo->prepare('SELECT wi.win_equipoid, eq.equipo_name, eq.equipo_type_id, eq.equipo_prod_isprinter_seri
                        FROM prod_worker_ot ot JOIN prod_worker_init wi ON wi.id = ot.wok_init_id LEFT JOIN equipo eq ON eq.id = wi.win_equipoid WHERE ot.id = ?');
                    $machine->execute([$workerOtId]);
                    $m = $machine->fetch(PDO::FETCH_ASSOC) ?: [];
                    if (MachineKind::isPulpo((int)($m['win_equipoid'] ?? 0), (string)($m['equipo_name'] ?? ''), (int)($m['equipo_type_id'] ?? 0), (int)($m['equipo_prod_isprinter_seri'] ?? 0) === 1)) {
                        (new PulpoBagService($this->erpPdo, $this->trzPdo))->assertReadyToClose($workerOtId, $confirmBagDifference, $bagDifferenceReason,
                            trim($supervisorUser) !== '' ? 'supervisor ' . trim($supervisorUser) : 'operador');
                    }
                } catch (Throwable $e) {
                    return ['ok' => false, 'error' => $e->getMessage()];
                }
            }
        }

        $now = time();
        $stmt = $this->erpPdo->prepare(
            "UPDATE prod_worker_ot
             SET wok_status = 2, wok_enddat = :enddat
             WHERE id = :id"
        );
        $stmt->execute([':enddat' => $now, ':id' => $workerOtId]);

        try {
            // Consolidar mermas de desmontaje en el evento de producción si aún no se vincularon
            $stmtLastProd = $this->erpPdo->prepare("
                SELECT id FROM prod_worker_ot_events
                WHERE evt_prod_worker_otid = :ot_id AND evt_type = 'prod' AND overrideembalaje_act = 0
                ORDER BY id DESC LIMIT 1
            ");
            $stmtLastProd->execute([':ot_id' => $workerOtId]);
            $lastProdId = (int)$stmtLastProd->fetchColumn();
            if ($lastProdId > 0) {
                $stmtCheckDef = $this->erpPdo->prepare("
                    SELECT COUNT(*) FROM prod_worker_ot_defectunits WHERE evt_refid = :eid AND evt_status > 0
                ");
                $stmtCheckDef->execute([':eid' => $lastProdId]);
                $defCount = (int)$stmtCheckDef->fetchColumn();
                if ($defCount === 0) {
                    $dismount = $this->getRollDismountMermas($workerOtId);
                    $mermasToSave = [];
                    foreach ($dismount as $tid => $dinfo) {
                        $mermasToSave[$tid] = [
                            'amount' => $dinfo['amount'] ?? 0,
                            'kgs' => $dinfo['kgs'] ?? 0,
                            'mts' => $dinfo['mts'] ?? 0,
                            'comments' => $dinfo['comments'] ?? '',
                        ];
                    }
                    if (!empty($mermasToSave)) {
                        $this->saveEventDefectUnits($lastProdId, $mermasToSave, []);
                        $this->erpPdo->prepare("
                            UPDATE prod_worker_ot_defectunits d
                            INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                            SET d.evt_status = 0
                            WHERE e.evt_prod_worker_otid = :ot_id AND e.evt_type = 'merma' AND e.id != :cur_eid
                        ")->execute([':ot_id' => $workerOtId, ':cur_eid' => $lastProdId]);
                    }
                }

                // Consolidar automáticamente peso y metros de las bobinas impresas si el evento tiene 0
                $prSummary = $this->getPrintedRollsSummary($workerOtId);
                if (!empty($prSummary['rolls_count']) && $prSummary['rolls_count'] > 0) {
                    $this->erpPdo->prepare("
                        UPDATE prod_worker_ot_events
                        SET prod_bobina_kg = CASE WHEN prod_bobina_kg <= 0 THEN :pkg ELSE prod_bobina_kg END,
                            evt_amount_metros_lineales = CASE WHEN evt_amount_metros_lineales <= 0 THEN :pmlin ELSE evt_amount_metros_lineales END,
                            evt_amount = CASE WHEN evt_amount <= 0 THEN :pamt ELSE evt_amount END
                        WHERE id = :eid
                    ")->execute([
                        ':pkg' => $prSummary['total_weight_kg'],
                        ':pmlin' => $prSummary['total_meters'],
                        ':pamt' => $prSummary['total_meters'],
                        ':eid' => $lastProdId,
                    ]);

                    $this->erpPdo->prepare("
                    UPDATE prod_worker_ot_events
                    SET evt_status = 2,
                        evt_enddat = COALESCE(evt_enddat, :now),
                        prod_bobina_kg = 0,
                        evt_amount = 0,
                        evt_amount_metros_lineales = 0,
                        evt_amount_metros_maquina = 0
                    WHERE evt_prod_worker_otid = :ot_id
                      AND (evt_comments LIKE '%Bobina Semielaborada%' OR evt_comments LIKE '%ROLL-SE-%')
                      AND id != :cur_eid
                    ")->execute([':now' => $now, ':ot_id' => $workerOtId, ':cur_eid' => $lastProdId]);
                }
            } else {
                // Si no existía evento previo de producción pero se emitieron bobinas impresas, crear evento final consolidado
                $prSummary = $this->getPrintedRollsSummary($workerOtId);
                if (!empty($prSummary['rolls_count']) && $prSummary['rolls_count'] > 0) {
                    $insEvt = $this->erpPdo->prepare("
                        INSERT INTO prod_worker_ot_events
                        (evt_prod_worker_otid, evt_amount, prod_bobina_kg, evt_amount_metros_lineales, evt_metrotype, evt_crtdat, evt_enddat, evt_status, evt_type, evt_comments)
                        VALUES
                        (:ot_id, :amt, :kg, :mlin, 'metros_lineales', :crtdat, :enddat, 2, 'prod', :comments)
                    ");
                    $insEvt->execute([
                        ':ot_id' => $workerOtId,
                        ':amt' => $prSummary['total_meters'],
                        ':kg' => $prSummary['total_weight_kg'],
                        ':mlin' => $prSummary['total_meters'],
                        ':crtdat' => $now,
                        ':enddat' => $now,
                        ':comments' => "Producción final consolidada ({$prSummary['rolls_count']} bobinas impresas)",
                    ]);
                    $lastProdId = (int)$this->erpPdo->lastInsertId();

                    $this->erpPdo->prepare("
                    UPDATE prod_worker_ot_events
                    SET evt_status = 2,
                        evt_enddat = COALESCE(evt_enddat, :now),
                        prod_bobina_kg = 0,
                        evt_amount = 0,
                        evt_amount_metros_lineales = 0,
                        evt_amount_metros_maquina = 0
                    WHERE evt_prod_worker_otid = :ot_id
                      AND (evt_comments LIKE '%Bobina Semielaborada%' OR evt_comments LIKE '%ROLL-SE-%')
                      AND id != :cur_eid
                    ")->execute([':now' => $now, ':ot_id' => $workerOtId, ':cur_eid' => $lastProdId]);
                }
            }
        } catch (Throwable) {}

        try {
            $stmtCloseEvt = $this->erpPdo->prepare(
                "UPDATE prod_worker_ot_events
                 SET evt_status = 2, evt_enddat = :enddat
                 WHERE evt_prod_worker_otid = :ot_id AND evt_status = 1"
            );
            $stmtCloseEvt->execute([':enddat' => $now, ':ot_id' => $workerOtId]);
        } catch (Throwable) {}

        try {
            $stmtOt = $this->erpPdo->prepare("SELECT wok_ag_id FROM prod_worker_ot WHERE id = :id");
            $stmtOt->execute([':id' => $workerOtId]);
            $agId = (int)$stmtOt->fetchColumn();
            if ($agId > 0) {
                $this->syncPulpoAgendaStats($agId);
            }
        } catch (Throwable) {}

        return ['ok' => true, 'without_supervisor' => !$prodStarted];
    }

}

class ProductionClosureService
{
    use ProductionClosureServiceTrait;

    private ?ProductionService $bridge = null;

    public function __construct(private PDO $erpPdo, private ?PDO $trzPdo = null, ?ProductionService $bridge = null)
    {
        $this->bridge = $bridge;
    }

    public function __call(string $name, array $arguments): mixed
    {
        $this->bridge ??= new ProductionService($this->erpPdo, $this->trzPdo);
        return $this->bridge->$name(...$arguments);
    }
}
