<?php
declare(strict_types=1);

/**
 * =============================================================================
 * Módulo HTTP · Evaluación y Liquidación de Bono de Ayudantes
 * (src/Http/HelperBonusModule.php)
 * =============================================================================
 *
 * Responsabilidad:
 * Gestiona la calificación mensual del personal de apoyo y ayudantes en planta:
 * 1. Sincronización con ERP: Obtiene el catálogo de operarios (workers) activos en el ERP.
 * 2. Formulario de Evaluación: Permite al supervisor calificar Proactividad, Eficiencia,
 *    Multitarea e ingresar incentivos adicionales u observaciones.
 * 3. Persistencia Atómica en TRZ: Guarda la evaluación del mes en la tabla 'bonus_process_configs'
 *    y audita el historial de cambios en 'bonus_configuration_history'.
 * 4. Cálculo de Totales: Determina el monto a pagar según los topes vigentes y tramos.
 */

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
        .'<div class="helper-bonus-body"><p class="muted">La selección rige desde '.h($month).' hacia adelante, hasta la próxima modificación. No cambia meses anteriores.</p>'
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
    return $html.'</div><div style="display:flex;justify-content:flex-end;margin-top:16px"><button class="btn"'.$disabled.'>Guardar selección</button></div></form></div></dialog>';
}

