<?php
/**
 * Plantilla de Vista · Modal de Cierre de Recepción con Faltantes (src/Views/reception/close_modal.php)
 * @var string $actionUrl
 * @var string $entityTitle
 * @var int $pendingUnits
 */
?>
<div id="modal-close-reception" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.65);z-index:99999;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px)">
  <div style="background:#ffffff;border-radius:14px;max-width:540px;width:100%;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);overflow:hidden;border:1px solid #fecdd3">
    <div style="padding:16px 20px;background:#fff1f2;border-bottom:1px solid #ffe4e6;display:flex;align-items:center;justify-content:space-between">
      <div style="font-weight:700;font-size:16px;color:#9f1239;display:flex;align-items:center;gap:8px">
        <span style="font-size:18px">🔒</span> Cerrar Recepción con Faltantes
      </div>
      <button type="button" onclick="closeReceptionModal()" style="background:none;border:none;font-size:24px;line-height:1;cursor:pointer;color:#9f1239;padding:0">&times;</button>
    </div>
    <form method="post" action="<?= h($actionUrl) ?>" style="padding:20px;margin:0">
      <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
      
      <div style="background:#fef2f2;border:1px solid #fee2e2;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#881337;line-height:1.5">
        <strong>¿Por qué cerrar anticipadamente?</strong><br>
        Esta acción dará por finalizada la recepción de <strong><?= h($entityTitle) ?></strong> y la moverá al historial de <strong>Finalizadas</strong>. Úsela cuando el proveedor no entregará el saldo restante (falla de despacho, faltantes o reembolso pactado).
      </div>
      <?php if ($pendingUnits > 0): ?>
        <div style="margin-bottom:14px;padding:10px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;color:#334155;display:flex;justify-content:space-between;align-items:center">
          <span>Saldo pendiente por ingresar:</span>
          <strong style="color:#e11d48;font-size:14px"><?= number_format($pendingUnits, 0, ',', '.') ?> Unid.</strong>
        </div>
      <?php endif; ?>
      <div style="margin-bottom:14px">
        <label style="display:block;font-weight:700;font-size:13px;color:#1e293b;margin-bottom:6px">Motivo del Cierre <span style="color:#e11d48">*</span></label>
        <select name="reason" style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;background:#fff;color:#0f172a" required>
          <option value="Falla de proveedor (Reembolso acordado)" selected>Falla de proveedor (Reembolso acordado)</option>
          <option value="Entrega incompleta acordada con compras">Entrega incompleta acordada con compras</option>
          <option value="Mercadería dañada en origen / No enviada">Mercadería dañada en origen / No enviada</option>
          <option value="Discrepancia de inventario / Otro">Discrepancia de inventario / Otro motivo</option>
        </select>
      </div>

      <div style="margin-bottom:18px">
        <label style="display:block;font-weight:700;font-size:13px;color:#1e293b;margin-bottom:6px">Observaciones / Detalle del Reembolso</label>
        <textarea name="notes" rows="3" placeholder="Indique detalle del reembolso, nota de crédito pactada o justificación..." style="width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;resize:vertical;font-family:inherit;box-sizing:border-box"></textarea>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:12px;border-top:1px solid #f1f5f9">
        <button type="button" class="btn secondary" onclick="closeReceptionModal()" style="padding:8px 16px;font-size:13px">Cancelar</button>
        <button type="submit" class="btn" style="background:#dc2626;border-color:#dc2626;color:#fff;font-weight:700;padding:8px 18px;font-size:13px">Confirmar y Cerrar Recepción</button>
      </div>
    </form>
  </div>
</div>
<script>
  function openCloseReceptionModal() {
    var m = document.getElementById("modal-close-reception");
    if (m) { m.style.display = "flex"; }
  }
  function closeReceptionModal() {
    var m = document.getElementById("modal-close-reception");
    if (m) { m.style.display = "none"; }
  }
</script>

