<?php
    ?>
    <div style="font-size:20px;font-weight:bold;color:#115452;margin-bottom:15px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div>
            <i class="fa fa-warehouse" style="color:#00A85A"></i> Inventario de Bobinas e Insumos en Bodega 3000 (Producción)
        </div>
        <div style="font-size:13px;font-weight:normal">
            <a href="/production/materials/request" class="btngreen" style="text-decoration:none;padding:7px 16px;font-size:12px">
                <i class="fa fa-dolly"></i> Solicitar / Cargar a OT en Curso
            </a>
        </div>
    </div>

    <!-- TARJETAS DE RESUMEN -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:15px;margin-bottom:18px">
        <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:16px;border-left:4px solid #00A85A">
            <div style="font-size:12px;color:#64748B;font-weight:bold;text-transform:uppercase">Total Bobinas / Ítems</div>
            <div style="font-size:24px;font-weight:bold;color:#0F766E;margin-top:4px"><?=number_format(count($bodega3000Rolls), 0, ',', '.')?></div>
            <div style="font-size:11px;color:#94A3B8;margin-top:4px">Disponibles en Planta</div>
        </div>
        <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:16px;border-left:4px solid #0284C7">
            <div style="font-size:12px;color:#64748B;font-weight:bold;text-transform:uppercase">Stock Total en Peso</div>
            <div style="font-size:24px;font-weight:bold;color:#0284C7;margin-top:4px"><?=number_format($totalKgs, 1, ',', '.')?> kg</div>
            <div style="font-size:11px;color:#94A3B8;margin-top:4px">Materia prima e insumos</div>
        </div>
        <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:16px;border-left:4px solid #D97706">
            <div style="font-size:12px;color:#64748B;font-weight:bold;text-transform:uppercase">Bodegas ERP Incluidas</div>
            <div style="font-size:14px;font-weight:bold;color:#92400E;margin-top:6px">16 (Insumos Prod) &bull; 27 (MP Prod)</div>
            <div style="font-size:11px;color:#94A3B8;margin-top:4px">Trazabilidad sincronizada</div>
        </div>
    </div>

    <!-- TABLA DE BOBINAS BODEGA 3000 -->
    <div style="background:#FFFFFF;border:1px solid #CBD5E1;border-radius:6px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,0.05)">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:15px;flex-wrap:wrap;gap:10px">
            <div style="font-size:14px;font-weight:bold;color:#0F766E">
                <i class="fa fa-boxes"></i> Detalle de Bobinas Físicas en Bodega 3000
            </div>
            <div>
                <input type="text" id="filter_b3000_full" placeholder="🔍 Filtrar por código, tela, color..." class="inptxt" style="width:300px;font-size:12px;padding:6px 12px" onkeyup="filterBodega3000(this.value)">
            </div>
        </div>

        <div style="overflow-x:auto">
            <table id="tbl_b3000_full" border="0" width="100%" cellpadding="8" cellspacing="0" style="min-width:1000px">
            <colgroup>
                <col width="160">
                <col width="100">
                <col>
                <col width="130">
                <col width="120">
                <col width="110">
                <col width="100">
                <col width="110">
                <col width="140">
            </colgroup>
            <thead>
                <tr>
                    <td class="tdheader">Código / Identificador</td>
                    <td class="tdheader" align="center">Material</td>
                    <td class="tdheader">Descripción</td>
                    <td class="tdheader">Bodega ERP / TRZ</td>
                    <td class="tdheader">Color</td>
                    <td class="tdheader" align="center">Ancho / Gramaje</td>
                    <td class="tdheader" align="right">Metros</td>
                    <td class="tdheader" align="right">Stock / Kilos</td>
                    <td class="tdheader" align="center">Acción</td>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($bodega3000Rolls)): ?>
                <tr>
                    <td colspan="9" class="tdnrm" style="text-align:center;padding:35px 20px;color:#64748B">
                        <i class="fa fa-box-open" style="font-size:28px;color:#94A3B8;margin-bottom:8px;display:block"></i>
                        No hay bobinas registradas actualmente en Bodega 3000.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($bodega3000Rolls as $r):
                    $rCode = (string)($r['roll_code'] ?? $r['sku_code'] ?? '-');
                    $rMat = (string)($r['roll_material'] ?? 'TNT');
                    $rDesc = (string)($r['sku_desc'] ?? '-');
                    $whName = (string)($r['warehouse_name'] ?? 'Bodega 3000');
                    $rCol = (string)($r['color'] ?? '-');
                    $rW = (float)($r['width_cm'] ?? round(($r['width_mm'] ?? 0) / 10));
                    $rGramms = (int)($r['microns'] ?? 80);
                    $rMts = (float)($r['meters'] ?? 0);
                    $rKg = (float)($r['weight_kg'] ?? $r['current_weight_kg'] ?? 0);
                    $rawId = (string)($r['id'] ?? $r['raw_id'] ?? '');
                ?>
                    <tr onmouseover="this.style.backgroundColor='#F0FDF4'" onmouseout="this.style.backgroundColor='transparent'">
                        <td class="tdnrm"><b><?=htmlspecialchars($rCode)?></b></td>
                        <td class="tdnrm" align="center"><span style="background:#E2E8F0;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:bold"><?=htmlspecialchars($rMat)?></span></td>
                        <td class="tdnrm" style="font-size:12px"><?=htmlspecialchars($rDesc)?></td>
                        <td class="tdnrm" style="font-size:11px;color:#64748B"><?=htmlspecialchars($whName)?></td>
                        <td class="tdnrm"><?=htmlspecialchars($rCol)?></td>
                        <td class="tdnrm" align="center" style="white-space:nowrap"><?=$rW > 0 ? "{$rW} cm" : '-'?> &bull; <?=$rGramms > 0 ? "{$rGramms}g" : '-'?></td>
                        <td class="tdnrm" align="right"><?=$rMts > 0 ? number_format($rMts, 0, ',', '.') . ' m' : '-'?></td>
                        <td class="tdnrm" align="right"><b style="color:#0F766E"><?=number_format($rKg, 1, ',', '.')?> kg</b></td>
                        <td class="tdnrm" align="center">
                            <a href="/production/materials/request?roll_id=<?=rawurlencode($rawId)?>" class="btnblue" style="padding:4px 10px;font-size:11px;text-decoration:none">
                                <i class="fa fa-dolly"></i> Asignar a OT
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            </table>
        </div>
    </div>

    <script>
    function filterBodega3000(query) {
        query = query.toLowerCase();
        var rows = document.querySelectorAll('#tbl_b3000_full tbody tr');
        rows.forEach(function(tr) {
            var text = tr.innerText.toLowerCase();
            tr.style.display = (text.indexOf(query) !== -1 || query === '') ? '' : 'none';
        });
    }
    </script>
    <?php

