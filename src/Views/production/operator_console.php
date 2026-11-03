<?php
/**
 * Vista Principal · Consola Operativa de Orden de Trabajo (/production/work-orders/operate)
 */
echo renderView('production/partials/operator_flash_banners', ['agenda' => $agenda]);
?>
    <!-- Encabezado de Navegación / Breadcrumb idéntico a editxid.php -->
    <div style="font-size:20px;margin-bottom:10px"><b>En curso</b></div>

    <?php if (isset($_GET['alis_started'])): ?>
        <div style="background:#E8F5E9;color:#2E7D32;padding:12px 18px;border-radius:4px;border:1px solid #A5D6A7;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle"></i> <b>Alistamiento iniciado exitosamente como evento de la OT.</b> El evento figura en el registro inferior como &quot;En curso&quot;. Complete la preparación de la máquina y luego seleccione todas las confirmaciones para terminarlo.
        </div>
    <?php elseif (isset($_GET['alis_finished'])): ?>
        <div style="background:#E8F5E9;color:#2E7D32;padding:12px 18px;border-radius:4px;border:1px solid #A5D6A7;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle"></i> <b>Alistamiento y confirmaciones de máquina completados.</b> El evento de alistamiento ha sido finalizado. Proceda a la aprobación de partida con las credenciales del supervisor.
        </div>
    <?php elseif (isset($_GET['partida_approved'])): ?>
        <div style="background:#E8F5E9;color:#2E7D32;padding:12px 18px;border-radius:4px;border:1px solid #A5D6A7;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle"></i> <b>¡Partida aprobada por Supervisor!</b> La orden de trabajo se encuentra 100% habilitada para registrar producción.
        </div>
    <?php elseif (isset($_GET['saved_anilox'])): ?>
        <div style="background:#E8F5E9;color:#2E7D32;padding:12px 18px;border-radius:4px;border:1px solid #A5D6A7;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle"></i> <b>Conformación de Anilox y tintas guardada exitosamente.</b>
        </div>
    <?php elseif (isset($_GET['prod_started'])): ?>
        <div style="background:#E8F5E9;color:#1B5E20;padding:12px 18px;border-radius:4px;border:1px solid #81C784;margin-bottom:12px;font-size:13px">
            <i class="fa fa-play-circle" style="font-size:15px"></i> <b>▶️ Tiraje de Producción iniciado exitosamente.</b> La máquina se encuentra ahora en producción activa. Al terminar la tirada o cambiar de turno, pulse &quot;Finalizar Producción&quot; para registrar las bolsas/metros y mermas reales.
        </div>
    <?php elseif (isset($_GET['prod_ended'])): ?>
        <div style="background:#E8F5E9;color:#1B5E20;padding:12px 18px;border-radius:4px;border:1px solid #81C784;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle" style="font-size:15px"></i> <b>⏹️ Tiraje de Producción finalizado correctamente.</b> Se han guardado las unidades, metros y registro de mermas/defectos.
        </div>
    <?php elseif (isset($_GET['saved_prod'])): ?>
        <div style="background:#E8F5E9;color:#1B5E20;padding:12px 18px;border-radius:4px;border:1px solid #81C784;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle"></i> <b>Registro de producción actualizado exitosamente.</b>
        </div>
    <?php elseif (isset($_GET['saved_material'])): ?>
        <div style="background:#ECFDF5;color:#065F46;padding:12px 18px;border-radius:4px;border:1px solid #6EE7B7;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle"></i> <b>Bobinas / Insumos solicitados exitosamente desde bodega para la OT.</b> Quedan disponibles al pie de máquina.
        </div>
    <?php elseif (isset($_GET['mounted'])): ?>
        <div style="background:#ECFDF5;color:#065F46;padding:12px 18px;border-radius:4px;border:1px solid #6EE7B7;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle"></i> <b>⚙️ Bobina montada exitosamente en la máquina (desbobinador).</b> Ya puede procesarse en producción y registrar su consumo.
        </div>
    <?php elseif (isset($_GET['unmounted'])): ?>
        <div style="background:#FFFBEB;color:#92400E;padding:12px 18px;border-radius:4px;border:1px solid #FCD34D;margin-bottom:12px;font-size:13px">
            <i class="fa fa-pause-circle"></i> <b>Bobina desmontada de la máquina.</b> Permanece asignada a la OT para posterior uso.
        </div>
    <?php elseif (isset($_GET['unassigned'])): ?>
        <div style="background:#EFF6FF;color:#1E40AF;padding:12px 18px;border-radius:4px;border:1px solid #BFDBFE;margin-bottom:12px;font-size:13px">
            <i class="fa fa-undo"></i> <b>Bobina devuelta a bodega exitosamente.</b> Ha sido liberada de esta OT.
        </div>
    <?php elseif (isset($_GET['saved_consumption'])): ?>
        <div style="background:#ECFDF5;color:#065F46;padding:12px 18px;border-radius:4px;border:1px solid #6EE7B7;margin-bottom:12px;font-size:13px">
            <i class="fa fa-check-circle"></i> <b>Consumo y retazo de bobina registrados exitosamente.</b> Trazabilidad de materia prima actualizada.
        </div>
    <?php elseif (isset($_GET['event_deleted'])): ?>
        <div style="background:#ECFDF5;color:#065F46;padding:12px 18px;border-radius:4px;border:1px solid #6EE7B7;margin-bottom:12px;font-size:13px">
            <i class="fa fa-trash"></i> <b>Registro de producción #<?= (int)$_GET['event_deleted'] ?> eliminado exitosamente.</b>
            <?php if (!empty($_GET['authorizer'])): ?>
                (Autorizado por: <b><?= htmlspecialchars((string)$_GET['authorizer']) ?></b>)
            <?php endif; ?>
        </div>
    <?php elseif (isset($_GET['error'])): ?>
        <div style="background:#FFEBEE;color:#C62828;padding:12px 18px;border-radius:4px;border:1px solid #EF9A9A;margin-bottom:12px;font-size:13px">
            <i class="fa fa-exclamation-circle"></i> <?=htmlspecialchars((string)$_GET['error'])?>
        </div>
    <?php endif; ?>

    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px">
    <tr>
        <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td align="left">
                    <a href="/production/work-orders/active" style="color:inherit;text-decoration:none;font-weight:bold" title="Ver órdenes de trabajo">
                        <i class="fa fa-fw fa-laptop-code" style="font-size:14px;"></i> Ordenes de trabajo
                    </a>
                    <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                    <b>En curso</b>: OT #<?=htmlspecialchars((string)$agenda['prd_number'])?> | Máquina: <b><?=htmlspecialchars((string)($machineInit['equipo_name'] ?? '-'))?></b>
                </td>
                <td align="right">
                    <div style="display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end">
                        <?php if ($activeLunch !== null): ?>
                            <button type="button" class="btnorange" style="background:#D97706;padding:7px 14px;cursor:pointer;border:none;border-radius:4px;font-size:13px;font-weight:bold;display:inline-flex;align-items:center;gap:6px" onclick="openLunchModal()" title="Colación activa">
                                <i class="fa fa-coffee"></i> EN COLACIÓN (desde <?=date('H:i', strtotime($activeLunch['start_time']))?>) &mdash; Terminar
                            </button>
                        <?php else: ?>
                            <button type="button" class="btnblue" style="background:#0284C7;padding:7px 14px;cursor:pointer;border:none;border-radius:4px;font-size:13px;font-weight:bold;display:inline-flex;align-items:center;gap:6px" onclick="openLunchModal()" title="Registrar horario de colación en tiempo real">
                                <i class="fa fa-coffee"></i> Colación
                            </button>
                        <?php endif; ?>
                        <div class="btngreen" style="display:inline-block" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=terminarot'">
                            <i class="fa fa-fw fa-check" style="color:white;"></i> Terminar OT&nbsp;
                        </div>
                        <a href="/logout" class="btnred" style="padding:7px 12px;background:#DC2626;color:#FFF;border-radius:4px;text-decoration:none;font-size:12px;font-weight:bold;display:inline-flex;align-items:center;gap:5px" onclick="return confirm('¿Desea cerrar sesión del sistema?');" title="Cerrar sesión / Salir">
                            <i class="fa fa-sign-out-alt"></i> Salir
                        </a>
                    </div>
                </td>
            </tr>
            </table>
        </td>
    </tr>
    </table>

    <?php if ($isSeriPulpo && ($prevFrente > 0 || $prevDorso > 0 || $prevFinished > 0)): ?>
        <div style="background:#FFFBEB;border:1px solid #FCD34D;border-left:5px solid #D97706;padding:12px 18px;border-radius:4px;margin-bottom:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td align="left">
                    <div style="font-size:14px;font-weight:bold;color:#92400E;display:flex;align-items:center;gap:7px">
                        <i class="fa fa-layer-group" style="font-size:16px;color:#D97706"></i>
                        Estado Acumulado Serigrafía Pulpo en esta OT
                    </div>
                    <div style="font-size:12.5px;color:#78350F;margin-top:4px">
                        Pasadas registradas: <b><?=number_format($prevFrente, 0, ',', '.')?></b> Frente &nbsp;|&nbsp; 
                        <b><?=number_format($prevDorso, 0, ',', '.')?></b> Dorso &nbsp;→&nbsp; 
                        <b><?=number_format($prevFinished, 0, ',', '.')?></b> bolsas terminadas completas
                    </div>
                </td>
                <td align="right">
                    <?php if ($prevSurplusFrente > 0): ?>
                        <span style="background:#FEF3C7;color:#92400E;border:1px solid #F59E0B;padding:6px 14px;border-radius:4px;font-weight:bold;font-size:13px;display:inline-flex;align-items:center;gap:6px">
                            <i class="fa fa-exclamation-circle" style="color:#D97706"></i>
                            Quedan <?=number_format($prevSurplusFrente, 0, ',', '.')?> bolsas impresas por el FRENTE (pendientes de dorso)
                        </span>
                    <?php elseif ($prevSurplusDorso > 0): ?>
                        <span style="background:#FEF3C7;color:#92400E;border:1px solid #F59E0B;padding:6px 14px;border-radius:4px;font-weight:bold;font-size:13px;display:inline-flex;align-items:center;gap:6px">
                            <i class="fa fa-exclamation-circle" style="color:#D97706"></i>
                            Quedan <?=number_format($prevSurplusDorso, 0, ',', '.')?> bolsas impresas por el DORSO (pendientes de frente)
                        </span>
                    <?php else: ?>
                        <span style="background:#ECFDF5;color:#065F46;border:1px solid #A7F3D0;padding:6px 14px;border-radius:4px;font-weight:bold;font-size:12px;display:inline-flex;align-items:center;gap:6px">
                            <i class="fa fa-check-circle" style="color:#059669"></i>
                            Frente y Dorso al día (0 pendientes entre caras)
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($openProdEvent !== null): ?>
        <div style="background:linear-gradient(135deg, #059669 0%, #10B981 100%);color:white;padding:12px 18px;border-radius:6px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1)">
            <div style="display:flex;align-items:center;gap:12px">
                <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#A7F3D0;box-shadow:0 0 0 4px rgba(167,243,208,0.4)"></span>
                <div>
                    <div style="font-size:15px;font-weight:bold;letter-spacing:0.5px">MÁQUINA EN PRODUCCIÓN ACTIVA</div>
                    <div style="font-size:12.5px;opacity:0.95">
                        Tiraje #<?=$openProdEvent['id']?> iniciado a las <?=date('H:i:s', (int)$openProdEvent['evt_crtdat'])?> (hace <?=max(0, round((time() - (int)$openProdEvent['evt_crtdat']) / 60))?> min)
                    </div>
                </div>
            </div>
            <div>
                <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=production&refid=<?=$openProdEvent['id']?>&submode=end" class="btnorange" style="font-size:13.5px;padding:9px 20px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-weight:bold;box-shadow:0 2px 4px rgba(0,0,0,0.2);border-radius:8px">
                    <i class="fa fa-stop"></i> Finalizar
                </a>
            </div>
        </div>
    <?php endif; ?>
 
    <?php if ($isFlexo && !empty($printedRollsSummary['rolls_count']) && $printedRollsSummary['rolls_count'] > 0): ?>
        <div style="background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:6px;padding:14px 18px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;color:#166534;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
            <div>
                <div style="font-size:14px;font-weight:bold;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-tag" style="color:#059669;font-size:16px"></i> Bobinas Impresas Producidas en esta OT:
                    <b style="color:#15803D;font-size:16px"><?=number_format($printedRollsSummary['total_weight_kg'], 2, ',', '.')?> kg</b>
                    <span style="font-size:13px;color:#047857">(<?=number_format($printedRollsSummary['total_meters'], 0, ',', '.')?> metros &bull; <?=$printedRollsSummary['rolls_count']?> bobina<?=$printedRollsSummary['rolls_count'] > 1 ? 's' : ''?>)</span>
                </div>
                <div style="font-size:12px;color:#166534;margin-top:4px">
                    Rollos emitidos: <b><?=implode(', ', array_map(fn($r) => $r['roll_code'] . ' (' . number_format($r['weight_kg'], 1, ',', '.') . ' kg)', $printedRollsSummary['rolls_list']))?></b>.
                    Estos valores se sumarán y pasarán automáticamente al cierre de producción.
                </div>
            </div>
            <div style="display:flex;gap:8px">
                <?php if ($openProdEvent !== null): ?>
                    <a href="/production/work-orders/operate?agid=<?=$agId?>&mode=production&refid=<?=$openProdEvent['id']?>&submode=end" class="btngreen" style="font-size:12px;padding:6px 14px;text-decoration:none;border-radius:8px">
                        <i class="fa fa-flag-checkered"></i> Finalizar
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <style>
    .unibag-collapse-header {
        background-color: #165552;
        color: #FFFFFF;
        padding: 8px 14px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        user-select: none;
        font-weight: bold;
        font-size: 13px;
        transition: background-color 0.15s ease;
    }
    .unibag-collapse-header:hover {
        background-color: #124341;
    }
    .unibag-collapse-btn {
        font-size: 11px;
        font-weight: normal;
        opacity: 0.9;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(255,255,255,0.15);
        padding: 3px 8px;
        border-radius: 3px;
    }
    .unibag-collapse-header:hover .unibag-collapse-btn {
        background: rgba(255,255,255,0.25);
    }
    </style>
    <script>
    function toggleCollapseSection(secId, iconId) {
        var sec = document.getElementById(secId);
        var icon = document.getElementById(iconId);
        var lblId = iconId.replace('icon_', 'lbl_');
        var lbl = document.getElementById(lblId);
        if (!sec) return;

        var isHidden = (sec.style.display === 'none');
        if (isHidden) {
            sec.style.display = '';
            if (icon) {
                icon.className = 'fa fa-chevron-up';
            }
            if (lbl) {
                lbl.textContent = 'Minimizar';
            }
            try { localStorage.setItem('unibag_collapse_' + secId, '0'); } catch(e){}
        } else {
            sec.style.display = 'none';
            if (icon) {
                icon.className = 'fa fa-chevron-down';
            }
            if (lbl) {
                lbl.textContent = 'Expandir';
            }
            try { localStorage.setItem('unibag_collapse_' + secId, '1'); } catch(e){}
        }
    }

    function initCollapsibleSections() {
        ['sec_info_cliente', 'sec_info_fab', 'sec_info_anilox', 'sec_info_fab_pulpo'].forEach(function(secId) {
            try {
                var saved = localStorage.getItem('unibag_collapse_' + secId);
                if (saved === '1') {
                    var sec = document.getElementById(secId);
                    var iconId = secId.replace('sec_', 'icon_');
                    var lblId = secId.replace('sec_', 'lbl_');
                    var icon = document.getElementById(iconId);
                    var lbl = document.getElementById(lblId);
                    if (sec) sec.style.display = 'none';
                    if (icon) icon.className = 'fa fa-chevron-down';
                    if (lbl) lbl.textContent = 'Expandir';
                }
            } catch(e){}
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCollapsibleSections);
    } else {
        initCollapsibleSections();
    }
    </script>

    <!-- Bloque de Información según tipo de máquina -->
    <div style="margin-bottom:15px">


    <?php if ($isFlexo): ?>
        <?php renderFlexoConsoleInfoSection(get_defined_vars()); ?>
    <?php elseif ($isSeriPulpo || $isSeriPlana): ?>
        <?php renderSerigrafiaConsoleInfoSection(get_defined_vars()); ?>
    <?php else: ?>
        <?php renderSealingConsoleInfoSection(get_defined_vars()); ?>
    <?php endif; ?>

    </div>

    <!-- ============================================================== -->
    <!-- DESPACHO DE SUBFORMULARIOS SEGÚN MODE (editxid.php 4594-4609)  -->
    <!-- ============================================================== -->

    <?php if (!$hasApertura && !$isRebobinadora && empty($mode)): ?>
        <!-- ============================================================== -->
        <!-- FORMULARIO DIRECTO DE ALISTAMIENTO (editxid.php 5657 - 5780)   -->
        <!-- Se muestra obligatoriamente antes de iniciar la OT             -->
        <!-- ============================================================== -->
        <div style="background:#FFF8E7;border:1px solid #FFE082;border-radius:4px;padding:12px 18px;margin-bottom:15px;color:#8A6D3B;font-size:13px">
            <i class="fa fa-fw fa-info-circle"></i> <b>Paso 1: Alistamiento de Máquina</b> &mdash; Antes de iniciar la operación de la OT, debe registrar el alistamiento indicando ayudante y comentarios.
        </div>
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <div style="font-size:16px;font-weight:bold;color:#E1A500;margin-bottom:15px">
                <i class="fa fa-fw fa-wrench"></i> Iniciar Alistamiento
            </div>
            <form method="post" action="/production/work-orders/events/apertura" id="form_direct_alis">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <input type="hidden" name="mode" value="aperturasave">
                <input type="hidden" name="submode" value="start">

                <table border="0" width="100%" cellpadding="8" cellspacing="0">
                <colgroup><col width="160"><col></colgroup>
                <tr>
                    <td class="tdleft">Tipo</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)($machineInit['type_ant_title'] ?? $agenda['type_ant_title'] ?? '-'))?></b></td>
                </tr>
                <tr>
                    <td class="tdleft">Máquina</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)($machineInit['equipo_name'] ?? $agenda['equipo_name'] ?? '-'))?></b></td>
                </tr>
                <?php if ($isSelladora || !empty($machineInit['type_ant_inpmedidas_act'])): ?>
                    <tr>
                        <td class="tdleft">De Medida *</td>
                        <td class="tdnrm">
                            <select name="evt_medida_fromid" required class="inptxt" style="width:350px;background:#fff">
                                <option value="">&lt; Seleccione medida actual &gt;</option>
                                <?php foreach ($medidas as $m): ?>
                                    <option value="<?=$m['id']?>"><?=htmlspecialchars((string)$m['med_name'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">A Medida *</td>
                        <td class="tdnrm">
                            <select name="evt_medida_toid" required class="inptxt" style="width:350px;background:#fff">
                                <option value="">&lt; Seleccione nueva medida &gt;</option>
                                <?php foreach ($medidas as $m): ?>
                                    <option value="<?=$m['id']?>"><?=htmlspecialchars((string)$m['med_name'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td class="tdleft">Ayudante</td>
                    <td class="tdnrm">
                        <select name="evt_idayudante" class="inptxt" style="width:350px;background:#fff">
                            <option value="0">&lt; Sin ayudante asignado &gt;</option>
                            <?php foreach ($helpers as $h): ?>
                                <option value="<?=$h['id']?>"><?=htmlspecialchars((string)$h['ayudantes'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft" valign="top">Comentarios</td>
                    <td class="tdnrm">
                        <textarea class="inptxt" name="evt_comments" id="evt_comments" style="width:100%;height:65px" placeholder="Detalles u observaciones de alistamiento..."></textarea>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">
                        <button type="submit" class="btngreen" style="font-size:14px;padding:9px 24px">
                            <i class="fa fa-fw fa-play"></i> Iniciar&nbsp;
                        </button>
                    </td>
                </tr>
                </table>
            </form>
        </div>

    <?php elseif ($mode === 'production'): ?>
        <!-- SUBFORMULARIO: Registrar Producción (form.production.php) -->
        <?php
        $openProdEvent = ProductionService::findOpenProductionPhase($events, $workerOtId, $overrideEmbalaje);
        if ($refId <= 0 && $openProdEvent !== null) $refId = (int)$openProdEvent['id'];
        $currentRefEvt = null;
        if ($refId > 0) {
            foreach ($events as $ev) {
                if ((int)$ev['id'] === $refId) {
                    $currentRefEvt = $ev;
                    break;
                }
            }
        }
        $isEvtOpen = ($currentRefEvt !== null && (int)($currentRefEvt['evt_enddat'] ?? 0) === 0);
        $isStarting = ($openProdEvent === null && ($refId <= 0 || ($_GET['action'] ?? '') === 'start') && ($_GET['submode'] ?? '') !== 'end');
        if ($isStarting && $overrideEmbalaje !== 1 && !$hasAlis && !$isRebobinadora) {
            redirectResponse('/production/work-orders/operate?agid=' . $agId . '&error=' . rawurlencode('Debe iniciar y terminar el alistamiento antes de iniciar la producción.'));
            return;
        }
        $prodServiceInstance = $prodService;
        $defectUnits = ($refId > 0) ? $prodServiceInstance->getEventDefectUnits($refId) : [];
        $mermaMap = [];
        $repairMap = [];
        foreach ($defectUnits as $du) {
            if ($du['evt_type'] === 'merma') {
                $mermaMap[(int)$du['evt_merma_typeid']] = $du;
            } elseif ($du['evt_type'] === 'repair') {
                $repairMap[(int)$du['evt_repair_typeid']] = $du;
            }
        }
        // Pre-llenar automáticamente las mermas registradas al desmontar bobina(s)
        $dismountMermas = ($workerOtId > 0) ? $prodServiceInstance->getRollDismountMermas($workerOtId) : [];
        $totalDismountKgs = 0.0;
        foreach ($dismountMermas as $dmTypeId => $dmInfo) {
            $dmKgs = (float)($dmInfo['kgs'] ?? 0);
            $totalDismountKgs += $dmKgs;
            if (empty($mermaMap[$dmTypeId]) || (float)($mermaMap[$dmTypeId]['evt_kgstounits'] ?? 0) <= 0) {
                $mermaMap[$dmTypeId] = [
                    'evt_amount' => (float)($dmInfo['amount'] ?? 0),
                    'evt_kgstounits' => $dmKgs,
                    'evt_mtstounits' => (int)($dmInfo['mts'] ?? 0),
                    'evt_comments' => (string)($dmInfo['comments'] ?? ''),
                    'from_dismount' => true,
                    'dismount_kgs' => $dmKgs,
                ];
            } else {
                $mermaMap[$dmTypeId]['dismount_kgs'] = $dmKgs;
            }
        }
        $valAmount = (float)($currentRefEvt['evt_amount'] ?? 0);
        $valAmount2 = (float)($currentRefEvt['evt_amount2'] ?? 0);
        $valBobinaKg = (float)($currentRefEvt['prod_bobina_kg'] ?? 0);
        $valMmaq = (int)($currentRefEvt['evt_amount_metros_maquina'] ?? 0);
        $valMlin = (int)($currentRefEvt['evt_amount_metros_lineales'] ?? 0);
        $valMtype = (string)($currentRefEvt['evt_metrotype'] ?? ($isFlexo ? 'metros_lineales' : ''));
        $valComments = (string)($currentRefEvt['evt_comments'] ?? '');
        $seriDevPrintsDefault = max(1, (int)($agenda['req_operador_bastidoramt'] ?? $agenda['req_solic_devprints_cc'] ?? 1));
        $seriCounterBaseValue = $valAmount2 > 0 ? (int)round($valAmount2) : 0;
        $seriCounterColors = [];
        $seriSavedCounters = [];
        $seriPrevCounters = [];
        $parseSeriCounterMap = static function (string $commentsText): array {
            $counterMap = [];
            if (!preg_match('/\[SERI_COUNTERS:\s*(.*?)\]/', $commentsText, $counterMetaMatch)) {
                return $counterMap;
            }
            $counterMeta = trim((string)($counterMetaMatch[1] ?? ''));
            $counterMeta = trim((string)preg_replace('/\s+\|\s+(?:img\/bast|imp\/des)=.*$/', '', $counterMeta));
            if ($counterMeta === '') {
                return $counterMap;
            }
            foreach (preg_split('/\s*,\s*/', $counterMeta) as $counterToken) {
                if (!preg_match('/^C(\d+):.*?=(\d+(?:\.\d+)?)$/', trim((string)$counterToken), $counterMatch)) {
                    continue;
                }
                $counterIdx = (int)$counterMatch[1];
                $counterMap[$counterIdx] = (float)$counterMatch[2];
            }
            return $counterMap;
        };
        foreach ($events as $seriEvent) {
            if (!in_array((string)($seriEvent['evt_type'] ?? ''), ['prod', 'prodsericolor'], true)) {
                continue;
            }
            if ((int)($seriEvent['id'] ?? 0) === $refId) {
                continue;
            }
            foreach ($parseSeriCounterMap((string)($seriEvent['evt_comments'] ?? '')) as $counterIdx => $counterVal) {
                $seriPrevCounters[$counterIdx] = (float)($seriPrevCounters[$counterIdx] ?? 0) + (float)$counterVal;
            }
        }
        if (preg_match('/\[SERI_COUNTERS:\s*(.*?)\]/', $valComments, $seriCounterMetaMatch)) {
            $seriCounterMeta = trim((string)($seriCounterMetaMatch[1] ?? ''));
            $seriCounterMeta = trim((string)preg_replace('/\s+\|\s+imp\/des=.*$/', '', $seriCounterMeta));
            if ($seriCounterMeta !== '') {
                foreach (preg_split('/\s*,\s*/', $seriCounterMeta) as $seriCounterToken) {
                    if (preg_match('/^C(\d+):.*?=(\d+)$/', trim((string)$seriCounterToken), $seriCounterMatch)) {
                        $seriSavedCounters[(int)$seriCounterMatch[1]] = (int)$seriCounterMatch[2];
                    }
                }
            }
        }
        for ($seriIdx = 1; $seriIdx <= 10; $seriIdx++) {
            $seriColorDesc = trim((string)($agenda['fab_print_colordesc_' . $seriIdx] ?? ''));
            $seriHasFront = (int)($agenda['fab_print_colors_front_' . $seriIdx] ?? 0) > 0;
            $seriHasBack = (int)($agenda['fab_print_colors_back_' . $seriIdx] ?? 0) > 0;
            if (!$seriHasFront && !$seriHasBack && $seriColorDesc === '') {
                continue;
            }
            $seriFaces = [];
            if ($seriHasFront) {
                $seriFaces[] = 'Frente';
            }
            if ($seriHasBack) {
                $seriFaces[] = 'Dorso';
            }
            $seriCounterColors[] = [
                'idx' => $seriIdx,
                'label' => $seriColorDesc !== '' ? $seriColorDesc : ('Color N° ' . $seriIdx),
                'faces' => implode(' / ', $seriFaces),
                'value' => (int)($seriSavedCounters[$seriIdx] ?? 0),
                'prev_value' => (int)round((float)($seriPrevCounters[$seriIdx] ?? 0)),
            ];
        }

        // Consultar resumen de bobinas impresas emitidas en la OT para automatizar suma de peso y metros
        $printedRollsSummary = ($workerOtId > 0) ? $prodServiceInstance->getPrintedRollsSummary($workerOtId) : [];
        if (!empty($printedRollsSummary['rolls_count']) && $printedRollsSummary['rolls_count'] > 0) {
            if ($valBobinaKg <= 0 || $isEvtOpen) {
                $valBobinaKg = (float)$printedRollsSummary['total_weight_kg'];
            }
            if ($valMlin <= 0 || $isEvtOpen) {
                $valMlin = (int)round($printedRollsSummary['total_meters']);
            }
            if ($valAmount <= 0) {
                if ((float)($corte_m2 ?? 0) > 0) {
                    $valAmount = (float)round($valMlin / (float)$corte_m2);
                } else {
                    $valAmount = 0.0;
                }
            }
        }

        $telaWProd = (float)($agenda['req_operador_tela_width'] ?? 80);
        $telaGsmProd = (float)($agenda['fab_mat_gramms'] ?? 70);
        $prodMeterWeightKg = ($telaWProd > 0 && $telaGsmProd > 0) ? round(($telaWProd / 100) * ($telaGsmProd / 1000), 4) : 0.056;
        $prodUnitWeightKg = 0.035;
        if ((float)($agenda['ag_amount'] ?? 0) > 0 && ($kgAImprimir ?? 0) > 0) {
            $prodUnitWeightKg = round(((float)$kgAImprimir) / (float)$agenda['ag_amount'], 4);
        }
        $flexoApproxUnits = $valAmount > 0
            ? (int)round($valAmount)
            : (((float)($corte_m2 ?? 0) > 0 && $valMlin > 0) ? (int)round($valMlin / (float)$corte_m2) : 0);
        ?>
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver al listado de eventos
            </div>
        </div>

        <?php if ($isStarting): ?>
            <!-- ============================================================== -->
            <!-- PANTALLA 1: INICIAR TIRAJE DE PRODUCCIÓN (ARRANQUE DE MÁQUINA) -->
            <!-- ============================================================== -->
            <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:24px">
                <div style="font-size:17px;font-weight:bold;color:#00A85A;margin-bottom:15px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-play-circle" style="font-size:22px"></i> <?=$overrideEmbalaje === 1 ? 'Confirmar inicio de embalaje' : 'Iniciar Tiraje de Producción'?>
                </div>

                <div style="background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:6px;padding:16px;margin-bottom:20px;color:#166534">
                    <div style="font-size:14px;font-weight:bold;margin-bottom:6px">
                        <i class="fa fa-info-circle"></i> <?=$overrideEmbalaje === 1 ? 'Inicio de produccion de embalaje' : 'Arranque de Producción'?>
                    </div>
                    <div style="font-size:13px;line-height:1.5">
                        <?php if ($overrideEmbalaje === 1): ?>
                            Al presionar <b>Iniciar</b>, se registrara el inicio del embalaje y volvera a la consola de la OT.
                            Las cantidades de cajas, pallets y unidades se ingresaran al <b>Finalizar embalaje</b>.
                        <?php else: ?>
                            Al presionar <b>Comenzar Producción</b>, la máquina pasará a estado <b>EN PRODUCCIÓN ACTIVA</b> y comenzará a registrar el tiempo del tiraje.
                            Las unidades o metros fabricados y el registro de mermas se ingresarán al <b>Finalizar Producción</b> al concluir la tirada o cambiar de turno.
                        <?php endif; ?>
                    </div>
                </div>

                <form method="post" action="/production/work-orders/events/production">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                    <input type="hidden" name="action_type" value="start">
                    <input type="hidden" name="submode" value="start">
                    <input type="hidden" name="overrideembalaje" value="<?=$overrideEmbalaje?>">

                    <table border="0" width="100%" cellpadding="9" cellspacing="0">
                    <colgroup><col width="220"><col></colgroup>
                    <tr>
                        <td class="tdleft">Orden de Trabajo</td>
                        <td class="tdnrm"><b>OT #<?=htmlspecialchars((string)$agenda['prd_number'])?></b> &mdash; <?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Producto</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)$agenda['item_title'])?></b> (<?=htmlspecialchars((string)$agenda['fab_type'])?>)</td>
                    </tr>
                    <tr>
                        <td class="tdleft">Cantidad Programada</td>
                        <td class="tdnrm"><b style="font-size:15px;color:#115452"><?=$fmt($agenda['ag_amount'])?></b> unidades / bolsas</td>
                    </tr>
                    <tr>
                        <td class="tdleft">Máquina / Proceso</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($machineInit['equipo_name'] ?? $agenda['equipo_name'] ?? '-'))?></b> (<?=htmlspecialchars((string)($machineInit['type_ant_title'] ?? $agenda['type_ant_title'] ?? '-'))?>)</td>
                    </tr>
                    <tr>
                        <td class="tdleft">Operador Responsable</td>
                        <td class="tdnrm"><b><?=htmlspecialchars($currentOperatorName)?></b></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Hora de Inicio</td>
                        <td class="tdnrm"><b style="color:#059669"><i class="fa fa-clock"></i> <?=date('d.m.Y H:i')?></b></td>
                    </tr>
                    <?php if ($isFlexo && $overrideEmbalaje !== 1): ?>
                        <input type="hidden" name="evt_metrotype" value="metros_lineales">
                    <?php endif; ?>
                    <tr>
                        <td class="tdleft">Observaciones de Inicio</td>
                        <td class="tdnrm">
                            <textarea name="evt_comments" class="inptxt" style="width:100%;height:55px" placeholder="Observaciones iniciales de arranque (opcional)..."></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm" style="padding-top:18px">
                            <button type="submit" class="btngreen" style="font-size:15px;padding:12px 30px;box-shadow:0 2px 4px rgba(0,0,0,0.15)">
                                <i class="fa fa-fw fa-play"></i> Iniciar
                            </button>
                        </td>
                    </tr>
                    </table>
                </form>
            </div>

        <?php else: ?>
            <!-- ============================================================== -->
            <!-- PANTALLA 2: FINALIZAR PRODUCCIÓN O MODIFICAR REGISTRO          -->
            <!-- ============================================================== -->
            <?php $showClosingSidePanel = ($isSelladora || $overrideEmbalaje === 1); ?>
            <?php if ($showClosingSidePanel): ?>
            <style>
            .closing-layout-grid {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 350px;
                gap: 22px;
                align-items: start;
                width: 100%;
                margin-bottom: 24px;
            }
            .closing-side-panel {
                position: sticky;
                top: 15px;
            }
            @media (max-width: 1100px) {
                .closing-layout-grid {
                    grid-template-columns: 1fr;
                }
                .closing-side-panel {
                    position: static;
                }
            }
            </style>
            <div class="closing-layout-grid">
            <?php endif; ?>
            <div style="background:#FFFFFF;border:1px solid #E2E8F0;border-radius:8px;padding:22px;box-sizing:border-box;min-width:0;box-shadow:0 1px 3px rgba(15,23,42,0.06);<?=$overrideEmbalaje === 1 && !$showClosingSidePanel ? 'max-width:1180px;margin:0 auto' : ''?>">
                <div style="font-size:16px;font-weight:bold;color:#115452;margin-bottom:15px">
                    <i class="fa fa-fw fa-flag-checkered"></i> <?=$overrideEmbalaje === 1 ? ($isEvtOpen ? 'Finalizar embalaje' : 'Detalle de embalaje') : ($refId > 0 ? ($isEvtOpen ? 'Finalizar / Registrar Avance de Producción' : 'Detalle de Producción') : 'Cierre de Producción')?>
                </div>

                <?php if ($isEvtOpen && $currentRefEvt !== null): ?>
                    <div style="background:#EFF6FF;border:1px solid #93C5FD;border-radius:4px;padding:12px 16px;margin-bottom:18px;color:#1E40AF">
                        <i class="fa fa-stopwatch"></i> <?=$overrideEmbalaje === 1 ? 'Embalaje iniciado el' : 'Tiraje en curso iniciado el'?> <b><?=date('d/m/Y H:i:s', (int)$currentRefEvt['evt_crtdat'])?></b>.
                        <?=$overrideEmbalaje === 1 ? 'Registre las cantidades embaladas y solicite la autorizacion del supervisor.' : ($isSelladora ? 'Ingrese los contadores, mermas y reparaciones de Corte y Sellado.' : 'Ingrese las bolsas/metros terminados y mermas para cerrar el evento.')?>
                    </div>
                <?php endif; ?>

                <form method="post" action="/production/work-orders/events/production">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                    <input type="hidden" name="refid" value="<?=$refId?>">
                    <input type="hidden" name="overrideembalaje" value="<?=$overrideEmbalaje?>">
                    <input type="hidden" name="submode" id="prod_submode" value="<?=$isEvtOpen ? 'end' : 'update'?>">

                    <table border="0" width="100%" cellpadding="8" cellspacing="0">
                    <colgroup><col width="220"><col></colgroup>
                    <tr>
                        <td class="tdleft">Tipo</td>
                        <td class="tdnrm"><b><?=$overrideEmbalaje === 1 ? 'Embalaje' : htmlspecialchars((string)($machineInit['type_ant_title'] ?? $agenda['type_ant_title'] ?? '-'))?></b></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Máquina</td>
                        <td class="tdnrm"><b><?=htmlspecialchars((string)($machineInit['equipo_name'] ?? $agenda['equipo_name'] ?? '-'))?></b></td>
                    </tr>
                    <?php if ($overrideEmbalaje === 1): ?>
                        <?php
                        $embReqId = (int)($agenda['ag_reqid'] ?? 0);
                        $mixedOrders = $prodService->getMixedPackagingOrders($embReqId);
                        $embDetail = $prodService->getPackagingEventDetail($refId, $workerOtId);
                        $embId = (int)($embDetail['id'] ?? 0);
                        $savedMixed = $embId > 0 ? $prodService->getMixedPackagingDetail($embId) : [];
                        $closureTypes = $prodService->listPackagingClosureTypes($embReqId);
                        ?>
                        <tr><td colspan="2"><?php renderPackagingProductionFields($agenda, $embDetail, $closureTypes, $mixedOrders, $savedMixed); ?></td></tr>
                    <?php elseif ($isFlexo): ?>
                        <input type="hidden" name="evt_metrotype" value="metros_lineales">
                        <input type="hidden" name="evt_amount_metros_maquina" value="<?=$valMmaq?>">
                        <?php if (!empty($printedRollsSummary['rolls_count']) && $printedRollsSummary['rolls_count'] > 0): ?>
                            <tr>
                                <td colspan="2" style="padding:0 0 14px 0">
                                    <div style="background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:6px;padding:12px 16px;color:#166534">
                                        <div style="font-size:13.5px;font-weight:bold;margin-bottom:5px;display:flex;align-items:center;gap:7px">
                                            <i class="fa fa-tag" style="color:#059669"></i> Bobinas Impresas Producidas en esta OT: 
                                            <b style="font-size:15px;color:#15803D"><?=number_format($printedRollsSummary['total_weight_kg'], 2, ',', '.')?> kg</b>
                                            <span style="font-size:12.5px;color:#047857">(<?=number_format($printedRollsSummary['total_meters'], 0, ',', '.')?> metros &bull; <?=$printedRollsSummary['rolls_count']?> bobina<?=$printedRollsSummary['rolls_count'] > 1 ? 's' : ''?>)</span>
                                        </div>
                                        <div style="font-size:12px;color:#166534;line-height:1.4">
                                            Se han sumado y precargado automáticamente los kilos y metros de las bobinas semielaboradas emitidas:
                                            <b><?=implode(', ', array_map(fn($r) => $r['roll_code'] . ' (' . number_format($r['weight_kg'], 2, ',', '.') . ' kg, ' . number_format($r['meters'], 0, ',', '.') . ' m)', $printedRollsSummary['rolls_list']))?></b>.
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="tdleft">Metros Lineales Producidos *</td>
                            <td class="tdnrm">
                                <input type="number" step="1" min="0" name="evt_amount_metros_lineales" id="inp_flexo_mlin" class="inptxt" style="width:200px;font-size:15px;font-weight:bold;<?=!empty($printedRollsSummary['rolls_count']) ? 'background:#F0FDF4;color:#166534' : ''?>" value="<?=$valMlin > 0 ? $valMlin : ''?>" placeholder="0" required oninput="calcFlexoKgs()"> mtrs
                                <?php if (!empty($printedRollsSummary['rolls_count'])): ?>
                                    <span style="font-size:11px;background:#059669;color:#FFFFFF;padding:3px 8px;border-radius:4px;margin-left:6px;font-weight:bold"><i class="fa fa-check-circle"></i> Suma de <?=count($printedRollsSummary['rolls_list'])?> bobinas impresas</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="tdleft">Unidades Aproximadas *</td>
                            <td class="tdnrm">
                                <input type="number" step="1" min="0" name="prod_amount" id="inp_flexo_amount" class="inptxt" style="width:200px;font-size:15px;font-weight:bold;background:#F8FAFC" value="<?=$flexoApproxUnits > 0 ? $flexoApproxUnits : ''?>" placeholder="0" required>
                                <div id="flexo_units_hint" style="font-size:11.5px;color:#64748B;margin-top:5px">
                                    <i class="fa fa-info-circle"></i>
                                    Aproximado segun el corte de la bolsa (<?=number_format((float)($corte_m2 ?? 0), 4, ',', '.')?> m por unidad). Puede corregirlo antes de guardar.
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="tdleft">Kilos Producidos *</td>
                            <td class="tdnrm">
                                <input type="number" step="0.01" min="0" name="prod_bobina_kg" id="inp_flexo_kgs" class="inptxt" style="width:200px;font-size:15px;font-weight:bold;background:#F0FDF4;color:#166534" value="<?=$valBobinaKg > 0 ? number_format($valBobinaKg, 2, '.', '') : ''?>" placeholder="0.00" required> kg
                                <?php if (!empty($printedRollsSummary['rolls_count'])): ?>
                                    <span style="font-size:11px;background:#059669;color:#FFFFFF;padding:3px 8px;border-radius:4px;margin-left:6px;font-weight:bold"><i class="fa fa-check-circle"></i> Suma de <?=count($printedRollsSummary['rolls_list'])?> bobinas impresas</span>
                                    <button type="button" class="btngrey" style="font-size:11px;padding:3px 8px;margin-left:6px;cursor:pointer" onclick="restorePrintedRollsValues()"><i class="fa fa-sync-alt"></i> Restablecer suma (<?=number_format($printedRollsSummary['total_weight_kg'], 2, ',', '.')?> kg)</button>
                                <?php else: ?>
                                    <span style="font-size:12px;color:#64748B;margin-left:8px"><i class="fa fa-calculator"></i> Calculado automáticamente según gramaje y ancho de tela (<?=$prodMeterWeightKg?> kg/m). Puede ajustarse manualmente si pesó en balanza.</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php elseif ($isSeriPulpo): ?>
                        <?php if ($amtColorsDorso > 0): ?>
                            <tr>
                                <td colspan="2" style="padding:0 0 12px 0">
                                    <div style="background:#F8FAFC;border:1.5px solid #CBD5E1;border-radius:6px;padding:12px 16px">
                                        <div style="font-weight:bold;color:#1E293B;margin-bottom:6px;font-size:13px;display:flex;align-items:center;gap:6px">
                                            <i class="fa fa-history" style="color:#0284C7"></i> Balance acumulado de tirajes anteriores en esta OT:
                                        </div>
                                        <div style="display:flex;gap:20px;flex-wrap:wrap;font-size:12.5px;color:#334155;margin-bottom:8px">
                                            <div>&bull; Frentes acumulados previos: <b><?=number_format($prevFrente, 0, ',', '.')?></b> pasadas</div>
                                            <div>&bull; Dorsos acumulados previos: <b><?=number_format($prevDorso, 0, ',', '.')?></b> pasadas</div>
                                            <div>&bull; Bolsas ya terminadas: <b><?=number_format($prevFinished, 0, ',', '.')?></b> unidades</div>
                                        </div>
                                        <div style="padding-top:6px;border-top:1px dashed #E2E8F0;font-size:12.5px">
                                            <?php if ($prevSurplusFrente > 0): ?>
                                                <span style="color:#D97706;font-weight:bold">
                                                    <i class="fa fa-exclamation-triangle"></i> Hay <?=number_format($prevSurplusFrente, 0, ',', '.')?> bolsas en proceso con FRENTE ya impreso (pendientes de dorso).
                                                </span>
                                            <?php elseif ($prevSurplusDorso > 0): ?>
                                                <span style="color:#D97706;font-weight:bold">
                                                    <i class="fa fa-exclamation-triangle"></i> Hay <?=number_format($prevSurplusDorso, 0, ',', '.')?> bolsas en proceso con DORSO ya impreso (pendientes de frente).
                                                </span>
                                            <?php else: ?>
                                                <span style="color:#059669;font-weight:bold">
                                                    <i class="fa fa-check-circle"></i> No hay bolsas pendientes entre caras (Frente y Dorso al día).
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="tdleft">Pasadas Frente (este tiraje)</td>
                                <td class="tdnrm">
                                    <input type="number" step="1" min="0" name="amount_pulpo_frente" id="idx_pulpo_frente" class="inptxt" style="width:200px" value="<?=(float)($currentRefEvt['evt_amount_pulpo_frente'] ?? 0) > 0 ? (int)$currentRefEvt['evt_amount_pulpo_frente'] : ''?>" placeholder="0" oninput="calcPulpoUnits()"> pasadas
                                </td>
                            </tr>
                            <tr>
                                <td class="tdleft">Pasadas Dorso (este tiraje)</td>
                                <td class="tdnrm">
                                    <input type="number" step="1" min="0" name="amount_pulpo_dorso" id="idx_pulpo_dorso" class="inptxt" style="width:200px" value="<?=(float)($currentRefEvt['evt_amount_pulpo_dorso'] ?? 0) > 0 ? (int)$currentRefEvt['evt_amount_pulpo_dorso'] : ''?>" placeholder="0" oninput="calcPulpoUnits()"> pasadas
                                </td>
                            </tr>
                            <tr>
                                <td class="tdleft">Bolsas Terminadas Completas *</td>
                                <td class="tdnrm">
                                    <input type="number" step="1" min="0" name="prod_amount" id="idx_pulpo_amount" class="inptxt" style="width:200px;font-weight:bold;background:#F8FAFC" value="<?=$valAmount > 0 ? (int)$valAmount : ''?>" placeholder="0" required> unidades
                                    <div id="pulpo_surplus_preview" style="font-size:12px;margin-top:5px"></div>
                                    <div style="font-size:11.5px;color:#64748B;margin-top:4px">
                                        <i class="fa fa-info-circle"></i> Se calculan las bolsas que completan ambas caras considerando también el saldo previo de frentes o dorsos disponibles.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td class="tdleft">Total Pasadas Frente *</td>
                                <td class="tdnrm">
                                    <input type="number" step="1" name="amount_pulpo_frente" id="idx_pulpo_frente" class="inptxt" style="width:200px" value="<?=(float)($currentRefEvt['evt_amount_pulpo_frente'] ?? 0) > 0 ? (int)$currentRefEvt['evt_amount_pulpo_frente'] : ''?>" placeholder="0" required oninput="calcPulpoUnitsSoloFrente()"> pasadas
                                    <input type="hidden" name="amount_pulpo_dorso" id="idx_pulpo_dorso" value="0">
                                </td>
                            </tr>
                            <tr>
                                <td class="tdleft">Bolsas Terminadas *</td>
                                <td class="tdnrm">
                                    <input type="number" step="1" name="prod_amount" id="idx_pulpo_amount" class="inptxt" style="width:200px;font-weight:bold;background:#F8FAFC" value="<?=$valAmount > 0 ? (int)$valAmount : ''?>" placeholder="0" required> unidades
                                    <div style="font-size:11.5px;color:#64748B;margin-top:4px">
                                        <i class="fa fa-info-circle"></i> Al ser solo frente, 1 pasada = 1 bolsa terminada.
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php elseif ($isSeri): ?>
                        <input type="hidden" name="prod_amount2" id="idx_seri_counter_base" value="<?=$seriCounterBaseValue > 0 ? $seriCounterBaseValue : ''?>">
                        <tr>
                            <td class="tdleft">Contador libre por color *</td>
                            <td class="tdnrm">
                                <div style="display:flex;flex-direction:column;gap:10px">
                                    <?php foreach ($seriCounterColors as $seriColor): ?>
                                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:8px 10px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:6px">
                                            <div style="min-width:220px;font-size:12.5px;font-weight:bold;color:#1E293B">
                                                <?=htmlspecialchars((string)$seriColor['label'])?>
                                                <?php if ($seriColor['faces'] !== ''): ?>
                                                    <div style="font-size:11px;font-weight:normal;color:#64748B"><?=htmlspecialchars((string)$seriColor['faces'])?></div>
                                                <?php endif; ?>
                                            </div>
                                            <input type="hidden" name="seri_counter_label_<?=$seriColor['idx']?>" value="<?=htmlspecialchars((string)$seriColor['label'])?>">
                                            <input type="number" step="1" min="0" name="seri_counter_color_<?=$seriColor['idx']?>" id="idx_seri_counter_<?=$seriColor['idx']?>" class="inptxt seri-counter-input" style="width:180px;font-size:15px;font-weight:bold" value="<?=$seriColor['value'] > 0 ? $seriColor['value'] : ''?>" placeholder="0" data-prev="<?=$seriColor['prev_value']?>" oninput="calcSeriUnitsFromCounters()">
                                            <span style="font-size:12px;color:#64748B">contador libre</span>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($seriCounterColors)): ?>
                                        <div style="font-size:12px;color:#64748B;background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:6px;padding:10px 12px">
                                            No hay colores configurados en la ficha. Puede registrar el contador igualmente en la cantidad final.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="tdleft">Cantidad de imágenes impresas en bastidor *</td>
                            <td class="tdnrm">
                                <input type="number" step="1" min="1" max="10" name="seri_bastidor_multiplier" id="idx_seri_bastidor_multiplier" class="inptxt" style="width:200px;font-size:15px;font-weight:bold" value="<?=$seriDevPrintsDefault?>" oninput="calcSeriUnitsFromCounters()"> imagen<?=($seriDevPrintsDefault !== 1 ? 'es' : '')?>
                            </td>
                        </tr>
                        <tr>
                            <td class="tdleft">Total unidades terminadas *</td>
                            <td class="tdnrm">
                                <input type="number" step="1" min="0" name="prod_amount" id="idx_seri_amount" class="inptxt" style="width:200px;font-weight:bold;background:#F8FAFC" value="<?=$valAmount > 0 ? (int)$valAmount : ''?>" placeholder="0" required> unidades
                            </td>
                        </tr>
                    <?php elseif ($isSelladora): ?>
                        <tr>
                            <td class="tdleft">Contador Tablero Principal *</td>
                            <td class="tdnrm"><input type="number" step="1" min="0" name="prod_amount" id="cys_counter_main" class="inptxt" style="width:200px" value="<?=$valAmount > 0 ? (int)$valAmount : ''?>" placeholder="0" required> unidades</td>
                        </tr>
                        <tr>
                            <td class="tdleft">Contador Módulo Recibidor *</td>
                            <td class="tdnrm"><input type="number" step="1" min="0" name="prod_amount2" id="cys_counter_receiver" class="inptxt" style="width:200px" value="<?=$valAmount2 > 0 ? (int)$valAmount2 : '0'?>" placeholder="0" required> unidades</td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td class="tdleft">Contador Principal / Bolsas *</td>
                            <td class="tdnrm"><input type="number" step="1" name="prod_amount" class="inptxt" style="width:200px" value="<?=$valAmount > 0 ? (int)$valAmount : ''?>" placeholder="0" required> unidades</td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="tdleft">Comentarios</td>
                        <td class="tdnrm"><textarea name="evt_comments" class="inptxt" style="width:100%;height:55px" placeholder="Observaciones de producción..."><?=htmlspecialchars($valComments)?></textarea></td>
                    </tr>

                    <?php if ($overrideEmbalaje !== 1 && !empty($mermatypes)): ?>
                        <!-- TABLA DE MERMAS (editxid.php líneas 850-915 y form.production.php 990-1010) -->
                        <tr>
                            <td colspan="2" style="padding-top:15px">
                                <?php if ($totalDismountKgs > 0): ?>
                                    <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:4px;padding:10px 14px;margin-bottom:12px;color:#166534;font-size:12.5px;display:flex;align-items:center;gap:8px">
                                        <i class="fa fa-dolly" style="font-size:16px;color:#059669"></i>
                                        <span>Se han precargado automáticamente <b><?=number_format($totalDismountKgs, 2, ',', '.')?> kg</b> de mermas registradas al desmontar bobina(s). Puede revisarlas, complementarlas o ajustarlas antes de finalizar.</span>
                                    </div>
                                <?php endif; ?>
                                <table border="0" width="100%" cellpadding="6" cellspacing="0" style="background:#FAFAFA;border:1px solid #DDDDDD;border-radius:4px">
                                <colgroup>
                                    <col width="220">
                                    <col width="120">
                                    <col width="120">
                                    <?php if (!$isSelladora): ?><col width="120"><?php endif; ?>
                                    <col>
                                </colgroup>
                                <tr>
                                    <td colspan="<?=$isSelladora ? 4 : 5?>" class="tdleft" style="background-color:#E8F0FE;color:#1A73E8;font-weight:bold">
                                        <i class="fa fa-fw fa-exclamation-triangle"></i> Registro de Mermas por Tipo
                                    </td>
                                </tr>
                                <tr>
                                    <td class="tdheader">Tipo / Causa</td>
                                    <td class="tdheader" align="center">Bolsas / Unid.</td>
                                    <td class="tdheader" align="center">Kilos</td>
                                    <?php if (!$isSelladora): ?><td class="tdheader" align="center">Metros</td><?php endif; ?>
                                    <td class="tdheader">Comentarios</td>
                                </tr>
                                <?php foreach ($mermatypes as $mt):
                                    $mtId = (int)$mt['id'];
                                    $existM = $mermaMap[$mtId] ?? [];
                                    $mAmt = (float)($existM['evt_amount'] ?? 0);
                                    $mKgs = (float)($existM['evt_kgstounits'] ?? 0);
                                    $mMts = (int)($existM['evt_mtstounits'] ?? 0);
                                    $mComm = (string)($existM['evt_comments'] ?? '');
                                    $fromAlis = !empty($existM['from_alistamiento']);
                                    $fromDismount = !empty($existM['from_dismount']);
                                    $dismountKgs = (float)($existM['dismount_kgs'] ?? 0);
                                ?>
                                <tr <?=$fromDismount ? 'style="background:#F0FDF4"' : ($fromAlis ? 'style="background:#FEF9C3"' : '')?>>
                                    <td class="tdnrm">
                                        <b><?=htmlspecialchars((string)$mt['merma_title'])?></b>
                                        <?php if ($isSelladora): ?>
                                            <input type="hidden" name="merma_mts_<?=$mtId?>" value="<?=$mMts?>">
                                        <?php endif; ?>
                                        <?php if ($isSelladora): ?><br><select name="merma_unit_<?=$mtId?>" id="merma_unit_<?=$mtId?>" class="inptxt" onchange="syncCysDefectUnit('merma',<?=$mtId?>)" style="margin-top:5px">
                                            <option value="kgs" <?=$mKgs>0?'selected':''?>>Kilos</option>
                                            <option value="amount" <?=$mKgs<=0&&$mAmt>0?'selected':''?>>Unidad</option>
                                        </select><?php endif; ?>
                                        <?php if ($fromDismount && $dismountKgs > 0): ?>
                                            <span style="font-size:11px;background:#059669;color:#FFFFFF;padding:2px 7px;border-radius:4px;margin-left:6px;font-weight:bold" title="Cargado automáticamente desde el desmontaje de bobinas">
                                                <i class="fa fa-dolly"></i> Desmontaje: <?=number_format($dismountKgs, 2, ',', '.')?> kg
                                            </span>
                                        <?php elseif ($fromAlis): ?>
                                            <span style="font-size:11px;background:#CA8A04;color:#FFFFFF;padding:2px 7px;border-radius:4px;margin-left:6px;font-weight:bold"><i class="fa fa-check-circle"></i> Desde Autocontrol</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="tdnrm" align="center">
                                        <input type="number" step="1" min="0" name="merma_amount_<?=$mtId?>" id="merma_amt_<?=$mtId?>" class="inptxt" style="width:90px;text-align:center" value="<?=$mAmt > 0 ? (int)$mAmt : ''?>" oninput="syncMermaRow(<?=$mtId?>, 'amount')">
                                    </td>
                                    <td class="tdnrm" align="center">
                                        <input type="number" step="0.01" min="0" name="merma_kgs_<?=$mtId?>" id="merma_kgs_<?=$mtId?>" class="inptxt" style="width:90px;text-align:center;<?=$fromDismount ? 'background:#DCFCE7;font-weight:bold;color:#166534' : ($fromAlis ? 'background:#FFFBEB;font-weight:bold;color:#92400E' : '')?>" value="<?=$mKgs > 0 ? $mKgs : ''?>" oninput="syncMermaRow(<?=$mtId?>, 'kgs')">
                                    </td>
                                    <?php if (!$isSelladora): ?>
                                    <td class="tdnrm" align="center">
                                        <input type="number" step="1" min="0" name="merma_mts_<?=$mtId?>" id="merma_mts_<?=$mtId?>" class="inptxt" style="width:90px;text-align:center" value="<?=$mMts > 0 ? $mMts : ''?>" oninput="syncMermaRow(<?=$mtId?>, 'mts')">
                                    </td>
                                    <?php endif; ?>
                                    <td class="tdnrm">
                                        <input type="text" name="merma_comments_<?=$mtId?>" class="inptxt" style="width:100%" value="<?=htmlspecialchars($mComm)?>" placeholder="Detalle...">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </table>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php if ($overrideEmbalaje !== 1 && $isSelladora && !empty($repairtypes)): ?>
                        <!-- TABLA DE REPARACIONES (editxid.php líneas 916-960) -->
                        <tr>
                            <td colspan="2" style="padding-top:15px">
                                <table border="0" width="100%" cellpadding="6" cellspacing="0" style="background:#FAFAFA;border:1px solid #DDDDDD;border-radius:4px">
                                <colgroup>
                                    <col width="220">
                                    <col width="120">
                                    <col width="120">
                                    <col>
                                </colgroup>
                                <tr>
                                    <td colspan="4" class="tdleft" style="background-color:#FFF3E0;color:#E65100;font-weight:bold">
                                        <i class="fa fa-fw fa-wrench"></i> Registro de Reparaciones / Defectos
                                    </td>
                                </tr>
                                <tr>
                                    <td class="tdheader">Tipo Reparación</td>
                                    <td class="tdheader" align="center">Unidades</td>
                                    <td class="tdheader" align="center">Kilos</td>
                                    <td class="tdheader">Comentarios</td>
                                </tr>
                                <?php foreach ($repairtypes as $rt):
                                    $rtId = (int)$rt['id'];
                                    $existR = $repairMap[$rtId] ?? [];
                                    $rAmt = (float)($existR['evt_amount'] ?? 0);
                                    $rKgs = (float)($existR['evt_kgstounits'] ?? 0);
                                    $rComm = (string)($existR['evt_comments'] ?? '');
                                ?>
                                <tr>
                                    <td class="tdnrm"><b><?=htmlspecialchars((string)$rt['repair_title'])?></b>
                                        <br><select name="repair_unit_<?=$rtId?>" id="repair_unit_<?=$rtId?>" class="inptxt" onchange="syncCysDefectUnit('repair',<?=$rtId?>)" style="margin-top:5px">
                                            <option value="kgs" <?=$rKgs>0?'selected':''?>>Kilos</option>
                                            <option value="amount" <?=$rKgs<=0&&$rAmt>0?'selected':''?>>Unidad</option>
                                        </select>
                                    </td>
                                    <td class="tdnrm" align="center">
                                        <input type="number" step="1" min="0" name="repair_amount_<?=$rtId?>" class="inptxt" style="width:90px;text-align:center" value="<?=$rAmt > 0 ? (int)$rAmt : ''?>" oninput="syncCysDefectUnit('repair',<?=$rtId?>,'amount')">
                                    </td>
                                    <td class="tdnrm" align="center">
                                        <input type="number" step="0.01" min="0" name="repair_kgs_<?=$rtId?>" class="inptxt" style="width:90px;text-align:center" value="<?=$rKgs > 0 ? $rKgs : ''?>" oninput="syncCysDefectUnit('repair',<?=$rtId?>,'kgs')">
                                    </td>
                                    <td class="tdnrm">
                                        <input type="text" name="repair_comments_<?=$rtId?>" class="inptxt" style="width:100%" value="<?=htmlspecialchars($rComm)?>" placeholder="Detalle...">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </table>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php if ($isSelladora || $overrideEmbalaje === 1): ?>
                        <?php renderProductionReceiverField($phaseReceivers, $phaseResponsibilities[$refId] ?? []); ?>
                    <?php endif; ?>
                    <?php if ($isSelladora && $overrideEmbalaje !== 1): ?>
                        <tr>
                            <td colspan="2" style="padding-top:14px">
                                <div style="background:#F0FDFA;border:1.5px solid #99F6E4;border-radius:6px;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px">
                                    <div>
                                        <b style="color:#0F766E;font-size:13.5px"><i class="fa fa-boxes"></i> Embalaje y Cierre de Pallet (Monoproducto o Mixto)</b>
                                        <div style="font-size:12px;color:#115E59;margin-top:2px">¿Vas a cerrar las cajas o armar un pallet mixto con otros CCs para este pedido? Puedes registrar el embalaje y cierre aquí:</div>
                                    </div>
                                    <a href="/production/work-orders/operate?agid=<?=$agId?>&amp;mode=production&amp;overrideembalaje=1&amp;action=start" class="btnblue" style="text-decoration:none;padding:9px 18px;font-size:13px;border-radius:6px;font-weight:bold;white-space:nowrap;box-shadow:0 1px 2px rgba(0,0,0,0.1)">
                                        <i class="fa fa-box-open"></i> Abrir Embalaje / Cierre Mixto
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm" style="padding-top:15px">
                            <?php if ($refId > 0 && $isEvtOpen): ?>
                                <button type="submit" class="btnorange" style="font-size:14px;padding:10px 24px;font-weight:bold;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16)" onclick="document.getElementById('prod_submode').value='end'">
                                    <i class="fa fa-fw fa-stop"></i> Finalizar
                                </button>
                                <button type="submit" class="btngreen" style="font-size:14px;padding:10px 22px;margin-left:10px;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16)" onclick="document.getElementById('prod_submode').value='update'">
                                    <i class="fa fa-fw fa-save"></i> Guardar
                                </button>
                            <?php elseif ($refId > 0): ?>
                                <button type="submit" class="btngreen" style="font-size:14px;padding:10px 24px;font-weight:bold;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16)" onclick="document.getElementById('prod_submode').value='update'">
                                    <i class="fa fa-fw fa-save"></i> Guardar Cambios
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btnorange" style="font-size:14px;padding:10px 24px;font-weight:bold;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16)" onclick="document.getElementById('prod_submode').value='end'">
                                    <i class="fa fa-fw fa-check"></i> Finalizar
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    </table>
                </form>

                <script>
                var PROD_METER_WEIGHT_KG = <?=(float)$prodMeterWeightKg?>;
                var PROD_UNIT_WEIGHT_KG = <?=(float)$prodUnitWeightKg?>;
                var FLEXO_CUT_M2 = <?=(float)($corte_m2 ?? 0)?>;
                var IS_FLEXO = <?=$isFlexo ? 'true' : 'false'?>;
                var HAS_PRINTED_ROLLS = <?=!empty($printedRollsSummary['rolls_count']) ? 'true' : 'false'?>;
                var PRINTED_ROLLS_TOTAL_KG = <?=(float)($printedRollsSummary['total_weight_kg'] ?? 0)?>;
                var PRINTED_ROLLS_TOTAL_METERS = <?=(float)($printedRollsSummary['total_meters'] ?? 0)?>;

                function calcFlexoApproxUnits(mlin) {
                    var meters = parseFloat(mlin) || 0;
                    if (meters <= 0) return 0;
                    if (FLEXO_CUT_M2 > 0) {
                        return Math.round(meters / FLEXO_CUT_M2);
                    }
                    return 0;
                }

                function updateFlexoUnitsHint(estimatedUnits) {
                    var hint = document.getElementById('flexo_units_hint');
                    if (!hint) return;
                    var units = parseInt(String(estimatedUnits || '0'), 10) || 0;
                    if (units > 0) {
                        hint.innerHTML = '<i class=\"fa fa-info-circle\"></i> Aproximado segun el corte de la bolsa (' + String(FLEXO_CUT_M2 || 0).replace('.', ',') + ' m por unidad): <b>' + units.toLocaleString('es-CL') + ' unidades</b>. Puede corregirlo antes de guardar.';
                    } else {
                        hint.innerHTML = '<i class=\"fa fa-info-circle\"></i> Aproximado segun el corte de la bolsa (' + String(FLEXO_CUT_M2 || 0).replace('.', ',') + ' m por unidad). Puede corregirlo antes de guardar.';
                    }
                }

                function restorePrintedRollsValues() {
                    var mlinInp = document.getElementById('inp_flexo_mlin');
                    var kgsInp = document.getElementById('inp_flexo_kgs');
                    var amtInp = document.getElementById('inp_flexo_amount');
                    if (mlinInp) mlinInp.value = Math.round(PRINTED_ROLLS_TOTAL_METERS);
                    if (kgsInp) kgsInp.value = PRINTED_ROLLS_TOTAL_KG.toFixed(2);
                    if (amtInp) amtInp.value = calcFlexoApproxUnits(PRINTED_ROLLS_TOTAL_METERS) || '';
                    updateFlexoUnitsHint(calcFlexoApproxUnits(PRINTED_ROLLS_TOTAL_METERS));
                }

                function calcFlexoKgs() {
                    var mlinInp = document.getElementById('inp_flexo_mlin');
                    var kgsInp = document.getElementById('inp_flexo_kgs');
                    var amtInp = document.getElementById('inp_flexo_amount');
                    if (!mlinInp || !kgsInp) return;
                    var mlin = parseFloat(mlinInp.value) || 0;
                    if (HAS_PRINTED_ROLLS && Math.abs(mlin - PRINTED_ROLLS_TOTAL_METERS) < 1.0) {
                        kgsInp.value = PRINTED_ROLLS_TOTAL_KG.toFixed(2);
                    } else if (mlin > 0 && PROD_METER_WEIGHT_KG > 0) {
                        var kgs = (mlin * PROD_METER_WEIGHT_KG).toFixed(2);
                        kgsInp.value = kgs;
                    }
                    if (amtInp) {
                        var approxUnits = calcFlexoApproxUnits(mlin);
                        amtInp.value = approxUnits > 0 ? approxUnits : '';
                        updateFlexoUnitsHint(approxUnits);
                    }
                }

                updateFlexoUnitsHint(calcFlexoApproxUnits(document.getElementById('inp_flexo_mlin') ? document.getElementById('inp_flexo_mlin').value : 0));

                function calcSeriUnitsFromCounters() {
                    var factorInp = document.getElementById('idx_seri_bastidor_multiplier');
                    var amountInp = document.getElementById('idx_seri_amount');
                    var baseInp = document.getElementById('idx_seri_counter_base');
                    if (!factorInp || !amountInp || !baseInp) return;

                    var factor = parseInt(String(factorInp.value || '0').replace(',', '.'), 10) || 0;
                    if (factor <= 0) factor = 1;

                    var counters = [];
                    var prevCounters = [];
                    document.querySelectorAll('.seri-counter-input').forEach(function(inp) {
                        var val = parseInt(String(inp.value || '0').replace(',', '.'), 10) || 0;
                        var prevVal = parseInt(String(inp.getAttribute('data-prev') || '0').replace(',', '.'), 10) || 0;
                        prevCounters.push(prevVal);
                        counters.push(prevVal + val);
                    });

                    if (counters.length === 0) {
                        baseInp.value = '';
                        return;
                    }

                    var prevBaseCounter = prevCounters.length > 0 ? Math.min.apply(Math, prevCounters) : 0;
                    var newBaseCounter = Math.min.apply(Math, counters);
                    var baseCounter = Math.max(0, newBaseCounter - prevBaseCounter);
                    var totalUnits = baseCounter * factor;
                    baseInp.value = String(baseCounter);
                    amountInp.value = String(totalUnits);
                }

                function syncMermaRow(id, field) {
                    if (document.getElementById('merma_unit_'+id)) {
                        syncCysDefectUnit('merma',id,field);
                        return;
                    }
                    var amtInp = document.getElementById('merma_amt_' + id);
                    var kgsInp = document.getElementById('merma_kgs_' + id);
                    var mtsInp = document.getElementById('merma_mts_' + id);
                    if (!amtInp || !kgsInp || !mtsInp) return;

                    var amt = parseFloat(amtInp.value) || 0;
                    var kgs = parseFloat(kgsInp.value) || 0;
                    var mts = parseFloat(mtsInp.value) || 0;

                    if (field === 'amount' && amt > 0) {
                        if (PROD_UNIT_WEIGHT_KG > 0 && (!kgs || kgs === 0)) {
                            kgsInp.value = (amt * PROD_UNIT_WEIGHT_KG).toFixed(2);
                        }
                    } else if (field === 'mts' && mts > 0) {
                        if (PROD_METER_WEIGHT_KG > 0 && (!kgs || kgs === 0)) {
                            kgsInp.value = (mts * PROD_METER_WEIGHT_KG).toFixed(2);
                        }
                    } else if (field === 'kgs' && kgs > 0) {
                        if (IS_FLEXO && PROD_METER_WEIGHT_KG > 0 && (!mts || mts === 0)) {
                            mtsInp.value = Math.round(kgs / PROD_METER_WEIGHT_KG);
                        } else if (!IS_FLEXO && PROD_UNIT_WEIGHT_KG > 0 && (!amt || amt === 0)) {
                            amtInp.value = Math.round(kgs / PROD_UNIT_WEIGHT_KG);
                        }
                    }
                }

                function syncCysDefectUnit(prefix,id,field){
                    const unit=document.getElementById(prefix+'_unit_'+id);
                    if (!unit) return;
                    const form=unit.form;
                    const amount=form.elements[prefix+'_amount_'+id];
                    const kgs=form.elements[prefix+'_kgs_'+id];
                    if (!amount||!kgs) return;
                    if (field==='amount'||field==='kgs') unit.value=field;
                    if (PROD_UNIT_WEIGHT_KG>0) {
                        if (unit.value==='amount') kgs.value=((Number(amount.value)||0)*PROD_UNIT_WEIGHT_KG).toFixed(2);
                        else amount.value=Math.round((Number(kgs.value)||0)/PROD_UNIT_WEIGHT_KG);
                    }
                }

                if (document.getElementById('idx_seri_bastidor_multiplier')) {
                    calcSeriUnitsFromCounters();
                }
                </script>
            </div>
            <?php if ($showClosingSidePanel): ?>
                <div class="closing-side-panel">
                    <?php renderProductionClosingRightPanel($agenda, $currentRefEvt, $refId, $valAmount, $overrideEmbalaje, $isSelladora); ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>

    <?php elseif ($mode === 'apertura'): ?>
        <!-- SUBFORMULARIO: Apertura / Alistamiento de Medida (form.apertura.php) -->
        <?php
        $currentRefEvt = null;
        if ($refId > 0) {
            foreach ($events as $ev) {
                if ((int)$ev['id'] === $refId) {
                    $currentRefEvt = $ev;
                    break;
                }
            }
        }
        $isEvtOpen = ($currentRefEvt !== null && (int)($currentRefEvt['evt_enddat'] ?? 0) === 0);
        $savedComments = (string)($currentRefEvt['evt_comments'] ?? '');
        $savedMedFrom = (int)($currentRefEvt['evt_medida_fromid'] ?? 0);
        $savedMedTo = (int)($currentRefEvt['evt_medida_toid'] ?? 0);
        $savedAyudante = (int)($currentRefEvt['evt_idayudante'] ?? 0);
        ?>
        <?php if ($refId > 0 && !$isEvtOpen): ?>
            <script>location.href = '/production/work-orders/operate?agid=<?=$agId?>';</script>
        <?php else: ?>
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver al listado de eventos
            </div>
        </div>
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <div style="font-size:16px;font-weight:bold;color:#E1A500;margin-bottom:15px">
                <i class="fa fa-fw fa-wrench"></i> <?=($refId > 0 ? ($isEvtOpen ? 'Terminar Alistamiento / Ir a Autocontrol' : 'Detalle de Alistamiento') : 'Registrar Alistamiento')?>
            </div>
            <form method="post" action="/production/work-orders/events/apertura" id="form_apertura_edit" onsubmit="return validateAperturaEditSubmit();">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <input type="hidden" name="refid" value="<?=$refId?>">
                <input type="hidden" name="submode" id="ap_submode" value="">

                <table border="0" width="100%" cellpadding="8" cellspacing="0">
                <colgroup><col width="180"><col></colgroup>
                <tr>
                    <td class="tdleft">Tipo</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)($machineInit['type_ant_title'] ?? $agenda['type_ant_title'] ?? '-'))?></b></td>
                </tr>
                <tr>
                    <td class="tdleft">Máquina</td>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)($machineInit['equipo_name'] ?? $agenda['equipo_name'] ?? '-'))?></b></td>
                </tr>
                <?php if ($isSelladora || !empty($machineInit['type_ant_inpmedidas_act'])): ?>
                    <tr>
                        <td class="tdleft">De Medida</td>
                        <td class="tdnrm">
                            <select name="evt_medida_fromid" required class="inptxt" style="width:300px;background:#fff">
                                <option value="">Seleccione medida actual...</option>
                                <?php foreach ($medidas as $m): ?>
                                    <option value="<?=$m['id']?>" <?=$savedMedFrom === (int)$m['id'] ? 'selected' : ''?>><?=htmlspecialchars((string)$m['med_name'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">A Medida</td>
                        <td class="tdnrm">
                            <select name="evt_medida_toid" required class="inptxt" style="width:300px;background:#fff">
                                <option value="">Seleccione nueva medida...</option>
                                <?php foreach ($medidas as $m): ?>
                                    <option value="<?=$m['id']?>" <?=$savedMedTo === (int)$m['id'] ? 'selected' : ''?>><?=htmlspecialchars((string)$m['med_name'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td class="tdleft">Ayudante</td>
                    <td class="tdnrm">
                        <select name="evt_idayudante" class="inptxt" style="width:300px;background:#fff">
                            <option value="0">&lt; Sin ayudante asignado &gt;</option>
                            <?php foreach ($helpers as $h): ?>
                                <option value="<?=$h['id']?>" <?=$savedAyudante === (int)$h['id'] ? 'selected' : ''?>><?=htmlspecialchars((string)$h['ayudantes'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft" valign="top">Comentarios</td>
                    <td class="tdnrm"><textarea name="evt_comments" id="evt_comments" class="inptxt" style="width:100%;height:65px" placeholder="Detalles de alistamiento..."><?=htmlspecialchars($savedComments)?></textarea></td>
                </tr>
                </table>
                <div style="clear:both;height:10px"></div>
                <?php if ($refId > 0): ?>
                    <table border="0" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="50%" style="padding-right:5px">
                            <div class="btngrey" onclick="location.href = '/production/work-orders/operate?agid=<?=$agId?>';" style="float:left;width:40%;cursor:pointer;text-align:center">
                                <i class="fa fa-fw fa-chevron-left" style="color:white;"></i> Volver&nbsp;
                            </div>
                        </td>
                        <td width="50%" style="padding-left:5px" align="right">
                            <?php if ($isEvtOpen): ?>
                                <div class="btnorange" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=autocontrol&end_alis=1';" style="margin-left:5px;float:right;cursor:pointer;text-align:center;padding:8px 16px;font-weight:bold;border-radius:8px;box-shadow:0 1px 3px rgba(15,23,42,0.16)">
                                    <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar
                                </div>
                                <div class="btngrey" onclick="document.getElementById('ap_submode').value='update';document.getElementById('form_apertura_edit').requestSubmit();" style="margin-left:5px;float:right;cursor:pointer;text-align:center;padding:8px 14px">
                                    <i class="fa fa-fw fa-save" style="color:white;"></i> Guardar
                                </div>
                            <?php else: ?>
                                <div class="btngreen" onclick="document.getElementById('ap_submode').value='update';document.getElementById('form_apertura_edit').requestSubmit();" style="width:40%;cursor:pointer;text-align:center">
                                    <i class="fa fa-fw fa-save" style="color:white;"></i> Guardar&nbsp;
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    </table>
                <?php else: ?>
                    <div class="btngreen" onclick="document.getElementById('ap_submode').value='start';document.getElementById('form_apertura_edit').requestSubmit();" style="cursor:pointer;display:inline-block">
                        <i class="fa fa-fw fa-play" style="color:white;"></i> Iniciar&nbsp;
                    </div>
                <?php endif; ?>
                    </td>
                </tr>
                </table>
            </form>

            <script>
            function validateAperturaEditSubmit() {
                var submode = document.getElementById('ap_submode').value;
                if (submode === 'end_and_autocontrol') {
                    var chks = document.querySelectorAll('.workerautochk_ap');
                    var allChecked = true;
                    chks.forEach(function(c) {
                        if (!c.checked) allChecked = false;
                    });
                    if (chks.length > 0 && !allChecked) {
                        alert('Debe seleccionar todos los puntos de autocontrol antes de ir a autocontrol.');
                        return false;
                    }
                }
                return true;
            }
            </script>
        </div>
        <?php endif; ?>

    <?php elseif ($mode === 'mantencion'): ?>
        <!-- SUBFORMULARIO: Mantención (form.mantencion.php) -->
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver al listado de eventos
            </div>
        </div>
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <div style="font-size:16px;font-weight:bold;color:#D64141;margin-bottom:15px">
                <i class="fa fa-fw fa-tools"></i> <?=($refId > 0 ? 'Terminar Mantención' : 'Registrar Mantención')?>
            </div>
            <form method="post" action="/production/work-orders/events/mantencion">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <input type="hidden" name="refid" value="<?=$refId?>">
                <input type="hidden" name="submode" id="mant_submode" value="">

                <table border="0" width="100%" cellpadding="8" cellspacing="0">
                <colgroup><col width="220"><col></colgroup>
                <tr>
                    <td class="tdleft">Tipo Mantención</td>
                    <td class="tdnrm">
                        <select name="evt_equipo_mantid" class="inptxt" style="width:250px" required>
                            <option value="">Seleccione tipo...</option>
                            <?php foreach ($repairs as $r): ?>
                                <option value="<?=$r['id']?>"><?=htmlspecialchars((string)$r['mant_title'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft">Comentarios / Causa</td>
                    <td class="tdnrm"><textarea name="evt_comments" class="inptxt" style="width:100%;height:60px" placeholder="Descripción de la mantención o falla..."></textarea></td>
                </tr>
                <tr>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">
                        <button type="submit" class="btnred" style="font-size:14px;padding:8px 20px" onclick="document.getElementById('mant_submode').value='start'">
                            <i class="fa fa-fw fa-play"></i> Iniciar Mantención
                        </button>
                        <?php if ($refId > 0): ?>
                            <button type="submit" class="btngreen" style="font-size:14px;padding:8px 20px;margin-left:10px" onclick="document.getElementById('mant_submode').value='end'">
                                <i class="fa fa-fw fa-check"></i> Terminar Mantención
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                </table>
            </form>
        </div>

    <?php elseif ($mode === 'pause'): ?>
        <?php
        $refEvent = null;
        if ($refId > 0) {
            foreach ($events as $ev) {
                if ((int)$ev['id'] === $refId) {
                    $refEvent = $ev;
                    break;
                }
            }
        }
        $selectedPauseId = $refEvent ? (int)($refEvent['evt_pause_id'] ?? 0) : (int)($_GET['pause_id'] ?? 0);
        $pauseComments = $refEvent ? (string)($refEvent['evt_comments'] ?? '') : '';
        $isPauseEnded = $refEvent && !empty($refEvent['evt_enddat']) && (int)$refEvent['evt_enddat'] > 0;
        ?>
        <!-- SUBFORMULARIO: Pausa (form.pause.php) -->
        <div style="margin-bottom:15px">
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver al listado de eventos
            </div>
        </div>
        <div style="background:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <div style="font-size:16px;font-weight:bold;color:#666666;margin-bottom:15px">
                <i class="fa fa-fw fa-coffee"></i> <?=($refId > 0 ? ($isPauseEnded ? 'Detalle de Pausa' : 'Terminar Pausa') : 'Registrar Pausa')?>
            </div>
            <form method="post" action="/production/work-orders/events/pause">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                <input type="hidden" name="refid" value="<?=$refId?>">
                <input type="hidden" name="submode" id="pause_submode" value="<?=$refId > 0 && !$isPauseEnded ? 'end' : ''?>">

                <table border="0" width="100%" cellpadding="8" cellspacing="0">
                <colgroup><col width="220"><col></colgroup>
                <tr>
                    <td class="tdleft">Motivo de Pausa</td>
                    <td class="tdnrm">
                        <select name="evt_pause_id" class="inptxt" style="width:350px" required>
                            <option value="">Seleccione motivo...</option>
                            <?php foreach ($pauses as $p): ?>
                                <option value="<?=$p['id']?>" <?=((int)$p['id'] === $selectedPauseId ? 'selected' : '')?>><?=htmlspecialchars((string)$p['pause_name'])?><?=!empty($p['pause_code']) ? ' (Código ' . htmlspecialchars((string)$p['pause_code']) . ')' : ''?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="tdleft">Comentarios</td>
                    <td class="tdnrm"><textarea name="evt_comments" class="inptxt" style="width:100%;height:60px" placeholder="Observaciones..."><?=htmlspecialchars($pauseComments)?></textarea></td>
                </tr>
                <tr>
                    <td class="tdleft">&nbsp;</td>
                    <td class="tdnrm">
                        <?php if ($refId <= 0): ?>
                            <button type="submit" class="btngreen" style="font-size:14px;padding:10px 24px;border:none;cursor:pointer" onclick="document.getElementById('pause_submode').value='start'">
                                <i class="fa fa-fw fa-play"></i> Iniciar Pausa
                            </button>
                            <button type="button" class="btnblue" style="font-size:14px;padding:10px 20px;border:none;cursor:pointer;background:<?=$activeLunch !== null ? '#D97706' : '#0284C7'?>;margin-left:10px" onclick="openLunchModal()" title="Colación">
                                <i class="fa fa-coffee"></i> <?=$activeLunch !== null ? 'Terminar Colación' : 'Colación'?>
                            </button>
                        <?php elseif (!$isPauseEnded): ?>
                            <button type="submit" class="btnorange" style="font-size:14px;padding:10px 24px;border:none;cursor:pointer" onclick="document.getElementById('pause_submode').value='end'">
                                <i class="fa fa-fw fa-stop"></i> Terminar Pausa
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btngreen" style="font-size:14px;padding:10px 24px;border:none;cursor:pointer">
                                <i class="fa fa-fw fa-save"></i> Guardar Cambios
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                </table>
            </form>
        </div>


    <?php elseif ($mode === 'tintas' || $mode === 'materiales' || $mode === 'consumo'): ?>
        <?php renderProductionMaterialsSubformSection(get_defined_vars()); ?>
    <?php elseif ($mode === 'reboprod'): ?>
        <?php renderRewindConsoleSubformSection(get_defined_vars()); ?>
    <?php elseif ($mode === 'embalaje'): ?>
        <?php renderSealingPackagingSubformSection(get_defined_vars()); ?>
    <?php elseif ($mode === 'semielaborado'): ?>
        <?php renderFlexoSemielaboradoSubformSection(get_defined_vars()); ?>
    <?php elseif ($mode === 'terminarot'): ?>
        <?php renderProductionClosureSubformSection(get_defined_vars()); ?>
    <?php endif; ?>

    <?php if ($mode === '' && !$showSupervisorApproval && ($hasApertura || $hasAlis || $isRebobinadora)): ?>
        <!-- ============================================================== -->
        <!-- BARRA DE BOTONES DE ACCIÓN (editxid.php líneas 4647 - 4780)    -->
        <!-- Visible en Alistamiento, en Producción y en Pulpo/Serigrafía    -->
        <!-- ============================================================== -->
        <?php
        $isBarExpanded = ($hasAlis || $isRebobinadora);
        $autoBarColWidth = $isFlexo ? '20%' : '25%';
        $actionBtnBaseStyle = 'border-radius:8px;padding:10px 14px;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;min-height:42px;box-shadow:0 1px 3px rgba(15,23,42,0.16);cursor:pointer';
        $actionBtnBlockStyle = $actionBtnBaseStyle . ($isBarExpanded ? '' : 'width:100%;box-sizing:border-box;text-align:center;display:flex;');
        $materialsActionLabel = $isRebobinadora ? 'Bobina' : (($isSeri || $isSeriPulpo) ? 'Insumos' : 'Materiales');
        ?>
        <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px;margin-bottom:10px">
        <tr>
            <?php if (!$hasAlis && $hasApertura && !$isRebobinadora): ?>
                <td style="padding-right:4px">
                    <div class="btnorange" style="<?=$actionBtnBaseStyle?>;background:#E1A500" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=autocontrol&end_alis=1'">
                        <nobr><i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar Alistamiento&nbsp;</nobr>
                    </div>
                </td>
            <?php endif; ?>
            <?php if ($isRebobinadora): ?>
                <td style="padding-right:4px">
                    <div class="btngreen" style="<?=$actionBtnBaseStyle?>" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=reboprod'">
                        <nobr><i class="fa fa-fw fa-cogs" style="color:white;"></i> Rebobinado&nbsp;</nobr>
                    </div>
                </td>
            <?php elseif ($isSelladora && $hasAlis): ?>
                <?php foreach ([0 => ['Corte y Sellado', $openProdEvent], 1 => ['Embalaje', $openPackagingEvent]] as $phaseKind => [$phaseLabel, $phaseEvent]): ?>
                    <td style="padding-right:4px">
                        <a class="<?=$phaseEvent === null ? 'btngreen' : 'btnorange'?>" style="text-decoration:none;<?=$actionBtnBaseStyle?>"
                           href="/production/work-orders/operate?agid=<?=$agId?>&amp;mode=production&amp;overrideembalaje=<?=$phaseKind?><?=$phaseEvent === null ? '&amp;action=start' : '&amp;refid=' . (int)$phaseEvent['id'] . '&amp;submode=end'?>">
                            <i class="fa fa-fw <?=$phaseEvent === null ? 'fa-play' : 'fa-stop'?>"></i>
                            <?=$phaseEvent === null ? 'Iniciar ' : 'Finalizar '?><?=$phaseLabel?>
                        </a>
                        <?php if ($phaseEvent !== null): ?>
                            <div style="font-size:12px;padding-top:5px">
                                Desde <?=date('H:i:s', (int)$phaseEvent['evt_crtdat'])?>
                                <?php $phaseReceiver = $phaseResponsibilities[(int)$phaseEvent['id']]['receiver_name'] ?? ''; ?>
                                <?php if ($phaseReceiver !== ''): ?><br>Recibe: <?=htmlspecialchars($phaseReceiver)?><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            <?php elseif ($openProdId > 0): ?>
                <td style="padding-right:4px">
                    <div class="btnorange" style="<?=$actionBtnBaseStyle?>" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=production&refid=<?=$openProdId?>&submode=end'">
                        <nobr><i class="fa fa-fw fa-stop" style="color:white;"></i> Finalizar&nbsp;</nobr>
                    </div>
                </td>
            <?php elseif ($hasAlis): ?>
                <td style="padding-right:4px">
                    <div class="btngreen" style="<?=$actionBtnBaseStyle?>" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=production&action=start'">
                        <nobr><i class="fa fa-fw fa-play" style="color:white;"></i> Iniciar&nbsp;</nobr>
                    </div>
                </td>
                <?php if ($isSelladora): ?>
                    <td style="padding-right:4px">
                        <?php renderStartPackagingButton($agId, $workerOtId, $actionBtnBaseStyle); ?>
                    </td>
                <?php endif; ?>
            <?php endif; ?>

            <td style="<?=$isBarExpanded ? 'padding-right:4px;padding-left:4px' : 'padding-right:2px;width:' . $autoBarColWidth?>">
                <div class="btnblue" style="<?=$actionBtnBlockStyle?>;background-color:#293865" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=materiales'">
                    <nobr><i class="fa fa-fw <?= $isRebobinadora ? 'fa-dolly' : 'fa-cubes' ?>" style="color:white;"></i> <?=$materialsActionLabel?>&nbsp;</nobr>
                </div>
            </td>

            <?php if ($isFlexo): ?>
                <td style="<?=$isBarExpanded ? 'padding-right:4px;padding-left:4px' : 'padding:0 2px;width:20%'?>">
                    <div class="btnblue" style="<?=$actionBtnBlockStyle?>;background-color:#1D4ED8" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=tintas'">
                        <nobr><i class="fa fa-fw fa-tint" style="color:white;"></i> Tintas&nbsp;</nobr>
                    </div>
                </td>
            <?php endif; ?>

            <td style="<?=$isBarExpanded ? 'padding-right:4px;padding-left:4px' : 'padding:0 2px;width:' . $autoBarColWidth?>">
                <div class="btnred" style="<?=$actionBtnBlockStyle?>;background-color:#D64141" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=mantencion'">
                    <nobr><i class="fa fa-fw fa-tools" style="color:white;"></i> Mantención&nbsp;</nobr>
                </div>
            </td>

            <?php if ($openPauseId > 0): ?>
                <td style="<?=$isBarExpanded ? 'padding-right:4px;padding-left:4px' : 'padding:0 2px;width:' . $autoBarColWidth?>">
                    <div class="btnorange" style="<?=$actionBtnBlockStyle?>;background-color:#E1A500" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=pause&refid=<?=$openPauseId?>'">
                        <nobr><i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar Pausa&nbsp;</nobr>
                    </div>
                </td>
            <?php else: ?>
                <td style="<?=$isBarExpanded ? 'padding-right:4px;padding-left:4px' : 'padding:0 2px;width:' . $autoBarColWidth?>">
                    <div class="btngrey" style="<?=$actionBtnBlockStyle?>;background-color:#7F7F7F" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=pause'">
                        <nobr><i class="fa fa-fw fa-coffee" style="color:white;"></i> Pausa&nbsp;</nobr>
                    </div>
                </td>
            <?php endif; ?>

            <!-- BOTÓN COLACIÓN DISPONIBLE EN TODAS LAS MÁQUINAS -->
            <td style="<?=$isBarExpanded ? 'padding-right:4px;padding-left:4px' : 'padding-left:2px;width:' . $autoBarColWidth?>">
                <?php if ($activeLunch !== null): ?>
                    <div class="btnorange" style="<?=$actionBtnBlockStyle?>;background-color:#D97706;font-weight:bold" onclick="openLunchModal()" title="Colación activa">
                        <nobr><i class="fa fa-fw fa-coffee" style="color:white;"></i> EN COLACIÓN&nbsp;</nobr>
                    </div>
                <?php else: ?>
                    <div class="btnblue" style="<?=$actionBtnBlockStyle?>;background-color:#0284C7" onclick="openLunchModal()" title="Registrar horario de colación en tiempo real">
                        <nobr><i class="fa fa-fw fa-coffee" style="color:white;"></i> Colación&nbsp;</nobr>
                    </div>
                <?php endif; ?>
            </td>

        </tr>
        </table>
    <?php endif; ?>

    <!-- ============================================================== -->
    <!-- TABLA DE EVENTOS DE LA OT (editxid.php líneas 4785 - 4970)     -->
    <!-- SIEMPRE VISIBLE EN TODOS LOS ESTADOS (Alistamiento, Prod, etc) -->
    <!-- ============================================================== -->
    <div style="clear:both;height:12px"></div>
    <table border="0" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
            <div style="font-size:15px;font-weight:bold;color:#115452;margin-bottom:12px">
                <i class="fa fa-fw fa-list-alt"></i> Registro de Eventos de la OT
            </div>
            <table border="0" width="100%" cellpadding="6" cellspacing="0">
            <colgroup>
                <col width="140">
                <col width="160">
                <col width="160">
                <col width="90">
                <col width="120">
                <col>
                <col width="160">
            </colgroup>
            <thead>
                <tr>
                    <td class="tdheader">Evento</td>
                    <td class="tdheader">Inicio</td>
                    <td class="tdheader">Término</td>
                    <td class="tdheader" align="center">Tiempo</td>
                    <td class="tdheader" align="center">Cantidad</td>
                    <td class="tdheader">Comentarios</td>
                    <td class="tdheader" align="center">Opciones</td>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($events)): ?>
                <tr><td colspan="7" class="tdnrm" style="text-align:center;padding:20px;color:#999">No hay eventos registrados aún en esta orden.</td></tr>
            <?php else: ?>
                <?php foreach ($events as $ev): ?>
                    <?php
                    $evttype = match($ev['evt_type']) {
                        'prod', 'prodsericolor' => ((int)($ev['overrideembalaje_act'] ?? 0) === 1 ? 'Prod. Embalaje' : ($isSelladora ? 'Corte y Sellado' : 'Producción')),
                        'apertura' => 'Alistamiento',
                        'merma' => 'Merma de Bobina',
                        'mantencion' => !empty($ev['repair_name']) ? 'Mantención: ' . htmlspecialchars((string)$ev['repair_name']) : 'Mantención',
                        'pause' => !empty($ev['pause_name']) ? 'Pausa: ' . htmlspecialchars((string)$ev['pause_name']) : 'Pausa',
                        default => ucfirst((string)$ev['evt_type'])
                    };

                    $start = (int)$ev['evt_crtdat'];
                    $end = (int)($ev['evt_enddat'] ?? 0);
                    $elapsed = ($end > 0 ? $end : time()) - $start;
                    $hours = (int)($elapsed / 3600);
                    $mins = (int)(($elapsed % 3600) / 60);

                    $amtStr = '-';
                    if (!empty($ev['evt_metrotype'])) {
                        if ($ev['evt_metrotype'] === 'metros_maquina') {
                            $amtStr = $fmt($ev['evt_amount_metros_maquina']) . ' m maq';
                        } else {
                            $amtStr = $fmt($ev['evt_amount_metros_lineales']) . ' m lin';
                        }
                        if ((float)($ev['prod_bobina_kg'] ?? 0) > 0) {
                            $amtStr .= '<br><span style="font-size:11px;color:#15803D;font-weight:bold">' . number_format((float)$ev['prod_bobina_kg'], 2, ',', '.') . ' kg</span>';
                        }
                    } elseif ($ev['evt_type'] === 'materiales') {
                        if ((float)($ev['prod_bobina_kg'] ?? 0) > 0) {
                            $amtStr = '<span style="font-size:11px;color:#15803D;font-weight:bold">' . number_format((float)$ev['prod_bobina_kg'], 2, ',', '.') . ' kg</span>';
                        } elseif ((float)($ev['evt_amount'] ?? 0) > 0) {
                            $amtStr = $fmt($ev['evt_amount'] ?? 0) . ' unid';
                        }
                    } elseif ((float)($ev['evt_amount'] ?? 0) > 0 || (float)($ev['evt_amount_pulpo_frente'] ?? 0) > 0 || (float)($ev['evt_amount_pulpo_dorso'] ?? 0) > 0) {
                        if ($ev['evt_type'] === 'merma') {
                            $amtStr = number_format((float)($ev['evt_amount'] ?? 0), 2, ',', '.') . ' kg';
                        } else {
                            $amtStr = $fmt($ev['evt_amount'] ?? 0) . ' bols';
                            if ((float)($ev['prod_bobina_kg'] ?? 0) > 0) {
                                $amtStr .= '<br><span style="font-size:11px;color:#15803D;font-weight:bold">' . number_format((float)$ev['prod_bobina_kg'], 2, ',', '.') . ' kg</span>';
                            }
                            if ((float)($ev['evt_amount_pulpo_frente'] ?? 0) > 0 || (float)($ev['evt_amount_pulpo_dorso'] ?? 0) > 0) {
                                $amtStr .= '<br><span style="font-size:11px;color:#64748B;font-weight:normal">(F: ' . $fmt($ev['evt_amount_pulpo_frente'] ?? 0) . ', D: ' . $fmt($ev['evt_amount_pulpo_dorso'] ?? 0) . ')</span>';
                            }
                        }
                    } elseif ((float)($ev['prod_bobina_kg'] ?? 0) > 0) {
                        $amtStr = '<span style="font-size:11px;color:#15803D;font-weight:bold">' . number_format((float)$ev['prod_bobina_kg'], 2, ',', '.') . ' kg</span>';
                    }
                    $targetMode = match($ev['evt_type']) {
                        'prod', 'merma' => 'production',
                        default => $ev['evt_type']
                    };
                    $isDurational = in_array($ev['evt_type'], ['prod', 'prodsericolor', 'apertura', 'pause', 'mantencion'], true);
                    $isSemiRoll = (strpos((string)($ev['evt_comments'] ?? ''), 'Bobina Semielaborada') !== false);
                    if ($isSemiRoll) {
                        $isDurational = false;
                    }
                    $isCompleted = (!$isDurational) || ($end > 0) || ((int)($ev['evt_status'] ?? 0) === 2);
                    ?>
                    <tr>
                        <td class="tdnrm"><b><?=$evttype?></b></td>
                        <td class="tdnrm"><?=date('d.m.Y H:i:s', $start)?></td>
                        <td class="tdnrm">
                            <?php if (!$isCompleted): ?>
                                <span style="color:#00A85A;font-weight:bold"><i class="fa fa-clock"></i> En curso</span>
                            <?php else: ?>
                                <?=date('d.m.Y H:i:s', $end > 0 ? $end : $start)?>
                            <?php endif; ?>
                        </td>
                        <td class="tdnrm" align="center"><?=$isDurational ? ($hours . 'h ' . $mins . 'm') : '-'?></td>
                        <td class="tdnrm" align="center"><b><?=$amtStr?></b></td>
                        <td class="tdnrm">
                            <?php $phaseResponsible = $phaseResponsibilities[(int)$ev['id']] ?? []; ?>
                            <?php if ($phaseResponsible !== []): ?>
                                <?php if (!empty($phaseResponsible['receiver_name'])): ?>
                                    <b>Recibe: <?=htmlspecialchars((string)$phaseResponsible['receiver_name'])?></b><br>
                                <?php endif; ?>
                                Inicio: <?=htmlspecialchars((string)$phaseResponsible['started_by_name'])?><br>
                                <?php if ((int)$phaseResponsible['ended_by_worker_id'] > 0): ?>
                                    Finalizo: <?=htmlspecialchars((string)$phaseResponsible['ended_by_name'])?><br>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($ev['evt_type'] === 'apertura'): ?>
                                <?php
                                $mFrom = (int)($ev['evt_medida_fromid'] ?? 0);
                                $mTo = (int)($ev['evt_medida_toid'] ?? 0);
                                $fromName = $medidasMap[$mFrom] ?? '';
                                $toName = $medidasMap[$mTo] ?? '';
                                $aid = (int)($ev['evt_idayudante'] ?? 0);
                                $ayudanteName = $helpersMap[$aid] ?? '';
                                ?>
                                <?php if ($fromName !== '' || $toName !== ''): ?>
                                    <span style="font-weight:bold;color:#115452">De <?=htmlspecialchars($fromName ?: '-')?> a <?=htmlspecialchars($toName ?: '-')?></span><br>
                                <?php endif; ?>
                                <?php if ($ayudanteName !== ''): ?>
                                    <span style="color:#555"><i class="fa fa-user-friends"></i> Ayudante: <?=htmlspecialchars($ayudanteName)?></span><br>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?=htmlspecialchars(renderSimpleProductionComment((string)($ev['evt_comments'] ?? '')))?>
                        </td>
                        <td class="tdnrm" align="center">
                            <?php if (!$isCompleted): ?>
                                <?php if ($ev['evt_type'] === 'apertura'): ?>
                                    <div class="btnorange" style="padding:4px 10px;font-size:12px;display:inline-block;cursor:pointer"
                                         onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=autocontrol&end_alis=1'">
                                        <i class="fa fa-stop"></i> Terminar
                                    </div>
                                <?php elseif (in_array($ev['evt_type'], ['prod', 'prodsericolor'], true)): ?>
                                    <div class="btnorange" style="padding:4px 10px;font-size:12px;display:inline-block;cursor:pointer"
                                         onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=production&refid=<?=$ev['id']?>&submode=end'">
                                        <i class="fa fa-stop"></i> Finalizar
                                    </div>
                                <?php elseif ($ev['evt_type'] === 'pause'): ?>
                                    <div class="btnorange" style="padding:4px 10px;font-size:12px;display:inline-block;cursor:pointer"
                                         onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=pause&refid=<?=$ev['id']?>'">
                                        <i class="fa fa-stop"></i> Terminar Pausa
                                    </div>
                                <?php elseif ($ev['evt_type'] === 'mantencion'): ?>
                                    <div class="btnorange" style="padding:4px 10px;font-size:12px;display:inline-block;cursor:pointer"
                                         onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=mantencion&refid=<?=$ev['id']?>'">
                                        <i class="fa fa-stop"></i> Terminar Mantención
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="display:inline-flex;align-items:center;gap:6px;justify-content:center">
                                <?php if (in_array($ev['evt_type'], ['prod', 'prodsericolor'], true) && !$isSemiRoll): ?>
                                    <div class="btngrey" style="padding:4px 8px;font-size:11.5px;display:inline-flex;align-items:center;gap:4px;cursor:pointer"
                                         onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>&mode=production&refid=<?=$ev['id']?>'">
                                        <i class="fa fa-edit"></i> Ver
                                    </div>
                                <?php else: ?>
                                    <span style="color:#94A3B8;font-size:11px"><i class="fa fa-lock"></i> Registrado</span>
                                <?php endif; ?>
                                <?php if (!$isSemiRoll): ?>
                                    <button type="button" class="btnred" style="padding:4px 8px;font-size:11.5px;font-weight:bold;cursor:pointer;border:none;border-radius:4px;display:inline-flex;align-items:center;gap:4px;box-shadow:0 1px 2px rgba(0,0,0,.1)"
                                            onclick="openDeleteProductionModal(<?= (int)$ev['id'] ?>, <?= (int)$workerOtId ?>, <?= htmlspecialchars(json_encode($evttype), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode(trim(strip_tags((string)$amtStr))), ENT_QUOTES) ?>)"
                                            title="Borrar registro de producción (Solo Javier y Héctor)">
                                        <i class="fa fa-trash"></i> Borrar
                                    </button>
                                <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            </table>
        </td>
    </tr>
    </table>

    <!-- Modal para Borrar Registro de Producción (Exclusivo Javier y Héctor) -->
    <div id="modal_delete_prod_event" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.65);backdrop-filter:blur(4px);z-index:99999;align-items:center;justify-content:center;padding:16px;">
        <div style="background:#fff;width:min(480px, 98%);border-radius:12px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.3);border:1px solid #fee2e2;overflow:hidden">
            <div style="background:#b91c1c;color:#fff;padding:14px 18px;display:flex;justify-content:space-between;align-items:center;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fa fa-trash" style="font-size:18px"></i>
                    <div>
                        <div style="font-size:15px;font-weight:800">Borrar Registro de Producción</div>
                        <div style="font-size:11px;color:#fecaca">Autorizado únicamente para Javier Perozo y Héctor Matus</div>
                    </div>
                </div>
                <button type="button" onclick="closeDeleteProductionModal()" style="background:transparent;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1">&times;</button>
            </div>
            
            <form method="post" action="/production/work-orders/events/delete" style="margin:0">
                <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="agid" value="<?= $agId ?>">
                <input type="hidden" name="worker_ot_id" id="modal_del_worker_ot_id" value="<?= $workerOtId ?>">
                <input type="hidden" name="event_id" id="modal_del_event_id" value="">
                
                <div style="padding:18px 20px;">
                    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;margin-bottom:14px;">
                        <p style="margin:0;font-size:12.5px;color:#991b1b;font-weight:600">
                            ¿Estás seguro de eliminar este registro de producción?
                        </p>
                        <p style="margin:4px 0 0;font-size:11.5px;color:#b91c1c;line-height:1.4">
                            Esta acción revertirá las cantidades y consumos automáticos asociados a este evento.
                        </p>
                    </div>

                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;font-size:12.5px;display:flex;flex-direction:column;gap:6px;margin-bottom:14px">
                        <div><strong style="color:#64748b">Tipo:</strong> <span id="del_modal_evttype" style="font-weight:700;color:#0f172a">-</span></div>
                        <div><strong style="color:#64748b">Cantidad / Materia:</strong> <span id="del_modal_amt" style="font-weight:700;color:#0f172a">-</span></div>
                    </div>

                    <?php if (unibagCanUserPerformModifications()): ?>
                        <div style="background:#EFF6FF;border:1px solid #BFDBFE;padding:10px 14px;border-radius:8px;font-size:12px;color:#1E40AF">
                            <i class="fa fa-check-circle"></i> Sesión autorizada como <b><?= htmlspecialchars($operatorName ?: 'Supervisor') ?></b> (Javier / Héctor). No se requiere segunda clave.
                        </div>
                    <?php else: ?>
                        <div style="background:#FFFBEB;border:1px solid #FCD34D;padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:11.5px;color:#92400E">
                            <i class="fa fa-lock"></i> <b>Autorización Requerida:</b> Debe ser autorizado con las credenciales de <b>Javier Perozo</b> o <b>Héctor Matus</b>.
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                            <div>
                                <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:3px">Usuario Supervisor *</label>
                                <input type="text" name="supervisor_user" class="inptxt" style="width:100%" placeholder="JAVIER o HECTOR" required autocomplete="off">
                            </div>
                            <div>
                                <label style="display:block;font-size:11.5px;font-weight:700;color:#334155;margin-bottom:3px">Contraseña *</label>
                                <input type="password" name="supervisor_pass" class="inptxt" style="width:100%" placeholder="••••••••" required autocomplete="new-password">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="background:#f8fafc;padding:12px 18px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" onclick="closeDeleteProductionModal()" style="padding:7px 14px;border-radius:6px;font-size:12.5px;font-weight:600;background:#e2e8f0;color:#334155;border:none;cursor:pointer">Cancelar</button>
                    <button type="submit" style="padding:7px 16px;border-radius:6px;font-size:12.5px;font-weight:700;background:#dc2626;color:#fff;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:5px;box-shadow:0 2px 4px rgba(220,38,38,.2)">
                        <i class="fa fa-trash"></i> Sí, Eliminar Registro
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openDeleteProductionModal(eventId, workerOtId, evtType, amtStr) {
        var m = document.getElementById("modal_delete_prod_event");
        if (!m) return;
        document.getElementById("modal_del_event_id").value = eventId;
        if (workerOtId > 0) {
            document.getElementById("modal_del_worker_ot_id").value = workerOtId;
        }
        document.getElementById("del_modal_evttype").textContent = evtType || "-";
        document.getElementById("del_modal_amt").textContent = amtStr || "-";
        m.style.display = "flex";
    }
    function closeDeleteProductionModal() {
        var m = document.getElementById("modal_delete_prod_event");
        if (m) m.style.display = "none";
    }
    </script>

    <script>
    function calcPulpoUnits() {
        var prevF = <?= (int)($prevFrente ?? 0) ?>;
        var prevD = <?= (int)($prevDorso ?? 0) ?>;
        var prevFinished = <?= (int)($prevFinished ?? 0) ?>;

        var fInp = document.getElementById('idx_pulpo_frente');
        var dInp = document.getElementById('idx_pulpo_dorso');
        var fNew = fInp && fInp.value !== '' ? parseInt(fInp.value, 10) : 0;
        var dNew = dInp && dInp.value !== '' ? parseInt(dInp.value, 10) : 0;
        if (isNaN(fNew) || fNew < 0) fNew = 0;
        if (isNaN(dNew) || dNew < 0) dNew = 0;

        var fCum = prevF + fNew;
        var dCum = prevD + dNew;
        var finishedCum = Math.min(fCum, dCum);
        var finishedThisEvent = Math.max(0, finishedCum - prevFinished);

        var amtInp = document.getElementById('idx_pulpo_amount');
        if (amtInp) {
            amtInp.value = finishedThisEvent > 0 ? finishedThisEvent : (fNew > 0 || dNew > 0 ? '0' : '');
        }

        var surplusEl = document.getElementById('pulpo_surplus_preview');
        if (surplusEl) {
            var remF = fCum - finishedCum;
            var remD = dCum - finishedCum;
            if (remF > 0) {
                surplusEl.innerHTML = '<span style="color:#D97706;font-weight:bold"><i class="fa fa-info-circle"></i> Al guardar este tiraje, quedarán <b>' + remF.toLocaleString('es-CL') + '</b> bolsas con frente impreso (pendientes de dorso).</span>';
            } else if (remD > 0) {
                surplusEl.innerHTML = '<span style="color:#D97706;font-weight:bold"><i class="fa fa-info-circle"></i> Al guardar este tiraje, quedarán <b>' + remD.toLocaleString('es-CL') + '</b> bolsas con dorso impreso (pendientes de frente).</span>';
            } else {
                surplusEl.innerHTML = '<span style="color:#059669;font-weight:bold"><i class="fa fa-check-circle"></i> Al guardar este tiraje, ambas caras quedan balanceadas (0 pendientes).</span>';
            }
        }
    }
    function calcPulpoUnitsSoloFrente() {
        var fInp = document.getElementById('idx_pulpo_frente');
        var f = fInp && fInp.value !== '' ? parseInt(fInp.value, 10) : 0;
        var amtInp = document.getElementById('idx_pulpo_amount');
        if (amtInp) {
            amtInp.value = f > 0 ? f : '';
        }
    }

    if (document.getElementById('idx_pulpo_frente') && document.getElementById('idx_pulpo_dorso')) {
        calcPulpoUnits();
    }
    </script>
    <?php
    echo renderDesignPreviewModal();
    renderStandbyAndLunchModals($activeStandby, $machineInit, $currentOperatorName, '/production/work-orders/operate?agid=' . $agId, $activeLunch);

