<?php
    ?>
        <!-- SUBFORMULARIO: Rebobinado (form.reboprod.php) -->
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-chevron-left" style="color:white;"></i> Volver&nbsp;
            </div>
        </div>

        <?php if (!empty($_GET['rebo_done'])): ?>
            <div style="background:#E8F8F5;border:1.5px solid #2ECC71;color:#117A65;padding:16px 20px;border-radius:6px;margin-bottom:18px;font-size:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div>
                    <i class="fa fa-fw fa-check-circle" style="font-size:20px;color:#27AE60"></i>
                    <?php if (!empty($_GET['is_empalme'])): ?>
                        <b>¡Empalme de bobinas procesado exitosamente!</b> Se unieron las bobinas en Bodega 3000 y se generó la nueva bobina empalmada con trazabilidad activa.
                    <?php elseif (!empty($_GET['is_rebobinado'])): ?>
                        <b>¡Rebobinado registrado exitosamente!</b> Se registró la bobina rebobinada con su pesaje y observaciones (sin alterar consumos de materiales ni duplicar inventario).
                    <?php else: ?>
                        <b>¡Producción de corte procesada exitosamente!</b> Se procesó la bobina y se dieron de alta <b><?= (int)($_GET['cuts'] ?? 0) ?></b> bobinas individuales con trazabilidad en <b>Bodega 3000</b>.
                        <?php if (!empty($_GET['handle_rolls'])): ?>
                            <span> (Incluye <b><?= (int)$_GET['handle_rolls'] ?></b> bobinas de manillas etiquetadas una por una).</span>
                        <?php endif; ?>
                        <?php if (!empty($_GET['merma_manillas'])): ?>
                            <span style="color:#C0392B;font-weight:bold"> (<b><?= (int)$_GET['merma_manillas'] ?></b> manillas cargadas a merma por bobina sucia / descarte: <?=number_format((float)($_GET['merma_manillas_kg'] ?? 0), 2, ',', '.')?> Kg).</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if (!empty($_GET['first_cut'])): ?>
                        <a href="/rolls/<?= (int)$_GET['first_cut'] ?>/label?server_print=1" target="_blank" class="btnblue" style="text-decoration:none;font-weight:bold">
                            <i class="fa fa-print"></i> <?= !empty($_GET['is_empalme']) ? 'Imprimir Etiqueta Bobina Empalmada (100×70mm)' : 'Imprimir Etiqueta Bobina (100×70mm)' ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php
        $reboType = trim((string)($reboOrder['req_rebo_type'] ?? ($agenda['req_rebo_type'] ?? 'Corte')));
        $isSoloManillas = $prodService->isReboSoloManillas($agenda, $reboOrder, $subproducts);
        $rollosccOpt = trim((string)($reboOrder['req_rebo_rolloscc_opt'] ?? ''));

        $rawTask = trim((string)($_REQUEST['task'] ?? ''));
        // Tarea de Rebobinadora determinada exclusivamente por la clasificación oficial de la OT (no editable por operador)
        if ($reboType === 'Empalme') {
            $task = 'empalme';
        } elseif ($reboType === 'Rebobinado') {
            $task = 'rebobinado';
        } else {
            $task = 'corte';
        }

        // Subtipo para Corte (Bolsas vs Solo Manillas) determinado automáticamente según la clasificación de la OT
        if ($isSoloManillas || $rollosccOpt === '100cm_manillas') {
            $corteSub = 'manillas';
        } else {
            $corteSub = 'bolsa';
        }
        $itemsel = $reboItemSel1;
        $lidx = 1;
        $totalScrappedManillasUnits = 0;
        $totalScrappedManillasKgSum = 0.0;
        ?>

        <!-- INDICADOR DE TAREA PLANIFICADA SEGÚN CLASIFICACIÓN (FIJA / NO MODIFICABLE POR OPERADOR) -->
        <div style="background:#FFFFFF;border:1px solid #D5D8DC;border-radius:6px;padding:12px 18px;margin-bottom:15px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span style="font-weight:bold;color:#2C3E50;font-size:13px"><i class="fa fa-tasks"></i> Tarea:</span>

                <!-- TAREA 1: CORTE -->
                <span style="padding:7px 16px;border-radius:4px;font-weight:bold;font-size:13px;display:inline-flex;align-items:center;gap:7px;<?=$task === 'corte' ? 'background:#6C3483;color:#FFFFFF;box-shadow:0 2px 4px rgba(108,52,131,0.25)' : 'background:#F2F4F4;color:#95A5A6;opacity:0.6;cursor:not-allowed'?>">
                    <i class="fa fa-cut"></i> Corte
                </span>

                <!-- TAREA 2: REBOBINADO -->
                <span style="padding:7px 16px;border-radius:4px;font-weight:bold;font-size:13px;display:inline-flex;align-items:center;gap:7px;<?=$task === 'rebobinado' ? 'background:#D35400;color:#FFFFFF;box-shadow:0 2px 4px rgba(211,84,0,0.25)' : 'background:#F2F4F4;color:#95A5A6;opacity:0.6;cursor:not-allowed'?>">
                    <i class="fa fa-sync-alt"></i> Rebobinado
                </span>

                <!-- TAREA 3: EMPALME -->
                <span style="padding:7px 16px;border-radius:4px;font-weight:bold;font-size:13px;display:inline-flex;align-items:center;gap:7px;<?=$task === 'empalme' ? 'background:#0E6251;color:#FFFFFF;box-shadow:0 2px 4px rgba(14,98,81,0.25)' : 'background:#F2F4F4;color:#95A5A6;opacity:0.6;cursor:not-allowed'?>">
                    <i class="fa fa-link"></i> Empalme
                </span>
            </div>
            <div style="font-size:12px;color:#7F8C8D">
                <b>Clasificación en OT:</b> <span style="background:#EBF5FB;padding:3px 8px;border-radius:3px;font-weight:bold;color:#1B4F72"><?=htmlspecialchars((string)($reboOrder['req_rebo_type'] ?? $agenda['req_rebo_type'] ?? 'Corte'))?> <?=htmlspecialchars($rollosccOpt !== '' ? "({$rollosccOpt})" : '')?></span>
                <span style="margin-left:6px;color:#27AE60;font-weight:bold"><i class="fa fa-lock"></i> Asignada automáticamente</span>
            </div>
        </div>

        <!-- BANNER INFORMATIVO SEGÚN TAREA ACTIVA -->
        <div style="background:#FFFFFF;border:1px solid #D5D8DC;border-radius:6px;padding:12px 18px;margin-bottom:15px;display:flex;align-items:center;gap:12px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
            <?php if ($task === 'empalme'): ?>
                <span style="background:#0E6251;color:#FFFFFF;padding:6px 12px;border-radius:4px;font-weight:bold;font-size:12.5px;letter-spacing:0.5px">
                    <i class="fa fa-link"></i> EMPALME DE BOBINAS
                </span>
                <span style="font-size:13px;color:#2C3E50">
                    Une dos o más bobinas para generar una de mayor metraje y peso. Seleccione las bobinas con stock en Bodega 3000 o escanéelas con el lector. <b>No genera duplicidad en el informe de materiales</b> y genera la <b>nueva bobina empalmada</b> con código de trazabilidad.
                </span>
            <?php elseif ($task === 'rebobinado'): ?>
                <span style="background:#D35400;color:#FFFFFF;padding:6px 12px;border-radius:4px;font-weight:bold;font-size:12.5px;letter-spacing:0.5px">
                    <i class="fa fa-sync-alt"></i> REBOBINADO DE BOBINA
                </span>
                <span style="font-size:13px;color:#2C3E50">
                    Rebobinado para ajuste de tensión, cambio de buje/cono o corrección de rollo. Indique qué bobina se rebobinó escaneando su código o seleccionándola de Bodega 3000. <b>Esta tarea no altera el informe oficial de materiales ni genera consumos indebidos de stock</b>.
                </span>
            <?php elseif ($corteSub === 'manillas'): ?>
                <span style="background:#1B4F72;color:#FFFFFF;padding:6px 12px;border-radius:4px;font-weight:bold;font-size:12.5px;letter-spacing:0.5px">
                    <i class="fa fa-cut"></i> CORTE: SOLO MANILLAS
                </span>
                <span style="font-size:13px;color:#2C3E50">
                    Corte longitudinal exclusivo para tiras de manillas a partir de bobinas de tela vírgenes (TEL) de Bodega 3000. <b>Se consumirá 1 bobina en el informe oficial de materiales</b> y se generarán los códigos de trazabilidad individuales (<b>ROLL-MAN-XXXXX</b>) con su etiqueta térmica para cada rollo en Bodega 3000.
                </span>
            <?php else: ?>
                <span style="background:#6C3483;color:#FFFFFF;padding:6px 12px;border-radius:4px;font-weight:bold;font-size:12.5px;letter-spacing:0.5px">
                    <i class="fa fa-shopping-bag"></i> CORTE: CUERPOS DE BOLSA (+ MANILLAS)
                </span>
                <span style="font-size:13px;color:#2C3E50">
                    Puede cortar una bobina impresa o una tela virgen antes de imprimir. <b>Los cuerpos cortados de tela virgen quedan disponibles para impresión en Bodega 3000</b>. Escanee la bobina o seleccione una tela virgen del listado.
                </span>
            <?php endif; ?>
        </div>

        <!-- MÓDULO SCANNER DE BOBINA (CÓDIGO DE BARRA / QR) -->
        <div style="background:#EBF5FB;border:2px dashed #2980B9;border-radius:6px;padding:14px 18px;margin-bottom:15px">
            <form action="/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod" method="post" style="margin:0;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="mode" value="reboprod">
                <input type="hidden" name="task" value="<?=htmlspecialchars($task)?>">
                <input type="hidden" name="corte_sub" value="<?=htmlspecialchars($corteSub)?>">
                <input type="hidden" name="selitemid1" value="<?=$selItemId1?>">
                <input type="hidden" name="selitemid2" value="<?=$selItemId2?>">

                <div style="font-size:22px;color:#2980B9">
                    <i class="fa fa-barcode"></i> <i class="fa fa-qrcode"></i>
                </div>
                <div style="font-weight:bold;color:#1B4F72;font-size:14px">
                    Lector de Bobina:
                </div>

                <?php if ($task === 'empalme'): ?>
                    <select name="target_slot" style="padding:8px 12px;border:1px solid #2980B9;border-radius:4px;font-weight:bold;background:#FFFFFF;color:#1B4F72">
                        <option value="1" <?=$selItemId1 <= 0 ? 'selected' : ''?>>Cargar en Bobina 1</option>
                        <option value="2" <?=($selItemId1 > 0 && $selItemId2 <= 0) ? 'selected' : ''?>>Cargar en Bobina 2</option>
                    </select>
                <?php else: ?>
                    <input type="hidden" name="target_slot" value="1">
                <?php endif; ?>

                <?php
                if ($task === 'empalme') {
                    $scanPh = $selItemId1 > 0 ? 'Escanee Bobina 2 a empalmar (Ej: TEL0165, BTE0075)...' : 'Escanee Bobina 1 a empalmar...';
                } elseif ($task === 'rebobinado') {
                    $scanPh = 'Escanee código de trazabilidad o SKU de la bobina a rebobinar (Ej: ROLL-..., TEL...)...';
                } elseif ($corteSub === 'bolsa') {
                    $scanPh = 'Escanee bobina virgen o impresa (RB-..., ROLL-..., TEL...)...';
                } else {
                    $scanPh = 'Escanee código de bobina TEL virgen o elija de Bodega 3000...';
                }
                ?>

                <input type="text" name="scan_rebo_code" id="scan_rebo_code" autofocus
                       placeholder="<?=htmlspecialchars($scanPh)?>"
                       style="flex:1;min-width:280px;padding:9px 14px;font-size:15px;border:1px solid #2980B9;border-radius:4px;font-weight:bold;background:#FFFFFF"
                       onkeydown="if(event.key === 'Enter'){ this.form.submit(); }">
                <button type="submit" class="btngreen" style="padding:9px 18px;font-size:14px;font-weight:bold;cursor:pointer;border:none;border-radius:4px">
                    <i class="fa fa-search"></i> Escanear / Cargar
                </button>
            </form>
        </div>

        <?php if (!empty($_GET['scan_ok'])): ?>
            <div style="background:#D4EFDF;border:1px solid #27AE60;color:#145A32;padding:12px 16px;border-radius:6px;margin-bottom:15px;font-size:14px">
                <i class="fa fa-check-circle"></i> <b>Bobina escaneada con éxito:</b> '<?=htmlspecialchars($_GET['scanned_code'] ?? '')?>' cargada en ENTRADA REBOBINADORA con trazabilidad activa.
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['scan_err'])): ?>
            <div style="background:#FADBD8;border:1px solid #E74C3C;color:#78281F;padding:12px 16px;border-radius:6px;margin-bottom:15px;font-size:14px">
                <i class="fa fa-exclamation-triangle"></i> <b>Error al escanear bobina:</b> <?=htmlspecialchars($_GET['scan_err'])?>
            </div>
        <?php endif; ?>

        <?php if ($task === 'empalme'): ?>
            <?php
            // Cálculos para Empalme (Unión de Bobina 1 y Bobina 2)
            $rawIngresoKg1 = isset($_REQUEST["ingresokgamt_1"]) ? trim((string)$_REQUEST["ingresokgamt_1"]) : '';
            if ($rawIngresoKg1 === '' && $reboItemSel1 !== null) {
                $rawIngresoKg1 = number_format((float)($reboItemSel1['item_reg_kg'] ?? 0), 2, ',', '.');
            }
            $ingresoKg1 = (float)str_replace(',', '.', $rawIngresoKg1);
            $rawSobrante1 = isset($_REQUEST["sobranteamt_1"]) ? trim((string)$_REQUEST["sobranteamt_1"]) : '0';
            $sobranteKg1 = (float)str_replace(',', '.', $rawSobrante1);
            $useamt1 = max(0.0, $ingresoKg1 - $sobranteKg1);
            $baseRegKg1 = (float)($reboItemSel1['item_reg_kg'] ?? 1);
            if ($baseRegKg1 <= 0) $baseRegKg1 = 1.0;
            $largo1 = (float)($reboItemSel1['item_reg_length'] ?? 0) - ((($baseRegKg1 - $useamt1) * (float)($reboItemSel1['item_reg_length'] ?? 0)) / $baseRegKg1);

            $rawIngresoKg2 = isset($_REQUEST["ingresokgamt_2"]) ? trim((string)$_REQUEST["ingresokgamt_2"]) : '';
            if ($rawIngresoKg2 === '' && $reboItemSel2 !== null) {
                $rawIngresoKg2 = number_format((float)($reboItemSel2['item_reg_kg'] ?? 0), 2, ',', '.');
            }
            $ingresoKg2 = (float)str_replace(',', '.', $rawIngresoKg2);
            $rawSobrante2 = isset($_REQUEST["sobranteamt_2"]) ? trim((string)$_REQUEST["sobranteamt_2"]) : '0';
            $sobranteKg2 = (float)str_replace(',', '.', $rawSobrante2);
            $useamt2 = max(0.0, $ingresoKg2 - $sobranteKg2);
            $baseRegKg2 = (float)($reboItemSel2['item_reg_kg'] ?? 1);
            if ($baseRegKg2 <= 0) $baseRegKg2 = 1.0;
            $largo2 = (float)($reboItemSel2['item_reg_length'] ?? 0) - ((($baseRegKg2 - $useamt2) * (float)($reboItemSel2['item_reg_length'] ?? 0)) / $baseRegKg2);

            $rawMerma = isset($_REQUEST['itemmermakg']) ? trim((string)$_REQUEST['itemmermakg']) : '0';
            $itemMermaKg = (float)str_replace(',', '.', $rawMerma);
            $totalUsedKg = $useamt1 + $useamt2;
            $salidaEmpalmeKg = max(0.0, $totalUsedKg - $itemMermaKg);
            $salidaLargoTotal = max(0.0, $largo1 + $largo2);
            $bothLoaded = ($reboItemSel1 !== null && $reboItemSel2 !== null);
            ?>

            <script language="Javascript">
            function roundToDecimal(num, decimalPlaces) {
                const factor = Math.pow(10, decimalPlaces);
                return Math.round(num * factor) / factor;
            }
            function recalcEmpalme() {
                var in1El = document.getElementById('ingresokgamt_1');
                var sob1El = document.getElementById('sobranteamt_1');
                var use1El = document.getElementById('useamt_1');
                var in2El = document.getElementById('ingresokgamt_2');
                var sob2El = document.getElementById('sobranteamt_2');
                var use2El = document.getElementById('useamt_2');
                var mermaEl = document.getElementById('itemmermakg');
                var outEl = document.getElementById('empalme_output_kg');

                var in1 = in1El ? parseFloat(in1El.value.replace(/,/g, '.')) || 0 : 0;
                var sob1 = sob1El ? parseFloat(sob1El.value.replace(/,/g, '.')) || 0 : 0;
                var u1 = Math.max(0, in1 - sob1);
                if (use1El) use1El.value = roundToDecimal(u1, 2).toFixed(2).replace(/\./g, ',');

                var in2 = in2El ? parseFloat(in2El.value.replace(/,/g, '.')) || 0 : 0;
                var sob2 = sob2El ? parseFloat(sob2El.value.replace(/,/g, '.')) || 0 : 0;
                var u2 = Math.max(0, in2 - sob2);
                if (use2El) use2El.value = roundToDecimal(u2, 2).toFixed(2).replace(/\./g, ',');

                var merma = mermaEl ? parseFloat(mermaEl.value.replace(/,/g, '.')) || 0 : 0;
                var netOut = Math.max(0, (u1 + u2) - merma);
                if (outEl) outEl.textContent = roundToDecimal(netOut, 2).toFixed(2).replace(/\./g, ',') + ' Kg';

                var btnTerm = document.getElementById('idx_btn_term');
                var btnSave = document.getElementById('idx_btn_save');
                if (btnTerm) btnTerm.style.display = 'none';
                if (btnSave) btnSave.style.display = 'inline-block';
            }
            </script>

            <form action="/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod#xform_itemcalc" method="post" name="xform_itemcalc" id="xform_itemcalc">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="mode" value="reboprod">
                <input type="hidden" name="task" value="empalme">
                <input type="hidden" name="is_empalme" value="1">
                <input type="hidden" name="req_rebo_type" value="Empalme">
                <input type="hidden" name="selitemid1" value="<?=$selItemId1?>">
                <input type="hidden" name="selitemid2" value="<?=$selItemId2?>">
                <input type="hidden" name="scanned_roll_code_1" value="<?=htmlspecialchars((string)($reboItemSel1['item_number_prod'] ?? ''))?>">
                <input type="hidden" name="scanned_roll_id_1" value="<?=(int)($_REQUEST['roll_id'] ?? 0)?>">
                <input type="hidden" name="scanned_roll_code_2" value="<?=htmlspecialchars((string)($reboItemSel2['item_number_prod'] ?? ''))?>">
                <input type="hidden" name="scanned_roll_id_2" value="<?=(int)($_REQUEST['roll_id_2'] ?? 0)?>">
                <input type="hidden" name="rebocalc" value="1">
                <input type="hidden" name="rebofinalize" value="0">

                <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
                <tr>
                    <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:0px">
                        <br>
                        <table border="0" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td colspan="2" style="background-color:#0E6251;color:#FFFFFF;padding:8px 12px;font-weight:bold;text-align:center;font-size:14px">
                                <i class="fa fa-link"></i> ENTRADA REBOBINADORA: BOBINAS A EMPALMAR (CONSUMO EN BODEGA 3000)
                            </td>
                        </tr>
                        </table>
                        <table border="0" width="100%" cellpadding="6" cellspacing="0">
                        <tr>
                            <td class="tdheader" width="90" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Slot</td>
                            <td class="tdheader" width="120" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Código</td>
                            <td align="center" class="tdheader" width="90" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Material</td>
                            <td align="left" class="tdheader" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Color</td>
                            <td align="center" class="tdheader" width="80" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Gramaje</td>
                            <td align="center" class="tdheader" width="80" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Ancho</td>
                            <td align="center" class="tdheader" width="80" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Largo</td>
                            <td align="center" class="tdheader" width="95" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Pesa</td>
                            <td align="center" class="tdheader" width="95" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Sobrante</td>
                            <td align="center" class="tdheader" width="95" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Usado</td>
                            <td align="center" class="tdheader" width="80" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Acción</td>
                        </tr>

                        <!-- BOBINA 1 -->
                        <?php if ($reboItemSel1 !== null): ?>
                            <tr>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;">
                                    <span style="background:#0E6251;color:#FFFFFF;padding:3px 8px;border-radius:3px;font-weight:bold;font-size:11px">Bobina 1</span>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;"><b><?=htmlspecialchars((string)$reboItemSel1['item_number_prod'])?></b></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=htmlspecialchars((string)$reboItemSel1['material'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="left"><?=htmlspecialchars((string)$reboItemSel1['colordesc'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($reboItemSel1['item_reg_gsm'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($reboItemSel1['item_reg_width'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($largo1)?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                    <input type="text" class="inptxt" id="ingresokgamt_1" name="ingresokgamt_1"
                                    style="width:100%;text-align:center" onkeyup="recalcEmpalme()"
                                    value="<?=number_format($ingresoKg1, 2, ',', '.')?>">
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                    <input type="text" class="inptxt" id="sobranteamt_1" name="sobranteamt_1"
                                    style="width:100%;text-align:center" onkeyup="recalcEmpalme()"
                                    value="<?=number_format($sobranteKg1, 2, ',', '.')?>">
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                    <input type="text" class="inptxt" id="useamt_1" name="useamt_1"
                                    style="width:100%;text-align:center;background-color:#EEEEEE" readonly
                                    value="<?=number_format($useamt1, 2, ',', '.')?>">
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                    <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod&task=empalme&removeslot=1&selitemid1=<?=$selItemId1?>&selitemid2=<?=$selItemId2?>"
                                       style="color:#C0392B;font-weight:bold;text-decoration:none" title="Quitar bobina 1">
                                        <i class="fa fa-times-circle"></i> Quitar
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr style="background:#FEF9E7">
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;">
                                    <span style="background:#BDC3C7;color:#2C3E50;padding:3px 8px;border-radius:3px;font-weight:bold;font-size:11px">Bobina 1</span>
                                </td>
                                <td colspan="10" class="tdnrm" style="border-left:1px solid #DDDDDD;color:#7D6608;padding:10px 12px">
                                    <i class="fa fa-info-circle"></i> <b>Bobina 1 no cargada.</b> Escanee el código en el lector superior (opción Bobina 1) o selecciónela con <b>"+ Bobina 1"</b> en la lista inferior.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <!-- BOBINA 2 -->
                        <?php if ($reboItemSel2 !== null): ?>
                            <tr>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;">
                                    <span style="background:#0E6251;color:#FFFFFF;padding:3px 8px;border-radius:3px;font-weight:bold;font-size:11px">Bobina 2</span>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;"><b><?=htmlspecialchars((string)$reboItemSel2['item_number_prod'])?></b></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=htmlspecialchars((string)$reboItemSel2['material'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="left"><?=htmlspecialchars((string)$reboItemSel2['colordesc'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($reboItemSel2['item_reg_gsm'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($reboItemSel2['item_reg_width'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($largo2)?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                    <input type="text" class="inptxt" id="ingresokgamt_2" name="ingresokgamt_2"
                                    style="width:100%;text-align:center" onkeyup="recalcEmpalme()"
                                    value="<?=number_format($ingresoKg2, 2, ',', '.')?>">
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                    <input type="text" class="inptxt" id="sobranteamt_2" name="sobranteamt_2"
                                    style="width:100%;text-align:center" onkeyup="recalcEmpalme()"
                                    value="<?=number_format($sobranteKg2, 2, ',', '.')?>">
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                    <input type="text" class="inptxt" id="useamt_2" name="useamt_2"
                                    style="width:100%;text-align:center;background-color:#EEEEEE" readonly
                                    value="<?=number_format($useamt2, 2, ',', '.')?>">
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                    <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod&task=empalme&removeslot=2&selitemid1=<?=$selItemId1?>&selitemid2=<?=$selItemId2?>"
                                       style="color:#C0392B;font-weight:bold;text-decoration:none" title="Quitar bobina 2">
                                        <i class="fa fa-times-circle"></i> Quitar
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr style="background:#FEF9E7">
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;">
                                    <span style="background:#BDC3C7;color:#2C3E50;padding:3px 8px;border-radius:3px;font-weight:bold;font-size:11px">Bobina 2</span>
                                </td>
                                <td colspan="10" class="tdnrm" style="border-left:1px solid #DDDDDD;color:#7D6608;padding:10px 12px">
                                    <i class="fa fa-info-circle"></i> <b>Bobina 2 no cargada.</b> Escanee el código en el lector superior (opción Bobina 2) o selecciónela con <b>"+ Bobina 2"</b> en la lista inferior.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </table>

                        <table border="0" cellpadding="6" cellspacing="0" style="margin-top:8px">
                        <colgroup>
                            <col width="110">
                            <col width="110">
                            <col width="120">
                        </colgroup>
                        <tr>
                            <td width="110" style="font-weight:bold;color:#2C3E50">Merma Empalme:</td>
                            <td width="110">
                                <input type="text" class="inptxt" name="itemmermakg" id="itemmermakg" style="width:100%;text-align:center"
                                onkeyup="recalcEmpalme();"
                                value="<?=$itemMermaKg > 0 ? number_format($itemMermaKg, 2, ',', '.') : ''?>"
                                placeholder="0,00 Kg">
                            </td>
                            <td>
                                <input type="button" class="btngrey" value="Calcular" style="padding:6px 14px;border:0px;font-size:14px;cursor:pointer"
                                onclick="document.xform_itemcalc.rebocalc.value='1'; document.xform_itemcalc.rebofinalize.value='0'; document.xform_itemcalc.submit();">
                            </td>
                        </tr>
                        </table>

                        <!-- SALIDA EMPALME -->
                        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-top:14px">
                        <tr>
                            <td colspan="2" style="background-color:#0E6251;color:#FFFFFF;padding:8px 12px;font-weight:bold;text-align:center;font-size:14px">
                                <i class="fa fa-link"></i> SALIDA REBOBINADORA: NUEVA BOBINA EMPALMADA RESULTANTE (ENTRADA A BODEGA 3000)
                            </td>
                        </tr>
                        </table>

                        <?php if ($bothLoaded): ?>
                            <table border="0" width="100%" cellpadding="6" cellspacing="0">
                            <tr>
                                <td class="tdheader" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Código Resultante</td>
                                <td align="center" class="tdheader" width="90" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Material</td>
                                <td align="left" class="tdheader" width="140" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Color</td>
                                <td align="center" class="tdheader" width="80" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Gramaje</td>
                                <td align="center" class="tdheader" width="80" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Ancho</td>
                                <td align="center" class="tdheader" width="120" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Salida Neto</td>
                                <td align="center" class="tdheader" width="90" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Merma</td>
                                <td align="center" class="tdheader" width="90" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Largo Total</td>
                                <td align="center" class="tdheader" width="160" style="color: white;background-color: #117864;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Destino</td>
                            </tr>
                            <tr>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;">
                                    <b style="color:#0E6251">[SE ASIGNARÁ ROLL-EMP-XXXXX Y CÓDIGO ERP]</b>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=htmlspecialchars((string)$reboItemSel1['material'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="left"><?=htmlspecialchars((string)$reboItemSel1['colordesc'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($reboItemSel1['item_reg_gsm'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($reboItemSel1['item_reg_width'])?> cm</td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                    <b id="empalme_output_kg" style="font-size:15px;color:#0E6251"><?=number_format($salidaEmpalmeKg, 2, ',', '.')?> Kg</b>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=number_format($itemMermaKg, 2, ',', '.')?> Kg</td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($salidaLargoTotal)?> m</td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                    <span style="background:#D4EFDF;color:#145A32;padding:4px 8px;border-radius:3px;font-weight:bold;font-size:11px">
                                        <i class="fa fa-warehouse"></i> Bodega 3000
                                    </span>
                                </td>
                            </tr>
                            </table>

                            <table border="0" cellpadding="6" cellspacing="0" style="margin-top:10px">
                            <tr>
                                <td>
                                    <input type="button" class="btngreen" id="idx_btn_term" value="Finalizar Empalme"
                                    style="padding:9px 22px;border:0px;font-size:15px;font-weight:bold;cursor:pointer;border-radius:4px"
                                    onclick="openReboFinalizeModal('empalme');">

                                    <input type="button" class="btngrey" id="idx_btn_save" value="Recalcular"
                                    style="display:none;padding:9px 18px;border:0px;font-size:14px;cursor:pointer"
                                    onclick="document.xform_itemcalc.rebocalc.value='1'; document.xform_itemcalc.rebofinalize.value='0'; document.xform_itemcalc.submit();">
                                </td>
                            </tr>
                            </table>
                        <?php else: ?>
                            <div style="padding:15px 18px;background:#FADBD8;color:#78281F;border:1px solid #F5B7B1;border-radius:4px;margin:10px 15px 15px 15px;font-size:13.5px">
                                <i class="fa fa-exclamation-triangle"></i> <b>Faltan bobinas para el empalme:</b> Cargue tanto la <b>Bobina 1</b> como la <b>Bobina 2</b> para poder unir y generar la nueva bobina resultante.
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                </table>
        <?php elseif ($task === 'rebobinado'): ?>
            <!-- FORMULARIO / VISTA DE REBOBINADO -->
            <?php if ($itemsel !== null): ?>
                <?php
                $rawIngresoKg = isset($_REQUEST["ingresokgamt_1"]) ? trim((string)$_REQUEST["ingresokgamt_1"]) : '';
                if ($rawIngresoKg === '') {
                    $rawIngresoKg = number_format((float)($itemsel['item_reg_kg'] ?? 0), 2, ',', '.');
                }
                $ingresoKg = (float)str_replace(',', '.', $rawIngresoKg);
                $rawMerma = isset($_REQUEST['itemmermakg']) ? trim((string)$_REQUEST['itemmermakg']) : '0';
                $itemMermaKg = (float)str_replace(',', '.', $rawMerma);
                $finalKg = max(0.0, $ingresoKg - $itemMermaKg);
                ?>
                <form action="/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod#xform_itemcalc" method="post" name="xform_itemcalc" id="xform_itemcalc">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="mode" value="reboprod">
                    <input type="hidden" name="task" value="rebobinado">
                    <input type="hidden" name="selitemid1" value="<?=$selItemId1?>">
                    <input type="hidden" name="scanned_roll_id" value="<?=(int)($_REQUEST['roll_id'] ?? 0)?>">
                    <input type="hidden" name="scanned_roll_code" value="<?=htmlspecialchars((string)($_REQUEST['scanned_code'] ?? ($itemsel['item_number_prod'] ?? '')))?>">
                    <input type="hidden" name="req_rebo_type" value="Rebobinado">
                    <input type="hidden" name="rebocalc" value="1">
                    <input type="hidden" name="rebofinalize" value="0">

                    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
                    <tr>
                        <td style="background-color:#FFFFFF;border:1px solid #D5D8DC;border-radius:6px;overflow:hidden">
                            <table border="0" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="background-color:#D35400;color:#FFFFFF;padding:10px 14px;font-weight:bold;font-size:14px">
                                    <i class="fa fa-sync-alt"></i> ENTRADA REBOBINADORA: BOBINA A REBOBINAR
                                </td>
                            </tr>
                            </table>
                            <table border="0" width="100%" cellpadding="8" cellspacing="0">
                            <tr>
                                <td class="tdheader" width="130" style="color:white;background-color:#2C3E50;border-left:1px solid #4A6572;border-top:1px solid #4A6572">Código / SKU</td>
                                <td align="left" class="tdheader" style="color:white;background-color:#2C3E50;border-left:1px solid #4A6572;border-top:1px solid #4A6572">Descripción</td>
                                <td align="center" class="tdheader" width="100" style="color:white;background-color:#2C3E50;border-left:1px solid #4A6572;border-top:1px solid #4A6572">Material</td>
                                <td align="left" class="tdheader" width="120" style="color:white;background-color:#2C3E50;border-left:1px solid #4A6572;border-top:1px solid #4A6572">Color</td>
                                <td align="center" class="tdheader" width="80" style="color:white;background-color:#2C3E50;border-left:1px solid #4A6572;border-top:1px solid #4A6572">Gramaje</td>
                                <td align="center" class="tdheader" width="85" style="color:white;background-color:#2C3E50;border-left:1px solid #4A6572;border-top:1px solid #4A6572">Ancho</td>
                                <td align="center" class="tdheader" width="110" style="color:white;background-color:#2C3E50;border-left:1px solid #4A6572;border-top:1px solid #4A6572">Kg Entrada</td>
                                <td align="center" class="tdheader" width="90" style="color:white;background-color:#2C3E50;border-left:1px solid #4A6572;border-top:1px solid #4A6572">Acción</td>
                            </tr>
                            <tr>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD"><b><?=htmlspecialchars((string)$itemsel['item_number_prod'])?></b></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=htmlspecialchars((string)$itemsel['item_title'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center"><?=htmlspecialchars((string)$itemsel['material'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="left"><?=htmlspecialchars((string)$itemsel['colordesc'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center"><?=$fmt($itemsel['item_reg_gsm'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center"><?=$fmt($itemsel['item_reg_width'])?> cm</td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">
                                    <input type="text" class="inptxt" id="ingresokgamt_1" name="ingresokgamt_1"
                                           style="width:85px;text-align:center;font-weight:bold"
                                           value="<?=number_format($ingresoKg, 2, ',', '.')?>"> Kg
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">
                                    <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod&task=rebobinado&removeslot=1"
                                       class="btngrey" style="padding:4px 8px;font-size:11px;text-decoration:none;border-radius:3px">
                                        <i class="fa fa-times"></i> Quitar
                                    </a>
                                </td>
                            </tr>
                            </table>
                        </td>
                    </tr>
                    </table>

                    <!-- SALIDA DE REBOBINADO -->
                    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
                    <tr>
                        <td style="background-color:#FFFFFF;border:1px solid #D5D8DC;border-radius:6px;overflow:hidden">
                            <table border="0" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="background-color:#B9770E;color:#FFFFFF;padding:10px 14px;font-weight:bold;font-size:14px">
                                    <i class="fa fa-sync-alt"></i> SALIDA REBOBINADORA: REGISTRO DE BOBINA REBOBINADA
                                </td>
                            </tr>
                            </table>
                            <div style="padding:16px 20px;display:flex;flex-wrap:wrap;gap:20px;align-items:flex-end">
                                <div>
                                    <label style="display:block;font-size:12px;font-weight:bold;color:#475569;margin-bottom:4px">Kg Rebobinados (Pesa Final):</label>
                                    <input type="text" class="inptxt" id="useamt_1" name="useamt_1"
                                           value="<?=number_format($finalKg, 2, ',', '.')?>"
                                           style="width:130px;padding:7px 10px;font-size:14px;font-weight:bold;text-align:center"> Kg
                                </div>
                                <div>
                                    <label style="display:block;font-size:12px;font-weight:bold;color:#475569;margin-bottom:4px">Merma / Despunte (Kg):</label>
                                    <input type="text" class="inptxt" id="itemmermakg" name="itemmermakg" value="<?=number_format($itemMermaKg, 2, ',', '.')?>"
                                           style="width:100px;padding:7px 10px;font-size:14px;text-align:center"> Kg
                                </div>
                                <div style="flex:1;min-width:260px">
                                    <label style="display:block;font-size:12px;font-weight:bold;color:#475569;margin-bottom:4px">Motivo / Observaciones del Rebobinado:</label>
                                    <input type="text" class="inptxt" name="rebo_comments" value="Ajuste de tensión / corrección de rollo"
                                           style="width:100%;padding:7px 10px;font-size:13px">
                                </div>
                                <div>
                                    <button type="button" class="btngreen" style="padding:9px 22px;font-size:14px;font-weight:bold;cursor:pointer;border:none;border-radius:4px"
                                            onclick="openReboFinalizeModal('rebobinado');">
                                        <i class="fa fa-sync-alt"></i> Registrar Rebobinado
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    </table>
                </form>
            <?php else: ?>
                <!-- REBOBINADO: NINGUNA BOBINA CARGADA -->
                <div style="background:#FFFFFF;border:2px dashed #D35400;border-radius:8px;padding:34px 22px;text-align:center;margin-bottom:18px;box-shadow:0 2px 6px rgba(0,0,0,0.03)">
                    <div style="display:inline-flex;align-items:center;justify-content:center;width:60px;height:60px;border-radius:50%;background:#FDF2E9;color:#D35400;font-size:26px;margin-bottom:12px">
                        <i class="fa fa-sync-alt"></i>
                    </div>
                    <div style="font-size:17px;font-weight:bold;color:#2C3E50;margin-bottom:6px">
                        Esperando Selección o Escaneo de Bobina a Rebobinar
                    </div>
                    <div style="font-size:13.5px;color:#5D6D7E;max-width:620px;margin:0 auto 12px auto;line-height:1.5">
                        Escanee el código de trazabilidad o código de barra de la bobina con la pistola lectora, o selecciónela directamente de la lista inferior de <b>Bodega 3000</b> para indicar qué bobina se rebobina.
                    </div>
                    <span style="display:inline-flex;align-items:center;gap:6px;background:#FDF2E9;color:#A04000;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:bold">
                        <i class="fa fa-info-circle"></i> Solo registra la acción de rebobinado sin alterar consumo oficial ni stock
                    </span>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- FORMULARIO / VISTA DE CORTE (task === 'corte') -->
            <?php if ($itemsel !== null): ?>
            <?php
            // Cálculos de entrada para Corte (Cuerpos de Bolsa / Solo Manillas)
            $rawIngresoKg = isset($_REQUEST["ingresokgamt_{$lidx}"]) ? trim((string)$_REQUEST["ingresokgamt_{$lidx}"]) : '';
            if ($rawIngresoKg === '') {
                $rawIngresoKg = number_format((float)($itemsel['item_reg_kg'] ?? 0), 2, ',', '.');
            }
            $ingresoKg = (float)str_replace(',', '.', $rawIngresoKg);
            $rawSobrante = isset($_REQUEST["sobranteamt_{$lidx}"]) ? trim((string)$_REQUEST["sobranteamt_{$lidx}"]) : '0';
            $sobranteKg = (float)str_replace(',', '.', $rawSobrante);
            $useamt = max(0.0, $ingresoKg - $sobranteKg);
            $origusediffamt = (float)($itemsel['item_reg_kg'] ?? 0) - $useamt;
            $baseRegKg = (float)($itemsel['item_reg_kg'] ?? 1);
            if ($baseRegKg <= 0) $baseRegKg = 1.0;
            $baseLength = (float)($itemsel['item_reg_length'] ?? 0) - (($origusediffamt * (float)($itemsel['item_reg_length'] ?? 0)) / $baseRegKg);

            $rawMerma = isset($_REQUEST['itemmermakg']) ? trim((string)$_REQUEST['itemmermakg']) : '0';
            $itemMermaKg = (float)str_replace(',', '.', $rawMerma);
            $isReboCalc = !empty($_REQUEST['rebocalc']);
            $rollWidth = (float)($itemsel['item_reg_width'] ?? 100);
            if ($rollWidth <= 0) $rollWidth = 100.0;
            ?>

            <script language="Javascript">
            function liveUpdateCutWidth() {
                var rollWidth = <?=json_encode((float)$rollWidth)?>;
                var totalW = 0;
                var allAmtInputs = document.querySelectorAll('input[name^="reboamt_"]');
                allAmtInputs.forEach(function(amtEl) {
                    var m = amtEl.name.match(/^reboamt_(\d+)$/);
                    if (m) {
                        var idx = m[1];
                        var dimEl = document.querySelector('input[name="rollo_dims_' + idx + '"]');
                        var amt = parseFloat(amtEl.value.replace(/,/g, '.')) || 0;
                        var dim = dimEl ? (parseFloat(dimEl.value.replace(/,/g, '.')) || 0) : 0;
                        totalW += (amt * dim);
                    }
                });
                totalW = Math.round(totalW * 100) / 100;
                var totalWidthEl = document.getElementById('idx_total_width');
                var statusText = document.getElementById('idx_width_status_text');
                var matches = (Math.abs(totalW - rollWidth) < 0.1);
                if (totalWidthEl) {
                    totalWidthEl.innerText = totalW.toFixed(2).replace('.', ',') + ' cm';
                    totalWidthEl.style.color = matches ? '#0E6251' : '#C0392B';
                }
                if (statusText) {
                    if (matches) {
                        statusText.innerHTML = '<span style="color:#0E6251"><i class="fa fa-check-circle"></i> Ancho distribuido coincide exactamente con el ancho de bobina (' + rollWidth.toFixed(2).replace('.', ',') + ' cm). Listo para finalizar.</span>';
                    } else {
                        statusText.innerHTML = '<span style="color:#C0392B"><i class="fa fa-exclamation-circle"></i> Ancho total sumado (' + totalW.toFixed(2).replace('.', ',') + ' cm) debe coincidir con bobina (' + rollWidth.toFixed(2).replace('.', ',') + ' cm).</span>';
                    }
                }
            }

            function markCutsFormModified(origin) {
                liveUpdateCutWidth();
                var btnTerm = document.getElementById('idx_btn_term');
                var btnSave = document.getElementById('idx_btn_save');
                var statusText = document.getElementById('idx_width_status_text');

                if (btnTerm) {
                    btnTerm.style.display = 'none';
                }
                if (btnSave) {
                    btnSave.style.display = 'inline-flex';
                    btnSave.style.backgroundColor = '#D97706';
                    btnSave.style.color = '#FFFFFF';
                }
                if (statusText) {
                    statusText.style.color = '#D97706';
                    statusText.innerHTML = '<i class="fa fa-sync-alt"></i> Modificaciones pendientes. Presione <b>"Actualizar y Recalcular Distribución"</b> para validar el corte.';
                }
            }

            function submitReboRecalculate() {
                var form = document.getElementById('xform_itemcalc') || document.xform_itemcalc;
                if (!form) return;
                if (form.rebocalc) form.rebocalc.value = '1';
                if (form.rebofinalize) form.rebofinalize.value = '0';
                form.submit();
            }

            function recalcUsedAmt(xlineid) {
                var inEl = document.getElementById('ingresokgamt_' + xlineid);
                var sobEl = document.getElementById('sobranteamt_' + xlineid);
                var useEl = document.getElementById('useamt_' + xlineid);
                if (!inEl || !sobEl || !useEl) return;

                var inVal = parseFloat(inEl.value.replace(/,/g, '.')) || 0;
                var sobVal = parseFloat(sobEl.value.replace(/,/g, '.')) || 0;
                var useVal = Math.max(0, inVal - sobVal);
                useEl.value = roundToDecimal(useVal, 2).toFixed(2).replace(/\./g, ',');
                markCutsFormModified('useamt_' + xlineid);
            }

            function roundToDecimal(num, decimalPlaces) {
                const factor = Math.pow(10, decimalPlaces);
                return Math.round(num * factor) / factor;
            }

            function recalcTotalWidth() {
                markCutsFormModified('total_width');
            }

            function updateDestinoRow(idx) {
                var sel = document.getElementById('rebo_destino_' + idx);
                var badge = document.getElementById('badge_dest_' + idx);
                var mermaWrap = document.getElementById('merma_manillas_wrap_' + idx);
                var mermaInp = document.getElementById('manillas_merma_amt_' + idx);
                if (!sel) return;
                var val = sel.value;
                if (badge) {
                    if (val === 'merma') {
                        badge.style.background = '#C0392B';
                        badge.innerHTML = '<i class="fa fa-trash"></i> MERMA / REFILE (100%)';
                    } else if (val === 'manilla') {
                        badge.style.background = '#1B4F72';
                        badge.innerHTML = '<i class="fa fa-cut"></i> MANILLAS (ROLL-MAN-)';
                    } else {
                        badge.style.background = '#6C3483';
                        badge.innerHTML = '<i class="fa fa-shopping-bag"></i> CUERPO BOLSA (ROLL-BOL-)';
                    }
                }
                if (mermaWrap) {
                    mermaWrap.style.display = (val === 'manilla') ? 'flex' : 'none';
                }
                if (mermaInp && val !== 'manilla') {
                    mermaInp.value = '0';
                }
                markCutsFormModified('destino_' + idx);
            }

            function addReboCutRow() {
                var highestIdx = -1;
                var allAmtInputs = document.querySelectorAll('input[name^="reboamt_"]');
                allAmtInputs.forEach(function(el) {
                    var m = el.name.match(/^reboamt_(\d+)$/);
                    if (m) {
                        var n = parseInt(m[1], 10);
                        if (n > highestIdx) highestIdx = n;
                    }
                });
                var nextIdx = highestIdx + 1;

                var rollWidth = <?=json_encode((float)$rollWidth)?>;
                var currentSum = 0;
                allAmtInputs.forEach(function(el) {
                    var m = el.name.match(/^reboamt_(\d+)$/);
                    if (m) {
                        var idx = m[1];
                        var dimEl = document.querySelector('input[name="rollo_dims_' + idx + '"]');
                        var a = parseFloat(el.value.replace(/,/g, '.')) || 0;
                        var d = dimEl ? (parseFloat(dimEl.value.replace(/,/g, '.')) || 0) : 0;
                        currentSum += (a * d);
                    }
                });
                var remainingWidth = Math.max(0.5, Math.round((rollWidth - currentSum) * 100) / 100);
                if (remainingWidth <= 0) remainingWidth = 2.0;

                var tbody = document.getElementById('table_cuts_rows');
                if (!tbody) return;

                var tr = document.createElement('tr');
                tr.id = 'cut_row_' + nextIdx;
                tr.style.borderBottom = '1px solid #EEEEEE';
                tr.innerHTML = '<td class="tdnrm" style="border-left:1px solid #DDDDDD;">'
                    + '<input type="text" class="inptxt clsreboamt" style="width:100%;text-align:center" name="reboamt_' + nextIdx + '" value="1" oninput="markCutsFormModified(\'reboamt_' + nextIdx + '\');">'
                    + '</td>'
                    + '<td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="left">'
                    + '<select name="rebo_destino_' + nextIdx + '" id="rebo_destino_' + nextIdx + '" style="width:100%;font-size:12px;font-weight:bold;padding:5px 8px;border:1px solid #BDC3C7;border-radius:4px;background:#FFFFFF" onchange="updateDestinoRow(' + nextIdx + ');">'
                    + '<option value="merma" selected>🗑️ 100% Merma / Refile</option>'
                    + '<option value="manilla">🏷️ Manilla</option>'
                    + '<option value="bolsa">📦 Cuerpo de Bolsa</option>'
                    + '</select>'
                    + '</td>'
                    + '<td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="left">'
                    + '<span id="badge_dest_' + nextIdx + '" style="background:#C0392B;color:#FFFFFF;padding:4px 8px;border-radius:4px;font-size:11px;font-weight:bold;white-space:nowrap;display:inline-flex;align-items:center;gap:4px">'
                    + '<i class="fa fa-trash"></i> MERMA / REFILE (100%)'
                    + '</span>'
                    + '</td>'
                    + '<td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=htmlspecialchars((string)$itemsel['material'])?></td>'
                    + '<td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="left"><?=htmlspecialchars((string)$itemsel['colordesc'])?></td>'
                    + '<td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($itemsel['item_reg_gsm'])?></td>'
                    + '<td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><span style="color:#64748B;font-size:11px"><i class="fa fa-sync-alt"></i> (Recalcular)</span></td>'
                    + '<td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">'
                    + '<input type="text" class="inptxt" id="rollo_dims_' + nextIdx + '" name="rollo_dims_' + nextIdx + '" style="width:100%;text-align:center" oninput="markCutsFormModified(\'rollo_dims_' + nextIdx + '\');" value="' + remainingWidth.toFixed(2).replace('.', ',') + '">'
                    + '</td>'
                    + '<td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><b>(Recalcular)</b></td>'
                    + '<td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">0,00</td>'
                    + '<td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">'
                    + '<div style="font-weight:bold;color:#C0392B">100% Merma</div>'
                    + '</td>'
                    + '<td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">'
                    + '<div id="merma_manillas_wrap_' + nextIdx + '" style="display:none;align-items:center;justify-content:center;gap:4px">'
                    + '<input type="number" min="0" max="1" step="1" name="manillas_merma_amt_' + nextIdx + '" id="manillas_merma_amt_' + nextIdx + '" value="0" class="inptxt" style="width:65px;text-align:center;padding:4px;border-radius:4px" oninput="markCutsFormModified(\'manillas_merma_' + nextIdx + '\');">'
                    + '<span style="font-size:11px;color:#64748B">unid</span>'
                    + '</div>'
                    + '<span id="merma_dash_' + nextIdx + '" style="color:#94A3B8;font-size:13px">&mdash;</span>'
                    + '</td>'
                    + '<td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">-</td>'
                    + '<td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">'
                    + '<button type="button" class="btngrey" style="padding:3px 7px;font-size:11px;color:#C0392B;border-radius:3px;cursor:pointer" onclick="removeReboCutRow(' + nextIdx + ');" title="Eliminar esta fila">'
                    + '<i class="fa fa-trash"></i>'
                    + '</button>'
                    + '</td>';
                tbody.appendChild(tr);
                markCutsFormModified('add_row');
            }

            function removeReboCutRow(idx) {
                var tr = document.getElementById('cut_row_' + idx);
                if (tr) {
                    tr.remove();
                    markCutsFormModified('remove_row');
                }
            }

            function attachCutsFormListeners() {
                var form = document.getElementById('xform_itemcalc');
                if (!form) return;
                var inputs = form.querySelectorAll('input[type="text"], input[type="number"], select');
                inputs.forEach(function(el) {
                    if (el.type === 'hidden' || el.name === 'rebocalc' || el.name === 'rebofinalize') return;
                    el.addEventListener('input', function() { markCutsFormModified(el.name); });
                    el.addEventListener('change', function() { markCutsFormModified(el.name); });
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', attachCutsFormListeners);
            } else {
                attachCutsFormListeners();
            }
            </script>

            <form action="/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod#xform_itemcalc" method="post" name="xform_itemcalc" id="xform_itemcalc">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="mode" value="reboprod">
                <input type="hidden" name="task" value="corte">
                <input type="hidden" name="corte_sub" value="<?=htmlspecialchars($corteSub)?>">
                <input type="hidden" name="selitemid1" value="<?=$selItemId1?>">
                <input type="hidden" name="selitemid2" value="<?=$selItemId2?>">
                <input type="hidden" name="scanned_roll_id" value="<?=(int)($_REQUEST['roll_id'] ?? 0)?>">
                <input type="hidden" name="scanned_roll_code" value="<?=htmlspecialchars((string)($_REQUEST['scanned_code'] ?? ($itemsel['item_number_prod'] ?? '')))?>">
                <input type="hidden" name="req_rebo_type" value="Corte">
                <input type="hidden" name="rebocalc" value="1">
                <input type="hidden" name="rebofinalize" value="0">

                <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
                <tr>
                    <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:0px">
                        <br>
                        <table border="0" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td colspan="2" style="background-color:#1AAAA4;color:#FFFFFF;padding:8px 12px;font-weight:bold;text-align:center;font-size:14px">
                                <i class="fa fa-cut"></i> ENTRADA REBOBINADORA: BOBINA MADRE A CORTAR (CONSUMO EN BODEGA 3000)
                            </td>
                        </tr>
                        </table>
                        <table border="0" width="100%" cellpadding="6" cellspacing="0">
                        <tr>
                            <td class="tdheader" width="110" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Código</td>
                            <td align="center" class="tdheader" width="90" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Material</td>
                            <td align="left" class="tdheader" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Color</td>
                            <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Gramaje</td>
                            <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Ancho</td>
                            <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Largo</td>
                            <td align="center" class="tdheader" width="95" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Pesa</td>
                            <td align="center" class="tdheader" width="95" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Sobrante</td>
                            <td align="center" class="tdheader" width="95" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Usado</td>
                            <td align="center" class="tdheader" width="80" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Acción</td>
                        </tr>
                        <tr>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;"><b><?=htmlspecialchars((string)$itemsel['item_number_prod'])?></b></td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=htmlspecialchars((string)$itemsel['material'])?></td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="left"><?=htmlspecialchars((string)$itemsel['colordesc'])?></td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($itemsel['item_reg_gsm'])?></td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($itemsel['item_reg_width'])?></td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($baseLength)?></td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                <input type="text" class="inptxt" id="ingresokgamt_<?=$lidx?>" name="ingresokgamt_<?=$lidx?>"
                                style="width:100%;text-align:center" onkeyup="recalcUsedAmt('<?=$lidx?>')"
                                value="<?=number_format($ingresoKg, 2, ',', '.')?>">
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                <input type="text" class="inptxt" id="sobranteamt_<?=$lidx?>" name="sobranteamt_<?=$lidx?>"
                                style="width:100%;text-align:center" onkeyup="recalcUsedAmt('<?=$lidx?>')"
                                value="<?=number_format($sobranteKg, 2, ',', '.')?>">
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                <input type="text" class="inptxt" id="useamt_<?=$lidx?>" name="useamt_<?=$lidx?>"
                                style="width:100%;text-align:center;background-color:#EEEEEE" readonly
                                value="<?=number_format($useamt, 2, ',', '.')?>">
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod&task=corte&corte_sub=<?=htmlspecialchars($corteSub)?>&removeslot=1"
                                   style="color:#C0392B;font-weight:bold;text-decoration:none" title="Quitar bobina">
                                    <i class="fa fa-times-circle"></i> Quitar
                                </a>
                            </td>
                        </tr>
                        </table>

                        <table border="0" cellpadding="6" cellspacing="0" style="margin-top:8px">
                        <colgroup>
                            <col width="110">
                            <col width="110">
                            <col width="140">
                        </colgroup>
                        <tr>
                            <td width="110" style="font-weight:bold;color:#2C3E50">Merma Corte:</td>
                            <td width="110">
                                <input type="text" class="inptxt" name="itemmermakg" id="itemmermakg" style="width:100%;text-align:center"
                                oninput="markCutsFormModified('itemmermakg');"
                                value="<?=$itemMermaKg > 0 ? number_format($itemMermaKg, 2, ',', '.') : ''?>"
                                placeholder="0,00 Kg">
                            </td>
                            <td>
                                <button type="button" class="btngrey" style="padding:6px 14px;border:0px;font-size:13px;cursor:pointer;border-radius:4px;display:inline-flex;align-items:center;gap:6px"
                                onclick="submitReboRecalculate();">
                                    <i class="fa fa-calculator"></i> Calcular
                                </button>
                            </td>
                        </tr>
                        </table>

                        <!-- SALIDA REBOBINADORA: CORTE DE BOBINA (TODOS A BODEGA 3000 CON ETIQUETA INDIVIDUAL) -->
                        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-top:14px">
                        <tr>
                            <td colspan="2" style="background-color:#1AAAA4;color:#FFFFFF;padding:8px 12px;font-weight:bold;text-align:center;font-size:14px">
                                <i class="fa fa-fw fa-cut"></i> SALIDA REBOBINADORA: FRACCIONAMIENTO DE BOBINA (BODEGA 3000 - ETIQUETADO INDIVIDUAL)
                            </td>
                        </tr>
                        </table>

                        <table border="0" width="100%" cellpadding="6" cellspacing="0">
                        <tr>
                            <td class="tdheader" width="65" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Cant.</td>
                            <td class="tdheader" width="155" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Tipo</td>
                            <td class="tdheader" width="165" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Trazabilidad</td>
                            <td align="center" class="tdheader" width="65" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Material</td>
                            <td align="left" class="tdheader" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Color</td>
                            <td align="center" class="tdheader" width="55" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">GSM</td>
                            <td align="center" class="tdheader" width="105" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD" title="Código de artículo que se asignará al rollo según su ancho, color y gramaje">Código SKU</td>
                            <td align="center" class="tdheader" width="70" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Ancho</td>
                            <td align="center" class="tdheader" width="75" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Total</td>
                            <td align="center" class="tdheader" width="70" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Unit.</td>
                            <td align="center" class="tdheader" width="85" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD" title="Distribución de merma de acorde al peso del producto">Kg/Merma</td>
                            <td align="center" class="tdheader" width="115" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD" title="Manillas que se van a merma por bobina sucia o descarte">Merma (Manillas)</td>
                            <td align="center" class="tdheader" width="75" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Largo</td>
                            <td align="center" class="tdheader" width="60" style="color: white;background-color: #0EA9A4;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Acción</td>
                        </tr>
                        <tbody id="table_cuts_rows">
                        <?php
                        $cutsList = [];
                        if ($corteSub === 'manillas') {
                            // En solo manillas, fraccionar directamente como tipo manilla
                            if (!empty($reboValues['rollo'])) {
                                foreach ($reboValues['rollo'] as $rv) {
                                    $cutsList[] = [
                                        'rollo_amt' => (float)($rv['rollo_amt'] ?? 1),
                                        'rollo_dims' => (float)($rv['rollo_dims'] ?? 0),
                                        'destino' => 'manilla',
                                    ];
                                }
                            }
                        } else {
                            if (!empty($reboValues['rollo'])) {
                                foreach ($reboValues['rollo'] as $rv) {
                                    $cutsList[] = [
                                        'rollo_amt' => (float)($rv['rollo_amt'] ?? 1),
                                        'rollo_dims' => (float)($rv['rollo_dims'] ?? 0),
                                        'destino' => (string)($rv['subprod_destino'] ?? ''),
                                    ];
                                }
                            } elseif (!empty($reboValues['metro'])) {
                                foreach ($reboValues['metro'] as $rv) {
                                    $cutsList[] = [
                                        'rollo_amt' => (float)($rv['rollo_amt'] ?? 1),
                                        'rollo_dims' => (float)($rv['rollo_dims'] ?? 0),
                                        'destino' => (string)($rv['subprod_destino'] ?? ''),
                                    ];
                                }
                            }
                        }

                        if (empty($cutsList) && !empty($subproducts)) {
                            foreach ($subproducts as $sp) {
                                $cutsList[] = [
                                    'rollo_amt' => (float)($sp['subprod_amount'] ?? 1),
                                    'rollo_dims' => (float)($sp['subprod_ancho_cm'] ?? 0),
                                    'destino' => (string)($sp['subprod_destino'] ?? ''),
                                ];
                            }
                        }

                        if (empty($cutsList)) {
                            $cutsList[] = [
                                'rollo_amt' => 1,
                                'rollo_dims' => (float)($itemsel['item_reg_width'] ?? 100),
                                'destino' => $corteSub === 'manillas' ? 'manilla' : 'bolsa',
                            ];
                        }

                        // Detectar todas las filas enviadas por el formulario (incluyendo filas agregadas dinámicamente)
                        $submittedIndices = [];
                        foreach ($_REQUEST as $rk => $rv) {
                            if (preg_match('/^reboamt_(\d+)$/', (string)$rk, $rm)) {
                                $submittedIndices[] = (int)$rm[1];
                            }
                        }
                        $submittedIndices = array_unique($submittedIndices);
                        sort($submittedIndices);

                        if (!empty($submittedIndices)) {
                            $cutsList = [];
                            foreach ($submittedIndices as $sIdx) {
                                $cutsList[$sIdx] = [
                                    'rollo_amt' => max(1, (int)($_REQUEST["reboamt_{$sIdx}"] ?? 1)),
                                    'rollo_dims' => (float)str_replace(',', '.', (string)($_REQUEST["rollo_dims_{$sIdx}"] ?? 0)),
                                    'destino' => (string)($_REQUEST["rebo_destino_{$sIdx}"] ?? 'bolsa'),
                                ];
                            }
                        }

                        $rollWidth = (float)($itemsel['item_reg_width'] ?? 100);
                        if ($rollWidth <= 0) $rollWidth = 100.0;

                        $_TOTAL_WIDTH = 0.0;
                        $calculatedCuts = [];

                        foreach ($cutsList as $idxTemp => $rebovalTemp) {
                            $cReqAmt = isset($_REQUEST["reboamt_{$idxTemp}"]) ? max(1, (int)$_REQUEST["reboamt_{$idxTemp}"]) : max(1, (int)$rebovalTemp['rollo_amt']);
                            $cReqDims = isset($_REQUEST["rollo_dims_{$idxTemp}"]) ? (float)str_replace(',', '.', (string)$_REQUEST["rollo_dims_{$idxTemp}"]) : (float)$rebovalTemp['rollo_dims'];
                            $rawDest = mb_strtolower(trim((string)($_REQUEST["rebo_destino_{$idxTemp}"] ?? ($rebovalTemp['destino'] ?? ''))));

                            if ($rawDest === 'merma' || str_contains($rawDest, 'merma') || str_contains($rawDest, 'refile')) {
                                $cDestino = 'merma';
                                $isHandleCut = false;
                                $isMermaCut = true;
                            } elseif ($rawDest === 'bolsa' || $rawDest === '3000' || str_contains($rawDest, 'bolsa')) {
                                $cDestino = 'bolsa';
                                $isHandleCut = false;
                                $isMermaCut = false;
                            } elseif ($rawDest === 'manilla' || $rawDest === 'rebo_handle_pallet' || str_contains($rawDest, 'manilla')) {
                                $cDestino = 'manilla';
                                $isHandleCut = true;
                                $isMermaCut = false;
                            } else {
                                $isHandleCut = ($corteSub === 'manillas');
                                $cDestino = $isHandleCut ? 'manilla' : 'bolsa';
                                $isMermaCut = false;
                            }

                            $cMermaAmt = 0;
                            if ($isHandleCut) {
                                $cMermaAmt = isset($_REQUEST["manillas_merma_amt_{$idxTemp}"]) ? max(0, min($cReqAmt, (int)$_REQUEST["manillas_merma_amt_{$idxTemp}"])) : 0;
                            }

                            $lineWidth = $cReqAmt * $cReqDims;
                            $_TOTAL_WIDTH += $lineWidth;

                            $calculatedCuts[$idxTemp] = [
                                'amt' => $cReqAmt,
                                'dims' => $cReqDims,
                                'destino' => $cDestino,
                                'is_handle' => $isHandleCut,
                                'is_merma' => $isMermaCut,
                                'merma_amt' => $cMermaAmt,
                                'line_width' => $lineWidth,
                            ];
                        }

                        $netProductsKg = max(0.0, $useamt - $itemMermaKg);
                        $baseRegKg = (float)($itemsel['item_reg_kg'] ?? 1);
                        if ($baseRegKg <= 0) $baseRegKg = 1.0;

                        $_LARGO = (float)($itemsel['item_reg_length'] ?? 0)
                            - (($itemMermaKg * (float)($itemsel['item_reg_length'] ?? 0)) / $baseRegKg)
                            - (($sobranteKg * (float)($itemsel['item_reg_length'] ?? 0)) / $baseRegKg)
                            - (($origusediffamt * (float)($itemsel['item_reg_length'] ?? 0)) / $baseRegKg);

                        $totalScrappedManillasUnits = 0;
                        $totalScrappedManillasKgSum = 0.0;
                        $totalCutMermaKgSum = 0.0;
                        $totalUsableRollsCount = 0;
                        $totalUsableKgSum = 0.0;

                        foreach ($calculatedCuts as $x => $cData):
                            $cReqAmt = $cData['amt'];
                            $cReqDims = $cData['dims'];
                            $cDestino = $cData['destino'];
                            $isHandleCut = $cData['is_handle'];
                            $isMermaCut = !empty($cData['is_merma']);
                            $cMermaAmt = $cData['merma_amt'];
                            $lineWidth = $cData['line_width'];

                            // Proporción de este producto según su peso/ancho respecto a la bobina
                            $productProportion = $rollWidth > 0 ? ($lineWidth / $rollWidth) : (1.0 / max(1, count($calculatedCuts)));

                            // Kilos netos asignados a esta línea de corte
                            $_KG = round($netProductsKg * $productProportion, 2);
                            $unitKg = $cReqAmt > 0 ? round($_KG / $cReqAmt, 2) : 0.0;

                            if ($isMermaCut) {
                                $usableAmt = 0;
                                $usableKg = 0.0;
                                $lineGeneralMermaKg = 0.0;
                                $lineScrappedManillasKg = 0.0;
                                $lineTotalMermaKg = $_KG; // 100% de esta tira se asigna a merma/refile
                                $totalCutMermaKgSum += $_KG;
                            } else {
                                // Distribución de la Merma General de acorde al peso del producto:
                                $lineGeneralMermaKg = round($itemMermaKg * $productProportion, 2);

                                // Kilos de manillas descartadas a merma (por bobina sucia):
                                $lineScrappedManillasKg = round($cMermaAmt * $unitKg, 2);

                                // Merma total correspondiente a esta línea:
                                $lineTotalMermaKg = round($lineGeneralMermaKg + $lineScrappedManillasKg, 2);

                                // Unidades útiles resultantes que se darán de alta
                                $usableAmt = max(0, $cReqAmt - $cMermaAmt);
                                $usableKg = round($usableAmt * $unitKg, 2);

                                $totalScrappedManillasUnits += $cMermaAmt;
                                $totalScrappedManillasKgSum += $lineScrappedManillasKg;
                                $totalUsableRollsCount += $usableAmt;
                                $totalUsableKgSum += $usableKg;
                            }

                            // Buscar si ya existe un código en catálogo con el ancho, color y gramaje exactos
                            $matchedCutItemId = 0;
                            $matchedCutSku = '';
                            if (!$isMermaCut && $cReqDims > 0) {
                                $rowColorId = (int)($itemsel['colorid'] ?? 0);
                                $rowGsm = (float)($itemsel['item_reg_gsm'] ?? 80);
                                if ($rowColorId > 0 && $rowGsm > 0) {
                                    try {
                                        $stFindCut = Db::erpPdo()->prepare("
                                            SELECT t1.id, t1.item_number_prod
                                            FROM item t1
                                            INNER JOIN item_productcats t2 ON t1.id = t2.item_id
                                            INNER JOIN tran_comments_item_vals t4 ON t4.item_id = t1.id AND t4.com_id = 17
                                            WHERE t1.item_status > 0
                                              AND t2.cat_id IN (6, 27)
                                              AND ABS(t1.item_reg_gsm - :gsm) < 0.5
                                              AND ABS(t1.item_reg_width - :w) < 0.2
                                              AND t4.val_id = :color_id
                                            ORDER BY (CASE WHEN t1.item_number_prod LIKE 'TEL%' THEN 0 ELSE 1 END), t1.id DESC
                                            LIMIT 1
                                        ");
                                        $stFindCut->execute([':gsm' => $rowGsm, ':w' => $cReqDims, ':color_id' => $rowColorId]);
                                        $foundCutRow = $stFindCut->fetch(PDO::FETCH_ASSOC);
                                        if ($foundCutRow) {
                                            $matchedCutItemId = (int)$foundCutRow['id'];
                                            $matchedCutSku = (string)$foundCutRow['item_number_prod'];
                                        }
                                    } catch (Throwable) {}
                                }
                            }
                        ?>
                            <input type="hidden" name="stockplustotalkg_<?=$x?>" value="<?=round($_KG, 2)?>">
                            <input type="hidden" name="stockpluslargo_<?=$x?>" value="<?=round($_LARGO, 2)?>">
                            <input type="hidden" name="stockplusitemid_<?=$x?>" value="<?=$matchedCutItemId > 0 ? $matchedCutItemId : ''?>">
                            <tr id="cut_row_<?=$x?>">
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;">
                                    <input type="text" class="inptxt clsreboamt" style="width:100%;text-align:center"
                                    name="reboamt_<?=$x?>" value="<?=$cReqAmt?>"
                                    oninput="markCutsFormModified('reboamt_<?=$x?>');">
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="left">
                                    <select name="rebo_destino_<?=$x?>" id="rebo_destino_<?=$x?>" style="width:100%;font-size:12px;font-weight:bold;padding:5px 8px;border:1px solid #BDC3C7;border-radius:4px;background:#FFFFFF"
                                            onchange="updateDestinoRow(<?=$x?>);">
                                        <option value="bolsa" <?=$cDestino === 'bolsa' ? 'selected' : ''?>>📦 Cuerpo de Bolsa</option>
                                        <option value="manilla" <?=$cDestino === 'manilla' ? 'selected' : ''?>>🏷️ Manilla</option>
                                        <option value="merma" <?=$cDestino === 'merma' ? 'selected' : ''?>>🗑️ 100% Merma / Refile</option>
                                    </select>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="left">
                                    <?php if ($isMermaCut): ?>
                                        <span id="badge_dest_<?=$x?>" style="background:#C0392B;color:#FFFFFF;padding:4px 8px;border-radius:4px;font-size:11px;font-weight:bold;white-space:nowrap;display:inline-flex;align-items:center;gap:4px">
                                            <i class="fa fa-trash"></i> MERMA / REFILE (100%)
                                        </span>
                                    <?php elseif ($isHandleCut): ?>
                                        <span id="badge_dest_<?=$x?>" style="background:#1B4F72;color:#FFFFFF;padding:4px 8px;border-radius:4px;font-size:11px;font-weight:bold;white-space:nowrap;display:inline-flex;align-items:center;gap:4px">
                                            <i class="fa fa-cut"></i> MANILLA (ROLL-MAN-)
                                        </span>
                                    <?php else: ?>
                                        <span id="badge_dest_<?=$x?>" style="background:#6C3483;color:#FFFFFF;padding:4px 8px;border-radius:4px;font-size:11px;font-weight:bold;white-space:nowrap;display:inline-flex;align-items:center;gap:4px">
                                            <i class="fa fa-shopping-bag"></i> CUERPO BOLSA (ROLL-BOL-)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=htmlspecialchars((string)$itemsel['material'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="left"><?=htmlspecialchars((string)$itemsel['colordesc'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($itemsel['item_reg_gsm'])?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                    <?php if ($isMermaCut): ?>
                                        <span style="color:#94a3b8;font-size:11px">&mdash;</span>
                                    <?php elseif ($matchedCutItemId > 0): ?>
                                        <span style="background:#CCFBF1;color:#0F766E;padding:3px 7px;border-radius:4px;font-size:12px;font-weight:bold;white-space:nowrap;display:inline-flex;align-items:center;gap:4px" title="Código de artículo en catálogo para este ancho, color y gramaje">
                                            <i class="fa fa-barcode"></i> <?=$matchedCutSku?>
                                        </span>
                                    <?php else: ?>
                                        <span style="background:#FEF3C7;color:#B45309;padding:3px 7px;border-radius:4px;font-size:11px;font-weight:bold;white-space:nowrap;display:inline-flex;align-items:center;gap:4px" title="Se creará automáticamente un nuevo código TEL para este color, ancho y gramaje">
                                            <i class="fa fa-plus-circle"></i> Nuevo TEL
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                    <input type="text" class="inptxt" id="rollo_dims_<?=$x?>" name="rollo_dims_<?=$x?>"
                                    style="width:100%;text-align:center"
                                    oninput="markCutsFormModified('rollo_dims_<?=$x?>');"
                                    value="<?=number_format($cReqDims, 2, ',', '.')?>">
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                    <b><?=number_format($_KG, 2, ',', '.')?></b>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                    <?=number_format($unitKg, 2, ',', '.')?>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                    <?php if ($isMermaCut): ?>
                                        <div style="font-weight:bold;color:#C0392B"><?=number_format($lineTotalMermaKg, 2, ',', '.')?> Kg</div>
                                        <div style="font-size:10px;color:#C0392B">(100% Merma de Corte)</div>
                                    <?php else: ?>
                                        <div style="font-weight:bold;color:#2C3E50"><?=number_format($lineTotalMermaKg, 2, ',', '.')?> Kg</div>
                                        <div style="font-size:10px;color:#64748B">
                                            (<?=number_format($productProportion * 100, 1, ',', '.')?>% peso<?= $cMermaAmt > 0 ? " + {$cMermaAmt} descarte" : "" ?>)
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                    <?php if ($isHandleCut): ?>
                                        <div id="merma_manillas_wrap_<?=$x?>" style="display:flex;align-items:center;justify-content:center;gap:4px">
                                            <input type="number" min="0" max="<?=$cReqAmt?>" step="1"
                                                   name="manillas_merma_amt_<?=$x?>" id="manillas_merma_amt_<?=$x?>"
                                                   value="<?=$cMermaAmt?>" class="inptxt"
                                                   style="width:65px;text-align:center;font-weight:bold;color:<?=$cMermaAmt > 0 ? '#C0392B' : '#333'?>;background:<?=$cMermaAmt > 0 ? '#FDEDEC' : '#FFF'?>;border:1px solid <?=$cMermaAmt > 0 ? '#E6B0AA' : '#CBD5E1'?>;padding:4px;border-radius:4px"
                                                   placeholder="0" title="Manillas descartadas a merma por bobina sucia"
                                                   oninput="markCutsFormModified('manillas_merma_<?=$x?>');">
                                            <span style="font-size:11px;color:#64748B">unid</span>
                                        </div>
                                        <?php if ($cMermaAmt > 0): ?>
                                            <div style="font-size:10.5px;color:#C0392B;font-weight:bold;margin-top:2px">
                                                <?=number_format($lineScrappedManillasKg, 2, ',', '.')?> Kg descarte
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#94A3B8;font-size:13px">&mdash;</span>
                                        <input type="hidden" name="manillas_merma_amt_<?=$x?>" id="manillas_merma_amt_<?=$x?>" value="0">
                                    <?php endif; ?>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center"><?=$fmt($_LARGO)?></td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;" align="center">
                                    <button type="button" class="btngrey" style="padding:3px 7px;font-size:11px;color:#C0392B;border-radius:3px;cursor:pointer"
                                            onclick="removeReboCutRow(<?=$x?>);" title="Eliminar fila">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        </table>

                        <!-- BOTÓN PARA AGREGAR FILA DE CORTE / MERMA DINÁMICAMENTE -->
                        <div style="margin:10px 12px;display:flex;justify-content:flex-start">
                            <button type="button" class="btnblue" style="padding:7px 16px;border-radius:5px;font-size:13px;font-weight:bold;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 1px 3px rgba(0,0,0,0.12)"
                                    onclick="addReboCutRow();">
                                <i class="fa fa-plus-circle"></i> ➕ Agregar Fila de Corte / Merma
                            </button>
                        </div>

                        <?php
                        $totalProcessMermaKg = round($itemMermaKg + $totalScrappedManillasKgSum + $totalCutMermaKgSum, 2);
                        $widthMatches = round($_TOTAL_WIDTH, 1) == round($rollWidth, 1);
                        ?>

                        <!-- RESUMEN TOTAL DE CORTE Y DISTRIBUCIÓN DE MERMA -->
                        <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:12px 16px;margin:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
                            <div style="font-size:12.5px;color:#334155;line-height:1.6">
                                <span><b>Bobina Usada:</b> <?=number_format($useamt, 2, ',', '.')?> Kg</span> |
                                <span><b>Rollos Útiles:</b> <b style="color:#0E6251"><?=$totalUsableRollsCount?> rollos</b> (<?=number_format($totalUsableKgSum, 2, ',', '.')?> Kg)</span> |
                                <span><b>Merma General (por peso):</b> <?=number_format($itemMermaKg, 2, ',', '.')?> Kg</span>
                                <?php if ($totalScrappedManillasUnits > 0): ?>
                                    | <span style="color:#C0392B;font-weight:bold"><b>Manillas a Merma (Sucia):</b> <?=$totalScrappedManillasUnits?> unid (<?=number_format($totalScrappedManillasKgSum, 2, ',', '.')?> Kg)</span>
                                <?php endif; ?>
                                <?php if ($totalCutMermaKgSum > 0): ?>
                                    | <span style="color:#C0392B;font-weight:bold"><b>Merma de Refile / Corte:</b> <?=number_format($totalCutMermaKgSum, 2, ',', '.')?> Kg</span>
                                <?php endif; ?>
                                | <span><b>Merma Total Proceso:</b> <b style="color:#C0392B"><?=number_format($totalProcessMermaKg, 2, ',', '.')?> Kg</b></span>
                            </div>
                            <div style="font-size:13px;font-weight:bold">
                                <span>Ancho Sumado: </span>
                                <b id="idx_total_width" style="color:<?=$widthMatches ? '#0E6251' : '#C0392B'?>"><?=number_format($_TOTAL_WIDTH, 2, ',', '.')?> cm</b>
                                <span style="font-size:11.5px;color:#64748B">/ <?=number_format($rollWidth, 2, ',', '.')?> cm</span>
                            </div>
                        </div>

                        <table border="0" cellpadding="6" cellspacing="0" style="margin:8px 12px 16px 12px;width:calc(100% - 24px)">
                        <tr>
                            <td width="330">
                                <button type="button" class="btngreen" id="idx_btn_term"
                                        style="<?=$widthMatches ? 'display:inline-flex;' : 'display:none;'?>padding:9px 24px;border:0px;font-size:14.5px;font-weight:bold;cursor:pointer;border-radius:5px;align-items:center;gap:8px;box-shadow:0 1px 3px rgba(0,0,0,0.12)"
                                        onclick="openReboFinalizeModal('<?=htmlspecialchars($corteSub === 'manillas' ? 'corte_manillas' : 'corte_bolsa')?>');">
                                    <i class="fa fa-check-circle"></i> Finalizar Corte
                                </button>

                                <button type="button" class="btngrey" id="idx_btn_save"
                                        style="<?=$widthMatches ? 'display:none;' : 'display:inline-flex;'?>padding:9px 22px;border:0px;font-size:14px;font-weight:bold;cursor:pointer;border-radius:5px;align-items:center;gap:8px;background:#D97706;color:#FFFFFF"
                                        onclick="submitReboRecalculate();">
                                    <i class="fa fa-sync-alt"></i> Actualizar y Recalcular Distribución
                                </button>
                            </td>
                            <td style="font-size:12.5px;font-weight:bold" id="idx_width_status_text">
                                <?php if ($widthMatches): ?>
                                    <span style="color:#0E6251"><i class="fa fa-check-circle"></i> Ancho distribuido coincide exactamente con el ancho de bobina (<?=$fmt($rollWidth)?> cm). Listo para finalizar.</span>
                                <?php else: ?>
                                    <span style="color:#C0392B"><i class="fa fa-exclamation-circle"></i> El ancho total sumado (<?=number_format($_TOTAL_WIDTH, 2, ',', '.')?> cm) debe ser igual al ancho de la bobina entrante (<?=$fmt($rollWidth)?> cm). Presione "Actualizar y Recalcular Distribución".</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        </table>
                    </td>
                </tr>
                </table>
            </form>
        <?php else: ?>
            <!-- NINGUNA BOBINA CARGADA PARA CORTE -->
            <div style="background:#FFFFFF;border:2px dashed <?=$corteSub === 'bolsa' ? '#8E44AD' : '#2980B9'?>;border-radius:8px;padding:34px 22px;text-align:center;margin-bottom:18px;box-shadow:0 2px 6px rgba(0,0,0,0.03)">
                <div style="display:inline-flex;align-items:center;justify-content:center;width:60px;height:60px;border-radius:50%;background:<?=$corteSub === 'bolsa' ? '#F4ECF7' : '#EBF5FB'?>;color:<?=$corteSub === 'bolsa' ? '#8E44AD' : '#2980B9'?>;font-size:26px;margin-bottom:12px">
                    <i class="fa fa-barcode"></i>
                </div>
                <div style="font-size:17px;font-weight:bold;color:#2C3E50;margin-bottom:6px">
                    Esperando Selección o Escaneo de Bobina
                </div>
                <div style="font-size:13.5px;color:#5D6D7E;max-width:620px;margin:0 auto 12px auto;line-height:1.5">
                    <?php if ($corteSub === 'bolsa'): ?>
                        Puede ingresar una bobina impresa o una tela virgen. <b>Escanee su código de trazabilidad</b> o seleccione una tela virgen de Bodega 3000 para cortarla antes de imprimir.
                    <?php else: ?>
                        Escanee la bobina virgen <b>TEL</b> en el lector superior o selecciónela directamente de la lista inferior de <b>Bodega 3000</b>.
                    <?php endif; ?>
                </div>
                <span style="display:inline-flex;align-items:center;gap:6px;background:<?=$corteSub === 'bolsa' ? '#F4ECF7' : '#EBF5FB'?>;color:<?=$corteSub === 'bolsa' ? '#6C3483' : '#1B4F72'?>;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:bold">
                    <i class="fa fa-info-circle"></i> <?= $corteSub === 'bolsa' ? 'Tela virgen o bobina impresa' : 'Solo bobinas vírgenes TEL disponibles para confección de manillas' ?>
                </span>
            </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- MODAL DE CONFIRMACIÓN DE FINALIZACIÓN -->
        <div id="rebo_finalize_modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:99998;align-items:center;justify-content:center;padding:18px;box-sizing:border-box">
            <div style="background:#FFFFFF;border-radius:10px;box-shadow:0 20px 35px rgba(0,0,0,0.28);width:100%;max-width:560px;border:1px solid #CBD5E1;overflow:hidden">
                <div style="background:#0F766E;color:#FFFFFF;padding:14px 18px;font-size:16px;font-weight:bold;display:flex;align-items:center;gap:10px">
                    <i class="fa fa-check-circle"></i> Confirmar finalización
                </div>
                <div style="padding:20px 18px;color:#334155;font-size:14px;line-height:1.6">
                    <div id="rebo_finalize_modal_message">
                        ¿Desea continuar con este registro?
                    </div>
                </div>
                <div style="padding:0 18px 18px;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap">
                    <button type="button" class="btngrey" style="padding:8px 18px;border:0;cursor:pointer" onclick="closeReboFinalizeModal()">Cancelar</button>
                    <button type="button" class="btngreen" style="padding:8px 18px;border:0;cursor:pointer" onclick="submitReboFinalize()">Sí, finalizar</button>
                </div>
            </div>
        </div>
        <script>
        (function () {
            var reboFinalizeKind = '';
            window.openReboFinalizeModal = function (kind) {
                reboFinalizeKind = kind || 'corte';
                var message = document.getElementById('rebo_finalize_modal_message');
                if (message) {
                    if (reboFinalizeKind === 'empalme') {
                        message.innerHTML = '<b>Se consumirán las bobinas seleccionadas en Bodega 3000</b> y <b>se dará de alta la nueva bobina empalmada en Bodega 3000</b> con su código de trazabilidad (ROLL-EMP-XXXXX).<br><br><i>Nota: No genera duplicidad en el informe oficial de materiales ya que provienen de procesos anteriores.</i><br><br>¿Desea finalizar el empalme ahora?';
                    } else if (reboFinalizeKind === 'rebobinado') {
                        message.innerHTML = '<b>Se registrará la bobina rebobinada</b> con su peso final y observaciones de rebobinado.<br><br><i>Nota: Esta operación no altera el informe oficial de materiales ni genera consumos en Bodega 3000.</i><br><br>¿Desea confirmar el rebobinado ahora?';
                    } else if (reboFinalizeKind === 'corte_manillas') {
                        var msg = '<b>Se consumirá 1 bobina madre TEL en Bodega 3000</b> (registrada en el informe oficial de materiales) y <b>se darán de alta las bobinas individuales de manillas útiles en Bodega 3000</b> con su código <b>ROLL-MAN-XXXXX</b> y etiqueta para cada rollo.';
                        <?php if ($totalScrappedManillasUnits > 0): ?>
                            msg += '<br><div style="background:#FDEDEC;border:1px solid #E6B0AA;color:#922B21;padding:8px 12px;border-radius:4px;font-weight:bold;margin-top:10px">'
                                + '<i class="fa fa-exclamation-triangle"></i> Se registrarán <b><?=$totalScrappedManillasUnits?> manillas a merma</b> por bobina sucia (<?=number_format($totalScrappedManillasKgSum, 2, ',', '.')?> Kg).'
                                + '</div>';
                        <?php endif; ?>
                        msg += '<br>¿Desea finalizar el corte de manillas ahora?';
                        message.innerHTML = msg;
                    } else if (reboFinalizeKind === 'corte_bolsa') {
                        var msg = '<b>Se registrará el fraccionamiento de la bobina madre</b> y <b>quedarán en Bodega 3000</b> las bobinas hijas útiles (ROLL-BOL- / ROLL-MAN-) con sus códigos y etiquetas individuales.<br><br><i>Nota: No genera duplicidad en el informe oficial de materiales ya que fue consumida en Flexo/Seri.</i>';
                        <?php if ($totalScrappedManillasUnits > 0): ?>
                            msg += '<br><div style="background:#FDEDEC;border:1px solid #E6B0AA;color:#922B21;padding:8px 12px;border-radius:4px;font-weight:bold;margin-top:10px">'
                                + '<i class="fa fa-exclamation-triangle"></i> Se registrarán <b><?=$totalScrappedManillasUnits?> manillas a merma</b> por bobina sucia (<?=number_format($totalScrappedManillasKgSum, 2, ',', '.')?> Kg).'
                                + '</div>';
                        <?php endif; ?>
                        msg += '<br>¿Desea finalizar el corte ahora?';
                        message.innerHTML = msg;
                    } else {
                        message.innerHTML = 'Se registrará el proceso en el sistema. ¿Desea continuar?';
                    }
                }
                var modal = document.getElementById('rebo_finalize_modal');
                if (modal) {
                    modal.style.display = 'flex';
                }
            };
            window.closeReboFinalizeModal = function () {
                var modal = document.getElementById('rebo_finalize_modal');
                if (modal) {
                    modal.style.display = 'none';
                }
            };
            window.submitReboFinalize = function () {
                if (!document.xform_itemcalc) return;
                document.xform_itemcalc.rebofinalize.value = '1';
                document.xform_itemcalc.submit();
            };
            window.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    window.closeReboFinalizeModal();
                }
            });
        })();
        </script>

        <!-- TABLA: BOBINAS DISPONIBLES EN BODEGA 3000 (INVENTARIO ERP) -->
        <?php if (in_array($task, ['empalme', 'rebobinado', 'corte'], true)): ?>
            <?php
            if ($task === 'rebobinado') {
                $displayMaterials = $reboAvailableMaterials ?: [];
                $tableTitle = 'BOBINAS DISPONIBLES EN BODEGA 3000 (SELECCIONAR BOBINA A REBOBINAR)';
                $tableSubtitle = 'Seleccione la bobina que se rebobinó o escanéela con el lector superior';
                $headerColor = '#D35400';
            } elseif ($task === 'corte') {
                // Para solo manillas: estrictamente bobinas TEL vírgenes
                $displayMaterials = array_filter($reboAvailableMaterials ?: [], function ($am) {
                    $sku = (string)($am['item_number_prod'] ?? '');
                    return stripos($sku, 'TEL') === 0;
                });
                $tableTitle = 'BOBINAS TEL VÍRGENES DISPONIBLES EN BODEGA 3000';
                $tableSubtitle = $corteSub === 'manillas' ? 'Tela para confección de manillas' : 'Tela para cortar antes de imprimir';
                $headerColor = '#1B4F72';
            } else {
                // Para empalme: todas las bobinas disponibles en Bodega 3000
                $displayMaterials = $reboAvailableMaterials ?: [];
                $tableTitle = 'BOBINAS DISPONIBLES EN BODEGA 3000 (PARA EMPALME)';
                $tableSubtitle = 'Bobinas con stock listas para unir en Bobina 1 y Bobina 2';
                $headerColor = '#0E6251';
            }
            ?>
            <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
            <tr>
                <td style="background-color:#FFFFFF;border:1px solid #D5D8DC;border-radius:6px;overflow:hidden">
                    <table border="0" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="background-color:<?=$headerColor?>;color:#FFFFFF;padding:10px 14px;font-weight:bold;font-size:14px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                                <div>
                                    <i class="fa fa-fw fa-warehouse" style="color:#F39C12"></i> <?=$tableTitle?>
                                    <span style="font-size:12px;color:#BDC3C7;margin-left:8px">(<?=count($displayMaterials)?> bobinas con stock)</span>
                                </div>
                                <div style="font-size:12px;color:#BDC3C7">
                                    <?=$tableSubtitle?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    </table>
                    <table border="0" width="100%" cellpadding="8" cellspacing="0">
                    <tr>
                        <td class="tdheader" width="140" style="color:white;background-color:#34495E;border-left:1px solid #4A6572;border-top:1px solid #4A6572">Código</td>
                        <td align="left" class="tdheader" style="color:white;background-color:#34495E;border-left:1px solid #4A6572;border-top:1px solid #4A6572">SKU / Descripción</td>
                        <td align="left" class="tdheader" width="100" style="color:white;background-color:#34495E;border-left:1px solid #4A6572;border-top:1px solid #4A6572">Material</td>
                        <td align="left" class="tdheader" width="130" style="color:white;background-color:#34495E;border-left:1px solid #4A6572;border-top:1px solid #4A6572">Color</td>
                        <td align="center" class="tdheader" width="80" style="color:white;background-color:#34495E;border-left:1px solid #4A6572;border-top:1px solid #4A6572">Gramaje</td>
                        <td align="center" class="tdheader" width="85" style="color:white;background-color:#34495E;border-left:1px solid #4A6572;border-top:1px solid #4A6572">Ancho (cm)</td>
                        <td align="center" class="tdheader" width="95" style="color:white;background-color:#34495E;border-left:1px solid #4A6572;border-top:1px solid #4A6572">Kg/Pesa</td>
                        <td align="center" class="tdheader" width="100" style="color:white;background-color:#34495E;border-left:1px solid #4A6572;border-top:1px solid #4A6572">Stock Disp.</td>
                        <td align="center" class="tdheader" width="160" style="color:white;background-color:#34495E;border-left:1px solid #4A6572;border-top:1px solid #4A6572">Acción</td>
                    </tr>
                    <?php if (empty($displayMaterials)): ?>
                        <tr>
                            <td colspan="9" align="center" style="padding:22px 15px;color:#7F8C8D;font-size:13px">
                                <i class="fa fa-info-circle fa-2x" style="color:#BDC3C7;margin-bottom:8px;display:block"></i>
                                <?= $task === 'rebobinado'
                                    ? 'No hay bobinas con stock disponible en Bodega 3000 para rebobinar.'
                                    : (($task === 'corte' && $corteSub === 'manillas')
                                        ? 'No hay bobinas TEL vírgenes disponibles en Bodega 3000 que coincidan con la clasificación de la orden.'
                                        : 'No hay bobinas con stock disponible en Bodega 3000 para empalmar.') ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($displayMaterials as $am):
                            $isSel1 = ($selItemId1 > 0 && (int)$am['id'] === $selItemId1);
                            $isSel2 = ($selItemId2 > 0 && (int)$am['id'] === $selItemId2);
                            $rowBg = ($isSel1 || $isSel2) ? 'background-color:#FFF5A0;' : '';
                        ?>
                            <tr style="<?=$rowBg?>border-bottom:1px solid #EEEEEE">
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;<?=$rowBg?>">
                                    <b style="font-size:13.5px;color:<?=$headerColor?>"><?=htmlspecialchars((string)$am['item_number_prod'])?></b>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;<?=$rowBg?>" align="left">
                                    <?=htmlspecialchars((string)$am['item_title'])?>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;<?=$rowBg?>" align="left">
                                    <?=htmlspecialchars((string)$am['material'])?>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;<?=$rowBg?>" align="left">
                                    <?=htmlspecialchars((string)$am['colordesc'])?>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;<?=$rowBg?>" align="center">
                                    <?=$fmt($am['item_reg_gsm'])?>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;<?=$rowBg?>" align="center">
                                    <?=$fmt($am['item_reg_width'])?>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;<?=$rowBg?>" align="center">
                                    <b><?=number_format((float)$am['item_reg_kg'], 2, ',', '.')?> Kg</b>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;<?=$rowBg?>" align="center">
                                    <span style="background:#D4EFDF;color:#196F3D;padding:3px 8px;border-radius:3px;font-size:11px;font-weight:bold">
                                        <?=number_format((float)$am['iss_inventory'], 1, ',', '.')?> un
                                    </span>
                                </td>
                                <td class="tdnrm" style="border-left:1px solid #DDDDDD;<?=$rowBg?>" align="center">
                                    <?php if ($task === 'empalme'): ?>
                                        <?php if ($isSel1): ?>
                                            <span style="background:#0E6251;color:#FFFFFF;padding:4px 8px;border-radius:3px;font-weight:bold;font-size:11px">
                                                <i class="fa fa-check"></i> En Bobina 1
                                            </span>
                                        <?php elseif ($isSel2): ?>
                                            <span style="background:#0E6251;color:#FFFFFF;padding:4px 8px;border-radius:3px;font-weight:bold;font-size:11px">
                                                <i class="fa fa-check"></i> En Bobina 2
                                            </span>
                                        <?php else: ?>
                                            <div style="display:inline-flex;gap:4px">
                                                <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod&task=empalme&selitemid1=<?=(int)$am['id']?>&selitemid2=<?=$selItemId2?>"
                                                   class="btnblue" style="text-decoration:none;padding:4px 8px;font-size:11px;font-weight:bold;border-radius:3px">
                                                    <i class="fa fa-plus"></i> Bobina 1
                                                </a>
                                                <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod&task=empalme&selitemid1=<?=$selItemId1?>&selitemid2=<?=(int)$am['id']?>"
                                                   class="btngreen" style="text-decoration:none;padding:4px 8px;font-size:11px;font-weight:bold;border-radius:3px">
                                                    <i class="fa fa-plus"></i> Bobina 2
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($task === 'rebobinado'): ?>
                                        <?php if ($isSel1): ?>
                                            <span style="color:#D35400;font-weight:bold;font-size:13px">
                                                <i class="fa fa-check-circle"></i> Cargada
                                            </span>
                                        <?php else: ?>
                                            <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod&task=rebobinado&selitemid1=<?=(int)$am['id']?>"
                                               class="btngrey" style="text-decoration:none;padding:5px 12px;font-size:12px;font-weight:bold;border-radius:4px;display:inline-block;background:#D35400;color:#FFFFFF">
                                                <i class="fa fa-sync-alt"></i> Cargar a Rebobinado
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if ($isSel1): ?>
                                            <span style="color:#27AE60;font-weight:bold;font-size:13px">
                                                <i class="fa fa-check-circle"></i> Cargada
                                            </span>
                                        <?php else: ?>
                                            <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod&task=corte&corte_sub=<?=htmlspecialchars($corteSub)?>&selitemid1=<?=(int)$am['id']?>"
                                               class="btnblue" style="text-decoration:none;padding:5px 12px;font-size:12px;font-weight:bold;border-radius:4px;display:inline-block">
                                                <i class="fa fa-arrow-up"></i> Cargar a Entrada
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </table>
                </td>
            </tr>
            </table>
        <?php endif; ?>

        <!-- TABLA: PRODUCCIÓN FINALIZADA (form.reboprod.php líneas 700-880) -->
        <?php if (!empty($reboMoveOuts)): ?>
            <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
            <tr>
                <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:0px">
                    <br>
                    <table border="0" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td colspan="2" style="background-color:#1AAAA4;color:#FFFFFF;padding:6px;font-weight:bold;text-align:center">
                            <i class="fa fa-fw fa-info"></i> PRODUCCIÓN FINALIZADA
                        </td>
                    </tr>
                    </table>
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <tr>
                        <td class="tdheader" width="100" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Transacción</td>
                        <td class="tdheader" width="100" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Tipo</td>
                        <td class="tdheader" width="100" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Fecha</td>
                        <td class="tdheader" width="100" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Código</td>
                        <td class="tdheader" width="100" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Material</td>
                        <td class="tdheader" width="100" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Cantidad</td>
                        <td class="tdheader" width="100" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Kg/Unit.</td>
                    </tr>
                    <?php
                    $_PROD_AMOUNT = 0;
                    foreach ($reboMoveOuts as $mo):
                        $delParam = "&delsthids[]={$mo['id']}";
                        foreach (($mo['moveins'] ?? []) as $mi) {
                            $delParam .= "&delsthids[]={$mi['id']}";
                        }
                    ?>
                        <tr>
                            <td style="border-top:3px double #666666;"><?=htmlspecialchars((string)$mo['stk_num'])?></td>
                            <td style="border-top:3px double #666666;color:red"><nobr><i class="fa fa-fw fa-minus"></i> Uso material</nobr></td>
                            <td style="border-top:3px double #666666;"><?=date("d.m.Y", (int)$mo['stk_bookdate'])?></td>
                            <td style="border-top:3px double #666666;"><?=htmlspecialchars((string)$mo['item_number_prod'])?></td>
                            <td style="border-top:3px double #666666;"><?=htmlspecialchars((string)$mo['item_title'])?></td>
                            <td style="border-top:3px double #666666;"><?=$fmt($mo['item_reboprod_amt'])?></td>
                            <td style="border-top:3px double #666666;">
                                <?=number_format((float)$mo['item_reboprod_unitkg'], 2, ',', '.')?>
                                <span style="float:right">
                                    <i class="fa fa-fw fa-times-circle" style="color:red;cursor:pointer" title="Anular transacción"
                                    onclick="if(confirm('¿Está seguro de anular este movimiento y revertir el inventario?')) { location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod<?=$delParam?>'; }"></i>
                                </span>
                            </td>
                        </tr>
                        <?php
                        $_PROD_AMOUNT += (float)$mo['item_amount'];
                        foreach (($mo['moveins'] ?? []) as $mi):
                        ?>
                            <tr style="background-color:#EEEEEE">
                                <td><?=htmlspecialchars((string)$mi['stk_num'])?></td>
                                <td style="color:green"><nobr><i class="fa fa-fw fa-plus"></i> Ingreso material</nobr></td>
                                <td><?=date("d.m.Y", (int)$mi['stk_bookdate'])?></td>
                                <td><?=htmlspecialchars((string)$mi['item_number_prod'])?></td>
                                <td><?=htmlspecialchars((string)$mi['item_title'])?></td>
                                <td><?=$fmt($mi['item_reboprod_amt'])?></td>
                                <td><?=number_format((float)$mi['item_reboprod_unitkg'], 2, ',', '.')?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    </table>

                    <?php
                    $reqTotal = (float)($reboOrder['req_rebo_rolloscc'] ?? ($agenda['req_rebo_rolloscc'] ?? 1));
                    ?>
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <tr>
                        <td class="tdheader" align="center" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Cantidad requerida</td>
                        <td class="tdheader" align="center" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Cantidad procesada</td>
                        <td class="tdheader" align="center" style="color: white;background-color: #666666;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Cantidad pendiente</td>
                    </tr>
                    <tr>
                        <td align="center" style="background-color:darkorange;font-weight:bold;color:#FFFFFF"><?=$fmt($reqTotal)?></td>
                        <td align="center" style="background-color:green;font-weight:bold;color:#FFFFFF"><?=$fmt($_PROD_AMOUNT)?></td>
                        <td align="center" style="background-color:red;font-weight:bold;color:#FFFFFF"><?=$fmt(max(0.0, $reqTotal - $_PROD_AMOUNT))?></td>
                    </tr>
                    </table>
                </td>
            </tr>
            </table>
        <?php endif; ?>

        <!-- TABLA: BOBINAS HIJAS FRACCIONADAS / CORTADAS (TRAZABILIDAD 100×70) -->
        <?php
        $reboCutRolls = [];
        if (Db::trzPdo() !== null) {
            try {
                $stmtCuts = Db::trzPdo()->prepare("
                    SELECT r.*, pr.roll_code AS parent_roll_code, s.code AS sku_code, s.description AS sku_desc
                    FROM rolls r 
                    LEFT JOIN rolls pr ON pr.id = r.parent_roll_id 
                    LEFT JOIN skus s ON s.id = r.sku_id
                    WHERE (r.source_work_order_id = :otid " . ($agId > 0 ? "OR r.source_work_order_id = :agid" : "") . ")
                      AND r.process_stage IN ('CUT', 'EMPALME')
                    ORDER BY r.id DESC LIMIT 50
                ");
                $paramsCuts = [':otid' => $workerOtId];
                if ($agId > 0) {
                    $paramsCuts[':agid'] = $agId;
                }
                $stmtCuts->execute($paramsCuts);
                $reboCutRolls = $stmtCuts->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable) {}
        }
        ?>
        <?php if (!empty($reboCutRolls)): ?>
            <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
            <tr>
                <td style="background-color:#FFFFFF;border:1px solid #D5D8DC;border-radius:6px;overflow:hidden">
                    <table border="0" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="background:#2C3E50;color:#FFFFFF;padding:12px 16px;font-weight:bold;font-size:14px">
                            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                                <div>
                                    <i class="fa fa-fw fa-cut" style="color:#F39C12"></i> BOBINAS HIJAS GENERADAS / FRACCIONADAS (TRAZABILIDAD 100×70)
                                    <span style="font-size:12px;color:#BDC3C7;margin-left:8px">(<?=count($reboCutRolls)?> bobinas cortadas)</span>
                                </div>
                                <div>
                                    <a href="/rolls/<?=$reboCutRolls[0]['id']?>/label?server_print=1" target="_blank" class="btngreen" style="text-decoration:none;padding:5px 12px;font-size:12px;font-weight:bold;border-radius:4px;display:inline-block">
                                        <i class="fa fa-print"></i> Imprimir Última Etiqueta en Zebra
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    </table>
                    <table border="0" width="100%" cellpadding="8" cellspacing="0">
                    <tr style="background:#34495E;color:white;font-size:12px">
                        <td width="160" style="border-left:1px solid #4A6572">Código Bobina Hija</td>
                        <td width="150" style="border-left:1px solid #4A6572">Bobina Madre</td>
                        <td align="left" style="border-left:1px solid #4A6572">SKU / Detalle</td>
                        <td align="center" width="90" style="border-left:1px solid #4A6572">Ancho (mm)</td>
                        <td align="center" width="90" style="border-left:1px solid #4A6572">Metros</td>
                        <td align="center" width="90" style="border-left:1px solid #4A6572">Peso (Kg)</td>
                        <td align="center" width="90" style="border-left:1px solid #4A6572">Etapa</td>
                        <td align="center" width="220" style="border-left:1px solid #4A6572">Acciones</td>
                    </tr>
                    <?php foreach ($reboCutRolls as $cr): ?>
                        <tr style="border-bottom:1px solid #EEEEEE;font-size:12.5px">
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD;font-weight:bold;color:#2980B9">
                                <i class="fa fa-barcode"></i> <?=htmlspecialchars($cr['roll_code'])?>
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD">
                                <?=htmlspecialchars($cr['parent_roll_code'] ?: ('ID #' . ($cr['parent_roll_id'] ?? '-')))?>
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="left">
                                <div><b style="color:#2C3E50"><?=htmlspecialchars((string)($cr['sku_code'] ?? ''))?></b></div>
                                <div style="font-size:11.5px;color:#555555;margin-top:2px">
                                    <?php 
                                    $descStr = (string)($cr['sku_desc'] ?? '');
                                    if (str_contains($descStr, 'Cargada a SKU:')) {
                                        $parts = explode(' - Cargada a SKU: ', $descStr, 2);
                                        echo htmlspecialchars($parts[0]);
                                        echo ' <span style="background:#E8F8F5;color:#117A65;font-weight:bold;padding:1px 6px;border-radius:3px;border:1px solid #A3E4D7;font-size:11px"><i class="fa fa-bullseye"></i> Cargada a SKU: ' . htmlspecialchars($parts[1]) . '</span>';
                                    } else {
                                        echo htmlspecialchars($descStr);
                                    }
                                    ?>
                                </div>
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">
                                <?=!empty($cr['width_mm']) ? number_format((float)$cr['width_mm'], 0) . ' mm' : '-'?>
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">
                                <?=!empty($cr['meters']) ? number_format((float)$cr['meters'], 0) . ' m' : '-'?>
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">
                                <b><?=number_format((float)$cr['weight_kg'], 2, ',', '.')?> Kg</b>
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">
                                <span style="background:#EBF5FB;color:#2980B9;padding:2px 7px;border-radius:3px;font-weight:bold;font-size:11px">
                                    <?=htmlspecialchars($cr['process_stage'] ?? 'CUT')?>
                                </span>
                            </td>
                            <td class="tdnrm" style="border-left:1px solid #DDDDDD" align="center">
                                <a href="/rolls/<?=$cr['id']?>/label" target="_blank" class="btngrey" style="text-decoration:none;padding:4px 8px;font-size:11.5px;margin-right:4px" title="Ver e imprimir etiqueta en navegador">
                                    <i class="fa fa-tag"></i> 100×70
                                </a>
                                <a href="/rolls/<?=$cr['id']?>/label?server_print=1" target="_blank" class="btnblue" style="text-decoration:none;padding:4px 8px;font-size:11.5px;margin-right:4px" title="Enviar directamente a impresora Zebra">
                                    <i class="fa fa-print"></i> Zebra
                                </a>
                                <a href="/production/traceability?q=<?=urlencode($cr['roll_code'])?>" target="_blank" class="btngreen" style="text-decoration:none;padding:4px 8px;font-size:11.5px" title="Ver árbol genealógico completo">
                                    <i class="fa fa-sitemap"></i> 🌳
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </table>
                </td>
            </tr>
            </table>
        <?php endif; ?>

    <?php

