# Mapa Completo De `unibagqa`

## Objetivo

Este documento deja mapeada la base ERP `unibagqa` con foco en:

- tablas relevantes
- relaciones entre tablas
- flujo funcional de los datos
- conexión actual con el sistema de trazabilidad
- puntos donde hoy el proyecto lee o escribe en ERP
- riesgos y recomendaciones para la integración futura

## Alcance

Este mapa se construye usando:

- el código real del proyecto
- el servicio principal de integración ERP en `src/ReceptionService.php`
- la conexión de base en `src/Db.php`
- el bootstrap web en `public/index.php`
- scripts de inspección del esquema ERP
- el informe funcional previo de integración

No reemplaza un DER extraído directamente desde MySQL con claves foráneas físicas, porque varias relaciones aquí están inferidas por uso funcional del sistema y por los joins existentes en el código.

## Conexión Actual Del Proyecto

La aplicación abre dos conexiones:

- conexión local de trazabilidad
- conexión ERP hacia `unibagqa`

Archivos base:

- `src/Db.php`
- `public/index.php`

Flujo actual:

```text
Usuario -> Aplicación PHP
               |
               |-- PDO trazabilidad
               |-- PDO ERP (`unibagqa`)
```

## Rol De `unibagqa`

`unibagqa` hoy actúa como ERP fuente para:

- proveedores
- órdenes de compra
- líneas de OC
- productos
- bodegas
- stock agregado por producto y bodega
- contenedores de importación
- usuarios
- operarios
- parte del modelo ERP de producción

El módulo de trazabilidad usa esos datos como base de negocio y genera su propia trazabilidad detallada en tablas locales.

## Tablas ERP Mapeadas

### 1. `supplier`

Propósito:

- maestro de proveedores

Campos relevantes:

- `id`
- `supp_company`
- `supp_short`
- `supp_status`
- `supp_countryid`

Uso en el proyecto:

- mostrar proveedor de la OC
- clasificar el origen del suministro
- enlazar proveedor con recepción nacional o importación

Relaciones:

- `supplier.supp_countryid -> country.id`
- `supplier.id <- supplier_order.sord_supplier_id`

### 2. `country`

Propósito:

- catálogo de países

Campos relevantes:

- `id`
- `country_name`

Uso en el proyecto:

- distinguir proveedor nacional vs importado
- mostrar país del proveedor

Relaciones:

- `country.id <- supplier.supp_countryid`

### 3. `supplier_order`

Propósito:

- cabecera de orden de compra

Campos relevantes:

- `id`
- `sord_number`
- `sord_supplier_id`
- `sord_status`
- `sord_crtdat`
- `sord_type`
- `sord_order_shipped`
- `sord_shop_id`
- `sord_company_id`

Uso en el proyecto:

- listado de OCs recepcionables
- trazabilidad de abastecimiento
- cálculo de avance de recepción

Relaciones:

- `supplier_order.sord_supplier_id -> supplier.id`
- `supplier_order.id <- supplier_order_items.sord_id`

### 4. `supplier_order_items`

Propósito:

- líneas de cada orden de compra

Campos relevantes:

- `id`
- `sord_id`
- `item_id`
- `item_amount`
- `item_amount_shipped`
- `item_kgs`
- `item_desc`
- `item_pos`

Uso en el proyecto:

- líneas recepcionables
- cantidad ordenada
- cantidad ya recepcionada
- producto comprado
- descripción libre de la línea

Relaciones:

- `supplier_order_items.sord_id -> supplier_order.id`
- `supplier_order_items.item_id -> item.id`
- `supplier_order_items.id <- supplier_contenedor_items.sord_pos_id`
- `supplier_order_items` se complementa con `supplier_order_items_specs`

### 5. `supplier_order_items_specs`

Propósito:

- especificaciones técnicas de cada línea de compra

Campos relevantes:

- `sord_id`
- `item_id`
- `item_pos`
- `spec_id`
- `spec_value`

Uso esperado:

- gramos
- ancho
- color
- metros lineales
- atributos técnicos adicionales

