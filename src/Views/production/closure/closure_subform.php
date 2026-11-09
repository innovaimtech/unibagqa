<?php
    ?>
        <!-- SUBFORMULARIO: Cierre de OT con Supervisor (fancy.embalaje.termot.php) -->
        <?php 
        $useCloseSupervisorModal = true; 
        $prodStarted = $prodService->hasWorkOrderProductionStarted($workerOtId);
        ?>
        <?php if (!$useCloseSupervisorModal): ?>
            <div style="margin-bottom:15px">
                <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                    <i class="fa fa-fw fa-arrow-left"></i> Cancelar y Volver a la OT
                </div>
            </div>
        <?php endif; ?>
        <div style="<?= $useCloseSupervisorModal ? 'padding:0;max-width:none;margin:0;border:none;background:transparent' : 'background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:25px;max-width:700px;margin:0 auto' ?>">
            <?php if (!$useCloseSupervisorModal): ?>
                <div style="font-size:18px;font-weight:bold;color:<?=!$prodStarted ? '#059669' : '#D64141'?>;margin-bottom:10px;text-align:center">
                    <i class="fa fa-fw <?=!$prodStarted ? 'fa-check-circle' : 'fa-lock'?>"></i> <?=!$prodStarted ? 'Terminar Orden de Trabajo (Sin Producción)' : 'Finalizar y Cerrar Orden de Trabajo'?>
                </div>
                <div style="text-align:center;color:#666;margin-bottom:20px;font-size:13px">
                    <?php if (!$prodStarted): ?>
                        Esta OT #<b><?=htmlspecialchars((string)$agenda['prd_number'])?></b> no registra producción iniciada. Puede cerrarla directamente.
                    <?php else: ?>
                        Se requiere autorización de Supervisor para cerrar la OT #<b><?=htmlspecialchars((string)$agenda['prd_number'])?></b>.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($isSeriPulpo && !$useCloseSupervisorModal): ?>
                <div style="background:#F8FAFC;border:1px solid #CBD5E1;border-radius:5px;padding:14px 18px;margin-bottom:18px">
                    <div style="font-size:13px;font-weight:bold;color:#1E293B;margin-bottom:8px">
                        <i class="fa fa-layer-group" style="color:#008A87"></i> Balance de Producción Serigrafía Pulpo al Cierre:
                    </div>
                    <table border="0" width="100%" cellpadding="4" cellspacing="0" style="font-size:12.5px">
                        <tr>
                            <td style="color:#64748B" width="220">Pasadas Frente acumuladas:</td>
                            <td><b><?=number_format($prevFrente, 0, ',', '.')?></b> pasadas</td>
                        </tr>
                        <tr>
                            <td style="color:#64748B">Pasadas Dorso acumuladas:</td>
                            <td><b><?=number_format($prevDorso, 0, ',', '.')?></b> pasadas</td>
                        </tr>
                        <tr>
                            <td style="color:#64748B">Bolsas terminadas completas:</td>
                            <td><b style="color:#059669"><?=number_format($prevFinished, 0, ',', '.')?></b> bolsas</td>
                        </tr>
                        <tr>
                            <td style="color:#64748B">Bolsas sin terminar al cierre:</td>
                            <td>
                                <?php if ($prevSurplusFrente > 0): ?>
                                    <span style="color:#D97706;font-weight:bold">
                                        <i class="fa fa-exclamation-triangle"></i> <?=number_format($prevSurplusFrente, 0, ',', '.')?> bolsas con Frente impreso (pendientes de dorso). Quedarán guardadas para el próximo ingreso.
                                    </span>
                                <?php elseif ($prevSurplusDorso > 0): ?>
                                    <span style="color:#D97706;font-weight:bold">
                                        <i class="fa fa-exclamation-triangle"></i> <?=number_format($prevSurplusDorso, 0, ',', '.')?> bolsas con Dorso impreso (pendientes de frente). Quedarán guardadas para el próximo ingreso.
                                    </span>
                                <?php else: ?>
                                    <span style="color:#059669;font-weight:bold">
                                        <i class="fa fa-check-circle"></i> 0 bolsas pendientes (Frente y Dorso al día).
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>

            <?php
            // Balance general de producción y mermas de la OT al cierre
            $reboCloseMoves = $isRebobinadora ? $prodService->getReboFinishedMoves($agId) : [];
            $otSummaryProduced = 0.0;
            $otSummaryMlin = 0.0;
            $otSummaryKg = 0.0;
            try {
                $stSumProd = Db::erpPdo()->prepare("
                    SELECT SUM(evt_amount) AS total_amt, 
                           SUM(evt_amount_metros_lineales) AS total_mlin, 
                           SUM(prod_bobina_kg) AS total_kg
                    FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :ot_id
                      AND evt_type IN ('prod', 'prodsericolor')
                      AND evt_status > 0
                      AND (evt_comments IS NULL OR evt_comments NOT LIKE '%Bobina Semielaborada%')
                ");
                $stSumProd->execute([':ot_id' => $workerOtId]);
                $rSumProd = $stSumProd->fetch(PDO::FETCH_ASSOC);
                if ($rSumProd) {
                    $otSummaryProduced = (float)($rSumProd['total_amt'] ?? 0);
                    $otSummaryMlin = (float)($rSumProd['total_mlin'] ?? 0);
                    $otSummaryKg = (float)($rSumProd['total_kg'] ?? 0);
                }
            } catch (Throwable) {}

            $prSummary = $prodService->getPrintedRollsSummary($workerOtId);

            $otSummaryWasteKg = 0.0;
            $otWasteBreakdown = [];
            $otProducedDetail = [];
            $otWasteDetail = [];
            $otSummaryProducedDisplay = 0.0;
            $parseSeriProdMeta = static function (string $commentsText): array {
                $result = ['imp_des' => 0, 'img_bast' => 0, 'base' => 0];
                if ($commentsText === '') {
                    return $result;
                }
                if (preg_match('/\bimp\/des=(\d+(?:\.\d+)?)\b/i', $commentsText, $mImp)) {
                    $result['imp_des'] = (float)($mImp[1] ?? 0);
                }
                if (preg_match('/\bimg\/bast=(\d+(?:\.\d+)?)\b/i', $commentsText, $mBast)) {
                    $result['img_bast'] = (float)($mBast[1] ?? 0);
                }
                if (preg_match('/\bbase=(\d+(?:\.\d+)?)\b/i', $commentsText, $mBase)) {
                    $result['base'] = (float)($mBase[1] ?? 0);
                }
                return $result;
            };
            try {
                $stProdDetail = Db::erpPdo()->prepare("
                    SELECT id,
                           evt_type,
                           evt_amount,
                           evt_amount2,
                           evt_amount_metros_lineales,
                           prod_bobina_kg,
                           evt_comments,
                           evt_crtdat
                    FROM prod_worker_ot_events
                    WHERE evt_prod_worker_otid = :ot_id
                      AND evt_type IN ('prod', 'prodsericolor')
                      AND evt_status > 0
                      AND (evt_comments IS NULL OR evt_comments NOT LIKE '%Bobina Semielaborada%')
                    ORDER BY evt_crtdat ASC, id ASC
                ");
                $stProdDetail->execute([':ot_id' => $workerOtId]);
                while ($pRow = $stProdDetail->fetch(PDO::FETCH_ASSOC)) {
                    $commentsText = (string)($pRow['evt_comments'] ?? '');
                    $isSeriDetail = ($isSeri || $isSeriPulpo || (string)($pRow['evt_type'] ?? '') === 'prodsericolor' || str_contains($commentsText, '[SERI_COUNTERS:'));
                    $meta = $parseSeriProdMeta($commentsText);
                    $displayCounter = 0.0;
                    $displayDevPrints = 0.0;
                    $displayUnits = (float)($pRow['evt_amount'] ?? 0);
                    if ($isSeriDetail) {
                        $displayCounter = (float)($pRow['evt_amount2'] ?? 0);
                        if ($displayCounter <= 0 && (float)$meta['base'] > 0) {
                            $displayCounter = (float)$meta['base'];
                        }
                        $displayDevPrints = (float)$meta['img_bast'];
                        if ($displayDevPrints <= 0) {
                            $displayDevPrints = (float)$meta['imp_des'];
                        }
                        if ($displayDevPrints <= 0) {
                            $displayDevPrints = max(1.0, (float)($agenda['req_operador_bastidoramt'] ?? 1));
                        }
                        if ($displayUnits <= 0 && $displayCounter > 0) {
                            $displayUnits = $displayCounter * $displayDevPrints;
                        }
                    }
                    $pRow['display_is_seri'] = $isSeriDetail ? 1 : 0;
                    $pRow['display_counter'] = $displayCounter;
                    $pRow['display_devprints'] = $displayDevPrints;
                    $pRow['display_units'] = $displayUnits;
                    $otSummaryProducedDisplay += $displayUnits;
                    $otProducedDetail[] = $pRow;
                }
            } catch (Throwable) {}
            if ($otSummaryProducedDisplay <= 0) {
                $otSummaryProducedDisplay = $otSummaryProduced;
            }
            try {
                $stSumWaste = Db::erpPdo()->prepare("
                    SELECT COALESCE(t2.merma_title, 'Merma') AS merma_title,
                           SUM(d.evt_kgstounits) AS kgs
                    FROM prod_worker_ot_defectunits d
                    INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                    LEFT JOIN prod_mermatypes t2 ON d.evt_merma_typeid = t2.id
                    WHERE e.evt_prod_worker_otid = :ot_id
                      AND d.evt_status > 0
                    GROUP BY d.evt_merma_typeid, t2.merma_title
                    HAVING kgs > 0
                ");
                $stSumWaste->execute([':ot_id' => $workerOtId]);
                while ($wRow = $stSumWaste->fetch(PDO::FETCH_ASSOC)) {
                    $wKgs = (float)$wRow['kgs'];
                    $otSummaryWasteKg += $wKgs;
                    $otWasteBreakdown[] = htmlspecialchars($wRow['merma_title']) . ': <b>' . number_format($wKgs, 2, ',', '.') . ' kg</b>';
                }
            } catch (Throwable) {}
            try {
                $stWasteDetail = Db::erpPdo()->prepare("
                    SELECT COALESCE(t2.merma_title, 'Merma') AS merma_title,
                           d.evt_amount,
                           d.evt_kgstounits,
                           d.evt_mtstounits,
                           d.evt_comments,
                           d.evt_crtdat
                    FROM prod_worker_ot_defectunits d
                    INNER JOIN prod_worker_ot_events e ON d.evt_refid = e.id
                    LEFT JOIN prod_mermatypes t2 ON d.evt_merma_typeid = t2.id
                    WHERE e.evt_prod_worker_otid = :ot_id
                      AND d.evt_status > 0
                    ORDER BY d.evt_crtdat ASC, d.id ASC
                ");
                $stWasteDetail->execute([':ot_id' => $workerOtId]);
                while ($wRow = $stWasteDetail->fetch(PDO::FETCH_ASSOC)) {
                    $otWasteDetail[] = $wRow;
                }
            } catch (Throwable) {}
            ?>
            <?php if ($useCloseSupervisorModal): ?>
                <div id="close_ot_supervisor_modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.62);z-index:99999;align-items:center;justify-content:center;padding:18px;box-sizing:border-box">
                    <div style="background:#FFFFFF;border-radius:12px;box-shadow:0 24px 48px rgba(0,0,0,0.3);width:100%;max-width:1100px;max-height:92vh;border:1px solid #CBD5E1;overflow:hidden;display:flex;flex-direction:column">
                        <div style="background:<?=!$prodStarted ? '#047857' : '#991B1B'?>;color:#FFFFFF;padding:16px 20px;font-size:17px;font-weight:bold;display:flex;align-items:center;justify-content:space-between;gap:12px">
                            <span><i class="fa <?=!$prodStarted ? 'fa-check-circle' : 'fa-lock'?>"></i> <?=!$prodStarted ? 'Terminar OT (Sin Producción Iniciada)' : 'Cierre de OT con Supervisor'?></span>
                            <button type="button" onclick="closeCloseOtSupervisorModal()" style="background:transparent;border:none;color:#FFFFFF;font-size:22px;cursor:pointer;line-height:1">&times;</button>
                        </div>
                        <div style="padding:18px 20px 6px;color:#475569;font-size:13px">
                            <?php if (!$prodStarted): ?>
                                <div style="background:#ECFDF5;border:1px solid #A7F3D0;border-radius:6px;padding:12px 16px;color:#065F46;font-size:13px;display:flex;align-items:center;gap:10px">
                                    <i class="fa fa-info-circle" style="font-size:20px;color:#059669;flex-shrink:0"></i>
                                    <div>
                                        <b>Esta orden de trabajo no registra producción iniciada</b> (0 unidades/metros/kg y sin mermas).<br>
                                        Puede terminarla directamente para liberar la máquina sin necesidad de llamar al supervisor.
                                    </div>
                                </div>
                            <?php else: ?>
                                OT #<b><?=htmlspecialchars((string)$agenda['prd_number'])?></b>. La orden registra producción o mermas; ingrese las credenciales del supervisor para autorizar el término de la OT.
                            <?php endif; ?>
                        </div>
                        <div style="padding:0 20px 8px;overflow:auto;flex:1 1 auto">
                            <?php if ($isSeriPulpo): ?>
                                <div style="background:#F8FAFC;border:1px solid #CBD5E1;border-radius:5px;padding:14px 18px;margin-bottom:16px">
                                    <div style="font-size:13px;font-weight:bold;color:#1E293B;margin-bottom:8px">
                                        <i class="fa fa-layer-group" style="color:#008A87"></i> Balance de Producción Serigrafía Pulpo al Cierre
                                    </div>
                                    <table border="0" width="100%" cellpadding="4" cellspacing="0" style="font-size:12.5px">
                                        <tr>
                                            <td style="color:#64748B" width="220">Pasadas Frente acumuladas:</td>
                                            <td><b><?=number_format($prevFrente, 0, ',', '.')?></b> pasadas</td>
                                        </tr>
                                        <tr>
                                            <td style="color:#64748B">Pasadas Dorso acumuladas:</td>
                                            <td><b><?=number_format($prevDorso, 0, ',', '.')?></b> pasadas</td>
                                        </tr>
                                        <tr>
                                            <td style="color:#64748B">Bolsas terminadas completas:</td>
                                            <td><b style="color:#059669"><?=number_format($prevFinished, 0, ',', '.')?></b> bolsas</td>
                                        </tr>
                                        <tr>
                                            <td style="color:#64748B">Bolsas sin terminar al cierre:</td>
                                            <td>
                                                <?php if ($prevSurplusFrente > 0): ?>
                                                    <span style="color:#D97706;font-weight:bold">
                                                        <i class="fa fa-exclamation-triangle"></i> <?=number_format($prevSurplusFrente, 0, ',', '.')?> bolsas con Frente impreso (pendientes de dorso). Quedarán guardadas para el próximo ingreso.
                                                    </span>
                                                <?php elseif ($prevSurplusDorso > 0): ?>
                                                    <span style="color:#D97706;font-weight:bold">
                                                        <i class="fa fa-exclamation-triangle"></i> <?=number_format($prevSurplusDorso, 0, ',', '.')?> bolsas con Dorso impreso (pendientes de frente). Quedarán guardadas para el próximo ingreso.
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color:#059669;font-weight:bold">
                                                        <i class="fa fa-check-circle"></i> 0 bolsas pendientes (Frente y Dorso al día).
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            <?php endif; ?>
                            <div style="background:#F8FAFC;border:1px solid #CBD5E1;border-radius:5px;padding:14px 18px;margin-bottom:16px">
                                <div style="font-size:13px;font-weight:bold;color:#1E293B;margin-bottom:8px">
                                    <i class="fa fa-balance-scale" style="color:#008A87"></i> Resumen Acumulado de la OT al Cierre
                                </div>
                                <table border="0" width="100%" cellpadding="4" cellspacing="0" style="font-size:12.5px">
                                    <?php if ($isFlexo): ?>
                                        <tr>
                                            <td style="color:#64748B" width="220">Metros Lineales terminados:</td>
                                            <td><b style="color:#059669"><?=number_format($prSummary['total_meters'] > 0 ? $prSummary['total_meters'] : $otSummaryMlin, 0, ',', '.')?></b> mtrs <?=($prSummary['total_weight_kg'] > 0 ? ' (' . number_format($prSummary['total_weight_kg'], 2, ',', '.') . ' kg)' : ($otSummaryKg > 0 ? ' (' . number_format($otSummaryKg, 2, ',', '.') . ' kg)' : ''))?></td>
                                        </tr>
                                        <?php if ($prSummary['rolls_count'] > 0): ?>
                                            <tr>
                                                <td style="color:#64748B">Bobinas Impresas producidas:</td>
                                                <td>
                                                    <b style="color:#1E293B"><?=number_format($prSummary['total_weight_kg'], 2, ',', '.')?> kg</b>
                                                    <span style="font-size:11.5px;color:#64748B;margin-left:6px">(<?=$prSummary['rolls_count']?> bobina<?=$prSummary['rolls_count'] > 1 ? 's' : ''?>: <?=implode(', ', array_map(fn($r) => $r['roll_code'], $prSummary['rolls_list']))?>)</span>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td style="color:#64748B" width="220">Producción total registrada:</td>
                                            <td><b style="color:#059669"><?=number_format($otSummaryProducedDisplay, 0, ',', '.')?></b> unidades / bolsas</td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td style="color:#64748B">Total Mermas acumuladas:</td>
                                        <td>
                                            <?php if ($otSummaryWasteKg > 0): ?>
                                                <b style="color:#DC2626"><?=number_format($otSummaryWasteKg, 2, ',', '.')?> kg</b>
                                                <?php if (!empty($otWasteBreakdown)): ?>
                                                    <span style="font-size:11.5px;color:#64748B;margin-left:6px">(<?=implode(', ', $otWasteBreakdown)?>)</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color:#64748B">0.00 kg</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;padding:14px 18px;margin-bottom:16px">
                                <div style="font-size:13px;font-weight:bold;color:#1E293B;margin-bottom:10px;display:flex;align-items:center;gap:8px">
                                    <i class="fa fa-industry" style="color:#0284C7"></i> Detalle de lo Producido en esta OT
                                </div>
                                <?php if ($isFlexo && !empty($prSummary['rolls_list'])): ?>
                                    <div style="overflow:auto;margin-bottom:12px">
                                        <table border="0" width="100%" cellpadding="6" cellspacing="0" style="font-size:12.5px;min-width:640px">
                                            <tr style="background:#F8FAFC;color:#334155">
                                                <td><b>Bobina</b></td>
                                                <td><b>Kg</b></td>
                                                <td><b>Metros</b></td>
                                            </tr>
                                            <?php foreach ($prSummary['rolls_list'] as $rollInfo): ?>
                                                <tr>
                                                    <td style="border-top:1px solid #E2E8F0"><b><?=htmlspecialchars((string)$rollInfo['roll_code'])?></b></td>
                                                    <td style="border-top:1px solid #E2E8F0"><?=number_format((float)($rollInfo['weight_kg'] ?? 0), 2, ',', '.')?></td>
                                                    <td style="border-top:1px solid #E2E8F0"><?=number_format((float)($rollInfo['meters'] ?? 0), 0, ',', '.')?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($otProducedDetail)): ?>
                                    <div style="overflow:auto">
                                        <table border="0" width="100%" cellpadding="6" cellspacing="0" style="font-size:12.5px;min-width:760px">
                                            <tr style="background:#F8FAFC;color:#334155">
                                                <td><b>Fecha</b></td>
                                                <td><b>Tipo</b></td>
                                                <?php if ($isSeri || $isSeriPulpo): ?>
                                                    <td><b>Contador</b></td>
                                                    <td><b>Img/Bast.</b></td>
                                                    <td><b>Unidades</b></td>
                                                <?php elseif ($isFlexo): ?>
                                                    <td><b>Metros</b></td>
                                                    <td><b>Kg</b></td>
                                                <?php else: ?>
                                                    <td><b>Unidades</b></td>
                                                    <td><b>Kg</b></td>
                                                <?php endif; ?>
                                                <?php if ($isSeri || $isSeriPulpo): ?>
                                                    <td><b>Kg</b></td>
                                                <?php endif; ?>
                                            </tr>
                                            <?php foreach ($otProducedDetail as $prodRow): ?>
                                                <?php
                                                $prodTs = (int)($prodRow['evt_crtdat'] ?? 0);
                                                $prodTypeLabel = ((string)($prodRow['evt_type'] ?? '') === 'prodsericolor') ? 'Producción color' : 'Producción';
                                                ?>
                                                <tr>
                                                    <td style="border-top:1px solid #E2E8F0"><?=($prodTs > 0 ? date('d/m/Y H:i', $prodTs) : '-')?></td>
                                                    <td style="border-top:1px solid #E2E8F0"><?=$prodTypeLabel?></td>
                                                    <?php if ($isSeri || $isSeriPulpo): ?>
                                                        <td style="border-top:1px solid #E2E8F0"><?=number_format((float)($prodRow['display_counter'] ?? 0), 0, ',', '.')?></td>
                                                        <td style="border-top:1px solid #E2E8F0"><?=number_format((float)($prodRow['display_devprints'] ?? 0), 0, ',', '.')?></td>
                                                        <td style="border-top:1px solid #E2E8F0"><b style="color:#059669"><?=number_format((float)($prodRow['display_units'] ?? 0), 0, ',', '.')?></b></td>
                                                        <td style="border-top:1px solid #E2E8F0"><?=number_format((float)($prodRow['prod_bobina_kg'] ?? 0), 2, ',', '.')?></td>
                                                    <?php elseif ($isFlexo): ?>
                                                        <td style="border-top:1px solid #E2E8F0"><?=number_format((float)($prodRow['evt_amount_metros_lineales'] ?? 0), 0, ',', '.')?></td>
                                                        <td style="border-top:1px solid #E2E8F0"><?=number_format((float)($prodRow['prod_bobina_kg'] ?? 0), 2, ',', '.')?></td>
                                                    <?php else: ?>
                                                        <td style="border-top:1px solid #E2E8F0"><b style="color:#059669"><?=number_format((float)($prodRow['display_units'] ?? 0), 0, ',', '.')?></b></td>
                                                        <td style="border-top:1px solid #E2E8F0"><?=number_format((float)($prodRow['prod_bobina_kg'] ?? 0), 2, ',', '.')?></td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size:12.5px;color:#64748B">No hay registros de producción acumulada para mostrar en esta OT.</div>
                                <?php endif; ?>
                                <?php if ($isRebobinadora && $reboCloseMoves !== []): ?>
                                    <table width="100%" cellpadding="6" style="font-size:12.5px">
                                        <tr><th>Bobina de entrada</th><th>Producto de salida</th><th>Rollos</th><th>Kg por rollo</th></tr>
                                        <?php foreach ($reboCloseMoves as $move): ?>
                                            <?php foreach ($move['moveins'] ?? [] as $output): ?>
                                                <tr>
                                                    <td><?=htmlspecialchars((string)$move['item_number_prod'])?></td>
                                                    <td><?=htmlspecialchars((string)$output['item_number_prod'] . ' - ' . (string)$output['item_title'])?></td>
                                                    <td><?=number_format((float)($output['item_reboprod_amt'] ?? $output['item_amount']), 0, ',', '.')?></td>
                                                    <td><?=number_format((float)($output['item_reboprod_unitkg'] ?? 0), 2, ',', '.')?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </table>
                                <?php endif; ?>
                            </div>

                            <div style="background:#FFFFFF;border:1px solid #FECACA;border-radius:8px;padding:14px 18px;margin-bottom:16px">
                                <div style="font-size:13px;font-weight:bold;color:#991B1B;margin-bottom:10px;display:flex;align-items:center;gap:8px">
                                    <i class="fa fa-trash" style="color:#DC2626"></i> Detalle de Mermas de la OT
                                </div>
                                <?php if (!empty($otWasteDetail)): ?>
                                    <div style="overflow:auto">
                                        <table border="0" width="100%" cellpadding="6" cellspacing="0" style="font-size:12.5px;min-width:760px">
                                            <tr style="background:#FEF2F2;color:#7F1D1D">
                                                <td><b>Fecha</b></td>
                                                <td><b>Tipo de merma</b></td>
                                                <td><b>Unidades</b></td>
                                                <td><b>Kg</b></td>
                                                <td><b>Metros</b></td>
                                                <td><b>Observación</b></td>
                                            </tr>
                                            <?php foreach ($otWasteDetail as $wasteRow): ?>
                                                <?php $wasteTs = (int)($wasteRow['evt_crtdat'] ?? 0); ?>
                                                <tr>
                                                    <td style="border-top:1px solid #FECACA"><?=($wasteTs > 0 ? date('d/m/Y H:i', $wasteTs) : '-')?></td>
                                                    <td style="border-top:1px solid #FECACA"><b><?=htmlspecialchars((string)($wasteRow['merma_title'] ?? 'Merma'))?></b></td>
                                                    <td style="border-top:1px solid #FECACA"><?=number_format((float)($wasteRow['evt_amount'] ?? 0), 0, ',', '.')?></td>
                                                    <td style="border-top:1px solid #FECACA;color:#B91C1C"><?=number_format((float)($wasteRow['evt_kgstounits'] ?? 0), 2, ',', '.')?></td>
                                                    <td style="border-top:1px solid #FECACA"><?=number_format((float)($wasteRow['evt_mtstounits'] ?? 0), 0, ',', '.')?></td>
                                                    <td style="border-top:1px solid #FECACA;color:#7F1D1D"><?=htmlspecialchars((string)($wasteRow['evt_comments'] ?? ''))?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size:12.5px;color:#64748B">No hay mermas registradas en esta OT.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <form method="post" action="/production/work-orders/close" id="close_ot_supervisor_form">
                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                            <input type="hidden" name="agid" value="<?=$agId?>">
                            <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                            <?php if ($isSeriPulpo && $prodStarted) echo renderView('production/closure/pulpo_bag_balance', ['workerOtId' => (int)$workerOtId, 'agId' => (int)$agId]); ?>

                            <div style="padding:14px 20px 20px;border-top:1px solid #E2E8F0">
                                <?php if ($prodStarted): ?>
                                    <div style="display:grid;grid-template-columns:1fr;gap:14px">
                                        <div>
                                            <label style="display:block;font-size:12.5px;font-weight:bold;color:#334155;margin-bottom:5px">Usuario Supervisor</label>
                                            <input type="text" name="sup_user" id="close_ot_sup_user" class="inptxt" style="width:100%" required autocomplete="off">
                                        </div>
                                        <div>
                                            <label style="display:block;font-size:12.5px;font-weight:bold;color:#334155;margin-bottom:5px">Contraseña</label>
                                            <input type="password" name="sup_pass" id="close_ot_sup_pass" class="inptxt" style="width:100%" required>
                                        </div>
                                        <div>
                                            <label style="display:block;font-size:12.5px;font-weight:bold;color:#334155;margin-bottom:5px">Motivo / Cierre</label>
                                            <select name="close_reason" class="inptxt" style="width:100%">
                                                <option value="completa">Producción Completada al 100%</option>
                                                <option value="parcial">Cierre Parcial por Cambio de Turno</option>
                                                <option value="interrumpida">Interrumpida por Falla Mecánica / Insumo</option>
                                            </select>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <input type="hidden" name="sup_user" value="">
                                    <input type="hidden" name="sup_pass" value="">
                                    <div style="margin-bottom:10px">
                                        <label style="display:block;font-size:12.5px;font-weight:bold;color:#334155;margin-bottom:5px">Motivo de Término</label>
                                        <select name="close_reason" class="inptxt" style="width:100%">
                                            <option value="apertura_error">Apertura por error (Sin producción)</option>
                                            <option value="cambio_planificacion">Cambio de planificación / OT cancelada</option>
                                            <option value="interrumpida">Interrumpida antes de iniciar producción</option>
                                        </select>
                                    </div>
                                <?php endif; ?>
                                <div style="display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:18px">
                                    <button type="button" class="btngrey" style="font-size:14px;padding:10px 18px;border:none;cursor:pointer" onclick="closeCloseOtSupervisorModal()">Cancelar</button>
                                    <?php if ($prodStarted): ?>
                                        <button type="submit" class="btnred" style="font-size:14px;padding:10px 24px;border:none;cursor:pointer">
                                            <i class="fa fa-fw fa-check-circle"></i> Terminar con Supervisor
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btngreen" style="font-size:14px;padding:10px 24px;border:none;cursor:pointer;font-weight:bold">
                                            <i class="fa fa-fw fa-check-circle"></i> Terminar OT sin Supervisor
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <script>
                (function () {
                    var modal = document.getElementById('close_ot_supervisor_modal');
                    var firstInput = document.getElementById('close_ot_sup_user');
                    window.openCloseOtSupervisorModal = function () {
                        if (!modal) return;
                        modal.style.display = 'flex';
                        if (firstInput) {
                            setTimeout(function () { firstInput.focus(); }, 50);
                        }
                    };
                    window.closeCloseOtSupervisorModal = function () {
                        if (!modal) return;
                        modal.style.display = 'none';
                        window.location.href = '/production/work-orders/operate?agid=<?=$agId?>';
                    };
                    window.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape' && modal && modal.style.display === 'flex') {
                            window.closeCloseOtSupervisorModal();
                        }
                    });
                    <?php if ($mode === 'terminarot'): ?>
                    window.openCloseOtSupervisorModal();
                    <?php endif; ?>
                })();
                </script>
            <?php else: ?>
                <form method="post" action="/production/work-orders/close">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                            <?php if ($isSeriPulpo && $prodStarted) echo renderView('production/closure/pulpo_bag_balance', ['workerOtId' => (int)$workerOtId, 'agId' => (int)$agId]); ?>

                    <table border="0" width="100%" cellpadding="8" cellspacing="0">
                    <?php if ($prodStarted): ?>
                        <tr>
                            <td class="tdleft" width="180">Usuario Supervisor</td>
                            <td class="tdnrm"><input type="text" name="sup_user" class="inptxt" style="width:100%" required autocomplete="off"></td>
                        </tr>
                        <tr>
                            <td class="tdleft">Contraseña</td>
                            <td class="tdnrm"><input type="password" name="sup_pass" class="inptxt" style="width:100%" required></td>
                        </tr>
                    <?php else: ?>
                        <input type="hidden" name="sup_user" value="">
                        <input type="hidden" name="sup_pass" value="">
                        <tr>
                            <td class="tdnrm" colspan="2">
                                <div style="background:#ECFDF5;border:1px solid #A7F3D0;border-radius:6px;padding:12px 16px;color:#065F46;font-size:13px">
                                    <i class="fa fa-check-circle" style="color:#059669"></i> Esta OT no registra producción. Puede terminarla directamente sin clave de supervisor.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="tdleft">Motivo / Cierre</td>
                        <td class="tdnrm">
                            <select name="close_reason" class="inptxt" style="width:100%">
                                <?php if ($prodStarted): ?>
                                    <option value="completa">Producción Completada al 100%</option>
                                    <option value="parcial">Cierre Parcial por Cambio de Turno</option>
                                    <option value="interrumpida">Interrumpida por Falla Mecánica / Insumo</option>
                                <?php else: ?>
                                    <option value="apertura_error">Apertura por error (Sin producción)</option>
                                    <option value="cambio_planificacion">Cambio de planificación / OT cancelada</option>
                                    <option value="interrumpida">Interrumpida antes de iniciar producción</option>
                                <?php endif; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm">
                            <?php if ($prodStarted): ?>
                                <button type="submit" class="btnred" style="font-size:14px;padding:10px 24px;width:100%">
                                    <i class="fa fa-fw fa-check-circle"></i> Terminar con Supervisor
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btngreen" style="font-size:14px;padding:10px 24px;width:100%;font-weight:bold">
                                    <i class="fa fa-fw fa-check-circle"></i> Terminar OT sin Supervisor
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    </table>
                </form>
            <?php endif; ?>
        </div>


    <?php

