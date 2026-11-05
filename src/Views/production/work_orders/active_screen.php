<?php
    ?>
    <?php if (isset($_GET['closed'])): ?>
        <?php if (!empty($_GET['noprod'])): ?>
            <div style="background:#ECFDF5;border:1px solid #A7F3D0;border-left:5px solid #059669;padding:12px 18px;border-radius:6px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
                <div style="font-size:14px;font-weight:bold;color:#065F46;display:flex;align-items:center;gap:7px">
                    <i class="fa fa-check-circle" style="font-size:16px;color:#059669"></i> Orden de trabajo terminada exitosamente
                </div>
                <div style="font-size:12.5px;color:#047857;margin-top:4px">
                    La OT no registraba producción iniciada y fue terminada directamente por el operador sin requerir autorización de supervisor.
                </div>
            </div>
        <?php else: ?>
            <div style="background:#ECFDF5;border:1px solid #A7F3D0;border-left:5px solid #059669;padding:12px 18px;border-radius:6px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
                <div style="font-size:14px;font-weight:bold;color:#065F46;display:flex;align-items:center;gap:7px">
                    <i class="fa fa-check-circle" style="font-size:16px;color:#059669"></i> Orden de trabajo finalizada y cerrada con autorización de supervisor.
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($isRebobinadora): ?>
        <!-- ============================================================== -->
        <!-- DISEÑO ESPECÍFICO REBOBINADORA: En curso                       -->
        <!-- ============================================================== -->
        <div style="font-size:20px;margin-bottom:10px"><b>Ordenes de trabajo</b></div>
        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
        <tr>
            <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                    <div>
                        <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
                        <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                        <b>En cursos</b> (OTs actualmente abiertas en máquina <b><?=htmlspecialchars((string)($activeInit['equipo_name'] ?? 'Rebobinadora'))?></b>)
                    </div>
                    <div>
                        <a href="/production/materials/request" class="btngreen" style="padding:5px 12px;font-size:12px;text-decoration:none;border-radius:4px;display:inline-flex;align-items:center;gap:6px" title="Solicitar o cargar bobinas a la máquina">
                            <i class="fa fa-dolly"></i> Solicitar Bobinas
                        </a>
                    </div>
                </div>
            </td>
        </tr>
        </table>

        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
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
            <?php if (empty($activeList)): ?>
                <tr><td colspan="9" class="tdnrm" style="text-align:center;padding:25px;color:#999">No hay órdenes en curso activas actualmente.</td></tr>
            <?php else: ?>
                <?php foreach ($activeList as $row):
                    $imgHash = trim((string)($row['fab_design_imagehash'] ?? $row['item_img_hash'] ?? ''));
                    $hasImg = ($imgHash !== '' && $imgHash !== 'dummy');
                    $imgUrl = $hasImg ? ('/production/design-image?folder=docs.order&hash=' . rawurlencode($imgHash)) : '';
                    $designName = (string)($row['fab_design_name'] ?? '');
                    $w = (int)($row['fab_med_width'] ?? 0);
                    $h = (int)($row['fab_med_height'] ?? 0);
                    $fuelle = (int)($row['fab_med_fuelle'] ?? 0);
                    $medidasStr = $w > 0 ? ("{$w}x{$h}" . ($fuelle > 0 ? " + {$fuelle}" : "") . " cm") : ((float)($row['req_operador_tela_width'] ?? 0) > 0 ? (float)$row['req_operador_tela_width'] . ' cm' : '-');
                    $mat = !empty($row['fab_type']) ? (string)$row['fab_type'] : 'TNT';
                    $actividad = !empty($row['rebo_distribucion']) && $row['rebo_distribucion'] !== '-' ? $row['rebo_distribucion'] : (!empty($row['req_rebo_type']) ? ('Corte ' . ucfirst((string)$row['req_rebo_type'])) : 'Corte / Rebobinado');
                    $metros = (float)($row['ag_amount'] ?? 0);
                ?>
                    <tr onmouseover="this.style.backgroundColor='#F0F7F6'" onmouseout="this.style.backgroundColor='transparent'">
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$row['req_number'])?></b></td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$row['prd_number'])?></b></td>
                        <td class="tdnrm">
                            <b><?=htmlspecialchars((string)$row['cust_name'])?></b>
                            <?php if (!empty($row['item_title'])): ?>
                                <div style="font-size:11.5px;color:#64748B"><?=htmlspecialchars((string)$row['item_title'])?></div>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm" align="center">
                            <?php if ($hasImg): ?>
                                <div style="display:inline-flex;align-items:center;gap:6px;cursor:pointer" onclick="openDesignModal('<?=htmlspecialchars($imgUrl)?>', '<?=htmlspecialchars((string)$row['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$row['cust_name']))?>', '<?=htmlspecialchars(addslashes($designName))?>', '<?=htmlspecialchars($medidasStr)?>')" title="Click para ver foto del diseño">
                                    <img src="<?=htmlspecialchars($imgUrl)?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #CBD5E1;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
                                    <i class="fa fa-search-plus" style="color:#0284C7;font-size:12px"></i>
                                </div>
                            <?php else: ?>
                                <span style="color:#94A3B8;font-size:12px"><i>Sin foto</i></span>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm" align="center">
                            <span style="background:#F1F5F9;color:#334155;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600"><?=htmlspecialchars($mat)?></span>
                            <div style="font-weight:800;margin-top:5px">Color: <?=htmlspecialchars((string)($row['fabric_color'] ?? '-'))?></div>
                        </td>
                        <td class="tdnrm">
                            <span style="color:#0F766E;font-weight:600;font-size:12.5px"><i class="fa fa-cut"></i> <?=htmlspecialchars($actividad)?></span>
                        </td>
                        <td class="tdnrm"><?=htmlspecialchars($medidasStr)?></td>
                        <td class="tdnrm" align="right"><b><?=number_format($metros, 0, ',', '.')?></b> m</td>
                        <td class="tdnrm" align="center">
                            <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                                <a href="/production/work-orders/operate?agid=<?=$row['ag_id']?>" class="btnblue" style="padding:6px 12px;text-decoration:none">
                                    <i class="fa fa-desktop"></i> Operar
                                </a>
                                <a href="/production/materials/request?agid=<?=$row['ag_id']?>" class="btngreen" style="padding:6px 10px;text-decoration:none;font-size:11px" title="Solicitar o cargar bobinas para esta OT">
                                    <i class="fa fa-dolly"></i> Bobinas
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
        <!-- ============================================================== -->
        <!-- DISEÑO ESPECÍFICO FLEXOGRAFÍA: En curso                       -->
        <!-- ============================================================== -->
        <div style="font-size:20px;margin-bottom:10px"><b>Ordenes de trabajo</b></div>
        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
        <tr>
            <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                    <div>
                        <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
                        <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                        <b>En cursos</b> (OTs actualmente abiertas en máquina <b><?=htmlspecialchars((string)($activeInit['equipo_name'] ?? 'Flexografía'))?></b>)
                    </div>
                    <div>
                        <a href="/production/materials/request" class="btngreen" style="padding:5px 12px;font-size:12px;text-decoration:none;border-radius:4px;display:inline-flex;align-items:center;gap:6px" title="Solicitar o cargar bobinas a la máquina">
                            <i class="fa fa-dolly"></i> Solicitar Bobinas
                        </a>
                    </div>
                </div>
            </td>
        </tr>
        </table>

        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px;overflow-x:auto">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px">
                <div style="font-size:15px;font-weight:bold;color:#115452;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <span><i class="fa fa-fw fa-play-circle"></i> OTs en Curso en esta Máquina</span>
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
                    <form method="get" action="/production/work-orders/active" style="margin:0;display:inline-flex;align-items:center;gap:6px">
                        <label style="font-size:12px;color:#64748B;font-weight:600"><i class="fa fa-calendar-alt"></i> Fecha:</label>
                        <input type="date" name="plan_date" value="<?= !empty($dateFilter) && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateFilter, $mD) ? "{$mD[3]}-{$mD[2]}-{$mD[1]}" : date('Y-m-d') ?>" style="padding:4px 8px;border:1px solid #CBD5E1;border-radius:4px;font-size:12px" onchange="this.form.submit()">
                        <?php if (!empty($dateFilter) && $dateFilter !== date('d.m.Y')): ?>
                            <a href="/production/work-orders/active" class="btn" style="background:#0284C7;color:#fff;padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Hoy</a>
                        <?php endif; ?>
                        <?php if (empty($dateFilter)): ?>
                            <a href="/production/work-orders/active" class="btngreen" style="padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Solo Hoy</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <table border="0" width="100%" cellpadding="8" cellspacing="0" style="min-width:1150px">
            <colgroup>
                <col width="90">
                <col width="85">
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
                    <td class="tdheader" align="right" style="min-width:95px">Programada CC</td>
                    <td class="tdheader" align="right" style="min-width:95px">Producida CC</td>
                    <td class="tdheader" align="right" style="min-width:105px;color:#0284C7">Falta producir</td>
                    <td class="tdheader" align="center">Imagen</td>
                    <td class="tdheader" align="center">Opciones</td>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($activeList)): ?>
                <tr>
                    <td colspan="14" class="tdnrm" style="text-align:center;padding:35px 20px;color:#64748B">
                        <div style="font-size:16px;font-weight:bold;color:#334155;margin-bottom:6px">
                            <i class="fa fa-info-circle" style="color:#0284C7"></i> No hay órdenes en curso <?= !empty($dateFilter) ? 'planificadas para el ' . htmlspecialchars($dateFilter) : 'activas actualmente' ?>.
                        </div>
                        <?php if (!empty($dateFilter)): ?>
                            <div style="font-size:12.5px;color:#64748B;margin-top:6px">
                                Puedes iniciar una orden planificada para hoy en <a href="/production/work-orders/new" style="color:#059669;font-weight:600;text-decoration:underline">Iniciar nueva OT</a>.
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($activeList as $row):
                    $imgHash = trim((string)($row['fab_design_imagehash'] ?? $row['item_img_hash'] ?? ''));
                    $hasImg = ($imgHash !== '' && $imgHash !== 'dummy');
                    $imgUrl = $hasImg ? ('/production/design-image?folder=docs.order&hash=' . rawurlencode($imgHash)) : '';
                    
                    $prodSku = trim((string)($row['item_number_prod'] ?? ''));
                    $prodTitle = trim((string)($row['item_title'] ?? ''));
                    $designName = trim((string)($row['fab_design_name'] ?? ''));
                    $fullDesign = ($prodSku !== '' && $prodTitle !== '') ? "{$prodSku} | {$prodTitle}" : ($prodTitle ?: ($prodSku ?: ($designName ?: '-')));
                    
                    $w = (int)($row['fab_med_width'] ?? 0);
                    $h = (int)($row['fab_med_height'] ?? 0);
                    $fuelle = (int)($row['fab_med_fuelle'] ?? 0);
                    $medidasStr = $w > 0 ? ("{$w} x {$h}" . ($fuelle > 0 ? " + {$fuelle}" : "") . " cm") : '-';
                    $mat = !empty($row['fab_type']) ? (string)$row['fab_type'] : 'TNT';
                    
                    $colores = [];
                    for ($i = 1; $i <= 6; $i++) {
                        $c = trim((string)($row["fab_print_colordesc_{$i}"] ?? ''));
                        if ($c !== '') {
                            $colores[] = $c;
                        }
                    }
                    
                    $z = (int)($row['flexo_z'] ?? 0);
                    $ubicacion = trim((string)($row['req_infoaddprd_cliche_ubicacion'] ?? ''));
                    $codCliche = trim((string)($row['req_infoaddprd_cliche_codigo'] ?? ''));
                    
                    $metros = (float)($row['flexo_metros'] ?? 0);
                    $cantBolsas = (float)($row['ag_amount'] ?? 0);
                    $producidas = (float)($row['total_produced'] ?? 0);
                    $producidasMts = (float)($row['total_produced_meters'] ?? 0);
                    $totalCcBolsas = (float)($row['total_cc_units'] ?? $producidas);
                    $totalCcMts = (float)($row['total_cc_meters'] ?? $producidasMts);
                    $progCcBolsas = (float)($row['plan_cc_units'] ?? $cantBolsas);
                    $progCcMts = (float)($row['plan_cc_meters'] ?? $metros);
                    $faltaBolsas = max(0.0, $progCcBolsas - $totalCcBolsas);
                    $faltaMts = max(0.0, $progCcMts - $totalCcMts);
                ?>
                    <tr onmouseover="this.style.backgroundColor='#F0F7F6'" onmouseout="this.style.backgroundColor='transparent'">
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$row['req_number'])?></b></td>
                        <td class="tdnrm">
                            <b><?=htmlspecialchars((string)$row['prd_number'])?></b>
                            <?php if (!empty($row['ag_date'])): ?>
                                <div style="font-size:10.5px;color:#0284C7;font-weight:600;white-space:nowrap;margin-top:2px">
                                    <i class="fa fa-calendar-day"></i> <?=htmlspecialchars((string)$row['ag_date'])?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$row['cust_name'])?></b></td>
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
                            <div style="font-weight:700;color:#0F172A;font-size:12.5px"><?=number_format($progCcMts, 0, ',', '.')?> m</div>
                            <div style="font-size:11px;color:#64748B;font-weight:600"><?=number_format($progCcBolsas, 0, ',', '.')?> un.</div>
                        </td>
                        <td class="tdnrm" align="right" style="white-space:nowrap">
                            <div style="font-weight:700;color:#0F766E;font-size:12.5px"><?=number_format($totalCcMts, 0, ',', '.')?> m</div>
                            <div style="font-size:11px;color:#0F766E;font-weight:600"><?=number_format($totalCcBolsas, 0, ',', '.')?> un.</div>
                        </td>
                        <td class="tdnrm" align="right" style="white-space:nowrap;background-color:rgba(2, 132, 199, 0.05)">
                            <div style="font-weight:800;color:#0369A1;font-size:12.5px"><?=number_format($faltaMts, 0, ',', '.')?> m</div>
                            <div style="font-size:11px;color:#0284C7;font-weight:700"><?=number_format($faltaBolsas, 0, ',', '.')?> un.</div>
                        </td>
                        <td class="tdnrm" align="center">
                            <?php if ($hasImg): ?>
                                <div style="display:inline-flex;align-items:center;gap:4px;cursor:pointer" onclick="openDesignModal('<?=htmlspecialchars($imgUrl)?>', '<?=htmlspecialchars((string)$row['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$row['cust_name']))?>', '<?=htmlspecialchars(addslashes($fullDesign))?>', '<?=htmlspecialchars($medidasStr)?>')" title="Click para ver foto del diseño">
                                    <img src="<?=htmlspecialchars($imgUrl)?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #CBD5E1;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
                                    <i class="fa fa-search-plus" style="color:#0284C7;font-size:11px"></i>
                                </div>
                            <?php else: ?>
                                <span style="color:#94A3B8;font-size:12px"><i>Sin foto</i></span>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm" align="center">
                            <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                                <a href="/production/work-orders/operate?agid=<?=$row['ag_id']?>" class="btnblue" style="padding:6px 12px;text-decoration:none">
                                    <i class="fa fa-desktop"></i> Operar
                                </a>
                                <a href="/production/materials/request?agid=<?=$row['ag_id']?>" class="btngreen" style="padding:6px 10px;text-decoration:none;font-size:11px" title="Solicitar o cargar bobinas para esta OT">
                                    <i class="fa fa-dolly"></i> Bobinas
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
        <!-- ============================================================== -->
        <!-- DISEÑO ESPECÍFICO SERIGRAFÍA: En curso                         -->
        <!-- ============================================================== -->
        <div style="font-size:20px;margin-bottom:10px"><b>Ordenes de trabajo</b></div>
        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
        <tr>
            <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                    <div>
                        <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
                        <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                        <b>En curso</b> en máquina <b><?=htmlspecialchars((string)($activeInit['equipo_name'] ?? 'Serigrafía'))?></b>
                    </div>
                    <div>
                        <a href="/production/materials/request" class="btngreen" style="padding:5px 12px;font-size:12px;text-decoration:none;border-radius:4px;display:inline-flex;align-items:center;gap:6px" title="Solicitar o cargar bobinas a la máquina">
                            <i class="fa fa-dolly"></i> Solicitar Bobinas
                        </a>
                    </div>
                </div>
            </td>
        </tr>
        </table>

        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
                <div style="font-size:15px;font-weight:bold;color:#115452">
                    <i class="fa fa-fw fa-paint-brush"></i> OTs En Curso para Serigrafía
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

                <!-- Filtro rápido por fecha -->
                <div style="display:flex;align-items:center;gap:8px">
                    <form method="get" action="/production/work-orders/active" style="margin:0;display:inline-flex;align-items:center;gap:6px">
                        <label style="font-size:12px;color:#64748B;font-weight:600"><i class="fa fa-calendar-alt"></i> Fecha:</label>
                        <input type="date" name="plan_date" value="<?= !empty($dateFilter) && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateFilter, $mD) ? "{$mD[3]}-{$mD[2]}-{$mD[1]}" : date('Y-m-d') ?>" style="padding:4px 8px;border:1px solid #CBD5E1;border-radius:4px;font-size:12px" onchange="this.form.submit()">
                        <?php if (!empty($dateFilter) && $dateFilter !== date('d.m.Y')): ?>
                            <a href="/production/work-orders/active" class="btn" style="background:#0284C7;color:#fff;padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Hoy</a>
                        <?php endif; ?>
                        <?php if (empty($dateFilter)): ?>
                            <a href="/production/work-orders/active" class="btngreen" style="padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Solo Hoy</a>
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
            <?php if (empty($activeList)): ?>
                <tr>
                    <td colspan="11" class="tdnrm" style="text-align:center;padding:35px 20px;color:#64748B">
                        <i class="fa fa-calendar-times" style="font-size:26px;color:#94A3B8;margin-bottom:8px;display:block"></i>
                        No hay órdenes en curso para esta máquina en la fecha <b><?=htmlspecialchars($dateFilter ?: date('d.m.Y'))?></b>.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($activeList as $row):
                    $imgHash = trim((string)($row['fab_design_imagehash'] ?? $row['item_img_hash'] ?? ''));
                    $hasImg = ($imgHash !== '' && $imgHash !== 'dummy');
                    $imgUrl = $hasImg ? ('/production/design-image?folder=docs.order&hash=' . rawurlencode($imgHash)) : '';
                    
                    $prodSku = trim((string)($row['item_number_prod'] ?? ''));
                    $prodTitle = trim((string)($row['item_title'] ?? ''));
                    $designName = trim((string)($row['fab_design_name'] ?? ''));
                    $fullDesign = ($prodSku !== '' && $prodTitle !== '') ? "{$prodSku} | {$prodTitle}" : ($prodTitle ?: ($prodSku ?: ($designName ?: '-')));
                    
                    $w = (int)($row['fab_med_width'] ?? 0);
                    $h = (int)($row['fab_med_height'] ?? 0);
                    $fuelle = (int)($row['fab_med_fuelle'] ?? 0);
                    $medidasStr = $w > 0 ? ("{$w} x {$h}" . ($fuelle > 0 ? " + {$fuelle}" : "") . " cm") : '-';
                    $mat = !empty($row['fab_type']) ? (string)$row['fab_type'] : 'TNT';
                    
                    $colores = [];
                    for ($i = 1; $i <= 6; $i++) {
                        $c = trim((string)($row["fab_print_colordesc_{$i}"] ?? ''));
                        if ($c !== '') {
                            $colores[] = $c;
                        }
                    }
                    
                    $cantBolsas = (float)($row['ag_amount'] ?? 0);
                    $contador = (float)($row['seri_contador'] ?? 0);
                ?>
                    <tr onmouseover="this.style.backgroundColor='#F0F7F6'" onmouseout="this.style.backgroundColor='transparent'">
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$row['req_number'])?></b></td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$row['prd_number'])?></b></td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$row['cust_name'])?></b></td>
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
                                <div style="display:inline-flex;align-items:center;gap:4px;cursor:pointer" onclick="openDesignModal('<?=htmlspecialchars($imgUrl)?>', '<?=htmlspecialchars((string)$row['prd_number'])?>', '<?=htmlspecialchars(addslashes((string)$row['cust_name']))?>', '<?=htmlspecialchars(addslashes($fullDesign))?>', '<?=htmlspecialchars($medidasStr)?>')" title="Click para ver foto del diseño">
                                    <img src="<?=htmlspecialchars($imgUrl)?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #CBD5E1;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.1)">
                                    <i class="fa fa-search-plus" style="color:#0284C7;font-size:11px"></i>
                                </div>
                            <?php else: ?>
                                <span style="color:#94A3B8;font-size:12px"><i>Sin foto</i></span>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm" align="center">
                            <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                                <a href="/production/work-orders/operate?agid=<?=$row['ag_id']?>" class="btnblue" style="padding:6px 12px;text-decoration:none">
                                    <i class="fa fa-desktop"></i> Operar
                                </a>
                                <a href="/production/materials/request?agid=<?=$row['ag_id']?>" class="btngreen" style="padding:6px 10px;text-decoration:none;font-size:11px" title="Solicitar o cargar bobinas para esta OT">
                                    <i class="fa fa-dolly"></i> Bobinas
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
        <!-- ============================================================== -->
        <!-- FORMATO ESTÁNDAR / LEGACY DE UNIBAG PARA OTRAS MÁQUINAS         -->
        <!-- ============================================================== -->
        <font style="font-size:20px"><b>En cursos</b></font>
        <div style="clear:both;height:10px"></div>
        <table border="0" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                    <div>
                        <i class="fa fa-fw fa-laptop-code" style="font-size:14px;"></i> Ordenes de trabajo
                        <i class="fa fa-fw fa-chevron-right" style="font-size:14px;"></i>
                        En cursos
                    </div>
                    <div>
                        <a href="/production/materials/request" class="btngreen" style="padding:5px 12px;font-size:12px;text-decoration:none;border-radius:4px;display:inline-flex;align-items:center;gap:6px" title="Solicitar o cargar bobinas a la máquina">
                            <i class="fa fa-dolly"></i> Solicitar Bobinas
                        </a>
                    </div>
                </div>
            </td>
        </tr>
        </table>
        <div style="clear:both;height:10px"></div>

        <?php if (!empty($activeInit['id'])): ?>
        <table border="0" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
                <table border="0" width="100%" cellpadding="6" cellspacing="0">
                <colgroup>
                    <col width="120">
                    <col>
                </colgroup>
                <tr>
                    <td class="tdleft">Fecha inicio</td>
                    <td class="tdnrm"><?=date("d.m.Y", (int)($activeInit["win_crtdat"] ?? time()))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Hora inicio</td>
                    <td class="tdnrm"><?=date("H:i:s", (int)($activeInit["win_crtdat"] ?? time()))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Tipo</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($activeInit["type_ant_title"] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Máquina</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)($activeInit["equipo_name"] ?? '-'))?></td>
                </tr>
                <tr>
                    <td class="tdleft">Acciones</td>
                    <td class="tdnrm">
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                            <?php if (!empty($activeLunch)): ?>
                                <button type="button" class="btnorange" style="background:#D97706;padding:6px 14px;cursor:pointer;border:none;font-weight:bold" onclick="openLunchModal()" title="Colación activa">
                                    <i class="fa fa-coffee"></i> EN COLACIÓN (desde <?=date('H:i', strtotime($activeLunch['start_time']))?>) &mdash; Terminar
                                </button>
                            <?php else: ?>
                                <button type="button" class="btnblue" style="background:#0284C7;padding:6px 14px;cursor:pointer;border:none" onclick="openLunchModal()" title="Registrar horario de colación en tiempo real">
                                    <i class="fa fa-coffee"></i> Colación
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                </table>
                <div style="clear:both;height:10px"></div>
            </td>
        </tr>
        </table>
        <div style="clear:both;height:10px"></div>
        <?php endif; ?>

        <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px;overflow-x:auto">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px">
                <div style="font-size:15px;font-weight:bold;color:#115452;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <span><i class="fa fa-fw fa-tasks"></i> OTs En Curso para esta Máquina</span>
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
                    <form method="get" action="/production/work-orders/active" style="margin:0;display:inline-flex;align-items:center;gap:6px">
                        <label style="font-size:12px;color:#64748B;font-weight:600"><i class="fa fa-calendar-alt"></i> Fecha:</label>
                        <input type="date" name="plan_date" value="<?= !empty($dateFilter) && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateFilter, $mD) ? "{$mD[3]}-{$mD[2]}-{$mD[1]}" : date('Y-m-d') ?>" style="padding:4px 8px;border:1px solid #CBD5E1;border-radius:4px;font-size:12px" onchange="this.form.submit()">
                        <?php if (!empty($dateFilter) && $dateFilter !== date('d.m.Y')): ?>
                            <a href="/production/work-orders/active" class="btn" style="background:#0284C7;color:#fff;padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Hoy</a>
                        <?php endif; ?>
                        <?php if (empty($dateFilter)): ?>
                            <a href="/production/work-orders/active" class="btngreen" style="padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Solo Hoy</a>
                        <?php else: ?>
                            <a href="/production/work-orders/active?plan_date=all" class="btn" style="background:#64748B;color:#fff;padding:4px 10px;font-size:11.5px;border-radius:4px;text-decoration:none">Ver Todas</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <?php if (empty($activeList)): ?>
                <b class="msg_save_err" style="color:#C00"><i class="fa fa-fw fa-exclamation-triangle"></i> No existen OT's en curso para esta fecha (<?=htmlspecialchars($dateFilter ?: date('d.m.Y'))?>).</b>
            <?php elseif ($tableProcess !== ''): ?>
                <?php renderMachineWorkOrderTable($activeList, 'active', $tableProcess); ?>
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
                <?php foreach ($activeList as $row):
                    $prodAmt = (float)($row["total_produced"] ?? 0);
                    $itemAmt = (float)($row["item_amount"] ?? $row["ag_amount"]);
                    $saldo = max(0, $itemAmt - $prodAmt);
                ?>
                <tr onmouseover="this.style.backgroundColor='#F0F7F6'" onmouseout="this.style.backgroundColor='transparent'">
                    <td class="tdleft"><?=htmlspecialchars((string)$row["prd_number"])?>&nbsp;</td>
                    <td class="tdnrm"><nobr><?=htmlspecialchars((string)$row["req_number"])?></nobr>&nbsp;</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$row["cust_name"])?>&nbsp;</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$row["item_number_prod"])?>&nbsp;</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$row["item_title"])?>&nbsp;</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$row["fab_printtype"])?>&nbsp;</td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$row["fab_type"])?>&nbsp;</td>
                    <td class="tdnrm" align="center"><?=number_format((float)$row["ag_amount"], 0, ',', '.')?>&nbsp;</td>
                    <td class="tdnrm" align="center"><?=number_format($prodAmt, 0, ',', '.')?></td>
                    <td class="tdnrm" align="center"><?=number_format($saldo, 0, ',', '.')?></td>
                    <td class="tdnrm" align="center">
                        <b class="msg_save_ok" style="color:#00A85A">Activado</b>
                    </td>
                    <td class="tdnrm" align="center">
                        <div style="display:flex;gap:6px;justify-content:center;align-items:center;flex-wrap:wrap">
                            <div class="btngreen" style="cursor:pointer;display:inline-block" onclick="location.href='/production/work-orders/operate?agid=<?=$row['ag_id']?>'">
                                <nobr><i class="fa fa-fw fa-edit" style="color:white;"></i> Ingresar a OT&nbsp;</nobr>
                            </div>
                            <a href="/production/materials/request?agid=<?=$row['ag_id']?>" class="btnblue" style="padding:6px 10px;text-decoration:none;font-size:11px" title="Solicitar o cargar bobinas para esta OT">
                                <nobr><i class="fa fa-dolly"></i> Bobinas</nobr>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php
    echo renderDesignPreviewModal();
    renderStandbyAndLunchModals($activeStandby, $activeInit, $operatorName, '/production/work-orders/active', $activeLunch);

