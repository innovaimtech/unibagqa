<?php
    ?>
        <!-- ============================================================== -->
        <!-- EMBALAJE: solo la información del cliente y del embalaje        -->
        <!-- (sin configuración ni insumos de Corte y Sellado)               -->
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

        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;overflow:hidden;margin-bottom:12px">
            <div class="unibag-collapse-header" onclick="toggleCollapseSection('sec_info_emb', 'icon_info_emb')">
                <span><i class="fa fa-fw fa-box"></i> Información de Embalaje</span>
                <span class="unibag-collapse-btn">
                    <span id="lbl_info_emb">Minimizar</span>
                    <i id="icon_info_emb" class="fa fa-chevron-up"></i>
                </span>
            </div>
            <div id="sec_info_emb">
                <?php renderMachineFabricationExtras($agenda, true); ?>
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
            </div>
        </div>

    <?php
