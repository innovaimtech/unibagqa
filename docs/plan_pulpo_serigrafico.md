# Plan: Pulpo Serigráfico con consumo de bolsas por caja y todo consumo por kardex (8 nov 2026)

## Contexto
El Pulpo imprime sobre bolsas ya hechas (de algodón compradas o en blanco hechas en CyS), no sobre bobinas. Hoy el sistema solo le deja montar bobinas TEL y rechaza las bolsas. Tampoco descuenta las bolsas, y la tinta se descuenta de la bodega 3000 sin pasar por el kardex.

**Lo que pediste:**
- Las bolsas no tienen código propio: el código va en la **caja**, y la caja guarda sus unidades (ej. 200).
- Al escanearla en el pulpo se descuentan solo las usadas (ej. 50): a la caja le quedan 150 y el inventario del SKU baja 50, **por kardex**.
- Las cajas se piden por **Solicitudes MP** y se traspasan a la bodega 3000; el pulpo consume desde ahí.
- En CyS, cada caja recibe su **código al cerrar el embalaje**.
- Las bolsas impresas pasan después a una **OT de Embalaje** (flujo que ya existe).
- **Todo lo que se consuma debe descontarse por el kardex**, en todos lados.

**Lo que hay del sistema anterior:** el pulpo era una variante de serigrafía (equipo 36) con pasadas frente/dorso y una ficha técnica (horno, velocidades, camillas, 10 estaciones con bastidor/mesh/tinta). No descontaba bolsas ni tinta, salvo que el operador lo hiciera a mano. Tenía errores que no se copian:
- La validación de pasadas no cuadraba con más de una imagen por bastidor.
- Solo se leían 5 de los 10 colores.
- La tinta se sumaba dos veces al reenviar el formulario.
- Había SQL roto.

El proyecto actual ya trae la ficha y las pasadas (`savePulpoV2Data`, `syncPulpoAgendaStats`). La tabla TRZ `boxes` ya existe y Despacho ya le descuenta unidades parciales, pero las cajas no tienen ítem, CC ni bodega del ERP.

## Fase 1. Un solo motor de kardex (base de todo)
Servicio nuevo `src/StockLedgerService.php`. Junta lo mejor de dos piezas que ya funcionan:
- `HandleConsumptionService`: diario TRZ idempotente, `LOCK TABLES` para MyISAM y reversa.
- `RewindAndPackagingService::postPackagingStockChange`: manejo de CC con `iss_order_id` y las 19 columnas de `tran_data`.

API:
- `post(key, itemId, stId, delta, orderId=0, issueId, annotation, otId, user)` escribe el documento completo: `stockchanges`, `stockchanges_items`, `item_shops_storehouses` (fila del CC si hay `orderId`) y `tran_data`. Deja la marca `[TRZ-LED:key]`.
  - Repetir el mismo `key` no duplica.
  - Si no hay stock suficiente, falla con un mensaje claro.
- `reverse(key)` hace un contra-documento con la marca «reversa».
- `transfer(...)` para traspasos (`storehousechanges` + `sthdown`/`sthup`). Reutiliza la lógica de `postStorehouseTransfer`.
- Tabla TRZ de diario `stock_ledger_operations` (PENDING/DONE/REVERSED) y `resume()` para terminar operaciones cortadas.

El número de documento sale de `generateNextStockChangeNumber`, que se mueve a este servicio.

## Fase 2. Caja con código y saldo
- **Tabla `boxes`** (TRZ), columnas nuevas: `erp_item_id`, `erp_st_id`, `erp_order_id` (CC) y `units_initial`, que pasa a quedar siempre lleno.
- **Tabla `traceability_warehouse_entries`**: igual, `quantity` / `quantity_initial`.
- **Un solo generador de código** (`BoxCodeService::next()`, con contador seguro en vez de `SELECT MAX(id)`) y un solo buscador `BoxLookup::find(code)`. Los tres formatos actuales (`BOX-`, `BX-fecha-`, `BX`+hex) se siguen leyendo.
- **Formas de crear una caja:**
  1. **Cierre de embalaje CyS** (`RewindAndPackagingService::savePackagingEventDetail` / `finalizeCysPackagingEvent`): se crea una caja por cada caja completa más la caja final, con CC, SKU, bodega de destino y unidades. Se imprimen sus etiquetas (`PrintService::printBoxLabel`). El stock ya entra por `postPackagingStockChange`, así que la caja **no suma stock otra vez**.
     - En el cierre mixto, las cajas de cada CC llevan su CC y su bodega.
     - Anular o borrar el embalaje anula sus cajas, pero solo si ninguna fue consumida. Si alguna se consumió, se bloquea con un aviso.
  2. **Recepción de bolsas compradas** (algodón): opción «caja» en el ingreso por OC. Crea N cajas × M unidades y suma al ERP por `StockLedgerService` (no como bobina en `rolls`).
  3. **Ingreso trazable `BOX`** (`/stock/traceability-entry`) para cajas que ya están en stock: solo etiqueta, sin sumar. Ahora guarda ítem, bodega y CC y valida que haya stock suficiente para cubrirlas.
