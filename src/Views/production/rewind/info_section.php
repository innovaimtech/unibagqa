<?php
    ?>
                    <!-- TABLA TAREA REBOBINADORA Y DISTRIBUCIONES (editxid.php 2370-2600) -->
                    <?php
                    $reborder = !empty($reboOrder) ? $reboOrder : $agenda;
                    if (!empty($reborder['req_rebo_type'])):
                    ?>
                        <div style="height:10px"></div>
                        <table border="0" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td colspan="2" style="background-color:#1AAAA4;color:#FFFFFF;padding:6px;font-weight:bold;text-align:center">
                                <i class="fa fa-fw fa-wrench"></i> Tarea Rebobinadora
                            </td>
                        </tr>
                        <tr>
                            <td align="left" valign="top" width="50%" style="padding-right:10px">
                                <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                                <colgroup>
                                    <col width="160">
                                    <col>
                                </colgroup>
                                <tr>
                                    <td class="tdleft" style="background-color:#EEEEEE;">Tarea</td>
                                    <td class="tdnrm"><b><?=htmlspecialchars((string)$reborder['req_rebo_type'])?></b></td>
                                </tr>
                                <tr>
                                    <td class="tdleft" style="background-color:#EEEEEE;">Tipo</td>
                                    <td class="tdnrm"><?=htmlspecialchars((string)(!empty($reborder['req_rebo_rolloscc_opttype']) ? $reborder['req_rebo_rolloscc_opttype'] : 'Por rollo'))?></td>
                                </tr>
                                <tr>
                                    <td class="tdleft" style="background-color:#EEEEEE;">Rollos</td>
                                    <td class="tdnrm">
                                        <?php
                                        $opt = (string)($reborder['req_rebo_rolloscc_opt'] ?? '');
                                        if ($opt === '100cm_manillas') {
                                            echo 'Rollos de 100cm para manillas';
                                        } elseif ($opt === '100cm_algunos_metros') {
                                            echo 'Rollos de 100cm (algunos metros) a rollo de 76cm más manillas y restante debe mantener código';
                                        } elseif ($opt === 'solo_rebobinar') {
                                            echo 'Rollos solo rebobinar';
                                        } elseif (!empty($reborder['req_rebo_cortescc']) || count($subproducts) > 0 || !empty($reboValues['rollo'])) {
                                            $telaW = (int)($reborder['req_operador_tela_width'] ?? $agenda['req_operador_tela_width'] ?? 100);
                                            if ($telaW <= 0) $telaW = 100;
                                            $bagW = 0;
                                            foreach ($subproducts as $sp) {
                                                if (strtoupper((string)($sp['subprod_destino'] ?? '')) !== 'MANILLAS') {
                                                    $bagW = (int)$sp['subprod_ancho_cm'];
                                                    break;
                                                }
                                            }
                                            if ($bagW <= 0) {
                                                foreach ($reboValues['rollo'] ?? [] as $rv) {
                                                    $w = (int)($rv['rollo_dims'] ?? 0);
                                                    if ($w > 50) { $bagW = $w; break; }
                                                }
                                            }
                                            if ($bagW > 0) {
                                                echo "Rollos de {$telaW}cm a rollo de {$bagW}cm más manillas y restante debe mantener código";
                                            } else {
                                                echo "Rollos de {$telaW}cm para corte más manillas";
                                            }
                                        } else {
                                            echo '- - -';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                </table>
                            </td>
                            <td align="left" valign="top" width="50%" style="padding-left:10px">
                                <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                                <colgroup>
                                    <col width="160">
                                    <col>
                                </colgroup>
                                <tr>
                                    <td class="tdleft" style="background-color:#EEEEEE;">Estado</td>
                                    <td class="tdnrm"><?=htmlspecialchars((string)($reborder['req_rebo_state'] ?? ''))?></td>
                                </tr>
                                <tr>
                                    <td class="tdleft" style="background-color:#EEEEEE;">Cantidad de cortes</td>
                                    <td class="tdnrm"><?=(float)($reborder['req_rebo_cortescc'] ?? 0) > 0 ? $fmt($reborder['req_rebo_cortescc']) : '- - -'?></td>
                                </tr>
                                <tr>
                                    <td class="tdleft" style="background-color:#EEEEEE;">&nbsp;</td>
                                    <td class="tdnrm">&nbsp;</td>
                                </tr>
                                </table>
                            </td>
                        </tr>
                        </table>

                        <?php if (($reborder['req_rebo_rolloscc_opttype'] ?? '') === 'Por metro'): ?>
                            <!-- MODO METRO: Distribución solicitado y Largos -->
                            <div style="height:6px"></div>
                            <table border="0" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td align="left" valign="top" width="50%" style="padding-right:10px">
                                    <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                                    <colgroup>
                                        <col width="160">
                                        <col>
                                        <col>
                                    </colgroup>
                                    <tr>
                                        <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="3" align="center">
                                            <i class="fa fa-fw fa-info"></i> Distribución solicitado
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Cantidad</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Medida</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Subtotal</td>
                                    </tr>
                                    <?php
                                    $rebovalsMetro = array_values($reboValues['metro'] ?? []);
                                    $xtotalMetro = 0.0;
                                    for ($x = 0; $x < count($rebovalsMetro); $x++):
                                        $amt = (float)($rebovalsMetro[$x]['rollo_amt'] ?? 0);
                                        $dims = (float)($rebovalsMetro[$x]['rollo_dims'] ?? 0);
                                        $subtotal = $amt * $dims;
                                    ?>
                                        <tr>
                                            <td class="tdnrm"><?=(int)($rebovalsMetro[$x]['id'] ?? 0) ? $fmt($amt) : ''?></td>
                                            <td class="tdnrm"><?=(int)($rebovalsMetro[$x]['id'] ?? 0) ? $fmt($dims, 2) : ''?></td>
                                            <td class="tdnrm"><?=(int)($rebovalsMetro[$x]['id'] ?? 0) ? $fmt($subtotal, 2) : ''?></td>
                                        </tr>
                                    <?php
                                        $xtotalMetro += $subtotal;
                                    endfor;
                                    ?>
                                    <tr>
                                        <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Total</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;"><b><?=$fmt($xtotalMetro, 2)?></b></td>
                                    </tr>
                                    </table>
                                </td>
                                <td align="left" valign="top" width="50%" style="padding-left:10px">
                                    <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                                    <tr>
                                        <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="5" align="center">
                                            <i class="fa fa-fw fa-info"></i> Largos
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Materialidad</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Color</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Gramaje</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Ancho</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Largo</td>
                                    </tr>
                                    <?php
                                    $rebovalsMetroAmt = array_values($reboValues['metro_amt'] ?? []);
                                    $xtotalMetroAmt = 0.0;
                                    for ($x = 0; $x < count($rebovalsMetroAmt); $x++):
                                        $amt = (float)($rebovalsMetroAmt[$x]['rollo_amt'] ?? 0);
                                    ?>
                                        <tr>
                                            <td class="tdnrm"><?php if ($x === 0) echo htmlspecialchars((string)$agenda['fab_type']); ?></td>
                                            <td class="tdnrm"><?php if ($x === 0) echo htmlspecialchars((string)($agenda['fabric_color'] ?? '-')); ?></td>
                                            <td class="tdnrm"><?php if ($x === 0) echo $fmt($agenda['fab_mat_gramms']) . ' gr'; ?></td>
                                            <td class="tdnrm"><?php if ($x === 0) echo htmlspecialchars((string)($reborder['req_operador_tela_width'] ?? $agenda['req_operador_tela_width'] ?? '-')); ?></td>
                                            <td class="tdnrm"><?=(int)($rebovalsMetroAmt[$x]['id'] ?? 0) ? $fmt($amt) : ''?></td>
                                        </tr>
                                    <?php
                                        $xtotalMetroAmt += $amt;
                                    endfor;
                                    ?>
                                    <tr>
                                        <td class="tdleft" style="background-color:#EEEEEE;" colspan="4">Total</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;"><b><?=$fmt($xtotalMetroAmt, 0)?></b></td>
                                    </tr>
                                    </table>
                                </td>
                            </tr>
                            </table>
                            <div style="height:6px"></div>
                        <?php endif; ?>

                        <?php if (($reborder['req_rebo_rolloscc_opttype'] ?? '') !== 'Por metro'): ?>
                            <!-- MODO ROLLO: Distribución solicitado y Rollos -->
                            <div style="height:6px"></div>
                            <table border="0" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td align="left" valign="top" width="50%" style="padding-right:10px">
                                    <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                                    <colgroup>
                                        <col width="160">
                                        <col>
                                        <col>
                                    </colgroup>
                                    <tr>
                                        <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="3" align="center">
                                            <i class="fa fa-fw fa-info"></i> Distribución solicitado
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Cantidad</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Medida</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Subtotal</td>
                                    </tr>
                                    <?php
                                    $rolloVals = array_values($reboValues['rollo'] ?? []);
                                    if (empty($rolloVals) && !empty($subproducts)) {
                                        foreach ($subproducts as $sp) {
                                            $amt = (float)($sp['subprod_amount'] ?? 0);
                                            $dims = (float)($sp['subprod_ancho_cm'] ?? 0);
                                            if ($amt > 0 && $dims > 0) {
                                                $rolloVals[] = [
                                                    'id' => 1,
                                                    'rollo_amt' => $amt,
                                                    'rollo_dims' => $dims,
                                                ];
                                            }
                                        }
                                    }
                                    $xtotalRollo = 0.0;
                                    for ($x = 0; $x < count($rolloVals); $x++):
                                        $amt = (float)($rolloVals[$x]['rollo_amt'] ?? 0);
                                        $dims = (float)($rolloVals[$x]['rollo_dims'] ?? 0);
                                        $subtotal = $amt * $dims;
                                    ?>
                                        <tr>
                                            <td class="tdnrm"><?=(int)($rolloVals[$x]['id'] ?? 0) ? $fmt($amt) : ''?></td>
                                            <td class="tdnrm"><?=(int)($rolloVals[$x]['id'] ?? 0) ? $fmt($dims, 2) : ''?></td>
                                            <td class="tdnrm"><?=(int)($rolloVals[$x]['id'] ?? 0) ? $fmt($subtotal, 2) : ''?></td>
                                        </tr>
                                    <?php
                                        $xtotalRollo += $subtotal;
                                    endfor;
                                    ?>
                                    <tr>
                                        <td class="tdleft" style="background-color:#EEEEEE;" colspan="2">Total</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;"><b><?=$fmt($xtotalRollo, 2)?></b></td>
                                    </tr>
                                    </table>
                                </td>
                                <td align="left" valign="top" width="50%" style="padding-left:10px">
                                    <table border="0" width="100%" cellpadding="6" cellspacing="0" style="border-left:1px solid #DDDDDD;border-right:1px solid #DDDDDD">
                                    <tr>
                                        <td class="tdleft" style="background-color:#1AAAA4;color:#FFFFFF" colspan="5" align="center">
                                            <i class="fa fa-fw fa-info"></i> Rollos
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Cantidad de rollos</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Materialidad</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Color</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Gramaje</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;">Ancho</td>
                                    </tr>
                                    <?php
                                    $rolloAmtVals = array_values($reboValues['rollo_amt'] ?? []);
                                    if (empty($rolloAmtVals)) {
                                        $cantRollosMadre = (float)($reborder['req_rebo_rolloscc'] ?? 1);
                                        if ($cantRollosMadre <= 0) $cantRollosMadre = 1.0;
                                        $rolloAmtVals[] = [
                                            'id' => 1,
                                            'rollo_amt' => $cantRollosMadre,
                                        ];
                                    }
                                    $xtotalRolloAmt = 0.0;
                                    for ($x = 0; $x < count($rolloAmtVals); $x++):
                                        $amt = (float)($rolloAmtVals[$x]['rollo_amt'] ?? 0);
                                    ?>
                                        <tr>
                                            <td class="tdnrm"><?=(int)($rolloAmtVals[$x]['id'] ?? 0) ? $fmt($amt) : ''?></td>
                                            <td class="tdnrm"><?php if ($x === 0) echo htmlspecialchars((string)$agenda['fab_type']); ?></td>
                                            <td class="tdnrm"><?php if ($x === 0) echo htmlspecialchars((string)($agenda['fabric_color'] ?? '-')); ?></td>
                                            <td class="tdnrm"><?php if ($x === 0) echo $fmt($agenda['fab_mat_gramms']) . ' gr'; ?></td>
                                            <td class="tdnrm"><?php if ($x === 0) echo htmlspecialchars((string)($reborder['req_operador_tela_width'] ?? $agenda['req_operador_tela_width'] ?? '-')); ?></td>
                                        </tr>
                                    <?php
                                        $xtotalRolloAmt += $amt;
                                    endfor;
                                    ?>
                                    <tr>
                                        <td class="tdleft" style="background-color:#EEEEEE;" colspan="4">Total</td>
                                        <td class="tdleft" style="background-color:#EEEEEE;"><b><?=$fmt($xtotalRolloAmt, 0)?></b></td>
                                    </tr>
                                    </table>
                                </td>
                            </tr>
                            </table>
                            <div style="height:6px"></div>
                        <?php endif; ?>
                    <?php endif; ?>

    <?php

