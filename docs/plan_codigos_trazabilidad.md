# Plan: ordenar los códigos de trazabilidad (9 nov 2026)

## Objetivo
Que cada código de trazabilidad (bobina, caja, pallet o producto) diga en qué bodega está de verdad, para poder descontarlo y usarlo. Que el número de códigos de cada artículo en cada bodega cuadre con el stock del ERP. Y que esto no vuelva a pasar.

---

## 1. Qué está mal hoy (revisado en la base real, solo lectura)

| # | Problema | Cuántos | Causa |
|---|---|---|---|
| A | La pantalla de la **bodega 100** mostraba los códigos de la **400**. | ~2.900 códigos (cajas y productos) | El sistema numera las bodegas de dos formas: el número del ERP y el número interno. El 1 interno es la 100, y el 1 del ERP es la 400. La pantalla mezclaba las dos. **Los datos de esas cajas están bien**; solo se mostraban mal. |
| B | Bobinas de la **100** que aparecen en la **3000**. | 595 bobinas TEL | El 3 nov, entre 12:27 y 12:32, se hizo un **traspaso masivo con «solo trazabilidad»** desde «Traspasos» (usuario Javier Perozo). Ninguna se usó después: no tienen OT ni entrega a producción. El ERP las sigue teniendo en la 100. |
| C | Bobinas impresas BOL (`ROLL-SE-…`) que estaban en la 500 y pasaron a la 3000 en ese mismo traspaso. | 154 | La misma de B. No tienen stock en el ERP, así que no se sabe dónde están. |
| D | Bobinas que quedaron en la **500** al recibirlas (7 al 9 oct). | 36 activas (BOL) | La recepción de esos días guardó el número del ERP (5 = bodega 100) como si fuera el interno (5 = bodega 500). |
| E | Registros de códigos guardados con el número interno en vez del número del ERP. | 179 bobinas (3000) y 164 cajas (1000) | Tres partes del programa graban la bodega de forma distinta. Hoy se ven bien, pero son la misma trampa que causó A. |
| F | Artículos donde la cantidad de códigos no cuadra con el ERP. | Ej.: TEL0180 (52 códigos en la 100, 1 en el ERP), TEL0122 (22 / 8), TEL0194 (39 en la 200 y 0 en el ERP de la 200; 23 en el ERP de la 100), TEL0088 (412 / 388) | Mezcla de B, D y movimientos físicos que no se registraron. Solo se resuelve contando. |

---

## 2. Cómo lo vamos a hacer

Hay una regla para todos los pasos. Cada corrección es un script que primero **solo muestra** lo que haría. Lo revisas, me das el OK y recién ahí se aplica con `--apply`, en una sola transacción. Cada bobina corregida deja un movimiento «Corrección» que se ve en Trazabilidad 360. **El ERP no se toca**: el stock del ERP es la referencia.

### Paso 0 · Respaldo (desde casa, 5 min)
- En la VPS, antes de cualquier `--apply`:
  `mysqldump unibag_trazabilidad rolls traceability_warehouse_entries boxes pallets movements > respaldo_trz_YYYYMMDD.sql`
- Mientras dure el arreglo, **nadie usa «Traspasos» con «solo trazabilidad»**. Hay que avisar a bodega (Javier y Gabriel).

### Paso 1 · Detener el daño (código, sin tocar datos) — desde casa
1. ✅ **Hecho:** el filtro de la pantalla de la bodega ya no mezcla bodegas (problema A).
2. ✅ **Hecho:** el escaneo en Solicitudes MP corrige la comilla `'` y la convierte en `-`.
3. **Por hacer:** que todo el programa guarde la bodega de los códigos con un solo número, el del ERP. Son tres lugares: cajas, etiquetas de recepción y registro de ingreso.
4. **Por hacer:** el traspaso masivo «solo trazabilidad» hacia la **3000** queda bloqueado. A la 3000 solo se entra por Solicitudes MP, porque ahí se registra para qué OT va. Hacia otras bodegas, el traspaso masivo pedirá un motivo y mostrará un resumen antes de confirmar («vas a mover 747 bobinas de la 500 a la 3000»).
5. Tú haces commit y lo subes a la VPS (`git pull`).

