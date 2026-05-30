# PROPUESTA TÉCNICA INTEGRAL
# Sistema Integrado de Trazabilidad Operativa
# Proyecto: Transformación Digital Unibag Chile
# Fecha: 2026-05-07

Responsables Técnicos: Ingenieros en Informática (Yusmery Iglecia y Alejandro Muñoz)

Principio operativo: “Si no se piquea y no se pesa, no existe”.

---

## 1. Resumen Ejecutivo
Se desarrollará un sistema modular de trazabilidad y control operativo, basado en eventos de escaneo (RF/QR/Barras) y pesaje obligatorio, que permita:

- Trazabilidad completa por OT/lote, con trazabilidad inversa desde producto terminado hacia insumos.
- Control segmentado de bodegas y movimientos con validaciones.
- Logística híbrida y maquila con salida/retorno y control de mermas/costos por OT.
- Gestión de activos (clisés) con ubicación y trazabilidad histórica.
- Interfaz móvil de picking con validación en tiempo real.

La integración con ERP/API/BD corporativa queda preparada mediante una capa de adaptadores para activarse en una etapa posterior.

---

## 2. Alcance Por Módulos

### Módulo 1 – Trazabilidad Doble Hebra
- Consumo múltiple de Confirmaciones de Compra (CC) por líneas simultáneas.
- Consolidación a SKU final por OT/lote.
- Trazabilidad inversa completa (bulto → OT/lote → insumos/CC/máquina/operador).

### Módulo 2 – Gestión Segmentada de Bodegas
- Control automático de movimientos entre bodegas:
  - 100/200 (MP)
  - 500/501 (Proceso)
  - 700 (Canal Tradicional)
  - 1000 (Retail)
  - 400 (Logística Híbrida)
  - 2000 (Talleres Externos)

### Módulo 3 – Logística Híbrida y Maquila
- Gestión de salida y retorno a talleres externos.
- Control de mermas y costos asociados por OT.

### Módulo 4 – Gestión de Activos (Clisés)
- Inventario digital de matrices con ubicación física.
- Picking por OT.
- Trazabilidad histórica de uso.

### Módulo 5 – Interfaz Móvil de Picking
- Escaneo QR/Barras (handheld/tablet).
- Validación de mezcla correcta en tiempo real contra OT activa.

---

## 3. Reglas Críticas (Invariantes)
- Ningún paso “existe” sin evento digital (escaneo) y pesaje cuando aplique.
- Consumo de químicos por OT: (Peso Inicial - Peso Retorno) = Consumo Neto.
- Corte/Sellado: si bobina o manillas no corresponden a la OT activa, se registra bloqueo y se impide continuar.
- Empaque/Despacho: solo se permite ingreso a bodegas finales y despacho con trazabilidad completa (tela + químicos + manillas + empaque).

---

## 4. Estrategia De Desarrollo (pasos pequeños y programables)

### 4.1. Convención de pasos
Cada paso pequeño tiene:

- **ID**: `P#.#`
- **Resultado**: qué queda funcionando
- **Entradas/Salidas**: datos capturados y datos generados
- **Criterio de aceptación**: cómo se demuestra en demo

Los pasos están ordenados para que cada semana haya avances demostrables, sin depender de integración ERP real.

---

## 5. Backlog Implementable (Micro-Pasos)

### Fase 0 – Base del sistema (Core)

**P0.1 – Catálogos mínimos**
- Resultado: catálogos iniciales creados (bodegas, máquinas, módulos, SKUs, usuarios/roles).
- Criterio de aceptación: listado/alta/baja/edición desde una pantalla admin.

**P0.2 – Auditoría y eventos de trazabilidad**
- Resultado: bitácora de eventos (escaneo, pesaje, movimiento, validación, bloqueo).
- Criterio de aceptación: al registrar una acción, aparece un evento con usuario/fecha/entidad.

**P0.3 – Motor de estados por entidad**
- Resultado: estados básicos para bobina, bulto y OT (definidos y persistidos).
- Criterio de aceptación: cambios de estado quedan registrados y consultables.

**P0.4 – Capa de integración (stub)**
- Resultado: interfaces/adaptadores definidos para ERP/API/BD (sin conexión real).
- Criterio de aceptación: existe un “proveedor simulado” que devuelve maestros de prueba.

---

### Fase 1 – Recepción y bodegaje MP (B100/200)

**P1.1 – Alta de bobina con datos obligatorios**
- Entradas: SKU interno, peso real (Kg), micras, ancho, color, metros lineales.
- Salida: ID único de bobina + evento de recepción.
- Criterio de aceptación: no permite guardar si falta un campo obligatorio.

**P1.2 – Etiqueta/identificador de bobina**
- Resultado: generación de código (QR o barras) para bobina.
- Criterio de aceptación: se imprime/visualiza el código y permite re-escaneo.

**P1.3 – Movimiento a bodega 100/200**
- Resultado: stock por bodega actualizado por movimiento.
- Criterio de aceptación: la bobina aparece en stock de la bodega seleccionada.

---

### Fase 2 – Transformación inicial + químicos

**P2.1 – OT mínima + asociación con SKU objetivo**
- Resultado: crear/activar OT (estado: activa).
- Criterio de aceptación: se puede seleccionar OT activa en operaciones.

**P2.2 – Traspaso de bobina a proceso (500/501)**
- Resultado: mover bobina desde MP a Proceso.
- Criterio de aceptación: movimiento visible y trazable.

**P2.3 – Registro de pesajes de químicos (B900/910/920)**
- Entradas: químico, peso inicial, peso retorno, OT.
- Salida: consumo neto calculado y guardado.
- Criterio de aceptación: consumo neto coincide con la diferencia y queda en reporte por OT.

