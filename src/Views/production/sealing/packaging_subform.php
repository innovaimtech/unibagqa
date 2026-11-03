<?php
    ?>
        <!-- SUBFORMULARIO: Embalaje, Generación de Cajas y Pallet (Selladora / Confección) -->
        <?php
        $packagingActiveEvent = null;
        foreach ($events as $packagingEvent) {
            if ((int)($packagingEvent['overrideembalaje_act'] ?? 0) === 1 && (int)$packagingEvent['evt_status'] === 1
                && (int)$packagingEvent['evt_prod_worker_otid'] === $workerOtId
                && (int)($packagingEvent['evt_enddat'] ?? 0) === 0 && (string)$packagingEvent['evt_type'] === 'prod') {
                $packagingActiveEvent = $packagingEvent;
                break;
            }
        }
        ?>
        <div style="margin-bottom:15px">
            <?php if ($packagingActiveEvent !== null): ?>
                <a class="btnorange" style="display:inline-block;text-decoration:none;margin-right:8px" href="/production/work-orders/operate?agid=<?=$agId?>&mode=production&overrideembalaje=1&refid=<?=(int)$packagingActiveEvent['id']?>&submode=end">Finalizar embalaje</a>
                <span style="font-weight:700;color:#165552">Embalaje en curso desde <?=date('d/m/Y H:i', (int)$packagingActiveEvent['evt_crtdat'])?></span>
            <?php endif; ?>
            <div class="btngrey" style="display:inline-block;cursor:pointer" onclick="location.href='/production/work-orders/operate?agid=<?=$agId?>'">
                <i class="fa fa-fw fa-arrow-left"></i> Volver a la Consola de OT
            </div>
        </div>

        <?php if (!empty($_GET['boxes_done'])): ?>
            <div style="background:#E8F8F5;border:1.5px solid #2ECC71;color:#117A65;padding:16px 20px;border-radius:6px;margin-bottom:18px;font-size:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                <div>
                    <i class="fa fa-fw fa-check-circle" style="font-size:20px;color:#27AE60"></i>
                    <b>¡Cajas generadas exitosamente!</b> Se registraron <b><?= (int)$_GET['boxes_done'] ?></b> cajas vinculadas a la OT #<b><?= htmlspecialchars((string)$agenda['prd_number']) ?></b>.
                    <?php if (!empty($_GET['pallet_id'])): ?>
                        <span> · Pallet ID: <b><?= (int)$_GET['pallet_id'] ?></b></span>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if (!empty($_GET['first_box'])): ?>
                        <a href="/boxes/<?= (int)$_GET['first_box'] ?>/label?server_print=1" target="_blank" class="btnblue" style="text-decoration:none;font-weight:bold">
                            <i class="fa fa-print"></i> 🖨️ Mandar a Imprimir Etiquetas (100×70mm)
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php
        $woBoxes = [];
        $woPallet = null;
        if ($workerOtId > 0 && Db::trzPdo() !== null) {
            try {
                $stmtWoBoxes = Db::trzPdo()->prepare("SELECT * FROM boxes WHERE work_order_id = :otid ORDER BY id DESC");
                $stmtWoBoxes->execute([':otid' => $workerOtId]);
                $woBoxes = $stmtWoBoxes->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $stmtWoPallet = Db::trzPdo()->prepare("SELECT * FROM pallets WHERE work_order_id = :otid ORDER BY id DESC LIMIT 1");
                $stmtWoPallet->execute([':otid' => $workerOtId]);
                $woPallet = $stmtWoPallet->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Throwable) {}
        }
        $skuFinal = (string)($agenda['item_title'] ?? $agenda['fab_type'] ?? 'PRODUCTO-TERMINADO');
        ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(400px, 1fr));gap:20px;margin-bottom:20px">
            <!-- COLUMNA 1: FORMULARIO GENERACIÓN DE CAJAS -->
            <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
                <div style="font-size:17px;font-weight:bold;color:#0F172A;margin-bottom:14px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-box" style="color:#F59E0B"></i> Generación de Cajas y Pallet
                </div>

                <form method="post" action="/production/work-orders/boxes/generate">
                    <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
                    <input type="hidden" name="agid" value="<?=$agId?>">
                    <input type="hidden" name="worker_ot_id" value="<?=$workerOtId?>">
                    <input type="hidden" name="sku_final" value="<?=htmlspecialchars($skuFinal)?>">

                    <table border="0" width="100%" cellpadding="8" cellspacing="0">
                    <tr>
                        <td class="tdleft" width="160">OT Activa:</td>
                        <td class="tdnrm"><b>OT #<?=htmlspecialchars((string)$agenda['prd_number'])?></b> &mdash; <?=htmlspecialchars((string)$agenda['cust_name'])?></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Producto / SKU:</td>
                        <td class="tdnrm"><b><?=htmlspecialchars($skuFinal)?></b></td>
                    </tr>
                    <tr>
                        <td class="tdleft">Bobina de Origen:</td>
                        <td class="tdnrm">
                            <?php if (!empty($assignedRolls)): ?>
                                <select name="source_roll_id" class="inptxt" style="width:100%">
                                    <?php foreach ($assignedRolls as $ar): ?>
                                        <option value="<?=$ar['id']?>" <?=!empty($ar['is_mounted']) ? 'selected' : ''?>>
                                            <?=htmlspecialchars($ar['roll_code'])?> (<?=number_format((float)$ar['weight_kg'], 1)?> Kg <?=!empty($ar['is_mounted']) ? ' - MONTADA' : ''?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <span style="color:#64748B;font-size:12px;font-style:italic">Se auto-detectará la bobina activa de la orden.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Cantidad de Cajas:</td>
                        <td class="tdnrm">
                            <input type="number" name="box_qty" min="1" step="1" value="10" required class="inptxt" style="width:120px;font-weight:bold;font-size:15px"> cajas
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Unidades por Caja:</td>
                        <td class="tdnrm">
                            <input type="number" name="units_per_box" min="1" step="1" value="500" required class="inptxt" style="width:120px;font-weight:bold;font-size:15px"> unidades
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Cajas por Pallet:</td>
                        <td class="tdnrm">
                            <input type="number" name="boxes_per_pallet" min="0" step="1" value="20" class="inptxt" style="width:120px">
                            <span style="font-size:11px;color:#64748B">(Opcional: agrupa en etiqueta PAL-XXXXXX)</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">Destino:</td>
                        <td class="tdnrm">
                            <select name="destination_mode" class="inptxt" style="width:160px">
                                <option value="STOCK">STOCK BODEGA</option>
                                <option value="CUSTOMER_ORDER">ORDEN CLIENTE</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class="tdleft">&nbsp;</td>
                        <td class="tdnrm" style="padding-top:16px">
                            <button type="submit" class="btngreen" style="font-size:15px;padding:12px 26px;box-shadow:0 2px 4px rgba(0,0,0,0.15)">
                                <i class="fa fa-boxes"></i> 📦 Generar Cajas y Rotular Etiquetas
                            </button>
                        </td>
                    </tr>
                    </table>
                </form>
            </div>

            <!-- COLUMNA 2: PALLET Y RESUMEN DE EMBALAJE -->
            <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
                <div style="font-size:17px;font-weight:bold;color:#0F172A;margin-bottom:14px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-pallet" style="color:#8B5CF6"></i> Pallet de Consolidación Activo
                </div>

                <?php if ($woPallet): ?>
                    <div style="background:#F5F3FF;border:1.5px solid #DDD6FE;border-radius:6px;padding:16px;margin-bottom:16px">
                        <div style="font-size:18px;font-weight:bold;color:#6B21A8;margin-bottom:4px">
                            <?= htmlspecialchars((string)$woPallet['pallet_code']) ?>
                        </div>
                        <div style="font-size:13px;color:#475569;margin-bottom:12px">
                            Total Cajas Agrupadas: <b><?= (int)($woPallet['box_count'] ?? 0) ?></b> · Estado: <b><?= htmlspecialchars((string)$woPallet['status']) ?></b>
                        </div>
                        <a href="/pallets/<?= (int)$woPallet['id'] ?>/label" target="_blank" class="btnblue" style="text-decoration:none;font-weight:bold;display:inline-flex;align-items:center;gap:6px">
                            <i class="fa fa-print"></i> Imprimir Etiqueta Pallet 100×70mm
                        </a>
                    </div>
                <?php else: ?>
                    <div style="background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:6px;padding:20px;text-align:center;color:#64748B;font-size:13px;margin-bottom:16px">
                        <i class="fa fa-pallet" style="font-size:28px;color:#94A3B8;margin-bottom:8px"></i><br>
                        Aún no se ha consolidado un pallet en esta orden. Al ingresar "Cajas por Pallet" en el formulario se generará automáticamente.
                    </div>
                <?php endif; ?>

                <div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:6px;padding:12px;color:#92400E;font-size:12.5px;line-height:1.5">
                    <i class="fa fa-info-circle"></i> <b>Norma de Trazabilidad Confección:</b> Cada caja llevará su código de barras único <b>BOX-XXXXXX</b> vinculado a la bobina de origen pesada en balanza. Al escanear la caja en despacho se audita toda la cadena de custodia.
                </div>
            </div>
        </div>

        <!-- TABLA: HISTORIAL DE CAJAS GENERADAS EN ESTA OT -->
        <?php if (!empty($woBoxes)): ?>
            <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);margin-bottom:20px">
                <div style="font-size:16px;font-weight:bold;color:#0F172A;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between">
                    <div><i class="fa fa-boxes"></i> Cajas Generadas en esta OT (<?= count($woBoxes) ?> cajas)</div>
                </div>
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr style="background:#F1F5F9;color:#334155;text-align:left;border-bottom:2px solid #CBD5E1">
                            <th style="padding:8px">Código Caja</th>
                            <th style="padding:8px">Unidades</th>
                            <th style="padding:8px">Bobina Origen</th>
                            <th style="padding:8px">Pallet</th>
                            <th style="padding:8px">Destino</th>
                            <th style="padding:8px">Operario</th>
                            <th style="padding:8px">Fecha</th>
                            <th style="padding:8px;text-align:center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($woBoxes as $wb): ?>
                            <tr style="border-bottom:1px solid #E2E8F0">
                                <td style="padding:8px"><b><?= htmlspecialchars((string)$wb['box_code']) ?></b></td>
                                <td style="padding:8px"><b><?= number_format((float)$wb['units_qty'], 0, ',', '.') ?></b> uds</td>
                                <td style="padding:8px"><?= (int)($wb['source_roll_id'] ?? 0) > 0 ? '#' . (int)$wb['source_roll_id'] : 'Auto-detect' ?></td>
                                <td style="padding:8px"><?= !empty($wb['pallet_id']) ? 'PAL-' . (int)$wb['pallet_id'] : '-' ?></td>
                                <td style="padding:8px"><?= htmlspecialchars((string)($wb['destination_mode'] ?? 'STOCK')) ?></td>
                                <td style="padding:8px"><?= htmlspecialchars((string)($wb['operator_name'] ?? '-')) ?></td>
                                <td style="padding:8px"><?= htmlspecialchars((string)($wb['created_at'] ?? '-')) ?></td>
                                <td style="padding:8px;text-align:center">
                                    <a href="/boxes/<?= (int)$wb['id'] ?>/label" target="_blank" class="btnblue" style="padding:3px 8px;font-size:11px;text-decoration:none">
                                        <i class="fa fa-print"></i> 100×70
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <?php

