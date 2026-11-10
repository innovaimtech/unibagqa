<?php

declare(strict_types=1);

/**
 * Correo de "unidades listas para facturar" que se envía al registrar el embalaje de una OT
 * (réplica del aviso de cierre del sistema anterior: EnvioCorreo en editxid.php).
 *
 * Destinatarios (regla del sistema anterior): usuarios activos con correo y la marca
 * `user_st_email_cierre = 1`, excepto el grupo 18, salvo que sea el vendedor del pedido.
 * Opcionalmente se suman direcciones fijas con PACKAGING_BILLING_MAIL_TO (separadas por coma).
 * Se envía una sola vez por registro de embalaje; si se vuelve a guardar con otra cantidad total
 * se reenvía marcado como ACTUALIZACIÓN. Un fallo de correo nunca interrumpe el guardado.
 */
final class PackagingBillingMail
{
    public function __construct(private PDO $erpPdo, private ?PDO $trzPdo = null)
    {
    }

    private function ensureLog(): void
    {
        if ($this->trzPdo === null) {
            return;
        }
        $this->trzPdo->exec('CREATE TABLE IF NOT EXISTS packaging_billing_mail_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            embalaje_id INT NOT NULL,
            total_units INT NOT NULL,
            status VARCHAR(12) NOT NULL,
            recipients TEXT NULL,
            error_message VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pbm_event (event_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    private function h(mixed $v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param array<string,mixed> $detail Detalle de embalaje ya normalizado (cantidades y cierre).
     * @param array<int,array<string,mixed>> $mixedItems Detalle de CC del pallet mixto.
     * @return array{ok:bool,skipped?:bool,error?:string,recipients?:int}
     */
    public function notify(int $eventId, int $workerOtId, int $embalajeId, int $requestId, array $detail, array $mixedItems, int $totalUnits, int $packerUserId): array
    {
        try {
            $this->ensureLog();
            $lastSent = null;
            if ($this->trzPdo !== null) {
                $st = $this->trzPdo->prepare("SELECT total_units FROM packaging_billing_mail_log WHERE event_id=? AND status='SENT' ORDER BY id DESC LIMIT 1");
                $st->execute([$eventId]);
                $lastSent = $st->fetchColumn();
                if ($lastSent !== false && (int)$lastSent === $totalUnits) {
                    return ['ok' => true, 'skipped' => true];
                }
            }
            $isUpdate = $lastSent !== null && $lastSent !== false;

            $order = $this->erpPdo->prepare('SELECT o.id, o.req_number, o.req_userid_seller, o.req_id_cc_m1, o.req_id_cc_m2, o.req_id_cc_m3, c.cust_name
                FROM orders o LEFT JOIN customer c ON c.id = o.req_cust_id WHERE o.id = ?');
            $order->execute([$requestId]);
            $o = $order->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!$o) {
                return ['ok' => false, 'error' => 'No se encontró el pedido para el correo de facturación.'];
            }

            $names = static fn(PDO $p, int $id): array => (function () use ($p, $id) {
                if ($id <= 0) return ['name' => '', 'mail' => ''];
                $s = $p->prepare("SELECT CONCAT(user_firstname,' ',user_lastname) AS n, user_mail FROM user WHERE id = ?");
                $s->execute([$id]);
                $r = $s->fetch(PDO::FETCH_ASSOC) ?: [];
                return ['name' => trim((string)($r['n'] ?? '')), 'mail' => trim((string)($r['user_mail'] ?? ''))];
            })();
            $seller = $names($this->erpPdo, (int)$o['req_userid_seller']);
            $packer = $names($this->erpPdo, $packerUserId);
            if ($packer['name'] === '') {
                $packer['name'] = trim((string)($_SESSION['operator_name'] ?? ''));
            }
            $supStmt = $this->erpPdo->prepare("SELECT CONCAT(u.user_firstname,' ',u.user_lastname) FROM prod_worker_embalajes e JOIN user u ON u.id = e.req_id_supervisor WHERE e.id = ?");
            $supStmt->execute([$embalajeId]);
            $receiver = trim((string)$supStmt->fetchColumn());

            // Destinatarios
            $to = [];
            $rs = $this->erpPdo->prepare("SELECT DISTINCT u.id, u.user_mail FROM user u INNER JOIN user_group g ON g.user_id = u.id
                WHERE u.user_status > 0 AND u.user_st_email_cierre = 1 AND u.user_mail LIKE '%@%' AND (g.group_id != 18 OR u.id = ?)");
            $rs->execute([(int)$o['req_userid_seller']]);
            foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $to[] = trim((string)$r['user_mail']);
            }
            foreach (explode(',', (string)(Env::get('PACKAGING_BILLING_MAIL_TO', '') ?? '')) as $extra) {
                if (trim($extra) !== '') $to[] = trim($extra);
            }
            $to = array_values(array_unique(array_filter($to, static fn($m) => filter_var($m, FILTER_VALIDATE_EMAIL) !== false)));
            if ($to === []) {
                $this->log($eventId, $embalajeId, $totalUnits, 'NO_RCPT', '', 'Sin destinatarios (usuarios con aviso de cierre).');
                return ['ok' => false, 'error' => 'No hay usuarios con aviso de cierre y correo para enviar la facturación.'];
            }

            // Asunto (parametros ASUNTO según el código de cierre; si no existe, texto por defecto)
            $subjectBase = '';
            $code = (string)($detail['req_codigo_cierre'] ?? '');
            if ($code !== '') {
                $sa = $this->erpPdo->prepare("SELECT descripcion FROM parametros WHERE tabla = 'ASUNTO' AND codigo = ? LIMIT 1");
                $sa->execute([$code]);
                $subjectBase = trim((string)$sa->fetchColumn());
            }
            if ($subjectBase === '') {
                $subjectBase = ['0' => 'INGRESO BODEGA', '1' => 'CIERRE FINAL BODEGA', '2' => 'CIERRE PROVISORIO BODEGA'][$code]
                    ?? 'Embalaje listo para facturar';
            }
            $ccs = [(string)$o['req_number']];
            $mixedNums = [];
            foreach ($mixedItems as $m) {
                $n = trim((string)($m['req_number'] ?? ''));
                if ($n !== '' && !in_array($n, $ccs, true)) { $ccs[] = $n; $mixedNums[] = $n; }
            }
            $subject = ($isUpdate ? 'ACTUALIZACIÓN - ' : '') . $subjectBase . ' - ' . (string)$o['cust_name'] . ' (' . implode(') - (', $ccs) . ')';

            // Total embalado por CC (todo lo registrado del pedido)
            $tc = $this->erpPdo->prepare('SELECT COALESCE(SUM((req_embalaje_bolsas_por_caja_amt * req_embalaje_cajas_completas_amt)
                + req_embalaje_caja_final + req_embalaje_bolsas_sobrantes_amt),0) FROM prod_worker_embalajes WHERE evt_reqid = ?');
            $tc->execute([$requestId]);
            $totalCc = (int)$tc->fetchColumn();

            $td = 'style="border:1px solid #cbd5e1;padding:6px 10px"';
            $th = 'style="border:1px solid #cbd5e1;padding:6px 10px;background:#f1f5f9;font-weight:bold"';
            $row = fn(string $k, mixed $v): string => "<tr><td $th>" . $this->h($k) . "</td><td $td>" . $this->h($v) . '</td></tr>';
            $html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#0f172a">'
                . '<p>Estimados(as),</p>'
                . '<p>Se informa ' . ($isUpdate ? '<b>la actualización de</b> ' : '') . 'el cierre de embalaje de producción. '
                . '<b style="font-size:18px">Unidades listas para facturar y enviar: ' . number_format($totalUnits, 0, ',', '.') . '</b></p>'
                . '<table cellspacing="0" style="border-collapse:collapse;min-width:360px">'
                . $row('Cliente', $o['cust_name']) . $row('C.C.', implode(' / ', $ccs))
                . $row('Vendedor(a)', $seller['name']) . $row('Embalador(a)', $packer['name']) . $row('Quien recibe', $receiver)
                . '</table><br>'
                . '<table cellspacing="0" style="border-collapse:collapse;min-width:360px">'
                . $row('Total embalado por C.C. (acumulado)', number_format($totalCc, 0, ',', '.')) . '</table><br>';

            $mixedRows = $this->mixedRows($code, (string)$o['req_number'], $detail, $mixedItems);
            if ($mixedRows !== []) {
                $fmt = static fn(mixed $n): string => number_format((int)$n, 0, ',', '.');
                $tdn = 'style="border:1px solid #cbd5e1;padding:6px 10px;text-align:right"';
                $html .= '<table cellspacing="0" style="border-collapse:collapse"><tr>'
                    . "<td $th>C.C.</td><td $th>Cajas</td><td $th>Unid. por caja</td><td $th>Caja final</td><td $th>Sobrante</td><td $th>Total unidades</td></tr>";
                $sumBoxes = 0;
                $sumUnits = 0;
                foreach ($mixedRows as $m) {
                    $sumBoxes += (int)$m['total_cajas'];
                    $sumUnits += (int)$m['total_bolsas'];
                    $html .= '<tr><td ' . $td . '>' . $this->h($m['req_number']) . "</td><td $tdn>" . $fmt($m['total_cajas'])
                        . "</td><td $tdn>" . $fmt($m['bolsas_por_caja']) . "</td><td $tdn>" . $fmt($m['bolsas_caja_final'])
                        . "</td><td $tdn>" . $fmt($m['bolsas_sobrante']) . "</td><td $tdn>" . $fmt($m['total_bolsas']) . '</td></tr>';
                }
                $thn = 'style="border:1px solid #cbd5e1;padding:6px 10px;background:#f1f5f9;font-weight:bold;text-align:right"';
                $html .= "<tr><td $th>Total pallet</td><td $thn>" . $fmt($sumBoxes) . "</td><td $th colspan=\"3\"></td>"
                    . "<td $thn>" . $fmt($sumUnits) . '</td></tr></table><br>';
            } else {
                $html .= '<table cellspacing="0" style="border-collapse:collapse;min-width:360px">'
                    . $row('Medida de caja', $detail['req_embalaje_medidas_caja'] ?? '')
                    . $row('Unidades por caja', $detail['req_embalaje_bolsas_por_caja_amt'] ?? 0)
                    . $row('Cajas por pallet', $detail['req_embalaje_cajas_por_pallet_amt'] ?? 0)
                    . $row('Pallets completos', $detail['req_embalaje_pallets_completos_amt'] ?? 0)
                    . $row('Cajas en pallet incompleto', $detail['req_embalaje_palletcajas_incompletos_amt'] ?? 0)
                    . $row('Cajas completas', $detail['req_embalaje_cajas_completas_amt'] ?? 0)
                    . $row('Caja final (completa pedido)', $detail['req_embalaje_caja_final'] ?? 0)
                    . $row('Unidades sobrantes', $detail['req_embalaje_bolsas_sobrantes_amt'] ?? 0)
                    . $row('Unidades a showroom', $detail['req_embalaje_showroom'] ?? 0)
                    . $row('Total ingresado', $totalUnits)
                    . '</table><br>';
            }
            $obs = trim((string)($detail['req_observacion'] ?? ''));
            $html .= '<table cellspacing="0" style="border-collapse:collapse;min-width:360px"><tr><td ' . $th . '>Observación</td></tr><tr><td '
                . $td . ' style="height:50px">' . nl2br($this->h($obs)) . '</td></tr></table>'
                . '<p style="color:#64748b;font-size:12px">*Este correo es una notificación automática; no es necesario responderlo.</p></div>';

            (new MailService($this->erpPdo, $packerUserId))->send($to, $subject, $html);
            $this->log($eventId, $embalajeId, $totalUnits, 'SENT', implode(', ', $to), '');
            return ['ok' => true, 'recipients' => count($to)];
        } catch (Throwable $e) {
            error_log('Correo de facturación de embalaje: ' . $e->getMessage());
            try {
                $this->log($eventId, $embalajeId, $totalUnits, 'ERROR', '', $e->getMessage());
            } catch (Throwable) {
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Filas por CC del pallet mixto, o [] si el cierre no es mixto (código 50). El formulario envía siempre la fila del
     * CC principal, aunque el cierre sea estándar, y esa fila llega en 0: por eso el correo mostraba la tabla en ceros.
     * En mixto, la fila del CC principal se completa con lo registrado arriba (cajas, unidades por caja, caja final, sobrante).
     *
     * @param array<string,mixed> $detail
     * @param array<int,array<string,mixed>> $mixedItems
     * @return array<int,array{req_number:string,total_cajas:int,bolsas_por_caja:int,bolsas_caja_final:int,bolsas_sobrante:int,total_bolsas:int}>
     */
    public function mixedRows(string $closureCode, string $mainNumber, array $detail, array $mixedItems): array
    {
        if ($closureCode !== '50' || $mixedItems === []) {
            return [];
        }
        $rows = [];
        $hasUnits = false;
        foreach (array_values($mixedItems) as $i => $m) {
            $number = trim((string)($m['req_number'] ?? ''));
            $row = [
                'req_number' => $number,
                'total_cajas' => (int)($m['total_cajas'] ?? 0),
                'bolsas_por_caja' => (int)($m['bolsas_por_caja'] ?? 0),
                'bolsas_caja_final' => (int)($m['bolsas_caja_final'] ?? 0),
                'bolsas_sobrante' => (int)($m['bolsas_sobrante'] ?? 0),
                'total_bolsas' => (int)($m['total_bolsas'] ?? 0),
            ];
            $isMain = ($number !== '' && $number === $mainNumber) || ($number === '' && $i === 0);
            if ($isMain && $row['total_bolsas'] <= 0) {
                $row['req_number'] = $mainNumber;
                $row['total_cajas'] = (int)($detail['req_embalaje_cajas_completas_amt'] ?? 0);
                $row['bolsas_por_caja'] = (int)($detail['req_embalaje_bolsas_por_caja_amt'] ?? 0);
                $row['bolsas_caja_final'] = (int)($detail['req_embalaje_caja_final'] ?? 0);
                $row['bolsas_sobrante'] = (int)($detail['req_embalaje_bolsas_sobrantes_amt'] ?? 0);
                $row['total_bolsas'] = $row['total_cajas'] * $row['bolsas_por_caja'] + $row['bolsas_caja_final'] + $row['bolsas_sobrante'];
            }
            if ($row['req_number'] === '' && $row['total_bolsas'] <= 0) {
                continue;
            }
            $hasUnits = $hasUnits || $row['total_bolsas'] > 0;
            $rows[] = $row;
        }
        return $hasUnits ? $rows : [];
    }

    private function log(int $eventId, int $embalajeId, int $totalUnits, string $status, string $recipients, string $error): void
    {
        if ($this->trzPdo === null) {
            return;
        }
        $this->ensureLog();
        $this->trzPdo->prepare('INSERT INTO packaging_billing_mail_log (event_id, embalaje_id, total_units, status, recipients, error_message) VALUES (?,?,?,?,?,?)')
            ->execute([$eventId, $embalajeId, $totalUnits, $status, $recipients !== '' ? $recipients : null, $error !== '' ? mb_substr($error, 0, 500) : null]);
    }
}
