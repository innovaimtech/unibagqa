# Informe De Produccion Y Operarios En `unibagqa`

## Objetivo

Este informe explica cómo funciona la parte de producción y operarios dentro de la base `unibagqa`, qué tablas participan, qué datos guarda cada una, cómo se conectan entre sí y qué flujo operativo representan.

El foco de este informe está en:

- operarios
- usuarios asociados
- apertura de turno o sesión de trabajo
- agenda de producción
- cabecera de producción
- OT del operario
- eventos de producción del operario

## Resumen Ejecutivo

La producción de operarios en `unibagqa` no está modelada como una sola tabla, sino como una cadena de tablas que representan distintas capas del proceso:

1. `user`
   - representa el usuario del sistema
2. `workers`
   - representa al operario de planta
3. `prod_worker_init`
   - representa el inicio de trabajo del operario en una máquina o equipo
4. `prod_agenda`
   - representa la programación o agenda de producción
5. `prod_header`
   - representa la cabecera de la orden o registro productivo
6. `prod_worker_ot`
   - representa la ejecución del operario sobre una OT o trabajo específico
7. `prod_worker_ot_events`
   - representa los eventos reales dentro de esa ejecución

En otras palabras:

```text
Usuario -> Operario -> Inicio en equipo -> Agenda -> Cabecera producción -> Trabajo del operario -> Eventos
```

## Vista General Del Flujo

```text
user
  |
  v
workers
  |
  v
prod_worker_init
  |
  v
prod_worker_ot
  |               \
  |                \ 
  v                 v
prod_worker_ot_events   prod_agenda -> prod_header
```

Flujo funcional probable:

1. un usuario existe en `user`
2. ese usuario puede estar vinculado a un operario en `workers`
3. el operario inicia trabajo en una máquina/equipo y eso queda en `prod_worker_init`
4. el trabajo del operario se enlaza a una agenda programada en `prod_agenda`
5. la agenda se enlaza a una cabecera productiva en `prod_header`
6. la ejecución del operario se registra en `prod_worker_ot`
7. cada acción concreta de esa ejecución queda en `prod_worker_ot_events`

## Tablas Principales

## 1. `user`

### Propósito

Guarda los usuarios del sistema ERP.

### Campos importantes

- `id`
- `user_login`
- `user_firstname`
- `user_lastname`
- `user_status`
- `user_appmode_0`
- `user_appmode_1`
- `user_appmode_2`
- `user_appmode_3`
- `user_appmode_4`

### Qué representa

- identidad de acceso al sistema
- permisos o modos de aplicación
- nombre visible del usuario

### Observación funcional

Los modos `user_appmode_*` parecen indicar permisos o acceso por áreas del sistema.

### Relación con operarios

- se observa una relación funcional con `workers.wrk_uid`
- en las muestras reales, `wrk_uid` coincide con `user.id`

Ejemplo observado:

- `workers.wrk_uid = 4101`
- `user.id = 4101`

## 2. `workers`

### Propósito

Guarda a los operarios de planta.

### Campos importantes

- `id`
- `wrk_firstname`
- `wrk_lastname`
- `wrk_status`
- `wrk_rut`
- `wrk_turno_turnoid`
- `wrk_turno_state`
- `wrk_cargoid`
- `wrk_costo_hh`
- `wrk_turno_startdate`
- `wrk_uid`

### Qué representa

- identidad del operario
- estado del operario
- datos laborales básicos
- posible turno asignado
- usuario ERP relacionado

### Campo clave

- `wrk_uid`

Este campo es el vínculo más claro hacia `user.id`.

### Interpretación

- `workers` es la entidad humana de planta
- `user` es la identidad de sistema
- una misma persona parece estar representada en ambas tablas

## 3. `prod_worker_init`

### Propósito

Representa el inicio de trabajo del operario en planta, normalmente sobre un equipo o máquina.

### Campos

- `id`
- `win_crtdat`
- `win_enddat`
- `win_wrkid`
- `win_status`
- `win_plantaid`
- `win_equipoid`
- `win_ass_id`
- `win_day`
- `win_month`
- `win_year`