Observación:

- para usarla bien se necesita un diccionario funcional `spec_id -> significado`
- hoy el proyecto la reconoce conceptualmente, pero no la explota completa en runtime principal

### 6. `item`

Propósito:

- catálogo maestro de productos ERP

Campos relevantes:

- `id`
- `item_number`
- `item_number_prod`
- `item_title`
- `item_status`
- `item_reg_gsm`
- `item_reg_width`
- `item_reg_length`
- `item_reg_kg`
- `item_prodwrk_act`
- `item_purchasable`

Uso en el proyecto:

- código SKU
- nombre o especificación del producto
- gramos
- ancho
- metros
- datos base para recepción y etiquetas

Relaciones:

- `item.id <- supplier_order_items.item_id`
- `item.id <- item_shops_storehouses.item_id`

### 7. `company_shops_storehouses`

Propósito:

- catálogo de bodegas ERP

Campos relevantes:

- `id`
- `st_name`
- `st_shop_id`
- `st_status`
- `st_unibagreserva_act`
- `st_unibagflexo_act`
- `st_unibagseri_act`
- `st_unibagsellador_act`

Uso en el proyecto:

- sincronización de bodegas
- selección de destino en recepción
- movimiento lógico entre bodegas
- clasificación operativa por área

Relaciones:

- `company_shops_storehouses.id <- item_shops_storehouses.st_id`

### 8. `item_shops_storehouses`

Propósito:

- stock agregado por producto y bodega

Campos relevantes:

- `item_id`
- `shop_id`
- `st_id`
- `iss_inventory`
- `iss_inventory_reserved`

Uso en el proyecto:

- validar disponibilidad agregada por producto y bodega
- abastecimiento de materiales por OT

Observación:

- esta tabla no sirve para trazabilidad por unidad física
- solo representa stock agregado
- no reemplaza las bobinas trazables locales

Relaciones:

- `item_shops_storehouses.item_id -> item.id`
- `item_shops_storehouses.st_id -> company_shops_storehouses.id`

### 9. `supplier_contenedor`

Propósito:

- cabecera de contenedores de importación

Campos relevantes:

- `id`
- `sord_contenedor`
- `sord_buque`
- `sord_forward`
- `sord_billoflanding`
- `sord_ocs`
- `sord_eta_puerto`
- `sord_eta_puertounibag`

Uso en el proyecto:

- recepción de importación
- fecha de arribo
- número/serial de contenedor
- datos logísticos de llegada

Relaciones:

- `supplier_contenedor.id <- supplier_contenedor_items.sord_id`

### 10. `supplier_contenedor_items`

Propósito:

- líneas contenidas dentro del contenedor

Campos relevantes:

- `id`
- `sord_id`
- `sord_pos_id`
- `sord_amount`
- `sord_kgs_amount`

Uso en el proyecto:

- recepcionar importación por línea de contenedor
- enlazar el contenedor con la línea de compra original

Relaciones:

- `supplier_contenedor_items.sord_id -> supplier_contenedor.id`
- `supplier_contenedor_items.sord_pos_id -> supplier_order_items.id`

### 11. `user`

Propósito:

- usuarios del ERP

Campos relevantes:

- `id`
- `user_login`
- `user_firstname`
- `user_lastname`
- `user_status`
- `user_appmode_0`
- `user_appmode_1`
- `user_appmode_3`

Uso esperado:

- autenticación ERP
- permisos por modo o módulo
- identidad del usuario visible

### 12. `workers`

Propósito:

- operarios de planta

Campos relevantes:

- `id`
- `wrk_firstname`
- `wrk_lastname`
- `wrk_status`
- `wrk_uid`

Uso esperado:

- operador real de producción
- enlace operativo con usuario ERP

Relaciones:

- `workers.wrk_uid -> user.id` como relación funcional probable

### 13. Tablas ERP De Producción

Tablas detectadas:

- `prod_header`
- `prod_agenda`
- `prod_worker_init`
- `prod_worker_ot`
- `prod_worker_ot_events`

Uso esperado:

