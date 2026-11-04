<?php
    $totalCc = (float)($agenda['item_amount'] ?? $agenda['ag_amount'] ?? 0);
    if ($totalCc <= 0) {
        $totalCc = (float)($agenda['ag_amount'] ?? 0);
    }

    $reqId = (int)($agenda['ag_reqid'] ?? 0);
    $excludeEvtId = (int)($refId > 0 ? $refId : ($currentRefEvt['id'] ?? 0));
    $acumuladoCc = 0.0;

    if ($reqId > 0) {
        try {
            if ($overrideEmbalaje === 1) {
                $stAcum = Db::erpPdo()->prepare("
                    SELECT COALESCE(SUM(evt.evt_amount), 0)
                    FROM prod_worker_ot_events evt
                    JOIN prod_worker_ot pwo ON evt.evt_prod_worker_otid = pwo.id
                    JOIN prod_agenda pa ON pwo.wok_ag_id = pa.id
                    WHERE pa.ag_reqid = :req_id
                      AND evt.evt_type = 'prod'
                      AND evt.overrideembalaje_act = 1
                      AND evt.evt_status > 0
                      AND evt.id != :exclude_evt_id
                ");
                $stAcum->execute([':req_id' => $reqId, ':exclude_evt_id' => $excludeEvtId]);
                $acumuladoCc = (float)$stAcum->fetchColumn();
            } else {
                $stAcum = Db::erpPdo()->prepare("
                    SELECT COALESCE(SUM(evt.evt_amount), 0)
                    FROM prod_worker_ot_events evt
                    JOIN prod_worker_ot pwo ON evt.evt_prod_worker_otid = pwo.id
                    JOIN prod_agenda pa ON pwo.wok_ag_id = pa.id
                    JOIN equipo teq ON pa.ag_equipo_id = teq.id
                    JOIN equipo_type ttype ON teq.equipo_type_id = ttype.id
                    WHERE pa.ag_reqid = :req_id
                      AND evt.evt_type = 'prod'
                      AND evt.overrideembalaje_act = 0
                      AND (ttype.id = 8 OR ttype.type_ant_title LIKE '%SELLADORA%')
                      AND evt.evt_status > 0
                      AND evt.id != :exclude_evt_id
                ");
                $stAcum->execute([':req_id' => $reqId, ':exclude_evt_id' => $excludeEvtId]);
                $acumuladoCc = (float)$stAcum->fetchColumn();
            }
        } catch (Throwable) {
            $acumuladoCc = 0.0;
        }
    }

    $producidoInitial = (float)$valAmount;
    $totalCcActualizado = $acumuladoCc + $producidoInitial;
    $porProducir = max(0.0, $totalCc - $totalCcActualizado);
    $percentProgress = ($totalCc > 0) ? min(100.0, round(($totalCcActualizado / $totalCc) * 100, 1)) : 0.0;

    $prdNumber = (string)($agenda['prd_number'] ?? '');
    $custName = (string)($agenda['cust_name'] ?? '');
    $reqNumber = (string)($agenda['req_number'] ?? '');
    $itemTitle = (string)($agenda['item_title'] ?? '');
    $fabType = (string)($agenda['fab_type'] ?? '');

    $w = (int)($agenda['fab_med_width'] ?? 0);
    $h = (int)($agenda['fab_med_height'] ?? 0);
    $f = (int)($agenda['fab_med_fuelle'] ?? 0);
    $m = (int)($agenda['fab_manilla_length'] ?? 0);
    $medidasStr = $w > 0 ? ("{$w} x {$h}" . ($f > 0 ? " + {$f}" : "") . " cm") : '-';

    // Foto 1: Diseño
    $designHash = trim((string)($agenda['fab_design_imagehash'] ?? $agenda['item_img_hash'] ?? ''));
    $hasDesignImg = ($designHash !== '' && $designHash !== 'dummy');
    $designUrl = $hasDesignImg ? ('/production/design-image?folder=order&hash=' . rawurlencode($designHash)) : '';
    $designTitle = trim((string)($agenda['fab_design_name'] ?? $itemTitle));

    // Obtener datos de "Registrar fechas de entrega y observaciones" (prod_clasify / prod_amtplan)
    $prdId = (int)($agenda['prdid'] ?? $agenda['ag_prdid'] ?? 0);
    $prodHeaderData = null;
    $plantaName = '';
    $prodPlans = [];
    $orderData = null;

    try {
        if ($prdId > 0) {
            $stPrd = Db::erpPdo()->prepare("
                SELECT ph.id, ph.prd_desc, ph.prd_plantaid, ph.prd_number, pl.planta_name
                FROM prod_header ph
                LEFT JOIN plantas pl ON ph.prd_plantaid = pl.id
                WHERE ph.id = :prd_id
                LIMIT 1
            ");
            $stPrd->execute([':prd_id' => $prdId]);
            $prodHeaderData = $stPrd->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$prodHeaderData && $reqId > 0) {
            $stPrd = Db::erpPdo()->prepare("
                SELECT ph.id, ph.prd_desc, ph.prd_plantaid, ph.prd_number, pl.planta_name
                FROM prod_header ph
                LEFT JOIN plantas pl ON ph.prd_plantaid = pl.id
                WHERE ph.prd_reqid = :req_id AND ph.prd_status >= 1
                ORDER BY ph.id DESC LIMIT 1
            ");
            $stPrd->execute([':req_id' => $reqId]);
            $prodHeaderData = $stPrd->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $actualPrdId = (int)($prodHeaderData['id'] ?? $prdId);
        if ($actualPrdId > 0) {
            $stPlans = Db::erpPdo()->prepare("
                SELECT id, prodplan_amt, prodplan_date
                FROM prod_amtplan
                WHERE prodplan_prdid = :prdid
                ORDER BY prodplan_date ASC, id ASC
            ");
            $stPlans->execute([':prdid' => $actualPrdId]);
            $prodPlans = $stPlans->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (!empty($prodHeaderData['planta_name'])) {
            $plantaName = (string)$prodHeaderData['planta_name'];
        }

        if ($reqId > 0) {
            $stOrd = Db::erpPdo()->prepare("
                SELECT req_despacho_desc, req_desc_intern, req_fec_despacho
                FROM orders WHERE id = :req_id LIMIT 1
            ");
            $stOrd->execute([':req_id' => $reqId]);
            $orderData = $stOrd->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (Throwable) {
        // Fallback defensivo ante eventual error en BD
    }
    ?>
    <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:8px;padding:18px;box-sizing:border-box;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 1px 3px rgba(15,23,42,0.06)">
        <div>
            <!-- Encabezado del panel -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:12px;border-bottom:2px solid #E2E8F0">
                <div style="display:flex;align-items:center;gap:10px">
                    <div style="width:36px;height:36px;border-radius:8px;background:#E6FFFA;color:#00A9A6;display:flex;align-items:center;justify-content:center;font-size:17px">
                        <i class="fa fa-clipboard-check"></i>
                    </div>
                    <div>
                        <div style="font-size:15px;font-weight:bold;color:#115452">Control y Balance de CC</div>
                        <div style="font-size:12px;color:#64748B">
                            CC #<b><?=htmlspecialchars($reqNumber)?></b> &bull; OT #<b><?=htmlspecialchars($prdNumber)?></b>
                        </div>
                    </div>
                </div>
                <div style="font-size:11.5px;background:#F1F5F9;color:#334155;padding:4px 10px;border-radius:6px;font-weight:700;border:1px solid #CBD5E1">
                    <?=$overrideEmbalaje === 1 ? '<i class="fa fa-box"></i> Embalaje' : '<i class="fa fa-cut"></i> Corte y Sellado'?>
                </div>
            </div>

            <!-- FOTO DE DISEÑO (Foto de medidas retirada a solicitud) -->
            <div style="margin-bottom:14px">
                <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;padding:10px 12px;display:flex;flex-direction:column;align-items:center;justify-content:space-between;box-shadow:0 1px 3px rgba(15,23,42,0.04)">
                    <div style="width:100%;display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <span style="font-size:11px;font-weight:700;color:#0284C7;text-transform:uppercase;letter-spacing:0.5px">
                            <i class="fa fa-palette"></i> Foto Diseño
                        </span>
                        <div style="display:flex;align-items:center;gap:6px">
                            <?php if ($medidasStr !== '-'): ?>
                                <span style="font-size:10px;background:#F1F5F9;color:#475569;padding:2px 6px;border-radius:4px;font-weight:600" title="Medidas de la bolsa">
                                    <i class="fa fa-ruler"></i> <?=$medidasStr?>
                                </span>
                            <?php endif; ?>
                            <?php if ($hasDesignImg): ?>
                                <span style="font-size:10px;background:#E0F2FE;color:#0369A1;padding:2px 6px;border-radius:4px;font-weight:600">Arte</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:center;width:100%;padding:4px 0;min-height:120px">
                        <?php if ($hasDesignImg): ?>
                            <a href="<?=$designUrl?>" target="_blank" onclick="openDesignModal('<?=$designUrl?>', '<?=htmlspecialchars($prdNumber)?>', '<?=htmlspecialchars(addslashes($custName))?>', '<?=htmlspecialchars(addslashes($designTitle))?>', '<?=htmlspecialchars($medidasStr)?>'); return false;" title="Clic para ampliar y rotar foto de diseño" style="display:block;cursor:pointer">
                                <img src="<?=$designUrl?>" alt="Diseño de Bolsa" style="max-height:140px;max-width:100%;object-fit:contain;border-radius:6px;border:1px solid #CBD5E1;background:#FFFFFF;box-shadow:0 2px 5px rgba(0,0,0,0.08);transition:transform 0.15s ease" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                            </a>
                        <?php else: ?>
                            <div style="text-align:center;color:#94A3B8;padding:15px 6px">
                                <i class="fa fa-image" style="font-size:30px;margin-bottom:6px;display:block;color:#CBD5E1"></i>
                                <span style="font-size:11px">Sin foto de diseño</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($hasDesignImg): ?>
                        <button type="button" class="btngrey" style="width:100%;font-size:11px;padding:5px 8px;margin-top:6px;display:inline-flex;align-items:center;justify-content:center;gap:5px;cursor:pointer" onclick="openDesignModal('<?=$designUrl?>', '<?=htmlspecialchars($prdNumber)?>', '<?=htmlspecialchars(addslashes($custName))?>', '<?=htmlspecialchars(addslashes($designTitle))?>', '<?=htmlspecialchars($medidasStr)?>')">
                            <i class="fa fa-search-plus"></i> Ampliar / Rotar 360°
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- LOS 5 CAMPOS SEGUIDOS PARA ABAJO -->
            <div style="display:flex;flex-direction:column;gap:9px;margin-bottom:16px">
                <!-- 1. TOTAL CC -->
                <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-left:4px solid #0284C7;border-radius:6px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between">
                    <div>
                        <div style="font-size:13px;font-weight:700;color:#1E293B;display:flex;align-items:center;gap:6px">
                            <i class="fa fa-clipboard-list" style="color:#0284C7"></i> Total CC:
                        </div>
                    </div>
                    <div style="text-align:right">
                        <span style="font-size:17px;font-weight:800;color:#0F172A"><?=number_format($totalCc, 0, ',', '.')?></span>
                        <span style="font-size:11px;color:#64748B;font-weight:600">unid.</span>
                    </div>
                </div>

                <!-- 2. PRODUCIDO -->
                <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-left:4px solid #2563EB;border-radius:6px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between">
                    <div>
                        <div style="font-size:13px;font-weight:700;color:#1E40AF;display:flex;align-items:center;gap:6px">
                            <i class="fa fa-arrow-circle-right" style="color:#2563EB"></i> Producido:
                        </div>
                    </div>
                    <div style="text-align:right">
                        <span id="sync_cierre_producido" style="font-size:17px;font-weight:800;color:#1D4ED8"><?=number_format($producidoInitial, 0, ',', '.')?></span>
                        <span style="font-size:11px;color:#2563EB;font-weight:600">unid.</span>
                    </div>
                </div>

                <!-- 3. ACUMULADO CC -->
                <div style="background:#F1F5F9;border:1px solid #CBD5E1;border-left:4px solid #64748B;border-radius:6px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between">
                    <div>
                        <div style="font-size:13px;font-weight:700;color:#334155;display:flex;align-items:center;gap:6px">
                            <i class="fa fa-layer-group" style="color:#64748B"></i> Acumulado CC:
                        </div>
                    </div>
                    <div style="text-align:right">
                        <span style="font-size:17px;font-weight:800;color:#334155"><?=number_format($acumuladoCc, 0, ',', '.')?></span>
                        <span style="font-size:11px;color:#64748B;font-weight:600">unid.</span>
                    </div>
                </div>

                <!-- 4. TOTAL CC ACTUALIZADO -->
                <div style="background:#ECFDF5;border:1px solid #A7F3D0;border-left:4px solid #059669;border-radius:6px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between">
                    <div>
                        <div style="font-size:13px;font-weight:700;color:#065F46;display:flex;align-items:center;gap:6px">
                            <i class="fa fa-chart-line" style="color:#059669"></i> Total CC Actualizado:
                        </div>
                    </div>
                    <div style="text-align:right">
                        <span id="sync_cierre_total_act" style="font-size:17px;font-weight:800;color:#047857"><?=number_format($totalCcActualizado, 0, ',', '.')?></span>
                        <span style="font-size:11px;color:#059669;font-weight:600">unid.</span>
                    </div>
                </div>

                <!-- 5. POR PRODUCIR -->
                <div style="background:#FFFBEB;border:1px solid #FDE68A;border-left:4px solid #D97706;border-radius:6px;padding:10px 14px;display:flex;align-items:center;justify-content:space-between">
                    <div>
                        <div style="font-size:13px;font-weight:700;color:#92400E;display:flex;align-items:center;gap:6px">
                            <i class="fa fa-hourglass-half" style="color:#D97706"></i> Por Producir:
                        </div>
                    </div>
                    <div style="text-align:right">
                        <span id="sync_cierre_por_producir" style="font-size:17px;font-weight:800;color:#B45309"><?=number_format($porProducir, 0, ',', '.')?></span>
                        <span style="font-size:11px;color:#D97706;font-weight:600">unid.</span>
                    </div>
                </div>
            </div>

            <!-- REGISTRAR FECHAS DE ENTREGA Y OBSERVACIONES (clasificar la orden de trabajo) -->
            <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;overflow:hidden;margin-bottom:16px;box-shadow:0 1px 3px rgba(15,23,42,0.04)">
                <div style="background:#F1F5F9;border-bottom:1px solid #CBD5E1;padding:8px 12px;display:flex;align-items:center;justify-content:space-between">
                    <div style="font-size:11.5px;font-weight:700;color:#0F172A;display:flex;align-items:center;gap:6px">
                        <i class="fa fa-calendar-alt" style="color:#00A9A6"></i> Registrar fechas de entrega y observaciones
                    </div>
                    <?php if (!empty($plantaName)): ?>
                        <span style="font-size:10px;background:#E2E8F0;color:#334155;padding:2px 7px;border-radius:4px;font-weight:700">
                            <i class="fa fa-industry"></i> <?=htmlspecialchars($plantaName)?>
                        </span>
                    <?php endif; ?>
                </div>

                <div style="padding:10px 12px">
                    <table style="width:100%;border-collapse:collapse;font-size:11px;margin-bottom:8px">
                        <thead>
                            <tr style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;color:#475569;font-size:10px;text-transform:uppercase;letter-spacing:0.4px">
                                <th style="padding:5px 8px;text-align:left;font-weight:700">Cantidad</th>
                                <th style="padding:5px 8px;text-align:left;font-weight:700">Fecha entrega</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($prodPlans)): ?>
                                <?php 
                                $totalPlanAmt = 0.0;
                                foreach ($prodPlans as $pPlan): 
                                    $pAmt = (float)($pPlan['prodplan_amt'] ?? 0);
                                    $totalPlanAmt += $pAmt;
                                    $pDateTs = (int)($pPlan['prodplan_date'] ?? 0);
                                    $pDateStr = $pDateTs > 0 ? date('d/m/Y', $pDateTs) : '-';
                                ?>
                                    <tr style="border-bottom:1px solid #F1F5F9">
                                        <td style="padding:5px 8px;font-weight:700;color:#1E293B">
                                            <?=number_format($pAmt, 0, ',', '.')?> <span style="font-size:10px;color:#64748B;font-weight:400">unid.</span>
                                        </td>
                                        <td style="padding:5px 8px;color:#0F172A">
                                            <i class="fa fa-calendar-day" style="color:#00A9A6;margin-right:4px"></i> <b><?=$pDateStr?></b>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($prodPlans) > 1): ?>
                                    <tr style="background:#F8FAFC;border-top:1px solid #CBD5E1;font-weight:700">
                                        <td style="padding:5px 8px;color:#0F172A">
                                            Total: <?=number_format($totalPlanAmt, 0, ',', '.')?> <span style="font-size:10px;color:#64748B;font-weight:400">unid.</span>
                                        </td>
                                        <td style="padding:5px 8px;color:#64748B;font-size:10.5px">
                                            (<?=count($prodPlans)?> entregas)
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php
                                $fecDespachoTs = (int)($orderData['req_fec_despacho'] ?? 0);
                                $fecDespachoStr = $fecDespachoTs > 0 ? date('d/m/Y', $fecDespachoTs) : (!empty($orderData['req_despacho_desc']) ? (string)$orderData['req_despacho_desc'] : '-');
                                ?>
                                <tr style="border-bottom:1px solid #F1F5F9">
                                    <td style="padding:5px 8px;font-weight:700;color:#1E293B">
                                        <?=number_format($totalCc, 0, ',', '.')?> <span style="font-size:10px;color:#64748B;font-weight:400">unid.</span>
                                    </td>
                                    <td style="padding:5px 8px;color:#0F172A">
                                        <i class="fa fa-calendar-day" style="color:#00A9A6;margin-right:4px"></i> <b><?=$fecDespachoStr?></b>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- PLANTA Y OBSERVACIONES -->
                    <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:8px 10px">
                        <div style="font-size:10.5px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.4px;margin-bottom:4px;display:flex;align-items:center;justify-content:space-between">
                            <span><i class="fa fa-comment-dots" style="color:#0284C7"></i> Planta y observaciones</span>
                            <?php if (!empty($plantaName)): ?>
                                <span style="font-weight:600;color:#1E293B;text-transform:none"><?=htmlspecialchars($plantaName)?></span>
                            <?php endif; ?>
                        </div>
                        <?php 
                        $obsPrd = trim((string)($prodHeaderData['prd_desc'] ?? ''));
                        $obsDesp = trim((string)($orderData['req_despacho_desc'] ?? ''));
                        ?>
                        <?php if ($obsPrd !== ''): ?>
                            <div style="font-size:11.5px;color:#1E293B;white-space:pre-wrap;line-height:1.4;background:#FFFFFF;border:1px solid #CBD5E1;border-radius:4px;padding:6px 8px"><?=htmlspecialchars($obsPrd)?></div>
                        <?php endif; ?>
                        <?php if ($obsDesp !== '' && $obsDesp !== $obsPrd): ?>
                            <div style="font-size:11px;color:#475569;margin-top:<?=$obsPrd !== '' ? '5px' : '0'?>;line-height:1.35">
                                <span style="font-weight:600;color:#64748B">Despacho:</span> <?=htmlspecialchars($obsDesp)?>
                            </div>
                        <?php endif; ?>
                        <?php if ($obsPrd === '' && $obsDesp === ''): ?>
                            <div style="font-size:11px;color:#94A3B8;font-style:italic">
                                Sin observaciones registradas para esta OT.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- BARRA DE CUMPLIMIENTO DE LA CC -->
        <div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px;padding:12px;margin-top:auto">
            <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;font-weight:bold;margin-bottom:6px">
                <span style="color:#475569"><i class="fa fa-percentage" style="color:#00A9A6"></i> Cumplimiento de la CC:</span>
                <span id="sync_cierre_perc_txt" style="color:#115452;font-size:13px"><?=number_format($percentProgress, 1, ',', '.')?>%</span>
            </div>
            <div style="height:10px;background:#E2E8F0;border-radius:999px;overflow:hidden">
                <div id="sync_cierre_progress_bar" style="height:100%;width:<?=$percentProgress?>%;background:linear-gradient(90deg,#00A9A6,#059669);border-radius:999px;transition:width 0.25s ease"></div>
            </div>
        </div>
    </div>

    <!-- MODAL DE PREVISUALIZACIÓN Y ROTACIÓN 360° -->
    <?=renderDesignPreviewModal()?>

    <script>
    (function() {
        var TOTAL_CC = <?=(float)$totalCc?>;
        var ACUMULADO_CC = <?=(float)$acumuladoCc?>;
        var IS_SELLADORA = <?=$isSelladora ? 'true' : 'false'?>;
        var IS_EMBALAJE = <?=$overrideEmbalaje === 1 ? 'true' : 'false'?>;

        function formatNumberEs(num) {
            return Math.round(num).toLocaleString('es-CL');
        }

        function updateClosingPanel(currentProd) {
            var produced = Math.max(0, parseFloat(currentProd) || 0);
            var totalAct = ACUMULADO_CC + produced;
            var porProd = Math.max(0, TOTAL_CC - totalAct);
            var perc = TOTAL_CC > 0 ? Math.min(100, Math.round((totalAct / TOTAL_CC) * 1000) / 10) : 0;

            var elProd = document.getElementById('sync_cierre_producido');
            if (elProd) elProd.textContent = formatNumberEs(produced);

            var elTotAct = document.getElementById('sync_cierre_total_act');
            if (elTotAct) elTotAct.textContent = formatNumberEs(totalAct);

            var elPorProd = document.getElementById('sync_cierre_por_producir');
            if (elPorProd) elPorProd.textContent = formatNumberEs(porProd);

            var elBar = document.getElementById('sync_cierre_progress_bar');
            if (elBar) elBar.style.width = perc + '%';

            var elTxt = document.getElementById('sync_cierre_perc_txt');
            if (elTxt) elTxt.textContent = (perc.toFixed(1)).replace('.', ',') + '%';
        }

        window.updateClosingPanelFromCurrentInput = function() {
            var producedVal = 0;
            if (IS_SELLADORA) {
                var inpCyS = document.getElementById('cys_counter_main');
                if (inpCyS) producedVal = parseFloat(inpCyS.value) || 0;
            } else if (IS_EMBALAJE) {
                var inpPack = document.getElementById('pack_total_units');
                if (inpPack) producedVal = parseFloat(inpPack.value) || 0;
            }
            updateClosingPanel(producedVal);
        };

        if (IS_SELLADORA) {
            var cysInp = document.getElementById('cys_counter_main');
            if (cysInp) {
                cysInp.addEventListener('input', function() { updateClosingPanel(this.value); });
                cysInp.addEventListener('change', function() { updateClosingPanel(this.value); });
            }
        }

        if (IS_EMBALAJE) {
            var packTotal = document.getElementById('pack_total_units');
            if (packTotal) {
                packTotal.addEventListener('input', function() { updateClosingPanel(this.value); });
                packTotal.addEventListener('change', function() { updateClosingPanel(this.value); });
            }
            document.querySelectorAll('input[name^="req_embalaje_"]').forEach(function(inp) {
                inp.addEventListener('input', function() {
                    setTimeout(function() {
                        var p = document.getElementById('pack_total_units');
                        if (p) updateClosingPanel(p.value);
                    }, 50);
                });
            });
        }

        setTimeout(window.updateClosingPanelFromCurrentInput, 100);
    })();
    </script>
    <?php