### Qué representa

- cuándo un operario inicia una sesión de trabajo
- en qué planta trabaja
- en qué equipo o máquina entra
- cuándo termina esa sesión

### Relaciones

- `prod_worker_init.win_wrkid -> workers.id`
- `prod_worker_ot.wok_init_id -> prod_worker_init.id`

### Campos funcionales clave

- `win_wrkid`: operario
- `win_equipoid`: máquina/equipo
- `win_plantaid`: planta
- `win_crtdat`: fecha/hora de inicio
- `win_enddat`: fecha/hora de término
- `win_status`: estado de la sesión

### Lectura funcional

Esta tabla parece ser el equivalente a:

- iniciar turno
- iniciar sesión en máquina
- quedar disponible o activo sobre un equipo

## 4. `prod_header`

### Propósito

Es la cabecera principal del registro productivo.

### Campos

- `id`
- `prd_crtdat`
- `prd_crtusr`
- `prd_status`
- `prd_desc`
- `prd_plantaid`
- `prd_reqid`
- `prd_upddat`
- `prd_updusr`
- `prd_number`

### Qué representa

- un documento o cabecera de producción
- el identificador general del trabajo
- la planta donde se ejecuta
- un número formal de producción

### Campos funcionales clave

- `prd_number`: número legible del trabajo
- `prd_reqid`: identificador asociado al requerimiento o solicitud
- `prd_status`: estado de la producción
- `prd_plantaid`: planta

### Relación observada

- `prod_agenda.ag_prdid -> prod_header.id`

Eso sugiere que la agenda programada apunta a la cabecera productiva.

## 5. `prod_agenda`

### Propósito

Representa la agenda o programación de producción.

### Campos

- `id`
- `ag_date`
- `ag_date_stamp`
- `ag_equipo_id`
- `ag_equipotype_id`
- `ag_amount`
- `ag_prdid`
- `ag_reqid`
- `ag_plantaid`
- `ag_crtdat`
- `ag_crtusr`
- `ag_status`
- `ag_active`
- `ag_order`

### Qué representa

- el trabajo programado en una fecha determinada
- el equipo o máquina asignada
- el tipo de equipo
- la cantidad objetivo programada
- el enlace a la cabecera de producción

### Relaciones

- `prod_agenda.ag_prdid -> prod_header.id`
- `prod_worker_ot.wok_ag_id -> prod_agenda.id`

### Campos funcionales clave

- `ag_equipo_id`: máquina o equipo
- `ag_equipotype_id`: tipo de máquina
- `ag_amount`: cantidad programada
- `ag_prdid`: cabecera productiva
- `ag_reqid`: requerimiento
- `ag_date`: fecha del trabajo

### Lectura funcional

`prod_agenda` parece ser la planificación por máquina:

- qué se hace
- cuándo se hace
- en qué equipo
- cuánto se espera producir

## 6. `prod_worker_ot`

### Propósito

Representa la ejecución concreta del trabajo del operario sobre una OT o trabajo programado.

### Campos

- `id`
- `wok_ag_id`
- `wok_init_id`
- `wok_crtdat`
- `wok_enddat`
- `wok_status`

### Qué representa

- el cruce entre una sesión de operario y una agenda productiva

En otras palabras:

- qué operario ejecutó
- qué trabajo
- desde cuándo
- hasta cuándo
- con qué estado

### Relaciones

- `prod_worker_ot.wok_ag_id -> prod_agenda.id`
- `prod_worker_ot.wok_init_id -> prod_worker_init.id`
- `prod_worker_ot_events.evt_prod_worker_otid -> prod_worker_ot.id`

### Lectura funcional

Esta tabla es probablemente la tabla central del seguimiento operativo del operario.

Une:

- al operario que inició turno o máquina
- con la tarea programada
- y permite luego colgar eventos detallados

## 7. `prod_worker_ot_events`

### Propósito

Guarda el historial detallado de eventos del trabajo del operario.

### Campos

