<?php

declare(strict_types=1);

final class RollReceptionService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function createRoll(array $input): array
    {
        $errors = $this->validateCreate($input);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }

        $rollCode = $this->generateRollCode();

        $microns = isset($input['microns']) && $input['microns'] !== '' && $input['microns'] !== null ? (int)$input['microns'] : null;
        $width = isset($input['width_mm']) && $input['width_mm'] !== '' && $input['width_mm'] !== null ? (int)$input['width_mm'] : null;
        $color = isset($input['color']) && trim((string)$input['color']) !== '' ? trim((string)$input['color']) : null;
        $meters = isset($input['meters']) && $input['meters'] !== '' && $input['meters'] !== null ? (float)$input['meters'] : null;
        $receivedQty = isset($input['received_qty']) ? (float)$input['received_qty'] : 1.0;
        $poId = isset($input['purchase_order_id']) ? (int)$input['purchase_order_id'] : null;
        $polId = isset($input['purchase_order_line_id']) ? (int)$input['purchase_order_line_id'] : null;
        $importContainerId = isset($input['import_container_id']) ? (int)$input['import_container_id'] : null;
        $importContainerItemId = isset($input['import_container_item_id']) ? (int)$input['import_container_item_id'] : null;
        $supplierId = isset($input['supplier_id']) ? (int)$input['supplier_id'] : null;
        $operatorName = trim((string)($input['operator_name'] ?? ''));
        $receptionMode = $this->normalizeReceptionMode((string)($input['reception_mode'] ?? 'QUANTITY'));

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO rolls (roll_code, sku_id, warehouse_id, weight_kg, received_qty, reception_mode, microns, width_mm, color, meters, status, purchase_order_id, purchase_order_line_id, import_container_id, import_container_item_id, supplier_id, current_work_order_id)
                 VALUES (:roll_code, :sku_id, :warehouse_id, :weight_kg, :received_qty, :reception_mode, :microns, :width_mm, :color, :meters, :status, :po_id, :pol_id, :import_container_id, :import_container_item_id, :supplier_id, :work_order_id)'
            );
            $stmt->execute([
                ':roll_code' => $rollCode,
                ':sku_id' => (int)$input['sku_id'],
                ':warehouse_id' => (int)$input['warehouse_id'],
                ':weight_kg' => (string)$input['weight_kg'],
                ':received_qty' => number_format($receivedQty, 3, '.', ''),
                ':reception_mode' => $receptionMode,
                ':microns' => $microns,
                ':width_mm' => $width,
                ':color' => $color,
                ':meters' => $meters,
                ':status' => 'RECEIVED',
                ':po_id' => $poId > 0 ? $poId : null,
                ':pol_id' => $polId > 0 ? $polId : null,
                ':import_container_id' => $importContainerId !== null && $importContainerId > 0 ? $importContainerId : null,
                ':import_container_item_id' => $importContainerItemId !== null && $importContainerItemId > 0 ? $importContainerItemId : null,
                ':supplier_id' => $supplierId > 0 ? $supplierId : null,
                ':work_order_id' => null,
            ]);

            $rollId = (int)$this->pdo->lastInsertId();

            $this->insertMovement($rollId, (int)$input['warehouse_id'], $input);
            $this->insertEvent('ROLL_RECEIVED', [
                'roll_id' => $rollId,
                'roll_code' => $rollCode,
                'warehouse_id' => (int)$input['warehouse_id'],
                'sku_id' => (int)$input['sku_id'],
                'purchase_order_id' => $poId,
                'purchase_order_line_id' => $polId,
                'import_container_id' => $importContainerId,
                'import_container_item_id' => $importContainerItemId,
                'reception_mode' => $receptionMode,
                'supplier_id' => $supplierId,
                'operator_name' => $operatorName,
            ]);

            $this->pdo->commit();
            return ['ok' => true, 'errors' => [], 'id' => $rollId];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function insertMovement(int $rollId, int $toWarehouseId, array $input): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO movements (entity_type, entity_id, movement_type, from_warehouse_id, to_warehouse_id, payload)
             VALUES (:entity_type, :entity_id, :movement_type, :from_warehouse_id, :to_warehouse_id, :payload)'
        );

        $payload = json_encode([
            'weight_kg' => (string)$input['weight_kg'],
            'received_qty' => isset($input['received_qty']) ? (float)$input['received_qty'] : 1.0,
            'reception_mode' => $this->normalizeReceptionMode((string)($input['reception_mode'] ?? 'QUANTITY')),
            'microns' => isset($input['microns']) && $input['microns'] !== '' ? (int)$input['microns'] : null,
            'width_mm' => isset($input['width_mm']) && $input['width_mm'] !== '' ? (int)$input['width_mm'] : null,
            'color' => isset($input['color']) && trim((string)$input['color']) !== '' ? trim((string)$input['color']) : null,
            'meters' => isset($input['meters']) && $input['meters'] !== '' ? (float)$input['meters'] : null,
            'purchase_order_id' => isset($input['purchase_order_id']) ? (int)$input['purchase_order_id'] : null,
            'purchase_order_line_id' => isset($input['purchase_order_line_id']) ? (int)$input['purchase_order_line_id'] : null,
            'import_container_id' => isset($input['import_container_id']) ? (int)$input['import_container_id'] : null,
            'import_container_item_id' => isset($input['import_container_item_id']) ? (int)$input['import_container_item_id'] : null,
            'operator_name' => trim((string)($input['operator_name'] ?? '')),
        ], JSON_UNESCAPED_UNICODE);

        $stmt->execute([
            ':entity_type' => 'ROLL',
            ':entity_id' => $rollId,
            ':movement_type' => 'RECEIPT',
            ':from_warehouse_id' => null,
            ':to_warehouse_id' => $toWarehouseId,
            ':payload' => $payload,
        ]);
    }

    private function insertEvent(string $type, array $payload): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO events (type, payload) VALUES (:type, :payload)');
        $stmt->execute([
            ':type' => $type,
            ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function validateCreate(array $input): array
    {
        $errors = [];

        $skuId = isset($input['sku_id']) ? (int)$input['sku_id'] : 0;
        if ($skuId <= 0) {
            $errors['sku_id'] = 'SKU es obligatorio.';
        }

        $warehouseId = isset($input['warehouse_id']) ? (int)$input['warehouse_id'] : 0;
        if ($warehouseId <= 0) {
            $errors['warehouse_id'] = 'Bodega es obligatoria.';
        } else {
            $stmt = $this->pdo->prepare('SELECT code FROM warehouses WHERE id = :id');
            $stmt->execute([':id' => $warehouseId]);
            $row = $stmt->fetch();
            if ($row === false) {
                $errors['warehouse_id'] = 'La bodega seleccionada no existe.';
            }
        }

        $mode = $this->normalizeReceptionMode((string)($input['reception_mode'] ?? 'QUANTITY'));
        $weight = isset($input['weight_kg']) ? (float)$input['weight_kg'] : 0.0;
        if ($mode === 'WEIGHT' && $weight <= 0) {
            $errors['weight_kg'] = 'Peso real (Kg) debe ser mayor a 0.';
        }
        if ($mode !== 'WEIGHT' && $weight < 0) {
            $errors['weight_kg'] = 'Peso real (Kg) no puede ser negativo.';
        }

        $receivedQty = isset($input['received_qty']) ? (float)$input['received_qty'] : 1.0;
        if ($mode === 'QUANTITY' && $receivedQty <= 0) {
            $errors['received_qty'] = 'Cantidad recibida debe ser mayor a 0.';
        }

        $operatorName = trim((string)($input['operator_name'] ?? ''));
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }

        if (isset($input['microns']) && $input['microns'] !== '') {
            $microns = (int)$input['microns'];
            if ($microns <= 0) {
                $errors['microns'] = 'Gramos debe ser mayor a 0.';
            }
        }

        if (isset($input['width_mm']) && $input['width_mm'] !== '') {
            $width = (int)$input['width_mm'];
            if ($width <= 0) {
                $errors['width_mm'] = 'Ancho (mm) debe ser mayor a 0.';
            }
        }

        if (isset($input['color']) && trim((string)$input['color']) !== '') {
            $color = trim((string)$input['color']);
            if ($color === '') {
                $errors['color'] = 'Color es inválido.';
            }
        }

        if (isset($input['meters']) && $input['meters'] !== '') {
            $meters = (float)$input['meters'];
            if ($meters <= 0) {
                $errors['meters'] = 'Metros lineales debe ser mayor a 0.';
            }
        }

        return $errors;
    }

    private function normalizeReceptionMode(?string $mode): string
    {
        return strtoupper(trim((string)$mode)) === 'WEIGHT' ? 'WEIGHT' : 'QUANTITY';
    }

    private function generateRollCode(): string
    {
        $date = gmdate('Ymd');
        $rand = bin2hex(random_bytes(3));
        return 'RB-' . $date . '-' . strtoupper($rand);
    }
}