- producción histórica en ERP
- agenda de máquina o proceso
- apertura de trabajo
- eventos de operario por OT

Observación:

- hoy no están integradas de forma principal en el runtime del módulo actual
- sí existen scripts de inspección y documentación interna
- requieren una capa de adaptación si luego se quiere usar la OT ERP en vez de la OT simplificada local

### 14. `balanca_kgs`

Propósito:

- lectura de balanza

Campo detectado:

- `kg`

Uso esperado:

- recepción
- control de peso
- merma
- procesos automáticos de lectura

## Relaciones Funcionales Principales

### Flujo De Compra Nacional

```text
supplier
   |
   v
supplier_order
   |
   v
supplier_order_items
   |
   v
item
```

Detalle:

- un proveedor tiene múltiples OCs
- una OC tiene múltiples líneas
- cada línea apunta a un producto `item`

### Flujo De Importación

```text
supplier_contenedor
   |
   v
supplier_contenedor_items
   |
   v
supplier_order_items
   |
   v
supplier_order
   |
   v
supplier
```

Detalle:

- un contenedor tiene múltiples líneas
- cada línea del contenedor apunta a una línea real de compra
- desde ahí se puede reconstruir proveedor, producto y OC

### Flujo De Bodega ERP

```text
item
   |
   v
item_shops_storehouses
   |
   v
company_shops_storehouses
```

Detalle:

- un producto puede existir en varias bodegas
- cada registro refleja stock agregado por producto-bodega

## Joins Reales Usados En El Proyecto

### Join De Orden De Compra

```sql
supplier_order po
JOIN supplier s ON s.id = po.sord_supplier_id
LEFT JOIN country c ON c.id = s.supp_countryid
JOIN supplier_order_items soi ON soi.sord_id = po.id
```

Uso:

- listar OCs nacionales
- mostrar proveedor, país y líneas

### Join De Contenedor

```sql
supplier_contenedor_items sci
JOIN supplier_order_items soi ON soi.id = sci.sord_pos_id
JOIN supplier_order so ON so.id = soi.sord_id
JOIN supplier s ON s.id = so.sord_supplier_id
LEFT JOIN country c ON c.id = s.supp_countryid
```

Uso:

- recepción por contenedor
- enlazar línea importada con compra original

### Join De Detalle De Contenedor

```sql
supplier_contenedor sc
JOIN supplier_contenedor_items sci ON sci.sord_id = sc.id
JOIN supplier_order_items soi ON soi.id = sci.sord_pos_id
JOIN supplier_order so ON so.id = soi.sord_id
JOIN supplier s ON s.id = so.sord_supplier_id
LEFT JOIN country c ON c.id = s.supp_countryid
```

Uso:

- abrir el detalle completo del contenedor
- mostrar datos logísticos y comerciales

## Cómo Entra El ERP A La Base De Trazabilidad

La trazabilidad local crea registros operativos propios pero guarda referencias al ERP.

Campos ERP que hoy se copian a las bobinas locales:

- `purchase_order_id`
- `purchase_order_line_id`
- `import_container_id`
- `import_container_item_id`
- `supplier_id`

Eso permite:

- saber desde qué OC vino una bobina
- saber desde qué línea ERP vino
- saber desde qué contenedor llegó
- enlazar proveedor y documento original

## Flujo De Datos Completo

### 1. Recepción Nacional

```text
supplier_order
 -> supplier_order_items
 -> item
 -> supplier
 -> country
 -> selección de bodega
 -> captura de peso
 -> creación de bobina local
 -> movimiento local
 -> evento local
 -> etiqueta
```

### 2. Recepción De Importación

```text
supplier_contenedor
 -> supplier_contenedor_items
 -> supplier_order_items
 -> supplier_order
 -> supplier
 -> item
 -> bodega
 -> peso
 -> creación de bobina local
 -> evento local
 -> etiqueta
```

### 3. Producción

```text
bobina local
 -> asignación a OT local
 -> solicitud de materiales
 -> consumo
 -> merma
 -> bobina hija de salida
 -> corte
 -> cajas
 -> pallets
```

