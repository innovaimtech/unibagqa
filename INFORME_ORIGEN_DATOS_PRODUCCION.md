# Informe De Origen De Datos Necesarios Para Producción

## Objetivo

Este informe explica de dónde sale cada dato que necesita el área de Producción en el sistema actual.

El foco no es solo listar tablas, sino responder estas preguntas:

- qué dato ve Producción
- de qué tabla sale
- de qué campo sale
- si viene desde ERP `unibagqa` o desde la base local de trazabilidad
- si ese dato hoy existe realmente en runtime
- si ese dato todavía falta integrar

## Resumen Ejecutivo

La Producción del sistema actual **no trabaja principalmente con las tablas ERP `prod_*`**.

Hoy, en runtime, Producción usa sobre todo datos de la base local de trazabilidad:

- `work_orders`
- `rolls`
- `events`
- `work_order_material_requests`
- `production_wastes`
- `chemical_weighings`
- `chemicals`
- `boxes`
- `pallets`
- `warehouses`

El ERP `unibagqa` sí se usa, pero principalmente para:

- bodegas
- datos de origen de una bobina
- proveedor
- OC
- línea de compra
- contenedor
- producto base

En otras palabras:

```text
ERP `unibagqa`
 -> entrega contexto maestro y de origen

Base local de trazabilidad
 -> controla la operación real de Producción
```

## Flujo General De Dónde Sale La Información

```text
ERP `unibagqa`
  |
  |-- proveedores
  |-- OCs
  |-- líneas
  |-- productos
  |-- contenedores
  |-- bodegas
  v
Recepción / Integración
  |
  |-- crea bobinas locales
  |-- vincula origen ERP
  v
Base local de trazabilidad
  |
  |-- OT
  |-- bobinas activas
  |-- solicitudes
  |-- tintas
  |-- mermas
  |-- bobina salida
  |-- corte
  |-- cajas
  |-- pallets
  v
Pantallas de Producción
```

## Base De Datos Que Realmente Alimenta Producción

### 1. Base ERP `unibagqa`

Se usa para:

- catálogo de producto base
- proveedor y país
- orden de compra y línea
- contenedor de importación
- bodegas ERP

### 2. Base Local De Trazabilidad

Se usa para:

- OT
- ejecución del proceso
- asignación de bobina
- trazabilidad interna
- tintas
- merma
- corte
- cajas
- pallets
- eventos

## Matriz Completa De Origen De Datos

## A. Datos Base De La OT

### Dato: código de OT

- Pantalla: Producción / detalle de OT
- Tabla: `work_orders`
- Campo: `ot_code`
- Fuente: trazabilidad local
- Uso: identificar la orden visible en Producción

### Dato: SKU final de la OT

- Tabla: `work_orders`
- Campo: `sku_final`
- Fuente: trazabilidad local
- Uso: mostrar qué producto final se espera fabricar

### Dato: cantidad objetivo

- Tabla: `work_orders`
- Campo: `target_qty`
- Fuente: trazabilidad local
- Uso: meta productiva de la OT

### Dato: estado de la OT

- Tabla: `work_orders`
- Campo: `status`
- Fuente: trazabilidad local
- Uso: saber si está abierta, activa, en corte o cerrada

### Dato: fecha de creación OT

- Tabla: `work_orders`
- Campo: `created_at`
- Fuente: trazabilidad local
- Uso: trazabilidad administrativa

### Dato: fecha de cierre OT

- Tabla: `work_orders`
- Campo: `closed_at`
- Fuente: trazabilidad local
- Uso: saber cuándo terminó

### Dato: operador que inició la OT

- Tabla: `events`
- Tipo de evento: `WORK_ORDER_STARTED`
- Campo: `payload.operator_name`
- Fuente: trazabilidad local
- Uso: mostrar quién activó o inició el trabajo

### Dato faltante: número ERP de producción

- Tabla ERP esperada: `prod_header`
- Campo esperado: `prd_number`
- Estado actual: no integrado al runtime de Producción
- Comentario: hoy la OT local no queda enlazada automáticamente al número ERP de producción

## B. Datos Del Producto A Fabricar

### Dato: código SKU o código base del producto

