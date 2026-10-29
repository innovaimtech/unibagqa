<?php
declare(strict_types=1);

require_once __DIR__.'/../OpenActivityService.php';

function handleOpenActivityRoutes(string $path,string $method,PDO $erp,PDO $trz): bool
{
    $list=$path==='/bonificaciones/mantencion/activities' && $method==='GET';
    $detail=$path==='/bonificaciones/mantencion/activity' && $method==='GET';
    $impact=$path==='/bonificaciones/mantencion/activity-impact' && $method==='GET';
    $save=$path==='/bonificaciones/mantencion/activity-save' && $method==='POST';
    if (!$list && !$detail && !$impact && !$save) return false;
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!unibagIsAuthenticated() || !userCanAccessArea('ERP',sessionAreaPermissions())) {
        http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Debes tener acceso al ERP.']); return true;
    }
    if ($save && !canEditBonusConfiguration()) {
        http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Solo los responsables autorizados pueden intervenir actividades.']); return true;
    }
    if ($save) requireCsrf();
    try {
        $service=new OpenActivityService($erp,$trz);
        if ($list) {
            $period=MaintenanceBonusService::period((string)($_GET['month']??date('Y-m')));
            $result=['ok'=>true]+$service->monitor(null,$period);
        }
        elseif ($impact) {
            $month=(string)($_GET['month']??'');
            $report=(new MaintenanceBonusService($erp,$trz,new BonusConfigurationService($trz)))->report($month);
            $result=['ok'=>true,'html'=>maintenanceBonusTable($report),'detail'=>maintenanceBonusDetail($report),'summary'=>$report['summary']['status'],
                'pending_html'=>maintenanceBonusPending($report['rows']),
                'pending'=>array_values(array_map(static fn($row)=>['name'=>$row['name'],'warnings'=>$row['warnings']],
                    array_filter($report['rows'],static fn($row)=>$row['warnings'])))];
        } elseif ($detail) {
            $item=$service->get((string)($_GET['source']??''),(int)($_GET['id']??0));
            unset($item['raw']);
            $history=$service->history($item['source'],$item['id']);
            foreach ($history as &$entry) {
                $entry['before']=json_decode($entry['before_json'],true);
                $entry['after']=json_decode($entry['after_json'],true);
                unset($entry['before_json'],$entry['after_json']);
            }
            $result=['ok'=>true,'item'=>$item,'history'=>$history];
        } else $result=$service->change($_POST,bonusConfigurationActor());
        echo json_encode($result,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $e) {
        error_log('Open activity: '.$e->getMessage());
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>$e instanceof PDOException ? 'No se pudo consultar o modificar la actividad.' : $e->getMessage()],JSON_INVALID_UTF8_SUBSTITUTE);
    }
    return true;
}

/** Modales densos; ningun cierre se ejecuta por el monitoreo.
 * Dense dialogs; monitoring only alerts and never automatically closes records. */