---

### Fase 3 – Corte y sellado (validación cruzada + bloqueo)

**P3.1 – Escaneo de bobina impresa contra OT activa**
- Resultado: validación de SKU/lote de bobina vs OT.
- Criterio de aceptación: si coincide, permite continuar; si no, dispara bloqueo.

**P3.2 – Escaneo de manillas/insumos de corte**
- Resultado: validación de insumos requeridos por OT.
- Criterio de aceptación: insumo incorrecto genera bloqueo y registro de causa.

**P3.3 – Bloqueo lógico y liberación por rol**
- Resultado: estado “bloqueado”, registro de evento y flujo de liberación (solo supervisor).
- Criterio de aceptación: operador no puede liberar; supervisor sí, dejando evidencia.

---

### Fase 4 – Empaque (B500) + etiqueta única de bulto

**P4.1 – Registro de unidades por caja y peso total**
- Entradas: OT, unidades por caja, peso total.
- Salida: bulto creado.
- Criterio de aceptación: bulto no se guarda sin unidades y peso.

**P4.2 – Etiqueta única de bulto con cadena de trazabilidad**
- Resultado: etiqueta con referencias a bobina/OT/consumos/insumos.
- Criterio de aceptación: desde el bulto se puede ver su “cadena” (resumen).

**P4.3 – Piqueo de materiales de empaque (cajas/cinta)**
- Resultado: consumos de empaque asociados a OT y cierre de bulto.
- Criterio de aceptación: bulto no pasa a “despachable” sin empaque escaneado.

---

### Fase 5 – Bodegas finales (B700/B1000) y despacho

**P5.1 – Validación de ingreso a bodega final**
- Regla: solo bultos con trazabilidad completa.
- Criterio de aceptación: bulto incompleto rechazado; bulto completo aceptado.

**P5.2 – Clasificación por canal (700 vs 1000)**
- Resultado: ingreso a bodega destino según canal/cliente.
- Criterio de aceptación: el stock final refleja la clasificación.

**P5.3 – Estado “despachable” y confirmación de despacho**
- Resultado: marcar bulto como despachado con evento.
- Criterio de aceptación: trazabilidad inversa disponible desde despacho.

---

### Fase 6 – Maquila (B2000) y logística híbrida (B400)

**P6.1 – Orden de salida a taller externo**
- Entradas: OT/lote/bultos, peso salida.
- Criterio de aceptación: bultos quedan “en maquila” y salen de stock interno.

**P6.2 – Retorno parcial/total**
- Entradas: peso retorno, cantidades.
- Criterio de aceptación: conciliación muestra diferencias.

**P6.3 – Cálculo de merma por diferencia**
- Resultado: merma calculada y reportable por OT/taller.
- Criterio de aceptación: reporte de merma coincide con pesos/retornos.

---

### Fase 7 – Gestión de activos (Clisés)

**P7.1 – Alta e inventario con ubicación física**
- Criterio de aceptación: búsqueda por código/ubicación/estado.

**P7.2 – Picking por OT (retiro y devolución)**
- Criterio de aceptación: no permite asignar clisé “en uso” a otra OT.

**P7.3 – Historial de uso por OT**
- Criterio de aceptación: vista de historial por activo y por OT.

---

### Fase 8 – Interfaz móvil de picking

**P8.1 – Flujo móvil: selección de proceso + escaneo**
- Criterio de aceptación: el móvil registra eventos igual que web.

**P8.2 – Validación en tiempo real contra OT activa**
- Criterio de aceptación: mismatch genera bloqueo y alerta.

---

### Fase 9 – Dashboards y reportes

**P9.1 – Monitor de Stock Maestro**
**P9.2 – Monitor de Insumos de Empaque**
**P9.3 – Monitor de Bloqueos**
**P9.4 – Consumo/Mermas/Activos**

Cada reporte se alimenta del mismo origen: eventos + movimientos + consumos.

---

## 6. Cronograma (con demos semanales)

### Semana 1
- Entrega: P0.1–P0.4
- Demo: catálogos + bitácora + estados + integración stub.

### Semana 2
- Entrega: P1.1–P1.3
- Demo: recepción de bobina + etiqueta + stock por bodega.

---

## 8. Stack de implementación
- Backend: PHP 8.4
- Base de datos: MySQL

### Semana 3
- Entrega: P2.1–P2.3
- Demo: OT activa + traspaso a proceso + químicos con consumo neto.

### Semana 4
- Entrega: P3.1–P3.3
- Demo: validación cruzada + bloqueo + liberación por rol.

### Semana 5
- Entrega: P4.1–P4.3
- Demo: bulto + etiqueta única + empaque obligatorio.

### Semana 6
- Entrega: P5.1–P5.3
- Demo: ingreso a 700/1000 con trazabilidad completa + despacho.

### Semana 7
- Entrega: P6.1–P6.3
- Demo: maquila con salida/retorno + merma.

### Semana 8
- Entrega: P7.1–P7.3 + P8.1–P8.2
- Demo: clisés + picking móvil validado.

### Semana 9 (cierre)
- Entrega: P9.1–P9.4 + endurecimiento y pruebas.
- Demo: dashboards + casos de prueba del anexo + checklist de reglas críticas.

---

## 7. Criterios De “Avances Demostrables”
- Cada semana debe existir al menos un flujo completo operable (entrada → validación → registro → consulta).
- Todo dato capturado debe generar evento trazable.
- Toda regla crítica debe poder demostrarse con un caso “pasa” y un caso “falla”.
