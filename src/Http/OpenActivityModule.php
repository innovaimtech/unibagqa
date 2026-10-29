<?php
declare(strict_types=1);

require_once __DIR__.'/../OpenActivityService.php';

function handleOpenActivityRoutes(string $path,string $method,PDO $erp,PDO $trz): bool
{
    $list=$path==='/bonificaciones/mantencion/activities' && $method==='GET';
    $detail=$path==='/bonificaciones/mantencion/activity' && $method==='GET';
    $impact=$path==='/bonificaciones/mantencion/activity-impact' && $method==='GET';
    $save=$path==='/bonificaciones/mantencion/activity-save' && $method==='POST';
    $batch=$path==='/bonificaciones/mantencion/activity-batch-save' && $method==='POST';
    if (!$list && !$detail && !$impact && !$save && !$batch) return false;
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (!unibagIsAuthenticated() || !userCanAccessArea('ERP',sessionAreaPermissions())) {
        http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Debes tener acceso al ERP.']); return true;
    }
    if (($save || $batch) && !canManageMaintenanceBonus()) {
        http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Solo Javier y Hector pueden intervenir actividades.']); return true;
    }
    if ($save || $batch) requireCsrf();
    try {
        $service=new OpenActivityService($erp,$trz);
        if ($list) {
            $period=MaintenanceBonusService::period((string)($_GET['month']??date('Y-m')));
            $result=['ok'=>true]+$service->monitor(null,$period,(string)($_GET['scope']??'period'));
        }
        elseif ($impact) {
            $month=(string)($_GET['month']??'');
            $report=(new MaintenanceBonusService($erp,$trz,new BonusConfigurationService($trz)))->report($month);
            $result=['ok'=>true,'html'=>maintenanceBonusTable($report),'detail'=>maintenanceBonusDetail($report),'summary'=>$report['summary']['status'],
                'problem_events_html'=>maintenanceBonusProblemEvents($report),
                'pending_html'=>maintenanceBonusPending($report['rows'],count($report['problem_events'])),
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
        } elseif ($batch) {
            $json=$_POST['items_json']??'';
            if (!is_string($json) || strlen($json)>20000) throw new RuntimeException('Seleccion de eventos no valida.');
            $input=$_POST;$input['items']=json_decode($json,true,512,JSON_THROW_ON_ERROR);
            $result=$service->changeBatch($input,bonusConfigurationActor());
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
    $editable=canManageMaintenanceBonus();
    $period=json_encode(['start'=>$report['period']['start'],'end'=>$report['period']['end']],JSON_THROW_ON_ERROR);
    $html='<style>.maintenance-dialog [hidden]{display:none!important}</style>'
        .'<div class="maintenance-toolbar"><label>Buscar <input id="activity-filter" placeholder="Maquina, operario, OT o motivo"></label>'
        .'<label>Eventos <select id="activity-scope"><option value="period">Del periodo</option><option value="old">Antiguos</option><option value="all">Todos</option></select></label>'
        .'<label><input id="activity-only-alerts" type="checkbox"> Solo anomalias</label>'
        .'<button class="btn secondary" type="button" id="activity-refresh">Actualizar</button>'
        .($editable?'<button class="btn" type="button" id="activity-batch-open" disabled>Finalizar seleccionados</button>':'')
        .'<span id="activity-monitor-status" role="status" aria-live="polite">Sin consultar</span></div>'
        .'<p>Antiguos: eventos anteriores al 26/'.date('m/Y',$report['period']['start']).'. Revisa o completa el termino verificado y selecciona hasta 50. Las fechas de inicio incorrectas se editan individualmente.</p>'
        .'<div class="maintenance-scroll"><table class="maintenance-table"><thead><tr>'
        .'<th>'.($editable?'<input type="checkbox" id="activity-select-visible" aria-label="Seleccionar eventos con cierre registrado">':'').'</th>'
        .'<th>Actividad</th><th>Maquina</th><th>Operario</th><th>OT</th><th>Motivo</th><th>Inicio</th><th>Termino verificado</th><th>Estado</th><th>Acciones</th>'
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
        .'<h3>Historial de intervenciones</h3><div id="activity-audit"></div></div></dialog>'
        .'<dialog class="maintenance-dialog" id="activity-batch-editor"><div class="maintenance-dialog-head"><b>Finalizar seleccionados</b>'
        .'<button type="button" class="btn secondary" id="activity-batch-close">Cerrar</button></div>'
        .'<div class="maintenance-dialog-body"><div id="activity-batch-preview"></div><div id="activity-batch-message" role="alert"></div>'
        .'<form id="activity-batch-form"><input type="hidden" name="_csrf" value="'.h(csrfToken()).'"><input type="hidden" name="items_json">'
        .'<div class="maintenance-toolbar"><label>Causa<select name="diagnosis" required><option value="">Seleccionar</option>'
        .'<option value="forgotten">Olvido de cierre</option><option value="system_error">Error del sistema</option><option value="verified">Actividad verificada</option></select></label>'
        .'<label>Revision realizada<input name="notes" required minlength="5" maxlength="850"></label>'
        .'<label>Supervisor<input name="supervisor" required autocomplete="off"></label><label>Clave<input name="password" type="password" required autocomplete="new-password"></label></div>'
        .'<p>Solo registra el cierre de cada evento seleccionado. No cierra OTs ni sesiones, no modifica produccion o stock. Cada resultado queda en el historial.</p>'
        .'<label><input type="checkbox" name="confirmed" value="1" required> Revise y confirmo los horarios de cierre mostrados arriba.</label>'
        .'<p><button class="btn" type="submit" id="activity-batch-submit"'.(!$editable?' disabled':'').'>Finalizar seleccionados</button></p>'
        .'</form></div></dialog>';
    $script=<<<'JS'
<script>(function(){
const period=__PERIOD__,month=__MONTH__,editable=__EDITABLE__,panel=document.getElementById("maintenance-activities"),
rows=document.getElementById("activity-rows"),editor=document.getElementById("activity-editor"),
form=document.getElementById("activity-edit-form"),status=document.getElementById("activity-monitor-status"),
batchEditor=document.getElementById("activity-batch-editor"),batchForm=document.getElementById("activity-batch-form"),selected=new Map(),manualEnds=new Map();
// Keep the editor independent so reviewing a monthly issue does not open the all-activities list.
// Separar el editor para corregir un pendiente sin abrir la lista de todas las actividades.
document.body.append(editor);
document.body.append(batchEditor);
let items=[],server=Date.now()/1000,received=performance.now(),busy=false,editing=null,reloadRequested=false;
function el(tag,text){const e=document.createElement(tag);if(text!==undefined)e.textContent=text;return e;}
function date(ts){if(!ts)return "Sin finalizar";const d=new Date(ts*1000),parts=new Intl.DateTimeFormat("es-CL",{timeZone:"America/Santiago",year:"numeric",month:"2-digit",day:"2-digit",hour:"2-digit",minute:"2-digit",second:"2-digit",hourCycle:"h23"}).formatToParts(d),p={};parts.forEach(v=>p[v.type]=v.value);return p.day+"/"+p.month+"/"+p.year+" "+p.hour+":"+p.minute+":"+p.second;}
function duration(s){s=Math.max(0,Math.floor(s));return Math.floor(s/3600)+":"+String(Math.floor(s%3600/60)).padStart(2,"0")+":"+String(s%60).padStart(2,"0");}
function now(){return server+(performance.now()-received)/1000;}
function closingDate(i){return manualEnds.has(i.key)?manualEnds.get(i.key):(i.suggested_end?date(i.suggested_end):"");}
function alertFor(i){const elapsed=Math.max(0,now()-i.start),exceeded=i.open&&i.alert.limit!==null&&elapsed>i.alert.limit;return {elapsed,anomaly:i.alert.anomaly||exceeded,label:exceeded?"Umbral excedido":i.alert.label};}
async function request(url,options){const r=await fetch(url,options),data=await r.json();if(!r.ok||!data.ok)throw Error(data.error||"No se pudo completar la solicitud.");return data;}
function selectionState(){const b=document.getElementById("activity-batch-open");if(b){b.disabled=!selected.size;b.textContent="Finalizar seleccionados ("+selected.size+")";}const all=document.getElementById("activity-select-visible");if(all){const choices=[...rows.querySelectorAll("[data-activity-select]:not(:disabled)")];all.checked=choices.length>0&&choices.every(c=>selected.has(c.dataset.activitySelect));all.indeterminate=!all.checked&&selected.size>0;all.disabled=!choices.length;}}
async function recalculate(){const impact=await request("/bonificaciones/mantencion/activity-impact?month="+encodeURIComponent(month));document.getElementById("maintenance-results").innerHTML=impact.html;document.querySelector("#maintenance-detail .maintenance-dialog-body").innerHTML=impact.detail;document.querySelector("#maintenance-problems .maintenance-dialog-body").innerHTML=impact.problem_events_html;const summary=document.getElementById("maintenance-summary");summary.textContent=impact.summary;summary.classList.remove("yes","no","pending");summary.classList.add(impact.summary==="CUMPLE"?"yes":impact.summary==="NO CUMPLE"?"no":"pending");const p=document.getElementById("maintenance-pending");p.innerHTML=impact.pending_html;p.hidden=!impact.pending_html;}
function paint(){
rows.replaceChildren();const search=document.getElementById("activity-filter").value.toLocaleLowerCase("es"),only=document.getElementById("activity-only-alerts").checked;
items.forEach(i=>{const a=alertFor(i);if(only&&!a.anomaly)return;if(![i.key,i.reason,i.operator,i.machine_name,i.ot_number].join(" ").toLocaleLowerCase("es").includes(search))return;
const tr=el("tr");if(a.anomaly)tr.style.background="#fff1f2";
const choice=el("td");let c=null;if(editable){c=el("input");c.type="checkbox";c.dataset.activitySelect=i.key;c.disabled=!i.open||!closingDate(i)||i.start<i.parent_start;c.checked=selected.has(i.key);c.setAttribute("aria-label","Seleccionar "+i.key);c.title=c.disabled?"Completa un horario verificado o revisa el inicio":"Termino listo para revisar y confirmar";c.addEventListener("change",()=>{if(c.checked&&selected.size>=50){c.checked=false;status.textContent="Selecciona hasta 50 eventos por operacion.";return;}if(c.checked)selected.set(i.key,i);else selected.delete(i.key);selectionState();});choice.append(c);}tr.append(choice);
[i.key,i.machine_name,i.operator,i.ot_number||("Proceso "+(i.ot_id||"sin OT")),i.reason,date(i.start)].forEach(v=>{const td=el("td",v);td.className="text-cell";tr.append(td);});
const term=el("td");term.className="text-cell";if(editable&&i.open){const field=el("input");field.type="text";field.dataset.activityBatchEnd=i.key;field.value=closingDate(i);field.placeholder="dd/mm/aaaa hh:mm:ss";field.setAttribute("aria-label","Termino verificado "+i.key);field.addEventListener("input",()=>{manualEnds.set(i.key,field.value.trim());c.disabled=!field.value.trim()||i.start<i.parent_start;if(c.disabled){c.checked=false;selected.delete(i.key);}selectionState();});term.append(field);term.append(el("div",i.suggested_end?"Respaldo: "+i.suggested_end_source:"Completar horario verificado"));}else term.textContent=i.end?date(i.end):"Requiere horario";tr.append(term);tr.append(el("td",a.anomaly?a.label:(i.open?"Abierta":"Revision de fechas/sincronizacion")));
const actions=el("td");[["detail","Detalle"],...(editable?[["close","Finalizar"],["edit","Editar"]]:[])].forEach(([mode,label])=>{const b=el("button",label);b.type="button";b.className="btn secondary";b.addEventListener("click",()=>open(i,mode));actions.append(b);});tr.append(actions);rows.append(tr);
});if(!rows.children.length){const td=el("td","Sin actividades para estos filtros.");td.colSpan=10;const tr=el("tr");tr.append(td);rows.append(tr);}selectionState();
}
async function load(){if(busy){reloadRequested=true;return;}busy=true;const scope=document.getElementById("activity-scope").value;try{const data=await request("/bonificaciones/mantencion/activities?month="+encodeURIComponent(month)+"&scope="+scope);if(scope!==document.getElementById("activity-scope").value){reloadRequested=true;return;}items=data.items;selected.clear();manualEnds.clear();server=data.server_time;received=performance.now();status.textContent=items.length+" actividades; "+data.anomalies+" anomalias. "+(data.truncated?"Listado limitado a 500 registros por fuente; al corregirlos apareceran los siguientes.":"Actualizado "+date(server));document.querySelectorAll('[data-maintenance-open="activities"]').forEach(b=>{if(b.dataset.maintenanceActivityScope)return;const count=b.querySelector("[data-activity-count]");if(count){count.textContent=data.anomalies;count.hidden=!data.anomalies;}else b.textContent="Abiertas ("+data.anomalies+")";b.classList.toggle("maintenance-alert-button",data.anomalies>0);b.setAttribute("aria-label","Abiertas: "+data.anomalies+" anomalias");});paint();}catch(e){status.textContent="No se pudo actualizar: "+e.message;}finally{busy=false;if(reloadRequested){reloadRequested=false;load();}}}
async function open(item,mode){
try{const data=await request("/bonificaciones/mantencion/activity?source="+encodeURIComponent(item.source)+"&id="+item.id);editing=data.item;form.reset();
document.getElementById("activity-editor-title").textContent=(mode==="close"?"Finalizar":mode==="edit"?"Editar":"Detalle")+" "+editing.key;
const facts=document.getElementById("activity-facts");facts.replaceChildren();const table=el("table");table.className="maintenance-table";
const limits=editing.parent_end?date(editing.parent_end):"Sin cierre de OT/sesion registrado";
Object.entries({"Maquina":editing.machine_name,"Operario":editing.operator,"OT":editing.ot_number||"Sin OT numerada","Proceso":editing.ot_id||"Sin OT","Sesion":editing.init_id||"Sin sesion","Motivo":editing.reason,"Inicio original":date(editing.start),"Termino original":date(editing.end),"Limite de OT/sesion":limits,"Umbral":editing.alert.limit?duration(editing.alert.limit):"No definido para este tipo","Estado":alertFor(editing).label}).forEach(([k,v])=>{const tr=el("tr");tr.append(el("th",k),el("td",v));table.append(tr);});facts.append(table);
if(editing.suggested_end)facts.append(el("p","Cierre registrado sugerido: "+date(editing.suggested_end)+" ("+editing.suggested_end_source+"). Revisa el horario antes de confirmar."));
document.getElementById("activity-impact").textContent="Impacto: "+editing.impact+" "+(editing.open?"Un termino ausente impide validar la duracion. ":"")+(editing.start>=period.end||editing.end&&editing.end<=period.start?"Fuera del periodo del bono consultado; puede afectar otros periodos.":"Coincide con el periodo del bono consultado.")+" Los intervalos repetidos se unen por maquina; cerrar un registro no garantiza por si solo el cumplimiento.";
for(const [name,value] of Object.entries({source:editing.source,id:editing.id,version:editing.version,action:mode==="close"?"close":"edit",start:date(editing.start),end:editing.end?date(editing.end):(mode==="close"&&editing.suggested_end?date(editing.suggested_end):""),comments:editing.comments}))form.elements[name].value=value;
form.elements.start.readOnly=mode==="close";form.elements.end.required=mode==="close"||!editing.open;form.hidden=mode==="detail"||!editable;document.getElementById("activity-detail-actions").hidden=!editable||mode!=="detail";document.getElementById("activity-edit-message").textContent="";
const audit=document.getElementById("activity-audit");audit.replaceChildren();const causes={forgotten:"Olvido de cierre",system_error:"Error del sistema",verified:"Actividad verificada"},states={done:"Aplicada",failed:"No aplicada",pending:"Pendiente de confirmar o sincronizar"};
data.history.forEach(h=>{audit.append(el("h4",date(h.created_at)+" | "+h.actor+" | "+(states[h.state]||h.state)));audit.append(el("p",(causes[h.diagnosis]||h.diagnosis)+": "+h.notes+" | Supervisor: "+h.supervisor));const t=el("table");t.className="maintenance-table";const head=el("tr");["Dato","Antes","Despues"].forEach(v=>head.append(el("th",v)));t.append(head);["start","end","comments",...(h.before.status!==undefined?["status"]:[])].forEach(k=>{const text=v=>k==="comments"?v:k==="status"?({1:"Abierta",2:"Finalizada"})[v]||String(v):date(v),tr=el("tr");tr.append(el("td",({start:"Inicio",end:"Termino",comments:"Observaciones",status:"Estado"})[k]),el("td",text(h.before[k])),el("td",text(h.after[k])));t.append(tr);});audit.append(t);});if(!data.history.length)audit.append(el("p","Sin intervenciones registradas."));
if(!editor.open)editor.showModal();
}catch(e){status.textContent=e.message;}
}
form.addEventListener("submit",async e=>{e.preventDefault();if(!editing)return;const submit=document.getElementById("activity-submit"),message=document.getElementById("activity-edit-message");submit.disabled=true;
try{const result=await request("/bonificaciones/mantencion/activity-save",{method:"POST",body:new FormData(form)});form.elements.password.value="";message.textContent=result.message+" "+result.warning;form.hidden=true;document.getElementById("activity-detail-actions").hidden=true;document.getElementById("activity-monitor-status").textContent=result.message;await load();document.getElementById("activity-recalculate").hidden=false;
try{await recalculate();message.textContent+=" Bono recalculado.";}catch(error){message.textContent+=" No se pudo recalcular el bono: "+error.message;}
}catch(error){form.elements.password.value="";message.textContent=error.message;}finally{submit.disabled=false;}});
document.getElementById("activity-editor-close").addEventListener("click",()=>{form.elements.password.value="";editor.close();});
editor.addEventListener("close",()=>{form.elements.password.value="";});
document.getElementById("activity-refresh").addEventListener("click",load);
document.getElementById("activity-detail-edit").addEventListener("click",()=>{if(editing)open(editing,"edit");});
document.getElementById("activity-detail-finish").addEventListener("click",()=>{if(editing)open(editing,"close");});
document.getElementById("activity-filter").addEventListener("input",()=>{selected.clear();paint();});document.getElementById("activity-only-alerts").addEventListener("change",()=>{selected.clear();paint();});
document.getElementById("activity-scope").addEventListener("change",load);
document.querySelectorAll('[data-maintenance-open="activities"]').forEach(b=>b.addEventListener("click",()=>{document.getElementById("activity-scope").value=b.dataset.maintenanceActivityScope||"period";load();}));
document.addEventListener("click",e=>{const b=e.target.closest("[data-activity-detail-source]");if(b){open({source:b.dataset.activityDetailSource,id:Number(b.dataset.activityDetailId)},b.dataset.activityDetailMode||"detail");}});
if(editable){
document.getElementById("activity-select-visible").addEventListener("change",e=>{selected.clear();if(e.target.checked){const keys=new Set([...rows.querySelectorAll("[data-activity-select]:not(:disabled)")].map(c=>c.dataset.activitySelect));for(const i of items){if(keys.has(i.key)&&selected.size<50)selected.set(i.key,i);}}paint();});
document.getElementById("activity-batch-open").addEventListener("click",()=>{if(!selected.size)return;batchForm.reset();batchForm.hidden=false;batchForm.elements.items_json.value=JSON.stringify([...selected.values()].map(i=>({source:i.source,id:i.id,version:i.version,end:closingDate(i)})));const preview=document.getElementById("activity-batch-preview");preview.replaceChildren();preview.append(el("p",selected.size+" eventos seleccionados. Revisa los horarios antes de confirmar."));const table=el("table");table.className="maintenance-table";const head=el("tr");["Evento","Maquina","Inicio original","Termino propuesto","Respaldo"].forEach(v=>head.append(el("th",v)));table.append(head);for(const i of selected.values()){const row=el("tr");[i.key,i.machine_name,date(i.start),closingDate(i),closingDate(i)===date(i.suggested_end)?i.suggested_end_source:"Horario verificado manualmente"].forEach(v=>row.append(el("td",v)));table.append(row);}preview.append(table);document.getElementById("activity-batch-message").textContent="";batchEditor.showModal();});
}
document.getElementById("activity-batch-close").addEventListener("click",()=>batchEditor.close());batchEditor.addEventListener("close",()=>{batchForm.elements.password.value="";});
batchForm.addEventListener("submit",async e=>{e.preventDefault();if(!editable)return;const submit=document.getElementById("activity-batch-submit"),message=document.getElementById("activity-batch-message");submit.disabled=true;try{const result=await request("/bonificaciones/mantencion/activity-batch-save",{method:"POST",body:new FormData(batchForm)});batchForm.elements.password.value="";batchForm.hidden=true;message.replaceChildren();message.append(el("p",result.applied+" finalizados; "+result.failed+" pendientes de corregir."));result.results.forEach(r=>message.append(el("p",r.key+": "+r.message+(r.warning?" "+r.warning:""))));await load();try{await recalculate();message.append(el("p","Bono recalculado."));}catch(error){message.append(el("p","No se pudo recalcular: "+error.message));}}catch(error){batchForm.elements.password.value="";message.textContent=error.message;}finally{submit.disabled=false;}});
setInterval(()=>{if(!document.hidden&&!editor.open&&!batchEditor.open&&!selected.size&&!manualEnds.size)load();},60000);load();
})();</script>
JS;
    $html.='<a id="activity-recalculate" class="btn" hidden href="/bonificaciones?view=bonomantencion&amp;month='.h($report['month']).'">Recalcular bono</a>';
    return $html.str_replace(['__PERIOD__','__MONTH__','__EDITABLE__'],[$period,json_encode($report['month']),$editable?'true':'false'],$script);
}