- Origen ERP primario: `item.item_number` o `item.item_number_prod`
- Origen runtime actual Producción: se refleja como texto en `work_orders.sku_final` o en bobinas ya recepcionadas
- Fuente real visible hoy: trazabilidad local con información ya normalizada

### Dato: nombre o especificación del producto

- Origen ERP: `item.item_title`
- Origen local cuando ya entra a trazabilidad: `rolls.sku_description`
- Uso: mostrar producto de entrada o de salida

### Dato: gramos

- Origen ERP: `item.item_reg_gsm`
- O también puede venir desde especificaciones de línea:
  - `supplier_order_items_specs.spec_id/spec_value`
- En runtime de Producción normalmente se usa ya guardado en bobina local
- Campo local: `rolls.grams`

### Dato: ancho

- Origen ERP: `item.item_reg_width`
- O desde `supplier_order_items_specs`
- Campo local en bobina: `rolls.width_cm`

### Dato: metros

- Origen ERP: `item.item_reg_length`
- O desde `supplier_order_items_specs`
- Campo local en bobina: `rolls.length_m`

### Dato: color

- Origen ERP posible:
  - `supplier_order_items_specs`
  - o parte del nombre del artículo
- Campo local en bobina: `rolls.color`

## C. Datos De La Bobina De Entrada

### Dato: código de bobina

- Tabla: `rolls`
- Campo: `roll_code`
- Fuente: trazabilidad local
- Uso: identificación física de la bobina en Producción

### Dato: ID de la bobina

- Tabla: `rolls`
- Campo: `id`
- Fuente: trazabilidad local
- Uso: relación interna y código de barra/etiqueta

### Dato: peso actual de la bobina

- Tabla: `rolls`
- Campo: `weight_kg`
- Fuente: trazabilidad local
- Uso: control de uso y finalización

### Dato: estado de la bobina

- Tabla: `rolls`
- Campo: `status`
- Fuente: trazabilidad local
- Uso: disponible, en proceso, consumida, etc.

### Dato: etapa de proceso de la bobina

- Tabla: `rolls`
- Campo: `process_stage`
- Fuente: trazabilidad local
- Uso: materia prima, impresa, cortada

### Dato: OT actual que está usando la bobina

- Tabla: `rolls`
- Campo: `current_work_order_id`
- Fuente: trazabilidad local

### Dato: bobina madre de una bobina procesada

- Tabla: `rolls`
- Campo: `parent_roll_id`
- Fuente: trazabilidad local
- Uso: trazabilidad de transformación

### Dato: OT fuente que generó la bobina

- Tabla: `rolls`
- Campo: `source_work_order_id`
- Fuente: trazabilidad local

## D. Origen ERP De La Bobina

Estos datos no controlan la ejecución productiva, pero sí explican de dónde vino la bobina.

### Dato: proveedor

- ERP:
  - tabla: `supplier`
  - campo: `supp_company`
- Se decora en runtime para mostrar contexto ERP

### Dato: país proveedor

- ERP:
  - tabla: `country`
  - campo: `country_name`

### Dato: orden de compra

- ERP:
  - tabla: `supplier_order`
  - campo: `sord_number`

### Dato: línea de compra

- ERP:
  - tabla: `supplier_order_items`
  - campo: `id`

### Dato: contenedor

- ERP:
  - tabla: `supplier_contenedor`
  - campo: `sord_contenedor`

### Dato: fecha de arribo

- ERP:
  - tabla: `supplier_contenedor`
  - campo: `sord_eta_puertounibag`
- Respaldo local posible: fecha de creación de la recepción

### Cómo se vincula esto con la bobina local

La bobina local guarda estos enlaces:

- `purchase_order_id`
- `purchase_order_line_id`
- `import_container_id`
- `import_container_item_id`
- `supplier_id`

Todos esos campos viven en `rolls` y permiten reconstruir su origen ERP.

## E. Datos De Solicitudes A Bodega

### Dato: tipo de solicitud

- Tabla: `work_order_material_requests`
- Campo: `request_type`
- Fuente: trazabilidad local
- Valores funcionales: bobina, tinta, insumo, otro