### 4. Inventario

```text
ERP:
 item_shops_storehouses = stock agregado ERP

TRZ:
 rolls + boxes + pallets = trazabilidad operativa fina
```

## Punto Crítico: Escritura En ERP

Aunque la recomendación arquitectónica es usar `unibagqa` como fuente ERP y evitar escritura, el código actual sí modifica ERP en recepción.

Actualizaciones detectadas:

- `supplier_order_items.item_amount_shipped`
- `supplier_order.sord_order_shipped`

Implicación:

- el sistema no está 100 por ciento en modo solo lectura sobre ERP
- si se quiere una integración más segura, esto debe revisarse antes de pasar a producción

## Diferencia Entre ERP Y Trazabilidad

### Lo Que Debe Seguir En ERP

- proveedores
- órdenes de compra
- líneas de compra
- productos
- bodegas
- stock agregado
- usuarios
- operarios
- estructuras ERP de producción

### Lo Que Conviene Mantener En La Base De Trazabilidad

- bobinas únicas
- movimientos por bobina
- bitácora de eventos
- solicitud y entrega de materiales por OT
- pesajes de tintas
- merma por evento
- bobina hija de proceso
- cajas
- pallets
- etiquetas

## Recomendación Técnica

La arquitectura más segura sigue siendo:

### Base 1: `unibagqa`

- rol: ERP fuente
- uso ideal: lectura

### Base 2: `unibag_trazabilidad`

- rol: trazabilidad operativa completa
- uso: lectura y escritura

Ventajas:

- no se rompe ERP
- se audita mejor
- permite crecer la trazabilidad sin forzar el modelo ERP

## Riesgos Si Se Usa `unibagqa` Directamente Para Todo

- no existe trazabilidad física por bobina unitaria
- stock ERP es agregado, no por unidad trazable
- pallets y cajas no están modelados igual que en el proyecto
- eventos operativos finos no están completos para este flujo
- escribir en ERP puede afectar procesos actuales del negocio

## Tablas Más Importantes Para Próxima Integración

Si el siguiente paso es enchufar más fuerte con ERP, este es el orden recomendado:

1. `item`
2. `supplier`
3. `supplier_order`
4. `supplier_order_items`
5. `supplier_contenedor`
6. `supplier_contenedor_items`
7. `company_shops_storehouses`
8. `item_shops_storehouses`
9. `user`
10. `workers`
11. `prod_header`
12. `prod_agenda`
13. `prod_worker_init`
14. `prod_worker_ot`
15. `prod_worker_ot_events`
16. `balanca_kgs`

## Der Textual Simplificado

```text
country (1) --------- (N) supplier

supplier (1) -------- (N) supplier_order

supplier_order (1) --- (N) supplier_order_items

item (1) ------------ (N) supplier_order_items

supplier_order_items (1) --- (N) supplier_order_items_specs

supplier_contenedor (1) --- (N) supplier_contenedor_items

supplier_order_items (1) --- (N) supplier_contenedor_items

item (1) ------------ (N) item_shops_storehouses

company_shops_storehouses (1) --- (N) item_shops_storehouses

user (1) ------------ (N/1) workers

prod_worker_ot (1) --- (N) prod_worker_ot_events
```

## Archivos Del Proyecto Que Sirven De Fuente

- `src/Db.php`
- `src/ReceptionService.php`
- `public/index.php`
- `INFORME_MAPA_DATOS_UNIBAGQA_TRAZABILIDAD.md`
- `.tmp_unibagqa_describe.php`
- `.tmp_unibagqa_samples.php`
- `.tmp_unibagqa_otmap.php`

## Siguiente Paso Recomendado

Si necesitas una integración total y no solo documental, el siguiente entregable ideal es:

- un diccionario de campos por tabla
- un diccionario de `spec_id`
- un mapa OT ERP `prod_*` hacia OT de trazabilidad
- una tabla puente de sincronización ERP -> TRZ
- una definición formal de qué sí puede escribirse en ERP y qué no
