# Propuesta de Dashboard y Trazabilidad

## 1. Contexto del proyecto

Viendo el proyecto actual, la aplicacion ya tiene una base muy buena para construir un dashboard real y no solo una pantalla de indicadores sueltos.

Hoy ya existen modulos y flujos para:

- recepcion de bobinas y stock por bodega
- solicitudes de materiales por OT
- uso de materiales en produccion
- flujo de OT por Flexografia, Selladora y Embalaje
- eventos operativos de produccion y aprobaciones
- trazabilidad de bobinas, cajas y pallets
- consulta ejecutiva de OT y trazabilidad desde ERP
- turnos y asignacion de maquina
- historial de clises

Ademas, el backend ya calcula algunos resumenes utiles en `src/ReceptionService.php`, como:

- estado de OTs
- bobinas en stock / proceso / listas para corte
- cajas y pallets generados
- recepciones pendientes
- alertas operativas
- trazabilidad reciente

Eso significa que el dashboard se puede construir sobre informacion que ya existe, sin inventar logica desde cero.

## 2. Objetivo del dashboard

El dashboard deberia servir para tres cosas:

1. Ver el estado actual de la operacion.
2. Detectar cuellos de botella y pendientes.
3. Entrar rapido al detalle trazable de una OT, bobina, caja o pallet.

No lo plantearia como un unico dashboard para todos, sino como una vista principal con bloques por rol.

## 3. Dashboard principal propuesto

## 3.1 Resumen ejecutivo

Bloques superiores tipo KPI:

- OTs abiertas
- OTs activas
- OTs en corte
- OTs cerradas
- Bobinas en proceso
- Bobinas listas para corte
- Bobinas bloqueadas
- Cajas generadas hoy
- Pallets generados hoy
- Recepciones pendientes

Esto ya conversa bien con la estructura actual del proyecto.

## 3.2 Alertas operativas

Un bloque prioritario con tarjetas o lista:

- OTs detenidas sin avanzar
- OTs en Flexo pendientes de validacion de cierre
- OTs listas para Selladora
- OTs listas para Embalaje
- bobinas impresas pendientes de corte
- solicitudes de materiales pendientes de entrega
- bobinas bloqueadas o con inconsistencias
- turnos abiertos sin cierre

Este bloque debe ser accionable, es decir, cada alerta debe llevar directo a la pantalla correcta.

## 3.3 Seguimiento por etapa

Un bloque visual por etapa de produccion:

- Flexografia
- Selladora
- Embalaje
- Cerradas

Cada columna deberia mostrar:

- cantidad de OTs
- OT mas antigua en espera
- OT mas reciente iniciada
- tiempo promedio en etapa

Idealmente con acceso directo a:

- `Ver OT`
- `Iniciar alistamiento`
- `Ir a Selladora`
- `Ir a Embalaje`
- `Cerrar OT`

## 3.4 Produccion en vivo

Un bloque operativo para planta:

- maquina activa
- operador o turno activo
- OT en curso
- bobina actual en maquina
- kilos en proceso
- ultima entrada / salida de material
- ultima merma registrada

Esto serviria mucho para jefatura de planta y supervision.

## 3.5 Materiales y abastecimiento

Un bloque especifico para lo que ya estan usando fuerte:

- solicitudes de bobinas pendientes
- solicitudes de tintas pendientes
- solicitudes de otros insumos pendientes
- bobinas activas por OT
- bobinas ya egresadas
- OT con mas consumo de material

Aqui conviene incluir filtros rapidos:

- por tipo: Bobinas / Tintas / Otros
- por estado: Pendiente / Parcial / Entregado / En uso / Cerrado

## 3.6 Calidad y merma

Un bloque con enfoque de control:

- merma total del dia
- merma por maquina
- merma por OT
- merma por etapa
- top causas de merma
- OTs con desviacion alta

Esto es muy valioso porque el proyecto ya registra mermas en varias pantallas del flujo.

## 3.7 Trazabilidad reciente

Un bloque tipo timeline:

- ultima bobina recibida
- ultima bobina ingresada a OT
- ultima bobina salida de maquina
- ultima caja generada
- ultimo pallet generado
- ultimo cierre de OT
- ultimo movimiento de clise

Sirve mucho para auditoria operativa y para soporte cuando algo "se pego".

## 4. Dashboards recomendados por rol

## 4.1 Dashboard Produccion

Pensado para supervisores y lideres de turno.

Debe mostrar:

- OTs en curso por maquina
- turnos activos
- etapa real de cada OT
- bobina activa por maquina
- materiales pendientes de ingreso
- materiales pendientes de salida
- mermas del turno
- accesos rapidos a Flexo, Selladora y Embalaje

## 4.2 Dashboard ERP / Gerencia

Pensado para consulta y seguimiento.

Debe mostrar:

- cumplimiento de plan
- OTs terminadas vs planificadas
- avance por etapa
- backlog de corte / sellado / embalaje
- recepciones pendientes
- consumo y merma
- trazabilidad resumida por OT

Esta vista debe ser solo lectura, alineada con lo que hoy ya existe en ERP.

## 4.3 Dashboard Bodega / Recepcion

Pensado para materiales y entradas.

Debe mostrar:

- bobinas disponibles por bodega
- bobinas reservadas para OTs
- solicitudes pendientes por prioridad
- ingresos del dia
- devoluciones del dia
- bobinas con diferencias de peso o estado