- `id`
- `evt_prod_worker_otid`
- `evt_amount`
- `evt_crtdat`
- `evt_enddat`
- `evt_status`
- `evt_type`
- `evt_comments`
- `evt_equipo_mantid`
- `evt_pause_id`
- `prod_bobina_kg`
- `prod_seri_color`
- `prod_seri_converted_amt`
- `evt_medida_fromid`
- `evt_medida_toid`
- `evt_ubim_id`
- `evt_amount_metros_maquina`
- `evt_amount_metros_lineales`
- `evt_metrotype`

### Qué representa

Es la bitácora fina del trabajo.

Cada fila representa algo que ocurrió dentro de la ejecución del operario:

- apertura
- producción
- pausa
- mantención
- color en serigrafía

### Relación

- `evt_prod_worker_otid -> prod_worker_ot.id`

### Tipos de evento observados

En los datos reales aparecen:

- `apertura`
- `prod`
- `pause`
- `mantencion`
- `prodsericolor`

### Campos funcionales clave

- `evt_type`: tipo de evento
- `evt_amount`: cantidad producida o asociada al evento
- `evt_comments`: comentarios
- `prod_bobina_kg`: peso de bobina si aplica
- `evt_amount_metros_maquina`: metros de máquina
- `evt_amount_metros_lineales`: metros lineales
- `evt_pause_id`: causa o categoría de pausa
- `evt_equipo_mantid`: referencia de mantención de equipo

## Relaciones Entre Tablas

## Relación 1. Usuario Y Operario

```text
user.id = workers.wrk_uid
```

Interpretación:

- un usuario del ERP se vincula a un operario de planta

## Relación 2. Operario E Inicio En Equipo

```text
workers.id = prod_worker_init.win_wrkid
```

Interpretación:

- un operario inicia una sesión en una máquina o equipo

## Relación 3. Inicio De Operario Y Trabajo Ejecutado

```text
prod_worker_init.id = prod_worker_ot.wok_init_id
```

Interpretación:

- el trabajo ejecutado por el operario cuelga de su sesión iniciada

## Relación 4. Agenda Y Trabajo Ejecutado

```text
prod_agenda.id = prod_worker_ot.wok_ag_id
```

Interpretación:

- el trabajo del operario está ligado a una programación concreta

## Relación 5. Cabecera Productiva Y Agenda

```text
prod_header.id = prod_agenda.ag_prdid
```

Interpretación:

- la agenda es una instancia programada de una cabecera de producción

## Relación 6. Trabajo Ejecutado Y Eventos

```text
prod_worker_ot.id = prod_worker_ot_events.evt_prod_worker_otid
```

Interpretación:

- todos los eventos cuelgan del trabajo concreto del operario

## Mapa Del Flujo Operativo

### Flujo resumido

```text
1. Existe un usuario ERP
2. Ese usuario tiene un operario asociado
3. El operario inicia trabajo en una máquina
4. Existe una agenda de producción para esa máquina
5. La agenda apunta a una cabecera de producción
6. Se crea el registro de trabajo del operario sobre esa agenda
7. Durante la ejecución se registran eventos
```

### Flujo más detallado

```text
user
 -> workers
 -> prod_worker_init
 -> prod_worker_ot
 -> prod_worker_ot_events

prod_header
 -> prod_agenda
 -> prod_worker_ot
```

## Qué Significa Cada Parte En Negocio

### `user`

- persona que entra al sistema

### `workers`

- persona física que trabaja en planta

### `prod_worker_init`

- inicio de trabajo de esa persona en una máquina

### `prod_header`

- cabecera del trabajo productivo

### `prod_agenda`

- planificación del trabajo en máquina, fecha y cantidad

### `prod_worker_ot`

- ejecución real del operario sobre ese trabajo

### `prod_worker_ot_events`

- historial detallado de lo que pasó mientras trabajaba

## Estados Observables

No hay diccionario formal en el proyecto, pero por muestras reales se puede inferir:

### `win_status` en `prod_worker_init`

- `1`: activo o abierto
- `2`: cerrado o terminado

### `wok_status` en `prod_worker_ot`

- `1`: abierto/en curso
- `2`: cerrado/finalizado

### `prd_status` en `prod_header`

- `2` aparece en producciones activas/finalizadas recientes
- requiere diccionario ERP para interpretación exacta

