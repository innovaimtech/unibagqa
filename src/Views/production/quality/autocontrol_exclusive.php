<?php
    ?>
        <?php if (!$hasAlis): ?>
            <a href="/production/work-orders/operate?agid=<?=$agId?>" class="btngrey" style="display:inline-block;margin-bottom:12px;text-decoration:none">Volver al alistamiento</a>
        <?php endif; ?>
        <!-- ============================================================== -->
        <!-- PANTALLA EXCLUSIVA DE AUTOCONTROL (autocontrol.form.php)       -->
        <!-- Se muestra únicamente cuando el alistamiento ha terminado y    -->
        <!-- la partida aún no ha sido aprobada por el supervisor.         -->
        <!-- ============================================================== -->
        <div style="font-size:20px;margin-bottom:10px"><b>En curso</b></div>

        <?php if (isset($_GET['alis_finished'])): ?>
            <div style="background:#E8F5E9;color:#2E7D32;padding:12px 18px;border-radius:4px;border:1px solid #A5D6A7;margin-bottom:12px;font-size:13px">
                <i class="fa fa-check-circle"></i> <b>Alistamiento y confirmaciones de máquina completados.</b> Complete el autocontrol de calidad a continuación para solicitar la aprobación de partida del supervisor.
            </div>
        <?php elseif (isset($_GET['supervisor_notified'])): ?>
            <div style="background:#E8F5E9;color:#2E7D32;padding:12px 18px;border-radius:4px;border:1px solid #A5D6A7;margin-bottom:12px;font-size:13px">
                <i class="fa fa-check-circle"></i> <b>Se ha notificado al Supervisor de Planta.</b> El supervisor debe ingresar sus credenciales para aprobar la partida y habilitar la producción.
            </div>
        <?php elseif (isset($_GET['lider_notified'])): ?>
            <div style="background:#E8F5E9;color:#2E7D32;padding:12px 18px;border-radius:4px;border:1px solid #A5D6A7;margin-bottom:12px;font-size:13px">
                <i class="fa fa-check-circle"></i> <b>Se ha informado al Líder de Área.</b> El líder de área debe revisar y aprobar la lista de autocontrol.
            </div>
        <?php elseif (isset($_GET['lider_approved'])): ?>
            <div style="background:#E8F5E9;color:#2E7D32;padding:12px 18px;border-radius:4px;border:1px solid #A5D6A7;margin-bottom:12px;font-size:13px">
                <i class="fa fa-check-circle"></i> <b>Revisión del Líder de Área aprobada con éxito.</b> Ahora el Supervisor de Planta debe validar y aprobar la partida para habilitar la producción.
            </div>
        <?php elseif (isset($_GET['error'])): ?>
            <div style="background:#FFEBEE;color:#C62828;padding:12px 18px;border-radius:4px;border:1px solid #EF9A9A;margin-bottom:12px;font-size:13px">
                <i class="fa fa-exclamation-circle"></i> <?=htmlspecialchars((string)$_GET['error'])?>
            </div>
        <?php endif; ?>

        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px">
        <tr>
            <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td align="left">
                        <i class="fa fa-fw fa-laptop-code" style="font-size:14px;"></i> Ordenes de trabajo
                        <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                        <b>En curso</b>: OT #<?=htmlspecialchars((string)$agenda['prd_number'])?> | Máquina: <b><?=htmlspecialchars((string)($machineInit['equipo_name'] ?? '-'))?></b>
                    </td>
                    <td align="right">
                        <div class="btngreen" style="display:inline-block;cursor:pointer;padding:6px 14px;font-size:13px;font-weight:bold;border-radius:4px" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=terminarot'">
                            <i class="fa fa-fw fa-check" style="color:white;"></i> Terminar OT
                        </div>
                    </td>
                </tr>
                </table>
            </td>
        </tr>
        </table>

        <style>
        .unibag-collapse-header {
            background-color: #165552;
            color: #FFFFFF;
            padding: 8px 14px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
            font-weight: bold;
            font-size: 13px;
            transition: background-color 0.15s ease;
        }
        .unibag-collapse-header:hover {
            background-color: #124341;
        }
        .unibag-collapse-btn {
            font-size: 11px;
            font-weight: normal;
            opacity: 0.9;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.15);
            padding: 3px 8px;
            border-radius: 3px;
        }
        .unibag-collapse-header:hover .unibag-collapse-btn {
            background: rgba(255,255,255,0.25);
        }
        </style>
        <script>
        function toggleCollapseSection(secId, iconId) {
            var sec = document.getElementById(secId);
            var icon = document.getElementById(iconId);
            var lblId = iconId.replace('icon_', 'lbl_');
            var lbl = document.getElementById(lblId);
            if (!sec) return;

            var isHidden = (sec.style.display === 'none');
            if (isHidden) {
                sec.style.display = '';
                if (icon) icon.className = 'fa fa-chevron-up';
                if (lbl) lbl.textContent = 'Minimizar';
                try { localStorage.setItem('unibag_collapse_' + secId, '0'); } catch(e){}
            } else {
                sec.style.display = 'none';
                if (icon) icon.className = 'fa fa-chevron-down';
                if (lbl) lbl.textContent = 'Expandir';
                try { localStorage.setItem('unibag_collapse_' + secId, '1'); } catch(e){}
            }
        }
        </script>

        <?php
        $acImgHash = trim((string)($agenda['fab_design_imagehash'] ?? $agenda['item_img_hash'] ?? ''));
        $acHasImg = ($acImgHash !== '' && $acImgHash !== 'dummy');
        $acImgUrl = $acHasImg ? ('/production/design-image?folder=order&hash=' . rawurlencode($acImgHash)) : '';
        $acProdSku = trim((string)($agenda['item_number_prod'] ?? ''));
        $acProdTitle = trim((string)($agenda['item_title'] ?? ''));
        $acDesignName = trim((string)($agenda['fab_design_name'] ?? ''));
        $acFullDesign = ($acProdSku !== '' && $acProdTitle !== '') ? "{$acProdSku} | {$acProdTitle}" : ($acProdTitle ?: ($acProdSku ?: ($acDesignName ?: '-')));
        $acW = (int)($agenda['fab_med_width'] ?? 0);
        $acH = (int)($agenda['fab_med_height'] ?? 0);
        $acFuelle = (int)($agenda['fab_med_fuelle'] ?? 0);
        $acMedidasStr = $acW > 0 ? ("{$acW} x {$acH}" . ($acFuelle > 0 ? " + {$acFuelle}" : "") . " cm") : '-';
        $acTelaWidth = (float)($agenda['req_operador_tela_width'] ?? 0);
        $acSeriMermaPerc = (float)($agenda['req_operador_mermaperc'] ?? 0);
        $acSeriDevPrints = max(1, (int)($agenda['req_solic_devprints_cc'] ?? 1));
        $acSeriMlinProg = round((float)$agenda['ag_amount'] * (float)$corte_m2);
        $acSeriMlinAddi = round($acSeriMlinProg / 100 * $acSeriMermaPerc);
        $acSeriMlinPrint = round($acSeriMlinProg + $acSeriMlinAddi);
        $acSeriMermaUnits = round((float)$agenda['ag_amount'] / 100 * $acSeriMermaPerc);
        $acSeriRealUnits = (float)$agenda['ag_amount'] + $acSeriMermaUnits;
        $acSeriPasadasPrint = (int)round($acSeriRealUnits / $acSeriDevPrints);
        $acSeriKgsProcess = round(
            $acSeriMlinPrint
            * (float)($agenda['req_operador_tela_width'] ?? 0)
            / 100
            * (float)($agenda['fab_mat_gramms'] ?? 0)
            / 1000
        );
        ?>

        <?php if ($isSeriPlana): ?>
            <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
                <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_autoctrl_info_cliente', 'icon_autoctrl_info_cliente')">
                    <span><i class="fa fa-fw fa-user"></i> Información Cliente</span>
                    <span class="unibag-collapse-btn">
                        <span id="lbl_autoctrl_info_cliente">Minimizar</span>
                        <i id="icon_autoctrl_info_cliente" class="fa fa-chevron-up"></i>
                    </span>
                </div>
                <div id="sec_autoctrl_info_cliente">
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
                            <?php if ($acHasImg): ?>
                                <div style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap">
                                    <a href="<?=$acImgUrl?>" target="_blank" onclick="openDesignModal('<?=$acImgUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($acFullDesign))?>', '<?=htmlspecialchars($acMedidasStr)?>'); return false;" title="Clic para ampliar diseño">
                                        <img src="<?=$acImgUrl?>" alt="Foto Diseño" style="max-height:65px;max-width:105px;object-fit:contain;border:1px solid #CBD5E1;border-radius:4px;background:#fff;vertical-align:middle;box-shadow:0 1px 3px rgba(0,0,0,0.1);cursor:pointer">
                                    </a>
                                    <a href="<?=$acImgUrl?>" target="_blank" onclick="openDesignModal('<?=$acImgUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($acFullDesign))?>', '<?=htmlspecialchars($acMedidasStr)?>'); return false;" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
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
                                        <a href="<?=$adjUrl?>" target="_blank" onclick="openDesignModal('<?=$adjUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($acMedidasStr)?>'); return false;" title="Clic para ampliar adjunto">
                                            <img src="<?=$adjUrl?>" alt="Adjunto #<?=($x + 1)?>" style="max-height:55px;max-width:85px;object-fit:contain;border:1px solid #CBD5E1;border-radius:4px;background:#fff;vertical-align:middle;box-shadow:0 1px 3px rgba(0,0,0,0.1);cursor:pointer">
                                        </a>
                                        <a href="<?=$adjUrl?>" target="_blank" onclick="openDesignModal('<?=$adjUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($acMedidasStr)?>'); return false;" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
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

            <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
                <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_autoctrl_info_fab', 'icon_autoctrl_info_fab')">
                    <span><i class="fa fa-fw fa-info-circle"></i> Información de Fabricación</span>
                    <span class="unibag-collapse-btn">
                        <span id="lbl_autoctrl_info_fab">Minimizar</span>
                        <i id="icon_autoctrl_info_fab" class="fa fa-chevron-up"></i>
                    </span>
                </div>
                <div id="sec_autoctrl_info_fab">
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
                        <td class="tdnrm"><?=$fmt($acSeriMlinPrint)?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Color Tela</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fabric_color'] ?? '-'))?></td>
                        <td class="tdleft">Contador impresora</td>
                        <td class="tdnrm"><?=$fmt($acSeriPasadasPrint)?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Ancho Tela</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_operador_tela_width'] ?? '-'))?></td>
                        <td class="tdleft">Kg a imprimir</td>
                        <td class="tdnrm"><?=$fmt($acSeriKgsProcess)?></td>
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
                                <select class="inptxt js-bastidor-select" id="idx_bastidor_amt" name="bastidoramt" style="width:120px;font-weight:bold" onchange="var fm = document.getElementById('idx_seri_bastidor_multiplier'); if (fm) { fm.value = this.value; if (typeof calcSeriUnitsFromCounters === 'function') calcSeriUnitsFromCounters(); }">
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
                            <?php if ($acHasImg): ?>
                                <div style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap">
                                    <a href="<?=$acImgUrl?>" target="_blank" onclick="openDesignModal('<?=$acImgUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($acFullDesign))?>', '<?=htmlspecialchars($acMedidasStr)?>'); return false;" title="Clic para ampliar diseño">
                                        <img src="<?=$acImgUrl?>" alt="Foto Diseño" style="max-height:65px;max-width:105px;object-fit:contain;border:1px solid #CBD5E1;border-radius:4px;background:#fff;vertical-align:middle;box-shadow:0 1px 3px rgba(0,0,0,0.1);cursor:pointer">
                                    </a>
                                    <a href="<?=$acImgUrl?>" target="_blank" onclick="openDesignModal('<?=$acImgUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($acFullDesign))?>', '<?=htmlspecialchars($acMedidasStr)?>'); return false;" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
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
                                        <a href="<?=$adjUrl?>" target="_blank" onclick="openDesignModal('<?=$adjUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($acMedidasStr)?>'); return false;" title="Clic para ampliar adjunto">
                                            <img src="<?=$adjUrl?>" alt="Adjunto #<?=($x + 1)?>" style="max-height:55px;max-width:85px;object-fit:contain;border:1px solid #CBD5E1;border-radius:4px;background:#fff;vertical-align:middle;box-shadow:0 1px 3px rgba(0,0,0,0.1);cursor:pointer">
                                        </a>
                                        <a href="<?=$adjUrl?>" target="_blank" onclick="openDesignModal('<?=$adjUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($acMedidasStr)?>'); return false;" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
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
        <?php elseif ($isFlexo): ?>
            <!-- Tarjeta 1: Información Cliente y Diseño para Autocontrol -->
            <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
                <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_autoctrl_info_cliente', 'icon_autoctrl_info_cliente')">
                    <span><i class="fa fa-fw fa-user"></i> 1. Información Cliente y Diseño</span>
                    <span class="unibag-collapse-btn">
                        <span id="lbl_autoctrl_info_cliente">Minimizar</span>
                        <i id="icon_autoctrl_info_cliente" class="fa fa-chevron-up"></i>
                    </span>
                </div>
                <div id="sec_autoctrl_info_cliente" style="padding:15px">
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <colgroup>
                        <col width="180">
                        <col width="32%">
                        <col width="160">
                        <col>
                    </colgroup>
                    <tr>
                        <td class="tdleft">N° OT</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$agenda['prd_number'])?></b></td>
                        <td class="tdleft">Color N° 1</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Cliente</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$agenda['cust_name'])?></b></td>
                        <td class="tdleft">Color N° 2</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_2'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">N° CC</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$agenda['req_number'])?></b></td>
                        <td class="tdleft">Color N° 3</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_3'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Diseño</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_design_name'] !== '' ? $agenda['fab_design_name'] : $agenda['item_title']))?></td>
                        <td class="tdleft">Color N° 4</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_4'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Producto</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_number_prod'])?> | <?=htmlspecialchars((string)$agenda['item_title'])?></td>
                        <td class="tdleft">Color N° 5</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_5'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Materialidad</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                        <td class="tdleft">Color N° 6</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_6'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Medidas (Ancho x Alto)</td>
                        <td class="tdnrm"><b><?=(int)$agenda['fab_med_width']?> x <?=(int)$agenda['fab_med_height']?> cm</b></td>
                        <td class="tdleft">Colores Resumen</td>
                        <td class="tdnrm"><?=htmlspecialchars($printcolors ?: '-')?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Fuelle</td>
                        <td class="tdnrm"><?=(int)$agenda['fab_med_fuelle']?> cm</td>
                        <td class="tdleft">Fecha de Entrega</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($agenda['req_despacho_desc'] !== '' ? $agenda['req_despacho_desc'] : $agenda['ag_date']))?></b></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Cantidad de Bolsas</td>
                        <td class="tdnrm"><b><?=$fmt($agenda['ag_amount'])?></b></td>
                        <td class="tdleft">Fecha solicitud cliché</td>
                        <td class="tdnrm"><?=(int)($agenda['req_cliche_peli_solic_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_solic_dat']) : '-'?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Color Tela</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fabric_color'] ?? '-'))?></td>
                        <td class="tdleft">Fecha recepción cliché</td>
                        <td class="tdnrm"><?=(int)($agenda['req_cliche_peli_recep_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_recep_dat']) : '-'?></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="background:#EDF0F6;padding:4px"></td>
                    </tr>
                    <tr>
                        <td class="tdleft" valign="middle">Imagen Diseño</td>
                        <td class="tdnrm" colspan="3" valign="middle">
                            <?php if ($acHasImg): ?>
                                <div style="display:inline-flex;align-items:center;gap:14px;flex-wrap:wrap">
                                    <div style="cursor:pointer;display:inline-flex;align-items:center;gap:12px;background:#F8FAFC;border:2px solid #0284C7;padding:8px 14px;border-radius:6px;box-shadow:0 2px 6px rgba(2,132,199,0.15)"
                                         onclick="openDesignModal('<?=htmlspecialchars($acImgUrl)?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($acFullDesign))?>', '<?=htmlspecialchars($acMedidasStr)?>')"
                                         title="Clic para ver foto del diseño en ventana flotante">
                                        <img src="<?=htmlspecialchars($acImgUrl)?>" alt="Foto Diseño" style="height:70px;max-width:120px;object-fit:contain;border:1px solid #CBD5E1;border-radius:4px;background:#fff">
                                        <div>
                                            <div style="font-size:13px;font-weight:700;color:#0284C7"><i class="fa fa-search-plus"></i> Abrir en Ventana Flotante</div>
                                            <div style="font-size:11px;color:#64748B;margin-top:2px">Clic aquí para ampliar y revisar diseño</div>
                                        </div>
                                    </div>
                                    <a href="<?=$acImgUrl?>" target="_blank" class="btngrey" style="padding:6px 12px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px">
                                        <i class="fa fa-fw fa-external-link-alt" style="color:white;"></i> Pestaña nueva
                                    </a>
                                </div>
                            <?php else: ?>
                                <span style="color:#999;font-style:italic">Sin imagen de diseño cargada</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php for ($x = 0; $x < 5; $x++): ?>
                        <?php if (!empty($agenda["req_prod_adjfile_{$x}"])):
                            $adjFile = (string)$agenda["req_prod_adjfile_{$x}"];
                            $isImg = (bool)preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $adjFile);
                            $adjUrl = "/docs/prod/" . rawurlencode($adjFile);
                        ?>
                            <tr>
                                <td class="tdleft">Imagen Adjunta #<?=($x + 1)?></td>
                                <td class="tdnrm" colspan="3">
                                    <div style="display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap">
                                        <?php if ($isImg): ?>
                                            <img src="<?=$adjUrl?>" alt="Adjunto #<?=($x + 1)?>" style="height:48px;max-width:80px;object-fit:contain;border:1px solid #cbd5e1;border-radius:4px;background:#fff;cursor:pointer"
                                                 onclick="openDesignModal('<?=$adjUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($acMedidasStr)?>')"
                                                 title="Clic para ampliar en ventana flotante">
                                        <?php endif; ?>
                                        <button type="button" class="btngrey" style="padding:4px 10px;font-size:12px;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:5px"
                                                onclick="openDesignModal('<?=$adjUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($acMedidasStr)?>')">
                                            <i class="fa fa-fw fa-<?= $isImg ? 'image' : 'file' ?>" style="color:white;"></i> <?= $isImg ? 'Ver Imagen #' . ($x + 1) : 'Descargar Adjunto #' . ($x + 1) ?>
                                        </button>
                                        <?php if (!empty($agenda["req_prod_adjcomments_{$x}"])): ?>
                                            <span style="margin-left:6px;color:#475569;font-size:11.5px"><?=htmlspecialchars((string)$agenda["req_prod_adjcomments_{$x}"])?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endfor; ?>
                    </table>
                </div>
            </div>

            <!-- Tarjeta 2: Información de Fabricación para Autocontrol -->
            <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
                <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_autoctrl_info_fab', 'icon_autoctrl_info_fab')">
                    <span><i class="fa fa-fw fa-cogs"></i> 2. Información de Fabricación</span>
                    <span class="unibag-collapse-btn">
                        <span id="lbl_autoctrl_info_fab">Minimizar</span>
                        <i id="icon_autoctrl_info_fab" class="fa fa-chevron-up"></i>
                    </span>
                </div>
                <div id="sec_autoctrl_info_fab" style="padding:15px">
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <colgroup>
                        <col width="180">
                        <col width="32%">
                        <col width="160">
                        <col>
                    </colgroup>
                    <tr>
                        <td class="tdleft">Rodillo a utilizar</td>
                        <td class="tdnrm"><b style="color:#0284C7;font-size:14px">Z = <?=$fmt($corte_z, 0)?></b></td>
                        <td class="tdleft">Unidad N° 1</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Corte de bolsa</td>
                        <td class="tdnrm"><?=$fmt($corte_m2, 4)?> mtrs</td>
                        <td class="tdleft">Unidad N° 2</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_2'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Ubicación Clisé</td>
                        <td class="tdnrm"><b style="color:#0F766E"><?=htmlspecialchars((string)($agenda['req_infoaddprd_cliche_ubicacion'] ?? '-'))?></b></td>
                        <td class="tdleft">Unidad N° 3</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_3'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Código Cliché</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($agenda['req_infoaddprd_cliche_codigo'] ?? '-'))?></b></td>
                        <td class="tdleft">Unidad N° 4</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_4'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Pie de Imprenta / Máquina</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($agenda['req_pie_imprenta'] ?? '-'))?></b></td>
                        <td class="tdleft">Unidad N° 5</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_5'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">N° Código de Barra</td>
                        <td class="tdnrm"><b style="font-family:monospace;font-size:13px"><?=htmlspecialchars((string)($agenda['item_sellprice_barcodenumber'] ?? '-'))?></b></td>
                        <td class="tdleft">Unidad N° 6</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_6'] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Ancho Bobina / Tela</td>
                        <td class="tdnrm"><b><?= $acTelaWidth > 0 ? $acTelaWidth . ' cm' : '-' ?></b></td>
                        <td class="tdleft">Unidad N° 1</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($aniloxDescMap[1] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Impresiones por desarrollo</td>
                        <td class="tdnrm"><?=(int)($agenda['req_solic_devprints_cc'] ?? 0)?> impresiones</td>
                        <td class="tdleft">Unidad N° 2</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($aniloxDescMap[2] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">% de merma</td>
                        <td class="tdnrm"><?=$fmt($agenda['req_operador_mermaperc'], 2)?> %</td>
                        <td class="tdleft">Unidad N° 3</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($aniloxDescMap[3] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Metros a imprimir</td>
                        <td class="tdnrm"><b><?=$fmt($metrosAImprimir, 2)?> mtrs</b></td>
                        <td class="tdleft">Unidad N° 4</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($aniloxDescMap[4] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Color Tela</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($agenda['fabric_color'] ?? '-'))?></b></td>
                        <td class="tdleft">Unidad N° 5</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($aniloxDescMap[5] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Saldo total</td>
                        <td class="tdnrm"><b><?=$fmt($stats['saldo_total'] ?? 0)?> unidades</b></td>
                        <td class="tdleft">Unidad N° 6</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($aniloxDescMap[6] ?? '-'))?></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="background:#EDF0F6;padding:4px"></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Materialidad</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                        <td class="tdleft">Metros a imprimir</td>
                        <td class="tdnrm"><b><?=$fmt($metrosAImprimir, 2)?> mtrs</b></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Color Tela</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fabric_color'] ?? '-'))?></td>
                        <td class="tdleft">Contador impresora</td>
                        <td class="tdnrm"><b><?=$fmt(round($metrosAImprimir / 0.41))?></b></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Ancho Tela</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_operador_tela_width'] ?? '-'))?> cm</td>
                        <td class="tdleft">Kg a imprimir</td>
                        <td class="tdnrm"><b><?=$fmt($kgAImprimir, 2)?> kgs</b></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Gramaje</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_mat_gramms'] ?? '70'))?> gr</td>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm">&nbsp;</td>
                    </tr>
                    <?php renderInternalFabricationNoteRow($agenda); ?>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <?php renderMachineAutocontrolFabrication($agenda, $machineInit, $transcomInfos, $corte_m2); ?>
        <?php endif; ?>

        <!-- Tarjeta 2: AUTOCONTROL / APROBACIÓN LÍDER DE ÁREA O SUPERVISOR -->
        <?php if ($showLiderApproval): ?>
            <!-- APROBACIÓN REVISIÓN POR LÍDER DE ÁREA (autocontrol.form.php) -->
            <?php
            $pts = !empty($autoctrlStatus['points']) ? $autoctrlStatus['points'] : $autocontrolPoints;
            ?>
            <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px">
            <tr>
                <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <colgroup>
                        <col width="25">
                        <col width="25%">
                        <col width="25">
                        <col width="25%">
                    </colgroup>
                    <tr>
                        <td class="tdleft" colspan="4" style="background-color:#FFFF00;border:0px;border-radius:4px;color:black;font-weight:bold;text-align:center" align="center">AUTOCONTROL</td>
                    </tr>
                    <tr>
                    <?php
                    $px = 0;
                    foreach ($pts as $p):
                    ?>
                        <td class="tdnrm">
                            <input type="checkbox" value="1" checked disabled>
                            <?=htmlspecialchars((string)$p['ac_name'])?>
                        </td>
                        <?php
                        $px++;
                        if ($px >= 4):
                            $px = 0;
                            echo '</tr><tr>';
                        endif;
                    endforeach;
                    ?>
                    </tr>
                    </table>
                </td>
            </tr>
            </table>

            <form action="/production/work-orders/autocontrol/supervisor" method="post" name="xform_autoctrl_lider" id="xform_autoctrl_lider">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <input type="hidden" name="mode" value="liderautoctrlsave">
                <input type="hidden" name="submode" value="lidersave">

                <table border="0" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
                        <table border="0" width="100%" cellpadding="6" cellspacing="0">
                        <colgroup>
                            <col width="25">
                            <col width="25%">
                            <col width="25">
                            <col width="25%">
                        </colgroup>
                        <tr>
                            <td class="tdleft" colspan="4" style="background-color:#FF5733;border:0px;border-radius:4px;color:white;font-weight:bold;text-align:center" align="center">
                                APROBACIÓN LÍDER DE ÁREA
                                <span style="float:left;font-weight:normal">
                                    <label style="cursor:pointer;color:white">
                                        <input type="checkbox" name="dummy_lideraprob_markall" id="dummy_lideraprob_markall" value="1"
                                               onclick="document.querySelectorAll('.lideraprobchk').forEach(c=>c.checked=this.checked); checkLiderAprob();">
                                        Marcar todos
                                    </label>
                                </span>
                            </td>
                        </tr>
                        <tr>
                        <?php
                        $px = 0;
                        foreach ($pts as $p):
                            $acid = (int)$p['id'];
                        ?>
                            <td class="tdnrm">
                                <input type="hidden" name="liderbaseacids[]" value="<?=$acid?>">
                                <input type="checkbox" value="1" name="lideracids_<?=$acid?>" class="lideraprobchk clsautocontrolareachks"
                                       id="lider_acid_<?=$acid?>"
                                       onclick="checkLiderAprob()">
                                <label for="lider_acid_<?=$acid?>" style="cursor:pointer"><?=htmlspecialchars((string)$p['ac_name'])?></label>
                            </td>
                            <?php
                            $px++;
                            if ($px >= 4):
                                $px = 0;
                                echo '</tr><tr>';
                            endif;
                        endforeach;
                        ?>
                        </tr>
                        </table>
                    </td>
                </tr>
                </table>

                <div style="clear:both;height:12px"></div>
                <table border="0" width="100%" cellpadding="0" cellspacing="0" id="idx_btnlideraprob" style="display:none">
                <tr>
                    <td width="210">
                        <input type="text" class="inptxt" name="axx_username" id="axx_lider_username" placeholder="Usuario Líder de Área" style="width:200px" autocomplete="off">
                    </td>
                    <td width="210">
                        <input type="password" class="inptxt" name="axx_pass" id="axx_lider_pass" placeholder="Contraseña Líder de Área" style="width:200px" autocomplete="off">
                    </td>
                    <td>
                        <button type="submit" class="btngreen" style="padding:8px 22px;border:none;cursor:pointer;font-size:14px;background-color:#FF5733;color:white">
                            <i class="fa fa-fw fa-save" style="color:white;"></i> Aprobar Revisión&nbsp;
                        </button>
                    </td>
                </tr>
                </table>
            </form>

            <script>
            function checkLiderAprob() {
                var canaprob = true;
                var chks = document.querySelectorAll('.lideraprobchk');
                if (chks.length === 0) canaprob = false;
                chks.forEach(function(c) {
                    if (!c.checked) canaprob = false;
                });
                var box = document.getElementById('idx_btnlideraprob');
                if (box) {
                    if (canaprob) {
                        box.style.display = '';
                        var u = document.getElementById('axx_lider_username');
                        if (u && !u.value) u.focus();
                    } else {
                        box.style.display = 'none';
                    }
                }
            }
            checkLiderAprob();
            </script>

        <?php elseif ($showSupervisorApproval): ?>
            <!-- FOTO 3: APROBACIÓN PARTIDA POR SUPERVISOR (autocontrol.form.php) -->
            <?php
            $pts = !empty($autoctrlStatus['points']) ? $autoctrlStatus['points'] : $autocontrolPoints;
            ?>
            <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px">
            <tr>
                <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <colgroup>
                        <col width="25">
                        <col width="25%">
                        <col width="25">
                        <col width="25%">
                    </colgroup>
                    <tr>
                        <td class="tdleft" colspan="4" style="background-color:#FFFF00;border:0px;border-radius:4px;color:black;font-weight:bold;text-align:center" align="center">AUTOCONTROL</td>
                    </tr>
                    <tr>
                    <?php
                    $px = 0;
                    foreach ($pts as $p):
                    ?>
                        <td class="tdnrm">
                            <input type="checkbox" value="1" checked disabled>
                            <?=htmlspecialchars((string)$p['ac_name'])?>
                        </td>
                        <?php
                        $px++;
                        if ($px >= 4):
                            $px = 0;
                            echo '</tr><tr>';
                        endif;
                    endforeach;
                    ?>
                    </tr>
                    </table>
                </td>
            </tr>
            </table>

            <?php if (($equipoTypeId === 7 || $isFlexo) && (!empty($autoctrlStatus['lider_inputs']) || !empty($autoctrlStatus['lider_approved']))): ?>
            <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px">
            <tr>
                <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <colgroup>
                        <col width="25">
                        <col width="25%">
                        <col width="25">
                        <col width="25%">
                    </colgroup>
                    <tr>
                        <td class="tdleft" colspan="4" style="background-color:#FF5733;border:0px;border-radius:4px;color:white;font-weight:bold;text-align:center" align="center">
                            <i class="fa fa-check"></i> APROBACIÓN LÍDER DE ÁREA (APROBADO)
                        </td>
                    </tr>
                    <tr>
                    <?php
                    $px = 0;
                    foreach ($pts as $p):
                    ?>
                        <td class="tdnrm">
                            <input type="checkbox" value="1" checked disabled>
                            <?=htmlspecialchars((string)$p['ac_name'])?>
                        </td>
                        <?php
                        $px++;
                        if ($px >= 4):
                            $px = 0;
                            echo '</tr><tr>';
                        endif;
                    endforeach;
                    ?>
                    </tr>
                    </table>
                </td>
            </tr>
            </table>
            <?php endif; ?>

            <form action="/production/work-orders/autocontrol/supervisor" method="post" name="xform_autoctrl_super" id="xform_autoctrl_super">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <input type="hidden" name="mode" value="superautoctrlsave">
                <input type="hidden" name="submode" value="supersave">

                <table border="0" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
                        <table border="0" width="100%" cellpadding="6" cellspacing="0">
                        <colgroup>
                            <col width="25">
                            <col width="25%">
                            <col width="25">
                            <col width="25%">
                        </colgroup>
                        <tr>
                            <td class="tdleft" colspan="4" style="background-color:#92D050;border:0px;border-radius:4px;color:black;font-weight:bold;text-align:center" align="center">
                                APROBACIÓN PARTIDA
                                <span style="float:left;font-weight:normal">
                                    <label style="cursor:pointer">
                                        <input type="checkbox" name="dummy_aprobpart_markall" id="dummy_aprobpart_markall" value="1"
                                               onclick="document.querySelectorAll('.superaprobchk').forEach(c=>c.checked=this.checked); checkSuperAprob();">
                                        Marcar todos
                                    </label>
                                </span>
                            </td>
                        </tr>
                        <tr>
                        <?php
                        $px = 0;
                        foreach ($pts as $p):
                            $acid = (int)$p['id'];
                        ?>
                            <td class="tdnrm">
                                <input type="hidden" name="superbaseacids[]" value="<?=$acid?>">
                                <input type="checkbox" value="1" name="superacids_<?=$acid?>" class="superaprobchk clsaprobpartchks"
                                       id="super_acid_<?=$acid?>"
                                       onclick="checkSuperAprob()">
                                <label for="super_acid_<?=$acid?>" style="cursor:pointer"><?=htmlspecialchars((string)$p['ac_name'])?></label>
                            </td>
                            <?php
                            $px++;
                            if ($px >= 4):
                                $px = 0;
                                echo '</tr><tr>';
                            endif;
                        endforeach;
                        ?>
                        </tr>
                        </table>
                    </td>
                </tr>
                </table>

                <div style="clear:both;height:12px"></div>
                <table border="0" width="100%" cellpadding="0" cellspacing="0" id="idx_btnsuperaprob" style="display:none">
                <tr>
                    <td width="210">
                        <input type="text" class="inptxt" name="axx_username" id="axx_username" placeholder="Usuario Supervisor" style="width:200px" autocomplete="off">
                    </td>
                    <td width="210">
                        <input type="password" class="inptxt" name="axx_pass" id="axx_pass" placeholder="Contraseña Supervisor" style="width:200px" autocomplete="off">
                    </td>
                    <td>
                        <button type="submit" class="btngreen" style="padding:8px 22px;border:none;cursor:pointer;font-size:14px">
                            <i class="fa fa-fw fa-save" style="color:white;"></i> Aprobar partida&nbsp;
                        </button>
                    </td>
                </tr>
                </table>
            </form>

            <script>
            function checkSuperAprob() {
                var canaprob = true;
                var chks = document.querySelectorAll('.superaprobchk');
                if (chks.length === 0) canaprob = false;
                chks.forEach(function(c) {
                    if (!c.checked) canaprob = false;
                });
                var box = document.getElementById('idx_btnsuperaprob');
                if (box) {
                    if (canaprob) {
                        box.style.display = '';
                        var u = document.getElementById('axx_username');
                        if (u && !u.value) u.focus();
                    } else {
                        box.style.display = 'none';
                    }
                }
            }
            checkSuperAprob();
            </script>

        <?php else: ?>
            <!-- FOTO 2: AUTOCONTROL OPERADOR (autocontrol.form.php) -->
            <form action="/production/work-orders/autocontrol" method="post" name="xform_autoctrl" id="xform_autoctrl">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <input type="hidden" name="mode" value="autoctrlsave">
                <input type="hidden" name="submode" id="autoctrl_submode" value="close">

                <table border="0" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
                        <table border="0" width="100%" cellpadding="6" cellspacing="0">
                        <colgroup>
                            <col width="25">
                            <col width="25%">
                            <col width="25">
                            <col width="25%">
                        </colgroup>
                        <tr>
                            <td class="tdleft" colspan="4" style="background-color:#FFFF00;border:0px;border-radius:4px;color:black;font-weight:bold;font-size:14px;padding:8px;text-align:center" align="center">
                                AUTOCONTROL
                            </td>
                        </tr>
                        <tr>
                        <?php
                        $px = 0;
                        $workerResponses = $autoctrlStatus['worker_responses'] ?? [];
                        foreach ($autocontrolPoints as $p):
                            $acid = (int)$p['id'];
                            $isChk = !empty($workerResponses[$acid]);
                        ?>
                            <td class="tdnrm">
                                <input type="hidden" name="baseacids[]" value="<?=$acid?>">
                                <input type="checkbox" value="1" name="acids_<?=$acid?>" class="clsautocontrolchks"
                                       id="acid_chk_<?=$acid?>"
                                       <?=($isChk ? 'checked' : '')?>
                                       onchange="checkOperatorAutocontrolReady()">
                                <label for="acid_chk_<?=$acid?>" style="cursor:pointer"><?=htmlspecialchars((string)$p['ac_name'])?></label>
                            </td>
                            <?php
                            $px++;
                            if ($px >= 4):
                                $px = 0;
                                echo '</tr><tr>';
                            endif;
                        endforeach;
                        ?>
                        </tr>
                        </table>
                    </td>
                </tr>
                </table>

                <div style="clear:both;height:12px"></div>

                <?php if ($equipoTypeId === 7 || $isFlexo): ?>
                    <div id="idx_lider_btn_wrap" style="margin-bottom:8px">
                        <button type="submit" class="btngreen" id="idx_lider_btn"
                                style="width:100%;box-sizing:border-box;background-color:#FF5722;color:white;font-weight:bold;font-size:14px;padding:10px 0;border:none;border-radius:4px;cursor:pointer;display:block;text-align:center"
                                onclick="document.getElementById('autoctrl_submode').value='close2';">
                            <i class="fa fa-fw fa-envelope" style="color:white;"></i> Informar Lider de Área
                        </button>
                    </div>
                <?php endif; ?>

                <div id="idx_supervisor_btn_wrap" style="margin-bottom:12px">
                    <button type="submit" class="btngreen" id="idx_supervisor_btn"
                                style="width:100%;box-sizing:border-box;background-color:#4597C2;color:white;font-weight:bold;font-size:14px;padding:10px 0;border:none;border-radius:4px;cursor:pointer;display:block;text-align:center"
                                onclick="document.getElementById('autoctrl_submode').value='close';">
                        <i class="fa fa-fw fa-envelope" style="color:white;"></i> Informa supervisor
                    </button>
                </div>
            </form>

            <script>
            function checkOperatorAutocontrolReady() {
                var allChecked = true;
                var chks = document.querySelectorAll('.clsautocontrolchks');
                if (chks.length === 0) allChecked = false;
                chks.forEach(function(c) {
                    if (!c.checked) allChecked = false;
                });
                var btnSup = document.getElementById('idx_supervisor_btn');
                var btnLid = document.getElementById('idx_lider_btn');
                if (btnSup) {
                    btnSup.style.opacity = allChecked ? '1' : '0.5';
                }
                if (btnLid) {
                    btnLid.style.opacity = allChecked ? '1' : '0.5';
                }
                return allChecked;
            }
            document.getElementById('xform_autoctrl').addEventListener('submit', function(e) {
                if (!checkOperatorAutocontrolReady()) {
                    e.preventDefault();
                    alert('Debe seleccionar todos los puntos de autocontrol antes de informar.');
                    return false;
                }
            });
            checkOperatorAutocontrolReady();

            </script>
        <?php endif; ?>

        <!-- Tarjeta 3: Botones auxiliares de autocontrol.form.php -->
        <?php
        $autoMaterialsLabel = $isRebobinadora ? 'Bobina' : (($isSeri || $isSeriPulpo) ? 'Insumos' : 'Materiales');
        $autoHasTintasShortcut = $isFlexo;
        $autoColWidth = $autoHasTintasShortcut ? '20%' : '25%';
        ?>
        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px">
        <tr>
            <td width="<?=$autoColWidth?>" style="padding-right:3px">
                <div class="btnblue" style="width:100%;box-sizing:border-box;background-color:#293865;padding:10px 0;cursor:pointer;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16);font-weight:800;text-align:center" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=materiales&fromautocontrol=1'">
                    <i class="fa fa-fw <?= $isRebobinadora ? 'fa-dolly' : 'fa-cubes' ?>"></i> <?=$autoMaterialsLabel?>
                </div>
            </td>
            <?php if ($autoHasTintasShortcut): ?>
                <td width="<?=$autoColWidth?>" style="padding:0 3px">
                    <div class="btnblue" style="width:100%;box-sizing:border-box;background-color:#1D4ED8;padding:10px 0;cursor:pointer;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16);font-weight:800;text-align:center" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=tintas&fromautocontrol=1'">
                        <i class="fa fa-fw fa-tint"></i> Tintas
                    </div>
                </td>
            <?php endif; ?>
            <td width="<?=$autoColWidth?>" style="padding:0 3px">
                <div class="btnred" style="width:100%;box-sizing:border-box;background-color:#D64141;padding:10px 0;cursor:pointer;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16);font-weight:800;text-align:center" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=mantencion&fromautocontrol=1'">
                    <i class="fa fa-fw fa-wrench"></i> Mantención
                </div>
            </td>
            <td width="<?=$autoColWidth?>" style="padding:0 3px">
                <div class="btngrey" style="width:100%;box-sizing:border-box;background-color:#7F7F7F;padding:10px 0;cursor:pointer;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16);font-weight:800;text-align:center" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=pause&fromautocontrol=1'">
                    <i class="fa fa-fw fa-coffee"></i> Pausa
                </div>
            </td>
            <td width="<?=$autoColWidth?>" style="padding-left:3px">
                <?php if ($activeLunch !== null): ?>
                    <div class="btnorange" style="width:100%;box-sizing:border-box;background-color:#D97706;padding:10px 0;cursor:pointer;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16);font-weight:800;text-align:center" onclick="openLunchModal()" title="Colación activa">
                        <i class="fa fa-fw fa-coffee"></i> EN COLACIÓN
                    </div>
                <?php else: ?>
                    <div class="btnblue" style="width:100%;box-sizing:border-box;background-color:#0284C7;padding:10px 0;cursor:pointer;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16);font-weight:800;text-align:center" onclick="openLunchModal()" title="Registrar horario de colación">
                        <i class="fa fa-fw fa-coffee"></i> Colación
                    </div>
                <?php endif; ?>
            </td>
        </tr>
        </table>
        <?php
        echo renderDesignPreviewModal();
        renderStandbyAndLunchModals($activeStandby, $machineInit, $currentOperatorName, '/production/work-orders/operate?agid=' . $agId, $activeLunch);

