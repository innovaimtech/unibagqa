<?php
declare(strict_types=1);

function canEditBonusConfiguration(): bool
{
    return unibagIsAuthenticated() && unibagCanUserPerformModifications();
}

function requireBonusConfigurationEditAccess(): void
{
    if (canEditBonusConfiguration()) return;
    http_response_code(403);
    render('Bonificaciones', '<div class="card"><b>Configuración de solo lectura</b><p>Javier y Héctor pueden modificar los bonos. Puedes consultar las planillas y su configuración.</p></div>');
    exit;
}

function bonusConfigurationActor(): string
{
    foreach (['auth_display_name','operator_name'] as $key) {
        if (trim((string)($_SESSION[$key] ?? '')) !== '') return trim((string)$_SESSION[$key]);
    }
    $name = trim((string)($_SESSION['user_firstname'] ?? '') . ' ' . (string)($_SESSION['user_lastname'] ?? ''));
    if ($name !== '') return $name;
    $id = (int)($_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? 0);
    if ($id === 4129) return 'Javier Perozo';
    if ($id === 4077) return 'Héctor Matus';
    return trim((string)($_SESSION['user_login'] ?? $_SESSION['auth_username'] ?? $_SESSION['user_name'] ?? ('Usuario #' . $id)));
}

/** Human-readable changes; JSON remains an internal audit format.
 * Cambios comprensibles; el JSON se conserva solo como respaldo interno. */
function bonusHistoryChanges(array $entry): array
{
    $before = json_decode((string)($entry['before_json'] ?? 'null'), true) ?: [];
    $after = json_decode((string)($entry['after_json'] ?? 'null'), true) ?: [];
    $changes = [];
    if (($entry['entity_key'] ?? '') === 'bonoayudante-evaluation') {
        foreach (['proactividad_score'=>'Nota Proactividad','eficiencia_score'=>'Nota Eficiencia',
            'multitarea_score'=>'Nota Multitarea','additional_clp'=>'Incentivo adicional','observations'=>'Observaciones'] as $field=>$label) {
            if (($before[$field] ?? null) === ($after[$field] ?? null)) continue;
            $format = static function(array $data) use ($field): string {
                if (!$data) return 'Sin evaluar';
                if ($field === 'additional_clp') return '$ '.number_format((float)$data[$field],0,',','.');
                $value = (string)($data[$field] ?? '');
                return str_ends_with($field,'_score') ? str_replace('.',',',$value) : $value;
            };
            $changes[] = [$label,$format($before),$format($after)];
        }
        if (!$changes) $changes[] = ['Evaluacion','Valores anteriores','Guardada con los maximos vigentes'];
        return ['title'=>'Calificacion: '.($after['operator'] ?? ''),'changes'=>$changes];
    }
    if (($entry['entity_key'] ?? '') === 'bonoayudante') {
        $labels = ['active'=>'Participa en el bono','matrix_proactividad_clp'=>'Maximo Proactividad',
            'matrix_eficiencia_clp'=>'Maximo Eficiencia','matrix_multitarea_clp'=>'Maximo Multitarea',
            'fixed_clp'=>'Bono fijo','instructions'=>'Indicaciones'];
        foreach ($labels as $key=>$label) {
            $format = static function(array $data) use ($key): string {
                if (!$data) return 'Sin configurar';
                if ($key === 'active') return !empty($data[$key]) ? 'Si' : 'No';
                if ($key === 'instructions') return (string)($data[$key] ?? '') ?: 'Sin indicaciones';
                return '$ ' . number_format((float)($data[$key] ?? 0), 0, ',', '.');
            };
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) $changes[] = [$label,$format($before),$format($after)];
        }
        if (!$changes) $changes[] = ['Configuracion','Sin cambios','Guardada sin cambios'];
        return ['title'=>'Bono ayudante: '.($after['operator'] ?? $before['operator'] ?? ''),'changes'=>$changes];
    }
    if (str_starts_with((string)$entry['entity_key'], 'cys-measure:')) {
        $source = (string)($after['source_measure'] ?? $before['source_measure'] ?? '');
        $category = static fn(array $data): string => BonusConfigurationService::CATEGORIES[$data['category_code'] ?? ''] ?? 'Sin ajuste de bonos';
        $changes[] = ['Medida original', $before['source_measure'] ?? $source, $source];
        $changes[] = ['Medida para bonos', !empty($before['active']) ? $before['bonus_measure'] : ($before['source_measure'] ?? $source),
            !empty($after['active']) ? ($after['bonus_measure'] ?? $source) : $source];
        $changes[] = ['Categoría del bono', !empty($before['active']) ? $category($before) : 'Sin ajuste de bonos',
            !empty($after['active']) ? $category($after) : 'Sin ajuste de bonos'];
        $changes[] = ['Estado', $before ? (!empty($before['active']) ? 'Ajuste activo' : 'Ajuste desactivado') : 'Medida original',
            !empty($after['active']) ? 'Ajuste activo' : 'Ajuste desactivado: se vuelve a usar la medida original'];
        return ['title'=>'Medida ' . $source, 'changes'=>$changes];
    }
    $modeLabel = static fn(string $mode): string => $mode === 'percentage' ? 'Porcentaje por operador' : 'Bono completo (100%)';
    $oldMode = (string)($before['mode'] ?? 'unit');
    $newMode = (string)($after['mode'] ?? 'unit');
    if ($oldMode !== $newMode) $changes[] = ['Modalidad del bono',$modeLabel($oldMode),$modeLabel($newMode)];
    $names = array_unique(array_merge(array_keys($before['factors'] ?? []),array_keys($after['factors'] ?? [])));
    foreach ($names as $name) {
        $old = (int)round((float)($before['factors'][$name] ?? 1)*100);
        $new = (int)round((float)($after['factors'][$name] ?? 1)*100);
        if ($old !== $new) $changes[] = [(string)$name,$old.'%',$new.'%'];
    }
    if (!$changes) $changes[] = ['Configuración',$modeLabel($oldMode),'Guardada sin cambios'];
    return ['title'=>'Compartido por operador','changes'=>$changes];
}

