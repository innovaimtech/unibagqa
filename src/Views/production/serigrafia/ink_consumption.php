<?php
    $agenda = $ctx['agenda'] ?? [];
    $workerOt = $ctx['worker_ot'] ?? null;
    $workerOtId = $workerOt !== null ? (int)$workerOt['id'] : 0;
    $agId = (int)($agenda['id'] ?? 0);
    $bodega3000Inks = $ctx['bodega3000_ink_items'] ?? [];
    $machineInkLoads = $ctx['machine_ink_loads'] ?? [];

    $totalConsumed = 0.0;
    $seriLoads = [];
    foreach ($machineInkLoads as $load) {
        $totalConsumed += (float)($load['consumed_qty'] ?? 0);
        $seriLoads[] = $load;
    }

    $otColors = [];
    for ($cIdx = 1; $cIdx <= 10; $cIdx++) {
        $cDesc = trim((string)($agenda["fab_print_colordesc_{$cIdx}"] ?? ''));
        if ($cDesc !== '') {
            $fCheck = (int)($agenda["fab_print_colors_front_{$cIdx}"] ?? 0);
            $bCheck = (int)($agenda["fab_print_colors_back_{$cIdx}"] ?? 0);
            $sideStr = '';
            if ($fCheck > 0 && $bCheck > 0) $sideStr = ' [Frente y Dorso]';
            elseif ($fCheck > 0) $sideStr = ' [Frente]';
            elseif ($bCheck > 0) $sideStr = ' [Dorso]';
            $otColors[] = "Color {$cIdx}: {$cDesc}{$sideStr}";
        }
    }
    if (empty($otColors)) {
        $otColors = ['Color 1', 'Color 2', 'Color 3', 'Color 4'];
    }

    if ($returnTo === '') {
        $returnTo = $_SERVER['REQUEST_URI'] ?? ('/production/work-orders/operate?agid=' . $agId);
    }
    $uniqId = 'seri_ink_' . $agId . '_' . ($workerOtId > 0 ? $workerOtId : '0');
    ?>
    <div style="background-color:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;overflow:hidden;margin-bottom:15px;box-shadow:0 1px 3px rgba(15,23,42,0.06)">
        <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_seri_inks_<?=$agId?>', 'icon_seri_inks_<?=$agId?>')" style="background:#F8FAFC;padding:12px 18px;display:flex;justify-content:space-between;align-items:center;cursor:pointer;border-bottom:1px solid #E2E8F0">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span style="width:34px;height:34px;border-radius:8px;background:#E0F2FE;color:#0284C7;display:inline-flex;align-items:center;justify-content:center;font-size:16px">
                    <i class="fa fa-tint"></i>
                </span>
                <div>
                    <span style="font-weight:800;font-size:14px;color:#0F172A">
                        Consumo de Tintas de Serigrafía &mdash; Bodega 3000 (Insumos en Producción)
                    </span>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
                <span style="background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:800">
                    <i class="fa fa-warehouse"></i> Bodega 3000
                </span>
                <?php if ($totalConsumed > 0): ?>
                    <span style="background:#ECFDF5;color:#065F46;border:1px solid #A7F3D0;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:800">
                        <i class="fa fa-check"></i> Consumido OT: <?=number_format($totalConsumed, 2, ',', '.')?> kg
                    </span>
                <?php endif; ?>
                <span class="unibag-collapse-btn" style="color:#64748B;font-size:12px">
                    <span id="lbl_seri_inks_<?=$agId?>">Minimizar</span>
                    <i id="icon_seri_inks_<?=$agId?>" class="fa fa-chevron-up"></i>
                </span>
            </div>
        </div>

        <div id="sec_seri_inks_<?=$agId?>" style="padding:18px">
            <!-- Formulario de nuevo consumo -->
            <form method="post" action="/production/work-orders/inks/manage" id="form_<?=$uniqId?>" onsubmit="return validateSeriInkForm(this);" style="margin:0 0 16px 0;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:16px">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <input type="hidden" name="ink_action" value="seri_consume">
                <input type="hidden" name="op_key" value="<?=bin2hex(random_bytes(16))?>">
                <input type="hidden" name="return_to" value="<?=htmlspecialchars($returnTo)?>">

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;align-items:start">
                    <!-- Tinta Bodega 3000 -->
                    <div style="grid-column: span 2; min-width:280px">
                        <label style="display:block;font-size:11.5px;font-weight:800;color:#334155;margin-bottom:4px">
                            <i class="fa fa-flask" style="color:#0284C7"></i> Tinta desde Bodega 3000 *
                        </label>
                        <select name="item_id" id="<?=$uniqId?>_item" class="inptxt" style="width:100%;height:38px;font-size:12px;padding:4px 8px;border-radius:6px;background:#FFF;font-weight:600" required>
                            <option value="">-- Seleccionar Tinta de Bodega 3000 --</option>
                            <?php foreach ($bodega3000Inks as $ink):
                                $stockVal = (float)($ink['available_qty'] ?? 0);
                                $hasStock = $stockVal > 0.0001;
                            ?>
                                <option value="<?=$ink['item_id']?>" data-stock="<?=$stockVal?>">
                                    <?=htmlspecialchars((string)$ink['code'])?> - <?=htmlspecialchars((string)$ink['name'])?> (Stock: <?=number_format($stockVal, 2, ',', '.')?> <?=$ink['unit_name']?>)<?=$hasStock ? ' ✓' : ''?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Color de la OT -->
                    <div>
                        <label style="display:block;font-size:11.5px;font-weight:800;color:#334155;margin-bottom:4px">
                            <i class="fa fa-palette" style="color:#6366F1"></i> Color Asignado
                        </label>
                        <select name="color_label" class="inptxt" style="width:100%;height:38px;font-size:12px;padding:4px 8px;border-radius:6px;background:#FFF">
                            <option value="">&lt; General / Sin Color &gt;</option>
                            <?php foreach ($otColors as $cText): ?>
                                <option value="<?=htmlspecialchars($cText)?>"><?=htmlspecialchars($cText)?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Peso al Inicio (kg) -->
                    <div>
                        <label style="display:block;font-size:11.5px;font-weight:800;color:#334155;margin-bottom:4px">
                            <i class="fa fa-weight-hanging" style="color:#059669"></i> Peso al Inicio (kg) *
                        </label>
                        <input type="number" step="0.001" min="0" name="initial_weight_kg" id="<?=$uniqId?>_init" class="inptxt" style="width:100%;height:38px;font-size:13px;font-weight:700;text-align:right;padding-right:10px;border-radius:6px;background:#FFF" placeholder="0.000" oninput="calcSeriInkNet('<?=$uniqId?>')" required>
                        <div style="font-size:10.5px;color:#64748B;margin-top:2px">Peso del tarro preparado al iniciar</div>
                    </div>

                    <!-- Cuánto sobró (kg) -->
                    <div>
                        <label style="display:block;font-size:11.5px;font-weight:800;color:#334155;margin-bottom:4px">
                            <i class="fa fa-undo" style="color:#D97706"></i> Cuánto sobró (kg) *
                        </label>
                        <input type="number" step="0.001" min="0" name="leftover_qty" id="<?=$uniqId?>_leftover" class="inptxt" style="width:100%;height:38px;font-size:13px;font-weight:700;text-align:right;padding-right:10px;border-radius:6px;background:#FFF" placeholder="0.000" oninput="calcSeriInkNet('<?=$uniqId?>')" required>
                        <div style="font-size:10.5px;color:#64748B;margin-top:2px">Peso restante en el tarro al finalizar</div>
                    </div>

                    <!-- Consumo Neto Calculado -->
                    <div>
                        <label style="display:block;font-size:11.5px;font-weight:800;color:#0F766E;margin-bottom:4px">
                            <i class="fa fa-calculator"></i> Consumo (kg)
                        </label>
                        <div style="display:flex;align-items:center;height:38px;background:#ECFDF5;border:1.5px solid #10B981;border-radius:6px;padding:0 12px;justify-content:space-between">
                            <span style="font-size:11px;font-weight:800;color:#047857">A DESCONTAR:</span>
                            <span id="<?=$uniqId?>_display_net" style="font-size:16px;font-weight:900;color:#065F46">0.000 kg</span>
                        </div>
                        <input type="hidden" name="consumed_qty" id="<?=$uniqId?>_consumed" value="0">
                        <input type="hidden" name="loaded_qty" id="<?=$uniqId?>_loaded" value="0">
                        <div style="font-size:10.5px;color:#047857;margin-top:2px">Peso al Inicio &minus; Cuánto sobró</div>
                    </div>
                </div>

                <!-- Observaciones y Botón Guardar -->
                <div style="display:flex;gap:12px;align-items:center;margin-top:14px;flex-wrap:wrap">
                    <div style="flex:1;min-width:220px">
                        <input type="text" name="comments" class="inptxt" style="width:100%;height:38px;border-radius:6px;font-size:12px;padding:0 10px;background:#FFF" placeholder="Observaciones opcionales (ej: matriz frente 1, prueba de tono...)">
                    </div>
                    <div>
                        <button type="submit" class="btngreen" style="height:38px;padding:0 22px;border:none;border-radius:6px;font-size:13px;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:8px;box-shadow:0 1px 2px rgba(0,0,0,0.1)">
                            <i class="fa fa-check-circle" style="font-size:15px"></i> Registrar Consumo en Bodega 3000
                        </button>
                    </div>
                </div>
            </form>

            <!-- Historial de consumos en esta OT -->
            <div>
                <div style="font-size:13px;font-weight:800;color:#1E293B;margin-bottom:8px;display:flex;align-items:center;justify-content:space-between">
                    <span><i class="fa fa-history" style="color:#64748B"></i> Historial de Tintas Registradas en esta OT (<?=count($seriLoads)?>)</span>
                    <span style="font-size:11.5px;color:#64748B;font-weight:normal">
                        Total Neto Consumido: <b style="color:#0F766E"><?=number_format($totalConsumed, 2, ',', '.')?> kg</b>
                    </span>
                </div>

                <?php if (empty($seriLoads)): ?>
                    <div style="background:#F1F5F9;border:1px dashed #CBD5E1;border-radius:6px;padding:14px;text-align:center;color:#64748B;font-size:12px">
                        No hay consumos de tinta registrados aún para esta orden de trabajo. Complete el formulario superior para registrar la primera tinta desde Bodega 3000.
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;border:1px solid #E2E8F0;border-radius:6px">
                        <table border="0" width="100%" cellpadding="8" cellspacing="0" style="background:#FFF">
                            <thead>
                                <tr style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;font-size:11.5px;color:#475569;text-transform:uppercase;letter-spacing:0.3px">
                                    <th align="left" style="padding-left:12px">Tinta (Bodega 3000)</th>
                                    <th align="left">Color</th>
                                    <th align="right">Peso al Inicio</th>
                                    <th align="right">Cuánto sobró</th>
                                    <th align="right" style="color:#0F766E">Consumo (kg)</th>
                                    <th align="left">Operador / Fecha</th>
                                    <th align="center" style="width:90px">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($seriLoads as $sLoad):
                                    $sLoadId = (int)($sLoad['id'] ?? 0);
                                    $sNet = (float)($sLoad['consumed_qty'] ?? 0);
                                    $sInit = (float)($sLoad['initial_weight_kg'] ?? 0);
                                    $sLeft = (float)($sLoad['leftover_qty'] ?? 0);
                                    $sColor = trim((string)($sLoad['color_label'] ?? ''));
                                    $sOp = trim((string)($sLoad['operator_name'] ?? ''));
                                    $sDate = (int)($sLoad['created_at'] ?? 0) > 0 ? date('d/m H:i', (int)$sLoad['created_at']) : '-';
                                    $sComm = trim((string)($sLoad['comments'] ?? ''));
                                ?>
                                <tr style="border-bottom:1px solid #F1F5F9" onmouseover="this.style.backgroundColor='#F8FBFF'" onmouseout="this.style.backgroundColor=''">
                                    <td style="padding-left:12px">
                                        <div style="font-weight:700;color:#0F172A;font-size:12.5px">
                                            <?=htmlspecialchars(trim((string)($sLoad['item_code'] ?? '') . ' - ' . (string)($sLoad['item_name'] ?? ''), ' -'))?>
                                        </div>
                                        <?php if ($sComm !== ''): ?>
                                            <div style="font-size:11px;color:#64748B;font-style:italic"><?=htmlspecialchars($sComm)?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:12px;color:#334155">
                                        <?=htmlspecialchars($sColor !== '' ? $sColor : '-')?>
                                    </td>
                                    <td align="right" style="font-size:12px;color:#475569">
                                        <?=$sInit > 0 ? number_format($sInit, 2, ',', '.') . ' kg' : '-'?>
                                    </td>
                                    <td align="right" style="font-size:12px;color:#D97706">
                                        <?=$sLeft > 0 ? number_format($sLeft, 2, ',', '.') . ' kg' : '-'?>
                                    </td>
                                    <td align="right" style="font-size:13px;font-weight:800;color:#0F766E">
                                        <?=number_format($sNet, 2, ',', '.')?> kg
                                    </td>
                                    <td style="font-size:11.5px;color:#64748B">
                                        <div><b><?=htmlspecialchars($sOp !== '' ? $sOp : 'Operador')?></b></div>
                                        <div><?=$sDate?></div>
                                    </td>
                                    <td align="center">
                                        <form method="post" action="/production/work-orders/inks/manage" style="margin:0" onsubmit="return confirm('¿Eliminar este registro de tinta y devolver <?=number_format($sNet, 2, ',', '.')?> kg al stock de Bodega 3000?');">
                                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                            <input type="hidden" name="agid" value="<?=$agId?>">
                                            <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                            <input type="hidden" name="ink_action" value="delete_load">
                                            <input type="hidden" name="load_id" value="<?=$sLoadId?>">
                                            <input type="hidden" name="return_to" value="<?=htmlspecialchars($returnTo)?>">
                                            <button type="submit" class="btnred" style="padding:4px 8px;font-size:11px;cursor:pointer;border-radius:4px" title="Eliminar y devolver stock a Bodega 3000">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    if (typeof calcSeriInkNet !== 'function') {
        window.calcSeriInkNet = function(prefix) {
            var initEl = document.getElementById(prefix + '_init');
            var leftoverEl = document.getElementById(prefix + '_leftover');
            var displayEl = document.getElementById(prefix + '_display_net');
            var consumedEl = document.getElementById(prefix + '_consumed');
            var loadedEl = document.getElementById(prefix + '_loaded');
            if (!displayEl || !consumedEl) return;

            var initVal = initEl ? parseFloat(initEl.value.replace(',', '.')) || 0 : 0;
            var leftoverVal = leftoverEl ? parseFloat(leftoverEl.value.replace(',', '.')) || 0 : 0;

            var net = 0;
            if (initVal > 0) {
                net = Math.max(0, initVal - leftoverVal);
            }

            net = Math.round(net * 1000) / 1000;
            consumedEl.value = net.toFixed(3);
            if (loadedEl) loadedEl.value = net.toFixed(3);
            displayEl.textContent = net.toFixed(3) + ' kg';
        };
    }

    if (typeof validateSeriInkForm !== 'function') {
        window.validateSeriInkForm = function(form) {
            var itemSelect = form.querySelector('select[name="item_id"]');
            if (!itemSelect || !itemSelect.value) {
                alert('Debe seleccionar una tinta desde Bodega 3000.');
                if (itemSelect) itemSelect.focus();
                return false;
            }
            var init = parseFloat((form.querySelector('input[name="initial_weight_kg"]').value || '0').replace(',', '.')) || 0;
            var leftover = parseFloat((form.querySelector('input[name="leftover_qty"]').value || '0').replace(',', '.')) || 0;

            if (init <= 0) {
                alert('Debe ingresar el Peso al Inicio (kg) del tarro de tinta preparado.');
                var initEl = form.querySelector('input[name="initial_weight_kg"]');
                if (initEl) initEl.focus();
                return false;
            }
            if (leftover > init) {
                alert('El peso que sobró (' + leftover + ' kg) no puede ser mayor al peso al inicio del tarro (' + init + ' kg).');
                var leftEl = form.querySelector('input[name="leftover_qty"]');
                if (leftEl) leftEl.focus();
                return false;
            }
            // Evita el doble clic: el botón queda desactivado mientras se envía (el servidor además ignora un reenvío con la misma clave).
            var btn = form.querySelector('button[type="submit"]');
            if (btn) { btn.disabled = true; btn.style.opacity = '.6'; }
            return true;
        };
    }
    </script>
    <?php