function renderOpenActivityPanel(array $report): string
{
    $editable=canEditBonusConfiguration();
    $period=json_encode(['start'=>$report['period']['start'],'end'=>$report['period']['end']],JSON_THROW_ON_ERROR);
    $html='<style>.maintenance-dialog [hidden]{display:none!important}</style>'
        .'<div class="maintenance-toolbar"><label>Buscar <input id="activity-filter" placeholder="Maquina, operario, OT o motivo"></label>'
        .'<label><input id="activity-only-alerts" type="checkbox"> Solo anomalias</label>'
        .'<button class="btn secondary" type="button" id="activity-refresh">Actualizar</button>'
        .'<span id="activity-monitor-status" role="status" aria-live="polite">Sin consultar</span></div>'
        .'<p>Colacion abierta: alerta despues de 1 hora. Mantencion abierta: alerta despues de 3 horas. '
        .'Actualizacion cada 60 segundos mientras la pagina este visible. Incluye actividades abiertas antiguas y fechas inconsistentes del periodo consultado.</p>'
        .'<div class="maintenance-scroll"><table class="maintenance-table"><thead><tr>'
        .'<th>Actividad</th><th>Maquina</th><th>Operario</th><th>OT</th><th>Motivo</th><th>Inicio</th><th>Tiempo abierto</th><th>Estado</th><th>Acciones</th>'
        .'</tr></thead><tbody id="activity-rows"></tbody></table></div>'
        .'<dialog class="maintenance-dialog" id="activity-editor" aria-labelledby="activity-editor-title">'
        .'<div class="maintenance-dialog-head"><b id="activity-editor-title">Detalle de actividad</b>'
        .'<button type="button" class="btn secondary" id="activity-editor-close">Cerrar ventana</button></div>'
        .'<div class="maintenance-dialog-body"><div id="activity-facts"></div><div class="maintenance-card" id="activity-impact"></div>'
        .'<div id="activity-detail-actions" class="maintenance-toolbar"><button type="button" class="btn secondary" id="activity-detail-edit">Editar</button>'
        .'<button type="button" class="btn" id="activity-detail-finish">Finalizar</button></div>'
        .'<div id="activity-edit-message" role="alert" style="white-space:normal"></div>'
        .'<form id="activity-edit-form"><input name="_csrf" type="hidden" value="'.h(csrfToken()).'">'
        .'<input name="source" type="hidden"><input name="id" type="hidden"><input name="version" type="hidden"><input name="action" type="hidden">'
        .'<div class="maintenance-toolbar"><label>Inicio (dd/mm/aaaa hh:mm:ss)<input name="start" required></label>'
        .'<label>Termino (dd/mm/aaaa hh:mm:ss)<input name="end"></label></div>'
        .'<label>Observaciones<textarea name="comments" rows="3" maxlength="5000" style="display:block;width:95%"></textarea></label>'
        .'<div class="maintenance-toolbar"><label>Causa<select name="diagnosis" required><option value="">Seleccionar</option>'
        .'<option value="forgotten">Olvido de cierre</option><option value="system_error">Error del sistema</option>'
        .'<option value="verified">Actividad verificada</option></select></label>'
        .'<label>Revision realizada<input name="notes" required minlength="5" maxlength="1000"></label>'
        .'<label>Supervisor<input name="supervisor" required autocomplete="off"></label>'
        .'<label>Clave<input name="password" type="password" required autocomplete="new-password"></label></div>'
        .'<p>Modifica solo horarios y observaciones. No cierra la OT, no cambia cantidades ni movimientos de bodega. '
        .'La clasificacion del motivo permanece fija. El supervisor confirma la causa; el sistema no puede deducir por si solo un olvido o un error.</p>'
        .'<button class="btn" type="submit" id="activity-submit">Guardar</button></form>'
        .(!$editable ? '<p>Solo lectura: las intervenciones requieren un responsable autorizado y credenciales de supervisor.</p>' : '')
        .'<h3>Historial de intervenciones</h3><div id="activity-audit"></div></div></dialog>';
    $script=<<<'JS'
<script>(function(){
const period=__PERIOD__,month=__MONTH__,editable=__EDITABLE__,panel=document.getElementById("maintenance-activities"),
rows=document.getElementById("activity-rows"),editor=document.getElementById("activity-editor"),
form=document.getElementById("activity-edit-form"),status=document.getElementById("activity-monitor-status");
let items=[],server=Date.now()/1000,received=performance.now(),busy=false,editing=null;
function el(tag,text){const e=document.createElement(tag);if(text!==undefined)e.textContent=text;return e;}
function date(ts){if(!ts)return "Sin finalizar";const d=new Date(ts*1000),parts=new Intl.DateTimeFormat("es-CL",{timeZone:"America/Santiago",year:"numeric",month:"2-digit",day:"2-digit",hour:"2-digit",minute:"2-digit",second:"2-digit",hourCycle:"h23"}).formatToParts(d),p={};parts.forEach(v=>p[v.type]=v.value);return p.day+"/"+p.month+"/"+p.year+" "+p.hour+":"+p.minute+":"+p.second;}
function duration(s){s=Math.max(0,Math.floor(s));return Math.floor(s/3600)+":"+String(Math.floor(s%3600/60)).padStart(2,"0")+":"+String(s%60).padStart(2,"0");}
function now(){return server+(performance.now()-received)/1000;}
function alertFor(i){const elapsed=Math.max(0,now()-i.start),exceeded=i.open&&i.alert.limit!==null&&elapsed>i.alert.limit;return {elapsed,anomaly:i.alert.anomaly||exceeded,label:exceeded?"Umbral excedido":i.alert.label};}
async function request(url,options){const r=await fetch(url,options),data=await r.json();if(!r.ok||!data.ok)throw Error(data.error||"No se pudo completar la solicitud.");return data;}
function paint(){
rows.replaceChildren();const search=document.getElementById("activity-filter").value.toLocaleLowerCase("es"),only=document.getElementById("activity-only-alerts").checked;
items.forEach(i=>{const a=alertFor(i);if(only&&!a.anomaly)return;if(![i.key,i.reason,i.operator,i.machine_name,i.ot_number].join(" ").toLocaleLowerCase("es").includes(search))return;
const tr=el("tr");if(a.anomaly)tr.style.background="#fff1f2";
[i.key,i.machine_name,i.operator,i.ot_number||("Proceso "+(i.ot_id||"sin OT")),i.reason,date(i.start),i.open?duration(a.elapsed):duration(i.end-i.start),a.anomaly?a.label:(i.open?"Abierta":"Revision de fechas/sincronizacion")].forEach(v=>{const td=el("td",v);td.className="text-cell";tr.append(td);});
const actions=el("td");[["detail","Detalle"],...(editable?[["close","Finalizar"],["edit","Editar"]]:[])].forEach(([mode,label])=>{const b=el("button",label);b.type="button";b.className="btn secondary";b.addEventListener("click",()=>open(i,mode));actions.append(b);});tr.append(actions);rows.append(tr);
});if(!rows.children.length){const td=el("td","Sin actividades para estos filtros.");td.colSpan=9;const tr=el("tr");tr.append(td);rows.append(tr);}
}
async function load(){if(busy)return;busy=true;try{const data=await request("/bonificaciones/mantencion/activities?month="+encodeURIComponent(month));items=data.items;server=data.server_time;received=performance.now();status.textContent=items.length+" actividades; "+data.anomalies+" anomalias. "+(data.truncated?"Listado limitado a 500 registros por fuente; revisar pendientes adicionales.":"Actualizado "+date(server));document.querySelectorAll('[data-maintenance-open="activities"]').forEach(b=>{const count=b.querySelector("[data-activity-count]");if(count){count.textContent=data.anomalies;count.hidden=!data.anomalies;}else b.textContent="Abiertas ("+data.anomalies+")";b.classList.toggle("maintenance-alert-button",data.anomalies>0);b.setAttribute("aria-label","Abiertas: "+data.anomalies+" anomalias");});paint();}catch(e){status.textContent="No se pudo actualizar: "+e.message;}finally{busy=false;}}
async function open(item,mode){
try{const data=await request("/bonificaciones/mantencion/activity?source="+encodeURIComponent(item.source)+"&id="+item.id);editing=data.item;form.reset();
document.getElementById("activity-editor-title").textContent=(mode==="close"?"Finalizar":mode==="edit"?"Editar":"Detalle")+" "+editing.key;
const facts=document.getElementById("activity-facts");facts.replaceChildren();const table=el("table");table.className="maintenance-table";
const limits=editing.parent_end?date(editing.parent_end):"Sin cierre de OT/sesion registrado";
Object.entries({"Maquina":editing.machine_name,"Operario":editing.operator,"OT":editing.ot_number||"Sin OT numerada","Proceso":editing.ot_id||"Sin OT","Sesion":editing.init_id||"Sin sesion","Motivo":editing.reason,"Inicio original":date(editing.start),"Termino original":date(editing.end),"Limite de OT/sesion":limits,"Umbral":editing.alert.limit?duration(editing.alert.limit):"No definido para este tipo","Estado":alertFor(editing).label}).forEach(([k,v])=>{const tr=el("tr");tr.append(el("th",k),el("td",v));table.append(tr);});facts.append(table);
document.getElementById("activity-impact").textContent="Impacto: "+editing.impact+" "+(editing.open?"Un termino ausente impide validar la duracion. ":"")+(editing.start>=period.end||editing.end&&editing.end<=period.start?"Fuera del periodo del bono consultado; puede afectar otros periodos.":"Coincide con el periodo del bono consultado.")+" Los intervalos repetidos se unen por maquina; cerrar un registro no garantiza por si solo el cumplimiento.";
for(const [name,value] of Object.entries({source:editing.source,id:editing.id,version:editing.version,action:mode==="close"?"close":"edit",start:date(editing.start),end:editing.end?date(editing.end):"",comments:editing.comments}))form.elements[name].value=value;
form.elements.start.readOnly=mode==="close";form.elements.end.required=mode==="close"||!editing.open;form.hidden=mode==="detail"||!editable;document.getElementById("activity-detail-actions").hidden=!editable||mode!=="detail";document.getElementById("activity-edit-message").textContent="";
const audit=document.getElementById("activity-audit");audit.replaceChildren();const causes={forgotten:"Olvido de cierre",system_error:"Error del sistema",verified:"Actividad verificada"},states={done:"Aplicada",failed:"No aplicada",pending:"Pendiente de confirmar o sincronizar"};
data.history.forEach(h=>{audit.append(el("h4",date(h.created_at)+" | "+h.actor+" | "+(states[h.state]||h.state)));audit.append(el("p",(causes[h.diagnosis]||h.diagnosis)+": "+h.notes+" | Supervisor: "+h.supervisor));const t=el("table");t.className="maintenance-table";const head=el("tr");["Dato","Antes","Despues"].forEach(v=>head.append(el("th",v)));t.append(head);["start","end","comments",...(h.before.status!==undefined?["status"]:[])].forEach(k=>{const text=v=>k==="comments"?v:k==="status"?({1:"Abierta",2:"Finalizada"})[v]||String(v):date(v),tr=el("tr");tr.append(el("td",({start:"Inicio",end:"Termino",comments:"Observaciones",status:"Estado"})[k]),el("td",text(h.before[k])),el("td",text(h.after[k])));t.append(tr);});audit.append(t);});if(!data.history.length)audit.append(el("p","Sin intervenciones registradas."));
if(!editor.open)editor.showModal();
}catch(e){status.textContent=e.message;}
}
form.addEventListener("submit",async e=>{e.preventDefault();if(!editing)return;const submit=document.getElementById("activity-submit"),message=document.getElementById("activity-edit-message");submit.disabled=true;
try{const result=await request("/bonificaciones/mantencion/activity-save",{method:"POST",body:new FormData(form)});form.elements.password.value="";message.textContent=result.message+" "+result.warning;form.hidden=true;document.getElementById("activity-detail-actions").hidden=true;document.getElementById("activity-monitor-status").textContent=result.message;await load();document.getElementById("activity-recalculate").hidden=false;
try{const impact=await request("/bonificaciones/mantencion/activity-impact?month="+encodeURIComponent(month));document.getElementById("maintenance-results").innerHTML=impact.html;document.querySelector("#maintenance-detail .maintenance-dialog-body").innerHTML=impact.detail;const summary=document.getElementById("maintenance-summary");summary.textContent=impact.summary;summary.classList.remove("yes","no","pending");summary.classList.add(impact.summary==="CUMPLE"?"yes":impact.summary==="NO CUMPLE"?"no":"pending");const p=document.getElementById("maintenance-pending");p.innerHTML=impact.pending_html;p.hidden=!impact.pending.length;message.textContent+=" Bono recalculado.";}catch(error){message.textContent+=" No se pudo recalcular el bono: "+error.message;}
}catch(error){form.elements.password.value="";message.textContent=error.message;}finally{submit.disabled=false;}});
document.getElementById("activity-editor-close").addEventListener("click",()=>{form.elements.password.value="";editor.close();});
editor.addEventListener("close",()=>{form.elements.password.value="";});
document.getElementById("activity-refresh").addEventListener("click",load);
document.getElementById("activity-detail-edit").addEventListener("click",()=>{if(editing)open(editing,"edit");});
document.getElementById("activity-detail-finish").addEventListener("click",()=>{if(editing)open(editing,"close");});
document.getElementById("activity-filter").addEventListener("input",paint);document.getElementById("activity-only-alerts").addEventListener("change",paint);
document.querySelectorAll('[data-maintenance-open="activities"]').forEach(b=>b.addEventListener("click",load));
document.addEventListener("click",e=>{const b=e.target.closest("[data-activity-detail-source]");if(b){if(!panel.open)panel.showModal();open({source:b.dataset.activityDetailSource,id:Number(b.dataset.activityDetailId)},"detail");}});
setInterval(()=>{if(!document.hidden&&!editor.open)load();},60000);setInterval(()=>{if(panel.open&&!editor.open)paint();},1000);load();
})();</script>
JS;
    $html.='<a id="activity-recalculate" class="btn" hidden href="/bonificaciones?view=bonomantencion&amp;month='.h($report['month']).'">Recalcular bono</a>';
    return $html.str_replace(['__PERIOD__','__MONTH__','__EDITABLE__'],[$period,json_encode($report['month']),$editable?'true':'false'],$script);
}
