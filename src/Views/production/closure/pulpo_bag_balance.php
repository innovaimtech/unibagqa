<?php
/**
 * Parcial · Cierre de OT del Pulpo: bolsas usadas de las cajas frente a las impresas más la merma.
 * @var int $workerOtId
 * @var int $agId
 */
$bagBalance = null;
try {
    if (Db::trzPdo() !== null && $workerOtId > 0 && $agId > 0) {
        $pulpoBags = new PulpoBagService(Db::erpPdo(), Db::trzPdo());
        $need = $pulpoBags->neededForAgenda($agId);
        $used = $pulpoBags->consumedForAgenda($agId);
        $bagBalance = $need + ['used' => $used, 'diff' => round($used - $need['needed'], 3)];
    }
} catch (Throwable) {
    $bagBalance = null;
}
if ($bagBalance === null) {
    return;
}
$fmt = static fn(float $n): string => number_format($n, 0, ',', '.');
$ok = abs($bagBalance['diff']) < 1;
?>
<div style="margin:12px 20px 0;padding:12px 14px;border-radius:6px;font-size:13px;border:1px solid <?=$ok ? '#A7F3D0' : '#FCD34D'?>;background:<?=$ok ? '#ECFDF5' : '#FFFBEB'?>;color:<?=$ok ? '#065F46' : '#92400E'?>">
    <b>Bolsas usadas de las cajas:</b> <?=$fmt($bagBalance['used'])?> ·
    impresas <?=$fmt($bagBalance['printed'])?> + merma <?=$fmt($bagBalance['merma'])?> = <?=$fmt($bagBalance['needed'])?>.
    <?php if ($ok): ?>
        Cuadra.
    <?php else: ?>
        <div style="margin-top:6px"><?=$bagBalance['diff'] < 0 ? 'Faltan registrar ' . $fmt(-$bagBalance['diff']) . ' bolsas usadas' : 'Hay ' . $fmt($bagBalance['diff']) . ' bolsas usadas de más'?>.
        Corrígelo en Materiales, o confirma la diferencia con el supervisor:</div>
        <label style="display:flex;gap:8px;align-items:center;margin-top:8px;font-weight:bold">
            <input type="checkbox" name="confirm_bag_difference" value="1"> Confirmo la diferencia de bolsas
        </label>
        <textarea name="bag_difference_reason" rows="2" maxlength="300" style="width:100%;margin-top:6px" placeholder="Motivo (mínimo 10 caracteres). Ej.: bolsas rotas que no se registraron como merma"></textarea>
    <?php endif; ?>
</div>
