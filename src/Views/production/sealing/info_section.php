<?php
    ?>
        <!-- ============================================================== -->
        <!-- RAMA D2: SELLADORA / CONFECCIÓN / REBOBINADORA (2050 - 2600)   -->
        <!-- ============================================================== -->
        <?php if (!$isRebobinadora && !$isSelladora): ?>
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
                <tr>
                    <td class="tdleft">Materialidad</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                    <td class="tdleft">Fecha Entrega</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)($agenda['req_despacho_desc'] !== '' ? $agenda['req_despacho_desc'] : $agenda['ag_date']))?></b></td>
                </tr>
                <?=renderDesignAndAttachmentsHtml($agenda, (int)($agenda['fab_med_width'] ?? 0) . ' x ' . (int)($agenda['fab_med_height'] ?? 0) . ' x ' . (int)($agenda['fab_med_fuelle'] ?? 0) . ' cm', (string)($agenda['fab_design_name'] ?? $agenda['item_title'] ?? ''), 1, 3, 'Imagen Diseño')?>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_fab', 'icon_info_fab')">
                <span><i class="fa fa-fw fa-info-circle"></i> Información de Fabricación</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_fab">Minimizar</span>
                    <i id="icon_info_fab" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_fab">
                <!-- Encabezado exacto de editxid.php: N° OT, Cliente, N° CC -->
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="160">
                    <col>
                </colgroup>
                <tr>
                    <td class="tdleft" style="background-color:#EEEEEE;">N° O.T.</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)$agenda['prd_number'])?></b></td>
                </tr>
                <tr>
                    <td class="tdleft" style="background-color:#EEEEEE;">Cliente</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                </tr>
                <tr>
                    <td class="tdleft" style="background-color:#EEEEEE;">N° C.C.</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$agenda['req_number'])?></td>
                </tr>
                <?=renderDesignAndAttachmentsHtml($agenda, (int)($agenda['fab_med_width'] ?? 0) . ' x ' . (int)($agenda['fab_med_height'] ?? 0) . ' x ' . (int)($agenda['fab_med_fuelle'] ?? 0) . ' cm', (string)($agenda['fab_design_name'] ?? $agenda['item_title'] ?? ''), 1, 1, 'Imagen Diseño (lo que se está cortando / sellando)')?>
                </table>

                <!-- Tablas de Configuración e Insumos en 2 columnas (editxid.php 2170-2340) -->
                <table border="0" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="left" valign="top" width="50%" style="padding-right:10px">
                        <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                        <colgroup>
                            <col width="160">
                            <col>
                        </colgroup>
                        <tr>
                            <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="2" align="center">
                                <i class="fa fa-fw fa-wrench"></i> Configuración
                            </td>
                        </tr>
                        <tr>
                            <td class="tdleft" style="background-color:#EEEEEE;">Producto</td>
                            <td class="tdnrm"><?=htmlspecialchars((string)$agenda['item_title'])?></td>
                        </tr>
                        <?php if ($isSelladora): ?>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Corte de Bolsa</td>
                                <td class="tdnrm"><b><?=$fmt($corte_m2, 4)?> mtrs</b></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!$reqGenRefId): ?>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Ancho</td>
                                <td class="tdnrm"><?=(int)$agenda['fab_med_width']?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Alto (Tiro)</td>
                                <td class="tdnrm"><?=htmlspecialchars((string)($transcomInfos['alto_tiro'] ?? (string)$agenda['fab_med_height']))?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Alto (Retiro)</td>
                                <td class="tdnrm"><?=htmlspecialchars((string)($transcomInfos['alto_retiro'] ?? (string)$agenda['fab_med_height']))?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Doblez superior</td>
                                <td class="tdnrm"><?=htmlspecialchars((string)($transcomInfos['doblez_superior'] ?? '3'))?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Fuelle</td>
                                <td class="tdnrm"><?=(int)$agenda['fab_med_fuelle']?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Dado de Manillas</td>
                                <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_infoaddprd_dado_manillas'] ?? ''))?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Cabezal</td>
                                <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_infoaddprd_cabezal_act'] ?? ''))?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Alarma</td>
                                <td class="tdnrm"><?=stripos((string)($agenda['dispositivo'] ?? ''), 'ALARMA') !== false ? 'Si' : 'No'?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Código</td>
                                <td class="tdnrm"><?=htmlspecialchars((string)($agenda['item_sellprice_barcodenumber'] ?? ''))?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Etiqueta adhesiva</td>
                                <td class="tdnrm"><?=stripos((string)($agenda['dispositivo'] ?? ''), 'ETIQUETA') !== false ? 'Si' : 'No'?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Código</td>
                                <td class="tdnrm"><?=!empty($agenda['item_sellprice_barcodenumber']) ? 'Si' : 'No'?></td>
                            </tr>
                        <?php endif; ?>
                        </table>
                    </td>
                    <td align="left" valign="top" width="50%" style="padding-left:10px">
                        <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                        <colgroup>
                            <col width="160">
                            <col>
                        </colgroup>
                        <tr>
                            <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="2" align="center">
                                <i class="fa fa-fw fa-cubes"></i> Insumos
                            </td>
                        </tr>
                        <tr>
                            <td class="tdleft" style="background-color:#D5EDEB" colspan="2" align="center">
                                Características de tela
                            </td>
                        </tr>
                        <tr>
                            <td class="tdleft" style="background-color:#EEEEEE;">Materialidad</td>
                            <td class="tdnrm"><?=htmlspecialchars((string)$agenda['fab_type'])?></td>
                        </tr>
                        <tr>
                            <td class="tdleft" style="background-color:#EEEEEE;">Color Tela</td>
                            <td class="tdnrm"><?=htmlspecialchars((string)($agenda['fabric_color'] ?? '-'))?></td>
                        </tr>
                        <tr>
                            <td class="tdleft" style="background-color:#EEEEEE;">Ancho Tela</td>
                            <td class="tdnrm"><?=htmlspecialchars((string)($agenda['req_operador_tela_width'] ?? '-'))?></td>
                        </tr>
                        <tr>
                            <td class="tdleft" style="background-color:#EEEEEE;">Gramaje</td>
                            <td class="tdnrm"><?=$gramaje?> gr</td>
                        </tr>
                        <?php if (!$reqGenRefId): ?>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Cant. Bobinas</td>
                                <td class="tdnrm"><b><?=$fmt($cantBobinas, 2)?></b></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#D5EDEB" colspan="2" align="center">
                                    Características de manillas
                                </td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Color Manilla</td>
                                <td class="tdnrm"><?=htmlspecialchars((string)($agenda['manilla_color'] ?? '-'))?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Ancho de Manillas</td>
                                <td class="tdnrm">
                                    <?php
                                    $anchoMan = trim((string)($transcomInfos['ancho_manillas'] ?? ''));
                                    if ($anchoMan === '') {
                                        foreach ($subproducts as $sp) {
                                            if (strtoupper((string)($sp['subprod_destino'] ?? '')) === 'MANILLAS') {
                                                $anchoMan = (string)(float)($sp['subprod_ancho_cm'] ?? 0);
                                                break;
                                            }
                                        }
                                    }
                                    if ($anchoMan === '' && stripos((string)($agenda['item_title'] ?? ''), 'BOUTIQUE') !== false) {
                                        $anchoMan = '2.5';
                                    }
                                    echo htmlspecialchars($anchoMan !== '' ? $anchoMan : '-');
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Largo de Manillas</td>
                                <td class="tdnrm"><?=(int)($agenda['fab_manilla_length'] ?? 0)?></td>
                            </tr>
                            <tr>
                                <td class="tdleft" style="background-color:#EEEEEE;">Cant. Manillas</td>
                                <td class="tdnrm"><b><?=$fmt($cantManillas, 2)?></b></td>
                            </tr>
                        <?php endif; ?>
                        </table>
                    </td>
                </tr>
                </table>


                <?php if ($isRebobinadora): ?>
                    <?php renderRewindConsoleInfoSection(get_defined_vars()); ?>
                <?php endif; ?>

                <?php if (!$reqGenRefId): ?>
                    <!-- TABLA FECHAS DE ENTREGA (editxid.php 2593-2638) -->
                    <table border="0" width="100%" cellpadding="6" cellspacing="0" style="margin-top:6px">
                    <tr>
                        <td class="tdleft" style="background-color:#165552;color:#FFFFFF" colspan="4" align="center">
                            <i class="fa fa-fw fa-calendar"></i> Fechas de entrega
                        </td>
                    </tr>
                    <?php
                    $planTotal = 0.0;
                    foreach ($entregas as $entPlan) { $planTotal += (float)($entPlan['prodplan_amt'] ?? 0); }
                    $programadaTotal = $planTotal > 0 ? $planTotal : (float)($agenda['ag_amount'] ?? 0);
                    $producidaTotal = (float)($stats['total_produced_cc'] ?? $stats['total_produced_all'] ?? $stats['total_produced_this'] ?? 0);
                    $restanteTotal = max(0.0, $programadaTotal - $producidaTotal);
                    ?>
                    <tr>
                        <td class="tdleft" style="background-color:#EEEEEE;width:calc(50% - 150px)">Cantidad programada</td>
                        <td class="tdnrm" style="width:150px"><b><?=$fmt($programadaTotal)?></b></td>
                        <td class="tdleft" style="background-color:#EEEEEE;width:calc(50% - 150px)">Cantidad producida</td>
                        <td class="tdnrm" style="width:150px"><b><?=$fmt($producidaTotal)?></b></td>
                    </tr>
                    <?php
                    $hasEntregasPlan = false;
                    $porAsignar = $producidaTotal;
                    foreach ($entregas as $entrega):
                        $hasEntregasPlan = true;
                        $entDate = (int)($entrega['prodplan_date'] ?? 0);
                        $entDateStr = $entDate > 0 ? date('d.m.Y', $entDate) : '-';
                        $entAmt = (float)($entrega['prodplan_amt'] ?? 0);
                        $entCubierta = min($entAmt, max(0.0, $porAsignar));
                        $porAsignar -= $entCubierta;
                        $entRestante = max(0.0, $entAmt - $entCubierta);
                    ?>
                        <tr>
                            <td class="tdleft" style="background-color:#EEEEEE;width:calc(50% - 150px)">Cantidad de Bolsas (restantes)</td>
                            <td class="tdnrm" style="width:150px"><b><?=$fmt($entRestante)?></b></td>
                            <td class="tdleft" style="background-color:#EEEEEE;width:calc(50% - 150px)">Fechas de Entrega</td>
                            <td class="tdnrm" style="width:150px"><?=$entDateStr?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($hasEntregasPlan): ?>
                        <tr>
                            <td class="tdleft" style="background-color:#D5EDEB;width:calc(50% - 150px)">Total restante por producir</td>
                            <td class="tdnrm" style="width:150px"><b><?=$fmt($restanteTotal)?></b></td>
                            <td class="tdleft" style="background-color:#D5EDEB;width:calc(50% - 150px)">&nbsp;</td>
                            <td class="tdnrm" style="width:150px">&nbsp;</td>
                        </tr>
                    <?php endif; ?>
                    <?php if (!$hasEntregasPlan): ?>
                        <tr>
                            <td class="tdleft" align="center" colspan="4"><b class="msg_save_err" style="color:#C00;">No hay fechas de entrega registradas</b></td>
                        </tr>
                    <?php endif; ?>
                    </table>
                <?php endif; ?>
                <?php renderMachineFabricationExtras($agenda, !$isRebobinadora); ?>
            </div>
        </div>

    <?php