- **Despacho** (`DispatchService`) ya descuenta parcial en la caja. Se mantiene así: el ERP descuenta con la guía. Solo cambia que use `BoxLookup`.

## Fase 3. Solicitud y traspaso de cajas a la bodega 3000
- `MaterialSupplyService` hoy solo acepta TEL. Se le agrega el tipo «caja de bolsas»: el pulpo pide el SKU de bolsa (o el CC) y la cantidad de cajas.
- Al despachar, bodega escanea la caja: `bookTransfer` (o `StockLedgerService::transfer`) traspasa **el saldo de la caja** con su CC a la bodega 3000, con kardex. La caja queda con `erp_st_id` = 3000.
- Al devolver una caja con saldo, el traspaso es inverso hacia su bodega.

## Fase 4. Pulpo: escanear la caja y consumir las bolsas usadas
- En `ProductionEventsModule` (escaneo, cerca de la rama de manillas, l.721-751), si `$isSeriPulpo` y el código es una caja:
  1. Pantalla de confirmación con el saldo de la caja.
  2. Al terminar de usarla o al cerrar la producción, el operador indica cuántas usó.
  3. `PulpoBagConsumptionService::consume(box, units, ot)` descuenta `boxes.units_qty` y `traceability_warehouse_entries.quantity` y llama a `StockLedgerService::post(-units)` en la 3000 con el CC. Si la caja llega a 0, queda CONSUMED.
  - Se puede usar parte de una caja y seguir otro día o en otra OT (la caja sigue «en máquina»).
  - Se registra `reverse` al anular.
- **Control:** al terminar producción se compara lo consumido con bolsas terminadas + merma (`evt_amount` + `prod_worker_ot_defectunits`). Si no cuadra, aviso, y para cerrar la OT se pide supervisor (como `assertNoPendingForWorkOrder` de manillas).
- **Detección:** un solo helper `MachineKind::isPulpo(equipo)` (equipo 36 o tipo 22) en reemplazo de las 6 detecciones repetidas. En pulpo se quita la restricción TEL del escáner y se aceptan cajas.
- **Ficha técnica:** corregir lo heredado.
  - Leer los 10 colores.
  - Que la tinta de la ficha **no se sume de nuevo** al reenviar: el total se toma de los consumos de tinta registrados.
  - Validación de pasadas: unidades = pasadas × imágenes por bastidor, sin división por cero.
- **Salida:** nada nuevo. Las bolsas impresas van a la OT de Embalaje, que ingresa el stock del CC (ya funciona). Se verifica con una prueba.

## Fase 5. Tinta por kardex (serigrafía plana y pulpo)
- `ProductionMaterialsService::recordSerigrafiaInkConsumption` y `deleteMachineInkLoad` reemplazan el `UPDATE item_shops_storehouses` directo (l.4838 y 4953) por `StockLedgerService::post` / `reverse`.
  - Si falla, se informa: se quitan los `catch {}` vacíos.
- La tinta del pulpo usa el mismo formulario de consumo. `val_total_tinta` pasa a ser solo lectura.

## Fase 6. Resto de consumos sin kardex → por el motor común
Mismo patrón en cada caso: cambiar el `UPDATE` directo por `StockLedgerService` y mantener los mensajes. Por orden de riesgo:
1. **Insumos de embalaje:** `RewindAndPackagingService::adjustWarehouseStock`, usado por `consumePackagingSuppliesInner` y por la reversa en `ProductionEventsService` l.894-901.
2. **Bobinas:**
   - `adjustBodega3000InventoryBySku` (montaje, devolución, sobrante).
   - `requestRollsForWorkOrder` (l.720-750; pasa a ser traspaso).
   - `assignBodega3000RollToMachineUnlocked` (l.6311, 6387).
   - `recordStockChangeForRollConsumption`: agregar `tran_data` y no duplicar el descuento ya hecho al montar.
