<?php
    ?>
    <div style="font-size:20px;margin-bottom:10px"><b>Ordenes de trabajo</b></div>
    <table border="0" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:15px">
    <tr>
        <td style="background-color:#FFFFFF;padding:12px 15px;color:#666666;font-size:14px;border-radius:5px;border:1px solid #DDDDDD">
            <i class="fa fa-fw fa-calendar-alt"></i> Ordenes de trabajo
            <i class="fa fa-fw fa-chevron-right" style="font-size:12px;margin:0 5px"></i>
            <b>Histórico</b> de mis últimos 100 registros
        </td>
    </tr>
    </table>

    <div style="background-color:#FFFFFF;border:1px solid #CCCCCC;border-radius:4px;padding:20px">
        <table border="0" width="100%" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <td class="tdheader">N° OT</td>
                <td class="tdheader">N° CC</td>
                <td class="tdheader">Máquina</td>
                <td class="tdheader">Cliente / Producto</td>
                <td class="tdheader">Fecha</td>
                <td class="tdheader">Evento / Descripción</td>
                <td class="tdheader">Cantidad / Metros</td>
                <td class="tdheader">Tiempo</td>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($historyList)): ?>
            <tr><td colspan="8" class="tdnrm" style="text-align:center;padding:25px;color:#999">No hay registros en tu historial.</td></tr>
        <?php else: ?>
            <?php foreach ($historyList as $row): ?>
                <tr>
                    <td class="tdnrm"><b><?=htmlspecialchars((string)$row['prd_number'])?></b></td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$row['req_number'])?></td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$row['equipo_name'])?></td>
                    <td class="tdnrm"><?=htmlspecialchars((string)$row['cust_name'])?> · <?=htmlspecialchars((string)$row['item_title'])?></td>
                    <td class="tdnrm"><?=date('d/m/Y H:i', (int)$row['evt_crtdat'])?></td>
                    <td class="tdnrm">
                        <b><?=htmlspecialchars(match ((string)$row['evt_type']) {
                            'prod', 'prodsericolor' => 'Producción', 'apertura' => 'Alistamiento',
                            'pause' => 'Pausa', 'mantencion' => 'Mantención', 'materiales' => 'Materiales',
                            default => (string)$row['evt_type'],
                        })?></b><br><?=nl2br(htmlspecialchars(renderSimpleProductionComment(trim((string)$row['cause_title'] . ' ' . (string)($row['evt_comments'] ?? '')))))?>
                        <?php foreach ($row['defects'] ?? [] as $defect): ?>
                            <div><b><?=htmlspecialchars((string)$defect['cause_title'])?></b>: <?=number_format((float)$defect['evt_amount'], 0, ',', '.')?> unid. / <?=number_format((float)($defect['evt_kgstounits'] ?? 0), 2, ',', '.')?> kg</div>
                        <?php endforeach; ?>
                    </td>
                    <td class="tdnrm"><?=number_format((float)$row['evt_amount'], 0, ',', '.')?> unid.<br><?=number_format((float)($row['evt_amount_metros_lineales'] ?? 0), 0, ',', '.')?> m</td>
                    <?php $minutes = (int)floor(max(0, ((int)($row['evt_enddat'] ?: time()) - (int)$row['evt_crtdat'])) / 60); ?>
                    <td class="tdnrm"><?=intdiv($minutes, 60)?>h <?=$minutes % 60?>m</td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
        </table>
    </div>
    <?php

