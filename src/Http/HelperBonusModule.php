<?php
declare(strict_types=1);

/** ERP supplies names only; settings and evaluations belong to TRZ.
 * El ERP aporta nombres; configuraciones y evaluaciones pertenecen a TRZ. */
function handleHelperBonusConfiguration(ReceptionService $service, BonusConfigurationService $config): void
{
    requireBonusConfigurationEditAccess();
    requireCsrf();
    $month = (string)($_POST['month_key'] ?? '');
    $target = '/bonificaciones?'.http_build_query(['view'=>'configuracion','bonus'=>'bonoayudante','month'=>$month]);
    try {
        $name = trim((string)($_POST['operator'] ?? ''));
        $allowed = $service->listErpWorkerNames();
        if (!in_array($name, $allowed, true)) throw new RuntimeException('Selecciona un operador del listado ERP.');
        $config->saveHelperConfiguration($month, $name, [
            'active'=>isset($_POST['active']),
            'matrix_proactividad_clp'=>$_POST['matrix_proactividad_clp'] ?? null,
            'matrix_eficiencia_clp'=>$_POST['matrix_eficiencia_clp'] ?? null,
            'matrix_multitarea_clp'=>$_POST['matrix_multitarea_clp'] ?? null,
            'fixed_clp'=>$_POST['fixed_clp'] ?? null,
            'instructions'=>$_POST['instructions'] ?? '',
        ], bonusConfigurationActor());
        header('Location: '.$target.'&saved=1');
    } catch (Throwable $e) {
        error_log('Helper bonus configuration: '.$e->getMessage());
        $message = $e instanceof PDOException ? 'No se pudo guardar el bono de ayudante.' : $e->getMessage();
        header('Location: '.$target.'&error='.rawurlencode($message));
    }
    exit;
}

function helperBonusDefaultSettings(): array
{
    return ['active'=>false,'matrix_proactividad_clp'=>0,'matrix_eficiencia_clp'=>0,
        'matrix_multitarea_clp'=>0,'fixed_clp'=>0,'instructions'=>''];
}

function handleHelperBonusRecipients(ReceptionService $service, BonusConfigurationService $config): void
{
    requireBonusConfigurationEditAccess();
    requireCsrf();
    $month = (string)($_POST['month_key'] ?? '');
    $view = ($_POST['return_view'] ?? '') === 'configuracion' ? 'configuracion' : 'bonoayudante';
    $target = '/bonificaciones?'.http_build_query(['view'=>$view,'bonus'=>'bonoayudante','month'=>$month]);
    try {
        if (($_POST['recipient_form'] ?? '') !== '1') throw new RuntimeException('Formulario de seleccion incompleto.');
        $selected = $_POST['selected'] ?? [];
        if (!is_array($selected)) throw new RuntimeException('Seleccion de operadores no valida.');
        foreach ($selected as $name) if (!is_string($name)) throw new RuntimeException('Operador no valido.');
        $available = helperBonusErpRecipientSettings($service, $month);
        if (!$available) throw new RuntimeException('No se pudo cargar el listado del ERP. No se modifico la seleccion.');
        $config->saveHelperRecipients($month, $selected, $available, bonusConfigurationActor());
        header('Location: '.$target.'&saved=1');
    } catch (Throwable $e) {
        error_log('Helper recipients: '.$e->getMessage());
        header('Location: '.$target.'&error='.rawurlencode($e instanceof PDOException ? 'No se pudo guardar la seleccion.' : $e->getMessage()));
    }
    exit;
}

function helperBonusErpRecipientSettings(ReceptionService $service, string $month): array
{
    $settings = $service->getBonusHelperSettings($month);
    $available = [];
    // TRZ supplies settings, never additional identities.
    // TRZ aporta configuracion, nunca identidades adicionales.
    foreach ($service->listErpWorkerNames() as $name) {
        $available[$name] = $settings[$name] ?? helperBonusDefaultSettings();
    }
    return $available;
}

