<?php
    $isLunchActive = ($activeLunch !== null);
    $lunchStartTime = $isLunchActive ? date('H:i', strtotime((string)$activeLunch['start_time'])) : '';
?>
    <!-- MODAL REGISTRAR COLACIÓN (TIEMPO REAL - SIN HORA FIJA MANUAL) -->
    <div id="modal_lunch" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:99999;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:8px;max-width:480px;width:92%;padding:24px;box-shadow:0 10px 25px rgba(0,0,0,0.3)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;border-bottom:1px solid #e2e8f0;padding-bottom:10px">
                <div style="font-size:17px;font-weight:bold;color:<?=$isLunchActive ? '#EA580C' : '#0369A1'?>;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-coffee"></i> <?=$isLunchActive ? 'Finalizar Horario de Colación' : 'Registrar Horario de Colación'?>
                </div>
                <button type="button" onclick="closeLunchModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#64748b">&times;</button>
            </div>
            <form method="post" action="/production/machines/lunch">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="action" value="<?=$isLunchActive ? 'end' : 'start'?>">
                <?php if ($isLunchActive): ?>
                    <input type="hidden" name="lunch_id" value="<?=(int)$activeLunch['id']?>">
                <?php endif; ?>
                <?php if ($redirectTo !== ''): ?>
                    <input type="hidden" name="redirect_to" value="<?=htmlspecialchars($redirectTo)?>">
                <?php endif; ?>
                <?php if ($activeInit !== null): ?>
                    <input type="hidden" name="init_id" value="<?=(int)($activeInit['id'] ?? 0)?>">
                    <input type="hidden" name="machine_id" value="<?=(int)($activeInit['win_equipoid'] ?? $activeInit['equipo_id'] ?? 0)?>">
                    <input type="hidden" name="machine_name" value="<?=htmlspecialchars((string)($activeInit['equipo_name'] ?? ''))?>">
                <?php endif; ?>

                <div style="margin-bottom:12px">
                    <label style="display:block;font-size:12.5px;font-weight:bold;color:#475569;margin-bottom:4px">Operario</label>
                    <input type="text" class="inptxt" style="width:100%;background:#f8fafc" value="<?=htmlspecialchars($operatorName)?>" readonly>
                </div>

                <?php if ($isLunchActive): ?>
                    <div style="background:#FFF7ED;border:1px solid #FED7AA;border-radius:6px;padding:12px;margin-bottom:14px">
                        <div style="color:#C2410C;font-size:14px;font-weight:bold;margin-bottom:4px">
                            <i class="fa fa-clock"></i> Colación iniciada a las <?=$lunchStartTime?> hrs
                        </div>
                        <div style="font-size:12.5px;color:#9A3412">
                            Al hacer clic en el botón, se registrará la hora actual (<b><?=date('H:i')?> hrs</b>) como término de tu colación y se reanudará tu jornada.
                        </div>
                    </div>
                <?php else: ?>
                    <div style="background:#F0F9FF;border:1px solid #BAE6FD;border-radius:6px;padding:12px;margin-bottom:14px">
                        <div style="color:#0369A1;font-size:14px;font-weight:bold;margin-bottom:4px">
                            <i class="fa fa-play-circle"></i> Hora de inicio actual: <?=date('H:i')?> hrs
                        </div>
                        <div style="font-size:12.5px;color:#075985">
                            La hora se registra automáticamente en tiempo real. Al regresar de tu colación, vuelve a abrir este botón para registrar el término.
                        </div>
                    </div>
                <?php endif; ?>

                <div style="margin-bottom:18px">
                    <label style="display:block;font-size:12.5px;font-weight:bold;color:#475569;margin-bottom:4px">Observaciones / Motivo (opcional)</label>
                    <textarea name="comments" class="inptxt" style="width:100%;height:50px" placeholder="<?=$isLunchActive ? 'Comentarios de cierre...' : 'Observación de colación...'?>"></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px">
                    <button type="button" class="btngrey" style="padding:8px 16px" onclick="closeLunchModal()">Cancelar</button>
                    <?php if ($isLunchActive): ?>
                        <button type="submit" class="btngreen" style="background:#059669;padding:8px 20px;border:none;font-weight:bold;cursor:pointer">
                            <i class="fa fa-check"></i> Terminar Colación Ahora
                        </button>
                    <?php else: ?>
                        <button type="submit" class="btnblue" style="background:#0284C7;padding:8px 20px;border:none;font-weight:bold;cursor:pointer">
                            <i class="fa fa-play"></i> Iniciar Colación Ahora
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: INICIAR EVENTO SIN PRODUCCIÓN / ESPERA -->
    <div id="modal_standby_start" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:99999;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:8px;max-width:500px;width:92%;padding:24px;box-shadow:0 10px 25px rgba(0,0,0,0.3)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;border-bottom:1px solid #e2e8f0;padding-bottom:10px">
                <div style="font-size:17px;font-weight:bold;color:#D97706;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-pause-circle"></i> Informar Máquina Sin Producción / Espera
                </div>
                <button type="button" onclick="closeStandbyStartModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#64748b">&times;</button>
            </div>
            <form method="post" action="/production/machines/standby">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="action" value="start">
                <?php if ($redirectTo !== ''): ?>
                    <input type="hidden" name="redirect_to" value="<?=htmlspecialchars($redirectTo)?>">
                <?php endif; ?>

                <div style="margin-bottom:12px">
                    <label style="display:block;font-size:12.5px;font-weight:bold;color:#475569;margin-bottom:4px">Motivo / Causa de Espera o Parada *</label>
                    <select name="reason" class="inptxt" style="width:100%" required>
                        <option value="Espera de Orden de Trabajo (OT)">Espera de Orden de Trabajo (OT)</option>
                        <option value="Sin Producción">Sin Producción</option>
                        <option value="Falta de Material / Insumos / Sustratos">Falta de Material / Insumos / Sustratos</option>
                        <option value="Mantención Mecánica / Falla de Máquina">Mantención Mecánica / Falla de Máquina</option>
                        <option value="Limpieza y Preparación de Máquina">Limpieza y Preparación de Máquina</option>
                        <option value="Falta de Operador / Relevo">Falta de Operador / Relevo</option>
                        <option value="Corte de Energía / Suministro">Corte de Energía / Suministro</option>
                        <option value="Otro Motivo">Otro Motivo</option>
                    </select>
                </div>
                <div style="margin-bottom:12px">
                    <label style="display:block;font-size:12.5px;font-weight:bold;color:#475569;margin-bottom:4px">Hora Inicio *</label>
                    <input type="time" name="start_time" class="inptxt" style="width:160px" value="<?=date('H:i')?>" required>
                </div>
                <div style="margin-bottom:18px">
                    <label style="display:block;font-size:12.5px;font-weight:bold;color:#475569;margin-bottom:4px">Observaciones / Detalles</label>
                    <textarea name="comments" class="inptxt" style="width:100%;height:65px" placeholder="Indicar detalles del por qué la máquina no tiene producción..." required></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px">
                    <button type="button" class="btngrey" style="padding:8px 16px" onclick="closeStandbyStartModal()">Cancelar</button>
                    <button type="submit" class="btnorange" style="background:#D97706;padding:8px 20px;border:none;font-weight:bold;cursor:pointer"><i class="fa fa-play"></i> Iniciar Sin Producción</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: FINALIZAR EVENTO SIN PRODUCCIÓN -->
    <?php if ($activeStandby !== null): ?>
    <div id="modal_standby_end" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:99999;align-items:center;justify-content:center">
        <div style="background:#fff;border-radius:8px;max-width:460px;width:92%;padding:24px;box-shadow:0 10px 25px rgba(0,0,0,0.3)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;border-bottom:1px solid #e2e8f0;padding-bottom:10px">
                <div style="font-size:17px;font-weight:bold;color:#EA580C;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-play-circle"></i> Finalizar Sin Producción / Espera
                </div>
                <button type="button" onclick="closeStandbyEndModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#64748b">&times;</button>
            </div>
            <form method="post" action="/production/machines/standby">
                <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                <input type="hidden" name="action" value="end">
                <input type="hidden" name="standby_id" value="<?=(int)$activeStandby['id']?>">
                <?php if ($redirectTo !== ''): ?>
                    <input type="hidden" name="redirect_to" value="<?=htmlspecialchars($redirectTo)?>">
                <?php endif; ?>

                <div style="margin-bottom:12px;background:#fff7ed;padding:10px 14px;border-radius:5px;border:1px solid #fed7aa;font-size:13px;color:#9a3412">
                    <b>Motivo:</b> <?=htmlspecialchars((string)$activeStandby['reason'])?><br>
                    <b>Iniciado:</b> <?=htmlspecialchars((string)$activeStandby['start_time'])?> hrs
                    <?php if (!empty($activeStandby['comments'])): ?>
                        <br><b>Detalle:</b> <i><?=htmlspecialchars((string)$activeStandby['comments'])?></i>
                    <?php endif; ?>
                </div>
                <div style="margin-bottom:18px">
                    <label style="display:block;font-size:12.5px;font-weight:bold;color:#475569;margin-bottom:4px">Hora Término *</label>
                    <input type="time" name="end_time" class="inptxt" style="width:160px" value="<?=date('H:i')?>" required>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px">
                    <button type="button" class="btngrey" style="padding:8px 16px" onclick="closeStandbyEndModal()">Cancelar</button>
                    <button type="submit" class="btnorange" style="background:#EA580C;padding:8px 20px;border:none;font-weight:bold;cursor:pointer"><i class="fa fa-check"></i> Finalizar y Reanudar</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function openLunchModal() {
        var m = document.getElementById('modal_lunch');
        if (m) m.style.display = 'flex';
    }
    function closeLunchModal() {
        var m = document.getElementById('modal_lunch');
        if (m) m.style.display = 'none';
    }
    function openStandbyStartModal() {
        var m = document.getElementById('modal_standby_start');
        if (m) m.style.display = 'flex';
    }
    function closeStandbyStartModal() {
        var m = document.getElementById('modal_standby_start');
        if (m) m.style.display = 'none';
    }
    function openStandbyEndModal() {
        var m = document.getElementById('modal_standby_end');
        if (m) m.style.display = 'flex';
    }
    function closeStandbyEndModal() {
        var m = document.getElementById('modal_standby_end');
        if (m) m.style.display = 'none';
    }
    </script>
<?php

