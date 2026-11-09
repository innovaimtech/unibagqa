<?php

declare(strict_types=1);

/**
 * Revisión de solo lectura de los movimientos de stock del sistema y de las cajas de bolsas.
 *
 * - docs_a_medias: documentos del motor de kardex ([TRZ-LED:...]) que quedaron en estado 1 (se cortó la conexión).
 * - cajas_sin_stock: cajas con saldo cuyo total supera el stock del ERP de su artículo, bodega y CC.
 * - movimientos_pendientes: bolsas usadas o devueltas en el Pulpo que no terminaron de registrarse.
 * - stock_fuera_de_kardex: filas de stock tocadas por el sistema cuyo saldo no coincide con su último registro del kardex
 *   (alguien cambió el stock sin dejar movimiento después).
 */
final class StockLedgerAudit
{
    public function __construct(private PDO $erp, private ?PDO $trz)
    {
    }

    private function all(PDO $pdo, string $sql, array $params = []): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function run(int $since): array
    {
        $result = ['docs_a_medias' => [], 'cajas_sin_stock' => [], 'movimientos_pendientes' => [], 'stock_fuera_de_kardex' => []];

        foreach ($this->all($this->erp, "SELECT id, stk_num, stk_crtdat, stk_annotation FROM stockchanges
                WHERE stk_crtdat >= ? AND stk_status = 1 AND stk_annotation LIKE '%[TRZ-LED:%'", [$since]) as $d) {
            $result['docs_a_medias'][] = ['documento' => $d['stk_num'], 'fecha' => (int)$d['stk_crtdat'], 'detalle' => mb_substr((string)$d['stk_annotation'], 0, 160)];
        }
        foreach ($this->all($this->erp, "SELECT id, strc_number, strc_crtdat, strc_desc FROM storehousechanges
                WHERE strc_crtdat >= ? AND strc_status = 1 AND strc_desc LIKE '%[TRZ-LED:%'", [$since]) as $d) {
            $result['docs_a_medias'][] = ['documento' => $d['strc_number'], 'fecha' => (int)$d['strc_crtdat'], 'detalle' => mb_substr((string)$d['strc_desc'], 0, 160)];
        }

        if ($this->trz !== null) {
            try {
                $groups = $this->all($this->trz, "SELECT erp_item_id, erp_st_id, COALESCE(erp_order_id,0) AS erp_order_id, final_sku,
                        COUNT(*) AS cajas, SUM(units_qty) AS bolsas FROM boxes
                    WHERE status IN ('IN_WAREHOUSE','IN_MACHINE') AND erp_item_id > 0 AND erp_st_id > 0 AND units_qty > 0
                    GROUP BY erp_item_id, erp_st_id, COALESCE(erp_order_id,0), final_sku");
                foreach ($groups as $g) {
                    $orderId = (int)$g['erp_order_id'];
                    $stock = $this->all($this->erp, 'SELECT COALESCE(SUM(iss_inventory),0) AS s FROM item_shops_storehouses WHERE item_id = ? AND st_id = ?'
                        . ($orderId > 0 ? ' AND iss_order_id = ?' : ''), $orderId > 0 ? [(int)$g['erp_item_id'], (int)$g['erp_st_id'], $orderId] : [(int)$g['erp_item_id'], (int)$g['erp_st_id']]);
                    $erpStock = (float)($stock[0]['s'] ?? 0);
                    if ((float)$g['bolsas'] > $erpStock + 0.0005) {
                        $result['cajas_sin_stock'][] = ['sku' => $g['final_sku'], 'bodega' => (int)$g['erp_st_id'], 'cc' => $orderId,
                            'cajas' => (int)$g['cajas'], 'bolsas_en_cajas' => (float)$g['bolsas'], 'stock_erp' => $erpStock];
                    }
                }
                foreach ($this->all($this->trz, "SELECT m.id, m.kind, m.qty, m.worker_ot_id, m.created_at, m.error_message, b.box_code
                        FROM box_unit_moves m JOIN boxes b ON b.id = m.box_id WHERE m.state = 'PENDING'") as $m) {
                    $result['movimientos_pendientes'][] = ['caja' => $m['box_code'], 'tipo' => $m['kind'], 'bolsas' => (float)$m['qty'],
                        'ot' => (int)$m['worker_ot_id'], 'fecha' => (int)$m['created_at'], 'error' => (string)$m['error_message']];
                }
            } catch (Throwable $e) {
                $result['movimientos_pendientes'][] = ['error' => 'No se pudo leer las cajas: ' . $e->getMessage()];
            }
        }

        // Filas tocadas por el sistema desde la fecha: su saldo debe ser el del último registro del kardex.
        $touched = $this->all($this->erp, "SELECT DISTINCT t.item_id, t.tran_shop_id, t.tran_st_id, t.tran_order_id
            FROM tran_data t JOIN stockchanges s ON s.id = t.tran_id AND t.tran_type IN ('stockchangeup','stockchangedown')
            WHERE t.tran_crtdat >= ? AND s.stk_annotation LIKE '%[TRZ-%'", [$since]);
        foreach ($touched as $row) {
            $last = $this->all($this->erp, "SELECT tran_type, tran_number, tran_currstock, tran_amount, tran_crtdat FROM tran_data
                WHERE tran_crtdat >= ? AND item_id = ? AND tran_shop_id = ? AND tran_st_id = ? AND tran_order_id = ?
                ORDER BY tran_crtdat DESC, tran_id DESC, tran_pos DESC LIMIT 1",
                [$since, (int)$row['item_id'], (int)$row['tran_shop_id'], (int)$row['tran_st_id'], (int)$row['tran_order_id']]);
            if ($last === []) {
                continue;
            }
            $l = $last[0];
            $expected = (float)$l['tran_currstock'] + (in_array($l['tran_type'], ['stockchangeup', 'sthup'], true) ? 1 : -1) * (float)$l['tran_amount'];
            $now = $this->all($this->erp, 'SELECT COALESCE(SUM(iss_inventory),0) AS s FROM item_shops_storehouses WHERE item_id = ? AND shop_id = ? AND st_id = ? AND iss_order_id = ?',
                [(int)$row['item_id'], (int)$row['tran_shop_id'], (int)$row['tran_st_id'], (int)$row['tran_order_id']]);
            $current = (float)($now[0]['s'] ?? 0);
            if (abs($current - $expected) > 0.001) {
                $result['stock_fuera_de_kardex'][] = ['articulo' => (int)$row['item_id'], 'bodega' => (int)$row['tran_st_id'], 'cc' => (int)$row['tran_order_id'],
                    'ultimo_documento' => $l['tran_number'], 'saldo_segun_kardex' => round($expected, 3), 'stock_actual' => round($current, 3)];
            }
        }
        return $result;
    }
}
