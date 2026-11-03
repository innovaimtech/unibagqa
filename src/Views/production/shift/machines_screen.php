<?php
    ?>
    <div style="font-size:20px;margin-bottom:10px"><b>Asignación máquina</b></div>

    <?php if (isset($_GET['lunch_saved'])): ?>
        <div style="background:#ECFDF5;border:1px solid #6EE7B7;color:#065F46;padding:12px 18px;border-radius:5px;margin-bottom:15px;font-size:13.5px">
            <i class="fa fa-check-circle" style="color:#059669;font-size:16px"></i> <b>Colación registrada exitosamente.</b> El horario ha quedado guardado sin necesidad de tener una OT en curso.
        </div>
    <?php elseif (isset($_GET['standby_started'])): ?>
        <div style="background:#FFFBEB;border:1px solid #FCD34D;color:#92400E;padding:12px 18px;border-radius:5px;margin-bottom:15px;font-size:13.5px">
            <i class="fa fa-pause-circle" style="color:#D97706;font-size:16px"></i> <b>Evento Sin Producción iniciado.</b> La máquina figura en estado de espera informada.
        </div>
    <?php elseif (isset($_GET['standby_ended'])): ?>
        <div style="background:#ECFDF5;border:1px solid #6EE7B7;color:#065F46;padding:12px 18px;border-radius:5px;margin-bottom:15px;font-size:13.5px">
            <i class="fa fa-play-circle" style="color:#059669;font-size:16px"></i> <b>Evento Sin Producción finalizado.</b> La máquina vuelve a estar disponible para operar.
        </div>
    <?php elseif (isset($_GET['machine_ended'])): ?>
        <div style="background:#F1F5F9;border:1px solid #CBD5E1;color:#334155;padding:12px 18px;border-radius:5px;margin-bottom:15px;font-size:13.5px">
            <i class="fa fa-check-circle" style="color:#64748B;font-size:16px"></i> Turno cerrado correctamente en la máquina.
        </div>
    <?php elseif (isset($_GET['error'])): ?>
        <div style="background:#FEF2F2;border:1px solid #FCA5A5;color:#991B1B;padding:12px 18px;border-radius:5px;margin-bottom:15px;font-size:13.5px">
            <i class="fa fa-exclamation-circle" style="color:#DC2626;font-size:16px"></i> <?=htmlspecialchars((string)$_GET['error'])?>
        </div>
    <?php endif; ?>

    <?php if ($activeStandby !== null): ?>
        <div style="background:#FFF7ED;border:1.5px solid #F97316;border-radius:6px;padding:12px 18px;margin-bottom:15px;color:#9A3412;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;box-shadow:0 1px 3px rgba(0,0,0,0.06)">
            <div style="display:flex;align-items:center;gap:12px">
                <i class="fa fa-pause-circle" style="font-size:26px;color:#EA580C"></i>
                <div>
                    <div style="font-weight:bold;font-size:14.5px;text-transform:uppercase;color:#C2410C">
                        MÁQUINA EN ESPERA / SIN PRODUCCIÓN: <?=htmlspecialchars((string)$activeStandby['reason'])?>
                    </div>
                    <div style="font-size:12.5px;color:#9A3412;margin-top:2px">
                        Iniciado a las <b><?=htmlspecialchars((string)$activeStandby['start_time'])?> hrs</b> por <b><?=htmlspecialchars((string)$activeStandby['operator_name'])?></b>
                        <?php if (!empty($activeStandby['comments'])): ?>
                            &bull; <i><?=htmlspecialchars((string)$activeStandby['comments'])?></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <button type="button" class="btnorange" style="background:#EA580C;padding:8px 18px;font-size:13px;font-weight:bold;cursor:pointer;border:none;border-radius:4px" onclick="openStandbyEndModal()">
                <i class="fa fa-play"></i> Finalizar Espera / Reanudar
            </button>
        </div>
    <?php endif; ?>

    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
    <tr>
        <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                <div>
                    <i class="fa fa-fw fa-laptop-code"></i> Asignación máquina
                    <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
                    <b>Iniciar / Terminar turno</b>
                </div>
                <?php if ($activeInit !== null): ?>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <span style="background:#E8F0EF;color:#115452;padding:6px 12px;border-radius:4px;font-weight:bold">
                            <i class="fa fa-check-circle" style="color:#00A85A"></i> Máquina Activa: <?=htmlspecialchars((string)$activeInit['equipo_name'])?>
                        </span>
                        <a href="/production/work-orders/new" class="btngreen" style="padding:6px 12px;text-decoration:none;border-radius:4px"><i class="fa fa-play"></i> Ir a OTs</a>
                        
                        <!-- BOTÓN: REGISTRAR COLACIÓN EN TIEMPO REAL -->
                        <?php if (!empty($activeLunch)): ?>
                            <button type="button" class="btnorange" style="background:#D97706;padding:6px 12px;cursor:pointer;border:none;font-weight:bold;border-radius:4px" onclick="openLunchModal()" title="Colación activa">
                                <i class="fa fa-coffee"></i> EN COLACIÓN (desde <?=date('H:i', strtotime($activeLunch['start_time']))?>) &mdash; Terminar
                            </button>
                        <?php else: ?>
                            <button type="button" class="btnblue" style="background:#0284C7;padding:6px 12px;cursor:pointer;border:none;border-radius:4px" onclick="openLunchModal()" title="Ingresar horario de colación en tiempo real">
                                <i class="fa fa-coffee"></i> Colación
                            </button>
                        <?php endif; ?>

                        <!-- BOTÓN: ESPERA / SIN PRODUCCIÓN -->
                        <?php if ($activeStandby !== null): ?>
                            <button type="button" class="btnorange" style="background:#EA580C;padding:6px 14px;cursor:pointer;border:none;font-weight:bold;border-radius:4px;display:inline-flex;align-items:center;gap:6px" onclick="openStandbyEndModal()" title="Finalizar estado sin producción">
                                <i class="fa fa-play-circle"></i> Finalizar Espera
                            </button>
                        <?php else: ?>
                            <button type="button" class="btnorange" style="background:#D97706;padding:6px 14px;cursor:pointer;border:none;font-weight:bold;border-radius:4px;display:inline-flex;align-items:center;gap:6px" onclick="openStandbyStartModal()" title="Informar máquina sin producción / espera de OT / mantención">
                                <i class="fa fa-pause-circle"></i> Espera / Sin Producción
                            </button>
                        <?php endif; ?>

                        <!-- BOTÓN 3: CERRAR TURNO -->
                        <form method="post" action="/production/machines/end" style="margin:0;display:inline-block">
                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                            <button type="submit" class="btnred" style="padding:6px 12px;border-radius:4px" onclick="return confirm('¿Seguro que deseas cerrar turno en esta máquina?')">Cerrar turno</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <a href="/logout" class="btnred" style="padding:6px 14px;border-radius:4px;text-decoration:none;background:#DC2626;color:#FFF;display:inline-flex;align-items:center;gap:6px;font-weight:bold" onclick="return confirm('¿Desea cerrar sesión del sistema?')" title="Cerrar sesión / Salir">
                            <i class="fa fa-sign-out-alt"></i> Cerrar sesión
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </td>
    </tr>
    </table>

    <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
        <div style="font-size:15px;font-weight:bold;color:#115452;margin-bottom:12px">
            <i class="fa fa-fw fa-cogs"></i> Seleccionar Máquina para Operar
        </div>
        <table border="0" width="100%" cellpadding="8" cellspacing="0">
        <colgroup><col width="180"><col><col width="220"><col width="140"></colgroup>
        <thead>
            <tr>
                <td class="tdheader">Tipo Máquina</td>
                <td class="tdheader">Nombre Máquina</td>
                <td class="tdheader">Características</td>
                <td class="tdheader" align="center">Acción</td>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($machines as $m): ?>
            <?php $isThisActive = $activeInit !== null && (int)$activeInit['win_equipoid'] === (int)$m['id']; ?>
            <tr>
                <td class="tdnrm"><b><?=htmlspecialchars((string)($m['type_ant_title'] ?? 'General'))?></b></td>
                <td class="tdnrm" style="font-size:14px;font-weight:bold"><?=htmlspecialchars((string)$m['equipo_name'])?></td>
                <td class="tdnrm" style="color:#666">
                    <?php
                    $feats = [];
                    if ((int)($m['equipo_prod_isprinter_flexo'] ?? 0) === 1) $feats[] = 'Flexografía';
                    if ((int)($m['equipo_prod_isprinter_seri'] ?? 0) === 1) $feats[] = 'Serigrafía / Pulpo';
                    if ((int)($m['type_createstock_act'] ?? 0) === 1) $feats[] = 'Genera Stock';
                    echo !empty($feats) ? implode(' · ', $feats) : 'Estándar';
                    ?>
                </td>
                <td class="tdnrm" align="center">
                    <?php if ($isThisActive): ?>
                        <?php if ($activeStandby !== null): ?>
                            <button type="button" class="btnorange" style="background:#EA580C;padding:6px 14px;cursor:pointer;border:none;font-weight:bold;border-radius:4px;font-size:12px" onclick="openStandbyEndModal()" title="Máquina sin producción. Clic para finalizar espera y volver a producción">
                                <i class="fa fa-pause-circle"></i> En Espera (Finalizar)
                            </button>
                        <?php else: ?>
                            <span class="btngreen" style="padding:6px 12px;cursor:default"><i class="fa fa-check"></i> Activa</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <form method="post" action="/production/machines/init" style="margin:0">
                            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                            <input type="hidden" name="equipo_id" value="<?=$m['id']?>">
                            <button type="submit" class="btnblue" style="padding:6px 14px">Iniciar</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
    </div>

    <?php
    renderStandbyAndLunchModals($activeStandby, $activeInit, $operatorName, '/production/machines', $activeLunch);

