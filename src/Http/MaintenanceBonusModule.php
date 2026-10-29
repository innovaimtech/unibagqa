<?php
declare(strict_types=1);

require_once __DIR__.'/../MaintenanceBonusService.php';
require_once __DIR__.'/../MaintenanceBonusWorkbook.php';
require_once __DIR__.'/OpenActivityModule.php';
require_once __DIR__.'/BonusConfigurationModule.php';

/** Vista y Excel usan el mismo calculo / UI and Excel share the same calculation. */
function maintenanceBonusTable(array $report, bool $excel=false): string
{
    $headers = ['Mes','Máquina','Código','Preventivo programado','Colación','Días disponibles',
        'Horas programadas / dia','Horas totales / mes','Tiempo disponible','Averias',
        'Alistamientos operativos','Paros externos','Operativo bruto','Operativo mantención',
        'Disponibilidad real','Disponibilidad esperada','Cumple / No cumple'];
    $html='<table class="maintenance-table"><thead><tr>';
    foreach ($headers as $i=>$header) {
        if ($excel && in_array($i,[3,4,6,7,8,9,10,11,12,13],true)) $header.=' (h)';
        $html.='<th>'.h($header).'</th>';
    }
    $html.='</tr></thead><tbody>';
    foreach ($report['rows'] as $row) {
        $html.='<tr><td class="text-cell">'.h(substr($report['month'],5,2).'/'.substr($report['month'],0,4)).'</td>'
            .'<td class="text-cell">'.h($row['name']).'</td><td class="text-cell">'.h($row['code']?:('#'.$row['id'])).'</td>';
        foreach (['preventive','lunch','days','daily','total','available','breakdown','setup','external','gross','maintenance'] as $key) {
            $value=(float)$row[$key];
            if ($key==='days') $html.='<td class="num-int">'.(int)$value.'</td>';
            elseif ($excel) $html.='<td class="num-dec3" data-xlsx-value="'.($value/3600).'">'.number_format($value/3600,3,',','').'</td>';
            else $html.='<td>'.h(MaintenanceBonusService::duration($value)).'</td>';
        }
        foreach (['real','target'] as $key) {
            $html.=$row[$key]===null ? '<td class="text-cell">Sin base</td>'
                : '<td class="num-percent" data-xlsx-type="percentage" data-xlsx-value="'.($row[$key]/100).'">'
                    .number_format($row[$key],2,',','').'%</td>';
        }
        $class=$row['status']==='CUMPLE' ? 'yes' : ($row['status']==='NO CUMPLE' ? 'no' : 'pending');
        $html.='<td class="text-cell"><span class="maintenance-status '.$class.'" title="'.h(implode('; ',$row['warnings'])).'">'
            .h($row['status']).'</span></td></tr>';
    }
    if (!$report['rows']) $html.='<tr><td colspan="17">Sin maquinas seleccionadas.</td></tr>';
    $html.='</tbody><tfoot><tr><th colspan="14">PROMEDIO SIMPLE</th>';
    foreach (['real','target'] as $key) {
        $value=$report['summary'][$key];
        $html.=$value===null ? '<td class="text-cell">Pendiente</td>'
            : '<td class="num-percent" data-xlsx-type="percentage" data-xlsx-value="'.($value/100).'">'
                .number_format($value,2,',','').'%</td>';
    }
    return $html.'<td class="text-cell">'.h($report['summary']['status']).'</td></tr></tfoot></table>';
}

