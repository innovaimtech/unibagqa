<?php
declare(strict_types=1);

/**
 * Conserva la carga fisica entre CC de la misma maquina, sin movimientos de stock.
 * Preserves physical loads across cost centers on one machine, without stock postings.
 */
final class MachineRollContinuityService
{
    private const LOAD_TYPES=['ROLL_MOUNTED_ON_MACHINE','ROLL_LOADED_TO_OT','ROLL_LOADED_WITHOUT_TRACEABILITY',
        'ROLL_REMAINED_MOUNTED_AFTER_OUTPUT','ROLL_OUTPUT_COMPLETED','ROLL_UNMOUNTED_FROM_MACHINE',
        'ROLL_RETURNED_TO_WAREHOUSE','ROLL_CONSUMED','ROLL_SCRAPPED_MERMA','ROLL_MERGED_IN_OUTPUT'];

    public function __construct(private PDO $erp, private PDO $trz) {}

    private function query(PDO $pdo, string $sql, array $values=[]): PDOStatement
    {
        $stmt=$pdo->prepare($sql);
        $stmt->execute($values);
        return $stmt;
    }

    private function jsonId(string $field): string
    {
        return $this->trz->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'
            ? "CAST(json_extract(payload, '$.$field') AS TEXT)"
            : "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.$field'))";
    }

