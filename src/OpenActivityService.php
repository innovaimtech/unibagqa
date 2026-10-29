<?php
declare(strict_types=1);

require_once __DIR__.'/MaintenanceBonusService.php';
require_once __DIR__.'/ProductionService.php';

/** Revision de tiempos, sin cerrar OTs ni alterar cantidades o inventario.
 * Time interventions never close work orders or change production/stock. */
final class OpenActivityService
{
    private array $tables=[];
    private Closure $authorize;

    public function __construct(private PDO $erp, private PDO $trz, ?callable $authorize=null)
    {
        $this->authorize=$authorize!==null ? Closure::fromCallable($authorize)
            : static fn(string $user,string $password): bool=>(new ProductionService($erp,$trz))->validateSupervisor($user,$password);
    }

    private function exists(PDO $pdo,string $table): bool
    {
        $key=spl_object_id($pdo).':'.$table;
        if (!array_key_exists($key,$this->tables)) {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') {
                $q=$pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
            } else $q=$pdo->prepare('SHOW TABLES LIKE ?');
            $q->execute([$table]);
            $this->tables[$key]=(bool)$q->fetchColumn();
        }
        return $this->tables[$key];
    }

    public static function anomaly(string $type,int $start,bool $open,int $now): array
    {
        $limit=$type==='lunch' ? 3600 : ($type==='maintenance' ? 10800 : null);
        $elapsed=max(0,$now-$start);
        $invalid=$start<=0 || $start>$now;
        return ['elapsed'=>$elapsed,'limit'=>$limit,
            'anomaly'=>$invalid || ($open && $limit!==null && $elapsed>$limit),
            'label'=>$invalid ? 'Fecha de inicio no valida' : ($open && $limit!==null && $elapsed>$limit ? 'Umbral excedido' : 'Sin exceso de umbral')];
    }

    private function erpRows(string $where,array $params=[]): array
    {
        $q=$this->erp->prepare("SELECT e.id,e.evt_type,e.evt_crtdat,e.evt_enddat,e.evt_status,e.evt_comments,
            e.evt_pause_id,e.evt_equipo_mantid,o.id AS ot_id,o.wok_crtdat,o.wok_enddat,
            i.id AS init_id,i.win_wrkid AS worker_id,i.win_equipoid AS machine_id,i.win_crtdat,i.win_enddat,
            w.wrk_firstname,w.wrk_lastname,m.equipo_name AS machine_name,
            p.pause_name,p.pause_clasifica,p.pause_code,mt.mant_title,a.ag_reqid AS ot_number
            FROM prod_worker_ot_events e INNER JOIN prod_worker_ot o ON o.id=e.evt_prod_worker_otid
            INNER JOIN prod_worker_init i ON i.id=o.wok_init_id
            LEFT JOIN workers w ON w.id=i.win_wrkid LEFT JOIN equipo m ON m.id=i.win_equipoid
            LEFT JOIN prod_pause_types p ON p.id=e.evt_pause_id
            LEFT JOIN equipo_manttype mt ON mt.id=e.evt_equipo_mantid
            LEFT JOIN prod_agenda a ON a.id=o.wok_ag_id
            WHERE e.evt_status>0 AND o.wok_status>0 AND e.evt_type IN ('pause','mantencion','apertura')
                AND ($where) ORDER BY e.evt_crtdat,e.id LIMIT 501");
        $q->execute($params);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    private function normalize(string $source,array $raw,int $now): array
    {
        $id=(int)$raw['id'];
        if ($source==='ERP') {
            $start=(int)$raw['evt_crtdat']; $end=(int)$raw['evt_enddat'];
            $key=$raw['evt_type']==='apertura' ? 'setup'
                : ($raw['evt_type']==='mantencion' ? 'repair:'.$raw['evt_equipo_mantid'] : 'pause:'.$raw['evt_pause_id']);
            $category=MaintenanceBonusService::classify(['reason_key'=>$key,'source'=>'ERP','event_type'=>$raw['evt_type'],
                'pause_id'=>$raw['evt_pause_id'],'pause_code'=>$raw['pause_code'],'classification'=>$raw['pause_clasifica']],[]);
            $type=$category==='lunch' ? 'lunch' : ($raw['evt_type']==='mantencion' ? 'maintenance' : $raw['evt_type']);
            $reason=$raw['evt_type']==='apertura' ? 'Apertura / Alistamiento'
                : ($raw['evt_type']==='mantencion' ? 'Mantencion: '.($raw['mant_title']?:'Sin tipo registrado') : ($raw['pause_name']?:'Pausa sin motivo'));
            $operator=trim($raw['wrk_firstname'].' '.$raw['wrk_lastname'])?:('Operario #'.$raw['worker_id']);
            $parentStart=max((int)$raw['wok_crtdat'],(int)$raw['win_crtdat']);
            $ends=array_filter([(int)$raw['wok_enddat'],(int)$raw['win_enddat']],static fn($v)=>$v>0);
            $parentEnd=$ends ? min($ends) : 0;
            $comments=(string)$raw['evt_comments'];
        } else {
            $start=$source==='LUNCH' ? strtotime((string)$raw['start_time'])
                : strtotime($raw['shift_date'].' '.$raw['start_time']);
            $end=$source==='LUNCH' ? (!empty($raw['end_time']) ? strtotime($raw['end_time']) : 0) : (int)($raw['ended_at']??0);
            if (!$end && $source==='STANDBY' && !empty($raw['end_time'])) {
                $end=strtotime($raw['shift_date'].' '.$raw['end_time']);
                if ($end<$start) $end=strtotime('+1 day',$end);
            }
            $reason=$source==='LUNCH' ? 'Colacion' : (string)$raw['reason'];
            $key=$source==='LUNCH' ? 'pause:1' : 'standby:'.$reason;
            $category=$source==='LUNCH' ? 'lunch' : MaintenanceBonusService::classify(['reason_key'=>$key,'source'=>'TRZ','event_type'=>'standby'],[]);
            $type=$category==='lunch' ? 'lunch' : ($category==='preventive' || $category==='breakdown' ? 'maintenance' : 'standby');
            // Monitoring names do not override the fixed bonus classification.
            // Nombres para alertas, sin cambiar la clasificacion fija del bono.
            $name=mb_strtolower(trim($reason),'UTF-8');
            if (in_array($name,['colacion','colación'],true)) $type='lunch';
            elseif (in_array($name,['mantencion','mantención','mantenimiento','mantenimiento / reparacion','mantenimiento / reparación'],true)) $type='maintenance';
            $operator=(string)$raw['operator_name']; $parentStart=0; $parentEnd=0; $comments=(string)$raw['comments'];
        }
        $alert=self::anomaly($type,(int)$start,$end<=0,$now);
        $invalid=$end>0 && ($end<$start || ($parentEnd>0 && $end>$parentEnd));
        $impact=[
            'setup'=>'Alistamientos y operativo bruto; no cambia las horas programadas.',
            'external'=>'Paros externos y operativo bruto; no cambia las horas programadas.',
            'breakdown'=>'Averias, operativo bruto, operativo de mantencion y disponibilidad real.',
            'preventive'=>'Preventivo realizado informativo; no se descuenta otra vez del disponible.',
            'lunch'=>'Colacion registrada informativa; la colacion mensual sigue fija en 30 minutos por dia habil.',
            'ignore'=>'No descuenta tiempo del bono.',
            'unknown'=>'Clasificacion no reconocida: requiere revision y bloquea la validacion del bono.',
        ][$category];
        $version=hash('sha256',json_encode($raw,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE));
        if (!empty($raw['link_unavailable'])) {
            $alert=array_replace($alert,['anomaly'=>true,'label'=>'Evento ERP vinculado inexistente o anulado']);
            $impact.=' Solo se puede corregir la copia local; no se restaura ni modifica el evento ERP anulado.';
        }
        return ['source'=>$source,'id'=>$id,'key'=>$source.':'.$id,'type'=>$type,'category'=>$category,
            'reason'=>$reason,'machine_id'=>(int)$raw['machine_id'],'machine_name'=>(string)($raw['machine_name']??''),
            'operator'=>$operator,'worker_id'=>(int)($raw['worker_id']??0),'ot_id'=>(int)($raw['ot_id']??0),
            'ot_number'=>(string)($raw['ot_number']??''),'init_id'=>(int)($raw['init_id']??0),
            'start'=>(int)$start,'end'=>(int)$end,'open'=>$end<=0,'comments'=>$comments,'version'=>$version,
            'parent_start'=>$parentStart,'parent_end'=>$parentEnd,'impact'=>$impact,
            'alert'=>array_replace($alert,$invalid ? ['anomaly'=>true,'label'=>'Termino incompatible con inicio o cierre de OT/sesion'] : []),
            'raw'=>$raw];
    }

    public function get(string $source,int $id,?int $now=null): array
    {
        if ($id<=0 || !in_array($source,['ERP','STANDBY','LUNCH'],true)) throw new RuntimeException('Actividad no valida.');
        if ($source==='ERP') $raw=$this->erpRows('e.id=?',[$id])[0]??null;
        else {
            $table=$source==='LUNCH' ? 'production_operator_lunch_breaks' : 'production_machine_standby_events';
            if (!$this->exists($this->trz,$table)) throw new RuntimeException('Fuente de actividad no disponible.');
            $q=$this->trz->prepare("SELECT * FROM $table WHERE id=?"); $q->execute([$id]); $raw=$q->fetch(PDO::FETCH_ASSOC);
            // Linked lunch interventions always target their canonical ERP event.
            // Colaciones vinculadas: corregir el evento ERP y sincronizar su espejo.
            if ($raw && $source==='LUNCH' && (int)($raw['erp_event_id']??0)>0) {
                $linked=$this->erpRows('e.id=?',[(int)$raw['erp_event_id']])[0]??null;
                if ($linked) return $this->normalize('ERP',$linked,$now??time());
                $raw['link_unavailable']=true;
            }
        }
        if (!$raw) throw new RuntimeException('Actividad inexistente o anulada.');
        return $this->normalize($source,$raw,$now??time());
    }

    public function monitor(?int $now=null,?array $period=null): array
    {
        $now=$now??time(); $items=[]; $truncated=false;
        $period=$period??MaintenanceBonusService::period(date('Y-m',$now));
        $raw=$this->erpRows('COALESCE(e.evt_enddat,0)=0 OR (e.evt_crtdat>=? AND e.evt_crtdat<?
            AND (e.evt_enddat<e.evt_crtdat OR (o.wok_enddat>0 AND e.evt_enddat>o.wok_enddat)
                OR (i.win_enddat>0 AND e.evt_enddat>i.win_enddat)))',[$period['start'],$period['end']]);
        if (count($raw)>500) $truncated=true;
        foreach (array_slice($raw,0,500) as $row) { $item=$this->normalize('ERP',$row,$now); $items[$item['key']]=$item; }
        foreach (['STANDBY'=>'production_machine_standby_events','LUNCH'=>'production_operator_lunch_breaks'] as $source=>$table) {
            if (!$this->exists($this->trz,$table)) continue;
            $where=$source==='STANDBY' ? 'COALESCE(ended_at,0)=0 AND (end_time IS NULL OR end_time=\'\')' : 'end_time IS NULL';
            $raw=$this->trz->query("SELECT * FROM $table WHERE $where ORDER BY id LIMIT 501")->fetchAll(PDO::FETCH_ASSOC);
            if (count($raw)>500) $truncated=true;
            foreach (array_slice($raw,0,500) as $row) {
                if ($source==='LUNCH' && (int)($row['erp_event_id']??0)>0) {
                    $item=$this->get('LUNCH',(int)$row['id'],$now);
                    if (!$item['open']) $item['alert']=array_replace($item['alert'],
                        ['anomaly'=>true,'label'=>'Colacion local pendiente de sincronizar con ERP']);
                } else $item=$this->normalize($source,$row,$now);
                $items[$item['key']]=$item;
            }
        }
        $items=array_values($items);
        usort($items,static fn($a,$b)=>[$b['alert']['anomaly'],$a['start']]<=>[$a['alert']['anomaly'],$b['start']]);
        foreach ($items as &$item) unset($item['raw']);
        return ['items'=>$items,'server_time'=>$now,'truncated'=>$truncated,'anomalies'=>count(array_filter($items,static fn($i)=>$i['alert']['anomaly']))];
    }

    public static function parseDate(string $value): int
    {
        foreach (['d/m/Y H:i:s','d/m/Y H:i'] as $format) {
            $date=DateTimeImmutable::createFromFormat('!'.$format,trim($value),new DateTimeZone('America/Santiago'));
            $errors=DateTimeImmutable::getLastErrors();
            if ($date && (!$errors || (!$errors['warning_count'] && !$errors['error_count'])) && $date->format($format)===trim($value)) return $date->getTimestamp();
        }
        throw new RuntimeException('Fecha no valida. Usa dd/mm/aaaa hh:mm:ss.');
    }

    private function ensureAudit(): void
    {
        $id=$this->trz->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        $this->trz->exec("CREATE TABLE IF NOT EXISTS production_activity_interventions (
            id $id,source VARCHAR(20) NOT NULL,event_id BIGINT NOT NULL,action VARCHAR(20) NOT NULL,
            diagnosis VARCHAR(30) NOT NULL,notes TEXT NOT NULL,actor VARCHAR(200) NOT NULL,supervisor VARCHAR(100) NOT NULL,
            before_json TEXT NOT NULL,after_json TEXT NOT NULL,state VARCHAR(20) NOT NULL,created_at BIGINT NOT NULL)");
        $this->tables[spl_object_id($this->trz).':production_activity_interventions']=true;
    }

    public function history(string $source,int $id): array
    {
        if (!$this->exists($this->trz,'production_activity_interventions')) return [];
        $q=$this->trz->prepare("SELECT * FROM production_activity_interventions WHERE source=? AND event_id=? ORDER BY id DESC LIMIT 30");
        $q->execute([$source,$id]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function executeUpdate(PDOStatement $query,array $values): void
    {
        foreach ($values as $i=>$value) {
            $query->bindValue($i+1,$value,is_int($value) ? PDO::PARAM_INT : ($value===null ? PDO::PARAM_NULL : PDO::PARAM_STR));
        }
        $query->execute();
    }

    public function change(array $input,string $actor): array
    {
        if (trim($actor)==='' || !($this->authorize)((string)($input['supervisor']??''),(string)($input['password']??''))) {
            throw new RuntimeException('Se requiere autorizacion valida de supervisor.');
        }
        $action=(string)($input['action']??'');
        $diagnosis=(string)($input['diagnosis']??'');
        $notes=trim((string)($input['notes']??''));
        if (!in_array($action,['close','edit'],true) || !in_array($diagnosis,['forgotten','system_error','verified'],true)
            || mb_strlen($notes)<5 || mb_strlen($notes)>1000) throw new RuntimeException('Selecciona la causa y describe la revision (5 a 1000 caracteres).');
        $activity=$this->get((string)($input['source']??''),(int)($input['id']??0));
        if (!hash_equals($activity['version'],(string)($input['version']??''))) throw new RuntimeException('La actividad cambio. Actualiza el detalle antes de intervenir.');
        $start=$action==='close' ? $activity['start'] : self::parseDate((string)($input['start']??''));
        $end=trim((string)($input['end']??''))!=='' ? self::parseDate((string)$input['end']) : 0;
        if ($start<=0 || $start>time() || ($end>0 && ($end<$start || $end>time()))
            || ($activity['parent_start']>0 && $start<$activity['parent_start'])
            || ($activity['parent_end']>0 && ($start>$activity['parent_end'] || $end>$activity['parent_end']))) {
            throw new RuntimeException('El horario debe respetar inicio, cierre de OT/sesion y fecha actual.');
        }
        if (($action==='close' || !$activity['open']) && !$end) throw new RuntimeException('Debes indicar el termino; no se permite reabrir un evento cerrado.');
        $comments=trim((string)($input['comments']??$activity['comments']));
        if (mb_strlen($comments)>5000) throw new RuntimeException('Observaciones demasiado extensas.');
        $labels=['forgotten'=>'Olvido de cierre','system_error'=>'Error del sistema','verified'=>'Revision confirmada'];
        $auditLine='Revision por '.$actor.'; autorizado por '.trim((string)$input['supervisor']).'; '.$labels[$diagnosis].': '.$notes;
        $after=['start'=>$start,'end'=>$end,'comments'=>$comments."\n".$auditLine];
        $before=array_intersect_key($activity,array_flip(['start','end','comments']));
        if ($activity['source']==='ERP') {
            $before['status']=(int)$activity['raw']['evt_status'];
            $after['status']=$end?2:$before['status'];
        }
        $this->ensureAudit();
        $q=$this->trz->prepare('INSERT INTO production_activity_interventions
            (source,event_id,action,diagnosis,notes,actor,supervisor,before_json,after_json,state,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$activity['source'],$activity['id'],$action,$diagnosis,$notes,$actor,trim((string)$input['supervisor']),
            json_encode($before,JSON_THROW_ON_ERROR),json_encode($after,JSON_THROW_ON_ERROR),'pending',time()]);
        $auditId=(int)$this->trz->lastInsertId();
        $pdo=$activity['source']==='ERP' ? $this->erp : $this->trz;
        $transactional=true;
        if ($activity['source']==='ERP' && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') {
            $engine=$pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $engine->execute(['prod_worker_ot_events']);
            $transactional=strcasecmp((string)$engine->fetchColumn(),'InnoDB')===0;
        }
        try {
            if ($transactional) $pdo->beginTransaction();
            if ($transactional && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') {
                $table=$activity['source']==='ERP' ? 'prod_worker_ot_events'
                    : ($activity['source']==='LUNCH' ? 'production_operator_lunch_breaks' : 'production_machine_standby_events');
                $lock=$pdo->prepare("SELECT id FROM $table WHERE id=? FOR UPDATE");
                $lock->execute([$activity['id']]); $lock->fetchColumn();
                if ($activity['source']==='ERP') {
                    $lock=$pdo->prepare('SELECT o.id FROM prod_worker_ot o INNER JOIN prod_worker_init i ON i.id=o.wok_init_id
                        WHERE o.id=? FOR UPDATE');
                    $lock->execute([$activity['ot_id']]); $lock->fetchColumn();
                }
            }
            $current=$this->get($activity['source'],$activity['id']);
            if (!hash_equals($activity['version'],$current['version'])) throw new RuntimeException('Otro usuario modifico la actividad. No se aplicaron cambios.');
            if ($activity['source']==='ERP') {
                $raw=$activity['raw'];
                // MyISAM cannot roll back: one atomic, version-guarded UPDATE only.
                // MyISAM no revierte transacciones: una sola actualizacion con condiciones.
                $q=$pdo->prepare('UPDATE prod_worker_ot_events SET evt_crtdat=?,evt_enddat=?,evt_comments=?,evt_status=?
                    WHERE id=? AND evt_status=? AND evt_crtdat=? AND COALESCE(evt_enddat,0)=? AND COALESCE(evt_comments,\'\')=?
                    AND evt_type=? AND evt_pause_id=? AND evt_equipo_mantid=?
                    AND EXISTS (SELECT 1 FROM prod_worker_ot o INNER JOIN prod_worker_init i ON i.id=o.wok_init_id
                        WHERE o.id=prod_worker_ot_events.evt_prod_worker_otid AND o.wok_status>0
                            AND o.wok_crtdat=? AND o.wok_enddat=? AND i.win_crtdat=? AND i.win_enddat=?)');
                self::executeUpdate($q,[$start,$end,$after['comments'],$after['status'],$activity['id'],$before['status'],$activity['start'],$activity['end'],$activity['comments'],
                    $raw['evt_type'],$raw['evt_pause_id'],$raw['evt_equipo_mantid'],$raw['wok_crtdat'],$raw['wok_enddat'],$raw['win_crtdat'],$raw['win_enddat']]);
            } else {
                $table=$activity['source']==='LUNCH' ? 'production_operator_lunch_breaks' : 'production_machine_standby_events';
                $raw=$activity['raw']; $minutes=$end ? (int)round(($end-$start)/60) : null;
                if ($activity['source']==='LUNCH') {
                    $q=$pdo->prepare("UPDATE $table SET shift_date=?,start_time=?,end_time=?,duration_minutes=?,comments=?
                        WHERE id=? AND start_time=? AND COALESCE(end_time,'')=? AND COALESCE(comments,'')=?");
                    self::executeUpdate($q,[date('Y-m-d',$start),date('Y-m-d H:i:s',$start),$end?date('Y-m-d H:i:s',$end):null,
                        $minutes,$after['comments'],$activity['id'],$raw['start_time'],$raw['end_time']??'',$activity['comments']]);
                } else {
                    $q=$pdo->prepare("UPDATE $table SET shift_date=?,start_time=?,end_time=?,ended_at=?,duration_minutes=?,is_active=?,comments=?
                        WHERE id=? AND start_time=? AND COALESCE(ended_at,0)=? AND COALESCE(comments,'')=?");
                    self::executeUpdate($q,[date('Y-m-d',$start),date('H:i:s',$start),$end?date('H:i:s',$end):null,$end?:null,$minutes,$end?0:1,
                        $after['comments'],$activity['id'],$raw['start_time'],(int)($raw['ended_at']??0),$activity['comments']]);
                }
            }
            if ($q->rowCount()!==1) throw new RuntimeException('La actividad cambio o no pudo actualizarse.');
            if ($transactional) $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->trz->prepare("UPDATE production_activity_interventions SET state='failed' WHERE id=?")->execute([$auditId]);
            throw $e;
        }
        // ERP and TRZ use separate connections: retain pending intent on sync failure.
        // Dos conexiones: conservar revision pendiente si falla auditoria/sincronizacion.
        $warning='';
        try {
            $this->trz->beginTransaction();
            if ($activity['source']==='ERP' && $this->exists($this->trz,'production_operator_lunch_breaks')) {
                $q=$this->trz->prepare('UPDATE production_operator_lunch_breaks SET shift_date=?,start_time=?,end_time=?,duration_minutes=?,comments=?
                    WHERE erp_event_id=?');
                $q->execute([date('Y-m-d',$start),date('Y-m-d H:i:s',$start),$end?date('Y-m-d H:i:s',$end):null,
                    $end?(int)round(($end-$start)/60):null,$after['comments'],$activity['id']]);
            }
            $this->trz->prepare("UPDATE production_activity_interventions SET state='done' WHERE id=?")->execute([$auditId]);
            $this->trz->commit();
        } catch (Throwable $e) {
            if ($this->trz->inTransaction()) $this->trz->rollBack();
            error_log('Activity intervention sync pending #'.$auditId.': '.$e->getMessage());
            $warning='Actividad modificada; sincronizacion o auditoria pendiente. Revision #'.$auditId.'.';
        }
        return ['ok'=>true,'message'=>$end?'Actividad finalizada y revisada.':'Actividad editada; sigue abierta.','warning'=>$warning,'audit_id'=>$auditId];
    }
}
