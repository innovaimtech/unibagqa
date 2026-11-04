<?php
    ?>
        <!-- ============================================================== -->
        <!-- RAMA A: FLEXOGRAFÍA (editxid.php líneas 2750 - 3080)           -->
        <!-- ============================================================== -->

        <!-- 1. INFORMACIÓN CLIENTE -->
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
                    <col width="220">
                    <col width="30%">
                    <col width="160">
                    <col>
                </colgroup>
                <tr>
                    <td class="tdleft">N° OT</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['prd_number'])?></td>
                    <td class="tdleft">Color N° 1</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Cliente</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                    <td class="tdleft">Color N° 2</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_2'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">N° CC</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['req_number'])?></td>
                    <td class="tdleft">Color N° 3</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_3'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Diseño</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_design_name'] !== '' ? $agenda['fab_design_name'] : $agenda['item_title']))?></td>
                    <td class="tdleft">Color N° 4</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_4'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Producto</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_number_prod'])?> | <?=htmlspecialchars((string)$agenda['item_title'])?></td>
                    <td class="tdleft">Color N° 5</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_5'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Materialidad</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                    <td class="tdleft">Color N° 6</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_6'] ?? ''))?></td>
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
                    <td colspan="4" style="background:#EDF0F6;padding:4px"></td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Fecha de solicitud de cliché</td>
                    <td class="tdnrm" colspan="2">
                        <?=(int)($agenda['req_cliche_peli_solic_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_solic_dat']) : '<span style="color:#999">-</span>'?>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Fecha de recepción de cliché</td>
                    <td class="tdnrm" colspan="2">
                        <?=(int)($agenda['req_cliche_peli_recep_dat'] ?? 0) > 0 ? date('d.m.Y', (int)$agenda['req_cliche_peli_recep_dat']) : '<span style="color:#999">-</span>'?>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft" colspan="2">Imagen Diseño</td>
                    <td class="tdnrm" colspan="2">
                        <?php if (!empty($agenda['fab_design_imagehash']) && $agenda['fab_design_imagehash'] !== 'dummy'): ?>
                            <?php 
                                $dUrl = '/production/design-image?folder=order&hash=' . rawurlencode((string)$agenda['fab_design_imagehash']); 
                                $consFullDesign = trim((string)($agenda['prd_name'] ?? ''));
                                $consMedidasStr = '';
                                if (!empty($agenda['fab_cut_cm'])) $consMedidasStr .= 'Corte ' . (float)$agenda['fab_cut_cm'] . 'cm';
                                if (!empty($agenda['fab_ancho_cm'])) $consMedidasStr .= ($consMedidasStr ? ' · ' : '') . 'Ancho ' . (float)$agenda['fab_ancho_cm'] . 'cm';
                                if (!empty($agenda['fab_alto_cm'])) $consMedidasStr .= ($consMedidasStr ? ' · ' : '') . 'Alto ' . (float)$agenda['fab_alto_cm'] . 'cm';
                            ?>
                            <div style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap">
                                <a href="<?=$dUrl?>" target="_blank" onclick="openDesignModal('<?=$dUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($consFullDesign))?>', '<?=htmlspecialchars($consMedidasStr)?>'); return false;" title="Clic para ampliar foto de lo que se va a producir">
                                    <img src="<?=$dUrl?>" alt="Foto Diseño" style="max-height:85px;max-width:130px;object-fit:contain;border:1px solid #cbd5e1;border-radius:6px;background:#fff;vertical-align:middle;box-shadow:0 1px 3px rgba(0,0,0,0.12);cursor:pointer">
                                </a>
                                <a href="<?=$dUrl?>" target="_blank" onclick="openDesignModal('<?=$dUrl?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', '<?=htmlspecialchars(addslashes($consFullDesign))?>', '<?=htmlspecialchars($consMedidasStr)?>'); return false;" class="btngrey" style="padding:6px 12px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">
                                    <i class="fa fa-fw fa-image" style="color:white;"></i> Ver Foto Completa
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
                        $isImg = unibagAttachmentIsImage($adjFile);
                    ?>
                        <tr>
                            <td class="tdleft" colspan="2">Imagen #<?=($x + 1)?></td>
                            <td class="tdnrm" colspan="2">
                                <div style="display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap">
                                    <?php if ($isImg): ?>
                                        <a href="/docs/prod/<?=htmlspecialchars($adjFile)?>" target="_blank" onclick="openDesignModal('/docs/prod/<?=htmlspecialchars(addslashes($adjFile))?>', '<?=htmlspecialchars((string)$agenda['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$agenda['cust_name']))?>', 'Adjunto #<?=($x + 1)?>', '<?=htmlspecialchars($consMedidasStr ?? '')?>'); return false;" title="Clic para ampliar">
                                            <img src="/docs/prod/<?=htmlspecialchars($adjFile)?>" alt="Adjunto #<?=($x + 1)?>" style="max-height:55px;max-width:85px;object-fit:contain;border:1px solid #cbd5e1;border-radius:4px;background:#fff;vertical-align:middle;cursor:pointer">
                                        </a>
                                    <?php endif; ?>
                                    <a href="/docs/prod/<?=htmlspecialchars($adjFile)?>" target="_blank" <?= $isImg ? 'onclick="openDesignModal(\'/docs/prod/' . htmlspecialchars(addslashes($adjFile)) . '\', \'' . htmlspecialchars((string)$agenda['prd_number']) . '\', \'' . htmlspecialchars(addslashes((string)$agenda['cust_name'])) . '\', \'Adjunto #' . ($x + 1) . '\', \'' . htmlspecialchars($consMedidasStr ?? '') . '\'); return false;"' : '' ?> class="btngrey" style="padding:4px 10px;font-size:12px;display:inline-flex;align-items:center;gap:5px;cursor:pointer">
                                        <i class="fa fa-fw fa-<?= $isImg ? 'image' : 'file' ?>" style="color:white;"></i> <?= $isImg ? 'Ver Imagen #' . ($x + 1) : 'Descargar Adjunto #' . ($x + 1) ?>
                                    </a>
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

        <!-- 2. INFORMACIÓN DE FABRICACIÓN -->
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
                    <col width="220">
                    <col width="30%">
                    <col width="160">
                    <col>
                </colgroup>
                <tr>
                    <td class="tdleft">Rodillo a utilizar</td>
                    <td class="tdnrm"><b>Z = <?=$fmt($corte_z, 0)?></b></td>
                    <td class="tdleft">Unidad N° 1</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_1'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Corte de bolsa</td>
                    <td class="tdnrm"><?=$fmt($corte_m2, 4)?> mtrs</td>
                    <td class="tdleft">Unidad N° 2</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_2'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Ubicación Clisé</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_infoaddprd_cliche_ubicacion'] ?? '-'))?></td>
                    <td class="tdleft">Unidad N° 3</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_3'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Código Cliché</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_infoaddprd_cliche_codigo'] ?? '-'))?></td>
                    <td class="tdleft">Unidad N° 4</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_4'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Pie de Imprenta</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_pie_imprenta'] ?? '-'))?></td>
                    <td class="tdleft">Unidad N° 5</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_5'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">N° Código de Barra</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['item_sellprice_barcodenumber'] ?? '-'))?></td>
                    <td class="tdleft">Unidad N° 6</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fab_print_colordesc_6'] ?? ''))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Impresiones al eje</td>
                    <td class="tdnrm">Sin información</td>
                    <td class="tdleft">Unidad N° 1</td>
                    <td class="tdnrm" id="summary_anilox_1"><?=htmlspecialchars((string)($aniloxDescMap[1] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Impresiones por desarrollo</td>
                    <td class="tdnrm"><?=(int)($agenda['req_solic_devprints_cc'] ?? 0)?> impresiones</td>
                    <td class="tdleft">Unidad N° 2</td>
                    <td class="tdnrm" id="summary_anilox_2"><?=htmlspecialchars((string)($aniloxDescMap[2] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">% de merma</td>
                    <td class="tdnrm"><?=$fmt($agenda['req_operador_mermaperc'], 2)?> %</td>
                    <td class="tdleft">Unidad N° 3</td>
                    <td class="tdnrm" id="summary_anilox_3"><?=htmlspecialchars((string)($aniloxDescMap[3] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Bolsas producidas / en curso</td>
                    <td class="tdnrm"><?=$fmt($stats['total_produced_this'])?> de <?=$fmt($stats['target_amount'] ?? $agenda['ag_amount'])?> (<?=$fmt($stats['percent_completed_this'], 2)?> %)</td>
                    <td class="tdleft">Unidad N° 4</td>
                    <td class="tdnrm" id="summary_anilox_4"><?=htmlspecialchars((string)($aniloxDescMap[4] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Bolsas producidas / total</td>
                    <td class="tdnrm"><?=$fmt($stats['total_produced_all'])?> de <?=$fmt($stats['order_amount'] ?? $agenda['item_amount'])?> (<?=$fmt($stats['percent_completed_all'], 2)?> %)</td>
                    <td class="tdleft">Unidad N° 5</td>
                    <td class="tdnrm" id="summary_anilox_5"><?=htmlspecialchars((string)($aniloxDescMap[5] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Saldo total</td>
                    <td class="tdnrm"><b><?=$fmt($stats['saldo_total'])?> unidades</b></td>
                    <td class="tdleft">Unidad N° 6</td>
                    <td class="tdnrm" id="summary_anilox_6"><?=htmlspecialchars((string)($aniloxDescMap[6] ?? '-'))?></td>
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

        <!-- 3. CONFORMACIÓN DE ANILOX Y CONSUMO DE TINTAS -->
        <style>
        .anilox-swap-highlight {
            background-color: #E8F5E9 !important;
            transition: background-color 0.8s ease;
        }
        .anilox-row-transition {
            transition: background-color 0.3s ease;
        }
        .anilox-kg-badge {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-width:92px;
            padding:6px 10px;
            border-radius:999px;
            font-size:12px;
            font-weight:bold;
            background:#EFF6FF;
            color:#1D4ED8;
            border:1px solid #BFDBFE;
        }
        .anilox-mix-card {
            background:#F8FAFC;
            border:1px solid #D8E2EE;
            border-radius:8px;
            padding:12px;
        }
        .anilox-mix-grid {
            display:grid;
            grid-template-columns: minmax(220px, 1fr) 110px;
            gap:8px;
            align-items:center;
        }
        .anilox-mix-modal {
            position: fixed;
            inset: 0;
            z-index: 99995;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, 0.55);
        }
        .anilox-mix-modal-card {
            width: min(920px, calc(100vw - 40px));
            max-height: calc(100vh - 40px);
            overflow: auto;
            background: #FFFFFF;
            border-radius: 14px;
            box-shadow: 0 18px 48px rgba(15, 23, 42, 0.30);
            border: 1px solid #CBD5E1;
        }
        .anilox-mix-slot {
            display:none;
            grid-template-columns: minmax(220px, 1fr) 110px 42px;
            gap:8px;
            align-items:center;
            margin-bottom:8px;
        }
        .anilox-mix-slot.is-visible {
            display:grid;
        }
        </style>
        <script>
        var isAniloxMoving = false;
        var aniloxMixSlots = 6;

        function showAniloxToast(msg, type) {
            var toast = document.getElementById('anilox_toast_msg');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'anilox_toast_msg';
                toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:999999;padding:12px 20px;border-radius:6px;font-size:13px;font-weight:bold;box-shadow:0 4px 16px rgba(0,0,0,0.2);transition:opacity 0.3s ease;display:none;';
                document.body.appendChild(toast);
            }
            if (type === 'success') {
                toast.style.backgroundColor = '#2E7D32';
                toast.style.color = '#FFFFFF';
                toast.innerHTML = '<i class="fa fa-check-circle" style="margin-right:6px;"></i> ' + msg;
            } else {
                toast.style.backgroundColor = '#C62828';
                toast.style.color = '#FFFFFF';
                toast.innerHTML = '<i class="fa fa-exclamation-circle" style="margin-right:6px;"></i> ' + msg;
            }
            toast.style.display = 'block';
            toast.style.opacity = '1';
            if (window._aniloxToastTimer) clearTimeout(window._aniloxToastTimer);
            window._aniloxToastTimer = setTimeout(function() {
                toast.style.opacity = '0';
                setTimeout(function() { toast.style.display = 'none'; }, 300);
            }, 2600);
        }

        function aniloxParseNumber(value) {
            var parsed = parseFloat(String(value || '').replace(/,/g, '.'));
            return isNaN(parsed) ? 0 : parsed;
        }

        function refreshAniloxSummaryLabel(unit) {
            var sel = document.getElementById('id_anilox_' + unit);
            var sumEl = document.getElementById('summary_anilox_' + unit);
            if (!sel || !sumEl) return;
            var selectedText = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '-';
            if (String(sel.value || '') === '') {
                selectedText = '-';
            }
            sumEl.innerText = selectedText;
        }

        function calcAniloxInkConsumed(unit) {
            var loadedInp = document.getElementById('ink_total_loaded_' + unit);
            var leftoverInp = document.getElementById('ink_leftover_' + unit);
            var badge = document.getElementById('ink_consumed_badge_' + unit);
            var loaded = loadedInp ? aniloxParseNumber(loadedInp.value) : 0;
            var leftover = leftoverInp ? aniloxParseNumber(leftoverInp.value) : 0;
            var consumed = Math.max(0, loaded - leftover);
            if (badge) {
                badge.innerText = consumed.toFixed(2) + ' kg';
                badge.style.backgroundColor = consumed > 0 ? '#ECFDF5' : '#EFF6FF';
                badge.style.borderColor = consumed > 0 ? '#A7F3D0' : '#BFDBFE';
                badge.style.color = consumed > 0 ? '#047857' : '#1D4ED8';
            }
        }

        function calcAniloxLoadTotal(unit) {
            var baseInp = document.getElementById('ink_loaded_base_' + unit);
            var totalInp = document.getElementById('ink_total_loaded_' + unit);
            var totalDisplayInp = document.getElementById('ink_total_loaded_display_' + unit);
            var loadNowInp = document.getElementById('ink_load_now_' + unit);
            var mixChk = document.getElementById('ink_is_mix_' + unit);
            var useMix = !!(mixChk && mixChk.checked);
            var baseLoaded = baseInp ? aniloxParseNumber(baseInp.value) : 0;
            var loadNow = 0;

            if (useMix) {
                for (var idx = 0; idx < aniloxMixSlots; idx++) {
                    var qtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
                    loadNow += qtyInp ? aniloxParseNumber(qtyInp.value) : 0;
                }
                if (loadNowInp) {
                    loadNowInp.value = loadNow > 0 ? loadNow.toFixed(2) : '';
                }
            } else {
                loadNow = loadNowInp ? aniloxParseNumber(loadNowInp.value) : 0;
            }

            var totalLoaded = Math.max(0, baseLoaded + loadNow);
            if (totalInp) {
                totalInp.value = totalLoaded > 0 ? totalLoaded.toFixed(2) : '';
            }
            if (totalDisplayInp) {
                totalDisplayInp.value = totalLoaded > 0 ? totalLoaded.toFixed(2) : '';
            }
            calcAniloxInkConsumed(unit);
            return {
                baseLoaded: baseLoaded,
                loadNow: loadNow,
                totalLoaded: totalLoaded
            };
        }

        function calcAniloxMixTotal(unit) {
            var total = 0;
            var usedCount = 0;
            for (var idx = 0; idx < aniloxMixSlots; idx++) {
                var itemSel = document.getElementById('mix_component_item_' + unit + '_' + idx);
                var qtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
                var qty = qtyInp ? aniloxParseNumber(qtyInp.value) : 0;
                var itemId = itemSel ? String(itemSel.value || '') : '';
                if (itemId !== '' || qty > 0) {
                    usedCount++;
                }
                total += qty;
            }

            var totalEl = document.getElementById('mix_total_badge_' + unit);
            var diffEl = document.getElementById('mix_diff_badge_' + unit);
            var countEl = document.getElementById('mix_count_badge_' + unit);
            var totals = calcAniloxLoadTotal(unit);

            if (totalEl) {
                totalEl.innerText = 'A cargar: ' + total.toFixed(2) + ' kg';
            }
            if (countEl) {
                countEl.innerText = usedCount + ' componente(s)';
            }
            if (diffEl) {
                if (totals.totalLoaded > 0) {
                    diffEl.innerText = 'Total: ' + totals.totalLoaded.toFixed(2) + ' kg';
                    diffEl.style.color = '#047857';
                } else {
                    diffEl.innerText = 'Total: 0.00 kg';
                    diffEl.style.color = '#64748B';
                }
            }
            refreshAniloxMixSummary(unit);
        }

        function toggleAniloxMixRow(unit) {
            var chk = document.getElementById('ink_is_mix_' + unit);
            var singleWrap = document.getElementById('anilox_single_ink_wrap_' + unit);
            var inkItemSel = document.getElementById('ink_item_' + unit);
            var loadNowInp = document.getElementById('ink_load_now_' + unit);
            var openBtn = document.getElementById('btn_open_mix_' + unit);
            var summary = document.getElementById('mix_summary_inline_' + unit);
            var useMix = !!(chk && chk.checked);
            if (singleWrap) {
                singleWrap.style.display = useMix ? 'none' : 'block';
            }
            if (inkItemSel) {
                inkItemSel.disabled = useMix;
                if (useMix) {
                    inkItemSel.value = '';
                }
            }
            if (loadNowInp) {
                loadNowInp.readOnly = useMix;
                loadNowInp.style.backgroundColor = useMix ? '#EFF6FF' : '#FFFFFF';
            }
            if (openBtn) {
                openBtn.style.display = useMix ? 'inline-block' : 'none';
            }
            if (summary) {
                summary.style.display = useMix ? 'inline-flex' : 'none';
            }
            if (!useMix) {
                closeAniloxMixModal(unit);
            }
            normalizeAniloxMixSlots(unit);
            calcAniloxMixTotal(unit);
        }

        function openAniloxMixModal(unit) {
            var chk = document.getElementById('ink_is_mix_' + unit);
            if (chk && !chk.checked) {
                chk.checked = true;
            }
            toggleAniloxMixRow(unit);
            var modal = document.getElementById('anilox_mix_modal_' + unit);
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeAniloxMixModal(unit) {
            var modal = document.getElementById('anilox_mix_modal_' + unit);
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function getUsedAniloxMixCount(unit) {
            var usedCount = 0;
            for (var idx = 0; idx < aniloxMixSlots; idx++) {
                var itemSel = document.getElementById('mix_component_item_' + unit + '_' + idx);
                var qtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
                var itemId = itemSel ? String(itemSel.value || '') : '';
                var qty = qtyInp ? aniloxParseNumber(qtyInp.value) : 0;
                if (itemId !== '' || qty > 0) {
                    usedCount++;
                }
            }
            return usedCount;
        }

        function setAniloxMixSlotVisibility(unit, idx, visible) {
            var slot = document.getElementById('mix_slot_' + unit + '_' + idx);
            if (!slot) return;
            slot.classList.toggle('is-visible', !!visible);
        }

        function normalizeAniloxMixSlots(unit) {
            var highestUsedCount = 0;
            var currentVisibleCount = 0;
            for (var idx = 0; idx < aniloxMixSlots; idx++) {
                var slot = document.getElementById('mix_slot_' + unit + '_' + idx);
                var itemSel = document.getElementById('mix_component_item_' + unit + '_' + idx);
                var qtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
                var itemId = itemSel ? String(itemSel.value || '') : '';
                var qty = qtyInp ? aniloxParseNumber(qtyInp.value) : 0;
                var isUsed = itemId !== '' || qty > 0;
                if (isUsed) {
                    highestUsedCount = idx + 1;
                }
                if (slot && slot.classList.contains('is-visible')) {
                    currentVisibleCount = idx + 1;
                }
            }

            var visibleCount = Math.max(1, highestUsedCount, currentVisibleCount);
            for (var showIdx = 0; showIdx < aniloxMixSlots; showIdx++) {
                setAniloxMixSlotVisibility(unit, showIdx, showIdx < visibleCount);
            }

            for (var hideIdx = visibleCount; hideIdx < aniloxMixSlots; hideIdx++) {
                var hideItemSel = document.getElementById('mix_component_item_' + unit + '_' + hideIdx);
                var hideQtyInp = document.getElementById('mix_component_qty_' + unit + '_' + hideIdx);
                if (hideIdx > 0 && hideItemSel && hideQtyInp && String(hideItemSel.value || '') === '' && aniloxParseNumber(hideQtyInp.value) <= 0) {
                    setAniloxMixSlotVisibility(unit, hideIdx, false);
                }
            }
            var addBtn = document.getElementById('btn_add_mix_slot_' + unit);
            if (addBtn) {
                addBtn.style.display = visibleCount >= aniloxMixSlots ? 'none' : 'inline-block';
            }
            refreshAniloxMixSummary(unit);
        }

        function addAniloxMixSlot(unit) {
            for (var idx = 0; idx < aniloxMixSlots; idx++) {
                var slot = document.getElementById('mix_slot_' + unit + '_' + idx);
                if (slot && !slot.classList.contains('is-visible')) {
                    setAniloxMixSlotVisibility(unit, idx, true);
                    var itemSel = document.getElementById('mix_component_item_' + unit + '_' + idx);
                    if (itemSel) itemSel.focus();
                    break;
                }
            }
            normalizeAniloxMixSlots(unit);
        }

        function clearAniloxMixSlot(unit, idx) {
            var itemSel = document.getElementById('mix_component_item_' + unit + '_' + idx);
            var qtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
            if (itemSel) itemSel.value = '';
            if (qtyInp) qtyInp.value = '';
            if (idx > 0) {
                setAniloxMixSlotVisibility(unit, idx, false);
            }
            normalizeAniloxMixSlots(unit);
            calcAniloxMixTotal(unit);
        }

        function clearAniloxMixQtys(unit) {
            for (var idx = 0; idx < aniloxMixSlots; idx++) {
                var qtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
                if (qtyInp) {
                    qtyInp.value = '';
                }
            }
            normalizeAniloxMixSlots(unit);
            calcAniloxMixTotal(unit);
        }

        function refreshAniloxMixSummary(unit) {
            var summary = document.getElementById('mix_summary_inline_' + unit);
            if (!summary) return;
            var usedCount = getUsedAniloxMixCount(unit);
            var total = 0;
            for (var idx = 0; idx < aniloxMixSlots; idx++) {
                var qtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
                total += qtyInp ? aniloxParseNumber(qtyInp.value) : 0;
            }
            summary.innerText = usedCount > 0 ? (usedCount + ' comp. / ' + total.toFixed(2) + ' kg') : 'Sin mezcla';
        }

        function collectAniloxRowState(unit) {
            var state = {
                color: '',
                colorHidden: '',
                anilox: '',
                inkItem: '',
                loaded: '',
                loadedBase: '',
                loadNow: '',
                leftover: '',
                isMix: false,
                notes: '',
                componentItems: [],
                componentQtys: []
            };

            var colorInp = document.getElementById('color_' + unit);
            var colorHiddenInp = document.getElementById('color_hidden_' + unit);
            var aniloxSel = document.getElementById('id_anilox_' + unit);
            var inkItemSel = document.getElementById('ink_item_' + unit);
            var loadedInp = document.getElementById('ink_total_loaded_' + unit);
            var loadedBaseInp = document.getElementById('ink_loaded_base_' + unit);
            var loadNowInp = document.getElementById('ink_load_now_' + unit);
            var leftoverInp = document.getElementById('ink_leftover_' + unit);
            var mixChk = document.getElementById('ink_is_mix_' + unit);
            var notesInp = document.getElementById('mix_notes_' + unit);

            state.color = colorInp ? colorInp.value : '';
            state.colorHidden = colorHiddenInp ? colorHiddenInp.value : '';
            state.anilox = aniloxSel ? aniloxSel.value : '';
            state.inkItem = inkItemSel ? inkItemSel.value : '';
            state.loaded = loadedInp ? loadedInp.value : '';
            state.loadedBase = loadedBaseInp ? loadedBaseInp.value : '';
            state.loadNow = loadNowInp ? loadNowInp.value : '';
            state.leftover = leftoverInp ? leftoverInp.value : '';
            state.isMix = mixChk ? !!mixChk.checked : false;
            state.notes = notesInp ? notesInp.value : '';

            for (var idx = 0; idx < aniloxMixSlots; idx++) {
                var componentItemSel = document.getElementById('mix_component_item_' + unit + '_' + idx);
                var componentQtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
                state.componentItems.push(componentItemSel ? componentItemSel.value : '');
                state.componentQtys.push(componentQtyInp ? componentQtyInp.value : '');
            }

            return state;
        }

        function applyAniloxRowState(unit, state) {
            var colorInp = document.getElementById('color_' + unit);
            var colorHiddenInp = document.getElementById('color_hidden_' + unit);
            var aniloxSel = document.getElementById('id_anilox_' + unit);
            var inkItemSel = document.getElementById('ink_item_' + unit);
            var loadedInp = document.getElementById('ink_total_loaded_' + unit);
            var loadedDisplayInp = document.getElementById('ink_total_loaded_display_' + unit);
            var loadedBaseInp = document.getElementById('ink_loaded_base_' + unit);
            var loadNowInp = document.getElementById('ink_load_now_' + unit);
            var leftoverInp = document.getElementById('ink_leftover_' + unit);
            var mixChk = document.getElementById('ink_is_mix_' + unit);
            var notesInp = document.getElementById('mix_notes_' + unit);

            if (colorInp) colorInp.value = state.color || '';
            if (colorHiddenInp) colorHiddenInp.value = state.colorHidden || state.color || '';
            if (aniloxSel) aniloxSel.value = state.anilox || '';
            if (inkItemSel) inkItemSel.value = state.inkItem || '';
            if (loadedInp) loadedInp.value = state.loaded || '';
            if (loadedDisplayInp) loadedDisplayInp.value = state.loaded || '';
            if (loadedBaseInp) loadedBaseInp.value = state.loadedBase || '';
            if (loadNowInp) loadNowInp.value = state.loadNow || '';
            if (leftoverInp) leftoverInp.value = state.leftover || '';
            if (mixChk) mixChk.checked = !!state.isMix;
            if (notesInp) notesInp.value = state.notes || '';

            for (var idx = 0; idx < aniloxMixSlots; idx++) {
                var componentItemSel = document.getElementById('mix_component_item_' + unit + '_' + idx);
                var componentQtyInp = document.getElementById('mix_component_qty_' + unit + '_' + idx);
                if (componentItemSel) componentItemSel.value = state.componentItems[idx] || '';
                if (componentQtyInp) componentQtyInp.value = state.componentQtys[idx] || '';
            }

            refreshAniloxSummaryLabel(unit);
            calcAniloxInkConsumed(unit);
            toggleAniloxMixRow(unit);
        }

        function GrabaAnilox(unidadCount) {
            var saveBtn = document.getElementById('btn_grabar_anilox');
            var form = document.getElementById('anilox_form');
            var origHtml = saveBtn ? saveBtn.innerHTML : '';
            if (!form) {
                alert('No se encontró el formulario de anilox.');
                return;
            }

            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fa fa-fw fa-spinner fa-spin" style="color:white;"></i> Guardando...';
            }

            var maxUnits = (typeof unidadCount === 'number' && unidadCount > 1) ? unidadCount : <?=count($detalleAnilox)?>;
            for (var x = 1; x <= maxUnits; x++) {
                closeAniloxMixModal(x);
                normalizeAniloxMixSlots(x);
                refreshAniloxSummaryLabel(x);
                calcAniloxInkConsumed(x);
                calcAniloxMixTotal(x);
            }

            var formData = new FormData(form);
            formData.append('save_anilox', '1');
            formData.append('ajax', '1');

            fetch('/production/work-orders/operate', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(data) {
                if (data && data.ok) {
                    for (var x = 1; x <= maxUnits; x++) {
                        var totalInp = document.getElementById('ink_total_loaded_' + x);
                        var baseInp = document.getElementById('ink_loaded_base_' + x);
                        var loadNowInp = document.getElementById('ink_load_now_' + x);
                        if (baseInp && totalInp) {
                            baseInp.value = totalInp.value || '';
                        }
                        if (loadNowInp) {
                            loadNowInp.value = '';
                        }
                        clearAniloxMixQtys(x);
                        calcAniloxLoadTotal(x);
                        calcAniloxMixTotal(x);
                    }
                    showAniloxToast('Conformación de Anilox y tintas guardada exitosamente.', 'success');
                } else {
                    alert(data && data.error ? data.error : 'Error al guardar Anilox y tintas.');
                }
            })
            .catch(function(err) {
                alert('Error de conexión al guardar Anilox: ' + err.message);
            })
            .finally(function() {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = origHtml;
                }
            });
        }

        function MoverFlecha(ir, unidad) {
            if (isAniloxMoving) {
                return;
            }
            var totalUnidades = <?=count($detalleAnilox)?>;
            if (ir === 1 && unidad <= 1) {
                alert("No puede Mover, estas en la primera posición");
                return;
            }
            if (ir === 2 && totalUnidades > 0 && unidad >= totalUnidades) {
                alert("No puede Mover, estas en la última posición");
                return;
            }

            var targetUnidad = (ir === 1) ? (unidad - 1) : (unidad + 1);

            var trCurr = document.getElementById('tr_anilox_' + unidad);
            var trTgt = document.getElementById('tr_anilox_' + targetUnidad);
            var mixRowCurr = document.getElementById('tr_anilox_mix_' + unidad);
            var mixRowTgt = document.getElementById('tr_anilox_mix_' + targetUnidad);

            var prevStateCurr = collectAniloxRowState(unidad);
            var prevStateTgt = collectAniloxRowState(targetUnidad);

            applyAniloxRowState(unidad, prevStateTgt);
            applyAniloxRowState(targetUnidad, prevStateCurr);

            if (trCurr) {
                trCurr.classList.add('anilox-swap-highlight');
                setTimeout(function() { trCurr.classList.remove('anilox-swap-highlight'); }, 800);
            }
            if (trTgt) {
                trTgt.classList.add('anilox-swap-highlight');
                setTimeout(function() { trTgt.classList.remove('anilox-swap-highlight'); }, 800);
            }
            if (mixRowCurr) {
                mixRowCurr.classList.add('anilox-swap-highlight');
                setTimeout(function() { mixRowCurr.classList.remove('anilox-swap-highlight'); }, 800);
            }
            if (mixRowTgt) {
                mixRowTgt.classList.add('anilox-swap-highlight');
                setTimeout(function() { mixRowTgt.classList.remove('anilox-swap-highlight'); }, 800);
            }

            isAniloxMoving = true;
            var allBtns = document.querySelectorAll('.btn-anilox-mover');
            allBtns.forEach(function(b) {
                b.style.opacity = '0.5';
                b.style.cursor = 'not-allowed';
            });

            var url = '/production/work-orders/operate?agid=<?=$agId?>&mover=' + ir + '&unidad=' + unidad + '&ajax=1';
            fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(data) {
                if (!data || !data.ok) {
                    applyAniloxRowState(unidad, prevStateCurr);
                    applyAniloxRowState(targetUnidad, prevStateTgt);
                    alert(data && data.error ? data.error : 'Error al mover anilox.');
                } else {
                    showAniloxToast('Unidad ' + unidad + ' intercambiada con Unidad ' + targetUnidad, 'success');
                }
            })
            .catch(function(err) {
                applyAniloxRowState(unidad, prevStateCurr);
                applyAniloxRowState(targetUnidad, prevStateTgt);
                alert('Error de conexión al mover anilox: ' + err.message);
            })
            .finally(function() {
                isAniloxMoving = false;
                allBtns.forEach(function(b) {
                    b.style.opacity = '1';
                    b.style.cursor = 'pointer';
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            var totalUnidades = <?=count($detalleAnilox)?>;
            for (var x = 1; x <= totalUnidades; x++) {
                refreshAniloxSummaryLabel(x);
                calcAniloxInkConsumed(x);
                toggleAniloxMixRow(x);
            }
        });
        </script>
        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_anilox', 'icon_info_anilox')">
                <span><i class="fa fa-fw fa-circle-notch"></i> Conformación de Anilox y Tintas</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_anilox">Minimizar</span>
                    <i id="icon_info_anilox" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_anilox" style="padding:15px">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px">
                    <div style="font-size:12px;color:#334155">
                        Bodega de tintas: <b><?=htmlspecialchars((string)($inkWarehouse['name'] ?? 'Sin definir'))?></b>
                    </div>
                </div>
                <form id="anilox_form" method="post" action="/production/work-orders/operate" onsubmit="return false;">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="anilox_unit_count" value="<?=count($detalleAnilox)?>">
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <colgroup>
                        <col width="55">
                        <col width="20%">
                        <col width="19%">
                        <col width="20%">
                        <col width="90">
                        <col width="90">
                        <col width="95">
                        <col width="105">
                        <col width="95">
                        <col width="92">
                    </colgroup>
                    <tr>
                        <td class="tdheader" align="center">Unid.</td>
                        <td class="tdheader" align="center">Color</td>
                        <td class="tdheader" align="center">Anilox</td>
                        <td class="tdheader" align="center">Tinta</td>
                        <td class="tdheader" align="center">Total kg</td>
                        <td class="tdheader" align="center">Cargar kg</td>
                        <td class="tdheader" align="center">Sobrante kg</td>
                        <td class="tdheader" align="center">Consumido</td>
                        <td class="tdheader" align="center">Mezcla</td>
                        <td class="tdheader" align="center">Opciones</td>
                    </tr>
                    <?php
                    $xAni = 1;
                    foreach ($detalleAnilox as $ani):
                        $unidadAnilox = (int)($ani['paow_unidad'] ?? 0);
                        $savedInkConfig = $aniloxInkConfigByUnit[$unidadAnilox] ?? [];
                        $savedInkItemId = (int)($savedInkConfig['ink_item_id'] ?? 0);
                        $savedLoadedKg = (float)($savedInkConfig['loaded_kg'] ?? 0);
                        $savedLeftoverKg = (float)($savedInkConfig['leftover_kg'] ?? 0);
                        $savedConsumedKg = (float)($savedInkConfig['consumed_kg'] ?? max(0, $savedLoadedKg - $savedLeftoverKg));
                        $savedIsMixed = (int)($savedInkConfig['is_mixed'] ?? 0) === 1;
                        $savedMixNotes = trim((string)($savedInkConfig['mixture_notes'] ?? ''));
                        $savedComponents = array_values($savedInkConfig['components'] ?? []);
                    ?>
                    <tr id="tr_anilox_<?=$unidadAnilox?>" class="anilox-row-transition">
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD;text-align:center"><b><?=$unidadAnilox?></b></td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD">
                            <input type="text" class="inptxt" id="color_<?=$unidadAnilox?>" style="width:100%;background:#F8FAFC" value="<?=htmlspecialchars((string)($ani['paow_color'] ?? ''))?>" readonly>
                            <input type="hidden" id="color_hidden_<?=$unidadAnilox?>" name="color<?=$unidadAnilox?>" value="<?=htmlspecialchars((string)($ani['paow_color'] ?? ''))?>">
                        </td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD">
                            <select name="anilox<?=$unidadAnilox?>" id="id_anilox_<?=$unidadAnilox?>" class="inptxt" style="width:100%;background-color:#FFFFFF">
                                <option value="">&lt; Seleccione Anilox &gt;</option>
                                <?php foreach ($itemAnilox as $ia): ?>
                                    <option value="<?=$ia['id']?>" <?=(int)$ia['id'] === (int)($ani['paow_anilox'] ?? 0) ? 'selected' : ''?>>
                                        <?=htmlspecialchars((string)($ia['item_number_prod'] . '-' . $ia['item_title']))?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD">
                            <div id="anilox_single_ink_wrap_<?=$unidadAnilox?>">
                                <select name="ink_item_<?=$unidadAnilox?>" id="ink_item_<?=$unidadAnilox?>" class="inptxt" style="width:100%;background-color:#FFFFFF">
                                    <option value="">&lt; Seleccione Tinta &gt;</option>
                                    <?php foreach ($inkItems as $inkItem): ?>
                                        <?php
                                        $inkItemId = (int)($inkItem['id'] ?? 0);
                                        $inkItemCode = trim((string)($inkItem['code'] ?? ''));
                                        $inkItemName = trim((string)($inkItem['name'] ?? ''));
                                        ?>
                                        <option value="<?=$inkItemId?>" <?=$inkItemId === $savedInkItemId ? 'selected' : ''?>>
                                            <?=htmlspecialchars(trim($inkItemCode . ' - ' . $inkItemName, ' -'))?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD;text-align:center">
                            <input type="hidden" name="ink_loaded_base_<?=$unidadAnilox?>" id="ink_loaded_base_<?=$unidadAnilox?>" value="<?=$savedLoadedKg > 0 ? number_format($savedLoadedKg, 2, '.', '') : ''?>">
                            <input type="hidden" name="ink_total_loaded_<?=$unidadAnilox?>" id="ink_total_loaded_<?=$unidadAnilox?>" value="<?=$savedLoadedKg > 0 ? number_format($savedLoadedKg, 2, '.', '') : ''?>">
                            <input type="number" step="0.01" min="0" id="ink_total_loaded_display_<?=$unidadAnilox?>" class="inptxt" style="width:82px;text-align:center;background:#F8FAFC" value="<?=$savedLoadedKg > 0 ? number_format($savedLoadedKg, 2, '.', '') : ''?>" placeholder="0.00" readonly>
                        </td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD;text-align:center">
                            <input type="number" step="0.01" min="0" name="ink_load_now_<?=$unidadAnilox?>" id="ink_load_now_<?=$unidadAnilox?>" class="inptxt" style="width:82px;text-align:center" value="" placeholder="0.00" oninput="calcAniloxLoadTotal(<?=$unidadAnilox?>);calcAniloxMixTotal(<?=$unidadAnilox?>)">
                        </td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD;text-align:center">
                            <input type="number" step="0.01" min="0" name="ink_leftover_<?=$unidadAnilox?>" id="ink_leftover_<?=$unidadAnilox?>" class="inptxt" style="width:82px;text-align:center" value="<?=$savedLeftoverKg > 0 ? number_format($savedLeftoverKg, 2, '.', '') : ''?>" placeholder="0.00" oninput="calcAniloxInkConsumed(<?=$unidadAnilox?>)">
                        </td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD;text-align:center">
                            <span id="ink_consumed_badge_<?=$unidadAnilox?>" class="anilox-kg-badge"><?=number_format($savedConsumedKg, 2, '.', '')?> kg</span>
                        </td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD;text-align:center">
                            <label style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:bold;color:#1D4ED8;cursor:pointer">
                                <input type="checkbox" name="ink_is_mix_<?=$unidadAnilox?>" id="ink_is_mix_<?=$unidadAnilox?>" value="1" <?=$savedIsMixed ? 'checked' : ''?> onchange="toggleAniloxMixRow(<?=$unidadAnilox?>)">
                                Mezcla
                            </label>
                            <button type="button" id="btn_open_mix_<?=$unidadAnilox?>" class="btnblue" style="display:<?=$savedIsMixed ? 'inline-block' : 'none'?>;padding:4px 8px;font-size:11px;cursor:pointer;margin-left:6px" onclick="openAniloxMixModal(<?=$unidadAnilox?>)">
                                <i class="fa fa-flask"></i> Abrir
                            </button>
                            <div id="mix_summary_inline_<?=$unidadAnilox?>" style="display:<?=$savedIsMixed ? 'inline-flex' : 'none'?>;margin-top:6px;font-size:10.5px;font-weight:bold;color:#475569;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:999px;padding:3px 8px">
                                Sin mezcla
                            </div>
                        </td>
                        <td class="tdnrm" style="border-left:1px solid #DDDDDD;text-align:center">
                            <button type="button" class="btngrey btn-anilox-mover" style="padding:4px 8px;font-size:12px;cursor:pointer" title="Mover arriba" onclick="MoverFlecha(1, <?=$unidadAnilox?>)">
                                <i class="fa fa-fw fa-arrow-up"></i>
                            </button>
                            <button type="button" class="btngrey btn-anilox-mover" style="padding:4px 8px;font-size:12px;cursor:pointer;margin-left:4px" title="Mover abajo" onclick="MoverFlecha(2, <?=$unidadAnilox?>)">
                                <i class="fa fa-fw fa-arrow-down"></i>
                            </button>
                        </td>
                    </tr>
                    <tr id="tr_anilox_mix_<?=$unidadAnilox?>" style="height:0">
                        <td colspan="10" class="tdnrm" style="padding:0;border:none;height:0;line-height:0">
                            <div id="anilox_mix_modal_<?=$unidadAnilox?>" class="anilox-mix-modal" onclick="if(event.target===this){closeAniloxMixModal(<?=$unidadAnilox?>)}">
                                <div class="anilox-mix-modal-card">
                                    <div style="padding:16px 18px;border-bottom:1px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                                        <div>
                                            <div style="font-size:17px;font-weight:800;color:#0F172A">
                                                Mezcla Unidad <?=$unidadAnilox?> · <?=htmlspecialchars((string)($ani['paow_color'] ?? 'Sin color'))?>
                                            </div>
                                            <div style="font-size:12px;color:#64748B;margin-top:3px">
                                                La suma de componentes se carga automáticamente en <b>Carga kg</b>.
                                            </div>
                                        </div>
                                        <button type="button" class="btngrey" style="padding:6px 12px;font-size:12px;cursor:pointer" onclick="closeAniloxMixModal(<?=$unidadAnilox?>)">
                                            <i class="fa fa-times"></i> Cerrar
                                        </button>
                                    </div>
                                    <div style="padding:16px 18px">
                                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px">
                                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                                <span id="mix_count_badge_<?=$unidadAnilox?>" class="anilox-kg-badge" style="background:#F8FAFC;border-color:#E2E8F0;color:#475569">0 componente(s)</span>
                                                <span id="mix_total_badge_<?=$unidadAnilox?>" class="anilox-kg-badge" style="background:#EEF2FF;border-color:#C7D2FE;color:#3730A3">Mezcla: 0.00 kg</span>
                                                <span id="mix_diff_badge_<?=$unidadAnilox?>" class="anilox-kg-badge" style="background:#FFF7ED;border-color:#FED7AA;color:#B45309">Dif.: 0.00 kg</span>
                                            </div>
                                        </div>
                                        <div style="display:flex;flex-direction:column;gap:0">
                                            <?php for ($mixIdx = 0; $mixIdx < 6; $mixIdx++): ?>
                                                <?php $savedComponent = $savedComponents[$mixIdx] ?? []; ?>
                                                <div id="mix_slot_<?=$unidadAnilox?>_<?=$mixIdx?>" class="anilox-mix-slot<?=((int)($savedComponent['item_id'] ?? 0) > 0 || (float)($savedComponent['qty_kg'] ?? 0) > 0) ? ' is-visible' : ''?>">
                                                    <select name="mix_component_item_<?=$unidadAnilox?>[]" id="mix_component_item_<?=$unidadAnilox?>_<?=$mixIdx?>" class="inptxt" style="width:100%" onchange="normalizeAniloxMixSlots(<?=$unidadAnilox?>);calcAniloxMixTotal(<?=$unidadAnilox?>)">
                                                        <option value="">&lt; Componente <?=($mixIdx + 1)?> &gt;</option>
                                                        <?php foreach ($inkItems as $inkItem): ?>
                                                            <?php
                                                            $inkItemId = (int)($inkItem['id'] ?? 0);
                                                            $inkItemCode = trim((string)($inkItem['code'] ?? ''));
                                                            $inkItemName = trim((string)($inkItem['name'] ?? ''));
                                                            ?>
                                                            <option value="<?=$inkItemId?>" <?=$inkItemId === (int)($savedComponent['item_id'] ?? 0) ? 'selected' : ''?>>
                                                                <?=htmlspecialchars(trim($inkItemCode . ' - ' . $inkItemName, ' -'))?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="number" step="0.01" min="0" name="mix_component_qty_<?=$unidadAnilox?>[]" id="mix_component_qty_<?=$unidadAnilox?>_<?=$mixIdx?>" class="inptxt" style="width:100%;text-align:center" value="<?=!empty($savedComponent['qty_kg']) ? number_format((float)$savedComponent['qty_kg'], 2, '.', '') : ''?>" placeholder="kg" oninput="normalizeAniloxMixSlots(<?=$unidadAnilox?>);calcAniloxMixTotal(<?=$unidadAnilox?>)">
                                                    <button type="button" class="btngrey" style="padding:6px 0;font-size:12px;cursor:pointer" onclick="clearAniloxMixSlot(<?=$unidadAnilox?>, <?=$mixIdx?>)">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                        <div style="margin-top:10px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
                                            <button type="button" id="btn_add_mix_slot_<?=$unidadAnilox?>" class="btnblue" style="padding:6px 12px;font-size:12px;cursor:pointer" onclick="addAniloxMixSlot(<?=$unidadAnilox?>)">
                                                <i class="fa fa-plus"></i> Agregar componente
                                            </button>
                                            <div style="font-size:11.5px;color:#64748B">
                                                Máximo 6 componentes
                                            </div>
                                        </div>
                                        <div style="margin-top:10px">
                                            <input type="text" name="mix_notes_<?=$unidadAnilox?>" id="mix_notes_<?=$unidadAnilox?>" class="inptxt" style="width:100%" value="<?=htmlspecialchars($savedMixNotes)?>" placeholder="Observación de mezcla">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php
                    $xAni++;
                    endforeach;
                    ?>
                    <tr>
                        <td colspan="9" style="padding-top:10px">
                            <button type="button" id="btn_grabar_anilox" class="btngreen" style="font-size:13px;padding:8px 22px;cursor:pointer" onclick="GrabaAnilox(<?=count($detalleAnilox)?>)">
                                <i class="fa fa-fw fa-save" style="color:white;"></i> Guardar Anilox y Tintas
                            </button>
                        </td>
                    </tr>
                    </table>
                </form>
            </div>
        </div>


    <?php