    /** Ultimo estado fisico global, nunca filtrado por la OT visible.
     * Latest global physical state, never restricted to the displayed work order. */
    public function state(int $rollId): array
    {
        $types=implode(',',array_fill(0,count(self::LOAD_TYPES),'?'));
        $stmt=$this->query($this->trz,"SELECT id,type,payload,created_at FROM events
            WHERE type IN ($types) AND (".$this->jsonId('roll_id')."=?
                OR (type IN ('ROLL_REMAINED_MOUNTED_AFTER_OUTPUT','ROLL_OUTPUT_COMPLETED')
                    AND ".$this->jsonId('remaining_mounted_roll_id')."=?))
            ORDER BY id DESC LIMIT 1",array_merge(self::LOAD_TYPES,[(string)$rollId,(string)$rollId]));
        $event=$stmt->fetch(PDO::FETCH_ASSOC)?:[];
        $payload=json_decode((string)($event['payload']??''),true)?:[];
        $type=(string)($event['type']??'');
        $remaining=(int)($payload['remaining_mounted_roll_id']??0)===$rollId
            && ($type==='ROLL_REMAINED_MOUNTED_AFTER_OUTPUT' || ($type==='ROLL_OUTPUT_COMPLETED' && !empty($payload['keep_remaining_mounted'])));
        $mounted=$type==='ROLL_MOUNTED_ON_MACHINE' || $remaining;
        $loaded=$mounted || in_array($type,['ROLL_LOADED_TO_OT','ROLL_LOADED_WITHOUT_TRACEABILITY'],true);
        return ['loaded'=>$loaded,'mounted'=>$mounted,'worker_ot_id'=>(int)($payload['worker_ot_id']??0),
            'machine_id'=>(int)($payload['machine_id']??0),'event'=>$event,'payload'=>$payload];
    }

    /** Bloqueos compartidos con montaje, salida y devolucion para evitar carreras.
     * Locks shared by mount, output and return operations to prevent races. */
    public function lockRolls(array $rollIds): array
    {
        if ($this->trz->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql') return [];
        $ids=array_values(array_unique(array_filter(array_map('intval',$rollIds),static fn($id)=>$id>0)));
        sort($ids,SORT_NUMERIC);
        $locks=[];
        try {
            foreach ($ids as $id) $locks[]=$this->lock('roll:'.$id);
            return $locks;
        } catch (Throwable $e) {
            $this->unlock($locks);
            throw $e;
        }
    }

    public function lockMachineForInit(int $initId): array
    {
        if ($this->trz->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql') return [];
        $machine=(int)$this->query($this->erp,'SELECT win_equipoid FROM prod_worker_init WHERE id=?',[$initId])->fetchColumn();
        return $machine>0?[$this->lock('machine:'.$machine)]:[];
    }

    public function lockMachineForWorkOrder(int $workerOtId): array
    {
        $job=$this->context($workerOtId);
        if (!$job || (int)$job['wok_status']!==1 || (int)$job['win_status']!==1) {
            throw new RuntimeException('La OT no esta abierta en una maquina activa.');
        }
        $locks=$this->trz->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'
            ?[$this->lock('machine:'.$job['win_equipoid'])]:[];
        try {
            $latest=(int)$this->query($this->erp,'SELECT o.id FROM prod_worker_ot o
                JOIN prod_worker_init i ON i.id=o.wok_init_id
                WHERE i.win_equipoid=? AND o.wok_status>0 ORDER BY o.id DESC LIMIT 1',[(int)$job['win_equipoid']])->fetchColumn();
            if ($latest!==$workerOtId) throw new RuntimeException('La maquina tiene una OT mas reciente. Actualiza la pantalla antes de cargar material.');
            return $locks;
        } catch (Throwable $e) {
            $this->unlock($locks);
            throw $e;
        }
    }

    public function assertRollJob(int $rollId, int $workerOtId): void
    {
        $roll=$this->query($this->trz,'SELECT current_work_order_id,status FROM rolls WHERE id=?',[$rollId])->fetch(PDO::FETCH_ASSOC);
        $state=$this->state($rollId);
        if ($roll && (int)$roll['current_work_order_id']>0 && $state['worker_ot_id']>0
            && $state['worker_ot_id']!==$workerOtId && $roll['status']==='IN_PROCESS') {
            throw new RuntimeException('La bobina esta vinculada a otra apertura de OT. Actualiza la pantalla.');
        }
    }

    private function lock(string $resource): string
    {
        $database=(string)$this->trz->query('SELECT DATABASE()')->fetchColumn();
        $key='rollcontinuity:'.substr(hash('sha256',$database.'|'.$resource),0,48);
        if ((int)$this->query($this->trz,'SELECT GET_LOCK(?,0)',[$key])->fetchColumn()!==1) {
            throw new RuntimeException('La bobina o maquina esta siendo actualizada. Espera la confirmacion y vuelve a intentar.');
        }
        return $key;
    }

    public function unlock(array $locks): void
    {
        foreach (array_reverse($locks) as $key) {
            try { $this->query($this->trz,'SELECT RELEASE_LOCK(?)',[$key]); }
            catch (Throwable $e) { error_log('Roll continuity unlock: '.$e->getMessage()); }
        }
    }

    private function context(int $workerOtId): ?array
    {
        return $this->query($this->erp,'SELECT o.id,o.wok_ag_id,o.wok_status,o.wok_crtdat,i.win_equipoid,i.win_status,i.win_wrkid
            FROM prod_worker_ot o JOIN prod_worker_init i ON i.id=o.wok_init_id WHERE o.id=?',[$workerOtId])->fetch(PDO::FETCH_ASSOC)?:null;
    }

    private function assertTransactional(): void
    {
        if ($this->trz->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql') return;
        $engines=$this->trz->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
            AND TABLE_NAME IN ('rolls','events','work_orders')")->fetchAll(PDO::FETCH_COLUMN);
        if (count($engines)!==3 || array_filter($engines,static fn($engine)=>strcasecmp((string)$engine,'InnoDB')!==0)) {
            throw new RuntimeException('No se puede confirmar la continuidad de bobinas sin trazabilidad transaccional. Solicita revision al administrador.');
        }
    }

    /**
     * $resolveWorkOrder recibe (workerOtId, createIfMissing) y devuelve el ID TRZ.
     * Only the latest active job may inherit loads; revisiting history cannot take them back.
     */
    public function continueForWorkOrder(int $workerOtId, callable $resolveWorkOrder): array
    {
        $target=$this->context($workerOtId);
        if (!$target || (int)$target['wok_status']!==1 || (int)$target['win_status']!==1 || (int)$target['win_equipoid']<=0) return [];
        $locks=[];$started=false;
        try {
            if ($this->trz->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') $locks[]=$this->lock('machine:'.$target['win_equipoid']);
            $latest=(int)$this->query($this->erp,'SELECT o.id FROM prod_worker_ot o
                JOIN prod_worker_init i ON i.id=o.wok_init_id WHERE i.win_equipoid=? AND o.wok_status>0 ORDER BY o.id DESC LIMIT 1',
                [(int)$target['win_equipoid']])->fetchColumn();
            if ($latest!==$workerOtId) return [];
            $candidates=$this->trz->query("SELECT id FROM rolls WHERE status='IN_PROCESS' AND weight_kg>0 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
            $eligible=[];
            foreach ($candidates as $id) {
                $state=$this->state((int)$id);
                if (!$state['loaded'] || $state['worker_ot_id']<=0 || $state['worker_ot_id']>=$workerOtId) continue;
                $source=$this->context($state['worker_ot_id']);
                if (!$source || (int)$source['wok_status']<=0 || (int)$source['win_equipoid']!==(int)$target['win_equipoid']) continue;
                if ($state['machine_id']>0 && $state['machine_id']!==(int)$target['win_equipoid']) continue;
                $eligible[(int)$id]=$state;
            }
            if (!$eligible) return [];
            if ($this->trz->inTransaction()) throw new RuntimeException('Finaliza la operacion de trazabilidad antes de cambiar de CC.');
            $this->assertTransactional();
            $locks=array_merge($locks,$this->lockRolls(array_keys($eligible)));
            $this->trz->beginTransaction();$started=true;
            $targetTrzId=(int)$resolveWorkOrder($workerOtId,true);
            if ($targetTrzId<=0) throw new RuntimeException('No se pudo vincular la nueva OT con trazabilidad.');
            $continued=[];
            foreach ($eligible as $id=>$snapshot) {
                $suffix=$this->trz->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
                $roll=$this->query($this->trz,'SELECT * FROM rolls WHERE id=?'.$suffix,[$id])->fetch(PDO::FETCH_ASSOC);
                $state=$this->state($id);
                if (!$roll || $roll['status']!=='IN_PROCESS' || (float)$roll['weight_kg']<=0 || !$state['loaded']) continue;
                if (($state['event']['id']??0)!==($snapshot['event']['id']??0)) {
                    throw new RuntimeException('El estado de la bobina cambio durante la apertura. Vuelve a intentar.');
                }
                $sourceId=$state['worker_ot_id'];
                $sourceNow=$this->context($sourceId);
                if (!$sourceNow || (int)$sourceNow['wok_status']<=0 || (int)$sourceNow['win_equipoid']!==(int)$target['win_equipoid']) {
                    throw new RuntimeException('La OT anterior cambio durante la apertura. Revisa su estado antes de continuar.');
                }
                $sourceTrzId=(int)$resolveWorkOrder($sourceId,false);
                $owner=(int)($roll['current_work_order_id']??0);
                if ($owner<=0 || !in_array($owner,[$sourceId,$sourceTrzId],true)) {
                    throw new RuntimeException('La bobina '.$roll['roll_code'].' tiene una vinculacion distinta. Revisa su carga antes de continuar.');
                }
                // Only rebind the job. Keep weight, meters, warehouse, stock and provenance intact.
                // Solo cambia la OT actual; conserva peso, metros, bodega, stock y origen.
                $this->query($this->trz,'UPDATE rolls SET current_work_order_id=? WHERE id=?',[$targetTrzId,$id]);
                $payload=['roll_id'=>$id,'roll_code'=>$roll['roll_code'],'worker_ot_id'=>$workerOtId,
                    'machine_id'=>(int)$target['win_equipoid'],'previous_worker_ot_id'=>$sourceId,
                    'previous_traceability_work_order_id'=>$owner,'continuity'=>true,
                    'original_mount_event_id'=>$state['payload']['original_mount_event_id']??(int)$state['event']['id'],
                    'physical_mount_created_at'=>$state['payload']['physical_mount_created_at']??$state['event']['created_at'],
                    'entry_weight_kg'=>(float)$roll['weight_kg'],'weight_kg'=>(float)$roll['weight_kg'],
                    'operator_worker_id'=>(int)$target['win_wrkid'],'timestamp'=>time(),
                    'comments'=>'La bobina permanece cargada en la misma maquina al cambiar de CC.'];
                $this->query($this->trz,'INSERT INTO events (type,payload) VALUES (?,?)',
                    [$state['mounted']?'ROLL_MOUNTED_ON_MACHINE':'ROLL_LOADED_TO_OT',json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
                $continued[]=$id;
            }
            $targetNow=$this->context($workerOtId);
            if (!$targetNow || (int)$targetNow['wok_status']!==1 || (int)$targetNow['win_status']!==1
                || (int)$targetNow['win_equipoid']!==(int)$target['win_equipoid']) {
                throw new RuntimeException('La nueva OT dejo de estar activa durante la apertura. Vuelve a consultar la maquina.');
            }
            $this->trz->commit();$started=false;
            return $continued;
        } catch (Throwable $e) {
            if ($started && $this->trz->inTransaction()) $this->trz->rollBack();
            throw $e;
        } finally {
            $this->unlock($locks);
        }
    }
}
