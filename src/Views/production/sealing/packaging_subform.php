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

        <?php
        // Cajas con código: se crean solas al cerrar cada embalaje (una por caja completa y otra por la caja final),
        // con su CC, SKU, bodega y unidades. Antes se generaban a mano aquí, sin quedar ligadas al stock del ERP.
        $woBoxes = [];
        $packagingBoxes = [];
        if ($workerOtId > 0 && Db::trzPdo() !== null) {
            try {
                $stmtWoBoxes = Db::trzPdo()->prepare("SELECT * FROM boxes WHERE work_order_id = :otid ORDER BY id DESC");
                $stmtWoBoxes->execute([':otid' => $workerOtId]);
                $woBoxes = $stmtWoBoxes->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $bagBoxes = new BagBoxService(Db::trzPdo(), Db::erpPdo());
                foreach ($events as $packagingEvent) {
                    if ((int)($packagingEvent['overrideembalaje_act'] ?? 0) === 1 && (string)$packagingEvent['evt_type'] === 'prod') {
                        foreach ($bagBoxes->packagingBoxes((int)$packagingEvent['id']) as $pb) {
                            $packagingBoxes[] = $pb;
                        }
                    }
                }
            } catch (Throwable) {}
        }
        ?>
        <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);margin-bottom:20px">
            <div style="font-size:16px;font-weight:bold;color:#0F172A;margin-bottom:8px">
                <i class="fa fa-boxes"></i> Cajas con código de esta OT (<?= count($packagingBoxes) ?>)
            </div>
            <p style="margin:0 0 12px;color:#475569;font-size:13px">Al cerrar el embalaje, cada caja recibe su código con el CC, el SKU, la bodega y sus bolsas. Pega la etiqueta en la caja: así se puede pedir y usar en el Pulpo, despachar y rastrear.</p>
            <?php if ($packagingBoxes === []): ?>
                <div style="background:#F8FAFC;border:1px dashed #CBD5E1;border-radius:6px;padding:16px;text-align:center;color:#64748B;font-size:13px">Todavía no hay cajas: aparecen al finalizar el embalaje.</div>
            <?php else: ?>
                <div style="margin-bottom:10px"><button type="button" class="btnblue" onclick="unibagPrintBoxLabels(<?=htmlspecialchars(json_encode(array_map(static fn($b) => (int)$b['id'], $packagingBoxes)))?>, this)"><i class="fa fa-print"></i> Imprimir todas las etiquetas</button></div>
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead><tr style="background:#F1F5F9;color:#334155;text-align:left;border-bottom:2px solid #CBD5E1">
                        <th style="padding:8px">Código</th><th style="padding:8px">SKU</th><th style="padding:8px">CC</th>
                        <th style="padding:8px">Bolsas</th><th style="padding:8px">Estado</th><th style="padding:8px;text-align:center">Etiqueta</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($packagingBoxes as $pb): ?>
                        <tr style="border-bottom:1px solid #E2E8F0">
                            <td style="padding:8px"><b><?= htmlspecialchars((string)$pb['box_code']) ?></b></td>
                            <td style="padding:8px"><?= htmlspecialchars((string)$pb['final_sku']) ?></td>
                            <td style="padding:8px"><?= htmlspecialchars((string)($pb['customer_order_ref'] ?? '')) ?></td>
                            <td style="padding:8px"><b><?= number_format((float)$pb['units_qty'], 0, ',', '.') ?></b> de <?= number_format((float)($pb['units_initial'] ?? $pb['units_qty']), 0, ',', '.') ?></td>
                            <td style="padding:8px"><?= htmlspecialchars(['IN_WAREHOUSE' => 'En bodega', 'IN_MACHINE' => 'En máquina', 'CONSUMED' => 'Usada', 'DISPATCHED' => 'Despachada'][(string)$pb['status']] ?? (string)$pb['status']) ?></td>
                            <td style="padding:8px;text-align:center"><a href="/boxes/<?= (int)$pb['id'] ?>/label" target="_blank" class="btnblue" style="padding:3px 8px;font-size:11px;text-decoration:none"><i class="fa fa-print"></i> 100×70</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- TABLA: cajas generadas a mano antes (sin stock ERP), solo como historial -->
        <?php if (!empty($woBoxes)): ?>
            <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,0.05);margin-bottom:20px">
                <div style="font-size:16px;font-weight:bold;color:#0F172A;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between">
                    <div><i class="fa fa-boxes"></i> Cajas generadas a mano anteriormente (<?= count($woBoxes) ?>)</div>
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