### `ag_status` en `prod_agenda`

- `1` aparece como agenda válida o vigente

### `ag_active` en `prod_agenda`

- `0` aparece en muestras recientes
- requiere validación funcional para saber si significa no activa, cerrada o ya procesada

## Tipos De Datos Importantes Que Lleva Producción

La base productiva de operarios lleva al menos estos grupos de datos:

### Datos de identidad

- nombre del operario
- apellido del operario
- usuario de acceso
- estado del usuario y del operario

### Datos de asignación

- máquina o equipo
- tipo de equipo
- planta
- fecha programada
- cantidad programada

### Datos de ejecución

- cuándo inició
- cuándo terminó
- qué operario la ejecutó
- qué agenda estaba ejecutando

### Datos de evento

- aperturas
- producción
- pausas
- mantenciones
- comentarios
- peso de bobina
- metros de máquina
- metros lineales
- color en procesos de serigrafía

## Ejemplo Real Reconstruido

Ejemplo observado en los datos:

- `prod_worker_ot.id = 1716`
- `wok_status = 2`
- `wok_init_id = 646`
- `wok_ag_id = 1911`

Eso conecta con:

- `prod_worker_init.id = 646`
- `win_wrkid = 19`
- `win_equipoid = 33`

El operario es:

- `workers.id = 19`
- `wrk_firstname = Juan Alberto`
- `wrk_lastname = Gutiérrez Cabeza`
- `wrk_uid = 4101`

Y el usuario asociado:

- `user.id = 4101`

La agenda asociada:

- `prod_agenda.id = 1911`
- `ag_equipo_id = 33`
- `ag_equipotype_id = 7`
- `ag_amount = 51742.00`
- `ag_prdid = 718`
- `ag_reqid = 5509`

La cabecera productiva:

- `prod_header.id = 718`
- `prd_reqid = 5509`
- `prd_number = 000671`

Evento asociado reciente:

- `prod_worker_ot_events.evt_prod_worker_otid = 1716`
- `evt_type = apertura`

Interpretación del ejemplo:

- el operario Juan Alberto inicia trabajo en el equipo 33
- ejecuta la agenda 1911
- esa agenda pertenece a la producción 718
- el trabajo queda registrado como `prod_worker_ot 1716`
- y sus acciones quedan registradas en `prod_worker_ot_events`

## Qué Falta Para Tener El Mapeo 100 Por Ciento Exacto

Para dejar esto totalmente cerrado faltarían diccionarios ERP de:

- estados de `prod_header`
- estados de `prod_agenda`
- estados de `prod_worker_init`
- estados de `prod_worker_ot`
- significado exacto de `ag_equipotype_id`
- maestro de equipos para `ag_equipo_id` y `win_equipoid`
- catálogo de pausas para `evt_pause_id`
- catálogo de mantenciones para `evt_equipo_mantid`
- catálogo de unidades para `evt_medida_fromid` y `evt_medida_toid`

## Conclusión

La producción de operarios en `unibagqa` funciona como un modelo orientado a ejecución en planta:

- `user` identifica acceso
- `workers` identifica al operario
- `prod_worker_init` marca el inicio del operario en un equipo
- `prod_agenda` marca la programación del trabajo
- `prod_header` es la cabecera productiva
- `prod_worker_ot` une operario y agenda en una ejecución concreta
- `prod_worker_ot_events` guarda todo lo que ocurre durante esa ejecución

La tabla más importante para seguimiento operativo fino es:

- `prod_worker_ot_events`

La tabla más importante para unir el flujo completo es:

- `prod_worker_ot`

Y la tabla que conecta al operario real con el sistema es:

- `workers`, enlazada a `user`

## Archivos Fuente Usados

- `MAPA_COMPLETO_UNIBAGQA.md`
- `INFORME_MAPA_DATOS_UNIBAGQA_TRAZABILIDAD.md`
- `.tmp_unibagqa_describe.php`
- `.tmp_unibagqa_otmap.php`
- `.tmp_unibagqa_samples.php`
- `.tmp_unibagqa_prod_join.php`
