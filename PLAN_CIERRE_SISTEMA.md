# Plan De Cierre Del Sistema

Fecha: 2026-05-25

## 1. Objetivo
Dejar el sistema de trazabilidad Unibag operativo, validado y listo para uso controlado, cubriendo recepcion, stock, OT, impresion, corte, cajas, pallets y despliegue online.

## 2. Estado Actual

### Ya implementado
- Arquitectura dual:
  - ERP fuente en `unibagqa`
  - trazabilidad en `unibag_trazabilidad`
- Recepcion nacional por OC.
- Recepcion de importacion por contenedor.
- Recepcion por peso o por cantidad segun seleccion del operador.
- Filtro de proveedores con pendientes reales.
- Bodegas visibles ampliadas.
- Login separado por areas:
  - `ERP`
  - `Recepcion`
  - `Produccion`
- Flujo de OT con:
  - inicio
  - impresion
  - bobina de salida
  - corte dentro de la misma OT
  - cajas y pallets
- Cierre de OT solo al terminar corte.
- Compatibilidad con Vercel/Railway:
  - sesion
  - CSRF
  - despliegue
  - optimizacion de rendimiento principal
- Datos demo locales para:
  - recepcion nacional
  - importacion
  - OT pendiente
  - OT en produccion
  - OT en corte
  - OT fabricada completa

### Pendiente de validar en operacion
- Flujo real completo con operadores.
- Trazabilidad final de cajas y pallets en todos los casos.
- Etiquetas finales y lectura operativa.
- Casos borde:
  - corte parcial
  - reproceso
  - multiples bobinas de salida
  - anulaciones o correcciones

## 3. Cierre Por Etapas

### Etapa 1. Validacion funcional
Objetivo: confirmar que el flujo actual cumple la operacion esperada.

Tareas:
- Probar recepcion nacional completa.
- Probar recepcion parcial nacional.
- Probar importacion por contenedor.
- Probar OT en produccion.
- Probar paso de impresion a corte.
- Probar cierre final con cajas y pallets.
- Revisar mensajes, nombres de campos y botones con usuario final.

Criterio de aprobado:
- El usuario puede recorrer el flujo sin bloqueos inesperados.
- Los estados cambian correctamente.
- La informacion visible coincide con la operacion real.

### Etapa 2. Ajustes de operacion
Objetivo: corregir detalles que aparezcan en la validacion.

Tareas:
- Ajustar textos y nombres de etapas.
- Simplificar pantallas donde sobre informacion.
- Integrar completamente `Corte` dentro de `OT` si se decide quitar acceso separado.
- Mejorar campos de ayuda operativa:
  - nombre de bodega
  - destino
  - operador
  - resumen de estado

Criterio de aprobado:
- La interfaz se entiende sin explicacion tecnica adicional.
- El operador sabe que accion hacer en cada etapa.

### Etapa 3. Trazabilidad final
Objetivo: asegurar consistencia completa entre entidades.

Tareas:
- Verificar relacion:
  - OT -> bobina entrada
  - OT -> bobina salida
  - bobina salida -> corte
  - corte -> cajas
  - cajas -> pallets
- Verificar operador y fecha por evento.
- Verificar bodega origen/destino.
- Verificar etiquetas y vista de detalle.

Criterio de aprobado:
- Desde una caja o pallet se puede reconstruir la OT y la bobina origen.
- Desde una OT se pueden ver todos los resultados generados.

### Etapa 4. Reglas de negocio pendientes
Objetivo: cerrar decisiones que aun no estan totalmente definidas.

Decisiones pendientes:
- Si una OT puede tener multiples cortes parciales antes de cerrarse.
- Si una OT puede reabrirse despues de entrar a corte.
- Como manejar reprocesos.
- Como manejar anulacion de una recepcion erronea.
- Como manejar anulacion de cajas o pallets ya generados.
- Si se exigira supervisor para liberacion de errores criticos.

Entregable:
- Reglas escritas y aprobadas.

### Etapa 5. Salida online controlada
Objetivo: pasar lo validado a entorno online estable.

Tareas:
- Confirmar datos y variables online.
- Decidir si se cargaran datos demo o solo datos reales.
- Probar login por area en Vercel.
- Probar formularios `POST`.
- Probar rendimiento en rutas principales.
- Probar flujo real minimo en Railway/Vercel.

Criterio de aprobado:
- La version online soporta el flujo principal sin errores funcionales.

## 4. Prioridades Reales

### Alta
- Validacion funcional completa con usuarios.
- Ajustes operativos detectados.
- Trazabilidad final de cajas y pallets.
- Definicion de reglas de negocio faltantes.

