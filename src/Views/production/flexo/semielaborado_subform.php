<?php
    ?>
        <!-- SUBFORMULARIO: Bobina Semielaborada Impresa (Flexografía) -->
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver a la Consola de OT
            </div>
        </div>

        <?php if (!empty($_GET['roll_created'])): ?>
            <div style="background:#E8F8F5;border:1.5px solid #2ECC71;color:#117A65;padding:16px 20px;border-radius:6px;margin-bottom:18px;font-size:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div>
                    <i class="fa fa-fw fa-check-circle" style="font-size:20px;color:#27AE60"></i>
                    <b>¡Bobina semielaborada generada exitosamente!</b> Se registró <b><?=htmlspecialchars($_GET['roll_code'] ?? ('ID #' . $_GET['roll_created']))?></b> vinculada a la OT #<b><?=htmlspecialchars((string)$agenda['prd_number'])?></b>.
                </div>
                <div style="display:flex;gap:8px">
                    <a href="/rolls/<?=(int)$_GET['roll_created']?>/label?server_print=1" target="_blank" class="btnblue" style="text-decoration:none;font-weight:bold">
                        <i class="fa fa-print"></i> 🖨️ Mandar a Imprimir en Zebra
                    </a>
                    <a href="/rolls/<?=(int)$_GET['roll_created']?>/label" target="_blank" class="btngrey" style="text-decoration:none;font-weight:bold">
                        <i class="fa fa-eye"></i> Vista Previa (100×70)
                    </a>
                    <a href="/production/traceability?q=<?=urlencode((string)($_GET['roll_code'] ?? ''))?>" target="_blank" class="btngreen" style="text-decoration:none;font-weight:bold">
                        <i class="fa fa-sitemap"></i> 🌳 Trazabilidad
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['error'])): ?>
            <div style="background:#FDEDEC;border:1.5px solid #E74C3C;color:#922B21;padding:14px 18px;border-radius:6px;margin-bottom:18px;font-size:14px">
                <i class="fa fa-fw fa-exclamation-triangle" style="font-size:18px"></i>
                <b>Error:</b> <?=htmlspecialchars((string)$_GET['error'])?>
            </div>
        <?php endif; ?>

        <?php
        $woSemielaboratedRolls = [];
        if ($workerOtId > 0 && Db::trzPdo() !== null) {
            try {
                $stmtWoSE = Db::trzPdo()->prepare("
                    SELECT r.*, pr.roll_code AS parent_roll_code, s.code AS sku_code, s.description AS sku_desc
                    FROM rolls r 
                    LEFT JOIN rolls pr ON pr.id = r.parent_roll_id 
                    LEFT JOIN skus s ON s.id = r.sku_id
                    WHERE (r.source_work_order_id = :otid " . ($agId > 0 ? "OR r.source_work_order_id = :agid" : "") . ")
                      AND r.process_stage = 'PRINTED'
                    ORDER BY r.id DESC
                ");
                $paramsSE = [':otid' => $workerOtId];
                if ($agId > 0) {
                    $paramsSE[':agid'] = $agId;
                }
                $stmtWoSE->execute($paramsSE);
                $woSemielaboratedRolls = $stmtWoSE->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable) {}
        }
        $skuPrintDefault = (string)($agenda['item_title'] ?? $agenda['fab_type'] ?? 'BOBINA-IMPRESA');
        $suggestedMeters = (float)($metros_a_imprimir ?? ($stats['total_meters'] ?? 0));
        $telaWidth = (float)($agenda['req_operador_tela_width'] ?? ($agenda['fab_med_width'] ?? 80));
        if ($telaWidth <= 0) $telaWidth = 80.0;
        $telaGramms = (float)($agenda['fab_mat_gramms'] ?? 80);
        if ($telaGramms <= 0) $telaGramms = 80.0;
        $metersPerKg = round(100000.0 / ($telaWidth * $telaGramms), 2);
        ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(420px, 1fr));gap:20px;margin-bottom:20px">
            <!-- COLUMNA 1: FORMULARIO GENERACIÓN DE BOBINA SEMIELABORADA -->
            <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
                <div style="font-size:17px;font-weight:bold;color:#0F172A;margin-bottom:14px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-tag" style="color:#6366F1"></i> Emisión de Bobina Impresa Semielaborada
                </div>
                <div style="font-size:13px;color:#64748B;margin-bottom:18px">
                    Registre el tiraje de la bobina impresa al salir de <?=htmlspecialchars($materialsMachineProcessTitle)?>. Se creará con código <b>ROLL-SE-XXXXX</b>, vinculada genealógicamente a la bobina virgen y lista para rotularse en 100×70 mm.
                </div>

                <form method="post" action="/production/work-orders/rolls/generate-semielaborated" id="form_semielaborado">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">

                    <table border="0" width="100%" cellpadding="8" cellspacing="0">
                    <tr>
                        <td class="tdleft" width="160">OT Activa:</td>
                        <td class="tdnrm"><b>OT #<?=htmlspecialchars((string)$agenda['prd_number'])?></b> &mdash; <?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Bobina Madre (Virgen):</td>
                        <td class="tdnrm">
                            <?php if (!empty($assignedRolls)): ?>
                                <select name="parent_roll_id" id="parent_roll_id" class="inptxt" style="width:100%" onchange="onParentRollChange()">
                                    <option value="" data-width="<?=$telaWidth?>" data-gramms="<?=$telaGramms?>">-- Sin bobina directa / Auto-detectar --</option>
                                    <?php foreach ($assignedRolls as $ar): 
                                        $arWidth = (float)($ar['width_mm'] ?? 0) > 0 ? round((float)$ar['width_mm'] / 10, 1) : $telaWidth;
                                        $arGramms = (float)($ar['microns'] ?? 0) > 0 ? (float)$ar['microns'] : $telaGramms;
                                    ?>
                                        <option value="<?=$ar['id']?>" 
                                                data-width="<?=$arWidth?>" 
                                                data-gramms="<?=$arGramms?>"
                                                <?=!empty($ar['is_mounted']) ? 'selected' : ''?>>
                                            <?=htmlspecialchars($ar['roll_code'])?> (<?=number_format((float)$ar['weight_kg'], 1)?> Kg<?=!empty($ar['is_mounted']) ? ' - MONTADA' : ''?> &bull; Ancho: <?=$arWidth?> cm)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <span style="color:#64748B;font-size:12px;font-style:italic">Se auto-detectará la bobina activa montada en la OT.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Descripción / SKU:</td>
                        <td class="tdnrm">
                            <input type="text" name="sku_code" class="inptxt" style="width:100%" value="<?=htmlspecialchars($skuPrintDefault)?>" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft" style="font-weight:bold;color:#0F172A">
                            <i class="fa fa-weight-hanging" style="color:#2563EB"></i> Peso Bobina Saliente (Kg):
                        </td>
                        <td class="tdnrm">
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                                <input type="number" step="0.01" min="0.1" name="weight_kg" id="flexo-weight-kg" class="inptxt" style="width:140px;font-weight:bold;font-size:16px;color:#0F172A;border:2px solid #3B82F6;border-radius:6px;padding:7px 10px" placeholder="0.00" required oninput="recalcFlexoMetersFromWeight()" onchange="recalcFlexoMetersFromWeight()">
                                <span style="font-size:14px;font-weight:bold;color:#475569">Kg neto</span>
                                <button type="button" id="btn-read-scale-flexo" class="btnorange" style="padding:7px 16px;font-size:12.5px;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:6px" onclick="captureFlexoScaleWeight()">
                                    <i class="fa fa-balance-scale"></i> ⚖️ Capturar Balanza
                                </button>
                            </div>
                            <div id="scale-status-msg" style="font-size:11.5px;color:#64748B;margin-top:5px">
                                Ingrese el peso de la bobina impresa saliente o presione <b>Capturar Balanza</b> para pesaje directo en vivo.
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft" style="font-weight:bold;color:#0F172A">
                            <i class="fa fa-ruler-combined" style="color:#059669"></i> Metros Lineales:
                        </td>
                        <td class="tdnrm">
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                                <input type="number" step="1" min="1" name="meters" id="flexo-meters" class="inptxt" style="width:140px;font-weight:bold;font-size:16px;color:#065F46;border:2px solid #10B981;border-radius:6px;padding:7px 10px" value="<?=$suggestedMeters > 0 ? (int)$suggestedMeters : 0?>" required>
                                <span style="font-size:14px;font-weight:bold;color:#065F46">metros aprox.</span>
                                <button type="button" class="btngrey" style="padding:6px 12px;font-size:11.5px;cursor:pointer;display:inline-flex;align-items:center;gap:4px" onclick="recalcFlexoMetersFromWeight(true)" title="Recalcular metros según el peso actual">
                                    <i class="fa fa-sync-alt"></i> Recalcular por Peso
                                </button>
                                <?php if ($suggestedMeters > 0): ?>
                                    <button type="button" class="btngrey" style="padding:6px 10px;font-size:11px;cursor:pointer" onclick="document.getElementById('flexo-meters').value = '<?=(int)$suggestedMeters?>'">
                                        Usar programado (<?=(int)$suggestedMeters?> m)
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div id="meters-calc-info" style="font-size:12px;color:#059669;margin-top:6px;background:#F0FDF4;border:1px solid #BBF7D0;padding:6px 10px;border-radius:4px;display:inline-block">
                                <i class="fa fa-calculator"></i> <b>Cálculo automático según peso:</b> <span id="meters-ratio-text">~<?=$metersPerKg?> metros por kg</span> (Ancho: <span id="calc-width-text"><?=$telaWidth?></span> cm &bull; Gramaje: <span id="calc-gramms-text"><?=$telaGramms?></span> g/m²).
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Impresión Rótulo:</td>
                        <td class="tdnrm">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
                                <input type="checkbox" name="auto_print" value="1" checked>
                                <b>Mandar a imprimir etiqueta automáticamente en Zebra (100×70mm)</b>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm" style="padding-top:14px">
                            <button type="submit" class="btngreen" style="font-size:14px;padding:11px 22px;border:none;cursor:pointer;border-radius:4px;display:inline-flex;align-items:center;gap:8px">
                                <i class="fa fa-tag"></i> 🏷️ Generar y Rotular Bobina Semielaborada
                            </button>
                        </td>
                    </tr>
                    </table>
                </form>
            </div>

            <!-- COLUMNA 2: RESUMEN Y BOBINAS IMPRESAS GENERADAS -->
            <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
                <div style="font-size:17px;font-weight:bold;color:#0F172A;margin-bottom:14px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-history" style="color:#0284C7"></i> Bobinas Impresas Emitidas en esta OT
                </div>

                <?php
                $totSeWeight = 0.0;
                $totSeMeters = 0.0;
                foreach ($woSemielaboratedRolls as $wr) {
                    $totSeWeight += (float)($wr['weight_kg'] ?? 0);
                    $totSeMeters += (float)($wr['meters'] ?? 0);
                }
                ?>

                <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:10px;margin-bottom:16px">
                    <div style="background:#F0F9FF;border:1px solid #BAE6FD;border-radius:6px;padding:12px;text-align:center">
                        <div style="font-size:11px;font-weight:bold;color:#0369A1;text-transform:uppercase">Bobinas</div>
                        <div style="font-size:20px;font-weight:900;color:#0C4A6E"><?=count($woSemielaboratedRolls)?></div>
                    </div>
                    <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:6px;padding:12px;text-align:center">
                        <div style="font-size:11px;font-weight:bold;color:#15803D;text-transform:uppercase">Kilos Prod.</div>
                        <div style="font-size:20px;font-weight:900;color:#14532D"><?=number_format($totSeWeight, 1, ',', '.')?></div>
                    </div>
                    <div style="background:#FAF5FF;border:1px solid #E9D5FF;border-radius:6px;padding:12px;text-align:center">
                        <div style="font-size:11px;font-weight:bold;color:#7E22CE;text-transform:uppercase">Metros</div>
                        <div style="font-size:20px;font-weight:900;color:#581C87"><?=number_format($totSeMeters, 0, ',', '.')?></div>
                    </div>
                </div>

                <?php if (empty($woSemielaboratedRolls)): ?>
                    <div style="text-align:center;padding:32px 16px;color:#94A3B8;font-size:13px;background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:6px">
                        <i class="fa fa-info-circle fa-2x" style="color:#CBD5E1;margin-bottom:8px;display:block"></i>
                        Aún no se han generado bobinas semielaboradas para esta OT.<br>
                        Complete el formulario para emitir la primera bobina impresa.
                    </div>
                <?php else: ?>
                    <div style="max-height:280px;overflow-y:auto;border:1px solid #E2E8F0;border-radius:6px">
                        <table style="width:100%;border-collapse:collapse;font-size:12.5px">
                        <thead>
                            <tr style="background:#F8FAFC;color:#475569;border-bottom:1px solid #CBD5E1">
                                <th style="padding:8px;text-align:left">Código Rollo</th>
                                <th style="padding:8px;text-align:left">Madre</th>
                                <th style="padding:8px;text-align:right">Peso</th>
                                <th style="padding:8px;text-align:right">Metros</th>
                                <th style="padding:8px;text-align:center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($woSemielaboratedRolls as $wr): ?>
                                <tr style="border-bottom:1px solid #F1F5F9">
                                    <td style="padding:8px;font-weight:bold;color:#6366F1">
                                        <i class="fa fa-barcode"></i> <?=htmlspecialchars($wr['roll_code'])?>
                                    </td>
                                    <td style="padding:8px;color:#64748B">
                                        <?=htmlspecialchars($wr['parent_roll_code'] ?: ('ID #' . ($wr['parent_roll_id'] ?? '-')))?>
                                    </td>
                                    <td style="padding:8px;text-align:right">
                                        <b><?=number_format((float)$wr['weight_kg'], 2, ',', '.')?> Kg</b>
                                    </td>
                                    <td style="padding:8px;text-align:right">
                                        <?=number_format((float)$wr['meters'], 0, ',', '.')?> m
                                    </td>
                                    <td style="padding:8px;text-align:center">
                                        <a href="/rolls/<?=(int)$wr['id']?>/label" target="_blank" class="btngrey" style="padding:3px 7px;font-size:11px;text-decoration:none;margin-right:3px" title="Ver etiqueta 100×70">
                                            <i class="fa fa-tag"></i> 100×70
                                        </a>
                                        <a href="/rolls/<?=(int)$wr['id']?>/label?server_print=1" target="_blank" class="btnblue" style="padding:3px 7px;font-size:11px;text-decoration:none;margin-right:3px" title="Imprimir en Zebra">
                                            <i class="fa fa-print"></i> Zebra
                                        </a>
                                        <a href="/production/traceability?q=<?=urlencode($wr['roll_code'])?>" target="_blank" class="btngreen" style="padding:3px 7px;font-size:11px;text-decoration:none" title="Ver árbol de trazabilidad">
                                            <i class="fa fa-sitemap"></i> 🌳
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        var defaultTelaWidth = <?=(float)$telaWidth?>;
        var defaultTelaGramms = <?=(float)$telaGramms?>;
        var activeTelaWidth = defaultTelaWidth;
        var activeTelaGramms = defaultTelaGramms;

        function onParentRollChange() {
            var sel = document.getElementById('parent_roll_id');
            if (sel) {
                var opt = sel.options[sel.selectedIndex];
                if (opt) {
                    var w = parseFloat(opt.getAttribute('data-width')) || defaultTelaWidth;
                    var g = parseFloat(opt.getAttribute('data-gramms')) || defaultTelaGramms;
                    activeTelaWidth = w > 0 ? w : defaultTelaWidth;
                    activeTelaGramms = g > 0 ? g : defaultTelaGramms;
                }
            }
            updateMetersRatioDisplay();
            var wInp = document.getElementById('flexo-weight-kg');
            if (wInp && parseFloat(wInp.value) > 0) {
                recalcFlexoMetersFromWeight(false);
            }
        }

        function updateMetersRatioDisplay() {
            var ratio = 100000.0 / (activeTelaWidth * activeTelaGramms);
            var rTxt = document.getElementById('meters-ratio-text');
            if (rTxt) rTxt.innerText = '~' + ratio.toFixed(2) + ' metros por kg';
            var wTxt = document.getElementById('calc-width-text');
            if (wTxt) wTxt.innerText = activeTelaWidth;
            var gTxt = document.getElementById('calc-gramms-text');
            if (gTxt) gTxt.innerText = activeTelaGramms;
        }

        function recalcFlexoMetersFromWeight(forceFlash) {
            var weightInput = document.getElementById('flexo-weight-kg');
            var metersInput = document.getElementById('flexo-meters');
            if (!weightInput || !metersInput) return;
            
            var weight = parseFloat(String(weightInput.value).replace(',', '.')) || 0;
            if (weight > 0) {
                // Metros aproximados según peso, ancho de tela y gramaje:
                // metros = (peso_kg * 100000) / (ancho_cm * gramaje_g_m2)
                var ratio = 100000.0 / (activeTelaWidth * activeTelaGramms);
                var approxMeters = Math.round(weight * ratio);
                if (approxMeters <= 0) approxMeters = 1;
                metersInput.value = approxMeters;
                
                var infoBox = document.getElementById('meters-calc-info');
                if (infoBox) {
                    infoBox.innerHTML = '<i class="fa fa-check-circle" style="color:#16a34a"></i> <b>Aproximado por peso:</b> <b>' + approxMeters.toLocaleString('es-CL') + ' metros</b> (~' + ratio.toFixed(2) + ' m/kg con Ancho ' + activeTelaWidth + ' cm y ' + activeTelaGramms + ' g/m²).';
                }
                
                if (forceFlash) {
                    metersInput.style.backgroundColor = '#dcfce7';
                    setTimeout(function() { metersInput.style.backgroundColor = '#ffffff'; }, 1000);
                }
            } else {
                var infoBox = document.getElementById('meters-calc-info');
                if (infoBox) {
                    var ratio = 100000.0 / (activeTelaWidth * activeTelaGramms);
                    infoBox.innerHTML = '<i class="fa fa-calculator"></i> <b>Cálculo automático según peso:</b> ~' + ratio.toFixed(2) + ' metros por kg (Ancho: ' + activeTelaWidth + ' cm &bull; Gramaje: ' + activeTelaGramms + ' g/m²). Ingrese el peso para calcular.';
                }
            }
        }

        async function captureFlexoScaleWeight() {
            var btn = document.getElementById('btn-read-scale-flexo');
            var input = document.getElementById('flexo-weight-kg');
            var statusMsg = document.getElementById('scale-status-msg');
            if (!input || !btn) return;
            var oldHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Leyendo balanza...';
            btn.disabled = true;
            try {
                // Lectura con estabilidad: varias lecturas iguales, estables y > 0 (no se acepta peso en movimiento).
                var data = await (window.UnibagHardware ? window.UnibagHardware.readStableWeight() : fetch('/api/scale/weight', { cache: 'no-store' }).then(function (r) { return r.json(); }));
                if (data && data.ok && data.weight_kg !== undefined) {
                    var w = parseFloat(data.weight_kg);
                    input.value = w.toFixed(2);
                    input.style.backgroundColor = '#dcfce7';
                    if (statusMsg) {
                        statusMsg.innerHTML = '<span style="color:#16a34a;font-weight:bold"><i class="fa fa-check"></i> Peso capturado desde balanza: ' + w.toFixed(2) + ' Kg</span>';
                    }
                    // Calcular de inmediato los metros aproximados según el peso:
                    recalcFlexoMetersFromWeight(true);
                    setTimeout(function() { input.style.backgroundColor = '#ffffff'; }, 1200);
                } else {
                    alert('No se pudo obtener peso de balanza: ' + (data.error || 'Sin lectura'));
                }
            } catch(e) {
                alert('Error al conectar con la balanza: ' + e.message);
            } finally {
                btn.innerHTML = oldHtml;
                btn.disabled = false;
            }
        }

        // Inicializar al cargar
        window.addEventListener('DOMContentLoaded', function() {
            onParentRollChange();
        });
        </script>

    <?php