### Dato: ítem solicitado

- Tabla: `work_order_material_requests`
- Campo: `requested_item`

### Dato: cantidad solicitada

- Tabla: `work_order_material_requests`
- Campo: `requested_qty`

### Dato: unidad solicitada

- Tabla: `work_order_material_requests`
- Campo: `requested_unit`

### Dato: observación

- Tabla: `work_order_material_requests`
- Campo: `notes`

### Dato: estado de solicitud

- Tabla: `work_order_material_requests`
- Campo: `status`

### Dato: cantidad entregada

- Tabla: `work_order_material_requests`
- Campo: `delivered_qty`

### Dato: quién entregó

- Tabla: `work_order_material_requests`
- Campo: `delivered_by`

### Dato: fecha de entrega

- Tabla: `work_order_material_requests`
- Campo: `delivered_at`

### Dato: bobina entregada a la OT

- Tabla: `work_order_material_requests`
- Campo: `delivered_roll_id`
- Relación: enlaza con `rolls.id`

## F. Datos De Tintas

### Dato: código de tinta

- Tabla: `chemicals`
- Campo: `code`
- Fuente: trazabilidad local

### Dato: nombre de tinta

- Tabla: `chemicals`
- Campo: `name`

### Dato: peso inicial de tinta

- Evento inicial:
  - tabla: `events`
  - tipo: `CHEMICAL_INPUT_RECORDED`
  - campo: `payload.weight_kg`
- O pesaje completo:
  - tabla: `chemical_weighings`
  - campo: `initial_weight_kg`

### Dato: peso retorno

- Tabla: `chemical_weighings`
- Campo: `return_weight_kg`

### Dato: consumo neto

- Tabla: `chemical_weighings`
- Campo: `net_consumption_kg`

### Dato: operador que pesó

- Tabla: `chemical_weighings`
- Campo: `operator_name`

### Dato: fecha de pesaje

- Tabla: `chemical_weighings`
- Campo: `created_at`

## G. Datos De Merma

### Dato: etapa de la merma

- Tabla: `production_wastes`
- Campo: `waste_stage`

### Dato: motivo

- Tabla: `production_wastes`
- Campo: `reason`

### Dato: peso de merma

- Tabla: `production_wastes`
- Campo: `weight_kg`

### Dato: operador

- Tabla: `production_wastes`
- Campo: `operator_name`

### Dato: bobina asociada

- Tabla: `production_wastes`
- Campo: `roll_id`

### Dato: fecha

- Tabla: `production_wastes`
- Campo: `created_at`

## H. Datos De Cierre De Producción

### Dato: peso final bobina usada

- Tabla: `events`
- Evento: `WORK_ORDER_FINISHED`
- Campo: `payload.final_roll_weight_kg`

### Dato: peso final tintas

- Tabla: `events`
- Evento: `WORK_ORDER_FINISHED`
- Campo: `payload.final_chemical_weight_kg`

### Dato: cajas generadas

- Tabla: `events`
- Evento: `WORK_ORDER_FINISHED`
- Campo: `payload.box_qty`

### Dato: merma total al cierre

- Tabla: `events`
- Evento: `WORK_ORDER_FINISHED`
- Campo: `payload.waste_kg`

### Dato: nueva bobina de salida

- Tabla: `events`
- Evento: `WORK_ORDER_FINISHED`
- Campo: `payload.output_roll_id`

### Dato: fecha de cierre

- Tabla: `events`
- Evento: `WORK_ORDER_FINISHED`
- Campo: `created_at`

## I. Datos De La Bobina De Salida

### Dato: nueva bobina generada

- Tabla: `rolls`
- Campo clave: `id`
- Origen: creada al finalizar Producción

### Dato: código nueva bobina

- Tabla: `rolls`
- Campo: `roll_code`

### Dato: peso nueva bobina

- Tabla: `rolls`
- Campo: `weight_kg`

### Dato: etapa nueva bobina

- Tabla: `rolls`
- Campo: `process_stage`
- Valor funcional esperado: `PRINTED`

### Dato: bobina madre

- Tabla: `rolls`
- Campo: `parent_roll_id`

### Dato: OT que la generó