### Paso 2 · Informe de cuadre (solo lectura) — desde casa
Un script nuevo, `scripts/audit_trz_vs_erp.php`, y luego la misma pantalla en Bodega, entrega dos listas:
- **Por artículo y bodega:** códigos activos contra stock del ERP, y la diferencia.
- **Por código:** los que tienen una bodega distinta según dónde se mire. Por ejemplo, el registro del código dice 3000 y la bobina dice 100.

Sale también en Excel, para imprimir y llevar a bodega.

### Paso 3 · Correcciones automáticas (con tu OK en cada una) — desde casa
| Orden | Corrección | Script | Estado |
|---|---|---|---|
| 3a | Devolver las **595 bobinas TEL** de la 3000 a la 100 (problema B). | `scripts/fix_rolls_mass_transfer_20261103.php` | Listo; probado en simulación. |
| 3b | Unificar el número de bodega de los registros de códigos: 179 bobinas y 164 cajas (problema E). | Nuevo | Por hacer. |
| 3c | Que cada registro de código diga la misma bodega que su bobina, caja o pallet. | Nuevo (sale del informe del paso 2) | Por hacer. |
| 3d | Bobinas BOL `ROLL-SE` y las 36 que siguen en la 500 (problemas C y D). | Mismo método que 3a | **Primero me dices dónde están físicamente.** |

Después de cada corrección se vuelve a correr el informe del paso 2, para ver que la diferencia baje.

### Paso 4 · Conteo físico (en la planta) — lo que no cuadre después del paso 3
1. Con el Excel del paso 2, se cuentan solo los artículos con diferencia (problema F), bodega por bodega: 100, 200, 3000 y 500.
2. Para contar se **escanea cada bobina** con la pistola. Así se sabe exactamente qué código está en qué bodega, sin escribir a mano.
3. Con el resultado, se aplica un script, con tu OK:
   - **Código escaneado en otra bodega:** se mueve a donde se encontró.
   - **Código que no aparece físicamente:** queda «dado de baja (no encontrado en conteo)», con fecha y responsable. No se borra.
   - **Bobina física sin código o con etiqueta ilegible:** se imprime etiqueta nueva desde el ingreso trazable.
4. Si después de contar el ERP tampoco cuadra con lo físico, eso es un ajuste de inventario en el ERP. **Se decide aparte**; no entra en este plan.

### Paso 5 · Que no vuelva a pasar
- El informe de cuadre queda como pantalla en Bodega, con un aviso cuando un artículo tenga más códigos que stock en el ERP, o al revés.
- Una revisión diaria automática del cuadre (cron), que deja un resumen en el log.
- Las reglas del paso 1.4 quedan permanentes.

---

## 3. Orden y tiempos

| Cuándo | Qué | Quién |
|---|---|---|
| Desde casa (sesión 1) | Paso 0 (respaldo). Paso 1.3 y 1.4 (código). Commit y subida. | Tú el respaldo y el commit; yo el código. |
| Desde casa (sesión 1) | Paso 2: correr el informe. Paso 3a con tu OK. | Yo preparo y ejecuto solo con tu OK. |
| Desde casa (sesión 1) | Pasos 3b y 3c en simulación, revisión y OK. | Igual. |
| Siguiente día en planta | Avisar a bodega. Paso 3d (me dices dónde están las BOL). Paso 4 (conteo con pistola). | Bodega cuenta; yo aplico el resultado con tu OK. |
| Después | Paso 5. | Yo. |

## 4. Lo que necesito de ti
1. El OK para cada `--apply`, uno por uno.
2. Dónde están físicamente las 154 bobinas BOL `ROLL-SE-…` y las 36 de la 500.
3. Que avises a bodega que no usen el traspaso masivo «solo trazabilidad» hasta terminar.
4. Acceso a la base desde casa: el `.env` con la TRZ real, el mismo de hoy.

## 5. Riesgos
- **El `.env` local apunta a la TRZ real.** Cualquier prueba local escribe en producción. Para pruebas se usa una copia (`TRZ_DB_NAME` distinto).
- **Si producción sigue moviendo bobinas mientras corregimos, las cifras cambian.** Por eso cada script se vuelve a simular justo antes de aplicarlo.
- **Las 24 bobinas de más de TEL0088** (412 códigos contra 388 en el ERP) puede que ya se hayan usado sin registrarse. Solo el conteo lo confirma.