3. **Rebobinado:** `executeReboEmpalme`, `executeReboFinalize` y `deleteReboStockChanges` (falta `tran_data`; en un caso falta también el stock).
4. **`scrapRollToMerma`:** hoy falla en silencio (tabla `items` inexistente, kg en vez de unidades, columnas erradas). Se corrige.
5. **Respaldos de `RollStockService`** (`adjustErpInventoryRow`): si falla el kardex, se deja pendiente para reintentar, en vez de tocar solo el stock.

## Fase 7. Revisión (solo lectura)
`scripts/audit_stock_ledger.php`, al estilo de `audit_cys_packaging.php`:
- Saldo de cajas TRZ frente al stock ERP por SKU, CC y bodega.
- Kardex frente a `iss_inventory` en los ítems que toca el sistema.
- Operaciones del diario que quedaron PENDING.

## Archivos principales
- **Nuevos:**
  - `src/StockLedgerService.php`
  - `src/BoxCodeService.php` + `BoxLookup`
  - `src/PulpoBagConsumptionService.php`
  - `src/MachineKind.php`
  - `scripts/audit_stock_ledger.php`
  - Vista de confirmación de caja en `src/Views/production/materials/`
- **Modificados:**
  - `ProductionEventsModule.php`, `ProductionMaterialsService.php`, `ProductionMaterialsModule.php`
  - `RewindAndPackagingService.php`, `ProductionEventsService.php`
  - `MaterialSupplyService.php`, `RollStockService.php`, `DispatchService.php`
  - `ReceptionSchemaService.php` (columnas nuevas)
  - `PrintService.php`, `operator_console.php`, `serigrafia/info_section.php`

## Orden de trabajo y entregas
Cada fase se entrega por separado con lint, QA y prueba en MySQL temporal, y tú decides el commit:
1. Fases 1 y 5: motor + tinta (cambio chico y visible).
2. Fase 2: cajas con código.
3. Fases 3 y 4: pulpo.
4. Fase 6: resto de consumos, uno por uno.
5. Fase 7: revisión.

## Verificación
- **QA sqlite por fase** (`scripts/qa/09_stock_ledger.php`, `09_box_codes.php`, `09_pulpo_bags.php`, `09_ink_kardex.php`) y `run_all.php` en «TODO OK».
- **Arnés MySQL temporal** con la estructura real del ERP (como `C:\xampp\tmp\pp\cys_safety_mysql.php`), que se borra al final. Caso de punta a punta:
  1. Cierre CyS que crea 3 cajas de 200.
  2. Solicitud y traspaso de una caja a la 3000.
  3. El pulpo consume 50; la caja queda con 150.
  4. El ERP baja 50 en la 3000 por el CC, con `tran_data`.
  5. Al anular, todo vuelve.
  6. La tinta deja kardex.
  7. Repetir la misma operación no duplica.
  8. El kardex cuadra con `iss_inventory`.
- Capturas de la consola del pulpo con Chrome headless.
- **Base real:** solo lecturas. Las columnas y tablas TRZ nuevas se crean al instalar (`ensureSchema`), lo que se te avisará antes. Ninguna escritura en ERP ni TRZ reales sin tu pedido.

## Dudas que quedan (no bloquean la Fase 1)
- **SKU de las bolsas de algodón compradas:** ¿existe ya en el ERP y en qué bodega se reciben?
- **Cajas que ya están hoy en bodega:** ¿se etiquetan con el ingreso trazable antes de empezar?
- **Bolsas de más:** si el pulpo consume más bolsas que terminadas + merma, ¿solo aviso o bloqueo con supervisor? Propuesta: supervisor.

---

## Estado de la implementación (8 nov 2026)
Implementado, sin commit. Pruebas: `scripts/qa/09_stock_ledger.php`, `scripts/qa/09_pulpo_bags.php` y `run_all.php` en «TODO OK». Trazabilidad probada en MySQL temporal con la estructura real. ERP real no probado (sin conexión).

**Cambios respecto del plan:**
- **Máquina Embalaje propia:** sigue sin cargar stock desde este sistema. Hoy lo carga el sistema antiguo, y cargarlo aquí lo duplicaría. Las bolsas impresas por el pulpo entran a bodega igual que hoy.
- **Bolsas compradas:** se reciben como siempre y sus cajas se etiquetan con el ingreso trazable «Caja». La etiqueta queda ligada al artículo, la bodega y el CC, y no deja etiquetar más bolsas que el stock del ERP. La recepción por OC no se cambió: crea una fila por unidad, con un máximo de 5.000.
- **Revisión de solo lectura:** `php scripts/audit_stock_ledger.php`.
