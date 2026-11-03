<?php
    if ($isSelladora) {
        $label = 'Lector de Bobina / Manillas:';
        $ph = 'Escanee bobina (ROLL-..., TEL..., RB...) o rollo de manillas (ROLL-MAN-...) con la pistola láser...';
    } elseif ($isFlexo) {
        $label = 'Lector de Bobina:';
        $ph = 'Escanee código de bobina virgen TEL o SKU (Ej: ROLL-..., TEL0165) con la pistola láser...';
    } else {
        $label = 'Lector de Bobina / Insumo:';
        $ph = 'Escanee código de trazabilidad (ROLL-..., TEL...), SKU o etiqueta de Bodega 3000 con la pistola...';
    }
    ?>
    <!-- MÓDULO SCANNER DE BOBINA / MATERIAL (CÓDIGO DE BARRA / QR) -->
    <div style="background:#EBF5FB;border:2px dashed #2980B9;border-radius:6px;padding:14px 18px;margin-bottom:15px;box-shadow:0 1px 3px rgba(0,0,0,0.04)">
        <form action="/production/work-orders/operate?agid=<?=$agId?><?=($currentMode !== '' ? '&mode=' . urlencode($currentMode) : '')?>" method="post" style="margin:0;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <input type="hidden" name="_csrf" value="<?=csrfToken()?>">
            <input type="hidden" name="agid" value="<?=$agId?>">
            <input type="hidden" name="return_mode" value="<?=htmlspecialchars($currentMode)?>">

            <div style="font-size:22px;color:#2980B9">
                <i class="fa fa-barcode"></i> <i class="fa fa-qrcode"></i>
            </div>
            <div style="font-weight:bold;color:#1B4F72;font-size:14px;white-space:nowrap">
                <?=htmlspecialchars($label)?>
            </div>

            <input type="text" name="scan_machine_code" id="scan_machine_code_<?=htmlspecialchars($currentMode ?: 'main')?>"
                   placeholder="<?=htmlspecialchars($ph)?>"
                   style="flex:1;min-width:300px;padding:9px 14px;font-size:15px;border:1px solid #2980B9;border-radius:4px;font-weight:bold;background:#FFFFFF"
                   onkeydown="if(event.key === 'Enter'){ this.form.submit(); }">
            <button type="submit" class="btngreen" style="padding:9px 18px;font-size:14px;font-weight:bold;cursor:pointer;border:none;border-radius:4px;display:inline-flex;align-items:center;gap:6px">
                <i class="fa fa-search"></i> Escanear / Cargar
            </button>
        </form>
    </div>

    <?php if (!empty($_GET['scan_ok'])): ?>
        <div style="background:#D4EFDF;border:1px solid #27AE60;color:#145A32;padding:12px 16px;border-radius:6px;margin-bottom:15px;font-size:14px">
            <i class="fa fa-check-circle"></i> <b>Bobina escaneada y montada con éxito:</b> '<?=htmlspecialchars((string)($_GET['scanned_code'] ?? ''))?>'<?php if (!empty($_GET['m_kg'])): ?> (<b><?=number_format((float)$_GET['m_kg'], 2, ',', '.')?> kg</b>)<?php endif; ?> cargada en la máquina con trazabilidad activa.
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['already_mounted'])): ?>
        <div style="background:#EFF6FF;border:1px solid #3B82F6;color:#1E40AF;padding:12px 16px;border-radius:6px;margin-bottom:15px;font-size:14px">
            <i class="fa fa-info-circle"></i> <b>Bobina ya montada:</b> '<?=htmlspecialchars((string)($_GET['scanned_code'] ?? ''))?>' ya se encuentra montada en esta máquina.
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['scan_err'])): ?>
        <div style="background:#FADBD8;border:1px solid #E74C3C;color:#78281F;padding:12px 16px;border-radius:6px;margin-bottom:15px;font-size:14px">
            <i class="fa fa-exclamation-triangle"></i> <b>Error al escanear bobina:</b> <?=htmlspecialchars((string)$_GET['scan_err'])?>
        </div>
    <?php endif; ?>
    <?php