- Tabla: `rolls`
- Campo: `source_work_order_id`

## J. Datos De Corte

### Dato: unidades por caja

- Origen runtime actual: ingreso manual o parámetro local del proceso de corte
- No proviene hoy desde ERP `unibagqa`

### Dato: cajas por pallet

- Origen runtime actual: ingreso manual o parámetro local del proceso de corte
- No proviene hoy desde ERP

### Dato: cantidad total cortada

- Tabla: `events`
- Evento: `CUT_COMPLETED`
- Campo de payload según proceso generado

### Dato: rollo origen del corte

- Tabla: `boxes`
- Campo: `source_roll_id`
- Relación con `rolls.id`

## K. Datos De Cajas

### Dato: código de caja

- Tabla: `boxes`
- Campo: `box_code`

### Dato: OT asociada

- Tabla: `boxes`
- Campo: `work_order_id`

### Dato: bobina origen

- Tabla: `boxes`
- Campo: `source_roll_id`

### Dato: unidades

- Tabla: `boxes`
- Campo: `units_qty`

### Dato: estado

- Tabla: `boxes`
- Campo: `status`

### Dato: pallet asignado

- Tabla: `boxes`
- Campo: `pallet_id`

### Dato: bodega

- Tabla: `boxes`
- Campo: `warehouse_id`

## L. Datos De Pallets

### Dato: código de pallet

- Tabla: `pallets`
- Campo: `pallet_code`

### Dato: OT asociada

- Tabla: `pallets`
- Campo: `work_order_id`

### Dato: bobina origen

- Tabla: `pallets`
- Campo: `source_roll_id`

### Dato: unidades totales

- Tabla: `pallets`
- Campo: `units_total`

### Dato: estado del pallet

- Tabla: `pallets`
- Campo: `status`

### Dato: bodega del pallet

- Tabla: `pallets`
- Campo: `warehouse_id`

### Dato: fecha de creación del pallet

- Tabla: `pallets`
- Campo: `created_at`

## M. Datos De Bodegas Que Usa Producción

### Dato: ID de bodega

- ERP:
  - tabla: `company_shops_storehouses`
  - campo: `id`

### Dato: nombre de bodega

- ERP:
  - tabla: `company_shops_storehouses`
  - campo: `st_name`

### Dato: estado de bodega

- ERP:
  - tabla: `company_shops_storehouses`
  - campo: `st_status`

### Cómo se usa en Producción

No se consulta esa tabla ERP directamente cada vez.

Primero se sincroniza a la tabla local:

- `warehouses`

Por tanto, el runtime de Producción luego trabaja con:

- tabla local `warehouses`
- pero el origen maestro sigue siendo ERP

## N. Datos De Stock

### Dato: stock agregado por producto y bodega en ERP

- ERP:
  - tabla: `item_shops_storehouses`
  - campos:
    - `item_id`
    - `st_id`
    - `iss_inventory`
    - `iss_inventory_reserved`

### Uso real en Producción actual

- apoyo conceptual para disponibilidad
- no resuelve trazabilidad por bobina física

### Dato que sí usa la operación trazable

- bobinas disponibles reales en:
  - `rolls`

Conclusión:

- ERP da stock agregado
- trazabilidad da stock unitario trazable

## O. Datos De Operador

### Dato: nombre de operador en runtime actual

- No sale directamente de ERP en Producción actual
- Se guarda como texto en eventos y registros locales

Campos típicos:

- `events.payload.operator_name`
- `production_wastes.operator_name`
- `chemical_weighings.operator_name`

### Dato ERP del operario real

- tabla: `workers`
- campos:
  - `id`
  - `wrk_firstname`
  - `wrk_lastname`
  - `wrk_uid`

### Dato del usuario de sistema

- tabla: `user`
- campos:
  - `id`
  - `user_login`
  - `user_firstname`
  - `user_lastname`

### Estado actual

- estas tablas ERP existen
- pero Producción actual no las usa como fuente central en runtime
- hoy el nombre del operador se guarda como texto libre en trazabilidad

## P. Datos De Máquina, Equipo Y Turno

### Dato esperado: máquina o equipo