function maintenanceBonusSources(): string
{
    return '<p><b>Periodo:</b> del 26 del mes anterior al 25 del mes seleccionado, ambos incluidos.</p>'
        .'<p><b>Maquinas:</b> catalogo activo del ERP (equipo). Seleccion, metas y preventivo programado: configuracion TRZ.</p>'
        .'<p><b>Dias disponibles:</b> lunes a viernes del periodo, descontando exclusivamente los feriados nacionales de Chile que caigan en esos dias. Sabados, domingos y feriados en fin de semana no se descuentan dos veces. No depende de los dias con produccion registrada.</p>'
        .'<p><b>Horas programadas / dia:</b> 42 horas semanales / 5 dias = 8,4 horas = 8:24:00 = 30.240 segundos. Jornada fija, sin depender de turnos ERP, asistencia ni ajustes manuales.</p>'
        .'<p><b>Horas totales / mes:</b> dias disponibles del periodo x 30.240 segundos. Se recalcula al consultar el mes y despues de guardar la configuracion.</p>'
        .'<p><b>Colacion:</b> siempre 30 minutos x dias habiles del periodo. No es editable. Las colaciones registradas quedan como informacion, sin modificar este calculo.</p>'
        .'<p><b>Feriados:</b> calendario anual de Chile consultado automaticamente y guardado en cache local, con actualizacion semanal. Respaldo oficial verificado para 2026. Si otro ano no puede validarse ni tiene cache, no se supone que no hubo feriados.</p>'
        .'<p><b>Averias, alistamientos y paros externos:</b> prod_worker_ot_events, prod_worker_ot y prod_worker_init; '
        .'clasificacion de prod_pause_types (3/3000 averia, 1/1000 alistamiento, 2/2000 externo, 6/6000 colacion). '
        .'Las aperturas cuentan como alistamiento. Los motivos indicados tienen reglas fijas; no dependen de una seleccion mensual. Los nombres de mantencion se leen de equipo_manttype.</p>'
        .'<p><b>Eventos sin OT:</b> production_machine_standby_events de TRZ. No se considera toda parada sin OT como averia.</p>'
        .'<p><b>Tiempo disponible</b> = horas totales / mes - colacion mensual - preventivo programado. Colacion mensual = dias disponibles x 1.800 segundos. El preventivo realizado no se descuenta otra vez.<br>'
        .'<b>Operativo bruto</b> = disponible - averias - alistamientos - externos.<br>'
        .'<b>Operativo mantencion</b> = disponible - averias.<br>'
        .'<b>Disponibilidad real</b> = operativo mantencion / disponible x 100.</p>'
        .'<p><b>Cumplimiento:</b> disponibilidad real mayor o igual a meta, sin redondear antes de comparar. '
        .'El total compara el promedio simple de disponibilidades con el promedio simple de metas, como la planilla. '
        .'No exige que todas las maquinas cumplan individualmente.</p>'
        .'<p><b>Control:</b> se excluyen eventos y OTs anulados, se recortan tiempos al periodo 26-25 y se unen intervalos duplicados por maquina. '
        .'Los cierres registrados de OT y sesion limitan la duracion de eventos heredados. Si falta el termino, se usa la siguiente conexion registrada del mismo operario o, para una apertura, el inicio de produccion de esa OT; el detalle identifica ese limite. Sin ningun limite registrado, la duracion queda sin determinar y no se inventan horas. Eventos realmente abiertos, motivos no reconocidos, datos ausentes, tiempos incompatibles o categorias superpuestas dejan el bono PENDIENTE. '
        .'Un periodo cuyo dia 25 aun no termino no determina el bono definitivo.</p>'
        .'<p><b>Importe:</b> este modulo determina cumplimiento, no un monto a pagar: no se proporciono una tarifa ni una regla de pago.</p>';
}

function maintenanceBonusDetail(array $report, bool $excel=false): string
{
    $html=maintenanceBonusCalendar($report).'<table class="maintenance-table"><thead><tr><th>Máquina</th><th>Programación</th><th>Colación registrada (h)</th>'
        .'<th>Preventivo realizado (h)</th><th>Pendientes por maquina</th></tr></thead><tbody>';
    foreach ($report['rows'] as $row) {
        $ready=empty($row['warnings']) && in_array($row['status']??'', ['CUMPLE','NO CUMPLE'],true);
        $html.='<tr'.(!$excel && $ready ? ' class="maintenance-ready"' : '').'><td class="text-cell">'.h($row['name']).'</td><td class="text-cell">'.h($row['schedule_source']).'</td>';
        foreach (['recorded_lunch','recorded_preventive'] as $key) {
            $html.='<td class="num-dec3" data-xlsx-value="'.($row[$key]/3600).'">'.number_format($row[$key]/3600,3,',','').'</td>';
        }
        $html.='<td class="text-cell">'.h(implode('; ',$row['warnings']) ?: 'Sin pendientes').'</td></tr>';
    }
    $html.='</tbody></table><h3>Eventos del mes</h3><table class="maintenance-table"><thead><tr>'
        .'<th>Maquina</th><th>Origen</th><th>Evento</th><th>Motivo</th><th>Clasificacion</th><th>Inicio</th><th>Termino</th><th>Origen del termino</th><th>Horas</th><th>Observaciones</th>'
        .(!$excel ? '<th>Acciones</th>' : '').'</tr></thead><tbody>';
    foreach ($report['detail'] as $event) {
        $html.='<tr>';
        $values=[$event['machine_name'],$event['source'],(string)$event['id'],$event['reason'],
            MaintenanceBonusService::CATEGORIES[$event['category']],date('d/m/Y H:i:s',$event['clipped_start']),
            $event['open'] ? 'Sin finalizar' : date('d/m/Y H:i:s',$event['clipped_end']),
            $event['end_source']??'Evento'];
        foreach ($values as $value) $html.='<td class="text-cell">'.h((string)$value).'</td>';
        $html.=$event['open'] ? '<td class="text-cell">Sin determinar</td>'
            : '<td class="num-dec3" data-xlsx-value="'.($event['seconds']/3600).'">'.number_format($event['seconds']/3600,3,',','').'</td>';
        $html.='<td class="text-cell">'.h((string)$event['comments']).'</td>';
        if (!$excel) $html.='<td><button type="button" class="btn secondary" data-activity-detail-source="'
            .($event['source']==='ERP' ? 'ERP' : 'STANDBY').'" data-activity-detail-id="'.(int)$event['id'].'">Revisar</button></td>';
        $html.='</tr>';
    }
    if (!$report['detail']) $html.='<tr><td colspan="'.($excel?10:11).'">Sin eventos registrados.</td></tr>';
    return $html.'</tbody></table><p>El detalle lista eventos individuales; el resumen une intervalos repetidos y no duplica su tiempo.</p>';
}

