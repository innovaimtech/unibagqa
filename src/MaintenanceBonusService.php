<?php
declare(strict_types=1);

require_once __DIR__.'/ChileBusinessCalendar.php';

/**
 * Disponibilidad mensual para mantencion / Monthly maintenance availability.
 * ERP solo lectura; parametros y clasificaciones especiales exclusivamente TRZ.
 */
final class MaintenanceBonusService
{
    public const WEEKLY_HOURS = 42;
    public const WORKDAYS_PER_WEEK = 5;
    public const DAILY_SECONDS = self::WEEKLY_HOURS * 3600 / self::WORKDAYS_PER_WEEK;
    public const DAILY_LUNCH_SECONDS = 30 * 60;

    public const CATEGORIES = [
        'breakdown'=>'Averia', 'setup'=>'Alistamiento', 'external'=>'Paro externo',
        'preventive'=>'Preventivo realizado', 'lunch'=>'Colacion', 'ignore'=>'No afecta', 'unknown'=>'Sin clasificar',
    ];

    /** Reglas indicadas por el usuario, no editables por mes.
     * User-defined rules, never overridden by monthly configuration. */
    public const FIXED_CLASSIFICATIONS = [
        'setup'=>'setup',
        'pause:7'=>'external',
        'pause:1'=>'lunch',
        'repair:8'=>'external',
        'pause:4'=>'external',
        'repair:7'=>'external',
        'standby:Falta de Material / Insumos'=>'external',
        'standby:Espera de Orden de Trabajo (OT)'=>'external',
        'standby:Mantenimiento / Reparación'=>'preventive',
    ];

    public function __construct(private PDO $erp, private PDO $trz, private BonusConfigurationService $config,
        private ?ChileBusinessCalendar $calendar = null)
    {
    }

