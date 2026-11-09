<?php

declare(strict_types=1);

/**
 * Revisa, solo con lecturas, que los embalajes de Corte y Sellado hayan terminado bien: un fallo solo avisa al operador
 * en la pantalla, así que esto es lo que permite descubrirlo después.
 */
final class CysPackagingAudit
{
    private const SEALING_TYPE = 8;
    private const STALE_OPEN_SECONDS = 86400;

    public function __construct(private PDO $erp)
    {
    }

    /**
     * @param int $stockSince Cierres desde esta fecha deben tener ingreso automático; los anteriores los ajustó bodega.
     * @return array<string,list<array<string,mixed>>>
     */
    public function run(int $stockSince, int $now): array
    {
        return [
            'sin_mover' => $this->notMoved($stockSince),
            'stock' => $this->stockProblems($stockSince),
            'traspasos' => $this->mixedProblems($stockSince),
            'abiertos' => $this->staleOpen($now),
        ];
    }

    /** Cerrados con unidades que siguen contando como sellado. */
    private function notMoved(int $since): array
    {
        $stmt = $this->erp->prepare('SELECT e.id AS evento, o.req_number AS cc, e.evt_amount AS unidades, e.evt_enddat AS cierre,
                (SELECT COUNT(*) FROM prod_worker_embalajes d WHERE d.req_v2refid = e.id) AS detalles
            FROM prod_worker_ot_events e
            JOIN prod_worker_ot ot ON ot.id = e.evt_prod_worker_otid
            JOIN prod_agenda a ON a.id = ot.wok_ag_id
            LEFT JOIN orders o ON o.id = a.ag_reqid
            WHERE e.evt_type = \'prod\' AND e.overrideembalaje_act = 1 AND e.overrideembalaje_selladora_evtrefid = 0
              AND e.evt_status > 0 AND e.evt_enddat >= ? AND e.evt_amount > 0 AND a.ag_equipotype_id = ?
            ORDER BY e.id');
        $stmt->execute([$since, self::SEALING_TYPE]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Embalajes movidos cuyo ingreso marcado no coincide con lo embalado o entró a otra bodega. */
    private function stockProblems(int $since): array
    {
        $loaded = [];
        $docs = $this->erp->prepare('SELECT stk_annotation FROM stockchanges WHERE stk_crtdat >= ? AND stk_annotation LIKE ?');
        $docs->execute([$since, '%[TRZ-EMB-STOCK:%']);
        foreach ($docs->fetchAll(PDO::FETCH_COLUMN) as $annotation) {
            if (preg_match('/\[TRZ-EMB-STOCK:(\d+):(\d+):(-?\d+)\]/', (string)$annotation, $m) === 1) {
                $loaded[(int)$m[1]][(int)$m[2]] = ($loaded[(int)$m[1]][(int)$m[2]] ?? 0) + (int)$m[3];
            }
        }
        $problems = [];
        foreach ($this->movedDetails($since) as $row) {
            $units = (int)$row['req_embalaje_cajas_completas_amt'] * (int)$row['req_embalaje_bolsas_por_caja_amt']
                + (int)$row['req_embalaje_caja_final'] + (int)$row['req_embalaje_bolsas_sobrantes_amt'] + (int)$row['req_embalaje_showroom'];
            $byWarehouse = array_filter($loaded[(int)$row['evento']] ?? []);
            $warehouse = (int)$row['req_id_bodega'];
            if ($units === 0 ? $byWarehouse === [] : $byWarehouse === [$warehouse => $units]) {
                continue;
            }
            $problems[] = ['evento' => (int)$row['evento'], 'cc' => $row['cc'], 'unidades' => $units, 'bodega' => $warehouse, 'cargado' => $byWarehouse];
        }
        return $problems;
    }

    /** Líneas de otro CC en un cierre mixto cuyo traspaso no coincide con las bolsas declaradas. */
    private function mixedProblems(int $since): array
    {
        $moved = [];
        $docs = $this->erp->prepare('SELECT strc_desc FROM storehousechanges WHERE strc_crtdat >= ? AND strc_desc LIKE ?');
        $docs->execute([$since, '%[TRZ-EMB:%']);
        foreach ($docs->fetchAll(PDO::FETCH_COLUMN) as $desc) {
            if (preg_match('/\[TRZ-EMB:(\d+):([^:\]]+):(\d+)>(\d+):(\d+)\]/', (string)$desc, $m) === 1) {
                $key = $m[1] . '|' . $m[2];
                $moved[$key] = ($moved[$key] ?? 0) + (str_contains((string)$desc, '] reversa') ? -(int)$m[5] : (int)$m[5]);
            }
        }
        $problems = [];
        foreach ($this->movedDetails($since) as $row) {
            if ((string)$row['req_codigo_cierre'] !== '50') {
                continue;
            }
            $lines = $this->erp->prepare('SELECT dm.req_number, dm.total_bolsas FROM embalaje_mixto m JOIN detalle_mixto dm ON dm.id_mixto = m.id WHERE m.id_embalaje = ?');
            $lines->execute([(int)$row['detalle']]);
            foreach ($lines->fetchAll(PDO::FETCH_ASSOC) as $line) {
                if ((string)$line['req_number'] === (string)$row['cc'] || (int)$line['total_bolsas'] <= 0) {
                    continue;
                }
                $done = $moved[$row['detalle'] . '|' . $line['req_number']] ?? 0;
                // Sin traspaso es válido solo si el operador indicó que ya estaba en destino: lo revisa una persona.
                if ($done !== (int)$line['total_bolsas']) {
                    $problems[] = ['evento' => (int)$row['evento'], 'cc' => $line['req_number'], 'bolsas' => (int)$line['total_bolsas'], 'traspasado' => $done];
                }
            }
        }
        return $problems;
    }

    /** Embalajes de selladora abiertos hace más de un día. */
    private function staleOpen(int $now): array
    {
        $stmt = $this->erp->prepare('SELECT e.id AS evento, o.req_number AS cc, e.evt_crtdat AS inicio, ot.id AS ot, ot.wok_status AS estado_ot
            FROM prod_worker_ot_events e
            JOIN prod_worker_ot ot ON ot.id = e.evt_prod_worker_otid
            JOIN prod_agenda a ON a.id = ot.wok_ag_id
            LEFT JOIN orders o ON o.id = a.ag_reqid
            WHERE e.evt_type = \'prod\' AND e.overrideembalaje_act = 1 AND e.evt_status = 1 AND e.evt_enddat = 0
              AND a.ag_equipotype_id = ? AND e.evt_crtdat < ?
            ORDER BY e.id');
        $stmt->execute([self::SEALING_TYPE, $now - self::STALE_OPEN_SECONDS]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function movedDetails(int $since): array
    {
        $stmt = $this->erp->prepare('SELECT e.id AS evento, o.req_number AS cc, d.id AS detalle, d.req_codigo_cierre, d.req_id_bodega,
                d.req_embalaje_cajas_completas_amt, d.req_embalaje_bolsas_por_caja_amt, d.req_embalaje_caja_final,
                d.req_embalaje_bolsas_sobrantes_amt, d.req_embalaje_showroom
            FROM prod_worker_ot_events e
            JOIN prod_worker_embalajes d ON d.req_v2refid = e.id
            LEFT JOIN orders o ON o.id = d.evt_reqid
            WHERE e.evt_type = \'prod\' AND e.overrideembalaje_act = 1 AND e.overrideembalaje_selladora_evtrefid > 0
              AND e.evt_status > 0 AND e.evt_enddat >= ?
            ORDER BY e.id');
        $stmt->execute([$since]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
