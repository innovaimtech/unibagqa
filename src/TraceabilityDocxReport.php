<?php

declare(strict_types=1);

require_once __DIR__ . '/DocxBuilder.php';

/**
 * Informes de Trazabilidad en Word:
 *  - buildOrderReport(): informe 360° completo del pedido (incluye la ficha técnica de cada máquina).
 *  - buildMachineReport(): ficha técnica de lo fabricado en una máquina.
 */
final class TraceabilityDocxReport
{
    private static function n(mixed $v, int $dec = 0): string
    {
        return number_format((float)$v, $dec, ',', '.');
    }

    private static function d(mixed $ts, bool $time = false): string
    {
        $ts = (int)$ts;
        return $ts > 0 ? date($time ? 'd/m/Y H:i' : 'd/m/Y', $ts) : '-';
    }

    private static function s(mixed $v, string $empty = '-'): string
    {
        $t = trim((string)$v);
        return $t !== '' ? $t : $empty;
    }

    /** Ruta local de la imagen de diseño si ya está en el caché del sistema (no descarga nada). */
    private static function cachedImagePath(string $hash): ?string
    {
        $hash = basename(trim($hash));
        if ($hash === '' || $hash === 'dummy') {
            return null;
        }
        foreach ([__DIR__ . '/Http/docs_cache/' . $hash, __DIR__ . '/Http/docs/order/' . $hash, __DIR__ . '/Http/docs/prod/' . $hash] as $path) {
            if (is_file($path) && filesize($path) > 0) {
                return $path;
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $detail resultado de TraceabilityService::getOrderDetail()
     * @param array<int,array<string,mixed>> $machineDossiers fichas por agenda (FabricationDossierService::forAgenda)
     */
    public static function buildOrderReport(array $detail, array $machineDossiers = []): string
    {
        $order = $detail['order'];
        $analysis = $detail['analysis'];
        $nv = trim((string)$order['req_number']);
        $ot = trim((string)$order['ot_number']);
        $total = (float)$order['total_order_amount'];
        $shipped = (float)$order['shipped_units'];

        $doc = new DocxBuilder('Informe de Trazabilidad ' . $nv, 'Informe de Trazabilidad 360°', 'Nota de Venta ' . $nv . ($ot !== '' ? ' · OT ' . $ot : ''));
        $doc->cover('Informe de Trazabilidad 360°', self::s($order['cust_name']), [
            ['Nota de Venta', $nv],
            ['OC cliente', self::s($order['req_oc_number'], 'Sin OC')],
            ['OT planta', $ot !== '' ? '#' . $ot : 'Pendiente'],
            ['RUT', self::s($order['cust_rut'], 'No registrado')],
            ['Fecha de ingreso', self::d($order['req_crtdat'], true)],
            ['Entrega prometida', self::s($order['req_despacho_desc'])],
            ['Etapa actual', self::s($analysis['stage_label'])],
            ['Cantidad requerida', self::n($total) . ' un.'],
            ['Terminado (embalaje)', self::n($order['produced_units']) . ' un. (' . self::n($analysis['prod_pct'], 1) . ' %)'],
            ['Despachado', self::n($shipped) . ' un. (' . self::n($analysis['ship_pct'], 1) . ' %)'],
        ]);
        $blocked = (bool)$analysis['is_blocked'];
        $doc->callout(
            $blocked ? 'Qué está esperando este pedido (cuello de botella)' : 'Estado del pedido: en flujo normal',
            self::s($analysis['waiting_for']),
            $blocked ? 'DC2626' : '16A34A',
            $blocked ? 'FEF2F2' : 'F0FDF4'
        );

        // Línea de vida
        $doc->heading('Línea de vida del pedido', 1);
        $statusLabels = ['done' => 'Completado', 'completed' => 'Completado', 'active' => 'En curso', 'current' => 'En curso', 'pending' => 'Pendiente', 'blocked' => 'Bloqueado'];
        $rows = [];
        foreach ($detail['timeline'] as $st) {
            $rows[] = [(string)$st['step'], self::s($st['name']), $statusLabels[(string)($st['status'] ?? '')] ?? self::s($st['status'] ?? ''), self::s($st['date']), self::s($st['desc'])];
        }
        $doc->table(['N°', 'Etapa', 'Estado', 'Fecha', 'Detalle'], $rows, [1, 4, 3, 3, 8], [1 => 'left']);

        // 1. Comercial
        $doc->heading('1. Información comercial y producto', 1);
        $rows = [];
        foreach ($detail['items'] as $it) {
            $rows[] = [
                self::s($it['item_title'], 'Bolsa'), self::s($it['item_number_prod'], 'N/A'),
                self::n($it['fab_med_width'], 1) . ' x ' . self::n($it['fab_med_height'], 1) . ' + ' . self::n($it['fab_med_fuelle'], 1) . ' cm',
                self::s($it['fab_type'], 'TNT'), self::s($it['fab_printtype'], 'SIN'),
            ];
        }
        $doc->table(['Producto', 'SKU', 'Medidas (ancho x alto + fuelle)', 'Tela', 'Impresión'], $rows, [5, 3, 5, 2, 3]);
        $primary = $detail['items'][0] ?? [];
        $img = self::cachedImagePath((string)($primary['fab_design_imagehash'] ?? ''));
        if ($img !== null) {
            $doc->image($img, 8.0, 'Diseño de impresión');
        }

        // 2. Clisés
        $doc->heading('2. Clisés y pre-prensa técnica', 1);
        if (!$analysis['needs_cliche']) {
            $doc->text('Este pedido no requiere clisés (sin impresión).', ['italic' => true]);
        } else {
            $doc->kv([
                ['Tipo de clisé', (int)$order['req_cliche_peli_solic_repeat_act'] === 1 ? 'Repetición existente en bodega' : 'Clisé nuevo / compra a proveedor'],
                ['Código en bodega', self::s($order['req_infoaddprd_cliche_codigo'], 'Pendiente asignación')],
                ['Ubicación rack', self::s($order['req_infoaddprd_cliche_ubicacion'], 'Sin ubicar')],
                ['Proveedor', self::s($order['cliche_supplier_name'] ?? ($order['req_custnameprov'] ?? ''), 'No asignado')],
                ['Seudónimo / matriz', self::s($order['req_solic_supp_seudonimo'], 'Sin seudónimo')],
                ['Espesor polímero', self::s($order['req_solic_devprints_poltype'], 'Estándar')],
                ['Solicitud de compra', (int)$order['req_cliche_peli_solic_dat'] > 0 ? self::d($order['req_cliche_peli_solic_dat'], true) : 'Pendiente solicitud a proveedor'],
                ['Recepción en planta', (int)$order['req_cliche_peli_recep_dat'] > 0 ? self::d($order['req_cliche_peli_recep_dat'], true) : 'En espera de entrega por proveedor'],
            ], 2);
        }
        $designer = trim(trim((string)($order['designer_firstname'] ?? '')) . ' ' . trim((string)($order['designer_lastname'] ?? '')));
        $doc->heading('Validación pre-prensa', 2);
        $doc->kv([
            ['Checklist técnico', (int)$order['req_prod_checklist_term'] === 1 ? 'Aprobado y validado' : 'Pendiente de aprobación'],
            ['Diseñador asignado', $designer !== '' ? $designer : 'Sin diseñador asignado'],
        ], 2);
        if (!empty($detail['attachments'])) {
            $doc->text('Archivos adjuntos de pre-prensa (' . count($detail['attachments']) . '):', ['bold' => true]);
            $doc->bullets(array_map(static fn(array $a): string => self::s($a['filename'] ?? ''), $detail['attachments']));
        }

        // 3. Planificación
        $doc->heading('3. Planificación y agendas de máquinas', 1);
        $otStatus = (int)$order['prd_status'];
        $doc->kv([
            ['OT de planta', $ot !== '' ? '#' . $ot : 'Pendiente de generación'],
            ['Estado de la OT', $otStatus === 1 ? 'En preparación' : ($otStatus === 2 ? 'Activa / programada' : ($otStatus === 3 ? 'Terminada' : 'Sin OT'))],
        ], 2);
        $rows = [];
        foreach ($detail['agendas'] as $ag) {
            $st = (int)$ag['ag_status'];
            $rows[] = [self::s($ag['equipo_name']), self::s($ag['ag_date']), $st === 3 ? 'Terminado' : ($st === 2 ? 'En curso' : 'Programado'), self::n($ag['ag_amount']) . ' un.'];
        }
        $doc->table(['Máquina', 'Fecha', 'Estado', 'Programado'], $rows, [5, 3, 3, 3], [3 => 'right']);

        // 4. Fabricación
        $doc->heading('4. Fabricación en planta y mermas', 1);
        $doc->kv([
            ['Avance (terminado en embalaje)', self::n($analysis['prod_pct'], 1) . ' %'],
            ['Requerido', self::n($total) . ' un.'],
            ['Flexografía', self::n($order['produced_flexo'] ?? 0) . ' un.'],
            ['Corte y sellado', self::n($order['produced_sellado'] ?? 0) . ' un.'],
            ['Embalaje (final)', self::n($order['produced_embalaje'] ?? 0) . ' un.'],
            ['Metros lineales', self::n($order['produced_meters']) . ' m'],
            ['Kilos procesados', self::n($order['consumed_kg'], 1) . ' kg'],
            ['Eventos / turnos', (string)count($detail['events'])],
        ], 2);
        $doc->heading('Historial de eventos de producción', 2);
        $rows = [];
        foreach ($detail['events'] as $ev) {
            $helper = trim((string)($ev['helper_name'] ?? ''));
            $rows[] = [
                self::s($ev['start_fmt']),
                self::s($ev['equipo_name'], 'Máquina') . "\n" . strtoupper(self::s($ev['evt_type'])),
                self::s($ev['operator_name']) . ($helper !== '' ? "\n+ " . $helper : ''),
                self::s($ev['duration_text']),
                self::n($ev['evt_amount']) . ' un.',
                self::n($ev['prod_bobina_kg'], 1) . ' kg',
                self::s($ev['evt_comments'] ?? ''),
            ];
        }
        $doc->table(['Inicio', 'Máquina / tipo', 'Operador', 'Duración', 'Cantidad', 'Kilos', 'Observaciones'], $rows, [3, 3, 3, 2, 2, 2, 4], [4 => 'right', 5 => 'right']);

        // Fichas por máquina
        if ($machineDossiers !== []) {
            $doc->pageBreak();
            $doc->heading('Detalle técnico por máquina', 1);
            $doc->text('Resumen de lo programado y lo realizado en cada máquina de la OT: medidas, corte, rodillo, clichés, anilox, colores, producción, mermas y aprobaciones.', ['color' => '64748B']);
            $first = true;
            foreach ($machineDossiers as $dossier) {
                if (!$first) {
                    $doc->pageBreak();
                }
                $first = false;
                self::appendMachineDossier($doc, $dossier, 2);
            }
        }

        // 5. Despachos
        $doc->pageBreak();
        $doc->heading('5. Despachos, guías de entrega y facturación', 1);
        $balance = max(0.0, $total - $shipped);
        $doc->kv([
            ['Total pedido', self::n($total) . ' un.'],
            ['Total despachado', self::n($shipped) . ' un.'],
            ['Saldo pendiente de entrega', self::n($balance) . ' un.'],
            ['% entrega cumplida', self::n($analysis['ship_pct'], 1) . ' %'],
        ], 2);
        if (empty($detail['deliveries'])) {
            $doc->text('Aún no se han emitido guías de despacho para este pedido.', ['italic' => true]);
        } else {
            $rows = [];
            foreach ($detail['deliveries'] as $dlv) {
                $rows[] = [self::s($dlv['dlv_num']), self::s($dlv['date_fmt']), self::s($dlv['dlv_oc_number'] ?? '', self::s($order['req_oc_number'])), (string)count($dlv['items']), self::n($dlv['total_shipped']) . ' un.'];
            }
            $doc->table(['N° guía', 'Fecha despacho', 'OC despacho', 'Productos', 'Cantidad despachada'], $rows, [3, 3, 3, 2, 3], [3 => 'center', 4 => 'right']);
            foreach ($detail['deliveries'] as $dlv) {
                $doc->heading('Guía ' . self::s($dlv['dlv_num']) . ' · ' . self::s($dlv['date_fmt']), 3);
                $items = [];
                foreach ($dlv['items'] as $it) {
                    $items[] = [self::s($it['item_title'], 'Bolsa'), self::s($it['item_number_prod'] ?? ''), self::n($it['item_amount_shipped']) . ' un.'];
                }
                $doc->table(['Producto', 'SKU', 'Cantidad'], $items, [6, 3, 2], [2 => 'right']);
                $note = trim((string)($dlv['dlv_annotation'] ?? ''));
                if ($note !== '') {
                    $doc->text('Observación: ' . $note, ['italic' => true, 'color' => '64748B']);
                }
            }
        }

        return $doc->build();
    }

    /** Ficha técnica de una máquina como documento independiente. */
    public static function buildMachineReport(array $dossier): string
    {
        $plan = $dossier['plan'] ?? [];
        $machine = self::s($plan['machine'] ?? '', 'Máquina');
        $doc = new DocxBuilder('Ficha de fabricación ' . $machine, 'Ficha técnica de fabricación', $machine . ' · OT ' . self::s($plan['ot'] ?? ''));
        self::appendMachineDossier($doc, $dossier, 1);
        return $doc->build();
    }

    /** @param array<string,mixed> $d */
    private static function appendMachineDossier(DocxBuilder $doc, array $d, int $level): void
    {
        $p = $d['plan'] ?? [];
        $doc->heading(self::s($p['machine'] ?? '', 'Máquina') . ($p['process'] ?? '' ? ' · ' . $p['process'] : ''), $level);
        $doc->kv([
            ['OT', self::s($p['ot'] ?? '')], ['Cliente', self::s($p['customer'] ?? '')],
            ['Producto', self::s($p['product'] ?? '')], ['Diseño', self::s($p['design'] ?? '')],
            ['Cantidad programada', self::n($p['planned_units'] ?? 0) . ' un.'], ['Merma programada', self::n($p['waste_percent_planned'] ?? 0, 1) . ' %'],
        ], 2);

        $doc->heading('Medidas programadas', $level + 1);
        $kv = [
            ['Ancho', self::n($p['width'] ?? 0, 1) . ' cm'], ['Alto', self::n($p['height'] ?? 0, 1) . ' cm'],
            ['Fuelle', self::n($p['gusset'] ?? 0, 1) . ' cm'], ['Área de impresión', self::n($p['print_width'] ?? 0, 1) . ' x ' . self::n($p['print_height'] ?? 0, 1) . ' cm'],
        ];
        if (($p['handle_length'] ?? 0) > 0) {
            $kv[] = ['Largo de manilla', self::n($p['handle_length'], 0) . ' cm'];
        }
        if (($p['grams'] ?? 0) > 0) {
            $kv[] = ['Gramaje', self::n($p['grams'], 0) . ' g'];
        }
        if (self::s($p['fabric_color'] ?? '') !== '-') {
            $kv[] = ['Color de tela', (string)$p['fabric_color']];
        }
        $doc->kv($kv, 2);

        if (!empty($d['cut'])) {
            $c = $d['cut'];
            $doc->heading('Corte y formato de rodillo', $level + 1);
            $doc->kv([
                ['Rodillo (Z)', ($c['roller_z'] ?? 0) > 0 ? 'Z = ' . $c['roller_z'] : 'Sin dato'],
                ['Corte por bolsa', self::n($c['cut_m'] ?? 0, 4) . ' m'],
                ['Medida usada para el rodillo', self::n($c['measure_used'] ?? 0, 0) . ' cm'],
                ['Polímero', self::s($c['polymer_type'] ?? '')],
                ['Metros lineales programados', self::n($c['linear_meters_planned'] ?? 0) . ' m'],
                ['Adicionales por merma', self::n($c['linear_meters_extra'] ?? 0) . ' m'],
                ['Metros lineales a imprimir', self::n($c['linear_meters_to_print'] ?? 0) . ' m'],
            ], 2);
        }

        $cl = $d['cliche'] ?? [];
        $assigned = implode(' | ', array_map(static fn(array $a): string => trim((string)$a['code'] . ((string)($a['description'] ?? '') !== '' ? ' - ' . $a['description'] : '')), $cl['assigned'] ?? []));
        $doc->heading('Clichés', $level + 1);
        $doc->kv([
            ['Código de cliché', self::s($cl['code'] ?? '')], ['Ubicación', self::s($cl['location'] ?? '')],
            ['Solicitado', self::s($cl['requested_at'] ?? '')], ['Recibido', self::s($cl['received_at'] ?? '')],
            ['Asignados a la OT', $assigned !== '' ? $assigned : 'Sin registro'],
        ], 2);

        if (!empty($d['units'])) {
            $doc->heading('Colores, anilox y tintas por unidad', $level + 1);
            $rows = [];
            foreach ($d['units'] as $u) {
                $rows[] = [
                    'N° ' . $u['unit'], self::s($u['color_planned'] ?? ''), self::s($u['color_used'] ?? ''), self::s($u['anilox'] ?? ''),
                    self::s($u['ink'] ?? '') . (!empty($u['mixed']) ? ' (mezcla' . (self::s($u['notes'] ?? '') !== '-' ? ': ' . $u['notes'] : '') . ')' : ''),
                    ($u['consumed_kg'] ?? null) === null ? '-' : self::n($u['consumed_kg'], 2) . ' kg',
                ];
            }
            $doc->table(['Unidad', 'Color programado', 'Color cargado', 'Anilox usado', 'Tinta', 'Consumo'], $rows, [2, 4, 3, 4, 4, 2], [5 => 'right']);
        }

        $pr = $d['production'] ?? [];
        $doc->heading('Producción realizada', $level + 1);
        $doc->kv([
            ['Unidades producidas', self::n($pr['units'] ?? 0) . ' un.'], ['Metros lineales', self::n($pr['meters'] ?? 0) . ' m'],
            ['Kilos procesados', self::n($pr['kg'] ?? 0, 1) . ' kg'], ['Turnos / eventos', (string)($pr['turns'] ?? 0)],
            ['Primer registro', self::s($pr['first_at'] ?? '')], ['Último registro', self::s($pr['last_at'] ?? '')],
        ], 2);
        if (!empty($pr['operators'])) {
            $rows = [];
            foreach ($pr['operators'] as $o) {
                $rows[] = [self::s($o['operator']), (string)$o['turns'], self::n($o['units']) . ' un.'];
            }
            $doc->table(['Operador', 'Turnos', 'Unidades'], $rows, [6, 2, 3], [1 => 'center', 2 => 'right']);
        }

        if (!empty($d['wastes'])) {
            $doc->heading('Mermas registradas', $level + 1);
            $rows = [];
            foreach ($d['wastes'] as $w) {
                $rows[] = [self::s($w['title']), self::n($w['units']), self::n($w['kg'], 2)];
            }
            $doc->table(['Tipo de merma', 'Unidades', 'Kilos'], $rows, [6, 2, 2], [1 => 'right', 2 => 'right']);
        }

        if (!empty($d['setup'])) {
            $doc->heading('Alistamiento / apertura de máquina', $level + 1);
            $rows = [];
            foreach ($d['setup'] as $s) {
                $rows[] = [self::s($s['start']), self::s($s['end']), ($s['minutes'] ?? null) === null ? '-' : $s['minutes'] . ' min', self::s($s['operator'] ?? ''), self::s($s['helper'] ?? ''), self::s($s['comments'] ?? '')];
            }
            $doc->table(['Inicio', 'Término', 'Duración', 'Operador', 'Ayudante', 'Comentarios'], $rows, [3, 3, 2, 3, 3, 4]);
        }

        $doc->heading('Aprobación de la producción', $level + 1);
        if (empty($d['approvals'])) {
            $doc->text('No hay aprobación registrada (líder o supervisor) para esta máquina.', ['italic' => true]);
        } else {
            $rows = [];
            foreach ($d['approvals'] as $a) {
                $rows[] = [self::s($a['role']), self::s($a['name']), self::s($a['at'] ?? ''), self::s($a['source'] ?? '')];
            }
            $doc->table(['Rol', 'Aprobó', 'Fecha y hora', 'Origen'], $rows, [3, 4, 3, 3]);
        }

        if (!empty($d['warnings'])) {
            $doc->text('Algunos datos no se pudieron consultar al generar este informe: ' . implode(', ', $d['warnings']) . '.', ['italic' => true, 'color' => 'B45309', 'size' => 9]);
        }
    }
}
