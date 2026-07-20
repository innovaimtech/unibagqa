# Valor de compra de bobinas en `unibagqa`

## Objetivo

Dejar documentado dónde buscar el valor de compra de bobinas o materias primas en la base `unibagqa`, para usarlo más adelante en valorización de inventario por bodega.

## Resumen corto

En `unibagqa` el valor de compra no parece estar en una sola tabla. Hay tres fuentes principales, dependiendo de qué queramos medir:

1. **Compra original por orden de compra**
2. **Costo maestro por proveedor**
3. **Costo promedio para valorización de inventario**

## Tablas importantes

## 1. `supplier_order_items`

Esta tabla sirve para ver el **valor de compra registrado en la orden de compra**.

Campos importantes:

- `item_costprice_netto`
- `item_costprice_brutto`
- `item_costprice_netto_dsc`
- `item_costprice_taxes`
- `item_kgs`

Uso recomendado:

- cuando queramos saber **a cuánto se compró** un item en una OC específica
- cuando necesitemos trazabilidad de costo desde compra

Relacion principal:

- `supplier_order_items.sord_id -> supplier_order.id`

Tabla relacionada:

- `supplier_order`

Campos útiles en `supplier_order`:

- `sord_number`
- `sord_supplier_id`
- `sord_date`
- `sord_total_netto`
- `sord_total_brutto`

## 2. `item_suppliers` y `itemlist_suppliers`

Estas tablas guardan el **costo maestro o referencial por proveedor-item**.

Campos importantes:

- `item_costprice_netto`
- `item_costprice_brutto`
- `item_costprice_usd`
- `supplier_id`
- `item_id`

Uso recomendado:

- para consultar un costo base por proveedor
- para análisis comercial o comparación de proveedores

No es la mejor fuente para valorizar stock real, porque puede representar un costo de referencia y no necesariamente el costo vigente del inventario actual.

## 3. `tran_average_costprices` y `tran_data`

Estas son las tablas más importantes si queremos calcular **cuánto dinero hay en las bodegas**.

### `tran_average_costprices`

Campo importante:

- `item_costprice_avg_netto`

Uso:

- costo promedio por item y compañía

### `tran_data`

Campos importantes:

- `item_costprice_avg_netto`
- `tran_currstock`
- `tran_st_id`
- `item_id`
- `item_title`

Uso:

- stock actual
- costo promedio
- valorización por bodega

## Recomendación principal

Si el objetivo es mostrar:

### A. Precio al que se compró

Usar:

- `supplier_order_items`

### B. Cuánto dinero hay en las bodegas

Usar:

- `tran_data`
- y complementar con `tran_average_costprices` si hace falta validar costo promedio

Esto es importante porque para valorizar inventario normalmente conviene usar **costo promedio del stock**, no el precio de una compra puntual.

## Estado encontrado en la copia local

En la revisión hecha sobre la base local `unibagqa`:

- las tablas de costo **sí existen**
- pero actualmente **no se encontraron valores útiles mayores a 0** en:
  - `supplier_order_items`
  - `item_suppliers`
  - `itemlist_suppliers`
  - `tran_average_costprices`
  - `stockchanges_items`

Eso significa que:

- la estructura está lista
- pero en esta copia local no hay costos cargados todavía para una valorización real

## Consulta base para compras

```sql
SELECT
    so.sord_number,
    soi.item_id,
    soi.item_amount,
    soi.item_kgs,
    soi.item_costprice_netto,
    soi.item_costprice_brutto,
    soi.item_costprice_netto_dsc
FROM supplier_order_items soi
JOIN supplier_order so ON so.id = soi.sord_id;
```

## Consulta base para valorización de stock

```sql
SELECT
    t.tran_st_id AS bodega_id,
    t.item_id,
    MAX(t.item_title) AS item,
    MAX(t.tran_currstock) AS stock_actual,
    MAX(t.item_costprice_avg_netto) AS costo_promedio,
    MAX(t.tran_currstock) * MAX(t.item_costprice_avg_netto) AS valor_stock
FROM tran_data t
GROUP BY t.tran_st_id, t.item_id;
```

## Próximo uso recomendado

Cuando retomemos este tema, lo ideal sería:

1. identificar qué tabla usa el ERP como stock vigente por bodega
2. validar si `tran_data` es la fuente final correcta para stock actual
3. cruzar bodega + item + costo promedio
4. construir el reporte:
   - bodega
   - item
   - stock
   - costo promedio
   - valor total

## Referencia dentro del proyecto

En el proyecto ya aparece el uso de estos campos de compra en:

- [scripts/seed_reception_demo.php](file:///c:/Users/Axiliarmu/Desktop/unibag%20proyecto/scripts/seed_reception_demo.php#L272-L275)

Especialmente:

- `item_costprice_brutto`
- `item_costprice_taxes_perc`
- `item_costprice_netto`
- `item_costprice_netto_dsc`
- `item_costprice_taxes`