function handleBonusConfigurationRoutes(string $path, string $method, BonusConfigurationService $config): void
{
    if ($method !== 'POST') {
        http_response_code(405);
        exit;
    }
    requireBonusConfigurationEditAccess();
    requireCsrf();
    $code = (string)($_POST['bonus_code']??'');
    $month = (string)($_POST['month_key']??'');
    $actor = bonusConfigurationActor();
    $target = '/bonificaciones?'.http_build_query(['view'=>'configuracion','bonus'=>$code,'month'=>$month]);
    try {
        if (!in_array($code,['bonoseri','bonocys'],true)) throw new RuntimeException('Bono no valido.');
        if ($path==='/bonificaciones/config/bonus-policy') {
            $operators = $_POST['operators']??[];
            $values = $_POST['percentages']??[];
            if (!is_array($operators) || !is_array($values) || count($operators)!==count($values)) {
                throw new RuntimeException('Lista de porcentajes no valida.');
            }
            $percentages = [];
            foreach ($operators as $i=>$name) {
                if (!is_string($name) || !array_key_exists($i,$values)) throw new RuntimeException('Operador no valido.');
                $percentages[$name] = $values[$i];
            }
            $config->savePolicy($code,$month,(string)($_POST['mode']??''),$percentages,$actor);
        } elseif ($code==='bonocys' && $path==='/bonificaciones/config/bonus-measure') {
            $config->saveMeasureRule($month,(int)($_POST['id']??0),(string)($_POST['source_measure']??''),
                (string)($_POST['bonus_measure']??''),(string)($_POST['category_code']??''),$actor);
        } elseif ($code==='bonocys' && $path==='/bonificaciones/config/bonus-measure-disable') {
            $config->disableMeasureRule($month,(int)($_POST['id']??0),$actor);
        } else {
            throw new RuntimeException('Accion de bonos no valida.');
        }
        header('Location: '.$target.'&saved=1');
    } catch (Throwable $e) {
        error_log('Bonus configuration: '.$e->getMessage());
        $error = $e instanceof PDOException ? 'No se pudo guardar la configuracion de bonos.' : $e->getMessage();
        header('Location: '.$target.'&error='.rawurlencode($error));
    }
    exit;
}

