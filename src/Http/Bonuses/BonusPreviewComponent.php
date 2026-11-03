<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Componente de vista previa de planillas de bonos (Flexo, Seri, CYS)
 * (src/Http/Bonuses/BonusPreviewComponent.php)
 * =============================================================================
 *
 * Antes la pantalla mostraba solo las primeras 12 filas "planas" y era necesario descargar el Excel
 * para entender el período. Este componente muestra, con TODAS las filas del período:
 *   1. Tarjetas resumen (registros, operadores, unidades, cumplimiento).
 *   2. Ranking por operador (con barra de participación y filtro con un clic).
 *   3. Detalle agrupado por operador (desplegable), ordenado por fecha, con subtotales.
 *
 * Es solo presentación: no cambia ningún cálculo de bonos ni la descarga a Excel.
 */

/** Hoja de estilos del componente (prefijo bpv- para no chocar con otros estilos). */
function bonusPreviewStyles(): string
{
    return '<style>
.bpv{margin-top:16px}
.bpv-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:16px}
.bpv-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:12px 14px;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.bpv-card .k{font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#64748b}
.bpv-card .v{font-size:24px;font-weight:900;color:#0f172a;margin-top:2px;line-height:1.15}
.bpv-card .s{font-size:12px;color:#64748b;margin-top:2px}
.bpv-card.teal{border-top:3px solid #00A9A6}.bpv-card.blue{border-top:3px solid #3b82f6}.bpv-card.amber{border-top:3px solid #f59e0b}.bpv-card.green{border-top:3px solid #16a34a}
.bpv-title{font-size:14px;font-weight:900;color:#1e293b;margin:18px 0 8px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.bpv-title small{font-weight:600;color:#64748b}
.bpv-wrap{overflow-x:auto;border:1px solid #e2e8f0;border-radius:12px;background:#fff}
.bpv-table{width:100%;border-collapse:collapse;font-size:13px}
.bpv-table th{position:sticky;top:0;background:#f1f5f9;color:#334155;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.03em;padding:9px 10px;border-bottom:1px solid #e2e8f0;text-align:left;white-space:nowrap}
.bpv-table td{padding:8px 10px;border-bottom:1px solid #f1f5f9;color:#1f2937;vertical-align:middle}
.bpv-table tbody tr:nth-child(even) td{background:#fafcff}
.bpv-table tbody tr:hover td{background:#f0fdfa}
.bpv-table .c{text-align:center}.bpv-table .r{text-align:right;font-variant-numeric:tabular-nums}
.bpv-table tfoot td{background:#f8fafc;font-weight:900;border-top:2px solid #cbd5e1}
.bpv-rank{width:34px;text-align:center;font-weight:900;color:#94a3b8}
.bpv-op a{color:#0f766e;font-weight:800;text-decoration:none}.bpv-op a:hover{text-decoration:underline}
.bpv-bar{height:8px;border-radius:6px;background:#e2e8f0;min-width:90px;overflow:hidden}
.bpv-bar>i{display:block;height:100%;background:linear-gradient(90deg,#00A9A6,#22c5c3)}
.bpv-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:800;white-space:nowrap}
.bpv-ok{background:#dcfce7;color:#166534}.bpv-mid{background:#fef3c7;color:#92400e}.bpv-low{background:#fee2e2;color:#991b1b}.bpv-na{background:#f1f5f9;color:#64748b}
.bpv-group{border:1px solid #e2e8f0;border-radius:12px;margin-bottom:10px;background:#fff;overflow:hidden}
.bpv-group>summary{cursor:pointer;list-style:none;display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:11px 14px;background:#f8fafc;font-weight:800;color:#0f172a}
.bpv-group>summary::-webkit-details-marker{display:none}
.bpv-group>summary::before{content:"▸";color:#00A9A6;font-size:14px;transition:transform .15s}
.bpv-group[open]>summary::before{transform:rotate(90deg)}
.bpv-group>summary .grow{flex:1;min-width:160px}
.bpv-group>summary .pill{font-size:12px;font-weight:700;color:#475569;background:#fff;border:1px solid #e2e8f0;border-radius:999px;padding:2px 10px}
.bpv-group .bpv-wrap{border:0;border-radius:0;border-top:1px solid #e2e8f0}
.bpv-note{font-size:12px;color:#64748b;margin-top:8px}
.bpv-empty{padding:18px;border:1px dashed #cbd5e1;border-radius:12px;color:#64748b;background:#fff}
.bpv-filterbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;padding:8px 12px;background:#ecfeff;border:1px solid #a5f3fc;border-radius:10px;font-size:13px;color:#155e75}
.bpv-filterbar a{font-weight:800;color:#0e7490}
</style>';
}

/** Número entero con separador de miles chileno (1.234.567). */
function bonusPreviewInt(float $value): string
{
    return number_format((float)round($value), 0, ',', '.');
}

/** Fecha Y-m-d a d/m/Y; si no tiene ese formato se deja tal cual. */
function bonusPreviewDate(string $value): string
{
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m) === 1) {
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }
    return $value;
}

/** Etiqueta de cumplimiento (producidas vs solicitadas) con color: verde >=100 %, ámbar >=90 %, rojo <90 %. */
function bonusPreviewComplianceBadge(float $produced, float $requested): string
{
    if ($requested <= 0) {
        return '<span class="bpv-badge bpv-na" title="Sin cantidad solicitada">s/d</span>';
    }
    $pct = $produced / $requested * 100.0;
    $class = $pct >= 100.0 ? 'bpv-ok' : ($pct >= 90.0 ? 'bpv-mid' : 'bpv-low');
    return '<span class="bpv-badge ' . $class . '" title="Impresas / Solicitadas">' . h(number_format($pct, 1, ',', '.')) . '%</span>';
}

/**
 * Dibuja el panel de vista previa de una planilla.
 *
 * Opciones de $cfg:
 * - title (string): nombre de la planilla, ej. "Flexo".
 * - columns (list): columnas del detalle; cada una ['label'=>string, 'value'=>callable(array):string, 'align'=>'l|c|r', 'strong'=>bool].
 * - operator_key / units_key / date_key / order_key: campos de la fila (operador, unidades, fecha, N° OT).
 * - requested_key (?string): campo de unidades solicitadas; si es null no se muestra cumplimiento.
 * - units_label (string): rótulo de las unidades (ej. "Impresas", "Producidas").
 * - operator_url (callable(string):string): URL para filtrar por un operador.
 * - clear_url (string): URL para quitar el filtro de operador.
 * - selected_operator (string): operador filtrado actualmente.
 * - max_rows (int): tope de filas dibujadas (el resto se descarga por Excel).
 *
 * @param list<array<string,mixed>> $rows
 * @param array<string,mixed> $cfg
 */
function bonusRenderPreviewPanel(array $rows, array $cfg): string
{
    $opKey = (string)($cfg['operator_key'] ?? 'operator_name');
    $unitsKey = (string)($cfg['units_key'] ?? 'produced_units');
    $dateKey = (string)($cfg['date_key'] ?? 'event_date');
    $orderKey = (string)($cfg['order_key'] ?? 'work_order_number');
    $reqKey = $cfg['requested_key'] ?? null;
    $unitsLabel = (string)($cfg['units_label'] ?? 'Unidades');
    $columns = (array)($cfg['columns'] ?? []);
    $selected = trim((string)($cfg['selected_operator'] ?? ''));
    $maxRows = max(20, (int)($cfg['max_rows'] ?? 400));
    $operatorUrl = $cfg['operator_url'] ?? null;

    if ($rows === []) {
        return '<div class="bpv-empty">Sin registros en el período seleccionado.</div>';
    }

    // Agrupación y totales por operador.
    $groups = [];
    $totalUnits = 0.0;
    $totalReq = 0.0;
    $allOrders = [];
    foreach ($rows as $r) {
        $name = trim((string)($r[$opKey] ?? ''));
        if ($name === '') {
            $name = 'Sin operador';
        }
        $units = (float)($r[$unitsKey] ?? 0);
        $req = $reqKey !== null ? (float)($r[$reqKey] ?? 0) : 0.0;
        $order = trim((string)($r[$orderKey] ?? ''));
        if (!isset($groups[$name])) {
            $groups[$name] = ['rows' => [], 'units' => 0.0, 'req' => 0.0, 'orders' => []];
        }
        $groups[$name]['rows'][] = $r;
        $groups[$name]['units'] += $units;
        $groups[$name]['req'] += $req;
        if ($order !== '') {
            $groups[$name]['orders'][$order] = true;
            $allOrders[$order] = true;
        }
        $totalUnits += $units;
        $totalReq += $req;
    }
    uasort($groups, static fn(array $a, array $b): int => $b['units'] <=> $a['units']);
    $maxUnits = max(array_map(static fn(array $g): float => (float)$g['units'], $groups) ?: [1.0]);
    $maxUnits = $maxUnits > 0 ? $maxUnits : 1.0;

    $html = '<div class="bpv">';

    if ($selected !== '') {
        $html .= '<div class="bpv-filterbar">Mostrando solo a <strong>' . h($selected) . '</strong>. <a href="' . h((string)($cfg['clear_url'] ?? '#')) . '">Quitar filtro y ver todos</a></div>';
    }

    // 1) Tarjetas resumen
    $html .= '<div class="bpv-cards">';
    $html .= '<div class="bpv-card teal"><div class="k">Registros</div><div class="v">' . h(bonusPreviewInt((float)count($rows))) . '</div><div class="s">' . h(bonusPreviewInt((float)count($allOrders))) . ' órdenes de trabajo</div></div>';
    $html .= '<div class="bpv-card blue"><div class="k">Operadores</div><div class="v">' . h(bonusPreviewInt((float)count($groups))) . '</div><div class="s">con producción en el período</div></div>';
    $html .= '<div class="bpv-card green"><div class="k">' . h($unitsLabel) . '</div><div class="v">' . h(bonusPreviewInt($totalUnits)) . '</div><div class="s">unidades en total</div></div>';
    if ($reqKey !== null) {
        $pct = $totalReq > 0 ? ($totalUnits / $totalReq * 100.0) : 0.0;
        $html .= '<div class="bpv-card amber"><div class="k">Cumplimiento</div><div class="v">' . h(number_format($pct, 1, ',', '.')) . '%</div><div class="s">' . h(bonusPreviewInt($totalUnits)) . ' de ' . h(bonusPreviewInt($totalReq)) . ' solicitadas</div></div>';
    }
    $html .= '</div>';

    // 2) Ranking por operador
    $html .= '<div class="bpv-title">Resumen por operador <small>Haz clic en un operador para filtrar</small></div>';
    $html .= '<div class="bpv-wrap"><table class="bpv-table"><thead><tr><th class="c">#</th><th>Operador</th><th class="r">Registros</th><th class="r">OT</th><th class="r">' . h($unitsLabel) . '</th><th>Participación</th>';
    if ($reqKey !== null) {
        $html .= '<th class="c">Cumplimiento</th>';
    }
    $html .= '</tr></thead><tbody>';
    $rank = 0;
    foreach ($groups as $name => $g) {
        $rank++;
        $share = $totalUnits > 0 ? ($g['units'] / $totalUnits * 100.0) : 0.0;
        $barWidth = max(2.0, $g['units'] / $maxUnits * 100.0);
        $nameHtml = h((string)$name);
        if (is_callable($operatorUrl) && $name !== 'Sin operador' && $selected === '') {
            $nameHtml = '<a href="' . h((string)$operatorUrl((string)$name)) . '">' . $nameHtml . '</a>';
        }
        $html .= '<tr><td class="bpv-rank">' . $rank . '</td><td class="bpv-op">' . $nameHtml . '</td>'
            . '<td class="r">' . h(bonusPreviewInt((float)count($g['rows']))) . '</td>'
            . '<td class="r">' . h(bonusPreviewInt((float)count($g['orders']))) . '</td>'
            . '<td class="r"><strong>' . h(bonusPreviewInt((float)$g['units'])) . '</strong></td>'
            . '<td><div style="display:flex;align-items:center;gap:8px"><div class="bpv-bar" style="flex:1"><i style="width:' . h(number_format($barWidth, 1, '.', '')) . '%"></i></div><span style="font-size:12px;color:#475569;min-width:46px;text-align:right">' . h(number_format($share, 1, ',', '.')) . '%</span></div></td>';
        if ($reqKey !== null) {
            $html .= '<td class="c">' . bonusPreviewComplianceBadge((float)$g['units'], (float)$g['req']) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody><tfoot><tr><td></td><td>TOTAL</td><td class="r">' . h(bonusPreviewInt((float)count($rows))) . '</td><td class="r">' . h(bonusPreviewInt((float)count($allOrders))) . '</td><td class="r">' . h(bonusPreviewInt($totalUnits)) . '</td><td></td>';
    if ($reqKey !== null) {
        $html .= '<td class="c">' . bonusPreviewComplianceBadge($totalUnits, $totalReq) . '</td>';
    }
    $html .= '</tr></tfoot></table></div>';

    // 3) Detalle agrupado por operador (desplegable, ordenado por fecha)
    $html .= '<div class="bpv-title">Detalle por operador <small>Toca el nombre para abrir o cerrar</small></div>';
    $drawn = 0;
    $first = true;
    foreach ($groups as $name => $g) {
        $groupRows = $g['rows'];
        usort($groupRows, static function (array $a, array $b) use ($dateKey, $orderKey): int {
            return [(string)($a[$dateKey] ?? ''), (string)($a[$orderKey] ?? '')] <=> [(string)($b[$dateKey] ?? ''), (string)($b[$orderKey] ?? '')];
        });
        $open = ($first || $selected !== '') ? ' open' : '';
        $first = false;
        $html .= '<details class="bpv-group"' . $open . '><summary><span class="grow">' . h((string)$name) . '</span>'
            . '<span class="pill">' . count($groupRows) . ' registros</span>'
            . '<span class="pill">' . h(bonusPreviewInt((float)$g['units'])) . ' ' . h(strtolower($unitsLabel)) . '</span></summary>';
        $html .= '<div class="bpv-wrap"><table class="bpv-table"><thead><tr>';
        foreach ($columns as $col) {
            $al = (string)($col['align'] ?? 'l');
            $html .= '<th' . ($al === 'c' ? ' class="c"' : ($al === 'r' ? ' class="r"' : '')) . '>' . h((string)($col['label'] ?? '')) . '</th>';
        }
        if ($reqKey !== null) {
            $html .= '<th class="c">Cumpl.</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($groupRows as $r) {
            if ($drawn >= $maxRows) {
                break;
            }
            $drawn++;
            $html .= '<tr>';
            foreach ($columns as $col) {
                $al = (string)($col['align'] ?? 'l');
                $val = (string)(is_callable($col['value'] ?? null) ? $col['value']($r) : '');
                $style = !empty($col['strong']) ? ' style="font-weight:800"' : '';
                $html .= '<td' . ($al === 'c' ? ' class="c"' : ($al === 'r' ? ' class="r"' : '')) . $style . '>' . h($val) . '</td>';
            }
            if ($reqKey !== null) {
                $html .= '<td class="c">' . bonusPreviewComplianceBadge((float)($r[$unitsKey] ?? 0), (float)($r[$reqKey] ?? 0)) . '</td>';
            }
            $html .= '</tr>';
        }
        $colspan = count($columns) + ($reqKey !== null ? 1 : 0);
        $html .= '</tbody><tfoot><tr><td colspan="' . max(1, $colspan - ($reqKey !== null ? 2 : 1)) . '">Total ' . h((string)$name) . '</td><td class="r">' . h(bonusPreviewInt((float)$g['units'])) . '</td>' . ($reqKey !== null ? '<td class="c">' . bonusPreviewComplianceBadge((float)$g['units'], (float)$g['req']) . '</td>' : '') . '</tr></tfoot>';
        $html .= '</table></div></details>';
    }
    if ($drawn >= $maxRows && count($rows) > $maxRows) {
        $html .= '<div class="bpv-note">Se muestran las primeras ' . h(bonusPreviewInt((float)$maxRows)) . ' filas. Para ver todas, descarga la planilla en Excel.</div>';
    }
    $html .= '<div class="bpv-note">Vista de revisión: los montos a pagar y el detalle completo por día están en la planilla de Excel.</div>';
    $html .= '</div>';
    return $html;
}