function maintenanceBonusCalendar(array $report): string
{
    $calendar=$report['calendar'];
    $html='<h3>Calendario del bono</h3><p>'.date('d/m/Y',$report['period']['start']).' al '
        .date('d/m/Y',$report['period']['end']-1).' | Lunes a viernes: '.$calendar['weekdays']
        .' | Dias habiles netos: <b>'.$calendar['days'].'</b> | Colacion: <b>'
        .h(MaintenanceBonusService::duration($calendar['lunch_seconds'])).'</b></p>'
        .'<table class="maintenance-table"><thead><tr><th>Fecha</th><th>Feriado nacional</th><th>Descontado</th></tr></thead><tbody>';
    foreach ($calendar['holidays'] as $holiday) {
        $html.='<tr><td class="text-cell">'.h(date('d/m/Y',strtotime($holiday['date']))).'</td>'
            .'<td class="text-cell">'.h($holiday['name']).'</td><td class="text-cell">'
            .($holiday['deducted'] ? 'Si' : 'No, cae en fin de semana').'</td></tr>';
    }
    if (!$calendar['holidays']) $html.='<tr><td colspan="3">Sin feriados nacionales en el periodo.</td></tr>';
    $html.='</tbody></table><p>Fuentes del calendario:</p><ul>';
    foreach ($calendar['sources'] as $year=>$source) $html.='<li>'.(int)$year.': '.h($source).'</li>';
    return $html.'</ul>';
}

function maintenanceBonusHistory(array $entries, array $machines): string
{
    $names=array_column($machines,'name','id');
    $flatten=static function(array $policy) use ($names): array {
        $values=[];
        if (!$policy) return $values;
        $values['Minutos colacion / dia']=(string)($policy['lunch_minutes']??30);
        $source=$policy['lunch_source']??'planned';
        $values['Origen colacion']=$source==='business_calendar' ? '30 minutos por dia habil automatico' : ($source==='planned' ? 'Programada' : 'Registrada');
        foreach ($policy['machines']??[] as $id=>$row) {
            $name=$names[$id]??('Maquina #'.$id);
            $values[$name.' - Participa']=!empty($row['active']) ? 'Si' : 'No';
            $values[$name.' - Preventivo programado (h)']=(string)$row['preventive_hours'];
            $values[$name.' - Meta (%)']=(string)$row['target_percent'];
        }
        foreach ($policy['schedule']??[] as $id=>$row) {
            $name=($names[$id]??('Maquina #'.$id)).' - Jornada '.($policy['schedule_month']??'');
            $values[$name]=(isset($row['days']) ? $row['days'].' dias' : 'Dias habiles automaticos').' x '.$row['daily_hours'].' h';
        }
        foreach ($policy['mapping']??[] as $key=>$value) $values['Motivo '.$key]=MaintenanceBonusService::CATEGORIES[$value]??$value;
        return $values;
    };
    $html='';
    foreach ($entries as $entry) {
        $before=$flatten(json_decode((string)$entry['before_json'],true)?:[]);
        $after=$flatten(json_decode((string)$entry['after_json'],true)?:[]);
        $html.='<h3>'.h($entry['actor']).' | '.h(date('d/m/Y H:i',strtotime($entry['created_at']))).'</h3>'
            .'<table class="maintenance-table"><thead><tr><th>Dato</th><th>Antes</th><th>Despues</th></tr></thead><tbody>';
        foreach (array_unique(array_merge(array_keys($before),array_keys($after))) as $key) {
            if (($before[$key]??null)===($after[$key]??null)) continue;
            $html.='<tr><td>'.h($key).'</td><td>'.h($before[$key]??'Sin configurar').'</td>'
                .'<td>'.h($after[$key]??'Sin configurar').'</td></tr>';
        }
        $html.='</tbody></table>';
    }
    return $html ?: '<p>Sin modificaciones en este mes.</p>';
}

