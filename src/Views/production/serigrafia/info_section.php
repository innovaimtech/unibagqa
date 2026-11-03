<?php
    ?>
    <?php if ($isSeriPulpo): ?>
        <!-- ============================================================== -->
        <!-- RAMA B: SERIGRAFÍA PULPO (editxid.php líneas 3085 - 3395)      -->
        <!-- ============================================================== -->
        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_cliente', 'icon_info_cliente')">
                <span><i class="fa fa-fw fa-user"></i> Información Cliente</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_cliente">Minimizar</span>
                    <i id="icon_info_cliente" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_cliente">
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="250">
                    <col width="30%">
                    <col width="160">
                    <col>
                </colgroup>
                <tr>
                    <td class="tdleft">N° OT</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['prd_number'])?></td>
                    <td class="tdleft">Ancho</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_width']?></td>
                </tr>
                <tr>
                    <td class="tdleft">Cliente</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                    <td class="tdleft">Alto</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_height']?></td>
                </tr>
                <tr>
                    <td class="tdleft">N° CC</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['req_number'])?></td>
                    <td class="tdleft">Fuelle</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_fuelle']?></td>
                </tr>
                <tr>
                    <td class="tdleft">Producto</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_number_prod'])?> | <?=htmlspecialchars((string)$agenda['item_title'])?></td>
                    <td class="tdleft">Cantidad de Bolsas</td>
                    <td class="tdnrm"><b><?=$fmt($agenda['ag_amount'])?></b></td>
                </tr>
                <tr>
                    <td class="tdleft">Materialidad</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">&nbsp;</td>
                </tr>
                <tr>
                    <td colspan="4" style="background:#EDF0F6;padding:4px"></td>
                </tr>
                <tr>
                    <td class="tdleft">Cant. Colores Frente</td>
                    <td class="tdnrm"><b><?=$fmt($amtColorsFrente)?></b></td>
                    <td class="tdleft">Cant. Colores Dorso</td>
                    <td class="tdnrm"><b><?=$fmt($amtColorsDorso)?></b></td>
                </tr>
                <?php for ($xx = 1; $xx <= 10; $xx++): ?>
                    <?php 
                        $colDesc = trim((string)($agenda["fab_print_colordesc_{$xx}"] ?? ''));
                        if ($colDesc === '') continue;
                        $hasFront = !empty($agenda["fab_print_colors_front_{$xx}"]) || ($amtColorsDorso == 0 && empty($agenda["fab_print_colors_back_{$xx}"]));
                        $hasBack = !empty($agenda["fab_print_colors_back_{$xx}"]);
                        if (!$hasFront && !$hasBack) continue;
                    ?>
                    <tr>
                        <td class="tdleft">Color N° <?=$xx?> (Frente)</td>
                        <td class="tdnrm"><?=$hasFront ? htmlspecialchars($colDesc) : '<span style="color:#94a3b8">-</span>'?></td>
                        <td class="tdleft">Color N° <?=$xx?> (Dorso)</td>
                        <td class="tdnrm"><?=$hasBack ? htmlspecialchars($colDesc) : '<span style="color:#94a3b8">-</span>'?></td>
                    </tr>
                <?php endfor; ?>
                <tr>
                    <td colspan="4" style="background:#EDF0F6;padding:4px"></td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Fecha de Entrega</td>
                    <td class="tdnrm" colspan="2"><b><?=htmlspecialchars((string)($agenda['req_despacho_desc'] !== '' ? $agenda['req_despacho_desc'] : $agenda['ag_date']))?></b></td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Fecha de solicitud de película</td>
                    <td class="tdnrm" colspan="2">
                        <?=(int)($agenda['req_cliche_peli_solic_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_solic_dat']) : '<b class="msg_save_err">N/A</b>'?>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Fecha de recepción de película</td>
                    <td class="tdnrm" colspan="2">
                        <?=(int)($agenda['req_cliche_peli_recep_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_recep_dat']) : '<b class="msg_save_err">N/A</b>'?>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Imagen Diseño</td>
                    <td class="tdnrm" colspan="2">
                        <?php if (!empty($agenda['fab_design_imagehash']) && $agenda['fab_design_imagehash'] !== 'dummy'): ?>
                            <?php $dUrl = '/production/design-image?folder=order&hash=' . rawurlencode((string)$agenda['fab_design_imagehash']); ?>
                            <div style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap">
                                <a href="<?=$dUrl?>" target="_blank" title="Clic para ampliar">
                                    <img src="<?=$dUrl?>" alt="Foto Diseño" style="max-height:75px;max-width:120px;object-fit:contain;border:1px solid #cbd5e1;border-radius:4px;background:#fff;vertical-align:middle;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
                                </a>
                                <a href="<?=$dUrl?>" target="_blank" class="btngrey" style="padding:6px 12px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px">
                                    <i class="fa fa-fw fa-image" style="color:white;"></i> Ver Foto Completa
                                </a>
                            </div>
                        <?php else: ?>
                            <span style="color:#999">Sin imagen</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">% de merma</td>
                    <td class="tdnrm" colspan="2"><?=$fmt($agenda['req_operador_mermaperc'], 2)?> %</td>
                </tr>
                <tr>
                    <td class="tdleft">Bolsas producidas / en curso</td>
                    <td class="tdnrm" colspan="3"><?=$fmt($stats['total_produced_this'])?> de <?=$fmt($stats['target_amount'] ?? $agenda['ag_amount'])?> (<?=$fmt($stats['percent_completed_this'], 2)?> %)</td>
                </tr>
                <tr>
                    <td class="tdleft">Bolsas producidas / total</td>
                    <td class="tdnrm" colspan="3"><?=$fmt($stats['total_produced_all'])?> de <?=$fmt($stats['order_amount'] ?? $agenda['item_amount'])?> (<?=$fmt($stats['percent_completed_all'], 2)?> %)</td>
                </tr>
                <tr>
                    <td class="tdleft">Saldo total</td>
                    <td class="tdnrm" colspan="3"><b><?=$fmt($stats['saldo_total'])?> unidades</b></td>
                </tr>
                <?php if ($isSeriPulpo): ?>
                    <tr>
                        <td class="tdleft">Pasadas Frente acumuladas</td>
                        <td class="tdnrm"><b><?=$fmt($stats['total_pulpo_frente_agenda'] ?? 0)?></b> pasadas</td>
                        <td class="tdleft">Pasadas Dorso acumuladas</td>
                        <td class="tdnrm"><b><?=$fmt($stats['total_pulpo_dorso_agenda'] ?? 0)?></b> pasadas</td>
                    </tr>
                    <tr>
                        <td class="tdleft">Bolsas en proceso (semi-terminadas)</td>
                        <td class="tdnrm" colspan="3">
                            <?php
                            $sF = (float)($stats['surplus_frente'] ?? 0);
                            $sD = (float)($stats['surplus_dorso'] ?? 0);
                            ?>
                            <?php if ($sF > 0): ?>
                                <span style="background:#FEF3C7;color:#92400E;padding:4px 10px;border-radius:4px;font-weight:bold;display:inline-flex;align-items:center;gap:6px">
                                    <i class="fa fa-exclamation-circle" style="color:#D97706"></i>
                                    <?=$fmt($sF)?> bolsas con FRENTE impreso (pendientes de dorso)
                                </span>
                            <?php elseif ($sD > 0): ?>
                                <span style="background:#FEF3C7;color:#92400E;padding:4px 10px;border-radius:4px;font-weight:bold;display:inline-flex;align-items:center;gap:6px">
                                    <i class="fa fa-exclamation-circle" style="color:#D97706"></i>
                                    <?=$fmt($sD)?> bolsas con DORSO impreso (pendientes de frente)
                                </span>
                            <?php else: ?>
                                <span style="color:#059669;font-weight:bold">
                                    <i class="fa fa-check-circle"></i> 0 bolsas pendientes (Frente y Dorso al día)
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td class="tdleft">Contador total estimado</td>
                    <td class="tdnrm" colspan="3">
                        <b><?=$fmt(round((float)$agenda['ag_amount'] / max(1, (int)($agenda['req_solic_devprints_cc'] ?? 1))))?></b>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft">Imágenes impresas en bastidor</td>
                    <td class="tdnrm" colspan="3">
                        <form method="get" action="/production/work-orders/operate" class="js-bastidor-form" onsubmit="return handleBastidorSubmit(event, this);" style="margin:0;display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap">
                            <input type="hidden" name="agid" value="<?=$agId?>">
                            <input type="hidden" name="mode" value="setspecparams">
                            <input type="hidden" name="req_id" value="<?=(int)($agenda['ag_reqid'] ?? $agenda['req_id'] ?? 0)?>">
                            <input type="hidden" name="return_to" value="<?=htmlspecialchars($_SERVER['REQUEST_URI'] ?? '')?>">
                            <select class="inptxt js-bastidor-select" id="idx_bastidor_amt_top" name="bastidoramt" style="width:120px;font-weight:bold" onchange="var fm = document.getElementById('idx_seri_bastidor_multiplier'); if (fm) { fm.value = this.value; if (typeof calcSeriUnitsFromCounters === 'function') calcSeriUnitsFromCounters(); }">
                                <?php for ($zzz = 1; $zzz <= 5; $zzz++): ?>
                                    <option value="<?=$zzz?>" <?=((int)($agenda['req_operador_bastidoramt'] ?? 1) === $zzz) ? 'selected' : ''?>><?=$zzz?></option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit" class="btngreen js-bastidor-btn" style="padding:5px 12px;border:none;cursor:pointer;font-weight:bold;display:inline-flex;align-items:center;gap:5px">
                                <i class="fa fa-save"></i> <span>Guardar</span>
                            </button>
                            <span class="js-bastidor-feedback" style="display:none;font-size:12px;font-weight:bold;align-items:center;gap:4px"></span>
                        </form>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft">Tipo</td>
                    <td class="tdnrm" colspan="3"><?=htmlspecialchars((string)($machineInit['type_ant_title'] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Máquina</td>
                    <td class="tdnrm" colspan="3"><?=htmlspecialchars((string)($machineInit['equipo_name'] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Comentarios Apertura</td>
                    <td class="tdnrm" colspan="3"><?=htmlspecialchars($aperturaComments !== '' ? $aperturaComments : 'Sin comentarios')?></td>
                </tr>
                </table>
            </div>
        </div>

        <!-- SELECTOR: LADO A IMPRIMIR (Frente / Dorso) -->
        <div style="background:#FFFFFF;border:1.5px solid #115452;border-radius:5px;padding:12px 18px;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;box-shadow:0 1px 3px rgba(0,0,0,0.06)">
            <div style="font-size:14px;font-weight:bold;color:#115452;display:flex;align-items:center;gap:10px">
                <i class="fa fa-print" style="font-size:18px;color:#059669"></i> Lado a imprimir seleccionado:
                <span style="background:#E8F0EF;color:#115452;padding:4px 14px;border-radius:4px;font-size:13px;font-weight:bold;letter-spacing:0.5px">
                    <?=$amtColorsDorso > 0 ? strtoupper($colorMode) : 'FRENTE (SOLO FRENTE)'?>
                </span>
            </div>
            <?php if ($amtColorsDorso > 0): ?>
            <div style="display:flex;align-items:center;gap:8px">
                <span style="font-size:12.5px;color:#64748B;font-weight:bold">Seleccionar lado:</span>
                <a href="/production/work-orders/operate?agid=<?=$agId?>&colormode=Frente" class="<?=$colorMode === 'Frente' ? 'btngreen' : 'btngrey'?>" style="padding:7px 18px;text-decoration:none;font-weight:bold;font-size:13px;display:inline-flex;align-items:center;gap:6px">
                    <i class="fa fa-arrow-right"></i> Frente
                </a>
                <a href="/production/work-orders/operate?agid=<?=$agId?>&colormode=Dorso" class="<?=$colorMode === 'Dorso' ? 'btngreen' : 'btngrey'?>" style="padding:7px 18px;text-decoration:none;font-weight:bold;font-size:13px;display:inline-flex;align-items:center;gap:6px">
                    <i class="fa fa-arrow-left"></i> Dorso
                </a>
            </div>
            <?php else: ?>
            <div style="font-size:12.5px;color:#64748B">
                <i class="fa fa-info-circle" style="color:#0284C7"></i> <i>Esta OT no tiene impresión en dorso (0 colores dorso).</i>
            </div>
            <?php endif; ?>
        </div>

        <!-- 2. INFORMACIÓN DE FABRICACIÓN PULPO (form.v2pulpo.php) -->
        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_fab_pulpo', 'icon_info_fab_pulpo')">
                <span><i class="fa fa-fw fa-industry"></i> Información de Fabricación (Pulpo &mdash; Lado <?=htmlspecialchars($colorMode)?>)</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_fab_pulpo">Minimizar</span>
                    <i id="icon_info_fab_pulpo" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_fab_pulpo" style="padding:15px">
                <?php if (isset($_GET['saved_pulpo'])): ?>
                    <div style="background:#ECFDF5;border:1px solid #6EE7B7;color:#065F46;padding:10px 14px;border-radius:4px;margin-bottom:12px;font-size:13px">
                        <i class="fa fa-check-circle"></i> Información de Fabricación guardada exitosamente para lado <?=htmlspecialchars($colorMode)?>.
                    </div>
                <?php endif; ?>
                <div style="margin-bottom:12px;background:#F8FAFC;border:1px solid #CBD5E1;border-radius:4px;padding:10px 12px">
                    <?php renderMachineFabricationExtras($agenda, false); ?>
                </div>
                <form method="post" action="/production/work-orders/events/pulpo-fabricacion">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="colormode" value="<?=htmlspecialchars($colorMode)?>">

                    <!-- Parámetros de Horno y Máquina -->
                    <table border="0" width="100%" cellpadding="6" cellspacing="0" style="margin-bottom:15px;background:#FAFAFA;border:1px solid #E5E7EB;border-radius:4px">
                    <colgroup><col width="22%"><col width="28%"><col width="22%"><col width="28%"></colgroup>
                    <tr>
                        <td class="tdleft">Temperatura Horno (°C)</td>
                        <td class="tdnrm">
                            <input type="number" step="1" name="temp_horno" class="inptxt" style="width:140px" value="<?=htmlspecialchars((string)($pulpoAgendaData['pulpo_temp_horno_' . strtolower($colorMode)] ?? ''))?>" placeholder="0"> °C
                        </td>
                        <td class="tdleft">Velocidad Máquina</td>
                        <td class="tdnrm">
                            <input type="number" step="0.1" name="maq_speed" class="inptxt" style="width:140px" value="<?=htmlspecialchars((string)($pulpoAgendaData['pulpo_maq_speed_' . strtolower($colorMode)] ?? ''))?>" placeholder="0.0">
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Velocidad Horno</td>
                        <td class="tdnrm">
                            <input type="number" step="0.1" name="horno_speed" class="inptxt" style="width:140px" value="<?=htmlspecialchars((string)($pulpoAgendaData['pulpo_horno_speed_' . strtolower($colorMode)] ?? ''))?>" placeholder="0.0">
                        </td>
                        <td class="tdleft">Medidas Camillas</td>
                        <td class="tdnrm">
                            <input type="text" name="medidas_camillas" class="inptxt" style="width:200px" value="<?=htmlspecialchars((string)($pulpoAgendaData['pulpo_medidas_camillas_' . strtolower($colorMode)] ?? ''))?>" placeholder="Ej: 45 x 55 cm">
                        </td>
                    </tr>
                    </table>

                    <!-- Tabla de 10 Unidades de Bastidores y Secadores -->
                    <div id="pulpo-config-10" style="font-weight:bold;color:#115452;font-size:13.5px;margin-bottom:8px">
                        <span><i class="fa fa-th"></i> Configuración de 10 Unidades de Bastidores / Secadores</span>
                    </div>
                    <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border:1px solid #DDDDDD">
                    <thead>
                        <tr>
                            <td class="tdheader" align="center" width="45">Unid.</td>
                            <td class="tdheader" width="220">Color Asignado / Acción</td>
                            <td class="tdheader" align="center" width="110">N° Bastidor</td>
                            <td class="tdheader" width="150">Malla / Mesh</td>
                            <td class="tdheader" align="center" width="120">Temp. Secador (°C)</td>
                            <td class="tdheader" align="center" width="100">Tinta Utilizada</td>
                            <td class="tdheader" align="center" width="95">Carga Tinta</td>
                            <td class="tdheader" align="center" width="95">Sobrante Tinta</td>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sideColors = [];
                    for ($cIdx = 1; $cIdx <= 10; $cIdx++) {
                        $fCheck = (int)($agenda["fab_print_colors_front_{$cIdx}"] ?? 0);
                        $bCheck = (int)($agenda["fab_print_colors_back_{$cIdx}"] ?? 0);
                        $cDesc = trim((string)($agenda["fab_print_colordesc_{$cIdx}"] ?? ''));
                        if ($cDesc !== '') {
                            if ($colorMode === 'Frente' && ($fCheck > 0 || ($bCheck === 0 && $amtColorsDorso === 0))) {
                                $sideColors[$cIdx] = "Color #{$cIdx}: " . $cDesc;
                            } elseif ($colorMode === 'Dorso' && $bCheck > 0) {
                                $sideColors[$cIdx] = "Color #{$cIdx}: " . $cDesc;
                            }
                        }
                    }

                    for ($px = 0; $px < 10; $px++):
                        $unit = $pulpoUnits[$px] ?? [];
                        $savedColor = trim((string)($unit['color_id'] ?? ''));
                        $savedBastidor = (string)($unit['val_num_bastidor'] ?? '');
                        $savedMesh = (float)($unit['val_mesh'] ?? 0);
                        $savedTempSec = (float)($unit['val_temp_secador'] ?? 0);
                        $savedTotalTinta = (float)($unit['val_total_tinta'] ?? 0);
                        $savedCargaTinta = (float)($unit['val_carga_tinta'] ?? 0);
                        $savedSobranteTinta = (float)($unit['val_sobrante_tinta'] ?? 0);
                    ?>
                    <tr>
                        <td class="tdnrm" align="center" style="font-weight:bold;background:#F8FAFC"><?=$px + 1?></td>
                        <td class="tdnrm">
                            <select name="unit_color_<?=$px?>" class="inptxt" style="width:100%">
                                <option value="">&lt; Unidad Libre &gt;</option>
                                <option value="-1" <?=$savedColor === '-1' ? 'selected' : ''?>>-- Secador Intermedio --</option>
                                <?php foreach ($sideColors as $cid => $clbl): ?>
                                    <option value="<?=$cid?>" <?=$savedColor === (string)$cid ? 'selected' : ''?>><?=htmlspecialchars($clbl)?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="tdnrm" align="center">
                            <input type="text" name="unit_bastidor_<?=$px?>" class="inptxt" style="width:95px;text-align:center" value="<?=htmlspecialchars($savedBastidor)?>" placeholder="N°">
                        </td>
                        <td class="tdnrm">
                            <select name="unit_mesh_<?=$px?>" class="inptxt" style="width:100%">
                                <option value="0">&lt; Seleccione &gt;</option>
                                <?php foreach ($bastidoresList as $b):
                                    $bMeshVal = (float)($b['valor1'] ?? $b['densidad'] ?? $b['codigo'] ?? 0);
                                ?>
                                    <option value="<?=$bMeshVal?>" <?=abs($savedMesh - $bMeshVal) < 0.01 ? 'selected' : ''?>>
                                        <?=htmlspecialchars((string)($b['descripcion'] ?? $b['codigo'] ?? ''))?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="tdnrm" align="center">
                            <input type="number" step="1" name="unit_temp_sec_<?=$px?>" class="inptxt" style="width:95px;text-align:center" value="<?=$savedTempSec > 0 ? (int)$savedTempSec : ''?>" placeholder="°C">
                        </td>
                        <td class="tdnrm" align="center">
                            <input type="number" step="0.01" name="unit_tinta_<?=$px?>" class="inptxt" style="width:85px;text-align:center" value="<?=$savedTotalTinta > 0 ? $savedTotalTinta : ''?>" placeholder="0.00">
                        </td>
                        <td class="tdnrm" align="center">
                            <input type="number" step="0.01" name="unit_carga_tinta_<?=$px?>" class="inptxt" style="width:80px;text-align:center" value="<?=$savedCargaTinta > 0 ? $savedCargaTinta : ''?>" placeholder="0.00">
                        </td>
                        <td class="tdnrm" align="center">
                            <input type="number" step="0.01" name="unit_sobrante_tinta_<?=$px?>" class="inptxt" style="width:80px;text-align:center" value="<?=$savedSobranteTinta > 0 ? $savedSobranteTinta : ''?>" placeholder="0.00">
                        </td>
                    </tr>
                    <?php endfor; ?>
                    </tbody>
                    </table>

                    <div style="margin-top:12px;text-align:right">
                        <button type="submit" class="btngreen" style="padding:9px 24px;font-size:13.5px;border:none;cursor:pointer">
                            <i class="fa fa-save"></i> Guardar Fabricación Pulpo (Lado <?=htmlspecialchars($colorMode)?>)
                        </button>
                    </div>
                </form>
            </div>
        </div>



    <?php elseif ($isSeriPlana): ?>
        <!-- ============================================================== -->
        <!-- RAMA C: SERIGRAFÍA PLANA (editxid.php líneas 3409 - 3650)      -->
        <!-- ============================================================== -->
        <?php
            $seriImgHash = trim((string)($agenda['fab_design_imagehash'] ?? $agenda['item_img_hash'] ?? ''));
            $seriHasImg = ($seriImgHash !== '' && $seriImgHash !== 'dummy');
            $seriImgUrl = $seriHasImg ? ('/production/design-image?folder=order&hash=' . rawurlencode($seriImgHash)) : '';
            $seriFullDesign = trim((string)($agenda['prd_name'] ?? ($agenda['item_title'] ?? '')));
            $seriMedidasStr = (int)($agenda['fab_med_width'] ?? 0) . ' x ' . (int)($agenda['fab_med_height'] ?? 0);
            if ((int)($agenda['fab_med_fuelle'] ?? 0) > 0) {
                $seriMedidasStr .= ' + ' . (int)$agenda['fab_med_fuelle'];
            }
            $seriMedidasStr .= ' cm';
            $seriMermaPerc = (float)($agenda['req_operador_mermaperc'] ?? 0);
            $seriDevPrints = max(1, (int)($agenda['req_solic_devprints_cc'] ?? 1));
            $seriMlinProg = round((float)$agenda['ag_amount'] * (float)$corte_m2);
            $seriMlinAddi = round($seriMlinProg / 100 * $seriMermaPerc);
            $seriMlinPrint = round($seriMlinProg + $seriMlinAddi);
            $seriMermaUnits = round((float)$agenda['ag_amount'] / 100 * $seriMermaPerc);
            $seriRealUnits = (float)$agenda['ag_amount'] + $seriMermaUnits;
            $seriPasadasPrint = (int)round($seriRealUnits / $seriDevPrints);
            $seriKgsProcess = round(
                $seriMlinPrint
                * (float)($agenda['req_operador_tela_width'] ?? 0)
                / 100
                * (float)($agenda['fab_mat_gramms'] ?? 0)
                / 1000
            );
        ?>
        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_cliente', 'icon_info_cliente')">
                <span><i class="fa fa-fw fa-user"></i> Información Cliente</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_cliente">Minimizar</span>
                    <i id="icon_info_cliente" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_cliente">
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="200">
                    <col width="40%">
                    <col width="185">
                    <col>
                </colgroup>
                <tr>
                    <td class="tdleft">N° OT</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['prd_number'])?></td>
                    <td class="tdleft">Cant. Colores</td>
                    <td class="tdnrm"><b><?=$fmt($amtColorsSeri)?></b></td>
                </tr>
                <tr>
                    <td class="tdleft">Cliente</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                    <td class="tdleft">Color N° 1</td>
                    <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_1'] ?? 0) || (int)($agenda['fab_print_colors_back_1'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? '')) : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft">N° CC</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['req_number'])?></td>
                    <td class="tdleft">Color N° 2</td>
                    <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_2'] ?? 0) || (int)($agenda['fab_print_colors_back_2'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_2'] ?? '')) : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft">Producto</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_number_prod'])?> | <?=htmlspecialchars((string)$agenda['item_title'])?></td>
                    <td class="tdleft">Color N° 3</td>
                    <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_3'] ?? 0) || (int)($agenda['fab_print_colors_back_3'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_3'] ?? '')) : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft">Materialidad</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                    <td class="tdleft">Color N° 4</td>
                    <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_4'] ?? 0) || (int)($agenda['fab_print_colors_back_4'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_4'] ?? '')) : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft">Ancho</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_width']?></td>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">&nbsp;</td>
                </tr>
                <tr>
                    <td class="tdleft">Alto</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_height']?></td>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">&nbsp;</td>
                </tr>
                <tr>
                    <td class="tdleft">Fuelle</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_fuelle']?></td>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">&nbsp;</td>
                </tr>
                <tr>
                    <td class="tdleft">Cantidad de Bolsas</td>
                    <td class="tdnrm"><b><?=$fmt($agenda['ag_amount'])?></b></td>
                    <td class="tdleft">Fecha de Entrega</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)($agenda['req_despacho_desc'] !== '' ? $agenda['req_despacho_desc'] : $agenda['ag_date']))?></b></td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Fecha solicitud película</td>
                    <td class="tdnrm" colspan="2"><?=(int)($agenda['req_cliche_peli_solic_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_solic_dat']) : '<b class="msg_save_err">N/A</b>'?></td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Fecha recepción película</td>
                    <td class="tdnrm" colspan="2"><?=(int)($agenda['req_cliche_peli_recep_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_recep_dat']) : '<b class="msg_save_err">N/A</b>'?></td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2" valign="middle">Imagen diseño</td>
                    <td class="tdnrm" colspan="2" valign="middle">
                        <?php if ($seriHasImg): ?>
                            <div style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap">
                                <a href="<?=$seriImgUrl?>" target="_blank" onclick="openDesignModal('<?=$seriImgUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($seriFullDesign))?>', '<?=htmlspecialchars($seriMedidasStr)?>'); return false;" title="Clic para ampliar diseño">
                                    <img src="<?=$seriImgUrl?>" alt="Foto Diseño" style="max-height:65px;max-width:105px;object-fit:contain;border:1px solid #CBD5E1;border-radius:4px;background:#fff;vertical-align:middle;box-shadow:0 1px 3px rgba(0,0,0,0.1);cursor:pointer">
                                </a>
                                <a href="<?=$seriImgUrl?>" target="_blank" onclick="openDesignModal('<?=$seriImgUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($seriFullDesign))?>', '<?=htmlspecialchars($seriMedidasStr)?>'); return false;" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                    <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen
                                </a>
                            </div>
                        <?php else: ?>
                            <span style="color:#999">Sin imagen cargada</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php for ($x = 0; $x < 5; $x++): ?>
                    <?php if (!empty($agenda["req_prod_adjfile_{$x}"])):
                        $adjFile = (string)$agenda["req_prod_adjfile_{$x}"];
                        $adjIsImage = (bool)preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $adjFile);
                        $adjUrl = '/docs/prod/' . rawurlencode($adjFile);
                    ?>
                    <tr>
                        <td class="tdleft" colspan="2" valign="middle">Imagen x #<?=($x + 1)?></td>
                        <td class="tdnrm" colspan="2" valign="middle">
                            <?php if ($adjIsImage): ?>
                                <div style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap">
                                    <a href="<?=$adjUrl?>" target="_blank" onclick="openDesignModal('<?=$adjUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($seriMedidasStr)?>'); return false;" title="Clic para ampliar adjunto">
                                        <img src="<?=$adjUrl?>" alt="Adjunto #<?=($x + 1)?>" style="max-height:55px;max-width:85px;object-fit:contain;border:1px solid #CBD5E1;border-radius:4px;background:#fff;vertical-align:middle;box-shadow:0 1px 3px rgba(0,0,0,0.1);cursor:pointer">
                                    </a>
                                    <a href="<?=$adjUrl?>" target="_blank" onclick="openDesignModal('<?=$adjUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($seriMedidasStr)?>'); return false;" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                        <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen
                                    </a>
                                </div>
                            <?php else: ?>
                                <a href="<?=$adjUrl?>" target="_blank" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                    <i class="fa fa-fw fa-file" style="color:white;"></i> Mostrar Documento
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($agenda["req_prod_adjcomments_{$x}"])): ?>
                                <span style="margin-left:8px;color:#64748B;font-size:11.5px"><?=htmlspecialchars((string)$agenda["req_prod_adjcomments_{$x}"])?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endfor; ?>
                </table>
            </div>
        </div>

        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_fab', 'icon_info_fab')">
                <span><i class="fa fa-fw fa-info-circle"></i> Información de Fabricación</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_fab">Minimizar</span>
                    <i id="icon_info_fab" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_fab">
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="200">
                    <col width="40%">
                    <col width="185">
                    <col>
                </colgroup>
                <tr>
                    <td class="tdleft">Desarrollo</td>
                    <td class="tdnrm"><b><?=$fmt($corte_m2, 4)?> mtrs</b></td>
                    <td class="tdleft">Color N° 1</td>
                    <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_1'] ?? 0) || (int)($agenda['fab_print_colors_back_1'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? '')) : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft">Pie de Imprenta</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_pie_imprenta'] ?? '-'))?></td>
                    <td class="tdleft">Color N° 2</td>
                    <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_2'] ?? 0) || (int)($agenda['fab_print_colors_back_2'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_2'] ?? '')) : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft">N° Código de Barra</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['item_sellprice_barcodenumber'] ?? '-'))?></td>
                    <td class="tdleft">Color N° 3</td>
                    <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_3'] ?? 0) || (int)($agenda['fab_print_colors_back_3'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_3'] ?? '')) : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft">Impresiones en cilindro</td>
                    <td class="tdnrm">&nbsp;</td>
                    <td class="tdleft">Color N° 4</td>
                    <td class="tdnrm"><?=((int)($agenda['fab_print_colors_front_4'] ?? 0) || (int)($agenda['fab_print_colors_back_4'] ?? 0)) ? htmlspecialchars((string)($agenda['fab_print_colordesc_4'] ?? '')) : ''?></td>
                </tr>
                <tr>
                    <td class="tdleft">Impresiones por desarrollo</td>
                    <td class="tdnrm"><?=(int)($agenda['req_solic_devprints_cc'] ?? 0)?> impresiones</td>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">&nbsp;</td>
                </tr>
                <tr>
                    <td colspan="4"><div style="height:10px"></div></td>
                </tr>
                <tr>
                    <td class="tdleft">Materialidad</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_type'] ?? '-'))?></td>
                    <td class="tdleft">Metros a imprimir</td>
                    <td class="tdnrm"><?=$fmt($seriMlinPrint)?></td>
                </tr>
                <tr>
                    <td class="tdleft">Color Tela</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fabric_color'] ?? '-'))?></td>
                    <td class="tdleft">Contador impresora</td>
                    <td class="tdnrm"><?=$fmt($seriPasadasPrint)?></td>
                </tr>
                <tr>
                    <td class="tdleft">Ancho Tela</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_operador_tela_width'] ?? '-'))?></td>
                    <td class="tdleft">Kg a imprimir</td>
                    <td class="tdnrm"><?=$fmt($seriKgsProcess)?></td>
                </tr>
                <tr>
                    <td class="tdleft">Gramaje</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_mat_gramms'] ?? '-'))?> gr</td>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">&nbsp;</td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Cantidad de imágenes impresas en bastidor</td>
                    <td class="tdnrm" colspan="2">
                        <form method="get" action="/production/work-orders/operate" class="js-bastidor-form" onsubmit="return handleBastidorSubmit(event, this);" style="margin:0;display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap">
                            <input type="hidden" name="agid" value="<?=$agId?>">
                            <input type="hidden" name="mode" value="setspecparams">
                            <input type="hidden" name="req_id" value="<?=(int)($agenda['ag_reqid'] ?? $agenda['req_id'] ?? 0)?>">
                            <input type="hidden" name="return_to" value="<?=htmlspecialchars($_SERVER['REQUEST_URI'] ?? '')?>">
                            <select class="inptxt js-bastidor-select" id="idx_bastidor_amt_fab" name="bastidoramt" style="width:120px;font-weight:bold" onchange="var fm = document.getElementById('idx_seri_bastidor_multiplier'); if (fm) { fm.value = this.value; if (typeof calcSeriUnitsFromCounters === 'function') calcSeriUnitsFromCounters(); }">
                                <option value="">Seleccione</option>
                                <?php for ($zzz = 1; $zzz <= 5; $zzz++): ?>
                                    <option value="<?=$zzz?>" <?=((int)($agenda['req_operador_bastidoramt'] ?? 1) === $zzz) ? 'selected' : ''?>><?=$zzz?></option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit" class="btngreen js-bastidor-btn" style="padding:5px 12px;border:none;cursor:pointer;font-weight:bold;display:inline-flex;align-items:center;gap:5px">
                                <i class="fa fa-save"></i> <span>Guardar</span>
                            </button>
                            <span class="js-bastidor-feedback" style="display:none;font-size:12px;font-weight:bold;align-items:center;gap:4px"></span>
                        </form>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2" valign="middle">Imagen diseño</td>
                    <td class="tdnrm" colspan="2" valign="middle">
                        <?php if ($seriHasImg): ?>
                            <div style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap">
                                <a href="<?=$seriImgUrl?>" target="_blank" onclick="openDesignModal('<?=$seriImgUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($seriFullDesign))?>', '<?=htmlspecialchars($seriMedidasStr)?>'); return false;" title="Clic para ampliar diseño">
                                    <img src="<?=$seriImgUrl?>" alt="Foto Diseño" style="max-height:65px;max-width:105px;object-fit:contain;border:1px solid #CBD5E1;border-radius:4px;background:#fff;vertical-align:middle;box-shadow:0 1px 3px rgba(0,0,0,0.1);cursor:pointer">
                                </a>
                                <a href="<?=$seriImgUrl?>" target="_blank" onclick="openDesignModal('<?=$seriImgUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($seriFullDesign))?>', '<?=htmlspecialchars($seriMedidasStr)?>'); return false;" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                    <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen
                                </a>
                            </div>
                        <?php else: ?>
                            <span style="color:#999">Sin imagen cargada</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php for ($x = 0; $x < 5; $x++): ?>
                    <?php if (!empty($agenda["req_prod_adjfile_{$x}"])):
                        $adjFile = (string)$agenda["req_prod_adjfile_{$x}"];
                        $adjIsImage = (bool)preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $adjFile);
                        $adjUrl = '/docs/prod/' . rawurlencode($adjFile);
                    ?>
                    <tr>
                        <td class="tdleft" colspan="2" valign="middle">Imagen x #<?=($x + 1)?></td>
                        <td class="tdnrm" colspan="2" valign="middle">
                            <?php if ($adjIsImage): ?>
                                <div style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap">
                                    <a href="<?=$adjUrl?>" target="_blank" onclick="openDesignModal('<?=$adjUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($seriMedidasStr)?>'); return false;" title="Clic para ampliar adjunto">
                                        <img src="<?=$adjUrl?>" alt="Adjunto #<?=($x + 1)?>" style="max-height:55px;max-width:85px;object-fit:contain;border:1px solid #CBD5E1;border-radius:4px;background:#fff;vertical-align:middle;box-shadow:0 1px 3px rgba(0,0,0,0.1);cursor:pointer">
                                    </a>
                                    <a href="<?=$adjUrl?>" target="_blank" onclick="openDesignModal('<?=$adjUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($seriMedidasStr)?>'); return false;" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                        <i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen
                                    </a>
                                </div>
                            <?php else: ?>
                                <a href="<?=$adjUrl?>" target="_blank" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                    <i class="fa fa-fw fa-file" style="color:white;"></i> Mostrar Documento
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($agenda["req_prod_adjcomments_{$x}"])): ?>
                                <span style="margin-left:8px;color:#64748B;font-size:11.5px"><?=htmlspecialchars((string)$agenda["req_prod_adjcomments_{$x}"])?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php renderInternalFabricationNoteRow($agenda, 2, 2); ?>
                </table>
            </div>
        </div>



    <?php elseif ($isEmbalaje): ?>
        <!-- ============================================================== -->
        <!-- RAMA D1: EMBALAJE (editxid.php líneas 1880 - 2050)             -->
        <!-- ============================================================== -->
        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_cliente', 'icon_info_cliente')">
                <span><i class="fa fa-fw fa-user"></i> Información Cliente</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_cliente">Minimizar</span>
                    <i id="icon_info_cliente" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_cliente">
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="180">
                    <col width="32%">
                    <col width="160">
                    <col>
                </colgroup>
                <tr>
                    <td class="tdleft">N° O.T.</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)$agenda['prd_number'])?></b></td>
                    <td class="tdleft">Cliente</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                </tr>
                <tr>
                    <td class="tdleft">N° C.C.</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['req_number'])?></td>
                    <td class="tdleft">Producto</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_title'])?></td>
                </tr>
                <tr>
                    <td class="tdleft">Medidas</td>
                    <td class="tdnrm"><?=(int)$agenda['fab_med_width']?> x <?=(int)$agenda['fab_med_height']?> x <?=(int)$agenda['fab_med_fuelle']?> cm</td>
                    <td class="tdleft">Cantidad Bolsas</td>
                    <td class="tdnrm"><b><?=$fmt($agenda['ag_amount'])?></b></td>
                </tr>
                <?=renderDesignAndAttachmentsHtml($agenda, (int)$agenda['fab_med_width'] . ' x ' . (int)$agenda['fab_med_height'] . ' x ' . (int)$agenda['fab_med_fuelle'] . ' cm', (string)($agenda['fab_design_name'] ?? $agenda['item_title'] ?? ''), 1, 3, 'Imagen Diseño (lo que se está embalando)')?>
                </table>
            </div>
        </div>

        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_fab', 'icon_info_fab')">
                <span><i class="fa fa-fw fa-info-circle"></i> Información de Fabricación</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_fab">Minimizar</span>
                    <i id="icon_info_fab" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_fab">
                <table border="0" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="left" valign="top" width="50%" style="padding-right:10px">
                        <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-right:1px solid #DDDDDD">
                        <colgroup><col width="160"><col></colgroup>
                        <tr><td class="tdleft">N° O.T.</td><td class="tdnrm"><?=htmlspecialchars((string)$agenda['prd_number'])?></td></tr>
                        <tr><td class="tdleft">Cliente</td><td class="tdnrm"><?=htmlspecialchars((string)$agenda['cust_name'])?></td></tr>
                        <tr><td class="tdleft">N° C.C.</td><td class="tdnrm"><?=htmlspecialchars((string)$agenda['req_number'])?></td></tr>
                        <tr><td class="tdleft">Tipo de Material</td><td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td></tr>
                        <tr><td class="tdleft">Tipo de Bolsa</td><td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_title'])?></td></tr>
                        <tr><td class="tdleft">Medidas</td><td class="tdnrm"><?=(int)$agenda['fab_med_width']?>x<?=(int)$agenda['fab_med_height']?>x<?=(int)$agenda['fab_med_fuelle']?></td></tr>
                        <tr><td class="tdleft">Procedencia</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_infoaddprd_procedencia'] ?? '-'))?></td></tr>
                        <tr><td class="tdleft">Tipo Impresión</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_printtype'] ?? '-'))?></td></tr>
                        <tr><td class="tdleft">Colores</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? ''))?></td></tr>
                        <tr><td class="tdleft">Color Manillas</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['manilla_color'] ?? '-'))?></td></tr>
                        <tr><td class="tdleft">Medida Manillas</td><td class="tdnrm"><?=(int)($agenda['fab_manilla_length'] ?? 0)?></td></tr>
                        <tr><td class="tdleft">Reversa</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_infoaddprd_reversa_act'] ?? 'No'))?></td></tr>
                        </table>
                    </td>
                    <td align="left" valign="top" width="50%" style="padding-left:10px">
                        <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD">
                        <colgroup><col width="170"><col></colgroup>
                        <tr>
                            <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="2" align="center">
                                <i class="fa fa-fw fa-cube"></i> Datos Embalaje
                            </td>
                        </tr>
                        <tr><td class="tdleft">Mezcla</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['reqnum_mezcla_1'] ?? 'Sin Mezcla'))?></td></tr>
                        <tr><td class="tdleft">Medida de Caja</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_embalaje_medidas_caja'] ?? '-'))?></td></tr>
                        <tr><td class="tdleft">Logotipo empresa</td><td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_caja_impresa'] ?? '-'))?></td></tr>
                        <tr><td class="tdleft">Cant. Cajas por Pallet</td><td class="tdnrm"><?=$fmt($agenda['req_embalaje_cajas_por_pallet_amt'])?></td></tr>
                        <tr><td class="tdleft">Cant. Cajas a Utilizar</td><td class="tdnrm"><?=$fmt($agenda['req_embalaje_cajas_completas_amt'])?></td></tr>
                        <tr><td class="tdleft">Unidades por caja</td><td class="tdnrm"><b><?=$fmt($agenda['req_embalaje_bolsas_por_caja_amt'])?></b></td></tr>
                        <tr><td class="tdleft">Unidades Caja Final</td><td class="tdnrm"><?=$fmt($agenda['req_embalaje_caja_final'])?></td></tr>
                        <tr><td class="tdleft">Total Pedido</td><td class="tdnrm"><b><?=$fmt($agenda['ag_amount'])?> bolsas</b></td></tr>
                        </table>
                    </td>
                </tr>
                </table>
                <?php renderMachineFabricationExtras($agenda, false); ?>
            </div>
        </div>


    <?php endif; ?>
    <?php

