<?php
    ?>
    <!-- ============================================================== -->
    <!-- CABECERA PRINCIPAL Y ACCIONES RÁPIDAS                          -->
    <!-- ============================================================== -->
    <div style="font-size:20px;margin-bottom:10px"><b>Ordenes de trabajo</b></div>

    <?php if ($activeStandby !== null): ?>
        <div style="background:#FFF7ED;border:1.5px solid #F97316;border-radius:6px;padding:12px 18px;margin-bottom:12px;color:#9A3412;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;box-shadow:0 1px 3px rgba(0,0,0,0.06)">
            <div style="display:flex;align-items:center;gap:12px">
                <i class="fa fa-pause-circle" style="font-size:24px;color:#EA580C"></i>
                <div>
                    <div style="font-weight:bold;font-size:14px;text-transform:uppercase;color:#C2410C">
                        MÁQUINA EN ESPERA / SIN PRODUCCIÓN: <?=htmlspecialchars((string)$activeStandby['reason'])?>
                    </div>
                    <div style="font-size:12px;color:#9A3412;margin-top:2px">
                        Iniciado a las <b><?=htmlspecialchars((string)$activeStandby['start_time'])?> hrs</b> por <b><?=htmlspecialchars((string)$activeStandby['operator_name'])?></b>
                        <?php if (!empty($activeStandby['comments'])): ?>
                            &bull; <i><?=htmlspecialchars((string)$activeStandby['comments'])?></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <button type="button" class="btnorange" style="background:#EA580C;padding:7px 16px;font-size:12.5px;font-weight:bold;cursor:pointer;border:none;border-radius:4px" onclick="openStandbyEndModal()">
                <i class="fa fa-play"></i> Finalizar Espera / Reanudar
            </button>
        </div>
    <?php endif; ?>

    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px">
    <tr>
        <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                <div>
                    <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
                    <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                    <b>Iniciar nueva OT</b> en máquina <b><?=htmlspecialchars($machineName)?></b>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <a href="/production/materials/request" class="btngreen" style="padding:5px 12px;font-size:12px;text-decoration:none;border-radius:4px;display:inline-flex;align-items:center;gap:6px" title="Solicitar o cargar bobinas a la máquina">
                        <i class="fa fa-dolly"></i> Solicitar Bobinas
                    </a>
                    <?php if (!empty($activeLunch)): ?>
                        <button type="button" class="btnorange" style="background:#D97706;padding:5px 12px;font-size:12px;cursor:pointer;border:none;border-radius:4px;font-weight:bold" onclick="openLunchModal()" title="Colación activa">
                            <i class="fa fa-coffee"></i> EN COLACIÓN (desde <?=date('H:i', strtotime($activeLunch['start_time']))?>) &mdash; Terminar
                        </button>
                    <?php else: ?>
                        <button type="button" class="btnblue" style="background:#0284C7;padding:5px 12px;font-size:12px;cursor:pointer;border:none;border-radius:4px" onclick="openLunchModal()" title="Registrar horario de colación en tiempo real">
                            <i class="fa fa-coffee"></i> Colación
                        </button>
                    <?php endif; ?>

                    <!-- BOTÓN: ESPERA / SIN PRODUCCIÓN -->
                    <?php if ($activeStandby !== null): ?>
                        <button type="button" class="btnorange" style="background:#EA580C;padding:5px 12px;font-size:12px;cursor:pointer;border:none;border-radius:4px;font-weight:bold;display:inline-flex;align-items:center;gap:6px" onclick="openStandbyEndModal()" title="Finalizar estado sin producción">
                            <i class="fa fa-play-circle"></i> Finalizar Espera
                        </button>
                    <?php else: ?>
                        <button type="button" class="btnorange" style="background:#D97706;padding:5px 12px;font-size:12px;cursor:pointer;border:none;border-radius:4px;font-weight:bold;display:inline-flex;align-items:center;gap:6px" onclick="openStandbyStartModal()" title="Informar máquina sin producción / espera de OT / mantención">
                            <i class="fa fa-pause-circle"></i> Espera / Sin Prod.
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </td>
    </tr>
    </table>

    <!-- PESTAÑA: PROGRAMADAS EN AGENDA -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px">
        <div style="display:flex;align-items:center;gap:6px">
            <a href="/production/work-orders/new?tab=scheduled<?= !empty($dateFilter) ? '&plan_date=' . urlencode($dateFilter) : '' ?>"
               style="padding:8px 16px;border-radius:6px;font-size:13px;font-weight:bold;text-decoration:none;display:inline-flex;align-items:center;gap:8px;border:1.5px solid <?= $activeTab === 'scheduled' ? '#0F766E' : '#CBD5E1' ?>;background:<?= $activeTab === 'scheduled' ? '#0F766E' : '#FFFFFF' ?>;color:<?= $activeTab === 'scheduled' ? '#FFFFFF' : '#475569' ?>;box-shadow:0 1px 3px rgba(0,0,0,0.06)">
                <i class="fa fa-calendar-check"></i> OTs Programadas en Agenda
                <span style="background:<?= $activeTab === 'scheduled' ? '#115E59' : '#E2E8F0' ?>;color:<?= $activeTab === 'scheduled' ? '#FFFFFF' : '#334155' ?>;padding:2px 8px;border-radius:10px;font-size:11px">
                    <?= count($scheduledOrders) ?>
                </span>
            </a>
        </div>
    </div>

    <?php if ($activeTab === 'unscheduled'): ?>
        <!-- ============================================================== -->
        <!-- VISTA DE PRODUCTOS FUERA DE PLANIFICACIÓN (BACKLOG)           -->
        <!-- ============================================================== -->
        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px;overflow-x:auto">
            <div style="background:#FFFBEB;border:1.5px solid #FCD34D;border-radius:6px;padding:12px 16px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
                <div>
                    <div style="font-size:14.5px;font-weight:bold;color:#92400E;display:flex;align-items:center;gap:8px">
                        <i class="fa fa-exclamation-triangle" style="font-size:16px"></i>
                        <span>Productos Aprobados Fuera de Planificación de Agenda</span>
                        <span style="background:#FEF3C7;color:#92400E;border:1px solid #F59E0B;font-size:12px;padding:2px 8px;border-radius:12px">
                            <?= count($unscheduledOrders) ?> órdenes disponibles
                        </span>
                    </div>
                    <div style="font-size:12.5px;color:#78350F;margin-top:3px">
                        Estas órdenes cuentan con ficha técnica aprobada en ERP, pero aún no están calendarizadas en la agenda de esta máquina.
                        Puedes iniciar cualquiera de ellas de urgencia haciendo clic en <b>"Iniciar OT"</b>.
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <form method="get" action="/production/work-orders/new" style="margin:0;display:inline-flex;align-items:center;gap:6px">
                        <input type="hidden" name="tab" value="unscheduled">
                        <input type="text" name="search" value="<?=htmlspecialchars((string)$searchQuery)?>" placeholder="Buscar OT, CC, cliente..." style="padding:5px 10px;border:1px solid #CBD5E1;border-radius:4px;font-size:12px;width:220px">
                        <button type="submit" class="btnblue" style="padding:5px 10px;font-size:12px"><i class="fa fa-search"></i> Buscar</button>
                        <?php if (!empty($searchQuery)): ?>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <?php if ($tableProcess !== ''): ?>
                <?php renderMachineWorkOrderTable($unscheduledOrders, 'unscheduled', $tableProcess); ?>
            <?php else: ?>
            <table border="0" width="100%" cellpadding="8" cellspacing="0" style="min-width:1100px">
            <colgroup>
                <col width="90">
                <col width="80">
                <col width="160">
                <col width="180">
                <col width="75">
                <col width="110">
                <?php if ($isFlexo): ?>
                    <col width="140">
                    <col width="85">
                    <col width="100">
                    <col width="100">
                    <col width="105">
                <?php elseif ($isSeri): ?>
                    <col width="140">
                    <col width="90">
                    <col width="90">
                    <col width="90">
                <?php elseif ($isRebobinadora): ?>
                    <col width="150">
                    <col width="90">
                    <col width="90">
                    <col width="90">
                <?php else: ?>
                    <col width="130">
                    <col width="90">
                    <col width="90">
                <?php endif; ?>
                <col width="65">
                <col width="120">
            </colgroup>
            <thead>
                <tr>
                    <td class="tdheader">N° C.C.</td>
                    <td class="tdheader">N° O.T.</td>
                    <td class="tdheader">Cliente</td>
                    <td class="tdheader">Diseño / Producto</td>
                    <td class="tdheader" align="center">Material</td>
                    <td class="tdheader">Medidas</td>
                    <?php if ($isFlexo): ?>
                        <td class="tdheader">Colores</td>
                        <td class="tdheader" align="center">Rodillo [Z]</td>
                        <td class="tdheader" align="right" style="min-width:95px">Planificación</td>
                        <td class="tdheader" align="right" style="min-width:95px">Producidas</td>
                        <td class="tdheader" align="right" style="min-width:105px;color:#0284C7">Total CC</td>
                    <?php elseif ($isSeri): ?>
                        <td class="tdheader">Colores</td>
                        <td class="tdheader" align="right">Contador</td>
                        <td class="tdheader" align="right">Planificado</td>
                        <td class="tdheader" align="right">Producidas</td>
                    <?php elseif ($isRebobinadora): ?>
                        <td class="tdheader">Actividad</td>
                        <td class="tdheader" align="right">Metros</td>
                        <td class="tdheader" align="right">Planificado</td>
                        <td class="tdheader" align="right">Producidas</td>
                    <?php else: ?>
                        <td class="tdheader">Tipo Impresión</td>
                        <td class="tdheader" align="right">Planificado</td>
                        <td class="tdheader" align="right">Producidas</td>
                    <?php endif; ?>
                    <td class="tdheader" align="center">Imagen</td>
                    <td class="tdheader" align="center">Acción</td>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($unscheduledOrders)): ?>
                <tr>
                    <td colspan="14" class="tdnrm" style="text-align:center;padding:35px 20px;color:#64748B">
                        <i class="fa fa-check-circle" style="font-size:28px;color:#10B981;margin-bottom:8px;display:block"></i>
                        <?php if (!empty($searchQuery)): ?>
                            No se encontraron órdenes fuera de planificación que coincidan con "<b><?=htmlspecialchars($searchQuery)?></b>".
                        <?php else: ?>
                            ¡Excelente! No hay órdenes pendientes fuera de planificación compatibles con esta máquina.
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($unscheduledOrders as $uo):
                    $imgHash = trim((string)($uo['fab_design_imagehash'] ?? $uo['item_img_hash'] ?? ''));
                    $hasImg = ($imgHash !== '' && $imgHash !== 'dummy');
                    $imgUrl = $hasImg ? ('/production/design-image?folder=docs.order&hash=' . rawurlencode($imgHash)) : '';
                    
                    $prodSku = trim((string)($uo['item_number_prod'] ?? ''));
                    $prodTitle = trim((string)($uo['item_title'] ?? ''));
                    $designName = trim((string)($uo['fab_design_name'] ?? ''));
                    $fullDesign = ($prodSku !== '' && $prodTitle !== '') ? "{$prodSku} | {$prodTitle}" : ($prodTitle ?: ($prodSku ?: ($designName ?: '-')));
                    
                    $w = (int)($uo['fab_med_width'] ?? 0);
                    $h = (int)($uo['fab_med_height'] ?? 0);
                    $fuelle = (int)($uo['fab_med_fuelle'] ?? 0);
                    $medidasStr = $w > 0 ? ("{$w} x {$h}" . ($fuelle > 0 ? " + {$fuelle}" : "") . " cm") : ((float)($uo['req_operador_tela_width'] ?? 0) > 0 ? (float)$uo['req_operador_tela_width'] . ' cm' : '-');
                    $mat = !empty($uo['fab_type']) ? (string)$uo['fab_type'] : 'TNT';
                    
                    $colores = [];
                    for ($i = 1; $i <= 6; $i++) {
                        $c = trim((string)($uo["fab_print_colordesc_{$i}"] ?? ''));
                        if ($c !== '') {
                            $colores[] = $c;
                        }
                    }
                    
                    $cantBolsas = (float)($uo['item_amount'] ?? 0);
                    $producidas = (float)($uo['total_produced'] ?? 0);
                    $producidasMts = (float)($uo['total_produced_meters'] ?? 0);
                    $totalCcBolsas = (float)($uo['total_cc_units'] ?? $producidas);
                    $totalCcMts = (float)($uo['total_cc_meters'] ?? $producidasMts);
                    $metros = (float)($uo['flexo_metros'] ?? 0);
                    $z = (int)($uo['flexo_z'] ?? 0);
                    $contador = (float)($uo['seri_contador'] ?? 0);
                    $actividad = !empty($uo['rebo_distribucion']) && $uo['rebo_distribucion'] !== '-' ? $uo['rebo_distribucion'] : (!empty($uo['req_rebo_type']) ? ('Corte ' . ucfirst((string)$uo['req_rebo_type'])) : 'Corte / Rebobinado');
                ?>
                    <tr onmouseover="this.style.backgroundColor='#FEF9C3'" onmouseout="this.style.backgroundColor='transparent'">
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$uo['req_number'])?></b></td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$uo['prd_number'])?></b></td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$uo['cust_name'])?></b></td>
                        <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($fullDesign)?></td>
                        <td class="tdnrm" align="center">
                            <span style="background:#F1F5F9;color:#334155;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600"><?=htmlspecialchars($mat)?></span>
                        </td>
                        <td class="tdnrm" style="white-space:nowrap"><?=htmlspecialchars($medidasStr)?></td>
                        <?php if ($isFlexo): ?>
                            <td class="tdnrm">
                                <?php if (empty($colores)): ?>
                                    <span style="color:#94A3B8;font-size:12px"><i>Sin colores</i></span>
                                <?php else: ?>
                                    <div style="display:flex;flex-direction:column;gap:2px">
                                        <?php foreach ($colores as $idx => $colorName): ?>
                                            <span style="font-size:11px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:3px;padding:1px 5px;color:#334155;white-space:nowrap">
                                                <b><?=($idx + 1)?>:</b> <?=htmlspecialchars($colorName)?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="center">
                                <?php if ($z > 0): ?>
                                    <span style="background:#FEF3C7;color:#92400E;padding:3px 8px;border-radius:4px;font-weight:700;font-size:12px">Z = <?=$z?></span>
                                <?php else: ?>
                                    <span style="color:#94A3B8;font-size:12px">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="right" style="white-space:nowrap">
                                <div style="font-weight:700;color:#0F172A;font-size:12.5px"><?=number_format($metros, 0, ',', '.')?> m</div>
                                <div style="font-size:11px;color:#64748B;font-weight:600"><?=number_format($cantBolsas, 0, ',', '.')?> un.</div>
                            </td>
                            <td class="tdnrm" align="right" style="white-space:nowrap">
                                <div style="font-weight:700;color:#0F766E;font-size:12.5px"><?=number_format($producidasMts, 0, ',', '.')?> m</div>
                                <div style="font-size:11px;color:#0F766E;font-weight:600"><?=number_format($producidas, 0, ',', '.')?> un.</div>
                            </td>
                            <td class="tdnrm" align="right" style="white-space:nowrap;background-color:rgba(2, 132, 199, 0.05)">
                                <div style="font-weight:800;color:#0369A1;font-size:12.5px"><?=number_format($totalCcMts, 0, ',', '.')?> m</div>
                                <div style="font-size:11px;color:#0284C7;font-weight:700"><?=number_format($totalCcBolsas, 0, ',', '.')?> un.</div>
                            </td>
                        <?php elseif ($isSeri): ?>
                            <td class="tdnrm">
                                <?php if (empty($colores)): ?>
                                    <span style="color:#94A3B8;font-size:12px"><i>Sin colores</i></span>
                                <?php else: ?>
                                    <div style="display:flex;flex-direction:column;gap:2px">
                                        <?php foreach ($colores as $idx => $colorName): ?>
                                            <span style="font-size:11px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:3px;padding:1px 5px;color:#334155;white-space:nowrap">
                                                <b><?=($idx + 1)?>:</b> <?=htmlspecialchars($colorName)?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="right"><b style="color:#0F766E"><?=number_format($contador, 0, ',', '.')?></b></td>
                            <td class="tdnrm" align="right"><b><?=number_format($cantBolsas, 0, ',', '.')?></b></td>
                            <td class="tdnrm" align="right"><b style="color:#0F766E"><?=number_format($producidas, 0, ',', '.')?></b></td>
                        <?php elseif ($isRebobinadora): ?>
                            <td class="tdnrm"><span style="color:#0F766E;font-weight:600;font-size:12.5px"><i class="fa fa-cut"></i> <?=htmlspecialchars($actividad)?></span><div style="font-weight:800">Color: <?=htmlspecialchars((string)($uo['fabric_color'] ?? '-'))?></div></td>
                            <td class="tdnrm" align="right"><b><?=number_format($metros, 0, ',', '.')?></b> m</td>
                            <td class="tdnrm" align="right"><b><?=number_format($cantBolsas, 0, ',', '.')?></b></td>
                            <td class="tdnrm" align="right"><b style="color:#0F766E"><?=number_format($producidas, 0, ',', '.')?></b></td>
                        <?php else: ?>
                            <td class="tdnrm"><?=htmlspecialchars((string)$uo['fab_printtype'])?></td>
                            <td class="tdnrm" align="right"><b><?=number_format($cantBolsas, 0, ',', '.')?></b></td>
                            <td class="tdnrm" align="right"><b style="color:#0F766E"><?=number_format($producidas, 0, ',', '.')?></b></td>
                        <?php endif; ?>
                        <td class="tdnrm" align="center">
                            <?php if ($hasImg): ?>
                                <div style="display:inline-flex;align-items:center;gap:4px;cursor:pointer" onclick="openDesignModal('<?=htmlspecialchars($imgUrl)?>', '<?=htmlspecialchars((string)$uo['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$uo['cust_name']))?>', '<?=htmlspecialchars(addslashes($fullDesign))?>', '<?=htmlspecialchars($medidasStr)?>')" title="Click para ver foto del diseño">
                                    <img src="<?=htmlspecialchars($imgUrl)?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #CBD5E1;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
                                    <i class="fa fa-search-plus" style="color:#0284C7;font-size:11px"></i>
                                </div>
                            <?php else: ?>
                                <span style="color:#94A3B8;font-size:12px"><i>Sin foto</i></span>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm" align="center">
                            <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                                <form method="post" action="/production/work-orders/init-unscheduled" style="margin:0">
                                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                    <input type="hidden" name="prd_id" value="<?=$uo['prd_id']?>">
                                    <button type="submit" class="btngreen" style="padding:6px 12px;background:#15803D" onclick="return confirm('¿Confirmas incorporar esta OT no planificada (#<?=htmlspecialchars((string)$uo['prd_number'])?>) a esta máquina e iniciar producción ahora?');">
                                        <i class="fa fa-play"></i> Iniciar OT
                                    </button>
                                </form>
                                <a href="/production/materials/request?prd_id=<?=$uo['prd_id']?>" class="btnblue" style="padding:6px 10px;font-size:11px;text-decoration:none" title="Solicitar o cargar bobinas para esta OT no planificada">
                                    <i class="fa fa-dolly"></i> Solicitar Bobina
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            </table>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- ============================================================== -->
        <!-- VISTA DE PRODUCTOS PROGRAMADOS EN AGENDA                       -->
        <!-- ============================================================== -->
        <?php if (empty($scheduledOrders)): ?>
            <div style="background:#EFF6FF;border:1px solid #BFDBFE;color:#1E40AF;padding:14px 18px;border-radius:6px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div style="display:flex;align-items:center;gap:10px;font-size:13.5px">
                    <i class="fa fa-info-circle" style="font-size:22px;color:#3B82F6"></i>
                    <div>
                        <b>No hay órdenes de trabajo programadas para esta fecha.</b>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($isRebobinadora): ?>
            <!-- DISEÑO REBOBINADORA: OTs Programadas -->
            <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px;overflow-x:auto">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px">
                    <div style="font-size:15px;font-weight:bold;color:#115452;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <span><i class="fa fa-fw fa-tasks"></i> OTs Programadas para esta Máquina</span>
                        <?php if (!empty($dateFilter)): ?>
                            <span style="font-size:12px;background:#E0F2FE;color:#0369A1;padding:3px 10px;border-radius:12px;font-weight:600;border:1px solid #BAE6FD">
                                <i class="fa fa-calendar-day"></i> Planificado para: <b><?=htmlspecialchars($dateFilter)?></b><?= $dateFilter === date('d.m.Y') ? ' (Hoy)' : '' ?>
                            </span>
                        <?php else: ?>
                            <span style="font-size:12px;background:#F1F5F9;color:#475569;padding:3px 10px;border-radius:12px;font-weight:600">
                                Todas las fechas
                            </span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px">
                        <form method="get" action="/production/work-orders/new" style="margin:0;display:inline-flex;align-items:center;gap:6px">
                            <label style="font-size:12px;color:#64748B;font-weight:600"><i class="fa fa-calendar-alt"></i> Fecha:</label>
                            <input type="date" name="plan_date" value="<?= !empty($dateFilter) && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateFilter, $mD) ? "{$mD[3]}-{$mD[2]}-{$mD[1]}" : date('Y-m-d') ?>" style="padding:4px 8px;border:1px solid #CBD5E1;border-radius:4px;font-size:12px" onchange="this.form.submit()">
                            <?php if (!empty($dateFilter) && $dateFilter !== date('d.m.Y')): ?>
                                <a href="/production/work-orders/new" class="btn" style="background:#0284C7;color:#fff;padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Hoy</a>
                            <?php endif; ?>
                            <?php if (empty($dateFilter)): ?>
                                <a href="/production/work-orders/new" class="btngreen" style="padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Solo Hoy</a>
                            <?php else: ?>
                                <a href="/production/work-orders/new?plan_date=all" class="btn" style="background:#64748B;color:#fff;padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Todas</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <table border="0" width="100%" cellpadding="8" cellspacing="0">
                <colgroup>
                    <col width="95">
                    <col width="85">
                    <col>
                    <col width="120">
                    <col width="85">
                    <col width="160">
                    <col width="120">
                    <col width="90">
                    <col width="115">
                </colgroup>
                <thead>
                    <tr>
                        <td class="tdheader">N° C.C.</td>
                        <td class="tdheader">N° O.T.</td>
                        <td class="tdheader">Cliente</td>
                        <td class="tdheader" align="center">Diseño</td>
                        <td class="tdheader" align="center">Material</td>
                        <td class="tdheader">Actividad</td>
                        <td class="tdheader">Medidas</td>
                        <td class="tdheader" align="right">Metros</td>
                        <td class="tdheader" align="center">Acción</td>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($scheduledOrders)): ?>
                    <tr><td colspan="9" class="tdnrm" style="text-align:center;padding:25px;color:#999">No hay órdenes de trabajo programadas para esta máquina.</td></tr>
                <?php else: ?>
                    <?php foreach ($scheduledOrders as $so):
                        $imgHash = trim((string)($so['fab_design_imagehash'] ?? $so['item_img_hash'] ?? ''));
                        $hasImg = ($imgHash !== '' && $imgHash !== 'dummy');
                        $imgUrl = $hasImg ? ('/production/design-image?folder=docs.order&hash=' . rawurlencode($imgHash)) : '';
                        $designName = (string)($so['fab_design_name'] ?? '');
                        $w = (int)($so['fab_med_width'] ?? 0);
                        $h = (int)($so['fab_med_height'] ?? 0);
                        $fuelle = (int)($so['fab_med_fuelle'] ?? 0);
                        $medidasStr = $w > 0 ? ("{$w}x{$h}" . ($fuelle > 0 ? " + {$fuelle}" : "") . " cm") : ((float)($so['req_operador_tela_width'] ?? 0) > 0 ? (float)$so['req_operador_tela_width'] . ' cm' : '-');
                        $mat = !empty($so['fab_type']) ? (string)$so['fab_type'] : 'TNT';
                        $actividad = !empty($so['rebo_distribucion']) && $so['rebo_distribucion'] !== '-' ? $so['rebo_distribucion'] : (!empty($so['req_rebo_type']) ? ('Corte ' . ucfirst((string)$so['req_rebo_type'])) : 'Corte / Rebobinado');
                        $metros = (float)($so['ag_amount'] ?? 0);
                    ?>
                        <tr onmouseover="this.style.backgroundColor='#F0F7F6'" onmouseout="this.style.backgroundColor='transparent'">
                            <td class="tdnrm"><b><?=htmlspecialchars((string)$so['req_number'])?></b></td>
                            <td class="tdnrm"><b><?=htmlspecialchars((string)$so['prd_number'])?></b></td>
                            <td class="tdnrm">
                                <b><?=htmlspecialchars((string)$so['cust_name'])?></b>
                                <?php if (!empty($so['item_title'])): ?>
                                    <div style="font-size:11.5px;color:#64748B"><?=htmlspecialchars((string)$so['item_title'])?></div>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="center">
                                <?php if ($hasImg): ?>
                                    <div style="display:inline-flex;align-items:center;gap:6px;cursor:pointer" onclick="openDesignModal('<?=htmlspecialchars($imgUrl)?>', '<?=htmlspecialchars((string)$so['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$so['cust_name']))?>', '<?=htmlspecialchars(addslashes($designName))?>', '<?=htmlspecialchars($medidasStr)?>')" title="Click para ver foto del diseño">
                                        <img src="<?=htmlspecialchars($imgUrl)?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #CBD5E1;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
                                        <i class="fa fa-search-plus" style="color:#0284C7;font-size:12px"></i>
                                    </div>
                                <?php else: ?>
                                    <span style="color:#94A3B8;font-size:12px"><i>Sin foto</i></span>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="center">
                                <span style="background:#F1F5F9;color:#334155;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600"><?=htmlspecialchars($mat)?></span>
                                <div style="font-weight:800;margin-top:5px">Color: <?=htmlspecialchars((string)($so['fabric_color'] ?? '-'))?></div>
                            </td>
                            <td class="tdnrm">
                                <span style="color:#0F766E;font-weight:600;font-size:12.5px"><i class="fa fa-cut"></i> <?=htmlspecialchars($actividad)?></span>
                            </td>
                            <td class="tdnrm"><?=htmlspecialchars($medidasStr)?></td>
                            <td class="tdnrm" align="right"><b><?=number_format($metros, 0, ',', '.')?></b> m</td>
                            <td class="tdnrm" align="center">
                                <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                                    <form method="post" action="/production/work-orders/init" style="margin:0">
                                        <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                        <input type="hidden" name="ag_id" value="<?=$so['ag_id']?>">
                                        <button type="submit" class="btngreen" style="padding:6px 12px"><i class="fa fa-play"></i> Iniciar OT</button>
                                    </form>
                                    <a href="/production/materials/request?agid=<?=$so['ag_id']?>" class="btnblue" style="padding:6px 10px;font-size:11px;text-decoration:none" title="Solicitar o cargar bobinas antes de iniciar la OT">
                                        <i class="fa fa-dolly"></i> Solicitar Bobina
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                </table>
            </div>
        <?php elseif ($isFlexo): ?>
            <!-- DISEÑO FLEXOGRAFÍA: OTs Programadas -->
            <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px;overflow-x:auto">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px">
                    <div style="font-size:15px;font-weight:bold;color:#115452;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <span><i class="fa fa-fw fa-tasks"></i> OTs Programadas para esta Máquina</span>
                        <?php if (!empty($dateFilter)): ?>
                            <span style="font-size:12px;background:#E0F2FE;color:#0369A1;padding:3px 10px;border-radius:12px;font-weight:600;border:1px solid #BAE6FD">
                                <i class="fa fa-calendar-day"></i> Planificado para: <b><?=htmlspecialchars($dateFilter)?></b><?= $dateFilter === date('d.m.Y') ? ' (Hoy)' : '' ?>
                            </span>
                        <?php else: ?>
                            <span style="font-size:12px;background:#F1F5F9;color:#475569;padding:3px 10px;border-radius:12px;font-weight:600">
                                Todas las fechas
                            </span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px">
                        <form method="get" action="/production/work-orders/new" style="margin:0;display:inline-flex;align-items:center;gap:6px">
                            <label style="font-size:12px;color:#64748B;font-weight:600"><i class="fa fa-calendar-alt"></i> Fecha:</label>
                            <input type="date" name="plan_date" value="<?= !empty($dateFilter) && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateFilter, $mD) ? "{$mD[3]}-{$mD[2]}-{$mD[1]}" : date('Y-m-d') ?>" style="padding:4px 8px;border:1px solid #CBD5E1;border-radius:4px;font-size:12px" onchange="this.form.submit()">
                            <?php if (!empty($dateFilter) && $dateFilter !== date('d.m.Y')): ?>
                                <a href="/production/work-orders/new" class="btn" style="background:#0284C7;color:#fff;padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Hoy</a>
                            <?php endif; ?>
                            <?php if (empty($dateFilter)): ?>
                                <a href="/production/work-orders/new" class="btngreen" style="padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Solo Hoy</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <table border="0" width="100%" cellpadding="8" cellspacing="0" style="min-width:1150px">
                <colgroup>
                    <col width="90">
                    <col width="75">
                    <col width="150">
                    <col width="170">
                    <col width="70">
                    <col width="105">
                    <col width="140">
                    <col width="85">
                    <col width="95">
                    <col width="100">
                    <col width="100">
                    <col width="105">
                    <col width="65">
                    <col width="105">
                </colgroup>
                <thead>
                    <tr>
                        <td class="tdheader">N° C.C.</td>
                        <td class="tdheader">N° O.T.</td>
                        <td class="tdheader">Cliente</td>
                        <td class="tdheader">Diseño</td>
                        <td class="tdheader" align="center">Material</td>
                        <td class="tdheader">Medidas</td>
                        <td class="tdheader">Colores</td>
                        <td class="tdheader" align="center">Rodillo [Z]</td>
                        <td class="tdheader" align="center">Ubicación Clisé</td>
                        <td class="tdheader" align="right" style="min-width:95px">Planificación</td>
                        <td class="tdheader" align="right" style="min-width:95px">Producidas</td>
                        <td class="tdheader" align="right" style="min-width:105px;color:#0284C7">Total CC</td>
                        <td class="tdheader" align="center">Imagen</td>
                        <td class="tdheader" align="center">Opciones</td>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($scheduledOrders)): ?>
                    <tr>
                        <td colspan="14" class="tdnrm" style="text-align:center;padding:35px 20px;color:#64748B">
                            <i class="fa fa-calendar-times" style="font-size:26px;color:#94A3B8;margin-bottom:8px;display:block"></i>
                            No hay órdenes de trabajo planificadas para esta máquina en la fecha <b><?=htmlspecialchars($dateFilter ?: date('d.m.Y'))?></b>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($scheduledOrders as $so):
                        $imgHash = trim((string)($so['fab_design_imagehash'] ?? $so['item_img_hash'] ?? ''));
                        $hasImg = ($imgHash !== '' && $imgHash !== 'dummy');
                        $imgUrl = $hasImg ? ('/production/design-image?folder=docs.order&hash=' . rawurlencode($imgHash)) : '';
                        
                        $prodSku = trim((string)($so['item_number_prod'] ?? ''));
                        $prodTitle = trim((string)($so['item_title'] ?? ''));
                        $designName = trim((string)($so['fab_design_name'] ?? ''));
                        $fullDesign = ($prodSku !== '' && $prodTitle !== '') ? "{$prodSku} | {$prodTitle}" : ($prodTitle ?: ($prodSku ?: ($designName ?: '-')));
                        
                        $w = (int)($so['fab_med_width'] ?? 0);
                        $h = (int)($so['fab_med_height'] ?? 0);
                        $fuelle = (int)($so['fab_med_fuelle'] ?? 0);
                        $medidasStr = $w > 0 ? ("{$w} x {$h}" . ($fuelle > 0 ? " + {$fuelle}" : "") . " cm") : '-';
                        $mat = !empty($so['fab_type']) ? (string)$so['fab_type'] : 'TNT';
                        
                        $colores = [];
                        for ($i = 1; $i <= 6; $i++) {
                            $c = trim((string)($so["fab_print_colordesc_{$i}"] ?? ''));
                            if ($c !== '') {
                                $colores[] = $c;
                            }
                        }
                        
                        $z = (int)($so['flexo_z'] ?? 0);
                        $ubicacion = trim((string)($so['req_infoaddprd_cliche_ubicacion'] ?? ''));
                        $codCliche = trim((string)($so['req_infoaddprd_cliche_codigo'] ?? ''));
                        
                        $metros = (float)($so['flexo_metros'] ?? 0);
                        $cantBolsas = (float)($so['ag_amount'] ?? 0);
                        $producidas = (float)($so['total_produced'] ?? 0);
                        $producidasMts = (float)($so['total_produced_meters'] ?? 0);
                        $totalCcBolsas = (float)($so['total_cc_units'] ?? $producidas);
                        $totalCcMts = (float)($so['total_cc_meters'] ?? $producidasMts);
                    ?>
                        <tr onmouseover="this.style.backgroundColor='#F0F7F6'" onmouseout="this.style.backgroundColor='transparent'">
                            <td class="tdnrm"><b><?=htmlspecialchars((string)$so['req_number'])?></b></td>
                            <td class="tdnrm"><b><?=htmlspecialchars((string)$so['prd_number'])?></b></td>
                            <td class="tdnrm"><b><?=htmlspecialchars((string)$so['cust_name'])?></b></td>
                            <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($fullDesign)?></td>
                            <td class="tdnrm" align="center">
                                <span style="background:#F1F5F9;color:#334155;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600"><?=htmlspecialchars($mat)?></span>
                            </td>
                            <td class="tdnrm" style="white-space:nowrap"><?=htmlspecialchars($medidasStr)?></td>
                            <td class="tdnrm">
                                <?php if (empty($colores)): ?>
                                    <span style="color:#94A3B8;font-size:12px"><i>Sin colores</i></span>
                                <?php else: ?>
                                    <div style="display:flex;flex-direction:column;gap:2px">
                                        <?php foreach ($colores as $idx => $colorName): ?>
                                            <span style="font-size:11px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:3px;padding:1px 5px;color:#334155;white-space:nowrap">
                                                <b><?=($idx + 1)?>:</b> <?=htmlspecialchars($colorName)?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="center">
                                <?php if ($z > 0): ?>
                                    <span style="background:#FEF3C7;color:#92400E;padding:3px 8px;border-radius:4px;font-weight:700;font-size:12px">Z = <?=$z?></span>
                                <?php else: ?>
                                    <span style="color:#94A3B8;font-size:12px">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="center">
                                <?php if ($ubicacion !== ''): ?>
                                    <div style="font-weight:700;color:#0F766E;font-size:13px"><?=htmlspecialchars($ubicacion)?></div>
                                    <?php if ($codCliche !== '' && $codCliche !== $ubicacion): ?>
                                        <div style="font-size:10.5px;color:#64748B"><?=htmlspecialchars($codCliche)?></div>
                                    <?php endif; ?>
                                <?php elseif ($codCliche !== ''): ?>
                                    <div style="font-weight:600;font-size:12px;color:#334155"><?=htmlspecialchars($codCliche)?></div>
                                <?php else: ?>
                                    <span style="color:#94A3B8;font-size:12px">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="right" style="white-space:nowrap">
                                <div style="font-weight:700;color:#0F172A;font-size:12.5px"><?=number_format($metros, 0, ',', '.')?> m</div>
                                <div style="font-size:11px;color:#64748B;font-weight:600"><?=number_format($cantBolsas, 0, ',', '.')?> un.</div>
                            </td>
                            <td class="tdnrm" align="right" style="white-space:nowrap">
                                <div style="font-weight:700;color:#0F766E;font-size:12.5px"><?=number_format($producidasMts, 0, ',', '.')?> m</div>
                                <div style="font-size:11px;color:#0F766E;font-weight:600"><?=number_format($producidas, 0, ',', '.')?> un.</div>
                            </td>
                            <td class="tdnrm" align="right" style="white-space:nowrap;background-color:rgba(2, 132, 199, 0.05)">
                                <div style="font-weight:800;color:#0369A1;font-size:12.5px"><?=number_format($totalCcMts, 0, ',', '.')?> m</div>
                                <div style="font-size:11px;color:#0284C7;font-weight:700"><?=number_format($totalCcBolsas, 0, ',', '.')?> un.</div>
                            </td>
                            <td class="tdnrm" align="center">
                                <?php if ($hasImg): ?>
                                    <div style="display:inline-flex;align-items:center;gap:4px;cursor:pointer" onclick="openDesignModal('<?=htmlspecialchars($imgUrl)?>', '<?=htmlspecialchars((string)$so['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$so['cust_name']))?>', '<?=htmlspecialchars(addslashes($fullDesign))?>', '<?=htmlspecialchars($medidasStr)?>')" title="Click para ver foto del diseño">
                                        <img src="<?=htmlspecialchars($imgUrl)?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #CBD5E1;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
                                        <i class="fa fa-search-plus" style="color:#0284C7;font-size:11px"></i>
                                    </div>
                                <?php else: ?>
                                    <span style="color:#94A3B8;font-size:12px"><i>Sin foto</i></span>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="center">
                                <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                                    <form method="post" action="/production/work-orders/init" style="margin:0">
                                        <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                        <input type="hidden" name="ag_id" value="<?=$so['ag_id']?>">
                                        <button type="submit" class="btngreen" style="padding:6px 12px"><i class="fa fa-play"></i> Iniciar OT</button>
                                    </form>
                                    <a href="/production/materials/request?agid=<?=$so['ag_id']?>" class="btnblue" style="padding:6px 10px;font-size:11px;text-decoration:none" title="Solicitar o cargar bobinas antes de iniciar la OT">
                                        <i class="fa fa-dolly"></i> Solicitar Bobina
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                </table>
            </div>
        <?php elseif ($isSeri && $tableProcess !== 'pulpo'): ?>
            <!-- DISEÑO SERIGRAFÍA: OTs Programadas -->
            <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
                    <div style="font-size:15px;font-weight:bold;color:#115452">
                        <i class="fa fa-fw fa-paint-brush"></i> OTs Programadas para Serigrafía
                        <?php if (!empty($dateFilter)): ?>
                            <span style="font-size:13px;font-weight:normal;color:#666666;margin-left:8px">
                                (Fecha: <b><?=htmlspecialchars($dateFilter)?></b>)
                            </span>
                        <?php else: ?>
                            <span style="font-size:13px;font-weight:normal;color:#666666;margin-left:8px">
                                (Todas las fechas)
                            </span>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex;align-items:center;gap:8px">
                        <form method="get" action="/production/work-orders/new" style="margin:0;display:inline-flex;align-items:center;gap:6px">
                            <label style="font-size:12px;color:#64748B;font-weight:600"><i class="fa fa-calendar-alt"></i> Fecha:</label>
                            <input type="date" name="plan_date" value="<?= !empty($dateFilter) && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateFilter, $mD) ? "{$mD[3]}-{$mD[2]}-{$mD[1]}" : date('Y-m-d') ?>" style="padding:4px 8px;border:1px solid #CBD5E1;border-radius:4px;font-size:12px" onchange="this.form.submit()">
                            <?php if (!empty($dateFilter) && $dateFilter !== date('d.m.Y')): ?>
                                <a href="/production/work-orders/new" class="btn" style="background:#0284C7;color:#fff;padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Hoy</a>
                            <?php endif; ?>
                            <?php if (empty($dateFilter)): ?>
                                <a href="/production/work-orders/new" class="btngreen" style="padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Solo Hoy</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <table border="0" width="100%" cellpadding="8" cellspacing="0" style="min-width:1050px">
                <colgroup>
                    <col width="90">
                    <col width="75">
                    <col width="160">
                    <col width="180">
                    <col width="75">
                    <col width="110">
                    <col width="150">
                    <col width="95">
                    <col width="95">
                    <col width="65">
                    <col width="110">
                </colgroup>
                <thead>
                    <tr>
                        <td class="tdheader">N° C.C.</td>
                        <td class="tdheader">N° O.T.</td>
                        <td class="tdheader">Cliente</td>
                        <td class="tdheader">Diseño</td>
                        <td class="tdheader" align="center">Material</td>
                        <td class="tdheader">Medidas</td>
                        <td class="tdheader">Colores</td>
                        <td class="tdheader" align="right">Contador</td>
                        <td class="tdheader" align="right">Cantidad Bolsas</td>
                        <td class="tdheader" align="center">Imagen</td>
                        <td class="tdheader" align="center">Opciones</td>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($scheduledOrders)): ?>
                    <tr>
                        <td colspan="11" class="tdnrm" style="text-align:center;padding:35px 20px;color:#64748B">
                            <i class="fa fa-calendar-times" style="font-size:26px;color:#94A3B8;margin-bottom:8px;display:block"></i>
                            No hay órdenes de trabajo planificadas para esta máquina en la fecha <b><?=htmlspecialchars($dateFilter ?: date('d.m.Y'))?></b>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($scheduledOrders as $so):
                        $imgHash = trim((string)($so['fab_design_imagehash'] ?? $so['item_img_hash'] ?? ''));
                        $hasImg = ($imgHash !== '' && $imgHash !== 'dummy');
                        $imgUrl = $hasImg ? ('/production/design-image?folder=docs.order&hash=' . rawurlencode($imgHash)) : '';
                        
                        $prodSku = trim((string)($so['item_number_prod'] ?? ''));
                        $prodTitle = trim((string)($so['item_title'] ?? ''));
                        $designName = trim((string)($so['fab_design_name'] ?? ''));
                        $fullDesign = ($prodSku !== '' && $prodTitle !== '') ? "{$prodSku} | {$prodTitle}" : ($prodTitle ?: ($prodSku ?: ($designName ?: '-')));
                        
                        $w = (int)($so['fab_med_width'] ?? 0);
                        $h = (int)($so['fab_med_height'] ?? 0);
                        $fuelle = (int)($so['fab_med_fuelle'] ?? 0);
                        $medidasStr = $w > 0 ? ("{$w} x {$h}" . ($fuelle > 0 ? " + {$fuelle}" : "") . " cm") : '-';
                        $mat = !empty($so['fab_type']) ? (string)$so['fab_type'] : 'TNT';
                        
                        $colores = [];
                        for ($i = 1; $i <= 6; $i++) {
                            $c = trim((string)($so["fab_print_colordesc_{$i}"] ?? ''));
                            if ($c !== '') {
                                $colores[] = $c;
                            }
                        }
                        
                        $cantBolsas = (float)($so['ag_amount'] ?? 0);
                        $contador = (float)($so['seri_contador'] ?? 0);
                    ?>
                        <tr onmouseover="this.style.backgroundColor='#F0F7F6'" onmouseout="this.style.backgroundColor='transparent'">
                            <td class="tdnrm"><b><?=htmlspecialchars((string)$so['req_number'])?></b></td>
                            <td class="tdnrm"><b><?=htmlspecialchars((string)$so['prd_number'])?></b></td>
                            <td class="tdnrm"><b><?=htmlspecialchars((string)$so['cust_name'])?></b></td>
                            <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($fullDesign)?></td>
                            <td class="tdnrm" align="center">
                                <span style="background:#F1F5F9;color:#334155;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600"><?=htmlspecialchars($mat)?></span>
                            </td>
                            <td class="tdnrm" style="white-space:nowrap"><?=htmlspecialchars($medidasStr)?></td>
                            <td class="tdnrm">
                                <?php if (empty($colores)): ?>
                                    <span style="color:#94A3B8;font-size:12px"><i>Sin colores</i></span>
                                <?php else: ?>
                                    <div style="display:flex;flex-direction:column;gap:2px">
                                        <?php foreach ($colores as $idx => $colorName): ?>
                                            <span style="font-size:11px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:3px;padding:1px 5px;color:#334155;white-space:nowrap">
                                                <b><?=($idx + 1)?>:</b> <?=htmlspecialchars($colorName)?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="right">
                                <b style="color:#0F766E"><?=number_format($contador, 0, ',', '.')?></b>
                            </td>
                            <td class="tdnrm" align="right"><b><?=number_format($cantBolsas, 0, ',', '.')?></b></td>
                            <td class="tdnrm" align="center">
                                <?php if ($hasImg): ?>
                                    <div style="display:inline-flex;align-items:center;gap:4px;cursor:pointer" onclick="openDesignModal('<?=htmlspecialchars($imgUrl)?>', '<?=htmlspecialchars((string)$so['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$so['cust_name']))?>', '<?=htmlspecialchars(addslashes($fullDesign))?>', '<?=htmlspecialchars($medidasStr)?>')" title="Click para ver foto del diseño">
                                        <img src="<?=htmlspecialchars($imgUrl)?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #CBD5E1;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
                                        <i class="fa fa-search-plus" style="color:#0284C7;font-size:11px"></i>
                                    </div>
                                <?php else: ?>
                                    <span style="color:#94A3B8;font-size:12px"><i>Sin foto</i></span>
                                <?php endif; ?>
                            </td>
                            <td class="tdnrm" align="center">
                                <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                                    <form method="post" action="/production/work-orders/init" style="margin:0">
                                        <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                        <input type="hidden" name="ag_id" value="<?=$so['ag_id']?>">
                                        <button type="submit" class="btngreen" style="padding:6px 12px"><i class="fa fa-play"></i> Iniciar OT</button>
                                    </form>
                                    <a href="/production/materials/request?agid=<?=$so['ag_id']?>" class="btnblue" style="padding:6px 10px;font-size:11px;text-decoration:none" title="Solicitar o cargar bobinas antes de iniciar la OT">
                                        <i class="fa fa-dolly"></i> Solicitar Bobina
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                </table>
            </div>
        <?php else: ?>
            <!-- DISEÑO ESTÁNDAR / OTRAS MÁQUINAS (Selladoras, Embalaje, etc.) -->
            <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px;overflow-x:auto">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px">
                    <div style="font-size:15px;font-weight:bold;color:#115452;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <span><i class="fa fa-fw fa-tasks"></i> OTs Programadas para esta Máquina</span>
                        <?php if (!empty($dateFilter)): ?>
                            <span style="font-size:12px;background:#E0F2FE;color:#0369A1;padding:3px 10px;border-radius:12px;font-weight:600;border:1px solid #BAE6FD">
                                <i class="fa fa-calendar-day"></i> Planificado para: <b><?=htmlspecialchars($dateFilter)?></b><?= $dateFilter === date('d.m.Y') ? ' (Hoy)' : '' ?>
                            </span>
                        <?php else: ?>
                            <span style="font-size:12px;background:#F1F5F9;color:#475569;padding:3px 10px;border-radius:12px;font-weight:600">
                                Todas las fechas
                            </span>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px">
                        <form method="get" action="/production/work-orders/new" style="margin:0;display:inline-flex;align-items:center;gap:6px">
                            <label style="font-size:12px;color:#64748B;font-weight:600"><i class="fa fa-calendar-alt"></i> Fecha:</label>
                            <input type="date" name="plan_date" value="<?= !empty($dateFilter) && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateFilter, $mD) ? "{$mD[3]}-{$mD[2]}-{$mD[1]}" : date('Y-m-d') ?>" style="padding:4px 8px;border:1px solid #CBD5E1;border-radius:4px;font-size:12px" onchange="this.form.submit()">
                            <?php if (!empty($dateFilter) && $dateFilter !== date('d.m.Y')): ?>
                                <a href="/production/work-orders/new" class="btn" style="background:#0284C7;color:#fff;padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Hoy</a>
                            <?php endif; ?>
                            <?php if (empty($dateFilter)): ?>
                                <a href="/production/work-orders/new" class="btngreen" style="padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Solo Hoy</a>
                            <?php else: ?>
                                <a href="/production/work-orders/new?plan_date=all" class="btn" style="background:#64748B;color:#fff;padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Todas</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <?php if (empty($scheduledOrders)): ?>
                    <b class="msg_save_err" style="color:#C00"><i class="fa fa-fw fa-exclamation-triangle"></i> No existen OT's planificadas para esta fecha (<?=htmlspecialchars($dateFilter ?: date('d.m.Y'))?>).</b>
                <?php elseif ($tableProcess !== ''): ?>
                    <?php renderMachineWorkOrderTable($scheduledOrders, 'scheduled', $tableProcess); ?>
                <?php else: ?>
                    <table border="0" width="100%" cellpadding="6" cellspacing="0">
                    <colgroup>
                        <col width="60">
                        <col width="60">
                        <col>
                        <col>
                        <col>
                        <col>
                        <col>
                        <col>
                        <col>
                        <col>
                        <col>
                        <col width="110">
                    </colgroup>
                    <tr>
                        <td class="tdheader">OT</td>
                        <td class="tdheader">CC</td>
                        <td class="tdheader">Cliente</td>
                        <td class="tdheader">Código</td>
                        <td class="tdheader">Descripción</td>
                        <td class="tdheader">Tipo</td>
                        <td class="tdheader">Material</td>
                        <td class="tdheader" align="center"><?=$isFlexo ? 'Planificado' : 'Cantidad'?></td>
                        <td class="tdheader" align="center"><?=$isFlexo ? 'Producidas' : 'Producido'?></td>
                        <td class="tdheader" align="center">Saldo</td>
                        <td class="tdheader" align="center">Estado</td>
                        <td class="tdheader" align="center">Opciones</td>
                    </tr>
                    <?php foreach ($scheduledOrders as $so):
                        $prodAmt = (float)($so["total_produced"] ?? 0);
                        $itemAmt = (float)($so["item_amount"] ?? $so["ag_amount"]);
                        $saldo = max(0, $itemAmt - $prodAmt);
                        $isActive = (int)($so["ag_active"] ?? 1) > 0;
                        $btnCls = $isActive ? "btngreen" : "btnorange";
                    ?>
                    <tr onmouseover="this.style.backgroundColor='#F0F7F6'" onmouseout="this.style.backgroundColor='transparent'">
                        <td class="tdleft"><?=htmlspecialchars((string)$so["prd_number"])?>&nbsp;</td>
                        <td class="tdnrm"><nobr><?=htmlspecialchars((string)$so["req_number"])?></nobr>&nbsp;</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$so["cust_name"])?>&nbsp;</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$so["item_number_prod"])?>&nbsp;</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$so["item_title"])?>&nbsp;</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$so["fab_printtype"])?>&nbsp;</td>
                        <td class="tdnrm"><?=htmlspecialchars((string)$so["fab_type"])?>&nbsp;</td>
                        <td class="tdnrm" align="center"><?=number_format((float)$so["ag_amount"], 0, ',', '.')?>&nbsp;</td>
                        <td class="tdnrm" align="center"><?=number_format($prodAmt, 0, ',', '.')?></td>
                        <td class="tdnrm" align="center"><?=number_format($saldo, 0, ',', '.')?></td>
                        <td class="tdnrm" align="center">
                            <?php if ($isActive): ?>
                                <b class="msg_save_ok" style="color:#00A85A">Activado</b>
                            <?php else: ?>
                                <b class="msg_save_err" style="color:#C00">Inactivo</b>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm" align="center">
                            <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                                <form method="post" action="/production/work-orders/init" style="margin:0">
                                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                                    <input type="hidden" name="ag_id" value="<?=$so['ag_id']?>">
                                    <div class="<?=$btnCls?>" style="cursor:pointer;display:inline-block" onclick="this.closest('form').submit();">
                                        <nobr><i class="fa fa-fw fa-play" style="color:white;"></i> Iniciar OT&nbsp;</nobr>
                                    </div>
                                </form>
                                <a href="/production/materials/request?agid=<?=$so['ag_id']?>" class="btnblue" style="padding:6px 10px;font-size:11px;text-decoration:none" title="Solicitar o cargar bobinas antes de iniciar la OT">
                                    <nobr><i class="fa fa-dolly"></i> Solicitar Bobina</nobr>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php
    echo renderDesignPreviewModal();
    renderStandbyAndLunchModals($activeStandby, $activeInit, $operatorName, '/production/work-orders/new', $activeLunch);

