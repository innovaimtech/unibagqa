<?php
/**
 * Plantilla de Vista · Banner de Cierre de Recepción (src/Views/reception/closure_banner.php)
 * @var array<string, mixed>|null $closure
 */
if (empty($closure)) {
    return;
}
$reason = h((string)($closure['reason'] ?? 'Cierre con faltantes'));
$notes = trim((string)($closure['notes'] ?? ''));
$date = h((string)($closure['created_at'] ?? ''));
?>
<div style="margin-bottom:16px;padding:14px 18px;background:#fef2f2;border:1px solid #fecdd3;border-left:5px solid #e11d48;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <div style="font-weight:700;color:#9f1239;font-size:14px;display:flex;align-items:center;gap:6px"><span>🔒</span> Recepción Cerrada Anticipadamente con Faltantes</div>
        <?php if ($date !== ''): ?>
            <div style="font-size:12px;color:#881337;font-weight:500">Fecha de cierre: <?= $date ?></div>
        <?php endif; ?>
    </div>
    <div style="margin-top:6px;font-size:13px;color:#334155"><strong>Motivo registrado:</strong> <span style="color:#9f1239;font-weight:600"><?= $reason ?></span></div>
    <?php if ($notes !== ''): ?>
        <div style="margin-top:4px;font-size:13px;color:#475569"><strong>Observaciones / Reembolso:</strong> <?= h($notes) ?></div>
    <?php endif; ?>
</div>