/** Lista exclusiva de eventos a corregir / Only records requiring review. */
function maintenanceBonusProblemEvents(array $report): string
{
    $events=$report['problem_events']??MaintenanceBonusService::problemEvents($report['detail'],$report['period']);
    $editable=canManageMaintenanceBonus();
    $html='<div class="maintenance-toolbar"><label>Buscar <input type="search" data-maintenance-problem-filter placeholder="Maquina, CC, operario o problema"></label>'
        .'<span data-maintenance-problem-count role="status">'.count($events).' eventos con problemas</span></div>'
        .'<p>Periodo: '.date('d/m/Y',$report['period']['start']).' al '.date('d/m/Y',$report['period']['end']-1).'</p>'
        .'<div class="maintenance-scroll"><table class="maintenance-table maintenance-problems"><thead><tr>'
        .'<th>Maquina</th><th>Evento</th><th>CC</th><th>Operario</th><th>Motivo</th><th>Inicio</th><th>Termino registrado</th><th>Duracion</th><th>Problema</th><th>Acciones</th></tr></thead><tbody>';
    foreach ($events as $event) {
        $issues=implode('; ',array_unique($event['issues']));
        $search=implode(' ',[$event['machine_name'],$event['source'].' #'.$event['id'],$event['ot_number']??'',
            $event['operator']??'',$event['reason'],$issues]);
        $html.='<tr data-maintenance-problem-search="'.h($search).'">';
        foreach ([$event['machine_name'],$event['source'].' #'.$event['id'],$event['ot_number']??'Sin OT',
            $event['operator']??'',$event['reason'],date('d/m/Y H:i:s',(int)$event['start_ts']),
            (int)$event['end_ts']>0?date('d/m/Y H:i:s',(int)$event['end_ts']):'Sin finalizar',
            $event['review_seconds']===null?'Sin determinar':MaintenanceBonusService::duration($event['review_seconds'])] as $value) {
            $html.='<td class="text-cell">'.h((string)$value).'</td>';
        }
        $html.='<td class="text-cell maintenance-problem-cause">'.h($issues).'</td><td>';
        $actions=['detail'=>'Revisar'];
        if ($editable) $actions+=['edit'=>'Editar'];
        if ($editable && (int)$event['end_ts']<=0) $actions+=['close'=>'Finalizar'];
        foreach ($actions as $mode=>$label) {
            $html.='<button type="button" class="btn secondary" data-activity-detail-source="'.($event['source']==='ERP'?'ERP':'STANDBY')
                .'" data-activity-detail-id="'.(int)$event['id'].'" data-activity-detail-mode="'.$mode.'">'.$label.'</button>';
        }
        $html.='</td></tr>';
    }
    $html.='<tr data-maintenance-problem-empty'.($events?' hidden':'').'><td colspan="10">'
        .($events?'Sin eventos para esta busqueda.':'Sin eventos con problemas en este periodo. Los pendientes de configuracion se revisan en Configuracion.')
        .'</td></tr>';
    return $html.'</tbody></table></div>';
}

/** Aviso agrupado reutilizado despues de recalcular / Reusable grouped pending notice. */
function maintenanceBonusPending(array $rows, int $problemCount=0): string
{
    $pending=array_filter($rows,static fn($row)=>$row['warnings']);
    if (!$pending && !$problemCount) return '';
    $causes=[];
    foreach ($pending as $row) {
        foreach (array_unique($row['warnings']) as $warning) $causes[$warning]=($causes[$warning]??0)+1;
    }
    $html='<div class="maintenance-notice-copy"><strong>'
        .($problemCount ? $problemCount.' eventos con problemas' : count($pending).' '.(count($pending)===1?'máquina pendiente':'máquinas pendientes'))
        .'</strong><div class="maintenance-causes">';
    foreach ($causes as $warning=>$count) {
        $html.='<span class="maintenance-cause">'.h($warning).'<b>'.$count.'</b></span>';
    }
    return $html.'</div></div><button type="button" class="btn btn-filter-secondary" data-maintenance-open="problems">Ver pendientes</button>';
}

