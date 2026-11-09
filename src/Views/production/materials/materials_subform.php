<?php
    ?>
    <?php if ($mode === 'tintas'): ?>
        <?php if ($isSeri): ?>
            <!-- Para Serigrafía redirigir a Insumos (mode=materiales) -->
            <script>location.href = '/production/work-orders/operate?agid=<?=$agId?>&mode=materiales';</script>
            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px;text-align:center">
                <p>Redirigiendo a <b>Insumos de Serigrafía</b>...</p>
                <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=materiales" class="btnblue">Ir a Insumos</a>
            </div>
        <?php else: ?>
        <?php
        $availableInkRows = is_array($bodega3000InkItems) ? $bodega3000InkItems : [];
        $activeMachineInkLoads = [];
        $closedMachineInkLoads = [];
        $totalInkLoadedQty = 0.0;
        $totalInkAvailableQty = 0.0;
        foreach ($machineInkLoads as $machineInkLoad) {
            $availableQty = (float)($machineInkLoad['available_qty'] ?? 0);
            $totalInkLoadedQty += (float)($machineInkLoad['loaded_qty'] ?? 0);
            $totalInkAvailableQty += $availableQty;
            if ($availableQty > 0.0001) {
                $activeMachineInkLoads[] = $machineInkLoad;
            } else {
                $closedMachineInkLoads[] = $machineInkLoad;
            }
        }
        ?>
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver al listado de eventos
            </div>
        </div>

        <?php if (!empty($_GET['ink_loaded'])): ?>
            <div style="background:#ECFDF5;border:1.5px solid #10B981;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#065F46;font-size:13px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-check-circle" style="font-size:18px;color:#059669"></i> Tinta cargada a máquina exitosamente desde 3000 Insumos de Producción.
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['ink_consumed'])): ?>
            <div style="background:#EFF6FF;border:1.5px solid #3B82F6;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#1E40AF;font-size:13px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-check-circle" style="font-size:18px;color:#2563EB"></i> Consumo de tinta registrado correctamente.
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['ink_returned'])): ?>
            <div style="background:#F8FAFC;border:1.5px solid #CBD5E1;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#475569;font-size:13px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-undo" style="font-size:16px"></i> Sobrante de tinta devuelto a 3000 Insumos de Producción.
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['error'])): ?>
            <div style="background:#FEF2F2;border:1.5px solid #EF4444;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#991B1B;font-size:13px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-exclamation-triangle" style="font-size:18px;color:#DC2626"></i>
                <div><?=htmlspecialchars((string)$_GET['error'])?></div>
            </div>
        <?php endif; ?>

        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:6px;padding:16px 20px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;box-shadow:0 1px 2px rgba(0,0,0,0.04)">
            <div>
                <div style="font-size:16px;font-weight:bold;color:#1E3A8A;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-tint"></i> Control de Tintas en Máquina &mdash; OT #<?=htmlspecialchars((string)$agenda['prd_number'])?>
                </div>
                <div style="font-size:12.5px;color:#64748B;margin-top:3px">
                    Cargue tintas desde <b>3000 Insumos de Producción</b> a la máquina, registre consumo parcial y devuelva el sobrante cuando corresponda.
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <span style="background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;padding:6px 10px;border-radius:999px;font-size:11.5px;font-weight:800">
                    <i class="fa fa-warehouse"></i> 3000 Insumos de Producción
                </span>
                <span style="background:#F8FAFC;color:#334155;border:1px solid #E2E8F0;padding:6px 10px;border-radius:999px;font-size:11.5px;font-weight:800">
                    <i class="fa fa-flask"></i> En máquina: <?=number_format($totalInkAvailableQty, 2, ',', '.')?> kg
                </span>
            </div>
        </div>

        <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,0.05);margin-bottom:18px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:14px">
                <div>
                    <div style="font-size:17px;font-weight:800;color:#1E3A8A;display:flex;align-items:center;gap:9px;margin-bottom:4px">
                        <span style="width:34px;height:34px;border-radius:10px;background:#DBEAFE;color:#1D4ED8;display:inline-flex;align-items:center;justify-content:center;font-size:15px">
                            <i class="fa fa-warehouse"></i>
                        </span>
                        Tintas disponibles en 3000 Insumos de Producción
                    </div>
                </div>
                <div style="font-size:11.5px;color:#64748B;font-weight:800">
                    <?=count($availableInkRows)?> item(s) con stock
                </div>
            </div>

            <div style="margin-bottom:12px">
                <input type="text" id="filter_machine_inks" placeholder="Buscar por código, descripción o color..." class="inptxt" style="width:100%;max-width:380px;font-size:12px;padding:8px 12px;border-radius:8px;background:#FFF" onkeyup="filterMachineInkTable(this.value)">
            </div>

            <div style="overflow:auto;max-height:360px;border:1px solid #CBD5E1;border-radius:10px;background:#FFF">
                <table id="tbl_machine_inks" border="0" width="100%" cellpadding="9" cellspacing="0" style="min-width:980px">
                    <colgroup>
                        <col width="150">
                        <col>
                        <col width="110">
                        <col width="150">
                        <col width="250">
                    </colgroup>
                    <thead style="position:sticky;top:0;background:#F8FAFC;z-index:1;box-shadow:0 1px 0 #E2E8F0">
                        <tr>
                            <td class="tdheader">Código</td>
                            <td class="tdheader">Descripción</td>
                            <td class="tdheader" align="right">Stock</td>
                            <td class="tdheader" align="center">Cargar kg</td>
                            <td class="tdheader" align="center">Acción</td>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($availableInkRows)): ?>
                        <tr>
                            <td colspan="5" class="tdnrm" style="text-align:center;padding:18px;color:#64748B">
                                No se encontraron tintas `TIN` con stock en 3000 Insumos de Producción.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($availableInkRows as $inkRow): ?>
                            <?php
                            $inkItemId = (int)($inkRow['item_id'] ?? 0);
                            $inkCode = trim((string)($inkRow['code'] ?? ''));
                            $inkName = trim((string)($inkRow['name'] ?? ''));
                            $inkStock = (float)($inkRow['available_qty'] ?? 0);
                            $inkLoadFormId = 'ink_load_form_' . $inkItemId;
                            ?>
                            <tr style="border-bottom:1px solid #F1F5F9" onmouseover="this.style.backgroundColor='#F8FBFF'" onmouseout="this.style.backgroundColor=''">
                                <td class="tdnrm"><b><?=htmlspecialchars($inkCode !== '' ? $inkCode : '-')?></b></td>
                                <td class="tdnrm"><?=htmlspecialchars($inkName !== '' ? $inkName : '-')?></td>
                                <td class="tdnrm" align="right"><b style="color:#0F766E"><?=number_format($inkStock, 2, ',', '.')?> kg</b></td>
                                <td class="tdnrm" align="center">
                                    <form id="<?=$inkLoadFormId?>" method="post" action="/production/work-orders/inks/manage" style="display:none;margin:0">
                                        <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                        <input type="hidden" name="agid" value="<?=$agId?>">
                                        <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                        <input type="hidden" name="ink_action" value="load">
                                        <input type="hidden" name="item_id" value="<?=$inkItemId?>">
                                    </form>
                                    <input form="<?=$inkLoadFormId?>" type="number" step="0.01" min="0.01" max="<?=htmlspecialchars(number_format($inkStock, 3, '.', ''))?>" name="qty_kg" class="inptxt" style="width:90px;text-align:center;font-weight:bold" value="">
                                </td>
                                <td class="tdnrm" align="center">
                                        <input form="<?=$inkLoadFormId?>" type="text" name="comments" class="inptxt" style="width:120px" placeholder="Obs.">
                                        <button form="<?=$inkLoadFormId?>" type="submit" class="btnblue" style="padding:5px 12px;font-size:12px;cursor:pointer" onclick="return confirm('¿Cargar esta tinta desde 3000 Insumos de Producción a la máquina?');">
                                            <i class="fa fa-sign-in-alt"></i> Cargar
                                        </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,0.05);margin-bottom:18px">
            <div style="font-size:15px;font-weight:bold;color:#115452;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                <div style="display:flex;align-items:center;gap:8px">
                    <i class="fa fa-flask"></i> Tintas actualmente cargadas en máquina
                </div>
                <div style="font-size:12.5px;color:#64748B;font-weight:normal">
                    Activas: <b><?=count($activeMachineInkLoads)?></b> &bull; Cargadas históricas: <b><?=count($machineInkLoads)?></b>
                </div>
            </div>

            <?php if (empty($machineInkLoads)): ?>
                <div style="padding:15px;color:#64748B;font-size:13px;font-style:italic">
                    Aún no hay tintas cargadas en esta OT. Use la tabla superior para cargar desde 3000 Insumos de Producción.
                </div>
            <?php else: ?>
                <table border="0" width="100%" cellpadding="8" cellspacing="0" style="border:1px solid #E2E8F0;border-radius:6px;overflow:hidden">
                    <thead>
                        <tr style="background:#F1F5F9;color:#334155;font-size:12px;text-transform:uppercase;letter-spacing:0.5px">
                            <th class="tdheader" align="left">Tinta</th>
                            <th class="tdheader" align="center">Cargado</th>
                            <th class="tdheader" align="center">Consumido</th>
                            <th class="tdheader" align="center">Devuelto</th>
                            <th class="tdheader" align="center">Disponible</th>
                            <th class="tdheader" align="center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($machineInkLoads as $inkLoad): ?>
                        <?php
                        $inkLoadId = (int)($inkLoad['id'] ?? 0);
                        $inkAvailable = (float)($inkLoad['available_qty'] ?? 0);
                        $inkIsOpen = $inkAvailable > 0.0001;
                        ?>
                        <tr style="border-bottom:1px solid #E2E8F0;background:<?=$inkIsOpen ? '#FFFFFF' : '#F8FAFC'?>">
                            <td class="tdnrm">
                                <div style="font-weight:700;color:#0F172A"><?=htmlspecialchars(trim((string)($inkLoad['item_code'] ?? '') . ' - ' . (string)($inkLoad['item_name'] ?? ''), ' -'))?></div>
                                <div style="font-size:11px;color:#64748B;margin-top:2px">Carga #<?=$inkLoadId?></div>
                            </td>
                            <td class="tdnrm" align="center"><b><?=number_format((float)($inkLoad['loaded_qty'] ?? 0), 2, ',', '.')?> kg</b></td>
                            <td class="tdnrm" align="center"><?=number_format((float)($inkLoad['consumed_qty'] ?? 0), 2, ',', '.')?> kg</td>
                            <td class="tdnrm" align="center"><?=number_format((float)($inkLoad['returned_qty'] ?? 0), 2, ',', '.')?> kg</td>
                            <td class="tdnrm" align="center">
                                <span style="background:<?=$inkIsOpen ? '#DBEAFE' : '#E5E7EB'?>;color:<?=$inkIsOpen ? '#1D4ED8' : '#475569'?>;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:800">
                                    <?=number_format($inkAvailable, 2, ',', '.')?> kg
                                </span>
                            </td>
                            <td class="tdnrm" align="center">
                                <?php if ($inkIsOpen): ?>
                                    <div style="display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap">
                                        <form method="post" action="/production/work-orders/inks/manage" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin:0">
                                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                            <input type="hidden" name="agid" value="<?=$agId?>">
                                            <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                            <input type="hidden" name="ink_action" value="consume">
                                            <input type="hidden" name="load_id" value="<?=$inkLoadId?>">
                                            <input type="number" step="0.01" min="0.01" max="<?=htmlspecialchars(number_format($inkAvailable, 3, '.', ''))?>" name="qty_kg" class="inptxt" style="width:78px;text-align:center" placeholder="kg">
                                            <button type="submit" class="btngreen" style="padding:4px 10px;font-size:11px;cursor:pointer" onclick="return confirm('¿Registrar consumo de esta tinta en la máquina?');">
                                                <i class="fa fa-minus-circle"></i> Consumir
                                            </button>
                                        </form>
                                        <form method="post" action="/production/work-orders/inks/manage" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin:0">
                                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                            <input type="hidden" name="agid" value="<?=$agId?>">
                                            <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                            <input type="hidden" name="ink_action" value="return">
                                            <input type="hidden" name="load_id" value="<?=$inkLoadId?>">
                                            <input type="number" step="0.01" min="0.01" max="<?=htmlspecialchars(number_format($inkAvailable, 3, '.', ''))?>" name="qty_kg" class="inptxt" style="width:78px;text-align:center" placeholder="kg">
                                            <button type="submit" class="btngrey" style="padding:4px 10px;font-size:11px;cursor:pointer" onclick="return confirm('¿Devolver este sobrante a 3000 Insumos de Producción?');">
                                                <i class="fa fa-undo"></i> Devolver
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span style="background:#DCFCE7;color:#166534;padding:4px 10px;border-radius:4px;font-size:11.5px;font-weight:bold;display:inline-block">
                                        <i class="fa fa-check-circle"></i> CERRADA
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <script>
        function filterMachineInkTable(query) {
            query = String(query || '').toLowerCase().trim();
            var rows = document.querySelectorAll('#tbl_machine_inks tbody tr');
            rows.forEach(function (tr) {
                var text = String(tr.innerText || tr.textContent || '').toLowerCase();
                tr.style.display = (query === '' || text.indexOf(query) !== -1) ? '' : 'none';
            });
        }
        </script>
        <?php endif; /* end if $isSeri */ ?>

    <?php elseif ($mode === 'materiales'): ?>
        <!-- SUBFORMULARIO: Materiales y Bobinas con Trazabilidad -->
        <?php if ($isSelladora): ?>
            <?php renderCysHandleMaterials($agId, $workerOtId, $currentOperatorName); ?>
        <?php endif; ?>
        <?php
        $materialQuota = $ctx['material_quota'] ?? [];
        $assignedRolls = $materialQuota['assigned_rolls'] ?? [];
        $maxAllowedRolls = (int)($materialQuota['max_allowed_rolls'] ?? 2);
        $plannedKg = (float)($materialQuota['planned_kg'] ?? 0);
        $requiresSupervisor = !empty($materialQuota['requires_supervisor']);
        $targetColor = (string)($materialQuota['target_color'] ?? $ctx['agenda']['fabric_color'] ?? $ctx['agenda']['ag_color_tela'] ?? '');
        $targetWidth = (float)($materialQuota['target_width'] ?? $ctx['agenda']['req_operador_tela_width'] ?? $ctx['agenda']['fab_med_width'] ?? 0);
        $targetMaterial = (string)($materialQuota['target_material'] ?? $ctx['agenda']['fab_type'] ?? '');
        $targetGrammage = (float)($materialQuota['target_grammage'] ?? $ctx['agenda']['fab_mat_gramms'] ?? 0);

        $tMatUpper = mb_strtoupper(trim($targetMaterial));
        $normTargetMat = '';
        if ($tMatUpper === 'PLA' || $tMatUpper === 'P.L.A' || str_contains($tMatUpper, 'PLA')) {
            $normTargetMat = 'PLA';
        } elseif ($tMatUpper === 'PP' || $tMatUpper === 'TNT' || $tMatUpper === 'TNT60' || str_contains($tMatUpper, 'POLIPROP')) {
            $normTargetMat = 'PP';
        } elseif ($tMatUpper !== '') {
            $normTargetMat = $tMatUpper;
        }
        $targetColorClean = mb_strtolower(trim($targetColor));
        $targetColorClean = trim((string)preg_replace('/\(pantone[^\)]*\)/i', '', $targetColorClean));
        $compatibleRolls = [];
        $otherRolls = [];
        foreach ($availableRolls as $r) {
            if (!empty($r['is_compatible'])) {
                $compatibleRolls[] = $r;
            } else {
                $otherRolls[] = $r;
            }
        }
        usort($compatibleRolls, static function (array $a, array $b): int {
            $aSource = (string)($a['source'] ?? '');
            $bSource = (string)($b['source'] ?? '');
            $aPriority = $aSource === 'TRZ' ? 0 : 1;
            $bPriority = $bSource === 'TRZ' ? 0 : 1;
            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }

            $aCode = (string)($a['roll_code'] ?? $a['sku_code'] ?? '');
            $bCode = (string)($b['roll_code'] ?? $b['sku_code'] ?? '');
            return strcasecmp($aCode, $bCode);
        });
        $availableBodega3000Rolls = $compatibleRolls;
        $availableBodega3000Count = count($availableBodega3000Rolls);
        $availableBodega3000Kg = 0.0;
        foreach ($availableBodega3000Rolls as $availableRoll) {
            $unitKg = (float)($availableRoll['weight_kg'] ?? $availableRoll['current_weight_kg'] ?? 0);
            $qty = (float)($availableRoll['stock_qty'] ?? $availableRoll['received_qty'] ?? 1);
            if ($qty <= 0) $qty = 1.0;
            $availableBodega3000Kg += (!empty($availableRoll['source']) && $availableRoll['source'] === 'ERP')
                ? ($unitKg * $qty)
                : $unitKg;
        }
        $mountedMergeCandidates = [];
        foreach ($assignedRolls as $candidateRoll) {
            $candidateStatus = (string)($candidateRoll['status'] ?? 'ASSIGNED');
            $candidateClosed = in_array($candidateStatus, ['CONSUMED', 'RETURNED'], true);
            if (empty($candidateRoll['is_mounted']) || $candidateClosed) {
                continue;
            }
            $mountedMergeCandidates[] = [
                'id' => (int)($candidateRoll['id'] ?? 0),
                'roll_code' => (string)($candidateRoll['roll_code'] ?? ('#' . (int)($candidateRoll['id'] ?? 0))),
                'sku_code' => (string)($candidateRoll['sku_code'] ?? ''),
                'weight_kg' => (float)($candidateRoll['entry_weight_kg'] ?? $candidateRoll['weight_kg'] ?? 0),
            ];
        }
        $compactMaterialCode = static function (string $code, string $fallback = ''): string {
            $code = trim($code);
            $fallback = trim($fallback);
            if ($fallback !== '' && preg_match('/^(TEL|TIN)[A-Z0-9\-]+$/i', $fallback)) {
                return strtoupper($fallback);
            }
            if ($code !== '' && preg_match('/\b((?:TEL|TIN)[A-Z0-9\-]+)\b/i', $code, $m)) {
                return strtoupper((string)$m[1]);
            }
            if ($fallback !== '') {
                return strtoupper($fallback);
            }
            return strtoupper($code);
        };
        $activeAssignedRolls = [];
        $closedAssignedRolls = [];
        foreach ($assignedRolls as $assignedRoll) {
            $assignedStatus = (string)($assignedRoll['status'] ?? 'ASSIGNED');
            $assignedClosed = in_array($assignedStatus, ['CONSUMED', 'RETURNED'], true);
            if ($assignedClosed) {
                $closedAssignedRolls[] = $assignedRoll;
            } else {
                $activeAssignedRolls[] = $assignedRoll;
            }
        }
        ?>
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> <?= $isSeri ? 'Volver a la consola de Serigrafía' : 'Volver al listado de eventos' ?>
            </div>
        </div>

        <?php if ($isSeri): ?>
            <?php if (!empty($_GET['ink_consumed'])): ?>
                <div style="background:#ECFDF5;border:1.5px solid #10B981;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#065F46;font-size:13px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-check-circle" style="font-size:18px;color:#059669"></i> Consumo de tinta registrado exitosamente y descontado de Bodega 3000.
                </div>
            <?php endif; ?>
            <?php if (!empty($_GET['ink_deleted'])): ?>
                <div style="background:#EFF6FF;border:1.5px solid #3B82F6;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#1E40AF;font-size:13px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-info-circle" style="font-size:18px;color:#2563EB"></i> Registro de tinta eliminado y stock reintegrado a Bodega 3000.
                </div>
            <?php endif; ?>
            <?php if (!empty($_GET['error'])): ?>
                <div style="background:#FEF2F2;border:1.5px solid #EF4444;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#991B1B;font-size:13px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-exclamation-triangle" style="font-size:18px;color:#DC2626"></i>
                    <div><?=htmlspecialchars((string)$_GET['error'])?></div>
                </div>
            <?php endif; ?>

            <?php renderSerigrafiaInkConsumptionSection($ctx, '/production/work-orders/operate?agid=' . $agId . '&mode=materiales', false); ?>

            <?php if ($isSeriPulpo): ?>
                <div style="margin-top:14px;text-align:right">
                    <a href="/production/work-orders/operate?agid=<?=$agId?>#pulpo-config-10" class="btnblue" style="text-decoration:none;font-size:13px;padding:8px 16px;display:inline-flex;align-items:center;gap:7px">
                        <i class="fa fa-th"></i> Configuración 10 Unidades (Bastidores / Secadores)
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($isSeriPulpo): ?>
            <?php // El Pulpo imprime sobre bolsas, no sobre bobinas: aquí se usan las cajas de bolsas con código. ?>
            <?=renderView('production/materials/pulpo_bags', ['agId' => (int)$agId, 'workerOtId' => (int)$workerOtId])?>
        <?php else: ?>
        <?php
        $printerOutputDefaultSku = trim((string)($agenda['item_number_prod'] ?? $agenda['item_title'] ?? $agenda['fab_type'] ?? 'BOBINA-IMPRESA'));
        $printerOutputSuggestedMeters = (float)($metros_a_imprimir ?? ($stats['total_meters'] ?? 0));
        $printerTelaWidth = (float)($agenda['req_operador_tela_width'] ?? ($agenda['fab_med_width'] ?? 80));
        if ($printerTelaWidth <= 0) {
            $printerTelaWidth = 80.0;
        }
        $printerTelaGramms = (float)($agenda['fab_mat_gramms'] ?? 80);
        if ($printerTelaGramms <= 0) {
            $printerTelaGramms = 80.0;
        }
        $printerMetersPerKg = round(100000.0 / ($printerTelaWidth * $printerTelaGramms), 2);
        ?>

        <!-- NOTIFICACIONES Y ALERTS DE MONTAJE Y SALIDA DE BOBINA -->
        <?php if (!empty($_GET['saved_output'])): ?>
            <div style="background:#ECFDF5;border:1.5px solid #10B981;border-radius:6px;padding:16px 20px;margin-bottom:18px;color:#065F46">
                <div style="font-weight:bold;font-size:15px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-check-circle" style="font-size:22px;color:#059669"></i> ¡Salida y desmontaje de bobina registrados exitosamente!
                </div>
                <?php if (!empty($_GET['output_code'])): ?>
                    <div style="margin-top:12px;background:#FFFFFF;border:1.5px solid #6366F1;padding:14px 18px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
                        <div style="font-size:13px;color:#4338CA;margin-bottom:6px">
                            <i class="fa fa-tag" style="color:#4F46E5"></i> <b>NUEVA BOBINA SALIENTE GENERADA:</b>
                        </div>
                        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                            <span style="font-size:17px;font-family:monospace;font-weight:bold;color:#3730A3;background:#EEF2FF;padding:5px 12px;border-radius:4px;border:1px solid #C7D2FE">
                                <?=htmlspecialchars((string)$_GET['output_code'])?>
                            </span>
                            <span style="font-size:14px;font-weight:bold;color:#1F2937">
                                Peso salida: <b style="color:#4338CA"><?=number_format((float)($_GET['output_kg'] ?? 0), 2, ',', '.')?> kg</b>
                            </span>
                            <?php if (!empty($_GET['output_meters'])): ?>
                                <span style="font-size:13px;color:#4338CA">
                                    <?=number_format((float)$_GET['output_meters'], 0, ',', '.')?> m aprox.
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($_GET['output_id'])): ?>
                                <a href="/rolls/<?=(int)$_GET['output_id']?>/label?server_print=1" target="_blank" class="btnblue" style="font-size:13px;padding:7px 16px;text-decoration:none;font-weight:bold;display:inline-flex;align-items:center;gap:6px;border-radius:4px;background:#4F46E5">
                                    <i class="fa fa-print"></i> 🖨️ Imprimir Etiqueta Salida
                                </a>
                                <a href="/rolls/<?=(int)$_GET['output_id']?>/label" target="_blank" class="btngrey" style="font-size:13px;padding:7px 14px;text-decoration:none;font-weight:bold;display:inline-flex;align-items:center;gap:6px;border-radius:4px">
                                    <i class="fa fa-eye"></i> Ver Etiqueta
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($_GET['leftover_code'])): ?>
                    <div style="margin-top:12px;background:#FFFFFF;border:1.5px solid #10B981;padding:14px 18px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
                        <div style="font-size:13px;color:#065F46;margin-bottom:6px">
                            <i class="fa fa-tags" style="color:#059669"></i> <b>NUEVA BOBINA SOBRANTE GENERADA &mdash; ETIQUETA LISTA PARA IMPRIMIR:</b>
                        </div>
                        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                            <span style="font-size:17px;font-family:monospace;font-weight:bold;color:#047857;background:#DCFCE7;padding:5px 12px;border-radius:4px;border:1px solid #86EFAC">
                                <?=htmlspecialchars((string)$_GET['leftover_code'])?>
                            </span>
                            <span style="font-size:14px;font-weight:bold;color:#1F2937">
                                Peso Sobrante: <b style="color:#059669"><?=number_format((float)($_GET['leftover_kg'] ?? 0), 2, ',', '.')?> kg</b>
                            </span>
                            <?php if (!empty($_GET['leftover_id'])): ?>
                                <a href="/rolls/<?=(int)$_GET['leftover_id']?>/label?server_print=1" target="_blank" class="btngreen" style="font-size:13px;padding:7px 16px;text-decoration:none;font-weight:bold;display:inline-flex;align-items:center;gap:6px;border-radius:4px">
                                    <i class="fa fa-print"></i> 🖨️ Mandar a Imprimir Etiqueta Sobrante (Zebra / 100×70mm)
                                </a>
                                <a href="/rolls/<?=(int)$_GET['leftover_id']?>/label" target="_blank" class="btnblue" style="font-size:13px;padding:7px 14px;text-decoration:none;font-weight:bold;display:inline-flex;align-items:center;gap:6px;border-radius:4px">
                                    <i class="fa fa-eye"></i> 👁️ Ver Etiqueta
                                </a>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:12px;color:#4B5563;margin-top:8px">
                            <i class="fa fa-info-circle"></i> La bobina sobrante ha quedado ingresada con trazabilidad completa vinculada a la bobina original y disponible en <b>Bodega 3000</b>.
                        </div>
                    </div>
                <?php elseif (!empty($_GET['remaining_mounted_code'])): ?>
                    <div style="margin-top:12px;background:#FFFFFF;border:1.5px solid #3B82F6;padding:14px 18px;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
                        <div style="font-size:13px;color:#1D4ED8;margin-bottom:6px">
                            <i class="fa fa-layer-group" style="color:#2563EB"></i> <b>BOBINA QUE QUEDÓ MONTADA PARA EL SIGUIENTE TIRAJE:</b>
                        </div>
                        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                            <span style="font-size:17px;font-family:monospace;font-weight:bold;color:#1D4ED8;background:#DBEAFE;padding:5px 12px;border-radius:4px;border:1px solid #93C5FD">
                                <?=htmlspecialchars((string)$_GET['remaining_mounted_code'])?>
                            </span>
                            <span style="font-size:14px;font-weight:bold;color:#1F2937">
                                Peso que sigue montado: <b style="color:#2563EB"><?=number_format((float)($_GET['remaining_mounted_kg'] ?? 0), 2, ',', '.')?> kg</b>
                            </span>
                        </div>
                        <div style="font-size:12px;color:#475569;margin-top:8px">
                            <i class="fa fa-info-circle"></i> No se generó un código nuevo para este remanente; la misma bobina quedó activa y montada para el siguiente uso.
                        </div>
                    </div>
                <?php else: ?>
                    <div style="margin-top:6px;font-size:12.5px;color:#047857">
                        La bobina se registró como consumida en su totalidad para esta orden de trabajo (sin saldo sobrante).
                    </div>
                <?php endif; ?>
                <?php if (!empty($_GET['output_autoprint']) && !empty($_GET['output_id'])): ?>
                    <script>
                    window.addEventListener('load', function() {
                        window.open('/rolls/<?=(int)$_GET['output_id']?>/label?server_print=1', '_blank');
                    }, { once: true });
                    </script>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['mounted'])): ?>
            <div style="background:#EFF6FF;border:1.5px solid #3B82F6;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#1E40AF;font-size:13px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-check-circle" style="font-size:18px;color:#2563EB"></i>
                <div>
                    Bobina montada en máquina exitosamente.
                    <?php if (!empty($_GET['m_kg'])): ?>
                        Peso de ingreso a máquina registrado: <b><?=number_format((float)$_GET['m_kg'], 2, ',', '.')?> kg</b>.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['unmounted'])): ?>
            <div style="background:#F1F5F9;border:1.5px solid #94A3B8;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#334155;font-size:13px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-pause-circle" style="font-size:18px"></i> Bobina desmontada temporalmente de la máquina.
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['unassigned'])): ?>
            <div style="background:#F8FAFC;border:1.5px solid #CBD5E1;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#475569;font-size:13px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-undo" style="font-size:16px"></i> Bobina devuelta a bodega exitosamente.
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['error'])): ?>
            <div style="background:#FEF2F2;border:1.5px solid #EF4444;border-radius:6px;padding:12px 16px;margin-bottom:18px;color:#991B1B;font-size:13px;display:flex;align-items:center;gap:8px">
                <i class="fa fa-exclamation-triangle" style="font-size:18px;color:#DC2626"></i>
                <div><?=htmlspecialchars((string)$_GET['error'])?></div>
            </div>
        <?php endif; ?>

        <!-- ENCABEZADO Y TABLERO COMPACTO CONTROL DE BOBINAS / MATERIALES -->
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:6px;padding:16px 20px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;box-shadow:0 1px 2px rgba(0,0,0,0.04)">
            <div>
                <div style="font-size:16px;font-weight:bold;color:#1E3A8A;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-link"></i> Control de Bobinas <?=htmlspecialchars($materialsMachineProcessTitle)?> &mdash; OT #<?=htmlspecialchars((string)$agenda['prd_number'])?>
                </div>
                <div style="font-size:12.5px;color:#64748B;margin-top:3px">
                    Izquierda: stock compatible en <b>Bodega 3000</b>. Derecha: bobinas que ya están en la máquina o al pie de máquina para seguir operando.
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="/production/materials/request?agid=<?=$agId?>" target="_blank" class="btnblue" style="text-decoration:none;font-weight:bold;font-size:13px;padding:8px 18px;display:inline-flex;align-items:center;gap:6px">
                    <i class="fa fa-dolly"></i> Solicitar más bobinas
                </a>
            </div>
        </div>

        <?php renderMachineRollScannerBox($agId, 'materiales', $isSelladora, $isFlexo, $isSeri); ?>

        <div style="display:grid;grid-template-columns:minmax(0,1.2fr) minmax(360px,0.9fr);gap:18px;margin-bottom:18px;align-items:start">
            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:6px;padding:18px;min-width:0">
                <div style="font-size:15px;font-weight:bold;color:#115452;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
                    <div style="display:flex;align-items:center;gap:8px">
                        <i class="fa fa-fw fa-warehouse"></i> Bodega 3000
                    </div>
                    <div style="font-size:12px;color:#64748B;font-weight:normal">
                        <b><?=$availableBodega3000Count?></b> rollos &bull; <b><?=number_format($availableBodega3000Kg, 1, ',', '.')?></b> kg aprox.
                    </div>
                </div>

                <?php if (empty($availableBodega3000Rolls)): ?>
                    <div style="background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:6px;padding:24px 20px;text-align:center;color:#64748B">
                        <i class="fa fa-box-open" style="font-size:28px;color:#94A3B8;margin-bottom:10px;display:block"></i>
                        No hay bobinas compatibles disponibles ahora mismo en <b>Bodega 3000</b>.
                    </div>
                <?php else: ?>
                    <table border="0" width="100%" cellpadding="7" cellspacing="0" style="border:1px solid #E2E8F0;border-radius:6px;overflow:hidden">
                        <thead>
                            <tr style="background:#F8FAFC;color:#334155;font-size:11.5px;text-transform:uppercase;letter-spacing:0.3px">
                                <th class="tdheader" align="left" style="padding:9px 8px">Código</th>
                                <th class="tdheader" align="left" style="padding:9px 8px">Descripción</th>
                                <th class="tdheader" align="center" style="padding:9px 8px">Stock</th>
                                <th class="tdheader" align="center" style="padding:9px 8px">Peso</th>
                                <th class="tdheader" align="center" style="padding:9px 8px">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($availableBodega3000Rolls as $b3000Roll):
                            $b3000Id = (string)($b3000Roll['id'] ?? $b3000Roll['raw_id'] ?? '');
                            $b3000Code = (string)($b3000Roll['roll_code'] ?? $b3000Roll['sku_code'] ?? '-');
                            $b3000Desc = (string)($b3000Roll['sku_desc'] ?? '-');
                            $b3000Weight = (float)($b3000Roll['weight_kg'] ?? $b3000Roll['current_weight_kg'] ?? 0);
                            $b3000Qty = (float)($b3000Roll['stock_qty'] ?? $b3000Roll['received_qty'] ?? 1);
                            if ($b3000Qty <= 0) $b3000Qty = 1.0;
                            $b3000Source = (string)($b3000Roll['source'] ?? '');
                            $b3000HasTraceability = ($b3000Source === 'TRZ');
                            $b3000IsCompatible = !empty($b3000Roll['is_compatible']);
                            $b3000DisplayCode = $compactMaterialCode($b3000Code, (string)($b3000Roll['sku_code'] ?? ''));
                        ?>
                            <tr style="border-bottom:1px solid #E2E8F0" onmouseover="this.style.backgroundColor='#F8FBFF'" onmouseout="this.style.backgroundColor=''">
                                <td class="tdnrm" style="padding:9px 8px;vertical-align:top">
                                    <div style="font-weight:800;color:#0F172A;font-size:13px"><?=htmlspecialchars($b3000DisplayCode)?></div>
                                    <div style="font-size:10.5px;color:#64748B;margin-top:2px">
                                        <?=$b3000HasTraceability ? 'Con trazabilidad' : 'Sin trazabilidad'?>
                                    </div>
                                </td>
                                <td class="tdnrm" style="padding:9px 8px;vertical-align:top">
                                    <div style="font-size:12.5px;color:#1E293B;line-height:1.35"><?=htmlspecialchars($b3000Desc)?></div>
                                </td>
                                <td class="tdnrm" align="center" style="padding:9px 8px;vertical-align:middle">
                                    <span style="background:#ECFDF5;color:#166534;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:800">
                                        <?=number_format($b3000Qty, ($b3000Qty == (int)$b3000Qty ? 0 : 2), ',', '.')?>
                                    </span>
                                </td>
                                <td class="tdnrm" align="center" style="padding:9px 8px;vertical-align:middle">
                                    <b><?=number_format($b3000Weight, 1, ',', '.')?> kg</b>
                                </td>
                                <td class="tdnrm" align="center" style="padding:9px 8px;vertical-align:middle">
                                    <?php if ($b3000IsCompatible): ?>
                                        <button
                                            type="button"
                                            class="btngreen"
                                            style="font-size:12px;padding:6px 12px;cursor:pointer;font-weight:bold"
                                            onclick="openTakeBodega3000Modal(
                                                <?=htmlspecialchars(json_encode($b3000Id), ENT_QUOTES)?>,
                                                <?=htmlspecialchars(json_encode($b3000Code, JSON_UNESCAPED_UNICODE), ENT_QUOTES)?>,
                                                <?=htmlspecialchars(json_encode($b3000DisplayCode, JSON_UNESCAPED_UNICODE), ENT_QUOTES)?>,
                                                <?=htmlspecialchars(json_encode($b3000Desc, JSON_UNESCAPED_UNICODE), ENT_QUOTES)?>,
                                                <?=htmlspecialchars(json_encode($b3000Source), ENT_QUOTES)?>,
                                                <?=htmlspecialchars(json_encode(number_format($b3000Weight, 2, '.', '')), ENT_QUOTES)?>,
                                                <?=htmlspecialchars(json_encode($b3000HasTraceability ? 'with' : 'without'), ENT_QUOTES)?>,
                                                <?=htmlspecialchars(json_encode($b3000HasTraceability), ENT_QUOTES)?>
                                            )"
                                        >
                                            <i class="fa fa-sign-in-alt"></i> Montar en máquina
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btngrey" style="font-size:12px;padding:6px 12px;font-weight:bold;opacity:.75;cursor:not-allowed" disabled>
                                            <i class="fa fa-ban"></i> No tomar
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:6px;padding:18px;min-width:0">
                <?php
                $mountedCount = 0;
                foreach ($activeAssignedRolls as $activeAssignedRoll) {
                    if (!empty($activeAssignedRoll['is_mounted'])) {
                        $mountedCount++;
                    }
                }
                ?>
                <div style="font-size:15px;font-weight:bold;color:#115452;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
                    <div style="display:flex;align-items:center;gap:8px">
                        <i class="fa fa-fw fa-industry"></i> Máquina / pie de máquina
                    </div>
                    <div style="font-size:12px;color:#64748B;font-weight:normal">
                        <b><?=$mountedCount?></b> montadas &bull; <b><?=count($activeAssignedRolls)?></b> activas
                    </div>
                </div>

                <?php if (empty($activeAssignedRolls)): ?>
                    <div style="background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:6px;padding:24px 18px;text-align:center;color:#64748B">
                        <i class="fa fa-cogs" style="font-size:28px;color:#94A3B8;margin-bottom:10px;display:block"></i>
                        Aún no hay bobinas activas en esta máquina.
                    </div>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:12px">
                        <?php foreach ($activeAssignedRolls as $ar):
                            $isMounted = !empty($ar['is_mounted']);
                            $entryKg = (float)($ar['entry_weight_kg'] ?? $ar['weight_kg'] ?? 0);
                            $arDisplayCode = $compactMaterialCode((string)($ar['roll_code'] ?? ('#' . $ar['id'])), (string)($ar['sku_code'] ?? ''));
                        ?>
                            <div style="border:1px solid <?=$isMounted ? '#86EFAC' : '#E2E8F0'?>;background:<?=$isMounted ? '#F0FDF4' : '#F8FAFC'?>;border-radius:8px;padding:12px 14px">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
                                    <div>
                                        <div style="font-size:14px;font-weight:800;color:#0F172A"><?=htmlspecialchars($arDisplayCode)?></div>
                                        <div style="font-size:12px;color:#475569;margin-top:2px"><?=htmlspecialchars((string)($ar['sku_desc'] ?? ''))?></div>
                                    </div>
                                    <div style="text-align:right">
                                        <div style="font-size:12px;font-weight:bold;color:<?=$isMounted ? '#166534' : '#92400E'?>">
                                            <?=$isMounted ? 'Montada en máquina' : 'Al pie de máquina'?>
                                        </div>
                                        <div style="font-size:12px;color:#334155;margin-top:2px">
                                            <?=number_format($entryKg > 0 ? $entryKg : (float)($ar['weight_kg'] ?? 0), 2, ',', '.')?> kg
                                        </div>
                                    </div>
                                </div>

                                <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                                    <?php if ($isMounted): ?>
                                        <button type="button" class="btnblue" style="font-size:12px;padding:7px 12px;cursor:pointer;font-weight:bold;display:inline-flex;align-items:center;gap:6px" onclick="openRollOutputModal(<?= (int)$ar['id'] ?>, <?= htmlspecialchars(json_encode($ar['roll_code'] ?? ('#' . $ar['id']))) ?>, <?= (float)$entryKg ?>)">
                                            <i class="fa fa-sign-out-alt"></i> Desmontar / salida
                                        </button>
                                    <?php else: ?>
                                        <form method="post" action="/production/work-orders/materials/mount" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center" class="js-preserve-material-scroll">
                                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                            <input type="hidden" name="agid" value="<?=$agId?>">
                                            <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                            <input type="hidden" name="roll_id" value="<?=$ar['id']?>">
                                            <input type="hidden" name="action_type" value="mount">
                                            <input type="number" step="0.01" min="0.1" name="entry_weight_kg" value="<?=number_format((float)($ar['weight_kg'] ?? 0), 2, '.', '')?>" class="inptxt" style="width:100px;padding:5px 7px;font-weight:bold;font-size:12px" required title="Peso real medido al ingresar la bobina a máquina">
                                            <button type="submit" class="btngreen" style="font-size:12px;padding:6px 10px;cursor:pointer;font-weight:bold">
                                                <i class="fa fa-play"></i> Montar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="/production/work-orders/materials/mount" style="display:inline" class="js-preserve-material-scroll" onsubmit="return confirm('¿Confirmas devolver la bobina <?=htmlspecialchars($arDisplayCode, ENT_QUOTES)?> a Bodega 3000?');">
                                        <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                        <input type="hidden" name="agid" value="<?=$agId?>">
                                        <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                                        <input type="hidden" name="roll_id" value="<?=$ar['id']?>">
                                        <input type="hidden" name="action_type" value="unassign">
                                        <button type="submit" class="btngrey" style="font-size:11px;padding:6px 10px;cursor:pointer">
                                            <i class="fa fa-undo"></i> Devolver a bodega 3000
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($closedAssignedRolls)): ?>
            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:6px;padding:18px;margin-bottom:18px">
                <div style="font-size:15px;font-weight:bold;color:#115452;margin-bottom:12px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-history"></i> Historial de salidas registradas
                </div>
                <div style="display:flex;flex-direction:column;gap:10px">
                    <?php foreach ($closedAssignedRolls as $ar): ?>
                        <div style="border:1px solid #E2E8F0;background:#FAFAFA;border-radius:8px;padding:12px 14px">
                            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                                <div>
                                    <div style="font-size:13.5px;font-weight:800;color:#0F172A"><?=htmlspecialchars($compactMaterialCode((string)($ar['roll_code'] ?? ('#' . $ar['id'])), (string)($ar['sku_code'] ?? '')))?></div>
                                    <div style="font-size:12px;color:#64748B;margin-top:2px"><?=htmlspecialchars((string)($ar['sku_desc'] ?? ''))?></div>
                                </div>
                                <div style="font-size:12px;color:#166534;font-weight:bold">
                                    Salida registrada
                                </div>
                            </div>
                            <div style="margin-top:8px;font-size:12px;color:#334155;display:flex;gap:14px;flex-wrap:wrap">
                                <span>Ingreso: <b><?=number_format((float)($ar['entry_weight_kg'] ?? $ar['weight_kg'] ?? 0), 2, ',', '.')?> kg</b></span>
                                <span>Salida: <b><?=number_format((float)($ar['output_bobina_kg'] ?? 0), 2, ',', '.')?> kg</b></span>
                                <span>Consumo: <b><?=number_format((float)($ar['total_net_consumed_kg'] ?? $ar['net_consumed_kg'] ?? 0), 2, ',', '.')?> kg</b><?php if (!empty($ar['total_consumed_units'])): ?> (<b><?=number_format((float)$ar['total_consumed_units'], ((float)$ar['total_consumed_units'] == (int)$ar['total_consumed_units'] ? 0 : 2), ',', '.')?></b> bob.)<?php endif; ?></span>
                                <span>Mermas: <b style="color:#DC2626"><?=number_format((float)($ar['scrap_kg'] ?? 0), 2, ',', '.')?> kg</b></span>
                                <?php if (!empty($ar['output_at'])): ?>
                                    <span><?=htmlspecialchars((string)$ar['output_at'])?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($ar['remaining_mounted_roll_code']) || (float)($ar['remaining_mounted_kg'] ?? 0) > 0): ?>
                                <div style="margin-top:8px;background:#EFF6FF;border:1px solid #93C5FD;border-radius:6px;padding:8px 10px;font-size:12px;color:#1D4ED8">
                                    <b>Quedó montada:</b> <?=htmlspecialchars((string)($ar['remaining_mounted_roll_code'] ?? ''))?> (<?=number_format((float)($ar['remaining_mounted_kg'] ?? 0), 2, ',', '.')?> kg)
                                    <?php if (!empty($ar['remaining_mounted_formula'])): ?>
                                        <div style="font-size:11px;color:#334155;margin-top:3px">Cálculo: <?=htmlspecialchars((string)$ar['remaining_mounted_formula'])?></div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif (!empty($ar['leftover_roll_code']) || (float)($ar['leftover_kg'] ?? 0) > 0): ?>
                                <div style="margin-top:8px;background:#F0FDF4;border:1px solid #86EFAC;border-radius:6px;padding:8px 10px;font-size:12px;color:#166534">
                                    <b>Sobrante:</b> <?=htmlspecialchars((string)($ar['leftover_roll_code'] ?? ''))?> (<?=number_format((float)($ar['leftover_kg'] ?? 0), 2, ',', '.')?> kg)
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div id="modal_take_bodega3000" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.62);z-index:99998;align-items:center;justify-content:center;padding:18px;box-sizing:border-box">
            <div style="width:100%;max-width:560px;background:#FFFFFF;border-radius:10px;box-shadow:0 20px 45px rgba(15,23,42,0.35);border:1px solid #CBD5E1;overflow:hidden">
                <div style="background:#1E3A8A;color:#FFFFFF;padding:14px 18px;display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <div style="font-size:16px;font-weight:bold"><i class="fa fa-sign-in-alt"></i> Montar bobina en máquina</div>
                        <div style="font-size:12px;color:#BFDBFE;margin-top:3px" id="modal_take_bodega3000_subtitle">-</div>
                    </div>
                    <button type="button" onclick="closeTakeBodega3000Modal()" style="background:transparent;border:none;color:#FFFFFF;font-size:24px;cursor:pointer;line-height:1">&times;</button>
                </div>
                <form method="post" action="/production/work-orders/materials/assign" style="margin:0;padding:18px" class="js-preserve-material-scroll" onsubmit="return validateTakeBodega3000Modal()">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                    <input type="hidden" name="action_mode" value="take_bodega3000">
                    <input type="hidden" name="roll_id" id="modal_take_bodega3000_roll_id" value="">
                    <input type="hidden" name="traceability_source" id="modal_take_bodega3000_source" value="">
                    <input type="hidden" name="expected_traceability_code" id="modal_take_bodega3000_expected_code" value="">

                    <div style="font-size:13px;color:#334155;line-height:1.5;margin-bottom:14px">
                        <b id="modal_take_bodega3000_display_code">-</b><br>
                        <span id="modal_take_bodega3000_desc">-</span>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:12px">
                        <div>
                            <label style="display:block;font-size:12px;font-weight:bold;color:#334155;margin-bottom:5px">Peso ingreso máquina (kg)</label>
                            <input type="number" step="0.01" min="0.1" name="weight_kg" id="modal_take_bodega3000_weight" class="inptxt" style="width:100%;padding:8px 10px;font-size:14px;font-weight:bold" required>
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;font-weight:bold;color:#334155;margin-bottom:5px">Trazabilidad</label>
                            <select name="traceability_mode" id="modal_take_bodega3000_traceability_mode" class="inptxt" style="width:100%;padding:8px 10px;font-size:13px" onchange="toggleTakeBodega3000TraceabilityMode()">
                                <option value="without">Sin trazabilidad</option>
                                <option value="with">Con trazabilidad</option>
                            </select>
                        </div>
                    </div>

                    <div id="modal_take_bodega3000_trace_scan_wrap" style="display:none;margin-bottom:14px">
                        <label style="display:block;font-size:12px;font-weight:bold;color:#334155;margin-bottom:5px">Código de trazabilidad</label>
                        <input type="text" name="confirmed_traceability_code" id="modal_take_bodega3000_trace_code" class="inptxt" style="width:100%;padding:8px 10px;font-size:13px;font-family:monospace;text-transform:uppercase" placeholder="Escanee o escriba el código">
                        <div id="modal_take_bodega3000_trace_hint" style="font-size:11px;color:#64748B;margin-top:4px"></div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap">
                        <button type="button" class="btngrey" style="padding:7px 14px;cursor:pointer" onclick="closeTakeBodega3000Modal()">Cancelar</button>
                        <button type="submit" class="btngreen" style="padding:7px 16px;cursor:pointer;font-weight:bold">
                            <i class="fa fa-check"></i> Montar en máquina
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        var MATERIAL_SCROLL_KEY = 'unibag_material_scroll_<?=$agId?>';

        function rememberMaterialScrollPosition() {
            try {
                sessionStorage.setItem(MATERIAL_SCROLL_KEY, String(window.scrollY || window.pageYOffset || 0));
            } catch (e) {}
        }

        (function restoreMaterialScrollPosition() {
            try {
                var params = new URLSearchParams(window.location.search || '');
                if (params.get('restore_scroll') !== '1') {
                    return;
                }
                var savedScroll = sessionStorage.getItem(MATERIAL_SCROLL_KEY);
                if (savedScroll === null || savedScroll === '') {
                    return;
                }
                window.addEventListener('load', function() {
                    window.scrollTo(0, parseInt(savedScroll, 10) || 0);
                }, { once: true });
            } catch (e) {}
        })();

        function openTakeBodega3000Modal(rollId, code, displayCode, desc, source, weightKg, traceabilityMode, forceTraceability) {
            var modal = document.getElementById('modal_take_bodega3000');
            if (!modal) return;
            document.getElementById('modal_take_bodega3000_roll_id').value = rollId || '';
            document.getElementById('modal_take_bodega3000_source').value = source || '';
            document.getElementById('modal_take_bodega3000_expected_code').value = code || '';
            document.getElementById('modal_take_bodega3000_display_code').innerText = displayCode || code || '-';
            document.getElementById('modal_take_bodega3000_desc').innerText = desc || '-';
            document.getElementById('modal_take_bodega3000_subtitle').innerText = 'Código base: ' + (code || '-');
            document.getElementById('modal_take_bodega3000_weight').value = weightKg || '';

            var select = document.getElementById('modal_take_bodega3000_traceability_mode');
            if (select) {
                select.value = traceabilityMode || 'without';
                select.dataset.forceTraceability = forceTraceability ? '1' : '0';
            }
            var traceInput = document.getElementById('modal_take_bodega3000_trace_code');
            if (traceInput) {
                traceInput.value = '';
            }
            toggleTakeBodega3000TraceabilityMode();
            modal.style.display = 'flex';
            setTimeout(function() {
                var useTrace = select && String(select.value || '').toLowerCase() === 'with';
                var target = document.getElementById(useTrace ? 'modal_take_bodega3000_trace_code' : 'modal_take_bodega3000_weight');
                if (target) target.focus();
            }, 40);
        }

        // Al escanear/escribir el código de trazabilidad se trae el peso registrado en la trazabilidad.
        function fillTakeBodega3000WeightFromTrace() {
            var input = document.getElementById('modal_take_bodega3000_trace_code');
            var weight = document.getElementById('modal_take_bodega3000_weight');
            var hint = document.getElementById('modal_take_bodega3000_trace_hint');
            if (!input || !weight) return;
            var code = String(input.value || '').trim();
            if (code.length < 3) return;
            fetch('/production/api/roll-weight?code=' + encodeURIComponent(code), { credentials: 'same-origin', cache: 'no-store' })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (!d || !d.ok) {
                        if (hint && d && d.error) { hint.innerText = d.error; hint.style.color = '#B91C1C'; }
                        return;
                    }
                    if (d.has_weight) {
                        weight.value = String(Math.round(d.weight_kg * 1000) / 1000);
                        if (hint) { hint.innerText = 'Peso registrado en trazabilidad: ' + weight.value + ' kg (bobina ' + d.roll_code + ').'; hint.style.color = '#166534'; }
                    } else if (hint) {
                        hint.innerText = 'La bobina ' + d.roll_code + ' no tiene peso registrado en trazabilidad; ingréselo manualmente.';
                        hint.style.color = '#B45309';
                    }
                })
                .catch(function() {});
        }
        document.addEventListener('DOMContentLoaded', function() {
            var ti = document.getElementById('modal_take_bodega3000_trace_code');
            if (!ti) return;
            ti.addEventListener('change', fillTakeBodega3000WeightFromTrace);
            ti.addEventListener('blur', fillTakeBodega3000WeightFromTrace);
            ti.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); fillTakeBodega3000WeightFromTrace(); }
            });
        });

        function closeTakeBodega3000Modal() {
            var modal = document.getElementById('modal_take_bodega3000');
            if (modal) modal.style.display = 'none';
        }

        function toggleTakeBodega3000TraceabilityMode() {
            var select = document.getElementById('modal_take_bodega3000_traceability_mode');
            var wrap = document.getElementById('modal_take_bodega3000_trace_scan_wrap');
            var input = document.getElementById('modal_take_bodega3000_trace_code');
            var hint = document.getElementById('modal_take_bodega3000_trace_hint');
            var expectedCode = String((document.getElementById('modal_take_bodega3000_expected_code') || {}).value || '');
            if (select && String(select.dataset.forceTraceability || '') === '1') {
                select.value = 'with';
            }
            var useTraceability = select && String(select.value || '').toLowerCase() === 'with';
            if (wrap) wrap.style.display = useTraceability ? 'block' : 'none';
            if (input) {
                input.required = useTraceability;
                if (!useTraceability) {
                    input.value = '';
                }
            }
            if (hint) {
                hint.innerText = useTraceability
                    ? ((select && String(select.dataset.forceTraceability || '') === '1')
                        ? ('Este rollo ya tiene trazabilidad. Confirme el código exacto: ' + expectedCode)
                        : ('Confirme el código exacto que ingresará a máquina: ' + expectedCode))
                    : 'Se registrará como ingreso sin trazabilidad individual.';
            }
        }

        function validateTakeBodega3000Modal() {
            var weight = parseFloat(String((document.getElementById('modal_take_bodega3000_weight') || {}).value || '').replace(',', '.')) || 0;
            if (weight <= 0) {
                return false;
            }
            var select = document.getElementById('modal_take_bodega3000_traceability_mode');
            var traceInput = document.getElementById('modal_take_bodega3000_trace_code');
            if (select && String(select.value || '').toLowerCase() === 'with') {
                if (!traceInput || String(traceInput.value || '').trim() === '') {
                    if (traceInput) traceInput.focus();
                    return false;
                }
            }
            rememberMaterialScrollPosition();
            return true;
        }

        document.addEventListener('DOMContentLoaded', function() {
            var forms = document.querySelectorAll('.js-preserve-material-scroll');
            forms.forEach(function(form) {
                form.addEventListener('submit', function() {
                    rememberMaterialScrollPosition();
                });
            });
        });
        </script>

        <!-- VENTANA FLOTANTE (MODAL): DESMONTAJE Y SALIDA DE BOBINA -->
        <div id="modal_roll_output" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.65);z-index:99999;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;backdrop-filter:blur(3px)">
            <div style="background:#FFFFFF;border-radius:10px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.3),0 10px 10px -5px rgba(0,0,0,0.2);width:100%;max-width:680px;max-height:92vh;overflow-y:auto;position:relative;border:1px solid #CBD5E1">
                <!-- Modal Header -->
                <div style="background:#1E3A8A;color:#FFFFFF;padding:14px 20px;border-top-left-radius:9px;border-top-right-radius:9px;display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <div style="font-size:16px;font-weight:bold;display:flex;align-items:center;gap:8px">
                            <i class="fa fa-sign-out-alt"></i> Desmontaje y Salida de Bobina
                        </div>
                        <div style="font-size:12.5px;color:#BFDBFE;margin-top:3px" id="modal_roll_subtitle">
                            Bobina: <b id="modal_roll_code_txt" style="color:#FFFFFF">-</b> &bull; Peso Ingreso: <b id="modal_roll_entry_txt" style="color:#FFFFFF">0.00 kg</b>
                        </div>
                    </div>
                    <button type="button" onclick="closeRollOutputModal()" style="background:transparent;border:none;color:#FFFFFF;font-size:24px;cursor:pointer;padding:0 6px;line-height:1" title="Cerrar ventana">
                        &times;
                    </button>
                </div>

                <!-- Modal Body / Form -->
                <form method="post" action="/production/work-orders/materials/consume" style="margin:0;padding:20px" onsubmit="return validateRollOutputModal();">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                    <input type="hidden" name="roll_id" id="modal_roll_id" value="0">
                    <input type="hidden" name="scrap_weight" id="modal_scrap_weight" value="0">

                    <?php if ($isPrinter): ?>
                    <div style="background:#EEF2FF;border:1.5px solid #C7D2FE;border-radius:8px;padding:14px;margin-bottom:16px">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px">
                            <label style="font-size:13.5px;font-weight:bold;color:#3730A3;display:flex;align-items:center;gap:8px;cursor:pointer">
                                <input type="checkbox" name="create_output_roll" id="modal_create_output_roll" value="1" checked onchange="toggleOutputRollSection()">
                                <span><i class="fa fa-tag"></i> Generar nueva bobina saliente con etiqueta</span>
                            </label>
                            <label style="font-size:12px;color:#4338CA;display:flex;align-items:center;gap:6px;cursor:pointer">
                                <input type="checkbox" name="auto_print_output" value="1" checked>
                                Imprimir etiqueta al guardar
                            </label>
                        </div>
                        <div id="modal_output_roll_section">
                            <div style="font-size:12px;color:#4338CA;margin-bottom:10px">
                                La bobina saliente quedará trazada como semielaborada y lista para impresión de etiqueta desde este mismo desmontaje.
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:10px">
                                <div>
                                    <label style="display:block;font-size:12px;font-weight:bold;color:#312E81;margin-bottom:4px">Peso Bobina Saliente (kg) *</label>
                                    <input type="number" step="0.01" min="0" name="output_bobina_kg" id="modal_output_bobina_kg" class="inptxt" style="width:100%;padding:8px 10px;font-size:14px;font-weight:bold;border:2px solid #6366F1;border-radius:6px" value="0.00" placeholder="0.00" oninput="recalcModalOutputMeters()" onchange="recalcModalOutputMeters()">
                                </div>
                                <div>
                                    <label style="display:block;font-size:12px;font-weight:bold;color:#312E81;margin-bottom:4px">Metros aprox.</label>
                                    <input type="number" step="1" min="0" name="output_meters" id="modal_output_meters" class="inptxt" style="width:100%;padding:8px 10px;font-size:14px;font-weight:bold;border:2px solid #A5B4FC;border-radius:6px;background:#F8FAFF" value="<?=$printerOutputSuggestedMeters > 0 ? (int)$printerOutputSuggestedMeters : 0?>" placeholder="0">
                                </div>
                                <div style="grid-column:span 2">
                                    <label style="display:block;font-size:12px;font-weight:bold;color:#312E81;margin-bottom:4px">Descripción / SKU bobina saliente</label>
                                    <input type="hidden" name="output_sku_code" id="modal_output_sku_code" value="<?=htmlspecialchars($printerOutputDefaultSku)?>">
                                    <div style="width:100%;padding:8px 10px;font-size:13px;font-weight:bold;color:#312E81;background:#F8FAFF;border:1px solid #C7D2FE;border-radius:6px;box-sizing:border-box">
                                        <?=htmlspecialchars($printerOutputDefaultSku)?>
                                    </div>
                                    <div style="margin-top:4px;font-size:11px;color:#4338CA">
                                        Código fijo definido por la OT. No se puede editar manualmente.
                                    </div>
                                </div>
                            </div>
                            <div style="margin-top:10px;background:#E0E7FF;border:1px solid #C7D2FE;border-radius:6px;padding:8px 12px;font-size:12px;color:#3730A3">
                                <i class="fa fa-calculator"></i> Relación estimada: <b id="modal_output_ratio_text"><?=number_format($printerMetersPerKg, 2, ',', '.')?> m/kg</b> con ancho <b><?=number_format($printerTelaWidth, 1, ',', '.')?> cm</b> y gramaje <b><?=number_format($printerTelaGramms, 0, ',', '.')?> g/m2</b>.
                                <div style="margin-top:4px;color:#4338CA">
                                    Cálculo automático actual: <b id="modal_output_calc_text"><?=$printerOutputSuggestedMeters > 0 ? number_format((float)$printerOutputSuggestedMeters, 0, ',', '.') : '0'?> m aprox.</b>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="output_bobina_kg" value="0">
                    <?php endif; ?>

                    <div style="background:#EFF6FF;border:1.5px solid #93C5FD;border-radius:8px;padding:14px;margin-bottom:16px">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px">
                            <div>
                                <label style="font-size:13.5px;font-weight:bold;color:#1D4ED8;display:flex;align-items:center;gap:6px">
                                    <i class="fa fa-random"></i> Bobinas que se unieron a esta salida
                                </label>
                                <div style="font-size:12px;color:#1E40AF">
                                    Si esta nueva bobina terminada se formó usando 2, 3 o más bobinas montadas, márquelas aquí para cerrarlas juntas en una sola salida.
                                </div>
                            </div>
                            <div style="background:#DBEAFE;border:1px solid #93C5FD;padding:4px 10px;border-radius:5px;font-size:12px;font-weight:bold;color:#1D4ED8" id="modal_merge_count_badge">
                                Adicionales: 0
                            </div>
                        </div>
                        <?php if (!empty($mountedMergeCandidates)): ?>
                            <div id="modal_merge_rolls_list" style="display:flex;flex-direction:column;gap:8px;max-height:180px;overflow:auto;padding-right:2px">
                                <?php foreach ($mountedMergeCandidates as $mergeCandidate): ?>
                                    <label
                                        class="modal-merge-roll-row"
                                        data-roll-id="<?=$mergeCandidate['id']?>"
                                        data-roll-code="<?=htmlspecialchars($mergeCandidate['roll_code'], ENT_QUOTES)?>"
                                        data-roll-weight="<?=number_format((float)$mergeCandidate['weight_kg'], 2, '.', '')?>"
                                        style="background:#FFFFFF;border:1px solid #BFDBFE;border-radius:6px;padding:8px 10px;display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer"
                                    >
                                        <div style="display:flex;align-items:center;gap:10px;min-width:0">
                                            <input type="checkbox" class="modal-merge-roll-checkbox" name="source_roll_ids[]" value="<?=$mergeCandidate['id']?>" onchange="updateMergeRollSelection()" style="transform:scale(1.1)">
                                            <div style="min-width:0">
                                                <div style="font-size:12.5px;font-weight:bold;color:#0F172A;word-break:break-all">
                                                    <?=htmlspecialchars($mergeCandidate['roll_code'])?>
                                                </div>
                                                <div style="font-size:11px;color:#475569">
                                                    <?=htmlspecialchars($mergeCandidate['sku_code'] !== '' ? $mergeCandidate['sku_code'] : 'Sin SKU')?> · <?=number_format((float)$mergeCandidate['weight_kg'], 2, ',', '.')?> kg
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-merge-roll-hint" style="font-size:11px;font-weight:bold;color:#2563EB;white-space:nowrap">
                                            Sumar a esta salida
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="background:#FFFFFF;border:1px dashed #93C5FD;border-radius:6px;padding:10px 12px;font-size:12px;color:#1E40AF">
                                No hay otras bobinas montadas disponibles para combinar en esta salida.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="background:#F8FAFC;border:1.5px solid #CBD5E1;border-radius:8px;padding:14px;margin-bottom:16px">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px">
                            <label style="font-size:13.5px;font-weight:bold;color:#0F172A;display:flex;align-items:center;gap:8px;cursor:pointer">
                                <input type="checkbox" name="keep_remaining_mounted" id="modal_keep_remaining_mounted" value="1" onchange="toggleRemainingMountedSection()">
                                <span><i class="fa fa-layer-group"></i> Dejar bobina montada para el siguiente tiraje</span>
                            </label>
                        </div>
                        <div id="modal_remaining_mounted_section" style="display:none">
                            <div style="font-size:12px;color:#334155;margin-bottom:10px">
                                El sistema calculará el peso remanente para la bobina que seguirá montada, sin generar un código nuevo. La referencia base será:
                                <b>peso entrada total - bobina saliente - mermas</b>.
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:10px">
                                <div>
                                    <label style="display:block;font-size:12px;font-weight:bold;color:#334155;margin-bottom:4px">Bobina que quedará montada</label>
                                    <select name="remaining_mounted_roll_id" id="modal_remaining_mounted_roll_id" class="inptxt" style="width:100%;padding:8px 10px;font-size:13px" onchange="recalcRemainingMountedWeight()">
                                        <option value="0">Seleccione bobina</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display:block;font-size:12px;font-weight:bold;color:#334155;margin-bottom:4px">Peso que quedará montado (kg)</label>
                                    <input type="number" step="0.01" min="0" name="remaining_mounted_kg" id="modal_remaining_mounted_kg" class="inptxt" style="width:100%;padding:8px 10px;font-size:14px;font-weight:bold;background:#EFF6FF;border:2px solid #93C5FD;border-radius:6px;color:#1D4ED8" value="0.00" readonly>
                                </div>
                            </div>
                            <div id="modal_remaining_formula" style="margin-top:10px;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:6px;padding:8px 12px;font-size:12px;color:#1D4ED8">
                                Cálculo: 0,00 - 0,00 - 0,00 = 0,00 kg
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 1: DESGLOSE DE MERMAS POR TIPO -->
                    <div style="background:#FFF1F2;border:1.5px solid #FECDD3;border-radius:8px;padding:14px;margin-bottom:16px">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;flex-wrap:wrap;gap:8px">
                            <div>
                                <label style="font-size:13.5px;font-weight:bold;color:#9F1239;display:flex;align-items:center;gap:6px">
                                    <i class="fa fa-trash-alt"></i> Mermas de Salida por Tipo
                                </label>
                                <div style="font-size:12px;color:#9F1239">
                                    Desglose los kilos de merma según el tipo que corresponda. En la primera bobina puede cargar aquí <b>Ajuste / Aprobación</b> para absorber la merma de alistamiento.
                                </div>
                            </div>
                            <div style="background:#FFE4E6;border:1px solid #FDA4AF;padding:4px 10px;border-radius:5px;font-size:12px;font-weight:bold;color:#9F1239" id="modal_total_merma_badge">
                                Total Mermas: 0.00 kg
                            </div>
                        </div>

                        <div style="display:flex;flex-direction:column;gap:8px;margin-top:10px">
                        <?php 
                        $typesList = !empty($mermatypes) ? $mermatypes : [
                            ['id' => 6, 'merma_title' => 'Merma Proceso'],
                            ['id' => 7, 'merma_title' => 'Impresión (Selladora)'],
                            ['id' => 8, 'merma_title' => 'Otros'],
                            ['id' => 9, 'merma_title' => 'Ajuste / Aprobación'],
                        ];
                        foreach ($typesList as $mt): 
                            $mtId = (int)$mt['id'];
                            $mtTitle = (string)$mt['merma_title'];
                        ?>
                            <div style="background:#FFFFFF;border:1px solid #FBCFE8;border-radius:6px;padding:8px 10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                                <div style="width:170px;font-size:12.5px;font-weight:bold;color:#881337">
                                    <?=htmlspecialchars($mtTitle)?>:
                                </div>
                                <div style="display:flex;align-items:center;gap:4px">
                                    <input type="number" step="0.01" min="0" name="merma_kgs[<?=$mtId?>]" class="inptxt modal-merma-input" style="width:90px;padding:5px 8px;font-size:13px;font-weight:bold;text-align:right" value="0.00" oninput="calcModalMermaTotal()" placeholder="0.00">
                                    <span style="font-size:11.5px;color:#64748B">kg</span>
                                </div>
                                <div style="flex:1;min-width:180px">
                                    <input type="text" name="merma_comm[<?=$mtId?>]" class="inptxt" style="width:100%;padding:5px 8px;font-size:12px" placeholder="Motivo / observación (opcional)...">
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: SOBRANTE DE BOBINA (CON GENERACIÓN DE ETIQUETA) -->
                    <div style="background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:8px;padding:14px;margin-bottom:16px">
                        <label style="display:block;font-size:13.5px;font-weight:bold;color:#166534;margin-bottom:4px">
                            <i class="fa fa-box-open" style="color:#059669"></i> Sobrante de Bobina (kg)
                        </label>
                        <div style="font-size:12px;color:#15803D;margin-bottom:8px">
                            Kilos de tela que quedaron sin utilizar en el cono / eje (dejar en 0 si se consumió por completo).
                        </div>
                        <div style="display:flex;align-items:center;gap:10px">
                            <input type="number" step="0.01" min="0" name="leftover_weight" id="modal_leftover_kg" class="inptxt" style="width:160px;padding:8px 12px;font-size:15px;font-weight:bold;color:#065F46;border:2px solid #10B981;border-radius:6px" value="0.00" placeholder="0.00">
                            <span style="font-size:13px;font-weight:bold;color:#15803D">kg sobrante</span>
                        </div>
                        <div style="margin-top:10px;background:#DCFCE7;border:1px solid #86EFAC;border-radius:6px;padding:8px 12px;font-size:12px;color:#14532D;line-height:1.4">
                            <i class="fa fa-tag" style="color:#059669"></i> <b>Generación automática de Etiqueta:</b> Si ingresa un sobrante mayor a 0 kg, el sistema creará inmediatamente un <b>nuevo código de bobina</b> con trazabilidad en <b>Bodega 3000</b> y habilitará su <b>etiqueta para impresión Zebra (100×70mm)</b>.
                        </div>
                        <div id="modal_leftover_mount_note" style="display:none;margin-top:10px;background:#DBEAFE;border:1px solid #93C5FD;border-radius:6px;padding:8px 12px;font-size:12px;color:#1D4ED8;line-height:1.4">
                            <i class="fa fa-info-circle"></i> El remanente quedará montado en máquina. En este modo no se generará un código nuevo de sobrante en Bodega 3000.
                        </div>
                    </div>

                    <!-- SECCIÓN 3: OBSERVACIONES GENERALES -->
                    <div style="margin-bottom:18px">
                        <label style="display:block;font-size:12.5px;font-weight:bold;color:#334155;margin-bottom:4px">
                            Observaciones de Desmontaje / Turno (Opcional):
                        </label>
                        <input type="text" name="comments" class="inptxt" style="width:100%;padding:7px 10px;font-size:12.5px" placeholder="Detalle adicional del desmontaje...">
                    </div>

                    <!-- MODAL FOOTER / BOTONES -->
                    <div style="display:flex;justify-content:flex-end;align-items:center;gap:10px;border-top:1px solid #E2E8F0;padding-top:14px">
                        <button type="button" onclick="closeRollOutputModal()" class="btngrey" style="font-size:13px;padding:8px 18px;cursor:pointer">
                            Cancelar
                        </button>
                        <button type="submit" class="btngreen" style="font-size:13px;padding:8px 22px;cursor:pointer;font-weight:bold;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 4px rgba(0,0,0,0.1)">
                            <i class="fa fa-check-circle"></i> 💾 Confirmar Desmontaje de Bobina
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function getModalCurrentEntryWeight() {
            var txt = document.getElementById('modal_roll_entry_txt');
            if (!txt) return 0;
            var raw = String(txt.innerText || '').replace(' kg', '').replace(',', '.');
            return parseFloat(raw) || 0;
        }

        function getSelectedMergeRolls() {
            var rolls = [];
            var rows = document.querySelectorAll('.modal-merge-roll-row');
            rows.forEach(function(row) {
                var checkbox = row.querySelector('.modal-merge-roll-checkbox');
                if (!checkbox || !checkbox.checked || checkbox.disabled) return;
                rolls.push({
                    id: parseInt(row.getAttribute('data-roll-id') || '0', 10) || 0,
                    code: String(row.getAttribute('data-roll-code') || ''),
                    weight: parseFloat(String(row.getAttribute('data-roll-weight') || '').replace(',', '.')) || 0
                });
            });
            return rolls;
        }

        function rebuildRemainingMountedOptions() {
            var select = document.getElementById('modal_remaining_mounted_roll_id');
            var currentRollId = parseInt(document.getElementById('modal_roll_id').value || '0', 10) || 0;
            var currentRollCode = String(document.getElementById('modal_roll_code_txt').innerText || '').trim();
            var currentEntryWeight = getModalCurrentEntryWeight();
            if (!select) return;

            var currentValue = parseInt(select.value || '0', 10) || 0;
            var options = [
                {
                    id: currentRollId,
                    code: currentRollCode,
                    weight: currentEntryWeight
                }
            ].concat(getSelectedMergeRolls());

            select.innerHTML = '';
            if (options.length === 0) {
                select.innerHTML = '<option value="0">Seleccione bobina</option>';
                return;
            }
            options.forEach(function(item) {
                if (!item || !item.id) return;
                var opt = document.createElement('option');
                opt.value = String(item.id);
                opt.text = item.code + ' (' + item.weight.toFixed(2).replace('.', ',') + ' kg)';
                select.appendChild(opt);
            });
            if (currentValue > 0) {
                select.value = String(currentValue);
            }
            if (!select.value && options.length > 0) {
                select.value = String(options[0].id);
            }
        }

        function recalcRemainingMountedWeight() {
            var section = document.getElementById('modal_remaining_mounted_section');
            if (!section || section.style.display === 'none') return;

            var currentEntryWeight = getModalCurrentEntryWeight();
            var selectedMergeRolls = getSelectedMergeRolls();
            var totalEntry = currentEntryWeight;
            selectedMergeRolls.forEach(function(item) { totalEntry += item.weight || 0; });

            var outputKg = parseFloat(String((document.getElementById('modal_output_bobina_kg') || {}).value || '').replace(',', '.')) || 0;
            var scrapKg = parseFloat(String((document.getElementById('modal_scrap_weight') || {}).value || '').replace(',', '.')) || 0;
            var remainingKg = Math.max(0, totalEntry - outputKg - scrapKg);

            var remainInput = document.getElementById('modal_remaining_mounted_kg');
            if (remainInput) remainInput.value = remainingKg.toFixed(2);

            var formula = document.getElementById('modal_remaining_formula');
            if (formula) {
                formula.innerHTML = 'Cálculo: <b>' + totalEntry.toFixed(2).replace('.', ',')
                    + '</b> kg entrada total - <b>' + outputKg.toFixed(2).replace('.', ',')
                    + '</b> kg salida - <b>' + scrapKg.toFixed(2).replace('.', ',')
                    + '</b> kg mermas = <b>' + remainingKg.toFixed(2).replace('.', ',')
                    + '</b> kg que seguirá montado.';
            }
        }

        function toggleRemainingMountedSection() {
            var chk = document.getElementById('modal_keep_remaining_mounted');
            var box = document.getElementById('modal_remaining_mounted_section');
            var leftoverInput = document.getElementById('modal_leftover_kg');
            var leftoverNote = document.getElementById('modal_leftover_mount_note');
            if (!chk || !box) return;

            box.style.display = chk.checked ? 'block' : 'none';
            if (leftoverInput) {
                if (chk.checked) {
                    leftoverInput.value = '0.00';
                    leftoverInput.readOnly = true;
                    leftoverInput.style.background = '#F1F5F9';
                    leftoverInput.style.opacity = '0.85';
                } else {
                    leftoverInput.readOnly = false;
                    leftoverInput.style.background = '#FFFFFF';
                    leftoverInput.style.opacity = '1';
                }
            }
            if (leftoverNote) {
                leftoverNote.style.display = chk.checked ? 'block' : 'none';
            }
            rebuildRemainingMountedOptions();
            recalcRemainingMountedWeight();
        }

        function resetMergeRollSelection(currentRollId) {
            var rows = document.querySelectorAll('.modal-merge-roll-row');
            rows.forEach(function(row) {
                var rowRollId = parseInt(row.getAttribute('data-roll-id') || '0', 10);
                var checkbox = row.querySelector('.modal-merge-roll-checkbox');
                var hint = row.querySelector('.modal-merge-roll-hint');
                if (!checkbox) return;
                checkbox.checked = false;
                if (rowRollId === currentRollId) {
                    checkbox.disabled = true;
                    row.style.opacity = '0.65';
                    row.style.background = '#E2E8F0';
                    if (hint) hint.innerText = 'Bobina principal';
                } else {
                    checkbox.disabled = false;
                    row.style.opacity = '1';
                    row.style.background = '#FFFFFF';
                    if (hint) hint.innerText = 'Sumar a esta salida';
                }
            });
            updateMergeRollSelection();
        }

        function updateMergeRollSelection() {
            var count = 0;
            var checkboxes = document.querySelectorAll('.modal-merge-roll-checkbox');
            checkboxes.forEach(function(checkbox) {
                if (checkbox.checked && !checkbox.disabled) {
                    count++;
                }
            });
            var badge = document.getElementById('modal_merge_count_badge');
            if (badge) {
                badge.innerText = 'Adicionales: ' + count;
            }
            rebuildRemainingMountedOptions();
            recalcRemainingMountedWeight();
        }

        function openRollOutputModal(rollId, rollCode, entryKg) {
            document.getElementById('modal_roll_id').value = rollId;
            document.getElementById('modal_roll_code_txt').innerText = rollCode;
            document.getElementById('modal_roll_entry_txt').innerText = Number(entryKg).toFixed(2) + ' kg';
            document.getElementById('modal_leftover_kg').value = '0.00';
            var outputKg = document.getElementById('modal_output_bobina_kg');
            if (outputKg) outputKg.value = '0.00';
            var outputMeters = document.getElementById('modal_output_meters');
            if (outputMeters) outputMeters.value = '<?=$printerOutputSuggestedMeters > 0 ? (int)$printerOutputSuggestedMeters : 0?>';
            var outputSku = document.getElementById('modal_output_sku_code');
            if (outputSku) outputSku.value = <?=json_encode($printerOutputDefaultSku, JSON_UNESCAPED_UNICODE)?>;
            var createChk = document.getElementById('modal_create_output_roll');
            if (createChk) createChk.checked = true;
            var keepMountedChk = document.getElementById('modal_keep_remaining_mounted');
            if (keepMountedChk) keepMountedChk.checked = false;
            toggleOutputRollSection();
            recalcModalOutputMeters();
            resetMergeRollSelection(parseInt(rollId, 10) || 0);
            toggleRemainingMountedSection();
            
            // Reset mermas
            var mInputs = document.querySelectorAll('.modal-merma-input');
            mInputs.forEach(function(inp) { inp.value = '0.00'; });
            calcModalMermaTotal();
            
            var modal = document.getElementById('modal_roll_output');
            modal.style.display = 'flex';
            setTimeout(function() {
                var lInp = document.getElementById('modal_leftover_kg');
                if (lInp) lInp.focus();
            }, 100);
        }

        function closeRollOutputModal() {
            var modal = document.getElementById('modal_roll_output');
            if (modal) modal.style.display = 'none';
        }

        function calcModalMermaTotal() {
            var total = 0;
            var mInputs = document.querySelectorAll('.modal-merma-input');
            mInputs.forEach(function(inp) {
                var val = parseFloat(String(inp.value).replace(',', '.')) || 0;
                total += val;
            });
            var hidScrap = document.getElementById('modal_scrap_weight');
            if (hidScrap) hidScrap.value = total.toFixed(2);
            var badge = document.getElementById('modal_total_merma_badge');
            if (badge) badge.innerText = 'Total Mermas: ' + total.toFixed(2) + ' kg';
            recalcRemainingMountedWeight();
        }

        function toggleOutputRollSection() {
            var chk = document.getElementById('modal_create_output_roll');
            var box = document.getElementById('modal_output_roll_section');
            if (!chk || !box) return;
            box.style.display = chk.checked ? 'block' : 'none';
        }

        var MODAL_OUTPUT_METERS_PER_KG = <?=json_encode((float)$printerMetersPerKg)?>;
        function recalcModalOutputMeters() {
            var kgInput = document.getElementById('modal_output_bobina_kg');
            var metersInput = document.getElementById('modal_output_meters');
            var calcText = document.getElementById('modal_output_calc_text');
            if (!kgInput || !metersInput) return;

            var kg = parseFloat(String(kgInput.value || '').replace(',', '.')) || 0;
            var meters = 0;
            if (kg > 0 && MODAL_OUTPUT_METERS_PER_KG > 0) {
                meters = Math.max(1, Math.round(kg * MODAL_OUTPUT_METERS_PER_KG));
            }
            metersInput.value = meters > 0 ? String(meters) : '0';
            if (calcText) {
                calcText.innerText = (meters > 0 ? meters : 0) + ' m aprox.';
            }
            recalcRemainingMountedWeight();
        }

        function validateRollOutputModal() {
            var selectedMergeCount = 0;
            var mergeCheckboxes = document.querySelectorAll('.modal-merge-roll-checkbox');
            mergeCheckboxes.forEach(function(checkbox) {
                if (checkbox.checked && !checkbox.disabled) {
                    selectedMergeCount++;
                }
            });
            var createChk = document.getElementById('modal_create_output_roll');
            if (selectedMergeCount > 0 && (!createChk || !createChk.checked)) {
                alert('Para convertir varias bobinas en una sola, debe generar una bobina saliente con etiqueta.');
                return false;
            }
            if (createChk && createChk.checked) {
                var outputKg = parseFloat(String(document.getElementById('modal_output_bobina_kg').value || '').replace(',', '.')) || 0;
                if (outputKg <= 0) {
                    alert('Debes ingresar el peso de la nueva bobina saliente para generar su etiqueta.');
                    return false;
                }
            }
            var keepMountedChk = document.getElementById('modal_keep_remaining_mounted');
            if (keepMountedChk && keepMountedChk.checked) {
                var keepRollId = parseInt(String((document.getElementById('modal_remaining_mounted_roll_id') || {}).value || '0'), 10) || 0;
                var keepWeight = parseFloat(String((document.getElementById('modal_remaining_mounted_kg') || {}).value || '').replace(',', '.')) || 0;
                if (keepRollId <= 0) {
                    alert('Debe seleccionar qué bobina quedará montada para el siguiente tiraje.');
                    return false;
                }
                if (keepWeight <= 0) {
                    alert('El cálculo del peso que quedará montado dio 0 kg. Revise el peso de salida o las mermas antes de guardar.');
                    return false;
                }
            }
            var confirmMessage = selectedMergeCount > 0
                ? '¿Confirmar registro de desmontaje y unir esta salida con ' + selectedMergeCount + ' bobina(s) adicional(es)?'
                : '¿Confirmar registro de desmontaje y cierre de esta bobina?';
            return confirm(confirmMessage);
        }

        // Cerrar con Escape
        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeRollOutputModal();
            }
        });
        </script>

        <?php endif; /* fin: bobinas (no Pulpo) */ ?>

    <?php elseif ($mode === 'consumo'): ?>
        <!-- Redirigir a modo materiales para gestionar montaje y salida de bobinas con trazabilidad -->
        <script>location.href = '/production/work-orders/operate?agid=<?=$agId?>&mode=materiales';</script>
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px;text-align:center">
            <p>Redirigiendo a <b>Montaje y Salida de Bobinas</b>...</p>
            <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=materiales" class="btnblue">Ir a Bobinas y Materiales</a>
        </div>

    <?php endif; ?>
    <?php

