<?php
/**
 * Parcial · Alertas superiores de consola de operador
 * @var array<string, mixed> $agenda
 */
?>
    <?php if (!empty($_GET['roll_created'])): ?>
        <div style="background:#E8F8F5;border:1.5px solid #2ECC71;color:#117A65;padding:16px 20px;border-radius:6px;margin-bottom:18px;font-size:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <i class="fa fa-fw fa-check-circle" style="font-size:20px;color:#27AE60"></i>
                <b>¡Bobina semielaborada generada exitosamente!</b> Se registró <b><?=htmlspecialchars($_GET['roll_code'] ?? ('ID #' . $_GET['roll_created']))?></b> en la OT #<b><?=htmlspecialchars((string)$agenda['prd_number'])?></b>.
            </div>
            <div style="display:flex;gap:8px">
                <a href="/rolls/<?=(int)$_GET['roll_created']?>/label?server_print=1" target="_blank" class="btnblue" style="text-decoration:none;font-weight:bold">
                    <i class="fa fa-print"></i> 🖨️ Mandar a Imprimir en Zebra
                </a>
                <a href="/rolls/<?=(int)$_GET['roll_created']?>/label" target="_blank" class="btngrey" style="text-decoration:none;font-weight:bold">
                    <i class="fa fa-eye"></i> Vista Previa (100×70)
                </a>
                <a href="/production/traceability?q=<?=urlencode((string)($_GET['roll_code'] ?? ''))?>" target="_blank" class="btngreen" style="text-decoration:none;font-weight:bold">
                    <i class="fa fa-sitemap"></i> 🌳 Trazabilidad
                </a>
            </div>
        </div>
    <?php endif; ?>

    <script>
    // Imprime las etiquetas de cajas con código en la térmica (puente local); si no hay puente, abre la primera etiqueta.
    window.unibagPrintBoxLabels = window.unibagPrintBoxLabels || async function (ids, btn) {
        if (!ids || !ids.length) return;
        if (btn) btn.disabled = true;
        try {
            var fd = new FormData();
            fd.append('_csrf', <?=json_encode(csrfToken())?>);
            fd.append('ids', ids.join('-'));
            var resp = await fetch('/production/boxes/print-job', {method: 'POST', body: fd, credentials: 'same-origin'});
            var data = await resp.json();
            if (!data.ok) throw new Error(data.error || 'No se pudo preparar la impresión.');
            if (window.UnibagHardware && typeof window.UnibagHardware.printJob === 'function') {
                var res = await window.UnibagHardware.printJob(data.print_job, {fallbackUrl: '/boxes/' + ids[0] + '/label'});
                if (!res || !res.ok) alert('No se pudo imprimir en la térmica. Usa el botón 100×70 de cada caja.');
            } else {
                window.open('/boxes/' + ids[0] + '/label', '_blank');
            }
        } catch (e) {
            alert('Error al imprimir: ' + (e && e.message ? e.message : e));
        } finally {
            if (btn) btn.disabled = false;
        }
    };
    </script>
    <?php if (!empty($_GET['box_labels'])):
        $bannerBoxIds = array_values(array_filter(array_map('intval', explode('-', (string)$_GET['box_labels'])))); ?>
        <div style="background:#E8F8F5;border:1.5px solid #2ECC71;color:#117A65;padding:14px 18px;border-radius:6px;margin-bottom:18px;font-size:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div><i class="fa fa-fw fa-check-circle" style="font-size:18px;color:#27AE60"></i>
                <b>Embalaje cerrado:</b> se crearon <?=count($bannerBoxIds)?> caja(s) con código. Imprime sus etiquetas y pégalas en cada caja.</div>
            <button type="button" class="btnblue" onclick="unibagPrintBoxLabels(<?=htmlspecialchars(json_encode($bannerBoxIds))?>, this)"><i class="fa fa-print"></i> Imprimir etiquetas de cajas</button>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['supply_warning'])): ?>
        <div style="background:#FFFBEB;border:1.5px solid #F59E0B;color:#92400E;padding:14px 18px;border-radius:6px;margin-bottom:18px;font-size:14px">
            <i class="fa fa-fw fa-exclamation-triangle" style="font-size:18px;color:#D97706"></i>
            <b>Aviso de stock:</b> <?=htmlspecialchars((string)$_GET['supply_warning'])?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])): ?>
        <div style="background:#FDECEC;border:1.5px solid #EF4444;color:#991B1B;padding:14px 18px;border-radius:6px;margin-bottom:18px;font-size:14px">
            <i class="fa fa-fw fa-exclamation-triangle" style="font-size:18px;color:#DC2626"></i>
            <b>Error:</b> <?=htmlspecialchars((string)$_GET['error'])?>
        </div>
    <?php endif; ?>