function renderMaintenanceBonus(array $report, BonusConfigurationService $config): string
{
    $month=$report['month'];
    $policy=$report['policy'];
    $editable=canManageMaintenanceBonus();
    $disabled=$editable ? '' : ' disabled';
    $summaryClass=$report['summary']['status']==='CUMPLE'?'yes':($report['summary']['status']==='NO CUMPLE'?'no':'pending');
    $html=bonusFilterStyles().'<style>
        .maintenance-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:16px;margin-bottom:14px}
        .maintenance-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .maintenance-header .erp-filter-title{margin:0;text-transform:none;font-size:22px}
        .maintenance-header .maintenance-header-top{display:flex;justify-content:space-between;align-items:center;gap:16px}
        .maintenance-summary-group{display:flex;align-items:center;gap:10px;font-size:12px;color:#64748b}
        .maintenance-controls{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;padding-top:16px;border-top:1px solid #f1f5f9}
        .maintenance-controls .erp-filter-form{width:auto;padding:0;border:0;flex-direction:row;align-items:flex-end}
        .maintenance-controls .erp-filter-field input{width:210px;min-width:0}
        .maintenance-header-actions{display:flex;gap:8px;flex-wrap:wrap}
        .maintenance-header-actions .btn{width:auto;padding:0 13px}
        .maintenance-header .btn-filter-secondary:focus-visible,.maintenance-notice .btn:focus-visible{outline:3px solid #93c5fd;outline-offset:2px}
        .maintenance-header-actions .maintenance-alert-button{border-color:#fecaca;background:#fff1f2;color:#9f1239}
        .maintenance-activity-count{min-width:20px;padding:2px 5px;border-radius:5px;background:#ffe4e6;font-size:11px}
        .maintenance-header [hidden],.maintenance-notice[hidden]{display:none!important}
        .maintenance-meta{display:flex;flex-wrap:wrap;gap:12px 28px;padding:12px 16px;background:#f8fafc;border:1px solid #f1f5f9;border-radius:10px}
        .maintenance-meta-item{display:flex;align-items:center;gap:8px;font-size:12px;color:#64748b}
        .maintenance-meta-item strong{color:#334155;font-size:13px;font-variant-numeric:tabular-nums}
        .maintenance-notice{display:flex;align-items:center;justify-content:space-between;gap:16px;background:#fffbeb;border:1px solid #fde68a;border-left:4px solid #f59e0b;border-radius:12px;padding:14px 18px;margin-bottom:16px;color:#92400e}
        .maintenance-notice-copy{min-width:0}.maintenance-notice-copy>strong{font-size:13px}
        .maintenance-causes{display:flex;gap:6px;flex-wrap:wrap;margin-top:7px}
        .maintenance-cause{display:inline-flex;align-items:center;gap:8px;font-size:12px}
        .maintenance-cause:not(:last-child){margin-right:6px;padding-right:12px;border-right:1px solid #fde68a}
        .maintenance-cause b{background:#fef3c7;border-radius:5px;padding:2px 6px;font-size:11px}
        .maintenance-notice>.btn{flex-shrink:0;width:auto;height:34px;font-size:12px}
        @media(max-width:640px){
            .maintenance-header.erp-filter-card{padding:16px;gap:14px}
            .maintenance-header .maintenance-header-top{align-items:flex-start;flex-direction:column;gap:10px}
            .maintenance-controls{align-items:stretch;gap:14px}
            .maintenance-controls .erp-filter-form{width:100%;flex-wrap:nowrap}
            .maintenance-controls .erp-filter-field:first-of-type{flex:1;min-width:0}
            .maintenance-controls .erp-filter-field input{width:100%}
            .maintenance-header-actions{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));width:100%}
            .maintenance-header-actions .btn{justify-content:center;padding:0 8px;font-size:12px}
            .maintenance-meta{gap:10px;padding:12px}
            .maintenance-meta-item{width:100%;justify-content:space-between}
            .maintenance-notice{align-items:flex-start;flex-direction:column;padding:14px}
            .maintenance-cause:not(:last-child){border:0}
        }
        .maintenance-scroll{overflow:auto;max-height:65vh}
        .maintenance-table{border-collapse:collapse;width:100%;font-size:12px}
        .maintenance-table th,.maintenance-table td{border:1px solid #e2e8f0;padding:8px;text-align:right;white-space:nowrap}
        .maintenance-table th{background:#eff6ff;color:#334155;white-space:normal;position:sticky;top:0;z-index:1}
        .maintenance-table td:nth-child(2),.maintenance-table .text-cell{text-align:left}
        .maintenance-table .maintenance-ready td{background:#dcfce7;color:#166534}
        .maintenance-table .maintenance-ready td:first-child,.maintenance-table .maintenance-ready td:last-child{font-weight:800}
        .maintenance-problems .maintenance-problem-cause{white-space:normal;min-width:240px;color:#991b1b;font-weight:700}
        .maintenance-problems td:last-child .btn{margin:2px}
        .maintenance-config-actions{position:sticky;top:0;z-index:3;background:#fff;padding:12px 0;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
        .maintenance-table tfoot{background:#f1f5f9;font-weight:800}
        .maintenance-status{font-weight:800;padding:4px 7px;border-radius:6px;display:inline-block}
        .maintenance-status.yes{color:#166534;background:#dcfce7}.maintenance-status.no{color:#991b1b;background:#fee2e2}
        .maintenance-status.pending{color:#92400e;background:#fef3c7}
        .maintenance-dialog{width:96vw;max-width:1500px;height:90vh;border:0;border-radius:14px;padding:0}
        .maintenance-dialog::backdrop{background:#0f172aa8}
        .maintenance-dialog-head{display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom:1px solid #e2e8f0}
        .maintenance-dialog-body{padding:18px;overflow:auto;height:calc(90vh - 75px);box-sizing:border-box}
        .maintenance-dialog input:not([type=checkbox]),.maintenance-dialog select{max-width:170px;padding:7px;border:1px solid #cbd5e1;border-radius:7px}
        .maintenance-dialog p{line-height:1.5}.maintenance-dialog input:disabled{background:#f1f5f9}
        </style><section class="erp-filter-card maintenance-header" aria-labelledby="maintenance-title">'
        .'<div class="maintenance-header-top"><h2 class="erp-filter-title" id="maintenance-title">Bono mantención</h2>'
        .'<div class="maintenance-summary-group"><span>Estado general</span><span id="maintenance-summary" class="maintenance-status '.$summaryClass.'" role="status">'.h($report['summary']['status']).'</span></div></div>'
        .'<div class="maintenance-controls"><form method="get" action="/bonificaciones" class="erp-filter-form" id="bonus-filter-form-maintenance">'
        .'<input type="hidden" name="view" value="bonomantencion"><div class="erp-filter-field bonus-filter-period-box">'
        .'<label for="month_maintenance">Mes (Ciclo 26–25)</label><input type="month" name="month" id="month_maintenance" value="'.h($month).'" required></div>'
        .'<div class="erp-filter-field erp-filter-actions"><button class="btn btn-filter-apply" type="submit">Consultar</button></div></form>'
        .'<div class="maintenance-header-actions" role="group" aria-label="Acciones del bono">'
        .'<a class="btn btn-filter-secondary" href="/bonificaciones/mantencion/export-xls?month='.h($month).'">Excel</a>';
    foreach (['activities'=>'Abiertas','config'=>'Configuración','detail'=>'Detalle','sources'=>'Cálculo','history'=>'Historial'] as $id=>$label) {
        $html.='<button type="button" class="btn btn-filter-secondary" data-maintenance-open="'.$id.'">'.$label
            .($id==='activities'?'<span class="maintenance-activity-count" data-activity-count hidden></span>':'').'</button>';
    }
    $html.='<button type="button" class="btn btn-filter-secondary" data-maintenance-open="activities" data-maintenance-activity-scope="old">Antiguos</button>';
    $html.='</div></div><div class="maintenance-meta">'
        .'<div class="maintenance-meta-item"><span>Período</span><strong>'.date('d/m/Y',$report['period']['start']).' al '.date('d/m/Y',$report['period']['end']-1).'</strong></div>'
        .'<div class="maintenance-meta-item"><span>Días hábiles</span><strong>'.$report['calendar']['days'].'</strong></div>'
        .'<div class="maintenance-meta-item"><span>Colación mensual</span><strong>'.h(MaintenanceBonusService::duration($report['calendar']['lunch_seconds'])).'</strong></div>'
        .'</div></section>';
    $error=trim((string)($_GET['error']??''));
    if ($error!=='') $html.='<div class="maintenance-card" style="color:#991b1b">'.h($error).'</div>';
    if (isset($_GET['saved'])) $html.='<div class="maintenance-card">Configuracion guardada.</div>';
    $pendingHtml=maintenanceBonusPending($report['rows'],count($report['problem_events']??[]));
    $html.='<div id="maintenance-pending" class="maintenance-notice" role="status"'.($pendingHtml===''?' hidden':'').'>'.$pendingHtml.'</div>';
    $html.='<div id="maintenance-results" class="maintenance-card maintenance-scroll">'.maintenanceBonusTable($report).'</div>';
    $contents=['activities'=>renderOpenActivityPanel($report),'detail'=>maintenanceBonusDetail($report),
        'problems'=>maintenanceBonusProblemEvents($report),'sources'=>maintenanceBonusSources(),
        'history'=>maintenanceBonusHistory($config->listHistory($month,'bonomantencion'),$report['machines'])];
    $form='<p>Vigencia desde '.h(substr($month,5,2).'/'.substr($month,0,4)).'. Metas, preventivo y clasificaciones se mantienen hacia adelante; los meses anteriores no cambian.</p>'
        .'<form id="maintenance-config-form" method="post" action="/bonificaciones/config/maintenance-save"><input type="hidden" name="_csrf" value="'.h(csrfToken()).'">'
        .'<input type="hidden" name="month" value="'.h($month).'">'
        .'<div class="maintenance-config-actions"><button class="btn" type="submit"'.$disabled.'>Guardar</button>'
        .'<button class="btn secondary" type="button" id="maintenance-config-discard" hidden>Descartar</button>'
        .'<span id="maintenance-config-state" role="status">'.(!empty($policy['effective_from'])?'Configuracion vigente desde '.h(substr($policy['effective_from'],5,2).'/'.substr($policy['effective_from'],0,4)):'Configuracion pendiente de confirmar').'</span></div>'
        .'<p>Dias y colacion automaticos: '.$report['calendar']['days'].' dias habiles x 30 minutos = '
        .h(MaintenanceBonusService::duration($report['calendar']['lunch_seconds'])).'. No se pueden modificar.</p>'
        .'<p>Jornada automatica: 42 / 5 = 8:24:00 por dia. Los valores sugeridos de preventivo y meta deben confirmarse. La vista previa se actualiza al editar; Guardar aplica los cambios al informe y al Excel.</p>'
        .'<table class="maintenance-table"><thead><tr><th>Participa</th><th>Maquina</th><th>Preventivo programado (h)</th><th>Meta (%)</th><th>Dias habiles (automaticos)</th><th>Horas / dia (automaticas)</th><th>Horas totales / mes</th><th>Tiempo disponible (vista previa)</th></tr></thead><tbody>';
    $programmed=MaintenanceBonusService::programmedTime($report['calendar']['days']);
    foreach ($report['machines'] as $machine) {
        $id=(int)$machine['id'];
        $row=$policy['machines'][$id];
        $prefix='machines['.$id.']';
        $form.='<tr data-maintenance-days="'.$programmed['days'].'" data-maintenance-daily="'.$programmed['daily']
            .'" data-maintenance-lunch="'.$programmed['lunch'].'"><td><input type="hidden" name="'.$prefix.'[active]" value="0"><input type="checkbox" name="'.$prefix.'[active]" value="1"'.(!empty($row['active'])?' checked':'').$disabled.'></td>'
            .'<td class="text-cell">'.h($machine['name']).'</td>';
        foreach (['preventive_hours'=>$row['preventive_hours'],'target_percent'=>$row['target_percent']] as $field=>$value) {
            $labels=['preventive_hours'=>'Preventivo programado','target_percent'=>'Meta'];
            $form.='<td><input aria-label="'.h($machine['name'].' '.$labels[$field]).'" name="'.$prefix.'['.$field.']"'
                .($field==='preventive_hours' ? ' data-maintenance-preventive' : '')
                .' inputmode="decimal" value="'.h((string)$value).'"'.$disabled.'></td>';
        }
        $form.='<td>'.$programmed['days'].'</td><td>'.h(MaintenanceBonusService::duration($programmed['daily'])).'</td>'
            .'<td data-maintenance-total>'.h(MaintenanceBonusService::duration($programmed['total'])).'</td>'
            .'<td data-maintenance-available aria-live="polite">'.h(MaintenanceBonusService::duration(
                $programmed['total']-$programmed['lunch']-(float)$row['preventive_hours']*3600)).'</td></tr>';
    }
    $form.='</tbody></table><h3>Clasificacion fija de motivos</h3>'
        .'<table class="maintenance-table"><thead><tr><th>Motivo</th><th>Clasificacion</th></tr></thead><tbody>';
    $groupedReasons=[];
    foreach ($report['reasons'] as $key=>$reason) {
        $group=mb_strtolower(trim($reason['label']),'UTF-8').'|'.$reason['category'];
        if (!isset($groupedReasons[$group])) $groupedReasons[$group]=$reason+['keys'=>[]];
        $groupedReasons[$group]['keys'][]=(string)$key;
    }
    foreach ($groupedReasons as $reason) {
        $form.='<tr><td class="text-cell">'.h($reason['label']).' <small>('.h(implode(', ',$reason['keys'])).')</small></td>'
            .'<td class="text-cell">'.h(MaintenanceBonusService::CATEGORIES[$reason['category']]).'</td></tr>';
    }
    $form.='</tbody></table><p>Preventivo realizado es informativo: se descuenta el preventivo programado una sola vez, como en tu planilla.</p>'
        .'<button class="btn" type="submit"'.$disabled.'>Guardar</button>'
        .(!$editable ? '<p>Solo Javier y Hector pueden modificar la configuracion.</p>' : '').'</form>';
    $contents['config']=$form;
    foreach ($contents as $id=>$content) {
        $titles=['activities'=>'Actividades abiertas','detail'=>'Detalle','problems'=>'Ver pendientes','sources'=>'Cálculo y fuentes','history'=>'Historial','config'=>'Configuración'];
        $html.='<dialog class="maintenance-dialog" id="maintenance-'.$id.'"><div class="maintenance-dialog-head">'
            .'<b>Bono mantención: '.$titles[$id].'</b><button type="button" class="btn secondary" data-maintenance-close>Cerrar</button></div>'
            .'<div class="maintenance-dialog-body">'.$content.'</div></dialog>';
    }
    $html.='<script>(function(){document.addEventListener("click",function(e){var b=e.target.closest("[data-maintenance-open]");if(b){document.getElementById("maintenance-"+b.dataset.maintenanceOpen).showModal();return;}b=e.target.closest("[data-maintenance-close]");if(b){var dialog=b.closest("dialog");if(dialog.id==="maintenance-config"&&dialog.dataset.dirty==="1"){document.getElementById("maintenance-config-state").textContent="Guarda los cambios o pulsa Descartar antes de cerrar.";return;}dialog.close();}});'
        .'document.addEventListener("input",function(e){if(!e.target.matches("[data-maintenance-problem-filter]"))return;var body=e.target.closest(".maintenance-dialog-body"),search=e.target.value.trim().toLocaleLowerCase("es"),count=0;body.querySelectorAll("[data-maintenance-problem-search]").forEach(function(row){row.hidden=!row.dataset.maintenanceProblemSearch.toLocaleLowerCase("es").includes(search);if(!row.hidden)count++;});body.querySelector("[data-maintenance-problem-count]").textContent=count+" eventos con problemas";body.querySelector("[data-maintenance-problem-empty]").hidden=count>0;});'
        .'function duration(s){var n=Math.round(Math.abs(s));return (s<0?"-":"")+Math.floor(n/3600)+":"+String(Math.floor(n%3600/60)).padStart(2,"0")+":"+String(n%60).padStart(2,"0");}'
        .'document.querySelectorAll("[data-maintenance-preventive]").forEach(function(input){function update(){var row=input.closest("tr"),raw=input.value.trim().replace(",","."),hours=Number(raw),total=Number(row.dataset.maintenanceDays)*Number(row.dataset.maintenanceDaily);'
        .'var valid=/^[+-]?(?:[0-9]+(?:\\.[0-9]*)?|\\.[0-9]+)(?:[eE][+-]?[0-9]+)?$/.test(raw)&&Number.isFinite(hours)&&hours>=0&&hours<=744;'
        .'row.querySelector("[data-maintenance-total]").textContent=duration(total);row.querySelector("[data-maintenance-available]").textContent=valid?duration(total-Number(row.dataset.maintenanceLunch)-hours*3600):"Dato no valido";}'
        .'input.addEventListener("input",update);input.addEventListener("change",update);update();});';
    $html.='var cfg=document.getElementById("maintenance-config"),cfgForm=document.getElementById("maintenance-config-form"),cfgState=document.getElementById("maintenance-config-state"),discard=document.getElementById("maintenance-config-discard");'
        .'cfgForm.addEventListener("input",function(){cfg.dataset.dirty="1";cfgState.textContent="Cambios sin guardar";discard.hidden=false;});cfgForm.addEventListener("change",function(){cfg.dataset.dirty="1";cfgState.textContent="Cambios sin guardar";discard.hidden=false;});'
        .'cfg.addEventListener("cancel",function(e){if(cfg.dataset.dirty==="1"){e.preventDefault();cfgState.textContent="Guarda los cambios o pulsa Descartar antes de cerrar.";}});'
        .'discard.addEventListener("click",function(){cfgForm.reset();cfg.dataset.dirty="0";discard.hidden=true;cfgState.textContent="Cambios descartados";document.querySelectorAll("[data-maintenance-preventive]").forEach(function(i){i.dispatchEvent(new Event("input"));});cfg.dataset.dirty="0";discard.hidden=true;cfgState.textContent="Cambios descartados";});'
        .'document.addEventListener("click",function(e){var link=e.target.closest("a");if(link&&cfg.dataset.dirty==="1"){e.preventDefault();if(!cfg.open)cfg.showModal();cfgState.textContent="Guarda los cambios o pulsa Descartar antes de salir.";}});';
    $html.='document.addEventListener("submit",function(e){if(e.target!==cfgForm&&cfg.dataset.dirty==="1"){e.preventDefault();if(!cfg.open)cfg.showModal();cfgState.textContent="Guarda los cambios o pulsa Descartar antes de cambiar el periodo.";}});';
    if (($_GET['view']??'')==='configuracion') $html.='document.getElementById("maintenance-config").showModal();';
    return $html.'})();</script>';
}

/** Un solo constructor para la descarga y su verificacion / Single export builder. */
function buildMaintenanceBonusExcel(array $report): string
{
    $month=$report['month'];
    $heading='<html><head><meta charset="UTF-8"></head><body><div class="title">Bono mantención</div>'
        .'<div class="meta">Mes: '.h(substr($month,5,2).'/'.substr($month,0,4)).' | Horas numericas decimales en anexos. '
        .'Periodo: '.date('d/m/Y',$report['period']['start']).' al '.date('d/m/Y',$report['period']['end']-1)
        .' | Dias habiles: '.$report['calendar']['days'].' | Disponibilidad como porcentaje numerico. Estado: '
        .h($report['summary']['status']).'</div>';
    return MaintenanceBonusWorkbook::build($report,[
        ['title'=>'Detalle','html'=>$heading.maintenanceBonusDetail($report,true).'</body></html>'],
        ['title'=>'Calculo y fuentes','html'=>$heading.'<table><tr><td class="text-cell">'
            .maintenanceBonusSources().'</td></tr></table></body></html>'],
    ]);
}

function handleMaintenanceBonusRoutes(string $path, string $method, PDO $erp, PDO $trz): bool
{
    if (handleOpenActivityRoutes($path,$method,$erp,$trz)) return true;
    $page=$path==='/bonificaciones' && $method==='GET'
        && (($_GET['view']??'')==='bonomantencion' || (($_GET['view']??'')==='configuracion' && ($_GET['bonus']??'')==='bonomantencion'));
    $export=$path==='/bonificaciones/mantencion/export-xls' && $method==='GET';
    $save=$path==='/bonificaciones/config/maintenance-save' && $method==='POST';
    if (!$page && !$export && !$save) return false;
    if (!unibagIsAuthenticated()) {
        http_response_code(403);
        render('Bonificaciones','<div class="card">Debes iniciar sesion para consultar el bono.</div>');
        return true;
    }
    $defaultMonth=BonusConfigurationService::monthForDate(date('Y-m-d'),date('Y-m'));
    $month=(string)($save ? ($_POST['month']??'') : ($_GET['month']??$_SESSION['maintenance_bonus_month']??$defaultMonth));
    $config=new BonusConfigurationService($trz);
    $service=new MaintenanceBonusService($erp,$trz,$config);
    if ($save) {
        if (!canManageMaintenanceBonus()) {
            http_response_code(403);
            render('Bonificaciones','<div class="card">Solo Javier y Hector pueden modificar la configuracion de mantencion.</div>');
            return true;
        }
        requireCsrf();
        try {
            $service->save($month,$_POST,bonusConfigurationActor());
            $_SESSION['maintenance_bonus_month']=$month;
            header('Location: /bonificaciones?'.http_build_query(['view'=>'bonomantencion','month'=>$month,'saved'=>1]));
        } catch (Throwable $e) {
            error_log('Maintenance bonus configuration: '.$e->getMessage());
            $message=$e instanceof PDOException ? 'No se pudo guardar la configuracion.' : $e->getMessage();
            header('Location: /bonificaciones?'.http_build_query(['view'=>'configuracion','bonus'=>'bonomantencion','month'=>$month,'error'=>$message]));
        }
        return true;
    }
    try {
        $report=$service->report($month);
        if ($page) $_SESSION['maintenance_bonus_month']=$month;
        if ($export) {
            SimpleXlsx::stream('bono_mantencion_'.$month.'.xlsx',buildMaintenanceBonusExcel($report));
            return true;
        }
        render('Bono mantención',renderMaintenanceBonus($report,$config));
    } catch (Throwable $e) {
        error_log('Maintenance bonus report: '.$e->getMessage());
        http_response_code(422);
        $message=$e instanceof PDOException ? 'No se pudo consultar una fuente de datos. No se calculo cumplimiento con datos incompletos.' : $e->getMessage();
        render('Bono mantención','<div class="card">'.h($message).'<p><a class="btn secondary" href="/bonificaciones?view=bonomantencion">Volver</a></p></div>');
    }
    return true;
}