function helperBonusStyles(): string
{
    // Estilos de las pantallas de Bono ayudante (evaluación mensual y configuración). Prefijo hbx- = nuevo diseño;
    // las clases helper-bonus-* se conservan porque las usan los diálogos y la tabla de configuración.
    return '<style>
        .hbx-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:22px 24px;box-shadow:0 4px 18px rgba(15,23,42,.05);margin-bottom:16px}
        .hbx-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px}
        .hbx-head h2{margin:0;font-size:22px;font-weight:900;color:#0f172a;letter-spacing:-.01em}
        .hbx-head p{margin:4px 0 0;color:#64748b;font-size:13px}
        .hbx-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
        .hbx-month{display:flex;align-items:center;gap:8px;margin:0}
        .hbx-month label{font-size:12px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:.04em}
        .hbx-month input,.hbx-search input{height:38px;padding:0 12px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;font-size:14px;box-sizing:border-box}
        .hbx-sep{width:1px;height:26px;background:#e2e8f0;margin:0 4px}
        .hbx-actions .btn,.hbx-month .btn,.hbx-save .btn{white-space:nowrap}
        .hbx-info{display:flex;gap:10px;align-items:flex-start;background:#f0fdfa;border:1px solid #99f6e4;color:#115e59;border-radius:12px;padding:11px 15px;font-size:13px;line-height:1.5;margin-bottom:16px}
        .hbx-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:18px}
        .hbx-stat{border:1px solid #e2e8f0;border-radius:12px;padding:13px 15px;background:#fff;border-top:3px solid #00A9A6}
        .hbx-stat.blue{border-top-color:#3b82f6}.hbx-stat.amber{border-top-color:#f59e0b}.hbx-stat.green{border-top-color:#16a34a}
        .hbx-stat .k{font-size:11px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#64748b}
        .hbx-stat .v{font-size:24px;font-weight:900;color:#0f172a;margin-top:2px;line-height:1.15;font-variant-numeric:tabular-nums}
        .hbx-stat .s{font-size:12px;color:#64748b;margin-top:2px}
        .hbx-tools{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:10px}
        .hbx-search{display:flex;align-items:center;gap:10px;flex:1;min-width:240px;max-width:420px}
        .hbx-search input{width:100%}
        .hbx-count{font-size:12px;color:#64748b}
        .hbx-empty{text-align:center;padding:42px 20px;border:2px dashed #cbd5e1;border-radius:14px;background:#f8fafc}
        .hbx-empty .ico{font-size:42px;line-height:1}
        .hbx-empty h3{margin:12px 0 6px;font-size:18px;color:#0f172a}
        .hbx-empty p{margin:0 auto 16px;max-width:520px;color:#64748b;font-size:14px;line-height:1.55}
        .hbx-save{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:16px}
        .helper-bonus-scroll{max-height:68vh;overflow:auto;border:1px solid #e2e8f0;border-radius:12px;background:#fff}
        .helper-bonus-table{width:100%;border-collapse:separate;border-spacing:0;font-size:13px}
        .helper-bonus-table th{background:#f1f5f9;text-align:left;color:#334155;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}
        .helper-bonus-table td,.helper-bonus-table th{padding:11px 14px;border-bottom:1px solid #eef2f7;vertical-align:middle}
        .helper-bonus-table tbody td{background:#fff}
        .helper-bonus-table tbody tr:nth-child(even) td{background:#fafcff}
        .helper-bonus-table tbody tr:hover td{background:#f0fdfa}
        .helper-bonus-table .r{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
        .helper-bonus-scroll thead th{position:sticky;top:0;z-index:3;border-bottom:1px solid #cbd5e1}
        .helper-bonus-table tfoot td{background:#f1f5f9;font-weight:900;color:#0f172a;border-top:2px solid #cbd5e1;position:sticky;bottom:0;z-index:2}
        .hbx-table{min-width:1120px}
        .hbx-sticky{position:sticky;left:0;z-index:2;box-shadow:1px 0 0 #e2e8f0;min-width:210px;max-width:300px}
        .helper-bonus-scroll thead th.hbx-sticky{z-index:4}
        .helper-bonus-table tfoot td.hbx-sticky{z-index:5}
        .hbx-name{font-weight:800;color:#0f172a}
        .hbx-instr{margin-top:4px;color:#64748b;font-size:12px;line-height:1.4;white-space:pre-wrap;overflow-wrap:anywhere}
        .hbx-instr.empty{font-style:italic;color:#94a3b8}
        .hbx-score{min-width:128px}
        .hbx-score-top{display:flex;align-items:center;gap:8px}
        .hbx-note{width:64px;text-align:center;font-weight:800}
        .hbx-pct{font-size:12px;font-weight:800;color:#0f766e;background:#ccfbf1;border-radius:999px;padding:2px 8px;white-space:nowrap}
        .hbx-paid{margin-top:5px;font-size:12px;color:#64748b;white-space:nowrap}
        .hbx-paid b{color:#0f172a;font-size:13px;margin-right:4px}
        .hbx-money{display:flex;align-items:center;gap:4px}
        .hbx-money span{color:#64748b;font-weight:700}
        .hbx-total{font-size:15px;font-weight:900;color:#0f766e}
        .hbx-status{display:inline-block;margin-top:5px;padding:1px 9px;border-radius:999px;font-size:11px;font-weight:700;background:#f1f5f9;color:#64748b}
        .hbx-status[data-state=saved]{background:#dcfce7;color:#166534}
        .hbx-status[data-state=pending]{background:#fef3c7;color:#92400e}
        .hbx-status[data-state=error]{background:#fee2e2;color:#991b1b}
        .hbx-pill{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:800}
        .hbx-pill.on{background:#dcfce7;color:#166534}.hbx-pill.off{background:#f1f5f9;color:#64748b}
        .helper-bonus-table input,.helper-bonus-table select,.helper-bonus-dialog input,.helper-bonus-dialog textarea{padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;box-sizing:border-box;max-width:100%;font-size:13px}
        .helper-bonus-table input:focus,.helper-bonus-dialog input:focus,.helper-bonus-dialog textarea:focus,.hbx-month input:focus,.hbx-search input:focus{outline:2px solid #5eead4;outline-offset:1px;border-color:#14b8a6}
        .helper-bonus-dialog{width:min(1100px,96vw);max-height:92vh;padding:0;border:1px solid #e2e8f0;border-radius:16px;color:#0f172a;box-shadow:0 24px 60px rgba(15,23,42,.35)}
        .helper-bonus-dialog::backdrop{background:#0f172aaa}
        .helper-bonus-head{padding:16px 20px;display:flex;justify-content:space-between;gap:12px;align-items:center;border-bottom:1px solid #e2e8f0;background:#f8fafc}
        .helper-bonus-head h2{margin:0;font-size:18px}
        .helper-bonus-body{padding:20px;overflow:auto;max-height:calc(92vh - 90px)}
        .helper-bonus-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:16px 0}
        .helper-bonus-grid input{display:block;width:100%;margin-top:5px}
        .helper-bonus-toolbar{display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin-bottom:14px}
        #helper-recipient-list label{display:flex;align-items:center;gap:8px;cursor:pointer;background:#fff}
        #helper-recipient-list label:hover{background:#f0fdfa;border-color:#5eead4!important}
        @media(max-width:900px){.hbx-card{padding:16px}.hbx-sep{display:none}.hbx-head h2{font-size:19px}}
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
    $html = helperBonusStyles().'<div class="hbx-card">'
        .'<div class="hbx-head"><div><h2>Bonos máximos por ayudante</h2></div>'
        .'<div class="hbx-actions"><form method="get" action="/bonificaciones" class="hbx-month">'
        .'<input type="hidden" name="view" value="configuracion"><input type="hidden" name="bonus" value="bonoayudante">'
        .'<label for="hbx-cfg-month">Mes</label><input id="hbx-cfg-month" type="month" name="month" required value="'.h($month).'"><button class="btn secondary">Cargar mes</button></form>'
        .'<span class="hbx-sep"></span><button type="button" class="btn"'.$disabled.' onclick="document.getElementById(\'helper-recipients\').showModal()">Seleccionar ayudantes</button>'
        .'<button type="button" class="btn secondary" onclick="document.getElementById(\'helper-history\').showModal()">Historial</button></div></div>'
        .'<div class="hbx-info"><span aria-hidden="true">ℹ️</span><div>Cada bono = <b>máximo × porcentaje de la nota</b>. La selección, los máximos y las indicaciones rigen desde el mes elegido hacia adelante; los meses anteriores no cambian.</div></div>'
        .'<div class="hbx-tools"><div class="hbx-search"><input type="search" placeholder="Buscar operador" aria-label="Buscar operador" oninput="document.querySelectorAll(\'#helper-config-list tr[data-search]\').forEach(r=>r.hidden=!r.dataset.search.includes(this.value.toLowerCase()))"></div></div>';
    if (!$erpNames) $html .= '<p class="err">No se pudo cargar el listado del ERP. No se muestran operadores de trazabilidad.</p>';
    $html .= '<div class="table-wrap helper-bonus-scroll"><table id="helper-config-list" class="helper-bonus-table" style="min-width:980px"><thead><tr>'
        .'<th>Operador</th><th>Estado</th><th class="r">Proactividad</th><th class="r">Eficiencia</th><th class="r">Multitarea</th><th class="r">Máximo variable</th><th class="r">Fijo</th><th>Indicaciones</th><th>Acción</th></tr></thead><tbody>';
    foreach ($names as $name) {
        $data = $settings[$name] ?? (($legacy[$name] ?? []) + ['active'=>isset($legacy[$name])]);
        $data += ['active'=>false,'matrix_proactividad_clp'=>0,'matrix_eficiencia_clp'=>0,'matrix_multitarea_clp'=>0,'fixed_clp'=>0,'instructions'=>''];
        $effectiveLabel = !empty($data['effective_from']) ? substr($data['effective_from'],5,2).'/'.substr($data['effective_from'],0,4) : 'Registro anterior';
        $html .= '<tr data-search="'.h(mb_strtolower($name)).'"><td><div class="hbx-name">'.h($name).'</div></td><td><span class="hbx-pill '.(!empty($data['active'])?'on':'off').'">'.(!empty($data['active'])?'Participa':'No participa')
            .'</span><div class="hbx-instr">Desde '.h($effectiveLabel).'</div></td>';
        $maximum = 0;
        foreach (['matrix_proactividad_clp','matrix_eficiencia_clp','matrix_multitarea_clp'] as $field) {
            $maximum += (float)$data[$field];
            $html .= '<td class="r">'.$money($data[$field]).'</td>';
        }
        $html .= '<td class="r"><b>'.$money($maximum).'</b></td><td class="r">'.$money($data['fixed_clp']).'</td><td class="hbx-instr'.($data['instructions'] ? '' : ' empty').'" style="max-width:250px">'.h($data['instructions'] ?: 'Sin indicaciones').'</td>'
            .'<td><button type="button" class="btn secondary"'.$disabled.' data-settings="'.h(json_encode(['operator'=>$name]+$data,JSON_THROW_ON_ERROR)).'" onclick="helperEdit(this)">Modificar</button></td></tr>';
    }
    if (!$names) $html .= '<tr><td colspan="9" style="text-align:center;padding:30px;color:#64748b">Aún no hay ayudantes seleccionados. Usa <b>Seleccionar ayudantes</b> para agregarlos.</td></tr>';
    $html .= '</tbody></table></div></div><dialog id="helper-editor" class="helper-bonus-dialog"><div class="helper-bonus-head"><h2 id="helper-editor-title">Bono ayudante</h2>'
        .'<button class="btn secondary" type="button" onclick="document.getElementById(\'helper-editor\').close()">Cerrar</button></div><div class="helper-bonus-body">'
        .'<form id="helper-editor-form" method="post" action="/bonificaciones/config/helper-save">'
        .'<input type="hidden" name="_csrf" value="'.h(csrfToken()).'"><input type="hidden" name="month_key" value="'.h($month).'"><input type="hidden" name="operator">'
        .'<label><input type="checkbox" name="active"'.$disabled.'> Participa desde este mes</label><div class="helper-bonus-grid">';
    foreach (['matrix_proactividad_clp'=>'Máximo Proactividad','matrix_eficiencia_clp'=>'Máximo Eficiencia','matrix_multitarea_clp'=>'Máximo Multitarea','fixed_clp'=>'Bono fijo'] as $field=>$label) {
        $html .= '<label>'.h($label).' (CLP)<input name="'.$field.'" type="number" min="0" max="9999999999.99" step="0.01" required'.$disabled.'></label>';
    }
    $html .= '</div><label>Indicaciones<textarea name="instructions" maxlength="2000" rows="5" style="display:block;width:100%;margin-top:5px"'.$disabled.'></textarea></label>'
        .'<p class="muted">Nota 10 paga el 100 % del máximo; nota 8 paga el 80 %. Esta configuración se aplica desde '.h($month).' hacia adelante. Las notas siguen siendo propias de cada mes.</p>'
        .'<button class="btn"'.$disabled.'>Guardar</button></form></div></dialog>'
        .'<dialog id="helper-history" class="helper-bonus-dialog"><div class="helper-bonus-head"><h2>Historial de ayudantes</h2>'
        .'<button class="btn secondary" type="button" onclick="document.getElementById(\'helper-history\').close()">Cerrar</button></div><div class="helper-bonus-body">';
    foreach ($history as $entry) {
        $description = bonusHistoryChanges($entry);
        $html .= '<div style="margin-bottom:18px"><b>'.h($description['title']).'</b><p class="muted">'.h($entry['actor']).' · '.h(date('d/m/Y H:i',strtotime($entry['created_at']))).'</p>'
            .'<table class="helper-bonus-table"><thead><tr><th>Dato</th><th>Antes</th><th>Después</th></tr></thead><tbody>';
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
    $canEdit = canEditBonusConfiguration();
    $disabled = $canEdit ? '' : ' disabled';
    $money = static fn($value): string => '$ '.number_format((float)$value,0,',','.');
    $monthNames = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
    $monthLabel = ($monthNames[(int)substr($month,5,2)] ?? $month).' '.substr($month,0,4);
    $recipientNames = array_values(array_unique($recipientNames));
    sort($recipientNames, SORT_NATURAL | SORT_FLAG_CASE);
    $erpSet = array_fill_keys($recipientNames, true);
    $rows = array_values(array_filter($rows, static fn(array $row): bool => isset($erpSet[$row['operator_name']])));
    $selector = renderHelperRecipientSelector($month,$recipientNames,$settings,'bonoayudante');
    $selectBtn = '<button type="button" class="btn"'.$disabled.' onclick="document.getElementById(\'helper-recipients\').showModal()">Seleccionar ayudantes</button>';

    // Cabecera: título, selector de mes y acciones.
    $html = helperBonusStyles().'<div class="hbx-card">'
        .'<div class="hbx-head"><div><h2>Bono ayudante</h2></div>'
        .'<div class="hbx-actions"><form method="get" action="/bonificaciones" class="hbx-month">'
        .'<input type="hidden" name="view" value="bonoayudante"><label for="hbx-month">Mes</label>'
        .'<input id="hbx-month" type="month" name="month" required value="'.h($month).'"><button class="btn secondary">Ver mes</button></form>'
        .'<span class="hbx-sep"></span>'.$selectBtn
        .'<a class="btn secondary" href="/bonificaciones?view=configuracion&amp;bonus=bonoayudante&amp;month='.h($month).'">Configuración</a>'
        .'<a id="helper-export" class="btn secondary" href="/bonificaciones/ayudante/export-xls?month='.h($month).'">Excel</a></div></div>'
        .'<div class="hbx-info"><span aria-hidden="true">ℹ️</span><div>Notas de 0 a 10, con decimales. El porcentaje se calcula por tramo entero: <b>7,25 = 70 %</b> y <b>8,75 = 80 %</b>. Los cambios se guardan automáticamente por mes.</div></div>';

    // Estado vacío: explica qué hacer en vez de mostrar una pantalla en blanco.
    if (!$rows) {
        return $html.'<div class="hbx-empty"><div class="ico" aria-hidden="true">👷</div><h3>Aún no hay ayudantes seleccionados</h3>'
            .'<p>Elige quiénes reciben el bono ayudante. La selección rige desde '.h($monthLabel).' hacia adelante, y luego podrás poner las notas de cada uno.</p>'
            .$selectBtn.(!$canEdit ? '<p style="margin-top:14px">Solo Javier y Héctor pueden modificar la selección.</p>' : '').'</div></div>'.$selector;
    }

    // Filas de la tabla y totales del mes.
    $sumVariable = 0.0; $sumFixed = 0.0; $sumAdditional = 0.0; $rowsHtml = '';
    foreach ($rows as $row) {
        $name = (string)$row['operator_name'];
        $variable = 0.0;
        $fixed = (float)$row['fixed_clp'];
        $additional = (float)$row['additional_clp'];
        $instr = trim((string)$row['instructions']);
        $rowsHtml .= '<tr data-helper="'.h(mb_strtolower($name)).'" data-fixed="'.h((string)$fixed).'"><td class="hbx-sticky"><div class="hbx-name">'.h($name).'</div>'
            .'<input type="hidden" name="operators[]" value="'.h($name).'"><div class="hbx-instr'.($instr === '' ? ' empty' : '').'">'.h($instr !== '' ? $instr : 'Sin indicaciones').'</div></td>';
        foreach (['p'=>'proactividad','e'=>'eficiencia','m'=>'multitarea'] as $prefix=>$category) {
            $score = (float)$row[$category.'_score'];
            $maximum = (float)$row['matrix_'.$category.'_clp'];
            $factor = BonusConfigurationService::helperScoreFactor($score);
            $paid = $maximum * $factor;
            $variable += $paid;
            $rowsHtml .= '<td class="hbx-score"><div class="hbx-score-top"><input class="hbx-note" type="text" inputmode="decimal" required pattern="[0-9]+([.,][0-9]{1,2})?" aria-label="Nota '.h($category).' de '.h($name).'" name="'.$prefix.'_score[]" value="'.h(str_replace('.',',',(string)$score)).'" data-maximum="'.h((string)$maximum).'"'.$disabled.'>'
                .'<span class="hbx-pct" data-score-percent>'.(int)($factor*100).'%</span></div>'
                .'<div class="hbx-paid"><b data-category-paid>'.$money($paid).'</b>de '.$money($maximum).'</div></td>';
        }
        $sumVariable += $variable; $sumFixed += $fixed; $sumAdditional += $additional;
        $rowsHtml .= '<td class="r" data-variable>'.$money($variable).'</td><td class="r">'.$money($fixed).'</td>'
            .'<td><div class="hbx-money"><span>$</span><input data-additional style="width:110px" aria-label="Incentivo adicional de '.h($name).'" type="number" name="additional[]" min="0" max="9999999999.99" step="0.01" value="'.h((string)$additional).'"'.$disabled.'></div></td>'
            .'<td class="r"><span class="hbx-total" data-total>'.$money($variable+$fixed+$additional).'</span></td>'
            .'<td><input aria-label="Observaciones de '.h($name).'" name="obs[]" maxlength="255" value="'.h($row['observations']).'" style="width:100%;min-width:200px" placeholder="Observación"'.$disabled.'>'
            .'<div><span class="hbx-status" data-save-status role="status">'.(!empty($row['has_evaluation']) ? 'Guardado' : 'Sin evaluar').'</span></div></td></tr>';
    }
    $total = $sumVariable + $sumFixed + $sumAdditional;
    $html .= '<div class="hbx-stats">'
        .'<div class="hbx-stat"><div class="k">Ayudantes</div><div class="v">'.count($rows).'</div><div class="s">participan en '.h($monthLabel).'</div></div>'
        .'<div class="hbx-stat blue"><div class="k">Bono variable</div><div class="v" id="hbx-stat-variable">'.$money($sumVariable).'</div><div class="s">según las notas</div></div>'
        .'<div class="hbx-stat amber"><div class="k">Fijo + incentivos</div><div class="v" id="hbx-stat-fixed">'.$money($sumFixed + $sumAdditional).'</div><div class="s">bono fijo e incentivo adicional</div></div>'
        .'<div class="hbx-stat green"><div class="k">Total a pagar</div><div class="v" id="hbx-stat-total">'.$money($total).'</div><div class="s">suma de todos los ayudantes</div></div></div>'
        .'<div class="hbx-tools"><div class="hbx-search"><input type="search" placeholder="Buscar ayudante" aria-label="Buscar ayudante" oninput="document.querySelectorAll(\'#helper-evaluations tr[data-helper]\').forEach(r=>r.hidden=!r.dataset.helper.includes(this.value.toLowerCase()))"></div>'
        .'<div class="hbx-count">'.count($rows).' ayudantes · las notas se guardan solas al salir de cada campo</div></div>'
        .'<form method="post" action="/bonificaciones/ayudante/month-save">'
        .'<input type="hidden" name="_csrf" value="'.h(csrfToken()).'"><input type="hidden" name="month" value="'.h($month).'">'
        .'<div class="table-wrap helper-bonus-scroll"><table id="helper-evaluations" class="helper-bonus-table hbx-table"><thead><tr>'
        .'<th class="hbx-sticky">Ayudante e indicaciones</th><th>Proactividad</th><th>Eficiencia</th><th>Multitarea</th><th class="r">Variable</th><th class="r">Fijo</th><th>Incentivo adicional</th><th class="r">Total a pagar</th><th>Observaciones</th></tr></thead><tbody>'
        .$rowsHtml;
    $exportError = '<dialog id="helper-export-error" class="helper-bonus-dialog"><div class="helper-bonus-head"><h2>Excel</h2>'
        .'<button type="button" class="btn secondary" onclick="document.getElementById(\'helper-export-error\').close()">Cerrar</button></div>'
        .'<div class="helper-bonus-body">Hay calificaciones sin guardar. Usa Guardar mes y vuelve a descargar el Excel.</div></dialog>';
    return $html.'</tbody><tfoot><tr><td class="hbx-sticky">Total ('.count($rows).' ayudantes)</td><td></td><td></td><td></td>'
        .'<td class="r" id="hbx-foot-variable">'.$money($sumVariable).'</td><td class="r" id="hbx-foot-fixed">'.$money($sumFixed).'</td>'
        .'<td class="r" id="hbx-foot-additional">'.$money($sumAdditional).'</td><td class="r" id="hbx-foot-total">'.$money($total).'</td><td></td></tr></tfoot></table></div>'
        .'<div class="hbx-save"><span class="hbx-count">Los cambios se guardan automáticamente. Usa Guardar mes para confirmar todo de una vez.</span><button class="btn"'.$disabled.'>Guardar mes</button></div></form></div>'.$selector.$exportError.'<script>
        (function(){
            /* Totales del mes y colores del estado de guardado (se recalculan al escribir). */
            var table=document.getElementById("helper-evaluations");if(!table)return;
            var num=function(el){return Number((el?el.textContent:"0").replace(/[^0-9]/g,""))||0;};
            var fmt=function(n){return "$ "+Math.round(n).toLocaleString("es-CL");};
            var set=function(id,n){var el=document.getElementById(id);if(el)el.textContent=fmt(n);};
            function totals(){
                var v=0,f=0,a=0,t=0;
                table.querySelectorAll("tr[data-helper]").forEach(function(r){
                    v+=num(r.querySelector("[data-variable]"));f+=Number(r.dataset.fixed)||0;
                    var inp=r.querySelector("input[data-additional]");a+=Number(inp?inp.value:0)||0;
                    t+=num(r.querySelector("[data-total]"));
                });
                set("hbx-foot-variable",v);set("hbx-foot-fixed",f);set("hbx-foot-additional",a);set("hbx-foot-total",t);
                set("hbx-stat-variable",v);set("hbx-stat-fixed",f+a);set("hbx-stat-total",t);
            }
            var state=function(txt){txt=(txt||"").toLowerCase();
                if(txt.indexOf("no guardado")===0)return "error";
                if(txt.indexOf("guardado")===0)return "saved";
                if(txt.indexOf("pendiente")>-1||txt.indexOf("guardando")>-1)return "pending";
                return "none";};
            var paint=function(el){el.setAttribute("data-state",state(el.textContent));};
            table.querySelectorAll("[data-save-status]").forEach(paint);
            new MutationObserver(function(list){list.forEach(function(m){
                var el=m.target.nodeType===3?m.target.parentElement:m.target;
                if(el&&el.hasAttribute&&el.hasAttribute("data-save-status"))paint(el);});
            }).observe(table,{childList:true,characterData:true,subtree:true});
            table.addEventListener("input",function(){setTimeout(totals,0);});
        })();
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