    public static function period(string $month): array
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $month, $m) || !checkdate((int)$m[2],1,(int)$m[1])) {
            throw new RuntimeException('Selecciona un mes valido.');
        }
        $current = new DateTimeImmutable($month.'-01 00:00:00',new DateTimeZone('America/Santiago'));
        $start = $current->modify('-1 month')->modify('+25 days');
        // Exclusive end: includes the entire 25th, even across a timezone change.
        // Fin exclusivo: incluye todo el dia 25, sin sumar segundos fijos.
        $end = $current->modify('+25 days');
        return ['start'=>$start->getTimestamp(),'end'=>$end->getTimestamp()];
    }

    public function catalog(): array
    {
        return $this->erp->query('SELECT id,equipo_name AS name,equipo_code AS code,
            equipo_type_id AS type_id,equipo_planta_id AS plant_id FROM equipo
            WHERE equipo_status>0 ORDER BY CASE equipo_type_id WHEN 7 THEN 1 WHEN 11 THEN 2
                WHEN 12 THEN 3 WHEN 8 THEN 4 ELSE 5 END,equipo_type_id,equipo_name,id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fixedReasons(): array
    {
        $maintenance=[];
        if ($this->tableExists($this->erp,'equipo_manttype')) {
            $maintenance=array_column($this->erp->query('SELECT id,mant_title FROM equipo_manttype')->fetchAll(PDO::FETCH_ASSOC),'mant_title','id');
        }
        $pauses=array_column($this->erp->query('SELECT id,pause_name FROM prod_pause_types')->fetchAll(PDO::FETCH_ASSOC),'pause_name','id');
        $reasons=[];
        foreach (self::FIXED_CLASSIFICATIONS as $key=>$category) {
            if ($key==='setup') $label='Apertura / Alistamiento';
            elseif (str_starts_with($key,'pause:')) $label=$pauses[(int)substr($key,6)]??('Pausa #'.substr($key,6));
            elseif (str_starts_with($key,'repair:')) $label='Mantencion: '.($maintenance[(int)substr($key,7)]??('Tipo #'.substr($key,7)));
            else $label=substr($key,8);
            $reasons[$key]=['label'=>$label,'category'=>$category];
        }
        return $reasons;
    }

    /** These are proposed values from the supplied sheet, never a fabricated ERP plan.
     * Valores sugeridos de la planilla, no una programacion inventada del ERP. */
    public static function suggestedMachine(array $machine): array
    {
        $name = strtoupper(trim((string)$machine['name']));
        $smallSealer = (bool)preg_match('/SELLADORA\s+(?:I|III|VII)\.?$/', $name);
        $rebob = str_contains($name,'REBO');
        $lowTarget = (bool)preg_match('/SELLADORA\s+(?:I|III)\.?$/', $name);
        return [
            'active'=>in_array((int)$machine['type_id'],[7,8,11,12],true) && !str_contains($name,'PULPO'),
            'preventive_hours'=>($smallSealer || $rebob) ? 4.0 : 8.0,
            'target_percent'=>$lowTarget ? 85.0 : 95.0,
        ];
    }

    public function policy(string $month, array $machines): array
    {
        self::period($month);
        $saved = $this->config->getMaintenanceConfiguration($month);
        $policy = array_replace(['lunch_minutes'=>30.0,'lunch_source'=>'planned','machines'=>[],
            'mapping'=>[],'schedule_month'=>'','schedule'=>[]],$saved);
        $policy['lunch_minutes']=30.0;
        $policy['lunch_source']='business_calendar';
        $policy['mapping']=self::FIXED_CLASSIFICATIONS;
        foreach ($machines as $machine) {
            $id = (int)$machine['id'];
            $policy['machines'][$id] = array_replace(self::suggestedMachine($machine),$policy['machines'][$id]??[],
                ['confirmed'=>isset($saved['machines'][$id])]);
        }
        return $policy;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
        } else {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        }
        $stmt->execute([$table]);
        return $stmt->fetchColumn() !== false;
    }

    /** Normalizar codigos ERP; mapping se conserva por compatibilidad, sin efecto.
     * Normalize ERP codes; legacy mapping argument is retained but never overrides rules. */
    public static function classify(array $event, array $mapping): string
    {
        if (isset(self::FIXED_CLASSIFICATIONS[$event['reason_key']])) {
            return self::FIXED_CLASSIFICATIONS[$event['reason_key']];
        }
        if (($event['source']??'') === 'TRZ') return 'unknown';
        if ($event['event_type']==='apertura') return 'setup';
        if ($event['event_type']==='mantencion') return 'unknown';
        $code = (int)($event['classification']??0);
        if ($code >= 1000) $code = intdiv($code,1000);
        if ((int)($event['pause_id']??0)===1 || ($event['pause_code']??'')==='2200') return 'lunch';
        return [1=>'setup',2=>'external',3=>'breakdown',4=>'ignore',5=>'ignore',6=>'lunch',7=>'ignore'][$code]??'unknown';
    }

    private function events(array $period): array
    {
        // No joins to parameter catalogs: duplicate descriptions must not duplicate time.
        // Sin joins a parametros: descripciones duplicadas no duplican horas.
        $stmt = $this->erp->prepare("SELECT ev.id,wi.win_equipoid AS machine_id,ev.evt_type AS event_type,
            ev.evt_crtdat AS start_ts,ev.evt_enddat AS end_ts,ev.evt_comments AS comments,
            ot.wok_enddat AS ot_end_ts,wi.win_enddat AS session_end_ts,
            (SELECT MIN(ns.win_crtdat) FROM prod_worker_init ns
                WHERE ns.win_wrkid=wi.win_wrkid AND ns.win_crtdat>ev.evt_crtdat) AS next_session_ts,
            (SELECT MIN(pe.evt_crtdat) FROM prod_worker_ot_events pe
                WHERE pe.evt_prod_worker_otid=ot.id AND pe.evt_status>0
                    AND pe.evt_type IN ('prod','prodsericolor') AND pe.evt_crtdat>ev.evt_crtdat) AS next_production_ts,
            ev.evt_pause_id AS pause_id,ev.evt_equipo_mantid AS repair_id,
            pt.pause_name,pt.pause_code,pt.pause_clasifica AS classification
            FROM prod_worker_ot_events ev
            INNER JOIN prod_worker_ot ot ON ot.id=ev.evt_prod_worker_otid
            INNER JOIN prod_worker_init wi ON wi.id=ot.wok_init_id
            LEFT JOIN prod_pause_types pt ON pt.id=ev.evt_pause_id
            WHERE ev.evt_status>0 AND ot.wok_status>0 AND ev.evt_type IN ('pause','apertura','mantencion')
              AND ev.evt_crtdat<:finish AND (ev.evt_enddat>:start OR COALESCE(ev.evt_enddat,0)=0)
            ORDER BY ev.evt_crtdat,ev.id");
        $stmt->execute([':finish'=>$period['end'],':start'=>$period['start']]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $repairs = [];
        // evt_equipo_mantid references equipo_manttype in the legacy ERP.
        // prod_repairtypes is a different catalog and produced false "Sin motivo".
        if ($this->tableExists($this->erp,'equipo_manttype')) {
            foreach ($this->erp->query('SELECT id,mant_title FROM equipo_manttype')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $repairs[(int)$row['id']] = $row['mant_title'];
            }
        }
        foreach ($events as &$event) {
            $event['source'] = 'ERP';
            $event['reason_key'] = $event['event_type']==='mantencion'
                ? 'repair:'.$event['repair_id'] : ($event['event_type']==='apertura' ? 'setup' : 'pause:'.$event['pause_id']);
            $event['reason'] = $event['event_type']==='mantencion'
                ? 'Mantencion: '.($repairs[(int)$event['repair_id']]??'Sin motivo')
                : ($event['event_type']==='apertura' ? 'Apertura / Alistamiento' : ($event['pause_name']??'Pausa sin motivo'));
        }
        unset($event);
        if ($this->tableExists($this->trz,'production_machine_standby_events')) {
            $stmt = $this->trz->prepare('SELECT * FROM production_machine_standby_events
                WHERE created_at<? AND (ended_at>? OR COALESCE(ended_at,0)=0) ORDER BY created_at,id');
            $stmt->execute([$period['end'],$period['start']]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                // Standby stores ERP machine ids, just like the machine connection.
                $start = strtotime($row['shift_date'].' '.$row['start_time']) ?: (int)$row['created_at'];
                $end = (int)($row['ended_at']??0);
                if (!$end && !(int)$row['is_active'] && !empty($row['end_time'])) {
                    $end = strtotime($row['shift_date'].' '.$row['end_time']) ?: 0;
                    if ($end<$start) $end = strtotime('+1 day',$end);
                }
                $events[] = ['source'=>'TRZ','id'=>$row['id'],'machine_id'=>$row['machine_id'],
                    'event_type'=>'standby','start_ts'=>$start,'end_ts'=>$end,'comments'=>$row['comments'],
                    'reason_key'=>'standby:'.$row['reason'],'reason'=>$row['reason'],'classification'=>0];
            }
        }
        return $events;
    }

    /** Un evento no puede continuar despues de cerrar su OT o sesion.
     * Bound legacy events by recorded OT/session closure, without changing ERP data. */
    public static function eventEnd(array $event): array
    {
        $end=0; $source='Sin finalizar';
        $bounds=['end_ts'=>'Evento','ot_end_ts'=>'Cierre de OT','session_end_ts'=>'Cierre de sesion'];
        // Only missing event ends may use the next recorded operator connection.
        // Solo sin termino: limitar por siguiente conexion registrada del operario.
        if ((int)($event['end_ts']??0)===0) {
            $bounds['next_session_ts']='Siguiente conexion del operario';
            if (($event['event_type']??'')==='apertura') $bounds['next_production_ts']='Inicio de produccion de la OT';
        }
        foreach ($bounds as $field=>$label) {
            $candidate=(int)($event[$field]??0);
            if ($candidate>0 && ($end===0 || $candidate<$end)) {
                $end=$candidate;
                $source=$label;
            }
        }
        return ['end'=>$end,'source'=>$source];
    }

    /** Union of intervals per physical machine, not the sum of operator records.
     * Union por maquina fisica, no suma de registros de operarios concurrentes. */
    public static function unionSeconds(array $intervals): int
    {
        if (!$intervals) return 0;
        usort($intervals,static fn($a,$b)=>$a[0]<=>$b[0]);
        $total = 0;
        [$start,$end] = array_shift($intervals);
        foreach ($intervals as [$a,$b]) {
            if ($a<=$end) $end = max($end,$b);
            else { $total += $end-$start; [$start,$end]=[$a,$b]; }
        }
        return $total+($end-$start);
    }

    /** Jornada indicada, independiente de turnos/asistencia o ajustes antiguos.
     * Requested workday, independent of shifts/attendance or legacy overrides. */
    public static function programmedTime(int $days): array
    {
        if ($days<0 || $days>31) throw new RuntimeException('Cantidad de dias disponibles no valida.');
        return ['days'=>$days,'daily'=>(int)self::DAILY_SECONDS,
            'total'=>$days*(int)self::DAILY_SECONDS,'lunch'=>$days*self::DAILY_LUNCH_SECONDS];
    }

    private function productionEvidence(array $period): array
    {
        $stmt=$this->erp->prepare("SELECT DISTINCT wi.win_equipoid
            FROM prod_worker_ot_events ev
            INNER JOIN prod_worker_ot ot ON ot.id=ev.evt_prod_worker_otid
            INNER JOIN prod_worker_init wi ON wi.id=ot.wok_init_id
            WHERE ev.evt_status>0 AND ot.wok_status>0 AND ev.evt_type IN ('prod','prodsericolor')
                AND ev.evt_crtdat<? AND (ev.evt_enddat>? OR ev.evt_crtdat>=?)");
        $stmt->execute([$period['end'],$period['start'],$period['start']]);
        return array_fill_keys(array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN)),true);
    }

    public static function calculate(float $total, float $preventive, float $lunch, float $breakdown,
        float $setup, float $external, float $target): array
    {
        $available = $total-$lunch-$preventive;
        $maintenance = $available-$breakdown;
        $gross = $maintenance-$setup-$external;
        $real = $available>0 ? $maintenance/$available*100 : null;
        return ['total'=>$total,'preventive'=>$preventive,'lunch'=>$lunch,'available'=>$available,
            'breakdown'=>$breakdown,'setup'=>$setup,'external'=>$external,'gross'=>$gross,
            'maintenance'=>$maintenance,'real'=>$real,'target'=>$target,
            'status'=>$available<=0 || $gross<0 ? 'PENDIENTE' : ($real>=$target ? 'CUMPLE' : 'NO CUMPLE')];
    }

    public function report(string $month): array
    {
        $period = self::period($month);
        $calendar=($this->calendar??new ChileBusinessCalendar())->forPeriod($period);
        $machines = $this->catalog();
        if (!$machines) throw new RuntimeException('No se pudo obtener el catalogo de maquinas del ERP.');
        $policy = $this->policy($month,$machines);
        $programmed = self::programmedTime($calendar['days']);
        $events = $this->events($period);
        $productionEvidence = $this->productionEvidence($period);
        $grouped = [];
        $reasons = $this->fixedReasons();
        foreach ($events as $event) {
            $end=self::eventEnd($event);
            // Closed historical events do not belong to this bonus period.
            // Eventos historicos ya cerrados no afectan el periodo consultado.
            if ($end['end']>0 && $end['end']<=$period['start']) continue;
            $event['effective_end_ts']=$end['end'];
            $event['end_source']=$end['source'];
            $event['category'] = self::classify($event,$policy['mapping']);
            $reasons[$event['reason_key']] = ['label'=>$event['reason'],'category'=>$event['category']];
            $grouped[(int)$event['machine_id']][] = $event;
        }
        $rows = [];
        $detail = [];
        foreach ($machines as $machine) {
            $id = (int)$machine['id'];
            $settings = $policy['machines'][$id];
            if (empty($settings['active'])) continue;
            $intervals = array_fill_keys(array_keys(self::CATEGORIES),[]);
            $warnings = [];
            $machineEvents = $grouped[$id]??[];
            foreach ($machineEvents as $event) {
                $start = max((int)$event['start_ts'],$period['start']);
                $end = (int)$event['effective_end_ts'];
                if ($end<=0) {
                    $warnings['open']='Eventos sin finalizar';
                    // Missing duration is not elapsed time over months or years.
                    // Sin duracion validable: no inventar horas hasta la fecha actual.
                    $end=$start;
                } elseif ($end<(int)$event['start_ts']) {
                    $warnings['invalid']='Eventos con fechas invalidas';
                }
                $end=min($end,$period['end']);
                $seconds=max(0,$end-$start);
                if ($seconds>0) $intervals[$event['category']][] = [$start,$end];
                if ($event['category']==='unknown') $warnings['unknown']='Motivos sin clasificar';
                $detail[] = array_replace($event,['machine_name'=>$machine['name'],'seconds'=>$seconds,
                    'open'=>(int)$event['effective_end_ts']<=0,'clipped_start'=>$start,'clipped_end'=>$end]);
            }
            $seconds = [];
            foreach ($intervals as $category=>$ranges) $seconds[$category]=self::unionSeconds($ranges);
            $lossRanges = array_merge($intervals['breakdown'],$intervals['setup'],$intervals['external']);
            if ($seconds['breakdown']+$seconds['setup']+$seconds['external']>self::unionSeconds($lossRanges)) {
                $warnings['overlap']='Tiempos de distintas categorias superpuestos';
            }
            if (!$programmed['days']) $warnings['schedule']='Sin dias habiles disponibles en el periodo';
            if (!$machineEvents && !isset($productionEvidence[$id])) $warnings['records']='Sin registros de actividad';
            if (empty($policy['effective_from'])) $warnings['policy']='Confirmar parametros de la planilla en Configuracion';
            elseif (empty($settings['confirmed'])) $warnings['policy']='Confirmar parametros de esta maquina en Configuracion';
            if ($period['end']>time()) $warnings['period']='Periodo del bono aun no terminado';
            $calc = self::calculate($programmed['total'],(float)$settings['preventive_hours']*3600,$programmed['lunch'],
                $seconds['breakdown'],$seconds['setup'],$seconds['external'],(float)$settings['target_percent']);
            if ($calc['status']==='PENDIENTE') $warnings['time']='Tiempos incompatibles con la jornada';
            if ($warnings) $calc['status']='PENDIENTE';
            $rows[] = array_replace($machine,$calc,[
                'days'=>$programmed['days'],'daily'=>$programmed['daily'],
                'schedule_source'=>'42 h semanales / 5 dias = 8:24:00; total = dias habiles x 8:24:00',
                'recorded_lunch'=>$seconds['lunch'],
                'recorded_preventive'=>$seconds['preventive'],'warnings'=>array_values($warnings),
            ]);
        }
        $valid = array_filter($rows,static fn($r)=>$r['status']!=='PENDIENTE');
        $complete = count($valid)===count($rows) && count($rows)>0;
        // Simple mean exactly like the reference, not a weighted fleet ratio.
        $real = $complete ? array_sum(array_column($rows,'real'))/count($rows) : null;
        $target = $rows ? array_sum(array_column($rows,'target'))/count($rows) : null;
        return compact('month','period','calendar','machines','policy','rows','detail','reasons')+
            ['summary'=>['real'=>$real,'target'=>$target,'status'=>!$complete ? 'PENDIENTE' : ($real>=$target ? 'CUMPLE' : 'NO CUMPLE')]];
    }

    public function save(string $month, array $input, string $actor): void
    {
        $period = self::period($month);
        $machines = $this->catalog();
        if (!$machines) throw new RuntimeException('No hay maquinas disponibles para configurar.');
        $policy = $this->policy($month,$machines);
        $number = static function(mixed $value,float $min,float $max): float {
            if (!is_scalar($value)) throw new RuntimeException('Valor numerico no valido.');
            $value=str_replace(',','.',trim((string)$value));
            if (!is_numeric($value) || !is_finite((float)$value) || (float)$value<$min || (float)$value>$max) {
                throw new RuntimeException('Revisa los dias, horas y porcentajes de la configuracion.');
            }
            return (float)$value;
        };
        $policy['lunch_minutes']=30.0;
        $policy['lunch_source']='business_calendar';
        // Discard obsolete overrides; posted daily hours/days never alter the formula.
        // Ignorar ajustes antiguos y enviados: jornada y dias siempre automaticos.
        $policy['schedule_month']='';
        $policy['schedule']=[];
        if (!is_array($input['machines']??null)) throw new RuntimeException('Configuracion no valida.');
        foreach ($machines as $machine) {
            $id=(int)$machine['id'];
            $row=$input['machines'][$id]??[];
            if (!is_array($row) || !array_key_exists('preventive_hours',$row) || !array_key_exists('target_percent',$row)) {
                throw new RuntimeException('Faltan datos de una maquina. Vuelve a abrir la configuracion.');
            }
            $policy['machines'][$id]=[
                'active'=>!empty($row['active']),
                'preventive_hours'=>$number($row['preventive_hours']??0,0,744),
                'target_percent'=>$number($row['target_percent']??95,0,100),
            ];
        }
        // Ignore submitted mappings, including forms opened before this change.
        // Formularios antiguos tampoco pueden modificar las reglas fijas.
        $policy['mapping']=self::FIXED_CLASSIFICATIONS;
        $this->config->saveMaintenanceConfiguration($month,$policy,$actor);
    }

    public static function duration(float $seconds): string
    {
        $n=(int)round(abs($seconds));
        return ($seconds<0 ? '-' : '').sprintf('%d:%02d:%02d',intdiv($n,3600),intdiv($n%3600,60),$n%60);
    }
}