function handleHelperBonusEvaluationSave(ReceptionService $service): void
{
    requireBonusConfigurationEditAccess();
    requireCsrf();
    header('Content-Type: application/json; charset=utf-8');
    try {
        $month = (string)($_POST['month_key'] ?? '');
        $name = trim((string)($_POST['operator'] ?? ''));
        $result = $service->saveBonusHelperMonthlyRows($month, [[
            'operator_name'=>$name,'proactividad_score'=>$_POST['p_score'] ?? null,
            'eficiencia_score'=>$_POST['e_score'] ?? null,'multitarea_score'=>$_POST['m_score'] ?? null,
            'additional_clp'=>$_POST['additional'] ?? null,'observations'=>(string)($_POST['obs'] ?? ''),
        ]], bonusConfigurationActor());
        if (empty($result['ok'])) {
            http_response_code(422);
            echo json_encode(['ok'=>false,'error'=>$result['errors'][0] ?? 'No se pudo guardar.']);
        } else {
            echo json_encode(['ok'=>true,'message'=>'Guardado']);
        }
    } catch (Throwable $e) {
        error_log('Helper evaluation save: '.$e->getMessage());
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'No se pudo guardar. Usa Guardar mes para reintentar.']);
    }
    exit;
}

function renderHelperRecipientSelector(string $month, array $names, array $settings, string $view): string
{
    $disabled = canEditBonusConfiguration() && $names ? '' : ' disabled';
    $html = '<dialog id="helper-recipients" class="helper-bonus-dialog"><div class="helper-bonus-head"><h2>Seleccionar ayudantes</h2>'
        .'<button type="button" class="btn secondary" onclick="document.getElementById(\'helper-recipients\').close()">Cerrar</button></div>'
        .'<div class="helper-bonus-body"><p class="muted">La seleccion rige desde '.h($month).' hacia adelante, hasta la proxima modificacion. No cambia meses anteriores.</p>'
        .'<input type="search" placeholder="Buscar operador" aria-label="Buscar en seleccion" style="width:100%;margin-bottom:14px" oninput="document.querySelectorAll(\'#helper-recipient-list label\').forEach(r=>r.hidden=!r.dataset.search.includes(this.value.toLowerCase()))">'
        .'<form method="post" action="/bonificaciones/config/helper-recipients-save">'
        .'<input type="hidden" name="_csrf" value="'.h(csrfToken()).'"><input type="hidden" name="recipient_form" value="1">'
        .'<input type="hidden" name="month_key" value="'.h($month).'"><input type="hidden" name="return_view" value="'.h($view).'">'
        .'<div id="helper-recipient-list" style="max-height:55vh;overflow:auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:8px">';
    foreach ($names as $name) {
        $html .= '<label data-search="'.h(mb_strtolower($name)).'" style="padding:10px;border:1px solid #e2e8f0;border-radius:8px">'
            .'<input type="checkbox" name="selected[]" value="'.h($name).'"'.(!empty($settings[$name]['active']) ? ' checked' : '').$disabled.'> '.h($name).'</label>';
    }
    if (!$names) $html .= '<p class="muted">No hay operadores disponibles.</p>';
    return $html.'</div><div style="display:flex;justify-content:flex-end;margin-top:16px"><button class="btn"'.$disabled.'>Guardar seleccion</button></div></form></div></dialog>';
}

