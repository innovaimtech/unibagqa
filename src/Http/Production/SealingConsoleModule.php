<?php

declare(strict_types=1);

// =============================================================================
// Módulo HTTP · Consola Operativa de Corte y Sellado, Embalaje, Cajas y Pallets
// (src/Http/Production/SealingConsoleModule.php)
// =============================================================================

function renderStartPackagingButton(int $agId, int $workerOtId, string $style): void
{
    echo '<a href="/production/work-orders/operate?agid=' . $agId . '&amp;mode=production&amp;overrideembalaje=1&amp;action=start"'
        . ' class="btngrey" style="text-decoration:none;' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '">Embalaje</a>';
}

function renderProductionReceiverField(array $receivers, array $saved, bool $isRequired = false): void
{
    $savedReceiverId = (int)($saved['receiver_worker_id'] ?? 0);
    $savedReceiverName = trim((string)($saved['receiver_name'] ?? ''));

    echo '<tr><td class="tdleft">Recibe producción</td><td class="tdnrm">';
    echo '<select name="phase_receiver_worker_id" class="inptxt" style="width:100%"' . ($isRequired ? ' required' : '') . '>';
    echo '<option value="">-- Seleccionar quién recibe ' . ($isRequired ? '' : '(opcional)') . ' --</option>';
    foreach ($receivers as $receiver) {
        $rId = (int)$receiver['id'];
        $rName = htmlspecialchars(trim($receiver['wrk_firstname'] . ' ' . $receiver['wrk_lastname']), ENT_QUOTES, 'UTF-8');
        $selected = ($savedReceiverId > 0 && $savedReceiverId === $rId) ? ' selected="selected"' : '';
        echo '<option value="' . $rId . '"' . $selected . '>' . $rName . '</option>';
    }
    echo '</select>';
    if ($savedReceiverName !== '') {
        echo '<div style="font-size:11px;color:#64748b;margin-top:4px"><i class="fa fa-info-circle"></i> Asignado previamente: <b>'
            . htmlspecialchars($savedReceiverName, ENT_QUOTES, 'UTF-8')
            . '</b> (puedes cambiarlo si otra persona recibe al finalizar)</div>';
    }
    echo '</td></tr>';
}

function renderPackagingProductionFields(
    array $agenda,
    array $saved,
    array $closureTypes,
    array $mixedOrders = [],
    array $savedMixed = []
): void {
    echo renderView('production/sealing/packaging_fields', get_defined_vars());
}

function renderSimpleProductionComment(string $comment): string
{
    // Display only: keep stored technical text intact for traceability.
    // Solo visualizacion: conservar el texto tecnico original en trazabilidad.
    $comment = trim(preg_replace('/\\[TRZ-MAN:[a-f0-9]{32}\\]/', '', $comment) ?? $comment);
    $comment = preg_replace_callback('/\\[SERI_COUNTERS:\\s*(.*?)\\]/', static function ($match): string {
        $parts = [];
        if (preg_match_all('/C\\d+:([^=,|]+)=(\\d+)/', $match[1], $counters, PREG_SET_ORDER)) {
            foreach ($counters as $counter) $parts[] = trim($counter[1]) . ': ' . $counter[2];
        }
        if (preg_match('/img\\/bast=(\\d+)/', $match[1], $factor)) $parts[] = $factor[1] . ' imagenes por bastidor';
        return 'Contadores de impresion: ' . implode(', ', $parts) . '.';
    }, $comment) ?? $comment;
    $comment = strtr($comment, [
        'Inicio de tiraje de producción' => 'Inicio de produccion',
        'Bobina Semielaborada' => 'Bobina procesada',
        'ROLL_RETURNED_TO_WAREHOUSE' => 'Bobina devuelta a bodega',
        'ROLL_DISMOUNTED' => 'Bobina desmontada',
        'ROLL_MOUNTED' => 'Bobina montada',
        'ROLL-CUT' => 'Rollo cortado',
        'Override' => 'Embalaje', 'overrideembalaje' => 'embalaje',
        'Kg/m' => 'kg por metro', 'Obs:' => 'Observacion:',
        '->' => ' pasa a ', ' | ' => '. ',
        'erp_item_id' => 'Articulo', 'sku_id' => 'Producto',
        'warehouse_id' => 'Bodega', 'roll_id' => 'Bobina',
        'evt_amount' => 'Cantidad', 'worker_ot_id' => 'OT',
    ]);
    return $comment;
}

function renderInternalFabricationNoteRow(array $agenda, int $labelColumns = 1, int $valueColumns = 3): void
{
    $note = trim((string)($agenda['req_desc_intern'] ?? ''));
    $noteHtml = $note !== ''
        ? nl2br(htmlspecialchars($note, ENT_QUOTES, 'UTF-8'))
        : '<span style="color:#999">-</span>';
    echo '<tr><td class="tdleft" colspan="' . $labelColumns . '" style="font-size:15px;font-weight:800;color:#0f172a">Observación interna</td>'
        . '<td class="tdnrm" colspan="' . $valueColumns . '" style="white-space:normal;overflow-wrap:anywhere;line-height:1.6;font-size:15px;font-weight:800;color:#111827">'
        . $noteHtml . '</td></tr>';
}

function renderMachineFabricationExtras(array $agenda, bool $showPackaging): void
{
    $escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    echo '<table width="100%" cellpadding="6" cellspacing="0" style="background:white;border-top:1px solid #ddd">';
    renderInternalFabricationNoteRow($agenda);
    if ($showPackaging) {
        echo '<tr><td colspan="4" class="tdleft" style="background:#1AAAA4;color:white;font-weight:800">Datos Embalaje</td></tr>';
        $fields = [
            'Mezcla' => $agenda['reqnum_mezcla_1'] ?? 'Sin Mezcla',
            'Medida de caja' => $agenda['req_embalaje_medidas_caja'] ?? '-',
            'Logotipo empresa' => $agenda['req_caja_impresa'] ?? '-',
            'Cajas por pallet' => $agenda['req_embalaje_cajas_por_pallet_amt'] ?? 0,
            'Cajas a utilizar' => $agenda['req_embalaje_cajas_completas_amt'] ?? 0,
            'Unidades por caja' => $agenda['req_embalaje_bolsas_por_caja_amt'] ?? 0,
            'Unidades caja final' => $agenda['req_embalaje_caja_final'] ?? 0,
            'Total pedido' => $agenda['ag_amount'] ?? 0,
        ];
        $index = 0;
        foreach ($fields as $label => $value) {
            if ($index % 2 === 0) echo '<tr>';
            echo '<td class="tdleft">' . $escape($label) . '</td><td class="tdnrm"><b>'
                . $escape(is_numeric($value) ? number_format((float)$value, 0, ',', '.') : $value) . '</b></td>';
            if ($index++ % 2 === 1) echo '</tr>';
        }
    }
    echo '</table>';
}


function renderDesignAndAttachmentsHtml(array $agenda, string $medidasStr = '', string $fullDesign = '', int $labelColspan = 1, int $valueColspan = 1, string $contextLabel = 'Imagen Diseño'): string
{
    return renderView('production/partials/design_attachments', get_defined_vars());
}


function renderSealingConsoleInfoSection(array $__vars): void
{
    echo renderView('production/sealing/info_section', $__vars);
}

function renderSealingPackagingSubformSection(array $__vars): void
{
    echo renderView('production/sealing/packaging_subform', $__vars);
}