### Media
- Limpieza final de interfaz y textos.
- Quitar accesos duplicados o flujos paralelos no necesarios.
- Documentacion corta de uso por area.

### Baja
- Mejoras cosmeticas.
- Automatizaciones no criticas.
- Casos avanzados no necesarios para arranque.

## 5. Checklist Final De Entrega

### Flujo funcional
- [ ] Recepcion nacional completa validada.
- [ ] Recepcion nacional parcial validada.
- [ ] Importacion por contenedor validada.
- [ ] OT en produccion validada.
- [ ] Paso a corte validado.
- [ ] Cierre final de OT validado.
- [ ] Cajas y pallets validados.

### Trazabilidad
- [ ] Bobina entrada ligada a OT.
- [ ] Bobina salida ligada a OT.
- [ ] Cajas ligadas a bobina/OT.
- [ ] Pallets ligados a cajas/OT.
- [ ] Operador y fecha visibles por evento.
- [ ] Bodega visible y coherente.

### Operacion
- [ ] Pantallas entendibles para operador.
- [ ] Textos revisados con usuario.
- [ ] Botones y estados sin ambiguedad.
- [ ] Errores criticos manejados.

### Online
- [ ] Variables de entorno correctas.
- [ ] Login y sesiones estables.
- [ ] CSRF estable.
- [ ] Rendimiento aceptable.
- [ ] Flujo minimo validado online.

## 6. Checklist Corto De Prueba Real

Objetivo: confirmar en operacion que la cadena final OT -> bobina entrada -> bobina salida -> corte -> cajas -> pallets se reconstruye completa y sin saltos.

### Prueba 1. Flujo base completo
- Crear o tomar una OT demo activa.
- Escanear una bobina de entrada y registrarla en la OT.
- Ingresar quimicos y arrancar produccion.
- Finalizar impresion generando una bobina salida.
- Procesar corte de esa bobina salida.
- Confirmar que se generen cajas y pallet.
- Validar desde la OT que aparezcan bobina entrada, bobina salida, cajas y pallet.

### Prueba 2. Trazabilidad inversa desde caja
- Abrir una caja generada.
- Confirmar que muestre enlace a OT.
- Confirmar que muestre enlace a bobina salida.
- Confirmar que muestre bobina entrada si existe parent_roll_id.
- Confirmar que permita llegar al pallet si fue asociado.

### Prueba 3. Trazabilidad inversa desde pallet
- Abrir un pallet generado.
- Confirmar que muestre enlace a OT.
- Confirmar que muestre enlace a bobina salida.
- Confirmar que muestre bobina entrada.
- Confirmar que liste las cajas contenidas.

### Prueba 4. Cambio de bobina durante OT
- Iniciar una OT con una bobina.
- Ejecutar cambio de bobina.
- Confirmar que la bobina usada queda liberada con su evento.
- Confirmar que se genera nueva bobina salida con codigo nuevo.
- Confirmar que la nueva bobina salida queda ligada a la bobina anterior y a la OT.

### Prueba 5. Multiples bobinas de salida
- Ejecutar una OT donde se generen dos o mas bobinas salida.
- Confirmar que la OT muestre todas las bobinas salida en la cadena final.
- Confirmar que cada bobina salida tenga sus propias cajas y pallets, sin mezclarse.

### Prueba 6. Corte parcial
- Cortar solo una de las bobinas salida de una OT.
- Confirmar que la OT siga pendiente mientras existan bobinas impresas sin cortar.
- Confirmar que solo cierre cuando no queden bobinas pendientes de corte.

### Prueba 7. Visibilidad operativa minima
- Confirmar que cada evento relevante muestre operador y fecha.
- Confirmar que cada caja y pallet muestre bodega o destino coherente.
- Confirmar que las etiquetas impresas correspondan a la entidad correcta.

### Criterio de cierre real
- [ ] Desde OT se reconstruye toda la cadena.
- [ ] Desde bobina se ve origen y descendencia.
- [ ] Desde caja se llega a OT y bobinas.
- [ ] Desde pallet se llega a OT, bobina y cajas.
- [ ] Multiples bobinas de salida quedan separadas correctamente.
- [ ] Corte parcial no cierra OT antes de tiempo.

## 7. Recomendacion Inmediata
Siguiente paso recomendado: ejecutar una validacion funcional guiada usando los datos demo locales ya cargados y registrar cualquier ajuste necesario antes de seguir moviendo logica o interfaz.

## 8. Decision De Arranque
El sistema puede seguir avanzando sin agregar modulos grandes nuevos por ahora. La prioridad ya no es construir mas alcance, sino validar, ajustar y cerrar correctamente el flujo que ya existe.
