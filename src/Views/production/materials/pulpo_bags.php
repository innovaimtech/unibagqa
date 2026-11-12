<?php
/**
 * Parcial · Pulpo Serigráfico: cajas de bolsas en la máquina y bolsas usadas.
 * @var int $agId
 * @var int $workerOtId
 */
$pulpoBags = null;
$mounted = $moves = $waiting = [];
$need = ['printed' => 0.0, 'merma' => 0.0, 'needed' => 0.0];
$used = 0.0;
$loadError = '';
try {
    if (Db::trzPdo() === null) {
        throw new RuntimeException('Base de trazabilidad no disponible.');
    }
    $pulpoBags = new PulpoBagService(Db::erpPdo(), Db::trzPdo());
    if ($workerOtId > 0) {
        $mounted = $pulpoBags->mountedBoxes($workerOtId);
    }
    $moves = $pulpoBags->moves($agId);
    $waiting = $pulpoBags->waitingPartials();
    $need = $pulpoBags->neededForAgenda($agId);
    $used = $pulpoBags->consumedForAgenda($agId);
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}
$fmt = static fn(float $n): string => number_format($n, 0, ',', '.');
$pending = array_values(array_filter($moves, static fn($m) => (string)$m['state'] === 'PENDING'));
$diff = round($used - $need['needed'], 3);
$action = '/production/work-orders/pulpo-bags';
$csrf = htmlspecialchars(csrfToken());
?>
<div id="pulpo-bags" style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:8px;padding:18px;margin:18px 0">
    <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:10px">
        <div style="font-size:16px;font-weight:bold;color:#115452"><i class="fa fa-fw fa-box-open"></i> Bolsas en la máquina</div>
        <a href="/production/materials/request?agid=<?=$agId?>&material_kind=boxes" target="_blank" class="btnblue" style="text-decoration:none;font-size:13px;padding:7px 14px">
            <i class="fa fa-dolly"></i> Pedir cajas de bolsas
        </a>
    </div>
    <p style="margin:0 0 12px;color:#475569;font-size:13px">Escanea la caja que vas a usar. Cuando termines con ella (o al cerrar), anota cuántas bolsas usaste: se descuentan de la caja y del inventario. Si sobran, la caja queda con su saldo para otro día.</p>

    <?php if (!empty($_GET['bag_ok'])): ?>
        <div style="background:#ECFDF5;border:1px solid #10B981;border-radius:6px;padding:10px 14px;margin-bottom:12px;color:#065F46;font-size:13px"><i class="fa fa-check-circle"></i> <?=htmlspecialchars((string)$_GET['bag_ok'])?></div>
    <?php endif; ?>
    <?php if ($loadError !== ''): ?>
        <div style="background:#FEF2F2;border:1px solid #EF4444;border-radius:6px;padding:10px 14px;margin-bottom:12px;color:#991B1B;font-size:13px"><?=htmlspecialchars($loadError)?></div>
    <?php endif; ?>

    <form method="post" action="<?=$action?>" autocomplete="off" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
        <input type="hidden" name="_csrf" value="<?=$csrf?>">
        <input type="hidden" name="agid" value="<?=$agId?>">
        <input type="hidden" name="bag_action" value="mount">
        <input name="code" required maxlength="60" class="inptxt" style="flex:1;min-width:220px;font-size:15px" placeholder="Escanea el código de la caja (BX...)" autofocus>
        <button class="btngreen" style="padding:8px 18px">Poner caja en máquina</button>
    </form>

    <?php if ($waiting !== [] && $workerOtId > 0): ?>
        <div style="background:#EFF6FF;border:1px solid #93C5FD;border-radius:6px;padding:10px 14px;margin-bottom:12px;font-size:13px;color:#1E3A8A">
            <b>Bolsas traspasadas con código temporal</b> (parte de una caja que quedó con saldo en bodega). No se escanean: cárgalas con el botón.
            <?php foreach ($waiting as $wb): ?>
            <form method="post" action="<?=$action?>" style="display:flex;gap:8px;align-items:center;margin-top:6px">
                <input type="hidden" name="_csrf" value="<?=$csrf?>">
                <input type="hidden" name="agid" value="<?=$agId?>">
                <input type="hidden" name="bag_action" value="mount">
                <input type="hidden" name="code" value="<?=htmlspecialchars((string)$wb['box_code'])?>">
                <span><b><?=$fmt((float)$wb['units_qty'])?></b> bolsas <?=htmlspecialchars((string)$wb['final_sku'])?><?=!empty($wb['customer_order_ref']) ? ' · CC ' . htmlspecialchars((string)$wb['customer_order_ref']) : ''?> <span style="color:#64748B">(<?=htmlspecialchars((string)$wb['box_code'])?>)</span></span>
                <button class="btnblue" style="padding:5px 12px">Cargar en la máquina</button>
            </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($pending !== []): ?>
        <form method="post" action="<?=$action?>" style="background:#FFFBEB;border:1px solid #F59E0B;border-radius:6px;padding:10px 14px;margin-bottom:12px;color:#92400E;font-size:13px">
            <input type="hidden" name="_csrf" value="<?=$csrf?>">
            <input type="hidden" name="agid" value="<?=$agId?>">
            <input type="hidden" name="bag_action" value="resume">
            Hay <?=count($pending)?> movimiento(s) de bolsas pendiente(s) (se cortó la conexión con el ERP). <button class="btnorange">Reintentar pendientes</button>
        </form>
    <?php endif; ?>

    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead><tr style="background:#F1F5F9;color:#334155;text-align:left">
                <th style="padding:8px">Caja</th><th style="padding:8px">SKU</th><th style="padding:8px">CC</th><th style="padding:8px">Bolsas en la caja</th>
                <th style="padding:8px">Bolsas usadas</th><th style="padding:8px"></th>
            </tr></thead>
            <tbody>
            <?php if ($mounted === []): ?>
                <tr><td colspan="6" style="padding:12px;color:#64748B;text-align:center">No hay cajas en la máquina.</td></tr>
            <?php endif; ?>
            <?php foreach ($mounted as $box): ?>
                <tr style="border-top:1px solid #E2E8F0">
                    <td style="padding:8px"><b><?=htmlspecialchars((string)$box['box_code'])?></b></td>
                    <td style="padding:8px"><?=htmlspecialchars((string)$box['final_sku'])?></td>
                    <td style="padding:8px"><?=htmlspecialchars((string)($box['customer_order_ref'] ?? ''))?></td>
                    <td style="padding:8px"><b><?=$fmt((float)$box['units_qty'])?></b> de <?=$fmt((float)($box['units_initial'] ?? $box['units_qty']))?></td>
                    <td style="padding:8px">
                        <form method="post" action="<?=$action?>" style="display:flex;gap:6px;align-items:center" onsubmit="return confirm('¿Registrar ' + this.qty.value + ' bolsas usadas de la caja <?=htmlspecialchars((string)$box['box_code'])?>?')">
                            <input type="hidden" name="_csrf" value="<?=$csrf?>">
                            <input type="hidden" name="agid" value="<?=$agId?>">
                            <input type="hidden" name="bag_action" value="consume">
                            <input type="hidden" name="box_id" value="<?=(int)$box['id']?>">
                            <input type="hidden" name="op_key" value="<?=bin2hex(random_bytes(16))?>">
                            <input type="number" name="qty" min="1" max="<?=(int)floor((float)$box['units_qty'])?>" step="1" required class="inptxt" style="width:100px" inputmode="numeric">
                            <button class="btnblue">Registrar</button>
                        </form>
                    </td>
                    <td style="padding:8px">
                        <form method="post" action="<?=$action?>">
                            <input type="hidden" name="_csrf" value="<?=$csrf?>">
                            <input type="hidden" name="agid" value="<?=$agId?>">
                            <input type="hidden" name="bag_action" value="unmount">
                            <input type="hidden" name="box_id" value="<?=(int)$box['id']?>">
                            <button class="btngrey" title="Queda en Bodega 3000 con su saldo">Bajar caja</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="margin-top:12px;padding:10px 14px;border-radius:6px;font-size:13px;background:<?=abs($diff) < 1 ? '#ECFDF5' : '#F8FAFC'?>;border:1px solid #CBD5E1">
        <b>Esta OT:</b> impresas <?=$fmt($need['printed'])?> + merma <?=$fmt($need['merma'])?> = <b><?=$fmt($need['needed'])?></b> bolsas · usadas de las cajas <b><?=$fmt($used)?></b>
        <?php if (abs($diff) >= 1): ?> · <span style="color:#92400E"><?=$diff < 0 ? 'faltan registrar ' . $fmt(-$diff) : 'sobran ' . $fmt($diff)?></span><?php endif; ?>
    </div>

    <?php $done = array_values(array_filter($moves, static fn($m) => (string)$m['kind'] === 'CONSUME')); ?>
    <?php if ($done !== []): ?>
        <details style="margin-top:12px">
            <summary style="cursor:pointer;font-weight:bold;color:#334155">Bolsas usadas registradas (<?=count($done)?>)</summary>
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;margin-top:8px">
                <thead><tr style="background:#F1F5F9;text-align:left"><th style="padding:6px">Fecha</th><th style="padding:6px">Caja</th><th style="padding:6px">Bolsas</th><th style="padding:6px">Operador</th><th style="padding:6px">Estado</th><th style="padding:6px"></th></tr></thead>
                <tbody>
                <?php foreach ($done as $m): ?>
                    <tr style="border-top:1px solid #E2E8F0">
                        <td style="padding:6px"><?=date('d/m H:i', (int)$m['created_at'])?></td>
                        <td style="padding:6px"><?=htmlspecialchars((string)$m['box_code'])?></td>
                        <td style="padding:6px"><b><?=$fmt((float)$m['qty'])?></b></td>
                        <td style="padding:6px"><?=htmlspecialchars((string)($m['operator_name'] ?? ''))?></td>
                        <td style="padding:6px"><?=htmlspecialchars(['DONE' => 'Registrado', 'PENDING' => 'Pendiente', 'REVERSED' => 'Anulado'][(string)$m['state']] ?? (string)$m['state'])?></td>
                        <td style="padding:6px">
                            <?php if ((string)$m['state'] === 'DONE' && (int)$m['worker_ot_id'] === $workerOtId): ?>
                            <form method="post" action="<?=$action?>" style="display:flex;gap:6px">
                                <input type="hidden" name="_csrf" value="<?=$csrf?>">
                                <input type="hidden" name="agid" value="<?=$agId?>">
                                <input type="hidden" name="bag_action" value="reverse">
                                <input type="hidden" name="move_id" value="<?=(int)$m['id']?>">
                                <input name="reason" required minlength="5" maxlength="200" class="inptxt" placeholder="Motivo de anulación">
                                <button class="btngrey">Anular</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </details>
    <?php endif; ?>
</div>