- ERP:
  - `prod_worker_init.win_equipoid`
  - `prod_agenda.ag_equipo_id`

### Dato esperado: tipo de equipo

- ERP:
  - `prod_agenda.ag_equipotype_id`

### Dato esperado: planta

- ERP:
  - `prod_worker_init.win_plantaid`
  - `prod_header.prd_plantaid`
  - `prod_agenda.ag_plantaid`

### Dato esperado: turno

- ERP:
  - `workers.wrk_turno_turnoid`
  - `workers.wrk_turno_state`

### Estado actual

- estos datos existen en `unibagqa`
- pero hoy no están integrados al runtime principal de Producción

## Q. Datos De Agenda Y Producción ERP

### Agenda productiva

- tabla: `prod_agenda`
- datos:
  - fecha
  - equipo
  - tipo de equipo
  - cantidad programada
  - referencia a cabecera productiva

### Cabecera productiva

- tabla: `prod_header`
- datos:
  - número de producción
  - requerimiento
  - planta
  - estado

### OT del operario

- tabla: `prod_worker_ot`
- datos:
  - agenda ejecutada
  - inicio del operario
  - fecha inicio/fin
  - estado

### Eventos del operario

- tabla: `prod_worker_ot_events`
- datos:
  - apertura
  - producción
  - pausa
  - mantención
  - comentarios
  - cantidades
  - metros
  - kg bobina

### Estado actual

- estas tablas están documentadas e inspeccionadas
- pero **no son la fuente principal de la Producción actual en la aplicación**

## R. Datos De Balanza

### Dato esperado ERP

- tabla ERP: `balanca_kgs`
- campo detectado: `kg`

### Estado actual en el sistema

- la aplicación actual usa `ScaleService`
- el peso se obtiene desde integración local o manual, no desde el runtime directo de `balanca_kgs`

## Qué Sale De ERP Y Qué Sale De Trazabilidad

## Sale Principalmente De ERP

- proveedor
- país
- orden de compra
- línea de compra
- producto base
- especificaciones técnicas base
- contenedor
- fecha de arribo
- bodegas
- stock agregado
- usuarios ERP
- operarios ERP
- agenda y producción ERP

## Sale Principalmente De Trazabilidad

- OT visible en Producción
- estado operativo de la OT
- bobina asignada
- solicitudes a bodega
- tintas
- merma
- eventos del proceso
- bobina de salida
- cajas
- pallets
- inventario trazable por unidad

## Datos Que Hoy Faltan Integrar Si Quieres Producción 100% ERP-Conectada

- vínculo formal entre `work_orders` y `prod_header`
- vínculo formal entre operador textual y `workers.id`
- vínculo de máquina real usando `win_equipoid` o `ag_equipo_id`
- vínculo de turno usando `workers.wrk_turno_turnoid`
- diccionario de estados ERP:
  - `prd_status`
  - `ag_status`
  - `win_status`
  - `wok_status`
- maestro de equipos y tipos de equipo
- lectura operativa directa desde `prod_worker_ot_events`

## Conclusión Final

Si la pregunta es:

**"¿de dónde sale cada dato que necesita Producción hoy?"**

La respuesta es:

- la operación diaria de Producción sale mayoritariamente de la **base local de trazabilidad**
- el ERP `unibagqa` entrega principalmente el **contexto maestro y el origen documental**

Si la pregunta es:

**"¿de dónde debería salir cada dato cuando Producción quede conectada totalmente al ERP?"**

La respuesta es:

- OT, máquina, turno, operario real, agenda y ejecución deberían vincularse con:
  - `prod_header`
  - `prod_agenda`
  - `prod_worker_init`
  - `prod_worker_ot`
  - `prod_worker_ot_events`

pero eso todavía no está conectado como fuente principal en el runtime actual.

## Archivos Fuente De Referencia

- `src/Db.php`
- `src/ReceptionService.php`
- `public/index.php`
- `MAPA_COMPLETO_UNIBAGQA.md`
- `INFORME_UNIBAGQA_PRODUCCION_OPERARIOS.md`
- `INFORME_MAPA_DATOS_UNIBAGQA_TRAZABILIDAD.md`
