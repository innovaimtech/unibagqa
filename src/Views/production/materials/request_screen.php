<?php
    ?>
    <div style="font-size:20px;font-weight:bold;color:#115452;margin-bottom:15px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div>
            <i class="fa fa-dolly" style="color:#00A85A"></i> Solicitar / Cargar Materiales a Máquina: <span style="color:#0E7572"><?=htmlspecialchars($machineName)?></span>
        </div>
        <div style="font-size:13px;font-weight:normal;color:#64748B">
            <span style="background:#E2E8F0;padding:4px 10px;border-radius:4px;color:#1E293B">
                <i class="fa fa-user"></i> <?=htmlspecialchars($operatorName)?> &bull; <i class="fa fa-building"></i> <?=htmlspecialchars($plantaName)?>
            </span>
        </div>
    </div>

    <?php if ($savedMaterial): ?>
        <div style="background:#D1FAE5;border:1.5px solid #10B981;border-radius:6px;padding:12px 18px;margin-bottom:15px;color:#065F46;font-size:13px;display:flex;align-items:center;gap:10px">
            <i class="fa fa-check-circle" style="font-size:20px;color:#059669"></i>
            <div>
                <b>¡Material cargado exitosamente a la máquina y operario!</b>
                <?php if ($noTrace): ?>
                    <span style="background:#FEF3C7;color:#92400E;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:bold;margin-left:8px">Sin Trazabilidad Física</span>
                    La bobina quedó registrada directamente para su uso en la orden de trabajo.
                <?php else: ?>
                    La bobina quedó cargada con su código de trazabilidad en Bodega 3000.
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($savedRequest): ?>
        <div style="background:#DBEAFE;border:1.5px solid #60A5FA;border-radius:6px;padding:12px 18px;margin-bottom:15px;color:#1D4ED8;font-size:13px;display:flex;align-items:center;gap:10px">
            <i class="fa fa-paper-plane" style="font-size:20px;color:#2563EB"></i>
            <div>
                <?php if ($savedRequestType === 'chemical'): ?>
                    <b>Solicitud de tinta registrada correctamente.</b>
                    La OT quedó solicitada contra la bodega sugerida <?=$inkWarehouseCode > 0 ? '<b>' . htmlspecialchars((string)$inkWarehouseCode) . '</b>' : ''?><?=($inkWarehouseName !== '' ? ' (' . htmlspecialchars($inkWarehouseName) . ')' : '')?>.
                <?php elseif ($savedRequestType === 'rolls'): ?>
                    <b>Solicitud de bobina registrada correctamente.</b>
                    La bobina quedó traspasada y disponible en <b>Bodega 3000</b> para que luego la tome la máquina que la vaya a consumir.
                <?php else: ?>
                    <b>Solicitud de material registrada correctamente.</b>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div style="background:#FEE2E2;border:1.5px solid #EF4444;border-radius:6px;padding:12px 18px;margin-bottom:15px;color:#991B1B;font-size:13px;display:flex;align-items:center;gap:10px">
            <i class="fa fa-exclamation-triangle" style="font-size:20px;color:#DC2626"></i>
            <div><b>Error en la solicitud de material:</b> <?=htmlspecialchars($error)?></div>
        </div>
    <?php endif; ?>

    <!-- ============================================================== -->
    <!-- SECCIÓN 1: SELECCIONAR ORDEN DE TRABAJO EN PRODUCCIÓN          -->
    <!-- ============================================================== -->
    <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:18px;margin-bottom:18px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
        <div style="font-size:14px;font-weight:bold;color:#0F766E;margin-bottom:12px;display:flex;align-items:center;gap:8px">
            <i class="fa fa-tasks"></i> 1. Seleccione la Orden de Trabajo (OT) que recibirá los materiales
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <label style="font-size:13px;color:#334155;font-weight:bold">Orden de Trabajo:</label>
            <select class="inptxt" style="width:100%;max-width:550px;background:#FFF;font-size:13px;padding:8px" onchange="if(this.value.startsWith('prd:')){ location.href='/production/materials/request?prd_id=' + this.value.substring(4); } else if(this.value !== '') { location.href='/production/materials/request?agid=' + this.value; }">
                <option value="">&lt; Seleccione una OT de esta máquina &gt;</option>
                <?php if (!empty($uniqueOrders)): ?>
                <optgroup label="OTs en Producción / Programadas">
                <?php foreach ($uniqueOrders as $ord):
                    $aid = (int)$ord['ag_id'];
                    $pNum = (string)($ord['prd_number'] ?? $ord['ag_id']);
                    $cName = (string)($ord['cust_name'] ?? '');
                    $pTitle = (string)($ord['item_title'] ?? $ord['fab_design_name'] ?? '');
                    $isSelected = ($aid === $selectedAgId);
                ?>
                    <option value="<?=$aid?>" <?=$isSelected ? 'selected' : ''?>>
                        OT #<?=$pNum?> &mdash; <?=htmlspecialchars($cName)?> (<?=htmlspecialchars(mb_substr($pTitle, 0, 40))?>)
                    </option>
                <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
                <?php if (!empty($unscheduledOrders)): ?>
                <optgroup label="OTs Fuera de Planificación">
                <?php foreach ($unscheduledOrders as $uo):
                    $uPrdId = (int)($uo['prd_id'] ?? 0);
                    $pNum = (string)($uo['prd_number'] ?? $uPrdId);
                    $cName = (string)($uo['cust_name'] ?? '');
                    $pTitle = (string)($uo['item_title'] ?? $uo['fab_design_name'] ?? '');
                    $isSelected = ($selectedAgenda !== null && (int)($selectedAgenda['prd_id'] ?? 0) === $uPrdId);
                ?>
                    <option value="prd:<?=$uPrdId?>" <?=$isSelected ? 'selected' : ''?>>
                        OT #<?=$pNum?> &mdash; <?=htmlspecialchars($cName)?> (<?=htmlspecialchars(mb_substr($pTitle, 0, 40))?>) [Sin planificar]
                    </option>
                <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
            </select>

            <?php if ($selectedAgId > 0): ?>
                <a href="/production/work-orders/operate?agid=<?=$selectedAgId?>" class="btnblue" style="text-decoration:none;padding:7px 15px;font-size:12px">
                    <i class="fa fa-desktop"></i> Ir a Consola de la OT
                </a>
            <?php endif; ?>
        </div>

        <?php if ($selectedAgenda !== null): ?>
            <!-- FICHA TÉCNICA DE LA OT SELECCIONADA -->
            <div style="margin-top:15px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:14px">
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="110">
                    <col width="120">
                    <col width="180">
                    <col>
                    <col width="100">
                    <col width="120">
                    <col width="90">
                </colgroup>
                <thead>
                    <tr style="border-bottom:1px solid #CBD5E1">
                        <td class="tdheader">N° C.C.</td>
                        <td class="tdheader">N° O.T.</td>
                        <td class="tdheader">Cliente</td>
                        <td class="tdheader">Diseño / Producto</td>
                        <td class="tdheader" align="center">Material</td>
                        <td class="tdheader">Medidas</td>
                        <td class="tdheader" align="center">Imagen</td>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($selectedAgenda['req_number'] ?? '-'))?></b></td>
                        <td class="tdnrm"><b style="color:#0F766E">OT #<?=htmlspecialchars($otTitle)?></b></td>
                        <td class="tdnrm"><b><?=htmlspecialchars($custName)?></b></td>
                        <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($fullDesign)?></td>
                        <td class="tdnrm" align="center">
                            <span style="background:#E0F2FE;color:#0369A1;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:bold"><?=htmlspecialchars($mat)?></span>
                        </td>
                        <td class="tdnrm" style="white-space:nowrap"><?=htmlspecialchars($medidasStr)?></td>
                        <td class="tdnrm" align="center">
                            <?php if ($hasImg): ?>
                                <img src="<?=htmlspecialchars($imgUrl)?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #CBD5E1;cursor:pointer" onclick="openDesignModal('<?=htmlspecialchars($imgUrl)?>', '<?=htmlspecialchars($otTitle)?>', '<?=htmlspecialchars(addslashes($custName))?>', '<?=htmlspecialchars(addslashes($fullDesign))?>', '<?=htmlspecialchars($medidasStr)?>')" title="Ver diseño">
                            <?php else: ?>
                                <span style="color:#94A3B8;font-size:12px">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="7" style="background:#FFFFFF;padding:8px 12px;border-bottom:1px solid #E2E8F0;font-size:12px">
                            <?php if (!empty($fabricColor)): ?>
                                <span style="margin-right:15px"><b>Color Tela:</b> <span style="background:#FEF3C7;color:#92400E;padding:2px 6px;border-radius:3px"><?=htmlspecialchars($fabricColor)?></span></span>
                            <?php endif; ?>
                            <?php if ($otGrams > 0): ?>
                                <span style="margin-right:15px"><b>Gramaje Requerido:</b> <span style="background:#E0E7FF;color:#3730A3;padding:2px 6px;border-radius:3px;font-weight:bold"><?=htmlspecialchars($otGramsStr)?></span></span>
                            <?php endif; ?>
                            <?php if ($isPrinterMachine): ?>
                                <span style="margin-right:15px;background:#F0FDF4;color:#166534;border:1px solid #BBF7D0;padding:2px 8px;border-radius:4px;font-weight:bold"><i class="fa fa-print"></i> Requiere Bobina Virgen (Sin Impresión)</span>
                            <?php endif; ?>
                            <?php if (!empty($colores)): ?>
                                <span style="margin-right:15px"><b>Colores Impresión:</b> <?=htmlspecialchars(implode(', ', $colores))?></span>
                            <?php endif; ?>
                            <?php if ($agAmount > 0): ?>
                                <span><b>Cantidad Programada:</b> <b style="color:#0F766E"><?=number_format($agAmount, 0, ',', '.')?></b> bolsas</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($selectedAgId <= 0): ?>
        <div style="background:#FFF;border:1px dashed #CBD5E1;border-radius:8px;padding:40px 20px;text-align:center;color:#64748B">
            <i class="fa fa-arrow-up" style="font-size:32px;color:#94A3B8;margin-bottom:10px;display:block"></i>
            Por favor, <b>seleccione una Orden de Trabajo arriba</b> para consultar las bobinas disponibles en Bodega 3000 o realizar la carga de materiales.
        </div>
    <?php else: ?>

        <!-- ============================================================== -->
        <!-- SECCIÓN 2: PESTAÑAS DE CARGA DE MATERIALES                     -->
        <!-- ============================================================== -->
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
            <button type="button" id="request_kind_btn_rolls" onclick="switchMaterialKind('rolls')" style="padding:9px 16px;border-radius:6px;border:1px solid <?=$preferredMaterialKind === 'rolls' ? '#86efac' : '#cbd5e1'?>;background:<?=$preferredMaterialKind === 'rolls' ? '#ecfdf5' : '#fff'?>;color:<?=$preferredMaterialKind === 'rolls' ? '#166534' : '#475569'?>;font-size:13px;font-weight:<?=$preferredMaterialKind === 'rolls' ? '800' : '700'?>;cursor:pointer">
                <i class="fa fa-dolly"></i> Bobinas Compatibles
            </button>
            <button type="button" id="request_kind_btn_inks" onclick="switchMaterialKind('inks')" style="padding:9px 16px;border-radius:6px;border:1px solid <?=$preferredMaterialKind === 'inks' ? '#93c5fd' : '#cbd5e1'?>;background:<?=$preferredMaterialKind === 'inks' ? '#eff6ff' : '#fff'?>;color:<?=$preferredMaterialKind === 'inks' ? '#1d4ed8' : '#475569'?>;font-size:13px;font-weight:<?=$preferredMaterialKind === 'inks' ? '800' : '700'?>;cursor:pointer">
                <i class="fa fa-tint"></i> Tintas e Insumos
            </button>
        </div>

        <div id="material_kind_rolls" style="display:<?=$preferredMaterialKind === 'inks' ? 'none' : 'block'?>">
        <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05);margin-bottom:18px">
            <div style="display:flex;border-bottom:1px solid #CBD5E1;background:#F8FAFC">
                <button type="button" id="tab_btn_bodega3000" class="tab-btn active" onclick="switchMaterialTab('bodega3000')" style="padding:12px 20px;font-size:13px;font-weight:bold;border:none;background:#FFF;border-bottom:3px solid #00A85A;color:#065F46;cursor:pointer;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-warehouse" style="color:#00A85A"></i> Bobinas en Bodega 3000 (<?=count($bodega3000Rolls)?>)
                </button>
                <button type="button" id="tab_btn_manual" class="tab-btn" onclick="switchMaterialTab('manual')" style="padding:12px 20px;font-size:13px;font-weight:bold;border:none;background:transparent;border-bottom:3px solid transparent;color:#64748B;cursor:pointer;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-bolt" style="color:#D97706"></i> Cargar Sin Trazabilidad (Saltar Trazabilidad)
                </button>
                <button type="button" id="tab_btn_central" class="tab-btn" onclick="switchMaterialTab('central')" style="padding:12px 20px;font-size:13px;font-weight:bold;border:none;background:transparent;border-bottom:3px solid transparent;color:#64748B;cursor:pointer;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-truck-loading" style="color:#0284C7"></i> Solicitar de Bodega Central (100 / 200)
                </button>
            </div>

            <!-- TAB 1: BOBINAS EN BODEGA 3000 -->
            <div id="tab_pane_bodega3000" style="padding:18px;display:block">
                <div style="background:#ECFDF5;border:1px solid #A7F3D0;border-radius:6px;padding:12px 16px;margin-bottom:15px;color:#065F46;font-size:13px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
                    <div>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                            <i class="fa fa-info-circle" style="color:#059669"></i>
                            <b>Bobinas actualmente ubicadas en Bodega 3000 (Planta / Insumos en Producción).</b>
                            <?php if ($isPrinterMachine): ?>
                                <span style="background:#E0F2FE;border:1px solid #BAE6FD;color:#0369A1;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:bold">
                                    <i class="fa fa-filter"></i> Solo Telas Vírgenes (TEL...)
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top:4px;font-size:12px;color:#047857">
                            Estas bobinas ya fueron trasladadas a producción y se muestran solo si son compatibles con la OT seleccionada.
                            <?php if ($isPrinterMachine): ?>
                                <span style="color:#0284C7;font-weight:500">(En Flexografía, Serigrafía y Pulpo se listan exclusivamente telas vírgenes TEL compatibles con la OT).</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <input type="text" id="filter_b3000" placeholder="🔍 Buscar por código, color o descripción..." class="inptxt" style="width:260px;font-size:12px;padding:6px 10px" onkeyup="filterTable('tbl_b3000', this.value)">
                    </div>
                </div>

                <div style="overflow-x:auto">
                    <table id="tbl_b3000" border="0" width="100%" cellpadding="8" cellspacing="0" style="min-width:980px">
                    <colgroup>
                        <col width="140">
                        <col width="90">
                        <col>
                        <col width="100">
                        <col width="110">
                        <col width="100">
                        <col width="110">
                        <col width="130">
                        <col width="140">
                    </colgroup>
                    <thead>
                        <tr>
                            <td class="tdheader">Código / SKU</td>
                            <td class="tdheader" align="center">Material</td>
                            <td class="tdheader">Descripción de Tela</td>
                            <td class="tdheader">Color</td>
                            <td class="tdheader" align="center">Ancho / Gramaje</td>
                            <td class="tdheader" align="right">Metros</td>
                            <td class="tdheader" align="right">Kilos Disp.</td>
                            <td class="tdheader" align="center">Compatibilidad</td>
                            <td class="tdheader" align="center">Acción</td>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($bodega3000Rolls)): ?>
                        <tr>
                            <td colspan="9" class="tdnrm" style="text-align:center;padding:35px 20px;color:#64748B">
                                <i class="fa fa-box-open" style="font-size:28px;color:#94A3B8;margin-bottom:8px;display:block"></i>
                                <?php if ($isPrinterMachine): ?>
                                    No hay bobinas de tela virgen compatibles con esta OT registradas actualmente con stock en Bodega 3000.<br>
                                    Puede cargar una bobina manual en la pestaña <b>"Cargar Sin Trazabilidad"</b> o solicitar telas desde la Bodega Central (100 / 200).
                                <?php else: ?>
                                    No hay bobinas compatibles registradas actualmente en Bodega 3000 para esta OT.<br>
                                    Puede cargar una bobina manual en la pestaña <b>"Cargar Sin Trazabilidad"</b> o solicitar bobinas desde la Bodega Central (100 / 200).
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bodega3000Rolls as $bRoll):
                            $rCode = (string)($bRoll['roll_code'] ?? $bRoll['sku_code'] ?? '-');
                            $rMat = (string)($bRoll['roll_material'] ?? 'TNT');
                            $rDesc = (string)($bRoll['sku_desc'] ?? '-');
                            $rCol = (string)($bRoll['color'] ?? '-');
                            $rW = (float)($bRoll['width_cm'] ?? round(($bRoll['width_mm'] ?? 0) / 10));
                            $rGramms = (int)($bRoll['grammage'] ?? $bRoll['microns'] ?? 80);
                            $rMts = (float)($bRoll['meters'] ?? 0);
                            $rKg = (float)($bRoll['weight_kg'] ?? $bRoll['current_weight_kg'] ?? 0);
                            $isCompat = !empty($bRoll['is_compatible']);
                            $rawId = (string)($bRoll['id'] ?? $bRoll['raw_id'] ?? '');
                            $rIsPrinted = !empty($bRoll['is_printed']);
                            $isBlockedForPrinter = ($isPrinterMachine && $rIsPrinted);
                        ?>
                            <tr style="<?=$isCompat ? 'background-color:#F0FDF4' : ''?>" onmouseover="this.style.backgroundColor='#E6F4EA'" onmouseout="this.style.backgroundColor='<?=$isCompat ? '#F0FDF4' : '#FFFFFF'?>'">
                                <td class="tdnrm">
                                    <b><?=htmlspecialchars($rCode)?></b>
                                    <?php if ($rIsPrinted): ?>
                                        <div style="margin-top:2px"><span style="background:#FEE2E2;color:#991B1B;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:bold"><i class="fa fa-print"></i> Bobina Impresa</span></div>
                                    <?php else: ?>
                                        <div style="margin-top:2px"><span style="background:#F0FDF4;color:#166534;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:bold"><i class="fa fa-check"></i> Bobina Virgen</span></div>
                                    <?php endif; ?>
                                </td>
                                <td class="tdnrm" align="center">
                                    <span style="background:#E2E8F0;color:#1E293B;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:bold"><?=htmlspecialchars($rMat)?></span>
                                </td>
                                <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($rDesc)?></td>
                                <td class="tdnrm"><?=htmlspecialchars($rCol)?></td>
                                <td class="tdnrm" align="center" style="white-space:nowrap">
                                    <b><?=$rW > 0 ? "{$rW} cm" : '-'?></b> &bull; <?=$rGramms > 0 ? "{$rGramms}g" : '-'?>
                                </td>
                                <td class="tdnrm" align="right"><?=$rMts > 0 ? number_format($rMts, 0, ',', '.') . ' m' : '-'?></td>
                                <td class="tdnrm" align="right"><b style="color:#0F766E"><?=number_format($rKg, 1, ',', '.')?> kg</b></td>
                                <td class="tdnrm" align="center">
                                    <?php if ($isBlockedForPrinter): ?>
                                        <span style="background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:bold" title="No apta para Flexo/Serigrafía porque ya fue impresa">
                                            🚫 YA IMPRESA
                                        </span>
                                    <?php elseif ($isCompat): ?>
                                        <span style="background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:bold">
                                            ⭐ COMPATIBLE
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#94A3B8;font-size:11px">Stock General</span>
                                    <?php endif; ?>
                                </td>
                                <td class="tdnrm" align="center">
                                    <?php if ($isBlockedForPrinter): ?>
                                        <button type="button" class="btnblue" style="background:#94A3B8;border-color:#94A3B8;cursor:not-allowed;padding:5px 12px;font-size:12px;opacity:0.7" title="Las máquinas de impresión requieren bobinas vírgenes sin uso previo" disabled>
                                            <i class="fa fa-ban"></i> No Apta
                                        </button>
                                    <?php else: ?>
                                        <form method="post" action="/production/materials/request/assign" style="margin:0" onsubmit="return confirm('¿Confirmar carga de bobina <?=htmlspecialchars(addslashes($rCode))?> (<?=$rKg?> kg) a la máquina y operario?');">
                                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                            <input type="hidden" name="agid" value="<?=$selectedAgId?>">
                                            <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                            <input type="hidden" name="action_mode" value="bodega3000">
                                            <input type="hidden" name="roll_id" value="<?=htmlspecialchars($rawId)?>">
                                            <input type="hidden" name="weight_kg" value="<?=$rKg?>">
                                            <button type="submit" class="btngreen" style="padding:5px 12px;font-size:12px;cursor:pointer">
                                                <i class="fa fa-arrow-circle-right"></i> Cargar a Máquina
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: CARGAR SIN TRAZABILIDAD (SALTAR TRAZABILIDAD) -->
            <div id="tab_pane_manual" style="padding:22px;display:none">
                <div style="background:#FFFBEB;border:1.5px solid #FCD34D;border-radius:6px;padding:16px;margin-bottom:20px;color:#92400E">
                    <div style="font-weight:bold;font-size:14px;margin-bottom:6px;display:flex;align-items:center;gap:8px">
                        <i class="fa fa-bolt" style="font-size:18px;color:#D97706"></i> Carga Directa Sin Trazabilidad (Saltar Trazabilidad Físicamente)
                    </div>
                    <div style="font-size:13px;line-height:1.5">
                        Como recién estamos iniciando la operación del sistema, esta opción le permite <b>saltarse la trazabilidad física por un momento</b> y utilizar bobinas que aún no cuenten con código QR o etiqueta de barras impresa.
                        El material quedará cargado inmediatamente a la máquina <b><?=htmlspecialchars($machineName)?></b> y al operario <b><?=htmlspecialchars($operatorName)?></b> bajo la Bodega 3000, permitiendo continuar la producción sin demoras.
                    </div>
                </div>

                <form method="post" action="/production/materials/request/assign" style="max-width:750px">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$selectedAgId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                    <input type="hidden" name="action_mode" value="manual_sin_trazabilidad">

                    <table border="0" width="100%" cellpadding="8" cellspacing="0">
                    <colgroup><col width="220"><col></colgroup>
                    <tr>
                        <td class="tdleft">Tipo de Material *</td>
                        <td class="tdnrm">
                            <select name="manual_material" class="inptxt" style="width:280px;background:#FFF" required>
                                <option value="TNT" <?=$mat === 'TNT' ? 'selected' : ''?>>TNT (Polipropileno Non-Woven)</option>
                                <option value="PLA" <?=$mat === 'PLA' ? 'selected' : ''?>>PLA (Biodegradable)</option>
                                <option value="PP" <?=$mat === 'PP' ? 'selected' : ''?>>PP (Polipropileno)</option>
                                <option value="OTRO">Otro Material</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Color de la Tela *</td>
                        <td class="tdnrm">
                            <input type="text" name="manual_color" class="inptxt" style="width:280px" value="<?=htmlspecialchars($fabricColor)?>" placeholder="Ej. Blanco, Azul Marino, Negro..." required>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Ancho de la Bobina (cm)</td>
                        <td class="tdnrm">
                            <input type="number" step="0.5" name="manual_width" class="inptxt" style="width:180px" value="<?=$w > 0 ? $w : ''?>" placeholder="Ej. 65"> cm
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Peso Estimado / Kilos (kg) *</td>
                        <td class="tdnrm">
                            <input type="number" step="0.1" name="manual_kg" class="inptxt" style="width:180px" value="85" placeholder="85.0" required> kg
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Metros Lineales Aprox.</td>
                        <td class="tdnrm">
                            <input type="number" step="1" name="manual_meters" class="inptxt" style="width:180px" value="1100" placeholder="1100"> m
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft" valign="top">Comentarios / Observaciones</td>
                        <td class="tdnrm">
                            <textarea name="manual_comments" class="inptxt" style="width:100%;height:60px" placeholder="Lote de fábrica del proveedor, estado del rollo o motivo de bypass de trazabilidad..."></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm" style="padding-top:15px">
                            <button type="submit" class="btngreen" style="font-size:14px;padding:10px 24px;cursor:pointer">
                                <i class="fa fa-play-circle"></i> Cargar a Máquina sin Trazabilidad
                            </button>
                        </td>
                    </tr>
                    </table>
                </form>
            </div>

            <!-- TAB 3: SOLICITAR DE BODEGA CENTRAL -->
            <div id="tab_pane_central" style="padding:18px;display:none">
                <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:6px;padding:12px 16px;margin-bottom:15px;color:#1E40AF;font-size:13px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
                    <div>
                        <i class="fa fa-info-circle"></i> <b>Stock disponible en Bodega Central (100 PLA / 200 PP).</b>
                        Seleccione las bobinas compatibles que requiere <b>traspasar a Bodega 3000</b>. Luego la máquina las tomará desde el stock de producción cuando corresponda.
                    </div>
                    <div>
                        <input type="text" placeholder="🔍 Filtrar stock central..." class="inptxt" style="width:240px;font-size:12px;padding:6px 10px" onkeyup="filterTable('tbl_central', this.value)">
                    </div>
                </div>

                <div style="overflow-x:auto">
                    <table id="tbl_central" border="0" width="100%" cellpadding="8" cellspacing="0" style="min-width:980px">
                    <colgroup>
                        <col width="140">
                        <col width="90">
                        <col>
                        <col width="110">
                        <col width="100">
                        <col width="110">
                        <col width="130">
                        <col width="140">
                    </colgroup>
                    <thead>
                        <tr>
                            <td class="tdheader">Código / SKU</td>
                            <td class="tdheader" align="center">Material</td>
                            <td class="tdheader">Descripción de Tela</td>
                            <td class="tdheader">Bodega Origen</td>
                            <td class="tdheader">Color</td>
                            <td class="tdheader" align="right">Kilos</td>
                            <td class="tdheader" align="center">Compatibilidad</td>
                            <td class="tdheader" align="center">Acción</td>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($compatibleCentralRolls)): ?>
                        <tr><td colspan="8" class="tdnrm" style="text-align:center;padding:30px;color:#94A3B8">No se encontraron bobinas adicionales en las bodegas centrales para las características de esta OT.</td></tr>
                    <?php else: ?>
                        <?php foreach (array_slice($compatibleCentralRolls, 0, 80) as $cRoll):
                            $rCode = (string)($cRoll['roll_code'] ?? $cRoll['sku_code'] ?? '-');
                            $rMat = (string)($cRoll['roll_material'] ?? 'TNT');
                            $rDesc = (string)($cRoll['sku_desc'] ?? '-');
                            $whName = (string)($cRoll['warehouse_name'] ?? 'Bodega Central');
                            $rCol = (string)($cRoll['color'] ?? '-');
                            $rKg = (float)($cRoll['weight_kg'] ?? 0);
                            $isCompat = !empty($cRoll['is_compatible']);
                            $rawId = (string)($cRoll['id'] ?? $cRoll['raw_id'] ?? '');
                        ?>
                            <tr onmouseover="this.style.backgroundColor='#F1F5F9'" onmouseout="this.style.backgroundColor='transparent'">
                                <td class="tdnrm"><b><?=htmlspecialchars($rCode)?></b></td>
                                <td class="tdnrm" align="center"><span style="background:#E2E8F0;padding:2px 6px;border-radius:3px;font-size:11px;font-weight:bold"><?=htmlspecialchars($rMat)?></span></td>
                                <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($rDesc)?></td>
                                <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($whName)?></td>
                                <td class="tdnrm"><?=htmlspecialchars($rCol)?></td>
                                <td class="tdnrm" align="right"><b><?=number_format($rKg, 1, ',', '.')?> kg</b></td>
                                <td class="tdnrm" align="center">
                                    <?php if ($isCompat): ?>
                                        <span style="background:#D1FAE5;color:#065F46;border:1px solid #6EE7B7;padding:2px 6px;border-radius:3px;font-size:11px;font-weight:bold">⭐ Compatible</span>
                                    <?php else: ?>
                                        <span style="color:#94A3B8;font-size:11px">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="tdnrm" align="center">
                                    <form method="post" action="/production/materials/request/assign" style="margin:0">
                                        <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                        <input type="hidden" name="agid" value="<?=$selectedAgId?>">
                                        <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                        <input type="hidden" name="action_mode" value="solicitar_central">
                                        <input type="hidden" name="roll_ids[]" value="<?=htmlspecialchars($rawId)?>">
                                        <button type="submit" class="btnblue" style="padding:4px 10px;font-size:12px;cursor:pointer">
                                            <i class="fa fa-share"></i> Solicitar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    </table>
                </div>
            </div>
        </div>
        </div>

        <div id="material_kind_inks" style="display:<?=$preferredMaterialKind === 'inks' ? 'block' : 'none'?>">
            <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,0.05);margin-bottom:18px">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:14px">
                    <div>
                        <div style="font-size:17px;font-weight:800;color:#1E3A8A;display:flex;align-items:center;gap:9px;margin-bottom:4px">
                            <span style="width:34px;height:34px;border-radius:10px;background:#DBEAFE;color:#1D4ED8;display:inline-flex;align-items:center;justify-content:center;font-size:15px">
                                <i class="fa fa-tint"></i>
                            </span>
                            Solicitud de Tintas para la OT
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <span style="background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;padding:6px 10px;border-radius:999px;font-size:11.5px;font-weight:800">
                            <i class="fa fa-warehouse"></i> Bodega <?=htmlspecialchars((string)$inkWarehouseCode)?>
                        </span>
                        <span style="background:#F8FAFC;color:#334155;border:1px solid #E2E8F0;padding:6px 10px;border-radius:999px;font-size:11.5px;font-weight:800">
                            <i class="fa fa-list"></i> <?=htmlspecialchars((string)$inkItemsCount)?> items con stock
                        </span>
                    </div>
                </div>

                <?php if ($isPulpoRequest): ?>
                    <div style="background:#F8FAFC;border:1px solid #CBD5E1;border-radius:10px;padding:12px 14px;margin-bottom:14px;color:#334155;font-size:12.5px;line-height:1.55">
                        <i class="fa fa-info-circle" style="color:#2563EB"></i>
                        En <b>Pulpo</b>, esta pantalla es solo para <b>solicitar tintas e insumos a bodega</b>. La utilización de tinta por color se configura después en la tabla de <b>10 Unidades de Bastidores / Secadores</b> dentro de la OT.
                    </div>
                <?php endif; ?>

                <div style="display:grid;grid-template-columns:minmax(260px, 1.2fr) minmax(240px, 1fr);gap:12px;margin-bottom:16px">
                    <div style="background:linear-gradient(180deg,#F8FBFF 0%,#EFF6FF 100%);border:1px solid #BFDBFE;border-radius:10px;padding:14px 16px;color:#1E40AF">
                        <div style="font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#2563EB;margin-bottom:6px">Bodega de Solicitud</div>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                            <span style="background:#DBEAFE;color:#1D4ED8;padding:4px 10px;border-radius:999px;font-weight:900;font-size:13px"><?=htmlspecialchars((string)$inkWarehouseCode)?></span>
                            <span style="font-size:13px;font-weight:700"><?=htmlspecialchars($inkWarehouseName)?></span>
                        </div>
                    </div>
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:14px 16px;color:#334155">
                        <div style="font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#475569;margin-bottom:6px">Referencia de la OT</div>
                        <div style="font-size:12.5px;line-height:1.55;color:#475569">
                            <?php if ($inkSuggestion !== ''): ?>
                                <?=htmlspecialchars($inkSuggestion)?>
                            <?php else: ?>
                                Sin referencia de color cargada en la OT.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <form method="post" action="/production/materials/request/assign" style="max-width:980px">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$selectedAgId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                    <input type="hidden" name="action_mode" value="chemical_request">
                    <input type="hidden" name="warehouse_code" value="<?=$inkWarehouseCode?>">
                    <input type="hidden" name="warehouse_name" value="<?=htmlspecialchars($inkWarehouseName)?>">
                    <input type="hidden" name="material_unit" value="Unid.">

                    <table border="0" width="100%" cellpadding="8" cellspacing="0">
                    <colgroup><col width="220"><col></colgroup>
                    <tr>
                        <td class="tdleft">Tinta en Bodega *</td>
                        <td class="tdnrm">
                            <div style="margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                                    <input type="text" id="filter_inks" placeholder="Buscar por codigo, descripcion o stock..." class="inptxt" style="width:100%;max-width:360px;font-size:12px;padding:8px 12px;border-radius:8px;background:#FFF" onkeyup="filterTable('tbl_inks', this.value)">
                                    <span style="font-size:11.5px;color:#64748B">Seleccione una fila para solicitarla.</span>
                                </div>
                            </div>
                            <div style="overflow:auto;max-height:360px;border:1px solid #CBD5E1;border-radius:10px;background:#FFF;box-shadow:inset 0 1px 0 rgba(255,255,255,0.5)">
                                <table id="tbl_inks" border="0" width="100%" cellpadding="9" cellspacing="0" style="min-width:760px">
                                    <colgroup>
                                        <col width="70">
                                        <col width="170">
                                        <col>
                                        <col width="140">
                                        <col width="120">
                                    </colgroup>
                                    <thead style="position:sticky;top:0;background:#F8FAFC;z-index:1;box-shadow:0 1px 0 #E2E8F0">
                                        <tr>
                                            <td class="tdheader" align="center">Sel.</td>
                                            <td class="tdheader">Codigo</td>
                                            <td class="tdheader">Descripcion</td>
                                            <td class="tdheader" align="right">Stock</td>
                                            <td class="tdheader" align="center">Unidad</td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($chemicals)): ?>
                                        <tr>
                                            <td colspan="5" class="tdnrm" style="text-align:center;padding:18px;color:#64748B">
                                                No se encontraron articulos con stock en esta bodega de tintas.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($chemicals as $idx => $chemical): ?>
                                            <?php
                                                $itemCode = trim((string)($chemical['code'] ?? ''));
                                                $itemName = trim((string)($chemical['name'] ?? ''));
                                                $itemUnit = trim((string)($chemical['unit_name'] ?? 'UNID'));
                                                $itemStock = (float)($chemical['available_qty'] ?? 0);
                                                $requestLabel = (string)($chemical['request_label'] ?? '');
                                                $radioId = 'ink_item_' . $idx;
                                            ?>
                                            <tr onclick="var radio=document.getElementById('<?=htmlspecialchars($radioId)?>'); if(radio){ radio.checked = true; }" style="cursor:pointer;border-bottom:1px solid #F1F5F9" onmouseover="this.style.backgroundColor='#F8FBFF'" onmouseout="this.style.backgroundColor=''">
                                                <td class="tdnrm" align="center" style="vertical-align:middle">
                                                    <input type="radio" id="<?=htmlspecialchars($radioId)?>" name="requested_item_text" value="<?=htmlspecialchars($requestLabel)?>" required style="transform:scale(1.1)">
                                                </td>
                                                <td class="tdnrm" style="vertical-align:middle">
                                                    <div style="font-weight:800;color:#0F172A"><?=htmlspecialchars($itemCode !== '' ? $itemCode : '-')?></div>
                                                </td>
                                                <td class="tdnrm" style="vertical-align:middle">
                                                    <div style="color:#0F172A;font-weight:700"><?=htmlspecialchars($itemName !== '' ? $itemName : $requestLabel)?></div>
                                                    <div style="margin-top:2px;font-size:11px;color:#64748B">Disponible en bodega <?=htmlspecialchars((string)$inkWarehouseCode)?></div>
                                                </td>
                                                <td class="tdnrm" align="right" style="vertical-align:middle">
                                                    <span style="display:inline-flex;align-items:center;justify-content:flex-end;min-width:88px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:999px;padding:4px 10px;font-weight:800;color:#0F766E">
                                                        <?=htmlspecialchars(number_format($itemStock, 2, ',', '.'))?>
                                                    </span>
                                                </td>
                                                <td class="tdnrm" align="center" style="vertical-align:middle">
                                                    <span style="display:inline-flex;align-items:center;justify-content:center;background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;border-radius:999px;padding:4px 10px;font-size:11px;font-weight:800">
                                                        <?=htmlspecialchars($itemUnit !== '' ? $itemUnit : 'UNID')?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Cantidad de Unidades *</td>
                        <td class="tdnrm">
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                                <input type="number" step="1" min="1" name="material_qty" class="inptxt" style="width:180px;height:40px;font-weight:800;text-align:center;border-radius:8px" value="1" required>
                                <span style="color:#475569;font-size:12px;font-weight:bold">unidades</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm" style="padding-top:12px">
                            <button type="submit" class="btnblue" style="padding:11px 22px;font-size:13px;font-weight:800;cursor:pointer;border-radius:8px;box-shadow:0 2px 6px rgba(37,99,235,0.18)">
                                <i class="fa fa-paper-plane"></i> Solicitar Tinta a Bodega <?=htmlspecialchars((string)$inkWarehouseCode)?>
                            </button>
                        </td>
                    </tr>
                    </table>
                </form>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- SECCIÓN 3: MATERIALES Y BOBINAS ASIGNADAS A ESTA OT            -->
        <!-- ============================================================== -->
        <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
            <div style="font-size:14px;font-weight:bold;color:#0F766E;margin-bottom:12px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-history"></i> Materiales y Bobinas Asignadas / Consumidas en esta OT
            </div>

            <?php if (empty($assignedRolls)): ?>
                <div style="padding:15px;color:#64748B;font-size:13px;font-style:italic">
                    Aún no hay bobinas asignadas activamente a esta orden de trabajo. Utilice las pestañas superiores para cargar bobinas de Bodega 3000 o realizar una carga sin trazabilidad.
                </div>
            <?php else: ?>
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <thead>
                    <tr>
                        <td class="tdheader">Código / Identificador</td>
                        <td class="tdheader">Descripción</td>
                        <td class="tdheader" align="right">Peso (Kg)</td>
                        <td class="tdheader">Operador</td>
                        <td class="tdheader">Fecha Carga</td>
                        <td class="tdheader" align="center">Estado</td>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($assignedRolls as $ar): ?>
                    <tr>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($ar['roll_code'] ?? '-'))?></b></td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($ar['sku_desc'] ?? '-'))?></td>
                        <td class="tdnrm" align="right"><b><?=number_format((float)($ar['weight_kg'] ?? 0), 1, ',', '.')?> kg</b></td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($ar['operator_name'] ?? $operatorName))?></td>
                        <td class="tdnrm"><?=htmlspecialchars((string)($ar['created_at'] ?? date('d.m.Y H:i')))?></td>
                        <td class="tdnrm" align="center">
                            <span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:bold">CARGADA EN MÁQUINA</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <script>
    function switchMaterialTab(tabName) {
        document.querySelectorAll('.tab-btn').forEach(function(btn) {
            btn.style.background = 'transparent';
            btn.style.color = '#64748B';
            btn.style.borderBottom = '3px solid transparent';
        });
        document.getElementById('tab_pane_bodega3000').style.display = 'none';
        document.getElementById('tab_pane_manual').style.display = 'none';
        document.getElementById('tab_pane_central').style.display = 'none';

        var activeBtn = document.getElementById('tab_btn_' + tabName);
        if (activeBtn) {
            activeBtn.style.background = '#FFF';
            activeBtn.style.color = '#065F46';
            activeBtn.style.borderBottom = '3px solid #00A85A';
        }
        var activePane = document.getElementById('tab_pane_' + tabName);
        if (activePane) {
            activePane.style.display = 'block';
        }
    }

    function switchMaterialKind(kindName) {
        var rollsBtn = document.getElementById('request_kind_btn_rolls');
        var inksBtn = document.getElementById('request_kind_btn_inks');
        var rollsPane = document.getElementById('material_kind_rolls');
        var inksPane = document.getElementById('material_kind_inks');

        if (rollsPane) rollsPane.style.display = (kindName === 'rolls') ? 'block' : 'none';
        if (inksPane) inksPane.style.display = (kindName === 'inks') ? 'block' : 'none';

        if (rollsBtn) {
            rollsBtn.style.background = (kindName === 'rolls') ? '#ecfdf5' : '#fff';
            rollsBtn.style.color = (kindName === 'rolls') ? '#166534' : '#475569';
            rollsBtn.style.borderColor = (kindName === 'rolls') ? '#86efac' : '#cbd5e1';
        }
        if (inksBtn) {
            inksBtn.style.background = (kindName === 'inks') ? '#eff6ff' : '#fff';
            inksBtn.style.color = (kindName === 'inks') ? '#1d4ed8' : '#475569';
            inksBtn.style.borderColor = (kindName === 'inks') ? '#93c5fd' : '#cbd5e1';
        }
    }

    function filterTable(tableId, query) {
        query = query.toLowerCase();
        var rows = document.querySelectorAll('#' + tableId + ' tbody tr');
        rows.forEach(function(tr) {
            var text = tr.innerText.toLowerCase();
            tr.style.display = (text.indexOf(query) !== -1 || query === '') ? '' : 'none';
        });
    }
    </script>
    <?php

