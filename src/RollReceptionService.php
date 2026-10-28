<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Subservicio de Recepción de Bobinas e Insumos Unitarios
 * (src/RollReceptionService.php)
 * =============================================================================
 *
 * Responsabilidad:
 * Gestiona el alta y registro inicial de bobinas físicas en la base de datos de
 * trazabilidad (TRZ). Se encarga de:
 * 1. Validar técnicamente las especificaciones de la bobina (gramaje/micrones,
 *    ancho en mm, color, metros lineales y peso en kg).
 * 2. Generar el código único e irrepetible de trazabilidad (RB-YYYYMMDD-XXXXXX).
 * 3. Insertar el registro maestro en la tabla 'rolls' con estado 'RECEIVED'.
 * 4. Registrar el movimiento inicial de entrada en la tabla 'movements' (RECEIPT).
 * 5. Emitir un evento inmutable en la bitácora 'events' (ROLL_RECEIVED).
 * 6. Soportar recepción masiva en lote mediante createUnits().
 */
final class RollReceptionService
{
    /**
     * @param PDO $pdo Conexión a la base de datos local de trazabilidad (unibag_trazabilidad).
     */
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Registra una bobina individual en el sistema con control de transacción.
     *
     * @param array<string, mixed> $input Datos capturados en el formulario de recepción:
     *     - sku_id: ID del catálogo de telas/insumos.
     *     - warehouse_id: Bodega de destino inicial (ej. Bodega 100 Materias Primas).
     *     - weight_kg: Peso real capturado en balanza o teclado.
     *     - reception_mode: 'WEIGHT' (por peso) o 'QUANTITY' (por unidades/conteo).
     *     - microns / grams: Gramaje o micrones de la tela.
     *     - width_mm: Ancho en milímetros.
     *     - color: Color de la tela.
     *     - meters: Metros lineales estimados o de fábrica.
     *     - purchase_order_id: Orden de compra asociada (opcional).
     *     - purchase_order_line_id: Línea de orden de compra (opcional).
     *     - import_container_id: Contenedor marítimo de importación (opcional).
     *     - supplier_id: Proveedor de la materia prima (opcional).
     *     - operator_name: Nombre del operario responsable de la recepción.
     *
     * @return array{ok: bool, errors: array<string, string>, id: int|null}
     */
    public function createRoll(array $input): array
    {
        // Normalizar valores en cero a null para campos opcionales
        $input['weight_kg'] = $input['weight_kg'] ?? 0;
        foreach (['microns', 'width_mm', 'meters'] as $field) {
            if (isset($input[$field]) && (float)$input[$field] === 0.0) {
                $input[$field] = null;
            }
        }

        // Validación estricta de reglas de negocio
        $errors = $this->validateCreate($input);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'id' => null];
        }

        // Generar identificador único con prefijo RB (Roll/Bobina)
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

        // Manejo atómico de transacción (si no viene abierta externamente)
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            // 1. Insertar el registro principal de la bobina en 'rolls'
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

            // 2. Registrar movimiento de inventario en la tabla 'movements'
            $this->insertMovement($rollId, (int)$input['warehouse_id'], $input);

            // 3. Registrar auditoría inmutable en la tabla 'events'
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

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return ['ok' => true, 'errors' => [], 'id' => $rollId];
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Registra un lote múltiple de unidades de forma secuencial y atómica.
     *
     * @param array<string, mixed> $input Contiene received_qty con el número de unidades a crear.
     * @return array{ok: bool, errors: array<string, string>, id: int|null, ids?: list<int>}
     */
    public function createUnits(array $input): array
    {
        $quantity = (float)($input['received_qty'] ?? 0);
        if (!is_finite($quantity) || $quantity < 1 || floor($quantity) !== $quantity || $quantity > 5000) {
            return ['ok' => false, 'errors' => ['received_qty' => 'Ingresa entre 1 y 5000 unidades enteras por recepción.'], 'id' => null];
        }
        $input['received_qty'] = 1.0;
        $input['weight_kg'] = 0.0;
        $input['reception_mode'] = 'QUANTITY';
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $ids = [];
            for ($i = 0; $i < (int)$quantity; $i++) {
                $result = $this->createRoll($input);
                if (!$result['ok']) {
                    if ($ownsTransaction) {
                        $this->pdo->rollBack();
                    }
                    return $result;
                }
                $ids[] = (int)$result['id'];
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return ['ok' => true, 'errors' => [], 'id' => $ids[0], 'ids' => $ids];
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Inserta el movimiento físico en el libro de movimientos de inventario.
     *
     * @param int $rollId ID de la bobina generada.
     * @param int $toWarehouseId Bodega donde ingresa el material.
     * @param array<string, mixed> $input Datos originales de recepción.
     */
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

    /**
     * Registra un evento inmutable de trazabilidad en la tabla 'events'.
     *
     * @param string $type Tipo de evento (ej. 'ROLL_RECEIVED').
     * @param array<string, mixed> $payload Datos del evento para auditoría.
     */
    private function insertEvent(string $type, array $payload): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO events (type, payload) VALUES (:type, :payload)');
        $stmt->execute([
            ':type' => $type,
            ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Valida exhaustivamente que los datos de entrada cumplan con los requisitos técnicos.
     *
     * @param array<string, mixed> $input
     * @return array<string, string> Mapa de errores por campo (vacío si todo es válido).
     */
    private function validateCreate(array $input): array
    {
        $errors = [];

        // 1. Validar que se seleccionó un SKU válido
        $skuId = isset($input['sku_id']) ? (int)$input['sku_id'] : 0;
        if ($skuId <= 0) {
            $errors['sku_id'] = 'SKU es obligatorio.';
        }

        // 2. Validar que la bodega exista en la base de datos
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

        // 3. Validar peso real según la modalidad de recepción
        $mode = $this->normalizeReceptionMode((string)($input['reception_mode'] ?? 'QUANTITY'));
        $weight = isset($input['weight_kg']) ? (float)$input['weight_kg'] : 0.0;
        if ($mode === 'WEIGHT' && $weight <= 0) {
            $errors['weight_kg'] = 'Peso real (Kg) debe ser mayor a 0.';
        }
        if ($mode !== 'WEIGHT' && $weight < 0) {
            $errors['weight_kg'] = 'Peso real (Kg) no puede ser negativo.';
        }

        // 4. Validar cantidad de unidades recibidas
        $receivedQty = isset($input['received_qty']) ? (float)$input['received_qty'] : 1.0;
        if ($mode === 'QUANTITY' && $receivedQty <= 0) {
            $errors['received_qty'] = 'Cantidad recibida debe ser mayor a 0.';
        }

        // 5. Validar nombre del operario responsable
        $operatorName = trim((string)($input['operator_name'] ?? ''));
        if ($operatorName === '') {
            $errors['operator_name'] = 'Operador es obligatorio.';
        }

        // 6. Validar micrones / gramaje si fue suministrado
        if (isset($input['microns']) && $input['microns'] !== '') {
            $microns = (int)$input['microns'];
            if ($microns <= 0) {
                $errors['microns'] = 'Gramos debe ser mayor a 0.';
            }
        }

        // 7. Validar ancho en mm
        if (isset($input['width_mm']) && $input['width_mm'] !== '') {
            $width = (int)$input['width_mm'];
            if ($width <= 0) {
                $errors['width_mm'] = 'Ancho (mm) debe ser mayor a 0.';
            }
        }

        // 8. Validar color
        if (isset($input['color']) && trim((string)$input['color']) !== '') {
            $color = trim((string)$input['color']);
            if ($color === '') {
                $errors['color'] = 'Color es inválido.';
            }
        }

        // 9. Validar metros lineales
        if (isset($input['meters']) && $input['meters'] !== '') {
            $meters = (float)$input['meters'];
            if ($meters <= 0) {
                $errors['meters'] = 'Metros lineales debe ser mayor a 0.';
            }
        }

        return $errors;
    }

    /**
     * Normaliza el modo de recepción a 'WEIGHT' o 'QUANTITY'.
     */
    private function normalizeReceptionMode(?string $mode): string
    {
        return strtoupper(trim((string)$mode)) === 'WEIGHT' ? 'WEIGHT' : 'QUANTITY';
    }

    /**
     * Genera un código de bobina único en formato RB-YYYYMMDD-XXXXXX (ej. RB-20261026-A1B2C3).
     */
    private function generateRollCode(): string
    {
        $date = gmdate('Ymd');
        $rand = bin2hex(random_bytes(3));
        return 'RB-' . $date . '-' . strtoupper($rand);
    }
}