function helperBonusStyles(): string
{
    return '<style>
        .helper-bonus-table{width:100%;border-collapse:collapse;font-size:13px}
        .helper-bonus-table th{background:#f1f5f9;text-align:left;color:#334155}
        .helper-bonus-table td,.helper-bonus-table th{padding:10px 12px;border-bottom:1px solid #e2e8f0}
        .helper-bonus-table tbody tr:nth-child(even){background:#f8fafc}
        .helper-bonus-scroll{max-height:65vh;overflow:auto}
        .helper-bonus-scroll thead{position:sticky;top:0;z-index:1}
        .helper-bonus-table input,.helper-bonus-table select,.helper-bonus-dialog input,.helper-bonus-dialog textarea{padding:8px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box;max-width:100%}
        .helper-bonus-dialog{width:min(1100px,96vw);max-height:92vh;padding:0;border:1px solid #e2e8f0;border-radius:14px;color:#0f172a}
        .helper-bonus-dialog::backdrop{background:#0f172aaa}
        .helper-bonus-head{padding:16px;display:flex;justify-content:space-between;gap:12px;align-items:center;border-bottom:1px solid #e2e8f0;background:#f8fafc}
        .helper-bonus-head h2{margin:0;font-size:18px}
        .helper-bonus-body{padding:18px;overflow:auto;max-height:calc(92vh - 90px)}
        .helper-bonus-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:16px 0}
        .helper-bonus-grid input{display:block;width:100%;margin-top:5px}
        .helper-bonus-toolbar{display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-bottom:14px}
        @media(max-width:640px){.helper-bonus-grid{grid-template-columns:1fr}}
    </style>';
}

function renderHelperBonusConfiguration(string $month, array $erpNames, array $settings, array $legacyRows, array $history): string
{
    $disabled = canEditBonusConfiguration() ? '' : ' disabled';
    $legacy = array_column($legacyRows, null, 'operator_name');
    $recipientNames = array_values(array_unique($erpNames));
    sort($recipientNames, SORT_NATURAL | SORT_FLAG_CASE);
    $names = [];
    foreach ($recipientNames as $name) {
        $data = $settings[$name] ?? ['active'=>isset($legacy[$name])];
        if (!empty($data['active'])) $names[] = $name;
    }
    $money = static fn($value): string => '$ '.number_format((float)$value,0,',','.');
    $html = helperBonusStyles().'<div class="card"><div style="font-weight:900;margin-bottom:12px">Bonos maximos por ayudante</div>'
        .'<div class="helper-bonus-toolbar"><form method="get" action="/bonificaciones" style="display:flex;gap:8px;align-items:end;margin:0">'
        .'<input type="hidden" name="view" value="configuracion"><input type="hidden" name="bonus" value="bonoayudante">'
        .'<label>Mes<br><input type="month" name="month" required value="'.h($month).'"></label><button class="btn secondary">Cargar mes</button></form>'
        .'<input type="search" placeholder="Buscar operador" aria-label="Buscar operador" oninput="document.querySelectorAll(\'#helper-config-list tr[data-search]\').forEach(r=>r.hidden=!r.dataset.search.includes(this.value.toLowerCase()))">'
        .'<button type="button" class="btn"'.$disabled.' onclick="document.getElementById(\'helper-recipients\').showModal()">Seleccionar</button>'
        .'<button type="button" class="btn secondary" onclick="document.getElementById(\'helper-history\').showModal()">Historial</button></div>'
        .'<p class="muted">Cada bono = maximo x porcentaje de la nota. Seleccion, maximos e indicaciones rigen desde el mes elegido hacia adelante. Los meses anteriores no cambian.</p>';
    if (!$erpNames) $html .= '<p class="err">No se pudo cargar el listado del ERP. No se muestran operadores de trazabilidad.</p>';
    $html .= '<div class="table-wrap helper-bonus-scroll"><table id="helper-config-list" class="helper-bonus-table"><thead><tr>'
        .'<th>Operador</th><th>Estado</th><th>Proactividad</th><th>Eficiencia</th><th>Multitarea</th><th>Maximo variable</th><th>Fijo</th><th>Indicaciones</th><th>Accion</th></tr></thead><tbody>';
    foreach ($names as $name) {
        $data = $settings[$name] ?? (($legacy[$name] ?? []) + ['active'=>isset($legacy[$name])]);
        $data += ['active'=>false,'matrix_proactividad_clp'=>0,'matrix_eficiencia_clp'=>0,'matrix_multitarea_clp'=>0,'fixed_clp'=>0,'instructions'=>''];
        $effectiveLabel = !empty($data['effective_from']) ? substr($data['effective_from'],5,2).'/'.substr($data['effective_from'],0,4) : 'Registro anterior';
        $html .= '<tr data-search="'.h(mb_strtolower($name)).'"><td><b>'.h($name).'</b></td><td>'.(!empty($data['active'])?'Participa':'No participa')
            .'<div class="muted">Desde '.h($effectiveLabel).'</div></td>';
        $maximum = 0;
        foreach (['matrix_proactividad_clp','matrix_eficiencia_clp','matrix_multitarea_clp'] as $field) {
            $maximum += (float)$data[$field];
            $html .= '<td>'.$money($data[$field]).'</td>';
        }
        $html .= '<td><b>'.$money($maximum).'</b></td><td>'.$money($data['fixed_clp']).'</td><td style="max-width:250px;white-space:pre-wrap;overflow-wrap:anywhere">'.h($data['instructions'] ?: 'Sin indicaciones').'</td>'
            .'<td><button type="button" class="btn secondary"'.$disabled.' data-settings="'.h(json_encode(['operator'=>$name]+$data,JSON_THROW_ON_ERROR)).'" onclick="helperEdit(this)">Modificar</button></td></tr>';
    }
    if (!$names) $html .= '<tr><td colspan="9">Sin ayudantes seleccionados. Usa Seleccionar para agregarlos.</td></tr>';
    $html .= '</tbody></table></div></div><dialog id="helper-editor" class="helper-bonus-dialog"><div class="helper-bonus-head"><h2 id="helper-editor-title">Bono ayudante</h2>'
        .'<button class="btn secondary" type="button" onclick="document.getElementById(\'helper-editor\').close()">Cerrar</button></div><div class="helper-bonus-body">'
        .'<form id="helper-editor-form" method="post" action="/bonificaciones/config/helper-save">'
        .'<input type="hidden" name="_csrf" value="'.h(csrfToken()).'"><input type="hidden" name="month_key" value="'.h($month).'"><input type="hidden" name="operator">'
        .'<label><input type="checkbox" name="active"'.$disabled.'> Participa desde este mes</label><div class="helper-bonus-grid">';
    foreach (['matrix_proactividad_clp'=>'Maximo Proactividad','matrix_eficiencia_clp'=>'Maximo Eficiencia','matrix_multitarea_clp'=>'Maximo Multitarea','fixed_clp'=>'Bono fijo'] as $field=>$label) {
        $html .= '<label>'.h($label).' (CLP)<input name="'.$field.'" type="number" min="0" max="9999999999.99" step="0.01" required'.$disabled.'></label>';
    }
    $html .= '</div><label>Indicaciones<textarea name="instructions" maxlength="2000" rows="5" style="display:block;width:100%;margin-top:5px"'.$disabled.'></textarea></label>'
        .'<p class="muted">Nota 10 paga el 100% del maximo; nota 8 paga el 80%. Esta configuracion se aplica desde '.h($month).' hacia adelante. Las notas siguen siendo propias de cada mes.</p>'
        .'<button class="btn"'.$disabled.'>Guardar</button></form></div></dialog>'
        .'<dialog id="helper-history" class="helper-bonus-dialog"><div class="helper-bonus-head"><h2>Historial de ayudantes</h2>'
        .'<button class="btn secondary" type="button" onclick="document.getElementById(\'helper-history\').close()">Cerrar</button></div><div class="helper-bonus-body">';
    foreach ($history as $entry) {
        $description = bonusHistoryChanges($entry);
        $html .= '<div style="margin-bottom:18px"><b>'.h($description['title']).'</b><p class="muted">'.h($entry['actor']).' · '.h(date('d/m/Y H:i',strtotime($entry['created_at']))).'</p>'
            .'<table class="helper-bonus-table"><thead><tr><th>Dato</th><th>Antes</th><th>Despues</th></tr></thead><tbody>';
        foreach ($description['changes'] as [$label,$before,$after]) $html .= '<tr><td>'.h($label).'</td><td style="white-space:pre-wrap">'.h($before).'</td><td style="white-space:pre-wrap">'.h($after).'</td></tr>';
        $html .= '</tbody></table></div>';
    }
    if (!$history) $html .= '<p class="muted">Sin modificaciones registradas para este mes.</p>';
    return $html.'</div></dialog>'.renderHelperRecipientSelector($month,$recipientNames,$settings,'configuracion').'<script>
        function helperEdit(button){
            const data=JSON.parse(button.dataset.settings),form=document.getElementById("helper-editor-form");
            for(const key of ["operator","matrix_proactividad_clp","matrix_eficiencia_clp","matrix_multitarea_clp","fixed_clp","instructions"]) form.elements.namedItem(key).value=data[key];
            form.elements.namedItem("active").checked=!!data.active;
            document.getElementById("helper-editor-title").textContent="Bono ayudante: "+data.operator;
            document.getElementById("helper-editor").showModal();
        }
    </script>';
}

function renderHelperBonusEvaluation(string $month, array $rows, array $recipientNames = [], array $settings = []): string
{
    $disabled = canEditBonusConfiguration() ? '' : ' disabled';
    $money = static fn($value): string => '$ '.number_format((float)$value,0,',','.');
    $html = helperBonusStyles().'<div class="card"><div style="font-weight:900;margin-bottom:12px">Bono ayudante</div>'
        .'<div class="helper-bonus-toolbar"><form method="get" action="/bonificaciones" style="display:flex;gap:8px;align-items:end;margin:0">'
        .'<input type="hidden" name="view" value="bonoayudante"><label>Mes<br><input type="month" name="month" required value="'.h($month).'"></label>'
        .'<button class="btn secondary">Ver mes</button></form><button type="button" class="btn"'.$disabled.' onclick="document.getElementById(\'helper-recipients\').showModal()">Seleccionar</button>'
        .'<a class="btn secondary" href="/bonificaciones?view=configuracion&amp;bonus=bonoayudante&amp;month='.h($month).'">Configuracion</a>'
        .'<a id="helper-export" class="btn secondary" href="/bonificaciones/ayudante/export-xls?month='.h($month).'">Excel</a>'
        .'<input type="search" placeholder="Buscar ayudante" aria-label="Buscar ayudante" oninput="document.querySelectorAll(\'#helper-evaluations tr[data-helper]\').forEach(r=>r.hidden=!r.dataset.helper.includes(this.value.toLowerCase()))"></div>'
        .'<p class="muted">Notas de 0 a 10, con decimales. Porcentaje por tramo entero: 7,25 = 70%, 8,75 = 80%. Guardado automatico por mes.</p>';
    $recipientNames = array_values(array_unique($recipientNames));
    sort($recipientNames, SORT_NATURAL | SORT_FLAG_CASE);
    $erpSet = array_fill_keys($recipientNames, true);
    $rows = array_values(array_filter($rows, static fn(array $row): bool => isset($erpSet[$row['operator_name']])));
    $selector = renderHelperRecipientSelector($month,$recipientNames,$settings,'bonoayudante');
    if (!$rows) return $html.'<p class="muted">Usa Seleccionar para elegir a quienes reciben el bono.</p></div>'.$selector;
    $html .= '<form method="post" action="/bonificaciones/ayudante/month-save">'
        .'<input type="hidden" name="_csrf" value="'.h(csrfToken()).'"><input type="hidden" name="month" value="'.h($month).'">'
        .'<div class="table-wrap helper-bonus-scroll"><table id="helper-evaluations" class="helper-bonus-table"><thead><tr>'
        .'<th>Ayudante e indicaciones</th><th>Proactividad</th><th>Eficiencia</th><th>Multitarea</th><th>Variable</th><th>Fijo</th><th>Incentivo adicional</th><th>Total a pagar</th><th>Observaciones</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $name = (string)$row['operator_name'];
        $variable = 0.0;
        $fixed = (float)$row['fixed_clp'];
        $additional = (float)$row['additional_clp'];
        $html .= '<tr data-helper="'.h(mb_strtolower($name)).'" data-fixed="'.h((string)$fixed).'"><td style="min-width:180px;max-width:300px"><b>'.h($name).'</b>'
            .'<input type="hidden" name="operators[]" value="'.h($name).'"><div class="muted" style="white-space:pre-wrap;overflow-wrap:anywhere;margin-top:6px">'.h($row['instructions'] ?: 'Sin indicaciones').'</div></td>';
        foreach (['p'=>'proactividad','e'=>'eficiencia','m'=>'multitarea'] as $prefix=>$category) {
            $score = (float)$row[$category.'_score'];
            $maximum = (float)$row['matrix_'.$category.'_clp'];
            $factor = BonusConfigurationService::helperScoreFactor($score);
            $paid = $maximum * $factor;
            $variable += $paid;
            $html .= '<td><label class="muted">Nota<br><input type="text" inputmode="decimal" required pattern="[0-9]+([.,][0-9]{1,2})?" aria-label="Nota '.h($category).' de '.h($name).'" name="'.$prefix.'_score[]" value="'.h(str_replace('.',',',(string)$score)).'" data-maximum="'.h((string)$maximum).'" style="width:75px"'.$disabled.'></label>'
                .'<div class="muted" data-score-percent>'.(int)($factor*100).'%</div><div class="muted" style="margin-top:5px;white-space:nowrap">Max. '.$money($maximum).'</div><b data-category-paid>'.$money($paid).'</b></td>';
        }
        $html .= '<td data-variable style="white-space:nowrap">'.$money($variable).'</td><td style="white-space:nowrap">'.$money($fixed).'</td>'
            .'<td><input aria-label="Incentivo adicional de '.h($name).'" type="number" name="additional[]" min="0" max="9999999999.99" step="0.01" value="'.h((string)$additional).'" style="width:120px"'.$disabled.'></td>'
            .'<td style="white-space:nowrap"><b data-total>'.$money($variable+$fixed+$additional).'</b></td>'
            .'<td><input aria-label="Observaciones de '.h($name).'" name="obs[]" maxlength="255" value="'.h($row['observations']).'" style="width:220px"'.$disabled.'>'
            .'<div class="muted" data-save-status role="status">'.(!empty($row['has_evaluation']) ? 'Guardado' : 'Sin evaluar').'</div></td></tr>';
    }
    $exportError = '<dialog id="helper-export-error" class="helper-bonus-dialog"><div class="helper-bonus-head"><h2>Excel</h2>'
        .'<button type="button" class="btn secondary" onclick="document.getElementById(\'helper-export-error\').close()">Cerrar</button></div>'
        .'<div class="helper-bonus-body">Hay calificaciones sin guardar. Usa Guardar mes y vuelve a descargar el Excel.</div></dialog>';
    return $html.'</tbody></table></div><div style="display:flex;justify-content:flex-end;margin-top:16px"><button class="btn"'.$disabled.'>Guardar mes</button></div></form></div>'.$selector.$exportError.'<script>
        document.getElementById("helper-evaluations").addEventListener("input",function(event){
            const row=event.target.closest("tr[data-helper]");if(!row)return;
            const money=n=>"$ "+Math.round(n).toLocaleString("es-CL");let variable=0;
            row.querySelectorAll("input[data-maximum]").forEach(input=>{
                const score=Number(input.value.replace(",","."));
                const factor=Number.isFinite(score)?Math.floor(score)/10:0;
                const paid=Number(input.dataset.maximum)*factor;variable+=paid;
                input.closest("td").querySelector("[data-category-paid]").textContent=money(paid);
                input.closest("td").querySelector("[data-score-percent]").textContent=Math.round(factor*100)+"%";
            });
            row.querySelector("[data-variable]").textContent=money(variable);
            row.querySelector("[data-total]").textContent=money(variable+Number(row.dataset.fixed)+Number(row.querySelector("input[name=\'additional[]\']").value));
            helperQueueSave(row,event.target.tagName==="SELECT"?0:350);
        });
        const helperSaveStates=new Map(),helperTable=document.getElementById("helper-evaluations");
        function helperState(row){
            if(!helperSaveStates.has(row))helperSaveStates.set(row,{dirty:false,busy:false,failed:false,timer:null,waiters:[]});
            return helperSaveStates.get(row);
        }
        function helperQueueSave(row,delay){
            const state=helperState(row);state.dirty=true;clearTimeout(state.timer);
            row.querySelector("[data-save-status]").textContent="Pendiente de guardar";
            if(delay)state.timer=setTimeout(()=>helperSaveRow(row),delay);else helperSaveRow(row);
        }
        async function helperSaveRow(row){
            const state=helperState(row);clearTimeout(state.timer);state.timer=null;
            if(state.busy||!state.dirty)return;
            state.dirty=false;state.busy=true;
            const form=helperTable.closest("form"),status=row.querySelector("[data-save-status]");
            const field=name=>row.querySelector("[name=\'"+name+"[]\']").value;
            const body=new URLSearchParams({_csrf:form.elements.namedItem("_csrf").value,
                month_key:form.elements.namedItem("month").value,operator:field("operators"),
                p_score:field("p_score"),e_score:field("e_score"),m_score:field("m_score"),
                additional:field("additional"),obs:field("obs")});
            status.textContent="Guardando...";
            try{
                const response=await fetch("/bonificaciones/ayudante/evaluation-save",{method:"POST",body,credentials:"same-origin",keepalive:true});
                const result=await response.json();
                if(!response.ok||!result.ok)throw new Error(result.error||"No se pudo guardar");
                state.failed=false;
                if(!state.dirty)status.textContent="Guardado";
            }catch(error){
                state.failed=true;
                status.textContent="No guardado. Usa Guardar mes para reintentar.";
            }finally{
                state.busy=false;
                if(state.dirty)helperSaveRow(row);
                else{state.waiters.forEach(resolve=>resolve());state.waiters=[];}
            }
        }
        helperTable.addEventListener("change",event=>{
            const row=event.target.closest("tr[data-helper]");
            if(row&&(event.target.tagName!=="SELECT"||!helperSaveStates.has(row)))helperQueueSave(row,0);
        });
        helperTable.closest("form").addEventListener("submit",async event=>{
            const pending=[...helperSaveStates].filter(([row,state])=>state.dirty||state.busy);
            if(!pending.length)return;
            event.preventDefault();
            await Promise.all(pending.map(([row,state])=>new Promise(resolve=>{
                state.waiters.push(resolve);clearTimeout(state.timer);helperSaveRow(row);
            })));
            event.target.submit();
        });
        document.getElementById("helper-export").addEventListener("click",async event=>{
            const downloadUrl=event.currentTarget.href;
            let pending=[...helperSaveStates].filter(([row,state])=>state.dirty||state.busy);
            const failed=[...helperSaveStates.values()].some(state=>state.failed);
            if(!pending.length&&!failed)return;
            event.preventDefault();
            while(pending.length){
                await Promise.all(pending.map(([row,state])=>new Promise(resolve=>{
                    state.waiters.push(resolve);clearTimeout(state.timer);helperSaveRow(row);
                })));
                pending=[...helperSaveStates].filter(([row,state])=>state.dirty||state.busy);
            }
            if([...helperSaveStates.values()].some(state=>state.failed)){
                document.getElementById("helper-export-error").showModal();return;
            }
            window.location.assign(downloadUrl);
        });
    </script>';
}