function renderBonusProcessConfiguration(BonusConfigurationService $config, string $code, string $month, array $operators, array $predefinedMeasures = []): string
{
    $policy = $config->getPolicy($code,$month);
    $operators = array_unique(array_merge($operators,array_keys($policy['factors'])));
    sort($operators,SORT_NATURAL|SORT_FLAG_CASE);
    $canEdit = canEditBonusConfiguration();
    $disabled = $canEdit ? '' : ' disabled';
    $monthNames = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
    $monthLabel = $monthNames[substr($month,5,2)] . ' ' . substr($month,0,4);
    $hidden = '<input type="hidden" name="_csrf" value="'.h(csrfToken()).'">'
        .'<input type="hidden" name="bonus_code" value="'.h($code).'">'
        .'<input type="hidden" name="month_key" value="'.h($month).'">';
    $html = '<style>.bonus-config-dialog{width:min(1100px,96vw);max-height:92vh;border:1px solid #e2e8f0;border-radius:14px;padding:0;color:#0f172a;box-shadow:0 24px 80px #0005}'
        .'.bonus-config-dialog::backdrop{background:rgba(15,23,42,.65)}'
        .'.bonus-config-head{padding:14px 18px;display:flex;gap:12px;align-items:center;justify-content:space-between;border-bottom:1px solid #e2e8f0;background:linear-gradient(180deg,#f8fafc,#fff)}'
        .'.bonus-config-head h2{font-size:18px;margin:0;font-weight:900}.bonus-config-body{padding:18px;overflow:auto;max-height:calc(92vh - 80px)}'
        .'.bonus-config-dialog input,.bonus-config-dialog select{padding:9px 10px;border:1px solid #cbd5e1;border-radius:8px;max-width:100%;box-sizing:border-box;background:#fff}'
        .'.bonus-config-dialog input:focus,.bonus-config-dialog select:focus{outline:2px solid #bfdbfe;border-color:#2563eb}'
        .'.bonus-config-dialog table{border-collapse:collapse;width:100%;font-size:13px}'
        .'.bonus-config-dialog td,.bonus-config-dialog th{padding:10px 12px;text-align:left;border-bottom:1px solid #e2e8f0}'
        .'.bonus-config-dialog th{background:#f1f5f9;color:#334155;font-weight:800}.bonus-config-dialog tr:nth-child(even){background:#f8fafc}'
        .'.bonus-config-toolbar{display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin-bottom:16px}'
        .'.bonus-config-note{padding:10px 14px;background:#eff6ff;color:#1e40af;border-radius:8px;margin-bottom:16px;font-size:13px}'
        .'.bonus-rule-editor{padding:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:16px}'
        .'.bonus-rule-editor label{font-size:12px;font-weight:700;color:#475569}.bonus-rule-editor input,.bonus-rule-editor select{display:block;margin-top:5px;width:100%}'
        .'.bonus-config-state{display:inline-block;padding:4px 8px;border-radius:6px;font-size:11px;font-weight:700;background:#e2e8f0;color:#475569}'
        .'.bonus-config-state.adjusted{background:#dcfce7;color:#166534}.bonus-history-entry{border:1px solid #e2e8f0;border-radius:10px;margin-bottom:12px;overflow:hidden}'
        .'.bonus-history-summary{padding:12px 14px;background:#f8fafc;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}'
        .'@media(max-width:700px){.bonus-config-dialog{width:96vw}.bonus-config-body{padding:12px}.bonus-rule-editor label{flex:1 1 100%}.bonus-config-dialog .table-wrap{overflow:auto}}</style>';
    $html .= '<div class="card" style="margin-bottom:12px"><div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;justify-content:space-between">'
        .'<div><div style="font-weight:900">Compartido por operador</div><div class="muted" style="margin-top:4px">Mes: <strong>'.h($monthLabel).'</strong> · Configurados: <strong>'.count($policy['factors']).'</strong>'
        .' · '.($policy['mode']==='percentage'?'Porcentaje por operador':'Bono completo (100%)').'</div></div><div style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">'
        .'<form method="get" action="/bonificaciones" style="display:flex;gap:8px;align-items:end;margin:0">'
        .'<input type="hidden" name="view" value="configuracion"><input type="hidden" name="bonus" value="'.h($code).'">'
        .'<label class="muted">Mes<br><input type="month" name="month" required value="'.h($month).'" style="padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px"></label><button class="btn secondary">Cargar mes</button></form>'
        .'<button type="button" class="btn" onclick="document.getElementById(\'bonus-policy\').showModal()">Configurar compartido</button>';
    if ($code==='bonocys') {
        $html .= '<button type="button" class="btn secondary" onclick="document.getElementById(\'bonus-measures\').showModal()">Medidas</button>';
    }
    $html .= '<button type="button" class="btn secondary" onclick="document.getElementById(\'bonus-history\').showModal()">Historial</button></div></div></div>';
    $head = static function(string $id,string $title):string {
        return '<dialog id="'.$id.'" class="bonus-config-dialog"><div class="bonus-config-head"><h2>'.$title.'</h2>'
            .'<button type="button" class="btn secondary" onclick="document.getElementById(\''.$id.'\').close()">Cerrar</button></div><div class="bonus-config-body">';
    };
    $html .= $head('bonus-policy','Compartido por operador · '.h($monthLabel));
    $html .= '<div class="bonus-config-note">El porcentaje se aplica solo al bono de producción.'
        .($code==='bonocys'?' Los cambios de configuración y el embalaje mantienen su tarifa.':'').'</div>'
        .(!$canEdit ? '<p class="muted">Solo lectura. Javier y Héctor pueden guardar modificaciones.</p>' : '')
        .'<form method="post" action="/bonificaciones/config/bonus-policy">'.$hidden
        .'<div class="bonus-config-toolbar"><label>Modalidad<br><select name="mode"'.$disabled.'><option value="unit"'.($policy['mode']==='unit'?' selected':'').'>Bono completo (100%)</option>'
        .'<option value="percentage"'.($policy['mode']==='percentage'?' selected':'').'>Porcentaje por operador</option></select></label>'
        .'<input type="search" placeholder="Buscar operador" style="width:280px" oninput="document.querySelectorAll(\'#bonus-operators tr[data-operator]\').forEach(r=>r.hidden=!r.dataset.operator.includes(this.value.toLowerCase()))"></div>'
        .'<div class="table-wrap"><table id="bonus-operators"><thead><tr><th>Operador</th><th style="width:180px">Compartido</th></tr></thead><tbody>';
    foreach ($operators as $operator) {
        $operator = trim((string)$operator);
        if ($operator==='') continue;
        $current = (float)($policy['factors'][$operator]??1)*100;
        $html .= '<tr data-operator="'.h(mb_strtolower($operator)).'"><td>'.h($operator)
            .'<input type="hidden" name="operators[]" value="'.h($operator).'"></td><td><select name="percentages[]"'.$disabled.' style="width:100%">';
        foreach ([100,90,80] as $percent) {
            $html .= '<option value="'.$percent.'"'.(abs($current-$percent)<.001?' selected':'').'>'.$percent.'%</option>';
        }
        $html .= '</select></td></tr>';
    }
    if (!$operators) $html .= '<tr><td colspan="2">Sin operadores disponibles.</td></tr>';
    $html .= '</tbody></table></div><div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">'
        .'<button type="button" class="btn secondary" onclick="document.getElementById(\'bonus-policy\').close()">Cancelar</button>'
        .'<button class="btn"'.$disabled.'>Guardar</button></div></form></div></dialog>';
    if ($code==='bonocys') {
        $html .= $head('bonus-measures','Medidas de bolsas · '.h($monthLabel));
        $html .= '<div class="bonus-config-note">Las medidas predefinidas se consultan del ERP. Las correcciones se aplican solo a los bonos de este mes, a todas las bolsas con esa medida. El ERP original no se modifica.</div>';
        $rules = $config->listMeasureRules($month);
        $bySource = [];
        foreach ($rules as $rule) $bySource[BonusConfigurationService::normalizeMeasure($rule['source_measure'])] = $rule;
        $catalog = BonusConfigurationService::prepareMeasureCatalog($predefinedMeasures);
        $seen = [];
        $html .= '<form id="bonus-rule-editor" class="bonus-rule-editor" method="post" action="/bonificaciones/config/bonus-measure">'.$hidden
            .'<input id="bonus-rule-id" type="hidden" name="id" value="0"><b id="bonus-rule-title">Agregar ajuste de medida</b>'
            .'<div class="bonus-config-toolbar" style="margin:12px 0 0"><label>Medida original<input id="bonus-rule-source" name="source_measure" list="bonus-measure-options" required maxlength="80" placeholder="Seleccionar o escribir"'.$disabled.'></label>'
            .'<label>Medida para bonos<input id="bonus-rule-target" name="bonus_measure" required maxlength="80" placeholder="XXL o medida corregida"'.$disabled.'></label>'
            .'<label>Categoría<select id="bonus-rule-category" name="category_code"'.$disabled.'>';
        foreach (BonusConfigurationService::CATEGORIES as $key=>$label) {
            $html .= '<option value="'.h($key).'"'.($key==='bolsas_xxl'?' selected':'').'>'.h($label).'</option>';
        }
        $html .= '</select></label><button class="btn"'.$disabled.'>Guardar</button><button type="button" class="btn secondary" onclick="bonusClearMeasure()">Nuevo</button></div>'
            .'<datalist id="bonus-measure-options">';
        foreach ($catalog as $item) $html .= '<option value="'.h($item['source_measure']).'">'.h($item['label']).'</option>';
        $html .= '</datalist></form><div class="bonus-config-toolbar"><input type="search" style="width:340px" placeholder="Buscar medida, tipo o categoría" oninput="document.querySelectorAll(\'#bonus-measure-table tr[data-search]\').forEach(r=>r.hidden=!r.dataset.search.includes(this.value.toLowerCase()))">'
            .'<span class="muted">'.count($catalog).' medidas predefinidas · '.count($rules).' ajustes de bonos</span></div>'
            .'<div class="table-wrap"><table id="bonus-measure-table"><thead><tr><th>Medida predefinida</th><th>Original</th><th>Para bonos</th><th>Categoría</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
        $renderMeasure = static function(array $item, ?array $rule) use ($hidden,$disabled): string {
            $source = $item['source_measure'];
            $target = $rule['bonus_measure'] ?? $source;
            $category = $rule['category_code'] ?? $item['category_code'];
            $label = BonusConfigurationService::CATEGORIES[$category] ?? 'Bolsas';
            $html = '<tr data-search="'.h(mb_strtolower($item['label'].' '.$source.' '.$target.' '.$label)).'"><td><b>'.h($item['label']).'</b></td>'
                .'<td>'.h($source).'</td><td><b>'.h($target).'</b></td><td>'.h($label).'</td>'
                .'<td><span class="bonus-config-state'.($rule?' adjusted':'').'">'.($rule?'Ajustada':'Predefinida').'</span></td>'
                .'<td><div style="display:flex;gap:6px"><button type="button" class="btn secondary"'.$disabled
                .' data-id="'.(int)($rule['id'] ?? 0).'" data-source="'.h($source).'" data-target="'.h($target).'" data-category="'.h($category).'" onclick="bonusEditMeasure(this)">Modificar</button>';
            if ($rule) $html .= '<form method="post" action="/bonificaciones/config/bonus-measure-disable" style="margin:0">'.$hidden
                .'<input type="hidden" name="id" value="'.(int)$rule['id'].'"><button class="btn secondary"'.$disabled.'>Desactivar</button></form>';
            return $html.'</div></td></tr>';
        };
        foreach ($catalog as $item) {
            $source = $item['source_measure'];
            $seen[$source] = true;
            $html .= $renderMeasure($item,$bySource[$source] ?? null);
        }
        foreach ($rules as $rule) {
            $source = BonusConfigurationService::normalizeMeasure($rule['source_measure']);
            if (!isset($seen[$source])) $html .= $renderMeasure(['label'=>'Medida propia de bonos','source_measure'=>$source,'category_code'=>$rule['category_code']],$rule);
        }
        if (!$catalog && !$rules) $html .= '<tr><td colspan="6">No se pudieron cargar medidas predefinidas. Puedes agregar un ajuste usando la medida de la planilla.</td></tr>';
        $html .= '</tbody></table></div></div></dialog><script>
            function bonusEditMeasure(button){
                document.getElementById("bonus-rule-id").value=button.dataset.id;
                document.getElementById("bonus-rule-source").value=button.dataset.source;
                document.getElementById("bonus-rule-target").value=button.dataset.target;
                document.getElementById("bonus-rule-category").value=button.dataset.category;
                document.getElementById("bonus-rule-title").textContent="Modificar medida "+button.dataset.source;
                document.getElementById("bonus-rule-editor").scrollIntoView({block:"start",behavior:"smooth"});
                document.getElementById("bonus-rule-target").focus({preventScroll:true});
            }
            function bonusClearMeasure(){
                document.getElementById("bonus-rule-editor").reset();
                document.getElementById("bonus-rule-id").value="0";
                document.getElementById("bonus-rule-title").textContent="Agregar ajuste de medida";
            }
        </script>';
    }
    $html .= $head('bonus-history','Historial de modificaciones · '.h($monthLabel))
        .'<p class="muted">Quién realizó el cambio y cómo estaba la configuración antes y después. El respaldo original se conserva en el sistema.</p>';
    $history = $config->listHistory($month,$code);
    foreach ($history as $entry) {
        $description = bonusHistoryChanges($entry);
        $actor = ['4129'=>'Javier Perozo','4077'=>'Héctor Matus'][$entry['actor']] ?? $entry['actor'];
        $html .= '<section class="bonus-history-entry"><div class="bonus-history-summary"><div><b>'.h($description['title']).'</b><div class="muted" style="margin-top:4px">Modificado por '.h($actor).'</div></div>'
            .'<div class="muted">'.h(date('d/m/Y H:i:s',strtotime($entry['created_at']))).'</div></div><table><thead><tr><th>Dato modificado</th><th>Antes</th><th>Después</th></tr></thead><tbody>';
        foreach ($description['changes'] as [$label,$before,$after]) {
            $html .= '<tr><td>'.h($label).'</td><td>'.h($before).'</td><td><b>'.h($after).'</b></td></tr>';
        }
        $html .= '</tbody></table></section>';
    }
    if (!$history) $html .= '<div class="bonus-config-note">Aún no hay modificaciones registradas para este mes.</div>';
    return $html.'</div></dialog>';
}