## 5. Trazabilidad que deberiamos construir y consolidar

## 5.1 Trazabilidad madre: desde OT hasta pallet

La trazabilidad central deberia quedar asi:

`OT -> solicitud de material -> bobina ingresada -> proceso/movimientos -> bobina salida -> cajas -> pallets`

Cada nodo debe poder abrir el siguiente y el anterior.

## 5.2 Trazabilidad por bobina

Cada bobina deberia tener:

- codigo unico
- bodega origen
- SKU / descripcion
- ancho, gramaje, largo, peso
- OT origen o OT destino
- maquina donde se uso
- fecha/hora de entrada a OT
- fecha/hora de salida
- operador responsable
- merma asociada
- estado actual
- cadena padre/hija si hubo transformacion

Esta es una de las trazabilidades mas importantes del sistema.

## 5.3 Trazabilidad por OT

Cada OT deberia mostrar una linea de tiempo completa:

- activacion
- inicio de alistamiento Flexo
- aprobacion de setup
- inicio de produccion
- fin de produccion
- validacion de cierre
- paso a Selladora
- paso a Embalaje
- cierre final

Ademas:

- materiales solicitados
- materiales entregados
- bobinas efectivamente usadas
- mermas por etapa
- cajas y pallets generados
- clises usados
- operadores y turnos que participaron

## 5.4 Trazabilidad de insumos no bobina

Tambien deberiamos dejar bien visible:

- tintas consumidas por OT
- otros insumos usados
- fecha/hora de entrega
- usuario que entrega
- usuario que consume
- devoluciones o anulaciones

## 5.5 Trazabilidad de clises

El proyecto ya tiene base para esto, y vale la pena consolidarlo con:

- clise asignado a OT
- ubicacion actual
- historial de movimientos
- maquina donde fue usado
- fecha de entrada y salida
- bloqueo para reutilizarlo si aun esta en uso

## 5.6 Trazabilidad de cajas y pallets

La trazabilidad final deberia permitir:

- abrir una caja y ver de que OT viene
- abrir un pallet y ver que cajas contiene
- abrir una caja o pallet y volver hacia la bobina de origen

Eso da una trazabilidad de punta a punta muy potente para auditoria, reclamos y control interno.

## 6. Indicadores clave recomendados

## 6.1 Produccion

- OTs iniciadas hoy
- OTs cerradas hoy
- tiempo promedio por etapa
- tiempo detenido por OT
- cumplimiento de plan diario

## 6.2 Materiales

- bobinas consumidas por dia
- kilos ingresados a maquina
- kilos egresados de maquina
- saldo por bobina
- solicitudes pendientes por tipo

## 6.3 Calidad / merma

- merma total por dia
- merma por OT
- merma por maquina
- merma por operador
- top causa de merma

## 6.4 Trazabilidad y control

- eventos registrados por dia
- OTs con trazabilidad incompleta
- bobinas sin OT asociada
- cajas sin pallet
- pallets sin cierre de OT

## 7. Propuesta de implementacion por fases

## Fase 1: Dashboard operativo basico

Construir primero:

- KPIs superiores
- alertas operativas
- OTs por etapa
- trazabilidad reciente
- accesos rapidos

Valor: alto y rapido de implementar.

## Fase 2: Dashboard de materiales y merma

Agregar:

- solicitudes pendientes
- consumo por OT
- bobinas activas por maquina
- merma por etapa
- top desvíos

Valor: muy alto para operacion diaria.

## Fase 3: Trazabilidad extendida

Consolidar:

- cadena completa bobina -> caja -> pallet
- historial navegable por OT
- timeline operativa
- trazabilidad de clises
- auditoria por operador y turno

Valor: muy alto para control y analisis historico.

## 8. Mi recomendacion concreta para este proyecto

Si lo hacemos sobre lo que ya existe, yo partiria por este dashboard:

1. Resumen KPI de OTs, bobinas, cajas y pallets.
2. Alertas operativas accionables.
3. Tablero por etapa: Flexo, Selladora, Embalaje.
4. Bloque de solicitudes y uso de materiales.
5. Bloque de mermas.
6. Timeline de trazabilidad reciente.

Y en paralelo dejaria definida la trazabilidad formal de:

- OT
- bobina
- clise
- caja
- pallet
- solicitud de material

## 9. Riesgos y cuidados

Para que el dashboard sea confiable, hay que cuidar estos puntos:

- que la etapa real de la OT siempre se calcule por eventos y no solo por `status`
- que las salidas de bobina cierren realmente el movimiento activo
- que una bobina cerrada no siga apareciendo como activa
- que las solicitudes de materiales mantengan relacion correcta con `request_id` y `roll_id`
- que los eventos de aprobacion y cierre no mezclen ciclos viejos con nuevos

Esto es clave, porque el dashboard va a depender de esa consistencia.

## 10. Entregable sugerido

Como siguiente paso, se puede hacer uno de estos dos entregables:

- un `dashboard funcional minimo` dentro del proyecto actual
- una `especificacion visual y funcional` del dashboard antes de implementarlo

Mi recomendacion seria:

1. definir las tarjetas KPI y alertas
2. definir las tablas por etapa
3. definir el bloque de trazabilidad reciente
4. despues construir la vista

